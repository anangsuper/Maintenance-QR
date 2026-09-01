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
$historyRows = get_history_rows($month, $year, $cabangId);
$findingsRows = get_findings_report($month, $year, $cabangId);

$total = (int)($data['total'] ?? 0);
$done = (int)($data['done'] ?? 0);
$findingsCount = (int)($data['findings'] ?? count($findingsRows));
$pendingRows = $data['pendingRows'] ?? [];
$cabangs = get_cabang_list();

$cabangName = 'Semua Cabang';
if ($cabangId > 0 && is_array($cabangs)) {
    foreach ($cabangs as $c) {
        if ((int)($c['id'] ?? 0) === $cabangId) {
            $cabangName = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cabangId);
            break;
        }
    }
}

$pending = max(0, $total - $done);
$percent = $total > 0 ? round(($done / $total) * 100) : 0;

// Filter Options
$cabangOpts = '';
foreach ($cabangs as $c) {
    $cId = (int)($c['id'] ?? 0);
    $cNama = $c['nama'] ?? $c['nama_cabang'] ?? 'Cabang #' . $cId;
    $sel = ($cId === $cabangId) ? ' selected' : '';
    $cabangOpts .= '<option value="'.$cId.'"'.$sel.'>'.e($cNama).'</option>';
}

// 1. Table Selesai Maintenance
$doneTrs = '';
$i = 0;
if (!empty($historyRows)) {
    foreach ($historyRows as $r) {
        $i++;
        $tech = $r['teknisi_nama'] ?? $r['technician_name'] ?? '-';
        $statusBadge = ($r['status'] ?? '') === 'Temuan' 
            ? '<span class="badge-print badge-print-danger">Ada Temuan</span>' 
            : '<span class="badge-print badge-print-success">Selesai</span>';

        $doneTrs .= '<tr>
          <td class="text-center">'.$i.'</td>
          <td>'.e(format_id_date((string)($r['maintenance_date'] ?? ''))).' <small class="text-muted">'.e(substr((string)($r['maintenance_time'] ?? ''), 0, 5)).'</small></td>
          <td class="fw-bold">'.e($r['kode_inventaris'] ?? '-').'</td>
          <td>'.e(trim(($r['merk'] ?? '').' '.($r['model'] ?? ''))).'</td>
          <td>'.e($r['karyawan_nama'] ?? '-').'</td>
          <td>'.e(($r['cabang_nama'] ?? '-')).'</td>
          <td>'.e($tech).'</td>
          <td class="text-center">'.$statusBadge.'</td>
        </tr>';
    }
}
if (!$doneTrs) {
    $doneTrs = '<tr><td colspan="8" class="text-center py-3 text-muted">Belum ada data maintenance pada periode ini.</td></tr>';
}

// 2. Table Temuan Kerusakan
$findingTrs = '';
$k = 0;
if (!empty($findingsRows)) {
    foreach ($findingsRows as $f) {
        $k++;
        $sevClass = match($f['severity']) {
            'Berat' => 'badge-print-danger',
            'Sedang' => 'badge-print-warning',
            default => 'badge-print-info'
        };

        $findingTrs .= '<tr>
          <td class="text-center">'.$k.'</td>
          <td class="fw-bold">'.e($f['kode_inventaris']).'</td>
          <td>'.e($f['merk_model']).'<br><small class="text-muted">'.e($f['karyawan_nama']).' ('.e($f['cabang_nama']).')</small></td>
          <td>'.nl2br(e($f['finding'])).'</td>
          <td>'.nl2br(e($f['action_taken'] ?: '-')).'</td>
          <td class="text-center"><span class="badge-print '.$sevClass.'">'.e($f['severity']).'</span></td>
          <td class="text-center"><strong>'.e($f['repair_status']).'</strong></td>
        </tr>';
    }
}

// 3. Table Belum Maintenance
$pendingTrs = '';
$j = 0;
if (!empty($pendingRows)) {
    foreach ($pendingRows as $r) {
        $j++;
        $pendingTrs .= '<tr>
          <td class="text-center">'.$j.'</td>
          <td class="fw-bold">'.e($r['kode_inventaris'] ?? '-').'</td>
          <td>'.e(asset_title($r)).'</td>
          <td>'.e($r['karyawan_nama'] ?? '-').'</td>
          <td>'.e($r['cabang_nama'] ?? '-').' ('.e($r['divisi_nama'] ?? '-').')</td>
          <td class="text-center"><span class="badge-print badge-print-secondary">Belum</span></td>
        </tr>';
    }
}
if (!$pendingTrs) {
    $pendingTrs = '<tr><td colspan="6" class="text-center py-3 text-success fw-semibold">✓ Seluruh aset telah selesai dilakukan maintenance untuk periode ini.</td></tr>';
}

$head = '<style>
body { background: #eaedf2; font-family: "Segoe UI", Arial, sans-serif; }
.report-page { max-width: 1100px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 35px 40px; }
.report-title-box { border-bottom: 3px double #0d6efd; padding-bottom: 15px; margin-bottom: 25px; }
.stat-card { border: 1px solid #dee2e6; border-radius: 8px; padding: 12px 16px; background: #fdfdfd; }
.stat-card .stat-val { font-size: 1.6rem; font-weight: 700; line-height: 1.2; }
.stat-card .stat-lbl { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; font-weight: 600; }
.table-report { font-size: 0.9rem; width: 100%; border-collapse: collapse; margin-bottom: 20px; }
.table-report th { background: #f1f4f8; font-weight: 600; border: 1px solid #ccc; padding: 8px 10px; color: #333; }
.table-report td { border: 1px solid #ccc; padding: 8px 10px; vertical-align: middle; }
.badge-print { display: inline-block; padding: 3px 8px; font-size: 0.75rem; font-weight: 600; border-radius: 4px; border: 1px solid transparent; }
.badge-print-success { background: #e8f5e9; color: #1e7e34; border-color: #c3e6cb; }
.badge-print-danger { background: #fde8e8; color: #bd2130; border-color: #f5c6cb; }
.badge-print-warning { background: #fff3cd; color: #856404; border-color: #ffeeba; }
.badge-print-info { background: #e3f2fd; color: #0c5460; border-color: #bee5eb; }
.badge-print-secondary { background: #f8f9fa; color: #6c757d; border-color: #dee2e6; }
.signature-section { margin-top: 40px; page-break-inside: avoid; }
.signature-box { text-align: center; }
.signature-line { width: 180px; margin: 60px auto 4px auto; border-bottom: 1px solid #333; }

@media print {
  body { background: #fff !important; margin: 0; padding: 0; font-size: 10.5pt; color: #000; }
  .no-print, nav, header { display: none !important; }
  .report-page { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; border-radius: 0 !important; }
  .report-title-box { border-bottom: 2px solid #000 !important; }
  .table-report th, .table-report td { border: 1px solid #555 !important; }
  .stat-card { border: 1px solid #888 !important; }
  tr { page-break-inside: avoid; }
  .page-break { page-break-before: always; }
}
</style>';

$body = '
<!-- Interactive Controls Bar (Hidden during Print) -->
<div class="no-print mb-4 p-3 bg-white rounded-3 shadow-sm" style="max-width: 1100px; margin: 0 auto 20px auto;">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom pb-3 mb-3">
    <div class="d-flex align-items-center gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'"><i class="bi bi-arrow-left"></i> Kembali ke Dashboard</a>
      <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-printer me-2 text-primary"></i>Cetak Laporan Maintenance</h5>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary fw-semibold px-4" onclick="window.print()"><i class="bi bi-printer-fill me-1"></i> Print / Download PDF</button>
    </div>
  </div>

  <form method="get" class="row g-2 align-items-end">
    <div class="col-6 col-md-3">
      <label class="form-label small fw-semibold">Bulan</label>
      <select class="form-select form-select-sm" name="bulan">';
for ($m=1;$m<=12;$m++) {
    $body .= '<option value="'.$m.'"'.($m===$month?' selected':'').'>'.$monthNames[$m].'</option>';
}
$body .= '</select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small fw-semibold">Tahun</label>
      <input type="number" class="form-control form-control-sm" name="tahun" value="'.$year.'" min="2020" max="2100">
    </div>
    <div class="col-md-4">
      <label class="form-label small fw-semibold">Cabang</label>
      <select class="form-select form-select-sm" name="cabang">
        <option value="0">Semua Cabang</option>
        '.$cabangOpts.'
      </select>
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-filter me-1"></i> Tampilkan</button>
    </div>
  </form>
</div>

<!-- Main Printable Report Sheet -->
<div class="report-page">
  <!-- Kop Surat & Judul Laporan -->
  <div class="report-title-box">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div>
        <h4 class="fw-bold mb-1 text-dark" style="letter-spacing: 0.5px;">LAPORAN REKAPITULASI MAINTENANCE IT & HARDWARE</h4>
        <div class="text-secondary fs-6">
          Periode: <strong class="text-dark">'.$monthName.' '.$year.'</strong> &nbsp;|&nbsp; 
          Cabang / Lokasi: <strong class="text-dark">'.e($cabangName).'</strong>
        </div>
      </div>
      <div class="text-end small text-muted">
        <div>Tanggal Cetak: <strong>'.date('d-m-Y').'</strong></div>
        <div>Waktu: '.date('H:i').' WITA</div>
        <div>Sistem: QR Maintenance</div>
      </div>
    </div>
  </div>

  <!-- Ringkasan Statistik Eksekutif -->
  <div class="row g-2 mb-4">
    <div class="col-3">
      <div class="stat-card">
        <div class="stat-lbl">Total Komputer / Aset</div>
        <div class="stat-val text-dark">'.$total.'</div>
      </div>
    </div>
    <div class="col-3">
      <div class="stat-card">
        <div class="stat-lbl">Sudah Maintenance</div>
        <div class="stat-val text-success">'.$done.' <small class="fs-6 fw-normal text-muted">('.$percent.'%)</small></div>
      </div>
    </div>
    <div class="col-3">
      <div class="stat-card">
        <div class="stat-lbl">Belum Maintenance</div>
        <div class="stat-val text-warning">'.$pending.'</div>
      </div>
    </div>
    <div class="col-3">
      <div class="stat-card">
        <div class="stat-lbl">Temuan Kerusakan</div>
        <div class="stat-val text-danger">'.$findingsCount.'</div>
      </div>
    </div>
  </div>

  <!-- Tabel 1: Selesai Maintenance -->
  <div class="d-flex justify-content-between align-items-center mb-2 mt-4">
    <h6 class="fw-bold text-dark mb-0">1. DAFTAR KOMPUTER / ASET SELESAI MAINTENANCE ('.$done.')</h6>
  </div>
  <table class="table-report">
    <thead>
      <tr>
        <th style="width: 35px;" class="text-center">No</th>
        <th style="width: 130px;">Waktu Scan</th>
        <th style="width: 120px;">Kode Inventaris</th>
        <th>Perangkat (Merk & Tipe)</th>
        <th>Pengguna / Pemilik</th>
        <th>Cabang</th>
        <th>Teknisi</th>
        <th style="width: 90px;" class="text-center">Status</th>
      </tr>
    </thead>
    <tbody>
      '.$doneTrs.'
    </tbody>
  </table>';

// Tabel Temuan Kerusakan jika ada
if (!empty($findingsRows)) {
    $body .= '
    <div class="d-flex justify-content-between align-items-center mb-2 mt-4">
      <h6 class="fw-bold text-danger mb-0">2. DAFTAR TEMUAN & KERUSAKAN ('.$findingsCount.')</h6>
    </div>
    <table class="table-report">
      <thead>
        <tr>
          <th style="width: 35px;" class="text-center">No</th>
          <th style="width: 120px;">Kode</th>
          <th>Perangkat & Pengguna</th>
          <th>Deskripsi Temuan / Kerusakan</th>
          <th>Tindakan yang Dilakukan</th>
          <th style="width: 80px;" class="text-center">Tingkat</th>
          <th style="width: 110px;" class="text-center">Status Solusi</th>
        </tr>
      </thead>
      <tbody>
        '.$findingTrs.'
      </tbody>
    </table>';
}

$sectionNum = !empty($findingsRows) ? '3' : '2';

$body .= '
  <!-- Tabel Belum Maintenance -->
  <div class="d-flex justify-content-between align-items-center mb-2 mt-4">
    <h6 class="fw-bold text-secondary mb-0">'.$sectionNum.'. DAFTAR KOMPUTER / ASET BELUM MAINTENANCE ('.$pending.')</h6>
  </div>
  <table class="table-report">
    <thead>
      <tr>
        <th style="width: 35px;" class="text-center">No</th>
        <th style="width: 130px;">Kode Inventaris</th>
        <th>Perangkat (Merk & Tipe)</th>
        <th>Pengguna / Pemilik</th>
        <th>Cabang & Divisi</th>
        <th style="width: 90px;" class="text-center">Status</th>
      </tr>
    </thead>
    <tbody>
      '.$pendingTrs.'
    </tbody>
  </table>

  <!-- Bagian Tanda Tangan & Pengesahan -->
  <div class="signature-section">
    <div class="row">
      <div class="col-6 signature-box">
        <div>Dibuat Oleh:</div>
        <div class="small text-muted">Teknisi Pelaksana IT</div>
        <div class="signature-line"></div>
        <div class="fw-bold">'.e(current_user_name()).'</div>
      </div>
      <div class="col-6 signature-box">
        <div>Mengetahui / Menyetujui:</div>
        <div class="small text-muted">Kepala Cabang / IT Manager</div>
        <div class="signature-line"></div>
        <div class="fw-bold">( .................................................. )</div>
      </div>
    </div>
  </div>
</div>';

render_page('Laporan Maintenance Bulanan', $body, $head);
