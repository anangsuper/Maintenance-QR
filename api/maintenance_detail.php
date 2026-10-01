<?php
require __DIR__ . '/bootstrap.php';
// Akses rincian pemeliharaan bersifat publik saat diakses via scan QR, login hanya diwajibkan saat edit data
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

if (empty($asset) || !is_array($asset)) {
    $assetAid = (int)($scan['asset_id'] ?? 0);
    $scanNama = trim((string)($scan['nama_perangkat'] ?? ''));
    $scanKode = trim((string)($scan['kode_inventaris'] ?? ''));
    $asset = [
        'id' => $assetAid,
        'kode_inventaris' => $scanKode !== '' ? $scanKode : ('INV-IT-' . sprintf('%03d', $assetAid)),
        'merk' => $scanNama !== '' ? $scanNama : 'Perangkat Terhapus / Tidak Ditemukan',
        'model' => '',
        'serial_number' => '-',
        'kategori_nama' => 'Komputer',
        'cabang_nama' => '-',
        'divisi_nama' => '-',
        'karyawan_nama' => '-',
        'status' => 'Nonaktif',
        'ip_address' => '-',
        'printer' => '-',
        'qr_token' => ''
    ];
}

$token = trim((string)($_GET['t'] ?? ''));
if ($token === '' && !empty($asset['token'])) {
    $token = (string)$asset['token'];
}
if ($token === '' && !empty($asset['qr_token'])) {
    $token = (string)$asset['qr_token'];
}
if ($token === '' && !empty($asset['id'])) {
    $token = get_static_qr_token((int)$asset['id']);
}

$status = $scan['status'] ?? 'Selesai';
$statusBadge = ($status === 'Temuan' || $status === 'Perlu Perbaikan')
    ? '<span class="badge-chip chip-danger fs-6 px-3 py-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> Perlu Perbaikan</span>'
    : ($status === 'Proses'
        ? '<span class="badge-chip chip-warning fs-6 px-3 py-2"><i class="bi bi-hourglass-split me-1"></i> Sedang Proses</span>'
        : '<span class="badge-chip chip-success fs-6 px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i> Selesai (Normal)</span>');

// Checklist 9 item table & Edit form inputs
$chkTableRows = '';
$chkMobileCards = '';
$editChecklistRows = '';
$totalChecked = 0;

foreach ($checklists as $num => $c) {
    $isDone = !empty($c['checked']);
    if ($isDone) $totalChecked++;

    $icon = $isDone
        ? '<span class="badge-chip chip-success fw-bold px-2.5 px-sm-3 py-1"><i class="bi bi-check2-circle me-1"></i> OK / Normal</span>'
        : '<span class="badge-chip chip-danger fw-bold px-2.5 px-sm-3 py-1"><i class="bi bi-exclamation-triangle me-1"></i> Belum Selesai</span>';

    $noteText = !empty($c['notes']) ? e($c['notes']) : ($isDone ? 'Normal' : '-');

    $chkTableRows .= '
    <tr class="'.($isDone ? '' : 'table-warning text-dark').'" style="border-bottom: 1px solid var(--app-border);">
      <td class="text-center font-monospace fw-bold text-secondary" style="width: 50px;">'.sprintf('%02d', $num).'</td>
      <td class="fw-semibold text-dark">'.e($c['name']).'</td>
      <td class="text-center" style="width: 160px;">'.$icon.'</td>
      <td><span class="text-dark">'.$noteText.'</span></td>
    </tr>';

    $chkMobileCards .= '
    <div class="checklist-mobile-item p-2.5 p-sm-3 mb-2 rounded-3 border '.($isDone ? 'bg-white shadow-xs' : 'table-warning bg-opacity-25').'" style="border-left: 4px solid '.($isDone ? '#10B981' : '#EF4444').' !important; border-color: var(--app-border) !important;">
      <div class="d-flex justify-content-between align-items-center gap-2 mb-1.5">
        <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
          <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 font-monospace fw-bold flex-shrink-0" style="font-size: 0.72rem; padding: 2px 6px;">#'.sprintf('%02d', $num).'</span>
          <span class="fw-bold text-dark text-truncate" style="font-size: 0.88rem; line-height: 1.35;" title="'.e($c['name']).'">'.e($c['name']).'</span>
        </div>
        <div class="flex-shrink-0">'.$icon.'</div>
      </div>
      <div class="pt-1.5 border-top d-flex align-items-center gap-1.5 text-secondary" style="font-size: 0.78rem; border-color: rgba(0,0,0,0.06) !important;">
        <i class="bi bi-chat-left-text me-1 opacity-75"></i>
        <span class="text-muted">Keterangan:</span>
        <span class="fw-semibold '.($isDone ? 'text-dark' : 'text-danger').'">'.$noteText.'</span>
      </div>
    </div>';

    $editChecklistRows .= '
    <div class="col-12 col-md-6 mb-3">
      <div class="p-3 border rounded-3 bg-light h-100" style="border-color: var(--app-border) !important;">
        <div class="form-check form-switch mb-2">
          <input class="form-check-input" type="checkbox" role="switch" id="chk_'.$num.'" name="chk_'.$num.'" value="1" '.($isDone ? 'checked' : '').' style="cursor: pointer; width: 2.2em; height: 1.2em;">
          <label class="form-check-label fw-bold text-dark ps-1" for="chk_'.$num.'">'.$num.'. '.e($c['name']).'</label>
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

// Maintenance Type & Susulan Detection
$scanType = $scan['source'] ?? $scan['maintenance_type'] ?? 'Maintenance';
$isSusulan = (stripos($scanType, 'susulan') !== false);
$scanMonth = (int)($scan['maintenance_month'] ?? 0);
$scanYear = (int)($scan['maintenance_year'] ?? 0);
$scanMonthName = $monthNames[$scanMonth] ?? ($scanMonth > 0 ? ('Bulan ' . $scanMonth) : '');

$susulanAlertHtml = '';
if ($isSusulan) {
    $susulanAlertHtml = '
    <div class="alert alert-warning py-2.5 px-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2 border-warning border-start border-4 rounded-3 shadow-xs">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-clock-history text-warning fs-4 flex-shrink-0"></i>
        <div>
          <strong class="text-dark">Maintenance Susulan:</strong>
          <span class="text-secondary small">Hasil pemeriksaan ini dicatat untuk mengisi Kartu Kontrol periode <strong>'.e($scanMonthName).' '.e($scanYear).'</strong> (Tanggal pelaksanaan aktual: '.e($dateStr).').</span>
        </div>
      </div>
      <span class="badge bg-warning text-dark fw-bold px-2 py-1"><i class="bi bi-tag-fill me-1"></i> Sesi Susulan</span>
    </div>';
}

// Biometric & Location Audit Data
$isBioVerified = !empty($scan['biometric_verified']) && ((string)$scan['biometric_verified'] === '1' || strtolower((string)$scan['biometric_verified']) === 'true');
$bioPhoto = trim((string)($scan['biometric_photo'] ?? ''));
$lat = trim((string)($scan['latitude'] ?? ''));
$lng = trim((string)($scan['longitude'] ?? ''));

// Helper validasi format data gambar agar tidak error/terpotong
$isValidPhotoString = function(string $str): bool {
    if ($str === '') return false;
    if (str_starts_with($str, 'data:image/')) {
        $pos = strpos($str, ';base64,');
        if ($pos === false) return false;
        $b64 = substr($str, $pos + 8);
        if (strlen($b64) < 150) return false; // String terlalu pendek / kosong
        $head = substr($b64, 0, 256);
        return base64_decode($head, true) !== false;
    }
    return filter_var($str, FILTER_VALIDATE_URL) || (strlen($str) < 500 && file_exists(__DIR__ . '/' . ltrim($str, '/')));
};

$isValidPhoto = $isValidPhotoString($bioPhoto);

// Jika foto di sesi scan tidak valid atau terpotong, ambil dari master foto teknisi terdaftar
if (!$isValidPhoto && $techName !== '') {
    $enrolled = get_enrolled_technicians(false);
    foreach ($enrolled as $en) {
        if (strcasecmp(trim((string)($en['nama'] ?? '')), trim($techName)) === 0 && !empty($en['photo'])) {
            $candidatePhoto = trim((string)$en['photo']);
            if ($isValidPhotoString($candidatePhoto)) {
                $bioPhoto = $candidatePhoto;
                $isValidPhoto = true;
                break;
            }
        }
    }
}

// Fallback avatar SVG jika gambar gagal dimuat (mencegah ikon tanda tanya (?) di safari/chrome)
$fallbackSvg = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='52' height='52' fill='%2364748b' viewBox='0 0 16 16'><rect width='16' height='16' fill='%23f1f5f9'/><path d='M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z'/><path fill-rule='evenodd' d='M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z'/></svg>";

if ($isBioVerified) {
    $photoThumb = $isValidPhoto
        ? '<img src="'.e($bioPhoto).'" class="rounded border shadow-sm" style="width: 52px; height: 52px; object-fit: cover; border-color: var(--app-border) !important;" alt="Foto Petugas" onerror="this.onerror=null; this.src=\''.$fallbackSvg.'\';">'
        : '<div class="bg-light text-secondary rounded d-flex align-items-center justify-content-center border" style="width: 52px; height: 52px;"><i class="bi bi-person-fill fs-3"></i></div>';

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
    $techPhoto = '';
    if ($techName !== '') {
        $enrolled = get_enrolled_technicians(false);
        foreach ($enrolled as $en) {
            if (strcasecmp(trim((string)($en['nama'] ?? '')), trim($techName)) === 0 && !empty($en['photo'])) {
                $c = trim((string)$en['photo']);
                if ($isValidPhotoString($c)) {
                    $techPhoto = $c;
                    break;
                }
            }
        }
    }
    $regThumb = $techPhoto !== ''
        ? '<img src="'.e($techPhoto).'" class="rounded-circle border shadow-sm me-2" style="width: 38px; height: 38px; object-fit: cover;" alt="Foto Petugas" onerror="this.onerror=null; this.style.display=\'none\';">'
        : '<div class="bg-light text-secondary rounded-circle d-inline-flex align-items-center justify-content-center border me-2" style="width: 38px; height: 38px;"><i class="bi bi-person-fill fs-5"></i></div>';

    $bioAuditHtml = '
    <div class="card border mb-4 shadow-sm" style="border-radius: 8px; border-color: var(--app-border) !important; background: #FAFBFD;">
      <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2 small">
        <div class="d-flex align-items-center">
          '.$regThumb.'
          <div class="text-secondary"><i class="bi bi-person-badge me-1"></i> Konfirmasi Petugas: <strong class="text-dark">'.e($techName).'</strong> (Pencatatan Reguler)</div>
        </div>
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

// Daftar nama bulan bahasa Indonesia
$indonesianMonths = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$curMonth = (int)date('n');
$curYear = (int)date('Y');

// Bulan & Tahun dari sesi yang sedang dilihat
$scanDate = $scan['maintenance_date'] ?? '';
$scanMonth = (int)($scan['maintenance_month'] ?? 0);
if ($scanMonth <= 0 && !empty($scanDate)) {
    $scanMonth = (int)date('n', strtotime($scanDate));
}
$scanYear = (int)($scan['maintenance_year'] ?? 0);
if ($scanYear <= 0 && !empty($scanDate)) {
    $scanYear = (int)date('Y', strtotime($scanDate));
}

// Rekapitulasi frekuensi per bulan untuk perangkat ini
$assetMonthlyBreakdown = [];
$thisMonthAssetCount = 0;
$scanMonthAssetCount = 0;

foreach ($assetHistory as $h) {
    $hRawDate = $h['maintenance_date'] ?? '';
    $hM = (int)($h['maintenance_month'] ?? ($hRawDate ? date('n', strtotime($hRawDate)) : 0));
    $hY = (int)($h['maintenance_year'] ?? ($hRawDate ? date('Y', strtotime($hRawDate)) : 0));
    if ($hM >= 1 && $hM <= 12 && $hY > 2000) {
        $ymKey = sprintf('%04d-%02d', $hY, $hM);
        if (!isset($assetMonthlyBreakdown[$ymKey])) {
            $assetMonthlyBreakdown[$ymKey] = [
                'year' => $hY,
                'month' => $hM,
                'label' => ($indonesianMonths[$hM] ?? 'Bulan ' . $hM) . ' ' . $hY,
                'short_label' => ($indonesianMonths[$hM] ?? 'Bulan ' . $hM),
                'count' => 0
            ];
        }
        $assetMonthlyBreakdown[$ymKey]['count']++;
        if ($hM === $curMonth && $hY === $curYear) {
            $thisMonthAssetCount++;
        }
        if ($hM === $scanMonth && $hY === $scanYear) {
            $scanMonthAssetCount++;
        }
    }
}
krsort($assetMonthlyBreakdown);

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
$allSessionsThisMonth = 0;
foreach ($allSessions as $sRow) {
    $sDate = $sRow['maintenance_date'] ?? '';
    $sM = (int)($sRow['maintenance_month'] ?? ($sDate ? date('n', strtotime($sDate)) : 0));
    $sY = (int)($sRow['maintenance_year'] ?? ($sDate ? date('Y', strtotime($sDate)) : 0));
    if ($sM === $curMonth && $sY === $curYear) {
        $allSessionsThisMonth++;
    }
}

$tokenParam = $token !== '' ? ['t' => $token] : [];

$navPrevBtn = $prevLogId > 0
    ? '<a href="'.e(module_url('maintenance_detail.php', ['id' => $prevLogId] + $tokenParam)).'" class="btn btn-outline-secondary btn-sm flex-fill flex-sm-grow-0" title="Lihat maintenance sebelumnya pada komputer ini"><i class="bi bi-chevron-left me-1"></i> Sebelumnya (#'.$prevLogId.')</a>'
    : '';

$navNextBtn = $nextLogId > 0
    ? '<a href="'.e(module_url('maintenance_detail.php', ['id' => $nextLogId] + $tokenParam)).'" class="btn btn-outline-secondary btn-sm flex-fill flex-sm-grow-0" title="Lihat maintenance berikutnya pada komputer ini">Berikutnya (#'.$nextLogId.') <i class="bi bi-chevron-right ms-1"></i></a>'
    : '';

$editBtnTop = $isLoggedIn
    ? '<button type="button" class="btn btn-warning text-dark fw-bold btn-sm flex-fill flex-sm-grow-0" data-bs-toggle="modal" data-bs-target="#editChecklistModal"><i class="bi bi-pencil-square me-1"></i> Edit Data</button>'
    : '<a class="btn btn-outline-primary btn-sm fw-semibold flex-fill flex-sm-grow-0" href="'.e(module_url('login.php')).'"><i class="bi bi-box-arrow-in-right me-1"></i> Login Edit</a>';

$backBtnTop = $isLoggedIn
    ? '<a class="btn btn-outline-secondary btn-sm fw-semibold flex-fill flex-sm-grow-0" href="'.e(module_url('audit.php')).'"><i class="bi bi-arrow-left me-1"></i> Riwayat Audit</a>'
    : (!empty($token)
        ? '<a class="btn btn-outline-primary btn-sm fw-bold flex-fill flex-sm-grow-0" href="'.e(module_url('scan.php', ['t' => $token])).'"><i class="bi bi-arrow-left me-1"></i> Kembali ke Kartu QR</a>'
        : '<a class="btn btn-outline-secondary btn-sm fw-semibold flex-fill flex-sm-grow-0" href="javascript:history.back()"><i class="bi bi-arrow-left me-1"></i> Kembali</a>');

$tindakBtnTop = (!empty($token) && ($status === 'Temuan' || $status === 'Perlu Perbaikan' || $status === 'Proses'))
    ? '<a class="btn btn-danger btn-sm fw-bold flex-fill flex-sm-grow-0" href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'tindak_lanjut'])).'"><i class="bi bi-tools me-1"></i> Form Tindak Lanjut</a>'
    : '';

// Pills dan Dropdown Filter Per Bulan
$monthlyPillsHtml = '';
$monthlyOptionsHtml = '';
if (!empty($assetMonthlyBreakdown)) {
    foreach ($assetMonthlyBreakdown as $ymKey => $bData) {
        $isCurrentCalMonth = ($bData['month'] === $curMonth && $bData['year'] === $curYear);
        $badgeClass = $isCurrentCalMonth ? 'bg-primary text-white' : 'bg-secondary text-white';
        $monthlyPillsHtml .= '
        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2.5 small month-pill-btn d-inline-flex align-items-center gap-1.5" data-ym="'.e($ymKey).'" onclick="selectMonthPill(\''.e($ymKey).'\')" style="font-size: 0.78rem; border-radius: 20px; transition: all 0.2s ease;">
          <span class="fw-semibold">'.e($bData['label']).'</span>
          <span class="badge '.$badgeClass.' rounded-pill" style="font-size: 0.72rem;">'.$bData['count'].'x</span>
        </button>';
        $monthlyOptionsHtml .= '<option value="'.e($ymKey).'">'.e($bData['label']).' ('.$bData['count'].' kali)</option>';
    }
} else {
    $monthlyPillsHtml = '<span class="text-muted small fst-italic">Belum ada riwayat tercatat.</span>';
}

// Tabel & Kartu Sesi Komputer Ini (Tab 1)
$thisAssetRowsHtml = '';
$thisAssetCardsHtml = '';
if (empty($assetHistory)) {
    $thisAssetRowsHtml = '<tr id="assetNoDataRow"><td colspan="7" class="text-center text-muted py-4">Belum ada riwayat maintenance lain yang tercatat untuk komputer ini.</td></tr>';
    $thisAssetCardsHtml = '<div class="text-center text-muted py-4 bg-light rounded-3 border"><i class="bi bi-inbox fs-3 d-block mb-1 opacity-50"></i>Belum ada riwayat maintenance lain yang tercatat untuk komputer ini.</div>';
} else {
    foreach ($assetHistory as $h) {
        $hId = (int)($h['id'] ?? 0);
        $isCurrent = ($hId === $id);
        $hRawDate = $h['maintenance_date'] ?? '';
        $hDate = format_id_date($hRawDate);
        $hTime = substr((string)($h['maintenance_time'] ?? ''), 0, 5);
        $hTech = $h['technician_name'] ?? 'Teknisi';
        $hStatus = $h['status'] ?? 'Selesai';
        $hType = $h['maintenance_type'] ?? 'Maintenance';
        $isHSusulan = (stripos($hType, 'susulan') !== false);
        $hTypeDisplay = $isHSusulan 
            ? '<span class="badge bg-warning text-dark fw-bold" style="font-size:0.72rem;"><i class="bi bi-clock-history me-0.5"></i> Susulan</span>' 
            : e($hType);
        $hFindings = trim((string)($h['findings'] ?? ''));

        $hM = (int)($h['maintenance_month'] ?? ($hRawDate ? date('n', strtotime($hRawDate)) : 0));
        $hY = (int)($h['maintenance_year'] ?? ($hRawDate ? date('Y', strtotime($hRawDate)) : 0));
        $hYm = ($hM > 0 && $hY > 0) ? sprintf('%04d-%02d', $hY, $hM) : '';
        $isThisMonthRow = ($hM === $curMonth && $hY === $curYear);

        $hBadge = ($hStatus === 'Temuan' || $hStatus === 'Perlu Perbaikan')
            ? '<span class="badge-chip chip-danger"><i class="bi bi-exclamation-triangle-fill"></i> Temuan</span>'
            : ($hStatus === 'Proses'
                ? '<span class="badge-chip chip-warning"><i class="bi bi-hourglass-split"></i> Proses</span>'
                : '<span class="badge-chip chip-success"><i class="bi bi-check-circle-fill"></i> Selesai</span>');

        $rowBg = $isCurrent ? 'style="background-color: #EAF3FF; font-weight: 600;"' : 'style="border-bottom: 1px solid var(--app-border);"';
        $currentBadge = $isCurrent ? ' <span class="badge-chip chip-primary ms-1" style="font-size:0.68rem;"><i class="bi bi-eye"></i> Sedang Dibuka</span>' : '';
        $monthBadge = $isThisMonthRow ? ' <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-1" style="font-size: 0.68rem; vertical-align: middle;"><i class="bi bi-calendar-check me-0.5"></i> Bulan Ini</span>' : '';

        $targetUrl = module_url('maintenance_detail.php', ['id' => $hId] + $tokenParam);

        $thisAssetRowsHtml .= '
        <tr class="asset-history-row" data-month="'.e($hYm).'" '.$rowBg.'>
          <td class="text-center font-monospace fw-bold text-primary">#'.$hId.'</td>
          <td>'.$hDate.' <span class="text-muted small">('.$hTime.' WITA)</span>'.$currentBadge.$monthBadge.'</td>
          <td><i class="bi bi-person text-secondary me-1"></i>'.e($hTech).'</td>
          <td>'.$hTypeDisplay.'</td>
          <td class="text-center">'.$hBadge.'</td>
          <td>'.($hFindings !== '' && $hFindings !== '-' ? '<span class="text-danger fw-semibold">'.e($hFindings).'</span>' : '<span class="text-muted">Normal</span>').'</td>
          <td class="text-center text-nowrap">
            '.($isCurrent 
                ? '<span class="badge-chip chip-primary" style="font-size: 0.72rem;"><i class="bi bi-check-lg"></i> Aktif</span>' 
                : '<a href="'.e($targetUrl).'" class="btn btn-sm btn-outline-secondary py-0 px-2 small">Buka #'.$hId.'</a>').'
          </td>
        </tr>';

        $thisAssetCardsHtml .= '
        <div class="asset-history-row asset-history-card p-3 mb-2 rounded-3 border shadow-xs '.($isCurrent ? 'bg-primary-subtle border-primary' : 'bg-white').'" data-month="'.e($hYm).'" style="'.($isCurrent ? 'border-left: 4px solid #2563EB !important;' : 'border-color: var(--app-border) !important;').'">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <div class="d-flex align-items-center gap-1.5 flex-wrap">
              <span class="badge bg-navy-subtle text-white font-monospace fw-bold" style="font-size: 0.72rem;">#'.$hId.'</span>
              <span class="fw-bold text-dark small">'.$hDate.'</span>
              <span class="text-muted font-monospace" style="font-size: 0.72rem;">('.$hTime.' WITA)</span>
              '.$currentBadge.$monthBadge.'
            </div>
            <div>'.$hBadge.'</div>
          </div>
          <div class="row g-2 small mb-2 text-secondary">
            <div class="col-6">
              <span class="text-muted d-block" style="font-size: 0.68rem;">TEKNISI:</span>
              <span class="fw-semibold text-dark text-truncate d-block"><i class="bi bi-person me-1 text-primary"></i>'.e($hTech).'</span>
            </div>
            <div class="col-6">
              <span class="text-muted d-block" style="font-size: 0.68rem;">JENIS:</span>
              <span class="fw-semibold text-dark text-truncate d-block">'.$hTypeDisplay.'</span>
            </div>
            <div class="col-12">
              <span class="text-muted d-block" style="font-size: 0.68rem;">TEMUAN / CATATAN:</span>
              '.($hFindings !== '' && $hFindings !== '-' ? '<span class="text-danger fw-semibold">'.e($hFindings).'</span>' : '<span class="text-muted">Normal (Tidak ada kendala)</span>').'
            </div>
          </div>
          <div class="pt-2 border-top d-flex justify-content-end" style="border-color: rgba(0,0,0,0.06) !important;">
            '.($isCurrent 
                ? '<span class="badge-chip chip-primary" style="font-size: 0.75rem;"><i class="bi bi-check-lg"></i> Sedang Dibuka</span>' 
                : '<a href="'.e($targetUrl).'" class="btn btn-sm btn-outline-primary py-1 px-3 w-100 fw-semibold text-center" style="font-size: 0.8rem;">Buka Sesi Ini (#'.$hId.') <i class="bi bi-arrow-right ms-1"></i></a>').'
          </div>
        </div>';
    }
    // Baris & Card penampung jika hasil filter bulan kosong
    $thisAssetRowsHtml .= '<tr id="assetFilterEmptyRow" style="display: none;"><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-calendar-x me-1 fs-5 d-block mb-1 opacity-50"></i>Tidak ada riwayat pemeliharaan pada bulan yang dipilih.</td></tr>';
    $thisAssetCardsHtml .= '<div id="assetFilterEmptyCard" class="text-center text-muted py-4 bg-light rounded-3 border" style="display: none;"><i class="bi bi-calendar-x fs-3 d-block mb-2 opacity-50"></i>Tidak ada riwayat pemeliharaan pada bulan yang dipilih.</div>';
}

// Tabel & Kartu Seluruh Sesi Sistem (Tab 2)
$allSessionsRowsHtml = '';
$allSessionsCardsHtml = '';
if (empty($allSessions)) {
    $allSessionsRowsHtml = '<tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>Belum ada riwayat sesi maintenance yang tercatat di sistem.</td></tr>';
    $allSessionsCardsHtml = '<div class="text-center text-muted py-4 bg-light rounded-3 border"><i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>Belum ada riwayat sesi maintenance yang tercatat di sistem.</div>';
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

        $targetUrl = module_url('maintenance_detail.php', ['id' => $sId] + $tokenParam);

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
                : '<a href="'.e($targetUrl).'" class="btn btn-sm btn-outline-secondary py-0 px-2 small">Buka #'.$sId.'</a>').'
          </td>
        </tr>';

        $allSessionsCardsHtml .= '
        <div class="p-3 mb-2 rounded-3 border shadow-xs '.($isCurrent ? 'bg-primary-subtle border-primary' : 'bg-white').'" style="'.($isCurrent ? 'border-left: 4px solid #2563EB !important;' : 'border-color: var(--app-border) !important;').'">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <div class="d-flex align-items-center gap-1.5 flex-wrap">
              <span class="badge bg-navy-subtle text-white font-monospace fw-bold" style="font-size: 0.72rem;">#'.$sId.'</span>
              <span class="fw-bold text-dark small">'.$sDate.'</span>
              <span class="text-muted font-monospace" style="font-size: 0.72rem;">('.$sTime.' WITA)</span>
              '.$currentBadge.'
            </div>
            <div>'.$sBadge.'</div>
          </div>
          <div class="mb-2">
            <div class="fw-bold font-monospace text-primary small">'.e($sKode).'</div>
            <div class="text-dark small fw-semibold text-truncate">'.e($sDevName).'</div>
          </div>
          <div class="d-flex justify-content-between align-items-center small text-secondary mb-2 flex-wrap gap-1">
            <span><i class="bi bi-geo-alt me-1 text-danger"></i>'.e($sCabang).'</span>
            <span><i class="bi bi-person me-1 text-primary"></i>'.e($sTech).'</span>
          </div>
          '.($sFindings !== '' && $sFindings !== '-' ? '<div class="alert alert-danger py-1 px-2 mb-2 small" style="font-size: 0.76rem;"><i class="bi bi-exclamation-triangle-fill me-1"></i>'.e($sFindings).'</div>' : '').'
          <div class="pt-2 border-top d-flex justify-content-end" style="border-color: rgba(0,0,0,0.06) !important;">
            '.($isCurrent 
                ? '<span class="badge-chip chip-primary" style="font-size: 0.75rem;"><i class="bi bi-check-lg"></i> Sedang Dibuka</span>' 
                : '<a href="'.e($targetUrl).'" class="btn btn-sm btn-outline-primary py-1 px-3 w-100 fw-semibold text-center" style="font-size: 0.8rem;">Buka Detail (#'.$sId.') <i class="bi bi-arrow-right ms-1"></i></a>').'
          </div>
        </div>';
    }
}

$publicBanner = !$isLoggedIn ? '
<div class="alert alert-info py-2.5 px-3 small d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3 border-0 shadow-sm rounded-3">
  <span><i class="bi bi-qr-code-scan me-1.5 text-primary"></i> <strong>Rincian Pemeliharaan</strong> · Mode Publik (Hasil Scan QR Perangkat)</span>
  '.(!empty($token) ? '<a href="'.e(module_url('scan.php', ['t' => $token])).'" class="btn btn-sm btn-primary fw-bold py-1 px-3 w-100 w-sm-auto text-center"><i class="bi bi-arrow-left me-1"></i> Kembali ke Kartu QR</a>' : '').'
</div>' : '';

$body = '
'.$publicBanner.'
'.$flashHtml.'
'.$errorHtml.'

<!-- Header Kicker & Action Bar -->
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-3 mb-md-4 no-print">
  <div>
    <div class="text-uppercase small fw-bold" style="letter-spacing: 0.08em; color: var(--app-accent, #124E96); font-size: 0.72rem; margin-bottom: 2px;">MONITORING / MAINTENANCE AUDIT RECORD</div>
    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
      <h2 class="h4 h3-md fw-bold text-dark mb-0 d-flex align-items-center gap-2 flex-wrap" style="font-size: clamp(1.2rem, 4vw, 1.65rem);">
        <i class="bi bi-file-earmark-medical text-primary"></i> Rincian Hasil Maintenance #'.$id.'
      </h2>
      <span class="badge-chip chip-primary font-monospace" style="font-size: 0.75rem;">Sesi ke-'.($totalMaintCount > 0 ? (array_search($id, array_column($assetHistory, 'id')) !== false ? ($totalMaintCount - array_search($id, array_column($assetHistory, 'id'))) : '1') : '1').' dari '.$totalMaintCount.' Sesi Komputer Ini</span>
    </div>
    <div class="text-muted small">Pencatatan 9 checklist pemeliharaan resmi komputer <strong>'.e(asset_title($asset)).'</strong> ('.e($asset['kode_inventaris'] ?? '-').').</div>
  </div>
  <div class="d-flex flex-wrap gap-2 align-items-center w-100 w-lg-auto">
    '.$navPrevBtn.'
    '.$navNextBtn.'
    '.$backBtnTop.'
    '.$tindakBtnTop.'
    '.$editBtnTop.'
    <button class="btn btn-primary fw-semibold btn-sm flex-fill flex-sm-grow-0" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak Dokumen</button>
  </div>
</div>

<div class="row g-3 g-md-4">
  <div class="col-12">
    
    <!-- Card 1: Detail Perangkat Komputer -->
    <div class="card p-0 border shadow-sm mb-3 mb-md-4" style="border-radius: 8px; border-color: var(--app-border) !important; background: #fff;">
      <div class="card-header bg-white py-2.5 px-3 py-md-3 px-md-4 d-flex justify-content-between align-items-center flex-wrap gap-2" style="border-bottom: 1px solid var(--app-border);">
        <div class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.05em;">
          <i class="bi bi-laptop text-primary me-2"></i>01 · Detail Perangkat Komputer
        </div>
        <span class="badge-chip chip-secondary font-monospace" style="font-size: 0.72rem;">ID ASET #'.(int)($asset['id'] ?? 0).'</span>
      </div>
      <div class="card-body p-3 p-md-4">
        <div class="row g-2 g-md-3 small">
          <div class="col-6 col-md-3">
            <div class="p-2.5 p-md-3 bg-light rounded-3 border h-100" style="border-color: var(--app-border) !important;">
              <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.04em;">Kode Inventaris:</div>
              <div class="fw-bold font-monospace text-primary fs-6 text-truncate" title="'.e($asset['kode_inventaris'] ?? '-').'">'.e($asset['kode_inventaris'] ?? '-').'</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="p-2.5 p-md-3 bg-light rounded-3 border h-100" style="border-color: var(--app-border) !important;">
              <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.04em;">Serial Number:</div>
              <div class="fw-semibold font-monospace text-dark text-truncate" title="'.e($asset['serial_number'] ?? '-').'">'.e($asset['serial_number'] ?? '-').'</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="p-2.5 p-md-3 bg-light rounded-3 border h-100" style="border-color: var(--app-border) !important;">
              <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.04em;">Jenis / Kategori:</div>
              <div class="fw-semibold text-dark text-truncate">'.e($asset['kategori_nama'] ?? 'Perangkat IT').'</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="p-2.5 p-md-3 bg-light rounded-3 border h-100" style="border-color: var(--app-border) !important;">
              <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.04em;">Merk & Model:</div>
              <div class="fw-bold text-dark text-truncate" title="'.e(asset_title($asset)).'">'.e(asset_title($asset)).'</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="p-2.5 p-md-3 bg-light rounded-3 border h-100" style="border-color: var(--app-border) !important;">
              <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.04em;">Pengguna / PIC:</div>
              <div class="fw-bold text-dark text-truncate"><i class="bi bi-person-circle text-primary me-1"></i>'.e($asset['karyawan_nama'] ?? '-').'</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="p-2.5 p-md-3 bg-light rounded-3 border h-100" style="border-color: var(--app-border) !important;">
              <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.04em;">Cabang & Divisi:</div>
              <div class="fw-semibold text-dark text-truncate" title="'.e($asset['cabang_nama'] ?? '-').' · '.e($asset['divisi_nama'] ?? '-').'">'.e($asset['cabang_nama'] ?? '-').' · '.e($asset['divisi_nama'] ?? '-').'</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="p-2.5 p-md-3 bg-light rounded-3 border h-100" style="border-color: var(--app-border) !important;">
              <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.04em;">Alamat IP:</div>
              <div class="fw-bold font-monospace text-primary text-truncate">'.e($asset['ip_address'] ?? $asset['ip'] ?? '-').'</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="p-2.5 p-md-3 bg-light rounded-3 border h-100" style="border-color: var(--app-border) !important;">
              <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.04em;">Printer Terhubung:</div>
              <div class="fw-semibold text-dark text-truncate">'.e($asset['printer'] ?? '-').'</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Card 2: Detail Pelaksanaan Maintenance & 9 Checklist -->
    <div class="card p-0 border shadow-sm mb-3 mb-md-4" style="border-radius: 8px; border-color: var(--app-border) !important; background: #fff;">
      <div class="card-header bg-white py-2.5 px-3 py-md-3 px-md-4 d-flex justify-content-between align-items-center flex-wrap gap-2" style="border-bottom: 1px solid var(--app-border);">
        <div class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.05em;">
          <i class="bi bi-calendar2-check text-primary me-2"></i>02 · Detail Pelaksanaan Pemeliharaan
        </div>
        <div class="d-flex align-items-center gap-2">
          '.($isSusulan ? '<span class="badge bg-warning text-dark fw-bold"><i class="bi bi-clock-history me-1"></i> Maintenance Susulan</span>' : '').'
          '.$statusBadge.'
        </div>
      </div>
      <div class="card-body p-3 p-md-4">
        '.$susulanAlertHtml.'
        <div class="row g-2 g-md-3 small mb-3 mb-md-4">
          <div class="col-6 col-md-3">
            <div class="p-2.5 p-md-3 bg-light rounded-3 border h-100" style="border-color: var(--app-border) !important;">
              <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.04em;"><i class="bi bi-calendar-event text-primary me-1"></i>Tanggal:</div>
              <div class="fw-bold text-dark fs-6 mt-0.5">'.e($dateStr).'</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="p-2.5 p-md-3 bg-light rounded-3 border h-100" style="border-color: var(--app-border) !important;">
              <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.04em;"><i class="bi bi-clock text-primary me-1"></i>Waktu:</div>
              <div class="fw-bold text-dark fs-6 mt-0.5">'.e($timeStr).' <span class="small fw-normal text-muted">WITA</span></div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="p-2.5 p-md-3 bg-light rounded-3 border h-100" style="border-color: var(--app-border) !important;">
              <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.04em;"><i class="bi bi-person-badge text-primary me-1"></i>Petugas / Teknisi:</div>
              <div class="fw-bold text-primary fs-6 mt-0.5 text-truncate" title="'.e($techName).'"><i class="bi bi-person-badge me-1"></i>'.e($techName).'</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="p-2.5 p-md-3 bg-light rounded-3 border h-100" style="border-color: var(--app-border) !important;">
              <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.04em;"><i class="bi bi-check2-circle text-success me-1"></i>Checklist:</div>
              <div class="fw-bold text-dark fs-6 mt-0.5"><span class="text-success">'.$totalChecked.'</span> / 9 Selesai</div>
            </div>
          </div>
        </div>

        <!-- Audit Presensi Petugas -->
        '.$bioAuditHtml.'

        <!-- Checklist Table (Desktop) & Cards (Mobile) -->
        <div class="fw-bold text-dark text-uppercase small mb-2 d-flex justify-content-between align-items-center" style="letter-spacing: 0.04em;">
          <span><i class="bi bi-card-checklist text-primary me-1"></i>Status 9 Item Pemeriksaan Standar:</span>
          <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 font-monospace small px-2 py-0.5">'.$totalChecked.'/9 OK</span>
        </div>

        <!-- Tampilan Desktop: Tabel Standar -->
        <div class="d-none d-md-block table-responsive border rounded mb-4" style="border-color: var(--app-border) !important;">
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

        <!-- Tampilan Handphone: Kartu Rapi & Sentuh Mudah -->
        <div class="d-block d-md-none mb-3">
          '.$chkMobileCards.'
        </div>

        <div class="row g-2 g-md-3">
          <div class="col-12 col-md-6">
            <div class="p-3 bg-light rounded-3 border h-100" style="border-color: #FEDF89 !important; border-left: 4px solid #F59E0B !important;">
              <div class="text-warning-emphasis text-uppercase fw-bold small mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>Ringkasan Temuan / Masalah:
              </div>
              <div class="text-dark small">'.($scan['findings'] ? nl2br(e($scan['findings'])) : '<span class="text-muted fst-italic">Tidak ada temuan kendala (Normal).</span>').'</div>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="p-3 bg-light rounded-3 border h-100" style="border-color: #BAE6FD !important; border-left: 4px solid #0284C7 !important;">
              <div class="text-primary-emphasis text-uppercase fw-bold small mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                <i class="bi bi-lightbulb-fill text-info me-1"></i>Rekomendasi Tindak Lanjut:
              </div>
              <div class="text-dark small">'.($scan['recommendation'] ? nl2br(e($scan['recommendation'])) : '<span class="text-muted fst-italic">Tidak ada rekomendasi khusus.</span>').'</div>
            </div>
          </div>
        </div>
        '.(($status === 'Temuan' || $status === 'Perlu Perbaikan' || $status === 'Proses') ? '
        <div class="alert alert-danger border-0 shadow-sm d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mt-3 mb-0" style="border-radius: 8px; border-left: 4px solid #DC2626 !important; background-color: #FEF2F2;">
          <div>
            <div class="fw-bold fs-6 text-danger"><i class="bi bi-tools me-2"></i>Status Perangkat: Perlu Perbaikan (Temuan Masalah Aktif)</div>
            <div class="small text-danger-emphasis">Perangkat ini terdaftar dalam daftar kendala aktif dashboard. Anda dapat langsung mencatat hasil perbaikan (tindak lanjut) atau mengubah status menjadi Selesai.</div>
          </div>
          <div class="d-flex gap-2 flex-wrap flex-shrink-0">
            '.(!empty($token) ? '<a href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'tindak_lanjut'])).'" class="btn btn-danger btn-sm fw-bold shadow-sm"><i class="bi bi-wrench-adjustable me-1"></i> Form Tindak Lanjut</a>' : '').'
            '.($isLoggedIn ? '<button type="button" class="btn btn-outline-danger btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#editChecklistModal"><i class="bi bi-pencil-square me-1"></i> Selesaikan / Edit Status</button>' : '').'
          </div>
        </div>' : '').'
      </div>
    </div>

    <!-- Card 3: Riwayat Pencatatan Sesi Pemeliharaan (Sesi Komputer Ini vs Seluruh Sistem) -->
    <div class="card p-0 border shadow-sm mb-3 mb-md-4" style="border-radius: 8px; border-color: var(--app-border) !important; background: #fff;">
      <div class="card-header bg-white py-2.5 px-3 py-md-3 px-md-4 d-flex justify-content-between align-items-center flex-wrap gap-2" style="border-bottom: 1px solid var(--app-border);">
        <div>
          <div class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.05em;">
            <i class="bi bi-clock-history text-primary me-2"></i>03 · Pencatatan Sesi Pemeliharaan (Audit Sesi)
          </div>
          <div class="text-muted small mt-0.5" style="font-size: 0.78rem;">Lihat rekam jejak sesi pemeliharaan komputer ini atau seluruh sesi pencatatan sistem operasional.</div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap w-100 w-sm-auto justify-content-start justify-content-sm-end">
          '.(!empty($asset['token']) ? '<a href="'.e(module_url('scan.php', ['t' => $asset['token']])).'" class="btn btn-outline-secondary btn-sm fw-semibold flex-fill flex-sm-grow-0"><i class="bi bi-card-checklist me-1"></i> Kartu 12 Bulan</a>' : '').'
          <a href="'.e(module_url('audit.php')).'" class="btn btn-outline-primary btn-sm fw-semibold flex-fill flex-sm-grow-0"><i class="bi bi-table me-1"></i> Audit Lengkap</a>
        </div>
      </div>
      
      <div class="card-body p-3 p-md-4">
        <!-- Dual Tabs: Sesi Komputer Ini vs Seluruh Sesi Sistem -->
        <ul class="nav nav-pills mb-3 gap-2" id="pills-session-tab" role="tablist">
          <li class="nav-item flex-fill flex-md-grow-0" role="presentation">
            <button class="nav-link active fw-bold small py-2 px-3 border w-100 text-center text-md-start" id="pills-this-asset-tab" data-bs-toggle="pill" data-bs-target="#pills-this-asset" type="button" role="tab" aria-controls="pills-this-asset" aria-selected="true" style="border-radius: 6px;">
              <i class="bi bi-laptop me-1"></i> Sesi Perangkat Ini <span class="badge bg-primary-subtle text-primary border ms-1 px-1.5 py-0.5">'.$totalMaintCount.' Sesi</span>
            </button>
          </li>
          <li class="nav-item flex-fill flex-md-grow-0" role="presentation">
            <button class="nav-link fw-bold small py-2 px-3 border w-100 text-center text-md-start" id="pills-all-sessions-tab" data-bs-toggle="pill" data-bs-target="#pills-all-sessions" type="button" role="tab" aria-controls="pills-all-sessions" aria-selected="false" style="border-radius: 6px;">
              <i class="bi bi-collection me-1"></i> Semua Sesi Sistem <span class="badge bg-secondary-subtle text-secondary border ms-1 px-1.5 py-0.5">'.$totalAllSessions.' Sesi</span>
            </button>
          </li>
        </ul>

        <div class="tab-content" id="pills-session-tabContent">
          <!-- TAB 1: Sesi Komputer Ini -->
          <div class="tab-pane fade show active" id="pills-this-asset" role="tabpanel" aria-labelledby="pills-this-asset-tab">
            
            <!-- Panel Ringkasan Frekuensi Pemeliharaan Per Bulan -->
            <div class="card p-2.5 p-md-3 mb-3 border shadow-sm" style="border-radius: 8px; border-color: var(--app-border) !important; background: #F8FAFC;">
              <div class="d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-2 mb-2">
                <div class="d-flex align-items-center flex-wrap gap-2">
                  <div class="badge bg-primary px-2.5 py-2 fs-6 text-white d-inline-flex align-items-center shadow-xs flex-fill flex-sm-grow-0">
                    <i class="bi bi-calendar-check-fill me-2 fs-5"></i>
                    <div class="text-start">
                      <div class="text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em; opacity: 0.85;">Frekuensi Bulan Ini ('.($indonesianMonths[$curMonth] ?? '').' '.$curYear.')</div>
                      <div class="fw-bold" style="font-size: 0.95rem;">'.$thisMonthAssetCount.' Kali Pemeliharaan</div>
                    </div>
                  </div>
                  '.(($scanMonth !== $curMonth || $scanYear !== $curYear) ? '
                  <div class="badge bg-secondary bg-opacity-75 px-2.5 py-2 fs-6 text-white d-inline-flex align-items-center flex-fill flex-sm-grow-0">
                    <i class="bi bi-calendar-event me-2 fs-5"></i>
                    <div class="text-start">
                      <div class="text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em; opacity: 0.85;">Bulan Sesi Ini ('.($indonesianMonths[$scanMonth] ?? '').' '.$scanYear.')</div>
                      <div class="fw-bold" style="font-size: 0.95rem;">'.$scanMonthAssetCount.' Kali</div>
                    </div>
                  </div>' : '').'
                  <div class="text-secondary small ms-sm-1 py-1">
                    <i class="bi bi-info-circle me-1"></i>Total: <strong class="text-dark">'.$totalMaintCount.' Sesi</strong>
                  </div>
                </div>

                <div class="d-flex align-items-center gap-2 w-100 w-md-auto mt-2 mt-md-0">
                  <label for="filterAssetMonth" class="text-muted small fw-semibold text-nowrap"><i class="bi bi-funnel me-1"></i>Filter:</label>
                  <select class="form-select form-select-sm flex-fill" id="filterAssetMonth" onchange="filterAssetTableByMonth(this.value)" style="min-width: 150px;">
                    <option value="all">Semua Bulan ('.$totalMaintCount.')</option>
                    '.$monthlyOptionsHtml.'
                  </select>
                </div>
              </div>

              <!-- Rekapitulasi Rincian Per Bulan (Interactive Horizontal Scrollable Pills) -->
              <div class="pt-2 border-top" style="border-color: #E2E8F0 !important;">
                <div class="d-flex align-items-center justify-content-between mb-1.5">
                  <span class="text-secondary small fw-bold" style="font-size: 0.74rem; letter-spacing: 0.03em;">
                    <i class="bi bi-bar-chart-fill text-primary me-1"></i>REKAP PER BULAN:
                  </span>
                  <span class="text-muted d-inline d-md-none" style="font-size: 0.7rem;"><i class="bi bi-arrows-expand me-0.5"></i>Geser &rarr;</span>
                </div>
                <div class="month-pills-scroll">
                  <button type="button" class="btn btn-sm btn-primary py-1 px-2.5 small month-pill-btn" data-ym="all" onclick="selectMonthPill(\'all\')" style="font-size: 0.75rem; border-radius: 20px;">
                    Semua ('.$totalMaintCount.')
                  </button>
                  '.$monthlyPillsHtml.'
                </div>
              </div>
            </div>

            <!-- Tampilan Desktop: Tabel Sesi Komputer Ini -->
            <div class="d-none d-md-block table-responsive rounded border" style="border-color: var(--app-border) !important;">
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

            <!-- Tampilan Handphone: Kartu Sesi Komputer Ini -->
            <div class="d-block d-md-none">
              '.$thisAssetCardsHtml.'
            </div>
          </div>

          <!-- TAB 2: Seluruh Sesi Sistem -->
          <div class="tab-pane fade" id="pills-all-sessions" role="tabpanel" aria-labelledby="pills-all-sessions-tab">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
              <div class="text-muted small">Menampilkan seluruh catatan sesi pemeliharaan yang terekam di sistem IT Bank Mitra.</div>
              <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 font-monospace small px-2 py-1"><i class="bi bi-calendar-check me-1"></i>'.$allSessionsThisMonth.' Sesi Bulan Ini</span>
                <span class="badge-chip chip-primary font-monospace">'.$totalAllSessions.' Total Sesi</span>
              </div>
            </div>

            <!-- Tampilan Desktop: Tabel Seluruh Sesi -->
            <div class="d-none d-md-block table-responsive rounded border" style="border-color: var(--app-border) !important; max-height: 480px; overflow-y: auto;">
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

            <!-- Tampilan Handphone: Kartu Seluruh Sesi -->
            <div class="d-block d-md-none" style="max-height: 520px; overflow-y: auto;">
              '.$allSessionsCardsHtml.'
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
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <form method="post" class="modal-content border-0 shadow">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="update_detail">

      <div class="modal-header bg-white py-3 px-3 px-md-4" style="border-bottom: 1px solid var(--app-border);">
        <div>
          <div class="text-uppercase small fw-bold text-warning" style="font-size: 0.72rem; letter-spacing: 0.06em;">MODIFIKASI HASIL AUDIT</div>
          <h5 class="modal-title fw-bold text-dark mb-0 fs-6 fs-md-5" id="editChecklistModalLabel"><i class="bi bi-pencil-square text-warning me-2"></i>Perbarui Data Maintenance & Checklist #'.$id.'</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-3 p-md-4" style="overflow-y: auto; -webkit-overflow-scrolling: touch;">
          <!-- 1. Edit Tanggal & Petugas Pelaksana -->
          <div class="p-3 bg-light rounded-3 border mb-3 mb-md-4" style="border-color: var(--app-border) !important;">
            <div class="fw-bold text-dark text-uppercase small mb-2" style="font-size: 0.75rem; letter-spacing: 0.04em;">
              <i class="bi bi-calendar-event text-primary me-1"></i>Waktu Pelaksanaan & Petugas:
            </div>
            <div class="row g-2 g-md-3">
              <div class="col-12 col-md-4">
                <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.04em;">Tanggal Pelaksanaan <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="maintenance_date" value="'.e(substr((string)($scan['maintenance_date'] ?? ''), 0, 10)).'" required>
                <div class="form-text text-muted" style="font-size: 0.72rem;">Bulan pada Kartu Kontrol akan otomatis disesuaikan.</div>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.04em;">Jam / Waktu</label>
                <input type="time" class="form-control" name="maintenance_time" value="'.e(substr((string)($scan['maintenance_time'] ?? '10:00'), 0, 5)).'">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.04em;">Petugas / Teknisi <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="technician_name" list="listTeknisiEdit" value="'.e($techName).'" required placeholder="Nama petugas">
                <datalist id="listTeknisiEdit">'.$techOptions.'</datalist>
              </div>
            </div>
          </div>

          <!-- 2. Status, Temuan, Rekomendasi -->
          <div class="row g-2 g-md-3 mb-3 mb-md-4">
            <div class="col-12 col-md-4">
              <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.04em;">Status Hasil Maintenance</label>
              <select class="form-select" name="status">
                <option value="Selesai" '.($status==='Selesai'?'selected':'').'>✅ Selesai (Normal)</option>
                <option value="Temuan" '.($status==='Temuan'?'selected':'').'>⚠️ Temuan (Ada Masalah)</option>
                <option value="Perlu Perbaikan" '.($status==='Perlu Perbaikan'?'selected':'').'>🚨 Perlu Perbaikan</option>
                <option value="Proses" '.($status==='Proses'?'selected':'').'>⏳ Sedang Proses</option>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.04em;">Temuan / Catatan Kerusakan</label>
              <input type="text" class="form-control" name="findings" value="'.e($findings !== '-' ? $findings : '').'" placeholder="Ketik temuan jika ada...">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.04em;">Rekomendasi / Tindakan</label>
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

        <div class="modal-footer bg-light d-flex flex-column-reverse flex-sm-row justify-content-end gap-2" style="border-top: 1px solid var(--app-border);">
          <button type="button" class="btn btn-outline-secondary fw-semibold w-100 w-sm-auto" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary fw-bold px-4 w-100 w-sm-auto"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
        </div>
      </form>
  </div>
</div>';
}

$extraHead = '
<style>
:root {
  --app-border: #E2E8F0;
  --app-navy: #0D2748;
  --app-accent: #124E96;
}
.month-pills-scroll {
  display: flex;
  align-items: center;
  gap: 6px;
  overflow-x: auto;
  flex-wrap: nowrap;
  -webkit-overflow-scrolling: touch;
  padding-bottom: 4px;
  scrollbar-width: thin;
}
.month-pills-scroll::-webkit-scrollbar {
  height: 4px;
}
.month-pills-scroll::-webkit-scrollbar-thumb {
  background: #CBD5E1;
  border-radius: 4px;
}
.month-pill-btn {
  white-space: nowrap;
  flex-shrink: 0;
}
.checklist-mobile-item {
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.checklist-mobile-item:active {
  transform: scale(0.99);
}
.asset-history-card {
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.asset-history-card:active {
  transform: scale(0.99);
}
@media (max-width: 576px) {
  .badge-chip {
    padding: 2px 6px;
    font-size: 0.7rem;
  }
  .card-header {
    padding: 10px 12px !important;
  }
  .card-body {
    padding: 12px 10px !important;
  }
  .checklist-mobile-item {
    padding: 8px 10px !important;
    margin-bottom: 6px !important;
  }
  .asset-history-card {
    padding: 10px 10px !important;
  }
}
</style>';

$extraScript = '
<script>
function filterAssetTableByMonth(ym) {
  var rows = document.querySelectorAll(".asset-history-row");
  var visibleCount = 0;
  rows.forEach(function(r) {
    if (ym === "all" || r.getAttribute("data-month") === ym) {
      r.style.display = "";
      if (r.tagName === "TR") visibleCount++;
    } else {
      r.style.display = "none";
    }
  });

  var emptyRow = document.getElementById("assetFilterEmptyRow");
  if (emptyRow) {
    emptyRow.style.display = (visibleCount === 0 && rows.length > 0) ? "" : "none";
  }

  var emptyCard = document.getElementById("assetFilterEmptyCard");
  if (emptyCard) {
    emptyCard.style.display = (visibleCount === 0 && rows.length > 0) ? "" : "none";
  }

  // Update pill styles
  var pills = document.querySelectorAll(".month-pill-btn");
  pills.forEach(function(p) {
    if (p.getAttribute("data-ym") === ym) {
      p.classList.remove("btn-outline-secondary");
      p.classList.add("btn-primary", "text-white");
    } else {
      p.classList.remove("btn-primary", "text-white");
      p.classList.add("btn-outline-secondary");
    }
  });

  var sel = document.getElementById("filterAssetMonth");
  if (sel && sel.value !== ym) {
    sel.value = ym;
  }
}

function selectMonthPill(ym) {
  filterAssetTableByMonth(ym);
}
</script>';

render_page('Detail Maintenance #' . $id, $body, $extraHead, $extraScript, $isLoggedIn);
