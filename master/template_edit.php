<?php
$activeMenu = 'master';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/template_store.php';

$id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : null;
if (!$id) {
    header('Location: template.php');
    exit;
}

$templates = idcardGetAllTemplates($koneksi);
$row = null;
foreach ($templates as $t) {
    if ((int) $t['id'] === $id) { $row = $t; break; }
}
if (!$row) {
    header('Location: template.php?error=' . urlencode('Layout tidak ditemukan.'));
    exit;
}

$resolved = idcardResolveLayout($koneksi, $row['kode_status']);
$rootDir = realpath(__DIR__ . '/..') . '/';
$frontRel = str_replace($rootDir, '', $resolved['front_image_abs']);
$backRel  = str_replace($rootDir, '', $resolved['back_image_abs']);
$hasCustomFront = !empty($row['front_image']);
$hasCustomBack  = !empty($row['back_image']);

$pageTitle = 'Layout Kartu - ' . $row['label'];
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><h1 class="page-title">Layout Kartu: <?= htmlspecialchars($row['label']) ?></h1></div>
<a href="template.php" class="page-back"><?= icon('arrow-left', 14) ?> Kembali ke Layout Kartu</a>

<?php if (!empty($_GET['msg'])): ?><div class="alert alert-success"><?= icon('check', 16) ?> <?= htmlspecialchars($_GET['msg']) ?></div><?php endif; ?>
<?php if (!empty($_GET['error'])): ?><div class="alert alert-error"><?= icon('alert', 16) ?> <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>

<form method="post" action="template_save.php" enctype="multipart/form-data" id="layoutForm">
<input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
<input type="hidden" name="kode_status" value="<?= htmlspecialchars($row['kode_status']) ?>">

<div class="editor-layout">
    <!-- ================= PREVIEW ================= -->
    <div class="editor-preview">
        <div class="preview-block">
            <div class="preview-head">
                <h3>Kartu Depan</h3>
                <div class="preview-upload-actions">
                    <label class="btn small secondary file-btn">
                        <?= icon('upload', 14) ?> <?= $hasCustomFront ? 'Ganti' : 'Upload' ?> Gambar
                        <input type="file" name="front_image_file" accept="image/png,image/jpeg,image/webp" onchange="this.form.requestSubmit(document.getElementById('btnUploadFront'))">
                    </label>
                    <button type="submit" name="action" value="upload_front" id="btnUploadFront" style="display:none;"></button>
                    <?php if ($hasCustomFront): ?>
                        <button type="submit" name="action" value="delete_front" class="btn small danger" onclick="return confirm('Kembalikan ke gambar bawaan?');"><?= icon('undo', 14) ?> Reset</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="preview-canvas" id="previewFront" data-w="<?= $row['card_front_w'] ?>" data-h="<?= $row['card_h'] ?>">
                <img src="../<?= htmlspecialchars($frontRel) ?>?v=<?= time() ?>" id="frontImg">
                <div class="overlay-box" id="boxPhoto" data-field="photo" title="Kotak Foto — seret untuk pindah"><span>Foto</span></div>
                <div class="overlay-box overlay-text" id="boxNama" data-field="nama" title="Nama — seret untuk pindah"><span>Nama</span></div>
                <div class="overlay-box overlay-text" id="boxNik" data-field="nik" title="NIK — seret untuk pindah"><span>NIK</span></div>
            </div>
        </div>

        <div class="preview-block">
            <div class="preview-head">
                <h3>Kartu Belakang</h3>
                <div class="preview-upload-actions">
                    <label class="btn small secondary file-btn">
                        <?= icon('upload', 14) ?> <?= $hasCustomBack ? 'Ganti' : 'Upload' ?> Gambar
                        <input type="file" name="back_image_file" accept="image/png,image/jpeg,image/webp" onchange="this.form.requestSubmit(document.getElementById('btnUploadBack'))">
                    </label>
                    <button type="submit" name="action" value="upload_back" id="btnUploadBack" style="display:none;"></button>
                    <?php if ($hasCustomBack): ?>
                        <button type="submit" name="action" value="delete_back" class="btn small danger" onclick="return confirm('Kembalikan ke gambar bawaan?');"><?= icon('undo', 14) ?> Reset</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="preview-canvas" id="previewBack" data-w="<?= $row['card_back_w'] ?>" data-h="<?= $row['card_h'] ?>">
                <img src="../<?= htmlspecialchars($backRel) ?>?v=<?= time() ?>" id="backImg">
                <div class="overlay-box overlay-barcode" id="boxBarcode" data-field="barcode" title="Barcode — seret untuk pindah"><span>Barcode</span></div>
            </div>
        </div>
        <p class="hint"><?= icon('lightbulb', 14) ?> Kotak berwarna bisa diseret (drag) langsung di atas gambar untuk mengatur posisi, atau ketik angka presisi di panel kanan.</p>
    </div>

    <!-- ================= CONTROLS ================= -->
    <div class="editor-controls">
        <div class="ctrl-card">
            <h4>Ukuran Kartu (mm)</h4>
            <div class="ctrl-row">
                <label>Lebar Depan <input type="number" step="0.1" name="card_front_w" id="f_card_front_w" value="<?= $row['card_front_w'] ?>"></label>
                <label>Lebar Belakang <input type="number" step="0.1" name="card_back_w" id="f_card_back_w" value="<?= $row['card_back_w'] ?>"></label>
            </div>
            <div class="ctrl-row">
                <label>Tinggi <input type="number" step="0.1" name="card_h" id="f_card_h" value="<?= $row['card_h'] ?>"></label>
                <label>Jarak Depan-Belakang <input type="number" step="0.1" name="gap_front_back" id="f_gap_front_back" value="<?= $row['gap_front_back'] ?>"></label>
            </div>
        </div>

        <div class="ctrl-card">
            <h4><?= icon('image', 15) ?> Kotak Foto</h4>
            <div class="ctrl-row">
                <label>X <input type="number" step="0.1" name="photo_x" id="f_photo_x" value="<?= $row['photo_x'] ?>"></label>
                <label>Y <input type="number" step="0.1" name="photo_y" id="f_photo_y" value="<?= $row['photo_y'] ?>"></label>
            </div>
            <div class="ctrl-row">
                <label>Lebar <input type="number" step="0.1" name="photo_w" id="f_photo_w" value="<?= $row['photo_w'] ?>"></label>
                <label>Tinggi <input type="number" step="0.1" name="photo_h" id="f_photo_h" value="<?= $row['photo_h'] ?>"></label>
            </div>
        </div>

        <div class="ctrl-card">
            <h4><?= icon('type', 15) ?> Nama</h4>
            <div class="ctrl-row">
                <label>X <input type="number" step="0.1" name="nama_x" id="f_nama_x" value="<?= $row['nama_x'] ?>"></label>
                <label>Y <input type="number" step="0.1" name="nama_y" id="f_nama_y" value="<?= $row['nama_y'] ?>"></label>
            </div>
            <div class="ctrl-row">
                <label>Lebar <input type="number" step="0.1" name="nama_w" id="f_nama_w" value="<?= $row['nama_w'] ?>"></label>
                <label>Tinggi <input type="number" step="0.1" name="nama_h" id="f_nama_h" value="<?= $row['nama_h'] ?>"></label>
            </div>
            <div class="ctrl-row">
                <label>Ukuran Font Awal <input type="number" step="1" name="nama_fontsz" id="f_nama_fontsz" value="<?= $row['nama_fontsz'] ?>"></label>
            </div>
            <p class="hint-sm">Font otomatis mengecil kalau nama kepanjangan supaya tidak kepotong.</p>
        </div>

        <div class="ctrl-card">
            <h4><?= icon('id-card', 15) ?> NIK</h4>
            <div class="ctrl-row">
                <label>X <input type="number" step="0.1" name="nik_x" id="f_nik_x" value="<?= $row['nik_x'] ?>"></label>
                <label>Y <input type="number" step="0.1" name="nik_y" id="f_nik_y" value="<?= $row['nik_y'] ?>"></label>
            </div>
            <div class="ctrl-row">
                <label>Lebar <input type="number" step="0.1" name="nik_w" id="f_nik_w" value="<?= $row['nik_w'] ?>"></label>
                <label>Tinggi <input type="number" step="0.1" name="nik_h" id="f_nik_h" value="<?= $row['nik_h'] ?>"></label>
            </div>
            <div class="ctrl-row">
                <label>Ukuran Font <input type="number" step="1" name="nik_fontsz" id="f_nik_fontsz" value="<?= $row['nik_fontsz'] ?>"></label>
            </div>
        </div>

        <div class="ctrl-card">
            <h4><?= icon('sliders', 15) ?> Barcode (belakang)</h4>
            <div class="ctrl-row">
                <label>Lebar <input type="number" step="0.1" name="barcode_w" id="f_barcode_w" value="<?= $row['barcode_w'] ?>"></label>
                <label>Tinggi <input type="number" step="0.1" name="barcode_h" id="f_barcode_h" value="<?= $row['barcode_h'] ?>"></label>
            </div>
            <div class="ctrl-row">
                <label>Posisi Y <input type="number" step="0.1" name="barcode_y" id="f_barcode_y" value="<?= $row['barcode_y'] ?>"></label>
                <label>Ketebalan Bar <input type="number" step="0.05" name="barcode_xres" id="f_barcode_xres" value="<?= $row['barcode_xres'] ?>"></label>
            </div>
            <div class="ctrl-row">
                <label>Geser Horizontal <input type="number" step="0.1" name="barcode_x_offset" id="f_barcode_x_offset" value="<?= $row['barcode_x_offset'] ?? 0 ?>"></label>
            </div>
            <p class="hint-sm">Barcode otomatis di tengah horizontal kartu belakang. Kalau kelihatan kurang center, seret kotaknya langsung atau isi "Geser Horizontal" (mm, minus = ke kiri, plus = ke kanan).</p>
        </div>

        <div class="ctrl-card">
            <h4><?= icon('tag', 15) ?> Label</h4>
            <input type="text" name="label" value="<?= htmlspecialchars($row['label']) ?>" style="width:100%;">
        </div>

        <button type="submit" name="action" value="save_layout" class="btn success btn-block"><?= icon('save', 16) ?> Simpan Posisi Layout</button>
    </div>
</div>
</form>

<script>
function setupCanvas(canvasId, fields) {
    const canvas = document.getElementById(canvasId);
    const cardWmm = parseFloat(canvas.dataset.w);
    const cardHmm = parseFloat(canvas.dataset.h);

    function px() { return canvas.clientWidth / cardWmm; }

    function render() {
        const scale = px();
        canvas.style.height = (cardHmm * scale) + 'px';
        fields.forEach(f => {
            const box = document.getElementById(f.box);
            if (!box) return;
            const x = parseFloat(document.getElementById(f.x).value) || 0;
            const y = parseFloat(document.getElementById(f.y).value) || 0;
            const w = f.w ? (parseFloat(document.getElementById(f.w).value) || 5) : (parseFloat(document.getElementById(f.wStatic)) || 5);
            const h = f.h ? (parseFloat(document.getElementById(f.h).value) || 5) : (parseFloat(document.getElementById(f.hStatic)) || 5);
            box.style.left = (x * scale) + 'px';
            box.style.top = (y * scale) + 'px';
            box.style.width = (w * scale) + 'px';
            box.style.height = (h * scale) + 'px';
        });
    }

    fields.forEach(f => {
        [f.x, f.y, f.w, f.h].forEach(id => {
            if (!id) return;
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', render);
        });

        const box = document.getElementById(f.box);
        if (!box) return;
        box.addEventListener('mousedown', e => {
            e.preventDefault();
            const scale = px();
            const startX = e.clientX, startY = e.clientY;
            const xInput = document.getElementById(f.x), yInput = document.getElementById(f.y);
            const origX = parseFloat(xInput.value) || 0;
            const origY = parseFloat(yInput.value) || 0;

            const persistXInput = f.persistX ? document.getElementById(f.persistX) : null;
            const origPersistX = persistXInput ? (parseFloat(persistXInput.value) || 0) : 0;

            function onMove(ev) {
                const dyMm = (ev.clientY - startY) / scale;
                yInput.value = Math.max(0, (origY + dyMm)).toFixed(1);
                if (persistXInput) {
                    // Field yang di-drag cuma representasi visual (mis. tengah otomatis + geseran);
                    // yang beneran ke-save adalah field geseran (boleh minus, gak di-clamp ke 0).
                    const dxMm = (ev.clientX - startX) / scale;
                    persistXInput.value = (origPersistX + dxMm).toFixed(1);
                    persistXInput.dispatchEvent(new Event('input'));
                } else if (!f.lockX) {
                    const dxMm = (ev.clientX - startX) / scale;
                    xInput.value = Math.max(0, (origX + dxMm)).toFixed(1);
                }
                render();
            }
            function onUp() {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            }
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    });

    window.addEventListener('resize', render);
    render();
}

setupCanvas('previewFront', [
    { box: 'boxPhoto', x: 'f_photo_x', y: 'f_photo_y', w: 'f_photo_w', h: 'f_photo_h' },
    { box: 'boxNama', x: 'f_nama_x', y: 'f_nama_y', w: 'f_nama_w', h: 'f_nama_h' },
    { box: 'boxNik', x: 'f_nik_x', y: 'f_nik_y', w: 'f_nik_w', h: 'f_nik_h' },
]);

// Barcode X = tengah otomatis kartu belakang + geseran manual "barcode_x_offset"
// (lihat card_helper.php), jadi di preview ini kita bikin field tersembunyi khusus
// buat mensimulasikan posisi X-nya berdasarkan kedua nilai itu.
// PENTING: elemen ini harus sudah ada di DOM SEBELUM setupCanvas('previewBack', ...) dipanggil,
// karena render() langsung jalan sekali saat setup dan butuh elemen ini.
(function () {
    const canvas = document.getElementById('previewBack');
    const cardBackW = parseFloat(canvas.dataset.w);
    const dummy = document.createElement('input');
    dummy.type = 'hidden';
    dummy.id = 'f_barcode_dummy_x';
    const barcodeWInput = document.getElementById('f_barcode_w');
    const offsetInput = document.getElementById('f_barcode_x_offset');

    function recalc() {
        const center = (cardBackW - (parseFloat(barcodeWInput.value) || 0)) / 2;
        const offset = parseFloat(offsetInput.value) || 0;
        dummy.value = Math.max(0, center + offset);
    }
    recalc();
    document.body.appendChild(dummy);

    barcodeWInput.addEventListener('input', () => { recalc(); dummy.dispatchEvent(new Event('input')); });
    offsetInput.addEventListener('input', () => { recalc(); dummy.dispatchEvent(new Event('input')); });
})();

setupCanvas('previewBack', [
    { box: 'boxBarcode', x: 'f_barcode_dummy_x', y: 'f_barcode_y', w: 'f_barcode_w', h: 'f_barcode_h', persistX: 'f_barcode_x_offset' },
]);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>