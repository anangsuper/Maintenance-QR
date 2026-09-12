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

// 1. Ambil data master untuk filter dropdown
$cabangs = get_cabang_list();
$divisis = get_divisi_list();

// 2. Query data aset dasar
$rawAssets = [];
if (is_google_cloud_mode()) {
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

// Render Opsi Dropdown Divisi
$optDiv = '<option value="0">Semua Divisi ('.count($divisis).')</option>';
foreach ($divisis as $d) {
    $did = (int)($d['id'] ?? 0);
    $dnama = $d['nama'] ?? $d['nama_divisi'] ?? ('Divisi #' . $did);
    $optDiv .= '<option value="'.$did.'"'.($did === $divisiId ? ' selected' : '').'>'.e($dnama).'</option>';
}

// Status Banner Khusus Filter Cepat To-Do List
$statusBannerHtml = '';
if ($maintStatus === 'pending') {
    $statusBannerHtml = '
    <div class="alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25 py-3 px-4 rounded-4 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 shadow-sm">
      <div class="d-flex align-items-center gap-3">
        <div class="fs-2 text-danger"><i class="bi bi-clipboard2-x-fill"></i></div>
        <div>
          <h6 class="fw-bold text-danger mb-0">🎯 TO-DO LIST: Komputer Jatuh Tempo (Belum Diperiksa Bulan Ini)</h6>
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
          <h6 class="fw-bold text-warning-emphasis mb-0">⚠️ DAFTAR TEMUAN MASALAH / PERBAIKAN (Pending Issues)</h6>
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
          <h6 class="fw-bold text-success mb-0">✅ DAFTAR KOMPUTER SELESAI MAINTENANCE</h6>
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
      <i class="bi bi-exclamation-circle-fill me-1"></i> 🔴 Belum Diperiksa Bulan Ini ('.$statPendingMaint.')
    </a>

    <!-- 3. Ada Temuan Masalah -->
    <a href="'.e(filter_query(['maint' => 'repair', 'page' => 1])).'" 
       class="btn btn-sm rounded-pill px-3 fw-bold '.($maintStatus === 'repair' ? 'btn-warning text-dark shadow' : 'btn-outline-warning text-dark').'" style="'.($maintStatus !== 'repair' ? 'background-color: #FFFBEB; border-color: #FCD34D;' : '').'">
      <i class="bi bi-tools me-1"></i> 🟡 Ada Temuan Masalah ('.$statRepair.')
    </a>

    <!-- 4. Sudah Selesai -->
    <a href="'.e(filter_query(['maint' => 'done', 'page' => 1])).'" 
       class="btn btn-sm rounded-pill px-3 fw-bold '.($maintStatus === 'done' ? 'btn-success text-white shadow' : 'btn-outline-success').'" style="'.($maintStatus !== 'done' ? 'background-color: #F0FDF4; border-color: #86EFAC;' : '').'">
      <i class="bi bi-check-circle-fill me-1"></i> 🟢 Selesai ('.$statDoneMaint.')
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

    // Quick Action Button for Technicians
    $quickActionBtn = '';
    if ($isRepairIssue && $scanUrl) {
        $quickActionBtn = '<a class="btn btn-sm btn-danger py-1 px-2 fw-semibold text-nowrap" href="'.e($scanUrl . '&action=tindak_lanjut').'" title="Tindak Lanjuti Kendala"><i class="bi bi-tools me-1"></i> Perbaiki</a>';
    } elseif (!$isDoneMaint && $scanUrl) {
        $quickActionBtn = '<a class="btn btn-sm btn-primary py-1 px-2 fw-semibold text-nowrap" href="'.e($scanUrl . '&action=start').'" title="Mulai Checklist Pemeliharaan"><i class="bi bi-clipboard-check me-1"></i> Periksa</a>';
    }

    $tableRows .= '
    <tr>
      <td class="text-center text-muted small">'.$startNum.'</td>
      <td>
        <div class="d-flex align-items-center gap-2">
          <div class="fw-bold text-dark fs-6 font-monospace">'.e($kode).'</div>
          '.($token ? '<a href="'.e($scanUrl).'" class="badge bg-light text-secondary border text-decoration-none" title="Lihat Kartu Kontrol"><i class="bi bi-qr-code"></i></a>' : '').'
        </div>
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
      <td colspan="8" class="text-center py-5">
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
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div>
    <div class="tech-label mb-1">ASSET MANAGEMENT</div>
    <h1 class="h3 mb-1">Asset Registry</h1>
    <p class="text-secondary small mb-0">Katalog dan inventaris lengkap perangkat IT, komputer kantor cabang, dan status pemeliharaan.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-primary d-inline-flex align-items-center gap-1 fw-semibold px-3" href="'.e(module_url('asset_add.php')).'"><i class="bi bi-plus-lg"></i> Tambah Aset</a>
    <a class="btn btn-light border d-inline-flex align-items-center gap-1" target="_blank" href="'.e(module_url('print_card.php', ['cabang' => $cabangId, 'tahun' => $year])).'"><i class="bi bi-printer"></i> Cetak Kartu Kontrol</a>
    <a class="btn btn-light border d-inline-flex align-items-center gap-1" href="'.e(module_url('export_csv.php')).'"><i class="bi bi-download"></i> Export CSV</a>
  </div>
</div>

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
  <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center">
    <div>
      <h2 class="h6 mb-0 fw-semibold text-dark"><i class="bi bi-pc-display me-2 text-primary"></i>Daftar Perangkat IT & Komputer</h2>
      <div class="text-secondary small">Seluruh unit PC Desktop, Laptop, dan Printer terdata</div>
    </div>
    <span class="small text-muted">Halaman '.$page.' dari '.$totalPages.'</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th style="width: 40px;" class="text-center">No</th>
          <th style="width: 150px;">Kode Inventaris</th>
          <th>Perangkat / Spesifikasi</th>
          <th>Pengguna / Divisi</th>
          <th>Lokasi & IP</th>
          <th style="width: 110px;" class="text-center">Kondisi</th>
          <th style="width: 160px;" class="text-center">Maintenance ('.$currentMonthName.')</th>
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
';

render_page('Asset Registry', $body);
