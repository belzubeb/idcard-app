<?php
/**
 * includes/master_helper.php
 * Baca struktur tabel master secara otomatis (introspection) biar gak perlu
 * hardcode nama kolom yang belum tentu benar. Dipakai buat:
 *   1. LEFT JOIN otomatis di karyawan/index.php (nampilin nama departemen,
 *      bukan cuma kode-nya)
 *   2. Generic CRUD di master/manage.php
 *
 * Daftar tabel master yang dikenal aplikasi ini:
 */
function getMasterTableList()
{
    return [
        'departemen' => ['table' => 'tbdepartemen', 'fk_in_karyawan' => 'KodeDepartemen', 'label' => 'Departemen'],
        'jabatan'    => ['table' => 'tbjabatan',    'fk_in_karyawan' => 'KodeJabatan',    'label' => 'Jabatan'],
        'status'     => ['table' => 'tbstatus',     'fk_in_karyawan' => 'KodeStatus',     'label' => 'Status'],
        'lpk'        => ['table' => 'tbllpk',       'fk_in_karyawan' => 'KodeLPK',        'label' => 'LPK'],
    ];
}

/**
 * Ambil struktur kolom sebuah tabel + tebak kolom PK & kolom "nama" (buat ditampilkan).
 * Balikin null kalau tabel gak ada (biar gak fatal error).
 */
function getMasterMeta(mysqli $koneksi, string $table)
{
    $tableEsc = mysqli_real_escape_string($koneksi, $table);
    $res = @mysqli_query($koneksi, "SHOW COLUMNS FROM `$tableEsc`");
    if (!$res) {
        return null; // tabel gak ada / gak bisa diakses
    }

    $columns = [];
    $pk = null;
    while ($col = mysqli_fetch_assoc($res)) {
        if ($col['Key'] === 'PRI' && $pk === null) {
            $pk = $col['Field'];
        }
        // Kolom BLOB/TEXT besar (mis. tbstatus.Layout peninggalan software lama)
        // sengaja dilewati dari form/tabel generic ini, biar gak coba nampilin/nyimpen
        // data binary lewat <input type=text>. Layout kartu yang baru diatur lewat
        // menu Master Data > Layout Kartu (includes/template_store.php).
        if (preg_match('/blob/i', $col['Type'])) {
            continue;
        }
        $columns[] = $col['Field'];
    }
    if ($pk === null && count($columns) > 0) {
        $pk = $columns[0]; // fallback: kolom pertama dianggap PK
    }

    // Cari kolom "nama" - prioritas kolom yang namanya mengandung kata "nama"
    $nameCol = null;
    foreach ($columns as $c) {
        if (stripos($c, 'nama') !== false) {
            $nameCol = $c;
            break;
        }
    }
    // fallback: kolom kedua (biasanya kode di kolom 1, nama di kolom 2)
    if ($nameCol === null && count($columns) > 1) {
        $nameCol = $columns[1];
    } elseif ($nameCol === null) {
        $nameCol = $columns[0];
    }

    return [
        'table'   => $table,
        'columns' => $columns,
        'pk'      => $pk,
        'name_col' => $nameCol,
    ];
}
