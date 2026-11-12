<?php
/**
 * includes/template_store.php
 *
 * Sistem layout kartu yang bisa diedit lewat browser (Master Data > Layout Kartu):
 *  - Setiap status (KONTRAK/MAGANG/TETAP/YAYASAN/dst) punya baris sendiri di tabel
 *    `tbidcard_template`, isinya: gambar depan/belakang + posisi foto/nama/NIK/barcode.
 *  - Kalau status karyawan belum ada baris khusus, otomatis pakai baris '_DEFAULT_'.
 *  - Tabel dibuat otomatis (CREATE TABLE IF NOT EXISTS) + di-seed dari 4 template
 *    lama (kontrak/magang/tetap/yayasan) supaya aplikasi lama tetap jalan tanpa
 *    perlu import SQL manual. Migrasi manual tetap disediakan di sql/upgrade.sql.
 */

define('IDCARD_TEMPLATE_TABLE', 'tbidcard_template');
define('IDCARD_UPLOAD_DIR', __DIR__ . '/../uploads/templates/');
define('IDCARD_UPLOAD_URLBASE', 'uploads/templates/');

/** Nilai dasar (fallback) kalau kolom di DB kosong / tabel baru dibuat. */
function idcardDefaultLayoutValues()
{
    return [
        'card_front_w'   => 52.1,
        'card_back_w'    => 53.0,
        'card_h'         => 79.1,
        'gap_front_back' => 5.0,
        'photo_x' => 17.2, 'photo_y' => 29.2, 'photo_w' => 20.1, 'photo_h' => 24.2,
        'nama_x' => 11.4, 'nama_y' => 59.0, 'nama_w' => 31.7, 'nama_h' => 3.8, 'nama_fontsz' => 8,
        'nik_x'  => 11.4, 'nik_y'  => 62.9, 'nik_w'  => 31.7, 'nik_h'  => 3.7, 'nik_fontsz'  => 8,
        'barcode_w' => 28.0, 'barcode_h' => 6.5, 'barcode_y' => 70.0, 'barcode_xres' => 0.25, 'barcode_x_offset' => 0.0,
    ];
}

/** Pastikan tabel template ada + ke-seed data awal. Aman dipanggil berkali-kali. */
function idcardEnsureTemplateTable(mysqli $koneksi)
{
    static $done = false;
    if ($done) return;
    $done = true;

    $t = IDCARD_TEMPLATE_TABLE;
    mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS `$t` (
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
        `barcode_x_offset` DECIMAL(6,2) NOT NULL DEFAULT 0.0,
        `is_default` TINYINT(1) NOT NULL DEFAULT 0,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `kode_status` (`kode_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Jaga-jaga buat instalasi lama yang tabelnya udah kebentuk sebelum kolom ini ada.
    $colCheck = mysqli_query($koneksi, "SHOW COLUMNS FROM `$t` LIKE 'barcode_x_offset'");
    if ($colCheck && mysqli_num_rows($colCheck) === 0) {
        mysqli_query($koneksi, "ALTER TABLE `$t` ADD COLUMN `barcode_x_offset` DECIMAL(6,2) NOT NULL DEFAULT 0.0 AFTER `barcode_xres`");
    }

    $res = mysqli_query($koneksi, "SELECT COUNT(*) c FROM `$t`");
    $row = $res ? mysqli_fetch_assoc($res) : ['c' => 0];
    if ((int)($row['c'] ?? 0) === 0) {
        $d = idcardDefaultLayoutValues();
        $legacy = [
            '_DEFAULT_' => ['Default (fallback)', 'kontrak', 1],
            'KONTRAK'   => ['Kontrak', 'kontrak', 0],
            'MAGANG'    => ['Magang', 'magang', 0],
            'TETAP'     => ['Tetap', 'tetap', 0],
            'YAYASAN'   => ['Yayasan', 'yayasan', 0],
        ];
        foreach ($legacy as $kode => $meta) {
            [$label, $legacyFolder, $isDefault] = $meta;
            // gambar lama tetap dipakai lewat legacy_asset_path(), jadi front_image/back_image
            // sengaja dikosongkan (NULL) supaya resolver otomatis fallback ke assets lama.
            $stmt = mysqli_prepare($koneksi, "INSERT IGNORE INTO `$t`
                (kode_status,label,front_image,back_image,card_front_w,card_back_w,card_h,gap_front_back,
                 photo_x,photo_y,photo_w,photo_h,nama_x,nama_y,nama_w,nama_h,nama_fontsz,
                 nik_x,nik_y,nik_w,nik_h,nik_fontsz,barcode_w,barcode_h,barcode_y,barcode_xres,barcode_x_offset,is_default,updated_at)
                VALUES (?,?,NULL,NULL,?,?,?,?, ?,?,?,?, ?,?,?,?,?, ?,?,?,?,?, ?,?,?,?,?, ?, NOW())");
            mysqli_stmt_bind_param(
                $stmt,
                'ssdddd' . 'dddd' . 'ddddi' . 'ddddi' . 'ddddd' . 'i',
                $kode, $label,
                $d['card_front_w'], $d['card_back_w'], $d['card_h'], $d['gap_front_back'],
                $d['photo_x'], $d['photo_y'], $d['photo_w'], $d['photo_h'],
                $d['nama_x'], $d['nama_y'], $d['nama_w'], $d['nama_h'], $d['nama_fontsz'],
                $d['nik_x'], $d['nik_y'], $d['nik_w'], $d['nik_h'], $d['nik_fontsz'],
                $d['barcode_w'], $d['barcode_h'], $d['barcode_y'], $d['barcode_xres'], $d['barcode_x_offset'],
                $isDefault
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            // simpan folder asset lama supaya resolver tau di mana mencarinya
            idcardRegisterLegacyFolder($kode, $legacyFolder);
        }
    }

    if (!is_dir(IDCARD_UPLOAD_DIR)) {
        @mkdir(IDCARD_UPLOAD_DIR, 0775, true);
    }
}

/** Folder assets/img/templates/xxx/ lama, dipetakan dari kode status (dipakai kalau belum upload gambar baru). */
function idcardRegisterLegacyFolder($kode, $folder)
{
    static $map = [];
    if ($folder !== null) {
        $map[strtoupper($kode)] = $folder;
        return;
    }
}
function idcardLegacyFolderFor($kode)
{
    $known = [
        'KONTRAK' => 'kontrak', 'MAGANG' => 'magang', 'TETAP' => 'tetap', 'YAYASAN' => 'yayasan',
        '_DEFAULT_' => 'kontrak',
    ];
    $key = strtoupper(trim((string)$kode));
    return $known[$key] ?? 'kontrak';
}

/** Ambil daftar semua baris template (dipakai di halaman Master > Layout Kartu). */
function idcardGetAllTemplates(mysqli $koneksi)
{
    idcardEnsureTemplateTable($koneksi);
    $t = IDCARD_TEMPLATE_TABLE;
    $res = mysqli_query($koneksi, "SELECT * FROM `$t` ORDER BY (kode_status='_DEFAULT_') DESC, label ASC");
    $rows = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    }
    return $rows;
}

function idcardGetTemplateByKode(mysqli $koneksi, $kodeStatus)
{
    idcardEnsureTemplateTable($koneksi);
    $t = IDCARD_TEMPLATE_TABLE;
    $stmt = mysqli_prepare($koneksi, "SELECT * FROM `$t` WHERE kode_status = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $kodeStatus);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

/** Buat baris mapping baru untuk sebuah kode status (dipanggil dari UI "Tambah mapping status"). */
function idcardCreateTemplateForStatus(mysqli $koneksi, $kodeStatus, $label)
{
    idcardEnsureTemplateTable($koneksi);
    $t = IDCARD_TEMPLATE_TABLE;
    $d = idcardDefaultLayoutValues();
    $stmt = mysqli_prepare($koneksi, "INSERT IGNORE INTO `$t`
        (kode_status,label,card_front_w,card_back_w,card_h,gap_front_back,
         photo_x,photo_y,photo_w,photo_h,nama_x,nama_y,nama_w,nama_h,nama_fontsz,
         nik_x,nik_y,nik_w,nik_h,nik_fontsz,barcode_w,barcode_h,barcode_y,barcode_xres,barcode_x_offset,updated_at)
        VALUES (?,?,?,?,?,?, ?,?,?,?, ?,?,?,?,?, ?,?,?,?,?, ?,?,?,?,?, NOW())");
    mysqli_stmt_bind_param(
        $stmt,
        'ssdddd' . 'dddd' . 'ddddi' . 'ddddi' . 'ddddd',
        $kodeStatus, $label,
        $d['card_front_w'], $d['card_back_w'], $d['card_h'], $d['gap_front_back'],
        $d['photo_x'], $d['photo_y'], $d['photo_w'], $d['photo_h'],
        $d['nama_x'], $d['nama_y'], $d['nama_w'], $d['nama_h'], $d['nama_fontsz'],
        $d['nik_x'], $d['nik_y'], $d['nik_w'], $d['nik_h'], $d['nik_fontsz'],
        $d['barcode_w'], $d['barcode_h'], $d['barcode_y'], $d['barcode_xres'], $d['barcode_x_offset']
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/** Simpan perubahan posisi/ukuran (dari form editor visual). $fields = assoc numeric fields. */
function idcardSaveTemplateLayout(mysqli $koneksi, $id, array $fields)
{
    idcardEnsureTemplateTable($koneksi);
    $t = IDCARD_TEMPLATE_TABLE;
    $numericCols = ['card_front_w','card_back_w','card_h','gap_front_back',
        'photo_x','photo_y','photo_w','photo_h',
        'nama_x','nama_y','nama_w','nama_h','nama_fontsz',
        'nik_x','nik_y','nik_w','nik_h','nik_fontsz',
        'barcode_w','barcode_h','barcode_y','barcode_xres','barcode_x_offset'];
    $set = [];
    $params = [];
    $types = '';
    foreach ($numericCols as $c) {
        if (isset($fields[$c]) && $fields[$c] !== '') {
            $set[] = "`$c` = ?";
            $params[] = (float) $fields[$c];
            $types .= 'd';
        }
    }
    if (isset($fields['label'])) {
        $set[] = "`label` = ?";
        $params[] = $fields['label'];
        $types .= 's';
    }
    if (empty($set)) return false;
    $set[] = "`updated_at` = NOW()";
    $params[] = (int) $id;
    $types .= 'i';
    $sql = "UPDATE `$t` SET " . implode(', ', $set) . " WHERE id = ?";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/** Upload / ganti gambar depan atau belakang. $side = 'front_image' | 'back_image'. */
function idcardUploadTemplateImage(mysqli $koneksi, $id, $kodeStatus, $side, array $file)
{
    if (!in_array($side, ['front_image', 'back_image'], true)) return [false, 'Sisi tidak valid.'];
    idcardEnsureTemplateTable($koneksi);
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) return [false, 'Upload gagal (kode error: ' . ($file['error'] ?? '?') . ').'];

    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) return [false, 'Format gambar harus PNG/JPG/WEBP.'];

    if (!is_dir(IDCARD_UPLOAD_DIR)) @mkdir(IDCARD_UPLOAD_DIR, 0775, true);

    $safeKode = preg_replace('/[^A-Za-z0-9_-]/', '_', $kodeStatus);
    $sideKey = $side === 'front_image' ? 'front' : 'back';
    $filename = $safeKode . '_' . $sideKey . '_' . time() . '.' . $allowed[$mime];
    $dest = IDCARD_UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return [false, 'Gagal menyimpan file ke server.'];
    }

    $t = IDCARD_TEMPLATE_TABLE;
    $relPath = IDCARD_UPLOAD_URLBASE . $filename;
    $stmt = mysqli_prepare($koneksi, "UPDATE `$t` SET `$side` = ?, updated_at = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $relPath, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return [$ok, $ok ? 'Gambar berhasil diperbarui.' : 'Gagal menyimpan ke database.'];
}

/** Hapus gambar custom (kembali ke default bawaan aplikasi). */
function idcardDeleteTemplateImage(mysqli $koneksi, $id, $side)
{
    if (!in_array($side, ['front_image', 'back_image'], true)) return false;
    idcardEnsureTemplateTable($koneksi);
    $t = IDCARD_TEMPLATE_TABLE;
    $stmt = mysqli_prepare($koneksi, "UPDATE `$t` SET `$side` = NULL, updated_at = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/**
 * Ambil layout SIAP PAKAI (buat generate.php / generate_batch.php / preview editor):
 * gabungan default + baris DB (kalau ada) + path absolut gambar depan/belakang yang benar-benar ada.
 */
function idcardResolveLayout(mysqli $koneksi, $kodeStatus)
{
    idcardEnsureTemplateTable($koneksi);
    $key = strtoupper(trim((string) $kodeStatus));
    $row = idcardGetTemplateByKode($koneksi, $key);
    if (!$row) {
        $row = idcardGetTemplateByKode($koneksi, '_DEFAULT_');
    }
    $d = idcardDefaultLayoutValues();
    $layout = $d;
    if ($row) {
        foreach ($d as $k => $v) {
            if (isset($row[$k]) && $row[$k] !== null && $row[$k] !== '') {
                $layout[$k] = (float) $row[$k];
            }
        }
        $layout['nama_fontsz'] = (int) ($row['nama_fontsz'] ?? $d['nama_fontsz']);
        $layout['nik_fontsz']  = (int) ($row['nik_fontsz'] ?? $d['nik_fontsz']);
    }

    $baseDir = realpath(__DIR__ . '/..') . '/';
    $legacyFolder = idcardLegacyFolderFor($key);

    foreach (['front_image' => 'front', 'back_image' => 'back'] as $col => $side) {
        $abs = null;
        if ($row && !empty($row[$col])) {
            $candidate = $baseDir . $row[$col];
            if (is_file($candidate)) $abs = $candidate;
        }
        if ($abs === null) {
            $legacyPath = $baseDir . 'assets/img/templates/' . $legacyFolder . '/' . $side . '.png';
            if (is_file($legacyPath)) $abs = $legacyPath;
        }
        if ($abs === null) {
            // fallback terakhir: template kontrak bawaan supaya tidak fatal error
            $abs = $baseDir . 'assets/img/templates/kontrak/' . $side . '.png';
        }
        $layout[$side . '_image_abs'] = $abs;
    }

    $layout['id'] = $row['id'] ?? null;
    $layout['kode_status'] = $key;
    $layout['label'] = $row['label'] ?? $key;
    $layout['front_image_rel'] = ($row['front_image'] ?? null);
    $layout['back_image_rel'] = ($row['back_image'] ?? null);

    return $layout;
}