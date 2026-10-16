<?php
if (!function_exists('render_page')) {
function render_page(string $title, string $content, string $extraHead = '', string $extraScript = '', bool $showNav = true): void {
    $currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $role = current_user_role();
    $userName = current_user_name();
    $userInitial = strtoupper(substr($userName, 0, 1) ?: 'U');
    $isAdmin = is_admin();
    $isLoggedIn = is_logged_in();

    // Context section
    $sectionContext = 'OPERATIONS';
    if (in_array($currentPage, ['audit.php', 'monthly_history.php', 'history.php', 'print_report.php'], true)) {
        $sectionContext = 'MONITORING';
    } elseif (in_array($currentPage, ['cabang_admin.php', 'divisi_admin.php', 'users_admin.php', 'user_biometric_enroll.php'], true)) {
        $sectionContext = 'MANAGEMENT';
    } elseif (in_array($currentPage, ['qr_admin.php', 'system_design.php'], true)) {
        $sectionContext = 'SYSTEM';
    }

    $isAdminUser = is_admin();
    $isHrdUser = is_admin_or_hrd();

    $isHrdRole = is_hrd();
    $brandTag = $isHrdRole ? 'SDM & UMUM' : 'IT OPS';
    $displayRole = $isHrdRole ? 'SDM & Umum' : (($role === 'admin') ? 'Administrator' : ucfirst($role));

    $monitoringItems = [];
    if (!$isHrdRole) {
        $monitoringItems = [
            ['title' => 'Reports & Audit', 'url' => module_url('audit.php'), 'icon' => 'bi-file-earmark-bar-graph', 'active' => ($currentPage === 'audit.php')],
            ['title' => 'Riwayat Bulanan', 'url' => module_url('monthly_history.php'), 'icon' => 'bi-calendar3', 'active' => ($currentPage === 'monthly_history.php')],
        ];
        if ($isAdminUser || in_array($role, ['auditor', 'skai'], true)) {
            $monitoringItems[] = ['title' => 'Log Aktivitas (Audit)', 'url' => module_url('audit_trail.php'), 'icon' => 'bi-shield-check', 'active' => ($currentPage === 'audit_trail.php')];
        }
    }

    $managementItems = [];
    if ($isHrdRole || $isAdminUser) {
        $managementItems[] = ['title' => 'Kantor Cabang', 'url' => module_url('cabang_admin.php'), 'icon' => 'bi-buildings', 'active' => ($currentPage === 'cabang_admin.php')];
        $managementItems[] = ['title' => 'Divisi / Unit Kerja', 'url' => module_url('divisi_admin.php'), 'icon' => 'bi-diagram-3', 'active' => ($currentPage === 'divisi_admin.php')];
        $managementItems[] = ['title' => 'Akun Pengguna', 'url' => module_url('users_admin.php'), 'icon' => 'bi-people', 'active' => ($currentPage === 'users_admin.php')];
    }
    if (!$isHrdRole) {
        $managementItems[] = ['title' => 'Wajah Teknisi (HP)', 'url' => module_url('user_biometric_enroll.php'), 'icon' => 'bi-person-bounding-box', 'active' => ($currentPage === 'user_biometric_enroll.php')];
    }

    $systemItems = [];
    if (!$isHrdRole) {
        if ($isHrdUser) {
            $systemItems[] = ['title' => 'QR Aset Label', 'url' => module_url('qr_admin.php'), 'icon' => 'bi-qr-code', 'active' => ($currentPage === 'qr_admin.php')];
        }
        if ($isAdminUser) {
            $systemItems[] = ['title' => 'Dokumen Desain', 'url' => module_url('system_design.php'), 'icon' => 'bi-file-earmark-pdf', 'active' => ($currentPage === 'system_design.php')];
        }
    }

    // Susun menu operasi (khusus SDM hanya kartu inventaris & galeri foto)
    $operationsItems = [];
    if ($isHrdRole) {
        $operationsItems = [
            ['title' => 'Kartu Inventaris', 'url' => module_url('dashboard.php', ['tab' => 'kartu']), 'icon' => 'bi-credit-card-2-front', 'active' => ($currentPage === 'dashboard.php' && ($_GET['tab'] ?? '') === 'kartu') || in_array($currentPage, ['inventory_card.php', 'cetak_kartu.php', 'print_inventory_card.php', 'kartu_detail.php'], true)],
            ['title' => 'Memo Pengajuan', 'url' => module_url('memo_pengajuan.php'), 'icon' => 'bi-file-earmark-text', 'active' => in_array($currentPage, ['memo_pengajuan.php', 'print_memo.php'], true)],
            ['title' => 'Kepatuhan SDM (OJK)', 'url' => module_url('sdm_kepatuhan.php'), 'icon' => 'bi-shield-check', 'active' => ($currentPage === 'sdm_kepatuhan.php')],
            ['title' => 'Galeri Foto Inventaris', 'url' => module_url('asset_gallery.php', ['sub' => 'inventaris']), 'icon' => 'bi-images', 'active' => in_array($currentPage, ['asset_gallery.php', 'inventaris_gallery.php'], true)],
            ['title' => 'Mutasi Inventaris', 'url' => module_url('inventaris_mutation.php'), 'icon' => 'bi-arrow-left-right', 'active' => in_array($currentPage, ['inventaris_mutation.php', 'asset_mutations.php', 'print_bast.php'], true)],
        ];
    } else {
        $operationsItems = [
            ['title' => 'Dashboard', 'url' => module_url('dashboard.php'), 'icon' => 'bi-speedometer2', 'active' => ($currentPage === 'dashboard.php' && !in_array($_GET['tab'] ?? '', ['kartu', 'temuan'], true))],
            ['title' => 'Kartu Inventaris', 'url' => module_url('dashboard.php', ['tab' => 'kartu']), 'icon' => 'bi-credit-card-2-front', 'active' => ($currentPage === 'dashboard.php' && ($_GET['tab'] ?? '') === 'kartu') || in_array($currentPage, ['inventory_card.php', 'cetak_kartu.php', 'print_inventory_card.php', 'kartu_detail.php'], true)],
            ['title' => 'Galeri Foto', 'url' => module_url('asset_gallery.php'), 'icon' => 'bi-images', 'active' => in_array($currentPage, ['asset_gallery.php', 'inventaris_gallery.php'], true)],
            ['title' => 'Temuan Kendala', 'url' => module_url('dashboard.php', ['tab' => 'temuan']), 'icon' => 'bi-tools', 'active' => ($currentPage === 'dashboard.php' && ($_GET['tab'] ?? '') === 'temuan')],
            ['title' => 'Asset Registry', 'url' => module_url('assets.php'), 'icon' => 'bi-pc-display', 'active' => in_array($currentPage, ['assets.php', 'asset_add.php', 'asset_edit.php', 'asset_delete.php', 'asset_import.php'], true)],
            ['title' => 'Mutasi PC / Aset IT', 'url' => module_url('asset_mutation.php'), 'icon' => 'bi-arrow-left-right', 'active' => in_array($currentPage, ['asset_mutation.php'], true)],
            ['title' => 'Mutasi Inventaris', 'url' => module_url('inventaris_mutation.php'), 'icon' => 'bi-arrow-left-right', 'active' => in_array($currentPage, ['inventaris_mutation.php', 'asset_mutations.php', 'print_bast.php'], true)],
            ['title' => 'Memo Pengajuan', 'url' => module_url('memo_pengajuan.php'), 'icon' => 'bi-file-earmark-text', 'active' => in_array($currentPage, ['memo_pengajuan.php', 'print_memo.php'], true)],
            ['title' => 'Kepatuhan SDM (OJK)', 'url' => module_url('sdm_kepatuhan.php'), 'icon' => 'bi-shield-check', 'active' => ($currentPage === 'sdm_kepatuhan.php')],
            ['title' => 'Maintenance', 'url' => module_url('audit.php'), 'icon' => 'bi-clipboard-check', 'active' => in_array($currentPage, ['audit.php', 'monthly_history.php', 'history.php', 'maintenance_detail.php'], true)],
        ];
    }

    // Sidebar navigation menu
    $navLinks = [
        'OPERATIONS' => $operationsItems,
        'MONITORING' => $monitoringItems,
        'MANAGEMENT' => $managementItems,
        'SYSTEM' => $systemItems
    ];

    $sidebarMenuHtml = '';
    foreach ($navLinks as $groupName => $items) {
        if (empty($items)) {
            continue;
        }
        $sidebarMenuHtml .= '<div class="sidebar-section-title">'.$groupName.'</div>';
        $sidebarMenuHtml .= '<ul class="nav flex-column mb-3">';
        foreach ($items as $item) {
            $activeClass = $item['active'] ? 'active' : '';
            $sidebarMenuHtml .= '
            <li class="nav-item">
              <a class="sidebar-link '.$activeClass.'" href="'.e($item['url']).'">
                <i class="bi '.e($item['icon']).' sidebar-icon"></i>
                <span class="sidebar-text">'.e($item['title']).'</span>
              </a>
            </li>';
        }
        $sidebarMenuHtml .= '</ul>';
    }

    $sidebarHtml = '
    <aside class="app-sidebar d-none d-lg-flex flex-column" id="appDesktopSidebar">
      <div class="sidebar-brand py-2 px-3">
        <a href="'.e(module_url('dashboard.php')).'" class="d-flex align-items-center justify-content-between text-decoration-none">
          <img src="'.app_logo_url().'" alt="Bank Mitra Logo" class="sidebar-brand-img" style="height: 54px; width: auto; max-width: 175px; object-fit: contain;">
          <span class="badge '.($isHrdRole ? 'bg-warning text-dark border-warning' : 'bg-primary bg-opacity-25 text-white border-primary border-opacity-50').' border" style="font-size: 0.62rem; letter-spacing: 0.05em; font-weight: 700;">'.$brandTag.'</span>
        </a>
      </div>

      <div class="sidebar-content flex-grow-1 custom-scrollbar">
        '.$sidebarMenuHtml.'
      </div>

      <div class="sidebar-footer">
        <div class="sidebar-user-card d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2 overflow-hidden">
            <div class="sidebar-user-avatar '.($isHrdRole ? 'bg-warning text-dark' : '').'">'.$userInitial.'</div>
            <div class="overflow-hidden">
              <div class="sidebar-user-name text-truncate">'.e($userName).'</div>
              <div class="sidebar-user-role text-capitalize">'.e($displayRole).'</div>
            </div>
          </div>
          <a href="'.e(module_url('logout.php')).'" class="sidebar-logout-btn" title="Keluar / Logout">
            <i class="bi bi-box-arrow-right"></i>
          </a>
        </div>
      </div>
    </aside>

    <!-- Offcanvas Mobile Drawer -->
    <div class="offcanvas offcanvas-start bg-navy-dark text-white" tabindex="-1" id="appMobileSidebar" aria-labelledby="appMobileSidebarLabel">
      <div class="offcanvas-header border-bottom border-navy-subtle py-2 px-3">
        <a href="'.e(module_url('dashboard.php')).'" class="d-flex align-items-center gap-2 text-decoration-none">
          <img src="'.app_logo_url().'" alt="Bank Mitra Logo" class="sidebar-brand-img" style="height: 48px; width: auto; max-width: 170px; object-fit: contain;">
        </a>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body custom-scrollbar p-3">
        '.$sidebarMenuHtml.'
      </div>
      <div class="p-3 border-top border-navy-subtle">
        <div class="d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <div class="sidebar-user-avatar '.($isHrdRole ? 'bg-warning text-dark' : '').'">'.$userInitial.'</div>
            <div>
              <div class="text-white small fw-bold">'.e($userName).'</div>
              <div class="text-muted small text-capitalize">'.e($displayRole).'</div>
            </div>
          </div>
          <a href="'.e(module_url('logout.php')).'" class="btn btn-sm btn-outline-danger" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
      </div>
    </div>';

    // Topbar HTML
    $topbarHtml = '
    <header class="app-topbar d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appMobileSidebar" aria-controls="appMobileSidebar">
          <i class="bi bi-list fs-5"></i>
        </button>
        <div class="topbar-context d-none d-sm-flex align-items-center gap-2">
          <span class="tech-label">'.$sectionContext.'</span>
          <span class="text-muted opacity-50">/</span>
          <span class="topbar-page-title text-truncate">'.e($title).'</span>
        </div>
      </div>

      <div class="d-flex align-items-center gap-2 gap-md-3">
        <button type="button" class="topbar-search-btn" data-bs-toggle="modal" data-bs-target="#globalSearchModal" title="Cari cepat aset, serial number, atau teknisi (Ctrl + K)">
          <i class="bi bi-search text-muted"></i>
          <span class="d-none d-md-inline text-muted me-2">Cari aset, serial number...</span>
          <kbd class="d-none d-md-inline-block topbar-kbd">Ctrl K</kbd>
        </button>

        <div class="topbar-status-badge d-none d-md-flex align-items-center gap-2">
          <span class="status-pulse-dot"></span>
          <span class="status-text">Sistem Operasional</span>
        </div>

        <!-- Tombol Antrean Offline PWA (Muncul otomatis jika ada data tersimpan di HP) -->
        <button type="button" class="btn btn-sm btn-warning d-none pwa-queue-btn align-items-center gap-1 fw-bold px-2 py-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPWAQueue" title="Antrean Pemeliharaan Offline">
          <i class="bi bi-cloud-arrow-up-fill"></i>
          <span class="d-none d-sm-inline">Antrean:</span>
          <span class="badge bg-dark text-white pwa-queue-badge">0</span>
        </button>

        <!-- Tombol Pasang PWA -->
        <button type="button" class="btn btn-sm btn-outline-info d-none btn-pwa-install align-items-center gap-1 fw-semibold px-2 py-1" title="Pasang ke Layar Utama HP">
          <i class="bi bi-download"></i>
          <span class="d-none d-md-inline">Pasang App</span>
        </button>

        <!-- Tombol Panduan Cepat Mudah Dipahami -->
        <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1.5 fw-semibold px-2.5 py-1" data-bs-toggle="modal" data-bs-target="#modalPanduanAplikasi" title="Buka Panduan Mudah Penggunaan Aplikasi">
          <i class="bi bi-question-circle-fill"></i>
          <span class="d-none d-md-inline">Panduan</span>
        </button>

        '.($isHrdRole
            ? '<a href="'.e(module_url('dashboard.php', ['tab' => 'kartu'])).'" class="btn btn-sm btn-warning text-dark d-none d-sm-inline-flex align-items-center gap-1 fw-bold px-3 shadow-xs"><i class="bi bi-credit-card-2-front-fill me-1"></i><span>Kartu Inventaris</span></a>'
            : (in_array($role, ['auditor', 'skai'], true)
                ? '<a href="'.e(module_url('audit_trail.php')).'" class="btn btn-sm btn-outline-secondary d-none d-sm-inline-flex align-items-center gap-1 fw-semibold px-3"><i class="bi bi-shield-check me-1"></i><span>Log Aktivitas</span></a>'
                : '<a href="'.e(module_url('asset_add.php')).'" class="btn btn-sm btn-primary d-none d-sm-inline-flex align-items-center gap-1 fw-semibold px-3"><i class="bi bi-plus-lg me-1"></i><span>Tambah Aset</span></a>'
            )
        ).'

        <div class="dropdown">
          <button class="topbar-user-btn dropdown-toggle border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="topbar-user-avatar">'.$userInitial.'</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm border mt-2">
            <li class="px-3 py-2 border-bottom">
              <div class="fw-bold text-dark">'.e($userName).'</div>
              <div class="small text-muted text-capitalize"><i class="bi bi-shield-lock me-1"></i>'.e($displayRole).'</div>
            </li>
            '.($isHrdRole ? '
              <li><a class="dropdown-item py-2" href="'.e(module_url('dashboard.php', ['tab' => 'kartu'])).'"><i class="bi bi-credit-card-2-front me-2 text-warning"></i> Kartu Inventaris</a></li>
              <li><a class="dropdown-item py-2" href="'.e(module_url('asset_gallery.php', ['sub' => 'inventaris'])).'"><i class="bi bi-images me-2 text-primary"></i> Galeri Foto Barang</a></li>
              <li><a class="dropdown-item py-2" href="'.e(module_url('cabang_admin.php')).'"><i class="bi bi-buildings me-2 text-success"></i> Kantor Cabang</a></li>
            ' : (in_array($role, ['auditor', 'skai'], true) ? '
              <li><a class="dropdown-item py-2" href="'.e(module_url('audit.php')).'"><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i> Laporan Audit</a></li>
              <li><a class="dropdown-item py-2" href="'.e(module_url('monthly_history.php')).'"><i class="bi bi-calendar3 me-2 text-info"></i> Riwayat Bulanan</a></li>
              <li><a class="dropdown-item py-2" href="'.e(module_url('audit_trail.php')).'"><i class="bi bi-shield-check me-2 text-warning"></i> Log Aktivitas (Audit)</a></li>
            ' : '
              <li><a class="dropdown-item py-2" href="'.e(module_url('assets.php')).'"><i class="bi bi-pc-display me-2 text-primary"></i> Data Komputer</a></li>
              <li><a class="dropdown-item py-2" href="'.e(module_url('scanner.php')).'"><i class="bi bi-qr-code-scan me-2 text-info"></i> Scanner QR</a></li>
              '.($isAdminUser ? '<li><a class="dropdown-item py-2" href="'.e(module_url('audit_trail.php')).'"><i class="bi bi-shield-check me-2 text-warning"></i> Log Aktivitas (Audit)</a></li>' : '').'
            ')).'
            <li><button class="dropdown-item py-2 text-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalPanduanAplikasi"><i class="bi bi-question-circle-fill me-2"></i> Bantuan & Panduan</button></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item py-2 text-danger" href="'.e(module_url('logout.php')).'"><i class="bi bi-box-arrow-right me-2"></i> Keluar</a></li>
          </ul>
        </div>
      </div>
    </header>';

    // Public header if nav is disabled
    $publicHeaderHtml = '
    <header class="public-topbar py-2 px-3 px-md-4 bg-white border-bottom shadow-sm d-flex align-items-center justify-content-between">
      <a href="'.e(module_url('dashboard.php')).'" class="d-flex align-items-center gap-3 text-decoration-none">
        <img src="'.app_logo_url().'" alt="Bank Mitra Logo" class="sidebar-brand-img" style="height: 48px; width: auto; object-fit: contain;">
        <div class="border-start ps-3 d-none d-sm-block">
          <div class="tech-label" style="font-size: 0.68rem; color: #667085;">IT OPERATIONS & INVENTARIS UMUM</div>
        </div>
      </a>
      <div class="d-flex align-items-center gap-2">
        <!-- Tombol Antrean Offline PWA -->
        <button type="button" class="btn btn-sm btn-warning d-none pwa-queue-btn align-items-center gap-1 fw-bold px-2 py-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPWAQueue" title="Antrean Pemeliharaan Offline">
          <i class="bi bi-cloud-arrow-up-fill"></i>
          <span class="d-none d-sm-inline">Antrean:</span>
          <span class="badge bg-dark text-white pwa-queue-badge">0</span>
        </button>

        <button type="button" class="btn btn-sm btn-outline-info d-none btn-pwa-install align-items-center gap-1 fw-semibold px-2 py-1" title="Pasang ke Layar Utama HP">
          <i class="bi bi-download"></i>
          <span class="d-none d-sm-inline">Pasang App</span>
        </button>

        '.(is_logged_in() 
            ? '<a href="'.e(module_url('dashboard.php')).'" class="btn btn-sm btn-outline-primary"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>' 
            : '<a href="'.e(module_url('login.php')).'" class="btn btn-sm btn-outline-secondary"><i class="bi bi-person-circle me-1"></i> Login Petugas</a>').'
      </div>
    </header>';

    echo '<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>'.e($title).' · IT Operations Bank Mitra</title>
<link rel="manifest" href="'.e(module_url('manifest.webmanifest')).'">
<meta name="theme-color" content="#0D2748">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="QR Maint">
<link rel="apple-touch-icon" href="'.e(module_url('pwa_icons.php', ['size' => 192])).'">
<link rel="icon" type="image/png" href="'.app_logo_url().'">
<link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
<link rel="dns-prefetch" href="https://fonts.googleapis.com">
<link rel="dns-prefetch" href="https://fonts.gstatic.com">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
/* High-Performance Rendering & Hardware Acceleration */
*, *::before, *::after {
  -webkit-tap-highlight-color: transparent;
  box-sizing: border-box;
}
img {
  content-visibility: auto;
}
table tbody tr {
  contain: layout style;
}

/* BANKING IT OPERATIONS CENTER DESIGN SYSTEM - LUXURY ENTERPRISE EDITION */
:root {
  --navy-deep: #051329;
  --navy-primary: #0A1E38;
  --navy-subtle: #162E52;
  --blue-corporate: #1D4ED8;
  --blue-accent: #2563EB;
  --blue-soft: #EFF6FF;
  --bg-app: #F8FAFC;
  --surface-card: #FFFFFF;
  --border-subtle: #E2E8F0;
  --border-strong: #CBD5E1;
  --text-primary: #0F172A;
  --text-secondary: #475569;
  --text-muted: #94A3B8;
  --status-success: #16A34A;
  --status-success-bg: #F0FDF4;
  --status-warning: #D97706;
  --status-warning-bg: #FFFBEB;
  --status-danger: #DC2626;
  --status-danger-bg: #FEF2F2;
  --status-info: #0284C7;
  --status-info-bg: #F0F9FF;
  --radius-xs: 5px;
  --radius-sm: 8px;
  --radius-md: 10px;
  --radius-lg: 14px;
  --radius-xl: 18px;
  --shadow-subtle: 0 1px 3px rgba(15, 23, 42, 0.05), 0 1px 2px rgba(15, 23, 42, 0.03);
  --shadow-card: 0 4px 14px -2px rgba(15, 23, 42, 0.06), 0 2px 4px -1px rgba(15, 23, 42, 0.03);
  --shadow-hover: 0 12px 28px -4px rgba(15, 23, 42, 0.1), 0 4px 8px -2px rgba(15, 23, 42, 0.04);
}

/* PWA Offline & Network Banner Styles */
.pwa-network-banner {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 99999;
  font-size: 0.82rem;
  padding: 8px 16px;
  text-align: center;
  font-weight: 600;
  transition: all 0.3s ease;
}
.pwa-network-offline {
  background-color: #B42318;
  color: #FFFFFF;
  box-shadow: 0 4px 12px rgba(180, 35, 24, 0.35);
}
.pwa-network-online {
  background-color: #16803C;
  color: #FFFFFF;
  box-shadow: 0 4px 12px rgba(22, 128, 60, 0.35);
}
.pwa-sync-toast {
  position: fixed;
  bottom: 24px;
  right: 24px;
  background: #08182F;
  color: #FFFFFF;
  border: 1px solid #22C55E;
  padding: 12px 18px;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.3);
  font-size: 0.85rem;
  font-weight: 600;
  z-index: 99998;
  opacity: 0;
  transform: translateY(20px);
  transition: all 0.3s ease;
  pointer-events: none;
}
.pwa-sync-toast.show {
  opacity: 1;
  transform: translateY(0);
  pointer-events: auto;
}

* { box-sizing: border-box; }

body, input, button, select, textarea, optgroup,
.btn, .form-control, .form-select, .form-check-label,
.card, .modal, .dropdown-menu, .dropdown-item, .popover,
.tooltip, .nav, .nav-link, .table, .badge, .badge-chip,
.alert, .toast, .breadcrumb, .pagination, .page-link {
  font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

body {
  margin: 0;
  padding: 0;
  background-color: var(--bg-app);
  background-image: radial-gradient(at 50% 0%, #EEF4FF 0%, #F8FAFC 60%, #F1F5F9 100%);
  background-attachment: fixed;
  color: var(--text-primary);
  min-height: 100vh;
}

h1, h2, h3, h4, h5, h6,
.h1, .h2, .h3, .h4, .h5, .h6 {
  font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
  color: var(--text-primary);
  font-weight: 700;
  letter-spacing: -0.025em;
}

.text-white,
.text-white h1, .text-white h2, .text-white h3, .text-white h4, .text-white h5, .text-white h6,
.text-white .modal-title,
.modal-header.text-white .modal-title,
.modal-header.bg-primary .modal-title,
.modal-header.bg-dark .modal-title,
.modal-header.bg-navy-dark .modal-title,
.modal-header.bg-danger .modal-title,
.modal-header.bg-success .modal-title {
  color: #ffffff !important;
}

/* Base Layout Framework */
.app-layout-wrapper {
  display: flex;
  min-height: 100vh;
  width: 100%;
}

.app-sidebar {
  width: 256px;
  min-width: 256px;
  background: linear-gradient(180deg, #051329 0%, #0A1E38 60%, #071526 100%);
  border-right: 1px solid rgba(255, 255, 255, 0.07);
  box-shadow: 4px 0 24px rgba(0, 0, 0, 0.12);
  height: 100vh;
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  z-index: 1020;
}

@media (min-width: 992px) {
  .app-main-viewport {
    margin-left: 256px;
    width: calc(100% - 256px);
  }
}

@media (max-width: 991.98px) {
  .app-main-viewport {
    margin-left: 0;
    width: 100%;
  }
}

.bg-navy-dark {
  background-color: var(--navy-deep) !important;
}

.border-navy-subtle {
  border-color: rgba(255, 255, 255, 0.08) !important;
}

.sidebar-brand {
  padding: 18px 20px 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.sidebar-brand-img {
  image-rendering: auto;
  object-fit: contain;
}

.sidebar-brand-badge {
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, #1E40AF 0%, #3B82F6 100%);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #FFFFFF;
  font-size: 1.2rem;
}

.sidebar-brand-title {
  font-size: 1.02rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  color: #FFFFFF;
  line-height: 1.2;
}

.sidebar-brand-sub {
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: #94A3B8;
  text-transform: uppercase;
}

.sidebar-content {
  overflow-y: auto;
  padding: 16px 12px;
}

.sidebar-section-title {
  font-size: 0.66rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.09em;
  color: #64748B;
  padding: 10px 12px 6px;
}

.sidebar-link {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 9px 14px;
  color: #94A3B8;
  text-decoration: none;
  font-size: 0.86rem;
  font-weight: 500;
  border-radius: var(--radius-sm);
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  margin-bottom: 3px;
  border-left: 3px solid transparent;
}

.sidebar-link:hover {
  color: #FFFFFF;
  background-color: rgba(255, 255, 255, 0.08);
  transform: translateX(2px);
}

.sidebar-link.active {
  color: #FFFFFF;
  background: linear-gradient(90deg, rgba(37, 99, 235, 0.24) 0%, rgba(37, 99, 235, 0.06) 100%);
  border-left-color: #3B82F6;
  font-weight: 600;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.sidebar-link.active .sidebar-icon {
  color: #60A5FA;
}

.sidebar-icon {
  font-size: 1.05rem;
  width: 20px;
  text-align: center;
}

.sidebar-footer {
  padding: 12px 14px;
  border-top: 1px solid var(--navy-subtle);
  background: rgba(0, 0, 0, 0.15);
}

.sidebar-user-card {
  width: 100%;
}

.sidebar-user-avatar {
  width: 32px;
  height: 32px;
  border-radius: var(--radius-sm);
  background-color: var(--blue-corporate);
  color: #FFFFFF;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.85rem;
  flex-shrink: 0;
}

.sidebar-user-name {
  font-size: 0.82rem;
  font-weight: 600;
  color: #FFFFFF;
  max-width: 125px;
}

.sidebar-user-role {
  font-size: 0.68rem;
  color: #98A2B3;
}

.sidebar-logout-btn {
  color: #98A2B3;
  padding: 6px;
  border-radius: var(--radius-xs);
  transition: color 0.15s ease;
}

.sidebar-logout-btn:hover {
  color: #F87171;
}

/* Main Container Area */
.app-main-viewport {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.app-topbar {
  height: 66px;
  background-color: rgba(255, 255, 255, 0.88);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid rgba(226, 232, 240, 0.85);
  position: sticky;
  top: 0;
  z-index: 1010;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.topbar-page-title {
  font-size: 0.98rem;
  font-weight: 700;
  color: var(--text-primary);
  max-width: 360px;
  letter-spacing: -0.01em;
}

.topbar-search-btn {
  display: flex;
  align-items: center;
  gap: 9px;
  background-color: #FFFFFF;
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-sm);
  padding: 7px 14px;
  font-size: 0.82rem;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.topbar-search-btn:hover {
  border-color: #3B82F6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
  background-color: #FFFFFF;
}

.topbar-kbd {
  background-color: #F1F5F9;
  border: 1px solid var(--border-subtle);
  border-radius: 4px;
  box-shadow: 0 1px 1px rgba(0,0,0,0.05);
  font-size: 0.68rem;
  font-weight: 700;
  color: var(--text-muted);
  padding: 2px 6px;
}

.topbar-status-badge {
  background-color: var(--status-success-bg);
  border: 1px solid #BBF7D0;
  border-radius: 20px;
  padding: 5px 12px;
  font-size: 0.74rem;
  font-weight: 600;
  color: var(--status-success);
  box-shadow: 0 1px 2px rgba(22, 163, 74, 0.08);
}

.status-pulse-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background-color: var(--status-success);
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.2);
  animation: pulseDot 2s infinite ease-in-out;
}

@keyframes pulseDot {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.15); opacity: 0.75; }
}

.topbar-user-btn {
  background: none;
  padding: 0;
  cursor: pointer;
  border: none;
  display: flex;
  align-items: center;
}

.topbar-user-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: linear-gradient(135deg, #1E40AF 0%, #3B82F6 100%);
  color: #FFFFFF;
  border: 2px solid #FFFFFF;
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.88rem;
  transition: all 0.2s ease;
}

.topbar-user-btn:hover .topbar-user-avatar {
  transform: scale(1.05);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
}

.app-content-container {
  padding: 24px 20px 48px;
  max-width: 1440px;
  width: 100%;
  margin: 0 auto;
}

@media (min-width: 768px) {
  .app-content-container {
    padding: 28px 32px 60px;
  }
}

@media (max-width: 576px) {
  html, body {
    overflow-x: clip;
    max-width: 100%;
  }
  .app-content-container {
    padding: 12px 12px 36px;
  }
  .app-topbar, .public-topbar {
    padding-left: 12px !important;
    padding-right: 12px !important;
  }
  .card {
    border-radius: var(--radius-md) !important;
  }
}

/* Design Tokens & Overrides */
.tech-label {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--text-secondary);
}

.card {
  background-color: var(--surface-card);
  border: 1px solid rgba(226, 232, 240, 0.85) !important;
  border-radius: var(--radius-lg) !important;
  box-shadow: var(--shadow-card) !important;
  transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.22s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease;
}

.card:hover {
  border-color: #CBD5E1 !important;
}

/* Metric Cards - Symmetrical & High Elevation */
.card-metric {
  padding: 22px 24px !important;
  border-left: 4px solid var(--blue-corporate) !important;
  border-radius: var(--radius-lg) !important;
  display: flex !important;
  flex-direction: column !important;
  justify-content: space-between !important;
  min-height: 116px !important;
  position: relative !important;
  overflow: hidden !important;
  background: #FFFFFF !important;
}

.card-metric::after {
  content: "";
  position: absolute;
  top: 0;
  right: 0;
  width: 75px;
  height: 75px;
  background: radial-gradient(circle, rgba(37, 99, 235, 0.05) 0%, transparent 70%);
  pointer-events: none;
}

.card-metric:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-hover) !important;
}

.metric-value {
  font-size: 1.95rem !important;
  font-weight: 700 !important;
  letter-spacing: -0.03em !important;
  font-feature-settings: "tnum" 1;
  color: var(--text-primary);
  line-height: 1.15;
}

.metric-label {
  font-size: 0.73rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.06em !important;
  color: var(--text-secondary);
  margin-top: 5px;
}

/* Luxury Button Standards */
.btn {
  font-size: 0.85rem;
  font-weight: 600;
  border-radius: var(--radius-sm);
  padding: 8px 16px;
  transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.btn-primary {
  background: linear-gradient(135deg, #1E40AF 0%, #2563EB 100%) !important;
  border: 1px solid #1D4ED8 !important;
  color: #FFFFFF !important;
  box-shadow: 0 2px 6px rgba(37, 99, 235, 0.28);
}

.btn-primary:hover, .btn-primary:focus {
  background: linear-gradient(135deg, #1D3A9C 0%, #1D4ED8 100%) !important;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
  transform: translateY(-1px);
}

.btn-success {
  background: linear-gradient(135deg, #15803D 0%, #16A34A 100%) !important;
  border: 1px solid #15803D !important;
  color: #FFFFFF !important;
  box-shadow: 0 2px 6px rgba(22, 163, 74, 0.25);
}

.btn-success:hover {
  background: linear-gradient(135deg, #14532D 0%, #15803D 100%) !important;
  box-shadow: 0 4px 12px rgba(22, 163, 74, 0.35);
  transform: translateY(-1px);
}

.btn-secondary, .btn-light {
  background-color: #FFFFFF !important;
  border-color: var(--border-subtle) !important;
  color: var(--text-primary) !important;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.btn-secondary:hover, .btn-light:hover {
  background-color: #F8FAFC !important;
  border-color: var(--border-strong) !important;
  transform: translateY(-1px);
}

.btn-outline-primary {
  color: var(--blue-corporate) !important;
  border-color: #BFDBFE !important;
  background-color: #FFFFFF !important;
}

.btn-outline-primary:hover {
  background: linear-gradient(135deg, #1E40AF 0%, #2563EB 100%) !important;
  color: #FFFFFF !important;
  border-color: transparent !important;
  box-shadow: 0 3px 10px rgba(37, 99, 235, 0.3);
  transform: translateY(-1px);
}

/* Form Controls */
.form-control, .form-select {
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  padding: 8px 14px;
  font-size: 0.88rem;
  color: var(--text-primary);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
  background-color: #FFFFFF;
}

.form-control:focus, .form-select:focus {
  border-color: var(--blue-accent) !important;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15) !important;
  outline: none;
}

/* Luxury Table Design */
.table {
  font-size: 0.86rem;
  color: var(--text-primary);
  margin-bottom: 0;
  vertical-align: middle;
}

.table thead th {
  background: linear-gradient(180deg, #F8FAFC 0%, #F1F5F9 100%) !important;
  color: #475569 !important;
  font-weight: 700;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  border-bottom: 1.5px solid #E2E8F0;
  padding: 12px 16px;
  white-space: nowrap;
}

.table tbody td {
  padding: 12px 16px;
  border-bottom: 1px solid #F1F5F9;
  vertical-align: middle;
}

.table-hover tbody tr {
  transition: background-color 0.15s ease;
}

.table-hover tbody tr:hover {
  background-color: rgba(37, 99, 235, 0.035) !important;
}

/* Badge Chips */
.badge-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 8px;
  border-radius: var(--radius-xs);
  font-size: 0.74rem;
  font-weight: 600;
}

.chip-success { background: var(--status-success-bg); color: var(--status-success); border: 1px solid #A6F4C5; }
.chip-warning { background: var(--status-warning-bg); color: var(--status-warning); border: 1px solid #FEDF89; }
.chip-danger  { background: var(--status-danger-bg); color: var(--status-danger); border: 1px solid #FECDCA; }
.chip-primary { background: var(--blue-soft); color: var(--blue-corporate); border: 1px solid #BFDBFE; }
.chip-secondary { background: #F2F4F7; color: #344054; border: 1px solid #D0D5DD; }

/* Status Dot System */
.status-dot {
  display: inline-block;
  width: 7px;
  height: 7px;
  border-radius: 50%;
}
.status-dot.operational { background-color: var(--status-success); }
.status-dot.warning     { background-color: var(--status-warning); }
.status-dot.critical    { background-color: var(--status-danger); }
.status-dot.repair      { background-color: var(--blue-accent); }
.status-dot.offline     { background-color: var(--text-muted); }

/* Branch Navigation Pills (Global Switcher) */
.branch-nav-bar {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 8px;
  margin-bottom: 20px;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: thin;
}
.branch-nav-bar::-webkit-scrollbar {
  height: 4px;
}
.branch-nav-bar::-webkit-scrollbar-thumb {
  background: rgba(0, 59, 115, 0.2);
  border-radius: 4px;
}
.branch-nav-pill {
  white-space: nowrap;
  padding: 8px 16px;
  font-size: 0.83rem;
  font-weight: 500;
  border-radius: 8px;
  background: #FFFFFF;
  border: 1px solid #CBD5E1;
  color: #334155;
  text-decoration: none !important;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
  box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}
.branch-nav-pill:hover {
  background: #F1F5F9;
  color: #0F172A;
  border-color: #94A3B8;
  transform: translateY(-1px);
  box-shadow: 0 2px 5px rgba(0,0,0,0.06);
}
.branch-nav-pill.active {
  background: var(--blue-corporate, #003B73) !important;
  border-color: var(--blue-corporate, #003B73) !important;
  color: #FFFFFF !important;
  font-weight: 600;
  box-shadow: 0 3px 8px rgba(0, 59, 115, 0.25);
}
.branch-nav-pill.active i {
  color: #93C5FD !important;
}
.branch-nav-pill.active .badge {
  background: rgba(255, 255, 255, 0.25) !important;
  color: #FFFFFF !important;
}

/* Progress Bar Minimalist */
.progress {
  background-color: #E2E8F0;
  border-radius: 4px;
  overflow: hidden;
}

.progress-bar {
  background-color: var(--blue-corporate);
}

/* Custom Scrollbars */
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(255,255,255,0.15);
  border-radius: 4px;
}

#top-progress-bar {
  position: fixed;
  top: 0;
  left: 0;
  height: 3px;
  background: linear-gradient(90deg, #124E96, #2E7CF6, #00D2FF);
  box-shadow: 0 0 10px rgba(46, 124, 246, 0.8), 0 0 4px rgba(0, 210, 255, 0.6);
  z-index: 99999;
  transition: width 0.25s cubic-bezier(0.1, 0.85, 0.25, 1), opacity 0.2s ease;
  width: 0%;
  opacity: 0;
  pointer-events: none;
}

#top-progress-bar.active {
  opacity: 1;
}

/* Floating Page Transition Loader */
.page-nav-loader {
  position: fixed;
  top: 16px;
  right: 20px;
  z-index: 99998;
  background: rgba(13, 39, 72, 0.94);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  color: #ffffff;
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 0.76rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  display: flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(255, 255, 255, 0.12);
  pointer-events: none;
  animation: pageLoaderFadeIn 0.2s ease-out;
}

.page-nav-spinner {
  width: 13px;
  height: 13px;
  border: 2px solid rgba(255, 255, 255, 0.2);
  border-top-color: #00D2FF;
  border-radius: 50%;
  animation: pageLoaderSpin 0.65s infinite linear;
}

@keyframes pageLoaderSpin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

@keyframes pageLoaderFadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}

@media print {
  .no-print, .app-sidebar, .app-topbar, .public-topbar, #top-progress-bar, .page-nav-loader { display: none !important; }
  .app-main-viewport { margin-left: 0 !important; width: 100% !important; }
  .app-content-container { padding: 0 !important; max-width: 100% !important; }
  body { background: #FFFFFF !important; }
}

/* ============================================================
   PREMIUM GLOBAL UI UPGRADE — ALL PAGES
   ============================================================ */

/* ── Fix: teks di dalam gradient/dark header selalu putih ── */
[class*="bg-navy"],
[class*="bg-primary"],
[class*="bg-dark"],
[style*="background: linear-gradient"],
[style*="background:linear-gradient"] {
  color: inherit;
}
.mutation-page-header,
.mutation-page-header * {
  color: #ffffff !important;
}
.mutation-page-header .step-badge,
.mutation-page-header .type-switcher * {
  color: inherit !important;
}

/* ── Card Premium — elevasi saat hover ── */
.card {
  transition: box-shadow 0.25s cubic-bezier(.4,0,.2,1), transform 0.25s cubic-bezier(.4,0,.2,1), border-color 0.2s !important;
}
.card:hover {
  box-shadow: 0 8px 28px rgba(15,23,42,0.11), 0 2px 8px rgba(15,23,42,0.05) !important;
  border-color: #C7D7F0 !important;
}
.card-metric:hover {
  transform: translateY(-3px) !important;
  box-shadow: 0 12px 32px rgba(15,23,42,0.13) !important;
}

/* ── Table — lebih bersih & readable ── */
.table thead th {
  font-size: 0.71rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.07em !important;
  text-transform: uppercase !important;
  color: #475569 !important;
  background: linear-gradient(180deg, #F8FAFC 0%, #F1F5F9 100%) !important;
  padding: 13px 16px !important;
  border-bottom: 2px solid #E2E8F0 !important;
}
.table tbody td {
  padding: 13px 16px !important;
  border-bottom: 1px solid #F1F5F9 !important;
  vertical-align: middle !important;
  color: #1E293B !important;
}
.table tbody tr:hover td {
  background-color: #EFF6FF !important;
}
.table tbody tr:last-child td {
  border-bottom: none !important;
}

/* ── Badge Improvements ── */
.badge {
  font-weight: 600 !important;
  letter-spacing: 0.03em;
  padding: 4px 10px !important;
  border-radius: 6px !important;
}
.badge.rounded-pill { border-radius: 20px !important; }

/* ── Alert Styling ── */
.alert {
  border-radius: 12px !important;
  border-left-width: 4px !important;
  font-size: 0.875rem;
}
.alert-success { background: #F0FDF4 !important; border-color: #86EFAC !important; color: #15803D !important; }
.alert-danger  { background: #FEF2F2 !important; border-color: #FCA5A5 !important; color: #DC2626 !important; }
.alert-warning { background: #FFFBEB !important; border-color: #FCD34D !important; color: #B45309 !important; }
.alert-info    { background: #F0F9FF !important; border-color: #7DD3FC !important; color: #0369A1 !important; }

/* ── Dropdown Menu ── */
.dropdown-menu {
  border-radius: 12px !important;
  border: 1px solid #E2E8F0 !important;
  box-shadow: 0 8px 24px rgba(15,23,42,0.12), 0 2px 8px rgba(15,23,42,0.05) !important;
  padding: 6px !important;
}
.dropdown-item {
  border-radius: 8px !important;
  font-size: 0.86rem !important;
  padding: 9px 14px !important;
  transition: background 0.15s !important;
}
.dropdown-item:hover {
  background-color: #EFF6FF !important;
  color: #1D4ED8 !important;
}
.dropdown-divider { margin: 5px 0 !important; }

/* ── Modal Premium ── */
.modal-content {
  border-radius: 18px !important;
  border: none !important;
  box-shadow: 0 20px 64px rgba(15,23,42,0.2) !important;
  overflow: hidden;
}
.modal-header {
  padding: 18px 24px !important;
}
.modal-body {
  padding: 20px 24px !important;
}
.modal-footer {
  padding: 14px 24px !important;
  background: #F8FAFC !important;
  border-top: 1px solid #E2E8F0 !important;
}

/* ── Input Group ── */
.input-group-text {
  background: #F8FAFC !important;
  border-color: #CBD5E1 !important;
  color: #64748B;
  font-size: 0.875rem;
}

/* ── Pagination ── */
.page-link {
  border-radius: 8px !important;
  margin: 0 2px;
  border: 1px solid #E2E8F0 !important;
  color: #475569 !important;
  font-weight: 600;
  font-size: 0.84rem;
  transition: all 0.18s !important;
}
.page-link:hover {
  background: #EFF6FF !important;
  color: #1D4ED8 !important;
  border-color: #BFDBFE !important;
}
.page-item.active .page-link {
  background: linear-gradient(135deg, #1E40AF, #2563EB) !important;
  border-color: transparent !important;
  color: #fff !important;
  box-shadow: 0 3px 10px rgba(37,99,235,0.3) !important;
}

/* ── Topbar Search Button ── */
.topbar-search-btn {
  border-radius: 10px !important;
}

/* ── Sidebar upgrade ── */
.sidebar-link {
  border-radius: 10px !important;
}
.sidebar-link.active {
  background: linear-gradient(90deg, rgba(59,130,246,0.22) 0%, rgba(59,130,246,0.07) 100%) !important;
  border-left-color: #60A5FA !important;
}

/* ── Form Label Global ── */
label.form-label,
.form-label {
  font-size: 0.73rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #475569 !important;
  margin-bottom: 6px;
}

/* ── Smooth page animations (aman untuk layout) ── */
.mutation-card {
  animation: pageFadeIn 0.2s ease both;
}
@keyframes pageFadeIn {
  from { opacity: 0; }
  to   { opacity: 1; }
}

/* ── Responsive tweaks mobile & grid protection ── */
.branch-nav-bar {
  max-width: 100%;
  overflow-x: auto;
  white-space: nowrap;
}
.row {
  margin-right: -0.75rem;
  margin-left: -0.75rem;
}
.app-content-container {
  overflow-x: hidden;
}
@media (max-width: 576px) {
  .mutation-page-header { border-radius: 14px !important; padding: 18px 16px !important; }
  .modal-content { border-radius: 16px !important; }
  .card { border-radius: 14px !important; }
  .btn { font-size: 0.82rem; }
}
</style>

'.$extraHead.'
</head>
<body>
<div id="top-progress-bar"></div>
<div id="page-nav-loader" class="page-nav-loader" style="display: none;">
  <div class="page-nav-spinner"></div>
  <span>Memuat...</span>
</div>

'.($showNav ? '
<div class="app-layout-wrapper">
  '.$sidebarHtml.'
  <div class="app-main-viewport">
    '.$topbarHtml.'
    <main class="app-content-container">
      '.$content.'
    </main>
  </div>
</div>
' : '
<div>
  '.$publicHeaderHtml.'
  <main class="app-content-container" style="max-width: 960px;">
    '.$content.'
  </main>
</div>
').'

<!-- Global Search Palette (Ctrl + K) -->
<div class="modal fade" id="globalSearchModal" tabindex="-1" aria-labelledby="globalSearchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border shadow">
      <div class="modal-header py-2 px-3 border-bottom bg-light">
        <div class="d-flex align-items-center gap-2 w-100">
          <i class="bi bi-search text-muted"></i>
          <input type="text" id="globalSearchInput" class="form-control border-0 shadow-none bg-transparent" placeholder="Ketik kode aset, nama komputer, atau kata kunci..." autocomplete="off">
        </div>
        <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-3" id="globalSearchResults" style="max-height: 380px; overflow-y: auto;">
        <div class="small text-muted text-uppercase fw-bold mb-2" style="font-size: 0.68rem; letter-spacing: 0.06em;">Navigasi Cepat</div>
        <div class="list-group list-group-flush border-0">
          <a href="'.e(module_url('dashboard.php')).'" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2 px-2 border-0 rounded">
            <i class="bi bi-speedometer2 text-primary"></i> <span>Dashboard IT Operations</span>
          </a>
          <a href="'.e(module_url('assets.php')).'" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2 px-2 border-0 rounded">
            <i class="bi bi-pc-display text-primary"></i> <span>Asset Registry (Data Komputer)</span>
          </a>
          <a href="'.e(module_url('scanner.php')).'" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2 px-2 border-0 rounded">
            <i class="bi bi-qr-code-scan text-primary"></i> <span>Buka Scanner Kamera QR</span>
          </a>
          <a href="'.e(module_url('asset_add.php')).'" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2 px-2 border-0 rounded">
            <i class="bi bi-plus-circle-fill text-success"></i> <span>Tambah Perangkat Komputer Baru</span>
          </a>
          <a href="'.e(module_url('audit.php')).'" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2 px-2 border-0 rounded">
            <i class="bi bi-clipboard-check text-info"></i> <span>Rekap Audit & Laporan Maintenance</span>
          </a>
        </div>
      </div>
      <div class="modal-footer py-2 px-3 bg-light border-top d-flex justify-content-between">
        <span class="small text-muted" style="font-size: 0.75rem;"><kbd>Esc</kbd> untuk menutup</span>
        <span class="small text-muted" style="font-size: 0.75rem;">PT. BPR Mitratama Arthabuana</span>
      </div>
    </div>
  </div>
</div>

<!-- Modal Panduan Penggunaan Sistem (Ramah Pemula & Mudah Dipahami) -->
<div class="modal fade" id="modalPanduanAplikasi" tabindex="-1" aria-labelledby="modalPanduanAplikasiLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header py-3 px-4 text-white" style="background: linear-gradient(135deg, #0A192F 0%, #172A46 100%); border-bottom: 2px solid #D97706;">
        <div class="d-flex align-items-center gap-3">
          <div class="rounded-circle d-flex align-items-center justify-content-center text-warning" style="width: 44px; height: 44px; background: rgba(245, 158, 11, 0.15);">
            <i class="bi bi-lightbulb-fill fs-4"></i>
          </div>
          <div>
            <h5 class="modal-title fw-bold mb-0" id="modalPanduanAplikasiLabel">Panduan Singkat Alur Sistem</h5>
            <div class="text-white-50 small" style="font-size: 0.78rem;">Petunjuk langkah-demi-langkah mudah dipahami sesuai peranan Anda</div>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 bg-light">
        <!-- Tab Pill Navigasi Panduan -->
        <ul class="nav nav-pills nav-fill mb-4 p-1 bg-white rounded-3 border shadow-xs" id="pillsPanduanTab" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link '.(!$isHrdRole ? 'active fw-bold' : 'text-secondary').' py-2" id="pill-panduan-it-tab" data-bs-toggle="pill" data-bs-target="#pill-panduan-it" type="button" role="tab">
              <i class="bi bi-tools me-1 text-primary"></i> 1. Teknisi IT
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link '.($isHrdRole ? 'active fw-bold' : 'text-secondary').' py-2" id="pill-panduan-sdm-tab" data-bs-toggle="pill" data-bs-target="#pill-panduan-sdm" type="button" role="tab">
              <i class="bi bi-person-workspace me-1 text-warning"></i> 2. SDM &amp; Umum
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link text-secondary py-2" id="pill-panduan-audit-tab" data-bs-toggle="pill" data-bs-target="#pill-panduan-audit" type="button" role="tab">
              <i class="bi bi-shield-check me-1 text-success"></i> 3. Auditor / SKAI
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link text-secondary py-2" id="pill-panduan-status-tab" data-bs-toggle="pill" data-bs-target="#pill-panduan-status" type="button" role="tab">
              <i class="bi bi-palette me-1 text-info"></i> 4. Arti Warna &amp; Checklist
            </button>
          </li>
        </ul>

        <div class="tab-content" id="pillsPanduanTabContent">
          <!-- Panduan 1: Teknisi IT -->
          <div class="tab-pane fade '.(!$isHrdRole ? 'show active' : '').'" id="pill-panduan-it" role="tabpanel">
            <div class="card border-0 shadow-sm p-4 bg-white rounded-3 mb-3">
              <h6 class="fw-bold text-dark mb-3"><i class="bi bi-play-circle-fill text-primary me-2"></i>Alur Pemeriksaan Komputer di Lapangan:</h6>
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="p-3 border rounded-3 h-100 bg-light">
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <span class="badge rounded-pill bg-primary text-white px-2.5 py-1">Langkah 1</span>
                      <strong class="text-dark small">Pindai Stiker QR</strong>
                    </div>
                    <p class="text-muted small mb-0">Buka menu <strong>Scanner QR</strong> pada smartphone atau klik ikon scanner di laptop. Arahkan kamera ke stiker QR pada CPU/monitor.</p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="p-3 border rounded-3 h-100 bg-light">
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <span class="badge rounded-pill bg-primary text-white px-2.5 py-1">Langkah 2</span>
                      <strong class="text-dark small">Isi 9 Butir Checklist</strong>
                    </div>
                    <p class="text-muted small mb-0">Beri tanda centang pada 9 butir checklist fisik & software. Jika ada komponen rusak, pilih status <strong>Temuan / Kendala</strong>.</p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="p-3 border rounded-3 h-100 bg-light">
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <span class="badge rounded-pill bg-primary text-white px-2.5 py-1">Langkah 3</span>
                      <strong class="text-dark small">Verifikasi Wajah Petugas</strong>
                    </div>
                    <p class="text-muted small mb-0">Arahkan kamera ke wajah Anda untuk konfirmasi kehadiran fisik teknisi (mencegah titip checklist). Sistem AI akan memverifikasi kecocokan.</p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="p-3 border rounded-3 h-100 bg-light">
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <span class="badge rounded-pill bg-success text-white px-2.5 py-1">Langkah 4</span>
                      <strong class="text-dark small">Selesai &amp; Paraf Otomatis</strong>
                    </div>
                    <p class="text-muted small mb-0">Klik <strong>Simpan Pemeliharaan</strong>. Nama paraf Anda otomatis tercatat pada Kartu Kontrol IT 12 Bulan dan live di dashboard.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Panduan 2: SDM & Umum -->
          <div class="tab-pane fade '.($isHrdRole ? 'show active' : '').'" id="pill-panduan-sdm" role="tabpanel">
            <div class="card border-0 shadow-sm p-4 bg-white rounded-3 mb-3">
              <h6 class="fw-bold text-dark mb-3"><i class="bi bi-credit-card-2-front-fill text-warning me-2"></i>Alur Pengelolaan Kartu Inventaris Barang Bank:</h6>
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="p-3 border rounded-3 h-100 bg-light">
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <span class="badge rounded-pill bg-warning text-dark px-2.5 py-1">Langkah 1</span>
                      <strong class="text-dark small">Buka Tab Kartu Inventaris</strong>
                    </div>
                    <p class="text-muted small mb-0">Buka menu <strong>Kartu Inventaris</strong> untuk melihat seluruh data barang inventaris kantor (kursi, AC, genset, brankas, printer, dll).</p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="p-3 border rounded-3 h-100 bg-light">
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <span class="badge rounded-pill bg-warning text-dark px-2.5 py-1">Langkah 2</span>
                      <strong class="text-dark small">Tambah Data / Import Excel</strong>
                    </div>
                    <p class="text-muted small mb-0">Klik tombol <strong>+ Tambah Data Kartu</strong> untuk menginput 1 kartu baru, atau tombol <strong>Import FBD3</strong> untuk import massal Excel.</p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="p-3 border rounded-3 h-100 bg-light">
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <span class="badge rounded-pill bg-warning text-dark px-2.5 py-1">Langkah 3</span>
                      <strong class="text-dark small">Unggah Foto Fisik Barang</strong>
                    </div>
                    <p class="text-muted small mb-0">Buka rincian kartu barang, lalu klik <strong>Unggah Foto</strong> agar kondisi fisik barang terdokumentasi rapi di sistem dan galeri foto.</p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="p-3 border rounded-3 h-100 bg-light">
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <span class="badge rounded-pill bg-success text-white px-2.5 py-1">Langkah 4</span>
                      <strong class="text-dark small">Cetak Kartu Standar ATM</strong>
                    </div>
                    <p class="text-muted small mb-0">Klik tombol <strong>Cetak Kartu CR80</strong> (ukuran standar kartu ATM 85.6mm x 54mm) atau cetak format lembar A4 untuk ditempel pada unit barang.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Panduan 3: Auditor / SKAI -->
          <div class="tab-pane fade" id="pill-panduan-audit" role="tabpanel">
            <div class="card border-0 shadow-sm p-4 bg-white rounded-3 mb-3">
              <h6 class="fw-bold text-dark mb-3"><i class="bi bi-shield-check text-success me-2"></i>Panduan Pemeriksaan Kepatuhan &amp; Audit Trail:</h6>
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="p-3 border rounded-3 h-100 bg-light">
                    <strong class="text-dark d-block mb-1"><i class="bi bi-speedometer2 text-primary me-1"></i> 1. Monitoring Kepatuhan</strong>
                    <p class="text-muted small mb-0">Pantau persentase pemeliharaan bulanan di dashboard utama. Pastikan target kepatuhan pemeliharaan seluruh cabang tercapai.</p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="p-3 border rounded-3 h-100 bg-light">
                    <strong class="text-dark d-block mb-1"><i class="bi bi-journal-text text-warning me-1"></i> 2. Log Aktivitas (Audit Trail)</strong>
                    <p class="text-muted small mb-0">Buka menu <strong>Log Aktivitas (Audit)</strong> untuk memeriksa rekaman mutasi data, login, pengubahan status, dan unduh CSV untuk arsip OJK/ISO.</p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="p-3 border rounded-3 h-100 bg-light">
                    <strong class="text-dark d-block mb-1"><i class="bi bi-calendar3 text-info me-1"></i> 3. Riwayat Bulanan</strong>
                    <p class="text-muted small mb-0">Buka menu <strong>Riwayat Bulanan</strong> untuk melihat riwayat audit pemeliharaan bulan-bulan lampau secara terperinci.</p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="p-3 border rounded-3 h-100 bg-light">
                    <strong class="text-dark d-block mb-1"><i class="bi bi-tools text-danger me-1"></i> 4. Pantau Temuan Kendala</strong>
                    <p class="text-muted small mb-0">Buka tab <strong>Temuan Kendala</strong> untuk memantau apakah kerusakan komputer yang ditemukan teknisi sudah diperbaiki atau belum.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Panduan 4: Arti Warna Status & 9 Checklist -->
          <div class="tab-pane fade" id="pill-panduan-status" role="tabpanel">
            <div class="card border-0 shadow-sm p-4 bg-white rounded-3 mb-3">
              <h6 class="fw-bold text-dark mb-3"><i class="bi bi-palette-fill text-info me-2"></i>Arti Warna Indikator Status &amp; 9 Checklist IT:</h6>
              <div class="row g-2 mb-3">
                <div class="col-sm-4">
                  <div class="p-2.5 rounded-3 border bg-success bg-opacity-10 text-success fw-bold small">
                    <i class="bi bi-check-circle-fill me-1"></i> Hijau: Selesai / Normal
                    <div class="fw-normal text-muted" style="font-size: 0.72rem;">Perangkat dalam kondisi prima dan checklist telah lengkap.</div>
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="p-2.5 rounded-3 border bg-warning bg-opacity-10 text-warning-emphasis fw-bold small">
                    <i class="bi bi-hourglass-split me-1"></i> Kuning: Proses / Pending
                    <div class="fw-normal text-muted" style="font-size: 0.72rem;">Sedang dalam proses perbaikan atau verifikasi admin.</div>
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="p-2.5 rounded-3 border bg-danger bg-opacity-10 text-danger fw-bold small">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Merah: Temuan Kerusakan
                    <div class="fw-normal text-muted" style="font-size: 0.72rem;">Ada komponen rusak yang membutuhkan penggantian suku cadang.</div>
                  </div>
                </div>
              </div>

              <div class="p-3 bg-light rounded-3 border small">
                <strong class="text-dark d-block mb-1.5"><i class="bi bi-check2-square text-primary me-1"></i> 9 Standar Checklist Pemeliharaan Komputer:</strong>
                <ol class="mb-0 ps-3 text-muted">
                  <li>Pembersihan debu &amp; kotoran fisik CPU / Monitor / Printer</li>
                  <li>Pemeriksaan kipas pendingin &amp; suhu processor</li>
                  <li>Scan virus / malware &amp; update database antivirus bank</li>
                  <li>Pembersihan file sampah (temp / cache) &amp; disk cleanup</li>
                  <li>Pemeriksaan koneksi kabel LAN, IP Address, &amp; stabilitas jaringan</li>
                  <li>Pemeriksaan integritas sistem operasi &amp; update keamanan</li>
                  <li>Pemeriksaan fungsi printer, scanner, &amp; perangkat pendukung</li>
                  <li>Pemeriksaan kelayakan UPS / stabilizer &amp; suplai listrik</li>
                  <li>Pemeriksaan cadangan data (backup) file penting user</li>
                </ol>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer py-2.5 px-4 bg-white border-top d-flex justify-content-between align-items-center">
        <span class="small text-muted"><i class="bi bi-shield-lock-fill text-primary me-1"></i> Sistem Terproteksi Standar Perbankan POJK No. 75</span>
        <button type="button" class="btn btn-primary btn-sm px-4 fw-semibold" data-bs-dismiss="modal">Saya Paham</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Antrean Pemeliharaan Offline PWA -->
<div class="modal fade" id="modalPWAQueue" tabindex="-1" aria-labelledby="modalPWAQueueLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-dark text-white border-0 py-3">
        <div class="d-flex align-items-center gap-2">
          <div class="p-2 bg-warning bg-opacity-25 rounded-circle text-warning fs-5">
            <i class="bi bi-cloud-arrow-up-fill"></i>
          </div>
          <div>
            <h6 class="modal-title fw-bold mb-0">Antrean Pemeliharaan Offline</h6>
            <div class="text-white-50 small" style="font-size: 0.72rem;">Data tersimpan di memori HP karena sinyal lemah / offline</div>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-3">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light small">
              <tr>
                <th>Perangkat</th>
                <th>Teknisi</th>
                <th>Tipe</th>
                <th class="text-end">Aksi</th>
              </tr>
            </thead>
            <tbody id="pwaQueueListBody">
              <tr><td colspan="4" class="text-center py-3 text-muted">Memuat antrean...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer justify-content-between py-2 bg-light border-top">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary btn-sm fw-bold" id="btnSyncAllQueue">
          <i class="bi bi-arrow-repeat me-1"></i> Sinkronkan Semua Sekarang
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  // Keyboard Shortcut: Ctrl + K or / to open Global Search
  document.addEventListener("keydown", function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "k") {
      e.preventDefault();
      var modalEl = document.getElementById("globalSearchModal");
      if (modalEl) {
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
      }
    }
  });

  var modalEl = document.getElementById("globalSearchModal");
  if (modalEl) {
    modalEl.addEventListener("shown.bs.modal", function () {
      var inp = document.getElementById("globalSearchInput");
      if (inp) inp.focus();
    });
  }

  // Client-side quick filter in Global Search
  var searchInput = document.getElementById("globalSearchInput");
  if (searchInput) {
    searchInput.addEventListener("input", function() {
      var q = this.value.toLowerCase().trim();
      var items = document.querySelectorAll("#globalSearchResults .list-group-item");
      items.forEach(function(el) {
        var text = el.textContent.toLowerCase();
        el.style.display = (q === "" || text.includes(q)) ? "flex" : "none";
      });
    });
    searchInput.addEventListener("keydown", function(e) {
      if (e.key === "Enter") {
        var q = this.value.trim();
        if (q !== "") {
          window.location.href = "'.e(module_url('assets.php')).'?q=" + encodeURIComponent(q);
        }
      }
    });
  }

  // =========================================================================
  // SISTEM IDLE TIMEOUT (2 JAM LOGOUT OTOMATIS JIKA DITINGGAL TOTAL)
  // =========================================================================
  var isUserLoggedIn = '.($isLoggedIn ? 'true' : 'false').';
  var idleLogoutTimeoutMs = 2 * 60 * 60 * 1000; // 2 jam (7200 detik) -> Logout saat ditinggal total
  var loginExpiredUrl = "'.e(module_url('login.php', ['expired' => 1])).'";
  var logoutTimer = null;

  function resetIdleTimers() {
    clearTimeout(logoutTimer);
    if (isUserLoggedIn) {
      logoutTimer = setTimeout(function(){
        window.location.href = loginExpiredUrl;
      }, idleLogoutTimeoutMs);
    }
  }

  if (isUserLoggedIn) {
    ["mousemove", "mousedown", "keydown", "scroll", "touchstart", "click"].forEach(function(evt){
      document.addEventListener(evt, resetIdleTimers, { passive: true });
    });
    resetIdleTimers();
  }

  // =========================================================================
  // AUTOSAVE DRAFT FORM INPUT (Mencegah kehilangan data isian pengguna)
  // =========================================================================
  (function initFormAutosave() {
    var form = document.querySelector("form:not(#lockScreenForm):not(#globalSearchModal form)");
    if (!form) return;
    var storageKey = "bm_form_draft_" + window.location.pathname;

    try {
      var saved = sessionStorage.getItem(storageKey);
      if (saved) {
        var data = JSON.parse(saved);
        if (data && typeof data === "object") {
          Object.keys(data).forEach(function(k){
            var el = form.elements[k];
            if (el && !el.value && el.type !== "password" && el.type !== "file" && el.type !== "hidden") {
              el.value = data[k];
            }
          });
        }
      }
    } catch(e){}

    var saveTimer = null;
    form.addEventListener("input", function(){
      clearTimeout(saveTimer);
      saveTimer = setTimeout(function(){
        try {
          var draft = {};
          for (var i = 0; i < form.elements.length; i++) {
            var el = form.elements[i];
            if (el.name && el.type !== "password" && el.type !== "file" && el.type !== "hidden") {
              draft[el.name] = el.value;
            }
          }
          sessionStorage.setItem(storageKey, JSON.stringify(draft));
        } catch(e){}
      }, 500);
    }, { passive: true });

    form.addEventListener("submit", function(){
      try { sessionStorage.removeItem(storageKey); } catch(e){}
    });
  })();

  // =========================================================================
  // INDIKATOR PROSES LOADING PERPINDAHAN HALAMAN (PAGE TRANSITION PROGRESS)
  // =========================================================================
  var progressBar = document.getElementById("top-progress-bar");
  var pageNavLoader = document.getElementById("page-nav-loader");
  var progressTimer = null;
  var progressVal = 0;

  function setProgress(val) {
    if (!progressBar) return;
    progressVal = val;
    progressBar.classList.add("active");
    progressBar.style.opacity = "1";
    progressBar.style.width = val + "%";
  }

  function startPageLoading() {
    if (progressTimer) clearInterval(progressTimer);
    setProgress(18);
    if (pageNavLoader) pageNavLoader.style.display = "flex";

    progressTimer = setInterval(function() {
      if (progressVal < 50) {
        setProgress(progressVal + 12);
      } else if (progressVal < 80) {
        setProgress(progressVal + 6);
      } else if (progressVal < 94) {
        setProgress(progressVal + 1.5);
      }
    }, 150);
  }

  function stopPageLoading() {
    if (progressTimer) {
      clearInterval(progressTimer);
      progressTimer = null;
    }
    if (progressBar) {
      setProgress(100);
      setTimeout(function() {
        progressBar.style.opacity = "0";
        setTimeout(function() {
          progressBar.classList.remove("active");
          progressBar.style.width = "0%";
          if (pageNavLoader) pageNavLoader.style.display = "none";
        }, 220);
      }, 140);
    } else {
      if (pageNavLoader) pageNavLoader.style.display = "none";
    }
  }

  // Tangkap klik pada seluruh link internal ke halaman lain
  document.addEventListener("click", function(e) {
    var a = e.target.closest("a");
    if (!a || !a.href) return;

    if (a.target && a.target !== "_self") return;
    if (a.hasAttribute("download")) return;
    if (a.href.indexOf("javascript:") === 0) return;
    if (a.getAttribute("data-bs-toggle") || a.getAttribute("data-bs-target")) return;
    if (a.origin !== location.origin) return;
    if (a.pathname === location.pathname && a.search === location.search && a.hash) return;
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.which === 2) return;

    startPageLoading();
  }, { capture: true, passive: true });

  // Tangkap pengiriman form (filter, submit data, dsb)
  document.addEventListener("submit", function(e) {
    var form = e.target;
    if (!form || (form.target && form.target !== "_self")) return;
    if (form.getAttribute("data-ajax") || form.id === "lockScreenForm") return;
    startPageLoading();
  }, { passive: true });

  // Tangani saat halaman selesai dimuat atau kembali dari cache history browser
  window.addEventListener("pageshow", function() {
    stopPageLoading();
  });

  // Animasi awal saat halaman pertama kali selesai dirender
  document.addEventListener("DOMContentLoaded", function() {
    startPageLoading();
    setTimeout(stopPageLoading, 160);
  });
})();

// PWA Service Worker Registration
if ("serviceWorker" in navigator) {
  window.addEventListener("load", function() {
    navigator.serviceWorker.register("'.e(module_url('sw.js')).'")
      .then(function(reg) {
        // Success
      })
      .catch(function(err) {
        console.warn("SW registration error:", err);
      });
  });
}
</script>
<script src="'.e(module_url('pwa-offline-queue.js')).'"></script>
'.$extraScript.'
</body>
</html>';
}
}

if (!function_exists('render_view')) {
    function render_view(string $title, string $content, string $extraHead = '', string $extraScript = '', bool $showNav = true): void {
        render_page($title, $content, $extraHead, $extraScript, $showNav);
    }
}

