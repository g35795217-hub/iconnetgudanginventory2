<?php
// Sinkronisasi data dari spreadsheet sumber (link berbeda) ke sheet web.
// Pengaturan link disimpan di data/sinkron.json (folder data/ ditutup lewat .htaccess). Pembacaan dilakukan oleh Apps Script,
// jadi spreadsheet sumber TIDAK perlu dipublikasikan; cukup dapat dibuka oleh akun pemilik Apps Script.
require 'koneksi.php';
@set_time_limit(300);
$judul = 'Sinkron Spreadsheet'; $sheets = [];

function sk_dir() {
    $d = __DIR__ . '/data';
    if (!is_dir($d)) { @mkdir($d, 0777, true); @file_put_contents($d . '/.htaccess', "Require all denied\nDeny from all\n"); @file_put_contents($d . '/index.html', ''); }
    return (is_dir($d) && is_writable($d)) ? $d : cache_dir();
}
function sk_file() { return sk_dir() . '/sinkron.json'; }
function sk_load() { $j = is_file(sk_file()) ? json_decode((string)@file_get_contents(sk_file()), true) : null; return is_array($j) ? $j : []; }
function sk_save($c) { if (@file_put_contents(sk_file(), json_encode($c, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) throw new Exception('Pengaturan tidak dapat disimpan. Pastikan folder data/ dapat ditulis.'); }
function sk_cek($link) {
    $t = trim($link); if ($t === '') return '';
    if (strpos($t, '/spreadsheets/d/e/') !== false) return 'Link "Publikasikan ke web" tidak dapat dipakai. Gunakan link dari tombol Bagikan (berisi /spreadsheets/d/ID/edit).';
    if (!preg_match('#^https://docs\.google\.com/spreadsheets/d/[A-Za-z0-9_-]{20,}#', $t) && !preg_match('/^[A-Za-z0-9_-]{25,}$/', $t)) return 'Link Google Sheets tidak valid. Contoh: https://docs.google.com/spreadsheets/d/.../edit';
    return '';
}
function sk_jalan($k, $c, $dry) {
    $m = MOD()[$k];
    if (trim($c['link'] ?? '') === '') throw new Exception($m['judul'] . ': isi link spreadsheet sumber terlebih dahulu.');
    $r = api('sync', ['sheet' => $m['sheet'], 'source' => $c['link'], 'tab' => $c['tab'] ?? '', 'update' => !empty($c['upd']), 'dry' => $dry]);
    if (!$dry) cache_hapus([$m['sheet']]);
    return $r;
}
function sk_ringkas($m, $r) {
    $t = '<b>' . h($m['judul']) . '</b>' . (!empty($r['dry']) ? ' (pemeriksaan, belum ada data yang ditulis)' : '') . ' — tab "' . h($r['tab'] ?? '') . '": ' . (int)$r['total'] . ' baris sumber dibaca; '
        . (!empty($r['dry']) ? 'akan ditambahkan ' : 'ditambahkan ') . '<b>' . (int)$r['baru'] . '</b>, sudah ada ' . (int)$r['ada'] . ', SN ganda di sumber ' . (int)$r['ganda'] . ', dilewati ' . (int)$r['dilewati']
        . (!empty($r['diperbarui']) ? ', ' . (!empty($r['dry']) ? 'akan diperbarui ' : 'diperbarui ') . (int)$r['diperbarui'] : '') . '.';
    if (!empty($r['alasan'])) { $t .= '<br><small style="color:inherit;opacity:.85">Baris dilewati: ' . implode('; ', array_map('h', array_slice($r['alasan'], 0, 6))) . (count($r['alasan']) > 6 ? '; ...' : '') . '</small>'; }
    return $t;
}

$M = MOD();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $cfg = sk_load(); $err = [];
        foreach ($M as $k => $m) {
            $link = trim((string)($_POST['link'][$k] ?? '')); $e = sk_cek($link);
            if ($e !== '') $err[] = $m['judul'] . ': ' . $e;
            $cfg[$k] = ['link' => $link, 'tab' => trim((string)($_POST['tab'][$k] ?? '')), 'upd' => !empty($_POST['upd'][$k]), 'last' => $cfg[$k]['last'] ?? null];
        }
        if ($err) throw new Exception(implode(' ', $err));
        sk_save($cfg);
        $go = (string)($_POST['go'] ?? 'simpan'); $pesan = [];
        if ($go === 'simpan') $pesan[] = 'Pengaturan sinkronisasi tersimpan.';
        else {
            if ($go === 'semua') $target = array_keys(array_filter($cfg, function ($c) { return trim($c['link'] ?? '') !== ''; })); 
            else { $p = explode(':', $go, 2); $target = [$p[1] ?? '']; }
            $dry = strpos($go, 'cek:') === 0;
            if (!$target) throw new Exception('Belum ada link spreadsheet sumber yang diisi.');
            foreach ($target as $k) {
                if (!isset($M[$k])) throw new Exception('Menu tidak dikenal.');
                $r = sk_jalan($k, $cfg[$k], $dry); $pesan[] = sk_ringkas($M[$k], $r);
                if (!$dry) { $cfg[$k]['last'] = ['t' => date('d/m/Y H:i'), 'baru' => (int)$r['baru'], 'ada' => (int)$r['ada'], 'lewat' => (int)$r['dilewati'], 'ubah' => (int)($r['diperbarui'] ?? 0)]; sk_save($cfg); }
            }
        }
        flash(implode('<br>', $pesan));
    } catch (Exception $e) { flash(h($e->getMessage()), 'err'); }
    ke('sinkron.php');
}
$cfg = sk_load();
include 'header.php';
?>
<div class="card"><h3 class="ct">Sinkronisasi dari spreadsheet sumber</h3>
  <p class="ring" style="margin:0">Tempel link Google Sheets lain (misalnya daftar yang diisi petugas lapangan) pada menu yang sesuai, lalu tekan <b>Sinkronkan</b>. Isi spreadsheet sumber dibaca dan <b>hanya baris yang belum ada</b> yang ditambahkan ke web.
  Data lama <b>tidak dihapus</b> dan tidak digandakan: baris ber-SN dicocokkan menurut SN, baris tanpa SN menurut seluruh isi barisnya. Spreadsheet sumber tidak pernah diubah.</p>
  <p class="ring" style="margin:8px 0 0">Judul kolom di sumber dikenali otomatis (Tanggal, Yang Mengambil, Nama Deskripsi / Material, SN, Keterangan, Mitra, dst.). Akun Google pemilik Apps Script harus bisa membuka spreadsheet sumber (cukup akses <i>Lihat</i>); spreadsheet tidak perlu dibuat publik. File .xlsx di Drive harus dibuka dulu lalu <i>Simpan sebagai Google Spreadsheet</i>.</p>
</div>
<form method="post" autocomplete="off">
<?php foreach ($M as $k => $m): $c = $cfg[$k] ?? []; $l = $c['last'] ?? null; ?>
  <div class="card sk">
    <div class="ch"><h3><i class="bi bi-<?= h($m['ikon']) ?>"></i> <?= h($m['judul']) ?></h3><small>Sheet <?= h($m['sheet']) ?></small></div>
    <div class="skf">
      <label>Link spreadsheet sumber<input type="url" name="link[<?= h($k) ?>]" value="<?= h($c['link'] ?? '') ?>" placeholder="https://docs.google.com/spreadsheets/d/.../edit" autocomplete="off"></label>
      <label>Nama tab (opsional)<input name="tab[<?= h($k) ?>]" value="<?= h($c['tab'] ?? '') ?>" placeholder="otomatis" autocomplete="off"></label>
    </div>
    <label class="inl" style="margin-top:10px"><input type="checkbox" name="upd[<?= h($k) ?>]" value="1"<?= !empty($c['upd']) ? ' checked' : '' ?> style="width:auto"> Perbarui juga baris ber-SN yang sudah ada bila isinya di sumber berbeda (hanya kolom yang terisi di sumber)</label>
    <div class="skb">
      <button class="btn y" name="go" value="sync:<?= h($k) ?>"><i class="bi bi-cloud-arrow-down"></i> Sinkronkan</button>
      <button class="btn" name="go" value="cek:<?= h($k) ?>"><i class="bi bi-search"></i> Periksa dulu</button>
      <span class="skl"><?= $l ? 'Terakhir ' . h($l['t']) . ': +' . (int)$l['baru'] . ' baru, ' . (int)$l['ada'] . ' sudah ada' . (!empty($l['lewat']) ? ', ' . (int)$l['lewat'] . ' dilewati' : '') . (!empty($l['ubah']) ? ', ' . (int)$l['ubah'] . ' diperbarui' : '') : 'Belum pernah disinkronkan' ?></span>
    </div>
  </div>
<?php endforeach; ?>
  <div class="card fbar"><span class="fb-t"><i class="bi bi-info-circle"></i> Pengaturan ikut tersimpan setiap kali Anda menekan salah satu tombol.</span>
    <span><button class="btn" name="go" value="simpan"><i class="bi bi-check2"></i> Simpan pengaturan</button> <button class="btn y" name="go" value="semua" onclick="return confirm('Sinkronkan semua menu yang sudah diisi link-nya?')"><i class="bi bi-cloud-arrow-down"></i> Sinkronkan semua</button></span></div>
</form>
<?php include 'footer.php';
