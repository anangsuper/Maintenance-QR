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

// 4. Hitung Statistik Keseluruhan
$statTotal = count($rawAssets);
$statAktif = 0;
$statDoneMaint = 0;
$statPendingMaint = 0;
$statRepair = 0;

foreach ($rawAssets as $a) {
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
    $token = $a['qr_token'] ?? get_asset_qr_token($aid) ?? '';

    // Status Badge Komputer
    $statusBadge = match (strtolower($stAset)) {
        'aktif' => '<span class="badge text-bg-success px-2 py-1"><i class="bi bi-check-circle me-1"></i>Aktif</span>',
        'perbaikan' => '<span class="badge text-bg-warning text-dark px-2 py-1"><i class="bi bi-tools me-1"></i>Perbaikan</span>',
        'backup' => '<span class="badge text-bg-info px-2 py-1"><i class="bi bi-shield-check me-1"></i>Backup</span>',
        'nonaktif' => '<span class="badge text-bg-secondary px-2 py-1"><i class="bi bi-slash-circle me-1"></i>Nonaktif</span>',
        default => '<span class="badge text-bg-light border px-2 py-1">'.e($stAset).'</span>',
    };

    // Status Maintenance Bulan Berjalan
    $mInfo = $maintStatusMap[$aid] ?? null;
    $maintBadge = '';
    if ($mInfo && $mInfo['is_done']) {
        $st = $mInfo['status'];
        if (in_array($st, ['Temuan', 'Perlu Perbaikan', 'Proses'], true)) {
            $maintBadge = '<span class="badge text-bg-danger text-wrap" title="Temuan pada '.$mInfo['date'].' oleh '.$mInfo['tech'].'"><i class="bi bi-exclamation-triangle-fill me-1"></i>Temuan: '.e($st).'</span>';
        } else {
            $maintBadge = '<span class="badge text-bg-success text-wrap" title="Selesai pada '.$mInfo['date'].' oleh '.$mInfo['tech'].'"><i class="bi bi-check-lg me-1"></i>Selesai ('.$mInfo['date'].')</span>';
        }
    } else {
        $maintBadge = '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle text-wrap"><i class="bi bi-clock me-1"></i>Belum Maintenance</span>';
    }

    // Link Action
    $scanUrl = $token ? module_url('scan.php', ['t' => $token]) : '';
    $cardUrl = module_url('print_card.php', ['id' => $aid, 'layout' => 'single']);
    $editUrl = module_url('asset_edit.php', ['id' => $aid]);
    $deleteUrl = module_url('asset_delete.php', ['id' => $aid, 'redirect' => $_SERVER['REQUEST_URI'] ?? module_url('assets.php')]);

    $tableRows .= '
    <tr>
      <td class="text-center text-muted fw-semibold small">'.$startNum.'</td>
      <td>
        <div class="d-flex align-items-center gap-2">
          <div class="fw-bold text-primary fs-6">'.e($kode).'</div>
          '.($token ? '<a href="'.e($scanUrl).'" class="badge text-bg-light border text-decoration-none" title="Lihat Kartu Kontrol / QR Scan"><i class="bi bi-qr-code text-primary"></i></a>' : '').'
        </div>
        <small class="text-muted">ID: #'.$aid.'</small>
      </td>
      <td>
        <div class="fw-bold text-dark">'.e($device).'</div>
        <div class="small text-secondary">
          '.($sn ? '<span class="me-2"><i class="bi bi-upc me-1"></i>SN: <code>'.e($sn).'</code></span>' : '').'
          '.(!empty($a['kategori_nama']) ? '<span class="badge bg-light text-secondary border">'.e($a['kategori_nama']).'</span>' : '').'
        </div>
      </td>
      <td>
        <div class="fw-semibold text-dark"><i class="bi bi-person-circle text-primary me-1"></i>'.e($user).'</div>
        '.($divisi ? '<div class="small text-secondary"><i class="bi bi-diagram-3 me-1"></i>'.e($divisi).'</div>' : '').'
      </td>
      <td>
        <div class="fw-semibold text-dark"><i class="bi bi-buildings text-secondary me-1"></i>'.e($cabang).'</div>
        '.($ip !== '-' ? '<div class="small text-muted font-monospace"><i class="bi bi-hdd-network me-1 text-success"></i>'.e($ip).'</div>' : '').'
      </td>
      <td class="text-center">'.$statusBadge.'</td>
      <td class="text-center">'.$maintBadge.'</td>
      <td class="text-end text-nowrap">
        <div class="btn-group btn-group-sm" role="group">
          '.($scanUrl ? '<a class="btn btn-outline-primary" href="'.e($scanUrl).'" title="Buka Kartu / Scan Form"><i class="bi bi-qr-code-scan"></i></a>' : '').'
          <a class="btn btn-outline-secondary" target="_blank" href="'.e($cardUrl).'" title="Cetak Kartu Kontrol 1 Lembar"><i class="bi bi-printer"></i></a>
          <a class="btn btn-outline-warning text-dark" href="'.e($editUrl).'" title="Edit Data Komputer"><i class="bi bi-pencil-square"></i></a>
          <a class="btn btn-outline-danger" href="'.e($deleteUrl).'" title="Hapus Aset Komputer"><i class="bi bi-trash3-fill"></i></a>
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
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <div class="d-inline-flex align-items-center gap-2 mb-1">
      <span class="badge text-bg-primary fs-7"><i class="bi bi-pc-display-horizontal me-1"></i> Database Aset</span>
      <span class="text-secondary small">Periode Maintenance: <strong>'.$currentMonthName.' '.$year.'</strong></span>
    </div>
    <h2 class="fw-bold mb-0 text-dark">Data Komputer & Aset IT</h2>
    <div class="text-secondary small">Katalog lengkap PC Desktop, Laptop, Printer, dan perangkat IT beserta status maintenance rutin.</div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-action-add fw-bold px-3 shadow-sm" href="'.e(module_url('asset_add.php')).'"><i class="bi bi-plus-circle-fill me-1"></i> Tambah Komputer Baru</a>
    <a class="btn btn-outline-primary fw-semibold px-3 shadow-sm" target="_blank" href="'.e(module_url('print_card.php', ['cabang' => $cabangId, 'tahun' => $year])).'"><i class="bi bi-printer-fill me-1"></i> Cetak Kartu Kontrol</a>
    <a class="btn btn-outline-secondary fw-semibold px-3 shadow-sm" href="'.e(module_url('qr_admin.php', ['cabang' => $cabangId])).'"><i class="bi bi-qr-code me-1"></i> Manajemen QR</a>
  </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <a href="'.e(filter_query(['status' => null, 'maint' => null, 'page' => 1])).'" class="text-decoration-none">
      <div class="card p-3 border-0 shadow-sm h-100 bg-white border-start border-4 border-primary">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="text-secondary small fw-semibold text-uppercase">Total Unit Komputer</div>
            <div class="fs-3 fw-bold text-dark mt-1">'.$statTotal.'</div>
            <div class="small text-muted">Seluruh unit terdaftar</div>
          </div>
          <div class="fs-1 text-primary opacity-50"><i class="bi bi-pc-display"></i></div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-sm-6 col-lg-3">
    <a href="'.e(filter_query(['maint' => 'done', 'page' => 1])).'" class="text-decoration-none">
      <div class="card p-3 border-0 shadow-sm h-100 bg-white border-start border-4 border-success">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="text-secondary small fw-semibold text-uppercase">Sudah Maintenance</div>
            <div class="fs-3 fw-bold text-success mt-1">'.$statDoneMaint.'</div>
            <div class="small text-muted">Bulan '.$currentMonthName.'</div>
          </div>
          <div class="fs-1 text-success opacity-50"><i class="bi bi-check-circle-fill"></i></div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-sm-6 col-lg-3">
    <a href="'.e(filter_query(['maint' => 'pending', 'page' => 1])).'" class="text-decoration-none">
      <div class="card p-3 border-0 shadow-sm h-100 bg-white border-start border-4 border-warning">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="text-secondary small fw-semibold text-uppercase">Belum Maintenance</div>
            <div class="fs-3 fw-bold text-warning mt-1">'.$statPendingMaint.'</div>
            <div class="small text-muted">Perlu dicek bulan ini</div>
          </div>
          <div class="fs-1 text-warning opacity-50"><i class="bi bi-clock-history"></i></div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-sm-6 col-lg-3">
    <a href="'.e(filter_query(['maint' => 'repair', 'page' => 1])).'" class="text-decoration-none">
      <div class="card p-3 border-0 shadow-sm h-100 bg-white border-start border-4 border-danger">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="text-secondary small fw-semibold text-uppercase">Perlu Tindak Lanjut</div>
            <div class="fs-3 fw-bold text-danger mt-1">'.$statRepair.'</div>
            <div class="small text-muted">Ada temuan kendala</div>
          </div>
          <div class="fs-1 text-danger opacity-50"><i class="bi bi-exclamation-octagon-fill"></i></div>
        </div>
      </div>
    </a>
  </div>
</div>

<!-- Filter Bar -->
<div class="card p-4 border-0 shadow-sm mb-4">
  <form method="get" class="row g-3 align-items-end">
    <div class="col-lg-4 col-md-6">
      <label class="form-label small fw-bold text-secondary">Pencarian Komputer / Pengguna / IP</label>
      <div class="input-group">
        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
        <input type="text" class="form-control" name="q" value="'.e($search).'" placeholder="Cari kode inventaris, merk, tipe, nama staf, IP...">
      </div>
    </div>
    <div class="col-lg-2 col-md-3 col-sm-6">
      <label class="form-label small fw-bold text-secondary">Cabang / Lokasi</label>
      <select class="form-select" name="cabang">
        '.$optCab.'
      </select>
    </div>
    <div class="col-lg-2 col-md-3 col-sm-6">
      <label class="form-label small fw-bold text-secondary">Divisi / Bagian</label>
      <select class="form-select" name="divisi">
        '.$optDiv.'
      </select>
    </div>
    <div class="col-lg-2 col-md-3 col-sm-6">
      <label class="form-label small fw-bold text-secondary">Status Komputer</label>
      <select class="form-select" name="status">
        <option value="">Semua Status</option>
        <option value="Aktif"'.($statusAset === 'Aktif' ? ' selected' : '').'>Aktif</option>
        <option value="Backup"'.($statusAset === 'Backup' ? ' selected' : '').'>Backup</option>
        <option value="Perbaikan"'.($statusAset === 'Perbaikan' ? ' selected' : '').'>Sedang Perbaikan</option>
        <option value="Nonaktif"'.($statusAset === 'Nonaktif' ? ' selected' : '').'>Nonaktif</option>
      </select>
    </div>
    <div class="col-lg-2 col-md-3 col-sm-6">
      <label class="form-label small fw-bold text-secondary">Status Maintenance</label>
      <select class="form-select" name="maint">
        <option value="all">Semua Status</option>
        <option value="done"'.($maintStatus === 'done' ? ' selected' : '').'>Sudah Selesai</option>
        <option value="pending"'.($maintStatus === 'pending' ? ' selected' : '').'>Belum Selesai</option>
        <option value="repair"'.($maintStatus === 'repair' ? ' selected' : '').'>Ada Temuan Masalah</option>
      </select>
    </div>
    <div class="col-12 d-flex justify-content-between align-items-center pt-2 border-top">
      <div class="text-secondary small">
        Ditemukan <strong>'.$totalFiltered.'</strong> unit komputer dari total <strong>'.$statTotal.'</strong> unit.
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm px-3" href="'.e(module_url('assets.php')).'"><i class="bi bi-x-circle me-1"></i> Reset</a>
        <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold"><i class="bi bi-funnel-fill me-1"></i> Terapkan Filter</button>
      </div>
    </div>
  </form>
</div>

<!-- Table Card -->
<div class="card p-0 border-0 shadow-sm overflow-hidden mb-4">
  <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center">
    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-table text-primary me-2"></i>Daftar Komputer & Perangkat IT</h5>
    <div class="d-flex align-items-center gap-2">
      <span class="badge text-bg-light border text-secondary">Halaman '.$page.' dari '.$totalPages.'</span>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width: 45px;" class="text-center">No</th>
          <th style="width: 160px;">Kode Inventaris</th>
          <th>Perangkat / Tipe</th>
          <th>Pengguna / PIC</th>
          <th>Lokasi & IP Address</th>
          <th style="width: 110px;" class="text-center">Status Unit</th>
          <th style="width: 170px;" class="text-center">Maintenance ('.$currentMonthName.')</th>
          <th style="width: 150px;" class="text-end">Aksi</th>
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

render_page('Data Komputer', $body);
