<?php
require __DIR__ . '/bootstrap.php';
require_admin();

$assetId = max(0, (int)($_GET['asset_id'] ?? 0));
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));
$sizeOption = trim((string)($_GET['size'] ?? 'medium')); // medium (7x4.4cm), mini (6x3.8cm), atm (8.5x5.4cm)

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
    $url = module_url('scan.php', ['t'=>$r['qr_token']]);
    $cabangLabel = $r['cabang_nama'] ?? 'ASET IT';
    $userLabel = !empty($r['karyawan_nama']) && $r['karyawan_nama'] !== '-' ? $r['karyawan_nama'] : 'Umum / Pool';
    $divisiLabel = !empty($r['divisi_nama']) && $r['divisi_nama'] !== '-' ? $r['divisi_nama'] : 'IT / Operasional';

    $cards .= '
    <div class="qr-sticker-wrapper">
      <div class="qr-sticker">
        <!-- Header Kartu -->
        <div class="qr-header">
          <span class="qr-logo"><i class="bi bi-qr-code-scan"></i> QR MAINTENANCE</span>
          <span class="qr-tag">'.e($cabangLabel).'</span>
        </div>

        <!-- Body: QR Code & Informasi Aset -->
        <div class="qr-body">
          <div class="qr-code-col">
            <div id="qr-'.$i.'" class="qrbox" data-qr="'.e($url).'"></div>
          </div>
          <div class="qr-info-col">
            <div class="qr-kode">'.e($r['kode_inventaris'] ?? 'ASET-'.$r['id']).'</div>
            <div class="qr-title" title="'.e(asset_title($r)).'">'.e(asset_title($r)).'</div>
            <div class="qr-user"><i class="bi bi-person"></i> '.e($userLabel).'</div>
            <div class="qr-loc"><i class="bi bi-diagram-3"></i> '.e($divisiLabel).'</div>
          </div>
        </div>

        <!-- Footer Instruksi -->
        <div class="qr-footer">
          <span>SCAN SETELAH MAINTENANCE</span>
        </div>
      </div>
    </div>';
}

if (!$cards) {
    $cards = '<div class="alert alert-warning">Belum ada QR yang dapat dicetak. Silakan generate QR terlebih dahulu di halaman QR Aset.</div>';
}

$head = '<style id="stickerStyle">
/* =========================================================================
   DEFAULT SIZE: KOMPAK / SEDANG (70 mm x 44 mm / 7.0 cm x 4.4 cm)
   Lebih kecil sedikit dari kartu ATM, sangat pas untuk stiker bodi komputer.
   ========================================================================= */
@page {
  size: A4 portrait;
  margin: 7mm 6mm;
}

body {
  background: #f0f2f5;
  font-family: "Segoe UI", Tahoma, Arial, sans-serif;
  color: #111;
}

.qr-container {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 3.5mm;
  padding: 10px 0;
}

/* Base Wrapper & Card Default (Medium: 70 x 44 mm) */
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
  border: 1.2px solid #0d6efd;
  border-radius: 3mm;
  padding: 2.2mm 2.8mm;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  position: relative;
  overflow: hidden;
  box-shadow: 0 3px 10px rgba(0,0,0,0.06);
}

.qr-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 1mm;
  margin-bottom: 1mm;
}

.qr-logo {
  font-size: 6.5pt;
  font-weight: 800;
  color: #0d6efd;
  letter-spacing: 0.2px;
}

.qr-tag {
  font-size: 5.5pt;
  font-weight: 700;
  background: #e7f1ff;
  color: #0d6efd;
  padding: 0.3mm 1.4mm;
  border-radius: 0.8mm;
  text-transform: uppercase;
  max-width: 32mm;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.qr-body {
  display: flex;
  gap: 2.2mm;
  align-items: center;
  flex: 1;
}

.qr-code-col {
  width: 25mm;
  height: 25mm;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fff;
  border: 1px solid #cbd5e1;
  border-radius: 1.2mm;
  padding: 0.6mm;
  box-sizing: border-box;
  flex-shrink: 0;
}

.qr-code-col img, .qr-code-col canvas {
  width: 100% !important;
  height: 100% !important;
  display: block;
}

.qr-info-col {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.4mm;
}

.qr-kode {
  font-size: 7.8pt;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.qr-title {
  font-size: 6.2pt;
  font-weight: 600;
  color: #334155;
  line-height: 1.15;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.qr-user {
  font-size: 5.6pt;
  color: #475569;
  line-height: 1.1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.qr-loc {
  font-size: 5.2pt;
  color: #64748b;
  line-height: 1.1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.qr-footer {
  text-align: center;
  background: #f1f5f9;
  border-radius: 0.8mm;
  padding: 0.6mm 0;
  font-size: 4.8pt;
  font-weight: 700;
  color: #334155;
  letter-spacing: 0.2px;
}

/* =========================================================================
   PENGATURAN CETAK (PRINT)
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
    margin: 1.5mm 1.5mm !important;
  }

  .qr-sticker {
    border: 1px solid #333 !important;
    box-shadow: none !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  .qr-tag, .qr-footer {
    background: #f0f0f0 !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>';

$currentBase = module_base_url();
$isLocal = (str_contains($currentBase, 'localhost') || str_contains($currentBase, '127.0.0.1'));
$localWarning = $isLocal ? '
<div class="alert alert-warning no-print mb-3">
  <div class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> Perhatian: QR Menggunakan Alamat "localhost"</div>
  <div class="small">Kamera HP tidak bisa membuka alamat <code>localhost</code>. Agar QR bisa discan dari HP teknisi, pastikan QR dicetak dari <strong>Domain Vercel Anda</strong> (misal: <code>https://nama-project.vercel.app</code>) atau atur variabel <code>APP_URL</code> ke alamat domain publik Anda.</div>
  <div class="small mt-1 text-muted">URL Target Saat Ini: <code>'.e($currentBase).'</code></div>
</div>' : '';

$singleAsset = ($assetId > 0 && count($rows) === 1);
$pageHeading = $singleAsset ? 'Cetak Label QR Komputer' : 'Cetak Label QR Aset';

$body = '
<div class="no-print mb-3 p-3 bg-white rounded-3 shadow-sm">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom pb-2 mb-3">
    <div>
      <a class="btn btn-outline-secondary btn-sm mb-1" href="'.e(module_url('qr_admin.php', ['cabang'=>$cabangId])).'"><i class="bi bi-arrow-left"></i> Kembali ke QR Aset</a>
      <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-qr-code me-2 text-primary"></i>'.$pageHeading.'</h4>
      <div class="text-secondary small">Format stiker kompak (7 x 4.4 cm) — Pas dan rapi untuk bodi laptop, CPU, atau monitor.</div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary fw-semibold px-4" onclick="window.print()"><i class="bi bi-printer-fill me-1"></i> Print / Cetak Stiker</button>
    </div>
  </div>

  <div class="d-flex flex-wrap align-items-center gap-2">
    <span class="small fw-semibold text-secondary">Pilihan Ukuran Stiker:</span>
    <div class="btn-group btn-group-sm" role="group">
      <button type="button" class="btn btn-outline-primary active" id="btnMedium" onclick="applySize(\'medium\')">Kompak (7.0 x 4.4 cm)</button>
      <button type="button" class="btn btn-outline-primary" id="btnMini" onclick="applySize(\'mini\')">Mini (6.0 x 3.8 cm)</button>
      <button type="button" class="btn btn-outline-primary" id="btnAtm" onclick="applySize(\'atm\')">Kartu ATM (8.5 x 5.4 cm)</button>
    </div>
  </div>
</div>
'.$localWarning.'
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
      body { background: #f0f2f5; font-family: "Segoe UI", Tahoma, Arial, sans-serif; }
      .qr-container { display: flex; flex-wrap: wrap; justify-content: center; gap: 3mm; padding: 10px 0; }
      .qr-sticker-wrapper { width: 60mm; height: 38mm; display: inline-block; box-sizing: border-box; page-break-inside: avoid; }
      .qr-sticker { width: 60mm; height: 38mm; background: #fff; border: 1.2px solid #0d6efd; border-radius: 2.5mm; padding: 1.8mm 2.2mm; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; }
      .qr-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.8mm; margin-bottom: 0.8mm; }
      .qr-logo { font-size: 5.5pt; font-weight: 800; color: #0d6efd; }
      .qr-tag { font-size: 4.8pt; font-weight: 700; background: #e7f1ff; color: #0d6efd; padding: 0.2mm 1.2mm; border-radius: 0.6mm; text-transform: uppercase; }
      .qr-body { display: flex; gap: 1.8mm; align-items: center; flex: 1; }
      .qr-code-col { width: 22mm; height: 22mm; display: flex; align-items: center; justify-content: center; background: #fff; border: 1px solid #cbd5e1; border-radius: 1mm; padding: 0.5mm; flex-shrink: 0; }
      .qr-code-col img, .qr-code-col canvas { width: 100% !important; height: 100% !important; display: block; }
      .qr-info-col { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: 0.3mm; }
      .qr-kode { font-size: 7pt; font-weight: 800; color: #0f172a; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .qr-title { font-size: 5.5pt; font-weight: 600; color: #334155; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .qr-user { font-size: 5pt; color: #475569; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .qr-loc { font-size: 4.8pt; color: #64748b; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .qr-footer { text-align: center; background: #f1f5f9; border-radius: 0.6mm; padding: 0.5mm 0; font-size: 4.2pt; font-weight: 700; color: #334155; }
      @media print {
        body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
        .no-print, nav, header { display: none !important; }
        .container, main.container { max-width: 100% !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
        .qr-container { display: block !important; text-align: center; padding: 0 !important; }
        .qr-sticker-wrapper { display: inline-block !important; margin: 1.2mm !important; }
        .qr-sticker { border: 1px solid #333 !important; box-shadow: none !important; print-color-adjust: exact !important; }
      }
    `;
  } else if (size === "atm") {
    document.getElementById("btnAtm").classList.add("active");
    s.innerHTML = `
      @page { size: A4 portrait; margin: 8mm 6mm; }
      body { background: #f0f2f5; font-family: "Segoe UI", Tahoma, Arial, sans-serif; }
      .qr-container { display: flex; flex-wrap: wrap; justify-content: center; gap: 4mm; padding: 10px 0; }
      .qr-sticker-wrapper { width: 85.6mm; height: 54mm; display: inline-block; box-sizing: border-box; page-break-inside: avoid; }
      .qr-sticker { width: 85.6mm; height: 54mm; background: #fff; border: 1.2px solid #0d6efd; border-radius: 3.5mm; padding: 2.8mm 3.2mm; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; }
      .qr-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 1.2mm; margin-bottom: 1.2mm; }
      .qr-logo { font-size: 7.2pt; font-weight: 800; color: #0d6efd; letter-spacing: 0.3px; }
      .qr-tag { font-size: 6.2pt; font-weight: 700; background: #e7f1ff; color: #0d6efd; padding: 0.4mm 1.6mm; border-radius: 1mm; text-transform: uppercase; }
      .qr-body { display: flex; gap: 2.5mm; align-items: center; flex: 1; }
      .qr-code-col { width: 31mm; height: 31mm; display: flex; align-items: center; justify-content: center; background: #fff; border: 1px solid #cbd5e1; border-radius: 1.5mm; padding: 0.8mm; flex-shrink: 0; }
      .qr-code-col img, .qr-code-col canvas { width: 100% !important; height: 100% !important; display: block; }
      .qr-info-col { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: 0.6mm; }
      .qr-kode { font-size: 8.5pt; font-weight: 800; color: #0f172a; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .qr-title { font-size: 6.8pt; font-weight: 600; color: #334155; line-height: 1.15; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .qr-user { font-size: 6.2pt; color: #475569; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .qr-loc { font-size: 6pt; color: #64748b; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .qr-footer { text-align: center; background: #f1f5f9; border-radius: 1mm; padding: 0.8mm 0; font-size: 5.5pt; font-weight: 700; color: #334155; }
      @media print {
        body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
        .no-print, nav, header { display: none !important; }
        .container, main.container { max-width: 100% !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
        .qr-container { display: block !important; text-align: center; padding: 0 !important; }
        .qr-sticker-wrapper { display: inline-block !important; margin: 2mm 1.5mm !important; }
        .qr-sticker { border: 1px solid #333 !important; box-shadow: none !important; print-color-adjust: exact !important; }
      }
    `;
  } else {
    document.getElementById("btnMedium").classList.add("active");
    s.innerHTML = `
      @page { size: A4 portrait; margin: 7mm 6mm; }
      body { background: #f0f2f5; font-family: "Segoe UI", Tahoma, Arial, sans-serif; }
      .qr-container { display: flex; flex-wrap: wrap; justify-content: center; gap: 3.5mm; padding: 10px 0; }
      .qr-sticker-wrapper { width: 70mm; height: 44mm; display: inline-block; box-sizing: border-box; page-break-inside: avoid; }
      .qr-sticker { width: 70mm; height: 44mm; background: #fff; border: 1.2px solid #0d6efd; border-radius: 3mm; padding: 2.2mm 2.8mm; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; }
      .qr-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 1mm; margin-bottom: 1mm; }
      .qr-logo { font-size: 6.5pt; font-weight: 800; color: #0d6efd; letter-spacing: 0.2px; }
      .qr-tag { font-size: 5.5pt; font-weight: 700; background: #e7f1ff; color: #0d6efd; padding: 0.3mm 1.4mm; border-radius: 0.8mm; text-transform: uppercase; }
      .qr-body { display: flex; gap: 2.2mm; align-items: center; flex: 1; }
      .qr-code-col { width: 25mm; height: 25mm; display: flex; align-items: center; justify-content: center; background: #fff; border: 1px solid #cbd5e1; border-radius: 1.2mm; padding: 0.6mm; flex-shrink: 0; }
      .qr-code-col img, .qr-code-col canvas { width: 100% !important; height: 100% !important; display: block; }
      .qr-info-col { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: 0.4mm; }
      .qr-kode { font-size: 7.8pt; font-weight: 800; color: #0f172a; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .qr-title { font-size: 6.2pt; font-weight: 600; color: #334155; line-height: 1.15; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .qr-user { font-size: 5.6pt; color: #475569; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .qr-loc { font-size: 5.2pt; color: #64748b; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .qr-footer { text-align: center; background: #f1f5f9; border-radius: 0.8mm; padding: 0.6mm 0; font-size: 4.8pt; font-weight: 700; color: #334155; }
      @media print {
        body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
        .no-print, nav, header { display: none !important; }
        .container, main.container { max-width: 100% !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
        .qr-container { display: block !important; text-align: center; padding: 0 !important; }
        .qr-sticker-wrapper { display: inline-block !important; margin: 1.5mm !important; }
        .qr-sticker { border: 1px solid #333 !important; box-shadow: none !important; print-color-adjust: exact !important; }
      }
    `;
  }
}
</script>';

render_page('Cetak Label QR Komputer', $body, $head, $script);
