<?php
/**
 * includes/header.php
 * Panggil di paling atas tiap halaman:
 *   $activeMenu = 'karyawan'; // salah satu: dashboard, karyawan, master
 *   require_once __DIR__ . '/../includes/header.php';
 */
if (!isset($activeMenu)) {
    $activeMenu = '';
}
if (!isset($pageTitle)) {
    $pageTitle = 'ID Card App';
}

require_once __DIR__ . '/icons.php';

// Path relatif ke root project, dihitung otomatis biar link tetap benar
// baik diakses dari root maupun dari subfolder (karyawan/, master/, cards/).
// PENTING: SCRIPT_FILENAME & realpath() bisa beda gaya garis miring di Windows
// (\ vs /), jadi kedua sisi dinormalisasi ke "/" dulu SEBELUM dibandingkan,
// supaya deteksi prefix tetap benar baik di Linux maupun Windows (XAMPP/Laragon).
$currentFile = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']);
$rootDir = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$relativeToRoot = str_replace($rootDir, '', dirname($currentFile));
$prefix = ($relativeToRoot === '' || $relativeToRoot === '/') ? '' : '../';

// Logo custom (opsional) — taruh file di assets/img/logo.png, kalau belum ada
// tampilkan lambang huruf sebagai fallback biar sidebar tetap rapi.
$logoAbsPath = $rootDir . '/assets/img/logo.png';
$hasLogo = file_exists($logoAbsPath);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> - ID Card PT WOOIN</title>
    <link rel="stylesheet" href="<?= $prefix ?>assets/css/style.css">
</head>
<body>
<button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Buka menu">
    <?= icon('grid', 20) ?>
</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a href="<?= $prefix ?>index.php" class="sidebar-brand">
            <span class="brand-logo">
                <?php if ($hasLogo): ?>
                    <img src="<?= $prefix ?>assets/img/logo.png" alt="Logo">
                <?php else: ?>
                    SW
                <?php endif; ?>
            </span>
            <span class="brand-text">
                <strong>STARION</strong>
                <small>PT WOOIN &middot; ID Card</small>
            </span>
        </a>

        <nav class="sidebar-nav">
            <span class="sidebar-label">Menu</span>
            <a href="<?= $prefix ?>index.php" class="<?= $activeMenu === 'dashboard' ? 'active' : '' ?>">
                <?= icon('home') ?> <span>Dashboard</span>
            </a>
            <a href="<?= $prefix ?>karyawan/index.php" class="<?= $activeMenu === 'karyawan' ? 'active' : '' ?>">
                <?= icon('users') ?> <span>Data Karyawan</span>
            </a>
            <a href="<?= $prefix ?>master/index.php" class="<?= $activeMenu === 'master' ? 'active' : '' ?>">
                <?= icon('folder') ?> <span>Master Data</span>
            </a>
        </nav>
    </aside>

    <div class="main-area">
        <main class="container">
