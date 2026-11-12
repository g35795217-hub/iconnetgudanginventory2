<?php
// php tools/ekspor_json.php file.xlsx hasil.json  -> normalisasi Excel ke JSON (dipakai untuk menyiapkan inventory.xlsx)
require __DIR__ . '/../lib_excel.php';
ini_set('memory_limit', '1024M');
$h = normalisasi_xlsx($argv[1]);
file_put_contents($argv[2], json_encode($h, JSON_UNESCAPED_UNICODE));
foreach ($h['lap'] as $t => $l) echo str_pad($t, 26), ' dibaca ', str_pad($l['dibaca'], 6), ' kosong ', str_pad($l['kosong'], 5), ' diimpor ', count($h['data'][$t]), "\n";
