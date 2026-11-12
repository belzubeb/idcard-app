<?php
/**
 * includes/card_layout.php
 * Koordinat presisi (mm) + mapping template per status karyawan.
 *
 * PENTING - WAJIB DICEK:
 * Array 'status_map' di bawah ini menghubungkan NILAI ASLI kolom
 * KodeStatus (atau nama status dari tabel tbstatus) ke folder template.
 * Saya isi dengan tebakan umum ('KONTRAK','MAGANG','TETAP','YAYASAN').
 * Kalau di database kamu nilainya beda (misal cuma 'K','M','T','Y', atau
 * kode angka), SESUAIKAN key di 'status_map' ini biar template yang
 * kepilih otomatis benar.
 */

return [
    // Ukuran kartu (mm) - sama untuk semua template/status
    'card_front_w' => 52.1,
    'card_back_w'  => 53.0,
    'card_h'       => 79.1,
    'gap_front_back' => 5.0,

    // Kotak foto (relatif ke pojok kiri-atas kartu DEPAN)
    'photo_x' => 17.2,
    'photo_y' => 29.2,
    'photo_w' => 20.1,
    'photo_h' => 24.2,

    // Baris nama
    'nama_x' => 11.4, 'nama_y' => 59.0, 'nama_w' => 31.7, 'nama_h' => 3.8, 'nama_fontsz' => 8,
    // Baris NIK
    'nik_x' => 11.4, 'nik_y' => 62.9, 'nik_w' => 31.7, 'nik_h' => 3.7, 'nik_fontsz' => 8,

    // Barcode (belakang) - diperkecil & dipusatkan otomatis secara horizontal
    // (x dihitung otomatis di generate.php: (card_back_w - barcode_w) / 2)
    'barcode_w'    => 28.0,
    'barcode_h'    => 6.5,
    'barcode_y'    => 70.0,
    'barcode_xres' => 0.25, // ketebalan bar (lebih kecil = lebih tipis)

    // Folder tempat semua template disimpan
    'template_dir' => __DIR__ . '/../assets/img/templates/',

    // Mapping nilai KodeStatus -> nama folder template
    // SESUAIKAN key di kiri ini dengan nilai asli di database kamu!
    'status_map' => [
        'KONTRAK' => 'kontrak',
        'MAGANG'  => 'magang',
        'TETAP'   => 'tetap',
        'YAYASAN' => 'yayasan',
    ],

    // Kalau KodeStatus karyawan gak ketemu di status_map di atas,
    // pakai template default ini biar tetap bisa generate (gak fatal error).
    'default_template' => 'kontrak',
];
