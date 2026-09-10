<?php
require __DIR__ . '/bootstrap.php';
require_login();

$assetId = max(0, (int)($_GET['id'] ?? $_GET['asset_id'] ?? 0));
$cabangId = isset($_GET['cabang']) ? max(0, (int)$_GET['cabang']) : 0;
$year = max(2020, min(2100, (int)($_GET['tahun'] ?? date('Y'))));
$layout = trim((string)($_GET['layout'] ?? 'grid6')); // 'grid6' (default: 6 per A4), 'grid8', or 'single'

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

// Function to generate 1 card in grid6 (6 per A4: 2 kolom x 3 baris) format
function render_card_grid6(array $asset, int $year): string {
    $assetId = (int)$asset['id'];
    $matrix = get_asset_yearly_card_matrix($assetId, $year);

    $rawUser = !empty($asset['karyawan_nama']) && $asset['karyawan_nama'] !== '-' ? $asset['karyawan_nama'] : 'Umum / Pool';
    $userDisplay = $rawUser;
    $divisi = !empty($asset['divisi_nama']) && $asset['divisi_nama'] !== '-' ? $asset['divisi_nama'] : '';
    $userWithDiv = $divisi ? "{$userDisplay} ({$divisi})" : $userDisplay;
    $ipDisplay = !empty($asset['ip_address']) ? $asset['ip_address'] : (!empty($asset['ip']) ? $asset['ip'] : '-');
    $kodeInv = $asset['kode_inventaris'] ?? ('ASET-' . $assetId);
    $deviceTitle = asset_title($asset);
    $printerDisplay = !empty($asset['printer']) ? $asset['printer'] : '-';
    $cabangLabel = !empty($asset['cabang_nama']) && $asset['cabang_nama'] !== '-' ? $asset['cabang_nama'] : 'KPO';

    $tableRows = '';
    for ($m = 1; $m <= 12; $m++) {
        $row = $matrix[$m];
        $dateLabel = $row['date_str'];
        $isDone = $row['is_done'];
        $paraf = $isDone ? e($row['paraf']) : '&nbsp;';
        $rowClass = ($m % 2 === 0) ? 'even-row' : 'odd-row';
        if ($isDone) $rowClass .= ' done-row';

        $cols1to9 = '';
        for ($num = 1; $num <= 9; $num++) {
            $chkVal = $row['checklists'][$num] ?? 0;
            if ($isDone) {
                $cols1to9 .= '<td class="chk-col '.($chkVal ? 'chk-yes' : 'chk-no').'">'.($chkVal ? '✓' : '-').'</td>';
            } else {
                $cols1to9 .= '<td class="chk-col">&nbsp;</td>';
            }
        }

        $tableRows .= '
        <tr class="'.$rowClass.'">
          <td class="tgl-col">'.e($dateLabel).'</td>
          '.$cols1to9.'
          <td class="paraf-col">'.$paraf.'</td>
        </tr>';
    }

    return '
    <div class="card-item-6">
      <!-- Header Banner Berwarna -->
      <div class="grid6-top-banner">
        <span><i class="bi bi-card-checklist"></i> KARTU KONTROL IT · '.$year.'</span>
        <span class="grid6-branch-pill">'.e($cabangLabel).'</span>
      </div>

      <!-- Header Info -->
      <table class="grid6-info-table">
        <tr>
          <td style="width: 52px;"><span class="badge-lbl badge-blue">NAMA</span></td>
          <td style="width: 4px;">:</td>
          <td class="info-v"><strong>'.e($userWithDiv).'</strong></td>
        </tr>
        <tr>
          <td><span class="badge-lbl badge-green">IP / KODE</span></td>
          <td>:</td>
          <td class="info-v"><span class="text-success fw-bold">'.e($ipDisplay).'</span> · <span class="badge-kode">'.e($kodeInv).'</span></td>
        </tr>
        <tr>
          <td><span class="badge-lbl badge-purple">UNIT/PRT</span></td>
          <td>:</td>
          <td class="info-v">'.e($deviceTitle).' · <span class="text-secondary">'.e($printerDisplay).'</span></td>
        </tr>
      </table>

      <!-- 12 Months Matrix Table -->
      <table class="grid6-matrix-table">
        <thead>
          <tr>
            <th class="tgl-h">TGL</th>
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

      <!-- 9 Item Legend Footer Lengkap (1-9) -->
      <div class="grid6-ket-box">
        <div class="grid6-ket-title"><i class="bi bi-info-circle-fill me-1"></i>KETERANGAN ITEM CHECKLIST (1 - 9):</div>
        <div class="grid6-ket-grid">
          <span class="leg-tag leg-blue"><b>1.</b>Scan Virus</span>
          <span class="leg-tag leg-blue"><b>2.</b>Update AV</span>
          <span class="leg-tag leg-blue"><b>3.</b>Temp File</span>
          <span class="leg-tag leg-purple"><b>4.</b>Keyboard</span>
          <span class="leg-tag leg-purple"><b>5.</b>Mouse</span>
          <span class="leg-tag leg-purple"><b>6.</b>CPU & Mon</span>
          <span class="leg-tag leg-teal"><b>7.</b>Cek Tinta</span>
          <span class="leg-tag leg-teal"><b>8.</b>Cartridge</span>
          <span class="leg-tag leg-teal"><b>9.</b>Cek Nozzle</span>
        </div>
      </div>
    </div>';
}

// Function to generate 1 card in grid8 (8 per A4) format
function render_card_grid8(array $asset, int $year): string {
    $assetId = (int)$asset['id'];
    $matrix = get_asset_yearly_card_matrix($assetId, $year);

    $rawUser = !empty($asset['karyawan_nama']) && $asset['karyawan_nama'] !== '-' ? $asset['karyawan_nama'] : 'Umum / Pool';
    $userDisplay = $rawUser;
    $divisi = !empty($asset['divisi_nama']) && $asset['divisi_nama'] !== '-' ? $asset['divisi_nama'] : '';
    $userWithDiv = $divisi ? "{$userDisplay} ({$divisi})" : $userDisplay;
    $ipDisplay = !empty($asset['ip_address']) ? $asset['ip_address'] : (!empty($asset['ip']) ? $asset['ip'] : '-');
    $kodeInv = $asset['kode_inventaris'] ?? ('ASET-' . $assetId);
    $deviceTitle = asset_title($asset);
    $printerDisplay = !empty($asset['printer']) ? $asset['printer'] : '-';
    $cabangLabel = !empty($asset['cabang_nama']) && $asset['cabang_nama'] !== '-' ? $asset['cabang_nama'] : 'KPO';

    $tableRows = '';
    for ($m = 1; $m <= 12; $m++) {
        $row = $matrix[$m];
        $dateLabel = $row['date_str'];
        $isDone = $row['is_done'];
        $paraf = $isDone ? e($row['paraf']) : '&nbsp;';
        $rowClass = ($m % 2 === 0) ? 'even-row' : 'odd-row';
        if ($isDone) $rowClass .= ' done-row';

        $cols1to9 = '';
        for ($num = 1; $num <= 9; $num++) {
            $chkVal = $row['checklists'][$num] ?? 0;
            if ($isDone) {
                $cols1to9 .= '<td class="chk-col '.($chkVal ? 'chk-yes' : 'chk-no').'">'.($chkVal ? '✓' : '-').'</td>';
            } else {
                $cols1to9 .= '<td class="chk-col">&nbsp;</td>';
            }
        }

        $tableRows .= '
        <tr class="'.$rowClass.'">
          <td class="tgl-col">'.e($dateLabel).'</td>
          '.$cols1to9.'
          <td class="paraf-col">'.$paraf.'</td>
        </tr>';
    }

    return '
    <div class="card-item-8">
      <!-- Header Banner Berwarna -->
      <div class="grid8-top-banner">
        <span><i class="bi bi-card-checklist"></i> KARTU KONTROL IT · '.$year.'</span>
        <span class="grid8-branch-pill">'.e($cabangLabel).'</span>
      </div>

      <!-- Header Info -->
      <table class="grid8-info-table">
        <tr>
          <td style="width: 46px;"><span class="badge-lbl badge-blue">NAMA</span></td>
          <td style="width: 4px;">:</td>
          <td class="info-v"><strong>'.e($userWithDiv).'</strong></td>
        </tr>
        <tr>
          <td><span class="badge-lbl badge-green">IP / KODE</span></td>
          <td>:</td>
          <td class="info-v"><span class="text-success fw-bold">'.e($ipDisplay).'</span> · <span class="badge-kode">'.e($kodeInv).'</span></td>
        </tr>
        <tr>
          <td><span class="badge-lbl badge-purple">UNIT/PRT</span></td>
          <td>:</td>
          <td class="info-v">'.e($deviceTitle).' · <span class="text-secondary">'.e($printerDisplay).'</span></td>
        </tr>
      </table>

      <!-- 12 Months Matrix Table -->
      <table class="grid8-matrix-table">
        <thead>
          <tr>
            <th class="tgl-h">TGL</th>
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

      <!-- 9 Item Legend Footer Lengkap (1-9) -->
      <div class="grid8-ket-box">
        <div class="grid8-ket-title">Keterangan Item Checklist (1 - 9):</div>
        <div class="grid8-ket-grid">
          <span class="leg-tag leg-blue"><b>1.</b>Scan Virus</span>
          <span class="leg-tag leg-blue"><b>2.</b>Update AV</span>
          <span class="leg-tag leg-blue"><b>3.</b>Temp File</span>
          <span class="leg-tag leg-purple"><b>4.</b>Keyboard</span>
          <span class="leg-tag leg-purple"><b>5.</b>Mouse</span>
          <span class="leg-tag leg-purple"><b>6.</b>CPU & Mon</span>
          <span class="leg-tag leg-teal"><b>7.</b>Cek Tinta</span>
          <span class="leg-tag leg-teal"><b>8.</b>Cartridge</span>
          <span class="leg-tag leg-teal"><b>9.</b>Cek Nozzle</span>
        </div>
      </div>
    </div>';
}

// Function to generate 1 large single card
function render_card_single(array $asset, int $year): string {
    $assetId = (int)$asset['id'];
    $matrix = get_asset_yearly_card_matrix($assetId, $year);

    $rawUser = !empty($asset['karyawan_nama']) && $asset['karyawan_nama'] !== '-' ? $asset['karyawan_nama'] : '';
    $userDisplay = $rawUser;
    $ipDisplay = !empty($asset['ip_address']) ? $asset['ip_address'] : (!empty($asset['ip']) ? $asset['ip'] : '');
    $printerDisplay = !empty($asset['printer']) ? $asset['printer'] : '';
    $kodeInv = $asset['kode_inventaris'] ?? ('ASET-' . $assetId);
    $deviceTitle = asset_title($asset);

    $tableRows = '';
    for ($m = 1; $m <= 12; $m++) {
        $row = $matrix[$m];
        $dateLabel = $row['date_str'];
        $isDone = $row['is_done'];
        $paraf = $isDone ? e($row['paraf']) : '&nbsp;';
        $rowClass = ($m % 2 === 0) ? 'even-row' : 'odd-row';
        if ($isDone) $rowClass .= ' done-row';

        $cols1to9 = '';
        for ($num = 1; $num <= 9; $num++) {
            $chkVal = $row['checklists'][$num] ?? 0;
            if ($isDone) {
                $cols1to9 .= '<td style="border: 1.5px solid #2563eb; width: 34px;" class="fw-bold '.($chkVal ? 'text-success' : 'text-muted').' text-center">'.($chkVal ? '✓' : '-').'</td>';
            } else {
                $cols1to9 .= '<td style="border: 1.5px solid #cbd5e1; width: 34px;">&nbsp;</td>';
            }
        }

        $tableRows .= '
        <tr class="'.$rowClass.'" style="height: 28px;">
          <td style="border: 1.5px solid #2563eb; width: 100px;" class="fw-bold text-primary text-center font-monospace">'.e($dateLabel).'</td>
          '.$cols1to9.'
          <td style="border: 1.5px solid #2563eb; min-width: 90px;" class="text-center font-monospace small">'.$paraf.'</td>
        </tr>';
    }

    return '
    <div class="print-card-wrapper-single mb-4">
      <div class="d-flex justify-content-between align-items-center bg-primary text-white p-2 rounded-2 mb-3 shadow-sm">
        <h5 class="fw-bold mb-0"><i class="bi bi-card-checklist me-2"></i>KARTU KONTROL PEMELIHARAAN IT '.$year.'</h5>
        <span class="badge bg-light text-primary fw-bold fs-6">'.e($kodeInv).'</span>
      </div>

      <table class="info-table-single">
        <tr>
          <td style="width: 120px;"><span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1">NAMA PENGGUNA</span></td>
          <td style="width: 15px;">:</td>
          <td class="info-line-single"><strong>'.e($userDisplay).'</strong></td>
        </tr>
        <tr>
          <td><span class="badge bg-success bg-opacity-10 text-success px-2 py-1">IP & PERANGKAT</span></td>
          <td>:</td>
          <td class="info-line-single">'.e($ipDisplay).' · <strong>'.e($deviceTitle).'</strong></td>
        </tr>
        <tr>
          <td><span class="badge bg-info bg-opacity-10 text-info text-dark px-2 py-1">PRINTER TERHUBUNG</span></td>
          <td>:</td>
          <td class="info-line-single">'.e($printerDisplay).'</td>
        </tr>
      </table>

      <table class="card-table-single mt-3">
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
        <div class="fw-bold mb-1 text-primary"><i class="bi bi-info-circle me-1"></i>Keterangan 9 Item Pemeriksaan:</div>
        <div class="row g-2">
          <div class="col-4">
            <div class="p-2 bg-light rounded border border-primary border-opacity-25">
              <strong class="text-primary d-block mb-1">Software & Sistem:</strong>
              <div>1. Scan Virus</div>
              <div>2. Update Anti Virus</div>
              <div>3. Deleting Temp File</div>
            </div>
          </div>
          <div class="col-4">
            <div class="p-2 bg-light rounded border border-indigo border-opacity-25">
              <strong class="text-indigo d-block mb-1" style="color: #4f46e5;">Hardware & Input:</strong>
              <div>4. Cek Keyboard</div>
              <div>5. Cek Mouse</div>
              <div>6. Cek CPU & Monitor</div>
            </div>
          </div>
          <div class="col-4">
            <div class="p-2 bg-light rounded border border-success border-opacity-25">
              <strong class="text-success d-block mb-1">Perangkat Printer:</strong>
              <div>7. Cek Tinta</div>
              <div>8. Cek Cartridge</div>
              <div>9. Cek Nozzle</div>
            </div>
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
    } elseif ($layout === 'grid8') {
        // Grid 8: 8 kartu per halaman A4 (chunking by 8)
        $chunks = array_chunk($assetList, 8);
        foreach ($chunks as $chunk) {
            $cardsHtml .= '<div class="page-grid-8">';
            foreach ($chunk as $a) {
                $cardsHtml .= render_card_grid8($a, $year);
            }
            $cardsHtml .= '</div>';
        }
    } else {
        // Grid 6 (DEFAULT): 6 kartu per halaman A4 (2 kolom x 3 baris - Lega & Jelas)
        $chunks = array_chunk($assetList, 6);
        foreach ($chunks as $chunk) {
            $cardsHtml .= '<div class="page-grid-6">';
            foreach ($chunk as $a) {
                $cardsHtml .= render_card_grid6($a, $year);
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
  margin: 7mm 8mm 7mm 8mm;
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
   GRID 6 PER LEMBAR A4 (2 KOLOM x 3 BARIS) - FIT PRESISI & LEGA
   ========================================================= */
.page-grid-6 {
  width: 184mm;
  max-width: 184mm;
  display: grid;
  grid-template-columns: 89mm 89mm;
  grid-auto-rows: 85mm;
  gap: 3mm 6mm;
  justify-content: center;
  margin: 0 auto 10mm auto;
  page-break-after: always;
  break-after: page;
}

.card-item-6 {
  width: 89mm;
  height: 85mm;
  background: #ffffff;
  border: 1.4px solid #2E77AD;
  border-radius: 2.2mm;
  padding: 2mm 2.2mm;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  page-break-inside: avoid;
  break-inside: avoid;
  overflow: hidden;
}

.grid6-top-banner {
  background: linear-gradient(135deg, #2E77AD 0%, #30B0E0 100%);
  color: #ffffff;
  padding: 1px 4px;
  border-radius: 0.8mm;
  font-weight: 800;
  font-size: 7pt;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.grid6-branch-pill {
  background: #30B0E0;
  color: #ffffff;
  font-size: 5.6pt;
  font-weight: 800;
  padding: 0.2px 2.5px;
  border-radius: 0.5mm;
  text-transform: uppercase;
}

.grid6-info-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 6.9pt;
  line-height: 1.15;
}
.grid6-info-table td {
  padding: 0.3px 1px;
  vertical-align: middle;
}

.grid6-matrix-table {
  width: 100%;
  border-collapse: collapse;
  border: 1px solid #2E77AD;
  font-size: 6.6pt;
}
.grid6-matrix-table th {
  background: #2E77AD !important;
  color: #ffffff !important;
  border: 0.6px solid #1A4064;
  font-weight: bold;
  text-align: center;
  padding: 0.8px 0;
  height: 11.5px;
}
.grid6-matrix-table td {
  border: 0.6px solid #cbd5e1;
  text-align: center;
  padding: 0;
  height: 11.2px;
}
.grid6-matrix-table tr.even-row { background-color: #f8fafc; }
.grid6-matrix-table tr.done-row { background-color: #f0fdf4; }
.grid6-matrix-table tr.done-row td { border-color: #A7F3D0; }
.grid6-matrix-table .tgl-h { width: 20mm; }
.grid6-matrix-table .tgl-col { font-weight: bold; font-family: "Courier New", monospace; font-size: 6.8pt; color: #2E77AD; }
.grid6-matrix-table .chk-h { width: 4.5mm; }
.grid6-matrix-table .chk-col { font-weight: bold; font-size: 7.2pt; }
.grid6-matrix-table .chk-yes { color: #10B981; font-weight: 900; }
.grid6-matrix-table .chk-no { color: #94a3b8; }
.grid6-matrix-table .paraf-h { min-width: 15mm; }
.grid6-matrix-table .paraf-col { font-size: 6pt; font-family: "Courier New", monospace; color: #334155; }

.grid6-ket-box {
  background: #f8fafc;
  border: 0.7px solid #94a3b8;
  border-radius: 1mm;
  padding: 0.8mm 1.2mm;
}
.grid6-ket-title {
  font-size: 5.4pt;
  font-weight: 800;
  color: #2E77AD;
  margin-bottom: 0.3mm;
  text-transform: uppercase;
  letter-spacing: 0.2px;
}
.grid6-ket-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.3mm 0.8mm;
}

/* Badges & Pills */
.badge-lbl {
  font-size: 5.6pt;
  font-weight: 800;
  padding: 0.2px 2.2px;
  border-radius: 0.5mm;
  display: inline-block;
  text-align: center;
  white-space: nowrap;
}
.badge-blue { background: #E6EDF5; color: #2E77AD; border: 0.5px solid #30B0E0; }
.badge-green { background: #ECFDF5; color: #065F46; border: 0.5px solid #A7F3D0; }
.badge-purple { background: #E6EDF5; color: #50C0C0; border: 0.5px solid #50C0C0; }
.badge-kode { background: #f1f5f9; color: #0f172a; padding: 0.2px 2.2px; border-radius: 0.5mm; font-weight: bold; border: 0.5px solid #cbd5e1; }

.leg-tag {
  font-size: 5.2pt;
  font-weight: 600;
  padding: 0.2px 1.5px;
  border-radius: 0.5mm;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 1.2px;
}
.leg-tag b {
  font-weight: 800;
  color: #0f172a;
}
.leg-blue { background: #eff6ff; color: #1d4ed8; border: 0.5px solid #bfdbfe; }
.leg-purple { background: #faf5ff; color: #7e22ce; border: 0.5px solid #e9d5ff; }
.leg-teal { background: #f0fdfa; color: #0f766e; border: 0.5px solid #99f6e4; }

/* =========================================================
   GRID 8 PER LEMBAR A4 (2 KOLOM x 4 BARIS)
   ========================================================= */
.page-grid-8 {
  width: 184mm;
  max-width: 184mm;
  display: grid;
  grid-template-columns: 89mm 89mm;
  grid-auto-rows: 64mm;
  gap: 2mm 6mm;
  justify-content: center;
  margin: 0 auto 10mm auto;
  page-break-after: always;
  break-after: page;
}
.card-item-8 {
  width: 89mm;
  height: 64mm;
  background: #ffffff;
  border: 1.3px solid #2E77AD;
  border-radius: 1.8mm;
  padding: 1mm 1.5mm;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  page-break-inside: avoid;
  break-inside: avoid;
  overflow: hidden;
}
.grid8-top-banner {
  background: linear-gradient(135deg, #2E77AD 0%, #30B0E0 100%);
  color: #ffffff;
  padding: 0.5px 3px;
  border-radius: 0.6mm;
  font-weight: 800;
  font-size: 5.6pt;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.grid8-branch-pill {
  background: #30B0E0;
  color: #ffffff;
  font-size: 4.6pt;
  font-weight: 800;
  padding: 0.2mm 1.2mm;
  border-radius: 0.4mm;
  text-transform: uppercase;
}
.grid8-info-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 5.9pt;
  line-height: 1.1;
}
.grid8-info-table td { padding: 0.2px 0.5px; vertical-align: middle; }
.grid8-matrix-table {
  width: 100%;
  border-collapse: collapse;
  border: 0.8px solid #2E77AD;
  font-size: 5.5pt;
}
.grid8-matrix-table th {
  background: #2E77AD !important;
  color: #ffffff !important;
  border: 0.5px solid #1A4064;
  font-weight: bold;
  text-align: center;
  padding: 0.3px 0;
  height: 9.8px;
}
.grid8-matrix-table td { border: 0.5px solid #cbd5e1; text-align: center; padding: 0; height: 9.2px; }
.grid8-matrix-table tr.even-row { background-color: #f8fafc; }
.grid8-matrix-table tr.done-row { background-color: #f0fdf4; }
.grid8-matrix-table tr.done-row td { border-color: #A7F3D0; }
.grid8-matrix-table .tgl-h { width: 18mm; }
.grid8-matrix-table .tgl-col { font-weight: bold; font-family: "Courier New", monospace; font-size: 5.6pt; color: #2E77AD; }
.grid8-matrix-table .chk-h { width: 4.2mm; }
.grid8-matrix-table .chk-col { font-weight: bold; font-size: 6pt; }
.grid8-matrix-table .chk-yes { color: #10B981; font-weight: 900; }
.grid8-matrix-table .chk-no { color: #94a3b8; }
.grid8-matrix-table .paraf-h { min-width: 15mm; }
.grid8-matrix-table .paraf-col { font-size: 5pt; font-family: "Courier New", monospace; color: #334155; }
.grid8-ket-box {
  background: #f8fafc;
  border: 0.6px solid #94a3b8;
  border-radius: 0.6mm;
  padding: 0.3mm 0.6mm;
}
.grid8-ket-title {
  font-size: 4.4pt;
  font-weight: 800;
  color: #2E77AD;
  margin-bottom: 0.2mm;
  text-transform: uppercase;
}
.grid8-ket-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.2mm 0.6mm;
}

/* =========================================================
   SINGLE CARD FORMAT (1 PER HALAMAN)
   ========================================================= */
.print-card-wrapper-single {
  background: #ffffff;
  border: 2px solid #2E77AD;
  border-radius: 4px;
  padding: 20px 24px;
  max-width: 720px;
  margin: 0 auto;
  page-break-inside: avoid;
  box-shadow: 0 4px 12px rgba(46, 119, 173, 0.1);
}
.info-table-single {
  width: 100%;
  margin-bottom: 8px;
  font-weight: bold;
  font-size: 10.5pt;
}
.info-table-single td { padding: 4px 2px; }
.info-line-single { border-bottom: 1.5px solid #2E77AD; padding-left: 6px; }
.card-table-single { width: 100%; border-collapse: collapse; border: 1.5px solid #2E77AD; }
.card-table-single th { background: #2E77AD !important; color: #ffffff !important; border: 1.5px solid #1A4064 !important; font-weight: bold; padding: 5px 2px; text-align: center; font-size: 9.5pt; }
.card-table-single td { border: 1px solid #E6EDF5; font-size: 9pt; padding: 3px 2px; }
.card-table-single tr.even-row { background-color: #f8fafc; }
.card-table-single tr.done-row { background-color: #f0fdf4; }
.ket-box-single { font-size: 8.5pt; margin-top: 10px; line-height: 1.4; }

/* Print Media Query */
@media print {
  html, body {
    background: #ffffff !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  .no-print, nav, header, footer {
    display: none !important;
  }
  .container-fluid, .container, main {
    max-width: 100% !important;
    width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
  }
  .cards-print-area {
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  .page-grid-6 {
    width: 184mm !important;
    max-width: 184mm !important;
    margin: 0 auto !important;
    padding: 0 !important;
    page-break-after: always !important;
    break-after: page !important;
  }
  .page-grid-6:last-child {
    page-break-after: auto !important;
    break-after: auto !important;
  }
  .page-grid-8 {
    width: 184mm !important;
    max-width: 184mm !important;
    margin: 0 auto !important;
    padding: 0 !important;
    page-break-after: always !important;
    break-after: page !important;
  }
  .page-grid-8:last-child {
    page-break-after: auto !important;
    break-after: auto !important;
  }
  .card-item-6, .card-item-8 {
    box-shadow: none !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  .print-card-wrapper-single {
    box-shadow: none !important;
    border: 1.5px solid #2563eb !important;
    margin: 0 auto !important;
    page-break-after: always !important;
    break-after: page !important;
  }
  .print-card-wrapper-single:last-child {
    page-break-after: auto !important;
    break-after: auto !important;
  }
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

        <select class="form-select form-select-sm fw-bold text-primary" name="layout" style="width: 200px;" onchange="this.form.submit()">
          <option value="grid6"'.($layout === 'grid6' ? ' selected' : '').'>📄 6 Kartu / Lembar A4 (Rekomendasi)</option>
          <option value="grid8"'.($layout === 'grid8' ? ' selected' : '').'>📄 8 Kartu / Lembar A4 (Padat)</option>
          <option value="single"'.($layout === 'single' ? ' selected' : '').'>📄 1 Kartu Besar / Lembar</option>
        </select>

        <input type="number" class="form-control form-control-sm font-monospace fw-bold" name="tahun" value="'.$year.'" min="2020" max="2100" style="width: 95px;" onchange="this.form.submit()">

        <button type="button" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm" onclick="window.print()">
          <i class="bi bi-printer-fill me-1"></i> CETAK (PRINT)
        </button>
      </form>
    </div>

    <div class="alert alert-info py-2 px-3 small mt-3 mb-0 d-flex align-items-center justify-content-between">
      <span><i class="bi bi-info-circle-fill me-1"></i> <strong>Tips Cetak:</strong> Gunakan kertas <strong>A4 Portrait</strong>, Margin: <strong>Default / Minimum</strong>, dan centang opsi <strong>"Background Graphics"</strong> di menu printer browser.</span>
      <span class="badge bg-primary fs-6">Layout Aktif: '.($layout === 'grid6' ? '6 Kartu per A4' : ($layout === 'grid8' ? '8 Kartu per A4' : '1 Kartu Penuh')).'</span>
    </div>
  </div>

  <!-- Cards Container -->
  <div class="cards-print-area">
    '.$cardsHtml.'
  </div>

</div>';

render_page($pageTitle, $body, $head, '', false);
