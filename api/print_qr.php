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
    $url = module_url('scan.php', ['t'=>$r['qr_token']]);
    $cabangLabel = $r['cabang_nama'] ?? 'ASET IT';
    $userLabel = !empty($r['karyawan_nama']) && $r['karyawan_nama'] !== '-' ? $r['karyawan_nama'] : 'Umum / Pool';
    $divisiLabel = !empty($r['divisi_nama']) && $r['divisi_nama'] !== '-' ? $r['divisi_nama'] : 'IT / Operasional';

    $cards .= '
    <div class="atm-card-wrapper">
      <div class="atm-card">
        <!-- Header Kartu -->
        <div class="atm-header">
          <span class="atm-logo"><i class="bi bi-qr-code-scan"></i> QR MAINTENANCE</span>
          <span class="atm-tag">'.e($cabangLabel).'</span>
        </div>

        <!-- Body: QR Code & Informasi Aset -->
        <div class="atm-body">
          <div class="atm-qr-col">
            <div id="qr-'.$i.'" class="qrbox" data-qr="'.e($url).'"></div>
          </div>
          <div class="atm-info-col">
            <div class="atm-kode">'.e($r['kode_inventaris'] ?? 'ASET-'.$r['id']).'</div>
            <div class="atm-title" title="'.e(asset_title($r)).'">'.e(asset_title($r)).'</div>
            <div class="atm-user"><i class="bi bi-person"></i> '.e($userLabel).'</div>
            <div class="atm-loc"><i class="bi bi-diagram-3"></i> '.e($divisiLabel).'</div>
          </div>
        </div>

        <!-- Footer Instruksi -->
        <div class="atm-footer">
          <span>SCAN QR SETELAH MAINTENANCE SELESAI</span>
        </div>
      </div>
    </div>';
}

if (!$cards) {
    $cards = '<div class="alert alert-warning">Belum ada QR yang dapat dicetak. Silakan generate QR terlebih dahulu di halaman QR Aset.</div>';
}

$head = '<style>
/* =========================================================================
   UKURAN STANDAR KARTU ATM / ID CARD (CR80: 85.6 mm x 53.98 mm / 8.5 x 5.4 cm)
   ========================================================================= */
@page {
  size: A4 portrait;
  margin: 8mm 6mm;
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
  gap: 4mm;
  padding: 10px 0;
}

.atm-card-wrapper {
  width: 85.6mm;
  height: 54mm;
  display: inline-block;
  box-sizing: border-box;
  page-break-inside: avoid;
}

.atm-card {
  width: 85.6mm;
  height: 54mm;
  background: #ffffff;
  border: 1.2px solid #0d6efd;
  border-radius: 3.5mm;
  padding: 2.8mm 3.2mm;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  position: relative;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}

.atm-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 1.2mm;
  margin-bottom: 1.2mm;
}

.atm-logo {
  font-size: 7.2pt;
  font-weight: 800;
  color: #0d6efd;
  letter-spacing: 0.3px;
}

.atm-tag {
  font-size: 6.2pt;
  font-weight: 700;
  background: #e7f1ff;
  color: #0d6efd;
  padding: 0.4mm 1.6mm;
  border-radius: 1mm;
  text-transform: uppercase;
  max-width: 38mm;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.atm-body {
  display: flex;
  gap: 2.5mm;
  align-items: center;
  flex: 1;
}

.atm-qr-col {
  width: 31mm;
  height: 31mm;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fff;
  border: 1px solid #cbd5e1;
  border-radius: 1.5mm;
  padding: 0.8mm;
  box-sizing: border-box;
  flex-shrink: 0;
}

.atm-qr-col img, .atm-qr-col canvas {
  width: 100% !important;
  height: 100% !important;
  display: block;
}

.atm-info-col {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.6mm;
}

.atm-kode {
  font-size: 8.5pt;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.atm-title {
  font-size: 6.8pt;
  font-weight: 600;
  color: #334155;
  line-height: 1.15;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.atm-user {
  font-size: 6.2pt;
  color: #475569;
  line-height: 1.1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.atm-loc {
  font-size: 6pt;
  color: #64748b;
  line-height: 1.1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.atm-footer {
  text-align: center;
  background: #f1f5f9;
  border-radius: 1mm;
  padding: 0.8mm 0;
  font-size: 5.5pt;
  font-weight: 700;
  color: #334155;
  letter-spacing: 0.3px;
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

  .atm-card-wrapper {
    display: inline-block !important;
    margin: 2mm 1.5mm !important;
  }

  .atm-card {
    border: 1px solid #444 !important;
    box-shadow: none !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  .atm-tag, .atm-footer {
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
<div class="no-print d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 p-3 bg-white rounded-3 shadow-sm">
  <div>
    <a class="btn btn-outline-secondary btn-sm mb-2" href="'.e(module_url('qr_admin.php', ['cabang'=>$cabangId])).'"><i class="bi bi-arrow-left"></i> Kembali ke QR Aset</a>
    <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-credit-card-2-front me-2 text-primary"></i>'.$pageHeading.' (Ukuran Kartu ATM: 8.5 x 5.4 cm)</h4>
    <div class="text-secondary small">Ukuran presisi standar CR80 (Kartu ATM / KTP). Siap dicetak pada kertas stiker dan digunting sesuai garis luar.</div>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-primary fw-semibold px-4" onclick="window.print()"><i class="bi bi-printer-fill me-1"></i> Print / Cetak Label</button>
  </div>
</div>
'.$localWarning.'
<div class="qr-container">'.$cards.'</div>';

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
</script>';

render_page('Cetak Label QR (Ukuran Kartu ATM)', $body, $head, $script);
