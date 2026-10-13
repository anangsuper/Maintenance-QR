<?php
/**
 * CLI Tool: Reset Password User & Bersihkan Lockout Throttle
 * Hanya dapat dijalankan melalui terminal (Command Line / CLI)
 * 
 * Penggunaan:
 *   php api/cli_reset_password.php [username] [password_baru]
 * Contoh:
 *   php api/cli_reset_password.php admin admin123
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Akses ditolak. Skrip ini hanya dapat dijalankan melalui terminal CLI server.\n");
}

require __DIR__ . '/bootstrap.php';

$targetUser = $argv[1] ?? 'admin';
$newPass = $argv[2] ?? 'admin123';

echo "=== RESET PASSWORD & UNLOCK TOOL ===\n";
echo "Target Username : {$targetUser}\n";
echo "Password Baru   : {$newPass}\n\n";

try {
    // 1. Bersihkan seluruh lockout (jika akun atau IP sempat terkunci)
    if (function_exists('clear_all_login_lockouts')) {
        clear_all_login_lockouts();
        echo "[1/3] Berhasil membersihkan seluruh status lockout/throttle.\n";
    }

    // 2. Cek apakah user ada di database
    $st = db()->prepare("SELECT id, username, role FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
    $st->execute([$targetUser]);
    $user = $st->fetch();

    $hashed = password_hash($newPass, PASSWORD_DEFAULT);

    if ($user) {
        $upd = db()->prepare("UPDATE users SET password = ?, status = 'Aktif' WHERE id = ?");
        $upd->execute([$hashed, $user['id']]);
        echo "[2/3] Password untuk user '{$user['username']}' (Role: {$user['role']}) berhasil diperbarui.\n";
    } else {
        // Buat akun baru jika belum ada
        $ins = db()->prepare("INSERT INTO users (nama, username, password, role, status, created_at) VALUES (?, ?, ?, 'admin', 'Aktif', NOW())");
        $ins->execute([ucfirst($targetUser), $targetUser, $hashed]);
        echo "[2/3] User '{$targetUser}' belum ada sebelumnya. Akun baru Administrator telah dibuat.\n";
    }

    echo "[3/3] SELESAI!\n\n";
    echo "Silakan login sekarang di browser menggunakan:\n";
    echo "Username : {$targetUser}\n";
    echo "Password : {$newPass}\n\n";

} catch (Throwable $e) {
    echo "[ERROR] Terjadi kesalahan: " . $e->getMessage() . "\n";
}
