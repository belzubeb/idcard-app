<?php
$activeMenu = 'karyawan';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/master_helper.php';

$id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : null;
$pageTitle = $id ? 'Edit Karyawan' : 'Tambah Karyawan';

$karyawan = [
    'NIK' => '', 'NamaLengkap' => '', 'NamaIDCard' => '', 'JK' => 'L',
    'KodeDepartemen' => '', 'KodeJabatan' => '', 'KodeStatus' => '', 'KodeLPK' => '',
    'TglMasuk' => date('Y-m-d'), 'Aktif' => 'Y',
];
if ($id) {
    $stmt = mysqli_prepare($koneksi, 'SELECT * FROM tbkaryawan WHERE ID = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if (!$row) {
        header('Location: index.php?error=' . urlencode('Karyawan tidak ditemukan.'));
        exit;
    }
    $karyawan = array_merge($karyawan, $row);
    if ($karyawan['TglMasuk']) {
        $karyawan['TglMasuk'] = date('Y-m-d', strtotime($karyawan['TglMasuk']));
    }
}

// ==== Dropdown master ====
$masterList = getMasterTableList();
function getDropdownOpts(mysqli $koneksi, $meta)
{
    if ($meta === null) return [];
    $sql = "SELECT `{$meta['pk']}` AS kode, `{$meta['name_col']}` AS nama FROM `{$meta['table']}` ORDER BY `{$meta['name_col']}` ASC";
    $res = @mysqli_query($koneksi, $sql);
    $opts = [];
    if ($res) while ($r = mysqli_fetch_assoc($res)) $opts[] = $r;
    return $opts;
}
$deptOpts = getDropdownOpts($koneksi, getMasterMeta($koneksi, $masterList['departemen']['table']));
$jabOpts = getDropdownOpts($koneksi, getMasterMeta($koneksi, $masterList['jabatan']['table']));
$statusOpts = getDropdownOpts($koneksi, getMasterMeta($koneksi, $masterList['status']['table']));
$lpkOpts = getDropdownOpts($koneksi, getMasterMeta($koneksi, $masterList['lpk']['table']));

require_once __DIR__ . '/../includes/header.php';
?>
<a href="index.php" class="page-back"><?= icon('arrow-left', 14) ?> Kembali ke Data Karyawan</a>
<div class="page-header">
    <h1 class="page-title"><?= $pageTitle ?></h1>
</div>

<?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-error"><?= icon('alert', 16) ?> <?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div class="card-box">
    <form method="post" action="save.php" enctype="multipart/form-data" class="form-grid">
        <?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

        <div class="form-col">
            <div class="form-field">
                <label>NIK <span class="req">*</span></label>
                <input type="text" name="NIK" required value="<?= htmlspecialchars($karyawan['NIK']) ?>" placeholder="mis. 22660004">
            </div>

            <div class="form-field">
                <label>Nama Lengkap <span class="req">*</span> <span class="hint">(dipakai kalau cetak dipilih "Nama Panjang")</span></label>
                <input type="text" id="namaLengkap" name="NamaLengkap" required value="<?= htmlspecialchars($karyawan['NamaLengkap']) ?>" placeholder="mis. Akhdan Najib Ramadhan">
            </div>

            <div class="form-field">
                <label>Nama di ID Card <span class="req">*</span> <span class="hint">(nama pendek, default dipakai saat cetak)</span></label>
                <div class="input-with-btn">
                    <input type="text" id="namaIdCard" name="NamaIDCard" required value="<?= htmlspecialchars($karyawan['NamaIDCard']) ?>" placeholder="mis. AKHDAN NAJIB">
                    <button type="button" class="btn secondary small" onclick="document.getElementById('namaIdCard').value = document.getElementById('namaLengkap').value;">= Nama Lengkap</button>
                </div>
            </div>

            <div class="form-field">
                <label>Jenis Kelamin</label>
                <select name="JK">
                    <option value="L" <?= $karyawan['JK'] === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                    <option value="P" <?= $karyawan['JK'] === 'P' ? 'selected' : '' ?>>Perempuan</option>
                </select>
            </div>

            <div class="form-field-row">
                <div class="form-field">
                    <label>Departemen</label>
                    <select name="KodeDepartemen">
                        <option value="">- Pilih -</option>
                        <?php foreach ($deptOpts as $o): ?>
                            <option value="<?= htmlspecialchars($o['kode']) ?>" <?= $karyawan['KodeDepartemen'] === $o['kode'] ? 'selected' : '' ?>><?= htmlspecialchars($o['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label>Jabatan</label>
                    <select name="KodeJabatan">
                        <option value="">- Pilih -</option>
                        <?php foreach ($jabOpts as $o): ?>
                            <option value="<?= htmlspecialchars($o['kode']) ?>" <?= $karyawan['KodeJabatan'] === $o['kode'] ? 'selected' : '' ?>><?= htmlspecialchars($o['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-field-row">
                <div class="form-field">
                    <label>Status <span class="hint">(menentukan template kartu)</span></label>
                    <select name="KodeStatus">
                        <option value="">- Pilih -</option>
                        <?php foreach ($statusOpts as $o): ?>
                            <option value="<?= htmlspecialchars($o['kode']) ?>" <?= $karyawan['KodeStatus'] === $o['kode'] ? 'selected' : '' ?>><?= htmlspecialchars($o['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label>LPK</label>
                    <select name="KodeLPK">
                        <option value="">- Pilih -</option>
                        <?php foreach ($lpkOpts as $o): ?>
                            <option value="<?= htmlspecialchars($o['kode']) ?>" <?= $karyawan['KodeLPK'] === $o['kode'] ? 'selected' : '' ?>><?= htmlspecialchars($o['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-field-row">
                <div class="form-field">
                    <label>Tanggal Masuk</label>
                    <input type="date" name="TglMasuk" value="<?= htmlspecialchars($karyawan['TglMasuk']) ?>">
                </div>
                <div class="form-field">
                    <label>Status Aktif</label>
                    <select name="Aktif">
                        <option value="Y" <?= $karyawan['Aktif'] === 'Y' ? 'selected' : '' ?>>Aktif</option>
                        <option value="N" <?= $karyawan['Aktif'] === 'N' ? 'selected' : '' ?>>Non-aktif</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-col form-col-photo">
            <label>Foto</label>
            <div class="photo-preview" id="photoPreview">
                <?php if ($id): ?>
                    <img src="../includes/foto.php?id=<?= $id ?>&t=<?= time() ?>" alt="Foto">
                <?php else: ?>
                    <img src="../assets/img/no-photo.png" alt="Foto">
                <?php endif; ?>
            </div>
            <input type="file" name="Foto" id="fotoInput" accept="image/*" onchange="previewFoto(this)">
            <p class="hint">Format JPG/PNG. Pas foto formal (latar netral) disarankan agar hasil ID card rapi.</p>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn success"><?= icon('save', 16) ?> Simpan</button>
            <a href="index.php" class="btn secondary">Batal</a>
        </div>
    </form>
</div>

<script>
function previewFoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('photoPreview').innerHTML = '<img src="' + e.target.result + '" alt="Foto">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
