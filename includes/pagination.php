<?php
/**
 * includes/pagination.php
 * Helper pagination generic, dipakai di halaman list (Data Karyawan, Master Data).
 *
 * Pemakaian:
 *   $page    = paginateGetPage();               // ambil halaman aktif dari ?page=
 *   $perPage = 20;
 *   $offset  = ($page - 1) * $perPage;
 *   // ... query pakai LIMIT $perPage OFFSET $offset, dan query COUNT(*) terpisah buat $totalRows
 *   echo renderPagination($totalRows, $perPage, $page);
 */

require_once __DIR__ . '/icons.php';

function paginateGetPage(): int
{
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    return $page > 0 ? $page : 1;
}

/**
 * Bangun ulang query string saat ini tapi dengan parameter "page" diganti.
 */
function paginateUrl(int $page): string
{
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

/**
 * Render kontrol pagination (Sebelumnya / nomor halaman / Berikutnya) + info jumlah data.
 */
function renderPagination(int $totalRows, int $perPage, int $currentPage): string
{
    $totalPages = (int) max(1, ceil($totalRows / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));

    if ($totalRows === 0) {
        return '';
    }

    $start = ($currentPage - 1) * $perPage + 1;
    $end = min($totalRows, $currentPage * $perPage);

    $html = '<div class="pagination">';
    $html .= '<div class="pagination-info">Menampilkan <strong>' . $start . '&ndash;' . $end . '</strong> dari <strong>' . $totalRows . '</strong> data</div>';
    $html .= '<div class="pagination-controls">';

    $prevDisabled = $currentPage <= 1 ? ' disabled' : '';
    $prevHref = $currentPage <= 1 ? '#' : htmlspecialchars(paginateUrl($currentPage - 1));
    $html .= '<a href="' . $prevHref . '" class="page-btn' . $prevDisabled . '" aria-label="Halaman sebelumnya">' . icon('chevron-left', 16) . '</a>';

    // Bangun daftar nomor halaman dengan elipsis kalau halamannya banyak
    $pagesToShow = [];
    for ($p = 1; $p <= $totalPages; $p++) {
        if ($p === 1 || $p === $totalPages || abs($p - $currentPage) <= 1) {
            $pagesToShow[] = $p;
        }
    }
    $pagesToShow = array_values(array_unique($pagesToShow));

    $prevP = null;
    foreach ($pagesToShow as $p) {
        if ($prevP !== null && $p - $prevP > 1) {
            $html .= '<span class="page-ellipsis">&hellip;</span>';
        }
        $activeClass = $p === $currentPage ? ' active' : '';
        $html .= '<a href="' . htmlspecialchars(paginateUrl($p)) . '" class="page-btn' . $activeClass . '">' . $p . '</a>';
        $prevP = $p;
    }

    $nextDisabled = $currentPage >= $totalPages ? ' disabled' : '';
    $nextHref = $currentPage >= $totalPages ? '#' : htmlspecialchars(paginateUrl($currentPage + 1));
    $html .= '<a href="' . $nextHref . '" class="page-btn' . $nextDisabled . '" aria-label="Halaman berikutnya">' . icon('chevron-right', 16) . '</a>';

    $html .= '</div></div>';
    return $html;
}
