<?php
/**
 * master/manage.php
 * CRUD generic buat tabel master (Departemen/Jabatan/Status/LPK).
 * Kolom form dibuat otomatis berdasarkan struktur tabel asli (introspection),
 * jadi gak perlu hardcode nama kolom.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/pagination.php';

$masterList = getMasterTableList();
$entity = $_GET['entity'] ?? '';

if (!isset($masterList[$entity])) {
    http_response_code(404);
    die('Master data tidak dikenali.');
}
$info = $masterList[$entity];
$meta = getMasterMeta($koneksi, $info['table']);

if ($meta === null) {
    $activeMenu = 'master';
    $pageTitle = $info['label'];
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="page-header"><h1 class="page-title"><?= htmlspecialchars($info['label']) ?></h1></div>
    <div class="alert alert-error"><?= icon('alert', 16) ?> Tabel <code><?= htmlspecialchars($info['table']) ?></code> tidak ditemukan di database. Cek lagi nama tabelnya.</div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$table = $meta['table'];
$pk = $meta['pk'];
$columns = $meta['columns'];
$editableColumns = array_values(array_filter($columns, fn($c) => $c !== $pk)); // PK biasanya auto/kode, gak diedit lewat form biasa kalau auto increment; tapi kalau PK-nya text (kode manual), tetap perlu diisi saat tambah baru.

// deteksi apakah PK auto_increment (kalau iya, sembunyikan dari form tambah)
$pkAutoIncrement = false;
$resCol = mysqli_query($koneksi, "SHOW COLUMNS FROM `$table` WHERE Field = '" . mysqli_real_escape_string($koneksi, $pk) . "'");
if ($resCol && $c = mysqli_fetch_assoc($resCol)) {
    $pkAutoIncrement = stripos($c['Extra'], 'auto_increment') !== false;
}
$formColumns = $pkAutoIncrement ? $editableColumns : $columns;

$message = '';
$messageType = 'success';

// ==== Handle POST (Tambah / Edit) ====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $isEdit = !empty($_POST['pk_value']);
        $data = [];
        foreach ($formColumns as $col) {
            $data[$col] = $_POST['field'][$col] ?? '';
        }

        if ($isEdit) {
            $setParts = [];
            $params = [];
            $types = '';
            foreach ($data as $col => $val) {
                $setParts[] = "`$col` = ?";
                $params[] = $val;
                $types .= 's';
            }
            $params[] = $_POST['pk_value'];
            $types .= 's';
            $sql = "UPDATE `$table` SET " . implode(', ', $setParts) . " WHERE `$pk` = ?";
            $stmt = mysqli_prepare($koneksi, $sql);
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Data berhasil diupdate.';
            } else {
                $message = 'Gagal update: ' . mysqli_error($koneksi);
                $messageType = 'error';
            }
            mysqli_stmt_close($stmt);
        } else {
            $cols = array_keys($data);
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $colNames = implode(',', array_map(fn($c) => "`$c`", $cols));
            $params = array_values($data);
            $types = str_repeat('s', count($params));
            $sql = "INSERT INTO `$table` ($colNames) VALUES ($placeholders)";
            $stmt = mysqli_prepare($koneksi, $sql);
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Data baru berhasil ditambahkan.';
            } else {
                $message = 'Gagal tambah data: ' . mysqli_error($koneksi);
                $messageType = 'error';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action === 'delete') {
        $pkValue = $_POST['pk_value'] ?? '';
        $stmt = mysqli_prepare($koneksi, "DELETE FROM `$table` WHERE `$pk` = ?");
        mysqli_stmt_bind_param($stmt, 's', $pkValue);
        if (mysqli_stmt_execute($stmt)) {
            $message = 'Data berhasil dihapus.';
        } else {
            $message = 'Gagal hapus (kemungkinan masih dipakai di data karyawan): ' . mysqli_error($koneksi);
            $messageType = 'error';
        }
        mysqli_stmt_close($stmt);
    }
}

// ==== Ambil data untuk edit (kalau ada ?edit=) ====
$editRow = null;
if (isset($_GET['edit'])) {
    $stmt = mysqli_prepare($koneksi, "SELECT * FROM `$table` WHERE `$pk` = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $_GET['edit']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $editRow = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

// ==== Pagination daftar data ====
$perPage = 20;
$page = paginateGetPage();
$offset = ($page - 1) * $perPage;

$totalRows = 0;
$resCount = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM `$table`");
if ($resCount && $r = mysqli_fetch_assoc($resCount)) $totalRows = (int) $r['total'];

$allData = [];
$res = mysqli_query($koneksi, "SELECT * FROM `$table` ORDER BY `$pk` ASC LIMIT $perPage OFFSET $offset");
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $allData[] = $r;
    }
}

$activeMenu = 'master';
$pageTitle = $info['label'];
require_once __DIR__ . '/../includes/header.php';
?>
<a href="index.php" class="page-back"><?= icon('arrow-left', 14) ?> Kembali ke Master Data</a>
<div class="page-header">
    <h1 class="page-title">Master <?= htmlspecialchars($info['label']) ?></h1>
</div>

<?php if ($message): ?>
    <div class="alert <?= $messageType === 'error' ? 'alert-error' : 'alert-success' ?>">
        <?= icon($messageType === 'error' ? 'alert' : 'check', 16) ?> <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="card-box">
    <h3><?= $editRow ? 'Edit Data' : 'Tambah Data Baru' ?></h3>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <?php if ($editRow): ?>
            <input type="hidden" name="pk_value" value="<?= htmlspecialchars($editRow[$pk]) ?>">
        <?php endif; ?>

        <table class="simple-form-table">
            <?php foreach ($formColumns as $col): ?>
            <tr>
                <td><?= htmlspecialchars($col) ?></td>
                <td>
                    <input type="text" name="field[<?= htmlspecialchars($col) ?>]"
                           value="<?= htmlspecialchars($editRow[$col] ?? '') ?>"
                           <?= ($col === $pk && $editRow) ? 'readonly' : '' ?>>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <div class="form-actions" style="margin-top:14px;">
            <button type="submit" class="btn success"><?= icon('save', 16) ?> <?= $editRow ? 'Update' : 'Tambah' ?></button>
            <?php if ($editRow): ?>
                <a href="manage.php?entity=<?= urlencode($entity) ?>" class="btn secondary">Batal</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card-box">
    <h3>Daftar Data (<?= $totalRows ?>)</h3>
    <?php if (empty($allData)): ?>
        <div class="empty"><?= icon('folder', 28) ?> Belum ada data.</div>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <?php foreach ($columns as $col): ?>
                    <th><?= htmlspecialchars($col) ?></th>
                <?php endforeach; ?>
                <th style="width:160px;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allData as $row): ?>
            <tr>
                <?php foreach ($columns as $col): ?>
                    <td><?= htmlspecialchars((string) $row[$col]) ?></td>
                <?php endforeach; ?>
                <td class="actions-cell">
                    <a class="btn small secondary" href="manage.php?entity=<?= urlencode($entity) ?>&edit=<?= urlencode($row[$pk]) ?>"><?= icon('edit', 14) ?> Edit</a>
                    <form method="post" class="inline" onsubmit="return confirm('Yakin hapus data ini?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="pk_value" value="<?= htmlspecialchars($row[$pk]) ?>">
                        <button type="submit" class="btn danger small icon-only" title="Hapus"><?= icon('trash', 14) ?></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?= renderPagination($totalRows, $perPage, $page) ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
