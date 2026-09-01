-- ============================================================
-- MODUL QR MAINTENANCE - PT BPR Mitratama Arthabuana
-- Scan QR = maintenance bulan berjalan tercatat otomatis
-- Aman untuk digabung ke database Rekap IT yang sudah memiliki
-- tabel: users, cabang, divisi, karyawan, kategori_aset, assets
-- ============================================================

CREATE TABLE IF NOT EXISTS asset_qr_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_id INT NOT NULL,
    token CHAR(32) NOT NULL,
    placement_label VARCHAR(120) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_asset_qr_asset (asset_id),
    UNIQUE KEY uq_asset_qr_token (token),
    KEY idx_asset_qr_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maintenance_scan (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_id INT NOT NULL,
    technician_user_id INT NOT NULL,
    maintenance_date DATE NOT NULL,
    maintenance_time TIME NOT NULL,
    maintenance_month TINYINT UNSIGNED NOT NULL,
    maintenance_year SMALLINT UNSIGNED NOT NULL,
    status ENUM('Selesai','Temuan') NOT NULL DEFAULT 'Selesai',
    source VARCHAR(20) NOT NULL DEFAULT 'QR',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_maintenance_asset_period (asset_id, maintenance_month, maintenance_year),
    KEY idx_maintenance_period (maintenance_year, maintenance_month),
    KEY idx_maintenance_technician (technician_user_id),
    KEY idx_maintenance_date (maintenance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maintenance_findings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    maintenance_scan_id BIGINT UNSIGNED NOT NULL,
    asset_id INT NOT NULL,
    finding TEXT NOT NULL,
    action_taken TEXT NULL,
    severity ENUM('Ringan','Sedang','Berat') NOT NULL DEFAULT 'Ringan',
    repair_status ENUM('Perlu Tindak Lanjut','Proses','Selesai') NOT NULL DEFAULT 'Perlu Tindak Lanjut',
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_finding_scan (maintenance_scan_id),
    KEY idx_finding_asset (asset_id),
    KEY idx_finding_status (repair_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
