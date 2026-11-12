# PLN Icon Plus - Gudang Inventory (siap online)

**Teknologi:** PHP 7.4+ (tanpa framework) + Google Sheets via Google Apps Script. Database utama tetap **Google Spreadsheet "inventory"** (semua data lama, SN, pengambilan, dan laporan tetap sama). Tidak memakai MySQL, jadi phpMyAdmin dan import SQL **tidak diperlukan**; alasannya di bagian "Keputusan teknologi".

## Yang baru di versi ini
- Tampilan **Liquid Glass** (`assets/glass.css`; hapus satu baris `<link>` di `header.php` untuk kembali ke tampilan lama).
- **Pengaturan terpisah**: semua isian (URL Apps Script, API key, tanggal stok awal, login) ada di `config.local.php`. Lokal vs hosting dikenali otomatis dari nama host; tidak perlu ubah banyak file.
- **Login admin** opsional (`login.php`, kata sandi di-hash), sesi aman, batas percobaan gagal (5x, kunci 15 menit), proteksi **CSRF** pada semua formulir, header keamanan, HTTPS otomatis di hosting, galat disembunyikan di hosting.
- `.htaccess` memblokir unduhan `config*.php`, `Code.gs`, `*.xlsx`, `*.md`, `*.log`, `*.json`, folder `cache/`, `data/`, `tools/`.
- Menu **Backup Data** (unduh ZIP JSON+CSV semua sheet) dan backup harian otomatis ke Google Drive (`Code.gs`).
- `cek.php` kini memeriksa versi PHP, HTTPS, login, dan API key.

## Keputusan teknologi
Aplikasi lama sudah memakai Google Sheets sebagai database dengan cache, penguncian (LockService), validasi stok dan SN. Menambah MySQL berarti menulis ulang lapisan data dan membuat dua sumber kebenaran yang harus disinkronkan, dengan risiko data ganda/hilang; itu bertentangan dengan permintaan "jangan ubah fungsi dan data lama". Jadi MySQL tidak ditambahkan. Bila nanti perlu, bisa dikerjakan sebagai tahap terpisah.

## 1. Menjalankan di XAMPP
1. Ekstrak ZIP ke `C:\xampp\htdocs\gudang_inventory`.
2. Start **Apache** di XAMPP (MySQL tidak perlu).
3. Buka `http://localhost/gudang_inventory/`. Bila data tidak tampil, buka `cek.php`.

## 2. Menghubungkan Google Spreadsheet (sudah terisi untuk akun Anda)
`config.local.php` sudah berisi URL Apps Script dan API key yang selama ini Anda pakai. Untuk spreadsheet baru: ikuti bagian **Pasang** di bawah (unggah `inventory.xlsx`, tempel `Code.gs`, Deploy, salin URL `/exec` ke `APPS_SCRIPT_URL`).
**Penting:** API key harus berupa kunci acak panjang. Nilai baru sudah dibuat pada `config.local.php`. Salin nilai `API_KEY` dari file tersebut ke Apps Script > Project Settings > Script Properties dengan nama properti `API_KEY` (nilainya harus sama persis). Setelah mengubah Script Properties, lakukan Deploy > Manage deployments > Edit > New version > Deploy agar Web App menggunakan versi terbaru.

## 3. Login
1. Di XAMPP buka `http://localhost/gudang_inventory/buat_password.php`, ketik kata sandi (min. 8 karakter), salin hasil hash.
2. Tempel ke `config.local.php`: `define('ADMIN_HASH', 'hasil-hash');` (dan ubah `ADMIN_USER` bila perlu).
3. Alternatif lewat terminal: `php -r "echo password_hash('KATASANDI', PASSWORD_DEFAULT);"`
Kosongkan `ADMIN_HASH` bila tidak ingin login (tidak disarankan saat online). Halaman `buat_password.php` menolak akses dari luar localhost.

## 4. Upload ke hosting cPanel
1. Atur `API_KEY` di `config.local.php` dan samakan dengan Script Property `API_KEY` di Apps Script; isi `ADMIN_HASH` bila login admin diperlukan.
2. Compress folder jadi ZIP > cPanel **File Manager** > `public_html` (atau subfolder/subdomain) > Upload > Extract.
3. cPanel > **MultiPHP Manager**: pilih PHP 8.1/8.2; **Select PHP Version > Extensions**: aktifkan `curl`, `zip`, `xml`, `mbstring`.
4. Pastikan folder `cache/` dan `data/` dapat ditulis (izin 755; bila gagal 775).
5. Buka domain Anda, lalu `/cek.php`: semua baris harus OK.
6. Hosting harus mengizinkan koneksi keluar ke `script.google.com` (shared hosting umumnya boleh).

## 5. Domain dan SSL
1. Arahkan domain ke hosting (nameserver dari hosting, atau A record ke IP hosting).
2. cPanel > **SSL/TLS Status** > Run AutoSSL (gratis) untuk domain tersebut.
3. Setelah SSL aktif, http otomatis dialihkan ke https. Jika SSL belum aktif dan situs tidak bisa dibuka, sementara tambahkan `define('FORCE_HTTPS', false);` di `config.local.php`.

## 6. Backup dan pemeliharaan
- Menu **Backup Data**: unduh ZIP (JSON + CSV semua sheet). Lakukan minimal seminggu sekali.
- Backup otomatis harian: Apps Script > pilih fungsi `pasangBackupHarian` > Jalankan sekali (setujui izin Drive). Salinan ke folder Drive "Backup Gudang Inventory", 30 terakhir disimpan. Tempel ulang `Code.gs` dan Deploy versi baru lebih dulu.
- Jangan hapus isi `cache/` sembarangan: itu salinan terakhir data bila Google sedang tidak terjangkau. Aman dihapus hanya bila spreadsheet sudah benar dan terhubung.
- Cadangkan juga `config.local.php`. Cek `cache/php-error.log` bila ada masalah di hosting.

---
# PLN Icon Plus - Gudang Inventory (berbasis versi rekomendasi)

PHP + XAMPP + Google Sheets + Google Apps Script. Tanpa login. Ikon, font, dan grafik sudah ada di folder `assets/vendor`, jadi web tetap jalan tanpa internet (kecuali untuk Google Sheets).

## Tampilan
Sidebar biru gelap dengan logo PLN Icon Plus di atas dan tulisan "Gudang Inventory" tepat di bawahnya (sesuai spesifikasi). Untuk sidebar putih ubah `const TEMA = 'gelap';` menjadi `'terang'` di `config.local.php`. Paket "sidebar-terang" sudah memakai pilihan itu; isi dan fungsinya sama persis.

## Menu
Dashboard, Data Material, Material Masuk, Material Keluar, Pengambilan Ritel, **Pengambilan Lainnya** (Gangguan, Aktivasi ICRM, Aktivasi, HAR, Pembangunan), Master SN, Riwayat Transaksi, Laporan, Import Excel.

## Spreadsheet `inventory` (10 sheet, sudah terisi dari Excel Anda)
| Sheet | Kolom | Sumber di Excel |
|---|---|---|
| `Data_Barang` | Nama Material, Valtype, Satuan, Stok Awal, Stok Minimum, Keterangan | Kartu gantung |
| `Barang_Masuk` | No, Tanggal, Nama Material, Valtype, SN, Keterangan, Divisi | Material Masuk |
| `Barang_Keluar` | Tanggal, Yang Mengambil, Material, SN, Keterangan, Mitra | Material Keluar |
| `Pengambilan_Ritel` | No, Tanggal, Mitra, Bidang, Nama, Material, SN, Lokasi, Keterangan | Pengambilan Retail |
| `Pengambilan_Ggn` | No, Tanggal, Mitra, Bidang, Nama, Material, SN, Lokasi, Keterangan | Pengambilan ggn |
| `Pengambilan_ICRM` | No, Tanggal, WO, Mitra, Nama, Lokasi, Perangkat, SN | Pengambilan Aktivasi ICRM |
| `Pengambilan_Aktivasi` | No, Tanggal, PA, Mitra, Bidang, SN | Pengambilan Aktivasi |
| `Pengambilan_HAR` | No, Tanggal, Yang Mengambil, Bidang, Nama, Material, SN, Lokasi, Keterangan | Pengambilan HAR |
| `Pengambilan_Pembangunan` | No, Tanggal, Bidang, Nama, Material, SN, Lokasi, Keterangan | Pengambilan Pembangunan |
| `Master_SN` | Kode Material, Deskripsi Material, Valtype, Plnt, SLoc, Serial Number, Keterangan, Lokasi | Sheet1, Sheet2, Sheet3 (tersembunyi) |

Spreadsheet boleh diedit langsung; web membaca ulang isinya (cache 30 detik) dan memuat ulang halaman hanya bila isi spreadsheet benar-benar berubah.

## Cara membaca stok (penting)
- Excel Anda hanya memuat 7 baris Material Masuk, jadi stok tidak bisa dihitung dari "masuk dikurangi keluar" sejak 2024. Stok resmi adalah **Kartu gantung** (SAP), yang menjadi **Stok Awal** di Data Material (6.546 unit, per material dan Valtype/Batch).
- Transaksi bertanggal **sama atau sebelum `STOK_AWAL_TANGGAL`** (default 05/11/2026, di `config.local.php`) adalah riwayat: tampil di Riwayat, Laporan, dan grafik, tetapi tidak mengubah stok. Transaksi **sesudah** tanggal itu menambah (Masuk) atau mengurangi (Keluar, Ritel, Gangguan, HAR, Pembangunan) stok.
- **Ubah `STOK_AWAL_TANGGAL` di `config.local.php` jika Kartu gantung Anda diambil dari SAP pada tanggal lain.**
- Aktivasi ICRM dan Aktivasi tidak mengubah stok (isinya teks bebas).
- Nama material pada transaksi Excel (mis. "DW 100M", "ONT FIBERHOME") berbeda dengan deskripsi SAP di Kartu gantung. Material yang belum terdaftar di Data Material muncul dengan status "Belum terdaftar" bila ada transaksi baru; tombol "+" di barisnya mendaftarkannya.

## Aturan SN dan stok
- SN **tidak wajib**. Dengan SN: satu SN = satu unit, satu baris. Tanpa SN: isi **Jumlah**, web membuat satu baris per unit.
- Tombol penghapus (eraser) pada baris **mengosongkan SN** tanpa menghapus barisnya; tombol tempat sampah menghapus baris.
- SN ganda ditolak: Material Masuk unik di sheet masuk; Material Keluar, Ritel, Gangguan, HAR, Pembangunan unik di gabungan kelimanya. Duplikat yang sudah ada di data lama ditandai merah.
- Stok minus dicegah: transaksi keluar melebihi stok material terdaftar ditolak. Material yang belum terdaftar diizinkan dengan catatan peringatan.
- Valtype (dropdown): EX-PROJECT, DISMANTLE, RUSAK-L, RUSAK-TL, BAIK. Di Material Masuk otomatis terisi dari Data Material. (Data Excel "RUISAK-TL" dikoreksi menjadi RUSAK-TL.)

## Dashboard
Grafik **Pergerakan Stok** berupa garis berarea (stok akhir tiap hari / bulan / tahun), dihitung dari stok Kartu gantung per `STOK_AWAL_TANGGAL` dengan seluruh transaksi yang tercatat. Penerimaan barang yang tidak tercatat di Excel tidak ikut terhitung, jadi angka sebelum tanggal itu adalah perkiraan.

Filter Periode **Harian / Bulanan / Tahunan**, Bulan, dan Tahun (2026, 2025, 2024, dan tahun lebih lama bila ada di data). Kartu dan grafik mengikuti periode; grafik Aktivitas Harian (tanggal 1 sampai akhir bulan), Grafik Bulanan, dan Perbandingan Tahunan selalu tampil.

## Import Excel (menu Import Excel)
Unggah file "List Pengambilan Material Gudang" (.xlsx) -> **Periksa file** (pratinjau, belum menulis apa pun) -> **Mulai import** (bertahap dengan progres). Data yang sudah ada tidak digandakan dan tidak ditimpa, kecuali Stok Awal pada Kartu gantung yang diperbarui bila angkanya berubah. Mengimpor file yang sama dua kali menambah 0 baris.

## Hasil pemeriksaan data Excel Anda (otomatis, lihat juga "catatan pemeriksaan" di halaman Import)
- 517 tanggal salah ketik di Material Keluar (mis. `Wednesday, March 11, 20216`, `Thursday, April, 16, 2026`, `07.10.20-24`) dibaca dan diperbaiki; 6 tanggal salah tahun hasil seret-isi (17.10.2025 sampai 2030) di sheet gangguan mengikuti baris sekitarnya, teks aslinya dicatat di Keterangan.
- Baris tanpa tanggal tetap diimpor dengan tanggal kosong: Material Keluar 131, Ritel 177. Isi tanggalnya lewat tombol Ubah agar masuk ke grafik periode.
- Kolom H tanpa judul di Material Keluar (116 baris, mis. RULEMLBD) digabung ke Keterangan.
- Material Masuk: sel berisi 32 SN dipecah menjadi satu baris per SN (7 baris menjadi 38).
- Aktivasi ICRM: baris perangkat mengikuti No, Tanggal, WO, Mitra, Nama, Lokasi dari baris induknya. Pengambilan HAR memiliki dua susunan kolom dalam satu sheet; keduanya dikenali otomatis.
- Baris kosong dan baris yang hanya berisi nomor dilewati. Baris ber-SN yang persis sama (Keluar 2, Ritel 11, ICRM 1, HAR 1) hanya dimasukkan sekali.
- Kartu gantung dan SN SAP (Sheet1-3) tidak cocok jumlahnya (6.546 unit vs 3.387 SN unik); Master SN hanya sebagai daftar rujukan.

## Pasang
1. **Spreadsheet:** unggah `inventory.xlsx` ke Google Drive, buka dengan Google Sheets, File > Simpan sebagai Google Spreadsheet. Nama: **inventory**. (Sudah berisi seluruh data Excel Anda. Jika memakai spreadsheet lama, jalankan Import Excel.)
2. **Apps Script:** Ekstensi > Apps Script, hapus isi lama, tempel seluruh `Code.gs` (versi 10), simpan.
3. Jalankan fungsi `setup` sekali (membuat sheet yang belum ada). Bila `Barang_Keluar` lama berformat Mitra/Bidang/Lokasi, jalankan `migrasi` sekali.
4. Deploy > Kelola deployment > pensil > Versi baru > Deploy (Jalankan sebagai Saya, akses Siapa saja). URL `/exec` tetap; bila membuat deployment baru, salin URL-nya ke `config.local.php`.
5. Buka URL `/exec` di browser: tampil "PLN Gudang Inventory API aktif - versi 10 - terhubung ke spreadsheet: inventory".
6. Salin folder ke `C:\xampp\htdocs\pln-gudang-inventory-rekomendasi`, start Apache, buka `http://localhost/pln-gudang-inventory-rekomendasi/`.
7. **Bila ada masalah, buka `http://localhost/pln-gudang-inventory-rekomendasi/cek.php`**: halaman itu memeriksa ekstensi PHP, folder cache, koneksi dan versi Apps Script, serta kecepatan baca sheet.
8. Ekstensi PHP yang dibutuhkan (aktif secara bawaan di XAMPP): curl, zip, xml/simplexml/xmlreader, mbstring. Folder `cache/` harus bisa ditulis. Untuk mengimpor file besar, pastikan `upload_max_filesize` dan `post_max_size` di php.ini minimal 16M (bawaan XAMPP 40M).

Opsional: Apps Script > Project Settings > Script Properties, tambah `API_KEY` yang sama dengan `API_KEY` di `config.local.php`.

## Bila ada masalah
- Galat "Argumen tak valid: timeZone": tempel `Code.gs` versi 10 lalu Deploy versi baru. Cara cepat tanpa kode baru: di Google Sheets buka File > Setelan > Zona waktu, pilih (GMT+07:00) Jakarta.
Buka `http://localhost/pln-gudang-inventory-rekomendasi/cek.php`: memeriksa ekstensi PHP, folder cache, koneksi ke Apps Script (dan apakah sudah versi 10), serta kecepatan membaca sheet.

## Logo
`assets/logo.png` adalah logo PLN Icon Plus dari gambar yang Anda kirim, hanya dipotong tepi putihnya; bentuk, warna, dan proporsinya tidak diubah. Ganti file itu bila ingin versi lain.

## Kecepatan
- Tiap sheet di-cache di folder `cache/`. Cache yang sudah lewat 30 detik tetap ditampilkan seketika, lalu dibaca ulang dari Google **setelah** halaman terkirim (maksimal 30 menit); data hasil tulis/ubah/hapus langsung tampil karena cache sheet itu dibuang.
- Batas waktu PHP dinaikkan otomatis ke 120 detik (bawaan XAMPP 30 detik) untuk pembacaan pertama sheet besar.
- Tanggal dibaca dan ditulis menurut zona waktu spreadsheet, jadi tidak bergeser sehari.
- **Halaman tidak menunggu Google:** bila cache sudah lebih dari 30 detik tetapi kurang dari 30 menit, halaman langsung tampil dari cache, lalu data disegarkan di latar belakang (dibuktikan: 4,3 detik menjadi 0,08 detik saat Google lambat). Setelah tambah / ubah / hapus, cache sheet itu dibuang sehingga perubahan langsung terlihat.
- Batas waktu PHP dinaikkan otomatis ke 120 detik (bawaan XAMPP 30 detik) untuk pembacaan pertama yang lama.
Tiap sheet di-cache 30 detik dan dibaca paralel dari Google; pagination, pencarian, urut, dan filter memakai Fetch API tanpa memuat ulang halaman; dashboard hanya menghitung data satu kali per tampilan; Chart.js, ikon, dan font disajikan lokal. Pembacaan pertama setelah cache kosong bergantung pada kecepatan Google Sheets (sheet Material Keluar paling besar, sekitar 21 ribu baris).

## File
`index.php` Dashboard; `barang.php` Data Material; `barang_masuk.php`, `barang_keluar.php`, `ritel.php`, `lainnya.php?m=ggn|icrm|aktivasi|har|pembangunan`; `master_sn.php`; `riwayat.php`; `laporan.php`; `cek.php` (pemeriksaan sistem); `import.php` + `import_run.php` + `import_lib.php` + `lib_excel.php`; `proses.php`; `cetak.php`; `sync.php`; `config.local.php`; `fungsi.php` (data, cache, stok), `modul.php` (halaman transaksi), `tabel.php` (tabel, form, ekspor); `header.php`, `footer.php`, `assets/`; `Code.gs`; `inventory.xlsx`; `tools/` (pembuat inventory.xlsx dari Excel).

## Pembaruan (perbaikan form, urutan data, sinkron spreadsheet)
- **Form tambah / ubah** selalu tepat di tengah layar (horizontal dan vertikal), responsif di HP.
- **Deskripsi**: kolom "Material" (Material Keluar) dan "Nama Material" (Material Masuk) kini berlabel **Deskripsi**. Isian berupa kotak teks biasa; tidak ada lagi dropdown hitam / daftar saran / riwayat isian browser di web. Judul kolom di Google Sheets tidak perlu diubah (judul lama maupun "Deskripsi" / "Nama Deskripsi" sama-sama dikenali).
  Karena daftar saran dihapus, ketik deskripsi persis seperti di Data Material agar stok terpotong dengan benar (nama yang belum terdaftar tetap tersimpan dengan catatan peringatan).
- **SN**: label hanya "SN"; tetap boleh dikosongkan (isi Jumlah bila tanpa SN).
- **Urutan**: di web data baru tampil paling atas. Ekspor Excel / PDF berurutan terbalik: **lama di atas, terbaru di bawah** (semua baris dan kolom ikut, mengikuti pencarian/filter yang aktif). Jika Anda mengklik judul kolom untuk mengurutkan, ekspor mengikuti urutan pilihan Anda itu.
- **Sinkron Spreadsheet** (menu baru): isi link Google Sheets sumber per menu (Masuk, Keluar, Ritel, dst.), opsional nama tab, lalu **Periksa dulu** (pratinjau, belum menulis) atau **Sinkronkan**.
  - Hanya baris yang belum ada yang ditambahkan; tidak ada data yang dihapus, spreadsheet sumber tidak diubah.
  - Pencocokan: baris ber-SN menurut SN (SN yang sama tidak masuk dua kali); baris tanpa SN menurut seluruh isi baris (jumlahnya dibandingkan, hanya kelebihannya yang masuk). Sel SN berisi beberapa SN dipecah satu baris per SN. Mengklik Sinkronkan berulang kali menambah 0 baris bila sumber tidak berubah.
  - Opsi "Perbarui juga baris ber-SN yang sudah ada": hanya mengisi ulang kolom yang terisi di sumber dan berbeda; tidak pernah mengosongkan atau menghapus.
  - Baris yang tidak bisa dibaca (mis. tanggal tidak valid, Valtype tidak dikenal di Material Masuk) dilewati dan alasannya ditampilkan, bukan hilang diam-diam.
  - Metode aman: pembacaan dilakukan oleh Apps Script (`SpreadsheetApp.openById`) memakai akun pemilik script, jadi spreadsheet sumber **tidak perlu dibagikan ke publik**; cukup beri akses Lihat ke akun tersebut. Link disimpan di `data/sinkron.json` (folder `data/` ditutup oleh `.htaccess`), kunci API tidak pernah dikirim ke browser.
  - Catatan: baris tanpa SN yang Anda ubah di web setelah disinkron dapat dianggap baru pada sinkron berikutnya; ubah juga di sumber agar konsisten.

### Yang harus dilakukan setelah mengganti file
1. Ganti seluruh isi folder web dengan paket ini (pengaturan `config.local.php` Anda tetap bisa disalin kembali).
2. **Tempel ulang `Code.gs` (versi 11)** di Apps Script, lalu **Deploy > Kelola deployment > Edit > Versi baru > Deploy** (URL tetap sama). Saat pertama kali, Google akan meminta izin tambahan untuk membuka spreadsheet lain; setujui agar sinkronisasi dapat membaca spreadsheet sumber.
