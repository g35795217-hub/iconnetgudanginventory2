<?php
// Pembuat hash kata sandi. HANYA bisa dibuka dari komputer lokal (localhost). Di hosting halaman ini menolak akses.
require 'koneksi.php';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1'], true)) { http_response_code(403); exit('Halaman ini hanya dapat dibuka dari localhost. Buat hash di XAMPP lalu salin ke config.local.php di hosting.'); }
$hash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p = (string)($_POST['pass'] ?? '');
    if (strlen($p) < 8) $hash = 'ERR';
    else $hash = password_hash($p, PASSWORD_DEFAULT);
}
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Buat kata sandi</title>
<link rel="stylesheet" href="assets/vendor/poppins.css"><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/glass.css"></head>
<body class="tema-terang lg-body">
<form method="post" class="lg-box" autocomplete="off">
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
  <h1>Buat kata sandi admin</h1>
  <p>Ketik kata sandi baru (minimal 8 karakter), salin hasilnya ke <b>ADMIN_HASH</b> di config.local.php.</p>
  <label>Kata sandi<input name="pass" type="text" required minlength="8"></label>
  <button class="btn y lg-btn">Buat hash</button>
  <?php if ($hash === 'ERR'): ?><div class="fl e">Minimal 8 karakter.</div>
  <?php elseif ($hash): ?><label style="margin-top:12px">Hasil (salin seluruhnya)<textarea rows="3" readonly onclick="this.select()"><?= h($hash) ?></textarea></label>
  <p>Contoh baris: <code>define('ADMIN_HASH', '...hash...');</code></p><?php endif; ?>
</form>
</body></html>
