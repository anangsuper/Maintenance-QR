<?php
function get_dashboard_data(int $month, int $year, int $cabangId): array {
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return ['total' => 0, 'done' => 0, 'findings' => 0, 'pendingRows' => [], 'recentRows' => [], 'cabangs' => []];

        $assets = array_filter(map_sheets_assets(), function($a) use ($cabangId) {
            $st = strtolower($a['status']);
            $active = ($st === 'aktif' || $st === '');
            if (!$active) return false;
            if ($cabangId > 0 && $a['id_cabang'] !== $cabangId) return false;
            return true;
        });

        $scans = $client->getSheetData('Maintenance_Scan', true);
        $scannedAssetIds = [];
        $findingAssetIds = [];

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

            if ($sM === $month && $sY === $year && $aid > 0) {
                $scannedAssetIds[$aid] = true;
                $st = (string)($s['status'] ?? $s['col_8'] ?? '');
                if ($st === 'Temuan' || $st === 'Perlu Perbaikan' || $st === 'Proses') {
                    $findingAssetIds[$aid] = true;
                }
            }
        }

        // Cek session cache jika scan baru saja disimpan
        if (session_status() === PHP_SESSION_ACTIVE) {
            foreach ($assets as $a) {
                $aid = (int)$a['id'];
                if (empty($scannedAssetIds[$aid]) && !empty($_SESSION['_recent_scan_' . $aid])) {
                    $rec = $_SESSION['_recent_scan_' . $aid];
                    $rM = (int)($rec['maintenance_month'] ?? (int)date('n', strtotime((string)($rec['maintenance_date'] ?? ''))));
                    $rY = (int)($rec['maintenance_year'] ?? (int)date('Y', strtotime((string)($rec['maintenance_date'] ?? ''))));
                    if ($rM === $month && $rY === $year) {
                        $scannedAssetIds[$aid] = true;
                        if (($rec['status'] ?? '') === 'Temuan') {
                            $findingAssetIds[$aid] = true;
                        }
                    }
                }
            }
        }

        $total = count($assets);
        $done = 0;
        $findings = 0;
        $pendingRows = [];

        foreach ($assets as $a) {
            if (!empty($scannedAssetIds[$a['id']])) {
                $done++;
                if (!empty($findingAssetIds[$a['id']])) $findings++;
            } else {
                $pendingRows[] = $a;
            }
        }

        $assetMap = []; foreach (map_sheets_assets() as $a) $assetMap[$a['id']] = $a;
        $recentRows = [];

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

            if ($sM === $month && $sY === $year && $aid > 0) {
                $a = $assetMap[$aid] ?? [];
                if ($cabangId > 0 && ($a['id_cabang'] ?? 0) !== $cabangId) continue;

                $recentRows[] = [
                    'id' => $s['id'] ?? $s['col_0'] ?? 0,
                    'asset_id' => $aid,
                    'maintenance_date' => substr((string)($s['maintenance_date'] ?? $s['col_4'] ?? ''), 0, 10),
                    'maintenance_time' => substr((string)($s['maintenance_time'] ?? $s['col_5'] ?? ''), 0, 8),
                    'status' => $s['status'] ?? $s['col_8'] ?? 'Selesai',
                    'kode_inventaris' => $a['kode_inventaris'] ?? '-',
                    'merk' => $a['merk'] ?? '',
                    'model' => $a['model'] ?? '',
                    'karyawan_nama' => $a['karyawan_nama'] ?? '-',
                    'cabang_nama' => $a['cabang_nama'] ?? '-'
                ];
            }
        }

        return [
            'total' => $total,
            'done' => $done,
            'findings' => $findings,
            'pendingRows' => array_values($pendingRows),
            'recentRows' => array_values($recentRows),
            'cabangs' => get_cabang_list()
        ];
    }

    // MySQL mode
    $cName = name_column('cabang') ?: 'id';
    $cabangs = db()->query("SELECT id, `{$cName}` AS nama FROM cabang ORDER BY `{$cName}`")->fetchAll();

    $params = [];
    $where = " WHERE (a.status = 'Aktif' OR a.status = 'aktif' OR a.status IS NULL OR a.status = '') ";
    if ($cabangId) {
        $where .= " AND a.id_cabang = ? ";
        $params[] = $cabangId;
    }

    $totalSt = db()->prepare("SELECT COUNT(*) FROM assets a {$where}");
    $totalSt->execute($params);
    $total = (int)$totalSt->fetchColumn();

    $scannedSql = "
        SELECT COUNT(*)
        FROM maintenance_scan ms
        JOIN assets a ON a.id = ms.asset_id
        {$where}
        AND ms.maintenance_month = ?
        AND ms.maintenance_year = ?
    ";
    $st = db()->prepare($scannedSql);
    $st->execute(array_merge($params, [$month, $year]));
    $done = (int)$st->fetchColumn();

    $findSql = "
        SELECT COUNT(*)
        FROM maintenance_scan ms
        JOIN assets a ON a.id = ms.asset_id
        {$where}
        AND ms.maintenance_month = ?
        AND ms.maintenance_year = ?
        AND ms.status = 'Temuan'
    ";
    $st = db()->prepare($findSql);
    $st->execute(array_merge($params, [$month, $year]));
    $findings = (int)$st->fetchColumn();

    $assetBase = asset_query_base();
    $pendingSql = $assetBase . "
        {$where}
        AND NOT EXISTS (
            SELECT 1 FROM maintenance_scan ms
            WHERE ms.asset_id = a.id
              AND ms.maintenance_month = ?
              AND ms.maintenance_year = ?
        )
        ORDER BY c.`{$cName}`, karyawan_nama, a.kode_inventaris
        LIMIT 300
    ";
    $st = db()->prepare($pendingSql);
    $st->execute(array_merge($params, [$month, $year]));
    $pendingRows = $st->fetchAll();

    $recentSql = "
        SELECT ms.*, a.kode_inventaris, a.merk, a.model,
               c.`{$cName}` AS cabang_nama,
               ".(name_column('karyawan') ? "k.`".name_column('karyawan')."`" : "k.id")." AS karyawan_nama
        FROM maintenance_scan ms
        JOIN assets a ON a.id = ms.asset_id
        LEFT JOIN cabang c ON c.id = a.id_cabang
        LEFT JOIN karyawan k ON k.id = a.id_karyawan
        WHERE ms.maintenance_month = ? AND ms.maintenance_year = ?
        ".($cabangId ? " AND a.id_cabang = ? " : "")."
        ORDER BY ms.maintenance_date DESC, ms.maintenance_time DESC
        LIMIT 12
    ";
    $recentParams = [$month, $year];
    if ($cabangId) $recentParams[] = $cabangId;
    $st = db()->prepare($recentSql);
    $st->execute($recentParams);
    $recentRows = $st->fetchAll();

    return [
        'total' => $total,
        'done' => $done,
        'findings' => $findings,
        'pendingRows' => $pendingRows,
        'recentRows' => $recentRows,
        'cabangs' => $cabangs
    ];
}

function get_comprehensive_dashboard_data(int $month, int $year, int $cabangId = 0, string $statusAset = '', string $statusMaint = ''): array {
    $cabangs = get_cabang_list();
    $cabangMap = [];
    foreach ($cabangs as $c) {
        $cId = (int)($c['id'] ?? 0);
        $cabangMap[$cId] = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cId);
    }

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        $allRawAssets = map_sheets_assets();
        $scans = $client ? $client->getSheetData('Maintenance_Scan') : [];
        $rawFindings = $client ? $client->getSheetData('Maintenance_Findings') : [];
        
        $assetMap = [];
        foreach ($allRawAssets as $a) {
            $assetMap[(int)$a['id']] = $a;
        }

        $currentMonthScans = [];
        $scannedAssetIds = [];
        $findingAssetIds = [];

        foreach ($scans as $s) {
            $sMonth = (int)($s['maintenance_month'] ?? 0);
            $sYear = (int)($s['maintenance_year'] ?? 0);
            $aid = (int)($s['asset_id'] ?? 0);
            if ($sMonth === $month && $sYear === $year) {
                $currentMonthScans[$aid] = $s;
                $scannedAssetIds[$aid] = true;
                $stVal = strtolower(trim((string)($s['status'] ?? '')));
                if (in_array($stVal, ['selesai', 'ok', 'resolved', 'closed'], true)) {
                    unset($findingAssetIds[$aid]);
                } elseif (in_array($stVal, ['temuan', 'perlu perbaikan', 'perlu tindak lanjut', 'proses'], true)) {
                    $findingAssetIds[$aid] = true;
                }
            }
        }

        $totalAll = 0;
        $totalActive = 0;
        $totalBroken = 0;
        $totalDone = 0;
        $totalDue = 0;

        $filteredAssets = [];

        foreach ($allRawAssets as $a) {
            $aid = (int)$a['id'];
            $cId = (int)($a['id_cabang'] ?? 0);
            $st = strtolower(trim((string)($a['status'] ?? '')));
            $isActive = ($st === 'aktif' || $st === '');
            $isBroken = (in_array($st, ['rusak', 'perlu perbaikan', 'rusak ringan', 'rusak berat', 'temuan'], true) || !empty($findingAssetIds[$aid]));

            if ($cabangId > 0 && $cId !== $cabangId) {
                continue;
            }

            $totalAll++;
            if ($isActive) $totalActive++;
            if ($isBroken) $totalBroken++;

            $isDoneThisMonth = !empty($scannedAssetIds[$aid]);
            if ($isActive) {
                if ($isDoneThisMonth) {
                    $totalDone++;
                } else {
                    $totalDue++;
                }
            }

            if ($statusAset !== '') {
                if ($statusAset === 'aktif' && !$isActive) continue;
                if ($statusAset === 'rusak' && !$isBroken) continue;
                if ($statusAset === 'nonaktif' && ($isActive || $isBroken)) continue;
            }

            if ($statusMaint !== '') {
                if ($statusMaint === 'done' && !$isDoneThisMonth) continue;
                if ($statusMaint === 'pending' && $isDoneThisMonth) continue;
                if ($statusMaint === 'repair' && empty($findingAssetIds[$aid])) continue;
            }

            $filteredAssets[] = $a;
        }

        $scanMap = [];
        $assetLatestScanMap = [];
        foreach ($scans as $s) {
            $sId = (int)($s['id'] ?? $s['col_0'] ?? 0);
            if ($sId > 0) {
                $scanMap[$sId] = $s;
            }
            $sAid = (int)($s['asset_id'] ?? $s['id_asset'] ?? $s['col_1'] ?? 0);
            if ($sAid > 0) {
                if (!isset($assetLatestScanMap[$sAid])) {
                    $assetLatestScanMap[$sAid] = $s;
                } else {
                    $prevId = (int)($assetLatestScanMap[$sAid]['id'] ?? $assetLatestScanMap[$sAid]['col_0'] ?? 0);
                    $currId = (int)($s['id'] ?? $s['col_0'] ?? 0);
                    if ($currId >= $prevId) {
                        $assetLatestScanMap[$sAid] = $s;
                    }
                }
            }
        }

        $unresolvedFindingsList = [];
        foreach ($rawFindings as $f) {
            $fStatus = trim((string)($f['status'] ?? $f['repair_status'] ?? $f['status_perbaikan'] ?? $f['col_6'] ?? 'Open'));
            $isUnresolved = !in_array(strtolower($fStatus), ['resolved', 'closed', 'selesai', 'done', 'ok'], true);
            if (!$isUnresolved) continue;

            $aid = (int)($f['asset_id'] ?? $f['id_asset'] ?? $f['col_2'] ?? 0);
            $a = $assetMap[$aid] ?? [];
            if ($cabangId > 0 && (int)($a['id_cabang'] ?? 0) !== $cabangId) continue;

            $fLogId = (int)($f['maintenance_scan_id'] ?? $f['maintenance_id'] ?? $f['log_id'] ?? $f['col_1'] ?? 0);
            $matchingScan = $scanMap[$fLogId] ?? ($assetLatestScanMap[$aid] ?? []);
            $latestScan = $assetLatestScanMap[$aid] ?? null;

            // 1. Jika scan terkait langsung ternyata sudah selesai, lewati temuan ini
            $isScanResolved = !empty($matchingScan['status']) && in_array(strtolower(trim((string)$matchingScan['status'])), ['selesai', 'resolved', 'closed', 'ok'], true);
            if ($isScanResolved) continue;

            // 2. KUNCI: Jika aset ini sudah memiliki scan maintenance terbaru yang berstatus 'Selesai', temuan lama dianggap terselesaikan!
            if ($latestScan) {
                $latestSt = strtolower(trim((string)($latestScan['status'] ?? $latestScan['col_8'] ?? '')));
                $latestScanId = (int)($latestScan['id'] ?? $latestScan['col_0'] ?? 0);
                if (in_array($latestSt, ['selesai', 'resolved', 'closed', 'ok'], true) && ($fLogId <= 0 || $latestScanId >= $fLogId)) {
                    continue;
                }
            }

            // Temuan / Masalah: cek berbagai nama kolom, fallback ke scan log
            $findingText = '';
            foreach (['deskripsi_temuan', 'finding', 'description', 'masalah', 'catatan', 'col_4'] as $k) {
                if (isset($f[$k]) && trim((string)$f[$k]) !== '' && trim((string)$f[$k]) !== '-') {
                    $findingText = trim((string)$f[$k]);
                    break;
                }
            }
            if ($findingText === '') {
                $findingText = !empty($matchingScan['findings']) && $matchingScan['findings'] !== '-'
                    ? (string)$matchingScan['findings']
                    : 'Pemeriksaan lanjutan perangkat';
            }

            // Tanggal ditemukan: cek reported_at, created_at, tanggal, date, fallback ke scan date
            $dateStr = '';
            foreach (['reported_at', 'created_at', 'tanggal', 'date', 'col_8'] as $k) {
                if (isset($f[$k]) && trim((string)$f[$k]) !== '' && trim((string)$f[$k]) !== '-') {
                    $dateStr = trim((string)$f[$k]);
                    break;
                }
            }
            if ($dateStr === '') {
                $dateStr = !empty($matchingScan['maintenance_date']) ? (string)$matchingScan['maintenance_date'] : (!empty($matchingScan['created_at']) ? (string)$matchingScan['created_at'] : date('Y-m-d'));
            }

            // Pelapor / Teknisi: cek reported_by, created_by, reporter, teknisi, fallback ke scan tech
            $reporterName = '';
            foreach (['reported_by', 'created_by', 'reporter', 'teknisi', 'technician', 'col_7'] as $k) {
                if (isset($f[$k]) && trim((string)$f[$k]) !== '' && trim((string)$f[$k]) !== '-' && strcasecmp(trim((string)$f[$k]), 'teknisi') !== 0) {
                    $reporterName = trim((string)$f[$k]);
                    break;
                }
            }
            if ($reporterName === '') {
                $reporterName = !empty($matchingScan['technician_name']) ? (string)$matchingScan['technician_name'] : (string)($f['reported_by'] ?? $f['created_by'] ?? 'Teknisi');
            }

            // Tindakan perbaikan / rekomendasi
            $actionText = '';
            foreach (['tindakan_diperlukan', 'action_taken', 'rekomendasi', 'recommendation', 'col_5'] as $k) {
                if (isset($f[$k]) && trim((string)$f[$k]) !== '' && trim((string)$f[$k]) !== '-') {
                    $actionText = trim((string)$f[$k]);
                    break;
                }
            }
            if ($actionText === '') {
                $actionText = (string)($matchingScan['recommendation'] ?? '-');
            }

            // Tingkat keparahan
            $sev = (string)($f['kategori_temuan'] ?? $f['severity'] ?? $f['tingkat_kerusakan'] ?? $f['col_3'] ?? 'Sedang');

            $unresolvedFindingsList[] = [
                'id' => (int)($f['id'] ?? $f['col_0'] ?? 0),
                'log_id' => $fLogId ?: (int)($matchingScan['id'] ?? 0),
                'asset_id' => $aid,
                'kode_inventaris' => $a['kode_inventaris'] ?? ('ASET #' . $aid),
                'nama_perangkat' => trim(($a['merk'] ?? '').' '.($a['model'] ?? '')),
                'cabang_nama' => $a['cabang_nama'] ?? ($cabangMap[(int)($a['id_cabang'] ?? 0)] ?? '-'),
                'finding' => $findingText,
                'action_taken' => $actionText,
                'severity' => $sev,
                'status' => $fStatus ?: 'Perlu Tindak Lanjut',
                'reporter' => $reporterName,
                'token' => (string)($a['token'] ?? ''),
                'created_at' => substr($dateStr, 0, 10)
            ];
        }

        if (empty($unresolvedFindingsList)) {
            $sortedScans = $scans;
            usort($sortedScans, function($a, $b) {
                return (int)($b['id'] ?? 0) - (int)($a['id'] ?? 0);
            });
            $processedAssets = [];
            foreach ($sortedScans as $s) {
                $aid = (int)($s['asset_id'] ?? 0);
                if ($aid <= 0 || isset($processedAssets[$aid])) continue;
                $processedAssets[$aid] = true;

                $sStatus = trim((string)($s['status'] ?? ''));
                $isResolved = in_array(strtolower($sStatus), ['selesai', 'resolved', 'closed', 'ok'], true);
                if (!$isResolved && (in_array(strtolower($sStatus), ['temuan', 'perlu perbaikan', 'perlu tindak lanjut', 'proses'], true) || (!empty($s['findings']) && $s['findings'] !== '-'))) {
                    $a = $assetMap[$aid] ?? [];
                    if ($cabangId > 0 && (int)($a['id_cabang'] ?? 0) !== $cabangId) continue;

                    $unresolvedFindingsList[] = [
                        'id' => (int)($s['id'] ?? 0),
                        'log_id' => (int)($s['id'] ?? 0),
                        'asset_id' => $aid,
                        'kode_inventaris' => $a['kode_inventaris'] ?? '-',
                        'nama_perangkat' => trim(($a['merk'] ?? '').' '.($a['model'] ?? '')),
                        'cabang_nama' => $a['cabang_nama'] ?? ($cabangMap[(int)($a['id_cabang'] ?? 0)] ?? '-'),
                        'finding' => (string)($s['findings'] ?? 'Pemeriksaan lanjutan perangkat'),
                        'action_taken' => (string)($s['recommendation'] ?? '-'),
                        'severity' => 'Sedang',
                        'status' => 'Perlu Tindak Lanjut',
                        'reporter' => (string)($s['technician_name'] ?? 'Teknisi'),
                        'token' => (string)($a['token'] ?? ''),
                        'created_at' => (string)($s['maintenance_date'] ?? '')
                    ];
                }
            }
        }
        $totalUnresolvedFindings = count($unresolvedFindingsList);

        usort($unresolvedFindingsList, function($a, $b) {
            return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
        });
        $topUnresolvedFindings = array_slice($unresolvedFindingsList, 0, 5);

        $recentLogs = [];
        $sortedScans = $scans;
        usort($sortedScans, function($a, $b) {
            $dtA = ($a['maintenance_date'] ?? '') . ' ' . ($a['maintenance_time'] ?? '');
            $dtB = ($b['maintenance_date'] ?? '') . ' ' . ($b['maintenance_time'] ?? '');
            return strcmp($dtB, $dtA);
        });

        foreach ($sortedScans as $s) {
            if (empty($s['id']) && empty($s['asset_id'])) continue;
            $aid = (int)($s['asset_id'] ?? 0);
            $a = $assetMap[$aid] ?? [];
            if ($cabangId > 0 && (int)($a['id_cabang'] ?? 0) !== $cabangId) continue;

            $recentLogs[] = [
                'id' => (int)($s['id'] ?? 0),
                'asset_id' => $aid,
                'kode_inventaris' => $a['kode_inventaris'] ?? '-',
                'nama_perangkat' => trim(($a['merk'] ?? '').' '.($a['model'] ?? '')),
                'karyawan_nama' => $a['karyawan_nama'] ?? '-',
                'cabang_nama' => $a['cabang_nama'] ?? ($cabangMap[(int)($a['id_cabang'] ?? 0)] ?? '-'),
                'technician_name' => $s['technician_name'] ?? 'Teknisi',
                'maintenance_date' => substr((string)($s['maintenance_date'] ?? ''), 0, 10),
                'maintenance_time' => substr((string)($s['maintenance_time'] ?? ''), 0, 8),
                'status' => $s['status'] ?? 'Selesai',
                'findings' => $s['findings'] ?? '-'
            ];
            if (count($recentLogs) >= 10) break;
        }

        $upcomingList = [];
        $daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
        $targetDueDateStr = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
        $todayTs = strtotime(date('Y-m-d'));
        $targetDueTs = strtotime($targetDueDateStr);
        $diffDays = (int)round(($targetDueTs - $todayTs) / 86400);

        foreach ($allRawAssets as $a) {
            $aid = (int)$a['id'];
            $st = strtolower(trim((string)($a['status'] ?? '')));
            if ($st !== 'aktif' && $st !== '') continue;
            if ($cabangId > 0 && (int)($a['id_cabang'] ?? 0) !== $cabangId) continue;

            if (empty($scannedAssetIds[$aid])) {
                $upcomingList[] = [
                    'asset_id' => $aid,
                    'kode_inventaris' => $a['kode_inventaris'] ?? '-',
                    'nama_perangkat' => trim(($a['merk'] ?? '').' '.($a['model'] ?? '')),
                    'cabang_nama' => $a['cabang_nama'] ?? ($cabangMap[(int)($a['id_cabang'] ?? 0)] ?? '-'),
                    'divisi_nama' => $a['divisi_nama'] ?? '-',
                    'karyawan_nama' => $a['karyawan_nama'] ?? '-',
                    'due_date' => $targetDueDateStr,
                    'sisa_hari' => $diffDays
                ];
            }
        }
        $topUpcomingList = array_slice($upcomingList, 0, 10);

        $branchAssetDistribution = [];
        foreach ($cabangs as $c) {
            $cId = (int)($c['id'] ?? 0);
            $cName = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cId);
            $cnt = 0;
            foreach ($allRawAssets as $a) {
                if ((int)($a['id_cabang'] ?? 0) === $cId) {
                    $cnt++;
                }
            }
            if ($cnt > 0) {
                $branchAssetDistribution[] = [
                    'id' => $cId,
                    'nama' => $cName,
                    'count' => $cnt
                ];
            }
        }

        $monthlyOverview = get_monthly_overview($year, $cabangId);

        return [
            'total_all' => $totalAll,
            'total_active' => $totalActive,
            'total_broken' => $totalBroken,
            'total_done' => $totalDone,
            'total_due' => $totalDue,
            'total_unresolved_findings' => $totalUnresolvedFindings,
            'recent_logs' => $recentLogs,
            'upcoming_list' => $topUpcomingList,
            'unresolved_findings' => $topUnresolvedFindings,
            'branch_distribution' => $branchAssetDistribution,
            'monthly_overview' => $monthlyOverview,
            'cabangs' => $cabangs
        ];
    }

    // MySQL Mode
    $cName = name_column('cabang') ?: 'id';
    $whereBranch = $cabangId > 0 ? " AND a.id_cabang = {$cabangId} " : "";

    $totalAll = (int)db()->query("SELECT COUNT(*) FROM assets a WHERE 1=1 {$whereBranch}")->fetchColumn();
    $totalActive = (int)db()->query("SELECT COUNT(*) FROM assets a WHERE (a.status = 'Aktif' OR a.status = 'aktif' OR a.status IS NULL OR a.status = '') {$whereBranch}")->fetchColumn();
    $totalBroken = (int)db()->query("SELECT COUNT(*) FROM assets a WHERE (a.status IN ('Rusak', 'rusak', 'Perlu Perbaikan', 'Temuan')) {$whereBranch}")->fetchColumn();

    $doneSt = db()->prepare("
        SELECT COUNT(DISTINCT ms.asset_id)
        FROM maintenance_scan ms
        JOIN assets a ON a.id = ms.asset_id
        WHERE ms.maintenance_month = ? AND ms.maintenance_year = ?
        ".($cabangId > 0 ? " AND a.id_cabang = ? " : "")."
    ");
    $doneParams = [$month, $year];
    if ($cabangId > 0) $doneParams[] = $cabangId;
    $doneSt->execute($doneParams);
    $totalDone = (int)$doneSt->fetchColumn();
    $totalDue = max(0, $totalActive - $totalDone);

    try {
        $findSt = db()->prepare("
            SELECT mf.id, mf.maintenance_scan_id AS log_id, mf.asset_id, mf.finding, mf.action_taken, mf.severity,
                   COALESCE(mf.repair_status, 'Open') AS status, mf.created_at,
                   a.kode_inventaris, a.merk, a.model,
                   c.`{$cName}` AS cabang_nama,
                   COALESCE(u.nama, u.username, 'Teknisi') AS reporter
            FROM maintenance_findings mf
            JOIN assets a ON a.id = mf.asset_id
            LEFT JOIN cabang c ON c.id = a.id_cabang
            LEFT JOIN users u ON u.id = mf.created_by
            WHERE COALESCE(mf.repair_status, 'Open') NOT IN ('Resolved', 'Closed', 'Selesai', 'Done')
              AND NOT EXISTS (
                  SELECT 1 FROM maintenance_scan ms2 
                  WHERE ms2.asset_id = mf.asset_id 
                    AND ms2.id >= COALESCE(mf.maintenance_scan_id, 0)
                    AND LOWER(ms2.status) IN ('selesai', 'ok', 'resolved')
              )
            ".($cabangId > 0 ? " AND a.id_cabang = ? " : "")."
            ORDER BY mf.created_at DESC
            LIMIT 5
        ");
        $findSt->execute($cabangId > 0 ? [$cabangId] : []);
        $topUnresolvedFindings = $findSt->fetchAll();
        $totalUnresolvedFindings = count($topUnresolvedFindings);
    } catch (Throwable $e) {
        $topUnresolvedFindings = [];
        $totalUnresolvedFindings = 0;
    }

    $kName = name_column('karyawan') ?: 'id';
    $uName = name_column('users') ?: 'id';
    $recentSt = db()->prepare("
        SELECT ms.id, ms.asset_id, ms.maintenance_date, ms.maintenance_time, ms.status, ms.findings,
               COALESCE(ms.technician_name, u.`{$uName}`, 'Teknisi') AS technician_name,
               a.kode_inventaris, a.merk, a.model,
               c.`{$cName}` AS cabang_nama,
               k.`{$kName}` AS karyawan_nama
        FROM maintenance_scan ms
        JOIN assets a ON a.id = ms.asset_id
        LEFT JOIN cabang c ON c.id = a.id_cabang
        LEFT JOIN karyawan k ON k.id = a.id_karyawan
        LEFT JOIN users u ON u.id = ms.technician_user_id
        WHERE 1=1 ".($cabangId > 0 ? " AND a.id_cabang = ? " : "")."
        ORDER BY ms.maintenance_date DESC, ms.maintenance_time DESC
        LIMIT 10
    ");
    $recentSt->execute($cabangId > 0 ? [$cabangId] : []);
    $recentRowsRaw = $recentSt->fetchAll();
    $recentLogs = [];
    foreach ($recentRowsRaw as $r) {
        $recentLogs[] = [
            'id' => (int)$r['id'],
            'asset_id' => (int)$r['asset_id'],
            'kode_inventaris' => $r['kode_inventaris'] ?? '-',
            'nama_perangkat' => trim(($r['merk'] ?? '').' '.($r['model'] ?? '')),
            'karyawan_nama' => $r['karyawan_nama'] ?? '-',
            'cabang_nama' => $r['cabang_nama'] ?? '-',
            'technician_name' => $r['technician_name'] ?? 'Teknisi',
            'maintenance_date' => substr((string)($r['maintenance_date'] ?? ''), 0, 10),
            'maintenance_time' => substr((string)($r['maintenance_time'] ?? ''), 0, 8),
            'status' => $r['status'] ?? 'Selesai',
            'findings' => $r['findings'] ?? '-'
        ];
    }

    $dName = name_column('divisi') ?: 'id';
    $daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
    $targetDueDateStr = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
    $todayTs = strtotime(date('Y-m-d'));
    $targetDueTs = strtotime($targetDueDateStr);
    $diffDays = (int)round(($targetDueTs - $todayTs) / 86400);

    $upSt = db()->prepare("
        SELECT a.id AS asset_id, a.kode_inventaris, a.merk, a.model,
               c.`{$cName}` AS cabang_nama,
               d.`{$dName}` AS divisi_nama,
               k.`{$kName}` AS karyawan_nama
        FROM assets a
        LEFT JOIN cabang c ON c.id = a.id_cabang
        LEFT JOIN divisi d ON d.id = a.id_divisi
        LEFT JOIN karyawan k ON k.id = a.id_karyawan
        WHERE (a.status = 'Aktif' OR a.status = 'aktif' OR a.status IS NULL OR a.status = '')
          ".($cabangId > 0 ? " AND a.id_cabang = ? " : "")."
          AND NOT EXISTS (
              SELECT 1 FROM maintenance_scan ms
              WHERE ms.asset_id = a.id
                AND ms.maintenance_month = ?
                AND ms.maintenance_year = ?
          )
        ORDER BY c.`{$cName}`, a.kode_inventaris
        LIMIT 10
    ");
    $upParams = $cabangId > 0 ? [$cabangId, $month, $year] : [$month, $year];
    $upSt->execute($upParams);
    $upRowsRaw = $upSt->fetchAll();
    $upcomingList = [];
    foreach ($upRowsRaw as $r) {
        $upcomingList[] = [
            'asset_id' => (int)$r['asset_id'],
            'kode_inventaris' => $r['kode_inventaris'] ?? '-',
            'nama_perangkat' => trim(($r['merk'] ?? '').' '.($r['model'] ?? '')),
            'cabang_nama' => $r['cabang_nama'] ?? '-',
            'divisi_nama' => $r['divisi_nama'] ?? '-',
            'karyawan_nama' => $r['karyawan_nama'] ?? '-',
            'due_date' => $targetDueDateStr,
            'sisa_hari' => $diffDays
        ];
    }

    $distSt = db()->query("
        SELECT c.id, c.`{$cName}` AS nama, COUNT(a.id) AS count
        FROM cabang c
        LEFT JOIN assets a ON a.id_cabang = c.id
        GROUP BY c.id, c.`{$cName}`
        HAVING count > 0
        ORDER BY count DESC
    ");
    $branchAssetDistribution = $distSt->fetchAll();

    $monthlyOverview = get_monthly_overview($year, $cabangId);

    return [
        'total_all' => $totalAll,
        'total_active' => $totalActive,
        'total_broken' => $totalBroken,
        'total_done' => $totalDone,
        'total_due' => $totalDue,
        'total_unresolved_findings' => $totalUnresolvedFindings,
        'recent_logs' => $recentLogs,
        'upcoming_list' => $upcomingList,
        'unresolved_findings' => $topUnresolvedFindings,
        'branch_distribution' => $branchAssetDistribution,
        'monthly_overview' => $monthlyOverview,
        'cabangs' => $cabangs
    ];
}

function get_branch_maintenance_summary(int $month, int $year): array {
    $cabangs = get_cabang_list();
    $results = [];

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        $allAssets = array_filter(map_sheets_assets(), function($a) {
            $st = strtolower($a['status']);
            return ($st === 'aktif' || $st === '');
        });

        $scans = $client ? $client->getSheetData('Maintenance_Scan', false) : [];
        $scannedAssetIds = [];
        $findingAssetIds = [];

        foreach ($scans as $s) {
            $aid = (int)($s['asset_id'] ?? $s['id_asset'] ?? $s['col_1'] ?? 0);
            $sM = (int)($s['maintenance_month'] ?? $s['month'] ?? $s['col_6'] ?? 0);
            $sDate = (string)($s['maintenance_date'] ?? $s['col_4'] ?? '');
            if ($sM <= 0 && $sDate !== '') $sM = (int)date('n', strtotime($sDate));
            $sY = (int)($s['maintenance_year'] ?? $s['year'] ?? $s['col_7'] ?? 0);
            if ($sY <= 0 && $sDate !== '') $sY = (int)date('Y', strtotime($sDate));

            if ($sM === $month && $sY === $year && $aid > 0) {
                $scannedAssetIds[$aid] = true;
                $st = (string)($s['status'] ?? $s['col_8'] ?? '');
                if ($st === 'Temuan' || $st === 'Perlu Perbaikan' || $st === 'Proses') {
                    $findingAssetIds[$aid] = true;
                }
            }
        }

        foreach ($cabangs as $c) {
            $cId = (int)($c['id'] ?? 0);
            $cName = $c['nama'] ?? $c['nama_cabang'] ?? 'Cabang #' . $cId;
            
            $bAssets = array_filter($allAssets, fn($a) => (int)($a['id_cabang'] ?? 0) === $cId);
            $total = count($bAssets);
            $done = 0;
            $findings = 0;

            foreach ($bAssets as $a) {
                if (!empty($scannedAssetIds[$a['id']])) {
                    $done++;
                    if (!empty($findingAssetIds[$a['id']])) $findings++;
                }
            }

            $pending = max(0, $total - $done);
            $percent = $total > 0 ? round(($done / $total) * 100) : 0;

            $results[] = [
                'id' => $cId,
                'nama' => $cName,
                'total' => $total,
                'done' => $done,
                'pending' => $pending,
                'findings' => $findings,
                'percent' => $percent
            ];
        }

        return $results;
    }

    // MySQL Mode
    $cName = name_column('cabang') ?: 'id';
    foreach ($cabangs as $c) {
        $cId = (int)($c['id'] ?? 0);
        $cNama = $c['nama'] ?? $c['nama_cabang'] ?? 'Cabang #' . $cId;

        $totSt = db()->prepare("SELECT COUNT(*) FROM assets WHERE (status = 'Aktif' OR status = 'aktif' OR status IS NULL OR status = '') AND id_cabang = ?");
        $totSt->execute([$cId]);
        $total = (int)$totSt->fetchColumn();

        $doneSt = db()->prepare("
            SELECT COUNT(*) FROM maintenance_scan ms
            JOIN assets a ON a.id = ms.asset_id
            WHERE (a.status = 'Aktif' OR a.status = 'aktif' OR a.status IS NULL OR a.status = '')
              AND a.id_cabang = ?
              AND ms.maintenance_month = ?
              AND ms.maintenance_year = ?
        ");
        $doneSt->execute([$cId, $month, $year]);
        $done = (int)$doneSt->fetchColumn();

        $findSt = db()->prepare("
            SELECT COUNT(*) FROM maintenance_scan ms
            JOIN assets a ON a.id = ms.asset_id
            WHERE (a.status = 'Aktif' OR a.status = 'aktif' OR a.status IS NULL OR a.status = '')
              AND a.id_cabang = ?
              AND ms.maintenance_month = ?
              AND ms.maintenance_year = ?
              AND ms.status = 'Temuan'
        ");
        $findSt->execute([$cId, $month, $year]);
        $findings = (int)$findSt->fetchColumn();

        $pending = max(0, $total - $done);
        $percent = $total > 0 ? round(($done / $total) * 100) : 0;

        $results[] = [
            'id' => $cId,
            'nama' => $cNama,
            'total' => $total,
            'done' => $done,
            'pending' => $pending,
            'findings' => $findings,
            'percent' => $percent
        ];
    }

    return $results;
}

function get_divisi_maintenance_summary(int $month = 0, int $year = 0): array {
    if ($month <= 0) $month = (int)date('n');
    if ($year <= 0) $year = (int)date('Y');

    $divisis = get_divisi_list();
    $results = [];

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        $allAssets = array_filter(map_sheets_assets(), function($a) {
            $st = strtolower($a['status'] ?? '');
            return ($st === 'aktif' || $st === '');
        });

        $scans = $client ? $client->getSheetData('Maintenance_Scan', true) : [];
        $scannedAssetIds = [];
        $findingAssetIds = [];

        foreach ($scans as $s) {
            $aid = (int)($s['asset_id'] ?? $s['id_asset'] ?? $s['col_1'] ?? 0);
            $sM = (int)($s['maintenance_month'] ?? $s['month'] ?? $s['col_6'] ?? 0);
            $sDate = (string)($s['maintenance_date'] ?? $s['col_4'] ?? '');
            if ($sM <= 0 && $sDate !== '') $sM = (int)date('n', strtotime($sDate));
            $sY = (int)($s['maintenance_year'] ?? $s['year'] ?? $s['col_7'] ?? 0);
            if ($sY <= 0 && $sDate !== '') $sY = (int)date('Y', strtotime($sDate));

            if ($sM === $month && $sY === $year && $aid > 0) {
                $scannedAssetIds[$aid] = true;
                $st = (string)($s['status'] ?? $s['col_8'] ?? '');
                if ($st === 'Temuan' || $st === 'Perlu Perbaikan' || $st === 'Proses') {
                    $findingAssetIds[$aid] = true;
                }
            }
        }

        foreach ($divisis as $d) {
            $dId = (int)($d['id'] ?? 0);
            $dName = $d['nama_divisi'] ?? $d['nama'] ?? 'Divisi #' . $dId;
            $dKet = $d['keterangan'] ?? '';

            $dAssets = array_filter($allAssets, fn($a) => (int)($a['id_divisi'] ?? 0) === $dId);
            $total = count($dAssets);
            $done = 0;
            $findings = 0;

            foreach ($dAssets as $a) {
                if (!empty($scannedAssetIds[$a['id']])) {
                    $done++;
                    if (!empty($findingAssetIds[$a['id']])) $findings++;
                }
            }

            $pending = max(0, $total - $done);
            $percent = $total > 0 ? round(($done / $total) * 100) : 0;

            $results[] = [
                'id' => $dId,
                'nama' => $dName,
                'keterangan' => $dKet,
                'total' => $total,
                'done' => $done,
                'pending' => $pending,
                'findings' => $findings,
                'percent' => $percent
            ];
        }

        return $results;
    }

    // MySQL Mode
    foreach ($divisis as $d) {
        $dId = (int)($d['id'] ?? 0);
        $dNama = $d['nama_divisi'] ?? $d['nama'] ?? 'Divisi #' . $dId;
        $dKet = $d['keterangan'] ?? '';

        try {
            $totSt = db()->prepare("SELECT COUNT(*) FROM assets WHERE (status = 'Aktif' OR status = 'aktif' OR status IS NULL OR status = '') AND id_divisi = ?");
            $totSt->execute([$dId]);
            $total = (int)$totSt->fetchColumn();

            $doneSt = db()->prepare("
                SELECT COUNT(*) FROM maintenance_scan ms
                JOIN assets a ON a.id = ms.asset_id
                WHERE (a.status = 'Aktif' OR a.status = 'aktif' OR a.status IS NULL OR a.status = '')
                  AND a.id_divisi = ?
                  AND ms.maintenance_month = ?
                  AND ms.maintenance_year = ?
            ");
            $doneSt->execute([$dId, $month, $year]);
            $done = (int)$doneSt->fetchColumn();

            $findSt = db()->prepare("
                SELECT COUNT(*) FROM maintenance_scan ms
                JOIN assets a ON a.id = ms.asset_id
                WHERE (a.status = 'Aktif' OR a.status = 'aktif' OR a.status IS NULL OR a.status = '')
                  AND a.id_divisi = ?
                  AND ms.maintenance_month = ?
                  AND ms.maintenance_year = ?
                  AND ms.status = 'Temuan'
            ");
            $findSt->execute([$dId, $month, $year]);
            $findings = (int)$findSt->fetchColumn();
        } catch (Throwable $e) {
            $total = 0; $done = 0; $findings = 0;
        }

        $pending = max(0, $total - $done);
        $percent = $total > 0 ? round(($done / $total) * 100) : 0;

        $results[] = [
            'id' => $dId,
            'nama' => $dNama,
            'keterangan' => $dKet,
            'total' => $total,
            'done' => $done,
            'pending' => $pending,
            'findings' => $findings,
            'percent' => $percent
        ];
    }

    return $results;
}


function get_audit_maintenance_data(array $filters): array {
    $month = (int)($filters['bulan'] ?? date('n'));
    $year = (int)($filters['tahun'] ?? date('Y'));
    $cabangId = (int)($filters['cabang'] ?? 0);
    $divisiId = (int)($filters['divisi'] ?? 0);
    $filterKat = (int)($filters['kategori'] ?? 0);
    $filterTech = trim((string)($filters['teknisi'] ?? ''));
    $filterStatus = trim((string)($filters['status'] ?? ''));

    // 1. Ambil semua master aset aktif
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        $allAssets = array_filter(map_sheets_assets(), function($a) use ($cabangId, $divisiId, $filterKat) {
            $st = strtolower($a['status']);
            if ($st !== 'aktif' && $st !== '') return false;
            if ($cabangId > 0 && (int)($a['id_cabang'] ?? 0) !== $cabangId) return false;
            if ($divisiId > 0 && (int)($a['id_divisi'] ?? 0) !== $divisiId) return false;
            if ($filterKat > 0 && (int)($a['id_kategori'] ?? 0) !== $filterKat) return false;
            return true;
        });

        $scans = $client ? $client->getSheetData('Maintenance_Scan', false) : [];
        $chkRows = $client ? $client->getSheetData('Maintenance_Checklists', false) : [];
        
        // Buat map checklist per maintenance_id
        $chkMap = [];
        foreach ($chkRows as $c) {
            $mid = (int)($c['maintenance_id'] ?? $c['maintenance_scan_id'] ?? $c['id_maintenance'] ?? $c['log_id'] ?? $c['scan_id'] ?? $c['col_1'] ?? 0);
            if ($mid > 0) {
                $chkMap[$mid][] = $c;
            }
        }

        // Map maintenance terbaru per asset_id untuk bulan & tahun ini
        $assetScanMap = [];
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

            if ($sM === $month && $sY === $year && $aid > 0) {
                // Jika teknisi difilter
                $sTech = trim((string)($s['technician_name'] ?? $s['col_3'] ?? ''));
                if ($filterTech !== '' && strcasecmp($sTech, $filterTech) !== 0) {
                    continue;
                }
                $currId = (int)($s['id'] ?? $s['col_0'] ?? 0);
                if (!isset($assetScanMap[$aid]) || $currId >= (int)($assetScanMap[$aid]['id'] ?? $assetScanMap[$aid]['col_0'] ?? 0)) {
                    $assetScanMap[$aid] = $s;
                }
            }
        }

        // Cek session cache jika scan baru saja disimpan di sesi saat ini
        if (session_status() === PHP_SESSION_ACTIVE) {
            foreach ($allAssets as $a) {
                $aid = (int)$a['id'];
                if (!isset($assetScanMap[$aid]) && !empty($_SESSION['_recent_scan_' . $aid])) {
                    $rec = $_SESSION['_recent_scan_' . $aid];
                    $rM = (int)($rec['maintenance_month'] ?? (int)date('n', strtotime((string)($rec['maintenance_date'] ?? ''))));
                    $rY = (int)($rec['maintenance_year'] ?? (int)date('Y', strtotime((string)($rec['maintenance_date'] ?? ''))));
                    if ($rM === $month && $rY === $year) {
                        $assetScanMap[$aid] = $rec;
                    }
                }
            }
        }

        $rows = [];
        $total = 0;
        $done = 0;
        $pending = 0;
        $repair = 0;

        foreach ($allAssets as $a) {
            $aid = (int)$a['id'];
            $scan = $assetScanMap[$aid] ?? null;

            $isDone = ($scan !== null);
            $st = $isDone ? ($scan['status'] ?? $scan['col_8'] ?? 'Selesai') : 'Belum Maintenance';
            
            // Filter status
            if ($filterStatus !== '') {
                if ($filterStatus === 'done' && !$isDone) continue;
                if ($filterStatus === 'pending' && $isDone) continue;
                if ($filterStatus === 'repair' && (!in_array($st, ['Temuan', 'Perlu Perbaikan', 'Proses'], true))) continue;
                if (!in_array($filterStatus, ['done', 'pending', 'repair'], true) && strcasecmp($st, $filterStatus) !== 0) continue;
            }

            $total++;
            if ($isDone) {
                $done++;
                if (in_array($st, ['Temuan', 'Perlu Perbaikan', 'Proses'], true)) {
                    $repair++;
                }
            } else {
                $pending++;
            }

            $mid = $scan ? (int)($scan['id'] ?? $scan['col_0'] ?? 0) : 0;
            $chks = $mid > 0 ? ($chkMap[$mid] ?? []) : [];

            $rows[] = [
                'asset_id' => $aid,
                'kode_inventaris' => $a['kode_inventaris'] ?? '-',
                'serial_number' => $a['serial_number'] ?? '-',
                'perangkat' => trim(($a['merk'] ?? '').' '.($a['model'] ?? '')),
                'merk' => $a['merk'] ?? '',
                'model' => $a['model'] ?? '',
                'ip_address' => $a['ip_address'] ?? '-',
                'printer' => $a['printer'] ?? '-',
                'karyawan_nama' => $a['karyawan_nama'] ?? '-',
                'divisi_nama' => $a['divisi_nama'] ?? '-',
                'cabang_nama' => $a['cabang_nama'] ?? '-',
                'kategori_nama' => $a['kategori_nama'] ?? 'Perangkat IT',
                'is_done' => $isDone,
                'status' => $st,
                'log_id' => $mid,
                'maintenance_date' => $scan ? substr((string)($scan['maintenance_date'] ?? $scan['col_4'] ?? ''), 0, 10) : '-',
                'technician_name' => $scan ? ($scan['technician_name'] ?? $scan['col_3'] ?? 'Teknisi') : '-',
                'findings' => $scan ? ($scan['findings'] ?? $scan['col_11'] ?? '-') : '-',
                'recommendation' => $scan ? ($scan['recommendation'] ?? $scan['col_12'] ?? '-') : '-',
                'biometric_verified' => !empty($scan['biometric_verified'] ?? $scan['col_13'] ?? 0),
                'biometric_confidence' => (float)($scan['biometric_confidence'] ?? $scan['col_14'] ?? 0),
                'biometric_photo' => (string)($scan['biometric_photo'] ?? $scan['col_15'] ?? ''),
                'latitude' => (string)($scan['latitude'] ?? $scan['col_16'] ?? ''),
                'longitude' => (string)($scan['longitude'] ?? $scan['col_17'] ?? ''),
                'checklists' => $chks
            ];
        }

        $percent = $total > 0 ? round(($done / $total) * 100) : 0;
        return [
            'stats' => ['total' => $total, 'done' => $done, 'pending' => $pending, 'repair' => $repair, 'percent' => $percent],
            'rows' => $rows
        ];
    }

    // MySQL Mode
    $where = " WHERE (a.status = 'Aktif' OR a.status = 'aktif' OR a.status IS NULL OR a.status = '') ";
    $params = [];
    if ($cabangId > 0) { $where .= " AND a.id_cabang = ? "; $params[] = $cabangId; }
    if ($divisiId > 0) { $where .= " AND a.id_divisi = ? "; $params[] = $divisiId; }
    if ($filterKat > 0) { $where .= " AND a.id_kategori = ? "; $params[] = $filterKat; }

    $cName = name_column('cabang') ?: 'id';
    $kName = name_column('karyawan') ?: 'id';
    $dName = name_column('divisi') ?: 'id';
    $katName = name_column('kategori_aset') ?: 'id';
    $uName = name_column('users') ?: 'id';

    $assetCols = table_columns('assets');
    $ipCol = in_array('ip_address', $assetCols, true) ? 'a.ip_address' : "'-' AS ip_address";
    $prtCol = in_array('printer', $assetCols, true) ? 'a.printer' : "'-' AS printer";

    $scanCols = table_columns('maintenance_scan');
    $bioVerCol = in_array('biometric_verified', $scanCols, true) ? 'ms.biometric_verified' : "0 AS biometric_verified";
    $bioConfCol = in_array('biometric_confidence', $scanCols, true) ? 'ms.biometric_confidence' : "0 AS biometric_confidence";
    $bioPhotoCol = in_array('biometric_photo', $scanCols, true) ? 'ms.biometric_photo' : "'' AS biometric_photo";
    $latCol = in_array('latitude', $scanCols, true) ? 'ms.latitude' : "'' AS latitude";
    $lngCol = in_array('longitude', $scanCols, true) ? 'ms.longitude' : "'' AS longitude";

    $sql = "
        SELECT a.id AS asset_id, a.kode_inventaris, a.serial_number, a.merk, a.model,
               {$ipCol}, {$prtCol},
               c.`{$cName}` AS cabang_nama,
               k.`{$kName}` AS karyawan_nama,
               d.`{$dName}` AS divisi_nama,
               kat.`{$katName}` AS kategori_nama,
               ms.id AS log_id,
               ms.maintenance_date,
               ms.status AS scan_status,
               ms.findings,
               ms.recommendation,
               {$bioVerCol}, {$bioConfCol}, {$bioPhotoCol}, {$latCol}, {$lngCol},
               COALESCE(ms.technician_name, u.`{$uName}`, 'Teknisi') AS technician_name
        FROM assets a
        LEFT JOIN cabang c ON c.id = a.id_cabang
        LEFT JOIN karyawan k ON k.id = a.id_karyawan
        LEFT JOIN divisi d ON d.id = a.id_divisi
        LEFT JOIN kategori_aset kat ON kat.id = a.id_kategori
        LEFT JOIN maintenance_scan ms ON ms.asset_id = a.id AND ms.maintenance_month = {$month} AND ms.maintenance_year = {$year}
        LEFT JOIN users u ON u.id = ms.technician_user_id
        {$where}
        ORDER BY c.`{$cName}`, a.kode_inventaris ASC
    ";
    $st = db()->prepare($sql);
    $st->execute($params);
    $allRows = $st->fetchAll();

    $rows = [];
    $total = 0;
    $done = 0;
    $pending = 0;
    $repair = 0;

    foreach ($allRows as $r) {
        $isDone = !empty($r['log_id']);
        $stVal = $isDone ? ($r['scan_status'] ?: 'Selesai') : 'Belum Maintenance';

        if ($filterTech !== '' && $isDone && strcasecmp(trim($r['technician_name'] ?? ''), $filterTech) !== 0) {
            continue;
        }

        if ($filterStatus !== '') {
            if ($filterStatus === 'done' && !$isDone) continue;
            if ($filterStatus === 'pending' && $isDone) continue;
            if ($filterStatus === 'repair' && (!in_array($stVal, ['Temuan', 'Perlu Perbaikan', 'Proses'], true))) continue;
            if (!in_array($filterStatus, ['done', 'pending', 'repair'], true) && strcasecmp($stVal, $filterStatus) !== 0) continue;
        }

        $total++;
        if ($isDone) {
            $done++;
            if (in_array($stVal, ['Temuan', 'Perlu Perbaikan', 'Proses'], true)) $repair++;
        } else {
            $pending++;
        }

        $rows[] = [
            'asset_id' => (int)$r['asset_id'],
            'kode_inventaris' => $r['kode_inventaris'] ?? '-',
            'serial_number' => $r['serial_number'] ?? '-',
            'perangkat' => trim(($r['merk'] ?? '').' '.($r['model'] ?? '')),
            'merk' => $r['merk'] ?? '',
            'model' => $r['model'] ?? '',
            'ip_address' => $r['ip_address'] ?? '-',
            'printer' => $r['printer'] ?? '-',
            'karyawan_nama' => $r['karyawan_nama'] ?? '-',
            'divisi_nama' => $r['divisi_nama'] ?? '-',
            'cabang_nama' => $r['cabang_nama'] ?? '-',
            'kategori_nama' => $r['kategori_nama'] ?? 'Perangkat IT',
            'is_done' => $isDone,
            'status' => $stVal,
            'log_id' => (int)($r['log_id'] ?? 0),
            'maintenance_date' => $isDone ? substr((string)($r['maintenance_date'] ?? ''), 0, 10) : '-',
            'technician_name' => $isDone ? ($r['technician_name'] ?? 'Teknisi') : '-',
            'findings' => $isDone ? ($r['findings'] ?? '-') : '-',
            'recommendation' => $isDone ? ($r['recommendation'] ?? '-') : '-',
            'biometric_verified' => !empty($r['biometric_verified']),
            'biometric_confidence' => (float)($r['biometric_confidence'] ?? 0),
            'biometric_photo' => (string)($r['biometric_photo'] ?? ''),
            'latitude' => (string)($r['latitude'] ?? ''),
            'longitude' => (string)($r['longitude'] ?? ''),
            'checklists' => []
        ];
    }

    $percent = $total > 0 ? round(($done / $total) * 100) : 0;
    return [
        'stats' => ['total' => $total, 'done' => $done, 'pending' => $pending, 'repair' => $repair, 'percent' => $percent],
        'rows' => $rows
    ];
}

function get_monthly_overview(int $year, int $cabangId = 0): array {
    $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $currentMonth = (int)date('n');
    $currentYear = (int)date('Y');

    $overview = [];
    for ($m = 1; $m <= 12; $m++) {
        $audit = get_audit_maintenance_data([
            'bulan' => $m,
            'tahun' => $year,
            'cabang' => $cabangId
        ]);
        $stats = $audit['stats'];
        $isFuture = ($year > $currentYear) || ($year === $currentYear && $m > $currentMonth);
        $isCurrent = ($year === $currentYear && $m === $currentMonth);

        $overview[$m] = [
            'month' => $m,
            'month_name' => $monthNames[$m],
            'total' => $stats['total'],
            'done' => $stats['done'],
            'pending' => $stats['pending'],
            'repair' => $stats['repair'],
            'percent' => $stats['percent'],
            'is_future' => $isFuture,
            'is_current' => $isCurrent
        ];
    }
    return $overview;
}

