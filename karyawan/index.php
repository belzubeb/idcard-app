<?php
$activeMenu = 'karyawan';
$pageTitle  = 'Data Karyawan';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/pagination.php';
require_once __DIR__ . '/../includes/header.php';

// ==== Ambil meta tabel master (buat JOIN otomatis & dropdown filter) ====
$masterList = getMasterTableList();
$masterMeta = [];
foreach ($masterList as $key => $info) {
    $masterMeta[$key] = getMasterMeta($koneksi, $info['table']);
}

// ==== Bangun query pencarian & filter ====
$search    = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
$filterDept   = isset($_GET['departemen']) ? trim($_GET['departemen']) : '';
$showInactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] === '1';

$allowedPerPage = [20, 50, 100, 200];
$perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 20;
}
$page = paginateGetPage();
$offset = ($page - 1) * $perPage;

$selectCols = "k.ID, k.NIK, k.NamaLengkap, k.NamaIDCard, k.KodeDepartemen, k.KodeJabatan, k.KodeStatus, k.KodeLPK, k.Aktif";
$joins = '';
$extraSelects = [];

foreach ($masterMeta as $key => $meta) {
    if ($meta === null) continue;
    $info = $masterList[$key];
    $alias = 'm_' . $key;
    $fk = $info['fk_in_karyawan'];
    $joins .= " LEFT JOIN `{$meta['table']}` {$alias} ON k.`{$fk}` = {$alias}.`{$meta['pk']}`";
    $extraSelects[] = "{$alias}.`{$meta['name_col']}` AS Nama_" . ucfirst($key);
}
if (!empty($extraSelects)) {
    $selectCols .= ', ' . implode(', ', $extraSelects);
}

$where = [];
$params = [];
$types = '';

if (!$showInactive) {
    $where[] = "k.Aktif = 'Y'";
}
if ($search !== '') {
    $where[] = "(k.NamaIDCard LIKE ? OR k.NamaLengkap LIKE ? OR k.NIK LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sss';
}
if ($filterStatus !== '') {
    $where[] = "k.KodeStatus = ?";
    $params[] = $filterStatus;
    $types .= 's';
}
if ($filterDept !== '') {
    $where[] = "k.KodeDepartemen = ?";
    $params[] = $filterDept;
    $types .= 's';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ==== Hitung total data (buat pagination) ====
$countSql = "SELECT COUNT(*) AS total FROM tbkaryawan k $joins $whereSql";
$stmt = mysqli_prepare($koneksi, $countSql);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$countResult = mysqli_stmt_get_result($stmt);
$totalRows = (int) (mysqli_fetch_assoc($countResult)['total'] ?? 0);
mysqli_stmt_close($stmt);

// ==== Ambil data untuk halaman aktif ====
$sql = "SELECT $selectCols FROM tbkaryawan k $joins $whereSql ORDER BY k.NamaIDCard ASC LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($koneksi, $sql);
$paramsWithLimit = array_merge($params, [$perPage, $offset]);
$typesWithLimit = $types . 'ii';
mysqli_stmt_bind_param($stmt, $typesWithLimit, ...$paramsWithLimit);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$karyawanList = [];
while ($row = mysqli_fetch_assoc($result)) {
    $karyawanList[] = $row;
}
mysqli_stmt_close($stmt);

// ==== Ambil opsi dropdown filter (Status & Departemen) ====
function getDropdownOptions(mysqli $koneksi, $meta)
{
    if ($meta === null) return [];
    $sql = "SELECT `{$meta['pk']}` AS kode, `{$meta['name_col']}` AS nama FROM `{$meta['table']}` ORDER BY `{$meta['name_col']}` ASC";
    $res = @mysqli_query($koneksi, $sql);
    $opts = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $opts[] = $r;
        }
    }
    return $opts;
}
$statusOptions = getDropdownOptions($koneksi, $masterMeta['status']);
$deptOptions   = getDropdownOptions($koneksi, $masterMeta['departemen']);
?>
<div class="page-header">
    <h1 class="page-title">Data Karyawan</h1>
    <div class="page-actions">
        <a href="import.php" class="btn secondary"><?= icon('upload', 16) ?> Import Excel</a>
        <a href="import_photos.php" class="btn secondary"><?= icon('image', 16) ?> Upload Foto Massal</a>
        <a href="form.php" class="btn success"><?= icon('plus', 16) ?> Tambah Karyawan</a>
    </div>
</div>

<?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-success"><?= icon('check', 16) ?> <?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-error"><?= icon('alert', 16) ?> <?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div class="card-box">
    <form method="get" class="toolbar">
        <div class="search-box">
            <?= icon('search', 16) ?>
            <input type="text" name="q" placeholder="Cari nama atau NIK..." value="<?= htmlspecialchars($search) ?>">
        </div>

        <?php if (!empty($statusOptions)): ?>
        <select name="status">
            <option value="">Semua Status</option>
            <?php foreach ($statusOptions as $opt): ?>
                <option value="<?= htmlspecialchars($opt['kode']) ?>" <?= $filterStatus === $opt['kode'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($opt['nama']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <?php if (!empty($deptOptions)): ?>
        <select name="departemen">
            <option value="">Semua Departemen</option>
            <?php foreach ($deptOptions as $opt): ?>
                <option value="<?= htmlspecialchars($opt['kode']) ?>" <?= $filterDept === $opt['kode'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($opt['nama']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <label class="check-inline">
            <input type="checkbox" name="show_inactive" value="1" <?= $showInactive ? 'checked' : '' ?> onchange="this.form.submit()">
            Tampilkan non-aktif
        </label>

        <select name="per_page" onchange="this.form.submit()">
            <?php foreach ($allowedPerPage as $pp): ?>
                <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?> / halaman</option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn">Cari</button>
        <?php if ($search !== '' || $filterStatus !== '' || $filterDept !== '' || $showInactive): ?>
            <a href="index.php" class="btn secondary">Reset</a>
        <?php endif; ?>
    </form>

    <form method="post" action="../cards/generate_batch.php" target="_blank" id="bulkForm">
        <div class="toolbar bulk-toolbar">
            <div class="bulk-left">
                <label class="check-inline">
                    <input type="checkbox" id="checkAll"> Pilih Semua (halaman ini)
                </label>
                <span id="selectedCount" class="muted">0 dipilih</span>
                <button type="button" id="clearSelectionBtn" class="btn small secondary" style="display:none;">Bersihkan pilihan</button>
            </div>
            <div class="bulk-right">
                <label class="name-mode-label">
                    Nama saat cetak:
                    <select name="name" id="nameMode">
                        <option value="idcard">Nama ID Card (pendek)</option>
                        <option value="lengkap">Nama Lengkap (panjang)</option>
                    </select>
                </label>
                <button type="submit" class="btn success" id="bulkPrintBtn" disabled><?= icon('printer', 16) ?> Cetak Terpilih (Batch)</button>
                <button type="submit" formaction="delete.php" formtarget="_self" class="btn danger" id="bulkDeleteBtn" disabled
                        onclick="return confirm('Yakin hapus semua data yang dipilih? Tindakan ini tidak bisa dibatalkan.');">
                    <?= icon('trash', 16) ?> Hapus Terpilih
                </button>
            </div>
        </div>

        <?php if (empty($karyawanList)): ?>
            <div class="empty">
                <?= icon('search', 28) ?>
                Tidak ada data karyawan yang cocok. Coba ubah kata kunci pencarian atau filter.
            </div>
        <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:30px;"></th>
                    <th style="width:44px;">No</th>
                    <th>Foto</th>
                    <th>NIK</th>
                    <th>Nama (ID Card)</th>
                    <?php if (!empty($extraSelects)): ?>
                        <th>Departemen</th>
                        <th>Jabatan</th>
                        <th>Status</th>
                    <?php else: ?>
                        <th>Kode Departemen</th>
                        <th>Kode Jabatan</th>
                        <th>Kode Status</th>
                    <?php endif; ?>
                    <th>Kondisi</th>
                    <th style="width:230px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = $offset + 1; foreach ($karyawanList as $row): ?>
                <tr>
                    <td><input type="checkbox" class="rowCheck" name="ids[]" value="<?= (int)$row['ID'] ?>"></td>
                    <td><?= $no++ ?></td>
                    <td><img class="thumb" src="../includes/foto.php?id=<?= (int)$row['ID'] ?>" alt=""></td>
                    <td><?= htmlspecialchars($row['NIK']) ?></td>
                    <td>
                        <div class="nama-idcard"><?= htmlspecialchars($row['NamaIDCard']) ?></div>
                        <div class="nama-lengkap muted"><?= htmlspecialchars($row['NamaLengkap']) ?></div>
                    </td>
                    <td><?= htmlspecialchars($row['Nama_Departemen'] ?? $row['KodeDepartemen']) ?></td>
                    <td><?= htmlspecialchars($row['Nama_Jabatan'] ?? $row['KodeJabatan']) ?></td>
                    <td><span class="badge badge-<?= strtolower(htmlspecialchars($row['KodeStatus'])) ?>"><?= htmlspecialchars($row['Nama_Status'] ?? $row['KodeStatus']) ?></span></td>
                    <td><span class="dot <?= $row['Aktif'] === 'Y' ? 'dot-active' : 'dot-inactive' ?>"><?= $row['Aktif'] === 'Y' ? 'Aktif' : 'Non-aktif' ?></span></td>
                    <td class="actions-cell">
                        <a class="btn small icon-only" href="../cards/generate.php?id=<?= (int)$row['ID'] ?>" target="_blank" title="Cetak nama ID Card"><?= icon('printer', 15) ?></a>
                        <a class="btn small secondary" href="form.php?id=<?= (int)$row['ID'] ?>"><?= icon('edit', 14) ?> Edit</a>
                        <button type="button" class="btn small danger icon-only" title="Hapus" onclick="deleteSingle(<?= (int)$row['ID'] ?>, '<?= htmlspecialchars(addslashes($row['NamaIDCard'])) ?>')"><?= icon('trash', 15) ?></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>

        <?= renderPagination($totalRows, $perPage, $page) ?>
    </form>

    <form method="post" action="delete.php" id="singleDeleteForm" style="display:none;">
        <input type="hidden" name="ids[]" id="singleDeleteId">
    </form>
</div>

<script>
// ==== Pilihan (checkbox) dipertahankan lintas halaman pake localStorage ====
// Supaya bisa pilih ratusan karyawan biar pun harus pindah-pindah halaman pagination.
const STORAGE_KEY = 'idcard_bulk_selection';
const checkAll = document.getElementById('checkAll');
const rowChecks = document.querySelectorAll('.rowCheck');
const selectedCount = document.getElementById('selectedCount');
const bulkPrintBtn = document.getElementById('bulkPrintBtn');
const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
const clearSelectionBtn = document.getElementById('clearSelectionBtn');
const bulkForm = document.getElementById('bulkForm');

function loadSelection() {
    try {
        return new Set(JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'));
    } catch (e) {
        return new Set();
    }
}
function saveSelection(set) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify([...set]));
}

let selection = loadSelection();

// Centang ulang checkbox di halaman ini kalau ID-nya ada di pilihan tersimpan
rowChecks.forEach(cb => {
    if (selection.has(cb.value)) cb.checked = true;
});

function updateCount() {
    // sinkronkan state checkbox halaman ini ke "selection" global
    rowChecks.forEach(cb => {
        if (cb.checked) selection.add(cb.value);
        else selection.delete(cb.value);
    });
    saveSelection(selection);

    const total = selection.size;
    const onPage = document.querySelectorAll('.rowCheck:checked').length;
    selectedCount.textContent = total + ' dipilih' + (total > onPage ? ' (termasuk halaman lain)' : '');
    bulkPrintBtn.disabled = total === 0;
    bulkDeleteBtn.disabled = total === 0;
    clearSelectionBtn.style.display = total > 0 ? '' : 'none';

    if (checkAll) {
        checkAll.checked = rowChecks.length > 0 && onPage === rowChecks.length;
    }
}

if (checkAll) {
    checkAll.addEventListener('change', () => {
        rowChecks.forEach(cb => cb.checked = checkAll.checked);
        updateCount();
    });
}
rowChecks.forEach(cb => cb.addEventListener('change', updateCount));

if (clearSelectionBtn) {
    clearSelectionBtn.addEventListener('click', () => {
        selection = new Set();
        saveSelection(selection);
        rowChecks.forEach(cb => cb.checked = false);
        updateCount();
    });
}

// Sebelum submit (cetak/hapus terpilih), suntik hidden input buat ID yang
// terpilih tapi lagi gak tampil di halaman ini (karena beda halaman).
if (bulkForm) {
    bulkForm.addEventListener('submit', () => {
        const onPageIds = new Set([...rowChecks].map(cb => cb.value));
        selection.forEach(id => {
            if (!onPageIds.has(id)) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'ids[]';
                hidden.value = id;
                bulkForm.appendChild(hidden);
            }
        });
    });
}

updateCount();

function deleteSingle(id, nama) {
    if (confirm('Yakin hapus data karyawan "' + nama + '"? Tindakan ini tidak bisa dibatalkan.')) {
        document.getElementById('singleDeleteId').value = id;
        document.getElementById('singleDeleteForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>