<?php
require __DIR__ . '/bootstrap.php';

$uName = current_user_name() ?: 'Pengguna';
$uId = current_user_id();
if ($uId > 0) {
    try {
        record_audit_log('LOGOUT', 'KEAMANAN', $uId, $uName, 'Pengguna keluar dari sistem');
    } catch (Throwable $e) {}
}

logout_user();

// Mulai session baru setelah destroy untuk flash message
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION['flash_login'] = 'Anda telah berhasil keluar dari sistem.';

// Lepaskan session lock segera sebelum redirect ke halaman login
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Location: ' . module_url('login.php'));
exit;
