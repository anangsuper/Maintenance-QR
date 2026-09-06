<?php
require __DIR__ . '/bootstrap.php';
require_login();

$assetId = max(0, (int)($_GET['id'] ?? $_GET['asset_id'] ?? 0));
$cabangId = isset($_GET['cabang']) ? max(0, (int)$_GET['cabang']) : 0;
$year = max(2020, min(2100, (int)($_GET['tahun'] ?? date('Y'))));
$layout = trim((string)($_GET['layout'] ?? 'grid8')); // 'grid8' (default: 8 per A4) or 'single'

// Ambil daftar aset yang akan dicetak
$assetList = [];
if ($assetId > 0) {
    $a = get_asset_by_id($assetId);
    if ($a) {
        $assetList[] = $a;
    }
} else {
    $rows = get_qr_admin_rows($cabangId);
    $assetList = $rows;
}

$cabangs = get_cabang_list();
$totalAssets = count($assetList);

// Cabang name label
$selectedCabangName = 'Semua Cabang';
foreach ($cabangs as $c) {
    if ((int)($c['id'] ?? 0) === $cabangId) {
        $selectedCabangName = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cabangId);
        break;
    }
}

// Function to generate 1 card in grid8 (8 per A4) format
function render_card_grid8(array $asset, int $year): string {
    $assetId = (int)$asset['id'];
    $matrix = get_asset_yearly_card_matrix($assetId, $year);

    $userDisplay = !empty($asset['karyawan_nama']) && $asset['karyawan_nama'] !== '-' ? $asset['karyawan_nama'] : 'Umum / Pool';
    $divisi = !empty($asset['divisi_nama']) && $asset['divisi_nama'] !== '-' ? $asset['divisi_nama'] : '';
    $userWithDiv = $divisi ? "{$userDisplay} ({$divisi})" : $userDisplay;
    $ipDisplay = !empty($asset['ip_address']) ? $asset['ip_address'] : (!empty($asset['ip']) ? $asset['ip'] : '-');
    $kodeInv = $asset['kode_inventaris'] ?? ('ASET-' . $assetId);
    $deviceTitle = asset_title($asset);
    $printerDisplay = !empty($asset['printer']) ? $asset['printer'] : '-';

    $tableRows = '';
    for ($m = 1; $m <= 12; $m++) {
        $row = $matrix[$m];
        $dateLabel = $row['date_str'];
        $isDone = $row['is_done'];
        $paraf = $isDone ? e($row['paraf']) : '&nbsp;';

        $cols1to9 = '';
        for ($num = 1; $num <= 9; $num++) {
            $chkVal = $row['checklists'][$num] ?? 0;
            if ($isDone) {
                $cols1to9 .= '<td class="chk-col">'.($chkVal ? '✓' : '-').'</td>';
            } else {
                $cols1to9 .= '<td class="chk-col">&nbsp;</td>';
            }
        }

        $tableRows .= '
        <tr>
          <td class="tgl-col">'.e($dateLabel).'</td>
          '.$cols1to9.'
          <td class="paraf-col">'.$paraf.'</td>
        </tr>';
    }

    return '
    <div class="card-item-8">
      <!-- Header Info -->
      <table class="grid8-info-table">
        <tr>
          <td style="width: 42px;" class="info-k">NAMA</td>
          <td style="width: 6px;">:</td>
          <td class="info-v"><strong>'.e($userWithDiv).'</strong></td>
        </tr>
        <tr>
          <td class="info-k">IP / KODE</td>
          <td>:</td>
          <td class="info-v">'.e($ipDisplay).' · <span class="text-dark fw-bold">'.e($kodeInv).'</span></td>
        </tr>
        <tr>
          <td class="info-k">UNIT / PRT</td>
          <td>:</td>
          <td class="info-v">'.e($deviceTitle).' · Prt: '.e($printerDisplay).'</td>
        </tr>
      </table>

      <!-- 12 Months Matrix Table -->
      <table class="grid8-matrix-table">
        <thead>
          <tr>
            <th class="tgl-h">TANGGAL</th>
            <th class="chk-h">1</th>
            <th class="chk-h">2</th>
            <th class="chk-h">3</th>
            <th class="chk-h">4</th>
            <th class="chk-h">5</th>
            <th class="chk-h">6</th>
            <th class="chk-h">7</th>
            <th class="chk-h">8</th>
            <th class="chk-h">9</th>
            <th class="paraf-h">PARAF</th>
          </tr>
        </thead>
        <tbody>
          '.$tableRows.'
        </tbody>
      </table>

      <!-- 9 Item Legend Footer -->
      <div class="grid8-ket-box">
        <span class="fw-bold">Ket:</span> 1.Scan Virus · 2.Update AV · 3.Del Temp · 4.Cek Keyboard · 5.Cek Mouse · 6.Cek CPU/Mon · 7.Tinta · 8.Cartridge · 9.Nozzle
      </div>
    </div>';
}

// Function to generate 1 large single card
function render_card_single(array $asset, int $year): string {
    $assetId = (int)$asset['id'];
    $matrix = get_asset_yearly_card_matrix($assetId, $year);

    $userDisplay = !empty($asset['karyawan_nama']) && $asset['karyawan_nama'] !== '-' ? $asset['karyawan_nama'] : '';
    $ipDisplay = !empty($asset['ip_address']) ? $asset['ip_address'] : (!empty($asset['ip']) ? $asset['ip'] : '');
    $printerDisplay = !empty($asset['printer']) ? $asset['printer'] : '';

    $tableRows = '';
    for ($m = 1; $m <= 12; $m++) {
        $row = $matrix[$m];
        $dateLabel = $row['date_str'];
        $isDone = $row['is_done'];
        $paraf = $isDone ? e($row['paraf']) : '&nbsp;';

        $cols1to9 = '';
        for ($num = 1; $num <= 9; $num++) {
            $chkVal = $row['checklists'][$num] ?? 0;
            if ($isDone) {
                $cols1to9 .= '<td style="border: 1.5px solid #000; width: 34px;" class="fw-bold text-dark text-center">'.($chkVal ? '✓' : '-').'</td>';
            } else {
                $cols1to9 .= '<td style="border: 1.5px solid #000; width: 34px;">&nbsp;</td>';
            }
        }

        $tableRows .= '
        <tr style="height: 28px;">
          <td style="border: 1.5px solid #000; width: 100px;" class="fw-bold text-dark text-center font-monospace">'.e($dateLabel).'</td>
          '.$cols1to9.'
          <td style="border: 1.5px solid #000; min-width: 90px;" class="text-center font-monospace small">'.$paraf.'</td>
        </tr>';
    }

    return '
    <div class="print-card-wrapper-single mb-4">
      <table class="info-table-single">
        <tr>
          <td style="width: 110px;">NAMA</td>
          <td style="width: 20px;">:</td>
          <td class="info-line-single">'.e($userDisplay).'</td>
        </tr>
        <tr>
          <td>IP</td>
          <td>:</td>
          <td class="info-line-single">'.e($ipDisplay).'</td>
        </tr>
        <tr>
          <td>PRINTER</td>
          <td>:</td>
          <td class="info-line-single">'.e($printerDisplay).'</td>
        </tr>
      </table>

      <table class="card-table-single mt-2">
        <thead>
          <tr>
            <th style="width: 100px;">TANGGAL</th>
            <th style="width: 32px;">1</th>
            <th style="width: 32px;">2</th>
            <th style="width: 32px;">3</th>
            <th style="width: 32px;">4</th>
            <th style="width: 32px;">5</th>
            <th style="width: 32px;">6</th>
            <th style="width: 32px;">7</th>
            <th style="width: 32px;">8</th>
            <th style="width: 32px;">9</th>
            <th style="min-width: 90px;">PARAF</th>
          </tr>
        </thead>
        <tbody>
          '.$tableRows.'
        </tbody>
      </table>

      <div class="ket-box-single">
        <div class="fw-bold mb-1">Ket</div>
        <div class="row g-1">
          <div class="col-4">
            <div>1. Scan Virus</div>
            <div>2. Update Anti Virus</div>
            <div>3. Deleting Temporary File</div>
          </div>
          <div class="col-4">
            <div>4. Cek Keyboard</div>
            <div>5. Cek Mouse</div>
            <div>6. Cek CPU & Monitor</div>
          </div>
          <div class="col-4">
            <div>7. Cek Tinta</div>
            <div>8. Cek Cartidge</div>
            <div>9. Cek Nozel</div>
          </div>
        </div>
      </div>
    </div>';
}

// Generate Cards Content
$cardsHtml = '';
if (empty($assetList)) {
    $cardsHtml = '<div class="alert alert-warning text-center p-4">Tidak ada data unit komputer yang ditemukan untuk dicetak.</div>';
} else {
    if ($layout === 'single') {
        foreach ($assetList as $a) {
            $cardsHtml .= render_card_single($a, $year);
        }
    } else {
        // Grid 8: 8 kartu per halaman A4 (chunking by 8)
        $chunks = array_chunk($assetList, 8);
        foreach ($chunks as $chunk) {
            $cardsHtml .= '<div class="page-grid-8">';
            foreach ($chunk as $a) {
                $cardsHtml .= render_card_grid8($a, $year);
            }
            $cardsHtml .= '</div>';
        }
    }
}

// Cabang options for filter toolbar
$cabangOptions = '<option value="0"'.($cabangId === 0 ? ' selected' : '').'>Semua Cabang</option>';
foreach ($cabangs as $c) {
    $cId = (int)($c['id'] ?? 0);
    $cNama = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cId);
    $cabangOptions .= '<option value="'.$cId.'"'.($cabangId === $cId ? ' selected' : '').'>'.e($cNama).'</option>';
}

$head = '
<style>
@page {
  size: A4 portrait;
  margin: 5mm 5mm 5mm 5mm;
}
* {
  box-sizing: border-box;
}
body {
  font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
  color: #000000;
  background: #f1f5f9;
  margin: 0;
  padding: 0;
  -webkit-print-color-adjust: exact !important;
  print-color-adjust: exact !important;
}

/* =========================================================
   GRID 8 PER LEMBAR A4 (2 KOLOM x 4 BARIS)
   ========================================================= */
.page-grid-8 {
  width: 198mm;
  display: grid;
  grid-template-columns: 96mm 96mm;
  grid-auto-rows: 68mm;
  gap: 3mm 4mm;
  justify-content: center;
  margin: 0 auto 12mm auto;
  page-break-after: always;
  break-after: page;
}

.card-item-8 {
  width: 96mm;
  height: 68mm;
  background: #ffffff;
  border: 1.2px solid #000000;
  border-radius: 2px;
  padding: 2mm 2.5mm;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  page-break-inside: avoid;
  break-inside: avoid;
  overflow: hidden;
}

/* Header Table Mini */
.grid8-info-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 7.2pt;
  line-height: 1.15;
  margin-bottom: 1.5mm;
}
.grid8-info-table td {
  padding: 0.8px 1px;
  vertical-align: middle;
}
.grid8-info-table .info-k {
  font-weight: bold;
  color: #000;
  white-space: nowrap;
}
.grid8-info-table .info-v {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 70mm;
}

/* 12 Months Matrix Table */
.grid8-matrix-table {
  width: 100%;
  border-collapse: collapse;
  border: 1.2px solid #000000;
  font-size: 6.6pt;
}
.grid8-matrix-table th {
  background-color: #8ea9db !important;
  color: #000000 !important;
  border: 1px solid #000000;
  font-weight: bold;
  text-align: center;
  padding: 1.2px 0;
  height: 14px;
}
.grid8-matrix-table td {
  border: 1px solid #000000;
  text-align: center;
  padding: 0;
  height: 12.8px;
}
.grid8-matrix-table .tgl-h { width: 23mm; }
.grid8-matrix-table .tgl-col { font-weight: bold; font-family: "Courier New", monospace; font-size: 6.8pt; }
.grid8-matrix-table .chk-h { width: 4.8mm; }
.grid8-matrix-table .chk-col { font-weight: bold; font-size: 7.2pt; }
.grid8-matrix-table .paraf-h { min-width: 18mm; }
.grid8-matrix-table .paraf-col { font-size: 6.2pt; font-family: "Courier New", monospace; }

/* Legend Box Mini */
.grid8-ket-box {
  font-size: 5.6pt;
  line-height: 1.15;
  color: #111;
  margin-top: 1mm;
  padding-top: 1px;
  border-top: 0.5px solid #666;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* =========================================================
   SINGLE CARD FORMAT (1 PER HALAMAN)
   ========================================================= */
.print-card-wrapper-single {
  background: #ffffff;
  border: 1.5px solid #000000;
  padding: 20px 24px;
  max-width: 720px;
  margin: 0 auto;
  page-break-inside: avoid;
}
.info-table-single {
  width: 100%;
  margin-bottom: 8px;
  font-weight: bold;
  font-size: 11pt;
}
.info-table-single td { padding: 3px 2px; }
.info-line-single { border-bottom: 1.5px solid #000; padding-left: 6px; }
.card-table-single { width: 100%; border-collapse: collapse; border: 1.5px solid #000; }
.card-table-single th { background-color: #8ea9db !important; border: 1.5px solid #000 !important; font-weight: bold; padding: 5px 2px; text-align: center; font-size: 9.5pt; }
.card-table-single td { border: 1.5px solid #000; font-size: 9pt; padding: 3px 2px; }
.ket-box-single { font-size: 8.5pt; margin-top: 8px; line-height: 1.4; }

/* Print Media Query */
@media print {
  body { background: #ffffff !important; }
  .no-print { display: none !important; }
  .page-grid-8 { margin: 0 auto !important; }
  .print-card-wrapper-single { box-shadow: none !important; border: 1.5px solid #000 !important; margin: 0 auto !important; }
}
</style>';

$pageTitle = 'Cetak Kartu Maintenance · ' . ($cabangId > 0 ? $selectedCabangName : ($assetId > 0 ? ($assetList[0]['kode_inventaris'] ?? 'Unit') : 'Semua Cabang'));

$body = '
<div class="container-fluid py-3">
  
  <!-- Toolbar Kontrol (No Print) -->
  <div class="no-print bg-white p-3 rounded-3 shadow-sm mb-4 border" style="max-width: 980px; margin: 0 auto;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div class="d-flex align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="'.e(module_url('dashboard.php')).'"><i class="bi bi-arrow-left"></i> Dashboard</a>
        <div>
          <h5 class="fw-bold text-dark mb-0"><i class="bi bi-card-checklist text-primary me-1"></i> Cetak Kartu Maintenance IT</h5>
          <small class="text-secondary">Ditemukan <strong>'.$totalAssets.'</strong> unit komputer untuk dicetak</small>
        </div>
      </div>

      <!-- Filter Form -->
      <form method="get" class="d-flex flex-wrap align-items-center gap-2">
        <input type="hidden" name="id" value="'.$assetId.'">
        
        <select class="form-select form-select-sm" name="cabang" style="width: 170px;" onchange="this.form.submit()">
          '.$cabangOptions.'
        </select>

        <select class="form-select form-select-sm" name="layout" style="width: 190px;" onchange="this.form.submit()">
          <option value="grid8"'.($layout === 'grid8' ? ' selected' : '').'>📄 8 Kartu / Lembar A4 (HVS)</option>
          <option value="single"'.($layout === 'single' ? ' selected' : '').'>📄 1 Kartu Besar / Lembar</option>
        </select>

        <input type="number" class="form-control form-control-sm" name="tahun" value="'.$year.'" style="width: 80px;" onchange="this.form.submit()">

        <button type="button" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm" onclick="window.print()">
          <i class="bi bi-printer-fill me-1"></i> CETAK (PRINT)
        </button>
      </form>
    </div>

    <div class="alert alert-info py-2 px-3 small mt-3 mb-0 d-flex align-items-center justify-content-between">
      <span><i class="bi bi-info-circle-fill me-1"></i> <strong>Tips Cetak:</strong> Gunakan kertas <strong>A4 Portrait</strong>, Margin: <strong>Default / Minimum</strong>, dan centang opsi <strong>"Background Graphics"</strong> di menu printer browser.</span>
      <span class="badge bg-primary fs-6">Layout: '.($layout === 'grid8' ? '8 Kartu per A4' : '1 Kartu Penuh').'</span>
    </div>
  </div>

  <!-- Cards Container -->
  <div class="cards-print-area">
    '.$cardsHtml.'
  </div>

</div>';

render_page($pageTitle, $body, $head, '', false);

