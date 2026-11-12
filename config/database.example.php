<?php
/**
 * config/database.example.php
 * TEMPLATE konfigurasi database.
 *
 * SALIN file ini menjadi config/database.php, lalu sesuaikan nilainya
 * dengan server lokal kamu (Laragon / XAMPP):
 *
 *     copy config/database.example.php config/database.php
 *
 * File config/database.php sengaja TIDAK ikut ke Git (lihat .gitignore)
 * supaya kredensial database tidak pernah bocor ke repository.
 */

// ==== KONFIGURASI DATABASE ====
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hru');
define('DB_PORT', 3307); // 3306 untuk MySQL standar XAMPP, 3307 untuk Laragon

// ==== KONEKSI ====
$koneksi = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if (!$koneksi) {
    // Jangan tampilkan detail error mysqli ke user di production,
    // cukup log ke error_log server.
    error_log('DB Connection Error: ' . mysqli_connect_error());
    die('Koneksi database gagal. Silakan hubungi admin.');
}

// Pastikan karakter set konsisten (biar nama karyawan dgn karakter aneh gak berantakan)
mysqli_set_charset($koneksi, 'utf8mb4');

// Timezone PHP disamakan (opsional, tapi bagus buat TglEntry dll kalau dihitung di PHP)
date_default_timezone_set('Asia/Jakarta');
