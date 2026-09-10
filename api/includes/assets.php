<?php
function asset_query_base(): string {
    $cCab = name_column('cabang') ?: 'id';
    $cDiv = name_column('divisi') ?: 'id';
    $cKar = name_column('karyawan') ?: 'id';
    $cKat = name_column('kategori_aset') ?: 'id';

    return "
        SELECT
            a.*,
            c.`{$cCab}` AS cabang_nama,
            d.`{$cDiv}` AS divisi_nama,
            k.`{$cKar}` AS karyawan_nama,
            ka.`{$cKat}` AS kategori_nama,
            q.token AS qr_token,
            q.placement_label,
            q.is_active AS qr_active
        FROM assets a
        LEFT JOIN cabang c ON c.id = a.id_cabang
        LEFT JOIN divisi d ON d.id = a.id_divisi
        LEFT JOIN karyawan k ON k.id = a.id_karyawan
        LEFT JOIN kategori_aset ka ON ka.id = a.id_kategori
        LEFT JOIN asset_qr_tokens q ON q.asset_id = a.id
    ";
}

function asset_title(array $a): string {
    $parts = [];
    if (!empty($a['kategori_nama'])) $parts[] = $a['kategori_nama'];
    if (!empty($a['merk'])) $parts[] = $a['merk'];
    if (!empty($a['model'])) $parts[] = $a['model'];
    $title = trim(implode(' ', $parts));
    if ($title === '') $title = 'Aset #' . ($a['id'] ?? '-');
    return $title;
}


function get_static_qr_token(int $assetId): string {
    return substr(hash('sha256', 'STATIC_QR_MAINTENANCE_KEY_SALT_' . $assetId), 0, 32);
}

function map_sheets_assets(bool $refresh = false): array {
    static $cachedAssets = null;
    if ($cachedAssets !== null && !$refresh) {
        return $cachedAssets;
    }

    $client = google_sheets_v4_client();
    if (!$client) return [];

    $assets = $client->getSheetData('Assets', $refresh);
    // Filter baris kosong / yang sudah dihapus
    $assets = array_values(array_filter($assets, function($a) {
        return !empty($a['id']) && (int)$a['id'] > 0;
    }));
    $cabangRows = $client->getSheetData('Cabang', $refresh);
    $divRows = $client->getSheetData('Divisi', $refresh);
    $karRows = $client->getSheetData('Karyawan', $refresh);
    $katRows = $client->getSheetData('Kategori_Aset', $refresh);
    $qrRows = $client->getSheetData('Asset_QR_Tokens', $refresh);

    $cabangMap = []; foreach ($cabangRows as $r) $cabangMap[$r['id'] ?? 0] = $r['nama_cabang'] ?? $r['nama'] ?? '';
    $divMap = []; foreach ($divRows as $r) $divMap[$r['id'] ?? 0] = $r['nama_divisi'] ?? $r['nama'] ?? '';
    $karMap = []; foreach ($karRows as $r) $karMap[$r['id'] ?? 0] = $r['nama_karyawan'] ?? $r['nama'] ?? '';
    $katMap = []; foreach ($katRows as $r) $katMap[$r['id'] ?? 0] = $r['nama_kategori'] ?? $r['nama'] ?? '';
    $qrMap = []; foreach ($qrRows as $r) { $aid = (int)($r['asset_id'] ?? 0); if ($aid > 0) $qrMap[$aid] = $r; }

    $cachedAssets = array_map(function($a) use ($cabangMap, $divMap, $karMap, $katMap, $qrMap) {
        $id = (int)($a['id'] ?? 0);
        $qr = $qrMap[$id] ?? [];
        $isAct = strtolower(trim((string)($qr['is_active'] ?? '1')));
        $qrActive = ($isAct === '0' || $isAct === 'false' || $isAct === 'off') ? 0 : 1;
        $token = trim((string)($qr['token'] ?? ''));
        if ($token === '' && $id > 0) {
            $token = get_static_qr_token($id);
        }
        return [
            'id' => $id,
            'kode_inventaris' => $a['kode_inventaris'] ?? '',
            'merk' => $a['merk'] ?? '',
            'model' => $a['model'] ?? '',
            'serial_number' => $a['serial_number'] ?? '',
            'id_kategori' => (int)($a['id_kategori'] ?? 0),
            'id_cabang' => (int)($a['id_cabang'] ?? 0),
            'id_divisi' => (int)($a['id_divisi'] ?? 0),
            'id_karyawan' => (int)($a['id_karyawan'] ?? 0),
            'status' => $a['status'] ?? 'Aktif',
            'keterangan' => $a['keterangan'] ?? '',
            'ip_address' => $a['ip_address'] ?? $a['ip'] ?? '',
            'printer' => $a['printer'] ?? '',
            'cabang_nama' => $cabangMap[$a['id_cabang'] ?? 0] ?? '-',
            'divisi_nama' => $divMap[$a['id_divisi'] ?? 0] ?? '-',
            'karyawan_nama' => $karMap[$a['id_karyawan'] ?? 0] ?? '-',
            'kategori_nama' => $katMap[$a['id_kategori'] ?? 0] ?? '-',
            'qr_token' => $token,
            'placement_label' => $qr['placement_label'] ?? 'Bodi Top',
            'qr_active' => $qrActive
        ];
    }, $assets);

    return $cachedAssets;
}


function create_new_asset(array $data): array {
    $kode = trim((string)($data['kode_inventaris'] ?? ''));
    $merk = trim((string)($data['merk'] ?? ''));
    $model = trim((string)($data['model'] ?? ''));
    $sn = trim((string)($data['serial_number'] ?? ''));
    $idKat = (int)($data['id_kategori'] ?? 0);
    $idCab = (int)($data['id_cabang'] ?? 0);
    $idDiv = (int)($data['id_divisi'] ?? 0);
    $idKar = (int)($data['id_karyawan'] ?? 0);
    $namaKar = trim((string)($data['nama_karyawan'] ?? $data['custom_karyawan'] ?? ''));
    $status = trim((string)($data['status'] ?? 'Aktif')) ?: 'Aktif';
    $ket = trim((string)($data['keterangan'] ?? ''));
    $ip = trim((string)($data['ip_address'] ?? $data['ip'] ?? ''));
    $printer = trim((string)($data['printer'] ?? ''));
    $placement = trim((string)($data['placement_label'] ?? 'Bodi Casing')) ?: 'Bodi Casing';

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) {
            return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];
        }

        // Cari atau buat karyawan jika nama diisi
        if ($namaKar !== '') {
            $karRows = $client->getSheetData('Karyawan');
            $foundKarId = 0;
            foreach ($karRows as $kr) {
                $kName = trim((string)($kr['nama_karyawan'] ?? $kr['nama'] ?? ''));
                if (strcasecmp($kName, $namaKar) === 0) {
                    $foundKarId = (int)($kr['id'] ?? 0);
                    break;
                }
            }
            if ($foundKarId > 0) {
                $idKar = $foundKarId;
            } else {
                $newKarId = count($karRows) + 1;
                $client->appendValues('Karyawan!A:D', [[$newKarId, $namaKar, $idCab, $idDiv]]);
                $idKar = $newKarId;
            }
        }

        $assets = $client->getSheetData('Assets');
        $maxId = 0;
        foreach ($assets as $a) {
            $aid = (int)($a['id'] ?? 0);
            if ($aid > $maxId) $maxId = $aid;
        }
        $newAssetId = max(count($assets) + 1, $maxId + 1);

        if ($kode === '') {
            $kode = sprintf('INV-IT-%03d', $newAssetId);
        }

        $assetRow = [
            $newAssetId,
            $kode,
            $merk,
            $model,
            $sn,
            $idKat,
            $idCab,
            $idDiv,
            $idKar,
            $status,
            $ket,
            $ip,
            $printer
        ];

        $appended = $client->appendValues('Assets!A:M', [$assetRow]);
        if (!$appended) {
            return ['success' => false, 'error' => 'Gagal menyimpan data ke tab Assets'];
        }

        // Generate Token QR
        $qrRows = $client->getSheetData('Asset_QR_Tokens');
        $nextQrId = count($qrRows) + 1;
        $token = bin2hex(random_bytes(16));

        $client->appendValues('Asset_QR_Tokens!A:F', [[
            $nextQrId,
            $newAssetId,
            $token,
            $placement,
            1,
            date('Y-m-d H:i:s')
        ]]);

        return [
            'success' => true,
            'asset_id' => $newAssetId,
            'kode_inventaris' => $kode,
            'qr_token' => $token,
            'karyawan_nama' => $namaKar
        ];
    }

    // MySQL Mode
    try {
        if ($namaKar !== '') {
            $kName = name_column('karyawan') ?: 'nama_karyawan';
            $findSt = db()->prepare("SELECT id FROM karyawan WHERE LOWER(`{$kName}`) = LOWER(?) LIMIT 1");
            $findSt->execute([$namaKar]);
            $existingKarId = (int)$findSt->fetchColumn();
            if ($existingKarId > 0) {
                $idKar = $existingKarId;
            } else {
                $insKar = db()->prepare("INSERT INTO karyawan (`{$kName}`, id_cabang, id_divisi) VALUES (?, ?, ?)");
                $insKar->execute([$namaKar, $idCab, $idDiv]);
                $idKar = (int)db()->lastInsertId();
            }
        }

        if ($kode === '') {
            $countSt = db()->query("SELECT MAX(id) FROM assets");
            $nextVal = ((int)$countSt->fetchColumn()) + 1;
            $kode = sprintf('INV-IT-%03d', $nextVal);
        }

        $cols = table_columns('assets');
        if (!in_array('ip_address', $cols, true)) {
            try { db()->exec("ALTER TABLE assets ADD COLUMN ip_address VARCHAR(45) NULL, ADD COLUMN printer VARCHAR(100) NULL"); } catch (Throwable $e) {}
            $cols = table_columns('assets');
        }
        $hasIp = in_array('ip_address', $cols, true);

        if ($hasIp) {
            $ins = db()->prepare("
                INSERT INTO assets
                (kode_inventaris, merk, model, serial_number, id_kategori, id_cabang, id_divisi, id_karyawan, status, keterangan, ip_address, printer)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([
                $kode, $merk, $model, $sn, $idKat, $idCab, $idDiv, $idKar, $status, $ket, $ip, $printer
            ]);
        } else {
            $ins = db()->prepare("
                INSERT INTO assets
                (kode_inventaris, merk, model, serial_number, id_kategori, id_cabang, id_divisi, id_karyawan, status, keterangan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([
                $kode, $merk, $model, $sn, $idKat, $idCab, $idDiv, $idKar, $status, $ket
            ]);
        }
        $assetId = (int)db()->lastInsertId();

        $token = bin2hex(random_bytes(16));
        $insQr = db()->prepare("
            INSERT INTO asset_qr_tokens (asset_id, token, placement_label, is_active)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE token = VALUES(token), placement_label = VALUES(placement_label), is_active = 1
        ");
        $insQr->execute([$assetId, $token, $placement]);

        return [
            'success' => true,
            'asset_id' => $assetId,
            'kode_inventaris' => $kode,
            'qr_token' => $token,
            'karyawan_nama' => $namaKar
        ];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function get_asset_by_id(int $id): ?array {
    if ($id <= 0) return null;
    if (is_google_cloud_mode()) {
        $assets = map_sheets_assets(true);
        foreach ($assets as $a) {
            if ((int)($a['id'] ?? 0) === $id) {
                return $a;
            }
        }
        return null;
    }

    try {
        $base = asset_query_base();
        $st = db()->prepare($base . " WHERE a.id = ? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function update_asset(int $id, array $data): array {
    if ($id <= 0) return ['success' => false, 'error' => 'ID aset tidak valid'];

    $kode = trim((string)($data['kode_inventaris'] ?? ''));
    $merk = trim((string)($data['merk'] ?? ''));
    $model = trim((string)($data['model'] ?? ''));
    $sn = trim((string)($data['serial_number'] ?? ''));
    $idKat = (int)($data['id_kategori'] ?? 0);
    $idCab = (int)($data['id_cabang'] ?? 0);
    $idDiv = (int)($data['id_divisi'] ?? 0);
    $idKar = (int)($data['id_karyawan'] ?? 0);
    $namaKar = trim((string)($data['nama_karyawan'] ?? $data['custom_karyawan'] ?? ''));
    $status = trim((string)($data['status'] ?? 'Aktif')) ?: 'Aktif';
    $ket = trim((string)($data['keterangan'] ?? ''));
    $ip = trim((string)($data['ip_address'] ?? $data['ip'] ?? ''));
    $printer = trim((string)($data['printer'] ?? ''));
    $placement = trim((string)($data['placement_label'] ?? 'Bodi Casing')) ?: 'Bodi Casing';

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) {
            return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];
        }

        // Cari atau buat karyawan jika nama diisi
        if ($namaKar !== '') {
            $karRows = $client->getSheetData('Karyawan');
            $foundKarId = 0;
            foreach ($karRows as $kr) {
                $kName = trim((string)($kr['nama_karyawan'] ?? $kr['nama'] ?? ''));
                if (strcasecmp($kName, $namaKar) === 0) {
                    $foundKarId = (int)($kr['id'] ?? 0);
                    break;
                }
            }
            if ($foundKarId > 0) {
                $idKar = $foundKarId;
            } else {
                $newKarId = count($karRows) + 1;
                $client->appendValues('Karyawan!A:D', [[$newKarId, $namaKar, $idCab, $idDiv]]);
                $idKar = $newKarId;
            }
        }

        $assets = $client->getSheetData('Assets');
        $targetRow = null;
        foreach ($assets as $a) {
            if ((int)($a['id'] ?? 0) === $id) {
                $targetRow = $a;
                break;
            }
        }

        if (!$targetRow) {
            return ['success' => false, 'error' => 'Aset dengan ID tersebut tidak ditemukan'];
        }

        $rowNum = (int)($targetRow['_row_num'] ?? 0);
        if ($rowNum <= 1) {
            return ['success' => false, 'error' => 'Gagal menentukan baris data aset'];
        }

        if ($kode === '') {
            $kode = sprintf('INV-IT-%03d', $id);
        }

        $assetRow = [
            $id,
            $kode,
            $merk,
            $model,
            $sn,
            $idKat,
            $idCab,
            $idDiv,
            $idKar,
            $status,
            $ket,
            $ip,
            $printer
        ];

        $updated = $client->updateValues("Assets!A{$rowNum}:M{$rowNum}", [$assetRow]);
        if (!$updated) {
            return ['success' => false, 'error' => 'Gagal memperbarui data di Google Sheets'];
        }

        // Update placement label di Asset_QR_Tokens jika ada
        $qrRows = $client->getSheetData('Asset_QR_Tokens');
        foreach ($qrRows as $q) {
            if ((int)($q['asset_id'] ?? 0) === $id) {
                $qrRowNum = (int)($q['_row_num'] ?? 0);
                if ($qrRowNum > 1) {
                    $client->updateValues("Asset_QR_Tokens!D{$qrRowNum}", [[$placement]]);
                }
                break;
            }
        }

        map_sheets_assets(true);

        return [
            'success' => true,
            'asset_id' => $id,
            'kode_inventaris' => $kode,
            'karyawan_nama' => $namaKar
        ];
    }

    // MySQL Mode
    try {
        if ($namaKar !== '') {
            $kName = name_column('karyawan') ?: 'nama_karyawan';
            $findSt = db()->prepare("SELECT id FROM karyawan WHERE LOWER(`{$kName}`) = LOWER(?) LIMIT 1");
            $findSt->execute([$namaKar]);
            $existingKarId = (int)$findSt->fetchColumn();
            if ($existingKarId > 0) {
                $idKar = $existingKarId;
            } else {
                $insKar = db()->prepare("INSERT INTO karyawan (`{$kName}`, id_cabang, id_divisi) VALUES (?, ?, ?)");
                $insKar->execute([$namaKar, $idCab, $idDiv]);
                $idKar = (int)db()->lastInsertId();
            }
        }

        if ($kode === '') {
            $kode = sprintf('INV-IT-%03d', $id);
        }

        $cols = table_columns('assets');
        if (!in_array('ip_address', $cols, true)) {
            try { db()->exec("ALTER TABLE assets ADD COLUMN ip_address VARCHAR(45) NULL, ADD COLUMN printer VARCHAR(100) NULL"); } catch (Throwable $e) {}
            $cols = table_columns('assets');
        }
        $hasIp = in_array('ip_address', $cols, true);

        if ($hasIp) {
            $upSt = db()->prepare("
                UPDATE assets
                SET kode_inventaris = ?, merk = ?, model = ?, serial_number = ?,
                    id_kategori = ?, id_cabang = ?, id_divisi = ?, id_karyawan = ?,
                    status = ?, keterangan = ?, ip_address = ?, printer = ?
                WHERE id = ?
            ");
            $upSt->execute([
                $kode, $merk, $model, $sn, $idKat, $idCab, $idDiv, $idKar, $status, $ket, $ip, $printer, $id
            ]);
        } else {
            $upSt = db()->prepare("
                UPDATE assets
                SET kode_inventaris = ?, merk = ?, model = ?, serial_number = ?,
                    id_kategori = ?, id_cabang = ?, id_divisi = ?, id_karyawan = ?,
                    status = ?, keterangan = ?
                WHERE id = ?
            ");
            $upSt->execute([
                $kode, $merk, $model, $sn, $idKat, $idCab, $idDiv, $idKar, $status, $ket, $id
            ]);
        }

        $upQr = db()->prepare("UPDATE asset_qr_tokens SET placement_label = ? WHERE asset_id = ?");
        $upQr->execute([$placement, $id]);

        return [
            'success' => true,
            'asset_id' => $id,
            'kode_inventaris' => $kode,
            'karyawan_nama' => $namaKar
        ];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function delete_asset(int $id): array {
    if ($id <= 0) return ['success' => false, 'error' => 'ID aset tidak valid'];

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) {
            return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];
        }

        $assets = $client->getSheetData('Assets', true);
        $targetRow = null;
        foreach ($assets as $a) {
            if ((int)($a['id'] ?? 0) === $id) {
                $targetRow = $a;
                break;
            }
        }

        if (!$targetRow) {
            return ['success' => false, 'error' => 'Data aset tidak ditemukan'];
        }

        $rowNum = (int)($targetRow['_row_num'] ?? 0);
        if ($rowNum > 1) {
            $client->clearValues("Assets!A{$rowNum}:K{$rowNum}");
        }

        // Hapus token QR terkait jika ada
        $qrRows = $client->getSheetData('Asset_QR_Tokens', true);
        foreach ($qrRows as $q) {
            if ((int)($q['asset_id'] ?? 0) === $id) {
                $qrRowNum = (int)($q['_row_num'] ?? 0);
                if ($qrRowNum > 1) {
                    $client->clearValues("Asset_QR_Tokens!A{$qrRowNum}:F{$qrRowNum}");
                }
            }
        }

        $client->clearCache();
        map_sheets_assets(true);
        return ['success' => true];
    }

    // MySQL Mode
    try {
        $st = db()->prepare("DELETE FROM asset_qr_tokens WHERE asset_id = ?");
        $st->execute([$id]);

        $st = db()->prepare("DELETE FROM maintenance_sessions WHERE asset_id = ?");
        $st->execute([$id]);

        $st = db()->prepare("DELETE FROM assets WHERE id = ?");
        $st->execute([$id]);

        return ['success' => true];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => 'Gagal menghapus aset dari database: ' . $e->getMessage()];
    }
}


function get_asset_by_token(string $token): ?array {
    $token = trim($token);
    if ($token === '') return null;

    if (is_google_cloud_mode()) {
        $assets = map_sheets_assets();
        foreach ($assets as $a) {
            $aid = (int)($a['id'] ?? 0);
            $staticTok = get_static_qr_token($aid);
            $qrToken = (string)($a['qr_token'] ?? '');
            $kode = (string)($a['kode_inventaris'] ?? '');

            if (
                strcasecmp($qrToken, $token) === 0 ||
                strcasecmp($staticTok, $token) === 0 ||
                (is_numeric($token) && (int)$token === $aid) ||
                ($kode !== '' && strcasecmp($kode, $token) === 0)
            ) {
                return $a;
            }
        }
        // Direct fallback check on Asset_QR_Tokens tab
        $client = google_sheets_v4_client();
        if ($client) {
            $qrRows = $client->getSheetData('Asset_QR_Tokens');
            foreach ($qrRows as $q) {
                $qToken = trim((string)($q['token'] ?? ''));
                if ($qToken !== '' && strcasecmp($qToken, $token) === 0) {
                    $targetAssetId = (int)($q['asset_id'] ?? 0);
                    foreach ($assets as $a) {
                        if ((int)$a['id'] === $targetAssetId) {
                            return $a;
                        }
                    }
                    $single = get_asset_by_id($targetAssetId);
                    if ($single) return $single;
                }
            }
            // Second attempt with fresh data if not found in cache
            $freshAssets = map_sheets_assets(true);
            foreach ($freshAssets as $a) {
                $aid = (int)($a['id'] ?? 0);
                $staticTok = get_static_qr_token($aid);
                $qrToken = (string)($a['qr_token'] ?? '');
                $kode = (string)($a['kode_inventaris'] ?? '');

                if (
                    strcasecmp($qrToken, $token) === 0 ||
                    strcasecmp($staticTok, $token) === 0 ||
                    (is_numeric($token) && (int)$token === $aid) ||
                    ($kode !== '' && strcasecmp($kode, $token) === 0)
                ) {
                    return $a;
                }
            }
        }
        return null;
    }

    $sql = asset_query_base() . " WHERE (q.token = ? OR a.kode_inventaris = ? " . (is_numeric($token) ? " OR a.id = ? " : "") . ") LIMIT 1";
    $st = db()->prepare($sql);
    $params = is_numeric($token) ? [$token, $token, (int)$token] : [$token, $token];
    $st->execute($params);
    $asset = $st->fetch();
    return $asset ?: null;
}


function get_qr_admin_rows(int $cabangId): array {
    if (is_google_cloud_mode()) {
        $assets = map_sheets_assets();
        if ($cabangId > 0) {
            $assets = array_filter($assets, function($a) use ($cabangId) {
                return $a['id_cabang'] === $cabangId;
            });
        }
        return array_values($assets);
    }
    $where = " WHERE (a.status = 'Aktif' OR a.status = 'aktif' OR a.status IS NULL OR a.status = '') ";
    $params = [];
    if ($cabangId) {
        $where .= " AND a.id_cabang = ? ";
        $params[] = $cabangId;
    }
    $st = db()->prepare(asset_query_base() . $where . " ORDER BY cabang_nama, karyawan_nama, a.kode_inventaris LIMIT 1000");
    $st->execute($params);
    return $st->fetchAll();
}

function generate_missing_qr_tokens(int $cabangId): int {
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return 0;

        $assets = map_sheets_assets();
        $qrRows = $client->getSheetData('Asset_QR_Tokens');
        $existingAssetIds = [];
        foreach ($qrRows as $q) $existingAssetIds[(int)($q['asset_id'] ?? 0)] = true;

        $created = 0;
        $nextId = count($qrRows) + 1;
        $newRows = [];

        foreach ($assets as $a) {
            if ($cabangId > 0 && $a['id_cabang'] !== $cabangId) continue;
            if (empty($existingAssetIds[$a['id']])) {
                $newRows[] = [
                    $nextId++,
                    $a['id'],
                    bin2hex(random_bytes(16)),
                    'Bodi Top',
                    1,
                    date('Y-m-d H:i:s')
                ];
                $created++;
            }
        }
        if ($newRows) {
            $client->appendValues('Asset_QR_Tokens!A:F', $newRows);
        }
        return $created;
    }
    $where = " WHERE (a.status = 'Aktif' OR a.status = 'aktif' OR a.status IS NULL OR a.status = '') ";
    $params = [];
    if ($cabangId) {
        $where .= " AND a.id_cabang = ? ";
        $params[] = $cabangId;
    }
    $st = db()->prepare("
        SELECT a.id FROM assets a
        LEFT JOIN asset_qr_tokens q ON q.asset_id = a.id
        {$where} AND q.id IS NULL
    ");
    $st->execute($params);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);

    $ins = db()->prepare("INSERT IGNORE INTO asset_qr_tokens (asset_id, token) VALUES (?, ?)");
    $created = 0;
    foreach ($ids as $id) {
        $ins->execute([(int)$id, bin2hex(random_bytes(16))]);
        $created += $ins->rowCount();
    }
    return $created;
}

function regenerate_qr_token(int $assetId): bool {
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return false;

        $qrRows = $client->getSheetData('Asset_QR_Tokens');
        $newToken = bin2hex(random_bytes(16));

        foreach ($qrRows as $q) {
            if ((int)($q['asset_id'] ?? 0) === $assetId) {
                $rowNum = (int)($q['_row_num'] ?? 0);
                if ($rowNum > 1) {
                    $client->updateValues("Asset_QR_Tokens!C{$rowNum}:E{$rowNum}", [[$newToken, 'Bodi Top', 1]]);
                    return true;
                }
            }
        }
        // If not found, append
        $newId = count($qrRows) + 1;
        $client->appendValues('Asset_QR_Tokens!A:F', [[$newId, $assetId, $newToken, 'Bodi Top', 1, date('Y-m-d H:i:s')]]);
        return true;
    }
    if ($assetId > 0) {
        $new = bin2hex(random_bytes(16));
        $st = db()->prepare("
            INSERT INTO asset_qr_tokens (asset_id, token, is_active)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE token = VALUES(token), is_active = 1
        ");
        $st->execute([$assetId, $new]);
        return true;
    }
    return false;
}

