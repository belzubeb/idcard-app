<?php
/**
 * cards/generate_batch.php
 * Generate PDF berisi banyak kartu sekaligus (depan+belakang tiap karyawan),
 * disusun otomatis dalam grid di halaman A4. Kalau kartu gak muat, otomatis
 * lanjut ke halaman berikutnya. Template per kartu otomatis ikut status
 * masing-masing karyawan (lihat Master Data > Layout Kartu).
 *
 * Cara pakai: kirim POST dari karyawan/index.php dengan field ids[] = ID karyawan,
 * atau bisa juga GET dengan format: generate_batch.php?ids=1,2,3,4
 * Parameter opsional: name=idcard (default) | lengkap
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tcpdf_autoload.php';
require_once __DIR__ . '/../includes/template_store.php';
require_once __DIR__ . '/../includes/card_helper.php';

// ==== Ambil daftar ID dari POST atau GET ====
$ids = [];
if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = $_POST['ids'];
} elseif (!empty($_GET['ids'])) {
    $ids = explode(',', $_GET['ids']);
}

// filter cuma angka valid
$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

if (empty($ids)) {
    http_response_code(400);
    die('Tidak ada data karyawan yang dipilih untuk dicetak.');
}

$nameModeRaw = $_POST['name'] ?? $_GET['name'] ?? 'idcard';
$nameMode = $nameModeRaw === 'lengkap' ? 'lengkap' : 'idcard';

// ==== Ambil data semua karyawan terpilih ====
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));

$sql = "SELECT ID, NIK, NamaLengkap, NamaIDCard, Foto, KodeStatus FROM tbkaryawan WHERE ID IN ($placeholders) ORDER BY NamaIDCard ASC";
$stmt = mysqli_prepare($koneksi, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$ids);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$karyawanList = [];
while ($row = mysqli_fetch_assoc($result)) {
    $karyawanList[] = $row;
}
mysqli_stmt_close($stmt);

if (empty($karyawanList)) {
    http_response_code(404);
    die('Data karyawan tidak ditemukan.');
}

// ==== Pre-resolve layout per status supaya tidak query berulang ====
$layoutCache = [];
function getCachedLayout(mysqli $koneksi, array &$cache, $kodeStatus)
{
    $key = strtoupper(trim((string) $kodeStatus));
    if (!isset($cache[$key])) {
        $cache[$key] = idcardResolveLayout($koneksi, $key);
    }
    return $cache[$key];
}

// ==== Setup dokumen PDF ====
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetTitle('Cetak Batch ID Card');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(0, 0, 0, true);
$pdf->SetAutoPageBreak(false, 0);

// ==== Hitung grid: berapa kolom & baris muat di 1 halaman A4 ====
// Ukuran kartu dianggap seragam (dari layout default) supaya grid tetap rapi
// walau tiap status punya template gambar berbeda.
$baseLayout = getCachedLayout($koneksi, $layoutCache, '_DEFAULT_');

$pageW = 210; // A4 mm
$pageH = 297;
$marginPage = 10;

$cardFrontW = $baseLayout['card_front_w'];
$cardBackW  = $baseLayout['card_back_w'];
$cardH      = $baseLayout['card_h'];
$gapFrontBack = $baseLayout['gap_front_back'];
$pairW = $cardFrontW + $gapFrontBack + $cardBackW; // lebar 1 pasang depan-belakang
$gapBetweenPairs = 8; // jarak antar pasang kartu (mm)
$gapBetweenRows  = 8;

$usableW = $pageW - (2 * $marginPage);
$usableH = $pageH - (2 * $marginPage);

$cols = max(1, floor(($usableW + $gapBetweenPairs) / ($pairW + $gapBetweenPairs)));
$rows = max(1, floor(($usableH + $gapBetweenRows) / ($cardH + $gapBetweenRows)));
$perPage = $cols * $rows;

// ==== Gambar semua kartu ====
$index = 0;
foreach ($karyawanList as $k) {
    $posInPage = $index % $perPage;
    if ($posInPage === 0) {
        $pdf->AddPage();
    }

    $col = $posInPage % $cols;
    $row = intdiv($posInPage, $cols);

    $x = $marginPage + ($col * ($pairW + $gapBetweenPairs));
    $y = $marginPage + ($row * ($cardH + $gapBetweenRows));

    $layout = getCachedLayout($koneksi, $layoutCache, $k['KodeStatus']);

    drawCard($pdf, $layout, [
        'nik'       => $k['NIK'],
        'nama'      => resolvePrintName($k, $nameMode),
        'foto_blob' => $k['Foto'],
    ], $x, $y);

    $index++;
}

// ==== Output ====
$filename = 'IDCard_Batch_' . date('Ymd_His') . '.pdf';
$pdf->Output($filename, 'I');
