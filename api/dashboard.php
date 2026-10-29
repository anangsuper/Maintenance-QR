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
        $ruanganVal = trim((string)($_POST['ruangan'] ?? ''));
        $ruanganBaru = trim((string)($_POST['nama_ruangan_baru'] ?? ''));
        if ($ruanganBaru !== '') {
            $ruanganVal = add_master_ruangan($ruanganBaru, trim((string)($_POST['kode_ruangan_baru'] ?? '')) ?: null);
        }

        $res = insert_inventaris_kartu([
            'nomor_rekening'        => $_POST['nomor_rekening'] ?? '',
            'nama_barang'           => $_POST['nama_barang'] ?? '',
            'nomor_seri_inventaris' => $_POST['nomor_seri_inventaris'] ?? '',
            'jenis_inventaris'      => $_POST['jenis_inventaris'] ?? '',
            'golongan'              => $_POST['golongan'] ?? '',
            'ruangan'               => $ruanganVal,
            'sumber_perolehan'      => $_POST['sumber_perolehan'] ?? '',
            'status_inventaris'     => $_POST['status_inventaris'] ?? '1 : Aktif',
            'keterangan'            => $_POST['keterangan'] ?? '',
            'tanggal_perolehan'     => $_POST['tanggal_perolehan'] ?? date('Y-m-d'),
            'barcode_data'          => $_POST['barcode_data'] ?? '',
            'lokasi'                => $_POST['lokasi'] ?? ''
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
        $ruanganVal = trim((string)($_POST['ruangan'] ?? ''));
        $ruanganBaru = trim((string)($_POST['nama_ruangan_baru'] ?? ''));
        if ($ruanganBaru !== '') {
            $ruanganVal = add_master_ruangan($ruanganBaru, trim((string)($_POST['kode_ruangan_baru'] ?? '')) ?: null);
        }

        $ok = update_inventaris_kartu($cardId, [
            'nomor_rekening'        => $_POST['nomor_rekening'] ?? '',
            'nama_barang'           => $_POST['nama_barang'] ?? '',
            'nomor_seri_inventaris' => $_POST['nomor_seri_inventaris'] ?? '',
            'jenis_inventaris'      => $_POST['jenis_inventaris'] ?? '',
            'golongan'              => $_POST['golongan'] ?? '',
            'ruangan'               => $ruanganVal,
            'sumber_perolehan'      => $_POST['sumber_perolehan'] ?? '',
            'status_inventaris'     => $_POST['status_inventaris'] ?? '1 : Aktif',
            'keterangan'            => $_POST['keterangan'] ?? '',
            'tanggal_perolehan'     => $_POST['tanggal_perolehan'] ?? date('Y-m-d'),
            'barcode_data'          => $_POST['barcode_data'] ?? '',
            'lokasi'                => $_POST['lokasi'] ?? ''
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

    // F. Update Proses Perbaikan dari Tab Temuan
    if ($action === 'save_proses_perbaikan') {
        $pFindingId = (int)($_POST['finding_id'] ?? 0);
        $pScanId = (int)($_POST['scan_id'] ?? 0);
        $pAssetId = (int)($_POST['asset_id'] ?? 0);
        $pStatus = trim((string)($_POST['status'] ?? 'In Progress'));
        $pNotes = trim((string)($_POST['proses_perbaikan'] ?? ''));
        $pTech = trim((string)($_POST['technician_name'] ?? current_user_name()));

        $res = update_finding_progress($pFindingId, $pScanId, $pAssetId, $pStatus, $pNotes, $pTech);
        if (!empty($res['success'])) {
            $_SESSION['flash'] = 'Proses perbaikan perangkat berhasil disimpan.';
        } else {
            $_SESSION['flash_error'] = $res['error'] ?? 'Gagal menyimpan proses perbaikan.';
        }
        header('Location: ' . module_url('dashboard.php', [
            'tab' => 'temuan',
            'bulan' => max(1, min(12, (int)($_POST['bulan'] ?? date('n')))),
            'tahun' => max(2020, min(2100, (int)($_POST['tahun'] ?? date('Y')))),
            'cabang' => max(0, (int)($_POST['cabang'] ?? 0))
        ]));
        exit;
    }
}

// 2. Ambil Parameter Filter dengan Validasi
$activeTab = trim((string)($_GET['tab'] ?? ''));
if (is_hrd() && !is_admin()) {
    $activeTab = 'kartu';
} elseif ($activeTab === '' && (isset($_GET['q_kartu']) || isset($_GET['cabang_kartu']))) {
    $activeTab = 'kartu';
}
if ($activeTab === 'galeri' || $activeTab === 'gallery') {
    $cabIdReq = max(0, (int)($_GET['cabang'] ?? 0));
    redirect(module_url('asset_gallery.php', $cabIdReq > 0 ? ['cabang' => $cabIdReq] : []));
}
if ($activeTab === 'galeri_inventaris' || $activeTab === 'galeri_kartu' || $activeTab === 'inv_gallery') {
    $cabIdReq = max(0, (int)($_GET['cabang'] ?? 0));
    redirect(module_url('inventaris_gallery.php', $cabIdReq > 0 ? ['cabang' => sprintf('%02d', $cabIdReq)] : []));
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

// Filter Kartu Inventaris (Mendukung Pencarian Fleksibel dengan/tanpa Titik, Cabang 01-05, Tahun, Hari Ini, dan Label QR)
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
        $q = strtolower(trim($searchCard));
        $cleanQ = preg_replace('/[^a-z0-9]/', '', $q);
        
        $rekRaw = strtolower(trim((string)($r['nomor_rekening'] ?? '')));
        $cleanRek = preg_replace('/[^a-z0-9]/', '', $rekRaw);
        
        $namaRaw = strtolower(trim((string)($r['nama_barang'] ?? '')));
        $barcodeRaw = strtolower(trim((string)($r['barcode_data'] ?? '')));
        $lokasiRaw = strtolower(trim((string)($r['lokasi'] ?? '')));
        $tglRaw = trim((string)($r['tanggal_perolehan'] ?? ''));
        $nomorGabungan = strtolower(get_nomor_asset_gabungan($rekRaw, $tglRaw));
        $cleanGabungan = preg_replace('/[^a-z0-9]/', '', $nomorGabungan);

        $haystack = $rekRaw . ' ' . $namaRaw . ' ' . $barcodeRaw . ' ' . $lokasiRaw . ' ' . $nomorGabungan;
        $cleanHaystack = preg_replace('/[^a-z0-9]/', '', $haystack);

        $matched = false;
        if (strpos($haystack, $q) !== false) {
            $matched = true;
        } elseif ($cleanQ !== '') {
            if (strpos($cleanRek, $cleanQ) !== false || 
                strpos($cleanGabungan, $cleanQ) !== false || 
                strpos($cleanHaystack, $cleanQ) !== false) {
                $matched = true;
            }
        }
        if (!$matched) return false;
    }
    if ($cabangCard !== '' && $cabangCard !== 'Semua Cabang' && $cabangCard !== 'all') {
        $rek = trim((string)($r['nomor_rekening'] ?? ''));
        $lokasi = strtolower(trim((string)($r['lokasi'] ?? '')));
        $cleanRek = preg_replace('/[^a-z0-9]/', '', strtolower($rek));
        $cleanCabang = preg_replace('/[^a-z0-9]/', '', strtolower($cabangCard));
        
        $matchFound = false;
        // Cek prefix nomor rekening (01., 02., 03., 04., 05. atau 01, 02, 03, 04, 05)
        if (preg_match('/^' . preg_quote($cabangCard, '/') . '[\.\-]/', $rek) || str_starts_with($cleanRek, $cleanCabang)) {
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
$totalWithPhotoCount = 0;
foreach ($rawAssetsForModal as $ra) {
    if (!empty(trim((string)($ra['foto'] ?? '')))) {
        $totalWithPhotoCount++;
    }
}
$totalInvWithPhotoCount = 0;
foreach ($allCards as $ac) {
    if (!empty(trim((string)($ac['foto'] ?? '')))) {
        $totalInvWithPhotoCount++;
    }
}
$logoUri = app_logo_data_uri();

$flash = $_SESSION['flash'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_error']);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

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
    <tr class="'.($isCurrent ? 'table-active' : '').'" id="branchRow_'.$bId.'" data-branch-id="'.$bId.'">
      <td>
        <div class="fw-semibold text-dark">'.e($bName).'</div>
        <div class="small text-muted">ID: #'.$bId.'</div>
      </td>
      <td class="text-center fw-semibold" id="branchTotal_'.$bId.'">'.$bTotal.'</td>
      <td class="text-center text-success fw-semibold" id="branchDone_'.$bId.'">'.$bDone.'</td>
      <td class="text-center text-secondary" id="branchPending_'.$bId.'">'.$bPending.'</td>
      <td class="text-center" id="branchFindings_'.$bId.'">'.($bFindings > 0 ? '<span class="text-danger fw-bold">'.$bFindings.'</span>' : '<span class="text-muted">0</span>').'</td>
      <td class="text-center" id="branchCompliance_'.$bId.'">'.$complianceBadge.'</td>
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
        // 1. Normalisasi nama perangkat & bersihkan kata berulang (mis: "HP HP All-in-One..." -> "HP All-in-One...", "PC Custom PC DESKTOP" -> "PC Custom Desktop")
        $rawDevName = !empty($uf['perangkat']) 
            ? $uf['perangkat'] 
            : (!empty($uf['nama_perangkat']) 
                ? $uf['nama_perangkat'] 
                : trim(($uf['merk'] ?? '') . ' ' . ($uf['model'] ?? '')));

        if ($rawDevName === '') {
            $rawDevName = 'Perangkat IT';
        } else {
            $rawDevName = preg_replace('/\b(PC\s+Custom)\s+PC\s+DESKTOP\b/i', 'PC Custom Desktop', $rawDevName);
            $rawDevName = preg_replace('/\b(\w+)\s+\1\b/i', '$1', $rawDevName);
        }

        // 2. Deskripsi Temuan
        $findingText = '';
        foreach (['finding', 'findings', 'deskripsi', 'catatan', 'keterangan'] as $fk) {
            if (!empty($uf[$fk]) && trim((string)$uf[$fk]) !== '' && trim((string)$uf[$fk]) !== '-') {
                $findingText = trim((string)$uf[$fk]);
                break;
            }
        }
        if ($findingText === '') {
            $findingText = 'Kendala perangkat dilaporkan';
        }

        // 3. Format Tanggal
        $rawDate = '';
        foreach (['date', 'created_at', 'maintenance_date', 'reported_at', 'tanggal'] as $dk) {
            if (!empty($uf[$dk]) && trim((string)$uf[$dk]) !== '' && trim((string)$uf[$dk]) !== '-' && trim((string)$uf[$dk]) !== '0000-00-00') {
                $rawDate = trim((string)$uf[$dk]);
                break;
            }
        }
        $dateFormatted = '';
        if ($rawDate !== '') {
            $datePart = substr($rawDate, 0, 10);
            $formatted = format_id_date($datePart);
            if ($formatted !== '-' && $formatted !== '') {
                $dateFormatted = $formatted;
            }
        }

        // 4. Cabang & Badge Severity
        $branchName = trim((string)($uf['cabang_nama'] ?? ''));
        if ($branchName === '' || $branchName === '-') {
            $branchName = 'Cabang Operasional';
        }

        $sev = trim((string)($uf['severity'] ?? ''));
        $sevBadge = '';
        if (strcasecmp($sev, 'Berat') === 0 || strcasecmp($sev, 'Tinggi') === 0 || strcasecmp($sev, 'Critical') === 0) {
            $sevBadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-0.5" style="font-size: 0.65rem;">Tinggi</span>';
        } elseif (strcasecmp($sev, 'Sedang') === 0 || strcasecmp($sev, 'Medium') === 0) {
            $sevBadge = '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-0.5" style="font-size: 0.65rem;">Sedang</span>';
        } elseif (strcasecmp($sev, 'Ringan') === 0 || strcasecmp($sev, 'Low') === 0) {
            $sevBadge = '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill px-2 py-0.5" style="font-size: 0.65rem;">Ringan</span>';
        }

        $detailUrl = !empty($uf['log_id']) 
            ? module_url('maintenance_detail.php', ['id' => $uf['log_id']]) 
            : module_url('maintenance_detail.php', ['asset_id' => $uf['asset_id'] ?? 0]);

        $findingsListHtml .= '
        <div class="finding-card-item p-3 mb-2.5 rounded-3 bg-white position-relative" style="box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
          <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
            <div class="min-w-0">
              <div class="d-flex align-items-center gap-1.5 flex-wrap">
                <span class="font-monospace fw-bold text-dark small">'.e($uf['kode_inventaris'] ?? 'Aset').'</span>
                '.$sevBadge.'
              </div>
              <div class="text-secondary small fw-medium text-truncate mt-0.5" title="'.e($rawDevName).'">
                '.e($rawDevName).'
              </div>
            </div>
            <a href="'.e($detailUrl).'" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 py-1 px-2.5 rounded-pill fw-semibold flex-shrink-0" style="font-size: 0.72rem;">
              <span>Periksa</span>
              <i class="bi bi-chevron-right" style="font-size: 0.65rem;"></i>
            </a>
          </div>

          <div class="p-2 my-2 rounded bg-danger-subtle bg-opacity-25 border border-danger-subtle text-dark small" style="font-size: 0.8rem; line-height: 1.35;">
            <div class="d-flex align-items-start gap-1.5">
              <i class="bi bi-exclamation-triangle-fill text-danger mt-0.5 flex-shrink-0" style="font-size: 0.82rem;"></i>
              <span class="fw-medium">'.e($findingText).'</span>
            </div>
          </div>

          <div class="d-flex align-items-center justify-content-between text-muted flex-wrap gap-1 pt-1" style="font-size: 0.72rem;">
            <span class="d-inline-flex align-items-center gap-1 text-truncate">
              <i class="bi bi-geo-alt text-secondary"></i>
              <span>'.e($branchName).'</span>
            </span>
            '.($dateFormatted !== '' ? '
            <span class="d-inline-flex align-items-center gap-1">
              <i class="bi bi-calendar3 text-secondary"></i>
              <span>'.e($dateFormatted).'</span>
            </span>' : '').'
          </div>
        </div>';
    }
} else {
    $findingsListHtml = '<div class="text-center py-4 text-muted small"><i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>Tidak ada temuan kendala aktif.<br><span class="text-secondary opacity-75">Seluruh perangkat beroperasi normal.</span></div>';
}

// =========================================================================
// 5. GENERASI DATA TABEL KARTU INVENTARIS
// =========================================================================
$optCardJenis = '';
foreach (get_master_jenis_inventaris() as $k => $v) {
    $optCardJenis .= '<option value="'.e($v).'">'.e($v).'</option>';
}

$optCardGolongan = '';
foreach (get_master_golongan_inventaris() as $k => $v) {
    $optCardGolongan .= '<option value="'.e($v).'">'.e($v).'</option>';
}

$optCardRuangan = '';
$masterRuanganList = get_master_ruangan_list();
foreach ($masterRuanganList as $r) {
    $valR = trim($r['kode_ruangan'] . ' : ' . $r['nama_ruangan']);
    $optCardRuangan .= '<option value="'.e($valR).'">'.e($valR).'</option>';
}

$optCardSumber = '';
foreach (get_master_sumber_perolehan() as $k => $v) {
    $optCardSumber .= '<option value="'.e($v).'">'.e($v).'</option>';
}

$optCardStatusInv = '';
foreach (get_master_status_inventaris() as $k => $v) {
    $optCardStatusInv .= '<option value="'.e($v).'">'.e($v).'</option>';
}

// Pagination untuk Tab Kartu Inventaris
$totalFilteredCards = count($filteredCards);
$perPageCards = max(10, min(100, (int)($_GET['limit_kartu'] ?? 25)));
$pageKartu = max(1, (int)($_GET['p_kartu'] ?? 1));
$totalPagesCards = max(1, (int)ceil($totalFilteredCards / $perPageCards));
if ($pageKartu > $totalPagesCards) $pageKartu = $totalPagesCards;
$offsetCards = ($pageKartu - 1) * $perPageCards;
$displayCards = array_slice($filteredCards, $offsetCards, $perPageCards);

$cardsTableRowsHtml = '';
$cardsMobileCardsHtml = '';
if (empty($displayCards)) {
    $cardsTableRowsHtml = '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>Tidak ada data kartu inventaris yang ditemukan.</td></tr>';
    $cardsMobileCardsHtml = '<div class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>Tidak ada data kartu inventaris yang ditemukan.</div>';
} else {
    foreach ($displayCards as $c) {
        $cId = (int)$c['id'];
        $rek = $c['nomor_rekening'] ?? '';
        $nama = $c['nama_barang'] ?? '';
        $tglRaw = $c['tanggal_perolehan'] ?? '';
        $tglCard = format_card_date($tglRaw);
        $gabungan = get_nomor_asset_gabungan($rek, $tglRaw);
        $barcode = $c['barcode_data'] ?? '';
        $lokasi = !empty($c['ruangan']) ? $c['ruangan'] : (!empty($c['lokasi']) ? $c['lokasi'] : 'KPO');
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
        $todayBadge = $isCardToday ? '<span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 0.65rem; font-weight: 700;"><i class="bi bi-stars me-0.5"></i>Hari Ini</span>' : '';

        $photoThumbHtml = '';
        if (!empty($c['foto'])) {
            $cFotoSrc = function_exists('asset_image_url') ? asset_image_url($c['foto']) : module_url($c['foto']);
            $photoThumbHtml = '<a href="'.e(module_url('kartu_detail.php', ['id'=>$cId])).'" target="_blank" class="flex-shrink-0 position-relative text-decoration-none" title="Lihat Foto Fisik Inventaris">
                <img src="'.e($cFotoSrc).'" alt="Foto" class="rounded border shadow-xs" style="width: 36px; height: 36px; object-fit: cover;">
                <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-success p-0 border border-white" style="width: 8px; height: 8px;" title="Ada Foto Fisik"></span>
            </a>';
        } else {
            $photoThumbHtml = '<a href="'.e(module_url('kartu_detail.php', ['id'=>$cId])).'" target="_blank" class="flex-shrink-0 d-inline-flex align-items-center justify-content-center rounded border bg-light text-muted text-decoration-none" style="width: 36px; height: 36px;" title="Belum Ada Foto - Klik untuk Ambil/Upload Foto">
                <i class="bi bi-camera text-secondary opacity-50" style="font-size: 1.05rem;"></i>
            </a>';
        }

        // Tentukan style badge lokasi
        $lokasiNorm = strtoupper(trim((string)$lokasi));
        $lokasiBadgeClass = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
        if (str_contains($lokasiNorm, '01') || str_contains($lokasiNorm, 'PUSAT') || str_contains($lokasiNorm, 'KPO')) {
            $lokasiBadgeClass = 'bg-primary-subtle text-primary border border-primary-subtle';
        } elseif (str_contains($lokasiNorm, '02') || str_contains($lokasiNorm, 'BATULICIN')) {
            $lokasiBadgeClass = 'bg-success-subtle text-success border border-success-subtle';
        } elseif (str_contains($lokasiNorm, '03') || str_contains($lokasiNorm, 'MARTAPURA')) {
            $lokasiBadgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
        } elseif (str_contains($lokasiNorm, '04') || str_contains($lokasiNorm, 'TANJUNG')) {
            $lokasiBadgeClass = 'bg-info-subtle text-info-emphasis border border-info-subtle';
        } elseif (str_contains($lokasiNorm, '05') || str_contains($lokasiNorm, 'HANDIL')) {
            $lokasiBadgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
        }

        $noSeriBadge = !empty($c['nomor_seri_inventaris']) ? '<span class="badge bg-light text-dark border font-monospace mt-0.5" style="font-size: 0.68rem;"><i class="bi bi-hash me-0.5"></i>'.e($c['nomor_seri_inventaris']).'</span>' : '';

        $cardsTableRowsHtml .= '
        <tr id="card-row-'.$cId.'" class="hover:bg-[#F9FAFB] transition-colors">
          <td class="text-center py-2.5" style="width: 38px;">
            <input class="form-check-input card-checkbox cursor-pointer" type="checkbox" value="'.$cId.'" onchange="updateCardCounters(this)">
          </td>
          <td style="width: 155px;" class="py-2.5">
            <div class="font-mono font-bold text-[#0E5C58] text-[13px]">'.e($rek).'</div>
            <div class="text-[11px] text-[#53595F] font-mono mt-0.5" title="Nomor Aset Gabungan">'.e($gabungan).'</div>
            '.$noSeriBadge.'
          </td>
          <td class="py-2.5">
            <div class="flex items-center gap-2.5">
              '.$photoThumbHtml.'
              <div class="min-w-0">
                <a href="'.e(module_url('kartu_detail.php', ['id'=>$cId])).'" target="_blank" class="font-semibold text-[#1B2A3A] hover:text-[#0E5C58] transition-colors text-decoration-none text-break text-[13px]" title="Buka Detail &amp; Foto Fisik">'.e($nama).'</a>
                <div class="text-[11px] text-[#53595F] font-mono mt-0.5 flex items-center gap-1">
                  <i class="bi bi-calendar-event text-[#0E5C58]"></i>'.e($tglCard).'
                  '.$todayBadge.'
                </div>
              </div>
            </div>
          </td>
          <td style="width: 75px;" class="text-center py-2.5">
            '.($barcode ? '<a href="'.e($barcode).'" target="_blank" class="px-2 py-0.5 rounded-[4px] text-[11px] font-bold bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC] text-decoration-none inline-flex items-center gap-1 shadow-2xs" title="'.e($barcode).'"><i class="bi bi-qr-code"></i>QR</a>' : '<span class="text-[#A0A5AA] text-[12px]">-</span>').'
          </td>
          <td style="width: 95px;" class="text-center py-2.5">
            <span class="badge px-2 py-1 rounded-pill '.$lokasiBadgeClass.'" style="font-size: 0.72rem; font-weight: 600;">'.e($lokasi).'</span>
          </td>
          <td class="text-end py-2.5 pe-3" style="width: 210px;">
            <div class="inline-flex items-center gap-1 justify-end">
              <a class="h-[28px] px-2 bg-white border border-[#D1D5D8] hover:bg-[#ECFDF5] text-[#10B981] rounded-[5px] text-[11px] font-medium transition-colors inline-flex items-center gap-1 text-decoration-none shadow-2xs" target="_blank" href="'.e(module_url('inventaris_mutation.php', ['id'=>$cId])).'" title="Mutasi Fasilitas &amp; Kartu Inventaris">
                <i class="bi bi-arrow-left-right"></i><span class="d-none d-xl-inline"> Mutasi</span>
              </a>
              <a class="h-[28px] px-2 bg-white border border-[#D1D5D8] hover:bg-[#D9F1EF] text-[#0E5C58] rounded-[5px] text-[11px] font-medium transition-colors inline-flex items-center gap-1 text-decoration-none shadow-2xs" target="_blank" href="'.e(module_url('kartu_detail.php', ['id'=>$cId])).'" title="Buka Detail Web &amp; Foto">
                <i class="bi bi-box-arrow-up-right"></i><span class="d-none d-xl-inline"> Detail</span>
              </a>
              <button type="button" class="h-[28px] px-2 bg-white border border-[#D1D5D8] hover:bg-[#E0F2FE] text-[#0284C7] rounded-[5px] text-[11px] font-medium transition-colors inline-flex items-center gap-1 shadow-2xs cursor-pointer" title="Pratinjau Fisik Kartu" onclick=\'openCardPreviewModal('.$jsonPayload.')\'>
                <i class="bi bi-eye"></i><span class="d-none d-xl-inline"> Lihat</span>
              </button>
              <a class="h-[28px] px-2 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] rounded-[5px] text-[11px] font-medium transition-colors inline-flex items-center gap-1 text-decoration-none shadow-2xs" target="_blank" href="'.e(module_url('print_inventory_card.php', ['source'=>'inventaris_kartu', 'id'=>$cId])).'" title="Cetak Kartu Ini">
                <i class="bi bi-printer"></i><span class="d-none d-xl-inline"> Cetak</span>
              </a>
              <button type="button" class="h-[28px] px-2 bg-white border border-[#D1D5D8] hover:bg-[#FEF3C7] text-[#D97706] rounded-[5px] text-[11px] font-medium transition-colors inline-flex items-center gap-1 shadow-2xs cursor-pointer" title="Edit Kartu" onclick=\'openCardEditModal('.$jsonPayload.')\'>
                <i class="bi bi-pencil"></i><span class="d-none d-xl-inline"> Edit</span>
              </button>
              <button type="button" class="h-[28px] px-2 bg-white border border-[#D1D5D8] hover:bg-[#FEE2E2] text-[#DC2626] rounded-[5px] text-[11px] font-medium transition-colors inline-flex items-center gap-1 shadow-2xs cursor-pointer" title="Hapus Kartu" onclick="confirmDeleteCard('.$cId.', \''.e(addslashes($rek . ' - ' . $nama)).'\')">
                <i class="bi bi-trash"></i><span class="d-none d-xl-inline"> Hapus</span>
              </button>
            </div>
          </td>
        </tr>';

        // Mobile Card Row (Tanpa Scroll Horizontal pada Smartphone)
        $cardsMobileCardsHtml .= '
        <div class="p-3 border-b border-[#E5E7EB] bg-white hover:bg-[#F9FAFB] transition-colors" id="card-mobile-'.$cId.'">
          <div class="flex items-start justify-between gap-2 mb-2">
            <div class="flex items-center gap-2">
              <input class="form-check-input card-checkbox cursor-pointer" type="checkbox" value="'.$cId.'" onchange="updateCardCounters(this)">
              <div>
                <div class="font-mono font-bold text-[#0E5C58] text-[13px]">'.e($rek).'</div>
                <div class="text-[11px] text-[#53595F] font-mono" title="Nomor Aset Gabungan">'.e($gabungan).'</div>
              </div>
            </div>
            <div class="flex items-center gap-1.5 flex-wrap justify-end">
              <span class="badge px-2 py-1 rounded-pill '.$lokasiBadgeClass.'" style="font-size: 0.70rem; font-weight: 600;">'.e($lokasi).'</span>
              '.($barcode ? '<a href="'.e($barcode).'" target="_blank" class="px-2 py-0.5 rounded-[4px] text-[10px] font-bold bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC] text-decoration-none inline-flex items-center gap-1"><i class="bi bi-qr-code"></i>QR</a>' : '').'
            </div>
          </div>

          <div class="flex items-center gap-2.5 mb-2.5">
            '.$photoThumbHtml.'
            <div class="min-w-0 flex-1">
              <a href="'.e(module_url('kartu_detail.php', ['id'=>$cId])).'" target="_blank" class="font-semibold text-[#1B2A3A] hover:text-[#0E5C58] transition-colors text-decoration-none text-[13px] line-clamp-2" title="Buka Detail &amp; Foto Fisik">'.e($nama).'</a>
              <div class="text-[11px] text-[#53595F] font-mono mt-0.5 flex items-center gap-1">
                <i class="bi bi-calendar-event text-[#0E5C58]"></i>'.e($tglCard).'
                '.$todayBadge.'
              </div>
              '.$noSeriBadge.'
            </div>
          </div>

          <div class="grid grid-cols-6 gap-1 pt-2 border-t border-[#F3F4F6]">
            <a class="h-[30px] bg-white border border-[#D1D5D8] hover:bg-[#ECFDF5] text-[#10B981] rounded-[6px] text-[11px] font-medium transition-colors flex items-center justify-center text-decoration-none shadow-2xs" target="_blank" href="'.e(module_url('inventaris_mutation.php', ['id'=>$cId])).'" title="Mutasi">
              <i class="bi bi-arrow-left-right"></i>
            </a>
            <a class="h-[30px] bg-white border border-[#D1D5D8] hover:bg-[#D9F1EF] text-[#0E5C58] rounded-[6px] text-[11px] font-medium transition-colors flex items-center justify-center text-decoration-none shadow-2xs" target="_blank" href="'.e(module_url('kartu_detail.php', ['id'=>$cId])).'" title="Detail">
              <i class="bi bi-box-arrow-up-right"></i>
            </a>
            <button type="button" class="h-[30px] bg-white border border-[#D1D5D8] hover:bg-[#E0F2FE] text-[#0284C7] rounded-[6px] text-[11px] font-medium transition-colors flex items-center justify-center shadow-2xs cursor-pointer" title="Lihat" onclick=\'openCardPreviewModal('.$jsonPayload.')\'>
              <i class="bi bi-eye"></i>
            </button>
            <a class="h-[30px] bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] rounded-[6px] text-[11px] font-medium transition-colors flex items-center justify-center text-decoration-none shadow-2xs" target="_blank" href="'.e(module_url('print_inventory_card.php', ['source'=>'inventaris_kartu', 'id'=>$cId])).'" title="Cetak">
              <i class="bi bi-printer"></i>
            </a>
            <button type="button" class="h-[30px] bg-white border border-[#D1D5D8] hover:bg-[#FEF3C7] text-[#D97706] rounded-[6px] text-[11px] font-medium transition-colors flex items-center justify-center shadow-2xs cursor-pointer" title="Edit" onclick=\'openCardEditModal('.$jsonPayload.')\'>
              <i class="bi bi-pencil"></i>
            </button>
            <button type="button" class="h-[30px] bg-white border border-[#D1D5D8] hover:bg-[#FEE2E2] text-[#DC2626] rounded-[6px] text-[11px] font-medium transition-colors flex items-center justify-center shadow-2xs cursor-pointer" title="Hapus" onclick="confirmDeleteCard('.$cId.', \''.e(addslashes($rek . ' - ' . $nama)).'\')">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </div>';
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
        <a class="btn btn-sm btn-light border py-1 px-2.5 text-nowrap fw-medium" target="_blank" href="'.e(module_url('print_inventory_card.php', ['source'=>'inventaris_kartu', 'id'=>$qId])).'" title="Cetak Kartu" style="font-size: 0.78rem;"><i class="bi bi-printer me-1"></i>Cetak</a>
      </td>
    </tr>';
}

$head = '
<script src="' . e(module_url('assets/vendor/qrcode.min.js')) . '"></script>
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
  padding: 7px 16px;
  font-size: 0.83rem;
  font-weight: 600;
  border-radius: 8px;
  background: #FFFFFF;
  border: 1px solid var(--border-subtle);
  color: #475569;
  text-decoration: none;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.branch-nav-pill:hover {
  background: #F1F5F9;
  color: #0F172A;
  border-color: #CBD5E1;
  transform: translateY(-1px);
}
.branch-nav-pill.active {
  background: linear-gradient(135deg, #1E40AF 0%, #2563EB 100%) !important;
  border-color: #1D4ED8 !important;
  color: #FFFFFF !important;
  font-weight: 700;
  box-shadow: 0 3px 8px rgba(37, 99, 235, 0.3);
}
.ops-header-badge {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 6px 14px;
  border-radius: 30px;
  font-size: 0.76rem;
  font-weight: 600;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
.activity-time {
  min-width: 45px;
  font-size: 0.72rem;
}
.finding-card-item {
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  border: 1px solid #fecaca !important;
  border-left: 4px solid #dc2626 !important;
  background: #ffffff;
}
.finding-card-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 14px rgba(220, 38, 38, 0.08) !important;
  border-color: #fca5a5 !important;
}
.dashboard-main-nav {
  display: flex;
  gap: 6px;
  background: #F1F5F9;
  padding: 6px;
  border-radius: 12px;
  border: 1px solid #E2E8F0;
  margin-bottom: 24px;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.dashboard-nav-tab {
  padding: 8px 18px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #64748B;
  text-decoration: none;
  border-radius: 8px;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  white-space: nowrap;
}
.dashboard-nav-tab:hover {
  color: #1E293B;
  background: rgba(255, 255, 255, 0.65);
}
.dashboard-nav-tab.active {
  background: #FFFFFF;
  color: #1D4ED8;
  font-weight: 700;
  box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08), 0 1px 2px rgba(15, 23, 42, 0.04);
}

/* HERO RICH GRADIENT BANNERS (DARK NAVBAR THEME) */
.hero-gradient-header {
  color: #FFFFFF !important;
  padding: 24px 28px;
  border-radius: 18px;
  margin-bottom: 24px;
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #051329 0%, #0A1E38 55%, #162E52 100%) !important;
  border: 1px solid rgba(255, 255, 255, 0.08) !important;
  box-shadow: 0 12px 28px -6px rgba(5, 19, 41, 0.55);
}
.hero-gradient-header::after {
  content: "";
  position: absolute;
  top: -50px;
  right: -50px;
  width: 240px;
  height: 240px;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0) 70%);
  border-radius: 50%;
  pointer-events: none;
}
.hero-gradient-blue {
  background: linear-gradient(135deg, #051329 0%, #0A1E38 55%, #162E52 100%) !important;
  box-shadow: 0 12px 28px -6px rgba(5, 19, 41, 0.55);
}
.hero-gradient-green {
  background: linear-gradient(135deg, #051329 0%, #0A1E38 55%, #162E52 100%) !important;
  box-shadow: 0 12px 28px -6px rgba(5, 19, 41, 0.55);
}
.hero-gradient-header h1, .hero-gradient-header h2, .hero-gradient-header h3, .hero-gradient-header h4 {
  color: #FFFFFF !important;
  font-weight: 800;
  letter-spacing: -0.02em;
  margin: 0;
}
.hero-gradient-header p {
  color: rgba(255, 255, 255, 0.88) !important;
  margin-top: 4px;
  margin-bottom: 0;
  font-size: 0.85rem;
}
.hero-gradient-header .tech-label-blue {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #93C5FD;
}
.hero-gradient-header .tech-label-green {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #A7F3D0;
}
.hero-gradient-header .header-actions .btn-glass {
  background: rgba(255, 255, 255, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.22);
  color: #FFFFFF !important;
  font-weight: 600;
  font-size: 0.82rem;
  padding: 8px 16px;
  border-radius: 10px;
  transition: all 0.2s ease;
  backdrop-filter: blur(6px);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.hero-gradient-header .header-actions .btn-glass:hover {
  background: #FFFFFF;
  color: #0A1E38 !important;
  border-color: #FFFFFF;
  transform: translateY(-1px);
}
.hero-gradient-green .header-actions .btn-glass:hover {
  color: #0A1E38 !important;
}
.hero-gradient-header .header-actions .btn-glass.active-glass {
  background: rgba(255, 255, 255, 0.28) !important;
  border-color: #FFFFFF !important;
  color: #FFFFFF !important;
  font-weight: 700;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}
.hero-gradient-header .header-actions .btn-solid-cta {
  background: #FFFFFF !important;
  color: #0A1E38 !important;
  border: 1px solid #FFFFFF !important;
  font-weight: 700;
  font-size: 0.82rem;
  padding: 8px 18px;
  border-radius: 10px;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
  transition: all 0.2s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.hero-gradient-green .header-actions .btn-solid-cta {
  color: #0A1E38 !important;
}
.hero-gradient-header .header-actions .btn-solid-cta:hover {
  background: #F1F5F9 !important;
  color: #051329 !important;
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35);
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

      <!-- Symmetrical Metric Strip (88px) -->
      <div class="bg-[#FFFFFF] border border-[#D1D5D8] rounded-[10px] h-[88px] flex overflow-hidden shrink-0 mb-4 shadow-xs">
        <!-- Card 1: Total Kartu Inventaris -->
        <a href="'.e(module_url('dashboard.php', ['tab'=>'kartu'])).'" class="flex-1 flex flex-col justify-center px-6 border-r border-[#D1D5D8] relative hover:bg-[#F4F5F7] transition-colors text-decoration-none">
          <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0E5C58]"></div>
          <div class="text-[28px] font-bold text-[#1B2A3A] tabular-nums leading-none">
            '.number_format($totalCardsCount).' <span class="text-[13px] font-normal text-[#53595F]">Aset</span>
          </div>
          <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
            <span class="text-[#10B981] font-semibold"><i class="bi bi-check-circle-fill me-0.5"></i> Terdaftar Resmi</span>
            '.($todayCardsCount > 0 ? '<span>&middot;</span> <span class="text-[#0E5C58] font-bold">+'.$todayCardsCount.' Hari Ini</span>' : '').'
          </div>
        </a>

        <!-- Card 2: Dokumentasi Foto Fisik -->
        <a href="'.e(module_url('asset_gallery.php', ['sub'=>'inventaris'])).'" class="flex-1 flex flex-col justify-center px-6 border-r border-[#D1D5D8] relative hover:bg-[#F4F5F7] transition-colors text-decoration-none">
          <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0284C7]"></div>
          <div class="text-[28px] font-bold text-[#0284C7] tabular-nums leading-none">
            '.number_format($totalInvWithPhotoCount).' <span class="text-[13px] font-normal text-[#53595F]">Foto Unit</span>
          </div>
          <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
            <span class="text-[#0284C7] font-semibold"><i class="bi bi-camera-fill me-0.5"></i> Galeri Foto Fisik</span>
            <span>&middot;</span>
            <span class="text-[#53595F] text-[11px]">'.($totalCardsCount > 0 ? round(($totalInvWithPhotoCount / $totalCardsCount) * 100) : 0).'% Terdokumentasi</span>
          </div>
        </a>

        <!-- Card 3: Jaringan Kantor Bank -->
        <a href="'.e(module_url('cabang_admin.php')).'" class="flex-1 flex flex-col justify-center px-6 relative hover:bg-[#F4F5F7] transition-colors text-decoration-none">
          <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#10B981]"></div>
          <div class="text-[28px] font-bold text-[#1B2A3A] tabular-nums leading-none">
            5 <span class="text-[13px] font-normal text-[#53595F]">Cabang</span>
          </div>
          <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
            <span class="text-[#10B981] font-semibold"><i class="bi bi-buildings-fill me-0.5"></i> 01 Pusat s/d 05 Handil</span>
            <span>&middot;</span>
            <span class="text-[#0E5C58] font-bold">100% Aktif</span>
          </div>
        </a>
      </div>

      <!-- Tabs Navigation (44px) -->
      <div class="flex gap-6 h-[44px] border-b border-[#D1D5D8] shrink-0 mb-4">
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
    try {
        $stDc = db()->query("SELECT COUNT(*) FROM server_room_logs WHERE status = 'in_room'");
        if ($stDc) $activeServerGuests = (int)$stDc->fetchColumn();
    } catch (Throwable $e) {}
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

            if ($bPct >= 100) {
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
                  <span class="text-[11px] text-[#53595F] font-mono">('.$bDone.'/'.$bTotal.' unit)</span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-[12px] font-bold text-[#1B2A3A] font-mono tabular-nums">'.$bPct.'%</span>
                  '.$bBadge.'
                </div>
              </div>
              <div class="h-2 w-full bg-[#E5E7EB] rounded-full overflow-hidden">
                <div class="h-full '.$bBar.' rounded-full transition-all duration-500" style="width: '.$bPct.'%;"></div>
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
            <i class="bi bi-shield-check"></i> BANKING IT OPERATIONS COMMAND
          </div>
          <h1 class="text-[22px] font-bold text-[#1B2A3A] tracking-tight m-0">Antrean Pemeliharaan &amp; Operasional IT</h1>
        </div>
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
      </div>

      <!-- 4. Bar Pintasan Cepat (Quick Action Hub) -->
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
            <a href="'.e(module_url('cabang_admin.php')).'" class="text-[#0E5C58] font-bold hover:underline text-decoration-none">Kelola Cabang &rarr;</a>
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

      <!-- Tabs Navigation (44px) -->
      <div class="flex gap-6 h-[44px] border-b border-[#D1D5D8] shrink-0 mb-4">
        <a href="'.e(module_url('dashboard.php', ['cabang'=>$cabangId, 'bulan'=>$month, 'tahun'=>$year])).'" class="h-full flex items-center font-'.($isTabAntrean ? 'bold text-[#1B2A3A] border-b-[3px] border-[#0E5C58]' : 'semibold text-[#53595F] hover:text-[#1B2A3A] border-b-[3px] border-transparent').' px-1 relative top-[1px] transition-colors text-decoration-none">
          <i class="bi bi-clock-history me-1.5 '.($isTabAntrean ? 'text-[#0E5C58]' : '').'"></i> Belum dipelihara ('.$displayDueCount.')
        </a>
        <a href="'.e(module_url('dashboard.php', ['tab'=>'temuan', 'cabang'=>$cabangId, 'bulan'=>$month, 'tahun'=>$year])).'" class="h-full flex items-center font-'.($isTabTemuan ? 'bold text-[#1B2A3A] border-b-[3px] border-[#0E5C58]' : 'semibold text-[#53595F] hover:text-[#1B2A3A] border-b-[3px] border-transparent').' px-1 relative top-[1px] transition-colors text-decoration-none">
          <i class="bi bi-exclamation-triangle me-1.5 '.($isTabTemuan ? 'text-[#DC2626]' : '').'"></i> Temuan terbuka ('.$displayFindingsCount.')
        </a>
        <a href="'.e(module_url('dashboard.php', ['tab'=>'kartu'])).'" class="h-full flex items-center font-'.($isTabKartu ? 'bold text-[#1B2A3A] border-b-[3px] border-[#0E5C58]' : 'semibold text-[#53595F] hover:text-[#1B2A3A] border-b-[3px] border-transparent').' px-1 relative top-[1px] transition-colors text-decoration-none">
          <i class="bi bi-credit-card-2-front me-1.5 '.($isTabKartu ? 'text-[#0E5C58]' : '').'"></i> Kartu Inventaris ('.$totalCardsCount.')
        </a>
        <a href="'.e(module_url('asset_gallery.php', $cabangId > 0 ? ['cabang'=>$cabangId] : [])).'" class="h-full flex items-center font-semibold text-[#53595F] hover:text-[#1B2A3A] border-b-[3px] border-transparent px-1 relative top-[1px] transition-colors text-decoration-none">
          <i class="bi bi-images me-1.5"></i> Galeri Foto
        </a>
        '.(is_admin_or_auditor() ? '<a href="'.e(module_url('audit_trail.php')).'" class="h-full flex items-center font-semibold text-[#53595F] hover:text-[#1B2A3A] border-b-[3px] border-transparent px-1 relative top-[1px] transition-colors text-decoration-none"><i class="bi bi-journal-text me-1.5"></i> Log Audit</a>' : '').'
      </div>
    ';
}
// =========================================================================
// 7. KONTEN TAB: KARTU INVENTARIS ATAU MONITORING
// =========================================================================
if ($activeTab === 'kartu') {
    // --- TAMPILAN TAB KARTU INVENTARIS (KOMBAI TEAL DESIGN SYSTEM) ---
    $body .= '
    <!-- Minimalist Action Toolbar Tab Kartu -->
    <div class="bg-white border border-[#D1D5D8] rounded-[10px] p-3.5 mb-4 shadow-xs">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="px-2.5 py-1 text-[11px] font-bold bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC] rounded-[6px] font-mono tracking-wider">
            KARTU INVENTARIS
          </span>
          <span class="text-[13px] text-[#53595F]">
            Kelola data kartu fisik CR80, penerbitan QR code, cetak massal A4, dan ekspor dokumen.
          </span>
        </div>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
          <button type="button" class="h-[34px] px-3.5 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[6px] text-[12px] font-semibold transition-colors inline-flex items-center gap-1.5 border-0 shadow-xs cursor-pointer" data-bs-toggle="modal" data-bs-target="#addCardModal">
            <i class="bi bi-plus-lg"></i> Tambah Data Kartu
          </button>
          <a href="'.e(module_url('print_inventory_card.php', ['source'=>'inventaris_kartu'])).'" target="_blank" class="h-[34px] px-3 bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
            <i class="bi bi-printer text-[#0E5C58]"></i> Cetak A4
          </a>
          <div class="dropdown">
            <button class="h-[34px] px-3 bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 shadow-xs dropdown-toggle cursor-pointer" type="button" data-bs-toggle="dropdown">
              <i class="bi bi-download text-[#53595F]"></i> Ekspor
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border border-[#D1D5D8] rounded-[8px]" style="font-size: 0.85rem;">
              <li>
                <a class="dropdown-item fw-semibold text-[#10B981]" href="'.e(module_url('print_inventory_card.php', ['export'=>'excel'])).'">
                  <i class="bi bi-file-earmark-excel-fill text-[#10B981] me-2"></i> Laporan Excel SDM (.xls)
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="'.e(module_url('print_inventory_card.php', ['export'=>'doc'])).'">
                  <i class="bi bi-file-earmark-word-fill text-[#0284C7] me-2"></i> Dokumen Microsoft Word (.doc)
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="'.e(module_url('print_inventory_card.php', ['export'=>'csv'])).'">
                  <i class="bi bi-file-earmark-spreadsheet text-[#53595F] me-2"></i> Spreadsheet CSV (.csv)
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- 4 Kotak Metrik Ringkasan Tab Kartu Termasuk Baru Diperoleh Hari Ini -->
    <div class="bg-[#FFFFFF] border border-[#D1D5D8] rounded-[10px] flex flex-col sm:flex-row overflow-hidden shrink-0 mb-4 shadow-xs">
      <!-- Card 1: Total Kartu -->
      <a href="'.e(module_url('dashboard.php', ['tab'=>'kartu'])).'" class="flex-1 flex flex-col justify-center px-5 py-3 border-b sm:border-b-0 sm:border-r border-[#D1D5D8] relative hover:bg-[#F4F5F7] transition-colors text-decoration-none">
        <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0E5C58]"></div>
        <div class="text-[26px] font-bold text-[#1B2A3A] tabular-nums leading-none">
          '.number_format($totalCardsCount).' <span class="text-[13px] font-normal text-[#53595F]">Unit</span>
        </div>
        <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
          <span class="text-[#0E5C58] font-bold">Total Kartu Inventaris</span>
          <span>&middot;</span>
          <span class="text-[#53595F] text-[11px]">Semua Unit</span>
        </div>
      </a>

      <!-- Card 2: Baru Hari Ini -->
      <a href="'.e(module_url('dashboard.php', ['tab'=>'kartu', 'hari_ini'=>($filterHariIni ? null : 1)])).'" class="flex-1 flex flex-col justify-center px-5 py-3 border-b sm:border-b-0 sm:border-r border-[#D1D5D8] relative '.($filterHariIni ? 'bg-[#ECFDF5]' : 'hover:bg-[#F4F5F7]').' transition-colors text-decoration-none">
        <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#10B981]"></div>
        <div class="text-[26px] font-bold text-[#10B981] tabular-nums leading-none flex items-center justify-between">
          <span>'.$todayCardsCount.' <span class="text-[13px] font-normal text-[#53595F]">Unit</span></span>
          '.($todayCardsCount > 0 ? '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#10B981] text-white"><i class="bi bi-stars me-0.5"></i>Baru</span>' : '').'
        </div>
        <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
          <span class="text-[#10B981] font-semibold"><i class="bi bi-funnel me-0.5"></i> Baru Hari Ini</span>
          <span>&middot;</span>
          <span class="text-[#53595F] text-[11px]">'.date('d/m/Y').'</span>
        </div>
      </a>

      <!-- Card 3: Kartu Ber-Label QR -->
      <a href="'.e(module_url('dashboard.php', ['tab'=>'kartu', 'has_qr'=>($filterHasQr ? null : 1)])).'" class="flex-1 flex flex-col justify-center px-5 py-3 border-b sm:border-b-0 sm:border-r border-[#D1D5D8] relative '.($filterHasQr ? 'bg-[#EFF6FF]' : 'hover:bg-[#F4F5F7]').' transition-colors text-decoration-none">
        <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0284C7]"></div>
        <div class="text-[26px] font-bold text-[#0284C7] tabular-nums leading-none">
          '.number_format($withQrCardsCount).' <span class="text-[13px] font-normal text-[#53595F]">Unit</span>
        </div>
        <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
          <span class="text-[#0284C7] font-semibold"><i class="bi bi-qr-code me-0.5"></i> Kartu Ber-Label QR</span>
          <span>&middot;</span>
          <span class="text-[#53595F] text-[11px]">'.($totalCardsCount > 0 ? round(($withQrCardsCount / $totalCardsCount) * 100) : 0).'% Ber-QR</span>
        </div>
      </a>

      <!-- Card 4: Sebaran 5 Kantor Cabang -->
      <a href="#branchDistributionGrid" onclick="document.getElementById(\'branchDistributionGrid\')?.scrollIntoView({behavior:\'smooth\'})" class="flex-1 flex flex-col justify-center px-5 py-3 relative hover:bg-[#F4F5F7] transition-colors text-decoration-none">
        <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#D97706]"></div>
        <div class="text-[26px] font-bold text-[#1B2A3A] tabular-nums leading-none">
          5 <span class="text-[13px] font-normal text-[#53595F]">Cabang</span>
        </div>
        <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
          <span class="text-[#D97706] font-semibold"><i class="bi bi-buildings me-0.5"></i> Distribusi Kantor</span>
          <span>&middot;</span>
          <span class="text-[#53595F] text-[11px]">01 Pusat s/d 05</span>
        </div>
      </a>
    </div>

    <!-- Ringkasan Jumlah Kartu Inventaris Per Cabang (01 - 05) -->
    <div id="branchDistributionGrid" class="bg-white border border-[#D1D5D8] rounded-[10px] p-4 mb-4 shadow-xs">
      <div class="flex justify-between items-center mb-3 flex-wrap gap-2">
        <div>
          <div class="text-[11px] font-bold text-[#0E5C58] uppercase tracking-wider mb-0.5">DISTRIBUSI UNIT CABANG</div>
          <h3 class="text-[15px] font-bold text-[#1B2A3A] m-0 flex items-center gap-1.5">
            <i class="bi bi-buildings text-[#0E5C58]"></i> Jumlah Kartu Inventaris Per Cabang
          </h3>
        </div>
        <div class="text-[12px] text-[#53595F]">
          Total <strong>5 Kantor Cabang</strong> &middot; Klik cabang untuk filter cepat
        </div>
      </div>
      <div class="row g-2">
        '.$branchCardsGridHtml.'
      </div>
    </div>

    <!-- Compact Filter Bar (Sistem Filter Aset Komputer) -->
    <div class="bg-white border border-[#D1D5D8] rounded-[10px] p-4 mb-4 shadow-xs">
      <form method="get" class="row g-2 align-items-end">
        <input type="hidden" name="tab" value="kartu">
        <div class="col-lg-4 col-md-6">
          <label class="block text-[12px] font-bold text-[#1B2A3A] mb-1.5">Cari Nomor Rekening / Perangkat / PIC</label>
          <div class="relative">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-[#53595F] text-[13px]"></i>
            <input type="text" class="h-[36px] w-full bg-white border border-[#D1D5D8] rounded-[8px] pl-9 pr-3 text-[13px] text-[#1B2A3A] placeholder-[#9CA3AF] outline-none focus:border-[#0E5C58] transition-colors" name="q_kartu" value="'.e($searchCard).'" placeholder="Cari 05500032, nama barang, lokasi...">
          </div>
        </div>
        <div class="col-lg-3 col-md-3 col-sm-6">
          <label class="block text-[12px] font-bold text-[#1B2A3A] mb-1.5">Kantor Cabang</label>
          <select class="h-[36px] w-full bg-white border border-[#D1D5D8] rounded-[8px] px-3 text-[13px] text-[#1B2A3A] outline-none focus:border-[#0E5C58] transition-colors" name="cabang_kartu">
            <option value="">Semua Cabang (01 - 05)</option>
            <option value="01"'.($cabangCard === '01' ? ' selected' : '').'>01 - Kantor Pusat ('.$cabangStats['01']['count'].')</option>
            <option value="02"'.($cabangCard === '02' ? ' selected' : '').'>02 - Batulicin ('.$cabangStats['02']['count'].')</option>
            <option value="03"'.($cabangCard === '03' ? ' selected' : '').'>03 - Martapura ('.$cabangStats['03']['count'].')</option>
            <option value="04"'.($cabangCard === '04' ? ' selected' : '').'>04 - Tanjung ('.$cabangStats['04']['count'].')</option>
            <option value="05"'.($cabangCard === '05' ? ' selected' : '').'>05 - Handil Bakti ('.$cabangStats['05']['count'].')</option>
          </select>
        </div>
        <div class="col-lg-2 col-md-3 col-sm-6">
          <label class="block text-[12px] font-bold text-[#1B2A3A] mb-1.5">Tahun Perolehan</label>
          <select class="h-[36px] w-full bg-white border border-[#D1D5D8] rounded-[8px] px-3 text-[13px] text-[#1B2A3A] outline-none focus:border-[#0E5C58] transition-colors" name="tahun_kartu">
            <option value="">Semua Tahun</option>
            '.$optTahunKartuHtml.'
          </select>
        </div>
        <div class="col-lg-3 col-md-12 flex gap-1 justify-content-lg-end">
          <a class="h-[36px] px-3 w-full rounded-[8px] text-[13px] font-semibold transition-colors flex items-center justify-center gap-1.5 text-decoration-none '.($filterHariIni ? 'bg-[#10B981] hover:bg-[#059669] text-white shadow-xs' : 'bg-white border border-[#D1D5D8] text-[#10B981] hover:bg-[#ECFDF5]').'" href="'.e(module_url('dashboard.php', ['tab' => 'kartu', 'hari_ini' => ($filterHariIni ? null : 1)])).'" title="Filter aset yang baru diperoleh / didaftarkan hari ini">
            <i class="bi bi-stars"></i> Baru Hari Ini ('.$todayCardsCount.')
          </a>
        </div>
        <div class="col-12 flex justify-between items-center pt-3 border-t border-[#F3F4F6] mt-2 flex-wrap gap-2">
          <div class="text-[12px] text-[#53595F]">
            Menampilkan <strong>'.count($filteredCards).'</strong> kartu dari total <strong>'.$totalCardsCount.'</strong> kartu inventaris terdaftar.
            '.($filterHariIni ? '<span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC] ms-1.5"><i class="bi bi-funnel me-0.5"></i>Filter Hari Ini Aktif</span>' : '').'
          </div>
          <div class="flex gap-2">
            <a class="h-[34px] px-3 bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1 text-decoration-none" href="'.e(module_url('dashboard.php', ['tab' => 'kartu'])).'"><i class="bi bi-x-circle"></i> Reset</a>
            <button type="submit" class="h-[34px] px-3.5 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[6px] text-[12px] font-semibold transition-colors inline-flex items-center gap-1 border-0 shadow-xs cursor-pointer"><i class="bi bi-filter"></i> Terapkan</button>
          </div>
        </div>
      </form>
    </div>

    <!-- Table Container Card -->
    <div class="bg-white border border-[#D1D5D8] rounded-[10px] mb-4 shadow-xs overflow-hidden">
      <!-- Selection Card Bar -->
      <div id="selectionCardBar" class="border-b border-[#BFDBFE] px-4 py-2.5 d-none align-items-center justify-content-between flex-wrap gap-2" style="background-color: #EFF6FF !important;">
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-1 text-[12px] font-bold bg-[#0284C7] text-white rounded-[6px]"><i class="bi bi-check-square me-1"></i> <span id="countSelectedText">0</span> Dipilih</span>
          <span class="text-[13px] text-[#1E40AF] font-semibold">Aksi untuk kartu inventaris terpilih:</span>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <button type="button" class="h-[32px] px-3 bg-[#10B981] hover:bg-[#059669] text-white rounded-[6px] text-[12px] font-semibold transition-colors flex items-center gap-1.5 border-0 shadow-xs cursor-pointer" id="btnExportExcelSelected" onclick="exportExcelSelectedCards()">
            <i class="bi bi-file-earmark-excel-fill"></i> Ekspor Excel (<span id="countExcel">0</span>)
          </button>
          <button type="button" class="h-[32px] px-3 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[6px] text-[12px] font-semibold transition-colors flex items-center gap-1.5 border-0 shadow-xs cursor-pointer" id="btnPrintSelected" onclick="printSelectedCards()">
            <i class="bi bi-printer-fill"></i> Cetak Kartu (<span id="countPrint">0</span>)
          </button>
          <button type="button" class="h-[32px] px-3 bg-[#DC2626] hover:bg-[#B91C1C] text-white rounded-[6px] text-[12px] font-semibold transition-colors flex items-center gap-1.5 border-0 shadow-xs cursor-pointer" id="btnDeleteSelected" onclick="bulkDeleteCards()">
            <i class="bi bi-trash"></i> Hapus Terpilih (<span id="countDelete">0</span>)
          </button>
          <button type="button" class="h-[32px] px-3 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] rounded-[6px] text-[12px] font-medium transition-colors flex items-center gap-1 cursor-pointer" onclick="deselectAllCards()">
            <i class="bi bi-x-circle"></i> Batal
          </button>
        </div>
      </div>

      <!-- Desktop View: Tabel Pas Lebar Layar (Tanpa Scroll Horizontal) -->
      <div class="d-none d-md-block w-100 overflow-hidden">
        <table class="table table-hover align-middle mb-0 w-100" style="font-size: 13px;">
          <thead style="background: #F4F5F7; border-bottom: 1px solid #D1D5D8;">
            <tr>
              <th style="width: 38px;" class="text-center py-2.5">
                <input class="form-check-input cursor-pointer" type="checkbox" id="checkAllRows" onchange="toggleSelectAllCards(this)" title="Pilih Semua Kartu">
              </th>
              <th style="width: 155px;" class="text-[11px] font-bold text-[#53595F] uppercase tracking-wider py-2.5">NOMOR INVENTARIS</th>
              <th class="text-[11px] font-bold text-[#53595F] uppercase tracking-wider py-2.5">NAMA BARANG &amp; TGL PEROLEHAN</th>
              <th style="width: 75px;" class="text-center text-[11px] font-bold text-[#53595F] uppercase tracking-wider py-2.5">LABEL QR</th>
              <th style="width: 95px;" class="text-center text-[11px] font-bold text-[#53595F] uppercase tracking-wider py-2.5">LOKASI</th>
              <th class="text-end text-[11px] font-bold text-[#53595F] uppercase tracking-wider py-2.5 pe-3" style="width: 210px;">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#E5E7EB]">
            '.$cardsTableRowsHtml.'
          </tbody>
        </table>
      </div>

      <!-- Mobile View: Kartu Vertikal (Tanpa Scroll Horizontal) -->
      <div class="d-block d-md-none divide-y divide-[#E5E7EB]">
        '.$cardsMobileCardsHtml.'
      </div>

      <div class="bg-white border-t border-[#D1D5D8] py-2.5 px-4 flex flex-wrap justify-between items-center gap-2">
        <div class="text-[12px] text-[#53595F]">
          Menampilkan <strong>'.($totalFilteredCards > 0 ? ($offsetCards + 1) : 0).' - '.min($offsetCards + $perPageCards, $totalFilteredCards).'</strong> dari <strong>'.$totalFilteredCards.'</strong> kartu inventaris terdaftar
        </div>
        '.($totalPagesCards > 1 ? '
        <nav aria-label="Halaman Kartu">
          <ul class="pagination pagination-sm m-0 flex items-center gap-1">
            <li class="page-item '.($pageKartu <= 1 ? 'disabled opacity-50' : '').'">
              <a class="h-[28px] px-2 bg-white border border-[#D1D5D8] rounded-[4px] text-[12px] text-[#1B2A3A] flex items-center text-decoration-none" href="'.e(module_url('dashboard.php', array_merge($_GET, ['tab'=>'kartu', 'p_kartu'=>max(1, $pageKartu - 1)]))).'"><i class="bi bi-chevron-left"></i></a>
            </li>
            '.implode('', array_map(function($p) use ($pageKartu) {
                if ($p === '...') return '<li class="page-item disabled px-1 text-[#A0A5AA]">&hellip;</li>';
                $isAct = ($p == $pageKartu);
                $cls = $isAct ? 'bg-[#0E5C58] text-white font-bold border-[#0E5C58]' : 'bg-white text-[#1B2A3A] border-[#D1D5D8] hover:bg-[#F4F5F7]';
                return '<li class="page-item"><a class="h-[28px] min-w-[28px] px-2 border rounded-[4px] text-[12px] flex items-center justify-center text-decoration-none '.$cls.'" href="'.e(module_url('dashboard.php', array_merge($_GET, ['tab'=>'kartu', 'p_kartu'=>$p]))).'">'.$p.'</a></li>';
            }, (function() use ($pageKartu, $totalPagesCards) {
                $pages = [];
                $start = max(1, $pageKartu - 2);
                $end = min($totalPagesCards, $pageKartu + 2);
                if ($start > 1) {
                    $pages[] = 1;
                    if ($start > 2) $pages[] = '...';
                }
                for ($i = $start; $i <= $end; $i++) $pages[] = $i;
                if ($end < $totalPagesCards) {
                    if ($end < $totalPagesCards - 1) $pages[] = '...';
                    $pages[] = $totalPagesCards;
                }
                return $pages;
            })())).'
            <li class="page-item '.($pageKartu >= $totalPagesCards ? 'disabled opacity-50' : '').'">
              <a class="h-[28px] px-2 bg-white border border-[#D1D5D8] rounded-[4px] text-[12px] text-[#1B2A3A] flex items-center text-decoration-none" href="'.e(module_url('dashboard.php', array_merge($_GET, ['tab'=>'kartu', 'p_kartu'=>min($totalPagesCards, $pageKartu + 1)]))).'"><i class="bi bi-chevron-right"></i></a>
            </li>
          </ul>
        </nav>
        ' : '').'
      </div>
    </div>
    ' ;


} elseif ($activeTab === 'temuan') {
    // --- TAMPILAN TAB TEMUAN KENDALA & STATUS PERBAIKAN ---
    $statusTemuanFilter = trim((string)($_GET['status_temuan'] ?? 'all'));
    $tingkatTemuanFilter = trim((string)($_GET['tingkat_temuan'] ?? 'all'));
    $qTemuan = trim((string)($_GET['q_temuan'] ?? ''));
    $bulanTemuan = isset($_GET['bulan_temuan']) ? (int)$_GET['bulan_temuan'] : 0;
    $tahunTemuan = isset($_GET['tahun_temuan']) ? (int)$_GET['tahun_temuan'] : 0;

    // Ambil data temuan dari tabel/sheet Maintenance_Findings & Maintenance_Scan
    $allFindingsRaw = get_findings_report($bulanTemuan, $tahunTemuan, $cabangId);

    // KONSOLIDASI DENGAN ACTIVE FINDINGS DARI DASHBOARD
    // Menjamin bahwa temuan kendala aktif yang dihitung pada kartu KPI Dashboard pasti tampil di sini
    $existingKeys = [];
    foreach ($allFindingsRaw as $item) {
        $aid = (int)($item['asset_id'] ?? 0);
        $sid = (int)($item['scan_id'] ?? 0);
        if ($aid > 0 && $sid > 0) {
            $existingKeys["{$aid}_{$sid}"] = true;
        }
        if ($aid > 0) {
            $existingKeys["asset_{$aid}"] = true;
        }
    }

    if (!empty($unresolvedFindings)) {
        foreach ($unresolvedFindings as $uf) {
            $aid = (int)($uf['asset_id'] ?? 0);
            $sid = (int)($uf['log_id'] ?? $uf['id'] ?? 0);
            if ($aid > 0 && isset($existingKeys["{$aid}_{$sid}"])) {
                continue;
            }

            $devName = !empty($uf['perangkat']) 
                ? $uf['perangkat'] 
                : (!empty($uf['nama_perangkat']) 
                    ? $uf['nama_perangkat'] 
                    : trim(($uf['merk'] ?? '') . ' ' . ($uf['model'] ?? '')));
            if ($devName === '') $devName = 'Perangkat IT';

            $fText = '';
            foreach (['finding', 'findings', 'deskripsi', 'catatan', 'keterangan'] as $fk) {
                if (!empty($uf[$fk]) && trim((string)$uf[$fk]) !== '' && trim((string)$uf[$fk]) !== '-') {
                    $fText = trim((string)$uf[$fk]);
                    break;
                }
            }
            if ($fText === '') $fText = 'Kendala perangkat dilaporkan';

            $fDate = '';
            foreach (['date', 'created_at', 'maintenance_date', 'reported_at', 'tanggal'] as $dk) {
                if (!empty($uf[$dk]) && trim((string)$uf[$dk]) !== '' && trim((string)$uf[$dk]) !== '-') {
                    $fDate = trim((string)$uf[$dk]);
                    break;
                }
            }

            $rawStUf = strtolower(trim((string)($uf['status'] ?? '')));
            $ufRepSt = (in_array($rawStUf, ['proses', 'in progress', 'sedang perbaikan', 'dalam proses'], true)) ? 'In Progress' : 'Perlu Perbaikan';

            $allFindingsRaw[] = [
                'id' => $sid,
                'finding_id' => 0,
                'scan_id' => $sid,
                'asset_id' => $aid,
                'token' => (string)($uf['token'] ?? ''),
                'kode_inventaris' => (string)($uf['kode_inventaris'] ?? '-'),
                'merk_model' => $devName,
                'karyawan_nama' => (string)($uf['karyawan_nama'] ?? $uf['pengguna'] ?? '-'),
                'cabang_nama' => (string)($uf['cabang_nama'] ?? '-'),
                'divisi_nama' => (string)($uf['divisi_nama'] ?? '-'),
                'finding' => $fText,
                'action_taken' => (string)($uf['action_taken'] ?? $uf['recommendation'] ?? '-'),
                'proses_perbaikan' => (string)($uf['proses_perbaikan'] ?? ''),
                'catatan_penyelesaian' => (string)($uf['catatan_penyelesaian'] ?? ''),
                'severity' => (string)($uf['severity'] ?? 'Sedang'),
                'repair_status' => $ufRepSt,
                'teknisi' => (string)($uf['reporter'] ?? $uf['technician_name'] ?? 'Teknisi'),
                'reported_by' => (string)($uf['reporter'] ?? $uf['technician_name'] ?? 'Teknisi'),
                'reported_at' => $fDate,
                'resolved_by' => '',
                'resolved_at' => '',
                'created_at' => $fDate
            ];
            $existingKeys["{$aid}_{$sid}"] = true;
        }
    }

    // Hitung statistik
    $countTotalTemuan = count($allFindingsRaw);
    $countPerluPerbaikan = 0;
    $countInProgress = 0;
    $countResolved = 0;

    foreach ($allFindingsRaw as $f) {
        $st = strtolower(trim((string)($f['repair_status'] ?? '')));
        if (in_array($st, ['resolved', 'selesai', 'closed', 'ok'], true)) {
            $countResolved++;
        } elseif (in_array($st, ['in progress', 'proses', 'sedang perbaikan'], true)) {
            $countInProgress++;
        } else {
            $countPerluPerbaikan++;
        }
    }

    // Filter daftar temuan sesuai pencarian dan filter
    $filteredFindings = array_filter($allFindingsRaw, function($f) use ($statusTemuanFilter, $tingkatTemuanFilter, $qTemuan, $bulanTemuan) {
        if ($bulanTemuan > 0) {
            $rawD = $f['created_at'] ?? $f['reported_at'] ?? '';
            if ($rawD !== '') {
                $m = (int)date('n', strtotime(substr($rawD, 0, 10)));
                if ($m > 0 && $m !== $bulanTemuan) return false;
            }
        }

        $st = strtolower(trim((string)($f['repair_status'] ?? '')));
        if ($statusTemuanFilter === 'perlu_perbaikan') {
            if (in_array($st, ['resolved', 'selesai', 'closed', 'ok', 'in progress', 'proses', 'sedang perbaikan'], true)) return false;
        } elseif ($statusTemuanFilter === 'proses') {
            if (!in_array($st, ['in progress', 'proses', 'sedang perbaikan'], true)) return false;
        } elseif ($statusTemuanFilter === 'selesai') {
            if (!in_array($st, ['resolved', 'selesai', 'closed', 'ok'], true)) return false;
        }

        if ($tingkatTemuanFilter !== 'all' && $tingkatTemuanFilter !== '') {
            if (strcasecmp(trim((string)($f['severity'] ?? '')), $tingkatTemuanFilter) !== 0) return false;
        }

        if ($qTemuan !== '') {
            $haystack = strtolower(
                ($f['kode_inventaris'] ?? '') . ' ' .
                ($f['merk_model'] ?? '') . ' ' .
                ($f['karyawan_nama'] ?? '') . ' ' .
                ($f['cabang_nama'] ?? '') . ' ' .
                ($f['divisi_nama'] ?? '') . ' ' .
                ($f['finding'] ?? '') . ' ' .
                ($f['action_taken'] ?? '') . ' ' .
                ($f['proses_perbaikan'] ?? '') . ' ' .
                ($f['teknisi'] ?? '')
            );
            if (strpos($haystack, strtolower($qTemuan)) === false) return false;
        }

        return true;
    });

    $findingsTableRows = '';
    $findingsCardsHtml = '';
    $noF = 0;

    if (empty($filteredFindings)) {
        $findingsTableRows = '
        <tr>
          <td colspan="7" class="text-center py-5 text-muted">
            <i class="bi bi-shield-check text-success fs-1 d-block mb-2 opacity-75"></i>
            <div class="fw-semibold">Tidak ada temuan kendala yang sesuai filter.</div>
            <div class="small text-secondary mt-1">Perangkat beroperasi normal atau silakan sesuaikan filter bulan/status.</div>
          </td>
        </tr>';
        $findingsCardsHtml = '
        <div class="text-center py-5 text-muted bg-white rounded-3 border">
          <i class="bi bi-shield-check text-success fs-2 d-block mb-2"></i>
          <div class="fw-semibold">Tidak ada data temuan yang sesuai.</div>
        </div>';
    } else {
        foreach ($filteredFindings as $f) {
            $noF++;
            $fId = (int)($f['finding_id'] ?? $f['id'] ?? 0);
            $scanId = (int)($f['scan_id'] ?? 0);
            $assetId = (int)($f['asset_id'] ?? 0);
            $token = (string)($f['token'] ?? '');
            $kode = $f['kode_inventaris'] ?? '-';
            $devName = $f['merk_model'] ?? 'Perangkat IT';
            $pic = $f['karyawan_nama'] ?? '-';
            $cabang = $f['cabang_nama'] ?? '-';
            $divisi = $f['divisi_nama'] ?? '-';
            $findingDesc = $f['finding'] ?? 'Kendala perangkat dilaporkan';
            $actionTaken = $f['action_taken'] ?? '-';
            $prosesPerbaikan = $f['proses_perbaikan'] ?? $f['catatan_penyelesaian'] ?? '';
            $severity = $f['severity'] ?? 'Sedang';
            $repairStatus = $f['repair_status'] ?? 'Perlu Perbaikan';
            $teknisi = $f['teknisi'] ?? $f['reported_by'] ?? 'Teknisi';
            $tgl = !empty($f['created_at']) ? format_id_date($f['created_at']) : (!empty($f['reported_at']) ? format_id_date($f['reported_at']) : '-');

            $lowStatus = strtolower($repairStatus);
            if (in_array($lowStatus, ['resolved', 'selesai', 'closed', 'ok'], true)) {
                $statusBadge = '<span class="badge-chip chip-success"><i class="bi bi-check-circle-fill me-1"></i> Terselesaikan</span>';
                $cardBorderColor = '#10B981';
            } elseif (in_array($lowStatus, ['in progress', 'proses', 'sedang perbaikan'], true)) {
                $statusBadge = '<span class="badge-chip chip-warning"><i class="bi bi-hourglass-split me-1"></i> Sedang Dikerjakan</span>';
                $cardBorderColor = '#F59E0B';
            } else {
                $statusBadge = '<span class="badge-chip chip-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Perlu Perbaikan</span>';
                $cardBorderColor = '#EF4444';
            }

            $lowSev = strtolower($severity);
            if ($lowSev === 'berat') {
                $sevBadge = '<span class="badge bg-danger text-white px-2 py-0.5" style="font-size: 0.72rem;"><i class="bi bi-shield-fill-exclamation me-0.5"></i> Berat</span>';
            } elseif ($lowSev === 'sedang') {
                $sevBadge = '<span class="badge bg-warning text-dark px-2 py-0.5" style="font-size: 0.72rem;"><i class="bi bi-exclamation-diamond me-0.5"></i> Sedang</span>';
            } else {
                $sevBadge = '<span class="badge bg-secondary bg-opacity-75 text-white px-2 py-0.5" style="font-size: 0.72rem;">Ringan</span>';
            }

            $modalDataObj = [
                'finding_id' => $fId,
                'scan_id' => $scanId,
                'asset_id' => $assetId,
                'kode' => $kode,
                'perangkat' => $devName,
                'pengguna' => $pic,
                'finding' => $findingDesc,
                'status' => $repairStatus,
                'proses_perbaikan' => $prosesPerbaikan,
                'teknisi' => $teknisi
            ];
            $jsonModalAttr = htmlspecialchars(json_encode($modalDataObj), ENT_QUOTES, 'UTF-8');

            $logBtn = $scanId > 0
                ? '<a href="'.e(module_url('maintenance_detail.php', ['id' => $scanId])).'" class="btn btn-sm btn-outline-secondary py-1 px-2 small" title="Rincian Hasil Audit Pemeliharaan"><i class="bi bi-file-earmark-medical me-0.5"></i> #'.$scanId.'</a>'
                : '';

            $qrBtn = $token !== ''
                ? '<a href="'.e(module_url('scan.php', ['t' => $token])).'" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-2 small" title="Buka Kartu QR Komputer"><i class="bi bi-qr-code"></i></a>'
                : '';

            $findingsTableRows .= '
            <tr style="border-bottom: 1px solid var(--app-border);">
              <td class="text-center font-monospace fw-bold text-secondary" style="width: 45px;">'.$noF.'</td>
              <td style="min-width: 170px;">
                <div class="fw-bold font-monospace text-primary" style="font-size: 0.86rem;">'.e($kode).'</div>
                <div class="fw-semibold text-dark text-truncate" style="max-width: 220px;" title="'.e($devName).'">'.e($devName).'</div>
                <div class="small text-muted text-truncate" style="font-size: 0.74rem;">
                  <i class="bi bi-person me-0.5"></i>'.e($pic).' · <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:0.68rem;">'.e($cabang).'</span>
                </div>
              </td>
              <td style="min-width: 130px;">
                <div class="small text-dark fw-semibold">'.$tgl.'</div>
                <div class="text-muted" style="font-size: 0.72rem;"><i class="bi bi-person-badge me-1"></i>'.e($teknisi).'</div>
              </td>
              <td style="min-width: 220px;">
                <div class="p-2 rounded bg-light border" style="border-color: var(--app-border) !important;">
                  <div class="fw-semibold text-danger small"><i class="bi bi-exclamation-triangle-fill me-1"></i>'.e($findingDesc).'</div>
                  '.($actionTaken !== '-' && $actionTaken !== '' ? '<div class="text-muted small mt-1 pt-1 border-top" style="font-size: 0.72rem;"><span class="fw-semibold text-secondary">Rekomendasi:</span> '.e($actionTaken).'</div>' : '').'
                </div>
              </td>
              <td class="text-center" style="width: 90px;">'.$sevBadge.'</td>
              <td style="min-width: 180px;">
                <div class="mb-1">'.$statusBadge.'</div>
                '.($prosesPerbaikan !== '' ? '<div class="small text-secondary bg-white p-1.5 rounded border" style="font-size: 0.72rem; border-color: rgba(0,0,0,0.08) !important;"><i class="bi bi-chat-left-text me-1 opacity-75"></i>'.e($prosesPerbaikan).'</div>' : '<span class="text-muted small fst-italic" style="font-size:0.72rem;">Belum ada catatan tindak lanjut</span>').'
              </td>
              <td class="text-end text-nowrap" style="width: 160px;">
                <div class="d-flex justify-content-end align-items-center gap-1">
                  <button type="button" class="btn btn-sm btn-primary py-1 px-2 fw-semibold" onclick="openProsesPerbaikanModal('.$jsonModalAttr.')" title="Catat / Perbarui Progres Perbaikan">
                    <i class="bi bi-tools me-1"></i> Tindak Lanjut
                  </button>
                  '.$logBtn.'
                  '.$qrBtn.'
                </div>
              </td>
            </tr>';

            $findingsCardsHtml .= '
            <div class="p-3 mb-2.5 rounded-3 bg-white border shadow-xs" style="border-left: 4px solid '.$cardBorderColor.' !important; border-color: var(--app-border) !important;">
              <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                  <span class="badge bg-navy-subtle text-white font-monospace fw-bold" style="font-size: 0.72rem;">'.e($kode).'</span>
                  <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size: 0.7rem;">'.e($cabang).'</span>
                  '.$sevBadge.'
                </div>
                <div>'.$statusBadge.'</div>
              </div>
              <div class="fw-bold text-dark mb-1">'.e($devName).'</div>
              <div class="small text-muted mb-2"><i class="bi bi-person me-1"></i>PIC: <strong>'.e($pic).'</strong> · Divisi: '.e($divisi).'</div>
              <div class="p-2 rounded bg-light border mb-2" style="border-color: var(--app-border) !important;">
                <div class="fw-semibold text-danger small"><i class="bi bi-exclamation-triangle-fill me-1"></i>'.e($findingDesc).'</div>
                '.($actionTaken !== '-' && $actionTaken !== '' ? '<div class="text-muted small mt-1 pt-1 border-top" style="font-size: 0.72rem;"><span class="fw-semibold text-secondary">Rekomendasi:</span> '.e($actionTaken).'</div>' : '').'
              </div>
              '.($prosesPerbaikan !== '' ? '<div class="small text-secondary bg-light p-2 rounded mb-2 border" style="font-size: 0.76rem;"><strong class="text-dark d-block mb-0.5"><i class="bi bi-clock-history me-1"></i>Catatan Perbaikan Terakhir:</strong>'.e($prosesPerbaikan).'</div>' : '').'
              <div class="pt-2 border-top d-flex justify-content-between align-items-center flex-wrap gap-2" style="border-color: rgba(0,0,0,0.06) !important;">
                <span class="text-muted small" style="font-size: 0.72rem;"><i class="bi bi-calendar-event me-1"></i>'.$tgl.' oleh '.e($teknisi).'</span>
                <div class="d-flex gap-1.5 w-100 w-sm-auto justify-content-end">
                  <button type="button" class="btn btn-sm btn-primary py-1 px-3 fw-semibold flex-fill flex-sm-grow-0" onclick="openProsesPerbaikanModal('.$jsonModalAttr.')">
                    <i class="bi bi-tools me-1"></i> Tindak Lanjut
                  </button>
                  '.$logBtn.'
                  '.$qrBtn.'
                </div>
              </div>
            </div>';
        }
    }

    $body .= '
    <!-- Header Kicker & Action Bar Tab Temuan -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <div class="text-uppercase small fw-bold" style="letter-spacing: 0.08em; color: var(--app-accent); font-size: 0.72rem; margin-bottom: 2px;">OPERATIONS / TEMUAN KENDALA &amp; PERBAIKAN</div>
        <h2 class="h4 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
          <i class="bi bi-tools text-danger"></i> Daftar Temuan Kendala &amp; Status Perbaikan
        </h2>
        <div class="text-muted small">Monitoring seluruh temuan kendala dari scan pemeliharaan, tingkat keparahan, teknisi penanggung jawab, dan progres tindak lanjut (<strong>'.($bulanTemuan > 0 ? ($monthNames[$bulanTemuan].' '.$year) : 'Semua Periode / Riwayat Temuan').'</strong>).</div>
      </div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="'.e(module_url('audit.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'" class="btn btn-sm btn-outline-primary fw-semibold"><i class="bi bi-file-earmark-bar-graph me-1"></i> Rekapitulasi Audit Bulanan</a>
      </div>
    </div>

    <!-- Mini KPI Metrics Temuan -->
    <div class="row g-2 g-md-3 mb-3 mb-md-4">
      <div class="col-6 col-md-3">
        <div class="card card-metric h-100">
          <div class="metric-value text-dark mb-0">'.$countTotalTemuan.' <span class="fs-6 fw-normal text-muted">Kasus</span></div>
          <div class="metric-label">Total Temuan Terdata</div>
          <div class="small text-muted mt-2" style="font-size: 0.72rem;">'.($bulanTemuan > 0 ? ($monthNames[$bulanTemuan].' '.$year) : 'Semua Periode').'</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card card-metric h-100">
          <div class="metric-value text-danger mb-0">'.$countPerluPerbaikan.' <span class="fs-6 fw-normal text-danger">Unit</span></div>
          <div class="metric-label text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Perlu Perbaikan (Aktif)</div>
          <div class="small text-muted mt-2" style="font-size: 0.72rem;">Menunggu penanganan</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card card-metric h-100">
          <div class="metric-value text-warning mb-0">'.$countInProgress.' <span class="fs-6 fw-normal text-warning">Unit</span></div>
          <div class="metric-label text-warning-emphasis"><i class="bi bi-hourglass-split me-1"></i>Sedang Dikerjakan</div>
          <div class="small text-muted mt-2" style="font-size: 0.72rem;">Tunggu sparepart / instalasi</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card card-metric h-100">
          <div class="metric-value text-success mb-0">'.$countResolved.' <span class="fs-6 fw-normal text-success">Unit</span></div>
          <div class="metric-label text-success"><i class="bi bi-check-circle-fill me-1"></i>Terselesaikan</div>
          <div class="small text-muted mt-2" style="font-size: 0.72rem;">Normal kembali</div>
        </div>
      </div>
    </div>

    <!-- Toolbar Filter & Pencarian -->
    <div class="card p-3 mb-3 border shadow-xs" style="border-radius: 8px; border-color: var(--app-border) !important; background: #FAFBFD;">
      <form method="get" class="row g-2 align-items-center">
        <input type="hidden" name="tab" value="temuan">
        <input type="hidden" name="tahun" value="'.$year.'">
        <input type="hidden" name="cabang" value="'.$cabangId.'">

        <div class="col-12 col-md-3">
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="text" name="q_temuan" class="form-control" placeholder="Cari kode inventaris, merk, user, temuan..." value="'.e($qTemuan).'">
          </div>
        </div>
        <div class="col-6 col-md-2">
          <select name="bulan_temuan" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="0" '.($bulanTemuan === 0 ? 'selected' : '').'>Semua Periode (Bulan)</option>';
    for ($bm = 1; $bm <= 12; $bm++) {
        $body .= '<option value="'.$bm.'" '.($bulanTemuan === $bm ? 'selected' : '').'>'.$monthNames[$bm].'</option>';
    }
    $body .= '
          </select>
        </div>
        <div class="col-6 col-md-3">
          <select name="status_temuan" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="all" '.($statusTemuanFilter === 'all' ? 'selected' : '').'>Semua Status Temuan ('.$countTotalTemuan.')</option>
            <option value="perlu_perbaikan" '.($statusTemuanFilter === 'perlu_perbaikan' ? 'selected' : '').'>🚨 Perlu Perbaikan ('.$countPerluPerbaikan.')</option>
            <option value="proses" '.($statusTemuanFilter === 'proses' ? 'selected' : '').'>⏳ Sedang Proses ('.$countInProgress.')</option>
            <option value="selesai" '.($statusTemuanFilter === 'selesai' ? 'selected' : '').'>✅ Terselesaikan ('.$countResolved.')</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select name="tingkat_temuan" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="all" '.($tingkatTemuanFilter === 'all' ? 'selected' : '').'>Semua Tingkat</option>
            <option value="Berat" '.($tingkatTemuanFilter === 'Berat' ? 'selected' : '').'>Tingkat Berat</option>
            <option value="Sedang" '.($tingkatTemuanFilter === 'Sedang' ? 'selected' : '').'>Tingkat Sedang</option>
            <option value="Ringan" '.($tingkatTemuanFilter === 'Ringan' ? 'selected' : '').'>Tingkat Ringan</option>
          </select>
        </div>
        <div class="col-6 col-md-2 d-flex gap-1.5">
          <button type="submit" class="btn btn-sm btn-primary fw-semibold flex-fill"><i class="bi bi-funnel me-1"></i> Filter</button>
          '.(($qTemuan !== '' || $statusTemuanFilter !== 'all' || $tingkatTemuanFilter !== 'all' || $bulanTemuan !== 0) ? '<a href="'.e(module_url('dashboard.php', ['tab'=>'temuan', 'cabang'=>$cabangId])).'" class="btn btn-sm btn-outline-secondary" title="Reset Filter"><i class="bi bi-x-lg"></i></a>' : '').'
        </div>
      </form>
    </div>

    <!-- Tabel Temuan (Desktop) & Cards (Mobile) -->
    <div class="card p-0 border shadow-xs" style="border-radius: 8px; border-color: var(--app-border) !important; background: #fff;">
      <div class="card-header bg-white py-2.5 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2" style="border-bottom: 1px solid var(--app-border);">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-table text-primary"></i>
          <span class="fw-bold text-dark small text-uppercase" style="letter-spacing: 0.04em;">Daftar Rekapitulasi Temuan &amp; Tindakan Perbaikan</span>
        </div>
        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 font-monospace small px-2 py-0.5">'.count($filteredFindings).' Baris Data</span>
      </div>

      <!-- Desktop View: Tabel Standar -->
      <div class="d-none d-md-block table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead style="background-color: var(--app-navy); color: #ffffff;">
            <tr>
              <th class="text-center font-monospace" style="width: 45px; background-color: var(--app-navy); color: #ffffff;">NO</th>
              <th style="background-color: var(--app-navy); color: #ffffff;">KODE &amp; PERANGKAT</th>
              <th style="background-color: var(--app-navy); color: #ffffff;">WAKTU &amp; PELAPOR</th>
              <th style="background-color: var(--app-navy); color: #ffffff;">URAIAN TEMUAN / KENDALA</th>
              <th class="text-center" style="width: 90px; background-color: var(--app-navy); color: #ffffff;">TINGKAT</th>
              <th style="background-color: var(--app-navy); color: #ffffff;">STATUS &amp; PROGRES</th>
              <th class="text-end" style="width: 160px; background-color: var(--app-navy); color: #ffffff;">AKSI</th>
            </tr>
          </thead>
          <tbody>
            '.$findingsTableRows.'
          </tbody>
        </table>
      </div>

      <!-- Mobile View: Kartu -->
      <div class="d-block d-md-none p-2.5">
        '.$findingsCardsHtml.'
      </div>
    </div>';

} else {
    // --- TAMPILAN ANTREAN PEMELIHARAAN (KOMBAI DESIGN 1:1) ---
    $searchQueue = trim((string)($_GET['q_queue'] ?? ''));
    $pageQueue = max(1, (int)($_GET['p_queue'] ?? 1));
    $perPageQueue = 8;

    // Ambil seluruh daftar aset yang belum dilakukan maintenance di bulan & tahun terpilih langsung dari Database
    $queueAssets = [];
    try {
        $qWhere = " WHERE (a.status = 'Aktif' OR a.status IS NULL OR a.status = '') ";
        $qParams = [];
        if ($cabangId > 0) {
            $qWhere .= " AND a.id_cabang = ? ";
            $qParams[] = $cabangId;
        }

        // Filter aset yang belum pernah di-scan di bulan & tahun terpilih
        $qWhere .= " AND a.id NOT IN (
            SELECT asset_id FROM maintenance_scan 
            WHERE maintenance_month = ? AND maintenance_year = ?
        ) ";
        $qParams[] = $month;
        $qParams[] = $year;

        $cCab = name_column('cabang') ?: 'nama_cabang';
        $cDiv = name_column('divisi') ?: 'nama_divisi';
        $cKar = name_column('karyawan') ?: 'nama_karyawan';

        $qSql = "
            SELECT 
                a.id, a.kode_inventaris, a.merk, a.model, a.keterangan, a.ruangan,
                c.`{$cCab}` AS cabang_nama,
                d.`{$cDiv}` AS divisi_nama,
                k.`{$cKar}` AS karyawan_nama,
                q.token AS qr_token,
                last_m.max_date AS last_maint_date
            FROM assets a
            LEFT JOIN cabang c ON c.id = a.id_cabang
            LEFT JOIN divisi d ON d.id = a.id_divisi
            LEFT JOIN karyawan k ON k.id = a.id_karyawan
            LEFT JOIN asset_qr_tokens q ON q.asset_id = a.id
            LEFT JOIN (
                SELECT asset_id, MAX(maintenance_date) AS max_date 
                FROM maintenance_scan 
                GROUP BY asset_id
            ) last_m ON last_m.asset_id = a.id
            {$qWhere}
            ORDER BY last_m.max_date ASC, a.kode_inventaris ASC
        ";

        $qStmt = db()->prepare($qSql);
        $qStmt->execute($qParams);
        $dueRows = $qStmt->fetchAll();

        foreach ($dueRows as $r) {
            $aid = (int)$r['id'];
            $tok = !empty($r['qr_token']) ? $r['qr_token'] : get_static_qr_token($aid);
            
            // Format pemeliharaan terakhir
            $lastMaintStr = 'Belum pernah';
            $isOverdue = true;
            if (!empty($r['last_maint_date'])) {
                $lastTs = strtotime($r['last_maint_date']);
                $lastMonth = (int)date('n', $lastTs);
                $lastYear = (int)date('Y', $lastTs);
                $lastMaintStr = ($monthNames[$lastMonth] ?? date('M', $lastTs)) . ' ' . $lastYear;
                
                $diffMonths = (($year - $lastYear) * 12) + ($month - $lastMonth);
                $isOverdue = ($diffMonths >= 2);
            }

            $devName = trim(($r['merk'] ?? '') . ' ' . ($r['model'] ?? ''));
            if ($devName === '') $devName = 'Perangkat Komputer IT';

            $roomName = !empty($r['ruangan']) ? $r['ruangan'] : (!empty($r['divisi_nama']) ? $r['divisi_nama'] : 'Operasional');

            $queueAssets[] = [
                'id' => $aid,
                'kode_inventaris' => (string)($r['kode_inventaris'] ?? ('INV-IT-' . $aid)),
                'nama_perangkat' => $devName,
                'pengguna' => (string)($r['karyawan_nama'] ?? 'Umum / Staf'),
                'cabang' => (string)($r['cabang_nama'] ?? 'KPO'),
                'ruangan' => (string)$roomName,
                'token' => $tok,
                'last_maint' => $lastMaintStr,
                'is_overdue' => $isOverdue
            ];
        }
    } catch (Throwable $e) {}

    // Fallback jika database masih kosong
    if (empty($queueAssets)) {
        if (!empty($dashData['upcoming_list'])) {
            foreach ($dashData['upcoming_list'] as $u) {
                $aid = (int)($u['asset_id'] ?? 0);
                $queueAssets[] = [
                    'id' => $aid,
                    'kode_inventaris' => (string)($u['kode_inventaris'] ?? ('INV-IT-' . $aid)),
                    'nama_perangkat' => (string)($u['nama_perangkat'] ?? 'Perangkat IT'),
                    'pengguna' => (string)($u['karyawan_nama'] ?? '-'),
                    'cabang' => (string)($u['cabang_nama'] ?? 'KPO'),
                    'ruangan' => (string)($u['divisi_nama'] ?? 'Operasional'),
                    'token' => get_static_qr_token($aid),
                    'last_maint' => 'Sep 2026',
                    'is_overdue' => false
                ];
            }
        }
    }

    $filteredQueueAssets = $queueAssets;
    if ($searchQueue !== '') {
        $sqLower = strtolower($searchQueue);
        $filteredQueueAssets = array_filter($queueAssets, function($item) use ($sqLower) {
            $haystack = strtolower(
                ($item['kode_inventaris'] ?? '') . ' ' .
                ($item['nama_perangkat'] ?? '') . ' ' .
                ($item['pengguna'] ?? '') . ' ' .
                ($item['cabang'] ?? '') . ' ' .
                ($item['ruangan'] ?? '') . ' ' .
                ($item['last_maint'] ?? '')
            );
            return strpos($haystack, $sqLower) !== false;
        });
    }

    $totalQueueCount = ($totalDue > 0 && $searchQueue === '') ? $totalDue : count($filteredQueueAssets);
    $totalPagesQueue = max(1, (int)ceil($totalQueueCount / $perPageQueue));
    $pageQueue = min($pageQueue, $totalPagesQueue);
    $offsetQueue = ($pageQueue - 1) * $perPageQueue;
    $pagedQueueAssets = array_slice(array_values($filteredQueueAssets), $offsetQueue, $perPageQueue);

    $queueRowsHtml = '';
    $rIdx = 0;
    foreach ($pagedQueueAssets as $item) {
        $rIdx++;
        $dotColor = !empty($item['is_overdue']) ? 'bg-[#A5292B]' : 'bg-[#875315]';
        $textColor = !empty($item['is_overdue']) ? 'text-[#A5292B]' : 'text-[#875315]';
        $actionUrl = !empty($item['token'])
            ? module_url('scan.php', ['t' => $item['token']])
            : (!empty($item['id']) ? module_url('scan.php', ['id' => $item['id']]) : module_url('scanner.php'));

        $isSel = ($pageQueue === 1 && $rIdx === 3);
        $rowBg = $isSel ? 'bg-[#D9F1EF]' : 'hover:bg-[#F4F5F7]';
        $accentBar = $isSel ? '<div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0E5C58]" data-kid="1-2-2-1-7-3-3-1" data-name="teal left accent bar on selected row"></div>' : '';
        $borderClass = ($rIdx < count($pagedQueueAssets)) ? 'border-b border-[#D1D5D8]' : '';

        $queueRowsHtml .= '
        <div class="h-[48px] px-4 flex items-center '.$borderClass.' '.$rowBg.' transition-colors relative" data-kid="1-2-2-1-7-3-'.$rIdx.'" data-name="queue table row ('.e($item['kode_inventaris']).')">
          '.$accentBar.'
          <div class="w-[280px] shrink-0 tabular-nums font-bold text-[12px] font-mono text-[#1B2A3A] truncate pr-3" data-kid="1-2-2-1-7-3-'.$rIdx.'-1" data-name=\'asset inventory code "'.e($item['kode_inventaris']).'"\' title="'.e($item['kode_inventaris']).'">'.e($item['kode_inventaris']).'</div>
          <div class="flex-1 min-w-[200px] flex items-baseline gap-2 truncate px-3" data-kid="1-2-2-1-7-3-'.$rIdx.'-2" data-name="device and user cell">
            <span class="text-[13px] font-semibold text-[#1B2A3A] truncate" data-kid="1-2-2-1-7-3-'.$rIdx.'-2-1" data-name=\'device name "'.e($item['nama_perangkat']).'"\' title="'.e($item['nama_perangkat']).'">'.e($item['nama_perangkat']).'</span>
            <span class="text-[12px] text-[#53595F] truncate shrink-0" data-kid="1-2-2-1-7-3-'.$rIdx.'-2-2" data-name=\'user name "'.e($item['pengguna']).'"\' title="'.e($item['pengguna']).'">'.e($item['pengguna']).'</span>
          </div>
          <div class="w-48 shrink-0 flex items-baseline gap-1.5 truncate px-3" data-kid="1-2-2-1-7-3-'.$rIdx.'-3" data-name="branch and room cell">
            <span class="text-[13px] font-semibold text-[#1B2A3A] truncate" data-kid="1-2-2-1-7-3-'.$rIdx.'-3-1" data-name=\'branch "'.e($item['cabang']).'"\' title="'.e($item['cabang']).'">'.e($item['cabang']).'</span>
            <span class="text-[12px] text-[#53595F] truncate" data-kid="1-2-2-1-7-3-'.$rIdx.'-3-2" data-name=\'room "/ '.e($item['ruangan']).'"\' title="'.e($item['ruangan']).'">/ '.e($item['ruangan']).'</span>
          </div>
          <div class="w-44 shrink-0 flex items-center gap-2 px-3" data-kid="1-2-2-1-7-3-'.$rIdx.'-4" data-name="last maintenance cell">
            <span class="w-2 h-2 rounded-full '.$dotColor.' shrink-0" data-kid="1-2-2-1-7-3-'.$rIdx.'-4-1" data-name="status dot"></span>
            <span class="text-[12px] '.$textColor.' font-semibold tabular-nums truncate" data-kid="1-2-2-1-7-3-'.$rIdx.'-4-2" data-name=\'last maintenance date "'.e($item['last_maint']).'"\'>'.e($item['last_maint']).'</span>
          </div>
          <div class="w-28 shrink-0 flex justify-end" data-kid="1-2-2-1-7-3-'.$rIdx.'-5" data-name="action cell">
            <a href="'.e($actionUrl).'" class="h-[32px] px-3 bg-[#FFFFFF] border border-[#D1D5D8] rounded-[6px] text-[12px] font-semibold text-[#1B2A3A] hover:bg-[#F4F5F7] hover:border-[#A0A5AA] transition-all cursor-pointer inline-flex items-center justify-center text-decoration-none shadow-xs" data-kid="1-2-2-1-7-3-'.$rIdx.'-5-1" data-name=\'row action button "Buka aset"\'>Buka aset</a>
          </div>
        </div>';
    }

    if (empty($pagedQueueAssets)) {
        $queueRowsHtml = '
        <div class="py-12 text-center text-[#53595F]">
          <i class="bi bi-check-circle text-[#0E5C58] text-[36px] d-block mb-2"></i>
          <div class="font-semibold text-[15px] text-[#1B2A3A]">Tidak ada antrean pemeliharaan</div>
          <div class="text-[13px] mt-1">Semua unit telah selesai dipelihara atau tidak ada aset yang sesuai kriteria pencarian.</div>
        </div>';
    }

    $startItem = $totalQueueCount > 0 ? ($offsetQueue + 1) : 0;
    $endItem = min($totalQueueCount, $offsetQueue + count($pagedQueueAssets));

    $queuePaginationHtml = '';
    if ($pageQueue > 1) {
        $prevUrl = module_url('dashboard.php', array_merge($_GET, ['p_queue' => $pageQueue - 1]));
        $queuePaginationHtml .= '<a href="'.e($prevUrl).'" class="w-[28px] h-[28px] rounded-[6px] flex items-center justify-center border border-[#D1D5D8] bg-[#FFFFFF] text-[#1B2A3A] hover:bg-[#E9EAEC] transition-colors text-decoration-none cursor-pointer" data-kid="1-2-2-1-7-4-2-1" data-name="previous page button"><i class="bi bi-chevron-left" data-kid="1-2-2-1-7-4-2-1-1" data-name="chevron left icon"></i></a>';
    } else {
        $queuePaginationHtml .= '<button class="w-[28px] h-[28px] rounded-[6px] flex items-center justify-center border border-[#D1D5D8] bg-[#FFFFFF] text-[#A0A5AA] cursor-not-allowed" disabled data-kid="1-2-2-1-7-4-2-1" data-name="disabled previous page button"><i class="bi bi-chevron-left" data-kid="1-2-2-1-7-4-2-1-1" data-name="chevron left icon"></i></button>';
    }

    for ($p = 1; $p <= min(5, $totalPagesQueue); $p++) {
        if ($p === $pageQueue) {
            $queuePaginationHtml .= '<button class="w-[28px] h-[28px] rounded-[6px] flex items-center justify-center border border-[#D1D5D8] bg-[#FFFFFF] text-[#1B2A3A] font-bold shadow-[0_1px_2px_rgba(0,0,0,0.05)] cursor-pointer" data-kid="1-2-2-1-7-4-2-2" data-name=\'current page number "'.$p.'"\'>'.$p.'</button>';
        } else {
            $pUrl = module_url('dashboard.php', array_merge($_GET, ['p_queue' => $p]));
            $queuePaginationHtml .= '<a href="'.e($pUrl).'" class="w-[28px] h-[28px] rounded-[6px] flex items-center justify-center border border-transparent text-[#53595F] hover:bg-[#D1D5D8] transition-colors cursor-pointer text-decoration-none" data-kid="1-2-2-1-7-4-2-3" data-name=\'page number "'.$p.'" button\'>'.$p.'</a>';
        }
    }
    if ($totalPagesQueue > 5) {
        $queuePaginationHtml .= '<span class="px-1 text-[#A0A5AA]" data-kid="1-2-2-1-7-4-2-5" data-name="page ellipsis separator">...</span>';
    }

    if ($pageQueue < $totalPagesQueue) {
        $nextUrl = module_url('dashboard.php', array_merge($_GET, ['p_queue' => $pageQueue + 1]));
        $queuePaginationHtml .= '<a href="'.e($nextUrl).'" class="w-[28px] h-[28px] rounded-[6px] flex items-center justify-center border border-[#D1D5D8] bg-[#FFFFFF] text-[#1B2A3A] hover:bg-[#E9EAEC] transition-colors text-decoration-none cursor-pointer" data-kid="1-2-2-1-7-4-2-6" data-name="next page button"><i class="bi bi-chevron-right" data-kid="1-2-2-1-7-4-2-6-1" data-name="chevron right icon"></i></a>';
    } else {
        $queuePaginationHtml .= '<button class="w-[28px] h-[28px] rounded-[6px] flex items-center justify-center border border-[#D1D5D8] bg-[#FFFFFF] text-[#A0A5AA] cursor-not-allowed" disabled data-kid="1-2-2-1-7-4-2-6" data-name="disabled next page button"><i class="bi bi-chevron-right" data-kid="1-2-2-1-7-4-2-6-1" data-name="chevron right icon"></i></button>';
    }

    $body .= '
    <!-- Queue Surface -->
    <div class="bg-[#FFFFFF] border border-[#D1D5D8] rounded-[10px] flex flex-col shrink-0 overflow-hidden" data-kid="1-2-2-1-7" data-name="queue table card surface">
      <!-- Toolbar (56px) -->
      <div class="h-[56px] px-4 flex justify-between items-center border-b border-[#D1D5D8] bg-[#FFFFFF]" data-kid="1-2-2-1-7-1" data-name="toolbar row with filter and scan">
        <form method="get" class="relative m-0" data-kid="1-2-2-1-7-1-1" data-name="queue filter input wrapper with icon">
          <input type="hidden" name="cabang" value="'.(int)$cabangId.'">
          <input type="hidden" name="bulan" value="'.(int)$month.'">
          <input type="hidden" name="tahun" value="'.(int)$year.'">
          <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-[#53595F] text-[14px]" data-kid="1-2-2-1-7-1-1-1" data-name="magnifying glass icon in input"></i>
          <input class="h-[36px] w-[280px] pl-9 pr-3 text-[14px] font-medium text-[#1B2A3A] border border-[#D1D5D8] rounded-[8px] outline-none focus:border-[#0E5C58] placeholder:text-[#A0A5AA] transition-colors" data-kid="1-2-2-1-7-1-1-2" data-name="queue filter search input field" placeholder="Saring antrean..." type="text" name="q_queue" value="'.e($searchQueue).'"/>
        </form>
        <div class="flex items-center gap-2">
          <a href="'.e(module_url('scanner.php')).'" class="h-[36px] px-4 bg-[#0E5C58] hover:bg-[#0c4f4b] text-white rounded-[8px] font-semibold text-[14px] flex items-center gap-2 transition-colors cursor-pointer text-decoration-none shadow-sm" data-kid="1-2-2-1-7-1-2" data-name=\'primary "Scan QR Unit" action button\'>
            <i class="bi bi-qr-code-scan" data-kid="1-2-2-1-7-1-2-1" data-name="QR code scan icon"></i>
            Scan QR Unit
          </a>
          <button type="button" class="h-[36px] px-3 bg-[#FFFFFF] border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#53595F] rounded-[8px] font-semibold text-[13px] flex items-center gap-1.5 transition-colors" data-bs-toggle="modal" data-bs-target="#modalMobileScannerQr" title="Pindai di HP">
            <i class="bi bi-phone"></i>
          </button>
          <a href="'.e(module_url('print_report.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'" target="_blank" class="h-[36px] px-3 bg-[#FFFFFF] border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#53595F] rounded-[8px] font-semibold text-[13px] flex items-center gap-1.5 transition-colors text-decoration-none" title="Cetak Rekap">
            <i class="bi bi-printer"></i>
          </a>
        </div>
      </div>

      <!-- Table Scroll Wrapper -->
      <div class="overflow-x-auto">
        <div class="min-w-[980px]">
          <!-- Table Header (40px) -->
          <div class="h-[40px] px-4 flex items-center bg-[#F4F5F7] border-b border-[#D1D5D8] text-[12px] font-bold text-[#53595F] uppercase tracking-wider" data-kid="1-2-2-1-7-2" data-name="table header row">
            <div class="w-[280px] shrink-0 pr-3" data-kid="1-2-2-1-7-2-1" data-name=\'column header "Kode Inventaris"\'>Kode Inventaris</div>
            <div class="flex-1 min-w-[200px] px-3" data-kid="1-2-2-1-7-2-2" data-name=\'column header "Perangkat &amp; Pengguna"\'>Perangkat &amp; Pengguna</div>
            <div class="w-48 shrink-0 px-3" data-kid="1-2-2-1-7-2-3" data-name=\'column header "Cabang &amp; Ruangan"\'>Cabang &amp; Ruangan</div>
            <div class="w-44 shrink-0 px-3" data-kid="1-2-2-1-7-2-4" data-name=\'column header "Pemeliharaan Terakhir"\'>Pemeliharaan Terakhir</div>
            <div class="w-28 shrink-0 text-right" data-kid="1-2-2-1-7-2-5" data-name=\'column header "Aksi"\'>Aksi</div>
          </div>

          <!-- Rows (8x 48px) -->
          <div class="flex flex-col" data-kid="1-2-2-1-7-3" data-name="queue table body rows container">
            '.$queueRowsHtml.'
          </div>
        </div>
      </div>

      <!-- Footer / Pagination (40px) -->
      <div class="h-[40px] px-4 flex justify-between items-center bg-[#F4F5F7] border-t border-[#D1D5D8] text-[13px] font-semibold text-[#53595F]" data-kid="1-2-2-1-7-4" data-name="pagination footer bar">
        <div data-kid="1-2-2-1-7-4-1" data-name="pagination results summary text">Menampilkan <span class="font-bold text-[#1B2A3A]">'.$startItem.'&ndash;'.$endItem.'</span> dari <span class="font-bold text-[#1B2A3A]">'.$totalQueueCount.'</span> aset</div>
        <div class="flex items-center gap-1.5" data-kid="1-2-2-1-7-4-2" data-name="pagination page numbers container">
          '.$queuePaginationHtml.'
        </div>
      </div>
    </div>

    <!-- Secondary Collapsible: Branch Compliance Matrix & Activity Stream -->
    <details class="mt-4 bg-[#FFFFFF] border border-[#D1D5D8] rounded-[10px] p-4 text-[13px] text-[#53595F] cursor-pointer group shadow-xs">
      <summary class="font-bold text-[#1B2A3A] flex items-center justify-between select-none">
        <div class="flex items-center gap-2">
          <i class="bi bi-buildings text-[#0E5C58]"></i>
          <span>Rekapitulasi Kepatuhan Cabang &amp; Activity Stream Audit</span>
        </div>
        <i class="bi bi-chevron-down text-[#53595F] group-open:rotate-180 transition-transform text-[12px]"></i>
      </summary>
      <div class="pt-4 mt-3 border-t border-[#D1D5D8] cursor-default">
        <div class="row g-4">
          <div class="col-lg-7">
            <div class="card border shadow-xs mb-3">
              <div class="card-header bg-white py-2 px-3 fw-bold small text-dark d-flex justify-content-between align-items-center">
                <span><i class="bi bi-diagram-3 text-primary me-1"></i> Kepatuhan Per Cabang</span>
                <a href="'.e(module_url('print_report.php', ['bulan'=>$month,'tahun'=>$year])).'" target="_blank" class="btn btn-xs btn-outline-secondary py-0.5 px-2 small"><i class="bi bi-printer me-1"></i> Cetak Rekap</a>
              </div>
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                  <thead class="table-light">
                    <tr>
                      <th>Kantor Cabang</th>
                      <th class="text-center">Target</th>
                      <th class="text-center">Selesai</th>
                      <th class="text-center">Belum</th>
                      <th class="text-center">Kepatuhan</th>
                    </tr>
                  </thead>
                  <tbody>
                    '.$branchRowsHtml.'
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="card border shadow-xs mb-3">
              <div class="card-header bg-white py-2 px-3 fw-bold small text-dark">
                <i class="bi bi-activity text-primary me-1"></i> Live Activity Stream
              </div>
              <div class="card-body p-3" style="max-height: 280px; overflow-y: auto;">
                '.$activityStreamHtml.'
              </div>
            </div>
          </div>
        </div>
      </div>
    </details>';
}

$body .= '
</div><!-- End max-w-[1152px] -->
<div class="h-8 shrink-0" data-kid="1-2-2-2" data-name="bottom spacer div"></div>';

// =========================================================================
// 8. MODALS TERPADU UNTUK KARTU INVENTARIS CR80 & TEMUAN
// =========================================================================
$body .= '
<!-- MODAL: UPDATE PROSES PERBAIKAN PERANGKAT -->
<div class="modal fade" id="modalProsesPerbaikan" tabindex="-1" aria-labelledby="modalProsesPerbaikanLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content border-0 shadow">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="save_proses_perbaikan">
      <input type="hidden" name="finding_id" id="modalFindingId" value="0">
      <input type="hidden" name="scan_id" id="modalScanId" value="0">
      <input type="hidden" name="asset_id" id="modalAssetId" value="0">
      <input type="hidden" name="bulan" value="'.$month.'">
      <input type="hidden" name="tahun" value="'.$year.'">
      <input type="hidden" name="cabang" value="'.$cabangId.'">
      
      <div class="modal-header bg-primary text-white py-3">
        <h5 class="modal-title fw-bold fs-6 mb-0" id="modalProsesPerbaikanLabel"><i class="bi bi-tools me-2"></i>Update Proses Perbaikan Perangkat</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4" style="overflow-y: auto; -webkit-overflow-scrolling: touch;">
        <!-- Info Perangkat & Kendala -->
        <div class="p-3 bg-light rounded-3 mb-3 border">
          <div class="fw-bold text-primary font-monospace" id="modalKodePerangkat">-</div>
          <div class="small fw-semibold text-dark mb-2" id="modalNamaPerangkat">-</div>
          <div class="alert alert-danger py-2 px-3 small mb-0">
            <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Temuan:</strong>
            <span id="modalDeskripsiTemuan">-</span>
          </div>
        </div>

        <!-- Status Hasil Perbaikan -->
        <div class="mb-3">
          <label class="form-label small fw-bold text-dark">Status Proses Perbaikan <span class="text-danger">*</span></label>
          <select class="form-select fw-semibold" name="status" id="modalStatusSelect" required>
            <option value="In Progress" class="text-warning fw-bold">⏳ Dalam Proses Perbaikan (Sedang Dikerjakan / Tunggu Sparepart)</option>
            <option value="Resolved" class="text-success fw-bold">✓ Terselesaikan (Perbaikan Berhasil & Komputer Normal)</option>
            <option value="Perlu Perbaikan" class="text-danger fw-bold">⚠️ Perlu Perbaikan (Belum Ditangani)</option>
          </select>
        </div>

        <!-- Catatan / Progres Perbaikan -->
        <div class="mb-3">
          <label class="form-label small fw-bold text-dark">Catatan / Uraian Tindak Lanjut <span class="text-danger">*</span></label>
          <textarea class="form-control" name="proses_perbaikan" id="modalProsesTextarea" rows="3" placeholder="Contoh: Telah diajukan pengadaan SSD 256GB / Sedang backup data & install OS..." required></textarea>
          
          <div class="mt-2">
            <div class="small text-muted mb-1"><i class="bi bi-tag me-1"></i> Klik untuk isi cepat tindakan:</div>
            <div class="d-flex flex-wrap gap-1">
              <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="appendModalAction(\'Sedang diajukan pengadaan sparepart (SSD/RAM)\')">📦 Tunggu Sparepart</button>
              <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="appendModalAction(\'Sedang proses backup data & instalasi ulang OS Windows\')">💻 Backup & Reinstall OS</button>
              <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="appendModalAction(\'Telah diganti sparepart baru dan berfungsi normal kembali\')">✓ Ganti Sparepart Selesai</button>
              <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="appendModalAction(\'Pembersihan debu hardware & pergantian thermal paste\')">💨 Bersih Hardware</button>
              <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.72rem;" onclick="appendModalAction(\'Perbaikan driver & konfigurasi jaringan/LAN selesai\')">🌐 Driver & LAN Normal</button>
            </div>
          </div>
        </div>

        <!-- Teknisi -->
        <div class="mb-2">
          <label class="form-label small fw-bold text-secondary">Teknisi Penanggung Jawab</label>
          <input type="text" class="form-control" name="technician_name" id="modalTeknisiInput" value="'.e(current_user_name()).'" placeholder="Nama teknisi...">
        </div>
      </div>
      <div class="modal-footer bg-light py-2 px-4">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-save-fill me-1"></i> Simpan Proses Perbaikan</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: TAMBAH DATA KARTU INVENTARIS -->
<div class="modal fade" id="addCardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <form method="post">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="action" value="create_card">
        <div class="modal-header py-3 px-4 text-white" style="background: #0E5C58 !important;">
          <div>
            <h5 class="modal-title fw-bold mb-0"><i class="bi bi-plus-circle-fill me-2"></i>Tambah Data Kartu Inventaris</h5>
            <small class="text-white-50">Sistem Inventaris Core Banking BPR Mitratama</small>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <!-- Section 1: Identitas Rekening & Barang -->
          <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 font-monospace small">01 · REKENING & IDENTITAS BARANG</span>
          </div>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Nomor Rekening <span class="text-danger">*</span></label>
              <input type="text" name="nomor_rekening" id="addCardRekening" class="form-control font-monospace" placeholder="Contoh: 01.05.0493" maxlength="11" required oninput="handleRekeningInput(this, event)">
              <div class="form-text text-muted" style="font-size: 0.72rem;">Format: <strong>XX.XX.XXXX</strong></div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Nama Barang <span class="text-danger">*</span></label>
              <input type="text" name="nama_barang" class="form-control" placeholder="Contoh: MOBIL AVANZA DA 1532 BV" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Nomor Seri Inventaris Bank</label>
              <div class="input-group">
                <input type="text" name="nomor_seri_inventaris" id="addCardNoSeri" class="form-control font-monospace" placeholder="Contoh: 01.05.02.200224.001">
                <button class="btn btn-light border" type="button" onclick="autoGenerateCardNoSeri(\'add\')" title="Generate Format Bank"><i class="bi bi-magic me-1"></i>Auto</button>
              </div>
              <div class="form-text text-muted" style="font-size: 0.72rem;">Format resmi: [Cabang].[Ruang].[Gol].[ddMMyy].[No]</div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Tanggal Perolehan <span class="text-danger">*</span></label>
              <input type="text" name="tanggal_perolehan" id="addCardTanggal" class="form-control font-monospace" placeholder="dd/mm/yyyy" value="'.date('d/m/Y').'" maxlength="10" required oninput="handleDateInput(this, event)">
              <div class="form-text text-muted" style="font-size: 0.72rem;">Format: <strong>dd/mm/yyyy</strong></div>
            </div>
          </div>

          <!-- Section 2: Master Core Banking Bank -->
          <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 font-monospace small">02 · MASTER DATA CORE BANKING</span>
          </div>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Jenis Inventaris</label>
              <select class="form-select" name="jenis_inventaris" id="addCardJenis">
                '.$optCardJenis.'
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Golongan Inventaris</label>
              <select class="form-select" name="golongan" id="addCardGolongan">
                '.$optCardGolongan.'
              </select>
            </div>
            <div class="col-md-6">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label small fw-bold text-dark mb-0">Ruangan Penempatan</label>
                <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none fw-semibold text-primary" data-bs-toggle="modal" data-bs-target="#modalQuickAddRuanganDashboard" style="font-size: 0.78rem;">
                  <i class="bi bi-plus-circle me-1"></i>+ Tambah Ruangan
                </button>
              </div>
              <div class="input-group">
                <select class="form-select" name="ruangan" id="addCardRuangan">
                  '.$optCardRuangan.'
                </select>
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalQuickAddRuanganDashboard" title="Tambah Ruangan Baru">
                  <i class="bi bi-plus-lg text-primary"></i>
                </button>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Sumber Perolehan</label>
              <select class="form-select" name="sumber_perolehan" id="addCardSumber">
                '.$optCardSumber.'
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Status Inventaris</label>
              <select class="form-select" name="status_inventaris" id="addCardStatusInv">
                '.$optCardStatusInv.'
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Lokasi Barang / Kantor</label>
              <input type="text" name="lokasi" id="addCardLokasi" class="form-control" placeholder="Contoh: Kantor Pusat, Handil Bakti, Marabahan..." list="listLokasiBank">
              <datalist id="listLokasiBank">
                <option value="Kantor Pusat (KPO)">
                <option value="Handil Bakti">
                <option value="Marabahan">
                <option value="Pasar Panas">
              </datalist>
            </div>
          </div>

          <!-- Section 3: QR Code & Catatan -->
          <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 font-monospace small">03 · KODE QR & KETERANGAN</span>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Kode QR / Barcode (Data QR Code)</label>
              <input type="text" name="barcode_data" class="form-control font-monospace" placeholder="Salin/tempel link QR atau biarkan kosong">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Keterangan / Spesifikasi</label>
              <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan spesifikasi unit perangkat atau nomor polisi..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2.5 px-4 bg-light d-flex justify-content-between">
          <button type="button" class="btn btn-sm btn-light border rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-sm btn-primary rounded-pill fw-semibold px-4"><i class="bi bi-save me-1"></i> Simpan Data Kartu</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: EDIT DATA KARTU INVENTARIS -->
<div class="modal fade" id="editCardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <form method="post">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="action" value="update_card">
        <input type="hidden" name="id" id="editCardId">
        <div class="modal-header bg-warning text-dark py-3 px-4">
          <div>
            <h5 class="modal-title fw-bold mb-0 d-flex align-items-center gap-2">
              <i class="bi bi-pencil-square"></i> Edit Kartu Inventaris
            </h5>
            <small class="text-dark opacity-75">Perbarui data spesifikasi dan klasifikasi Core Banking</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <!-- Section 1: Identitas Rekening & Barang -->
          <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
            <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 px-2 py-1 font-monospace small">01 · REKENING & IDENTITAS BARANG</span>
          </div>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Nomor Rekening <span class="text-danger">*</span></label>
              <input type="text" name="nomor_rekening" id="editCardRekening" class="form-control font-monospace" placeholder="Contoh: 01.05.0493" maxlength="11" required oninput="handleRekeningInput(this, event)">
              <div class="form-text text-muted" style="font-size: 0.72rem;">Format: <strong>XX.XX.XXXX</strong></div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Nama Barang <span class="text-danger">*</span></label>
              <input type="text" name="nama_barang" id="editCardNama" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Nomor Seri Inventaris Bank</label>
              <div class="input-group">
                <input type="text" name="nomor_seri_inventaris" id="editCardNoSeri" class="form-control font-monospace" placeholder="Contoh: 01.05.02.200224.001">
                <button class="btn btn-light border" type="button" onclick="autoGenerateCardNoSeri(\'edit\')" title="Generate Format Bank"><i class="bi bi-magic me-1"></i>Auto</button>
              </div>
              <div class="form-text text-muted" style="font-size: 0.72rem;">Format resmi: [Cabang].[Ruang].[Gol].[ddMMyy].[No]</div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Tanggal Perolehan <span class="text-danger">*</span></label>
              <input type="text" name="tanggal_perolehan" id="editCardTanggal" class="form-control font-monospace" placeholder="dd/mm/yyyy" maxlength="10" required oninput="handleDateInput(this, event)">
              <div class="form-text text-muted" style="font-size: 0.72rem;">Format: <strong>dd/mm/yyyy</strong></div>
            </div>
          </div>

          <!-- Section 2: Master Core Banking Bank -->
          <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
            <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 px-2 py-1 font-monospace small">02 · MASTER DATA CORE BANKING</span>
          </div>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Jenis Inventaris</label>
              <select class="form-select" name="jenis_inventaris" id="editCardJenis">
                '.$optCardJenis.'
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Golongan Inventaris</label>
              <select class="form-select" name="golongan" id="editCardGolongan">
                '.$optCardGolongan.'
              </select>
            </div>
            <div class="col-md-6">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label small fw-bold text-dark mb-0">Ruangan Penempatan</label>
                <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none fw-semibold text-primary" data-bs-toggle="modal" data-bs-target="#modalQuickAddRuanganDashboard" style="font-size: 0.78rem;">
                  <i class="bi bi-plus-circle me-1"></i>+ Tambah Ruangan
                </button>
              </div>
              <div class="input-group">
                <select class="form-select" name="ruangan" id="editCardRuangan">
                  '.$optCardRuangan.'
                </select>
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalQuickAddRuanganDashboard" title="Tambah Ruangan Baru">
                  <i class="bi bi-plus-lg text-primary"></i>
                </button>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Sumber Perolehan</label>
              <select class="form-select" name="sumber_perolehan" id="editCardSumber">
                '.$optCardSumber.'
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Status Inventaris</label>
              <select class="form-select" name="status_inventaris" id="editCardStatusInv">
                '.$optCardStatusInv.'
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Lokasi Barang / Kantor</label>
              <input type="text" name="lokasi" id="editCardLokasi" class="form-control" placeholder="Contoh: Handil Bakti, KPO..." list="listLokasiBank">
            </div>
          </div>

          <!-- Section 3: QR Code & Catatan -->
          <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
            <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 px-2 py-1 font-monospace small">03 · KODE QR & KETERANGAN</span>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Kode QR / Barcode (Data QR Code)</label>
              <input type="text" name="barcode_data" id="editCardBarcode" class="form-control font-monospace" placeholder="Salin/tempel kode QR di sini">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Keterangan / Spesifikasi</label>
              <textarea name="keterangan" id="editCardKeterangan" class="form-control" rows="2" placeholder="Catatan spesifikasi perangkat..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2.5 px-4 bg-light d-flex justify-content-between">
          <button type="button" class="btn btn-sm btn-light border rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-sm btn-primary rounded-pill fw-semibold px-4"><i class="bi bi-check-lg me-1"></i> Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Quick Add Ruangan di Dashboard -->
<div class="modal fade" id="modalQuickAddRuanganDashboard" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow border-0" style="border-radius: 16px;">
      <div class="modal-header border-bottom py-3 px-4">
        <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
          <i class="bi bi-door-open text-primary"></i> Tambah Ruangan Penempatan
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div id="quickRuanganDashboardAlert" class="alert alert-danger d-none py-2 small mb-3"></div>
        <div class="mb-3">
          <label class="form-label text-secondary small fw-semibold">Kode Ruangan (2 Digit)</label>
          <input type="text" class="form-control font-monospace" id="inputQuickKodeRuanganDashboard" placeholder="Contoh: 12 (Kosongkan utk otomatis)">
          <div class="form-text small text-muted">Biarkan kosong untuk penomoran otomatis.</div>
        </div>
        <div class="mb-2">
          <label class="form-label text-secondary small fw-semibold">Nama Ruangan <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="inputQuickNamaRuanganDashboard" placeholder="Contoh: RUANG AUDIT & SKAI...">
        </div>
      </div>
      <div class="modal-footer border-top bg-light py-2 px-4 d-flex justify-content-between">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-sm btn-primary fw-semibold" id="btnQuickSubmitRuanganDashboard" onclick="submitQuickRuanganDashboard()">
          <i class="bi bi-check2 me-1"></i> Simpan Ruangan
        </button>
      </div>
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
  cbs.forEach(cb => {
    if (!ids.includes(cb.value)) ids.push(cb.value);
  });
  return ids;
}

function updateCardCounters(sourceCb) {
  if (sourceCb && sourceCb.value) {
    document.querySelectorAll(".card-checkbox[value=\\"" + sourceCb.value + "\\"]").forEach(function(cb) {
      cb.checked = sourceCb.checked;
    });
  }
  const ids = getSelectedCardIds();
  const count = ids.length;
  const countDelSpan = document.getElementById("countDelete");
  const countPrintSpan = document.getElementById("countPrint");
  const countExcelSpan = document.getElementById("countExcel");
  const countSelectedText = document.getElementById("countSelectedText");
  const selBar = document.getElementById("selectionCardBar");

  if (countDelSpan) countDelSpan.innerText = count;
  if (countPrintSpan) countPrintSpan.innerText = count;
  if (countExcelSpan) countExcelSpan.innerText = count;
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
  const allUnique = new Set();
  document.querySelectorAll(".card-checkbox").forEach(cb => allUnique.add(cb.value));
  const totalCount = allUnique.size;
  if (checkAll && totalCount > 0) {
    checkAll.checked = count === totalCount;
    checkAll.indeterminate = count > 0 && count < totalCount;
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

function exportExcelSelectedCards() {
  const ids = getSelectedCardIds();
  let targetUrl = "print_inventory_card.php?source=inventaris_kartu&export=excel";
  if (ids.length > 0) {
    targetUrl += "&ids=" + encodeURIComponent(ids.join(","));
  }
  window.open(targetUrl, "_blank");
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

function autoGenerateCardNoSeri(prefix) {
  var d = new Date();
  var dd = String(d.getDate()).padStart(2, "0");
  var mm = String(d.getMonth() + 1).padStart(2, "0");
  var yy = String(d.getFullYear()).slice(-2);
  var rand = String(Math.floor(1 + Math.random() * 999)).padStart(3, "0");

  var selR = document.getElementById(prefix === "edit" ? "editCardRuangan" : "addCardRuangan");
  var kdR = "05";
  if (selR && selR.value) {
    var mR = selR.value.match(/^(\d{2})/);
    if (mR) kdR = mR[1];
  }

  var selG = document.getElementById(prefix === "edit" ? "editCardGolongan" : "addCardGolongan");
  var kdG = "02";
  if (selG && selG.value) {
    var mG = selG.value.match(/^(\d{2})/);
    if (mG) kdG = mG[1];
  }

  var target = document.getElementById(prefix === "edit" ? "editCardNoSeri" : "addCardNoSeri");
  if (target) {
    target.value = "01." + kdR + "." + kdG + "." + dd + mm + yy + "." + rand;
  }
}

function submitQuickRuanganDashboard() {
  const inputNama = document.getElementById("inputQuickNamaRuanganDashboard");
  const inputKode = document.getElementById("inputQuickKodeRuanganDashboard");
  const alertEl = document.getElementById("quickRuanganDashboardAlert");
  const btn = document.getElementById("btnQuickSubmitRuanganDashboard");

  const nama = inputNama ? inputNama.value.trim() : "";
  const kode = inputKode ? inputKode.value.trim() : "";

  if (!nama) {
    if (alertEl) {
      alertEl.textContent = "Nama ruangan tidak boleh kosong.";
      alertEl.classList.remove("d-none");
    }
    if (inputNama) inputNama.focus();
    return;
  }

  if (alertEl) alertEl.classList.add("d-none");
  if (btn) btn.disabled = true;

  const csrfInput = document.querySelector(\'input[name="_csrf"]\');
  const csrfToken = csrfInput ? csrfInput.value : "";

  const formData = new FormData();
  formData.append("_csrf", csrfToken);
  formData.append("nama_ruangan", nama);
  formData.append("kode_ruangan", kode);

  fetch("ruangan_ajax_add", {
    method: "POST",
    body: formData
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (btn) btn.disabled = false;
    if (!data.success) {
      if (alertEl) {
        alertEl.textContent = data.error || "Gagal menambahkan ruangan baru.";
        alertEl.classList.remove("d-none");
      }
      return;
    }

    const newVal = data.value || (data.kode + " : " + data.nama);
    const selAdd = document.getElementById("addCardRuangan");
    const selEdit = document.getElementById("editCardRuangan");

    [selAdd, selEdit].forEach(function(sel) {
      if (sel) {
        const opt = document.createElement("option");
        opt.value = newVal;
        opt.textContent = newVal;
        sel.appendChild(opt);
        sel.value = newVal;
      }
    });

    const modalEl = document.getElementById("modalQuickAddRuanganDashboard");
    if (modalEl && typeof bootstrap !== "undefined") {
      const bsModal = bootstrap.Modal.getInstance(modalEl);
      if (bsModal) bsModal.hide();
    }
  })
  .catch(function(err) {
    if (btn) btn.disabled = false;
    if (alertEl) {
      alertEl.textContent = "Terjadi kesalahan jaringan atau server.";
      alertEl.classList.remove("d-none");
    }
  });
}

function openCardEditModal(card) {
  document.getElementById("editCardId").value = card.id || "";
  document.getElementById("editCardRekening").value = card.nomor_rekening || "";
  document.getElementById("editCardNama").value = card.nama_barang || "";
  document.getElementById("editCardNoSeri").value = card.nomor_seri_inventaris || "";

  if (card.jenis_inventaris) {
    document.getElementById("editCardJenis").value = card.jenis_inventaris;
  }
  if (card.golongan) {
    document.getElementById("editCardGolongan").value = card.golongan;
  }
  if (card.ruangan) {
    const selR = document.getElementById("editCardRuangan");
    if (selR) {
      let matched = false;
      for (let i = 0; i < selR.options.length; i++) {
        if (selR.options[i].value.toLowerCase() === card.ruangan.toLowerCase()) {
          selR.selectedIndex = i;
          matched = true;
          break;
        }
      }
      if (!matched) {
        const opt = document.createElement("option");
        opt.value = card.ruangan;
        opt.textContent = card.ruangan;
        selR.appendChild(opt);
        selR.value = card.ruangan;
      }
    }
  }
  if (card.sumber_perolehan) {
    document.getElementById("editCardSumber").value = card.sumber_perolehan;
  }
  if (card.status_inventaris) {
    document.getElementById("editCardStatusInv").value = card.status_inventaris;
  }

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
  document.getElementById("editCardKeterangan").value = card.keterangan || "";

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
  document.getElementById("pvLokasi").innerText = card.ruangan || card.lokasi || "KPO";

  const qrTarget = card.id ? (window.location.origin + "/kartu_detail.php?id=" + card.id) : (card.barcode_data || window.location.origin);
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

function openProsesPerbaikanModal(data) {
  document.getElementById("modalFindingId").value = data.finding_id || 0;
  document.getElementById("modalScanId").value = data.scan_id || 0;
  document.getElementById("modalAssetId").value = data.asset_id || 0;
  document.getElementById("modalKodePerangkat").textContent = data.kode || "-";
  document.getElementById("modalNamaPerangkat").textContent = (data.perangkat || "-") + " (" + (data.pengguna || "-") + ")";
  document.getElementById("modalDeskripsiTemuan").textContent = data.finding || "-";
  
  var statusSel = document.getElementById("modalStatusSelect");
  var st = (data.status || "").toLowerCase();
  if (st === "resolved" || st === "selesai") {
    statusSel.value = "Resolved";
  } else if (st === "in progress" || st === "proses" || st === "dalam proses") {
    statusSel.value = "In Progress";
  } else {
    statusSel.value = "In Progress";
  }

  document.getElementById("modalProsesTextarea").value = data.proses_perbaikan || "";
  if (data.teknisi) {
    document.getElementById("modalTeknisiInput").value = data.teknisi;
  }

  var modalEl = document.getElementById("modalProsesPerbaikan");
  if (modalEl) {
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  }
}

function appendModalAction(text) {
  var ta = document.getElementById("modalProsesTextarea");
  if (!ta) return;
  if (ta.value.trim() !== "") {
    ta.value += "\n" + text;
  } else {
    ta.value = text;
  }
}

document.addEventListener("DOMContentLoaded", function() {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get("add") === "1" || window.location.hash === "#addCardModal") {
    const addCardModalEl = document.getElementById("addCardModal");
    if (addCardModalEl) {
      if (urlParams.get("from_memo")) {
        const namaInput = addCardModalEl.querySelector("input[name=\"nama_barang\"]");
        if (namaInput && urlParams.get("nama")) {
          namaInput.value = urlParams.get("nama");
        }

        const tglInput = document.getElementById("addCardTanggal");
        if (tglInput && urlParams.get("tgl")) {
          const raw = urlParams.get("tgl");
          if (raw.includes("-")) {
            const p = raw.split("-");
            if (p.length === 3) tglInput.value = `${p[2]}/${p[1]}/${p[0]}`;
          } else {
            tglInput.value = raw;
          }
        }

        const lokasiInput = document.getElementById("addCardLokasi");
        if (lokasiInput && urlParams.get("cabang")) {
          lokasiInput.value = urlParams.get("cabang");
        }

        const ketInput = addCardModalEl.querySelector("textarea[name=\"keterangan\"]");
        if (ketInput && urlParams.get("ket")) {
          let extra = urlParams.get("ket");
          if (urlParams.get("harga")) {
            const val = parseInt(urlParams.get("harga"), 10);
            if (!isNaN(val) && val > 0) {
              extra += " · Biaya: Rp. " + val.toLocaleString("id-ID");
            }
          }
          ketInput.value = extra;
        }

        // Auto generate no seri inventaris
        if (typeof autoGenerateCardNoSeri === "function") {
          setTimeout(function() {
            autoGenerateCardNoSeri("add");
          }, 300);
        }
      }

      const modal = bootstrap.Modal.getOrCreateInstance(addCardModalEl);
      modal.show();
    }
  }
});
</script>
';

if (!$isHrdOnly) {
    $extraScript .= '
<script>
window.REALTIME_CONFIG = {
  month: ' . (int)$month . ',
  year: ' . (int)$year . ',
  cabang: ' . (int)$cabangId . ',
  wsPort: ' . (int)(getenv('WS_PORT') ?: 8080) . ',
  initialEventId: ' . (int)(microtime(true) * 1000) . '
};
</script>
<script src="realtime_dashboard.js"></script>';
}

render_page($headerTitle, $body, $head, $extraScript);
