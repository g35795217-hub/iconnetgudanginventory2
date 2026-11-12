<?php
require 'koneksi.php';
$R = riwayat(); $M = MOD();
$T = tabel_siap(['tgl' => 'Tanggal', 'jenis' => 'Jenis', 'material' => 'Material', 'valtype' => 'Valtype', 'sn' => 'SN', 'oleh' => 'Pihak Terkait', 'ket' => 'Keterangan'], $R,
  ['judul' => 'Riwayat Transaksi', 'per' => 20, 'terbaru' => true, 'tanggal' => true, 'filter' => ['jenis' => 'Semua jenis', 'valtype' => 'Semua valtype'], 'fmt' => ['tgl' => 'fm_tgl', 'jenis' => 'fm_badge', 'sn' => 'fm_sn'],
   'detail' => function ($r) use ($M) { $m = $M[$r['_m']]; $o = ['Jenis transaksi' => $m['judul']]; foreach ($m['kol'] as $c => $l) $o[$l] = $c === 'tgl' ? tgl_tampil($r['_row'][$c] ?? '') : ($r['_row'][$c] ?? ''); return $o; },
   'aksi' => function ($r) use ($M) { $m = $M[$r['_m']]; $link = $m['file'] . (strpos($m['file'], '?') !== false ? '&' : '?');
        return btn_detail() . '<a class="ib" href="' . h($link . 'edit=' . $r['_r']) . '" title="Ubah di halaman ' . h($m['judul']) . '"><i class="bi bi-pencil-square"></i></a>' . btn_hapus($m['sheet'], $r, $m['judul'] . ' ' . $r['material'] . ' ' . $r['sn']); }]);
if (!empty($_GET['ajax'])) { tabel_tampil($T); exit; }
$judul = 'Riwayat Transaksi'; $sheets = mod_sheets(); include 'header.php';
tabel_tampil($T);
include 'footer.php';
