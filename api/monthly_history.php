<?php
require __DIR__ . '/bootstrap.php';
require_login();

$currentYear = (int)date('Y');
$yearParam = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
$year = ($yearParam >= 2020 && $yearParam <= 2035) ? $yearParam : $currentYear;
$cabangId = (int)($_GET['cabang'] ?? 0);

$yearOpts = '';
$startYear = max(2023, $currentYear - 2);
$endYear = $currentYear + 2;
for ($y = $startYear; $y <= $endYear; $y++) {
    $yearOpts .= '<option value="'.$y.'"'.($y === $year ? ' selected' : '').'>'.$y.'</option>';
}
if ($year < $startYear || $year > $endYear) {
    $yearOpts .= '<option value="'.$year.'" selected>'.$year.'</option>';
}

if (is_google_cloud_mode()) {
    $client = google_sheets_v4_client();
    if ($client) {
        $client->preloadSheets(['Assets', 'Cabang', 'Divisi', 'Karyawan', 'Kategori_Aset', 'Asset_QR_Tokens', 'Maintenance_Scan']);
    }
}

$cabangs = get_cabang_list();
$overview = get_monthly_overview($year, $cabangId);

$cabangOpts = '<option value="0">Semua Cabang</option>';
foreach ($cabangs as $c) {
    $cid = (int)($c['id'] ?? 0);
    $cn = $c['nama_cabang'] ?? $c['nama'] ?? ('Cabang #' . $cid);
    $cabangOpts .= '<option value="'.$cid.'"'.($cid === $cabangId ? ' selected' : '').'>'.e($cn).'</option>';
}

$branchPills = '';
$allActive = ($cabangId === 0);
$branchPills .= '<a class="branch-nav-pill '.($allActive ? 'active' : '').'" href="'.e(module_url('monthly_history.php', ['tahun'=>$year,'cabang'=>0])).'">
  <i class="bi bi-buildings"></i> Semua Cabang
</a>';

foreach ($cabangs as $c) {
    $cid = (int)($c['id'] ?? 0);
    $cn = $c['nama_cabang'] ?? $c['nama'] ?? ('Cabang #' . $cid);
    $isActive = ($cid === $cabangId);
    $branchPills .= '<a class="branch-nav-pill '.($isActive ? 'active' : '').'" href="'.e(module_url('monthly_history.php', ['tahun'=>$year,'cabang'=>$cid])).'">
      <i class="bi bi-geo-alt'.($isActive ? '-fill' : '').'"></i> '.e($cn).'
    </a>';
}

$monthCardsHtml = '';
foreach ($overview as $mNum => $m) {
    $isCurrent = $m['is_current'];
    $isFuture = $m['is_future'];
    $percent = $m['percent'];
    $done = $m['done'];
    $total = $m['total'];

    $iconClass = $isCurrent
        ? '<span class="text-primary fs-3"><i class="bi bi-play-circle-fill"></i></span>'
        : ($done === $total && $total > 0
            ? '<span class="text-success fs-3"><i class="bi bi-check-circle-fill"></i></span>'
            : ($isFuture
                ? '<span class="text-muted fs-3"><i class="bi bi-clock"></i></span>'
                : '<span class="text-warning fs-3"><i class="bi bi-exclamation-circle-fill"></i></span>'));

    $cardBorder = $isCurrent ? 'border-primary border-2 shadow' : 'border-0 shadow-sm';

    $progressBar = '
    <div class="progress" style="height: 8px;">
      <div class="progress-bar '.($percent === 100 ? 'bg-success' : ($percent >= 50 ? 'bg-primary' : 'bg-warning')).'" style="width: '.$percent.'%"></div>
    </div>';

    $monthCardsHtml .= '
    <div class="col-md-6 col-lg-4">
      <div class="card p-3 p-md-4 h-100 '.$cardBorder.'">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="d-flex align-items-center gap-2">
            '.$iconClass.'
            <div>
              <h5 class="fw-bold text-dark mb-0">'.e($m['month_name']).'</h5>
            </div>
          </div>
          '.($isCurrent ? '<span class="badge bg-primary px-2 py-1">Bulan Berjalan</span>' : '').'
        </div>

        <div class="my-3">
          <div class="d-flex justify-content-between small text-secondary mb-1">
            <span>Progress Pemeliharaan</span>
            <span class="fw-bold text-dark">'.$done.' / '.$total.' Unit ('.$percent.'%)</span>
          </div>
          '.$progressBar.'
        </div>

        <div class="row g-2 small text-center mb-3">
          <div class="col-4 bg-light p-2 rounded">
            <div class="text-muted" style="font-size:0.75rem;">TOTAL</div>
            <div class="fw-bold text-dark">'.$total.'</div>
          </div>
          <div class="col-4 bg-success bg-opacity-10 p-2 rounded">
            <div class="text-success" style="font-size:0.75rem;">SUDAH</div>
            <div class="fw-bold text-success">'.$done.'</div>
          </div>
          <div class="col-4 bg-danger bg-opacity-10 p-2 rounded">
            <div class="text-danger" style="font-size:0.75rem;">BELUM</div>
            <div class="fw-bold text-danger">'.$m['pending'].'</div>
          </div>
        </div>

        <div class="mt-auto">
          <a class="btn btn-outline-primary btn-sm w-100 fw-semibold" href="'.e(module_url('audit.php', ['bulan'=>$mNum,'tahun'=>$year,'cabang'=>$cabangId])).'">
            <i class="bi bi-list-check me-1"></i> Buka Rincian Perangkat
          </a>
        </div>
      </div>
    </div>';
}

$headStyle = '
<style>
.branch-nav-bar {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 6px;
  margin-bottom: 18px;
  -webkit-overflow-scrolling: touch;
}
.branch-nav-pill {
  white-space: nowrap;
  padding: 7px 14px;
  font-size: 0.82rem;
  font-weight: 500;
  border-radius: 6px;
  background: #FFFFFF;
  border: 1px solid #CBD5E1;
  color: #475569;
  text-decoration: none;
  transition: all 0.15s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}
.branch-nav-pill:hover {
  background: #F8FAFC;
  color: #0F172A;
  border-color: #94A3B8;
}
.branch-nav-pill.active {
  background: var(--blue-corporate, #003B73);
  border-color: var(--blue-corporate, #003B73);
  color: #FFFFFF;
  font-weight: 600;
}
</style>';

$body = '
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
  <div>
    <div class="tech-label mb-1">MONITORING</div>
    <h1 class="h3 mb-1">Riwayat Maintenance Bulanan</h1>
    <div class="text-secondary small">Ringkasan progress pemeliharaan komputer 12 bulan tahun <strong>'.$year.'</strong>. Target unit terkunci otomatis per cut-off akhir bulan sesuai inventaris aktif pada periode bersangkutan.</div>
  </div>
  <form method="get" class="d-flex flex-wrap gap-2 align-items-center">
    <select class="form-select form-select-sm" name="cabang" style="min-width: 160px;" onchange="this.form.submit()">'.$cabangOpts.'</select>
    <select class="form-select form-select-sm font-monospace fw-bold text-dark" name="tahun" style="width: 95px;" title="Pilih Tahun" onchange="this.form.submit()">'.$yearOpts.'</select>
  </form>
</div>

<!-- Interactive Branch Switcher Navigation Bar -->
<div class="branch-nav-bar custom-scrollbar">
  '.$branchPills.'
</div>

<div class="row g-3">
  '.$monthCardsHtml.'
</div>';

render_page('Riwayat Bulanan · ' . $year, $body, $headStyle);
