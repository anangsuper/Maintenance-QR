<?php
require __DIR__ . '/bootstrap.php';
require_login();

$month = max(1, min(12, (int)($_GET['bulan'] ?? date('n'))));
$year = max(2020, min(2100, (int)($_GET['tahun'] ?? date('Y'))));
$cabangId = (int)($_GET['cabang'] ?? 0);
$divisiId = (int)($_GET['divisi'] ?? 0);
$kategoriId = (int)($_GET['kategori'] ?? 0);
$techFilter = trim((string)($_GET['teknisi'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));

$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$monthName = $monthNames[$month] ?? date('F');

$cabangs = get_cabang_list();
$divisis = get_divisi_list();
$kategoris = get_kategori_list();

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

$body = '
<!-- Header & Action Bar -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
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
      <input type="number" class="form-control form-control-sm" name="tahun" value="'.$year.'">
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

render_page('Reports & Audit Trail · ' . $monthName . ' ' . $year, $body);
