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
 * Pastikan kolom printed_at dan print_count tersedia di tabel inventaris_kartu
 */
function ensure_inventaris_kartu_print_columns(): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $pdo = db();
        $st = $pdo->query("SHOW COLUMNS FROM inventaris_kartu LIKE 'printed_at'");
        if (!$st || !$st->fetch()) {
            $pdo->exec("ALTER TABLE inventaris_kartu ADD COLUMN printed_at DATETIME NULL DEFAULT NULL, ADD COLUMN print_count INT UNSIGNED NOT NULL DEFAULT 0");
        }
    } catch (Throwable $e) {
        // Ignore if already exists or non-MySQL mode
    }
}

/**
 * Tandai satu atau banyak kartu inventaris sebagai Sudah Dicetak / Belum Dicetak
 */
function mark_inventaris_kartu_printed(array|int $ids, bool $isPrinted = true): bool {
    $idArray = is_array($ids) ? array_map('intval', $ids) : [(int)$ids];
    $idArray = array_values(array_unique(array_filter($idArray, fn($x) => $x > 0)));
    if (empty($idArray)) return false;

    ensure_inventaris_kartu_print_columns();

    try {
        $pdo = db();
        $inClause = implode(',', array_fill(0, count($idArray), '?'));
        if ($isPrinted) {
            $now = date('Y-m-d H:i:s');
            $params = array_merge([$now], $idArray);
            $st = $pdo->prepare("UPDATE inventaris_kartu SET printed_at = ?, print_count = COALESCE(print_count, 0) + 1 WHERE id IN ({$inClause})");
            return $st->execute($params);
        } else {
            $st = $pdo->prepare("UPDATE inventaris_kartu SET printed_at = NULL, print_count = 0 WHERE id IN ({$inClause})");
            return $st->execute($idArray);
        }
    } catch (Throwable $e) {
        error_log("Gagal update status cetak kartu: " . $e->getMessage());
        return false;
    }
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

/**
 * Menghasilkan script JavaScript untuk render & unduh / bagikan kartu fisik CR80
 * sesuai ukuran asli kartu (85.60 mm x 54.00 mm pada 300 DPI = 1011 x 638 px)
 * lengkap dengan metadata pHYs 300 DPI (11811 piksel/meter).
 */
function get_card_png_exporter_js(): string {
    $logoUrl = module_url('logo_img.php');
    $printAjaxUrl = module_url('print_inventory_card.php');
    $js = <<<'JS'
window.BANK_MITRA_CARD_LOGO_URL = __LOGO_URL__;
window.BANK_MITRA_PRINT_AJAX_URL = __PRINT_AJAX_URL__;

(function() {
  // Tabel CRC32 untuk injeksi chunk pHYs 300 DPI pada file PNG
  const crcTable = (function() {
    const table = new Uint32Array(256);
    for (let n = 0; n < 256; n++) {
      let c = n;
      for (let k = 0; k < 8; k++) {
        c = (c & 1) ? (0xedb88320 ^ (c >>> 1)) : (c >>> 1);
      }
      table[n] = c >>> 0;
    }
    return table;
  })();

  function crc32(bytes) {
    let c = 0xffffffff;
    for (let i = 0; i < bytes.length; i++) {
      c = crcTable[(c ^ bytes[i]) & 0xff] ^ (c >>> 8);
    }
    return (c ^ 0xffffffff) >>> 0;
  }

  // Menyisipkan chunk pHYs 300 DPI (11811 px/meter = 85.6mm x 54.0mm pada 1011x638 px)
  async function injectPng300Dpi(blob) {
    try {
      const buffer = await blob.arrayBuffer();
      const src = new Uint8Array(buffer);
      // Pastikan header PNG valid dan chunk pertama adalah IHDR (panjang 13 byte -> selesai di byte 33)
      if (src.length < 33 || src[0] !== 0x89 || src[1] !== 0x50 || src[2] !== 0x4E || src[3] !== 0x47) {
        return blob;
      }
      // 21 byte chunk pHYs: 4 byte length (9), 4 byte type ('pHYs'), 9 byte data, 4 byte CRC
      const phys = new Uint8Array(21);
      const view = new DataView(phys.buffer);
      view.setUint32(0, 9, false); // Length = 9
      phys[4] = 0x70; // 'p'
      phys[5] = 0x48; // 'H'
      phys[6] = 0x59; // 'Y'
      phys[7] = 0x73; // 's'
      const ppm = 11811; // 300 DPI = 11811.0236 pixels per meter
      view.setUint32(8, ppm, false);  // X pixels per unit
      view.setUint32(12, ppm, false); // Y pixels per unit
      phys[16] = 1; // Unit = meter
      const crc = crc32(phys.subarray(4, 17));
      view.setUint32(17, crc, false);

      const out = new Uint8Array(src.length + phys.length);
      out.set(src.subarray(0, 33), 0);
      out.set(phys, 33);
      out.set(src.subarray(33), 33 + phys.length);
      return new Blob([out], { type: 'image/png' });
    } catch (e) {
      return blob;
    }
  }

  function loadImageElement(src) {
    return new Promise((resolve) => {
      if (!src) return resolve(null);
      const img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = () => resolve(img);
      img.onerror = () => resolve(null);
      img.src = src;
    });
  }

  function generateQrCanvas(text, sizePx) {
    return new Promise((resolve) => {
      if (typeof QRCode === 'undefined') {
        return resolve(null);
      }
      const holder = document.createElement('div');
      holder.style.position = 'fixed';
      holder.style.left = '-9999px';
      holder.style.top = '-9999px';
      document.body.appendChild(holder);
      try {
        new QRCode(holder, {
          text: text || window.location.href,
          width: sizePx,
          height: sizePx,
          colorDark: '#000000',
          colorLight: '#ffffff',
          correctLevel: QRCode.CorrectLevel.M
        });
        setTimeout(() => {
          const canvas = holder.querySelector('canvas');
          const img = holder.querySelector('img');
          if (canvas) {
            document.body.removeChild(holder);
            resolve(canvas);
          } else if (img && img.src) {
            loadImageElement(img.src).then((loaded) => {
              document.body.removeChild(holder);
              resolve(loaded);
            });
          } else {
            document.body.removeChild(holder);
            resolve(null);
          }
        }, 60);
      } catch (err) {
        if (holder.parentNode) document.body.removeChild(holder);
        resolve(null);
      }
    });
  }

  function drawRoundedRectPath(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.lineTo(x + w - r, y);
    ctx.quadraticCurveTo(x + w, y, x + w, y + r);
    ctx.lineTo(x + w, y + h - r);
    ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
    ctx.lineTo(x + r, y + h);
    ctx.quadraticCurveTo(x, y + h, x, y + h - r);
    ctx.lineTo(x, y + r);
    ctx.quadraticCurveTo(x, y, x + r, y);
    ctx.closePath();
  }

  function drawFieldVectorIcon(ctx, type, cx, cy, size) {
    ctx.save();
    ctx.strokeStyle = '#ffffff';
    ctx.fillStyle = '#ffffff';
    ctx.lineWidth = Math.max(2, size * 0.095);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    const r = size / 2;
    const x0 = cx - r;
    const y0 = cy - r;

    if (type === 'doc') {
      drawRoundedRectPath(ctx, x0 + size * 0.14, y0 + size * 0.08, size * 0.72, size * 0.84, size * 0.08);
      ctx.stroke();
      ctx.beginPath();
      ctx.moveTo(x0 + size * 0.30, y0 + size * 0.34);
      ctx.lineTo(x0 + size * 0.70, y0 + size * 0.34);
      ctx.moveTo(x0 + size * 0.30, y0 + size * 0.52);
      ctx.lineTo(x0 + size * 0.70, y0 + size * 0.52);
      ctx.moveTo(x0 + size * 0.30, y0 + size * 0.70);
      ctx.lineTo(x0 + size * 0.54, y0 + size * 0.70);
      ctx.stroke();
    } else if (type === 'monitor') {
      drawRoundedRectPath(ctx, x0 + size * 0.08, y0 + size * 0.12, size * 0.84, size * 0.58, size * 0.08);
      ctx.stroke();
      ctx.beginPath();
      ctx.moveTo(cx - size * 0.22, y0 + size * 0.88);
      ctx.lineTo(cx + size * 0.22, y0 + size * 0.88);
      ctx.moveTo(cx, y0 + size * 0.70);
      ctx.lineTo(cx, y0 + size * 0.88);
      ctx.stroke();
    } else if (type === 'calendar') {
      drawRoundedRectPath(ctx, x0 + size * 0.10, y0 + size * 0.18, size * 0.80, size * 0.72, size * 0.08);
      ctx.stroke();
      ctx.beginPath();
      ctx.moveTo(x0 + size * 0.32, y0 + size * 0.08);
      ctx.lineTo(x0 + size * 0.32, y0 + size * 0.26);
      ctx.moveTo(x0 + size * 0.68, y0 + size * 0.08);
      ctx.lineTo(x0 + size * 0.68, y0 + size * 0.26);
      ctx.moveTo(x0 + size * 0.10, y0 + size * 0.40);
      ctx.lineTo(x0 + size * 0.90, y0 + size * 0.40);
      ctx.stroke();
    } else if (type === 'pin') {
      ctx.beginPath();
      ctx.arc(cx, y0 + size * 0.40, size * 0.30, Math.PI, 0, false);
      ctx.bezierCurveTo(cx + size * 0.30, y0 + size * 0.64, cx, y0 + size * 0.88, cx, y0 + size * 0.92);
      ctx.bezierCurveTo(cx, y0 + size * 0.88, cx - size * 0.30, y0 + size * 0.64, cx - size * 0.30, y0 + size * 0.40);
      ctx.closePath();
      ctx.stroke();
      ctx.beginPath();
      ctx.arc(cx, y0 + size * 0.40, size * 0.11, 0, Math.PI * 2);
      ctx.stroke();
    }
    ctx.restore();
  }

  function drawShieldCheckIcon(ctx, cx, cy, size) {
    ctx.save();
    const r = size / 2;
    const x0 = cx - r;
    const y0 = cy - r;
    ctx.strokeStyle = '#003b73';
    ctx.lineWidth = Math.max(2.5, size * 0.085);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    ctx.beginPath();
    ctx.moveTo(cx, y0 + size * 0.08);
    ctx.lineTo(x0 + size * 0.84, y0 + size * 0.22);
    ctx.lineTo(x0 + size * 0.84, y0 + size * 0.50);
    ctx.bezierCurveTo(x0 + size * 0.84, y0 + size * 0.74, cx + size * 0.18, y0 + size * 0.88, cx, y0 + size * 0.94);
    ctx.bezierCurveTo(cx - size * 0.18, y0 + size * 0.88, x0 + size * 0.16, y0 + size * 0.74, x0 + size * 0.16, y0 + size * 0.50);
    ctx.lineTo(x0 + size * 0.16, y0 + size * 0.22);
    ctx.closePath();
    ctx.stroke();

    ctx.strokeStyle = '#7ac142';
    ctx.beginPath();
    ctx.moveTo(cx - size * 0.16, cy + size * 0.02);
    ctx.lineTo(cx - size * 0.03, cy + size * 0.15);
    ctx.lineTo(cx + size * 0.20, cy - size * 0.10);
    ctx.stroke();
    ctx.restore();
  }

  function wrapLines(ctx, text, maxWidth, maxLines) {
    const words = String(text || '-').toUpperCase().split(/\s+/).filter(Boolean);
    if (words.length === 0) return ['-'];
    const lines = [];
    let current = words[0];
    for (let i = 1; i < words.length; i++) {
      const test = current + ' ' + words[i];
      if (ctx.measureText(test).width <= maxWidth) {
        current = test;
      } else {
        lines.push(current);
        current = words[i];
      }
    }
    lines.push(current);
    if (lines.length > maxLines) {
      const clipped = lines.slice(0, maxLines);
      let last = clipped[maxLines - 1];
      while (last.length > 3 && ctx.measureText(last + '...').width > maxWidth) {
        last = last.slice(0, -1);
      }
      clipped[maxLines - 1] = last + (last !== lines[maxLines - 1] ? '...' : '');
      return clipped;
    }
    return lines;
  }

  // Render kartu fisik CR80 ukuran asli (85.6mm x 54.0mm @ 300 DPI = 1011 x 638 px)
  window.renderPhysicalCardBlob = async function(cardData) {
    const W = 1011; // 85.60 mm @ 300 DPI
    const H = 638;  // 54.00 mm @ 300 DPI
    const mm = (val) => val * (W / 85.6);

    const canvas = document.createElement('canvas');
    canvas.width = W;
    canvas.height = H;
    const ctx = canvas.getContext('2d');

    // Clip sudut kartu CR80 (radius 3.0mm)
    ctx.save();
    drawRoundedRectPath(ctx, 0, 0, W, H, mm(3.0));
    ctx.clip();

    // Latar belakang putih bersih
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, W, H);

    // 1. HEADER SECTION (Height: 11.5mm)
    const headerH = mm(11.5);
    const logoBoxW = W * 0.27;
    const titleBoxX = logoBoxW;
    const titleBoxW = W - logoBoxW;
    const topBarH = mm(5.5);
    const subBarH = mm(6.0);

    // Logo Bank Mitra di kiri
    const logoImg = await loadImageElement(window.BANK_MITRA_CARD_LOGO_URL);
    if (logoImg && logoImg.naturalWidth > 0) {
      const maxLW = logoBoxW - mm(3.0);
      const maxLH = headerH - mm(2.0);
      const scale = Math.min(maxLW / logoImg.naturalWidth, maxLH / logoImg.naturalHeight);
      const lw = logoImg.naturalWidth * scale;
      const lh = logoImg.naturalHeight * scale;
      ctx.drawImage(logoImg, (logoBoxW - lw) / 2, (headerH - lh) / 2, lw, lh);
    } else {
      ctx.fillStyle = '#003b73';
      ctx.font = `800 ${Math.round(mm(2.4))}px "Plus Jakarta Sans", Arial, sans-serif`;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText('BANK MITRA', logoBoxW / 2, headerH / 2);
    }

    // Top Bar Navy (#003b73)
    ctx.fillStyle = '#003b73';
    ctx.fillRect(titleBoxX, 0, titleBoxW, topBarH);
    ctx.fillStyle = '#ffffff';
    ctx.font = `800 ${Math.round(mm(2.54))}px "Plus Jakarta Sans", Arial, sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('PT BPR MITRATAMA ARTHABUANA', titleBoxX + titleBoxW / 2, topBarH / 2 + 1);

    // Sub Bar Banners (ASSET TETAP)
    const bannerContainerW = titleBoxW * 0.75;
    // Teal stripe (#009ca6): polygon(0 0, 78% 0, 71% 100%, 0 100%)
    ctx.fillStyle = '#009ca6';
    ctx.beginPath();
    ctx.moveTo(titleBoxX, topBarH);
    ctx.lineTo(titleBoxX + bannerContainerW * 0.78, topBarH);
    ctx.lineTo(titleBoxX + bannerContainerW * 0.71, topBarH + subBarH);
    ctx.lineTo(titleBoxX, topBarH + subBarH);
    ctx.closePath();
    ctx.fill();

    // Green banner (#7ac142): width 92% of bannerContainerW
    const greenW = bannerContainerW * 0.92;
    ctx.fillStyle = '#7ac142';
    ctx.beginPath();
    ctx.moveTo(titleBoxX, topBarH);
    ctx.lineTo(titleBoxX + greenW * 0.78, topBarH);
    ctx.lineTo(titleBoxX + greenW * 0.71, topBarH + subBarH);
    ctx.lineTo(titleBoxX, topBarH + subBarH);
    ctx.closePath();
    ctx.fill();

    ctx.fillStyle = '#ffffff';
    ctx.font = `800 ${Math.round(mm(2.54))}px "Plus Jakarta Sans", Arial, sans-serif`;
    ctx.textAlign = 'left';
    ctx.textBaseline = 'middle';
    ctx.fillText('ASSET TETAP', titleBoxX + mm(2.5), topBarH + subBarH / 2 + 1);

    // 3x2 Green Dots di sebelah kanan sub-bar
    const dotRadius = mm(0.45);
    const dotGap = mm(1.75);
    const dotsRightStart = W - mm(3.5) - dotGap * 2;
    const dotsTopStart = topBarH + (subBarH - dotGap) / 2;
    ctx.fillStyle = '#7ac142';
    for (let r = 0; r < 2; r++) {
      for (let c = 0; c < 3; c++) {
        ctx.beginPath();
        ctx.arc(dotsRightStart + c * dotGap, dotsTopStart + r * dotGap, dotRadius, 0, Math.PI * 2);
        ctx.fill();
      }
    }

    // Garis bawah header
    ctx.strokeStyle = '#cbd5e1';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(0, headerH);
    ctx.lineTo(W, headerH);
    ctx.stroke();

    // 2. DATA FIELDS (4 baris x 6.125mm = 24.5mm)
    const rowH = mm(6.125);
    const iconBoxW = mm(8.5);
    const fields = [
      { icon: 'doc',      label: 'NOMOR ASSET',   value: cardData.nomor_asset || cardData.kode || '-' },
      { icon: 'monitor',  label: 'NAMA ASSET',    value: cardData.nama || '-' },
      { icon: 'calendar', label: 'TGL PEROLEHAN', value: cardData.tgl || '-' },
      { icon: 'pin',      label: 'LOKASI',        value: cardData.lokasi || '-' }
    ];

    fields.forEach((f, idx) => {
      const y = headerH + idx * rowH;

      // Kotak ikon navy
      ctx.fillStyle = '#003b73';
      ctx.fillRect(0, y, iconBoxW, rowH);
      drawFieldVectorIcon(ctx, f.icon, iconBoxW / 2, y + rowH / 2, mm(3.5));

      // Label
      ctx.fillStyle = '#003b73';
      ctx.font = `800 ${Math.round(mm(2.47))}px "Plus Jakarta Sans", Arial, sans-serif`;
      ctx.textAlign = 'left';
      ctx.textBaseline = 'middle';
      ctx.fillText(f.label, iconBoxW + mm(3.0), y + rowH / 2 + 1);

      // Separator hijau '|'
      const sepX = iconBoxW + mm(27.5);
      ctx.fillStyle = '#7ac142';
      ctx.font = `800 ${Math.round(mm(3.15))}px "Plus Jakarta Sans", Arial, sans-serif`;
      ctx.fillText('|', sepX, y + rowH / 2);

      // Nilai field (mendukung hingga 2 baris)
      const valX = sepX + mm(2.5);
      const maxValW = W - valX - mm(2.2);
      ctx.fillStyle = '#1e293b';
      ctx.font = `800 ${Math.round(mm(2.47))}px "Plus Jakarta Sans", Arial, sans-serif`;
      const lines = wrapLines(ctx, f.value, maxValW, 2);
      if (lines.length === 1) {
        ctx.fillText(lines[0], valX, y + rowH / 2 + 1);
      } else {
        const lineH = mm(2.65);
        ctx.fillText(lines[0], valX, y + rowH / 2 - lineH / 2 + 1);
        ctx.fillText(lines[1], valX, y + rowH / 2 + lineH / 2 + 1);
      }

      // Garis bawah baris
      ctx.strokeStyle = '#e2e8f0';
      ctx.lineWidth = 1.5;
      ctx.beginPath();
      ctx.moveTo(0, y + rowH);
      ctx.lineTo(W, y + rowH);
      ctx.stroke();
    });

    // 3. BOTTOM SECTION (Height: 18.0mm, y: 36.0mm .. 54.0mm)
    const bottomY = headerH + 4 * rowH;
    const bottomH = H - bottomY;

    // Garis pemisah atas hijau (#7ac142)
    ctx.strokeStyle = '#7ac142';
    ctx.lineWidth = 2.5;
    ctx.beginPath();
    ctx.moveTo(0, bottomY);
    ctx.lineTo(W, bottomY);
    ctx.stroke();

    // Ornamen gelombang bawah (height: 7.5mm)
    const waveH = mm(7.5);
    const waveY = H - waveH;
    ctx.save();
    ctx.translate(0, waveY);
    ctx.scale(W / 500, waveH / 80);

    // Gelombang hijau
    ctx.fillStyle = 'rgba(122, 193, 66, 0.85)';
    ctx.beginPath();
    ctx.moveTo(0, 65);
    ctx.bezierCurveTo(120, 95, 220, 10, 380, 60);
    ctx.bezierCurveTo(440, 80, 480, 45, 500, 55);
    ctx.lineTo(500, 80);
    ctx.lineTo(0, 80);
    ctx.closePath();
    ctx.fill();

    // Gelombang biru navy
    ctx.fillStyle = '#003b73';
    ctx.beginPath();
    ctx.moveTo(0, 72);
    ctx.bezierCurveTo(160, 30, 280, 95, 430, 45);
    ctx.bezierCurveTo(470, 30, 490, 50, 500, 45);
    ctx.lineTo(500, 80);
    ctx.lineTo(0, 80);
    ctx.closePath();
    ctx.fill();
    ctx.restore();

    // Bagian Kiri Bawah: PERHATIAN (68% width)
    const leftBottomW = W * 0.68;
    const shieldSize = mm(7.2);
    const shieldCx = mm(3.0) + shieldSize / 2;
    const shieldCy = bottomY + bottomH / 2 - mm(0.6);
    drawShieldCheckIcon(ctx, shieldCx, shieldCy, shieldSize);

    const attTextX = mm(3.0) + shieldSize + mm(2.2);
    const attTopY = bottomY + mm(4.2);
    ctx.fillStyle = '#b91c1c';
    ctx.font = `800 ${Math.round(mm(2.54))}px "Plus Jakarta Sans", Arial, sans-serif`;
    ctx.textAlign = 'left';
    ctx.textBaseline = 'middle';
    ctx.fillText('PERHATIAN', attTextX, attTopY);

    ctx.fillStyle = '#0f172a';
    ctx.font = `700 ${Math.round(mm(1.84))}px "Plus Jakarta Sans", Arial, sans-serif`;
    const descLines = [
      'Barang inventaris ini tercatat di HRD.',
      'Segala bentuk pemindahan dan perubahan',
      'data wajib dilaporkan ke HRD.'
    ];
    descLines.forEach((line, i) => {
      ctx.fillText(line, attTextX, attTopY + mm(3.0) + i * mm(2.45));
    });

    // Garis pemisah vertikal antara Perhatian & QR
    ctx.strokeStyle = '#e2e8f0';
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.moveTo(leftBottomW, bottomY + mm(2.0));
    ctx.lineTo(leftBottomW, H - mm(2.0));
    ctx.stroke();

    // Bagian Kanan Bawah: QR Code (32% width, ukuran QR 13.5mm x 13.5mm)
    const qrBoxCenterX = leftBottomW + (W - leftBottomW) / 2;
    const qrBoxCenterY = bottomY + bottomH / 2;
    const qrSizePx = Math.round(mm(13.5));
    const qrBorderPad = mm(0.75);
    const qrOuterSize = qrSizePx + qrBorderPad * 2;

    ctx.fillStyle = '#ffffff';
    drawRoundedRectPath(
      ctx,
      qrBoxCenterX - qrOuterSize / 2,
      qrBoxCenterY - qrOuterSize / 2,
      qrOuterSize,
      qrOuterSize,
      mm(0.9)
    );
    ctx.fill();
    ctx.strokeStyle = '#cbd5e1';
    ctx.lineWidth = 1.5;
    ctx.stroke();

    const qrSource = await generateQrCanvas(cardData.qr_url || window.location.href, 280);
    if (qrSource) {
      ctx.imageSmoothingEnabled = false;
      ctx.drawImage(
        qrSource,
        qrBoxCenterX - qrSizePx / 2,
        qrBoxCenterY - qrSizePx / 2,
        qrSizePx,
        qrSizePx
      );
      ctx.imageSmoothingEnabled = true;
    }

    // Bingkai luar kartu CR80
    ctx.strokeStyle = '#cbd5e1';
    ctx.lineWidth = 3;
    drawRoundedRectPath(ctx, 1.5, 1.5, W - 3, H - 3, mm(3.0));
    ctx.stroke();
    ctx.restore();

    const rawBlob = await new Promise((res) => canvas.toBlob(res, 'image/png', 1.0));
    return await injectPng300Dpi(rawBlob);
  };

  // Tandai kartu sebagai "Sudah Dicetak" di database secara asinkron
  window.markCardPrintedAsync = function(cardId, csrfToken) {
    if (!cardId || cardId <= 0) return;
    const fd = new FormData();
    fd.append('ajax_action', 'mark_printed');
    fd.append('ids', String(cardId));
    if (csrfToken) fd.append('_csrf', csrfToken);
    fetch(window.BANK_MITRA_PRINT_AJAX_URL, { method: 'POST', body: fd }).catch(() => {});
  };

  // Unduh atau Bagikan (WhatsApp / Mobile Share) gambar kartu ukuran asli CR80
  window.downloadOrSharePhysicalCard = async function(cardData, mode, btnEl) {
    const origHtml = btnEl ? btnEl.innerHTML : '';
    if (btnEl) {
      btnEl.disabled = true;
      btnEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memproses PNG CR80...';
    }
    try {
      const blob = await window.renderPhysicalCardBlob(cardData);
      const safeKode = String(cardData.kode || cardData.nomor_asset || 'INV').replace(/[^A-Za-z0-9.\-_]/g, '_');
      const fileName = `Kartu_Inventaris_${safeKode}_CR80_85.6x54mm.png`;
      const file = new File([blob], fileName, { type: 'image/png' });

      if (mode === 'share' && navigator.canShare && navigator.canShare({ files: [file] })) {
        await navigator.share({
          title: `Kartu Inventaris ${cardData.nama || ''}`,
          text: `Kartu Inventaris Fisik Bank Mitra\nNama Aset: ${cardData.nama || '-'}\nNo. Rekening: ${cardData.kode || '-'}\nLokasi: ${cardData.lokasi || '-'}\n Ukuran Asli: CR80 (85.6mm x 54.0mm - 300 DPI)`,
          files: [file]
        });
        return;
      }

      // Unduh file PNG secara langsung
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = fileName;
      document.body.appendChild(a);
      a.click();
      setTimeout(() => {
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      }, 600);

      // Jika pengguna menekan tombol WA di perangkat desktop yang belum mendukung Web Share File API,
      // unduh gambar otomatis lalu buka WhatsApp dengan pesan pengantar
      if (mode === 'share') {
        const waText = encodeURIComponent(
          `*KARTU INVENTARIS BANK MITRA*\n` +
          `• *Nama Barang:* ${cardData.nama || '-'}\n` +
          `• *No. Rekening:* ${cardData.kode || '-'}\n` +
          `• *No. Asset:* ${cardData.nomor_asset || '-'}\n` +
          `• *Tanggal:* ${cardData.tgl || '-'}\n` +
          `• *Lokasi:* ${cardData.lokasi || '-'}\n` +
          `• *Link QR:* ${cardData.qr_url || window.location.href}\n\n` +
          `_(File gambar kartu ukuran asli CR80 85.6mm x 54.0mm telah diunduh otomatis, silakan lampirkan pada chat ini)_`
        );
        window.open(`https://wa.me/?text=${waText}`, '_blank', 'noopener');
      }
    } catch (err) {
      if (err && err.name === 'AbortError') return;
      alert('Gagal membuat gambar kartu: ' + (err.message || err));
    } finally {
      if (btnEl) {
        btnEl.disabled = false;
        btnEl.innerHTML = origHtml;
      }
    }
  };
})();
JS;

    return str_replace(
        ['__LOGO_URL__', '__PRINT_AJAX_URL__'],
        [json_encode($logoUrl), json_encode($printAjaxUrl)],
        $js
    );
}




