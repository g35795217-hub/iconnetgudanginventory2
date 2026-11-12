<?php
require 'koneksi.php';
$D = ambil(['Master_SN']); $rows = $D['Master_SN'];
$T = tabel_siap(['kode' => 'Kode Material', 'nama' => 'Deskripsi Material', 'valtype' => 'Valtype', 'plnt' => 'Plnt', 'sloc' => 'SLoc', 'sn' => 'Serial Number', 'ket' => 'Keterangan', 'lokasi' => 'Lokasi'], $rows,
  ['judul' => 'Master SN (daftar serial SAP)', 'per' => 20, 'filter' => ['valtype' => 'Semua valtype', 'ket' => 'Semua keterangan'],
   'detail' => function ($r) { return ['Kode Material' => $r['kode'], 'Deskripsi' => $r['nama'], 'Valtype / Batch' => $r['valtype'], 'Plnt' => $r['plnt'], 'SLoc' => $r['sloc'], 'Serial Number' => $r['sn'], 'Keterangan' => $r['ket'], 'Lokasi' => $r['lokasi']]; },
   'aksi' => function ($r) { return btn_detail(); }]);
if (!empty($_GET['ajax'])) { tabel_tampil($T); exit; }
$judul = 'Master SN'; $sheets = ['Master_SN']; include 'header.php';
echo '<p class="ring" style="margin:0 0 12px">Daftar serial number dari SAP (sheet Sheet1-3 pada Excel). Hanya untuk dilihat dan dicari; diperbarui lewat menu Import Excel.</p>';
tabel_tampil($T);
include 'footer.php';
