<?php
// Histori & Yearly Card Matrix & Complaints
$historyList = get_asset_maintenance_history($assetId);
$cardMatrix = get_asset_yearly_card_matrix($assetId, $year);
$loggedIn = is_logged_in();
$enrolledTechs = get_enrolled_technicians(false);
$hasEnrolledTechs = !empty($enrolledTechs);
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
        ? '<a class="btn btn-primary fw-semibold" href="'.e(module_url('maintenance_detail.php', ['id' => $cLogId, 't' => $token])).'"><i class="bi bi-file-earmark-text me-1"></i> Rincian Pemeliharaan</a>'
        : '';

    $btnUlang = '<a class="btn btn-outline-secondary fw-semibold" href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'ulang'])).'"><i class="bi bi-arrow-repeat me-1"></i> Pemeliharaan Ulang</a>';
    $btnSusulan = '<a class="btn btn-outline-warning text-dark fw-semibold" href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'susulan'])).'"><i class="bi bi-clock-history me-1"></i> Maintenance Susulan</a>';

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

      <div class="d-flex flex-wrap gap-2 mt-3 pt-2 mobile-btn-stack">
        '.$btnTindakLanjut.'
        '.$btnUlang.'
        '.$btnSusulan.'
        '.$btnDetail.'
        <a class="btn btn-outline-primary fw-semibold" href="'.e(module_url('scanner.php')).'"><i class="bi bi-qr-code-scan me-1"></i> Pindai Komputer Lain</a>
      </div>
    </div>';
} else {
    $stAsetRaw = trim((string)($asset['status'] ?? 'Aktif')) ?: 'Aktif';
    $stAsetLower = strtolower($stAsetRaw);
    $isAsetAktif = ($stAsetLower === 'aktif' || $stAsetLower === '');

    if (!$isAsetAktif) {
        // Perangkat Tidak Terpakai / Nonaktif / Afkir: Tampilkan info status netral & bebas maintenance
        $statusCardHtml = '
        <div class="card shadow-sm mb-4 p-3 p-md-4 rounded-3" style="background-color: #F8FAFC; border: 1px solid #CBD5E1; border-left: 4px solid #64748B;">
          <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
            <span class="text-secondary fw-semibold fs-6"><i class="bi bi-info-circle me-1"></i> Status Operasional Aset:</span>
            <span class="badge bg-secondary px-3 py-2 fs-6"><i class="bi bi-slash-circle me-1"></i> '.e(ucfirst($stAsetRaw)).' &bull; Bebas Maint</span>
          </div>
          <h5 class="fw-bold text-dark mb-1">Perangkat Bebas Jadwal Pemeliharaan Bulanan</h5>
          <p class="small mb-3" style="color: #475569 !important;">
            Perangkat ini berstatus <strong>'.e(ucfirst($stAsetRaw)).'</strong> (misalnya komputer lama yang sudah diganti unit baru atau dinonaktifkan).
            Data spesifikasi teknis, riwayat inspeksi lampau, dan foto unit tetap tersimpan aman di database inventaris, namun <strong>unit ini tidak masuk target pemeliharaan bulanan teknisi</strong>.
          </p>
          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary fw-semibold py-2 px-3 shadow-xs" href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'start'])).'">
              <i class="bi bi-clipboard-plus me-1"></i> Catat Pemeliharaan Khusus (Opsional)
            </a>
            '.(is_logged_in() ? '<a class="btn btn-outline-primary fw-semibold py-2 px-3 shadow-xs" href="'.e(module_url('asset_edit.php', ['id' => $assetId])).'"><i class="bi bi-pencil-square me-1"></i> Ubah Status / Aktifkan Kembali</a>' : '').'
          </div>
        </div>';
    } else {
        $btnStartAction = '
        <div class="d-grid gap-2">
          <a class="btn btn-success btn-lg fw-bold py-3 px-4 shadow-sm text-center" href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'start'])).'">
            <i class="bi bi-lightning-charge-fill me-1"></i> Mulai Checklist Pemeliharaan (Bulan Ini)
          </a>
          <div class="d-flex gap-2">
            <a class="btn btn-outline-warning text-dark fw-semibold py-2 px-3 flex-fill shadow-sm text-center" href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'susulan'])).'" title="Isi pemeliharaan susulan untuk bulan lain">
              <i class="bi bi-clock-history me-1"></i> Maintenance Susulan
            </a>
            <a class="btn btn-outline-secondary fw-semibold py-2 px-3 flex-fill shadow-sm text-center" href="'.e(module_url('scanner.php')).'">
              <i class="bi bi-qr-code-scan me-1"></i> Pindai Lainnya
            </a>
          </div>
        </div>
        <div class="text-center mt-2"><small class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>Bisa Langsung Diisi &amp; Disimpan Tanpa Perlu Login Akun</small></div>';

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
}

// Deteksi bulan-bulan lampau yang belum dilakukan pemeliharaan (missed months)
// HANYA dicek untuk perangkat yang berstatus Aktif
$stAsetCheck = strtolower(trim((string)($asset['status'] ?? 'Aktif')));
$isAsetAktifCheck = ($stAsetCheck === 'aktif' || $stAsetCheck === '');

$missedPastMonths = [];
if ($isAsetAktifCheck) {
    for ($mIdx = 1; $mIdx < $month; $mIdx++) {
        if (empty($cardMatrix[$mIdx]['is_done'])) {
            $missedPastMonths[$mIdx] = $monthNames[$mIdx] ?? ('Bulan ' . $mIdx);
        }
    }
}
$missedBannerHtml = '';
if (!empty($missedPastMonths)) {
    $missedNames = implode(', ', $missedPastMonths);
    $firstMissedMonth = array_key_first($missedPastMonths);
    $missedBannerHtml = '
    <div class="alert alert-warning border-warning border-start border-4 shadow-sm rounded-3 py-3 px-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-clock-history text-warning fs-3 flex-shrink-0"></i>
        <div>
          <div class="fw-bold text-dark">Bulan Pemeliharaan Belum Terisi: '.e($missedNames).'</div>
          <div class="small text-muted">Pengguna cuti, dinas luar, atau terlewat saat jadwal? Anda dapat melakukan <strong>Maintenance Susulan</strong>.</div>
        </div>
      </div>
      <a href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'susulan', 'month' => $firstMissedMonth])).'" class="btn btn-warning btn-sm text-dark fw-bold px-3 py-2 shadow-xs">
        <i class="bi bi-calendar-plus me-1"></i> Isi Maintenance Susulan
      </a>
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
        '.(!empty($pendingFinding['log_id']) ? '<a class="btn btn-outline-secondary py-2" href="'.e(module_url('maintenance_detail.php', ['id' => (int)$pendingFinding['log_id'], 't' => $token])).'"><i class="bi bi-file-earmark-text me-1"></i> Rincian Audit</a>' : '').'
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

$rawMerk = trim((string)($asset['merk'] ?? '-'));
$rawModel = trim((string)($asset['model'] ?? '-'));
$cleanModel = $rawModel;
if ($rawMerk !== '-' && $rawModel !== '-' && stripos($rawModel, $rawMerk) === 0) {
    $cleanModel = trim(substr($rawModel, strlen($rawMerk)));
    if ($cleanModel === '') $cleanModel = $rawModel;
}
$displayMerkModel = ($rawMerk !== '-' && $cleanModel !== '-') ? ($rawMerk . ' / ' . $cleanModel) : ($rawMerk !== '-' ? $rawMerk : $cleanModel);

$cardMatrixRows = '';
for ($m = 1; $m <= 12; $m++) {
    $row = $cardMatrix[$m];
    $dateLabel = $row['date_str'];
    $isDone = $row['is_done'];
    $logId = (int)($row['log_id'] ?? 0);

    if ($isDone) {
        $parafText = e($row['paraf']);
        if ($logId > 0) {
            $paraf = '<a href="'.e(module_url('maintenance_detail.php', ['id' => $logId, 't' => $token])).'" class="text-decoration-none text-dark fw-bold" title="Lihat Rincian Audit">'.$parafText.'</a>';
        } else {
            $paraf = $parafText;
        }
    } else {
        $paraf = '<span class="text-muted" style="font-size: 0.72rem;">-</span>';
    }

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
/* Modern Kartu Kontrol IT Base Styles */
.scan-card-section {
  background: #ffffff;
  border-radius: 12px;
}
.mobile-card-wrapper {
  background: #ffffff;
  border: 1.5px solid #2563eb;
  border-radius: 12px;
  padding: 12px 14px;
  box-shadow: 0 4px 18px rgba(37, 99, 235, 0.08);
  margin: 0 auto;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}

.grid6-top-banner {
  background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
  color: #ffffff;
  padding: 8px 12px;
  border-radius: 6px;
  font-weight: 700;
  font-size: 0.90rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.grid6-top-banner .banner-title {
  display: flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.grid6-branch-pill {
  background: #ffffff;
  color: #1e3a8a;
  font-size: 0.72rem;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  flex-shrink: 0;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
}

.grid6-info-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.84rem;
  line-height: 1.4;
  margin: 8px 0;
}
.grid6-info-table td {
  padding: 3px 2px;
  vertical-align: middle;
}
.grid6-info-table .info-lbl-col {
  width: 82px;
}
.grid6-info-table .info-sep-col {
  width: 8px;
  text-align: center;
}
.grid6-info-table .info-v {
  word-break: break-word;
}

.badge-lbl {
  font-size: 0.70rem;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 4px;
  display: inline-block;
  text-align: center;
  white-space: nowrap;
}
.badge-blue { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
.badge-green { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.badge-purple { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
.badge-kode { background: #f1f5f9; color: #0f172a; padding: 2px 6px; border-radius: 4px; font-weight: bold; border: 1px solid #cbd5e1; }

.grid6-matrix-wrapper {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  margin-bottom: 8px;
  border-radius: 6px;
  border: 1px solid #2563eb;
  background: #ffffff;
}

.grid6-matrix-table {
  width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: 0.82rem;
  margin: 0;
}
.col-w-tgl { width: 17%; }
.col-w-chk { width: 6.8%; }
.col-w-paraf { width: 21.8%; }

.grid6-matrix-table th {
  background: #2563eb !important;
  color: #ffffff !important;
  border: 1px solid #1d4ed8;
  font-weight: 700;
  text-align: center;
  padding: 5px 1px;
  font-size: 0.74rem;
  line-height: 1.2;
}
.grid6-matrix-table td {
  border: 1px solid #cbd5e1;
  text-align: center;
  padding: 3px 1px;
  height: 24px;
  vertical-align: middle;
}
.grid6-matrix-table tr.even-row { background-color: #f8fafc; }
.grid6-matrix-table tr.done-row { background-color: #f0fdf4; }
.grid6-matrix-table tr.done-row td { border-color: #a7f3d0; }

.grid6-matrix-table .tgl-col {
  font-weight: 700;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.72rem;
  color: #1e40af;
  white-space: nowrap;
  overflow: hidden;
  text-align: center;
  padding: 3px 1px;
}
.grid6-matrix-table .chk-col {
  font-size: 0.84rem;
  padding: 0;
  text-align: center;
  line-height: 1;
}
.grid6-matrix-table .chk-yes {
  color: #059669;
  font-weight: 900;
}
.grid6-matrix-table .chk-no {
  color: #94a3b8;
}
.grid6-matrix-table .paraf-col {
  font-size: 0.72rem;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  color: #334155;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  padding: 2px 2px;
  text-align: center;
}

.grid6-ket-box {
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 8px 10px;
}
.grid6-ket-title {
  font-size: 0.72rem;
  font-weight: 800;
  color: #1e40af;
  margin-bottom: 6px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  display: flex;
  align-items: center;
}
.grid6-ket-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 4px 6px;
}
.leg-tag {
  font-size: 0.70rem;
  font-weight: 600;
  padding: 2px 5px;
  border-radius: 4px;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 3px;
  justify-content: flex-start;
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

/* MOBILE RESPONSIVE TWEAKS (PLACED AFTER BASE STYLES SO THEY WIN SPECIFICITY) */
@media (max-width: 576px) {
  .scan-card-section {
    padding: 10px 8px !important;
  }
  .mobile-card-wrapper {
    padding: 8px 6px;
    border-radius: 10px;
    border-width: 1.5px;
  }
  .grid6-top-banner {
    padding: 6px 8px;
    font-size: 0.80rem;
    gap: 4px;
  }
  .grid6-branch-pill {
    font-size: 0.64rem;
    padding: 2px 6px;
  }
  .grid6-info-table {
    font-size: 0.76rem;
    margin: 6px 0;
  }
  .grid6-info-table td {
    padding: 2px 1px;
  }
  .grid6-info-table .info-lbl-col {
    width: 68px;
  }
  .badge-lbl {
    font-size: 0.64rem;
    padding: 1px 4px;
  }
  .grid6-matrix-wrapper {
    margin-bottom: 6px;
    border-radius: 6px;
  }
  .grid6-matrix-table {
    min-width: 285px;
  }
  .grid6-matrix-table th {
    padding: 4px 1px;
    font-size: 0.68rem;
  }
  .grid6-matrix-table td {
    padding: 2px 0;
    height: 22px;
  }
  .grid6-matrix-table .tgl-col {
    font-size: 0.68rem;
    padding: 2px 0;
  }
  .grid6-matrix-table .chk-col {
    font-size: 0.76rem;
  }
  .grid6-matrix-table .paraf-col {
    font-size: 0.66rem;
    padding: 2px 1px;
  }
  .grid6-ket-box {
    padding: 6px 6px;
  }
  .grid6-ket-title {
    font-size: 0.66rem;
    margin-bottom: 4px;
  }
  .grid6-ket-grid {
    grid-template-columns: repeat(3, 1fr);
    gap: 3px 3px;
  }
  .leg-tag {
    font-size: 0.62rem;
    padding: 1px 3px;
  }
  .mobile-btn-stack .btn {
    flex: 1 1 calc(50% - 6px);
    text-align: center;
    justify-content: center;
    font-size: 0.8rem;
    padding: 8px 6px;
  }
}

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
         <span class="small fw-bold text-dark"><i class="bi bi-person-check-fill text-success me-1"></i> Sesi Login: <span class="text-primary">'.e(current_user_name()).'</span></span>
         <div class="d-flex gap-2">
           <a href="'.e(module_url('scanner.php')).'" class="btn btn-sm btn-primary fw-semibold"><i class="bi bi-qr-code-scan me-1"></i> Pindai Lagi</a>
           <a href="'.e(module_url('dashboard.php')).'" class="btn btn-sm btn-outline-secondary"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
         </div>
       </div>'
    : '<div class="d-flex flex-wrap justify-content-between align-items-center bg-white p-2 px-3 rounded-3 shadow-sm mb-3 border gap-2">
         <div class="d-flex align-items-center gap-2">
           <span class="badge bg-success px-2 py-1"><i class="bi bi-phone-fill me-1"></i> Pemindaian HP Aktif</span>
           <span class="small text-secondary">Akses langsung lapangan &bull; Siap catat checklist tanpa login</span>
         </div>
         <div class="d-flex gap-2">
           <a href="'.e(module_url('scanner.php')).'" class="btn btn-sm btn-primary fw-semibold"><i class="bi bi-qr-code-scan me-1"></i> Pindai Lagi</a>
           <a href="'.e(module_url('login.php')).'" class="btn btn-sm btn-outline-secondary" title="Login Admin Portal"><i class="bi bi-shield-lock"></i></a>
         </div>
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

// Build Komponen Riwayat Sesi Pemeliharaan Komputer Ini
$historyCount = count($historyList);
$historyItemsHtml = '';
if (empty($historyList)) {
    $historyItemsHtml = '
    <div class="text-center py-4 text-muted bg-light rounded-3 border border-dashed">
      <i class="bi bi-calendar-x text-secondary fs-3 d-block mb-1"></i>
      <span class="small">Belum ada riwayat pelaksanaan pemeliharaan yang tercatat untuk perangkat ini.</span>
    </div>';
} else {
    foreach ($historyList as $idx => $h) {
        $hId = (int)($h['id'] ?? 0);
        $hDate = !empty($h['maintenance_date']) ? format_id_date($h['maintenance_date']) : '-';
        $hTime = !empty($h['maintenance_time']) ? substr($h['maintenance_time'], 0, 5) . ' WITA' : '';
        $hTech = !empty($h['technician_name']) ? $h['technician_name'] : 'Teknisi';
        $hStatus = $h['status'] ?? 'Selesai';
        $hType = $h['maintenance_type'] ?? 'Maintenance';
        $isSusulanItem = (stripos($hType, 'susulan') !== false);

        $hBadgeColor = ($hStatus === 'Temuan' || $hStatus === 'Perlu Perbaikan') ? 'danger' : ($hStatus === 'Proses' ? 'warning text-dark' : 'success');
        $hBadgeIcon = ($hStatus === 'Temuan' || $hStatus === 'Perlu Perbaikan') ? 'bi-exclamation-triangle-fill' : ($hStatus === 'Proses' ? 'bi-hourglass-split' : 'bi-check-circle-fill');

        $hDetailUrl = ($hId > 0) ? module_url('maintenance_detail.php', ['id' => $hId, 't' => $token]) : '#';
        $hFindings = trim((string)($h['findings'] ?? ''));
        $hRecom = trim((string)($h['recommendation'] ?? ''));

        $historyItemsHtml .= '
        <div class="p-3 mb-2 rounded-3 border bg-white shadow-xs">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 font-monospace small px-2 py-1">
                <i class="bi bi-calendar-event me-1"></i>'.e($hDate).($hTime ? ' · '.e($hTime) : '').'
              </span>
              '.($isSusulanItem ? '<span class="badge bg-warning text-dark small px-2 py-1"><i class="bi bi-clock-history me-1"></i>Susulan</span>' : '<span class="badge bg-light text-secondary border small px-2 py-1">Reguler</span>').'
            </div>
            <span class="badge bg-'.$hBadgeColor.' px-2.5 py-1 small">
              <i class="bi '.$hBadgeIcon.' me-1"></i>'.e($hStatus).'
            </span>
          </div>

          <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 pt-2 border-top">
            <div class="small text-secondary">
              <i class="bi bi-person-badge text-primary me-1"></i>Petugas: <strong class="text-dark">'.e($hTech).'</strong>
            </div>
            '.($hId > 0 ? '
            <a href="'.e($hDetailUrl).'" class="btn btn-sm btn-outline-primary fw-semibold py-1 px-3 w-100 w-sm-auto text-center" style="font-size: 0.78rem;">
              <i class="bi bi-file-earmark-medical me-1"></i> Rincian Audit #'.$hId.' &rarr;
            </a>' : '').'
          </div>

          '.($hFindings !== '' && $hFindings !== '-' ? '
          <div class="alert alert-danger py-1.5 px-2.5 small my-2 mb-0" style="font-size: 0.78rem;">
            <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Temuan:</strong> '.e($hFindings).'
          </div>' : '').'
          '.($hRecom !== '' && $hRecom !== '-' ? '
          <div class="alert alert-info py-1.5 px-2.5 small mt-1.5 mb-0" style="font-size: 0.78rem;">
            <strong><i class="bi bi-lightbulb-fill me-1"></i>Tindakan/Solusi:</strong> '.e($hRecom).'
          </div>' : '').'
        </div>';
    }
}

$historyCardHtml = '
<div class="card p-3 p-md-4 border-0 shadow-sm mb-4" id="riwayat-pemeliharaan">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h5 class="fw-bold text-dark mb-0 fs-6 fs-md-5">
        <i class="bi bi-clock-history text-primary me-2"></i>Riwayat Pemeliharaan Komputer Ini
      </h5>
      <p class="small text-secondary mb-0">Catatan log pelaksanaan pemeliharaan fisik & software pada perangkat ini.</p>
    </div>
    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1.5 fw-bold" style="font-size: 0.75rem;">
      <i class="bi bi-journal-check me-1"></i> '.$historyCount.' Sesi Tercatat
    </span>
  </div>

  <div class="history-list-box">
    '.$historyItemsHtml.'
  </div>
</div>';

// Foto Fisik Unit
$fotoUnit = trim((string)($asset['foto'] ?? ''));
$fotoUnitSrc = function_exists('asset_image_url') ? asset_image_url($fotoUnit) : ($fotoUnit ? (str_starts_with($fotoUnit, 'http') ? $fotoUnit : module_url($fotoUnit)) : '');


$verifiedBadge = !empty($asset['foto_updated_by']) ? '
  <div class="mt-1">
    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2 py-0.5" style="font-size: 0.68rem;">
      <i class="bi bi-patch-check-fill me-1"></i> Diverifikasi: <strong>'.e($asset['foto_updated_by']).'</strong>
    </span>
  </div>' : '';

if ($fotoUnitSrc !== '') {
    $fotoCardHtml = '
    <div class="col-12 col-md-4 col-lg-3 text-center mb-3 mb-md-0">
      <div class="position-relative rounded-3 overflow-hidden border shadow-xs bg-dark bg-opacity-10 d-flex align-items-center justify-content-center" style="cursor: pointer; aspect-ratio: 4 / 5; max-height: 240px; margin: 0 auto;" onclick="openScanPhotoModal(\''.e($fotoUnitSrc).'\', \''.e(addslashes(asset_title($asset))).'\', \''.e(addslashes($asset['kode_inventaris'] ?? '')).'\')" title="Klik untuk memperbesar foto unit">
        <img src="'.e($fotoUnitSrc).'" alt="Foto Fisik '.e(asset_title($asset)).'" class="w-100 h-100 object-fit-cover" style="max-height: 210px; min-height: 175px; display: block;" onerror="this.onerror=null; this.parentElement.innerHTML=\'<div class=\\\'p-4 text-muted small\\\'><i class=\\\'bi bi-image fs-1 d-block mb-1 text-secondary opacity-50\\\'></i>Gagal memuat foto</div>\';">
        <div class="position-absolute bottom-0 start-0 w-100 bg-dark bg-opacity-75 text-white py-1 px-2 small text-center" style="font-size: 0.72rem; backdrop-filter: blur(2px);">
          <i class="bi bi-zoom-in me-1"></i> Perbesar Foto
        </div>
      </div>
      '.$verifiedBadge.'
      <div class="mt-2 d-flex justify-content-center gap-1">
        <button type="button" class="btn btn-sm btn-light border py-1 px-2.5 text-secondary fw-semibold shadow-xs" style="font-size: 0.75rem;" onclick="openUploadFotoTeknisiModal()" title="Ganti atau perbarui foto unit dengan scan wajah teknisi">
          <i class="bi bi-camera me-1"></i> Ganti Foto
        </button>
      </div>
    </div>';
    $specColClass = 'col-12 col-md-8 col-lg-9';
} else {
    $fotoCardHtml = '
    <div class="col-12 col-md-4 col-lg-3 text-center mb-3 mb-md-0">
      <div class="rounded-3 border border-2 border-dashed bg-light p-3 d-flex flex-column align-items-center justify-content-center h-100" style="min-height: 175px; border-color: #cbd5e1 !important;">
        <i class="bi bi-camera fs-1 text-secondary opacity-50 mb-1"></i>
        <div class="small text-muted mb-2 fw-semibold" style="font-size: 0.75rem;">Belum ada foto fisik</div>
        <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2.5 fw-semibold shadow-xs" onclick="openUploadFotoTeknisiModal()" style="font-size: 0.75rem;">
          <i class="bi bi-plus-circle me-1"></i> Upload Foto
        </button>
      </div>
    </div>';
    $specColClass = 'col-12 col-md-8 col-lg-9';
}

$replInfo = function_exists('get_asset_replacement_info') ? get_asset_replacement_info($asset) : ['replaces' => null, 'replaced_by' => null, 'date' => null];
$crossLinkBannerHtml = '';

if (!empty($replInfo['replaces'])) {
    $oldA = $replInfo['replaces'];
    $oldToken = !empty($oldA['qr_token']) ? $oldA['qr_token'] : get_static_qr_token((int)$oldA['id']);
    $oldTitle = asset_title($oldA);
    $oldUser = !empty($oldA['karyawan_nama']) && $oldA['karyawan_nama'] !== '-' ? $oldA['karyawan_nama'] : '';
    $rawDate = $replInfo['date'] ?? '';
    $dateText = $rawDate ? (strtotime($rawDate) ? format_id_date($rawDate) : $rawDate) : date('d/m/Y');
    
    $crossLinkBannerHtml = '
    <div class="alert alert-primary bg-primary bg-opacity-10 border-primary border-opacity-25 rounded-3 py-2.5 px-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2 shadow-xs">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-arrow-repeat text-primary fs-4 flex-shrink-0"></i>
        <div>
          <div class="fw-bold text-primary small"><i class="bi bi-link-45deg me-1"></i>Unit ini menggantikan: <span class="text-dark">'.e($oldA['kode_inventaris'] ?? '#'.$oldA['id']).'</span></div>
          <div class="text-dark small">
            '.e($oldTitle).($oldUser ? ' &middot; Pengguna: <strong>'.e($oldUser).'</strong>' : '').'
            '.($dateText ? '<span class="text-muted"> &bull; Diganti pada: <strong>'.e($dateText).'</strong></span>' : '').'
          </div>
        </div>
      </div>
      <a href="'.e(module_url('scan.php', ['t' => $oldToken])).'" class="btn btn-sm btn-outline-primary py-1 px-2.5 fw-semibold font-monospace shadow-xs" style="font-size:0.75rem;">
        <i class="bi bi-clock-history me-1"></i>Lihat Komputer Lama &rarr;
      </a>
    </div>';
} elseif (!empty($replInfo['replaced_by'])) {
    $newA = $replInfo['replaced_by'];
    $newToken = !empty($newA['qr_token']) ? $newA['qr_token'] : get_static_qr_token((int)$newA['id']);
    $newTitle = asset_title($newA);
    $rawDate = $replInfo['date'] ?? '';
    $dateText = $rawDate ? (strtotime($rawDate) ? format_id_date($rawDate) : $rawDate) : date('d/m/Y');

    $crossLinkBannerHtml = '
    <div class="alert alert-warning bg-warning bg-opacity-10 border-warning border-opacity-50 rounded-3 py-2.5 px-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2 shadow-xs">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-arrow-right-circle-fill text-warning fs-4 flex-shrink-0"></i>
        <div>
          <div class="fw-bold text-dark small"><i class="bi bi-link-45deg me-1"></i>Telah diganti oleh: <span class="text-primary fw-bold">'.e($newA['kode_inventaris'] ?? '#'.$newA['id']).'</span></div>
          <div class="text-dark small">
            '.e($newTitle).'
            '.($dateText ? '<span class="text-muted"> &bull; Pada tanggal <strong>'.e($dateText).'</strong></span>' : '').'
          </div>
        </div>
      </div>
      <a href="'.e(module_url('scan.php', ['t' => $newToken])).'" class="btn btn-sm btn-dark py-1 px-2.5 fw-semibold font-monospace shadow-xs" style="font-size:0.75rem;">
        <i class="bi bi-box-arrow-up-right me-1"></i>Buka Unit Baru &rarr;
      </a>
    </div>';
}

$body = '
<div class="row justify-content-center">
  <div class="col-md-11 col-lg-10">

    <!-- Status Strip Login / Tamu -->
    '.$userStatusStrip.'
    '.$flashScanHtml.'
    '.($error ? '<div class="alert alert-danger py-2 px-3 mb-3 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Gagal Menyimpan:</strong> '.e($error).'</div>' : '').'

    <!-- Card Detail Perangkat Utama -->
    <div class="card p-3 p-md-4 border-0 shadow-sm mb-3 mb-md-4">
      <div class="d-flex flex-wrap align-items-center justify-content-between border-bottom pb-2 pb-md-3 mb-3 gap-2">
        <div class="flex-grow-1 min-w-0">
          <span class="badge bg-primary px-2.5 py-1 mb-1 font-monospace" style="font-size: 0.72rem;">'.e($asset['kategori_nama'] ?? 'Perangkat IT').'</span>
          <h4 class="fw-bold text-dark mb-0 fs-5 fs-md-4 text-break">'.e(asset_title($asset)).'</h4>
        </div>
        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-2.5 py-1.5 border border-success flex-shrink-0" style="font-size: 0.75rem;">
          <i class="bi bi-check-circle-fill me-1"></i> '.e($asset['status'] ?? 'Aktif').'
        </span>
      </div>

      '.$crossLinkBannerHtml.'

      <div class="row g-3 align-items-center">
        '.$fotoCardHtml.'
        <div class="'.$specColClass.'">
          <!-- Detail Spesifikasi & Kepemilikan -->
          <div class="row g-2 g-md-3 small mb-1">
            <div class="col-6 col-md-4">
              <div class="text-secondary" style="font-size: 0.72rem;">Kode Inventaris:</div>
              <div class="fw-bold text-primary fs-6 font-monospace text-truncate">'.e($asset['kode_inventaris'] ?? '-').'</div>
            </div>
            <div class="col-6 col-md-4">
              <div class="text-secondary" style="font-size: 0.72rem;">Serial Number:</div>
              <div class="fw-semibold text-dark text-truncate">'.e($asset['serial_number'] ?? '-').'</div>
            </div>
            <div class="col-6 col-md-4">
              <div class="text-secondary" style="font-size: 0.72rem;">Merk / Model:</div>
              <div class="fw-semibold text-dark text-truncate">'.e($displayMerkModel).'</div>
            </div>
            <div class="col-6 col-md-4">
              <div class="text-secondary" style="font-size: 0.72rem;">Pengguna / Pemilik:</div>
              <div class="fw-bold text-dark text-truncate"><i class="bi bi-person-circle text-primary me-1"></i>'.e($asset['karyawan_nama'] ?? '-').'</div>
            </div>
            <div class="col-6 col-md-4">
              <div class="text-secondary" style="font-size: 0.72rem;">Divisi:</div>
              <div class="fw-semibold text-dark text-truncate">'.e($asset['divisi_nama'] ?? '-').'</div>
            </div>
            <div class="col-6 col-md-4">
              <div class="text-secondary" style="font-size: 0.72rem;">Cabang / Lokasi:</div>
              <div class="fw-semibold text-dark text-truncate">'.e($asset['cabang_nama'] ?? '-').'</div>
            </div>
            <div class="col-12 pt-1 border-top mt-2">
              <div class="text-secondary" style="font-size: 0.72rem;">Maintenance Terakhir:</div>
              <div class="fw-semibold text-dark"><i class="bi bi-clock-history me-1 text-primary"></i>'.e($lastMaintStr).'</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Alert Temuan Kerusakan / Tindak Lanjut -->
    '.$pendingAlertHtml.'

    <!-- Banner Bulan Pemeliharaan Belum Terisi (Susulan) -->
    '.$missedBannerHtml.'

    <!-- Card Status Maintenance Bulan Berjalan -->
    '.$statusCardHtml.'

    <!-- Card Catatan & Keluhan Karyawan / Pengguna -->
    '.$complaintCardHtml.'

    <!-- KARTU KONTROL CHECKLIST 12 BULAN (PERSIS FORMAT GAMBAR / MOBILE & DESKTOP) -->
    <div class="card p-3 p-md-4 border-0 shadow-sm mb-4 scan-card-section">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0 fs-6 fs-md-5"><i class="bi bi-card-checklist text-primary me-2"></i>KARTU CHECKLIST MAINTENANCE IT '.$year.'</h5>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-outline-primary fw-semibold" target="_blank" href="'.e(module_url('print_card.php', ['id'=>$assetId, 'tahun'=>$year])).'"><i class="bi bi-printer me-1"></i> Cetak Kartu</a>
        </div>
      </div>

      <!-- Modern Kartu Kontrol Container (Persis Desain Gambar) -->
      <div class="mobile-card-wrapper">
        
        <!-- Header Banner Berwarna -->
        <div class="grid6-top-banner mb-2">
          <span class="banner-title"><i class="bi bi-card-checklist me-1"></i> KARTU KONTROL IT · '.$year.'</span>
          <span class="grid6-branch-pill">'.e($cabangLabel).'</span>
        </div>

        <!-- Header Info -->
        <table class="grid6-info-table mb-2">
          <tr>
            <td class="info-lbl-col"><span class="badge-lbl badge-blue">NAMA</span></td>
            <td class="info-sep-col">:</td>
            <td class="info-v"><strong>'.e($userWithDiv).'</strong></td>
          </tr>
          <tr>
            <td class="info-lbl-col"><span class="badge-lbl badge-green">IP / KODE</span></td>
            <td class="info-sep-col">:</td>
            <td class="info-v"><span class="text-success fw-bold font-monospace">'.e($ipDisplay).'</span> · <span class="badge-kode font-monospace">'.e($kodeInv).'</span></td>
          </tr>
          <tr>
            <td class="info-lbl-col"><span class="badge-lbl badge-purple">UNIT/PRT</span></td>
            <td class="info-sep-col">:</td>
            <td class="info-v">'.e($deviceTitle).' · <span class="text-secondary">'.e($printerDisplay).'</span></td>
          </tr>
        </table>

        <!-- 12 Months Matrix Table (Scrollable on small phones) -->
        <div class="grid6-matrix-wrapper">
          <table class="grid6-matrix-table">
            <colgroup>
              <col class="col-w-tgl">
              <col class="col-w-chk">
              <col class="col-w-chk">
              <col class="col-w-chk">
              <col class="col-w-chk">
              <col class="col-w-chk">
              <col class="col-w-chk">
              <col class="col-w-chk">
              <col class="col-w-chk">
              <col class="col-w-chk">
              <col class="col-w-paraf">
            </colgroup>
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

    <!-- Riwayat Seluruh Sesi Pemeliharaan Komputer Ini -->
    '.$historyCardHtml.'

  </div>
</div>

<!-- Modal Zoom Foto Fisik Unit -->
<div class="modal fade" id="modalScanPhoto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header py-3 px-4 border-bottom bg-light">
        <div>
          <h5 class="modal-title fw-bold text-dark mb-0" id="scanPhotoTitle">Foto Unit Perangkat</h5>
          <small class="text-primary font-monospace" id="scanPhotoSubtitle"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-3 text-center bg-dark bg-opacity-10">
        <img id="scanPhotoImg" src="" alt="Foto Unit" class="img-fluid rounded border shadow-sm" style="max-height: 520px; width: auto; object-fit: contain;">
      </div>
      <div class="modal-footer py-2 px-3 bg-light d-flex justify-content-between">
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-dismiss="modal" onclick="openUploadFotoTeknisiModal()">
          <i class="bi bi-camera me-1"></i> Ganti / Perbarui Foto Ini
        </button>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Upload Foto Unit dengan Verifikasi Wajah Teknisi -->
<div class="modal fade" id="modalUploadFotoTeknisi" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-primary text-white py-3 px-4">
        <div>
          <h5 class="modal-title fw-bold mb-0"><i class="bi bi-camera-fill me-2"></i>Foto Fisik Perangkat</h5>
          <small class="text-white-50">'.e($asset['kode_inventaris'] ?? 'Aset').' · '.e(asset_title($asset)).'</small>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form method="post" enctype="multipart/form-data" id="formUploadFotoUnit" action="'.e(module_url('scan.php', ['t' => $token])).'">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="action" value="update_asset_photo">
        <input type="hidden" name="foto_base64" id="scanPhotoBase64" value="">
        <input type="hidden" name="face_verified" id="scanPhotoBioVerified" value="0">
        <input type="hidden" name="verifier_name" id="scanPhotoBioUserName" value="">
        <input type="hidden" name="verifier_user_id" id="scanPhotoBioUserId" value="">
        <input type="hidden" name="verifier_confidence" id="scanPhotoBioConfidence" value="">
        <input type="hidden" name="verifier_photo" id="scanPhotoBioSnap" value="">

        <div class="modal-body p-4">
          <!-- Step 1: Ambil / Pilih Foto Unit -->
          <div class="mb-3">
            <label class="form-label small fw-bold text-secondary mb-1">
              <i class="bi bi-image me-1 text-primary"></i> 1. Jepret Kamera / Pilih Foto Fisik Unit:
            </label>
            <input type="file" id="fotoBarangScanInput" name="foto_unit" class="form-control" accept="image/*" capture="environment" onchange="handleScanPhotoSelect(event)" required>
            <div class="form-text small" style="font-size: 0.73rem;">
              Format JPEG, JPG, PNG. Otomatis dikompresi cerdas ke resolusi jernih hemat ruang server (~150-250KB).
            </div>
          </div>

          <!-- Preview & Status Kompresi -->
          <div id="scanPhotoPreviewContainer" class="d-none mb-3">
            <div class="position-relative rounded-3 border overflow-hidden bg-light text-center p-2">
              <img id="scanPhotoPreviewImg" src="" alt="Pratinjau" class="img-fluid rounded" style="max-height: 190px; object-fit: contain;">
              <div id="scanPhotoCompressBadge" class="mt-2 text-success small fw-semibold">
                <i class="bi bi-shield-check me-1"></i> Terkompres Cerdas Siap Unggah
              </div>
            </div>
          </div>

          <!-- Step 2: Otorisasi Biometrik Wajah -->
          <div class="bg-light p-3 rounded-3 border mb-2">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="small fw-bold text-dark">
                <i class="bi bi-person-bounding-box me-1 text-primary"></i> 2. Otorisasi Teknisi:
              </span>
              '.($hasEnrolledTechs
                ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-0.5 rounded-pill" style="font-size: 0.68rem;"><i class="bi bi-check-circle me-1"></i>'.count($enrolledTechs).' Teknisi Terdaftar</span>'
                : '<span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 px-2 py-0.5 rounded-pill" style="font-size: 0.68rem;"><i class="bi bi-exclamation-triangle me-1"></i>Belum Ada Wajah Terdaftar</span>').'
            </div>

            <p class="text-muted small mb-3" style="font-size: 0.75rem;">
              Untuk menjaga integritas data inventaris Core Banking, perubahan foto fisik unit wajib diverifikasi oleh wajah Teknisi IT terdaftar.
            </p>

            <button type="button" id="btnMulaiScanWajahFoto" class="btn btn-primary w-100 fw-bold py-2.5 rounded-pill shadow-xs d-flex align-items-center justify-content-center gap-2" onclick="startTeknisiFaceScanForPhoto()">
              <i class="bi bi-person-bounding-box fs-5"></i>
              <span>Scan Wajah Teknisi & Simpan Foto</span>
            </button>

            '.($loggedIn ? '
              <div class="mt-2 text-center">
                <button type="button" class="btn btn-link btn-xs text-muted p-0 text-decoration-none" onclick="submitPhotoAsLoggedInAdmin()" style="font-size: 0.72rem;">
                  <i class="bi bi-shield-lock me-1"></i> Otorisasi Cepat via Sesi Login ('.e(current_user_name()).')
                </button>
              </div>' : '').'
          </div>

          <div class="text-center">
            <a href="'.e(module_url('user_biometric_enroll.php', ['ret' => module_url('scan.php', ['t' => $token])])).'" class="text-decoration-none small text-primary" style="font-size: 0.72rem;">
              <i class="bi bi-person-plus me-1"></i> Daftarkan Wajah Teknisi Baru
            </a>
          </div>
        </div>

        <div class="modal-footer py-2 px-3 bg-light">
          <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Scanner Biometrik Wajah Teknisi -->
<div class="modal fade" id="modalFaceVerifyPhoto" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden bg-dark text-white">
      <div class="modal-header border-secondary border-opacity-25 py-3 px-4">
        <div>
          <h6 class="modal-title fw-bold text-white mb-0"><i class="bi bi-person-bounding-box text-primary me-2"></i>Verifikasi Biometrik Wajah Teknisi</h6>
          <small class="text-white-50">Arahkan wajah Anda ke kamera</small>
        </div>
        <button type="button" class="btn-close btn-close-white" onclick="closePhotoFaceModal()"></button>
      </div>

      <div class="modal-body p-3 text-center position-relative">
        <!-- Video Box & Scanner Frame -->
        <div class="position-relative mx-auto rounded-3 overflow-hidden bg-black shadow-inner" style="max-width: 320px; aspect-ratio: 1/1;">
          <video id="bioPhotoVideo" autoplay muted playsinline class="w-100 h-100 object-fit-cover" style="transform: scaleX(-1);"></video>
          
          <!-- Oval Guide Frame -->
          <div class="position-absolute top-50 start-50 translate-middle pointer-events-none" style="width: 72%; height: 85%; border: 2.5px dashed rgba(59, 130, 246, 0.85); border-radius: 50%; box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.35);"></div>
          
          <!-- Scanning Ray Animation -->
          <div id="bioPhotoScanRay" class="position-absolute start-0 w-100" style="height: 3px; background: linear-gradient(90deg, transparent, #3b82f6, #60a5fa, transparent); animation: scanRayAnim 2s infinite ease-in-out;"></div>

          <!-- Success Overlay -->
          <div id="bioPhotoSuccessOverlay" class="position-absolute top-0 start-0 w-100 h-100 bg-success bg-opacity-75 d-flex flex-column align-items-center justify-content-center text-white d-none" style="backdrop-filter: blur(4px);">
            <i class="bi bi-check-circle-fill display-3 mb-2 animate-bounce"></i>
            <h5 class="fw-bold mb-1" id="bioPhotoMatchedName">Wajah Terverifikasi!</h5>
            <span class="badge bg-white text-success fw-bold px-3 py-1 rounded-pill" id="bioPhotoMatchConf">100% Cocok</span>
          </div>
        </div>

        <div id="bioPhotoStatusBox" class="alert alert-secondary py-2 px-3 small fw-semibold mt-3 mb-2 text-start text-dark">
          <span class="spinner-border spinner-border-sm me-2 text-primary"></span> Menyiapkan kamera dan modul pengenalan wajah...
        </div>

        <div class="text-white-50 small" style="font-size: 0.72rem;">
          Pastikan wajah berada di dalam oval, tidak memakai masker, dan pencahayaan memadai.
        </div>
      </div>

      <div class="modal-footer border-secondary border-opacity-25 py-2 px-3 d-flex justify-content-between">
        <button type="button" class="btn btn-outline-light btn-sm rounded-pill px-3" onclick="closePhotoFaceModal()">
          Batal
        </button>
        <button type="button" id="btnManualTriggerPhotoBio" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold" onclick="triggerInstantPhotoFaceVerify()">
          <i class="bi bi-camera me-1"></i> Verifikasi Sekarang
        </button>
      </div>
    </div>
  </div>
</div>

<style>
@keyframes scanRayAnim {
  0% { top: 10%; opacity: 0.3; }
  50% { top: 85%; opacity: 1; }
  100% { top: 10%; opacity: 0.3; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.min.js"></script>
<script>
window.__ENROLLED_TECHS = '.json_encode($enrolledTechs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT).';

const parsedEnrolledTechs = (Array.isArray(window.__ENROLLED_TECHS) ? window.__ENROLLED_TECHS : []).map(t => {
  let desc = t.descriptor || t.face_descriptor;
  if (typeof desc === "string") {
    try { desc = JSON.parse(desc); } catch (e) { desc = null; }
  }
  return {
    id: t.id,
    nama: t.nama,
    descriptor: (Array.isArray(desc) && desc.length >= 64) ? new Float32Array(desc) : null
  };
}).filter(t => t.descriptor !== null);

let photoBioModelsLoaded = false;
let photoBioModelsLoading = false;
let photoBioVideoStream = null;
let photoBioTrackingTimer = null;
let photoBioCompleted = false;
const MODEL_URL = "https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/";

function openScanPhotoModal(src, title, kode) {
  var img = document.getElementById("scanPhotoImg");
  var t = document.getElementById("scanPhotoTitle");
  var s = document.getElementById("scanPhotoSubtitle");
  if (img) img.src = src;
  if (t) t.textContent = title || "Foto Unit Perangkat";
  if (s) s.textContent = kode || "";
  var modalEl = document.getElementById("modalScanPhoto");
  if (modalEl && typeof bootstrap !== "undefined") {
    var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.show();
  }
}

function openUploadFotoTeknisiModal() {
  var modalEl = document.getElementById("modalUploadFotoTeknisi");
  if (modalEl && typeof bootstrap !== "undefined") {
    var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.show();
  }
}

function handleScanPhotoSelect(event) {
  const file = event.target.files && event.target.files[0];
  if (!file) return;

  const previewContainer = document.getElementById("scanPhotoPreviewContainer");
  const previewImg = document.getElementById("scanPhotoPreviewImg");
  const compressBadge = document.getElementById("scanPhotoCompressBadge");

  const originalSizeKb = Math.round(file.size / 1024);

  const reader = new FileReader();
  reader.onload = function(e) {
    const tempImg = new Image();
    tempImg.onload = function() {
      // Kompresi & Auto-Crop Cerdas Rasio 4:5 Client-Side
      const targetRatio = 4 / 5;
      let cropW = tempImg.width;
      let cropH = tempImg.height;
      let sx = 0;
      let sy = 0;

      if (tempImg.width / tempImg.height > targetRatio) {
        cropW = Math.round(tempImg.height * targetRatio);
        cropH = tempImg.height;
        sx = Math.round((tempImg.width - cropW) / 2);
        sy = 0;
      } else {
        cropW = tempImg.width;
        cropH = Math.round(tempImg.width / targetRatio);
        sx = 0;
        sy = Math.round((tempImg.height - cropH) / 2);
      }

      let outW = 1200;
      let outH = 1500;
      if (cropW < outW) {
        outW = cropW;
        outH = Math.round(outW / targetRatio);
      }

      const canvas = document.createElement("canvas");
      canvas.width = outW;
      canvas.height = outH;
      const ctx = canvas.getContext("2d");
      ctx.imageSmoothingEnabled = true;
      ctx.imageSmoothingQuality = "high";
      ctx.drawImage(tempImg, sx, sy, cropW, cropH, 0, 0, outW, outH);

      // Export JPEG Quality 0.82
      const compressedDataUrl = canvas.toDataURL("image/jpeg", 0.82);
      document.getElementById("scanPhotoBase64").value = compressedDataUrl;

      const approxSizeKb = Math.round((compressedDataUrl.length * 3 / 4) / 1024);

      if (previewImg) previewImg.src = compressedDataUrl;
      if (previewContainer) previewContainer.classList.remove("d-none");
      if (compressBadge) {
        compressBadge.innerHTML = "<i class=\"bi bi-shield-check me-1\"></i> Siap Diunggah: <strong>" + approxSizeKb + " KB</strong> <span class=\"text-muted\">(Semula " + originalSizeKb + " KB)</span>";
      }
    };
    tempImg.src = e.target.result;
  };
  reader.readAsDataURL(file);
}

async function loadPhotoBioModels() {
  if (photoBioModelsLoaded) return true;
  if (photoBioModelsLoading) return true;
  photoBioModelsLoading = true;
  try {
    const statusBox = document.getElementById("bioPhotoStatusBox");
    if (statusBox) {
      statusBox.className = "alert alert-info py-2 px-3 small fw-semibold mt-3 mb-2 text-start";
      statusBox.innerHTML = "<span class=\"spinner-border spinner-border-sm me-2 text-primary\"></span> Mengunduh model pengenalan wajah AI...";
    }
    if (typeof faceapi !== "undefined" && faceapi.tf) {
      try {
        await faceapi.tf.setBackend("webgl");
        await faceapi.tf.ready();
      } catch(e) {}
    }
    await Promise.all([
      faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
      faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_URL),
      faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
    ]);
    photoBioModelsLoaded = true;
    photoBioModelsLoading = false;
    return true;
  } catch(err) {
    console.error("Gagal memuat face-api models:", err);
    photoBioModelsLoading = false;
    const statusBox = document.getElementById("bioPhotoStatusBox");
    if (statusBox) {
      statusBox.className = "alert alert-danger py-2 px-3 small fw-semibold mt-3 mb-2 text-start";
      statusBox.innerHTML = "<i class=\"bi bi-exclamation-triangle-fill me-1\"></i> Gagal memuat model AI biometrik. Periksa koneksi internet.";
    }
    return false;
  }
}

function calcEuclideanDist(a, b) {
  let s = 0;
  for (let i = 0; i < a.length; i++) {
    const d = a[i] - b[i];
    s += d * d;
  }
  return Math.sqrt(s);
}

function findBestMatchTech(queryVec) {
  let bestDist = 999;
  let bestTech = null;
  for (let i = 0; i < parsedEnrolledTechs.length; i++) {
    const t = parsedEnrolledTechs[i];
    const dist = calcEuclideanDist(queryVec, t.descriptor);
    if (dist < bestDist) {
      bestDist = dist;
      bestTech = t;
    }
  }
  return { tech: bestTech, dist: bestDist };
}

function captureVideoSnapshot(videoEl) {
  try {
    if (!videoEl || !videoEl.videoWidth || !videoEl.videoHeight) return "";
    const c = document.createElement("canvas");
    const outSize = 180;
    c.width = outSize;
    c.height = outSize;
    const ctx = c.getContext("2d");
    const s = Math.min(videoEl.videoWidth, videoEl.videoHeight);
    const sx = (videoEl.videoWidth - s) / 2;
    const sy = (videoEl.videoHeight - s) / 2;
    ctx.translate(outSize, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(videoEl, sx, sy, s, s, 0, 0, outSize, outSize);
    return c.toDataURL("image/jpeg", 0.65);
  } catch (err) {
    return "";
  }
}

function finalizePhotoFaceVerification(tech, dist, videoEl) {
  photoBioCompleted = true;
  if (photoBioTrackingTimer) clearInterval(photoBioTrackingTimer);

  let conf = Math.round((1.0 - (dist / 0.60)) * 100);
  if (conf > 99) conf = 99;
  if (conf < 75) conf = 75;

  document.getElementById("scanPhotoBioVerified").value = "1";
  document.getElementById("scanPhotoBioUserId").value = tech.id;
  document.getElementById("scanPhotoBioUserName").value = tech.nama;
  document.getElementById("scanPhotoBioConfidence").value = conf;

  const snap = captureVideoSnapshot(videoEl);
  document.getElementById("scanPhotoBioSnap").value = snap;

  const matchedNameEl = document.getElementById("bioPhotoMatchedName");
  if (matchedNameEl) matchedNameEl.textContent = tech.nama;
  const matchConfEl = document.getElementById("bioPhotoMatchConf");
  if (matchConfEl) matchConfEl.textContent = conf + "% Cocok";

  const successOverlay = document.getElementById("bioPhotoSuccessOverlay");
  if (successOverlay) successOverlay.classList.remove("d-none");

  const statusBox = document.getElementById("bioPhotoStatusBox");
  if (statusBox) {
    statusBox.className = "alert alert-success py-2 px-3 small fw-bold mt-3 mb-2 text-start";
    statusBox.innerHTML = "<i class=\"bi bi-check-circle-fill me-1\"></i> Wajah Terverifikasi: <strong>" + tech.nama + "</strong>! Menyimpan foto...";
  }

  if (photoBioVideoStream) {
    try { photoBioVideoStream.getTracks().forEach(t => t.stop()); } catch(e) {}
  }

  setTimeout(() => {
    document.getElementById("formUploadFotoUnit").submit();
  }, 700);
}

async function triggerInstantPhotoFaceVerify() {
  const video = document.getElementById("bioPhotoVideo");
  const statusBox = document.getElementById("bioPhotoStatusBox");
  if (!video || !video.videoWidth || photoBioCompleted) return;

  statusBox.className = "alert alert-warning py-2 px-3 small fw-bold mt-3 mb-2 text-start text-dark";
  statusBox.innerHTML = "<span class=\"spinner-border spinner-border-sm me-2 text-warning\"></span> Memproses pencocokan biometrik...";

  const detectorOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.3 });
  try {
    const detection = await faceapi.detectSingleFace(video, detectorOptions)
      .withFaceLandmarks(true)
      .withFaceDescriptor();

    if (!detection) {
      statusBox.className = "alert alert-warning py-2 px-3 small fw-semibold mt-3 mb-2 text-start text-dark";
      statusBox.innerHTML = "<i class=\"bi bi-exclamation-circle me-1\"></i> Wajah tidak terdeteksi. Posisikan wajah di tengah oval.";
      return;
    }

    if (!parsedEnrolledTechs.length) {
      statusBox.className = "alert alert-danger py-2 px-3 small fw-semibold mt-3 mb-2 text-start";
      statusBox.innerHTML = "<i class=\"bi bi-person-x me-1\"></i> Belum ada teknisi terdaftar di sistem Core Banking.";
      return;
    }

    const { tech, dist } = findBestMatchTech(detection.descriptor);
    if (tech && dist < 0.60) {
      finalizePhotoFaceVerification(tech, dist, video);
    } else {
      statusBox.className = "alert alert-danger py-2 px-3 small fw-semibold mt-3 mb-2 text-start";
      statusBox.innerHTML = "<i class=\"bi bi-person-x me-1\"></i> Wajah tidak cocok dengan teknisi terdaftar (Jarak: " + dist.toFixed(2) + "). Pastikan Anda terdaftar.";
    }
  } catch(err) {
    console.error("Error trigger bio verify:", err);
  }
}

async function startTeknisiFaceScanForPhoto() {
  const fileInput = document.getElementById("fotoBarangScanInput");
  const base64Input = document.getElementById("scanPhotoBase64");
  if ((!fileInput.files || !fileInput.files.length) && !base64Input.value) {
    alert("Silakan pilih atau jepret foto fisik perangkat terlebih dahulu!");
    fileInput.focus();
    return;
  }

  // Tutup modal upload foto
  const uploadModalEl = document.getElementById("modalUploadFotoTeknisi");
  if (uploadModalEl && typeof bootstrap !== "undefined") {
    const upModal = bootstrap.Modal.getInstance(uploadModalEl);
    if (upModal) upModal.hide();
  }

  // Buka modal kamera biometrik
  const bioModalEl = document.getElementById("modalFaceVerifyPhoto");
  if (bioModalEl && typeof bootstrap !== "undefined") {
    const bioModal = bootstrap.Modal.getInstance(bioModalEl) || new bootstrap.Modal(bioModalEl);
    bioModal.show();
  }

  photoBioCompleted = false;
  const successOverlay = document.getElementById("bioPhotoSuccessOverlay");
  if (successOverlay) successOverlay.classList.add("d-none");

  const statusBox = document.getElementById("bioPhotoStatusBox");
  if (statusBox) {
    statusBox.className = "alert alert-secondary py-2 px-3 small fw-semibold mt-3 mb-2 text-start text-dark";
    statusBox.innerHTML = "<span class=\"spinner-border spinner-border-sm me-2 text-primary\"></span> Menyiapkan kamera...";
  }

  // Nyalakan Kamera
  try {
    const stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: "user", width: { ideal: 640 }, height: { ideal: 480 } },
      audio: false
    });
    photoBioVideoStream = stream;
    const video = document.getElementById("bioPhotoVideo");
    video.srcObject = stream;
    await video.play();
  } catch(err) {
    console.error("Camera access error:", err);
    if (statusBox) {
      statusBox.className = "alert alert-danger py-2 px-3 small fw-semibold mt-3 mb-2 text-start";
      statusBox.innerHTML = "<i class=\"bi bi-camera-video-off me-1\"></i> Izin kamera tidak diberikan atau perangkat tidak memiliki webcam.";
    }
    return;
  }

  // Load Model AI
  const loaded = await loadPhotoBioModels();
  if (!loaded) return;

  if (statusBox) {
    statusBox.className = "alert alert-primary py-2 px-3 small fw-semibold mt-3 mb-2 text-start text-dark";
    statusBox.innerHTML = "<i class=\"bi bi-person-check me-1 text-primary\"></i> Posisikan wajah di tengah oval. Sistem otomatis mencocokkan wajah...";
  }

  // Tracking Loop Otomatis
  const detectorOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.35 });
  const video = document.getElementById("bioPhotoVideo");

  if (photoBioTrackingTimer) clearInterval(photoBioTrackingTimer);
  photoBioTrackingTimer = setInterval(async () => {
    if (photoBioCompleted || !video || !video.videoWidth || video.paused || video.ended) return;

    try {
      const detection = await faceapi.detectSingleFace(video, detectorOptions)
        .withFaceLandmarks(true)
        .withFaceDescriptor();

      if (detection && !photoBioCompleted) {
        if (!parsedEnrolledTechs.length) {
          if (statusBox) {
            statusBox.className = "alert alert-danger py-2 px-3 small fw-semibold mt-3 mb-2 text-start";
            statusBox.innerHTML = "<i class=\"bi bi-person-x me-1\"></i> Belum ada teknisi terdaftar.";
          }
          return;
        }

        const { tech, dist } = findBestMatchTech(detection.descriptor);
        if (tech && dist < 0.60) {
          finalizePhotoFaceVerification(tech, dist, video);
        } else {
          if (statusBox) {
            statusBox.className = "alert alert-warning py-2 px-3 small fw-semibold mt-3 mb-2 text-start text-dark";
            statusBox.innerHTML = "<i class=\"bi bi-search me-1 text-primary\"></i> Wajah terdeteksi, mencocokkan dengan data teknisi...";
          }
        }
      }
    } catch(e) {}
  }, 450);
}

function closePhotoFaceModal() {
  if (photoBioTrackingTimer) clearInterval(photoBioTrackingTimer);
  if (photoBioVideoStream) {
    try { photoBioVideoStream.getTracks().forEach(t => t.stop()); } catch(e) {}
  }
  const bioModalEl = document.getElementById("modalFaceVerifyPhoto");
  if (bioModalEl && typeof bootstrap !== "undefined") {
    const bioModal = bootstrap.Modal.getInstance(bioModalEl);
    if (bioModal) bioModal.hide();
  }
  openUploadFotoTeknisiModal();
}

function submitPhotoAsLoggedInAdmin() {
  const fileInput = document.getElementById("fotoBarangScanInput");
  const base64Input = document.getElementById("scanPhotoBase64");
  if ((!fileInput.files || !fileInput.files.length) && !base64Input.value) {
    alert("Silakan pilih atau jepret foto fisik perangkat terlebih dahulu!");
    fileInput.focus();
    return;
  }

  document.getElementById("scanPhotoBioVerified").value = "1";
  document.getElementById("scanPhotoBioUserName").value = "'.e(addslashes(current_user_name())).'";
  document.getElementById("scanPhotoBioConfidence").value = "100";
  
  const form = document.getElementById("formUploadFotoUnit");
  const btn = document.getElementById("btnMulaiScanWajahFoto");
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = "<span class=\"spinner-border spinner-border-sm me-2\"></span>Menyimpan Foto...";
  }
  form.submit();
}

// Pastikan jika pengguna kembali via tombol Back, halaman memuat data terbaru
window.addEventListener("pageshow", function(event) {
  if (event.persisted) {
    window.location.reload();
  }
});
</script>';
render_page('Detail Perangkat · ' . ($asset['kode_inventaris'] ?? 'QR'), $body, $headStyle, '', false);

