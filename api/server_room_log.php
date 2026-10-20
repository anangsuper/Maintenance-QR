<?php
require __DIR__ . '/bootstrap.php';
require_login();

$userName = current_user_name();
$userRole = current_user_role();
$userId = current_user_id();
$isAdmin = is_admin();

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

    // Generate kode unik log: SRL-YYYYMM-XXXX
    $prefix = 'SRL-' . date('Ym') . '-';
    $stCount = db()->prepare("SELECT COUNT(*) FROM server_room_logs WHERE log_code LIKE ?");
    $stCount->execute([$prefix . '%']);
    $cnt = (int)$stCount->fetchColumn() + 1;
    $logCode = $prefix . str_pad((string)$cnt, 4, '0', STR_PAD_LEFT);

    try {
        $stmt = db()->prepare("
            INSERT INTO server_room_logs (
                log_code, user_id, visitor_name, visitor_type, institution, contact_number,
                accompanied_by, purpose, purpose_detail, entry_time, temperature_entry,
                tools_brought, photo_in, status, cabang, ruangan, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, NOW(), ?,
                ?, ?, 'in_room', ?, ?, NOW()
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
            $temperatureEntry,
            $toolsBrought,
            $photoIn ?: null,
            $cabang,
            $ruangan
        ]);

        record_audit_log('SERVER_ROOM_CHECKIN', 'SECURITY', $userId, $userName, "Check-in masuk ruang server: {$visitorName} ({$institution}) - Keperluan: {$purpose}", [
            'log_code' => $logCode,
            'visitor' => $visitorName,
            'type' => $visitorType,
            'purpose' => $purpose,
            'suhu' => $temperatureEntry
        ]);

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
                $entryTime = strtotime($row['entry_time']);
                $nowTime = time();
                $durMin = max(1, (int)round(($nowTime - $entryTime) / 60));

                $stmt = db()->prepare("
                    UPDATE server_room_logs SET
                        exit_time = NOW(),
                        duration_minutes = ?,
                        temperature_exit = ?,
                        notes = ?,
                        status = 'completed',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$durMin, $temperatureExit, $notes ?: null, $id]);

                record_audit_log('SERVER_ROOM_CHECKOUT', 'SECURITY', $userId, $userName, "Check-out keluar ruang server: {$row['visitor_name']} ({$row['log_code']}) - Durasi: {$durMin} menit", [
                    'log_code' => $row['log_code'],
                    'duration_minutes' => $durMin,
                    'suhu_keluar' => $temperatureExit
                ]);

                $_SESSION['flash_msg'] = "✓ Check-Out Berhasil! Kunjungan {$row['visitor_name']} selesai. Durasi: {$durMin} menit.";
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

$autoOpenScanModal = (isset($_GET['action']) && in_array($_GET['action'], ['scan', 'checkin_quick'], true));

// Header styling & Script HTML
$head = '
<style>
.door-badge-pulse {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #10B981;
  box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
  animation: pulseGreen 1.8s infinite;
}
@keyframes pulseGreen {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
.live-occupant-card {
  background: linear-gradient(135deg, rgba(86, 202, 0, 0.08) 0%, rgba(22, 177, 255, 0.05) 100%);
  border: 1.5px solid rgba(86, 202, 0, 0.35);
  border-radius: 12px;
}
[data-bs-theme="dark"] .live-occupant-card {
  background: linear-gradient(135deg, rgba(86, 202, 0, 0.12) 0%, rgba(43, 44, 64, 0.4) 100%);
  border-color: rgba(86, 202, 0, 0.45);
}
.scanner-video-box {
  width: 100%;
  max-width: 480px;
  min-height: 280px;
  margin: 0 auto;
  border-radius: 14px;
  overflow: hidden;
  background: #000000;
  position: relative;
}
.table thead th {
  font-size: 0.74rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  font-weight: 700;
}
</style>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
';

ob_start();
?>
<div class="container-fluid py-3 px-md-3">

  <!-- Flash Notification -->
  <?php if ($flashMsg): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm d-flex align-items-center gap-2 mb-3" role="alert">
      <i class="bi bi-check-circle-fill fs-5 text-success"></i>
      <div><?= e($flashMsg) ?></div>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if ($flashErr): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm d-flex align-items-center gap-2 mb-3" role="alert">
      <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
      <div><?= e($flashErr) ?></div>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- HERO CARD MATERIO: LOG RUANG SERVER -->
  <div class="card-hero-neo mb-4" style="background: linear-gradient(72.47deg, #312D4B 0%, #28243D 55%, #4C3C6E 100%) !important; box-shadow: 0 10px 30px -4px rgba(79, 70, 229, 0.35) !important; border: 1px solid rgba(255, 255, 255, 0.12);">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 position-relative" style="z-index: 2;">
      <div style="max-width: 680px;">
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
          <span class="badge bg-white text-primary px-2.5 py-1 font-monospace fw-bold shadow-xs" style="font-size: 0.70rem; letter-spacing: 0.05em; border-radius: 6px; color: #8C57FF !important;">
            <i class="bi bi-shield-lock-fill me-1"></i>KEAMANAN DATA CENTER &middot; POJK 75/POJK.03/2016
          </span>
          <span class="text-white text-opacity-75 small fw-semibold">Pencatatan Masuk Ruang Server Bank</span>
        </div>
        <h2 class="display-6 fw-bold mb-1 text-white" style="letter-spacing: -0.025em;">Log Akses Ruang Server &amp; Data Center</h2>
        <p class="text-white text-opacity-90 mb-3" style="font-size: 0.94rem; font-weight: 500;">
          Kontrol akses fisik dan pencatatan riwayat kunjungan teknisi IT, auditor SKAI, serta vendor eksternal menggunakan sistem barcode/QR code pintu otomatis.
        </p>

        <!-- Action Button Group -->
        <div class="d-flex flex-wrap align-items-center gap-2 pt-1">
          <button type="button" class="btn btn-light rounded-pill px-3.5 py-2 fw-bold d-inline-flex align-items-center gap-2 shadow-sm" style="color: #6827EB !important; font-size: 0.88rem;" data-bs-toggle="modal" data-bs-target="#modalCheckin">
            <i class="bi bi-door-open-fill text-primary"></i> <span>Check-In Masuk Ruang Server</span>
          </button>
          <button type="button" class="btn btn-outline-light rounded-pill px-3 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-xs" style="font-size: 0.88rem; background: rgba(255, 255, 255, 0.12); border-color: rgba(255, 255, 255, 0.28);" data-bs-toggle="modal" data-bs-target="#modalScanner" onclick="startScannerModal()">
            <i class="bi bi-qr-code-scan"></i> <span>Scanner Barcode Live</span>
          </button>
          <a href="<?= module_url('server_room_print_qr.php') ?>" target="_blank" class="btn btn-outline-light rounded-pill px-3 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-xs" style="font-size: 0.88rem; background: rgba(255, 255, 255, 0.12); border-color: rgba(255, 255, 255, 0.28);">
            <i class="bi bi-printer-fill"></i> <span>Cetak Stiker QR Pintu</span>
          </a>
          <a href="<?= module_url('server_room_log.php', array_filter(['action' => 'export_csv', 'search' => $search, 'status' => $statusFilter, 'cabang' => $cabangFilter])) ?>" class="btn btn-outline-light rounded-pill px-3 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-xs" style="font-size: 0.88rem; background: rgba(255, 255, 255, 0.12); border-color: rgba(255, 255, 255, 0.28);">
            <i class="bi bi-file-earmark-excel-fill"></i> <span>Ekspor CSV (Audit)</span>
          </a>
        </div>
      </div>

      <!-- Right Decorative Badge -->
      <div class="d-none d-lg-flex align-items-center gap-3 pe-2">
        <div class="p-3 text-center rounded-4 shadow-sm" style="background: rgba(255, 255, 255, 0.14); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.22); min-width: 140px;">
          <div class="d-inline-flex align-items-center justify-content-center mb-2" style="width: 50px; height: 50px; border-radius: 12px; background: rgba(255, 255, 255, 0.22); box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
            <i class="bi bi-server text-white fs-3"></i>
          </div>
          <div class="text-white fw-bold small mb-0 lh-sm">SERVER VAULT</div>
          <div class="text-white text-opacity-75" style="font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.05em;">CCTV 24 Jam</div>
        </div>
      </div>
    </div>
  </div>

  <!-- LIVE OCCUPANCY ALERT (Jika sedang ada orang di dalam ruang server) -->
  <?php if (!empty($liveOccupants)): ?>
    <div class="card live-occupant-card mb-4 p-3 shadow-xs">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2.5">
          <span class="door-badge-pulse"></span>
          <div>
            <h6 class="fw-bold mb-0 text-success d-flex align-items-center gap-2">
              <span><?= count($liveOccupants) ?> Orang Sedang Berada di Dalam Ruang Server</span>
            </h6>
            <div class="text-muted small">Pintu ruang server dalam status terbuka/pekerjaan sedang berlangsung.</div>
          </div>
        </div>
        <div class="text-end text-muted small">
          <i class="bi bi-clock-history me-1"></i>Pencatatan real-time otomatis
        </div>
      </div>

      <div class="row g-2 mt-2">
        <?php foreach ($liveOccupants as $occ): 
          $enterTs = strtotime($occ['entry_time']);
          $elapsedMin = max(1, (int)round((time() - $enterTs) / 60));
        ?>
          <div class="col-md-6 col-xl-4">
            <div class="bg-white rounded-3 p-2.5 border shadow-2xs d-flex align-items-center justify-content-between">
              <div class="d-flex align-items-center gap-2 overflow-hidden">
                <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px; min-width: 36px; font-size: 0.85rem;">
                  <?= strtoupper(substr($occ['visitor_name'], 0, 1)) ?>
                </div>
                <div class="overflow-hidden">
                  <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem;"><?= e($occ['visitor_name']) ?></div>
                  <div class="text-muted" style="font-size: 0.74rem;">
                    <?= e($occ['institution']) ?> &middot; Masuk <?= date('H:i', $enterTs) ?> (<?= $elapsedMin ?> mnt lalu)
                  </div>
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-danger rounded-pill px-3 py-1 fw-bold text-nowrap shadow-xs" onclick="openCheckoutModal(<?= (int)$occ['id'] ?>, <?= htmlspecialchars(json_encode($occ['visitor_name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($occ['log_code']), ENT_QUOTES, 'UTF-8') ?>)">
                <i class="bi bi-box-arrow-right me-1"></i>Check-Out
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- 4 SYMMETRICAL KPI METRIC CARDS -->
  <div class="row g-3 mb-4">
    <!-- Card 1: Live Occupants -->
    <div class="col-6 col-xl-3">
      <div class="card-metric-modern h-100">
        <div>
          <div class="metric-title-muted">Di Ruang Server Saat Ini</div>
          <div class="metric-number-bold d-flex align-items-center gap-2 text-<?= count($liveOccupants) > 0 ? 'success' : 'secondary' ?>">
            <span><?= count($liveOccupants) ?></span>
            <span class="fs-6 fw-normal text-muted">Orang</span>
          </div>
        </div>
        <div class="small text-muted mt-2 d-flex align-items-center gap-1.5" style="font-size: 0.76rem;">
          <span class="door-badge-pulse" style="background: <?= count($liveOccupants) > 0 ? '#10B981' : '#8A8D93' ?>;"></span>
          <span><?= count($liveOccupants) > 0 ? 'Sedang ada aktivitas di dalam' : 'Ruang server kosong / terkunci' ?></span>
        </div>
      </div>
    </div>

    <!-- Card 2: Total Kunjungan Bulan Ini -->
    <div class="col-6 col-xl-3">
      <div class="card-metric-modern h-100">
        <div>
          <div class="metric-title-muted">Total Kunjungan (Bulan Ini)</div>
          <div class="metric-number-bold"><?= number_format($kpiTotalMonth) ?> <span class="fs-6 fw-normal text-muted">Akses</span></div>
        </div>
        <div class="small text-muted mt-2 fw-semibold" style="font-size: 0.76rem;">
          <i class="bi bi-calendar-check text-primary me-1"></i> Periode <?= date('F Y') ?>
        </div>
      </div>
    </div>

    <!-- Card 3: Tamu / Vendor Eksternal -->
    <div class="col-6 col-xl-3">
      <div class="card-metric-modern h-100">
        <div>
          <div class="metric-title-muted">Vendor &amp; Tamu Eksternal</div>
          <div class="metric-number-bold text-warning"><?= number_format($kpiVendorMonth) ?> <span class="fs-6 fw-normal text-muted">Kunjungan</span></div>
        </div>
        <div class="small text-muted mt-2 fw-semibold" style="font-size: 0.76rem;">
          <i class="bi bi-shield-shaded text-warning me-1"></i> Rekanan ISP, AC &amp; Core Banking
        </div>
      </div>
    </div>

    <!-- Card 4: Suhu Rata-rata Server -->
    <div class="col-6 col-xl-3">
      <div class="card-metric-modern h-100">
        <div>
          <div class="metric-title-muted">Rata-Rata Suhu Ruangan</div>
          <div class="metric-number-bold text-info"><?= $avgTempFormatted ?></div>
        </div>
        <div class="small text-muted mt-2 fw-semibold" style="font-size: 0.76rem;">
          <i class="bi bi-snow text-info me-1"></i> Standar Operasional OJK (18&deg;C - 22&deg;C)
        </div>
      </div>
    </div>
  </div>

  <!-- TABLE & FILTER TOOLBAR -->
  <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-transparent py-3 px-3 px-md-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2" style="font-size: 1.05rem;">
          <i class="bi bi-journal-text text-primary"></i>
          <span>Buku Catatan Kunjungan Ruang Server</span>
        </h5>
        <div class="text-muted small">Menampilkan data akses fisik terbaru lengkap dengan barcode log resmi.</div>
      </div>

      <!-- Filter Controls -->
      <form method="get" class="d-flex align-items-center gap-2 flex-wrap">
        <div class="input-group input-group-sm" style="max-width: 200px;">
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

    <!-- Table Body -->
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
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
                  <div class="fw-bold font-monospace text-primary small"><?= e($l['log_code']) ?></div>
                  <div class="text-muted" style="font-size: 0.74rem;">
                    <?= date('d/m/Y', $enterTs) ?> &middot; <strong><?= date('H:i', $enterTs) ?></strong>
                  </div>
                </td>
                <td>
                  <?php if ($exitTs): ?>
                    <div class="fw-semibold text-dark small"><?= date('H:i', $exitTs) ?></div>
                    <div class="badge bg-secondary-subtle text-secondary rounded-pill px-2" style="font-size: 0.70rem;">
                      <i class="bi bi-stopwatch me-1"></i><?= (int)$l['duration_minutes'] ?> mnt
                    </div>
                  <?php else: ?>
                    <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">
                      <span class="door-badge-pulse me-1"></span>Masih di dalam
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="fw-bold text-dark d-flex align-items-center gap-1.5">
                    <?php if (!empty($l['photo_in'])): ?>
                      <img src="<?= e($l['photo_in']) ?>" class="rounded-circle object-fit-cover border" style="width: 24px; height: 24px; cursor: pointer;" onclick="previewPhoto(<?= htmlspecialchars(json_encode($l['photo_in']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($l['visitor_name']), ENT_QUOTES, 'UTF-8') ?>)" title="Klik untuk lihat foto masuk">
                    <?php endif; ?>
                    <span><?= e($l['visitor_name']) ?></span>
                  </div>
                  <div class="text-muted" style="font-size: 0.75rem;">
                    <?php if ($l['visitor_type'] === 'vendor_external'): ?>
                      <span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size: 0.68rem;">Vendor</span>
                    <?php elseif ($l['visitor_type'] === 'auditor'): ?>
                      <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size: 0.68rem;">Auditor</span>
                    <?php else: ?>
                      <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.68rem;">Internal IT</span>
                    <?php endif; ?>
                    <span class="ms-1"><?= e($l['institution']) ?></span>
                    <?php if (!empty($l['contact_number'])): ?>
                      &middot; <i class="bi bi-telephone ms-0.5"></i> <?= e($l['contact_number']) ?>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="fw-semibold text-dark small"><?= e($l['purpose']) ?></div>
                  <?php if (!empty($l['purpose_detail'])): ?>
                    <div class="text-muted" style="font-size: 0.74rem;"><?= e(mb_strimwidth($l['purpose_detail'], 0, 60, '...')) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($l['tools_brought'])): ?>
                    <div class="text-secondary" style="font-size: 0.72rem;"><i class="bi bi-tools me-1"></i>Bawa: <?= e($l['tools_brought']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="small fw-medium text-dark"><?= e($l['accompanied_by'] ?: '-') ?></div>
                  <div class="text-muted" style="font-size: 0.72rem;"><?= e($l['cabang']) ?></div>
                </td>
                <td class="text-center font-monospace small">
                  <?php if (!empty($l['temperature_entry'])): ?>
                    <div class="fw-bold text-info"><?= $l['temperature_entry'] ?>&deg;C</div>
                    <?php if (!empty($l['temperature_exit'])): ?>
                      <div class="text-muted xs">&darr; <?= $l['temperature_exit'] ?>&deg;C</div>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <?php if ($isInside): ?>
                    <span class="badge bg-success rounded-pill px-2.5 py-1 fw-bold shadow-xs">
                      <span class="door-badge-pulse me-1"></span>DI DALAM
                    </span>
                  <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2.5 py-1 fw-semibold">
                      SELESAI
                    </span>
                  <?php endif; ?>
                </td>
                <td class="text-end pe-3">
                  <?php if ($isInside): ?>
                    <button type="button" class="btn btn-sm btn-danger rounded-pill px-2.5 py-1 shadow-xs fw-semibold" onclick="openCheckoutModal(<?= (int)$l['id'] ?>, <?= htmlspecialchars(json_encode($l['visitor_name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($l['log_code']), ENT_QUOTES, 'UTF-8') ?>)" title="Check-Out Keluar">
                      <i class="bi bi-box-arrow-right me-1"></i>Keluar
                    </button>
                  <?php else: ?>
                    <?php if ($isAdmin): ?>
                      <form method="post" class="d-inline" onsubmit="return confirm('Hapus riwayat log kunjungan ini?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger p-1 px-2 rounded-pill" title="Hapus Log"><i class="bi bi-trash3"></i></button>
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
    <div class="modal-content border-0 shadow-lg rounded-4">
      <form method="post" id="formCheckin">
        <input type="hidden" name="action" value="checkin">
        <input type="hidden" name="photo_in" id="inputPhotoIn">

        <div class="modal-header bg-primary text-white py-3 px-4" style="background: linear-gradient(72.47deg, #8C57FF 0%, #6827EB 100%) !important;">
          <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="modalCheckinLabel">
            <i class="bi bi-door-open-fill"></i> Check-In Masuk Ruang Server
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body p-4">
          <!-- Pilihan Tipe Pengunjung -->
          <div class="mb-3">
            <label class="form-label fw-bold small text-secondary">TIPE PENGUNJUNG / PETUGAS</label>
            <div class="row g-2">
              <div class="col-sm-3 col-6">
                <input type="radio" class="btn-check" name="visitor_type" id="typeIt" value="internal_it" checked onchange="onVisitorTypeChange('internal_it')">
                <label class="btn btn-outline-primary w-100 py-2 text-center rounded-3 fw-bold small" for="typeIt">
                  <i class="bi bi-pc-display d-block mb-1 fs-5"></i>Internal IT
                </label>
              </div>
              <div class="col-sm-3 col-6">
                <input type="radio" class="btn-check" name="visitor_type" id="typeStaff" value="internal_staff" onchange="onVisitorTypeChange('internal_staff')">
                <label class="btn btn-outline-primary w-100 py-2 text-center rounded-3 fw-bold small" for="typeStaff">
                  <i class="bi bi-person-badge d-block mb-1 fs-5"></i>Staf Bank
                </label>
              </div>
              <div class="col-sm-3 col-6">
                <input type="radio" class="btn-check" name="visitor_type" id="typeVendor" value="vendor_external" onchange="onVisitorTypeChange('vendor_external')">
                <label class="btn btn-outline-primary w-100 py-2 text-center rounded-3 fw-bold small" for="typeVendor">
                  <i class="bi bi-building d-block mb-1 fs-5"></i>Vendor Luar
                </label>
              </div>
              <div class="col-sm-3 col-6">
                <input type="radio" class="btn-check" name="visitor_type" id="typeAuditor" value="auditor" onchange="onVisitorTypeChange('auditor')">
                <label class="btn btn-outline-primary w-100 py-2 text-center rounded-3 fw-bold small" for="typeAuditor">
                  <i class="bi bi-shield-check d-block mb-1 fs-5"></i>Auditor SKAI
                </label>
              </div>
            </div>
          </div>

          <!-- Identitas Petugas / Pengunjung -->
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Nama Lengkap Pengunjung / Petugas <span class="text-danger">*</span></label>
              <input type="text" name="visitor_name" id="checkinVisitorName" class="form-control" required value="<?= e($userName) ?>" placeholder="Contoh: Akhmad Hafizh Firmansyah">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Instansi / Perusahaan Vendor</label>
              <input type="text" name="institution" id="checkinInstitution" class="form-control" value="PT BPR Mitratama Arthabuana" placeholder="Contoh: PT Telkom / Lintasarta">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small">No. HP / Kontak Pengunjung</label>
              <input type="text" name="contact_number" class="form-control" placeholder="Contoh: 0812-XXXX-XXXX">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Petugas Pendamping IT Bank</label>
              <input type="text" name="accompanied_by" id="checkinAccompaniedBy" class="form-control" value="Mandiri (Teknisi IT Bank)" placeholder="Nama staf IT yang mendampingi">
            </div>
          </div>

          <!-- Keperluan Kunjungan -->
          <div class="mb-3">
            <label class="form-label fw-semibold small">Keperluan / Alasan Masuk Ruang Server <span class="text-danger">*</span></label>
            <select name="purpose" id="checkinPurpose" class="form-select mb-2" required>
              <option value="Pemeliharaan Rutin Server & Jaringan">🛠️ Pemeliharaan Rutin Server & Jaringan</option>
              <option value="Troubleshooting / Perbaikan Gangguan Core Banking">⚡ Troubleshooting / Perbaikan Gangguan Core Banking</option>
              <option value="Backup Data Offline / Penarikan Pita Tape">💾 Backup Data Offline & Penarikan Harddisk/Tape</option>
              <option value="Pemeriksaan & Servis AC Presisi Ruang Server">❄️ Pemeriksaan & Servis AC Presisi Ruang Server</option>
              <option value="Uji Beban Genset & Pengecekan Aki UPS">🔋 Uji Beban Genset & Pengecekan Aki UPS</option>
              <option value="Inspeksi Pengawasan Audit SKAI / OJK">🔍 Inspeksi Pengawasan Audit SKAI / OJK</option>
              <option value="Kunjungan Vendor ISP / Perbaikan Kabel Fiber Optic">🌐 Kunjungan Vendor ISP / Fiber Optic</option>
              <option value="Instalasi / Penambahan Perangkat Baru">📦 Instalasi / Penambahan Perangkat Rak Server Baru</option>
              <option value="Lainnya">Lainnya...</option>
            </select>
            <textarea name="purpose_detail" class="form-control form-control-sm" rows="2" placeholder="Rincian pekerjaan yang akan dilakukan di dalam ruang server (opsional)..."></textarea>
          </div>

          <!-- Parameter Lingkungan & Alat Bawaan -->
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold small">Suhu Ruangan Saat Masuk (°C)</label>
              <div class="input-group">
                <input type="number" step="0.1" name="temperature_entry" class="form-control" placeholder="20.0" value="20.0">
                <span class="input-group-text">&deg;C</span>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold small">Lokasi Kantor Cabang</label>
              <select name="cabang" class="form-select">
                <option value="Kantor Pusat (KPO)">Kantor Pusat (KPO)</option>
                <?php foreach ($cabangMaster as $cb): 
                  $cName = $cb['nama_cabang'] ?? '';
                  if ($cName === '' || $cName === 'Kantor Pusat (KPO)') continue;
                ?>
                  <option value="<?= e($cName) ?>"><?= e($cName) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold small">Peralatan yang Dibawa Masuk</label>
              <input type="text" name="tools_brought" class="form-control" placeholder="Laptop teknisi, Toolkit, LAN tester">
            </div>
          </div>

          <!-- Capture Foto Wajah / Kamera Selfie (Opsional) -->
          <div class="border rounded-3 p-3 bg-light">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="fw-bold small text-dark"><i class="bi bi-camera-fill me-1 text-primary"></i> Ambil Foto Wajah Pengunjung (Opsional)</span>
              <button type="button" class="btn btn-xs btn-outline-primary" id="btnToggleCam" onclick="toggleWebcamCheckin()">
                <i class="bi bi-camera me-1"></i> Buka Kamera
              </button>
            </div>
            <div id="camContainer" class="d-none text-center">
              <video id="webcamVideo" autoplay playsinline style="width: 100%; max-width: 320px; border-radius: 10px; background: #000;"></video>
              <canvas id="webcamCanvas" class="d-none"></canvas>
              <div class="mt-2">
                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" onclick="snapPhotoCheckin()">
                  <i class="bi bi-camera-fill me-1"></i> Jepret Foto
                </button>
              </div>
            </div>
            <div id="previewSnapBox" class="d-none text-center mt-2">
              <img id="imgSnapPreview" src="" class="rounded-3 border shadow-xs" style="max-width: 140px; height: auto;">
              <div class="text-success small fw-bold mt-1"><i class="bi bi-check-circle-fill me-1"></i>Foto berhasil diambil</div>
            </div>
          </div>
        </div>

        <div class="modal-footer bg-light py-3 px-4">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4" style="background: #8C57FF !important; border-color: #8C57FF !important;">
            <i class="bi bi-door-open-fill me-1"></i> Konfirmasi Masuk (Check-In)
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
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header bg-dark text-white py-3 px-4">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="modalScannerLabel">
          <i class="bi bi-qr-code-scan text-warning"></i> Scanner Barcode &amp; QR Pintu Server
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="stopScannerModal()"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <p class="text-muted small mb-3">
          Arahkan kamera ke <strong>Stiker Barcode / QR Code Pintu Ruang Server</strong> atau scan Barcode ID Card Petugas:
        </p>
        <div id="scannerReader" class="scanner-video-box mb-3"></div>
        <div id="scannerStatusText" class="small fw-semibold text-primary">
          <i class="bi bi-camera me-1"></i> Menyiapkan kamera scanner...
        </div>

        <div class="hr-text text-muted my-3 small">&mdash; ATAU KETIK KODE MANUAL &mdash;</div>
        <div class="input-group">
          <input type="text" id="manualBarcodeInput" class="form-control" placeholder="Ketik nomor log / ID petugas..." onkeydown="if(event.key==='Enter') processManualBarcode()">
          <button class="btn btn-primary fw-bold" type="button" onclick="processManualBarcode()">Proses</button>
        </div>
      </div>
      <div class="modal-footer bg-light py-2 px-3 justify-content-between">
        <span class="text-muted xs"><i class="bi bi-shield-check me-1"></i>Deteksi otomatis</span>
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal" onclick="stopScannerModal()">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- MODAL 3: CHECK-OUT KELUAR RUANG SERVER     -->
<!-- ========================================== -->
<div class="modal fade" id="modalCheckout" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <form method="post">
        <input type="hidden" name="action" value="checkout">
        <input type="hidden" name="id" id="checkoutLogId">

        <div class="modal-header bg-danger text-white py-3 px-4">
          <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
            <i class="bi bi-box-arrow-right"></i> Check-Out Keluar Ruang Server
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body p-4">
          <div class="alert alert-info border-0 shadow-xs mb-3">
            <div class="fw-bold fs-6" id="checkoutVisitorName">Nama Pengunjung</div>
            <div class="small text-muted" id="checkoutLogCode">Kode Log</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold small">Suhu Ruangan Saat Keluar (°C)</label>
            <div class="input-group">
              <input type="number" step="0.1" name="temperature_exit" class="form-control" placeholder="20.0" value="20.0">
              <span class="input-group-text">&deg;C</span>
            </div>
            <div class="form-text xs text-muted">Pastikan AC ruang server tetap dingin dan stabil saat pintu ditutup.</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold small">Catatan Hasil Pekerjaan / Kondisi Server</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Contoh: Pekerjaan selesai, AC dingin normal, kabel LAN rapi, pintu dikunci kembali..."></textarea>
          </div>
        </div>

        <div class="modal-footer bg-light py-3 px-4">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger fw-bold rounded-pill px-4 shadow-sm">
            <i class="bi bi-box-arrow-right me-1"></i> Konfirmasi Keluar (Check-Out)
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- MODAL 4: PREVIEW FOTO PENGUNJUNG           -->
<!-- ========================================== -->
<div class="modal fade" id="modalPreviewPhoto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-dark text-white py-2 px-3">
        <h6 class="modal-title fw-bold small" id="previewTitle">Foto Pengunjung</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0 text-center bg-black">
        <img id="imgBigPreview" src="" class="img-fluid" style="max-height: 80vh; width: auto;">
      </div>
    </div>
  </div>
</div>

<?php
$body = ob_get_clean();

$extraScript = '
<script>
function onVisitorTypeChange(type) {
  const inst = document.getElementById("checkinInstitution");
  const acc = document.getElementById("checkinAccompaniedBy");
  if (type === "vendor_external") {
    if (inst.value === "PT BPR Mitratama Arthabuana") inst.value = "";
    inst.placeholder = "Nama Perusahaan (misal: PT Telkom / Lintasarta / Vendor AC)";
    if (acc.value === "Mandiri (Teknisi IT Bank)") acc.value = "";
    acc.placeholder = "Nama Petugas IT Bank yang mendampingi";
  } else if (type === "auditor") {
    inst.value = "SKAI / OJK";
  } else {
    inst.value = "PT BPR Mitratama Arthabuana";
    acc.value = "Mandiri (Teknisi IT Bank)";
  }
}

function openCheckoutModal(id, name, code) {
  document.getElementById("checkoutLogId").value = id;
  document.getElementById("checkoutVisitorName").textContent = name;
  document.getElementById("checkoutLogCode").textContent = "Kode Log: " + code;
  const m = new bootstrap.Modal(document.getElementById("modalCheckout"));
  m.show();
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
  if (container.classList.contains("d-none")) {
    container.classList.remove("d-none");
    btn.innerHTML = \'<i class="bi bi-camera-video-off me-1"></i> Tutup Kamera\';
    navigator.mediaDevices.getUserMedia({ video: { facingMode: "user", width: { ideal: 640 }, height: { ideal: 480 } } })
      .then(function(stream) {
        webcamStream = stream;
        const v = document.getElementById("webcamVideo");
        v.srcObject = stream;
      })
      .catch(function(err) {
        alert("Tidak dapat mengakses kamera: " + err.message);
      });
  } else {
    stopWebcamCheckin();
  }
}

function stopWebcamCheckin() {
  if (webcamStream) {
    webcamStream.getTracks().forEach(t => t.stop());
    webcamStream = null;
  }
  const container = document.getElementById("camContainer");
  if (container) container.classList.add("d-none");
  const btn = document.getElementById("btnToggleCam");
  if (btn) btn.innerHTML = \'<i class="bi bi-camera me-1"></i> Buka Kamera\';
}

function snapPhotoCheckin() {
  const v = document.getElementById("webcamVideo");
  const c = document.getElementById("webcamCanvas");
  if (!v || !c) return;
  c.width = 480;
  c.height = 360;
  const ctx = c.getContext("2d");
  ctx.drawImage(v, 0, 0, 480, 360);
  const dataUrl = c.toDataURL("image/jpeg", 0.75);
  document.getElementById("inputPhotoIn").value = dataUrl;
  document.getElementById("imgSnapPreview").src = dataUrl;
  document.getElementById("previewSnapBox").classList.remove("d-none");
  stopWebcamCheckin();
}

// Barcode & QR Scanner Modal Logic
let html5QrScanner = null;
function startScannerModal() {
  const statusEl = document.getElementById("scannerStatusText");
  statusEl.innerHTML = \'<i class="bi bi-camera me-1"></i> Mengaktifkan kamera scanner...\';
  
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
        statusEl.innerHTML = \'<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Kamera aktif &mdash; Arahkan ke Barcode / QR Pintu</span>\';
      }).catch(function(err) {
        statusEl.innerHTML = \'<span class="text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Gagal membuka kamera: \' + err + \'</span>\';
      });
    } catch(e) {
      statusEl.innerHTML = \'<span class="text-danger">Scanner error: \' + e.message + \'</span>\';
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

function onQrCodeDetected(text) {
  stopScannerModal();
  const scanModalEl = document.getElementById("modalScanner");
  const scanModal = bootstrap.Modal.getInstance(scanModalEl);
  if (scanModal) scanModal.hide();

  // Jika QR adalah URL atau scan pintu
  if (text.includes("server_room") || text.includes("scan") || text.includes("door")) {
    const checkinModal = new bootstrap.Modal(document.getElementById("modalCheckin"));
    checkinModal.show();
  } else {
    // Cari apakah ini kode log yang sedang di dalam
    document.getElementById("manualBarcodeInput").value = text;
    processManualBarcode(text);
  }
}

function processManualBarcode(overrideText) {
  const code = overrideText || document.getElementById("manualBarcodeInput").value.trim();
  if (!code) return;
  // Buka checkin modal dan masukkan code jika perlu
  const checkinModal = new bootstrap.Modal(document.getElementById("modalCheckin"));
  checkinModal.show();
}

document.addEventListener("DOMContentLoaded", function() {
  // Jika parameter URL action=scan (misal dari scan stiker pintu)
  '.($autoOpenScanModal ? '
    setTimeout(function() {
      const checkinModal = new bootstrap.Modal(document.getElementById("modalCheckin"));
      checkinModal.show();
    }, 300);
  ' : '').'
});
</script>
';

render_page('Log Masuk Ruang Server & Data Center', $body, $head, $extraScript);
