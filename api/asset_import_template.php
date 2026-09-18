<?php
/**
 * GENERATOR TEMPLATE IMPORT ASET IT (MICROSOFT EXCEL .XLSX DENGAN DROPDOWN INTERAKTIF)
 * Dilengkapi fitur Data Validation (Dropdown Otomatis) untuk:
 * 1. Kategori (Laptop, PC Desktop, Printer, Monitor, Server, dll)
 * 2. Kantor Cabang (Kantor Pusat Operasional, Cabang Batulicin, Cabang Martapura, Cabang Tanjung, Cabang Handil)
 * 3. Divisi / Unit Kerja (IT / MIS, Operasional, Akunting, Kredit, Direksi, SKAI, SDM & UMUM, Kepatuhan)
 * 4. Status Unit (Aktif (Digunakan), Backup / Cadangan, Sedang Dalam Perbaikan, Nonaktif)
 */
require __DIR__ . '/bootstrap.php';
require_login();

$format = strtolower(trim((string)($_GET['format'] ?? 'xlsx')));

if ($format === 'csv' || !class_exists('ZipArchive')) {
    // Fallback Mode CSV
    $filename = 'Template_Import_Aset_IT_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

    $headers = [
        'Kode Inventaris',
        'Kategori',
        'Merk',
        'Model / Tipe',
        'Serial Number',
        'Kantor Cabang',
        'Divisi / Unit Kerja',
        'Pengguna / PIC',
        'Posisi Stiker QR',
        'Alamat IP',
        'Printer Terhubung',
        'Status Unit',
        'Keterangan'
    ];
    fputcsv($out, $headers, ';');

    fclose($out);
    exit;
}

// Mode XLSX Asli dengan Dropdown Interaktif (Data Validation)
$filename = 'Template_Import_Aset_IT_' . date('Ymd') . '.xlsx';
$tempFile = tempnam(sys_get_temp_dir(), 'xlsx_tmpl_');
$zip = new ZipArchive();
$zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

// 1. [Content_Types].xml
$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
$zip->addFromString('[Content_Types].xml', $contentTypes);

// 2. _rels/.rels
$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
$zip->addFromString('_rels/.rels', $rels);

// 3. xl/_rels/workbook.xml.rels
$wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
$zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

// 4. xl/workbook.xml
$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Template Import Aset IT" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';
$zip->addFromString('xl/workbook.xml', $workbook);

// 5. xl/styles.xml (Gaya Header Biru Navy Bank & Border Tipis)
$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF0D2748"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color rgb="FFD1D5DB"/></left>
      <right style="thin"><color rgb="FFD1D5DB"/></right>
      <top style="thin"><color rgb="FFD1D5DB"/></top>
      <bottom style="thin"><color rgb="FFD1D5DB"/></bottom>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="3">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">
      <alignment vertical="center"/>
    </xf>
  </cellXfs>
</styleSheet>';
$zip->addFromString('xl/styles.xml', $styles);

// Header & Baris Contoh
$headers = [
    'Kode Inventaris',
    'Kategori',
    'Merk',
    'Model / Tipe',
    'Serial Number',
    'Kantor Cabang',
    'Divisi / Unit Kerja',
    'Pengguna / PIC',
    'Posisi Stiker QR',
    'Alamat IP',
    'Printer Terhubung',
    'Status Unit',
    'Keterangan'
];

$colsXml = '<cols>
  <col min="1" max="1" width="18" customWidth="1"/>
  <col min="2" max="2" width="18" customWidth="1"/>
  <col min="3" max="3" width="16" customWidth="1"/>
  <col min="4" max="4" width="26" customWidth="1"/>
  <col min="5" max="5" width="20" customWidth="1"/>
  <col min="6" max="6" width="28" customWidth="1"/>
  <col min="7" max="7" width="24" customWidth="1"/>
  <col min="8" max="8" width="24" customWidth="1"/>
  <col min="9" max="9" width="22" customWidth="1"/>
  <col min="10" max="10" width="18" customWidth="1"/>
  <col min="11" max="11" width="22" customWidth="1"/>
  <col min="12" max="12" width="24" customWidth="1"/>
  <col min="13" max="13" width="32" customWidth="1"/>
</cols>';

$sheetDataXml = '<sheetData>';

// Header Row
$sheetDataXml .= '<row r="1" ht="28" customHeight="1">';
$colLetters = ['A','B','C','D','E','F','G','H','I','J','K','L','M'];
foreach ($headers as $cIdx => $hText) {
    $cRef = $colLetters[$cIdx] . '1';
    $sheetDataXml .= '<c r="' . $cRef . '" s="1" t="inlineStr"><is><t>' . htmlspecialchars($hText, ENT_XML1, 'UTF-8') . '</t></is></c>';
}
$sheetDataXml .= '</row>';
$sheetDataXml .= '</sheetData>';

// DATA VALIDATION (DROPDOWN EXCEL ASLI SESUAI TAMPILAN FORM WEBSITE)
$kategoriList = '&quot;Laptop,PC Desktop,Printer,Monitor,Server,Scanner,UPS,Network Device&quot;';
$cabangList = '&quot;Kantor Pusat Operasional,Cabang Batulicin,Cabang Martapura,Cabang Tanjung,Cabang Handil&quot;';
$divisiList = '&quot;IT / MIS,Operasional,Akunting,Kredit,Direksi,SKAI,SDM &amp; UMUM,Kepatuhan&quot;';
$placementList = '&quot;Bodi Casing,Cover Atas Laptop,Samping CPU,Belakang Monitor,Meja Kerja,Badan Printer&quot;';
$statusList = '&quot;Aktif (Digunakan),Backup / Cadangan,Sedang Dalam Perbaikan,Nonaktif&quot;';

$dataValidationsXml = '<dataValidations count="5">
  <dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" sqref="B2:B500">
    <formula1>' . $kategoriList . '</formula1>
  </dataValidation>
  <dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" sqref="F2:F500">
    <formula1>' . $cabangList . '</formula1>
  </dataValidation>
  <dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" sqref="G2:G500">
    <formula1>' . $divisiList . '</formula1>
  </dataValidation>
  <dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" sqref="I2:I500">
    <formula1>' . $placementList . '</formula1>
  </dataValidation>
  <dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" sqref="L2:L500">
    <formula1>' . $statusList . '</formula1>
  </dataValidation>
</dataValidations>';

$sheet1Xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  ' . $colsXml . '
  ' . $sheetDataXml . '
  ' . $dataValidationsXml . '
</worksheet>';

$zip->addFromString('xl/worksheets/sheet1.xml', $sheet1Xml);
$zip->close();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tempFile));
header('Pragma: no-cache');
header('Expires: 0');
readfile($tempFile);
@unlink($tempFile);
exit;
