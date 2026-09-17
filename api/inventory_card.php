<?php
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/helpers/GoogleSheetsBridge.php';
require_once __DIR__ . '/includes/inventaris_kartu.php';
require_login();

// 1. Tangani Aksi POST (Tambah, Edit, Hapus, Hapus Massal, Import dari Aset IT)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // A. Tambah Data Kartu Baru
    if ($action === 'create') {
        $res = insert_inventaris_kartu([
            'nomor_rekening'   => $_POST['nomor_rekening'] ?? '',
            'nama_barang'      => $_POST['nama_barang'] ?? '',
            'tanggal_perolehan'=> $_POST['tanggal_perolehan'] ?? date('Y-m-d'),
            'barcode_data'     => $_POST['barcode_data'] ?? '',
            'lokasi'           => $_POST['lokasi'] ?? 'KPO / Operasional',
            'pengguna'         => $_POST['pengguna'] ?? 'Umum / Pool'
        ]);
        if (!empty($res['success'])) {
            $_SESSION['flash'] = 'Data kartu inventaris baru berhasil disimpan.';
        } else {
            $_SESSION['flash_error'] = 'Gagal menyimpan kartu: ' . ($res['error'] ?? 'Terjadi kesalahan.');
        }
        header('Location: ' . module_url('inventory_card.php'));
        exit;
    }

    // B. Edit Data Kartu
    if ($action === 'update') {
        $cardId = (int)($_POST['id'] ?? 0);
        $ok = update_inventaris_kartu($cardId, [
            'nomor_rekening'   => $_POST['nomor_rekening'] ?? '',
            'nama_barang'      => $_POST['nama_barang'] ?? '',
            'tanggal_perolehan'=> $_POST['tanggal_perolehan'] ?? date('Y-m-d'),
            'barcode_data'     => $_POST['barcode_data'] ?? '',
            'lokasi'           => $_POST['lokasi'] ?? 'KPO / Operasional',
            'pengguna'         => $_POST['pengguna'] ?? 'Umum / Pool'
        ]);
        if ($ok) {
            $_SESSION['flash'] = 'Data kartu inventaris berhasil diperbarui.';
        } else {
            $_SESSION['flash_error'] = 'Gagal memperbarui data kartu.';
        }
        header('Location: ' . module_url('inventory_card.php'));
        exit;
    }

    // C. Hapus Data Satuan
    if ($action === 'delete') {
        $cardId = (int)($_POST['id'] ?? 0);
        if ($cardId > 0 && delete_inventaris_kartu($cardId)) {
            $_SESSION['flash'] = 'Kartu inventaris berhasil dihapus.';
        } else {
            $_SESSION['flash_error'] = 'Gagal menghapus kartu inventaris.';
        }
        header('Location: ' . module_url('inventory_card.php'));
        exit;
    }

    // D. Hapus Terpilih (Bulk Delete)
    if ($action === 'delete_batch') {
        $rawIds = trim((string)($_POST['ids'] ?? ''));
        $ids = [];
        foreach (explode(',', $rawIds) as $item) {
            $cId = (int)trim($item);
            if ($cId > 0) $ids[] = $cId;
        }
        if (!empty($ids) && delete_inventaris_kartu($ids)) {
            $_SESSION['flash'] = count($ids) . ' kartu inventaris terpilih berhasil dihapus.';
        } else {
            $_SESSION['flash_error'] = 'Gagal menghapus kartu terpilih.';
        }
        header('Location: ' . module_url('inventory_card.php'));
        exit;
    }

    // E. Import dari Aset IT (Pilih dari Aset IT)
    if ($action === 'import_from_assets') {
        $selectedAssetIds = $_POST['asset_ids'] ?? [];
        if (is_string($selectedAssetIds)) {
            $selectedAssetIds = explode(',', $selectedAssetIds);
        }
        $importedCount = 0;
        foreach ($selectedAssetIds as $aid) {
            $aid = (int)$aid;
            if ($aid <= 0) continue;
            $asset = get_asset_by_id($aid);
            if ($asset) {
                $token = !empty($asset['qr_token']) ? $asset['qr_token'] : get_static_qr_token($aid);
                $qrUrl = module_url('scan.php', ['t' => $token]);
                $cId = (int)($asset['id_cabang'] ?? $asset['cabang_id'] ?? 0);
                $cabangName = $asset['cabang_nama'] ?? 'KPO';
                $divName = !empty($asset['divisi_nama']) && $asset['divisi_nama'] !== '-' ? $asset['divisi_nama'] : '';
                $lokasi = $divName !== '' ? "{$cabangName} / {$divName}" : $cabangName;
                $pengguna = !empty($asset['karyawan_nama']) && $asset['karyawan_nama'] !== '-' ? $asset['karyawan_nama'] : 'Umum / Pool';

                $res = insert_inventaris_kartu([
                    'nomor_rekening'   => $asset['kode_inventaris'] ?? ('INV-IT-' . $aid),
                    'nama_barang'      => asset_title($asset),
                    'tanggal_perolehan'=> $asset['tanggal_perolehan'] ?? $asset['created_at'] ?? date('Y-m-d'),
                    'barcode_data'     => $qrUrl,
                    'lokasi'           => $lokasi,
                    'pengguna'         => $pengguna
                ]);
                if (!empty($res['success'])) {
                    $importedCount++;
                }
            }
        }
        $_SESSION['flash'] = "{$importedCount} aset IT berhasil diimpor ke tabel Kartu Inventaris.";
        header('Location: ' . module_url('inventory_card.php'));
        exit;
    }
}

// 2. Ambil Parameter Filter
$search = trim((string)($_GET['q'] ?? ''));
$cabangFilter = trim((string)($_GET['cabang'] ?? ''));
$perPage = max(5, min(100, (int)($_GET['per_page'] ?? 25)));
$page = max(1, (int)($_GET['page'] ?? 1));

// Ambil Seluruh Data Kartu Inventaris
$allRows = get_inventaris_kartu_rows();

// Filter Pencarian & Cabang
$filteredRows = array_filter($allRows, function($r) use ($search, $cabangFilter) {
    if ($search !== '') {
        $q = strtolower($search);
        $haystack = strtolower(($r['nomor_rekening'] ?? '') . ' ' . ($r['nama_barang'] ?? '') . ' ' . ($r['barcode_data'] ?? '') . ' ' . ($r['lokasi'] ?? ''));
        if (strpos($haystack, $q) === false) return false;
    }
    if ($cabangFilter !== '' && $cabangFilter !== '0' && $cabangFilter !== 'Semua Cabang') {
        if (stripos($r['lokasi'] ?? '', $cabangFilter) === false) return false;
    }
    return true;
});

// Urutkan ID Descending (seperti di screenshot)
usort($filteredRows, fn($a, $b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));

$totalCount = count($filteredRows);
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$displayRows = array_slice($filteredRows, $offset, $perPage);

// Data Master Cabang & Aset IT untuk Modal
$cabangs = get_cabang_list();
$rawAssets = is_google_cloud_mode() ? map_sheets_assets() : get_qr_admin_rows(0);

$flashMsg = $_SESSION['flash'] ?? '';
$flashErr = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_error']);

// User Info
$userName = current_user_name();
$userInitial = strtoupper(substr($userName, 0, 2));
if (empty($userInitial)) $userInitial = 'AD';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cetak Kartu · Sistem Manajemen Aset IT</title>
  
  <!-- Fonts & CDN Bootstrap 5.3 -->
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root {
      --bg-main: #060913;
      --bg-card: rgba(13, 19, 36, 0.75);
      --border-card: rgba(255, 255, 255, 0.08);
      --primary-indigo: #6366f1;
      --primary-purple: #a855f7;
      --accent-cyan: #06b6d4;
      --text-white: #f8fafc;
      --text-muted: #94a3b8;
      --text-dim: #64748b;
    }

    * {
      box-sizing: border-box;
    }

    body {
      background-color: var(--bg-main);
      background-image: 
        radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.08) 0%, transparent 40%),
        radial-gradient(circle at 90% 80%, rgba(6, 182, 212, 0.06) 0%, transparent 40%);
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      color: var(--text-white);
      min-height: 100vh;
      margin: 0;
      padding: 0;
    }

    /* Top Navigation Bar */
    .app-header {
      background: rgba(8, 12, 24, 0.85);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--border-card);
      padding: 10px 24px;
      position: sticky;
      top: 0;
      z-index: 1000;
    }

    .brand-title {
      font-size: 1.15rem;
      font-weight: 800;
      color: #ffffff;
      letter-spacing: -0.01em;
      line-height: 1.2;
    }

    .brand-subtitle {
      font-size: 0.76rem;
      color: var(--text-muted);
      font-weight: 500;
    }

    .btn-nav-action {
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid var(--border-card);
      color: #cbd5e1;
      font-size: 0.78rem;
      font-weight: 600;
      padding: 6px 12px;
      border-radius: 8px;
      transition: all 0.15s ease;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
    }

    .btn-nav-action:hover {
      background: rgba(99, 102, 241, 0.15);
      border-color: var(--primary-indigo);
      color: #ffffff;
    }

    .user-pill {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 4px 10px 4px 4px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid var(--border-card);
      border-radius: 20px;
    }

    .user-avatar {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: linear-gradient(135deg, #3b82f6, #6366f1);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.75rem;
      font-weight: 800;
      color: #ffffff;
    }

    .user-name {
      font-size: 0.8rem;
      font-weight: 700;
      color: #f1f5f9;
    }

    /* Main Container */
    .main-wrapper {
      max-width: 1440px;
      margin: 0 auto;
      padding: 24px 20px 60px;
    }

    /* Hero Banner Card */
    .hero-banner {
      background: linear-gradient(135deg, rgba(20, 27, 50, 0.85) 0%, rgba(13, 19, 36, 0.9) 100%);
      border: 1px solid var(--border-card);
      border-radius: 16px;
      padding: 22px 26px;
      margin-bottom: 24px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
      backdrop-filter: blur(16px);
    }

    .hero-icon-box {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      background: linear-gradient(135deg, #6366f1, #a855f7);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.45rem;
      color: #ffffff;
      box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
      flex-shrink: 0;
    }

    .hero-desc {
      font-size: 0.88rem;
      color: #cbd5e1;
      margin-bottom: 0;
      line-height: 1.5;
    }

    /* Action Buttons in Hero */
    .action-row {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 10px;
      margin-top: 20px;
    }

    .btn-action-del {
      background: rgba(220, 38, 38, 0.1);
      border: 1px solid rgba(220, 38, 38, 0.4);
      color: #f87171;
      font-size: 0.82rem;
      font-weight: 600;
      padding: 7px 16px;
      border-radius: 8px;
      transition: all 0.15s ease;
    }

    .btn-action-del:hover:not(:disabled) {
      background: #dc2626;
      color: #ffffff;
    }

    .btn-action-print {
      background: rgba(99, 102, 241, 0.1);
      border: 1px solid rgba(99, 102, 241, 0.4);
      color: #a5b4fc;
      font-size: 0.82rem;
      font-weight: 600;
      padding: 7px 16px;
      border-radius: 8px;
      transition: all 0.15s ease;
    }

    .btn-action-print:hover:not(:disabled) {
      background: #6366f1;
      color: #ffffff;
    }

    .btn-action-export {
      background: rgba(16, 185, 129, 0.1);
      border: 1px solid rgba(16, 185, 129, 0.4);
      color: #34d399;
      font-size: 0.82rem;
      font-weight: 600;
      padding: 7px 16px;
      border-radius: 8px;
      transition: all 0.15s ease;
    }

    .btn-action-export:hover {
      background: #10b981;
      color: #ffffff;
    }

    .btn-action-import {
      background: rgba(59, 130, 246, 0.1);
      border: 1px solid rgba(59, 130, 246, 0.4);
      color: #60a5fa;
      font-size: 0.82rem;
      font-weight: 600;
      padding: 7px 16px;
      border-radius: 8px;
      transition: all 0.15s ease;
    }

    .btn-action-import:hover {
      background: #3b82f6;
      color: #ffffff;
    }

    .btn-action-add {
      background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: #ffffff;
      font-size: 0.82rem;
      font-weight: 700;
      padding: 7px 18px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
      transition: all 0.15s ease;
    }

    .btn-action-add:hover {
      background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
      color: #ffffff;
      box-shadow: 0 6px 16px rgba(99, 102, 241, 0.5);
    }

    /* Search & Filter Bar Card */
    .filter-card {
      background: rgba(13, 19, 36, 0.8);
      border: 1px solid var(--border-card);
      border-radius: 12px;
      padding: 12px 18px;
      margin-bottom: 20px;
    }

    .dark-input {
      background: rgba(7, 11, 22, 0.7) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      color: #f1f5f9 !important;
      font-size: 0.84rem;
      border-radius: 8px;
    }

    .dark-input:focus {
      border-color: var(--primary-indigo) !important;
      box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25) !important;
    }

    .dark-input::placeholder {
      color: var(--text-dim);
    }

    /* Table Surface */
    .table-container {
      background: rgba(13, 19, 36, 0.7);
      border: 1px solid var(--border-card);
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    .dark-table {
      margin-bottom: 0;
      color: #e2e8f0;
      font-size: 0.83rem;
      border-collapse: separate;
      border-spacing: 0;
      width: 100%;
    }

    .dark-table thead th {
      background: rgba(18, 25, 48, 0.95);
      color: #94a3b8;
      font-weight: 700;
      font-size: 0.73rem;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      padding: 14px 16px;
      border-bottom: 1px solid var(--border-card);
      white-space: nowrap;
    }

    .dark-table tbody td {
      padding: 13px 16px;
      vertical-align: middle;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      background: transparent;
      transition: background 0.1s ease;
    }

    .dark-table tbody tr:hover td {
      background: rgba(30, 41, 71, 0.4);
    }

    .font-rek {
      font-family: 'JetBrains Mono', monospace;
      font-weight: 700;
      color: #ffffff;
      font-size: 0.88rem;
    }

    .font-asset-gabungan {
      font-family: 'JetBrains Mono', monospace;
      font-weight: 700;
      color: #0284c7;
      font-size: 0.82rem;
      letter-spacing: 0.02em;
    }

    .barcode-link {
      color: #94a3b8;
      text-decoration: none;
      font-size: 0.76rem;
      font-family: 'JetBrains Mono', monospace;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 240px;
      display: inline-block;
      transition: color 0.15s ease;
    }

    .barcode-link:hover {
      color: #38bdf8;
      text-decoration: underline;
    }

    /* Action Buttons in Row */
    .btn-row-edit {
      width: 32px;
      height: 32px;
      border-radius: 6px;
      background: rgba(234, 179, 8, 0.12);
      border: 1px solid rgba(234, 179, 8, 0.4);
      color: #facc15;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: all 0.15s ease;
      cursor: pointer;
    }

    .btn-row-edit:hover {
      background: #eab308;
      color: #000000;
    }

    .btn-row-del {
      width: 32px;
      height: 32px;
      border-radius: 6px;
      background: rgba(239, 68, 68, 0.12);
      border: 1px solid rgba(239, 68, 68, 0.4);
      color: #f87171;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: all 0.15s ease;
      cursor: pointer;
    }

    .btn-row-del:hover {
      background: #ef4444;
      color: #ffffff;
    }

    .table-footer-bar {
      padding: 12px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-top: 1px solid var(--border-card);
      background: rgba(11, 16, 32, 0.85);
      font-size: 0.78rem;
      color: var(--text-muted);
    }

    /* Custom Checkbox */
    .form-check-input {
      background-color: rgba(255, 255, 255, 0.1);
      border-color: rgba(255, 255, 255, 0.25);
      cursor: pointer;
    }

    .form-check-input:checked {
      background-color: var(--primary-indigo);
      border-color: var(--primary-indigo);
    }

    /* Modal Styling */
    .modal-content.dark-modal {
      background: #0f172a;
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: #f8fafc;
      border-radius: 16px;
    }
  </style>
</head>
<body>

  <!-- =========================================================================
       TOPBAR NAVIGATION
       ========================================================================= -->
  <header class="app-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    
    <!-- Kiri: Brand & Subtitle -->
    <div class="d-flex align-items-center gap-3">
      <a href="<?= e(module_url('dashboard.php')) ?>" class="text-white text-decoration-none fs-5 d-flex align-items-center">
        <i class="bi bi-list"></i>
      </a>
      <div>
        <div class="brand-title">Cetak Kartu</div>
        <div class="brand-subtitle">Sistem Manajemen Aset IT</div>
      </div>
    </div>

    <!-- Kanan: Sync, Panduan, Scan, Live Clock, Status, User -->
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <button type="button" class="btn-nav-action" onclick="syncGoogleSheets()">
        <i class="bi bi-arrow-repeat text-primary"></i> <span>Sync</span>
      </button>

      <a href="<?= e(module_url('PANDUAN_MIGRASI_KARTU_INVENTARIS.md')) ?>" target="_blank" class="btn-nav-action">
        <i class="bi bi-question-circle text-info"></i> <span>Panduan</span>
      </a>

      <a href="<?= e(module_url('scanner.php')) ?>" class="btn-nav-action">
        <i class="bi bi-qr-code-scan text-indigo"></i> <span>Scan Kamera</span>
      </a>

      <button type="button" class="btn-nav-action" onclick="toggleTheme()" title="Mode Tampilan">
        <i class="bi bi-moon-stars"></i>
      </button>

      <!-- Live Clock & Status Badge -->
      <div class="d-none d-xl-flex flex-column align-items-end px-2" style="line-height: 1.15;">
        <span class="small fw-semibold text-light" id="liveClockDisplay" style="font-size: 0.76rem;">-</span>
        <span class="text-success small d-flex align-items-center gap-1" style="font-size: 0.7rem;">
          <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981; display: inline-block;"></span>
          Status: <strong>Online</strong>
        </span>
      </div>

      <!-- Notification Bell -->
      <button type="button" class="btn-nav-action px-2">
        <i class="bi bi-bell"></i>
      </button>

      <!-- User Badge -->
      <div class="user-pill">
        <div class="user-avatar"><?= e($userInitial) ?></div>
        <span class="user-name d-none d-sm-inline"><?= e($userName) ?></span>
      </div>
    </div>

  </header>

  <!-- =========================================================================
       MAIN CONTENT CONTAINER
       ========================================================================= -->
  <main class="main-wrapper">
    
    <!-- Flash Messages -->
    <?php if ($flashMsg): ?>
      <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3" style="background: rgba(16, 185, 129, 0.15); border-left: 4px solid #10b981 !important; color: #6ee7b7;">
        <i class="bi bi-check-circle-fill me-2 text-success"></i><?= e($flashMsg) ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if ($flashErr): ?>
      <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" style="background: rgba(239, 68, 68, 0.15); border-left: 4px solid #ef4444 !important; color: #fca5a5;">
        <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i><?= e($flashErr) ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- 1. HERO BANNER CARD -->
    <section class="hero-banner">
      <div class="d-flex align-items-center gap-3">
        <div class="hero-icon-box">
          <i class="bi bi-credit-card-2-front"></i>
        </div>
        <div>
          <p class="hero-desc">
            Kelola dan cetak kartu inventaris berukuran ATM (CR80) secara massal & presisi.
          </p>
        </div>
      </div>

      <!-- Action Buttons Row -->
      <div class="action-row">
        <!-- Hapus Terpilih -->
        <button type="button" class="btn btn-action-del" id="btnDeleteSelected" disabled onclick="bulkDeleteCards()">
          <i class="bi bi-trash me-1"></i> Hapus Terpilih ( <span id="countDelete">0</span> )
        </button>

        <!-- Cetak Kartu Pilihan -->
        <button type="button" class="btn btn-action-print" id="btnPrintSelected" onclick="printSelectedCards()">
          <i class="bi bi-printer me-1"></i> Cetak Kartu Pilihan ( <span id="countPrint">0</span> )
        </button>

        <!-- Ekspor CSV / Excel -->
        <a href="<?= e(module_url('print_inventory_card.php', ['export' => 'csv'])) ?>" class="btn btn-action-export">
          <i class="bi bi-file-earmark-spreadsheet me-1"></i> Ekspor CSV / Excel
        </a>

        <!-- Pilih dari Aset IT -->
        <button type="button" class="btn btn-action-import" data-bs-toggle="modal" data-bs-target="#importAssetModal">
          <i class="bi bi-box-seam me-1"></i> Pilih dari Aset IT
        </button>

        <!-- + Tambah Data Kartu -->
        <button type="button" class="btn btn-action-add ms-auto" data-bs-toggle="modal" data-bs-target="#addCardModal">
          <i class="bi bi-plus-lg me-1"></i> Tambah Data Kartu
        </button>
      </div>
    </section>

    <!-- 2. SEARCH & FILTER CARD -->
    <section class="filter-card">
      <form method="get" class="row g-2 align-items-center">
        <!-- Search Input -->
        <div class="col-12 col-md-5">
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-transparent border-0 text-muted ps-2"><i class="bi bi-search"></i></span>
            <input type="text" name="q" value="<?= e($search) ?>" class="form-control form-control-sm dark-input" placeholder="Cari nomor rekening / nama barang...">
          </div>
        </div>

        <!-- Filter Cabang -->
        <div class="col-8 col-md-3">
          <select name="cabang" class="form-select form-select-sm dark-input" onchange="this.form.submit()">
            <option value="Semua Cabang">Semua Cabang</option>
            <?php foreach ($cabangs as $c): ?>
              <?php $cName = $c['nama'] ?? $c['nama_cabang'] ?? ''; ?>
              <option value="<?= e($cName) ?>" <?= $cabangFilter === $cName ? 'selected' : '' ?>>
                <?= e($cName) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Reset Button -->
        <div class="col-4 col-md-1">
          <a href="<?= e(module_url('inventory_card.php')) ?>" class="btn btn-sm btn-outline-secondary w-100 py-1" title="Reset Filter">
            <i class="bi bi-arrow-clockwise"></i>
          </a>
        </div>

        <!-- Tampilkan X baris -->
        <div class="col-12 col-md-3 text-md-end">
          <span class="small text-muted me-2" style="font-size: 0.78rem;">Tampilkan:</span>
          <select name="per_page" class="form-select form-select-sm dark-input d-inline-block w-auto" onchange="this.form.submit()">
            <option value="10" <?= $perPage === 10 ? 'selected' : '' ?>>10 baris</option>
            <option value="25" <?= $perPage === 25 ? 'selected' : '' ?>>25 baris</option>
            <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50 baris</option>
            <option value="100" <?= $perPage === 100 ? 'selected' : '' ?>>100 baris</option>
          </select>
        </div>
      </form>
    </section>

    <!-- 3. TABLE CONTAINER -->
    <section class="table-container">
      <div class="table-responsive">
        <table class="dark-table">
          <thead>
            <tr>
              <th style="width: 44px;" class="text-center">
                <input class="form-check-input" type="checkbox" id="checkAllRows" onchange="toggleSelectAll(this)">
              </th>
              <th>NOMOR REKENING <i class="bi bi-arrow-down-up small opacity-50 ms-1"></i></th>
              <th>NAMA BARANG <i class="bi bi-arrow-down-up small opacity-50 ms-1"></i></th>
              <th>TANGGAL PEROLEHAN <i class="bi bi-arrow-down-up small opacity-50 ms-1"></i></th>
              <th>NOMOR ASSET (GABUNGAN)</th>
              <th>KODE QR / BARCODE</th>
              <th class="text-end" style="width: 100px;">AKSI</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($displayRows)): ?>
              <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                  <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                  Tidak ada data kartu inventaris yang ditemukan.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($displayRows as $row): ?>
                <?php 
                  $cId = (int)$row['id'];
                  $rek = $row['nomor_rekening'] ?? '';
                  $nama = $row['nama_barang'] ?? '';
                  $tglRaw = $row['tanggal_perolehan'] ?? '';
                  $tglIndo = format_indo_date($tglRaw);
                  $gabungan = get_nomor_asset_gabungan($rek, $tglRaw);
                  $barcode = $row['barcode_data'] ?? '';
                ?>
                <tr id="card-row-<?= $cId ?>">
                  <td class="text-center">
                    <input class="form-check-input row-checkbox" type="checkbox" value="<?= $cId ?>" onchange="updateSelectedCounters()">
                  </td>
                  <td>
                    <span class="font-rek"><?= e($rek) ?></span>
                  </td>
                  <td>
                    <span class="fw-bold text-white"><?= e($nama) ?></span>
                  </td>
                  <td>
                    <span class="d-inline-flex align-items-center gap-1 text-light" style="font-size: 0.8rem;">
                      <i class="bi bi-calendar-event text-primary opacity-75"></i>
                      <span><?= e($tglIndo) ?></span>
                    </span>
                  </td>
                  <td>
                    <span class="font-asset-gabungan"><?= e($gabungan) ?></span>
                  </td>
                  <td>
                    <?php if ($barcode): ?>
                      <a href="<?= e($barcode) ?>" target="_blank" class="barcode-link" title="<?= e($barcode) ?>">
                        <?= e($barcode) ?>
                      </a>
                    <?php else: ?>
                      <span class="text-muted small">-</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-1">
                      <!-- Edit Button -->
                      <button type="button" class="btn-row-edit" title="Edit Kartu" onclick='openEditModal(<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                        <i class="bi bi-pencil-square"></i>
                      </button>
                      
                      <!-- Delete Button -->
                      <button type="button" class="btn-row-del" title="Hapus Kartu" onclick="confirmDelete(<?= $cId ?>, '<?= e(addslashes($rek . ' - ' . $nama)) ?>')">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Table Footer / Pagination -->
      <div class="table-footer-bar">
        <div>
          Menampilkan <strong><?= $totalCount > 0 ? $offset + 1 : 0 ?></strong> – <strong><?= min($offset + $perPage, $totalCount) ?></strong> dari <strong><?= $totalCount ?></strong> data
        </div>

        <div class="d-flex align-items-center gap-1">
          <!-- Prev Prev -->
          <a href="<?= e(filter_query(['page' => 1])) ?>" class="btn btn-sm btn-outline-secondary py-0 px-2 <?= $page <= 1 ? 'disabled opacity-50' : '' ?>">&laquo;</a>
          <!-- Prev -->
          <a href="<?= e(filter_query(['page' => max(1, $page - 1)])) ?>" class="btn btn-sm btn-outline-secondary py-0 px-2 <?= $page <= 1 ? 'disabled opacity-50' : '' ?>">&lsaquo;</a>

          <!-- Page indicator -->
          <span class="badge bg-secondary px-3 py-1"><?= $page ?></span>

          <!-- Next -->
          <a href="<?= e(filter_query(['page' => min($totalPages, $page + 1)])) ?>" class="btn btn-sm btn-outline-secondary py-0 px-2 <?= $page >= $totalPages ? 'disabled opacity-50' : '' ?>">&rsaquo;</a>
          <!-- Next Next -->
          <a href="<?= e(filter_query(['page' => $totalPages])) ?>" class="btn btn-sm btn-outline-secondary py-0 px-2 <?= $page >= $totalPages ? 'disabled opacity-50' : '' ?>">&raquo;</a>
        </div>
      </div>
    </section>

  </main>

  <!-- =========================================================================
       MODAL: TAMBAH DATA KARTU INVENTARIS
       ========================================================================= -->
  <div class="modal fade" id="addCardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content dark-modal shadow-lg">
        <form method="post">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="create">
          <div class="modal-header border-secondary py-2 px-3">
            <h6 class="modal-title fw-bold text-white d-flex align-items-center gap-2">
              <i class="bi bi-plus-circle-fill text-primary"></i> Tambah Data Kartu Inventaris
            </h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-3">
            <div class="mb-3">
              <label class="form-label small fw-bold text-muted">Nomor Rekening</label>
              <input type="text" name="nomor_rekening" class="form-control form-control-sm dark-input font-monospace" placeholder="Contoh: 01.05.0494" required>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-bold text-muted">Nama Barang</label>
              <input type="text" name="nama_barang" class="form-control form-control-sm dark-input" placeholder="Contoh: LAPTOP ACER TRAVELMATE" required>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label small fw-bold text-muted">Tanggal Perolehan</label>
                <input type="date" name="tanggal_perolehan" class="form-control form-control-sm dark-input" value="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="col-6">
                <label class="form-label small fw-bold text-muted">Lokasi Penempatan</label>
                <input type="text" name="lokasi" class="form-control form-control-sm dark-input" value="KPO / Operasional" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-bold text-muted">Pengguna / PIC</label>
              <input type="text" name="pengguna" class="form-control form-control-sm dark-input" value="Umum / Pool">
            </div>
            <div class="mb-2">
              <label class="form-label small fw-bold text-muted">Kode QR / Barcode Link</label>
              <input type="text" name="barcode_data" class="form-control form-control-sm dark-input font-monospace" placeholder="https://canva.link/... atau URL verifikasi">
              <div class="form-text text-muted" style="font-size: 0.72rem;">Isi link canva atau teks target scan QR Code.</div>
            </div>
          </div>
          <div class="modal-footer border-secondary py-2 px-3">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-sm btn-action-add">Simpan Data Kartu</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- =========================================================================
       MODAL: EDIT DATA KARTU INVENTARIS
       ========================================================================= -->
  <div class="modal fade" id="editCardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content dark-modal shadow-lg">
        <form method="post">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" id="editCardId">
          <div class="modal-header border-secondary py-2 px-3">
            <h6 class="modal-title fw-bold text-white d-flex align-items-center gap-2">
              <i class="bi bi-pencil-square text-warning"></i> Edit Data Kartu Inventaris
            </h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-3">
            <div class="mb-3">
              <label class="form-label small fw-bold text-muted">Nomor Rekening</label>
              <input type="text" name="nomor_rekening" id="editCardRekening" class="form-control form-control-sm dark-input font-monospace" required>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-bold text-muted">Nama Barang</label>
              <input type="text" name="nama_barang" id="editCardNama" class="form-control form-control-sm dark-input" required>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label small fw-bold text-muted">Tanggal Perolehan</label>
                <input type="date" name="tanggal_perolehan" id="editCardTanggal" class="form-control form-control-sm dark-input" required>
              </div>
              <div class="col-6">
                <label class="form-label small fw-bold text-muted">Lokasi Penempatan</label>
                <input type="text" name="lokasi" id="editCardLokasi" class="form-control form-control-sm dark-input" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-bold text-muted">Pengguna / PIC</label>
              <input type="text" name="pengguna" id="editCardPengguna" class="form-control form-control-sm dark-input">
            </div>
            <div class="mb-2">
              <label class="form-label small fw-bold text-muted">Kode QR / Barcode Link</label>
              <input type="text" name="barcode_data" id="editCardBarcode" class="form-control form-control-sm dark-input font-monospace">
            </div>
          </div>
          <div class="modal-footer border-secondary py-2 px-3">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-sm btn-primary fw-bold">Perbarui Kartu</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- =========================================================================
       MODAL: PILIH DARI ASET IT (IMPORT CATALOG)
       ========================================================================= -->
  <div class="modal fade" id="importAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content dark-modal shadow-lg">
        <form method="post">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="import_from_assets">
          <div class="modal-header border-secondary py-2 px-3">
            <h6 class="modal-title fw-bold text-white d-flex align-items-center gap-2">
              <i class="bi bi-box-seam text-primary"></i> Pilih Perangkat dari Katalog Aset IT
            </h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-3">
            <p class="small text-muted mb-2">Pilih komputer atau perangkat di bawah untuk ditambahkan ke tabel Kartu Inventaris CR80:</p>
            <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
              <table class="dark-table">
                <thead>
                  <tr>
                    <th style="width: 36px;"></th>
                    <th>Kode Inventaris</th>
                    <th>Perangkat</th>
                    <th>Pengguna</th>
                    <th>Cabang</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rawAssets as $a): ?>
                    <?php $aid = (int)($a['id'] ?? 0); ?>
                    <tr>
                      <td class="text-center">
                        <input class="form-check-input" type="checkbox" name="asset_ids[]" value="<?= $aid ?>">
                      </td>
                      <td class="font-monospace fw-bold text-white"><?= e($a['kode_inventaris'] ?? ('ASET-' . $aid)) ?></td>
                      <td class="text-light"><?= e(asset_title($a)) ?></td>
                      <td class="text-muted"><?= e($a['karyawan_nama'] ?? '-') ?></td>
                      <td class="text-muted"><?= e($a['cabang_nama'] ?? 'KPO') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer border-secondary py-2 px-3">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-sm btn-primary fw-bold">Impor ke Kartu Inventaris</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Form Delete Hidden -->
  <form id="deleteForm" method="post" style="display: none;">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteTargetId">
  </form>

  <form id="deleteBatchForm" method="post" style="display: none;">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="delete_batch">
    <input type="hidden" name="ids" id="deleteBatchIds">
  </form>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // 1. Live Clock
    function updateClock() {
      const now = new Date();
      const hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
      const bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
      
      const hariName = hari[now.getDay()];
      const tgl = now.getDate();
      const blnName = bulan[now.getMonth()];
      const thn = now.getFullYear();
      const jam = String(now.getHours()).padStart(2, '0');
      const mnt = String(now.getMinutes()).padStart(2, '0');
      const dtk = String(now.getSeconds()).padStart(2, '0');

      const text = `${hariName}, ${tgl} ${blnName} ${thn} pukul ${jam}.${mnt}.${dtk}`;
      const el = document.getElementById("liveClockDisplay");
      if (el) el.innerText = text;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // 2. Selection Counters & Actions
    function getSelectedCardIds() {
      const checkedBoxes = document.querySelectorAll(".row-checkbox:checked");
      const ids = [];
      checkedBoxes.forEach(cb => ids.push(cb.value));
      return ids;
    }

    function updateSelectedCounters() {
      const ids = getSelectedCardIds();
      const count = ids.length;

      const delBtn = document.getElementById("btnDeleteSelected");
      const printBtn = document.getElementById("btnPrintSelected");
      const countDelSpan = document.getElementById("countDelete");
      const countPrintSpan = document.getElementById("countPrint");

      countDelSpan.innerText = count;
      countPrintSpan.innerText = count;

      if (count > 0) {
        delBtn.disabled = false;
      } else {
        delBtn.disabled = true;
      }
    }

    function toggleSelectAll(masterCb) {
      const checkboxes = document.querySelectorAll(".row-checkbox");
      checkboxes.forEach(cb => {
        cb.checked = masterCb.checked;
      });
      updateSelectedCounters();
    }

    // 3. Print Selected Cards
    function printSelectedCards() {
      const ids = getSelectedCardIds();
      let targetUrl = "print_inventory_card.php?source=inventaris_kartu";
      if (ids.length > 0) {
        targetUrl += "&ids=" + encodeURIComponent(ids.join(","));
      }
      window.open(targetUrl, "_blank");
    }

    // 4. Bulk Delete
    function bulkDeleteCards() {
      const ids = getSelectedCardIds();
      if (ids.length === 0) return;
      if (confirm(`Hapus ${ids.length} data kartu inventaris terpilih?`)) {
        document.getElementById("deleteBatchIds").value = ids.join(",");
        document.getElementById("deleteBatchForm").submit();
      }
    }

    // 5. Delete Single Card
    function confirmDelete(id, label) {
      if (confirm(`Hapus kartu inventaris: ${label}?`)) {
        document.getElementById("deleteTargetId").value = id;
        document.getElementById("deleteForm").submit();
      }
    }

    // 6. Open Edit Modal
    function openEditModal(card) {
      document.getElementById("editCardId").value = card.id || "";
      document.getElementById("editCardRekening").value = card.nomor_rekening || "";
      document.getElementById("editCardNama").value = card.nama_barang || "";
      document.getElementById("editCardTanggal").value = card.tanggal_perolehan || "";
      document.getElementById("editCardLokasi").value = card.lokasi || "KPO / Operasional";
      document.getElementById("editCardPengguna").value = card.pengguna || "Umum / Pool";
      document.getElementById("editCardBarcode").value = card.barcode_data || "";

      const modal = new bootstrap.Modal(document.getElementById("editCardModal"));
      modal.show();
    }

    // 7. Sync Google Sheets
    function syncGoogleSheets() {
      window.location.href = "print_inventory_card.php#sync";
    }

    function toggleTheme() {
      alert("Mode tema visual saat ini adalah Dark Cyberpunk Pro sesuai standar antarmuka.");
    }
  </script>
</body>
</html>
