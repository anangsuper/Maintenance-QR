<?php
/**
 * Modul Pengelolaan Penghapusan & Pemusnahan Aset Inventaris (Asset Disposal / BA-Afkir)
 * PT BPR Mitratama Arthabuana - Bagian SDM & Umum & IT Operations
 * Sesuai Kebijakan Bank: Aset tidak diperjualbelikan, murni penghapusan/pemusnahan barang rusak
 */

function ensure_disposal_schema(): void {
    static $ensured = false;
    if ($ensured) return;
    $ensured = true;

    try {
        $pdo = db();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS asset_disposals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nomor_ba VARCHAR(80) NOT NULL UNIQUE,
                tanggal_disposal DATE NOT NULL,
                jenis_tindakan VARCHAR(60) NOT NULL DEFAULT 'Pemusnahan / Afkir Fisik',
                asset_type VARCHAR(20) NOT NULL DEFAULT 'inventaris',
                asset_id INT NOT NULL DEFAULT 0,
                kode_aset VARCHAR(100) NOT NULL,
                nama_aset VARCHAR(255) NOT NULL,
                merk_model VARCHAR(255) DEFAULT '',
                serial_number VARCHAR(100) DEFAULT '',
                cabang VARCHAR(100) NOT NULL DEFAULT 'Kantor Pusat (KPO)',
                lokasi_ruangan VARCHAR(150) DEFAULT '',
                pengguna_terakhir VARCHAR(150) DEFAULT '',
                kondisi_terakhir VARCHAR(100) DEFAULT 'Rusak Berat / Tidak Ekonomis Diperbaiki',
                alasan_penghapusan TEXT NOT NULL,
                metode_pemusnahan VARCHAR(100) DEFAULT 'Dihancurkan / Dibuang Fisik',
                foto_barang VARCHAR(255) DEFAULT '',
                dokumen_ba_disetujui VARCHAR(255) DEFAULT '',
                petugas_nama VARCHAR(100) NOT NULL DEFAULT 'Staf Bagian Umum',
                petugas_jabatan VARCHAR(100) DEFAULT 'Staf Bagian Umum',
                nama_mengetahui VARCHAR(100) DEFAULT 'Defie Damayanti',
                jabatan_mengetahui VARCHAR(100) DEFAULT 'Kabag. Umum & SDM',
                nama_menyetujui VARCHAR(100) DEFAULT 'Kahar Muzaqir, SH',
                jabatan_menyetujui VARCHAR(100) DEFAULT 'Direktur Operasional',
                status_disposal VARCHAR(50) DEFAULT 'Selesai',
                keterangan_tambahan TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (asset_id),
                INDEX (nomor_ba),
                INDEX (jenis_tindakan),
                INDEX (tanggal_disposal)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("ALTER TABLE asset_disposals ADD COLUMN IF NOT EXISTS metode_pemusnahan VARCHAR(100) DEFAULT 'Dihancurkan / Dibuang Fisik';");
    } catch (Throwable $e) {
        error_log('ensure_disposal_schema error: ' . $e->getMessage());
    }
}

/**
 * Upload file pendukung penghapusan aset (foto fisik, scan BA disetujui)
 */
function upload_disposal_file(string $fileKey, string $prefix = 'disposal'): ?string {
    if (empty($_FILES[$fileKey]) || empty($_FILES[$fileKey]['tmp_name']) || !is_uploaded_file($_FILES[$fileKey]['tmp_name'])) {
        return null;
    }

    $file = $_FILES[$fileKey];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        return null;
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        return null;
    }

    $uploadDir = dirname(__DIR__) . '/uploads/disposal/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
        @chmod($uploadDir, 0777);
    }

    $safeName = sprintf('%s_%s_%s.%s', $prefix, date('Ymd_His'), bin2hex(random_bytes(4)), $ext);
    $targetPath = $uploadDir . $safeName;

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && function_exists('compress_and_save_uploaded_image')) {
        if (compress_and_save_uploaded_image($file['tmp_name'], $targetPath, 1800, 85)) {
            @chmod($targetPath, 0666);
            return 'uploads/disposal/' . $safeName;
        }
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        @chmod($targetPath, 0666);
        return 'uploads/disposal/' . $safeName;
    }

    return null;
}

function disposal_file_url(?string $path): string {
    if (empty($path)) return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    return module_url(ltrim($path, '/'));
}

/**
 * Generate nomor resmi Berita Acara Penghapusan & Pemusnahan Aset Bank Mitra
 * Contoh: 001/BA-AFKIR/SDM-UMUM/BANK-MITRA.KPO/X/2026
 */
function generate_nomor_ba_disposal(string $cabangCode = 'KPO', ?string $tgl = null): string {
    ensure_disposal_schema();
    $ts = !empty($tgl) ? strtotime($tgl) : time();
    $bulanRomawi = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
    ];
    $romawi = $bulanRomawi[(int)date('n', $ts)] ?? 'X';
    $tahun = date('Y', $ts);

    $cleanCabang = strtoupper(trim($cabangCode));
    if (str_contains($cleanCabang, 'BATULICIN')) $cTag = 'BATULICIN';
    elseif (str_contains($cleanCabang, 'MARTAPURA')) $cTag = 'MARTAPURA';
    elseif (str_contains($cleanCabang, 'TANJUNG')) $cTag = 'TANJUNG';
    elseif (str_contains($cleanCabang, 'HANDIL')) $cTag = 'HANDIL-BAKTI';
    else $cTag = 'KPO';

    try {
        $st = db()->prepare("
            SELECT COUNT(*) FROM asset_disposals 
            WHERE YEAR(tanggal_disposal) = ?
        ");
        $st->execute([(int)$tahun]);
        $seq = ((int)$st->fetchColumn()) + 1;
    } catch (Throwable $e) {
        $seq = 1;
    }

    return sprintf('%03d/BA-AFKIR/SDM-UMUM/BANK-MITRA.%s/%s/%s', $seq, $cTag, $romawi, $tahun);
}

/**
 * Ambil daftar data penghapusan aset
 */
function get_disposal_list(array $filters = []): array {
    ensure_disposal_schema();
    try {
        $pdo = db();
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = '(nomor_ba LIKE ? OR nama_aset LIKE ? OR kode_aset LIKE ? OR alasan_penghapusan LIKE ?)';
            $params = array_merge($params, [$s, $s, $s, $s]);
        }

        if (!empty($filters['cabang']) && $filters['cabang'] !== 'all') {
            $where[] = 'cabang LIKE ?';
            $params[] = '%' . trim($filters['cabang']) . '%';
        }

        if (!empty($filters['tahun'])) {
            $where[] = 'YEAR(tanggal_disposal) = ?';
            $params[] = (int)$filters['tahun'];
        }

        $sql = "SELECT * FROM asset_disposals";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY id DESC";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('get_disposal_list error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Ambil single data penghapusan aset berdasarkan ID
 */
function get_disposal_by_id(int $id): ?array {
    ensure_disposal_schema();
    try {
        $st = db()->prepare("SELECT * FROM asset_disposals WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Ringkasan KPI Penghapusan & Pemusnahan Aset (BA-Afkir)
 */
function get_disposal_kpi_summary(): array {
    ensure_disposal_schema();
    $summary = [
        'total_unit' => 0,
        'total_sdm' => 0,
        'total_it' => 0,
        'total_bulan_ini' => 0,
    ];

    try {
        $rows = db()->query("
            SELECT 
                COUNT(*) as total_unit,
                SUM(CASE WHEN asset_type = 'inventaris' THEN 1 ELSE 0 END) as total_sdm,
                SUM(CASE WHEN asset_type = 'komputer' THEN 1 ELSE 0 END) as total_it,
                SUM(CASE WHEN MONTH(tanggal_disposal) = MONTH(CURRENT_DATE()) AND YEAR(tanggal_disposal) = YEAR(CURRENT_DATE()) THEN 1 ELSE 0 END) as total_bulan_ini
            FROM asset_disposals
        ")->fetch(PDO::FETCH_ASSOC);

        if ($rows) {
            $summary['total_unit'] = (int)($rows['total_unit'] ?? 0);
            $summary['total_sdm'] = (int)($rows['total_sdm'] ?? 0);
            $summary['total_it'] = (int)($rows['total_it'] ?? 0);
            $summary['total_bulan_ini'] = (int)($rows['total_bulan_ini'] ?? 0);
        }
    } catch (Throwable $e) {
        // fallback
    }

    return $summary;
}

/**
 * Simpan Data Penghapusan / Pemusnahan Aset
 */
function save_asset_disposal(array $data, array $files = []): array {
    ensure_disposal_schema();
    $pdo = db();

    $id = (int)($data['id'] ?? 0);
    $assetType = trim((string)($data['asset_type'] ?? 'inventaris'));
    $assetId = (int)($data['asset_id'] ?? 0);
    $jenisTindakan = 'Pemusnahan / Afkir Fisik';
    $tanggal = trim((string)($data['tanggal_disposal'] ?? date('Y-m-d')));
    $alasan = trim((string)($data['alasan_penghapusan'] ?? ''));
    $metodePemusnahan = trim((string)($data['metode_pemusnahan'] ?? 'Dihancurkan / Dibuang Fisik'));

    if (empty($alasan)) {
        return ['success' => false, 'message' => 'Alasan pertimbangan teknis penghapusan aset wajib diisi.'];
    }

    // Ambil detail aset dari database
    $kodeAset = trim((string)($data['kode_aset'] ?? ''));
    $namaAset = trim((string)($data['nama_aset'] ?? ''));
    $merkModel = trim((string)($data['merk_model'] ?? ''));
    $serialNumber = trim((string)($data['serial_number'] ?? ''));
    $cabang = trim((string)($data['cabang'] ?? 'Kantor Pusat (KPO)'));
    $lokasiRuangan = trim((string)($data['lokasi_ruangan'] ?? ''));
    $penggunaTerakhir = trim((string)($data['pengguna_terakhir'] ?? ''));

    if ($assetId > 0 && ($namaAset === '' || $kodeAset === '')) {
        if ($assetType === 'inventaris') {
            $st = $pdo->prepare("SELECT * FROM inventaris_kartu WHERE id = ?");
            $st->execute([$assetId]);
            $item = $st->fetch(PDO::FETCH_ASSOC);
            if ($item) {
                $kodeAset = $item['nomor_rekening'];
                $namaAset = $item['nama_barang'];
                $cabang = $item['lokasi'] ?: 'Kantor Pusat (KPO)';
                $lokasiRuangan = $item['ruangan'] ?? '';
                $penggunaTerakhir = $item['pengguna'] ?? 'Umum / Pool';
            }
        } else {
            $st = $pdo->prepare("SELECT a.*, c.nama_cabang FROM assets a LEFT JOIN cabang c ON a.id_cabang = c.id WHERE a.id = ?");
            $st->execute([$assetId]);
            $item = $st->fetch(PDO::FETCH_ASSOC);
            if ($item) {
                $kodeAset = $item['kode_inventaris'];
                $namaAset = trim(($item['merk'] ?? '') . ' ' . ($item['model'] ?? ''));
                $merkModel = trim(($item['merk'] ?? '') . ' ' . ($item['model'] ?? ''));
                $serialNumber = $item['serial_number'] ?? '';
                $cabang = $item['nama_cabang'] ?: 'Kantor Pusat (KPO)';
                $lokasiRuangan = $item['ruangan'] ?? '';
            }
        }
    }

    // Nomor Berita Acara
    $nomorBa = trim((string)($data['nomor_ba'] ?? ''));
    if (empty($nomorBa)) {
        $nomorBa = generate_nomor_ba_disposal($cabang, $tanggal);
    }

    $kondisiTerakhir = trim((string)($data['kondisi_terakhir'] ?? 'Rusak Berat / Tidak Ekonomis Diperbaiki'));
    $petugasNama = trim((string)($data['petugas_nama'] ?? (current_user_name() ?: 'Staf Bagian Umum')));
    $petugasJabatan = trim((string)($data['petugas_jabatan'] ?? 'Staf Bagian Umum'));
    $namaMengetahui = trim((string)($data['nama_mengetahui'] ?? 'Defie Damayanti'));
    $jabatanMengetahui = trim((string)($data['jabatan_mengetahui'] ?? 'Kabag. Umum & SDM'));
    $namaMenyetujui = trim((string)($data['nama_menyetujui'] ?? 'Kahar Muzaqir, SH'));
    $jabatanMenyetujui = trim((string)($data['jabatan_menyetujui'] ?? 'Direktur Operasional'));
    $keteranganTambahan = trim((string)($data['keterangan_tambahan'] ?? ''));

    // Handle File Uploads
    $fotoBarang = upload_disposal_file('foto_barang', 'afkir_foto');
    $dokumenBaDisetujui = upload_disposal_file('dokumen_ba_disetujui', 'afkir_ba_acc');

    try {
        if ($id > 0) {
            $existing = get_disposal_by_id($id);
            if (!$existing) {
                return ['success' => false, 'message' => 'Data Berita Acara tidak ditemukan.'];
            }
            if (!$fotoBarang) $fotoBarang = $existing['foto_barang'];
            if (!$dokumenBaDisetujui) $dokumenBaDisetujui = $existing['dokumen_ba_disetujui'];

            $stmt = $pdo->prepare("
                UPDATE asset_disposals SET
                    nomor_ba = ?, tanggal_disposal = ?, jenis_tindakan = ?, asset_type = ?,
                    asset_id = ?, kode_aset = ?, nama_aset = ?, merk_model = ?, serial_number = ?,
                    cabang = ?, lokasi_ruangan = ?, pengguna_terakhir = ?, kondisi_terakhir = ?,
                    alasan_penghapusan = ?, metode_pemusnahan = ?, foto_barang = ?, dokumen_ba_disetujui = ?,
                    petugas_nama = ?, petugas_jabatan = ?, nama_mengetahui = ?, jabatan_mengetahui = ?,
                    nama_menyetujui = ?, jabatan_menyetujui = ?, keterangan_tambahan = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $nomorBa, $tanggal, $jenisTindakan, $assetType,
                $assetId, $kodeAset, $namaAset, $merkModel, $serialNumber,
                $cabang, $lokasiRuangan, $penggunaTerakhir, $kondisiTerakhir,
                $alasan, $metodePemusnahan, $fotoBarang, $dokumenBaDisetujui,
                $petugasNama, $petugasJabatan, $namaMengetahui, $jabatanMengetahui,
                $namaMenyetujui, $jabatanMenyetujui, $keteranganTambahan,
                $id
            ]);
            $disposalId = $id;
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO asset_disposals (
                    nomor_ba, tanggal_disposal, jenis_tindakan, asset_type,
                    asset_id, kode_aset, nama_aset, merk_model, serial_number,
                    cabang, lokasi_ruangan, pengguna_terakhir, kondisi_terakhir,
                    alasan_penghapusan, metode_pemusnahan, foto_barang, dokumen_ba_disetujui,
                    petugas_nama, petugas_jabatan, nama_mengetahui, jabatan_mengetahui,
                    nama_menyetujui, jabatan_menyetujui, keterangan_tambahan
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?
                )
            ");
            $stmt->execute([
                $nomorBa, $tanggal, $jenisTindakan, $assetType,
                $assetId, $kodeAset, $namaAset, $merkModel, $serialNumber,
                $cabang, $lokasiRuangan, $penggunaTerakhir, $kondisiTerakhir,
                $alasan, $metodePemusnahan, $fotoBarang, $dokumenBaDisetujui,
                $petugasNama, $petugasJabatan, $namaMengetahui, $jabatanMengetahui,
                $namaMenyetujui, $jabatanMenyetujui, $keteranganTambahan
            ]);
            $disposalId = (int)$pdo->lastInsertId();
        }

        // UPDATE STATUS ASLI ASET DI DATABASE MENJADI DIHAPUSKAN / AFKIR
        $logKet = sprintf("\n[DIAFKIRKAN / DIHAPUS: No. BA: %s | Tgl: %s | Alasan: %s]", $nomorBa, $tanggal, $alasan);

        if ($assetId > 0) {
            if ($assetType === 'inventaris') {
                $stUp = $pdo->prepare("
                    UPDATE inventaris_kartu 
                    SET status_inventaris = '3 : Dihapuskan', keterangan = CONCAT(COALESCE(keterangan, ''), ?)
                    WHERE id = ?
                ");
                $stUp->execute([$logKet, $assetId]);
            } else {
                $stUp = $pdo->prepare("
                    UPDATE assets 
                    SET status = 'Dihapuskan', keterangan = CONCAT(COALESCE(keterangan, ''), ?)
                    WHERE id = ?
                ");
                $stUp->execute([$logKet, $assetId]);
            }
        }

        // Catat ke Audit Trail
        if (function_exists('log_system_audit')) {
            log_system_audit(
                'ASSET_DISPOSAL',
                "Pencatatan Berita Acara Penghapusan Aset (BA-Afkir): {$namaAset} ({$kodeAset}) - No. BA: {$nomorBa}",
                [
                    'disposal_id' => $disposalId,
                    'nomor_ba' => $nomorBa,
                    'jenis_tindakan' => $jenisTindakan
                ]
            );
        }

        return [
            'success' => true,
            'id' => $disposalId,
            'nomor_ba' => $nomorBa,
            'message' => "Berita Acara Penghapusan Aset (BA-Afkir) berhasil dicatat dengan Nomor {$nomorBa}."
        ];
    } catch (Throwable $e) {
        error_log('save_asset_disposal error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Gagal menyimpan Berita Acara: ' . $e->getMessage()];
    }
}

/**
 * Batalkan / Hapus Data Berita Acara Penghapusan & Pulihkan Status Aset ke Aktif
 */
function delete_asset_disposal(int $id, bool $restoreAssetStatus = true): bool {
    ensure_disposal_schema();
    $item = get_disposal_by_id($id);
    if (!$item) return false;

    try {
        $pdo = db();
        if ($restoreAssetStatus && $item['asset_id'] > 0) {
            if ($item['asset_type'] === 'inventaris') {
                $pdo->prepare("UPDATE inventaris_kartu SET status_inventaris = '1 : Aktif' WHERE id = ?")->execute([$item['asset_id']]);
            } else {
                $pdo->prepare("UPDATE assets SET status = 'Aktif' WHERE id = ?")->execute([$item['asset_id']]);
            }
        }
        $st = $pdo->prepare("DELETE FROM asset_disposals WHERE id = ?");
        $st->execute([$id]);

        if (function_exists('log_system_audit')) {
            log_system_audit('ASSET_DISPOSAL_DELETE', "Pembatalan Berita Acara BA-Afkir No. {$item['nomor_ba']} untuk {$item['nama_aset']}");
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
