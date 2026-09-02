<?php
require __DIR__ . '/bootstrap.php';
require_admin();

$assetId = max(0, (int)($_GET['asset_id'] ?? 0));
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));

$rows = get_qr_admin_rows($cabangId);
if ($assetId > 0) {
    $rows = array_filter($rows, function($r) use ($assetId) {
        return (int)($r['id'] ?? 0) === $assetId;
    });
}

$cards = '';
$i = 0;
foreach ($rows as $r) {
    if (empty($r['qr_token'])) continue;
    $i++;
    $url = module_url('scan.php', ['t' => $r['qr_token']]);
    $cabangLabel = !empty($r['cabang_nama']) && $r['cabang_nama'] !== '-' ? $r['cabang_nama'] : 'KPO';
    $userLabel = !empty($r['karyawan_nama']) && $r['karyawan_nama'] !== '-' ? $r['karyawan_nama'] : 'Umum / Pool';
    $divisiLabel = !empty($r['divisi_nama']) && $r['divisi_nama'] !== '-' ? $r['divisi_nama'] : '';
    $userFull = $divisiLabel ? "{$userLabel} ({$divisiLabel})" : $userLabel;
    $deviceTitle = asset_title($r);
    $kode = $r['kode_inventaris'] ?? ('ASET-' . $r['id']);

    $cards .= '
    <div class="qr-sticker-wrapper">
      <div class="qr-sticker">
        <!-- Header Minimalis -->
        <div class="qr-top-bar">
          <span class="qr-org">PT BPR MITRATAMA ARTHABUANA</span>
          <span class="qr-cabang">'.e($cabangLabel).'</span>
        </div>

        <!-- Body: QR Code & Detail Singkat -->
        <div class="qr-main-body">
          <div class="qr-box-wrap">
            <div id="qr-'.$i.'" class="qrbox" data-qr="'.e($url).'"></div>
          </div>
          <div class="qr-text-wrap">
            <div class="qr-kode-inv">'.e($kode).'</div>
            <div class="qr-device-name" title="'.e($deviceTitle).'">'.e($deviceTitle).'</div>
            <div class="qr-user-name"><i class="bi bi-person-fill"></i> '.e($userFull).'</div>
          </div>
        </div>

        <!-- Footer Simpel -->
        <div class="qr-bot-bar">
          <span>SCAN UNTUK MAINTENANCE IT</span>
        </div>
      </div>
    </div>';
}

if (!$cards) {
    $cards = '<div class="alert alert-warning">Belum ada QR yang dapat dicetak. Silakan generate QR terlebih dahulu di halaman QR Aset.</div>';
}

$head = '<style id="stickerStyle">
/* =========================================================================
   DESAIN STIKER QR MODERN, MINIMALIS & SUPER SIMPEL
   Default: 7.0 x 4.4 cm (Kompak, Bersih, Sangat Tajam & Mudah Di-scan)
   ========================================================================= */
@page {
  size: A4 portrait;
  margin: 7mm 6mm;
}

body {
  background: #f8fafc;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  color: #0f172a;
}

.qr-container {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 3.5mm;
  padding: 12px 0;
}

.qr-sticker-wrapper {
  width: 70mm;
  height: 44mm;
  display: inline-block;
  box-sizing: border-box;
  page-break-inside: avoid;
}

.qr-sticker {
  width: 70mm;
  height: 44mm;
  background: #ffffff;
  border: 1.2px solid #1e293b;
  border-radius: 2.5mm;
  padding: 2mm 2.5mm;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.qr-top-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 0.8mm;
}

.qr-org {
  font-size: 5.2pt;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: 0.3px;
  text-transform: uppercase;
}

.qr-cabang {
  font-size: 5pt;
  font-weight: 700;
  color: #2563eb;
  background: #eff6ff;
  padding: 0.2mm 1.2mm;
  border-radius: 0.6mm;
  text-transform: uppercase;
  max-width: 25mm;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.qr-main-body {
  display: flex;
  gap: 2.2mm;
  align-items: center;
  flex: 1;
  padding: 1mm 0;
}

.qr-box-wrap {
  width: 26mm;
  height: 26mm;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ffffff;
  padding: 0.4mm;
  border: 1px solid #cbd5e1;
  border-radius: 1mm;
  flex-shrink: 0;
  box-sizing: border-box;
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
  gap: 0.6mm;
}

.qr-kode-inv {
  font-size: 8pt;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.1;
  word-break: break-word;
  letter-spacing: -0.2px;
}

.qr-device-name {
  font-size: 6.2pt;
  font-weight: 600;
  color: #334155;
  line-height: 1.15;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.qr-user-name {
  font-size: 5.6pt;
  color: #64748b;
  line-height: 1.1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-weight: 500;
}

.qr-bot-bar {
  text-align: center;
  background: #f8fafc;
  border-top: 1px dashed #cbd5e1;
  padding: 0.6mm 0;
  font-size: 4.8pt;
  font-weight: 700;
  color: #475569;
  letter-spacing: 0.4px;
}

/* =========================================================================
   CETAK (PRINT)
   ========================================================================= */
@media print {
  body {
    background: #fff !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  .no-print, nav, header {
    display: none !important;
  }

  .container, main.container {
    max-width: 100% !important;
    width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
  }

  .qr-container {
    display: block !important;
    text-align: center;
    padding: 0 !important;
  }

  .qr-sticker-wrapper {
    display: inline-block !important;
    margin: 1.5mm !important;
  }

  .qr-sticker {
    border: 1px solid #000 !important;
    box-shadow: none !important;
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
      <div class="text-secondary small">Desain stiker simpel & tajam — Bersih, mudah dibaca & cepat di-scan oleh kamera HP.</div>
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
    </div>
  </div>
</div>

<div class="qr-container" id="qrContainer">'.$cards.'</div>';

$script = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.querySelectorAll(".qrbox").forEach(function(el){
  if (typeof QRCode === "undefined") {
    el.innerHTML = "<small>QR library gagal dimuat.</small>";
    return;
  }
  new QRCode(el, {
    text: el.dataset.qr,
    width: 140,
    height: 140,
    correctLevel: QRCode.CorrectLevel.M
  });
});

function applySize(size) {
  document.getElementById("btnMedium").classList.remove("active");
  document.getElementById("btnMini").classList.remove("active");
  document.getElementById("btnAtm").classList.remove("active");

  var s = document.getElementById("stickerStyle");
  if (size === "mini") {
    document.getElementById("btnMini").classList.add("active");
    s.innerHTML = `
      @page { size: A4 portrait; margin: 6mm; }
      body { background: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
      .qr-container { display: flex; flex-wrap: wrap; justify-content: center; gap: 3mm; padding: 10px 0; }
      .qr-sticker-wrapper { width: 60mm; height: 38mm; display: inline-block; box-sizing: border-box; page-break-inside: avoid; }
      .qr-sticker { width: 60mm; height: 38mm; background: #fff; border: 1.2px solid #1e293b; border-radius: 2mm; padding: 1.6mm 2mm; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; }
      .qr-top-bar { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.6mm; }
      .qr-org { font-size: 4.6pt; font-weight: 800; color: #0f172a; letter-spacing: 0.2px; text-transform: uppercase; }
      .qr-cabang { font-size: 4.4pt; font-weight: 700; color: #2563eb; background: #eff6ff; padding: 0.2mm 1mm; border-radius: 0.5mm; }
      .qr-main-body { display: flex; gap: 1.8mm; align-items: center; flex: 1; padding: 0.8mm 0; }
      .qr-box-wrap { width: 22mm; height: 22mm; display: flex; align-items: center; justify-content: center; background: #fff; border: 1px solid #cbd5e1; border-radius: 0.8mm; padding: 0.3mm; flex-shrink: 0; box-sizing: border-box; }
      .qr-box-wrap img, .qr-box-wrap canvas { width: 100% !important; height: 100% !important; display: block; }
      .qr-text-wrap { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: 0.4mm; }
      .qr-kode-inv { font-size: 7pt; font-weight: 800; color: #0f172a; line-height: 1.1; word-break: break-word; }
      .qr-device-name { font-size: 5.5pt; font-weight: 600; color: #334155; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .qr-user-name { font-size: 5pt; color: #64748b; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .qr-bot-bar { text-align: center; background: #f8fafc; border-top: 1px dashed #cbd5e1; padding: 0.4mm 0; font-size: 4.2pt; font-weight: 700; color: #475569; letter-spacing: 0.3px; }
      @media print {
        body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
        .no-print, nav, header { display: none !important; }
        .container, main.container { max-width: 100% !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
        .qr-container { display: block !important; text-align: center; padding: 0 !important; }
        .qr-sticker-wrapper { display: inline-block !important; margin: 1.2mm !important; }
        .qr-sticker { border: 1px solid #000 !important; box-shadow: none !important; print-color-adjust: exact !important; }
      }
    `;
  } else if (size === "atm") {
    document.getElementById("btnAtm").classList.add("active");
    s.innerHTML = `
      @page { size: A4 portrait; margin: 8mm 6mm; }
      body { background: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
      .qr-container { display: flex; flex-wrap: wrap; justify-content: center; gap: 4mm; padding: 10px 0; }
      .qr-sticker-wrapper { width: 85.6mm; height: 54mm; display: inline-block; box-sizing: border-box; page-break-inside: avoid; }
      .qr-sticker { width: 85.6mm; height: 54mm; background: #fff; border: 1.2px solid #1e293b; border-radius: 3mm; padding: 2.5mm 3mm; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; }
      .qr-top-bar { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 1mm; }
      .qr-org { font-size: 6.2pt; font-weight: 800; color: #0f172a; letter-spacing: 0.3px; text-transform: uppercase; }
      .qr-cabang { font-size: 5.6pt; font-weight: 700; color: #2563eb; background: #eff6ff; padding: 0.3mm 1.4mm; border-radius: 0.8mm; }
      .qr-main-body { display: flex; gap: 2.8mm; align-items: center; flex: 1; padding: 1.2mm 0; }
      .qr-box-wrap { width: 33mm; height: 33mm; display: flex; align-items: center; justify-content: center; background: #fff; border: 1px solid #cbd5e1; border-radius: 1.2mm; padding: 0.6mm; flex-shrink: 0; box-sizing: border-box; }
      .qr-box-wrap img, .qr-box-wrap canvas { width: 100% !important; height: 100% !important; display: block; }
      .qr-text-wrap { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: 0.8mm; }
      .qr-kode-inv { font-size: 9.5pt; font-weight: 800; color: #0f172a; line-height: 1.1; word-break: break-word; }
      .qr-device-name { font-size: 7.2pt; font-weight: 600; color: #334155; line-height: 1.15; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .qr-user-name { font-size: 6.4pt; color: #64748b; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .qr-bot-bar { text-align: center; background: #f8fafc; border-top: 1px dashed #cbd5e1; padding: 0.8mm 0; font-size: 5.5pt; font-weight: 700; color: #475569; letter-spacing: 0.4px; }
      @media print {
        body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
        .no-print, nav, header { display: none !important; }
        .container, main.container { max-width: 100% !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
        .qr-container { display: block !important; text-align: center; padding: 0 !important; }
        .qr-sticker-wrapper { display: inline-block !important; margin: 2mm 1.5mm !important; }
        .qr-sticker { border: 1px solid #000 !important; box-shadow: none !important; print-color-adjust: exact !important; }
      }
    `;
  } else {
    document.getElementById("btnMedium").classList.add("active");
    s.innerHTML = `
      @page { size: A4 portrait; margin: 7mm 6mm; }
      body { background: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
      .qr-container { display: flex; flex-wrap: wrap; justify-content: center; gap: 3.5mm; padding: 12px 0; }
      .qr-sticker-wrapper { width: 70mm; height: 44mm; display: inline-block; box-sizing: border-box; page-break-inside: avoid; }
      .qr-sticker { width: 70mm; height: 44mm; background: #ffffff; border: 1.2px solid #1e293b; border-radius: 2.5mm; padding: 2mm 2.5mm; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; }
      .qr-top-bar { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.8mm; }
      .qr-org { font-size: 5.2pt; font-weight: 800; color: #0f172a; letter-spacing: 0.3px; text-transform: uppercase; }
      .qr-cabang { font-size: 5pt; font-weight: 700; color: #2563eb; background: #eff6ff; padding: 0.2mm 1.2mm; border-radius: 0.6mm; text-transform: uppercase; }
      .qr-main-body { display: flex; gap: 2.2mm; align-items: center; flex: 1; padding: 1mm 0; }
      .qr-box-wrap { width: 26mm; height: 26mm; display: flex; align-items: center; justify-content: center; background: #ffffff; padding: 0.4mm; border: 1px solid #cbd5e1; border-radius: 1mm; flex-shrink: 0; box-sizing: border-box; }
      .qr-box-wrap img, .qr-box-wrap canvas { width: 100% !important; height: 100% !important; display: block; }
      .qr-text-wrap { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: 0.6mm; }
      .qr-kode-inv { font-size: 8pt; font-weight: 800; color: #0f172a; line-height: 1.1; word-break: break-word; letter-spacing: -0.2px; }
      .qr-device-name { font-size: 6.2pt; font-weight: 600; color: #334155; line-height: 1.15; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .qr-user-name { font-size: 5.6pt; color: #64748b; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500; }
      .qr-bot-bar { text-align: center; background: #f8fafc; border-top: 1px dashed #cbd5e1; padding: 0.6mm 0; font-size: 4.8pt; font-weight: 700; color: #475569; letter-spacing: 0.4px; }
      @media print {
        body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
        .no-print, nav, header { display: none !important; }
        .container, main.container { max-width: 100% !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
        .qr-container { display: block !important; text-align: center; padding: 0 !important; }
        .qr-sticker-wrapper { display: inline-block !important; margin: 1.5mm !important; }
        .qr-sticker { border: 1px solid #000 !important; box-shadow: none !important; print-color-adjust: exact !important; }
      }
    `;
  }
}
</script>';

render_page('Cetak Stiker QR Komputer', $body, $head, $script);
