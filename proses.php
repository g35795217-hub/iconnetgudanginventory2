<?php
require 'koneksi.php';
$aksi = $_POST['aksi'] ?? ''; $sheet = $_POST['sheet'] ?? '';
$kembali = basename($_POST['kembali'] ?? 'index.php');
if (!preg_match('/^[a-z_]+\.php$/', $kembali)) $kembali = 'index.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $aksi === '') ke('index.php');
$mod = mod_by_sheet($sheet);
if ($mod && $kembali !== 'riwayat.php') { $kembali = $mod['file']; }
elseif ($sheet === 'Data_Barang') $kembali = 'barang.php';
$KEYS = $mod ? array_merge(array_values(array_diff($mod['kolom'], ['no'])), ['jumlah']) : ['nama', 'valtype', 'satuan', 'awal', 'min', 'ket'];
try {
    if (!$mod && $sheet !== 'Data_Barang') throw new Exception('Sheet tidak dikenal.');
    if ($aksi === 'del') {
        api('del', ['sheet' => $sheet, '_r' => (int)($_POST['_r'] ?? 0), '_s' => (string)($_POST['_s'] ?? '')]);
        flash('Data berhasil dihapus dari spreadsheet.');
    } elseif ($aksi === 'kosongsn') {
        cache_hapus([$sheet]); $rows = lembar([$sheet])[$sheet]; $row = null;
        foreach ($rows as $x) if ((int)$x['_r'] === (int)$_POST['_r']) $row = $x;
        if (!$row || $row['_s'] !== (string)($_POST['_s'] ?? '')) throw new Exception('Data di spreadsheet sudah berubah. Muat ulang halaman lalu coba lagi.');
        $d = []; foreach ($KEYS as $k) if (isset($row[$k])) $d[$k] = $row[$k]; $d['sn'] = '';
        if (($d['tgl'] ?? 'x') === '' ) throw new Exception('Baris ini belum punya tanggal. Isi tanggal lewat tombol Ubah terlebih dahulu.');
        $d['tgl'] = tgl_norm($d['tgl'] ?? '');
        api('save', ['sheet' => $sheet, 'data' => $d, '_r' => (int)$row['_r'], '_s' => $row['_s']]);
        flash('SN dikosongkan. Baris tetap tercatat sebagai unit tanpa SN.');
    } elseif ($aksi === 'add' || $aksi === 'save') {
        $d = []; foreach ($KEYS as $k) if (isset($_POST[$k]) && is_string($_POST[$k])) $d[$k] = $_POST[$k];
        $warn = '';
        if ($mod && $aksi === 'add') {
            $sn = trim($d['sn'] ?? ''); $qty = $sn !== '' ? 1 : max(1, (int)($d['jumlah'] ?? 1));
            if ($qty > 500) throw new Exception('Jumlah maksimal 500 unit per input. Pisahkan menjadi beberapa input.');
            $nm = trim($d[$mod['mat']] ?? '');
            if ($mod['key'] === 'masuk' && trim($d['valtype'] ?? '') === '' && $nm !== '') foreach (ambil(['Data_Barang'])['Data_Barang'] as $x) if (mkey($x['nama']) === mkey($nm) && $x['valtype'] !== '') { $d['valtype'] = $x['valtype']; break; }
            if ($mod['arah'] === -1 && $nm !== '' && tgl_norm($d['tgl'] ?? '') > STOK_AWAL_TANGGAL) {
                $st = stok_nama($nm);
                if ($st === null) $warn = 'Material "' . $nm . '" belum terdaftar di Data Material, stoknya tidak dapat diperiksa.';
                elseif ($st < $qty) throw new Exception('Stok ' . $nm . ' tidak cukup. Tersedia ' . $st . ', diminta ' . $qty . '.');
            }
        }
        $p = ['sheet' => $sheet, 'data' => $d];
        if ($aksi === 'save') { $p['_r'] = (int)($_POST['_r'] ?? 0); $p['_s'] = (string)($_POST['_s'] ?? ''); }
        $r = api($aksi, $p); $c = (int)($r['count'] ?? 1);
        $m = $aksi === 'save' ? 'Perubahan berhasil disimpan ke spreadsheet.' : ($c > 1 ? $c . ' unit tanpa SN berhasil ditambahkan ke spreadsheet.' : 'Data berhasil ditambahkan ke spreadsheet.');
        flash($m . ($warn ? ' Catatan: ' . h($warn) : ''));
    } else throw new Exception('Aksi tidak dikenal.');
} catch (Exception $e) { flash(h($e->getMessage()), 'err'); }
cache_hapus([$sheet]);
ke($kembali);
