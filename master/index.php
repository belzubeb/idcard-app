<?php
$activeMenu = 'master';
$pageTitle  = 'Master Data';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/header.php';

$masterList = getMasterTableList();
?>
<div class="page-header">
    <h1 class="page-title">Master Data</h1>
</div>
<p class="hint" style="margin-top:-14px;">
    Kelola <strong>Status</strong> &amp; layout kartu. Data Departemen, Jabatan dan LPK sudah dinonaktifkan di UI
    (tabel &amp; data di database sengaja dibiarkan, tidak dihapus).
</p>

<div class="menu-grid">
    <a class="menu-card" href="template.php">
        <div class="menu-card-icon"><?= icon('layout', 22) ?></div>
        <h3>Layout Kartu</h3>
        <p>Atur gambar depan/belakang &amp; posisi foto/nama/NIK/barcode per status karyawan.</p>
    </a>
    <?php
    // UI dinonaktifkan untuk Departemen, Jabatan & LPK — data DB tetap utuh.
    $uiDisabled = ['departemen', 'jabatan', 'lpk'];
    foreach ($masterList as $key => $info):
        if (in_array($key, $uiDisabled, true)) continue;
        $meta = getMasterMeta($koneksi, $info['table']);
        $count = null;
        if ($meta) {
            $res = @mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM `{$meta['table']}`");
            if ($res && $r = mysqli_fetch_assoc($res)) $count = $r['total'];
        }
    ?>
    <a class="menu-card" href="manage.php?entity=<?= urlencode($key) ?>">
        <div class="menu-card-icon"><?= icon('tag', 22) ?></div>
        <h3><?= htmlspecialchars($info['label']) ?></h3>
        <p>
            <?php if ($meta === null): ?>
                <?= icon('alert', 14) ?> Tabel <code><?= htmlspecialchars($info['table']) ?></code> tidak ditemukan.
            <?php else: ?>
                <?= $count ?? '-' ?> data &middot; tabel <code><?= htmlspecialchars($info['table']) ?></code>
            <?php endif; ?>
        </p>
    </a>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
