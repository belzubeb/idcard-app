<?php
/**
 * includes/tcpdf_autoload.php
 * Loader TCPDF versi minimal (folder vendor/tcpdf/, sudah di-trim
 * cuma nyisain font yang dipakai: helvetica, times, courier, dejavusans).
 *
 * Cukup require file ini di halaman yang butuh generate PDF:
 *   require_once __DIR__ . '/../includes/tcpdf_autoload.php';
 */

// Matikan config eksternal bawaan TCPDF, kita set manual di bawah biar ringkas & terkontrol.
if (!defined('K_TCPDF_EXTERNAL_CONFIG')) {
    define('K_TCPDF_EXTERNAL_CONFIG', true);
}

$tcpdfRoot = __DIR__ . '/../vendor/tcpdf/';

define('K_PATH_MAIN', $tcpdfRoot);
define('K_PATH_URL', ''); // gak dipakai buat generate PDF server-side
define('K_PATH_FONTS', $tcpdfRoot . 'fonts/');
define('K_PATH_CACHE', sys_get_temp_dir() . '/tcpdf_cache/');
define('K_PATH_IMAGES', __DIR__ . '/../assets/img/');
define('K_BLANK_IMAGE', __DIR__ . '/../assets/img/_blank.png');

define('PDF_PAGE_FORMAT', 'A4');
define('PDF_PAGE_ORIENTATION', 'P');
define('PDF_CREATOR', 'PT WOOIN - ID Card Generator');
define('PDF_AUTHOR', 'PT WOOIN');
define('PDF_HEADER_TITLE', '');
define('PDF_HEADER_STRING', '');
define('PDF_UNIT', 'mm');
define('PDF_MARGIN_HEADER', 0);
define('PDF_MARGIN_FOOTER', 0);
define('PDF_MARGIN_TOP', 0);
define('PDF_MARGIN_BOTTOM', 0);
define('PDF_MARGIN_LEFT', 0);
define('PDF_MARGIN_RIGHT', 0);
define('PDF_FONT_NAME_MAIN', 'helvetica');
define('PDF_FONT_SIZE_MAIN', 10);
define('PDF_FONT_NAME_DATA', 'helvetica');
define('PDF_FONT_SIZE_DATA', 8);
define('PDF_FONT_MONOSPACED', 'courier');
define('PDF_IMAGE_SCALE_RATIO', 1.25);
define('K_CELL_HEIGHT_RATIO', 1.25);
define('K_TITLE_MAGNIFICATION', 1.3);
define('K_SMALL_RATIO', 2 / 3);
define('K_THAI_TOPCHARS', true);
define('K_TCPDF_CALLS_IN_HTML', false);
define('K_TIMEZONE', 'Asia/Jakarta');
define('HEAD_MAGNIFICATION', 1.1);

// Pastikan folder cache ada (TCPDF butuh ini buat cache internal)
if (!is_dir(K_PATH_CACHE)) {
    @mkdir(K_PATH_CACHE, 0777, true);
}

require_once $tcpdfRoot . 'tcpdf.php';
