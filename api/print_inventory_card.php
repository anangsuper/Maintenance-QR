<?php
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/helpers/GoogleSheetsBridge.php';
require_login();

// 1. Tangani AJAX Action (Sinkronisasi ke Google Sheets atau Simpan URL)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    verify_csrf();
    $action = $_POST['ajax_action'];

    if ($action === 'save_gas_url') {
        $url = trim((string)($_POST['gas_url'] ?? ''));
        $_SESSION['google_sheets_webapp_url'] = $url;
        GoogleSheetsBridge::setUrl($url);
        echo json_encode(['success' => true, 'message' => 'URL Google Apps Script berhasil disimpan.']);
        exit;
    }

    if ($action === 'sync_to_sheets') {
        $rawCards = $_POST['cards_data'] ?? '[]';
        $cards = json_decode($rawCards, true) ?: [];
        if (empty($cards)) {
            echo json_encode(['success' => false, 'error' => 'Tidak ada data kartu yang dikirim.']);
            exit;
        }

        $successCount = 0;
        $errors = [];
        foreach ($cards as $card) {
            $payload = [
                'nomor_rekening'   => $card['kode'] ?? '',
                'nama_barang'      => $card['nama'] ?? '',
                'tanggal_perolehan'=> $card['tgl'] ?? '',
                'lokasi'           => $card['lokasi'] ?? '',
                'pengguna'         => $card['pengguna'] ?? '',
                'barcode_data'     => $card['qr_url'] ?? ''
            ];
            $res = GoogleSheetsBridge::post('insert', $payload, null, 'inventaris_kartu');
            if (!empty($res['success'])) {
                $successCount++;
            } else {
                $errors[] = $res['error'] ?? 'Gagal menyimpan baris ' . ($card['kode'] ?? '');
            }
        }

        echo json_encode([
            'success' => $successCount > 0,
            'synced'  => $successCount,
            'total'   => count($cards),
            'errors'  => $errors
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Aksi AJAX tidak dikenali.']);
    exit;
}

// 2. Tangkap Parameter Query
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));
$singleId = max(0, (int)($_GET['id'] ?? $_GET['asset_id'] ?? 0));
$rawIds = trim((string)($_GET['ids'] ?? $_POST['ids'] ?? ''));
$layout = trim((string)($_GET['layout'] ?? '10')); // '8', '10', '12'
if (!in_array($layout, ['8', '10', '12'], true)) {
    $layout = '10';
}
$showCutGuides = isset($_GET['cut_guides']) ? (int)$_GET['cut_guides'] : 1;
$exportMode = trim((string)($_GET['export'] ?? ''));

// Parsing daftar ID terpilih
$idList = [];
if ($rawIds !== '') {
    foreach (explode(',', $rawIds) as $item) {
        $cleanId = (int)trim($item);
        if ($cleanId > 0) $idList[] = $cleanId;
    }
}

// 3. Ambil Data Aset
$rawAssets = [];
if (is_google_cloud_mode()) {
    $all = map_sheets_assets();
    $rawAssets = $all;
} else {
    $rawAssets = get_qr_admin_rows(0);
}

// Filter sesuai parameter
$assetList = [];
if (!empty($idList)) {
    $map = [];
    foreach ($rawAssets as $a) {
        $map[(int)($a['id'] ?? 0)] = $a;
    }
    foreach ($idList as $tarId) {
        if (isset($map[$tarId])) {
            $assetList[] = $map[$tarId];
        } else {
            $sg = get_asset_by_id($tarId);
            if ($sg) $assetList[] = $sg;
        }
    }
} elseif ($singleId > 0) {
    foreach ($rawAssets as $a) {
        if ((int)($a['id'] ?? 0) === $singleId) {
            $assetList[] = $a;
            break;
        }
    }
    if (empty($assetList)) {
        $sg = get_asset_by_id($singleId);
        if ($sg) $assetList[] = $sg;
    }
} else {
    if ($cabangId > 0) {
        $assetList = array_values(array_filter($rawAssets, function($a) use ($cabangId) {
            return (int)($a['id_cabang'] ?? $a['cabang_id'] ?? 0) === $cabangId;
        }));
    } else {
        $assetList = $rawAssets;
    }
}

// Data Master Cabang untuk Filter
$cabangs = get_cabang_list();
$cabangMap = [];
foreach ($cabangs as $c) {
    $cId = (int)($c['id'] ?? 0);
    $cabangMap[$cId] = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cId);
}

// Normalisasi Data Kartu
$cardsData = [];
$index = 0;
foreach ($assetList as $a) {
    $index++;
    $id = (int)($a['id'] ?? 0);
    $kode = normalize_kode_inventaris((string)($a['kode_inventaris'] ?? 'ASET-' . $id));
    $device = asset_title($a);
    $sn = trim((string)($a['serial_number'] ?? $a['nomor_seri'] ?? ''));
    if ($sn !== '' && $sn !== '-') {
        $deviceFull = $device . ' (' . $sn . ')';
    } else {
        $deviceFull = $device;
    }

    $cId = (int)($a['id_cabang'] ?? $a['cabang_id'] ?? 0);
    $cabangName = $a['cabang_nama'] ?? ($cabangMap[$cId] ?? 'KPO');
    $divisiName = !empty($a['divisi_nama']) && $a['divisi_nama'] !== '-' ? $a['divisi_nama'] : '';
    $lokasi = $divisiName !== '' ? "{$cabangName} / {$divisiName}" : $cabangName;

    $pengguna = !empty($a['karyawan_nama']) && $a['karyawan_nama'] !== '-' ? $a['karyawan_nama'] : 'Umum / Pool';
    
    $tglRaw = (string)($a['tanggal_perolehan'] ?? $a['created_at'] ?? '');
    $tglFormatted = '-';
    if ($tglRaw !== '' && $tglRaw !== '0000-00-00') {
        $ts = strtotime($tglRaw);
        if ($ts && $ts > 0) {
            $tglFormatted = date('d/m/Y', $ts);
        } else {
            $tglFormatted = $tglRaw;
        }
    }

    $token = !empty($a['qr_token']) ? $a['qr_token'] : ($id > 0 ? get_static_qr_token($id) : '');
    $qrUrl = $token ? module_url('scan.php', ['t' => $token]) : module_url('assets.php');

    $cardsData[] = [
        'index'       => $index,
        'id'          => $id,
        'kode'        => $kode,
        'nama'        => $deviceFull,
        'device_pure' => $device,
        'serial'      => $sn,
        'tgl'         => $tglFormatted,
        'lokasi'      => $lokasi,
        'pengguna'    => $pengguna,
        'token'       => $token,
        'qr_url'      => $qrUrl,
        'cabang_nama' => $cabangName,
    ];
}

// 4. Tangani Ekspor CSV
if ($exportMode === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Kartu_Inventaris_CR80_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    fputcsv($out, ['No', 'ID Aset', 'Kode Inventaris', 'Nama Perangkat', 'Nomor Seri', 'Tanggal Perolehan', 'Lokasi Penempatan', 'Pengguna / PIC', 'URL QR Scan']);
    foreach ($cardsData as $c) {
        fputcsv($out, [
            $c['index'],
            $c['id'],
            sanitize_csv_cell($c['kode']),
            sanitize_csv_cell($c['nama']),
            sanitize_csv_cell($c['serial']),
            sanitize_csv_cell($c['tgl']),
            sanitize_csv_cell($c['lokasi']),
            sanitize_csv_cell($c['pengguna']),
            sanitize_csv_cell($c['qr_url'])
        ]);
    }
    fclose($out);
    exit;
}

// 5. Tangani Ekspor Microsoft Word (.doc)
if ($exportMode === 'doc') {
    header('Content-Type: application/msword; charset=utf-8');
    header('Content-Disposition: attachment; filename="Kartu_Inventaris_CR80_' . date('Ymd_His') . '.doc"');
    
    $logoUri = app_logo_data_uri();
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="utf-8"><title>Kartu Inventaris CR80</title>';
    echo '<style>
        @page { size: A4 portrait; margin: 1.2cm 1cm; }
        body { font-family: "Segoe UI", Arial, sans-serif; font-size: 8pt; color: #1e293b; background: #fff; }
        table.word-grid { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        td.word-card-cell { width: 50%; vertical-align: top; padding: 4px; }
        .cr80-box { border: 1.5pt solid #003b73; border-radius: 6pt; overflow: hidden; background: #ffffff; width: 85.6mm; min-height: 54mm; }
        .cr80-header { background-color: #003b73; color: #ffffff; padding: 4pt 6pt; }
        .cr80-title { font-size: 8pt; font-weight: bold; color: #ffffff; }
        .cr80-banner { background-color: #7ac142; color: #ffffff; font-size: 6.5pt; font-weight: bold; padding: 1pt 4pt; border-radius: 2pt; display: inline-block; margin-top: 2pt; }
        .cr80-body { padding: 4pt 6pt; }
        .field-table { width: 100%; border-collapse: collapse; }
        .field-table td { font-size: 7pt; padding: 1pt 0; vertical-align: middle; }
        .field-label { font-weight: bold; color: #003b73; width: 45pt; }
        .field-val { font-weight: 600; color: #0f172a; }
        .badge-kode { background-color: #003b73; color: #ffffff; font-weight: bold; padding: 1pt 4pt; border-radius: 2pt; font-size: 7pt; display: inline-block; }
        .cr80-footer { border-top: 1pt solid #7ac142; padding: 2pt 6pt; font-size: 5.5pt; color: #64748b; background: #f8fafc; }
    </style>';
    echo '</head><body>';
    
    echo '<h3 style="text-align: center; color: #003b73; margin-bottom: 6pt;">PT BPR MITRATAMA ARTHABUANA</h3>';
    echo '<p style="text-align: center; font-size: 8pt; color: #64748b; margin-top: 0; margin-bottom: 12pt;">Dokumen Cetak Kartu Inventaris CR80 (Ukuran Standar 85.6mm x 54.0mm)</p>';

    echo '<table class="word-grid">';
    $colCount = 0;
    foreach ($cardsData as $c) {
        if ($colCount % 2 === 0) {
            echo '<tr>';
        }
        echo '<td class="word-card-cell">';
        echo '<div class="cr80-box">';
        
        // Header
        echo '<div class="cr80-header">';
        echo '<table style="width:100%; border-collapse:collapse;"><tr>';
        echo '<td style="width: 38pt; vertical-align:middle;"><img src="'.e($logoUri).'" style="height: 22pt; width: auto;" alt="Logo"></td>';
        echo '<td style="vertical-align:middle; text-align:right;">';
        echo '<div class="cr80-title">PT BPR MITRATAMA ARTHABUANA</div>';
        echo '<div class="cr80-banner">KARTU INVENTARIS ASET</div>';
        echo '</td></tr></table>';
        echo '</div>';

        // Body
        echo '<div class="cr80-body">';
        echo '<table class="field-table">';
        echo '<tr><td class="field-label">NO. INV</td><td style="width:4pt;">:</td><td class="field-val"><span class="badge-kode">'.e($c['kode']).'</span></td></tr>';
        echo '<tr><td class="field-label">BARANG</td><td>:</td><td class="field-val">'.e($c['nama']).'</td></tr>';
        echo '<tr><td class="field-label">TANGGAL</td><td>:</td><td class="field-val">'.e($c['tgl']).'</td></tr>';
        echo '<tr><td class="field-label">LOKASI</td><td>:</td><td class="field-val">'.e($c['lokasi']).'</td></tr>';
        echo '<tr><td class="field-label">USER/PIC</td><td>:</td><td class="field-val">'.e($c['pengguna']).'</td></tr>';
        echo '</table>';
        echo '</div>';

        // Footer
        echo '<div class="cr80-footer">';
        echo 'Perhatian: Dilarang memindahkan barang inventaris ini tanpa seizin Departemen IT & Sarana Prasarana.';
        echo '</div>';

        echo '</div>'; // end cr80-box
        echo '</td>';

        $colCount++;
        if ($colCount % 2 === 0) {
            echo '</tr>';
        }
    }
    if ($colCount % 2 !== 0) {
        echo '<td class="word-card-cell">&nbsp;</td></tr>';
    }
    echo '</table>';
    echo '</body></html>';
    exit;
}

$logoUrl = app_logo_data_uri();
$storedGasUrl = GoogleSheetsBridge::getUrl();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cetak Kartu Inventaris (CR80) · PT BPR MITRATAMA ARTHABUANA</title>
  
  <!-- CSS Framework & Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  
  <!-- QRCode.js Library -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

  <style id="pageStyle">
    :root {
      --primary-navy: #003b73;
      --accent-green: #7ac142;
      --accent-teal: #009ca6;
      --card-bg: #ffffff;
      --text-dark: #0f172a;
      --text-muted: #64748b;
      --border-color: #cbd5e1;
    }

    * {
      box-sizing: border-box;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }

    body {
      background-color: #f1f5f9;
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      color: var(--text-dark);
      margin: 0;
      padding: 0;
    }

    /* =========================================================================
       TOOLBAR ATAS (NO-PRINT)
       ========================================================================= */
    .top-toolbar {
      background: #ffffff;
      border-bottom: 1px solid #e2e8f0;
      position: sticky;
      top: 0;
      z-index: 1020;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .toolbar-title {
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--primary-navy);
      letter-spacing: -0.01em;
    }

    /* =========================================================================
       CONTAINER & GRID CETAK KERTAS A4
       ========================================================================= */
    .print-stage {
      padding: 24px 0;
      display: flex;
      justify-content: center;
    }

    .a4-sheet {
      background: #ffffff;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
      position: relative;
      margin: 0 auto 30px;
      page-break-after: always;
      display: flex;
      flex-wrap: wrap;
      align-content: flex-start;
      justify-content: center;
      box-sizing: border-box;
    }

    /* Format 10 Kartu / Lembar (2x5 Portrait) */
    .layout-10 {
      width: 210mm;
      min-height: 297mm;
      padding: 11mm 15mm;
      gap: 3.5mm 6mm;
    }

    /* Format 8 Kartu / Lembar (2x4 Portrait) */
    .layout-8 {
      width: 210mm;
      min-height: 297mm;
      padding: 24mm 15mm;
      gap: 9mm 6mm;
    }

    /* Format 12 Kartu / Lembar (3x4 Landscape) */
    .layout-12 {
      width: 297mm;
      min-height: 210mm;
      padding: 6mm 10mm;
      gap: 3mm 4mm;
    }

    /* =========================================================================
       KARTU CR80 FISIK (85.6mm x 54.0mm)
       ========================================================================= */
    .cr80-card-wrapper {
      width: 85.6mm;
      height: 54.0mm;
      position: relative;
      page-break-inside: avoid;
      break-inside: avoid;
    }

    .cr80-card {
      width: 85.6mm;
      height: 54.0mm;
      background: #ffffff;
      border-radius: 3.2mm; /* Radius sudut kartu ATM CR80 */
      border: 1px solid #d1d5db;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      overflow: hidden;
      position: relative;
      box-shadow: 0 2px 6px rgba(0, 59, 115, 0.08);
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .cr80-card:hover {
      box-shadow: 0 6px 16px rgba(0, 59, 115, 0.15);
    }

    /* Cut Guides (Garis Panduan Potong) */
    .with-cut-guides .cr80-card-wrapper::after {
      content: "";
      position: absolute;
      top: -2mm;
      left: -2mm;
      right: -2mm;
      bottom: -2mm;
      border: 0.5px dashed #cbd5e1;
      pointer-events: none;
      border-radius: 4.5mm;
    }

    /* 1. Header Kartu */
    .card-header-bar {
      height: 12.8mm;
      background: #ffffff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.2mm 2.2mm 1mm 2.4mm;
      border-bottom: 0.8px solid #e2e8f0;
    }

    .header-logo-box {
      width: 24mm;
      height: 9.8mm;
      display: flex;
      align-items: center;
      justify-content: flex-start;
    }

    .header-logo-box img {
      max-width: 100%;
      max-height: 9.8mm;
      object-fit: contain;
    }

    .header-title-box {
      flex: 1;
      text-align: right;
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      justify-content: center;
    }

    .header-main-title {
      font-size: 5.6pt;
      font-weight: 800;
      color: var(--primary-navy);
      letter-spacing: 0.02em;
      line-height: 1.15;
      text-transform: uppercase;
      white-space: nowrap;
    }

    .header-sub-sec {
      display: flex;
      align-items: center;
      margin-top: 0.8mm;
    }

    .green-banner-wrapper {
      display: inline-flex;
      align-items: center;
      border-radius: 1.2mm;
      overflow: hidden;
      box-shadow: 0 1px 2px rgba(0,0,0,0.06);
    }

    .teal-stripe {
      width: 2.2mm;
      height: 3.8mm;
      background-color: var(--accent-teal);
    }

    .green-banner {
      background-color: var(--accent-green);
      color: #ffffff;
      font-size: 4.8pt;
      font-weight: 800;
      padding: 0.6mm 2.4mm 0.6mm 1.8mm;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      line-height: 1;
    }

    /* 2. Body Kartu */
    .card-main-body {
      flex: 1;
      padding: 1.4mm 2.4mm 1mm 2.4mm;
      display: flex;
      align-items: stretch;
      justify-content: space-between;
      gap: 1.8mm;
    }

    .info-left-col {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .field-row {
      display: flex;
      align-items: center;
      line-height: 1.2;
      font-size: 5.1pt;
      margin-bottom: 0.6mm;
    }

    .field-row:last-child {
      margin-bottom: 0;
    }

    .field-lbl-wrap {
      width: 17mm;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      gap: 1mm;
      color: var(--primary-navy);
      font-weight: 700;
    }

    .field-icon {
      font-size: 5pt;
      color: var(--primary-navy);
    }

    .field-sep {
      width: 1.5mm;
      flex-shrink: 0;
      color: var(--text-muted);
      font-weight: bold;
    }

    .field-val-wrap {
      flex: 1;
      min-width: 0;
      font-weight: 600;
      color: #1e293b;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .kode-badge {
      display: inline-block;
      background: var(--primary-navy);
      color: #ffffff;
      padding: 0.4mm 1.8mm;
      border-radius: 0.8mm;
      font-family: 'JetBrains Mono', monospace;
      font-size: 5.2pt;
      font-weight: 700;
      letter-spacing: 0.03em;
    }

    .field-val-device {
      font-weight: 700;
      color: #0f172a;
    }

    /* Sisi Kanan: QR Code Box */
    .qr-right-col {
      width: 23.5mm;
      flex-shrink: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding-left: 1mm;
      border-left: 0.6px dashed #e2e8f0;
    }

    .qr-canvas-wrap {
      width: 20mm;
      height: 20mm;
      background: #ffffff;
      border: 1px solid var(--accent-teal);
      border-radius: 1.2mm;
      padding: 1mm;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 1px 3px rgba(0, 59, 115, 0.08);
    }

    .qr-canvas-wrap canvas,
    .qr-canvas-wrap img {
      width: 100% !important;
      height: 100% !important;
      display: block;
    }

    .qr-subtext {
      font-size: 3.8pt;
      font-weight: 800;
      color: var(--primary-navy);
      text-transform: uppercase;
      letter-spacing: 0.02em;
      margin-top: 0.8mm;
      text-align: center;
      line-height: 1.1;
    }

    /* 3. Footer Kartu */
    .card-footer-sec {
      background: #f8fafc;
      border-top: 1px solid var(--accent-green);
      padding: 0.8mm 2.4mm;
      display: flex;
      align-items: center;
      gap: 1mm;
      font-size: 3.9pt;
      color: #475569;
      line-height: 1.15;
    }

    .card-footer-sec i {
      color: #eab308;
      font-size: 4.5pt;
      flex-shrink: 0;
    }

    .card-footer-sec .disclaimer-text {
      flex: 1;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* =========================================================================
       PRINT STYLING (@media print)
       ========================================================================= */
    @media print {
      @page {
        <?php if ($layout === '12'): ?>
        size: A4 landscape;
        <?php else: ?>
        size: A4 portrait;
        <?php endif; ?>
        margin: 0 !important;
      }

      body {
        background: #ffffff !important;
        margin: 0 !important;
        padding: 0 !important;
      }

      .no-print,
      .top-toolbar,
      .modal,
      .toast {
        display: none !important;
      }

      .print-stage {
        padding: 0 !important;
      }

      .a4-sheet {
        box-shadow: none !important;
        margin: 0 !important;
        page-break-after: always !important;
        break-after: page !important;
      }

      .cr80-card {
        box-shadow: none !important;
        border: 1px solid #cbd5e1 !important;
      }
    }
  </style>
</head>
<body>

  <!-- =========================================================================
       TOP TOOLBAR (KONTROL DAN AKSI)
       ========================================================================= -->
  <header class="top-toolbar py-2 px-3 no-print">
    <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center gap-2">
      
      <!-- Kiri: Brand & Informasi Aset -->
      <div class="d-flex align-items-center gap-3">
        <a href="<?= e(module_url('assets.php')) ?>" class="btn btn-sm btn-outline-secondary" title="Kembali ke Asset Registry">
          <i class="bi bi-arrow-left"></i>
        </a>
        <div>
          <div class="toolbar-title d-flex align-items-center gap-2">
            <i class="bi bi-credit-card-2-front text-primary"></i>
            <span>Cetak Kartu Inventaris CR80</span>
            <span class="badge bg-primary text-white" style="font-size: 0.72rem; font-weight: 600;">Standard ATM 85.6x54mm</span>
          </div>
          <div class="text-muted small" style="font-size: 0.74rem;">
            Total: <strong><?= count($cardsData) ?></strong> unit kartu aset · PT BPR Mitratama Arthabuana
          </div>
        </div>
      </div>

      <!-- Tengah: Filter & Kontrol Layout -->
      <div class="d-flex align-items-center gap-2 flex-wrap">
        
        <!-- Filter Cabang -->
        <form method="get" class="d-inline-flex align-items-center gap-1">
          <?php if (!empty($rawIds)): ?>
            <input type="hidden" name="ids" value="<?= e($rawIds) ?>">
          <?php endif; ?>
          <input type="hidden" name="layout" value="<?= e($layout) ?>">
          <select name="cabang" class="form-select form-select-sm" style="width: 170px;" onchange="this.form.submit()">
            <option value="0">Semua Cabang</option>
            <?php foreach ($cabangs as $c): ?>
              <?php $cId = (int)($c['id'] ?? 0); ?>
              <option value="<?= $cId ?>" <?= $cId === $cabangId ? 'selected' : '' ?>>
                <?= e($c['nama'] ?? $c['nama_cabang'] ?? 'Cabang #' . $cId) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>

        <!-- Layout Selector -->
        <div class="btn-group btn-group-sm" role="group" aria-label="Layout Selector">
          <a href="<?= e(filter_query(['layout' => '8'])) ?>" class="btn <?= $layout === '8' ? 'btn-primary' : 'btn-outline-secondary' ?>" title="8 Kartu per Lembar (2x4 Portrait, Margin Longgar)">
            <i class="bi bi-grid-fill me-1"></i> 8 / A4
          </a>
          <a href="<?= e(filter_query(['layout' => '10'])) ?>" class="btn <?= $layout === '10' ? 'btn-primary' : 'btn-outline-secondary' ?>" title="10 Kartu per Lembar (2x5 Portrait, Standar Kartu Nama)">
            <i class="bi bi-grid-3x3-gap-fill me-1"></i> 10 / A4
          </a>
          <a href="<?= e(filter_query(['layout' => '12'])) ?>" class="btn <?= $layout === '12' ? 'btn-primary' : 'btn-outline-secondary' ?>" title="12 Kartu per Lembar (3x4 Landscape, Kapasitas Maksimal)">
            <i class="bi bi-grid-3x2-gap-fill me-1"></i> 12 / A4
          </a>
        </div>

        <!-- Toggle Garis Potong -->
        <div class="form-check form-switch ms-2 d-none d-md-inline-block">
          <input class="form-check-input" type="checkbox" id="cutGuideToggle" checked onchange="toggleCutGuides(this)">
          <label class="form-check-label small" for="cutGuideToggle" style="font-size: 0.76rem;">Garis Potong</label>
        </div>
      </div>

      <!-- Kanan: Aksi Cetak & Ekspor -->
      <div class="d-flex align-items-center gap-2 flex-wrap">
        
        <!-- Live Preview Modal Trigger -->
        <button type="button" class="btn btn-sm btn-outline-info fw-semibold" onclick="openLivePreviewModal()">
          <i class="bi bi-eye me-1"></i> Pratinjau 1-per-1
        </button>

        <!-- Input Lokasi Cepat Trigger -->
        <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold" data-bs-toggle="modal" data-bs-target="#quickEditModal">
          <i class="bi bi-pencil-square me-1"></i> Set Lokasi Massal
        </button>

        <!-- Ekspor Dropdown -->
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-secondary dropdown-toggle fw-semibold" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-download me-1"></i> Ekspor
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.85rem;">
            <li>
              <a class="dropdown-item" href="<?= e(filter_query(['export' => 'doc'])) ?>">
                <i class="bi bi-file-earmark-word-fill text-primary me-2"></i> Dokumen Microsoft Word (.doc)
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="<?= e(filter_query(['export' => 'csv'])) ?>">
                <i class="bi bi-file-earmark-spreadsheet-fill text-success me-2"></i> Spreadsheet CSV / Excel
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#googleSheetsModal">
                <i class="bi bi-google text-danger me-2"></i> Database Google Sheets Otomatis
              </button>
            </li>
          </ul>
        </div>

        <!-- Tombol Cetak Browser Utama -->
        <button type="button" class="btn btn-sm btn-primary fw-bold shadow-sm" onclick="window.print()">
          <i class="bi bi-printer-fill me-1"></i> Cetak / Simpan PDF
        </button>
      </div>

    </div>
  </header>

  <!-- =========================================================================
       PRINT STAGE: LEMBAR A4 DENGAN KARTU CR80
       ========================================================================= -->
  <main class="print-stage">
    <?php if (empty($cardsData)): ?>
      <div class="card p-5 text-center my-5 shadow-sm" style="max-width: 500px;">
        <i class="bi bi-credit-card-2-front fs-1 text-muted mb-3 opacity-50"></i>
        <h5 class="fw-bold text-dark">Tidak Ada Kartu yang Dipilih</h5>
        <p class="text-muted small">Pilih aset dari halaman Asset Registry atau filter berdasarkan Kantor Cabang untuk mencetak kartu inventaris CR80.</p>
        <div class="mt-3">
          <a href="<?= e(module_url('assets.php')) ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Ke Asset Registry
          </a>
        </div>
      </div>
    <?php else: ?>
      
      <?php
        $cardsPerPage = (int)$layout; // 8, 10, atau 12
        $pages = array_chunk($cardsData, $cardsPerPage);
        $totalPageCount = count($pages);
      ?>

      <div id="sheetsContainer" class="with-cut-guides">
        <?php foreach ($pages as $pIdx => $pageCards): ?>
          <div class="a4-sheet layout-<?= e($layout) ?>" data-page="<?= $pIdx + 1 ?>">
            <?php foreach ($pageCards as $card): ?>
              <?php $cIdx = $card['index']; ?>
              <div class="cr80-card-wrapper" id="card-wrap-<?= $cIdx ?>" data-card-id="<?= $card['id'] ?>">
                <div class="cr80-card">
                  
                  <!-- 1. Header Kartu Berwarna -->
                  <div class="card-header-bar">
                    <div class="header-logo-box">
                      <img src="<?= e($logoUrl) ?>" alt="Logo Bank" loading="lazy">
                    </div>
                    <div class="header-title-box">
                      <div class="header-main-title">PT BPR MITRATAMA ARTHABUANA</div>
                      <div class="header-sub-sec">
                        <div class="green-banner-wrapper">
                          <div class="teal-stripe"></div>
                          <div class="green-banner">KARTU INVENTARIS ASET</div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- 2. Body Kartu: Kolom Informasi & Kolom QR Code -->
                  <div class="card-main-body">
                    
                    <!-- Kolom Kiri: Detail Aset -->
                    <div class="info-left-col">
                      <div class="field-row">
                        <div class="field-lbl-wrap">
                          <i class="bi bi-hash field-icon"></i>
                          <span>NO. INV</span>
                        </div>
                        <div class="field-sep">:</div>
                        <div class="field-val-wrap">
                          <span class="kode-badge"><?= e($card['kode']) ?></span>
                        </div>
                      </div>

                      <div class="field-row">
                        <div class="field-lbl-wrap">
                          <i class="bi bi-pc-display field-icon"></i>
                          <span>BARANG</span>
                        </div>
                        <div class="field-sep">:</div>
                        <div class="field-val-wrap field-val-device" title="<?= e($card['nama']) ?>">
                          <?= e($card['nama']) ?>
                        </div>
                      </div>

                      <div class="field-row">
                        <div class="field-lbl-wrap">
                          <i class="bi bi-calendar-event field-icon"></i>
                          <span>TANGGAL</span>
                        </div>
                        <div class="field-sep">:</div>
                        <div class="field-val-wrap">
                          <?= e($card['tgl']) ?>
                        </div>
                      </div>

                      <div class="field-row">
                        <div class="field-lbl-wrap">
                          <i class="bi bi-geo-alt-fill field-icon text-danger"></i>
                          <span>LOKASI</span>
                        </div>
                        <div class="field-sep">:</div>
                        <div class="field-val-wrap card-lokasi-text" title="<?= e($card['lokasi']) ?>">
                          <?= e($card['lokasi']) ?>
                        </div>
                      </div>

                      <div class="field-row">
                        <div class="field-lbl-wrap">
                          <i class="bi bi-person-fill field-icon text-primary"></i>
                          <span>PIC / USER</span>
                        </div>
                        <div class="field-sep">:</div>
                        <div class="field-val-wrap card-pengguna-text" title="<?= e($card['pengguna']) ?>">
                          <?= e($card['pengguna']) ?>
                        </div>
                      </div>
                    </div>

                    <!-- Kolom Kanan: Kotak QR Code -->
                    <div class="qr-right-col">
                      <div class="qr-canvas-wrap">
                        <div class="qr-box-inner" id="qr-box-<?= $cIdx ?>" data-qr="<?= e($card['qr_url']) ?>"></div>
                      </div>
                      <div class="qr-subtext">SCAN PEMELIHARAAN</div>
                    </div>

                  </div>

                  <!-- 3. Footer Kartu: Perhatian -->
                  <div class="card-footer-sec">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span class="disclaimer-text card-disclaimer-val">
                      Perhatian: Dilarang memindahkan barang inventaris ini tanpa seizin Departemen IT & Sarana Prasarana.
                    </span>
                  </div>

                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>
  </main>

  <!-- =========================================================================
       MODAL 1: LIVE PREVIEW INTERAKTIF (1-PER-1 SKALA NYATA)
       ========================================================================= -->
  <div class="modal fade" id="livePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 540px;">
      <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
        <div class="modal-header bg-primary text-white py-2 px-3">
          <h6 class="modal-title fw-bold d-flex align-items-center gap-2">
            <i class="bi bi-eye-fill"></i> Pratinjau Interaktif Kartu CR80
          </h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-center bg-light p-4">
          
          <!-- Wrapper Zoom Preview -->
          <div class="d-flex justify-content-center mb-3">
            <div id="previewCardTarget" style="transform: scale(1.35); transform-origin: top center; margin-bottom: 24mm;">
              <!-- Kartu CR80 akan di-clone ke sini -->
            </div>
          </div>

          <!-- Controls Navigasi Kartu -->
          <div class="d-flex justify-content-between align-items-center bg-white p-2 rounded-3 border shadow-sm mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold" id="prevCardBtn" onclick="navigateCard(-1)">
              <i class="bi bi-chevron-left"></i> Sebelumnya
            </button>
            <span class="small fw-bold text-muted font-monospace" id="previewCardCounter">Kartu 1 dari <?= count($cardsData) ?></span>
            <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold" id="nextCardBtn" onclick="navigateCard(1)">
              Berikutnya <i class="bi bi-chevron-right"></i>
            </button>
          </div>

        </div>
        <div class="modal-footer py-2 px-3 justify-content-between">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
          <button type="button" class="btn btn-sm btn-primary fw-bold" onclick="window.print()">
            <i class="bi bi-printer-fill me-1"></i> Cetak Seluruh Kartu
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- =========================================================================
       MODAL 2: INPUT LOKASI CEPAT & MASSAL
       ========================================================================= -->
  <div class="modal fade" id="quickEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
        <div class="modal-header py-2 px-3 bg-dark text-white">
          <h6 class="modal-title fw-bold"><i class="bi bi-sliders me-2"></i>Kustomisasi Teks Kartu Cetak</h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-3">
          
          <!-- Lokasi Massal -->
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Lokasi Penempatan Massal</label>
            <div class="input-group input-group-sm">
              <input type="text" id="bulkLocationInput" class="form-control" placeholder="Contoh: KPO / Lantai 2 / Ruang IT">
              <button class="btn btn-primary fw-semibold" type="button" onclick="applyBulkLocation()">
                <i class="bi bi-check-lg me-1"></i> Terapkan ke Semua
              </button>
            </div>
            <div class="form-text" style="font-size: 0.74rem;">Mengganti nilai lokasi pada seluruh kartu yang ada di layar cetak saat ini.</div>
          </div>

          <!-- Teks Perhatian / Disclaimer -->
          <div class="mb-2">
            <label class="form-label small fw-bold text-dark">Teks Catatan Perhatian (Footer Kartu)</label>
            <textarea id="bulkDisclaimerInput" class="form-control form-control-sm" rows="2">Perhatian: Dilarang memindahkan barang inventaris ini tanpa seizin Departemen IT & Sarana Prasarana.</textarea>
            <button class="btn btn-sm btn-outline-secondary w-100 mt-2 fw-semibold" type="button" onclick="applyBulkDisclaimer()">
              Terapkan Perhatian ke Semua Kartu
            </button>
          </div>

        </div>
        <div class="modal-footer py-2 px-3">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Selesai</button>
        </div>
      </div>
    </div>
  </div>

  <!-- =========================================================================
       MODAL 3: INTEGRASI & SINKRONISASI GOOGLE SHEETS
       ========================================================================= -->
  <div class="modal fade" id="googleSheetsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
        <div class="modal-header py-2 px-3 bg-success text-white">
          <h6 class="modal-title fw-bold"><i class="bi bi-google me-2"></i>Database Google Spreadsheet Otomatis</h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          
          <ul class="nav nav-pills nav-fill mb-3" id="gasTabs" role="tablist">
            <li class="nav-item">
              <button class="nav-link active fw-bold btn-sm" id="gas-sync-tab" data-bs-toggle="pill" data-bs-target="#gas-sync" type="button">
                <i class="bi bi-cloud-arrow-up-fill me-1"></i> 1. Sinkronisasi Data Kartu
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link fw-bold btn-sm" id="gas-code-tab" data-bs-toggle="pill" data-bs-target="#gas-code" type="button">
                <i class="bi bi-code-slash me-1"></i> 2. Salin Kode Google Apps Script
              </button>
            </li>
          </ul>

          <div class="tab-content">
            
            <!-- Tab 1: Sinkronisasi URL -->
            <div class="tab-pane fade show active" id="gas-sync">
              <div class="alert alert-info py-2 px-3 small border-0 shadow-sm mb-3">
                <i class="bi bi-info-circle-fill me-1"></i> Modul ini akan otomatis membuat tab sheet <code>inventaris_kartu</code> beserta kolom header jika belum ada di Spreadsheet Anda.
              </div>

              <div class="mb-3">
                <label class="form-label small fw-bold text-dark">URL Google Apps Script Web App (Exec URL)</label>
                <div class="input-group input-group-sm">
                  <input type="url" id="gasUrlInput" class="form-control font-monospace" value="<?= e($storedGasUrl) ?>" placeholder="https://script.google.com/macros/s/AKfycb.../exec">
                  <button class="btn btn-outline-secondary" type="button" onclick="saveGasUrl()">Simpan URL</button>
                </div>
                <div class="form-text" style="font-size: 0.74rem;">URL hasil Deploy Web App (Who has access: Anyone) dari Spreadsheet Anda.</div>
              </div>

              <div class="card p-3 bg-light border-0">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="small fw-bold text-dark">Aset yang akan disinkronkan:</span>
                  <span class="badge bg-success"><?= count($cardsData) ?> Unit Kartu</span>
                </div>
                <button type="button" class="btn btn-success btn-sm fw-bold w-100 py-2" id="syncSheetsBtn" onclick="syncCardsToGoogleSheets()">
                  <i class="bi bi-arrow-repeat me-1"></i> Sinkronkan Sekarang ke Google Sheets
                </button>
                <div id="syncResultStatus" class="mt-2 small text-center" style="display: none;"></div>
              </div>
            </div>

            <!-- Tab 2: Salin Kode Apps Script -->
            <div class="tab-pane fade" id="gas-code">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small text-muted">Pasang kode ini di Extensions &gt; Apps Script Google Sheets Anda:</span>
                <button class="btn btn-sm btn-outline-primary" type="button" onclick="copyGasScript()">
                  <i class="bi bi-clipboard me-1"></i> Salin Kode Skrip
                </button>
              </div>
              <textarea id="gasScriptArea" class="form-control font-monospace text-muted" rows="9" readonly style="font-size: 0.72rem; background: #f8fafc;">
function doGet(e) {
  var action = (e && e.parameter && e.parameter.action) ? e.parameter.action : 'readAll';
  var tableName = (e && e.parameter && e.parameter.table) ? e.parameter.table : 'inventaris_kartu';
  if (action === 'readAll') {
    var ss = SpreadsheetApp.getActiveSpreadsheet();
    var sheet = ss.getSheetByName(tableName);
    if (!sheet) return ContentService.createTextOutput(JSON.stringify([])).setMimeType(ContentService.MimeType.JSON);
    var data = sheet.getDataRange().getValues();
    if (data.length <= 1) return ContentService.createTextOutput(JSON.stringify([])).setMimeType(ContentService.MimeType.JSON);
    var headers = data[0], rows = [];
    for (var i = 1; i < data.length; i++) {
      var row = {}, isEmpty = true;
      for (var j = 0; j < headers.length; j++) {
        var val = data[i][j];
        if (val instanceof Date) val = Utilities.formatDate(val, Session.getScriptTimeZone(), "yyyy-MM-dd");
        row[headers[j]] = val;
        if (val !== "" && val !== null && val !== undefined) isEmpty = false;
      }
      if (!isEmpty) rows.push(row);
    }
    return ContentService.createTextOutput(JSON.stringify(rows)).setMimeType(ContentService.MimeType.JSON);
  }
  return ContentService.createTextOutput(JSON.stringify({error: "Invalid action"})).setMimeType(ContentService.MimeType.JSON);
}

function doPost(e) {
  try {
    var postData = JSON.parse(e.postData.contents);
    var table = postData.table || 'inventaris_kartu';
    var data = postData.data || {};
    var headers = ['id', 'nomor_rekening', 'nama_barang', 'tanggal_perolehan', 'lokasi', 'pengguna', 'barcode_data', 'created_at'];
    var ss = SpreadsheetApp.getActiveSpreadsheet();
    var sheet = ss.getSheetByName(table);
    if (!sheet) {
      sheet = ss.insertSheet(table);
      sheet.appendRow(headers);
      var hr = sheet.getRange(1, 1, 1, headers.length);
      hr.setBackground("#003b73").setFontColor("#ffffff").setFontWeight("bold");
      sheet.setFrozenRows(1);
    }
    if (postData.action === 'insert') {
      var vals = sheet.getDataRange().getValues();
      var idIdx = headers.indexOf('id');
      var maxId = 0;
      for (var i = 1; i < vals.length; i++) {
        var cid = parseInt(vals[i][idIdx]);
        if (!isNaN(cid) && cid > maxId) maxId = cid;
      }
      data['id'] = maxId + 1;
      data['created_at'] = Utilities.formatDate(new Date(), Session.getScriptTimeZone(), "yyyy-MM-dd HH:mm:ss");
      var newRow = [];
      headers.forEach(function(h) { newRow.push(data[h] !== undefined ? data[h] : ""); });
      sheet.appendRow(newRow);
      return ContentService.createTextOutput(JSON.stringify({success: true, id: data['id']})).setMimeType(ContentService.MimeType.JSON);
    }
    return ContentService.createTextOutput(JSON.stringify({error: "Action unknown"})).setMimeType(ContentService.MimeType.JSON);
  } catch(err) {
    return ContentService.createTextOutput(JSON.stringify({error: err.message})).setMimeType(ContentService.MimeType.JSON);
  }
}
              </textarea>
            </div>

          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- CSRF Token untuk Form AJAX -->
  <input type="hidden" id="csrfToken" value="<?= e(csrf_token()) ?>">

  <!-- Bootstrap Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Script Generator & Logika Interaktif -->
  <script>
    // Data Kartu dalam Memory Client
    const CARDS_DATA = <?= json_encode($cardsData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    let currentPreviewIndex = 0;

    // 1. Generate QR Code Client-Side untuk setiap kartu
    document.addEventListener("DOMContentLoaded", function() {
      const qrBoxes = document.querySelectorAll(".qr-box-inner");
      qrBoxes.forEach(box => {
        const qrUrl = box.getAttribute("data-qr");
        if (qrUrl) {
          new QRCode(box, {
            text: qrUrl,
            width: 72,
            height: 72,
            colorDark: "#003b73",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
          });
        }
      });
    });

    // 2. Toggle Garis Potong
    function toggleCutGuides(el) {
      const container = document.getElementById("sheetsContainer");
      if (el.checked) {
        container.classList.add("with-cut-guides");
      } else {
        container.classList.remove("with-cut-guides");
      }
    }

    // 3. Live Preview Modal (1-per-1)
    function openLivePreviewModal() {
      if (CARDS_DATA.length === 0) return;
      currentPreviewIndex = 0;
      renderPreviewCard();
      const modal = new bootstrap.Modal(document.getElementById('livePreviewModal'));
      modal.show();
    }

    function renderPreviewCard() {
      const target = document.getElementById("previewCardTarget");
      const counter = document.getElementById("previewCardCounter");
      const cardData = CARDS_DATA[currentPreviewIndex];
      if (!cardData) return;

      const sourceWrap = document.getElementById(`card-wrap-${cardData.index}`);
      if (sourceWrap) {
        target.innerHTML = sourceWrap.innerHTML;
      }

      counter.innerText = `Kartu ${currentPreviewIndex + 1} dari ${CARDS_DATA.length}`;
      document.getElementById("prevCardBtn").disabled = (currentPreviewIndex === 0);
      document.getElementById("nextCardBtn").disabled = (currentPreviewIndex === CARDS_DATA.length - 1);
    }

    function navigateCard(step) {
      const newIndex = currentPreviewIndex + step;
      if (newIndex >= 0 && newIndex < CARDS_DATA.length) {
        currentPreviewIndex = newIndex;
        renderPreviewCard();
      }
    }

    // 4. Quick Edit: Terapkan Lokasi Massal
    function applyBulkLocation() {
      const locVal = document.getElementById("bulkLocationInput").value.trim();
      if (!locVal) {
        alert("Silakan ketik nama lokasi terlebih dahulu.");
        return;
      }
      document.querySelectorAll(".card-lokasi-text").forEach(el => {
        el.innerText = locVal;
        el.setAttribute("title", locVal);
      });
      CARDS_DATA.forEach(c => {
        c.lokasi = locVal;
      });
      alert(`Lokasi "${locVal}" berhasil diterapkan ke semua kartu.`);
    }

    // 5. Quick Edit: Terapkan Disclaimer Massal
    function applyBulkDisclaimer() {
      const discVal = document.getElementById("bulkDisclaimerInput").value.trim();
      if (!discVal) return;
      document.querySelectorAll(".card-disclaimer-val").forEach(el => {
        el.innerText = discVal;
      });
      alert("Catatan perhatian footer berhasil diperbarui.");
    }

    // 6. Simpan URL Apps Script
    function saveGasUrl() {
      const gasUrl = document.getElementById("gasUrlInput").value.trim();
      if (!gasUrl) {
        alert("Ketik atau tempel URL Google Apps Script Web App.");
        return;
      }
      const csrf = document.getElementById("csrfToken").value;
      const formData = new FormData();
      formData.append("ajax_action", "save_gas_url");
      formData.append("gas_url", gasUrl);
      formData.append("_csrf", csrf);

      fetch(window.location.href, {
        method: "POST",
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          alert("URL Google Apps Script berhasil disimpan!");
        } else {
          alert("Gagal menyimpan: " + (data.error || ""));
        }
      })
      .catch(err => alert("Terjadi kesalahan jaringan: " + err));
    }

    // 7. Sinkronisasi ke Google Sheets
    function syncCardsToGoogleSheets() {
      const gasUrl = document.getElementById("gasUrlInput").value.trim();
      if (!gasUrl) {
        alert("Harap simpan URL Google Apps Script terlebih dahulu.");
        return;
      }

      const btn = document.getElementById("syncSheetsBtn");
      const status = document.getElementById("syncResultStatus");
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengirim data ke Google Sheets...';
      status.style.display = "block";
      status.className = "mt-2 small text-muted text-center";
      status.innerText = "Sedang berkomunikasi dengan spreadsheet...";

      const csrf = document.getElementById("csrfToken").value;
      const formData = new FormData();
      formData.append("ajax_action", "sync_to_sheets");
      formData.append("cards_data", JSON.stringify(CARDS_DATA));
      formData.append("_csrf", csrf);

      fetch(window.location.href, {
        method: "POST",
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Sinkronkan Sekarang ke Google Sheets';
        if (data.success) {
          status.className = "mt-2 small text-success fw-bold text-center";
          status.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> Berhasil menyinkronkan ${data.synced} dari ${data.total} kartu ke Google Sheets!`;
        } else {
          status.className = "mt-2 small text-danger fw-semibold text-center";
          status.innerHTML = `<i class="bi bi-exclamation-circle me-1"></i> Gagal: ${data.error || 'Terjadi kendala saat menyimpan.'}`;
        }
      })
      .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Sinkronkan Sekarang ke Google Sheets';
        status.className = "mt-2 small text-danger text-center";
        status.innerText = "Kesalahan koneksi: " + err;
      });
    }

    // 8. Salin Kode Apps Script
    function copyGasScript() {
      const area = document.getElementById("gasScriptArea");
      area.select();
      navigator.clipboard.writeText(area.value);
      alert("Kode Google Apps Script berhasil disalin ke clipboard!");
    }
  </script>
</body>
</html>
