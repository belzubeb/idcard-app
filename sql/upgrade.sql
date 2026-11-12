0-- ============================================================================
-- sql/upgrade.sql
-- ============================================================================
-- CATATAN: Tabel ini SUDAH otomatis dibuat sendiri oleh aplikasi (lihat
-- includes/template_store.php -> idcardEnsureTemplateTable()) begitu ada
-- halaman yang butuh, jadi file ini SEBENARNYA TIDAK WAJIB dijalankan manual.
--
-- Jalankan file ini secara manual HANYA KALAU:
--   - user database aplikasi kamu TIDAK punya hak akses CREATE TABLE, atau
--   - kamu mau lihat persis struktur tabel barunya sebelum dipakai.
--
-- Aman dijalankan berkali-kali (pakai IF NOT EXISTS / INSERT IGNORE).
-- ============================================================================

CREATE TABLE IF NOT EXISTS `tbidcard_template` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `kode_status` VARCHAR(50) NOT NULL,
    `label` VARCHAR(100) NOT NULL DEFAULT '',
    `front_image` VARCHAR(255) DEFAULT NULL,
    `back_image` VARCHAR(255) DEFAULT NULL,
    `card_front_w` DECIMAL(6,2) NOT NULL DEFAULT 52.1,
    `card_back_w` DECIMAL(6,2) NOT NULL DEFAULT 53.0,
    `card_h` DECIMAL(6,2) NOT NULL DEFAULT 79.1,
    `gap_front_back` DECIMAL(6,2) NOT NULL DEFAULT 5.0,
    `photo_x` DECIMAL(6,2) NOT NULL DEFAULT 17.2,
    `photo_y` DECIMAL(6,2) NOT NULL DEFAULT 29.2,
    `photo_w` DECIMAL(6,2) NOT NULL DEFAULT 20.1,
    `photo_h` DECIMAL(6,2) NOT NULL DEFAULT 24.2,
    `nama_x` DECIMAL(6,2) NOT NULL DEFAULT 11.4,
    `nama_y` DECIMAL(6,2) NOT NULL DEFAULT 59.0,
    `nama_w` DECIMAL(6,2) NOT NULL DEFAULT 31.7,
    `nama_h` DECIMAL(6,2) NOT NULL DEFAULT 3.8,
    `nama_fontsz` INT NOT NULL DEFAULT 8,
    `nik_x` DECIMAL(6,2) NOT NULL DEFAULT 11.4,
    `nik_y` DECIMAL(6,2) NOT NULL DEFAULT 62.9,
    `nik_w` DECIMAL(6,2) NOT NULL DEFAULT 31.7,
    `nik_h` DECIMAL(6,2) NOT NULL DEFAULT 3.7,
    `nik_fontsz` INT NOT NULL DEFAULT 8,
    `barcode_w` DECIMAL(6,2) NOT NULL DEFAULT 28.0,
    `barcode_h` DECIMAL(6,2) NOT NULL DEFAULT 6.5,
    `barcode_y` DECIMAL(6,2) NOT NULL DEFAULT 70.0,
    `barcode_xres` DECIMAL(5,2) NOT NULL DEFAULT 0.25,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `kode_status` (`kode_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `tbidcard_template`
    (kode_status, label, is_default, updated_at) VALUES
    ('_DEFAULT_', 'Default (fallback)', 1, NOW()),
    ('KONTRAK',   'Kontrak',            0, NOW()),
    ('MAGANG',    'Magang',             0, NOW()),
    ('TETAP',     'Tetap',              0, NOW()),
    ('YAYASAN',   'Yayasan',            0, NOW());

-- front_image / back_image sengaja dibiarkan NULL di baris di atas:
-- aplikasi otomatis fallback ke gambar bawaan di
-- assets/img/templates/{kontrak,magang,tetap,yayasan}/{front,back}.png
-- sampai kamu upload gambar custom lewat menu Master Data > Layout Kartu.
