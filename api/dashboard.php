<?php
require __DIR__ . '/bootstrap.php';
require_login();

// 1. Ambil Parameter Filter dengan Validasi
$month = max(1, min(12, (int)($_GET['bulan'] ?? date('n'))));
$year = max(2020, min(2100, (int)($_GET['tahun'] ?? date('Y'))));
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));
$statusAset = strtolower(trim((string)($_GET['status_aset'] ?? '')));
$statusMaint = strtolower(trim((string)($_GET['status_maint'] ?? '')));

$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$monthName = $monthNames[$month] ?? date('F');

// Dukungan tombol Refresh data langsung dari Google Sheets
if (!empty($_GET['refresh']) && is_google_cloud_mode()) {
    $client = google_sheets_v4_client();
    if ($client) {
        $client->clearCache();
    }
}

// 2. Ambil Data Dashboard Komprehensif
$dashData = get_comprehensive_dashboard_data($month, $year, $cabangId, $statusAset, $statusMaint);
$branchSummaries = get_branch_maintenance_summary($month, $year);

$totalAll = $dashData['total_all'];
$totalActive = $dashData['total_active'];
$totalBroken = $dashData['total_broken'];
$totalDone = $dashData['total_done'];
$totalDue = $dashData['total_due'];
$totalUnresolvedFindings = $dashData['total_unresolved_findings'];

$recentLogs = $dashData['recent_logs'];
$upcomingList = $dashData['upcoming_list'];
$unresolvedFindings = $dashData['unresolved_findings'];
$branchDistribution = $dashData['branch_distribution'];
$monthlyOverview = $dashData['monthly_overview'];
$cabangs = $dashData['cabangs'];

$percentDone = $totalActive > 0 ? round(($totalDone / $totalActive) * 100) : 0;

$selectedCabangName = 'Semua Cabang';
foreach ($cabangs as $c) {
    if ((int)($c['id'] ?? 0) === $cabangId) {
        $selectedCabangName = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cabangId);
        break;
    }
}

// 3. Tab Bar Navigasi Cepat Cabang
$branchTabs = '<a class="branch-nav-pill '.($cabangId === 0 ? 'active' : '').'" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>0,'status_aset'=>$statusAset,'status_maint'=>$statusMaint])).'">Semua Cabang</a>';
foreach ($cabangs as $c) {
    $cId = (int)($c['id'] ?? 0);
    $cNama = $c['nama'] ?? $c['nama_cabang'] ?? 'Cabang #' . $cId;
    $isActive = ($cId === $cabangId);
    $branchTabs .= '<a class="branch-nav-pill '.($isActive ? 'active' : '').'" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cId,'status_aset'=>$statusAset,'status_maint'=>$statusMaint])).'">'.e($cNama).'</a>';
}

// 4. Compact Branch Compliance Rows
$branchRowsHtml = '';
foreach ($branchSummaries as $bs) {
    $bId = $bs['id'];
    $bName = $bs['nama'];
    $bTotal = $bs['total'];
    $bDone = $bs['done'];
    $bPending = $bs['pending'];
    $bFindings = $bs['findings'];
    $bPercent = $bs['percent'];
    $isCurrent = ($bId === $cabangId);

    $complianceBadge = $bPercent === 100 
        ? '<span class="badge-chip chip-success">100%</span>' 
        : ($bPercent >= 75 
            ? '<span class="badge-chip chip-primary">'.$bPercent.'%</span>' 
            : '<span class="badge-chip chip-warning">'.$bPercent.'%</span>');

    $branchRowsHtml .= '
    <tr class="'.($isCurrent ? 'table-active' : '').'">
      <td>
        <div class="fw-semibold text-dark">'.e($bName).'</div>
        <div class="small text-muted">ID: #'.$bId.'</div>
      </td>
      <td class="text-center fw-semibold">'.$bTotal.'</td>
      <td class="text-center text-success fw-semibold">'.$bDone.'</td>
      <td class="text-center text-secondary">'.$bPending.'</td>
      <td class="text-center">'.($bFindings > 0 ? '<span class="text-danger fw-bold">'.$bFindings.'</span>' : '<span class="text-muted">0</span>').'</td>
      <td class="text-center">'.$complianceBadge.'</td>
      <td class="text-end text-nowrap">
        <div class="btn-group btn-group-sm">
          <a class="btn btn-sm btn-light border" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$bId])).'" title="Filter Dashboard Cabang Ini"><i class="bi bi-funnel"></i></a>
          <a class="btn btn-sm btn-light border" target="_blank" href="'.e(module_url('print_report.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$bId])).'" title="Cetak Rekap"><i class="bi bi-printer"></i></a>
          <a class="btn btn-sm btn-light border" target="_blank" href="'.e(module_url('print_card.php', ['cabang'=>$bId, 'layout'=>'grid6', 'tahun'=>$year])).'" title="Cetak Kartu Kontrol (6/A4)"><i class="bi bi-card-checklist"></i></a>
        </div>
      </td>
    </tr>';
}

// 5. Activity Stream (Timeline Items)
$activityStreamHtml = '';
if (!empty($recentLogs)) {
    foreach (array_slice($recentLogs, 0, 8) as $r) {
        $stVal = $r['status'] ?? 'Selesai';
        $stLower = strtolower($stVal);
        $timeStr = !empty($r['maintenance_time']) ? substr($r['maintenance_time'], 0, 5) : 'Hari ini';
        $dateStr = !empty($r['maintenance_date']) ? format_id_date($r['maintenance_date']) : '-';
        
        $dotClass = (in_array($stLower, ['temuan', 'perlu perbaikan'], true)) ? 'critical' : 'operational';
        $statusText = (in_array($stLower, ['temuan', 'perlu perbaikan'], true)) ? 'Temuan Kerusakan' : 'Maintenance Selesai';

        $activityStreamHtml .= '
        <div class="activity-item d-flex align-items-start gap-3 py-2 border-bottom">
          <div class="activity-time font-monospace text-muted small mt-1">'.e($timeStr).'</div>
          <span class="status-dot '.$dotClass.' mt-2"></span>
          <div class="flex-grow-1 min-w-0">
            <div class="d-flex align-items-center justify-content-between">
              <span class="fw-semibold text-dark small">'.e($statusText).'</span>
              <span class="text-muted" style="font-size: 0.72rem;">'.e($dateStr).'</span>
            </div>
            <div class="small text-truncate text-secondary">
              <strong class="text-primary">'.e($r['kode_inventaris'] ?? 'Aset').'</strong> · '.e($r['perangkat'] ?? ($r['merk'].' '.$r['model'])).' ('.e($r['cabang_nama'] ?? '-').')
            </div>
            <div class="text-muted" style="font-size: 0.72rem;">Teknisi: '.e($r['technician_name'] ?? 'Teknisi').'</div>
          </div>
          '.(!empty($r['log_id']) ? '<a href="'.e(module_url('maintenance_detail.php', ['id' => $r['log_id']])).'" class="btn btn-sm btn-light border py-1 px-2" title="Detail"><i class="bi bi-chevron-right"></i></a>' : '').'
        </div>';
    }
} else {
    $activityStreamHtml = '<div class="text-center py-4 text-muted small"><i class="bi bi-clock-history fs-4 d-block mb-2 text-secondary opacity-50"></i>Belum ada aktivitas maintenance pada periode ini.</div>';
}

// 6. Active Findings List
$findingsListHtml = '';
if (!empty($unresolvedFindings)) {
    foreach (array_slice($unresolvedFindings, 0, 5) as $uf) {
        $findingsListHtml .= '
        <div class="p-2 mb-2 rounded border border-danger-subtle bg-danger-subtle d-flex align-items-start gap-2">
          <i class="bi bi-exclamation-triangle-fill text-danger mt-1"></i>
          <div class="flex-grow-1 min-w-0">
            <div class="fw-semibold text-danger small text-truncate">'.e($uf['kode_inventaris'] ?? 'Aset').' · '.e($uf['perangkat'] ?? 'Perangkat').'</div>
            <div class="small text-dark text-truncate">'.e($uf['findings'] ?? 'Kendala perangkat').'</div>
            <div class="text-muted" style="font-size: 0.7rem;">'.e($uf['cabang_nama'] ?? '-').' · '.e(format_id_date($uf['date'] ?? '')).'</div>
          </div>
          <a href="'.e(module_url('maintenance_detail.php', ['id' => $uf['log_id'] ?? 0])).'" class="btn btn-sm btn-danger py-0 px-2 fw-semibold" style="font-size: 0.75rem;">Periksa</a>
        </div>';
    }
} else {
    $findingsListHtml = '<div class="text-center py-3 text-muted small"><i class="bi bi-check-circle text-success fs-5 d-block mb-1"></i>Tidak ada temuan kendala aktif. Seluruh perangkat beroperasi normal.</div>';
}

$head = '
<style>
.branch-nav-bar {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 6px;
  margin-bottom: 24px;
}
.branch-nav-pill {
  white-space: nowrap;
  padding: 6px 14px;
  font-size: 0.82rem;
  font-weight: 500;
  border-radius: 6px;
  background: #FFFFFF;
  border: 1px solid var(--border-subtle);
  color: var(--text-secondary);
  text-decoration: none;
  transition: all 0.15s ease;
}
.branch-nav-pill:hover {
  background: #F8FAFC;
  color: var(--text-primary);
  border-color: var(--border-strong);
}
.branch-nav-pill.active {
  background: var(--blue-corporate);
  border-color: var(--blue-corporate);
  color: #FFFFFF;
  font-weight: 600;
}
.ops-header-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
}
.activity-time {
  min-width: 45px;
  font-size: 0.72rem;
}
</style>';

$body = '
<!-- Page Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div>
    <div class="tech-label mb-1">BANKING IT OPERATIONS COMMAND</div>
    <h1 class="h3 mb-1">Dashboard Monitoring IT</h1>
    <p class="text-secondary small mb-0">Ringkasan aset, kepatuhan checklist pemeliharaan, dan tindak lanjut teknisi · Periode: <strong>'.$monthName.' '.$year.'</strong></p>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <div class="ops-header-badge" style="background:#ECFDF3; border:1px solid #A6F4C5; color:#16803C;">
      <span class="status-dot operational"></span>
      <span>Operasional Normal</span>
    </div>
    <form method="get" class="d-flex align-items-center gap-2">
      <select name="bulan" class="form-select form-select-sm" style="width: 120px;" onchange="this.form.submit()">';
for ($m = 1; $m <= 12; $m++) {
    $body .= '<option value="'.$m.'"'.($m === $month ? ' selected' : '').'>'.$monthNames[$m].'</option>';
}
$body .= '
      </select>
      <select name="tahun" class="form-select form-select-sm" style="width: 90px;" onchange="this.form.submit()">';
for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++) {
    $body .= '<option value="'.$y.'"'.($y === $year ? ' selected' : '').'>'.$y.'</option>';
}
$body .= '
      </select>
      <input type="hidden" name="cabang" value="'.$cabangId.'">
      <a href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId,'refresh'=>1])).'" class="btn btn-sm btn-light border" title="Segarkan Data Google Sheets"><i class="bi bi-arrow-clockwise"></i></a>
    </form>
  </div>
</div>

<!-- Branch Switcher Bar -->
<div class="branch-nav-bar custom-scrollbar">
  '.$branchTabs.'
</div>

<!-- Top KPI Metrics (Dominant Numbers) -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4 col-xl-2dot4" style="flex: 0 0 20%; max-width: 20%;">
    <div class="card card-metric h-100" style="border-left-color: var(--blue-corporate);">
      <div class="metric-value">'.$totalAll.'</div>
      <div class="metric-label">Total Aset Komputer</div>
      <div class="small text-muted mt-2" style="font-size: 0.72rem;">Unit terdaftar</div>
    </div>
  </div>

  <div class="col-6 col-md-4 col-xl-2dot4" style="flex: 0 0 20%; max-width: 20%;">
    <div class="card card-metric h-100" style="border-left-color: #16803C;">
      <div class="metric-value text-success">'.$totalDone.'</div>
      <div class="metric-label">Selesai Diperiksa</div>
      <div class="small text-success mt-2" style="font-size: 0.72rem;"><i class="bi bi-check-circle me-1"></i>'.$percentDone.'% kepatuhan</div>
    </div>
  </div>

  <div class="col-6 col-md-4 col-xl-2dot4" style="flex: 0 0 20%; max-width: 20%;">
    <div class="card card-metric h-100" style="border-left-color: #B54708;">
      <div class="metric-value text-warning">'.$totalDue.'</div>
      <div class="metric-label">Belum Maintenance</div>
      <div class="small text-secondary mt-2" style="font-size: 0.72rem;">Menunggu giliran</div>
    </div>
  </div>

  <div class="col-6 col-md-4 col-xl-2dot4" style="flex: 0 0 20%; max-width: 20%;">
    <div class="card card-metric h-100" style="border-left-color: #B42318;">
      <div class="metric-value text-danger">'.$totalUnresolvedFindings.'</div>
      <div class="metric-label">Temuan Kendala</div>
      <div class="small text-danger mt-2" style="font-size: 0.72rem;">Perlu perbaikan</div>
    </div>
  </div>

  <div class="col-12 col-md-4 col-xl-2dot4" style="flex: 0 0 20%; max-width: 20%;">
    <div class="card card-metric h-100" style="border-left-color: #2E7CF6;">
      <div class="metric-value text-primary">'.($totalAll - $totalBroken).'</div>
      <div class="metric-label">Perangkat Aktif</div>
      <div class="small text-secondary mt-2" style="font-size: 0.72rem;">Siap pakai</div>
    </div>
  </div>
</div>

<style>
@media (max-width: 1199px) {
  .col-xl-2dot4 { flex: 0 0 50% !important; max-width: 50% !important; }
}
@media (max-width: 575px) {
  .col-xl-2dot4 { flex: 0 0 100% !important; max-width: 100% !important; }
}
</style>

<!-- Asymmetric Operations Center Layout -->
<div class="row g-4">
  <!-- Left Column (8 cols): Branch Matrix & Live Activity Stream -->
  <div class="col-lg-8">
    <!-- Branch Compliance Matrix -->
    <div class="card mb-4">
      <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
        <div>
          <h2 class="h6 mb-0 fw-semibold text-dark"><i class="bi bi-buildings me-2 text-primary"></i>Kepatuhan Maintenance Per Cabang</h2>
          <div class="text-secondary small">Monitoring progres bulanan di masing-masing kantor kas & cabang</div>
        </div>
        <a href="'.e(module_url('print_report.php', ['bulan'=>$month,'tahun'=>$year])).'" target="_blank" class="btn btn-sm btn-light border"><i class="bi bi-printer me-1"></i> Cetak Rekap</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Kantor Cabang</th>
              <th class="text-center">Target Unit</th>
              <th class="text-center">Selesai</th>
              <th class="text-center">Belum</th>
              <th class="text-center">Temuan</th>
              <th class="text-center">Kepatuhan</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
            '.$branchRowsHtml.'
          </tbody>
        </table>
      </div>
    </div>

    <!-- Live Activity Stream -->
    <div class="card">
      <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
        <div>
          <h2 class="h6 mb-0 fw-semibold text-dark"><i class="bi bi-activity me-2 text-primary"></i>Live Activity Stream</h2>
          <div class="text-secondary small">Log rekam jejak pemeriksaan maintenance dan audit terbaru</div>
        </div>
        <a href="'.e(module_url('audit.php')).'" class="btn btn-sm btn-light border">Lihat Semua Log</a>
      </div>
      <div class="card-body p-3 p-md-4">
        <div class="activity-stream">
          '.$activityStreamHtml.'
        </div>
      </div>
    </div>
  </div>

  <!-- Right Column (4 cols): Compliance Panel, Asset Health & Active Issues -->
  <div class="col-lg-4">
    <!-- Maintenance Compliance Panel -->
    <div class="card mb-4">
      <div class="card-header bg-white border-bottom py-3 px-4">
        <div class="tech-label">INSPECTION COMPLIANCE</div>
        <h2 class="h6 mb-0 fw-semibold text-dark">Kepatuhan Periode Ini</h2>
      </div>
      <div class="card-body p-4">
        <div class="d-flex align-items-baseline justify-content-between mb-2">
          <span class="display-6 fw-bold text-dark">'.$percentDone.'%</span>
          <span class="small text-secondary fw-semibold">'.$totalDone.' dari '.$totalActive.' unit</span>
        </div>
        <div class="progress mb-3" style="height: 8px;">
          <div class="progress-bar '.($percentDone >= 80 ? 'bg-success' : ($percentDone >= 50 ? 'bg-primary' : 'bg-warning')).'" style="width: '.$percentDone.'%;"></div>
        </div>
        <div class="d-flex justify-content-between small text-secondary pt-1 border-top">
          <span><span class="status-dot operational me-1"></span> Selesai: <strong>'.$totalDone.'</strong></span>
          <span><span class="status-dot warning me-1"></span> Belum: <strong>'.$totalDue.'</strong></span>
          <span><span class="status-dot critical me-1"></span> Temuan: <strong>'.$totalUnresolvedFindings.'</strong></span>
        </div>
      </div>
    </div>

    <!-- Asset Health Status Dots -->
    <div class="card mb-4">
      <div class="card-header bg-white border-bottom py-3 px-4">
        <div class="tech-label">HARDWARE HEALTH</div>
        <h2 class="h6 mb-0 fw-semibold text-dark">Status Kondisi Perangkat</h2>
      </div>
      <div class="card-body p-4">
        <div class="d-flex flex-column gap-3">
          <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
              <span class="status-dot operational"></span>
              <span class="small fw-semibold text-dark">Operasional Normal</span>
            </div>
            <span class="badge-chip chip-success">'.max(0, $totalAll - $totalBroken - $totalUnresolvedFindings).' Unit</span>
          </div>

          <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
              <span class="status-dot warning"></span>
              <span class="small fw-semibold text-dark">Perlu Perhatian / Maintenance</span>
            </div>
            <span class="badge-chip chip-warning">'.$totalDue.' Unit</span>
          </div>

          <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
              <span class="status-dot critical"></span>
              <span class="small fw-semibold text-dark">Temuan Kerusakan Aktif</span>
            </div>
            <span class="badge-chip chip-danger">'.$totalUnresolvedFindings.' Unit</span>
          </div>

          <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
              <span class="status-dot offline"></span>
              <span class="small fw-semibold text-dark">Nonaktif / Rusak Permanen</span>
            </div>
            <span class="badge-chip chip-secondary">'.$totalBroken.' Unit</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Active Findings Panel -->
    <div class="card">
      <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
        <div>
          <div class="tech-label">REPAIR & FINDINGS</div>
          <h2 class="h6 mb-0 fw-semibold text-dark">Temuan Masalah Aktif</h2>
        </div>
        <span class="badge bg-danger rounded-pill">'.$totalUnresolvedFindings.'</span>
      </div>
      <div class="card-body p-3">
        '.$findingsListHtml.'
      </div>
    </div>
  </div>
</div>';

render_page('Dashboard IT Operations', $body, $head);
