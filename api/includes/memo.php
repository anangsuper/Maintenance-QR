<?php
/**
 * Modul Memo Internal Pengajuan Inventaris & Sarana Prasarana
 * PT BPR Mitratama Arthabuana
 */

function ensure_memo_schema(): void {
    static $ensured = false;
    if ($ensured) return;
    $ensured = true;

    if (!is_google_cloud_mode()) {
        try {
            db()->exec("
                CREATE TABLE IF NOT EXISTS memo_pengajuan_inventaris (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nomor_memo VARCHAR(120) NOT NULL,
                    tanggal_memo DATE NOT NULL,
                    kepada VARCHAR(150) NOT NULL DEFAULT 'Direksi',
                    dari_jabatan VARCHAR(150) NOT NULL,
                    id_cabang INT DEFAULT 0,
                    cabang_nama VARCHAR(100) NOT NULL,
                    perihal VARCHAR(255) NOT NULL,
                    lampiran VARCHAR(50) DEFAULT '-',
                    isi_memo TEXT NOT NULL,
                    total_estimasi DECIMAL(15,2) NOT NULL DEFAULT 0,
                    terbilang VARCHAR(255) DEFAULT '',
                    nama_pengaju VARCHAR(100) NOT NULL,
                    jabatan_pengaju VARCHAR(100) NOT NULL,
                    nama_mengetahui VARCHAR(100) NOT NULL DEFAULT 'Defie Damayanti',
                    jabatan_mengetahui VARCHAR(100) NOT NULL DEFAULT 'Kabag. Umum & SDM',
                    nama_menyetujui VARCHAR(100) NOT NULL DEFAULT 'Kahar Muzaqir, SH',
                    jabatan_menyetujui VARCHAR(100) NOT NULL DEFAULT 'Direktur Operasional',
                    status VARCHAR(50) NOT NULL DEFAULT 'Diajukan',
                    user_id INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (nomor_memo),
                    INDEX (id_cabang)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        } catch (Throwable $e) {
            // Ignore schema error
        }
    }
}

/**
 * Konversi angka nominal uang ke kalimat terbilang bahasa Indonesia
 */
function terbilang_angka($angka): string {
    $angka = abs((float)$angka);
    $bilangan = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
    $hasil = '';

    if ($angka < 12) {
        $hasil = $bilangan[(int)$angka];
    } elseif ($angka < 20) {
        $hasil = terbilang_angka($angka - 10) . ' Belas';
    } elseif ($angka < 100) {
        $hasil = terbilang_angka($angka / 10) . ' Puluh ' . terbilang_angka($angka % 10);
    } elseif ($angka < 200) {
        $hasil = 'Seratus ' . terbilang_angka($angka - 100);
    } elseif ($angka < 1000) {
        $hasil = terbilang_angka($angka / 100) . ' Ratus ' . terbilang_angka($angka % 100);
    } elseif ($angka < 2000) {
        $hasil = 'Seribu ' . terbilang_angka($angka - 1000);
    } elseif ($angka < 1000000) {
        $hasil = terbilang_angka($angka / 1000) . ' Ribu ' . terbilang_angka($angka % 1000);
    } elseif ($angka < 1000000000) {
        $hasil = terbilang_angka($angka / 1000000) . ' Juta ' . terbilang_angka($angka % 1000000);
    } elseif ($angka < 1000000000000) {
        $hasil = terbilang_angka($angka / 1000000000) . ' Milyar ' . terbilang_angka(fmod($angka, 1000000000));
    } else {
        $hasil = terbilang_angka($angka / 1000000000000) . ' Triliun ' . terbilang_angka(fmod($angka, 1000000000000));
    }

    return trim(preg_replace('/\s+/', ' ', $hasil));
}

function terbilang_rupiah($angka): string {
    $val = (float)$angka;
    if ($val <= 0) return 'Nol Rupiah';
    return terbilang_angka($val) . ' Rupiah';
}

function romawi_bulan_memo(int $m): string {
    $romawi = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
    ];
    return $romawi[$m] ?? 'I';
}

function resolve_kode_cabang_memo(string $cabangNama): string {
    $cn = strtolower($cabangNama);
    if (str_contains($cn, 'batulicin')) return 'KC.BTL';
    if (str_contains($cn, 'martapura')) return 'KC.MTP';
    if (str_contains($cn, 'tanjung')) return 'KC.TJG';
    if (str_contains($cn, 'handil')) return 'KC.HDL';
    if (str_contains($cn, 'pusat') || str_contains($cn, 'kpo')) return 'KPO';
    if (str_contains($cn, 'kas')) return 'KK';
    return 'KC';
}

function generate_nomor_memo(string $cabangNama = 'Batulicin'): string {
    ensure_memo_schema();
    $m = (int)date('n');
    $y = (int)date('Y');
    $romawi = romawi_bulan_memo($m);
    $kdCabang = resolve_kode_cabang_memo($cabangNama);

    $countThisYear = 1;
    if (!is_google_cloud_mode()) {
        try {
            $st = db()->query("SELECT COUNT(*) FROM memo_pengajuan_inventaris WHERE YEAR(tanggal_memo) = {$y}");
            $countThisYear = ($st ? (int)$st->fetchColumn() : 0) + 1;
        } catch (Throwable $e) {
            $countThisYear = 1;
        }
    }

    $numStr = str_pad((string)$countThisYear, 3, '0', STR_PAD_LEFT);
    return "{$numStr}/INT/MI/OPS/BANK-MITRA.{$kdCabang}/{$romawi}/{$y}";
}

function get_all_memo(?array $filters = []): array {
    ensure_memo_schema();
    $results = [];

    if (!is_google_cloud_mode()) {
        try {
            $sql = "SELECT * FROM memo_pengajuan_inventaris ORDER BY id DESC";
            $st = db()->query($sql);
            if ($st) {
                $results = $st->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {
            $results = [];
        }
    }

    if (!empty($filters['q'])) {
        $q = strtolower(trim((string)$filters['q']));
        $results = array_filter($results, function($r) use ($q) {
            return str_contains(strtolower($r['nomor_memo']), $q)
                || str_contains(strtolower($r['perihal']), $q)
                || str_contains(strtolower($r['cabang_nama']), $q)
                || str_contains(strtolower($r['nama_pengaju']), $q);
        });
    }

    if (!empty($filters['cabang'])) {
        $c = strtolower(trim((string)$filters['cabang']));
        $results = array_filter($results, fn($r) => strtolower($r['cabang_nama']) === $c);
    }

    return array_values($results);
}

function get_memo_by_id(int $id): ?array {
    ensure_memo_schema();
    if ($id <= 0) return null;

    if (!is_google_cloud_mode()) {
        try {
            $st = db()->prepare("SELECT * FROM memo_pengajuan_inventaris WHERE id = ? LIMIT 1");
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
    return null;
}

function save_memo_pengajuan(array $data): array {
    ensure_memo_schema();

    $id = (int)($data['id'] ?? 0);
    $cabang = trim((string)($data['cabang_nama'] ?? 'Batulicin'));
    $nomor = trim((string)($data['nomor_memo'] ?? ''));
    if ($nomor === '') {
        $nomor = generate_nomor_memo($cabang);
    }
    $tgl = trim((string)($data['tanggal_memo'] ?? date('Y-m-d')));
    $kepada = trim((string)($data['kepada'] ?? 'Direksi'));
    $dari = trim((string)($data['dari_jabatan'] ?? ('Kepala Cabang Kantor ' . $cabang)));
    $perihal = trim((string)($data['perihal'] ?? ''));
    $lampiran = trim((string)($data['lampiran'] ?? '-'));
    $isi = trim((string)($data['isi_memo'] ?? ''));
    $total = (float)($data['total_estimasi'] ?? 0);
    $terbilang = trim((string)($data['terbilang'] ?? ''));
    if ($terbilang === '' && $total > 0) {
        $terbilang = '//' . terbilang_rupiah($total) . '//';
    }

    $pengaju = trim((string)($data['nama_pengaju'] ?? ''));
    $jabatanPengaju = trim((string)($data['jabatan_pengaju'] ?? 'Kepala Cabang'));
    $mengetahui = trim((string)($data['nama_mengetahui'] ?? 'Defie Damayanti'));
    $jabatanMengetahui = trim((string)($data['jabatan_mengetahui'] ?? 'Kabag. Umum & SDM'));
    $menyetujui = trim((string)($data['nama_menyetujui'] ?? 'Kahar Muzaqir, SH'));
    $jabatanMenyetujui = trim((string)($data['jabatan_menyetujui'] ?? 'Direktur Operasional'));
    $status = trim((string)($data['status'] ?? 'Diajukan'));
    $userId = (int)($_SESSION['user_id'] ?? 0);

    if ($perihal === '') {
        return ['success' => false, 'error' => 'Perihal permohonan wajib diisi.'];
    }
    if ($isi === '') {
        return ['success' => false, 'error' => 'Uraian pengajuan memo wajib diisi.'];
    }

    if (!is_google_cloud_mode()) {
        try {
            if ($id > 0) {
                $st = db()->prepare("
                    UPDATE memo_pengajuan_inventaris SET
                        nomor_memo = ?, tanggal_memo = ?, kepada = ?, dari_jabatan = ?,
                        cabang_nama = ?, perihal = ?, lampiran = ?, isi_memo = ?,
                        total_estimasi = ?, terbilang = ?, nama_pengaju = ?, jabatan_pengaju = ?,
                        nama_mengetahui = ?, jabatan_mengetahui = ?, nama_menyetujui = ?, jabatan_menyetujui = ?,
                        status = ?
                    WHERE id = ?
                ");
                $st->execute([
                    $nomor, $tgl, $kepada, $dari,
                    $cabang, $perihal, $lampiran, $isi,
                    $total, $terbilang, $pengaju, $jabatanPengaju,
                    $mengetahui, $jabatanMengetahui, $menyetujui, $jabatanMenyetujui,
                    $status, $id
                ]);
                $memoId = $id;
            } else {
                $st = db()->prepare("
                    INSERT INTO memo_pengajuan_inventaris (
                        nomor_memo, tanggal_memo, kepada, dari_jabatan,
                        cabang_nama, perihal, lampiran, isi_memo,
                        total_estimasi, terbilang, nama_pengaju, jabatan_pengaju,
                        nama_mengetahui, jabatan_mengetahui, nama_menyetujui, jabatan_menyetujui,
                        status, user_id
                    ) VALUES (
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?
                    )
                ");
                $st->execute([
                    $nomor, $tgl, $kepada, $dari,
                    $cabang, $perihal, $lampiran, $isi,
                    $total, $terbilang, $pengaju, $jabatanPengaju,
                    $mengetahui, $jabatanMengetahui, $menyetujui, $jabatanMenyetujui,
                    $status, $userId
                ]);
                $memoId = (int)db()->lastInsertId();
            }

            return ['success' => true, 'id' => $memoId, 'nomor_memo' => $nomor];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    return ['success' => false, 'error' => 'Mode sistem belum mendukung penyimpanan memo.'];
}
