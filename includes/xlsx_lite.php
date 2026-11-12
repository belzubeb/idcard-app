<?php
/**
 * includes/xlsx_lite.php
 * Baca & tulis file .xlsx sederhana TANPA library luar (cuma pakai ZipArchive +
 * SimpleXML bawaan PHP). Cukup buat kebutuhan import/export data karyawan
 * (satu sheet, header di baris pertama, isinya teks/angka biasa).
 */

/**
 * Baca file .xlsx dan balikin array baris (array of assoc, key = header kolom).
 * Baris pertama dianggap header.
 *
 * @return array|false
 */
function xlsxLiteRead(string $filePath)
{
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) return false;

    // ---- shared strings ----
    $shared = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $ss = @simplexml_load_string($ssXml);
        if ($ss !== false) {
            foreach ($ss->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string) $si->t;
                } else {
                    // rich text (banyak <r><t>) digabung
                    $text = '';
                    foreach ($si->r as $r) {
                        $text .= (string) $r->t;
                    }
                    $shared[] = $text;
                }
            }
        }
    }

    // ---- cari sheet pertama ----
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        // fallback: cari file sheet apapun di folder worksheets
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $sheetXml = $zip->getFromName($name);
                break;
            }
        }
    }
    $zip->close();
    if ($sheetXml === false) return false;

    $sheet = @simplexml_load_string($sheetXml);
    if ($sheet === false) return false;

    $rowsRaw = [];
    $maxCol = 0;
    foreach ($sheet->sheetData->row as $row) {
        $rowIndex = (int) $row['r'];
        $cells = [];
        foreach ($row->c as $c) {
            $ref = (string) $c['r']; // e.g. "C5"
            preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
            $colLetters = $m[1] ?? '';
            $colIndex = xlsxLiteColToIndex($colLetters);
            $maxCol = max($maxCol, $colIndex);

            $type = (string) $c['t'];
            $value = '';
            if (isset($c->v)) {
                $raw = (string) $c->v;
                if ($type === 's') {
                    $value = $shared[(int) $raw] ?? '';
                } elseif ($type === 'str' || $type === 'inlineStr') {
                    $value = $raw;
                } else {
                    $value = $raw; // number
                }
            } elseif (isset($c->is->t)) {
                $value = (string) $c->is->t;
            }
            $cells[$colIndex] = $value;
        }
        $rowsRaw[$rowIndex] = $cells;
    }

    if (empty($rowsRaw)) return [];

    ksort($rowsRaw);
    $rowNums = array_keys($rowsRaw);
    $headerRowNum = $rowNums[0];
    $headerCells = $rowsRaw[$headerRowNum];

    $headers = [];
    for ($c = 0; $c <= $maxCol; $c++) {
        $h = trim((string) ($headerCells[$c] ?? ''));
        if ($h === '') $h = 'col' . $c;
        $headers[$c] = $h;
    }

    $result = [];
    foreach ($rowsRaw as $rNum => $cells) {
        if ($rNum === $headerRowNum) continue;
        $assoc = [];
        $hasValue = false;
        for ($c = 0; $c <= $maxCol; $c++) {
            $v = trim((string) ($cells[$c] ?? ''));
            if ($v !== '') $hasValue = true;
            $assoc[$headers[$c]] = $v;
        }
        if ($hasValue) $result[] = $assoc;
    }

    return $result;
}

function xlsxLiteColToIndex(string $letters): int
{
    $letters = strtoupper($letters);
    $idx = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $idx = $idx * 26 + (ord($letters[$i]) - ord('A') + 1);
    }
    return $idx - 1; // 0-based
}

function xlsxLiteIndexToCol(int $index): string
{
    $index++; // 1-based
    $letters = '';
    while ($index > 0) {
        $rem = ($index - 1) % 26;
        $letters = chr(65 + $rem) . $letters;
        $index = intdiv($index - 1, 26);
    }
    return $letters;
}

/**
 * Buat file .xlsx sederhana dari array data.
 *
 * @param string $filePath   tujuan file output
 * @param array  $headers    daftar nama kolom, mis. ['NIK','NamaLengkap',...]
 * @param array  $rows       array of array (urutan sesuai $headers), boleh kosong
 * @param array  $sheets     opsional: sheet tambahan [ ['title'=>'Referensi','headers'=>[...], 'rows'=>[...]] ]
 */
function xlsxLiteWrite(string $filePath, array $headers, array $rows, array $extraSheets = []): bool
{
    if (file_exists($filePath)) @unlink($filePath);
    $zip = new ZipArchive();
    if ($zip->open($filePath, ZipArchive::CREATE) !== true) return false;

    $allSheets = array_merge(
        [['title' => 'Data', 'headers' => $headers, 'rows' => $rows]],
        $extraSheets
    );

    $zip->addFromString('[Content_Types].xml', xlsxLiteContentTypes(count($allSheets)));
    $zip->addFromString('_rels/.rels', xlsxLiteRootRels());
    $zip->addFromString('xl/_rels/workbook.xml.rels', xlsxLiteWorkbookRels(count($allSheets)));
    $zip->addFromString('xl/workbook.xml', xlsxLiteWorkbookXml($allSheets));
    $zip->addFromString('xl/styles.xml', xlsxLiteStylesXml());

    foreach ($allSheets as $i => $sheetDef) {
        $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', xlsxLiteSheetXml($sheetDef['headers'], $sheetDef['rows']));
    }

    $zip->close();
    return true;
}

function xlsxLiteContentTypes(int $sheetCount): string
{
    $overrides = '';
    for ($i = 1; $i <= $sheetCount; $i++) {
        $overrides .= "<Override PartName=\"/xl/worksheets/sheet{$i}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
    }
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
        '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
        $overrides .
        '</Types>';
}

function xlsxLiteRootRels(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
        '</Relationships>';
}

function xlsxLiteWorkbookRels(int $sheetCount): string
{
    $rels = '';
    for ($i = 1; $i <= $sheetCount; $i++) {
        $rels .= "<Relationship Id=\"rId{$i}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$i}.xml\"/>";
    }
    $stylesId = $sheetCount + 1;
    $rels .= "<Relationship Id=\"rId{$stylesId}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/>";
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>';
}

function xlsxLiteWorkbookXml(array $sheets): string
{
    $sheetTags = '';
    foreach ($sheets as $i => $s) {
        $name = htmlspecialchars($s['title'], ENT_XML1);
        $sheetTags .= '<sheet name="' . $name . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
    }
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<sheets>' . $sheetTags . '</sheets></workbook>';
}

function xlsxLiteStylesXml(): string
{
    // style index 1 = bold header dengan background abu-abu muda
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>' .
        '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>' .
        '<fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/><bgColor indexed="64"/></patternFill></fill></fills>' .
        '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
        '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
        '<cellXfs count="2">' .
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .
        '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>' .
        '</cellXfs></styleSheet>';
}

function xlsxLiteSheetXml(array $headers, array $rows): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<sheetData>';

    // header row (style 1 = bold)
    $xml .= '<row r="1">';
    foreach ($headers as $i => $h) {
        $col = xlsxLiteIndexToCol($i);
        $xml .= '<c r="' . $col . '1" t="inlineStr" s="1"><is><t>' . htmlspecialchars((string) $h, ENT_XML1) . '</t></is></c>';
    }
    $xml .= '</row>';

    $rNum = 2;
    foreach ($rows as $row) {
        $xml .= '<row r="' . $rNum . '">';
        foreach (array_values($row) as $i => $val) {
            $col = xlsxLiteIndexToCol($i);
            // Selalu simpan sebagai teks (bukan angka) supaya NIK/kode dengan angka nol
            // di depan atau digit panjang tidak berubah/dibulatkan oleh Excel.
            $xml .= '<c r="' . $col . $rNum . '" t="inlineStr"><is><t>' . htmlspecialchars((string) $val, ENT_XML1) . '</t></is></c>';
        }
        $xml .= '</row>';
        $rNum++;
    }

    $xml .= '</sheetData></worksheet>';
    return $xml;
}
