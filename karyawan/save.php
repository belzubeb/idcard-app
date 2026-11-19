<?php
/**
 * karyawan/save.php
 * Simpan data karyawan baru atau update yang sudah ada (dipanggil dari form.php).
 * Foto disimpan sebagai BLOB di kolom tbkaryawan.Foto (kalau tidak upload foto baru
 * saat edit, foto lama tetap dipertahankan).
 */
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

$nik = trim($_POST['NIK'] ?? '');
$namaLengkap = trim($_POST['NamaLengkap'] ?? '');
$namaIdCard = strtoupper(trim($_POST['NamaIDCard'] ?? '')); // nama kartu selalu huruf kapital
$jk = trim($_POST['JK'] ?? 'L');
// Departemen / Jabatan / LPK tidak lagi ada di form.
// Nilai ini cuma dipakai saat INSERT; saat UPDATE kolomnya sengaja tidak disentuh
// supaya data lama di database tetap utuh (tidak ikut terhapus/tertimpa '').
// Kode master sengaja dikosongkan, bukan kode palsu (UMUM/STAF) yang tidak ada di tabel master.
$kodeDept = '';
$kodeJab = '';
$kodeStatus = trim($_POST['KodeStatus'] ?? '');
$kodeLpk = '';
$tglMasuk = trim($_POST['TglMasuk'] ?? '') ?: date('Y-m-d');
$aktif = ($_POST['Aktif'] ?? 'Y') === 'N' ? 'N' : 'Y';

$errors = [];
if ($nik === '') $errors[] = 'NIK wajib diisi.';
if ($namaLengkap === '') $errors[] = 'Nama Lengkap wajib diisi.';
if ($namaIdCard === '') $namaIdCard = strtoupper($namaLengkap);

// ---- Foto upload (opsional) ----
$fotoBlob = null;
$hasNewFoto = isset($_FILES['Foto']) && $_FILES['Foto']['error'] === UPLOAD_ERR_OK && $_FILES['Foto']['size'] > 0;
if ($hasNewFoto) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($_FILES['Foto']['tmp_name']);
    if (strpos($mime, 'image/') !== 0) {
        $errors[] = 'File foto harus berupa gambar (JPG/PNG).';
    } else {
        $fotoBlob = file_get_contents($_FILES['Foto']['tmp_name']);
    }
}

if (!empty($errors)) {
    $qs = http_build_query(['error' => implode(' ', $errors)]);
    header('Location: form.php' . ($id ? "?id=$id&$qs" : "?$qs"));
    exit;
}

if ($id) {
    // ==== UPDATE ====
    if ($hasNewFoto) {
        $sql = "UPDATE tbkaryawan SET NIK=?, NamaLengkap=?, NamaIDCard=?, JK=?, Foto=?, KodeStatus=?, TglMasuk=?, Aktif=?, LastDt=NOW() WHERE ID=?";
        $stmt = mysqli_prepare($koneksi, $sql);
        mysqli_stmt_bind_param($stmt, 'ssssssssi', $nik, $namaLengkap, $namaIdCard, $jk, $fotoBlob, $kodeStatus, $tglMasuk, $aktif, $id);
    } else {
        $sql = "UPDATE tbkaryawan SET NIK=?, NamaLengkap=?, NamaIDCard=?, JK=?, KodeStatus=?, TglMasuk=?, Aktif=?, LastDt=NOW() WHERE ID=?";
        $stmt = mysqli_prepare($koneksi, $sql);
        mysqli_stmt_bind_param($stmt, 'sssssssi', $nik, $namaLengkap, $namaIdCard, $jk, $kodeStatus, $tglMasuk, $aktif, $id);
    }
    $ok = mysqli_stmt_execute($stmt);
    $err = mysqli_error($koneksi);
    mysqli_stmt_close($stmt);

    if ($ok) {
        header('Location: index.php?msg=' . urlencode('Data karyawan berhasil diupdate.'));
    } else {
        header('Location: form.php?id=' . $id . '&error=' . urlencode('Gagal update: ' . $err));
    }
    exit;
} else {
    // ==== INSERT (ID auto_increment) ====
    $fotoBlob = $fotoBlob ?? '';
    $sql = "INSERT INTO tbkaryawan (NIK, NamaLengkap, NamaIDCard, JK, Foto, KodeDepartemen, KodeJabatan, KodeStatus, KodeLPK, TglMasuk, TglEntry, Aktif, Print) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),?,'N')";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, 'sssssssssss', $nik, $namaLengkap, $namaIdCard, $jk, $fotoBlob, $kodeDept, $kodeJab, $kodeStatus, $kodeLpk, $tglMasuk, $aktif);
    $ok = mysqli_stmt_execute($stmt);
    $newId = mysqli_insert_id($koneksi);
    $err = mysqli_error($koneksi);
    mysqli_stmt_close($stmt);

    if ($ok) {
        header('Location: index.php?msg=' . urlencode('Karyawan baru berhasil ditambahkan (No. urut otomatis, ID #' . $newId . ').'));
    } else {
        header('Location: form.php?error=' . urlencode('Gagal menambahkan: ' . $err));
    }
    exit;
}
