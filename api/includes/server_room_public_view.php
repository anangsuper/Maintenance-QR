<?php
/**
 * Tampilan Khusus Publik: Portal Akses & Buku Tamu Digital Ruang Server (Tanpa Login)
 * Dirancang khusus responsif untuk smartphone (scan QR pintu) dan tablet/desktop.
 * Kepatuhan OJK POJK No. 75/POJK.03/2016 & ISO/IEC 27001
 */

// Safe default fallbacks
$cabang = $cabang ?? (!empty($cabangFilter) && $cabangFilter !== 'all' ? $cabangFilter : 'Kantor Pusat - Ruang Server Utama (Lt. 2)');
$ruangan = $ruangan ?? 'Data Center & Server Room Bank Mitra';
$liveOccupants = $liveOccupants ?? [];
$logs = $logs ?? [];
$statActive = $statActive ?? count($liveOccupants);
$statToday = $statToday ?? 0;
$statMonth = $statMonth ?? 0;
$statAvgDuration = $statAvgDuration ?? 0;
$flashMsg = $flashMsg ?? null;
$flashErr = $flashErr ?? null;
$search = $search ?? '';
$statusFilter = $statusFilter ?? 'all';
$cabangFilter = $cabangFilter ?? 'all';
$cabangMaster = $cabangMaster ?? (function_exists('get_cabang_list') ? get_cabang_list() : []);
$autoOpenCheckin = !empty($autoOpenCheckin);
$autoOpenCheckout = !empty($autoOpenCheckout);
$autoViewLogs = !empty($autoViewLogs);
$autoCheckoutMatch = $autoCheckoutMatch ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Portal Akses &amp; Log Ruang Server &middot; Bank Mitra</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <meta name="theme-color" content="#28243D">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="icon" type="image/png" href="<?= app_logo_url() ?>">

  <!-- Bootstrap 5, Icons, Fonts -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/html5-qrcode"></script>

  <style>
    :root {
      --primary: #8C57FF;
      --primary-dark: #6827EB;
      --dark-card: #28243D;
      --success: #10B981;
      --danger: #EF4444;
      --warning: #F59E0B;
    }
    body {
      font-family: "Public Sans", sans-serif;
      background: #F4F5FA;
      color: #2E263D;
      margin: 0;
      padding: 0;
      -webkit-font-smoothing: antialiased;
    }
    .public-portal-header {
      background: rgba(255, 255, 255, 0.94);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid rgba(76, 78, 100, 0.12);
      position: sticky;
      top: 0;
      z-index: 1020;
    }
    .portal-hero-card {
      background: linear-gradient(135deg, #28243D 0%, #3B3258 100%);
      color: #FFFFFF;
      border-radius: 20px;
      padding: 24px 20px;
      box-shadow: 0 10px 30px rgba(40, 36, 61, 0.25);
      position: relative;
      overflow: hidden;
    }
    .portal-hero-card::after {
      content: "";
      position: absolute;
      top: -30px;
      right: -30px;
      width: 140px;
      height: 140px;
      background: radial-gradient(circle, rgba(140, 87, 255, 0.3) 0%, transparent 70%);
      border-radius: 50%;
    }
    .btn-action-hero {
      border-radius: 16px;
      padding: 16px 14px;
      text-align: center;
      transition: all 0.2s ease;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 4px;
      height: 100%;
      border: none;
    }
    .btn-action-hero:active {
      transform: scale(0.97);
    }
    .btn-hero-checkin {
      background: linear-gradient(135deg, #10B981 0%, #059669 100%);
      color: #FFFFFF !important;
      box-shadow: 0 6px 18px rgba(16, 185, 129, 0.35);
    }
    .btn-hero-checkout {
      background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
      color: #FFFFFF !important;
      box-shadow: 0 6px 18px rgba(239, 68, 68, 0.35);
    }
    .door-badge-pulse {
      display: inline-block;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: #10B981;
      animation: pulse-ring 1.8s infinite;
    }
    @keyframes pulse-ring {
      0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
      70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
      100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
    .door-badge-pulse-danger {
      background: #EF4444 !important;
      animation: pulse-danger 1.8s infinite !important;
    }
    @keyframes pulse-danger {
      0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
      70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
      100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }
    .metric-pill {
      background: #FFFFFF;
      border: 1px solid rgba(76, 78, 100, 0.08);
      border-radius: 14px;
      padding: 12px 14px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.03);
      height: 100%;
    }
    .log-card-item {
      background: #FFFFFF;
      border: 1px solid rgba(76, 78, 100, 0.12);
      border-radius: 16px;
      padding: 16px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.04);
      transition: all 0.2s ease;
    }
    .log-card-item.in-room {
      background: #F0FDF4;
      border: 2px solid #86EFAC !important;
    }
    .badge-code {
      font-family: "JetBrains Mono", monospace;
      font-weight: 700;
      letter-spacing: 0.04em;
    }
    .scanner-box {
      width: 100%;
      max-width: 440px;
      min-height: 260px;
      margin: 0 auto;
      border-radius: 14px;
      overflow: hidden;
      background: #000;
    }
    .modal-content {
      border-radius: 20px !important;
      border: none !important;
    }
  </style>
</head>
<body>

  <!-- ========================================== -->
  <!-- 1. TOP BAR RESMI BANK MITRA                -->
  <!-- ========================================== -->
  <header class="public-portal-header py-2 px-3 px-md-4">
    <div class="container-fluid max-container d-flex align-items-center justify-content-between" style="max-width: 900px; margin: 0 auto;">
      <div class="d-flex align-items-center gap-2.5">
        <img src="<?= app_logo_url() ?>" alt="Bank Mitra Logo" style="height: 38px; width: auto; object-fit: contain;">
        <div class="d-none d-sm-block border-start ps-2.5">
          <div class="fw-bold text-dark" style="font-size: 0.85rem; letter-spacing: -0.01em;">PT BPR MITRATAMA ARTHABUANA</div>
          <div class="text-muted" style="font-size: 0.70rem;">Divisi TI &amp; Keamanan Data Center</div>
        </div>
      </div>

      <div class="d-flex align-items-center gap-2">
        <span class="badge d-none d-md-inline-block text-uppercase fw-bold px-2.5 py-1.5" style="background: rgba(140, 87, 255, 0.12); color: #8C57FF; font-size: 0.70rem;">
          <i class="bi bi-shield-lock-fill me-1"></i> OJK POJK No. 75
        </span>
        <a href="<?= module_url('login.php') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold" style="font-size: 0.78rem;">
          <i class="bi bi-person-fill me-1"></i> Login Staf
        </a>
      </div>
    </div>
  </header>

  <!-- ========================================== -->
  <!-- 2. KONTEN UTAMA PORTAL AKSES               -->
  <!-- ========================================== -->
  <main class="py-3 px-3 px-md-4">
    <div class="container-fluid" style="max-width: 900px; margin: 0 auto;">

      <!-- Flash Message Notifikasi -->
      <?php if (!empty($flashMsg)): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 alert-dismissible fade show p-3 mb-3 d-flex align-items-center gap-2" role="alert">
          <i class="bi bi-check-circle-fill fs-5 text-success"></i>
          <div><?= e($flashMsg) ?></div>
          <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      <?php if (!empty($flashErr)): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 alert-dismissible fade show p-3 mb-3 d-flex align-items-center gap-2" role="alert">
          <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
          <div><?= e($flashErr) ?></div>
          <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- HERO: STATUS PINTU & DUA TOMBOL AKSI UTAMA -->
      <div class="portal-hero-card mb-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="badge bg-white bg-opacity-20 text-white rounded-pill px-2.5 py-1 small fw-semibold" style="font-size: 0.72rem;">
            <i class="bi bi-geo-alt-fill text-warning me-1"></i> <?= e($cabang) ?>
          </span>
          <div id="livePortalClock" class="font-monospace small text-white-50" style="font-size: 0.76rem;"></div>
        </div>

        <h1 class="h5 fw-bold mb-1 text-white" style="letter-spacing: -0.02em;">
          <?= e($ruangan) ?>
        </h1>
        <p class="small text-white-50 mb-3" style="font-size: 0.80rem;">
          Portal akses buku tamu digital ruang server. Siapa pun yang masuk wajib mencatatkan kehadiran secara mandiri.
        </p>

        <!-- Status Indikator Ruangan -->
        <div class="mb-3">
          <?php if (!empty($liveOccupants)): ?>
            <span class="badge bg-danger text-white rounded-pill px-3 py-1.5 shadow-sm fw-bold" style="font-size: 0.78rem;">
              <span class="door-badge-pulse door-badge-pulse-danger me-1.5"></span> SEDANG DIGUNAKAN (<?= count($liveOccupants) ?> Orang di Dalam)
            </span>
          <?php else: ?>
            <span class="badge rounded-pill px-3 py-1.5 shadow-sm fw-bold" style="background: rgba(16, 185, 129, 0.25); color: #34D399; border: 1px solid rgba(16, 185, 129, 0.4); font-size: 0.78rem;">
              <span class="door-badge-pulse me-1.5"></span> RUANGAN KOSONG &amp; TERKUNCI NORMAL
            </span>
          <?php endif; ?>
        </div>

        <!-- DUA TOMBOL AKSI UTAMA BESAR (TOUCH-FRIENDLY) -->
        <div class="row g-2 mb-2">
          <div class="col-6">
            <button type="button" class="btn btn-action-hero btn-hero-checkin w-100" data-bs-toggle="modal" data-bs-target="#modalCheckin">
              <i class="bi bi-door-open-fill fs-2"></i>
              <span class="fw-bold fs-6">CHECK-IN MASUK</span>
              <small class="text-white-50" style="font-size: 0.70rem;">Izin Masuk &amp; Foto Selfie</small>
            </button>
          </div>
          <div class="col-6">
            <button type="button" class="btn btn-action-hero btn-hero-checkout w-100" onclick="handlePublicCheckout()">
              <i class="bi bi-box-arrow-right fs-2"></i>
              <span class="fw-bold fs-6">CHECK-OUT KELUAR</span>
              <small class="text-white-50" style="font-size: 0.70rem;">Selesai Kerja &amp; Catat Suhu</small>
            </button>
          </div>
        </div>

        <!-- Tombol Tambahan: Scanner Kamera & Cetak Stiker -->
        <div class="d-flex align-items-center justify-content-between pt-2 border-top border-white border-opacity-10">
          <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1.5" style="font-size: 0.74rem; background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.25);" data-bs-toggle="modal" data-bs-target="#modalScanner" onclick="startScannerModal()">
            <i class="bi bi-qr-code-scan"></i> <span>Scan Barcode Live</span>
          </button>
          <a href="<?= module_url('server_room_print_qr.php') ?>" target="_blank" class="btn btn-sm btn-link text-white-50 text-decoration-none fw-semibold" style="font-size: 0.74rem;">
            <i class="bi bi-printer me-1"></i> Cetak Stiker Pintu A4
          </a>
        </div>
      </div>

      <!-- SPOTLIGHT: ORANG YANG SEDANG DI DALAM (LIVE OCCUPANTS) -->
      <?php if (!empty($liveOccupants)): ?>
        <div class="alert border-0 rounded-4 shadow-sm mb-3 p-3" style="background: #FFFBEB; border-left: 4px solid #F59E0B !important;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="fw-bold text-dark d-flex align-items-center gap-2">
              <span class="door-badge-pulse door-badge-pulse-danger"></span>
              <span>Sedang Berada di Dalam Ruang Server (<?= count($liveOccupants) ?>):</span>
            </div>
            <span class="badge bg-warning text-dark fw-bold rounded-pill" style="font-size: 0.68rem;">LIVE</span>
          </div>

          <div class="d-flex flex-column gap-2">
            <?php foreach ($liveOccupants as $occ): 
              $durMin = max(0, round((time() - strtotime($occ['entry_time'])) / 60));
            ?>
              <div class="bg-white rounded-3 p-2.5 shadow-xs border d-flex align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2 overflow-hidden">
                  <?php if (!empty($occ['photo_in'])): ?>
                    <img src="<?= e($occ['photo_in']) ?>" class="rounded-circle object-fit-cover border" style="width: 32px; height: 32px; min-width: 32px; cursor: pointer;" onclick="previewPhoto(<?= htmlspecialchars(json_encode($occ['photo_in']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($occ['visitor_name']), ENT_QUOTES, 'UTF-8') ?>)">
                  <?php else: ?>
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-secondary border" style="width: 32px; height: 32px; min-width: 32px;">
                      <i class="bi bi-person-fill"></i>
                    </div>
                  <?php endif; ?>
                  <div class="overflow-hidden">
                    <div class="fw-bold text-dark text-truncate" style="font-size: 0.85rem;"><?= e($occ['visitor_name']) ?></div>
                    <div class="small text-muted text-truncate" style="font-size: 0.72rem;"><?= e($occ['institution'] ?: 'PT BPR Mitratama') ?> &bull; Masuk jam <?= date('H:i', strtotime($occ['entry_time'])) ?> (<?= $durMin ?> mnt lalu)</div>
                  </div>
                </div>
                <button type="button" class="btn btn-sm btn-danger rounded-pill px-3 py-1 fw-bold text-nowrap shadow-xs" style="font-size: 0.76rem;" onclick="openCheckoutModal(<?= (int)$occ['id'] ?>, <?= htmlspecialchars(json_encode($occ['visitor_name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($occ['log_code']), ENT_QUOTES, 'UTF-8') ?>)">
                  <i class="bi bi-box-arrow-right me-1"></i> Keluar
                </button>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- 3 METRIK RINGKAS KONDISI RUANG SERVER -->
      <div class="row g-2 mb-3">
        <div class="col-4">
          <div class="metric-pill text-center">
            <div class="text-muted fw-semibold" style="font-size: 0.70rem;">SUHU SERVER</div>
            <div class="fw-bold fs-6 text-info mt-0.5"><?= $avgTempFormatted ?></div>
            <div class="text-muted" style="font-size: 0.64rem;">OJK: 18&deg;C-22&deg;C</div>
          </div>
        </div>
        <div class="col-4">
          <div class="metric-pill text-center">
            <div class="text-muted fw-semibold" style="font-size: 0.70rem;">DI DALAM</div>
            <div class="fw-bold fs-6 <?= !empty($liveOccupants) ? 'text-danger' : 'text-success' ?> mt-0.5"><?= count($liveOccupants) ?> Orang</div>
            <div class="text-muted" style="font-size: 0.64rem;">Akses Aktif</div>
          </div>
        </div>
        <div class="col-4">
          <div class="metric-pill text-center">
            <div class="text-muted fw-semibold" style="font-size: 0.70rem;">TOTAL BULAN INI</div>
            <div class="fw-bold fs-6 text-primary mt-0.5"><?= number_format($kpiTotalMonth) ?></div>
            <div class="text-muted" style="font-size: 0.64rem;">Riwayat Akses</div>
          </div>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- 3. BUKU CATATAN LOG KELUAR-MASUK (CARDS)   -->
      <!-- ========================================== -->
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4" id="riwayatLogTable">
        <div class="card-header bg-white py-3 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom">
          <div>
            <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-1.5">
              <i class="bi bi-journal-text text-primary"></i>
              <span>Riwayat Log Keluar &amp; Masuk</span>
            </div>
            <div class="text-muted" style="font-size: 0.74rem;">Catatan akses fisik ruang server &amp; data center</div>
          </div>

          <!-- Filter Pencarian Cepat -->
          <form method="get" class="d-flex align-items-center gap-1.5 w-100 mt-1 mt-sm-0 w-sm-auto">
            <input type="hidden" name="action" value="view_logs">
            <div class="input-group input-group-sm">
              <input type="text" name="search" class="form-control" placeholder="Cari nama / kode..." value="<?= e($search) ?>" style="font-size: 0.78rem;">
              <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
            </div>
            <?php if ($search): ?>
              <a href="<?= module_url('server_room_log.php', ['action' => 'view_logs']) ?>" class="btn btn-sm btn-outline-secondary" title="Reset"><i class="bi bi-x"></i></a>
            <?php endif; ?>
          </form>
        </div>

        <div class="card-body p-3">
          <?php if (empty($logs)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-door-closed display-5 d-block mb-2 text-secondary opacity-50"></i>
              <div class="fw-semibold">Belum ada catatan log kunjungan.</div>
              <div class="small text-muted mt-1">Tekan tombol <strong>Check-In Masuk</strong> di atas untuk mencatatkan kedatangan baru.</div>
            </div>
          <?php else: ?>
            <div class="d-flex flex-column gap-2.5">
              <?php foreach ($logs as $l): 
                $isInside = ($l['status'] === 'in_room');
                $enterTs = strtotime($l['entry_time']);
                $exitTs = !empty($l['exit_time']) ? strtotime($l['exit_time']) : null;
              ?>
                <div class="log-card-item <?= $isInside ? 'in-room' : '' ?>">
                  <!-- Baris Atas: Kode Log & Status -->
                  <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge badge-code <?= $isInside ? 'bg-success' : 'bg-primary' ?> text-white px-2 py-1" style="font-size: 0.70rem;">
                      <?= e($l['log_code']) ?>
                    </span>
                    <?php if ($isInside): ?>
                      <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.72rem;">
                        <span class="door-badge-pulse me-1"></span>Masih di Dalam
                      </span>
                    <?php else: ?>
                      <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-1" style="font-size: 0.72rem;">
                        <i class="bi bi-check2-circle me-1"></i>Selesai (<?= (int)$l['duration_minutes'] ?> mnt)
                      </span>
                    <?php endif; ?>
                  </div>

                  <!-- Identitas Pengunjung -->
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <?php if (!empty($l['photo_in'])): ?>
                      <img src="<?= e($l['photo_in']) ?>" class="rounded-circle object-fit-cover border" style="width: 38px; height: 38px; min-width: 38px; cursor: pointer;" onclick="previewPhoto(<?= htmlspecialchars(json_encode($l['photo_in']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($l['visitor_name']), ENT_QUOTES, 'UTF-8') ?>)" title="Klik untuk lihat foto masuk">
                    <?php else: ?>
                      <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-secondary border" style="width: 38px; height: 38px; min-width: 38px;">
                        <i class="bi bi-person-fill fs-5"></i>
                      </div>
                    <?php endif; ?>
                    <div class="overflow-hidden">
                      <div class="fw-bold text-dark fs-6 text-truncate"><?= e($l['visitor_name']) ?></div>
                      <div class="small text-muted text-truncate" style="font-size: 0.74rem;">
                        <?= e($l['institution'] ?: 'PT BPR Mitratama') ?> &bull; 
                        <span class="badge bg-light text-dark border" style="font-size: 0.68rem;"><?= e($l['visitor_type']) ?></span>
                      </div>
                    </div>
                  </div>

                  <!-- Keperluan & Pendamping -->
                  <div class="small text-muted mb-2" style="font-size: 0.78rem;">
                    <div class="mb-1 text-dark"><i class="bi bi-tools text-primary me-1.5"></i><strong>Keperluan:</strong> <?= e($l['purpose']) ?></div>
                    <?php if (!empty($l['purpose_detail'])): ?>
                      <div class="text-secondary ps-3 mb-1" style="font-size: 0.74rem;">&ldquo;<?= e($l['purpose_detail']) ?>&rdquo;</div>
                    <?php endif; ?>
                    <div><i class="bi bi-person-badge text-info me-1.5"></i><strong>Pendamping IT:</strong> <?= e($l['accompanied_by'] ?: 'Mandiri (IT Ops)') ?></div>
                  </div>

                  <!-- Waktu & Suhu -->
                  <div class="p-2 rounded-3 bg-light d-flex align-items-center justify-content-between small text-muted mb-2" style="font-size: 0.73rem;">
                    <div>
                      <i class="bi bi-box-arrow-in-right text-success me-1"></i>Masuk: <strong><?= date('d/m H:i', $enterTs) ?></strong> (<?= e($l['temperature_entry'] ? $l['temperature_entry'].'°C' : '-') ?>)
                    </div>
                    <div>
                      <i class="bi bi-box-arrow-right text-danger me-1"></i>Keluar: <strong><?= $exitTs ? date('H:i', $exitTs) : '-' ?></strong> (<?= e($l['temperature_exit'] ? $l['temperature_exit'].'°C' : '-') ?>)
                    </div>
                  </div>

                  <!-- Catatan Selesai -->
                  <?php if (!empty($l['notes'])): ?>
                    <div class="small text-muted bg-white p-2 rounded-2 border mb-2" style="font-size: 0.73rem;">
                      <i class="bi bi-chat-left-text me-1 text-secondary"></i><strong>Catatan:</strong> <?= e($l['notes']) ?>
                    </div>
                  <?php endif; ?>

                  <!-- Aksi Check-Out / Foto -->
                  <div class="d-flex align-items-center gap-2 pt-1">
                    <?php if ($isInside): ?>
                      <button type="button" class="btn btn-sm btn-danger rounded-pill flex-grow-1 fw-bold shadow-xs py-1.5" onclick="openCheckoutModal(<?= (int)$l['id'] ?>, <?= htmlspecialchars(json_encode($l['visitor_name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($l['log_code']), ENT_QUOTES, 'UTF-8') ?>)">
                        <i class="bi bi-box-arrow-right me-1"></i> Check-Out Keluar Sekarang
                      </button>
                    <?php endif; ?>
                    <?php if (!empty($l['photo_in'])): ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1.5" onclick="previewPhoto(<?= htmlspecialchars(json_encode($l['photo_in']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($l['visitor_name']), ENT_QUOTES, 'UTF-8') ?>)">
                        <i class="bi bi-image me-1"></i> Foto
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- FOOTER STANDAR KEPATUHAN -->
      <footer class="text-center text-muted small py-3" style="font-size: 0.72rem;">
        <div>&copy; <?= date('Y') ?> PT BPR Mitratama Arthabuana &middot; Ruang Server &amp; Data Center Terbatas</div>
        <div class="text-secondary mt-1">Standar Keamanan Fisik OJK POJK No. 75/POJK.03/2016 &middot; ISO/IEC 27001</div>
      </footer>

    </div>
  </main>

  <!-- ========================================== -->
  <!-- MODAL 1: FORM CHECK-IN MASUK               -->
  <!-- ========================================== -->
  <div class="modal fade" id="modalCheckin" tabindex="-1" aria-labelledby="modalCheckinLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content shadow-lg overflow-hidden">
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
            <div class="alert alert-info border-0 shadow-xs mb-3 py-2 px-3 small">
              <i class="bi bi-info-circle-fill me-1"></i> Setiap orang wajib mencatat kehadiran, keperluan, dan suhu awal saat masuk.
            </div>

            <div class="row g-3">
              <!-- Nama Pengunjung -->
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Nama Lengkap Pengunjung / Petugas <span class="text-danger">*</span></label>
                <input type="text" name="visitor_name" id="inputVisitorName" class="form-control" placeholder="Ketik nama lengkap..." required>
              </div>

              <!-- Tipe Pengunjung -->
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Tipe Pengunjung / Kategori <span class="text-danger">*</span></label>
                <select name="visitor_type" id="checkinVisitorType" class="form-select" onchange="onVisitorTypeChange(this.value)" required>
                  <option value="internal_it">Teknisi Internal IT Bank</option>
                  <option value="vendor_external">Vendor / Rekanan Luar</option>
                  <option value="auditor">Auditor SKAI / Eksternal</option>
                  <option value="internal_staff">Staff Internal Bank</option>
                </select>
              </div>

              <!-- Instansi / Perusahaan -->
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Instansi / Perusahaan Vendor</label>
                <input type="text" name="institution" id="checkinInstitution" class="form-control" value="PT BPR Mitratama Arthabuana" placeholder="Nama perusahaan vendor / cabang...">
              </div>

              <!-- No. Kontak / HP -->
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Nomor HP / WhatsApp Aktif</label>
                <input type="tel" name="contact_number" class="form-control" placeholder="08xxxxxxxxxx">
              </div>

              <!-- Pendamping IT Bank -->
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Pendamping Resmi Staf IT Bank <span class="text-danger">*</span></label>
                <input type="text" name="accompanied_by" id="checkinAccompaniedBy" class="form-control" placeholder="Nama staf IT pendamping..." required>
              </div>

              <!-- Suhu Masuk Ruangan -->
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Suhu Ruangan Saat Masuk (&deg;C) <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text bg-light"><i class="bi bi-thermometer-half text-danger"></i></span>
                  <input type="number" step="0.1" name="temperature_entry" class="form-control" placeholder="20.0" value="20.0" required>
                  <span class="input-group-text">&deg;C</span>
                </div>
              </div>

              <!-- Keperluan Kunjungan -->
              <div class="col-12">
                <label class="form-label fw-semibold small">Keperluan Kunjungan / Pekerjaan <span class="text-danger">*</span></label>
                <input type="text" name="purpose" class="form-control" placeholder="Contoh: Pemeliharaan berkala Switch Core, cek UPS, inspeksi AC..." required>
              </div>

              <!-- Rincian Pekerjaan -->
              <div class="col-12">
                <label class="form-label fw-semibold small">Rincian Pekerjaan &amp; Perangkat yang Dikerjakan</label>
                <textarea name="purpose_detail" class="form-control" rows="2" placeholder="Jelaskan secara singkat rack server nomor berapa atau unit apa yang dikerjakan..."></textarea>
              </div>

              <!-- Peralatan Dibawa Masuk -->
              <div class="col-12">
                <label class="form-label fw-semibold small">Peralatan / Tools yang Dibawa Masuk</label>
                <input type="text" name="tools_brought" class="form-control" placeholder="Contoh: Laptop console, Toolkit obeng, Fluke Tester...">
              </div>

              <!-- Kamera Selfie / Dokumentasi Masuk -->
              <div class="col-12">
                <label class="form-label fw-semibold small d-flex align-items-center justify-content-between">
                  <span>Dokumentasi Wajah Kedatangan (Kamera Selfie)</span>
                  <span class="badge bg-light text-muted border">Opsional / Rekomendasi</span>
                </label>
                <div class="border rounded-3 p-3 bg-light text-center">
                  <div id="webcamWrapper" class="d-none">
                    <video id="webcamVideo" autoplay playsinline style="width: 100%; max-width: 320px; height: 240px; object-fit: cover; border-radius: 12px; background:#000;" class="mb-2 shadow-sm"></video>
                    <canvas id="webcamCanvas" class="d-none"></canvas>
                    <div>
                      <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" onclick="snapPhotoCheckin()">
                        <i class="bi bi-camera-fill me-1"></i> Jepret Foto
                      </button>
                    </div>
                  </div>

                  <div id="previewSnapBox" class="d-none mb-2">
                    <img id="imgSnapPreview" src="" class="rounded-3 border shadow-sm" style="width: 120px; height: 120px; object-fit: cover;">
                    <div class="mt-1">
                      <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0.5 px-2" onclick="retakePhotoCheckin()" style="font-size: 0.72rem;">Foto Ulang</button>
                    </div>
                  </div>

                  <div id="btnStartWebcamBox">
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold" onclick="startWebcamCheckin()">
                      <i class="bi bi-camera me-1"></i> Buka Kamera Selfie
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer bg-light py-3 px-4 d-flex justify-content-between">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm" style="background: #8C57FF; border-color: #8C57FF;">
              <i class="bi bi-door-open-fill me-1"></i> Konfirmasi Check-In Masuk
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ========================================== -->
  <!-- MODAL 2: FORM CHECK-OUT KELUAR             -->
  <!-- ========================================== -->
  <div class="modal fade" id="modalCheckout" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content shadow-lg overflow-hidden">
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
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <div class="small text-muted text-uppercase fw-semibold" style="font-size: 0.70rem;">Petugas Keluar:</div>
                  <div class="fw-bold fs-6 text-dark" id="checkoutVisitorName">Nama Pengunjung</div>
                </div>
                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 fw-bold" id="checkoutLogCode">Kode Log</span>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold small">Suhu Ruangan Saat Keluar (&deg;C) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-thermometer-half text-danger"></i></span>
                <input type="number" step="0.1" name="temperature_exit" class="form-control" placeholder="20.0" value="20.0" required>
                <span class="input-group-text">&deg;C</span>
              </div>
              <div class="form-text text-muted" style="font-size: 0.72rem;">Standar suhu: 18&deg;C &ndash; 22&deg;C. Pastikan AC tetap dingin stabil.</div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold small">Catatan Hasil Pekerjaan / Kondisi Selesai <span class="text-danger">*</span></label>
              <textarea name="notes" class="form-control" rows="3" placeholder="Contoh: Pekerjaan selesai, AC dingin normal, lampu dimatikan dan pintu dikunci kembali..." required></textarea>
            </div>
          </div>

          <div class="modal-footer bg-light py-3 px-4 d-flex justify-content-between">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-danger fw-bold rounded-pill px-4 shadow-sm">
              <i class="bi bi-box-arrow-right me-1"></i> Konfirmasi Selesai &amp; Keluar
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ========================================== -->
  <!-- MODAL 3: PILIH PETUGAS KELUAR (> 1 ORANG)  -->
  <!-- ========================================== -->
  <div class="modal fade" id="modalSelectCheckout" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content shadow-lg overflow-hidden">
        <div class="modal-header bg-danger text-white py-3 px-4">
          <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
            <i class="bi bi-box-arrow-right"></i> Pilih Petugas yang Keluar
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <p class="text-muted small mb-3">
            Terdapat <strong class="text-danger"><?= count($liveOccupants) ?> orang</strong> di dalam ruang server. Silakan tap nama Anda yang akan Check-Out:
          </p>
          <div class="d-flex flex-column gap-2">
            <?php foreach ($liveOccupants as $occ): 
              $durMin = max(0, round((time() - strtotime($occ['entry_time'])) / 60));
            ?>
              <div class="card border rounded-3 p-3 shadow-xs" style="cursor: pointer;" onclick="openCheckoutModal(<?= (int)$occ['id'] ?>, <?= htmlspecialchars(json_encode($occ['visitor_name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($occ['log_code']), ENT_QUOTES, 'UTF-8') ?>); bootstrap.Modal.getInstance(document.getElementById('modalSelectCheckout')).hide();">
                <div class="d-flex align-items-center justify-content-between">
                  <div>
                    <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-1.5">
                      <i class="bi bi-person-fill text-danger"></i>
                      <span><?= e($occ['visitor_name']) ?></span>
                    </div>
                    <div class="small text-muted"><?= e($occ['institution'] ?: 'PT BPR Mitratama') ?> &bull; <code><?= e($occ['log_code']) ?></code></div>
                    <div class="small text-danger fw-semibold mt-1">
                      <i class="bi bi-clock-history me-1"></i>Masuk jam <?= date('H:i', strtotime($occ['entry_time'])) ?> (<?= $durMin ?> menit lalu)
                    </div>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold">
                    Keluar <i class="bi bi-box-arrow-right ms-1"></i>
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ========================================== -->
  <!-- MODAL 4: SCANNER BARCODE LIVE KAMERA       -->
  <!-- ========================================== -->
  <div class="modal fade" id="modalScanner" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content shadow-lg overflow-hidden">
        <div class="modal-header bg-dark text-white py-3 px-4">
          <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
            <i class="bi bi-qr-code-scan text-warning"></i> Scanner Barcode Live
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="stopScannerModal()"></button>
        </div>
        <div class="modal-body p-4 text-center">
          <p class="text-muted small mb-3">Arahkan kamera HP ke Barcode ID Card atau Stiker Pintu Ruang Server:</p>
          <div id="scannerReader" class="scanner-box mb-3"></div>
          <div id="scannerStatusText" class="small fw-semibold text-primary">
            <i class="bi bi-camera me-1"></i> Menyiapkan kamera scanner...
          </div>
        </div>
        <div class="modal-footer bg-light py-2 px-3">
          <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal" onclick="stopScannerModal()">Tutup</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ========================================== -->
  <!-- MODAL 5: TIDAK ADA ORANG DI DALAM          -->
  <!-- ========================================== -->
  <div class="modal fade" id="modalNoOccupants" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content shadow-lg text-center p-4">
        <div class="my-2 display-4" style="color: #F59E0B;">
          <i class="bi bi-door-closed-fill"></i>
        </div>
        <h5 class="fw-bold text-dark mt-2">Tidak Ada yang Sedang di Dalam</h5>
        <p class="text-muted small mb-3">
          Saat ini tidak ada data petugas atau vendor yang tercatat aktif di dalam Ruang Server.
        </p>
        <div class="d-flex justify-content-center gap-2">
          <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
          <button type="button" class="btn btn-sm btn-primary fw-bold rounded-pill px-4" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#modalCheckin">
            <i class="bi bi-door-open-fill me-1"></i> Buka Form Check-In Masuk
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ========================================== -->
  <!-- MODAL 6: PREVIEW FOTO PENGUNJUNG           -->
  <!-- ========================================== -->
  <div class="modal fade" id="modalPreviewPhoto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content shadow-lg overflow-hidden">
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

  <!-- Bootstrap 5 JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Live Clock
    function updateLiveClock() {
      const now = new Date();
      const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
      const str = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear() + ' · ' +
                  String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0') + ':' + String(now.getSeconds()).padStart(2, '0') + ' WIB';
      const el = document.getElementById("livePortalClock");
      if (el) el.textContent = str;
    }
    setInterval(updateLiveClock, 1000);
    updateLiveClock();

    // Data Occupants
    const liveOccupantsData = <?= json_encode(array_map(function($o) {
      return [
        'id' => (int)$o['id'],
        'visitor_name' => $o['visitor_name'],
        'log_code' => $o['log_code'],
        'user_id' => (int)($o['user_id'] ?? 0)
      ];
    }, $liveOccupants)) ?>;

    function handlePublicCheckout() {
      if (!liveOccupantsData || liveOccupantsData.length === 0) {
        const m = new bootstrap.Modal(document.getElementById("modalNoOccupants"));
        m.show();
        return;
      }
      if (liveOccupantsData.length === 1) {
        openCheckoutModal(liveOccupantsData[0].id, liveOccupantsData[0].visitor_name, liveOccupantsData[0].log_code);
      } else {
        const m = new bootstrap.Modal(document.getElementById("modalSelectCheckout"));
        m.show();
      }
    }

    function openCheckoutModal(id, name, code) {
      document.getElementById("checkoutLogId").value = id;
      document.getElementById("checkoutVisitorName").textContent = name;
      document.getElementById("checkoutLogCode").textContent = code;
      const m = new bootstrap.Modal(document.getElementById("modalCheckout"));
      m.show();
    }

    function previewPhoto(src, name) {
      document.getElementById("imgBigPreview").src = src;
      document.getElementById("previewTitle").textContent = "Dokumentasi Kedatangan: " + name;
      const m = new bootstrap.Modal(document.getElementById("modalPreviewPhoto"));
      m.show();
    }

    function onVisitorTypeChange(type) {
      const inst = document.getElementById("checkinInstitution");
      const acc = document.getElementById("checkinAccompaniedBy");
      if (type === "vendor_external") {
        if (inst.value === "PT BPR Mitratama Arthabuana") inst.value = "";
        inst.placeholder = "Nama Perusahaan Vendor (Wajib diisi)...";
        acc.placeholder = "Staf IT Bank pendamping resmi...";
      } else {
        if (!inst.value) inst.value = "PT BPR Mitratama Arthabuana";
      }
    }

    // Webcam Selfie Capture
    let webcamStream = null;
    function startWebcamCheckin() {
      const v = document.getElementById("webcamVideo");
      const wrap = document.getElementById("webcamWrapper");
      const btn = document.getElementById("btnStartWebcamBox");

      navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: false })
        .then(function(stream) {
          webcamStream = stream;
          v.srcObject = stream;
          wrap.classList.remove("d-none");
          btn.classList.add("d-none");
        })
        .catch(function(err) {
          alert("Gagal membuka kamera selfie: " + err.message + ". Pastikan izin kamera telah diberikan di browser.");
        });
    }

    function snapPhotoCheckin() {
      const v = document.getElementById("webcamVideo");
      const c = document.getElementById("webcamCanvas");
      c.width = v.videoWidth || 320;
      c.height = v.videoHeight || 240;
      const ctx = c.getContext("2d");
      ctx.drawImage(v, 0, 0, c.width, c.height);

      const dataUrl = c.toDataURL("image/jpeg", 0.75);
      document.getElementById("inputPhotoIn").value = dataUrl;
      document.getElementById("imgSnapPreview").src = dataUrl;
      document.getElementById("previewSnapBox").classList.remove("d-none");
      document.getElementById("webcamWrapper").classList.add("d-none");

      if (webcamStream) {
        webcamStream.getTracks().forEach(t => t.stop());
        webcamStream = null;
      }
    }

    function retakePhotoCheckin() {
      document.getElementById("inputPhotoIn").value = "";
      document.getElementById("previewSnapBox").classList.add("d-none");
      startWebcamCheckin();
    }

    // Barcode Scanner Live
    let html5QrScanner = null;
    function startScannerModal() {
      const statusEl = document.getElementById("scannerStatusText");
      statusEl.innerHTML = '<i class="bi bi-camera me-1"></i> Mengaktifkan kamera scanner...';
      setTimeout(function() {
        try {
          html5QrScanner = new Html5Qrcode("scannerReader");
          html5QrScanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            function(decodedText) {
              onQrCodeDetected(decodedText);
            },
            function() {}
          ).then(function() {
            statusEl.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Kamera aktif &mdash; Arahkan ke Barcode / QR Pintu</span>';
          }).catch(function(err) {
            statusEl.innerHTML = '<span class="text-danger">Gagal membuka kamera: ' + err + '</span>';
          });
        } catch(e) {
          statusEl.innerHTML = '<span class="text-danger">Scanner error: ' + e.message + '</span>';
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

      if (text.includes("checkout_scan") || text.includes("action=checkout")) {
        handlePublicCheckout();
        return;
      }
      if (text.includes("checkin_scan") || text.includes("server_room") || text.includes("door")) {
        const checkinModal = new bootstrap.Modal(document.getElementById("modalCheckin"));
        checkinModal.show();
        return;
      }
      // Cek apakah code ini cocok dengan seseorang di dalam
      const occMatch = liveOccupantsData.find(function(o) {
        return (o.log_code && o.log_code.toLowerCase() === text.toLowerCase()) || 
               (o.visitor_name && o.visitor_name.toLowerCase().includes(text.toLowerCase()));
      });
      if (occMatch) {
        openCheckoutModal(occMatch.id, occMatch.visitor_name, occMatch.log_code);
        return;
      }
      const checkinModal = new bootstrap.Modal(document.getElementById("modalCheckin"));
      const nameInp = document.getElementById("inputVisitorName");
      if (nameInp) nameInp.value = text;
      checkinModal.show();
    }

    // Auto-open via URL params
    document.addEventListener("DOMContentLoaded", function() {
      <?php if ($autoOpenCheckout): ?>
        setTimeout(function() {
          handlePublicCheckout();
        }, 350);
      <?php elseif ($autoOpenCheckin): ?>
        setTimeout(function() {
          const checkinModal = new bootstrap.Modal(document.getElementById("modalCheckin"));
          checkinModal.show();
        }, 350);
      <?php elseif ($autoViewLogs): ?>
        setTimeout(function() {
          const el = document.getElementById("riwayatLogTable");
          if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 350);
      <?php endif; ?>
    });
  </script>
</body>
</html>
