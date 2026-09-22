<?php
require __DIR__ . '/bootstrap.php';
require_login();

$month = max(1, min(12, (int)($_GET['bulan'] ?? date('n'))));
$currentYear = (int)date('Y');
$yearParam = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
$year = ($yearParam >= 2020 && $yearParam <= 2035) ? $yearParam : $currentYear;
$cabangId = (int)($_GET['cabang'] ?? 0);
$divisiId = (int)($_GET['divisi'] ?? 0);
$kategoriId = (int)($_GET['kategori'] ?? 0);
$techFilter = trim((string)($_GET['teknisi'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));

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

if (is_google_cloud_mode()) {
    $client = google_sheets_v4_client();
    if ($client) {
        $client->preloadSheets(['Assets', 'Cabang', 'Divisi', 'Karyawan', 'Kategori_Aset', 'Asset_QR_Tokens', 'Maintenance_Scan', 'Maintenance_Checklists']);
    }
}

$cabangs = get_cabang_list();
$divisis = get_divisi_list();
$kategoris = get_kategori_list();
$branchSummaries = get_branch_maintenance_summary($month, $year);
$branchSummaryMap = [];
foreach ($branchSummaries as $bs) {
    $branchSummaryMap[(int)$bs['id']] = $bs;
}

// Jalankan audit query
$audit = get_audit_maintenance_data([
    'bulan' => $month,
    'tahun' => $year,
    'cabang' => $cabangId,
    'divisi' => $divisiId,
    'kategori' => $kategoriId,
    'teknisi' => $techFilter,
    'status' => $statusFilter,
    'force_refresh' => true
]);

$stats = $audit['stats'];
$rows = $audit['rows'];

// Branch Nav Pills HTML
$branchPills = '';
$allActive = ($cabangId === 0);
$branchPills .= '<a class="branch-nav-pill '.($allActive ? 'active' : '').'" href="'.e(module_url('audit.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>0,'divisi'=>$divisiId,'kategori'=>$kategoriId,'teknisi'=>$techFilter,'status'=>$statusFilter])).'">
  <i class="bi bi-buildings"></i> Semua Cabang
  <span class="badge bg-light text-dark rounded-pill">'.$stats['total'].'</span>
</a>';

foreach ($cabangs as $c) {
    $cid = (int)($c['id'] ?? 0);
    $cn = $c['nama_cabang'] ?? $c['nama'] ?? ('Cabang #' . $cid);
    $isActive = ($cid === $cabangId);
    $bs = $branchSummaryMap[$cid] ?? null;
    $countBadge = '';
    if ($bs) {
        $countBadge = '<span class="badge '.($isActive ? 'bg-white text-dark' : 'bg-light text-secondary').' rounded-pill">'.$bs['done'].'/'.$bs['total'].'</span>';
    }
    $branchPills .= '<a class="branch-nav-pill '.($isActive ? 'active' : '').'" href="'.e(module_url('audit.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cid,'divisi'=>$divisiId,'kategori'=>$kategoriId,'teknisi'=>$techFilter,'status'=>$statusFilter])).'">
      <i class="bi bi-geo-alt'.($isActive ? '-fill' : '').'"></i> '.e($cn).'
      '.$countBadge.'
    </a>';
}

// Filter options HTML
$cabangOpts = '<option value="0">Semua Cabang</option>';
foreach ($cabangs as $c) {
    $cid = (int)($c['id'] ?? 0);
    $cn = $c['nama_cabang'] ?? $c['nama'] ?? ('Cabang #' . $cid);
    $cabangOpts .= '<option value="'.$cid.'"'.($cid === $cabangId ? ' selected' : '').'>'.e($cn).'</option>';
}

$divisiOpts = '<option value="0">Semua Divisi</option>';
foreach ($divisis as $d) {
    $did = (int)($d['id'] ?? 0);
    $dn = $d['nama_divisi'] ?? $d['nama'] ?? ('Divisi #' . $did);
    $divisiOpts .= '<option value="'.$did.'"'.($did === $divisiId ? ' selected' : '').'>'.e($dn).'</option>';
}

$kategoriOpts = '<option value="0">Semua Jenis Perangkat</option>';
foreach ($kategoris as $k) {
    $kid = (int)($k['id'] ?? 0);
    $kn = $k['nama_kategori'] ?? $k['nama'] ?? ('Kategori #' . $kid);
    $kategoriOpts .= '<option value="'.$kid.'"'.($kid === $kategoriId ? ' selected' : '').'>'.e($kn).'</option>';
}

// Table Rows
$tableRows = '';
$no = 0;
foreach ($rows as $r) {
    $no++;
    $isDone = !empty($r['is_done']);
    $st = $r['status'];
    
    $badge = $isDone
        ? (($st === 'Temuan' || $st === 'Perlu Perbaikan')
            ? '<span class="badge-chip chip-danger"><i class="bi bi-exclamation-triangle-fill"></i> '.$st.'</span>'
            : ($st === 'Proses'
                ? '<span class="badge-chip chip-warning"><i class="bi bi-hourglass-split"></i> '.$st.'</span>'
                : '<span class="badge-chip chip-success"><i class="bi bi-check-circle-fill"></i> Selesai</span>'))
        : '<span class="badge-chip chip-danger"><i class="bi bi-x-circle-fill"></i> Belum Maintenance</span>';

    $actionBtn = ($isDone && !empty($r['log_id']))
        ? '<a class="btn btn-sm btn-primary fw-semibold" href="'.e(module_url('maintenance_detail.php', ['id' => $r['log_id']])).'"><i class="bi bi-file-earmark-medical me-1"></i> Detail</a>'
        : '<span class="text-muted small">-</span>';

    $bioBadge = !empty($r['biometric_verified'])
        ? '<div class="mt-1"><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-50 py-1" style="font-size: 0.68rem;" title="Terverifikasi Biometrik Wajah"><i class="bi bi-shield-fill-check me-1"></i>AI ('.e((int)($r['biometric_confidence'] ?? 98)).'%)</span></div>'
        : '';

    $tableRows .= '
    <tr class="'.(!$isDone ? 'table-danger bg-opacity-10' : '').'">
      <td class="text-center text-muted small">'.$no.'</td>
      <td class="small fw-semibold">'.e($r['maintenance_date']).'</td>
      <td><span class="fw-bold text-primary">'.e($r['kode_inventaris']).'</span></td>
      <td class="fw-semibold text-dark">'.e($r['perangkat']).' <span class="badge bg-light text-secondary border small">'.e($r['kategori_nama']).'</span></td>
      <td><span class="d-inline-flex align-items-center gap-1"><i class="bi bi-person-circle text-secondary"></i> '.e($r['karyawan_nama']).'</span></td>
      <td class="small">'.e($r['divisi_nama']).'</td>
      <td class="small"><span class="badge-chip chip-secondary">'.e($r['cabang_nama']).'</span></td>
      <td class="small">
        <span class="fw-bold text-dark">'.e($r['technician_name']).'</span>
        '.$bioBadge.'
      </td>
      <td>'.$badge.'</td>
      <td class="text-end text-nowrap">'.$actionBtn.'</td>
    </tr>';
}

if (!$tableRows) {
    $tableRows = '<tr><td colspan="10" class="text-center py-5 text-secondary"><i class="bi bi-search fs-1 d-block mb-2 opacity-50"></i>Tidak ada data perangkat yang cocok dengan kriteria filter.</td></tr>';
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
.branch-nav-pill.active .badge {
  background: rgba(255, 255, 255, 0.25) !important;
  color: #FFFFFF !important;
}
</style>';

$body = '
<!-- Header & Action Bar -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
  <div>
    <div class="tech-label mb-1">MONITORING & AUDIT</div>
    <h1 class="h3 mb-1">Reports & Audit Trail</h1>
    <p class="text-secondary small mb-0">Pemeriksaan kepatuhan pemeliharaan perangkat IT dan rekam jejak audit periode <strong>'.$monthName.' '.$year.'</strong>.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-light border d-inline-flex align-items-center gap-1" href="'.e(module_url('audit.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId,'divisi'=>$divisiId,'kategori'=>$kategoriId,'teknisi'=>$techFilter,'status'=>$statusFilter,'refresh'=>'1'])).'"><i class="bi bi-arrow-clockwise"></i> Segarkan</a>
    
    <div class="dropdown">
      <button class="btn btn-primary dropdown-toggle d-inline-flex align-items-center gap-1 fw-semibold" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-download"></i> Export & Cetak
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm border mt-1">
        <li><a class="dropdown-item py-2" target="_blank" href="'.e(module_url('print_report.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'"><i class="bi bi-printer me-2 text-primary"></i> Cetak Laporan Formal (PDF)</a></li>
        <li><a class="dropdown-item py-2" href="'.e(module_url('export_csv.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'"><i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i> Export Data ke Excel (CSV)</a></li>
      </ul>
    </div>
  </div>
</div>

<!-- Interactive Branch Switcher Navigation Bar -->
<div class="branch-nav-bar custom-scrollbar">
  '.$branchPills.'
</div>

<!-- Statistik Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card card-metric h-100" style="border-left-color: var(--blue-corporate);">
      <div class="metric-value">'.$stats['total'].'</div>
      <div class="metric-label">Total Unit Diperiksa</div>
      <div class="small text-muted mt-2" style="font-size: 0.72rem;">Unit dalam target</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card card-metric h-100" style="border-left-color: #16803C;">
      <div class="metric-value text-success">'.$stats['done'].'</div>
      <div class="metric-label">Sudah Maintenance</div>
      <div class="small text-success mt-2" style="font-size: 0.72rem;"><i class="bi bi-check-circle me-1"></i>'.$stats['percent'].'% Kepatuhan</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card card-metric h-100" style="border-left-color: #B54708;">
      <div class="metric-value text-warning">'.$stats['pending'].'</div>
      <div class="metric-label">Belum Maintenance</div>
      <div class="small text-muted mt-2" style="font-size: 0.72rem;">Menunggu jadwal</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card card-metric h-100" style="border-left-color: #B42318;">
      <div class="metric-value text-danger">'.$stats['repair'].'</div>
      <div class="metric-label">Temuan Masalah</div>
      <div class="small text-danger mt-2" style="font-size: 0.72rem;">Perlu perbaikan</div>
    </div>
  </div>
</div>

<!-- Compact Filter Box -->
<div class="card p-3 mb-4">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-6 col-md-2">
      <label class="form-label text-secondary small fw-semibold mb-1">Bulan</label>
      <select class="form-select form-select-sm" name="bulan">';
for ($m=1; $m<=12; $m++) {
    $body .= '<option value="'.$m.'"'.($m === $month ? ' selected' : '').'>'.$monthNames[$m].'</option>';
}
$body .= '
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label text-secondary small fw-semibold mb-1">Tahun</label>
      <select class="form-select form-select-sm font-monospace fw-semibold" name="tahun">'.$yearOpts.'</select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label text-secondary small fw-semibold mb-1">Cabang</label>
      <select class="form-select form-select-sm" name="cabang">'.$cabangOpts.'</select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label text-secondary small fw-semibold mb-1">Divisi</label>
      <select class="form-select form-select-sm" name="divisi">'.$divisiOpts.'</select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label text-secondary small fw-semibold mb-1">Status</label>
      <select class="form-select form-select-sm" name="status">
        <option value="">Semua Status</option>
        <option value="done"'.($statusFilter==='done'?' selected':'').'>Sudah Maintenance</option>
        <option value="pending"'.($statusFilter==='pending'?' selected':'').'>Belum Maintenance</option>
        <option value="repair"'.($statusFilter==='repair'?' selected':'').'>Ada Temuan Masalah</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold"><i class="bi bi-filter me-1"></i> Terapkan</button>
    </div>
  </form>
</div>

<!-- Tabel Data Audit -->
<div class="card overflow-hidden mb-4">
  <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center">
    <div>
      <h2 class="h6 mb-0 fw-semibold text-dark"><i class="bi bi-list-check text-primary me-2"></i>Daftar Pemeriksaan Perangkat</h2>
      <div class="text-secondary small">Total '.count($rows).' unit komputer tercatat dalam log audit</div>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th style="width:30px" class="text-center">No</th>
          <th>Tanggal</th>
          <th>Kode Inventaris</th>
          <th>Perangkat</th>
          <th>Pengguna</th>
          <th>Divisi</th>
          <th>Cabang</th>
          <th>Teknisi</th>
          <th>Status</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>'.$tableRows.'</tbody>
    </table>
  </div>
</div>';

// Lampiran Temuan & Solusi Pemeliharaan
$findingsList = get_findings_report($month, $year, $cabangId);
$findingsTableRows = '';
$fNo = 0;
foreach ($findingsList as $fl) {
    $fNo++;
    $flSt = strtolower(trim((string)($fl['repair_status'] ?? '')));
    $isDoneFl = in_array($flSt, ['resolved', 'selesai', 'closed', 'ok'], true);
    $flBadge = $isDoneFl
        ? '<span class="badge-chip chip-success"><i class="bi bi-check-circle-fill"></i> Terselesaikan</span>'
        : '<span class="badge-chip chip-warning"><i class="bi bi-hourglass-split"></i> Dalam Proses</span>';
    
    $loc = !empty($fl['divisi_nama']) && $fl['divisi_nama'] !== '-'
        ? e($fl['cabang_nama']).' / '.e($fl['divisi_nama'])
        : e($fl['cabang_nama']);

    $findingsTableRows .= '<tr>
      <td class="text-center text-muted small">'.$fNo.'</td>
      <td>
        <span class="fw-bold text-primary">'.e($fl['kode_inventaris']).'</span>
        <div class="small text-muted">'.e($fl['merk_model']).'</div>
      </td>
      <td>
        <div class="fw-semibold text-dark">'.e($fl['karyawan_nama']).'</div>
        <div class="small text-secondary">'.$loc.'</div>
      </td>
      <td class="text-danger fw-semibold">
        <i class="bi bi-exclamation-circle-fill me-1 small"></i>'.e($fl['finding']).'
      </td>
      <td class="text-dark">
        <div class="text-success fw-bold small mb-1"><i class="bi bi-tools me-1"></i>Tindakan Solusi:</div>
        <div>'.e($fl['action_taken']).'</div>
      </td>
      <td class="text-center">'.$flBadge.'</td>
    </tr>';
}

if (!$findingsTableRows) {
    $findingsTableRows = '<tr><td colspan="6" class="text-center py-4 text-secondary"><i class="bi bi-check-circle-fill text-success fs-4 d-block mb-1"></i>Tidak ditemukan kendala maupun kerusakan pada periode pemeliharaan ini (Kondisi 100% Normal).</td></tr>';
}

// Append Lampiran Card to $body
$body .= '
<!-- Lampiran: Rekapitulasi Temuan & Solusi -->
<div class="card overflow-hidden mb-4 border-top border-3 border-danger shadow-sm">
  <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center">
    <div>
      <h2 class="h6 mb-0 fw-bold text-dark"><i class="bi bi-paperclip text-danger me-2"></i>Lampiran: Rekapitulasi Temuan Kendala & Rekomendasi Solusi</h2>
      <div class="text-secondary small">Daftar temuan masalah perangkat dan tindakan perbaikan pada periode '.$monthName.' '.$year.'</div>
    </div>
    <span class="badge '.(count($findingsList) > 0 ? 'bg-danger' : 'bg-success').' rounded-pill px-3 py-2">
      '.count($findingsList).' Temuan
    </span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:35px" class="text-center">No</th>
          <th style="width:180px">Kode & Perangkat</th>
          <th style="width:160px">Pengguna & Lokasi</th>
          <th>Uraian Temuan / Kendala</th>
          <th>Tindakan Solusi / Rekomendasi</th>
          <th style="width:130px" class="text-center">Status Solusi</th>
        </tr>
      </thead>
      <tbody>'.$findingsTableRows.'</tbody>
    </table>
  </div>
</div>';

render_page('Reports & Audit Trail · ' . $monthName . ' ' . $year, $body, $headStyle);
