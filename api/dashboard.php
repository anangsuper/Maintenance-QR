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
$branchTabs = '<a class="nav-branch-pill '.($cabangId === 0 ? 'active' : '').'" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>0,'status_aset'=>$statusAset,'status_maint'=>$statusMaint])).'"><i class="bi bi-grid-fill"></i> Semua Cabang</a>';
foreach ($cabangs as $c) {
    $cId = (int)($c['id'] ?? 0);
    $cNama = $c['nama'] ?? $c['nama_cabang'] ?? 'Cabang #' . $cId;
    $isActive = ($cId === $cabangId);
    $branchTabs .= '<a class="nav-branch-pill '.($isActive ? 'active' : '').'" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cId,'status_aset'=>$statusAset,'status_maint'=>$statusMaint])).'"><i class="bi bi-building"></i> '.e($cNama).'</a>';
}

// 4. Kartu Progres Monitoring Tiap Cabang
$branchCardsHtml = '';
foreach ($branchSummaries as $bs) {
    $bId = $bs['id'];
    $bName = $bs['nama'];
    $bTotal = $bs['total'];
    $bDone = $bs['done'];
    $bPending = $bs['pending'];
    $bFindings = $bs['findings'];
    $bPercent = $bs['percent'];
    $isCurrent = ($bId === $cabangId);

    $barGradient = $bPercent === 100 ? 'var(--success-gradient)' : ($bPercent >= 50 ? 'var(--primary-gradient)' : 'var(--warning-gradient)');

    $branchCardsHtml .= '
    <div class="col-md-6 col-lg-4">
      <div class="card card-hover p-4 h-100 '.($isCurrent ? 'border-primary border-2 shadow' : '').'">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div class="d-flex align-items-center gap-3">
            <div class="stat-icon-wrapper bg-primary bg-opacity-10 text-primary">
              <i class="bi bi-building"></i>
            </div>
            <div>
              <h5 class="fw-bold mb-0 text-dark">'.e($bName).'</h5>
              <small class="text-secondary fw-semibold">Target: '.$bTotal.' Unit PC</small>
            </div>
          </div>
          <span class="badge-chip '.($bPercent === 100 ? 'chip-success' : 'chip-primary').' fs-6">'.$bPercent.'%</span>
        </div>

        <div class="progress rounded-pill my-2" style="height: 10px; background-color: #f1f5f9;">
          <div class="progress-bar rounded-pill" style="width: '.$bPercent.'%; background: '.$barGradient.'; transition: width 0.6s ease;"></div>
        </div>

        <div class="row g-2 text-center small my-3 py-2 px-1 bg-light rounded-3">
          <div class="col-4 border-end"><div class="small-muted">Selesai</div><strong class="text-success fs-6">'.$bDone.'</strong></div>
          <div class="col-4 border-end"><div class="small-muted">Belum</div><strong class="text-warning fs-6">'.$bPending.'</strong></div>
          <div class="col-4"><div class="small-muted">Temuan</div><strong class="text-danger fs-6">'.$bFindings.'</strong></div>
        </div>

        <div class="d-flex gap-2 mt-auto pt-2 border-top">
          <a class="btn btn-sm btn-primary flex-fill fw-semibold" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$bId])).'"><i class="bi bi-folder2-open me-1"></i> Buka Cabang</a>
          <a class="btn btn-sm btn-outline-secondary" target="_blank" href="'.e(module_url('print_report.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$bId])).'" title="Cetak Rekap Cabang Ini"><i class="bi bi-printer"></i></a>
          <a class="btn btn-sm btn-outline-primary" target="_blank" href="'.e(module_url('print_card.php', ['cabang'=>$bId, 'layout'=>'grid6', 'tahun'=>$year])).'" title="Cetak Semua Kartu (6/A4)"><i class="bi bi-card-checklist"></i></a>
          <a class="btn btn-sm btn-outline-secondary" target="_blank" href="'.e(module_url('print_qr.php', ['cabang'=>$bId])).'" title="Cetak Semua QR"><i class="bi bi-qr-code"></i></a>
        </div>
      </div>
    </div>';
}

// 5. Data Chart.js: 12 Bulan Maintenance
$chartMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$chartDoneData = [];
$chartPendingData = [];
$chartRepairData = [];

for ($m = 1; $m <= 12; $m++) {
    $mInfo = $monthlyOverview[$m] ?? [];
    $chartDoneData[] = (int)($mInfo['done'] ?? 0);
    $chartPendingData[] = (int)($mInfo['pending'] ?? 0);
    $chartRepairData[] = (int)($mInfo['repair'] ?? 0);
}

// 6. Data Chart.js: Distribusi Cabang (Doughnut)
$donutLabels = [];
$donutCounts = [];
$palette = ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316', '#64748b'];
$donutColors = [];
$idx = 0;
foreach ($branchDistribution as $bd) {
    $donutLabels[] = $bd['nama'];
    $donutCounts[] = (int)$bd['count'];
    $donutColors[] = $palette[$idx % count($palette)];
    $idx++;
}
if (empty($donutLabels)) {
    $donutLabels = ['Belum ada aset'];
    $donutCounts = [0];
    $donutColors = ['#cbd5e1'];
}

// 7. Render Baris Tabel: 10 Log Maintenance Terbaru
$recentRowsHtml = '';
foreach ($recentLogs as $r) {
    $stVal = $r['status'] ?? 'Selesai';
    $stLower = strtolower($stVal);

    if ($stLower === 'selesai' || $stLower === 'normal') {
        $badgeClass = 'bg-success bg-opacity-10 text-success border border-success border-opacity-25';
        $icon = '<i class="bi bi-check-circle-fill me-1"></i>';
    } elseif ($stLower === 'proses' || $stLower === 'in progress') {
        $badgeClass = 'bg-info bg-opacity-10 text-info-emphasis border border-info border-opacity-25';
        $icon = '<i class="bi bi-arrow-repeat me-1"></i>';
    } elseif ($stLower === 'temuan' || $stLower === 'perlu perbaikan' || $stLower === 'rusak' || $stLower === 'terlambat') {
        $badgeClass = 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25';
        $icon = '<i class="bi bi-exclamation-triangle-fill me-1"></i>';
    } else {
        $badgeClass = 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25';
        $icon = '<i class="bi bi-dash-circle me-1"></i>';
    }

    $detailBtn = !empty($r['id'])
        ? '<a class="btn btn-sm btn-outline-primary py-1 px-2" href="'.e(module_url('maintenance_detail.php', ['id'=>(int)$r['id']])).'"><i class="bi bi-eye me-1"></i> Detail</a>'
        : '-';

    $timeStr = !empty($r['maintenance_time']) ? '<span class="badge bg-light text-muted border ms-1 font-monospace">'.e(substr($r['maintenance_time'], 0, 5)).'</span>' : '';

    $recentRowsHtml .= '
    <tr>
      <td class="text-nowrap small text-secondary">
        <i class="bi bi-calendar-check me-1 text-primary"></i>'.e(format_id_date($r['maintenance_date'])).$timeStr.'
      </td>
      <td class="fw-bold font-monospace text-primary">'.e($r['kode_inventaris']).'</td>
      <td class="fw-semibold text-dark">'.e($r['nama_perangkat']).'</td>
      <td><span class="badge bg-light text-dark border">'.e($r['cabang_nama']).'</span></td>
      <td><i class="bi bi-person-badge text-secondary me-1"></i>'.e($r['technician_name']).'</td>
      <td><span class="badge px-2 py-1 rounded-pill '.$badgeClass.'">'.$icon.e($stVal).'</span></td>
      <td class="text-end">'.$detailBtn.'</td>
    </tr>';
}
if (!$recentRowsHtml) {
    $recentRowsHtml = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-1"></i>Belum ada aktivitas scan maintenance terbaru.</td></tr>';
}

// 8. Render Baris Tabel: Maintenance Mendatang (Jatuh Tempo 30 Hari)
$upcomingRowsHtml = '';
foreach ($upcomingList as $u) {
    $sisa = (int)$u['sisa_hari'];
    if ($sisa > 7 && $sisa <= 30) {
        $dueBadge = '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1"><i class="bi bi-calendar-event me-1"></i>'.$sisa.' Hari Lagi</span>';
    } elseif ($sisa >= 1 && $sisa <= 7) {
        $dueBadge = '<span class="badge bg-warning bg-opacity-15 text-warning-emphasis border border-warning border-opacity-50 px-2 py-1"><i class="bi bi-clock-fill me-1"></i>'.$sisa.' Hari Lagi</span>';
    } elseif ($sisa === 0) {
        $dueBadge = '<span class="badge bg-warning text-white fw-bold px-2 py-1" style="background-color:#ea580c !important;"><i class="bi bi-exclamation-circle-fill me-1"></i>Hari Ini</span>';
    } else {
        $dueBadge = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Terlambat '.abs($sisa).' Hari</span>';
    }

    $upcomingRowsHtml .= '
    <tr>
      <td class="fw-bold font-monospace text-primary">'.e($u['kode_inventaris']).'</td>
      <td class="fw-semibold text-dark">'.e($u['nama_perangkat']).'</td>
      <td><span class="badge bg-light text-dark border">'.e($u['cabang_nama']).'</span> <small class="text-muted">· '.e($u['divisi_nama']).'</small></td>
      <td class="text-nowrap small text-secondary">'.e(format_id_date($u['due_date'])).'</td>
      <td>'.$dueBadge.'</td>
      <td><i class="bi bi-person text-secondary me-1"></i>'.e($u['karyawan_nama']).'</td>
      <td class="text-end">
        <a class="btn btn-sm btn-outline-success py-1 px-2" href="'.e(module_url('asset_edit.php', ['id'=>(int)$u['asset_id']])).'"><i class="bi bi-pencil-square me-1"></i> Cek</a>
      </td>
    </tr>';
}
if (!$upcomingRowsHtml) {
    $upcomingRowsHtml = '<tr><td colspan="7" class="text-center text-success py-4 fw-bold"><i class="bi bi-check-circle-fill me-2 fs-5"></i>Semua komputer telah selesai di-maintenance pada periode ini!</td></tr>';
}

// 9. Render Baris Tabel: 5 Temuan Belum Selesai Ditindaklanjuti
$findingsRowsHtml = '';
foreach ($unresolvedFindings as $f) {
    $sev = strtolower((string)($f['severity'] ?? 'sedang'));
    if ($sev === 'berat' || $sev === 'tinggi') {
        $sevBadge = '<span class="badge bg-danger px-2 py-1"><i class="bi bi-fire me-1"></i>Tinggi / Berat</span>';
    } elseif ($sev === 'sedang') {
        $sevBadge = '<span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-exclamation-circle me-1"></i>Sedang</span>';
    } else {
        $sevBadge = '<span class="badge bg-info text-dark px-2 py-1"><i class="bi bi-info-circle me-1"></i>Ringan</span>';
    }

    $fActionBtn = !empty($f['log_id'])
        ? '<a class="btn btn-sm btn-outline-danger py-1 px-2" href="'.e(module_url('maintenance_detail.php', ['id'=>(int)$f['log_id']])).'"><i class="bi bi-tools me-1"></i> Tindak Lanjuti</a>'
        : (!empty($f['asset_id']) ? '<a class="btn btn-sm btn-outline-secondary py-1 px-2" href="'.e(module_url('asset_edit.php', ['id'=>(int)$f['asset_id']])).'"><i class="bi bi-eye"></i> Detail</a>' : '-');

    $findingsRowsHtml .= '
    <tr>
      <td>
        <div class="fw-bold font-monospace text-primary">'.e($f['kode_inventaris']).'</div>
        <div class="small text-muted">'.e($f['nama_perangkat']).' ('.e($f['cabang_nama']).')</div>
      </td>
      <td class="text-dark fw-semibold" style="max-width: 260px;">'.nl2br(e($f['finding'])).'</td>
      <td class="text-nowrap small text-secondary"><i class="bi bi-clock me-1"></i>'.e($f['created_at']).'</td>
      <td><i class="bi bi-person-badge text-secondary me-1"></i>'.e($f['reporter']).'</td>
      <td>'.$sevBadge.'</td>
      <td><span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">'.e($f['status']).'</span></td>
      <td class="text-end">'.$fActionBtn.'</td>
    </tr>';
}
if (!$findingsRowsHtml) {
    $findingsRowsHtml = '<tr><td colspan="7" class="text-center text-success py-4"><i class="bi bi-shield-check fs-4 d-block mb-1"></i>Tidak ada temuan kerusakan yang pending. Semua unit dalam kondisi prima.</td></tr>';
}

$modeBadge = is_google_cloud_mode() ? '<span class="badge-chip chip-primary"><i class="bi bi-google"></i> Google Cloud Sheets API v4</span>' : '<span class="badge-chip chip-secondary"><i class="bi bi-database"></i> MySQL Database</span>';
$branchTitle = ($cabangId > 0) ? 'Cabang: ' . e($selectedCabangName) : 'Semua Cabang';

// 10. Head & Script Injection
$head = '
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>
.dashboard-hero {
  background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
  border: 1px solid rgba(226, 232, 240, 0.9);
  border-radius: 18px;
  padding: 24px 28px;
  margin-bottom: 24px;
}

.stat-card-clickable {
  text-decoration: none;
  color: inherit;
  display: block;
}

.stat-card-clickable:hover .card {
  transform: translateY(-4px);
  box-shadow: 0 12px 24px -4px rgba(15, 23, 42, 0.1);
  border-color: #cbd5e1;
}

.nav-branch-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 16px;
  border-radius: 30px;
  font-size: 0.86rem;
  font-weight: 600;
  color: #475569;
  background: #ffffff;
  border: 1.5px solid #e2e8f0;
  text-decoration: none;
  transition: all 0.2s ease;
}

.nav-branch-pill:hover {
  background: #f8fafc;
  color: #1e293b;
  border-color: #cbd5e1;
  transform: translateY(-1px);
}

.nav-branch-pill.active {
  background: #2563eb;
  color: #ffffff;
  border-color: #2563eb;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
}

.chart-card {
  min-height: 380px;
}

.filter-box {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 18px 22px;
}
</style>';

$body = '
<!-- Hero Banner -->
<div class="dashboard-hero shadow-sm">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <div class="mb-2">'.$modeBadge.'</div>
      <h2 class="fw-bold mb-1 text-dark" style="letter-spacing: -0.5px;">Dashboard Monitoring Maintenance</h2>
      <div class="text-secondary">Statistik & visibilitas pemeliharaan komputer real-time periode <strong>'.$monthName.' '.$year.'</strong> · <span class="text-primary fw-semibold">'.e($branchTitle).'</span></div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-action-add fw-bold" href="'.e(module_url('asset_add.php')).'"><i class="bi bi-plus-lg me-1"></i> Tambah Komputer</a>
      <a class="btn btn-outline-primary fw-semibold px-3" target="_blank" href="'.e(module_url('print_card.php', ['cabang'=>$cabangId, 'layout'=>'grid6', 'tahun'=>$year])).'"><i class="bi bi-card-checklist me-1"></i> Cetak Kartu (6/A4)</a>
      <a class="btn btn-primary fw-semibold px-3" target="_blank" href="'.e(module_url('print_report.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'"><i class="bi bi-printer-fill me-1"></i> Cetak Laporan</a>
      <a class="btn btn-outline-success fw-semibold" href="'.e(module_url('export_csv.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV</a>
    </div>
  </div>
</div>

<!-- Form Filter Interaktif Dashboard -->
<div class="filter-box shadow-sm mb-4">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-12 col-md-3">
      <label class="form-label small fw-bold text-secondary mb-1"><i class="bi bi-building me-1"></i> Lokasi Cabang</label>
      <select class="form-select form-select-sm" name="cabang">
        <option value="0" '.($cabangId === 0 ? 'selected' : '').'>🌐 Semua Cabang</option>';
foreach ($cabangs as $c) {
    $cId = (int)($c['id'] ?? 0);
    $cNama = $c['nama'] ?? $c['nama_cabang'] ?? 'Cabang #' . $cId;
    $body .= '<option value="'.$cId.'" '.($cId === $cabangId ? 'selected' : '').'>'.e($cNama).'</option>';
}
$body .= '
      </select>
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold text-secondary mb-1"><i class="bi bi-calendar-month me-1"></i> Bulan</label>
      <select class="form-select form-select-sm" name="bulan">';
for ($m = 1; $m <= 12; $m++) {
    $body .= '<option value="'.$m.'" '.($m === $month ? 'selected' : '').'>'.$monthNames[$m].'</option>';
}
$body .= '
      </select>
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold text-secondary mb-1"><i class="bi bi-calendar3 me-1"></i> Tahun</label>
      <input type="number" class="form-control form-control-sm" name="tahun" value="'.$year.'" min="2020" max="2100">
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold text-secondary mb-1"><i class="bi bi-cpu me-1"></i> Status Aset</label>
      <select class="form-select form-select-sm" name="status_aset">
        <option value="" '.($statusAset === '' ? 'selected' : '').'>Semua Status</option>
        <option value="aktif" '.($statusAset === 'aktif' ? 'selected' : '').'>✅ Aktif</option>
        <option value="rusak" '.($statusAset === 'rusak' ? 'selected' : '').'>⚠️ Rusak / Bermasalah</option>
        <option value="nonaktif" '.($statusAset === 'nonaktif' ? 'selected' : '').'>⛔ Nonaktif</option>
      </select>
    </div>

    <div class="col-6 col-md-3">
      <label class="form-label small fw-bold text-secondary mb-1"><i class="bi bi-tools me-1"></i> Status Maintenance</label>
      <select class="form-select form-select-sm" name="status_maint">
        <option value="" '.($statusMaint === '' ? 'selected' : '').'>Semua Status Maintenance</option>
        <option value="done" '.($statusMaint === 'done' ? 'selected' : '').'>🟢 Selesai</option>
        <option value="pending" '.($statusMaint === 'pending' ? 'selected' : '').'>🟡 Jatuh Tempo / Belum</option>
        <option value="repair" '.($statusMaint === 'repair' ? 'selected' : '').'>🔴 Ada Temuan</option>
      </select>
    </div>

    <div class="col-12 d-flex gap-2 justify-content-end mt-3 pt-2 border-top">
      <a class="btn btn-sm btn-outline-secondary px-3" href="'.e(module_url('dashboard.php')).'"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter</a>
      <button type="submit" class="btn btn-sm btn-primary px-4 fw-bold"><i class="bi bi-funnel-fill me-1"></i> Terapkan Filter</button>
    </div>
  </form>
</div>

<!-- Navigasi Cepat Tab Cabang -->
<div class="card p-3 mb-4 border-0 shadow-sm">
  <div class="d-flex flex-wrap gap-2">
    '.$branchTabs.'
  </div>
</div>

<!-- 1. KARTU RINGKASAN STATISTIK (6 KARTU RESPONSIF) -->
<div class="row g-3 mb-4">
  <!-- 1. Total Seluruh Aset -->
  <div class="col-6 col-md-4 col-xl-2">
    <a href="'.e(module_url('history.php')).'" class="stat-card-clickable" title="Lihat Riwayat & Daftar Aset">
      <div class="card card-hover p-3 h-100 border-0 shadow-sm">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon-wrapper bg-primary bg-opacity-10 text-primary">
            <i class="bi bi-pc-display"></i>
          </div>
          <div>
            <div class="small-muted">TOTAL ASET</div>
            <div class="stat text-dark">'.$totalAll.'</div>
          </div>
        </div>
      </div>
    </a>
  </div>

  <!-- 2. Aset Aktif -->
  <div class="col-6 col-md-4 col-xl-2">
    <a href="'.e(module_url('dashboard.php', ['status_aset'=>'aktif','bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'" class="stat-card-clickable" title="Filter Aset Aktif">
      <div class="card card-hover p-3 h-100 border-0 shadow-sm">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon-wrapper bg-success bg-opacity-10 text-success">
            <i class="bi bi-check2-circle"></i>
          </div>
          <div>
            <div class="small-muted">ASET AKTIF</div>
            <div class="stat text-success">'.$totalActive.'</div>
          </div>
        </div>
      </div>
    </a>
  </div>

  <!-- 3. Aset Rusak / Bermasalah -->
  <div class="col-6 col-md-4 col-xl-2">
    <a href="'.e(module_url('dashboard.php', ['status_aset'=>'rusak','bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'" class="stat-card-clickable" title="Filter Aset Rusak">
      <div class="card card-hover p-3 h-100 border-0 shadow-sm">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon-wrapper bg-danger bg-opacity-10 text-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
          </div>
          <div>
            <div class="small-muted">RUSAK / TEMUAN</div>
            <div class="stat text-danger">'.$totalBroken.'</div>
          </div>
        </div>
      </div>
    </a>
  </div>

  <!-- 4. Jatuh Tempo / Belum Selesai -->
  <div class="col-6 col-md-4 col-xl-2">
    <a href="'.e(module_url('dashboard.php', ['status_maint'=>'pending','bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'" class="stat-card-clickable" title="Filter Maintenance Jatuh Tempo">
      <div class="card card-hover p-3 h-100 border-0 shadow-sm">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon-wrapper bg-warning bg-opacity-15 text-warning-emphasis">
            <i class="bi bi-clock-history"></i>
          </div>
          <div>
            <div class="small-muted">JATUH TEMPO</div>
            <div class="stat text-warning">'.$totalDue.'</div>
          </div>
        </div>
      </div>
    </a>
  </div>

  <!-- 5. Selesai Bulan Ini -->
  <div class="col-6 col-md-4 col-xl-2">
    <a href="'.e(module_url('dashboard.php', ['status_maint'=>'done','bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'" class="stat-card-clickable" title="Filter Maintenance Selesai">
      <div class="card card-hover p-3 h-100 border-0 shadow-sm">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon-wrapper bg-info bg-opacity-10 text-primary">
            <i class="bi bi-patch-check-fill"></i>
          </div>
          <div>
            <div class="small-muted">SELESAI (BULAN INI)</div>
            <div class="stat text-primary">'.$totalDone.' <small class="fs-6 fw-normal text-muted">('.$percentDone.'%)</small></div>
          </div>
        </div>
      </div>
    </a>
  </div>

  <!-- 6. Temuan Belum Ditindaklanjuti -->
  <div class="col-6 col-md-4 col-xl-2">
    <a href="'.e(module_url('audit.php', ['status'=>'repair'])).'" class="stat-card-clickable" title="Buka Audit Temuan Kerusakan">
      <div class="card card-hover p-3 h-100 border-0 shadow-sm">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon-wrapper bg-danger bg-opacity-10 text-danger">
            <i class="bi bi-shield-exclamation"></i>
          </div>
          <div>
            <div class="small-muted">TEMUAN PENDING</div>
            <div class="stat text-danger">'.$totalUnresolvedFindings.'</div>
          </div>
        </div>
      </div>
    </a>
  </div>
</div>

<!-- 2. GRAFIK ANALITIK (MAINTENANCE 12 BULAN & DISTRIBUSI ASET CABANG) -->
<div class="row g-4 mb-4">
  <!-- Grafik Maintenance 12 Bulan -->
  <div class="col-lg-8">
    <div class="card p-4 h-100 border-0 shadow-sm chart-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h5 class="fw-bold text-dark mb-0"><i class="bi bi-graph-up-arrow text-primary me-2"></i>Tren Maintenance Bulanan (Tahun '.$year.')</h5>
          <small class="text-secondary">Distribusi status selesai, proses/temuan, dan belum selesai selama 12 bulan</small>
        </div>
        <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1">Tahun '.$year.'</span>
      </div>
      <div style="position: relative; height: 300px; width: 100%;">
        <canvas id="monthlyMaintenanceChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Grafik Distribusi Aset Cabang (Doughnut) -->
  <div class="col-lg-4">
    <div class="card p-4 h-100 border-0 shadow-sm chart-card">
      <div class="mb-3">
        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-pie-chart-fill text-primary me-2"></i>Distribusi Aset per Cabang</h5>
        <small class="text-secondary">Persebaran total unit komputer aktif di setiap cabang</small>
      </div>
      <div style="position: relative; height: 260px; width: 100%;">
        <canvas id="branchAssetDonutChart"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- 3. GRID MONITORING PROGRESS CABANG -->
<div class="mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-diagram-3-fill text-primary me-2"></i>Progres Maintenance Tiap Cabang ('.$monthName.' '.$year.')</h5>
    <a class="btn btn-sm btn-outline-primary" href="'.e(module_url('cabang_admin.php')).'"><i class="bi bi-gear-fill me-1"></i> Kelola Cabang</a>
  </div>
  <div class="row g-3">
    '.$branchCardsHtml.'
  </div>
</div>

<!-- 4. TABEL LOG MAINTENANCE TERBARU (MAX 10) -->
<div class="card p-4 mb-4 border-0 shadow-sm">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h5 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history text-primary me-2"></i>10 Aktivitas Maintenance Terbaru</h5>
      <small class="text-secondary">Log pemindaian dan pemeriksaan komputer yang baru selesai dilakukan</small>
    </div>
    <a class="btn btn-sm btn-outline-primary" href="'.e(module_url('monthly_history.php')).'"><i class="bi bi-journal-text me-1"></i> Lihat Semua Riwayat</a>
  </div>
  <div class="table-responsive rounded-3 border">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Tanggal & Waktu</th>
          <th>Kode Aset</th>
          <th>Nama Komputer / Perangkat</th>
          <th>Cabang</th>
          <th>Teknisi</th>
          <th>Status</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        '.$recentRowsHtml.'
      </tbody>
    </table>
  </div>
</div>

<!-- 5. TABEL MAINTENANCE MENDATANG / JATUH TEMPO (30 HARI KE DEPAN) -->
<div class="card p-4 mb-4 border-0 shadow-sm">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h5 class="fw-bold text-dark mb-0"><i class="bi bi-hourglass-top text-warning me-2"></i>Maintenance Mendatang & Jatuh Tempo (Periode '.$monthName.' '.$year.')</h5>
      <small class="text-secondary">Daftar komputer yang perlu segera dilakukan perawatan berkala</small>
    </div>
    <a class="btn btn-sm btn-outline-primary" target="_blank" href="'.e(module_url('print_qr.php', ['cabang'=>$cabangId])).'"><i class="bi bi-qr-code me-1"></i> Cetak QR Cabang Ini</a>
  </div>
  <div class="table-responsive rounded-3 border">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Kode Aset</th>
          <th>Perangkat</th>
          <th>Cabang & Divisi</th>
          <th>Jatuh Tempo</th>
          <th>Sisa Hari</th>
          <th>Pengguna / PIC</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        '.$upcomingRowsHtml.'
      </tbody>
    </table>
  </div>
</div>

<!-- 6. TABEL TEMUAN KERUSAKAN BELUM SELESAI (MAX 5) -->
<div class="card p-4 border-0 shadow-sm">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h5 class="fw-bold text-dark mb-0"><i class="bi bi-exclamation-octagon-fill text-danger me-2"></i>Temuan Kerusakan Belum Selesai (Pending Issues)</h5>
      <small class="text-secondary">Laporan temuan kerusakan hardware/software yang membutuhkan tindak lanjut teknisi</small>
    </div>
    <a class="btn btn-sm btn-outline-danger" href="'.e(module_url('audit.php', ['status'=>'repair'])).'"><i class="bi bi-shield-exclamation me-1"></i> Buka Menu Audit Temuan</a>
  </div>
  <div class="table-responsive rounded-3 border">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Aset Terkait</th>
          <th>Deskripsi Temuan / Kerusakan</th>
          <th>Tanggal Ditemukan</th>
          <th>Teknisi / Pelapor</th>
          <th>Prioritas</th>
          <th>Status</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        '.$findingsRowsHtml.'
      </tbody>
    </table>
  </div>
</div>

<!-- Inisialisasi Script Chart.js -->
<script>
document.addEventListener("DOMContentLoaded", function() {
  // 1. Inisialisasi Grafik Batang 12 Bulan Maintenance
  var ctxMonthly = document.getElementById("monthlyMaintenanceChart");
  if (ctxMonthly) {
    new Chart(ctxMonthly, {
      type: "bar",
      data: {
        labels: '.json_encode($chartMonths).',
        datasets: [
          {
            label: "Selesai",
            data: '.json_encode($chartDoneData).',
            backgroundColor: "#10b981",
            borderRadius: 6,
            barPercentage: 0.6
          },
          {
            label: "Ada Temuan / Proses",
            data: '.json_encode($chartRepairData).',
            backgroundColor: "#ef4444",
            borderRadius: 6,
            barPercentage: 0.6
          },
          {
            label: "Belum Selesai",
            data: '.json_encode($chartPendingData).',
            backgroundColor: "#f59e0b",
            borderRadius: 6,
            barPercentage: 0.6
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "top",
            labels: {
              boxWidth: 14,
              font: { weight: "600", size: 12 }
            }
          },
          tooltip: {
            padding: 10,
            cornerRadius: 8,
            callbacks: {
              label: function(context) {
                return " " + context.dataset.label + ": " + context.raw + " Unit";
              }
            }
          }
        },
        scales: {
          x: {
            grid: { display: false }
          },
          y: {
            beginAtZero: true,
            ticks: { precision: 0 }
          }
        }
      }
    });
  }

  // 2. Inisialisasi Grafik Donut Distribusi Aset Cabang
  var ctxDonut = document.getElementById("branchAssetDonutChart");
  if (ctxDonut) {
    new Chart(ctxDonut, {
      type: "doughnut",
      data: {
        labels: '.json_encode($donutLabels).',
        datasets: [{
          data: '.json_encode($donutCounts).',
          backgroundColor: '.json_encode($donutColors).',
          borderWidth: 2,
          borderColor: "#ffffff"
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
            labels: {
              boxWidth: 12,
              font: { size: 11, weight: "500" }
            }
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                var val = context.raw || 0;
                var pct = total > 0 ? Math.round((val / total) * 100) : 0;
                return " " + context.label + ": " + val + " Unit (" + pct + "%)";
              }
            }
          }
        },
        cutout: "68%"
      }
    });
  }
});
</script>
';

render_page('Dashboard Informatif QR Maintenance', $body, $head);
