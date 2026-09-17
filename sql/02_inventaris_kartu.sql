-- ============================================================
-- TABEL KARTU INVENTARIS (CR80) - PT BPR Mitratama Arthabuana
-- Terpisah dari tabel assets & asset_qr_tokens
-- ============================================================

CREATE TABLE IF NOT EXISTS inventaris_kartu (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nomor_rekening VARCHAR(100) NOT NULL,
    nama_barang VARCHAR(255) NOT NULL,
    tanggal_perolehan DATE NOT NULL,
    barcode_data TEXT NOT NULL,
    lokasi VARCHAR(150) NULL DEFAULT 'KPO / Operasional',
    pengguna VARCHAR(150) NULL DEFAULT 'Umum / Pool',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_nomor_rekening (nomor_rekening)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data Awal Kartu Inventaris
INSERT INTO inventaris_kartu (id, nomor_rekening, nama_barang, tanggal_perolehan, barcode_data, created_at)
VALUES
(9, '03.05.1973', 'KIPAS ANGIN EMBUN', '2026-02-27', 'https://canva.link/ko9ckx76pbojj2y', '2026-08-10 05:00:02'),
(10, '01.05.0359', 'ROLLER BLIND U/ RUANG PERPUS', '2023-01-31', 'https://canva.link/t9fcx334gfbmhrw', '2026-08-11 05:11:48'),
(11, '01.05.0302', 'Roller Blind KPO', '2022-05-23', 'https://canva.link/q5kkycw9vtomob7', '2026-08-11 05:13:00'),
(12, '01.05.0492', 'LAPTOP MSI THIN STAFF IT', '2026-08-26', 'https://canva.link/axqdgjgztd1uu3r', '2026-09-08 03:12:05'),
(13, '01.05.0493', 'PRINTER EPSON L3211 KAS', '2026-09-26', 'https://canva.link/tyu3nb63s2yjau9', '2026-09-08 03:15:02')
ON DUPLICATE KEY UPDATE
    nomor_rekening = VALUES(nomor_rekening),
    nama_barang = VALUES(nama_barang),
    tanggal_perolehan = VALUES(tanggal_perolehan),
    barcode_data = VALUES(barcode_data);
