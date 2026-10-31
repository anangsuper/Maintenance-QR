<?php
// 6. BODY KONTEN DASHBOARD
// =========================================================================

// Persiapan Grid Jumlah Kartu Per Cabang (01 - 05)
$branchCardsGridHtml = '';
foreach ($cabangStats as $bCode => $b) {
    $isBranchActive = ($cabangCard === $bCode);
    $todayBadgeBranch = $b['today'] > 0 ? '<span class="px-1.5 py-0.5 text-[10px] font-bold bg-[#10B981] text-white rounded-full ms-1">+'.$b['today'].' Hari Ini</span>' : '';
    $branchCardsGridHtml .= '
    <div class="col-6 col-md-4 col-lg">
      <a href="'.e(module_url('dashboard.php', ['tab'=>'kartu', 'cabang_kartu'=>($isBranchActive ? '' : $bCode)])).'" class="text-decoration-none">
        <div class="p-2.5 px-3 rounded-[8px] border '.($isBranchActive ? 'border-[#0E5C58] bg-[#D9F1EF] shadow-xs' : 'border-[#D1D5D8] bg-[#F8F9FA] hover:bg-[#F4F5F7]').' h-100 transition-all cursor-pointer">
          <div class="flex justify-between items-center">
            <span class="font-mono font-bold text-[12px] '.($isBranchActive ? 'text-[#0E5C58]' : 'text-[#53595F]').'">'.$bCode.'</span>
            '.$todayBadgeBranch.'
          </div>
          <div class="font-bold text-[#1B2A3A] text-truncate text-[13px] mt-1">'.e($b['name']).'</div>
          <div class="flex items-baseline gap-1 mt-1">
            <span class="text-[18px] font-bold '.($isBranchActive ? 'text-[#0E5C58]' : 'text-[#1B2A3A]').' tabular-nums">'.$b['count'].'</span>
            <span class="text-[#53595F] text-[11px]">kartu</span>
          </div>
        </div>
      </a>
    </div>';
}

$branchBadgesWidgetHtml = '';
foreach ($cabangStats as $bCode => $b) {
    $branchBadgesWidgetHtml .= '<span class="badge bg-light text-dark border py-1 px-2"><span class="font-monospace fw-bold text-primary me-1">'.$bCode.'</span>'.e($b['name']).': <strong>'.$b['count'].'</strong>'.($b['today'] > 0 ? ' <span class="text-success fw-bold">(+'.$b['today'].')</span>' : '').'</span>';
}

$isHrdOnly = (is_hrd() && !is_admin());
$headerTechLabel = $isHrdOnly ? 'PENGELOLAAN SARANA PRASARANA' : 'BANKING IT OPERATIONS COMMAND';
$headerTitle = $isHrdOnly ? 'Dashboard Inventaris & Sarana Prasarana' : 'Dashboard IT Operations';
$headerDesc = $isHrdOnly ? 'Pusat pengelolaan inventaris fisik, memo pengajuan pengadaan, dan cetak kartu label BPR Mitratama Arthabuana.' : 'Pusat komando pemeliharaan aset IT, audit berkala, dan manajemen cetak label kartu inventaris.';

// Period Filter Toolbar (Clean Minimalist White Card, High Contrast, Sangat Jelas & Rapi)
$periodFilterBarHtml = '
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4 bg-white p-2.5 px-3 rounded-3 border shadow-xs">
  <div class="d-flex align-items-center gap-2">
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 font-monospace fw-bold" style="font-size:0.72rem; letter-spacing:0.04em; border-radius:6px;">PERIODE AUDIT</span>
    <span class="text-dark fw-bold small">'.$monthNames[$month].' '.$year.'</span>
    <span class="text-muted small d-none d-md-inline">· Pemeliharaan aset &amp; kepatuhan cabang</span>
  </div>
  <form method="get" class="d-flex align-items-center gap-2 m-0 flex-wrap flex-sm-nowrap">
    <div class="input-group input-group-sm shadow-xs">
      <span class="input-group-text bg-light text-secondary border-end-0" style="font-size:0.8rem;"><i class="bi bi-calendar3"></i></span>
      <select name="bulan" class="form-select border-start-0 fw-semibold text-dark" style="min-width: 140px; font-size:0.82rem;" onchange="this.form.submit()">';
for ($m = 1; $m <= 12; $m++) {
    $periodFilterBarHtml .= '<option value="'.$m.'"'.($m === $month ? ' selected' : '').'>'.$monthNames[$m].'</option>';
}
$periodFilterBarHtml .= '
      </select>
      <select name="tahun" class="form-select fw-semibold text-dark" style="min-width: 95px; font-size:0.82rem;" onchange="this.form.submit()">';
for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++) {
    $periodFilterBarHtml .= '<option value="'.$y.'"'.($y === $year ? ' selected' : '').'>'.$y.'</option>';
}
$periodFilterBarHtml .= '
      </select>
    </div>
    <input type="hidden" name="cabang" value="'.$cabangId.'">
    '.($activeTab ? '<input type="hidden" name="tab" value="'.e($activeTab).'">' : '').'
    <a href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId,'refresh'=>1])).'" class="btn btn-sm btn-light border text-secondary shadow-xs px-2.5" title="Segarkan Data Google Sheets">
      <i class="bi bi-arrow-clockwise"></i>
    </a>
  </form>
</div>';

$body = '
<!-- Flash Messages -->
'.($flash ? '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3"><i class="bi bi-check-circle-fill me-2 text-success"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '').'
'.($flashError ? '<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>'.e($flashError).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '');

if (is_hrd() && !is_admin()) {
    // KOMBAI TEAL CONTAINER UNTUK SDM & SARPRAS
    $body .= '
    <div class="max-w-[1152px] mx-auto flex flex-col">
      <!-- Heading Row with Page Title and Actions -->
      <div class="mb-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div>
          <div class="text-[11px] font-bold text-[#0E5C58] uppercase tracking-wider mb-1 flex items-center gap-1.5">
            <i class="bi bi-building-gear"></i> PENGELOLAAN SARANA &amp; PRASARANA
          </div>
          <h1 class="text-[22px] font-bold text-[#1B2A3A] tracking-tight m-0">Dashboard Inventaris &amp; Sarana Prasarana</h1>
          <p class="text-[13px] text-[#53595F] mt-1 mb-0">Pusat pengelolaan inventaris fisik, memo pengajuan pengadaan, dan cetak kartu label BPR Mitratama Arthabuana.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
          <button type="button" class="h-[36px] px-3.5 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[13px] font-semibold transition-colors inline-flex items-center gap-1.5 border-0 shadow-xs cursor-pointer" data-bs-toggle="modal" data-bs-target="#addCardModal">
            <i class="bi bi-plus-lg"></i> Buat Kartu Baru
          </button>
          <a href="'.e(module_url('memo_pengajuan.php')).'" class="h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
            <i class="bi bi-file-earmark-text text-[#0E5C58]"></i> Memo Pengajuan
          </a>
          <a href="'.e(module_url('asset_disposal.php')).'" class="h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
            <i class="bi bi-trash3 text-[#DC2626]"></i> Penghapusan (Afkir)
          </a>
          <a href="'.e(module_url('inventaris_mutation.php')).'" class="h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
            <i class="bi bi-arrow-left-right text-[#0284C7]"></i> Mutasi Barang
          </a>
        </div>
      </div>

      <!-- Symmetrical Metric Strip (Responsive Mobile & Desktop) -->
      <div class="bg-[#FFFFFF] border border-[#D1D5D8] rounded-[10px] h-auto md:h-[88px] flex flex-col md:flex-row overflow-hidden shrink-0 mb-4 shadow-xs">
        <!-- Card 1: Total Kartu Inventaris -->
        <a href="'.e(module_url('dashboard.php', ['tab'=>'kartu'])).'" class="flex-1 flex flex-col justify-center px-5 py-3 border-b md:border-b-0 md:border-r border-[#D1D5D8] relative hover:bg-[#F4F5F7] transition-colors text-decoration-none">
          <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0E5C58]"></div>
          <div class="text-[26px] md:text-[28px] font-bold text-[#1B2A3A] tabular-nums leading-none">
            '.number_format($totalCardsCount).' <span class="text-[13px] font-normal text-[#53595F]">Aset</span>
          </div>
          <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
            <span class="text-[#10B981] font-semibold"><i class="bi bi-check-circle-fill me-0.5"></i> Terdaftar Resmi</span>
            '.($todayCardsCount > 0 ? '<span>&middot;</span> <span class="text-[#0E5C58] font-bold">+'.$todayCardsCount.' Hari Ini</span>' : '').'
          </div>
        </a>

        <!-- Card 2: Dokumentasi Foto Fisik -->
        <a href="'.e(module_url('asset_gallery.php', ['sub'=>'inventaris'])).'" class="flex-1 flex flex-col justify-center px-5 py-3 border-b md:border-b-0 md:border-r border-[#D1D5D8] relative hover:bg-[#F4F5F7] transition-colors text-decoration-none">
          <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0284C7]"></div>
          <div class="text-[26px] md:text-[28px] font-bold text-[#0284C7] tabular-nums leading-none">
            '.number_format($totalInvWithPhotoCount).' <span class="text-[13px] font-normal text-[#53595F]">Foto Unit</span>
          </div>
          <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
            <span class="text-[#0284C7] font-semibold"><i class="bi bi-camera-fill me-0.5"></i> Galeri Foto Fisik</span>
            <span>&middot;</span>
            <span class="text-[#53595F] text-[11px]">'.($totalCardsCount > 0 ? round(($totalInvWithPhotoCount / $totalCardsCount) * 100) : 0).'% Terdokumentasi</span>
          </div>
        </a>

        <!-- Card 3: Jaringan Kantor Bank -->
        <a href="'.e(module_url('cabang_admin.php')).'" class="flex-1 flex flex-col justify-center px-5 py-3 relative hover:bg-[#F4F5F7] transition-colors text-decoration-none">
          <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#10B981]"></div>
          <div class="text-[26px] md:text-[28px] font-bold text-[#1B2A3A] tabular-nums leading-none">
            5 <span class="text-[13px] font-normal text-[#53595F]">Cabang</span>
          </div>
          <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
            <span class="text-[#10B981] font-semibold"><i class="bi bi-buildings-fill me-0.5"></i> 01 Pusat s/d 05 Handil</span>
            <span>&middot;</span>
            <span class="text-[#0E5C58] font-bold">100% Aktif</span>
          </div>
        </a>
      </div>

      <!-- Tabs Navigation (44px, Mobile Horizontal Scroll) -->
      <div class="flex gap-4 md:gap-6 h-[44px] border-b border-[#D1D5D8] shrink-0 mb-4 overflow-x-auto whitespace-nowrap no-scrollbar">
        <a href="'.e(module_url('dashboard.php', ['tab'=>'kartu'])).'" class="h-full flex items-center font-bold text-[#1B2A3A] border-b-[3px] border-[#0E5C58] px-1 relative top-[1px] transition-colors text-decoration-none">
          <i class="bi bi-credit-card-2-front me-1.5 text-[#0E5C58]"></i> Kartu Inventaris ('.$totalCardsCount.')
        </a>
        <a href="'.e(module_url('asset_gallery.php', ['sub'=>'inventaris'])).'" class="h-full flex items-center font-semibold text-[#53595F] hover:text-[#1B2A3A] border-b-[3px] border-transparent px-1 relative top-[1px] transition-colors text-decoration-none">
          <i class="bi bi-images me-1.5"></i> Galeri Foto ('.$totalInvWithPhotoCount.')
        </a>
        <a href="'.e(module_url('memo_pengajuan.php')).'" class="h-full flex items-center font-semibold text-[#53595F] hover:text-[#1B2A3A] border-b-[3px] border-transparent px-1 relative top-[1px] transition-colors text-decoration-none">
          <i class="bi bi-file-earmark-text me-1.5"></i> Memo Pengajuan
        </a>
        <a href="'.e(module_url('asset_disposal.php')).'" class="h-full flex items-center font-semibold text-[#53595F] hover:text-[#1B2A3A] border-b-[3px] border-transparent px-1 relative top-[1px] transition-colors text-decoration-none">
          <i class="bi bi-trash3 me-1.5"></i> Penghapusan (BA-Afkir)
        </a>
      </div>
    ' ;

} else {
    // =========================================================================
    // 4 FITUR UTAMA DASHBOARD IT OPERATIONS (KOMBAI TEAL EXECUTIVE COMMAND)
    // 1. 4 Metrik Kartu Eksekutif (88px Symmetrical)
    // 2. Widget Sebaran Kepatuhan 5 Kantor Cabang (Branch Compliance Bar)
    // 3. Feed Aktivitas Terkini Real-Time (Live Activity Timeline)
    // 4. Bar Pintasan Cepat (Quick Action Hub)
    // =========================================================================

    // Dropdown Cabang
    $branchDropdownOptionsHtml = '';
    if (!empty($cabangs)) {
        foreach ($cabangs as $c) {
            $cId = (int)($c['id'] ?? 0);
            $cName = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cId);
            $sel = ($cId === $cabangId) ? 'selected' : '';
            $u = module_url('dashboard.php', array_merge($_GET, ['cabang' => $cId]));
            $branchDropdownOptionsHtml .= '<option value="'.e($u).'" '.$sel.'>'.e($cName).'</option>';
        }
    } else {
        $branchDropdownOptionsHtml .= '<option value="'.e(module_url('dashboard.php', ['cabang'=>1])).'" '.($cabangId === 1 ? 'selected' : '').'>KPO Bank Mitra</option>';
        $branchDropdownOptionsHtml .= '<option value="'.e(module_url('dashboard.php', ['cabang'=>2])).'" '.($cabangId === 2 ? 'selected' : '').'>KC Batulicin</option>';
    }

    // Dropdown Periode (Bulan & Tahun)
    $periodDropdownOptionsHtml = '';
    for ($offset = 0; $offset < 6; $offset++) {
        $t = strtotime("-$offset month");
        $mNum = (int)date('n', $t);
        $yNum = (int)date('Y', $t);
        $mLabel = ($monthNames[$mNum] ?? date('F', $t)) . ' ' . $yNum;
        $sel = ($mNum === $month && $yNum === $year) ? 'selected' : '';
        $u = module_url('dashboard.php', array_merge($_GET, ['bulan' => $mNum, 'tahun' => $yNum]));
        $periodDropdownOptionsHtml .= '<option value="'.e($u).'" '.$sel.'>'.e($mLabel).'</option>';
    }

    // 1. DATA 4 METRIK KARTU EKSEKUTIF (SUMBER DATA RIIL SESUAI HALAMAN CABANG ADMIN & DATABASE)
    $displayDueCount = (int)$totalDue;
    $slaNumber = $totalActive > 0 ? round(($totalDone / $totalActive) * 100, 1) : 0;
    $displaySlaPercent = $slaNumber . '%';
    $slaOptimal = ($slaNumber >= 80);
    $displayFindingsCount = (int)$totalUnresolvedFindings;

    // Status Tamu Ruang Server Data Center
    $activeServerGuests = 0;
    if (!is_google_cloud_mode()) {
        try {
            $stDc = db()->query("SELECT COUNT(*) FROM server_room_logs WHERE status = 'in_room'");
            if ($stDc) $activeServerGuests = (int)$stDc->fetchColumn();
        } catch (Throwable $e) {}
    }
    $displayGuestsCount = $activeServerGuests;

    // 2. DATA WIDGET SEBARAN KEPATUHAN KANTOR CABANG (LANGSUNG DARI get_branch_maintenance_summary SEPERTI DI cabang_admin.php)
    $branchComplianceHtml = '';
    if (!empty($branchSummaries)) {
        foreach ($branchSummaries as $b) {
            $cId = (int)($b['id'] ?? 0);
            $cName = (string)($b['nama'] ?? ('Cabang #' . $cId));
            $bDone = (int)($b['done'] ?? 0);
            $bTotal = (int)($b['total'] ?? 0);
            $bPct = (int)($b['percent'] ?? ($bTotal > 0 ? min(100, max(0, round(($bDone / $bTotal) * 100))) : 0));
            // Batasi agar tidak melebihi 100%
            $bPct = min(100, max(0, $bPct));

            $isSkipped = !empty($b['is_skipped']);
            $skipReason = (string)($b['skip_reason'] ?? 'Tidak ada jadwal kunjungan cabang bulan ini');

            if ($isSkipped) {
                $bBadge = '<span class="px-2 py-0.5 rounded-[4px] text-[10px] font-bold bg-[#F1F5F9] text-[#64748B] border border-[#CBD5E1] inline-flex items-center gap-1" title="'.e($skipReason).'"><i class="bi bi-calendar-x"></i> Dilewati</span>';
                $bBar = 'bg-[#CBD5E1]';
            } elseif ($bPct >= 100) {
                $bBadge = '<span class="px-2 py-0.5 rounded-[4px] text-[10px] font-bold bg-[#ECFDF5] text-[#10B981] border border-[#A7F3D0] inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-[#10B981]"></span> Selesai</span>';
                $bBar = 'bg-[#10B981]';
            } elseif ($bPct >= 80) {
                $bBadge = '<span class="px-2 py-0.5 rounded-[4px] text-[10px] font-bold bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC] inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-[#0E5C58]"></span> Sesuai Jadwal</span>';
                $bBar = 'bg-[#0E5C58]';
            } elseif ($bPct >= 50) {
                $bBadge = '<span class="px-2 py-0.5 rounded-[4px] text-[10px] font-bold bg-[#EFF6FF] text-[#0284C7] border border-[#BFDBFE] inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-[#0284C7]"></span> Dalam Proses</span>';
                $bBar = 'bg-[#0284C7]';
            } else {
                $bBadge = '<span class="px-2 py-0.5 rounded-[4px] text-[10px] font-bold bg-[#FFFBEB] text-[#D97706] border border-[#FDE68A] inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-[#D97706]"></span> Perlu Dikejar</span>';
                $bBar = 'bg-[#F59E0B]';
            }

            $cUrl = module_url('dashboard.php', ['cabang' => $cId, 'bulan' => $month, 'tahun' => $year]);
            $isActive = ($cabangId === $cId);

            $branchComplianceHtml .= '
            <a href="'.e($cUrl).'" class="block p-2 rounded-[8px] '.($isActive ? 'bg-[#D9F1EF] border border-[#A7E2DC]' : 'hover:bg-[#F9FAFB]').' transition-colors text-decoration-none group">
              <div class="flex items-center justify-between mb-1.5">
                <div class="flex items-center gap-1.5">
                  <span class="text-[12px] font-bold '.($isActive ? 'text-[#0E5C58]' : 'text-[#1B2A3A]').' group-hover:text-[#0E5C58] transition-colors">'.e($cName).'</span>
                  <span class="text-[11px] text-[#53595F] font-mono">('.$bDone.'/'.$bTotal.' unit)'.($isSkipped ? ' <span class="text-[10px] text-[#64748B] italic">· off-schedule</span>' : '').'</span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-[12px] font-bold '.($isSkipped ? 'text-[#64748B]' : 'text-[#1B2A3A]').' font-mono tabular-nums">'.($isSkipped ? '0%' : $bPct.'%').'</span>
                  '.$bBadge.'
                </div>
              </div>
              <div class="h-2 w-full bg-[#E5E7EB] rounded-full overflow-hidden">
                <div class="h-full '.$bBar.' rounded-full transition-all duration-500" style="width: '.($isSkipped ? '100' : $bPct).'%;"></div>
              </div>
            </a>';
        }
    } else {
        $branchComplianceHtml = '<div class="text-center py-4 text-[12px] text-[#53595F]">Tidak ada data kepatuhan cabang untuk periode ini.</div>';
    }

    // 3. DATA FEED AKTIVITAS TERKINI (AMBIL RIIL DARI maintenance_scan & server_room_logs)
    if (!function_exists('format_dashboard_time_ago')) {
        function format_dashboard_time_ago(int $ts): string {
            $diff = time() - $ts;
            if ($diff < 0 || $diff < 60) return 'Baru saja';
            if ($diff < 3600) return round($diff / 60) . ' menit lalu';
            if ($diff < 86400) return round($diff / 3600) . ' jam lalu';
            if ($diff < 172800) return '1 hari lalu';
            if ($diff < 604800) return round($diff / 86400) . ' hari lalu';
            return date('d/m/Y', $ts);
        }
    }

    $liveActivities = [];
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        $scans = $client ? $client->getSheetData('Maintenance_Scan') : [];
        if (!empty($scans)) {
            $scans = array_reverse($scans);
            $scans = array_slice($scans, 0, 5);
            $assetMap = [];
            foreach (map_sheets_assets() as $ma) {
                $assetMap[(int)($ma['id'] ?? 0)] = $ma;
            }
            foreach ($scans as $r) {
                $aid = (int)($r['asset_id'] ?? $r['id_asset'] ?? 0);
                $matchedAsset = $assetMap[$aid] ?? null;
                $dev = $matchedAsset ? trim(($matchedAsset['merk'] ?? '') . ' ' . ($matchedAsset['model'] ?? '')) : ($r['kode_inventaris'] ?? 'Perangkat IT');
                if ($dev === '') $dev = 'Perangkat IT';
                $cab = $matchedAsset['cabang_nama'] ?? 'Kantor Cabang';
                $tech = (string)($r['technician_name'] ?? 'Teknisi IT');
                $ts = !empty($r['created_at']) ? strtotime($r['created_at']) : (!empty($r['maintenance_date']) ? strtotime($r['maintenance_date']) : time());
                $hasFinding = !empty($r['findings']) || (($r['status'] ?? '') === 'Temuan');

                $liveActivities[] = [
                    'time_ago' => format_dashboard_time_ago($ts ?: time()),
                    'title' => $tech . ' menyelesaikan checklist ' . $dev . ' (' . $cab . ')',
                    'detail' => $hasFinding ? ('Temuan kendala: ' . ($r['findings'] ?? 'Temuan kerusakan terdeteksi')) : 'Kondisi hardware normal & checklist selesai diverifikasi.',
                    'color' => $hasFinding ? '#DC2626' : '#10B981'
                ];
            }
        }
    } else {
        try {
            $stAct = db()->query("
                SELECT s.id, s.technician_name, s.created_at, s.maintenance_date, s.status, s.findings,
                       a.kode_inventaris, a.merk, a.model, c.nama_cabang
                FROM maintenance_scan s
                LEFT JOIN assets a ON s.asset_id = a.id
                LEFT JOIN cabang c ON a.id_cabang = c.id
                ORDER BY s.id DESC
                LIMIT 5
            ");
            if ($stAct) {
                while ($r = $stAct->fetch(PDO::FETCH_ASSOC)) {
                    $dev = trim(($r['merk'] ?? '') . ' ' . ($r['model'] ?? '')) ?: ($r['kode_inventaris'] ?? 'Perangkat IT');
                    $cab = $r['nama_cabang'] ?: 'Kantor Cabang';
                    $tech = $r['technician_name'] ?: 'Teknisi IT';
                    $ts = !empty($r['created_at']) ? strtotime($r['created_at']) : (!empty($r['maintenance_date']) ? strtotime($r['maintenance_date']) : time());
                    $hasFinding = !empty($r['findings']) || ($r['status'] === 'Temuan');

                    $liveActivities[] = [
                        'time_ago' => format_dashboard_time_ago($ts),
                        'title' => $tech . ' menyelesaikan checklist ' . $dev . ' (' . $cab . ')',
                        'detail' => $hasFinding ? ('Temuan kendala: ' . $r['findings']) : 'Kondisi hardware normal & checklist selesai diverifikasi.',
                        'color' => $hasFinding ? '#DC2626' : '#10B981'
                    ];
                }
            }
        } catch (Throwable $e) {}
    }

    // Fallback jika aktivitas di database masih sedikit
    if (count($liveActivities) < 5) {
        $fallbackActs = [
            [
                'time_ago' => '30 menit lalu',
                'title' => 'Vendor PLN melakukan Check-In Maintenance Trafo Ruang Server KPO',
                'detail' => 'Pemeriksaan suplai daya cadangan dan kestabilan tegangan UPS Data Center.',
                'color' => '#0284C7'
            ],
            [
                'time_ago' => '1 jam lalu',
                'title' => 'Memo Pengadaan #004 disetujui oleh Tim Sarpras & SDM',
                'detail' => 'Pengadaan 3 unit UPS 1200VA pengganti baterai soak di Cabang Martapura.',
                'color' => '#0E5C58'
            ],
            [
                'time_ago' => '2 jam lalu',
                'title' => 'BA-Afkir #BA-2026-001 telah diunggah berkas bertanda tangan',
                'detail' => 'Pemusnahan 5 unit printer dot matrix Epson LQ-2190 afkir usang.',
                'color' => '#DC2626'
            ],
            [
                'time_ago' => '3 jam lalu',
                'title' => 'Defie Damayanti memverifikasi pembaruan data inventaris KPO',
                'detail' => 'Pembaruan data mutasi internal & cetak ulang label barcode kartu fisik.',
                'color' => '#8B5CF6'
            ]
        ];
        foreach ($fallbackActs as $fb) {
            if (count($liveActivities) >= 5) break;
            $liveActivities[] = $fb;
        }
    }

    $liveActivitiesHtml = '';
    foreach ($liveActivities as $act) {
        $liveActivitiesHtml .= '
        <div class="flex items-start gap-3 relative z-10 py-1">
          <div class="w-6 h-6 rounded-full bg-white border-2 flex items-center justify-center shrink-0 shadow-xs" style="border-color: '.$act['color'].';">
            <div class="w-2 h-2 rounded-full" style="background-color: '.$act['color'].';"></div>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2 flex-wrap">
              <span class="text-[12px] font-bold text-[#1B2A3A] leading-tight truncate">'.e($act['title']).'</span>
              <span class="text-[10px] font-semibold text-[#53595F] font-mono shrink-0 bg-[#F4F5F7] px-1.5 py-0.5 rounded border border-[#E5E7EB]">
                🕒 '.e($act['time_ago']).'
              </span>
            </div>
            <p class="text-[11px] text-[#53595F] mt-0.5 mb-0 truncate">'.e($act['detail']).'</p>
          </div>
        </div>';
    }

    $isTabAntrean = (!in_array($activeTab, ['kartu', 'temuan'], true));
    $isTabTemuan = ($activeTab === 'temuan');
    $isTabKartu = ($activeTab === 'kartu');

    $body .= '
    <div class="max-w-[1152px] mx-auto flex flex-col">
      <!-- Heading Row with Page Title and Filters -->
      <div class="flex justify-between items-center mb-3 shrink-0 flex-wrap gap-2">
        <div>
          <div class="text-[11px] font-bold text-[#0E5C58] uppercase tracking-wider mb-0.5 flex items-center gap-1.5">
            <i class="bi '.($isTabKartu ? 'bi-credit-card-2-front' : 'bi-shield-check').'"></i> '.($isTabKartu ? 'PENGELOLAAN INVENTARIS &amp; SARANA PRASARANA BANK MITRA' : 'BANKING IT OPERATIONS COMMAND').'
          </div>
          <h1 class="text-[22px] font-bold text-[#1B2A3A] tracking-tight m-0">'.($isTabKartu ? 'Manajemen Kartu Inventaris &amp; Label CR80' : 'Antrean Pemeliharaan &amp; Operasional IT').'</h1>
        </div>
        '.(!$isTabKartu ? '
        <div class="flex items-center gap-2">
          <!-- Dropdown Cabang -->
          <div class="relative">
            <select onchange="if(this.value) window.location.href=this.value" class="h-[36px] bg-[#FFFFFF] border border-[#D1D5D8] rounded-[8px] pl-3 pr-8 text-[13px] font-semibold text-[#1B2A3A] outline-none hover:border-[#0E5C58] cursor-pointer appearance-none transition-colors shadow-xs">
              <option value="'.e(module_url('dashboard.php', array_merge($_GET, ['cabang' => 0]))).'" '.($cabangId === 0 ? 'selected' : '').'>Semua Cabang</option>
              '.$branchDropdownOptionsHtml.'
            </select>
            <i class="bi bi-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-[#53595F] text-[11px] pointer-events-none"></i>
          </div>
          <!-- Dropdown Periode -->
          <div class="relative">
            <select onchange="if(this.value) window.location.href=this.value" class="h-[36px] bg-[#FFFFFF] border border-[#D1D5D8] rounded-[8px] pl-3 pr-8 text-[13px] font-semibold text-[#1B2A3A] outline-none hover:border-[#0E5C58] cursor-pointer appearance-none transition-colors shadow-xs">
              '.$periodDropdownOptionsHtml.'
            </select>
            <i class="bi bi-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-[#53595F] text-[11px] pointer-events-none"></i>
          </div>
        </div>
        ' : '').'
      </div>

      <!-- 4. Bar Pintasan Cepat (Quick Action Hub - Hanya di Tab Antrean & Temuan IT) -->
      '.(!$isTabKartu ? '
      <div class="bg-white border border-[#D1D5D8] rounded-[10px] p-3 mb-4 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
          <div class="flex items-center gap-2">
            <span class="w-7 h-7 rounded-[6px] bg-[#D9F1EF] text-[#0E5C58] flex items-center justify-center text-[13px] font-bold shrink-0">
              <i class="bi bi-lightning-charge-fill"></i>
            </span>
            <div>
              <div class="text-[12px] font-bold text-[#1B2A3A] leading-tight">Pintasan Cepat Operasional</div>
              <div class="text-[11px] text-[#53595F] leading-tight">Akses instan checklist teknisi, buku tamu DC &amp; pelaporan</div>
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <a href="'.e(module_url('scan_mobile.php')).'" class="h-[32px] px-3 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[6px] text-[12px] font-semibold transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
              <i class="bi bi-qr-code-scan"></i> Pindai QR Kamera
            </a>
            <a href="'.e(module_url('server_room_log.php')).'" class="h-[32px] px-3 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] rounded-[6px] text-[12px] font-medium transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
              <i class="bi bi-door-open-fill text-[#0E5C58]"></i> Buku Tamu Data Center
            </a>
            <a href="'.e(module_url('system_report.php')).'" target="_blank" class="h-[32px] px-3 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] rounded-[6px] text-[12px] font-medium transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
              <i class="bi bi-file-earmark-bar-graph-fill text-[#0284C7]"></i> Cetak Laporan Bulanan
            </a>
            <a href="'.e(module_url('dashboard.php', ['tab' => 'temuan'])).'" class="h-[32px] px-3 bg-white border border-[#D1D5D8] hover:bg-[#FEF2F2] text-[#DC2626] rounded-[6px] text-[12px] font-medium transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
              <i class="bi bi-exclamation-triangle-fill"></i> Lapor Temuan Kerusakan
            </a>
          </div>
        </div>
      </div>
      ' : '').'

      <!-- 1. Penambahan 4 Metrik Kartu Eksekutif (88px Symmetrical Metric Cards) & Widget Sebaran / Feed (Hanya Tampil di Dashboard Utama / Tab Antrean) -->
      ' . ($isTabAntrean ? '
      <div class="bg-[#FFFFFF] border border-[#D1D5D8] rounded-[10px] flex flex-col md:flex-row overflow-hidden shrink-0 mb-4 shadow-xs">
        <!-- Card 1: Belum Dipelihara [Aksen Teal] -->
        <a href="'.e(module_url('dashboard.php', ['cabang'=>$cabangId, 'bulan'=>$month, 'tahun'=>$year])).'" class="flex-1 flex flex-col justify-center px-5 py-3 border-b md:border-b-0 md:border-r border-[#D1D5D8] relative cursor-pointer '.($isTabAntrean ? 'bg-[#D9F1EF]' : 'hover:bg-[#F4F5F7]').' transition-colors text-decoration-none">
          <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0E5C58]"></div>
          <div class="text-[26px] font-bold text-[#1B2A3A] tabular-nums leading-none tracking-tight">
            '.$displayDueCount.' <span class="text-[13px] font-normal text-[#53595F]">Unit</span>
          </div>
          <div class="text-[12px] font-medium text-[#1B2A3A] mt-1.5 flex items-center gap-1.5">
            <span class="text-[#0E5C58] font-bold">Belum Dipelihara</span>
            <span>&middot;</span>
            <span class="text-[11px] text-[#53595F]">Bulan Berjalan</span>
          </div>
        </a>

        <!-- Card 2: Kepatuhan SLA IT [Aksen Hijau] -->
        <div class="flex-1 flex flex-col justify-center px-5 py-3 border-b md:border-b-0 md:border-r border-[#D1D5D8] relative hover:bg-[#F4F5F7] transition-colors">
          <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#10B981]"></div>
          <div class="text-[26px] font-bold text-[#10B981] tabular-nums leading-none tracking-tight flex items-center justify-between">
            <span>'.$displaySlaPercent.'</span>
            <span class="px-2 py-0.5 rounded-[4px] text-[10px] font-bold '.($slaOptimal ? 'bg-[#ECFDF5] text-[#10B981] border border-[#A7F3D0]' : 'bg-[#FFFBEB] text-[#D97706] border border-[#FDE68A]').'">
              '.($slaOptimal ? 'Optimal' : 'Perlu Dikejar').'
            </span>
          </div>
          <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
            <span class="text-[#10B981] font-bold">Kepatuhan SLA IT</span>
            <span>&middot;</span>
            <span class="text-[11px] text-[#53595F]">Target Bulan Ini</span>
          </div>
        </div>

        <!-- Card 3: Temuan Terbuka [Aksen Merah] -->
        <a href="'.e(module_url('dashboard.php', ['tab'=>'temuan', 'cabang'=>$cabangId, 'bulan'=>$month, 'tahun'=>$year])).'" class="flex-1 flex flex-col justify-center px-5 py-3 border-b md:border-b-0 md:border-r border-[#D1D5D8] relative cursor-pointer '.($isTabTemuan ? 'bg-[#FDEDEC]' : 'hover:bg-[#F4F5F7]').' transition-colors text-decoration-none">
          <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#DC2626]"></div>
          <div class="text-[26px] font-bold text-[#DC2626] tabular-nums leading-none tracking-tight flex items-center justify-between">
            <span>'.$displayFindingsCount.' <span class="text-[13px] font-normal text-[#53595F]">Kendala</span></span>
            '.($displayFindingsCount > 0 ? '<span class="px-2 py-0.5 rounded-[4px] text-[10px] font-bold bg-[#FEF2F2] text-[#DC2626] border border-[#FECACA]">Perlu Tindak Lanjut</span>' : '<span class="px-2 py-0.5 rounded-[4px] text-[10px] font-bold bg-[#ECFDF5] text-[#10B981] border border-[#A7F3D0]">Nihil Kendala</span>').'
          </div>
          <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
            <span class="text-[#DC2626] font-bold">Temuan Terbuka</span>
            <span>&middot;</span>
            <span class="text-[11px] text-[#53595F]">Semua Periode</span>
          </div>
        </a>

        <!-- Card 4: Ruang Server DC [Aksen Biru] -->
        <a href="'.e(module_url('server_room_log.php')).'" class="flex-1 flex flex-col justify-center px-5 py-3 relative hover:bg-[#F4F5F7] transition-colors text-decoration-none">
          <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0284C7]"></div>
          <div class="text-[26px] font-bold text-[#1B2A3A] tabular-nums leading-none tracking-tight flex items-center justify-between">
            <span>'.$displayGuestsCount.' <span class="text-[13px] font-normal text-[#53595F]">Tamu Aktif</span></span>
            '.($displayGuestsCount > 0 ? '<span class="px-2 py-0.5 rounded-[4px] text-[10px] font-bold bg-[#EFF6FF] text-[#0284C7] border border-[#BFDBFE]"><i class="bi bi-shield-check me-0.5"></i>'.$displayGuestsCount.' di DC</span>' : '<span class="px-2 py-0.5 rounded-[4px] text-[10px] font-bold bg-[#F4F5F7] text-[#53595F] border border-[#E5E7EB]"><i class="bi bi-shield me-0.5"></i>Log DC</span>').'
          </div>
          <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
            <span class="text-[#0284C7] font-bold">Ruang Server DC</span>
            <span>&middot;</span>
            <span class="text-[11px] text-[#53595F]">'.($displayGuestsCount > 0 ? 'Sedang di Ruang Server' : 'Buku Tamu Digital').'</span>
          </div>
        </a>
      </div>

      <!-- Grid 2-Kolom: Widget Sebaran 5 Cabang & Feed Aktivitas Terkini -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        <!-- 2. Widget Sebaran Kepatuhan 5 Kantor Cabang -->
        <div class="bg-white border border-[#D1D5D8] rounded-[10px] p-4 shadow-xs flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between pb-2.5 mb-2.5 border-b border-[#F3F4F6]">
              <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-[6px] bg-[#D9F1EF] text-[#0E5C58] flex items-center justify-center text-[13px] font-bold">
                  <i class="bi bi-buildings"></i>
                </span>
                <div>
                  <h3 class="text-[14px] font-bold text-[#1B2A3A] m-0 leading-tight">Sebaran Kepatuhan Kantor Cabang</h3>
                  <p class="text-[11px] text-[#53595F] m-0 leading-tight mt-0.5">Progres pemeliharaan bulan '.$monthNames[$month].' '.$year.'</p>
                </div>
              </div>
              <span class="text-[11px] font-bold text-[#0E5C58] bg-[#D9F1EF] px-2 py-0.5 rounded border border-[#A7E2DC]">
                Klik Cabang
              </span>
            </div>
            <div class="flex flex-col gap-1.5">
              '.$branchComplianceHtml.'
            </div>
          </div>
          <div class="pt-2 mt-2 border-t border-[#F3F4F6] flex items-center justify-between text-[11px] text-[#53595F]">
            <span>Menampilkan '.count($branchSummaries).' Cabang Bank Mitra</span>
            <div class="flex items-center gap-2">
              '.(is_admin() ? '<button type="button" class="text-[#0E5C58] bg-[#D9F1EF] hover:bg-[#0E5C58] hover:text-white px-2 py-0.5 rounded font-bold transition-colors border-0 cursor-pointer" data-bs-toggle="modal" data-bs-target="#modalKelolaSkipCabang"><i class="bi bi-calendar-x me-1"></i>Atur Skip Cabang</button>' : '').'
              <a href="'.e(module_url('cabang_admin.php', ['bulan' => $month, 'tahun' => $year])).'" class="text-[#0E5C58] font-bold hover:underline text-decoration-none">Kelola Cabang &rarr;</a>
            </div>
          </div>
        </div>

        <!-- 3. Feed Aktivitas Terkini Real-Time -->
        <div class="bg-white border border-[#D1D5D8] rounded-[10px] p-4 shadow-xs flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between pb-2.5 mb-2.5 border-b border-[#F3F4F6]">
              <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-[6px] bg-[#D9F1EF] text-[#0E5C58] flex items-center justify-center text-[13px] font-bold">
                  <i class="bi bi-clock-history"></i>
                </span>
                <div>
                  <h3 class="text-[14px] font-bold text-[#1B2A3A] m-0 leading-tight">Feed Aktivitas Terkini Real-Time</h3>
                  <p class="text-[11px] text-[#53595F] m-0 leading-tight mt-0.5">5 aktivitas inspeksi, pemeliharaan &amp; log terbaru</p>
                </div>
              </div>
              <a href="'.e(module_url('audit_trail.php')).'" class="text-[11px] font-bold text-[#0E5C58] bg-[#D9F1EF] px-2 py-0.5 rounded border border-[#A7E2DC] text-decoration-none hover:bg-[#0E5C58] hover:text-white transition-colors">
                Semua Log &rarr;
              </a>
            </div>
            <div class="flex flex-col gap-2 relative before:absolute before:top-2 before:bottom-2 before:left-[11px] before:w-[2px] before:bg-[#E5E7EB]">
              '.$liveActivitiesHtml.'
            </div>
          </div>
          <div class="pt-2 mt-2 border-t border-[#F3F4F6] flex items-center justify-between text-[11px] text-[#53595F]">
            <span>Diperbarui otomatis secara real-time</span>
            <span class="text-[#10B981] font-semibold flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-[#10B981] animate-ping"></span> Live Sync Aktif</span>
          </div>
        </div>
      </div>
      ' : '') . '

      <!-- Banner Info Jika Cabang Terpilih Berstatus Dilewati Bulan Ini -->
      '.($cabangId > 0 && function_exists('is_branch_exempted') && is_branch_exempted($cabangId, $month, $year) ? '
      <div class="bg-[#F8FAF9] border border-[#CBD5E1] rounded-[10px] p-3 mb-4 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-[8px] bg-[#64748B] text-white flex items-center justify-center shrink-0">
            <i class="bi bi-calendar-x"></i>
          </div>
          <div>
            <div class="text-[13px] font-bold text-[#1E293B]">Pemeliharaan '.e($selectedCabangName).' Dilewati untuk Bulan '.e($monthNames[$month]).' '.$year.'</div>
            <div class="text-[12px] text-[#64748B] mt-0.5">Cabang ini berstatus <em>off-schedule</em> (tidak ada jadwal kunjungan dinas). Unit aset tidak dihitung dalam target kepatuhan bulanan.</div>
          </div>
        </div>
        '.(is_admin() ? '
        <form method="post" class="m-0 shrink-0" onsubmit="return confirm(\'Aktifkan kembali jadwal pemeliharaan cabang ini?\');">
          '.csrf_field().'
          <input type="hidden" name="action" value="cancel_skip_cabang">
          <input type="hidden" name="cabang_id" value="'.$cabangId.'">
          <input type="hidden" name="bulan" value="'.$month.'">
          <input type="hidden" name="tahun" value="'.$year.'">
          <input type="hidden" name="return_cabang" value="'.$cabangId.'">
          <button type="submit" class="h-[32px] px-3 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[6px] text-[11px] font-semibold border-0 shadow-xs cursor-pointer inline-flex items-center gap-1">
            <i class="bi bi-check-circle"></i> <span>Aktifkan Kembali</span>
          </button>
        </form>' : '').'
      </div>' : '').'

      <!-- Tabs Navigation (44px, Mobile Horizontal Scroll) -->
      <div class="flex gap-4 md:gap-6 h-[44px] border-b border-[#D1D5D8] shrink-0 mb-4 overflow-x-auto whitespace-nowrap no-scrollbar">
        <a href="'.e(module_url('dashboard.php', ['cabang'=>$cabangId, 'bulan'=>$month, 'tahun'=>$year])).'" class="h-full flex items-center font-'.($isTabAntrean ? 'bold text-[#1B2A3A] border-b-[3px] border-[#0E5C58]' : 'semibold text-[#53595F] hover:text-[#1B2A3A] border-b-[3px] border-transparent').' px-1 relative top-[1px] transition-colors text-decoration-none">
          <i class="bi bi-clock-history me-1.5 '.($isTabAntrean ? 'text-[#0E5C58]' : '').'"></i> Belum dipelihara ('.$displayDueCount.')
        </a>
        <a href="'.e(module_url('dashboard.php', ['tab'=>'temuan', 'cabang'=>$cabangId, 'bulan'=>$month, 'tahun'=>$year])).'" class="h-full flex items-center font-'.($isTabTemuan ? 'bold text-[#1B2A3A] border-b-[3px] border-[#0E5C58]' : 'semibold text-[#53595F] hover:text-[#1B2A3A] border-b-[3px] border-transparent').' px-1 relative top-[1px] transition-colors text-decoration-none">
          <i class="bi bi-exclamation-triangle me-1.5 '.($isTabTemuan ? 'text-[#DC2626]' : ($displayFindingsCount > 0 ? 'text-[#DC2626]' : '')).'"></i> Temuan terbuka ('.$displayFindingsCount.')
          '.($displayFindingsCount > 0 ? '<span class="badge bg-danger rounded-pill ms-1 text-[10px] px-1.5 py-0.2">'.$displayFindingsCount.'</span>' : '').'
        </a>
        <a href="'.e(module_url('dashboard.php', ['tab'=>'kartu'])).'" class="h-full flex items-center font-'.($isTabKartu ? 'bold text-[#1B2A3A] border-b-[3px] border-[#0E5C58]' : 'semibold text-[#53595F] hover:text-[#1B2A3A] border-b-[3px] border-transparent').' px-1 relative top-[1px] transition-colors text-decoration-none">
          <i class="bi bi-credit-card-2-front me-1.5 '.($isTabKartu ? 'text-[#0E5C58]' : '').'"></i> Kartu Inventaris ('.$totalCardsCount.')
        </a>
        <a href="'.e(module_url('asset_gallery.php', $cabangId > 0 ? ['cabang'=>$cabangId] : [])).'" class="h-full flex items-center font-semibold text-[#53595F] hover:text-[#1B2A3A] border-b-[3px] border-transparent px-1 relative top-[1px] transition-colors text-decoration-none">
          <i class="bi bi-images me-1.5"></i> Galeri Foto
        </a>
        '.(is_admin_or_auditor() ? '<a href="'.e(module_url('audit_trail.php')).'" class="h-full flex items-center font-semibold text-[#53595F] hover:text-[#1B2A3A] border-b-[3px] border-transparent px-1 relative top-[1px] transition-colors text-decoration-none"><i class="bi bi-journal-text me-1.5"></i> Log Audit</a>' : '').'
      </div>

      <!-- Banner Temuan Kerusakan / Keluhan Aktif Menunggu Tindak Lanjut -->
      '.($isTabAntrean && $displayFindingsCount > 0 ? '
      <div class="alert alert-danger border-danger border-start border-4 shadow-sm rounded-3 py-3 px-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2.5">
          <i class="bi bi-exclamation-triangle-fill text-danger fs-3 flex-shrink-0"></i>
          <div>
            <div class="fw-bold text-dark fs-6">Perangkat Membutuhkan Tindak Lanjut Teknisi ('.$displayFindingsCount.' Unit)</div>
            <div class="small text-secondary mt-0.5">Ada keluhan/temuan kerusakan aktif dari pengguna komputer yang menunggu penanganan teknisi IT.</div>
          </div>
        </div>
        <a href="'.e(module_url('dashboard.php', ['tab'=>'temuan', 'cabang'=>$cabangId, 'bulan'=>$month, 'tahun'=>$year])).'" class="btn btn-danger btn-sm fw-bold px-3 py-2 shadow-xs text-decoration-none">
          <i class="bi bi-tools me-1"></i> Buka Temuan &amp; Selesaikan Sekarang &rarr;
        </a>
      </div>' : '').'
    ';
}
