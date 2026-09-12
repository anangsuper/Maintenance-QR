<?php
function render_page(string $title, string $content, string $extraHead = '', string $extraScript = '', bool $showNav = true): void {
    $nav = '';
    $currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');

    if ($showNav) {
        $isMasterActive = in_array($currentPage, ['cabang_admin.php', 'divisi_admin.php', 'users_admin.php', 'system_design.php'], true);
        $nav = '
        <nav class="navbar navbar-expand-lg navbar-dark main-navbar mb-4 sticky-top">
          <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-3 fw-bold" href="'.e(module_url('dashboard.php')).'">
              <span class="brand-icon shadow-sm"><i class="bi bi-qr-code-scan"></i></span>
              <div>
                <span class="brand-text d-block lh-1 text-white">QR Maintenance System</span>
                <span class="d-inline-block font-monospace fw-bold mt-1" style="font-size: 0.7rem; letter-spacing: 0.8px; color: #30B0E0;">BANK MITRA</span>
              </div>
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbarNav" aria-controls="mainNavbarNav" aria-expanded="false" aria-label="Toggle navigation">
              <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="mainNavbarNav">
              <div class="d-flex flex-column flex-lg-row gap-2 align-items-lg-center pt-2 pt-lg-0">
                <a class="nav-pill-btn '.($currentPage==='dashboard.php'?'active':'').'" href="'.e(module_url('dashboard.php')).'"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a class="nav-pill-btn '.(in_array($currentPage, ['assets.php', 'asset_edit.php', 'asset_delete.php'], true)?'active':'').'" href="'.e(module_url('assets.php')).'"><i class="bi bi-pc-display"></i> Data Komputer</a>
                <a class="nav-pill-btn '.(in_array($currentPage, ['audit.php', 'monthly_history.php', 'history.php', 'maintenance_detail.php'], true)?'active':'').'" href="'.e(module_url('audit.php')).'"><i class="bi bi-clock-history"></i> Riwayat</a>
                <a class="nav-pill-btn '.($currentPage==='qr_admin.php'?'active':'').'" href="'.e(module_url('qr_admin.php')).'"><i class="bi bi-qr-code"></i> QR Aset</a>

                <!-- Dropdown Kelola Data Master -->
                <div class="dropdown">
                  <button class="nav-pill-btn dropdown-toggle border-0 w-100 text-start '.($isMasterActive?'active':'').'" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-gear-fill"></i> Kelola Data
                  </button>
                  <ul class="dropdown-menu dropdown-menu-dark shadow border-0 mt-2">
                    <li><a class="dropdown-item py-2 '.($currentPage==='assets.php'?'active':'').'" href="'.e(module_url('assets.php')).'"><i class="bi bi-pc-display me-2 text-primary"></i> Data Komputer (Aset)</a></li>
                    <li><hr class="dropdown-divider border-secondary opacity-50"></li>
                    <li><a class="dropdown-item py-2 '.($currentPage==='cabang_admin.php'?'active':'').'" href="'.e(module_url('cabang_admin.php')).'"><i class="bi bi-buildings me-2 text-info"></i> Data Cabang</a></li>
                    <li><a class="dropdown-item py-2 '.($currentPage==='divisi_admin.php'?'active':'').'" href="'.e(module_url('divisi_admin.php')).'"><i class="bi bi-diagram-3 me-2 text-info"></i> Data Divisi</a></li>
                    <li><a class="dropdown-item py-2 '.($currentPage==='users_admin.php'?'active':'').'" href="'.e(module_url('users_admin.php')).'"><i class="bi bi-people me-2 text-warning"></i> Akun Pengguna / Teknisi</a></li>
                    <li><a class="dropdown-item py-2 '.($currentPage==='user_biometric_enroll.php'?'active':'').'" href="'.e(module_url('user_biometric_enroll.php')).'"><i class="bi bi-person-bounding-box me-2 text-success"></i> Daftar Wajah Teknisi (HP)</a></li>
                    <li><a class="dropdown-item py-2 '.($currentPage==='invoice.php'?'active':'').'" href="'.e(module_url('invoice.php')).'"><i class="fa-solid fa-receipt me-2 text-warning"></i> Struk Invoice Mesin (Slot)</a></li>
                    <li><hr class="dropdown-divider border-secondary opacity-50"></li>
                    <li><a class="dropdown-item py-2 '.($currentPage==='system_design.php'?'active':'').'" href="'.e(module_url('system_design.php')).'"><i class="bi bi-file-earmark-pdf-fill me-2 text-danger"></i> Dokumen Desain (PDF)</a></li>
                  </ul>
                </div>

                <a class="btn btn-sm btn-action-add fw-bold px-3 ms-lg-1" href="'.e(module_url('asset_add.php')).'"><i class="bi bi-plus-circle-fill me-1"></i> + Tambah Komputer</a>
                
                <div class="d-flex align-items-center gap-2 ms-lg-2 pt-2 pt-lg-0 border-top border-lg-0 border-secondary border-opacity-25">
                  <span class="d-inline-flex align-items-center gap-1 text-white-50 small"><i class="bi bi-person-circle"></i> '.e(current_user_name()).'</span>
                  <a class="nav-pill-btn text-danger-emphasis" href="'.e(module_url('logout.php')).'" title="Keluar / Logout"><i class="bi bi-box-arrow-right"></i></a>
                </div>
              </div>
            </div>
          </div>
        </nav>';
    } else {
        $nav = '
        <header class="text-center py-3 mb-4 bg-white border-bottom shadow-sm">
          <span class="fw-bold fs-5" style="color: #2E77AD;"><i class="bi bi-qr-code-scan me-2" style="color: #30B0E0;"></i>QR Maintenance System · <span class="badge bg-primary text-white ms-1 px-2 py-1">BANK MITRA</span></span>
        </header>';
    }

    echo '<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>'.e($title).' · QR Maintenance</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
/* BANK MITRA - PT. BPR MITRATAMA ARTHABUANA OFFICIAL BRAND PALETTE */
:root {
  --bm-primary-blue: #2E77AD;
  --bm-sky-blue: #30B0E0;
  --bm-teal-aqua: #50C0C0;
  --bm-cyan-teal: #40C0D0;
  --bm-lime-accent: #30B0E0;
  --bm-fresh-green: #10B981;
  --bm-bg-light: #F5F8FB;
  --bm-surface-gray: #E6EDF5;
  --bm-dark-text: #1F2A37;

  --primary-gradient: linear-gradient(135deg, #2E77AD 0%, #30B0E0 100%);
  --teal-gradient: linear-gradient(135deg, #30B0E0 0%, #50C0C0 100%);
  --lime-gradient: linear-gradient(135deg, #30B0E0 0%, #10B981 100%);
  --success-gradient: linear-gradient(135deg, #10B981 0%, #059669 100%);
  --warning-gradient: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
  --danger-gradient: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
}

body {
  background: #F5F8FB;
  font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  color: #1F2A37;
  -webkit-font-smoothing: antialiased;
}

h1, h2, h3, h4, h5, h6, .h1, .h2, .h3, .h4, .h5, .h6 {
  color: #1F2A37;
  font-weight: 700;
}

/* Contrast Guarantee: Headings inside dark headers, hero banners, and modal headers */
.text-white h1, .text-white h2, .text-white h3, .text-white h4, .text-white h5, .text-white h6,
.text-white .h1, .text-white .h2, .text-white .h3, .text-white .h4, .text-white .h5, .text-white .h6,
.dashboard-hero h1, .dashboard-hero h2, .dashboard-hero h3, .dashboard-hero h4, .dashboard-hero h5, .dashboard-hero h6,
.main-navbar h1, .main-navbar h2, .main-navbar h3, .main-navbar h4, .main-navbar h5, .main-navbar h6,
.main-navbar .brand-text,
.bg-primary h1, .bg-primary h2, .bg-primary h3, .bg-primary h4, .bg-primary h5, .bg-primary h6,
.bg-dark h1, .bg-dark h2, .bg-dark h3, .bg-dark h4, .bg-dark h5, .bg-dark h6,
.modal-header.bg-primary .modal-title,
.modal-header.text-white .modal-title {
  color: #ffffff !important;
}

/* Navbar Bank Mitra - Deep Corporate Navy with Vivid Cyan Accent */
.main-navbar {
  background: linear-gradient(135deg, #0B192C 0%, #112D4E 50%, #183B63 100%);
  backdrop-filter: blur(12px);
  box-shadow: 0 4px 20px rgba(11, 25, 44, 0.4);
  border-bottom: 3px solid #30B0E0;
  padding: 0.85rem 0;
}

.brand-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, #2E77AD 0%, #30B0E0 100%);
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(48, 176, 224, 0.4);
  color: #ffffff;
  font-size: 1.25rem;
}

.brand-text {
  font-size: 1.18rem;
  font-weight: 800;
  letter-spacing: -0.3px;
  color: #ffffff !important;
}

.nav-pill-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 15px;
  font-size: 0.85rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.95) !important;
  text-decoration: none;
  border-radius: 20px;
  background: transparent;
  border: none;
  transition: all 0.2s ease;
}

.nav-pill-btn:hover {
  color: #ffffff !important;
  background: rgba(255, 255, 255, 0.2) !important;
  transform: translateY(-1px);
}

.nav-pill-btn:focus,
.nav-pill-btn:focus-visible {
  outline: none;
  box-shadow: none;
}

.nav-pill-btn.active,
.nav-pill-btn.active:hover,
.nav-pill-btn.active:focus,
.nav-pill-btn.show,
.nav-pill-btn.show:hover,
.nav-pill-btn.show:focus,
.dropdown.show .nav-pill-btn,
.show > .nav-pill-btn {
  color: #1F2A37 !important;
  background: #ffffff !important;
  box-shadow: 0 3px 12px rgba(31, 42, 55, 0.18) !important;
}

.nav-pill-btn.active i,
.nav-pill-btn.active:hover i,
.nav-pill-btn.show i,
.show > .nav-pill-btn i {
  color: #2E77AD !important;
}

.nav-pill-btn.active::after,
.nav-pill-btn.show::after,
.show > .nav-pill-btn::after {
  border-top-color: #1F2A37 !important;
}

.dropdown-menu {
  border-radius: 12px;
  padding: 8px;
}

.dropdown-item {
  border-radius: 8px;
  font-weight: 500;
  font-size: 0.88rem;
  transition: all 0.15s ease;
}

.dropdown-item.active,
.dropdown-item:active {
  background: #2E77AD !important;
  color: #ffffff !important;
  font-weight: 700;
}

/* Tombol Aksi Tambah Komputer (Vibrant Modern Emerald Green) */
.btn-action-add {
  background: linear-gradient(135deg, #10B981 0%, #059669 100%);
  color: #ffffff !important;
  border: none;
  border-radius: 20px;
  padding: 7px 18px;
  font-weight: 700;
  box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
  transition: all 0.2s ease;
}

.btn-action-add:hover {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  color: #ffffff !important;
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(16, 185, 129, 0.45);
}

/* Card & Elevated Components (Soft Surface Gray #E6EDF5) */
.card {
  border: 1px solid #E6EDF5;
  border-radius: 16px;
  background: #ffffff;
  box-shadow: 0 4px 20px -2px rgba(46, 119, 173, 0.05), 0 2px 6px -1px rgba(31, 42, 55, 0.02);
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.card-hover:hover {
  transform: translateY(-3px);
  box-shadow: 0 14px 28px -4px rgba(46, 119, 173, 0.12), 0 4px 10px -2px rgba(31, 42, 55, 0.04);
  border-color: #30B0E0;
}

.stat-card {
  position: relative;
  overflow: hidden;
}

.stat-icon-wrapper {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}

.stat {
  font-size: 1.85rem;
  font-weight: 800;
  letter-spacing: -0.5px;
  line-height: 1.1;
  color: #1F2A37;
}

.small-muted {
  font-size: 0.78rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Modern Form Controls */
.form-control, .form-select {
  border: 1.5px solid #E6EDF5;
  border-radius: 10px;
  padding: 0.55rem 0.85rem;
  font-size: 0.9rem;
  color: #1F2A37;
  transition: all 0.2s ease;
}

.form-control:focus, .form-select:focus {
  border-color: #30B0E0 !important;
  box-shadow: 0 0 0 4px rgba(48, 176, 224, 0.18) !important;
}

/* Badges & Chips */
.badge-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.78rem;
  font-weight: 700;
}

.chip-success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
.chip-warning { background: #fef3c7; color: #b45309; }
.chip-danger { background: #fee2e2; color: #b91c1c; }
.chip-primary { background: rgba(46, 119, 173, 0.12); color: #2E77AD; border: 1px solid rgba(46, 119, 173, 0.25); }
.chip-secondary { background: #E6EDF5; color: #1F2A37; border: 1px solid #cbd5e1; }

/* Table Enhancements (Header Soft Surface Gray #E6EDF5) */
.table {
  font-size: 0.9rem;
  color: #1F2A37;
}

.table thead th {
  background: #E6EDF5 !important;
  color: #1F2A37 !important;
  font-weight: 700;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  border-bottom: 2px solid #cbd5e1;
  padding: 12px 14px;
}

.table tbody td {
  padding: 12px 14px;
  border-bottom: 1px solid #E6EDF5;
  vertical-align: middle;
}

.table-hover tbody tr:hover {
  background-color: #F5F8FB;
}

/* Bootstrap Color Overrides with Bank Mitra Palette */
.btn-primary {
  background-color: #2E77AD !important;
  border-color: #2E77AD !important;
  color: #ffffff !important;
}
.btn-primary:hover, .btn-primary:focus {
  background-color: #245f8b !important;
  border-color: #245f8b !important;
  color: #ffffff !important;
}
.btn-outline-primary {
  color: #2E77AD !important;
  border-color: #2E77AD !important;
}
.btn-outline-primary:hover, .btn-outline-primary:focus {
  background-color: #2E77AD !important;
  border-color: #2E77AD !important;
  color: #ffffff !important;
}
.btn-info {
  background-color: #30B0E0 !important;
  border-color: #30B0E0 !important;
  color: #ffffff !important;
}
.btn-outline-info {
  color: #30B0E0 !important;
  border-color: #30B0E0 !important;
}
.btn-outline-info:hover {
  background-color: #30B0E0 !important;
  color: #ffffff !important;
}
.btn-success {
  background-color: #10B981 !important;
  border-color: #10B981 !important;
  color: #ffffff !important;
}
.btn-success:hover {
  background-color: #059669 !important;
  border-color: #059669 !important;
}
.btn-outline-success {
  color: #10B981 !important;
  border-color: #10B981 !important;
}
.btn-outline-success:hover {
  background-color: #10B981 !important;
  color: #ffffff !important;
}
.text-primary {
  color: #1D4ED8 !important;
}
.text-secondary {
  color: #475569 !important;
}
.text-muted {
  color: #64748B !important;
}
.text-dark {
  color: #0F172A !important;
}
.bg-primary {
  background-color: rgba(46, 119, 173, var(--bs-bg-opacity, 1)) !important;
}
.text-bg-primary {
  background-color: #2E77AD !important;
  color: #ffffff !important;
}
/* Subtle Backgrounds with High-Contrast Text */
.bg-primary-subtle {
  background-color: #EFF6FF !important;
  color: #1D4ED8 !important;
  border-color: #BFDBFE !important;
}
.text-info {
  color: #0369A1 !important;
}
.bg-info {
  background-color: rgba(48, 176, 224, var(--bs-bg-opacity, 1)) !important;
}
.text-bg-info {
  background-color: #0284C7 !important;
  color: #ffffff !important;
}
.bg-info-subtle {
  background-color: #F0F9FF !important;
  color: #0369A1 !important;
  border-color: #BAE6FD !important;
}
.text-success {
  color: #047857 !important;
}
.bg-success {
  background-color: rgba(16, 185, 129, var(--bs-bg-opacity, 1)) !important;
}
.text-bg-success {
  background-color: #10B981 !important;
  color: #ffffff !important;
}
.bg-success-subtle {
  background-color: #ECFDF5 !important;
  color: #047857 !important;
  border-color: #A7F3D0 !important;
}
.text-warning {
  color: #B45309 !important;
}
.bg-warning-subtle {
  background-color: #FFFBEB !important;
  color: #B45309 !important;
  border-color: #FDE68A !important;
}
.text-danger {
  color: #DC2626 !important;
}
.bg-danger-subtle {
  background-color: #FEF2F2 !important;
  color: #DC2626 !important;
  border-color: #FECACA !important;
}
.bg-secondary-subtle {
  background-color: #F1F5F9 !important;
  color: #334155 !important;
  border-color: #CBD5E1 !important;
}

/* Emphasis Text Classes */
.text-primary-emphasis { color: #1D4ED8 !important; }
.text-info-emphasis { color: #0369A1 !important; }
.text-success-emphasis { color: #047857 !important; }
.text-warning-emphasis { color: #9A3412 !important; }
.text-danger-emphasis { color: #991B1B !important; }
.text-secondary-emphasis { color: #334155 !important; }

/* Badges: Solid badges get white text ONLY if they do NOT have opacity, subtle, or text-* */
.badge.bg-primary:not([class*="bg-opacity"]):not([class*="-subtle"]):not([class*="text-"]),
.badge.bg-info:not([class*="bg-opacity"]):not([class*="-subtle"]):not([class*="text-"]),
.badge.bg-success:not([class*="bg-opacity"]):not([class*="-subtle"]):not([class*="text-"]),
.badge.bg-danger:not([class*="bg-opacity"]):not([class*="-subtle"]):not([class*="text-"]),
.badge.bg-secondary:not([class*="bg-opacity"]):not([class*="-subtle"]):not([class*="text-"]),
.badge.bg-dark:not([class*="bg-opacity"]):not([class*="-subtle"]):not([class*="text-"]) {
  color: #ffffff !important;
}

/* Badges with Opacity or Subtle: Guaranteed high-contrast text and clean background */
.badge.bg-primary[class*="bg-opacity"],
.badge.bg-primary-subtle,
.badge.text-primary,
.badge.text-primary-emphasis {
  background-color: #EFF6FF !important;
  color: #1D4ED8 !important;
  border-color: #BFDBFE !important;
}

.badge.bg-info[class*="bg-opacity"],
.badge.bg-info-subtle,
.badge.text-info,
.badge.text-info-emphasis {
  background-color: #F0F9FF !important;
  color: #0369A1 !important;
  border-color: #BAE6FD !important;
}

.badge.bg-success[class*="bg-opacity"],
.badge.bg-success-subtle,
.badge.text-success,
.badge.text-success-emphasis {
  background-color: #ECFDF5 !important;
  color: #047857 !important;
  border-color: #A7F3D0 !important;
}

.badge.bg-warning[class*="bg-opacity"],
.badge.bg-warning-subtle,
.badge.text-warning,
.badge.text-warning-emphasis {
  background-color: #FFFBEB !important;
  color: #B45309 !important;
  border-color: #FDE68A !important;
}

.badge.bg-danger[class*="bg-opacity"],
.badge.bg-danger-subtle,
.badge.text-danger,
.badge.text-danger-emphasis {
  background-color: #FEF2F2 !important;
  color: #DC2626 !important;
  border-color: #FECACA !important;
}

.badge.bg-secondary[class*="bg-opacity"],
.badge.bg-secondary-subtle,
.badge.text-secondary,
.badge.text-secondary-emphasis {
  background-color: #F1F5F9 !important;
  color: #334155 !important;
  border-color: #CBD5E1 !important;
}

.badge.bg-light,
.badge.text-dark {
  background-color: #F8FAFC !important;
  color: #0F172A !important;
  border-color: #E2E8F0 !important;
}

/* High Contrast Alert Boxes */
.alert-info {
  background-color: #F0F9FF !important;
  border-color: #BAE6FD !important;
  color: #0369A1 !important;
}
.alert-success {
  background-color: #F0FDF4 !important;
  border-color: #BBF7D0 !important;
  color: #065F46 !important;
}
.alert-warning {
  background-color: #FFFBEB !important;
  border-color: #FDE68A !important;
  color: #92400E !important;
}
.alert-danger {
  background-color: #FEF2F2 !important;
  border-color: #FECACA !important;
  color: #991B1B !important;
}

/* Loading Progress Bar (Bank Mitra Blue-Teal-Emerald Gradient) */
#top-progress-bar {
  position: fixed;
  top: 0;
  left: 0;
  height: 3px;
  background: linear-gradient(90deg, #30B0E0, #40C0D0, #10B981);
  z-index: 9999;
  transition: width .2s ease;
  width: 0;
}

@media print {
  .no-print, nav, header, #top-progress-bar { display: none !important; }
  .qr-label { box-shadow: none; }
  .container { max-width: none !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
}
</style>
'.$extraHead.'
</head>
<body>
<div id="top-progress-bar"></div>
'.$nav.'
<main class="container pb-5">
'.$content.'
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Ultra-fast instant prefetching on hover / touch
(function(){
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
  document.addEventListener("touchstart", function(e){
    var a = e.target.closest("a");
    if (a && a.href && !a.target && a.origin === location.origin) doPrefetch(a.href);
  }, {passive: true});
  
  // Auto-Lock jika ditinggal lama tanpa aktivitas (15 menit = 900 detik)
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

