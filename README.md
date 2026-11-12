# ID Card App — PT WOOIN

Aplikasi web internal berbasis **PHP Native** dan **MySQL** yang digunakan untuk manajemen data karyawan serta pencetakan kartu tanda pengenal (ID Card) PT WOOIN. Aplikasi ini dilengkapi dengan **Visual Layout Editor** interaktif, sistem penyesuaian teks pintar (agar nama panjang tidak terpotong), serta fitur import data dan foto secara massal.

---

## 📌 Ringkasan Fitur Aplikasi

### 1. Manajemen Data Karyawan
* **CRUD Lengkap**: Tambah, lihat, ubah, dan hapus data karyawan (NIK, Nama Lengkap, Nama di ID Card, Jenis Kelamin, Departemen, Jabatan, Status, LPK, Tanggal Masuk, dan Status Aktif).
* **Pencarian & Filter Cepat**: Filter karyawan berdasarkan Departemen, Status Kerja, status keaktifan, serta pencarian instan berdasarkan NIK atau Nama.
* **Paginasi Fleksibel**: Pilihan jumlah data per halaman (20, 50, 100, 200 data).
* **Penyimpanan Foto**: Foto karyawan disimpan langsung dalam database dalam format binary (BLOB) dan disajikan secara aman via helper stream gambar.

### 2. Pencetakan ID Card (PDF Generator)
* **Cetak Satuan (Single)**: Cetak ID card untuk 1 karyawan terpilih (tampak depan dan belakang).
* **Cetak Massal (Batch)**: Pilih beberapa karyawan sekaligus dengan checkbox, lalu cetak dalam satu dokumen PDF siap cetak dengan tata letak grid rapi pada kertas A4.
* **Smart Text Fitting (Anti-Potong)**: Jika nama karyawan panjang, sistem otomatis memperkecil ukuran font secara bertahap. Jika font sudah mencapai batas minimum, teks otomatis dipecah menjadi 2 baris (multiline center) sehingga nama tidak akan pernah terpotong.
* **Pilihan Mode Nama**: Opsi cetak menggunakan **Nama di ID Card** (nama panggilan/singkat) atau **Nama Lengkap** asli.

### 3. Editor Visual Tata Letak Kartu (Layout per Status)
* **Layout Spesifik per Status**: Setiap status kerja (Kontrak, Magang, Tetap, Yayasan, atau status baru) memiliki template background dan koordinat posisi masing-masing. Status yang belum diatur otomatis menggunakan layout *Default*.
* **Interactive Drag & Drop**: Geser kotak posisi Foto, Nama, NIK, dan Barcode langsung di atas gambar kartu dengan mouse, atau atur angka presisi (satuan milimeter) pada panel input.
* **Kustomisasi Background**: Fitur upload, ganti, dan reset gambar latar belakang kartu depan maupun belakang (format PNG, JPG, WEBP).

### 4. Import & Export Massal
* **Import Data via Excel (.xlsx)**: Tambah dan perbarui data karyawan secara massal menggunakan file Excel tanpa memerlukan ekstensi Composer/library eksternal berat.
* **Download Template Excel**: Template siap pakai yang dilengkapi dengan daftar kode referensi valid (Departemen, Jabatan, Status, LPK) agar terhindar dari salah ketik.
* **Upload Foto Massal**: Unggah puluhan/ratusan foto karyawan sekaligus dalam satu klik. Sistem otomatis mencocokkan nama file foto (contoh: `19951006.jpg`) dengan NIK karyawan yang ada di database.

### 5. Manajemen Master Data
* Pengelolaan data master umum (Departemen, Jabatan, Status, dan LPK) secara dinamis dengan deteksi struktur tabel otomatis (*table introspection*).

---

## 🛠️ Kebutuhan Sistem & Tech Stack

* **Bahasa**: PHP 7.4 s/d PHP 8.x (PHP Native / Prosedural)
* **Database**: MySQL / MariaDB
* **Web Server**: Apache / Nginx (Laragon, XAMPP, dsb.)
* **Ekstensi PHP Wajib**:
  * `mysqli` (koneksi database)
  * `fileinfo` (validasi tipe file gambar)
  * `zip` (membaca file Excel via `ZipArchive`)
  * `simplexml` (parsing data Excel)
* **Library Eksternal**:
  * `vendor/tcpdf/` (TCPDF standalone, sudah terbundel di dalam project)

---

## 🚀 Panduan Instalasi & Penggunaan

1. **Letakkan Folder Project**:
   * Pindahkan folder `idcard-app` ke direktori web server Anda:
     * Laragon: `C:\laragon\www\idcard-app` atau `D:\laragon\www\idcard-app`
     * XAMPP: `C:\xampp\htdocs\idcard-app`

2. **Siapkan Database**:
   * Pastikan database `hru` sudah dibuat di MySQL Anda.
   * Import struktur tabel awal `tbkaryawan`, `tbdepartemen`, `tbjabatan`, `tbstatus`, dan `tbllpk` jika belum ada.
   * *Catatan:* Tabel pengaturan kartu (`tbidcard_template`) **otomatis dibuat dan diisi oleh aplikasi** saat pertama kali fitur layout/cetak dibuka (referensi struktur manual tersedia di `sql/upgrade.sql`).

3. **Sesuaikan Pengaturan Database**:
   * Salin template konfigurasi menjadi file konfigurasi lokal (file ini tidak ikut ke Git):
     ```bash
     copy config/database.example.php config/database.php
     ```
   * Buka `config/database.php`, lalu sesuaikan `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, dan `DB_NAME` dengan server lokal Anda:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'hru');
     define('DB_PORT', 3307); // Ubah ke 3306 jika menggunakan port default XAMPP/MySQL
     ```

4. **Izin Tulis Folder Uploads**:
   * Pastikan web server memiliki izin menulis (*write permission*) pada folder `uploads/templates/`.

5. **Akses Aplikasi**:
   * Buka browser dan arahkan ke: `http://localhost/idcard-app/`

---

## 📂 Struktur Direktori

| Direktori / File | Penjelasan Fungsi |
| :--- | :--- |
| `index.php` | Halaman Dashboard utama & ringkasan statistik karyawan |
| `assets/css/style.css` | Seluruh desain antarmuka, layout responsif, dan tema aplikasi |
| `assets/img/` | Template gambar bawaan (depan/belakang) dan placeholder `no-photo.png` |
| `cards/` | Script penghasil dokumen PDF kartu (single & batch A4) |
| `config/` | Konfigurasi koneksi MySQL (`database.php`) |
| `includes/` | Kumpulan helper (layout kartu, TCPDF loader, Excel parser, pagination, stream foto BLOB) |
| `karyawan/` | Fitur data karyawan (daftar, form, simpan, hapus, import Excel, upload foto massal) |
| `master/` | Fitur master data & editor visual tata letak kartu (`template_edit.php`) |
| `sql/` | Referensi skrip SQL (`upgrade.sql`) |
| `uploads/templates/` | Folder penyimpanan file background kartu kustom yang diunggah pengguna |
| `vendor/tcpdf/` | Library PDF engine |

---

## ⚠️ Catatan Penting untuk Developer / Admin

1. **Port Database (`DB_PORT`)**: Periksa nilai `DB_PORT` di `config/database.php`. Jika Anda menggunakan MySQL standar XAMPP (port 3306), ubah nilai 3307 menjadi 3306 agar tidak gagal terhubung.
2. **Keamanan Akses**: Aplikasi ini dirancang untuk penggunaan jaringan lokal/intranet dan belum memiliki modul login/autentikasi pengguna. Jangan mengekspos aplikasi langsung ke internet publik tanpa proteksi akses.
3. **Penyimpanan Foto**: Foto karyawan disimpan dalam kolom database bertipe BLOB. Disarankan menjaga ukuran file foto tidak terlalu besar sebelum di-upload agar ukuran database tetap efisien.
