<?php
function render_page(string $title, string $content, string $extraHead = '', string $extraScript = '', bool $showNav = true): void {
    $currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $role = current_user_role();
    $userName = current_user_name();
    $userInitial = strtoupper(substr($userName, 0, 1) ?: 'U');
    $isAdmin = is_admin();

    // Context section
    $sectionContext = 'OPERATIONS';
    if (in_array($currentPage, ['audit.php', 'monthly_history.php', 'history.php', 'print_report.php'], true)) {
        $sectionContext = 'MONITORING';
    } elseif (in_array($currentPage, ['cabang_admin.php', 'divisi_admin.php', 'users_admin.php', 'user_biometric_enroll.php'], true)) {
        $sectionContext = 'MANAGEMENT';
    } elseif (in_array($currentPage, ['qr_admin.php', 'system_design.php'], true)) {
        $sectionContext = 'SYSTEM';
    }

    // Sidebar navigation menu
    $navLinks = [
        'OPERATIONS' => [
            ['title' => 'Dashboard', 'url' => module_url('dashboard.php'), 'icon' => 'bi-speedometer2', 'active' => ($currentPage === 'dashboard.php')],
            ['title' => 'Asset Registry', 'url' => module_url('assets.php'), 'icon' => 'bi-pc-display', 'active' => in_array($currentPage, ['assets.php', 'asset_edit.php', 'asset_delete.php'], true)],
            ['title' => 'Maintenance', 'url' => module_url('audit.php'), 'icon' => 'bi-clipboard-check', 'active' => in_array($currentPage, ['audit.php', 'monthly_history.php', 'history.php', 'maintenance_detail.php'], true)],
            ['title' => 'QR Scanner', 'url' => module_url('scanner.php'), 'icon' => 'bi-qr-code-scan', 'active' => ($currentPage === 'scanner.php')],
        ],
        'MONITORING' => [
            ['title' => 'Reports & Audit', 'url' => module_url('audit.php'), 'icon' => 'bi-file-earmark-bar-graph', 'active' => ($currentPage === 'audit.php')],
            ['title' => 'Riwayat Bulanan', 'url' => module_url('monthly_history.php'), 'icon' => 'bi-calendar3', 'active' => ($currentPage === 'monthly_history.php')],
        ],
        'MANAGEMENT' => [
            ['title' => 'Kantor Cabang', 'url' => module_url('cabang_admin.php'), 'icon' => 'bi-buildings', 'active' => ($currentPage === 'cabang_admin.php')],
            ['title' => 'Divisi / Unit Kerja', 'url' => module_url('divisi_admin.php'), 'icon' => 'bi-diagram-3', 'active' => ($currentPage === 'divisi_admin.php')],
            ['title' => 'Akun Pengguna', 'url' => module_url('users_admin.php'), 'icon' => 'bi-people', 'active' => ($currentPage === 'users_admin.php')],
            ['title' => 'Wajah Teknisi (HP)', 'url' => module_url('user_biometric_enroll.php'), 'icon' => 'bi-person-bounding-box', 'active' => ($currentPage === 'user_biometric_enroll.php')],
        ],
        'SYSTEM' => [
            ['title' => 'QR Aset Label', 'url' => module_url('qr_admin.php'), 'icon' => 'bi-qr-code', 'active' => ($currentPage === 'qr_admin.php')],
            ['title' => 'Dokumen Desain', 'url' => module_url('system_design.php'), 'icon' => 'bi-file-earmark-pdf', 'active' => ($currentPage === 'system_design.php')],
        ]
    ];

    $sidebarMenuHtml = '';
    foreach ($navLinks as $groupName => $items) {
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
      <div class="sidebar-brand">
        <a href="'.e(module_url('dashboard.php')).'" class="d-flex align-items-center gap-3 text-decoration-none">
          <div class="sidebar-brand-badge">
            <i class="bi bi-shield-check"></i>
          </div>
          <div>
            <div class="sidebar-brand-title">BANK MITRA</div>
            <div class="sidebar-brand-sub">IT OPERATIONS CENTER</div>
          </div>
        </a>
      </div>

      <div class="sidebar-content flex-grow-1 custom-scrollbar">
        '.$sidebarMenuHtml.'
      </div>

      <div class="sidebar-footer">
        <div class="sidebar-user-card d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2 overflow-hidden">
            <div class="sidebar-user-avatar">'.$userInitial.'</div>
            <div class="overflow-hidden">
              <div class="sidebar-user-name text-truncate">'.e($userName).'</div>
              <div class="sidebar-user-role text-capitalize">'.e($role).'</div>
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
      <div class="offcanvas-header border-bottom border-navy-subtle">
        <div class="d-flex align-items-center gap-2">
          <div class="sidebar-brand-badge"><i class="bi bi-shield-check"></i></div>
          <div>
            <div class="sidebar-brand-title text-white">BANK MITRA</div>
            <div class="sidebar-brand-sub">IT OPERATIONS</div>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body custom-scrollbar p-3">
        '.$sidebarMenuHtml.'
      </div>
      <div class="p-3 border-top border-navy-subtle">
        <div class="d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <div class="sidebar-user-avatar">'.$userInitial.'</div>
            <div>
              <div class="text-white small fw-bold">'.e($userName).'</div>
              <div class="text-muted small text-capitalize">'.e($role).'</div>
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

        <a href="'.e(module_url('asset_add.php')).'" class="btn btn-sm btn-primary d-none d-sm-inline-flex align-items-center gap-1 fw-semibold px-3">
          <i class="bi bi-plus-lg"></i>
          <span>Tambah Aset</span>
        </a>

        <div class="dropdown">
          <button class="topbar-user-btn dropdown-toggle border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="topbar-user-avatar">'.$userInitial.'</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm border mt-2">
            <li class="px-3 py-2 border-bottom">
              <div class="fw-bold text-dark">'.e($userName).'</div>
              <div class="small text-muted text-capitalize"><i class="bi bi-shield-lock me-1"></i>'.e($role).'</div>
            </li>
            <li><a class="dropdown-item py-2" href="'.e(module_url('assets.php')).'"><i class="bi bi-pc-display me-2 text-primary"></i> Data Komputer</a></li>
            <li><a class="dropdown-item py-2" href="'.e(module_url('scanner.php')).'"><i class="bi bi-qr-code-scan me-2 text-info"></i> Scanner QR</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item py-2 text-danger" href="'.e(module_url('logout.php')).'"><i class="bi bi-box-arrow-right me-2"></i> Keluar</a></li>
          </ul>
        </div>
      </div>
    </header>';

    // Public header if nav is disabled
    $publicHeaderHtml = '
    <header class="public-topbar py-3 px-4 bg-white border-bottom shadow-sm d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-3">
        <div class="sidebar-brand-badge" style="width:34px; height:34px; font-size: 1rem;"><i class="bi bi-shield-check"></i></div>
        <div>
          <div class="fw-bold text-dark lh-1" style="font-size: 0.95rem; letter-spacing: 0.3px;">BANK MITRA</div>
          <div class="tech-label" style="font-size: 0.68rem;">IT OPERATIONS · INSPECTION PORTAL</div>
        </div>
      </div>
      <div>
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
/* BANKING IT OPERATIONS CENTER DESIGN SYSTEM */
:root {
  --navy-deep: #08182F;
  --navy-primary: #0D2748;
  --navy-subtle: #1E3A60;
  --blue-corporate: #124E96;
  --blue-accent: #2E7CF6;
  --blue-soft: #EAF3FF;
  --bg-app: #F4F7FB;
  --surface-card: #FFFFFF;
  --border-subtle: #E4E9F0;
  --border-strong: #CBD5E1;
  --text-primary: #182230;
  --text-secondary: #667085;
  --text-muted: #98A2B3;
  --status-success: #16803C;
  --status-success-bg: #ECFDF3;
  --status-warning: #B54708;
  --status-warning-bg: #FFFAEB;
  --status-danger: #B42318;
  --status-danger-bg: #FEF3F2;
  --status-info: #026AA2;
  --status-info-bg: #F0F9FF;
  --radius-xs: 4px;
  --radius-sm: 6px;
  --radius-md: 8px;
  --radius-lg: 10px;
  --radius-xl: 12px;
  --shadow-subtle: 0 1px 2px rgba(16, 24, 40, 0.05);
  --shadow-card: 0 1px 3px rgba(16, 24, 40, 0.08);
}

* { box-sizing: border-box; }

body {
  margin: 0;
  padding: 0;
  background-color: var(--bg-app);
  font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  color: var(--text-primary);
  -webkit-font-smoothing: antialiased;
  min-height: 100vh;
}

h1, h2, h3, h4, h5, h6 {
  color: var(--text-primary);
  font-weight: 600;
  letter-spacing: -0.02em;
}

/* Base Layout Framework */
.app-layout-wrapper {
  display: flex;
  min-height: 100vh;
  width: 100%;
}

.app-sidebar {
  width: 250px;
  min-width: 250px;
  background-color: var(--navy-deep);
  border-right: 1px solid var(--navy-subtle);
  height: 100vh;
  position: sticky;
  top: 0;
  z-index: 1020;
}

.bg-navy-dark {
  background-color: var(--navy-deep) !important;
}

.border-navy-subtle {
  border-color: var(--navy-subtle) !important;
}

.sidebar-brand {
  padding: 20px 18px 16px;
  border-bottom: 1px solid var(--navy-subtle);
}

.sidebar-brand-badge {
  width: 38px;
  height: 38px;
  background-color: var(--blue-corporate);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #FFFFFF;
  font-size: 1.15rem;
}

.sidebar-brand-title {
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  color: #FFFFFF;
  line-height: 1.2;
}

.sidebar-brand-sub {
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: #98A2B3;
  text-transform: uppercase;
}

.sidebar-content {
  overflow-y: auto;
  padding: 16px 10px;
}

.sidebar-section-title {
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #667085;
  padding: 8px 12px 6px;
}

.sidebar-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  color: #98A2B3;
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 500;
  border-radius: var(--radius-sm);
  transition: background-color 0.15s ease, color 0.15s ease;
  margin-bottom: 2px;
  border-left: 3px solid transparent;
}

.sidebar-link:hover {
  color: #FFFFFF;
  background-color: rgba(255, 255, 255, 0.06);
}

.sidebar-link.active {
  color: #FFFFFF;
  background-color: rgba(46, 124, 246, 0.12);
  border-left-color: var(--blue-accent);
  font-weight: 600;
}

.sidebar-link.active .sidebar-icon {
  color: var(--blue-accent);
}

.sidebar-icon {
  font-size: 1rem;
  width: 18px;
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
  height: 64px;
  background-color: var(--surface-card);
  border-bottom: 1px solid var(--border-subtle);
  position: sticky;
  top: 0;
  z-index: 1010;
}

.topbar-page-title {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--text-primary);
  max-width: 340px;
}

.topbar-search-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  background-color: var(--bg-app);
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-sm);
  padding: 6px 12px;
  font-size: 0.82rem;
  color: var(--text-secondary);
  cursor: pointer;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}

.topbar-search-btn:hover {
  border-color: var(--border-strong);
  background-color: #FFFFFF;
}

.topbar-kbd {
  background-color: #FFFFFF;
  border: 1px solid var(--border-subtle);
  border-radius: 4px;
  box-shadow: 0 1px 1px rgba(0,0,0,0.05);
  font-size: 0.68rem;
  font-weight: 600;
  color: var(--text-muted);
  padding: 2px 6px;
}

.topbar-status-badge {
  background-color: var(--status-success-bg);
  border: 1px solid #A6F4C5;
  border-radius: 20px;
  padding: 4px 10px;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--status-success);
}

.status-pulse-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: var(--status-success);
  box-shadow: 0 0 0 2px rgba(22, 128, 60, 0.2);
}

.topbar-user-btn {
  background: none;
  padding: 0;
  cursor: pointer;
}

.topbar-user-avatar {
  width: 34px;
  height: 34px;
  border-radius: var(--radius-sm);
  background-color: var(--blue-soft);
  color: var(--blue-corporate);
  border: 1px solid #BFDBFE;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.85rem;
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
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-subtle);
}

/* Flat & Subtle Card Accents */
.card-metric {
  padding: 20px 22px;
  border-left: 3px solid var(--blue-corporate);
}

.metric-value {
  font-size: 1.85rem;
  font-weight: 600;
  letter-spacing: -0.02em;
  color: var(--text-primary);
  line-height: 1.1;
}

.metric-label {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-secondary);
  margin-top: 4px;
}

/* Button Standards */
.btn {
  font-size: 0.85rem;
  font-weight: 500;
  border-radius: var(--radius-sm);
  padding: 7px 14px;
  transition: all 0.15s ease;
}

.btn-primary {
  background-color: var(--blue-corporate) !important;
  border-color: var(--blue-corporate) !important;
  color: #FFFFFF !important;
}

.btn-primary:hover, .btn-primary:focus {
  background-color: #0E3E77 !important;
  border-color: #0E3E77 !important;
}

.btn-secondary, .btn-light {
  background-color: #FFFFFF !important;
  border-color: var(--border-subtle) !important;
  color: var(--text-primary) !important;
}

.btn-secondary:hover, .btn-light:hover {
  background-color: var(--bg-app) !important;
  border-color: var(--border-strong) !important;
}

.btn-outline-primary {
  color: var(--blue-corporate) !important;
  border-color: var(--blue-corporate) !important;
}

.btn-outline-primary:hover {
  background-color: var(--blue-corporate) !important;
  color: #FFFFFF !important;
}

/* Form Controls */
.form-control, .form-select {
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  padding: 7px 12px;
  font-size: 0.88rem;
  color: var(--text-primary);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
  background-color: #FFFFFF;
}

.form-control:focus, .form-select:focus {
  border-color: var(--blue-corporate) !important;
  box-shadow: 0 0 0 3px rgba(18, 78, 150, 0.14) !important;
  outline: none;
}

/* Table Design */
.table {
  font-size: 0.88rem;
  color: var(--text-primary);
  margin-bottom: 0;
}

.table thead th {
  background-color: #F8FAFC !important;
  color: var(--text-secondary) !important;
  font-weight: 600;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  border-bottom: 1px solid var(--border-subtle);
  padding: 10px 14px;
}

.table tbody td {
  padding: 11px 14px;
  border-bottom: 1px solid #EDF2F7;
  vertical-align: middle;
}

.table-hover tbody tr:hover {
  background-color: #F8FAFC;
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
  height: 2.5px;
  background-color: var(--blue-accent);
  z-index: 9999;
  transition: width .2s ease;
  width: 0;
}

@media print {
  .no-print, .app-sidebar, .app-topbar, .public-topbar, #top-progress-bar { display: none !important; }
  .app-content-container { padding: 0 !important; max-width: 100% !important; }
  body { background: #FFFFFF !important; }
}
</style>
'.$extraHead.'
</head>
<body>
<div id="top-progress-bar"></div>

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

  // Instant prefetch on link hover
  var preloaded = {};
  function doPrefetch(url) {
    if (!url || preloaded[url]) return;
    if (url.indexOf("javascript:") === 0 || url.indexOf("#") !== -1) return;
    try {
      var u = new URL(url, location.href);
      if (u.origin !== location.origin) return;
      preloaded[url] = true;
      var link = document.createElement("link");
      link.rel = "prefetch";
      link.href = url;
      document.head.appendChild(link);
    } catch(e){}
  }
  document.addEventListener("mouseover", function(e){
    var a = e.target.closest("a");
    if (a && a.href && !a.target && a.origin === location.origin) doPrefetch(a.href);
  }, {passive: true});

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

  // Auto-Lock idle timer (15 min)
  var idleTimeoutMs = 900 * 1000;
  var idleTimer;
  function resetIdleTimer() {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(function(){
      window.location.href = "'.e(module_url('login.php', ['expired' => 1])).'";
    }, idleTimeoutMs);
  }
  ["mousemove", "mousedown", "keydown", "scroll", "touchstart", "click"].forEach(function(evt){
    document.addEventListener(evt, resetIdleTimer, {passive: true});
  });
  resetIdleTimer();
})();
</script>
'.$extraScript.'
</body>
</html>';
}
