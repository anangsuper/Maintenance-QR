<?php
require __DIR__ . '/bootstrap.php';
require_it();

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

$isRefresh = !empty($_GET['refresh']);

if (is_google_cloud_mode()) {
    $client = google_sheets_v4_client();
    if ($client) {
        if ($isRefresh) {
            $client->clearCache('Maintenance_Scan');
            $client->clearCache('Maintenance_Checklists');
            $client->clearCache('Maintenance_Findings');
            $client->clearCache('Assets');
            $client->clearCache();
        }
        $client->preloadSheets(['Assets', 'Cabang', 'Divisi', 'Karyawan', 'Kategori_Aset', 'Asset_QR_Tokens', 'Maintenance_Scan'], $isRefresh);
    }
}

$cabangs = get_cabang_list();
$overview = get_monthly_overview($year, $cabangId, $isRefresh);

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
        ? '<span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 36px; height: 36px; background-color: #D9F1EF; color: #0E5C58;"><i class="bi bi-play-circle-fill fs-5"></i></span>'
        : ($done === $total && $total > 0
            ? '<span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 36px; height: 36px; background-color: #DCFCE7; color: #16A34A;"><i class="bi bi-check-circle-fill fs-5"></i></span>'
            : ($isFuture
                ? '<span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 36px; height: 36px; background-color: #F1F5F9; color: #94A3B8;"><i class="bi bi-clock fs-5"></i></span>'
                : '<span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 36px; height: 36px; background-color: #FEF3C7; color: #D97706;"><i class="bi bi-exclamation-circle-fill fs-5"></i></span>'));

    $cardBorder = $isCurrent ? 'border-2 shadow-sm' : 'border shadow-2xs';
    $cardBorderInline = $isCurrent ? 'border-color: #0E5C58 !important;' : 'border-color: #D1D5D8 !important;';

    $barColor = ($percent >= 100) ? '#16A34A' : (($percent >= 50) ? '#0E5C58' : '#D97706');
    $progressBar = '
    <div class="rounded-pill overflow-hidden" style="height: 7px; background-color: #E2E8F0;">
      <div class="h-100 rounded-pill transition-all" style="width: '.$percent.'%; background-color: '.$barColor.';"></div>
    </div>';

    $monthCardsHtml .= '
    <div class="col-md-6 col-lg-4">
      <div class="card p-3 p-md-3.5 h-100 '.$cardBorder.' rounded-[12px] bg-white" style="'.$cardBorderInline.'">
        <div class="d-flex align-items-center justify-content-between mb-2.5">
          <div class="d-flex align-items-center gap-2.5">
            '.$iconClass.'
            <div>
              <h5 class="fw-bold mb-0 text-[#1B2A3A]" style="font-size: 16px;">'.e($m['month_name']).'</h5>
            </div>
          </div>
          '.($isCurrent ? '<span class="badge border rounded-pill px-2.5 py-1" style="font-size: 0.72rem; background-color: #D9F1EF !important; color: #0E5C58 !important; border-color: #A7E2DC !important; font-weight: 700;">Bulan Berjalan</span>' : '').'
        </div>

        <div class="my-2.5">
          <div class="d-flex justify-content-between small text-[#53595F] mb-1.5" style="font-size: 0.78rem;">
            <span>Progress Pemeliharaan</span>
            <span class="fw-bold text-[#1B2A3A]">'.$done.' / '.$total.' Unit ('.$percent.'%)</span>
          </div>
          '.$progressBar.'
        </div>

        <div class="row g-2 small text-center mb-3">
          <div class="col-4">
            <div class="p-2 rounded-[8px] border" style="background-color: #F8FAFC; border-color: #E2E8F0 !important;">
              <div class="text-[#53595F] fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.05em;">TOTAL</div>
              <div class="fw-bold text-[#1B2A3A] fs-6">'.$total.'</div>
            </div>
          </div>
          <div class="col-4">
            <div class="p-2 rounded-[8px] border" style="background-color: #F0FDF4; border-color: #BBF7D0 !important;">
              <div class="text-[#15803D] fw-bold" style="font-size: 0.68rem; letter-spacing: 0.05em;">SUDAH</div>
              <div class="fw-bold text-[#16A34A] fs-6">'.$done.'</div>
            </div>
          </div>
          <div class="col-4">
            <div class="p-2 rounded-[8px] border" style="background-color: #FEF2F2; border-color: #FECACA !important;">
              <div class="text-[#B91C1C] fw-bold" style="font-size: 0.68rem; letter-spacing: 0.05em;">BELUM</div>
              <div class="fw-bold text-[#DC2626] fs-6">'.$m['pending'].'</div>
            </div>
          </div>
        </div>

        <div class="mt-auto">
          <a class="btn btn-outline-teal btn-sm w-100 fw-bold rounded-[8px] py-1.5 shadow-2xs d-inline-flex align-items-center justify-content-center gap-1.5" href="'.e(module_url('audit.php', ['bulan'=>$mNum,'tahun'=>$year,'cabang'=>$cabangId])).'" style="font-size: 0.82rem;">
            <i class="bi bi-list-check"></i> Buka Rincian Perangkat
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
  background: #0E5C58 !important;
  border-color: #0E5C58 !important;
  color: #FFFFFF !important;
  font-weight: 600;
}
.btn-outline-teal {
  background: #FFFFFF;
  color: #0E5C58;
  border: 1px solid #0E5C58;
  transition: all 0.15s ease;
}
.btn-outline-teal:hover {
  background: #0E5C58;
  color: #FFFFFF;
  border-color: #0E5C58;
}
.badge.bg-primary {
  background-color: #0E5C58 !important;
}
.progress-bar.bg-primary {
  background-color: #0E5C58 !important;
}
.text-primary {
  color: #0E5C58 !important;
}
.border-primary {
  border-color: #0E5C58 !important;
}
</style>';

$refreshAlert = $isRefresh ? '
<div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 py-2 px-3 mb-3 shadow-sm border-0" role="alert">
  <i class="bi bi-check-circle-fill text-success fs-5"></i>
  <div>Data riwayat maintenance berhasil disegarkan langsung dari Google Spreadsheet!</div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>' : '';

$body = '
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
  <div>
    <div class="tech-label mb-1">MONITORING</div>
    <h1 class="h3 mb-1">Riwayat Maintenance Bulanan</h1>
    <div class="text-secondary small">Ringkasan progress pemeliharaan komputer &amp; laptop 12 bulan tahun <strong>'.$year.'</strong>. Target unit terkunci otomatis per cut-off akhir bulan sesuai inventaris aktif pada periode bersangkutan.</div>
  </div>
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <a href="'.e(module_url('monthly_history.php', ['tahun' => $year, 'cabang' => $cabangId, 'refresh' => 1])).'" 
       class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 shadow-sm" 
       title="Segarkan data langsung dari Google Spreadsheet">
      <i class="bi bi-arrow-clockwise"></i>
      <span>Segarkan Data</span>
    </a>
    <form method="get" class="d-flex flex-wrap gap-2 align-items-center m-0">
      <select class="form-select form-select-sm" name="cabang" style="min-width: 160px;" onchange="this.form.submit()">'.$cabangOpts.'</select>
      <select class="form-select form-select-sm font-monospace fw-bold text-dark" name="tahun" style="width: 95px;" title="Pilih Tahun" onchange="this.form.submit()">'.$yearOpts.'</select>
    </form>
  </div>
</div>
'.$refreshAlert.'

<!-- Interactive Branch Switcher Navigation Bar -->
<div class="branch-nav-bar custom-scrollbar">
  '.$branchPills.'
</div>

<div class="row g-3">
  '.$monthCardsHtml.'
</div>';

render_page('Riwayat Bulanan · ' . $year, $body, $headStyle);
