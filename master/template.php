<?php
$activeMenu = 'master';
$pageTitle  = 'Layout Kartu';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/template_store.php';
require_once __DIR__ . '/../includes/master_helper.php';

$templates = idcardGetAllTemplates($koneksi);

// daftar status yang ada di tbstatus (kalau ada) tapi belum punya mapping layout
$masterMeta = getMasterMeta($koneksi, 'tbstatus');
$statusOptions = [];
if ($masterMeta) {
    $res = @mysqli_query($koneksi, "SELECT `{$masterMeta['pk']}` AS kode, `{$masterMeta['name_col']}` AS nama FROM `{$masterMeta['table']}` ORDER BY `{$masterMeta['name_col']}` ASC");
    if ($res) while ($r = mysqli_fetch_assoc($res)) $statusOptions[] = $r;
}
$mappedKodes = array_map(fn($t) => strtoupper($t['kode_status']), $templates);
$unmapped = array_filter($statusOptions, fn($o) => !in_array(strtoupper($o['kode']), $mappedKodes, true));

$addMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_mapping') {
    $kode = trim($_POST['kode_status'] ?? '');
    $label = trim($_POST['label'] ?? '') ?: $kode;
    if ($kode !== '') {
        idcardCreateTemplateForStatus($koneksi, strtoupper($kode), $label);
        header('Location: template.php?msg=' . urlencode("Mapping layout untuk status \"$kode\" berhasil dibuat."));
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<a href="index.php" class="page-back"><?= icon('arrow-left', 14) ?> Kembali ke Master Data</a>
<div class="page-header">
    <h1 class="page-title">Layout Kartu per Status</h1>
</div>
<p class="hint" style="margin-top:-10px;">
    Setiap status karyawan (Kontrak, Magang, Tetap, Yayasan, dst) bisa punya desain kartu depan/belakang
    dan posisi foto/nama/NIK/barcode sendiri-sendiri. Kalau status karyawan belum punya layout khusus,
    otomatis pakai baris <strong>Default</strong>.
</p>

<?php if (!empty($_GET['msg'])): ?><div class="alert alert-success"><?= icon('check', 16) ?> <?= htmlspecialchars($_GET['msg']) ?></div><?php endif; ?>

<div class="template-grid">
    <?php foreach ($templates as $t):
        $resolved = idcardResolveLayout($koneksi, $t['kode_status']);
        $frontRel = str_replace(realpath(__DIR__ . '/..') . '/', '', $resolved['front_image_abs']);
    ?>
    <a class="template-card" href="template_edit.php?id=<?= (int)$t['id'] ?>">
        <div class="template-thumb">
            <img src="../<?= htmlspecialchars($frontRel) ?>" alt="">
        </div>
        <div class="template-info">
            <h3><?= htmlspecialchars($t['label']) ?> <?= $t['is_default'] ? '<span class="badge-default">default</span>' : '' ?></h3>
            <p class="muted">Kode status: <code><?= htmlspecialchars($t['kode_status']) ?></code></p>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<div class="card-box" style="margin-top:20px;">
    <h3><?= icon('plus', 16) ?> Tambah mapping layout untuk status lain</h3>
    <?php if (!empty($unmapped)): ?>
        <p class="hint">Status berikut ada di Master Status tapi belum punya layout kartu sendiri:</p>
        <form method="post" class="toolbar">
            <input type="hidden" name="action" value="add_mapping">
            <select name="kode_status" onchange="document.getElementById('labelInput').value = this.options[this.selectedIndex].dataset.label || '';">
                <?php foreach ($unmapped as $o): ?>
                    <option value="<?= htmlspecialchars($o['kode']) ?>" data-label="<?= htmlspecialchars($o['nama']) ?>"><?= htmlspecialchars($o['nama']) ?> (<?= htmlspecialchars($o['kode']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="label" id="labelInput" value="<?= htmlspecialchars($unmapped[array_key_first($unmapped)]['nama'] ?? '') ?>">
            <button type="submit" class="btn success">Buat Layout Baru</button>
        </form>
    <?php else: ?>
        <p class="hint">Atau ketik manual kode status baru (dipakai kalau kode statusnya belum terdaftar di Master Status):</p>
        <form method="post" class="toolbar">
            <input type="hidden" name="action" value="add_mapping">
            <input type="text" name="kode_status" placeholder="Kode Status, mis. PROBATION" required style="text-transform:uppercase;">
            <input type="text" name="label" placeholder="Label (mis. Masa Percobaan)">
            <button type="submit" class="btn success">Buat Layout Baru</button>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
