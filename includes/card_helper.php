<?php
/**
 * includes/card_helper.php
 * Fungsi reusable buat menggambar kartu (dipakai generate.php & generate_batch.php).
 * Layout diambil dari database (lihat includes/template_store.php) supaya bisa
 * diedit lewat browser di menu Master Data > Layout Kartu, per status karyawan.
 */

/**
 * Tulis teks ke dalam kotak dengan lebar terbatas, kalau kepanjangan otomatis:
 *  1) ukuran font diperkecil bertahap sampai muat 1 baris, atau
 *  2) kalau masih belum muat di ukuran font minimum, dipecah jadi 2 baris (MultiCell).
 * Ini mencegah nama karyawan yang panjang "kepotong" saat dicetak.
 */
function drawFittedText(TCPDF $pdf, $text, $x, $y, $w, $h, $baseFontSz, $minFontSz = 5.5, $bold = false)
{
    $style = $bold ? 'B' : '';
    $fontSz = $baseFontSz;
    $pdf->SetFont('helvetica', $style, $fontSz);

    while ($fontSz > $minFontSz && $pdf->GetStringWidth($text) > ($w - 1)) {
        $fontSz -= 0.5;
        $pdf->SetFont('helvetica', $style, $fontSz);
    }

    if ($pdf->GetStringWidth($text) > ($w - 1)) {
        // masih kepanjangan di font minimum -> pecah jadi 2 baris center.
        // PENTING: maxh TIDAK boleh dibatasi ke tinggi kotak aslinya ($h), karena
        // TCPDF akan diam-diam membuang baris kedua kalau kontennya melebihi maxh.
        // Jadi di sini maxh dimatikan (0 = tidak dibatasi) dan starting Y digeser
        // ke atas supaya blok 2 baris tetap terlihat center di posisi semula.
        $pdf->SetFont('helvetica', $style, max($minFontSz, $fontSz));
        $lineH = $h / 2;
        $pdf->SetXY($x, $y - ($lineH * 0.5));
        $pdf->MultiCell($w, $lineH, $text, 0, 'C', false, 1, null, null, true, 0, false, true, 0, 'T');
    } else {
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, $h, $text, 0, 0, 'C');
    }
}

/**
 * Gambar 1 kartu (depan + belakang) ke object TCPDF yang sudah di-AddPage().
 *
 * @param TCPDF $pdf
 * @param array $layout   hasil idcardResolveLayout() dari includes/template_store.php
 * @param array $data     ['nik'=>..,'nama'=>..,'foto_blob'=>..]
 * @param float $frontX   posisi X kartu depan (mm) di halaman
 * @param float $frontY   posisi Y kartu depan & belakang (mm) di halaman
 */
function drawCard(TCPDF $pdf, array $layout, array $data, $frontX, $frontY)
{
    $imgFront = $layout['front_image_abs'];
    $imgBack  = $layout['back_image_abs'];

    $cardFrontW = $layout['card_front_w'];
    $cardBackW  = $layout['card_back_w'];
    $cardH      = $layout['card_h'];
    $gap        = $layout['gap_front_back'];

    // ---- Foto ----
    $fotoBlob = $data['foto_blob'] ?? null;
    if (!empty($fotoBlob)) {
        $fotoSource = '@' . $fotoBlob;
    } else {
        $noPhoto = __DIR__ . '/../assets/img/no-photo.png';
        $fotoSource = is_file($noPhoto) ? $noPhoto : null;
    }

    // ================== KARTU DEPAN ==================
    if (is_file($imgFront)) {
        $pdf->Image($imgFront, $frontX, $frontY, $cardFrontW, $cardH, 'PNG');
    }

    if ($fotoSource) {
        $photoBoxX = $frontX + $layout['photo_x'];
        $photoBoxY = $frontY + $layout['photo_y'];

        $pdf->Image(
            $fotoSource,
            $photoBoxX,
            $photoBoxY,
            $layout['photo_w'],
            $layout['photo_h'],
            '', '', '', true, 300, '', false, false, 0, 'CM'
        );

        // Stroke/border tipis di pinggiran foto
        $pdf->SetLineStyle(['width' => 0.3, 'color' => [255, 255, 255]]);
        $pdf->Rect($photoBoxX, $photoBoxY, $layout['photo_w'], $layout['photo_h'], 'D');
    }

    $nama = strtoupper(trim((string) ($data['nama'] ?? '')));
    drawFittedText(
        $pdf, $nama,
        $frontX + $layout['nama_x'], $frontY + $layout['nama_y'],
        $layout['nama_w'], $layout['nama_h'],
        $layout['nama_fontsz'], 5.5, true
    );

    drawFittedText(
        $pdf, 'NIK : ' . $data['nik'],
        $frontX + $layout['nik_x'], $frontY + $layout['nik_y'],
        $layout['nik_w'], $layout['nik_h'],
        $layout['nik_fontsz'], 5.5, false
    );

    // ================== KARTU BELAKANG ==================
    $backX = $frontX + $cardFrontW + $gap;
    $backY = $frontY;

    if (is_file($imgBack)) {
        $pdf->Image($imgBack, $backX, $backY, $cardBackW, $cardH, 'PNG');
    }

    // Barcode: lebar diperkecil + dipusatkan otomatis secara horizontal
    $barcodeW = $layout['barcode_w'];
    $barcodeOffset = $layout['barcode_x_offset'] ?? 0;
    $barcodeX = $backX + (($cardBackW - $barcodeW) / 2) + $barcodeOffset;

    $pdf->write1DBarcode(
        $data['nik'],
        'C128',
        $barcodeX,
        $backY + $layout['barcode_y'],
        $barcodeW,
        $layout['barcode_h'],
        $layout['barcode_xres'],
        ['stretch' => true],
        'N'
    );

    return $backX + $cardBackW; // balikin posisi X paling kanan (buat batch layout)
}

/**
 * Tentukan nama yang dipakai di kartu berdasarkan pilihan cetak (panjang/pendek).
 * $mode: 'idcard' (default, nama pendek utk ID card) | 'lengkap' (nama lengkap penuh)
 */
function resolvePrintName(array $karyawan, $mode)
{
    if ($mode === 'lengkap' && !empty($karyawan['NamaLengkap'])) {
        return $karyawan['NamaLengkap'];
    }
    if (!empty($karyawan['NamaIDCard'])) {
        return $karyawan['NamaIDCard'];
    }
    return $karyawan['NamaLengkap'] ?? '';
}