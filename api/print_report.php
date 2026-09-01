<?php
require __DIR__ . '/bootstrap.php';
require_login();

$month = max(1, min(12, (int)($_GET['bulan'] ?? date('n'))));
$year = max(2020, min(2100, (int)($_GET['tahun'] ?? date('Y'))));
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));
$filterStatus = trim((string)($_GET['status'] ?? 'all')); // all, selesai, belum, temuan

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

// Mapping All Assets into a Single Unified Table
// 1. History scanned
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

// 2. Pending assets
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

// Build Unified Compact Table Rows
$trs = '';
$num = 0;
foreach ($allRows as $r) {
    $num++;
    $badge = match($r['status_type']) {
        'selesai' => '<span class="badge-compact bg-selesai">✓ Selesai</span>',
        'temuan' => '<span class="badge-compact bg-temuan">⚠️ Temuan</span>',
        default => '<span class="badge-compact bg-belum">Belum</span>'
    };

    $noteHtml = '';
    if (!empty($r['finding_note'])) {
        $noteHtml = '<div class="finding-desc"><small><strong>Ket:</strong> '.e($r['finding_note']).'</small></div>';
    }

    $trs .= '<tr>
      <td class="col-center">'.$num.'</td>
      <td class="col-nowrap fw-bold">'.e($r['kode']).'</td>
      <td>'.e($r['perangkat']).'</td>
      <td>'.e($r['pemilik']).'</td>
      <td>'.e($r['cabang_divisi']).'</td>
      <td class="col-nowrap text-center">'.e($r['waktu']).'</td>
      <td class="col-nowrap">'.e($r['teknisi']).'</td>
      <td class="col-nowrap text-center">'.$badge.$noteHtml.'</td>
    </tr>';
}

if (!$trs) {
    $trs = '<tr><td colspan="8" class="text-center py-2 text-muted">Tidak ada data untuk filter yang dipilih.</td></tr>';
}

$head = '<style>
/* Page Setup: A4 Landscape, tight margins for maximum space efficiency */
@page {
  size: A4 landscape;
  margin: 6mm 8mm;
}

body {
  background: #eaedf2;
  font-family: "Segoe UI", Arial, sans-serif;
  color: #111;
  font-size: 8.5pt;
  line-height: 1.2;
}

.report-wrapper {
  max-width: 1200px;
  margin: 0 auto;
}

.report-page {
  background: #fff;
  border-radius: 6px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06);
  padding: 18px 22px;
  box-sizing: border-box;
}

/* Compact Header */
.report-header {
  border-bottom: 2px solid #222;
  padding-bottom: 6px;
  margin-bottom: 10px;
}

.report-title {
  font-size: 11pt;
  font-weight: 700;
  margin: 0 0 2px 0;
  letter-spacing: 0.3px;
}

.report-sub {
  font-size: 8.5pt;
  color: #444;
  margin: 0;
}

/* 1-Line Compact Summary Strip */
.summary-strip {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  background: #f1f4f8;
  border: 1px solid #c5d0de;
  border-radius: 4px;
  padding: 5px 12px;
  margin-bottom: 10px;
  font-size: 8.5pt;
}

.summary-strip .item {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.summary-strip .val {
  font-weight: 700;
}

/* Ultra Compact High-Density Table */
.table-compact {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 12px;
  font-size: 8pt;
}

.table-compact th {
  background: #e9ecef;
  color: #000;
  font-weight: 700;
  border: 1px solid #555;
  padding: 4px 6px;
  vertical-align: middle;
  text-align: left;
}

.table-compact td {
  border: 1px solid #777;
  padding: 3px 6px;
  vertical-align: middle;
}

.col-center { text-align: center !important; }
.col-nowrap { white-space: nowrap !important; }

/* Status Badges */
.badge-compact {
  display: inline-block;
  padding: 1px 5px;
  font-size: 7.5pt;
  font-weight: 700;
  border-radius: 3px;
  border: 1px solid transparent;
  line-height: 1.1;
}

.bg-selesai { background: #d1e7dd; color: #0f5132; border-color: #badbcc; }
.bg-temuan { background: #f8d7da; color: #842029; border-color: #f5c2c7; }
.bg-belum { background: #fff3cd; color: #664d03; border-color: #ffecb5; }

.finding-desc {
  font-size: 7.5pt;
  color: #842029;
  margin-top: 1px;
  line-height: 1.1;
}

/* Compact Signature Block */
.signature-strip {
  margin-top: 15px;
  page-break-inside: avoid;
}

.sig-box {
  text-align: center;
  font-size: 8.5pt;
}

.sig-line {
  width: 170px;
  margin: 35px auto 2px auto;
  border-bottom: 1px solid #000;
}

/* Print CSS Optimization */
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

  .container, main.container, .report-wrapper, .report-page {
    max-width: 100% !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    box-shadow: none !important;
    border-radius: 0 !important;
  }

  .table-compact th {
    background: #f0f0f0 !important;
    border: 1px solid #333 !important;
  }

  .table-compact td {
    border: 1px solid #444 !important;
  }

  .summary-strip {
    background: #f8f8f8 !important;
    border: 1px solid #666 !important;
  }

  tr {
    page-break-inside: avoid;
  }
}
</style>';

$body = '
<div class="report-wrapper">
  <!-- Interactive Controls Bar (Hidden during Print) -->
  <div class="no-print mb-3 p-3 bg-white rounded-3 shadow-sm">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom pb-2 mb-2">
      <div class="d-flex align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm py-1" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'"><i class="bi bi-arrow-left"></i> Dashboard</a>
        <span class="fw-bold fs-6 text-dark"><i class="bi bi-printer me-1 text-primary"></i>Cetak Rekap Bulanan (Format Ringkas Hemat Kertas)</span>
      </div>
      <button class="btn btn-primary btn-sm fw-semibold px-3 py-1" onclick="window.print()"><i class="bi bi-printer-fill me-1"></i> Print / Simpan PDF</button>
    </div>

    <form method="get" class="row g-2 align-items-end">
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1 fw-semibold">Bulan</label>
        <select class="form-select form-select-sm" name="bulan">';
for ($m=1;$m<=12;$m++) {
    $body .= '<option value="'.$m.'"'.($m===$month?' selected':'').'>'.$monthNames[$m].'</option>';
}
$body .= '</select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1 fw-semibold">Tahun</label>
        <input type="number" class="form-control form-control-sm" name="tahun" value="'.$year.'" min="2020" max="2100">
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1 fw-semibold">Cabang</label>
        <select class="form-select form-select-sm" name="cabang">
          <option value="0">Semua Cabang</option>
          '.$cabangOpts.'
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1 fw-semibold">Filter Status</label>
        <select class="form-select form-select-sm" name="status">
          <option value="all"'.($filterStatus==='all'?' selected':'').'>Semua Aset (Rekap Lengkap)</option>
          <option value="selesai"'.($filterStatus==='selesai'?' selected':'').'>Hanya Yang Selesai</option>
          <option value="temuan"'.($filterStatus==='temuan'?' selected':'').'>Hanya Yang Ada Temuan</option>
          <option value="belum"'.($filterStatus==='belum'?' selected':'').'>Hanya Yang Belum</option>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-outline-primary btn-sm w-100 py-1"><i class="bi bi-filter"></i> Tampilkan</button>
      </div>
    </form>
  </div>

  <!-- Printable Report Page -->
  <div class="report-page">
    <!-- Header -->
    <div class="report-header d-flex justify-content-between align-items-end">
      <div>
        <div class="report-title">LAPORAN REKAPITULASI MAINTENANCE IT & HARDWARE</div>
        <div class="report-sub">
          Periode: <strong>'.$monthName.' '.$year.'</strong> &nbsp;|&nbsp; 
          Lokasi/Cabang: <strong>'.e($cabangName).'</strong>
        </div>
      </div>
      <div class="text-end small text-muted" style="font-size: 7.5pt;">
        <div>Tanggal Cetak: '.date('d-m-Y H:i').' WITA</div>
        <div>Total: '.$total.' Komputer</div>
      </div>
    </div>

    <!-- 1-Line Compact Summary Strip -->
    <div class="summary-strip">
      <div class="item"><span>Total Komputer:</span> <span class="val">'.$total.'</span></div>
      <div class="item"><span>Selesai:</span> <span class="val text-success">'.$done.' ('.$percent.'%)</span></div>
      <div class="item"><span>Belum Maintenance:</span> <span class="val text-warning">'.$pending.'</span></div>
      <div class="item"><span>Temuan Kerusakan:</span> <span class="val text-danger">'.$findingsCount.'</span></div>
    </div>

    <!-- Single Unified High-Density Table -->
    <table class="table-compact">
      <thead>
        <tr>
          <th class="col-center" style="width: 25px;">No</th>
          <th class="col-nowrap" style="width: 95px;">Kode Inventaris</th>
          <th>Perangkat (Merk & Tipe)</th>
          <th>Pengguna / Pemilik</th>
          <th>Cabang / Divisi</th>
          <th class="col-nowrap col-center" style="width: 110px;">Waktu Maintenance</th>
          <th class="col-nowrap" style="width: 85px;">Teknisi</th>
          <th class="col-nowrap col-center" style="width: 85px;">Status</th>
        </tr>
      </thead>
      <tbody>
        '.$trs.'
      </tbody>
    </table>

    <!-- Minimalist Signature Block -->
    <div class="signature-strip">
      <div class="row">
        <div class="col-6 sig-box">
          <div>Dibuat Oleh,</div>
          <div class="sig-line"></div>
          <div><strong>'.e(current_user_name()).'</strong> <small class="text-muted">(Teknisi IT)</small></div>
        </div>
        <div class="col-6 sig-box">
          <div>Mengetahui,</div>
          <div class="sig-line"></div>
          <div><strong>( .................................................. )</strong> <small class="text-muted">(Kepala Cabang / Manager)</small></div>
        </div>
      </div>
    </div>
  </div>
</div>';

render_page('Laporan Maintenance Bulanan', $body, $head);
