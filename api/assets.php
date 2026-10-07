<?php
require __DIR__ . '/bootstrap.php';
require_login();

// Parameter Pencarian & Filter
$search = trim((string)($_GET['q'] ?? ''));
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));
$divisiId = max(0, (int)($_GET['divisi'] ?? 0));
$statusAset = trim((string)($_GET['status'] ?? ''));
$maintStatus = trim((string)($_GET['maint'] ?? '')); // 'all', 'done', 'pending', 'repair'
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(5, min(100, (int)($_GET['per_page'] ?? 15)));

$month = (int)date('n');
$year = (int)date('Y');
$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$currentMonthName = $monthNames[$month] ?? date('F');

// 1. Ambil data master untuk filter dropdown & edit massal
$cabangs = get_cabang_list();
$divisis = get_divisi_list();
$kategoris = get_kategori_list();
$karyawans = get_karyawan_list();

// 2. Query data aset dasar
if (!empty($_GET['sync_device_names']) && is_admin()) {
    $syncCount = sync_maintenance_scan_device_names(true);
    $_SESSION['flash'] = "Berhasil menyinkronkan nama perangkat pada {$syncCount} baris di sheet Maintenance_Scan.";
    header('Location: ' . module_url('assets.php'));
    exit;
}

$rawAssets = [];
if (is_google_cloud_mode()) {
    $client = google_sheets_v4_client();
    if ($client) {
        $client->preloadSheets(['Assets', 'Cabang', 'Divisi', 'Karyawan', 'Kategori_Aset', 'Asset_QR_Tokens', 'Maintenance_Scan']);
        if (empty($_SESSION['_maint_scan_synced_v1'])) {
            $_SESSION['_maint_scan_synced_v1'] = 1;
            sync_maintenance_scan_device_names(false);
        }
    }
    $rawAssets = map_sheets_assets();
} else {
    try {
        $st = db()->query(asset_query_base() . " ORDER BY a.id DESC LIMIT 3000");
        $rawAssets = $st ? $st->fetchAll() : [];
    } catch (Throwable $e) {
        $rawAssets = [];
    }
}

// 3. Ambil riwayat scan bulan ini untuk menentukan status maintenance tiap aset
$maintStatusMap = [];
if (is_google_cloud_mode()) {
    $client = google_sheets_v4_client();
    $scans = $client ? $client->getSheetData('Maintenance_Scan') : [];
    foreach ($scans as $s) {
        $aid = (int)($s['asset_id'] ?? $s['id_asset'] ?? $s['col_1'] ?? 0);
        $sM = (int)($s['maintenance_month'] ?? $s['month'] ?? $s['col_6'] ?? 0);
        $sDate = (string)($s['maintenance_date'] ?? $s['col_4'] ?? '');
        if ($sM <= 0 && $sDate !== '') {
            $sM = (int)date('n', strtotime($sDate));
        }
        $sY = (int)($s['maintenance_year'] ?? $s['year'] ?? $s['col_7'] ?? 0);
        if ($sY <= 0 && $sDate !== '') {
            $sY = (int)date('Y', strtotime($sDate));
        }

        if ($aid > 0 && $sM === $month && $sY === $year) {
            $st = trim((string)($s['status'] ?? $s['col_8'] ?? 'Selesai'));
            $maintStatusMap[$aid] = [
                'is_done' => true,
                'status' => $st !== '' ? $st : 'Selesai',
                'date' => substr($sDate, 0, 10),
                'tech' => (string)($s['technician_name'] ?? 'Teknisi')
            ];
        }
    }
} else {
    try {
        $uName = name_column('users') ?: 'id';
        $mSql = "
            SELECT ms.asset_id, ms.status, ms.maintenance_date,
                   COALESCE(ms.technician_name, u.`{$uName}`, 'Teknisi') AS technician_name
            FROM maintenance_scan ms
            LEFT JOIN users u ON u.id = ms.technician_user_id
            WHERE ms.maintenance_month = ? AND ms.maintenance_year = ?
            ORDER BY ms.id ASC
        ";
        $mSt = db()->prepare($mSql);
        $mSt->execute([$month, $year]);
        $scans = $mSt->fetchAll();
        foreach ($scans as $s) {
            $aid = (int)($s['asset_id'] ?? 0);
            if ($aid > 0) {
                $st = trim((string)($s['status'] ?? 'Selesai'));
                $maintStatusMap[$aid] = [
                    'is_done' => true,
                    'status' => $st !== '' ? $st : 'Selesai',
                    'date' => substr((string)($s['maintenance_date'] ?? ''), 0, 10),
                    'tech' => (string)($s['technician_name'] ?? 'Teknisi')
                ];
            }
        }
    } catch (Throwable $e) {}
}

// Cek cache session bila ada scan baru disimpan saat sesi aktif
if (session_status() === PHP_SESSION_ACTIVE) {
    foreach ($rawAssets as $a) {
        $aid = (int)($a['id'] ?? 0);
        if ($aid > 0 && !empty($_SESSION['_recent_scan_' . $aid])) {
            $rec = $_SESSION['_recent_scan_' . $aid];
            $rM = (int)($rec['maintenance_month'] ?? (int)date('n', strtotime((string)($rec['maintenance_date'] ?? ''))));
            $rY = (int)($rec['maintenance_year'] ?? (int)date('Y', strtotime((string)($rec['maintenance_date'] ?? ''))));
            if ($rM === $month && $rY === $year) {
                $st = trim((string)($rec['status'] ?? 'Selesai'));
                $maintStatusMap[$aid] = [
                    'is_done' => true,
                    'status' => $st !== '' ? $st : 'Selesai',
                    'date' => substr((string)($rec['maintenance_date'] ?? date('Y-m-d')), 0, 10),
                    'tech' => (string)($rec['technician_name'] ?? current_user_name() ?: 'Teknisi')
                ];
            }
        }
    }
}

// 4. Hitung Statistik Sesuai Filter Lokasi (Cabang & Divisi)
$statTotal = 0;
$statAktif = 0;
$statDoneMaint = 0;
$statPendingMaint = 0;
$statRepair = 0;

foreach ($rawAssets as $a) {
    if ($cabangId > 0 && (int)($a['id_cabang'] ?? 0) !== $cabangId) continue;
    if ($divisiId > 0 && (int)($a['id_divisi'] ?? 0) !== $divisiId) continue;

    $statTotal++;
    $aid = (int)($a['id'] ?? 0);
    $stAset = strtolower(trim((string)($a['status'] ?? 'Aktif')));
    if ($stAset === 'aktif' || $stAset === '') {
        $statAktif++;
    }

    $mInfo = $maintStatusMap[$aid] ?? null;
    if ($mInfo && $mInfo['is_done']) {
        $statDoneMaint++;
        if (in_array($mInfo['status'], ['Temuan', 'Perlu Perbaikan', 'Proses'], true)) {
            $statRepair++;
        }
    } else {
        $statPendingMaint++;
    }
}

// 5. Filter Data Aset
$filteredAssets = array_filter($rawAssets, function($a) use ($search, $cabangId, $divisiId, $statusAset, $maintStatus, $maintStatusMap) {
    $aid = (int)($a['id'] ?? 0);

    // Filter Cabang
    if ($cabangId > 0 && (int)($a['id_cabang'] ?? 0) !== $cabangId) {
        return false;
    }

    // Filter Divisi
    if ($divisiId > 0 && (int)($a['id_divisi'] ?? 0) !== $divisiId) {
        return false;
    }

    // Filter Status Komputer
    if ($statusAset !== '') {
        $curStatus = trim((string)($a['status'] ?? 'Aktif'));
        if (strcasecmp($curStatus, $statusAset) !== 0) {
            return false;
        }
    }

    // Filter Status Maintenance Bulan Ini
    if ($maintStatus !== '' && $maintStatus !== 'all') {
        $mInfo = $maintStatusMap[$aid] ?? null;
        $isDone = $mInfo && $mInfo['is_done'];
        $stVal = $isDone ? ($mInfo['status'] ?? 'Selesai') : 'Belum Maintenance';

        if ($maintStatus === 'done' && !$isDone) return false;
        if ($maintStatus === 'pending' && $isDone) return false;
        if ($maintStatus === 'repair' && (!in_array($stVal, ['Temuan', 'Perlu Perbaikan', 'Proses'], true))) return false;
    }

    // Filter Pencarian Teks Bebas
    if ($search !== '') {
        $q = strtolower($search);
        $haystack = strtolower(implode(' ', [
            $a['kode_inventaris'] ?? '',
            $a['merk'] ?? '',
            $a['model'] ?? '',
            $a['serial_number'] ?? '',
            $a['karyawan_nama'] ?? '',
            $a['cabang_nama'] ?? '',
            $a['divisi_nama'] ?? '',
            $a['kategori_nama'] ?? '',
            $a['ip_address'] ?? $a['ip'] ?? '',
            $a['printer'] ?? '',
            $a['keterangan'] ?? ''
        ]));
        if (strpos($haystack, $q) === false) {
            return false;
        }
    }

    return true;
});

// Urutkan: Cabang, lalu Nama Karyawan/User, lalu Kode Inventaris
usort($filteredAssets, function($a, $b) {
    $cb = strcasecmp((string)($a['cabang_nama'] ?? ''), (string)($b['cabang_nama'] ?? ''));
    if ($cb !== 0) return $cb;
    $usr = strcasecmp((string)($a['karyawan_nama'] ?? ''), (string)($b['karyawan_nama'] ?? ''));
    if ($usr !== 0) return $usr;
    return strcasecmp((string)($a['kode_inventaris'] ?? ''), (string)($b['kode_inventaris'] ?? ''));
});

// 6. Pagination
$totalFiltered = count($filteredAssets);
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;
$pageAssets = array_slice($filteredAssets, $offset, $perPage);

// Flash message
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
$flashHtml = $flash ? '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4"><i class="bi bi-check-circle-fill me-2"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

// Helper Query URL untuk Pagination & Filter
function filter_query(array $overrides = []): string {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    return module_url('assets.php', $params);
}

// Render Opsi Dropdown Cabang
$optCab = '<option value="0">Semua Cabang ('.count($cabangs).')</option>';
foreach ($cabangs as $c) {
    $cid = (int)($c['id'] ?? 0);
    $cnama = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cid);
    $optCab .= '<option value="'.$cid.'"'.($cid === $cabangId ? ' selected' : '').'>'.e($cnama).'</option>';
}

// Hitung total aset per cabang untuk badge di pill navigation
$branchAssetCounts = [];
$totalAllAssets = count($rawAssets);
foreach ($rawAssets as $a) {
    $cId = (int)($a['id_cabang'] ?? 0);
    $branchAssetCounts[$cId] = ($branchAssetCounts[$cId] ?? 0) + 1;
}

// Render Branch Navigation Pills
$branchPillsHtml = '<div class="branch-nav-bar custom-scrollbar mb-3">';
$allActive = ($cabangId === 0);
$branchPillsHtml .= '<a class="branch-nav-pill '.($allActive ? 'active' : '').'" href="'.e(filter_query(['cabang' => 0, 'page' => 1])).'">
  <i class="bi bi-buildings"></i> Semua Cabang
  <span class="badge rounded-pill '.($allActive ? 'bg-white bg-opacity-25 text-white' : 'bg-light text-dark').'">'.$totalAllAssets.'</span>
</a>';
foreach ($cabangs as $c) {
    $cid = (int)($c['id'] ?? 0);
    $cnama = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cid);
    $cCount = $branchAssetCounts[$cid] ?? 0;
    $isActive = ($cid === $cabangId);
    $branchPillsHtml .= '<a class="branch-nav-pill '.($isActive ? 'active' : '').'" href="'.e(filter_query(['cabang' => $cid, 'page' => 1])).'">
      <i class="bi bi-geo-alt'.($isActive ? '-fill' : '').'"></i> '.e($cnama).'
      <span class="badge rounded-pill '.($isActive ? 'bg-white bg-opacity-25 text-white' : 'bg-light text-dark').'">'.$cCount.'</span>
    </a>';
}
$branchPillsHtml .= '</div>';

$extraHead = '';

// Render Opsi Dropdown Divisi
$optDiv = '<option value="0">Semua Divisi ('.count($divisis).')</option>';
foreach ($divisis as $d) {
    $did = (int)($d['id'] ?? 0);
    $dnama = $d['nama'] ?? $d['nama_divisi'] ?? ('Divisi #' . $did);
    $optDiv .= '<option value="'.$did.'"'.($did === $divisiId ? ' selected' : '').'>'.e($dnama).'</option>';
}

// Render Opsi Dropdown untuk Edit Massal
$bulkOptCab = '<option value="">-- Jangan Ubah (Tetap) --</option>';
foreach ($cabangs as $c) {
    $cid = (int)($c['id'] ?? 0);
    $cnama = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cid);
    $bulkOptCab .= '<option value="'.$cid.'">'.e($cnama).'</option>';
}

$bulkOptDiv = '<option value="">-- Jangan Ubah (Tetap) --</option>';
foreach ($divisis as $d) {
    $did = (int)($d['id'] ?? 0);
    $dnama = $d['nama'] ?? $d['nama_divisi'] ?? ('Divisi #' . $did);
    $bulkOptDiv .= '<option value="'.$did.'">'.e($dnama).'</option>';
}

$bulkOptKat = '<option value="">-- Jangan Ubah (Tetap) --</option>';
foreach ($kategoris as $k) {
    $kid = (int)($k['id'] ?? 0);
    $knama = $k['nama'] ?? $k['nama_kategori'] ?? ('Kategori #' . $kid);
    $bulkOptKat .= '<option value="'.$kid.'">'.e($knama).'</option>';
}

$datalistKaryawan = '';
$uniqueKaryawan = [];
foreach ($karyawans as $kar) {
    $kn = trim((string)($kar['nama_karyawan'] ?? $kar['nama'] ?? ''));
    if ($kn !== '' && !isset($uniqueKaryawan[$kn])) {
        $uniqueKaryawan[$kn] = true;
        $datalistKaryawan .= '<option value="'.e($kn).'">';
    }
}

// Status Banner Khusus Filter Cepat To-Do List
$statusBannerHtml = '';
if ($maintStatus === 'pending') {
    $statusBannerHtml = '
    <div class="alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25 py-3 px-4 rounded-4 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 shadow-sm">
      <div class="d-flex align-items-center gap-3">
        <div class="fs-2 text-danger"><i class="bi bi-clipboard2-x-fill"></i></div>
        <div>
          <h6 class="fw-bold text-danger mb-0"><i class="bi bi-bullseye me-1"></i> TO-DO LIST: Komputer Jatuh Tempo (Belum Diperiksa Bulan Ini)</h6>
          <div class="small text-muted">Ditemukan <strong>'.$totalFiltered.' unit</strong> komputer yang belum dilakukan pemeliharaan pada periode '.$currentMonthName.' '.$year.'. Klik tombol hijau <strong>"Periksa"</strong> di kolom aksi untuk langsung mengisi checklist.</div>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-danger fs-6 px-3 py-2 rounded-pill">Target: '.$totalFiltered.' Unit</span>
      </div>
    </div>';
} elseif ($maintStatus === 'repair') {
    $statusBannerHtml = '
    <div class="alert alert-warning bg-warning bg-opacity-10 border-warning border-opacity-50 py-3 px-4 rounded-4 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 shadow-sm">
      <div class="d-flex align-items-center gap-3">
        <div class="fs-2 text-warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <div>
          <h6 class="fw-bold text-warning-emphasis mb-0"><i class="bi bi-tools me-1"></i> DAFTAR TEMUAN MASALAH / PERBAIKAN (Pending Issues)</h6>
          <div class="small text-muted">Ditemukan <strong>'.$totalFiltered.' unit</strong> komputer yang mengalami kendala hardware/software. Klik tombol merah <strong>"Perbaiki"</strong> untuk mencatat tindakan perbaikan.</div>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-warning text-dark fs-6 px-3 py-2 rounded-pill">Perlu Perbaikan: '.$totalFiltered.' Unit</span>
      </div>
    </div>';
} elseif ($maintStatus === 'done') {
    $statusBannerHtml = '
    <div class="alert alert-success bg-success bg-opacity-10 border-success border-opacity-25 py-3 px-4 rounded-4 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 shadow-sm">
      <div class="d-flex align-items-center gap-3">
        <div class="fs-2 text-success"><i class="bi bi-patch-check-fill"></i></div>
        <div>
          <h6 class="fw-bold text-success mb-0"><i class="bi bi-check-circle-fill me-1"></i> DAFTAR KOMPUTER SELESAI MAINTENANCE</h6>
          <div class="small text-muted">Sebanyak <strong>'.$totalFiltered.' unit</strong> komputer telah selesai diperiksa dan tercatat normal di periode '.$currentMonthName.' '.$year.'.</div>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-success fs-6 px-3 py-2 rounded-pill">Selesai: '.$totalFiltered.' Unit</span>
      </div>
    </div>';
}

// Tab Filter Cepat (To-Do List Teknisi) HTML
$todoTabsHtml = '
<div class="card p-3 border-0 shadow-sm mb-4 bg-white" style="border-radius: 16px; border-left: 5px solid '.($maintStatus === 'pending' ? '#ef4444' : ($maintStatus === 'repair' ? '#f59e0b' : ($maintStatus === 'done' ? '#10b981' : '#2563eb'))).' !important;">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
    <div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge '.($maintStatus === 'pending' ? 'bg-danger text-white' : ($maintStatus === 'repair' ? 'bg-warning text-dark' : ($maintStatus === 'done' ? 'bg-success text-white' : 'bg-primary bg-opacity-10 text-primary'))).' fw-bold px-2 py-1">
          <i class="bi bi-list-task me-1"></i> TO-DO LIST TEKNISI
        </span>
        <span class="text-secondary small">Filter Cepat Status Periode: <strong>'.$currentMonthName.' '.$year.'</strong></span>
      </div>
      <div class="small text-muted mt-1">Pilih tab di bawah untuk memfokuskan daftar unit komputer:</div>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <a class="btn btn-outline-secondary btn-sm fw-semibold" target="_blank" href="'.e(module_url('print_qr.php', ['cabang' => $cabangId])).'">
        <i class="bi bi-qr-code me-1"></i> Cetak QR Cabang Ini
      </a>
      <a class="btn btn-outline-primary btn-sm fw-semibold" target="_blank" href="'.e(module_url('print_report.php', ['bulan' => $month, 'tahun' => $year, 'cabang' => $cabangId])).'">
        <i class="bi bi-printer me-1"></i> Cetak Laporan
      </a>
    </div>
  </div>

  <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
    <!-- 1. Semua Unit -->
    <a href="'.e(filter_query(['maint' => null, 'page' => 1])).'" 
       class="btn btn-sm rounded-pill px-3 fw-bold '.($maintStatus === '' || $maintStatus === 'all' ? 'btn-primary shadow-sm' : 'btn-outline-secondary').'">
      <i class="bi bi-grid-fill me-1"></i> Semua Unit ('.$statTotal.')
    </a>

    <!-- 2. Belum Diperiksa (To-Do Utama) -->
    <a href="'.e(filter_query(['maint' => 'pending', 'page' => 1])).'" 
       class="btn btn-sm rounded-pill px-3 fw-bold '.($maintStatus === 'pending' ? 'btn-danger text-white shadow' : 'btn-outline-danger').'" style="'.($maintStatus !== 'pending' ? 'background-color: #FEF2F2; border-color: #FCA5A5;' : '').'">
      <i class="bi bi-exclamation-circle-fill me-1"></i> Belum Diperiksa Bulan Ini ('.$statPendingMaint.')
    </a>

    <!-- 3. Ada Temuan Masalah -->
    <a href="'.e(filter_query(['maint' => 'repair', 'page' => 1])).'" 
       class="btn btn-sm rounded-pill px-3 fw-bold '.($maintStatus === 'repair' ? 'btn-warning text-dark shadow' : 'btn-outline-warning text-dark').'" style="'.($maintStatus !== 'repair' ? 'background-color: #FFFBEB; border-color: #FCD34D;' : '').'">
      <i class="bi bi-tools me-1"></i> Ada Temuan Masalah ('.$statRepair.')
    </a>

    <!-- 4. Sudah Selesai -->
    <a href="'.e(filter_query(['maint' => 'done', 'page' => 1])).'" 
       class="btn btn-sm rounded-pill px-3 fw-bold '.($maintStatus === 'done' ? 'btn-success text-white shadow' : 'btn-outline-success').'" style="'.($maintStatus !== 'done' ? 'background-color: #F0FDF4; border-color: #86EFAC;' : '').'">
      <i class="bi bi-check-circle-fill me-1"></i> Selesai ('.$statDoneMaint.')
    </a>
  </div>
</div>';

// Render Baris Tabel
$tableRows = '';
$startNum = $offset;
foreach ($pageAssets as $a) {
    $startNum++;
    $aid = (int)($a['id'] ?? 0);
    $kode = !empty($a['kode_inventaris']) ? $a['kode_inventaris'] : ('# ' . $aid);
    $device = asset_title($a);
    $sn = !empty($a['serial_number']) && $a['serial_number'] !== '-' ? $a['serial_number'] : '';
    $user = !empty($a['karyawan_nama']) && $a['karyawan_nama'] !== '-' ? $a['karyawan_nama'] : 'Umum / Pool';
    $cabang = !empty($a['cabang_nama']) && $a['cabang_nama'] !== '-' ? $a['cabang_nama'] : '-';
    $divisi = !empty($a['divisi_nama']) && $a['divisi_nama'] !== '-' ? $a['divisi_nama'] : '';
    $ip = !empty($a['ip_address']) ? $a['ip_address'] : (!empty($a['ip']) ? $a['ip'] : '-');
    $printer = !empty($a['printer']) ? $a['printer'] : '-';
    $stAset = trim((string)($a['status'] ?? 'Aktif')) ?: 'Aktif';
    $token = !empty($a['qr_token']) ? $a['qr_token'] : get_static_qr_token($aid);

    // Status Badge Komputer
    $statusDotClass = match (strtolower($stAset)) {
        'aktif' => 'operational',
        'perbaikan' => 'warning',
        'backup' => 'repair',
        'nonaktif' => 'offline',
        default => 'offline',
    };
    $statusChipClass = match (strtolower($stAset)) {
        'aktif' => 'chip-success',
        'perbaikan' => 'chip-warning',
        'backup' => 'chip-primary',
        'nonaktif' => 'chip-secondary',
        default => 'chip-secondary',
    };
    $statusBadge = '<span class="badge-chip '.$statusChipClass.'"><span class="status-dot '.$statusDotClass.'"></span> '.e(ucfirst($stAset)).'</span>';

    // Status Maintenance Bulan Berjalan
    $mInfo = $maintStatusMap[$aid] ?? null;
    $maintBadge = '';
    $isRepairIssue = false;
    $isDoneMaint = false;

    if ($mInfo && $mInfo['is_done']) {
        $st = $mInfo['status'];
        if (in_array($st, ['Temuan', 'Perlu Perbaikan', 'Proses'], true)) {
            $isRepairIssue = true;
            $maintBadge = '<span class="badge-chip chip-danger" title="Temuan pada '.$mInfo['date'].' oleh '.$mInfo['tech'].'"><i class="bi bi-exclamation-triangle-fill"></i> Temuan: '.e($st).'</span>';
        } else {
            $isDoneMaint = true;
            $maintBadge = '<span class="badge-chip chip-success" title="Selesai pada '.$mInfo['date'].' oleh '.$mInfo['tech'].'"><i class="bi bi-check2"></i> Selesai ('.$mInfo['date'].')</span>';
        }
    } else {
        $maintBadge = '<span class="badge-chip chip-warning"><i class="bi bi-clock"></i> Belum Diperiksa</span>';
    }

    // Link Action
    $scanUrl = $token ? module_url('scan.php', ['t' => $token]) : '';
    $cardUrl = module_url('print_card.php', ['id' => $aid, 'layout' => 'single']);
    $editUrl = module_url('asset_edit.php', ['id' => $aid]);
    $deleteUrl = module_url('asset_delete.php', ['id' => $aid, 'redirect' => $_SERVER['REQUEST_URI'] ?? module_url('assets.php')]);

    // Data untuk Modal Pratinjau Stiker QR (Quick QR Preview)
    $qrModalJson = '';
    if ($token && $scanUrl) {
        $qrModalData = [
            'id' => $aid,
            'kode' => $kode,
            'device' => $device,
            'user' => $user . ($divisi ? " ({$divisi})" : ''),
            'cabang' => $cabang,
            'url' => $scanUrl,
            'token' => $token
        ];
        $qrModalJson = htmlspecialchars(json_encode($qrModalData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
    }

    // Quick Action Button for Technicians
    $quickActionBtn = '';
    if ($isRepairIssue && $scanUrl) {
        $quickActionBtn = '<a class="btn btn-sm btn-danger py-1 px-2 fw-semibold text-nowrap" href="'.e($scanUrl . '&action=tindak_lanjut').'" title="Tindak Lanjuti Kendala"><i class="bi bi-tools me-1"></i> Perbaiki</a>';
    } elseif (!$isDoneMaint && $scanUrl) {
        $quickActionBtn = '<a class="btn btn-sm btn-primary py-1 px-2 fw-semibold text-nowrap" href="'.e($scanUrl . '&action=start').'" title="Mulai Checklist Pemeliharaan"><i class="bi bi-clipboard-check me-1"></i> Periksa</a>';
    }

    $tableRows .= '
    <tr id="row-asset-'.$aid.'">
      <td class="text-center">
        <input class="form-check-input asset-checkbox" type="checkbox" value="'.$aid.'" data-id="'.$aid.'" data-kode="'.e($kode).'" data-device="'.e($device).'" data-user="'.e($user).'" data-cabang="'.e($cabang).'" data-sn="'.e($sn).'" style="cursor: pointer; width: 1.15rem; height: 1.15rem;" title="Pilih unit '.e($kode).'">
      </td>
      <td class="text-center text-muted small">'.$startNum.'</td>
      <td>
        <div class="fw-bold text-dark fs-6 font-monospace">'.e($kode).'</div>
        <small class="text-muted" style="font-size: 0.72rem;">ID: #'.$aid.'</small>
      </td>
      <td>
        <div class="fw-semibold text-dark">'.e($device).'</div>
        <div class="small text-secondary" style="font-size: 0.75rem;">
          '.($sn ? '<span class="me-2"><span class="tech-label">SN:</span> <code class="text-dark">'.e($sn).'</code></span>' : '').'
          '.(!empty($a['kategori_nama']) ? '<span class="badge bg-light text-secondary border" style="font-size: 0.7rem;">'.e($a['kategori_nama']).'</span>' : '').'
        </div>
      </td>
      <td>
        <div class="fw-semibold text-dark small"><i class="bi bi-person text-secondary me-1"></i>'.e($user).'</div>
        '.($divisi ? '<div class="small text-secondary" style="font-size: 0.72rem;"><i class="bi bi-diagram-3 me-1 text-muted"></i>'.e($divisi).'</div>' : '').'
      </td>
      <td>
        <div class="fw-semibold text-dark small"><i class="bi bi-building text-secondary me-1"></i>'.e($cabang).'</div>
        '.($ip !== '-' ? '<div class="small text-muted font-monospace" style="font-size: 0.72rem;"><i class="bi bi-hdd-network me-1 text-success"></i>'.e($ip).'</div>' : '').'
      </td>
      <td class="text-center">'.$statusBadge.'</td>
      <td class="text-center">'.$maintBadge.'</td>
      <td class="text-center text-nowrap">
        '.($qrModalJson ? '
        <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 py-1 px-2 rounded-pill shadow-xs" 
          onclick="openQrModal('.$qrModalJson.')" title="Klik untuk Pratinjau & Unduh QR">
          <i class="bi bi-qr-code fs-6"></i>
          <span class="small fw-semibold" style="font-size: 0.72rem;">LIHAT QR</span>
        </button>' : '
        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1 rounded-pill font-monospace" style="font-size: 0.72rem;">
          <i class="bi bi-dash-circle me-1"></i>BELUM ADA
        </span>').'
      </td>
      <td class="text-end text-nowrap">
        <div class="d-inline-flex align-items-center gap-1">
          '.$quickActionBtn.'
          <div class="btn-group btn-group-sm">
            '.($scanUrl ? '<a class="btn btn-sm btn-light border" href="'.e($scanUrl).'" title="Buka Kartu / Scan"><i class="bi bi-qr-code-scan"></i></a>' : '').'
            <a class="btn btn-sm btn-light border" target="_blank" href="'.e($cardUrl).'" title="Cetak Kartu Kontrol"><i class="bi bi-printer"></i></a>
            <a class="btn btn-sm btn-light border" href="'.e($editUrl).'" title="Edit Perangkat"><i class="bi bi-pencil"></i></a>
            <a class="btn btn-sm btn-light border text-danger" href="'.e($deleteUrl).'" title="Hapus Perangkat"><i class="bi bi-trash"></i></a>
          </div>
        </div>
      </td>
    </tr>';
}

if (empty($tableRows)) {
    $tableRows = '
    <tr>
      <td colspan="10" class="text-center py-5">
        <div class="text-secondary opacity-75 mb-2"><i class="bi bi-pc-display fs-1"></i></div>
        <h6 class="fw-bold text-secondary">Tidak ada data komputer yang cocok dengan filter.</h6>
        <p class="text-muted small mb-3">Coba ubah kata kunci pencarian atau reset filter di atas.</p>
        <a class="btn btn-sm btn-outline-primary" href="'.e(module_url('assets.php')).'"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter</a>
      </td>
    </tr>';
}

// Render Pagination Controls
$paginationHtml = '';
if ($totalPages > 1) {
    $paginationHtml .= '<nav aria-label="Page navigation" class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4">';
    $paginationHtml .= '<div class="small text-secondary">Menampilkan <strong>'.($totalFiltered > 0 ? $offset + 1 : 0).'</strong> - <strong>'.min($offset + $perPage, $totalFiltered).'</strong> dari <strong>'.$totalFiltered.'</strong> unit komputer</div>';
    $paginationHtml .= '<ul class="pagination pagination-sm mb-0">';
    
    // Prev
    if ($page > 1) {
        $paginationHtml .= '<li class="page-item"><a class="page-link" href="'.e(filter_query(['page' => $page - 1])).'">&laquo; Prev</a></li>';
    } else {
        $paginationHtml .= '<li class="page-item disabled"><span class="page-link">&laquo; Prev</span></li>';
    }

    $startPage = max(1, $page - 2);
    $endPage = min($totalPages, $page + 2);
    if ($startPage > 1) {
        $paginationHtml .= '<li class="page-item"><a class="page-link" href="'.e(filter_query(['page' => 1])).'">1</a></li>';
        if ($startPage > 2) $paginationHtml .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }

    for ($p = $startPage; $p <= $endPage; $p++) {
        $activeClass = ($p === $page) ? ' active' : '';
        $paginationHtml .= '<li class="page-item'.$activeClass.'"><a class="page-link" href="'.e(filter_query(['page' => $p])).'">'.$p.'</a></li>';
    }

    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) $paginationHtml .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        $paginationHtml .= '<li class="page-item"><a class="page-link" href="'.e(filter_query(['page' => $totalPages])).'">'.$totalPages.'</a></li>';
    }

    // Next
    if ($page < $totalPages) {
        $paginationHtml .= '<li class="page-item"><a class="page-link" href="'.e(filter_query(['page' => $page + 1])).'">Next &raquo;</a></li>';
    } else {
        $paginationHtml .= '<li class="page-item disabled"><span class="page-link">Next &raquo;</span></li>';
    }

    $paginationHtml .= '</ul>';
    $paginationHtml .= '</nav>';
}

$body = '
'.$flashHtml.'

<!-- Header & Quick Actions -->
<div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 mb-4">
  <div>
    <div class="tech-label mb-1">ASSET MANAGEMENT</div>
    <h1 class="h3 mb-1">Asset Registry</h1>
    <p class="text-secondary small mb-0">Katalog dan inventaris lengkap perangkat IT, komputer kantor cabang, dan status pemeliharaan.</p>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap justify-content-start justify-content-xl-end">
    <a class="btn btn-primary d-inline-flex align-items-center gap-1 fw-semibold px-3 shadow-sm" href="'.e(module_url('asset_add.php')).'">
      <i class="bi bi-plus-lg"></i> Tambah Aset
    </a>
    <button type="button" class="btn btn-warning text-dark d-inline-flex align-items-center gap-1 fw-semibold shadow-sm" onclick="openBulkEditModal()">
      <i class="bi bi-pencil-square"></i> Edit Massal <span class="badge bg-dark text-white rounded-pill ms-1"><span class="selectedCountNum">0</span></span>
    </button>
    <a class="btn btn-outline-primary d-inline-flex align-items-center gap-1 fw-semibold" href="'.e(module_url('asset_import.php')).'">
      <i class="bi bi-file-earmark-arrow-up"></i> Import Excel / CSV
    </a>
    <div class="btn-group">
      <button type="button" class="btn btn-light border dropdown-toggle d-inline-flex align-items-center gap-1" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-printer"></i> Cetak Kartu
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius: 12px; min-width: 260px;">
        <li>
          <a class="dropdown-item py-2 d-flex align-items-center gap-2" target="_blank" href="'.e(module_url('print_card.php', ['cabang' => $cabangId, 'tahun' => $year])).'">
            <i class="bi bi-printer text-primary fs-5"></i>
            <div>
              <div class="fw-semibold">Cetak Kartu Kontrol</div>
              <small class="text-muted">Lembar checklist perawatan rutin</small>
            </div>
          </a>
        </li>
        <li><hr class="dropdown-divider my-1"></li>
        <li>
          <a class="dropdown-item py-2 d-flex align-items-center gap-2" target="_blank" href="'.e(module_url('print_inventory_card.php', ['cabang' => $cabangId])).'">
            <i class="bi bi-credit-card-2-front text-info fs-5"></i>
            <div>
              <div class="fw-semibold">Cetak Kartu Inventaris</div>
              <small class="text-muted">Format CR80 standar ID Card fisik</small>
            </div>
          </a>
        </li>
      </ul>
    </div>
    <a class="btn btn-light border d-inline-flex align-items-center gap-1" href="'.e(module_url('export_csv.php')).'">
      <i class="bi bi-download"></i> Export CSV
    </a>
  </div>
</div>

<!-- Interactive Branch Switcher Navigation Bar -->
'.$branchPillsHtml.'

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <a href="'.e(filter_query(['status' => null, 'maint' => null, 'page' => 1])).'" class="text-decoration-none">
      <div class="card card-metric h-100" style="border-left-color: var(--blue-corporate);">
        <div class="metric-value">'.$statTotal.'</div>
        <div class="metric-label">Total Unit Aset</div>
        <div class="small text-muted mt-2" style="font-size: 0.72rem;">Seluruh unit terdaftar</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a href="'.e(filter_query(['maint' => 'done', 'page' => 1])).'" class="text-decoration-none">
      <div class="card card-metric h-100" style="border-left-color: #16803C;">
        <div class="metric-value text-success">'.$statDoneMaint.'</div>
        <div class="metric-label">Sudah Maintenance</div>
        <div class="small text-success mt-2" style="font-size: 0.72rem;">Bulan '.$currentMonthName.'</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a href="'.e(filter_query(['maint' => 'pending', 'page' => 1])).'" class="text-decoration-none">
      <div class="card card-metric h-100" style="border-left-color: #B54708;">
        <div class="metric-value text-warning">'.$statPendingMaint.'</div>
        <div class="metric-label">Belum Diperiksa</div>
        <div class="small text-muted mt-2" style="font-size: 0.72rem;">Menunggu inspeksi</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a href="'.e(filter_query(['maint' => 'repair', 'page' => 1])).'" class="text-decoration-none">
      <div class="card card-metric h-100" style="border-left-color: #B42318;">
        <div class="metric-value text-danger">'.$statRepair.'</div>
        <div class="metric-label">Temuan Masalah</div>
        <div class="small text-danger mt-2" style="font-size: 0.72rem;">Perlu tindak lanjut</div>
      </div>
    </a>
  </div>
</div>

'.$todoTabsHtml.'

<!-- Compact Filter Bar -->
<div class="card p-3 mb-4">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-lg-4 col-md-6">
      <label class="form-label text-secondary small fw-semibold mb-1">Cari Perangkat / User / IP</label>
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
        <input type="text" class="form-control form-control-sm border-start-0" name="q" value="'.e($search).'" placeholder="Ketik kode, merk, tipe, nama pengguna, IP...">
      </div>
    </div>
    <div class="col-lg-2 col-md-3 col-sm-6">
      <label class="form-label text-secondary small fw-semibold mb-1">Kantor Cabang</label>
      <select class="form-select form-select-sm" name="cabang">
        '.$optCab.'
      </select>
    </div>
    <div class="col-lg-2 col-md-3 col-sm-6">
      <label class="form-label text-secondary small fw-semibold mb-1">Divisi</label>
      <select class="form-select form-select-sm" name="divisi">
        '.$optDiv.'
      </select>
    </div>
    <div class="col-lg-2 col-md-3 col-sm-6">
      <label class="form-label text-secondary small fw-semibold mb-1">Status Unit</label>
      <select class="form-select form-select-sm" name="status">
        <option value="">Semua Status</option>
        <option value="Aktif"'.($statusAset === 'Aktif' ? ' selected' : '').'>Aktif</option>
        <option value="Backup"'.($statusAset === 'Backup' ? ' selected' : '').'>Backup</option>
        <option value="Perbaikan"'.($statusAset === 'Perbaikan' ? ' selected' : '').'>Perbaikan</option>
        <option value="Nonaktif"'.($statusAset === 'Nonaktif' ? ' selected' : '').'>Nonaktif</option>
      </select>
    </div>
    <div class="col-lg-2 col-md-3 col-sm-6">
      <label class="form-label text-secondary small fw-semibold mb-1">Status Maintenance</label>
      <select class="form-select form-select-sm" name="maint">
        <option value="all">Semua Status</option>
        <option value="done"'.($maintStatus === 'done' ? ' selected' : '').'>Selesai</option>
        <option value="pending"'.($maintStatus === 'pending' ? ' selected' : '').'>Belum Selesai</option>
        <option value="repair"'.($maintStatus === 'repair' ? ' selected' : '').'>Ada Temuan</option>
      </select>
    </div>
    <div class="col-12 d-flex justify-content-between align-items-center pt-2 border-top mt-2">
      <div class="text-secondary small">
        Menampilkan <strong>'.$totalFiltered.'</strong> unit dari total <strong>'.$statTotal.'</strong> unit komputer.
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-light border px-3" href="'.e(module_url('assets.php')).'"><i class="bi bi-x-circle me-1"></i> Reset</a>
        <button type="submit" class="btn btn-sm btn-primary px-3 fw-semibold"><i class="bi bi-filter me-1"></i> Terapkan</button>
      </div>
    </div>
  </form>
</div>

'.$statusBannerHtml.'

<!-- Table Surface Card -->
<div class="card overflow-hidden mb-4">
  <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h2 class="h6 mb-0 fw-semibold text-dark"><i class="bi bi-pc-display me-2 text-primary"></i>Daftar Perangkat IT & Komputer</h2>
      <div class="text-secondary small">Seluruh unit PC Desktop, Laptop, dan Printer terdata</div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="small text-muted">Halaman '.$page.' dari '.$totalPages.'</span>
    </div>
  </div>

  <!-- Selection Action Bar (Appears when items are selected) -->
  <div id="selectionActionBar" class="border-bottom px-4 py-2 d-none align-items-center justify-content-between flex-wrap gap-2" style="background-color: #EFF6FF !important;">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <span class="badge bg-primary fs-6 px-2 py-1"><i class="bi bi-check-square me-1"></i> <span id="selectedCountText">0</span> Dipilih</span>
      <span class="badge bg-primary-subtle text-primary border border-primary-subtle small fw-normal d-none d-md-inline-flex align-items-center">
        <i class="bi bi-pin-angle-fill me-1"></i> Tersimpan otomatis lintas pencarian &amp; filter
      </span>
      <button type="button" class="btn btn-sm btn-outline-primary fw-semibold py-1 px-2" data-bs-toggle="modal" data-bs-target="#selectedAssetsModal" onclick="renderSelectedModalList()">
        <i class="bi bi-list-check me-1"></i> Lihat Daftar (<span class="selectedCountNum">0</span>)
      </button>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <button type="button" class="btn btn-sm btn-warning text-dark fw-semibold shadow-sm" onclick="openBulkEditModal()">
        <i class="bi bi-pencil-square me-1"></i> Edit Massal (<span class="selectedCountNum">0</span>)
      </button>
      <button type="button" class="btn btn-sm btn-primary fw-semibold shadow-sm" onclick="batchPrintCards()">
        <i class="bi bi-printer me-1"></i> Cetak Kartu Kontrol (<span class="selectedCountNum">0</span>)
      </button>
      <button type="button" class="btn btn-sm btn-dark fw-semibold shadow-sm" onclick="batchPrintQR()">
        <i class="bi bi-qr-code me-1"></i> Cetak Label QR (<span class="selectedCountNum">0</span>)
      </button>
      <button type="button" class="btn btn-sm btn-info text-white fw-semibold shadow-sm" onclick="batchPrintInventoryCards()">
        <i class="bi bi-credit-card-2-front me-1"></i> Cetak Kartu Inventaris (<span class="selectedCountNum">0</span>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllAssets()">
        <i class="bi bi-x-circle me-1"></i> Batalkan
      </button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th style="width: 40px;" class="text-center">
            <input class="form-check-input" type="checkbox" id="checkAllAssets" title="Pilih Semua di Halaman Ini" style="cursor: pointer; width: 1.15rem; height: 1.15rem;">
          </th>
          <th style="width: 40px;" class="text-center">No</th>
          <th style="width: 150px;">Kode Inventaris</th>
          <th>Perangkat / Spesifikasi</th>
          <th>Pengguna / Divisi</th>
          <th>Lokasi & IP</th>
          <th style="width: 110px;" class="text-center">Kondisi</th>
          <th style="width: 160px;" class="text-center">Maintenance ('.$currentMonthName.')</th>
          <th style="width: 120px;" class="text-center">Label QR</th>
          <th style="width: 130px;" class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        '.$tableRows.'
      </tbody>
    </table>
  </div>
  <div class="p-3 bg-white border-top">
    '.$paginationHtml.'
  </div>
</div>

<!-- Floating Sticky Selection Bar for Mobile & Quick Action -->
<div id="floatingSelectionBar" class="position-fixed bottom-0 start-50 translate-middle-x mb-4 shadow-lg rounded-pill px-4 py-2 bg-dark text-white d-none align-items-center gap-3" style="z-index: 1050; border: 1px solid rgba(255,255,255,0.2); animation: fadeInUp 0.25s ease;">
  <div class="d-flex align-items-center gap-2">
    <span class="badge bg-primary rounded-pill px-2 py-1"><span class="selectedCountNum">0</span></span>
    <span class="small fw-semibold">Dipilih</span>
  </div>
  <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-2 py-1 small" data-bs-toggle="modal" data-bs-target="#selectedAssetsModal" onclick="renderSelectedModalList()" title="Lihat rincian aset terpilih">
    <i class="bi bi-list-check me-1"></i> Rincian
  </button>
  <div class="vr bg-secondary opacity-50"></div>
  <div class="d-flex gap-2">
    <button type="button" class="btn btn-sm btn-warning rounded-pill px-3 fw-semibold text-dark" onclick="openBulkEditModal()">
      <i class="bi bi-pencil-square me-1"></i> Edit Massal
    </button>
    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold" onclick="batchPrintCards()">
      <i class="bi bi-printer me-1"></i> Cetak Kartu
    </button>
    <button type="button" class="btn btn-sm btn-light rounded-pill px-3 fw-semibold" onclick="batchPrintQR()">
      <i class="bi bi-qr-code me-1"></i> Cetak Label QR
    </button>
    <button type="button" class="btn btn-sm btn-link text-white-50 text-decoration-none p-0 ms-1" onclick="deselectAllAssets()" title="Batalkan Pilihan">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>
</div>

<!-- Modal Daftar Aset Terpilih -->
<div class="modal fade" id="selectedAssetsModal" tabindex="-1" aria-labelledby="selectedAssetsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content shadow-lg border-0" style="border-radius: 16px;">
      <div class="modal-header border-bottom py-3 px-4">
        <div>
          <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="selectedAssetsModalLabel">
            <i class="bi bi-check2-square text-primary"></i> Daftar Komputer Terpilih
            <span class="badge bg-primary rounded-pill fs-6 px-2 py-1"><span class="selectedCountNum">0</span> Unit</span>
          </h5>
          <div class="text-secondary small mt-1">Daftar unit yang Anda pilih tetap tersimpan meskipun Anda mencari atau mengganti filter.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light sticky-top">
              <tr>
                <th style="width: 45px;" class="text-center">No</th>
                <th>Kode Inventaris</th>
                <th>Perangkat / Model</th>
                <th>Pengguna / Divisi</th>
                <th>Cabang</th>
                <th style="width: 60px;" class="text-center">Hapus</th>
              </tr>
            </thead>
            <tbody id="selectedAssetsTableBody">
              <!-- Rendered via JS -->
            </tbody>
          </table>
        </div>
        <div id="selectedAssetsEmptyState" class="p-4 text-center text-muted d-none">
          <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
          Tidak ada aset yang sedang dipilih.
        </div>
      </div>
      <div class="modal-footer border-top bg-light py-2 px-4 d-flex justify-content-between align-items-center">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deselectAllAssets()">
          <i class="bi bi-trash3 me-1"></i> Batalkan Semua Pilihan
        </button>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
          <button type="button" class="btn btn-sm btn-warning fw-semibold text-dark" onclick="openBulkEditModal()">
            <i class="bi bi-pencil-square me-1"></i> Edit Massal (<span class="selectedCountNum">0</span>)
          </button>
          <button type="button" class="btn btn-sm btn-primary fw-semibold" onclick="batchPrintCards()">
            <i class="bi bi-printer me-1"></i> Cetak Kartu (<span class="selectedCountNum">0</span>)
          </button>
          <button type="button" class="btn btn-sm btn-dark fw-semibold" onclick="batchPrintQR()">
            <i class="bi bi-qr-code me-1"></i> Cetak QR (<span class="selectedCountNum">0</span>)
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Quick QR Preview (Sesuai Desain Pratinjau Stiker QR) -->
<div class="modal fade" id="quickQrModal" tabindex="-1" aria-labelledby="quickQrModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header text-white" style="background: linear-gradient(135deg, #1E3A60 0%, #2E77AD 100%);">
        <div>
          <div class="small text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.85) !important;">PRATINJAU STIKER QR</div>
          <h5 class="modal-title fw-bold text-white mb-0" id="modalAssetKode" style="color: #ffffff !important;">INV-IT-001</h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <!-- QR Code Canvas Container -->
        <div class="d-inline-block p-3 bg-white border border-2 border-primary border-opacity-25 rounded-3 shadow-sm mb-3 position-relative">
          <div id="modalQrBox" style="width: 200px; height: 200px; margin: 0 auto; display: flex; align-items: center; justify-content: center;"></div>
        </div>

        <!-- Detail Perangkat -->
        <div class="bg-light p-3 rounded-3 text-start mb-3 border">
          <div class="row g-2 small">
            <div class="col-4 text-muted">Perangkat:</div>
            <div class="col-8 fw-bold text-dark text-truncate" id="modalAssetDevice">-</div>
            <div class="col-4 text-muted">Pengguna:</div>
            <div class="col-8 fw-semibold text-primary text-truncate" id="modalAssetUser">-</div>
            <div class="col-4 text-muted">Kantor Cabang:</div>
            <div class="col-8 text-dark" id="modalAssetCabang">-</div>
          </div>
        </div>

        <!-- Input Link Scan & Copy -->
        <div class="input-group input-group-sm mb-3">
          <input type="text" id="modalScanUrlInput" class="form-control font-monospace" readonly style="background: #F1F5F9; font-size: 0.75rem;">
          <button class="btn btn-outline-secondary" type="button" onclick="copyModalScanUrl(this)" title="Salin Link">
            <i class="bi bi-clipboard"></i> Salin
          </button>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex flex-wrap gap-2 justify-content-center pt-2">
          <a id="modalBtnPrint" href="#" target="_blank" class="btn btn-primary btn-sm px-3 rounded-pill fw-semibold shadow-xs">
            <i class="bi bi-printer-fill me-1"></i> Cetak Stiker
          </a>
          <button type="button" class="btn btn-outline-primary btn-sm px-3 rounded-pill fw-semibold" onclick="downloadModalQrPng()">
            <i class="bi bi-download me-1"></i> Unduh Gambar QR (PNG)
          </button>
          <a id="modalBtnOpenScan" href="#" target="_blank" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
            <i class="bi bi-box-arrow-up-right me-1"></i> Buka Halaman Scan
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Floating Copy Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
  <div id="qrAdminToast" class="toast align-items-center text-white bg-dark border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill text-success fs-5"></i>
        <span>Link scan QR berhasil disalin!</span>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>
';

$extraHead = '
<style>
.table-row-selected {
  background-color: #eff6ff !important;
}
.table-row-selected > td {
  background-color: #eff6ff !important;
}
#modalQrBox {
  width: 200px;
  height: 200px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: center;
}
#modalQrBox canvas {
  display: block !important;
  max-width: 100% !important;
  height: auto !important;
  margin: 0 auto !important;
  border-radius: 4px;
}
#modalQrBox img {
  display: none !important;
}
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translate(-50%, 20px);
  }
  to {
    opacity: 1;
    transform: translate(-50%, 0);
  }
}
</style>
';

$logoDataUri = app_logo_url();

$extraScript = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
var appLogoUri = '.json_encode($logoDataUri).';
var currentModalData = null;

function openQrModal(data) {
  currentModalData = data;
  document.getElementById("modalAssetKode").textContent = data.kode || "-";
  document.getElementById("modalAssetDevice").textContent = data.device || "-";
  document.getElementById("modalAssetUser").textContent = data.user || "-";
  document.getElementById("modalAssetCabang").textContent = data.cabang || "-";
  document.getElementById("modalScanUrlInput").value = data.url || "";

  document.getElementById("modalBtnPrint").href = "'.e(module_url('print_qr.php')).'?asset_id=" + data.id;
  document.getElementById("modalBtnOpenScan").href = data.url;

  var qrBox = document.getElementById("modalQrBox");
  qrBox.innerHTML = "";

  if (typeof QRCode !== "undefined") {
    new QRCode(qrBox, {
      text: data.url,
      width: 200,
      height: 200,
      correctLevel: QRCode.CorrectLevel.H
    });

    // Pasang logo Bank Mitra di tengah QR
    attachLogoToQr(qrBox, appLogoUri);
  }

  var modalEl = document.getElementById("quickQrModal");
  if (modalEl && typeof bootstrap !== "undefined") {
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  }
}

function attachLogoToQr(containerEl, logoUri) {
  if (!logoUri) return;
  var tries = 0;
  var timer = setInterval(function(){
    tries++;
    var canvas = containerEl.querySelector("canvas");
    if (canvas && canvas.width > 0) {
      clearInterval(timer);
      drawLogoOnCanvas(canvas, logoUri, containerEl);
    } else if (tries > 25) {
      clearInterval(timer);
    }
  }, 40);
}

function drawLogoOnCanvas(canvas, logoUri, containerEl) {
  var ctx = canvas.getContext("2d");
  var img = new Image();
  img.onload = function() {
    var size = canvas.width;
    var logoSize = Math.round(size * 0.22);
    var center = Math.round((size - logoSize) / 2);
    var pad = Math.round(size * 0.025);

    var bgX = center - pad;
    var bgY = center - pad;
    var bgW = logoSize + (pad * 2);
    var bgH = logoSize + (pad * 2);
    var rad = Math.round(size * 0.035);

    // Rounded background
    ctx.save();
    ctx.fillStyle = "#FFFFFF";
    ctx.beginPath();
    ctx.moveTo(bgX + rad, bgY);
    ctx.lineTo(bgX + bgW - rad, bgY);
    ctx.quadraticCurveTo(bgX + bgW, bgY, bgX + bgW, bgY + rad);
    ctx.lineTo(bgX + bgW, bgY + bgH - rad);
    ctx.quadraticCurveTo(bgX + bgW, bgY + bgH, bgX + bgW - rad, bgY + bgH);
    ctx.lineTo(bgX + rad, bgY + bgH);
    ctx.quadraticCurveTo(bgX, bgY + bgH, bgX, bgY + bgH - rad);
    ctx.lineTo(bgX, bgY + rad);
    ctx.quadraticCurveTo(bgX, bgY, bgX + rad, bgY);
    ctx.closePath();
    ctx.fill();

    // Subtle cyan border
    ctx.strokeStyle = "#30B0E0";
    ctx.lineWidth = Math.max(1, Math.round(size * 0.012));
    ctx.stroke();
    ctx.restore();

    // Draw logo
    var aspect = (img.naturalWidth && img.naturalHeight) ? (img.naturalWidth / img.naturalHeight) : 1;
    var dw = logoSize;
    var dh = logoSize;
    if (aspect > 1) {
      dh = logoSize / aspect;
    } else {
      dw = logoSize * aspect;
    }
    var dx = center + (logoSize - dw) / 2;
    var dy = center + (logoSize - dh) / 2;

    ctx.drawImage(img, dx, dy, dw, dh);

    var qImg = containerEl.querySelector("img");
    if (qImg) {
      try { qImg.src = canvas.toDataURL("image/png"); } catch(e) {}
    }
  };
  img.src = logoUri;
}

function downloadModalQrPng() {
  var canvas = document.querySelector("#modalQrBox canvas");
  if (!canvas) {
    alert("Gambar QR belum selesai dirender.");
    return;
  }
  var kode = (currentModalData && currentModalData.kode) ? currentModalData.kode : "aset";
  var a = document.createElement("a");
  a.download = "QR-" + kode + ".png";
  a.href = canvas.toDataURL("image/png");
  a.click();
}

function copyModalScanUrl(btn) {
  var input = document.getElementById("modalScanUrlInput");
  if (!input || !input.value) return;
  var url = input.value;
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(url).then(function() {
      showToastNotification("Link scan QR berhasil disalin!");
    }).catch(function() {
      fallbackCopyText(url);
    });
  } else {
    fallbackCopyText(url);
  }
}

function fallbackCopyText(text) {
  var ta = document.createElement("textarea");
  ta.value = text;
  document.body.appendChild(ta);
  ta.select();
  document.execCommand("copy");
  document.body.removeChild(ta);
  showToastNotification("Link scan QR berhasil disalin!");
}

function showToastNotification(msg) {
  var toastEl = document.getElementById("qrAdminToast");
  if (toastEl) {
    var span = toastEl.querySelector(".toast-body span");
    if (span) span.textContent = msg;
    if (typeof bootstrap !== "undefined" && bootstrap.Toast) {
      var bsToast = new bootstrap.Toast(toastEl, { delay: 2200 });
      bsToast.show();
    } else {
      toastEl.classList.add("show");
      setTimeout(function(){ toastEl.classList.remove("show"); }, 2200);
    }
  } else {
    alert(msg);
  }
}
</script>
<script>
document.addEventListener("DOMContentLoaded", function() {
  const STORAGE_KEY = "asset_registry_selected_assets_v1";
  const checkAll = document.getElementById("checkAllAssets");
  const rowChecks = document.querySelectorAll(".asset-checkbox");
  const topBar = document.getElementById("selectionActionBar");
  const floatBar = document.getElementById("floatingSelectionBar");
  const countLabels = document.querySelectorAll(".selectedCountNum");
  const countText = document.getElementById("selectedCountText");

  function getStoredSelection() {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : {};
    } catch (e) {
      return {};
    }
  }

  function saveStoredSelection(data) {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    } catch (e) {
      console.error("Gagal menyimpan pilihan aset ke sessionStorage", e);
    }
  }

  function getSelectedIds() {
    const map = getStoredSelection();
    let ids = Object.keys(map);
    if (!ids.length) {
      document.querySelectorAll(".asset-checkbox:checked").forEach(function(cb) {
        if (cb.value && !ids.includes(cb.value)) ids.push(cb.value);
      });
    }
    return ids;
  }

  function escapeHtml(str) {
    if (!str) return "";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/\'/g, "&#039;");
  }

  function updateSelectionUI() {
    const selectedMap = getStoredSelection();
    const ids = Object.keys(selectedMap);
    const count = ids.length;

    countLabels.forEach(function(el) { el.textContent = count; });
    if (countText) countText.textContent = count;

    if (count > 0) {
      if (topBar) {
        topBar.classList.remove("d-none");
        topBar.classList.add("d-flex");
      }
      if (floatBar) {
        floatBar.classList.remove("d-none");
        floatBar.classList.add("d-flex");
      }
    } else {
      if (topBar) {
        topBar.classList.add("d-none");
        topBar.classList.remove("d-flex");
      }
      if (floatBar) {
        floatBar.classList.add("d-none");
        floatBar.classList.remove("d-flex");
      }
    }

    // Perbarui status checkbox di tabel halaman saat ini
    let checkedOnThisPage = 0;
    rowChecks.forEach(function(cb) {
      const isSelected = Boolean(selectedMap[cb.value]);
      cb.checked = isSelected;
      if (isSelected) {
        checkedOnThisPage++;
      }
      const row = cb.closest("tr");
      if (row) {
        if (isSelected) {
          row.classList.add("table-row-selected");
        } else {
          row.classList.remove("table-row-selected");
        }
      }
    });

    if (checkAll) {
      if (rowChecks.length === 0) {
        checkAll.checked = false;
        checkAll.indeterminate = false;
      } else if (checkedOnThisPage === rowChecks.length) {
        checkAll.checked = true;
        checkAll.indeterminate = false;
      } else if (checkedOnThisPage > 0) {
        checkAll.checked = false;
        checkAll.indeterminate = true;
      } else {
        checkAll.checked = false;
        checkAll.indeterminate = false;
      }
    }
  }

  // Inisialisasi: sinkronkan data unit di halaman ini ke storage jika belum lengkap
  (function initSync() {
    const selectedMap = getStoredSelection();
    let updated = false;
    rowChecks.forEach(function(cb) {
      const id = cb.value;
      if (selectedMap[id]) {
        cb.checked = true;
        if (!selectedMap[id].kode && cb.dataset.kode) {
          selectedMap[id].kode = cb.dataset.kode;
          selectedMap[id].device = cb.dataset.device || "";
          selectedMap[id].user = cb.dataset.user || "";
          selectedMap[id].cabang = cb.dataset.cabang || "";
          updated = true;
        }
      }
    });
    if (updated) {
      saveStoredSelection(selectedMap);
    }
    updateSelectionUI();
  })();

  // Listener checkbox per baris
  rowChecks.forEach(function(cb) {
    cb.addEventListener("change", function() {
      const selectedMap = getStoredSelection();
      const id = this.value;
      if (this.checked) {
        selectedMap[id] = {
          id: id,
          kode: this.dataset.kode || ("#" + id),
          device: this.dataset.device || "",
          user: this.dataset.user || "",
          cabang: this.dataset.cabang || ""
        };
      } else {
        delete selectedMap[id];
      }
      saveStoredSelection(selectedMap);
      updateSelectionUI();
    });
  });

  // Listener Check All di halaman aktif
  if (checkAll) {
    checkAll.addEventListener("change", function() {
      const isChecked = this.checked;
      const selectedMap = getStoredSelection();
      rowChecks.forEach(function(cb) {
        cb.checked = isChecked;
        const id = cb.value;
        if (isChecked) {
          selectedMap[id] = {
            id: id,
            kode: cb.dataset.kode || ("#" + id),
            device: cb.dataset.device || "",
            user: cb.dataset.user || "",
            cabang: cb.dataset.cabang || ""
          };
        } else {
          delete selectedMap[id];
        }
      });
      saveStoredSelection(selectedMap);
      updateSelectionUI();
    });
  }

  // Batalkan / Kosongkan semua pilihan
  window.deselectAllAssets = function() {
    sessionStorage.removeItem(STORAGE_KEY);
    if (checkAll) {
      checkAll.checked = false;
      checkAll.indeterminate = false;
    }
    rowChecks.forEach(function(cb) {
      cb.checked = false;
    });
    updateSelectionUI();
    if (typeof window.renderSelectedModalList === "function") {
      window.renderSelectedModalList();
    }
  };

  // Hapus satu item dari pilihan (misalnya dari modal daftar)
  window.removeSelectedItem = function(id) {
    const selectedMap = getStoredSelection();
    delete selectedMap[id];
    saveStoredSelection(selectedMap);

    const targetCb = document.querySelector(\'.asset-checkbox[value="\' + id + \'"]\');
    if (targetCb) {
      targetCb.checked = false;
    }

    updateSelectionUI();
    window.renderSelectedModalList();
  };

  // Render modal rincian aset terpilih
  window.renderSelectedModalList = function() {
    const tbody = document.getElementById("selectedAssetsTableBody");
    const emptyState = document.getElementById("selectedAssetsEmptyState");
    if (!tbody) return;

    const selectedMap = getStoredSelection();
    const items = Object.values(selectedMap);

    if (items.length === 0) {
      tbody.innerHTML = "";
      if (emptyState) emptyState.classList.remove("d-none");
      return;
    }

    if (emptyState) emptyState.classList.add("d-none");

    let html = "";
    items.forEach(function(item, idx) {
      html += \'<tr>\' +
        \'<td class="text-center text-muted small">\' + (idx + 1) + \'</td>\' +
        \'<td class="fw-bold font-monospace">\' + escapeHtml(item.kode || ("#" + item.id)) + \'</td>\' +
        \'<td><div class="fw-semibold small">\' + escapeHtml(item.device || "-") + \'</div></td>\' +
        \'<td class="small">\' + escapeHtml(item.user || "-") + \'</td>\' +
        \'<td class="small text-secondary">\' + escapeHtml(item.cabang || "-") + \'</td>\' +
        \'<td class="text-center">\' +
          \'<button type="button" class="btn btn-sm btn-outline-danger border-0 p-1 btn-remove-selected" data-remove-id="\' + escapeHtml(item.id) + \'" title="Hapus dari daftar pilihan">\' +
            \'<i class="bi bi-trash"></i>\' +
          \'</button>\' +
        \'</td>\' +
      \'</tr>\';
    });
    tbody.innerHTML = html;
  };

  // Event delegation untuk tombol hapus di dalam modal
  const modalTbody = document.getElementById("selectedAssetsTableBody");
  if (modalTbody) {
    modalTbody.addEventListener("click", function(e) {
      const btn = e.target.closest(".btn-remove-selected");
      if (btn) {
        const id = btn.getAttribute("data-remove-id");
        if (id) {
          window.removeSelectedItem(id);
        }
      }
    });
  }

  window.batchPrintCards = function() {
    const ids = getSelectedIds();
    if (!ids.length) {
      alert("Silakan pilih minimal 1 komputer untuk dicetak.");
      return;
    }
    const url = "print_card.php?ids=" + encodeURIComponent(ids.join(",")) + "&layout=grid6";
    window.open(url, "_blank");
  };

  window.batchPrintQR = function() {
    const ids = getSelectedIds();
    if (!ids.length) {
      alert("Silakan pilih minimal 1 komputer untuk dicetak stiker QR-nya.");
      return;
    }
    const url = "print_qr.php?ids=" + encodeURIComponent(ids.join(","));
    window.open(url, "_blank");
  };

  window.batchPrintInventoryCards = function() {
    const ids = getSelectedIds();
    if (!ids.length) {
      alert("Silakan pilih minimal 1 komputer untuk dicetak kartu inventarisnya.");
      return;
    }
    const url = "print_inventory_card.php?ids=" + encodeURIComponent(ids.join(",")) + "&layout=10";
    window.open(url, "_blank");
  };

  // --- Fitur Edit Massal (Multi-Row Spreadsheet Editor) ---
  window.openBulkEditModal = function() {
    let ids = getSelectedIds();
    if (!ids.length) {
      const visibleCheckboxes = document.querySelectorAll(".asset-checkbox");
      if (visibleCheckboxes.length > 0) {
        const confirmAll = confirm("Belum ada komputer yang dicentang.\\n\\nApakah Anda ingin mengedit massal seluruh komputer di halaman ini (" + visibleCheckboxes.length + " unit)?");
        if (confirmAll) {
          visibleCheckboxes.forEach(function(cb) {
            cb.checked = true;
            ids.push(cb.value);
          });
        } else {
          return;
        }
      } else {
        alert("Silakan checklist minimal 1 komputer untuk diedit secara massal.");
        return;
      }
    }
    const targetUrl = '.json_encode(module_url('asset_bulk_edit.php')).';
    window.location.href = targetUrl + "?ids=" + encodeURIComponent(ids.join(","));
  };
});
</script>
';

render_page('Asset Registry', $body, $extraHead, $extraScript);
