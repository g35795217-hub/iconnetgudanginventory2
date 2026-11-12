<?php
// Pemeriksaan lingkungan: buka http://localhost/<folder>/cek.php bila ada masalah koneksi / data tidak tampil.
require 'koneksi.php';
$judul = 'Cek Sistem'; $ok = '<span class="bd g">OK</span>'; $no = '<span class="bd r">Perlu diperbaiki</span>';
$rows = [];
foreach (['curl', 'zip', 'xmlreader', 'simplexml', 'mbstring', 'json'] as $e) $rows[] = ['Ekstensi PHP ' . $e, extension_loaded($e) ? $ok : $no, extension_loaded($e) ? '' : 'Aktifkan di php.ini (hapus tanda ; pada extension=' . $e . ') lalu restart Apache'];
$mem = ini_get('memory_limit'); $rows[] = ['memory_limit', $ok, $mem . ' (program menaikkannya otomatis ke 512M bila diizinkan)'];
$rows[] = ['max_execution_time', $ok, ini_get('max_execution_time') . ' detik (program menaikkannya ke 120 detik)'];
$rows[] = ['upload_max_filesize / post_max_size', $ok, ini_get('upload_max_filesize') . ' / ' . ini_get('post_max_size') . ' (untuk Import Excel, minimal 16M)'];
$cd = cache_dir(); $rows[] = ['Folder cache dapat ditulis', is_writable($cd) ? $ok : $no, $cd];
$t0 = microtime(true); $msg = ''; $cok = false;
try { $ch = curl_init(APPS_SCRIPT_URL); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 25]); $r = curl_exec($ch); $msg = $r === false ? curl_error($ch) : trim(strip_tags((string)$r)); curl_close($ch); $cok = $r !== false && stripos($msg, 'API aktif') !== false; } catch (Throwable $e) { $msg = $e->getMessage(); }
$rows[] = ['Apps Script (doGet)', $cok ? $ok : $no, h(mb_substr($msg, 0, 200)) . ($cok && strpos($msg, 'versi 11') === false ? ' <b>- belum versi 11: tempel Code.gs terbaru lalu Deploy versi baru</b>' : '')];
if ($cok) { try { $t1 = microtime(true); $d = api('boot', ['sheets' => ['Data_Barang']])['data']['Data_Barang'] ?? []; $rows[] = ['Baca sheet Data_Barang', $ok, count($d) . ' baris, ' . round((microtime(true) - $t1) * 1000) . ' ms']; } catch (Throwable $e) { $rows[] = ['Baca sheet Data_Barang', $no, h($e->getMessage())]; } }
$rows[] = ['STOK_AWAL_TANGGAL', $ok, STOK_AWAL_TANGGAL . ' (config.local.php)'];
$rows[] = ['Versi PHP (minimal 7.4)', version_compare(PHP_VERSION, '7.4.0', '>=') ? $ok : $no, PHP_VERSION];
$rows[] = ['Lingkungan terdeteksi', $ok, APP_ENV . (APP_ENV === 'hosting' ? ' (galat disembunyikan, dicatat di cache/php-error.log)' : ' (galat ditampilkan)')];
$rows[] = ['HTTPS', IS_HTTPS || APP_ENV === 'lokal' ? $ok : $no, IS_HTTPS ? 'Aktif' : (APP_ENV === 'lokal' ? 'Tidak diperlukan di localhost' : 'Aktifkan SSL di cPanel (AutoSSL / Let\'s Encrypt)')];
$rows[] = ['Login admin', AUTH_AKTIF ? $ok : (APP_ENV === 'lokal' ? $ok : $no), AUTH_AKTIF ? 'Aktif' : 'Belum aktif. Isi ADMIN_HASH di config.local.php sebelum website dipublikasikan.'];
$rows[] = ['API_KEY bukan bawaan', API_KEY !== 'pln2026' && strpos(API_KEY, 'GANTI') !== 0 ? $ok : $no, API_KEY === 'pln2026' ? 'Masih "pln2026". Ganti dengan kunci acak panjang (config.local.php) dan samakan dengan Script Property API_KEY.' : ''];
include 'header.php';
echo '<div class="card"><h3 class="ct">Pemeriksaan sistem</h3><div class="tw"><table class="mini"><thead><tr><th>Pemeriksaan</th><th>Status</th><th>Keterangan</th></tr></thead><tbody>';
foreach ($rows as $r) echo '<tr><td>' . h($r[0]) . '</td><td>' . $r[1] . '</td><td>' . $r[2] . '</td></tr>';
echo '</tbody></table></div></div>';
include 'footer.php';
