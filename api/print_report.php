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

// Filter Dropdown Options
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

        $waktuStr = e(format_id_date((string)($r['maintenance_date'] ?? ''))).' '.e(substr((string)($r['maintenance_time'] ?? ''), 0, 5));

        $doneTrs .= '<tr>
          <td class="col-no">'.$i.'</td>
          <td class="col-nowrap">'.$waktuStr.'</td>
          <td class="col-nowrap fw-bold">'.e($r['kode_inventaris'] ?? '-').'</td>
          <td>'.e(trim(($r['merk'] ?? '').' '.($r['model'] ?? ''))).'</td>
          <td>'.e($r['karyawan_nama'] ?? '-').'</td>
          <td>'.e(($r['cabang_nama'] ?? '-')).'</td>
          <td class="col-nowrap">'.e($tech).'</td>
          <td class="col-nowrap text-center">'.$statusBadge.'</td>
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
          <td class="col-no">'.$k.'</td>
          <td class="col-nowrap fw-bold">'.e($f['kode_inventaris']).'</td>
          <td>'.e($f['merk_model']).'<br><small class="text-muted">'.e($f['karyawan_nama']).' ('.e($f['cabang_nama']).')</small></td>
          <td>'.nl2br(e($f['finding'])).'</td>
          <td>'.nl2br(e($f['action_taken'] ?: '-')).'</td>
          <td class="col-nowrap text-center"><span class="badge-print '.$sevClass.'">'.e($f['severity']).'</span></td>
          <td class="col-nowrap text-center"><strong>'.e($f['repair_status']).'</strong></td>
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
          <td class="col-no">'.$j.'</td>
          <td class="col-nowrap fw-bold">'.e($r['kode_inventaris'] ?? '-').'</td>
          <td>'.e(asset_title($r)).'</td>
          <td>'.e($r['karyawan_nama'] ?? '-').'</td>
          <td>'.e($r['cabang_nama'] ?? '-').' ('.e($r['divisi_nama'] ?? '-').')</td>
          <td class="col-nowrap text-center"><span class="badge-print badge-print-secondary">Belum</span></td>
        </tr>';
    }
}
if (!$pendingTrs) {
    $pendingTrs = '<tr><td colspan="6" class="text-center py-3 text-success fw-semibold">✓ Seluruh aset telah selesai dilakukan maintenance untuk periode ini.</td></tr>';
}

$head = '<style>
/* Reset & Page Setup */
@page {
  size: A4 landscape;
  margin: 10mm 12mm;
}

body {
  background: #eaedf2;
  font-family: "Segoe UI", Tahoma, Arial, sans-serif;
  color: #212529;
}

.report-wrapper {
  max-width: 1200px;
  margin: 0 auto;
}

.report-page {
  width: 100%;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.08);
  padding: 30px 35px;
  box-sizing: border-box;
}

.report-header {
  border-bottom: 2px solid #222;
  padding-bottom: 12px;
  margin-bottom: 18px;
}

/* Compact Executive Summary Row */
.summary-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 22px;
}

.summary-card {
  flex: 1 1 0;
  min-width: 140px;
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  padding: 10px 14px;
}

.summary-card .lbl {
  font-size: 0.75rem;
  font-weight: 700;
  color: #6c757d;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 2px;
}

.summary-card .val {
  font-size: 1.4rem;
  font-weight: 700;
  line-height: 1.2;
}

/* Tables */
.table-report {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 22px;
  font-size: 9.5pt;
  table-layout: auto;
}

.table-report th {
  background: #f1f3f5;
  color: #111;
  font-weight: 600;
  border: 1px solid #999;
  padding: 7px 10px;
  vertical-align: middle;
}

.table-report td {
  border: 1px solid #bbb;
  padding: 6px 10px;
  vertical-align: middle;
}

.col-no {
  width: 35px;
  text-align: center;
  white-space: nowrap;
}

.col-nowrap {
  white-space: nowrap !important;
}

.badge-print {
  display: inline-block;
  padding: 2px 7px;
  font-size: 8pt;
  font-weight: 600;
  border-radius: 4px;
  border: 1px solid transparent;
  white-space: nowrap;
}

.badge-print-success { background: #d1e7dd; color: #0f5132; border-color: #badbcc; }
.badge-print-danger { background: #f8d7da; color: #842029; border-color: #f5c2c7; }
.badge-print-warning { background: #fff3cd; color: #664d03; border-color: #ffecb5; }
.badge-print-info { background: #cff4fc; color: #055160; border-color: #b6effb; }
.badge-print-secondary { background: #e2e3e5; color: #41464b; border-color: #d3d6d8; }

.section-heading {
  font-size: 10.5pt;
  font-weight: 700;
  margin-bottom: 8px;
  color: #111;
}

/* Signature Block */
.signature-section {
  margin-top: 35px;
  page-break-inside: avoid;
}

.signature-box {
  text-align: center;
}

.signature-line {
  width: 200px;
  margin: 55px auto 4px auto;
  border-bottom: 1px solid #000;
}

/* Print CSS */
@media print {
  body {
    background: #fff !important;
    margin: 0 !important;
    padding: 0 !important;
    font-size: 9pt;
    color: #000;
  }

  .no-print, nav, header {
    display: none !important;
  }

  .container, main.container, .report-wrapper, .report-page {
    max-width: 100% !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    box-shadow: none !important;
    border-radius: 0 !important;
  }

  .table-report th {
    background: #e9ecef !important;
    color: #000 !important;
    border: 1px solid #444 !important;
  }

  .table-report td {
    border: 1px solid #555 !important;
  }

  .summary-card {
    border: 1px solid #777 !important;
    background: #fff !important;
  }

  tr {
    page-break-inside: avoid;
  }

  .page-break {
    page-break-before: always;
  }
}
</style>';

$body = '
<div class="report-wrapper">
  <!-- Interactive Controls Bar (Hidden during Print) -->
  <div class="no-print mb-4 p-3 bg-white rounded-3 shadow-sm">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom pb-3 mb-3">
      <div class="d-flex align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'"><i class="bi bi-arrow-left"></i> Dashboard</a>
        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-printer me-2 text-primary"></i>Cetak Rekap Maintenance (Format A4 Landscape)</h5>
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
    <div class="report-header">
      <div class="d-flex justify-content-between align-items-end flex-wrap gap-2">
        <div>
          <h4 class="fw-bold mb-1 text-dark" style="letter-spacing: 0.5px;">LAPORAN REKAPITULASI MAINTENANCE IT & HARDWARE</h4>
          <div class="text-secondary" style="font-size: 10.5pt;">
            Periode: <strong class="text-dark">'.$monthName.' '.$year.'</strong> &nbsp;|&nbsp; 
            Cabang / Lokasi: <strong class="text-dark">'.e($cabangName).'</strong>
          </div>
        </div>
        <div class="text-end small text-muted">
          <div>Tanggal Cetak: <strong>'.date('d-m-Y').'</strong> ('.date('H:i').' WITA)</div>
          <div>Sistem: QR Maintenance</div>
        </div>
      </div>
    </div>

    <!-- Ringkasan Statistik Eksekutif (Horizontal Bar) -->
    <div class="summary-bar">
      <div class="summary-card">
        <div class="lbl">Total Aset</div>
        <div class="val text-dark">'.$total.'</div>
      </div>
      <div class="summary-card">
        <div class="lbl">Sudah Maintenance</div>
        <div class="val text-success">'.$done.' <small style="font-size: 9pt; font-weight: normal; color: #555;">('.$percent.'%)</small></div>
      </div>
      <div class="summary-card">
        <div class="lbl">Belum Maintenance</div>
        <div class="val text-warning">'.$pending.'</div>
      </div>
      <div class="summary-card">
        <div class="lbl">Temuan Kerusakan</div>
        <div class="val text-danger">'.$findingsCount.'</div>
      </div>
    </div>

    <!-- Tabel 1: Selesai Maintenance -->
    <div class="section-heading">1. DAFTAR KOMPUTER / ASET SELESAI MAINTENANCE ('.$done.')</div>
    <table class="table-report">
      <thead>
        <tr>
          <th class="col-no">No</th>
          <th style="width: 140px;" class="col-nowrap">Waktu Scan</th>
          <th style="width: 120px;" class="col-nowrap">Kode Inventaris</th>
          <th>Perangkat (Merk & Tipe)</th>
          <th>Pengguna / Pemilik</th>
          <th>Cabang</th>
          <th style="width: 110px;" class="col-nowrap">Teknisi</th>
          <th style="width: 90px;" class="col-nowrap text-center">Status</th>
        </tr>
      </thead>
      <tbody>
        '.$doneTrs.'
      </tbody>
    </table>';

// Tabel Temuan Kerusakan jika ada
if (!empty($findingsRows)) {
    $body .= '
    <div class="section-heading text-danger mt-3">2. DAFTAR TEMUAN & KERUSAKAN ('.$findingsCount.')</div>
    <table class="table-report">
      <thead>
        <tr>
          <th class="col-no">No</th>
          <th style="width: 120px;" class="col-nowrap">Kode</th>
          <th>Perangkat & Pengguna</th>
          <th>Deskripsi Temuan / Kerusakan</th>
          <th>Tindakan yang Dilakukan</th>
          <th style="width: 80px;" class="col-nowrap text-center">Tingkat</th>
          <th style="width: 110px;" class="col-nowrap text-center">Status Solusi</th>
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
    <div class="section-heading text-secondary mt-3">'.$sectionNum.'. DAFTAR KOMPUTER / ASET BELUM MAINTENANCE ('.$pending.')</div>
    <table class="table-report">
      <thead>
        <tr>
          <th class="col-no">No</th>
          <th style="width: 130px;" class="col-nowrap">Kode Inventaris</th>
          <th>Perangkat (Merk & Tipe)</th>
          <th>Pengguna / Pemilik</th>
          <th>Cabang & Divisi</th>
          <th style="width: 90px;" class="col-nowrap text-center">Status</th>
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
  </div>
</div>';

render_page('Laporan Maintenance Bulanan', $body, $head);
