<?php
require __DIR__ . '/bootstrap.php';
require_it();

// Parameter Pencarian & Filter
$search = trim((string)($_GET['q'] ?? ''));
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));
$divisiId = max(0, (int)($_GET['divisi'] ?? 0));
$statusAset = trim((string)($_GET['status'] ?? ''));
$maintStatus = trim((string)($_GET['maint'] ?? '')); // 'all', 'done', 'pending', 'repair'
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(5, min(100, (int)($_GET['per_page'] ?? 15)));

$month = max(1, min(12, (int)($_GET['bulan'] ?? date('n'))));
$currentYear = (int)date('Y');
$yearParam = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
$year = ($yearParam >= 2020 && $yearParam <= 2035) ? $yearParam : $currentYear;
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

// Cek temuan kerusakan & keluhan karyawan aktif yang belum selesai agar langsung masuk ke filter Temuan Kendala IT
try {
    $cSql = "
        SELECT mf.asset_id, mf.finding, mf.repair_status, mf.created_at
        FROM maintenance_findings mf
        WHERE COALESCE(mf.repair_status, 'Open') NOT IN ('Resolved', 'Closed', 'Selesai', 'Done')
          AND NOT EXISTS (
              SELECT 1 FROM maintenance_scan ms2 
              WHERE ms2.asset_id = mf.asset_id 
                AND ms2.created_at >= mf.created_at
                AND LOWER(ms2.status) IN ('selesai', 'ok', 'resolved')
          )
    ";
    $cSt = db()->query($cSql);
    foreach ($cSt->fetchAll() as $cf) {
        $aid = (int)($cf['asset_id'] ?? 0);
        if ($aid > 0) {
            $maintStatusMap[$aid] = [
                'is_done' => true,
                'status' => 'Perlu Perbaikan',
                'date' => substr((string)($cf['created_at'] ?? date('Y-m-d')), 0, 10),
                'tech' => 'Temuan Kerusakan',
                'is_active_issue' => true,
                'finding_text' => $cf['finding']
            ];
        }
    }

    $ecSt = db()->query("
        SELECT ec.asset_id, ec.complaint, ec.kategori, ec.reporter_name, ec.created_at
        FROM employee_complaints ec
        WHERE LOWER(COALESCE(ec.status, 'Menunggu Teknisi')) NOT IN ('selesai', 'resolved', 'closed', 'ok')
    ");
    foreach ($ecSt->fetchAll() as $ec) {
        $aid = (int)($ec['asset_id'] ?? 0);
        if ($aid > 0) {
            $maintStatusMap[$aid] = [
                'is_done' => true,
                'status' => 'Perlu Perbaikan',
                'date' => substr((string)($ec['created_at'] ?? date('Y-m-d')), 0, 10),
                'tech' => $ec['reporter_name'] ?: 'Pengguna Komputer',
                'is_active_issue' => true,
                'is_complaint' => true,
                'finding_text' => '[' . $ec['kategori'] . '] ' . $ec['complaint']
            ];
        }
    }
} catch (Throwable $e) {}

// 4. Hitung Statistik Sesuai Filter Lokasi (Cabang & Divisi)
$statTotal = 0;
$statAktif = 0;
$statDoneMaint = 0;
$statPendingMaint = 0;
$statRepair = 0;
$statTidakTerpakai = 0;

foreach ($rawAssets as $a) {
    if ($cabangId > 0 && (int)($a['id_cabang'] ?? 0) !== $cabangId) continue;
    if ($divisiId > 0 && (int)($a['id_divisi'] ?? 0) !== $divisiId) continue;

    $statTotal++;
    $aid = (int)($a['id'] ?? 0);
    $stAset = strtolower(trim((string)($a['status'] ?? 'Aktif')));
    $isAsetAktif = ($stAset === 'aktif' || $stAset === '');
    if ($isAsetAktif) {
        $statAktif++;
    } elseif (in_array($stAset, ['tidak terpakai', 'nonaktif', 'afkir', 'disposal', 'pensiun', 'rusak total', 'diganti baru'], true)) {
        $statTidakTerpakai++;
    }

    $mInfo = $maintStatusMap[$aid] ?? null;
    if ($mInfo && $mInfo['is_done']) {
        $statDoneMaint++;
        if (in_array($mInfo['status'], ['Temuan', 'Perlu Perbaikan', 'Proses'], true)) {
            $statRepair++;
        }
    } elseif ($isAsetAktif) {
        // HANYA unit aktif yang belum diperiksa yang dihitung ke target/tunggakan maintenance!
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
        if ($maintStatus === 'pending') {
            $stAsetRaw = strtolower(trim((string)($a['status'] ?? 'Aktif')));
            $isAsetAktif = ($stAsetRaw === 'aktif' || $stAsetRaw === '');
            // Aset tidak terpakai/nonaktif otomatis dikeluarkan dari to-do list belum diperiksa
            if (!$isAsetAktif || $isDone) return false;
        }
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
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
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
$branchPillsHtml = '<div class="flex items-center gap-2 overflow-x-auto pb-1 mb-4 custom-scrollbar">';
$allActive = ($cabangId === 0);
$branchPillsHtml .= '<a class="flex items-center gap-1.5 h-[34px] px-3.5 rounded-[8px] text-[13px] font-semibold text-decoration-none shrink-0 transition-colors '.($allActive ? 'bg-[#0E5C58] text-white shadow-sm' : 'bg-[#FFFFFF] border border-[#D1D5D8] text-[#53595F] hover:bg-[#F4F5F7] hover:text-[#1B2A3A]').'" href="'.e(filter_query(['cabang' => 0, 'page' => 1])).'">
  <i class="bi bi-buildings"></i> Semua Cabang
  <span class="text-[11px] font-bold px-1.5 py-0.2 rounded-full '.($allActive ? 'bg-white/20 text-white' : 'bg-[#F4F5F7] text-[#53595F]').'">'.$totalAllAssets.'</span>
</a>';
foreach ($cabangs as $c) {
    $cid = (int)($c['id'] ?? 0);
    $cnama = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cid);
    $cCount = $branchAssetCounts[$cid] ?? 0;
    $isActive = ($cid === $cabangId);
    $branchPillsHtml .= '<a class="flex items-center gap-1.5 h-[34px] px-3.5 rounded-[8px] text-[13px] font-semibold text-decoration-none shrink-0 transition-colors '.($isActive ? 'bg-[#0E5C58] text-white shadow-sm' : 'bg-[#FFFFFF] border border-[#D1D5D8] text-[#53595F] hover:bg-[#F4F5F7] hover:text-[#1B2A3A]').'" href="'.e(filter_query(['cabang' => $cid, 'page' => 1])).'">
      <i class="bi bi-geo-alt'.($isActive ? '-fill' : '').'"></i> '.e($cnama).'
      <span class="text-[11px] font-bold px-1.5 py-0.2 rounded-full '.($isActive ? 'bg-white/20 text-white' : 'bg-[#F4F5F7] text-[#53595F]').'">'.$cCount.'</span>
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

// Render Opsi Dropdown Periode Bulan & Tahun
$optMonth = '';
for ($m = 1; $m <= 12; $m++) {
    $optMonth .= '<option value="'.$m.'"'.($m === $month ? ' selected' : '').'>'.$monthNames[$m].'</option>';
}

$optYear = '';
$startYear = max(2023, $currentYear - 2);
$endYear = $currentYear + 2;
for ($y = $startYear; $y <= $endYear; $y++) {
    $optYear .= '<option value="'.$y.'"'.($y === $year ? ' selected' : '').'>'.$y.'</option>';
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

// Kombai Metric Strip (88px)
$metricStripHtml = '
<div class="bg-[#FFFFFF] border border-[#D1D5D8] rounded-[10px] h-[88px] flex overflow-hidden shrink-0 mb-4">
  <a href="'.e(filter_query(['status' => null, 'maint' => null, 'page' => 1])).'" class="flex-1 flex flex-col justify-center px-6 border-r border-[#D1D5D8] relative cursor-pointer text-decoration-none transition-colors '.($maintStatus === '' || $maintStatus === 'all' ? 'bg-[#D9F1EF]' : 'hover:bg-[#F4F5F7]').'">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0E5C58]"></div>
    <div class="text-[30px] font-bold text-[#1B2A3A] tabular-nums leading-none">'.$statTotal.'</div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5">Total Unit Komputer</div>
  </a>
  <a href="'.e(filter_query(['maint' => 'done', 'page' => 1])).'" class="flex-1 flex flex-col justify-center px-6 border-r border-[#D1D5D8] relative cursor-pointer text-decoration-none transition-colors '.($maintStatus === 'done' ? 'bg-[#D9F1EF]' : 'hover:bg-[#F4F5F7]').'">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#16A34A]"></div>
    <div class="text-[30px] font-bold text-[#16A34A] tabular-nums leading-none">'.$statDoneMaint.'</div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5">Sudah Dipelihara ('.$currentMonthName.')</div>
  </a>
  <a href="'.e(filter_query(['maint' => 'pending', 'page' => 1])).'" class="flex-1 flex flex-col justify-center px-6 border-r border-[#D1D5D8] relative cursor-pointer text-decoration-none transition-colors '.($maintStatus === 'pending' ? 'bg-[#FEF3C7]' : 'hover:bg-[#F4F5F7]').'">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#D97706]"></div>
    <div class="text-[30px] font-bold text-[#D97706] tabular-nums leading-none">'.$statPendingMaint.'</div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5">Belum Diperiksa (To-Do)</div>
  </a>
  <a href="'.e(filter_query(['maint' => 'repair', 'page' => 1])).'" class="flex-1 flex flex-col justify-center px-6 relative cursor-pointer text-decoration-none transition-colors '.($maintStatus === 'repair' ? 'bg-[#FEE2E2]' : 'hover:bg-[#F4F5F7]').'">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#DC2626]"></div>
    <div class="text-[30px] font-bold text-[#DC2626] tabular-nums leading-none">'.$statRepair.'</div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5">Temuan Kendala IT</div>
  </a>
</div>';

// Tab Filter Cepat (To-Do List Teknisi) HTML
$todoTabsHtml = '
<div class="flex items-center justify-between border-b border-[#D1D5D8] h-[44px] shrink-0 mb-4 px-1 flex-wrap gap-2">
  <div class="flex items-center gap-6 h-full overflow-x-auto custom-scrollbar">
    <a href="'.e(filter_query(['maint' => null, 'page' => 1])).'" 
       class="flex items-center gap-2 h-full border-b-2 text-[14px] text-decoration-none transition-colors '.($maintStatus === '' || $maintStatus === 'all' ? 'border-[#0E5C58] text-[#0E5C58] font-bold' : 'border-transparent text-[#53595F] hover:text-[#1B2A3A] font-semibold').'">
      <span>Semua Unit</span>
      <span class="text-[11px] font-bold px-1.5 py-0.5 rounded-full '.($maintStatus === '' || $maintStatus === 'all' ? 'bg-[#D9F1EF] text-[#0E5C58]' : 'bg-[#F4F5F7] text-[#53595F]').'">'.$statTotal.'</span>
    </a>
    <a href="'.e(filter_query(['maint' => 'pending', 'page' => 1])).'" 
       class="flex items-center gap-2 h-full border-b-2 text-[14px] text-decoration-none transition-colors '.($maintStatus === 'pending' ? 'border-[#D97706] text-[#D97706] font-bold' : 'border-transparent text-[#53595F] hover:text-[#1B2A3A] font-semibold').'">
      <span>Belum Diperiksa</span>
      <span class="text-[11px] font-bold px-1.5 py-0.5 rounded-full '.($maintStatus === 'pending' ? 'bg-[#FEF3C7] text-[#92400E]' : 'bg-[#F4F5F7] text-[#53595F]').'">'.$statPendingMaint.'</span>
    </a>
    <a href="'.e(filter_query(['maint' => 'repair', 'page' => 1])).'" 
       class="flex items-center gap-2 h-full border-b-2 text-[14px] text-decoration-none transition-colors '.($maintStatus === 'repair' ? 'border-[#DC2626] text-[#DC2626] font-bold' : 'border-transparent text-[#53595F] hover:text-[#1B2A3A] font-semibold').'">
      <span>Temuan Kendala</span>
      <span class="text-[11px] font-bold px-1.5 py-0.5 rounded-full '.($maintStatus === 'repair' ? 'bg-[#FEE2E2] text-[#991B1B]' : 'bg-[#F4F5F7] text-[#53595F]').'">'.$statRepair.'</span>
    </a>
    <a href="'.e(filter_query(['maint' => 'done', 'page' => 1])).'" 
       class="flex items-center gap-2 h-full border-b-2 text-[14px] text-decoration-none transition-colors '.($maintStatus === 'done' ? 'border-[#16A34A] text-[#16A34A] font-bold' : 'border-transparent text-[#53595F] hover:text-[#1B2A3A] font-semibold').'">
      <span>Sudah Maintenance</span>
      <span class="text-[11px] font-bold px-1.5 py-0.5 rounded-full '.($maintStatus === 'done' ? 'bg-[#D9F1EF] text-[#0E5C58]' : 'bg-[#F4F5F7] text-[#53595F]').'">'.$statDoneMaint.'</span>
    </a>
    <a href="'.e(filter_query(['status' => 'Tidak Terpakai', 'maint' => null, 'page' => 1])).'" 
       class="flex items-center gap-2 h-full border-b-2 text-[14px] text-decoration-none transition-colors '.($statusAset === 'Tidak Terpakai' ? 'border-[#53595F] text-[#1B2A3A] font-bold' : 'border-transparent text-[#53595F] hover:text-[#1B2A3A] font-semibold').'">
      <span>Tidak Terpakai (Histori)</span>
      <span class="text-[11px] font-bold px-1.5 py-0.5 rounded-full bg-[#F4F5F7] text-[#53595F]">'.$statTidakTerpakai.'</span>
    </a>
  </div>
  <div class="flex items-center gap-2 pb-1">
    <a class="flex items-center gap-1.5 h-[30px] px-2.5 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#53595F] hover:text-[#1B2A3A] font-semibold text-[12px] rounded-[6px] text-decoration-none transition-colors" target="_blank" href="'.e(module_url('print_qr.php', ['cabang' => $cabangId])).'">
      <i class="bi bi-qr-code text-[#0E5C58]"></i> Cetak QR
    </a>
    <a class="flex items-center gap-1.5 h-[30px] px-2.5 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#53595F] hover:text-[#1B2A3A] font-semibold text-[12px] rounded-[6px] text-decoration-none transition-colors" target="_blank" href="'.e(module_url('print_report.php', ['bulan' => $month, 'tahun' => $year, 'cabang' => $cabangId])).'">
      <i class="bi bi-printer text-[#0E5C58]"></i> Cetak Laporan
    </a>
  </div>
</div>';

// Render Baris Tabel
$tableRows = '';
$mobileCards = '';
$startNum = $offset;
foreach ($pageAssets as $a) {
    $startNum++;
    $aid = (int)($a['id'] ?? 0);
    $editUrl = module_url('asset_edit.php', ['id' => $aid]);
    $kode = !empty($a['kode_inventaris']) ? $a['kode_inventaris'] : ('# ' . $aid);
    $device = asset_title($a);
    $foto = trim((string)($a['foto'] ?? ''));
    $fotoSrc = function_exists('asset_image_url') ? asset_image_url($foto) : ($foto ? (str_starts_with($foto, 'http') ? $foto : module_url($foto)) : '');
    if ($fotoSrc !== '') {
        $thumbHtml = '<div class="me-2 flex-shrink-0" style="cursor: pointer;" onclick="viewAssetPhoto(\''.e($fotoSrc).'\', \''.e(addslashes($device)).'\', \''.e(addslashes($kode)).'\', \''.e(addslashes($editUrl)).'\')" title="Klik untuk memperbesar foto unit"><img src="'.e($fotoSrc).'" class="rounded border shadow-xs" style="width: 44px; height: 44px; object-fit: cover;" alt="Foto '.e($device).'" onerror="this.onerror=null; this.src=\'\'; this.parentElement.innerHTML=\'<div class=\\\'rounded border bg-light d-flex align-items-center justify-content-center text-muted shadow-xs\\\' style=\\\'width: 44px; height: 44px;\\\'><i class=\\\'bi bi-display fs-5\\\'></i></div>\';"></div>';
    } else {
        $thumbHtml = '<div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted me-2 flex-shrink-0 shadow-xs" style="width: 44px; height: 44px;" title="Belum ada foto unit"><i class="bi bi-display fs-5"></i></div>';
    }
    $sn = !empty($a['serial_number']) && $a['serial_number'] !== '-' ? $a['serial_number'] : '';
    $user = !empty($a['karyawan_nama']) && $a['karyawan_nama'] !== '-' ? $a['karyawan_nama'] : 'Umum / Pool';
    $cabang = !empty($a['cabang_nama']) && $a['cabang_nama'] !== '-' ? $a['cabang_nama'] : '-';
    $divisi = !empty($a['divisi_nama']) && $a['divisi_nama'] !== '-' ? $a['divisi_nama'] : '';
    $ip = !empty($a['ip_address']) ? $a['ip_address'] : (!empty($a['ip']) ? $a['ip'] : '-');
    $printer = !empty($a['printer']) ? $a['printer'] : '-';
    $stAset = trim((string)($a['status'] ?? 'Aktif')) ?: 'Aktif';
    $token = !empty($a['qr_token']) ? $a['qr_token'] : get_static_qr_token($aid);

    // Status Badge Komputer
    $stAsetLower = strtolower($stAset);
    $isAsetAktif = ($stAsetLower === 'aktif' || $stAsetLower === '');
    $isAsetTidakTerpakai = in_array($stAsetLower, ['tidak terpakai', 'nonaktif', 'afkir', 'disposal', 'pensiun', 'rusak total', 'diganti baru'], true);

    $statusDotClass = match ($stAsetLower) {
        'aktif', '' => 'operational',
        'perbaikan' => 'warning',
        'backup' => 'repair',
        'tidak terpakai', 'nonaktif' => 'offline',
        'afkir', 'disposal' => 'offline',
        default => 'offline',
    };
    $statusChipClass = match ($stAsetLower) {
        'aktif', '' => 'chip-success',
        'perbaikan' => 'chip-warning',
        'backup' => 'chip-primary',
        'tidak terpakai' => 'chip-secondary',
        'nonaktif' => 'chip-secondary',
        'afkir', 'disposal' => 'chip-danger',
        default => 'chip-secondary',
    };
    $statusLabelText = ($stAsetLower === 'tidak terpakai') ? 'Tidak Terpakai' : ucfirst($stAset);
    $statusBadge = '<span class="badge-chip '.$statusChipClass.'"><span class="status-dot '.$statusDotClass.'"></span> '.e($statusLabelText).'</span>';



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
    } elseif (!$isAsetAktif) {
        // Aset Tidak Terpakai / Nonaktif / Afkir: BEBAS DARI TARGET PEMELIHARAAN BULANAN
        $maintBadge = '<span class="badge-chip chip-secondary text-muted" title="Unit ini berstatus '.e(ucfirst($stAset)).' (bebas dari pemeliharaan bulanan)"><i class="bi bi-slash-circle me-1"></i> Tidak Dimaintenance</span>';
    } else {
        $maintBadge = '<span class="badge-chip chip-warning"><i class="bi bi-clock"></i> Belum Diperiksa</span>';
    }

    // Link Action (Target QR & Scan Resmi selalu menggunakan https://it.bankmitra.co.id)
    $scanUrl = $token ? qr_url('scan.php', ['t' => $token]) : '';
    $cardUrl = module_url('print_card.php', ['id' => $aid, 'layout' => 'single']);
    $editUrl = module_url('asset_edit.php', ['id' => $aid]);
    $deleteUrl = module_url('asset_delete.php', ['id' => $aid, 'redirect' => $_SERVER['REQUEST_URI'] ?? module_url('assets.php')]);

    // Cross-link penggantian unit
    $replacesId = (int)($a['replaces_asset_id'] ?? 0);
    $replacedById = (int)($a['replaced_by_id'] ?? 0);
    $crossLinkTag = '';
    if ($replacesId > 0) {
        $crossLinkTag = '<div class="mt-1"><a href="'.e(module_url('asset_edit.php', ['id' => $replacesId])).'" class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 text-decoration-none fw-semibold py-0.5 px-1.5" style="font-size:0.68rem;" title="Unit ini menggantikan komputer lama #'.$replacesId.'"><i class="bi bi-arrow-return-right me-0.5"></i>Ganti #'.$replacesId.'</a></div>';
    } elseif ($replacedById > 0) {
        $crossLinkTag = '<div class="mt-1"><a href="'.e(module_url('asset_edit.php', ['id' => $replacedById])).'" class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning border-opacity-50 text-decoration-none fw-semibold py-0.5 px-1.5" style="font-size:0.68rem;" title="Komputer ini telah diganti oleh unit baru #'.$replacedById.'"><i class="bi bi-arrow-right-short me-0.5"></i>Diganti #'.$replacedById.'</a></div>';
    }

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
            'token' => $token,
            'foto' => $fotoSrc,
            'edit_url' => $editUrl
        ];
        $qrModalJson = htmlspecialchars(json_encode($qrModalData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
    }

    // Quick Action Button for Technicians
    $quickActionBtn = '';
    if ($isRepairIssue && $scanUrl) {
        $quickActionBtn = '<a class="flex items-center gap-1 h-[28px] px-2.5 bg-[#DC2626] hover:bg-[#b91c1c] text-white font-semibold text-[11px] rounded-[6px] text-decoration-none transition-colors" href="'.e($scanUrl . '&action=tindak_lanjut').'" title="Tindak Lanjuti Kendala"><i class="bi bi-tools"></i> Perbaiki</a>';
    } elseif (!$isDoneMaint && $isAsetAktif && $scanUrl) {
        $quickActionBtn = '<a class="flex items-center gap-1 h-[28px] px-2.5 bg-[#0E5C58] hover:bg-[#0c4f4b] text-white font-semibold text-[11px] rounded-[6px] text-decoration-none transition-colors" href="'.e($scanUrl . '&action=start').'" title="Mulai Checklist Pemeliharaan"><i class="bi bi-clipboard-check"></i> Periksa</a>';
    } elseif ($scanUrl) {
        $quickActionBtn = '<a class="flex items-center gap-1 h-[28px] px-2.5 bg-[#D9F1EF] hover:bg-[#c2ebe8] text-[#0E5C58] font-semibold text-[11px] rounded-[6px] text-decoration-none transition-colors" href="'.e($scanUrl).'" title="Buka Kartu"><i class="bi bi-credit-card"></i> Buka Kartu</a>';
    } elseif (!$isAsetAktif) {
        $quickActionBtn = '<span class="inline-flex items-center h-[28px] px-2 bg-[#F4F5F7] text-[#53595F] text-[11px] font-semibold rounded-[6px]" title="Unit Bebas Maintenance">Bebas Maint</span>';
    }

    $mobileCards .= '
    <div class="bg-white border border-[#D1D5D8] rounded-[10px] p-3 shadow-xs position-relative" id="m-card-asset-'.$aid.'">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="d-flex align-items-center gap-2">
          <input class="form-check-input asset-checkbox mt-0" type="checkbox" value="'.$aid.'" data-id="'.$aid.'" data-kode="'.e($kode).'" data-device="'.e($device).'" data-user="'.e($user).'" data-cabang="'.e($cabang).'" data-sn="'.e($sn).'" style="cursor: pointer; width: 1.2rem; height: 1.2rem;">
          <span class="badge bg-light text-dark border font-mono small">#'.$startNum.'</span>
          <a href="'.e($scanUrl ?: $editUrl).'" class="fw-bold font-monospace text-[#0E5C58] text-decoration-none">'.e($kode).'</a>
        </div>
        <div class="d-flex align-items-center gap-1">
          '.$statusBadge.'
          '.$maintBadge.'
        </div>
      </div>
      
      <div class="d-flex align-items-start gap-2.5 mb-2">
        '.$thumbHtml.'
        <div class="min-w-0 flex-grow-1">
          <div class="fw-bold text-[#1B2A3A] text-truncate" style="font-size: 0.92rem;">'.e($device).'</div>
          <div class="text-[#53595F] small text-truncate" style="font-size: 0.76rem;">
            '.($sn ? '<span class="me-1">SN: <code>'.e($sn).'</code></span> &bull; ' : '').'
            <span class="badge bg-light text-secondary border">'.e($a['kategori_nama'] ?? 'PC').'</span>
          </div>
          '.$crossLinkTag.'
        </div>
      </div>

      <div class="bg-[#F8FAFC] rounded-[8px] p-2 mb-2 text-[#53595F]" style="font-size: 0.75rem;">
        <div class="d-flex align-items-center justify-content-between mb-1">
          <div><i class="bi bi-person text-secondary me-1"></i><strong class="text-[#1B2A3A]">'.e($user).'</strong>'.($divisi ? ' <span class="text-muted">('.e($divisi).')</span>' : '').'</div>
        </div>
        <div class="d-flex align-items-center justify-content-between text-muted">
          <div><i class="bi bi-building me-1"></i>'.e($cabang).'</div>
          '.($ip !== '-' ? '<div><i class="bi bi-hdd-network me-1 text-success"></i><code class="text-dark">'.e($ip).'</code></div>' : '').'
        </div>
      </div>

      <div class="d-flex align-items-center justify-content-between gap-2 pt-1 border-top border-[#F1F5F9]">
        <div class="d-flex align-items-center gap-1.5 flex-grow-1">
          '.$quickActionBtn.'
          '.($qrModalJson ? '
          <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2.5 rounded-[6px]" onclick="openQrModal('.$qrModalJson.')" title="Lihat Label QR">
            <i class="bi bi-qr-code"></i>
          </button>' : '').'
        </div>
        <div class="dropdown">
          <button class="btn btn-sm btn-light border px-2.5 py-1 shadow-xs" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-three-dots"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm border py-1" style="font-size: 0.82rem; min-width: 170px;">
            '.($scanUrl ? '<li><a class="dropdown-item py-1.5" href="'.e($scanUrl).'"><i class="bi bi-qr-code-scan me-2 text-info"></i> Buka / Scan</a></li>' : '').'
            <li><a class="dropdown-item py-1.5 text-primary fw-semibold" href="'.e(module_url('asset_add.php', ['replace_id' => $aid])).'"><i class="bi bi-arrow-repeat me-2"></i> Ganti Unit Baru</a></li>
            <li><a class="dropdown-item py-1.5" href="'.e(module_url('asset_mutation.php', ['id' => $aid, 'type' => 'komputer'])).'"><i class="bi bi-arrow-left-right me-2 text-primary"></i> Mutasi Aset</a></li>
            <li><a class="dropdown-item py-1.5" target="_blank" href="'.e($cardUrl).'"><i class="bi bi-printer me-2 text-secondary"></i> Cetak Kartu</a></li>
            <li><a class="dropdown-item py-1.5" href="'.e($editUrl).'"><i class="bi bi-pencil me-2 text-warning"></i> Edit Data</a></li>
            <li><hr class="dropdown-divider my-1"></li>
            <li><a class="dropdown-item py-1.5 text-danger" href="'.e($deleteUrl).'" onclick="return confirm(\'Yakin ingin menghapus aset '.e(addslashes($kode)).'?\');"><i class="bi bi-trash me-2"></i> Hapus</a></li>
          </ul>
        </div>
      </div>
    </div>';

    $tableRows .= '
    <tr id="row-asset-'.$aid.'">
      <td class="text-center">
        <input class="form-check-input asset-checkbox" type="checkbox" value="'.$aid.'" data-id="'.$aid.'" data-kode="'.e($kode).'" data-device="'.e($device).'" data-user="'.e($user).'" data-cabang="'.e($cabang).'" data-sn="'.e($sn).'" style="cursor: pointer; width: 1.15rem; height: 1.15rem;" title="Pilih unit '.e($kode).'">
      </td>
      <td class="text-center text-muted small">'.$startNum.'</td>
      <td>
        <div class="fw-bold text-dark fs-6 font-monospace">'.e($kode).'</div>
        <small class="text-muted" style="font-size: 0.72rem;">ID: #'.$aid.'</small>
        '.$crossLinkTag.'
      </td>
      <td>
        <div class="d-flex align-items-center">
          '.$thumbHtml.'
          <div>
            <div class="fw-semibold text-dark">'.e($device).'</div>
            <div class="small text-secondary" style="font-size: 0.75rem;">
              '.($sn ? '<span class="me-2"><span class="tech-label">SN:</span> <code class="text-dark">'.e($sn).'</code></span>' : '').'
              '.(!empty($a['kategori_nama']) ? '<span class="badge bg-light text-secondary border" style="font-size: 0.7rem;">'.e($a['kategori_nama']).'</span>' : '').'
            </div>
          </div>
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
        <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 py-1 px-2.5 rounded-pill shadow-xs" 
          onclick="openQrModal('.$qrModalJson.')" title="Klik untuk Pratinjau & Unduh QR">
          <i class="bi bi-qr-code"></i>
          <span style="font-size: 0.72rem; font-weight: 600;">QR</span>
        </button>' : '
        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1 rounded-pill" style="font-size: 0.7rem;">
          <i class="bi bi-dash"></i>
        </span>').'
      </td>
      <td class="text-end text-nowrap">
        <div class="d-inline-flex align-items-center gap-1">
          '.$quickActionBtn.'
          <div class="dropdown">
            <button class="btn btn-sm btn-light border px-2 py-1 shadow-xs" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Menu Aksi Lainnya">
              <i class="bi bi-three-dots-vertical"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border py-1" style="font-size: 0.82rem; min-width: 170px;">
              '.($scanUrl ? '<li><a class="dropdown-item py-1.5" href="'.e($scanUrl).'"><i class="bi bi-qr-code-scan me-2 text-info"></i> Buka / Scan</a></li>' : '').'
              <li><a class="dropdown-item py-1.5 text-primary fw-semibold" href="'.e(module_url('asset_add.php', ['replace_id' => $aid])).'"><i class="bi bi-arrow-repeat me-2"></i> Ganti Unit Baru</a></li>
              <li><a class="dropdown-item py-1.5" href="'.e(module_url('asset_mutation.php', ['id' => $aid, 'type' => 'komputer'])).'"><i class="bi bi-arrow-left-right me-2 text-primary"></i> Mutasi Aset</a></li>
              <li><a class="dropdown-item py-1.5" target="_blank" href="'.e($cardUrl).'"><i class="bi bi-printer me-2 text-secondary"></i> Cetak Kartu</a></li>
              <li><a class="dropdown-item py-1.5" href="'.e($editUrl).'"><i class="bi bi-pencil me-2 text-warning"></i> Edit Data</a></li>
              <li><hr class="dropdown-divider my-1"></li>
              <li><a class="dropdown-item py-1.5 text-danger" href="'.e($deleteUrl).'" onclick="return confirm(\'Yakin ingin menghapus aset '.e(addslashes($kode)).'?\');"><i class="bi bi-trash me-2"></i> Hapus</a></li>
            </ul>
          </div>
        </div>
      </td>
    </tr>';
}

if (empty($mobileCards)) {
    $mobileCards = '
    <div class="text-center py-5 bg-white rounded-[10px] border border-[#D1D5D8] p-4">
      <div class="text-secondary opacity-75 mb-2"><i class="bi bi-pc-display fs-1"></i></div>
      <h6 class="fw-bold text-secondary">Tidak ada data komputer yang cocok dengan filter.</h6>
      <p class="text-muted small mb-3">Coba ubah kata kunci pencarian atau reset filter di atas.</p>
      <a class="btn btn-sm btn-outline-primary" href="'.e(module_url('assets.php')).'"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter</a>
    </div>';
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
<div class="flex flex-col xl:flex-row xl:items-center justify-between gap-3 mb-4">
  <div>
    <h1 class="text-[24px] font-bold text-[#1B2A3A] tracking-tight mb-0">Data Komputer &amp; PC</h1>
    <p class="text-[13px] text-[#53595F] mb-0">Katalog dan registri lengkap perangkat IT, komputer kantor cabang, dan status pemeliharaan.</p>
  </div>
  <div class="flex items-center gap-2 flex-wrap">
    <a class="flex items-center gap-1.5 h-[36px] px-3.5 bg-[#0E5C58] hover:bg-[#0c4f4b] text-white font-semibold text-[13px] rounded-[8px] transition-colors shadow-sm text-decoration-none" href="'.e(module_url('asset_add.php')).'">
      <i class="bi bi-plus-lg"></i> Tambah Aset
    </a>
    <button type="button" class="flex items-center gap-1.5 h-[36px] px-3 bg-[#FFFFFF] border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] font-medium text-[13px] rounded-[8px] transition-colors cursor-pointer" onclick="openBulkEditModal()">
      <i class="bi bi-pencil-square text-[#875315]"></i> Edit Massal <span class="bg-[#F4F5F7] border border-[#D1D5D8] text-[#53595F] text-[11px] font-bold px-1.5 py-0.5 rounded-[4px] ml-1"><span class="selectedCountNum">0</span></span>
    </button>
    <a class="flex items-center gap-1.5 h-[36px] px-3 bg-[#FFFFFF] border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] font-medium text-[13px] rounded-[8px] transition-colors text-decoration-none" href="'.e(module_url('asset_import.php')).'">
      <i class="bi bi-file-earmark-arrow-up text-[#0E5C58]"></i> Import
    </a>
    <a class="flex items-center gap-1.5 h-[36px] px-3 bg-[#FFFFFF] border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] font-medium text-[13px] rounded-[8px] transition-colors text-decoration-none" href="'.e(module_url('asset_gallery.php')).'">
      <i class="bi bi-images text-[#0E5C58]"></i> Galeri Foto
    </a>
    <a class="flex items-center gap-1.5 h-[36px] px-3 bg-[#FFFFFF] border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] font-medium text-[13px] rounded-[8px] transition-colors text-decoration-none" href="'.e(module_url('asset_mutations.php')).'">
      <i class="bi bi-arrow-left-right text-[#0E5C58]"></i> Mutasi
    </a>
    <div class="dropdown">
      <button type="button" class="flex items-center gap-1.5 h-[36px] px-3 bg-[#FFFFFF] border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] font-medium text-[13px] rounded-[8px] transition-colors cursor-pointer" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-printer text-[#53595F]"></i> Cetak Kartu <i class="bi bi-chevron-down text-[11px] text-[#53595F]"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm border mt-1" style="border-radius: 8px; min-width: 220px;">
        <li>
          <a class="dropdown-item py-2" target="_blank" href="'.e(module_url('print_card.php', ['cabang' => $cabangId, 'tahun' => $year])).'">
            <i class="bi bi-printer text-[#0E5C58] me-2"></i> Cetak Kartu Kontrol
          </a>
        </li>
        <li><hr class="dropdown-divider my-1"></li>
        <li>
          <a class="dropdown-item py-2" target="_blank" href="'.e(module_url('print_inventory_card.php', ['cabang' => $cabangId])).'">
            <i class="bi bi-credit-card-2-front text-[#875315] me-2"></i> Cetak Kartu Inventaris
          </a>
        </li>
      </ul>
    </div>
    <a class="flex items-center justify-center w-[36px] h-[36px] bg-[#FFFFFF] border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#53595F] font-medium text-[13px] rounded-[8px] transition-colors text-decoration-none" href="'.e(module_url('export_csv.php')).'" title="Export Data CSV">
      <i class="bi bi-download"></i>
    </a>
  </div>
</div>

<!-- Interactive Branch Switcher Navigation Bar -->
'.$branchPillsHtml.'

<!-- Metric Strip (88px) -->
'.$metricStripHtml.'

<!-- Tab Filter Cepat (44px) -->
'.$todoTabsHtml.'

<!-- Compact Filter Bar -->
<div class="bg-white border border-[#D1D5D8] rounded-[10px] p-4 mb-4">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-lg-3 col-md-6">
      <label class="form-label text-[#53595F] text-[12px] font-bold uppercase tracking-wider mb-1">Cari Perangkat / User / IP</label>
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-white border-[#D1D5D8] text-[#53595F]"><i class="bi bi-search"></i></span>
        <input type="text" class="form-control form-control-sm border-[#D1D5D8] text-[#1B2A3A]" name="q" value="'.e($search).'" placeholder="Ketik kode, merk, tipe, nama pengguna, IP...">
      </div>
    </div>
    <div class="col-lg-2 col-md-3 col-sm-6">
      <label class="form-label text-[#53595F] text-[12px] font-bold uppercase tracking-wider mb-1">Kantor Cabang</label>
      <select class="form-select form-select-sm border-[#D1D5D8] text-[#1B2A3A]" name="cabang">
        '.$optCab.'
      </select>
    </div>
    <div class="col-lg-2 col-md-3 col-sm-6">
      <label class="form-label text-[#53595F] text-[12px] font-bold uppercase tracking-wider mb-1">Divisi</label>
      <select class="form-select form-select-sm border-[#D1D5D8] text-[#1B2A3A]" name="divisi">
        '.$optDiv.'
      </select>
    </div>
    <div class="col-lg-2 col-md-3 col-sm-6">
      <label class="form-label text-[#53595F] text-[12px] font-bold uppercase tracking-wider mb-1">Status Operasional Aset</label>
      <select class="form-select form-select-sm border-[#D1D5D8] text-[#1B2A3A]" name="status">
        <option value="">Semua Status Operasional</option>
        <option value="Aktif"'.($statusAset === 'Aktif' ? ' selected' : '').'>Aktif (Wajib Maint)</option>
        <option value="Tidak Terpakai"'.($statusAset === 'Tidak Terpakai' ? ' selected' : '').'>Tidak Terpakai (Histori)</option>
        <option value="Backup"'.($statusAset === 'Backup' ? ' selected' : '').'>Backup / Cadangan</option>
        <option value="Perbaikan"'.($statusAset === 'Perbaikan' ? ' selected' : '').'>Sedang Perbaikan</option>
        <option value="Nonaktif"'.($statusAset === 'Nonaktif' ? ' selected' : '').'>Nonaktif</option>
        <option value="Afkir"'.($statusAset === 'Afkir' ? ' selected' : '').'>Afkir / Dihapuskan</option>
      </select>
    </div>
    <div class="col-lg-2 col-md-3 col-sm-6">
      <label class="form-label text-[#53595F] text-[12px] font-bold uppercase tracking-wider mb-1">Status Maintenance</label>
      <select class="form-select form-select-sm border-[#D1D5D8] text-[#1B2A3A]" name="maint">
        <option value="all">Semua Status Maint</option>
        <option value="done"'.($maintStatus === 'done' ? ' selected' : '').'>Selesai</option>
        <option value="pending"'.($maintStatus === 'pending' ? ' selected' : '').'>Belum Diperiksa (Aktif)</option>
        <option value="repair"'.($maintStatus === 'repair' ? ' selected' : '').'>Ada Temuan</option>
      </select>
    </div>

    <div class="col-lg-2 col-md-3 col-sm-6">
      <label class="form-label text-[#53595F] text-[12px] font-bold uppercase tracking-wider mb-1">Periode Bulan &amp; Tahun</label>
      <div class="d-flex gap-1">
        <select class="form-select form-select-sm border-[#D1D5D8] text-[#1B2A3A]" name="bulan">
          '.$optMonth.'
        </select>
        <select class="form-select form-select-sm border-[#D1D5D8] text-[#1B2A3A]" name="tahun" style="max-width: 90px;">
          '.$optYear.'
        </select>
      </div>
    </div>
    <div class="col-12 d-flex justify-content-between align-items-center pt-2 border-top mt-2">
      <div class="text-[#53595F] text-[13px]">
        Menampilkan <strong>'.$totalFiltered.'</strong> unit dari total <strong>'.$statTotal.'</strong> unit komputer.
      </div>
      <div class="d-flex gap-2">
        <a class="flex items-center gap-1 h-[34px] px-3 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#53595F] font-semibold text-[13px] rounded-[8px] transition-colors text-decoration-none" href="'.e(module_url('assets.php')).'"><i class="bi bi-x-circle me-1"></i> Reset</a>
        <button type="submit" class="flex items-center gap-1 h-[34px] px-4 bg-[#0E5C58] hover:bg-[#0c4f4b] text-white font-semibold text-[13px] rounded-[8px] transition-colors cursor-pointer"><i class="bi bi-filter me-1"></i> Terapkan</button>
      </div>
    </div>
  </form>
</div>

'.$statusBannerHtml.'

<!-- Table Surface Card -->
<div class="bg-white border border-[#D1D5D8] rounded-[10px] overflow-hidden mb-4 shadow-none">
  <div class="bg-white border-b border-[#D1D5D8] py-3.5 px-5 flex justify-between items-center flex-wrap gap-2">
    <div>
      <h2 class="text-[15px] font-bold text-[#1B2A3A] mb-0 flex items-center gap-2">
        <i class="bi bi-pc-display text-[#0E5C58]"></i> Daftar Perangkat IT &amp; Komputer
      </h2>
      <div class="text-[12px] text-[#53595F]">Seluruh unit PC Desktop, Laptop, dan Printer terdata</div>
    </div>
    <div class="text-[12px] font-medium text-[#53595F]">
      Halaman '.$page.' dari '.$totalPages.'
    </div>
  </div>

  <!-- Selection Action Bar (Appears when items are selected) -->
  <div id="selectionActionBar" class="border-b border-[#D1D5D8] px-4 py-2.5 d-none align-items-center justify-content-between flex-wrap gap-2 bg-[#D9F1EF]">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <span class="bg-[#0E5C58] text-white text-[12px] font-bold px-2.5 py-1 rounded-[6px]"><i class="bi bi-check-square me-1"></i> <span id="selectedCountText">0</span> Dipilih</span>
      <span class="text-[#0E5C58] text-[12px] font-medium d-none d-md-inline-flex align-items-center">
        <i class="bi bi-pin-angle-fill me-1"></i> Tersimpan otomatis lintas pencarian &amp; filter
      </span>
      <button type="button" class="btn btn-sm btn-outline-dark fw-semibold py-1 px-2 text-[12px]" data-bs-toggle="modal" data-bs-target="#selectedAssetsModal" onclick="renderSelectedModalList()">
        <i class="bi bi-list-check me-1"></i> Lihat Daftar (<span class="selectedCountNum">0</span>)
      </button>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <button type="button" class="btn btn-sm btn-warning text-dark fw-semibold shadow-sm text-[12px]" onclick="openBulkEditModal()">
        <i class="bi bi-pencil-square me-1"></i> Edit Massal (<span class="selectedCountNum">0</span>)
      </button>
      <button type="button" class="btn btn-sm btn-dark fw-semibold shadow-sm text-[12px]" onclick="batchPrintCards()">
        <i class="bi bi-printer me-1"></i> Cetak Kartu Kontrol (<span class="selectedCountNum">0</span>)
      </button>
      <button type="button" class="btn btn-sm bg-[#0E5C58] text-white fw-semibold shadow-sm text-[12px]" onclick="batchPrintQR()">
        <i class="bi bi-qr-code me-1"></i> Cetak Label QR (<span class="selectedCountNum">0</span>)
      </button>
      <button type="button" class="btn btn-sm btn-info text-white fw-semibold shadow-sm text-[12px]" onclick="batchPrintInventoryCards()">
        <i class="bi bi-credit-card-2-front me-1"></i> Cetak Kartu Inventaris (<span class="selectedCountNum">0</span>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-secondary text-[12px]" onclick="deselectAllAssets()">
        <i class="bi bi-x-circle me-1"></i> Batalkan
      </button>
    </div>
  </div>

  <!-- TAMPILAN TABEL RESPONSIF (Dapat Digulir Halus di Smartphone & Desktop) -->
  <div class="table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
    <table class="table table-hover align-middle mb-0" style="table-layout: auto;">
      <thead class="bg-[#F4F5F7] text-[#53595F] text-[12px] font-bold uppercase tracking-wider border-b border-[#D1D5D8]">
        <tr>
          <th style="width: 38px;" class="text-center bg-[#F4F5F7]">
            <input class="form-check-input" type="checkbox" id="checkAllAssets" title="Pilih Semua di Halaman Ini" style="cursor: pointer; width: 1.15rem; height: 1.15rem;">
          </th>
          <th style="width: 36px;" class="text-center bg-[#F4F5F7]">No</th>
          <th style="width: 130px;" class="bg-[#F4F5F7]">Kode</th>
          <th class="bg-[#F4F5F7]">Perangkat / Spesifikasi</th>
          <th class="bg-[#F4F5F7]">Pengguna / Divisi</th>
          <th class="bg-[#F4F5F7]">Lokasi &amp; IP</th>
          <th style="width: 90px;" class="text-center bg-[#F4F5F7]">Kondisi</th>
          <th style="width: 140px;" class="text-center bg-[#F4F5F7]">Maintenance</th>
          <th style="width: 75px;" class="text-center bg-[#F4F5F7]">QR</th>
          <th style="width: 110px;" class="text-end bg-[#F4F5F7]">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-[#D1D5D8] text-[13px]">
        '.$tableRows.'
      </tbody>
    </table>
  </div>
  <div class="p-3 bg-white border-t border-[#D1D5D8]">
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

<!-- Modal Lihat Foto Unit -->
<div class="modal fade" id="modalViewPhoto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header py-3 px-4 border-bottom bg-light">
        <div>
          <h5 class="modal-title fw-bold text-dark mb-0" id="viewPhotoTitle">Foto Unit Perangkat</h5>
          <small class="text-primary font-monospace" id="viewPhotoSubtitle"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-3 text-center bg-dark bg-opacity-10">
        <img id="viewPhotoImg" src="" alt="Foto Unit" class="img-fluid rounded border shadow-sm" style="max-height: 480px; width: auto; object-fit: contain;">
      </div>
      <div class="modal-footer py-2 px-3 bg-light d-flex justify-content-between">
        <a id="viewPhotoEditLink" href="#" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-pencil me-1"></i> Ganti / Edit Foto
        </a>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Quick QR Preview (Sesuai Desain Pratinjau Stiker QR & Foto Perangkat) -->
<div class="modal fade" id="quickQrModal" tabindex="-1" aria-labelledby="quickQrModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header text-white" style="background: linear-gradient(135deg, #1E3A60 0%, #2E77AD 100%);">
        <div>
          <div class="small text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.85) !important;">PRATINJAU STIKER QR &amp; FOTO UNIT</div>
          <h5 class="modal-title fw-bold text-white mb-0" id="modalAssetKode" style="color: #ffffff !important;">INV-IT-001</h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <!-- Baris QR Code & Foto Fisik Perangkat -->
        <div class="row g-3 align-items-center mb-3">
          <!-- Sisi Kiri: Kode QR -->
          <div class="col-12 col-md-6 text-center">
            <div class="p-3 bg-white border border-2 border-primary border-opacity-25 rounded-3 shadow-sm d-inline-block position-relative">
              <div id="modalQrBox" style="width: 190px; height: 190px; margin: 0 auto; display: flex; align-items: center; justify-content: center;"></div>
            </div>
            <div class="small text-muted mt-2 fw-semibold" style="font-size: 0.75rem;">
              <i class="bi bi-qr-code me-1 text-primary"></i> Kode QR Maintenance IT
            </div>
          </div>

          <!-- Sisi Kanan: Foto Fisik Perangkat -->
          <div class="col-12 col-md-6 text-center">
            <div id="modalAssetPhotoContainer" class="p-2 bg-light border rounded-3 shadow-xs position-relative overflow-hidden d-flex align-items-center justify-content-center" style="min-height: 200px; height: 215px;">
              <img id="modalAssetPhotoImg" src="" alt="Foto Unit" class="w-100 h-100 object-fit-cover rounded border d-none" onerror="onModalPhotoError(this)">
              <div id="modalAssetPhotoPlaceholder" class="text-secondary small py-4">
                <i class="bi bi-camera fs-1 d-block mb-1 opacity-50 text-secondary"></i>
                <span class="fw-semibold">Belum ada foto fisik unit</span>
                <div class="mt-2">
                  <a id="modalAssetAddPhotoBtn" href="#" class="btn btn-sm btn-outline-primary py-1 px-2.5 fw-semibold" style="font-size: 0.75rem;">
                    <i class="bi bi-plus-circle me-1"></i> Upload Foto
                  </a>
                </div>
              </div>
            </div>
            <div class="small text-muted mt-2 fw-semibold d-flex justify-content-center align-items-center gap-1" style="font-size: 0.75rem;">
              <i class="bi bi-pc-display me-1 text-primary"></i> Foto Fisik Unit Perangkat
              <a id="modalAssetEditPhotoBtn" href="#" class="btn btn-sm btn-link py-0 px-1 text-decoration-none d-none" style="font-size: 0.72rem;">(Ganti Foto)</a>
            </div>
          </div>
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
<script src="' . e(module_url('assets/vendor/qrcode.min.js')) . '"></script>
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

  // Handle Foto Unit
  var photoImg = document.getElementById("modalAssetPhotoImg");
  var photoPlaceholder = document.getElementById("modalAssetPhotoPlaceholder");
  var editPhotoBtn = document.getElementById("modalAssetEditPhotoBtn");
  var addPhotoBtn = document.getElementById("modalAssetAddPhotoBtn");
  var editUrl = data.edit_url || (data.id ? ("'.e(module_url('asset_edit.php')).'?id=" + data.id) : "#");

  if (editPhotoBtn) editPhotoBtn.href = editUrl;
  if (addPhotoBtn) addPhotoBtn.href = editUrl;

  if (photoImg && photoPlaceholder) {
    if (data.foto) {
      photoImg.src = data.foto;
      photoImg.classList.remove("d-none");
      photoPlaceholder.classList.add("d-none");
      if (editPhotoBtn) editPhotoBtn.classList.remove("d-none");
    } else {
      photoImg.src = "";
      photoImg.classList.add("d-none");
      photoPlaceholder.classList.remove("d-none");
      if (editPhotoBtn) editPhotoBtn.classList.add("d-none");
    }
  }

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

function onModalPhotoError(img) {
  if (img) img.classList.add("d-none");
  var ph = document.getElementById("modalAssetPhotoPlaceholder");
  if (ph) ph.classList.remove("d-none");
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
      const card = document.getElementById("m-card-asset-" + cb.value);
      if (card) {
        if (isSelected) {
          card.classList.add("border-[#0E5C58]", "bg-[#F0FDF4]");
        } else {
          card.classList.remove("border-[#0E5C58]", "bg-[#F0FDF4]");
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

    document.querySelectorAll(\'.asset-checkbox[value="\' + id + \'"]\').forEach(function(targetCb) {
      targetCb.checked = false;
    });
    const targetCard = document.getElementById("m-card-asset-" + id);
    if (targetCard) {
      targetCard.classList.remove("border-[#0E5C58]", "bg-[#F0FDF4]");
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

  // Preview Foto Unit Modal
  window.viewAssetPhoto = function(src, title, kode, editUrl) {
    const img = document.getElementById("viewPhotoImg");
    const t = document.getElementById("viewPhotoTitle");
    const s = document.getElementById("viewPhotoSubtitle");
    if (img) img.src = src;
    if (t) t.textContent = title || "Foto Unit Perangkat";
    if (s) s.textContent = kode || "";
    const editBtn = document.getElementById("viewPhotoEditLink");
    if (editBtn) {
      if (editUrl) {
        editBtn.href = editUrl;
        editBtn.classList.remove("d-none");
      } else {
        editBtn.classList.add("d-none");
      }
    }
    const modalEl = document.getElementById("modalViewPhoto");
    if (modalEl) {
      const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
      modal.show();
    }
  };
});
</script>
';

render_page('Data Komputer & PC', $body, $extraHead, $extraScript);
