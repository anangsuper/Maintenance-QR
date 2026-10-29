<?php
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
ensure_inventaris_kartu_print_columns();
$allCards = get_inventaris_kartu_rows(!empty($_GET['refresh']));
$totalCardsCount = count($allCards);

$standardCabangs = get_standard_cabang_list();

$searchCard = trim((string)($_GET['q_kartu'] ?? ''));
$cabangCard = trim((string)($_GET['cabang_kartu'] ?? ''));
$tahunCard = trim((string)($_GET['tahun_kartu'] ?? ''));
$statusCetakCard = strtolower(trim((string)($_GET['status_cetak'] ?? ''))); // 'belum' atau 'sudah'

$todayDateStr = date('Y-m-d');
$todayDateDmy = date('d/m/Y');
$todayDateDmY = date('d-m-Y');

$filterHariIni = isset($_GET['hari_ini']) && (string)$_GET['hari_ini'] === '1';
$filterHasQr = isset($_GET['has_qr']) && (string)$_GET['has_qr'] === '1';

$todayCardsCount = 0;
$withQrCardsCount = 0;
$printedCardsCount = 0;
$unprintedCardsCount = 0;

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
    if (!empty($ac['printed_at'])) {
        $printedCardsCount++;
    } else {
        $unprintedCardsCount++;
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

// Filter Kartu Inventaris (Mendukung Pencarian Fleksibel dengan/tanpa Titik, Cabang 01-05, Tahun, Hari Ini, Status Cetak, dan Label QR)
$filteredCards = array_filter($allCards, function($r) use ($searchCard, $cabangCard, $tahunCard, $statusCetakCard, $filterHariIni, $filterHasQr, $todayDateStr, $todayDateDmy, $todayDateDmY) {
    if ($statusCetakCard === 'belum') {
        if (!empty($r['printed_at'])) return false;
    } elseif ($statusCetakCard === 'sudah') {
        if (empty($r['printed_at'])) return false;
    }
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

