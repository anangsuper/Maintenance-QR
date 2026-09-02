<?php
require __DIR__ . '/bootstrap.php';
require_login();

$year = max(2020, min(2100, (int)($_GET['tahun'] ?? date('Y'))));
$cabangId = (int)($_GET['cabang'] ?? 0);

$cabangs = get_cabang_list();
$overview = get_monthly_overview($year, $cabangId);

$cabangOpts = '<option value="0">Semua Cabang</option>';
foreach ($cabangs as $c) {
    $cid = (int)($c['id'] ?? 0);
    $cn = $c['nama_cabang'] ?? $c['nama'] ?? ('Cabang #' . $cid);
    $cabangOpts .= '<option value="'.$cid.'"'.($cid === $cabangId ? ' selected' : '').'>'.e($cn).'</option>';
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
              <div class="small text-muted">Tahun '.$year.'</div>
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

$body = '
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1 text-dark"><i class="bi bi-calendar-check text-primary me-2"></i>Riwayat Maintenance Bulanan</h2>
    <div class="text-secondary">Ringkasan progress pemeliharaan komputer tiap bulan sepanjang tahun <strong>'.$year.'</strong>.</div>
  </div>
  <form method="get" class="d-flex gap-2 align-items-center">
    <select class="form-select form-select-sm" name="cabang" onchange="this.form.submit()">'.$cabangOpts.'</select>
    <div class="input-group input-group-sm" style="width: 140px;">
      <span class="input-group-text bg-light">Tahun</span>
      <input type="number" class="form-control form-control-sm" name="tahun" value="'.$year.'" onchange="this.form.submit()">
    </div>
  </form>
</div>

<div class="row g-3">
  '.$monthCardsHtml.'
</div>';

render_page('Riwayat Bulanan · ' . $year, $body);
