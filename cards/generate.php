<?php
/**
 * cards/generate.php
 * Generate PDF ID Card untuk 1 karyawan. Template & posisi otomatis dipilih
 * berdasarkan KodeStatus karyawan (diatur di Master Data > Layout Kartu).
 *
 * Cara pakai:
 *   cards/generate.php?id=123
 *   cards/generate.php?id=123&download=1          (paksa download, bukan preview)
 *   cards/generate.php?id=123&name=lengkap         (pakai Nama Lengkap, bukan Nama ID Card)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tcpdf_autoload.php';
require_once __DIR__ . '/../includes/template_store.php';
require_once __DIR__ . '/../includes/card_helper.php';

// ==== Validasi input ====
$id = isset($_GET['id']) ? $_GET['id'] : null;
if ($id === null || !ctype_digit((string) $id)) {
    http_response_code(400);
    die('Parameter id tidak valid.');
}
$id = (int) $id;
$nameMode = ($_GET['name'] ?? 'idcard') === 'lengkap' ? 'lengkap' : 'idcard';

// ==== Ambil data karyawan ====
$stmt = mysqli_prepare($koneksi, 'SELECT NIK, NamaLengkap, NamaIDCard, Foto, KodeStatus FROM tbkaryawan WHERE ID = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$karyawan = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$karyawan) {
    http_response_code(404);
    die('Data karyawan dengan ID tersebut tidak ditemukan.');
}

$layout = idcardResolveLayout($koneksi, $karyawan['KodeStatus']);
$namaCetak = resolvePrintName($karyawan, $nameMode);

// ==== Setup dokumen PDF ====
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor(PDF_AUTHOR);
$pdf->SetTitle('ID Card - ' . $namaCetak);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(0, 0, 0, true);
$pdf->SetAutoPageBreak(false, 0);
$pdf->AddPage();

drawCard($pdf, $layout, [
    'nik'       => $karyawan['NIK'],
    'nama'      => $namaCetak,
    'foto_blob' => $karyawan['Foto'],
], 15, 15);

// ==== Output ====
$filename = 'IDCard_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $namaCetak) . '.pdf';
$dest = (isset($_GET['download']) && $_GET['download'] == '1') ? 'D' : 'I';
$pdf->Output($filename, $dest);
