<?php
require 'koneksi.php';
$C = stok_ctx(); $D = $C['D'];
$lain = ['Pengambilan_Ggn', 'Pengambilan_ICRM', 'Pengambilan_Aktivasi', 'Pengambilan_HAR', 'Pengambilan_Pembangunan'];
$DL = ambil($lain);
$In = $D['Barang_Masuk']; $Out = $D['Barang_Keluar']; $Rt = $D['Pengambilan_Ritel']; $Ot = []; foreach ($DL as $r) $Ot = array_merge($Ot, $r);

// ----- periode & filter -----
$tahunList = daftar_tahun([$In, $Out, $Rt, $Ot]);
$per = in_array($_GET['periode'] ?? '', ['harian', 'bulanan', 'tahunan'], true) ? $_GET['periode'] : 'bulanan';
$thn = (int)($_GET['tahun'] ?? date('Y')); if (!in_array($thn, $tahunList, true)) $thn = (int)date('Y');
$bln = (int)($_GET['bulan'] ?? date('n')); if ($bln < 1 || $bln > 12) $bln = (int)date('n');
if ($per === 'harian') { $a = sprintf('%04d-%02d-01', $thn, $bln); $b = date('Y-m-t', strtotime($a)); $label = BULAN[$bln - 1] . ' ' . $thn; }
elseif ($per === 'bulanan') { $a = "$thn-01-01"; $b = "$thn-12-31"; $label = 'Tahun ' . $thn; }
else { $a = '0000-00-00'; $b = '9999-12-31'; $label = 'Semua tahun (' . min($tahunList) . ' - ' . max($tahunList) . ')'; }

// ----- agregasi sekali jalan -----
$M = agregat_harian($In); $K = agregat_harian($Out); $R = agregat_harian($Rt); $O = agregat_harian($Ot);
$St = array_values(array_filter(stok_baris($C, $per === 'tahunan' ? null : $b), function ($x) { return $x['master']; }));
$totStok = 0; foreach ($St as $x) $totStok += $x['stok'];
if ($per !== 'tahunan' && $b < STOK_AWAL_TANGGAL) $totStok = stok_total_pada($C, $b);
$habis = array_values(array_filter($St, function ($x) { return $x['status'] === 'Habis'; }));
$tipis = array_values(array_filter($St, function ($x) { return $x['status'] === 'Menipis'; }));
$nM = jumlah_rentang($M, $a, $b); $nK = jumlah_rentang($K, $a, $b); $nR = jumlah_rentang($R, $a, $b); $nO = jumlah_rentang($O, $a, $b);
$cutL = tgl_tampil(STOK_AWAL_TANGGAL);
$_n = 0; foreach (stok_baris($C) as $_x) if ($_x['master'] && $_x['status'] !== 'Tersedia') $_n++; @file_put_contents(cache_dir() . '/notif.txt', $_n);
$Cd = [
  ['Total Jenis Material', count($St), 'material terdaftar', 'boxes', 'c1'],
  ['Total Stok Material', $totStok, ($per !== 'tahunan' && $b < STOK_AWAL_TANGGAL ? 'perkiraan akhir periode (dihitung mundur dari stok awal ' . $cutL . ')' : 'stok ' . ($per === 'tahunan' ? 'saat ini' : 'akhir periode')), 'stack', 'c2'],
  ['Total Material Masuk', $nM, 'keseluruhan ' . array_sum($M), 'box-arrow-in-down', 'c3'],
  ['Total Material Keluar', $nK, 'keseluruhan ' . array_sum($K), 'box-arrow-up', 'c4'],
  ['Total Pengambilan Ritel', $nR, 'keseluruhan ' . array_sum($R), 'shop', 'c5'],
  ['Pengambilan Lainnya', $nO, 'ggn, ICRM, aktivasi, HAR, pembangunan', 'collection', 'c8'],
  ['Stok Menipis', count($tipis), 'di bawah stok minimum', 'exclamation-triangle', 'c6'],
  ['Stok Habis', count($habis), 'stok sama dengan nol', 'x-octagon', 'c7'],
];
$pm = []; $py = [];
foreach ([[$M, 'm'], [$K, 'k'], [$R, 'r'], [$O, 'o']] as $z) foreach ($z[0] as $k => $v) { $pm[substr($k, 0, 7)][$z[1]] = ($pm[substr($k, 0, 7)][$z[1]] ?? 0) + $v; $py[substr($k, 0, 4)][$z[1]] = ($py[substr($k, 0, 4)][$z[1]] ?? 0) + $v; }
$hari = (int)date('t', strtotime(sprintf('%04d-%02d-01', $thn, $bln)));
$H = ['l' => [], 'm' => [], 'k' => [], 'r' => [], 'o' => []];
for ($d = 1; $d <= $hari; $d++) { $k = sprintf('%04d-%02d-%02d', $thn, $bln, $d); $H['l'][] = $d; $H['m'][] = $M[$k] ?? 0; $H['k'][] = $K[$k] ?? 0; $H['r'][] = $R[$k] ?? 0; $H['o'][] = $O[$k] ?? 0; }
$B = ['l' => [], 'm' => [], 'k' => [], 'r' => [], 'o' => []];
for ($i = 1; $i <= 12; $i++) { $k = sprintf('%04d-%02d', $thn, $i); $B['l'][] = substr(BULAN[$i - 1], 0, 3); foreach (['m', 'k', 'r', 'o'] as $z) $B[$z][] = $pm[$k][$z] ?? 0; }
$Y = ['l' => [], 'm' => [], 'k' => [], 'r' => [], 'o' => []];
foreach (array_reverse($tahunList) as $y) { $Y['l'][] = (string)$y; foreach (['m', 'k', 'r', 'o'] as $z) $Y[$z][] = $py[$y][$z] ?? 0; }
// pergerakan stok: selisih (masuk - pengambilan) dari seluruh transaksi + stok akhir per periode
$G = ['l' => [], 'stok' => []];
if ($per === 'harian') { for ($d = 1; $d <= $hari; $d++) { $G['l'][] = $d; $G['stok'][] = stok_total_pada($C, sprintf('%04d-%02d-%02d', $thn, $bln, $d)); } }
elseif ($per === 'bulanan') { for ($i = 0; $i < 12; $i++) { $G['l'][] = $B['l'][$i]; $G['stok'][] = stok_total_pada($C, date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $thn, $i + 1)))); } }
else { foreach (array_reverse($tahunList) as $i => $y) { $G['l'][] = (string)$y; $G['stok'][] = stok_total_pada($C, "$y-12-31"); } }
$vt = []; foreach ($St as $x) { $k = $x['valtype'] !== '' ? $x['valtype'] : 'Tanpa Valtype'; $vt[$k] = ($vt[$k] ?? 0) + max(0, $x['stok']); }
$mt = []; foreach ($Rt as $x) { if ($per === 'tahunan' || (iso($x['tgl']) && $x['tgl'] >= $a && $x['tgl'] <= $b)) { $k = $x['mitra'] !== '' ? $x['mitra'] : '-'; $mt[$k] = ($mt[$k] ?? 0) + unit($x['sn']); } } arsort($mt);
$CH = ['H' => $H, 'B' => $B, 'Y' => $Y, 'G' => $G, 'vt' => array_keys($vt), 'vtv' => array_values($vt), 'mt' => array_keys(array_slice($mt, 0, 10, true)), 'mtv' => array_values(array_slice($mt, 0, 10, true))];
$terbaru = array_slice(array_values(array_filter(riwayat(), function ($x) use ($a, $b, $per) { return $per === 'tahunan' || (iso($x['tgl']) && $x['tgl'] >= $a && $x['tgl'] <= $b); })), 0, 8);
$perhatian = array_slice(array_merge($habis, $tipis), 0, 10);
$judul = 'Dashboard'; $charts = true; $sheets = array_merge(['Data_Barang'], mod_sheets()); include 'header.php';
$sel = function ($v, $c) { return $v == $c ? ' selected' : ''; };
?>
<form method="get" class="card fbar"><div class="fb-t"><i class="bi bi-funnel"></i> Periode data: <b><?= h($label) ?></b></div>
  <div class="tools">
    <select name="periode" onchange="this.form.submit()" title="Periode"><option value="harian"<?= $sel($per, 'harian') ?>>Harian</option><option value="bulanan"<?= $sel($per, 'bulanan') ?>>Bulanan</option><option value="tahunan"<?= $sel($per, 'tahunan') ?>>Tahunan</option></select>
    <select name="bulan" onchange="this.form.submit()" title="Bulan" class="<?= $per === 'harian' ? '' : 'mute' ?>"><?php foreach (BULAN as $i => $nm) echo '<option value="' . ($i + 1) . '"' . $sel($bln, $i + 1) . '>' . $nm . '</option>'; ?></select>
    <select name="tahun" onchange="this.form.submit()" title="Tahun" class="<?= $per === 'tahunan' ? 'mute' : '' ?>"><?php foreach ($tahunList as $y) echo '<option' . $sel($thn, $y) . '>' . $y . '</option>'; ?></select>
  </div></form>
<div class="cards">
<?php foreach ($Cd as $c): ?>
  <div class="sc <?= $c[4] ?>"><div><small><?= h($c[0]) ?></small><b class="cnt" data-v="<?= (int)$c[1] ?>"><?= (int)$c[1] ?></b><span><?= h($c[2]) ?></span></div><div class="sc-i"><i class="bi bi-<?= $c[3] ?>"></i></div></div>
<?php endforeach; ?>
</div>
<div class="g2">
  <div class="card"><h3 class="ct">Aktivitas Harian <small><?= h(BULAN[$bln - 1] . ' ' . $thn) ?>, tanggal 1 - <?= $hari ?></small></h3><div class="cv"><canvas id="c1"></canvas></div></div>
  <div class="card"><h3 class="ct">Grafik Bulanan <small>Januari - Desember <?= $thn ?></small></h3><div class="cv"><canvas id="c2"></canvas></div></div>
</div>
<div class="g2">
  <div class="card"><h3 class="ct">Perbandingan Tahunan <small><?= min($tahunList) ?> - <?= max($tahunList) ?></small></h3><div class="cv"><canvas id="c3"></canvas></div></div>
  <div class="card"><h3 class="ct">Pergerakan Stok <small><?= h($label) ?></small></h3><div class="cv"><canvas id="c4"></canvas></div>
    <p class="ring">Stok akhir tiap periode, dihitung dari stok Kartu gantung per <?= h($cutL) ?> dengan seluruh transaksi yang tercatat. Penerimaan barang yang tidak tercatat di Excel tidak ikut terhitung, sehingga angka sebelum <?= h($cutL) ?> adalah perkiraan.</p></div>
</div>
<div class="g2">
  <div class="card"><h3 class="ct">Stok Berdasarkan Valtype</h3><div class="cv"><canvas id="c5"></canvas></div><?php if (!$vt) echo '<p class="em">Belum ada data.</p>'; ?></div>
  <div class="card"><h3 class="ct">Pengambilan Ritel per Mitra <small><?= h($label) ?></small></h3><div class="cv"><canvas id="c6"></canvas></div><?php if (!$mt) echo '<p class="em">Tidak ada pengambilan ritel pada periode ini.</p>'; ?></div>
</div>
<div class="g2">
  <div class="card"><h3 class="ct">Aktivitas Transaksi Terbaru <small><?= h($label) ?></small></h3>
    <div class="tw"><table class="mini"><thead><tr><th>Tanggal</th><th>Jenis</th><th>Material</th><th>SN</th><th>Pihak</th></tr></thead><tbody>
    <?php foreach ($terbaru as $x): ?>
      <tr><td><?= h(tgl_tampil($x['tgl'])) ?></td><td><?= bd($x['jenis']) ?></td><td><?= h($x['material']) ?></td><td><?= h(strtok($x['sn'], "\n")) ?></td><td><?= h($x['oleh']) ?></td></tr>
    <?php endforeach; if (!$terbaru) echo '<tr><td colspan="5" class="em">Tidak ada transaksi pada periode ini.</td></tr>'; ?>
    </tbody></table></div>
    <p class="ring"><a href="riwayat.php">Lihat seluruh riwayat</a></p></div>
  <div class="card" id="peringatan"><h3 class="ct">Peringatan Stok <small><?= $per === 'tahunan' ? 'saat ini' : 'akhir periode' ?></small></h3>
    <?php foreach ($perhatian as $x): $w = $x['min'] > 0 ? min(100, max(3, round($x['stok'] / $x['min'] * 100))) : 3; ?>
      <div class="bar"><div><span><i class="bi bi-exclamation-triangle-fill" style="color:<?= $x['status'] === 'Habis' ? '#FF0000' : '#e0a100' ?>"></i> <?= h($x['nama']) ?> <small><?= h($x['valtype']) ?></small></span><b><?= h($x['stok']) ?> / <?= h($x['min']) ?></b></div><i><u style="width:<?= $w ?>%;background:<?= $x['status'] === 'Habis' ? '#FF0000' : '#f5b800' ?>"></u></i></div>
    <?php endforeach; if (!$perhatian) echo '<p class="em">Semua stok aman.</p>'; ?>
  </div>
</div>
<script>window.CH = <?= json_encode($CH, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php include 'footer.php'; ?>
