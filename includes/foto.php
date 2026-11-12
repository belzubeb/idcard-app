<?php
/**
 * includes/foto.php
 * Serve kolom Foto (BLOB, isinya JPEG) langsung sebagai image response.
 *
 * Cara pakai di HTML:
 *   <img src="includes/foto.php?id=123">
 *
 * Parameter:
 *   id  = kolom ID di tabel Karyawan (bigint)
 */

require_once __DIR__ . '/../config/database.php';

// ==== Ambil & validasi parameter ====
$id = isset($_GET['id']) ? $_GET['id'] : null;

if ($id === null || !ctype_digit((string) $id)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Parameter id tidak valid.';
    exit;
}

$id = (int) $id;

// ==== Ambil data BLOB pakai prepared statement (aman dari SQL Injection) ====
$stmt = mysqli_prepare($koneksi, 'SELECT Foto FROM tbkaryawan WHERE ID = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

$fotoBlob = $row['Foto'] ?? null;

// ==== Kalau foto kosong, kirim gambar placeholder ====
if (empty($fotoBlob)) {
    $placeholder = __DIR__ . '/../assets/img/no-photo.png';
    if (file_exists($placeholder)) {
        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($placeholder));
        readfile($placeholder);
        exit;
    }
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Foto tidak ditemukan.';
    exit;
}

// ==== Deteksi tipe gambar otomatis dari isi binary ====
// Meski kamu bilang isinya JPEG, kita cek dulu biar aman kalau ternyata campur PNG.
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->buffer($fotoBlob);

if (strpos($mimeType, 'image/') !== 0) {
    // Data di kolom Foto bukan gambar valid
    http_response_code(415);
    header('Content-Type: text/plain');
    echo 'Data foto tidak valid (bukan gambar). Terdeteksi sebagai: ' . $mimeType;
    exit;
}

// ==== Kirim response gambar ====
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . strlen($fotoBlob));
// Cache ringan di browser, biar gak query ulang tiap refresh (opsional, bisa dihapus saat development)
header('Cache-Control: private, max-age=3600');

echo $fotoBlob;
exit;
