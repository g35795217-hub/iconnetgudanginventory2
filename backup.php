<?php
require 'koneksi.php';
$ns = array_keys(norm_skema());
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!class_exists('ZipArchive')) throw new Exception('Ekstensi PHP "zip" belum aktif.');
        $res = api_multi($ns);   // baca LANGSUNG dari Google Sheets (bukan cache) agar backup benar-benar terbaru
        foreach ($ns as $nm) if ($res[$nm] instanceof Exception) throw new Exception('Gagal membaca sheet ' . $nm . ': ' . $res[$nm]->getMessage() . ' Backup dibatalkan, tidak ada data yang diubah.');
        $tmp = tempnam(cache_dir(), 'bk'); $z = new ZipArchive();
        if ($z->open($tmp, ZipArchive::OVERWRITE) !== true) throw new Exception('Gagal membuat file ZIP sementara. Pastikan folder cache dapat ditulis.');
        $rg = 'Backup PLN Gudang Inventory - ' . date('d/m/Y H:i:s') . "\n\n";
        foreach ($ns as $nm) {
            $rows = $res[$nm]; $rg .= str_pad($nm, 26) . count($rows) . " baris\n";
            $z->addFromString($nm . '.json', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $kol = []; foreach ($rows as $r) foreach ($r as $k => $v) if ($k !== '' && $k[0] !== '_' && !in_array($k, $kol, true)) $kol[] = $k;
            $f = fopen('php://temp', 'r+'); fwrite($f, "\xEF\xBB\xBF"); fputcsv($f, $kol);
            foreach ($rows as $r) { $l = []; foreach ($kol as $k) $l[] = $r[$k] ?? ''; fputcsv($f, $l); }
            rewind($f); $z->addFromString($nm . '.csv', stream_get_contents($f)); fclose($f);
        }
        $z->addFromString('RINGKASAN.txt', $rg); $z->close();
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/zip'); header('Content-Length: ' . filesize($tmp)); header('Cache-Control: no-store');
        header('Content-Disposition: attachment; filename="backup-gudang-inventory-' . date('Ymd-His') . '.zip"');
        readfile($tmp); @unlink($tmp); exit;
    } catch (Exception $e) { if (!empty($tmp)) @unlink($tmp); flash(h($e->getMessage()), 'err'); ke('backup.php'); }
}
$judul = 'Backup Data';
include 'header.php';
?>
<div class="card">
  <h3 class="ct"><i class="bi bi-shield-check"></i> Backup data ke komputer Anda</h3>
  <p>Mengunduh satu file ZIP berisi <b>seluruh sheet</b> (<?= count($ns) ?> sheet) dalam format <b>JSON</b> dan <b>CSV</b> (bisa dibuka di Excel), dibaca langsung dari Google Sheets. Data di spreadsheet tidak diubah.</p>
  <form method="post" style="margin-top:12px"><button class="btn y"><i class="bi bi-download"></i> Unduh backup sekarang</button></form>
</div>
<div class="card">
  <h3 class="ct"><i class="bi bi-clock-history"></i> Backup otomatis harian (di Google Drive)</h3>
  <p>Di Apps Script jalankan fungsi <code>pasangBackupHarian</code> sekali. Setiap hari spreadsheet disalin ke folder Drive <b>Backup Gudang Inventory</b> dan 30 salinan terakhir disimpan. Langkah lengkap ada di PANDUAN.md.</p>
  <p>Disarankan juga mengunduh backup di atas minimal seminggu sekali.</p>
</div>
<?php include 'footer.php';
