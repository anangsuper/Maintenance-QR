<?php
/**
 * AUTO RUN FULL MIGRATION - RESET & IMPORT DARI SPREADSHEET LIVE
 * Menghapus seluruh data lama dan mengimpor 13 tabel dari Google Spreadsheet (Oktober 2026).
 *
 * Akses Web: https://192.168.88.163/run_full_migration.php
 * Akses CLI: php /var/www/html/Server-Maintenance/api/run_full_migration.php
 */
require_once __DIR__ . '/bootstrap.php';

$isCli = (php_sapi_name() === 'cli');
$sqlFile = dirname(__DIR__) . '/sql/05_full_database_migration.sql';

$executed = false;
$error = null;
$tableStats = [];
$executionTime = 0;

if ($isCli || (isset($_POST['confirm_migration']) && $_POST['confirm_migration'] === 'yes') || isset($_GET['auto'])) {
    $startTime = microtime(true);
    try {
        if (!file_exists($sqlFile)) {
            throw new Exception("File SQL tidak ditemukan di: " . $sqlFile);
        }

        $pdo = db();
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);

        // Baca file SQL
        $sqlContent = file_get_contents($sqlFile);
        if (!$sqlContent) {
            throw new Exception("File SQL kosong!");
        }

        // Jalankan perintah multi-query
        // Karena PDO exec() standar MySQL mendukung multi query jika diset, atau kita pecah per baris / statement
        // Untuk amannya, kita jalankan per statement
        $lines = explode("\n", $sqlContent);
        $currentQuery = '';
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $pdo->exec("SET NAMES utf8mb4;");

        $stmtCount = 0;
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                continue;
            }

            $currentQuery .= $line . "\n";
            if (str_ends_with($trimmed, ';')) {
                // Eksekusi query
                $q = trim($currentQuery);
                // Lewati USE db_maintenance_qr jika database sudah terkoneksi
                if (stripos($q, 'USE ') === 0) {
                    $currentQuery = '';
                    continue;
                }
                
                try {
                    $pdo->exec($q);
                    $stmtCount++;
                    $currentQuery = '';
                } catch (Throwable $eq) {
                    throw new Exception("Error pada query #{$stmtCount} (" . substr($q, 0, 120) . "): " . $eq->getMessage());
                }
            }
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        // Ambil statistik jumlah record saat ini
        $tables = [
            'cabang', 'divisi', 'kategori_aset', 'karyawan', 'assets',
            'asset_qr_tokens', 'users', 'maintenance_scan', 'maintenance_checklists',
            'maintenance_findings', 'audit_trail', 'inventaris_kartu', 'employee_complaints'
        ];

        foreach ($tables as $tbl) {
            try {
                $cnt = $pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
                $tableStats[$tbl] = (int)$cnt;
            } catch (Exception $e) {
                $tableStats[$tbl] = 'Error: ' . $e->getMessage();
            }
        }

        $executed = true;
        $executionTime = round(microtime(true) - $startTime, 2);

        if ($isCli) {
            echo "\n=======================================================\n";
            echo "   MIGRASI DATA LENGKAP BERHASIL ({$executionTime} detik) \n";
            echo "=======================================================\n";
            echo "Total SQL statements dieksekusi: {$stmtCount}\n\n";
            echo "STATISTIK JUMLAH DATA TERBARU:\n";
            foreach ($tableStats as $tbl => $cnt) {
                printf(" - %-25s : %s baris\n", $tbl, $cnt);
            }
            echo "\nSemua data lama berhasil di-reset dan digantikan data spreadsheet!\n";
            exit(0);
        }

    } catch (Throwable $t) {
        $error = $t->getMessage();
        if ($isCli) {
            echo "\n[ERROR] Gagal melakukan migrasi: " . $error . "\n";
            exit(1);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrasi Database Lengkap - Maintenance QR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-2xl w-full bg-slate-800 rounded-2xl shadow-2xl border border-slate-700 p-6 sm:p-8">
        
        <div class="flex items-center space-x-4 mb-6 border-b border-slate-700 pb-5">
            <div class="w-12 h-12 rounded-xl bg-blue-600/20 text-blue-400 flex items-center justify-center text-2xl font-bold">
                <i class="fas fa-database"></i>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-white">Full Database Migration Tool</h1>
                <p class="text-sm text-slate-400">Sinkronisasi Penuh Database dari Google Spreadsheet Live</p>
            </div>
        </div>

        <?php if ($executed): ?>
            <div class="bg-emerald-950/60 border border-emerald-500/40 rounded-xl p-5 mb-6 text-emerald-200">
                <div class="flex items-center space-x-3 mb-2">
                    <i class="fas fa-check-circle text-2xl text-emerald-400"></i>
                    <h2 class="text-lg font-bold text-emerald-300">Migrasi Data Sukses! (<?= $executionTime ?> detik)</h2>
                </div>
                <p class="text-sm text-emerald-300/80 mb-4">
                    Seluruh data lama telah berhasil di-reset (TRUNCATE) dan digantikan oleh seluruh isi spreadsheet terbaru.
                </p>

                <h3 class="text-xs uppercase font-bold tracking-wider text-emerald-400 mb-2 border-b border-emerald-800 pb-1">
                    Ringkasan Jumlah Data di Database:
                </h3>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <?php foreach ($tableStats as $tbl => $cnt): ?>
                        <div class="flex justify-between bg-emerald-900/40 px-3 py-1.5 rounded-lg border border-emerald-700/30">
                            <span class="font-mono text-slate-300"><?= htmlspecialchars($tbl) ?>:</span>
                            <span class="font-bold text-emerald-400"><?= htmlspecialchars((string)$cnt) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <a href="/dashboard.php" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-medium py-3 px-4 rounded-xl text-center transition flex items-center justify-center space-x-2">
                    <i class="fas fa-home"></i>
                    <span>Buka Dashboard</span>
                </a>
                <a href="/assets.php" class="flex-1 bg-slate-700 hover:bg-slate-600 text-white font-medium py-3 px-4 rounded-xl text-center transition flex items-center justify-center space-x-2">
                    <i class="fas fa-boxes"></i>
                    <span>Lihat Master Aset</span>
                </a>
            </div>

        <?php elseif ($error): ?>
            <div class="bg-rose-950/60 border border-rose-500/40 rounded-xl p-5 mb-6 text-rose-200">
                <div class="flex items-center space-x-3 mb-2">
                    <i class="fas fa-exclamation-triangle text-2xl text-rose-400"></i>
                    <h2 class="text-lg font-bold text-rose-300">Gagal Melakukan Migrasi</h2>
                </div>
                <p class="text-sm font-mono bg-rose-900/60 p-3 rounded-lg border border-rose-800/50 break-words">
                    <?= htmlspecialchars($error) ?>
                </p>
                <form method="POST" class="mt-4">
                    <input type="hidden" name="confirm_migration" value="yes">
                    <button type="submit" class="bg-rose-600 hover:bg-rose-500 text-white text-sm font-medium py-2 px-4 rounded-lg transition">
                        <i class="fas fa-redo mr-1"></i> Coba Lagi
                    </button>
                </form>
            </div>

        <?php else: ?>
            <div class="bg-amber-950/40 border border-amber-500/30 rounded-xl p-4 mb-6 text-amber-200 text-sm">
                <div class="flex items-start space-x-3">
                    <i class="fas fa-info-circle text-amber-400 text-lg mt-0.5"></i>
                    <div>
                        <strong class="text-amber-300 block mb-1">Perhatian Penting:</strong>
                        Tindakan ini akan mengosongkan (TRUNCATE) seluruh data yang ada sebelumnya di database server dan memasukkan kembali 100% data resmi dari file Google Spreadsheet terbaru yang telah disiapkan (termasuk 83 Aset, 111 Karyawan, 19 Divisi, 6 Cabang, 88 Riwayat Scan, 1403 Checklist, dan 532 Kartu Inventaris FBD3).
                    </div>
                </div>
            </div>

            <form method="POST" class="space-y-4" onsubmit="return confirm('PERINGATAN: Apakah Anda yakin ingin MERESET dan MENIMPA seluruh database dengan data spreadsheet terbaru?');">
                <input type="hidden" name="confirm_migration" value="yes">
                
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg transition flex items-center justify-center space-x-2 text-base">
                    <i class="fas fa-sync-alt fa-spin-hover"></i>
                    <span>JALANKAN MIGRASI SEKARANG (RESET & IMPORT)</span>
                </button>
            </form>
        <?php endif; ?>

        <div class="mt-6 pt-4 border-t border-slate-700/60 text-center text-xs text-slate-500">
            QR Maintenance System &bull; BPR Mitratama &bull; Automated Database Migration
        </div>
    </div>
</body>
</html>
