<?php
require 'koneksi.php';
$C = stok_ctx(); $D = $C['D'];
$f = [
    ['nama', 'Nama Material', 'text', null, 1], ['valtype', 'Valtype', 'select', VALTYPE, 1], ['satuan', 'Satuan', 'text', null, 0, 'Unit'],
    ['awal', 'Stok Awal (saldo per ' . tgl_tampil(STOK_AWAL_TANGGAL) . ')', 'number', null, 0, 0], ['min', 'Stok Minimum', 'number', null, 0, 0], ['ket', 'Keterangan', 'textarea'],
];
foreach ($f as &$x) if ($x[0] === 'awal' || $x[0] === 'min') $x[2] = 'number0'; unset($x);
$rows = stok_baris($C);
$mv = $C['mv'];
$_n = 0; foreach ($rows as $_x) if ($_x['master'] && $_x['status'] !== 'Tersedia') $_n++; @file_put_contents(cache_dir() . '/notif.txt', $_n);
$c = [
    'judul' => 'Data Material', 'sheet' => 'Data_Barang', 'data' => $rows, 'cols' => kolom('barang'), 'tambah' => 'Tambah Material', 'nomor' => true,
    'fields_tambah' => $f, 'fields_ubah' => $f, 'sheets' => array_merge(['Data_Barang'], mod_sheets(1), mod_sheets(-1)),
    'fmt' => ['stok' => 'fm_stok', 'status' => 'fm_badge'],
    'filter' => ['valtype' => 'Semua valtype', 'status' => 'Semua status'],
    'detail' => function ($r) use ($mv) {
        $k = mkey($r['nama']) . '|' . mkey($r['valtype']); $l = [];
        foreach ($mv as $m) if ($m[1] === $k) $l[] = $m;
        usort($l, function ($a, $b) { return strcmp($b[0], $a[0]); });
        $t = []; foreach (array_slice($l, 0, 8) as $m) $t[] = tgl_tampil($m[0]) . '  ' . $m[3] . '  ' . ($m[2] > 0 ? '+' : '') . $m[2] . ($m[4] !== '' ? '  SN ' . str_replace("\n", ', ', $m[4]) : '');
        return ['Nama Material' => $r['nama'], 'Valtype' => $r['valtype'], 'Satuan' => $r['satuan'], 'Stok Awal (per ' . tgl_tampil(STOK_AWAL_TANGGAL) . ')' => $r['awal'], 'Masuk (sesudah stok awal)' => $r['masuk'], 'Keluar / pengambilan (sesudah stok awal)' => $r['keluar'],
            'Stok Akhir' => $r['stok'], 'Stok Minimum' => $r['min'], 'Status' => $r['status'], 'Keterangan' => $r['ket'], 'Pergerakan terbaru' => $t ? implode("\n", $t) : 'Belum ada pergerakan sesudah stok awal'];
    },
    'aksi' => function ($r) {
        $d = btn_detail();
        if ($r['master']) return $d . ib(url(['edit' => $r['_r']]), 'pencil-square', 'Ubah') . btn_hapus('Data_Barang', $r, $r['nama'] . ' ' . $r['valtype']);
        return $d . ib(url(['baru' => $r['nama'], 'bv' => $r['valtype']]), 'plus-circle', 'Daftarkan ke Data Material');
    },
];
if (isset($_GET['baru'])) $c['prefill'] = ['nama' => $_GET['baru'], 'valtype' => $_GET['bv'] ?? '', 'satuan' => 'Unit', 'awal' => 0, 'min' => 0];
halaman_modul($c);
