<?php
/**
 * Modul Kepatuhan SDM & Tata Kelola OJK
 * PT BPR Mitratama Arthabuana
 * 
 * Mengakomodasi:
 * 1. Sertifikasi Profesi OJK (SKKNI / LSP) - POJK Tata Kelola
 * 2. Anggaran & Realisasi Pelatihan Wajib 5% - POJK No. 47/POJK.03/2017 & APU PPT
 * 3. Mandatory Block Leave (Cuti Wajib 5 Hari) - POJK No. 62/POJK.03/2020 & Strategi Anti-Fraud
 */

function ensure_sdm_kepatuhan_schema(): void {
    static $ensured = false;
    if ($ensured) return;
    $ensured = true;

    if (!is_google_cloud_mode()) {
        try {
            // 1. Tabel Sertifikasi Profesi
            db()->exec("
                CREATE TABLE IF NOT EXISTS sdm_sertifikasi (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nama_pegawai VARCHAR(150) NOT NULL,
                    nip VARCHAR(50) DEFAULT '',
                    jabatan VARCHAR(100) NOT NULL,
                    cabang_nama VARCHAR(100) NOT NULL,
                    bidang_sertifikasi VARCHAR(150) NOT NULL,
                    lembaga_penerbit VARCHAR(150) NOT NULL DEFAULT 'LSP Certif',
                    nomor_sertifikat VARCHAR(120) NOT NULL,
                    tanggal_terbit DATE NOT NULL,
                    tanggal_kadaluarsa DATE NOT NULL,
                    file_sertifikat VARCHAR(255) DEFAULT '',
                    catatan TEXT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (nama_pegawai),
                    INDEX (bidang_sertifikasi),
                    INDEX (tanggal_kadaluarsa)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            // 2. Tabel Realisasi Pelatihan & Alokasi 5%
            db()->exec("
                CREATE TABLE IF NOT EXISTS sdm_pelatihan (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tahun_anggaran INT NOT NULL,
                    judul_pelatihan VARCHAR(255) NOT NULL,
                    jenis_pelatihan VARCHAR(100) NOT NULL DEFAULT 'APU PPT Wajib',
                    penyelenggara VARCHAR(150) NOT NULL,
                    tanggal_mulai DATE NOT NULL,
                    tanggal_selesai DATE NOT NULL,
                    jumlah_peserta INT NOT NULL DEFAULT 1,
                    daftar_peserta TEXT DEFAULT NULL,
                    total_biaya DECIMAL(15,2) NOT NULL DEFAULT 0,
                    bukti_file VARCHAR(255) DEFAULT '',
                    catatan TEXT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (tahun_anggaran),
                    INDEX (jenis_pelatihan)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            // 3. Tabel Mandatory Block Leave (Anti-Fraud)
            db()->exec("
                CREATE TABLE IF NOT EXISTS sdm_block_leave (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tahun INT NOT NULL,
                    nama_pegawai VARCHAR(150) NOT NULL,
                    nip VARCHAR(50) DEFAULT '',
                    jabatan VARCHAR(100) NOT NULL,
                    cabang_nama VARCHAR(100) NOT NULL,
                    fungsi_rawan VARCHAR(10) NOT NULL DEFAULT 'Ya',
                    tanggal_mulai DATE NOT NULL,
                    tanggal_selesai DATE NOT NULL,
                    jumlah_hari INT NOT NULL DEFAULT 5,
                    petugas_pengganti VARCHAR(150) NOT NULL,
                    status VARCHAR(50) NOT NULL DEFAULT 'Terjadwal',
                    catatan TEXT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (tahun),
                    INDEX (nama_pegawai),
                    INDEX (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            // 4. Tabel Pengaturan Pagu Anggaran SDM
            db()->exec("
                CREATE TABLE IF NOT EXISTS sdm_anggaran_tahunan (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tahun INT UNIQUE NOT NULL,
                    pagu_biaya_pegawai DECIMAL(15,2) NOT NULL DEFAULT 0,
                    target_persen DECIMAL(5,2) NOT NULL DEFAULT 5.00,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            // Seed default anggaran tahun 2026 jika kosong
            $cnt = (int)db()->query("SELECT COUNT(*) FROM sdm_anggaran_tahunan WHERE tahun = 2026")->fetchColumn();
            if ($cnt === 0) {
                db()->exec("INSERT INTO sdm_anggaran_tahunan (tahun, pagu_biaya_pegawai, target_persen) VALUES (2026, 1200000000, 5.00)");
            }
        } catch (Throwable $e) {
            // Catch error
        }
    }
}

function sdm_upload_file(array $fileInfo, string $subDir = 'sdm'): string {
    if (empty($fileInfo['name']) || empty($fileInfo['tmp_name'])) {
        return '';
    }
    if ($fileInfo['error'] !== UPLOAD_ERR_OK) {
        return '';
    }

    $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return '';
    }

    $uploadDir = __DIR__ . '/../uploads/' . $subDir . '/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $newFileName = 'sdm_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $uploadDir . $newFileName;

    if (move_uploaded_file($fileInfo['tmp_name'], $targetPath)) {
        return $subDir . '/' . $newFileName;
    }
    return '';
}

function sdm_file_url(string $relPath): string {
    if (empty($relPath)) return '';
    return module_url('uploads/' . ltrim($relPath, '/'));
}
