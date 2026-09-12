<?php
require __DIR__ . '/bootstrap.php';
$isLoggedIn = is_logged_in();

$id = max(0, (int)($_GET['id'] ?? 0));
if ($id <= 0) {
    render_page('Parameter Tidak Valid', '<div class="alert alert-danger">ID log maintenance tidak valid.</div>');
    exit;
}

$error = '';
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Handle Edit / Update Checklist POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'update_detail')) {
    require_login();
    verify_csrf();

    $newStatus = trim((string)($_POST['status'] ?? 'Selesai'));
    $newFindings = trim((string)($_POST['findings'] ?? ''));
    $newRecommendation = trim((string)($_POST['recommendation'] ?? ''));
    $newDate = trim((string)($_POST['maintenance_date'] ?? ''));
    $newTime = trim((string)($_POST['maintenance_time'] ?? ''));
    $newTechName = trim((string)($_POST['technician_name'] ?? ''));

    $newChecklists = [];
    $fixedItems = get_fixed_checklists();
    foreach ($fixedItems as $num => $name) {
        $checked = !empty($_POST['chk_' . $num]) ? 1 : 0;
        $notes = trim((string)($_POST['notes_' . $num] ?? ''));
        $newChecklists[$num] = [
            'checked' => $checked,
            'notes' => $notes
        ];
    }

    $res = update_maintenance_detail($id, [
        'status' => $newStatus,
        'findings' => $newFindings,
        'recommendation' => $newRecommendation,
        'maintenance_date' => $newDate,
        'maintenance_time' => $newTime,
        'technician_name' => $newTechName,
        'checklists' => $newChecklists
    ]);

    if (!empty($res['success'])) {
        $_SESSION['flash'] = "Data maintenance #{$id} berhasil diperbarui.";
        header('Location: ' . module_url('maintenance_detail.php', ['id' => $id]));
        exit;
    } else {
        $error = $res['error'] ?? 'Gagal memperbarui data maintenance.';
    }
}

$detail = get_maintenance_detail($id);
if (!$detail) {
    render_page('Data Tidak Ditemukan', '<div class="alert alert-warning">Data rincian maintenance dengan ID #'.$id.' tidak ditemukan.</div>');
    exit;
}

$scan = $detail['scan'];
$asset = $detail['asset'];
$checklists = $detail['checklists'];

$status = $scan['status'] ?? 'Selesai';
$statusBadge = ($status === 'Temuan' || $status === 'Perlu Perbaikan')
    ? '<span class="badge-chip chip-danger fs-6 px-3 py-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> Perlu Perbaikan</span>'
    : ($status === 'Proses'
        ? '<span class="badge-chip chip-warning fs-6 px-3 py-2"><i class="bi bi-hourglass-split me-1"></i> Sedang Proses</span>'
        : '<span class="badge-chip chip-success fs-6 px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i> Selesai (Normal)</span>');

// Checklist 9 item table & Edit form inputs
$chkTableRows = '';
$editChecklistRows = '';
$totalChecked = 0;

foreach ($checklists as $num => $c) {
    $isDone = !empty($c['checked']);
    if ($isDone) $totalChecked++;

    $icon = $isDone
        ? '<span class="badge-chip chip-success fw-bold px-3 py-1"><i class="bi bi-check2-circle me-1"></i> OK / Normal</span>'
        : '<span class="badge-chip chip-danger fw-bold px-3 py-1"><i class="bi bi-exclamation-triangle me-1"></i> Belum Selesai</span>';

    $noteText = !empty($c['notes']) ? e($c['notes']) : ($isDone ? 'Normal' : '-');

    $chkTableRows .= '
    <tr class="'.($isDone ? '' : 'table-warning text-dark').'" style="border-bottom: 1px solid var(--app-border);">
      <td class="text-center font-monospace fw-bold text-secondary" style="width: 50px;">'.sprintf('%02d', $num).'</td>
      <td class="fw-semibold text-dark">'.e($c['name']).'</td>
      <td class="text-center" style="width: 160px;">'.$icon.'</td>
      <td><span class="text-dark">'.$noteText.'</span></td>
    </tr>';

    $editChecklistRows .= '
    <div class="col-md-6 mb-3">
      <div class="p-3 border rounded-3 bg-light h-100" style="border-color: var(--app-border) !important;">
        <div class="form-check form-switch mb-2">
          <input class="form-check-input" type="checkbox" role="switch" id="chk_'.$num.'" name="chk_'.$num.'" value="1" '.($isDone ? 'checked' : '').'>
          <label class="form-check-label fw-bold text-dark" for="chk_'.$num.'">'.$num.'. '.e($c['name']).'</label>
        </div>
        <input type="text" class="form-control form-control-sm" name="notes_'.$num.'" value="'.e($c['notes'] ?? '').'" placeholder="Catatan opsional (default: Normal)...">
      </div>
    </div>';
}

$techName = $scan['technician_name'] ?? 'Teknisi';
$dateStr = !empty($scan['maintenance_date']) ? format_id_date($scan['maintenance_date']) : '-';
$timeStr = !empty($scan['maintenance_time']) ? substr($scan['maintenance_time'], 0, 5) : '-';
$findings = trim((string)($scan['findings'] ?? ''));
$recommendation = trim((string)($scan['recommendation'] ?? ''));

// Biometric & Location Audit Data
$isBioVerified = !empty($scan['biometric_verified']) && ((string)$scan['biometric_verified'] === '1' || strtolower((string)$scan['biometric_verified']) === 'true');
$bioPhoto = trim((string)($scan['biometric_photo'] ?? ''));
$lat = trim((string)($scan['latitude'] ?? ''));
$lng = trim((string)($scan['longitude'] ?? ''));

if ($isBioVerified) {
    if ($bioPhoto === '' && $techName !== '') {
        $enrolled = get_enrolled_technicians(false);
        foreach ($enrolled as $en) {
            if (strcasecmp((string)$en['nama'], $techName) === 0 && !empty($en['photo'])) {
                $bioPhoto = (string)$en['photo'];
                break;
            }
        }
    }

    $photoThumb = $bioPhoto !== ''
        ? '<img src="'.e($bioPhoto).'" class="rounded border shadow-sm" style="width: 52px; height: 52px; object-fit: cover; border-color: var(--app-border) !important;" alt="Foto Petugas">'
        : '<div class="bg-light text-secondary rounded d-flex align-items-center justify-content-center border" style="width: 52px; height: 52px;"><i class="bi bi-person fs-3"></i></div>';

    $gpsLink = ($lat !== '' && $lng !== '')
        ? '<a href="https://maps.google.com/?q='.e($lat).','.e($lng).'" target="_blank" class="btn btn-outline-danger btn-sm text-decoration-none py-1 px-2"><i class="bi bi-geo-alt-fill me-1"></i> GPS: '.e(round((float)$lat, 4)).', '.e(round((float)$lng, 4)).'</a>'
        : '<span class="text-muted small"><i class="bi bi-geo-alt me-1"></i> GPS: Tidak terlampir</span>';

    $bioAuditHtml = '
    <div class="card border mb-4 shadow-sm" style="border-radius: 8px; border-color: var(--app-border) !important; background: #fff;">
      <div class="card-body p-3">
        <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3">
          <div class="d-flex align-items-center gap-3">
            '.$photoThumb.'
            <div>
              <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <span class="badge-chip chip-success"><i class="bi bi-check-circle-fill me-1"></i> Presensi Biometrik Terverifikasi</span>
                <span class="badge-chip chip-secondary"><i class="bi bi-person-badge me-1"></i> Teknisi IT Lapangan</span>
              </div>
              <div class="text-secondary small">Diselesaikan oleh <strong>'.e($techName).'</strong> dengan konfirmasi kehadiran saat checklist diisi.</div>
            </div>
          </div>
          <div>
            '.$gpsLink.'
          </div>
        </div>
      </div>
    </div>';
} else {
    $bioAuditHtml = '
    <div class="card border mb-4 shadow-sm" style="border-radius: 8px; border-color: var(--app-border) !important; background: #FAFBFD;">
      <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2 small">
        <div class="text-secondary"><i class="bi bi-person-badge me-1"></i> Konfirmasi Petugas: <strong class="text-dark">'.e($techName).'</strong> (Pencatatan Reguler)</div>
        <span class="badge-chip chip-secondary font-monospace" style="font-size: 0.72rem;">Tanpa Sampel Biometrik</span>
      </div>
    </div>';
}

$karyawanList = get_karyawan_list();
$techOptions = '';
foreach ($karyawanList as $k) {
    $kn = $k['nama_karyawan'] ?? $k['nama'] ?? '';
    if ($kn !== '') {
        $techOptions .= '<option value="'.e($kn).'">';
    }
}

$flashHtml = $flash ? '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm no-print" style="border-left: 4px solid #10B981 !important;"><i class="bi bi-check-circle-fill me-2 text-success"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';
$errorHtml = $error ? '<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm no-print" style="border-left: 4px solid #EF4444 !important;"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>'.e($error).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$assetId = (int)($asset['id'] ?? 0);
$assetHistory = get_asset_maintenance_history($assetId);
$totalMaintCount = count($assetHistory);

// Navigasi maintenance sebelum / sesudah untuk komputer yang sama
$prevLogId = 0;
$nextLogId = 0;
for ($idx = 0; $idx < $totalMaintCount; $idx++) {
    if ((int)($assetHistory[$idx]['id'] ?? 0) === $id) {
        if (isset($assetHistory[$idx - 1])) {
            $nextLogId = (int)($assetHistory[$idx - 1]['id'] ?? 0); // Lebih baru
        }
        if (isset($assetHistory[$idx + 1])) {
            $prevLogId = (int)($assetHistory[$idx + 1]['id'] ?? 0); // Lebih lama
        }
        break;
    }
}

// Ambil SELURUH sesi sistem (pencatatan semua sesi)
$allSessions = get_history_rows(0, 0, 0);
$totalAllSessions = count($allSessions);

$navPrevBtn = $prevLogId > 0
    ? '<a href="'.e(module_url('maintenance_detail.php', ['id' => $prevLogId])).'" class="btn btn-outline-secondary btn-sm" title="Lihat maintenance sebelumnya pada komputer ini"><i class="bi bi-chevron-left me-1"></i> Sebelumnya (#'.$prevLogId.')</a>'
    : '';

$navNextBtn = $nextLogId > 0
    ? '<a href="'.e(module_url('maintenance_detail.php', ['id' => $nextLogId])).'" class="btn btn-outline-secondary btn-sm" title="Lihat maintenance berikutnya pada komputer ini">Berikutnya (#'.$nextLogId.') <i class="bi bi-chevron-right ms-1"></i></a>'
    : '';

$editBtnTop = $isLoggedIn
    ? '<button type="button" class="btn btn-warning text-dark fw-bold btn-sm" data-bs-toggle="modal" data-bs-target="#editChecklistModal"><i class="bi bi-pencil-square me-1"></i> Edit Data & Checklist</button>'
    : '<a class="btn btn-outline-primary btn-sm fw-semibold" href="'.e(module_url('login.php')).'"><i class="bi bi-box-arrow-in-right me-1"></i> Login untuk Edit</a>';

$backBtnTop = $isLoggedIn
    ? '<a class="btn btn-outline-secondary btn-sm fw-semibold" href="'.e(module_url('audit.php')).'"><i class="bi bi-arrow-left me-1"></i> Riwayat Audit</a>'
    : (!empty($asset['token'])
        ? '<a class="btn btn-outline-secondary btn-sm fw-semibold" href="'.e(module_url('scan.php', ['t' => $asset['token']])).'"><i class="bi bi-card-checklist me-1"></i> Kartu Perangkat</a>'
        : '<a class="btn btn-outline-secondary btn-sm fw-semibold" href="javascript:history.back()"><i class="bi bi-arrow-left me-1"></i> Kembali</a>');

$tindakBtnTop = (!empty($asset['token']) && ($status === 'Temuan' || $status === 'Perlu Perbaikan' || $status === 'Proses'))
    ? '<a class="btn btn-danger btn-sm fw-bold" href="'.e(module_url('scan.php', ['t' => $asset['token'], 'action' => 'tindak_lanjut'])).'"><i class="bi bi-tools me-1"></i> Form Tindak Lanjut</a>'
    : '';

// Tabel Sesi Komputer Ini (Tab 1)
$thisAssetRowsHtml = '';
if (empty($assetHistory)) {
    $thisAssetRowsHtml = '<tr><td colspan="7" class="text-center text-muted py-4">Belum ada riwayat maintenance lain yang tercatat untuk komputer ini.</td></tr>';
} else {
    foreach ($assetHistory as $h) {
        $hId = (int)($h['id'] ?? 0);
        $isCurrent = ($hId === $id);
        $hDate = format_id_date($h['maintenance_date'] ?? '');
        $hTime = substr((string)($h['maintenance_time'] ?? ''), 0, 5);
        $hTech = $h['technician_name'] ?? 'Teknisi';
        $hStatus = $h['status'] ?? 'Selesai';
        $hType = $h['maintenance_type'] ?? 'Maintenance';
        $hFindings = trim((string)($h['findings'] ?? ''));

        $hBadge = ($hStatus === 'Temuan' || $hStatus === 'Perlu Perbaikan')
            ? '<span class="badge-chip chip-danger"><i class="bi bi-exclamation-triangle-fill"></i> Temuan</span>'
            : ($hStatus === 'Proses'
                ? '<span class="badge-chip chip-warning"><i class="bi bi-hourglass-split"></i> Proses</span>'
                : '<span class="badge-chip chip-success"><i class="bi bi-check-circle-fill"></i> Selesai</span>');

        $rowBg = $isCurrent ? 'style="background-color: #EAF3FF; font-weight: 600;"' : 'style="border-bottom: 1px solid var(--app-border);"';
        $currentBadge = $isCurrent ? ' <span class="badge-chip chip-primary ms-1" style="font-size:0.68rem;"><i class="bi bi-eye"></i> Sedang Dibuka</span>' : '';

        $thisAssetRowsHtml .= '
        <tr '.$rowBg.'>
          <td class="text-center font-monospace fw-bold text-primary">#'.$hId.'</td>
          <td>'.$hDate.' <span class="text-muted small">('.$hTime.' WITA)</span>'.$currentBadge.'</td>
          <td><i class="bi bi-person text-secondary me-1"></i>'.e($hTech).'</td>
          <td>'.e($hType).'</td>
          <td class="text-center">'.$hBadge.'</td>
          <td>'.($hFindings !== '' && $hFindings !== '-' ? '<span class="text-danger fw-semibold">'.e($hFindings).'</span>' : '<span class="text-muted">Normal</span>').'</td>
          <td class="text-center text-nowrap">
            '.($isCurrent 
                ? '<span class="badge-chip chip-primary" style="font-size: 0.72rem;"><i class="bi bi-check-lg"></i> Aktif</span>' 
                : '<a href="'.e(module_url('maintenance_detail.php', ['id' => $hId])).'" class="btn btn-sm btn-outline-secondary py-0 px-2 small">Buka #'.$hId.'</a>').'
          </td>
        </tr>';
    }
}

// Tabel Seluruh Sesi Sistem (Tab 2)
$allSessionsRowsHtml = '';
if (empty($allSessions)) {
    $allSessionsRowsHtml = '<tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>Belum ada riwayat sesi maintenance yang tercatat di sistem.</td></tr>';
} else {
    $sNo = 0;
    foreach ($allSessions as $sRow) {
        $sNo++;
        $sId = (int)($sRow['id'] ?? 0);
        $isCurrent = ($sId === $id);
        $sDate = format_id_date($sRow['maintenance_date'] ?? '');
        $sTime = substr((string)($sRow['maintenance_time'] ?? ''), 0, 5);
        $sTech = $sRow['technician_name'] ?? 'Teknisi';
        $sStatus = $sRow['status'] ?? 'Selesai';
        $sDevName = !empty($sRow['perangkat']) ? $sRow['perangkat'] : (!empty($sRow['nama_perangkat']) ? $sRow['nama_perangkat'] : trim(($sRow['merk'] ?? '').' '.($sRow['model'] ?? '')));
        if ($sDevName === '') $sDevName = 'Perangkat IT';
        $sKode = $sRow['kode_inventaris'] ?? ('INV-IT-' . sprintf('%03d', $sRow['asset_id'] ?? 0));
        $sCabang = $sRow['cabang_nama'] ?? '-';
        $sFindings = trim((string)($sRow['findings'] ?? ''));

        $sBadge = ($sStatus === 'Temuan' || $sStatus === 'Perlu Perbaikan')
            ? '<span class="badge-chip chip-danger"><i class="bi bi-exclamation-triangle-fill"></i> Temuan</span>'
            : ($sStatus === 'Proses'
                ? '<span class="badge-chip chip-warning"><i class="bi bi-hourglass-split"></i> Proses</span>'
                : '<span class="badge-chip chip-success"><i class="bi bi-check-circle-fill"></i> Selesai</span>');

        $rowBg = $isCurrent ? 'style="background-color: #EAF3FF; font-weight: 600;"' : 'style="border-bottom: 1px solid var(--app-border);"';
        $currentBadge = $isCurrent ? ' <span class="badge-chip chip-primary ms-1" style="font-size:0.68rem;"><i class="bi bi-eye"></i> Sedang Dibuka</span>' : '';

        $allSessionsRowsHtml .= '
        <tr '.$rowBg.'>
          <td class="text-center font-monospace fw-bold text-primary">#'.$sId.'</td>
          <td>
            <div class="small">'.$sDate.'</div>
            <div class="text-muted font-monospace" style="font-size: 0.72rem;">'.$sTime.' WITA'.$currentBadge.'</div>
          </td>
          <td>
            <div class="fw-bold font-monospace text-primary" style="font-size: 0.84rem;">'.e($sKode).'</div>
            <div class="text-muted small text-truncate" style="max-width: 200px;">'.e($sDevName).'</div>
          </td>
          <td><span class="badge-chip chip-secondary">'.e($sCabang).'</span></td>
          <td class="small"><i class="bi bi-person text-secondary me-1"></i>'.e($sTech).'</td>
          <td class="text-center">'.$sBadge.'</td>
          <td>'.($sFindings !== '' && $sFindings !== '-' ? '<span class="text-danger fw-semibold small">'.e($sFindings).'</span>' : '<span class="text-muted small">Normal</span>').'</td>
          <td class="text-center text-nowrap">
            '.($isCurrent 
                ? '<span class="badge-chip chip-primary" style="font-size: 0.72rem;"><i class="bi bi-check-lg"></i> Aktif</span>' 
                : '<a href="'.e(module_url('maintenance_detail.php', ['id' => $sId])).'" class="btn btn-sm btn-outline-secondary py-0 px-2 small">Buka #'.$sId.'</a>').'
          </td>
        </tr>';
    }
}

$body = '
'.$flashHtml.'
'.$errorHtml.'

<!-- Header Kicker & Action Bar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 no-print">
  <div>
    <div class="text-uppercase small fw-bold" style="letter-spacing: 0.08em; color: var(--app-accent); font-size: 0.72rem; margin-bottom: 2px;">MONITORING / MAINTENANCE AUDIT RECORD</div>
    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
      <h2 class="h3 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-file-earmark-medical text-primary"></i> Rincian Hasil Maintenance #'.$id.'
      </h2>
      <span class="badge-chip chip-primary font-monospace" style="font-size: 0.75rem;">Sesi ke-'.($totalMaintCount > 0 ? (array_search($id, array_column($assetHistory, 'id')) !== false ? ($totalMaintCount - array_search($id, array_column($assetHistory, 'id'))) : '1') : '1').' dari '.$totalMaintCount.' Sesi Komputer Ini</span>
    </div>
    <div class="text-muted small">Pencatatan 9 checklist pemeliharaan resmi komputer <strong>'.e(asset_title($asset)).'</strong> ('.e($asset['kode_inventaris'] ?? '-').').</div>
  </div>
  <div class="d-flex flex-wrap gap-2 align-items-center">
    '.$navPrevBtn.'
    '.$navNextBtn.'
    '.$backBtnTop.'
    '.$tindakBtnTop.'
    '.$editBtnTop.'
    <button class="btn btn-primary fw-semibold btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak Dokumen</button>
  </div>
</div>

<div class="row g-4">
  <div class="col-12">
    
    <!-- Card 1: Detail Perangkat Komputer -->
    <div class="card p-0 border shadow-sm mb-4" style="border-radius: 8px; border-color: var(--app-border) !important; background: #fff;">
      <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center" style="border-bottom: 1px solid var(--app-border);">
        <div class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.05em;">
          <i class="bi bi-laptop text-primary me-2"></i>01 · Detail Perangkat Komputer
        </div>
        <span class="badge-chip chip-secondary font-monospace" style="font-size: 0.72rem;">ID ASET #'.(int)($asset['id'] ?? 0).'</span>
      </div>
      <div class="card-body p-4">
        <div class="row g-3 small">
          <div class="col-md-3">
            <div class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em;">Kode Inventaris:</div>
            <div class="fw-bold font-monospace text-primary fs-6">'.e($asset['kode_inventaris'] ?? '-').'</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em;">Serial Number:</div>
            <div class="fw-semibold font-monospace text-dark">'.e($asset['serial_number'] ?? '-').'</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em;">Jenis / Kategori:</div>
            <div class="fw-semibold text-dark">'.e($asset['kategori_nama'] ?? 'Perangkat IT').'</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em;">Perangkat (Merk & Model):</div>
            <div class="fw-bold text-dark">'.e(asset_title($asset)).'</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em;">Penanggung Jawab / User:</div>
            <div class="fw-bold text-dark"><i class="bi bi-person-circle text-primary me-1"></i>'.e($asset['karyawan_nama'] ?? '-').'</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em;">Lokasi Cabang & Divisi:</div>
            <div class="fw-semibold text-dark">'.e($asset['cabang_nama'] ?? '-').' · '.e($asset['divisi_nama'] ?? '-').'</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em;">Alamat IP (IP Address):</div>
            <div class="fw-bold font-monospace text-primary">'.e($asset['ip_address'] ?? $asset['ip'] ?? '-').'</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em;">Printer Terhubung:</div>
            <div class="fw-semibold text-dark">'.e($asset['printer'] ?? '-').'</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Card 2: Detail Pelaksanaan Maintenance & 9 Checklist -->
    <div class="card p-0 border shadow-sm mb-4" style="border-radius: 8px; border-color: var(--app-border) !important; background: #fff;">
      <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center" style="border-bottom: 1px solid var(--app-border);">
        <div class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.05em;">
          <i class="bi bi-calendar2-check text-primary me-2"></i>02 · Detail Pelaksanaan Pemeliharaan
        </div>
        '.$statusBadge.'
      </div>
      <div class="card-body p-4">
        <div class="row g-3 small mb-4">
          <div class="col-md-3">
            <div class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em;">Tanggal Pelaksanaan:</div>
            <div class="fw-bold text-dark fs-6">'.e($dateStr).'</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em;">Waktu / Jam:</div>
            <div class="fw-semibold text-dark">'.e($timeStr).' WITA</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em;">Petugas / Teknisi:</div>
            <div class="fw-bold text-primary"><i class="bi bi-person-badge me-1"></i>'.e($techName).'</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.04em;">Status Checklist:</div>
            <div class="fw-bold text-dark fs-6">'.$totalChecked.' / 9 Selesai</div>
          </div>
        </div>

        <!-- Audit Presensi Petugas -->
        '.$bioAuditHtml.'

        <!-- Checklist Table -->
        <div class="fw-bold text-dark text-uppercase small mb-2" style="letter-spacing: 0.04em;">
          <i class="bi bi-card-checklist text-primary me-1"></i>Status 9 Item Pemeriksaan Standar:
        </div>
        <div class="table-responsive border rounded mb-4" style="border-color: var(--app-border) !important;">
          <table class="table table-hover align-middle mb-0 small">
            <thead style="background-color: var(--app-navy); color: #ffffff;">
              <tr>
                <th class="text-center font-monospace" style="width: 50px; background-color: var(--app-navy); color: #ffffff;">NO</th>
                <th style="background-color: var(--app-navy); color: #ffffff;">KOMPONEN / ITEM PEMERIKSAAN</th>
                <th class="text-center" style="width: 160px; background-color: var(--app-navy); color: #ffffff;">KONDISI</th>
                <th style="background-color: var(--app-navy); color: #ffffff;">CATATAN / KETERANGAN</th>
              </tr>
            </thead>
            <tbody>
              '.$chkTableRows.'
            </tbody>
          </table>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <div class="p-3 bg-light rounded border h-100" style="border-color: var(--app-border) !important;">
              <div class="text-muted text-uppercase fw-bold small mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                <i class="bi bi-exclamation-triangle text-warning me-1"></i>Ringkasan Temuan / Masalah:
              </div>
              <div class="text-dark">'.($scan['findings'] ? nl2br(e($scan['findings'])) : '<span class="text-muted fst-italic">Tidak ada temuan kendala (Normal).</span>').'</div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-3 bg-light rounded border h-100" style="border-color: var(--app-border) !important;">
              <div class="text-muted text-uppercase fw-bold small mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                <i class="bi bi-lightbulb text-info me-1"></i>Rekomendasi Tindak Lanjut:
              </div>
              <div class="text-dark">'.($scan['recommendation'] ? nl2br(e($scan['recommendation'])) : '<span class="text-muted fst-italic">Tidak ada rekomendasi khusus.</span>').'</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Card 3: Riwayat Pencatatan Sesi Pemeliharaan (Sesi Komputer Ini vs Seluruh Sistem) -->
    <div class="card p-0 border shadow-sm mb-4" style="border-radius: 8px; border-color: var(--app-border) !important; background: #fff;">
      <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2" style="border-bottom: 1px solid var(--app-border);">
        <div>
          <div class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.05em;">
            <i class="bi bi-clock-history text-primary me-2"></i>03 · Pencatatan Sesi Pemeliharaan (Audit Sesi)
          </div>
          <div class="text-muted small mt-1">Lihat rekam jejak sesi pemeliharaan komputer ini atau seluruh sesi pencatatan sistem operasional.</div>
        </div>
        <div class="d-flex align-items-center gap-2">
          '.(!empty($asset['token']) ? '<a href="'.e(module_url('scan.php', ['t' => $asset['token']])).'" class="btn btn-outline-secondary btn-sm fw-semibold"><i class="bi bi-card-checklist me-1"></i> Kartu Kontrol 12 Bulan</a>' : '').'
          <a href="'.e(module_url('audit.php')).'" class="btn btn-outline-primary btn-sm fw-semibold"><i class="bi bi-table me-1"></i> Halaman Audit Trail Lengkap</a>
        </div>
      </div>
      
      <div class="card-body p-4">
        <!-- Dual Tabs: Sesi Komputer Ini vs Seluruh Sesi Sistem -->
        <ul class="nav nav-pills mb-3 gap-2" id="pills-session-tab" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold small py-2 px-3 border" id="pills-this-asset-tab" data-bs-toggle="pill" data-bs-target="#pills-this-asset" type="button" role="tab" aria-controls="pills-this-asset" aria-selected="true" style="border-radius: 6px;">
              <i class="bi bi-laptop me-1"></i> Sesi Perangkat Ini ('.$totalMaintCount.' Sesi)
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold small py-2 px-3 border" id="pills-all-sessions-tab" data-bs-toggle="pill" data-bs-target="#pills-all-sessions" type="button" role="tab" aria-controls="pills-all-sessions" aria-selected="false" style="border-radius: 6px;">
              <i class="bi bi-collection me-1"></i> Semua Sesi Pemeliharaan Sistem (Total '.$totalAllSessions.' Sesi Tercatat)
            </button>
          </li>
        </ul>

        <div class="tab-content" id="pills-session-tabContent">
          <!-- TAB 1: Sesi Komputer Ini -->
          <div class="tab-pane fade show active" id="pills-this-asset" role="tabpanel" aria-labelledby="pills-this-asset-tab">
            <div class="table-responsive rounded border" style="border-color: var(--app-border) !important;">
              <table class="table table-hover align-middle mb-0 small">
                <thead style="background-color: var(--app-navy); color: #ffffff;">
                  <tr>
                    <th style="width: 60px; background-color: var(--app-navy); color: #ffffff;" class="text-center font-monospace">LOG ID</th>
                    <th style="background-color: var(--app-navy); color: #ffffff;">TANGGAL & WAKTU</th>
                    <th style="background-color: var(--app-navy); color: #ffffff;">PETUGAS / TEKNISI</th>
                    <th style="background-color: var(--app-navy); color: #ffffff;">JENIS SESI</th>
                    <th style="width: 140px; background-color: var(--app-navy); color: #ffffff;" class="text-center">STATUS</th>
                    <th style="background-color: var(--app-navy); color: #ffffff;">TEMUAN / MASALAH</th>
                    <th style="width: 110px; background-color: var(--app-navy); color: #ffffff;" class="text-center">AKSI</th>
                  </tr>
                </thead>
                <tbody>
                  '.$thisAssetRowsHtml.'
                </tbody>
              </table>
            </div>
          </div>

          <!-- TAB 2: Seluruh Sesi Sistem -->
          <div class="tab-pane fade" id="pills-all-sessions" role="tabpanel" aria-labelledby="pills-all-sessions-tab">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="text-muted small">Menampilkan seluruh catatan sesi pemeliharaan yang terekam di sistem IT Bank Mitra.</div>
              <span class="badge-chip chip-primary font-monospace">'.$totalAllSessions.' Total Sesi</span>
            </div>
            <div class="table-responsive rounded border" style="border-color: var(--app-border) !important; max-height: 480px; overflow-y: auto;">
              <table class="table table-hover align-middle mb-0 small">
                <thead style="background-color: var(--app-navy); color: #ffffff; position: sticky; top: 0; z-index: 1;">
                  <tr>
                    <th style="width: 60px; background-color: var(--app-navy); color: #ffffff;" class="text-center font-monospace">LOG ID</th>
                    <th style="width: 140px; background-color: var(--app-navy); color: #ffffff;">WAKTU & TANGGAL</th>
                    <th style="background-color: var(--app-navy); color: #ffffff;">KODE & PERANGKAT</th>
                    <th style="background-color: var(--app-navy); color: #ffffff;">CABANG</th>
                    <th style="background-color: var(--app-navy); color: #ffffff;">TEKNISI</th>
                    <th style="width: 120px; background-color: var(--app-navy); color: #ffffff;" class="text-center">STATUS</th>
                    <th style="background-color: var(--app-navy); color: #ffffff;">TEMUAN / CATATAN</th>
                    <th style="width: 90px; background-color: var(--app-navy); color: #ffffff;" class="text-center">AKSI</th>
                  </tr>
                </thead>
                <tbody>
                  '.$allSessionsRowsHtml.'
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div>
</div>';

// Render Modal Edit jika pengguna sudah login
if ($isLoggedIn) {
    $body .= '
<!-- Modal Edit Checklist & Data Maintenance -->
<div class="modal fade" id="editChecklistModal" tabindex="-1" aria-labelledby="editChecklistModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="post">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="action" value="update_detail">

        <div class="modal-header bg-white py-3 px-4" style="border-bottom: 1px solid var(--app-border);">
          <div>
            <div class="text-uppercase small fw-bold text-warning" style="font-size: 0.72rem; letter-spacing: 0.06em;">MODIFIKASI HASIL AUDIT</div>
            <h5 class="modal-title fw-bold text-dark mb-0" id="editChecklistModalLabel"><i class="bi bi-pencil-square text-warning me-2"></i>Perbarui Data Maintenance & Checklist #'.$id.'</h5>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">
          <!-- 1. Edit Tanggal & Petugas Pelaksana -->
          <div class="p-3 bg-light rounded border mb-4" style="border-color: var(--app-border) !important;">
            <div class="fw-bold text-dark text-uppercase small mb-2" style="font-size: 0.75rem; letter-spacing: 0.04em;">
              <i class="bi bi-calendar-event text-primary me-1"></i>Waktu Pelaksanaan & Petugas:
            </div>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Tanggal Pelaksanaan <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="maintenance_date" value="'.e(substr((string)($scan['maintenance_date'] ?? ''), 0, 10)).'" required>
                <div class="form-text text-muted" style="font-size: 0.73rem;">Bulan pada Kartu Kontrol akan otomatis disesuaikan.</div>
              </div>
              <div class="col-md-4">
                <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Jam / Waktu</label>
                <input type="time" class="form-control" name="maintenance_time" value="'.e(substr((string)($scan['maintenance_time'] ?? '10:00'), 0, 5)).'">
              </div>
              <div class="col-md-4">
                <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Petugas / Teknisi <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="technician_name" list="listTeknisiEdit" value="'.e($techName).'" required placeholder="Nama petugas">
                <datalist id="listTeknisiEdit">'.$techOptions.'</datalist>
              </div>
            </div>
          </div>

          <!-- 2. Status, Temuan, Rekomendasi -->
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Status Hasil Maintenance</label>
              <select class="form-select" name="status">
                <option value="Selesai" '.($status==='Selesai'?'selected':'').'>✅ Selesai (Normal)</option>
                <option value="Temuan" '.($status==='Temuan'?'selected':'').'>⚠️ Temuan (Ada Masalah)</option>
                <option value="Perlu Perbaikan" '.($status==='Perlu Perbaikan'?'selected':'').'>🚨 Perlu Perbaikan</option>
                <option value="Proses" '.($status==='Proses'?'selected':'').'>⏳ Sedang Proses</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Temuan / Catatan Kerusakan</label>
              <input type="text" class="form-control" name="findings" value="'.e($findings !== '-' ? $findings : '').'" placeholder="Ketik temuan jika ada...">
            </div>
            <div class="col-md-4">
              <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Rekomendasi / Tindakan</label>
              <input type="text" class="form-control" name="recommendation" value="'.e($recommendation !== '-' ? $recommendation : '').'" placeholder="Tindakan yang dilakukan...">
            </div>
          </div>

          <div class="fw-bold text-dark text-uppercase small border-bottom pb-2 mb-3" style="font-size: 0.75rem; letter-spacing: 0.04em;">
            <i class="bi bi-check2-square text-primary me-1"></i>9 Item Pemeriksaan Standar:
          </div>
          <div class="row">
            '.$editChecklistRows.'
          </div>
        </div>

        <div class="modal-footer bg-light" style="border-top: 1px solid var(--app-border);">
          <button type="button" class="btn btn-outline-secondary fw-semibold" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary fw-bold px-4"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
</div>';
}

render_page('Detail Maintenance #' . $id, $body, '', '', $isLoggedIn);
