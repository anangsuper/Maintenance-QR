<?php
// Histori & Yearly Card Matrix & Complaints
$historyList = get_asset_maintenance_history($assetId);
$cardMatrix = get_asset_yearly_card_matrix($assetId, $year);
$loggedIn = is_logged_in();
$complaints = get_asset_complaints($assetId);
$pendingComplaintsCount = 0;
foreach ($complaints as $c) {
    if (($c['status'] ?? '') === 'Menunggu Teknisi') {
        $pendingComplaintsCount++;
    }
}

// Status Bulan Berjalan
if ($currentMonthLog) {
    $cDate = $currentMonthLog['maintenance_date'] ?? date('Y-m-d');
    $cTech = $currentMonthLog['technician_name'] ?? 'Teknisi';
    $cStatus = $currentMonthLog['status'] ?? 'Selesai';
    $cLogId = (int)($currentMonthLog['id'] ?? 0);
    $cFindings = $currentMonthLog['findings'] ?? '';
    $cRecom = $currentMonthLog['recommendation'] ?? '';

    $hasActiveIssue = ($pendingFinding || in_array(strtolower($cStatus), ['temuan', 'perlu perbaikan', 'perlu tindak lanjut', 'proses'], true));
    $cardBgStyle = $hasActiveIssue 
        ? 'background-color: #FEF2F2; border: 1px solid #FECACA; border-left: 4px solid #DC2626;' 
        : 'background-color: #F0FDF4; border: 1px solid #BBF7D0; border-left: 4px solid #059669;';
    $cardTitleColor = $hasActiveIssue ? 'text-danger' : 'text-success';

    $btnTindakLanjut = $hasActiveIssue
        ? '<a class="btn btn-danger fw-semibold px-3 py-2" href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'tindak_lanjut'])).'"><i class="bi bi-tools me-1"></i> Tindak Lanjuti Sekarang</a>'
        : '';

    $badgeColor = ($cStatus === 'Temuan' || $cStatus === 'Perlu Perbaikan') ? 'danger' : ($cStatus === 'Proses' ? 'warning text-dark' : 'success');
    $badgeIcon = ($cStatus === 'Temuan' || $cStatus === 'Perlu Perbaikan') ? 'bi-exclamation-triangle-fill' : ($cStatus === 'Proses' ? 'bi-hourglass-split' : 'bi-check-circle-fill');

    $btnDetail = $cLogId > 0
        ? '<a class="btn btn-primary fw-semibold" href="'.e(module_url('maintenance_detail.php', ['id' => $cLogId])).'"><i class="bi bi-file-earmark-text me-1"></i> Rincian Pemeliharaan</a>'
        : '';

    $btnUlang = '<a class="btn btn-outline-secondary fw-semibold" href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'ulang'])).'"><i class="bi bi-arrow-repeat me-1"></i> Pemeliharaan Ulang</a>';

    $statusCardHtml = '
    <div class="card shadow-sm mb-4 p-3 p-md-4 rounded-3" style="'.$cardBgStyle.'">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="'.$cardTitleColor.' fw-semibold fs-6"><i class="bi bi-calendar-check me-1"></i> Status Pemeliharaan Bulan Ini:</span>
        <span class="badge bg-'.$badgeColor.' px-3 py-2 fs-6"><i class="bi '.$badgeIcon.' me-1"></i> '.e($cStatus).'</span>
      </div>
      <h5 class="fw-bold text-dark mb-1">Periode: '.$monthName.' '.$year.'</h5>
      <p class="small mb-2" style="color: #334155 !important;">Perangkat ini <strong>sudah dilakukan maintenance</strong> pada <strong>'.e(format_id_date($cDate)).'</strong> oleh <strong>'.e($cTech).'</strong>.</p>
      
      '.($cFindings !== '' ? '<div class="alert alert-danger py-2 px-3 small my-2"><strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Temuan:</strong> '.e($cFindings).'</div>' : '').'
      '.($cRecom !== '' ? '<div class="alert alert-info py-2 px-3 small my-2"><strong><i class="bi bi-lightbulb-fill me-1"></i>Rekomendasi:</strong> '.e($cRecom).'</div>' : '').'

      <div class="d-flex flex-wrap gap-2 mt-3 pt-2">
        '.$btnTindakLanjut.'
        '.$btnUlang.'
        '.$btnDetail.'
      </div>
    </div>';
} else {
    $btnStartAction = '<a class="btn btn-success btn-lg fw-semibold py-3 px-4 shadow-sm w-100" href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'start'])).'">
      <i class="bi bi-play-circle-fill me-2"></i> Mulai Pemeriksaan Checklist
    </a>
    <div class="text-center mt-2"><small class="text-muted"><i class="bi bi-check2-circle text-success me-1"></i>Cukup pilih/masukkan nama petugas saat mengisi checklist (tidak wajib login).</small></div>';

    $statusCardHtml = '
    <div class="card shadow-sm mb-4 p-3 p-md-4 rounded-3" style="background-color: #FEF2F2; border: 1px solid #FECACA; border-left: 4px solid #DC2626;">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-danger fw-semibold fs-6"><i class="bi bi-exclamation-circle me-1"></i> Status Pemeliharaan Bulan Ini:</span>
        <span class="badge bg-danger px-3 py-2 fs-6"><i class="bi bi-x-circle-fill me-1"></i> Belum Maintenance</span>
      </div>
      <h5 class="fw-bold text-dark mb-1">Periode: '.$monthName.' '.$year.'</h5>
      <p class="small mb-3" style="color: #334155 !important;">Perangkat ini belum dilakukan pemeliharaan hardware & OS untuk bulan ini.</p>
      
      '.$btnStartAction.'
    </div>';
}

$pendingAlertHtml = '';
if ($pendingFinding) {
    $pendingAlertHtml = '
    <div class="card border-0 shadow-sm mb-4 border-start border-danger border-4 p-3 p-md-4" style="background-color: #fff5f5;">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-danger fw-semibold fs-6"><i class="bi bi-exclamation-triangle-fill me-1"></i> Perangkat Membutuhkan Tindak Lanjut Teknisi</span>
        <span class="badge bg-danger px-3 py-2 fs-6"><i class="bi bi-tools me-1"></i> '.e($pendingFinding['status']).'</span>
      </div>
      <h5 class="fw-bold text-dark mb-1">Temuan Kerusakan: <span class="text-danger">"'.e($pendingFinding['finding']).'"</span></h5>
      <p class="text-secondary small mb-2">Dilaporkan pada <strong>'.e(format_id_date($pendingFinding['date'])).'</strong> oleh <strong>'.e($pendingFinding['reporter']).'</strong>.</p>
      '.(!empty($pendingFinding['recommendation']) && $pendingFinding['recommendation'] !== '-' ? '<div class="alert alert-white bg-white border py-2 px-3 small my-2 text-dark"><strong><i class="bi bi-lightbulb me-1"></i>Catatan Rekomendasi:</strong> '.e($pendingFinding['recommendation']).'</div>' : '').'
      <div class="d-flex flex-wrap gap-2 mt-3 pt-1">
        <a class="btn btn-danger fw-semibold py-2 px-4 shadow-sm" href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'tindak_lanjut'])).'">
          <i class="bi bi-tools me-2"></i> Tindak Lanjuti / Selesaikan Temuan
        </a>
        '.(!empty($pendingFinding['log_id']) ? '<a class="btn btn-outline-secondary py-2" href="'.e(module_url('maintenance_detail.php', ['id' => (int)$pendingFinding['log_id']])).'"><i class="bi bi-file-earmark-text me-1"></i> Rincian Audit</a>' : '').'
      </div>
    </div>';
}

// Build Matrix Table Rows 12 Bulan (Persis Desain Gambar Kartu Kontrol IT)
$rawUser = !empty($asset['karyawan_nama']) && $asset['karyawan_nama'] !== '-' ? $asset['karyawan_nama'] : 'Umum / Pool';
$userDisplay = $rawUser;
$divisi = !empty($asset['divisi_nama']) && $asset['divisi_nama'] !== '-' ? $asset['divisi_nama'] : '';
$userWithDiv = $divisi ? "{$userDisplay} ({$divisi})" : $userDisplay;
$ipDisplay = !empty($asset['ip_address']) ? $asset['ip_address'] : (!empty($asset['ip']) ? $asset['ip'] : '-');
$kodeInv = $asset['kode_inventaris'] ?? ('INV-IT-' . sprintf('%03d', $assetId));
$deviceTitle = asset_title($asset);
$printerDisplay = !empty($asset['printer']) && $asset['printer'] !== '-' ? $asset['printer'] : '-';
$cabangLabel = !empty($asset['cabang_nama']) && $asset['cabang_nama'] !== '-' ? $asset['cabang_nama'] : 'KPO';

$cardMatrixRows = '';
for ($m = 1; $m <= 12; $m++) {
    $row = $cardMatrix[$m];
    $dateLabel = $row['date_str'];
    $isDone = $row['is_done'];
    $paraf = $isDone ? e($row['paraf']) : '&nbsp;';
    $rowClass = ($m % 2 === 0) ? 'even-row' : 'odd-row';
    if ($isDone) $rowClass .= ' done-row';

    $cols1to9 = '';
    for ($num = 1; $num <= 9; $num++) {
        $chkVal = $row['checklists'][$num] ?? 0;
        if ($isDone) {
            $cols1to9 .= '<td class="chk-col '.($chkVal ? 'chk-yes' : 'chk-no').'">'.($chkVal ? '✓' : '-').'</td>';
        } else {
            $cols1to9 .= '<td class="chk-col">&nbsp;</td>';
        }
    }

    $cardMatrixRows .= '
    <tr class="'.$rowClass.'">
      <td class="tgl-col">'.e($dateLabel).'</td>
      '.$cols1to9.'
      <td class="paraf-col">'.$paraf.'</td>
    </tr>';
}

// Maintenance Terakhir
$lastMaintStr = !empty($historyList[0])
    ? format_id_date($historyList[0]['maintenance_date'] ?? '') . ' oleh ' . ($historyList[0]['technician_name'] ?? 'Teknisi')
    : 'Belum pernah';

$headStyle = '<style>
/* Modern Kartu Kontrol IT (Exact match to print_card.php grid6 / user screenshot) */
.mobile-card-wrapper {
  background: #ffffff;
  border: 2px solid #2E77AD;
  border-radius: 14px;
  padding: 14px 16px;
  box-shadow: 0 8px 24px rgba(46, 119, 173, 0.12);
  margin: 0 auto;
  max-width: 100%;
}
@media (max-width: 576px) {
  .mobile-card-wrapper {
    padding: 10px 8px;
    border-radius: 12px;
  }
}
.grid6-top-banner {
  background: #1D4ED8;
  color: #ffffff;
  padding: 7px 12px;
  border-radius: 6px;
  font-weight: 700;
  font-size: 0.92rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.grid6-branch-pill {
  background: #ffffff;
  color: #1A4064;
  font-size: 0.72rem;
  font-weight: 800;
  padding: 3px 10px;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
}
.grid6-info-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
  line-height: 1.4;
  margin: 8px 0;
}
.grid6-info-table td {
  padding: 3px 4px;
  vertical-align: middle;
}
.badge-lbl {
  font-size: 0.72rem;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 4px;
  display: inline-block;
  text-align: center;
  white-space: nowrap;
}
.badge-blue { background: #E6EDF5; color: #2E77AD; border: 1px solid #30B0E0; }
.badge-green { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
.badge-purple { background: #E6EDF5; color: #50C0C0; border: 1px solid #50C0C0; }
.badge-kode { background: #f1f5f9; color: #0f172a; padding: 2px 6px; border-radius: 4px; font-weight: bold; border: 1px solid #cbd5e1; }

.grid6-matrix-wrapper {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  margin-bottom: 8px;
}
.grid6-matrix-table {
  width: 100%;
  min-width: 340px;
  border-collapse: collapse;
  border: 1.5px solid #2E77AD;
  font-size: 0.82rem;
}
.grid6-matrix-table th {
  background: #2E77AD !important;
  color: #ffffff !important;
  border: 1px solid #1A4064;
  font-weight: bold;
  text-align: center;
  padding: 6px 2px;
}
.grid6-matrix-table td {
  border: 1px solid #cbd5e1;
  text-align: center;
  padding: 4px 2px;
  height: 25px;
}
.grid6-matrix-table tr.even-row { background-color: #f8fafc; }
.grid6-matrix-table tr.done-row { background-color: #f0fdf4; }
.grid6-matrix-table tr.done-row td { border-color: #A7F3D0; }
.grid6-matrix-table .tgl-col { font-weight: bold; font-family: "Courier New", monospace; font-size: 0.84rem; color: #2E77AD; width: 85px; }
.grid6-matrix-table .chk-col { font-weight: bold; font-size: 0.88rem; width: 28px; }
.grid6-matrix-table .chk-yes { color: #10B981; font-weight: 900; }
.grid6-matrix-table .chk-no { color: #94a3b8; }
.grid6-matrix-table .paraf-col { font-size: 0.78rem; font-family: "Courier New", monospace; color: #334155; min-width: 85px; }

.grid6-ket-box {
  background: #f8fafc;
  border: 1px solid #94a3b8;
  border-radius: 8px;
  padding: 8px 12px;
}
.grid6-ket-title {
  font-size: 0.74rem;
  font-weight: 800;
  color: #2E77AD;
  margin-bottom: 6px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
.grid6-ket-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 4px 8px;
}
@media (max-width: 480px) {
  .grid6-ket-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
.leg-tag {
  font-size: 0.72rem;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 4px;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 3px;
}
.leg-tag b {
  font-weight: 800;
  color: #0f172a;
}
.leg-blue { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.leg-purple { background: #faf5ff; color: #7e22ce; border: 1px solid #e9d5ff; }
.leg-teal { background: #f0fdfa; color: #0f766e; border: 1px solid #99f6e4; }
.bg-primary-subtle { background-color: #eff6ff !important; color: #1d4ed8 !important; }
.border-primary-subtle { border-color: #bfdbfe !important; }
.bg-success-subtle { background-color: #f0fdf4 !important; color: #15803d !important; }
.border-success-subtle { border-color: #bbf7d0 !important; }
.tracking-wide { letter-spacing: 0.05em; }
.shadow-xs { box-shadow: 0 1px 2px rgba(0,0,0,0.05); }

@media print {
  body { background: #fff !important; margin: 0 !important; }
  .no-print, nav, header { display: none !important; }
  .container, main.container { max-width: 100% !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
  .mobile-card-wrapper { box-shadow: none !important; margin: 0 auto !important; }
}
</style>';

$flashScanHtml = '';
if (!empty($_SESSION['flash_scan'])) {
    $flashScanHtml = '<div class="alert alert-success alert-dismissible fade show py-3 px-3 mb-3 shadow-sm rounded-3 border-success border-start border-4" role="alert">
        <div class="d-flex align-items-center">
            <i class="bi bi-check-circle-fill text-success fs-5 me-2"></i>
            <div>' . e($_SESSION['flash_scan']) . '</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
    unset($_SESSION['flash_scan']);
}

$userStatusStrip = $loggedIn
    ? '<div class="d-flex flex-wrap justify-content-between align-items-center bg-white p-2 px-3 rounded-3 shadow-sm mb-3 border gap-2">
         <span class="small fw-bold text-dark"><i class="bi bi-person-check-fill text-success me-1"></i> Status Login: <span class="text-primary">'.e(current_user_name()).'</span></span>
         <div class="d-flex gap-2">
           <a href="'.e(module_url('dashboard.php')).'" class="btn btn-sm btn-outline-primary"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
           <a href="'.e(module_url('logout.php')).'" class="btn btn-sm btn-outline-danger" title="Keluar"><i class="bi bi-box-arrow-right"></i> Keluar</a>
         </div>
       </div>'
    : '<div class="d-flex flex-wrap justify-content-between align-items-center bg-white p-2 px-3 rounded-3 shadow-sm mb-3 border gap-2">
         <span class="small text-secondary"><i class="bi bi-info-circle text-primary me-1"></i> Mode Cek Info Perangkat</span>
         <a href="'.e(module_url('login.php')).'" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-in-right me-1"></i> Login Admin</a>
       </div>';

// Build Komponen Catatan & Keluhan Karyawan
$complaintItemsHtml = '';
if (empty($complaints)) {
    $complaintItemsHtml = '
    <div class="text-center py-3 text-muted bg-light rounded-3 border border-dashed">
      <i class="bi bi-chat-heart text-secondary fs-3 d-block mb-1"></i>
      <span class="small">Belum ada catatan kendala pada perangkat ini. Semua berjalan lancar.</span>
    </div>';
} else {
    foreach ($complaints as $item) {
        $cId = (int)($item['id'] ?? 0);
        $cStatus = $item['status'] ?? 'Menunggu Teknisi';
        $isResolved = ($cStatus === 'Selesai');
        $statusBadge = $isResolved
            ? '<span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Selesai</span>'
            : '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Menunggu Teknisi</span>';
        
        $cDate = !empty($item['created_at']) ? format_id_date(substr($item['created_at'], 0, 10)) . ' ' . substr($item['created_at'], 11, 5) : '-';
        $cContact = !empty($item['contact']) ? '<span class="badge bg-light text-secondary border ms-1"><i class="bi bi-whatsapp me-1"></i>' . e($item['contact']) . '</span>' : '';
        $cCategory = !empty($item['kategori']) ? '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">' . e($item['kategori']) . '</span>' : '';
        
        $techResponseHtml = '';
        if ($isResolved && !empty($item['technician_response'])) {
            $resolvedDate = !empty($item['resolved_at']) ? format_id_date(substr($item['resolved_at'], 0, 10)) . ' ' . substr($item['resolved_at'], 11, 5) : '';
            $techResponseHtml = '
            <div class="mt-2 p-2 rounded small" style="background-color: #f0fdf4; border: 1px solid #bbf7d0;">
                <div class="fw-bold text-success mb-1"><i class="bi bi-check2-all me-1"></i>Respon / Solusi Teknisi:</div>
                <div class="text-dark">' . nl2br(e($item['technician_response'])) . '</div>
                <div class="text-muted mt-1" style="font-size: 0.75rem;">Ditindaklanjuti oleh <strong>' . e($item['resolved_by'] ?? 'Teknisi IT') . '</strong>' . ($resolvedDate ? ' pada ' . e($resolvedDate) : '') . '</div>
            </div>';
        }

        $resolveActionForm = '';
        if (!$isResolved && $loggedIn) {
            $resolveActionForm = '
            <div class="mt-2 p-2 bg-white rounded border">
                <form method="POST" action="" class="row g-2 align-items-center">
                    <input type="hidden" name="action" value="resolve_complaint">
                    <input type="hidden" name="complaint_id" value="' . $cId . '">
                    <div class="col-12 col-md-8">
                        <input type="text" name="technician_response" class="form-control form-control-sm" placeholder="Tuliskan tindakan perbaikan teknisi..." required>
                    </div>
                    <div class="col-12 col-md-4 text-end">
                        <button type="submit" class="btn btn-sm btn-success w-100 fw-semibold"><i class="bi bi-check-lg me-1"></i> Tandai Selesai</button>
                    </div>
                </form>
            </div>';
        }

        $complaintItemsHtml .= '
        <div class="p-3 mb-2 rounded-3 border bg-white shadow-xs">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-1 mb-2">
                <div class="d-flex align-items-center flex-wrap gap-1">
                    <strong class="text-dark"><i class="bi bi-person me-1 text-primary"></i>' . e($item['reporter_name'] ?? 'Karyawan') . '</strong>
                    ' . $cContact . '
                    ' . $cCategory . '
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small" style="font-size: 0.8rem;"><i class="bi bi-clock me-1"></i>' . e($cDate) . '</span>
                    ' . $statusBadge . '
                </div>
            </div>
            <div class="p-2 rounded bg-light text-dark small" style="border-left: 3px solid #3b82f6;">
                ' . nl2br(e($item['complaint'] ?? '')) . '
            </div>
            ' . $techResponseHtml . '
            ' . $resolveActionForm . '
        </div>';
    }
}

$defaultReporter = (!empty($asset['karyawan_nama']) && $asset['karyawan_nama'] !== '-') ? $asset['karyawan_nama'] : '';
$autoOpenForm = (!empty($_GET['tulis']) || !empty($_GET['complaint_form']));

$complaintCardHtml = '
<div class="card p-3 p-md-4 border-0 shadow-sm mb-4" id="catatan-karyawan">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <h5 class="fw-bold text-dark mb-0">
        <i class="bi bi-chat-left-dots-fill text-primary me-2"></i>Catatan & Keluhan Karyawan
      </h5>
      <p class="small text-secondary mb-0">Sampaikan keluhan, kerusakan hardware, kendala aplikasi, atau kebutuhan IT pada perangkat ini.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      '.($pendingComplaintsCount > 0 ? '<span class="badge bg-warning text-dark px-3 py-2"><i class="bi bi-exclamation-circle-fill me-1"></i> '.$pendingComplaintsCount.' Perlu Ditangani</span>' : '<span class="badge bg-success-subtle text-success px-3 py-2 border border-success-subtle"><i class="bi bi-check-circle me-1"></i> Normal</span>').'
      <button class="btn btn-sm btn-primary fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#formComplaintCollapse" aria-expanded="'.($autoOpenForm ? 'true' : 'false').'" aria-controls="formComplaintCollapse">
        <i class="bi bi-pencil-square me-1"></i> Tulis Catatan
      </button>
    </div>
  </div>

  <!-- Form Tambah Catatan / Keluhan -->
  <div class="collapse mb-3 '.($autoOpenForm ? 'show' : '').'" id="formComplaintCollapse">
    <div class="p-3 p-md-4 rounded-3 border bg-light">
      <h6 class="fw-bold text-dark mb-3"><i class="bi bi-send-plus text-primary me-2"></i>Kirim Catatan / Lapor Kendala ke Tim IT</h6>
      <form method="POST" action="">
        <input type="hidden" name="action" value="submit_complaint">
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label small fw-semibold text-secondary">Nama Karyawan / Pelapor <span class="text-danger">*</span></label>
            <input type="text" name="reporter_name" class="form-control form-control-sm" value="'.e($defaultReporter).'" placeholder="Nama pengguna PC" required>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label small fw-semibold text-secondary">No. WhatsApp / HP <span class="text-muted">(Opsional untuk konfirmasi)</span></label>
            <input type="text" name="contact" class="form-control form-control-sm" placeholder="Contoh: 08123456789">
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold text-secondary">Kategori Kendala</label>
            <select name="kategori" class="form-select form-select-sm">
              <option value="Komputer Lambat / Hang">Komputer Lambat / Sering Hang</option>
              <option value="Aplikasi / Software Error">Aplikasi / Software Error / Tidak Bisa Dibuka</option>
              <option value="Koneksi Jaringan / Internet">Koneksi Jaringan / Internet / Sambungan LAN-WiFi</option>
              <option value="Printer / Scanner Bermasalah">Printer / Scanner Bermasalah / Hasil Cetak Bergaris</option>
              <option value="Hardware (Keyboard / Mouse / Monitor)">Hardware (Keyboard / Mouse / Monitor Rusak)</option>
              <option value="Permintaan Akses / Pengaturan">Permintaan Akses / Pengaturan / Install Software</option>
              <option value="Lain-lain" selected>Catatan Lain-lain</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold text-secondary">Isi Catatan / Kendala yang Dialami <span class="text-danger">*</span></label>
            <textarea name="complaint" class="form-control form-control-sm" rows="3" placeholder="Tuliskan kendala yang dialami secara rinci agar teknisi dapat membawa peralatan yang sesuai..." required></textarea>
          </div>
          <div class="col-12 text-end">
            <button type="submit" class="btn btn-primary fw-semibold px-4 py-2">
              <i class="bi bi-send-fill me-1"></i> Kirim Catatan ke Tim IT
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Riwayat Catatan Keluhan Pada Perangkat Ini -->
  <div>
    <h6 class="fw-bold text-dark mb-2 small text-uppercase text-secondary tracking-wide">
      <i class="bi bi-clock-history me-1"></i> Riwayat Catatan / Keluhan Perangkat Ini:
    </h6>
    '.$complaintItemsHtml.'
  </div>
</div>';

$body = '
<div class="row justify-content-center">
  <div class="col-md-11 col-lg-10">

    <!-- Status Strip Login / Tamu -->
    '.$userStatusStrip.'
    '.$flashScanHtml.'
    '.($error ? '<div class="alert alert-danger py-2 px-3 mb-3 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Gagal Menyimpan:</strong> '.e($error).'</div>' : '').'

    <!-- Card Detail Perangkat Utama -->
    <div class="card p-3 p-md-4 border-0 shadow-sm mb-4">
      <div class="d-flex flex-wrap align-items-center justify-content-between border-bottom pb-3 mb-3 gap-2">
        <div>
          <span class="badge bg-primary px-3 py-1 mb-1">'.e($asset['kategori_nama'] ?? 'Perangkat IT').'</span>
          <h3 class="fw-bold text-dark mb-0">'.e(asset_title($asset)).'</h3>
        </div>
        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-2 border border-success">
          <i class="bi bi-check-circle-fill me-1"></i> '.e($asset['status'] ?? 'Aktif').'
        </span>
      </div>

      <!-- Detail Spesifikasi & Kepemilikan -->
      <div class="row g-3 small mb-2">
        <div class="col-6 col-md-4">
          <div class="text-secondary">Kode Inventaris:</div>
          <div class="fw-bold text-primary fs-6">'.e($asset['kode_inventaris'] ?? '-').'</div>
        </div>
        <div class="col-6 col-md-4">
          <div class="text-secondary">Serial Number:</div>
          <div class="fw-semibold text-dark">'.e($asset['serial_number'] ?? '-').'</div>
        </div>
        <div class="col-6 col-md-4">
          <div class="text-secondary">Merk / Model:</div>
          <div class="fw-semibold text-dark">'.e($asset['merk'] ?? '-').' / '.e($asset['model'] ?? '-').'</div>
        </div>
        <div class="col-6 col-md-4">
          <div class="text-secondary">Pengguna / Pemilik:</div>
          <div class="fw-bold text-dark"><i class="bi bi-person-circle text-primary me-1"></i>'.e($asset['karyawan_nama'] ?? '-').'</div>
        </div>
        <div class="col-6 col-md-4">
          <div class="text-secondary">Divisi:</div>
          <div class="fw-semibold text-dark">'.e($asset['divisi_nama'] ?? '-').'</div>
        </div>
        <div class="col-6 col-md-4">
          <div class="text-secondary">Cabang / Lokasi:</div>
          <div class="fw-semibold text-dark">'.e($asset['cabang_nama'] ?? '-').'</div>
        </div>
        <div class="col-12">
          <div class="text-secondary">Maintenance Terakhir:</div>
          <div class="fw-semibold text-dark"><i class="bi bi-clock-history me-1 text-secondary"></i>'.e($lastMaintStr).'</div>
        </div>
      </div>
    </div>

    <!-- Alert Temuan Kerusakan / Tindak Lanjut -->
    '.$pendingAlertHtml.'

    <!-- Card Status Maintenance Bulan Berjalan -->
    '.$statusCardHtml.'

    <!-- Card Catatan & Keluhan Karyawan / Pengguna -->
    '.$complaintCardHtml.'

    <!-- KARTU KONTROL CHECKLIST 12 BULAN (PERSIS FORMAT GAMBAR / MOBILE & DESKTOP) -->
    <div class="card p-3 p-md-4 border-0 shadow-sm mb-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-card-checklist text-primary me-2"></i>KARTU CHECKLIST MAINTENANCE IT '.$year.'</h5>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-outline-primary fw-semibold" target="_blank" href="'.e(module_url('print_card.php', ['id'=>$assetId, 'tahun'=>$year])).'"><i class="bi bi-printer me-1"></i> Cetak Kartu</a>
        </div>
      </div>

      <!-- Modern Kartu Kontrol Container (Persis Desain Gambar) -->
      <div class="mobile-card-wrapper">
        
        <!-- Header Banner Berwarna -->
        <div class="grid6-top-banner mb-2">
          <span><i class="bi bi-card-checklist me-1"></i> KARTU KONTROL IT · '.$year.'</span>
          <span class="grid6-branch-pill">'.e($cabangLabel).'</span>
        </div>

        <!-- Header Info -->
        <table class="grid6-info-table mb-2">
          <tr>
            <td style="width: 75px;"><span class="badge-lbl badge-blue">NAMA</span></td>
            <td style="width: 8px; text-align: center;">:</td>
            <td class="info-v"><strong>'.e($userWithDiv).'</strong></td>
          </tr>
          <tr>
            <td><span class="badge-lbl badge-green">IP / KODE</span></td>
            <td style="text-align: center;">:</td>
            <td class="info-v"><span class="text-success fw-bold font-monospace">'.e($ipDisplay).'</span> · <span class="badge-kode font-monospace">'.e($kodeInv).'</span></td>
          </tr>
          <tr>
            <td><span class="badge-lbl badge-purple">UNIT/PRT</span></td>
            <td style="text-align: center;">:</td>
            <td class="info-v">'.e($deviceTitle).' · <span class="text-secondary">'.e($printerDisplay).'</span></td>
          </tr>
        </table>

        <!-- 12 Months Matrix Table (Scrollable on small phones) -->
        <div class="grid6-matrix-wrapper">
          <table class="grid6-matrix-table">
            <thead>
              <tr>
                <th class="tgl-h">TGL</th>
                <th class="chk-h">1</th>
                <th class="chk-h">2</th>
                <th class="chk-h">3</th>
                <th class="chk-h">4</th>
                <th class="chk-h">5</th>
                <th class="chk-h">6</th>
                <th class="chk-h">7</th>
                <th class="chk-h">8</th>
                <th class="chk-h">9</th>
                <th class="paraf-h">PARAF</th>
              </tr>
            </thead>
            <tbody>
              '.$cardMatrixRows.'
            </tbody>
          </table>
        </div>

        <!-- 9 Item Legend Footer Lengkap (1-9) -->
        <div class="grid6-ket-box mt-2">
          <div class="grid6-ket-title"><i class="bi bi-info-circle-fill me-1"></i>KETERANGAN ITEM CHECKLIST (1 - 9):</div>
          <div class="grid6-ket-grid">
            <span class="leg-tag leg-blue"><b>1.</b> Scan Virus</span>
            <span class="leg-tag leg-blue"><b>2.</b> Update AV</span>
            <span class="leg-tag leg-blue"><b>3.</b> Temp File</span>
            <span class="leg-tag leg-purple"><b>4.</b> Keyboard</span>
            <span class="leg-tag leg-purple"><b>5.</b> Mouse</span>
            <span class="leg-tag leg-purple"><b>6.</b> CPU & Mon</span>
            <span class="leg-tag leg-teal"><b>7.</b> Cek Tinta</span>
            <span class="leg-tag leg-teal"><b>8.</b> Cartridge</span>
            <span class="leg-tag leg-teal"><b>9.</b> Cek Nozzle</span>
          </div>
        </div>

      </div>
    </div>

  </div>
</div>';
render_page('Detail Perangkat · ' . ($asset['kode_inventaris'] ?? 'QR'), $body, $headStyle, '', false);

