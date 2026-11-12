<?php
const VALTYPE = ['EX-PROJECT', 'DISMANTLE', 'RUSAK-L', 'RUSAK-TL', 'BAIK'];
const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const CACHE_TTL = 30;      // detik: cache hasil baca Google Sheets per sheet
if (!defined('STOK_AWAL_TANGGAL')) define('STOK_AWAL_TANGGAL', '2026-11-05');
@ini_set('memory_limit', '512M');
@set_time_limit(120);   // XAMPP bawaan 30 detik; pembacaan pertama dari Google Sheets bisa lebih lama
const CACHE_STALE = 1800;  // detik: data cache yang lebih tua dari CACHE_TTL tetap ditampilkan, lalu disegarkan di latar belakang
require __DIR__ . '/tabel.php';
require __DIR__ . '/modul.php';

// ---------- umum ----------
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function n($x) { return is_numeric($x) ? $x + 0 : 0; }
function ke($u) { header('Location: ' . $u); exit; }
function flash($m, $t = 'ok') { $_SESSION['flash'] = [$m, $t]; }
function url($ov = []) {
    $g = $_GET;
    foreach ($ov as $k => $v) { if ($v === null) unset($g[$k]); else $g[$k] = $v; }
    return '?' . http_build_query($g);
}
function fatal($m) {
    http_response_code(500);
    echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="assets/vendor/bootstrap-icons.min.css"><link rel="stylesheet" href="assets/style.css"><div class="fatal"><h2>Tidak dapat terhubung ke Google Sheets</h2><p>' . h($m) . '</p><p class="mu">Buka <a href="cek.php">cek.php</a> untuk memeriksa koneksi dan pengaturan PHP.</p><p class="mu">Periksa <code>koneksi.php</code> (APPS_SCRIPT_URL dan API_KEY) serta deployment Apps Script, lalu <a href="">muat ulang</a>.</p></div>';
    exit;
}

// ---------- API Google Apps Script ----------
function api_curl($aksi, $p) {
    if (strpos(APPS_SCRIPT_URL, 'GANTI') === 0 || strpos(API_KEY, 'GANTI') === 0) throw new Exception('APPS_SCRIPT_URL dan API_KEY di koneksi.php belum diisi.');
    $p['action'] = $aksi; $p['key'] = API_KEY;
    $ch = curl_init(APPS_SCRIPT_URL);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($p, JSON_UNESCAPED_UNICODE), CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 180, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_ENCODING => '', CURLOPT_HTTPHEADER => ['Content-Type: text/plain']]);
    return $ch;
}
function api_hasil($r, $err = '') {
    if ($r === false || $r === null || $r === '') throw new Exception('Gagal menghubungi Google Apps Script' . ($err ? ': ' . $err : '.'));
    $j = json_decode($r, true);
    if (!is_array($j)) throw new Exception('Respons Apps Script tidak valid. Pastikan deployment "Who has access" = Anyone dan URL berakhiran /exec.');
    if (empty($j['ok'])) throw new Exception($j['error'] ?? 'Terjadi kesalahan pada server.');
    return $j;
}
function api($aksi, $p = []) {
    $ch = api_curl($aksi, $p); $r = curl_exec($ch); $e = curl_error($ch); curl_close($ch);
    return api_hasil($r, $e);
}
/** Baca beberapa sheet SEKALIGUS (paralel): waktu tunggu = sheet terbesar, bukan jumlah semuanya. */
function api_multi($names) {
    $mh = curl_multi_init(); $hs = [];
    foreach ($names as $nm) { $ch = api_curl('boot', ['sheets' => [$nm]]); curl_multi_add_handle($mh, $ch); $hs[$nm] = $ch; }
    do { $st = curl_multi_exec($mh, $run); if ($run) curl_multi_select($mh, 1.0); } while ($run && $st === CURLM_OK);
    $out = [];
    foreach ($hs as $nm => $ch) {
        $r = curl_multi_getcontent($ch); $e = curl_error($ch); curl_multi_remove_handle($mh, $ch); curl_close($ch);
        try { $j = api_hasil($r, $e); $out[$nm] = $j['data'][$nm] ?? []; } catch (Exception $x) { $out[$nm] = $x; }
    }
    curl_multi_close($mh);
    return $out;
}

// ---------- cache pembacaan Google Sheets (per sheet) ----------
function cache_dir() {
    $d = __DIR__ . '/cache';
    if (!is_dir($d)) @mkdir($d, 0777, true);
    return (is_dir($d) && is_writable($d)) ? $d : sys_get_temp_dir();
}
function cache_file($sheet) { return cache_dir() . '/s_' . md5(APPS_SCRIPT_URL) . '_' . $sheet . '.json'; }
function cache_hapus($sheets = null) {
    foreach ((array)($sheets ?? array_keys(norm_skema())) as $s) @unlink(cache_file($s));
}
function norm_skema() { return array_fill_keys(['Data_Barang', 'Barang_Masuk', 'Barang_Keluar', 'Pengambilan_Ritel', 'Pengambilan_Ggn', 'Pengambilan_ICRM', 'Pengambilan_Aktivasi', 'Pengambilan_HAR', 'Pengambilan_Pembangunan', 'Master_SN'], 1); }
/** Baris mentah dari spreadsheet. Memakai cache file; sheet yang belum / sudah kedaluwarsa dibaca paralel. ?fresh=1 melewati cache. */
function lembar($names, $sync = false) {
    static $M = [];
    $ttl = $sync ? 10 : CACHE_TTL; $need = [];
    foreach ($names as $nm) {
        if (isset($M[$nm])) continue;
        $f = cache_file($nm);
        if (!isset($_GET['fresh']) && is_file($f)) {
            $umur = time() - filemtime($f);
            if ($umur < $ttl || (!$sync && $umur < CACHE_STALE)) {
                $j = json_decode((string)@file_get_contents($f), true);
                if (is_array($j)) { $M[$nm] = $j; if ($umur >= $ttl) $GLOBALS['STALE'][$nm] = 1; continue; }   // basi: tampil dulu, segarkan sesudah halaman terkirim
            }
        }
        $need[] = $nm;
    }
    if ($need) {
        $res = api_multi($need);
        foreach ($need as $nm) {
            if ($res[$nm] instanceof Exception) {
                $f = cache_file($nm); $j = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
                if (!is_array($j)) throw $res[$nm];
                $M[$nm] = $j;   // Google tidak terjangkau: pakai data terakhir yang tersimpan
            } else { $M[$nm] = $res[$nm]; @file_put_contents(cache_file($nm), json_encode($res[$nm], JSON_UNESCAPED_UNICODE), LOCK_EX); }
        }
    }
    $o = []; foreach ($names as $nm) $o[$nm] = $M[$nm]; return $o;
}
/** Dipanggil setelah halaman selesai: kirim respons ke browser lebih dulu, baru baca ulang sheet yang cache-nya sudah lewat TTL. */
function segarkan_latar() {
    $st = array_keys($GLOBALS['STALE'] ?? []); if (!$st || PHP_SAPI === 'cli') return;
    ignore_user_abort(true);
    // gabungkan semua lapis output buffer (bawaan php.ini XAMPP + buffer program), kirim utuh, lalu tutup koneksi sebelum membaca Google
    $isi = ''; while (ob_get_level() > 0) $isi = ob_get_clean() . $isi;
    if (!headers_sent()) {
        if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
        if (!ini_get('zlib.output_compression')) { header('Content-Encoding: identity'); header('Content-Length: ' . strlen($isi)); header('Connection: close'); }
    }
    echo $isi;
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); else { @flush(); }
    $lock = @fopen(cache_dir() . '/refresh.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return;   // sudah ada proses lain yang menyegarkan
    @set_time_limit(180);
    try { $res = api_multi($st); foreach ($st as $nm) if (!($res[$nm] instanceof Exception)) @file_put_contents(cache_file($nm), json_encode($res[$nm], JSON_UNESCAPED_UNICODE), LOCK_EX); } catch (Throwable $e) {}
    flock($lock, LOCK_UN);
}
register_shutdown_function('segarkan_latar');
function lembar_ver($names) { $s = ''; foreach ($names as $nm) { $f = cache_file($nm); $s .= is_file($f) ? md5_file($f) : '-'; } return md5($s); }
/** Seperti lembar() tetapi tanggal sudah dinormalkan ke Y-m-d. Gagal terhubung = halaman galat yang jelas. */
function ambil($names) {
    static $N = [];
    try { $raw = lembar($names); } catch (Exception $e) { fatal($e->getMessage()); }
    $o = [];
    foreach ($names as $nm) {
        if (!isset($N[$nm])) { $r = $raw[$nm]; if ($r && array_key_exists('tgl', $r[0])) foreach ($r as &$x) $x['tgl'] = tgl_norm($x['tgl']); unset($x); $N[$nm] = $r; }
        $o[$nm] = $N[$nm];
    }
    return $o;
}

// ---------- fungsi data ----------
function mkey($s) { return strtoupper(trim(preg_replace('/\s+/', ' ', (string)$s))); }
function sn_list($s) { return array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', (string)$s)), 'strlen')); }
function unit($s) { return max(1, count(sn_list($s))); }
function iso($t) { return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$t); }
function tgl_norm($s) {
    $s = trim((string)$s);
    if ($s === '' || iso($s)) return $s;
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $s, $m)) {
        list(, $a, $b, $y) = $m; $d = (int)$a > 12 ? $a : $b; $mo = (int)$a > 12 ? $b : $a;
        if (checkdate((int)$mo, (int)$d, (int)$y)) return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }
    $t = strtotime($s);
    return $t ? date('Y-m-d', $t) : $s;
}
function tgl_tampil($v) { return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string)$v, $m) ? "$m[3]/$m[2]/$m[1]" : (string)$v; }
function jumlah_unit($rows, $fn = null) { $s = 0; foreach ($rows as $x) if (!$fn || $fn($x)) $s += unit($x['sn'] ?? ''); return $s; }

// ---------- registri modul transaksi ----------
function MOD() {
    static $M = null; if ($M !== null) return $M;
    $L = ['no' => 'No', 'tgl' => 'Tanggal', 'nama' => 'Nama', 'material' => 'Material', 'sn' => 'SN', 'ket' => 'Keterangan', 'mitra' => 'Mitra', 'bidang' => 'Bidang', 'lokasi' => 'Lokasi',
          'pengambil' => 'Yang Mengambil', 'valtype' => 'Valtype', 'divisi' => 'Divisi', 'wo' => 'WO', 'pa' => 'PA', 'perangkat' => 'Perangkat'];
    $M = [
        'masuk' => ['sheet' => 'Barang_Masuk', 'judul' => 'Material Masuk', 'file' => 'barang_masuk.php', 'jenis' => 'Masuk', 'ikon' => 'box-arrow-in-down', 'mat' => 'nama', 'arah' => 1, 'qty' => true,
            'kolom' => ['no', 'tgl', 'nama', 'valtype', 'sn', 'ket', 'divisi'], 'label' => ['nama' => 'Deskripsi'], 'wajib' => ['tgl', 'nama', 'valtype'], 'filter' => ['valtype' => 'Semua valtype', 'divisi' => 'Semua divisi'], 'oleh' => 'divisi'],
        'keluar' => ['sheet' => 'Barang_Keluar', 'judul' => 'Material Keluar', 'file' => 'barang_keluar.php', 'jenis' => 'Keluar', 'ikon' => 'box-arrow-up', 'mat' => 'material', 'arah' => -1, 'qty' => true,
            'kolom' => ['tgl', 'pengambil', 'material', 'sn', 'ket', 'mitra'], 'label' => ['material' => 'Deskripsi'], 'wajib' => ['tgl', 'pengambil', 'material'], 'filter' => ['pengambil' => 'Semua pengambil', 'mitra' => 'Semua mitra'], 'oleh' => 'pengambil'],
        'ritel' => ['sheet' => 'Pengambilan_Ritel', 'judul' => 'Pengambilan Ritel', 'file' => 'ritel.php', 'jenis' => 'Ritel', 'ikon' => 'shop', 'mat' => 'material', 'arah' => -1, 'qty' => true,
            'kolom' => ['no', 'tgl', 'mitra', 'bidang', 'nama', 'material', 'sn', 'lokasi', 'ket'], 'label' => [], 'wajib' => ['tgl', 'mitra', 'material'], 'filter' => ['mitra' => 'Semua mitra', 'bidang' => 'Semua bidang'], 'oleh' => 'mitra'],
        'ggn' => ['sheet' => 'Pengambilan_Ggn', 'judul' => 'Pengambilan Gangguan', 'file' => 'lainnya.php?m=ggn', 'jenis' => 'Gangguan', 'ikon' => 'exclamation-diamond', 'mat' => 'material', 'arah' => -1, 'qty' => true,
            'kolom' => ['no', 'tgl', 'mitra', 'bidang', 'nama', 'material', 'sn', 'lokasi', 'ket'], 'label' => [], 'wajib' => ['tgl', 'material'], 'filter' => ['mitra' => 'Semua mitra', 'bidang' => 'Semua bidang'], 'oleh' => 'mitra'],
        'icrm' => ['sheet' => 'Pengambilan_ICRM', 'judul' => 'Pengambilan Aktivasi ICRM', 'file' => 'lainnya.php?m=icrm', 'jenis' => 'ICRM', 'ikon' => 'router', 'mat' => 'perangkat', 'arah' => 0, 'qty' => false,
            'kolom' => ['no', 'tgl', 'wo', 'mitra', 'nama', 'lokasi', 'perangkat', 'sn'], 'label' => [], 'wajib' => ['tgl', 'wo', 'perangkat'], 'filter' => ['mitra' => 'Semua mitra'], 'oleh' => 'mitra'],
        'aktivasi' => ['sheet' => 'Pengambilan_Aktivasi', 'judul' => 'Pengambilan Aktivasi', 'file' => 'lainnya.php?m=aktivasi', 'jenis' => 'Aktivasi', 'ikon' => 'lightning-charge', 'mat' => 'pa', 'arah' => 0, 'qty' => false,
            'kolom' => ['no', 'tgl', 'pa', 'mitra', 'bidang', 'sn'], 'label' => [], 'wajib' => ['tgl', 'pa'], 'filter' => ['mitra' => 'Semua mitra', 'bidang' => 'Semua bidang'], 'oleh' => 'mitra'],
        'har' => ['sheet' => 'Pengambilan_HAR', 'judul' => 'Pengambilan HAR', 'file' => 'lainnya.php?m=har', 'jenis' => 'HAR', 'ikon' => 'tools', 'mat' => 'material', 'arah' => -1, 'qty' => true,
            'kolom' => ['no', 'tgl', 'pengambil', 'bidang', 'nama', 'material', 'sn', 'lokasi', 'ket'], 'label' => [], 'wajib' => ['tgl', 'material'], 'filter' => ['pengambil' => 'Semua pengambil', 'bidang' => 'Semua bidang'], 'oleh' => 'pengambil'],
        'pembangunan' => ['sheet' => 'Pengambilan_Pembangunan', 'judul' => 'Pengambilan Pembangunan', 'file' => 'lainnya.php?m=pembangunan', 'jenis' => 'Pembangunan', 'ikon' => 'building', 'mat' => 'material', 'arah' => -1, 'qty' => true,
            'kolom' => ['no', 'tgl', 'bidang', 'nama', 'material', 'sn', 'lokasi', 'ket'], 'label' => [], 'wajib' => ['tgl', 'material'], 'filter' => ['bidang' => 'Semua bidang'], 'oleh' => 'nama'],
    ];
    foreach ($M as $k => &$m) {
        $m['key'] = $k; $lab = $m['label'] + $L; $m['kol'] = [];
        foreach ($m['kolom'] as $c) $m['kol'][$c] = $lab[$c];
        $m['lab'] = $lab;
    }
    return $M;
}
function mod_sheets($arah = null) { $o = []; foreach (MOD() as $m) if ($arah === null || $m['arah'] === $arah) $o[] = $m['sheet']; return $o; }
function mod_by_sheet($sheet) { foreach (MOD() as $m) if ($m['sheet'] === $sheet) return $m; return null; }
function kolom($k) {   // dipakai laporan / data material
    if ($k === 'barang') return ['nama' => 'Nama Material', 'valtype' => 'Valtype', 'satuan' => 'Satuan', 'awal' => 'Stok Awal', 'masuk' => 'Masuk', 'keluar' => 'Keluar', 'stok' => 'Stok Akhir', 'min' => 'Stok Minimum', 'status' => 'Status', 'ket' => 'Keterangan'];
    return MOD()[$k]['kol'];
}

// ---------- stok ----------
/** Stok = Stok Awal (Data Material, dari Kartu gantung SAP per STOK_AWAL_TANGGAL) + masuk - keluar/pengambilan SESUDAH tanggal itu.
 *  Transaksi bertanggal <= STOK_AWAL_TANGGAL adalah riwayat (sudah tercermin di stok awal) dan tidak mengubah stok. */
function stok_ctx() {
    static $C = null; if ($C !== null) return $C;
    $D = ambil(array_merge(['Data_Barang'], mod_sheets(1), mod_sheets(-1)));
    $rows = []; $byName = [];
    foreach ($D['Data_Barang'] as $b) {
        $nm = mkey($b['nama']); if ($nm === '') continue; $k = $nm . '|' . mkey($b['valtype']); if (isset($rows[$k])) continue;
        $rows[$k] = ['nama' => trim($b['nama']), 'valtype' => $b['valtype'], 'satuan' => $b['satuan'] !== '' ? $b['satuan'] : 'Unit', 'awal' => n($b['awal']), 'min' => n($b['min']), 'ket' => $b['ket'], 'master' => true, '_r' => $b['_r'], '_s' => $b['_s']];
        $byName[$nm][] = $k;
    }
    $baru = function ($nama, $vt) use (&$rows, &$byName) {
        $nm = mkey($nama); $k = $nm . '|' . mkey($vt);
        if (!isset($rows[$k])) { $rows[$k] = ['nama' => trim($nama), 'valtype' => $vt, 'satuan' => 'Unit', 'awal' => 0, 'min' => 0, 'ket' => '', 'master' => false, '_r' => '', '_s' => '']; $byName[$nm][] = $k; }
        return $k;
    };
    $snvt = []; $mv = [];
    foreach ($D['Barang_Masuk'] as $b) {
        if (mkey($b['nama']) === '') continue; $k = $baru($b['nama'], $b['valtype']);
        foreach (sn_list($b['sn']) as $s) $snvt[strtoupper($s)] = mkey($b['valtype']);
        if (iso($b['tgl'])) $mv[] = [$b['tgl'], $k, unit($b['sn']), 'Masuk', $b['sn']];
    }
    $best = [];
    foreach (MOD() as $m) {
        if ($m['arah'] !== -1) continue; $mt = $m['mat'];
        foreach ($D[$m['sheet']] as $b) {
            $nm = mkey($b[$mt] ?? ''); if ($nm === '') continue;
            $k = null; $l = sn_list($b['sn']);
            if ($l && isset($snvt[strtoupper($l[0])]) && isset($rows[$nm . '|' . $snvt[strtoupper($l[0])]])) $k = $nm . '|' . $snvt[strtoupper($l[0])];
            elseif (!empty($byName[$nm])) {
                if (!isset($best[$nm])) { $bk = $byName[$nm][0]; foreach ($byName[$nm] as $kk) if ($rows[$kk]['awal'] > $rows[$bk]['awal']) $bk = $kk; $best[$nm] = $bk; }
                $k = $best[$nm];
            } else $k = $baru($b[$mt], '');
            if (iso($b['tgl'])) $mv[] = [$b['tgl'], $k, -unit($b['sn']), $m['jenis'], $b['sn']];
        }
    }
    return $C = ['rows' => $rows, 'mv' => $mv, 'byName' => $byName, 'D' => $D];
}
/** Daftar stok pada tanggal $tgl (null = sekarang). Masuk/Keluar = pergerakan setelah STOK_AWAL_TANGGAL sampai $tgl. */
function stok_baris($C, $tgl = null, $semuaDerived = false) {
    $cut = STOK_AWAL_TANGGAL; $akhir = $tgl ?? '9999-12-31'; $R = $C['rows'];
    foreach ($R as &$r) { $r['masuk'] = 0; $r['keluar'] = 0; $r['net'] = 0; } unset($r);
    foreach ($C['mv'] as $m) {
        $t = $m[0];
        if ($akhir >= $cut) { if ($t > $cut && $t <= $akhir) { $R[$m[1]]['net'] += $m[2]; if ($m[2] > 0) $R[$m[1]]['masuk'] += $m[2]; else $R[$m[1]]['keluar'] -= $m[2]; } }
        elseif ($t > $akhir && $t <= $cut) $R[$m[1]]['net'] -= $m[2];
    }
    $o = [];
    foreach ($R as $r) {
        if (!$r['master'] && !$semuaDerived && $r['masuk'] + $r['keluar'] === 0) continue;
        $r['stok'] = $r['awal'] + $r['net'];
        $r['status'] = !$r['master'] ? 'Belum terdaftar' : ($r['stok'] <= 0 ? 'Habis' : ($r['min'] > 0 && $r['stok'] <= $r['min'] ? 'Menipis' : 'Tersedia'));
        $o[] = $r;
    }
    usort($o, function ($a, $b) { return strcasecmp($a['nama'], $b['nama']) ?: strcasecmp($a['valtype'], $b['valtype']); });
    return $o;
}
/** Stok material (jumlah semua valtype) saat ini untuk pengecekan sebelum transaksi keluar. null = material belum terdaftar. */
function stok_nama($nama) {
    $C = stok_ctx(); $nm = mkey($nama); if (empty($C['byName'][$nm])) return null;
    $ada = false; foreach ($C['byName'][$nm] as $k) if ($C['rows'][$k]['master']) $ada = true; if (!$ada) return null;
    $s = 0; foreach (stok_baris($C) as $r) if (mkey($r['nama']) === $nm) $s += $r['stok'];
    return $s;
}
function stok_total_pada($C, $tgl) {
    $cut = STOK_AWAL_TANGGAL; $s = 0; foreach ($C['rows'] as $r) if ($r['master']) $s += $r['awal'];
    foreach ($C['mv'] as $m) {
        if ($tgl >= $cut) { if ($m[0] > $cut && $m[0] <= $tgl) $s += $m[2]; } elseif ($m[0] > $tgl && $m[0] <= $cut) $s -= $m[2];
    }
    return $s;
}

// ---------- riwayat & duplikat ----------
function riwayat() {
    $D = ambil(mod_sheets()); $o = [];
    foreach (MOD() as $k => $m) {
        foreach ($D[$m['sheet']] as $x) {
            $oleh = trim(implode(' / ', array_filter([$m['key'] === 'masuk' ? ($x['divisi'] !== '' ? 'Divisi ' . $x['divisi'] : '') : ($x[$m['oleh']] ?? ''), $m['key'] === 'keluar' ? ($x['mitra'] ?? '') : ($m['key'] === 'ritel' || $m['key'] === 'ggn' ? ($x['nama'] ?? '') : '')], 'strlen')));
            $ket = $x['ket'] ?? ($x['lokasi'] ?? ($x['perangkat'] ?? ''));
            $o[] = ['tgl' => $x['tgl'], 'jenis' => $m['jenis'], 'material' => $x[$m['mat']] ?? '', 'valtype' => $x['valtype'] ?? '', 'sn' => $x['sn'] ?? '', 'oleh' => $oleh, 'ket' => $ket, '_m' => $k, '_r' => $x['_r'], '_s' => $x['_s'], '_row' => $x];
        }
    }
    usort($o, function ($a, $b) { return strcmp($b['tgl'], $a['tgl']) ?: ($b['_r'] <=> $a['_r']); });
    return $o;
}
function dup_map($sheets) {
    $c = [];
    foreach (ambil($sheets) as $rows) foreach ($rows as $x) foreach (sn_list($x['sn'] ?? '') as $s) { $k = strtoupper($s); $c[$k] = ($c[$k] ?? 0) + 1; }
    return $c;
}

// ---------- periode dashboard ----------
function agregat_harian($rows) {
    $o = [];
    foreach ($rows as $x) { $k = iso($x['tgl']) ? $x['tgl'] : '0000-00-00'; $o[$k] = ($o[$k] ?? 0) + unit($x['sn'] ?? ''); }
    return $o;
}
function jumlah_rentang($map, $a, $b) { $s = 0; foreach ($map as $k => $v) if ($k >= $a && $k <= $b) $s += $v; return $s; }
function daftar_tahun($sheets) {
    $th = [(int)date('Y'), 2024];
    foreach ($sheets as $rows) foreach ($rows as $x) if (isset($x['tgl'][3]) && preg_match('/^(\d{4})-/', $x['tgl'], $m) && (int)$m[1] > 1990) $th[(int)$m[1]] = (int)$m[1];
    return range(max($th), min($th));
}
