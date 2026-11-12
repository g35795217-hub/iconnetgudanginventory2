<?php
require 'koneksi.php';
if (!AUTH_AKTIF || !empty($_SESSION['login'])) ke('index.php');
$err = '';
$f = cache_dir() . '/login_' . md5($_SERVER['REMOTE_ADDR'] ?? 'x') . '.json';
$st = is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
if (!empty($st['t']) && time() - $st['t'] > 900) $st = [];            // kunci 15 menit setelah 5 kali gagal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($st['n'] ?? 0) >= 5) {
        $err = 'Terlalu banyak percobaan gagal. Coba lagi sekitar 15 menit.';
    } else {
        $okU = hash_equals(ADMIN_USER, (string)($_POST['user'] ?? ''));
        $okP = password_verify((string)($_POST['pass'] ?? ''), ADMIN_HASH);
        if ($okU && $okP) {
            @unlink($f); session_regenerate_id(true);
            $_SESSION['login'] = 1; $_SESSION['aktif'] = time(); $_SESSION['csrf'] = bin2hex(random_bytes(32));
            ke('index.php');
        }
        $st = ['n' => ($st['n'] ?? 0) + 1, 't' => time()]; @file_put_contents($f, json_encode($st), LOCK_EX);
        $err = 'Nama pengguna atau kata sandi salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk - PLN Icon Plus Gudang Inventory</title>
<link rel="icon" href="assets/logo.png">
<link rel="stylesheet" href="assets/vendor/poppins.css"><link rel="stylesheet" href="assets/vendor/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/glass.css">
</head>
<body class="tema-terang lg-body">
<form method="post" class="lg-box" autocomplete="off">
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
  <div class="lg-logo"><img src="assets/logo.png" alt="PLN Icon Plus"></div>
  <h1>Gudang Inventory</h1>
  <p>Masuk untuk mengelola stok material</p>
  <?php if ($err): ?><div class="fl e"><i class="bi bi-x-circle"></i> <?= h($err) ?></div><?php endif; ?>
  <label>Nama pengguna<input name="user" required autofocus autocomplete="username"></label>
  <label>Kata sandi<input name="pass" type="password" required autocomplete="current-password"></label>
  <button class="btn y lg-btn"><i class="bi bi-box-arrow-in-right"></i> Masuk</button>
</form>
</body>
</html>
