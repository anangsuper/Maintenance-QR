<?php
require __DIR__ . '/bootstrap.php';
require_login();

$assetId = max(0, (int)($_GET['id'] ?? $_GET['asset_id'] ?? 0));
$year = max(2020, min(2100, (int)($_GET['tahun'] ?? date('Y'))));

if ($assetId <= 0) {
    http_response_code(400);
    render_page('Parameter Tidak Valid', '<div class="alert alert-danger">Asset ID tidak valid.</div>');
    exit;
}

$asset = get_asset_by_id($assetId);
if (!$asset) {
    http_response_code(404);
    render_page('Aset Tidak Ditemukan', '<div class="alert alert-warning">Aset tidak ditemukan.</div>');
    exit;
}

$cardMatrix = get_asset_yearly_card_matrix($assetId, $year);

$userDisplay = !empty($asset['karyawan_nama']) && $asset['karyawan_nama'] !== '-' ? $asset['karyawan_nama'] : '';
$ipDisplay = !empty($asset['ip_address']) ? $asset['ip_address'] : (!empty($asset['ip']) ? $asset['ip'] : '');
$printerDisplay = !empty($asset['printer']) ? $asset['printer'] : '';

$cardMatrixRows = '';
for ($m = 1; $m <= 12; $m++) {
    $row = $cardMatrix[$m];
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

    $cardMatrixRows .= '
    <tr style="height: 28px;">
      <td style="border: 1.5px solid #000; width: 100px;" class="fw-bold text-dark text-center font-monospace">'.e($dateLabel).'</td>
      '.$cols1to9.'
      <td style="border: 1.5px solid #000; min-width: 90px;" class="text-center font-monospace small">'.$paraf.'</td>
    </tr>';
}

$head = '<style>
@page {
  size: A4 portrait;
  margin: 10mm 12mm;
}
body {
  font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
  color: #000;
  background: #f8fafc;
}
.card-table {
  width: 100%;
  border-collapse: collapse;
  border: 1.5px solid #000;
}
.card-table th {
  background-color: #8ea9db !important;
  color: #000000 !important;
  border: 1.5px solid #000 !important;
  font-weight: bold;
  font-size: 10pt;
  padding: 6px 2px;
  text-align: center;
}
.card-table td {
  border: 1.5px solid #000;
  font-size: 9.5pt;
  padding: 4px 2px;
}
.info-table {
  width: 100%;
  margin-bottom: 8px;
  font-family: Arial, sans-serif;
  font-weight: bold;
  font-size: 11pt;
  color: #000;
}
.info-table td {
  padding: 4px 2px;
}
.info-line {
  border-bottom: 1.5px solid #000;
  padding-left: 6px;
  font-weight: bold;
}
.ket-box {
  font-family: Arial, sans-serif;
  font-size: 9pt;
  line-height: 1.45;
  margin-top: 8px;
  color: #000;
}
.print-card-wrapper {
  background: #ffffff;
  border: 1.5px solid #000000;
  border-radius: 0px;
  padding: 24px 28px;
  max-width: 720px;
}
@media print {
  .no-print, nav, header { display: none !important; }
  body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
  .print-card-wrapper { box-shadow: none !important; border: 1.5px solid #000 !important; margin: 0 auto !important; padding: 12px 14px !important; }
}
</style>';

$body = '
<div class="container py-3">
  <div class="no-print mb-3 d-flex justify-content-between align-items-center" style="max-width: 720px; margin: 0 auto;">
    <a class="btn btn-outline-secondary btn-sm" href="javascript:history.back()"><i class="bi bi-arrow-left"></i> Kembali</a>
    <button class="btn btn-primary fw-semibold" onclick="window.print()"><i class="bi bi-printer-fill me-1"></i> Cetak Kartu Maintenance</button>
  </div>

  <div class="print-card-wrapper mx-auto">
    
    <!-- Info Header -->
    <table class="info-table">
      <tr>
        <td style="width: 110px;">NAMA</td>
        <td style="width: 20px;">:</td>
        <td class="info-line">'.e($userDisplay).'</td>
      </tr>
      <tr>
        <td>IP</td>
        <td>:</td>
        <td class="info-line">'.e($ipDisplay).'</td>
      </tr>
      <tr>
        <td>PRINTER</td>
        <td>:</td>
        <td class="info-line">'.e($printerDisplay).'</td>
      </tr>
    </table>

    <!-- Matrix Table 12 Bulan -->
    <table class="card-table mt-2">
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
        '.$cardMatrixRows.'
      </tbody>
    </table>

    <!-- Keterangan 9 Item -->
    <div class="ket-box">
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

  </div>
</div>';

render_page('Kartu Kontrol Maintenance · ' . ($asset['kode_inventaris'] ?? ''), $body, $head);
