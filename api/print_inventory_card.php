<?php
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/helpers/GoogleSheetsBridge.php';
require_once __DIR__ . '/includes/inventaris_kartu.php';
require_login();

// Helper untuk URL query parameter pada halaman ini
function card_url(array $params = []): string {
    $merged = array_merge($_GET, $params);
    foreach ($merged as $k => $v) {
        if ($v === null || $v === '') {
            unset($merged[$k]);
        }
    }
    return module_url('print_inventory_card.php', $merged);
}

// 1. Tangani Form Tambah Kartu Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'add_card') {
    verify_csrf();
    $res = insert_inventaris_kartu([
        'nomor_rekening'   => $_POST['nomor_rekening'] ?? '',
        'nama_barang'      => $_POST['nama_barang'] ?? '',
        'tanggal_perolehan'=> $_POST['tanggal_perolehan'] ?? date('Y-m-d'),
        'barcode_data'     => $_POST['barcode_data'] ?? '',
        'lokasi'           => $_POST['lokasi'] ?? 'KPO / Operasional',
        'pengguna'         => $_POST['pengguna'] ?? 'Umum / Pool'
    ]);
    if (!empty($res['success'])) {
        $_SESSION['flash'] = 'Kartu inventaris baru berhasil ditambahkan.';
    } else {
        $_SESSION['flash_error'] = 'Gagal menambahkan kartu: ' . ($res['error'] ?? 'Terjadi kesalahan.');
    }
    header('Location: ' . card_url(['source' => 'inventaris_kartu']));
    exit;
}

// Tangani AJAX Action (Sinkronisasi ke Google Sheets atau Simpan URL)
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
                'id'               => $card['id'] ?? null,
                'nomor_rekening'   => $card['kode'] ?? '',
                'nama_barang'      => $card['nama'] ?? '',
                'tanggal_perolehan'=> $card['tgl_raw'] ?? ($card['tgl'] ?? ''),
                'barcode_data'     => $card['barcode_data'] ?? ($card['qr_url'] ?? ''),
                'lokasi'           => $card['lokasi'] ?? '',
                'pengguna'         => $card['pengguna'] ?? ''
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
$source = trim((string)($_GET['source'] ?? 'inventaris_kartu')); // 'inventaris_kartu' (default) atau 'assets'
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

// 3. Ambil Data Sesuai Source
$cardsData = [];
$index = 0;

if ($source === 'inventaris_kartu') {
    // Mode Default: Tabel Terpisah inventaris_kartu
    $invRows = get_inventaris_kartu_rows();
    if (!empty($idList)) {
        $invRows = array_values(array_filter($invRows, function($r) use ($idList) {
            return in_array((int)$r['id'], $idList, true);
        }));
    } elseif ($singleId > 0) {
        $invRows = array_values(array_filter($invRows, function($r) use ($singleId) {
            return (int)$r['id'] === $singleId;
        }));
    }

    foreach ($invRows as $r) {
        $index++;
        $id = (int)($r['id'] ?? 0);
        $rek = trim((string)($r['nomor_rekening'] ?? ''));
        $nama = trim((string)($r['nama_barang'] ?? ''));
        $barcode = trim((string)($r['barcode_data'] ?? ''));
        $lokasi = trim((string)($r['lokasi'] ?? 'KPO / Operasional')) ?: 'KPO / Operasional';
        $pengguna = trim((string)($r['pengguna'] ?? 'Umum / Pool')) ?: 'Umum / Pool';
        $tglRaw = trim((string)($r['tanggal_perolehan'] ?? ''));

        $tglFormatted = '-';
        if ($tglRaw !== '' && $tglRaw !== '0000-00-00') {
            $ts = strtotime($tglRaw);
            $tglFormatted = $ts ? date('d/m/Y', $ts) : $tglRaw;
        }

        $qrTarget = $barcode !== '' ? $barcode : module_url('scan.php', ['t' => get_static_qr_token($id)]);

        $cardsData[] = [
            'index'        => $index,
            'id'           => $id,
            'kode'         => $rek !== '' ? $rek : ('INV-' . $id),
            'nama'         => $nama,
            'tgl'          => $tglFormatted,
            'tgl_raw'      => $tglRaw,
            'nomor_gabungan' => get_nomor_asset_gabungan($rek, $tglRaw),
            'lokasi'       => $lokasi,
            'pengguna'     => $pengguna,
            'barcode_data' => $barcode,
            'qr_url'       => $qrTarget,
            'created_at'   => $r['created_at'] ?? ''
        ];
    }
} else {
    // Mode Alternatif: Dari Katalog Asset Registry Komputer
    $rawAssets = is_google_cloud_mode() ? map_sheets_assets() : get_qr_admin_rows(0);
    $cabangs = get_cabang_list();
    $cabangMap = [];
    foreach ($cabangs as $c) {
        $cId = (int)($c['id'] ?? 0);
        $cabangMap[$cId] = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cId);
    }

    if (!empty($idList)) {
        $map = [];
        foreach ($rawAssets as $a) $map[(int)($a['id'] ?? 0)] = $a;
        $filtered = [];
        foreach ($idList as $tarId) {
            if (isset($map[$tarId])) $filtered[] = $map[$tarId];
            else { $sg = get_asset_by_id($tarId); if ($sg) $filtered[] = $sg; }
        }
        $rawAssets = $filtered;
    } elseif ($singleId > 0) {
        $rawAssets = array_values(array_filter($rawAssets, function($a) use ($singleId) {
            return (int)($a['id'] ?? 0) === $singleId;
        }));
    } elseif ($cabangId > 0) {
        $rawAssets = array_values(array_filter($rawAssets, function($a) use ($cabangId) {
            return (int)($a['id_cabang'] ?? $a['cabang_id'] ?? 0) === $cabangId;
        }));
    }

    foreach ($rawAssets as $a) {
        $index++;
        $id = (int)($a['id'] ?? 0);
        $kode = normalize_kode_inventaris((string)($a['kode_inventaris'] ?? 'ASET-' . $id));
        $device = asset_title($a);
        $sn = trim((string)($a['serial_number'] ?? $a['nomor_seri'] ?? ''));
        $deviceFull = ($sn !== '' && $sn !== '-') ? "{$device} ({$sn})" : $device;

        $cId = (int)($a['id_cabang'] ?? $a['cabang_id'] ?? 0);
        $cabangName = $a['cabang_nama'] ?? ($cabangMap[$cId] ?? 'KPO');
        $divisiName = !empty($a['divisi_nama']) && $a['divisi_nama'] !== '-' ? $a['divisi_nama'] : '';
        $lokasi = $divisiName !== '' ? "{$cabangName} / {$divisiName}" : $cabangName;
        $pengguna = !empty($a['karyawan_nama']) && $a['karyawan_nama'] !== '-' ? $a['karyawan_nama'] : 'Umum / Pool';

        $tglRaw = (string)($a['tanggal_perolehan'] ?? $a['created_at'] ?? '');
        $tglFormatted = '-';
        if ($tglRaw !== '' && $tglRaw !== '0000-00-00') {
            $ts = strtotime($tglRaw);
            $tglFormatted = $ts ? date('d/m/Y', $ts) : $tglRaw;
        }

        $token = !empty($a['qr_token']) ? $a['qr_token'] : ($id > 0 ? get_static_qr_token($id) : '');
        $qrUrl = $token ? module_url('scan.php', ['t' => $token]) : module_url('assets.php');

        $cardsData[] = [
            'index'        => $index,
            'id'           => $id,
            'kode'         => $kode,
            'nama'         => $deviceFull,
            'tgl'          => $tglFormatted,
            'tgl_raw'      => $tglRaw,
            'nomor_gabungan' => get_nomor_asset_gabungan($kode, $tglRaw),
            'lokasi'       => $lokasi,
            'pengguna'     => $pengguna,
            'barcode_data' => $qrUrl,
            'qr_url'       => $qrUrl,
            'created_at'   => $tglRaw
        ];
    }
}

// 4. Tangani Ekspor CSV
if ($exportMode === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Kartu_Inventaris_CR80_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    fputcsv($out, ['No', 'ID', 'Nomor Rekening / Kode', 'Nama Barang', 'Tanggal Perolehan', 'Barcode Data (URL)', 'Lokasi', 'Pengguna']);
    foreach ($cardsData as $c) {
        fputcsv($out, [
            $c['index'],
            $c['id'],
            sanitize_csv_cell($c['kode']),
            sanitize_csv_cell($c['nama']),
            sanitize_csv_cell($c['tgl']),
            sanitize_csv_cell($c['barcode_data']),
            sanitize_csv_cell($c['lokasi']),
            sanitize_csv_cell($c['pengguna'])
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
        @page { size: A4 portrait; margin: 1cm 0.8cm; }
        body { font-family: Arial, sans-serif; font-size: 8pt; color: #1e293b; }
        table.word-grid { width: 100%; border-collapse: collapse; }
        td.word-card-cell { width: 50%; vertical-align: top; padding: 4px; }
        .cr80-box { border: 1.5pt solid #003b73; border-radius: 6pt; overflow: hidden; width: 85.6mm; min-height: 54mm; }
        .cr80-header { background-color: #003b73; color: #ffffff; padding: 4pt 6pt; }
        .cr80-title { font-size: 7.5pt; font-weight: bold; color: #ffffff; }
        .cr80-banner { background-color: #7ac142; color: #ffffff; font-size: 6.5pt; font-weight: bold; padding: 1pt 4pt; border-radius: 2pt; display: inline-block; }
        .cr80-body { padding: 4pt 6pt; }
        .field-table { width: 100%; border-collapse: collapse; }
        .field-table td { font-size: 7pt; padding: 1.5pt 0; vertical-align: middle; }
        .field-label { font-weight: bold; color: #003b73; width: 48pt; }
        .badge-kode { background-color: #003b73; color: #ffffff; font-weight: bold; padding: 1pt 4pt; border-radius: 2pt; font-size: 7pt; }
        .cr80-footer { border-top: 1pt solid #7ac142; padding: 2.5pt 6pt; font-size: 5.5pt; color: #64748b; background: #f8fafc; }
    </style>';
    echo '</head><body>';
    echo '<table class="word-grid">';
    $colCount = 0;
    foreach ($cardsData as $c) {
        if ($colCount % 2 === 0) echo '<tr>';
        echo '<td class="word-card-cell">';
        echo '<div class="cr80-box">';
        echo '<div class="cr80-header">';
        echo '<table style="width:100%; border-collapse:collapse;"><tr>';
        echo '<td style="width: 36pt;"><img src="'.e($logoUri).'" style="height: 20pt; width: auto;"></td>';
        echo '<td style="text-align:right;"><div class="cr80-title">PT BPR MITRATAMA ARTHABUANA</div><div class="cr80-banner">ASSET TETAP</div></td>';
        echo '</tr></table>';
        echo '</div>';
        echo '<div class="cr80-body">';
        echo '<table class="field-table">';
        echo '<tr><td class="field-label">NOMOR ASSET</td><td style="width:4pt;">|</td><td><b>'.e($c['nomor_gabungan']).'</b></td></tr>';
        echo '<tr><td class="field-label">NAMA ASSET</td><td>|</td><td><b>'.e($c['nama']).'</b></td></tr>';
        echo '<tr><td class="field-label">TGL PEROLEHAN</td><td>|</td><td><b>'.e($c['tgl']).'</b></td></tr>';
        echo '<tr><td class="field-label">LOKASI</td><td>|</td><td><b>'.e($c['lokasi']).'</b></td></tr>';
        echo '</table>';
        echo '</div>';
        echo '<div class="cr80-footer" style="color:#dc2626;font-weight:bold;">PERHATIAN: <span style="color:#334155;font-weight:normal;">Perhatian Dilarang memindahkan barang inventaris ini tanpa seizin Human Resource Departement (HRD) Bank Mitra</span></div>';
        echo '</div>';
        echo '</td>';
        $colCount++;
        if ($colCount % 2 === 0) echo '</tr>';
    }
    if ($colCount % 2 !== 0) echo '<td class="word-card-cell">&nbsp;</td></tr>';
    echo '</table>';
    echo '</body></html>';
    exit;
}

$logoUrl = app_logo_data_uri();
$storedGasUrl = GoogleSheetsBridge::getUrl();
$flashMsg = $_SESSION['flash'] ?? '';
$flashErr = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_error']);
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

    .layout-10 {
      width: 210mm;
      min-height: 297mm;
      padding: 11mm 15mm;
      gap: 3.5mm 6mm;
    }

    .layout-8 {
      width: 210mm;
      min-height: 297mm;
      padding: 24mm 15mm;
      gap: 9mm 6mm;
    }

    .layout-12 {
      width: 297mm;
      min-height: 210mm;
      padding: 6mm 10mm;
      gap: 3mm 4mm;
    }

    .cr80-card-wrapper {
      width: 85.6mm;
      height: 54.0mm;
      position: relative;
      page-break-inside: avoid;
      break-inside: avoid;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .cr80-card {
      width: 85.6mm;
      height: 54.0mm;
      background: #ffffff;
      border-radius: 3.8mm;
      border: 1px solid #cbd5e1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      overflow: hidden;
      position: relative;
      box-shadow: 0 2px 8px rgba(0, 56, 112, 0.08);
      transition: transform 0.15s ease, box-shadow 0.15s ease;
      box-sizing: border-box;
    }

    .cr80-card:hover {
      box-shadow: 0 6px 18px rgba(0, 56, 112, 0.16);
    }

    /* Dashed Cutting Guide surrounding the card */
    .with-cut-guides .cr80-card-wrapper::after {
      content: "";
      position: absolute;
      top: -2.2mm;
      left: -2.2mm;
      right: -2.2mm;
      bottom: -2.2mm;
      border: 1px dashed #94a3b8;
      pointer-events: none;
      border-radius: 5mm;
    }

    /* 1. HEADER SECTION */
    .card-header-container {
      height: 12.2mm;
      display: flex;
      align-items: stretch;
      justify-content: space-between;
      background: #ffffff;
      position: relative;
      padding: 0;
      overflow: hidden;
    }

    .header-logo-area {
      width: 30mm;
      padding: 1.2mm 1mm 1mm 2.8mm;
      display: flex;
      align-items: center;
      justify-content: flex-start;
    }

    .header-logo-area img {
      max-width: 100%;
      max-height: 10mm;
      object-fit: contain;
    }

    .header-right-col {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: stretch;
    }

    .header-navy-bar {
      background-color: #003870;
      color: #ffffff;
      font-size: 5.6pt;
      font-weight: 800;
      letter-spacing: 0.03em;
      text-align: center;
      padding: 1.3mm 2mm 1.1mm;
      text-transform: uppercase;
      white-space: nowrap;
      line-height: 1.1;
      border-top-right-radius: 3.5mm;
    }

    .header-sub-bar {
      height: 5.6mm;
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #ffffff;
      position: relative;
      overflow: hidden;
    }

    .sub-green-badge {
      background-color: #6bb82a;
      color: #ffffff;
      font-size: 5.6pt;
      font-weight: 800;
      letter-spacing: 0.05em;
      padding: 0 4mm 0 2.5mm;
      height: 100%;
      display: flex;
      align-items: center;
      clip-path: polygon(0 0, 100% 0, calc(100% - 3.2mm) 100%, 0 100%);
      white-space: nowrap;
      text-transform: uppercase;
    }

    .sub-teal-slant {
      width: 3.5mm;
      height: 100%;
      background-color: #008fa0;
      margin-left: -2.2mm;
      clip-path: polygon(0 0, 100% 0, calc(100% - 3.2mm) 100%, 0 100%);
    }

    .sub-dots-pattern {
      display: grid;
      grid-template-columns: repeat(3, 2.6px);
      grid-gap: 2.2px;
      padding-right: 3.5mm;
      margin-left: auto;
    }

    .p-dot {
      width: 2.6px;
      height: 2.6px;
      background-color: #6bb82a;
      border-radius: 50%;
      display: block;
    }

    /* 2. TABLE ROWS (4 ROWS) */
    .card-table-section {
      display: flex;
      flex-direction: column;
      background: #ffffff;
      border-top: 1px solid #003870;
    }

    .attr-row {
      display: flex;
      align-items: center;
      height: 5.4mm;
      border-bottom: 1px solid #cbd5e1;
      background: #ffffff;
    }

    .attr-row.row-bottom-last {
      border-bottom: 2px solid #6bb82a;
    }

    .attr-icon-box {
      width: 6.8mm;
      height: 100%;
      background-color: #003870;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .attr-svg {
      width: 3.2mm;
      height: 3.2mm;
      color: #ffffff;
      fill: #ffffff;
    }

    .attr-label {
      width: 23mm;
      padding-left: 2.5mm;
      font-size: 5.2pt;
      font-weight: 800;
      color: #003870;
      letter-spacing: 0.02em;
      flex-shrink: 0;
      text-transform: uppercase;
    }

    .attr-divider {
      font-size: 6pt;
      font-weight: 800;
      color: #003870;
      margin: 0 2mm 0 1mm;
      flex-shrink: 0;
    }

    .attr-value {
      flex: 1;
      font-size: 5.5pt;
      font-weight: 800;
      color: #000000;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      padding-right: 2.5mm;
    }

    .attr-value-rek {
      font-family: 'JetBrains Mono', 'Plus Jakarta Sans', monospace, sans-serif;
      letter-spacing: 0.02em;
    }

    /* 3. BOTTOM SECTION: WARNING & QR CODE */
    .card-bottom-section {
      flex: 1;
      display: flex;
      align-items: stretch;
      position: relative;
      background: #ffffff;
      overflow: hidden;
    }

    .bottom-warning-col {
      flex: 1;
      padding: 1.8mm 2mm 1.5mm 2.8mm;
      position: relative;
      z-index: 2;
      border-right: 1px solid #cbd5e1;
      display: flex;
      align-items: center;
    }

    .warning-content {
      display: flex;
      align-items: center;
      gap: 2mm;
    }

    .warning-shield-icon {
      width: 5.5mm;
      height: 7mm;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .shield-svg {
      width: 100%;
      height: 100%;
    }

    .warning-text-wrap {
      flex: 1;
      line-height: 1.15;
    }

    .warning-heading {
      color: #dc2626;
      font-size: 5.2pt;
      font-weight: 800;
      letter-spacing: 0.04em;
      margin-bottom: 0.4mm;
      text-transform: uppercase;
    }

    .warning-body {
      color: #334155;
      font-size: 3.5pt;
      font-weight: 500;
      line-height: 1.2;
    }

    .bottom-qr-col {
      width: 25.5mm;
      flex-shrink: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 1.2mm 1mm;
      position: relative;
      z-index: 2;
    }

    .qr-canvas-holder {
      width: 14.5mm;
      height: 14.5mm;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #ffffff;
    }

    .qr-canvas-holder canvas,
    .qr-canvas-holder img {
      width: 100% !important;
      height: 100% !important;
      display: block;
    }

    .scan-pill-badge {
      background-color: #007a3d;
      color: #ffffff;
      border-radius: 10px;
      padding: 0.5mm 2.2mm;
      display: inline-flex;
      align-items: center;
      gap: 1mm;
      margin-top: 1mm;
      font-size: 3.5pt;
      font-weight: 800;
      letter-spacing: 0.02em;
      white-space: nowrap;
    }

    .phone-svg {
      width: 2.4mm;
      height: 2.4mm;
      fill: #ffffff;
    }

    /* Bottom Decorative Waves */
    .bottom-wave-decor {
      position: absolute;
      left: 0;
      bottom: 0;
      width: 100%;
      height: 6.5mm;
      z-index: 1;
      pointer-events: none;
    }

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
      .alert {
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

  <!-- TOP TOOLBAR -->
  <header class="top-toolbar py-2 px-3 no-print">
    <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center gap-2">
      
      <!-- Kiri: Brand & Info -->
      <div class="d-flex align-items-center gap-3">
        <a href="<?= e(module_url('assets.php')) ?>" class="btn btn-sm btn-outline-secondary" title="Kembali">
          <i class="bi bi-arrow-left"></i>
        </a>
        <div>
          <div class="toolbar-title d-flex align-items-center gap-2">
            <i class="bi bi-credit-card-2-front text-primary"></i>
            <span>Cetak Kartu Inventaris CR80</span>
            <span class="badge bg-primary text-white" style="font-size: 0.72rem; font-weight: 600;">Standard ATM 85.6x54mm</span>
          </div>
          <div class="text-muted small" style="font-size: 0.74rem;">
            Tabel: <strong><?= e($source === 'inventaris_kartu' ? 'inventaris_kartu (Khusus Kartu)' : 'assets (Asset Registry)') ?></strong> · Total: <strong><?= count($cardsData) ?></strong> unit kartu
          </div>
        </div>
      </div>

      <!-- Tengah: Filter & Layout -->
      <div class="d-flex align-items-center gap-2 flex-wrap">
        
        <!-- Pilihan Tabel / Sumber Data -->
        <div class="btn-group btn-group-sm" role="group">
          <a href="<?= e(card_url(['source' => 'inventaris_kartu'])) ?>" class="btn <?= $source === 'inventaris_kartu' ? 'btn-primary fw-bold' : 'btn-outline-secondary' ?>" title="Data dari tabel khusus inventaris_kartu (5 Kartu Utama)">
            <i class="bi bi-table me-1"></i> Tabel Inventaris Kartu
          </a>
          <a href="<?= e(card_url(['source' => 'assets'])) ?>" class="btn <?= $source === 'assets' ? 'btn-primary fw-bold' : 'btn-outline-secondary' ?>" title="Data dari katalog komputer Asset Registry">
            <i class="bi bi-pc-display me-1"></i> Dari Asset Registry
          </a>
        </div>

        <!-- Layout Selector -->
        <div class="btn-group btn-group-sm" role="group">
          <a href="<?= e(card_url(['layout' => '8'])) ?>" class="btn <?= $layout === '8' ? 'btn-dark' : 'btn-outline-secondary' ?>" title="8 Kartu per Lembar A4 Portrait">
            8 / A4
          </a>
          <a href="<?= e(card_url(['layout' => '10'])) ?>" class="btn <?= $layout === '10' ? 'btn-dark' : 'btn-outline-secondary' ?>" title="10 Kartu per Lembar A4 Portrait">
            10 / A4
          </a>
          <a href="<?= e(card_url(['layout' => '12'])) ?>" class="btn <?= $layout === '12' ? 'btn-dark' : 'btn-outline-secondary' ?>" title="12 Kartu per Lembar A4 Landscape">
            12 / A4
          </a>
        </div>

        <!-- Toggle Garis Potong -->
        <div class="form-check form-switch ms-1 d-none d-md-inline-block">
          <input class="form-check-input" type="checkbox" id="cutGuideToggle" checked onchange="toggleCutGuides(this)">
          <label class="form-check-label small" for="cutGuideToggle" style="font-size: 0.74rem;">Garis Potong</label>
        </div>
      </div>

      <!-- Kanan: Aksi -->
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <button type="button" class="btn btn-sm btn-success fw-semibold" data-bs-toggle="modal" data-bs-target="#addCardModal">
          <i class="bi bi-plus-lg me-1"></i> Tambah Data
        </button>

        <button type="button" class="btn btn-sm btn-outline-info fw-semibold" onclick="openLivePreviewModal()">
          <i class="bi bi-eye me-1"></i> Pratinjau
        </button>

        <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold" data-bs-toggle="modal" data-bs-target="#quickEditModal">
          <i class="bi bi-pencil-square me-1"></i> Set Lokasi
        </button>

        <div class="dropdown">
          <button class="btn btn-sm btn-outline-secondary dropdown-toggle fw-semibold" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-download me-1"></i> Ekspor
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.85rem;">
            <li>
              <a class="dropdown-item" href="<?= e(card_url(['export' => 'doc'])) ?>">
                <i class="bi bi-file-earmark-word-fill text-primary me-2"></i> Dokumen Microsoft Word (.doc)
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="<?= e(card_url(['export' => 'csv'])) ?>">
                <i class="bi bi-file-earmark-spreadsheet-fill text-success me-2"></i> Spreadsheet CSV / Excel
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#googleSheetsModal">
                <i class="bi bi-google text-danger me-2"></i> Sinkron Google Sheets
              </button>
            </li>
          </ul>
        </div>

        <button type="button" class="btn btn-sm btn-primary fw-bold shadow-sm" onclick="window.print()">
          <i class="bi bi-printer-fill me-1"></i> Cetak / PDF
        </button>
      </div>

    </div>
  </header>

  <?php if ($flashMsg): ?>
    <div class="alert alert-success m-3 py-2 px-3 small no-print"><i class="bi bi-check-circle-fill me-2"></i><?= e($flashMsg) ?></div>
  <?php endif; ?>
  <?php if ($flashErr): ?>
    <div class="alert alert-danger m-3 py-2 px-3 small no-print"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($flashErr) ?></div>
  <?php endif; ?>

  <!-- PRINT STAGE -->
  <main class="print-stage">
    <?php if (empty($cardsData)): ?>
      <div class="card p-5 text-center my-5 shadow-sm" style="max-width: 500px;">
        <i class="bi bi-credit-card-2-front fs-1 text-muted mb-3 opacity-50"></i>
        <h5 class="fw-bold text-dark">Tidak Ada Data Kartu</h5>
        <p class="text-muted small">Belum ada data inventaris yang dapat dicetak.</p>
        <div class="mt-3">
          <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCardModal">
            <i class="bi bi-plus-lg me-1"></i> Tambah Data Sekarang
          </button>
        </div>
      </div>
    <?php else: ?>
      
      <?php
        $cardsPerPage = (int)$layout;
        $pages = array_chunk($cardsData, $cardsPerPage);
      ?>

      <div id="sheetsContainer" class="with-cut-guides">
        <?php foreach ($pages as $pIdx => $pageCards): ?>
          <div class="a4-sheet layout-<?= e($layout) ?>" data-page="<?= $pIdx + 1 ?>">
            <?php foreach ($pageCards as $card): ?>
              <?php $cIdx = $card['index']; ?>
              <div class="cr80-card-wrapper" id="card-wrap-<?= $cIdx ?>" data-card-id="<?= $card['id'] ?>">
                <div class="cr80-card">
                  
                  <!-- 1. Header Kartu -->
                  <div class="card-header-container">
                    <div class="header-logo-area">
                      <img src="<?= e($logoUrl) ?>" alt="Logo Bank Mitra" loading="lazy">
                    </div>
                    <div class="header-right-col">
                      <div class="header-navy-bar">
                        PT BPR MITRATAMA ARTHABUANA
                      </div>
                      <div class="header-sub-bar">
                        <div class="sub-green-badge">
                          ASSET TETAP
                        </div>
                        <div class="sub-teal-slant"></div>
                        <div class="sub-dots-pattern">
                          <span class="p-dot"></span><span class="p-dot"></span><span class="p-dot"></span>
                          <span class="p-dot"></span><span class="p-dot"></span><span class="p-dot"></span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- 2. Tabel 4 Baris Atribut -->
                  <div class="card-table-section">
                    <!-- Row 1: NOMOR ASSET -->
                    <div class="attr-row">
                      <div class="attr-icon-box">
                        <svg viewBox="0 0 16 16" fill="currentColor" class="attr-svg"><path d="M6 1a1 1 0 0 0-.707.293L.293 6.293a1 1 0 0 0 0 1.414l6 6a1 1 0 0 0 1.414 0l5-5A1 1 0 0 0 13 8V2a1 1 0 0 0-1-1H6zm-2 4a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z"/></svg>
                      </div>
                      <div class="attr-label">NOMOR ASSET</div>
                      <div class="attr-divider">|</div>
                      <div class="attr-value attr-value-rek"><?= e($card['nomor_gabungan']) ?></div>
                    </div>

                    <!-- Row 2: NAMA ASSET -->
                    <div class="attr-row">
                      <div class="attr-icon-box">
                        <svg viewBox="0 0 16 16" fill="currentColor" class="attr-svg"><path d="M1 3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V3zm1.5.5v7h11v-7h-11zM6 13.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5z"/></svg>
                      </div>
                      <div class="attr-label">NAMA ASSET</div>
                      <div class="attr-divider">|</div>
                      <div class="attr-value attr-value-nama" title="<?= e($card['nama']) ?>"><?= e($card['nama']) ?></div>
                    </div>

                    <!-- Row 3: TGL PEROLEHAN -->
                    <div class="attr-row">
                      <div class="attr-icon-box">
                        <svg viewBox="0 0 16 16" fill="currentColor" class="attr-svg"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/></svg>
                      </div>
                      <div class="attr-label">TGL PEROLEHAN</div>
                      <div class="attr-divider">|</div>
                      <div class="attr-value attr-value-tgl"><?= e($card['tgl']) ?></div>
                    </div>

                    <!-- Row 4: LOKASI -->
                    <div class="attr-row row-bottom-last">
                      <div class="attr-icon-box">
                        <svg viewBox="0 0 16 16" fill="currentColor" class="attr-svg"><path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/></svg>
                      </div>
                      <div class="attr-label">LOKASI</div>
                      <div class="attr-divider">|</div>
                      <div class="attr-value attr-value-lokasi" title="<?= e($card['lokasi']) ?>"><?= e($card['lokasi']) ?></div>
                    </div>
                  </div>

                  <!-- 3. Bottom Section: Perhatian & QR Code -->
                  <div class="card-bottom-section">
                    <!-- Kolom Kiri: Peringatan HRD -->
                    <div class="bottom-warning-col">
                      <div class="warning-content">
                        <div class="warning-shield-icon">
                          <svg viewBox="0 0 24 24" fill="none" stroke="#003870" stroke-width="2" class="shield-svg">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" fill="#ffffff"/>
                            <path d="M12 8v5M12 16v.5" stroke="#003870" stroke-width="2.5" stroke-linecap="round"/>
                          </svg>
                        </div>
                        <div class="warning-text-wrap">
                          <div class="warning-heading">PERHATIAN</div>
                          <div class="warning-body card-disclaimer-val">
                            Perhatian Dilarang memindahkan barang inventaris ini tanpa seizin Human Resource Departement (HRD) Bank Mitra
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Kolom Kanan: QR Code & Badge -->
                    <div class="bottom-qr-col">
                      <div class="qr-canvas-holder">
                        <div class="qr-box-inner" id="qr-box-<?= $cIdx ?>" data-qr="<?= e($card['qr_url']) ?>"></div>
                      </div>
                      <div class="scan-pill-badge">
                        <svg viewBox="0 0 16 16" fill="currentColor" class="phone-svg"><path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z"/><path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/></svg>
                        <span>SCAN UNTUK INFO</span>
                      </div>
                    </div>

                    <!-- Bottom Wave Decoration -->
                    <svg class="bottom-wave-decor" viewBox="0 0 856 120" preserveAspectRatio="none">
                      <path d="M 0,55 C 100,50 180,95 270,95 C 330,95 380,85 450,110 L 450,120 L 0,120 Z" fill="#6bb82a" />
                      <path d="M 420,120 C 530,120 620,115 720,80 C 780,60 820,40 856,20 L 856,120 L 420,120 Z" fill="#003870" />
                    </svg>
                  </div>

                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>
  </main>

  <!-- MODAL: TAMBAH DATA KARTU INVENTARIS BARU -->
  <div class="modal fade" id="addCardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
        <form method="post">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="form_action" value="add_card">
          <div class="modal-header py-2 px-3 bg-primary text-white">
            <h6 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Tambah Data Kartu Inventaris</h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-3">
            <div class="mb-2">
              <label class="form-label small fw-bold text-dark">Nomor Rekening / Kode Inventaris</label>
              <input type="text" name="nomor_rekening" class="form-control form-control-sm font-monospace" placeholder="Contoh: 01.05.0494" required>
            </div>
            <div class="mb-2">
              <label class="form-label small fw-bold text-dark">Nama Barang / Perangkat</label>
              <input type="text" name="nama_barang" class="form-control form-control-sm" placeholder="Contoh: PC Desktop Kasir 2" required>
            </div>
            <div class="row g-2 mb-2">
              <div class="col-6">
                <label class="form-label small fw-bold text-dark">Tanggal Perolehan</label>
                <input type="date" name="tanggal_perolehan" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="col-6">
                <label class="form-label small fw-bold text-dark">Lokasi Penempatan</label>
                <input type="text" name="lokasi" class="form-control form-control-sm" value="KPO / Operasional" required>
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label small fw-bold text-dark">Pengguna / PIC</label>
              <input type="text" name="pengguna" class="form-control form-control-sm" value="Umum / Pool">
            </div>
            <div class="mb-2">
              <label class="form-label small fw-bold text-dark">Barcode Data / Target URL QR</label>
              <input type="text" name="barcode_data" class="form-control form-control-sm" placeholder="https://canva.link/... atau teks barcode">
              <div class="form-text" style="font-size: 0.72rem;">Jika dikosongkan, QR Code akan otomatis diarahkan ke URL verifikasi sistem.</div>
            </div>
          </div>
          <div class="modal-footer py-2 px-3">
            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-sm btn-primary fw-bold">Simpan Kartu</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL: LIVE PREVIEW INTERAKTIF -->
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
          <div class="d-flex justify-content-center mb-3">
            <div id="previewCardTarget" style="transform: scale(1.35); transform-origin: top center; margin-bottom: 24mm;"></div>
          </div>
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
            <i class="bi bi-printer-fill me-1"></i> Cetak Sekarang
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL: INPUT LOKASI CEPAT & MASSAL -->
  <div class="modal fade" id="quickEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
        <div class="modal-header py-2 px-3 bg-dark text-white">
          <h6 class="modal-title fw-bold"><i class="bi bi-sliders me-2"></i>Kustomisasi Teks Kartu</h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-3">
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

  <!-- MODAL: INTEGRASI GOOGLE SHEETS -->
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
    var headers = ['id', 'nomor_rekening', 'nama_barang', 'tanggal_perolehan', 'barcode_data', 'created_at'];
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

  <input type="hidden" id="csrfToken" value="<?= e(csrf_token()) ?>">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    const CARDS_DATA = <?= json_encode($cardsData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    let currentPreviewIndex = 0;

    document.addEventListener("DOMContentLoaded", function() {
      const qrBoxes = document.querySelectorAll(".qr-box-inner");
      qrBoxes.forEach(box => {
        const qrUrl = box.getAttribute("data-qr");
        if (qrUrl) {
          new QRCode(box, {
            text: qrUrl,
            width: 58,
            height: 58,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
          });
        }
      });
    });

    function toggleCutGuides(el) {
      const container = document.getElementById("sheetsContainer");
      if (!container) return;
      if (el.checked) {
        container.classList.add("with-cut-guides");
      } else {
        container.classList.remove("with-cut-guides");
      }
    }

    function openLivePreviewModal() {
      if (!CARDS_DATA || CARDS_DATA.length === 0) return;
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

      if (counter) {
        counter.innerText = `Kartu ${currentPreviewIndex + 1} dari ${CARDS_DATA.length}`;
      }
      const prevBtn = document.getElementById("prevCardBtn");
      const nextBtn = document.getElementById("nextCardBtn");
      if (prevBtn) prevBtn.disabled = (currentPreviewIndex === 0);
      if (nextBtn) nextBtn.disabled = (currentPreviewIndex === CARDS_DATA.length - 1);
    }

    function navigateCard(step) {
      const newIndex = currentPreviewIndex + step;
      if (newIndex >= 0 && newIndex < CARDS_DATA.length) {
        currentPreviewIndex = newIndex;
        renderPreviewCard();
      }
    }

    function applyBulkLocation() {
      const locVal = document.getElementById("bulkLocationInput").value.trim();
      if (!locVal) {
        alert("Silakan ketik nama lokasi terlebih dahulu.");
        return;
      }
      document.querySelectorAll(".attr-value-lokasi").forEach(el => {
        el.innerText = locVal;
        el.setAttribute("title", locVal);
      });
      CARDS_DATA.forEach(c => {
        c.lokasi = locVal;
      });
      alert(`Lokasi "${locVal}" berhasil diterapkan ke semua kartu.`);
    }

    function applyBulkDisclaimer() {
      const discVal = document.getElementById("bulkDisclaimerInput").value.trim();
      if (!discVal) return;
      document.querySelectorAll(".card-disclaimer-val").forEach(el => {
        el.innerText = discVal;
      });
      alert("Catatan perhatian footer berhasil diperbarui.");
    }

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

    function copyGasScript() {
      const area = document.getElementById("gasScriptArea");
      area.select();
      navigator.clipboard.writeText(area.value);
      alert("Kode Google Apps Script berhasil disalin ke clipboard!");
    }
  </script>
</body>
</html>
