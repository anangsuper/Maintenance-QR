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
$branchTabs = '<a class="btn btn-sm '.($cabangId === 0 ? 'btn-primary fw-bold shadow-sm' : 'btn-outline-secondary').' px-3" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>0])).'"><i class="bi bi-grid-fill me-1"></i> Semua Cabang</a>';
foreach ($cabangs as $c) {
    $cId = (int)($c['id'] ?? 0);
    $cNama = $c['nama'] ?? $c['nama_cabang'] ?? 'Cabang #' . $cId;
    $isActive = ($cId === $cabangId);
    $branchTabs .= '<a class="btn btn-sm '.($isActive ? 'btn-primary fw-bold shadow-sm' : 'btn-outline-secondary').' px-3" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cId])).'"><i class="bi bi-building me-1"></i> '.e($cNama).'</a>';
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

    $barColor = $bPercent === 100 ? 'bg-success' : ($bPercent >= 50 ? 'bg-primary' : 'bg-warning text-dark');

    $branchCardsHtml .= '
    <div class="col-md-6 col-lg-4">
      <div class="card p-3 border-0 shadow-sm h-100 '.($isCurrent ? 'border border-2 border-primary' : '').'">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-building text-primary me-2"></i>'.e($bName).'</h5>
            <small class="text-secondary">Target: <strong>'.$bTotal.' Unit Komputer</strong></small>
          </div>
          <span class="badge text-bg-'.($bPercent === 100 ? 'success' : 'primary').' fs-6">'.$bPercent.'%</span>
        </div>

        <div class="progress my-2" style="height: 10px;">
          <div class="progress-bar '.$barColor.'" style="width: '.$bPercent.'%"></div>
        </div>

        <div class="d-flex justify-content-between text-center small my-2 py-1 bg-light rounded">
          <div class="px-2"><span class="text-secondary">Selesai:</span> <strong class="text-success">'.$bDone.'</strong></div>
          <div class="px-2"><span class="text-secondary">Belum:</span> <strong class="text-warning">'.$bPending.'</strong></div>
          <div class="px-2"><span class="text-secondary">Temuan:</span> <strong class="text-danger">'.$bFindings.'</strong></div>
        </div>

        <div class="d-flex gap-1 mt-auto pt-2 border-top">
          <a class="btn btn-sm btn-outline-primary flex-fill" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$bId])).'"><i class="bi bi-eye"></i> Buka Cabang</a>
          <a class="btn btn-sm btn-outline-secondary" target="_blank" href="'.e(module_url('print_report.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$bId])).'" title="Cetak Rekap Cabang Ini"><i class="bi bi-printer"></i> Rekap</a>
          <a class="btn btn-sm btn-outline-secondary" target="_blank" href="'.e(module_url('print_qr.php', ['cabang'=>$bId])).'" title="Cetak Semua QR Cabang Ini"><i class="bi bi-qr-code"></i> QR</a>
        </div>
      </div>
    </div>';
}

// 3. Tabel Belum Maintenance
$pendingHtml = '';
foreach ($pendingRows as $r) {
    $pendingHtml .= '<tr>
      <td><a href="'.e(module_url('asset_edit.php', ['id' => (int)$r['id']])).'" class="fw-bold text-decoration-none" title="Klik untuk Edit Data">'.e($r['kode_inventaris'] ?? '-').' <i class="bi bi-pencil-square small text-secondary"></i></a></td>
      <td>'.e(asset_title($r)).'</td>
      <td>'.e($r['karyawan_nama'] ?? '-').'</td>
      <td><span class="badge text-bg-light border">'.e($r['cabang_nama'] ?? '-').'</span> <small class="text-muted">('.e($r['divisi_nama'] ?? '-').')</small></td>
      <td><span class="badge text-bg-warning">Belum</span></td>
    </tr>';
}
if ($pendingHtml === '') {
    $pendingHtml = '<tr><td colspan="5" class="text-center text-success py-4 fw-semibold"><i class="bi bi-check-circle-fill me-1"></i> Semua komputer di cabang ini telah selesai di-maintenance pada periode ini.</td></tr>';
}

// 4. Tabel Scan Terbaru
$recentHtml = '';
foreach ($recentRows as $r) {
    $recentHtml .= '<tr>
      <td>'.e(format_id_date($r['maintenance_date'])).' <small class="text-muted">'.e(substr($r['maintenance_time'],0,5)).'</small></td>
      <td><strong>'.e($r['kode_inventaris'] ?? '-').'</strong></td>
      <td>'.e(trim(($r['merk'] ?? '').' '.($r['model'] ?? ''))).'</td>
      <td>'.e($r['karyawan_nama'] ?? '-').'</td>
      <td><span class="badge text-bg-light border">'.e($r['cabang_nama'] ?? '-').'</span></td>
      <td>'.(($r['status'] ?? '') === 'Temuan' ? '<span class="badge text-bg-danger">Temuan</span>' : '<span class="badge text-bg-success">Selesai</span>').'</td>
    </tr>';
}
if ($recentHtml === '') {
    $recentHtml = '<tr><td colspan="6" class="text-center text-secondary py-4">Belum ada scan maintenance pada periode ini.</td></tr>';
}

$modeBadge = is_google_cloud_mode() ? '<span class="badge text-bg-info mb-2"><i class="bi bi-google me-1"></i> Google Cloud Sheets API v4</span>' : '<span class="badge text-bg-secondary mb-2"><i class="bi bi-database me-1"></i> MySQL Database</span>';

$branchTitle = ($cabangId > 0) ? 'Cabang: ' . e($selectedCabangName) : 'Semua Cabang';

$body = '
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
  <div>
    '.$modeBadge.'
    <h2 class="fw-bold mb-0 text-dark"><i class="bi bi-speedometer2 text-primary me-2"></i>Dashboard Maintenance Bulanan</h2>
    <div class="text-secondary">Monitoring progres pemeliharaan komputer per cabang untuk periode <strong>'.$monthName.' '.$year.'</strong>.</div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-warning text-dark fw-bold" href="'.e(module_url('asset_add.php')).'"><i class="bi bi-plus-lg"></i> Tambah Komputer</a>
    <a class="btn btn-primary fw-semibold" target="_blank" href="'.e(module_url('print_report.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'"><i class="bi bi-printer-fill me-1"></i> Cetak Laporan ('.$selectedCabangName.')</a>
    <a class="btn btn-outline-success" href="'.e(module_url('export_csv.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV</a>
  </div>
</div>

<!-- Navigasi Cepat Tab Cabang -->
<div class="card p-3 mb-4 border-0 shadow-sm">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <span class="fw-bold text-dark"><i class="bi bi-funnel-fill text-primary me-1"></i> Pilih Cabang:</span>
    <form method="get" class="d-flex gap-2 align-items-center">
      <input type="hidden" name="cabang" value="'.$cabangId.'">
      <select class="form-select form-select-sm" name="bulan" onchange="this.form.submit()">';
for ($m=1;$m<=12;$m++) {
    $body .= '<option value="'.$m.'"'.($m===$month?' selected':'').'>'.$monthNames[$m].'</option>';
}
$body .= '</select>
      <input type="number" class="form-control form-control-sm" style="width:85px" name="tahun" value="'.$year.'" onchange="this.form.submit()">
    </form>
  </div>
  <div class="d-flex flex-wrap gap-2">
    '.$branchTabs.'
  </div>
</div>

<!-- Grid Monitoring Tiap Cabang -->
<div class="mb-4">
  <h5 class="fw-bold text-dark mb-3"><i class="bi bi-diagram-3 text-primary me-2"></i>Status Maintenance Tiap Cabang ('.$monthName.' '.$year.')</h5>
  <div class="row g-3">
    '.$branchCardsHtml.'
  </div>
</div>

<!-- Detail Statistik Cabang Terpilih -->
<div class="d-flex justify-content-between align-items-center mb-3 mt-4">
  <h5 class="fw-bold text-dark mb-0"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Detail Data: <span class="text-primary">'.e($branchTitle).'</span></h5>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="card p-3 border-0 shadow-sm"><div class="small-muted fw-bold">TOTAL KOMPUTER</div><div class="stat text-dark">'.$total.'</div></div></div>
  <div class="col-6 col-lg-3"><div class="card p-3 border-0 shadow-sm"><div class="small-muted fw-bold">SUDAH SELESAI</div><div class="stat text-success">'.$done.' <small class="fs-6 fw-normal text-muted">('.$percent.'%)</small></div></div></div>
  <div class="col-6 col-lg-3"><div class="card p-3 border-0 shadow-sm"><div class="small-muted fw-bold">BELUM SELESAI</div><div class="stat text-warning">'.$pending.'</div></div></div>
  <div class="col-6 col-lg-3"><div class="card p-3 border-0 shadow-sm"><div class="small-muted fw-bold">ADA TEMUAN</div><div class="stat text-danger">'.$findings.'</div></div></div>
</div>

<!-- Tabel Belum Maintenance Cabang Ini -->
<div class="card p-4 border-0 shadow-sm mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history text-warning me-2"></i>Komputer Belum Maintenance ('.$pending.') — '.e($selectedCabangName).'</h5>
    <a class="btn btn-sm btn-outline-primary" target="_blank" href="'.e(module_url('print_qr.php', ['cabang'=>$cabangId])).'"><i class="bi bi-qr-code me-1"></i> Cetak QR Cabang Ini</a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>Kode</th><th>Perangkat</th><th>Pengguna / Pemilik</th><th>Lokasi Cabang & Divisi</th><th>Status</th></tr></thead>
      <tbody>'.$pendingHtml.'</tbody>
    </table>
  </div>
</div>

<!-- Tabel Scan Terbaru Cabang Ini -->
<div class="card p-4 border-0 shadow-sm">
  <h5 class="fw-bold text-dark mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i>Riwayat Scan Terbaru — '.e($selectedCabangName).'</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>Waktu Scan</th><th>Kode</th><th>Perangkat</th><th>Pengguna</th><th>Cabang</th><th>Status</th></tr></thead>
      <tbody>'.$recentHtml.'</tbody>
    </table>
  </div>
</div>';

render_page('Dashboard QR Maintenance', $body);
