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
        $client->preloadSheets(['Assets', 'Cabang', 'Divisi', 'Karyawan', 'Kategori_Aset', 'Asset_QR_Tokens', 'Maintenance_Scan', 'Maintenance_Checklists', 'Maintenance_Findings'], $isRefresh);
    }
}

// Handle POST Update Proses Perbaikan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'save_proses_perbaikan')) {
    verify_csrf();
    $pFindingId = (int)($_POST['finding_id'] ?? 0);
    $pScanId = (int)($_POST['scan_id'] ?? 0);
    $pAssetId = (int)($_POST['asset_id'] ?? 0);
    $pStatus = trim((string)($_POST['status'] ?? 'In Progress'));
    $pNotes = trim((string)($_POST['proses_perbaikan'] ?? ''));
    $pTech = trim((string)($_POST['technician_name'] ?? current_user_name()));

    $res = update_finding_progress($pFindingId, $pScanId, $pAssetId, $pStatus, $pNotes, $pTech);
    if (!empty($res['success'])) {
        $_SESSION['flash'] = 'Proses perbaikan perangkat berhasil disimpan.';
    } else {
        $_SESSION['flash_error'] = $res['error'] ?? 'Gagal menyimpan proses perbaikan.';
    }
    header('Location: ' . module_url('audit.php', [
        'bulan' => $month,
        'tahun' => $year,
        'cabang' => $cabangId,
        'divisi' => $divisiId,
        'kategori' => $kategoriId,
        'status' => $statusFilter
    ]));
    exit;
}

$flash = $_SESSION['flash'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_error']);

if ($isRefresh && empty($flash)) {
    $flash = 'Data audit dan rincian pemeliharaan berhasil disegarkan langsung dari Google Spreadsheet.';
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

$flashAlert = '';
if ($flash !== '') {
    $flashAlert .= '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3" role="alert"><i class="bi bi-check-circle-fill me-2"></i><strong>Berhasil!</strong> '.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
if ($flashError !== '') {
    $flashAlert .= '<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" role="alert"><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Gagal:</strong> '.e($flashError).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

$body = '
'.$flashAlert.'
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
    $isDoneFl = in_array($flSt, ['resolved', 'selesai', 'closed', 'ok', 'normal'], true);
    $isProgFl = in_array($flSt, ['proses', 'in progress', 'sedang perbaikan', 'dalam proses'], true);

    if ($isDoneFl) {
        $flBadge = '<span class="badge-chip chip-success"><i class="bi bi-check-circle-fill"></i> Terselesaikan</span>';
    } elseif ($isProgFl) {
        $flBadge = '<span class="badge-chip chip-warning"><i class="bi bi-hourglass-split"></i> Dalam Proses</span>';
    } else {
        $flBadge = '<span class="badge-chip chip-danger"><i class="bi bi-exclamation-triangle-fill"></i> Perlu Perbaikan</span>';
    }

    $loc = !empty($fl['divisi_nama']) && $fl['divisi_nama'] !== '-'
        ? e($fl['cabang_nama']).' / '.e($fl['divisi_nama'])
        : e($fl['cabang_nama']);

    $prosesNotes = trim((string)($fl['proses_perbaikan'] ?? $fl['catatan_penyelesaian'] ?? ''));
    if ($prosesNotes !== '' && $prosesNotes !== '-') {
        $prosesHtml = '
        <div class="p-2 bg-light rounded border small">
          <div class="fw-semibold text-primary mb-1"><i class="bi bi-gear-wide-connected me-1"></i>Progres Terkini:</div>
          <div class="text-dark" style="line-height: 1.35;">'.nl2br(e($prosesNotes)).'</div>
          '.(!empty($fl['resolved_by']) ? '<div class="text-muted mt-1" style="font-size: 0.72rem;"><i class="bi bi-person-check me-1"></i>'.e($fl['resolved_by']).(!empty($fl['resolved_at']) ? ' · '.e(substr($fl['resolved_at'], 0, 16)) : '').'</div>' : '').'
        </div>';
    } else {
        $prosesHtml = '<span class="badge bg-light text-muted border fw-normal py-1 px-2"><i class="bi bi-clock me-1"></i>Belum ada catatan progres</span>';
    }

    $fDataJson = htmlspecialchars(json_encode([
        'finding_id' => (int)($fl['finding_id'] ?? $fl['id'] ?? 0),
        'scan_id' => (int)($fl['scan_id'] ?? 0),
        'asset_id' => (int)($fl['asset_id'] ?? 0),
        'kode' => (string)($fl['kode_inventaris'] ?? '-'),
        'perangkat' => (string)($fl['merk_model'] ?? '-'),
        'pengguna' => (string)($fl['karyawan_nama'] ?? '-'),
        'lokasi' => (string)$loc,
        'finding' => (string)($fl['finding'] ?? ''),
        'recommendation' => (string)($fl['action_taken'] ?? ''),
        'status' => $fl['repair_status'] ?? 'Perlu Perbaikan',
        'proses_perbaikan' => $prosesNotes,
        'teknisi' => (string)($fl['teknisi'] ?? current_user_name()),
    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

    $detailUrl = !empty($fl['scan_id']) ? module_url('maintenance_detail.php', ['id' => (int)$fl['scan_id']]) : '';

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
        '.(!empty($fl['reported_by']) ? '<div class="small text-muted fw-normal mt-1" style="font-size: 0.72rem;"><i class="bi bi-person me-1"></i>Dilaporkan: '.e($fl['reported_by']).(!empty($fl['reported_at']) ? ' ('.e(substr($fl['reported_at'], 0, 10)).')' : '').'</div>' : '').'
      </td>
      <td class="text-dark">
        <div class="text-success fw-bold small mb-1"><i class="bi bi-tools me-1"></i>Tindakan Solusi:</div>
        <div>'.e($fl['action_taken']).'</div>
      </td>
      <td>'.$prosesHtml.'</td>
      <td class="text-center">'.$flBadge.'</td>
      <td class="text-center">
        <div class="d-flex justify-content-center gap-1">
          <button type="button" class="btn btn-sm btn-primary py-1 px-2 fw-semibold" onclick="openProsesPerbaikanModal('.$fDataJson.')" title="Update Proses Perbaikan">
            <i class="bi bi-tools me-1"></i> Update
          </button>
          '.($detailUrl ? '<a href="'.e($detailUrl).'" class="btn btn-sm btn-light border py-1 px-2" title="Lihat Rincian Maintenance"><i class="bi bi-eye"></i></a>' : '').'
        </div>
      </td>
    </tr>';
}

if (!$findingsTableRows) {
    $findingsTableRows = '<tr><td colspan="8" class="text-center py-4 text-secondary"><i class="bi bi-check-circle-fill text-success fs-4 d-block mb-1"></i>Tidak ditemukan kendala maupun kerusakan pada periode pemeliharaan ini (Kondisi 100% Normal).</td></tr>';
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
          <th style="width:170px">Kode & Perangkat</th>
          <th style="width:160px">Pengguna & Lokasi</th>
          <th>Uraian Temuan / Kendala</th>
          <th>Tindakan Solusi / Rekomendasi</th>
          <th style="width:230px">Proses Perbaikan</th>
          <th style="width:135px" class="text-center">Status Solusi</th>
          <th style="width:115px" class="text-center">Aksi</th>
        </tr>
      </thead>
      <tbody>'.$findingsTableRows.'</tbody>
    </table>
  </div>
</div>

<!-- Modal Update Proses Perbaikan -->
<div class="modal fade" id="modalProsesPerbaikan" tabindex="-1" aria-labelledby="modalProsesPerbaikanLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="post">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="action" value="save_proses_perbaikan">
        <input type="hidden" name="finding_id" id="modalFindingId" value="0">
        <input type="hidden" name="scan_id" id="modalScanId" value="0">
        <input type="hidden" name="asset_id" id="modalAssetId" value="0">
        
        <div class="modal-header bg-primary text-white py-3">
          <h5 class="modal-title fw-bold fs-6 mb-0" id="modalProsesPerbaikanLabel"><i class="bi bi-tools me-2"></i>Update Proses Perbaikan Perangkat</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <!-- Info Perangkat & Kendala -->
          <div class="p-3 bg-light rounded-3 mb-3 border">
            <div class="fw-bold text-primary font-monospace" id="modalKodePerangkat">-</div>
            <div class="small fw-semibold text-dark mb-2" id="modalNamaPerangkat">-</div>
            <div class="alert alert-danger py-2 px-3 small mb-0">
              <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Temuan:</strong>
              <span id="modalDeskripsiTemuan">-</span>
            </div>
          </div>

          <!-- Status Hasil Perbaikan -->
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Status Proses Perbaikan <span class="text-danger">*</span></label>
            <select class="form-select fw-semibold" name="status" id="modalStatusSelect" required>
              <option value="In Progress" class="text-warning fw-bold">⏳ Dalam Proses Perbaikan (Sedang Dikerjakan / Tunggu Sparepart)</option>
              <option value="Resolved" class="text-success fw-bold">✓ Terselesaikan (Perbaikan Berhasil & Komputer Normal)</option>
              <option value="Perlu Perbaikan" class="text-danger fw-bold">⚠️ Perlu Perbaikan (Belum Ditangani)</option>
            </select>
          </div>

          <!-- Catatan / Progres Perbaikan -->
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Catatan / Uraian Proses Perbaikan <span class="text-danger">*</span></label>
            <textarea class="form-control" name="proses_perbaikan" id="modalProsesTextarea" rows="3" placeholder="Contoh: Telah diajukan pengadaan SSD 256GB ke bagian pengadaan / Sedang proses backup data pengguna..." required></textarea>
            
            <div class="mt-2">
              <div class="small text-muted mb-1"><i class="bi bi-tag me-1"></i> Klik untuk isi cepat tindakan:</div>
              <div class="d-flex flex-wrap gap-1">
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="appendModalAction(\'Sedang diajukan pengadaan sparepart (SSD/RAM)\')">📦 Tunggu Sparepart</button>
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="appendModalAction(\'Sedang proses backup data & instalasi ulang OS Windows\')">💻 Backup & Reinstall OS</button>
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="appendModalAction(\'Telah diganti sparepart baru dan berfungsi normal kembali\')">✓ Ganti Sparepart Selesai</button>
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="appendModalAction(\'Pembersihan debu hardware & pergantian thermal paste\')">💨 Bersih Hardware</button>
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="appendModalAction(\'Perbaikan driver & konfigurasi jaringan/LAN selesai\')">🌐 Driver & LAN Normal</button>
              </div>
            </div>
          </div>

          <!-- Teknisi -->
          <div class="mb-2">
            <label class="form-label small fw-bold text-secondary">Teknisi Penanggung Jawab</label>
            <input type="text" class="form-control" name="technician_name" id="modalTeknisiInput" value="'.e(current_user_name()).'" placeholder="Nama teknisi...">
          </div>
        </div>
        <div class="modal-footer bg-light py-2 px-4">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-save-fill me-1"></i> Simpan Proses Perbaikan</button>
        </div>
      </form>
    </div>
  </div>
</div>';

$auditScript = '
<script>
function openProsesPerbaikanModal(data) {
  document.getElementById("modalFindingId").value = data.finding_id || 0;
  document.getElementById("modalScanId").value = data.scan_id || 0;
  document.getElementById("modalAssetId").value = data.asset_id || 0;
  document.getElementById("modalKodePerangkat").textContent = data.kode || "-";
  document.getElementById("modalNamaPerangkat").textContent = (data.perangkat || "-") + " (" + (data.pengguna || "-") + ")";
  document.getElementById("modalDeskripsiTemuan").textContent = data.finding || "-";
  
  var statusSel = document.getElementById("modalStatusSelect");
  var st = (data.status || "").toLowerCase();
  if (st === "resolved" || st === "selesai") {
    statusSel.value = "Resolved";
  } else if (st === "in progress" || st === "proses" || st === "dalam proses") {
    statusSel.value = "In Progress";
  } else {
    statusSel.value = "In Progress";
  }

  document.getElementById("modalProsesTextarea").value = data.proses_perbaikan || "";
  if (data.teknisi) {
    document.getElementById("modalTeknisiInput").value = data.teknisi;
  }

  var modalEl = document.getElementById("modalProsesPerbaikan");
  if (modalEl) {
    var modal = new bootstrap.Modal(modalEl);
    modal.show();
  }
}

function appendModalAction(text) {
  var ta = document.getElementById("modalProsesTextarea");
  if (!ta) return;
  if (ta.value.trim() !== "") {
    ta.value += "\n" + text;
  } else {
    ta.value = text;
  }
}
</script>';

render_page('Reports & Audit Trail · ' . $monthName . ' ' . $year, $body, $headStyle, $auditScript);
