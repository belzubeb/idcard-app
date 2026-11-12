<?php
$activeMenu = 'dashboard';
$pageTitle  = 'Dashboard';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php';

$totalKaryawan = 0;
$res = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM tbkaryawan WHERE Aktif = 'Y'");
if ($res && $row = mysqli_fetch_assoc($res)) {
    $totalKaryawan = (int) $row['total'];
}

$totalNonAktif = 0;
$res = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM tbkaryawan WHERE Aktif = 'N'");
if ($res && $row = mysqli_fetch_assoc($res)) {
    $totalNonAktif = (int) $row['total'];
}
?>
<div class="page-header">
    <h1 class="page-title">Dashboard</h1>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><?= icon('users', 20) ?></div>
        <div>
            <div class="stat-value"><?= $totalKaryawan ?></div>
            <div class="stat-label">Karyawan Aktif</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><?= icon('x', 20) ?></div>
        <div>
            <div class="stat-value"><?= $totalNonAktif ?></div>
            <div class="stat-label">Karyawan Non-aktif</div>
        </div>
    </div>
</div>

<div class="menu-grid">
    <a class="menu-card" href="karyawan/index.php">
        <div class="menu-card-icon"><?= icon('id-card', 22) ?></div>
        <h3>Data Karyawan</h3>
        <p>Cari, lihat, dan cetak ID Card (satu-satu atau banyak sekaligus).</p>
    </a>
    <a class="menu-card" href="master/index.php">
        <div class="menu-card-icon"><?= icon('folder', 22) ?></div>
        <h3>Master Data</h3>
        <p>Kelola Departemen, Jabatan, Status, dan LPK.</p>
    </a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
