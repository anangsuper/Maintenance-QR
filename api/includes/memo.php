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
                    lampiran_file VARCHAR(255) DEFAULT '',
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

            // Migration: pastikan kolom pendukung dan realisasi ada jika tabel sudah dibuat
            $chk = function(string $col, string $def) {
                $has = db()->query("SHOW COLUMNS FROM memo_pengajuan_inventaris LIKE '{$col}'")->fetchAll();
                if (empty($has)) {
                    db()->exec("ALTER TABLE memo_pengajuan_inventaris ADD COLUMN {$col} {$def}");
                }
            };
            $chk('lampiran_file', "VARCHAR(255) DEFAULT '' AFTER lampiran");
            $chk('bukti_beli_file', "VARCHAR(255) DEFAULT '' AFTER lampiran_file");
            $chk('foto_barang_file', "VARCHAR(255) DEFAULT '' AFTER bukti_beli_file");
            $chk('biaya_realisasi', "DECIMAL(15,2) DEFAULT NULL AFTER total_estimasi");
            $chk('tanggal_beli', "DATE DEFAULT NULL AFTER tanggal_memo");
            $chk('catatan_realisasi', "TEXT DEFAULT NULL AFTER isi_memo");
        } catch (Throwable $e) {
            // Ignore schema error
        }
    }
}

/**
 * URL absolut/relatif file lampiran memo / nota
 */
function memo_file_url(?string $path): string {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
        return $path;
    }
    $clean = ltrim($path, '/');
    return module_url($clean);
}

/**
 * Upload berkas memo generik (PDF, JPG, PNG, WEBP)
 */
function upload_memo_file(string $fileKey, string $prefix = 'memo'): ?string {
    if (empty($_FILES[$fileKey]) || empty($_FILES[$fileKey]['tmp_name']) || !is_uploaded_file($_FILES[$fileKey]['tmp_name'])) {
        return null;
    }
    $file = $_FILES[$fileKey];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $maxSize = 25 * 1024 * 1024; // 25 MB
    if ($file['size'] > $maxSize) {
        return null;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowedExts, true)) {
        return null;
    }

    $uploadDir = dirname(__DIR__) . '/uploads/memo/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
        @chmod($uploadDir, 0777);
    }

    $safeName = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $uploadDir . $safeName;

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && function_exists('compress_and_save_uploaded_image')) {
        if (compress_and_save_uploaded_image($file['tmp_name'], $targetPath, 1800, 85)) {
            @chmod($targetPath, 0666);
            return 'uploads/memo/' . $safeName;
        }
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        @chmod($targetPath, 0666);
        return 'uploads/memo/' . $safeName;
    }
    return null;
}

function upload_memo_lampiran(string $fileKey = 'lampiran_file'): ?string {
    return upload_memo_file($fileKey, 'penawaran');
}

function upload_memo_bukti(string $fileKey = 'bukti_beli_file'): ?string {
    return upload_memo_file($fileKey, 'nota');
}

function upload_memo_foto_barang(string $fileKey = 'foto_barang_file'): ?string {
    return upload_memo_file($fileKey, 'barang');
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

function get_next_memo_sequence(?int $year = null): int {
    ensure_memo_schema();
    $y = $year ?: (int)date('Y');
    if (!is_google_cloud_mode()) {
        try {
            $st = db()->query("
                SELECT MAX(CAST(SUBSTRING_INDEX(nomor_memo, '/', 1) AS UNSIGNED)) as max_num 
                FROM memo_pengajuan_inventaris 
                WHERE YEAR(tanggal_memo) = {$y} OR nomor_memo LIKE '%/{$y}'
            ");
            $maxNum = $st ? (int)$st->fetchColumn() : 0;
            return max($maxNum + 1, 1);
        } catch (Throwable $e) {
            return 1;
        }
    }
    return 1;
}

function generate_nomor_memo(string $cabangNama = 'Batulicin', ?int $customSeq = null): string {
    ensure_memo_schema();
    $m = (int)date('n');
    $y = (int)date('Y');
    $romawi = romawi_bulan_memo($m);
    $kdCabang = resolve_kode_cabang_memo($cabangNama);

    $seq = $customSeq !== null ? $customSeq : get_next_memo_sequence($y);
    $numStr = str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
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
    } elseif ($id === 0 && !is_google_cloud_mode()) {
        try {
            $stCheck = db()->prepare("SELECT COUNT(*) FROM memo_pengajuan_inventaris WHERE nomor_memo = ?");
            $stCheck->execute([$nomor]);
            if ((int)$stCheck->fetchColumn() > 0) {
                $nomor = generate_nomor_memo($cabang);
            }
        } catch (Throwable $ignore) {}
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

    $lampiranFile = trim((string)($data['lampiran_file'] ?? ''));

    if (!is_google_cloud_mode()) {
        try {
            if ($id > 0) {
                // Jika tidak ada upload file baru, pertahankan file lama
                if ($lampiranFile === '') {
                    $old = get_memo_by_id($id);
                    $lampiranFile = (string)($old['lampiran_file'] ?? '');
                }

                $st = db()->prepare("
                    UPDATE memo_pengajuan_inventaris SET
                        nomor_memo = ?, tanggal_memo = ?, kepada = ?, dari_jabatan = ?,
                        cabang_nama = ?, perihal = ?, lampiran = ?, lampiran_file = ?, isi_memo = ?,
                        total_estimasi = ?, terbilang = ?, nama_pengaju = ?, jabatan_pengaju = ?,
                        nama_mengetahui = ?, jabatan_mengetahui = ?, nama_menyetujui = ?, jabatan_menyetujui = ?,
                        status = ?
                    WHERE id = ?
                ");
                $st->execute([
                    $nomor, $tgl, $kepada, $dari,
                    $cabang, $perihal, $lampiran, $lampiranFile, $isi,
                    $total, $terbilang, $pengaju, $jabatanPengaju,
                    $mengetahui, $jabatanMengetahui, $menyetujui, $jabatanMenyetujui,
                    $status, $id
                ]);
                $memoId = $id;
            } else {
                $st = db()->prepare("
                    INSERT INTO memo_pengajuan_inventaris (
                        nomor_memo, tanggal_memo, kepada, dari_jabatan,
                        cabang_nama, perihal, lampiran, lampiran_file, isi_memo,
                        total_estimasi, terbilang, nama_pengaju, jabatan_pengaju,
                        nama_mengetahui, jabatan_mengetahui, nama_menyetujui, jabatan_menyetujui,
                        status, user_id
                    ) VALUES (
                        ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?
                    )
                ");
                $st->execute([
                    $nomor, $tgl, $kepada, $dari,
                    $cabang, $perihal, $lampiran, $lampiranFile, $isi,
                    $total, $terbilang, $pengaju, $jabatanPengaju,
                    $mengetahui, $jabatanMengetahui, $menyetujui, $jabatanMenyetujui,
                    $status, $userId
                ]);
                $memoId = (int)db()->lastInsertId();
            }

            return ['success' => true, 'id' => $memoId, 'nomor_memo' => $nomor, 'lampiran_file' => $lampiranFile];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    return ['success' => false, 'error' => 'Mode sistem belum mendukung penyimpanan memo.'];
}

/**
 * Simpan realisasi pembelian barang & nota fisik setelah disetujui
 */
function save_memo_realisasi(array $data): array {
    ensure_memo_schema();
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) {
        return ['success' => false, 'error' => 'ID memo tidak valid.'];
    }

    $old = get_memo_by_id($id);
    if (!$old) {
        return ['success' => false, 'error' => 'Dokumen memo tidak ditemukan.'];
    }

    $tanggalBeli = !empty($data['tanggal_beli']) ? trim((string)$data['tanggal_beli']) : date('Y-m-d');
    $biayaRealisasi = (float)($data['biaya_realisasi'] ?? 0);
    if ($biayaRealisasi <= 0) {
        $biayaRealisasi = (float)($old['biaya_realisasi'] ?: $old['total_estimasi']);
    }

    $catatanRealisasi = trim((string)($data['catatan_realisasi'] ?? ($old['catatan_realisasi'] ?? '')));
    $buktiBeliFile = trim((string)($data['bukti_beli_file'] ?? ''));
    if ($buktiBeliFile === '') {
        $buktiBeliFile = (string)($old['bukti_beli_file'] ?? '');
    }

    $fotoBarangFile = trim((string)($data['foto_barang_file'] ?? ''));
    if ($fotoBarangFile === '') {
        $fotoBarangFile = (string)($old['foto_barang_file'] ?? '');
    }

    $status = !empty($data['status']) ? trim((string)$data['status']) : 'Selesai Dibeli';

    try {
        $st = db()->prepare("
            UPDATE memo_pengajuan_inventaris SET
                tanggal_beli = ?,
                biaya_realisasi = ?,
                bukti_beli_file = ?,
                foto_barang_file = ?,
                catatan_realisasi = ?,
                status = ?
            WHERE id = ?
        ");
        $st->execute([
            $tanggalBeli,
            $biayaRealisasi,
            $buktiBeliFile,
            $fotoBarangFile,
            $catatanRealisasi,
            $status,
            $id
        ]);

        return [
            'success' => true,
            'id' => $id,
            'bukti_beli_file' => $buktiBeliFile,
            'foto_barang_file' => $fotoBarangFile
        ];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}
