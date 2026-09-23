<?php
/**
 * Migration Tool: Google Sheets API v4 -> Database Server (MySQL)
 * Akses: https://domain-anda/api/migrate_sheets_to_db.php
 * Khusus role Admin
 */
require __DIR__ . '/bootstrap.php';
require_admin();

$client = google_sheets_v4_client();
$hasSheets = is_google_cloud_mode() && $client;

$dbConnected = false;
$dbError = null;
try {
    $pdo = db();
    $dbConnected = ($pdo instanceof PDO);
} catch (Throwable $e) {
    $dbConnected = false;
    $dbError = $e->getMessage();
}

$migrationResults = [];
$isDone = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    verify_csrf();

    if (!$hasSheets) {
        $migrationResults['error'] = 'Koneksi Google Sheets tidak aktif. Pastikan GOOGLE_SPREADSHEET_ID dan Service Account sudah diset.';
    } elseif (!$dbConnected) {
        $migrationResults['error'] = 'Koneksi Database MySQL gagal: ' . $dbError;
    } else {
        // Jalankan migrasi tabel demi tabel
        try {
            // 1. Cabang
            $pdo->exec("CREATE TABLE IF NOT EXISTS cabang (
                id INT PRIMARY KEY,
                nama_cabang VARCHAR(150) NOT NULL,
                alamat TEXT NULL,
                telepon VARCHAR(50) NULL,
                penanggung_jawab VARCHAR(150) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $cabangRows = $client->getSheetData('Cabang', true);
            $cCount = 0;
            $stmtC = $pdo->prepare("INSERT INTO cabang (id, nama_cabang, alamat, telepon, penanggung_jawab) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                nama_cabang = VALUES(nama_cabang), 
                alamat = VALUES(alamat), 
                telepon = VALUES(telepon), 
                penanggung_jawab = VALUES(penanggung_jawab)");
            foreach ($cabangRows as $r) {
                $id = (int)($r['id'] ?? 0);
                if ($id <= 0) continue;
                $stmtC->execute([
                    $id,
                    (string)($r['nama_cabang'] ?? $r['nama'] ?? 'Cabang #' . $id),
                    (string)($r['alamat'] ?? ''),
                    (string)($r['telepon'] ?? ''),
                    (string)($r['penanggung_jawab'] ?? '')
                ]);
                $cCount++;
            }
            $migrationResults['cabang'] = $cCount;

            // 2. Divisi
            $pdo->exec("CREATE TABLE IF NOT EXISTS divisi (
                id INT PRIMARY KEY,
                nama_divisi VARCHAR(150) NOT NULL,
                keterangan TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $divRows = $client->getSheetData('Divisi', true);
            $dCount = 0;
            $stmtD = $pdo->prepare("INSERT INTO divisi (id, nama_divisi, keterangan) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                nama_divisi = VALUES(nama_divisi), 
                keterangan = VALUES(keterangan)");
            foreach ($divRows as $r) {
                $id = (int)($r['id'] ?? 0);
                if ($id <= 0) continue;
                $stmtD->execute([
                    $id,
                    (string)($r['nama_divisi'] ?? $r['nama'] ?? 'Divisi #' . $id),
                    (string)($r['keterangan'] ?? '')
                ]);
                $dCount++;
            }
            $migrationResults['divisi'] = $dCount;

            // 3. Kategori Aset
            $pdo->exec("CREATE TABLE IF NOT EXISTS kategori_aset (
                id INT PRIMARY KEY,
                nama_kategori VARCHAR(100) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $katRows = $client->getSheetData('Kategori_Aset', true);
            $katCount = 0;
            $stmtKat = $pdo->prepare("INSERT INTO kategori_aset (id, nama_kategori) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE nama_kategori = VALUES(nama_kategori)");
            foreach ($katRows as $r) {
                $id = (int)($r['id'] ?? 0);
                if ($id <= 0) continue;
                $stmtKat->execute([$id, (string)($r['nama_kategori'] ?? 'Kategori #' . $id)]);
                $katCount++;
            }
            $migrationResults['kategori_aset'] = $katCount;

            // 4. Karyawan
            $pdo->exec("CREATE TABLE IF NOT EXISTS karyawan (
                id INT PRIMARY KEY,
                nama_karyawan VARCHAR(150) NOT NULL,
                id_cabang INT NULL,
                id_divisi INT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $karRows = $client->getSheetData('Karyawan', true);
            $karCount = 0;
            $stmtKar = $pdo->prepare("INSERT INTO karyawan (id, nama_karyawan, id_cabang, id_divisi) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                nama_karyawan = VALUES(nama_karyawan), 
                id_cabang = VALUES(id_cabang), 
                id_divisi = VALUES(id_divisi)");
            foreach ($karRows as $r) {
                $id = (int)($r['id'] ?? 0);
                if ($id <= 0) continue;
                $stmtKar->execute([
                    $id,
                    (string)($r['nama_karyawan'] ?? $r['nama'] ?? 'Karyawan #' . $id),
                    !empty($r['id_cabang']) ? (int)$r['id_cabang'] : null,
                    !empty($r['id_divisi']) ? (int)$r['id_divisi'] : null,
                ]);
                $karCount++;
            }
            $migrationResults['karyawan'] = $karCount;

            // 5. Users
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama VARCHAR(150) NOT NULL,
                nama_panggilan VARCHAR(50) NULL,
                username VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(50) NOT NULL DEFAULT 'teknisi',
                telepon VARCHAR(50) NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'Aktif',
                face_descriptor TEXT NULL,
                face_photo MEDIUMTEXT NULL,
                face_status VARCHAR(50) DEFAULT 'none',
                passkey_credential TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $userRows = $client->getSheetData('Users', true);
            $uCount = 0;
            $stmtU = $pdo->prepare("INSERT INTO users (id, nama, nama_panggilan, username, password, role, telepon, status, face_descriptor, face_photo, face_status, passkey_credential)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                nama = VALUES(nama),
                nama_panggilan = VALUES(nama_panggilan),
                username = VALUES(username),
                role = VALUES(role),
                telepon = VALUES(telepon),
                status = VALUES(status),
                face_descriptor = VALUES(face_descriptor),
                face_photo = VALUES(face_photo),
                face_status = VALUES(face_status),
                passkey_credential = VALUES(passkey_credential)");
            foreach ($userRows as $r) {
                $id = (int)($r['id'] ?? 0);
                if ($id <= 0) continue;
                $uname = trim((string)($r['username'] ?? ''));
                if ($uname === '' || strcasecmp($uname, 'username') === 0) continue;

                $pwd = (string)($r['password'] ?? '');
                if ($pwd === '') {
                    $pwd = password_hash('teknisi123', PASSWORD_DEFAULT);
                }

                $stmtU->execute([
                    $id,
                    (string)($r['nama'] ?? $uname),
                    (string)($r['nama_panggilan'] ?? ''),
                    $uname,
                    $pwd,
                    (string)($r['role'] ?? 'teknisi'),
                    (string)($r['telepon'] ?? '-'),
                    (string)($r['status'] ?? 'Aktif'),
                    (string)($r['face_descriptor'] ?? ''),
                    (string)($r['face_photo'] ?? ''),
                    (string)($r['face_status'] ?? 'none'),
                    (string)($r['passkey_credential'] ?? '')
                ]);
                $uCount++;
            }
            $migrationResults['users'] = $uCount;

            // 6. Assets & Asset_QR_Tokens
            $pdo->exec("CREATE TABLE IF NOT EXISTS assets (
                id INT PRIMARY KEY,
                kode_inventaris VARCHAR(100) NOT NULL,
                merk VARCHAR(100) NULL,
                model VARCHAR(100) NULL,
                serial_number VARCHAR(100) NULL,
                id_kategori INT NULL,
                id_cabang INT NULL,
                id_divisi INT NULL,
                id_karyawan INT NULL,
                status VARCHAR(50) DEFAULT 'Aktif',
                keterangan TEXT NULL,
                ip_address VARCHAR(50) NULL,
                printer VARCHAR(100) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS asset_qr_tokens (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                asset_id INT NOT NULL UNIQUE,
                token CHAR(32) NOT NULL UNIQUE,
                placement_label VARCHAR(120) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $assetRows = $client->getSheetData('Assets', true);
            $aCount = 0;
            $stmtA = $pdo->prepare("INSERT INTO assets (id, kode_inventaris, merk, model, serial_number, id_kategori, id_cabang, id_divisi, id_karyawan, status, keterangan, ip_address, printer)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                kode_inventaris = VALUES(kode_inventaris),
                merk = VALUES(merk),
                model = VALUES(model),
                serial_number = VALUES(serial_number),
                id_kategori = VALUES(id_kategori),
                id_cabang = VALUES(id_cabang),
                id_divisi = VALUES(id_divisi),
                id_karyawan = VALUES(id_karyawan),
                status = VALUES(status),
                keterangan = VALUES(keterangan),
                ip_address = VALUES(ip_address),
                printer = VALUES(printer)");
            foreach ($assetRows as $r) {
                $id = (int)($r['id'] ?? 0);
                if ($id <= 0) continue;
                $stmtA->execute([
                    $id,
                    normalize_kode_inventaris((string)($r['kode_inventaris'] ?? '')),
                    (string)($r['merk'] ?? ''),
                    (string)($r['model'] ?? ''),
                    (string)($r['serial_number'] ?? ''),
                    !empty($r['id_kategori']) ? (int)$r['id_kategori'] : null,
                    !empty($r['id_cabang']) ? (int)$r['id_cabang'] : null,
                    !empty($r['id_divisi']) ? (int)$r['id_divisi'] : null,
                    !empty($r['id_karyawan']) ? (int)$r['id_karyawan'] : null,
                    (string)($r['status'] ?? 'Aktif'),
                    (string)($r['keterangan'] ?? ''),
                    (string)($r['ip_address'] ?? $r['ip'] ?? ''),
                    (string)($r['printer'] ?? '')
                ]);
                $aCount++;
            }
            $migrationResults['assets'] = $aCount;

            // QR Tokens
            $qrRows = $client->getSheetData('Asset_QR_Tokens', true);
            $qrCount = 0;
            $stmtQR = $pdo->prepare("INSERT INTO asset_qr_tokens (asset_id, token, placement_label, is_active)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                token = VALUES(token),
                placement_label = VALUES(placement_label),
                is_active = VALUES(is_active)");
            foreach ($qrRows as $r) {
                $aid = (int)($r['asset_id'] ?? 0);
                $token = trim((string)($r['token'] ?? ''));
                if ($aid <= 0 || $token === '') continue;
                $stmtQR->execute([
                    $aid,
                    $token,
                    (string)($r['placement_label'] ?? 'Bodi Depan'),
                    !empty($r['is_active']) ? 1 : 0
                ]);
                $qrCount++;
            }
            $migrationResults['qr_tokens'] = $qrCount;

            // 7. Maintenance Scan
            $pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_scan (
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
                UNIQUE KEY uq_maintenance_asset_period (asset_id, maintenance_month, maintenance_year)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $scanRows = $client->getSheetData('Maintenance_Scan', true);
            $mCount = 0;
            $stmtM = $pdo->prepare("INSERT INTO maintenance_scan (
                    asset_id, technician_user_id, technician_name, maintenance_date, maintenance_time, 
                    maintenance_month, maintenance_year, status, source, findings, recommendation
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                technician_name = VALUES(technician_name),
                status = VALUES(status),
                findings = VALUES(findings),
                recommendation = VALUES(recommendation)");
            foreach ($scanRows as $r) {
                $aid = (int)($r['asset_id'] ?? 0);
                $bln = (int)($r['maintenance_month'] ?? 0);
                $thn = (int)($r['maintenance_year'] ?? 0);
                if ($aid <= 0 || $bln <= 0 || $thn <= 0) continue;
                $stmtM->execute([
                    $aid,
                    !empty($r['technician_user_id']) ? (int)$r['technician_user_id'] : null,
                    (string)($r['technician_name'] ?? 'Teknisi'),
                    (string)($r['maintenance_date'] ?? date('Y-m-d')),
                    (string)($r['maintenance_time'] ?? date('H:i:s')),
                    $bln,
                    $thn,
                    (string)($r['status'] ?? 'Selesai'),
                    (string)($r['source'] ?? 'Maintenance'),
                    (string)($r['findings'] ?? ''),
                    (string)($r['recommendation'] ?? '')
                ]);
                $mCount++;
            }
            $migrationResults['maintenance_scan'] = $mCount;

            // 8. Inventaris Kartu
            $pdo->exec("CREATE TABLE IF NOT EXISTS inventaris_kartu (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                nomor_rekening VARCHAR(100) NOT NULL,
                nama_barang VARCHAR(255) NOT NULL,
                tanggal_perolehan DATE NOT NULL,
                barcode_data TEXT NOT NULL,
                lokasi VARCHAR(150) NULL DEFAULT 'Kantor Pusat (KPO)',
                pengguna VARCHAR(150) NULL DEFAULT 'Umum / Pool',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $kartuRows = $client->getSheetData('Inventaris_Kartu', true);
            $kCount = 0;
            $stmtK = $pdo->prepare("INSERT INTO inventaris_kartu (id, nomor_rekening, nama_barang, tanggal_perolehan, barcode_data, lokasi, pengguna)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                nomor_rekening = VALUES(nomor_rekening),
                nama_barang = VALUES(nama_barang),
                tanggal_perolehan = VALUES(tanggal_perolehan),
                barcode_data = VALUES(barcode_data),
                lokasi = VALUES(lokasi),
                pengguna = VALUES(pengguna)");
            foreach ($kartuRows as $r) {
                $id = (int)($r['id'] ?? 0);
                $nomor = trim((string)($r['nomor_rekening'] ?? ''));
                if ($id <= 0 || $nomor === '') continue;
                $stmtK->execute([
                    $id,
                    $nomor,
                    (string)($r['nama_barang'] ?? ''),
                    (string)($r['tanggal_perolehan'] ?? date('Y-m-d')),
                    (string)($r['barcode_data'] ?? ''),
                    (string)($r['lokasi'] ?? 'Kantor Pusat (KPO)'),
                    (string)($r['pengguna'] ?? 'Umum / Pool')
                ]);
                $kCount++;
            }
            $migrationResults['inventaris_kartu'] = $kCount;

            $isDone = true;
            record_audit_log('MIGRATE_SUCCESS', 'DATABASE', current_user_id(), current_user_name(), 'Migrasi data dari Google Sheets ke Database Server berhasil.');
        } catch (Throwable $ex) {
            $migrationResults['error'] = 'Terjadi kesalahan saat migrasi: ' . $ex->getMessage();
        }
    }
}

// Render UI
$content = '
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-cloud-arrow-down-fill me-2"></i>Migrasi Data: Google Sheets ➔ Database Server (MySQL)</h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted">
                    Fitur ini memindahkan seluruh data dari <strong>Google Spreadsheet</strong> (Cabang, Divisi, User, Komputer/Assets, QR Tokens, Riwayat Scan Maintenance, dan Kartu Inventaris) secara otomatis ke <strong>Database MySQL Server</strong> Anda.
                </p>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 border rounded ' . ($hasSheets ? 'bg-light text-success border-success' : 'bg-light text-danger border-danger') . '">
                            <div class="fw-bold"><i class="bi ' . ($hasSheets ? 'bi-check-circle-fill' : 'bi-x-circle-fill') . ' me-2"></i>Sumber Data: Google Sheets API</div>
                            <div class="small mt-1">' . ($hasSheets ? 'Terhubung dengan Spreadsheet ID: <code>' . e(substr(cfg('google_spreadsheet_id', envv('GOOGLE_SPREADSHEET_ID', '')) ?: '', 0, 15)) . '...</code>' : 'Tidak terhubung atau env Google Sheets kosong.') . '</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded ' . ($dbConnected ? 'bg-light text-success border-success' : 'bg-light text-danger border-danger') . '">
                            <div class="fw-bold"><i class="bi ' . ($dbConnected ? 'bi-check-circle-fill' : 'bi-x-circle-fill') . ' me-2"></i>Tujuan: Database Server MySQL</div>
                            <div class="small mt-1">' . ($dbConnected ? 'Terhubung ke Database <code>' . e(cfg('db_name', envv('DB_NAME', 'rekap_it'))) . '</code>' : 'Gagal terhubung: ' . e($dbError ?: 'Periksa konfigurasi MySQL')) . '</div>
                        </div>
                    </div>
                </div>';

if (!empty($migrationResults['error'])) {
    $content .= '<div class="alert alert-danger py-3"><i class="bi bi-exclamation-triangle-fill me-2"></i>' . e($migrationResults['error']) . '</div>';
}

if ($isDone) {
    $content .= '
    <div class="alert alert-success py-3">
        <h5 class="fw-bold mb-2"><i class="bi bi-check-circle-fill me-2"></i>Migrasi Berhasil Selesai!</h5>
        <p class="mb-3">Seluruh data telah berhasil disalin ke Database Server MySQL:</p>
        <ul class="mb-0">
            <li><strong>Kantor Cabang:</strong> ' . (int)($migrationResults['cabang'] ?? 0) . ' data</li>
            <li><strong>Divisi / Unit:</strong> ' . (int)($migrationResults['divisi'] ?? 0) . ' data</li>
            <li><strong>Kategori Aset:</strong> ' . (int)($migrationResults['kategori_aset'] ?? 0) . ' data</li>
            <li><strong>Karyawan:</strong> ' . (int)($migrationResults['karyawan'] ?? 0) . ' data</li>
            <li><strong>Pengguna / User Teknisi:</strong> ' . (int)($migrationResults['users'] ?? 0) . ' data</li>
            <li><strong>Komputer & Aset IT:</strong> ' . (int)($migrationResults['assets'] ?? 0) . ' data</li>
            <li><strong>Token QR:</strong> ' . (int)($migrationResults['qr_tokens'] ?? 0) . ' data</li>
            <li><strong>Riwayat Scan Maintenance:</strong> ' . (int)($migrationResults['maintenance_scan'] ?? 0) . ' data</li>
            <li><strong>Kartu Inventaris:</strong> ' . (int)($migrationResults['inventaris_kartu'] ?? 0) . ' data</li>
        </ul>
    </div>
    
    <div class="card bg-light border-info mb-4">
        <div class="card-body">
            <h6 class="fw-bold text-primary"><i class="bi bi-toggle2-on me-2"></i>Langkah Terakhir: Beralih ke Mode Database Server</h6>
            <ol class="small mb-0 text-secondary">
                <li>Buka file <code>config.local.php</code> di server atau pengaturan Environment Variable di Vercel.</li>
                <li>Hapus atau kosongkan variabel <code>GOOGLE_SPREADSHEET_ID</code> dan <code>GOOGLE_CLIENT_EMAIL</code>.</li>
                <li>Simpan. Sistem akan otomatis beralih menggunakan Database Server MySQL secara penuh!</li>
            </ol>
        </div>
    </div>';
}

$content .= '
                <form method="POST" onsubmit="return confirm(\'Jalankan migrasi seluruh data dari Google Sheets ke Database MySQL sekarang?\');">
                    ' . csrf_field() . '
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="' . module_url('dashboard.php') . '" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard</a>
                        <button type="submit" name="run_migration" value="1" class="btn btn-primary px-4 py-2" ' . (!$hasSheets || !$dbConnected ? 'disabled' : '') . '>
                            <i class="bi bi-play-circle-fill me-2"></i>Mulai Migrasi Data Sekarang
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>';

render_page('Migrasi Google Sheets ke Server Database', $content);
