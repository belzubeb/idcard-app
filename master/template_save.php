<?php
/**
 * master/template_save.php
 * Handler tunggal buat semua aksi dari master/template_edit.php:
 *  - save_layout    : simpan posisi/ukuran (angka) + label
 *  - upload_front   : upload/ganti gambar kartu depan
 *  - upload_back    : upload/ganti gambar kartu belakang
 *  - delete_front   : hapus gambar custom depan (balik ke default bawaan)
 *  - delete_back    : hapus gambar custom belakang (balik ke default bawaan)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/template_store.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: template.php');
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$kodeStatus = trim($_POST['kode_status'] ?? '');
$action = $_POST['action'] ?? '';

if ($id <= 0) {
    header('Location: template.php?error=' . urlencode('ID layout tidak valid.'));
    exit;
}

switch ($action) {
    case 'save_layout':
        $fields = $_POST;
        $ok = idcardSaveTemplateLayout($koneksi, $id, $fields);
        $msg = $ok ? 'Posisi layout berhasil disimpan.' : 'Gagal menyimpan posisi layout.';
        header('Location: template_edit.php?id=' . $id . '&' . ($ok ? 'msg' : 'error') . '=' . urlencode($msg));
        break;

    case 'upload_front':
    case 'upload_back':
        $side = $action === 'upload_front' ? 'front_image' : 'back_image';
        $fileKey = $action === 'upload_front' ? 'front_image_file' : 'back_image_file';
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
            header('Location: template_edit.php?id=' . $id . '&error=' . urlencode('Tidak ada file yang diupload / upload gagal.'));
            break;
        }
        [$ok, $msg] = idcardUploadTemplateImage($koneksi, $id, $kodeStatus, $side, $_FILES[$fileKey]);
        header('Location: template_edit.php?id=' . $id . '&' . ($ok ? 'msg' : 'error') . '=' . urlencode($msg));
        break;

    case 'delete_front':
        $ok = idcardDeleteTemplateImage($koneksi, $id, 'front_image');
        header('Location: template_edit.php?id=' . $id . '&msg=' . urlencode($ok ? 'Gambar depan dikembalikan ke default.' : 'Gagal menghapus gambar.'));
        break;

    case 'delete_back':
        $ok = idcardDeleteTemplateImage($koneksi, $id, 'back_image');
        header('Location: template_edit.php?id=' . $id . '&msg=' . urlencode($ok ? 'Gambar belakang dikembalikan ke default.' : 'Gagal menghapus gambar.'));
        break;

    default:
        header('Location: template_edit.php?id=' . $id . '&error=' . urlencode('Aksi tidak dikenali.'));
}
exit;
