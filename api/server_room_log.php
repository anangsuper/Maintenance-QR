<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/bootstrap.php';

// Auto-repair tabel server_room_logs jika tabel/kolom belum lengkap
try {
    db()->exec("CREATE TABLE IF NOT EXISTS server_room_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        log_code VARCHAR(100) NULL,
        user_id INT NULL,
        visitor_name VARCHAR(150) NOT NULL,
        visitor_type VARCHAR(50) DEFAULT 'internal_it',
        institution VARCHAR(150) NULL,
        contact_number VARCHAR(50) NULL,
        accompanied_by VARCHAR(150) NULL,
        purpose VARCHAR(255) NULL,
        purpose_detail TEXT NULL,
        entry_time DATETIME NOT NULL,
        exit_time DATETIME NULL,
        duration_minutes INT NULL,
        temperature_entry DECIMAL(5,2) NULL,
        temperature_exit DECIMAL(5,2) NULL,
        tools_brought TEXT NULL,
        photo_in MEDIUMTEXT NULL,
        photo_out MEDIUMTEXT NULL,
        status VARCHAR(50) DEFAULT 'in_room',
        cabang VARCHAR(100) NULL,
        ruangan VARCHAR(100) NULL,
        notes TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Pastikan kolom baru tetap ada jika tabel sudah terlanjur dibuat versi lama
    db()->exec("ALTER TABLE server_room_logs ADD COLUMN IF NOT EXISTS duration_minutes INT NULL AFTER exit_time");
    db()->exec("ALTER TABLE server_room_logs ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    db()->exec("ALTER TABLE server_room_logs MODIFY COLUMN photo_in MEDIUMTEXT NULL");
    db()->exec("ALTER TABLE server_room_logs MODIFY COLUMN photo_out MEDIUMTEXT NULL");

    // Sinkronisasi timezone database dan perbaiki durasi anomali lama (akibat perbedaan UTC vs WITA)
    try {
        db()->exec("SET time_zone = '+08:00'");
        db()->exec("UPDATE server_room_logs SET duration_minutes = GREATEST(1, TIMESTAMPDIFF(MINUTE, entry_time, exit_time)) WHERE exit_time IS NOT NULL AND (duration_minutes > 300 OR duration_minutes < 0)");
    } catch (Throwable $eTzFix) {}
} catch (Throwable $eDbAutoFix) {
    // Abaikan jika sudah ada
}

$isLoggedIn = is_logged_in();
$userName = $isLoggedIn ? current_user_name() : 'Pengunjung / Tamu';
$userRole = $isLoggedIn ? current_user_role() : 'guest';
$userId = $isLoggedIn ? current_user_id() : null;
$isAdmin = $isLoggedIn && is_admin();

// Daftar Staf IT aktif untuk opsi pendamping
$itStaffList = [];
try {
    $stIt = db()->query("SELECT nama FROM users WHERE role IN ('admin', 'teknisi') AND status = 'Aktif' ORDER BY nama ASC");
    $itStaffList = $stIt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $eIt) {}

// Handle Export CSV
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    $search = trim((string)($_GET['search'] ?? ''));
    $statusFilter = trim((string)($_GET['status'] ?? 'all'));
    $cabangFilter = trim((string)($_GET['cabang'] ?? 'all'));

    $sql = "SELECT * FROM server_room_logs WHERE 1=1";
    $params = [];
    if ($statusFilter !== 'all' && in_array($statusFilter, ['in_room', 'completed'], true)) {
        $sql .= " AND status = ?";
        $params[] = $statusFilter;
    }
    if ($cabangFilter !== 'all' && $cabangFilter !== '') {
        $sql .= " AND cabang = ?";
        $params[] = $cabangFilter;
    }
    if ($search !== '') {
        $sql .= " AND (visitor_name LIKE ? OR institution LIKE ? OR purpose LIKE ? OR log_code LIKE ?)";
        $sTerm = '%' . $search . '%';
        $params[] = $sTerm;
        $params[] = $sTerm;
        $params[] = $sTerm;
        $params[] = $sTerm;
    }
    $sql .= " ORDER BY entry_time DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Log_Akses_Ruang_Server_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'No', 'Kode Log', 'Tanggal & Waktu Masuk', 'Waktu Keluar', 'Durasi (Menit)',
        'Nama Pengunjung/Petugas', 'Tipe Kunjungan', 'Instansi/Vendor', 'No. Kontak',
        'Pendamping IT Bank', 'Keperluan Kunjungan', 'Rincian Pekerjaan', 'Suhu Masuk (C)',
        'Suhu Keluar (C)', 'Peralatan Dibawa', 'Status', 'Ruangan', 'Cabang', 'Catatan Keluar'
    ]);

    $no = 1;
    foreach ($rows as $r) {
        fputcsv($out, [
            $no++,
            $r['log_code'],
            $r['entry_time'],
            $r['exit_time'] ?: '-',
            $r['duration_minutes'] !== null ? $r['duration_minutes'] . ' menit' : 'Masih di Dalam',
            $r['visitor_name'],
            $r['visitor_type'],
            $r['institution'],
            $r['contact_number'] ?: '-',
            $r['accompanied_by'] ?: '-',
            $r['purpose'],
            $r['purpose_detail'] ?: '-',
            $r['temperature_entry'] ?: '-',
            $r['temperature_exit'] ?: '-',
            $r['tools_brought'] ?: '-',
            $r['status'] === 'in_room' ? 'Di Dalam Ruangan' : 'Selesai',
            $r['ruangan'],
            $r['cabang'],
            $r['notes'] ?: '-'
        ]);
    }
    fclose($out);
    exit;
}

// Handle POST: Check-In Masuk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkin') {
    $visitorType = trim((string)($_POST['visitor_type'] ?? 'internal_it'));
    if (!in_array($visitorType, ['internal_it', 'internal_staff', 'vendor_external', 'auditor'], true)) {
        $visitorType = 'internal_it';
    }

    $visitorName = trim((string)($_POST['visitor_name'] ?? ''));
    if ($visitorName === '') {
        $visitorName = $userName;
    }

    $institution = trim((string)($_POST['institution'] ?? 'PT BPR Mitratama Arthabuana'));
    if ($visitorType === 'internal_it' || $visitorType === 'internal_staff') {
        $institution = 'PT BPR Mitratama Arthabuana';
    }

    $contactNumber = trim((string)($_POST['contact_number'] ?? ''));
    $accompaniedBy = trim((string)($_POST['accompanied_by'] ?? ''));
    if ($visitorType === 'internal_it' && $accompaniedBy === '') {
        $accompaniedBy = 'Mandiri (Teknisi IT Bank)';
    }

    $purpose = trim((string)($_POST['purpose'] ?? ''));
    $purposeDetail = trim((string)($_POST['purpose_detail'] ?? ''));
    $temperatureEntry = !empty($_POST['temperature_entry']) ? (float)$_POST['temperature_entry'] : null;
    $toolsBrought = trim((string)($_POST['tools_brought'] ?? ''));
    $cabang = trim((string)($_POST['cabang'] ?? 'Kantor Pusat (KPO)'));
    $ruangan = trim((string)($_POST['ruangan'] ?? 'Ruang Server & Data Center Lt. 2'));
    $photoIn = trim((string)($_POST['photo_in'] ?? ''));
    if (!empty($photoIn) && preg_match('/^data:image\/(\w+);base64,/', $photoIn)) {
        $imgData = substr($photoIn, strpos($photoIn, ',') + 1);
        $decoded = base64_decode($imgData);
        if ($decoded !== false) {
            $uploadDir = __DIR__ . '/uploads/server_room/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
                @chmod($uploadDir, 0777);
            }
            $fileName = 'visitor_in_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.jpg';
            $filePath = $uploadDir . $fileName;
            if (@file_put_contents($filePath, $decoded)) {
                $photoIn = 'uploads/server_room/' . $fileName;
            }
        }
    }

    // Generate kode unik log: SRL-YYYYMM-XXXX
    $prefix = 'SRL-' . date('Ym') . '-';
    $stCount = db()->prepare("SELECT COUNT(*) FROM server_room_logs WHERE log_code LIKE ?");
    $stCount->execute([$prefix . '%']);
    $cnt = (int)$stCount->fetchColumn() + 1;
    $logCode = $prefix . str_pad((string)$cnt, 4, '0', STR_PAD_LEFT);

    $nowDateTime = date('Y-m-d H:i:s');

    try {
        $stmt = db()->prepare("
            INSERT INTO server_room_logs (
                log_code, user_id, visitor_name, visitor_type, institution, contact_number,
                accompanied_by, purpose, purpose_detail, entry_time, temperature_entry,
                tools_brought, photo_in, status, cabang, ruangan, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, 'in_room', ?, ?, ?
            )
        ");
        $stmt->execute([
            $logCode,
            ($visitorType === 'internal_it' || $visitorType === 'internal_staff') ? $userId : null,
            $visitorName,
            $visitorType,
            $institution,
            $contactNumber,
            $accompaniedBy,
            $purpose,
            $purposeDetail,
            $nowDateTime,
            $temperatureEntry,
            $toolsBrought,
            $photoIn ?: null,
            $cabang,
            $ruangan,
            $nowDateTime
        ]);

        try {
            record_audit_log('SERVER_ROOM_CHECKIN', 'SECURITY', $userId, $userName, "Check-in masuk ruang server: {$visitorName} ({$institution}) - Keperluan: {$purpose}", [
                'log_code' => $logCode,
                'visitor' => $visitorName,
                'type' => $visitorType,
                'purpose' => $purpose,
                'suhu' => $temperatureEntry
            ]);
        } catch (Throwable $eAudit) {
            // Abaikan kegagalan audit log agar proses checkin tetap berhasil
        }

        $_SESSION['flash_msg'] = "✓ Berhasil Check-In! Kunjungan ruang server resmi tercatat ({$logCode}).";
    } catch (Throwable $e) {
        $_SESSION['flash_err'] = "Gagal memproses check-in: " . $e->getMessage();
    }
    header('Location: ' . module_url('server_room_log.php'));
    exit;
}

// Handle POST: Check-Out Keluar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    $id = (int)($_POST['id'] ?? 0);
    $temperatureExit = !empty($_POST['temperature_exit']) ? (float)$_POST['temperature_exit'] : null;
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($id > 0) {
        try {
            $stRow = db()->prepare("SELECT * FROM server_room_logs WHERE id = ?");
            $stRow->execute([$id]);
            $row = $stRow->fetch(PDO::FETCH_ASSOC);

            if ($row && $row['status'] === 'in_room') {
                $nowDateTime = date('Y-m-d H:i:s');
                $entryTs = strtotime($row['entry_time']);
                $exitTs = strtotime($nowDateTime);
                $diffSec = $exitTs - $entryTs;

                // Koreksi otomatis jika entry_time sebelumnya tersimpan dalam format UTC (perbedaan ~8 jam):
                if ($diffSec >= 27000 && $diffSec <= 30600) {
                    $diffSec -= 28800;
                } elseif ($diffSec >= 23400 && $diffSec <= 27000) {
                    $diffSec -= 25200;
                }
                $diffSec = max(0, $diffSec);
                $durMin = max(1, (int)round($diffSec / 60));

                $stmt = db()->prepare("
                    UPDATE server_room_logs SET
                        exit_time = ?,
                        duration_minutes = ?,
                        temperature_exit = ?,
                        notes = ?,
                        status = 'completed',
                        updated_at = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nowDateTime, $durMin, $temperatureExit, $notes ?: null, $nowDateTime, $id]);

                try {
                    record_audit_log('SERVER_ROOM_CHECKOUT', 'SECURITY', $userId, $userName, "Check-out keluar ruang server: {$row['visitor_name']} ({$row['log_code']}) - Durasi: {$durMin} menit", [
                        'log_code' => $row['log_code'],
                        'duration_minutes' => $durMin,
                        'suhu_keluar' => $temperatureExit
                    ]);
                } catch (Throwable $eAudit) {
                    // Biarkan checkout tetap berhasil meski audit log gagal
                }

                $_SESSION['flash_msg'] = "✓ Check-Out Berhasil! Kunjungan {$row['visitor_name']} selesai. Durasi: {$durMin} menit.";
            } else {
                $_SESSION['flash_err'] = "Data kunjungan tidak ditemukan atau sudah check-out sebelumnya.";
            }
        } catch (Throwable $e) {
            $_SESSION['flash_err'] = "Gagal memproses check-out: " . $e->getMessage();
        }
    }
    header('Location: ' . module_url('server_room_log.php'));
    exit;
}

// Handle POST: Delete Log (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && $isAdmin) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        db()->prepare("DELETE FROM server_room_logs WHERE id = ?")->execute([$id]);
        $_SESSION['flash_msg'] = "Catatan log kunjungan berhasil dihapus.";
    }
    header('Location: ' . module_url('server_room_log.php'));
    exit;
}

// Filters & Data Retrieval
$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$cabangFilter = trim((string)($_GET['cabang'] ?? 'all'));
$cabang = !empty($_GET['cabang']) && $_GET['cabang'] !== 'all' ? trim((string)$_GET['cabang']) : 'Kantor Pusat (KPO)';
$ruangan = !empty($_GET['ruangan']) ? trim((string)$_GET['ruangan']) : 'Ruang Server & Data Center Lt. 2';

// 1. Orang yang sedang di dalam ruang server saat ini (Live Occupancy)
$stLive = db()->query("SELECT * FROM server_room_logs WHERE status = 'in_room' ORDER BY entry_time DESC");
$liveOccupants = $stLive->fetchAll(PDO::FETCH_ASSOC);

// 2. KPI Metrics Bulan Berjalan
$firstDayMonth = date('Y-m-01 00:00:00');
$kpiTotalMonth = (int)db()->query("SELECT COUNT(*) FROM server_room_logs WHERE entry_time >= '$firstDayMonth'")->fetchColumn();
$kpiVendorMonth = (int)db()->query("SELECT COUNT(*) FROM server_room_logs WHERE entry_time >= '$firstDayMonth' AND visitor_type = 'vendor_external'")->fetchColumn();
$kpiAvgTemp = db()->query("SELECT AVG(temperature_entry) FROM server_room_logs WHERE entry_time >= '$firstDayMonth' AND temperature_entry IS NOT NULL")->fetchColumn();
$avgTempFormatted = $kpiAvgTemp ? number_format((float)$kpiAvgTemp, 1) . ' °C' : '20.0 °C';

// 3. Query Log Riwayat
$sql = "SELECT * FROM server_room_logs WHERE 1=1";
$params = [];
if ($statusFilter !== 'all' && in_array($statusFilter, ['in_room', 'completed'], true)) {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
}
if ($cabangFilter !== 'all' && $cabangFilter !== '') {
    $sql .= " AND cabang = ?";
    $params[] = $cabangFilter;
}
if ($search !== '') {
    $sql .= " AND (visitor_name LIKE ? OR institution LIKE ? OR purpose LIKE ? OR log_code LIKE ?)";
    $sTerm = '%' . $search . '%';
    $params[] = $sTerm;
    $params[] = $sTerm;
    $params[] = $sTerm;
    $params[] = $sTerm;
}
$sql .= " ORDER BY entry_time DESC LIMIT 100";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Daftar Cabang
$cabangMaster = get_cabang_list();

$flashMsg = $_SESSION['flash_msg'] ?? null;
unset($_SESSION['flash_msg']);
$flashErr = $_SESSION['flash_err'] ?? null;
unset($_SESSION['flash_err']);

$actionGet = trim((string)($_GET['action'] ?? ''));
$autoOpenCheckin = in_array($actionGet, ['scan', 'checkin_scan', 'checkin_quick'], true);
$autoOpenCheckout = in_array($actionGet, ['checkout_scan', 'checkout_quick'], true);
$autoViewLogs = in_array($actionGet, ['view_logs', 'logs', 'history'], true);
$targetLogId = (int)($_GET['log_id'] ?? 0);

// Cari occupant yang cocok jika autoOpenCheckout
$autoCheckoutMatch = null;
if ($autoOpenCheckout) {
    if ($targetLogId > 0) {
        foreach ($liveOccupants as $occ) {
            if ((int)$occ['id'] === $targetLogId) {
                $autoCheckoutMatch = $occ;
                break;
            }
        }
    }
    if (!$autoCheckoutMatch && !empty($liveOccupants)) {
        foreach ($liveOccupants as $occ) {
            if ((int)($occ['user_id'] ?? 0) === $userId || strcasecmp($occ['visitor_name'], $userName) === 0) {
                $autoCheckoutMatch = $occ;
                break;
            }
        }
        if (!$autoCheckoutMatch && count($liveOccupants) === 1) {
            $autoCheckoutMatch = $liveOccupants[0];
        }
    }
}

// JIKA AKSES PUBLIK TANPA LOGIN (MISAL DARI SCAN QR PINTU DI HP):
// Gunakan template UI khusus publik (mobile-first portal, tanpa sidebar/header admin)
if (!$isLoggedIn) {
    require __DIR__ . '/includes/server_room_public_view.php';
    exit;
}

// Header styling & Script HTML
$head = '
<style>
.door-badge-pulse {
  display: inline-block;
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: #16A34A;
  box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.7);
  animation: pulseGreen 1.8s infinite;
}
@keyframes pulseGreen {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(22, 163, 74, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); }
}
.scanner-video-box {
  width: 100%;
  max-width: 480px;
  min-height: 280px;
  margin: 0 auto;
  border-radius: 10px;
  overflow: hidden;
  background: #000000;
  position: relative;
}
.table thead th {
  background: #F4F5F7;
  color: #53595F;
  font-size: 11px;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  font-weight: 700;
  border-bottom: 1px solid #D1D5D8;
  padding: 10px 14px;
}
.table tbody td {
  padding: 11px 14px;
  font-size: 13px;
  color: #1B2A3A;
  border-bottom: 1px solid #F0F2F4;
}
.table tbody tr:hover {
  background-color: #F8FAF9 !important;
}

/* Kombai Visitor Type Selector Cards */
.visitor-card-neo {
  display: block;
  cursor: pointer;
  border: 1px solid #D1D5D8;
  border-radius: 10px;
  background: #FFFFFF;
  transition: all 0.15s ease;
  user-select: none;
  position: relative;
}
.visitor-card-neo:hover {
  border-color: #0E5C58;
  background: #F8FAF9;
}
.visitor-card-neo.active,
.btn-check:checked + .visitor-card-neo {
  border-color: #0E5C58 !important;
  background: #D9F1EF !important;
  box-shadow: 0 2px 8px rgba(14, 92, 88, 0.15) !important;
}
.visitor-card-neo .icon-circle {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}
.visitor-card-neo.active .title-text,
.btn-check:checked + .visitor-card-neo .title-text {
  color: #0E5C58 !important;
}
.visitor-card-neo.active::after,
.btn-check:checked + .visitor-card-neo::after {
  content: "\2713";
  position: absolute;
  top: 6px;
  right: 8px;
  width: 18px;
  height: 18px;
  background: #0E5C58;
  color: #FFFFFF;
  border-radius: 50%;
  font-size: 11px;
  font-weight: bold;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Kombai Form & Buttons */
.form-control, .form-select {
  border-color: #D1D5D8;
  color: #1B2A3A;
  font-size: 13px;
  border-radius: 8px;
}
.form-control:focus, .form-select:focus {
  border-color: #0E5C58;
  box-shadow: 0 0 0 2px rgba(14, 92, 88, 0.15);
}
.btn-primary-teal {
  background: #0E5C58;
  color: #FFFFFF;
  font-weight: 600;
  border: none;
  border-radius: 8px;
  transition: background-color 0.15s ease;
}
.btn-primary-teal:hover {
  background: #094340;
  color: #FFFFFF;
}
.btn-outline-teal {
  background: #FFFFFF;
  color: #0E5C58;
  border: 1px solid #0E5C58;
  font-weight: 600;
  border-radius: 8px;
  transition: all 0.15s ease;
}
.btn-outline-teal:hover {
  background: #D9F1EF;
  color: #0E5C58;
}
.btn-kombai-white {
  background: #FFFFFF;
  color: #1B2A3A;
  border: 1px solid #D1D5D8;
  font-weight: 500;
  border-radius: 8px;
  font-size: 13px;
  transition: background-color 0.15s ease;
}
.btn-kombai-white:hover {
  background: #F4F5F7;
  color: #1B2A3A;
}
.step-badge-teal {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  background: #0E5C58;
  color: #FFFFFF;
}
</style>
<script src="' . e(module_url('html5-qrcode.min.js')) . '"></script>
';

ob_start();
?>
<div class="container-fluid py-3 px-md-3">

  <!-- Flash Notification -->
  <?php if ($flashMsg): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-xs d-flex align-items-center gap-2 mb-3" role="alert" style="background:#EDF7EE; color:#166534; border-left: 4px solid #16A34A !important; border-radius: 8px;">
      <i class="bi bi-check-circle-fill fs-5 text-success"></i>
      <div><?= e($flashMsg) ?></div>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if ($flashErr): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-xs d-flex align-items-center gap-2 mb-3" role="alert" style="background:#FDE8E8; color:#9B1C1C; border-left: 4px solid #E02424 !important; border-radius: 8px;">
      <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
      <div><?= e($flashErr) ?></div>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Page Heading Row -->
  <div class="mb-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
    <div>
      <div class="text-[11px] font-bold text-[#0E5C58] uppercase tracking-wider mb-1 flex items-center gap-1.5">
        <i class="bi bi-shield-lock-fill"></i> KEAMANAN DATA CENTER &middot; POJK NO. 75/POJK.03/2016
      </div>
      <h1 class="text-[22px] font-bold text-[#1B2A3A] tracking-tight m-0">Log Masuk Ruang Server &amp; Data Center</h1>
      <p class="text-[13px] text-[#53595F] mt-1 mb-0">Kontrol akses fisik dan pencatatan riwayat kunjungan teknisi IT, auditor SKAI, serta rekanan eksternal.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2 shrink-0">
      <button type="button" class="h-[36px] px-3.5 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[13px] font-semibold transition-colors inline-flex items-center gap-1.5 border-0 shadow-xs cursor-pointer" data-bs-toggle="modal" data-bs-target="#modalCheckin">
        <i class="bi bi-door-open-fill"></i> Check-In Masuk
      </button>
      <?php if (!empty($liveOccupants)): ?>
        <button type="button" class="h-[36px] px-3.5 bg-[#DC2626] hover:bg-[#B91C1C] text-white rounded-[8px] text-[13px] font-semibold transition-colors inline-flex items-center gap-1.5 border-0 shadow-xs cursor-pointer" onclick="handleCheckoutScanAction()">
          <i class="bi bi-box-arrow-right"></i> Check-Out (<?= count($liveOccupants) ?> di Dalam)
        </button>
      <?php endif; ?>
      <button type="button" class="h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 shadow-xs cursor-pointer" data-bs-toggle="modal" data-bs-target="#modalScanner" onclick="startScannerModal()">
        <i class="bi bi-qr-code-scan text-[#0E5C58]"></i> Scanner Live
      </button>
      <a href="<?= module_url('server_room_print_qr.php', ['cabang' => $cabang, 'ruangan' => $ruangan]) ?>" target="_blank" class="h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
        <i class="bi bi-printer text-[#53595F]"></i> Cetak QR Pintu
      </a>
      <a href="<?= module_url('server_room_log.php', array_filter(['action' => 'export_csv', 'search' => $search, 'status' => $statusFilter, 'cabang' => $cabangFilter])) ?>" class="h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
        <i class="bi bi-file-earmark-excel text-[#16A34A]"></i> Ekspor CSV
      </a>
    </div>
  </div>

  <!-- LIVE OCCUPANCY ALERT (Jika sedang ada orang di dalam ruang server) -->
  <?php if (!empty($liveOccupants)): ?>
    <div class="mb-4 bg-[#F0FDF4] border border-[#86EFAC] rounded-[10px] p-3.5 shadow-xs">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pb-2 border-bottom border-[#BBF7D0]">
        <div class="d-flex align-items-center gap-2">
          <span class="door-badge-pulse"></span>
          <div>
            <div class="fw-bold text-[#166534] d-flex align-items-center gap-2" style="font-size: 14px;">
              <?= count($liveOccupants) ?> Orang Sedang Berada di Dalam Ruang Server
            </div>
            <div class="text-[#15803D] small" style="font-size: 12px;">Pintu ruang server dalam status terbuka &middot; aktivitas pekerjaan sedang berlangsung.</div>
          </div>
        </div>
        <div class="text-end text-[#15803D] small" style="font-size: 12px;">
          <i class="bi bi-clock-history me-1"></i>Pencatatan real-time otomatis
        </div>
      </div>

      <div class="row g-2.5 mt-2">
        <?php foreach ($liveOccupants as $occ): 
          $enterTs = strtotime($occ['entry_time']);
          $diffSec = time() - $enterTs;
          if ($diffSec >= 27000 && $diffSec <= 30600) { $diffSec -= 28800; }
          elseif ($diffSec >= 23400 && $diffSec <= 27000) { $diffSec -= 25200; }
          $elapsedMin = max(1, (int)round(max(0, $diffSec) / 60));
        ?>
          <div class="col-md-6 col-xl-4">
            <div class="bg-white rounded-[8px] p-2.5 border border-[#D1D5D8] shadow-xs d-flex align-items-center justify-content-between gap-2">
              <div class="d-flex align-items-center gap-2 overflow-hidden">
                <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px; min-width: 36px; font-size: 0.85rem; background: #0E5C58;">
                  <?= strtoupper(substr($occ['visitor_name'], 0, 1)) ?>
                </div>
                <div class="overflow-hidden">
                  <div class="fw-bold text-[#1B2A3A] text-truncate" style="font-size: 0.88rem;"><?= e($occ['visitor_name']) ?></div>
                  <div class="text-[#53595F]" style="font-size: 0.74rem;">
                    <?= e($occ['institution']) ?> &middot; Masuk <?= date('H:i', $enterTs) ?> (<?= $elapsedMin ?> mnt lalu)
                  </div>
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-danger rounded-[6px] px-2.5 py-1 fw-bold text-nowrap shadow-xs" style="font-size: 12px;" data-bs-toggle="modal" data-bs-target="#modalCheckout" onclick="openCheckoutModal(<?= (int)$occ['id'] ?>, <?= htmlspecialchars(json_encode($occ['visitor_name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($occ['log_code']), ENT_QUOTES, 'UTF-8') ?>)">
                <i class="bi bi-box-arrow-right me-1"></i>Keluar
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- 4 SYMMETRICAL KOMBAI METRIC CARDS (88px) -->
  <div class="bg-[#FFFFFF] border border-[#D1D5D8] rounded-[10px] h-[88px] flex overflow-hidden shrink-0 mb-4">
    <!-- Card 1: Live Occupants -->
    <a href="<?= module_url('server_room_log.php', ['status' => 'in_room']) ?>" class="flex-1 flex flex-col justify-center px-6 border-r border-[#D1D5D8] relative cursor-pointer text-decoration-none transition-colors <?= $statusFilter === 'in_room' ? 'bg-[#D9F1EF]' : 'hover:bg-[#F4F5F7]' ?>">
      <div class="absolute left-0 top-0 bottom-0 w-1 <?= count($liveOccupants) > 0 ? 'bg-[#16A34A]' : 'bg-[#0E5C58]' ?>"></div>
      <div class="text-[28px] font-bold <?= count($liveOccupants) > 0 ? 'text-[#16A34A]' : 'text-[#1B2A3A]' ?> tabular-nums leading-none">
        <?= count($liveOccupants) ?> <span class="text-[13px] font-normal text-[#53595F]">Orang</span>
      </div>
      <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
        <span class="door-badge-pulse" style="background: <?= count($liveOccupants) > 0 ? '#16A34A' : '#9CA3AF' ?>;"></span>
        <span>Sedang di Dalam</span>
      </div>
    </a>

    <!-- Card 2: Total Kunjungan Bulan Ini -->
    <a href="<?= module_url('server_room_log.php', ['status' => 'all']) ?>" class="flex-1 flex flex-col justify-center px-6 border-r border-[#D1D5D8] relative cursor-pointer text-decoration-none transition-colors <?= ($statusFilter === 'all' && empty($search)) ? 'bg-[#D9F1EF]' : 'hover:bg-[#F4F5F7]' ?>">
      <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0E5C58]"></div>
      <div class="text-[28px] font-bold text-[#1B2A3A] tabular-nums leading-none">
        <?= number_format($kpiTotalMonth) ?> <span class="text-[13px] font-normal text-[#53595F]">Akses</span>
      </div>
      <div class="text-[12px] font-medium text-[#53595F] mt-1.5">
        Total Kunjungan (<?= date('M Y') ?>)
      </div>
    </a>

    <!-- Card 3: Tamu / Vendor Eksternal -->
    <div class="flex-1 flex flex-col justify-center px-6 border-r border-[#D1D5D8] relative hover:bg-[#F4F5F7] transition-colors">
      <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#D97706]"></div>
      <div class="text-[28px] font-bold text-[#D97706] tabular-nums leading-none">
        <?= number_format($kpiVendorMonth) ?> <span class="text-[13px] font-normal text-[#53595F]">Kunjungan</span>
      </div>
      <div class="text-[12px] font-medium text-[#53595F] mt-1.5">
        Vendor &amp; Tamu Eksternal
      </div>
    </div>

    <!-- Card 4: Suhu Rata-rata Server -->
    <div class="flex-1 flex flex-col justify-center px-6 relative hover:bg-[#F4F5F7] transition-colors">
      <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0284C7]"></div>
      <div class="text-[28px] font-bold text-[#0284C7] tabular-nums leading-none">
        <?= $avgTempFormatted ?>
      </div>
      <div class="text-[12px] font-medium text-[#53595F] mt-1.5">
        Rata-Rata Suhu Ruangan
      </div>
    </div>
  </div>

  <!-- TABLE & FILTER TOOLBAR -->
  <div class="bg-white border border-[#D1D5D8] rounded-[10px] overflow-hidden mb-4 shadow-xs" id="riwayatLogTable">
    <div class="py-3 px-3 px-md-4 border-b border-[#D1D5D8] d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <h5 class="fw-bold mb-0 text-[#1B2A3A] d-flex align-items-center gap-2" style="font-size: 15px;">
          <i class="bi bi-journal-text text-[#0E5C58]"></i>
          <span>Buku Catatan Kunjungan Ruang Server</span>
        </h5>
        <div class="text-[#53595F] small mt-0.5" style="font-size: 12px;">Menampilkan data akses fisik terbaru lengkap dengan barcode log resmi.</div>
      </div>

      <!-- Filter Controls -->
      <form method="get" class="d-flex align-items-center gap-2 flex-wrap">
        <div class="input-group input-group-sm" style="max-width: 210px;">
          <span class="input-group-text bg-light text-secondary border-end-0"><i class="bi bi-search"></i></span>
          <input type="text" name="search" class="form-control border-start-0" placeholder="Cari nama, instansi..." value="<?= e($search) ?>">
        </div>

        <select name="status" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
          <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Semua Status</option>
          <option value="in_room" <?= $statusFilter === 'in_room' ? 'selected' : '' ?>>Sedang di Dalam</option>
          <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Selesai (Completed)</option>
        </select>

        <?php if (!empty($cabangMaster)): ?>
          <select name="cabang" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
            <option value="all">Semua Cabang</option>
            <?php foreach ($cabangMaster as $cb): 
              $cName = $cb['nama_cabang'] ?? '';
              if ($cName === '') continue;
            ?>
              <option value="<?= e($cName) ?>" <?= $cabangFilter === $cName ? 'selected' : '' ?>><?= e($cName) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>

        <?php if ($search || $statusFilter !== 'all' || $cabangFilter !== 'all'): ?>
          <a href="<?= module_url('server_room_log.php') ?>" class="btn btn-sm btn-outline-secondary" title="Reset Filter"><i class="bi bi-x-circle"></i></a>
        <?php endif; ?>
      </form>
    </div>

    <!-- MOBILE (HP) CARD VIEW: Tampil otomatis pada layar HP -->
    <div class="d-md-none p-3">
      <?php if (empty($logs)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-door-closed display-6 d-block mb-2 text-secondary opacity-50"></i>
          <div class="fw-semibold">Belum ada riwayat kunjungan ruang server tercatat.</div>
          <div class="small text-muted mt-1">Gunakan tombol <strong>Check-In Masuk</strong> untuk mencatat kunjungan baru.</div>
        </div>
      <?php else: ?>
        <div class="d-flex flex-column gap-3">
          <?php foreach ($logs as $l): 
            $isInside = ($l['status'] === 'in_room');
            $enterTs = strtotime($l['entry_time']);
            $exitTs = !empty($l['exit_time']) ? strtotime($l['exit_time']) : null;
          ?>
            <div class="card border rounded-[8px] p-3 shadow-xs" style="background: <?= $isInside ? '#F0FDF4' : '#FFFFFF' ?>; border-color: <?= $isInside ? '#86EFAC' : '#D1D5D8' ?> !important;">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="badge font-monospace <?= $isInside ? 'bg-success' : 'bg-[#0E5C58]' ?> text-white px-2 py-1" style="font-size: 0.72rem;">
                  <?= e($l['log_code']) ?>
                </span>
                <?php if ($isInside): ?>
                  <span class="badge bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC] rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.72rem;">
                    <span class="door-badge-pulse me-1"></span>Masih di Dalam
                  </span>
                <?php else: ?>
                  <span class="badge bg-light text-[#53595F] border rounded-pill px-2 py-1" style="font-size: 0.72rem;">
                    <i class="bi bi-check2-circle me-1 text-success"></i>Selesai (<?= (int)$l['duration_minutes'] ?> mnt)
                  </span>
                <?php endif; ?>
              </div>

              <div class="d-flex align-items-center gap-2 mb-2">
                <?php if (!empty($l['photo_in'])): ?>
                  <img src="<?= e($l['photo_in']) ?>" class="rounded-circle object-fit-cover border" style="width: 36px; height: 36px; min-width: 36px; cursor: pointer;" onclick="previewPhoto(<?= htmlspecialchars(json_encode($l['photo_in']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($l['visitor_name']), ENT_QUOTES, 'UTF-8') ?>)">
                <?php else: ?>
                  <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-secondary border" style="width: 36px; height: 36px; min-width: 36px;">
                    <i class="bi bi-person-fill fs-5"></i>
                  </div>
                <?php endif; ?>
                <div class="overflow-hidden">
                  <div class="fw-bold text-[#1B2A3A] fs-6 text-truncate"><?= e($l['visitor_name']) ?></div>
                  <div class="small text-[#53595F] text-truncate" style="font-size: 0.75rem;"><?= e($l['institution'] ?: 'PT BPR Mitratama') ?> &bull; <span class="badge bg-light text-dark border"><?= e($l['visitor_type']) ?></span></div>
                </div>
              </div>

              <div class="small text-[#53595F] mb-2" style="font-size: 0.78rem;">
                <div class="mb-1"><i class="bi bi-tools text-[#0E5C58] me-1.5"></i><strong>Keperluan:</strong> <?= e($l['purpose']) ?></div>
                <?php if (!empty($l['purpose_detail'])): ?>
                  <div class="text-secondary ps-3 mb-1" style="font-size: 0.74rem;">&ldquo;<?= e($l['purpose_detail']) ?>&rdquo;</div>
                <?php endif; ?>
                <div><i class="bi bi-person-badge text-[#0E5C58] me-1.5"></i><strong>Pendamping IT:</strong> <?= e($l['accompanied_by'] ?: 'Mandiri (IT Ops)') ?></div>
              </div>

              <div class="p-2 rounded-2 bg-light d-flex align-items-center justify-content-between small text-muted mb-2" style="font-size: 0.74rem;">
                <div>
                  <i class="bi bi-box-arrow-in-right text-success me-1"></i>Masuk: <strong><?= date('d/m H:i', $enterTs) ?></strong> (<?= e($l['temperature_entry'] ? $l['temperature_entry'].'°C' : '-') ?>)
                </div>
                <div>
                  <i class="bi bi-box-arrow-right text-danger me-1"></i>Keluar: <strong><?= $exitTs ? date('H:i', $exitTs) : '-' ?></strong> (<?= e($l['temperature_exit'] ? $l['temperature_exit'].'°C' : '-') ?>)
                </div>
              </div>

              <?php if (!empty($l['notes'])): ?>
                <div class="small text-muted bg-white p-2 rounded border mb-2" style="font-size: 0.74rem;">
                  <i class="bi bi-chat-left-text me-1 text-secondary"></i><strong>Catatan:</strong> <?= e($l['notes']) ?>
                </div>
              <?php endif; ?>

              <div class="d-flex align-items-center gap-2 pt-1">
                <?php if ($isInside): ?>
                  <button type="button" class="btn btn-sm btn-danger rounded-[6px] flex-grow-1 fw-bold shadow-xs py-1.5" onclick="openCheckoutModal(<?= (int)$l['id'] ?>, <?= htmlspecialchars(json_encode($l['visitor_name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($l['log_code']), ENT_QUOTES, 'UTF-8') ?>)">
                    <i class="bi bi-box-arrow-right me-1"></i> Check-Out Keluar Sekarang
                  </button>
                <?php endif; ?>
                <?php if (!empty($l['photo_in'])): ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary rounded-[6px] px-3 py-1.5" onclick="previewPhoto(<?= htmlspecialchars(json_encode($l['photo_in']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($l['visitor_name']), ENT_QUOTES, 'UTF-8') ?>)">
                    <i class="bi bi-image me-1"></i> Foto
                  </button>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- DESKTOP/LAPTOP TABLE VIEW: Tampil pada layar lebar -->
    <div class="table-responsive d-none d-md-block">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th class="ps-3" style="width: 140px;">No. Log &amp; Masuk</th>
            <th style="width: 130px;">Keluar &amp; Durasi</th>
            <th>Pengunjung / Petugas</th>
            <th>Keperluan Kunjungan</th>
            <th>Pendamping IT</th>
            <th class="text-center" style="width: 90px;">Suhu</th>
            <th class="text-center" style="width: 110px;">Status</th>
            <th class="text-end pe-3" style="width: 110px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr>
              <td colspan="8" class="text-center py-5 text-muted">
                <i class="bi bi-door-closed display-6 d-block mb-2 text-secondary opacity-50"></i>
                <div class="fw-semibold">Belum ada riwayat kunjungan ruang server tercatat.</div>
                <div class="small text-muted mt-1">Gunakan tombol <strong>Check-In Masuk</strong> atau scan QR pintu untuk mencatat kunjungan baru.</div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $l): 
              $isInside = ($l['status'] === 'in_room');
              $enterTs = strtotime($l['entry_time']);
              $exitTs = !empty($l['exit_time']) ? strtotime($l['exit_time']) : null;
            ?>
              <tr>
                <td class="ps-3">
                  <div class="fw-bold font-monospace text-[#0E5C58] small"><?= e($l['log_code']) ?></div>
                  <div class="text-[#53595F]" style="font-size: 0.74rem;">
                    <?= date('d/m/Y', $enterTs) ?> &middot; <strong><?= date('H:i', $enterTs) ?></strong>
                  </div>
                </td>
                <td>
                  <?php if ($exitTs): ?>
                    <div class="fw-semibold text-[#1B2A3A] small"><?= date('H:i', $exitTs) ?></div>
                    <div class="badge bg-[#D9F1EF] text-[#0E5C58] rounded-[6px] px-2 py-0.5" style="font-size: 0.70rem;">
                      <i class="bi bi-stopwatch me-1"></i><?= (int)$l['duration_minutes'] ?> mnt
                    </div>
                  <?php else: ?>
                    <span class="badge bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC] rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">
                      <span class="door-badge-pulse me-1"></span>Masih di dalam
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="fw-bold text-[#1B2A3A] d-flex align-items-center gap-1.5">
                    <?php if (!empty($l['photo_in'])): ?>
                      <img src="<?= e($l['photo_in']) ?>" class="rounded-circle object-fit-cover border" style="width: 24px; height: 24px; cursor: pointer;" onclick="previewPhoto(<?= htmlspecialchars(json_encode($l['photo_in']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($l['visitor_name']), ENT_QUOTES, 'UTF-8') ?>)" title="Klik untuk lihat foto masuk">
                    <?php endif; ?>
                    <span><?= e($l['visitor_name']) ?></span>
                  </div>
                  <div class="text-[#53595F]" style="font-size: 0.75rem;">
                    <?php if ($l['visitor_type'] === 'vendor_external'): ?>
                      <span class="badge bg-[#FEF3C7] text-[#B45309] border border-[#FDE68A]" style="font-size: 0.68rem;">Vendor</span>
                    <?php elseif ($l['visitor_type'] === 'auditor'): ?>
                      <span class="badge bg-[#F3E8FF] text-[#6B21A8] border border-[#E9D5FF]" style="font-size: 0.68rem;">Auditor</span>
                    <?php elseif ($l['visitor_type'] === 'internal_staff'): ?>
                      <span class="badge bg-[#E0E7FF] text-[#3730A3] border border-[#C7D2FE]" style="font-size: 0.68rem;">Staf Bank</span>
                    <?php else: ?>
                      <span class="badge bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC]" style="font-size: 0.68rem;">Internal IT</span>
                    <?php endif; ?>
                    <span class="ms-1"><?= e($l['institution']) ?></span>
                    <?php if (!empty($l['contact_number'])): ?>
                      &middot; <i class="bi bi-telephone ms-0.5"></i> <?= e($l['contact_number']) ?>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="fw-semibold text-[#1B2A3A] small"><?= e($l['purpose']) ?></div>
                  <?php if (!empty($l['purpose_detail'])): ?>
                    <div class="text-[#53595F]" style="font-size: 0.74rem;"><?= e(mb_strimwidth($l['purpose_detail'], 0, 60, '...')) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($l['tools_brought'])): ?>
                    <div class="text-secondary" style="font-size: 0.72rem;"><i class="bi bi-tools me-1"></i>Bawa: <?= e($l['tools_brought']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="small fw-medium text-[#1B2A3A]"><?= e($l['accompanied_by'] ?: '-') ?></div>
                </td>
                <td class="text-center font-monospace small">
                  <?php if (!empty($l['temperature_entry'])): ?>
                    <div class="fw-bold text-[#0E5C58]"><?= $l['temperature_entry'] ?>&deg;C</div>
                    <?php if (!empty($l['temperature_exit'])): ?>
                      <div class="text-muted xs">&darr; <?= $l['temperature_exit'] ?>&deg;C</div>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <?php if ($isInside): ?>
                    <span class="badge bg-[#DCFCE7] text-[#15803D] border border-[#86EFAC] rounded-pill px-2.5 py-1 fw-bold shadow-xs">
                      <span class="door-badge-pulse me-1"></span>DI DALAM
                    </span>
                  <?php else: ?>
                    <span class="badge bg-light text-[#53595F] border rounded-pill px-2.5 py-1 fw-semibold">
                      SELESAI
                    </span>
                  <?php endif; ?>
                </td>
                <td class="text-end pe-3">
                  <?php if ($isInside): ?>
                    <button type="button" class="btn btn-sm btn-danger rounded-[6px] px-2.5 py-1 shadow-xs fw-semibold" data-bs-toggle="modal" data-bs-target="#modalCheckout" onclick="openCheckoutModal(<?= (int)$l['id'] ?>, <?= htmlspecialchars(json_encode($l['visitor_name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($l['log_code']), ENT_QUOTES, 'UTF-8') ?>)" title="Check-Out Keluar">
                      <i class="bi bi-box-arrow-right me-1"></i>Keluar
                    </button>
                  <?php else: ?>
                    <?php if ($isAdmin): ?>
                      <form method="post" class="d-inline" onsubmit="return confirm('Hapus riwayat log kunjungan ini?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger p-1 px-2 rounded-[6px]" title="Hapus Log"><i class="bi bi-trash3"></i></button>
                      </form>
                    <?php else: ?>
                      <span class="text-muted small"><i class="bi bi-check2-all text-success"></i></span>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- MODAL 1: CHECK-IN MASUK RUANG SERVER       -->
<!-- ========================================== -->
<div class="modal fade" id="modalCheckin" tabindex="-1" aria-labelledby="modalCheckinLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg rounded-[12px] overflow-hidden">
      <form method="post" id="formCheckin">
        <input type="hidden" name="action" value="checkin">
        <input type="hidden" name="photo_in" id="inputPhotoIn">

        <div class="modal-header text-white py-3 px-4" style="background: #0E5C58 !important;">
          <div>
            <h5 class="modal-title fw-bold d-flex align-items-center gap-2 mb-1" id="modalCheckinLabel" style="font-size: 16px;">
              <i class="bi bi-door-open-fill"></i> Check-In Masuk Ruang Server &amp; Data Center
            </h5>
            <div class="small text-white text-opacity-85" style="font-size: 0.8rem;">
              <i class="bi bi-shield-check me-1"></i>Pencatatan resmi kepatuhan akses fisik Data Center BPR Mitra (POJK 75)
            </div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body p-4 bg-[#F4F5F7]">
          <!-- STEP 1: PILIHAN TIPE PENGUNJUNG (INTERACTIVE ROLE CARDS) -->
          <div class="card border border-[#D1D5D8] rounded-[10px] mb-3 p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="fw-bold text-[#1B2A3A] small text-uppercase" style="letter-spacing: 0.04em;">
                <span class="step-badge-teal me-1">1</span>
                Kategori Petugas / Pengunjung
              </span>
              <span class="text-muted small" style="font-size: 0.75rem;">Pilih sesuai identitas Anda</span>
            </div>

            <div class="row g-2">
              <div class="col-sm-3 col-6">
                <input type="radio" class="btn-check" name="visitor_type" id="typeIt" value="internal_it" checked onchange="selectVisitorType('internal_it')">
                <label class="visitor-card-neo w-100 p-2.5 text-center active" for="typeIt" id="lblTypeIt" onclick="selectVisitorType('internal_it')">
                  <div class="icon-circle bg-[#D9F1EF] text-[#0E5C58] mb-1 mx-auto">
                    <i class="bi bi-pc-display fs-5"></i>
                  </div>
                  <div class="fw-bold small title-text">Internal IT</div>
                  <div class="text-muted xs sub-text">Teknisi / Admin IT</div>
                </label>
              </div>

              <div class="col-sm-3 col-6">
                <input type="radio" class="btn-check" name="visitor_type" id="typeStaff" value="internal_staff" onchange="selectVisitorType('internal_staff')">
                <label class="visitor-card-neo w-100 p-2.5 text-center" for="typeStaff" id="lblTypeStaff" onclick="selectVisitorType('internal_staff')">
                  <div class="icon-circle bg-[#DCFCE7] text-[#16A34A] mb-1 mx-auto">
                    <i class="bi bi-person-badge fs-5"></i>
                  </div>
                  <div class="fw-bold small title-text">Staf Bank</div>
                  <div class="text-muted xs sub-text">Karyawan Non-IT</div>
                </label>
              </div>

              <div class="col-sm-3 col-6">
                <input type="radio" class="btn-check" name="visitor_type" id="typeVendor" value="vendor_external" onchange="selectVisitorType('vendor_external')">
                <label class="visitor-card-neo w-100 p-2.5 text-center" for="typeVendor" id="lblTypeVendor" onclick="selectVisitorType('vendor_external')">
                  <div class="icon-circle bg-[#FEF3C7] text-[#D97706] mb-1 mx-auto">
                    <i class="bi bi-building fs-5"></i>
                  </div>
                  <div class="fw-bold small title-text">Vendor Luar</div>
                  <div class="text-muted xs sub-text">Telkom, AC, Listrik</div>
                </label>
              </div>

              <div class="col-sm-3 col-6">
                <input type="radio" class="btn-check" name="visitor_type" id="typeAuditor" value="auditor" onchange="selectVisitorType('auditor')">
                <label class="visitor-card-neo w-100 p-2.5 text-center" for="typeAuditor" id="lblTypeAuditor" onclick="selectVisitorType('auditor')">
                  <div class="icon-circle bg-[#F3E8FF] text-[#7C3AED] mb-1 mx-auto">
                    <i class="bi bi-shield-check fs-5"></i>
                  </div>
                  <div class="fw-bold small title-text">Auditor SKAI</div>
                  <div class="text-muted xs sub-text">Pemeriksaan / OJK</div>
                </label>
              </div>
            </div>

            <!-- Dynamic Help Banner -->
            <div id="roleHelpBanner" class="alert border-0 rounded-[8px] py-2 px-3 mt-2.5 mb-0 d-flex align-items-center gap-2 small shadow-xs" style="background-color: #D9F1EF; color: #0E5C58;">
              <i class="bi bi-info-circle-fill fs-5"></i>
              <div id="roleHelpText">Kunjungan mandiri Staf IT Bank. Nama pengunjung otomatis diisi dengan akun Anda.</div>
            </div>
          </div>

          <!-- STEP 2: IDENTITAS PENGUNJUNG & PENDAMPING -->
          <div class="card border border-[#D1D5D8] rounded-[10px] mb-3 p-3 bg-white">
            <div class="fw-bold text-[#1B2A3A] small text-uppercase mb-2.5" style="letter-spacing: 0.04em;">
              <span class="step-badge-teal me-1">2</span>
              Identitas Pengunjung &amp; Staf Pendamping
            </div>

            <div class="row g-2.5">
              <div class="col-md-6">
                <label class="form-label fw-bold text-secondary small mb-1">
                  Nama Lengkap Pengunjung / Petugas <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person-fill"></i></span>
                  <input type="text" name="visitor_name" id="checkinVisitorName" class="form-control border-start-0" required value="<?= e($userName) ?>" placeholder="Contoh: Akhmad Hafizh Firmansyah">
                </div>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-bold text-secondary small mb-1">
                  Instansi / Perusahaan Vendor
                </label>
                <div class="input-group">
                  <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-building"></i></span>
                  <input type="text" name="institution" id="checkinInstitution" class="form-control border-start-0" value="PT BPR Mitratama Arthabuana" placeholder="Contoh: PT Telkom / Lintasarta">
                </div>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-bold text-secondary small mb-1">
                  No. WhatsApp / Kontak Petugas
                </label>
                <div class="input-group">
                  <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-whatsapp"></i></span>
                  <input type="text" name="contact_number" class="form-control border-start-0" placeholder="0812-XXXX-XXXX (opsional)">
                </div>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-bold text-secondary small mb-1">
                  Petugas Pendamping IT Bank <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person-check-fill"></i></span>
                  <input type="text" name="accompanied_by" id="checkinAccompaniedBy" class="form-control border-start-0" value="Mandiri (Teknisi IT Bank)" list="listItStaff" placeholder="Pilih atau ketik nama staf IT">
                  <datalist id="listItStaff">
                    <option value="Mandiri (Teknisi IT Bank)">
                    <?php foreach ($itStaffList as $stName): ?>
                      <option value="<?= e($stName) ?>">
                    <?php endforeach; ?>
                  </datalist>
                </div>
                <div class="form-text text-muted xs">Pilih staf IT yang mendampingi selama di ruang server.</div>
              </div>
            </div>
          </div>

          <!-- STEP 3: KEPERLUAN & PARAMETER RUANG SERVER -->
          <div class="card border border-[#D1D5D8] rounded-[10px] mb-3 p-3 bg-white">
            <div class="fw-bold text-[#1B2A3A] small text-uppercase mb-2.5" style="letter-spacing: 0.04em;">
              <span class="step-badge-teal me-1">3</span>
              Keperluan Masuk &amp; Parameter Ruang Server
            </div>

            <div class="mb-2.5">
              <label class="form-label fw-bold text-secondary small mb-1">
                Alasan / Keperluan Masuk <span class="text-danger">*</span>
              </label>
              <div class="input-group mb-2">
                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-card-checklist"></i></span>
                <select name="purpose" id="checkinPurpose" class="form-select border-start-0 fw-semibold" required>
                  <option value="Pemeliharaan Rutin Server & Jaringan">🛠️ Pemeliharaan Rutin Server & Jaringan</option>
                  <option value="Troubleshooting / Perbaikan Gangguan Core Banking">⚡ Troubleshooting / Perbaikan Core Banking</option>
                  <option value="Backup Data Offline / Penarikan Harddisk">💾 Backup Data Offline & Penarikan Harddisk/Tape</option>
                  <option value="Pemeriksaan & Servis AC Presisi Ruang Server">❄️ Pemeriksaan & Servis AC Presisi Ruang Server</option>
                  <option value="Uji Beban Genset & Pengecekan Aki UPS">🔋 Uji Beban Genset & Pengecekan Aki UPS</option>
                  <option value="Inspeksi Pengawasan Audit SKAI / OJK">🔍 Inspeksi Pengawasan Audit SKAI / OJK</option>
                  <option value="Kunjungan Vendor ISP / Perbaikan Kabel Fiber Optic">🌐 Kunjungan Vendor ISP / Fiber Optic</option>
                  <option value="Instalasi / Penambahan Perangkat Baru">📦 Instalasi / Penambahan Perangkat Rak Baru</option>
                  <option value="Lainnya">📝 Keperluan Lainnya...</option>
                </select>
              </div>
              <textarea name="purpose_detail" class="form-control form-control-sm" rows="2" placeholder="Tuliskan rincian singkat pekerjaan yang akan dilakukan (opsional)..."></textarea>
            </div>

            <div class="row g-2.5">
              <div class="col-md-6">
                <label class="form-label fw-bold text-secondary small mb-1">
                  Suhu Ruangan Saat Masuk (&deg;C)
                </label>
                <div class="input-group">
                  <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-thermometer-half"></i></span>
                  <input type="number" step="0.1" name="temperature_entry" id="inputTempEntry" class="form-control border-start-0 border-end-0" placeholder="20.0" value="20.0" oninput="updateTempBadge(this.value)">
                  <span class="input-group-text bg-light text-muted">&deg;C</span>
                </div>
                <div id="tempStatusBadge" class="mt-1">
                  <span class="badge bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC] xs fw-semibold">
                    <i class="bi bi-check-circle-fill me-1"></i>Suhu Dingin Ideal (Standar: 18°C - 22°C)
                  </span>
                </div>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-bold text-secondary small mb-1">
                  Peralatan yang Dibawa Masuk
                </label>
                <div class="input-group mb-1">
                  <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-tools"></i></span>
                  <input type="text" name="tools_brought" id="inputToolsBrought" class="form-control border-start-0" placeholder="Laptop teknisi, Toolkit, LAN tester">
                </div>
                <div class="d-flex flex-wrap gap-1">
                  <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill py-0 px-2" style="font-size: 0.70rem;" onclick="addQuickTool('Laptop')">+ Laptop</button>
                  <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill py-0 px-2" style="font-size: 0.70rem;" onclick="addQuickTool('Toolkit Obeng')">+ Toolkit</button>
                  <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill py-0 px-2" style="font-size: 0.70rem;" onclick="addQuickTool('LAN Tester')">+ LAN Tester</button>
                  <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill py-0 px-2" style="font-size: 0.70rem;" onclick="addQuickTool('Harddisk Backup')">+ Harddisk</button>
                </div>
              </div>
            </div>
          </div>

          <!-- STEP 4: DOKUMENTASI FOTO WAJAH (KAMERA MODERN) -->
          <div class="card border border-[#D1D5D8] rounded-[10px] p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div class="fw-bold text-[#1B2A3A] small text-uppercase" style="letter-spacing: 0.04em;">
                <span class="step-badge-teal me-1">4</span>
                Foto Dokumentasi Wajah Pengunjung
              </div>
              <span class="badge bg-light text-secondary border xs fw-normal">Opsional (Audit Log)</span>
            </div>

            <!-- Box Belum Buka Kamera -->
            <div id="camIdleBox" class="border border-dashed rounded-[8px] p-3 text-center bg-light bg-opacity-50">
              <div class="d-inline-flex align-items-center justify-content-center bg-[#D9F1EF] text-[#0E5C58] rounded-circle mb-2" style="width: 44px; height: 44px;">
                <i class="bi bi-camera-fill fs-5"></i>
              </div>
              <div class="fw-bold small text-[#1B2A3A] mb-1">Ambil Foto Wajah Pengunjung</div>
              <p class="text-muted xs mb-3" style="max-width: 420px; margin: 0 auto;">
                Ambil foto selfie/wajah pengunjung secara instan melalui webcam laptop atau kamera HP sebagai bukti kepatuhan audit.
              </p>
              <button type="button" class="btn btn-sm btn-primary-teal px-4 fw-bold shadow-xs d-inline-flex align-items-center gap-2" id="btnToggleCam" onclick="toggleWebcamCheckin()">
                <i class="bi bi-camera"></i> Buka Kamera Wajah
              </button>
            </div>

            <!-- Box Live Kamera Aktif -->
            <div id="camContainer" class="d-none text-center pt-2">
              <div class="position-relative d-inline-block rounded-[8px] overflow-hidden shadow-sm border" style="background: #000; max-width: 380px; width: 100%;">
                <video id="webcamVideo" autoplay playsinline style="width: 100%; height: auto; display: block;"></video>
                <div style="position: absolute; top: 15%; left: 25%; width: 50%; height: 70%; border: 2px dashed rgba(255,255,255,0.7); border-radius: 50%; pointer-events: none;"></div>
                <div class="position-absolute bottom-0 start-0 end-0 py-1 bg-dark bg-opacity-75 text-white xs">
                  <i class="bi bi-person-bounding-box me-1"></i> Posisikan wajah di dalam bingkai
                </div>
              </div>
              <canvas id="webcamCanvas" class="d-none"></canvas>
              
              <div class="mt-2.5 d-flex align-items-center justify-content-center gap-2">
                <button type="button" class="btn btn-sm btn-primary-teal px-4 fw-bold shadow-sm d-inline-flex align-items-center gap-1.5" onclick="snapPhotoCheckin()">
                  <i class="bi bi-camera-fill fs-6"></i> Ambil Foto (Jepret)
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-[6px] px-3" onclick="stopWebcamCheckin()">
                  <i class="bi bi-x-circle me-1"></i> Batal / Tutup Kamera
                </button>
              </div>
            </div>

            <!-- Box Foto Sudah Terambil -->
            <div id="previewSnapBox" class="d-none text-center p-3 border rounded-[8px] bg-light mt-2">
              <div class="d-inline-block position-relative shadow-sm rounded-3 overflow-hidden border border-success border-2">
                <img id="imgSnapPreview" src="" class="rounded-2" style="max-width: 150px; height: auto; display: block;">
                <span class="position-absolute top-0 end-0 badge bg-success m-1 shadow-xs">
                  <i class="bi bi-check-lg"></i>
                </span>
              </div>
              <div class="text-success fw-bold small mt-2">
                <i class="bi bi-check-circle-fill me-1"></i> Foto Wajah Berhasil Diambil
              </div>
              <button type="button" class="btn btn-xs btn-outline-teal rounded-pill px-3 mt-1.5" onclick="retakePhotoCheckin()">
                <i class="bi bi-arrow-repeat me-1"></i> Foto Ulang
              </button>
            </div>
          </div>
        </div>

        <div class="modal-footer bg-white border-top py-3 px-4 d-flex justify-content-between">
          <button type="button" class="btn btn-kombai-white px-4 fw-semibold" data-bs-dismiss="modal">
            <i class="bi bi-x-lg me-1"></i> Batal
          </button>
          <button type="submit" class="btn btn-primary-teal fw-bold px-4 shadow-xs d-inline-flex align-items-center gap-2" style="font-size: 0.92rem;">
            <i class="bi bi-door-open-fill"></i> Konfirmasi Masuk Ruang Server
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- MODAL 2: SCANNER BARCODE LIVE              -->
<!-- ========================================== -->
<div class="modal fade" id="modalScanner" tabindex="-1" aria-labelledby="modalScannerLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-[12px] overflow-hidden">
      <div class="modal-header bg-[#1B2A3A] text-white py-3 px-4">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="modalScannerLabel" style="font-size: 15px;">
          <i class="bi bi-qr-code-scan text-[#D9F1EF]"></i> Scanner Barcode &amp; QR Pintu Server
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="stopScannerModal()"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <p class="text-[#53595F] small mb-3">
          Arahkan kamera ke <strong>Stiker Barcode / QR Code Pintu Ruang Server</strong> atau scan Barcode ID Card Petugas:
        </p>
        <div id="scannerReader" class="scanner-video-box mb-3"></div>
        <div id="scannerStatusText" class="small fw-semibold text-[#0E5C58]">
          <i class="bi bi-camera me-1"></i> Menyiapkan kamera scanner...
        </div>

        <div class="hr-text text-muted my-3 small">&mdash; ATAU KETIK KODE MANUAL &mdash;</div>
        <div class="input-group">
          <input type="text" id="manualBarcodeInput" class="form-control" placeholder="Ketik nomor log / ID petugas..." onkeydown="if(event.key==='Enter') processManualBarcode()">
          <button class="btn btn-primary-teal fw-bold" type="button" onclick="processManualBarcode()">Proses</button>
        </div>
      </div>
      <div class="modal-footer bg-[#F4F5F7] py-2 px-3 justify-content-between">
        <span class="text-muted xs"><i class="bi bi-shield-check me-1"></i>Deteksi otomatis</span>
        <button type="button" class="btn btn-sm btn-kombai-white rounded-[6px] px-3" data-bs-dismiss="modal" onclick="stopScannerModal()">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- MODAL 3: CHECK-OUT KELUAR RUANG SERVER     -->
<!-- ========================================== -->
<div class="modal fade" id="modalCheckout" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-[12px] overflow-hidden">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="checkout">
        <input type="hidden" name="id" id="checkoutLogId">

        <div class="modal-header text-white py-3 px-4" style="background: #DC2626 !important;">
          <div>
            <h5 class="modal-title fw-bold d-flex align-items-center gap-2 mb-1" style="font-size: 15px;">
              <i class="bi bi-box-arrow-right"></i> Check-Out Keluar Ruang Server
            </h5>
            <div class="small text-white text-opacity-85" style="font-size: 0.8rem;">
              Selesaikan kunjungan dan catat waktu keluar resmi
            </div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body p-4 bg-[#F4F5F7]">
          <div class="card border border-[#D1D5D8] rounded-[10px] p-3 mb-3 bg-white border-start border-4 border-danger">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <span class="text-muted xs text-uppercase fw-bold">Petugas yang Keluar</span>
                <div class="fw-bold fs-5 text-[#1B2A3A]" id="checkoutVisitorName">Nama Pengunjung</div>
                <div class="small text-[#0E5C58] font-monospace" id="checkoutLogCode">Kode Log</div>
              </div>
              <div class="rounded-circle bg-[#FEE2E2] text-danger p-2.5">
                <i class="bi bi-person-walking fs-4"></i>
              </div>
            </div>
          </div>

          <div class="card border border-[#D1D5D8] rounded-[10px] p-3 mb-3 bg-white">
            <label class="form-label fw-bold text-secondary small mb-1">Suhu Ruangan Saat Keluar (&deg;C)</label>
            <div class="input-group mb-1">
              <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-thermometer-half"></i></span>
              <input type="number" step="0.1" name="temperature_exit" class="form-control border-start-0 border-end-0" placeholder="20.0" value="20.0">
              <span class="input-group-text bg-light text-muted">&deg;C</span>
            </div>
            <div class="text-muted xs"><i class="bi bi-info-circle me-1"></i>Pastikan AC ruang server tetap dingin dan pintu terkunci rapat.</div>
          </div>

          <div class="card border border-[#D1D5D8] rounded-[10px] p-3 bg-white">
            <label class="form-label fw-bold text-secondary small mb-1">Catatan Hasil Pekerjaan / Kondisi Ruang Server</label>
            <textarea name="notes" id="checkoutNotes" class="form-control mb-2" rows="3" placeholder="Contoh: Pekerjaan selesai normal, kabel rapi, suhu AC 20°C, pintu dikunci kembali..."></textarea>
            
            <div class="d-flex flex-wrap gap-1">
              <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill py-0 px-2" style="font-size: 0.70rem;" onclick="addQuickNote('Pekerjaan selesai normal.')">+ Selesai Normal</button>
              <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill py-0 px-2" style="font-size: 0.70rem;" onclick="addQuickNote('Kondisi AC dingin normal.')">+ AC Dingin</button>
              <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill py-0 px-2" style="font-size: 0.70rem;" onclick="addQuickNote('Pintu dikunci kembali rapat.')">+ Pintu Dikunci</button>
              <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill py-0 px-2" style="font-size: 0.70rem;" onclick="addQuickNote('Perangkat beroperasi normal.')">+ Perangkat Normal</button>
            </div>
          </div>
        </div>

        <div class="modal-footer bg-white border-top py-3 px-4 d-flex justify-content-between">
          <button type="button" class="btn btn-kombai-white px-4 fw-semibold" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger fw-bold rounded-[8px] px-4 shadow-xs d-inline-flex align-items-center gap-2">
            <i class="bi bi-box-arrow-right"></i> Konfirmasi Selesai (Check-Out)
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- MODAL 3B: PILIH PETUGAS KELUAR (SCAN HP)   -->
<!-- ========================================== -->
<div class="modal fade" id="modalSelectCheckout" tabindex="-1" aria-labelledby="modalSelectCheckoutLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-[12px] overflow-hidden">
      <div class="modal-header bg-[#DC2626] text-white py-3 px-4">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="modalSelectCheckoutLabel" style="font-size: 15px;">
          <i class="bi bi-box-arrow-right"></i> Pilih Petugas yang Keluar
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <p class="text-muted small mb-3">
          Terdapat <strong class="text-danger"><?= count($liveOccupants) ?> orang</strong> yang sedang berada di dalam ruang server. Silakan tap nama Anda atau vendor yang akan Check-Out:
        </p>
        <div class="d-flex flex-column gap-2">
          <?php foreach ($liveOccupants as $occ): 
            $enterTs = strtotime($occ['entry_time']);
            $diffSec = time() - $enterTs;
            if ($diffSec >= 27000 && $diffSec <= 30600) { $diffSec -= 28800; }
            elseif ($diffSec >= 23400 && $diffSec <= 27000) { $diffSec -= 25200; }
            $durMin = max(1, (int)round(max(0, $diffSec) / 60));
          ?>
            <div class="card border border-[#D1D5D8] rounded-[8px] p-3 shadow-xs hover-card" style="cursor: pointer; transition: all 0.2s;" onclick="openCheckoutModal(<?= (int)$occ['id'] ?>, <?= htmlspecialchars(json_encode($occ['visitor_name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($occ['log_code']), ENT_QUOTES, 'UTF-8') ?>); bootstrap.Modal.getInstance(document.getElementById('modalSelectCheckout')).hide();">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <div class="fw-bold text-[#1B2A3A] fs-6 d-flex align-items-center gap-1.5">
                    <i class="bi bi-person-fill text-danger"></i>
                    <span><?= e($occ['visitor_name']) ?></span>
                  </div>
                  <div class="small text-muted mt-0.5"><?= e($occ['institution'] ?: 'PT BPR Mitratama') ?> &bull; <code><?= e($occ['log_code']) ?></code></div>
                  <div class="small text-danger fw-semibold mt-1">
                    <i class="bi bi-clock-history me-1"></i>Masuk jam <?= date('H:i', strtotime($occ['entry_time'])) ?> (<?= $durMin ?> menit lalu)
                  </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-[6px] px-3 fw-bold shadow-xs">
                  Keluar <i class="bi bi-box-arrow-right ms-1"></i>
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer bg-[#F4F5F7] py-2 px-3">
        <button type="button" class="btn btn-sm btn-kombai-white rounded-[6px] px-3" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- MODAL 3C: TIDAK ADA ORANG DI DALAM         -->
<!-- ========================================== -->
<div class="modal fade" id="modalNoOccupants" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-[12px] text-center p-4">
      <div class="my-2 display-4 text-[#D97706]">
        <i class="bi bi-door-closed-fill"></i>
      </div>
      <h5 class="fw-bold text-[#1B2A3A] mt-2">Tidak Ada yang Sedang di Dalam</h5>
      <p class="text-[#53595F] small mb-3">
        Saat ini tidak ada data petugas atau vendor yang tercatat aktif di dalam Ruang Server.
      </p>
      <div class="d-flex justify-content-center gap-2">
        <button type="button" class="btn btn-sm btn-kombai-white rounded-[6px] px-4" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-sm btn-primary-teal fw-bold rounded-[6px] px-4" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#modalCheckin">
          <i class="bi bi-door-open-fill me-1"></i> Buka Form Check-In (Masuk)
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- MODAL 4: PREVIEW FOTO PENGUNJUNG           -->
<!-- ========================================== -->
<div class="modal fade" id="modalPreviewPhoto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-[12px] overflow-hidden">
      <div class="modal-header bg-[#1B2A3A] text-white py-2 px-3">
        <h6 class="modal-title fw-bold small" id="previewTitle">Foto Pengunjung</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0 text-center bg-black">
        <img id="imgBigPreview" src="" class="img-fluid" style="max-height: 80vh; width: auto;">
      </div>
    </div>
  </div>
</div>

$body = ob_get_clean();

ob_start();
?>
<script>
function addQuickTool(tool) {
  const inp = document.getElementById("inputToolsBrought");
  if (!inp) return;
  if (!inp.value.trim()) {
    inp.value = tool;
  } else if (!inp.value.toLowerCase().includes(tool.toLowerCase())) {
    inp.value = inp.value.trim() + ", " + tool;
  }
}

function addQuickNote(text) {
  const notes = document.getElementById("checkoutNotes");
  if (!notes) return;
  if (!notes.value.trim()) {
    notes.value = text;
  } else if (!notes.value.includes(text)) {
    notes.value = notes.value.trim() + " " + text;
  }
}

function updateTempBadge(val) {
  const badge = document.getElementById("tempStatusBadge");
  if (!badge) return;
  const temp = parseFloat(val);
  if (isNaN(temp)) {
    badge.innerHTML = '';
    return;
  }
  if (temp <= 22.5 && temp >= 18.0) {
    badge.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle xs fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>Suhu Dingin Ideal (Standar: 18°C - 22°C)</span>';
  } else if (temp > 22.5 && temp <= 24.5) {
    badge.innerHTML = '<span class="badge bg-info-subtle text-info border border-info-subtle xs fw-semibold"><i class="bi bi-info-circle me-1"></i>Suhu Cukup Dingin (Batas Toleransi)</span>';
  } else if (temp > 24.5) {
    badge.innerHTML = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle xs fw-semibold"><i class="bi bi-exclamation-triangle-fill me-1"></i>Suhu Cenderung Hangat (>24°C - Periksa AC)</span>';
  } else {
    badge.innerHTML = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle xs fw-semibold"><i class="bi bi-snow me-1"></i>Suhu Sangat Dingin (<18°C)</span>';
  }
}

function retakePhotoCheckin() {
  document.getElementById("inputPhotoIn").value = "";
  document.getElementById("previewSnapBox").classList.add("d-none");
  toggleWebcamCheckin();
}

function selectVisitorType(type) {
  const radioMap = {
    'internal_it': 'typeIt',
    'internal_staff': 'typeStaff',
    'vendor_external': 'typeVendor',
    'auditor': 'typeAuditor'
  };
  const radioId = radioMap[type];
  if (radioId) {
    const radio = document.getElementById(radioId);
    if (radio) {
      radio.checked = true;
    }
  }
  onVisitorTypeChange(type);
  updateVisitorTypeUI();
}

function updateVisitorTypeUI() {
  const radioMap = {
    'internal_it': 'lblTypeIt',
    'internal_staff': 'lblTypeStaff',
    'vendor_external': 'lblTypeVendor',
    'auditor': 'lblTypeAuditor'
  };

  const currentChecked = document.querySelector('input[name="visitor_type"]:checked');
  const activeVal = currentChecked ? currentChecked.value : 'internal_it';

  Object.keys(radioMap).forEach(function(val) {
    const lbl = document.getElementById(radioMap[val]);
    if (!lbl) return;
    if (val === activeVal) {
      lbl.classList.add('active');
    } else {
      lbl.classList.remove('active');
    }
  });
}

function onVisitorTypeChange(type) {
  const inst = document.getElementById("checkinInstitution");
  const acc = document.getElementById("checkinAccompaniedBy");
  const nameInp = document.getElementById("checkinVisitorName") || document.getElementById("inputVisitorName");
  const helpText = document.getElementById("roleHelpText");
  const helpBanner = document.getElementById("roleHelpBanner");
  const defaultAdmin = <?= json_encode($userName) ?>;

  if (type === "vendor_external") {
    if (inst) {
      if (inst.value === "PT BPR Mitratama Arthabuana") inst.value = "";
      inst.placeholder = "Nama Perusahaan Vendor (PT Telkom / Lintasarta / AC)";
    }
    if (acc) {
      if (acc.value === "Mandiri (Teknisi IT Bank)" || !acc.value) acc.value = defaultAdmin;
    }
    if (nameInp && nameInp.value === defaultAdmin) {
      nameInp.value = "";
      nameInp.placeholder = "Nama Lengkap Petugas Vendor / Tamu";
    }
    if (helpText) helpText.innerHTML = "<strong>Kunjungan Pihak Rekanan / Vendor Eksternal.</strong> Wajib didampingi oleh Staf IT Bank selama di ruang server.";
    if (helpBanner) helpBanner.className = "alert alert-warning border-0 rounded-3 py-2 px-3 mt-2.5 mb-0 d-flex align-items-center gap-2 small shadow-xs";
  } else if (type === "auditor") {
    if (inst) {
      if (inst.value === "PT BPR Mitratama Arthabuana" || !inst.value) inst.value = "SKAI / OJK / Auditor";
    }
    if (acc) {
      if (acc.value === "Mandiri (Teknisi IT Bank)" || !acc.value) acc.value = defaultAdmin;
    }
    if (nameInp && nameInp.value === defaultAdmin) {
      nameInp.value = "";
      nameInp.placeholder = "Nama Lengkap Auditor";
    }
    if (helpText) helpText.innerHTML = "<strong>Inspeksi Pengawasan / Audit Data Center.</strong> Masukkan identitas auditor dan pastikan didampingi Staf IT Bank.";
    if (helpBanner) helpBanner.className = "alert border-0 rounded-3 py-2 px-3 mt-2.5 mb-0 d-flex align-items-center gap-2 small shadow-xs" ; if (helpBanner) { helpBanner.style.backgroundColor = "#F3E8FF"; helpBanner.style.color = "#6B21A8"; }
  } else if (type === "internal_staff") {
    if (inst) inst.value = "PT BPR Mitratama Arthabuana";
    if (acc) {
      if (acc.value === "Mandiri (Teknisi IT Bank)" || !acc.value) acc.value = defaultAdmin;
    }
    if (nameInp && nameInp.value === defaultAdmin) {
      nameInp.value = "";
      nameInp.placeholder = "Nama Lengkap Staf Bank";
    }
    if (helpText) helpText.innerHTML = "<strong>Kunjungan Karyawan Internal Non-IT.</strong> Wajib didampingi Staf IT Bank yang berwenang.";
    if (helpBanner) helpBanner.className = "alert alert-success border-0 rounded-3 py-2 px-3 mt-2.5 mb-0 d-flex align-items-center gap-2 small shadow-xs";
  } else {
    // internal_it
    if (inst) inst.value = "PT BPR Mitratama Arthabuana";
    if (acc) acc.value = "Mandiri (Teknisi IT Bank)";
    if (nameInp && !nameInp.value) nameInp.value = defaultAdmin;
    if (helpText) helpText.innerHTML = "<strong>Kunjungan Mandiri Staf IT Bank.</strong> Nama petugas otomatis diisi sesuai identitas login Anda.";
    if (helpBanner) helpBanner.className = "alert border-0 rounded-3 py-2 px-3 mt-2.5 mb-0 d-flex align-items-center gap-2 small shadow-xs"; if (helpBanner) { helpBanner.style.backgroundColor = "#D9F1EF"; helpBanner.style.color = "#0E5C58"; }
  }
}

function openCheckoutModal(id, name, code) {
  const logIdEl = document.getElementById("checkoutLogId");
  const nameEl = document.getElementById("checkoutVisitorName");
  const codeEl = document.getElementById("checkoutLogCode");
  if (logIdEl) logIdEl.value = id;
  if (nameEl) nameEl.textContent = name;
  if (codeEl) codeEl.textContent = "Kode Log: " + code;
  
  const modalEl = document.getElementById("modalCheckout");
  if (modalEl && typeof bootstrap !== "undefined") {
    const m = bootstrap.Modal.getOrCreateInstance(modalEl);
    m.show();
  }
}

function previewPhoto(src, name) {
  document.getElementById("imgBigPreview").src = src;
  document.getElementById("previewTitle").textContent = "Dokumentasi Masuk: " + name;
  const m = new bootstrap.Modal(document.getElementById("modalPreviewPhoto"));
  m.show();
}

// Webcam Capture Selfie Logic
let webcamStream = null;
function toggleWebcamCheckin() {
  const container = document.getElementById("camContainer");
  const btn = document.getElementById("btnToggleCam");
  if (!container || !btn) return;

  if (container.classList.contains("d-none")) {
    container.classList.remove("d-none");
    btn.innerHTML = '<i class="bi bi-camera-video-off me-1"></i> Tutup Kamera';

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      alert("Browser ini tidak mendukung akses kamera langsung atau koneksi tidak melalui HTTPS.");
      stopWebcamCheckin();
      return;
    }

    // Fungsi helper coba kamera: jika facingMode 'user' gagal (misal di PC/laptop), fallback ke kamera default
    function startCamera(constraints) {
      navigator.mediaDevices.getUserMedia(constraints)
        .then(function(stream) {
          webcamStream = stream;
          const v = document.getElementById("webcamVideo");
          if (v) {
            v.srcObject = stream;
            v.play().catch(function(e) { console.warn("Video play:", e); });
          }
        })
        .catch(function(err) {
          console.warn("Kamera gagal dengan constraint:", constraints, err);
          if (constraints.video && constraints.video.facingMode) {
            // Fallback: coba kamera apa saja tanpa batasan facingMode
            startCamera({ video: true });
          } else {
            alert("Tidak dapat membuka kamera: " + (err.message || err.name || err) + "\n\nPastikan izin akses kamera (camera permission) diizinkan di browser.");
            stopWebcamCheckin();
          }
        });
    }

    startCamera({ video: { facingMode: "user", width: { ideal: 640 }, height: { ideal: 480 } } });
  } else {
    stopWebcamCheckin();
  }
}

function stopWebcamCheckin() {
  if (webcamStream) {
    webcamStream.getTracks().forEach(function(t) { t.stop(); });
    webcamStream = null;
  }
  const container = document.getElementById("camContainer");
  const idleBox = document.getElementById("camIdleBox");
  const snapBox = document.getElementById("previewSnapBox");
  
  if (container) container.classList.add("d-none");
  const photoVal = document.getElementById("inputPhotoIn") ? document.getElementById("inputPhotoIn").value : '';
  if (photoVal) {
    if (snapBox) snapBox.classList.remove("d-none");
    if (idleBox) idleBox.classList.add("d-none");
  } else {
    if (idleBox) idleBox.classList.remove("d-none");
    if (snapBox) snapBox.classList.add("d-none");
  }
}

function snapPhotoCheckin() {
  const v = document.getElementById("webcamVideo");
  const c = document.getElementById("webcamCanvas");
  if (!v || !c) return;
  const w = v.videoWidth || 480;
  const h = v.videoHeight || 360;
  c.width = w;
  c.height = h;
  const ctx = c.getContext("2d");
  ctx.drawImage(v, 0, 0, w, h);
  const dataUrl = c.toDataURL("image/jpeg", 0.85);
  const photoInput = document.getElementById("inputPhotoIn");
  const snapPreview = document.getElementById("imgSnapPreview");
  const snapBox = document.getElementById("previewSnapBox");

  if (photoInput) photoInput.value = dataUrl;
  if (snapPreview) snapPreview.src = dataUrl;
  if (snapBox) snapBox.classList.remove("d-none");
  stopWebcamCheckin();
}

// Barcode & QR Scanner Modal Logic
let html5QrScanner = null;
function startScannerModal() {
  const statusEl = document.getElementById("scannerStatusText");
  if (statusEl) statusEl.innerHTML = '<i class="bi bi-camera me-1"></i> Mengaktifkan kamera scanner...';
  
  setTimeout(function() {
    try {
      html5QrScanner = new Html5Qrcode("scannerReader");
      html5QrScanner.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        function(decodedText, decodedResult) {
          onQrCodeDetected(decodedText);
        },
        function(errorMessage) {
          // scanning frame
        }
      ).then(function() {
        if (statusEl) statusEl.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Kamera aktif &mdash; Arahkan ke Barcode / QR Pintu</span>';
      }).catch(function(err) {
        if (statusEl) statusEl.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Gagal membuka kamera: ' + err + '</span>';
      });
    } catch(e) {
      if (statusEl) statusEl.innerHTML = '<span class="text-danger">Scanner error: ' + e.message + '</span>';
    }
  }, 400);
}

function stopScannerModal() {
  if (html5QrScanner) {
    html5QrScanner.stop().then(function() {
      html5QrScanner.clear();
      html5QrScanner = null;
    }).catch(function() {});
  }
}

const liveOccupantsData = <?= json_encode(array_map(function($o) {
  return [
    'id' => (int)$o['id'],
    'visitor_name' => $o['visitor_name'],
    'log_code' => $o['log_code'],
    'user_id' => (int)($o['user_id'] ?? 0)
  ];
}, $liveOccupants)) ?>;

function handleCheckoutScanAction() {
  if (!liveOccupantsData || liveOccupantsData.length === 0) {
    const m = new bootstrap.Modal(document.getElementById("modalNoOccupants"));
    m.show();
    return;
  }

  // Jika ada log id spesifik atau hanya 1 orang, atau cocok dengan user login
  let matched = null;
  const currentUid = <?= (int)$userId ?>;
  const currentUname = <?= json_encode($userName) ?>;

  matched = liveOccupantsData.find(function(o) {
    return (currentUid > 0 && o.user_id === currentUid) || (o.visitor_name && o.visitor_name.toLowerCase() === currentUname.toLowerCase());
  });

  if (!matched && liveOccupantsData.length === 1) {
    matched = liveOccupantsData[0];
  }

  if (matched) {
    openCheckoutModal(matched.id, matched.visitor_name, matched.log_code);
  } else {
    // Tampilkan daftar pilihan orang yang keluar
    const m = new bootstrap.Modal(document.getElementById("modalSelectCheckout"));
    m.show();
  }
}

function onQrCodeDetected(text) {
  stopScannerModal();
  const scanModalEl = document.getElementById("modalScanner");
  const scanModal = bootstrap.Modal.getInstance(scanModalEl);
  if (scanModal) scanModal.hide();

  // 1. Jika QR adalah URL Checkout Keluar (Pintu Keluar HP)
  if (text.includes("checkout_scan") || text.includes("action=checkout")) {
    handleCheckoutScanAction();
    return;
  }

  // 2. Jika QR adalah URL Check-In Masuk (Pintu Masuk HP)
  if (text.includes("checkin_scan") || text.includes("server_room") || text.includes("door")) {
    const checkinModal = bootstrap.Modal.getOrCreateInstance(document.getElementById("modalCheckin"));
    checkinModal.show();
    return;
  }

  // 3. Jika Barcode ID / Kode Log Petugas (misal SRL-202610-0001)
  processManualBarcode(text);
}

function processManualBarcode(overrideText) {
  const code = (overrideText || document.getElementById("manualBarcodeInput").value).trim();
  if (!code) return;

  // Cek apakah code ini cocok dengan kode log yang sedang di dalam (Live Occupants)
  const occMatch = liveOccupantsData.find(function(o) {
    return (o.log_code && o.log_code.toLowerCase() === code.toLowerCase()) || 
           (o.visitor_name && o.visitor_name.toLowerCase().includes(code.toLowerCase()));
  });

  if (occMatch) {
    // Langsung buka Check-Out untuk orang ini!
    openCheckoutModal(occMatch.id, occMatch.visitor_name, occMatch.log_code);
    return;
  }

  // Jika tidak di dalam, buka form checkin dan masukkan nama/identitas
  const checkinModal = bootstrap.Modal.getOrCreateInstance(document.getElementById("modalCheckin"));
  const nameInp = document.getElementById("checkinVisitorName") || document.getElementById("inputVisitorName");
  if (nameInp) nameInp.value = code;
  checkinModal.show();
}

document.addEventListener("DOMContentLoaded", function() {
  updateVisitorTypeUI();

  const mCheckinEl = document.getElementById("modalCheckin");
  if (mCheckinEl) {
    mCheckinEl.addEventListener("show.bs.modal", updateVisitorTypeUI);
    mCheckinEl.addEventListener("shown.bs.modal", updateVisitorTypeUI);
    mCheckinEl.addEventListener("hidden.bs.modal", stopWebcamCheckin);
  }

  <?php if ($autoViewLogs): ?>
    setTimeout(function() {
      const el = document.getElementById("riwayatLogTable");
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 350);
  <?php endif; ?>
  <?php if ($autoOpenCheckout): ?>
    setTimeout(function() {
      <?php if (!empty($autoCheckoutMatch)): ?>
        openCheckoutModal(<?= (int)$autoCheckoutMatch['id'] ?>, <?= json_encode($autoCheckoutMatch['visitor_name']) ?>, <?= json_encode($autoCheckoutMatch['log_code']) ?>);
      <?php elseif (!empty($liveOccupants)): ?>
        const m = bootstrap.Modal.getOrCreateInstance(document.getElementById("modalSelectCheckout"));
        m.show();
      <?php else: ?>
        const m = bootstrap.Modal.getOrCreateInstance(document.getElementById("modalNoOccupants"));
        m.show();
      <?php endif; ?>
    }, 350);
  <?php elseif ($autoOpenCheckin): ?>
    setTimeout(function() {
      const checkinModal = bootstrap.Modal.getOrCreateInstance(document.getElementById("modalCheckin"));
      checkinModal.show();
    }, 350);
  <?php endif; ?>
});
</script>
<?php
$extraScript = ob_get_clean();

render_page('Log Masuk Ruang Server & Data Center', $body, $head, $extraScript, $isLoggedIn);
