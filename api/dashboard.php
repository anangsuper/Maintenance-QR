<?php
require __DIR__ . '/bootstrap.php';
require_login();

// =========================================================================
// 1. TANGANI AKSI POST KARTU INVENTARIS CR80
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // A. Tambah Data Kartu Baru
    if ($action === 'create_card' || $action === 'create') {
        $res = insert_inventaris_kartu([
            'nomor_rekening'   => $_POST['nomor_rekening'] ?? '',
            'nama_barang'      => $_POST['nama_barang'] ?? '',
            'tanggal_perolehan'=> $_POST['tanggal_perolehan'] ?? date('Y-m-d'),
            'barcode_data'     => $_POST['barcode_data'] ?? '',
            'lokasi'           => $_POST['lokasi'] ?? ''
        ]);
        if (!empty($res['success'])) {
            $_SESSION['flash'] = 'Data kartu inventaris baru berhasil disimpan.';
        } else {
            $_SESSION['flash_error'] = 'Gagal menyimpan kartu: ' . ($res['error'] ?? 'Terjadi kesalahan.');
        }
        header('Location: ' . module_url('dashboard.php', ['tab' => 'kartu']));
        exit;
    }

    // B. Edit Data Kartu
    if ($action === 'update_card' || $action === 'update') {
        $cardId = (int)($_POST['id'] ?? 0);
        $ok = update_inventaris_kartu($cardId, [
            'nomor_rekening'   => $_POST['nomor_rekening'] ?? '',
            'nama_barang'      => $_POST['nama_barang'] ?? '',
            'tanggal_perolehan'=> $_POST['tanggal_perolehan'] ?? date('Y-m-d'),
            'barcode_data'     => $_POST['barcode_data'] ?? '',
            'lokasi'           => $_POST['lokasi'] ?? ''
        ]);
        if ($ok) {
            $_SESSION['flash'] = 'Data kartu inventaris berhasil diperbarui.';
        } else {
            $_SESSION['flash_error'] = 'Gagal memperbarui data kartu.';
        }
        header('Location: ' . module_url('dashboard.php', ['tab' => 'kartu']));
        exit;
    }

    // C. Hapus Data Kartu Satuan
    if ($action === 'delete_card' || $action === 'delete') {
        $cardId = (int)($_POST['id'] ?? 0);
        if ($cardId > 0 && delete_inventaris_kartu($cardId)) {
            $_SESSION['flash'] = 'Kartu inventaris berhasil dihapus.';
        } else {
            $_SESSION['flash_error'] = 'Gagal menghapus kartu inventaris.';
        }
        header('Location: ' . module_url('dashboard.php', ['tab' => 'kartu']));
        exit;
    }

    // D. Hapus Terpilih (Bulk Delete)
    if ($action === 'delete_batch_card' || $action === 'delete_batch') {
        $rawIds = trim((string)($_POST['ids'] ?? ''));
        $ids = [];
        foreach (explode(',', $rawIds) as $item) {
            $cId = (int)trim($item);
            if ($cId > 0) $ids[] = $cId;
        }
        if (!empty($ids) && delete_inventaris_kartu($ids)) {
            $_SESSION['flash'] = count($ids) . ' kartu inventaris terpilih berhasil dihapus.';
        } else {
            $_SESSION['flash_error'] = 'Gagal menghapus kartu terpilih.';
        }
        header('Location: ' . module_url('dashboard.php', ['tab' => 'kartu']));
        exit;
    }

    // E. Import dari Aset IT
    if ($action === 'import_from_assets') {
        $selectedAssetIds = $_POST['asset_ids'] ?? [];
        if (is_string($selectedAssetIds)) {
            $selectedAssetIds = explode(',', $selectedAssetIds);
        }
        $importedCount = 0;
        foreach ($selectedAssetIds as $aid) {
            $aid = (int)$aid;
            if ($aid <= 0) continue;
            $asset = get_asset_by_id($aid);
            if ($asset) {
                $token = !empty($asset['qr_token']) ? $asset['qr_token'] : get_static_qr_token($aid);
                $qrUrl = module_url('scan.php', ['t' => $token]);
                $cId = (int)($asset['id_cabang'] ?? $asset['cabang_id'] ?? 0);
                $cabangName = $asset['cabang_nama'] ?? 'KPO';
                $divName = !empty($asset['divisi_nama']) && $asset['divisi_nama'] !== '-' ? $asset['divisi_nama'] : '';
                $lokasi = $divName !== '' ? "{$cabangName} / {$divName}" : $cabangName;
                $pengguna = !empty($asset['karyawan_nama']) && $asset['karyawan_nama'] !== '-' ? $asset['karyawan_nama'] : 'Umum / Pool';

                $res = insert_inventaris_kartu([
                    'nomor_rekening'   => $asset['kode_inventaris'] ?? ('INV-IT-' . $aid),
                    'nama_barang'      => asset_title($asset),
                    'tanggal_perolehan'=> $asset['tanggal_perolehan'] ?? $asset['created_at'] ?? date('Y-m-d'),
                    'barcode_data'     => $qrUrl,
                    'lokasi'           => $lokasi
                ]);
                if (!empty($res['success'])) {
                    $importedCount++;
                }
            }
        }
        $_SESSION['flash'] = "{$importedCount} aset IT berhasil diimpor ke tabel Kartu Inventaris.";
        header('Location: ' . module_url('dashboard.php', ['tab' => 'kartu']));
        exit;
    }
}

// 2. Ambil Parameter Filter dengan Validasi
$activeTab = trim((string)($_GET['tab'] ?? ''));
if ($activeTab === '' && (isset($_GET['q_kartu']) || isset($_GET['cabang_kartu']))) {
    $activeTab = 'kartu';
}

$month = max(1, min(12, (int)($_GET['bulan'] ?? date('n'))));
$year = max(2020, min(2100, (int)($_GET['tahun'] ?? date('Y'))));
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));
$statusAset = strtolower(trim((string)($_GET['status_aset'] ?? '')));
$statusMaint = strtolower(trim((string)($_GET['status_maint'] ?? '')));

$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$monthName = $monthNames[$month] ?? date('F');

// Dukungan tombol Refresh data langsung dari Google Sheets
if (!empty($_GET['refresh']) && is_google_cloud_mode()) {
    $client = google_sheets_v4_client();
    if ($client) {
        $client->clearCache();
    }
}

// 2. Ambil Data Dashboard Komprehensif
$dashData = get_comprehensive_dashboard_data($month, $year, $cabangId, $statusAset, $statusMaint);
$branchSummaries = get_branch_maintenance_summary($month, $year);

$totalAll = $dashData['total_all'];
$totalActive = $dashData['total_active'];
$totalBroken = $dashData['total_broken'];
$totalDone = $dashData['total_done'];
$totalDue = $dashData['total_due'];
$totalUnresolvedFindings = $dashData['total_unresolved_findings'];

$recentLogs = $dashData['recent_logs'];
$upcomingList = $dashData['upcoming_list'];
$unresolvedFindings = $dashData['unresolved_findings'];
$branchDistribution = $dashData['branch_distribution'];
$monthlyOverview = $dashData['monthly_overview'];
$cabangs = $dashData['cabangs'];

$percentDone = $totalActive > 0 ? round(($totalDone / $totalActive) * 100) : 0;

$selectedCabangName = 'Semua Cabang';
foreach ($cabangs as $c) {
    if ((int)($c['id'] ?? 0) === $cabangId) {
        $selectedCabangName = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cabangId);
        break;
    }
}

// Ambil Seluruh Data Kartu Inventaris
$allCards = get_inventaris_kartu_rows(!empty($_GET['refresh']));
$totalCardsCount = count($allCards);

$standardCabangs = get_standard_cabang_list();

$searchCard = trim((string)($_GET['q_kartu'] ?? ''));
$cabangCard = trim((string)($_GET['cabang_kartu'] ?? ''));
$tahunCard = trim((string)($_GET['tahun_kartu'] ?? ''));

$todayDateStr = date('Y-m-d');
$todayDateDmy = date('d/m/Y');
$todayDateDmY = date('d-m-Y');

$filterHariIni = isset($_GET['hari_ini']) && (string)$_GET['hari_ini'] === '1';
$filterHasQr = isset($_GET['has_qr']) && (string)$_GET['has_qr'] === '1';

$todayCardsCount = 0;
$withQrCardsCount = 0;

// Metrik Jumlah Kartu Inventaris Per Cabang (01 - 05)
$cabangStats = [
    '01' => ['code' => '01', 'name' => 'Kantor Pusat', 'count' => 0, 'today' => 0, 'color' => '#2E7CF6', 'icon' => 'bi-building'],
    '02' => ['code' => '02', 'name' => 'Batulicin', 'count' => 0, 'today' => 0, 'color' => '#16803C', 'icon' => 'bi-geo-alt'],
    '03' => ['code' => '03', 'name' => 'Martapura', 'count' => 0, 'today' => 0, 'color' => '#D97706', 'icon' => 'bi-geo-alt'],
    '04' => ['code' => '04', 'name' => 'Tanjung', 'count' => 0, 'today' => 0, 'color' => '#8B5CF6', 'icon' => 'bi-geo-alt'],
    '05' => ['code' => '05', 'name' => 'Handil Bakti', 'count' => 0, 'today' => 0, 'color' => '#EC4899', 'icon' => 'bi-geo-alt'],
];

$branchKeywordMap = [
    '01' => ['kantor pusat', 'kpo', 'pusat'],
    '02' => ['batulicin'],
    '03' => ['martapura'],
    '04' => ['tanjung'],
    '05' => ['handil bakti', 'handil']
];

// Daftar tahun untuk filter kartu & hitung metrik
$cardYears = [];
foreach ($allCards as $ac) {
    $rek = trim((string)($ac['nomor_rekening'] ?? ''));
    $lok = strtolower(trim((string)($ac['lokasi'] ?? '')));
    $assignedCode = '01'; // Default

    if (preg_match('/^(\d{2})[\.\-]/', $rek, $m) && isset($cabangStats[$m[1]])) {
        $assignedCode = $m[1];
    } else {
        foreach ($branchKeywordMap as $bCode => $keywords) {
            foreach ($keywords as $kw) {
                if (strpos($lok, $kw) !== false) {
                    $assignedCode = $bCode;
                    break 2;
                }
            }
        }
    }

    $t = trim((string)($ac['tanggal_perolehan'] ?? ''));
    $createdAc = trim((string)($ac['created_at'] ?? ''));
    
    // Deteksi apakah diperoleh / dibuat hari ini
    $isTodayItem = false;
    if ($t !== '' && $t !== '0000-00-00') {
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $t, $m)) {
            $tglNorm = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            $y = $m[3];
        } else {
            $tglNorm = substr($t, 0, 10);
            $y = date('Y', strtotime($t));
        }
        if ($tglNorm === $todayDateStr) {
            $isTodayItem = true;
        }
        if ($y && !in_array($y, $cardYears, true)) {
            $cardYears[] = $y;
        }
    }
    if (!$isTodayItem && $createdAc !== '') {
        if (str_starts_with($createdAc, $todayDateStr) || str_starts_with($createdAc, $todayDateDmy) || str_starts_with($createdAc, $todayDateDmY)) {
            $isTodayItem = true;
        }
    }
    if ($isTodayItem) {
        $todayCardsCount++;
        if (isset($cabangStats[$assignedCode])) {
            $cabangStats[$assignedCode]['today']++;
        }
    }
    if (isset($cabangStats[$assignedCode])) {
        $cabangStats[$assignedCode]['count']++;
    }
    if (!empty($ac['barcode_data'])) {
        $withQrCardsCount++;
    }
}
rsort($cardYears);
if (!in_array((string)date('Y'), $cardYears, true)) {
    array_unshift($cardYears, (string)date('Y'));
}
$optTahunKartuHtml = '';
foreach ($cardYears as $cy) {
    $optTahunKartuHtml .= '<option value="' . e($cy) . '"' . ($tahunCard === (string)$cy ? ' selected' : '') . '>' . e($cy) . '</option>';
}

// Filter Kartu Inventaris (Mendukung Pencarian, Cabang 01-05, Tahun, Hari Ini, dan Label QR)
$filteredCards = array_filter($allCards, function($r) use ($searchCard, $cabangCard, $tahunCard, $filterHariIni, $filterHasQr, $todayDateStr, $todayDateDmy, $todayDateDmY) {
    if ($filterHasQr) {
        $bc = trim((string)($r['barcode_data'] ?? ''));
        if ($bc === '') return false;
    }
    if ($filterHariIni) {
        $tgl = trim((string)($r['tanggal_perolehan'] ?? ''));
        $created = trim((string)($r['created_at'] ?? ''));
        $isToday = false;
        if ($tgl !== '' && $tgl !== '0000-00-00') {
            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $tgl, $m)) {
                $tglNorm = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            } else {
                $tglNorm = substr($tgl, 0, 10);
            }
            if ($tglNorm === $todayDateStr) {
                $isToday = true;
            }
        }
        if (!$isToday && $created !== '') {
            if (str_starts_with($created, $todayDateStr) || str_starts_with($created, $todayDateDmy) || str_starts_with($created, $todayDateDmY)) {
                $isToday = true;
            }
        }
        if (!$isToday) return false;
    }
    if ($searchCard !== '') {
        $q = strtolower($searchCard);
        $haystack = strtolower(($r['nomor_rekening'] ?? '') . ' ' . ($r['nama_barang'] ?? '') . ' ' . ($r['barcode_data'] ?? '') . ' ' . ($r['lokasi'] ?? ''));
        if (strpos($haystack, $q) === false) return false;
    }
    if ($cabangCard !== '' && $cabangCard !== 'Semua Cabang' && $cabangCard !== 'all') {
        $rek = trim((string)($r['nomor_rekening'] ?? ''));
        $lokasi = strtolower(trim((string)($r['lokasi'] ?? '')));
        
        $matchFound = false;
        // Cek prefix nomor rekening (01., 02., 03., 04., 05.)
        if (preg_match('/^' . preg_quote($cabangCard, '/') . '[\.\-]/', $rek)) {
            $matchFound = true;
        } else {
            $branchMap = [
                '01' => ['kantor pusat', 'kpo', 'pusat'],
                '02' => ['batulicin'],
                '03' => ['martapura'],
                '04' => ['tanjung'],
                '05' => ['handil bakti', 'handil']
            ];
            if (isset($branchMap[$cabangCard])) {
                foreach ($branchMap[$cabangCard] as $kw) {
                    if (strpos($lokasi, $kw) !== false) {
                        $matchFound = true;
                        break;
                    }
                }
            } elseif (strpos($lokasi, strtolower($cabangCard)) !== false) {
                $matchFound = true;
            }
        }
        if (!$matchFound) return false;
    }
    if ($tahunCard !== '' && $tahunCard !== 'all' && $tahunCard !== 'Semua Tahun') {
        $tgl = trim((string)($r['tanggal_perolehan'] ?? ''));
        if ($tgl !== '' && $tgl !== '0000-00-00') {
            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $tgl, $m)) {
                $y = $m[3];
            } else {
                $y = date('Y', strtotime($tgl));
            }
            if ($y !== $tahunCard) return false;
        } else {
            return false;
        }
    }
    return true;
});
usort($filteredCards, fn($a, $b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));

$rawAssetsForModal = is_google_cloud_mode() ? map_sheets_assets() : get_qr_admin_rows(0);
$logoUri = app_logo_data_uri();

$flash = $_SESSION['flash'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_error']);

// 3. Tab Bar Navigasi Cepat Cabang
$branchTabs = '<a class="branch-nav-pill '.($cabangId === 0 ? 'active' : '').'" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>0,'status_aset'=>$statusAset,'status_maint'=>$statusMaint])).'">Semua Cabang</a>';
foreach ($cabangs as $c) {
    $cId = (int)($c['id'] ?? 0);
    $cNama = $c['nama'] ?? $c['nama_cabang'] ?? 'Cabang #' . $cId;
    $isActive = ($cId === $cabangId);
    $branchTabs .= '<a class="branch-nav-pill '.($isActive ? 'active' : '').'" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cId,'status_aset'=>$statusAset,'status_maint'=>$statusMaint])).'">'.e($cNama).'</a>';
}

// 4. Compact Branch Compliance Rows
$branchRowsHtml = '';
foreach ($branchSummaries as $bs) {
    $bId = $bs['id'];
    $bName = $bs['nama'];
    $bTotal = $bs['total'];
    $bDone = $bs['done'];
    $bPending = $bs['pending'];
    $bFindings = $bs['findings'];
    $bPercent = $bs['percent'];
    $isCurrent = ($bId === $cabangId);

    $complianceBadge = $bPercent === 100 
        ? '<span class="badge-chip chip-success">100%</span>' 
        : ($bPercent >= 75 
            ? '<span class="badge-chip chip-primary">'.$bPercent.'%</span>' 
            : '<span class="badge-chip chip-warning">'.$bPercent.'%</span>');

    $branchRowsHtml .= '
    <tr class="'.($isCurrent ? 'table-active' : '').'">
      <td>
        <div class="fw-semibold text-dark">'.e($bName).'</div>
        <div class="small text-muted">ID: #'.$bId.'</div>
      </td>
      <td class="text-center fw-semibold">'.$bTotal.'</td>
      <td class="text-center text-success fw-semibold">'.$bDone.'</td>
      <td class="text-center text-secondary">'.$bPending.'</td>
      <td class="text-center">'.($bFindings > 0 ? '<span class="text-danger fw-bold">'.$bFindings.'</span>' : '<span class="text-muted">0</span>').'</td>
      <td class="text-center">'.$complianceBadge.'</td>
      <td class="text-end text-nowrap">
        <div class="btn-group btn-group-sm">
          <a class="btn btn-sm btn-light border" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$bId])).'" title="Filter Dashboard Cabang Ini"><i class="bi bi-funnel"></i></a>
          <a class="btn btn-sm btn-light border" target="_blank" href="'.e(module_url('print_report.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$bId])).'" title="Cetak Rekap"><i class="bi bi-printer"></i></a>
          <a class="btn btn-sm btn-light border" target="_blank" href="'.e(module_url('print_card.php', ['cabang'=>$bId, 'layout'=>'grid6', 'tahun'=>$year])).'" title="Cetak Kartu Kontrol (6/A4)"><i class="bi bi-card-checklist"></i></a>
        </div>
      </td>
    </tr>';
}

// 5. Activity Stream (Timeline Items)
$activityStreamHtml = '';
if (!empty($recentLogs)) {
    foreach (array_slice($recentLogs, 0, 8) as $r) {
        $stVal = $r['status'] ?? 'Selesai';
        $stLower = strtolower($stVal);
        $timeStr = !empty($r['maintenance_time']) ? substr($r['maintenance_time'], 0, 5) : 'Hari ini';
        $dateStr = !empty($r['maintenance_date']) ? format_id_date($r['maintenance_date']) : '-';
        
        $dotClass = (in_array($stLower, ['temuan', 'perlu perbaikan'], true)) ? 'critical' : 'operational';
        $statusText = (in_array($stLower, ['temuan', 'perlu perbaikan'], true)) ? 'Temuan Kerusakan' : 'Maintenance Selesai';

        $devName = !empty($r['perangkat']) 
            ? $r['perangkat'] 
            : (!empty($r['nama_perangkat']) 
                ? $r['nama_perangkat'] 
                : trim(($r['merk'] ?? '') . ' ' . ($r['model'] ?? '')));
        if ($devName === '') {
            $devName = 'Perangkat IT';
        }

        $activityStreamHtml .= '
        <div class="activity-item d-flex align-items-start gap-3 py-2 border-bottom">
          <div class="activity-time font-monospace text-muted small mt-1">'.e($timeStr).'</div>
          <span class="status-dot '.$dotClass.' mt-2"></span>
          <div class="flex-grow-1 min-w-0">
            <div class="d-flex align-items-center justify-content-between">
              <span class="fw-semibold text-dark small">'.e($statusText).'</span>
              <span class="text-muted" style="font-size: 0.72rem;">'.e($dateStr).'</span>
            </div>
            <div class="small text-truncate text-secondary">
              <strong class="text-primary">'.e($r['kode_inventaris'] ?? 'Aset').'</strong> · '.e($devName).' ('.e($r['cabang_nama'] ?? '-').')
            </div>
            <div class="text-muted" style="font-size: 0.72rem;">Teknisi: '.e($r['technician_name'] ?? 'Teknisi').'</div>
          </div>
          '.(!empty($r['log_id']) ? '<a href="'.e(module_url('maintenance_detail.php', ['id' => $r['log_id']])).'" class="btn btn-sm btn-light border py-1 px-2" title="Detail"><i class="bi bi-chevron-right"></i></a>' : '').'
        </div>';
    }
} else {
    $activityStreamHtml = '<div class="text-center py-4 text-muted small"><i class="bi bi-clock-history fs-4 d-block mb-2 text-secondary opacity-50"></i>Belum ada aktivitas maintenance pada periode ini.</div>';
}

// 6. Active Findings List
$findingsListHtml = '';
if (!empty($unresolvedFindings)) {
    foreach (array_slice($unresolvedFindings, 0, 5) as $uf) {
        $findingsListHtml .= '
        <div class="p-2 mb-2 rounded border border-danger-subtle bg-danger-subtle d-flex align-items-start gap-2">
          <i class="bi bi-exclamation-triangle-fill text-danger mt-1"></i>
          <div class="flex-grow-1 min-w-0">
            <div class="fw-semibold text-danger small text-truncate">'.e($uf['kode_inventaris'] ?? 'Aset').' · '.e(!empty($uf['perangkat']) ? $uf['perangkat'] : (!empty($uf['nama_perangkat']) ? $uf['nama_perangkat'] : 'Perangkat')).'</div>
            <div class="small text-dark text-truncate">'.e($uf['findings'] ?? 'Kendala perangkat').'</div>
            <div class="text-muted" style="font-size: 0.7rem;">'.e($uf['cabang_nama'] ?? '-').' · '.e(format_id_date($uf['date'] ?? '')).'</div>
          </div>
          <a href="'.e(module_url('maintenance_detail.php', ['id' => $uf['log_id'] ?? 0])).'" class="btn btn-sm btn-danger py-0 px-2 fw-semibold" style="font-size: 0.75rem;">Periksa</a>
        </div>';
    }
} else {
    $findingsListHtml = '<div class="text-center py-3 text-muted small"><i class="bi bi-check-circle text-success fs-5 d-block mb-1"></i>Tidak ada temuan kendala aktif. Seluruh perangkat beroperasi normal.</div>';
}

// =========================================================================
// 5. GENERASI DATA TABEL KARTU INVENTARIS
// =========================================================================
$cardsTableRowsHtml = '';
if (empty($filteredCards)) {
    $cardsTableRowsHtml = '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>Tidak ada data kartu inventaris yang ditemukan.</td></tr>';
} else {
    foreach ($filteredCards as $c) {
        $cId = (int)$c['id'];
        $rek = $c['nomor_rekening'] ?? '';
        $nama = $c['nama_barang'] ?? '';
        $tglRaw = $c['tanggal_perolehan'] ?? '';
        $tglCard = format_card_date($tglRaw);
        $gabungan = get_nomor_asset_gabungan($rek, $tglRaw);
        $barcode = $c['barcode_data'] ?? '';
        $lokasi = $c['lokasi'] ?? 'KPO';
        $jsonPayload = json_encode(array_merge($c, ['gabungan' => $gabungan]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        // Deteksi apakah item baru diperoleh hari ini
        $isCardToday = false;
        $tglClean = trim((string)$tglRaw);
        $createdClean = trim((string)($c['created_at'] ?? ''));
        if ($tglClean !== '' && $tglClean !== '0000-00-00') {
            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $tglClean, $m)) {
                $tglNorm = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            } else {
                $tglNorm = substr($tglClean, 0, 10);
            }
            if ($tglNorm === $todayDateStr) {
                $isCardToday = true;
            }
        }
        if (!$isCardToday && $createdClean !== '') {
            if (str_starts_with($createdClean, $todayDateStr) || str_starts_with($createdClean, $todayDateDmy) || str_starts_with($createdClean, $todayDateDmY)) {
                $isCardToday = true;
            }
        }
        $todayBadge = $isCardToday ? '<span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 0.68rem; font-weight: 700;"><i class="bi bi-stars me-1"></i>Hari Ini</span>' : '';

        $cardsTableRowsHtml .= '
        <tr id="card-row-'.$cId.'">
          <td class="text-center">
            <input class="form-check-input card-checkbox" type="checkbox" value="'.$cId.'" onchange="updateCardCounters()">
          </td>
          <td>
            <span class="font-monospace fw-bold text-primary" style="font-size: 0.88rem;">'.e($rek).'</span>
          </td>
          <td>
            <div class="fw-semibold text-dark">'.e($nama).'</div>
          </td>
          <td>
            <span class="small text-secondary font-monospace"><i class="bi bi-calendar-event text-primary me-1"></i>'.e($tglCard).'</span>
            '.$todayBadge.'
          </td>
          <td>
            <span class="badge-chip chip-primary font-monospace" style="font-weight: 700; letter-spacing: 0.04em;">'.e($gabungan).'</span>
          </td>
          <td>';
        if ($barcode) {
            $cardsTableRowsHtml .= '<a href="'.e($barcode).'" target="_blank" class="small text-truncate d-inline-block text-primary text-decoration-none" style="max-width: 180px;" title="'.e($barcode).'"><i class="bi bi-box-arrow-up-right me-1"></i>'.e($barcode).'</a>';
        } else {
            $cardsTableRowsHtml .= '<span class="text-muted small">-</span>';
        }
        $cardsTableRowsHtml .= '
          </td>
          <td>
            <span class="badge-chip chip-secondary">'.e($lokasi).'</span>
          </td>
          <td class="text-end text-nowrap">
            <div class="btn-group btn-group-sm">
              <button type="button" class="btn btn-sm btn-light border" title="Pratinjau Fisik Kartu" onclick=\'openCardPreviewModal('.$jsonPayload.')\'>
                <i class="bi bi-eye"></i>
              </button>
              <a class="btn btn-sm btn-light border" target="_blank" href="'.e(module_url('print_inventory_card.php', ['source'=>'inventaris_kartu', 'id'=>$cId])).'" title="Cetak Kartu Ini">
                <i class="bi bi-printer"></i>
              </a>
              <button type="button" class="btn btn-sm btn-light border" title="Edit Kartu" onclick=\'openCardEditModal('.$jsonPayload.')\'>
                <i class="bi bi-pencil"></i>
              </button>
              <button type="button" class="btn btn-sm btn-light border text-danger" title="Hapus Kartu" onclick="confirmDeleteCard('.$cId.', \''.e(addslashes($rek . ' - ' . $nama)).'\')">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </td>
        </tr>';
    }
}

// Widget Ringkasan Kartu di Tab Monitoring
$quickCardsRowsHtml = '';
foreach (array_slice($allCards, 0, 5) as $qc) {
    $qId = (int)$qc['id'];
    $qRek = $qc['nomor_rekening'] ?? '';
    $qNama = $qc['nama_barang'] ?? '';
    $qTglRaw = $qc['tanggal_perolehan'] ?? '';
    $qTgl = format_card_date($qTglRaw);
    $qGab = get_nomor_asset_gabungan($qRek, $qTglRaw);

    $isQToday = false;
    $qTglClean = trim((string)$qTglRaw);
    $qCreatedClean = trim((string)($qc['created_at'] ?? ''));
    if ($qTglClean !== '' && $qTglClean !== '0000-00-00') {
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $qTglClean, $qm)) {
            $qTglNorm = sprintf('%04d-%02d-%02d', $qm[3], $qm[2], $qm[1]);
        } else {
            $qTglNorm = substr($qTglClean, 0, 10);
        }
        if ($qTglNorm === $todayDateStr) {
            $isQToday = true;
        }
    }
    if (!$isQToday && $qCreatedClean !== '') {
        if (str_starts_with($qCreatedClean, $todayDateStr) || str_starts_with($qCreatedClean, $todayDateDmy) || str_starts_with($qCreatedClean, $todayDateDmY)) {
            $isQToday = true;
        }
    }
    $qTodayBadge = $isQToday ? '<span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 0.65rem; font-weight: 700;">Hari Ini</span>' : '';

    $quickCardsRowsHtml .= '
    <tr>
      <td><span class="font-monospace fw-bold text-primary small">'.e($qRek).'</span></td>
      <td><span class="fw-semibold text-dark small">'.e($qNama).'</span></td>
      <td><span class="small text-secondary font-monospace">'.e($qTgl).'</span> '.$qTodayBadge.'</td>
      <td><span class="badge-chip chip-primary font-monospace">'.e($qGab).'</span></td>
      <td><span class="badge-chip chip-secondary">'.e($qc['lokasi'] ?? 'KPO').'</span></td>
      <td class="text-end">
        <a class="btn btn-sm btn-light border py-0 px-2" target="_blank" href="'.e(module_url('print_inventory_card.php', ['source'=>'inventaris_kartu', 'id'=>$qId])).'" title="Cetak Kartu"><i class="bi bi-printer"></i></a>
      </td>
    </tr>';
}

$head = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
.branch-nav-bar {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 6px;
  margin-bottom: 20px;
}
.branch-nav-pill {
  white-space: nowrap;
  padding: 6px 14px;
  font-size: 0.82rem;
  font-weight: 500;
  border-radius: 6px;
  background: #FFFFFF;
  border: 1px solid var(--border-subtle);
  color: var(--text-secondary);
  text-decoration: none;
  transition: all 0.15s ease;
}
.branch-nav-pill:hover {
  background: #F8FAFC;
  color: var(--text-primary);
  border-color: var(--border-strong);
}
.branch-nav-pill.active {
  background: var(--blue-corporate);
  border-color: var(--blue-corporate);
  color: #FFFFFF;
  font-weight: 600;
}
.ops-header-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
}
.activity-time {
  min-width: 45px;
  font-size: 0.72rem;
}
.dashboard-main-nav {
  display: flex;
  gap: 8px;
  border-bottom: 2px solid #E2E8F0;
  margin-bottom: 24px;
}
.dashboard-nav-tab {
  padding: 10px 18px;
  font-size: 0.9rem;
  font-weight: 600;
  color: #64748B;
  text-decoration: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.15s ease;
}
.dashboard-nav-tab:hover {
  color: var(--blue-corporate);
}
.dashboard-nav-tab.active {
  color: var(--blue-corporate);
  border-bottom-color: var(--blue-corporate);
}

/* Ukuran Standar Kartu ATM (CR80: 85.6mm x 54.0mm) */
.atm-card {
  width: 85.6mm !important;
  height: 54.0mm !important;
  box-sizing: border-box !important;
  border: 1.2px solid #e2e8f0 !important;
  border-radius: 8px !important;
  background: #ffffff !important;
  font-family: "Plus Jakarta Sans", Arial, sans-serif !important;
  color: #0f172a !important;
  overflow: hidden !important;
  position: relative !important;
  display: flex !important;
  flex-direction: column !important;
  padding: 0 !important;
  margin: 0 !important;
  box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08) !important;
  outline: 0.8px dashed #cbd5e1 !important;
  outline-offset: 1.5mm !important;
}
/* 1. Header Section */
.card-header-sec {
  display: flex !important;
  width: 100% !important;
  height: 11.5mm !important;
  border-bottom: 1.5px solid #003b73 !important;
  box-sizing: border-box !important;
}
.header-logo-box {
  width: 27% !important;
  height: 100% !important;
  border-right: 1px solid #e2e8f0 !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  background: #ffffff !important;
  box-sizing: border-box !important;
  padding: 0.8mm !important;
}
.header-logo-box img {
  max-height: 9.8mm !important;
  max-width: 95% !important;
  object-fit: contain !important;
}
.header-title-box {
  width: 73% !important;
  height: 100% !important;
  display: flex !important;
  flex-direction: column !important;
  box-sizing: border-box !important;
}
.header-main-title {
  background-color: #003b73 !important;
  color: #ffffff !important;
  font-weight: 800 !important;
  font-size: 7.8pt !important;
  text-align: center !important;
  height: 5.5mm !important;
  line-height: 5.5mm !important;
  text-transform: uppercase !important;
  letter-spacing: 0.2px !important;
  -webkit-print-color-adjust: exact !important;
  print-color-adjust: exact !important;
}
.header-sub-sec {
  height: 6.0mm !important;
  background: #ffffff !important;
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  padding: 0 !important;
  box-sizing: border-box !important;
  position: relative !important;
}
/* Pita Banner Hijau & Stripe Teal */
.green-banner-wrapper {
  position: relative !important;
  display: flex !important;
  height: 100% !important;
  width: 75% !important;
}
.teal-stripe {
  position: absolute !important;
  top: 0 !important;
  left: 0 !important;
  width: 100% !important;
  height: 100% !important;
  background-color: #009ca6 !important;
  clip-path: polygon(0 0, 78% 0, 71% 100%, 0 100%) !important;
  -webkit-print-color-adjust: exact !important;
  print-color-adjust: exact !important;
}
.green-banner {
  position: absolute !important;
  top: 0 !important;
  left: 0 !important;
  width: 92% !important;
  height: 100% !important;
  background: #7ac142 !important;
  clip-path: polygon(0 0, 78% 0, 71% 100%, 0 100%) !important;
  color: #ffffff !important;
  font-weight: 800 !important;
  font-size: 7.2pt !important;
  letter-spacing: 0.5px !important;
  display: flex !important;
  align-items: center !important;
  padding-left: 2.5mm !important;
  box-sizing: border-box !important;
  -webkit-print-color-adjust: exact !important;
  print-color-adjust: exact !important;
}
.header-dots-container {
  display: flex !important;
  align-items: center !important;
  padding-right: 3mm !important;
  height: 100% !important;
}
.header-dots {
  display: grid !important;
  grid-template-columns: repeat(3, 1mm) !important;
  grid-gap: 0.8mm !important;
}
.header-dots span {
  width: 0.9mm !important;
  height: 0.9mm !important;
  background-color: #7ac142 !important;
  border-radius: 50% !important;
  display: block !important;
  -webkit-print-color-adjust: exact !important;
  print-color-adjust: exact !important;
}

/* 2. Baris Data (Fields) */
.card-fields-sec {
  display: flex !important;
  flex-direction: column !important;
  width: 100% !important;
  padding: 0 !important;
  margin: 0 !important;
  box-sizing: border-box !important;
}
.card-field-row {
  display: flex !important;
  width: 100% !important;
  height: 6.125mm !important;
  border-bottom: 1px solid #e2e8f0 !important;
  box-sizing: border-box !important;
}
.field-icon-box {
  width: 8.5mm !important;
  height: 100% !important;
  background-color: #003b73 !important;
  color: #ffffff !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  box-sizing: border-box !important;
  -webkit-print-color-adjust: exact !important;
  print-color-adjust: exact !important;
}
.field-svg-icon {
  width: 3.5mm !important;
  height: 3.5mm !important;
  color: #ffffff !important;
}
.field-content-box {
  display: flex !important;
  align-items: center !important;
  width: 77.1mm !important;
  height: 100% !important;
  background: #ffffff !important;
  box-sizing: border-box !important;
}
.field-lbl {
  width: 25.5mm !important;
  color: #003b73 !important;
  font-weight: 800 !important;
  font-size: 7.0pt !important;
  display: flex !important;
  align-items: center !important;
  padding-left: 3.0mm !important;
  box-sizing: border-box !important;
  text-transform: uppercase !important;
  letter-spacing: 0.2px !important;
}
.field-sep {
  color: #7ac142 !important;
  font-weight: 800 !important;
  font-size: 9.0pt !important;
  margin: 0 1.0mm 0 2.0mm !important;
  display: flex !important;
  align-items: center !important;
}
.field-val {
  font-size: 7.0pt !important;
  color: #1e293b !important;
  font-weight: 800 !important;
  padding-left: 2.0mm !important;
  white-space: normal !important;
  line-height: 1.05 !important;
  overflow: hidden !important;
  display: -webkit-box !important;
  -webkit-line-clamp: 2 !important;
  -webkit-box-orient: vertical !important;
  text-transform: uppercase !important;
  flex-grow: 1;
  align-self: center !important;
  word-break: break-word !important;
}

/* 3. Bottom Section (Waves, Attention & QR Code) */
.card-bottom-sec {
  display: flex !important;
  width: 100% !important;
  height: 18.0mm !important;
  border-top: 1.5px solid #7ac142 !important;
  background-color: #ffffff !important;
  box-sizing: border-box !important;
  position: relative !important;
  -webkit-print-color-adjust: exact !important;
  print-color-adjust: exact !important;
}
.card-waves {
  position: absolute !important;
  bottom: 0 !important;
  left: 0 !important;
  width: 85.6mm !important;
  height: 7.5mm !important;
  z-index: 1 !important;
  pointer-events: none !important;
}
.bottom-left-attention {
  width: 68% !important;
  height: 100% !important;
  display: flex !important;
  align-items: center !important;
  padding: 1.0mm 2.0mm 1.0mm 3.0mm !important;
  box-sizing: border-box !important;
  z-index: 2 !important;
}
.attention-icon {
  margin-right: 2.2mm !important;
  display: flex !important;
  align-items: center !important;
  flex-shrink: 0 !important;
}
.attention-svg-icon {
  width: 7.2mm !important;
  height: 7.2mm !important;
  color: #003b73 !important;
}
.attention-text-box {
  display: flex !important;
  flex-direction: column !important;
}
.attention-title {
  font-weight: 800 !important;
  font-size: 7.2pt !important;
  color: #b91c1c !important;
  margin-bottom: 0.4mm !important;
  text-transform: uppercase !important;
  letter-spacing: 0.4px !important;
}
.attention-desc {
  font-size: 5.2pt !important;
  line-height: 1.35 !important;
  color: #0f172a !important;
  font-weight: 700 !important;
  letter-spacing: -0.05px !important;
}
.attention-qr-separator {
  width: 1px !important;
  height: 14.0mm !important;
  background-color: #e2e8f0 !important;
  align-self: center !important;
  z-index: 2 !important;
}
.bottom-right-qr {
  width: 32% !important;
  height: 100% !important;
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  justify-content: center !important;
  box-sizing: border-box !important;
  padding: 0.5mm !important;
  z-index: 2 !important;
}
.qr-border-box {
  border: 1px solid #cbd5e1 !important;
  border-radius: 4px !important;
  padding: 0.6mm !important;
  background: #ffffff !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04) !important;
  margin-bottom: 0 !important;
}
.card-qr-img {
  width: 13.5mm !important;
  height: 13.5mm !important;
}
.card-qr-img canvas, .card-qr-img img {
  width: 13.5mm !important;
  height: 13.5mm !important;
  margin: 0 auto !important;
  display: block;
}
.scan-info-capsule {
  display: none !important;
}
</style>';

// =========================================================================
// 6. BODY KONTEN DASHBOARD
// =========================================================================

// Persiapan Grid Jumlah Kartu Per Cabang (01 - 05)
$branchCardsGridHtml = '';
foreach ($cabangStats as $bCode => $b) {
    $isBranchActive = ($cabangCard === $bCode);
    $todayBadgeBranch = $b['today'] > 0 ? '<span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 0.65rem; font-weight: 700;">+'.$b['today'].' Hari Ini</span>' : '';
    $branchCardsGridHtml .= '
    <div class="col-6 col-md-4 col-lg">
      <a href="'.e(module_url('dashboard.php', ['tab'=>'kartu', 'cabang_kartu'=>($isBranchActive ? '' : $bCode)])).'" class="text-decoration-none">
        <div class="p-2 px-3 rounded border '.($isBranchActive ? 'border-primary bg-primary-subtle shadow-sm' : 'border-light-subtle bg-light').' h-100 transition-all" style="cursor: pointer;">
          <div class="d-flex justify-content-between align-items-center">
            <span class="font-monospace fw-bold small '.($isBranchActive ? 'text-primary' : 'text-secondary').'">'.$bCode.'</span>
            '.$todayBadgeBranch.'
          </div>
          <div class="fw-bold text-dark text-truncate mt-1" style="font-size: 0.85rem;">'.e($b['name']).'</div>
          <div class="d-flex align-items-baseline gap-1 mt-1">
            <span class="fs-5 fw-bold '.($isBranchActive ? 'text-primary' : 'text-dark').'">'.$b['count'].'</span>
            <span class="text-muted small" style="font-size: 0.72rem;">kartu</span>
          </div>
        </div>
      </a>
    </div>';
}

$branchBadgesWidgetHtml = '';
foreach ($cabangStats as $bCode => $b) {
    $branchBadgesWidgetHtml .= '<span class="badge bg-light text-dark border py-1 px-2"><span class="font-monospace fw-bold text-primary me-1">'.$bCode.'</span>'.e($b['name']).': <strong>'.$b['count'].'</strong>'.($b['today'] > 0 ? ' <span class="text-success fw-bold">(+'.$b['today'].')</span>' : '').'</span>';
}

$body = '
<!-- Flash Messages -->
'.($flash ? '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3" style="border-left: 4px solid #10B981 !important;"><i class="bi bi-check-circle-fill me-2 text-success"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '').'
'.($flashError ? '<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" style="border-left: 4px solid #EF4444 !important;"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>'.e($flashError).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '').'

<!-- Page Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
  <div>
    <div class="tech-label mb-1">BANKING IT OPERATIONS COMMAND</div>
    <h1 class="h3 mb-1 fw-bold text-dark">Dashboard IT Operations</h1>
    <p class="text-secondary small mb-0">Pusat komando pemeliharaan aset IT, audit berkala, dan manajemen cetak label kartu inventaris.</p>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <div class="ops-header-badge" style="background:#ECFDF3; border:1px solid #A6F4C5; color:#16803C;">
      <span class="status-dot operational"></span>
      <span>Operasional Normal</span>
    </div>
    <form method="get" class="d-flex align-items-center gap-2">
      <select name="bulan" class="form-select form-select-sm" style="width: 120px;" onchange="this.form.submit()">';
for ($m = 1; $m <= 12; $m++) {
    $body .= '<option value="'.$m.'"'.($m === $month ? ' selected' : '').'>'.$monthNames[$m].'</option>';
}
$body .= '
      </select>
      <select name="tahun" class="form-select form-select-sm" style="width: 90px;" onchange="this.form.submit()">';
for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++) {
    $body .= '<option value="'.$y.'"'.($y === $year ? ' selected' : '').'>'.$y.'</option>';
}
$body .= '
      </select>
      <input type="hidden" name="cabang" value="'.$cabangId.'">
      '.($activeTab ? '<input type="hidden" name="tab" value="'.e($activeTab).'">' : '').'
      <a href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId,'refresh'=>1])).'" class="btn btn-sm btn-light border" title="Segarkan Data Google Sheets"><i class="bi bi-arrow-clockwise"></i></a>
    </form>
  </div>
</div>

<!-- TOP KPI METRICS (6 KOTAK METRIK UTAMA) -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card card-metric h-100" style="border-left-color: var(--blue-corporate);">
      <div class="metric-value">'.$totalAll.'</div>
      <div class="metric-label">Aset Komputer</div>
      <div class="small text-muted mt-2" style="font-size: 0.72rem;">Unit terdaftar</div>
    </div>
  </div>

  <div class="col-6 col-md-4 col-xl-2">
    <div class="card card-metric h-100" style="border-left-color: #16803C;">
      <div class="metric-value text-success">'.$totalDone.'</div>
      <div class="metric-label">Selesai Diperiksa</div>
      <div class="small text-success mt-2" style="font-size: 0.72rem;"><i class="bi bi-check-circle me-1"></i>'.$percentDone.'% kepatuhan</div>
    </div>
  </div>

  <div class="col-6 col-md-4 col-xl-2">
    <div class="card card-metric h-100" style="border-left-color: #B54708;">
      <div class="metric-value text-warning">'.$totalDue.'</div>
      <div class="metric-label">Belum Maintenance</div>
      <div class="small text-secondary mt-2" style="font-size: 0.72rem;">Menunggu giliran</div>
    </div>
  </div>

  <div class="col-6 col-md-4 col-xl-2">
    <div class="card card-metric h-100" style="border-left-color: #B42318;">
      <div class="metric-value text-danger">'.$totalUnresolvedFindings.'</div>
      <div class="metric-label">Temuan Kendala</div>
      <div class="small text-danger mt-2" style="font-size: 0.72rem;">Perlu perbaikan</div>
    </div>
  </div>

  <div class="col-6 col-md-4 col-xl-2">
    <div class="card card-metric h-100" style="border-left-color: #2E7CF6;">
      <div class="metric-value text-primary">'.($totalAll - $totalBroken).'</div>
      <div class="metric-label">Perangkat Aktif</div>
      <div class="small text-secondary mt-2" style="font-size: 0.72rem;">Siap pakai</div>
    </div>
  </div>

  <div class="col-6 col-md-4 col-xl-2">
    <a href="'.e(module_url('dashboard.php', ['tab' => 'kartu'])).'" class="text-decoration-none">
      <div class="card card-metric h-100" style="border-left-color: #6bb82a; cursor: pointer;">
        <div class="metric-value text-success d-flex align-items-center justify-content-between">
          <span>'.$totalCardsCount.'</span>
          '.($todayCardsCount > 0 ? '<span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.68rem; font-weight: 700;">+'.$todayCardsCount.' Hari Ini</span>' : '').'
        </div>
        <div class="metric-label text-dark">Kartu Inventaris Aktif</div>
        <div class="small text-primary mt-2" style="font-size: 0.72rem;"><i class="bi bi-arrow-right-circle me-1"></i>Buka Kartu &raquo;</div>
      </div>
    </a>
  </div>
</div>

<!-- TABS NAVIGATION: SATU HALAMAN DASHBOARD -->
<div class="dashboard-main-nav">
  <a class="dashboard-nav-tab '.($activeTab !== 'kartu' ? 'active' : '').'" href="'.e(module_url('dashboard.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'">
    <i class="bi bi-speedometer2"></i> Ringkasan Maintenance & Kepatuhan
  </a>
  <a class="dashboard-nav-tab '.($activeTab === 'kartu' ? 'active' : '').'" href="'.e(module_url('dashboard.php', ['tab' => 'kartu'])).'">
    <i class="bi bi-credit-card-2-front"></i> Kartu Inventaris & Label QR
    <span class="badge bg-primary text-white rounded-pill ms-1">'.$totalCardsCount.'</span>
  </a>
</div>';

// =========================================================================
// 7. KONTEN TAB: KARTU INVENTARIS ATAU MONITORING
// =========================================================================
if ($activeTab === 'kartu') {
    // --- TAMPILAN TAB KARTU INVENTARIS (DISAMAKAN PERSIS DENGAN WEBSITE QR) ---
    $body .= '
    <!-- Header Kicker & Action Bar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <div class="text-uppercase small fw-bold" style="letter-spacing: 0.08em; color: var(--app-accent); font-size: 0.72rem; margin-bottom: 2px;">OPERATIONS / KARTU INVENTARIS</div>
        <h2 class="h4 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
          <i class="bi bi-credit-card-2-front text-primary"></i> Manajemen Kartu Inventaris
        </h2>
        <div class="text-muted small">Kelola data kartu inventaris, cetak massal A4, dan cetak kartu pilihan.</div>
      </div>
      <div class="d-flex gap-2 flex-wrap align-items-center">
        <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-1 fw-semibold px-3" data-bs-toggle="modal" data-bs-target="#addCardModal">
          <i class="bi bi-plus-lg"></i> Tambah Data Kartu
        </button>
        <a href="'.e(module_url('import_fbd3.php')).'" class="btn btn-outline-success d-inline-flex align-items-center gap-1 fw-semibold">
          <i class="bi bi-file-earmark-excel"></i> Import FBD3
        </a>
        <button type="button" class="btn btn-light border d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#importAssetModal">
          <i class="bi bi-box-seam"></i> Pilih dari Aset IT
        </button>
        <a href="'.e(module_url('print_inventory_card.php', ['source'=>'inventaris_kartu'])).'" target="_blank" class="btn btn-light border d-inline-flex align-items-center gap-1">
          <i class="bi bi-printer"></i> Cetak Semua (A4)
        </a>
        <div class="dropdown">
          <button class="btn btn-light border dropdown-toggle d-inline-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-download"></i> Ekspor
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.85rem;">
            <li>
              <a class="dropdown-item" href="'.e(module_url('print_inventory_card.php', ['export'=>'doc'])).'">
                <i class="bi bi-file-earmark-word-fill text-primary me-2"></i> Dokumen Microsoft Word (.doc)
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="'.e(module_url('print_inventory_card.php', ['export'=>'csv'])).'">
                <i class="bi bi-file-earmark-spreadsheet-fill text-success me-2"></i> Spreadsheet CSV (.csv)
              </a>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- 4 Kotak Metrik Ringkasan Tab Kartu Termasuk Baru Diperoleh Hari Ini -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <a href="'.e(module_url('dashboard.php', ['tab'=>'kartu'])).'" class="text-decoration-none">
          <div class="card card-metric h-100" style="border-left-color: #2E7CF6; cursor: pointer;">
            <div class="metric-value text-primary">'.$totalCardsCount.'</div>
            <div class="metric-label text-dark">Total Kartu Inventaris</div>
            <div class="small text-primary mt-2" style="font-size: 0.72rem;"><i class="bi bi-arrow-repeat me-1"></i>Semua unit &raquo;</div>
          </div>
        </a>
      </div>
      <div class="col-6 col-md-3">
        <a href="'.e(module_url('dashboard.php', ['tab'=>'kartu', 'hari_ini'=>($filterHariIni ? null : 1)])).'" class="text-decoration-none">
          <div class="card card-metric h-100 '.($filterHariIni ? 'bg-success-subtle border-success shadow-sm' : '').'" style="border-left-color: #16803C; cursor: pointer;">
            <div class="metric-value text-success d-flex align-items-center justify-content-between">
              <span>'.$todayCardsCount.'</span>
              '.($todayCardsCount > 0 ? '<span class="badge bg-success text-white" style="font-size: 0.72rem; font-weight: 700;"><i class="bi bi-stars me-1"></i>Baru</span>' : '').'
            </div>
            <div class="metric-label text-dark">Baru Diperoleh Hari Ini</div>
            <div class="small text-success mt-2" style="font-size: 0.72rem;"><i class="bi bi-funnel me-1"></i>'.($filterHariIni ? 'Tampilkan semua &raquo;' : 'Filter hari ini ('.date('d/m/Y').') &raquo;').'</div>
          </div>
        </a>
      </div>
      <div class="col-6 col-md-3">
        <a href="'.e(module_url('dashboard.php', ['tab'=>'kartu', 'has_qr'=>($filterHasQr ? null : 1)])).'" class="text-decoration-none">
          <div class="card card-metric h-100 '.($filterHasQr ? 'bg-info-subtle border-info shadow-sm' : '').'" style="border-left-color: #0284C7; cursor: pointer;">
            <div class="metric-value" style="color: #0284C7;">'.$withQrCardsCount.'</div>
            <div class="metric-label text-dark">Kartu Ber-Label QR</div>
            <div class="small text-info mt-2" style="font-size: 0.72rem;"><i class="bi bi-qr-code me-1"></i>'.($filterHasQr ? 'Tampilkan semua &raquo;' : 'Filter ber-QR ('.$withQrCardsCount.') &raquo;').'</div>
          </div>
        </a>
      </div>
      <div class="col-6 col-md-3">
        <a href="#branchDistributionGrid" class="text-decoration-none" onclick="document.getElementById(\'branchDistributionGrid\')?.scrollIntoView({behavior:\'smooth\'})">
          <div class="card card-metric h-100" style="border-left-color: #7C3AED; cursor: pointer;">
            <div class="metric-value" style="color: #7C3AED;">5</div>
            <div class="metric-label text-dark">Sebaran Kantor Cabang</div>
            <div class="small mt-2" style="color: #7C3AED; font-size: 0.72rem;"><i class="bi bi-buildings me-1"></i>01 Pusat s/d 05 Handil Bakti &raquo;</div>
          </div>
        </a>
      </div>
    </div>

    <!-- Ringkasan Jumlah Kartu Inventaris Per Cabang (01 - 05) -->
    <div id="branchDistributionGrid" class="card p-3 mb-4 border shadow-sm bg-white" style="border-radius: 8px; border-color: var(--app-border) !important;">
      <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <div>
          <div class="tech-label" style="font-size: 0.68rem;">DISTRIBUSI UNIT CABANG</div>
          <h3 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-buildings text-primary"></i> Jumlah Kartu Inventaris Per Cabang
          </h3>
        </div>
        <div class="text-secondary small">
          Total <strong>5 Kantor Cabang</strong> · Klik cabang untuk filter cepat
        </div>
      </div>
      <div class="row g-2 mt-1">
        '.$branchCardsGridHtml.'
      </div>
    </div>

    <!-- Compact Filter Bar (Sistem Filter Aset Komputer) -->
    <div class="card p-3 mb-4 border shadow-sm bg-white" style="border-radius: 8px; border-color: var(--app-border) !important;">
      <form method="get" class="row g-2 align-items-end">
        <input type="hidden" name="tab" value="kartu">
        <div class="col-lg-4 col-md-6">
          <label class="form-label text-secondary small fw-semibold mb-1">Cari Nomor Rekening / Perangkat / PIC</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" class="form-control form-control-sm border-start-0" name="q_kartu" value="'.e($searchCard).'" placeholder="Ketik nomor rekening, nama barang, kode QR, lokasi...">
          </div>
        </div>
        <div class="col-lg-3 col-md-3 col-sm-6">
          <label class="form-label text-secondary small fw-semibold mb-1">Kantor Cabang</label>
          <select class="form-select form-select-sm" name="cabang_kartu">
            <option value="">Semua Cabang (01 - 05)</option>
            <option value="01"'.($cabangCard === '01' ? ' selected' : '').'>01 - Kantor Pusat ('.$cabangStats['01']['count'].')</option>
            <option value="02"'.($cabangCard === '02' ? ' selected' : '').'>02 - Batulicin ('.$cabangStats['02']['count'].')</option>
            <option value="03"'.($cabangCard === '03' ? ' selected' : '').'>03 - Martapura ('.$cabangStats['03']['count'].')</option>
            <option value="04"'.($cabangCard === '04' ? ' selected' : '').'>04 - Tanjung ('.$cabangStats['04']['count'].')</option>
            <option value="05"'.($cabangCard === '05' ? ' selected' : '').'>05 - Handil Bakti ('.$cabangStats['05']['count'].')</option>
          </select>
        </div>
        <div class="col-lg-2 col-md-3 col-sm-6">
          <label class="form-label text-secondary small fw-semibold mb-1">Tahun Perolehan</label>
          <select class="form-select form-select-sm" name="tahun_kartu">
            <option value="">Semua Tahun</option>
            '.$optTahunKartuHtml.'
          </select>
        </div>
        <div class="col-lg-3 col-md-12 d-flex gap-1 justify-content-lg-end">
          <a class="btn btn-sm '.($filterHariIni ? 'btn-success fw-bold' : 'btn-outline-success').' w-100" href="'.e(module_url('dashboard.php', ['tab' => 'kartu', 'hari_ini' => ($filterHariIni ? null : 1)])).'" title="Filter aset yang baru diperoleh / didaftarkan hari ini">
            <i class="bi bi-stars me-1"></i> Baru Hari Ini ('.$todayCardsCount.')
          </a>
        </div>
        <div class="col-12 d-flex justify-content-between align-items-center pt-2 border-top mt-2 flex-wrap gap-2">
          <div class="text-secondary small">
            Menampilkan <strong>'.count($filteredCards).'</strong> kartu dari total <strong>'.$totalCardsCount.'</strong> kartu inventaris terdaftar.
            '.($filterHariIni ? '<span class="badge bg-success-subtle text-success border border-success-subtle ms-1"><i class="bi bi-funnel me-1"></i>Filter Hari Ini Aktif</span>' : '').'
          </div>
          <div class="d-flex gap-2">
            <a class="btn btn-sm btn-light border px-3" href="'.e(module_url('dashboard.php', ['tab' => 'kartu'])).'"><i class="bi bi-x-circle me-1"></i> Reset</a>
            <button type="submit" class="btn btn-sm btn-primary px-3 fw-semibold"><i class="bi bi-filter me-1"></i> Terapkan</button>
          </div>
        </div>
      </form>
    </div>

    <!-- Table Container Card -->
    <div class="card mb-4 border shadow-sm bg-white overflow-hidden" style="border-radius: 8px; border-color: var(--app-border) !important;">
      <!-- Selection Action Bar (Muncul bila kartu dicentang, sama persis dengan Asset Registry) -->
      <div id="selectionCardBar" class="border-bottom px-4 py-2 d-none align-items-center justify-content-between flex-wrap gap-2" style="background-color: #EFF6FF !important;">
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-primary fs-6 px-2 py-1"><i class="bi bi-check-square me-1"></i> <span id="countSelectedText">0</span> Dipilih</span>
          <span class="text-secondary small fw-semibold">Aksi untuk kartu inventaris terpilih:</span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <button type="button" class="btn btn-sm btn-primary fw-semibold shadow-sm d-inline-flex align-items-center gap-1" id="btnPrintSelected" onclick="printSelectedCards()">
            <i class="bi bi-printer-fill"></i> Cetak Kartu Pilihan (<span id="countPrint">0</span>)
          </button>
          <button type="button" class="btn btn-sm btn-danger fw-semibold shadow-sm d-inline-flex align-items-center gap-1" id="btnDeleteSelected" onclick="bulkDeleteCards()">
            <i class="bi bi-trash"></i> Hapus Terpilih (<span id="countDelete">0</span>)
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" onclick="deselectAllCards()">
            <i class="bi bi-x-circle"></i> Batal
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light" style="border-bottom: 2px solid var(--app-border);">
            <tr>
              <th style="width: 44px;" class="text-center">
                <input class="form-check-input" type="checkbox" id="checkAllRows" onchange="toggleSelectAllCards(this)" title="Pilih Semua Kartu" style="cursor: pointer; width: 1.15rem; height: 1.15rem;">
              </th>
              <th style="width: 140px;">NOMOR REKENING</th>
              <th>NAMA BARANG</th>
              <th style="width: 170px;">TANGGAL PEROLEHAN</th>
              <th style="width: 180px;">NOMOR ASSET (GABUNGAN)</th>
              <th>KODE QR / BARCODE</th>
              <th>LOKASI</th>
              <th class="text-end" style="width: 150px;">AKSI</th>
            </tr>
          </thead>
          <tbody>
            '.$cardsTableRowsHtml.'
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white border-top py-2 px-3 d-flex justify-content-between align-items-center text-muted small">
        <div>Menampilkan <strong>'.count($filteredCards).'</strong> dari <strong>'.$totalCardsCount.'</strong> kartu inventaris terdaftar</div>
        <div class="font-monospace">Ukuran Standar Kartu: 85.6 × 54.0 mm</div>
      </div>
    </div>';

} else {
    // --- TAMPILAN TAB MONITORING (DENGAN WIDGET KARTU INVENTARIS TERPADU) ---
    $body .= '
    <!-- Branch Switcher Bar -->
    <div class="branch-nav-bar custom-scrollbar">
      '.$branchTabs.'
    </div>

    <!-- Operations Center Layout -->
    <div class="row g-4 mb-4">
      <!-- Left Column (8 cols): Branch Compliance Matrix & Activity Stream -->
      <div class="col-lg-8">
        <!-- Branch Compliance Matrix -->
        <div class="card mb-4 border shadow-sm bg-white" style="border-radius: 8px;">
          <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
            <div>
              <h2 class="h6 mb-0 fw-semibold text-dark"><i class="bi bi-buildings me-2 text-primary"></i>Kepatuhan Maintenance Per Cabang</h2>
              <div class="text-secondary small">Monitoring progres bulanan di masing-masing kantor kas & cabang</div>
            </div>
            <a href="'.e(module_url('print_report.php', ['bulan'=>$month,'tahun'=>$year])).'" target="_blank" class="btn btn-sm btn-light border"><i class="bi bi-printer me-1"></i> Cetak Rekap</a>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Kantor Cabang</th>
                  <th class="text-center">Target Unit</th>
                  <th class="text-center">Selesai</th>
                  <th class="text-center">Belum</th>
                  <th class="text-center">Temuan</th>
                  <th class="text-center">Kepatuhan</th>
                  <th class="text-end">Aksi</th>
                </tr>
              </thead>
              <tbody>
                '.$branchRowsHtml.'
              </tbody>
            </table>
          </div>
        </div>

        <!-- Integrated Kartu Inventaris Widget -->
        <div class="card mb-4 border shadow-sm bg-white" style="border-radius: 8px;">
          <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
              <div class="tech-label">INVENTORY REGISTRY</div>
              <h2 class="h6 mb-0 fw-semibold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-credit-card-2-front text-primary"></i> Kartu Inventaris & Label QR
                <span class="badge bg-primary text-white rounded-pill ms-1">'.$totalCardsCount.' Unit</span>
                '.($todayCardsCount > 0 ? '<span class="badge bg-success-subtle text-success border border-success-subtle ms-1">+'.$todayCardsCount.' Baru Hari Ini</span>' : '').'
              </h2>
            </div>
            <div class="d-flex gap-2">
              <a href="'.e(module_url('import_fbd3.php')).'" class="btn btn-sm btn-outline-success fw-semibold">
                <i class="bi bi-file-earmark-excel me-1"></i> Import FBD3
              </a>
              <a href="'.e(module_url('print_inventory_card.php', ['source'=>'inventaris_kartu'])).'" target="_blank" class="btn btn-sm btn-primary fw-semibold">
                <i class="bi bi-printer-fill me-1"></i> Cetak Massal (A4)
              </a>
              <a href="'.e(module_url('dashboard.php', ['tab' => 'kartu'])).'" class="btn btn-sm btn-outline-primary fw-semibold">
                <i class="bi bi-sliders me-1"></i> Kelola Lengkap &raquo;
              </a>
            </div>
          </div>
          <div class="px-4 py-2 bg-light border-bottom d-flex gap-2 flex-wrap align-items-center" style="font-size: 0.75rem;">
            <span class="text-muted fw-semibold me-1"><i class="bi bi-diagram-3 me-1"></i>Cabang:</span>
            '.$branchBadgesWidgetHtml.'
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>NO. REKENING</th>
                  <th>NAMA BARANG</th>
                  <th>TANGGAL</th>
                  <th>NOMOR ASSET</th>
                  <th>LOKASI</th>
                  <th class="text-end">AKSI</th>
                </tr>
              </thead>
              <tbody>
                '.$quickCardsRowsHtml.'
              </tbody>
            </table>
          </div>
        </div>

        <!-- Live Activity Stream -->
        <div class="card border shadow-sm bg-white" style="border-radius: 8px;">
          <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
            <div>
              <h2 class="h6 mb-0 fw-semibold text-dark"><i class="bi bi-activity me-2 text-primary"></i>Live Activity Stream</h2>
              <div class="text-secondary small">Log rekam jejak pemeriksaan maintenance dan audit terbaru</div>
            </div>
            <a href="'.e(module_url('audit.php')).'" class="btn btn-sm btn-light border">Lihat Semua Log</a>
          </div>
          <div class="card-body p-3 p-md-4">
            <div class="activity-stream">
              '.$activityStreamHtml.'
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column (4 cols): Compliance, Health & Findings -->
      <div class="col-lg-4">
        <!-- Maintenance Compliance Panel -->
        <div class="card mb-4 border shadow-sm bg-white" style="border-radius: 8px;">
          <div class="card-header bg-white border-bottom py-3 px-4">
            <div class="tech-label">INSPECTION COMPLIANCE</div>
            <h2 class="h6 mb-0 fw-semibold text-dark">Kepatuhan Periode Ini</h2>
          </div>
          <div class="card-body p-4">
            <div class="d-flex align-items-baseline justify-content-between mb-2">
              <span class="display-6 fw-bold text-dark">'.$percentDone.'%</span>
              <span class="small text-secondary fw-semibold">'.$totalDone.' dari '.$totalActive.' unit</span>
            </div>
            <div class="progress mb-3" style="height: 8px;">
              <div class="progress-bar '.($percentDone >= 80 ? 'bg-success' : ($percentDone >= 50 ? 'bg-primary' : 'bg-warning')).'" style="width: '.$percentDone.'%;"></div>
            </div>
            <div class="d-flex justify-content-between small text-secondary pt-1 border-top">
              <span><span class="status-dot operational me-1"></span> Selesai: <strong>'.$totalDone.'</strong></span>
              <span><span class="status-dot warning me-1"></span> Belum: <strong>'.$totalDue.'</strong></span>
              <span><span class="status-dot critical me-1"></span> Temuan: <strong>'.$totalUnresolvedFindings.'</strong></span>
            </div>
          </div>
        </div>

        <!-- Hardware Health Status -->
        <div class="card mb-4 border shadow-sm bg-white" style="border-radius: 8px;">
          <div class="card-header bg-white border-bottom py-3 px-4">
            <div class="tech-label">HARDWARE HEALTH</div>
            <h2 class="h6 mb-0 fw-semibold text-dark">Status Kondisi Perangkat</h2>
          </div>
          <div class="card-body p-4">
            <div class="d-flex flex-column gap-3">
              <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                  <span class="status-dot operational"></span>
                  <span class="small fw-semibold text-dark">Operasional Normal</span>
                </div>
                <span class="badge-chip chip-success">'.max(0, $totalAll - $totalBroken - $totalUnresolvedFindings).' Unit</span>
              </div>
              <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                  <span class="status-dot warning"></span>
                  <span class="small fw-semibold text-dark">Perlu Perhatian</span>
                </div>
                <span class="badge-chip chip-warning">'.$totalDue.' Unit</span>
              </div>
              <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                  <span class="status-dot critical"></span>
                  <span class="small fw-semibold text-dark">Temuan Kendala</span>
                </div>
                <span class="badge-chip chip-danger">'.$totalUnresolvedFindings.' Unit</span>
              </div>
              <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                  <span class="status-dot offline"></span>
                  <span class="small fw-semibold text-dark">Nonaktif / Rusak</span>
                </div>
                <span class="badge-chip chip-secondary">'.$totalBroken.' Unit</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Active Findings Panel -->
        <div class="card border shadow-sm bg-white" style="border-radius: 8px;">
          <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
            <div>
              <div class="tech-label">REPAIR & FINDINGS</div>
              <h2 class="h6 mb-0 fw-semibold text-dark">Temuan Masalah Aktif</h2>
            </div>
            <span class="badge bg-danger rounded-pill">'.$totalUnresolvedFindings.'</span>
          </div>
          <div class="card-body p-3">
            '.$findingsListHtml.'
          </div>
        </div>
      </div>
    </div>';
}

// =========================================================================
// 8. MODALS TERPADU UNTUK KARTU INVENTARIS CR80
// =========================================================================
$body .= '
<!-- MODAL: TAMBAH DATA KARTU INVENTARIS -->
<div class="modal fade" id="addCardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
      <form method="post">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="action" value="create_card">
        <div class="modal-header bg-primary text-white py-2 px-3">
          <h6 class="modal-title fw-bold d-flex align-items-center gap-2">
            <i class="bi bi-plus-circle-fill"></i> Tambah Kartu Inventaris
          </h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-3">
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Nomor Rekening</label>
            <input type="text" name="nomor_rekening" id="addCardRekening" class="form-control form-control-sm font-monospace" placeholder="Contoh: 01.05.0493" maxlength="11" required oninput="handleRekeningInput(this, event)">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Nama Barang</label>
            <input type="text" name="nama_barang" class="form-control form-control-sm" placeholder="Contoh: BANGUNAN GEDUNG KANTOR PUSA" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Tanggal Perolehan</label>
            <input type="text" name="tanggal_perolehan" id="addCardTanggal" class="form-control form-control-sm font-monospace" placeholder="dd/mm/yyyy (contoh: '.date('d/m/Y').')" value="'.date('d/m/Y').'" maxlength="10" required oninput="handleDateInput(this, event)">
            <div class="form-text text-muted" style="font-size: 0.72rem;">Format: <strong>dd/mm/yyyy</strong></div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Lokasi Barang</label>
            <input type="text" name="lokasi" id="addCardLokasi" class="form-control form-control-sm" placeholder="Contoh: Kantor Pusat / Operasional / Ruang IT" onkeydown="handleLokasiKeydown(event, this)" oninput="this.dataset.customized=\'true\'">
            <div class="form-text text-muted" style="font-size: 0.72rem;"><i class="bi bi-info-circle me-1"></i>Tekan <strong>Enter</strong> untuk otomatis menambahkan tanda <code> / </code></div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Kode QR / Barcode (Data QR Code)</label>
            <input type="text" name="barcode_data" class="form-control form-control-sm font-monospace" placeholder="Salin/tempel kode QR di sini">
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light">
          <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-sm btn-primary fw-semibold px-3"><i class="bi bi-save me-1"></i> Simpan Data</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: EDIT DATA KARTU INVENTARIS -->
<div class="modal fade" id="editCardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
      <form method="post">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="action" value="update_card">
        <input type="hidden" name="id" id="editCardId">
        <div class="modal-header bg-warning text-dark py-2 px-3">
          <h6 class="modal-title fw-bold d-flex align-items-center gap-2">
            <i class="bi bi-pencil-square"></i> Edit Kartu Inventaris
          </h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-3">
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Nomor Rekening</label>
            <input type="text" name="nomor_rekening" id="editCardRekening" class="form-control form-control-sm font-monospace" placeholder="Contoh: 01.05.0493" maxlength="11" required oninput="handleRekeningInput(this, event)">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Nama Barang</label>
            <input type="text" name="nama_barang" id="editCardNama" class="form-control form-control-sm" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Tanggal Perolehan</label>
            <input type="text" name="tanggal_perolehan" id="editCardTanggal" class="form-control form-control-sm font-monospace" placeholder="dd/mm/yyyy" maxlength="10" required oninput="handleDateInput(this, event)">
            <div class="form-text text-muted" style="font-size: 0.72rem;">Format: <strong>dd/mm/yyyy</strong></div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Lokasi Barang</label>
            <input type="text" name="lokasi" id="editCardLokasi" class="form-control form-control-sm" placeholder="Contoh: Kantor Pusat / Operasional / Ruang IT" onkeydown="handleLokasiKeydown(event, this)">
            <div class="form-text text-muted" style="font-size: 0.72rem;"><i class="bi bi-info-circle me-1"></i>Tekan <strong>Enter</strong> untuk otomatis menambahkan tanda <code> / </code></div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Kode QR / Barcode (Data QR Code)</label>
            <input type="text" name="barcode_data" id="editCardBarcode" class="form-control form-control-sm font-monospace" placeholder="Salin/tempel kode QR di sini">
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light">
          <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-sm btn-primary fw-semibold px-3"><i class="bi bi-check-lg me-1"></i> Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: PILIH DARI ASET IT (IMPORT CATALOG) -->
<div class="modal fade" id="importAssetModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
      <form method="post">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="action" value="import_from_assets">
        <div class="modal-header bg-primary text-white py-2 px-3">
          <h6 class="modal-title fw-bold d-flex align-items-center gap-2">
            <i class="bi bi-box-seam"></i> Pilih Perangkat dari Katalog Aset IT
          </h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-3">
          <p class="small text-muted mb-2">Pilih perangkat di bawah ini untuk didaftarkan ke tabel Kartu Inventaris:</p>
          <div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 36px;"></th>
                  <th>Kode Inventaris</th>
                  <th>Nama Perangkat</th>
                  <th>Pengguna</th>
                  <th>Cabang</th>
                </tr>
              </thead>
              <tbody>';
foreach ($rawAssetsForModal as $a) {
    $aid = (int)($a['id'] ?? 0);
    $body .= '
                <tr>
                  <td class="text-center">
                    <input class="form-check-input" type="checkbox" name="asset_ids[]" value="'.$aid.'">
                  </td>
                  <td><span class="font-monospace fw-bold text-primary small">'.e($a['kode_inventaris'] ?? ('ASET-' . $aid)).'</span></td>
                  <td><span class="fw-semibold text-dark small">'.e(asset_title($a)).'</span></td>
                  <td><span class="text-muted small">'.e($a['karyawan_nama'] ?? '-').'</span></td>
                  <td><span class="badge-chip chip-secondary">'.e($a['cabang_nama'] ?? 'KPO').'</span></td>
                </tr>';
}
$body .= '
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light">
          <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-sm btn-primary fw-semibold px-3"><i class="bi bi-box-arrow-in-down me-1"></i> Impor ke Kartu Inventaris</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: PRATINJAU DESAIN FISIK KARTU -->
<div class="modal fade" id="previewCardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 540px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-header bg-primary text-white py-2 px-3">
        <h6 class="modal-title fw-bold d-flex align-items-center gap-2">
          <i class="bi bi-eye-fill"></i> Pratinjau Desain Kartu Fisik
        </h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center bg-light p-4" style="overflow-x: auto;">
        <!-- Container Skala Pratinjau -->
        <div style="display: inline-block; transform: scale(1.25); transform-origin: top center; margin-bottom: 22mm;">
          <div class="atm-card" style="text-align: left;">
            <!-- Top Header -->
            <div class="card-header-sec">
              <div class="header-logo-box">
                <img src="'.e($logoUri).'" alt="Logo Bank Mitra" onerror="this.src=\'logo.png\'">
              </div>
              <div class="header-title-box">
                <div class="header-main-title">PT BPR MITRATAMA ARTHABUANA</div>
                <div class="header-sub-sec">
                  <div class="green-banner-wrapper">
                    <div class="teal-stripe"></div>
                    <div class="green-banner">ASSET TETAP</div>
                  </div>
                  <div class="header-dots-container">
                    <div class="header-dots">
                      <span></span><span></span><span></span>
                      <span></span><span></span><span></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Data Fields Section -->
            <div class="card-fields-sec">
              <!-- 1. Nomor Asset -->
              <div class="card-field-row">
                <div class="field-icon-box">
                  <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M2 2a1 1 0 0 1 1-1h4.586a1 1 0 0 1 .707.293l7 7a1 1 0 0 1 0 1.414l-4.586 4.586a1 1 0 0 1-1.414 0l-7-7A1 1 0 0 1 2 8.586V2zm3.5 3.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/></svg>
                </div>
                <div class="field-content-box">
                  <span class="field-lbl">NOMOR ASSET</span>
                  <span class="field-sep">|</span>
                  <span class="field-val" id="pvNomorAsset">0105049326092026</span>
                </div>
              </div>
              <!-- 2. Nama Asset -->
              <div class="card-field-row">
                <div class="field-icon-box">
                  <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M12 1H4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zM4 2h8a1 1 0 0 1 1 1v7H3V3a1 1 0 0 1 1-1z"/><path d="M8 12a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/></svg>
                </div>
                <div class="field-content-box">
                  <span class="field-lbl">NAMA ASSET</span>
                  <span class="field-sep">|</span>
                  <span class="field-val" id="pvNamaAsset">PRINTER EPSON L3211 KAS</span>
                </div>
              </div>
              <!-- 3. Tgl Perolehan -->
              <div class="card-field-row">
                <div class="field-icon-box">
                  <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/></svg>
                </div>
                <div class="field-content-box">
                  <span class="field-lbl">TGL PEROLEHAN</span>
                  <span class="field-sep">|</span>
                  <span class="field-val" id="pvTglPerolehan">26/09/2026</span>
                </div>
              </div>
              <!-- 4. Lokasi -->
              <div class="card-field-row">
                <div class="field-icon-box">
                  <svg viewBox="0 0 16 16" fill="currentColor" class="field-svg-icon"><path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/></svg>
                </div>
                <div class="field-content-box">
                  <span class="field-lbl">LOKASI</span>
                  <span class="field-sep">|</span>
                  <span class="field-val" id="pvLokasi">KPO</span>
                </div>
              </div>
            </div>

            <!-- Bottom Section -->
            <div class="card-bottom-sec">
              <!-- Background Waves SVG -->
              <div class="card-waves">
                <svg viewBox="0 0 85.6 7.5" preserveAspectRatio="none" style="width: 100%; height: 100%; display: block;">
                  <path d="M 0 3 C 8 2.5, 18 5, 24 7.5 L 0 7.5 Z" fill="#7ac142" />
                  <path d="M 5 7.5 Q 32 3, 58 6.5 T 85.6 3.5 L 85.6 7.5 Z" fill="#003b73" />
                </svg>
              </div>
              <!-- Left: Attention Disclaimer -->
              <div class="bottom-left-attention">
                <div class="attention-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="#003b73" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="attention-svg-icon">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    <line x1="12" y1="8" x2="12" y2="13" />
                    <line x1="12" y1="16.5" x2="12.01" y2="16.5" stroke-width="3" />
                  </svg>
                </div>
                <div class="attention-text-box">
                  <div class="attention-title">Perhatian</div>
                  <div class="attention-desc">Dilarang memindahkan barang inventaris ini tanpa seizin Human Resource Departement (HRD) Bank Mitra</div>
                </div>
              </div>
              <!-- Separator Line -->
              <div class="attention-qr-separator"></div>
              <!-- Right: QR Code -->
              <div class="bottom-right-qr">
                <div class="qr-border-box">
                  <div id="pvQrBox" class="card-qr-img"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer py-2 px-3 bg-white justify-content-between">
        <a href="kartu_template.html" target="_blank" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-filetype-html me-1"></i> Buka File Template (kartu_template.html)
        </a>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
          <a id="pvPrintDirectBtn" href="#" target="_blank" class="btn btn-sm btn-primary fw-bold">
            <i class="bi bi-printer-fill me-1"></i> Cetak Kartu Ini
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Forms Hidden untuk Hapus Satuan & Hapus Massal -->
<form id="deleteCardForm" method="post" style="display: none;">
  <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
  <input type="hidden" name="action" value="delete_card">
  <input type="hidden" name="id" id="deleteTargetCardId">
</form>

<form id="deleteBatchCardForm" method="post" style="display: none;">
  <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
  <input type="hidden" name="action" value="delete_batch_card">
  <input type="hidden" name="ids" id="deleteBatchCardIds">
</form>';

// =========================================================================
// 9. JAVASCRIPT LOGIC
// =========================================================================
$extraScript = '
<script>
function getSelectedCardIds() {
  const cbs = document.querySelectorAll(".card-checkbox:checked");
  const ids = [];
  cbs.forEach(cb => ids.push(cb.value));
  return ids;
}

function updateCardCounters() {
  const ids = getSelectedCardIds();
  const count = ids.length;
  const countDelSpan = document.getElementById("countDelete");
  const countPrintSpan = document.getElementById("countPrint");
  const countSelectedText = document.getElementById("countSelectedText");
  const selBar = document.getElementById("selectionCardBar");

  if (countDelSpan) countDelSpan.innerText = count;
  if (countPrintSpan) countPrintSpan.innerText = count;
  if (countSelectedText) countSelectedText.innerText = count;

  if (selBar) {
    if (count > 0) {
      selBar.classList.remove("d-none");
      selBar.classList.add("d-flex");
    } else {
      selBar.classList.add("d-none");
      selBar.classList.remove("d-flex");
    }
  }

  const checkAll = document.getElementById("checkAllRows");
  const rowChecks = document.querySelectorAll(".card-checkbox");
  if (checkAll && rowChecks.length > 0) {
    checkAll.checked = count === rowChecks.length;
    checkAll.indeterminate = count > 0 && count < rowChecks.length;
  }
}

function toggleSelectAllCards(masterCb) {
  const cbs = document.querySelectorAll(".card-checkbox");
  cbs.forEach(cb => cb.checked = masterCb.checked);
  updateCardCounters();
}

function deselectAllCards() {
  const checkAll = document.getElementById("checkAllRows");
  if (checkAll) {
    checkAll.checked = false;
    checkAll.indeterminate = false;
  }
  document.querySelectorAll(".card-checkbox").forEach(cb => cb.checked = false);
  updateCardCounters();
}

function printSelectedCards() {
  const ids = getSelectedCardIds();
  let targetUrl = "print_inventory_card.php?source=inventaris_kartu";
  if (ids.length > 0) {
    targetUrl += "&ids=" + encodeURIComponent(ids.join(","));
  }
  window.open(targetUrl, "_blank");
}

function bulkDeleteCards() {
  const ids = getSelectedCardIds();
  if (ids.length === 0) return;
  if (confirm("Hapus " + ids.length + " data kartu inventaris terpilih?")) {
    document.getElementById("deleteBatchCardIds").value = ids.join(",");
    document.getElementById("deleteBatchCardForm").submit();
  }
}

function confirmDeleteCard(id, label) {
  if (confirm("Hapus kartu inventaris: " + label + "?")) {
    document.getElementById("deleteTargetCardId").value = id;
    document.getElementById("deleteCardForm").submit();
  }
}

function handleRekeningInput(el, event) {
  if (!el) return;
  const isDelete = event && event.inputType && event.inputType.startsWith("delete");
  let digits = el.value.replace(/[^0-9]/g, "");

  if (digits.length === 0) {
    el.value = "";
    return;
  }

  let p0 = digits.slice(0, 2);
  let p1 = digits.slice(2, 4);
  let p2 = digits.slice(4, 9); // Mendukung 4 hingga 5 digit di belakang (contoh: 01.05.0493 atau 01.05.00354)

  if (digits.length < 2) {
    el.value = digits;
  } else if (digits.length === 2) {
    el.value = isDelete ? p0 : p0 + ".";
  } else if (digits.length < 4) {
    el.value = p0 + "." + digits.slice(2);
  } else if (digits.length === 4) {
    el.value = isDelete ? (p0 + "." + p1) : (p0 + "." + p1 + ".");
  } else {
    el.value = p0 + "." + p1 + "." + p2;
  }

  if (p0.length === 2) {
    autoSuggestBranchLokasi(p0);
  }
}

function handleDateInput(el, event) {
  if (!el) return;
  const isDelete = event && event.inputType && event.inputType.startsWith("delete");
  let digits = el.value.replace(/[^0-9]/g, "");
  if (digits.length === 0) {
    el.value = "";
    return;
  }
  let d = digits.slice(0, 2);
  let m = digits.slice(2, 4);
  let y = digits.slice(4, 8);

  if (digits.length < 2) {
    el.value = digits;
  } else if (digits.length === 2) {
    el.value = isDelete ? d : d + "/";
  } else if (digits.length < 4) {
    el.value = d + "/" + digits.slice(2);
  } else if (digits.length === 4) {
    el.value = isDelete ? (d + "/" + m) : (d + "/" + m + "/");
  } else {
    el.value = d + "/" + m + "/" + y;
  }
}

function autoSuggestBranchLokasi(code) {
  const addLok = document.getElementById("addCardLokasi");
  if (!addLok || addLok.dataset.customized === "true") return;
  const map = {
    "01": "Kantor Pusat / ",
    "02": "Batulicin / ",
    "03": "Martapura / ",
    "04": "Tanjung / ",
    "05": "Handil Bakti / "
  };
  if (map[code]) {
    addLok.value = map[code];
  }
}

function handleLokasiKeydown(e, el) {
  if (!e || !el) return;
  if (e.key === "Enter") {
    e.preventDefault();
    el.dataset.customized = "true";
    const start = el.selectionStart !== null ? el.selectionStart : el.value.length;
    const end = el.selectionEnd !== null ? el.selectionEnd : el.value.length;
    const val = el.value;
    const before = val.substring(0, start);
    const after = val.substring(end);

    if (before.trimEnd().endsWith("/")) {
      if (!before.endsWith(" ")) {
        el.value = before + " " + after;
        el.selectionStart = el.selectionEnd = start + 1;
      }
      return;
    }

    let insert = " / ";
    if (before.length === 0) {
      insert = "/ ";
    } else if (before.endsWith(" ")) {
      insert = "/ ";
    }

    el.value = before + insert + after;
    const newPos = start + insert.length;
    el.selectionStart = el.selectionEnd = newPos;
  }
}

function openCardEditModal(card) {
  document.getElementById("editCardId").value = card.id || "";
  document.getElementById("editCardRekening").value = card.nomor_rekening || "";
  document.getElementById("editCardNama").value = card.nama_barang || "";

  let rawDate = (card.tanggal_perolehan || "").trim();
  let formattedDate = rawDate;
  if (rawDate.includes("-")) {
    const parts = rawDate.split("-");
    if (parts.length === 3) {
      formattedDate = `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
  }
  document.getElementById("editCardTanggal").value = formattedDate;
  document.getElementById("editCardLokasi").value = card.lokasi || "";
  document.getElementById("editCardBarcode").value = card.barcode_data || "";

  const modal = new bootstrap.Modal(document.getElementById("editCardModal"));
  modal.show();
}

function openCardPreviewModal(card) {
  const rek = card.nomor_rekening || "";
  const tglRaw = (card.tanggal_perolehan || "").trim();
  
  // Format DD/MM/YYYY
  let tglDisplay = "-";
  if (tglRaw) {
    if (tglRaw.includes("/")) {
      tglDisplay = tglRaw;
    } else if (tglRaw.includes("-")) {
      const parts = tglRaw.split("-");
      if (parts.length === 3) {
        tglDisplay = `${parts[2]}/${parts[1]}/${parts[0]}`;
      } else {
        tglDisplay = tglRaw;
      }
    } else {
      tglDisplay = tglRaw;
    }
  }

  document.getElementById("pvNomorAsset").innerText = card.gabungan || rek;
  document.getElementById("pvNamaAsset").innerText = card.nama_barang || "-";
  document.getElementById("pvTglPerolehan").innerText = tglDisplay;
  document.getElementById("pvLokasi").innerText = card.lokasi || "KPO";

  const qrTarget = card.barcode_data || window.location.origin;
  const qrBox = document.getElementById("pvQrBox");
  qrBox.innerHTML = "";
  new QRCode(qrBox, {
    text: qrTarget,
    width: 70,
    height: 70,
    colorDark: "#000000",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.M
  });

  const printBtn = document.getElementById("pvPrintDirectBtn");
  if (printBtn) {
    printBtn.href = "print_inventory_card.php?source=inventaris_kartu&id=" + (card.id || "");
  }

  const modal = new bootstrap.Modal(document.getElementById("previewCardModal"));
  modal.show();
}
</script>';

render_page('Dashboard IT Operations', $body, $head, $extraScript);
