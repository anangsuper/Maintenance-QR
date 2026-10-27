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
 * Format Resmi Kode Inventaris Bank BPR Mitratama: 00.0.00000
 * Contoh: 01.5.00495 (Cabang 01, Kelompok Aktiva 5, No Urut 00495)
 */
function format_kode_inventaris(?string $raw): string {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return '';
    }

    // Jika sudah memiliki pemisah titik
    $parts = explode('.', $raw);
    if (count($parts) === 3) {
        $p1 = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
        $p2 = $parts[1];
        $p3 = $parts[2];
        if (strlen($p2) === 2 && strlen($p3) === 4) {
            $p3 = $p2[1] . $p3;
            $p2 = $p2[0];
        }
        $p3 = str_pad($p3, 5, '0', STR_PAD_LEFT);
        return "{$p1}.{$p2}.{$p3}";
    }

    // Ekstrak hanya digit angka
    $digits = preg_replace('/[^0-9]/', '', $raw);
    if (strlen($digits) >= 8) {
        $p1 = substr($digits, 0, 2);
        $p2 = substr($digits, 2, 1);
        $p3 = substr($digits, 3, 5);
        return "{$p1}.{$p2}.{$p3}";
    } elseif (strlen($digits) >= 3) {
        $p1 = substr($digits, 0, 2);
        $p2 = substr($digits, 2, 1);
        $p3 = str_pad(substr($digits, 3), 5, '0', STR_PAD_LEFT);
        return "{$p1}.{$p2}.{$p3}";
    }

    return $raw;
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
 * Format tanggal khusus pelaporan Excel SDM (dd/mm/yyyy)
 */
function format_excel_date(?string $tgl): string {
    if (empty($tgl) || $tgl === '0000-00-00') {
        return '';
    }
    $clean = trim((string)$tgl);
    if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/', $clean, $m)) {
        return sprintf('%02d/%02d/%04d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }
    if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})/', $clean, $m)) {
        return sprintf('%02d/%02d/%04d', (int)$m[1], (int)$m[2], (int)$m[3]);
    }
    $ts = strtotime($clean);
    return $ts ? date('d/m/Y', $ts) : $clean;
}

/**
 * Menentukan kode singkatan Kantor (KPO, HDB, MTP, BATULICIN, TANJUNG) untuk laporan SDM
 */
function determine_kantor_code(?string $lokasi, ?string $noRek): string {
    $lok = strtoupper(trim((string)$lokasi));
    $rek = trim((string)$noRek);

    // 1. Prioritaskan deteksi cabang dari string lokasi aktual
    if (str_contains($lok, 'HDB') || str_contains($lok, 'HANDIL')) {
        return 'HDB';
    }
    if (str_contains($lok, 'BTL') || str_contains($lok, 'BATULICIN')) {
        return 'BATULICIN';
    }
    if (str_contains($lok, 'MTP') || str_contains($lok, 'MARTAPURA')) {
        return 'MTP';
    }
    if (str_contains($lok, 'TJG') || str_contains($lok, 'TANJUNG')) {
        return 'TANJUNG';
    }
    if (str_contains($lok, 'KPO') || str_contains($lok, 'PUSAT')) {
        return 'KPO';
    }

    // 2. Jika tidak ada di teks lokasi, deteksi dari prefix nomor rekening
    if (preg_match('/^(\d{2})[\.\-]/', $rek, $m)) {
        switch ($m[1]) {
            case '01': return 'KPO';
            case '02': return 'BATULICIN';
            case '03': return 'MTP';
            case '04': return 'TANJUNG';
            case '05': return 'HDB';
        }
    }

    return 'KPO';
}

/**
 * Mengambil semua data dari tabel inventaris_kartu
 */
function get_inventaris_kartu_rows(bool $refresh = false): array {
    static $staticCardsCache = null;
    if ($staticCardsCache !== null && !$refresh) {
        return $staticCardsCache;
    }

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
                $staticCardsCache = $defaults;
                return $defaults;
            }

            $normalized = [];
            foreach ($rows as $r) {
                $id = (int)($r['id'] ?? $r['col_0'] ?? 0);
                if ($id <= 0) continue;
                $rek = trim((string)($r['nomor_rekening'] ?? $r['col_1'] ?? ''));
                $nama = trim((string)($r['nama_barang'] ?? $r['col_2'] ?? ''));
                $tgl = trim((string)($r['tanggal_perolehan'] ?? $r['col_3'] ?? ''));
                $barcode = trim((string)($r['barcode_data'] ?? $r['col_4'] ?? ''));
                $lokasi = trim((string)($r['lokasi'] ?? $r['col_5'] ?? ''));
                $created = trim((string)($r['created_at'] ?? $r['col_6'] ?? ''));

                if ($lokasi === '' || $lokasi === 'KPO' || $lokasi === 'KPO / Operasional') {
                    $cBranch = get_cabang_from_nomor_rekening($rek);
                    $lokasi = $cBranch ? $cBranch['lokasi'] : 'Kantor Pusat (KPO)';
                }

                $normalized[] = [
                    'id'                => $id,
                    'nomor_rekening'   => $rek,
                    'nama_barang'      => $nama,
                    'tanggal_perolehan'=> $tgl,
                    'barcode_data'     => $barcode,
                    'lokasi'           => $lokasi,
                    'created_at'       => $created
                ];
            }
            if (!empty($normalized)) {
                $staticCardsCache = $normalized;
                return $normalized;
            }
        }
    }

    // 2. Mode MySQL
    try {
        $pdo = db();
        try {
            $st = $pdo->query("SELECT * FROM inventaris_kartu ORDER BY id DESC");
            $rows = $st ? $st->fetchAll() : [];
        } catch (Throwable $e) {
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
        }

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
                $newId, sheet_cell_text($rek), $nama, $tgl, $barcode, $lokasi, $nowStr
            ]]);
            return ['success' => true, 'id' => $newId];
        }
    }

    ensure_inventaris_kartu_columns();

    $noSeri = trim((string)($data['nomor_seri_inventaris'] ?? ''));
    $jenisInv = trim((string)($data['jenis_inventaris'] ?? ''));
    $golongan = trim((string)($data['golongan'] ?? ''));
    $ruangan = trim((string)($data['ruangan'] ?? ''));
    $sumber = trim((string)($data['sumber_perolehan'] ?? ''));
    $statusInv = trim((string)($data['status_inventaris'] ?? '1 : Aktif'));
    $ket = trim((string)($data['keterangan'] ?? ''));

    try {
        $ins = db()->prepare("
            INSERT INTO inventaris_kartu (
                nomor_rekening, nama_barang, tanggal_perolehan, barcode_data, lokasi,
                nomor_seri_inventaris, jenis_inventaris, golongan, ruangan, sumber_perolehan, status_inventaris, keterangan,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $rek, $nama, $tgl, $barcode, $lokasi,
            $noSeri, $jenisInv, $golongan, $ruangan, $sumber, $statusInv, $ket,
            $nowStr
        ]);
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
    ensure_inventaris_kartu_columns();

    if ($id <= 0) return false;

    $current = null;
    if (empty($data['nomor_rekening']) || empty($data['nama_barang'])) {
        $current = get_inventaris_kartu_by_id($id);
    }

    $rek = trim((string)($data['nomor_rekening'] ?? ($current['nomor_rekening'] ?? '')));
    $nama = trim((string)($data['nama_barang'] ?? ($current['nama_barang'] ?? '')));
    $tgl = !empty($data['tanggal_perolehan']) ? normalize_date_to_db($data['tanggal_perolehan']) : ($current['tanggal_perolehan'] ?? date('Y-m-d'));
    $barcode = trim((string)($data['barcode_data'] ?? ($current['barcode_data'] ?? '')));
    
    // Tentukan lokasi: gunakan input lokasi jika diisi, atau default berdasarkan cabang
    $lokasi = trim((string)($data['lokasi'] ?? ($current['lokasi'] ?? '')));
    if ($lokasi === '') {
        $cBranch = get_cabang_from_nomor_rekening($rek);
        $lokasi = $cBranch ? $cBranch['lokasi'] : 'Kantor Pusat (KPO)';
    }

    if ($rek === '' || $nama === '') return false;

    $noSeri = trim((string)($data['nomor_seri_inventaris'] ?? ($current['nomor_seri_inventaris'] ?? '')));
    $jenisInv = trim((string)($data['jenis_inventaris'] ?? ($current['jenis_inventaris'] ?? '')));
    $golongan = trim((string)($data['golongan'] ?? ($current['golongan'] ?? '')));
    $ruangan = trim((string)($data['ruangan'] ?? ($current['ruangan'] ?? '')));
    $pengguna = trim((string)($data['pengguna'] ?? ($current['pengguna'] ?? 'Umum / Pool')));
    $sumber = trim((string)($data['sumber_perolehan'] ?? ($current['sumber_perolehan'] ?? '')));
    $statusInv = trim((string)($data['status_inventaris'] ?? ($current['status_inventaris'] ?? '1 : Aktif')));
    $ket = trim((string)($data['keterangan'] ?? ($current['keterangan'] ?? '')));

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if ($client) {
            $rows = $client->getSheetData('inventaris_kartu', true);
            foreach ($rows as $idx => $r) {
                if ((int)($r['id'] ?? 0) === $id) {
                    $rowNum = $idx + 2;
                    $client->updateValues("inventaris_kartu!B{$rowNum}:F{$rowNum}", [[
                        sheet_cell_text($rek), $nama, $tgl, $barcode, $lokasi
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
            SET nomor_rekening = ?, nama_barang = ?, tanggal_perolehan = ?, barcode_data = ?, lokasi = ?,
                nomor_seri_inventaris = ?, jenis_inventaris = ?, golongan = ?, ruangan = ?, pengguna = ?, sumber_perolehan = ?, status_inventaris = ?, keterangan = ?
            WHERE id = ?
        ");
        return $st->execute([
            $rek, $nama, $tgl, $barcode, $lokasi,
            $noSeri, $jenisInv, $golongan, $ruangan, $pengguna, $sumber, $statusInv, $ket,
            $id
        ]);
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

/**
 * Mengambil satu data kartu inventaris berdasarkan ID
 */
function get_inventaris_kartu_by_id(int $id): ?array {
    if ($id <= 0) return null;
    try {
        $st = db()->prepare("SELECT * FROM inventaris_kartu WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row) return $row;
    } catch (Throwable $e) {}

    $rows = get_inventaris_kartu_rows();
    foreach ($rows as $r) {
        if ((int)($r['id'] ?? 0) === $id) {
            return $r;
        }
    }
    return null;
}

/**
 * Mengambil satu data kartu inventaris berdasarkan nomor rekening
 */
function get_inventaris_kartu_by_norek(string $noRek): ?array {
    $clean = trim($noRek);
    if ($clean === '') return null;
    $digits = preg_replace('/[^0-9]/', '', $clean);
    try {
        $st = db()->prepare("SELECT * FROM inventaris_kartu WHERE nomor_rekening = ? OR REPLACE(nomor_rekening, '.', '') = ? LIMIT 1");
        $st->execute([$clean, $digits]);
        $row = $st->fetch();
        if ($row) return $row;
    } catch (Throwable $e) {}

    $rows = get_inventaris_kartu_rows();
    foreach ($rows as $r) {
        $rRek = (string)($r['nomor_rekening'] ?? '');
        if ($rRek === $clean || preg_replace('/[^0-9]/', '', $rRek) === $digits) {
            return $r;
        }
    }
    return null;
}

/**
 * Update foto kartu inventaris (spesifik per ID kartu)
 */
function update_inventaris_kartu_foto(int $id, string $fotoPath, ?string $noRek = null, ?string $updatedBy = null): bool {
    if ($id <= 0 && empty($noRek)) return false;
    try {
        ensure_inventaris_kartu_columns();

        $now = date('Y-m-d H:i:s');
        if ($id > 0) {
            $st = db()->prepare("UPDATE inventaris_kartu SET foto = ?, foto_updated_by = ?, foto_updated_at = ? WHERE id = ?");
            $st->execute([$fotoPath, $updatedBy, $now, $id]);
        } elseif (!empty($noRek)) {
            $cleanRek = trim($noRek);
            $digits = preg_replace('/[^0-9]/', '', $cleanRek);
            $st2 = db()->prepare("UPDATE inventaris_kartu SET foto = ?, foto_updated_by = ?, foto_updated_at = ? WHERE nomor_rekening = ? OR REPLACE(nomor_rekening, '.', '') = ?");
            $st2->execute([$fotoPath, $updatedBy, $now, $cleanRek, $digits]);
        }
        return true;
    } catch (Throwable $e) {
        error_log("Gagal update foto kartu inventaris: " . $e->getMessage());
        return false;
    }
}

/**
 * Kosongkan seluruh foto di tabel inventaris_kartu
 */
function reset_all_inventaris_kartu_photos(): bool {
    try {
        ensure_inventaris_kartu_columns();
        db()->exec("UPDATE inventaris_kartu SET foto = NULL, foto_updated_by = NULL, foto_updated_at = NULL");
        return true;
    } catch (Throwable $e) {
        error_log("Gagal mengosongkan foto inventaris_kartu: " . $e->getMessage());
        return false;
    }
}

/**
 * Pastikan kolom-kolom baru (Nomor Seri, Jenis, Golongan, Ruangan, Keterangan, dll) ada di tabel inventaris_kartu
 */
function ensure_inventaris_kartu_columns(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    // Kolom-kolom inventaris sudah tersedia di skema database produksi.
    // Menghindari ALTER TABLE pada setiap HTTP request untuk menjaga kecepatan dan menghindari lock MySQL.
}

/**
 * Master Data Jenis Inventaris BPR Mitratama (Sesuai Core System)
 */
function get_master_jenis_inventaris(): array {
    return [
        '1' => '1 : TANAH',
        '2' => '2 : BANGUNAN',
        '3' => '3 : PERALATAN KANTOR',
        '4' => '4 : KENDARAAN',
        '5' => '5 : MESIN',
        '6' => '6 : BERWUJUD LAINNYA',
        '7' => '7 : PROGRAM KOMPUTER',
    ];
}

/**
 * Master Data Golongan Inventaris BPR Mitratama (Sesuai Core System)
 */
function get_master_golongan_inventaris(): array {
    return [
        '01' => '01 : TANAH & GEDUNG',
        '02' => '02 : GOLONGAN I',
        '03' => '03 : GOLONGAN II',
        '04' => '04 : GOLONGAN III',
        '05' => '05 : ASET TIDAK BERWUJUD',
    ];
}

/**
 * Master Data Sumber Perolehan BPR Mitratama
 */
function get_master_sumber_perolehan(): array {
    return [
        '01' => '01 : MODAL AWAL',
        '02' => '02 : MODAL DISETOR',
        '03' => '03 : PEMBELIAN INVENTARIS',
        '04' => '04 : HIBAH / LAINNYA',
    ];
}

/**
 * Master Data Status Inventaris
 */
function get_master_status_inventaris(): array {
    return [
        '1 : Aktif' => '1 : Aktif',
        '2 : Rusak / Perbaikan' => '2 : Rusak / Perbaikan',
        '3 : Dihapus / Mutasi' => '3 : Dihapus / Mutasi',
        '4 : Tidak Aktif' => '4 : Tidak Aktif',
    ];
}

/**
 * Mengambil daftar Ruangan dari database master_ruangan (dinamis & bisa ditambah)
 */
function get_master_ruangan_list(): array {
    try {
        try {
            $rows = db()->query("SELECT * FROM master_ruangan ORDER BY kode_ruangan ASC, id ASC")->fetchAll();
        } catch (Throwable $e) {
            db()->exec("
                CREATE TABLE IF NOT EXISTS master_ruangan (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    kode_ruangan VARCHAR(20) NOT NULL,
                    nama_ruangan VARCHAR(150) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_ruangan (kode_ruangan, nama_ruangan)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            $rows = db()->query("SELECT * FROM master_ruangan ORDER BY kode_ruangan ASC, id ASC")->fetchAll();
        }
        if (empty($rows)) {
            $defaultRooms = [
                ['01', 'RUANG DIREKSI / DIREKTUR UTAMA'],
                ['02', 'RUANG DIREKTUR OPERASIONAL'],
                ['03', 'RUANG KEPALA DIVISI'],
                ['04', 'RUANG IT & SERVER'],
                ['05', 'LANTAI I GEDUNG DEPAN'],
                ['06', 'LANTAI I KASIR / TELLER'],
                ['07', 'CUSTOMER SERVICE (CS)'],
                ['08', 'RUANG ARSIP / PERPUSTAKAAN'],
                ['09', 'RUANG RAPAT (MEETING ROOM)'],
                ['10', 'GUDANG & LOGISTIK'],
                ['11', 'PARKIRAN & AREA LUAR'],
            ];
            $st = db()->prepare("INSERT IGNORE INTO master_ruangan (kode_ruangan, nama_ruangan) VALUES (?, ?)");
            foreach ($defaultRooms as $r) {
                $st->execute([$r[0], $r[1]]);
            }
            $rows = db()->query("SELECT * FROM master_ruangan ORDER BY kode_ruangan ASC, id ASC")->fetchAll();
        }
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Menambahkan Ruangan Baru ke Master Ruangan
 */
function add_master_ruangan(string $namaRuangan, ?string $kodeRuangan = null): string {
    $namaClean = strtoupper(trim($namaRuangan));
    if ($namaClean === '') return '';
    try {
        get_master_ruangan_list(); // pastikan tabel ada
        if (empty($kodeRuangan)) {
            $st = db()->query("SELECT MAX(CAST(kode_ruangan AS UNSIGNED)) as max_c FROM master_ruangan");
            $maxC = (int)($st->fetch()['max_c'] ?? 11);
            $kodeRuangan = sprintf('%02d', max($maxC + 1, 1));
        } else {
            $kodeRuangan = sprintf('%02d', (int)$kodeRuangan);
        }
        $st = db()->prepare("INSERT IGNORE INTO master_ruangan (kode_ruangan, nama_ruangan) VALUES (?, ?)");
        $st->execute([$kodeRuangan, $namaClean]);
        return "{$kodeRuangan} : {$namaClean}";
    } catch (Throwable $e) {
        return $namaClean;
    }
}

/**
 * Update Data Master Lengkap Kartu Inventaris (Jenis, Golongan, Ruangan, No Seri, Keterangan)
 */
function update_inventaris_kartu_data(int $id, array $data): bool {
    if ($id <= 0) return false;
    ensure_inventaris_kartu_columns();
    try {
        $st = db()->prepare("
            UPDATE inventaris_kartu 
            SET nomor_seri_inventaris = ?,
                jenis_inventaris = ?,
                golongan = ?,
                ruangan = ?,
                lokasi = ?,
                sumber_perolehan = ?,
                status_inventaris = ?,
                keterangan = ?
            WHERE id = ?
        ");
        return $st->execute([
            $data['nomor_seri_inventaris'] ?? null,
            $data['jenis_inventaris'] ?? null,
            $data['golongan'] ?? null,
            $data['ruangan'] ?? null,
            $data['lokasi'] ?? ($data['ruangan'] ?? null),
            $data['sumber_perolehan'] ?? null,
            $data['status_inventaris'] ?? '1 : Aktif',
            $data['keterangan'] ?? null,
            $id
        ]);
    } catch (Throwable $e) {
        error_log("Gagal update data inventaris kartu: " . $e->getMessage());
        return false;
    }
}

/**
 * Auto-generate format Nomor Seri Inventaris resmi bank jika belum diset
 * Contoh: 01.05.02.220124.001
 */
function generate_default_nomor_seri(array $card): string {
    if (!empty($card['nomor_seri_inventaris'])) {
        return (string)$card['nomor_seri_inventaris'];
    }
    $cBranch = get_cabang_from_nomor_rekening((string)($card['nomor_rekening'] ?? ''));
    $kdCabang = $cBranch ? $cBranch['code'] : '01';

    // Ambil kode ruangan (default 05)
    $kdRuangan = '05';
    if (!empty($card['ruangan']) && preg_match('/^(\d{2})/i', trim($card['ruangan']), $mR)) {
        $kdRuangan = $mR[1];
    }

    // Ambil kode golongan (default 02)
    $kdGolongan = '02';
    if (!empty($card['golongan']) && preg_match('/^(\d{2})/i', trim($card['golongan']), $mG)) {
        $kdGolongan = $mG[1];
    }

    // Tanggal ddMMyy
    $tglClean = trim((string)($card['tanggal_perolehan'] ?? ''));
    $tglPart = '010124';
    if (!empty($tglClean) && $tglClean !== '0000-00-00') {
        $ts = strtotime($tglClean);
        if ($ts) $tglPart = date('dmy', $ts);
    }

    $idUrut = sprintf('%03d', (int)($card['id'] % 1000 ?: 1));
    return "{$kdCabang}.{$kdRuangan}.{$kdGolongan}.{$tglPart}.{$idUrut}";
}

/**
 * Kompres dan simpan gambar aset dengan kualitas tajam tapi ukuran file hemat (~150-280KB)
 * Menjaga performa server agar tidak kehabisan storage
 */
function compress_and_save_uploaded_image(string $tmpPath, string $targetPath, int $maxDimension = 1600, int $quality = 82, bool $crop4by5 = false): bool {
    if (!file_exists($tmpPath)) return false;

    // Pastikan direktori tujuan tersedia
    $dir = dirname($targetPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
        @chmod($dir, 0777);
    }

    // Jika ekstensi GD PHP tersedia, lakukan kompresi & resize pintar
    if (function_exists('imagecreatefromstring') && function_exists('imagecreatetruecolor') && function_exists('imagecopyresampled')) {
        $raw = @file_get_contents($tmpPath);
        if ($raw !== false && strlen($raw) > 0) {
            $src = @imagecreatefromstring($raw);
            if ($src !== false) {
                $w = imagesx($src);
                $h = imagesy($src);

                // Auto-rotate dari metadata EXIF kamera HP jika tersedia
                if (function_exists('exif_read_data')) {
                    try {
                        $exif = @exif_read_data($tmpPath);
                        if (!empty($exif['Orientation'])) {
                            switch ((int)$exif['Orientation']) {
                                case 3:
                                    $src = imagerotate($src, 180, 0);
                                    break;
                                case 6:
                                    $src = imagerotate($src, -90, 0);
                                    $w = imagesx($src);
                                    $h = imagesy($src);
                                    break;
                                case 8:
                                    $src = imagerotate($src, 90, 0);
                                    $w = imagesx($src);
                                    $h = imagesy($src);
                                    break;
                            }
                        }
                    } catch (Throwable $ignore) {}
                }

                $srcX = 0;
                $srcY = 0;
                $cropW = $w;
                $cropH = $h;

                if ($crop4by5) {
                    $targetRatio = 4 / 5; // 0.8
                    if ($w / $h > $targetRatio) {
                        $cropW = (int)round($h * $targetRatio);
                        $cropH = $h;
                        $srcX = (int)round(($w - $cropW) / 2);
                        $srcY = 0;
                    } else {
                        $cropW = $w;
                        $cropH = (int)round($w / $targetRatio);
                        $srcX = 0;
                        $srcY = (int)round(($h - $cropH) / 2);
                    }
                    $newW = min(1200, $cropW);
                    $newH = (int)round($newW / $targetRatio);
                } else {
                    // Hitung skala baru (maksimum 1600px pada sisi terpanjang)
                    $newW = $w;
                    $newH = $h;
                    if ($w > $maxDimension || $h > $maxDimension) {
                        if ($w >= $h) {
                            $newW = $maxDimension;
                            $newH = (int)round(($h / $w) * $maxDimension);
                        } else {
                            $newH = $maxDimension;
                            $newW = (int)round(($w / $h) * $maxDimension);
                        }
                    }
                }

                $dst = imagecreatetruecolor($newW, $newH);

                // Pertahankan latar belakang bersih
                $white = imagecolorallocate($dst, 255, 255, 255);
                imagefilledrectangle($dst, 0, 0, $newW, $newH, $white);

                imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $newW, $newH, $cropW, $cropH);

                // Simpan sebagai JPEG terkompresi kualitas 82 (sangat tajam & ringan)
                $saved = @imagejpeg($dst, $targetPath, $quality);
                imagedestroy($src);
                imagedestroy($dst);

                if ($saved && file_exists($targetPath) && filesize($targetPath) > 0) {
                    @chmod($targetPath, 0666);
                    return true;
                }
                if (file_exists($targetPath) && filesize($targetPath) === 0) {
                    @unlink($targetPath);
                }
            }
        }
    }

    // Fallback bila GD belum aktif di server: gunakan move_uploaded_file standar
    if (is_uploaded_file($tmpPath)) {
        $ok = @move_uploaded_file($tmpPath, $targetPath);
    } else {
        $ok = @copy($tmpPath, $targetPath);
    }
    if ($ok && file_exists($targetPath) && filesize($targetPath) > 0) {
        @chmod($targetPath, 0666);
        return true;
    }
    if (file_exists($targetPath) && filesize($targetPath) === 0) {
        @unlink($targetPath);
    }
    return false;
}



