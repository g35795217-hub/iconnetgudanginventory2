<?php
require 'koneksi.php';
@set_time_limit(300); header('Content-Type: application/json');
try {
    $t = $_GET['t'] ?? ''; if (!preg_match('/^[a-f0-9]{16}$/', $t)) throw new Exception('Token tidak valid');
    $f = cache_dir() . '/imp_' . $t . '.json'; if (!is_file($f)) throw new Exception('Rencana import tidak ditemukan atau sudah kedaluwarsa. Unggah ulang file.');
    if (!empty($_GET['selesai'])) { @unlink($f); cache_hapus(); echo json_encode(['ok' => true]); exit; }
    $s = $_GET['s'] ?? ''; if (!isset(norm_skema()[$s])) throw new Exception('Sheet tidak dikenal');
    $plan = json_decode(file_get_contents($f), true); if (!isset($plan[$s])) throw new Exception('Sheet tidak ada dalam rencana');
    $o = max(0, (int)($_GET['o'] ?? 0)); $l = max(1, min(1000, (int)($_GET['l'] ?? 800)));
    $rows = array_slice($plan[$s]['kirim'], $o, $l);
    $r = $rows ? api('import', ['sheet' => $s, 'rows' => $rows, 'mode' => $plan[$s]['mode']]) : ['baru' => 0, 'ubah' => 0];
    cache_hapus([$s]);
    echo json_encode(['ok' => true, 'baru' => (int)($r['baru'] ?? 0), 'ubah' => (int)($r['ubah'] ?? 0)]);
} catch (Exception $e) { echo json_encode(['ok' => false, 'error' => $e->getMessage()]); }
