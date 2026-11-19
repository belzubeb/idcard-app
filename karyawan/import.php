<?php
/**
 * karyawan/import.php
 * Import data karyawan secara massal dari file .xlsx.
 * Kolom template (header baris 1, tidak case-sensitive):
 *   NIK, NamaLengkap, NamaIDCard, Status, Aktif
 * - Status bebas pakai nama (MAGANG/KONTRAK/TETAP) atau kode lama (01/02/03);
 *   keduanya diterjemahkan ke kode DB oleh tabel tbstatus.
 * - NamaIDCard otomatis di-uppercase.
 * - JK, TglMasuk, Departemen, Jabatan & LPK tidak disentuh saat UPDATE; untuk data baru
 *   memakai nilai default sistem (Dept/Jabatan dikosongkan karena master sudah nonaktif).
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

                // Validasi header: kalau header dihapus/diubah, jangan sampai baris data
                // ikut terbaca sebagai header (bisa menyimpan data ke kolom yang salah).
                $headerKeys = [];
                foreach (array_keys(reset($rows)) as $hk) {
                    $headerKeys[] = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $hk));
                }
                $headerMissing = array_diff(['nik', 'namalengkap', 'status'], $headerKeys);
                $headerOk = empty($headerMissing);
                if (!$headerOk) {
                    $importErrors[] = 'Header Excel tidak sesuai — kolom wajib: NIK, NamaLengkap, Status '
                        . '(tidak ditemukan: ' . implode(', ', $headerMissing) . '). '
                        . 'Unduh ulang template dan jangan mengubah baris pertama.';
                }

                // Semua perubahan db atau tidak sama sekali (rollback kalau ada query gagal).
                mysqli_begin_transaction($koneksi);
                $created = 0; $updated = 0; $skipped = 0; $dbFailed = false;
                foreach ($rows as $i => $row) {
                    if (!$headerOk) break;
                    $lineNo = $i + 2; // +2 karena baris 1 = header
                    $nik = trim(findCol($row, ['NIK']));
                    $namaLengkap = trim(findCol($row, ['NamaLengkap', 'Nama Lengkap']));
                    // Nama di kartu selalu huruf kapital
                    $namaIdCard = strtoupper(trim(findCol($row, ['NamaIDCard', 'Nama IDCard', 'Nama ID Card'])));

                    // Kolom yang sudah tidak ada di template -> nilai default sistem.
                    // Dept/Jabatan sengaja dikosongkan (bukan kode palsu seperti UMUM/STAF
                    // yang tidak ada di tabel master) karena keduanya tak lagi dipakai.
                    $jk = 'L';            // JK dihapus dari template
                    $kodeDept = '';       // Departemen dihapus dari template
                    $kodeJab = '';        // Jabatan dihapus dari template
                    $kodeLpk = '';        // LPK dihapus dari template
                    $tglMasuk = date('Y-m-d'); // TglMasuk dihapus dari template

                    $kodeStatus = trim(findCol($row, ['Status', 'KodeStatus', 'Kode Status']));
                    if (strtoupper($kodeStatus) === 'TETEP') $kodeStatus = 'TETAP'; // alias typo umum
                    // Translate nama status (TETAP/KONTRAK/MAGANG) ATAU kode (01/02/03) -> kode DB.
                    // Kolom nama di tbstatus adalah `Status` (bukan NamaStatus).
                    $statusFound = false;
                    $stmtStatus = @mysqli_prepare($koneksi, "SELECT KodeStatus FROM tbstatus WHERE UPPER(Status) = UPPER(?) OR KodeStatus = ? LIMIT 1");
                    if ($stmtStatus && $kodeStatus !== '') {
                        mysqli_stmt_bind_param($stmtStatus, 'ss', $kodeStatus, $kodeStatus);
                        mysqli_stmt_execute($stmtStatus);
                        $resStatus = mysqli_stmt_get_result($stmtStatus);
                        if ($rowStatus = mysqli_fetch_assoc($resStatus)) {
                            $kodeStatus = $rowStatus['KodeStatus'];
                            $statusFound = true;
                        }
                        mysqli_stmt_close($stmtStatus);
                    }

                    $aktif = strtoupper(trim(findCol($row, ['Aktif']))) ?: 'Y';
                    $aktif = $aktif === 'N' ? 'N' : 'Y';

                    if ($nik === '' || $namaLengkap === '') {
                        $skipped++;
                        $importErrors[] = "Baris $lineNo dilewati: NIK atau Nama Lengkap kosong.";
                        continue;
                    }
                    // Status menentukan layout kartu — jangan pernah simpan nilai asing/kosong.
                    if (!$statusFound) {
                        $skipped++;
                        $importErrors[] = "Baris $lineNo dilewati: Status '$kodeStatus' tidak dikenal. Pilih MAGANG / KONTRAK / TETAP.";
                        continue;
                    }
                    if ($namaIdCard === '') $namaIdCard = strtoupper($namaLengkap);

                    $tglMasukVal = $tglMasuk !== '' ? date('Y-m-d', strtotime($tglMasuk) ?: time()) : date('Y-m-d');

                    // cek NIK sudah ada?
                    $stmt = mysqli_prepare($koneksi, 'SELECT ID FROM tbkaryawan WHERE NIK = ? LIMIT 1');
                    mysqli_stmt_bind_param($stmt, 's', $nik);
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    $existing = mysqli_fetch_assoc($res);
                    mysqli_stmt_close($stmt);

                    if ($existing) {
                        // UPDATE HANYA menyentuh kolom yang ada di template.
                        // JK, TglMasuk, Departemen, Jabatan, LPK & Foto sengaja dibiarkan apa adanya,
                        // supaya import tidak merusak data karyawan lama.
                        $stmt = mysqli_prepare($koneksi, "UPDATE tbkaryawan SET NamaLengkap=?, NamaIDCard=?, KodeStatus=?, Aktif=?, LastDt=NOW() WHERE ID=?");
                        mysqli_stmt_bind_param($stmt, 'ssssi', $namaLengkap, $namaIdCard, $kodeStatus, $aktif, $existing['ID']);
                        if (mysqli_stmt_execute($stmt)) {
                            $updated++;
                        } else {
                            $dbFailed = true;
                            $importErrors[] = "Baris $lineNo gagal update: " . mysqli_error($koneksi);
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        $emptyFoto = '';
                        $stmt = mysqli_prepare($koneksi, "INSERT INTO tbkaryawan (NIK, NamaLengkap, NamaIDCard, JK, Foto, KodeDepartemen, KodeJabatan, KodeStatus, KodeLPK, TglMasuk, TglEntry, Aktif, Print) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),?,'N')");
                        mysqli_stmt_bind_param($stmt, 'sssssssssss', $nik, $namaLengkap, $namaIdCard, $jk, $emptyFoto, $kodeDept, $kodeJab, $kodeStatus, $kodeLpk, $tglMasukVal, $aktif);
                        if (mysqli_stmt_execute($stmt)) {
                            $created++;
                        } else {
                            $dbFailed = true;
                            $importErrors[] = "Baris $lineNo gagal tambah: " . mysqli_error($koneksi);
                        }
                        mysqli_stmt_close($stmt);
                    }
                }

                // Commit kalau semua query sukses; rollback total kalau ada yang gagal.
                if ($dbFailed) {
                    mysqli_rollback($koneksi);
                    $importErrors[] = 'Semua perubahan dibatalkan (rollback). Database tidak berubah.';
                } else {
                    mysqli_commit($koneksi);
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
    <p class="hint">
        Template sengaja <strong>kosong</strong> — isi mulai baris 2, header jangan diubah. Upload template tanpa diisi tidak akan mengubah data apa pun.<br>
        Kolom: <strong>NIK, NamaLengkap, NamaIDCard, Status, Aktif</strong>. Status wajib dipilih lewat dropdown
        <strong>MAGANG / KONTRAK / TETAP</strong> (baris dengan status kosong/tidak dikenal dilewati).
        Nama kartu otomatis jadi HURUF KAPITAL.
    </p>
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
