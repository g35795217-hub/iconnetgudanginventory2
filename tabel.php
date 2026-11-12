<?php
// Tabel, formulir, ekspor, helper tampilan (dipakai semua halaman)
// ---------- tampilan kecil ----------
function bd($v) {
    $m = ['Tersedia' => 'g', 'Menipis' => 'o', 'Habis' => 'r', 'Masuk' => 'g', 'Keluar' => 'o', 'Ritel' => 'b', 'Belum terdaftar' => 'o'];
    return '<span class="bd ' . ($m[$v] ?? 'b') . '">' . h($v) . '</span>';
}
function fm_tgl($v) { return h(tgl_tampil($v)); }
function fm_badge($v) { return bd($v); }
function fm_sn($v, $r) {
    $t = nl2br(h($v)); $map = $GLOBALS['DUPMAP'] ?? null; $d = false;
    if ($map) foreach (sn_list($v) as $x) if (($map[strtoupper($x)] ?? 0) > 1) { $d = true; break; }
    return $d ? '<span class="dup" title="SN tercatat lebih dari satu kali">' . $t . '</span>' : $t;
}
function fm_stok($v, $r) { return '<b' . ($v < 0 ? ' style="color:#b91c1c"' : '') . '>' . h($v) . '</b>'; }
function btn_hapus($sheet, $r, $label) {
    return '<form method="post" action="proses.php" class="inl" onsubmit="return confirm(\'Hapus ' . h(addslashes($label)) . '? Baris juga dihapus dari spreadsheet.\')">'
        . '<input type="hidden" name="aksi" value="del"><input type="hidden" name="sheet" value="' . h($sheet) . '"><input type="hidden" name="_r" value="' . h($r['_r']) . '">'
        . '<input type="hidden" name="_s" value="' . h($r['_s']) . '"><input type="hidden" name="kembali" value="' . h(basename($_SERVER['PHP_SELF'])) . '"><button class="ib d" title="Hapus"><i class="bi bi-trash3"></i></button></form>';
}
function ib($href, $ico, $t) { return '<a class="ib" href="' . h($href) . '" title="' . h($t) . '"><i class="bi bi-' . $ico . '"></i></a>'; }
function opt_unik($rows, $k, $maks = 300) { $v = array_values(array_unique(array_filter(array_map('trim', array_column($rows, $k)), 'strlen'))); sort($v); return array_slice($v, 0, $maks); }
// ---------- tabel: cari, filter, urut, halaman, ekspor ----------
function tabel_siap($cols, $rows, $o = []) {
    $per = $o['per'] ?? 15; $q = trim($_GET['q'] ?? ''); $sort = $_GET['sort'] ?? ''; $dir = ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
    $opsi = [];
    foreach (($o['filter'] ?? []) as $k => $lbl) {
        $opsi[$k] = opt_unik($rows, $k);
        $sel = $_GET['f_' . $k] ?? '';
        if ($sel !== '') $rows = array_values(array_filter($rows, function ($r) use ($k, $sel) { return (string)($r[$k] ?? '') === $sel; }));
    }
    if (!empty($o['tanggal'])) {
        $d1 = $_GET['dari'] ?? ''; $d2 = $_GET['sampai'] ?? '';
        if ($d1 !== '' || $d2 !== '') $rows = array_values(array_filter($rows, function ($r) use ($d1, $d2) { return iso($r['tgl'] ?? '') && ($d1 === '' || $r['tgl'] >= $d1) && ($d2 === '' || $r['tgl'] <= $d2); }));
    }
    if ($q !== '') $rows = array_values(array_filter($rows, function ($r) use ($q) {
        return stripos(implode(' ', array_map('strval', array_filter(array_diff_key($r, ['_r' => 1, '_s' => 1, '_row' => 1, '_m' => 1]), 'is_scalar'))), $q) !== false; }));
    if ($sort !== '' && isset($cols[$sort])) usort($rows, function ($a, $b) use ($sort, $dir) {
        $x = $a[$sort] ?? ''; $y = $b[$sort] ?? '';
        $c = (is_numeric($x) && is_numeric($y)) ? ($x <=> $y) : strcasecmp((string)$x, (string)$y);
        return $dir === 'desc' ? -$c : $c;
    }); elseif (!empty($o['balik'])) $rows = array_reverse($rows);
    if (!empty($_GET['export'])) {   // web: terbaru di atas; Excel/PDF: lama di atas, terbaru di bawah (kecuali pengguna memilih urutan kolom sendiri)
        if (!($sort !== '' && isset($cols[$sort])) && (!empty($o['balik']) || !empty($o['terbaru']))) $rows = array_reverse($rows);
        tabel_ekspor($cols, $rows, $o, $_GET['export']);
    }
    $total = count($rows); $hal = max(1, (int)ceil($total / $per)); $p = min(max(1, (int)($_GET['p'] ?? 1)), $hal);
    return ['cols' => $cols, 'page' => array_slice($rows, ($p - 1) * $per, $per), 'p' => $p, 'hal' => $hal, 'total' => $total, 'per' => $per, 'o' => $o, 'q' => $q, 'sort' => $sort, 'dir' => $dir, 'opsi' => $opsi];
}
function cetak_halaman($isi) {
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>PLN Gudang Inventory</title><style>body{font:13px Arial;padding:28px;color:#111}h2{margin:0}.hd{border-bottom:4px solid #035B71;padding-bottom:10px;margin-bottom:16px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #999;padding:6px 8px;text-align:left;vertical-align:top}th{background:#035B71;color:#fff}.sg{display:flex;justify-content:space-around;margin-top:60px;text-align:center}</style></head><body><div class="hd"><h2>PLN Gudang Inventory</h2></div>' . $isi . '<script>window.onload=function(){window.print()}</script></body></html>';
    exit;
}
function tabel_matriks($cols, $rows) {
    $o = [];
    foreach ($rows as $r) { $l = []; foreach ($cols as $k => $_) $l[] = $k === 'tgl' ? tgl_tampil($r[$k] ?? '') : (string)($r[$k] ?? ''); $o[] = $l; }
    return $o;
}
function col_huruf($i) { $s = ''; $i++; while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = intdiv($i - 1, 26); } return $s; }
function xlsx_kirim($nama, $judulSheet, $head, $mat, $numCols) {
    $esc = function ($t) { return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)$t), ENT_XML1 | ENT_QUOTES, 'UTF-8'); };
    $w = []; foreach ($head as $i => $t) $w[$i] = max(10, strlen($t) + 2);
    foreach ($mat as $r) foreach ($r as $i => $t) $w[$i] = min(45, max($w[$i], max(array_map('strlen', explode("\n", (string)$t))) + 2));
    $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>';
    foreach ($w as $i => $c) $x .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $c . '" customWidth="1"/>';
    $x .= '</cols><sheetData><row r="1">';
    foreach ($head as $i => $t) $x .= '<c r="' . col_huruf($i) . '1" s="1" t="inlineStr"><is><t>' . $esc($t) . '</t></is></c>';
    $x .= '</row>';
    foreach ($mat as $ri => $r) {
        $x .= '<row r="' . ($ri + 2) . '">';
        foreach ($r as $i => $t) {
            $ref = col_huruf($i) . ($ri + 2);
            if (in_array($i, $numCols, true) && is_numeric($t)) $x .= '<c r="' . $ref . '"><v>' . $t . '</v></c>';
            elseif ($t !== '') $x .= '<c r="' . $ref . '" s="2" t="inlineStr"><is><t xml:space="preserve">' . $esc($t) . '</t></is></c>';
        }
        $x .= '</row>';
    }
    $x .= '</sheetData><autoFilter ref="A1:' . col_huruf(count($head) - 1) . '1"/></worksheet>';
    $st = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF035B71"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    $tmp = tempnam(sys_get_temp_dir(), 'xl'); $z = new ZipArchive(); $z->open($tmp, ZipArchive::OVERWRITE);
    $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
    $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $z->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $esc(substr(trim(preg_replace('/[^A-Za-z0-9 _-]+/', ' ', $judulSheet)) ?: 'Data', 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
    $z->addFromString('xl/styles.xml', $st);
    $z->addFromString('xl/worksheets/sheet1.xml', $x);
    $z->close();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nama . '.xlsx"'); header('Content-Length: ' . filesize($tmp));
    readfile($tmp); unlink($tmp); exit;
}
function tabel_ekspor($cols, $rows, $o, $tipe) {
    $nama = preg_replace('/[^A-Za-z0-9_-]+/', '-', $o['judul'] ?? 'Data') . '-' . date('Y-m-d');
    $mat = tabel_matriks($cols, $rows); $head = array_values($cols);
    if ($tipe === 'csv') {
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $nama . '.csv"');
        echo "\xEF\xBB\xBF"; $f = fopen('php://output', 'w'); fputcsv($f, $head, ',', '"', '\\');
        foreach ($mat as $l) fputcsv($f, $l, ',', '"', '\\');
        fclose($f); exit;
    }
    if ($tipe === 'xls') {
        $num = []; $i = 0; foreach ($cols as $k => $_) { if (in_array($k, ['no', 'awal', 'masuk', 'keluar', 'stok', 'min'], true)) $num[] = $i; $i++; }
        if (class_exists('ZipArchive')) xlsx_kirim($nama, $o['judul'] ?? 'Data', $head, $mat, $num);
        $t = '<table border="1"><tr>'; foreach ($head as $l) $t .= '<th>' . h($l) . '</th>'; $t .= '</tr>';
        foreach ($mat as $l) { $t .= '<tr>'; foreach ($l as $v) $t .= '<td style="mso-number-format:\'@\'">' . nl2br(h($v)) . '</td>'; $t .= '</tr>'; }
        header('Content-Type: application/vnd.ms-excel; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $nama . '.xls"');
        echo '<html><meta charset="utf-8">' . $t . '</table></html>'; exit;
    }
    $t = '<table><tr>'; foreach ($head as $l) $t .= '<th>' . h($l) . '</th>'; $t .= '</tr>';
    $cut = count($mat) > 3000; if ($cut) $mat = array_slice($mat, 0, 3000);
    foreach ($mat as $l) { $t .= '<tr>'; foreach ($l as $v) $t .= '<td>' . nl2br(h($v)) . '</td>'; $t .= '</tr>'; }
    if ($cut) $t .= '<tr><td colspan="99"><i>Tampilan cetak dibatasi 3000 baris pertama; gunakan Excel untuk seluruh data.</i></td></tr>';
    cetak_halaman('<h3>' . h($o['judul'] ?? 'Data') . '</h3><p>Dicetak ' . date('d-m-Y H:i') . '</p>' . $t . '</table>');
}
function tabel_tampil($T, $tambah = '') {
    $o = $T['o']; $cols = $T['cols'];
    echo '<div class="card" id="tbl"><div class="ch"><h3>' . h($o['judul']) . '</h3><div class="tools"><form method="get">';
    foreach ($_GET as $k => $v) {
        if (in_array($k, ['q', 'p', 'export', 'edit', 'baru', 'ajax', 'fresh', 'dari', 'sampai'])) continue;
        if (strpos($k, 'f_') === 0 && isset($o['filter'][substr($k, 2)])) continue;
        if (is_scalar($v)) echo '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    }
    echo '<div class="srw"><i class="bi bi-search"></i><input class="srch" type="search" name="q" autocomplete="off" placeholder="Cari..." value="' . h($T['q']) . '"></div>';
    if (!empty($o['tanggal'])) echo '<input type="date" name="dari" value="' . h($_GET['dari'] ?? '') . '" title="Dari tanggal"><input type="date" name="sampai" value="' . h($_GET['sampai'] ?? '') . '" title="Sampai tanggal">';
    foreach (($o['filter'] ?? []) as $k => $lbl) {
        echo '<select name="f_' . h($k) . '" onchange="this.form.requestSubmit?this.form.requestSubmit():this.form.submit()"><option value="">' . h($lbl) . '</option>';
        foreach ($T['opsi'][$k] as $v) echo '<option' . (($_GET['f_' . $k] ?? '') === (string)$v ? ' selected' : '') . '>' . h($v) . '</option>';
        echo '</select>';
    }
    echo '<button class="btn">Cari</button></form>' . $tambah;
    echo '<a class="btn" href="' . h(url(['export' => 'xls', 'p' => null])) . '"><i class="bi bi-file-earmark-excel"></i> Excel</a><a class="btn" target="_blank" href="' . h(url(['export' => 'pdf', 'p' => null])) . '"><i class="bi bi-file-earmark-pdf"></i> PDF</a></div></div>';
    echo '<div class="tw"><table><thead><tr>' . (!empty($o['nomor']) ? '<th>No</th>' : '');
    foreach ($cols as $k => $l) {
        $d = ($T['sort'] === $k && $T['dir'] === 'asc') ? 'desc' : 'asc'; $ar = $T['sort'] === $k ? ($T['dir'] === 'asc' ? ' ▲' : ' ▼') : '';
        echo '<th><a href="' . h(url(['sort' => $k, 'dir' => $d, 'p' => 1])) . '">' . h($l) . $ar . '</a></th>';
    }
    if (!empty($o['aksi'])) echo '<th class="aksi">Aksi</th>';
    echo '</tr></thead><tbody>';
    foreach ($T['page'] as $i => $r) {
        echo '<tr' . (!empty($o['detail']) ? ' data-d="' . h(json_encode($o['detail']($r), JSON_UNESCAPED_UNICODE)) . '"' : '') . '>' . (!empty($o['nomor']) ? '<td>' . (($T['p'] - 1) * $T['per'] + $i + 1) . '</td>' : '');
        foreach ($cols as $k => $l) { $v = $r[$k] ?? ''; echo '<td>' . (isset($o['fmt'][$k]) ? $o['fmt'][$k]($v, $r) : h($v)) . '</td>'; }
        if (!empty($o['aksi'])) echo '<td class="ac">' . $o['aksi']($r) . '</td>';
        echo '</tr>';
    }
    if (!$T['page']) echo '<tr><td colspan="99" class="em">Belum ada data.</td></tr>';
    echo '</tbody></table></div><div class="pg"><span>' . $T['total'] . ' data</span><div>';
    echo $T['p'] > 1 ? '<a class="btn" href="' . h(url(['p' => $T['p'] - 1])) . '"><i class="bi bi-chevron-left"></i></a>' : '<span class="btn dis"><i class="bi bi-chevron-left"></i></span>';
    echo ' Hal ' . $T['p'] . '/' . $T['hal'] . ' ';
    echo $T['p'] < $T['hal'] ? '<a class="btn" href="' . h(url(['p' => $T['p'] + 1])) . '"><i class="bi bi-chevron-right"></i></a>' : '<span class="btn dis"><i class="bi bi-chevron-right"></i></span>';
    echo '</div></div></div>';
}

// ---------- formulir ----------
// field: [nama, label, tipe(text|date|number|textarea|list), opsi, wajib, nilai awal]
function field($f, $val = '') {
    $f = array_pad($f, 6, null); list($nm, $l, $t, $o, $r) = $f; $t = $t ?: 'text'; $rq = $r ? ' required' : '';
    if ($t === 'textarea') $in = '<textarea name="' . h($nm) . '" rows="3"' . $rq . '>' . h($val) . '</textarea>';
    elseif ($t === 'select') {
        $o = $o ?: []; if ($val !== '' && !in_array($val, $o, true)) $o[] = $val;
        $in = '<select name="' . h($nm) . '"' . $rq . '><option value="">Pilih ' . h($l) . '</option>';
        foreach ($o as $v) $in .= '<option' . ((string)$v === (string)$val ? ' selected' : '') . '>' . h($v) . '</option>';
        $in .= '</select>';
    } elseif ($t === 'number0') $in = '<input name="' . h($nm) . '" type="number" value="' . h($val) . '" min="0" step="1">';
    else $in = '<input name="' . h($nm) . '" type="' . h($t) . '" value="' . h($val) . '" autocomplete="off"' . ($t === 'text' ? ' autocapitalize="off" spellcheck="false"' : '') . ($l === 'Deskripsi' ? ' placeholder="Ketik deskripsi"' : '') . $rq . ($t === 'number' ? ' min="1" step="1" max="500"' : '') . '>';
    return '<label class="' . ($t === 'textarea' ? 'full' : '') . '">' . h($l) . ($r ? ' *' : '') . $in . '</label>';
}
function form_modal($id, $judul, $fields, $vals, $hidden, $buka = false) {
    echo '<dialog id="' . h($id) . '" class="mb"' . ($buka ? ' data-open' : '') . '><form method="post" action="proses.php" autocomplete="off"><h3>' . h($judul) . '</h3>';
    $hidden['kembali'] = basename($_SERVER['PHP_SELF']);
    foreach ($hidden as $k => $v) echo '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    echo '<div class="fg">';
    foreach ($fields as $f) echo field($f, $vals[$f[0]] ?? ($f[5] ?? ''));
    echo '</div><div class="mf"><button type="button" class="btn" onclick="this.closest(\'dialog\').close()">Batal</button><button class="btn y"><i class="bi bi-check2"></i> Simpan</button></div></form></dialog>';
}

// ---------- halaman modul generik ----------
// $c: judul, sheet, data (baris), cols, tambah, fields_tambah, fields_ubah, fmt, filter, aksi, detail, balik, nomor, prefill, sheets (daftar sheet halaman ini untuk sinkron)
function halaman_modul($c) {
    $rows = $c['data']; $judul = $c['judul'];
    $T = tabel_siap($c['cols'], $rows, ['judul' => $judul, 'fmt' => $c['fmt'] ?? [], 'filter' => $c['filter'] ?? [], 'aksi' => $c['aksi'] ?? null, 'detail' => $c['detail'] ?? null, 'tanggal' => !empty($c['tanggal']), 'balik' => !empty($c['balik']), 'nomor' => !empty($c['nomor'])]);
    $tambah = !empty($c['fields_tambah']) ? '<button type="button" class="btn y" onclick="document.getElementById(\'fm\').showModal()"><i class="bi bi-plus-lg"></i> ' . h($c['tambah']) . '</button>' : '';
    if (!empty($_GET['ajax'])) { tabel_tampil($T, $tambah); exit; }
    $sheets = $c['sheets'] ?? [$c['sheet']];
    include __DIR__ . '/header.php';
    echo $c['extra'] ?? '';
    tabel_tampil($T, $tambah);
    if (!empty($c['fields_tambah'])) form_modal('fm', $c['tambah'], $c['fields_tambah'], $c['prefill'] ?? [], ['aksi' => 'add', 'sheet' => $c['sheet']], isset($c['prefill']));
    if (isset($_GET['edit']) && !empty($c['fields_ubah'])) foreach ($rows as $r) if ((string)($r['_r'] ?? '') === (string)$_GET['edit'])
        form_modal('fe', 'Ubah ' . $judul, $c['fields_ubah'], $r, ['aksi' => 'save', 'sheet' => $c['sheet'], '_r' => $r['_r'], '_s' => $r['_s']], true);
    include __DIR__ . '/footer.php';
}
