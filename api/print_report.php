<?php
require __DIR__ . '/bootstrap.php';
require_login();

$month = max(1, min(12, (int)($_GET['bulan'] ?? date('n'))));
$currentYear = (int)date('Y');
$yearParam = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
$year = ($yearParam >= 2020 && $yearParam <= 2035) ? $yearParam : $currentYear;
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));
$filterStatus = trim((string)($_GET['status'] ?? 'all'));

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

$data = get_dashboard_data($month, $year, $cabangId);
$historyRows = get_history_rows($month, $year, $cabangId);
$findingsRows = get_findings_report($month, $year, $cabangId);

$total = (int)($data['total'] ?? 0);
$done = (int)($data['done'] ?? 0);
$findingsCount = (int)($data['findings'] ?? count($findingsRows));
$pendingRows = $data['pendingRows'] ?? [];
$cabangs = get_cabang_list();

$cabangName = 'Semua Kantor Cabang';
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

// Mapping Map Temuan per Asset ID / Kode Inventaris
$findingMap = [];
foreach ($findingsRows as $f) {
    $fKode = $f['kode_inventaris'] ?? '';
    if ($fKode !== '') {
        $findingMap[$fKode] = $f;
    }
}

// Clean duplicate repeated words in titles like "ASUS AIO AIO ASUS ..."
function clean_report_device_title(string $rawTitle): string {
    $rawTitle = trim($rawTitle);
    if ($rawTitle === '') return '-';
    $words = preg_split('/\s+/', $rawTitle);
    $deduped = [];
    $prevLower = '';
    foreach ($words as $w) {
        $lower = strtolower($w);
        if ($lower !== $prevLower) {
            $deduped[] = $w;
            $prevLower = $lower;
        }
    }
    return implode(' ', $deduped);
}

// Unified Rows Construction
$allRows = [];
foreach ($historyRows as $r) {
    $kode = $r['kode_inventaris'] ?? '-';
    $fData = $findingMap[$kode] ?? null;
    
    // Perangkat Name
    $devRaw = trim(($r['kategori_nama'] ?? '').' '.($r['merk'] ?? '').' '.($r['model'] ?? ''));
    if ($devRaw === '' || $devRaw === '-') {
        $devRaw = trim(($r['merk'] ?? '').' '.($r['model'] ?? ''));
    }
    $devTitle = clean_report_device_title($devRaw);
    
    // Cabang & Divisi
    $cName = $r['cabang_nama'] ?? '-';
    $dName = $r['divisi_nama'] ?? '';
    $locDisplay = ($dName !== '' && $dName !== '-') ? "{$cName} &bull; <span class=\"text-muted\">{$dName}</span>" : $cName;

    $waktuFormatted = '-';
    if (!empty($r['maintenance_date'])) {
        $waktuFormatted = date('d/m/y', strtotime($r['maintenance_date']));
        if (!empty($r['maintenance_time'])) {
            $waktuFormatted .= ' ' . substr((string)$r['maintenance_time'], 0, 5);
        }
    }

    $allRows[] = [
        'is_done' => true,
        'status_type' => ($r['status'] ?? '') === 'Temuan' ? 'temuan' : 'selesai',
        'waktu' => $waktuFormatted,
        'kode' => $kode,
        'perangkat' => $devTitle,
        'pemilik' => $r['karyawan_nama'] ?? '-',
        'cabang_divisi' => $locDisplay,
        'teknisi' => $r['teknisi_nama'] ?? $r['technician_name'] ?? 'Teknisi IT',
        'is_bio' => !empty($r['biometric_verified']),
        'bio_conf' => (int)($r['biometric_confidence'] ?? 0),
        'status_label' => ($r['status'] ?? '') === 'Temuan' ? 'Temuan' : 'Selesai',
        'finding_note' => $fData ? ($fData['finding'] ?? '') : ($r['findings'] ?? ''),
    ];
}

foreach ($pendingRows as $r) {
    $kode = $r['kode_inventaris'] ?? '-';
    $cName = $r['cabang_nama'] ?? '-';
    $dName = $r['divisi_nama'] ?? '';
    $locDisplay = ($dName !== '' && $dName !== '-') ? "{$cName} &bull; <span class=\"text-muted\">{$dName}</span>" : $cName;

    $allRows[] = [
        'is_done' => false,
        'status_type' => 'belum',
        'waktu' => '-',
        'kode' => $kode,
        'perangkat' => clean_report_device_title(asset_title($r)),
        'pemilik' => $r['karyawan_nama'] ?? '-',
        'cabang_divisi' => $locDisplay,
        'teknisi' => '-',
        'is_bio' => false,
        'bio_conf' => 0,
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
        'selesai' => '<span class="status-pill status-selesai"><i class="bi bi-check-circle-fill"></i> Selesai</span>',
        'temuan' => '<span class="status-pill status-temuan"><i class="bi bi-exclamation-triangle-fill"></i> Temuan</span>',
        default => '<span class="status-pill status-belum"><i class="bi bi-clock-history"></i> Belum</span>'
    };

    $noteHtml = '';
    if (!empty($r['finding_note'])) {
        $noteHtml = '<div class="finding-tag"><i class="bi bi-info-circle"></i> '.e($r['finding_note']).'</div>';
    }

    $trs .= '<tr>
      <td class="col-num">'.$num.'</td>
      <td class="col-kode"><span class="kode-tag">'.e($r['kode']).'</span></td>
      <td class="col-perangkat"><strong>'.e($r['perangkat']).'</strong></td>
      <td class="col-pemilik">'.e($r['pemilik']).'</td>
      <td class="col-lokasi">'.$r['cabang_divisi'].'</td>
      <td class="col-waktu">'.e($r['waktu']).'</td>
      <td class="col-teknisi">'.e($r['teknisi']).(!empty($r['is_bio']) ? ' <span class="ai-verify" title="Verifikasi AI Biometrik">&#10003; AI</span>' : '').'</td>
      <td class="col-status">'.$badge.$noteHtml.'</td>
    </tr>';
}

if (!$trs) {
    $trs = '<tr><td colspan="8" class="empty-state">Tidak ada data aset untuk periode dan filter yang dipilih.</td></tr>';
}

$logoUrl = app_logo_url();

$head = '<style>
/* Base Screen Styling */
:root {
  --bank-navy: #0b2545;
  --bank-blue: #134074;
  --bank-gold: #c69214;
  --bank-gold-light: #fef9e7;
  --bank-slate: #475569;
  --bank-border: #cbd5e1;
  --bank-bg-table: #f8fafc;
}

body {
  background: #f1f5f9;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  color: #1e293b;
  -webkit-font-smoothing: antialiased;
}

.report-wrapper {
  max-width: 1140px;
  margin: 1.5rem auto 3rem auto;
  padding: 0 1rem;
}

.report-page {
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.07);
  padding: 36px 42px;
  position: relative;
}

/* KOP SURAT RESMI BANK */
.kop-surat {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 16px;
  gap: 20px;
}

.kop-logo-wrapper {
  flex-shrink: 0;
}

.kop-logo {
  height: 58px;
  width: auto;
  max-width: 220px;
  object-fit: contain;
}

.kop-info {
  flex-grow: 1;
  text-align: center;
}

.kop-bank-name {
  font-size: 15pt;
  font-weight: 800;
  letter-spacing: 0.5px;
  color: var(--bank-navy);
  margin-bottom: 2px;
  text-transform: uppercase;
}

.kop-doc-title {
  font-size: 12pt;
  font-weight: 700;
  color: var(--bank-blue);
  letter-spacing: 0.3px;
  margin-bottom: 4px;
}

.kop-sub {
  font-size: 8.5pt;
  color: var(--bank-slate);
}

.kop-sub strong {
  color: #0f172a;
}

.kop-meta {
  flex-shrink: 0;
  text-align: right;
  font-size: 7.5pt;
  color: #64748b;
  line-height: 1.4;
  border-left: 2px solid #e2e8f0;
  padding-left: 12px;
}

/* Double Accent Corporate Divider */
.kop-divider {
  height: 3px;
  background: var(--bank-navy);
  margin-top: 4px;
  margin-bottom: 2px;
  border-radius: 2px;
}

.kop-divider-gold {
  height: 1.5px;
  background: var(--bank-gold);
  margin-bottom: 16px;
  border-radius: 2px;
}

/* METRIC SCORECARDS (Corporate Executive Style) */
.scorecard-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 20px;
}

.scorecard-box {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 10px 14px;
  position: relative;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.scorecard-box::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  bottom: 0;
  width: 4px;
}

.scorecard-box.sc-total::before { background: #3b82f6; }
.scorecard-box.sc-done::before { background: #10b981; }
.scorecard-box.sc-pending::before { background: #f59e0b; }
.scorecard-box.sc-finding::before { background: #ef4444; }

.sc-label {
  font-size: 7.5pt;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 2px;
}

.sc-value {
  font-size: 18pt;
  font-weight: 800;
  line-height: 1.1;
  color: #0f172a;
}

.sc-sub {
  font-size: 7.5pt;
  color: #64748b;
  margin-top: 3px;
}

/* TABLE STYLING */
.table-report {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 24px;
  font-size: 8.5pt;
}

.table-report th {
  background: var(--bank-navy);
  color: #ffffff;
  font-weight: 700;
  font-size: 8pt;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  padding: 8px 8px;
  border: 1px solid #1e3a8a;
  text-align: left;
  vertical-align: middle;
}

.table-report th.col-num,
.table-report th.col-waktu,
.table-report th.col-status {
  text-align: center;
}

.table-report td {
  padding: 6px 8px;
  border: 1px solid #cbd5e1;
  vertical-align: middle;
  line-height: 1.25;
}

.table-report tbody tr:nth-child(even) {
  background-color: var(--bank-bg-table);
}

.table-report tbody tr:hover {
  background-color: #f1f5f9;
}

.col-num {
  width: 28px;
  text-align: center;
  font-weight: 600;
  color: #64748b;
}

.col-kode {
  width: 100px;
  white-space: nowrap;
}

.kode-tag {
  display: inline-block;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-weight: 700;
  color: #0f172a;
  background: #e2e8f0;
  padding: 1px 5px;
  border-radius: 4px;
  font-size: 8pt;
  border: 1px solid #cbd5e1;
}

.col-perangkat {
  font-size: 8.5pt;
  color: #0f172a;
}

.col-pemilik {
  font-size: 8.5pt;
  color: #334155;
  white-space: nowrap;
}

.col-lokasi {
  font-size: 8pt;
  color: #475569;
}

.col-waktu {
  width: 95px;
  text-align: center;
  white-space: nowrap;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 7.5pt;
  color: #334155;
}

.col-teknisi {
  width: 80px;
  white-space: nowrap;
  font-size: 8pt;
  color: #334155;
}

.ai-verify {
  color: #10b981;
  font-weight: 800;
  font-size: 7pt;
  background: #ecfdf5;
  padding: 1px 3px;
  border-radius: 3px;
  border: 1px solid #a7f3d0;
}

.col-status {
  width: 80px;
  text-align: center;
  white-space: nowrap;
}

.status-pill {
  display: inline-block;
  padding: 2px 7px;
  border-radius: 12px;
  font-size: 7pt;
  font-weight: 700;
  letter-spacing: 0.2px;
  text-transform: uppercase;
}

.status-selesai {
  background: #dcfce7;
  color: #15803d;
  border: 1px solid #bbf7d0;
}

.status-temuan {
  background: #fee2e2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

.status-belum {
  background: #fef3c7;
  color: #b45309;
  border: 1px solid #fde68a;
}

.finding-tag {
  font-size: 7pt;
  color: #b91c1c;
  background: #fff1f2;
  border: 1px solid #fecdd3;
  border-radius: 4px;
  padding: 2px 5px;
  margin-top: 3px;
  text-align: left;
  line-height: 1.15;
}

.empty-state {
  text-align: center;
  padding: 24px !important;
  color: #64748b;
  font-style: italic;
}

/* SIGNATURE BLOCK RESMI */
.signature-block {
  margin-top: 24px;
  page-break-inside: avoid;
}

.sig-date {
  text-align: right;
  font-size: 8.5pt;
  color: #334155;
  margin-bottom: 12px;
}

.sig-cards {
  display: flex;
  justify-content: space-between;
  gap: 24px;
}

.sig-card {
  flex: 1;
  text-align: center;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 12px 14px 10px 14px;
}

.sig-title {
  font-size: 8pt;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  color: var(--bank-navy);
}

.sig-role {
  font-size: 7.5pt;
  color: #64748b;
}

.sig-space {
  height: 52px;
}

.sig-line {
  border-bottom: 1px solid #0f172a;
  width: 80%;
  margin: 0 auto 4px auto;
}

.sig-name {
  font-size: 8.5pt;
  font-weight: 700;
  color: #0f172a;
}

.doc-footer-note {
  margin-top: 14px;
  padding-top: 8px;
  border-top: 1px dashed #cbd5e1;
  font-size: 7pt;
  color: #94a3b8;
  display: flex;
  justify-content: space-between;
}

/* =========================================================
   PRINT ENGINE OPTIMIZATION (PORTRAIT A4 DENSITY)
   Preserves crisp fonts, rich badge colors & compact layout
========================================================= */
@page {
  size: A4 portrait;
  margin: 6mm 7mm 7mm 7mm;
}

@media print {
  body {
    background: #ffffff !important;
    margin: 0 !important;
    padding: 0 !important;
    color: #000000 !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  .no-print, nav, header {
    display: none !important;
  }

  .report-wrapper, .report-page {
    max-width: 100% !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    box-shadow: none !important;
    border-radius: 0 !important;
  }

  .kop-surat {
    padding-bottom: 8px !important;
    gap: 12px !important;
  }

  .kop-logo {
    height: 48px !important;
  }

  .kop-bank-name {
    font-size: 13pt !important;
  }

  .kop-doc-title {
    font-size: 10.5pt !important;
  }

  .kop-sub {
    font-size: 7.5pt !important;
  }

  .kop-meta {
    font-size: 6.5pt !important;
    padding-left: 8px !important;
  }

  .kop-divider {
    height: 2.5px !important;
    background: #0b2545 !important;
    margin-top: 2px !important;
    margin-bottom: 1.5px !important;
  }

  .kop-divider-gold {
    height: 1.2px !important;
    background: #c69214 !important;
    margin-bottom: 8px !important;
  }

  /* Compact scorecards during print */
  .scorecard-grid {
    gap: 6px !important;
    margin-bottom: 10px !important;
  }

  .scorecard-box {
    padding: 5px 8px !important;
    border: 1px solid #94a3b8 !important;
  }

  .sc-label {
    font-size: 6.5pt !important;
    margin-bottom: 1px !important;
  }

  .sc-value {
    font-size: 13pt !important;
  }

  .sc-sub {
    font-size: 6.5pt !important;
    margin-top: 1px !important;
  }

  /* Dense portrait table */
  .table-report {
    font-size: 7.5pt !important;
    margin-bottom: 10px !important;
  }

  .table-report th {
    background: #0b2545 !important;
    color: #ffffff !important;
    border: 1px solid #000000 !important;
    padding: 4px 4px !important;
    font-size: 7pt !important;
  }

  .table-report td {
    border: 1px solid #94a3b8 !important;
    padding: 2.5px 4px !important;
    line-height: 1.15 !important;
  }

  .col-num {
    width: 22px !important;
  }

  .col-kode {
    width: 82px !important;
  }

  .kode-tag {
    font-size: 7pt !important;
    padding: 0 3px !important;
    border: 0.5px solid #64748b !important;
    background: #f1f5f9 !important;
  }

  .col-waktu {
    width: 80px !important;
    font-size: 6.8pt !important;
  }

  .col-teknisi {
    width: 65px !important;
    font-size: 7pt !important;
  }

  .col-status {
    width: 68px !important;
  }

  .status-pill {
    padding: 1px 4px !important;
    font-size: 6.5pt !important;
  }

  .status-selesai {
    background: #dcfce7 !important;
    color: #15803d !important;
    border: 0.5px solid #15803d !important;
  }

  .status-temuan {
    background: #fee2e2 !important;
    color: #b91c1c !important;
    border: 0.5px solid #b91c1c !important;
  }

  .status-belum {
    background: #fef3c7 !important;
    color: #b45309 !important;
    border: 0.5px solid #b45309 !important;
  }

  .finding-tag {
    font-size: 6.2pt !important;
    padding: 1px 3px !important;
    margin-top: 1.5px !important;
  }

  .signature-block {
    margin-top: 10px !important;
    page-break-inside: avoid !important;
  }

  .sig-cards {
    gap: 12px !important;
  }

  .sig-card {
    padding: 6px 8px !important;
    border: 1px solid #475569 !important;
  }

  .sig-space {
    height: 38px !important;
  }

  .sig-line {
    width: 70% !important;
    border-bottom: 1px solid #000000 !important;
  }

  tr {
    page-break-inside: avoid !important;
  }
}
</style>';

$body = '
<div class="report-wrapper">
  <!-- Interactive Controls Bar (Hidden during Print) -->
  <div class="no-print mb-4 p-3 bg-white rounded-3 shadow-sm border">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom pb-3 mb-3">
      <div class="d-flex align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'"><i class="bi bi-arrow-left"></i> Dashboard</a>
        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-printer me-2 text-primary"></i>Cetak Laporan Maintenance IT (Format Portrait)</h5>
      </div>
      <button class="btn btn-primary fw-semibold px-4 shadow-sm" onclick="window.print()"><i class="bi bi-printer-fill me-1"></i> Cetak / Simpan PDF</button>
    </div>

    <form method="get" class="row g-2 align-items-end">
      <div class="col-6 col-md-2">
        <label class="form-label small fw-semibold text-muted mb-1">Bulan</label>
        <select class="form-select form-select-sm" name="bulan">';
for ($m=1;$m<=12;$m++) {
    $body .= '<option value="'.$m.'"'.($m===$month?' selected':'').'>'.$monthNames[$m].'</option>';
}
$body .= '</select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small fw-semibold text-muted mb-1">Tahun</label>
        <select class="form-select form-select-sm font-monospace fw-semibold" name="tahun">'.$yearOpts.'</select>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold text-muted mb-1">Kantor Cabang</label>
        <select class="form-select form-select-sm" name="cabang">
          <option value="0">Semua Kantor Cabang</option>
          '.$cabangOpts.'
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold text-muted mb-1">Filter Status</label>
        <select class="form-select form-select-sm" name="status">
          <option value="all"'.($filterStatus==='all'?' selected':'').'>Semua Status (Lengkap)</option>
          <option value="selesai"'.($filterStatus==='selesai'?' selected':'').'>Hanya Selesai</option>
          <option value="temuan"'.($filterStatus==='temuan'?' selected':'').'>Hanya Temuan Kerusakan</option>
          <option value="belum"'.($filterStatus==='belum'?' selected':'').'>Hanya Belum Dikerjakan</option>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-outline-primary btn-sm w-100 fw-semibold"><i class="bi bi-filter me-1"></i> Tampilkan</button>
      </div>
    </form>
  </div>

  <!-- Main Report Container -->
  <div class="report-page">
    
    <!-- Kop Surat Resmi Bank -->
    <div class="kop-surat">
      <div class="kop-logo-wrapper">
        <img src="'.e($logoUrl).'" alt="Bank Logo" class="kop-logo">
      </div>
      <div class="kop-info">
        <div class="kop-bank-name">PT BPR MITRATAMA ARTHABUANA</div>
        <div class="kop-doc-title">CHECKLIST MAINTENANCE PERANGKAT IT</div>
        <div class="kop-sub">
          Periode: <strong>'.$monthName.' '.$year.'</strong> &nbsp;&bull;&nbsp; 
          Lokasi: <strong>'.e($cabangName).'</strong>
        </div>
      </div>
      <div class="kop-meta">
        <div>Dicetak: <strong>'.date('d/m/Y H:i').'</strong></div>
        <div>Sistem: <strong>QR Maintenance</strong></div>
        <div>Dokumen: <strong>IT-MNT-'.str_pad((string)$month, 2, '0', STR_PAD_LEFT).'-'.$year.'</strong></div>
      </div>
    </div>

    <!-- Double Corporate Line Divider -->
    <div class="kop-divider"></div>
    <div class="kop-divider-gold"></div>

    <!-- Executive Scorecard Strip (Always Visible: Screen & Print) -->
    <div class="scorecard-grid">
      <div class="scorecard-box sc-total">
        <div class="sc-label">Total Aset IT</div>
        <div class="sc-value">'.$total.'</div>
        <div class="sc-sub">Unit Terdaftar</div>
      </div>
      <div class="scorecard-box sc-done">
        <div class="sc-label">Sudah Maintenance</div>
        <div class="sc-value" style="color: #15803d;">'.$done.'</div>
        <div class="sc-sub"><strong>'.$percent.'%</strong> Dari Target</div>
      </div>
      <div class="scorecard-box sc-pending">
        <div class="sc-label">Belum Maintenance</div>
        <div class="sc-value" style="color: #b45309;">'.$pending.'</div>
        <div class="sc-sub">Menunggu Pengecekan</div>
      </div>
      <div class="scorecard-box sc-finding">
        <div class="sc-label">Temuan Masalah</div>
        <div class="sc-value" style="color: #b91c1c;">'.$findingsCount.'</div>
        <div class="sc-sub">Perlu Tindak Lanjut</div>
      </div>
    </div>

    <!-- Tabel Rekapitulasi Maintenance -->
    <table class="table-report">
      <thead>
        <tr>
          <th class="col-num">No</th>
          <th class="col-kode">Kode Inventaris</th>
          <th>Perangkat (Merk & Model)</th>
          <th>Pengguna / User</th>
          <th>Cabang & Divisi</th>
          <th class="col-waktu">Waktu Cek</th>
          <th class="col-teknisi">Teknisi</th>
          <th class="col-status">Status</th>
        </tr>
      </thead>
      <tbody>
        '.$trs.'
      </tbody>
    </table>

    <!-- Bagian Tanda Tangan Resmi Bank -->
    <div class="signature-block">
      <div class="sig-date">
        '.e($cabangName !== 'Semua Kantor Cabang' ? $cabangName : 'Denpasar').', '.format_id_date(date('Y-m-d')).'
      </div>
      <div class="sig-cards">
        <div class="sig-card">
          <div class="sig-title">Dibuat & Dilaksanakan Oleh</div>
          <div class="sig-role">Teknisi Pelaksana IT</div>
          <div class="sig-space"></div>
          <div class="sig-line"></div>
          <div class="sig-name">'.e(current_user_name()).'</div>
        </div>
        <div class="sig-card">
          <div class="sig-title">Mengetahui & Menyetujui</div>
          <div class="sig-role">Kepala Cabang / IT Manager</div>
          <div class="sig-space"></div>
          <div class="sig-line"></div>
          <div class="sig-name">( &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; )</div>
        </div>
      </div>
      <div class="doc-footer-note">
        <span>* Laporan ini dicetak secara otomatis melalui Sistem QR Maintenance IT PT BPR Mitratama Arthabuana.</span>
        <span>Halaman 1 / 1</span>
      </div>
    </div>

  </div>
</div>';

render_page('Checklist Maintenance Perangkat IT', $body, $head);
