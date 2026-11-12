<?php
/**
 * karyawan/delete.php
 * Hapus 1 atau banyak karyawan sekaligus (bulk delete).
 * Dipanggil via POST dari karyawan/index.php dengan field ids[].
 */
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$ids = [];
if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = $_POST['ids'];
}
$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

if (empty($ids)) {
    header('Location: index.php?error=' . urlencode('Tidak ada data yang dipilih untuk dihapus.'));
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));
$stmt = mysqli_prepare($koneksi, "DELETE FROM tbkaryawan WHERE ID IN ($placeholders)");
mysqli_stmt_bind_param($stmt, $types, ...$ids);
$ok = mysqli_stmt_execute($stmt);
$affected = mysqli_stmt_affected_rows($stmt);
$err = mysqli_error($koneksi);
mysqli_stmt_close($stmt);

if ($ok) {
    header('Location: index.php?msg=' . urlencode($affected . ' data karyawan berhasil dihapus.'));
} else {
    header('Location: index.php?error=' . urlencode('Gagal menghapus: ' . $err));
}
exit;
