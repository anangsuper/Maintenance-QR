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
        $lokasi = trim((string)($r['lokasi'] ?? ''));
        if ($lokasi === '' || $lokasi === 'KPO' || $lokasi === 'KPO / Operasional') {
            $cBranch = get_cabang_from_nomor_rekening($rek);
            $lokasi = $cBranch ? $cBranch['lokasi'] : 'Kantor Pusat (KPO)';
        }
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
    fputcsv($out, ['No', 'ID', 'Nomor Rekening / Kode', 'Nama Barang', 'Tanggal Perolehan', 'Barcode Data (URL)', 'Lokasi']);
    foreach ($cardsData as $c) {
        fputcsv($out, [
            $c['index'],
            $c['id'],
            sanitize_csv_cell($c['kode']),
            sanitize_csv_cell($c['nama']),
            sanitize_csv_cell($c['tgl']),
            sanitize_csv_cell($c['barcode_data']),
            sanitize_csv_cell($c['lokasi'])
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
    echo '<head><meta charset="utf-8"><title>Kartu Inventaris CR80 - PT BPR MITRATAMA ARTHABUANA</title>';
    echo '<!--[if gte mso 9]>
    <xml>
      <w:WordDocument>
        <w:View>Print</w:View>
        <w:Zoom>100</w:Zoom>
        <w:DoNotOptimizeForBrowser/>
      </w:WordDocument>
    </xml>
    <![endif]-->';
    echo '<style>
        @page Section1 {
            size: 210mm 297mm; /* A4 Portrait */
            margin: 10mm 8mm 10mm 8mm;
            mso-header-margin: 0mm;
            mso-footer-margin: 0mm;
        }
        div.Section1 { page: Section1; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 8pt; color: #1e293b; margin: 0; padding: 0; background: #FFFFFF; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        .word-page-break { page-break-before: always; mso-special-character: line-break; }
    </style>';
    echo '</head><body>';
    echo '<div class="Section1">';
    echo '<table width="100%" border="0" cellspacing="0" cellpadding="6" style="margin: 0 auto; width: 100%; border-collapse: collapse;">';
    
    $colCount = 0;
    $cardIndexOnPage = 0;
    foreach ($cardsData as $c) {
        $qrTarget = !empty($c['barcode_data']) ? $c['barcode_data'] : ($c['qr_url'] ?? module_url('scan.php', ['t' => get_static_qr_token((int)($c['id'] ?? 0))]));
        $qrImgUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=110x110&margin=0&data=' . urlencode($qrTarget);

        if ($colCount % 2 === 0) {
            echo '<tr>';
        }
        
        echo '<td width="50%" align="center" valign="top" style="padding: 6px 4px;">';
        
        // Kotak Fisik Kartu CR80 Standar ATM (85.6mm x 54.0mm)
        echo '<table width="324" height="204" border="1" bordercolor="#003B73" cellspacing="0" cellpadding="0" style="width: 85.6mm; height: 54.0mm; border: 1.5pt solid #003B73; border-collapse: collapse; background-color: #FFFFFF; table-layout: fixed; margin: 0 auto;">';
        
        // 1. Header Kartu
        echo '<tr height="44"><td colspan="2" style="border-bottom: 1.5pt solid #003B73; padding: 0;">';
        echo '<table width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse: collapse;"><tr>';
        echo '<td width="86" height="44" align="center" valign="middle" style="background-color: #FFFFFF; border-right: 1pt solid #E2E8F0; padding: 2px;">';
        echo '<img src="' . e($logoUri) . '" width="80" height="25" border="0" style="width: 80px; height: 25px; display: block; margin: auto;" alt="Logo Bank Mitra">';
        echo '</td>';
        echo '<td width="238" height="44" valign="top" style="padding: 0;">';
        echo '<table width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse: collapse;">';
        echo '<tr height="22" bgcolor="#003B73">';
        echo '<td align="center" valign="middle" style="background-color: #003B73; padding: 1px 4px;">';
        echo '<font face="Arial, sans-serif" size="1" color="#FFFFFF" style="font-size: 7.5pt; font-weight: bold; letter-spacing: 0.3px;"><b>PT BPR MITRATAMA ARTHABUANA</b></font>';
        echo '</td></tr>';
        echo '<tr height="22" bgcolor="#FFFFFF">';
        echo '<td valign="middle" style="background-color: #FFFFFF; padding: 1px 6px;">';
        echo '<table border="0" cellspacing="0" cellpadding="0" style="border-collapse: collapse;"><tr>';
        echo '<td bgcolor="#7AC142" style="background-color: #7AC142; padding: 2px 10px; border-radius: 2px;">';
        echo '<font face="Arial, sans-serif" size="1" color="#FFFFFF" style="font-size: 6.8pt; font-weight: bold;"><b>ASSET TETAP</b></font>';
        echo '</td></tr></table>';
        echo '</td></tr>';
        echo '</table>';
        echo '</td></tr></table>';
        echo '</td></tr>';
        
        // 2. Data Fields (Nomor Asset, Nama Asset, Tgl Perolehan, Lokasi)
        echo '<tr height="95"><td colspan="2" valign="top" style="padding: 6px 8px 2px 8px;">';
        echo '<table width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse: collapse;">';
        
        echo '<tr height="22">';
        echo '<td width="92" valign="middle"><font face="Arial" size="1" color="#003B73" style="font-size: 6.8pt; font-weight: bold;"><b>NOMOR ASSET</b></font></td>';
        echo '<td width="10" align="center" valign="middle"><font face="Arial" size="1" color="#94A3B8">|</font></td>';
        echo '<td valign="middle"><font face="Arial" size="1" color="#003B73" style="font-size: 7.2pt; font-weight: bold;"><b>' . e($c['nomor_gabungan']) . '</b></font></td>';
        echo '</tr>';

        echo '<tr height="22">';
        echo '<td width="92" valign="middle"><font face="Arial" size="1" color="#003B73" style="font-size: 6.8pt; font-weight: bold;"><b>NAMA ASSET</b></font></td>';
        echo '<td width="10" align="center" valign="middle"><font face="Arial" size="1" color="#94A3B8">|</font></td>';
        echo '<td valign="middle"><font face="Arial" size="1" color="#0F172A" style="font-size: 7.0pt; font-weight: bold;"><b>' . e($c['nama']) . '</b></font></td>';
        echo '</tr>';

        echo '<tr height="22">';
        echo '<td width="92" valign="middle"><font face="Arial" size="1" color="#003B73" style="font-size: 6.8pt; font-weight: bold;"><b>TGL PEROLEHAN</b></font></td>';
        echo '<td width="10" align="center" valign="middle"><font face="Arial" size="1" color="#94A3B8">|</font></td>';
        echo '<td valign="middle"><font face="Arial" size="1" color="#334155" style="font-size: 7.0pt;"><b>' . e($c['tgl']) . '</b></font></td>';
        echo '</tr>';

        echo '<tr height="22">';
        echo '<td width="92" valign="middle"><font face="Arial" size="1" color="#003B73" style="font-size: 6.8pt; font-weight: bold;"><b>LOKASI</b></font></td>';
        echo '<td width="10" align="center" valign="middle"><font face="Arial" size="1" color="#94A3B8">|</font></td>';
        echo '<td valign="middle"><font face="Arial" size="1" color="#003B73" style="font-size: 7.0pt; font-weight: bold;"><b>' . e($c['lokasi']) . '</b></font></td>';
        echo '</tr>';
        
        echo '</table>';
        echo '</td></tr>';
        
        // 3. Bottom Section: Disclaimer di Kiri, QR Code di Kanan
        echo '<tr height="62">';
        echo '<td width="238" valign="bottom" style="padding: 2px 6px 4px 6px;">';
        echo '<table width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse: collapse;">';
        echo '<tr><td style="padding-bottom: 3px;">';
        echo '<table width="100%" border="0" cellspacing="0" cellpadding="0"><tr><td height="2" bgcolor="#7AC142" style="background-color: #7AC142; font-size: 1px; line-height: 1px;">&nbsp;</td></tr></table>';
        echo '</td></tr>';
        echo '<tr><td style="border: 0.75pt solid #003B73; background-color: #F8FAFC; padding: 3px 5px;">';
        echo '<font face="Arial" size="1" color="#D92D20" style="font-size: 5.5pt; font-weight: bold;"><b>PERHATIAN: </b></font>';
        echo '<font face="Arial" size="1" color="#334155" style="font-size: 5.2pt;">Perhatian Dilarang memindahkan barang inventaris ini tanpa seizin Human Resource Departement (HRD) Bank Mitra</font>';
        echo '</td></tr></table>';
        echo '</td>';
        
        echo '<td width="86" align="center" valign="middle" style="padding: 2px 4px 4px 2px;">';
        echo '<img src="' . e($qrImgUrl) . '" width="50" height="50" border="1" style="border: 1pt solid #003B73; display: block; margin: auto;" alt="QR Code">';
        echo '<div style="font-family: Arial; font-size: 4.8pt; color: #003B73; font-weight: bold; margin-top: 2px; text-align: center;">SCAN UNTUK INFO</div>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        echo '</td>';
        
        $colCount++;
        $cardIndexOnPage++;
        
        if ($colCount % 2 === 0) {
            echo '</tr>';
            // Setiap 10 kartu (5 baris), lakukan page break di Word
            if ($cardIndexOnPage % 10 === 0 && $colCount < count($cardsData)) {
                echo '</table><br clear="all" style="mso-special-character:line-break;page-break-before:always"><table width="100%" border="0" cellspacing="0" cellpadding="6" style="margin: 0 auto; width: 100%; border-collapse: collapse;">';
            }
        }
    }
    
    if ($colCount % 2 !== 0) {
        echo '<td width="50%">&nbsp;</td></tr>';
    }
    
    echo '</table>';
    echo '</div>';
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

    /* Ukuran Standar Kartu ATM (CR80: 85.6mm x 54.0mm) */
    .atm-card {
      width: 85.6mm !important;
      height: 54.0mm !important;
      box-sizing: border-box !important;
      border: 1.2px solid #e2e8f0 !important;
      border-radius: 8px !important;
      background: #ffffff !important;
      font-family: "Plus Jakarta Sans", Arial, sans-serif !important;
      color: #0f172a !important;
      overflow: hidden !important;
      position: relative !important;
      display: flex !important;
      flex-direction: column !important;
      padding: 0 !important;
      margin: 0 !important;
      box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08) !important;
      outline: 0.8px dashed #cbd5e1 !important;
      outline-offset: 1.5mm !important;
    }

    /* 1. Header Section */
    .card-header-sec {
      display: flex !important;
      width: 100% !important;
      height: 11.5mm !important;
      border-bottom: 1.5px solid #003b73 !important;
      box-sizing: border-box !important;
    }
    .header-logo-box {
      width: 27% !important;
      height: 100% !important;
      border-right: 1px solid #e2e8f0 !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      background: #ffffff !important;
      box-sizing: border-box !important;
      padding: 0.8mm !important;
    }
    .header-logo-box img {
      max-height: 9.8mm !important;
      max-width: 95% !important;
      object-fit: contain !important;
    }
    .header-title-box {
      width: 73% !important;
      height: 100% !important;
      display: flex !important;
      flex-direction: column !important;
      box-sizing: border-box !important;
    }
    .header-main-title {
      background-color: #003b73 !important;
      color: #ffffff !important;
      font-weight: 800 !important;
      font-size: 7.8pt !important;
      text-align: center !important;
      height: 5.5mm !important;
      line-height: 5.5mm !important;
      text-transform: uppercase !important;
      letter-spacing: 0.2px !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    .header-sub-sec {
      height: 6.0mm !important;
      background: #ffffff !important;
      display: flex !important;
      align-items: center !important;
      justify-content: space-between !important;
      padding: 0 !important;
      box-sizing: border-box !important;
      position: relative !important;
    }
    /* Pita Banner Hijau & Stripe Teal */
    .green-banner-wrapper {
      position: relative !important;
      display: flex !important;
      height: 100% !important;
      width: 75% !important;
    }
    .teal-stripe {
      position: absolute !important;
      top: 0 !important;
      left: 0 !important;
      width: 100% !important;
      height: 100% !important;
      background-color: #009ca6 !important;
      clip-path: polygon(0 0, 78% 0, 71% 100%, 0 100%) !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    .green-banner {
      position: absolute !important;
      top: 0 !important;
      left: 0 !important;
      width: 92% !important;
      height: 100% !important;
      background: #7ac142 !important;
      clip-path: polygon(0 0, 78% 0, 71% 100%, 0 100%) !important;
      color: #ffffff !important;
      font-weight: 800 !important;
      font-size: 7.2pt !important;
      letter-spacing: 0.5px !important;
      display: flex !important;
      align-items: center !important;
      padding-left: 2.5mm !important;
      box-sizing: border-box !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    .header-dots-container {
      display: flex !important;
      align-items: center !important;
      padding-right: 3mm !important;
      height: 100% !important;
    }
    .header-dots {
      display: grid !important;
      grid-template-columns: repeat(3, 1mm) !important;
      grid-gap: 0.8mm !important;
    }
    .header-dots span {
      width: 0.9mm !important;
      height: 0.9mm !important;
      background-color: #7ac142 !important;
      border-radius: 50% !important;
      display: block !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }

    /* 2. Baris Data (Fields) */
    .card-fields-sec {
      display: flex !important;
      flex-direction: column !important;
      width: 100% !important;
      padding: 0 !important;
      margin: 0 !important;
      box-sizing: border-box !important;
    }
    .card-field-row {
      display: flex !important;
      width: 100% !important;
      height: 6.125mm !important;
      border-bottom: 1px solid #e2e8f0 !important;
      box-sizing: border-box !important;
    }
    .field-icon-box {
      width: 8.5mm !important;
      height: 100% !important;
      background-color: #003b73 !important;
      color: #ffffff !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      box-sizing: border-box !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    .field-svg-icon {
      width: 3.5mm !important;
      height: 3.5mm !important;
      color: #ffffff !important;
    }
    .field-content-box {
      display: flex !important;
      align-items: center !important;
      width: 77.1mm !important;
      height: 100% !important;
      background: #ffffff !important;
      box-sizing: border-box !important;
    }
    .field-lbl {
      width: 25.5mm !important;
      color: #003b73 !important;
      font-weight: 800 !important;
      font-size: 7.0pt !important;
      display: flex !important;
      align-items: center !important;
      padding-left: 3.0mm !important;
      box-sizing: border-box !important;
      text-transform: uppercase !important;
      letter-spacing: 0.2px !important;
    }
    .field-sep {
      color: #7ac142 !important;
      font-weight: 800 !important;
      font-size: 9.0pt !important;
      margin: 0 1.0mm 0 2.0mm !important;
      display: flex !important;
      align-items: center !important;
    }
    .field-val {
      font-size: 7.0pt !important;
      color: #1e293b !important;
      font-weight: 800 !important;
      padding-left: 2.0mm !important;
      white-space: normal !important;
      line-height: 1.05 !important;
      overflow: hidden !important;
      display: -webkit-box !important;
      -webkit-line-clamp: 2 !important;
      -webkit-box-orient: vertical !important;
      text-transform: uppercase !important;
      flex-grow: 1;
      align-self: center !important;
      word-break: break-word !important;
    }

    /* 3. Bottom Section (Waves, Attention & QR Code) */
    .card-bottom-sec {
      display: flex !important;
      width: 100% !important;
      height: 18.0mm !important;
      border-top: 1.5px solid #7ac142 !important;
      background-color: #ffffff !important;
      box-sizing: border-box !important;
      position: relative !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    .card-waves {
      position: absolute !important;
      bottom: 0 !important;
      left: 0 !important;
      width: 85.6mm !important;
      height: 7.5mm !important;
      z-index: 1 !important;
      pointer-events: none !important;
    }
    .bottom-left-attention {
      width: 66% !important;
      height: 100% !important;
      display: flex !important;
      align-items: center !important;
      padding: 1.0mm 1.5mm 1.0mm 3.0mm !important;
      box-sizing: border-box !important;
      z-index: 2 !important;
    }
    .attention-icon {
      margin-right: 2.0mm !important;
      display: flex !important;
      align-items: center !important;
    }
    .attention-svg-icon {
      width: 7.0mm !important;
      height: 7.0mm !important;
      color: #003b73 !important;
    }
    .attention-text-box {
      display: flex !important;
      flex-direction: column !important;
    }
    .attention-title {
      font-weight: 800 !important;
      font-size: 6.8pt !important;
      color: #dc2626 !important;
      margin-bottom: 0.3mm !important;
      text-transform: uppercase !important;
      letter-spacing: 0.3px !important;
    }
    .attention-desc {
      font-size: 4.6pt !important;
      line-height: 1.25 !important;
      color: #475569 !important;
      font-weight: 600 !important;
    }
    .attention-qr-separator {
      width: 1px !important;
      height: 13.0mm !important;
      background-color: #e2e8f0 !important;
      align-self: center !important;
      z-index: 2 !important;
    }
    .bottom-right-qr {
      width: 34% !important;
      height: 100% !important;
      display: flex !important;
      flex-direction: column !important;
      align-items: center !important;
      justify-content: center !important;
      box-sizing: border-box !important;
      padding: 0.8mm 1.0mm !important;
      z-index: 2 !important;
    }
    .qr-border-box {
      border: 1px solid #e2e8f0 !important;
      border-radius: 5px !important;
      padding: 0.5mm !important;
      background: #ffffff !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      box-shadow: 0 1px 3px rgba(0,0,0,0.02) !important;
      margin-bottom: 0.6mm !important;
    }
    .card-qr-img {
      width: 10.5mm !important;
      height: 10.5mm !important;
    }
    .card-qr-img canvas, .card-qr-img img {
      width: 10.5mm !important;
      height: 10.5mm !important;
      margin: 0 auto !important;
      display: block;
    }
    .scan-info-capsule {
      background-color: #008744 !important;
      color: #ffffff !important;
      border-radius: 12px !important;
      padding: 0.4mm 1.6mm !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 0.5mm !important;
      height: 2.8mm !important;
      width: 19.0mm !important;
      box-sizing: border-box !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    .scan-icon {
      width: 1.8mm !important;
      height: 1.8mm !important;
      color: #ffffff !important;
    }
    .scan-info-capsule span {
      font-size: 3.5pt !important;
      font-weight: 800 !important;
      white-space: nowrap !important;
      letter-spacing: 0.1px !important;
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
        <a href="<?= e(module_url('assets.php')) ?>" class="btn btn-sm btn-light border" title="Kembali">
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
          <a href="<?= e(card_url(['source' => 'inventaris_kartu'])) ?>" class="btn <?= $source === 'inventaris_kartu' ? 'btn-primary fw-semibold' : 'btn-light border' ?>" title="Data dari tabel khusus inventaris_kartu (5 Kartu Utama)">
            <i class="bi bi-table me-1"></i> Tabel Inventaris Kartu
          </a>
          <a href="<?= e(card_url(['source' => 'assets'])) ?>" class="btn <?= $source === 'assets' ? 'btn-primary fw-semibold' : 'btn-light border' ?>" title="Data dari katalog komputer Asset Registry">
            <i class="bi bi-pc-display me-1"></i> Dari Asset Registry
          </a>
        </div>

        <!-- Layout Selector -->
        <div class="btn-group btn-group-sm" role="group">
          <a href="<?= e(card_url(['layout' => '8'])) ?>" class="btn <?= $layout === '8' ? 'btn-dark fw-semibold' : 'btn-light border' ?>" title="8 Kartu per Lembar A4 Portrait">
            8 / A4
          </a>
          <a href="<?= e(card_url(['layout' => '10'])) ?>" class="btn <?= $layout === '10' ? 'btn-dark fw-semibold' : 'btn-light border' ?>" title="10 Kartu per Lembar A4 Portrait">
            10 / A4
          </a>
          <a href="<?= e(card_url(['layout' => '12'])) ?>" class="btn <?= $layout === '12' ? 'btn-dark fw-semibold' : 'btn-light border' ?>" title="12 Kartu per Lembar A4 Landscape">
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
        <button type="button" class="btn btn-sm btn-primary fw-semibold d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#addCardModal">
          <i class="bi bi-plus-lg"></i> Tambah Data
        </button>

        <button type="button" class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1" onclick="openLivePreviewModal()">
          <i class="bi bi-eye"></i> Pratinjau
        </button>

        <button type="button" class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#quickEditModal">
          <i class="bi bi-pencil-square"></i> Set Lokasi
        </button>

        <div class="dropdown">
          <button class="btn btn-sm btn-light border dropdown-toggle d-inline-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-download"></i> Ekspor
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

        <button type="button" class="btn btn-sm btn-primary fw-bold shadow-sm d-inline-flex align-items-center gap-1" onclick="window.print()">
          <i class="bi bi-printer-fill"></i> Cetak / PDF
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
                <div class="atm-card">
                  <!-- Top Header -->
                  <div class="card-header-sec">
                    <div class="header-logo-box">
                      <img src="<?= e($logoUrl) ?>" alt="Logo Bank Mitra" loading="lazy" onerror="this.src='logo.png'">
                    </div>
                    <div class="header-title-box">
                      <div class="header-main-title">PT BPR MITRATAMA ARTHABUANA</div>
                      <div class="header-sub-sec">
                        <div class="green-banner-wrapper">
                          <div class="teal-stripe"></div>
                          <div class="green-banner">ASSET TETAP</div>
                        </div>
                        <div class="header-dots-container">
                          <div class="header-dots">
                            <span></span><span></span><span></span>
                            <span></span><span></span><span></span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Data Fields Section -->
                  <div class="card-fields-sec">
                    <!-- 1. Nomor Asset -->
                    <div class="card-field-row">
                      <div class="field-icon-box">
                        <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M2 2a1 1 0 0 1 1-1h4.586a1 1 0 0 1 .707.293l7 7a1 1 0 0 1 0 1.414l-4.586 4.586a1 1 0 0 1-1.414 0l-7-7A1 1 0 0 1 2 8.586V2zm3.5 3.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/></svg>
                      </div>
                      <div class="field-content-box">
                        <span class="field-lbl">NOMOR ASSET</span>
                        <span class="field-sep">|</span>
                        <span class="field-val"><?= e($card['nomor_gabungan']) ?></span>
                      </div>
                    </div>

                    <!-- 2. Nama Asset -->
                    <div class="card-field-row">
                      <div class="field-icon-box">
                        <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M12 1H4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zM4 2h8a1 1 0 0 1 1 1v7H3V3a1 1 0 0 1 1-1z"/><path d="M8 12a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/></svg>
                      </div>
                      <div class="field-content-box">
                        <span class="field-lbl">NAMA ASSET</span>
                        <span class="field-sep">|</span>
                        <span class="field-val" title="<?= e($card['nama']) ?>"><?= e($card['nama']) ?></span>
                      </div>
                    </div>

                    <!-- 3. Tgl Perolehan -->
                    <div class="card-field-row">
                      <div class="field-icon-box">
                        <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/></svg>
                      </div>
                      <div class="field-content-box">
                        <span class="field-lbl">TGL PEROLEHAN</span>
                        <span class="field-sep">|</span>
                        <span class="field-val"><?= e($card['tgl']) ?></span>
                      </div>
                    </div>

                    <!-- 4. Lokasi -->
                    <div class="card-field-row">
                      <div class="field-icon-box">
                        <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/></svg>
                      </div>
                      <div class="field-content-box">
                        <span class="field-lbl">LOKASI</span>
                        <span class="field-sep">|</span>
                        <span class="field-val attr-value-lokasi" title="<?= e($card['lokasi']) ?>"><?= e($card['lokasi']) ?></span>
                      </div>
                    </div>
                  </div>

                  <!-- Bottom Section -->
                  <div class="card-bottom-sec">
                    <!-- Background Waves SVG -->
                    <div class="card-waves">
                      <svg viewBox="0 0 85.6 7.5" preserveAspectRatio="none" style="width: 100%; height: 100%; display: block;">
                        <path d="M 0 3 C 8 2.5, 18 5, 24 7.5 L 0 7.5 Z" fill="#7ac142" />
                        <path d="M 5 7.5 Q 32 3, 58 6.5 T 85.6 3.5 L 85.6 7.5 Z" fill="#003b73" />
                      </svg>
                    </div>
                    <!-- Left: Attention Disclaimer -->
                    <div class="bottom-left-attention">
                      <div class="attention-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#003b73" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="attention-svg-icon">
                          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                          <line x1="12" y1="8" x2="12" y2="13" />
                          <line x1="12" y1="16.5" x2="12.01" y2="16.5" stroke-width="3" />
                        </svg>
                      </div>
                      <div class="attention-text-box">
                        <div class="attention-title">Perhatian</div>
                        <div class="attention-desc card-disclaimer-val">Perhatian Dilarang memindahkan barang inventaris ini tanpa seizin Human Resource Departement (HRD) Bank Mitra</div>
                      </div>
                    </div>
                    <!-- Separator Line -->
                    <div class="attention-qr-separator"></div>
                    <!-- Right: QR Code & Scan Capsule -->
                    <div class="bottom-right-qr">
                      <div class="qr-border-box">
                        <div id="qr-box-<?= $cIdx ?>" class="card-qr-img qr-box-inner" data-qr="<?= e($card['qr_url']) ?>"></div>
                      </div>
                      <div class="scan-info-capsule">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="scan-icon">
                          <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                          <line x1="12" y1="18" x2="12.01" y2="18"/>
                        </svg>
                        <span>SCAN UNTUK INFO</span>
                      </div>
                    </div>
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
            <h6 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Tambah Kartu Inventaris</h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-3">
            <div class="mb-3">
              <label class="form-label small fw-bold text-dark">Nomor Rekening</label>
              <input type="text" name="nomor_rekening" id="printAddCardRekening" class="form-control form-control-sm font-monospace" placeholder="Contoh: 00.0.00000" maxlength="10" required oninput="handleRekeningInput(this, event)">
            </div>
            <div class="mb-3">
              <label class="form-label small fw-bold text-dark">Nama Barang</label>
              <input type="text" name="nama_barang" class="form-control form-control-sm" placeholder="Contoh: BANGUNAN GEDUNG KANTOR PUSA" required>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-bold text-dark">Tanggal Perolehan</label>
              <input type="date" name="tanggal_perolehan" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-bold text-dark">Lokasi Barang</label>
              <input type="text" name="lokasi" id="printAddCardLokasi" class="form-control form-control-sm" placeholder="Contoh: Kantor Pusat / Operasional / Ruang IT" onkeydown="handleLokasiKeydown(event, this)">
              <div class="form-text text-muted" style="font-size: 0.72rem;"><i class="bi bi-info-circle me-1"></i>Tekan <strong>Enter</strong> untuk otomatis menambahkan tanda <code> / </code></div>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-bold text-dark">Kode QR / Barcode (Data QR Code)</label>
              <input type="text" name="barcode_data" class="form-control form-control-sm font-monospace" placeholder="Salin/tempel kode QR di sini">
            </div>
          </div>
          <div class="modal-footer py-2 px-3 bg-light">
            <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-sm btn-primary fw-semibold px-3"><i class="bi bi-save me-1"></i> Simpan Data</button>
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
          <a href="kartu_template.html" target="_blank" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-filetype-html me-1"></i> Buka File Template (kartu_template.html)
          </a>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
            <button type="button" class="btn btn-sm btn-primary fw-bold" onclick="window.print()">
              <i class="bi bi-printer-fill me-1"></i> Cetak Sekarang
            </button>
          </div>
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
              <input type="text" id="bulkLocationInput" class="form-control" placeholder="Contoh: KPO / Lantai 2 / Ruang IT" onkeydown="handleLokasiKeydown(event, this)">
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

    function handleRekeningInput(el, event) {
      if (!el) return;
      const isDelete = event && event.inputType && event.inputType.startsWith("delete");
      let digits = el.value.replace(/[^0-9]/g, "");

      if (digits.length === 0) {
        el.value = "";
        return;
      }

      let p0 = digits.slice(0, 2);
      let p1 = digits.slice(2, 3);
      let p2 = digits.slice(3, 8); // Maksimal 5 digit setelah titik terakhir (00.0.00000)

      if (digits.length < 2) {
        el.value = digits;
      } else if (digits.length === 2) {
        el.value = isDelete ? p0 : p0 + ".";
      } else if (digits.length === 3) {
        el.value = isDelete ? (p0 + "." + p1) : (p0 + "." + p1 + ".");
      } else {
        el.value = p0 + "." + p1 + "." + p2;
      }

      if (p0.length === 2) {
        autoSuggestPrintBranch(p0);
      }
    }

    function autoSuggestPrintBranch(code) {
      const addLok = document.getElementById("printAddCardLokasi");
      if (!addLok || addLok.dataset.customized === "true") return;
      const map = {
        "01": "Kantor Pusat / ",
        "02": "Batulicin / ",
        "03": "Martapura / ",
        "04": "Tanjung / ",
        "05": "Handil Bakti / "
      };
      if (map[code]) {
        addLok.value = map[code];
      }
    }

    function handleLokasiKeydown(e, el) {
      if (!e || !el) return;
      if (e.key === "Enter") {
        e.preventDefault();
        el.dataset.customized = "true";
        const start = el.selectionStart !== null ? el.selectionStart : el.value.length;
        const end = el.selectionEnd !== null ? el.selectionEnd : el.value.length;
        const val = el.value;
        const before = val.substring(0, start);
        const after = val.substring(end);

        if (before.trimEnd().endsWith("/")) {
          if (!before.endsWith(" ")) {
            el.value = before + " " + after;
            el.selectionStart = el.selectionEnd = start + 1;
          }
          return;
        }

        let insert = " / ";
        if (before.length === 0) {
          insert = "/ ";
        } else if (before.endsWith(" ")) {
          insert = "/ ";
        }

        el.value = before + insert + after;
        const newPos = start + insert.length;
        el.selectionStart = el.selectionEnd = newPos;
      }
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
