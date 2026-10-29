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

function asset_title(?array $a): string {
    if (empty($a)) {
        return 'Perangkat Tidak Ditemukan / Telah Dihapus';
    }
    $kategori = trim((string)($a['kategori_nama'] ?? ''));
    $merk = trim((string)($a['merk'] ?? ''));
    $model = trim((string)($a['model'] ?? ''));

    // Bersihkan duplikasi jika model diawali oleh merk (misal merk "Asus", model "Asus VivoBook ...")
    if ($merk !== '' && $model !== '') {
        if (stripos($model, $merk) === 0) {
            $model = trim(preg_replace('/^' . preg_quote($merk, '/') . '\s*/i', '', $model));
        }
    }

    $parts = [];
    if ($kategori !== '') $parts[] = $kategori;
    if ($merk !== '') $parts[] = $merk;
    if ($model !== '') $parts[] = $model;

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

    // Preload all 6 sheets in 1 single batch request to Google Cloud
    $client->preloadSheets(['Assets', 'Cabang', 'Divisi', 'Karyawan', 'Kategori_Aset', 'Asset_QR_Tokens'], $refresh);

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
            'kode_inventaris' => normalize_kode_inventaris((string)($a['kode_inventaris'] ?? '')),
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
            'created_at' => trim((string)($a['created_at'] ?? $a['tanggal_pengadaan'] ?? $a['created_date'] ?? $qr['created_at'] ?? $qr['col_5'] ?? '')),
            'cabang_nama' => $cabangMap[$a['id_cabang'] ?? 0] ?? '-',
            'divisi_nama' => $divMap[$a['id_divisi'] ?? 0] ?? '-',
            'karyawan_nama' => $karMap[$a['id_karyawan'] ?? 0] ?? '-',
            'kategori_nama' => $katMap[$a['id_kategori'] ?? 0] ?? '-',
            'qr_token' => $token,
            'foto' => $a['foto'] ?? '',
            'placement_label' => $qr['placement_label'] ?? 'Bodi Top',
            'qr_active' => $qrActive,
            'nomor_seri_inventaris' => $a['nomor_seri_inventaris'] ?? '',
            'jenis_inventaris' => $a['jenis_inventaris'] ?? '',
            'golongan' => $a['golongan'] ?? '',
            'ruangan' => $a['ruangan'] ?? '',
            'sumber_perolehan' => $a['sumber_perolehan'] ?? '',
            'status_inventaris' => $a['status_inventaris'] ?? '',
            'foto_updated_by' => $a['foto_updated_by'] ?? '',
            'foto_updated_at' => $a['foto_updated_at'] ?? ''
        ];
    }, $assets);

    return $cachedAssets;
}

function ensure_asset_master_columns(): void {
    static $ensured = false;
    if ($ensured) return;
    try {
        $cols = table_columns('assets');
        $alterSql = [];
        if (!in_array('ip_address', $cols, true)) $alterSql[] = "ADD COLUMN ip_address VARCHAR(45) NULL";
        if (!in_array('printer', $cols, true)) $alterSql[] = "ADD COLUMN printer VARCHAR(100) NULL";
        if (!in_array('foto', $cols, true)) $alterSql[] = "ADD COLUMN foto TEXT NULL";
        if (!in_array('created_at', $cols, true)) $alterSql[] = "ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP";
        if (!in_array('nomor_seri_inventaris', $cols, true)) $alterSql[] = "ADD COLUMN nomor_seri_inventaris VARCHAR(100) NULL";
        if (!in_array('jenis_inventaris', $cols, true)) $alterSql[] = "ADD COLUMN jenis_inventaris VARCHAR(100) NULL";
        if (!in_array('golongan', $cols, true)) $alterSql[] = "ADD COLUMN golongan VARCHAR(100) NULL";
        if (!in_array('ruangan', $cols, true)) $alterSql[] = "ADD COLUMN ruangan VARCHAR(150) NULL";
        if (!in_array('sumber_perolehan', $cols, true)) $alterSql[] = "ADD COLUMN sumber_perolehan VARCHAR(100) NULL";
        if (!in_array('status_inventaris', $cols, true)) $alterSql[] = "ADD COLUMN status_inventaris VARCHAR(100) NULL";
        if (!in_array('foto_updated_by', $cols, true)) $alterSql[] = "ADD COLUMN foto_updated_by VARCHAR(150) NULL";
        if (!in_array('foto_updated_at', $cols, true)) $alterSql[] = "ADD COLUMN foto_updated_at DATETIME NULL";

        if (!empty($alterSql)) {
            db()->exec("ALTER TABLE assets " . implode(', ', $alterSql));
        }
        $ensured = true;
    } catch (Throwable $e) {}
}

function asset_image_url(?string $foto): string {
    $foto = trim((string)$foto);
    if ($foto === '') return '';
    if (str_starts_with($foto, 'http://') || str_starts_with($foto, 'https://') || str_starts_with($foto, '//')) {
        return $foto;
    }
    $clean = ltrim($foto, '/');
    return module_url($clean);
}

function upload_asset_image(string $fileKey = 'foto_unit'): ?string {
    if (empty($_FILES[$fileKey]) || empty($_FILES[$fileKey]['tmp_name']) || !is_uploaded_file($_FILES[$fileKey]['tmp_name'])) {
        return null;
    }
    $file = $_FILES[$fileKey];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $maxSize = 25 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return null;
    }
    $uploadDir = dirname(__DIR__) . '/uploads/assets/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
        @chmod($uploadDir, 0777);
    }
    $fileName = 'unit_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.jpg';
    $targetPath = $uploadDir . $fileName;

    // Kompresi otomatis (PHP GD max 1600px quality 82%) agar hemat storage server
    if (function_exists('compress_and_save_uploaded_image')) {
        if (compress_and_save_uploaded_image($file['tmp_name'], $targetPath, 1600, 82)) {
            return 'uploads/assets/' . $fileName;
        }
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        @chmod($targetPath, 0666);
        return 'uploads/assets/' . $fileName;
    }
    return null;
}

function save_asset_base64_image(string $base64Data): ?string {
    $clean = trim($base64Data);
    if ($clean === '') return null;
    if (preg_match('/^data:image\/(\w+);base64,/', $clean)) {
        $clean = substr($clean, strpos($clean, ',') + 1);
    }
    $decoded = base64_decode($clean);
    if ($decoded === false || strlen($decoded) === 0) return null;

    $uploadDir = dirname(__DIR__) . '/uploads/assets/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
        @chmod($uploadDir, 0777);
    }
    $fileName = 'unit_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.jpg';
    $targetPath = $uploadDir . $fileName;
    $tmpPath = $uploadDir . 'tmp_' . bin2hex(random_bytes(6)) . '.jpg';
    @file_put_contents($tmpPath, $decoded);

    $saved = false;
    if (function_exists('compress_and_save_uploaded_image')) {
        $saved = compress_and_save_uploaded_image($tmpPath, $targetPath, 1600, 82);
    }
    if (!$saved) {
        $saved = @rename($tmpPath, $targetPath);
    } else {
        @unlink($tmpPath);
    }
    if ($saved && file_exists($targetPath)) {
        @chmod($targetPath, 0666);
        return 'uploads/assets/' . $fileName;
    }
    return null;
}

function get_asset_replacement_info(array $asset): array {
    $info = [
        'replaces' => null,
        'replaced_by' => null,
        'date' => null,
    ];
    $replacesId = (int)($asset['replaces_asset_id'] ?? 0);
    $replacedById = (int)($asset['replaced_by_id'] ?? 0);
    $replDate = !empty($asset['replacement_date']) ? (string)$asset['replacement_date'] : null;
    $ket = (string)($asset['keterangan'] ?? '');

    if ($replacesId <= 0 && preg_match('/Menggantikan unit (?:baru |lama )?#?(\d+)/i', $ket, $m)) {
        $replacesId = (int)$m[1];
    }
    if ($replacedById <= 0 && preg_match('/Diganti (?:unit baru|dengan unit baru) (?:pada [^#]+)?#(\d+)/i', $ket, $m)) {
        $replacedById = (int)$m[1];
    }
    if (!$replDate && preg_match('/tgl\s+(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i', $ket, $m)) {
        $replDate = $m[1];
    }

    if ($replacesId > 0) {
        $oldA = get_asset_by_id($replacesId);
        if ($oldA) {
            $info['replaces'] = $oldA;
        }
    }
    if ($replacedById > 0) {
        $newA = get_asset_by_id($replacedById);
        if ($newA) {
            $info['replaced_by'] = $newA;
        }
    }
    $info['date'] = $replDate;
    return $info;
}

function create_new_asset(array $data): array {
    $kode = normalize_kode_inventaris(trim((string)($data['kode_inventaris'] ?? '')));
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
    $foto = trim((string)($data['foto'] ?? ''));

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
            sheet_cell_text($kode),
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
            $printer,
            date('Y-m-d H:i:s')
        ];

        $appended = $client->appendValues('Assets!A:N', [$assetRow]);
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

        $devLabel = trim($merk . ' ' . $model);
        $label = $kode . ($devLabel !== '' ? " ({$devLabel})" : '');
        $detail = "Registrasi unit baru. Pengguna: " . ($namaKar ?: '-') . ", IP: " . ($ip ?: '-');
        record_audit_log('CREATE', 'ASET', $newAssetId, $label, $detail);

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

        ensure_asset_master_columns();
        $cols = table_columns('assets');
        $nowStr = date('Y-m-d H:i:s');

        $noSeri = trim((string)($data['nomor_seri_inventaris'] ?? ''));
        $jenisInv = trim((string)($data['jenis_inventaris'] ?? ''));
        $golongan = trim((string)($data['golongan'] ?? ''));
        $ruangan = trim((string)($data['ruangan'] ?? ''));
        $sumberPerolehan = trim((string)($data['sumber_perolehan'] ?? ''));
        $statusInv = trim((string)($data['status_inventaris'] ?? '1 : Aktif'));

        $insertData = [
            'kode_inventaris' => $kode,
            'merk' => $merk,
            'model' => $model,
            'serial_number' => $sn,
            'id_kategori' => $idKat,
            'id_cabang' => $idCab,
            'id_divisi' => $idDiv,
            'id_karyawan' => $idKar,
            'status' => $status,
            'keterangan' => $ket,
        ];
        if (in_array('ip_address', $cols, true)) $insertData['ip_address'] = $ip;
        if (in_array('printer', $cols, true)) $insertData['printer'] = $printer;
        if (in_array('foto', $cols, true)) $insertData['foto'] = $foto;
        if (in_array('created_at', $cols, true)) $insertData['created_at'] = $nowStr;
        if (in_array('nomor_seri_inventaris', $cols, true)) $insertData['nomor_seri_inventaris'] = $noSeri;
        if (in_array('jenis_inventaris', $cols, true)) $insertData['jenis_inventaris'] = $jenisInv;
        if (in_array('golongan', $cols, true)) $insertData['golongan'] = $golongan;
        if (in_array('ruangan', $cols, true)) $insertData['ruangan'] = $ruangan;
        if (in_array('sumber_perolehan', $cols, true)) $insertData['sumber_perolehan'] = $sumberPerolehan;
        if (in_array('status_inventaris', $cols, true)) $insertData['status_inventaris'] = $statusInv;
        if (in_array('replaces_asset_id', $cols, true) && !empty($data['replaces_asset_id'])) {
            $insertData['replaces_asset_id'] = (int)$data['replaces_asset_id'];
        }
        if (in_array('replaced_by_id', $cols, true) && !empty($data['replaced_by_id'])) {
            $insertData['replaced_by_id'] = (int)$data['replaced_by_id'];
        }
        if (in_array('replacement_date', $cols, true) && !empty($data['replacement_date'])) {
            $insertData['replacement_date'] = substr((string)$data['replacement_date'], 0, 10);
        }

        $fieldNames = array_keys($insertData);
        $fieldHolders = array_fill(0, count($fieldNames), '?');
        $sql = "INSERT INTO assets (`" . implode('`, `', $fieldNames) . "`) VALUES (" . implode(', ', $fieldHolders) . ")";
        $ins = db()->prepare($sql);
        $ins->execute(array_values($insertData));
        $assetId = (int)db()->lastInsertId();

        $token = bin2hex(random_bytes(16));
        $insQr = db()->prepare("
            INSERT INTO asset_qr_tokens (asset_id, token, placement_label, is_active)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE token = VALUES(token), placement_label = VALUES(placement_label), is_active = 1
        ");
        $insQr->execute([$assetId, $token, $placement]);

        $devLabel = trim($merk . ' ' . $model);
        $label = $kode . ($devLabel !== '' ? " ({$devLabel})" : '');
        $detail = "Registrasi unit baru. Pengguna: " . ($namaKar ?: '-') . ", IP: " . ($ip ?: '-');
        record_audit_log('CREATE', 'ASET', $assetId, $label, $detail);

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

function get_asset_by_id(int $id, bool $refresh = false): ?array {
    if ($id <= 0) return null;
    if (is_google_cloud_mode()) {
        $assets = map_sheets_assets($refresh);
        foreach ($assets as $a) {
            if ((int)($a['id'] ?? 0) === $id) {
                if (empty($a['qr_token'])) {
                    $a['qr_token'] = get_static_qr_token($id);
                }
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
        if ($row) {
            if (isset($row['kode_inventaris'])) {
                $row['kode_inventaris'] = normalize_kode_inventaris((string)$row['kode_inventaris']);
            }
            if (empty($row['qr_token'])) {
                $staticTok = get_static_qr_token($id);
                $row['qr_token'] = $staticTok;
                // Auto-sync into asset_qr_tokens so token is immediately indexed
                try {
                    $ins = db()->prepare("INSERT INTO asset_qr_tokens (asset_id, token, is_active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_active = 1");
                    $ins->execute([$id, $staticTok]);
                } catch (Throwable $e) {}
            }
        }
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Mengambil banyak aset sekaligus dengan 1 batch query / 1 sheet request (Anti-Timeout)
 */
function get_assets_by_ids(array $ids, bool $refresh = false): array {
    $ids = array_values(array_filter(array_map('intval', $ids), function($v) { return $v > 0; }));
    if (empty($ids)) return [];

    if (is_google_cloud_mode()) {
        $assets = map_sheets_assets($refresh);
        $idLookup = array_flip($ids);
        $result = [];
        foreach ($assets as $a) {
            $aid = (int)($a['id'] ?? 0);
            if (isset($idLookup[$aid])) {
                if (empty($a['qr_token'])) {
                    $a['qr_token'] = get_static_qr_token($aid);
                }
                $result[$aid] = $a;
            }
        }
        $ordered = [];
        foreach ($ids as $aid) {
            if (isset($result[$aid])) {
                $ordered[] = $result[$aid];
            }
        }
        return $ordered;
    }

    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $base = asset_query_base();
        $st = db()->prepare($base . " WHERE a.id IN ($placeholders)");
        $st->execute($ids);
        $rows = $st->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $aid = (int)($row['id'] ?? 0);
            if (isset($row['kode_inventaris'])) {
                $row['kode_inventaris'] = normalize_kode_inventaris((string)$row['kode_inventaris']);
            }
            if (empty($row['qr_token'])) {
                $row['qr_token'] = get_static_qr_token($aid);
            }
            $map[$aid] = $row;
        }
        $ordered = [];
        foreach ($ids as $aid) {
            if (isset($map[$aid])) {
                $ordered[] = $map[$aid];
            }
        }
        return $ordered;
    } catch (Throwable $e) {
        return [];
    }
}

function update_asset(int $id, array $data): array {
    if ($id <= 0) return ['success' => false, 'error' => 'ID aset tidak valid'];

    $oldAsset = get_asset_by_id($id);
    if (!$oldAsset) return ['success' => false, 'error' => 'Aset dengan ID tersebut tidak ditemukan'];

    $kode = array_key_exists('kode_inventaris', $data)
        ? normalize_kode_inventaris(trim((string)$data['kode_inventaris']))
        : ($oldAsset['kode_inventaris'] ?? '');

    $merk = array_key_exists('merk', $data)
        ? trim((string)$data['merk'])
        : ($oldAsset['merk'] ?? '');

    $model = array_key_exists('model', $data)
        ? trim((string)$data['model'])
        : ($oldAsset['model'] ?? '');

    $sn = array_key_exists('serial_number', $data)
        ? trim((string)$data['serial_number'])
        : ($oldAsset['serial_number'] ?? '');

    $idKat = array_key_exists('id_kategori', $data)
        ? (int)$data['id_kategori']
        : (int)($oldAsset['id_kategori'] ?? 0);

    $idCab = array_key_exists('id_cabang', $data)
        ? (int)$data['id_cabang']
        : (int)($oldAsset['id_cabang'] ?? 0);

    $idDiv = array_key_exists('id_divisi', $data)
        ? (int)$data['id_divisi']
        : (int)($oldAsset['id_divisi'] ?? 0);

    $idKar = array_key_exists('id_karyawan', $data)
        ? (int)$data['id_karyawan']
        : (int)($oldAsset['id_karyawan'] ?? 0);

    $namaKar = (array_key_exists('nama_karyawan', $data) || array_key_exists('custom_karyawan', $data))
        ? trim((string)($data['nama_karyawan'] ?? $data['custom_karyawan'] ?? ''))
        : ($oldAsset['karyawan_nama'] ?? '');

    $status = array_key_exists('status', $data)
        ? (trim((string)$data['status']) ?: 'Aktif')
        : ($oldAsset['status'] ?? 'Aktif');

    $ket = array_key_exists('keterangan', $data)
        ? trim((string)$data['keterangan'])
        : ($oldAsset['keterangan'] ?? '');

    $ip = (array_key_exists('ip_address', $data) || array_key_exists('ip', $data))
        ? trim((string)($data['ip_address'] ?? $data['ip'] ?? ''))
        : ($oldAsset['ip_address'] ?? $oldAsset['ip'] ?? '');

    $printer = array_key_exists('printer', $data)
        ? trim((string)$data['printer'])
        : ($oldAsset['printer'] ?? '');

    $placement = array_key_exists('placement_label', $data)
        ? (trim((string)$data['placement_label']) ?: 'Bodi Casing')
        : ($oldAsset['placement_label'] ?? 'Bodi Casing');

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
            $kode = !empty($oldAsset['kode_inventaris']) ? $oldAsset['kode_inventaris'] : sprintf('INV-IT-%03d', $id);
        }

        $assetRow = [
            $id,
            sheet_cell_text($kode),
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

        // Update placement label di Asset_QR_Tokens dan pastikan token tetap utuh & permanen
        $qrRows = $client->getSheetData('Asset_QR_Tokens');
        $foundQr = false;
        foreach ($qrRows as $q) {
            if ((int)($q['asset_id'] ?? 0) === $id) {
                $foundQr = true;
                $qrRowNum = (int)($q['_row_num'] ?? 0);
                if ($qrRowNum > 1 && array_key_exists('placement_label', $data)) {
                    $client->updateValues("Asset_QR_Tokens!D{$qrRowNum}", [[$placement]]);
                }
                break;
            }
        }
        // Jika belum ada row di Asset_QR_Tokens, kunci token permanen agar tidak pernah berubah
        if (!$foundQr) {
            $nextQrId = count($qrRows) + 1;
            $staticToken = get_static_qr_token($id);
            $client->appendValues('Asset_QR_Tokens!A:F', [[
                $nextQrId,
                $id,
                $staticToken,
                $placement,
                1,
                date('Y-m-d H:i:s')
            ]]);
        }

        map_sheets_assets(true);

        if ($oldAsset) {
            $diffs = diff_asset_changes($oldAsset, $data);
            $devLabel = trim(($merk ?: ($oldAsset['merk'] ?? '')) . ' ' . ($model ?: ($oldAsset['model'] ?? '')));
            $label = ($kode ?: ($oldAsset['kode_inventaris'] ?? '#' . $id)) . ($devLabel !== '' ? " ({$devLabel})" : '');
            $detail = !empty($diffs) ? implode(' | ', $diffs) : 'Data aset diperbarui';
            record_audit_log('UPDATE', 'ASET', $id, $label, $detail);
        }

        return [
            'success' => true,
            'asset_id' => $id,
            'kode_inventaris' => $kode,
            'karyawan_nama' => $namaKar
        ];
    }

    // MySQL Mode
    try {
        if ($namaKar !== '' && (array_key_exists('nama_karyawan', $data) || array_key_exists('custom_karyawan', $data))) {
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
            $kode = !empty($oldAsset['kode_inventaris']) ? $oldAsset['kode_inventaris'] : sprintf('INV-IT-%03d', $id);
        }

        ensure_asset_master_columns();
        $cols = table_columns('assets');

        $updateFields = [];
        if (array_key_exists('kode_inventaris', $data)) $updateFields['kode_inventaris'] = $kode;
        if (array_key_exists('merk', $data)) $updateFields['merk'] = $merk;
        if (array_key_exists('model', $data)) $updateFields['model'] = $model;
        if (array_key_exists('serial_number', $data)) $updateFields['serial_number'] = $sn;
        if (array_key_exists('id_kategori', $data)) $updateFields['id_kategori'] = $idKat;
        if (array_key_exists('id_cabang', $data)) $updateFields['id_cabang'] = $idCab;
        if (array_key_exists('id_divisi', $data)) $updateFields['id_divisi'] = $idDiv;
        if (array_key_exists('id_karyawan', $data) || array_key_exists('nama_karyawan', $data) || array_key_exists('custom_karyawan', $data)) {
            $updateFields['id_karyawan'] = $idKar;
        }
        if (array_key_exists('status', $data)) $updateFields['status'] = $status;
        if (array_key_exists('keterangan', $data)) $updateFields['keterangan'] = $ket;
        if ((array_key_exists('ip_address', $data) || array_key_exists('ip', $data)) && in_array('ip_address', $cols, true)) {
            $updateFields['ip_address'] = $ip;
        }
        if (array_key_exists('printer', $data) && in_array('printer', $cols, true)) {
            $updateFields['printer'] = $printer;
        }
        if (array_key_exists('foto', $data)) {
            $updateFields['foto'] = trim((string)$data['foto']);
        }
        if (array_key_exists('foto_updated_by', $data) && in_array('foto_updated_by', $cols, true)) {
            $updateFields['foto_updated_by'] = trim((string)$data['foto_updated_by']);
        }
        if (array_key_exists('foto_updated_at', $data) && in_array('foto_updated_at', $cols, true)) {
            $updateFields['foto_updated_at'] = trim((string)$data['foto_updated_at']);
        }
        if (array_key_exists('nomor_seri_inventaris', $data) && in_array('nomor_seri_inventaris', $cols, true)) {
            $updateFields['nomor_seri_inventaris'] = trim((string)$data['nomor_seri_inventaris']);
        }
        if (array_key_exists('jenis_inventaris', $data) && in_array('jenis_inventaris', $cols, true)) {
            $updateFields['jenis_inventaris'] = trim((string)$data['jenis_inventaris']);
        }
        if (array_key_exists('golongan', $data) && in_array('golongan', $cols, true)) {
            $updateFields['golongan'] = trim((string)$data['golongan']);
        }
        if (array_key_exists('ruangan', $data) && in_array('ruangan', $cols, true)) {
            $updateFields['ruangan'] = trim((string)$data['ruangan']);
        }
        if (array_key_exists('replaces_asset_id', $data) && in_array('replaces_asset_id', $cols, true)) {
            $updateFields['replaces_asset_id'] = $data['replaces_asset_id'] ? (int)$data['replaces_asset_id'] : null;
        }
        if (array_key_exists('replaced_by_id', $data) && in_array('replaced_by_id', $cols, true)) {
            $updateFields['replaced_by_id'] = $data['replaced_by_id'] ? (int)$data['replaced_by_id'] : null;
        }
        if (array_key_exists('replacement_date', $data) && in_array('replacement_date', $cols, true)) {
            $updateFields['replacement_date'] = $data['replacement_date'] ? substr((string)$data['replacement_date'], 0, 10) : null;
        }
        if (array_key_exists('sumber_perolehan', $data) && in_array('sumber_perolehan', $cols, true)) {
            $updateFields['sumber_perolehan'] = trim((string)$data['sumber_perolehan']);
        }
        if (array_key_exists('status_inventaris', $data) && in_array('status_inventaris', $cols, true)) {
            $updateFields['status_inventaris'] = trim((string)$data['status_inventaris']);
        }

        if (!empty($updateFields)) {
            $setParts = [];
            $values = [];
            foreach ($updateFields as $colName => $val) {
                $setParts[] = "`{$colName}` = ?";
                $values[] = $val;
            }
            $values[] = $id;

            $upSt = db()->prepare("UPDATE assets SET " . implode(', ', $setParts) . " WHERE id = ?");
            $upSt->execute($values);
        }

        // Pastikan asset_qr_tokens ada dan token tetap utuh & permanen
        if (array_key_exists('placement_label', $data)) {
            $checkQr = db()->prepare("SELECT token FROM asset_qr_tokens WHERE asset_id = ? LIMIT 1");
            $checkQr->execute([$id]);
            $existingToken = $checkQr->fetchColumn();
            if ($existingToken) {
                $upQr = db()->prepare("UPDATE asset_qr_tokens SET placement_label = ? WHERE asset_id = ?");
                $upQr->execute([$placement, $id]);
            } else {
                $staticToken = get_static_qr_token($id);
                $insQr = db()->prepare("INSERT INTO asset_qr_tokens (asset_id, token, placement_label, is_active) VALUES (?, ?, ?, 1)");
                $insQr->execute([$id, $staticToken, $placement]);
            }
        }

        if ($oldAsset) {
            $diffs = diff_asset_changes($oldAsset, $data);
            $devLabel = trim(($merk ?: ($oldAsset['merk'] ?? '')) . ' ' . ($model ?: ($oldAsset['model'] ?? '')));
            $label = ($kode ?: ($oldAsset['kode_inventaris'] ?? '#' . $id)) . ($devLabel !== '' ? " ({$devLabel})" : '');
            $detail = !empty($diffs) ? implode(' | ', $diffs) : 'Data aset diperbarui';
            record_audit_log('UPDATE', 'ASET', $id, $label, $detail);
        }

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

    $oldAsset = get_asset_by_id($id);

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

        if ($oldAsset) {
            $devLabel = trim(($oldAsset['merk'] ?? '') . ' ' . ($oldAsset['model'] ?? ''));
            $label = ($oldAsset['kode_inventaris'] ?? '#' . $id) . ($devLabel !== '' ? " ({$devLabel})" : '');
            $detail = "Menghapus unit komputer. Pengguna: " . ($oldAsset['karyawan_nama'] ?? '-') . ", Cabang: " . ($oldAsset['cabang_nama'] ?? '-') . ", Divisi: " . ($oldAsset['divisi_nama'] ?? '-') . ", IP: " . ($oldAsset['ip_address'] ?? '-');
            record_audit_log('DELETE', 'ASET', $id, $label, $detail);
        }

        return ['success' => true];
    }

    // MySQL Mode
    try {
        $relatedTables = [
            'asset_qr_tokens',
            'maintenance_findings',
            'maintenance_checklists',
            'maintenance_scan',
            'asset_mutations'
        ];
        foreach ($relatedTables as $tbl) {
            try {
                $st = db()->prepare("DELETE FROM {$tbl} WHERE asset_id = ?");
                $st->execute([$id]);
            } catch (Throwable $ignored) {
                // Lanjutkan jika tabel opsional belum ada
            }
        }

        $st = db()->prepare("DELETE FROM assets WHERE id = ?");
        $st->execute([$id]);

        if ($oldAsset) {
            $devLabel = trim(($oldAsset['merk'] ?? '') . ' ' . ($oldAsset['model'] ?? ''));
            $label = ($oldAsset['kode_inventaris'] ?? '#' . $id) . ($devLabel !== '' ? " ({$devLabel})" : '');
            $detail = "Menghapus unit komputer. Pengguna: " . ($oldAsset['karyawan_nama'] ?? '-') . ", Cabang: " . ($oldAsset['cabang_nama'] ?? '-') . ", Divisi: " . ($oldAsset['divisi_nama'] ?? '-') . ", IP: " . ($oldAsset['ip_address'] ?? '-');
            record_audit_log('DELETE', 'ASET', $id, $label, $detail);
        }

        return ['success' => true];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => 'Gagal menghapus aset dari database: ' . $e->getMessage()];
    }
}


function get_asset_by_token(string $token): ?array {
    $token = trim($token);
    if ($token === '') return null;
    $token = trim($token, " '\t\n\r\0\x0B");
    $normToken = normalize_kode_inventaris($token);
    $unpaddedToken = ltrim($token, '0');

    if (is_google_cloud_mode()) {
        $assets = map_sheets_assets();
        foreach ($assets as $a) {
            $aid = (int)($a['id'] ?? 0);
            $staticTok = get_static_qr_token($aid);
            $qrToken = (string)($a['qr_token'] ?? '');
            $kode = (string)($a['kode_inventaris'] ?? '');
            $sn = (string)($a['serial_number'] ?? '');

            if (
                strcasecmp($qrToken, $token) === 0 ||
                strcasecmp($staticTok, $token) === 0 ||
                (is_numeric($token) && (int)$token === $aid) ||
                ($kode !== '' && (strcasecmp($kode, $token) === 0 || strcasecmp($kode, $normToken) === 0 || ($unpaddedToken !== '' && strcasecmp(ltrim($kode, '0'), $unpaddedToken) === 0))) ||
                ($sn !== '' && strcasecmp($sn, $token) === 0)
            ) {
                return $a;
            }
        }
        // Direct fallback check on Asset_QR_Tokens tab
        $client = google_sheets_v4_client();
        if ($client) {
            $qrRows = $client->getSheetData('Asset_QR_Tokens', true);
            foreach ($qrRows as $q) {
                $qToken = trim((string)($q['token'] ?? $q['col_2'] ?? ''));
                if ($qToken !== '' && strcasecmp($qToken, $token) === 0) {
                    $targetAssetId = (int)($q['asset_id'] ?? $q['col_1'] ?? 0);
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
                $sn = (string)($a['serial_number'] ?? '');

                if (
                    strcasecmp($qrToken, $token) === 0 ||
                    strcasecmp($staticTok, $token) === 0 ||
                    (is_numeric($token) && (int)$token === $aid) ||
                    ($kode !== '' && (strcasecmp($kode, $token) === 0 || strcasecmp($kode, $normToken) === 0 || ($unpaddedToken !== '' && strcasecmp(ltrim($kode, '0'), $unpaddedToken) === 0))) ||
                    ($sn !== '' && strcasecmp($sn, $token) === 0)
                ) {
                    return $a;
                }
            }
        }
        return null;
    }

    // MySQL Mode
    try {
        // 1. Direct search by token, kode_inventaris, serial_number, or numeric ID
        $sql = asset_query_base() . " WHERE (q.token = ? OR a.kode_inventaris = ? OR a.kode_inventaris = ? OR a.serial_number = ?" . (is_numeric($token) ? " OR a.id = ? " : "") . ") LIMIT 1";
        $st = db()->prepare($sql);
        $params = is_numeric($token) ? [$token, $token, $normToken, $token, (int)$token] : [$token, $token, $normToken, $token];
        $st->execute($params);
        $asset = $st->fetch();
        if ($asset) {
            if (isset($asset['kode_inventaris'])) {
                $asset['kode_inventaris'] = normalize_kode_inventaris((string)$asset['kode_inventaris']);
            }
            return $asset;
        }

        // 2. Static QR Token Fallback for MySQL: check if token matches get_static_qr_token($id)
        $stAll = db()->query("SELECT id, kode_inventaris, serial_number FROM assets");
        $allA = $stAll ? $stAll->fetchAll() : [];
        foreach ($allA as $row) {
            $aid = (int)$row['id'];
            $stToken = get_static_qr_token($aid);
            $rowKode = (string)($row['kode_inventaris'] ?? '');
            $rowSn = (string)($row['serial_number'] ?? '');
            if (
                strcasecmp($stToken, $token) === 0 ||
                ($unpaddedToken !== '' && strcasecmp(ltrim($rowKode, '0'), $unpaddedToken) === 0) ||
                ($rowSn !== '' && strcasecmp($rowSn, $token) === 0)
            ) {
                // Ensure token is persisted into asset_qr_tokens
                try {
                    $insQr = db()->prepare("INSERT INTO asset_qr_tokens (asset_id, token, is_active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_active = 1");
                    $insQr->execute([$aid, $stToken]);
                } catch (Throwable $e) {}

                return get_asset_by_id($aid);
            }
        }
    } catch (Throwable $e) {}

    return null;
}


function get_qr_admin_rows(int $cabangId): array {
    if (is_google_cloud_mode()) {
        $assets = map_sheets_assets();
        if ($cabangId > 0) {
            $assets = array_filter($assets, function($a) use ($cabangId) {
                return (int)($a['id_cabang'] ?? 0) === $cabangId;
            });
        }
        return array_values($assets);
    }
    $where = " WHERE 1=1 ";
    $params = [];
    if ($cabangId > 0) {
        $where .= " AND a.id_cabang = ? ";
        $params[] = $cabangId;
    }
    $st = db()->prepare(asset_query_base() . $where . " ORDER BY cabang_nama, karyawan_nama, a.kode_inventaris LIMIT 1000");
    $st->execute($params);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $aid = (int)($r['id'] ?? 0);
        if (empty($r['qr_token']) && $aid > 0) {
            $r['qr_token'] = get_static_qr_token($aid);
        }
        if (isset($r['kode_inventaris'])) {
            $r['kode_inventaris'] = normalize_kode_inventaris((string)$r['kode_inventaris']);
        }
    }
    return $rows;
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

function bulk_update_assets(array $ids, array $updates): array {
    $updatedCount = 0;
    $errors = [];

    // Pre-sanitize IDs
    $validIds = [];
    foreach ($ids as $rawId) {
        $cleanId = (int)$rawId;
        if ($cleanId > 0) {
            $validIds[] = $cleanId;
        }
    }
    $validIds = array_values(array_unique($validIds));

    if (empty($validIds)) {
        return ['success' => false, 'error' => 'Tidak ada aset yang dipilih untuk diubah', 'updated_count' => 0];
    }

    foreach ($validIds as $aid) {
        $old = get_asset_by_id($aid);
        if (!$old) {
            $errors[] = "Aset ID #{$aid} tidak ditemukan.";
            continue;
        }

        $idCab = (!empty($updates['id_cabang']) && (int)$updates['id_cabang'] > 0) 
            ? (int)$updates['id_cabang'] 
            : (int)($old['id_cabang'] ?? 0);

        $idDiv = (!empty($updates['id_divisi']) && (int)$updates['id_divisi'] > 0) 
            ? (int)$updates['id_divisi'] 
            : (int)($old['id_divisi'] ?? 0);

        $idKat = (!empty($updates['id_kategori']) && (int)$updates['id_kategori'] > 0) 
            ? (int)$updates['id_kategori'] 
            : (int)($old['id_kategori'] ?? 0);

        $status = (!empty($updates['status']) && $updates['status'] !== 'keep') 
            ? trim((string)$updates['status']) 
            : trim((string)($old['status'] ?? 'Aktif'));

        $placement = (!empty($updates['placement_label']) && $updates['placement_label'] !== 'keep') 
            ? trim((string)$updates['placement_label']) 
            : trim((string)($old['placement_label'] ?? 'Bodi Casing'));

        $printer = (!empty($updates['change_printer'])) 
            ? trim((string)($updates['printer'] ?? '')) 
            : trim((string)($old['printer'] ?? ''));

        $namaKar = (!empty($updates['change_karyawan'])) 
            ? trim((string)($updates['nama_karyawan'] ?? '')) 
            : trim((string)($old['karyawan_nama'] ?? $old['nama_karyawan'] ?? ''));

        $payload = [
            'kode_inventaris' => trim((string)($old['kode_inventaris'] ?? '')),
            'merk' => trim((string)($old['merk'] ?? '')),
            'model' => trim((string)($old['model'] ?? '')),
            'serial_number' => trim((string)($old['serial_number'] ?? '')),
            'id_kategori' => $idKat,
            'id_cabang' => $idCab,
            'id_divisi' => $idDiv,
            'status' => $status,
            'placement_label' => $placement,
            'keterangan' => trim((string)($old['keterangan'] ?? '')),
            'ip_address' => trim((string)($old['ip_address'] ?? $old['ip'] ?? '')),
            'printer' => $printer,
            'nama_karyawan' => $namaKar
        ];

        $res = update_asset($aid, $payload);
        if (!empty($res['success'])) {
            $updatedCount++;
        } else {
            $errors[] = "Aset #{$aid}: " . ($res['error'] ?? 'Gagal diperbarui');
        }
    }

    if (is_google_cloud_mode()) {
        map_sheets_assets(true);
    }

    return [
        'success' => $updatedCount > 0,
        'total_requested' => count($validIds),
        'updated_count' => $updatedCount,
        'errors' => $errors
    ];
}

/**
 * Menyimpan perubahan edit massal multi-row spreadsheet dalam 1 single batch request (Super Cepat & Anti-Timeout)
 */
function bulk_update_assets_spreadsheet(array $assetsData): array {
    if (empty($assetsData)) {
        return ['success' => false, 'error' => 'Data aset kosong', 'updated' => 0];
    }

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) {
            return ['success' => false, 'error' => 'Google Sheets client tidak tersedia', 'updated' => 0];
        }

        // Preload seluruh sheet yang dibutuhkan dalam 1 batch
        $client->preloadSheets(['Assets', 'Cabang', 'Divisi', 'Karyawan', 'Asset_QR_Tokens'], false);

        $karRows = $client->getSheetData('Karyawan');
        $karNameToId = [];
        foreach ($karRows as $kr) {
            $kName = strtolower(trim((string)($kr['nama_karyawan'] ?? $kr['nama'] ?? '')));
            if ($kName !== '') {
                $karNameToId[$kName] = (int)($kr['id'] ?? 0);
            }
        }

        $allAssets = $client->getSheetData('Assets');
        $assetRowMap = [];
        foreach ($allAssets as $a) {
            $aid = (int)($a['id'] ?? 0);
            if ($aid > 0) {
                $assetRowMap[$aid] = $a;
            }
        }

        $qrRows = $client->getSheetData('Asset_QR_Tokens');
        $qrRowMap = [];
        foreach ($qrRows as $q) {
            $aid = (int)($q['asset_id'] ?? 0);
            if ($aid > 0) {
                $qrRowMap[$aid] = $q;
            }
        }

        $valueRanges = [];
        $newKaryawanRows = [];
        $nextKarId = count($karRows) + 1;
        $updatedCount = 0;

        foreach ($assetsData as $aid => $row) {
            $aid = (int)$aid;
            if ($aid <= 0 || !isset($assetRowMap[$aid])) continue;

            $old = $assetRowMap[$aid];
            $rowNum = (int)($old['_row_num'] ?? 0);
            if ($rowNum <= 1) continue;

            $merk = trim((string)($row['merk'] ?? $old['merk'] ?? ''));
            $model = trim((string)($row['model'] ?? $old['model'] ?? ''));
            $sn = trim((string)($row['serial_number'] ?? $old['serial_number'] ?? ''));
            $idKat = (int)($row['id_kategori'] ?? $old['id_kategori'] ?? 0);
            $idCab = (int)($row['id_cabang'] ?? $old['id_cabang'] ?? 0);
            $idDiv = (int)($row['id_divisi'] ?? $old['id_divisi'] ?? 0);
            $status = trim((string)($row['status'] ?? $old['status'] ?? 'Aktif')) ?: 'Aktif';
            $ip = trim((string)($row['ip_address'] ?? $old['ip_address'] ?? $old['ip'] ?? ''));
            $printer = trim((string)($row['printer'] ?? $old['printer'] ?? ''));
            $placement = trim((string)($row['placement_label'] ?? 'Bodi Casing')) ?: 'Bodi Casing';
            $namaKar = trim((string)($row['nama_karyawan'] ?? ''));
            $ket = trim((string)($row['keterangan'] ?? $old['keterangan'] ?? ''));

            $idKar = (int)($old['id_karyawan'] ?? 0);
            if ($namaKar !== '') {
                $lowerKar = strtolower($namaKar);
                if (isset($karNameToId[$lowerKar])) {
                    $idKar = $karNameToId[$lowerKar];
                } else {
                    $idKar = $nextKarId++;
                    $karNameToId[$lowerKar] = $idKar;
                    $newKaryawanRows[] = [$idKar, $namaKar, $idCab, $idDiv];
                }
            }

            $kode = trim((string)($old['kode_inventaris'] ?? ''));
            if ($kode === '') {
                $kode = sprintf('INV-IT-%03d', $aid);
            }

            $assetRow = [
                $aid,
                sheet_cell_text($kode),
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

            $valueRanges[] = [
                'range' => "Assets!A{$rowNum}:M{$rowNum}",
                'values' => [$assetRow]
            ];

            if (isset($qrRowMap[$aid])) {
                $qrRowNum = (int)($qrRowMap[$aid]['_row_num'] ?? 0);
                if ($qrRowNum > 1) {
                    $valueRanges[] = [
                        'range' => "Asset_QR_Tokens!D{$qrRowNum}",
                        'values' => [[$placement]]
                    ];
                }
            }

            $updatedCount++;
        }

        // Tambah karyawan baru jika ada
        if (!empty($newKaryawanRows)) {
            $client->appendValues('Karyawan!A:D', $newKaryawanRows);
        }

        // Eksekusi seluruh baris dalam 1 kali HTTP request batchUpdate
        if (!empty($valueRanges)) {
            $batchOk = $client->batchUpdateValues($valueRanges);
            if (!$batchOk) {
                return ['success' => false, 'error' => $client->getLastError() ?: 'Gagal menyimpan ke Google Sheets', 'updated' => 0];
            }
        }

        map_sheets_assets(true);
        return ['success' => true, 'updated' => $updatedCount];
    }

    // MySQL Mode
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $updatedCount = 0;
        $updSt = $pdo->prepare("UPDATE assets SET merk = ?, model = ?, serial_number = ?, id_kategori = ?, id_cabang = ?, id_divisi = ?, status = ?, ip_address = ?, printer = ?, keterangan = ?, updated_at = NOW() WHERE id = ?");
        $updPlacement = $pdo->prepare("UPDATE asset_qr_tokens SET placement_label = ? WHERE asset_id = ?");

        foreach ($assetsData as $aid => $row) {
            $aid = (int)$aid;
            if ($aid <= 0) continue;

            $merk = trim((string)($row['merk'] ?? ''));
            $model = trim((string)($row['model'] ?? ''));
            $sn = trim((string)($row['serial_number'] ?? ''));
            $idKat = (int)($row['id_kategori'] ?? 0);
            $idCab = (int)($row['id_cabang'] ?? 0);
            $idDiv = (int)($row['id_divisi'] ?? 0);
            $status = trim((string)($row['status'] ?? 'Aktif')) ?: 'Aktif';
            $ip = trim((string)($row['ip_address'] ?? ''));
            $printer = trim((string)($row['printer'] ?? ''));
            $placement = trim((string)($row['placement_label'] ?? 'Bodi Casing')) ?: 'Bodi Casing';
            $ket = trim((string)($row['keterangan'] ?? ''));

            $updSt->execute([$merk, $model, $sn, $idKat, $idCab, $idDiv, $status, $ip, $printer, $ket, $aid]);
            $updPlacement->execute([$placement, $aid]);
            $updatedCount++;
        }

        $pdo->commit();
        return ['success' => true, 'updated' => $updatedCount];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage(), 'updated' => 0];
    }
}


