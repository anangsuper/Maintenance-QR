<?php
require __DIR__ . '/bootstrap.php';
require_login();

$month = max(1, min(12, (int)($_GET['bulan'] ?? date('n'))));
$currentYear = (int)date('Y');
$yearParam = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
$year = ($yearParam >= 2020 && $yearParam <= 2035) ? $yearParam : $currentYear;
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));

$yearOpts = '';
$startYear = max(2023, $currentYear - 2);
$endYear = $currentYear + 2;
for ($y = $startYear; $y <= $endYear; $y++) {
    $yearOpts .= '<option value="'.$y.'"'.($y === $year ? ' selected' : '').'>'.$y.'</option>';
}
if ($year < $startYear || $year > $endYear) {
    $yearOpts .= '<option value="'.$year.'" selected>'.$year.'</option>';
}

$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$monthName = $monthNames[$month] ?? date('F');

$cabangs = get_cabang_list();
$rows = get_history_rows($month, $year, $cabangId);

$branchPills = '';
$allActive = ($cabangId === 0);
$branchPills .= '<a class="branch-nav-pill '.($allActive ? 'active' : '').'" href="'.e(module_url('history.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>0])).'">
  <i class="bi bi-buildings"></i> Semua Cabang
</a>';

foreach ($cabangs as $c) {
    $cid = (int)($c['id'] ?? 0);
    $cn = $c['nama_cabang'] ?? $c['nama'] ?? ('Cabang #' . $cid);
    $isActive = ($cid === $cabangId);
    $branchPills .= '<a class="branch-nav-pill '.($isActive ? 'active' : '').'" href="'.e(module_url('history.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cid])).'">
      <i class="bi bi-geo-alt'.($isActive ? '-fill' : '').'"></i> '.e($cn).'
    </a>';
}

$opts = '';
foreach ($cabangs as $c) {
    $cId = (int)($c['id'] ?? 0);
    $cNama = $c['nama'] ?? $c['nama_cabang'] ?? 'Cabang #' . $cId;
    $opts .= '<option value="'.$cId.'"'.($cId===$cabangId?' selected':'').'>'.e($cNama).'</option>';
}

$trs = '';
$num = 0;
foreach ($rows as $r) {
    $num++;
    $tech = $r['teknisi_nama'] ?? $r['technician_name'] ?? '-';
    $statusChip = (($r['status'] ?? '') === 'Temuan') 
        ? '<span class="badge-chip chip-danger"><i class="bi bi-exclamation-triangle-fill"></i> Ada Temuan</span>' 
        : '<span class="badge-chip chip-success"><i class="bi bi-check-circle-fill"></i> Selesai</span>';

    $actionBtn = !empty($r['id'])
        ? '<a class="btn btn-sm btn-outline-primary" href="'.e(module_url('maintenance_detail.php', ['id'=>(int)$r['id']])).'"><i class="bi bi-file-earmark-medical me-1"></i> Detail</a>'
        : '-';

    $trs .= '<tr>
      <td class="text-center text-muted">'.$num.'</td>
      <td class="text-secondary small"><i class="bi bi-calendar-check me-1"></i>'.e(format_id_date($r['maintenance_date'])).' <span class="badge bg-light text-dark border">'.e(substr($r['maintenance_time'],0,5)).'</span></td>
      <td><span class="fw-bold text-primary">'.e($r['kode_inventaris'] ?? '-').'</span></td>
      <td class="fw-semibold text-dark">'.e(trim(($r['merk'] ?? '').' '.($r['model'] ?? ''))).'</td>
      <td><span class="d-inline-flex align-items-center gap-1"><i class="bi bi-person-circle text-secondary"></i> '.e($r['karyawan_nama'] ?? '-').'</span></td>
      <td><span class="badge-chip chip-secondary">'.e($r['cabang_nama'] ?? '-').'</span></td>
      <td class="small fw-semibold text-dark">'.e($tech).'</td>
      <td class="text-center">'.$statusChip.'</td>
      <td class="text-end">'.$actionBtn.'</td>
    </tr>';
}
if (!$trs) {
    $trs = '<tr><td colspan="9" class="text-center py-5 text-secondary"><i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>Belum ada riwayat maintenance yang tercatat untuk periode ini.</td></tr>';
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
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
  <div>
    <h2 class="fw-bold mb-1 text-dark"><i class="bi bi-clock-history text-primary me-2"></i>Riwayat Maintenance</h2>
    <div class="text-secondary">Arsip lengkap pencatatan pemeliharaan komputer bulanan periode <strong>'.$monthName.' '.$year.'</strong>.</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-primary fw-semibold" target="_blank" href="'.e(module_url('print_report.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'"><i class="bi bi-printer-fill me-1"></i> Cetak Laporan</a>
    <a class="btn btn-outline-success fw-semibold" href="'.e(module_url('export_csv.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV</a>
  </div>
</div>

<!-- Interactive Branch Switcher Navigation Bar -->
<div class="branch-nav-bar custom-scrollbar">
  '.$branchPills.'
</div>

<div class="card p-3 mb-4 border-0 shadow-sm">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-6 col-md-3">
      <label class="form-label small fw-bold text-secondary">Bulan</label>
      <select class="form-select" name="bulan">';
for($m=1;$m<=12;$m++) {
    $body .= '<option value="'.$m.'"'.($m===$month?' selected':'').'>'.$monthNames[$m].'</option>';
}
$body .= '</select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold text-secondary">Tahun</label>
      <select class="form-select font-monospace fw-semibold" name="tahun">'.$yearOpts.'</select>
    </div>
    <div class="col-md-5">
      <label class="form-label small fw-bold text-secondary">Cabang / Lokasi</label>
      <select class="form-select" name="cabang"><option value="0">Semua Cabang</option>'.$opts.'</select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100 fw-bold py-2"><i class="bi bi-filter me-1"></i> Tampilkan</button>
    </div>
  </form>
</div>

<div class="card p-4 border-0 shadow-sm">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-list-check text-primary me-2"></i>Daftar Scan Selesai ('.count($rows).' Data)</h5>
  </div>
  <div class="table-responsive rounded-3 border">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th style="width: 40px;" class="text-center">No</th>
          <th>Waktu Maintenance</th>
          <th>Kode Inventaris</th>
          <th>Perangkat</th>
          <th>Pengguna / Pemilik</th>
          <th>Cabang</th>
          <th>Teknisi</th>
          <th class="text-center">Status</th>
        </tr>
      </thead>
      <tbody>'.$trs.'</tbody>
    </table>
  </div>
</div>';

render_page('Riwayat Maintenance', $body, $headStyle);
