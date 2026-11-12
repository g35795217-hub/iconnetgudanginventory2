<?php
$grupLain = [['lainnya.php?m=ggn', 'Pengambilan Gangguan', 'exclamation-diamond'], ['lainnya.php?m=icrm', 'Aktivasi ICRM', 'router'], ['lainnya.php?m=aktivasi', 'Pengambilan Aktivasi', 'lightning-charge'], ['lainnya.php?m=har', 'Pengambilan HAR', 'tools'], ['lainnya.php?m=pembangunan', 'Pengambilan Pembangunan', 'building']];
$menus = [['index.php', 'Dashboard', 'grid-1x2'], ['barang.php', 'Data Material', 'box-seam'], ['barang_masuk.php', 'Material Masuk', 'box-arrow-in-down'],
          ['barang_keluar.php', 'Material Keluar', 'box-arrow-up'], ['ritel.php', 'Pengambilan Ritel', 'shop'], 'GRUP',
          ['master_sn.php', 'Master SN', 'upc-scan'], ['riwayat.php', 'Riwayat Transaksi', 'clock-history'], ['laporan.php', 'Laporan', 'file-earmark-bar-graph'], ['import.php', 'Import Excel', 'file-earmark-arrow-up'], ['sinkron.php', 'Sinkron Spreadsheet', 'cloud-arrow-down'], ['backup.php', 'Backup Data', 'shield-check']];
$aktif = basename($_SERVER['PHP_SELF']) . (isset($_GET['m']) && basename($_SERVER['PHP_SELF']) === 'lainnya.php' ? '?m=' . $_GET['m'] : '');
$notifF = cache_dir() . '/notif.txt'; $notif = is_file($notifF) ? (int)@file_get_contents($notifF) : 0;
$sheets = $sheets ?? [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf" content="<?= h(csrf_token()) ?>">
<meta name="theme-color" content="#035B71">
<title><?= h($judul) ?> - PLN Icon Plus Gudang Inventory</title>
<link rel="icon" href="assets/logo.png">
<link rel="stylesheet" href="assets/vendor/poppins.css">
<link rel="stylesheet" href="assets/vendor/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
<link rel="stylesheet" href="assets/glass.css?v=<?= filemtime(__DIR__ . '/assets/glass.css') ?>">
<script>window.VER = <?= json_encode($sheets ? lembar_ver($sheets) : '') ?>; window.SH = <?= json_encode(implode(',', $sheets)) ?>;</script>
</head>
<body class="<?= defined('TEMA') && TEMA === 'terang' ? 'tema-terang' : 'tema-gelap' ?>">
<aside class="sb" id="sb">
  <div class="brand">
    <div class="logo"><img src="assets/logo.png" alt="PLN Icon Plus"></div>
    <span>Gudang Inventory</span>
  </div>
  <nav>
    <?php foreach ($menus as $m): if ($m === 'GRUP'): ?>
      <div class="grp">Pengambilan Lainnya</div>
      <?php foreach ($grupLain as $g): ?><a class="sub <?= $aktif === $g[0] ? 'on' : '' ?>" href="<?= $g[0] ?>"><i class="bi bi-<?= $g[2] ?>"></i><span><?= h($g[1]) ?></span></a><?php endforeach; ?>
      <div class="grp">Data dan Laporan</div>
    <?php else: ?>
      <a href="<?= $m[0] ?>" class="<?= $aktif === $m[0] ? 'on' : '' ?>"><i class="bi bi-<?= $m[2] ?>"></i><span><?= h($m[1]) ?></span><?php if ($m[0] === 'barang.php' && $notif): ?><em class="nb"><?= $notif ?></em><?php endif; ?></a>
    <?php endif; endforeach; ?>
  </nav>
</aside>
<div class="mn">
  <header>
    <button class="hb" onclick="document.getElementById('sb').classList.toggle('open')"><i class="bi bi-list"></i></button>
    <div><h2><?= h($judul) ?></h2><small>Beranda / <?= h($judul) ?></small></div>
    <div class="hr">
      <a class="btn" href="<?= h(url(['fresh' => 1, 'ajax' => null])) ?>" title="Baca ulang data dari spreadsheet"><i class="bi bi-arrow-repeat"></i><span class="hd-t"> Muat ulang</span></a>
      <span class="dt"><i class="bi bi-calendar3"></i> <?= date('d/m/Y') ?></span>
      <?php if (AUTH_AKTIF): ?><a class="btn" href="logout.php?t=<?= h(csrf_token()) ?>" title="Keluar"><i class="bi bi-box-arrow-right"></i><span class="hd-t"> Keluar</span></a><?php endif; ?>
      <a class="bell" href="index.php#peringatan" title="Stok menipis / habis"><i class="bi bi-bell"></i><?php if ($notif): ?><span class="nbd"><?= $notif ?></span><?php endif; ?></a>
    </div>
  </header>
  <main class="pin">
<?php
if (APP_ENV === 'hosting' && !AUTH_AKTIF) echo '<div class="fl e"><i class="bi bi-exclamation-triangle"></i> Website sudah online tetapi <b>belum memakai login</b>: siapa pun yang tahu alamatnya dapat mengubah data. Isi ADMIN_HASH di config.local.php (lihat PANDUAN.md).</div>';
if (!empty($_SESSION['flash'])) {
    list($m, $t) = $_SESSION['flash']; unset($_SESSION['flash']);
    echo '<div class="fl ' . ($t === 'err' ? 'e' : '') . '"><i class="bi bi-' . ($t === 'err' ? 'x-circle' : 'check-circle') . '"></i> ' . $m . '</div>';
}
