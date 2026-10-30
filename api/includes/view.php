<?php
if (!function_exists('render_page')) {
function render_page(string $title, string $content, string $extraHead = '', string $extraScript = '', bool $showNav = true): void {
    $currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $role = current_user_role();
    $userName = current_user_name();
    $userInitial = strtoupper(substr($userName, 0, 1) ?: 'U');
    $cleanTitle = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
    $userPhoto = function_exists('current_user_photo') ? current_user_photo() : null;
    $anonymousSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" style="width: 100%; height: 100%; display: block;"><circle cx="32" cy="32" r="31" fill="#28243D" stroke="#0E5C58" stroke-width="2"/><path d="M32 10C21 10 14 17 14 27c0 10 6 21 18 27 12-6 18-17 18-27 0-10-7-17-18-17z" fill="#FFFFFF"/><ellipse cx="20" cy="34" rx="3.5" ry="2" fill="#FFB4BA" opacity="0.7"/><ellipse cx="44" cy="34" rx="3.5" ry="2" fill="#FFB4BA" opacity="0.7"/><path d="M19 21c3-2.5 7-2.5 10-0.5" stroke="#1E1B2E" stroke-width="2" stroke-linecap="round"/><path d="M45 21c-3-2.5-7-2.5-10-0.5" stroke="#1E1B2E" stroke-width="2" stroke-linecap="round"/><ellipse cx="24" cy="26" rx="3.5" ry="2.2" fill="#1E1B2E"/><ellipse cx="40" cy="26" rx="3.5" ry="2.2" fill="#1E1B2E"/><path d="M22 36c4-1 8 2 10 3 2-1 6-4 10-3 3 1 4 3 4 3-3-1-6 0-8 2-2 2-4 2-6 0-2-2-5-3-8-2-1 0-2-3-2-3z" fill="#1E1B2E"/><path d="M30 44h4l-2 8z" fill="#1E1B2E"/><path d="M25 41c2.5 2 11.5 2 14 0" stroke="#0E5C58" stroke-width="1.8" stroke-linecap="round"/></svg>';
    if ($userPhoto && (str_starts_with($userPhoto, 'data:image') || str_starts_with($userPhoto, 'http') || str_starts_with($userPhoto, '/'))) {
        $avatarHtml = '<img src="'.e($userPhoto).'" alt="'.e($userName).'" class="sidebar-user-avatar-img rounded-circle object-fit-cover shadow-xs" style="width: 38px; height: 38px; min-width: 38px; border: 2px solid rgba(14, 92, 88, 0.45); display: inline-block;">';
        $topbarAvatarHtml = '<img src="'.e($userPhoto).'" alt="'.e($userName).'" class="topbar-user-avatar-img rounded-circle object-fit-cover" style="width: 32px; height: 32px; min-width: 32px; border: 1.5px solid rgba(14, 92, 88, 0.45); display: inline-block;">';
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
    } elseif (in_array($currentPage, ['memo_pengajuan.php', 'print_memo.php', 'inventaris_mutation.php', 'asset_mutations.php', 'print_bast.php', 'asset_disposal.php', 'print_bast_disposal.php', 'asset_gallery.php', 'inventaris_gallery.php', 'photo_trash.php', 'inventory_card.php', 'cetak_kartu.php', 'print_inventory_card.php', 'kartu_detail.php'], true) || ($currentPage === 'dashboard.php' && ($_GET['tab'] ?? '') === 'kartu')) {
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
            ['title' => 'Folder Sampah Foto', 'url' => module_url('photo_trash.php'), 'icon' => 'bi-recycle', 'active' => ($currentPage === 'photo_trash.php')],
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
            ['title' => 'Pemeliharaan Rutin', 'url' => module_url('audit.php'), 'icon' => 'bi-clipboard-check', 'active' => in_array($currentPage, ['audit.php', 'history.php', 'maintenance_detail.php'], true)],
            ['title' => 'Data Komputer & PC', 'url' => module_url('assets.php'), 'icon' => 'bi-pc-display', 'active' => in_array($currentPage, ['assets.php', 'asset_add.php', 'asset_edit.php', 'asset_delete.php', 'asset_import.php'], true)],
            ['title' => 'Temuan Kendala IT', 'url' => module_url('dashboard.php', ['tab' => 'temuan']), 'icon' => 'bi-exclamation-triangle', 'active' => ($currentPage === 'dashboard.php' && ($_GET['tab'] ?? '') === 'temuan')],
            ['title' => 'Log Ruang Server', 'url' => module_url('server_room_log.php'), 'icon' => 'bi-door-closed', 'active' => in_array($currentPage, ['server_room_log.php', 'server_room_print_qr.php'], true)],
            ['title' => 'Riwayat Bulanan', 'url' => module_url('monthly_history.php'), 'icon' => 'bi-calendar3', 'active' => ($currentPage === 'monthly_history.php')],
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
    } else {
        // PERAN: IT OPERATIONS & ADMINISTRATOR (DEFAULT)
        $navLinks['OPERASIONAL'] = [
            ['title' => 'Dashboard IT Ops', 'url' => module_url('dashboard.php'), 'icon' => 'bi-grid-1x2', 'active' => ($currentPage === 'dashboard.php' && !in_array($_GET['tab'] ?? '', ['kartu', 'temuan'], true))],
            ['title' => 'Pemeliharaan Rutin', 'url' => module_url('audit.php'), 'icon' => 'bi-clipboard-check', 'active' => in_array($currentPage, ['audit.php', 'history.php', 'maintenance_detail.php'], true)],
            ['title' => 'Data Komputer & PC', 'url' => module_url('assets.php'), 'icon' => 'bi-pc-display', 'active' => in_array($currentPage, ['assets.php', 'asset_add.php', 'asset_edit.php', 'asset_delete.php', 'asset_import.php'], true)],
            ['title' => 'Temuan Kendala IT', 'url' => module_url('dashboard.php', ['tab' => 'temuan']), 'icon' => 'bi-exclamation-triangle', 'active' => ($currentPage === 'dashboard.php' && ($_GET['tab'] ?? '') === 'temuan')],
            ['title' => 'Log Ruang Server', 'url' => module_url('server_room_log.php'), 'icon' => 'bi-door-closed', 'active' => in_array($currentPage, ['server_room_log.php', 'server_room_print_qr.php'], true)],
            ['title' => 'Riwayat Bulanan', 'url' => module_url('monthly_history.php'), 'icon' => 'bi-calendar3', 'active' => ($currentPage === 'monthly_history.php')],
            ['title' => 'Scanner QR Unit', 'url' => module_url('scanner.php'), 'icon' => 'bi-qr-code-scan', 'active' => in_array($currentPage, ['scanner.php', 'scan.php'], true)],
        ];
        $navLinks['ASET & SARPRAS'] = [
            ['title' => 'Pengadaan & Memo', 'url' => module_url('memo_pengajuan.php'), 'icon' => 'bi-file-earmark-text', 'active' => in_array($currentPage, ['memo_pengajuan.php', 'print_memo.php'], true)],
            ['title' => 'Mutasi Aset', 'url' => module_url('inventaris_mutation.php'), 'icon' => 'bi-arrow-left-right', 'active' => in_array($currentPage, ['inventaris_mutation.php', 'asset_mutation.php', 'asset_mutations.php', 'print_bast.php'], true)],
            ['title' => 'Penghapusan Aset', 'url' => module_url('asset_disposal.php'), 'icon' => 'bi-trash', 'active' => in_array($currentPage, ['asset_disposal.php', 'print_bast_disposal.php'], true)],
            ['title' => 'Galeri Foto & Unit', 'url' => module_url('asset_gallery.php'), 'icon' => 'bi-images', 'active' => in_array($currentPage, ['asset_gallery.php', 'inventaris_gallery.php'], true)],
            ['title' => 'Folder Sampah Foto', 'url' => module_url('photo_trash.php'), 'icon' => 'bi-recycle', 'active' => ($currentPage === 'photo_trash.php')],
            ['title' => 'Kartu Inventaris', 'url' => module_url('dashboard.php', ['tab' => 'kartu']), 'icon' => 'bi-credit-card-2-front', 'active' => ($currentPage === 'dashboard.php' && ($_GET['tab'] ?? '') === 'kartu') || in_array($currentPage, ['inventory_card.php', 'cetak_kartu.php', 'print_inventory_card.php', 'kartu_detail.php'], true)],
        ];

        $masterItems = [];
        if ($isAdminUser) {
            $masterItems[] = ['title' => 'Denah Meja Kantor', 'url' => module_url('floor_plan.php'), 'icon' => 'bi-layout-text-window-reverse', 'active' => ($currentPage === 'floor_plan.php')];
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
                ? 'nav-item-link active flex items-center h-[40px] px-3 rounded-[8px] bg-[#D9F1EF] text-[#1B2A3A] font-semibold text-[14px] text-decoration-none'
                : 'nav-item-link flex items-center h-[40px] px-3 rounded-[8px] text-[#53595F] hover:bg-[#F4F5F7] hover:text-[#1B2A3A] font-medium text-[14px] transition-colors text-decoration-none';
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
    <aside class="w-[240px] bg-[#FFFFFF] border-r border-[#D1D5D8] flex flex-col shrink-0 app-sidebar" id="appDesktopSidebar" style="background:#FFFFFF !important; border-right:1px solid #D1D5D8 !important; width:240px !important; min-width:240px !important; box-shadow:none !important;">
      <!-- Logo Area (64px) -->
      <div class="h-[64px] flex items-center px-4 border-b border-[#D1D5D8] sidebar-brand" style="border-bottom:1px solid #D1D5D8 !important; background:#FFFFFF !important;">
        <a href="' . e(module_url('dashboard.php')) . '" class="flex items-center gap-2.5 text-decoration-none overflow-hidden">
          <img src="' . app_logo_url() . '" alt="Logo Bank Mitra" class="h-[36px] w-auto max-w-[42px] object-contain shrink-0" onerror="this.onerror=null; this.src=\'logo.png\';">
          <div class="flex flex-col leading-tight overflow-hidden">
            <span class="font-bold text-[14px] tracking-tight text-[#1B2A3A] truncate">Bank Mitra</span>
            <span class="text-[10px] font-semibold text-[#0E5C58] uppercase tracking-wider truncate">QR Maintenance</span>
          </div>
        </a>
      </div>

      <!-- Nav Links -->
      <div class="flex-1 overflow-y-auto py-5 px-3 flex flex-col gap-1 custom-scrollbar" style="background:#FFFFFF !important;">
        ' . $sidebarMenuHtml . '
      </div>

      <!-- User footer -->
      <div class="p-3 border-t border-[#D1D5D8] bg-[#FFFFFF]" style="border-top:1px solid #D1D5D8 !important; background:#FFFFFF !important;">
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
    <div class="offcanvas offcanvas-start bg-white text-dark" tabindex="-1" id="appMobileSidebar" aria-labelledby="appMobileSidebarLabel" style="width: 260px; background:#FFFFFF !important; color:#1B2A3A !important;">
      <div class="offcanvas-header border-b border-[#D1D5D8] py-3 px-4" style="border-bottom:1px solid #D1D5D8 !important;">
        <a href="' . e(module_url('dashboard.php')) . '" class="flex items-center gap-2.5 text-decoration-none overflow-hidden">
          <img src="' . app_logo_url() . '" alt="Logo Bank Mitra" class="h-[34px] w-auto max-w-[40px] object-contain shrink-0" onerror="this.onerror=null; this.src=\'logo.png\';">
          <div class="flex flex-col leading-tight overflow-hidden">
            <span class="font-bold text-[14px] tracking-tight text-[#1B2A3A] truncate">Bank Mitra</span>
            <span class="text-[10px] font-semibold text-[#0E5C58] uppercase tracking-wider truncate">QR Maintenance</span>
          </div>
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body custom-scrollbar p-3" style="background:#FFFFFF !important;">
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
    <header class="h-[58px] md:h-[64px] bg-[#FFFFFF] border-b border-[#D1D5D8] flex items-center justify-between px-3 sm:px-4 md:px-6 shrink-0 sticky top-0 z-10 app-topbar gap-2">
      <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1">
        <button class="btn btn-sm btn-outline-secondary d-lg-none shrink-0 px-2 py-1" type="button" data-bs-toggle="offcanvas" data-bs-target="#appMobileSidebar" aria-controls="appMobileSidebar" aria-label="Menu Navigasi">
          <i class="bi bi-list fs-5"></i>
        </button>
        <div class="min-w-0 flex-1">
          <div class="font-semibold text-[14px] md:text-[15px] text-[#1B2A3A] text-truncate leading-tight">' . e($cleanTitle) . '</div>
          <div class="text-[11px] text-[#53595F] leading-tight d-none d-sm-block text-truncate">Halo, <strong class="text-[#1B2A3A] font-semibold">' . e($userName) . '</strong></div>
        </div>
      </div>

      <div class="flex items-center gap-1.5 sm:gap-2.5 md:gap-4 shrink-0">
        <button type="button" class="flex items-center justify-center gap-2 h-[34px] md:h-[36px] px-2.5 md:px-3 bg-[#F4F5F7] border border-[#D1D5D8] rounded-[8px] text-[#53595F] text-[13px] hover:bg-[#e9eaec] transition-colors cursor-pointer" data-bs-toggle="modal" data-bs-target="#globalSearchModal" title="Cari aset (Ctrl+K)">
          <i class="bi bi-search"></i>
          <span class="d-none d-sm-inline">Cari aset...</span>
          <kbd class="ml-2 font-mono text-[10px] font-semibold bg-[#FFFFFF] border border-[#D1D5D8] rounded-[4px] px-1.5 py-0.5 text-[#53595F] d-none d-md-inline-block">Ctrl+K</kbd>
        </button>
        <div class="w-px h-6 bg-[#D1D5D8] d-none d-sm-block"></div>
        <button type="button" class="flex items-center gap-1 text-[12px] md:text-[13px] font-semibold text-[#875315] hover:opacity-80 transition-opacity pwa-queue-btn border-0 bg-transparent cursor-pointer px-1" data-bs-toggle="modal" data-bs-target="#modalPWAQueue" title="Antrean Pemeliharaan Offline">
          <i class="bi bi-cloud-slash"></i>
          <span class="pwa-queue-text d-none d-md-inline">2 tertunda</span>
        </button>
        <button type="button" class="text-[#53595F] hover:text-[#1B2A3A] text-[17px] md:text-[18px] transition-colors border-0 bg-transparent cursor-pointer px-1" data-bs-toggle="modal" data-bs-target="#modalPanduanAplikasi" title="Bantuan & Panduan">
          <i class="bi bi-question-circle"></i>
        </button>
        <div class="dropdown">
          <button type="button" class="flex items-center gap-2 h-[36px] md:h-[38px] px-1.5 md:px-2 rounded-[8px] hover:bg-[#F4F5F7] border border-transparent hover:border-[#D1D5D8] transition-all cursor-pointer bg-transparent text-decoration-none" data-bs-toggle="dropdown" aria-expanded="false" title="Akun Pengguna: ' . e($userName) . '">
            <div class="w-[32px] h-[32px] rounded-[8px] bg-[#0E5C58] text-white flex items-center justify-center font-bold text-[13px] shadow-[0_1px_2px_rgba(0,0,0,0.1)] shrink-0">
              ' . $userInitial . '
            </div>
            <div class="d-none d-md-flex flex-col text-start leading-none pr-0.5">
              <span class="text-[13px] font-semibold text-[#1B2A3A] truncate max-w-[150px]">' . e($userName) . '</span>
              <span class="text-[11px] text-[#53595F] text-capitalize mt-0.5">' . e($displayRole) . '</span>
            </div>
            <i class="bi bi-chevron-down text-[#53595F] text-[10px] d-none d-md-inline-block"></i>
          </button>
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
<!-- Local Vendor Assets (Zero External CDN Dependency / 100% Offline LAN Compatible) -->
<link href="'.e(module_url('assets/vendor/bootstrap.min.css')).'" rel="stylesheet">
<link rel="stylesheet" href="'.e(module_url('assets/vendor/bootstrap-icons.min.css')).'">
<script src="'.e(module_url('assets/vendor/tailwindcss-browser.js')).'"></script>
<link rel="stylesheet" href="'.e(module_url('assets/css/app-theme.css', ['v' => @filemtime(__DIR__ . '/../assets/css/app-theme.css') ?: '20261029'])).'">

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
    <main class="app-content-container flex-1 overflow-y-auto p-3 sm:p-4 md:p-6">
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
                    <p class="text-muted small mb-0">Buka menu <strong>Log Aktivitas (Audit)</strong> untuk memeriksa rekaman mutasi data, login, pengubahan status, dan unduh CSV untuk arsip audit.</p>
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
        <span class="small text-muted"><i class="bi bi-shield-lock-fill text-primary me-1"></i> Sistem Terproteksi Standar Keamanan Perbankan</span>
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

<script src="' . e(module_url('assets/vendor/bootstrap.bundle.min.js')) . '"></script>
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

// Unregister service workers to avoid stale cache issues on LAN
if ("serviceWorker" in navigator) {
  navigator.serviceWorker.getRegistrations().then(function(registrations) {
    for (var reg of registrations) { reg.unregister(); }
  });
}

// ============================================================
// ANTI-IDLE HEARTBEAT & TAB RESUME AUTO-REFRESH
// Menjaga koneksi router NAT & socket TCP tetap hidup saat idle di jaringan LAN
// ============================================================
(function() {
  var lastActiveTime = Date.now();

  function sendKeepAlivePing() {
    try {
      fetch("realtime_sync.php?action=ws_status&_t=" + Date.now(), { 
        cache: "no-store",
        priority: "low"
      }).catch(function() {});
    } catch(e) {}
  }

  // Ping ringan setiap 25 detik untuk mencegah router NAT timeout
  setInterval(sendKeepAlivePing, 25000);

  // Saat tab aktif kembali setelah diminimize / idle lama
  document.addEventListener("visibilitychange", function() {
    if (document.visibilityState === "visible") {
      var idleSec = Math.round((Date.now() - lastActiveTime) / 1000);
      lastActiveTime = Date.now();
      if (idleSec > 60) {
        sendKeepAlivePing();
      }
    } else {
      lastActiveTime = Date.now();
    }
  });

  window.addEventListener("focus", function() {
    lastActiveTime = Date.now();
    sendKeepAlivePing();
  });
})();
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

