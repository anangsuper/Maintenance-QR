<?php
/**
 * Modul Pengecualian / Lewati Pemeliharaan Cabang (Branch Maintenance Exemption / Skip)
 * Memungkinkan kantor cabang yang tidak dikunjungi pada bulan tertentu (misal: siklus berkala,
 * triwulanan, atau off-schedule) untuk dilewati secara resmi tanpa merusak metrik SLA kepatuhan IT.
 */

declare(strict_types=1);

/**
 * Pastikan skema tabel branch_maintenance_exemptions siap digunakan
 */
function ensure_branch_exemptions_schema(): void {
    static $ensured = false;
    if ($ensured) return;
    $ensured = true;

    if (function_exists('is_google_cloud_mode') && is_google_cloud_mode()) {
        return;
    }

    try {
        $pdo = db();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS branch_maintenance_exemptions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                cabang_id INT UNSIGNED NOT NULL,
                month TINYINT UNSIGNED NOT NULL,
                year SMALLINT UNSIGNED NOT NULL,
                reason VARCHAR(255) NOT NULL DEFAULT 'Tidak ada jadwal kunjungan cabang bulan ini',
                created_by VARCHAR(180) NULL DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_cabang_month_year (cabang_id, month, year),
                INDEX idx_exempt_period (month, year)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {
        error_log("Gagal inisialisasi skema branch_maintenance_exemptions: " . $e->getMessage());
    }
}

/**
 * Ambil daftar cabang yang dilewati pada bulan & tahun tertentu
 * Mengembalikan array berindeks cabang_id
 * 
 * @return array<int, array{id: int, cabang_id: int, month: int, year: int, reason: string, created_by: string, created_at: string}>
 */
function get_branch_exemptions(int $month, int $year): array {
    ensure_branch_exemptions_schema();
    $results = [];

    // Jika mode Google Sheets / fallback berkas lokal
    if (function_exists('is_google_cloud_mode') && is_google_cloud_mode()) {
        $cacheFile = sys_get_temp_dir() . '/branch_exemptions_' . $year . '_' . $month . '.json';
        if (file_exists($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) return $decoded;
            }
        }
        return [];
    }

    try {
        $st = db()->prepare("
            SELECT id, cabang_id, month, year, reason, created_by, created_at 
            FROM branch_maintenance_exemptions 
            WHERE month = ? AND year = ?
        ");
        $st->execute([$month, $year]);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $cid = (int)$row['cabang_id'];
            $results[$cid] = $row;
        }
    } catch (Throwable $e) {
        error_log("Error get_branch_exemptions: " . $e->getMessage());
    }

    return $results;
}

/**
 * Ambil daftar ID cabang (array integer) yang dilewati pada periode tertentu
 * 
 * @return int[]
 */
function get_branch_exempted_ids(int $month, int $year): array {
    $exemptions = get_branch_exemptions($month, $year);
    return array_map('intval', array_keys($exemptions));
}

/**
 * Cek apakah cabang tertentu dilewati pada periode bulan & tahun tertentu
 */
function is_branch_exempted(int $cabangId, int $month, int $year): bool {
    if ($cabangId <= 0) return false;
    $exemptions = get_branch_exemptions($month, $year);
    return !empty($exemptions[$cabangId]);
}

/**
 * Simpan atau batalkan status lewati cabang pada bulan & tahun tertentu
 */
function set_branch_exemption(int $cabangId, int $month, int $year, bool $exempt, string $reason = '', ?string $actor = null): bool {
    if ($cabangId <= 0 || $month < 1 || $month > 12 || $year < 2020) {
        return false;
    }
    ensure_branch_exemptions_schema();

    $actor = $actor ?: (function_exists('current_user_name') ? (current_user_name() ?: 'Admin IT') : 'Admin IT');
    $reason = trim($reason) !== '' ? trim($reason) : 'Tidak ada jadwal kunjungan cabang bulan ini';

    $cabangName = "Cabang #{$cabangId}";
    if (function_exists('get_cabang_by_id')) {
        $cb = get_cabang_by_id($cabangId);
        if ($cb) {
            $cabangName = $cb['nama'] ?? $cb['nama_cabang'] ?? $cabangName;
        }
    }

    $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $mName = $monthNames[$month] ?? "Bulan {$month}";

    if (function_exists('is_google_cloud_mode') && is_google_cloud_mode()) {
        $cacheFile = sys_get_temp_dir() . '/branch_exemptions_' . $year . '_' . $month . '.json';
        $curr = [];
        if (file_exists($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            if ($raw) {
                $dec = json_decode($raw, true);
                if (is_array($dec)) $curr = $dec;
            }
        }
        if ($exempt) {
            $curr[$cabangId] = [
                'id' => $cabangId,
                'cabang_id' => $cabangId,
                'month' => $month,
                'year' => $year,
                'reason' => $reason,
                'created_by' => $actor,
                'created_at' => date('Y-m-d H:i:s')
            ];
        } else {
            unset($curr[$cabangId]);
        }
        @file_put_contents($cacheFile, json_encode($curr, JSON_PRETTY_PRINT));
        return true;
    }

    try {
        $pdo = db();
        if ($exempt) {
            $stmt = $pdo->prepare("
                INSERT INTO branch_maintenance_exemptions (cabang_id, month, year, reason, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    reason = VALUES(reason),
                    created_by = VALUES(created_by),
                    created_at = NOW()
            ");
            $stmt->execute([$cabangId, $month, $year, $reason, $actor]);

            if (function_exists('record_audit_log')) {
                try {
                    record_audit_log(
                        'UPDATE',
                        'JADWAL_CABANG',
                        $cabangId,
                        $cabangName,
                        "Cabang '{$cabangName}' dilewati untuk pemeliharaan periode {$mName} {$year}. Alasan: {$reason} (oleh {$actor})"
                    );
                } catch (Throwable $e) {}
            }
        } else {
            $stmt = $pdo->prepare("
                DELETE FROM branch_maintenance_exemptions 
                WHERE cabang_id = ? AND month = ? AND year = ?
            ");
            $stmt->execute([$cabangId, $month, $year]);

            if (function_exists('record_audit_log')) {
                try {
                    record_audit_log(
                        'UPDATE',
                        'JADWAL_CABANG',
                        $cabangId,
                        $cabangName,
                        "Pemeliharaan cabang '{$cabangName}' diaktifkan kembali untuk periode {$mName} {$year} (oleh {$actor})"
                    );
                } catch (Throwable $e) {}
            }
        }
        return true;
    } catch (Throwable $e) {
        error_log("Error set_branch_exemption: " . $e->getMessage());
        return false;
    }
}

