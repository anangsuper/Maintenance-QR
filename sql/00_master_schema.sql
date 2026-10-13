-- ============================================================
-- MASTER SCHEMA & INITIAL DATABASE
-- SERVER-MAINTENANCE (NATIVE UBUNTU / LINUX / APACHE / MYSQL)
-- ============================================================

CREATE DATABASE IF NOT EXISTS db_maintenance_qr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_maintenance_qr;

-- 1. TABEL CABANG
CREATE TABLE IF NOT EXISTS cabang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_cabang VARCHAR(150) NOT NULL,
    alamat TEXT NULL,
    telepon VARCHAR(50) NULL,
    penanggung_jawab VARCHAR(150) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABEL DIVISI
CREATE TABLE IF NOT EXISTS divisi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_divisi VARCHAR(150) NOT NULL,
    id_cabang INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABEL KATEGORI ASET
CREATE TABLE IF NOT EXISTS kategori_aset (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TABEL KARYAWAN
CREATE TABLE IF NOT EXISTS karyawan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_karyawan VARCHAR(150) NOT NULL,
    nik VARCHAR(50) NULL,
    id_cabang INT NULL,
    id_divisi INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. TABEL ASSETS (KOMPUTER, LAPTOP, PRINTER)
CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_inventaris VARCHAR(100) NOT NULL,
    merk VARCHAR(100) NULL,
    model VARCHAR(100) NULL,
    serial_number VARCHAR(100) NULL,
    id_kategori INT NULL,
    id_cabang INT NULL DEFAULT 1,
    id_divisi INT NULL,
    id_karyawan INT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Aktif',
    keterangan TEXT NULL,
    ip_address VARCHAR(50) NULL,
    printer VARCHAR(150) NULL,
    foto TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_assets_cabang (id_cabang),
    KEY idx_assets_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TABEL USERS (AKUN PENGGUNA & TEKNISI)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(150) NOT NULL,
    nama_panggilan VARCHAR(50) NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'admin',
    telepon VARCHAR(50) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Aktif',
    face_descriptor TEXT NULL,
    face_photo MEDIUMTEXT NULL,
    face_status VARCHAR(50) DEFAULT 'none',
    passkey_credential TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. TABEL TOKEN QR ASET
CREATE TABLE IF NOT EXISTS asset_qr_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    token CHAR(32) NOT NULL,
    placement_label VARCHAR(120) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_asset_qr_asset (asset_id),
    UNIQUE KEY uq_asset_qr_token (token),
    KEY idx_asset_qr_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. TABEL MAINTENANCE SCAN & CHECKLIST
CREATE TABLE IF NOT EXISTS maintenance_scan (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    technician_user_id INT NULL,
    technician_name VARCHAR(150) NULL,
    maintenance_date DATE NOT NULL,
    maintenance_time TIME NOT NULL,
    maintenance_month TINYINT UNSIGNED NOT NULL,
    maintenance_year SMALLINT UNSIGNED NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Selesai',
    source VARCHAR(50) NOT NULL DEFAULT 'Maintenance',
    findings TEXT NULL,
    recommendation TEXT NULL,
    biometric_verified TINYINT(1) DEFAULT 0,
    biometric_confidence FLOAT NULL,
    biometric_photo MEDIUMTEXT NULL,
    latitude VARCHAR(50) NULL,
    longitude VARCHAR(50) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_maintenance_asset_period (asset_id, maintenance_month, maintenance_year),
    KEY idx_maintenance_period (maintenance_year, maintenance_month),
    KEY idx_maintenance_technician (technician_user_id),
    KEY idx_maintenance_date (maintenance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maintenance_checklists (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    maintenance_id BIGINT UNSIGNED NOT NULL,
    asset_id INT NOT NULL,
    checklist_number TINYINT NOT NULL,
    checklist_name VARCHAR(150) NOT NULL,
    checked TINYINT(1) DEFAULT 0,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_chk_maintenance (maintenance_id),
    KEY idx_chk_asset (asset_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maintenance_findings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    maintenance_scan_id BIGINT UNSIGNED NULL,
    asset_id INT NOT NULL,
    created_by INT NULL,
    finding TEXT NOT NULL,
    action_taken TEXT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'Sedang',
    repair_status VARCHAR(50) NOT NULL DEFAULT 'Open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_finding_asset (asset_id),
    KEY idx_finding_status (repair_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(50) NOT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'SISTEM',
    user_id INT NULL,
    username VARCHAR(100) NULL,
    details TEXT NULL,
    ip_address VARCHAR(50) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_created (created_at),
    KEY idx_audit_user (user_id),
    KEY idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_lockouts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    target_key VARCHAR(120) NOT NULL,
    ip_address VARCHAR(50) NOT NULL,
    failed_attempts INT UNSIGNED NOT NULL DEFAULT 1,
    locked_until DATETIME NULL,
    last_attempt DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_lockout_target (target_key),
    KEY idx_lockout_ip (ip_address),
    KEY idx_lockout_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_complaints (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    employee_name VARCHAR(150) NOT NULL,
    employee_nik VARCHAR(50) NULL,
    complaint_text TEXT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Open',
    technician_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    KEY idx_complaint_asset (asset_id),
    KEY idx_complaint_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventaris_kartu (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nomor_rekening VARCHAR(100) NOT NULL,
    nama_barang VARCHAR(255) NOT NULL,
    tanggal_perolehan DATE NOT NULL,
    barcode_data TEXT NULL,
    lokasi VARCHAR(150) NULL DEFAULT 'Kantor Pusat (KPO)',
    pengguna VARCHAR(150) NULL DEFAULT 'Umum / Pool',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_nomor_rekening (nomor_rekening)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. DATA SEEDING AWAL (CABANG, DIVISI, KATEGORI, DAN AKUN ADMIN)
INSERT IGNORE INTO cabang (id, nama_cabang, alamat, telepon, penanggung_jawab) VALUES 
(1, 'Kantor Pusat (KPO)', 'Banjarmasin', '0511-123456', 'Pimpinan Cabang'),
(2, 'Cabang Batulicin', 'Batulicin', '0518-123456', 'Pimpinan Cabang'),
(3, 'Kantor Kas Martapura', 'Martapura', '0511-654321', 'Pimpinan Kas'),
(4, 'Cabang Tanjung', 'Tanjung', '0526-123456', 'Pimpinan Cabang'),
(5, 'Cabang Handil Bakti', 'Handil Bakti', '0511-789012', 'Pimpinan Cabang');

INSERT IGNORE INTO divisi (id, nama_divisi, id_cabang) VALUES 
(1, 'TI & Operasional', 1),
(2, 'SDM & Umum', 1),
(3, 'Bisnis & Kredit', 1),
(4, 'Kepatuhan & SKAI', 1);

INSERT IGNORE INTO kategori_aset (id, nama_kategori) VALUES 
(1, 'Komputer PC'),
(2, 'Laptop'),
(3, 'Printer & Scanner'),
(4, 'Server & Jaringan');

-- Akun default: admin / admin123, teknisi / teknisi123
INSERT INTO users (id, nama, nama_panggilan, username, password, role, status) 
VALUES 
(1, 'Administrator', 'Admin', 'admin', 'admin123', 'admin', 'Aktif'),
(2, 'Teknisi IT Lapangan', 'Teknisi', 'teknisi', 'teknisi123', 'teknisi', 'Aktif')
ON DUPLICATE KEY UPDATE password = VALUES(password), status = 'Aktif';

-- Sampel Unit Komputer Contoh
INSERT IGNORE INTO assets (id, kode_inventaris, merk, model, serial_number, id_kategori, id_cabang, id_divisi, status) 
VALUES (1, 'KPO/PC/001', 'Lenovo', 'ThinkCentre M720q', 'SN-LEN-001', 1, 1, 1, 'Aktif');

INSERT IGNORE INTO asset_qr_tokens (asset_id, token, placement_label) 
VALUES (1, 'a1b2c3d4e5f60718293a4b5c6d7e8f90', 'Unit PC Server IT');
