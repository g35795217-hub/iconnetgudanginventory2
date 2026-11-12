<?php
// ============================================================
// KONEKSI + KEAMANAN DASAR. Pengaturan ada di config.local.php (bukan di file ini).
// Persyaratan: PHP 7.4 atau lebih baru (ekstensi curl, zip, xml, mbstring).
// ============================================================
$__cfg = __DIR__ . '/config.local.php';
if (is_file($__cfg)) require $__cfg;

defined('APPS_SCRIPT_URL')   || define('APPS_SCRIPT_URL', 'GANTI-APPS-SCRIPT-URL');
defined('API_KEY')           || define('API_KEY', 'GANTI-API-KEY');
defined('STOK_AWAL_TANGGAL') || define('STOK_AWAL_TANGGAL', '2026-11-05');
defined('TEMA')              || define('TEMA', 'terang');
defined('ADMIN_USER')        || define('ADMIN_USER', 'admin');
defined('ADMIN_HASH')        || define('ADMIN_HASH', '');
defined('TZ')                || define('TZ', 'Asia/Jakarta');

// Lingkungan: otomatis lokal bila dibuka lewat localhost / IP jaringan lokal.
$__host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));
$__lokal = $__host === 'localhost' || $__host === '127.0.0.1' || $__host === '[::1]' || substr($__host, -6) === '.local' || (bool)preg_match('/^(192\.168|10\.|172\.(1[6-9]|2\d|3[01]))\./', $__host);
defined('APP_ENV')    || define('APP_ENV', $__lokal ? 'lokal' : 'hosting');
defined('FORCE_HTTPS') || define('FORCE_HTTPS', APP_ENV === 'hosting');
define('AUTH_AKTIF', ADMIN_HASH !== '');
define('IS_HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || ($_SERVER['SERVER_PORT'] ?? '') == 443);

date_default_timezone_set(TZ);

// Galat: tampil di lokal, disembunyikan (dicatat ke cache/php-error.log) di hosting.
if (APP_ENV === 'lokal') { @ini_set('display_errors', '1'); error_reporting(E_ALL & ~E_DEPRECATED); }
else { @ini_set('display_errors', '0'); @ini_set('log_errors', '1'); @ini_set('error_log', __DIR__ . '/cache/php-error.log'); }

// HTTPS wajib di hosting (bisa dimatikan dengan define('FORCE_HTTPS', false) bila SSL belum aktif).
if (FORCE_HTTPS && !IS_HTTPS && PHP_SAPI !== 'cli') {
    header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301); exit;
}

// Header keamanan.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if (IS_HTTPS) header('Strict-Transport-Security: max-age=31536000');

// Sesi yang aman.
@ini_set('session.use_strict_mode', '1'); @ini_set('session.use_only_cookies', '1');
session_name('PLNGUDANG');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => IS_HTTPS, 'httponly' => true, 'samesite' => 'Lax']);
session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function csrf_token() { return $_SESSION['csrf']; }

ob_start();   // seluruh halaman dikirim sekaligus (lebih cepat, dan memungkinkan penyegaran cache di latar belakang)
require __DIR__ . '/fungsi.php';

// Proteksi CSRF: setiap POST wajib membawa token sesi (ditambahkan otomatis oleh assets/app.js ke semua form).
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $__t = (string)($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!hash_equals($_SESSION['csrf'], $__t)) {
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(403);
        exit('Sesi tidak valid atau sudah kedaluwarsa. Kembali ke halaman sebelumnya, muat ulang (F5), lalu coba lagi.');
    }
}

// Login (aktif bila ADMIN_HASH di config.local.php terisi).
$__skrip = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (AUTH_AKTIF && empty($_SESSION['login']) && !in_array($__skrip, ['login.php', 'buat_password.php'], true)) {
    while (ob_get_level() > 0) ob_end_clean();
    if ($__skrip === 'sync.php' || $__skrip === 'import_run.php' || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch')) {
        http_response_code(401); header('Content-Type: application/json');
        exit(json_encode(['ok' => false, 'error' => 'login', 'ver' => ''])); }
    header('Location: login.php'); exit;
}
// Sesi login berakhir bila tidak aktif 8 jam.
if (AUTH_AKTIF && !empty($_SESSION['login'])) {
    if (time() - ($_SESSION['aktif'] ?? 0) > 28800) { $_SESSION = []; session_destroy(); header('Location: login.php'); exit; }
    $_SESSION['aktif'] = time();
}
