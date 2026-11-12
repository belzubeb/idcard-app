<?php
/**
 * karyawan/import_photos.php
 * Upload foto karyawan secara massal (banyak file sekaligus).
 * Setiap file HARUS dinamai sesuai NIK karyawan, contoh: 1234567890.jpg
 * Sistem mencocokkan nama file (tanpa ekstensi) = NIK di database, lalu
 * menyimpan isi file itu ke kolom tbkaryawan.Foto (BLOB) milik NIK tsb.
 */
$activeMenu = 'karyawan';
$pageTitle = 'Upload Foto Massal';
require_once __DIR__ . '/../config/database.php';

$summary = null;
$notFound = [];
$invalidFiles = [];
$errorRows = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fotos'])) {
    $files = $_FILES['fotos'];
    $count = is_array($files['name']) ? count($files['name']) : 0;

    if ($count === 0) {
        $errorRows[] = 'Tidak ada file yang diupload.';
    } else {
        $updated = 0;
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        for ($i = 0; $i < $count; $i++) {
            $origName = $files['name'][$i];
            $tmpName  = $files['tmp_name'][$i];
            $error    = $files['error'][$i];

            if ($error !== UPLOAD_ERR_OK || $origName === '') {
                continue; // slot kosong / gagal upload, skip diam-diam
            }

            // NIK diambil dari nama file tanpa ekstensi, trim spasi
            $nik = trim(pathinfo($origName, PATHINFO_FILENAME));

            if ($nik === '') {
                $invalidFiles[] = $origName . ' (nama file kosong / gak jelas NIK-nya)';
                continue;
            }

            $mime = $finfo->file($tmpName);
            if (strpos($mime, 'image/') !== 0) {
                $invalidFiles[] = $origName . ' (bukan file gambar, terdeteksi: ' . $mime . ')';
                continue;
            }

            // cari karyawan dengan NIK ini
            $stmt = mysqli_prepare($koneksi, 'SELECT ID FROM tbkaryawan WHERE NIK = ? LIMIT 1');
            mysqli_stmt_bind_param($stmt, 's', $nik);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if (!$row) {
                $notFound[] = $origName . ' (NIK "' . $nik . '" tidak ditemukan di data karyawan)';
                continue;
            }

            $fotoBlob = file_get_contents($tmpName);
            $stmt = mysqli_prepare($koneksi, 'UPDATE tbkaryawan SET Foto = ?, LastDt = NOW() WHERE ID = ?');
            mysqli_stmt_bind_param($stmt, 'si', $fotoBlob, $row['ID']);
            if (mysqli_stmt_execute($stmt)) {
                $updated++;
            } else {
                $errorRows[] = $origName . ' gagal disimpan: ' . mysqli_error($koneksi);
            }
            mysqli_stmt_close($stmt);
        }

        $summary = [
            'total'     => $count,
            'updated'   => $updated,
            'not_found' => count($notFound),
            'invalid'   => count($invalidFiles),
        ];
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<a href="index.php" class="page-back"><?= icon('arrow-left', 14) ?> Kembali ke Data Karyawan</a>
<div class="page-header">
    <h1 class="page-title">Upload Foto Massal</h1>
</div>

<div class="card-box">
    <h3>Cara pakai</h3>
    <ol class="hint" style="padding-left: 18px; margin: 0;">
        <li>Siapkan semua foto karyawan (format JPG/PNG/WEBP).</li>
        <li>Ganti nama tiap file jadi <strong>NIK karyawan</strong> yang bersangkutan.<br>
            Contoh: karyawan dengan NIK <code>19951006</code> → nama file foto harus <code>19951006.jpg</code></li>
        <li>Pilih semua file sekaligus di form bawah, lalu klik Upload.</li>
        <li>Sistem otomatis mencocokkan nama file = NIK dan menyimpan foto ke karyawan yang sesuai.</li>
    </ol>
</div>

<div class="card-box">
    <h3>Upload foto</h3>
    <form method="post" enctype="multipart/form-data" class="toolbar">
        <input type="file" name="fotos[]" accept="image/*" multiple required>
        <button type="submit" class="btn success"><?= icon('upload', 16) ?> Upload &amp; Cocokkan</button>
    </form>
    <p class="hint">Bisa pilih ratusan file sekaligus (Ctrl+klik / Cmd+klik / select all di file explorer).</p>
</div>

<?php if ($summary): ?>
<div class="alert alert-success">
    <?= icon('check', 16) ?>
    <span>
        Selesai — <?= $summary['total'] ?> file diproses:
        <strong><?= $summary['updated'] ?></strong> foto berhasil dipasang,
        <strong><?= $summary['not_found'] ?></strong> NIK tidak ditemukan,
        <strong><?= $summary['invalid'] ?></strong> file tidak valid.
    </span>
</div>
<?php endif; ?>

<?php if (!empty($notFound)): ?>
<div class="alert alert-error">
    <?= icon('alert', 16) ?>
    <span>
        <strong>NIK tidak ketemu (cek penamaan file / mungkin karyawan belum diimport):</strong>
        <ul>
            <?php foreach ($notFound as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </span>
</div>
<?php endif; ?>

<?php if (!empty($invalidFiles)): ?>
<div class="alert alert-error">
    <?= icon('alert', 16) ?>
    <span>
        <strong>File tidak valid:</strong>
        <ul>
            <?php foreach ($invalidFiles as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </span>
</div>
<?php endif; ?>

<?php if (!empty($errorRows)): ?>
<div class="alert alert-error">
    <?= icon('alert', 16) ?>
    <span>
        <strong>Error lain:</strong>
        <ul>
            <?php foreach ($errorRows as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </span>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
