<?php
/**
 * Modul Database & CRUD: Kartu Inventaris (CR80)
 * Tabel terpisah dari assets & asset_qr_tokens
 * Mendukung mode Hybrid: Google Sheets API v4 / MySQL / Fallback Local Storage
 */

function default_inventaris_kartu_rows(): array {
    return [
        [
            'id' => 13,
            'nomor_rekening' => '01.05.0493',
            'nama_barang' => 'PRINTER EPSON L3211 KAS',
            'tanggal_perolehan' => '2026-09-26',
            'barcode_data' => 'https://canva.link/tyu3nb63s2yjau9',
            'lokasi' => 'Kantor Pusat (KPO)',
            'created_at' => '2026-09-08 03:15:02'
        ],
        [
            'id' => 12,
            'nomor_rekening' => '01.05.0492',
            'nama_barang' => 'LAPTOP MSI THIN STAFF IT',
            'tanggal_perolehan' => '2026-08-26',
            'barcode_data' => 'https://canva.link/axqdgjgztd1uu3r',
            'lokasi' => 'Kantor Pusat (KPO)',
            'created_at' => '2026-09-08 03:12:05'
        ],
        [
            'id' => 11,
            'nomor_rekening' => '01.05.0302',
            'nama_barang' => 'Roller Blind KPO',
            'tanggal_perolehan' => '2022-05-23',
            'barcode_data' => 'https://canva.link/q5kkycw9vtomob7',
            'lokasi' => 'Kantor Pusat (KPO)',
            'created_at' => '2026-08-11 05:13:00'
        ],
        [
            'id' => 10,
            'nomor_rekening' => '01.05.0359',
            'nama_barang' => 'ROLLER BLIND U/ RUANG PERPUS',
            'tanggal_perolehan' => '2023-01-31',
            'barcode_data' => 'https://canva.link/t9fcx334gfbmhrw',
            'lokasi' => 'Kantor Pusat (KPO)',
            'created_at' => '2026-08-11 05:11:48'
        ],
        [
            'id' => 9,
            'nomor_rekening' => '03.05.1973',
            'nama_barang' => 'KIPAS ANGIN EMBUN',
            'tanggal_perolehan' => '2026-02-27',
            'barcode_data' => 'https://canva.link/ko9ckx76pbojj2y',
            'lokasi' => 'Martapura / Operasional',
            'created_at' => '2026-08-10 05:00:02'
        ]
    ];
}

/**
 * Format tanggal kartu inventaris ke format dd/mm/yyyy (contoh: 26/09/2026)
 */
function format_card_date(?string $dateStr): string {
    if (empty($dateStr) || $dateStr === '0000-00-00') return '-';
    $clean = trim((string)$dateStr);
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $clean, $m)) {
        return sprintf('%02d/%02d/%04d', $m[1], $m[2], $m[3]);
    }
    if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $clean, $m)) {
        return sprintf('%02d/%02d/%04d', $m[3], $m[2], $m[1]);
    }
    $ts = strtotime($clean);
    return $ts ? date('d/m/Y', $ts) : $clean;
}

/**
 * Normalisasi format tanggal input (dd/mm/yyyy atau yyyy-mm-dd) ke format database standar Y-m-d
 */
function normalize_date_to_db(?string $dateStr): string {
    $clean = trim((string)$dateStr);
    if ($clean === '' || $clean === '0000-00-00') return date('Y-m-d');
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $clean, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $clean)) {
        return $clean;
    }
    $ts = strtotime($clean);
    return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
}

/**
 * Format tanggal ke bahasa Indonesia (contoh: 26 September 2026)
 */
function format_indo_date(?string $dateStr): string {
    if (empty($dateStr) || $dateStr === '0000-00-00') return '-';
    $clean = trim((string)$dateStr);
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $clean, $m)) {
        $ts = mktime(0, 0, 0, (int)$m[2], (int)$m[1], (int)$m[3]);
    } else {
        $ts = strtotime($clean);
    }
    if (!$ts) return $clean;
    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $d = date('j', $ts);
    $m = (int)date('n', $ts);
    $y = date('Y', $ts);
    return $d . ' ' . ($bulan[$m] ?? date('F', $ts)) . ' ' . $y;
}

/**
 * Generate Nomor Asset (Gabungan), misal: 01.05.0493 + 2026-09-26 -> 0105049326092026
 */
function get_nomor_asset_gabungan(string $noRek, ?string $tgl): string {
    $cleanRek = preg_replace('/[^0-9]/', '', $noRek);
    $tglPart = '';
    if (!empty($tgl) && $tgl !== '0000-00-00') {
        $cleanTgl = trim((string)$tgl);
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $cleanTgl, $m)) {
            $tglPart = sprintf('%02d%02d%04d', $m[1], $m[2], $m[3]);
        } else if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $cleanTgl, $m)) {
            $tglPart = sprintf('%02d%02d%04d', $m[3], $m[2], $m[1]);
        } else {
            $ts = strtotime($cleanTgl);
            if ($ts) {
                $tglPart = date('dmY', $ts);
            }
        }
    }
    return $cleanRek . $tglPart;
}

/**
 * Pemetaan Resmi 5 Kantor Cabang Standar
 * 01: Kantor Pusat (KPO)
 * 02: Batulicin
 * 03: Martapura
 * 04: Tanjung
 * 05: Handil Bakti
 */
function get_standard_cabang_list(): array {
    return [
        ['code' => '01', 'name' => 'Kantor Pusat', 'lokasi' => 'Kantor Pusat (KPO)'],
        ['code' => '02', 'name' => 'Batulicin', 'lokasi' => 'Batulicin'],
        ['code' => '03', 'name' => 'Martapura', 'lokasi' => 'Martapura'],
        ['code' => '04', 'name' => 'Tanjung', 'lokasi' => 'Tanjung'],
        ['code' => '05', 'name' => 'Handil Bakti', 'lokasi' => 'Handil Bakti'],
    ];
}

function get_standard_cabang_by_code(string $code): ?array {
    $code = trim($code);
    foreach (get_standard_cabang_list() as $c) {
        if ($c['code'] === $code) return $c;
    }
    return null;
}

function get_cabang_from_nomor_rekening(?string $noRek): ?array {
    $clean = trim((string)$noRek);
    if (preg_match('/^(\d{2})[\.\-]/', $clean, $m)) {
        return get_standard_cabang_by_code($m[1]);
    }
    return null;
}

/**
 * Mengambil semua data dari tabel inventaris_kartu
 */
function get_inventaris_kartu_rows(bool $refresh = false): array {
    // 1. Mode Google Sheets v4
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if ($client) {
            $client->createSheetIfNotExists('inventaris_kartu');
            $rows = $client->getSheetData('inventaris_kartu', $refresh);
            if (empty($rows)) {
                $defaults = default_inventaris_kartu_rows();
                $headers = ['id', 'nomor_rekening', 'nama_barang', 'tanggal_perolehan', 'barcode_data', 'lokasi', 'created_at'];
                $appendData = [$headers];
                foreach ($defaults as $d) {
                    $appendData[] = [
                        $d['id'],
                        $d['nomor_rekening'],
                        $d['nama_barang'],
                        $d['tanggal_perolehan'],
                        $d['barcode_data'],
                        $d['lokasi'],
                        $d['created_at']
                    ];
                }
                $client->appendValues('inventaris_kartu!A:G', $appendData);
                return $defaults;
            }

            $normalized = [];
            foreach ($rows as $r) {
                $id = (int)($r['id'] ?? 0);
                if ($id <= 0) continue;
                $rek = trim((string)($r['nomor_rekening'] ?? ''));
                $lokasi = trim((string)($r['lokasi'] ?? ''));
                if ($lokasi === '' || $lokasi === 'KPO' || $lokasi === 'KPO / Operasional') {
                    $cBranch = get_cabang_from_nomor_rekening($rek);
                    $lokasi = $cBranch ? $cBranch['lokasi'] : 'Kantor Pusat (KPO)';
                }

                $normalized[] = [
                    'id'                => $id,
                    'nomor_rekening'   => $rek,
                    'nama_barang'      => trim((string)($r['nama_barang'] ?? '')),
                    'tanggal_perolehan'=> trim((string)($r['tanggal_perolehan'] ?? '')),
                    'barcode_data'     => trim((string)($r['barcode_data'] ?? '')),
                    'lokasi'           => $lokasi,
                    'created_at'       => trim((string)($r['created_at'] ?? ''))
                ];
            }
            if (!empty($normalized)) {
                return $normalized;
            }
        }
    }

    // 2. Mode MySQL
    try {
        $pdo = db();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS inventaris_kartu (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                nomor_rekening VARCHAR(100) NOT NULL,
                nama_barang VARCHAR(255) NOT NULL,
                tanggal_perolehan DATE NOT NULL,
                barcode_data TEXT NOT NULL,
                lokasi VARCHAR(150) NULL DEFAULT 'Kantor Pusat (KPO)',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $st = $pdo->query("SELECT * FROM inventaris_kartu ORDER BY id DESC");
        $rows = $st ? $st->fetchAll() : [];

        if (empty($rows)) {
            $defaults = default_inventaris_kartu_rows();
            $ins = $pdo->prepare("
                INSERT INTO inventaris_kartu (id, nomor_rekening, nama_barang, tanggal_perolehan, barcode_data, lokasi, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($defaults as $d) {
                $ins->execute([
                    $d['id'],
                    $d['nomor_rekening'],
                    $d['nama_barang'],
                    $d['tanggal_perolehan'],
                    $d['barcode_data'],
                    $d['lokasi'],
                    $d['created_at']
                ]);
            }
            return $defaults;
        }

        return $rows;
    } catch (Throwable $e) {
        return default_inventaris_kartu_rows();
    }
}

/**
 * Tambah kartu inventaris baru
 */
function insert_inventaris_kartu(array $data): array {
    $rek = trim((string)($data['nomor_rekening'] ?? ''));
    $nama = trim((string)($data['nama_barang'] ?? ''));
    $tgl = normalize_date_to_db($data['tanggal_perolehan'] ?? '');
    $barcode = trim((string)($data['barcode_data'] ?? ''));
    
    // Tentukan lokasi: gunakan input lokasi jika diisi, atau default berdasarkan cabang
    $lokasi = trim((string)($data['lokasi'] ?? ''));
    if ($lokasi === '') {
        $cBranch = get_cabang_from_nomor_rekening($rek);
        $lokasi = $cBranch ? $cBranch['lokasi'] : 'Kantor Pusat (KPO)';
    }
    
    $nowStr = date('Y-m-d H:i:s');

    if ($rek === '' || $nama === '') {
        return ['success' => false, 'error' => 'Nomor rekening dan nama barang wajib diisi.'];
    }

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if ($client) {
            $existing = get_inventaris_kartu_rows(true);
            $maxId = 0;
            foreach ($existing as $r) {
                if ($r['id'] > $maxId) $maxId = $r['id'];
            }
            $newId = $maxId + 1;
            $client->appendValues('inventaris_kartu!A:G', [[
                $newId, $rek, $nama, $tgl, $barcode, $lokasi, $nowStr
            ]]);
            return ['success' => true, 'id' => $newId];
        }
    }

    try {
        $ins = db()->prepare("
            INSERT INTO inventaris_kartu (nomor_rekening, nama_barang, tanggal_perolehan, barcode_data, lokasi, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([$rek, $nama, $tgl, $barcode, $lokasi, $nowStr]);
        $newId = (int)db()->lastInsertId();
        return ['success' => true, 'id' => $newId];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update kartu inventaris
 */
function update_inventaris_kartu(int $id, array $data): bool {
    $rek = trim((string)($data['nomor_rekening'] ?? ''));
    $nama = trim((string)($data['nama_barang'] ?? ''));
    $tgl = normalize_date_to_db($data['tanggal_perolehan'] ?? '');
    $barcode = trim((string)($data['barcode_data'] ?? ''));
    
    // Tentukan lokasi: gunakan input lokasi jika diisi, atau default berdasarkan cabang
    $lokasi = trim((string)($data['lokasi'] ?? ''));
    if ($lokasi === '') {
        $cBranch = get_cabang_from_nomor_rekening($rek);
        $lokasi = $cBranch ? $cBranch['lokasi'] : 'Kantor Pusat (KPO)';
    }

    if ($id <= 0 || $rek === '' || $nama === '') return false;

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if ($client) {
            $rows = $client->getSheetData('inventaris_kartu', true);
            foreach ($rows as $idx => $r) {
                if ((int)($r['id'] ?? 0) === $id) {
                    $rowNum = $idx + 2;
                    $client->updateValues("inventaris_kartu!B{$rowNum}:F{$rowNum}", [[
                        $rek, $nama, $tgl, $barcode, $lokasi
                    ]]);
                    return true;
                }
            }
        }
        return false;
    }

    try {
        $st = db()->prepare("
            UPDATE inventaris_kartu
            SET nomor_rekening = ?, nama_barang = ?, tanggal_perolehan = ?, barcode_data = ?, lokasi = ?
            WHERE id = ?
        ");
        return $st->execute([$rek, $nama, $tgl, $barcode, $lokasi, $id]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Hapus satu atau banyak kartu inventaris
 */
function delete_inventaris_kartu(array|int $ids): bool {
    $idArray = is_array($ids) ? array_map('intval', $ids) : [(int)$ids];
    $idArray = array_filter($idArray, fn($x) => $x > 0);
    if (empty($idArray)) return false;

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if ($client) {
            $rows = $client->getSheetData('inventaris_kartu', true);
            for ($i = count($rows) - 1; $i >= 0; $i--) {
                $curId = (int)($rows[$i]['id'] ?? 0);
                if (in_array($curId, $idArray, true)) {
                    $rowNum = $i + 2;
                    $client->clearValues("inventaris_kartu!A{$rowNum}:G{$rowNum}");
                }
            }
            return true;
        }
    }

    try {
        $inClause = implode(',', array_fill(0, count($idArray), '?'));
        $st = db()->prepare("DELETE FROM inventaris_kartu WHERE id IN ({$inClause})");
        return $st->execute(array_values($idArray));
    } catch (Throwable $e) {
        return false;
    }
}
