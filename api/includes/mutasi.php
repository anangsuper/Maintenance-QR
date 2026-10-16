<?php
/**
 * Modul Mutasi & Serah Terima Aset (BAST) Bank Mitra
 * Mendukung Aset Komputer (IT) & Kartu Inventaris Non-Komputer (SDM)
 */

function ensure_mutasi_schema(): void {
    static $ensured = false;
    if ($ensured) return;
    $ensured = true;

    if (!is_google_cloud_mode()) {
        try {
            db()->exec("
                CREATE TABLE IF NOT EXISTS asset_mutations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nomor_bast VARCHAR(60) NOT NULL,
                    tanggal_mutasi DATETIME NOT NULL,
                    asset_id INT NOT NULL,
                    asset_type VARCHAR(20) NOT NULL DEFAULT 'komputer',
                    kode_aset VARCHAR(100) NOT NULL,
                    nama_aset VARCHAR(255) NOT NULL,
                    serial_number VARCHAR(100) DEFAULT '',
                    id_cabang_asal INT DEFAULT 0,
                    cabang_asal VARCHAR(100) NOT NULL,
                    id_cabang_tujuan INT DEFAULT 0,
                    cabang_tujuan VARCHAR(100) NOT NULL,
                    ruangan_asal VARCHAR(100) DEFAULT '',
                    ruangan_tujuan VARCHAR(100) DEFAULT '',
                    pengguna_asal VARCHAR(100) DEFAULT '',
                    pengguna_tujuan VARCHAR(100) DEFAULT '',
                    divisi_asal VARCHAR(100) DEFAULT '',
                    divisi_tujuan VARCHAR(100) DEFAULT '',
                    alasan_mutasi VARCHAR(255) NOT NULL,
                    keterangan TEXT,
                    petugas_user_id INT DEFAULT 0,
                    petugas_nama VARCHAR(100) NOT NULL,
                    status VARCHAR(50) DEFAULT 'Selesai',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (asset_id),
                    INDEX (nomor_bast)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        } catch (Throwable $e) {
            // Abaikan jika tabel sudah ada atau tidak ada permission create table
        }
    } else {
        $client = google_sheets_v4_client();
        if ($client) {
            $client->createSheetIfNotExists('Asset_Mutations');
            $header = $client->getValues('Asset_Mutations!A1:T1');
            if (empty($header)) {
                $client->appendValues('Asset_Mutations!A:T', [[
                    'id', 'nomor_bast', 'tanggal_mutasi', 'asset_id', 'asset_type',
                    'kode_aset', 'nama_aset', 'serial_number', 'cabang_asal', 'cabang_tujuan',
                    'ruangan_asal', 'ruangan_tujuan', 'pengguna_asal', 'pengguna_tujuan',
                    'divisi_asal', 'divisi_tujuan', 'alasan_mutasi', 'keterangan', 'petugas_nama', 'created_at'
                ]], 'USER_ENTERED');
            }
        }
    }
}

function resolve_cabang_match(?string $noRek, ?string $lokasi = '', ?string $rawCabang = '', ?array $cabangMaster = null): string {
    if ($cabangMaster === null) {
        $cabangMaster = get_cabang_list();
    }

    $cleanRek = trim((string)$noRek);
    $cleanLok = strtolower(trim((string)$lokasi));
    $cleanRaw = strtolower(trim((string)$rawCabang));

    // 1. Ekstrak prefix 2 digit nomor rekening (misal: 01.5.00423 -> 01)
    $prefix = '';
    if (preg_match('/^(\d{2})[\.\-]/', $cleanRek, $m)) {
        $prefix = $m[1];
    }

    // 2. Petakan prefix atau kata kunci ke pola cabang
    $pattern = '';
    if ($prefix === '01') {
        $pattern = 'pusat|kpo';
    } elseif ($prefix === '02') {
        $pattern = 'batulicin';
    } elseif ($prefix === '03') {
        $pattern = 'martapura';
    } elseif ($prefix === '04') {
        $pattern = 'tanjung';
    } elseif ($prefix === '05') {
        $pattern = 'handil';
    } else {
        $combined = $cleanLok . ' ' . $cleanRaw;
        if (preg_match('/(pusat|kpo)/i', $combined)) {
            $pattern = 'pusat|kpo';
        } elseif (preg_match('/batulicin/i', $combined)) {
            $pattern = 'batulicin';
        } elseif (preg_match('/martapura/i', $combined)) {
            $pattern = 'martapura';
        } elseif (preg_match('/tanjung/i', $combined)) {
            $pattern = 'tanjung';
        } elseif (preg_match('/handil/i', $combined)) {
            $pattern = 'handil';
        } elseif (preg_match('/kas/i', $combined)) {
            $pattern = 'kas';
        }
    }

    // 3. Cari nama cabang resmi dari daftar master cabang
    if ($pattern !== '') {
        foreach ($cabangMaster as $c) {
            $cName = (string)($c['nama_cabang'] ?? $c['nama'] ?? '');
            if (preg_match('/' . $pattern . '/i', $cName)) {
                return $cName;
            }
        }
    }

    // 4. Jika rawCabang sudah sesuai dengan salah satu master cabang, gunakan itu
    if ($rawCabang !== '') {
        foreach ($cabangMaster as $c) {
            $cName = (string)($c['nama_cabang'] ?? $c['nama'] ?? '');
            if (strcasecmp($cName, $rawCabang) === 0) {
                return $cName;
            }
        }
        return $rawCabang;
    }

    $first = $cabangMaster[0]['nama_cabang'] ?? $cabangMaster[0]['nama'] ?? 'Kantor Pusat Operasional';
    return $first;
}

function romawi_bulan(int $m): string {
    $romawi = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
    ];
    return $romawi[$m] ?? 'I';
}

function generate_nomor_bast(): string {
    $m = (int)date('n');
    $y = (int)date('Y');
    $romawi = romawi_bulan($m);

    $all = get_asset_mutations();
    $countThisYear = 1;
    foreach ($all as $item) {
        $date = $item['tanggal_mutasi'] ?? $item['created_at'] ?? '';
        if ($date && str_starts_with($date, (string)$y)) {
            $countThisYear++;
        }
    }

    $numPadded = str_pad((string)$countThisYear, 3, '0', STR_PAD_LEFT);
    return "BAST/BMR/{$romawi}/{$y}/{$numPadded}";
}

function get_asset_mutations(?array $filters = []): array {
    ensure_mutasi_schema();

    $results = [];
    if (!is_google_cloud_mode()) {
        try {
            $sql = "SELECT * FROM asset_mutations ORDER BY id DESC";
            $st = db()->query($sql);
            if ($st) {
                $results = $st->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {
            $results = [];
        }
    } else {
        $client = google_sheets_v4_client();
        if ($client) {
            $rows = $client->getSheetData('Asset_Mutations');
            foreach ($rows as $r) {
                $id = (int)($r['id'] ?? $r['col_0'] ?? 0);
                if ($id <= 0) continue;
                $results[] = [
                    'id'              => $id,
                    'nomor_bast'      => (string)($r['nomor_bast'] ?? $r['col_1'] ?? ''),
                    'tanggal_mutasi'  => (string)($r['tanggal_mutasi'] ?? $r['col_2'] ?? ''),
                    'asset_id'        => (int)($r['asset_id'] ?? $r['col_3'] ?? 0),
                    'asset_type'      => (string)($r['asset_type'] ?? $r['col_4'] ?? 'komputer'),
                    'kode_aset'       => (string)($r['kode_aset'] ?? $r['col_5'] ?? ''),
                    'nama_aset'       => (string)($r['nama_aset'] ?? $r['col_6'] ?? ''),
                    'serial_number'   => (string)($r['serial_number'] ?? $r['col_7'] ?? ''),
                    'cabang_asal'     => (string)($r['cabang_asal'] ?? $r['col_8'] ?? ''),
                    'cabang_tujuan'   => (string)($r['cabang_tujuan'] ?? $r['col_9'] ?? ''),
                    'ruangan_asal'    => (string)($r['ruangan_asal'] ?? $r['col_10'] ?? ''),
                    'ruangan_tujuan'  => (string)($r['ruangan_tujuan'] ?? $r['col_11'] ?? ''),
                    'pengguna_asal'   => (string)($r['pengguna_asal'] ?? $r['col_12'] ?? ''),
                    'pengguna_tujuan' => (string)($r['pengguna_tujuan'] ?? $r['col_13'] ?? ''),
                    'divisi_asal'     => (string)($r['divisi_asal'] ?? $r['col_14'] ?? ''),
                    'divisi_tujuan'   => (string)($r['divisi_tujuan'] ?? $r['col_15'] ?? ''),
                    'alasan_mutasi'   => (string)($r['alasan_mutasi'] ?? $r['col_16'] ?? ''),
                    'keterangan'      => (string)($r['keterangan'] ?? $r['col_17'] ?? ''),
                    'petugas_nama'    => (string)($r['petugas_nama'] ?? $r['col_18'] ?? ''),
                    'created_at'      => (string)($r['created_at'] ?? $r['col_19'] ?? '')
                ];
            }
            usort($results, fn($a, $b) => $b['id'] <=> $a['id']);
        }
    }

    if (!empty($filters['q'])) {
        $q = strtolower(trim((string)$filters['q']));
        $results = array_filter($results, function($item) use ($q) {
            return str_contains(strtolower($item['kode_aset']), $q)
                || str_contains(strtolower($item['nama_aset']), $q)
                || str_contains(strtolower($item['nomor_bast']), $q)
                || str_contains(strtolower($item['pengguna_tujuan']), $q)
                || str_contains(strtolower($item['cabang_tujuan']), $q);
        });
    }

    if (!empty($filters['asset_id'])) {
        $aid = (int)$filters['asset_id'];
        $results = array_filter($results, fn($item) => (int)$item['asset_id'] === $aid);
    }

    return array_values($results);
}

function get_asset_mutation_by_id(int $id): ?array {
    if ($id <= 0) return null;
    $all = get_asset_mutations();
    foreach ($all as $item) {
        if ((int)$item['id'] === $id) {
            return $item;
        }
    }
    return null;
}

function process_asset_mutation(array $data): array {
    ensure_mutasi_schema();

    $assetId = (int)($data['asset_id'] ?? 0);
    $assetType = trim((string)($data['asset_type'] ?? 'komputer'));
    if ($assetId <= 0) {
        return ['success' => false, 'error' => 'Aset yang akan dimutasi tidak valid.'];
    }

    $alasan = trim((string)($data['alasan_mutasi'] ?? ''));
    if ($alasan === '') {
        return ['success' => false, 'error' => 'Alasan mutasi wajib diisi.'];
    }

    $cabangTujuan = trim((string)($data['cabang_tujuan'] ?? ''));
    $ruanganTujuan = trim((string)($data['ruangan_tujuan'] ?? ''));
    $penggunaTujuan = trim((string)($data['pengguna_tujuan'] ?? ''));
    $divisiTujuan = trim((string)($data['divisi_tujuan'] ?? ''));
    $keterangan = trim((string)($data['keterangan'] ?? ''));
    $petugasNama = trim((string)($data['petugas_nama'] ?? ($_SESSION['nama'] ?? 'Petugas IT')));
    $petugasId = (int)($_SESSION['user_id'] ?? 0);

    $kodeAset = '';
    $namaAset = '';
    $sn = '';
    $cabangAsal = '';
    $ruanganAsal = '';
    $penggunaAsal = '';
    $divisiAsal = '';
    $idCabangAsal = 0;
    $idCabangTujuan = (int)($data['id_cabang_tujuan'] ?? 0);

    if ($assetType === 'komputer') {
        $asset = get_asset_by_id($assetId);
        if (!$asset) {
            return ['success' => false, 'error' => 'Data perangkat komputer tidak ditemukan.'];
        }
        $kodeAset = (string)($asset['kode_inventaris'] ?? 'ASET-' . $assetId);
        $namaAset = asset_title($asset);
        $sn = (string)($asset['serial_number'] ?? '');
        $cabangAsal = (string)($asset['cabang_nama'] ?? 'Pusat');
        $idCabangAsal = (int)($asset['id_cabang'] ?? 0);
        $ruanganAsal = (string)($asset['ruangan'] ?? $asset['lokasi'] ?? '');
        $penggunaAsal = (string)($asset['karyawan_nama'] ?? 'Umum');
        $divisiAsal = (string)($asset['divisi_nama'] ?? '');

        // Cari ID cabang tujuan jika nama cabang diberikan
        if ($idCabangTujuan <= 0 && $cabangTujuan !== '') {
            foreach (get_cabang_list() as $c) {
                if (strcasecmp($c['nama_cabang'] ?? '', $cabangTujuan) === 0) {
                    $idCabangTujuan = (int)$c['id'];
                    break;
                }
            }
        }

        // Cari ID divisi tujuan
        $idDivisiTujuan = 0;
        if ($divisiTujuan !== '') {
            foreach (get_divisi_list() as $d) {
                if (strcasecmp($d['nama_divisi'] ?? '', $divisiTujuan) === 0) {
                    $idDivisiTujuan = (int)$d['id'];
                    break;
                }
            }
        }

        // Update data aset komputer
        $updatePayload = [
            'nama_karyawan' => $penggunaTujuan ?: $penggunaAsal,
            'keterangan'    => "Dimutasi dari {$cabangAsal} ke {$cabangTujuan} pada " . date('d/m/Y') . ($keterangan ? " ({$keterangan})" : '')
        ];
        if ($idCabangTujuan > 0) {
            $updatePayload['id_cabang'] = $idCabangTujuan;
        }
        if ($idDivisiTujuan > 0) {
            $updatePayload['id_divisi'] = $idDivisiTujuan;
        }

        update_asset($assetId, $updatePayload);

    } else {
        // Aset inventaris kartu CR80
        $card = get_inventaris_kartu_by_id($assetId);
        if (!$card) {
            return ['success' => false, 'error' => 'Data kartu inventaris tidak ditemukan.'];
        }
        $kodeAset = (string)($card['nomor_rekening'] ?? 'INV-' . $assetId);
        $namaAset = (string)($card['nama_barang'] ?? 'Aset Inventaris');
        $sn = (string)($card['nomor_seri_inventaris'] ?? '');
        $cabangAsal = resolve_cabang_match($kodeAset, (string)($card['lokasi'] ?? ''), (string)($card['cabang_nama'] ?? ''));
        $ruanganAsal = (string)(!empty($card['ruangan']) ? $card['ruangan'] : (!empty($card['lokasi']) ? $card['lokasi'] : '-'));
        $penggunaAsal = (string)(!empty($card['pengguna']) ? $card['pengguna'] : 'Umum / Pool');
        $divisiAsal = (string)(!empty($card['golongan']) ? $card['golongan'] : ($card['jenis_inventaris'] ?? '-'));

        // Update data kartu inventaris
        $updateCardData = [
            'nomor_rekening' => $kodeAset,
            'nama_barang'    => $namaAset,
            'ruangan'        => $ruanganTujuan ?: $ruanganAsal,
            'keterangan'     => "Mutasi ke {$cabangTujuan} (" . date('d/m/Y') . ")" . ($keterangan ? ": {$keterangan}" : ''),
            'lokasi'         => $cabangTujuan ?: $cabangAsal,
            'pengguna'       => $penggunaTujuan ?: $penggunaAsal
        ];
        $golonganTujuan = trim((string)($data['golongan_tujuan'] ?? ''));
        if ($golonganTujuan !== '') {
            $updateCardData['golongan'] = $golonganTujuan;
        }
        update_inventaris_kartu($assetId, $updateCardData);
    }

    $nomorBast = generate_nomor_bast();
    $now = date('Y-m-d H:i:s');

    $all = get_asset_mutations();
    $newId = 1;
    if (!empty($all)) {
        $maxId = max(array_column($all, 'id'));
        $newId = $maxId + 1;
    }

    $record = [
        'id'              => $newId,
        'nomor_bast'      => $nomorBast,
        'tanggal_mutasi'  => $now,
        'asset_id'        => $assetId,
        'asset_type'      => $assetType,
        'kode_aset'       => $kodeAset,
        'nama_aset'       => $namaAset,
        'serial_number'   => $sn,
        'cabang_asal'     => $cabangAsal,
        'cabang_tujuan'   => $cabangTujuan ?: $cabangAsal,
        'ruangan_asal'    => $ruanganAsal,
        'ruangan_tujuan'  => $ruanganTujuan ?: $ruanganAsal,
        'pengguna_asal'   => $penggunaAsal,
        'pengguna_tujuan' => $penggunaTujuan ?: $penggunaAsal,
        'divisi_asal'     => $divisiAsal,
        'divisi_tujuan'   => $divisiTujuan ?: $divisiAsal,
        'alasan_mutasi'   => $alasan,
        'keterangan'      => $keterangan,
        'petugas_nama'    => $petugasNama,
        'created_at'      => $now
    ];

    if (!is_google_cloud_mode()) {
        try {
            $st = db()->prepare("
                INSERT INTO asset_mutations (
                    id, nomor_bast, tanggal_mutasi, asset_id, asset_type,
                    kode_aset, nama_aset, serial_number, id_cabang_asal, cabang_asal,
                    id_cabang_tujuan, cabang_tujuan, ruangan_asal, ruangan_tujuan,
                    pengguna_asal, pengguna_tujuan, divisi_asal, divisi_tujuan,
                    alasan_mutasi, keterangan, petugas_user_id, petugas_nama, status, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, 'Selesai', ?
                )
            ");
            $st->execute([
                $newId, $nomorBast, $now, $assetId, $assetType,
                $kodeAset, $namaAset, $sn, $idCabangAsal, $cabangAsal,
                $idCabangTujuan, $record['cabang_tujuan'], $ruanganAsal, $record['ruangan_tujuan'],
                $penggunaAsal, $record['pengguna_tujuan'], $divisiAsal, $record['divisi_tujuan'],
                $alasan, $keterangan, $petugasId, $petugasNama, $now
            ]);
        } catch (Throwable $e) {
            // fallback if id conflict
        }
    } else {
        $client = google_sheets_v4_client();
        if ($client) {
            $client->appendValues('Asset_Mutations!A:T', [[
                $newId, $nomorBast, $now, $assetId, $assetType,
                $kodeAset, $namaAset, $sn, $cabangAsal, $record['cabang_tujuan'],
                $ruanganAsal, $record['ruangan_tujuan'], $penggunaAsal, $record['pengguna_tujuan'],
                $divisiAsal, $record['divisi_tujuan'], $alasan, $keterangan, $petugasNama, $now
            ]], 'USER_ENTERED');
        }
    }

    // Catat log audit mutasi resmi
    record_audit_log('MUTASI', 'MUTASI_ASET', $assetId, $kodeAset, "Mutasi {$assetType}: {$namaAset} dari {$cabangAsal} ke {$record['cabang_tujuan']} ({$nomorBast})", [
        'nomor_bast'      => $nomorBast,
        'alasan'          => $alasan,
        'pengguna_tujuan' => $record['pengguna_tujuan'],
        'cabang_tujuan'   => $record['cabang_tujuan']
    ]);

    return [
        'success'    => true,
        'id'         => $newId,
        'nomor_bast' => $nomorBast,
        'message'    => "Aset {$kodeAset} berhasil dimutasi ke {$record['cabang_tujuan']} dengan nomor BAST: {$nomorBast}."
    ];
}
