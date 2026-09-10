<?php
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/views/scan/biometric_modal.php';

$token = trim((string)($_GET['t'] ?? $_POST['t'] ?? ''));
if ($token === '' || !preg_match('/^[a-zA-Z0-9\-_]{1,128}$/', $token)) {
    render_page('QR Tidak Valid', '<div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-octagon-fill me-2"></i><strong>QR tidak valid.</strong> Token QR tidak dikenali.</div>', '', '', false);
    exit;
}

$asset = get_asset_by_token($token);

if (!$asset) {
    render_page('QR Tidak Ditemukan', '<div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>QR tidak ditemukan atau belum terdaftar di sistem.</strong> Pastikan kode QR sudah di-generate di menu admin QR Aset.</div>', '', '', false);
    exit;
}

$assetId = (int)$asset['id'];
$month = (int)date('n');
$year = (int)date('Y');
$currentDateStr = date('Y-m-d');
$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$monthName = $monthNames[$month] ?? date('F');

// =========================================================================
// 1. PROSES SIMPAN FORM MAINTENANCE & TINDAK LANJUT (POST)
// =========================================================================
$successData = null;
$successTindakLanjut = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'save_maintenance')) {
    // Verifikasi CSRF session jika ada, atau verifikasi token QR fisik jika sesi mobile serverless ter-reset
    $sentCsrf = (string)($_POST['_csrf'] ?? '');
    $sessionCsrf = (string)($_SESSION['_csrf'] ?? '');
    $postToken = trim((string)($_POST['t'] ?? ''));
    $csrfValid = ($sessionCsrf !== '' && $sentCsrf !== '' && hash_equals($sessionCsrf, $sentCsrf));
    $tokenValid = ($postToken !== '' && hash_equals($token, $postToken));
    if (!$csrfValid && !$tokenValid) {
        verify_csrf();
    }

    $techName = trim((string)($_POST['technician_name'] ?? ''));
    if ($techName === '') {
        $techName = is_logged_in() ? current_user_name() : 'Teknisi';
    }
    $mDate = trim((string)($_POST['maintenance_date'] ?? $currentDateStr));
    $mStatus = trim((string)($_POST['status'] ?? 'Selesai'));
    $mType = trim((string)($_POST['maintenance_type'] ?? 'Maintenance'));
    $findings = trim((string)($_POST['findings'] ?? ''));
    $recommendation = trim((string)($_POST['recommendation'] ?? ''));

    // Biometric audit fields
    $bioVerified = !empty($_POST['biometric_verified']) ? 1 : 0;
    $bioConfidence = (float)($_POST['biometric_confidence'] ?? 0);
    $bioPhoto = trim((string)($_POST['biometric_photo'] ?? ''));
    $latitude = trim((string)($_POST['latitude'] ?? ''));
    $longitude = trim((string)($_POST['longitude'] ?? ''));

    // Update IP & Printer jika ada perubahan saat scan
    $inputIp = trim((string)($_POST['ip_address'] ?? ''));
    $inputPrinter = trim((string)($_POST['printer'] ?? ''));
    $curIp = $asset['ip_address'] ?? $asset['ip'] ?? '';
    $curPrt = $asset['printer'] ?? '';
    if (($inputIp !== '' && $inputIp !== $curIp) || ($inputPrinter !== '' && $inputPrinter !== $curPrt)) {
        $upData = $asset;
        if ($inputIp !== '') $upData['ip_address'] = $inputIp;
        if ($inputPrinter !== '') $upData['printer'] = $inputPrinter;
        update_asset($assetId, $upData);
        $asset['ip_address'] = $upData['ip_address'];
        $asset['printer'] = $upData['printer'];
    }

    // Checklists 1..9
    $checklists = [];
    $fixedItems = get_fixed_checklists();
    foreach ($fixedItems as $num => $name) {
        $checked = !empty($_POST['chk_' . $num]) ? 1 : 0;
        $notes = trim((string)($_POST['notes_' . $num] ?? ''));
        $checklists[$num] = [
            'checked' => $checked,
            'notes' => $notes
        ];
    }

    $techUserId = is_logged_in() ? current_user_id() : 0;
    if ($techUserId <= 0 && $techName !== '' && strcasecmp($techName, 'Teknisi') !== 0) {
        $allUsers = get_user_list(true);
        $foundUser = false;
        foreach ($allUsers as $u) {
            if (strcasecmp((string)($u['nama'] ?? ''), $techName) === 0) {
                $techUserId = (int)($u['id'] ?? 0);
                $foundUser = true;
                break;
            }
        }
        if (!$foundUser) {
            $created = create_new_user([
                'nama' => $techName,
                'role' => 'teknisi',
                'status' => 'Aktif'
            ]);
            if (!empty($created['id'])) {
                $techUserId = (int)$created['id'];
            }
        }
    }

    $payload = [
        'asset_id' => $assetId,
        'technician_user_id' => $techUserId,
        'technician_name' => $techName,
        'maintenance_date' => $mDate,
        'maintenance_time' => date('H:i:s'),
        'maintenance_month' => (int)date('n', strtotime($mDate)),
        'maintenance_year' => (int)date('Y', strtotime($mDate)),
        'status' => $mStatus,
        'maintenance_type' => $mType,
        'findings' => $findings,
        'recommendation' => $recommendation,
        'biometric_verified' => $bioVerified,
        'biometric_confidence' => $bioConfidence,
        'biometric_photo' => $bioPhoto,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'checklists' => $checklists
    ];

    $res = save_maintenance_record($payload);
    if (!empty($res['success'])) {
        $successData = [
            'log_id' => $res['log_id'],
            'date' => $mDate,
            'technician' => $techName,
            'status' => $mStatus,
            'type' => $mType,
            'findings' => $findings,
            'recommendation' => $recommendation,
            'biometric_verified' => $bioVerified,
            'biometric_confidence' => $bioConfidence,
            'biometric_photo' => $bioPhoto,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    } else {
        $error = $res['error'] ?? 'Gagal menyimpan data maintenance.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'save_tindak_lanjut')) {
    $sentCsrf = (string)($_POST['_csrf'] ?? '');
    $sessionCsrf = (string)($_SESSION['_csrf'] ?? '');
    $postToken = trim((string)($_POST['t'] ?? ''));
    $csrfValid = ($sessionCsrf !== '' && $sentCsrf !== '' && hash_equals($sessionCsrf, $sentCsrf));
    $tokenValid = ($postToken !== '' && hash_equals($token, $postToken));
    if (!$csrfValid && !$tokenValid) {
        verify_csrf();
    }

    $logId = (int)($_POST['log_id'] ?? 0);
    $findingId = (int)($_POST['finding_id'] ?? 0);
    $techName = trim((string)($_POST['technician_name'] ?? ''));
    if ($techName === '') {
        $techName = is_logged_in() ? current_user_name() : 'Teknisi';
    }
    $actionTaken = trim((string)($_POST['action_taken'] ?? ''));
    $newStatus = trim((string)($_POST['status'] ?? 'Selesai'));
    $updateChecklists = !empty($_POST['update_checklists']);
    $tglTindakLanjut = trim((string)($_POST['tindak_lanjut_date'] ?? date('Y-m-d')));
    $jamTindakLanjut = trim((string)($_POST['tindak_lanjut_time'] ?? date('H:i:s')));
    $bioVerified = !empty($_POST['biometric_verified']) ? 1 : 0;
    $bioConfidence = (float)($_POST['biometric_confidence'] ?? 0);
    $bioPhoto = trim((string)($_POST['biometric_photo'] ?? ''));

    $res = resolve_asset_finding($assetId, [
        'log_id' => $logId,
        'finding_id' => $findingId,
        'technician_name' => $techName,
        'action_taken' => $actionTaken,
        'status' => $newStatus,
        'update_checklists' => $updateChecklists,
        'date' => $tglTindakLanjut,
        'time' => $jamTindakLanjut,
        'biometric_verified' => $bioVerified,
        'biometric_confidence' => $bioConfidence,
        'biometric_photo' => $bioPhoto
    ]);

    if (!empty($res['success'])) {
        $successTindakLanjut = [
            'technician' => $techName,
            'action_taken' => $actionTaken,
            'status' => $newStatus,
            'date' => $tglTindakLanjut,
            'time' => $jamTindakLanjut,
            'log_id' => $res['log_id'] ?? $logId,
            'biometric_verified' => $bioVerified,
            'biometric_confidence' => $bioConfidence,
            'biometric_photo' => $bioPhoto
        ];
    } else {
        $error = $res['error'] ?? 'Gagal menyimpan data tindak lanjut.';
    }
}

// =========================================================================
// 2. JIKA PROSES SIMPAN BERHASIL, TAMPILKAN VIEW SUKSES
// =========================================================================
if ($successData || $successTindakLanjut) {
    require __DIR__ . '/views/scan/success_view.php';
    exit;
}

// =========================================================================
// 3. ROUTING VIEW BERDASARKAN ACTION
// =========================================================================
$currentMonthLog = get_asset_maintenance_status_month($assetId, $month, $year);
$pendingFinding = get_asset_active_finding($assetId);
$action = trim((string)($_GET['action'] ?? $_GET['amp;action'] ?? $_POST['action_type'] ?? ''));
$autoOpenLogin = trim((string)($_GET['open_login'] ?? ''));

if ($action === 'tindak_lanjut') {
    require __DIR__ . '/views/scan/form_tindak_lanjut.php';
    exit;
}

if ($action === 'start' || $action === 'form' || $action === 'ulang') {
    require __DIR__ . '/views/scan/form_checklist.php';
    exit;
}

// Default View: Kartu Kontrol 12 Bulan & Info Detail Perangkat
require __DIR__ . '/views/scan/asset_card.php';
