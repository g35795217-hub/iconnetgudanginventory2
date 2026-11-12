<?php
require 'koneksi.php';
$M = MOD(); $m = $M[$_GET['m'] ?? ''] ?? null; if (!$m) ke('index.php');
$D = ambil([$m['sheet']]); $x = null; foreach ($D[$m['sheet']] as $y) if ((string)$y['_r'] === (string)($_GET['r'] ?? '')) $x = $y;
if (!$x) die('Data tidak ditemukan.');
$t = '<h3>Bukti ' . h($m['judul']) . '</h3><table>';
foreach ($m['kol'] as $k => $lbl) $t .= '<tr><th style="width:30%">' . h($lbl) . '</th><td>' . (nl2br(h($k === 'tgl' ? tgl_tampil($x[$k]) : ($x[$k] ?? ''))) ?: '-') . '</td></tr>';
$pihak = $m['arah'] === 1 ? 'Penyerah' : 'Yang Mengambil'; $nama = $x['pengambil'] ?? ($x['nama'] ?? '');
$t .= '</table><div class="sg"><div>' . $pihak . '<br><br><br><br>(' . ($nama !== '' ? h($nama) : '................') . ')</div><div>Petugas Gudang<br><br><br><br>(................)</div></div>';
cetak_halaman($t);
