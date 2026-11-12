<?php
// Cek ringan: apakah isi spreadsheet berubah sejak halaman dibuka? (tidak memuat ulang halaman)
require 'koneksi.php';
header('Content-Type: application/json'); header('Cache-Control: no-store');
$ok = array_keys(norm_skema()); $nm = array_values(array_intersect(explode(',', $_GET['s'] ?? ''), $ok));
try { lembar($nm, true); echo json_encode(['ver' => lembar_ver($nm)]); } catch (Exception $e) { echo json_encode(['ver' => '', 'error' => true]); }
