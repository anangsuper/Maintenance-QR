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
    'status' => $statusFilter
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

    $tableRows .= '
    <tr class="'.(!$isDone ? 'table-danger bg-opacity-10' : '').'">
      <td class="text-center text-muted small">'.$no.'</td>
      <td class="small fw-semibold">'.e($r['maintenance_date']).'</td>
      <td><span class="fw-bold text-primary">'.e($r['kode_inventaris']).'</span></td>
      <td class="fw-semibold text-dark">'.e($r['perangkat']).' <span class="badge bg-light text-secondary border small">'.e($r['kategori_nama']).'</span></td>
      <td><span class="d-inline-flex align-items-center gap-1"><i class="bi bi-person-circle text-secondary"></i> '.e($r['karyawan_nama']).'</span></td>
      <td class="small">'.e($r['divisi_nama']).'</td>
      <td class="small"><span class="badge-chip chip-secondary">'.e($r['cabang_nama']).'</span></td>
      <td class="small fw-bold text-dark">'.e($r['technician_name']).'</td>
      <td>'.$badge.'</td>
      <td class="text-end text-nowrap">'.$actionBtn.'</td>
    </tr>';
}

if (!$tableRows) {
    $tableRows = '<tr><td colspan="10" class="text-center py-5 text-secondary"><i class="bi bi-search fs-1 d-block mb-2 opacity-50"></i>Tidak ada data perangkat yang cocok dengan kriteria filter.</td></tr>';
}

$body = '
<!-- Header & Action Bar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1 text-dark"><i class="bi bi-shield-check text-primary me-2"></i>Audit Maintenance IT</h2>
    <div class="text-secondary">Pemeriksaan kepatuhan pemeliharaan perangkat IT periode <strong>'.$monthName.' '.$year.'</strong>.</div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-primary fw-semibold" target="_blank" href="'.e(module_url('print_report.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'"><i class="bi bi-printer-fill me-1"></i> Cetak Laporan PDF</a>
    <a class="btn btn-outline-success fw-semibold" href="'.e(module_url('export_csv.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Excel (CSV)</a>
  </div>
</div>

<!-- Filter Box -->
<div class="card p-3 p-md-4 border-0 shadow-sm mb-4">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold text-secondary">Bulan</label>
      <select class="form-select form-select-sm" name="bulan">';
for ($m=1; $m<=12; $m++) {
    $body .= '<option value="'.$m.'"'.($m === $month ? ' selected' : '').'>'.$monthNames[$m].'</option>';
}
$body .= '
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold text-secondary">Tahun</label>
      <input type="number" class="form-control form-control-sm" name="tahun" value="'.$year.'">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold text-secondary">Cabang</label>
      <select class="form-select form-select-sm" name="cabang">'.$cabangOpts.'</select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold text-secondary">Divisi</label>
      <select class="form-select form-select-sm" name="divisi">'.$divisiOpts.'</select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold text-secondary">Status</label>
      <select class="form-select form-select-sm" name="status">
        <option value="">Semua Status</option>
        <option value="done"'.($statusFilter==='done'?' selected':'').'>✓ Sudah Maintenance</option>
        <option value="pending"'.($statusFilter==='pending'?' selected':'').'>✕ Belum Maintenance</option>
        <option value="repair"'.($statusFilter==='repair'?' selected':'').'>⚠️ Ada Temuan / Perlu Perbaikan</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold py-2"><i class="bi bi-funnel-fill me-1"></i> Terapkan Filter</button>
    </div>
  </form>
</div>

<!-- Statistik Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card p-3 border-0 shadow-sm border-start border-primary border-4 h-100">
      <div class="text-secondary small fw-bold">TOTAL PERANGKAT</div>
      <div class="fs-2 fw-bold text-dark mt-1">'.$stats['total'].'</div>
      <div class="text-muted small">Unit terdaftar</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card p-3 border-0 shadow-sm border-start border-success border-4 h-100">
      <div class="text-success small fw-bold">SUDAH MAINTENANCE</div>
      <div class="fs-2 fw-bold text-success mt-1">'.$stats['done'].'</div>
      <div class="small text-success fw-semibold"><i class="bi bi-check-circle-fill"></i> '.$stats['percent'].'% Progress</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card p-3 border-0 shadow-sm border-start border-danger border-4 h-100">
      <div class="text-danger small fw-bold">BELUM MAINTENANCE</div>
      <div class="fs-2 fw-bold text-danger mt-1">'.$stats['pending'].'</div>
      <div class="small text-danger fw-semibold"><i class="bi bi-x-circle-fill"></i> Perlu dilakukan</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card p-3 border-0 shadow-sm border-start border-warning border-4 h-100">
      <div class="text-warning-emphasis small fw-bold">PROSES / PERLU PERBAIKAN</div>
      <div class="fs-2 fw-bold text-warning-emphasis mt-1">'.$stats['repair'].'</div>
      <div class="small text-muted">Ada temuan kendala</div>
    </div>
  </div>
</div>

<!-- Tabel Data Audit -->
<div class="card p-4 border-0 shadow-sm">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-list-check text-primary me-2"></i>Daftar Pemeriksaan Perangkat ('.count($rows).' Unit)</h5>
  </div>
  <div class="table-responsive rounded-3 border">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:30px" class="text-center">No</th>
          <th>Tanggal</th>
          <th>Kode Inventaris</th>
          <th>Perangkat</th>
          <th>Pengguna/User</th>
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

render_page('Audit Maintenance · ' . $monthName . ' ' . $year, $body);
