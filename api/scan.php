<?php
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/views/scan/biometric_modal.php';

// Pastikan akses scan diarahkan ke domain resmi ber-HTTPS https://it.bankmitra.co.id
enforce_qr_domain('scan.php');

$rawToken = (string)($_GET['t'] ?? $_POST['t'] ?? $_GET['token'] ?? $_POST['token'] ?? '');
if (stripos($rawToken, 'scan.php') !== false || stripos($rawToken, '?t=') !== false) {
    $parsedQuery = parse_url($rawToken, PHP_URL_QUERY);
    if ($parsedQuery) {
        parse_str($parsedQuery, $qp);
        if (!empty($qp['t'])) {
            $rawToken = $qp['t'];
        } elseif (!empty($qp['token'])) {
            $rawToken = $qp['token'];
        }
    }
}
$token = trim(urldecode($rawToken));
$token = trim($token, " '\t\n\r\0\x0B");

// Dukung akses langsung via ?id=, ?asset_id=, ?kode=, atau ?rek=
if ($token === '' && (!empty($_GET['id']) || !empty($_GET['asset_id']) || !empty($_GET['kode']) || !empty($_GET['rek']))) {
    $paramVal = trim((string)($_GET['id'] ?? $_GET['asset_id'] ?? $_GET['kode'] ?? $_GET['rek'] ?? ''));
    if (is_numeric($paramVal)) {
        $foundAsset = get_asset_by_id((int)$paramVal);
        if ($foundAsset) {
            $token = !empty($foundAsset['qr_token']) ? $foundAsset['qr_token'] : get_static_qr_token((int)$paramVal);
        }
    }
    if ($token === '' && $paramVal !== '') {
        $foundAsset = get_asset_by_token($paramVal);
        if ($foundAsset) {
            $token = !empty($foundAsset['qr_token']) ? $foundAsset['qr_token'] : get_static_qr_token((int)$foundAsset['id']);
        }
    }
}

if ($token === '' || !preg_match('/^[a-zA-Z0-9\-_\.\/\:\+\s%]{1,128}$/', $token)) {
    render_page('QR Tidak Valid', '<div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-octagon-fill me-2"></i><strong>QR tidak valid.</strong> Token QR tidak dikenali atau kosong.</div>', '', '', false);
    exit;
}

// Preload seluruh data sheet yang dibutuhkan oleh scan dalam 1 batch HTTP request
if (is_google_cloud_mode()) {
    $gsClient = google_sheets_v4_client();
    if ($gsClient) {
        $gsClient->preloadSheets(['Assets', 'Cabang', 'Divisi', 'Karyawan', 'Kategori_Aset', 'Asset_QR_Tokens', 'Maintenance_Scan', 'Maintenance_Checklists', 'Employee_Complaints', 'Users']);
    }
}

$asset = get_asset_by_token($token);

if (!$asset) {
    // Jika tidak ditemukan di tabel assets, periksa apakah ini kartu fisik inventaris (CR80)
    if (function_exists('find_inventaris_kartu_by_scan')) {
        $foundCard = find_inventaris_kartu_by_scan($token);
        if ($foundCard && !empty($foundCard['id'])) {
            header('Location: ' . qr_url('kartu_detail.php', ['id' => (int)$foundCard['id']]), true, 301);
            exit;
        }
    }

    render_page('QR Tidak Ditemukan', '<div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>QR tidak ditemukan atau belum terdaftar di sistem.</strong> Pastikan kode QR sudah di-generate di menu admin QR Aset atau periksa nomor inventaris perangkat.</div>', '', '', false);
    exit;
}

$assetId = (int)$asset['id'];
$currentMonth = (int)date('n');
$currentYear = (int)date('Y');
$month = $currentMonth;
$yearParam = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (isset($_GET['year']) ? (int)$_GET['year'] : 0);
$year = ($yearParam >= 2020 && $yearParam <= 2035) ? $yearParam : $currentYear;
$currentDateStr = date('Y-m-d');
$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$monthName = $monthNames[$month] ?? date('F');

// =========================================================================
// 0. PROSES FORM CATATAN & KELUHAN KARYAWAN (POST)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'submit_complaint')) {
    $reporterName = trim((string)($_POST['reporter_name'] ?? ''));
    $kategori = trim((string)($_POST['kategori'] ?? 'Lain-lain'));
    $complaintText = trim((string)($_POST['complaint'] ?? ''));
    $contact = trim((string)($_POST['contact'] ?? ''));

    if ($reporterName === '') {
        $reporterName = !empty($asset['karyawan_nama']) && $asset['karyawan_nama'] !== '-' ? $asset['karyawan_nama'] : 'Pengguna Komputer';
    }

    $cRes = save_employee_complaint($assetId, [
        'reporter_name' => $reporterName,
        'kategori' => $kategori,
        'complaint' => $complaintText,
        'contact' => $contact
    ]);

    if (!empty($cRes['success'])) {
        $_SESSION['flash_scan'] = '✓ Catatan keluhan Anda berhasil dikirim ke Tim IT. Teknisi akan segera memeriksa dan menindaklanjuti.';
        header('Location: ' . qr_url('scan.php', ['t' => $token, 'complaint_saved' => 1]));
        exit;
    } else {
        $error = $cRes['error'] ?? 'Gagal menyimpan catatan keluhan.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'resolve_complaint')) {
    $cId = (int)($_POST['complaint_id'] ?? 0);
    $cResp = trim((string)($_POST['technician_response'] ?? ''));
    $cTech = trim((string)($_POST['technician_name'] ?? ''));
    if ($cTech === '') {
        $cTech = is_logged_in() ? current_user_name() : 'Teknisi IT';
    }
    $cStatus = trim((string)($_POST['status'] ?? 'Selesai'));
    if (!in_array($cStatus, ['Selesai', 'Sedang Diproses'], true)) {
        $cStatus = 'Selesai';
    }
    if ($cId > 0) {
        resolve_employee_complaint($cId, [
            'technician_response' => $cResp ?: ($cStatus === 'Selesai' ? 'Telah dilakukan pengecekan dan perbaikan oleh teknisi IT.' : 'Sedang dalam pengecekan dan tindak lanjut teknisi IT.'),
            'resolved_by' => $cTech,
            'status' => $cStatus
        ]);
        if ($cStatus === 'Selesai') {
            $_SESSION['flash_scan'] = '✓ Keluhan karyawan telah ditindaklanjuti dan ditandai SELESAI oleh ' . $cTech . '.';
        } else {
            $_SESSION['flash_scan'] = '✓ Progres tindak lanjut keluhan ("Sedang Diproses") berhasil disimpan oleh ' . $cTech . '.';
        }
        header('Location: ' . qr_url('scan.php', ['t' => $token]));
        exit;
    }
}

// =========================================================================
// 0.05. PROSES PULIHKAN (RESTORE) FOTO ASET DARI FOLDER SAMPAH (7 HARI)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'restore_asset_photo')) {
    $trashId = (int)($_POST['trash_id'] ?? 0);
    $actor = is_logged_in() ? (current_user_name() ?: 'Teknisi IT') : 'Teknisi IT';
    $resRestore = restore_photo_from_trash($trashId, $actor);
    if (!empty($resRestore['success'])) {
        $_SESSION['flash_scan'] = "✓ Foto Ke-{$resRestore['restored_slot']} berhasil dipulihkan dari Folder Sampah!";
        header('Location: ' . qr_url('scan.php', ['t' => $token, 'photo_updated' => 1]));
        exit;
    } else {
        $error = $resRestore['error'] ?? 'Gagal memulihkan foto dari Folder Sampah.';
    }
}

// =========================================================================
// 0.1. PROSES UPDATE / EDIT / HAPUS 1-2 FOTO FISIK ASET (POST)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'update_asset_photo')) {
    $sentCsrf = (string)($_POST['_csrf'] ?? '');
    $sessionCsrf = (string)($_SESSION['_csrf'] ?? '');
    $postToken = trim((string)($_POST['t'] ?? ''));
    $csrfValid = ($sessionCsrf !== '' && $sentCsrf !== '' && hash_equals($sessionCsrf, $sentCsrf));
    $tokenValid = ($postToken !== '' && hash_equals($token, $postToken));
    if (!$csrfValid && !$tokenValid) {
        verify_csrf();
    }

    $verifierName = trim((string)($_POST['verifier_name'] ?? ''));
    if ($verifierName === '') {
        $verifierName = is_logged_in() ? current_user_name() : 'Teknisi IT';
    }

    $curF1 = trim((string)($asset['foto'] ?? ''));
    $curF2 = trim((string)($asset['foto_2'] ?? ''));
    $hapusSemua = !empty($_POST['hapus_semua_foto']);
    $hapusF1 = $hapusSemua || !empty($_POST['hapus_foto_1']);
    $hapusF2 = $hapusSemua || !empty($_POST['hapus_foto_2']);
    $changedLabels = [];

    if ($hapusF1 && $curF1 !== '') {
        move_photo_to_trash('assets', $assetId, 1, $curF1, $verifierName, 'Dihapus melalui halaman Scan QR (Foto 1)');
        $curF1 = '';
        $changedLabels[] = 'Foto 1 dipindah ke Sampah (7 Hari)';
    }
    if ($hapusF2 && $curF2 !== '') {
        move_photo_to_trash('assets', $assetId, 2, $curF2, $verifierName, 'Dihapus melalui halaman Scan QR (Foto 2)');
        $curF2 = '';
        $changedLabels[] = 'Foto 2 dipindah ke Sampah (7 Hari)';
    }

    // Upload Foto 1 baru jika ada
    $fotoPath1 = null;
    if (!empty($_POST['foto_base64'])) {
        $fotoPath1 = save_asset_base64_image($_POST['foto_base64']);
    }
    if (!$fotoPath1 && !empty($_FILES['foto_unit']['tmp_name'])) {
        $fotoPath1 = upload_asset_image('foto_unit');
    }
    if (!$fotoPath1 && !empty($_FILES['foto_unit_cam']['tmp_name'])) {
        $fotoPath1 = upload_asset_image('foto_unit_cam');
    }
    if ($fotoPath1) {
        if ($curF1 !== '') {
            move_photo_to_trash('assets', $assetId, 1, $curF1, $verifierName, 'Diganti dengan Foto 1 baru pada Scan QR');
        }
        $curF1 = $fotoPath1;
        $changedLabels[] = 'Foto 1 disimpan';
    }

    // Upload Foto 2 baru jika ada
    $fotoPath2 = null;
    if (!empty($_POST['foto_base64_2'])) {
        $fotoPath2 = save_asset_base64_image($_POST['foto_base64_2']);
    }
    if (!$fotoPath2 && !empty($_FILES['foto_unit_2']['tmp_name'])) {
        $fotoPath2 = upload_asset_image('foto_unit_2');
    }
    if (!$fotoPath2 && !empty($_FILES['foto_unit_2_cam']['tmp_name'])) {
        $fotoPath2 = upload_asset_image('foto_unit_2_cam');
    }
    if ($fotoPath2) {
        if ($curF2 !== '') {
            move_photo_to_trash('assets', $assetId, 2, $curF2, $verifierName, 'Diganti dengan Foto 2 baru pada Scan QR');
        }
        $curF2 = $fotoPath2;
        $changedLabels[] = 'Foto 2 disimpan';
    }

    if (!empty($changedLabels)) {
        $nowStr = date('Y-m-d H:i:s');
        $upPayload = [
            'foto' => $curF1,
            'foto_2' => $curF2,
            'foto_updated_by' => ($curF1 === '' && $curF2 === '') ? '' : $verifierName,
            'foto_updated_at' => ($curF1 === '' && $curF2 === '') ? '' : $nowStr
        ];
        update_asset($assetId, $upPayload);

        $_SESSION['flash_scan'] = '✓ Perubahan foto (' . implode(', ', $changedLabels) . ') berhasil diproses dan diverifikasi oleh ' . htmlspecialchars($verifierName) . '.';
        header('Location: ' . qr_url('scan.php', ['t' => $token, 'photo_updated' => 1]));
        exit;
    } else {
        $error = 'Silakan pilih foto baru (Foto 1 / Foto 2) atau centang foto yang ingin dihapus.';
    }
}

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

    // Penanganan Maintenance Susulan (misal pengguna cuti/tidak ada saat jadwal reguler)
    $isSusulan = ($mType === 'Maintenance Susulan' || ($_POST['maint_mode_radio'] ?? '') === 'susulan' || !empty($_POST['is_susulan']));
    if ($isSusulan) {
        $mType = 'Maintenance Susulan';
        $targetMonth = max(1, min(12, (int)($_POST['target_month'] ?? 0)));
        $targetYear = max(2020, (int)($_POST['target_year'] ?? date('Y', strtotime($mDate))));
        if ($targetMonth < 1 || $targetMonth > 12) {
            $targetMonth = (int)date('n', strtotime($mDate));
        }

        $susulanReason = trim((string)($_POST['susulan_reason'] ?? ''));
        if ($susulanReason === '' && !empty($_POST['susulan_reason_custom'])) {
            $susulanReason = trim((string)$_POST['susulan_reason_custom']);
        }
        if ($susulanReason === '' && !empty($_POST['susulan_reason_select']) && $_POST['susulan_reason_select'] !== 'custom') {
            $susulanReason = trim((string)$_POST['susulan_reason_select']);
        }
        if ($susulanReason === '') {
            $susulanReason = 'Pengguna / PIC tidak di tempat saat jadwal reguler';
        }

        $susulanTag = "[Maintenance Susulan Periode: " . ($monthNames[$targetMonth] ?? ('Bulan ' . $targetMonth)) . " {$targetYear} | Alasan: {$susulanReason}]";
        if ($recommendation === '') {
            $recommendation = $susulanTag;
        } else {
            $recommendation = $susulanTag . "\n" . $recommendation;
        }
    } else {
        $targetMonth = (int)date('n', strtotime($mDate));
        $targetYear = (int)date('Y', strtotime($mDate));
    }

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
        $allUsers = get_user_list(false);
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
        'maintenance_month' => $targetMonth,
        'maintenance_year' => $targetYear,
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
            'target_month' => $targetMonth,
            'target_year' => $targetYear,
            'target_month_name' => $monthNames[$targetMonth] ?? ('Bulan ' . $targetMonth),
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
// 2. JIKA REQUEST VIA AJAX / FETCH JSON, KEMBALIKAN RESPONS JSON
// =========================================================================
$isJsonRequest = (
    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
);

if ($isJsonRequest) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Content-Type: application/json; charset=utf-8');
    if ($successData) {
        echo json_encode(['success' => true, 'action' => 'maintenance', 'data' => $successData]);
        exit;
    } elseif ($successTindakLanjut) {
        echo json_encode(['success' => true, 'action' => 'tindak_lanjut', 'data' => $successTindakLanjut]);
        exit;
    } elseif ($error !== '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
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
$currentMonthLog = get_asset_maintenance_status_month($assetId, $currentMonth, $currentYear);
$pendingFinding = get_asset_active_finding($assetId);
$action = trim((string)($_GET['action'] ?? $_GET['amp;action'] ?? $_POST['action_type'] ?? ''));
$autoOpenLogin = trim((string)($_GET['open_login'] ?? ''));

if ($action === 'tindak_lanjut') {
    require __DIR__ . '/views/scan/form_tindak_lanjut.php';
    exit;
}

if ($action === 'start' || $action === 'form' || $action === 'ulang' || $action === 'susulan') {
    require __DIR__ . '/views/scan/form_checklist.php';
    exit;
}

// Default View: Kartu Kontrol 12 Bulan & Info Detail Perangkat
require __DIR__ . '/views/scan/asset_card.php';
