<?php
/**
 * karyawan/import.php
 * Import data karyawan secara massal dari file .xlsx.
 * Kolom yang dibaca (header baris 1, tidak case-sensitive):
 *   NIK, NamaLengkap, NamaIDCard, JK, KodeDepartemen, KodeJabatan, KodeStatus, KodeLPK, TglMasuk, Aktif
 * NIK dipakai sebagai kunci: kalau NIK sudah ada di database -> data diupdate,
 * kalau belum ada -> ditambahkan sebagai karyawan baru. Foto TIDAK bisa diisi
 * lewat Excel (upload foto tetap lewat form edit karyawan satu-satu).
 */
$activeMenu = 'karyawan';
$pageTitle = 'Import Data Karyawan (Excel)';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/xlsx_lite.php';

$summary = null;
$importErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xlsx_file'])) {
    $file = $_FILES['xlsx_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $importErrors[] = 'Upload file gagal (kode error: ' . $file['error'] . ').';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            $importErrors[] = 'File harus berformat .xlsx (Excel).';
        } else {
            $rows = xlsxLiteRead($file['tmp_name']);
            if ($rows === false) {
                $importErrors[] = 'Gagal membaca isi file Excel. Pastikan file tidak rusak.';
            } elseif (empty($rows)) {
                $importErrors[] = 'File Excel tidak berisi data (cek apakah baris pertama adalah header kolom).';
            } else {
                // normalisasi nama kolom (case-insensitive, trim)
                function findCol(array $row, array $candidates)
                {
                    $lower = [];
                    foreach ($row as $k => $v) $lower[strtolower(trim($k))] = $k;
                    foreach ($candidates as $c) {
                        $c = strtolower($c);
                        if (isset($lower[$c])) return $row[$lower[$c]];
                    }
                    return '';
                }

                $created = 0; $updated = 0; $skipped = 0;
                foreach ($rows as $i => $row) {
                    $lineNo = $i + 2; // +2 karena baris 1 = header
                    $nik = trim(findCol($row, ['NIK']));
                    $namaLengkap = trim(findCol($row, ['NamaLengkap', 'Nama Lengkap']));
                    $namaIdCard = trim(findCol($row, ['NamaIDCard', 'Nama IDCard', 'Nama ID Card']));
                    $jk = strtoupper(trim(findCol($row, ['JK', 'JenisKelamin']))) ?: 'L';
                    $jk = in_array($jk, ['L', 'P']) ? $jk : 'L';
                    $kodeDept = trim(findCol($row, ['KodeDepartemen', 'Kode Departemen']));
                    $kodeJab = trim(findCol($row, ['KodeJabatan', 'Kode Jabatan']));
                    $kodeStatus = trim(findCol($row, ['KodeStatus', 'Kode Status']));
                    $kodeLpk = trim(findCol($row, ['KodeLPK', 'Kode LPK']));
                    $tglMasuk = trim(findCol($row, ['TglMasuk', 'Tanggal Masuk']));
                    $aktif = strtoupper(trim(findCol($row, ['Aktif']))) ?: 'Y';
                    $aktif = $aktif === 'N' ? 'N' : 'Y';

                    if ($nik === '' || $namaLengkap === '') {
                        $skipped++;
                        $importErrors[] = "Baris $lineNo dilewati: NIK atau Nama Lengkap kosong.";
                        continue;
                    }
                    if ($namaIdCard === '') $namaIdCard = $namaLengkap;

                    $tglMasukVal = $tglMasuk !== '' ? date('Y-m-d', strtotime($tglMasuk) ?: time()) : date('Y-m-d');

                    // cek NIK sudah ada?
                    $stmt = mysqli_prepare($koneksi, 'SELECT ID FROM tbkaryawan WHERE NIK = ? LIMIT 1');
                    mysqli_stmt_bind_param($stmt, 's', $nik);
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    $existing = mysqli_fetch_assoc($res);
                    mysqli_stmt_close($stmt);

                    if ($existing) {
                        $stmt = mysqli_prepare($koneksi, "UPDATE tbkaryawan SET NamaLengkap=?, NamaIDCard=?, JK=?, KodeDepartemen=?, KodeJabatan=?, KodeStatus=?, KodeLPK=?, TglMasuk=?, Aktif=?, LastDt=NOW() WHERE ID=?");
                        mysqli_stmt_bind_param($stmt, 'sssssssssi', $namaLengkap, $namaIdCard, $jk, $kodeDept, $kodeJab, $kodeStatus, $kodeLpk, $tglMasukVal, $aktif, $existing['ID']);
                        if (mysqli_stmt_execute($stmt)) $updated++; else $importErrors[] = "Baris $lineNo gagal update: " . mysqli_error($koneksi);
                        mysqli_stmt_close($stmt);
                    } else {
                        $emptyFoto = '';
                        $stmt = mysqli_prepare($koneksi, "INSERT INTO tbkaryawan (NIK, NamaLengkap, NamaIDCard, JK, Foto, KodeDepartemen, KodeJabatan, KodeStatus, KodeLPK, TglMasuk, TglEntry, Aktif, Print) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),?,'N')");
                        mysqli_stmt_bind_param($stmt, 'sssssssssss', $nik, $namaLengkap, $namaIdCard, $jk, $emptyFoto, $kodeDept, $kodeJab, $kodeStatus, $kodeLpk, $tglMasukVal, $aktif);
                        if (mysqli_stmt_execute($stmt)) $created++; else $importErrors[] = "Baris $lineNo gagal tambah: " . mysqli_error($koneksi);
                        mysqli_stmt_close($stmt);
                    }
                }

                $summary = ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'total' => count($rows)];
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<a href="index.php" class="page-back"><?= icon('arrow-left', 14) ?> Kembali ke Data Karyawan</a>
<div class="page-header">
    <h1 class="page-title">Import Data Karyawan (Excel)</h1>
</div>

<div class="card-box">
    <h3>1. Unduh format Excel</h3>
    <p class="hint">Isi data karyawan di file ini, lalu upload kembali di bawah. Baris pertama (header) jangan diubah/dihapus.</p>
    <a href="template_xlsx.php" class="btn secondary"><?= icon('download', 16) ?> Download Template Excel (.xlsx)</a>
</div>

<div class="card-box">
    <h3>2. Upload file yang sudah diisi</h3>
    <form method="post" enctype="multipart/form-data" class="toolbar">
        <input type="file" name="xlsx_file" accept=".xlsx" required>
        <button type="submit" class="btn success"><?= icon('upload', 16) ?> Import Sekarang</button>
    </form>
    <p class="hint">
        Karyawan dicocokkan berdasarkan <strong>NIK</strong>: kalau NIK sudah ada di database, datanya akan
        di-update; kalau belum ada, akan ditambahkan sebagai karyawan baru. Foto tidak bisa diisi lewat Excel —
        upload foto lewat menu Edit di masing-masing karyawan setelah import.
    </p>
</div>

<?php if ($summary): ?>
<div class="alert alert-success">
    <?= icon('check', 16) ?>
    <span>
        Import selesai — <?= $summary['total'] ?> baris diproses:
        <strong><?= $summary['created'] ?></strong> ditambahkan,
        <strong><?= $summary['updated'] ?></strong> diupdate,
        <strong><?= $summary['skipped'] ?></strong> dilewati.
    </span>
</div>
<?php endif; ?>

<?php if (!empty($importErrors)): ?>
<div class="alert alert-error">
    <?= icon('alert', 16) ?>
    <span>
        <strong>Catatan / baris bermasalah:</strong>
        <ul>
            <?php foreach ($importErrors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </span>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
