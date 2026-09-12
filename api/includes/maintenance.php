<?php
function get_fixed_checklists(): array {
    return [
        1 => 'Scan Virus',
        2 => 'Update Anti Virus',
        3 => 'Deleting Temporary File',
        4 => 'Cek Keyboard',
        5 => 'Cek Mouse',
        6 => 'Cek CPU & Monitor',
        7 => 'Cek Tinta',
        8 => 'Cek Cartridge',
        9 => 'Cek Nozzle'
    ];
}

function get_fixed_checklist_default_notes(): array {
    return [
        1 => 'Bersih',
        2 => 'Sudah update',
        3 => 'Sudah dibersihkan',
        4 => 'Normal',
        5 => 'Normal',
        6 => 'Normal',
        7 => 'Normal',
        8 => 'Normal',
        9 => 'Normal'
    ];
}

function get_asset_maintenance_status_month(int $assetId, int $month, int $year): ?array {
    if ($assetId <= 0) return null;
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return null;
        $scans = $client->getSheetData('Maintenance_Scan', false);
        $latest = null;
        foreach ($scans as $s) {
            $sAid = (int)($s['asset_id'] ?? $s['id_asset'] ?? $s['col_1'] ?? 0);
            $sM = (int)($s['maintenance_month'] ?? $s['month'] ?? $s['col_6'] ?? 0);
            $sY = (int)($s['maintenance_year'] ?? $s['year'] ?? $s['col_7'] ?? 0);
            $sDate = (string)($s['maintenance_date'] ?? $s['col_4'] ?? '');
            if ($sM <= 0 && $sDate !== '') {
                $sM = (int)date('n', strtotime($sDate));
            }
            if ($sY <= 0 && $sDate !== '') {
                $sY = (int)date('Y', strtotime($sDate));
            }
            if ($sAid === $assetId && $sM === $month && $sY === $year) {
                $latest = $s;
            }
        }
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['_recent_scan_' . $assetId])) {
            $rec = $_SESSION['_recent_scan_' . $assetId];
            $rM = (int)($rec['maintenance_month'] ?? (int)date('n', strtotime((string)($rec['maintenance_date'] ?? ''))));
            $rY = (int)($rec['maintenance_year'] ?? (int)date('Y', strtotime((string)($rec['maintenance_date'] ?? ''))));
            if ($rM === $month && $rY === $year) {
                $latest = array_merge($latest ?: [], $rec);
            }
        }
        return $latest;
    }
    try {
        $st = db()->prepare("
            SELECT ms.*, 
                   COALESCE(NULLIF(ms.technician_name, ''), u.nama, 'Teknisi') AS technician_name,
                   COALESCE(NULLIF(ms.technician_name, ''), u.nama, 'Teknisi') AS teknisi_nama
            FROM maintenance_scan ms
            LEFT JOIN users u ON u.id = ms.technician_user_id
            WHERE ms.asset_id = ? AND ms.maintenance_month = ? AND ms.maintenance_year = ?
            ORDER BY ms.id DESC LIMIT 1
        ");
        $st->execute([$assetId, $month, $year]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function get_asset_maintenance_history(int $assetId): array {
    if ($assetId <= 0) return [];
    $asset = get_asset_by_id($assetId);
    $assetKode = trim((string)($asset['kode_inventaris'] ?? ''));

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return [];
        $scans = $client->getSheetData('Maintenance_Scan', true);
        $history = [];
        $seenIds = [];

        foreach ($scans as $s) {
            $sAid = (int)($s['asset_id'] ?? $s['id_asset'] ?? $s['col_1'] ?? 0);
            $rawAid = trim((string)($s['asset_id'] ?? $s['id_asset'] ?? $s['col_1'] ?? ''));
            $sKode = trim((string)($s['kode_inventaris'] ?? ''));

            $isMatch = ($sAid === $assetId) 
                || ($rawAid === (string)$assetId)
                || ($assetKode !== '' && (strcasecmp($sKode, $assetKode) === 0 || strcasecmp($rawAid, $assetKode) === 0));

            if ($isMatch) {
                $hId = (int)($s['id'] ?? $s['col_0'] ?? 0);
                if ($hId > 0) {
                    $seenIds[$hId] = true;
                }
                $history[] = [
                    'id' => $hId,
                    'asset_id' => $sAid ?: $assetId,
                    'maintenance_date' => substr((string)($s['maintenance_date'] ?? $s['col_4'] ?? ''), 0, 10),
                    'maintenance_time' => substr((string)($s['maintenance_time'] ?? $s['col_5'] ?? ''), 0, 8),
                    'maintenance_month' => (int)($s['maintenance_month'] ?? $s['col_6'] ?? 0),
                    'maintenance_year' => (int)($s['maintenance_year'] ?? $s['col_7'] ?? 0),
                    'technician_name' => $s['technician_name'] ?? $s['col_3'] ?? 'Teknisi',
                    'maintenance_type' => $s['source'] ?? $s['maintenance_type'] ?? $s['col_9'] ?? 'Maintenance',
                    'findings' => $s['findings'] ?? $s['col_11'] ?? '',
                    'recommendation' => $s['recommendation'] ?? $s['col_12'] ?? '',
                    'status' => $s['status'] ?? $s['col_8'] ?? 'Selesai'
                ];
            }
        }

        // Cek session cache jika scan baru saja disimpan di sesi saat ini
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['_recent_scan_' . $assetId])) {
            $rec = $_SESSION['_recent_scan_' . $assetId];
            $rId = (int)($rec['id'] ?? 0);
            if ($rId > 0 && empty($seenIds[$rId])) {
                $history[] = [
                    'id' => $rId,
                    'asset_id' => $assetId,
                    'maintenance_date' => substr((string)($rec['maintenance_date'] ?? date('Y-m-d')), 0, 10),
                    'maintenance_time' => substr((string)($rec['maintenance_time'] ?? date('H:i:s')), 0, 8),
                    'maintenance_month' => (int)($rec['maintenance_month'] ?? date('n')),
                    'maintenance_year' => (int)($rec['maintenance_year'] ?? date('Y')),
                    'technician_name' => $rec['technician_name'] ?? 'Teknisi',
                    'maintenance_type' => $rec['maintenance_type'] ?? 'Maintenance',
                    'findings' => $rec['findings'] ?? '',
                    'recommendation' => $rec['recommendation'] ?? '',
                    'status' => $rec['status'] ?? 'Selesai'
                ];
            }
        }

        usort($history, function($a, $b) {
            $cmp = strcmp((string)($b['maintenance_date'] ?? ''), (string)($a['maintenance_date'] ?? ''));
            if ($cmp === 0) {
                return (int)($b['id'] ?? 0) - (int)($a['id'] ?? 0);
            }
            return $cmp;
        });
        return $history;
    }
    try {
        $st = db()->prepare("
            SELECT ms.*, COALESCE(ms.technician_name, u.nama, 'Teknisi') AS technician_name
            FROM maintenance_scan ms
            LEFT JOIN users u ON u.id = ms.technician_user_id
            WHERE ms.asset_id = ?
            ORDER BY ms.maintenance_date DESC, ms.id DESC
        ");
        $st->execute([$assetId]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function get_asset_active_finding(int $assetId): ?array {
    if ($assetId <= 0) return null;

    // Cek override sesi: jika baru saja diselesaikan tindak lanjutnya / status Selesai, temuan aktif langsung dianggap tuntas
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (!empty($_SESSION['_resolved_finding_' . $assetId]) && (time() - (int)$_SESSION['_resolved_finding_' . $assetId] < 3600)) {
            return null;
        }
        if (!empty($_SESSION['_recent_scan_' . $assetId])) {
            $rec = $_SESSION['_recent_scan_' . $assetId];
            $st = strtolower(trim((string)($rec['status'] ?? '')));
            if (in_array($st, ['selesai', 'ok', 'resolved', 'closed', 'normal'], true)) {
                return null;
            }
        }
    }

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return null;

        $scans = $client->getSheetData('Maintenance_Scan', false);
        $assetScans = [];
        foreach ($scans as $s) {
            $sAid = (int)($s['asset_id'] ?? $s['id_asset'] ?? $s['col_1'] ?? 0);
            if ($sAid === $assetId) {
                $assetScans[] = $s;
            }
        }
        $latestScan = null;
        if (!empty($assetScans)) {
            usort($assetScans, function($a, $b) {
                return (int)($b['id'] ?? $b['col_0'] ?? 0) - (int)($a['id'] ?? $a['col_0'] ?? 0);
            });
            $latestScan = $assetScans[0];
        }

        // 1. Cek sheet Maintenance_Findings jika ada
        try {
            $findings = $client->getSheetData('Maintenance_Findings', false);
            foreach ($findings as $f) {
                $fAid = (int)($f['asset_id'] ?? $f['id_asset'] ?? $f['col_2'] ?? 0);
                if ($fAid === $assetId) {
                    $fStatus = trim((string)($f['status'] ?? $f['repair_status'] ?? $f['col_6'] ?? 'Open'));
                    if (!in_array(strtolower($fStatus), ['resolved', 'closed', 'selesai', 'done', 'ok'], true)) {
                        // Jika ada scan terbaru untuk aset ini yang sudah 'Selesai', temuan lama dianggap terselesaikan
                        if ($latestScan) {
                            $latestSt = strtolower(trim((string)($latestScan['status'] ?? $latestScan['col_8'] ?? '')));
                            $latestScanId = (int)($latestScan['id'] ?? $latestScan['col_0'] ?? 0);
                            $fLogId = (int)($f['maintenance_scan_id'] ?? $f['maintenance_id'] ?? $f['log_id'] ?? $f['col_1'] ?? 0);
                            if (in_array($latestSt, ['selesai', 'resolved', 'closed', 'ok'], true) && ($fLogId <= 0 || $latestScanId >= $fLogId)) {
                                continue;
                            }
                        }

                        $fText = '';
                        foreach (['deskripsi_temuan', 'finding', 'description', 'masalah', 'catatan', 'col_4'] as $k) {
                            if (isset($f[$k]) && trim((string)$f[$k]) !== '' && trim((string)$f[$k]) !== '-') {
                                $fText = trim((string)$f[$k]);
                                break;
                            }
                        }
                        if ($fText === '' && !empty($latestScan['findings']) && $latestScan['findings'] !== '-') {
                            $fText = (string)$latestScan['findings'];
                        }

                        $fDate = '';
                        foreach (['reported_at', 'created_at', 'tanggal', 'date', 'col_8'] as $k) {
                            if (isset($f[$k]) && trim((string)$f[$k]) !== '' && trim((string)$f[$k]) !== '-') {
                                $fDate = trim((string)$f[$k]);
                                break;
                            }
                        }
                        if ($fDate === '' && !empty($latestScan['maintenance_date'])) {
                            $fDate = (string)$latestScan['maintenance_date'];
                        }

                        $fRep = '';
                        foreach (['reported_by', 'created_by', 'reporter', 'teknisi', 'technician', 'col_7'] as $k) {
                            if (isset($f[$k]) && trim((string)$f[$k]) !== '' && trim((string)$f[$k]) !== '-' && strcasecmp(trim((string)$f[$k]), 'teknisi') !== 0) {
                                $fRep = trim((string)$f[$k]);
                                break;
                            }
                        }
                        if ($fRep === '' && !empty($latestScan['technician_name'])) {
                            $fRep = (string)$latestScan['technician_name'];
                        }

                        return [
                            'type' => 'finding_table',
                            'id' => (int)($f['id'] ?? $f['col_0'] ?? 0),
                            'finding_id' => (int)($f['id'] ?? $f['col_0'] ?? 0),
                            'log_id' => (int)($f['maintenance_scan_id'] ?? ($latestScan['id'] ?? 0)),
                            'asset_id' => $assetId,
                            'finding' => $fText ?: 'Pemeriksaan lanjutan perangkat',
                            'recommendation' => (string)($f['tindakan_diperlukan'] ?? $f['action_taken'] ?? ($latestScan['recommendation'] ?? '')),
                            'status' => $fStatus,
                            'reporter' => $fRep ?: 'Teknisi',
                            'date' => $fDate ?: date('Y-m-d'),
                            'severity' => (string)($f['kategori_temuan'] ?? $f['severity'] ?? 'Sedang'),
                        ];
                    }
                }
            }
        } catch (Throwable $e) {}

        // 2. Cek sheet Maintenance_Scan jika ada temuan di log scan
        if ($latestScan) {
            $st = trim((string)($latestScan['status'] ?? $latestScan['col_8'] ?? 'Selesai'));
            $isResolved = in_array(strtolower($st), ['selesai', 'closed', 'resolved', 'ok'], true);
            $hasFinding = !empty($latestScan['findings']) && $latestScan['findings'] !== '-';

            if (!$isResolved || in_array(strtolower($st), ['temuan', 'perlu perbaikan', 'perlu tindak lanjut', 'proses'], true)) {
                return [
                    'type' => 'scan_log',
                    'id' => (int)($latestScan['id'] ?? $latestScan['col_0'] ?? 0),
                    'finding_id' => 0,
                    'log_id' => (int)($latestScan['id'] ?? $latestScan['col_0'] ?? 0),
                    'asset_id' => $assetId,
                    'finding' => $hasFinding ? (string)$latestScan['findings'] : 'Pemeriksaan lanjutan perangkat',
                    'recommendation' => (string)($latestScan['recommendation'] ?? $latestScan['col_12'] ?? ''),
                    'status' => $st ?: 'Perlu Tindak Lanjut',
                    'reporter' => (string)($latestScan['technician_name'] ?? $latestScan['col_3'] ?? 'Teknisi'),
                    'date' => (string)($latestScan['maintenance_date'] ?? $latestScan['col_4'] ?? date('Y-m-d')),
                    'severity' => 'Sedang'
                ];
            }
        }

        return null;
    }

    // MySQL Mode
    try {
        $st = db()->prepare("
            SELECT mf.* FROM maintenance_findings mf
            WHERE mf.asset_id = ? AND LOWER(COALESCE(mf.repair_status, 'open')) NOT IN ('resolved', 'closed', 'selesai', 'done')
              AND NOT EXISTS (
                  SELECT 1 FROM maintenance_scan ms 
                  WHERE ms.asset_id = mf.asset_id 
                    AND ms.id >= COALESCE(mf.maintenance_scan_id, 0)
                    AND LOWER(ms.status) IN ('selesai', 'ok', 'resolved')
              )
            ORDER BY mf.id DESC LIMIT 1
        ");
        $st->execute([$assetId]);
        $row = $st->fetch();
        if ($row) {
            return [
                'type' => 'finding_table',
                'id' => (int)$row['id'],
                'finding_id' => (int)$row['id'],
                'log_id' => (int)($row['maintenance_scan_id'] ?? 0),
                'asset_id' => $assetId,
                'finding' => (string)($row['finding'] ?? ''),
                'recommendation' => (string)($row['action_taken'] ?? ''),
                'status' => (string)($row['repair_status'] ?? 'Open'),
                'reporter' => (string)($row['created_by'] ?? 'Teknisi'),
                'date' => (string)($row['created_at'] ?? ''),
                'severity' => (string)($row['severity'] ?? 'Sedang')
            ];
        }

        $st2 = db()->prepare("
            SELECT * FROM maintenance_scan 
            WHERE asset_id = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $st2->execute([$assetId]);
        $scan = $st2->fetch();
        if ($scan) {
            $stScan = trim((string)($scan['status'] ?? ''));
            $isResolved = in_array(strtolower($stScan), ['selesai', 'closed', 'resolved', 'ok'], true);
            if (!$isResolved || in_array(strtolower($stScan), ['temuan', 'perlu perbaikan', 'perlu tindak lanjut', 'proses'], true)) {
                return [
                    'type' => 'scan_log',
                    'id' => (int)$scan['id'],
                    'finding_id' => 0,
                    'log_id' => (int)$scan['id'],
                    'asset_id' => $assetId,
                    'finding' => !empty($scan['findings']) ? (string)$scan['findings'] : 'Pemeriksaan lanjutan perangkat',
                    'recommendation' => (string)($scan['recommendation'] ?? ''),
                    'status' => $stScan ?: 'Perlu Tindak Lanjut',
                    'reporter' => (string)($scan['technician_name'] ?? 'Teknisi'),
                    'date' => (string)($scan['maintenance_date'] ?? ''),
                    'severity' => 'Sedang'
                ];
            }
        }
        return null;
    } catch (Throwable $e) {
        return null;
    }
}

function resolve_asset_finding(int $assetId, array $data): array {
    if ($assetId <= 0) return ['success' => false, 'error' => 'ID aset tidak valid'];

    $logId = (int)($data['log_id'] ?? 0);
    $findingId = (int)($data['finding_id'] ?? 0);
    $techName = trim((string)($data['technician_name'] ?? 'Teknisi'));
    $actionTaken = trim((string)($data['action_taken'] ?? ''));
    $status = trim((string)($data['status'] ?? 'Selesai'));
    if (!in_array($status, ['Selesai', 'Proses', 'Perlu Perbaikan'], true)) {
        $status = 'Selesai';
    }
    $updateChecklists = !empty($data['update_checklists']);
    $date = trim((string)($data['date'] ?? date('Y-m-d')));
    $time = trim((string)($data['time'] ?? date('H:i:s')));
    $bioVerified = !empty($data['biometric_verified']) ? 1 : 0;
    $bioConfidence = (float)($data['biometric_confidence'] ?? 0);
    $bioPhoto = trim((string)($data['biometric_photo'] ?? ''));

    // Jika verifikasi biometrik berhasil namun foto tangkapan kosong, ambil foto profil biometrik teknisi
    if ($bioVerified && $bioPhoto === '' && $techName !== '') {
        $enrolled = get_enrolled_technicians(false);
        foreach ($enrolled as $en) {
            if (strcasecmp((string)$en['nama'], $techName) === 0 && !empty($en['photo'])) {
                $bioPhoto = (string)$en['photo'];
                break;
            }
        }
    } elseif (!$bioVerified) {
        $bioPhoto = '';
        $bioConfidence = 0;
    }

    if ($actionTaken === '') {
        return ['success' => false, 'error' => 'Tindakan perbaikan wajib diisi'];
    }

    $bioTag = $bioVerified ? " [AI Face ID " . round($bioConfidence) . "% Verified]" : "";
    $formattedAction = "[Tindak Lanjut " . date('d/m/Y', strtotime($date)) . " oleh {$techName}{$bioTag}]: " . $actionTaken;

    // 1. Jika logId belum ada, cari log maintenance terakhir untuk aset ini
    if ($logId <= 0) {
        $activeF = get_asset_active_finding($assetId);
        if ($activeF && !empty($activeF['log_id'])) {
            $logId = (int)$activeF['log_id'];
        }
    }

    // 2. Update maintenance scan log jika ada
    if ($logId > 0) {
        $detail = get_maintenance_detail($logId);
        $oldScan = $detail['scan'] ?? [];
        $oldFindings = ($status === 'Selesai') ? '' : (string)($oldScan['findings'] ?? '');
        $oldRecom = (string)($oldScan['recommendation'] ?? '');
        $newRecom = ($oldRecom !== '' && $oldRecom !== '-') ? $oldRecom . "\n" . $formattedAction : $formattedAction;

        $checklistsPayload = [];
        if ($updateChecklists) {
            $fixed = get_fixed_checklists();
            foreach ($fixed as $num => $nm) {
                $checklistsPayload[$num] = [
                    'checked' => 1,
                    'notes' => 'Normal (Selesai Ditindaklanjuti)'
                ];
            }
        } elseif (!empty($detail['checklists'])) {
            $checklistsPayload = $detail['checklists'];
        }

        $upRes = update_maintenance_detail($logId, [
            'status' => $status,
            'findings' => $oldFindings,
            'recommendation' => $newRecom,
            'technician_name' => $techName,
            'maintenance_date' => $date,
            'maintenance_time' => $time,
            'checklists' => $checklistsPayload,
            'biometric_verified' => $bioVerified,
            'biometric_confidence' => $bioConfidence,
            'biometric_photo' => $bioPhoto
        ]);

        if (empty($upRes['success'])) {
            return ['success' => false, 'error' => $upRes['error'] ?? 'Gagal memperbarui log maintenance'];
        }
    } else {
        // Buat scan log baru jika belum ada scan sama sekali
        $checklistsPayload = [];
        $fixed = get_fixed_checklists();
        foreach ($fixed as $num => $nm) {
            $checklistsPayload[$num] = [
                'checked' => $updateChecklists ? 1 : 0,
                'notes' => $updateChecklists ? 'Normal (Selesai Ditindaklanjuti)' : '-'
            ];
        }
        $saved = save_maintenance_record([
            'asset_id' => $assetId,
            'technician_name' => $techName,
            'maintenance_date' => $date,
            'maintenance_time' => $time,
            'maintenance_month' => (int)date('n', strtotime($date)),
            'maintenance_year' => (int)date('Y', strtotime($date)),
            'status' => $status,
            'maintenance_type' => 'Tindak Lanjut',
            'findings' => '',
            'recommendation' => $formattedAction,
            'biometric_verified' => $bioVerified,
            'biometric_confidence' => $bioConfidence,
            'biometric_photo' => $bioPhoto,
            'checklists' => $checklistsPayload
        ]);
        if (!empty($saved['log_id'])) {
            $logId = (int)$saved['log_id'];
        }
    }

    // 3. Update sheet Maintenance_Findings jika ada
    $techResolvedStr = $techName . ($bioVerified ? " (Face ID " . round($bioConfidence) . "%)" : "");
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if ($client) {
            try {
                $fRows = $client->getSheetData('Maintenance_Findings', true);
                foreach ($fRows as $fr) {
                    $frId = (int)($fr['id'] ?? 0);
                    $frMid = (int)($fr['maintenance_scan_id'] ?? 0);
                    $frAid = (int)($fr['asset_id'] ?? 0);
                    if (($findingId > 0 && $frId === $findingId) || ($logId > 0 && $frMid === $logId) || ($frAid === $assetId)) {
                        $rowNum = (int)($fr['_row_num'] ?? 0);
                        if ($rowNum > 1) {
                            $fStatus = ($status === 'Selesai') ? 'Resolved' : 'In Progress';
                            $client->updateValues("Maintenance_Findings!G{$rowNum}:L{$rowNum}", [[
                                $fStatus,
                                $fr['reported_by'] ?? 'Teknisi',
                                $fr['reported_at'] ?? $date,
                                $techResolvedStr,
                                date('Y-m-d H:i:s'),
                                $actionTaken
                            ]]);
                        }
                    }
                }
            } catch (Throwable $e) {}
            $client->clearCache('Maintenance_Scan');
            $client->clearCache('Maintenance_Findings');
            $client->clearCache('Maintenance_Checklists');
            $client->clearCache();
        }
    } else {
        // MySQL
        try {
            $fStatus = ($status === 'Selesai') ? 'Resolved' : 'In Progress';
            $st = db()->prepare("
                UPDATE maintenance_findings 
                SET repair_status = ?, resolved_by = ?, resolved_at = NOW(), action_taken = ?
                WHERE (id = ? AND ? > 0) OR (maintenance_scan_id = ? AND ? > 0) OR asset_id = ?
            ");
            $st->execute([$fStatus, $techResolvedStr, $actionTaken, $findingId, $findingId, $logId, $logId, $assetId]);
        } catch (Throwable $e) {}
    }

    // Sinkronisasi Sesi: Kartu Kontrol & Status Perangkat langsung normal seketika
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['_recent_scan_' . $assetId] = [
            'id' => $logId,
            'asset_id' => $assetId,
            'technician_name' => $techName,
            'maintenance_date' => $date,
            'maintenance_time' => $time,
            'maintenance_month' => (int)date('n', strtotime($date)),
            'maintenance_year' => (int)date('Y', strtotime($date)),
            'status' => $status,
            'findings' => ($status === 'Selesai' ? '' : ($oldFindings ?? '')),
            'recommendation' => $formattedAction,
            'biometric_verified' => $bioVerified,
            'biometric_confidence' => $bioConfidence,
            'biometric_photo' => $bioPhoto,
            'checklists' => $checklistsPayload
        ];
        if ($status === 'Selesai') {
            $_SESSION['_resolved_finding_' . $assetId] = time();
        }
    }

    return ['success' => true, 'log_id' => $logId, 'status' => $status];
}

function get_asset_yearly_maintenance_grid(int $assetId, int $year): array {
    $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $grid = [];
    for ($m = 1; $m <= 12; $m++) {
        $grid[$m] = [
            'month' => $m,
            'month_name' => $monthNames[$m],
            'status' => 'pending', // 'done' or 'pending'
            'is_done' => false,
            'date' => '',
            'technician' => '',
            'log_id' => 0
        ];
    }

    $history = get_asset_maintenance_history($assetId);
    foreach ($history as $h) {
        $hYear = (int)($h['maintenance_year'] ?? (int)date('Y', strtotime($h['maintenance_date'] ?? '')));
        $hMonth = (int)($h['maintenance_month'] ?? (int)date('n', strtotime($h['maintenance_date'] ?? '')));
        if ($hYear === $year && isset($grid[$hMonth])) {
            $grid[$hMonth]['status'] = 'done';
            $grid[$hMonth]['is_done'] = true;
            $grid[$hMonth]['date'] = substr((string)($h['maintenance_date'] ?? ''), 0, 10);
            $grid[$hMonth]['technician'] = $h['technician_name'] ?? 'Teknisi';
            $grid[$hMonth]['log_id'] = (int)($h['id'] ?? 0);
        }
    }
    return $grid;
}

function get_asset_yearly_card_matrix(int $assetId, int $year): array {
    $yrSuffix = sprintf('%02d', $year % 100);
    $matrix = [];
    for ($m = 1; $m <= 12; $m++) {
        $matrix[$m] = [
            'month' => $m,
            'month_tag' => sprintf('/%02d/%s', $m, $yrSuffix),
            'date_str' => sprintf('/%02d/%s', $m, $yrSuffix),
            'is_done' => false,
            'log_id' => 0,
            'checklists' => [1=>0, 2=>0, 3=>0, 4=>0, 5=>0, 6=>0, 7=>0, 8=>0, 9=>0],
            'paraf' => '',
            'status' => ''
        ];
    }

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if ($client) {
            $scans = $client->getSheetData('Maintenance_Scan', false);
            $chkRows = $client->getSheetData('Maintenance_Checklists', false);

            $chkMap = [];
            foreach ($chkRows as $c) {
                $mid = (int)($c['maintenance_id'] ?? $c['maintenance_scan_id'] ?? $c['id_maintenance'] ?? $c['log_id'] ?? $c['scan_id'] ?? $c['col_1'] ?? 0);
                $num = (int)($c['checklist_number'] ?? $c['number'] ?? $c['item_number'] ?? $c['no'] ?? $c['checklist_id'] ?? $c['col_3'] ?? 0);
                $isCh = strtolower(trim((string)($c['checked'] ?? $c['status'] ?? $c['is_checked'] ?? $c['col_5'] ?? '0')));
                $checked = in_array($isCh, ['1', 'true', 'yes', 'v', '✓', 'ok', 'selesai', 'normal', 'checked'], true) ? 1 : 0;
                if ($mid > 0 && $num >= 1 && $num <= 9) {
                    if (!isset($chkMap[$mid])) {
                        $chkMap[$mid] = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0, 6=>0, 7=>0, 8=>0, 9=>0];
                    }
                    $chkMap[$mid][$num] = $checked;
                }
            }

            // Filter & urutkan scan agar scan terbaru pada bulan tersebut yang digunakan
            $assetScans = [];
            foreach ($scans as $s) {
                $sAid = (int)($s['asset_id'] ?? $s['id_asset'] ?? $s['col_1'] ?? 0);
                if ($sAid === $assetId) {
                    $sYear = (int)($s['maintenance_year'] ?? $s['year'] ?? $s['col_7'] ?? (int)date('Y', strtotime((string)($s['maintenance_date'] ?? $s['col_4'] ?? ''))));
                    if ($sYear === $year) {
                        $assetScans[] = $s;
                    }
                }
            }

            // Fallback session jika record baru belum ter-refresh dari API cache Google Sheets
            if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['_recent_scan_' . $assetId])) {
                $rec = $_SESSION['_recent_scan_' . $assetId];
                if ((int)($rec['maintenance_year'] ?? 0) === $year) {
                    $replaced = false;
                    foreach ($assetScans as $k => $as) {
                        if ((int)($as['id'] ?? $as['col_0'] ?? 0) === (int)$rec['id']) {
                            $assetScans[$k] = array_merge($as, $rec);
                            $replaced = true;
                            break;
                        }
                    }
                    if (!$replaced) {
                        $assetScans[] = $rec;
                    }
                }
            }

            usort($assetScans, function($a, $b) {
                $da = (string)($a['maintenance_date'] ?? $a['col_4'] ?? '') . ' ' . (string)($a['maintenance_time'] ?? $a['col_5'] ?? '');
                $db = (string)($b['maintenance_date'] ?? $b['col_4'] ?? '') . ' ' . (string)($b['maintenance_time'] ?? $b['col_5'] ?? '');
                if ($da === $db) {
                    return ((int)($a['id'] ?? $a['col_0'] ?? 0)) <=> ((int)($b['id'] ?? $b['col_0'] ?? 0));
                }
                return strcmp($da, $db);
            });

            foreach ($assetScans as $s) {
                $sMonth = (int)($s['maintenance_month'] ?? $s['month'] ?? $s['col_6'] ?? (int)date('n', strtotime((string)($s['maintenance_date'] ?? $s['col_4'] ?? ''))));
                if (isset($matrix[$sMonth])) {
                    $logId = (int)($s['id'] ?? $s['col_0'] ?? 0);
                    $d = substr((string)($s['maintenance_date'] ?? $s['col_4'] ?? ''), 0, 10);
                    $dDay = $d ? date('d', strtotime($d)) : '';
                    $dateFormatted = $dDay ? "{$dDay}/" . sprintf('%02d/%s', $sMonth, $yrSuffix) : sprintf('/%02d/%s', $sMonth, $yrSuffix);

                    $matrix[$sMonth]['is_done'] = true;
                    $matrix[$sMonth]['log_id'] = $logId;
                    $matrix[$sMonth]['date_str'] = $dateFormatted;
                    $techRaw = (string)($s['technician_name'] ?? $s['col_3'] ?? 'Teknisi');
                    $matrix[$sMonth]['paraf'] = get_technician_nickname($techRaw);
                    $matrix[$sMonth]['status'] = $s['status'] ?? $s['col_8'] ?? 'Selesai';

                    if (!empty($s['checklists']) && is_array($s['checklists'])) {
                        $matrix[$sMonth]['checklists'] = [];
                        foreach ($s['checklists'] as $chkN => $chkI) {
                            $matrix[$sMonth]['checklists'][$chkN] = is_array($chkI) ? ($chkI['checked'] ?? 1) : $chkI;
                        }
                    } elseif (isset($chkMap[$logId])) {
                        $matrix[$sMonth]['checklists'] = $chkMap[$logId];
                    } else {
                        // Fallback jika memang tidak ada data checklist terpisah (misal log lama)
                        $scanStatus = trim((string)($s['status'] ?? $s['col_8'] ?? 'Selesai'));
                        $isCompleted = ($scanStatus === 'Selesai' || $scanStatus === 'Normal' || $scanStatus === 'OK' || $scanStatus === '');
                        $defVal = $isCompleted ? 1 : 0;
                        $matrix[$sMonth]['checklists'] = [
                            1 => $defVal, 2 => $defVal, 3 => $defVal,
                            4 => $defVal, 5 => $defVal, 6 => $defVal,
                            7 => $defVal, 8 => $defVal, 9 => $defVal
                        ];
                    }
                }
            }
        }
        return $matrix;
    }

    // MySQL Mode
    $st = db()->prepare("
        SELECT ms.* 
        FROM maintenance_scan ms
        WHERE ms.asset_id = ? AND ms.maintenance_year = ?
        ORDER BY ms.maintenance_date ASC, ms.id ASC
    ");
    $st->execute([$assetId, $year]);
    $scans = $st->fetchAll();

    foreach ($scans as $s) {
        $sMonth = (int)($s['maintenance_month'] ?? (int)date('n', strtotime($s['maintenance_date'] ?? '')));
        if (isset($matrix[$sMonth])) {
            $logId = (int)$s['id'];
            $d = substr((string)($s['maintenance_date'] ?? ''), 0, 10);
            $dDay = $d ? date('d', strtotime($d)) : '';
            $dateFormatted = $dDay ? "{$dDay}/" . sprintf('%02d/%s', $sMonth, $yrSuffix) : sprintf('/%02d/%s', $sMonth, $yrSuffix);

            $matrix[$sMonth]['is_done'] = true;
            $matrix[$sMonth]['log_id'] = $logId;
            $matrix[$sMonth]['date_str'] = $dateFormatted;
            $techRaw = (string)($s['technician_name'] ?: 'Teknisi');
            $matrix[$sMonth]['paraf'] = get_technician_nickname($techRaw);
            $matrix[$sMonth]['status'] = $s['status'] ?: 'Selesai';

            $chkSt = db()->prepare("SELECT checklist_number, checked FROM maintenance_checklists WHERE maintenance_id = ?");
            $chkSt->execute([$logId]);
            $chks = $chkSt->fetchAll();
            if (!empty($chks)) {
                $mChecklists = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0, 6=>0, 7=>0, 8=>0, 9=>0];
                foreach ($chks as $c) {
                    $cNum = (int)$c['checklist_number'];
                    if ($cNum >= 1 && $cNum <= 9) {
                        $mChecklists[$cNum] = (int)$c['checked'];
                    }
                }
                $matrix[$sMonth]['checklists'] = $mChecklists;
            } else {
                $scanStatus = trim((string)($s['status'] ?: 'Selesai'));
                $isCompleted = ($scanStatus === 'Selesai' || $scanStatus === 'Normal' || $scanStatus === 'OK' || $scanStatus === '');
                $defVal = $isCompleted ? 1 : 0;
                $matrix[$sMonth]['checklists'] = [
                    1 => $defVal, 2 => $defVal, 3 => $defVal,
                    4 => $defVal, 5 => $defVal, 6 => $defVal,
                    7 => $defVal, 8 => $defVal, 9 => $defVal
                ];
            }
        }
    }
    return $matrix;
}

function save_maintenance_record(array $data): array {
    $assetId = (int)($data['asset_id'] ?? 0);
    if ($assetId <= 0) return ['success' => false, 'error' => 'Asset ID tidak valid'];

    $date = !empty($data['maintenance_date']) ? substr((string)$data['maintenance_date'], 0, 10) : date('Y-m-d');
    $time = !empty($data['maintenance_time']) ? substr((string)$data['maintenance_time'], 0, 8) : date('H:i:s');
    $month = (int)date('n', strtotime($date));
    $year = (int)date('Y', strtotime($date));
    $userId = (int)($data['technician_user_id'] ?? current_user_id());
    $techName = trim((string)($data['technician_name'] ?? ''));
    if ($techName === '') {
        $techName = current_user_name();
    }
    $status = trim((string)($data['status'] ?? 'Selesai'));
    if (!in_array($status, ['Selesai', 'Proses', 'Perlu Perbaikan', 'Temuan'], true)) {
        $status = 'Selesai';
    }
    $mType = trim((string)($data['maintenance_type'] ?? 'Maintenance'));
    $findings = trim((string)($data['findings'] ?? ''));
    $recommendation = trim((string)($data['recommendation'] ?? ''));
    $bioVerified = !empty($data['biometric_verified']) ? 1 : 0;
    $bioConfidence = (float)($data['biometric_confidence'] ?? 0);
    $bioPhoto = trim((string)($data['biometric_photo'] ?? ''));
    $latitude = trim((string)($data['latitude'] ?? ''));
    $longitude = trim((string)($data['longitude'] ?? ''));
    $checklists = (array)($data['checklists'] ?? []);

    $fixedItems = get_fixed_checklists();
    $defaultNotes = get_fixed_checklist_default_notes();

    // Pastikan jika checklists kosong dan status Selesai, berikan checklist default
    if (empty($checklists)) {
        $isCompleted = ($status === 'Selesai');
        foreach ($fixedItems as $num => $name) {
            $checklists[$num] = [
                'checked' => $isCompleted ? 1 : 0,
                'notes' => $isCompleted ? ($defaultNotes[$num] ?? 'Normal') : ''
            ];
        }
    }

    if (strlen($bioPhoto) > 18000) {
        $bioPhoto = substr($bioPhoto, 0, 18000);
    }
    // Prevent Google Sheets formula interpretation if starting with special characters
    if ($bioPhoto !== '' && in_array($bioPhoto[0], ['=', '+', '-', '@'], true)) {
        $bioPhoto = "'" . $bioPhoto;
    }

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];

        // Pastikan sheet Maintenance_Scan dan Maintenance_Checklists minimal 26 kolom A..Z
        $client->ensureMinColumns('Maintenance_Scan', 26);
        $client->ensureMinColumns('Maintenance_Checklists', 26);

        $fullScanHeaders = ['id', 'asset_id', 'technician_user_id', 'technician_name', 'maintenance_date', 'maintenance_time', 'maintenance_month', 'maintenance_year', 'status', 'source', 'created_at', 'findings', 'recommendation', 'biometric_verified', 'biometric_confidence', 'biometric_photo', 'latitude', 'longitude'];

        // Cek header tabel Maintenance_Scan
        $scanHead = $client->getValues('Maintenance_Scan!A1:R1');
        if (empty($scanHead) || empty($scanHead[0]) || count($scanHead[0]) < count($fullScanHeaders)) {
            $client->createSheetIfNotExists('Maintenance_Scan');
            $client->updateValues('Maintenance_Scan!A1:R1', [$fullScanHeaders]);
        }

        // Cek apakah scan untuk asset ini di bulan & tahun yang sama sudah pernah tersimpan sebelumnya
        $existingScans = $client->getSheetData('Maintenance_Scan', true);
        $targetScanRowIdx = 0;
        $activeScanId = 0;
        foreach ($existingScans as $es) {
            $esAid = (int)($es['asset_id'] ?? $es['id_asset'] ?? $es['col_1'] ?? 0);
            $esM = (int)($es['maintenance_month'] ?? $es['month'] ?? $es['col_6'] ?? 0);
            $esY = (int)($es['maintenance_year'] ?? $es['year'] ?? $es['col_7'] ?? 0);
            $esDate = (string)($es['maintenance_date'] ?? $es['col_4'] ?? '');
            if ($esM <= 0 && $esDate !== '') {
                $esM = (int)date('n', strtotime($esDate));
            }
            if ($esY <= 0 && $esDate !== '') {
                $esY = (int)date('Y', strtotime($esDate));
            }

            if ($esAid === $assetId && $esM === $month && $esY === $year) {
                $targetScanRowIdx = (int)($es['_row_num'] ?? 0);
                $activeScanId = (int)($es['id'] ?? $es['col_0'] ?? 0);
                break;
            }
        }

        if ($activeScanId <= 0 || $targetScanRowIdx <= 1) {
            // Hitung Scan ID dan baris baru di Kolom A
            $rawScanCol = $client->getValues('Maintenance_Scan!A:A');
            $maxId = 0;
            if (!empty($rawScanCol)) {
                foreach ($rawScanCol as $cRow) {
                    $val = (int)($cRow[0] ?? 0);
                    if ($val > $maxId) $maxId = $val;
                }
            }
            $activeScanId = max(count($rawScanCol), $maxId + 1);
            if ($activeScanId <= 0) $activeScanId = 1;
            $targetScanRowIdx = max(1, count($rawScanCol)) + 1;
        }

        $newScanRow = [
            $activeScanId,
            $assetId,
            $userId,
            $techName,
            $date,
            $time,
            $month,
            $year,
            $status,
            $mType,
            date('Y-m-d H:i:s'),
            $findings,
            $recommendation,
            $bioVerified,
            $bioConfidence,
            $bioPhoto,
            $latitude,
            $longitude
        ];

        // Tulis tepat pada baris target dan kolom A..R (mencegah kolom bergeser ke kanan)
        $scanOk = $client->updateValues("Maintenance_Scan!A{$targetScanRowIdx}:R{$targetScanRowIdx}", [$newScanRow]);
        if (!$scanOk) {
            $client->ensureMinColumns('Maintenance_Scan', 26);
            $scanOk = $client->updateValues("Maintenance_Scan!A{$targetScanRowIdx}:R{$targetScanRowIdx}", [$newScanRow]);
        }
        if (!$scanOk) {
            $scanOk = $client->appendValues('Maintenance_Scan!A:R', [$newScanRow]);
        }
        if (!$scanOk) {
            error_log("save_maintenance_record: updateValues Maintenance_Scan failed!");
            return ['success' => false, 'error' => 'Gagal menyimpan rekaman maintenance ke Google Sheet.'];
        }

        // 2. Simpan 9 item checklist
        $rawChkCol = $client->getValues('Maintenance_Checklists!A:A');
        $maxChkId = 0;
        if (!empty($rawChkCol)) {
            foreach ($rawChkCol as $cRow) {
                $val = (int)($cRow[0] ?? 0);
                if ($val > $maxChkId) $maxChkId = $val;
            }
        }
        $nextChkId = max(count($rawChkCol), $maxChkId + 1);
        if ($nextChkId <= 0) $nextChkId = 1;

        $fullChkHeaders = ['id', 'maintenance_id', 'asset_id', 'checklist_number', 'checklist_name', 'checked', 'notes', 'created_at'];
        $chkHead = $client->getValues('Maintenance_Checklists!A1:H1');
        if (empty($chkHead) || empty($chkHead[0]) || count($chkHead[0]) < count($fullChkHeaders)) {
            $client->createSheetIfNotExists('Maintenance_Checklists');
            $client->updateValues('Maintenance_Checklists!A1:H1', [$fullChkHeaders]);
        }

        $chkRows = [];
        foreach ($fixedItems as $num => $name) {
            $chkItem = $checklists[$num] ?? [];
            $checked = !empty($chkItem['checked']) ? 1 : 0;
            $notes = trim((string)($chkItem['notes'] ?? ($checked ? ($defaultNotes[$num] ?? 'Normal') : '')));
            $chkRows[] = [
                $nextChkId,
                $activeScanId,
                $assetId,
                $num,
                $name,
                $checked,
                $notes,
                date('Y-m-d H:i:s')
            ];
            $nextChkId++;
        }

        $nextChkRow = max(1, count($rawChkCol)) + 1;
        $endChkRow = $nextChkRow + count($chkRows) - 1;
        $chkOk = $client->updateValues("Maintenance_Checklists!A{$nextChkRow}:H{$endChkRow}", $chkRows);
        if (!$chkOk) {
            $client->ensureMinColumns('Maintenance_Checklists', 26);
            $chkOk = $client->updateValues("Maintenance_Checklists!A{$nextChkRow}:H{$endChkRow}", $chkRows);
            if (!$chkOk) {
                $client->appendValues('Maintenance_Checklists!A:H', $chkRows);
            }
        }

        // 3. Jika status Selesai, selesaikan temuan terbuka untuk aset ini di Maintenance_Findings
        if ($status === 'Selesai') {
            try {
                $fRows = $client->getSheetData('Maintenance_Findings', true);
                foreach ($fRows as $fr) {
                    $frAid = (int)($fr['asset_id'] ?? $fr['id_asset'] ?? $fr['col_2'] ?? 0);
                    if ($frAid === $assetId) {
                        $frStatus = strtolower(trim((string)($fr['status'] ?? $fr['repair_status'] ?? $fr['col_6'] ?? '')));
                        if (!in_array($frStatus, ['resolved', 'closed', 'selesai', 'ok', 'done'], true)) {
                            $rowNum = (int)($fr['_row_num'] ?? 0);
                            if ($rowNum > 1) {
                                $client->updateValues("Maintenance_Findings!G{$rowNum}:L{$rowNum}", [[
                                    'Resolved',
                                    $fr['reported_by'] ?? 'Teknisi',
                                    $fr['reported_at'] ?? date('Y-m-d H:i:s'),
                                    $techName,
                                    date('Y-m-d H:i:s'),
                                    'Diselesaikan melalui maintenance rutin'
                                ]]);
                            }
                        }
                    }
                }
            } catch (Throwable $e) {}
        }

        // 4. Jika ada temuan / kerusakan, catat di Maintenance_Findings
        if ($findings !== '' || $status === 'Perlu Perbaikan' || $status === 'Proses' || $status === 'Temuan') {
            $rawFindCol = $client->getValues('Maintenance_Findings!A:A');
            $newFindId = max(1, count($rawFindCol) + 1);
            if (empty($rawFindCol)) {
                $client->createSheetIfNotExists('Maintenance_Findings');
            }
            $client->appendValues('Maintenance_Findings', [[
                $newFindId,
                $activeScanId,
                $assetId,
                'Maintenance Temuan',
                $findings,
                $recommendation,
                $status,
                $techName,
                date('Y-m-d H:i:s'),
                '', '', ''
            ]]);
        }

        $client->clearCache('Maintenance_Scan');
        $client->clearCache('Maintenance_Checklists');
        $client->clearCache('Maintenance_Findings');
        $client->clearCache();

        // Simpan ke sesi agar tampilan kartu langsung terupdate 100% instan
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_recent_scan_' . $assetId] = [
                'id' => $activeScanId,
                'asset_id' => $assetId,
                'technician_name' => $techName,
                'maintenance_date' => $date,
                'maintenance_time' => $time,
                'maintenance_month' => $month,
                'maintenance_year' => $year,
                'status' => $status,
                'checklists' => $checklists
            ];
        }

        return ['success' => true, 'log_id' => $activeScanId];
    }

    // MySQL Mode
    try {
        db()->exec("
            CREATE TABLE IF NOT EXISTS maintenance_scan (
                id INT AUTO_INCREMENT PRIMARY KEY,
                asset_id INT NOT NULL,
                technician_user_id INT NULL,
                technician_name VARCHAR(150) NULL,
                maintenance_date DATE NOT NULL,
                maintenance_time TIME NOT NULL,
                maintenance_month TINYINT NOT NULL,
                maintenance_year SMALLINT NOT NULL,
                status VARCHAR(50) DEFAULT 'Selesai',
                source VARCHAR(50) DEFAULT 'Maintenance',
                findings TEXT NULL,
                recommendation TEXT NULL,
                biometric_verified TINYINT(1) DEFAULT 0,
                biometric_confidence FLOAT NULL,
                biometric_photo MEDIUMTEXT NULL,
                latitude VARCHAR(50) NULL,
                longitude VARCHAR(50) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_maintenance_asset_period (asset_id, maintenance_month, maintenance_year)
            );
            CREATE TABLE IF NOT EXISTS maintenance_checklists (
                id INT AUTO_INCREMENT PRIMARY KEY,
                maintenance_id INT NOT NULL,
                asset_id INT NOT NULL,
                checklist_number TINYINT NOT NULL,
                checklist_name VARCHAR(150) NOT NULL,
                checked TINYINT(1) DEFAULT 0,
                notes VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $scanCols = table_columns('maintenance_scan');
        $alterQueries = [
            'technician_user_id' => "ALTER TABLE maintenance_scan MODIFY COLUMN technician_user_id INT NULL",
            'technician_name' => "ALTER TABLE maintenance_scan ADD COLUMN technician_name VARCHAR(150) NULL AFTER technician_user_id",
            'findings' => "ALTER TABLE maintenance_scan ADD COLUMN findings TEXT NULL",
            'recommendation' => "ALTER TABLE maintenance_scan ADD COLUMN recommendation TEXT NULL",
            'biometric_verified' => "ALTER TABLE maintenance_scan ADD COLUMN biometric_verified TINYINT(1) DEFAULT 0",
            'biometric_confidence' => "ALTER TABLE maintenance_scan ADD COLUMN biometric_confidence FLOAT NULL",
            'biometric_photo' => "ALTER TABLE maintenance_scan ADD COLUMN biometric_photo MEDIUMTEXT NULL",
            'latitude' => "ALTER TABLE maintenance_scan ADD COLUMN latitude VARCHAR(50) NULL",
            'longitude' => "ALTER TABLE maintenance_scan ADD COLUMN longitude VARCHAR(50) NULL",
            'source' => "ALTER TABLE maintenance_scan ADD COLUMN source VARCHAR(50) DEFAULT 'Maintenance'",
        ];
        foreach ($alterQueries as $col => $sql) {
            if (!in_array($col, $scanCols, true)) {
                try { db()->exec($sql); } catch (Throwable $e) {}
            }
        }
        try {
            db()->exec("ALTER TABLE maintenance_scan MODIFY COLUMN `status` VARCHAR(50) DEFAULT 'Selesai'");
            db()->exec("ALTER TABLE maintenance_scan MODIFY COLUMN `technician_user_id` INT NULL");
        } catch (Throwable $e) {}

        $ins = db()->prepare("
            INSERT INTO maintenance_scan
            (asset_id, technician_user_id, technician_name, maintenance_date, maintenance_time, maintenance_month, maintenance_year, status, source, findings, recommendation, biometric_verified, biometric_confidence, biometric_photo, latitude, longitude, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            technician_user_id = VALUES(technician_user_id),
            technician_name = VALUES(technician_name),
            maintenance_date = VALUES(maintenance_date),
            maintenance_time = VALUES(maintenance_time),
            status = VALUES(status),
            source = VALUES(source),
            findings = VALUES(findings),
            recommendation = VALUES(recommendation),
            biometric_verified = VALUES(biometric_verified),
            biometric_confidence = VALUES(biometric_confidence),
            biometric_photo = VALUES(biometric_photo),
            latitude = VALUES(latitude),
            longitude = VALUES(longitude)
        ");
        $ins->execute([$assetId, $userId, $techName, $date, $time, $month, $year, $status, $mType, $findings, $recommendation, $bioVerified, $bioConfidence, $bioPhoto, $latitude, $longitude]);

        $logId = (int)db()->lastInsertId();
        if ($logId <= 0) {
            $stId = db()->prepare("SELECT id FROM maintenance_scan WHERE asset_id = ? AND maintenance_month = ? AND maintenance_year = ? ORDER BY id DESC LIMIT 1");
            $stId->execute([$assetId, $month, $year]);
            $logId = (int)$stId->fetchColumn();
        }

        // Bersihkan checklist lama untuk log_id ini agar tidak terduplikasi saat update
        if ($logId > 0) {
            try {
                $delChk = db()->prepare("DELETE FROM maintenance_checklists WHERE maintenance_id = ?");
                $delChk->execute([$logId]);
            } catch (Throwable $e) {}
        }

        $chkSt = db()->prepare("
            INSERT INTO maintenance_checklists
            (maintenance_id, asset_id, checklist_number, checklist_name, checked, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        foreach ($fixedItems as $num => $name) {
            $chkItem = $checklists[$num] ?? [];
            $checked = !empty($chkItem['checked']) ? 1 : 0;
            $notes = trim((string)($chkItem['notes'] ?? ($checked ? ($defaultNotes[$num] ?? 'Normal') : '')));
            $chkSt->execute([$logId, $assetId, $num, $name, $checked, $notes]);
        }

        if ($status === 'Selesai') {
            try {
                $stFind = db()->prepare("
                    UPDATE maintenance_findings 
                    SET repair_status = 'Resolved', resolved_by = ?, resolved_at = NOW(), action_taken = 'Diselesaikan melalui maintenance rutin'
                    WHERE asset_id = ? AND LOWER(COALESCE(repair_status, 'open')) NOT IN ('resolved', 'closed', 'selesai', 'done')
                ");
                $stFind->execute([$techName, $assetId]);
            } catch (Throwable $e) {}
        }

        return ['success' => true, 'log_id' => $logId];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function get_maintenance_detail(int $logId): ?array {
    if ($logId <= 0) return null;
    $fixedItems = get_fixed_checklists();
    $defaultNotes = get_fixed_checklist_default_notes();

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return null;

        $scans = $client->getSheetData('Maintenance_Scan');
        $targetScan = null;
        foreach ($scans as $s) {
            if ((int)($s['id'] ?? 0) === $logId) {
                $targetScan = $s;
                break;
            }
        }
        if (!$targetScan) return null;

        $assetId = (int)($targetScan['asset_id'] ?? 0);
        $asset = get_asset_by_id($assetId);

        // Checklist items
        $chkRows = $client->getSheetData('Maintenance_Checklists');
        $checklists = [];
        foreach ($fixedItems as $num => $name) {
            $checklists[$num] = [
                'number' => $num,
                'name' => $name,
                'checked' => 0,
                'notes' => ''
            ];
        }

        $hasMatchingChecklistRows = false;
        foreach ($chkRows as $c) {
            $mid = (int)($c['maintenance_id'] ?? $c['maintenance_scan_id'] ?? $c['id_maintenance'] ?? $c['log_id'] ?? $c['scan_id'] ?? $c['col_1'] ?? 0);
            if ($mid === $logId) {
                $cnum = (int)($c['checklist_number'] ?? $c['number'] ?? $c['item_number'] ?? $c['no'] ?? $c['checklist_id'] ?? $c['col_3'] ?? 0);
                if (isset($checklists[$cnum])) {
                    $hasMatchingChecklistRows = true;
                    $isCh = strtolower(trim((string)($c['checked'] ?? $c['status'] ?? $c['is_checked'] ?? $c['col_5'] ?? '0')));
                    $isDone = in_array($isCh, ['1', 'true', 'yes', 'v', '✓', 'ok', 'selesai', 'normal', 'checked'], true);
                    $checklists[$cnum]['checked'] = $isDone ? 1 : 0;
                    $checklists[$cnum]['notes'] = (string)($c['notes'] ?? $c['keterangan'] ?? $c['catatan'] ?? $c['col_6'] ?? '');
                }
            }
        }

        // Fallback cerdas: HANYA jika memang belum ada baris terpisah sama sekali di tab Maintenance_Checklists
        // (misal log lama / scan cepat tanpa checklist)
        if (!$hasMatchingChecklistRows) {
            $scanStatus = trim((string)($targetScan['status'] ?? 'Selesai'));
            $isCompleted = ($scanStatus === 'Selesai' || $scanStatus === 'Normal' || $scanStatus === 'OK' || $scanStatus === '');
            foreach ($fixedItems as $num => $name) {
                $checklists[$num]['checked'] = $isCompleted ? 1 : 0;
                $checklists[$num]['notes'] = $isCompleted ? ($defaultNotes[$num] ?? 'Normal') : ($targetScan['findings'] ?? '-');
            }
        }

        return [
            'scan' => $targetScan,
            'asset' => $asset,
            'checklists' => $checklists
        ];
    }

    // MySQL Mode
    try {
        $st = db()->prepare("
            SELECT ms.*, COALESCE(ms.technician_name, u.nama, 'Teknisi') AS technician_name
            FROM maintenance_scan ms
            LEFT JOIN users u ON u.id = ms.technician_user_id
            WHERE ms.id = ? LIMIT 1
        ");
        $st->execute([$logId]);
        $scan = $st->fetch();
        if (!$scan) return null;

        $asset = get_asset_by_id((int)$scan['asset_id']);

        $chkSt = db()->prepare("SELECT * FROM maintenance_checklists WHERE maintenance_id = ? ORDER BY checklist_number ASC");
        $chkSt->execute([$logId]);
        $chkRows = $chkSt->fetchAll();

        $checklists = [];
        foreach ($fixedItems as $num => $name) {
            $checklists[$num] = [
                'number' => $num,
                'name' => $name,
                'checked' => 0,
                'notes' => ''
            ];
        }

        $hasMatchingChecklistRows = false;
        foreach ($chkRows as $c) {
            $cnum = (int)($c['checklist_number'] ?? 0);
            if (isset($checklists[$cnum])) {
                $hasMatchingChecklistRows = true;
                $checklists[$cnum]['checked'] = (int)($c['checked'] ?? 0);
                $checklists[$cnum]['notes'] = (string)($c['notes'] ?? '');
            }
        }

        // Fallback cerdas untuk database yang belum memiliki detail checklist
        if (!$hasMatchingChecklistRows) {
            $scanStatus = trim((string)($scan['status'] ?? 'Selesai'));
            $isCompleted = ($scanStatus === 'Selesai' || $scanStatus === 'Normal' || $scanStatus === 'OK' || $scanStatus === '');
            foreach ($fixedItems as $num => $name) {
                $checklists[$num]['checked'] = $isCompleted ? 1 : 0;
                $checklists[$num]['notes'] = $isCompleted ? ($defaultNotes[$num] ?? 'Normal') : ($scan['findings'] ?? '-');
            }
        }

        return [
            'scan' => $scan,
            'asset' => $asset,
            'checklists' => $checklists
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function update_maintenance_detail(int $logId, array $data): array {
    if ($logId <= 0) return ['success' => false, 'error' => 'ID log tidak valid'];

    $status = trim((string)($data['status'] ?? 'Selesai'));
    if (!in_array($status, ['Selesai', 'Proses', 'Perlu Perbaikan', 'Temuan'], true)) {
        $status = 'Selesai';
    }
    $findings = trim((string)($data['findings'] ?? ''));
    $recommendation = trim((string)($data['recommendation'] ?? ''));
    $checklists = (array)($data['checklists'] ?? []);
    $fixedItems = get_fixed_checklists();
    $defaultNotes = get_fixed_checklist_default_notes();

    $newDate = !empty($data['maintenance_date']) ? substr(trim((string)$data['maintenance_date']), 0, 10) : '';
    $newTime = !empty($data['maintenance_time']) ? substr(trim((string)$data['maintenance_time']), 0, 8) : '';
    $newTechName = isset($data['technician_name']) ? trim((string)$data['technician_name']) : '';

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];

        // 1. Update Maintenance_Scan
        $scans = $client->getSheetData('Maintenance_Scan', true);
        $targetScan = null;
        $scanRowNum = 0;
        foreach ($scans as $s) {
            if ((int)($s['id'] ?? 0) === $logId) {
                $targetScan = $s;
                $scanRowNum = (int)($s['_row_num'] ?? 0);
                break;
            }
        }
        if (!$targetScan || $scanRowNum <= 1) {
            return ['success' => false, 'error' => 'Data maintenance scan tidak ditemukan'];
        }

        $assetId = (int)($targetScan['asset_id'] ?? 0);
        $userId = (int)($targetScan['technician_user_id'] ?? 0);
        $techName = $newTechName !== '' ? $newTechName : (string)($targetScan['technician_name'] ?? '');
        $date = (string)($targetScan['maintenance_date'] ?? date('Y-m-d'));
        $time = $newTime !== '' ? $newTime : (string)($targetScan['maintenance_time'] ?? date('H:i:s'));
        $month = (int)($targetScan['maintenance_month'] ?? date('n'));
        $year = (int)($targetScan['maintenance_year'] ?? date('Y'));
        $mType = (string)($targetScan['source'] ?? $targetScan['maintenance_type'] ?? 'Maintenance');

        if ($newDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
            $date = $newDate;
            $month = (int)date('n', strtotime($newDate));
            $year = (int)date('Y', strtotime($newDate));
        }

        $bioVerified = isset($data['biometric_verified']) ? (int)$data['biometric_verified'] : (int)($targetScan['biometric_verified'] ?? 0);
        $bioConfidence = isset($data['biometric_confidence']) ? (float)$data['biometric_confidence'] : (float)($targetScan['biometric_confidence'] ?? 0);
        $bioPhoto = !empty($data['biometric_photo']) ? (string)$data['biometric_photo'] : (string)($targetScan['biometric_photo'] ?? '');
        $latitude = !empty($data['latitude']) ? (string)$data['latitude'] : (string)($targetScan['latitude'] ?? '');
        $longitude = !empty($data['longitude']) ? (string)$data['longitude'] : (string)($targetScan['longitude'] ?? '');

        $client->updateValues("Maintenance_Scan!A{$scanRowNum}:R{$scanRowNum}", [[
            $logId,
            $assetId,
            $userId,
            $techName,
            $date,
            $time,
            $month,
            $year,
            $status,
            $mType,
            $targetScan['created_at'] ?? date('Y-m-d H:i:s'),
            $findings,
            $recommendation,
            $bioVerified,
            $bioConfidence,
            $bioPhoto,
            $latitude,
            $longitude
        ]]);

        // 2. Update atau Tambah Checklist di Maintenance_Checklists
        $client->createSheetIfNotExists('Maintenance_Checklists');
        $existingHeader = $client->getValues('Maintenance_Checklists!A1:H1');
        if (empty($existingHeader)) {
            $client->appendValues('Maintenance_Checklists!A:H', [
                ['id', 'maintenance_id', 'asset_id', 'checklist_number', 'checklist_name', 'checked', 'notes', 'created_at']
            ]);
        }

        $chkRows = $client->getSheetData('Maintenance_Checklists', true);
        $existingMap = [];
        $maxChkId = 0;
        foreach ($chkRows as $c) {
            $cid = (int)($c['id'] ?? $c['col_0'] ?? 0);
            if ($cid > $maxChkId) $maxChkId = $cid;
            $mid = (int)($c['maintenance_id'] ?? $c['maintenance_scan_id'] ?? $c['id_maintenance'] ?? $c['log_id'] ?? $c['col_1'] ?? 0);
            if ($mid === $logId) {
                $cnum = (int)($c['checklist_number'] ?? $c['number'] ?? $c['item_number'] ?? $c['no'] ?? $c['col_3'] ?? 0);
                if ($cnum > 0) {
                    $existingMap[$cnum] = (int)($c['_row_num'] ?? 0);
                }
            }
        }

        $rowsToAppend = [];
        $nextChkId = $maxChkId + 1;
        foreach ($fixedItems as $num => $name) {
            $chkItem = $checklists[$num] ?? [];
            $checked = !empty($chkItem['checked']) ? 1 : 0;
            $notes = trim((string)($chkItem['notes'] ?? ($checked ? ($defaultNotes[$num] ?? 'Normal') : '')));

            if (!empty($existingMap[$num]) && $existingMap[$num] > 1) {
                $rNum = $existingMap[$num];
                $client->updateValues("Maintenance_Checklists!A{$rNum}:H{$rNum}", [[
                    $rNum - 1,
                    $logId,
                    $assetId,
                    $num,
                    $name,
                    $checked,
                    $notes,
                    date('Y-m-d H:i:s')
                ]]);
            } else {
                $rowsToAppend[] = [
                    $nextChkId++,
                    $logId,
                    $assetId,
                    $num,
                    $name,
                    $checked,
                    $notes,
                    date('Y-m-d H:i:s')
                ];
            }
        }

        if (!empty($rowsToAppend)) {
            $rawChk = $client->getValues('Maintenance_Checklists!A:A');
            $nextRow = max(1, count($rawChk)) + 1;
            $endRow = $nextRow + count($rowsToAppend) - 1;
            $client->updateValues("Maintenance_Checklists!A{$nextRow}:H{$endRow}", $rowsToAppend);
        }

        if ($status === 'Selesai') {
            try {
                $fRows = $client->getSheetData('Maintenance_Findings', true);
                foreach ($fRows as $fr) {
                    $frAid = (int)($fr['asset_id'] ?? $fr['id_asset'] ?? $fr['col_2'] ?? 0);
                    $frMid = (int)($fr['maintenance_scan_id'] ?? $fr['maintenance_id'] ?? $fr['log_id'] ?? 0);
                    if ($frAid === $assetId || ($frMid > 0 && $frMid === $logId)) {
                        $frStatus = strtolower(trim((string)($fr['status'] ?? $fr['repair_status'] ?? $fr['col_6'] ?? '')));
                        if (!in_array($frStatus, ['resolved', 'closed', 'selesai', 'ok', 'done'], true)) {
                            $rowNum = (int)($fr['_row_num'] ?? 0);
                            if ($rowNum > 1) {
                                $client->updateValues("Maintenance_Findings!G{$rowNum}:L{$rowNum}", [[
                                    'Resolved',
                                    $fr['reported_by'] ?? 'Teknisi',
                                    $fr['reported_at'] ?? date('Y-m-d H:i:s'),
                                    $techName,
                                    date('Y-m-d H:i:s'),
                                    'Diselesaikan melalui update maintenance'
                                ]]);
                            }
                        }
                    }
                }
            } catch (Throwable $e) {}
        }

        $client->clearCache();
        return ['success' => true];
    }

    // MySQL Mode
    try {
        // Pastikan kolom baru tersedia
        try {
            $cols = table_columns('maintenance_scan');
            if (!empty($cols) && !in_array('technician_name', $cols, true)) {
                db()->exec("ALTER TABLE maintenance_scan ADD COLUMN technician_name VARCHAR(150) NULL");
            }
            if (!empty($cols) && !in_array('findings', $cols, true)) {
                db()->exec("ALTER TABLE maintenance_scan ADD COLUMN findings TEXT NULL");
            }
            if (!empty($cols) && !in_array('recommendation', $cols, true)) {
                db()->exec("ALTER TABLE maintenance_scan ADD COLUMN recommendation TEXT NULL");
            }
        } catch (Throwable $e) {}

        $updates = ["status = ?", "findings = ?", "recommendation = ?"];
        $params = [$status, $findings, $recommendation];

        if ($newDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
            $month = (int)date('n', strtotime($newDate));
            $year = (int)date('Y', strtotime($newDate));
            $updates[] = "maintenance_date = ?";
            $updates[] = "maintenance_month = ?";
            $updates[] = "maintenance_year = ?";
            $params[] = $newDate;
            $params[] = $month;
            $params[] = $year;
        }
        if ($newTime !== '') {
            $updates[] = "maintenance_time = ?";
            $params[] = $newTime;
        }
        if ($newTechName !== '') {
            $updates[] = "technician_name = ?";
            $params[] = $newTechName;
        }
        if (isset($data['biometric_verified'])) {
            $updates[] = "biometric_verified = ?";
            $params[] = (int)$data['biometric_verified'];
        }
        if (isset($data['biometric_confidence'])) {
            $updates[] = "biometric_confidence = ?";
            $params[] = (float)$data['biometric_confidence'];
        }
        if (!empty($data['biometric_photo'])) {
            $updates[] = "biometric_photo = ?";
            $params[] = (string)$data['biometric_photo'];
        }

        $params[] = $logId;
        $sql = "UPDATE maintenance_scan SET " . implode(', ', $updates) . " WHERE id = ?";
        $st = db()->prepare($sql);
        $st->execute($params);

        // Get asset_id
        $scanSt = db()->prepare("SELECT asset_id FROM maintenance_scan WHERE id = ? LIMIT 1");
        $scanSt->execute([$logId]);
        $assetId = (int)$scanSt->fetchColumn();

        foreach ($fixedItems as $num => $name) {
            $chkItem = $checklists[$num] ?? [];
            $checked = !empty($chkItem['checked']) ? 1 : 0;
            $notes = trim((string)($chkItem['notes'] ?? ($checked ? ($defaultNotes[$num] ?? 'Normal') : '')));

            $chkCheck = db()->prepare("SELECT id FROM maintenance_checklists WHERE maintenance_id = ? AND checklist_number = ? LIMIT 1");
            $chkCheck->execute([$logId, $num]);
            $chkId = (int)$chkCheck->fetchColumn();

            if ($chkId > 0) {
                $up = db()->prepare("UPDATE maintenance_checklists SET checked = ?, notes = ? WHERE id = ?");
                $up->execute([$checked, $notes, $chkId]);
            } else {
                $ins = db()->prepare("
                    INSERT INTO maintenance_checklists (maintenance_id, asset_id, checklist_number, checklist_name, checked, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $ins->execute([$logId, $assetId, $num, $name, $checked, $notes]);
            }
        }

        if ($status === 'Selesai') {
            try {
                $stFind = db()->prepare("
                    UPDATE maintenance_findings 
                    SET repair_status = 'Resolved', resolved_by = ?, resolved_at = NOW(), action_taken = 'Diselesaikan melalui update maintenance'
                    WHERE (asset_id = ? OR maintenance_scan_id = ?) AND LOWER(COALESCE(repair_status, 'open')) NOT IN ('resolved', 'closed', 'selesai', 'done')
                ");
                $stFind->execute([$newTechName ?: 'Teknisi', $assetId, $logId]);
            } catch (Throwable $e) {}
        }

        return ['success' => true];
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'Duplicate entry') !== false || stripos($msg, 'uq_maintenance_asset_period') !== false) {
            $msg = 'Sudah ada data maintenance untuk perangkat ini pada periode bulan/tahun yang dipilih.';
        }
        return ['success' => false, 'error' => $msg];
    }
}

function record_scan(int $assetId, int $userId, string $techName, string $date, string $time, int $month, int $year, bool $force = false): array {
    return save_maintenance_record([
        'asset_id' => $assetId,
        'technician_user_id' => $userId,
        'technician_name' => $techName,
        'maintenance_date' => $date,
        'maintenance_time' => $time,
        'status' => 'Selesai',
        'maintenance_type' => $force ? 'Maintenance Ulang' : 'Maintenance'
    ]);
}

function get_log_by_id(int $logId): ?array {
    if (is_google_cloud_mode()) {
        $history = get_history_rows(0, 0, 0);
        foreach ($history as $h) {
            if ((int)($h['id'] ?? 0) === $logId) return $h;
        }
        return null;
    }
    $st = db()->prepare("
        SELECT ms.*, a.kode_inventaris, a.merk, a.model
        FROM maintenance_scan ms
        JOIN assets a ON a.id = ms.asset_id
        WHERE ms.id = ?
        LIMIT 1
    ");
    $st->execute([$logId]);
    $log = $st->fetch();
    return $log ?: null;
}

function record_finding_issue(int $logId, int $assetId, string $finding, string $action, string $severity, string $reporter): array {
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return ['success' => false];

        $findings = $client->getSheetData('Maintenance_Findings');
        $newId = count($findings) + 1;
        $newRow = [
            $newId,
            $logId,
            $assetId,
            $severity,
            $finding,
            $action,
            'Open',
            $reporter,
            date('Y-m-d H:i:s'),
            '',
            '',
            ''
        ];
        $client->appendValues('Maintenance_Findings!A:L', [$newRow]);

        // Update scan status to 'Temuan'
        $scans = $client->getSheetData('Maintenance_Scan');
        foreach ($scans as $s) {
            if ((int)($s['id'] ?? 0) === $logId) {
                $rowNum = (int)($s['_row_num'] ?? 0);
                if ($rowNum > 1) {
                    $client->updateValues("Maintenance_Scan!I{$rowNum}", [['Temuan']]);
                }
                break;
            }
        }
        return ['success' => true, 'finding_id' => $newId];
    }
    db()->beginTransaction();
    try {
        $ins = db()->prepare("
            INSERT INTO maintenance_findings
            (maintenance_scan_id, asset_id, finding, action_taken, severity, repair_status, created_by)
            VALUES (?, ?, ?, ?, ?, 'Perlu Tindak Lanjut', ?)
        ");
        $ins->execute([$logId, $assetId, $finding, $action ?: null, $severity, current_user_id()]);

        $up = db()->prepare("UPDATE maintenance_scan SET status = 'Temuan' WHERE id = ?");
        $up->execute([$logId]);

        db()->commit();
        return ['success' => true];
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

function get_findings_report(int $month, int $year, int $cabangId): array {
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return [];
        $findings = $client->getSheetData('Maintenance_Findings');
        $scans = $client->getSheetData('Maintenance_Scan');
        $scanMap = []; foreach ($scans as $s) $scanMap[$s['id'] ?? 0] = $s;
        $assets = map_sheets_assets();
        $assetMap = []; foreach ($assets as $a) $assetMap[$a['id'] ?? 0] = $a;

        $results = [];
        foreach ($findings as $f) {
            $scanId = (int)($f['maintenance_scan_id'] ?? 0);
            $scan = $scanMap[$scanId] ?? [];
            if ($month > 0 && (int)($scan['maintenance_month'] ?? 0) !== $month) continue;
            if ($year > 0 && (int)($scan['maintenance_year'] ?? 0) !== $year) continue;

            $aid = (int)($f['asset_id'] ?? $scan['asset_id'] ?? 0);
            $a = $assetMap[$aid] ?? [];
            if ($cabangId > 0 && (int)($a['id_cabang'] ?? 0) !== $cabangId) continue;

            $results[] = [
                'id' => $f['id'] ?? 0,
                'kode_inventaris' => $a['kode_inventaris'] ?? '-',
                'merk_model' => trim(($a['merk'] ?? '').' '.($a['model'] ?? '')),
                'karyawan_nama' => $a['karyawan_nama'] ?? '-',
                'cabang_nama' => $a['cabang_nama'] ?? '-',
                'finding' => $f['finding'] ?? $f['deskripsi_temuan'] ?? '-',
                'action_taken' => $f['action_taken'] ?? $f['tindakan_diperlukan'] ?? '-',
                'severity' => $f['severity'] ?? $f['kategori_temuan'] ?? 'Ringan',
                'repair_status' => $f['repair_status'] ?? $f['status'] ?? 'Open',
                'created_at' => substr((string)($f['created_at'] ?? $f['reported_at'] ?? ''), 0, 10),
            ];
        }
        return $results;
    }

    // MySQL Mode
    try {
        $cName = name_column('cabang') ?: 'id';
        $kName = name_column('karyawan') ?: 'id';
        $sql = "
            SELECT mf.*, a.kode_inventaris, a.merk, a.model,
                   c.`{$cName}` AS cabang_nama,
                   k.`{$kName}` AS karyawan_nama
            FROM maintenance_findings mf
            JOIN maintenance_scan ms ON ms.id = mf.maintenance_scan_id
            JOIN assets a ON a.id = mf.asset_id
            LEFT JOIN cabang c ON c.id = a.id_cabang
            LEFT JOIN karyawan k ON k.id = a.id_karyawan
            WHERE ms.maintenance_month = ? AND ms.maintenance_year = ?
        ";
        $params = [$month, $year];
        if ($cabangId > 0) {
            $sql .= " AND a.id_cabang = ? ";
            $params[] = $cabangId;
        }
        $sql .= " ORDER BY mf.id DESC";
        $st = db()->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        return array_map(function($r) {
            return [
                'id' => $r['id'],
                'kode_inventaris' => $r['kode_inventaris'] ?? '-',
                'merk_model' => trim(($r['merk'] ?? '').' '.($r['model'] ?? '')),
                'karyawan_nama' => $r['karyawan_nama'] ?? '-',
                'cabang_nama' => $r['cabang_nama'] ?? '-',
                'finding' => $r['finding'] ?? '-',
                'action_taken' => $r['action_taken'] ?? '-',
                'severity' => $r['severity'] ?? 'Ringan',
                'repair_status' => $r['repair_status'] ?? 'Perlu Tindak Lanjut',
                'created_at' => substr((string)($r['created_at'] ?? ''), 0, 10),
            ];
        }, $rows);
    } catch (Throwable $e) {
        return [];
    }
}

function get_history_rows(int $month, int $year, int $cabangId, string $status = ''): array {
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return [];

        $scans = $client->getSheetData('Maintenance_Scan');
        $assetMap = []; foreach (map_sheets_assets() as $a) $assetMap[$a['id']] = $a;

        $rows = [];
        foreach ($scans as $s) {
            if ($month > 0 && (int)($s['maintenance_month'] ?? 0) !== $month) continue;
            if ($year > 0 && (int)($s['maintenance_year'] ?? 0) !== $year) continue;
            if ($status !== '' && ($s['status'] ?? '') !== $status) continue;

            $a = $assetMap[(int)($s['asset_id'] ?? 0)] ?? [];
            if ($cabangId > 0 && ($a['id_cabang'] ?? 0) !== $cabangId) continue;

            $rows[] = [
                'id' => (int)($s['id'] ?? 0),
                'asset_id' => (int)($s['asset_id'] ?? 0),
                'maintenance_date' => substr((string)($s['maintenance_date'] ?? ''), 0, 10),
                'maintenance_time' => substr((string)($s['maintenance_time'] ?? ''), 0, 8),
                'status' => $s['status'] ?? 'Selesai',
                'technician_name' => $s['technician_name'] ?? 'Teknisi',
                'maintenance_type' => $s['source'] ?? $s['maintenance_type'] ?? 'Maintenance',
                'findings' => $s['findings'] ?? '',
                'recommendation' => $s['recommendation'] ?? '',
                'kode_inventaris' => $a['kode_inventaris'] ?? '-',
                'serial_number' => $a['serial_number'] ?? '-',
                'merk' => $a['merk'] ?? '',
                'model' => $a['model'] ?? '',
                'kategori_nama' => $a['kategori_nama'] ?? '-',
                'karyawan_nama' => $a['karyawan_nama'] ?? '-',
                'cabang_nama' => $a['cabang_nama'] ?? '-'
            ];
        }
        usort($rows, fn($a, $b) => strcmp($b['maintenance_date'], $a['maintenance_date']));
        return array_values($rows);
    }

    $cName = name_column('cabang') ?: 'id';
    $kName = name_column('karyawan') ?: 'id';
    $uName = name_column('users') ?: 'id';

    $sql = "
    SELECT ms.*, a.kode_inventaris, a.serial_number, a.merk, a.model,
           c.`{$cName}` AS cabang_nama,
           k.`{$kName}` AS karyawan_nama,
           COALESCE(ms.technician_name, u.`{$uName}`, 'Teknisi') AS technician_name
    FROM maintenance_scan ms
    JOIN assets a ON a.id = ms.asset_id
    LEFT JOIN cabang c ON c.id = a.id_cabang
    LEFT JOIN karyawan k ON k.id = a.id_karyawan
    LEFT JOIN users u ON u.id = ms.technician_user_id
    WHERE 1=1
    ";
    $params = [];
    if ($month > 0) { $sql .= " AND ms.maintenance_month = ? "; $params[] = $month; }
    if ($year > 0) { $sql .= " AND ms.maintenance_year = ? "; $params[] = $year; }
    if ($cabangId > 0) { $sql .= " AND a.id_cabang = ? "; $params[] = $cabangId; }
    if ($status !== '') { $sql .= " AND ms.status = ? "; $params[] = $status; }

    $sql .= " ORDER BY ms.maintenance_date DESC, ms.id DESC LIMIT 1000";

    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

