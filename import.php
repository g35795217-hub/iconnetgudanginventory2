<?php
require 'koneksi.php'; require 'lib_excel.php'; require 'import_lib.php';
@set_time_limit(300);
$judul = 'Import Excel'; $hasil = null; $err = '';
$judulSheet = ['Data_Barang' => 'Data Material (Kartu gantung)', 'Barang_Masuk' => 'Material Masuk', 'Barang_Keluar' => 'Material Keluar', 'Pengambilan_Ritel' => 'Pengambilan Ritel', 'Pengambilan_Ggn' => 'Pengambilan Gangguan',
    'Pengambilan_ICRM' => 'Aktivasi ICRM', 'Pengambilan_Aktivasi' => 'Pengambilan Aktivasi', 'Pengambilan_HAR' => 'Pengambilan HAR', 'Pengambilan_Pembangunan' => 'Pengambilan Pembangunan', 'Master_SN' => 'Master SN (daftar serial SAP)'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_FILES['berkas']['tmp_name']) || $_FILES['berkas']['error'] !== UPLOAD_ERR_OK) throw new Exception('Pilih file Excel (.xlsx) terlebih dahulu. Bila file besar, periksa upload_max_filesize di php.ini.');
        if (strtolower(pathinfo($_FILES['berkas']['name'], PATHINFO_EXTENSION)) !== 'xlsx') throw new Exception('Format file harus .xlsx');
        $tok = bin2hex(random_bytes(8)); $tmp = cache_dir() . '/import_' . $tok . '.xlsx';
        if (!move_uploaded_file($_FILES['berkas']['tmp_name'], $tmp)) throw new Exception('File tidak dapat disimpan sementara. Pastikan folder cache dapat ditulis.');
        $h = normalisasi_xlsx($tmp); @unlink($tmp);
        if (!array_filter($h['data'])) throw new Exception('Tidak ada sheet yang dikenali. Nama sheet yang dibaca: ' . implode(', ', array_keys(norm_sumber())) . '.');
        $plan = import_rencana($h['data']);
        foreach (glob(cache_dir() . '/imp_*.json') ?: [] as $f) if (time() - filemtime($f) > 7200) @unlink($f);
        file_put_contents(cache_dir() . '/imp_' . $tok . '.json', json_encode($plan, JSON_UNESCAPED_UNICODE));
        $hasil = ['tok' => $tok, 'plan' => $plan, 'lap' => $h['lap']];
    } catch (Exception $e) { $err = $e->getMessage(); }
}
include 'header.php';
if ($err) echo '<div class="fl e"><i class="bi bi-x-circle"></i> ' . h($err) . '</div>';
?>
<div class="card"><h3 class="ct">Import data dari Excel</h3>
  <p class="ring" style="margin:0 0 12px">Unggah file <b>List Pengambilan Material Gudang</b> (.xlsx). Sheet dikenali menurut namanya dan diperlakukan sesuai fungsinya: <i>Kartu gantung</i> menjadi stok awal Data Material, <i>Sheet1-3</i> menjadi Master SN, sheet pengambilan masuk ke menu masing-masing.
  Data yang sudah ada di spreadsheet <b>tidak ditimpa dan tidak digandakan</b>; Anda dapat mengunggah file yang sama berulang kali dengan aman.</p>
  <form method="post" enctype="multipart/form-data" class="filebox"><input type="file" name="berkas" accept=".xlsx" required> <button class="btn y"><i class="bi bi-search"></i> Periksa file</button>
  <p class="ring" style="margin:8px 0 0">Tahap 1 hanya memeriksa dan menampilkan pratinjau. Belum ada data yang ditulis.</p></form></div>
<?php if ($hasil): $baru = 0; foreach ($hasil['plan'] as $p) $baru += $p['baru'] + $p['ubah']; ?>
<div class="card imp"><div class="ch"><h3>Pratinjau import</h3>
  <?php if ($baru): ?><button class="btn y" id="mulai"><i class="bi bi-cloud-arrow-up"></i> Mulai import (<?= number_format($baru, 0, ',', '.') ?> baris)</button><?php else: ?><span class="bd g">Tidak ada data baru. Spreadsheet sudah sesuai dengan file.</span><?php endif; ?></div>
  <div class="tw"><table><thead><tr><th>Sheet tujuan</th><th>Sumber Excel</th><th>Dibaca</th><th>Kosong</th><th>Data valid</th><th>Akan ditambah</th><th>Diperbarui</th><th>Sudah ada</th><th>Duplikat di file</th><th>Progres</th></tr></thead><tbody>
  <?php foreach ($hasil['plan'] as $s => $p): $l = $hasil['lap'][$s]; ?>
    <tr><td><b><?= h($judulSheet[$s] ?? $s) ?></b></td><td><?= h($l['sumber']) ?></td><td><?= $l['dibaca'] ?></td><td><?= $l['kosong'] ?></td><td><?= $p['file'] ?></td><td><b><?= $p['baru'] ?></b></td><td><?= $p['ubah'] ?></td><td><?= $p['ada'] ?></td><td><?= $p['ganda'] ?></td>
      <td style="min-width:140px"><div class="prog"><u id="pg_<?= h($s) ?>"></u></div><small id="tx_<?= h($s) ?>"></small></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?php foreach ($hasil['lap'] as $s => $l): $cat = is_array($l['catatan']) ? $l['catatan'] : []; if (!$cat) continue; ?>
    <details style="margin-top:10px"><summary><b><?= h($judulSheet[$s] ?? $s) ?></b> - catatan pemeriksaan data (<?= count($cat) ?>)</summary><ul style="margin:6px 0 0 18px">
    <?php foreach ($cat as $k => $v): $c = $l['contoh'][$k] ?? []; ?><li><?= h($k) ?>: <b><?= $v ?></b><?= $c ? ' <small>contoh: ' . h(implode('; ', $c)) . '</small>' : '' ?></li><?php endforeach; ?></ul></details>
  <?php endforeach; ?>
  <p id="hasilimp" class="ring"></p>
</div>
<script>
(function () {
  var tok = <?= json_encode($hasil['tok']) ?>, P = <?= json_encode(array_map(function ($p) { return ['n' => count($p['kirim']), 'mode' => $p['mode']]; }, $hasil['plan'])) ?>, btn = document.getElementById('mulai');
  if (!btn) return;
  btn.addEventListener('click', async function () {
    btn.disabled = true; var out = document.getElementById('hasilimp'), tambah = 0, ubah = 0;
    try {
      for (var s in P) {
        var n = P[s].n, o = 0, step = P[s].mode === 'upsert' ? 1000 : 800; if (!n) continue;
        while (o < n) {
          var r = await fetch('import_run.php?t=' + tok + '&s=' + s + '&o=' + o + '&l=' + step); var j = await r.json();
          if (!j.ok) throw new Error(j.error || 'Gagal');
          o += step; tambah += j.baru || 0; ubah += j.ubah || 0; var pc = Math.min(100, Math.round(o / n * 100));
          document.getElementById('pg_' + s).style.width = pc + '%'; document.getElementById('tx_' + s).textContent = Math.min(o, n) + ' / ' + n;
        }
      }
      await fetch('import_run.php?t=' + tok + '&selesai=1');
      out.innerHTML = '<span class="bd g">Selesai</span> ' + tambah + ' baris ditambahkan, ' + ubah + ' baris diperbarui. Data langsung tampil di menu masing-masing.';
    } catch (e) { out.innerHTML = '<span class="bd r">Terhenti</span> ' + e.message + ' - Anda bisa menekan "Periksa file" lalu mengulang; baris yang sudah masuk tidak akan digandakan.'; btn.disabled = false; }
  });
})();
</script>
<?php endif; include 'footer.php'; ?>
