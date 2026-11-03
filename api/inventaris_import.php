<?php
/**
 * IMPOR KARTU INVENTARIS DARI EXCEL / CSV (KOMBAI TEAL DESIGN SYSTEM)
 * Aturan Utama: Data yang sudah ada TIDAK DITIMPA (dilewati otomatis).
 * Hanya data inventaris baru yang belum terdaftar yang akan dibuatkan kartu inventarisnya.
 */
require __DIR__ . '/bootstrap.php';
require_login();

$flash = $_SESSION['flash'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_error']);

// -------------------------------------------------------------
// 1. FITUR UNDUH TEMPLATE EXCEL / CSV
// -------------------------------------------------------------
if (isset($_GET['download_template'])) {
    $tplFormat = strtolower(trim((string)$_GET['download_template']));
    $dateStr = date('Ymd');

    $headers = [
        'No Sistem',
        'Nama Aktiva',
        'Tanggal Perolehan',
        'Ruangan',
        'Lokasi / Cabang',
        'Kode Barcode',
        'Nomor Seri Inventaris',
        'Jenis Inventaris',
        'Golongan',
        'Keterangan'
    ];

    $sampleRows = [
        [
            '01.05.0495',
            'AC SPLIT 1.5 PK DAIKIN',
            '2026-10-15',
            'Ruang Pelayanan / Teller',
            'Kantor Pusat (KPO)',
            '',
            '',
            '3 : PERALATAN KANTOR',
            '02 : GOLONGAN I',
            'Pengadaan unit pendingin baru teller'
        ],
        [
            '01.05.0496',
            'KURSI KERJA ERGONOMIS STAFF',
            '2026-10-20',
            'Ruang IT / Server',
            'Kantor Pusat (KPO)',
            '',
            '',
            '3 : PERALATAN KANTOR',
            '02 : GOLONGAN I',
            'Kursi kerja operasional staf IT'
        ],
        [
            '02.05.0112',
            'FILING CABINET 4 LACI BESI',
            '2026-10-22',
            'Ruang Arsip & Berkas',
            'Cabang Batulicin',
            '',
            '',
            '3 : PERALATAN KANTOR',
            '02 : GOLONGAN I',
            'Lemari penyimpanan berkas kredit'
        ]
    ];

    if ($tplFormat === 'csv' || !class_exists('ZipArchive')) {
        $filename = "Template_Import_Kartu_Inventaris_{$dateStr}.csv";
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM
        fputcsv($out, $headers, ';');
        foreach ($sampleRows as $row) {
            fputcsv($out, $row, ';');
        }
        fclose($out);
        exit;
    }

    // Generator XLSX Asli Native (Zero External Dependencies)
    $filename = "Template_Import_Kartu_Inventaris_{$dateStr}.xlsx";
    $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_tmpl_kartu_');
    $zip = new ZipArchive();
    $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Template Kartu Inventaris" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>');

    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="10.5"/><name val="Segoe UI"/></font>
    <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Segoe UI"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF0E5C58"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color rgb="FFD1D5D8"/></left>
      <right style="thin"><color rgb="FFD1D5D8"/></right>
      <top style="thin"><color rgb="FFD1D5D8"/></top>
      <bottom style="thin"><color rgb="FFD1D5D8"/></bottom>
      <diagonal/>
    </border>
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="3">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">
      <alignment vertical="center"/>
    </xf>
  </cellXfs>
</styleSheet>');

    // Susun Sheet1 dengan Header & Sample Data
    $colsConfig = [
        1 => 18, 2 => 36, 3 => 18, 4 => 26, 5 => 24,
        6 => 20, 7 => 24, 8 => 24, 9 => 20, 10 => 32
    ];
    $colsXml = '<cols>';
    foreach ($colsConfig as $cIdx => $w) {
        $colsXml .= '<col min="' . $cIdx . '" max="' . $cIdx . '" width="' . $w . '" customWidth="1"/>';
    }
    $colsXml .= '</cols>';

    $sheetDataXml = '<sheetData>';
    
    // Header Row (Row 1)
    $sheetDataXml .= '<row r="1" ht="28" customHeight="1">';
    $colLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
    foreach ($headers as $idx => $hText) {
        $ref = $colLetters[$idx] . '1';
        $sheetDataXml .= '<c r="' . $ref . '" s="1" t="inlineStr"><is><t>' . htmlspecialchars($hText, ENT_XML1, 'UTF-8') . '</t></is></c>';
    }
    $sheetDataXml .= '</row>';

    // Sample Data Rows (Row 2 .. 4)
    $rNum = 2;
    foreach ($sampleRows as $row) {
        $sheetDataXml .= '<row r="' . $rNum . '" ht="22" customHeight="1">';
        foreach ($row as $idx => $val) {
            $ref = $colLetters[$idx] . $rNum;
            $sheetDataXml .= '<c r="' . $ref . '" s="2" t="inlineStr"><is><t>' . htmlspecialchars((string)$val, ENT_XML1, 'UTF-8') . '</t></is></c>';
        }
        $sheetDataXml .= '</row>';
        $rNum++;
    }
    $sheetDataXml .= '</sheetData>';

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  ' . $colsXml . '
  ' . $sheetDataXml . '
</worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tempFile));
    header('Cache-Control: max-age=0, no-cache, must-revalidate');
    header('Pragma: public');
    readfile($tempFile);
    @unlink($tempFile);
    exit;
}

// -------------------------------------------------------------
// 2. HELPER PARSER XLSX & CSV
// -------------------------------------------------------------
function xlsx_col_to_index(string $cellRef): int {
    if (preg_match('/^([A-Z]+)/i', $cellRef, $matches)) {
        $letters = strtoupper($matches[1]);
        $len = strlen($letters);
        $num = 0;
        for ($i = 0; $i < $len; $i++) {
            $num = $num * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return $num - 1;
    }
    return -1;
}

function parse_uploaded_csv(string $filepath): array {
    if (!file_exists($filepath)) return [];
    $firstLine = '';
    $f = @fopen($filepath, 'r');
    if ($f) {
        $firstLine = (string)fgets($f);
        fclose($f);
    }
    $delim = (strpos($firstLine, ';') !== false) ? ';' : ',';
    $handle = fopen($filepath, 'r');
    if (!$handle) return [];

    $bomCheck = fread($handle, 3);
    if ($bomCheck !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $rows = [];
    while (($data = fgetcsv($handle, 8192, $delim)) !== false) {
        $trimmed = array_map(function($v) {
            return trim((string)$v);
        }, $data);
        if (implode('', $trimmed) === '') continue;
        $rows[] = $trimmed;
    }
    fclose($handle);
    return $rows;
}

function parse_uploaded_xlsx(string $filepath): array {
    if (!class_exists('ZipArchive')) return [];
    $zip = new ZipArchive();
    if ($zip->open($filepath) !== true) return [];

    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $xml = @simplexml_load_string($ssXml);
        if ($xml && isset($xml->si)) {
            foreach ($xml->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } elseif (isset($si->r)) {
                    $txt = '';
                    foreach ($si->r as $r) {
                        $txt .= (string)($r->t ?? '');
                    }
                    $sharedStrings[] = $txt;
                } else {
                    $sharedStrings[] = '';
                }
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $rows = [];
    if ($sheetXml !== false) {
        $xml = @simplexml_load_string($sheetXml);
        if ($xml && isset($xml->sheetData->row)) {
            foreach ($xml->sheetData->row as $r) {
                $rowCells = [];
                $maxCol = 0;
                foreach ($r->c as $c) {
                    $cRef = (string)($c['r'] ?? '');
                    $colIdx = xlsx_col_to_index($cRef);
                    if ($colIdx < 0) $colIdx = $maxCol;

                    $type = (string)($c['t'] ?? '');
                    $val = '';
                    if ($type === 's') {
                        $sIdx = (int)($c->v ?? 0);
                        $val = $sharedStrings[$sIdx] ?? '';
                    } elseif ($type === 'inlineStr') {
                        if (isset($c->is->t)) {
                            $val = (string)$c->is->t;
                        } elseif (isset($c->is->r)) {
                            foreach ($c->is->r as $ir) {
                                $val .= (string)($ir->t ?? '');
                            }
                        }
                    } else {
                        $val = (string)($c->v ?? '');
                    }

                    $rowCells[$colIdx] = trim($val);
                    if ($colIdx >= $maxCol) {
                        $maxCol = $colIdx + 1;
                    }
                }

                if ($maxCol > 0) {
                    $fullRow = [];
                    $hasAnyContent = false;
                    for ($i = 0; $i < $maxCol; $i++) {
                        $cellVal = $rowCells[$i] ?? '';
                        $fullRow[$i] = $cellVal;
                        if ($cellVal !== '') $hasAnyContent = true;
                    }
                    if ($hasAnyContent) {
                        $rows[] = $fullRow;
                    }
                }
            }
        }
    }
    $zip->close();
    return $rows;
}

// -------------------------------------------------------------
// 3. LOGIKA DETEKSI KARTU YANG SUDAH ADA (JANGAN DITIMPA)
// -------------------------------------------------------------
function build_existing_cards_index(): array {
    $existing = get_inventaris_kartu_rows(true);
    $byRek = [];
    $bySerial = [];
    $byNameDate = [];

    foreach ($existing as $c) {
        $rek = trim((string)($c['nomor_rekening'] ?? ''));
        if ($rek !== '') {
            $byRek[strtolower($rek)] = $c;
            $cleanRek = strtolower(preg_replace('/[^0-9a-z]/i', '', $rek));
            if ($cleanRek !== '') {
                $byRek[$cleanRek] = $c;
            }
        }

        $sn = trim((string)($c['nomor_seri_inventaris'] ?? ''));
        if ($sn !== '') {
            $bySerial[strtolower($sn)] = $c;
        }

        $nama = strtolower(trim((string)($c['nama_barang'] ?? '')));
        $tgl = normalize_date_to_db($c['tanggal_perolehan'] ?? '');
        if ($nama !== '' && $tgl !== '') {
            $byNameDate[$nama . '|' . $tgl] = $c;
        }
    }

    return [
        'all' => $existing,
        'by_rek' => $byRek,
        'by_serial' => $bySerial,
        'by_name_date' => $byNameDate
    ];
}

// -------------------------------------------------------------
// 4. PENANGANAN POST: PREVIEW & KONFIRMASI IMPOR
// -------------------------------------------------------------
$previewData = null;
$stats = [
    'total_rows' => 0,
    'new_count' => 0,
    'skipped_count' => 0,
    'invalid_count' => 0
];

// TAHAP 1: Unggah & Pratinjau File
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'preview_file') {
    verify_csrf();

    if (empty($_FILES['file_excel']['tmp_name']) || !is_uploaded_file($_FILES['file_excel']['tmp_name'])) {
        $_SESSION['flash_error'] = 'Pilih file Excel (.xlsx) atau CSV (.csv) terlebih dahulu.';
        redirect(module_url('inventaris_import.php'));
    }

    $fileName = $_FILES['file_excel']['name'] ?? '';
    $tmpPath = $_FILES['file_excel']['tmp_name'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $rawRows = [];
    if ($ext === 'xlsx') {
        $rawRows = parse_uploaded_xlsx($tmpPath);
    } else {
        $rawRows = parse_uploaded_csv($tmpPath);
    }

    if (empty($rawRows) || count($rawRows) < 2) {
        $_SESSION['flash_error'] = 'File yang diunggah kosong atau tidak memiliki baris data kartu inventaris.';
        redirect(module_url('inventaris_import.php'));
    }

    $headerRow = array_map('strtolower', $rawRows[0]);
    $dataRows = array_slice($rawRows, 1);

    // Pemetaan Indeks Kolom Cerdas
    $colMap = [
        'nomor_rekening' => -1,
        'nama_barang' => -1,
        'tanggal_perolehan' => -1,
        'ruangan' => -1,
        'lokasi' => -1,
        'barcode_data' => -1,
        'nomor_seri_inventaris' => -1,
        'jenis_inventaris' => -1,
        'golongan' => -1,
        'sumber_perolehan' => -1,
        'status_inventaris' => -1,
        'keterangan' => -1
    ];

    foreach ($headerRow as $idx => $h) {
        $cleanH = strtolower(trim($h));
        if ($cleanH === '') continue;

        if (strpos($cleanH, 'seri') !== false || strpos($cleanH, 'sn') !== false || strpos($cleanH, 'serial') !== false) {
            $colMap['nomor_seri_inventaris'] = $idx;
        } elseif (strpos($cleanH, 'barcode') !== false || strpos($cleanH, 'qr') !== false || strpos($cleanH, 'gabungan') !== false) {
            $colMap['barcode_data'] = $idx;
        } elseif (strpos($cleanH, 'rekening') !== false || strpos($cleanH, 'no sistem') !== false || strpos($cleanH, 'nosistem') !== false || strpos($cleanH, 'kode') !== false) {
            $colMap['nomor_rekening'] = $idx;
        } elseif (strpos($cleanH, 'nama') !== false || strpos($cleanH, 'aktiva') !== false || strpos($cleanH, 'barang') !== false || strpos($cleanH, 'deskripsi') !== false) {
            $colMap['nama_barang'] = $idx;
        } elseif (strpos($cleanH, 'tanggal') !== false || strpos($cleanH, 'tgl') !== false || strpos($cleanH, 'perolehan') !== false || strpos($cleanH, 'beli') !== false) {
            $colMap['tanggal_perolehan'] = $idx;
        } elseif (strpos($cleanH, 'ruang') !== false) {
            $colMap['ruangan'] = $idx;
        } elseif (strpos($cleanH, 'lokasi') !== false || strpos($cleanH, 'cabang') !== false || strpos($cleanH, 'kantor') !== false) {
            $colMap['lokasi'] = $idx;
        } elseif (strpos($cleanH, 'jenis') !== false || strpos($cleanH, 'kategori') !== false) {
            $colMap['jenis_inventaris'] = $idx;
        } elseif (strpos($cleanH, 'golongan') !== false || strpos($cleanH, 'gol') !== false) {
            $colMap['golongan'] = $idx;
        } elseif (strpos($cleanH, 'sumber') !== false) {
            $colMap['sumber_perolehan'] = $idx;
        } elseif (strpos($cleanH, 'status') !== false) {
            $colMap['status_inventaris'] = $idx;
        } elseif (strpos($cleanH, 'keterangan') !== false || strpos($cleanH, 'ket') !== false || strpos($cleanH, 'catatan') !== false) {
            $colMap['keterangan'] = $idx;
        }
    }

    // Fallback jika header tidak terdeteksi
    if ($colMap['nomor_rekening'] === -1) $colMap['nomor_rekening'] = 0;
    if ($colMap['nama_barang'] === -1) $colMap['nama_barang'] = 1;
    if ($colMap['tanggal_perolehan'] === -1) $colMap['tanggal_perolehan'] = 2;
    if ($colMap['ruangan'] === -1) $colMap['ruangan'] = 3;
    if ($colMap['lokasi'] === -1) $colMap['lokasi'] = 4;

    $cardsIndex = build_existing_cards_index();
    $previewRows = [];

    foreach ($dataRows as $rIdx => $row) {
        $rawRek = trim((string)($row[$colMap['nomor_rekening']] ?? ''));
        $rawNama = trim((string)($row[$colMap['nama_barang']] ?? ''));
        $rawTgl = trim((string)($row[$colMap['tanggal_perolehan']] ?? ''));
        $rawRuangan = ($colMap['ruangan'] >= 0) ? trim((string)($row[$colMap['ruangan']] ?? '')) : '';
        $rawLokasi = ($colMap['lokasi'] >= 0) ? trim((string)($row[$colMap['lokasi']] ?? '')) : '';
        $rawBarcode = ($colMap['barcode_data'] >= 0) ? trim((string)($row[$colMap['barcode_data']] ?? '')) : '';
        $rawSn = ($colMap['nomor_seri_inventaris'] >= 0) ? trim((string)($row[$colMap['nomor_seri_inventaris']] ?? '')) : '';
        $rawJenis = ($colMap['jenis_inventaris'] >= 0) ? trim((string)($row[$colMap['jenis_inventaris']] ?? '')) : '';
        $rawGol = ($colMap['golongan'] >= 0) ? trim((string)($row[$colMap['golongan']] ?? '')) : '';
        $rawSumber = ($colMap['sumber_perolehan'] >= 0) ? trim((string)($row[$colMap['sumber_perolehan']] ?? '')) : '';
        $rawStatus = ($colMap['status_inventaris'] >= 0) ? trim((string)($row[$colMap['status_inventaris']] ?? '')) : '';
        $rawKet = ($colMap['keterangan'] >= 0) ? trim((string)($row[$colMap['keterangan']] ?? '')) : '';

        // Abaikan jika baris kosong total
        if ($rawRek === '' && $rawNama === '' && $rawTgl === '') {
            continue;
        }

        $stats['total_rows']++;

        if ($rawNama === '') {
            $stats['invalid_count']++;
            $previewRows[] = [
                'status' => 'invalid',
                'reason' => 'Nama barang tidak boleh kosong',
                'data' => [
                    'nomor_rekening' => $rawRek,
                    'nama_barang' => $rawNama,
                    'tanggal_perolehan' => $rawTgl,
                    'ruangan' => $rawRuangan,
                    'lokasi' => $rawLokasi,
                    'barcode_data' => $rawBarcode,
                    'nomor_seri_inventaris' => $rawSn
                ]
            ];
            continue;
        }

        // Cek duplikasi dengan data yang sudah ada di sistem
        $isExisting = false;
        $matchedInfo = '';
        $cleanRek = strtolower(preg_replace('/[^0-9a-z]/i', '', $rawRek));

        if ($cleanRek !== '' && isset($cardsIndex['by_rek'][$cleanRek])) {
            $isExisting = true;
            $exist = $cardsIndex['by_rek'][$cleanRek];
            $matchedInfo = "Nomor Rekening '{$rawRek}' sudah terdaftar (Kartu #{$exist['id']} - {$exist['nama_barang']})";
        } elseif ($rawRek !== '' && isset($cardsIndex['by_rek'][strtolower($rawRek)])) {
            $isExisting = true;
            $exist = $cardsIndex['by_rek'][strtolower($rawRek)];
            $matchedInfo = "Nomor Rekening '{$rawRek}' sudah terdaftar (Kartu #{$exist['id']})";
        } elseif ($rawSn !== '' && isset($cardsIndex['by_serial'][strtolower($rawSn)])) {
            $isExisting = true;
            $exist = $cardsIndex['by_serial'][strtolower($rawSn)];
            $matchedInfo = "Nomor Seri '{$rawSn}' sudah terdaftar (Kartu #{$exist['id']})";
        } else {
            $normTgl = normalize_date_to_db($rawTgl);
            $keyNameDate = strtolower($rawNama) . '|' . $normTgl;
            if ($rawTgl !== '' && isset($cardsIndex['by_name_date'][$keyNameDate])) {
                $isExisting = true;
                $exist = $cardsIndex['by_name_date'][$keyNameDate];
                $matchedInfo = "Barang & tanggal perolehan sudah terdaftar (Kartu #{$exist['id']})";
            }
        }

        if ($isExisting) {
            $stats['skipped_count']++;
            $previewRows[] = [
                'status' => 'skipped',
                'reason' => $matchedInfo . ' &mdash; <strong>Dilewati (Tidak Ditimpa)</strong>',
                'data' => [
                    'nomor_rekening' => $rawRek,
                    'nama_barang' => $rawNama,
                    'tanggal_perolehan' => $rawTgl,
                    'ruangan' => $rawRuangan,
                    'lokasi' => $rawLokasi,
                    'barcode_data' => $rawBarcode,
                    'nomor_seri_inventaris' => $rawSn,
                    'jenis_inventaris' => $rawJenis,
                    'golongan' => $rawGol,
                    'sumber_perolehan' => $rawSumber,
                    'status_inventaris' => $rawStatus,
                    'keterangan' => $rawKet
                ]
            ];
        } else {
            $stats['new_count']++;
            $previewRows[] = [
                'status' => 'new',
                'reason' => 'Baru &mdash; <strong>Akan dibuatkan kartu inventaris</strong>',
                'data' => [
                    'nomor_rekening' => $rawRek,
                    'nama_barang' => $rawNama,
                    'tanggal_perolehan' => $rawTgl,
                    'ruangan' => $rawRuangan,
                    'lokasi' => $rawLokasi,
                    'barcode_data' => $rawBarcode,
                    'nomor_seri_inventaris' => $rawSn,
                    'jenis_inventaris' => $rawJenis ?: '3 : PERALATAN KANTOR',
                    'golongan' => $rawGol ?: '02 : GOLONGAN I',
                    'sumber_perolehan' => $rawSumber ?: '02 : MODAL DISETOR',
                    'status_inventaris' => $rawStatus ?: '1 : Aktif',
                    'keterangan' => $rawKet ?: $rawNama
                ]
            ];
        }
    }

    $previewData = $previewRows;
}

// TAHAP 2: Konfirmasi Impor (Hanya baris baru yang dibuatkan kartu, data lama tidak ditimpa)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_import') {
    verify_csrf();

    $rawJson = (string)($_POST['rows_json'] ?? '');
    $rowsToImport = json_decode($rawJson, true);

    if (!is_array($rowsToImport) || empty($rowsToImport)) {
        $_SESSION['flash_error'] = 'Tidak ada baris data kartu yang dapat diimpor.';
        redirect(module_url('inventaris_import.php'));
    }

    $cardsIndex = build_existing_cards_index();
    $createdCount = 0;
    $skippedCount = 0;

    foreach ($rowsToImport as $row) {
        $rek = trim((string)($row['nomor_rekening'] ?? ''));
        $nama = trim((string)($row['nama_barang'] ?? ''));
        $tgl = trim((string)($row['tanggal_perolehan'] ?? ''));

        if ($nama === '') {
            continue;
        }

        // Cek ulang untuk menjamin 100% data lama TIDAK DITIMPA
        $cleanRek = strtolower(preg_replace('/[^0-9a-z]/i', '', $rek));
        $isExisting = false;

        if ($cleanRek !== '' && isset($cardsIndex['by_rek'][$cleanRek])) {
            $isExisting = true;
        } elseif ($rek !== '' && isset($cardsIndex['by_rek'][strtolower($rek)])) {
            $isExisting = true;
        } else {
            $normTgl = normalize_date_to_db($tgl);
            $keyNameDate = strtolower($nama) . '|' . $normTgl;
            if ($tgl !== '' && isset($cardsIndex['by_name_date'][$keyNameDate])) {
                $isExisting = true;
            }
        }

        if ($isExisting) {
            $skippedCount++;
            continue;
        }

        // Buat kartu inventaris baru
        $res = insert_inventaris_kartu([
            'nomor_rekening' => $rek,
            'nama_barang' => $nama,
            'tanggal_perolehan' => $tgl ?: date('Y-m-d'),
            'lokasi' => $row['lokasi'] ?? '',
            'ruangan' => $row['ruangan'] ?? '',
            'barcode_data' => $row['barcode_data'] ?? '',
            'nomor_seri_inventaris' => $row['nomor_seri_inventaris'] ?? '',
            'jenis_inventaris' => $row['jenis_inventaris'] ?? '3 : PERALATAN KANTOR',
            'golongan' => $row['golongan'] ?? '02 : GOLONGAN I',
            'sumber_perolehan' => $row['sumber_perolehan'] ?? '02 : MODAL DISETOR',
            'status_inventaris' => $row['status_inventaris'] ?? '1 : Aktif',
            'keterangan' => $row['keterangan'] ?? $nama
        ]);

        if (!empty($res['success'])) {
            $createdCount++;
            // Update cache lokal agar baris duplikat di dalam file yang sama juga tidak ganda
            if ($cleanRek !== '') $cardsIndex['by_rek'][$cleanRek] = true;
            $normTgl = normalize_date_to_db($tgl);
            if ($tgl !== '') $cardsIndex['by_name_date'][strtolower($nama) . '|' . $normTgl] = true;
        }
    }

    if (function_exists('record_audit_log')) {
        record_audit_log(
            'IMPORT_KARTU_INVENTARIS',
            'KARTU_INVENTARIS',
            current_user_id(),
            current_user_name(),
            "Impor Excel Kartu: {$createdCount} kartu inventaris baru dibuatkan. {$skippedCount} kartu yang sudah ada dilewati (tidak ditimpa)."
        );
    }

    $_SESSION['flash'] = "Impor Selesai: <strong>{$createdCount} kartu inventaris baru</strong> berhasil dibuatkan ke sistem. Sebanyak <strong>{$skippedCount} data yang sudah ada dilewati</strong> dan tidak ditimpa.";
    redirect(module_url('dashboard.php', ['tab' => 'kartu']));
}

// Data total kartu untuk metrik
$allCurrentCards = get_inventaris_kartu_rows();
$totalCurrentCards = count($allCurrentCards);
$unprintedCardsCount = 0;
foreach ($allCurrentCards as $c) {
    if (empty($c['printed_at'])) {
        $unprintedCardsCount++;
    }
}
$printedCardsCount = $totalCurrentCards - $unprintedCardsCount;

ob_start();
?>
<style>
  .import-card {
    background: #FFFFFF;
    border: 1px solid #D1D5D8;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  }
  .drop-zone {
    border: 2px dashed #0E5C58;
    background: #F8FBFB;
    border-radius: 10px;
    padding: 32px 20px;
    text-align: center;
    transition: all 0.2s ease;
    cursor: pointer;
  }
  .drop-zone:hover, .drop-zone.dragover {
    background: #D9F1EF;
    border-color: #094340;
  }
  .preview-table-container {
    max-height: 520px;
    overflow-y: auto;
    border: 1px solid #D1D5D8;
    border-radius: 8px;
  }
</style>
<?php
$head = ob_get_clean();

ob_start();
?>
<!-- Breadcrumbs & Header Halaman -->
<div class="mb-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
  <div>
    <div class="text-[11px] font-bold text-[#0E5C58] uppercase tracking-wider mb-1 flex items-center gap-1.5">
      <i class="bi bi-credit-card-2-front"></i> SARPRAS &middot; KARTU INVENTARIS
    </div>
    <h1 class="text-[22px] font-bold text-[#1B2A3A] tracking-tight m-0">Impor Kartu Inventaris (Excel / CSV)</h1>
    <p class="text-[13px] text-[#53595F] mt-1 mb-0">
      Unggah file Excel untuk mendaftarkan kartu inventaris secara massal. Sistem secara cerdas <strong>hanya membuatkan kartu untuk data baru</strong>, dan <strong>tidak menimpa data yang sudah ada</strong>.
    </p>
  </div>
  <div class="flex flex-wrap items-center gap-2 shrink-0">
    <a href="<?= e(module_url('inventaris_import.php', ['download_template' => 'xlsx'])) ?>" class="h-[36px] px-3.5 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-semibold text-[#10B981] hover:bg-[#ECFDF5] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-file-earmark-excel-fill text-[#10B981]"></i> Unduh Template Excel (.xlsx)
    </a>
    <a href="<?= e(module_url('dashboard.php', ['tab' => 'kartu'])) ?>" class="h-[36px] px-3.5 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-semibold text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-arrow-left text-[#53595F]"></i> Kembali ke Kartu Inventaris
    </a>
  </div>
</div>

<?php if ($flash): ?>
  <div class="alert alert-success alert-dismissible fade show text-[13px] rounded-[8px] border-[#A7F3D0] bg-[#ECFDF5] text-[#065F46] p-3 mb-4 shadow-xs" role="alert">
    <div class="flex items-center gap-2">
      <i class="bi bi-check-circle-fill fs-5 text-[#10B981]"></i>
      <div><?= $flash ?></div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<?php if ($flashError): ?>
  <div class="alert alert-danger alert-dismissible fade show text-[13px] rounded-[8px] border-[#FECACA] bg-[#FEF2F2] text-[#991B1B] p-3 mb-4 shadow-xs" role="alert">
    <div class="flex items-center gap-2">
      <i class="bi bi-exclamation-triangle-fill fs-5 text-[#EF4444]"></i>
      <div><?= $flashError ?></div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<!-- 3 METRIC CARDS STATISTIK KARTU SAAT INI -->
<div class="bg-[#FFFFFF] border border-[#D1D5D8] rounded-[10px] flex flex-col sm:flex-row overflow-hidden shrink-0 mb-4 shadow-xs">
  <div class="flex-1 flex flex-col justify-center px-5 py-3 border-b sm:border-b-0 sm:border-r border-[#D1D5D8] relative hover:bg-[#F4F5F7] transition-colors">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0E5C58]"></div>
    <div class="text-[26px] font-bold text-[#1B2A3A] tabular-nums leading-none">
      <?= number_format($totalCurrentCards) ?> <span class="text-[13px] font-normal text-[#53595F]">Kartu</span>
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
      <span class="text-[#0E5C58] font-semibold"><i class="bi bi-credit-card-2-front-fill me-0.5"></i> Total Terdaftar</span>
      <span>&middot;</span>
      <span class="text-[#53595F] text-[11px]">Database Aktif</span>
    </div>
  </div>

  <div class="flex-1 flex flex-col justify-center px-5 py-3 border-b sm:border-b-0 sm:border-r border-[#D1D5D8] relative hover:bg-[#F4F5F7] transition-colors">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#F59E0B]"></div>
    <div class="text-[26px] font-bold text-[#D97706] tabular-nums leading-none">
      <?= number_format($unprintedCardsCount) ?> <span class="text-[13px] font-normal text-[#53595F]">Kartu</span>
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
      <span class="text-[#D97706] font-semibold"><i class="bi bi-printer me-0.5"></i> Belum Dicetak</span>
      <span>&middot;</span>
      <span class="text-[#53595F] text-[11px]">Menunggu Cetak A4</span>
    </div>
  </div>

  <div class="flex-1 flex flex-col justify-center px-5 py-3 relative hover:bg-[#F4F5F7] transition-colors">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#10B981]"></div>
    <div class="text-[26px] font-bold text-[#10B981] tabular-nums leading-none">
      <?= number_format($printedCardsCount) ?> <span class="text-[13px] font-normal text-[#53595F]">Kartu</span>
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
      <span class="text-[#10B981] font-semibold"><i class="bi bi-check-circle-fill me-0.5"></i> Sudah Dicetak</span>
      <span>&middot;</span>
      <span class="text-[#53595F] text-[11px]">Fisik CR80 Terbit</span>
    </div>
  </div>
</div>

<?php if ($previewData === null): ?>
  <!-- FORM UNGGAH FILE EXCEL / CSV -->
  <div class="import-card p-4 md:p-6 mb-4">
    <div class="row g-4 align-items-center">
      <div class="col-lg-7">
        <form method="post" enctype="multipart/form-data" id="formUploadExcel">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="preview_file">

          <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileExcelInput').click()">
            <i class="bi bi-cloud-arrow-up-fill text-[42px] text-[#0E5C58] mb-2 block"></i>
            <h5 class="text-[16px] font-bold text-[#1B2A3A] mb-1">Pilih atau Tarik File Excel / CSV ke Sini</h5>
            <p class="text-[12px] text-[#53595F] mb-3">Mendukung format Microsoft Excel (<strong>.xlsx</strong>, <strong>.xls</strong>) dan <strong>.csv</strong></p>
            <input type="file" name="file_excel" id="fileExcelInput" accept=".xlsx,.xls,.csv" class="d-none" onchange="handleFileSelected(this)">
            <button type="button" class="h-[36px] px-4 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[6px] text-[13px] font-semibold transition-colors inline-flex items-center gap-1.5 border-0 shadow-xs cursor-pointer">
              <i class="bi bi-folder2-open"></i> Cari File di Komputer
            </button>
            <div id="selectedFileName" class="mt-2 text-[12px] font-bold text-[#0E5C58] d-none"></div>
          </div>

          <div class="mt-3 flex items-center justify-between gap-3">
            <span class="text-[11px] text-[#53595F]">
              <i class="bi bi-shield-check text-[#0E5C58]"></i> Pemeriksaan duplikasi otomatis aktif: Data yang sudah ada tidak akan ditimpa.
            </span>
            <button type="submit" class="h-[38px] px-5 bg-[#10B981] hover:bg-[#059669] text-white rounded-[6px] text-[13px] font-bold transition-colors inline-flex items-center gap-1.5 border-0 shadow-xs cursor-pointer" id="btnProsesPreview" disabled>
              Pratinjau Data Impor <i class="bi bi-arrow-right"></i>
            </button>
          </div>
        </form>
      </div>

      <div class="col-lg-5 border-start-lg ps-lg-4">
        <div class="p-3.5 bg-[#F8F9FA] rounded-[8px] border border-[#E5E7EB] text-[12px]">
          <div class="font-bold text-[#1B2A3A] mb-2 flex items-center gap-1.5 text-[13px]">
            <i class="bi bi-info-circle-fill text-[#0E5C58]"></i> Aturan &amp; Ketentuan Impor Kartu:
          </div>
          <ul class="mb-3 ps-3 space-y-1.5 text-[#53595F]" style="line-height: 1.5;">
            <li><strong>Data yang sudah ada TIDAK DITIMPA</strong>: Sistem akan mencocokkan nomor rekening, nomor seri, atau nama barang + tanggal perolehan. Jika sudah ada, data lama tetap dipertahankan.</li>
            <li><strong>Hanya data baru yang dibuatkan kartu</strong>: Baris yang belum terdaftar otomatis dibuatkan kartu inventaris lengkap beserta QR code resmi.</li>
            <li><strong>Kolom Wajib</strong>: Minimal kolom <em>Nama Aktiva / Nama Barang</em> terisi. Kolom <em>No Sistem</em> dan <em>Tanggal Perolehan</em> sangat dianjurkan.</li>
            <li><strong>Barcode &amp; Nomor Seri Otomatis</strong>: Jika kolom barcode atau nomor seri kosong, sistem akan otomatis menerbitkan QR link canonical dan nomor seri unik.</li>
          </ul>

          <div class="flex flex-wrap gap-2 pt-2 border-t border-[#E5E7EB]">
            <a href="<?= e(module_url('inventaris_import.php', ['download_template' => 'xlsx'])) ?>" class="min-h-[44px] md:min-h-[36px] px-2.5 bg-white border border-[#D1D5D8] rounded-[6px] text-[11px] font-semibold text-[#10B981] hover:bg-[#ECFDF5] transition-colors inline-flex items-center gap-1 text-decoration-none">
              <i class="bi bi-file-earmark-excel"></i> Template .xlsx
            </a>
            <a href="<?= e(module_url('inventaris_import.php', ['download_template' => 'csv'])) ?>" class="min-h-[44px] md:min-h-[36px] px-2.5 bg-white border border-[#D1D5D8] rounded-[6px] text-[11px] font-semibold text-[#53595F] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1 text-decoration-none">
              <i class="bi bi-file-earmark-text"></i> Template .csv
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- TAHAP 2: PRATINJAU DATA SEBELUM DISIMPAN KE SISTEM -->
  <div class="import-card p-4 md:p-6 mb-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 mb-3 border-b border-[#E5E7EB]">
      <div>
        <h4 class="text-[17px] font-bold text-[#1B2A3A] m-0 flex items-center gap-2">
          <i class="bi bi-table text-[#0E5C58]"></i> Hasil Analisis &amp; Pratinjau File
        </h4>
        <p class="text-[12px] text-[#53595F] mt-1 mb-0">
          Periksa hasil deteksi di bawah ini. Baris <strong>Hijau</strong> akan dibuatkan kartu inventaris baru, sedangkan baris <strong>Kuning</strong> dilewati (tidak ditimpa).
        </p>
      </div>
      <div class="flex items-center gap-2">
        <a href="<?= e(module_url('inventaris_import.php')) ?>" class="min-h-[44px] md:min-h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] font-medium text-[#53595F] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1 text-decoration-none">
          <i class="bi bi-arrow-left"></i> Ganti File Lain
        </a>
      </div>
    </div>

    <!-- Ringkasan Statistik Pratinjau -->
    <div class="flex flex-wrap items-center gap-2 mb-3 text-[12px]">
      <span class="px-3 py-1.5 rounded-full font-bold bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC]">
        Total Baris di File: <?= number_format($stats['total_rows']) ?>
      </span>
      <span class="px-3 py-1.5 rounded-full font-bold bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0]">
        <i class="bi bi-check-circle-fill me-1"></i> Baru (Akan Dibuat): <?= number_format($stats['new_count']) ?>
      </span>
      <span class="px-3 py-1.5 rounded-full font-bold bg-[#FEF3C7] text-[#92400E] border border-[#FCD34D]">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> Sudah Ada (Dilewati / Tidak Ditimpa): <?= number_format($stats['skipped_count']) ?>
      </span>
      <?php if ($stats['invalid_count'] > 0): ?>
        <span class="px-3 py-1.5 rounded-full font-bold bg-[#FEE2E2] text-[#991B1B] border border-[#FECACA]">
          <i class="bi bi-x-circle-fill me-1"></i> Tidak Valid: <?= number_format($stats['invalid_count']) ?>
        </span>
      <?php endif; ?>
    </div>

    <!-- Tabel Pratinjau Baris -->
    <div class="preview-table-container mb-4">
      <table class="table table-hover align-middle mb-0 text-[12px]">
        <thead class="table-light sticky-top" style="border-bottom: 2px solid #D1D5D8; z-index: 2;">
          <tr class="text-nowrap text-[#1B2A3A] font-bold">
            <th style="width: 45px;" class="text-center">No</th>
            <th style="width: 170px;">Status Impor</th>
            <th style="width: 130px;">No Sistem</th>
            <th>Nama Aktiva / Barang</th>
            <th style="width: 110px;">Tgl Perolehan</th>
            <th style="width: 160px;">Ruangan</th>
            <th style="width: 160px;">Lokasi / Cabang</th>
            <th style="width: 200px;">Keterangan &amp; Alasan</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $rowNum = 0;
          $importableRows = [];
          foreach ($previewData as $pRow): 
            $rowNum++;
            $st = $pRow['status'];
            $d = $pRow['data'];
            if ($st === 'new') {
                $importableRows[] = $d;
                $rowClass = 'bg-[#F0FDF4]';
                $badgeHtml = '<span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#DCFCE7] text-[#15803D] border border-[#86EFAC] inline-flex items-center gap-1"><i class="bi bi-plus-circle-fill"></i> Baru</span>';
            } elseif ($st === 'skipped') {
                $rowClass = 'bg-[#FFFBEB]';
                $badgeHtml = '<span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#FEF3C7] text-[#B45309] border border-[#FDE68A] inline-flex items-center gap-1"><i class="bi bi-shield-slash"></i> Sudah Ada (Dilewati)</span>';
            } else {
                $rowClass = 'bg-[#FEF2F2]';
                $badgeHtml = '<span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#FEE2E2] text-[#B91C1C] border border-[#FCA5A5] inline-flex items-center gap-1"><i class="bi bi-x-circle-fill"></i> Tidak Valid</span>';
            }
          ?>
          <tr class="<?= $rowClass ?>">
            <td class="text-center font-mono text-[#53595F]"><?= $rowNum ?></td>
            <td><?= $badgeHtml ?></td>
            <td class="font-mono font-bold text-[#1B2A3A]"><?= e($d['nomor_rekening'] ?: '-') ?></td>
            <td class="font-semibold text-[#1B2A3A]"><?= e($d['nama_barang']) ?></td>
            <td class="font-mono text-[#53595F]"><?= e($d['tanggal_perolehan'] ?: '-') ?></td>
            <td class="text-[#53595F]"><?= e($d['ruangan'] ?: '-') ?></td>
            <td class="text-[#53595F]"><?= e($d['lokasi'] ?: '-') ?></td>
            <td class="text-[11px] text-[#53595F]"><?= $pRow['reason'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Tombol Eksekusi Impor -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t border-[#E5E7EB]">
      <div class="text-[12px] text-[#53595F]">
        <?php if ($stats['new_count'] > 0): ?>
          Siap membuatkan <strong><?= number_format($stats['new_count']) ?> kartu inventaris baru</strong>. Sebanyak <strong><?= number_format($stats['skipped_count']) ?> kartu yang sudah ada akan dilewati dan tidak ditimpa</strong>.
        <?php else: ?>
          <span class="text-[#B45309] font-bold"><i class="bi bi-info-circle me-1"></i> Seluruh data di file sudah terdaftar di sistem. Tidak ada kartu baru yang perlu dibuat.</span>
        <?php endif; ?>
      </div>

      <div class="flex items-center gap-2">
        <a href="<?= e(module_url('inventaris_import.php')) ?>" class="h-[38px] px-4 bg-white border border-[#D1D5D8] rounded-[6px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors flex items-center gap-1.5 text-decoration-none">
          Batal
        </a>
        <?php if ($stats['new_count'] > 0): ?>
          <form method="post" class="m-0" onsubmit="return confirm('Mulai proses pembuatan <?= $stats['new_count'] ?> kartu inventaris baru? Data lama tidak akan ditimpa.')">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="confirm_import">
            <input type="hidden" name="rows_json" value="<?= e(json_encode($importableRows)) ?>">
            <button type="submit" class="h-[38px] px-5 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[6px] text-[13px] font-bold transition-colors inline-flex items-center gap-2 border-0 shadow-xs cursor-pointer">
              <i class="bi bi-check2-circle"></i> Konfirmasi &amp; Buatkan <?= number_format($stats['new_count']) ?> Kartu Baru
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

  </div>
<?php endif; ?>

<script>
function handleFileSelected(input) {
  const fileNameBox = document.getElementById("selectedFileName");
  const btnProses = document.getElementById("btnProsesPreview");
  if (input.files && input.files[0]) {
    const f = input.files[0];
    if (fileNameBox) {
      fileNameBox.classList.remove("d-none");
      fileNameBox.innerHTML = '<i class="bi bi-file-earmark-check-fill me-1"></i> File dipilih: <strong>' + f.name + '</strong> (' + Math.round(f.size / 1024) + ' KB)';
    }
    if (btnProses) {
      btnProses.disabled = false;
    }
  } else {
    if (fileNameBox) fileNameBox.classList.add("d-none");
    if (btnProses) btnProses.disabled = true;
  }
}

// Drag and drop event handling
const dropZone = document.getElementById("dropZone");
if (dropZone) {
  ["dragenter", "dragover"].forEach(evtName => {
    dropZone.addEventListener(evtName, (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropZone.classList.add("dragover");
    });
  });

  ["dragleave", "drop"].forEach(evtName => {
    dropZone.addEventListener(evtName, (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropZone.classList.remove("dragover");
    });
  });

  dropZone.addEventListener("drop", (e) => {
    const dt = e.dataTransfer;
    if (dt && dt.files && dt.files.length) {
      const fileInput = document.getElementById("fileExcelInput");
      if (fileInput) {
        fileInput.files = dt.files;
        handleFileSelected(fileInput);
      }
    }
  });
}
</script>
<?php
$body = ob_get_clean();

render_page('Impor Kartu Inventaris', $body, $head);
