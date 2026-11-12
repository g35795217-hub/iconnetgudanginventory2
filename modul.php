<?php
// Halaman transaksi generik berdasarkan registri MOD() di fungsi.php
function opt_populer($rows, $k, $maks = 150) {
    $c = [];
    foreach ($rows as $x) { $v = trim($x[$k] ?? ''); if ($v !== '') $c[$v] = ($c[$v] ?? 0) + 1; }
    arsort($c); return array_slice(array_keys($c), 0, $maks);
}
function modul_fields($m, $tambah, $D, $rows) {
    $f = [];
    foreach ($m['kolom'] as $c) {
        if ($c === 'no') continue;
        $lab = $m['lab'][$c]; $req = in_array($c, $m['wajib'], true) ? 1 : 0;
        if ($c === 'tgl') $f[] = ['tgl', $lab, 'date', null, 1, date('Y-m-d')];
        elseif ($c === 'valtype') $f[] = ['valtype', $lab, 'select', VALTYPE, $req];
        elseif ($c === 'sn') {
            $f[] = ['sn', $m['qty'] ? 'SN' : 'SN / Serial Number', 'text', null, 0];
            if ($tambah && $m['qty']) $f[] = ['jumlah', 'Jumlah (isi bila tanpa SN)', 'number', null, 0, 1];
        }
        elseif ($c === 'ket') $f[] = ['ket', $lab, 'text', null, 0];
        elseif ($c === $m['mat'] && $m['arah'] !== 0) $f[] = [$c, $lab, 'text', null, $req];
        else $f[] = [$c, $lab, in_array($c, ['wo', 'pa', 'perangkat'], true) ? 'text' : 'text', null, $req];
    }
    return $f;
}
function btn_kosongsn($sheet, $r, $label) {
    return '<form method="post" action="proses.php" class="inl" onsubmit="return confirm(\'Kosongkan SN pada ' . h(addslashes($label)) . '? Baris tetap ada sebagai unit tanpa SN.\')">'
        . '<input type="hidden" name="aksi" value="kosongsn"><input type="hidden" name="sheet" value="' . h($sheet) . '"><input type="hidden" name="_r" value="' . h($r['_r']) . '"><input type="hidden" name="_s" value="' . h($r['_s']) . '">'
        . '<input type="hidden" name="kembali" value="' . h(basename($_SERVER['PHP_SELF'])) . '"><button class="ib" title="Kosongkan SN"><i class="bi bi-eraser"></i></button></form>';
}
function btn_detail() { return '<button type="button" class="ib" data-det title="Detail"><i class="bi bi-eye"></i></button>'; }
function modul_detail($m) {
    return function ($r) use ($m) { $o = []; foreach ($m['kol'] as $c => $l) $o[$l] = $c === 'tgl' ? tgl_tampil($r[$c] ?? '') : ($r[$c] ?? ''); return $o; };
}
function modul_aksi($m) {
    return function ($r) use ($m) {
        $lab = ($r[$m['mat']] ?? '') . ' ' . ($r['sn'] ?? '');
        $link = $m['file'] . (strpos($m['file'], '?') !== false ? '&' : '?');
        return btn_detail() . '<a class="ib" href="' . h($link . 'edit=' . $r['_r']) . '" title="Ubah"><i class="bi bi-pencil-square"></i></a>'
            . '<a class="ib" target="_blank" href="cetak.php?m=' . h($m['key']) . '&r=' . urlencode($r['_r']) . '" title="Cetak bukti"><i class="bi bi-printer"></i></a>'
            . (($m['qty'] && trim($r['sn'] ?? '') !== '') ? btn_kosongsn($m['sheet'], $r, trim($lab)) : '') . btn_hapus($m['sheet'], $r, trim($lab));
    };
}
function modul_halaman($key) {
    $M = MOD(); if (!isset($M[$key])) ke('index.php'); $m = $M[$key];
    $grup = $m['arah'] === -1 ? mod_sheets(-1) : ($key === 'masuk' ? ['Barang_Masuk'] : []);
    $names = array_values(array_unique(array_merge([$m['sheet'], 'Data_Barang'], $grup)));
    $D = ambil($names); $rows = $D[$m['sheet']];
    if ($grup && in_array('sn', $m['kolom'], true)) $GLOBALS['DUPMAP'] = dup_map($grup);
    $filter = [];
    foreach ($m['filter'] as $k => $l) $filter[$k] = $l;
    $extra = '';
    if ($key === 'masuk' && empty($_GET['ajax'])) {   // Valtype otomatis: nama material -> valtype dari Data Material (atau riwayat masuk)
        $MV = [];
        foreach (array_reverse($D['Barang_Masuk']) as $x) if ($x['valtype'] !== '' && mkey($x['nama']) !== '') $MV[mkey($x['nama'])] = $x['valtype'];
        foreach ($D['Data_Barang'] as $x) if ($x['valtype'] !== '' && mkey($x['nama']) !== '') $MV[mkey($x['nama'])] = $x['valtype'];
        $extra = '<script>window.MV = ' . json_encode($MV, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
    }
    halaman_modul(['judul' => $m['judul'], 'sheet' => $m['sheet'], 'data' => $rows, 'cols' => $m['kol'], 'tambah' => 'Tambah ' . $m['judul'], 'balik' => true, 'tanggal' => true,
        'fields_tambah' => modul_fields($m, true, $D, $rows), 'fields_ubah' => modul_fields($m, false, $D, $rows),
        'fmt' => ['tgl' => 'fm_tgl', 'sn' => 'fm_sn'], 'filter' => $filter, 'aksi' => modul_aksi($m), 'detail' => modul_detail($m), 'sheets' => $names, 'extra' => $extra]);
}
