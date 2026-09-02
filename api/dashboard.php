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
$branchSummaries = get_branch_maintenance_summary($month, $year);

$total = $data['total'];
$done = $data['done'];
$findings = $data['findings'];
$pendingRows = $data['pendingRows'];
$recentRows = $data['recentRows'];
$cabangs = $data['cabangs'];

$pending = max(0, $total - $done);
$percent = $total > 0 ? round(($done / $total) * 100) : 0;

$selectedCabangName = 'Semua Cabang';
foreach ($cabangs as $c) {
    if ((int)($c['id'] ?? 0) === $cabangId) {
        $selectedCabangName = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cabangId);
        break;
    }
}

// 1. Tab Bar Navigasi Cabang (Branch Navigation Pills)
$branchTabs = '<a class="nav-branch-pill '.($cabangId === 0 ? 'active' : '').'" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>0])).'"><i class="bi bi-grid-fill"></i> Semua Cabang</a>';
foreach ($cabangs as $c) {
    $cId = (int)($c['id'] ?? 0);
    $cNama = $c['nama'] ?? $c['nama_cabang'] ?? 'Cabang #' . $cId;
    $isActive = ($cId === $cabangId);
    $branchTabs .= '<a class="nav-branch-pill '.($isActive ? 'active' : '').'" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cId])).'"><i class="bi bi-building"></i> '.e($cNama).'</a>';
}

// 2. Kartu Progress Monitoring Tiap Cabang (Branch Cards Grid)
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
          <a class="btn btn-sm btn-outline-secondary" target="_blank" href="'.e(module_url('print_qr.php', ['cabang'=>$bId])).'" title="Cetak Semua QR Cabang Ini"><i class="bi bi-qr-code"></i></a>
        </div>
      </div>
    </div>';
}

// 3. Tabel Belum Maintenance
$pendingHtml = '';
foreach ($pendingRows as $r) {
    $pendingHtml .= '<tr>
      <td><a href="'.e(module_url('asset_edit.php', ['id' => (int)$r['id']])).'" class="fw-bold text-decoration-none text-primary" title="Klik untuk Edit Data"><i class="bi bi-pencil-square me-1 small"></i>'.e($r['kode_inventaris'] ?? '-').'</a></td>
      <td class="fw-semibold text-dark">'.e(asset_title($r)).'</td>
      <td><span class="d-inline-flex align-items-center gap-1"><i class="bi bi-person-circle text-secondary"></i> '.e($r['karyawan_nama'] ?? '-').'</span></td>
      <td><span class="badge-chip chip-secondary">'.e($r['cabang_nama'] ?? '-').' · '.e($r['divisi_nama'] ?? '-').'</span></td>
      <td><span class="badge-chip chip-warning"><i class="bi bi-hourglass-split"></i> Belum</span></td>
    </tr>';
}
if ($pendingHtml === '') {
    $pendingHtml = '<tr><td colspan="5" class="text-center text-success py-4 fw-bold"><i class="bi bi-check-circle-fill me-2 fs-5"></i> Semua komputer di cabang ini telah selesai di-maintenance untuk periode ini.</td></tr>';
}

// 4. Tabel Scan Terbaru
$recentHtml = '';
foreach ($recentRows as $r) {
    $statusChip = (($r['status'] ?? '') === 'Temuan') 
        ? '<span class="badge-chip chip-danger"><i class="bi bi-exclamation-triangle-fill"></i> Ada Temuan</span>' 
        : '<span class="badge-chip chip-success"><i class="bi bi-check-circle-fill"></i> Selesai</span>';

    $actionBtn = !empty($r['id'])
        ? '<a class="btn btn-sm btn-outline-primary" href="'.e(module_url('maintenance_detail.php', ['id'=>(int)$r['id']])).'"><i class="bi bi-file-earmark-medical me-1"></i> Detail</a>'
        : '-';

    $recentHtml .= '<tr>
      <td class="text-secondary small"><i class="bi bi-calendar-event me-1"></i>'.e(format_id_date($r['maintenance_date'])).' <span class="badge bg-light text-dark border">'.e(substr($r['maintenance_time'],0,5)).'</span></td>
      <td class="fw-bold text-primary">'.e($r['kode_inventaris'] ?? '-').'</td>
      <td class="fw-semibold text-dark">'.e(trim(($r['merk'] ?? '').' '.($r['model'] ?? ''))).'</td>
      <td>'.e($r['karyawan_nama'] ?? '-').'</td>
      <td><span class="badge-chip chip-secondary">'.e($r['cabang_nama'] ?? '-').'</span></td>
      <td>'.$statusChip.'</td>
      <td class="text-end">'.$actionBtn.'</td>
    </tr>';
}
if ($recentHtml === '') {
    $recentHtml = '<tr><td colspan="7" class="text-center text-secondary py-4">Belum ada scan maintenance pada periode ini.</td></tr>';
}

$modeBadge = is_google_cloud_mode() ? '<span class="badge-chip chip-primary"><i class="bi bi-google"></i> Google Cloud Sheets API v4</span>' : '<span class="badge-chip chip-secondary"><i class="bi bi-database"></i> MySQL Database</span>';
$branchTitle = ($cabangId > 0) ? 'Cabang: ' . e($selectedCabangName) : 'Semua Cabang';

$head = '<style>
.dashboard-hero {
  background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);
  border: 1px solid rgba(226, 232, 240, 0.9);
  border-radius: 18px;
  padding: 24px 28px;
  margin-bottom: 24px;
}

.nav-branch-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 18px;
  border-radius: 30px;
  font-size: 0.88rem;
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
</style>';

$body = '
<!-- Hero Banner -->
<div class="dashboard-hero shadow-sm">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <div class="mb-2">'.$modeBadge.'</div>
      <h2 class="fw-bold mb-1 text-dark" style="letter-spacing: -0.5px;">Dashboard Maintenance Bulanan</h2>
      <div class="text-secondary">Monitoring real-time pemeliharaan komputer hardware & OS periode <strong>'.$monthName.' '.$year.'</strong>.</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-action-add fw-bold" href="'.e(module_url('asset_add.php')).'"><i class="bi bi-plus-lg me-1"></i> Tambah Komputer</a>
      <a class="btn btn-warning fw-bold text-dark px-3" href="'.e(module_url('import_kpo.php')).'"><i class="bi bi-cloud-arrow-up-fill me-1"></i> Import 44 Data KPO</a>
      <a class="btn btn-primary fw-semibold px-3" target="_blank" href="'.e(module_url('print_report.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'"><i class="bi bi-printer-fill me-1"></i> Cetak Laporan</a>
      <a class="btn btn-outline-success fw-semibold" href="'.e(module_url('export_csv.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV</a>
    </div>
  </div>
</div>

<!-- Navigasi Cepat Tab Cabang -->
<div class="card p-3 mb-4 border-0">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
    <span class="fw-bold text-dark fs-6"><i class="bi bi-funnel-fill text-primary me-1"></i> Filter Lokasi Cabang:</span>
    <form method="get" class="d-flex gap-2 align-items-center">
      <input type="hidden" name="cabang" value="'.$cabangId.'">
      <div class="input-group input-group-sm" style="width: 130px;">
        <span class="input-group-text bg-light"><i class="bi bi-calendar"></i></span>
        <select class="form-select form-select-sm" name="bulan" onchange="this.form.submit()">';
for ($m=1;$m<=12;$m++) {
    $body .= '<option value="'.$m.'"'.($m===$month?' selected':'').'>'.$monthNames[$m].'</option>';
}
$body .= '</select>
      </div>
      <input type="number" class="form-control form-control-sm" style="width:80px" name="tahun" value="'.$year.'" onchange="this.form.submit()">
    </form>
  </div>
  <div class="d-flex flex-wrap gap-2">
    '.$branchTabs.'
  </div>
</div>

<!-- Grid Monitoring Tiap Cabang -->
<div class="mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-diagram-3-fill text-primary me-2"></i>Status Maintenance Tiap Cabang</h5>
    <a class="btn btn-sm btn-outline-primary" href="'.e(module_url('cabang_admin.php')).'"><i class="bi bi-gear-fill me-1"></i> Kelola Cabang</a>
  </div>
  <div class="row g-3">
    '.$branchCardsHtml.'
  </div>
</div>

<!-- Detail Statistik Cabang Terpilih -->
<div class="d-flex justify-content-between align-items-center mb-3 mt-4 pt-2">
  <h5 class="fw-bold text-dark mb-0"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Statistik: <span class="text-primary">'.e($branchTitle).'</span></h5>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card card-hover p-3 h-100">
      <div class="d-flex align-items-center gap-3">
        <div class="stat-icon-wrapper bg-primary bg-opacity-10 text-primary">
          <i class="bi bi-pc-display"></i>
        </div>
        <div>
          <div class="small-muted">TOTAL KOMPUTER</div>
          <div class="stat text-dark">'.$total.'</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card card-hover p-3 h-100">
      <div class="d-flex align-items-center gap-3">
        <div class="stat-icon-wrapper bg-success bg-opacity-10 text-success">
          <i class="bi bi-check2-circle"></i>
        </div>
        <div>
          <div class="small-muted">SUDAH SELESAI</div>
          <div class="stat text-success">'.$done.' <small class="fs-6 fw-normal text-muted">('.$percent.'%)</small></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card card-hover p-3 h-100">
      <div class="d-flex align-items-center gap-3">
        <div class="stat-icon-wrapper bg-warning bg-opacity-10 text-warning">
          <i class="bi bi-hourglass-split"></i>
        </div>
        <div>
          <div class="small-muted">BELUM SELESAI</div>
          <div class="stat text-warning">'.$pending.'</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card card-hover p-3 h-100">
      <div class="d-flex align-items-center gap-3">
        <div class="stat-icon-wrapper bg-danger bg-opacity-10 text-danger">
          <i class="bi bi-exclamation-triangle"></i>
        </div>
        <div>
          <div class="small-muted">ADA TEMUAN</div>
          <div class="stat text-danger">'.$findings.'</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Tabel Belum Maintenance Cabang Ini -->
<div class="card p-4 mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history text-warning me-2"></i>Daftar Komputer Belum Maintenance ('.$pending.') — '.e($selectedCabangName).'</h5>
    <a class="btn btn-sm btn-outline-primary" target="_blank" href="'.e(module_url('print_qr.php', ['cabang'=>$cabangId])).'"><i class="bi bi-qr-code me-1"></i> Cetak QR Cabang Ini</a>
  </div>
  <div class="table-responsive rounded-3 border">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Kode Inventaris</th><th>Perangkat</th><th>Pengguna / Pemilik</th><th>Lokasi & Divisi</th><th>Status</th></tr></thead>
      <tbody>'.$pendingHtml.'</tbody>
    </table>
  </div>
</div>

<!-- Tabel Scan Terbaru Cabang Ini -->
<div class="card p-4">
  <h5 class="fw-bold text-dark mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i>Riwayat Scan Terbaru — '.e($selectedCabangName).'</h5>
  <div class="table-responsive rounded-3 border">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Waktu Scan</th><th>Kode</th><th>Perangkat</th><th>Pengguna</th><th>Cabang</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
      <tbody>'.$recentHtml.'</tbody>
    </table>
  </div>
</div>';

render_page('Dashboard QR Maintenance', $body, $head);
