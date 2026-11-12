<?php
/** Pembaca .xlsx (tanpa library) + normalisasi sheet "List Pengambilan Material Gudang" ke format sheet web.
 *  Dipakai oleh import.php (unggah dari web) dan tools/ekspor_json.php. */

class XlsxReader {
    private $zip, $path, $ss = [], $dateXf = [], $sheetFile = [];
    function __construct($path) {
        $this->path = $path; $this->zip = new ZipArchive();
        if ($this->zip->open($path) !== true) throw new Exception('File bukan .xlsx yang valid.');
        $wb = $this->zip->getFromName('xl/workbook.xml'); $rel = $this->zip->getFromName('xl/_rels/workbook.xml.rels');
        if (!$wb || !$rel) throw new Exception('Struktur .xlsx tidak dikenali.');
        $rid = [];
        foreach (simplexml_load_string($rel)->Relationship as $r) $rid[(string)$r['Id']] = (string)$r['Target'];
        $x = simplexml_load_string($wb); $x->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        foreach ($x->sheets->sheet as $s) {
            $a = $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $t = $rid[(string)$a['id']] ?? ''; $t = ltrim($t, '/'); if (strpos($t, 'xl/') !== 0) $t = 'xl/' . $t;
            $this->sheetFile[trim((string)$s['name'])] = $t;
        }
        $this->bacaStyles(); $this->bacaStrings();
    }
    function sheetNames() { return array_keys($this->sheetFile); }
    function ada($n) { return isset($this->sheetFile[$n]); }
    private function bacaStrings() {
        $s = $this->zip->getFromName('xl/sharedStrings.xml'); if (!$s) return;
        $r = new XMLReader(); $r->XML($s); $cur = null; $inT = false; $depthRPh = -1;
        while ($r->read()) {
            if ($r->nodeType === XMLReader::ELEMENT) {
                if ($r->name === 'si') { $cur = ''; }
                elseif ($r->name === 'rPh') { $r->next(); }
                elseif ($r->name === 't' && $cur !== null) { $cur .= $r->readString(); }
            } elseif ($r->nodeType === XMLReader::END_ELEMENT && $r->name === 'si') { $this->ss[] = $cur; $cur = null; }
        }
    }
    private function bacaStyles() {
        $s = $this->zip->getFromName('xl/styles.xml'); if (!$s) return;
        $x = simplexml_load_string($s); $custom = [];
        if (isset($x->numFmts)) foreach ($x->numFmts->numFmt as $f) $custom[(int)$f['numFmtId']] = (string)$f['formatCode'];
        $i = 0;
        foreach ($x->cellXfs->xf as $xf) {
            $id = (int)$xf['numFmtId']; $d = false;
            if (($id >= 14 && $id <= 22) || ($id >= 27 && $id <= 36) || ($id >= 45 && $id <= 47) || ($id >= 50 && $id <= 58)) $d = true;
            elseif (isset($custom[$id])) { $f = preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\./', '', $custom[$id]); $d = (bool)preg_match('/[ymdhs]/i', $f); }
            $this->dateXf[$i++] = $d;
        }
    }
    /** Memanggil $cb($noBaris, [indeksKolom => nilai]) untuk tiap baris. Tanggal: "\x01D2024-08-09". Sel error / kosong: tidak ada. */
    function baca($nama, callable $cb) {
        if (!isset($this->sheetFile[$nama])) throw new Exception("Sheet '$nama' tidak ditemukan");
        $r = new XMLReader(); $r->open('zip://' . str_replace(chr(92), '/', $this->path) . '#' . $this->sheetFile[$nama]);
        $row = null; $no = 0;
        while ($r->read()) {
            if ($r->nodeType === XMLReader::ELEMENT && $r->name === 'row') { $no = (int)$r->getAttribute('r') ?: $no + 1; $row = []; if ($r->isEmptyElement) { $cb($no, []); $row = null; } continue; }
            if ($r->nodeType === XMLReader::END_ELEMENT && $r->name === 'row') { $cb($no, $row); $row = null; continue; }
            if ($row !== null && $r->nodeType === XMLReader::ELEMENT && $r->name === 'c') {
                $ref = $r->getAttribute('r'); $t = $r->getAttribute('t'); $st = (int)$r->getAttribute('s');
                $col = 0; if (preg_match('/^([A-Z]+)/', (string)$ref, $m)) foreach (str_split($m[1]) as $ch) $col = $col * 26 + ord($ch) - 64; $col--;
                $v = null; $is = null;
                if (!$r->isEmptyElement) {
                    $d = $r->depth;
                    while ($r->read() && !($r->nodeType === XMLReader::END_ELEMENT && $r->depth === $d)) {
                        if ($r->nodeType === XMLReader::ELEMENT) {
                            if ($r->name === 'v') $v = $r->readString();
                            elseif ($r->name === 'is') $is = $r->readString();
                        }
                    }
                }
                $val = null;
                if ($t === 's') $val = $this->ss[(int)$v] ?? null;
                elseif ($t === 'inlineStr') $val = $is;
                elseif ($t === 'str') $val = $v;
                elseif ($t === 'e') $val = null;
                elseif ($t === 'b') $val = $v;
                elseif ($v !== null && $v !== '') {
                    if (!empty($this->dateXf[$st]) && is_numeric($v) && $v > 0) $val = "\x01D" . gmdate('Y-m-d', (int)(floor((float)$v) - 25569) * 86400);
                    elseif (preg_match('/^-?\d+(\.\d+)?[eE][+-]?\d+$/', $v)) $val = rtrim(rtrim(sprintf('%.15F', (float)$v), '0'), '.');
                    else $val = $v;
                }
                if ($val !== null) $row[$col] = $val;
            }
        }
        $r->close();
    }
}

// ---------- normalisasi ----------
const NORM_VALTYPE = ['RUISAK-TL' => 'RUSAK-TL', 'RUISAK-L' => 'RUSAK-L', 'EX PROJECT' => 'EX-PROJECT', 'EXPROJECT' => 'EX-PROJECT', 'RUSAK TL' => 'RUSAK-TL', 'RUSAK L' => 'RUSAK-L'];

function n_txt($v) {
    if ($v === null) return '';
    $v = str_replace(["\xC2\xA0", "\t"], ' ', (string)$v);
    return trim(preg_replace('/\s+/', ' ', $v));
}
function n_sn($v) {
    if ($v === null) return '';
    $v = str_replace(["\xC2\xA0", "\t", "\r"], ' ', (string)$v);
    $lines = array_values(array_filter(array_map(function ($l) { return trim(rtrim(trim($l), ';,')); }, explode("\n", $v)), 'strlen'));
    return implode("\n", $lines);
}
function n_tgl($v, &$bad = null) {
    if ($v === null || trim((string)$v) === '') return '';
    $v = (string)$v;
    if (strpos($v, "\x01D") === 0) return substr($v, 2);
    $v = n_txt($v);
    if ($v === '') return '';
    $ok = function ($y, $mo, $d) { return checkdate($mo, $d, $y) && $y >= 2015 && sprintf('%04d-%02d-%02d', $y, $mo, $d) <= date('Y-m-d', strtotime('+7 days')) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null; };
    $hari = null; $s = $v;
    if (preg_match('/^(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b[,\s]*(.*)$/i', $s, $m)) { $hari = strtolower($m[1]); $s = $m[2]; }
    $s = preg_replace('/^([A-Za-z]+),\s*(\d)/', '$1 $2', $s);                 // "April, 16, 2026" -> "April 16, 2026"
    if (preg_match('/^([A-Za-z]+) (\d{1,2}), (\d{5})$/', $s, $m)) {            // tahun salah ketik 5 digit: pilih yang cocok dengan nama hari
        $mo = (int)date('n', strtotime("1 $m[1] 2000")); $cand = [];
        for ($i = 0; $i < 5; $i++) { $y = (int)(substr($m[3], 0, $i) . substr($m[3], $i + 1)); if ($ok($y, $mo, (int)$m[2])) $cand[] = $y; }
        foreach ($cand as $y) if ($hari === null || strtolower(date('l', mktime(0, 0, 0, $mo, (int)$m[2], $y))) === $hari) { $r = $ok($y, $mo, (int)$m[2]); if ($r) return $r; }
    }
    if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2})-(\d{2})$/', $s, $m)) $s = "$m[1].$m[2].$m[3]$m[4]";   // "07.10.20-24"
    if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})$/', $s, $m)) {
        $a = (int)$m[1]; $b = (int)$m[2]; $y = (int)$m[3]; if ($y < 100) $y += 2000;
        if ($a > 12) { $d = $a; $mo = $b; } elseif ($b > 12) { $d = $b; $mo = $a; } else { $d = $a; $mo = $b; }
        if ($r = $ok($y, $mo, $d)) return $r;
    } elseif (($t = strtotime($s)) && ($r = $ok((int)date('Y', $t), (int)date('n', $t), (int)date('j', $t)))) return $r;
    $bad = $v; return '';
}
/** Tanggal yang tidak terbaca / tidak masuk akal tidak dibuang: teks aslinya dicatat di Keterangan. */
function bad_ket($row, $bad) {
    if ($bad && array_key_exists('ket', $row)) $row['ket'] = trim($row['ket'] . ' [Tanggal asli: ' . $bad . ']');
    return $row;
}
function n_valtype($v) { $v = strtoupper(n_txt($v)); return NORM_VALTYPE[$v] ?? $v; }
function n_isi($r, $cols) { foreach ($cols as $c) if (isset($r[$c]) && n_txt($r[$c]) !== '') return true; return false; }

/** Kolom sheet web per sheet (urutan = urutan kolom spreadsheet). Dipakai untuk fingerprint & ekspor. */
function norm_kolom() {
    return [
        'Data_Barang' => ['nama', 'valtype', 'satuan', 'awal', 'min', 'ket'],
        'Barang_Masuk' => ['no', 'tgl', 'nama', 'valtype', 'sn', 'ket', 'divisi'],
        'Barang_Keluar' => ['tgl', 'pengambil', 'material', 'sn', 'ket', 'mitra'],
        'Pengambilan_Ritel' => ['no', 'tgl', 'mitra', 'bidang', 'nama', 'material', 'sn', 'lokasi', 'ket'],
        'Pengambilan_Ggn' => ['no', 'tgl', 'mitra', 'bidang', 'nama', 'material', 'sn', 'lokasi', 'ket'],
        'Pengambilan_ICRM' => ['no', 'tgl', 'wo', 'mitra', 'nama', 'lokasi', 'perangkat', 'sn'],
        'Pengambilan_Aktivasi' => ['no', 'tgl', 'pa', 'mitra', 'bidang', 'sn'],
        'Pengambilan_HAR' => ['no', 'tgl', 'pengambil', 'bidang', 'nama', 'material', 'sn', 'lokasi', 'ket'],
        'Pengambilan_Pembangunan' => ['no', 'tgl', 'bidang', 'nama', 'material', 'sn', 'lokasi', 'ket'],
        'Master_SN' => ['kode', 'nama', 'valtype', 'plnt', 'sloc', 'sn', 'ket', 'lokasi'],
    ];
}
/** Nama sheet sumber di Excel -> sheet tujuan (pencocokan tanpa memperhatikan huruf besar/kecil & spasi ganda). */
function norm_sumber() {
    return ['Kartu gantung' => 'Data_Barang', 'Material Masuk' => 'Barang_Masuk', 'Material Keluar' => 'Barang_Keluar', 'Pengambilan Retail' => 'Pengambilan_Ritel',
        'Pengambilan ggn' => 'Pengambilan_Ggn', 'Pengambilan Aktivasi ICRM' => 'Pengambilan_ICRM', 'Pengambilan Aktivasi' => 'Pengambilan_Aktivasi',
        'Pengambilan HAR' => 'Pengambilan_HAR', 'Pengambilan Pembangunan' => 'Pengambilan_Pembangunan', 'Sheet2|Sheet3|Sheet1' => 'Master_SN'];
}

/** Membaca seluruh sheet relevan. Mengembalikan ['data' => [sheet => [baris...]], 'lap' => [sheet => [...]]]. */
function normalisasi_xlsx($path, &$progress = null) {
    $X = new XlsxReader($path);
    $find = function ($n) use ($X) { foreach ($X->sheetNames() as $s) if (strcasecmp(preg_replace('/\s+/', ' ', trim($s)), $n) === 0) return $s; return null; };
    $out = []; $lap = [];
    $cat = function ($t, $k, $contoh = '') use (&$lap) { $lap[$t]['catatan'][$k] = ($lap[$t]['catatan'][$k] ?? 0) + 1; if ($contoh !== '' && count($lap[$t]['contoh'][$k] ?? []) < 3) $lap[$t]['contoh'][$k][] = $contoh; };
    $mulai = function ($t, $sumber) use (&$lap, &$out) { $out[$t] = []; $lap[$t] = ['sumber' => $sumber, 'dibaca' => 0, 'kosong' => 0, 'catatan' => [], 'contoh' => []]; };

    // --- Kartu gantung -> Data_Barang (stok awal per material + valtype)
    if ($s = $find('Kartu gantung')) {
        $t = 'Data_Barang'; $mulai($t, $s);
        $X->baca($s, function ($no, $r) use (&$out, &$lap, $t, $cat) {
            if ($no === 1) return; $lap[$t]['dibaca']++;
            if (!n_isi($r, [0, 1, 2, 3])) { $lap[$t]['kosong']++; return; }
            $kode = n_txt($r[0] ?? ''); $nama = n_txt($r[1] ?? '');
            if (stripos($kode, 'grand total') !== false || $nama === '' || !isset($r[3])) { $lap[$t]['kosong']++; return; }
            $out[$t][] = ['nama' => $nama, 'valtype' => n_valtype($r[2] ?? ''), 'satuan' => 'Unit', 'awal' => (int)$r[3], 'min' => 0, 'ket' => 'Kode SAP: ' . $kode];
        });
    }
    // --- Material Masuk (SN dipecah satu per baris)
    if ($s = $find('Material Masuk')) {
        $t = 'Barang_Masuk'; $mulai($t, $s); $no = 0;
        $X->baca($s, function ($n, $r) use (&$out, &$lap, $t, $cat, &$no) {
            if ($n === 1) return; $lap[$t]['dibaca']++;
            if (!n_isi($r, [1, 2, 3, 4, 5, 6])) { $lap[$t]['kosong']++; return; }
            $bad = null; $tgl = n_tgl($r[1] ?? null, $bad); if ($bad) $cat($t, 'tanggal tidak terbaca (teks asli dicatat di Keterangan)', $bad); if ($tgl === '') $cat($t, 'tanpa tanggal', "baris $n");
            $nama = n_txt($r[2] ?? ''); if ($nama === '') $cat($t, 'tanpa nama material', "baris $n");
            $vt = n_valtype($r[3] ?? ''); if (isset($r[3]) && n_txt($r[3]) !== $vt) $cat($t, 'valtype dikoreksi (' . n_txt($r[3]) . ' -> ' . $vt . ')');
            $sns = n_sn($r[4] ?? '') === '' ? [''] : explode("\n", n_sn($r[4])); if (count($sns) > 1) $cat($t, 'SN dipecah menjadi satu baris per SN (baris ' . $n . ': ' . count($sns) . ' SN)');
            foreach ($sns as $sn) $out[$t][] = bad_ket(['no' => ++$no, 'tgl' => $tgl, 'nama' => $nama, 'valtype' => $vt, 'sn' => $sn, 'ket' => n_txt($r[5] ?? ''), 'divisi' => n_txt($r[6] ?? '')], $bad ?? null);
        });
    }
    // --- Material Keluar
    if ($s = $find('Material Keluar')) {
        $t = 'Barang_Keluar'; $mulai($t, $s);
        $X->baca($s, function ($n, $r) use (&$out, &$lap, $t, $cat) {
            if ($n === 1) return; $lap[$t]['dibaca']++;
            if (!n_isi($r, [1, 2, 3, 4, 5, 6, 7])) { $lap[$t]['kosong']++; return; }
            $bad = null; $tgl = n_tgl($r[1] ?? null, $bad); if ($bad) $cat($t, 'tanggal tidak terbaca (teks asli dicatat di Keterangan)', $bad); if ($tgl === '') $cat($t, 'tanpa tanggal', "baris $n");
            $ket = n_txt($r[5] ?? ''); $h = n_txt($r[7] ?? ''); if ($h !== '') { $ket = $ket === '' ? $h : $ket . ' | ' . $h; $cat($t, 'kolom H tanpa judul digabung ke Keterangan'); }
            $out[$t][] = bad_ket(['tgl' => $tgl, 'pengambil' => n_txt($r[2] ?? ''), 'material' => n_txt($r[3] ?? ''), 'sn' => n_sn($r[4] ?? ''), 'ket' => $ket, 'mitra' => n_txt($r[6] ?? '')], $bad ?? null);
        });
    }
    // --- Retail & ggn (struktur sama, sheet terpisah)
    foreach (['Pengambilan Retail' => 'Pengambilan_Ritel', 'Pengambilan ggn' => 'Pengambilan_Ggn'] as $src => $t) {
        if (!($s = $find($src))) continue;
        $mulai($t, $s); $no = 0;
        $X->baca($s, function ($n, $r) use (&$out, &$lap, $t, $cat, &$no) {
            if ($n === 1) return; $lap[$t]['dibaca']++;
            if (!n_isi($r, [1, 2, 3, 4, 5, 6, 7, 8])) { $lap[$t]['kosong']++; return; }
            $bad = null; $tgl = n_tgl($r[1] ?? null, $bad); if ($bad) $cat($t, 'tanggal tidak terbaca (teks asli dicatat di Keterangan)', $bad); if ($tgl === '') $cat($t, 'tanpa tanggal', "baris $n");
            $out[$t][] = bad_ket(['no' => ++$no, 'tgl' => $tgl, 'mitra' => n_txt($r[2] ?? ''), 'bidang' => n_txt($r[3] ?? ''), 'nama' => n_txt($r[4] ?? ''), 'material' => n_txt($r[5] ?? ''), 'sn' => n_sn($r[6] ?? ''), 'lokasi' => n_txt($r[7] ?? ''), 'ket' => n_txt($r[8] ?? '')], $bad ?? null);
        });
    }
    // --- ICRM: baris induk (No, Tanggal, WO, Mitra, Nama, Lokasi) lalu baris perangkat; baris kosong memutus grup
    if ($s = $find('Pengambilan Aktivasi ICRM')) {
        $t = 'Pengambilan_ICRM'; $mulai($t, $s); $g = null;
        $X->baca($s, function ($n, $r) use (&$out, &$lap, $t, $cat, &$g) {
            if ($n === 1) return; $lap[$t]['dibaca']++; $bad = null;
            if (!n_isi($r, [0, 1, 2, 3, 4, 5, 6, 7])) { $g = null; $lap[$t]['kosong']++; return; }
            $induk = n_isi($r, [1, 2, 3, 4]);
            if ($induk) { $bad = null; $tgl = n_tgl($r[1] ?? null, $bad); if ($bad) $cat($t, 'tanggal tidak terbaca (teks asli dicatat di Keterangan)', $bad);
                $g = ['no' => n_txt($r[0] ?? ''), 'tgl' => $tgl, 'wo' => n_txt($r[2] ?? ''), 'mitra' => n_txt($r[3] ?? ''), 'nama' => n_txt($r[4] ?? ''), 'lokasi' => n_txt($r[5] ?? '')]; }
            if ($g === null) { $g = ['no' => '', 'tgl' => '', 'wo' => '', 'mitra' => '', 'nama' => '', 'lokasi' => n_txt($r[5] ?? '')]; $cat($t, 'baris tanpa induk WO (diimpor apa adanya)', 'baris ' . $n); }
            elseif (!$induk) $cat($t, 'baris perangkat mengikuti WO di atasnya');
            if (!$induk && n_txt($r[5] ?? '') !== '' && $g['lokasi'] === '') $g['lokasi'] = n_txt($r[5]);
            $out[$t][] = bad_ket(['no' => $g['no'], 'tgl' => $g['tgl'], 'wo' => $g['wo'], 'mitra' => $g['mitra'], 'nama' => $g['nama'], 'lokasi' => $induk ? $g['lokasi'] : (n_txt($r[5] ?? '') !== '' ? n_txt($r[5]) : $g['lokasi']), 'perangkat' => n_txt($r[6] ?? ''), 'sn' => n_sn($r[7] ?? '')], $bad ?? null);
        });
    }
    // --- Aktivasi (PA)
    if ($s = $find('Pengambilan Aktivasi')) {
        $t = 'Pengambilan_Aktivasi'; $mulai($t, $s);
        $X->baca($s, function ($n, $r) use (&$out, &$lap, $t, $cat) {
            if ($n === 1) return; $lap[$t]['dibaca']++;
            if (!n_isi($r, [1, 2, 3, 4, 5, 6])) { $lap[$t]['kosong']++; return; }
            $bad = null; $tgl = n_tgl($r[1] ?? null, $bad); if ($bad) $cat($t, 'tanggal tidak terbaca (teks asli dicatat di Keterangan)'); if ($tgl === '') $cat($t, 'tanpa tanggal', "baris $n");
            $out[$t][] = bad_ket(['no' => n_txt($r[0] ?? ''), 'tgl' => $tgl, 'pa' => n_txt($r[2] ?? ''), 'mitra' => n_txt($r[3] ?? ''), 'bidang' => n_txt($r[4] ?? ''), 'sn' => n_txt($r[5] ?? '')], $bad ?? null);
        });
    }
    // --- HAR: dua susunan kolom dalam satu sheet
    if ($s = $find('Pengambilan HAR')) {
        $t = 'Pengambilan_HAR'; $mulai($t, $s); $no = 0;
        $X->baca($s, function ($n, $r) use (&$out, &$lap, $t, $cat, &$no) {
            if ($n === 1) return; $lap[$t]['dibaca']++;
            if (!n_isi($r, [1, 2, 3, 4, 5, 6, 7, 8])) { $lap[$t]['kosong']++; return; }
            $bad = null; $tgl = n_tgl($r[1] ?? null, $bad); if ($bad) $cat($t, 'tanggal tidak terbaca (teks asli dicatat di Keterangan)'); if ($tgl === '') $cat($t, 'tanpa tanggal', "baris $n");
            $E = n_txt($r[4] ?? ''); $G = n_txt($r[6] ?? '');
            $B = $E !== '' && !preg_match('/\d/', $E) && $G !== '' && !preg_match('/\s/', $G) && preg_match('/\d/', $G) && strlen($G) >= 6;
            if ($B) { $cat($t, 'susunan kolom ke-2 (Bidang, Nama, Material, SN, Lokasi) dikenali otomatis');
                $row = ['pengambil' => n_txt($r[2] ?? ''), 'bidang' => n_txt($r[3] ?? ''), 'nama' => $E, 'material' => n_txt($r[5] ?? ''), 'sn' => n_sn($r[6] ?? ''), 'lokasi' => n_txt($r[7] ?? ''), 'ket' => n_txt($r[8] ?? '')]; }
            else $row = ['pengambil' => n_txt($r[2] ?? ''), 'bidang' => '', 'nama' => n_txt($r[5] ?? ''), 'material' => n_txt($r[3] ?? ''), 'sn' => n_sn($r[4] ?? ''), 'lokasi' => '', 'ket' => n_txt($r[6] ?? '')];
            $out[$t][] = bad_ket(['no' => ++$no, 'tgl' => $tgl] + $row, $bad ?? null);
        });
    }
    // --- Pembangunan
    if ($s = $find('Pengambilan Pembangunan')) {
        $t = 'Pengambilan_Pembangunan'; $mulai($t, $s); $no = 0;
        $X->baca($s, function ($n, $r) use (&$out, &$lap, $t, $cat, &$no) {
            if ($n === 1) return; $lap[$t]['dibaca']++;
            if (!n_isi($r, [1, 2, 3, 4, 5, 6, 7])) { $lap[$t]['kosong']++; return; }
            $bad = null; $tgl = n_tgl($r[1] ?? null, $bad); if ($bad) $cat($t, 'tanggal tidak terbaca (teks asli dicatat di Keterangan)'); if ($tgl === '') $cat($t, 'tanpa tanggal', "baris $n");
            $out[$t][] = bad_ket(['no' => ++$no, 'tgl' => $tgl, 'bidang' => n_txt($r[2] ?? ''), 'nama' => n_txt($r[3] ?? ''), 'material' => n_txt($r[4] ?? ''), 'sn' => n_sn($r[5] ?? ''), 'lokasi' => n_txt($r[6] ?? ''), 'ket' => n_txt($r[7] ?? '')], $bad ?? null);
        });
    }
    // --- Master SN (daftar serial SAP: Sheet2 > Sheet3 > Sheet1, digabung per SN; nilai sampah #N/A / 0 diabaikan)
    $sap = []; foreach (['Sheet2', 'Sheet3', 'Sheet1'] as $nm) if ($s = $find($nm)) $sap[] = $s;
    if ($sap) {
        $t = 'Master_SN'; $mulai($t, implode(', ', $sap)); $M = [];
        $bersih = function ($v) { $v = n_txt($v); return ($v === '' || $v === '0' || strpos($v, '#') === 0) ? '' : $v; };
        foreach ($sap as $s) $X->baca($s, function ($n, $r) use (&$M, &$lap, $t, $bersih) {
            if ($n === 1) return; $lap[$t]['dibaca']++;
            $sn = n_sn($r[5] ?? ''); if ($sn === '') { $lap[$t]['kosong']++; return; }
            $k = strtoupper($sn); $new = ['kode' => n_txt($r[0] ?? ''), 'nama' => n_txt($r[1] ?? ''), 'valtype' => n_valtype($r[2] ?? ''), 'plnt' => n_txt($r[3] ?? ''), 'sloc' => n_txt($r[4] ?? ''), 'sn' => $sn, 'ket' => $bersih($r[6] ?? ''), 'lokasi' => $bersih($r[7] ?? '')];
            if (!isset($M[$k])) $M[$k] = $new; else foreach (['ket', 'lokasi', 'nama', 'kode', 'valtype'] as $f) if ($M[$k][$f] === '' && $new[$f] !== '') $M[$k][$f] = $new[$f];
        });
        $out[$t] = array_values($M); $lap[$t]['catatan']['SN unik dari gabungan 3 sheet'] = count($M);
    }
    foreach (['Barang_Keluar', 'Pengambilan_Ritel', 'Pengambilan_Ggn', 'Pengambilan_HAR', 'Pengambilan_Pembangunan'] as $t) if (!empty($out[$t])) {
        $f = perbaiki_urutan($out[$t]); if ($f) { $lap[$t]['catatan']['tanggal salah tahun dikoreksi mengikuti baris sekitar'] = $f; }
    }
    return ['data' => $out, 'lap' => $lap];
}

/** Tahun salah akibat seret-isi (mis. 17.10.2024, 2025, 2026, ... lalu kembali ke 17.10.2024): ikuti tanggal baris sekitarnya, teks asli dicatat. */
function perbaiki_urutan(&$rows) {
    $prev = null; $n = count($rows); $fix = 0;
    for ($i = 0; $i < $n; $i++) {
        $r =& $rows[$i]; $t = $r['tgl'] ?? ''; $ket = $r['ket'] ?? ''; $raw = false;
        if ($t === '' && preg_match('/\[Tanggal asli: (\d{1,2})\.(\d{1,2})\.(\d{4})\]/', $ket, $m)) { $t = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]); $raw = true; }
        if ($t === '') { unset($r); continue; }
        if ($prev !== null && $t > $prev && substr($t, 5) === substr($prev, 5)) {
            for ($j = $i + 1; $j < min($n, $i + 12); $j++) if (($rows[$j]['tgl'] ?? '') === $prev) {
                $r['tgl'] = $prev; if (!$raw) $r['ket'] = trim($ket . ' [Tanggal asli: ' . tgl_tampil_lib($t) . ']'); $fix++; unset($r); continue 2;
            }
        }
        $prev = $t; unset($r);
    }
    return $fix;
}
function tgl_tampil_lib($v) { return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) ? "$m[3].$m[2].$m[1]" : $v; }

/** Sidik jari baris (tanpa kolom No) untuk mencegah data ganda. */
function norm_fp($sheet, $r) {
    $o = [];
    foreach (norm_kolom()[$sheet] as $k) { if ($k === 'no') continue; $o[] = preg_replace('/\s+/', ' ', mb_strtoupper(trim((string)($r[$k] ?? '')))); }
    return md5(implode("\x1F", $o));
}
