<?php
/**
 * karyawan/template_xlsx.php
 * Generate & download file .xlsx kosong (+ 1 baris contoh) buat diisi lalu
 * di-upload lewat karyawan/import.php. Sheet kedua berisi daftar kode master
 * (Departemen/Jabatan/Status/LPK) yang sedang ada di database, biar user tau
 * kode apa yang valid dipakai.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/xlsx_lite.php';
require_once __DIR__ . '/../includes/master_helper.php';

$headers = ['NIK', 'NamaLengkap', 'NamaIDCard', 'JK', 'KodeDepartemen', 'KodeJabatan', 'KodeStatus', 'KodeLPK', 'TglMasuk', 'Aktif'];
$exampleRows = [
    ['22660004', 'Akhdan Najib Ramadhan', 'AKHDAN NAJIB', 'L', 'PRD', 'OPR', 'KONTRAK', '', date('Y-m-d'), 'Y'],
];

// ---- Sheet referensi kode dari master data yang ada sekarang ----
$refRows = [];
$masterList = getMasterTableList();
foreach ($masterList as $key => $info) {
    $meta = getMasterMeta($koneksi, $info['table']);
    if ($meta === null) continue;
    $sql = "SELECT `{$meta['pk']}` AS kode, `{$meta['name_col']}` AS nama FROM `{$meta['table']}` ORDER BY `{$meta['name_col']}` ASC";
    $res = @mysqli_query($koneksi, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $refRows[] = [$info['label'], $r['kode'], $r['nama']];
        }
    }
}
if (empty($refRows)) {
    $refRows[] = ['(belum ada data master)', '-', '-'];
}

$tmpFile = tempnam(sys_get_temp_dir(), 'idcard_tpl_') . '.xlsx';
xlsxLiteWrite($tmpFile, $headers, $exampleRows, [
    ['title' => 'Referensi Kode', 'headers' => ['Jenis', 'Kode', 'Nama'], 'rows' => $refRows],
]);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Template_Import_Karyawan.xlsx"');
header('Content-Length: ' . filesize($tmpFile));
readfile($tmpFile);
@unlink($tmpFile);
exit;
