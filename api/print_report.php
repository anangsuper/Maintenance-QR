<?php
require __DIR__ . '/bootstrap.php';
require_login();

$month = max(1, min(12, (int)($_GET['bulan'] ?? date('n'))));
$year = max(2020, min(2100, (int)($_GET['tahun'] ?? date('Y'))));
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));

$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$monthName = $monthNames[$month] ?? date('F');

$data = get_dashboard_data($month, $year, $cabangId);
$historyRows = get_history_rows($month, $year, $cabangId);

$total = (int)($data['total'] ?? 0);
$done = (int)($data['done'] ?? 0);
$findings = (int)($data['findings'] ?? 0);
$pendingRows = $data['pendingRows'] ?? [];
$cabangs = $data['cabangs'] ?? [];

$cabangName = 'Semua Cabang';
if ($cabangId > 0 && is_array($cabangs)) {
    foreach ($cabangs as $c) {
        if ((int)($c['id'] ?? 0) === $cabangId) {
            $cabangName = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cabangId);
            break;
        }
    }
}

$pending = max(0, $total - $done);
$percent = $total > 0 ? round(($done / $total) * 100) : 0;

// History Rows HTML
$doneTrs = '';
$i = 0;
if (is_array($historyRows)) {
    foreach ($historyRows as $r) {
        $i++;
        $tech = $r['teknisi_nama'] ?? $r['technician_name'] ?? '-';
        $statusBadge = ($r['status'] ?? '') === 'Temuan' 
            ? '<span class="badge text-bg-danger">Temuan</span>' 
            : '<span class="badge text-bg-success">Selesai</span>';

        $doneTrs .= '<tr>
          <td class="text-center">'.$i.'</td>
          <td>'.e(format_id_date((string)($r['maintenance_date'] ?? ''))).' '.e(substr((string)($r['maintenance_time'] ?? ''), 0, 5)).'</td>
          <td><strong>'.e($r['kode_inventaris'] ?? '-').'</strong></td>
          <td>'.e(trim(($r['merk'] ?? '').' '.($r['model'] ?? ''))).'</td>
          <td>'.e($r['karyawan_nama'] ?? '-').'</td>
          <td>'.e($r['cabang_nama'] ?? '-').'</td>
          <td>'.e($tech).'</td>
          <td class="text-center">'.$statusBadge.'</td>
        </tr>';
    }
}
if (!$doneTrs) {
    $doneTrs = '<tr><td colspan="8" class="text-center py-3 text-secondary">Belum ada maintenance yang tercatat untuk periode ini.</td></tr>';
}

// Pending Rows HTML
$pendingTrs = '';
$j = 0;
if (is_array($pendingRows)) {
    foreach ($pendingRows as $r) {
        $j++;
        $pendingTrs .= '<tr>
          <td class="text-center">'.$j.'</td>
          <td><strong>'.e($r['kode_inventaris'] ?? '-').'</strong></td>
          <td>'.e(asset_title($r)).'</td>
          <td>'.e($r['karyawan_nama'] ?? '-').'</td>
          <td>'.e($r['cabang_nama'] ?? '-').'</td>
          <td class="text-center"><span class="badge text-bg-warning">Belum</span></td>
        </tr>';
    }
}
if (!$pendingTrs) {
    $pendingTrs = '<tr><td colspan="6" class="text-center py-3 text-success">✓ Semua aset telah selesai di-maintenance pada periode ini.</td></tr>';
}

$head = '<style>
@media print {
    body { background: #fff!important; color: #000!important; font-size: 12pt; }
    .no-print, nav, header { display: none!important; }
    .container { max-width: 100%!important; width: 100%!important; padding: 0!important; margin: 0!important; }
    .card { border: 1px solid #ccc!important; box-shadow: none!important; }
    .table-bordered th, .table-bordered td { border: 1px solid #777!important; }
    .page-break { page-break-before: always; }
}
.report-header { border-bottom: 2px solid #0d6efd; padding-bottom: 15px; margin-bottom: 25px; }
.stat-box { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; text-align: center; }
.signature-box { margin-top: 50px; }
</style>';

$body = '
<div class="no-print d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <a class="btn btn-outline-secondary btn-sm mb-2" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'">← Kembali ke Dashboard</a>
    <h3 class="mb-0">Cetak Laporan Maintenance Bulanan</h3>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print / Download PDF</button>
  </div>
</div>

<div class="card p-4">
  <!-- Report Header -->
  <div class="report-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h3 class="fw-bold mb-1 text-primary">LAPORAN REKAPITULASI MAINTENANCE IT</h3>
      <div class="fs-5 text-secondary">Periode: <strong>'.$monthName.' '.$year.'</strong> | Cabang: <strong>'.e($cabangName).'</strong></div>
    </div>
    <div class="text-end text-muted small">
      <div>Tanggal Cetak: '.date('d-m-Y H:i').' WITA</div>
      <div>Modul QR Maintenance</div>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="row g-3 mb-4">
    <div class="col-3">
      <div class="stat-box">
        <div class="small-muted">Total Aset</div>
        <div class="fs-3 fw-bold">'.$total.'</div>
      </div>
    </div>
    <div class="col-3">
      <div class="stat-box">
        <div class="small-muted">Sudah Maintenance</div>
        <div class="fs-3 fw-bold text-success">'.$done.' ('.$percent.'%)</div>
      </div>
    </div>
    <div class="col-3">
      <div class="stat-box">
        <div class="small-muted">Belum Maintenance</div>
        <div class="fs-3 fw-bold text-warning">'.$pending.'</div>
      </div>
    </div>
    <div class="col-3">
      <div class="stat-box">
        <div class="small-muted">Ada Temuan</div>
        <div class="fs-3 fw-bold text-danger">'.$findings.'</div>
      </div>
    </div>
  </div>

  <!-- Table 1: Sudah Maintenance -->
  <h5 class="fw-bold mb-3 text-dark">1. Daftar Aset Selesai Maintenance ('.$done.')</h5>
  <div class="table-responsive mb-4">
    <table class="table table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:40px" class="text-center">No</th>
          <th>Waktu Maintenance</th>
          <th>Kode Inventaris</th>
          <th>Perangkat</th>
          <th>Pemilik</th>
          <th>Cabang</th>
          <th>Teknisi</th>
          <th class="text-center">Status</th>
        </tr>
      </thead>
      <tbody>'.$doneTrs.'</tbody>
    </table>
  </div>

  <!-- Table 2: Belum Maintenance -->
  <h5 class="fw-bold mb-3 text-dark">2. Daftar Aset Belum Maintenance ('.$pending.')</h5>
  <div class="table-responsive mb-4">
    <table class="table table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:40px" class="text-center">No</th>
          <th>Kode Inventaris</th>
          <th>Perangkat</th>
          <th>Pemilik</th>
          <th>Cabang</th>
          <th class="text-center">Status</th>
        </tr>
      </thead>
      <tbody>'.$pendingTrs.'</tbody>
    </table>
  </div>

  <!-- Signature Block -->
  <div class="signature-box">
    <div class="row text-center">
      <div class="col-6">
        <div>Dibuat Oleh,</div>
        <div style="height: 70px;"></div>
        <div class="fw-bold text-decoration-underline">'.e(current_user_name()).'</div>
        <div class="small text-secondary">Teknisi IT</div>
      </div>
      <div class="col-6">
        <div>Mengetahui,</div>
        <div style="height: 70px;"></div>
        <div class="fw-bold text-decoration-underline">( Manager / Head of Branch )</div>
        <div class="small text-secondary">Penanggung Jawab</div>
      </div>
    </div>
  </div>
</div>';

render_page('Cetak Laporan Maintenance', $body, $head);
