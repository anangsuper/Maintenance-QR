<?php
require __DIR__ . '/bootstrap.php';
require_login();

$month = max(1, min(12, (int)($_GET['bulan'] ?? date('n'))));
$year = max(2020, min(2100, (int)($_GET['tahun'] ?? date('Y'))));
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));
$filterStatus = trim((string)($_GET['status'] ?? 'all'));

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

// Mapping Map Temuan per Asset ID
$findingMap = [];
foreach ($findingsRows as $f) {
    $findingMap[$f['kode_inventaris']] = $f;
}

// Unified Rows Construction
$allRows = [];
foreach ($historyRows as $r) {
    $kode = $r['kode_inventaris'] ?? '-';
    $fData = $findingMap[$kode] ?? null;
    $allRows[] = [
        'is_done' => true,
        'status_type' => ($r['status'] ?? '') === 'Temuan' ? 'temuan' : 'selesai',
        'waktu' => format_id_date((string)($r['maintenance_date'] ?? '')).' '.substr((string)($r['maintenance_time'] ?? ''), 0, 5),
        'kode' => $kode,
        'perangkat' => trim(($r['merk'] ?? '').' '.($r['model'] ?? '')),
        'pemilik' => $r['karyawan_nama'] ?? '-',
        'cabang_divisi' => $r['cabang_nama'] ?? '-',
        'teknisi' => $r['teknisi_nama'] ?? $r['technician_name'] ?? 'Teknisi',
        'status_label' => ($r['status'] ?? '') === 'Temuan' ? 'Ada Temuan' : 'Selesai',
        'finding_note' => $fData ? $fData['finding'] : '',
    ];
}

foreach ($pendingRows as $r) {
    $allRows[] = [
        'is_done' => false,
        'status_type' => 'belum',
        'waktu' => '-',
        'kode' => $r['kode_inventaris'] ?? '-',
        'perangkat' => asset_title($r),
        'pemilik' => $r['karyawan_nama'] ?? '-',
        'cabang_divisi' => ($r['cabang_nama'] ?? '-').' ('.($r['divisi_nama'] ?? '-').')',
        'teknisi' => '-',
        'status_label' => 'Belum',
        'finding_note' => '',
    ];
}

// Filter Status if selected
if ($filterStatus === 'selesai') {
    $allRows = array_filter($allRows, fn($r) => $r['status_type'] === 'selesai');
} elseif ($filterStatus === 'belum') {
    $allRows = array_filter($allRows, fn($r) => $r['status_type'] === 'belum');
} elseif ($filterStatus === 'temuan') {
    $allRows = array_filter($allRows, fn($r) => $r['status_type'] === 'temuan');
}

// Filter Dropdown Options
$cabangOpts = '';
foreach ($cabangs as $c) {
    $cId = (int)($c['id'] ?? 0);
    $cNama = $c['nama'] ?? $c['nama_cabang'] ?? 'Cabang #' . $cId;
    $sel = ($cId === $cabangId) ? ' selected' : '';
    $cabangOpts .= '<option value="'.$cId.'"'.$sel.'>'.e($cNama).'</option>';
}

// Build Unified Table Rows
$trs = '';
$num = 0;
foreach ($allRows as $r) {
    $num++;
    $badge = match($r['status_type']) {
        'selesai' => '<span class="badge text-bg-success badge-compact">Selesai</span>',
        'temuan' => '<span class="badge text-bg-danger badge-compact">Temuan</span>',
        default => '<span class="badge text-bg-warning badge-compact">Belum</span>'
    };

    $noteHtml = '';
    if (!empty($r['finding_note'])) {
        $noteHtml = '<div class="small text-danger mt-1"><strong>Temuan:</strong> '.e($r['finding_note']).'</div>';
    }

    $trs .= '<tr>
      <td class="col-center">'.$num.'</td>
      <td class="col-nowrap fw-bold text-primary">'.e($r['kode']).'</td>
      <td>'.e($r['perangkat']).'</td>
      <td>'.e($r['pemilik']).'</td>
      <td>'.e($r['cabang_divisi']).'</td>
      <td class="col-nowrap text-center">'.e($r['waktu']).'</td>
      <td class="col-nowrap">'.e($r['teknisi']).'</td>
      <td class="col-nowrap text-center">'.$badge.$noteHtml.'</td>
    </tr>';
}

if (!$trs) {
    $trs = '<tr><td colspan="8" class="text-center py-4 text-muted">Tidak ada data untuk filter yang dipilih.</td></tr>';
}

$head = '<style>
/* Base Screen Styling */
body {
  background: #f4f6fa;
  font-family: "Segoe UI", Tahoma, Arial, sans-serif;
  color: #212529;
}

.report-wrapper {
  max-width: 1200px;
  margin: 0 auto;
}

.report-page {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.06);
  padding: 30px 35px;
}

.report-header {
  border-bottom: 2px solid #dee2e6;
  padding-bottom: 15px;
  margin-bottom: 25px;
}

.report-title {
  font-size: 1.4rem;
  font-weight: 700;
  color: #0d6efd;
  margin-bottom: 4px;
}

.report-sub {
  font-size: 1rem;
  color: #6c757d;
}

/* Visibility Helpers */
.print-only { display: none !important; }
.screen-only { display: flex !important; }

/* Table Screen View */
.table-report {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 25px;
}

.table-report th {
  background: #f8f9fa;
  color: #495057;
  font-weight: 600;
  border: 1px solid #dee2e6;
  padding: 10px 12px;
  vertical-align: middle;
}

.table-report td {
  border: 1px solid #dee2e6;
  padding: 10px 12px;
  vertical-align: middle;
}

.col-center { text-align: center !important; }
.col-nowrap { white-space: nowrap !important; }

/* Signature Screen View */
.signature-section {
  margin-top: 40px;
}

.sig-box {
  text-align: center;
}

.sig-line {
  width: 200px;
  margin: 60px auto 4px auto;
  border-bottom: 1px solid #333;
}

/* =========================================================
   PRINT VIEW (KHUSUS SAAT DICETAK / PDF)
   Ultra-Compact, High-Density, Paper-Saving A4 Landscape
========================================================= */
@page {
  size: A4 landscape;
  margin: 6mm 8mm;
}

@media print {
  body {
    background: #fff !important;
    margin: 0 !important;
    padding: 0 !important;
    font-size: 8pt !important;
    color: #000 !important;
  }

  .no-print, nav, header {
    display: none !important;
  }

  .screen-only {
    display: none !important;
  }

  .print-only {
    display: flex !important;
  }

  .container, main.container, .report-wrapper, .report-page {
    max-width: 100% !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    box-shadow: none !important;
    border-radius: 0 !important;
  }

  .report-header {
    border-bottom: 1.5px solid #000 !important;
    padding-bottom: 4px !important;
    margin-bottom: 8px !important;
  }

  .report-title {
    font-size: 10.5pt !important;
    color: #000 !important;
    margin-bottom: 2px !important;
  }

  .report-sub {
    font-size: 8pt !important;
    color: #333 !important;
  }

  /* Compact 1-Line Summary Strip */
  .summary-strip {
    display: flex !important;
    justify-content: space-between;
    background: #f8f8f8 !important;
    border: 1px solid #555 !important;
    border-radius: 3px !important;
    padding: 4px 10px !important;
    margin-bottom: 8px !important;
    font-size: 8pt !important;
  }

  .summary-strip .val {
    font-weight: 700 !important;
  }

  /* Ultra High-Density Table */
  .table-report {
    margin-bottom: 10px !important;
    font-size: 8pt !important;
  }

  .table-report th {
    background: #eaeaea !important;
    color: #000 !important;
    border: 1px solid #333 !important;
    padding: 3px 5px !important;
    font-weight: 700 !important;
  }

  .table-report td {
    border: 1px solid #555 !important;
    padding: 2.5px 5px !important;
    line-height: 1.15 !important;
  }

  .badge-compact {
    padding: 1px 4px !important;
    font-size: 7.5pt !important;
    border-radius: 2px !important;
  }

  .signature-section {
    margin-top: 15px !important;
    page-break-inside: avoid !important;
  }

  .sig-line {
    width: 170px !important;
    margin: 35px auto 2px auto !important;
    border-bottom: 1px solid #000 !important;
  }

  tr {
    page-break-inside: avoid !important;
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
        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-printer me-2 text-primary"></i>Cetak Laporan Rekapitulasi Maintenance</h5>
      </div>
      <button class="btn btn-primary fw-semibold px-4" onclick="window.print()"><i class="bi bi-printer-fill me-1"></i> Print / Download PDF</button>
    </div>

    <form method="get" class="row g-2 align-items-end">
      <div class="col-6 col-md-2">
        <label class="form-label small fw-semibold">Bulan</label>
        <select class="form-select form-select-sm" name="bulan">';
for ($m=1;$m<=12;$m++) {
    $body .= '<option value="'.$m.'"'.($m===$month?' selected':'').'>'.$monthNames[$m].'</option>';
}
$body .= '</select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small fw-semibold">Tahun</label>
        <input type="number" class="form-control form-control-sm" name="tahun" value="'.$year.'" min="2020" max="2100">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Cabang</label>
        <select class="form-select form-select-sm" name="cabang">
          <option value="0">Semua Cabang</option>
          '.$cabangOpts.'
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Filter Status</label>
        <select class="form-select form-select-sm" name="status">
          <option value="all"'.($filterStatus==='all'?' selected':'').'>Semua Aset (Rekap Lengkap)</option>
          <option value="selesai"'.($filterStatus==='selesai'?' selected':'').'>Hanya Selesai</option>
          <option value="temuan"'.($filterStatus==='temuan'?' selected':'').'>Hanya Ada Temuan</option>
          <option value="belum"'.($filterStatus==='belum'?' selected':'').'>Hanya Belum</option>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-filter me-1"></i> Tampilkan</button>
      </div>
    </form>
  </div>

  <!-- Main Report Container -->
  <div class="report-page">
    <!-- Header Dokumen -->
    <div class="report-header d-flex justify-content-between align-items-end flex-wrap gap-2">
      <div>
        <div class="report-title">LAPORAN REKAPITULASI MAINTENANCE IT & HARDWARE</div>
        <div class="report-sub">
          Periode: <strong class="text-dark">'.$monthName.' '.$year.'</strong> &nbsp;|&nbsp; 
          Cabang / Lokasi: <strong class="text-dark">'.e($cabangName).'</strong>
        </div>
      </div>
      <div class="text-end small text-muted">
        <div>Tanggal Cetak: <strong>'.date('d-m-Y').'</strong> ('.date('H:i').' WITA)</div>
        <div>Modul QR Maintenance</div>
      </div>
    </div>

    <!-- TAMPILAN LAYAR: 4 Kartu Statistik Elegan (Screen Only) -->
    <div class="row g-3 mb-4 screen-only">
      <div class="col-6 col-md-3">
        <div class="card p-3 border-0 bg-light">
          <div class="small text-muted fw-bold">TOTAL KOMPUTER / ASET</div>
          <div class="fs-2 fw-bold text-dark mt-1">'.$total.'</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card p-3 border-0 bg-light">
          <div class="small text-muted fw-bold">SUDAH MAINTENANCE</div>
          <div class="fs-2 fw-bold text-success mt-1">'.$done.' <small class="fs-6 fw-normal text-muted">('.$percent.'%)</small></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card p-3 border-0 bg-light">
          <div class="small text-muted fw-bold">BELUM MAINTENANCE</div>
          <div class="fs-2 fw-bold text-warning mt-1">'.$pending.'</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card p-3 border-0 bg-light">
          <div class="small text-muted fw-bold">TEMUAN KERUSAKAN</div>
          <div class="fs-2 fw-bold text-danger mt-1">'.$findingsCount.'</div>
        </div>
      </div>
    </div>

    <!-- TAMPILAN CETAK: 1 Baris Ringkasan Kompak (Print Only) -->
    <div class="summary-strip print-only">
      <div><span>Total Komputer:</span> <span class="val">'.$total.'</span></div>
      <div><span>Sudah Maintenance:</span> <span class="val text-success">'.$done.' ('.$percent.'%)</span></div>
      <div><span>Belum Maintenance:</span> <span class="val text-warning">'.$pending.'</span></div>
      <div><span>Temuan Kerusakan:</span> <span class="val text-danger">'.$findingsCount.'</span></div>
    </div>

    <!-- Tabel Rekapitulasi -->
    <table class="table-report">
      <thead>
        <tr>
          <th class="col-center" style="width: 35px;">No</th>
          <th class="col-nowrap" style="width: 120px;">Kode Inventaris</th>
          <th>Perangkat (Merk & Tipe)</th>
          <th>Pengguna / Pemilik</th>
          <th>Cabang & Divisi</th>
          <th class="col-nowrap col-center" style="width: 130px;">Waktu Maintenance</th>
          <th class="col-nowrap" style="width: 100px;">Teknisi</th>
          <th class="col-nowrap col-center" style="width: 90px;">Status</th>
        </tr>
      </thead>
      <tbody>
        '.$trs.'
      </tbody>
    </table>

    <!-- Bagian Tanda Tangan -->
    <div class="signature-section">
      <div class="row">
        <div class="col-6 sig-box">
          <div>Dibuat Oleh,</div>
          <div class="small text-muted">Teknisi Pelaksana IT</div>
          <div class="sig-line"></div>
          <div><strong>'.e(current_user_name()).'</strong></div>
        </div>
        <div class="col-6 sig-box">
          <div>Mengetahui / Menyetujui,</div>
          <div class="small text-muted">Kepala Cabang / IT Manager</div>
          <div class="sig-line"></div>
          <div><strong>( .................................................. )</strong></div>
        </div>
      </div>
    </div>
  </div>
</div>';

render_page('Laporan Maintenance Bulanan', $body, $head);
