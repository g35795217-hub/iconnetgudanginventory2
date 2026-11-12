<?php
// ============================================================
// KONFIGURASI LOKAL / HOSTING  (file ini TIDAK boleh dibagikan; diblokir oleh .htaccess)
// Nilai di bawah adalah nilai yang sebelumnya ada di koneksi.php -> koneksi ke spreadsheet Anda tidak berubah.
// Saat pindah ke hosting: upload file ini lalu ubah hanya yang perlu (lihat PANDUAN.md).
// ============================================================

// --- Google Sheets (Apps Script) ---
define('APPS_SCRIPT_URL', 'https://script.google.com/macros/s/AKfycbxE_UUjMa66UdZR-FHgBBFKhKRntw-yzREpJDDlTvAPzfkgEK4ZdTIuWNNQtFKClBXh_Q/exec');
define('API_KEY', '1266ddce98d8cea6f8f18294e24ff6a724d2ba201289a9dc9c46aab265bebf12');   // Samakan persis dengan Script Property API_KEY di Apps Script

// --- Stok & tampilan ---
define('STOK_AWAL_TANGGAL', '2026-11-05');
define('TEMA', 'terang');       // 'terang' atau 'gelap' (sidebar)

// --- Login (sangat disarankan saat online) ---
// Kosong = tanpa login (seperti sebelumnya). Isi ADMIN_HASH agar halaman meminta login.
// Cara membuat hash: lihat PANDUAN.md bagian "Login".
define('ADMIN_USER', 'admin');
define('ADMIN_HASH', '');

// --- Opsional (biarkan bila tidak perlu) ---
// define('APP_ENV', 'hosting');     // 'lokal' atau 'hosting'; bila tidak diisi dideteksi otomatis dari nama host
// define('FORCE_HTTPS', true);      // alihkan otomatis http -> https (bawaan: aktif di hosting, mati di lokal)
// define('TZ', 'Asia/Jakarta');
