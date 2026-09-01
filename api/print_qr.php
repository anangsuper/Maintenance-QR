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
    $cards .= '
    <div class="qr-label">
      <div class="row g-3 align-items-center">
        <div class="col-5">
          <div id="qr-'.$i.'" class="qrbox" data-qr="'.e($url).'"></div>
        </div>
        <div class="col-7">
          <div class="fw-bold">'.e($r['kode_inventaris'] ?? 'ASET-'.$r['id']).'</div>
          <div>'.e(asset_title($r)).'</div>
          <div class="small-muted">'.e($r['karyawan_nama'] ?? '-').' · '.e($r['divisi_nama'] ?? '-').'</div>
          <div class="small-muted">'.e($r['cabang_nama'] ?? '-').'</div>
          <div class="mt-2 fw-semibold">SCAN SETELAH MAINTENANCE</div>
        </div>
      </div>
    </div>';
}

if (!$cards) $cards = '<div class="alert alert-warning">Belum ada QR yang dapat dicetak.</div>';

$head = '<style>
.qr-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.qrbox img,.qrbox canvas{max-width:100%;height:auto!important}
@media(max-width:700px){.qr-grid{grid-template-columns:1fr}}
@media print{
  body{background:#fff}
  .container{max-width:none!important}
  .qr-grid{grid-template-columns:repeat(2,1fr);gap:8mm}
  .qr-label{border:1px solid #777;border-radius:3mm;padding:4mm}
}
</style>';

$currentBase = module_base_url();
$isLocal = (str_contains($currentBase, 'localhost') || str_contains($currentBase, '127.0.0.1'));
$localWarning = $isLocal ? '
<div class="alert alert-warning no-print mb-4">
  <div class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> Perhatian: QR Menggunakan Alamat "localhost"</div>
  <div class="small">Kamera HP tidak bisa membuka alamat <code>localhost</code>. Agar QR bisa discan dari HP teknisi, pastikan QR dicetak dari <strong>Domain Vercel Anda</strong> (misal: <code>https://nama-project.vercel.app</code>) atau atur variabel <code>APP_URL</code> ke alamat domain/IP publik Anda.</div>
  <div class="small mt-1 text-muted">URL Target Saat Ini: <code>'.e($currentBase).'</code></div>
</div>' : '';

$body = '
<div class="no-print d-flex justify-content-between align-items-center mb-3">
  <div><h2 class="mb-0">Cetak QR Maintenance</h2><div class="text-secondary">Gunakan kertas stiker agar mudah ditempel pada bodi komputer.</div></div>
  <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print / Cetak</button>
</div>
'.$localWarning.'
<div class="qr-grid">'.$cards.'</div>';

$script = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.querySelectorAll(".qrbox").forEach(function(el){
  if (typeof QRCode === "undefined") {
    el.innerHTML = "<small>QR library gagal dimuat. Pastikan internet tersedia atau pasang qrcode.min.js secara lokal.</small>";
    return;
  }
  new QRCode(el, {
    text: el.dataset.qr,
    width: 180,
    height: 180,
    correctLevel: QRCode.CorrectLevel.M
  });
});
</script>';

render_page('Cetak QR', $body, $head, $script);
