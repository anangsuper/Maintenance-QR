<?php
require __DIR__ . '/bootstrap.php';
require_admin();

$assetId = max(0, (int)($_GET['asset_id'] ?? 0));
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));
$rawIds = trim((string)($_GET['ids'] ?? $_POST['ids'] ?? ''));
$idList = [];
if ($rawIds !== '') {
    foreach (explode(',', $rawIds) as $item) {
        $cleanId = (int)trim($item);
        if ($cleanId > 0) $idList[] = $cleanId;
    }
}

$rows = get_qr_admin_rows(!empty($idList) ? 0 : $cabangId);
if (!empty($idList)) {
    $rows = array_values(array_filter($rows, function($r) use ($idList) {
        return in_array((int)($r['id'] ?? 0), $idList, true);
    }));
} elseif ($assetId > 0) {
    $rows = array_values(array_filter($rows, function($r) use ($assetId) {
        return (int)($r['id'] ?? 0) === $assetId;
    }));
}

$cards = '';
$i = 0;
foreach ($rows as $r) {
    $aid = (int)($r['id'] ?? 0);
    $tok = !empty($r['qr_token']) ? $r['qr_token'] : ($aid > 0 ? get_static_qr_token($aid) : '');
    if (empty($tok)) continue;
    $i++;
    $url = module_url('scan.php', ['t' => $tok]);
    $cabangLabel = !empty($r['cabang_nama']) && $r['cabang_nama'] !== '-' ? $r['cabang_nama'] : 'KPO';
    $userLabel = !empty($r['karyawan_nama']) && $r['karyawan_nama'] !== '-' ? $r['karyawan_nama'] : 'Umum / Pool';
    $divisiLabel = !empty($r['divisi_nama']) && $r['divisi_nama'] !== '-' ? $r['divisi_nama'] : '';
    $userFull = $divisiLabel ? "{$userLabel} ({$divisiLabel})" : $userLabel;
    $deviceTitle = asset_title($r);
    // Bersihkan pengulangan merk yang duplikat (misal: "HP HP All-in-One")
    $merkVal = trim((string)($r['merk'] ?? ''));
    if ($merkVal !== '' && stripos($deviceTitle, $merkVal . ' ' . $merkVal) !== false) {
        $deviceTitle = preg_replace('/\\b' . preg_quote($merkVal, '/') . '\\s+' . preg_quote($merkVal, '/') . '\\b/i', $merkVal, $deviceTitle);
    }
    $kode = $r['kode_inventaris'] ?? ('ASET-' . $r['id']);

    $cards .= '
    <div class="qr-sticker-wrapper">
      <div class="qr-sticker-actions no-print">
        <button type="button" class="btn btn-sm qr-dl-btn" onclick="downloadSticker(this, \''.e(addslashes($kode)).'\')" title="Unduh Stiker Gambar (PNG)">
          <i class="bi bi-download text-primary me-1"></i><span class="d-none d-sm-inline">Unduh PNG</span>
        </button>
      </div>
      <div class="qr-sticker">
        <!-- Header Berwarna & Modern -->
        <div class="qr-top-bar">
          <div class="d-flex align-items-center gap-1 qr-brand-col">
            <span class="qr-dot"></span>
            <span class="qr-org">PT BPR MITRATAMA ARTHABUANA</span>
          </div>
          <span class="qr-cabang" title="'.e($cabangLabel).'">'.e($cabangLabel).'</span>
        </div>

        <!-- Body: QR Code & Detail Berwarna -->
        <div class="qr-main-body">
          <div class="qr-box-wrap">
            <div id="qr-'.$i.'" class="qrbox" data-qr="'.e($url).'"></div>
          </div>
          <div class="qr-text-wrap">
            <div class="qr-kode-badge">'.e($kode).'</div>
            <div class="qr-device-name" title="'.e($deviceTitle).'">'.e($deviceTitle).'</div>
            <div class="qr-user-name" title="'.e($userFull).'"><i class="bi bi-person-fill text-success me-1"></i>'.e($userFull).'</div>
          </div>
        </div>

        <!-- Footer Berwarna -->
        <div class="qr-bot-bar">
          <i class="bi bi-qr-code-scan me-1"></i> SCAN UNTUK PEMELIHARAAN IT
        </div>
      </div>
    </div>';
}

if (!$cards) {
    $cards = '<div class="alert alert-warning">Belum ada QR yang dapat dicetak. Silakan generate QR terlebih dahulu di halaman QR Aset.</div>';
}

$head = '<style id="stickerStyle">
/* =========================================================================
   DESAIN STIKER QR MODERN, VIBRANT & BERWARNA
   Ukuran: Kompak (7.0x4.4cm), Mini (6.0x3.8cm), ATM (8.5x5.4cm)
   ========================================================================= */
@page {
  size: A4 portrait;
  margin: 6mm 5mm;
}

* {
  box-sizing: border-box;
}

body {
  background: #F5F8FB;
  font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  color: #1F2A37;
  -webkit-print-color-adjust: exact !important;
  print-color-adjust: exact !important;
}

.qr-container {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 3.5mm;
  padding: 12px 0;
}

.qr-sticker-wrapper {
  display: inline-flex;
  flex-direction: column;
  box-sizing: border-box;
  page-break-inside: avoid;
}

.qr-sticker-actions {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 4px;
}

.qr-dl-btn {
  font-size: 0.72rem;
  padding: 2px 8px;
  background: #ffffff;
  border: 1px solid #CBD5E1;
  color: #1E3A60;
  border-radius: 4px;
  font-weight: 600;
  transition: all 0.15s ease;
  cursor: pointer;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.qr-dl-btn:hover {
  background: #EFF6FF;
  border-color: #3B82F6;
  color: #1D4ED8;
}

.qr-sticker {
  background: #ffffff;
  border: 1.5px solid #2E77AD;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(46, 119, 173, 0.12);
  position: relative;
  -webkit-print-color-adjust: exact !important;
  print-color-adjust: exact !important;
}

.qr-top-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1.5mm;
  background: linear-gradient(135deg, #2E77AD 0%, #30B0E0 100%) !important;
  -webkit-print-color-adjust: exact !important;
  print-color-adjust: exact !important;
}

.qr-brand-col {
  min-width: 0;
  flex-shrink: 1;
}

.qr-dot {
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: #50C0C0 !important;
  display: inline-block;
  flex-shrink: 0;
  -webkit-print-color-adjust: exact !important;
  print-color-adjust: exact !important;
}

.qr-org {
  font-weight: 800;
  color: #ffffff !important;
  letter-spacing: 0.2px;
  text-transform: uppercase;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.qr-cabang {
  font-weight: 800;
  color: #1A4064 !important;
  background: #ffffff !important;
  border-radius: 0.6mm;
  padding: 0.3mm 2mm;
  text-transform: uppercase;
  white-space: nowrap;
  flex-shrink: 0;
  text-align: right;
  box-shadow: 0 1px 3px rgba(0,0,0,0.18);
  -webkit-print-color-adjust: exact !important;
  print-color-adjust: exact !important;
}

.qr-main-body {
  display: flex;
  align-items: center;
  flex: 1;
}

.qr-box-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ffffff;
  border: 1.5px solid #30B0E0;
  flex-shrink: 0;
  box-sizing: border-box;
  box-shadow: 0 1px 4px rgba(46, 119, 173, 0.15);
}

.qr-box-wrap img, .qr-box-wrap canvas {
  width: 100% !important;
  height: 100% !important;
  display: block;
}

.qr-text-wrap {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.qr-kode-badge {
  font-weight: 800;
  color: #2E77AD !important;
  background: #E6EDF5 !important;
  border: 1px solid #30B0E0 !important;
  line-height: 1.1;
  word-break: break-all;
  display: inline-block;
  letter-spacing: -0.2px;
  align-self: flex-start;
  -webkit-print-color-adjust: exact !important;
  print-color-adjust: exact !important;
}

.qr-device-name {
  font-weight: 700;
  color: #1F2A37 !important;
  line-height: 1.25;
  white-space: normal;
  word-break: break-word;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.qr-user-name {
  color: #1F2A37 !important;
  background: #E6EDF5 !important;
  border: 0.8px solid #30B0E0 !important;
  line-height: 1.2;
  white-space: normal;
  word-break: break-word;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  font-weight: 700;
  -webkit-print-color-adjust: exact !important;
  print-color-adjust: exact !important;
}

.qr-bot-bar {
  text-align: center;
  background: linear-gradient(90deg, #2E77AD 0%, #30B0E0 100%) !important;
  font-weight: 800;
  color: #ffffff !important;
  letter-spacing: 0.4px;
  text-transform: uppercase;
  -webkit-print-color-adjust: exact !important;
  print-color-adjust: exact !important;
}

/* --- UKURAN 1: DEFAULT / MEDIUM / KOMPAK (7.0 x 4.4 cm) --- */
.qr-container.size-medium .qr-sticker-wrapper,
.qr-container:not(.size-mini):not(.size-atm) .qr-sticker-wrapper {
  width: 70mm;
  height: 44mm;
}
.qr-container.size-medium .qr-sticker,
.qr-container:not(.size-mini):not(.size-atm) .qr-sticker {
  width: 70mm;
  height: 44mm;
  border-radius: 2.8mm;
  padding: 1.8mm 2.2mm;
}
.qr-container.size-medium .qr-top-bar,
.qr-container:not(.size-mini):not(.size-atm) .qr-top-bar {
  margin: -1.8mm -2.2mm 0 -2.2mm;
  padding: 1.2mm 2.2mm;
}
.qr-container.size-medium .qr-org,
.qr-container:not(.size-mini):not(.size-atm) .qr-org { font-size: 5pt; }
.qr-container.size-medium .qr-cabang,
.qr-container:not(.size-mini):not(.size-atm) .qr-cabang { font-size: 4.6pt; padding: 0.2mm 1.5mm; max-width: 36mm; }
.qr-container.size-medium .qr-main-body,
.qr-container:not(.size-mini):not(.size-atm) .qr-main-body { gap: 2.2mm; padding: 0.8mm 0; }
.qr-container.size-medium .qr-box-wrap,
.qr-container:not(.size-mini):not(.size-atm) .qr-box-wrap { width: 25.5mm; height: 25.5mm; padding: 0.5mm; border-radius: 1.5mm; }
.qr-container.size-medium .qr-text-wrap,
.qr-container:not(.size-mini):not(.size-atm) .qr-text-wrap { gap: 0.8mm; }
.qr-container.size-medium .qr-kode-badge,
.qr-container:not(.size-mini):not(.size-atm) .qr-kode-badge { font-size: 7.2pt; padding: 0.3mm 1.4mm; border-radius: 0.8mm; }
.qr-container.size-medium .qr-device-name,
.qr-container:not(.size-mini):not(.size-atm) .qr-device-name { font-size: 6.2pt; }
.qr-container.size-medium .qr-user-name,
.qr-container:not(.size-mini):not(.size-atm) .qr-user-name { font-size: 6.8pt; padding: 0.4mm 1.4mm; border-radius: 0.8mm; }
.qr-container.size-medium .qr-bot-bar,
.qr-container:not(.size-mini):not(.size-atm) .qr-bot-bar { margin: 0 -2.2mm -1.8mm -2.2mm; padding: 0.8mm 0; font-size: 4.8pt; }

/* --- UKURAN 2: MINI (6.0 x 3.8 cm) --- */
.qr-container.size-mini .qr-sticker-wrapper {
  width: 60mm;
  height: 38mm;
}
.qr-container.size-mini .qr-sticker {
  width: 60mm;
  height: 38mm;
  border-radius: 2mm;
  padding: 1.4mm 1.8mm;
}
.qr-container.size-mini .qr-top-bar {
  margin: -1.4mm -1.8mm 0 -1.8mm;
  padding: 0.9mm 1.8mm;
}
.qr-container.size-mini .qr-dot { width: 3.5px; height: 3.5px; }
.qr-container.size-mini .qr-org { font-size: 4.4pt; }
.qr-container.size-mini .qr-cabang { font-size: 4.2pt; padding: 0.2mm 1.2mm; max-width: 30mm; }
.qr-container.size-mini .qr-main-body { gap: 1.8mm; padding: 0.5mm 0; }
.qr-container.size-mini .qr-box-wrap { width: 22mm; height: 22mm; padding: 0.4mm; border-radius: 1mm; }
.qr-container.size-mini .qr-text-wrap { gap: 0.6mm; }
.qr-container.size-mini .qr-kode-badge { font-size: 6.6pt; padding: 0.3mm 1mm; border-radius: 0.8mm; }
.qr-container.size-mini .qr-device-name { font-size: 5.4pt; }
.qr-container.size-mini .qr-user-name { font-size: 5.8pt; padding: 0.3mm 1.2mm; border-radius: 0.6mm; }
.qr-container.size-mini .qr-bot-bar { margin: 0 -1.8mm -1.4mm -1.8mm; padding: 0.6mm 0; font-size: 4.2pt; }

/* --- UKURAN 3: ATM (8.5 x 5.4 cm) --- */
.qr-container.size-atm .qr-sticker-wrapper {
  width: 85.6mm;
  height: 54mm;
}
.qr-container.size-atm .qr-sticker {
  width: 85.6mm;
  height: 54mm;
  border-radius: 3.2mm;
  padding: 2.2mm 2.8mm;
}
.qr-container.size-atm .qr-top-bar {
  margin: -2.2mm -2.8mm 0 -2.8mm;
  padding: 1.5mm 2.8mm;
}
.qr-container.size-atm .qr-dot { width: 5px; height: 5px; }
.qr-container.size-atm .qr-org { font-size: 6pt; }
.qr-container.size-atm .qr-cabang { font-size: 5.5pt; padding: 0.3mm 1.8mm; max-width: 44mm; }
.qr-container.size-atm .qr-main-body { gap: 2.8mm; padding: 1.2mm 0; }
.qr-container.size-atm .qr-box-wrap { width: 33mm; height: 33mm; padding: 0.6mm; border-radius: 2mm; }
.qr-container.size-atm .qr-text-wrap { gap: 1mm; }
.qr-container.size-atm .qr-kode-badge { font-size: 9.2pt; padding: 0.5mm 1.8mm; border-radius: 1.2mm; }
.qr-container.size-atm .qr-device-name { font-size: 7.5pt; }
.qr-container.size-atm .qr-user-name { font-size: 8pt; padding: 0.5mm 1.6mm; border-radius: 1mm; }
.qr-container.size-atm .qr-bot-bar { margin: 0 -2.8mm -2.2mm -2.8mm; padding: 1mm 0; font-size: 5.5pt; }

/* --- UKURAN 4: TOM & JERRY 121 (7.5 x 3.8 cm) --- */
.qr-container.size-tj121 .qr-sticker-wrapper {
  width: 75mm;
  height: 38mm;
}
.qr-container.size-tj121 .qr-sticker {
  width: 75mm;
  height: 38mm;
  border-radius: 2mm;
  padding: 1.5mm 2mm;
}
.qr-container.size-tj121 .qr-top-bar {
  margin: -1.5mm -2mm 0 -2mm;
  padding: 1mm 2mm;
}
.qr-container.size-tj121 .qr-dot { width: 3.5px; height: 3.5px; }
.qr-container.size-tj121 .qr-org { font-size: 5pt; }
.qr-container.size-tj121 .qr-cabang { font-size: 4.5pt; padding: 0.2mm 1.5mm; max-width: 36mm; }
.qr-container.size-tj121 .qr-main-body { gap: 2.2mm; padding: 0.6mm 0; }
.qr-container.size-tj121 .qr-box-wrap { width: 23mm; height: 23mm; padding: 0.5mm; border-radius: 1.2mm; }
.qr-container.size-tj121 .qr-text-wrap { gap: 0.6mm; }
.qr-container.size-tj121 .qr-kode-badge { font-size: 7.2pt; padding: 0.3mm 1.2mm; border-radius: 0.8mm; }
.qr-container.size-tj121 .qr-device-name { font-size: 6.2pt; }
.qr-container.size-tj121 .qr-user-name { font-size: 6.5pt; padding: 0.3mm 1.2mm; border-radius: 0.6mm; }
.qr-container.size-tj121 .qr-bot-bar { margin: 0 -2mm -1.5mm -2mm; padding: 0.7mm 0; font-size: 4.4pt; }

/* --- UKURAN 5: TOM & JERRY 108 (3.8 x 1.8 cm) --- */
.qr-container.size-tj108 .qr-sticker-wrapper {
  width: 38mm;
  height: 18mm;
}
.qr-container.size-tj108 .qr-sticker {
  width: 38mm;
  height: 18mm;
  border-radius: 1mm;
  padding: 0.8mm 1mm;
  border-width: 0.8px;
}
.qr-container.size-tj108 .qr-top-bar {
  margin: -0.8mm -1mm 0 -1mm;
  padding: 0.4mm 1mm;
}
.qr-container.size-tj108 .qr-dot { width: 2px; height: 2px; }
.qr-container.size-tj108 .qr-org { font-size: 3.2pt; letter-spacing: 0; }
.qr-container.size-tj108 .qr-cabang { font-size: 3pt; padding: 0.1mm 0.8mm; max-width: 15mm; }
.qr-container.size-tj108 .qr-main-body { gap: 1mm; padding: 0.2mm 0; }
.qr-container.size-tj108 .qr-box-wrap { width: 11mm; height: 11mm; padding: 0.2mm; border-width: 0.8px; }
.qr-container.size-tj108 .qr-text-wrap { gap: 0.3mm; }
.qr-container.size-tj108 .qr-kode-badge { font-size: 4.5pt; padding: 0.1mm 0.8mm; border-width: 0.5px; }
.qr-container.size-tj108 .qr-device-name { font-size: 3.8pt; -webkit-line-clamp: 1; }
.qr-container.size-tj108 .qr-user-name { font-size: 3.8pt; padding: 0.1mm 0.8mm; border-width: 0.5px; -webkit-line-clamp: 1; }
.qr-container.size-tj108 .qr-bot-bar { margin: 0 -1mm -0.8mm -1mm; padding: 0.3mm 0; font-size: 3pt; }

/* =========================================================================
   CETAK (PRINT)
   ========================================================================= */
@media print {
  html, body {
    background: #fff !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  .no-print, nav, header, footer {
    display: none !important;
  }

  .container, main.container, .container-fluid {
    max-width: 100% !important;
    width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
  }

  .qr-container {
    display: block !important;
    text-align: center !important;
    padding: 0 !important;
    margin: 0 auto !important;
    width: 100% !important;
  }

  .qr-sticker-wrapper {
    display: inline-block !important;
    margin: 1.2mm !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
  }

  .qr-sticker {
    box-shadow: none !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
  }

  .qr-top-bar, .qr-bot-bar, .qr-kode-badge, .qr-user-name, .qr-cabang {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>';

$currentBase = module_base_url();
$singleAsset = ($assetId > 0 && count($rows) === 1);
$pageHeading = $singleAsset ? 'Cetak Stiker QR Komputer' : 'Cetak Stiker QR Aset';

$body = '
<div class="no-print mb-3 p-3 bg-white rounded-3 shadow-sm">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom pb-2 mb-3">
    <div>
      <a class="btn btn-outline-secondary btn-sm mb-1" href="'.e(module_url('qr_admin.php', ['cabang'=>$cabangId])).'"><i class="bi bi-arrow-left"></i> Kembali ke QR Aset</a>
      <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-qr-code me-2 text-primary"></i>'.$pageHeading.'</h4>
      <div class="text-secondary small">Desain stiker modern & berwarna — Tajam, jelas, dan mudah di-scan oleh kamera HP.</div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary fw-semibold px-4 py-2" onclick="window.print()"><i class="bi bi-printer-fill me-1"></i> Print / Cetak Stiker</button>
    </div>
  </div>

  <div class="d-flex flex-wrap align-items-center gap-2">
    <span class="small fw-semibold text-secondary">Pilihan Ukuran Stiker:</span>
    <div class="btn-group btn-group-sm" role="group">
      <button type="button" class="btn btn-outline-primary active" id="btnMedium" onclick="applySize(\'medium\')">Kompak (7.0 x 4.4 cm)</button>
      <button type="button" class="btn btn-outline-primary" id="btnMini" onclick="applySize(\'mini\')">Mini (6.0 x 3.8 cm)</button>
      <button type="button" class="btn btn-outline-primary" id="btnAtm" onclick="applySize(\'atm\')">ATM (8.5 x 5.4 cm)</button>
      <button type="button" class="btn btn-outline-primary" id="btnTj121" onclick="applySize(\'tj121\')" title="Format label Tom & Jerry No. 121 (7.5 x 3.8 cm - 10 label per lembar)">Tom & Jerry 121</button>
      <button type="button" class="btn btn-outline-primary" id="btnTj108" onclick="applySize(\'tj108\')" title="Format label Tom & Jerry No. 108 (3.8 x 1.8 cm - ultra kompak)">Tom & Jerry 108</button>
    </div>
  </div>
</div>

<div class="qr-container size-medium" id="qrContainer">'.$cards.'</div>';

$logoDataUri = app_logo_url();

$script = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
var appLogoUri = '.json_encode($logoDataUri).';

document.querySelectorAll(".qrbox").forEach(function(el){
  if (typeof QRCode === "undefined") {
    el.innerHTML = "<small>QR library gagal dimuat.</small>";
    return;
  }
  new QRCode(el, {
    text: el.dataset.qr,
    width: 220,
    height: 220,
    correctLevel: QRCode.CorrectLevel.H // Level H = 30% error correction
  });

  attachLogoToQr(el, appLogoUri);
});

function attachLogoToQr(containerEl, logoUri) {
  if (!logoUri) return;
  var tries = 0;
  var timer = setInterval(function(){
    tries++;
    var canvas = containerEl.querySelector("canvas");
    if (canvas && canvas.width > 0) {
      clearInterval(timer);
      drawLogoOnCanvas(canvas, logoUri, containerEl);
    } else if (tries > 25) {
      clearInterval(timer);
    }
  }, 40);
}

function drawLogoOnCanvas(canvas, logoUri, containerEl) {
  var ctx = canvas.getContext("2d");
  var img = new Image();
  img.onload = function() {
    var size = canvas.width;
    // Logo takes ~22% of QR width (safely within 30% Level H tolerance)
    var logoSize = Math.round(size * 0.22);
    var center = Math.round((size - logoSize) / 2);
    var pad = Math.round(size * 0.025);

    var bgX = center - pad;
    var bgY = center - pad;
    var bgW = logoSize + (pad * 2);
    var bgH = logoSize + (pad * 2);
    var rad = Math.round(size * 0.035);

    // 1. Draw rounded white background
    ctx.save();
    ctx.fillStyle = "#FFFFFF";
    ctx.beginPath();
    ctx.moveTo(bgX + rad, bgY);
    ctx.lineTo(bgX + bgW - rad, bgY);
    ctx.quadraticCurveTo(bgX + bgW, bgY, bgX + bgW, bgY + rad);
    ctx.lineTo(bgX + bgW, bgY + bgH - rad);
    ctx.quadraticCurveTo(bgX + bgW, bgY + bgH, bgX + bgW - rad, bgY + bgH);
    ctx.lineTo(bgX + rad, bgY + bgH);
    ctx.quadraticCurveTo(bgX, bgY + bgH, bgX, bgY + bgH - rad);
    ctx.lineTo(bgX, bgY + rad);
    ctx.quadraticCurveTo(bgX, bgY, bgX + rad, bgY);
    ctx.closePath();
    ctx.fill();

    // 2. Subtle cyan border
    ctx.strokeStyle = "#30B0E0";
    ctx.lineWidth = Math.max(1, Math.round(size * 0.012));
    ctx.stroke();
    ctx.restore();

    // 3. Draw Bank Mitra logo inside keeping aspect ratio
    var aspect = (img.naturalWidth && img.naturalHeight) ? (img.naturalWidth / img.naturalHeight) : 1;
    var dw = logoSize;
    var dh = logoSize;
    if (aspect > 1) {
      dh = logoSize / aspect;
    } else {
      dw = logoSize * aspect;
    }
    var dx = center + (logoSize - dw) / 2;
    var dy = center + (logoSize - dh) / 2;

    ctx.drawImage(img, dx, dy, dw, dh);

    // Update <img> tag created by qrcodejs if present
    var qImg = containerEl.querySelector("img");
    if (qImg) {
      try {
        qImg.src = canvas.toDataURL("image/png");
      } catch(e) {}
    }
  };
  img.src = logoUri;
}

function downloadSticker(btn, kode) {
  var wrapper = btn.closest(".qr-sticker-wrapper");
  if (!wrapper) return;
  var sticker = wrapper.querySelector(".qr-sticker");
  if (!sticker) return;

  var origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...\';

  if (typeof html2canvas === "function") {
    html2canvas(sticker, {
      scale: 3, // 300 DPI ultra-sharp
      useCORS: true,
      backgroundColor: null,
      logging: false
    }).then(function(canvas) {
      var a = document.createElement("a");
      a.download = "stiker-" + (kode || "aset") + ".png";
      a.href = canvas.toDataURL("image/png");
      a.click();
      btn.disabled = false;
      btn.innerHTML = origHtml;
    }).catch(function(err) {
      console.error(err);
      fallbackDownload(sticker, kode, btn, origHtml);
    });
  } else {
    fallbackDownload(sticker, kode, btn, origHtml);
  }
}

function fallbackDownload(sticker, kode, btn, origHtml) {
  var canvas = sticker.querySelector("canvas");
  if (canvas) {
    var a = document.createElement("a");
    a.download = "qr-" + (kode || "aset") + ".png";
    a.href = canvas.toDataURL("image/png");
    a.click();
  } else {
    alert("Gagal memproses gambar stiker.");
  }
  btn.disabled = false;
  btn.innerHTML = origHtml;
}

function applySize(size) {
  var buttons = {
    medium: document.getElementById("btnMedium"),
    mini: document.getElementById("btnMini"),
    atm: document.getElementById("btnAtm"),
    tj121: document.getElementById("btnTj121"),
    tj108: document.getElementById("btnTj108")
  };
  var container = document.getElementById("qrContainer");

  Object.keys(buttons).forEach(function(k){
    if (buttons[k]) buttons[k].classList.toggle("active", k === size);
  });

  if (container) {
    container.className = "qr-container size-" + size;
  }
}
</script>';

render_page('Cetak Stiker QR Komputer', $body, $head, $script);
