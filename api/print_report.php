<?php
require __DIR__ . '/bootstrap.php';
require_login();

$month = max(1, min(12, (int)($_GET['bulan'] ?? date('n'))));
$currentYear = (int)date('Y');
$yearParam = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
$year = ($yearParam >= 2020 && $yearParam <= 2035) ? $yearParam : $currentYear;
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));
$filterStatus = trim((string)($_GET['status'] ?? 'all'));

// Parameter Tanda Tangan Kustom
$mengetahuiJabatan = trim((string)($_GET['mengetahui_jabatan'] ?? ''));
if ($mengetahuiJabatan === '') {
    $mengetahuiJabatan = 'Kepala Cabang / IT Manager';
}
$mengetahuiNama = trim((string)($_GET['mengetahui_nama'] ?? ''));

$dibuatJabatan = trim((string)($_GET['dibuat_jabatan'] ?? ''));
if ($dibuatJabatan === '') {
    $dibuatJabatan = 'Teknisi Pelaksana IT';
}
$dibuatNama = trim((string)($_GET['dibuat_nama'] ?? ''));
if ($dibuatNama === '') {
    $dibuatNama = current_user_name();
}

$useKurung = !isset($_GET['use_kurung']) || $_GET['use_kurung'] === '1' || $_GET['use_kurung'] === 'on';

if ($mengetahuiNama === '') {
    $renderedMengetahuiNama = '( .................................................. )';
} else {
    $cleanMengetahuiNama = $mengetahuiNama;
    if ($useKurung) {
        if (!str_starts_with($cleanMengetahuiNama, '(') || !str_ends_with($cleanMengetahuiNama, ')')) {
            $cleanMengetahuiNama = '( ' . $cleanMengetahuiNama . ' )';
        }
    }
    $renderedMengetahuiNama = e($cleanMengetahuiNama);
}

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
        $client->preloadSheets(['Assets', 'Cabang', 'Divisi', 'Karyawan', 'Kategori_Aset', 'Asset_QR_Tokens', 'Maintenance_Scan', 'Maintenance_Findings']);
    }
}

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

// Hitung rincian tipe unit (Komputer / PC vs Laptop)
$countLaptop = 0;
$countPC = 0;
$countOther = 0;
$countedAssets = [];

foreach (array_merge($historyRows, $pendingRows) as $r) {
    $assetKey = !empty($r['asset_id']) ? 'id_'.$r['asset_id'] : (!empty($r['id']) ? 'id_'.$r['id'] : (!empty($r['kode_inventaris']) ? 'kode_'.$r['kode_inventaris'] : null));
    if ($assetKey && isset($countedAssets[$assetKey])) {
        continue;
    }
    if ($assetKey) {
        $countedAssets[$assetKey] = true;
    }

    $haystack = strtolower(
        ($r['kategori_nama'] ?? '') . ' ' .
        ($r['merk'] ?? '') . ' ' .
        ($r['model'] ?? '') . ' ' .
        ($r['perangkat'] ?? '') . ' ' .
        ($r['kode_inventaris'] ?? '')
    );

    if (str_contains($haystack, 'laptop') || str_contains($haystack, 'notebook') || str_contains($haystack, 'vivobook') || str_contains($haystack, 'ideapad') || str_contains($haystack, 'thinkpad')) {
        $countLaptop++;
    } elseif (str_contains($haystack, 'printer')) {
        $countOther++;
    } else {
        $countPC++;
    }
}

$deviceBreakdownParts = [];
if ($countPC > 0) $deviceBreakdownParts[] = 'PC: ' . $countPC;
if ($countLaptop > 0) $deviceBreakdownParts[] = 'Laptop: ' . $countLaptop;
if ($countOther > 0) $deviceBreakdownParts[] = 'Lainnya: ' . $countOther;

$breakdownHtml = '';
$breakdownPrint = '';
if (!empty($deviceBreakdownParts) && ($countLaptop > 0 || $countPC > 0)) {
    $breakdownHtml = '<div class="small text-muted mt-1" style="font-size: 0.72rem;">' . implode(' &bull; ', $deviceBreakdownParts) . '</div>';
    $breakdownPrint = ' (' . implode(', ', $deviceBreakdownParts) . ')';
}

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
        'is_bio' => !empty($r['biometric_verified']),
        'bio_conf' => (int)($r['biometric_confidence'] ?? 0),
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

    $trs .= '<tr>
      <td class="col-num col-center">'.$num.'</td>
      <td class="col-kode fw-bold text-primary">'.e($r['kode']).'</td>
      <td class="col-perangkat">'.e($r['perangkat']).'</td>
      <td class="col-pemilik">'.e($r['pemilik']).'</td>
      <td class="col-cabang">'.e($r['cabang_divisi']).'</td>
      <td class="col-waktu col-center">'.e($r['waktu']).'</td>
      <td class="col-teknisi">'.e($r['teknisi']).(!empty($r['is_bio']) ? ' <span style="color:#16a34a;font-weight:bold;font-size:7.5pt;" title="Terverifikasi Biometrik AI">✓ AI</span>' : '').'</td>
      <td class="col-status col-center">'.$badge.'</td>
    </tr>';
}

if (!$trs) {
    $trs = '<tr><td colspan="8" class="text-center py-4 text-muted">Tidak ada data untuk filter yang dipilih.</td></tr>';
}

// Build Appendix Rows for Findings & Solutions
$appendixTrs = '';
$fNum = 0;
foreach ($findingsRows as $f) {
    $fNum++;
    $fStatus = strtolower(trim((string)($f['repair_status'] ?? '')));
    $isResolved = in_array($fStatus, ['resolved', 'selesai', 'closed', 'ok', 'done', 'normal'], true);
    $isProgress = in_array($fStatus, ['proses', 'in progress', 'sedang perbaikan', 'dalam proses'], true);
    if ($isResolved) {
        $statusBadge = '<span class="badge text-bg-success badge-compact"><i class="bi bi-check2"></i> Selesai</span>';
    } elseif ($isProgress) {
        $statusBadge = '<span class="badge text-bg-warning badge-compact"><i class="bi bi-hourglass-split"></i> Dalam Proses</span>';
    } else {
        $statusBadge = '<span class="badge text-bg-danger badge-compact"><i class="bi bi-exclamation-triangle"></i> Perlu Perbaikan</span>';
    }
    
    $urgencyBadge = match(strtolower($f['severity'] ?? 'ringan')) {
        'tinggi', 'berat', 'kritis', 'high', 'critical' => '<span class="badge text-bg-danger badge-compact">Kritis</span>',
        'sedang', 'medium' => '<span class="badge text-bg-warning badge-compact">Sedang</span>',
        default => '<span class="badge text-bg-info text-white badge-compact">Ringan</span>'
    };

    $loc = !empty($f['divisi_nama']) && $f['divisi_nama'] !== '-'
        ? e($f['cabang_nama']).' / '.e($f['divisi_nama'])
        : e($f['cabang_nama']);

    $appendixTrs .= '<tr>
      <td class="col-num col-center">'.$fNum.'</td>
      <td class="col-kode">
        <div class="fw-bold text-primary">'.e($f['kode_inventaris']).'</div>
        <div class="small text-muted" style="font-size: 7.2pt;">'.e($f['merk_model']).'</div>
      </td>
      <td class="col-pemilik">
        <div class="fw-semibold text-dark">'.e($f['karyawan_nama']).'</div>
        <div class="small text-secondary" style="font-size: 7.2pt;">'.$loc.'</div>
      </td>
      <td class="col-temuan text-danger fw-semibold">
        <div style="line-height: 1.25;">'.e($f['finding']).'</div>
      </td>
      <td class="col-solusi text-dark">
        <div class="text-success fw-bold mb-1" style="font-size: 7.5pt;"><i class="bi bi-tools me-1"></i>Tindakan / Solusi:</div>
        <div style="line-height: 1.25;">'.e($f['action_taken']).'</div>
        '.(!empty($f['proses_perbaikan']) ? '<div class="mt-1 p-1 bg-light border small" style="font-size: 7pt; line-height: 1.2;"><strong class="text-primary">Proses:</strong> '.e($f['proses_perbaikan']).'</div>' : '').'
      </td>
      <td class="col-urgensi col-center">
        '.$urgencyBadge.'
        <div class="mt-1">'.$statusBadge.'</div>
        '.(!empty($f['created_at']) ? '<div class="small text-muted mt-1" style="font-size: 6.5pt;">'.e($f['created_at']).'</div>' : '').'
      </td>
    </tr>';
}

$appendixHtml = '';
if (!empty($findingsRows)) {
    $appendixHtml = '
    <div class="report-appendix mt-4">
      <div class="appendix-title-box d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom border-2 border-dark">
        <div class="fw-bold text-uppercase" style="font-size: 9.2pt; letter-spacing: 0.04em;">
          <i class="bi bi-paperclip me-1 text-primary"></i> LAMPIRAN: DAFTAR TEMUAN KENDALA & REKOMENDASI SOLUSI PENANGANAN
        </div>
        <div class="small fw-semibold text-danger" style="font-size: 8pt;">
          Total Temuan: '.count($findingsRows).' Perangkat
        </div>
      </div>
      <div class="table-responsive report-table-wrapper">
        <table class="table-report table-appendix">
          <thead>
            <tr>
              <th class="col-num col-center" style="width: 4%;">No</th>
              <th class="col-kode" style="width: 17%;">Kode & Perangkat</th>
              <th class="col-pemilik" style="width: 16%;">Pengguna & Lokasi</th>
              <th class="col-temuan" style="width: 27%;">Uraian Temuan Kendala / Kerusakan</th>
              <th class="col-solusi" style="width: 26%;">Tindakan Solusi / Rekomendasi Teknis</th>
              <th class="col-urgensi col-center" style="width: 10%;">Status Tindakan</th>
            </tr>
          </thead>
          <tbody>
            '.$appendixTrs.'
          </tbody>
        </table>
      </div>
    </div>';
} else {
    $appendixHtml = '
    <div class="report-appendix mt-4">
      <div class="appendix-title-box d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom border-2 border-dark">
        <div class="fw-bold text-uppercase" style="font-size: 9.2pt; letter-spacing: 0.04em;">
          <i class="bi bi-paperclip me-1 text-primary"></i> LAMPIRAN: DAFTAR TEMUAN KENDALA & REKOMENDASI SOLUSI PENANGANAN
        </div>
        <div class="small fw-semibold text-success" style="font-size: 8pt;">
          Nihil Temuan Kerusakan
        </div>
      </div>
      <div class="p-2 px-3 bg-light border border-secondary border-opacity-25 rounded text-center small text-secondary" style="font-size: 7.8pt;">
        <i class="bi bi-check-circle-fill text-success me-1"></i> Tidak ditemukan adanya kendala teknis atau kerusakan pada seluruh perangkat yang telah diperiksa pada periode ini (Kondisi 100% Normal & Beroperasi Baik).
      </div>
    </div>';
}

$head = '<style>
/* Base Screen Styling */
body {
  background: #f4f6fa;
  font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  color: #212529;
}

.report-wrapper {
  width: 100%;
  max-width: 1100px;
  margin: 0 auto;
  box-sizing: border-box;
}

.report-page {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.06);
  padding: 26px 28px;
  box-sizing: border-box;
  width: 100%;
  overflow: hidden;
}

.report-header {
  border-bottom: 2px solid #dee2e6;
  padding-bottom: 15px;
  margin-bottom: 22px;
}

.report-title {
  font-size: 1.35rem;
  font-weight: 700;
  color: #0d6efd;
  margin-bottom: 4px;
}

.report-sub {
  font-size: 0.95rem;
  color: #6c757d;
}

/* Visibility Helpers */
.print-only { display: none !important; }
.screen-only { display: flex !important; }

/* Table Screen View */
.report-table-wrapper {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  margin-bottom: 25px;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  background: #fff;
}

.table-report {
  width: 100%;
  min-width: 780px;
  border-collapse: collapse;
  margin-bottom: 0;
  font-size: 0.85rem;
}

.table-report th {
  background: #f8f9fa;
  color: #495057;
  font-weight: 600;
  border: 1px solid #dee2e6;
  padding: 9px 12px;
  vertical-align: middle;
}

.table-report td {
  border: 1px solid #dee2e6;
  padding: 8px 12px;
  vertical-align: middle;
  word-break: break-word;
}

.col-center { text-align: center !important; }
.col-nowrap { white-space: nowrap !important; }

.col-num { width: 35px; text-align: center; }
.col-kode { font-family: monospace; font-size: 0.82rem; white-space: nowrap; }
.col-waktu { white-space: nowrap; font-size: 0.82rem; text-align: center; }
.col-teknisi { white-space: nowrap; }
.col-status { text-align: center; }

/* Signature Screen View */
.signature-section {
  margin-top: 35px;
}

.sig-box {
  text-align: center;
}

.sig-line {
  width: 200px;
  margin: 50px auto 4px auto;
  border-bottom: 1px solid #333;
}

.editable-sig {
  display: inline-block;
  min-width: 80px;
  padding: 1px 6px;
  border-radius: 4px;
  border: 1px dashed #cbd5e1;
  background-color: #f8fafc;
  transition: all 0.15s ease-in-out;
  cursor: text;
}
.editable-sig:hover {
  border-color: #0d6efd;
  background-color: #eef6ff;
}
.editable-sig:focus {
  border-color: #0d6efd;
  background-color: #ffffff;
  outline: none;
  box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.25);
}

/* =========================================================
   PRINT VIEW (KHUSUS SAAT DICETAK / PDF: PORTRAIT A4)
   Ultra-Compact, High-Density, Paper-Saving
========================================================= */
@page {
  size: A4 portrait;
  margin: 6mm 6mm;
}

@media print {
  html, body {
    background: #fff !important;
    margin: 0 !important;
    padding: 0 !important;
    font-size: 7.8pt !important;
    color: #000 !important;
    width: 100% !important;
    overflow: visible !important;
  }

  .no-print, nav, header, aside, .app-sidebar, .app-topbar, #top-progress-bar {
    display: none !important;
  }

  .screen-only {
    display: none !important;
  }

  .print-only {
    display: flex !important;
  }

  .container, main.container, .app-content-container, .app-main-viewport, .app-layout-wrapper, .report-wrapper, .report-page {
    max-width: 100% !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    box-shadow: none !important;
    border-radius: 0 !important;
    border: none !important;
    overflow: visible !important;
  }

  .report-header {
    border-bottom: 1.5px solid #000 !important;
    padding-bottom: 4px !important;
    margin-bottom: 6px !important;
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
    padding: 3px 8px !important;
    margin-bottom: 6px !important;
    font-size: 7.5pt !important;
  }

  .summary-strip .val {
    font-weight: 700 !important;
  }

  .report-table-wrapper {
    overflow: visible !important;
    border: none !important;
    margin-bottom: 6px !important;
  }

  /* Ultra High-Density Table for Portrait */
  .table-report {
    width: 100% !important;
    min-width: 100% !important;
    table-layout: fixed !important;
    margin-bottom: 6px !important;
    font-size: 7.2pt !important;
    border-collapse: collapse !important;
  }

  .table-report th {
    background: #eaeaea !important;
    color: #000 !important;
    border: 1px solid #333 !important;
    padding: 2.5px 3px !important;
    font-weight: 700 !important;
    word-break: break-word !important;
    white-space: normal !important;
  }

  .table-report td {
    border: 1px solid #555 !important;
    padding: 2px 3px !important;
    line-height: 1.15 !important;
    word-break: break-word !important;
    white-space: normal !important;
  }

  /* Exact column percentages adding up to 100% on A4 Portrait */
  .col-num       { width: 4% !important; text-align: center !important; }
  .col-kode      { width: 17% !important; font-size: 6.8pt !important; word-break: break-all !important; }
  .col-perangkat { width: 23% !important; }
  .col-pemilik   { width: 14% !important; }
  .col-cabang    { width: 16% !important; }
  .col-waktu     { width: 10% !important; font-size: 6.8pt !important; text-align: center !important; }
  .col-teknisi   { width: 8% !important; font-size: 6.8pt !important; }
  .col-status    { width: 8% !important; text-align: center !important; }

  /* Appendix Column widths in Print */
  .table-appendix .col-temuan { width: 27% !important; }
  .table-appendix .col-solusi { width: 26% !important; }
  .table-appendix .col-urgensi { width: 10% !important; text-align: center !important; }
  .table-appendix th { background: #dedede !important; }

  .report-appendix {
    page-break-inside: avoid !important;
    margin-top: 10px !important;
    margin-bottom: 8px !important;
  }

  .badge-compact {
    padding: 1px 3px !important;
    font-size: 6.5pt !important;
    border-radius: 2px !important;
  }

  .signature-section {
    margin-top: 10px !important;
    page-break-inside: avoid !important;
  }

  .signature-section .text-muted {
    color: #222 !important;
  }

  .sig-line {
    width: 150px !important;
    margin: 25px auto 2px auto !important;
    border-bottom: 1px solid #000 !important;
  }

  .editable-sig {
    border: none !important;
    background: transparent !important;
    padding: 0 !important;
    box-shadow: none !important;
    outline: none !important;
    display: inline !important;
    min-width: 0 !important;
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
        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-printer me-2 text-primary"></i>Cetak Laporan Maintenance (Format Portrait)</h5>
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
        <select class="form-select form-select-sm font-monospace fw-semibold" name="tahun">'.$yearOpts.'</select>
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

      <!-- Kustomisasi Tanda Tangan Sebelum Dicetak -->
      <div class="col-12 mt-3 pt-3 border-top">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1"><i class="bi bi-pen-fill me-1"></i> Format Tanda Tangan</span>
            <span class="small fw-bold text-dark">Kustomisasi Jabatan &amp; Nama Sebelum Dicetak</span>
          </div>
          <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" id="btnResetSig" style="font-size: 0.78rem;" title="Kembalikan ke format default">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Default
          </button>
        </div>
        
        <div class="row g-2">
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-secondary mb-1">
              <i class="bi bi-briefcase text-primary me-1"></i>Jabatan (Mengetahui)
            </label>
            <input type="text" class="form-control form-control-sm" id="inputMengetahuiJabatan" name="mengetahui_jabatan" value="'.e($mengetahuiJabatan).'" placeholder="Misal: Kepala Cabang / IT Manager">
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-secondary mb-1">
              <i class="bi bi-person-check text-success me-1"></i>Nama Pejabat (Mengetahui)
            </label>
            <input type="text" class="form-control form-control-sm" id="inputMengetahuiNama" name="mengetahui_nama" value="'.e($mengetahuiNama).'" placeholder="Ketik nama pejabat (opsional)">
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-secondary mb-1">
              <i class="bi bi-person-badge text-secondary me-1"></i>Jabatan (Dibuat Oleh)
            </label>
            <input type="text" class="form-control form-control-sm" id="inputDibuatJabatan" name="dibuat_jabatan" value="'.e($dibuatJabatan).'" placeholder="Teknisi Pelaksana IT">
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-secondary mb-1">
              <i class="bi bi-person-circle text-secondary me-1"></i>Nama Teknisi (Dibuat Oleh)
            </label>
            <input type="text" class="form-control form-control-sm" id="inputDibuatNama" name="dibuat_nama" value="'.e($dibuatNama).'" placeholder="Nama teknisi / admin">
          </div>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between mt-2 pt-1 gap-2">
          <div class="form-check form-check-inline mb-0">
            <input class="form-check-input" type="checkbox" id="checkKurung" name="use_kurung" value="1"'.($useKurung ? ' checked' : '').'>
            <label class="form-check-label small text-muted" for="checkKurung" style="font-size: 0.8rem; cursor: pointer;">
              Gunakan tanda kurung <code>( ... )</code> pada nama Mengetahui
            </label>
          </div>
          <div class="small text-muted" style="font-size: 0.78rem;">
            <i class="bi bi-cursor-fill text-primary me-1"></i> <em>Bisa diketik di form ini atau langsung klik &amp; edit teks di kolom tanda tangan di bawah!</em>
          </div>
        </div>
      </div>
    </form>
  </div>

  <!-- Main Report Container -->
  <div class="report-page">
    <!-- Header Dokumen -->
    <div class="report-header d-flex justify-content-between align-items-end flex-wrap gap-2">
      <div>
        <div class="small fw-bold text-secondary text-uppercase tracking-wider">PT BPR MITRATAMA ARTHABUANA</div>
        <div class="report-title">CHECKLIST MAINTENANCE PERANGKAT IT</div>
        <div class="report-sub">
          Periode: <strong class="text-dark">'.$monthName.' '.$year.'</strong> &nbsp;|&nbsp; 
          Cabang / Lokasi: <strong class="text-dark">'.e($cabangName).'</strong>
        </div>
      </div>
      <div class="text-end small text-muted">
        <div>Tanggal Cetak: <strong>'.date('d-m-Y').'</strong> ('.date('H:i').' WITA)</div>
        <div>Modul QR Maintenance System</div>
      </div>
    </div>

    <!-- TAMPILAN LAYAR: 4 Kartu Statistik Elegan (Screen Only) -->
    <div class="row g-3 mb-4 screen-only">
      <div class="col-6 col-md-3">
        <div class="card p-3 border-0 bg-light">
          <div class="small text-muted fw-bold">TOTAL KOMPUTER &amp; LAPTOP</div>
          <div class="fs-2 fw-bold text-dark mt-1">'.$total.'</div>
          '.$breakdownHtml.'
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
      <div><span>Total Komputer &amp; Laptop:</span> <span class="val">'.$total.$breakdownPrint.'</span></div>
      <div><span>Sudah Maintenance:</span> <span class="val text-success">'.$done.' ('.$percent.'%)</span></div>
      <div><span>Belum Maintenance:</span> <span class="val text-warning">'.$pending.'</span></div>
      <div><span>Temuan Kerusakan:</span> <span class="val text-danger">'.$findingsCount.'</span></div>
    </div>

    <!-- Tabel Rekapitulasi Utama -->
    <div class="table-responsive report-table-wrapper">
      <table class="table-report">
        <thead>
          <tr>
            <th class="col-num col-center" style="width: 35px;">No</th>
            <th class="col-kode" style="width: 160px;">Kode Inventaris</th>
            <th class="col-perangkat">Perangkat (Merk & Tipe)</th>
            <th class="col-pemilik" style="width: 130px;">Pengguna / Pemilik</th>
            <th class="col-cabang" style="width: 150px;">Cabang & Divisi</th>
            <th class="col-waktu col-center" style="width: 120px;">Waktu Maintenance</th>
            <th class="col-teknisi" style="width: 105px;">Teknisi</th>
            <th class="col-status col-center" style="width: 85px;">Status</th>
          </tr>
        </thead>
        <tbody>
          '.$trs.'
        </tbody>
      </table>
    </div>

    <!-- LAMPIRAN DAFTAR TEMUAN & SOLUSI DI BAWAH TABEL -->
    '.$appendixHtml.'

    <!-- Bagian Tanda Tangan -->
    <div class="signature-section">
      <div class="row">
        <div class="col-6 sig-box">
          <div>Dibuat Oleh,</div>
          <div class="small text-muted"><span class="editable-sig" id="sigDibuatJabatanText" contenteditable="true" title="Klik untuk edit jabatan">'.e($dibuatJabatan).'</span></div>
          <div class="sig-line"></div>
          <div><strong><span class="editable-sig" id="sigDibuatNamaText" contenteditable="true" title="Klik untuk edit nama">'.e($dibuatNama).'</span></strong></div>
        </div>
        <div class="col-6 sig-box">
          <div>Mengetahui / Menyetujui,</div>
          <div class="small text-muted"><span class="editable-sig" id="sigMengetahuiJabatanText" contenteditable="true" title="Klik untuk edit jabatan">'.e($mengetahuiJabatan).'</span></div>
          <div class="sig-line"></div>
          <div><strong><span class="editable-sig" id="sigMengetahuiNamaText" contenteditable="true" title="Klik untuk edit nama">'.$renderedMengetahuiNama.'</span></strong></div>
        </div>
      </div>
    </div>
  </div>
</div>';

$script = '<script>
(function() {
  var inputMengetahuiJabatan = document.getElementById("inputMengetahuiJabatan");
  var inputMengetahuiNama    = document.getElementById("inputMengetahuiNama");
  var inputDibuatJabatan     = document.getElementById("inputDibuatJabatan");
  var inputDibuatNama        = document.getElementById("inputDibuatNama");
  var checkKurung            = document.getElementById("checkKurung");
  var btnResetSig            = document.getElementById("btnResetSig");

  var sigMengetahuiJabatanText = document.getElementById("sigMengetahuiJabatanText");
  var sigMengetahuiNamaText    = document.getElementById("sigMengetahuiNamaText");
  var sigDibuatJabatanText     = document.getElementById("sigDibuatJabatanText");
  var sigDibuatNamaText        = document.getElementById("sigDibuatNamaText");

  var DOTS = "( .................................................. )";
  var defaultMengetahuiJabatan = "Kepala Cabang / IT Manager";
  var defaultMengetahuiNama = "";
  var defaultDibuatJabatan = "Teknisi Pelaksana IT";
  var defaultDibuatNama = ' . json_encode($dibuatNama, JSON_UNESCAPED_UNICODE) . ';

  function formatMengetahuiNama(rawNama, useKurung) {
    var nama = (rawNama || "").trim();
    if (!nama || nama === DOTS) {
      return DOTS;
    }
    if (nama.startsWith("(") && nama.endsWith(")")) {
      nama = nama.slice(1, -1).trim();
    }
    if (!nama) return DOTS;
    return useKurung ? "( " + nama + " )" : nama;
  }

  function getCleanNama(formattedNama) {
    var s = (formattedNama || "").trim();
    if (!s || s === DOTS) return "";
    if (s.startsWith("(") && s.endsWith(")")) {
      s = s.slice(1, -1).trim();
    }
    return (s === DOTS) ? "" : s;
  }

  // Restore from localStorage if URL didn\'t specify
  var urlParams = new URLSearchParams(window.location.search);
  var hasUrlMengetahui = urlParams.has("mengetahui_jabatan") || urlParams.has("mengetahui_nama");

  if (!hasUrlMengetahui) {
    var savedMJ = localStorage.getItem("qr_report_sig_mengetahui_jabatan");
    var savedMN = localStorage.getItem("qr_report_sig_mengetahui_nama");
    var savedDJ = localStorage.getItem("qr_report_sig_dibuat_jabatan");
    var savedDN = localStorage.getItem("qr_report_sig_dibuat_nama");
    var savedK  = localStorage.getItem("qr_report_sig_kurung");

    if (savedMJ !== null && savedMJ !== "") {
      inputMengetahuiJabatan.value = savedMJ;
      sigMengetahuiJabatanText.textContent = savedMJ;
    }
    if (savedMN !== null && savedMN !== "") {
      inputMengetahuiNama.value = savedMN;
    }
    if (savedDJ !== null && savedDJ !== "") {
      inputDibuatJabatan.value = savedDJ;
      sigDibuatJabatanText.textContent = savedDJ;
    }
    if (savedDN !== null && savedDN !== "") {
      inputDibuatNama.value = savedDN;
      sigDibuatNamaText.textContent = savedDN;
    }
    if (savedK !== null) {
      checkKurung.checked = (savedK === "1");
    }

    sigMengetahuiNamaText.textContent = formatMengetahuiNama(inputMengetahuiNama.value, checkKurung.checked);
  }

  function saveState() {
    localStorage.setItem("qr_report_sig_mengetahui_jabatan", inputMengetahuiJabatan.value.trim());
    localStorage.setItem("qr_report_sig_mengetahui_nama", inputMengetahuiNama.value.trim());
    localStorage.setItem("qr_report_sig_dibuat_jabatan", inputDibuatJabatan.value.trim());
    localStorage.setItem("qr_report_sig_dibuat_nama", inputDibuatNama.value.trim());
    localStorage.setItem("qr_report_sig_kurung", checkKurung.checked ? "1" : "0");
  }

  // Input -> Signature sync
  inputMengetahuiJabatan.addEventListener("input", function() {
    sigMengetahuiJabatanText.textContent = this.value.trim() || defaultMengetahuiJabatan;
    saveState();
  });

  inputMengetahuiNama.addEventListener("input", function() {
    sigMengetahuiNamaText.textContent = formatMengetahuiNama(this.value, checkKurung.checked);
    saveState();
  });

  inputDibuatJabatan.addEventListener("input", function() {
    sigDibuatJabatanText.textContent = this.value.trim() || defaultDibuatJabatan;
    saveState();
  });

  inputDibuatNama.addEventListener("input", function() {
    sigDibuatNamaText.textContent = this.value.trim() || defaultDibuatNama;
    saveState();
  });

  checkKurung.addEventListener("change", function() {
    sigMengetahuiNamaText.textContent = formatMengetahuiNama(inputMengetahuiNama.value, this.checked);
    saveState();
  });

  // Inline Signature -> Input sync
  sigMengetahuiJabatanText.addEventListener("input", function() {
    inputMengetahuiJabatan.value = this.textContent.trim();
    saveState();
  });

  sigMengetahuiNamaText.addEventListener("focus", function() {
    var cur = this.textContent.trim();
    if (cur === DOTS) {
      this.textContent = "";
    } else {
      var range = document.createRange();
      range.selectNodeContents(this);
      var sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(range);
    }
  });

  sigMengetahuiNamaText.addEventListener("input", function() {
    var raw = this.textContent;
    inputMengetahuiNama.value = getCleanNama(raw);
    saveState();
  });

  sigMengetahuiNamaText.addEventListener("blur", function() {
    var raw = this.textContent.trim();
    var clean = getCleanNama(raw);
    inputMengetahuiNama.value = clean;
    this.textContent = formatMengetahuiNama(clean, checkKurung.checked);
    saveState();
  });

  sigDibuatJabatanText.addEventListener("input", function() {
    inputDibuatJabatan.value = this.textContent.trim();
    saveState();
  });

  sigDibuatNamaText.addEventListener("input", function() {
    inputDibuatNama.value = this.textContent.trim();
    saveState();
  });

  // Prevent Enter newline in single-line editable signatures
  document.querySelectorAll(".editable-sig").forEach(function(el) {
    el.addEventListener("keydown", function(e) {
      if (e.key === "Enter") {
        e.preventDefault();
        this.blur();
      }
    });
  });

  // Reset Button
  btnResetSig.addEventListener("click", function() {
    inputMengetahuiJabatan.value = defaultMengetahuiJabatan;
    inputMengetahuiNama.value    = defaultMengetahuiNama;
    inputDibuatJabatan.value     = defaultDibuatJabatan;
    inputDibuatNama.value        = defaultDibuatNama;
    checkKurung.checked          = true;

    sigMengetahuiJabatanText.textContent = defaultMengetahuiJabatan;
    sigMengetahuiNamaText.textContent    = DOTS;
    sigDibuatJabatanText.textContent     = defaultDibuatJabatan;
    sigDibuatNamaText.textContent        = defaultDibuatNama;

    localStorage.removeItem("qr_report_sig_mengetahui_jabatan");
    localStorage.removeItem("qr_report_sig_mengetahui_nama");
    localStorage.removeItem("qr_report_sig_dibuat_jabatan");
    localStorage.removeItem("qr_report_sig_dibuat_nama");
    localStorage.removeItem("qr_report_sig_kurung");
  });

  window.addEventListener("beforeprint", function() {
    if (document.activeElement && document.activeElement.blur) {
      document.activeElement.blur();
    }
  });
})();
</script>';

render_page('Laporan Maintenance Bulanan', $body, $head, $script);
