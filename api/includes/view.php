<?php
if (!function_exists('render_page')) {
function render_page(string $title, string $content, string $extraHead = '', string $extraScript = '', bool $showNav = true): void {
    $currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $role = current_user_role();
    $userName = current_user_name();
    $userInitial = strtoupper(substr($userName, 0, 1) ?: 'U');
    $cleanTitle = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
    $userPhoto = function_exists('current_user_photo') ? current_user_photo() : null;
    $anonymousSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" style="width: 100%; height: 100%; display: block;"><circle cx="32" cy="32" r="31" fill="#28243D" stroke="#8C57FF" stroke-width="2"/><path d="M32 10C21 10 14 17 14 27c0 10 6 21 18 27 12-6 18-17 18-27 0-10-7-17-18-17z" fill="#FFFFFF"/><ellipse cx="20" cy="34" rx="3.5" ry="2" fill="#FFB4BA" opacity="0.7"/><ellipse cx="44" cy="34" rx="3.5" ry="2" fill="#FFB4BA" opacity="0.7"/><path d="M19 21c3-2.5 7-2.5 10-0.5" stroke="#1E1B2E" stroke-width="2" stroke-linecap="round"/><path d="M45 21c-3-2.5-7-2.5-10-0.5" stroke="#1E1B2E" stroke-width="2" stroke-linecap="round"/><ellipse cx="24" cy="26" rx="3.5" ry="2.2" fill="#1E1B2E"/><ellipse cx="40" cy="26" rx="3.5" ry="2.2" fill="#1E1B2E"/><path d="M22 36c4-1 8 2 10 3 2-1 6-4 10-3 3 1 4 3 4 3-3-1-6 0-8 2-2 2-4 2-6 0-2-2-5-3-8-2-1 0-2-3-2-3z" fill="#1E1B2E"/><path d="M30 44h4l-2 8z" fill="#1E1B2E"/><path d="M25 41c2.5 2 11.5 2 14 0" stroke="#8C57FF" stroke-width="1.8" stroke-linecap="round"/></svg>';
    if ($userPhoto && (str_starts_with($userPhoto, 'data:image') || str_starts_with($userPhoto, 'http') || str_starts_with($userPhoto, '/'))) {
        $avatarHtml = '<img src="'.e($userPhoto).'" alt="'.e($userName).'" class="sidebar-user-avatar-img rounded-circle object-fit-cover shadow-xs" style="width: 38px; height: 38px; min-width: 38px; border: 2px solid rgba(140, 87, 255, 0.45); display: inline-block;">';
        $topbarAvatarHtml = '<img src="'.e($userPhoto).'" alt="'.e($userName).'" class="topbar-user-avatar-img rounded-circle object-fit-cover" style="width: 32px; height: 32px; min-width: 32px; border: 1.5px solid rgba(140, 87, 255, 0.45); display: inline-block;">';
    } else {
        $avatarHtml = '<div class="sidebar-user-avatar d-flex align-items-center justify-content-center overflow-hidden shadow-xs" style="width: 38px; height: 38px; min-width: 38px; border-radius: 50%; padding: 0; background: #28243D;" title="Anonymous User">'.$anonymousSvg.'</div>';
        $topbarAvatarHtml = '<span class="topbar-user-avatar d-inline-flex align-items-center justify-content-center overflow-hidden" style="width: 32px; height: 32px; min-width: 32px; border-radius: 50%; padding: 0; background: #28243D;" title="Anonymous User">'.$anonymousSvg.'</span>';
    }
    $isAdmin = is_admin();
    $isLoggedIn = is_logged_in();

    // Context section / Kategori navbar atas
    $sectionContext = 'MENU UTAMA';
    if (in_array($currentPage, ['assets.php', 'floor_plan.php', 'asset_add.php', 'asset_edit.php', 'asset_delete.php', 'asset_import.php', 'asset_mutation.php'], true) || ($currentPage === 'dashboard.php' && ($_GET['tab'] ?? '') === 'temuan')) {
        $sectionContext = 'ASET & OPERASIONAL IT';
    } elseif (in_array($currentPage, ['memo_pengajuan.php', 'print_memo.php', 'inventaris_mutation.php', 'asset_mutations.php', 'print_bast.php', 'asset_disposal.php', 'print_bast_disposal.php', 'asset_gallery.php', 'inventaris_gallery.php', 'inventory_card.php', 'cetak_kartu.php', 'print_inventory_card.php', 'kartu_detail.php'], true) || ($currentPage === 'dashboard.php' && ($_GET['tab'] ?? '') === 'kartu')) {
        $sectionContext = 'INVENTARIS & SARPRAS';
    } elseif (in_array($currentPage, ['server_room_log.php', 'server_room_print_qr.php', 'audit.php', 'monthly_history.php', 'history.php', 'print_report.php', 'audit_trail.php'], true)) {
        $sectionContext = 'KEAMANAN & AUDIT';
    } elseif (in_array($currentPage, ['cabang_admin.php', 'divisi_admin.php', 'users_admin.php', 'user_biometric_enroll.php', 'qr_admin.php'], true)) {
        $sectionContext = 'PENGATURAN & MASTER';
    }

    $isAdminUser = is_admin();
    $isHrdUser = is_admin_or_hrd();

    $isHrdRole = is_hrd();
    $brandTag = $isHrdRole ? 'SDM & UMUM' : 'IT OPS';
    $displayRole = $isHrdRole ? 'SDM & Umum' : (($role === 'admin') ? 'Administrator' : ucfirst($role));

    // ==========================================
    // SUSUN MENU NAVIGASI SESUAI KATEGORI RESMI
    // ==========================================
    $navLinks = [];

    if ($isHrdRole) {
        // PERAN: SDM & UMUM (SARANA PRASARANA)
        $navLinks['MENU UTAMA'] = [
            ['title' => 'Kartu Inventaris', 'url' => module_url('dashboard.php', ['tab' => 'kartu']), 'icon' => 'bi-credit-card-2-front', 'active' => ($currentPage === 'dashboard.php' && ($_GET['tab'] ?? '') === 'kartu') || in_array($currentPage, ['inventory_card.php', 'cetak_kartu.php', 'print_inventory_card.php', 'kartu_detail.php'], true)],
            ['title' => 'Denah Meja Kantor', 'url' => module_url('floor_plan.php'), 'icon' => 'bi-layout-text-window-reverse', 'active' => ($currentPage === 'floor_plan.php')],
            ['title' => 'Galeri Foto Barang', 'url' => module_url('asset_gallery.php', ['sub' => 'inventaris']), 'icon' => 'bi-images', 'active' => in_array($currentPage, ['asset_gallery.php', 'inventaris_gallery.php'], true)],
        ];
        $navLinks['INVENTARIS & SARPRAS'] = [
            ['title' => 'Memo Pengajuan', 'url' => module_url('memo_pengajuan.php'), 'icon' => 'bi-file-earmark-text', 'active' => in_array($currentPage, ['memo_pengajuan.php', 'print_memo.php'], true)],
            ['title' => 'Mutasi Inventaris', 'url' => module_url('inventaris_mutation.php'), 'icon' => 'bi-arrow-left-right', 'active' => in_array($currentPage, ['inventaris_mutation.php', 'asset_mutations.php', 'print_bast.php'], true)],
            ['title' => 'Penghapusan Aset (Afkir)', 'url' => module_url('asset_disposal.php'), 'icon' => 'bi-trash3', 'active' => in_array($currentPage, ['asset_disposal.php', 'print_bast_disposal.php'], true)],
        ];
        $navLinks['KEAMANAN & KUNJUNGAN'] = [
            ['title' => 'Log Ruang Server', 'url' => module_url('server_room_log.php'), 'icon' => 'bi-door-closed-fill', 'active' => in_array($currentPage, ['server_room_log.php', 'server_room_print_qr.php'], true)],
        ];
        $navLinks['PENGATURAN & MASTER'] = [
            ['title' => 'Kantor Cabang', 'url' => module_url('cabang_admin.php'), 'icon' => 'bi-buildings', 'active' => ($currentPage === 'cabang_admin.php')],
            ['title' => 'Divisi / Unit Kerja', 'url' => module_url('divisi_admin.php'), 'icon' => 'bi-diagram-3', 'active' => ($currentPage === 'divisi_admin.php')],
            ['title' => 'Akun Pengguna', 'url' => module_url('users_admin.php'), 'icon' => 'bi-people', 'active' => ($currentPage === 'users_admin.php')],
        ];
    } elseif (in_array($role, ['auditor', 'skai'], true)) {
        // PERAN: AUDITOR / SKAI
        $navLinks['OPERASIONAL'] = [
            ['title' => 'Dashboard IT Ops', 'url' => module_url('dashboard.php'), 'icon' => 'bi-grid-1x2', 'active' => ($currentPage === 'dashboard.php' && !in_array($_GET['tab'] ?? '', ['kartu', 'temuan'], true))],
            ['title' => 'Data Komputer & PC', 'url' => module_url('assets.php'), 'icon' => 'bi-pc-display', 'active' => in_array($currentPage, ['assets.php', 'asset_add.php', 'asset_edit.php', 'asset_delete.php', 'asset_import.php'], true)],
            ['title' => 'Temuan Kendala IT', 'url' => module_url('dashboard.php', ['tab' => 'temuan']), 'icon' => 'bi-exclamation-triangle', 'active' => ($currentPage === 'dashboard.php' && ($_GET['tab'] ?? '') === 'temuan')],
            ['title' => 'Log Ruang Server', 'url' => module_url('server_room_log.php'), 'icon' => 'bi-door-closed', 'active' => in_array($currentPage, ['server_room_log.php', 'server_room_print_qr.php'], true)],
        ];
        $navLinks['ASET & SARPRAS'] = [
            ['title' => 'Pengadaan & Memo', 'url' => module_url('memo_pengajuan.php'), 'icon' => 'bi-file-earmark-text', 'active' => in_array($currentPage, ['memo_pengajuan.php', 'print_memo.php'], true)],
            ['title' => 'Mutasi Aset', 'url' => module_url('inventaris_mutation.php'), 'icon' => 'bi-arrow-left-right', 'active' => in_array($currentPage, ['inventaris_mutation.php', 'asset_mutation.php', 'asset_mutations.php', 'print_bast.php'], true)],
            ['title' => 'Penghapusan Aset', 'url' => module_url('asset_disposal.php'), 'icon' => 'bi-trash', 'active' => in_array($currentPage, ['asset_disposal.php', 'print_bast_disposal.php'], true)],
        ];

        $masterItems = [];
        if ($isAdminUser) {
            $masterItems[] = ['title' => 'Denah Meja Kantor', 'url' => module_url('floor_plan.php'), 'icon' => 'bi-layout-text-window-reverse', 'active' => ($currentPage === 'floor_plan.php')];
            $masterItems[] = ['title' => 'Kartu Inventaris', 'url' => module_url('dashboard.php', ['tab' => 'kartu']), 'icon' => 'bi-credit-card-2-front', 'active' => ($currentPage === 'dashboard.php' && ($_GET['tab'] ?? '') === 'kartu')];
            $masterItems[] = ['title' => 'Kantor Cabang', 'url' => module_url('cabang_admin.php'), 'icon' => 'bi-buildings', 'active' => ($currentPage === 'cabang_admin.php')];
            $masterItems[] = ['title' => 'Divisi / Unit Kerja', 'url' => module_url('divisi_admin.php'), 'icon' => 'bi-diagram-3', 'active' => ($currentPage === 'divisi_admin.php')];
            $masterItems[] = ['title' => 'Akun Pengguna', 'url' => module_url('users_admin.php'), 'icon' => 'bi-people', 'active' => ($currentPage === 'users_admin.php')];
            $masterItems[] = ['title' => 'Cetak Label QR', 'url' => module_url('qr_admin.php'), 'icon' => 'bi-qr-code', 'active' => ($currentPage === 'qr_admin.php')];
        }
        if (!empty($masterItems)) {
            $navLinks['PENGATURAN & MASTER'] = $masterItems;
        }
    }
    $sidebarMenuHtml = '';
    $sectionIdx = 0;
    foreach ($navLinks as $groupName => $items) {
        if (empty($items)) {
            continue;
        }
        $sidebarMenuHtml .= '<div class="text-[11px] font-bold text-[#53595F] uppercase tracking-wider mb-2 px-3 ' . ($sectionIdx > 0 ? 'mt-6' : '') . '">' . e($groupName) . '</div>';
        $sidebarMenuHtml .= '<div class="flex flex-col gap-1 mb-1">';
        foreach ($items as $item) {
            $isActive = !empty($item['active']);
            $linkClass = $isActive
                ? 'flex items-center h-[40px] px-3 rounded-[8px] bg-[#D9F1EF] text-[#1B2A3A] font-semibold text-[14px] text-decoration-none'
                : 'flex items-center h-[40px] px-3 rounded-[8px] text-[#53595F] hover:bg-[#F4F5F7] hover:text-[#1B2A3A] font-medium text-[14px] transition-colors text-decoration-none';
            $iconColor = $isActive ? 'text-[#0E5C58]' : '';
            $sidebarMenuHtml .= '
              <a class="' . $linkClass . '" href="' . e($item['url']) . '">
                <i class="bi ' . e($item['icon']) . ' ' . $iconColor . ' w-7"></i>
                <span class="truncate">' . e($item['title']) . '</span>
              </a>';
        }
        $sidebarMenuHtml .= '</div>';
        $sectionIdx++;
    }

    $sidebarHtml = '
    <aside class="w-[240px] bg-[#FFFFFF] border-r border-[#D1D5D8] flex flex-col shrink-0 app-sidebar" id="appDesktopSidebar">
      <!-- Logo Area (64px) -->
      <div class="h-[64px] flex items-center px-5 border-b border-[#D1D5D8] sidebar-brand">
        <a href="' . e(module_url('dashboard.php')) . '" class="flex items-center text-decoration-none">
          <i class="bi bi-shield-check text-[#0E5C58] text-[22px] mr-2.5"></i>
          <span class="font-bold text-[15px] tracking-tight text-[#1B2A3A]">QR Maintenance</span>
        </a>
      </div>

      <!-- Nav Links -->
      <div class="flex-1 overflow-y-auto py-5 px-3 flex flex-col gap-1 custom-scrollbar">
        ' . $sidebarMenuHtml . '
      </div>

      <!-- User footer -->
      <div class="p-3 border-t border-[#D1D5D8] bg-[#FFFFFF]">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2 overflow-hidden">
            <div class="w-[34px] h-[34px] rounded-[8px] bg-[#0E5C58] text-white flex items-center justify-center font-bold text-[12px] shrink-0">
              ' . $userInitial . '
            </div>
            <div class="overflow-hidden">
              <div class="text-[13px] font-semibold text-[#1B2A3A] truncate">' . e($userName) . '</div>
              <div class="text-[11px] text-[#53595F] truncate text-capitalize">' . e($displayRole) . '</div>
            </div>
          </div>
          <a href="' . e(module_url('logout.php')) . '" class="text-[#53595F] hover:text-[#A5292B] p-1.5 transition-colors" title="Keluar / Logout">
            <i class="bi bi-box-arrow-right"></i>
          </a>
        </div>
      </div>
    </aside>

    <!-- Offcanvas Mobile Drawer -->
    <div class="offcanvas offcanvas-start bg-white text-dark" tabindex="-1" id="appMobileSidebar" aria-labelledby="appMobileSidebarLabel" style="width: 260px;">
      <div class="offcanvas-header border-b border-[#D1D5D8] py-3 px-4">
        <a href="' . e(module_url('dashboard.php')) . '" class="flex items-center text-decoration-none">
          <i class="bi bi-shield-check text-[#0E5C58] text-[22px] mr-2.5"></i>
          <span class="font-bold text-[15px] tracking-tight text-[#1B2A3A]">QR Maintenance</span>
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body custom-scrollbar p-3">
        ' . $sidebarMenuHtml . '
      </div>
      <div class="p-3 border-t border-[#D1D5D8] bg-[#F4F5F7]">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <div class="w-[32px] h-[32px] rounded-[8px] bg-[#0E5C58] text-white flex items-center justify-center font-bold text-[12px]">
              ' . $userInitial . '
            </div>
            <div>
              <div class="text-[13px] font-semibold text-[#1B2A3A]">' . e($userName) . '</div>
              <div class="text-[11px] text-[#53595F] text-capitalize">' . e($displayRole) . '</div>
            </div>
          </div>
          <a href="' . e(module_url('logout.php')) . '" class="btn btn-sm btn-outline-danger" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
      </div>
    </div>';

    // Topbar HTML
    $topbarHtml = '
    <header class="h-[64px] bg-[#FFFFFF] border-b border-[#D1D5D8] flex items-center justify-between px-6 shrink-0 sticky top-0 z-10 app-topbar">
      <div class="flex items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appMobileSidebar" aria-controls="appMobileSidebar">
          <i class="bi bi-list fs-5"></i>
        </button>
        <div class="font-semibold text-[15px] text-[#1B2A3A] text-truncate">' . e($cleanTitle) . '</div>
      </div>

      <div class="flex items-center gap-4">
        <button type="button" class="flex items-center gap-2 h-[36px] px-3 bg-[#F4F5F7] border border-[#D1D5D8] rounded-[8px] text-[#53595F] text-[13px] hover:bg-[#e9eaec] transition-colors cursor-pointer" data-bs-toggle="modal" data-bs-target="#globalSearchModal" title="Cari aset (Ctrl+K)">
          <i class="bi bi-search"></i>
          <span class="d-none d-sm-inline">Cari aset...</span>
          <kbd class="ml-2 font-mono text-[10px] font-semibold bg-[#FFFFFF] border border-[#D1D5D8] rounded-[4px] px-1.5 py-0.5 text-[#53595F] d-none d-md-inline-block">Ctrl+K</kbd>
        </button>
        <div class="w-px h-6 bg-[#D1D5D8] d-none d-sm-block"></div>
        <button type="button" class="flex items-center gap-1.5 text-[13px] font-semibold text-[#875315] hover:opacity-80 transition-opacity pwa-queue-btn border-0 bg-transparent cursor-pointer" data-bs-toggle="modal" data-bs-target="#modalPWAQueue" title="Antrean Pemeliharaan Offline">
          <i class="bi bi-cloud-slash"></i>
          <span class="pwa-queue-text">2 tertunda</span>
        </button>
        <button type="button" class="text-[#53595F] hover:text-[#1B2A3A] ml-2 text-[18px] transition-colors border-0 bg-transparent cursor-pointer" data-bs-toggle="modal" data-bs-target="#modalPanduanAplikasi" title="Bantuan & Panduan">
          <i class="bi bi-question-circle"></i>
        </button>
        <div class="dropdown">
          <div class="w-[36px] h-[36px] rounded-[8px] bg-[#0E5C58] text-white flex items-center justify-center font-bold text-[13px] ml-2 cursor-pointer shadow-[0_1px_2px_rgba(0,0,0,0.1)]" data-bs-toggle="dropdown" aria-expanded="false" title="Akun Pengguna">
            ' . $userInitial . '
          </div>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm border mt-2">
            <li class="px-3 py-2 border-bottom">
              <div class="fw-bold text-[#1B2A3A]">' . e($userName) . '</div>
              <div class="small text-muted text-capitalize"><i class="bi bi-shield-lock me-1"></i>' . e($displayRole) . '</div>
            </li>
            <li><a class="dropdown-item py-2" href="' . e(module_url('dashboard.php')) . '"><i class="bi bi-grid-1x2 me-2 text-[#0E5C58]"></i> Dashboard IT Ops</a></li>
            <li><a class="dropdown-item py-2" href="' . e(module_url('scanner.php')) . '"><i class="bi bi-qr-code-scan me-2 text-[#0E5C58]"></i> Scan QR Unit</a></li>
            <li><a class="dropdown-item py-2" href="' . e(module_url('assets.php')) . '"><i class="bi bi-pc-display me-2 text-[#0E5C58]"></i> Data Komputer &amp; PC</a></li>
            <li><a class="dropdown-item py-2" href="' . e(module_url('dashboard.php', ['tab' => 'kartu'])) . '"><i class="bi bi-credit-card-2-front me-2 text-warning"></i> Kartu Inventaris</a></li>
            <li><hr class="dropdown-divider my-1"></li>
            <li><a class="dropdown-item py-2 text-danger" href="' . e(module_url('logout.php')) . '"><i class="bi bi-box-arrow-right me-2"></i> Keluar</a></li>
          </ul>
        </div>
      </div>
    </header>';

    $publicHeaderHtml = '
    <header class="public-topbar py-2 px-3 px-md-4 bg-white border-bottom shadow-sm d-flex align-items-center justify-content-between">
      <a href="'.e(module_url('dashboard.php')).'" class="d-flex align-items-center gap-3 text-decoration-none">
        <img src="'.app_logo_url().'" alt="Bank Mitra Logo" class="sidebar-brand-img" style="height: 48px; width: auto; object-fit: contain;">
        <div class="border-start ps-3 d-none d-sm-block">
          <div class="tech-label" style="font-size: 0.68rem; color: #667085;">IT OPERATIONS & INVENTARIS UMUM</div>
        </div>
      </a>
      <div class="d-flex align-items-center gap-2">
        <!-- Tombol Beralih Mode Gelap / Terang -->
        <button type="button" class="theme-toggle-btn shadow-xs" onclick="toggleAppTheme()" title="Beralih Mode Gelap / Terang" aria-label="Toggle Mode Tampilan">
          <i class="bi bi-moon-stars-fill theme-toggle-icon fs-6"></i>
        </button>

        <!-- Tombol Antrean Offline PWA -->
        <button type="button" class="btn btn-sm btn-warning d-none pwa-queue-btn align-items-center gap-1 fw-bold px-2 py-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPWAQueue" title="Antrean Pemeliharaan Offline">
          <i class="bi bi-cloud-arrow-up-fill"></i>
          <span class="d-none d-sm-inline">Antrean:</span>
          <span class="badge bg-dark text-white pwa-queue-badge">0</span>
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
<script>
(function() {
  try {
    var storedTheme = localStorage.getItem("app_theme");
    var theme = "light";
    if (storedTheme === "dark" || storedTheme === "light") {
      theme = storedTheme;
    } else if (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) {
      theme = "dark";
    }
    document.documentElement.setAttribute("data-bs-theme", theme);
    document.documentElement.setAttribute("data-theme", theme);
  } catch(e) {}
})();
</script>
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
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" media="print" onload="this.media=\'all\'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"></noscript>
<script src="https://unpkg.com/@tailwindcss/browser@4.3.3/dist/index.global.js" integrity="sha384-2ql948lIdLcGEE0/qxNiudyTjgauA3RDJERu5xW75kFCvSl5a9odyQYCb6tEjnmB" crossorigin="anonymous"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
/* =============================================================
   QR MAINTENANCE DESIGN SYSTEM (KOMBAI TEAL PALETTE)
   ============================================================= */
:root {
  --primary: #0E5C58;
  --primary-hover: #0c4f4b;
  --primary-light: #D9F1EF;
  --bg-app: #F4F5F7;
  --surface-card: #FFFFFF;
  --border-subtle: #D1D5D8;
  --border-strong: #A0A5AA;
  --text-primary: #1B2A3A;
  --text-secondary: #53595F;
  --text-muted: #A0A5AA;
  --status-warning: #875315;
  --status-danger: #A5292B;

  --materio-primary: #0E5C58;
  --materio-primary-hover: #0c4f4b;
  --materio-primary-active: #093c39;
  --materio-primary-rgb: 14, 92, 88;
  --materio-primary-gradient: linear-gradient(72.47deg, #0E5C58 22.16%, #16803C 76.47%);
  --materio-primary-subtle: #D9F1EF;
  --materio-primary-border: rgba(14, 92, 88, 0.28);
}

body {
  font-family: "Plus Jakarta Sans", sans-serif !important;
  background-color: #F4F5F7 !important;
  color: #1B2A3A !important;
  margin: 0;
}
.tabular-nums {
  font-variant-numeric: tabular-nums;
}
::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  background: #D1D5D8;
  border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
  background: #A0A5AA;
}

.app-sidebar {
  width: 240px !important;
  min-width: 240px !important;
  background: #FFFFFF !important;
  border-right: 1px solid #D1D5D8 !important;
  box-shadow: none !important;
}

.app-main-viewport {
  margin-left: 240px !important;
  width: calc(100% - 240px) !important;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: #F4F5F7 !important;
}

@media (max-width: 991.98px) {
  .app-sidebar {
    display: none !important;
  }
  .app-main-viewport {
    margin-left: 0 !important;
    width: 100% !important;
  }
}

.app-content-container {
  flex: 1;
  padding: 24px !important;
  background-color: #F4F5F7 !important;
}

.btn-primary {
  background-color: #0E5C58 !important;
  border-color: #0E5C58 !important;
  color: #FFFFFF !important;
  border-radius: 8px !important;
  font-weight: 600 !important;
}
.btn-primary:hover, .btn-primary:focus {
  background-color: #0c4f4b !important;
  border-color: #0c4f4b !important;
}

:root {
  /* Materio Brand Palette */
  --materio-primary: #8C57FF;
  --materio-primary-hover: #7E4EE6;
  --materio-primary-active: #7135FF;
  --materio-primary-rgb: 140, 87, 255;
  --materio-primary-gradient: linear-gradient(72.47deg, #8C57FF 22.16%, #BA9BFB 76.47%);
  --materio-primary-subtle: rgba(140, 87, 255, 0.12);
  --materio-primary-border: rgba(140, 87, 255, 0.28);
  --materio-secondary: #8A8D93;
  --materio-secondary-subtle: rgba(138, 141, 147, 0.12);
  --materio-success: #56CA00;
  --materio-success-subtle: rgba(86, 202, 0, 0.12);
  --materio-warning: #FFB400;
  --materio-warning-subtle: rgba(255, 180, 0, 0.12);
  --materio-error: #FF4C51;
  --materio-error-subtle: rgba(255, 76, 81, 0.12);
  --materio-info: #16B1FF;
  --materio-info-subtle: rgba(22, 177, 255, 0.12);

  /* Light Theme Surface & Spacing */
  --bg-app: #F4F5FA;
  --surface-card: #FFFFFF;
  --border-subtle: rgba(76, 78, 100, 0.12);
  --border-strong: rgba(76, 78, 100, 0.22);
  --text-primary: #2E263D;
  --text-secondary: #6D6777;
  --text-muted: #8A8D93;
  --shadow-subtle: 0 1px 3px rgba(76, 78, 100, 0.05);
  --shadow-card: 0px 2px 10px 0px rgba(76, 78, 100, 0.08);
  --shadow-hover: 0px 6px 18px 0px rgba(76, 78, 100, 0.14);
  --radius-xs: 6px;
  --radius-sm: 8px;
  --radius-md: 10px;
  --radius-lg: 12px;
  --radius-xl: 16px;
  --radius-pill: 9999px;

  /* Backward Compatibility Mappings */
  --blue-accent: var(--materio-primary);
  --blue-corporate: var(--materio-primary);
  --navy-deep: #2E263D;
  --navy-primary: #38304D;
  --status-success: var(--materio-success);
  --status-warning: var(--materio-warning);
  --status-danger: var(--materio-error);
  --status-info: var(--materio-info);
}

/* Materio Dark Mode Palette */
@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]):not([data-bs-theme="light"]) {
    --bg-app: #232333;
    --surface-card: #2B2C40;
    --border-subtle: rgba(255, 255, 255, 0.12);
    --border-strong: rgba(255, 255, 255, 0.22);
    --text-primary: #E7E3FC;
    --text-secondary: #B2B0C4;
    --text-muted: #7E7B92;
    --shadow-card: 0px 4px 16px 0px rgba(0, 0, 0, 0.4);
    --shadow-hover: 0px 8px 24px 0px rgba(0, 0, 0, 0.5);
  }
}

[data-theme="dark"], [data-bs-theme="dark"] {
  --bg-app: #232333;
  --surface-card: #2B2C40;
  --border-subtle: rgba(255, 255, 255, 0.12);
  --border-strong: rgba(255, 255, 255, 0.22);
  --text-primary: #E7E3FC;
  --text-secondary: #B2B0C4;
  --text-muted: #7E7B92;
  --shadow-card: 0px 4px 16px 0px rgba(0, 0, 0, 0.4);
  --shadow-hover: 0px 8px 24px 0px rgba(0, 0, 0, 0.5);
}

[data-theme="light"], [data-bs-theme="light"] {
  --bg-app: #F4F5FA;
  --surface-card: #FFFFFF;
  --border-subtle: rgba(76, 78, 100, 0.12);
  --border-strong: rgba(76, 78, 100, 0.22);
  --text-primary: #2E263D;
  --text-secondary: #6D6777;
  --text-muted: #8A8D93;
  --shadow-subtle: 0 1px 3px rgba(76, 78, 100, 0.05);
  --shadow-card: 0px 2px 10px 0px rgba(76, 78, 100, 0.08);
  --shadow-hover: 0px 6px 18px 0px rgba(76, 78, 100, 0.14);
}

/* Materio Theme Toggle Button */
.theme-toggle-btn {
  width: 36px;
  height: 36px;
  min-width: 36px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--border-subtle);
  background-color: var(--surface-card);
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  padding: 0;
  flex-shrink: 0;
}
.theme-toggle-btn:hover {
  background-color: var(--materio-secondary-subtle);
  color: var(--materio-primary);
  border-color: var(--materio-primary-border);
  transform: translateY(-1px);
}
[data-bs-theme="dark"] .theme-toggle-btn {
  background-color: rgba(255, 255, 255, 0.06);
  border-color: rgba(255, 255, 255, 0.14);
  color: #FFB400;
}
[data-bs-theme="dark"] .theme-toggle-btn:hover {
  background-color: rgba(255, 180, 0, 0.16);
  border-color: rgba(255, 180, 0, 0.35);
  color: #FFC033;
}
[data-bs-theme="dark"] .dropdown-menu .text-dark {
  color: #E7E3FC !important;
}

/* Typography across all components */
body, input, button, select, textarea, optgroup,
.btn, .form-control, .form-select, .form-check-label,
.card, .modal, .dropdown-menu, .dropdown-item, .popover,
.tooltip, .nav, .nav-link, .table, .badge, .badge-chip,
.alert, .toast, .breadcrumb, .pagination, .page-link {
  font-family: "Public Sans", "Plus Jakarta Sans", system-ui, -apple-system, sans-serif !important;
}

body {
  background-color: var(--bg-app) !important;
  background-image: none !important;
  color: var(--text-primary) !important;
}

/* Materio Semi-Dark Vertical Navigation (High-Contrast ThemeSelection Style) */
.app-sidebar,
.offcanvas#appMobileSidebar {
  width: 260px !important;
  min-width: 260px !important;
  background: #28243D !important;
  border-right: 1px solid rgba(255, 255, 255, 0.08) !important;
  box-shadow: 0 0 20px 0 rgba(0, 0, 0, 0.25) !important;
}

@media (min-width: 992px) {
  .app-main-viewport {
    margin-left: 260px !important;
    width: calc(100% - 260px) !important;
  }
}

/* ============================================================
   RESPONSIVE LAYOUT ENGINE FOR ALL LAPTOP RESOLUTIONS
   Supports: 1280x720, 1280x800, 1366x768, 1440x900, 1536x864, 1920x1080
   ============================================================ */
@media (min-width: 992px) and (max-width: 1399.98px) {
  .app-sidebar {
    width: 235px !important;
    min-width: 235px !important;
  }
  .app-main-viewport {
    margin-left: 235px !important;
    width: calc(100% - 235px) !important;
  }
  .sidebar-link {
    margin: 2px 8px !important;
    padding: 8px 10px !important;
    font-size: 0.84rem !important;
    gap: 9px !important;
  }
  .sidebar-section-title {
    padding: 8px 12px 3px !important;
    font-size: 0.63rem !important;
  }
  .sidebar-brand {
    padding: 14px 14px 12px !important;
  }
  .sidebar-footer {
    padding: 10px 12px !important;
  }
}

.topbar-context {
  max-width: clamp(160px, 22vw, 360px) !important;
  min-width: 0 !important;
  flex-shrink: 1 !important;
}
.topbar-page-title {
  overflow: hidden !important;
  text-overflow: ellipsis !important;
  white-space: nowrap !important;
  display: inline-block !important;
  max-width: 100% !important;
}

@media (max-width: 1240px) {
  .topbar-search-btn {
    padding: 6px 10px !important;
  }
  .topbar-search-btn .topbar-kbd {
    display: none !important;
  }
  .topbar-status-badge .status-text {
    display: none !important;
  }
  .topbar-status-badge {
    padding: 6px 8px !important;
  }
}

.container-fluid {
  padding-left: clamp(12px, 1.8vw, 24px) !important;
  padding-right: clamp(12px, 1.8vw, 24px) !important;
}

.card-hero-neo,
.hero-gradient-header {
  padding: clamp(18px, 2.2vw, 28px) !important;
  border-radius: clamp(12px, 1.4vw, 18px) !important;
  margin-bottom: clamp(16px, 1.8vw, 24px) !important;
}

.card-hero-neo h2,
.card-hero-neo .display-6,
.hero-gradient-header h1,
.hero-gradient-header h2 {
  font-size: clamp(1.20rem, 2vw, 1.85rem) !important;
  line-height: 1.25 !important;
}

.card-hero-neo p,
.hero-gradient-header p {
  font-size: clamp(0.80rem, 1vw, 0.94rem) !important;
  line-height: 1.4 !important;
  margin-bottom: clamp(10px, 1.2vw, 16px) !important;
}

.card-hero-neo .btn,
.card-hero-neo .btn-hero-pill,
.hero-gradient-header .btn {
  font-size: clamp(0.78rem, 0.95vw, 0.88rem) !important;
  padding: clamp(6px, 0.8vw, 9px) clamp(12px, 1.2vw, 18px) !important;
  margin: 3px 6px 3px 0 !important;
  white-space: nowrap !important;
}

.card-hero-neo .d-flex.flex-wrap,
.hero-btn-group,
.gap-2\.5 {
  gap: 12px !important;
}

@media (max-width: 1199.98px) {
  .card-hero-neo .d-none.d-lg-flex,
  .card-hero-neo .d-none.d-md-flex,
  .hero-gradient-header .hero-sun-container,
  .hero-gradient-header .hero-pin-marker {
    display: none !important;
  }
}

.card-metric-modern,
.card-metric {
  padding: clamp(14px, 1.6vw, 22px) !important;
}
.card-metric-modern .metric-number-bold,
.card-metric .metric-value {
  font-size: clamp(1.30rem, 1.8vw, 1.85rem) !important;
}
.card-metric-modern .metric-title-muted,
.card-metric .metric-label {
  font-size: clamp(0.72rem, 0.85vw, 0.82rem) !important;
}

.table-responsive {
  overflow-x: auto !important;
  -webkit-overflow-scrolling: touch !important;
  scrollbar-width: thin !important;
}

@media (max-height: 820px) {
  .modal-dialog {
    margin-top: 0.75rem !important;
    margin-bottom: 0.75rem !important;
  }
  .modal-body {
    max-height: calc(100vh - 150px) !important;
    overflow-y: auto !important;
  }
}

.sidebar-brand {
  padding: 18px 20px 16px !important;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
  background: #28243D !important;
}

.sidebar-section-title {
  display: flex !important;
  align-items: center !important;
  font-size: 0.68rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.08em !important;
  color: rgba(231, 227, 252, 0.42) !important;
  padding: 14px 18px 6px !important;
  white-space: nowrap !important;
}
.sidebar-section-title::after {
  content: "" !important;
  flex-grow: 1 !important;
  height: 1px !important;
  background: rgba(231, 227, 252, 0.12) !important;
  margin-left: 10px !important;
}

.sidebar-link {
  margin: 3px 14px !important;
  padding: 10px 14px !important;
  border-radius: 8px !important;
  font-weight: 500 !important;
  font-size: 0.90rem !important;
  color: #D8D6DE !important;
  border-left: none !important;
  display: flex !important;
  align-items: center !important;
  gap: 12px !important;
  transition: all 0.2s ease !important;
}

.sidebar-link .sidebar-icon {
  font-size: 1.15rem !important;
  color: #A8A5B6 !important;
  transition: color 0.2s ease !important;
}

.sidebar-link:hover {
  background: rgba(255, 255, 255, 0.08) !important;
  color: #FFFFFF !important;
  transform: translateX(3px) !important;
}

.sidebar-link:hover .sidebar-icon {
  color: #FFFFFF !important;
}

.sidebar-link.active {
  background: linear-gradient(72.47deg, #8C57FF 0%, #7135FF 100%) !important;
  color: #FFFFFF !important;
  box-shadow: 0px 4px 14px rgba(140, 87, 255, 0.5) !important;
  font-weight: 700 !important;
  border-left: none !important;
}

.sidebar-link.active .sidebar-icon,
.sidebar-link.active .sidebar-text,
.sidebar-link.active i,
.sidebar-link.active span {
  color: #FFFFFF !important;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
}

.sidebar-footer {
  padding: 14px 18px !important;
  background: #232035 !important;
  border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
}

.sidebar-user-name {
  color: #FFFFFF !important;
  font-weight: 600 !important;
}

.sidebar-user-role {
  color: #8A8D93 !important;
}

.sidebar-user-avatar {
  background: #8C57FF !important;
  color: #FFFFFF !important;
  border-radius: 50% !important;
}

/* Offcanvas Mobile Sidebar */
.offcanvas.bg-navy-dark {
  background-color: #28243D !important;
  color: #FFFFFF !important;
  border-right: 1px solid rgba(255, 255, 255, 0.08) !important;
}
.offcanvas .border-navy-subtle {
  border-color: rgba(255, 255, 255, 0.08) !important;
}

/* Materio AppBar / Topbar */
.app-topbar, .public-topbar {
  background-color: rgba(255, 255, 255, 0.88) !important;
  backdrop-filter: blur(8px) !important;
  border-bottom: 1px solid var(--border-subtle) !important;
  box-shadow: 0 2px 10px rgba(76, 78, 100, 0.04) !important;
}
[data-bs-theme="dark"] .app-topbar,
[data-bs-theme="dark"] .public-topbar {
  background-color: rgba(43, 44, 64, 0.88) !important;
  border-bottom: 1px solid rgba(255, 255, 255, 0.12) !important;
}

/* Materio Card Architecture */
.card {
  border-radius: 10px !important;
  border: 1px solid var(--border-subtle) !important;
  box-shadow: var(--shadow-card) !important;
  background-color: var(--surface-card) !important;
  transition: box-shadow 0.2s ease, transform 0.2s ease !important;
}

.card:hover {
  box-shadow: var(--shadow-hover) !important;
}

.card-header {
  background-color: transparent !important;
  border-bottom: 1px solid var(--border-subtle) !important;
  padding: 16px 20px !important;
  font-weight: 600 !important;
}

/* Materio Buttons */
.btn-primary {
  background-color: var(--materio-primary) !important;
  border-color: var(--materio-primary) !important;
  color: #FFFFFF !important;
  box-shadow: 0px 2px 6px rgba(140, 87, 255, 0.35) !important;
  border-radius: 8px !important;
  font-weight: 500 !important;
  transition: all 0.2s ease !important;
}

.btn-primary:hover, .btn-primary:focus {
  background-color: var(--materio-primary-hover) !important;
  border-color: var(--materio-primary-hover) !important;
  box-shadow: 0px 4px 10px rgba(140, 87, 255, 0.45) !important;
  transform: translateY(-1px) !important;
}

.btn-outline-primary {
  color: var(--materio-primary) !important;
  border-color: var(--materio-primary) !important;
  border-radius: 8px !important;
  font-weight: 500 !important;
}

.btn-outline-primary:hover {
  background-color: var(--materio-primary-subtle) !important;
  color: var(--materio-primary) !important;
  border-color: var(--materio-primary) !important;
}

.btn-success {
  background-color: var(--materio-success) !important;
  border-color: var(--materio-success) !important;
  box-shadow: 0px 2px 6px rgba(86, 202, 0, 0.35) !important;
  border-radius: 8px !important;
}

.btn-warning {
  background-color: var(--materio-warning) !important;
  border-color: var(--materio-warning) !important;
  color: #FFFFFF !important;
  box-shadow: 0px 2px 6px rgba(255, 180, 0, 0.35) !important;
  border-radius: 8px !important;
}

.btn-danger {
  background-color: var(--materio-error) !important;
  border-color: var(--materio-error) !important;
  box-shadow: 0px 2px 6px rgba(255, 76, 81, 0.35) !important;
  border-radius: 8px !important;
}

/* Materio Chip / Badge Architecture */
.badge-chip, .badge {
  border-radius: 16px !important;
  font-weight: 600 !important;
  font-size: 0.75rem !important;
  padding: 4px 10px !important;
  border: none !important;
}

.chip-primary, .badge.bg-primary {
  background-color: var(--materio-primary-subtle) !important;
  color: var(--materio-primary) !important;
}

.chip-success, .badge.bg-success {
  background-color: var(--materio-success-subtle) !important;
  color: var(--materio-success) !important;
}

.chip-warning, .badge.bg-warning {
  background-color: var(--materio-warning-subtle) !important;
  color: var(--materio-warning) !important;
}

.chip-danger, .badge.bg-danger {
  background-color: var(--materio-error-subtle) !important;
  color: var(--materio-error) !important;
}

.chip-info, .badge.bg-info {
  background-color: var(--materio-info-subtle) !important;
  color: var(--materio-info) !important;
}

.chip-secondary, .badge.bg-secondary {
  background-color: var(--materio-secondary-subtle) !important;
  color: var(--materio-secondary) !important;
}

/* Materio Tables */
.table thead th {
  background-color: #F9FAFC !important;
  color: var(--text-secondary) !important;
  font-size: 0.75rem !important;
  letter-spacing: 0.05em !important;
  text-transform: uppercase !important;
  font-weight: 600 !important;
  border-bottom: 1px solid var(--border-subtle) !important;
  padding: 12px 16px !important;
}

[data-bs-theme="dark"] .table thead th {
  background-color: #31324B !important;
  color: #B2B0C4 !important;
}

.table tbody td {
  padding: 12px 16px !important;
  vertical-align: middle !important;
  border-bottom: 1px solid var(--border-subtle) !important;
}

.table tbody tr:hover td {
  background-color: rgba(140, 87, 255, 0.04) !important;
}

/* Materio Inputs */
.form-control, .form-select {
  border-radius: 8px !important;
  border: 1px solid var(--border-strong) !important;
  font-size: 0.92rem !important;
  transition: all 0.2s ease !important;
}

.form-control:focus, .form-select:focus {
  border-color: var(--materio-primary) !important;
  box-shadow: 0 0 0 3px var(--materio-primary-subtle) !important;
}

/* Materio Hero Cards & Accent Gradients */
.card-hero-neo {
  border-radius: 12px !important;
  box-shadow: 0 6px 20px rgba(140, 87, 255, 0.25) !important;
}
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

/* MATERIO DESIGN SYSTEM CORE DESIGN TOKENS */
:root {
  --navy-deep: #28243D;
  --navy-primary: #312D4B;
  --navy-subtle: #3D3759;
  --blue-corporate: var(--materio-primary, #8C57FF);
  --blue-accent: var(--materio-primary, #8C57FF);
  --blue-soft: var(--materio-primary-subtle, rgba(140, 87, 255, 0.12));
  --bg-app: #F4F5FA;
  --surface-card: #FFFFFF;
  --border-subtle: rgba(76, 78, 100, 0.12);
  --border-strong: rgba(76, 78, 100, 0.22);
  --text-primary: #2E263D;
  --text-secondary: #6D6777;
  --text-muted: #8A8D93;
  --status-success: #56CA00;
  --status-success-bg: rgba(86, 202, 0, 0.12);
  --status-warning: #FFB400;
  --status-warning-bg: rgba(255, 180, 0, 0.12);
  --status-danger: #FF4C51;
  --status-danger-bg: rgba(255, 76, 81, 0.12);
  --status-info: #16B1FF;
  --status-info-bg: rgba(22, 177, 255, 0.12);
  --radius-xs: 6px;
  --radius-sm: 8px;
  --radius-md: 10px;
  --radius-lg: 12px;
  --radius-xl: 16px;
  --radius-pill: 9999px;
  --shadow-subtle: 0 1px 3px rgba(76, 78, 100, 0.05);
  --shadow-card: 0px 2px 10px 0px rgba(76, 78, 100, 0.08);
  --shadow-hover: 0px 6px 18px 0px rgba(76, 78, 100, 0.14);
}

/* -------------------------------------------------------------
   DEVICE AUTO DARK MODE & THEME DESIGN TOKENS (MATERIO NEXT.JS)
   ------------------------------------------------------------- */
@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]):not([data-bs-theme="light"]) {
    --navy-deep: #1E1B2E;
    --navy-primary: #28243D;
    --navy-subtle: #312D4B;
    --bg-app: #232333;
    --surface-card: #2B2C40;
    --border-subtle: rgba(255, 255, 255, 0.12);
    --border-strong: rgba(255, 255, 255, 0.22);
    --text-primary: #E7E3FC;
    --text-secondary: #B2B0C4;
    --text-muted: #7E7B92;
    --status-success-bg: rgba(86, 202, 0, 0.18);
    --status-warning-bg: rgba(255, 180, 0, 0.18);
    --status-danger-bg: rgba(255, 76, 81, 0.18);
    --status-info-bg: rgba(22, 177, 255, 0.18);
    --shadow-subtle: 0 1px 3px rgba(0, 0, 0, 0.3);
    --shadow-card: 0px 4px 16px 0px rgba(0, 0, 0, 0.4);
    --shadow-hover: 0px 8px 24px 0px rgba(0, 0, 0, 0.5);
  }
}

[data-theme="dark"], [data-bs-theme="dark"] {
  --navy-deep: #1E1B2E;
  --navy-primary: #28243D;
  --navy-subtle: #312D4B;
  --bg-app: #232333;
  --surface-card: #2B2C40;
  --border-subtle: rgba(255, 255, 255, 0.12);
  --border-strong: rgba(255, 255, 255, 0.22);
  --text-primary: #E7E3FC;
  --text-secondary: #B2B0C4;
  --text-muted: #7E7B92;
  --status-success-bg: rgba(86, 202, 0, 0.18);
  --status-warning-bg: rgba(255, 180, 0, 0.18);
  --status-danger-bg: rgba(255, 76, 81, 0.18);
  --status-info-bg: rgba(22, 177, 255, 0.18);
  --shadow-subtle: 0 1px 3px rgba(0, 0, 0, 0.3);
  --shadow-card: 0px 4px 16px 0px rgba(0, 0, 0, 0.4);
  --shadow-hover: 0px 8px 24px 0px rgba(0, 0, 0, 0.5);
}

/* Dark Mode Global & Component Overrides (Materio Pure Architecture) */
[data-bs-theme="dark"] body {
  background-color: var(--bg-app) !important;
  background-image: none !important;
  color: var(--text-primary) !important;
}

[data-bs-theme="dark"] .app-topbar,
[data-bs-theme="dark"] .public-topbar {
  background: rgba(43, 44, 64, 0.92) !important;
  border-bottom-color: rgba(255, 255, 255, 0.12) !important;
}

[data-bs-theme="dark"] .topbar-search-btn {
  background: rgba(255, 255, 255, 0.06) !important;
  border-color: rgba(255, 255, 255, 0.12) !important;
  color: #B2B0C4 !important;
}

[data-bs-theme="dark"] .topbar-search-btn .topbar-kbd {
  background: rgba(255, 255, 255, 0.12) !important;
  border-color: rgba(255, 255, 255, 0.18) !important;
  color: #E7E3FC !important;
}

[data-bs-theme="dark"] .card {
  background-color: var(--surface-card) !important;
  border-color: var(--border-subtle) !important;
  color: var(--text-primary) !important;
}

[data-bs-theme="dark"] .card-header,
[data-bs-theme="dark"] .card-footer {
  background-color: transparent !important;
  border-color: var(--border-subtle) !important;
  color: var(--text-primary) !important;
}

[data-bs-theme="dark"] .card-metric-modern {
  background: var(--surface-card) !important;
  border-color: var(--border-subtle) !important;
  box-shadow: 0px 4px 16px 0px rgba(0, 0, 0, 0.4) !important;
}

[data-bs-theme="dark"] .card-metric-modern .metric-title-muted {
  color: #8A8D93 !important;
}

[data-bs-theme="dark"] .card-metric-modern .metric-number-bold {
  color: #E7E3FC !important;
}

[data-bs-theme="dark"] .mutation-card {
  background-color: var(--surface-card) !important;
  border-color: var(--border-subtle) !important;
}

[data-bs-theme="dark"] .mutation-card-header {
  background-color: rgba(255, 255, 255, 0.03) !important;
  border-bottom-color: var(--border-subtle) !important;
}

[data-bs-theme="dark"] .mutation-card-header h5 {
  color: var(--text-primary) !important;
}

[data-bs-theme="dark"] .location-box {
  background-color: rgba(86, 202, 0, 0.08) !important;
  border-color: rgba(86, 202, 0, 0.35) !important;
  color: var(--text-primary) !important;
}

[data-bs-theme="dark"] .info-bast {
  background-color: rgba(22, 177, 255, 0.08) !important;
  border-left-color: #16B1FF !important;
  color: var(--text-primary) !important;
}

[data-bs-theme="dark"] .btn-cancel-mutation {
  background-color: rgba(255, 255, 255, 0.08) !important;
  color: #B2B0C4 !important;
  border-color: rgba(255, 255, 255, 0.14) !important;
}

[data-bs-theme="dark"] .browser-window-card {
  background-color: var(--surface-card) !important;
  border-color: var(--border-subtle) !important;
}

[data-bs-theme="dark"] .browser-address-pill {
  background-color: rgba(255, 255, 255, 0.06) !important;
  border-color: rgba(255, 255, 255, 0.12) !important;
  color: #E7E3FC !important;
}

[data-bs-theme="dark"] .floating-toast-pill {
  background-color: rgba(255, 255, 255, 0.06) !important;
  border-color: rgba(255, 255, 255, 0.12) !important;
}

[data-bs-theme="dark"] .card-status-mint {
  background: rgba(16, 185, 129, 0.12) !important;
  border-color: rgba(16, 185, 129, 0.28) !important;
}

[data-bs-theme="dark"] .card-status-mint .status-heading {
  color: #34D399 !important;
}

[data-bs-theme="dark"] .card-status-mint .status-subtext {
  color: #6EE7B7 !important;
}

[data-bs-theme="dark"] .browser-mockup-frame {
  background: #0B1322 !important;
  border-color: #1E2D4A !important;
}

[data-bs-theme="dark"] .browser-address-pill {
  background: #121F36 !important;
  border-color: #1E2D4A !important;
  color: #94A3B8 !important;
}

[data-bs-theme="dark"] .badge-floating-trend {
  background: #0F1A30 !important;
  border-color: #1E2D4A !important;
  box-shadow: 0 10px 25px -4px rgba(0, 0, 0, 0.5) !important;
}

[data-bs-theme="dark"] .badge-floating-trend .text-dark {
  color: #F8FAFC !important;
}

[data-bs-theme="dark"] .trend-chart-box {
  background: linear-gradient(180deg, rgba(16, 185, 129, 0.08) 0%, rgba(6, 182, 212, 0.06) 100%) !important;
  border-color: #1E2D4A !important;
}

[data-bs-theme="dark"] .table {
  color: var(--text-primary) !important;
  border-color: var(--border-subtle) !important;
}

[data-bs-theme="dark"] .table > :not(caption) > * > * {
  background-color: transparent !important;
  color: var(--text-primary) !important;
  border-color: var(--border-subtle) !important;
}

[data-bs-theme="dark"] .table tbody tr:hover {
  background-color: rgba(30, 58, 138, 0.2) !important;
}

[data-bs-theme="dark"] .table thead th {
  background-color: #0C172B !important;
  color: #94A3B8 !important;
  border-color: #1E2D4A !important;
}

[data-bs-theme="dark"] .form-control,
[data-bs-theme="dark"] .form-select {
  background-color: #11203A !important;
  border-color: #1E2D4A !important;
  color: #F8FAFC !important;
}

[data-bs-theme="dark"] .form-control:focus,
[data-bs-theme="dark"] .form-select:focus {
  background-color: #142544 !important;
  border-color: #2563EB !important;
  color: #FFFFFF !important;
  box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.25) !important;
}

[data-bs-theme="dark"] .input-group-text {
  background-color: #0E1A30 !important;
  border-color: #1E2D4A !important;
  color: #8496B0 !important;
}

[data-bs-theme="dark"] .bg-white {
  background-color: #0F1A30 !important;
}

[data-bs-theme="dark"] .bg-light {
  background-color: #13223B !important;
}

[data-bs-theme="dark"] .border-bottom,
[data-bs-theme="dark"] .border-top,
[data-bs-theme="dark"] .border-start,
[data-bs-theme="dark"] .border-end,
[data-bs-theme="dark"] .border {
  border-color: #1E2D4A !important;
}

[data-bs-theme="dark"] .text-dark {
  color: #F8FAFC !important;
}

[data-bs-theme="dark"] .text-muted {
  color: #8496B0 !important;
}

[data-bs-theme="dark"] .dropdown-menu {
  background-color: #0F1A30 !important;
  border-color: #1E2D4A !important;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
}

[data-bs-theme="dark"] .dropdown-item {
  color: #E2E8F0 !important;
}

[data-bs-theme="dark"] .dropdown-item:hover,
[data-bs-theme="dark"] .dropdown-item:focus {
  background-color: #1A2C4E !important;
  color: #FFFFFF !important;
}

[data-bs-theme="dark"] .dropdown-divider {
  border-color: #1E2D4A !important;
}

[data-bs-theme="dark"] .modal-content {
  background-color: #0F1A30 !important;
  border-color: #1E2D4A !important;
  color: #F8FAFC !important;
}

[data-bs-theme="dark"] .modal-header,
[data-bs-theme="dark"] .modal-footer {
  border-color: #1E2D4A !important;
}

[data-bs-theme="dark"] .btn-close {
  filter: invert(1) grayscale(100%) brightness(200%);
}

[data-bs-theme="dark"] .btn-outline-secondary {
  border-color: #334A70 !important;
  color: #CBD5E1 !important;
}

[data-bs-theme="dark"] .btn-outline-secondary:hover {
  background-color: #1E2D4A !important;
  color: #FFFFFF !important;
}

[data-bs-theme="dark"] .branch-nav-pill {
  background: #0F1A30 !important;
  border-color: #1E2D4A !important;
  color: #CBD5E1 !important;
}

[data-bs-theme="dark"] .branch-nav-pill:hover {
  background: #1A2C4E !important;
  color: #FFFFFF !important;
}

[data-bs-theme="dark"] .branch-nav-pill.active {
  background: #1D4ED8 !important;
  border-color: #2563EB !important;
  color: #FFFFFF !important;
}

/* Comprehensive Dark Mode Components (Buttons, Tables, Headers, Badges) */
[data-bs-theme="dark"] .btn-secondary,
[data-bs-theme="dark"] .btn-light {
  background-color: #1A2844 !important;
  border-color: #2D3F63 !important;
  color: #E2E8F0 !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3) !important;
}

[data-bs-theme="dark"] .btn-secondary:hover,
[data-bs-theme="dark"] .btn-light:hover {
  background-color: #24365A !important;
  border-color: #435B88 !important;
  color: #FFFFFF !important;
}

[data-bs-theme="dark"] .btn-secondary i,
[data-bs-theme="dark"] .btn-light i,
[data-bs-theme="dark"] .btn-light .bi {
  color: #CBD5E1 !important;
}

[data-bs-theme="dark"] .btn-secondary:hover i,
[data-bs-theme="dark"] .btn-light:hover i,
[data-bs-theme="dark"] .btn-light:hover .bi {
  color: #FFFFFF !important;
}

[data-bs-theme="dark"] .btn-group > .btn-light {
  border-color: #2D3F63 !important;
}

[data-bs-theme="dark"] .btn-outline-primary {
  background-color: rgba(140, 87, 255, 0.12) !important;
  border-color: rgba(140, 87, 255, 0.45) !important;
  color: #C2A4FF !important;
}

[data-bs-theme="dark"] .btn-outline-primary:hover {
  background-color: #8C57FF !important;
  border-color: #8C57FF !important;
  color: #FFFFFF !important;
}

[data-bs-theme="dark"] .btn-outline-primary i,
[data-bs-theme="dark"] .btn-outline-primary .bi {
  color: #C2A4FF !important;
}

[data-bs-theme="dark"] .btn-outline-primary:hover i,
[data-bs-theme="dark"] .btn-outline-primary:hover .bi {
  color: #FFFFFF !important;
}

[data-bs-theme="dark"] .btn-outline-success {
  background-color: rgba(86, 202, 0, 0.1) !important;
  border-color: rgba(86, 202, 0, 0.35) !important;
  color: #6EDB10 !important;
}

[data-bs-theme="dark"] .btn-outline-success:hover {
  background-color: #56CA00 !important;
  border-color: #56CA00 !important;
  color: #FFFFFF !important;
}

[data-bs-theme="dark"] .bg-white {
  background-color: var(--surface-card, #2B2C40) !important;
}

[data-bs-theme="dark"] .bg-light {
  background-color: rgba(255, 255, 255, 0.05) !important;
  color: var(--text-primary, #E7E3FC) !important;
}

[data-bs-theme="dark"] .text-dark {
  color: #E7E3FC !important;
}

[data-bs-theme="dark"] .text-body {
  color: #E7E3FC !important;
}

[data-bs-theme="dark"] .text-secondary {
  color: #B2B0C4 !important;
}

[data-bs-theme="dark"] .text-muted {
  color: #9E9BB2 !important;
}

[data-bs-theme="dark"] .border,
[data-bs-theme="dark"] .border-top,
[data-bs-theme="dark"] .border-bottom,
[data-bs-theme="dark"] .border-start,
[data-bs-theme="dark"] .border-end {
  border-color: rgba(255, 255, 255, 0.1) !important;
}

[data-bs-theme="dark"] .table-light,
[data-bs-theme="dark"] thead.table-light,
[data-bs-theme="dark"] thead.table-light th,
[data-bs-theme="dark"] .table thead.table-light th {
  background-color: #121F36 !important;
  color: #94A3B8 !important;
  border-color: #1E2D4A !important;
}

[data-bs-theme="dark"] .table {
  --bs-table-bg: transparent;
  --bs-table-color: var(--text-primary);
  border-color: rgba(255, 255, 255, 0.08) !important;
}

[data-bs-theme="dark"] .table tbody tr:hover td {
  background-color: rgba(255, 255, 255, 0.04) !important;
}

[data-bs-theme="dark"] .finding-card-item {
  background: #1E293B !important;
  border-color: rgba(239, 68, 68, 0.4) !important;
  border-left-color: #EF4444 !important;
  color: #F8FAFC !important;
}

[data-bs-theme="dark"] .badge-chip {
  background-color: rgba(255, 255, 255, 0.08) !important;
  color: #E2E8F0 !important;
}

[data-bs-theme="dark"] .chip-primary {
  background-color: rgba(140, 87, 255, 0.18) !important;
  color: #C2A4FF !important;
}

[data-bs-theme="dark"] .chip-secondary {
  background-color: rgba(255, 255, 255, 0.08) !important;
  color: #CBD5E1 !important;
}

[data-bs-theme="dark"] .dashboard-main-nav {
  background: #142036 !important;
  border-color: #1E2D4A !important;
}

[data-bs-theme="dark"] .dashboard-nav-tab {
  color: #94A3B8 !important;
}

[data-bs-theme="dark"] .dashboard-nav-tab:hover {
  color: #FFFFFF !important;
  background: rgba(255, 255, 255, 0.08) !important;
}

[data-bs-theme="dark"] .dashboard-nav-tab.active {
  background: #1E2D4A !important;
  color: #60A5FA !important;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.4) !important;
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
  font-family: "Plus Jakarta Sans", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important;
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
  font-family: "Plus Jakarta Sans", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important;
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
  background: linear-gradient(72.47deg, #8C57FF 0%, #7135FF 100%) !important;
  color: #FFFFFF !important;
  box-shadow: 0px 4px 14px rgba(140, 87, 255, 0.5) !important;
  border-left: none !important;
  font-weight: 700 !important;
}

.sidebar-link.active .sidebar-icon,
.sidebar-link.active .sidebar-text,
.sidebar-link.active i,
.sidebar-link.active span {
  color: #FFFFFF !important;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
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
/* Minimalist Clean Metric Cards */
.card-metric {
  padding: 18px 20px !important;
  border: 1px solid #E2E8F0 !important;
  border-left: 1px solid #E2E8F0 !important;
  border-radius: 12px !important;
  display: flex !important;
  flex-direction: column !important;
  justify-content: space-between !important;
  min-height: 104px !important;
  position: relative !important;
  overflow: hidden !important;
  background: #FFFFFF !important;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04) !important;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

.card-metric::after {
  display: none !important;
}

.card-metric:hover {
  transform: translateY(-2px) !important;
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08) !important;
  border-color: #CBD5E1 !important;
}

.metric-value {
  font-size: 1.75rem !important;
  font-weight: 800 !important;
  letter-spacing: -0.025em !important;
  font-feature-settings: "tnum" 1;
  color: #0F172A;
  line-height: 1.15;
}

.metric-label {
  font-size: 0.72rem !important;
  font-weight: 600 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.05em !important;
  color: #64748B;
  margin-top: 4px;
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

:root:not([data-bs-theme="dark"]) .btn-secondary,
:root:not([data-bs-theme="dark"]) .btn-light {
  background-color: #FFFFFF !important;
  border-color: var(--border-subtle) !important;
  color: var(--text-primary) !important;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

:root:not([data-bs-theme="dark"]) .btn-secondary:hover,
:root:not([data-bs-theme="dark"]) .btn-light:hover {
  background-color: #F8FAFC !important;
  border-color: var(--border-strong) !important;
  transform: translateY(-1px);
}

:root:not([data-bs-theme="dark"]) .btn-outline-primary {
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

.btn-check:checked + .btn-outline-primary,
.btn-check:checked + label.btn,
.btn-outline-primary.active {
  background: linear-gradient(135deg, #8C57FF 0%, #6827EB 100%) !important;
  color: #FFFFFF !important;
  border-color: #6827EB !important;
  box-shadow: 0 4px 14px rgba(104, 39, 235, 0.35) !important;
}

.btn-check:checked + .btn-outline-primary i,
.btn-check:checked + label.btn i,
.btn-outline-primary.active i {
  color: #FFFFFF !important;
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
  border: 1px solid #E2E8F0 !important;
  border-radius: 12px !important;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03) !important;
  transition: box-shadow 0.2s ease, transform 0.2s ease, border-color 0.2s ease !important;
}
.card:hover {
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06) !important;
  border-color: #CBD5E1 !important;
}
.card-metric:hover {
  transform: translateY(-2px) !important;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.07) !important;
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
  background: linear-gradient(72.47deg, #8C57FF 0%, #7135FF 100%) !important;
  color: #FFFFFF !important;
  box-shadow: 0px 4px 14px rgba(140, 87, 255, 0.5) !important;
  border-left: none !important;
  font-weight: 700 !important;
}
.sidebar-link.active .sidebar-icon,
.sidebar-link.active .sidebar-text,
.sidebar-link.active i,
.sidebar-link.active span {
  color: #FFFFFF !important;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
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

/* NEO-MODERN SAAS AESTHETIC (VIBRANT GRADIENT HERO, ROUNDED CARDS, SOFT GLOWS) */
.btn-pill {
  border-radius: 9999px !important;
  padding: 8px 22px !important;
  font-weight: 700 !important;
}

.browser-mockup-frame {
  background: #FFFFFF;
  border: 1px solid #E2E8F0;
  border-radius: 24px;
  box-shadow: 0 16px 40px -8px rgba(15, 23, 42, 0.08), 0 4px 12px -2px rgba(15, 23, 42, 0.03);
  overflow: hidden;
  position: relative;
}

.browser-mockup-topbar {
  background: #FFFFFF;
  border-bottom: 1px solid #F1F5F9;
  padding: 12px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.browser-traffic-dots {
  display: flex;
  align-items: center;
  gap: 7px;
}

.browser-traffic-dot {
  width: 11px;
  height: 11px;
  border-radius: 50%;
}
.browser-dot-amber { background-color: #FBBF24; }
.browser-dot-cyan  { background-color: #22D3EE; }
.browser-dot-green { background-color: #10B981; }

.browser-address-pill {
  background: #F1F5F9;
  border: 1px solid #E2E8F0;
  border-radius: 9999px;
  padding: 5px 18px;
  font-size: 0.82rem;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  color: #475569;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  max-width: 480px;
  width: 100%;
  justify-content: center;
}

.floating-toast-pill {
  background: #FFFFFF;
  border: 1px solid #E2E8F0;
  border-radius: 16px;
  box-shadow: 0 10px 25px -4px rgba(15, 23, 42, 0.08);
  padding: 10px 16px;
  display: inline-flex;
  align-items: center;
  gap: 12px;
  position: relative;
  z-index: 5;
}

.floating-toast-icon-amber {
  width: 36px;
  height: 36px;
  background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
  color: #FFFFFF;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  box-shadow: 0 4px 10px rgba(245, 158, 11, 0.25);
  flex-shrink: 0;
}

/* Gradient Hero Banner */
.card-hero-neo {
  background: linear-gradient(135deg, #059669 0%, #06B6D4 48%, #2563EB 100%) !important;
  color: #FFFFFF !important;
  border: none !important;
  border-radius: 24px !important;
  padding: 28px 32px !important;
  position: relative;
  overflow: hidden;
  box-shadow: 0 12px 32px -4px rgba(6, 182, 212, 0.28) !important;
}

.card-hero-neo::before {
  content: "";
  position: absolute;
  top: -60%;
  right: -25%;
  width: 450px;
  height: 450px;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0) 70%);
  border-radius: 50%;
  pointer-events: none;
}

.card-hero-neo .btn,
.card-hero-neo .btn-hero-pill {
  margin: 4px 6px 4px 0 !important;
}

.gap-2\.5,
.card-hero-neo .d-flex {
  gap: 12px !important;
}

.card-hero-neo .btn-hero-pill {
  background: #FFFFFF !important;
  color: #2563EB !important;
  border: none !important;
  border-radius: 9999px !important;
  padding: 10px 24px !important;
  font-weight: 700 !important;
  font-size: 0.88rem !important;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1) !important;
  transition: all 0.2s ease !important;
}

.card-hero-neo .btn-hero-pill:hover {
  transform: translateY(-2px) !important;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.16) !important;
  background: #F8FAFC !important;
}

.hero-sun-icon {
  width: 60px;
  height: 60px;
  background: radial-gradient(circle, #FDE047 0%, #F59E0B 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 0 30px rgba(253, 224, 71, 0.5);
  position: relative;
}

/* Modern Metric Cards */
.card-metric-modern {
  background: #FFFFFF !important;
  border: 1px solid #E2E8F0 !important;
  border-radius: 20px !important;
  padding: 22px 24px !important;
  box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.04) !important;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.card-metric-modern:hover {
  transform: translateY(-3px) !important;
  box-shadow: 0 12px 28px -4px rgba(15, 23, 42, 0.08) !important;
}

.metric-title-muted {
  font-size: 0.82rem;
  color: #64748B;
  font-weight: 600;
  margin-bottom: 6px;
}

.metric-number-bold {
  font-size: 2rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: #0F172A;
  line-height: 1.1;
}

/* Mint Green Operational Status Card */
.card-status-mint {
  background: #ECFDF5 !important;
  border: 1px solid #A7F3D0 !important;
  border-radius: 20px !important;
  padding: 22px 24px !important;
  box-shadow: 0 4px 16px -2px rgba(16, 185, 129, 0.08) !important;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.card-status-mint .status-heading {
  color: #065F46;
  font-weight: 800;
  font-size: 1.15rem;
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-status-mint .status-subtext {
  color: #047857;
  font-size: 0.88rem;
  font-weight: 500;
  margin-top: 6px;
}

/* Floating Trend Chip */
.badge-floating-trend {
  background: #FFFFFF;
  border: 1px solid #E2E8F0;
  border-radius: 16px;
  padding: 10px 16px;
  box-shadow: 0 10px 25px -4px rgba(15, 23, 42, 0.09);
  display: inline-flex;
  align-items: center;
  gap: 12px;
}

.badge-floating-trend .trend-icon-box {
  width: 36px;
  height: 36px;
  background: #047857;
  color: #FFFFFF;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
  box-shadow: 0 4px 10px rgba(4, 120, 87, 0.25);
}

.pulse-dot-green {
  width: 9px;
  height: 9px;
  background-color: #10B981;
  border-radius: 50%;
  display: inline-block;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25);
  animation: pulseDot 2s infinite ease-in-out;
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
<div class="app-layout-wrapper flex h-screen w-full overflow-hidden bg-[#F4F5F7]">
  '.$sidebarHtml.'
  <div class="app-main-viewport flex-1 flex flex-col min-w-0 bg-[#F4F5F7] h-screen overflow-hidden">
    '.$topbarHtml.'
    <main class="app-content-container flex-1 overflow-y-auto p-6">
      '.$content.'
    </main>
  </div>
</div>
' : '
<div>
  '.$publicHeaderHtml.'
  <main class="app-content-container" style="max-width: 1320px; margin: 0 auto;">
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

// ============================================================
// THEME SWITCHER (Dark Mode / Light Mode with localStorage)
// ============================================================
(function() {
  function getPreferredTheme() {
    try {
      var stored = localStorage.getItem("app_theme");
      if (stored === "dark" || stored === "light") return stored;
    } catch(e) {}
    var isDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
    return isDark ? "dark" : "light";
  }

  function applyTheme(theme, save) {
    document.documentElement.setAttribute("data-bs-theme", theme);
    document.documentElement.setAttribute("data-theme", theme);
    if (save) {
      try { localStorage.setItem("app_theme", theme); } catch(e) {}
    }
    updateThemeUI(theme);
  }

  function updateThemeUI(theme) {
    var isDark = theme === "dark";
    var toggleButtons = document.querySelectorAll(".theme-toggle-btn");
    toggleButtons.forEach(function(btn) {
      btn.setAttribute("title", isDark ? "Beralih ke Mode Terang" : "Beralih ke Mode Gelap");
      btn.setAttribute("aria-label", isDark ? "Mode Terang" : "Mode Gelap");
      var icon = btn.querySelector(".theme-toggle-icon");
      if (icon) {
        if (isDark) {
          icon.className = "bi bi-sun-fill text-warning fs-6 theme-toggle-icon";
        } else {
          icon.className = "bi bi-moon-stars-fill text-secondary fs-6 theme-toggle-icon";
        }
      }
    });

    var dropdownIcons = document.querySelectorAll(".theme-toggle-dropdown-icon");
    dropdownIcons.forEach(function(icon) {
      icon.className = (isDark ? "bi bi-sun-fill text-warning" : "bi bi-moon-stars-fill text-secondary") + " me-2 theme-toggle-dropdown-icon";
    });

    var modeBadges = document.querySelectorAll(".theme-mode-badge");
    modeBadges.forEach(function(badge) {
      badge.textContent = isDark ? "Gelap" : "Terang";
      badge.className = "badge " + (isDark ? "bg-warning-subtle text-warning border border-warning-subtle" : "bg-primary-subtle text-primary border border-primary-subtle") + " px-2 py-0.5 rounded-pill theme-mode-badge";
    });

    var toggleLabels = document.querySelectorAll(".theme-toggle-label");
    toggleLabels.forEach(function(label) {
      label.textContent = isDark ? "Mode Terang" : "Mode Gelap";
    });
  }

  window.toggleAppTheme = function() {
    var current = document.documentElement.getAttribute("data-bs-theme") || "light";
    var next = (current === "dark") ? "light" : "dark";
    applyTheme(next, true);
  };

  window.setAppTheme = function(theme) {
    if (theme === "dark" || theme === "light") {
      applyTheme(theme, true);
    } else {
      try { localStorage.removeItem("app_theme"); } catch(e) {}
      var isDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
      applyTheme(isDark ? "dark" : "light", false);
    }
  };

  if (window.matchMedia) {
    var mql = window.matchMedia("(prefers-color-scheme: dark)");
    var onSystemChange = function(e) {
      try {
        if (!localStorage.getItem("app_theme")) {
          applyTheme(e.matches ? "dark" : "light", false);
        }
      } catch(err) {}
    };
    if (mql.addEventListener) {
      mql.addEventListener("change", onSystemChange);
    } else if (mql.addListener) {
      mql.addListener(onSystemChange);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function() {
      var current = document.documentElement.getAttribute("data-bs-theme") || getPreferredTheme();
      updateThemeUI(current);
    });
  } else {
    var current = document.documentElement.getAttribute("data-bs-theme") || getPreferredTheme();
    updateThemeUI(current);
  }
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

