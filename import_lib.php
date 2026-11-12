<?php
/** Rencana import: membandingkan hasil normalisasi Excel dengan isi spreadsheet saat ini supaya data yang sama tidak masuk dua kali.
 *  - Data_Barang (Kartu gantung): material + valtype baru ditambahkan, yang sudah ada hanya diperbarui Stok Awalnya.
 *  - Master_SN: SN yang belum ada ditambahkan.
 *  - Transaksi: baris ber-SN yang sama persis dilewati; baris tanpa SN dibandingkan jumlahnya (hanya kelebihannya yang masuk). */
function import_rencana($data) {
    $names = array_keys($data); cache_hapus($names); unset($_GET['fresh']);
    $ex = lembar($names); $plan = [];
    $seq = ['Barang_Masuk', 'Pengambilan_Ritel', 'Pengambilan_Ggn', 'Pengambilan_HAR', 'Pengambilan_Pembangunan'];
    foreach ($data as $sheet => $rows) {
        $p = ['mode' => 'tambah', 'file' => count($rows), 'baru' => 0, 'ubah' => 0, 'ada' => 0, 'ganda' => 0, 'kirim' => []];
        if ($sheet === 'Data_Barang') {
            $p['mode'] = 'upsert'; $idx = [];
            foreach ($ex[$sheet] as $x) $idx[mkey($x['nama']) . '|' . mkey($x['valtype'])] = $x;
            foreach ($rows as $r) {
                $k = mkey($r['nama']) . '|' . mkey($r['valtype']);
                if (!isset($idx[$k])) { $p['baru']++; $p['kirim'][] = $r; }
                elseif ((int)$idx[$k]['awal'] !== (int)$r['awal']) { $p['ubah']++; $p['kirim'][] = $r; }
                else $p['ada']++;
            }
        } elseif ($sheet === 'Master_SN') {
            $have = []; foreach ($ex[$sheet] as $x) $have[strtoupper($x['sn'])] = 1;
            foreach ($rows as $r) { if (isset($have[strtoupper($r['sn'])])) $p['ada']++; else { $p['baru']++; $p['kirim'][] = $r; } }
        } else {
            $cnt = []; $max = 0;
            foreach ($ex[$sheet] as $x) { $fp = norm_fp($sheet, $x); $cnt[$fp] = ($cnt[$fp] ?? 0) + 1; $max = max($max, (int)($x['no'] ?? 0)); }
            $seen = [];
            foreach ($rows as $r) {
                $fp = norm_fp($sheet, $r); $seen[$fp] = ($seen[$fp] ?? 0) + 1; $ada = $cnt[$fp] ?? 0;
                if (trim($r['sn'] ?? '') !== '') { if ($seen[$fp] > 1) { $p['ganda']++; continue; } if ($ada > 0) { $p['ada']++; continue; } }
                elseif ($seen[$fp] <= $ada) { $p['ada']++; continue; }
                $p['kirim'][] = $r;
            }
            if (in_array($sheet, $seq, true)) foreach ($p['kirim'] as $i => &$r) $r['no'] = $max + $i + 1; unset($r);
            $p['baru'] = count($p['kirim']);
        }
        $plan[$sheet] = $p;
    }
    return $plan;
}
