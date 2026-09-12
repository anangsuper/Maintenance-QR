<?php
/**
 * AUDIT TRAIL & LOG AKTIVITAS SISTEM TERPUSAT
 * Memenuhi standar kepatuhan operasional perbankan (POJK / ISO 27001)
 * Mendukung Dual-Storage Engine: Database MySQL dan Google Sheets API v4
 */

declare(strict_types=1);

/**
 * Inisialisasi tabel system_audit_logs di MySQL jika belum ada
 */
function ensure_audit_log_storage(): void {
    static $initialized = false;
    if ($initialized) return;
    $initialized = true;

    if (is_google_cloud_mode()) {
        try {
            $client = google_sheets_v4_client();
            if ($client) {
                $client->createSheetIfNotExists('Audit_Trail');
                $existing = $client->getValues('Audit_Trail!A1:K1');
                if (empty($existing)) {
                    $client->appendValues('Audit_Trail!A:K', [[
                        'id',
                        'created_at',
                        'user_id',
                        'user_name',
                        'user_role',
                        'ip_address',
                        'action',
                        'module',
                        'target_id',
                        'target_label',
                        'details'
                    ]]);
                }
            }
        } catch (Throwable $e) {
            error_log('Audit trail google sheets init warning: ' . $e->getMessage());
        }
        return;
    }

    try {
        db()->exec("
            CREATE TABLE IF NOT EXISTS system_audit_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                user_id INT NULL,
                user_name VARCHAR(100) NOT NULL,
                user_role VARCHAR(50) NOT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                action VARCHAR(50) NOT NULL,
                module VARCHAR(50) NOT NULL,
                target_id INT NULL,
                target_label VARCHAR(255) NULL,
                details TEXT NULL,
                PRIMARY KEY (id),
                KEY idx_audit_created (created_at),
                KEY idx_audit_module (module),
                KEY idx_audit_action (action),
                KEY idx_audit_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {
        error_log('Audit trail mysql init warning: ' . $e->getMessage());
    }
}

/**
 * Dapatkan IP address klien secara aman
 */
function get_client_ip(): string {
    $candidates = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    ];
    foreach ($candidates as $key) {
        if (!empty($_SERVER[$key])) {
            $ipList = explode(',', (string)$_SERVER[$key]);
            $ip = trim($ipList[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
}

/**
 * Catat aktivitas ke dalam Audit Trail
 * 
 * @param string $action      Tipe aksi: CREATE, UPDATE, DELETE, LOGIN, LOGOUT, SCAN, IMPORT
 * @param string $module      Modul target: ASET, PENGGUNA, CABANG, DIVISI, KEAMANAN, MAINTENANCE
 * @param int|null $targetId  ID entitas yang terdampak (misal ID Aset, ID User)
 * @param string|null $targetLabel Label deskriptif (misal: "INV-IT-001 (Dell Optiplex)")
 * @param string|null $details Rincian detail perubahan / alasan
 * @param array|null $metadata Data kontekstual tambahan (opsional)
 */
function record_audit_log(
    string $action,
    string $module,
    ?int $targetId = null,
    ?string $targetLabel = null,
    ?string $details = null,
    ?array $metadata = null
): void {
    try {
        ensure_audit_log_storage();

        $userId = current_user_id() ?: null;
        $userName = current_user_name() ?: 'System';
        $userRole = current_user_role() ?: 'system';
        $ip = get_client_ip();
        $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'), 0, 255);
        $createdAt = date('Y-m-d H:i:s');

        // Jika ada metadata user khusus yang dilewatkan (misal saat login)
        if (!empty($metadata['user_name'])) $userName = (string)$metadata['user_name'];
        if (!empty($metadata['user_role'])) $userRole = (string)$metadata['user_role'];
        if (!empty($metadata['user_id'])) $userId = (int)$metadata['user_id'];

        if (is_google_cloud_mode()) {
            $client = google_sheets_v4_client();
            if ($client) {
                $sheetRows = $client->getSheetData('Audit_Trail');
                $nextId = count($sheetRows) + 1;
                $row = [
                    $nextId,
                    $createdAt,
                    $userId ?? '-',
                    $userName,
                    $userRole,
                    $ip,
                    $action,
                    $module,
                    $targetId ?? '-',
                    $targetLabel ?? '-',
                    $details ?? '-'
                ];
                $client->appendValues('Audit_Trail!A:K', [$row]);
            }
            return;
        }

        // MySQL Mode
        $stmt = db()->prepare("
            INSERT INTO system_audit_logs 
                (created_at, user_id, user_name, user_role, ip_address, user_agent, action, module, target_id, target_label, details)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $createdAt,
            $userId,
            $userName,
            $userRole,
            $ip,
            $userAgent,
            strtoupper($action),
            strtoupper($module),
            $targetId,
            $targetLabel,
            $details
        ]);
    } catch (Throwable $e) {
        // Logging tidak boleh memecahkan alur utama aplikasi jika terjadi kendala DB/koneksi
        error_log('Failed to record audit log: ' . $e->getMessage());
    }
}

/**
 * Bandingkan aset lama dengan data input baru untuk menghasilkan deskripsi diff yang rapi
 */
function diff_asset_changes(array $old, array $new): array {
    $changes = [];

    // 1. IP Address
    $oldIp = trim((string)($old['ip_address'] ?? $old['ip'] ?? ''));
    $newIp = trim((string)($new['ip_address'] ?? $new['ip'] ?? ''));
    if ($oldIp !== $newIp) {
        $changes[] = 'IP Address: ' . ($oldIp ?: '(kosong)') . ' ➔ ' . ($newIp ?: '(dikosongkan)');
    }

    // 2. Nama Pengguna / Karyawan
    $oldKar = trim((string)($old['karyawan_nama'] ?? $old['nama_karyawan'] ?? ''));
    $newKar = trim((string)($new['nama_karyawan'] ?? ''));
    if ($oldKar !== '' && $newKar !== '' && strcasecmp($oldKar, $newKar) !== 0) {
        $changes[] = 'Pengguna: "' . $oldKar . '" ➔ "' . $newKar . '"';
    } elseif ($oldKar === '' && $newKar !== '') {
        $changes[] = 'Pengguna ditetapkan ke: "' . $newKar . '"';
    }

    // 3. Divisi
    $oldDivId = (int)($old['id_divisi'] ?? 0);
    $newDivId = (int)($new['id_divisi'] ?? 0);
    if ($newDivId > 0 && $oldDivId !== $newDivId) {
        $oldDivName = trim((string)($old['divisi_nama'] ?? 'Divisi #' . $oldDivId));
        $newDivName = 'Divisi #' . $newDivId;
        $divList = get_divisi_list();
        foreach ($divList as $d) {
            if ((int)($d['id'] ?? 0) === $newDivId) {
                $newDivName = $d['nama_divisi'] ?? $d['nama'] ?? $newDivName;
                break;
            }
        }
        $changes[] = 'Divisi: "' . $oldDivName . '" ➔ "' . $newDivName . '"';
    }

    // 4. Cabang
    $oldCabId = (int)($old['id_cabang'] ?? 0);
    $newCabId = (int)($new['id_cabang'] ?? 0);
    if ($newCabId > 0 && $oldCabId !== $newCabId) {
        $oldCabName = trim((string)($old['cabang_nama'] ?? 'Cabang #' . $oldCabId));
        $newCabName = 'Cabang #' . $newCabId;
        $cabList = get_cabang_list();
        foreach ($cabList as $c) {
            if ((int)($c['id'] ?? 0) === $newCabId) {
                $newCabName = $c['nama_cabang'] ?? $c['nama'] ?? $newCabName;
                break;
            }
        }
        $changes[] = 'Cabang: "' . $oldCabName . '" ➔ "' . $newCabName . '"';
    }

    // 5. Status
    $oldStatus = trim((string)($old['status'] ?? 'Aktif'));
    $newStatus = trim((string)($new['status'] ?? 'Aktif'));
    if ($newStatus !== '' && strcasecmp($oldStatus, $newStatus) !== 0) {
        $changes[] = 'Status: ' . $oldStatus . ' ➔ ' . $newStatus;
    }

    // 6. Kode Inventaris
    $oldKode = trim((string)($old['kode_inventaris'] ?? ''));
    $newKode = trim((string)($new['kode_inventaris'] ?? ''));
    if ($oldKode !== '' && $newKode !== '' && $oldKode !== $newKode) {
        $changes[] = 'Kode Inventaris: ' . $oldKode . ' ➔ ' . $newKode;
    }

    // 7. Merk & Model
    $oldUnit = trim(($old['merk'] ?? '') . ' ' . ($old['model'] ?? ''));
    $newUnit = trim(($new['merk'] ?? '') . ' ' . ($new['model'] ?? ''));
    if ($oldUnit !== '' && $newUnit !== '' && $oldUnit !== $newUnit) {
        $changes[] = 'Perangkat: ' . $oldUnit . ' ➔ ' . $newUnit;
    }

    // 8. Serial Number
    $oldSn = trim((string)($old['serial_number'] ?? ''));
    $newSn = trim((string)($new['serial_number'] ?? ''));
    if ($oldSn !== $newSn && ($oldSn !== '' || $newSn !== '')) {
        $changes[] = 'Serial Number: ' . ($oldSn ?: '(kosong)') . ' ➔ ' . ($newSn ?: '(dikosongkan)');
    }

    // 9. Printer
    $oldPrt = trim((string)($old['printer'] ?? ''));
    $newPrt = trim((string)($new['printer'] ?? ''));
    if ($oldPrt !== $newPrt && ($oldPrt !== '' || $newPrt !== '')) {
        $changes[] = 'Printer Terhubung: ' . ($oldPrt ?: '(tidak ada)') . ' ➔ ' . ($newPrt ?: '(tidak ada)');
    }

    // 10. Keterangan
    $oldKet = trim((string)($old['keterangan'] ?? ''));
    $newKet = trim((string)($new['keterangan'] ?? ''));
    if ($oldKet !== $newKet && ($oldKet !== '' || $newKet !== '')) {
        $changes[] = 'Catatan/Keterangan diperbarui';
    }

    return $changes;
}

/**
 * Mengambil daftar data Audit Log dengan filter dan pagination
 */
function get_audit_logs(array $filters = [], int $limit = 50, int $offset = 0): array {
    ensure_audit_log_storage();

    $module = trim((string)($filters['module'] ?? ''));
    $action = trim((string)($filters['action'] ?? ''));
    $search = trim((string)($filters['search'] ?? ''));
    $month = (int)($filters['bulan'] ?? 0);
    $year = (int)($filters['tahun'] ?? 0);

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        $rows = $client ? $client->getSheetData('Audit_Trail') : [];
        if (empty($rows)) return ['rows' => [], 'total' => 0];

        // Format and filter in-memory
        $filtered = [];
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            if ($id <= 0) continue;

            $logDate = (string)($r['created_at'] ?? '');
            $ts = strtotime($logDate);

            if ($month > 0 && $ts && (int)date('n', $ts) !== $month) continue;
            if ($year > 0 && $ts && (int)date('Y', $ts) !== $year) continue;

            $m = strtoupper(trim((string)($r['module'] ?? '')));
            if ($module !== '' && $module !== 'ALL' && $m !== strtoupper($module)) continue;

            $a = strtoupper(trim((string)($r['action'] ?? '')));
            if ($action !== '' && $action !== 'ALL' && $a !== strtoupper($action)) continue;

            if ($search !== '') {
                $haystack = strtolower(implode(' ', [
                    $r['user_name'] ?? '',
                    $r['target_label'] ?? '',
                    $r['details'] ?? '',
                    $r['ip_address'] ?? ''
                ]));
                if (!str_contains($haystack, strtolower($search))) continue;
            }

            $filtered[] = [
                'id' => $id,
                'created_at' => $logDate,
                'user_id' => $r['user_id'] ?? 0,
                'user_name' => $r['user_name'] ?? 'System',
                'user_role' => $r['user_role'] ?? 'system',
                'ip_address' => $r['ip_address'] ?? '-',
                'action' => $a,
                'module' => $m,
                'target_id' => $r['target_id'] ?? 0,
                'target_label' => $r['target_label'] ?? '-',
                'details' => $r['details'] ?? '-'
            ];
        }

        // Urutkan dari terbaru ke terlama
        usort($filtered, function($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        $total = count($filtered);
        $paginated = array_slice($filtered, $offset, $limit);
        return ['rows' => $paginated, 'total' => $total];
    }

    // MySQL Mode
    try {
        $where = ["1=1"];
        $params = [];

        if ($module !== '' && $module !== 'ALL') {
            $where[] = "module = ?";
            $params[] = strtoupper($module);
        }

        if ($action !== '' && $action !== 'ALL') {
            $where[] = "action = ?";
            $params[] = strtoupper($action);
        }

        if ($month > 0) {
            $where[] = "MONTH(created_at) = ?";
            $params[] = $month;
        }

        if ($year > 0) {
            $where[] = "YEAR(created_at) = ?";
            $params[] = $year;
        }

        if ($search !== '') {
            $where[] = "(user_name LIKE ? OR target_label LIKE ? OR details LIKE ? OR ip_address LIKE ?)";
            $q = "%{$search}%";
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $whereSql = implode(' AND ', $where);

        // Count total
        $countSt = db()->prepare("SELECT COUNT(*) FROM system_audit_logs WHERE {$whereSql}");
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        // Fetch rows
        $fetchSt = db()->prepare("
            SELECT * FROM system_audit_logs 
            WHERE {$whereSql}
            ORDER BY id DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $fetchSt->execute($params);
        $rows = $fetchSt->fetchAll();

        return ['rows' => $rows, 'total' => $total];
    } catch (Throwable $e) {
        error_log('Failed to fetch audit logs: ' . $e->getMessage());
        return ['rows' => [], 'total' => 0];
    }
}

/**
 * Statistik KPI ringkas untuk Audit Trail Dashboard
 */
function get_audit_stats(): array {
    ensure_audit_log_storage();

    $stats = [
        'total' => 0,
        'today' => 0,
        'asset_updates' => 0,
        'asset_deletes' => 0,
        'auth_logins' => 0,
    ];

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        $rows = $client ? $client->getSheetData('Audit_Trail') : [];
        $todayStr = date('Y-m-d');
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            if ($id <= 0) continue;
            $stats['total']++;
            $date = substr((string)($r['created_at'] ?? ''), 0, 10);
            if ($date === $todayStr) $stats['today']++;
            $m = strtoupper(trim((string)($r['module'] ?? '')));
            $a = strtoupper(trim((string)($r['action'] ?? '')));
            if ($m === 'ASET' && $a === 'UPDATE') $stats['asset_updates']++;
            if ($m === 'ASET' && $a === 'DELETE') $stats['asset_deletes']++;
            if ($m === 'KEAMANAN' && str_starts_with($a, 'LOGIN')) $stats['auth_logins']++;
        }
        return $stats;
    }

    try {
        $todayStr = date('Y-m-d');
        $r = db()->query("
            SELECT
                COUNT(*) AS total,
                SUM(DATE(created_at) = '{$todayStr}') AS today,
                SUM(module = 'ASET' AND action = 'UPDATE') AS asset_updates,
                SUM(module = 'ASET' AND action = 'DELETE') AS asset_deletes,
                SUM(module = 'KEAMANAN' AND action LIKE 'LOGIN%') AS auth_logins
            FROM system_audit_logs
        ")->fetch();

        if ($r) {
            $stats['total'] = (int)($r['total'] ?? 0);
            $stats['today'] = (int)($r['today'] ?? 0);
            $stats['asset_updates'] = (int)($r['asset_updates'] ?? 0);
            $stats['asset_deletes'] = (int)($r['asset_deletes'] ?? 0);
            $stats['auth_logins'] = (int)($r['auth_logins'] ?? 0);
        }
    } catch (Throwable $e) {
        error_log('Failed to fetch audit stats: ' . $e->getMessage());
    }

    return $stats;
}
