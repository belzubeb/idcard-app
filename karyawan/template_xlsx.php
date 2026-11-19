<?php
/**
 * karyawan/template_xlsx.php
 * Generate & download template import karyawan (.xlsx).
 *
 * Kolom yang dipakai (cukup 5):
 *   NIK, NamaLengkap, NamaIDCard, Status, Aktif
 *
 * - Status  : dropdown MAGANG / KONTRAK / TETAP (diterjemahkan ke kode DB saat import)
 * - Aktif   : dropdown Y / N
 * - NamaIDCard otomatis diubah ke HURUF KAPITAL oleh sistem saat import.
 * - JK, TglMasuk, Departemen, Jabatan & LPK tidak lagi ada di template.
 *
 * TIDAK ada baris contoh: template sengaja dikosongkan supaya upload apa adanya
 * tidak pernah menimpa/menambah data karyawan. Isi mulai baris 2.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/xlsx_lite.php';

$headers = ['NIK', 'NamaLengkap', 'NamaIDCard', 'Status', 'Aktif'];
$rows = []; // sengaja kosong — lihat catatan di atas

$tmpFile = sys_get_temp_dir() . '/idcard_tpl_' . bin2hex(random_bytes(8)) . '.xlsx';
xlsxLiteWrite($tmpFile, $headers, $rows, [], [
    // kolom ke-4 (D) = Status
    ['col' => 3, 'options' => ['MAGANG', 'KONTRAK', 'TETAP'], 'lastRow' => 1000],
    // kolom ke-5 (E) = Aktif
    ['col' => 4, 'options' => ['Y', 'N'], 'lastRow' => 1000],
]);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Template_Import_Karyawan.xlsx"');
header('Content-Length: ' . filesize($tmpFile));
readfile($tmpFile);
@unlink($tmpFile);
exit;
