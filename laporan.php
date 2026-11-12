<?php
require 'koneksi.php';
$M = MOD();
$J = ['stok' => 'Laporan Stok (stok awal, masuk, keluar, stok akhir)'];
foreach ($M as $k => $m) $J[$k] = 'Laporan ' . $m['judul'];
$jenis = isset($J[$_GET['jenis'] ?? '']) ? $_GET['jenis'] : 'stok';
$dari = $_GET['dari'] ?? ''; $sampai = $_GET['sampai'] ?? ''; $mat = trim($_GET['material'] ?? ''); $vt = $_GET['valtype'] ?? '';
if ($jenis === 'stok') {
    if ($dari === '') $dari = date('Y-m-01'); if ($sampai === '') $sampai = date('Y-m-d');
    $C = stok_ctx(); $hari0 = date('Y-m-d', strtotime($dari . ' -1 day'));
    $A = []; foreach (stok_baris($C, $hari0, true) as $r) $A[mkey($r['nama']) . '|' . mkey($r['valtype'])] = $r;
    $Z = []; foreach (stok_baris($C, $sampai, true) as $r) $Z[mkey($r['nama']) . '|' . mkey($r['valtype'])] = $r;
    $mi = []; $ko = [];
    foreach ($C['mv'] as $x) if ($x[0] >= $dari && $x[0] <= $sampai) { if ($x[2] > 0) $mi[$x[1]] = ($mi[$x[1]] ?? 0) + $x[2]; else $ko[$x[1]] = ($ko[$x[1]] ?? 0) - $x[2]; }
    $rows = [];
    foreach ($C['rows'] as $k => $r) {
        $masuk = $mi[$k] ?? 0; $keluar = $ko[$k] ?? 0;
        if (!$r['master'] && $masuk + $keluar === 0) continue;
        if ($mat !== '' && stripos($r['nama'], $mat) === false) continue; if ($vt !== '' && $r['valtype'] !== $vt) continue;
        $rows[] = ['nama' => $r['nama'], 'valtype' => $r['valtype'], 'satuan' => $r['satuan'], 'awal' => $r['master'] ? $A[$k]['stok'] : '', 'masuk' => $masuk, 'keluar' => $keluar, 'stok' => $r['master'] ? $Z[$k]['stok'] : '', 'status' => $r['master'] ? $Z[$k]['status'] : 'Belum terdaftar'];
    }
    usort($rows, function ($a, $b) { return strcasecmp($a['nama'], $b['nama']); });
    $cols = ['nama' => 'Nama Material', 'valtype' => 'Valtype', 'satuan' => 'Satuan', 'awal' => 'Stok Awal', 'masuk' => 'Masuk', 'keluar' => 'Keluar', 'stok' => 'Stok Akhir', 'status' => 'Status'];
    $T = tabel_siap($cols, $rows, ['judul' => 'Laporan Stok ' . tgl_tampil($dari) . ' - ' . tgl_tampil($sampai), 'per' => 20, 'nomor' => true, 'fmt' => ['status' => 'fm_badge', 'stok' => 'fm_stok']]);
} else {
    $m = $M[$jenis]; $D = ambil([$m['sheet']]); $rows = $D[$m['sheet']];
    $rows = array_values(array_filter($rows, function ($x) use ($m, $mat, $vt) {
        if ($mat !== '' && stripos($x[$m['mat']] ?? '', $mat) === false) return false;
        if ($vt !== '' && ($x['valtype'] ?? '') !== $vt) return false; return true; }));
    $T = tabel_siap($m['kol'], $rows, ['judul' => $J[$jenis], 'per' => 20, 'tanggal' => true, 'balik' => true, 'filter' => $m['filter'], 'fmt' => ['tgl' => 'fm_tgl', 'sn' => 'fm_sn']]);
}
if (!empty($_GET['ajax'])) { tabel_tampil($T); exit; }
$judul = 'Laporan'; $sheets = $jenis === 'stok' ? array_merge(['Data_Barang'], mod_sheets(1), mod_sheets(-1)) : [$M[$jenis]['sheet']]; include 'header.php';
?>
<form method="get" class="card" style="margin-bottom:16px"><div class="ch"><h3>Filter laporan</h3><div class="tools">
  <select name="jenis" onchange="this.form.submit()"><?php foreach ($J as $k => $l) echo '<option value="' . $k . '"' . ($k === $jenis ? ' selected' : '') . '>' . h($l) . '</option>'; ?></select>
  <?php if ($jenis === 'stok'): ?>
  <label class="inl">Dari <input type="date" name="dari" value="<?= h($dari) ?>"></label><label class="inl">Sampai <input type="date" name="sampai" value="<?= h($sampai) ?>"></label>
  <?php endif; ?>
  <input name="material" autocomplete="off" placeholder="Cari material" value="<?= h($mat) ?>" style="width:170px">
  <select name="valtype"><option value="">Semua valtype</option><?php foreach (VALTYPE as $v) echo '<option' . ($v === $vt ? ' selected' : '') . '>' . h($v) . '</option>'; ?></select>
  <button class="btn y"><i class="bi bi-funnel"></i> Terapkan</button></div></div></form>
<?php
tabel_tampil($T);
if ($jenis === 'stok') echo '<p class="ring">Stok awal Data Material per ' . h(tgl_tampil(STOK_AWAL_TANGGAL)) . ' (Kartu gantung). Material yang belum terdaftar di Data Material hanya menampilkan pergerakan.</p>';
else echo '<p class="ring">Total unit pada laporan ini: <b>' . jumlah_unit($T['total'] <= 50000 ? $rows : []) . '</b> (' . $T['total'] . ' baris sesuai pencarian)</p>';
include 'footer.php';
