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
            'lokasi' => 'KPO',
            'pengguna' => 'Kas / Teller',
            'created_at' => '2026-09-08 03:15:02'
        ],
        [
            'id' => 12,
            'nomor_rekening' => '01.05.0492',
            'nama_barang' => 'LAPTOP MSI THIN STAFF IT',
            'tanggal_perolehan' => '2026-08-26',
            'barcode_data' => 'https://canva.link/axqdgjgztd1uu3r',
            'lokasi' => 'Ruang IT',
            'pengguna' => 'Staff IT',
            'created_at' => '2026-09-08 03:12:05'
        ],
        [
            'id' => 11,
            'nomor_rekening' => '01.05.0302',
            'nama_barang' => 'Roller Blind KPO',
            'tanggal_perolehan' => '2022-05-23',
            'barcode_data' => 'https://canva.link/q5kkycw9vtomob7',
            'lokasi' => 'KPO',
            'pengguna' => 'Operasional KPO',
            'created_at' => '2026-08-11 05:13:00'
        ],
        [
            'id' => 10,
            'nomor_rekening' => '01.05.0359',
            'nama_barang' => 'ROLLER BLIND U/ RUANG PERPUS',
            'tanggal_perolehan' => '2023-01-31',
            'barcode_data' => 'https://canva.link/t9fcx334gfbmhrw',
            'lokasi' => 'Ruang Perpustakaan',
            'pengguna' => 'Umum / Perpustakaan',
            'created_at' => '2026-08-11 05:11:48'
        ],
        [
            'id' => 9,
            'nomor_rekening' => '03.05.1973',
            'nama_barang' => 'KIPAS ANGIN EMBUN',
            'tanggal_perolehan' => '2026-02-27',
            'barcode_data' => 'https://canva.link/ko9ckx76pbojj2y',
            'lokasi' => 'KPO / Operasional',
            'pengguna' => 'Umum / Pool',
            'created_at' => '2026-08-10 05:00:02'
        ]
    ];
}

/**
 * Format tanggal ke bahasa Indonesia (contoh: 26 September 2026)
 */
function format_indo_date(?string $dateStr): string {
    if (empty($dateStr) || $dateStr === '0000-00-00') return '-';
    $ts = strtotime($dateStr);
    if (!$ts) return (string)$dateStr;
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
        $ts = strtotime($tgl);
        if ($ts) {
            $tglPart = date('dmY', $ts);
        }
    }
    return $cleanRek . $tglPart;
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
                $headers = ['id', 'nomor_rekening', 'nama_barang', 'tanggal_perolehan', 'barcode_data', 'lokasi', 'pengguna', 'created_at'];
                $appendData = [$headers];
                foreach ($defaults as $d) {
                    $appendData[] = [
                        $d['id'],
                        $d['nomor_rekening'],
                        $d['nama_barang'],
                        $d['tanggal_perolehan'],
                        $d['barcode_data'],
                        $d['lokasi'],
                        $d['pengguna'],
                        $d['created_at']
                    ];
                }
                $client->appendValues('inventaris_kartu!A:H', $appendData);
                return $defaults;
            }

            $normalized = [];
            foreach ($rows as $r) {
                $id = (int)($r['id'] ?? 0);
                if ($id <= 0) continue;
                $normalized[] = [
                    'id'                => $id,
                    'nomor_rekening'   => trim((string)($r['nomor_rekening'] ?? '')),
                    'nama_barang'      => trim((string)($r['nama_barang'] ?? '')),
                    'tanggal_perolehan'=> trim((string)($r['tanggal_perolehan'] ?? '')),
                    'barcode_data'     => trim((string)($r['barcode_data'] ?? '')),
                    'lokasi'           => trim((string)($r['lokasi'] ?? 'KPO / Operasional')),
                    'pengguna'         => trim((string)($r['pengguna'] ?? 'Umum / Pool')),
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
                lokasi VARCHAR(150) NULL DEFAULT 'KPO / Operasional',
                pengguna VARCHAR(150) NULL DEFAULT 'Umum / Pool',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $st = $pdo->query("SELECT * FROM inventaris_kartu ORDER BY id DESC");
        $rows = $st ? $st->fetchAll() : [];

        if (empty($rows)) {
            $defaults = default_inventaris_kartu_rows();
            $ins = $pdo->prepare("
                INSERT INTO inventaris_kartu (id, nomor_rekening, nama_barang, tanggal_perolehan, barcode_data, lokasi, pengguna, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($defaults as $d) {
                $ins->execute([
                    $d['id'],
                    $d['nomor_rekening'],
                    $d['nama_barang'],
                    $d['tanggal_perolehan'],
                    $d['barcode_data'],
                    $d['lokasi'],
                    $d['pengguna'],
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
    $tgl = trim((string)($data['tanggal_perolehan'] ?? date('Y-m-d')));
    $barcode = trim((string)($data['barcode_data'] ?? ''));
    $lokasi = trim((string)($data['lokasi'] ?? 'KPO / Operasional')) ?: 'KPO / Operasional';
    $pengguna = trim((string)($data['pengguna'] ?? 'Umum / Pool')) ?: 'Umum / Pool';
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
            $client->appendValues('inventaris_kartu!A:H', [[
                $newId, $rek, $nama, $tgl, $barcode, $lokasi, $pengguna, $nowStr
            ]]);
            return ['success' => true, 'id' => $newId];
        }
    }

    try {
        $ins = db()->prepare("
            INSERT INTO inventaris_kartu (nomor_rekening, nama_barang, tanggal_perolehan, barcode_data, lokasi, pengguna, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([$rek, $nama, $tgl, $barcode, $lokasi, $pengguna, $nowStr]);
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
    $tgl = trim((string)($data['tanggal_perolehan'] ?? date('Y-m-d')));
    $barcode = trim((string)($data['barcode_data'] ?? ''));
    $lokasi = trim((string)($data['lokasi'] ?? 'KPO / Operasional')) ?: 'KPO / Operasional';
    $pengguna = trim((string)($data['pengguna'] ?? 'Umum / Pool')) ?: 'Umum / Pool';

    if ($id <= 0 || $rek === '' || $nama === '') return false;

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if ($client) {
            $rows = $client->getSheetData('inventaris_kartu', true);
            foreach ($rows as $idx => $r) {
                if ((int)($r['id'] ?? 0) === $id) {
                    $rowNum = $idx + 2;
                    $client->updateValues("inventaris_kartu!B{$rowNum}:G{$rowNum}", [[
                        $rek, $nama, $tgl, $barcode, $lokasi, $pengguna
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
            SET nomor_rekening = ?, nama_barang = ?, tanggal_perolehan = ?, barcode_data = ?, lokasi = ?, pengguna = ?
            WHERE id = ?
        ");
        return $st->execute([$rek, $nama, $tgl, $barcode, $lokasi, $pengguna, $id]);
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
                    $client->clearValues("inventaris_kartu!A{$rowNum}:H{$rowNum}");
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
