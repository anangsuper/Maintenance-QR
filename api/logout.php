<?php
require __DIR__ . '/bootstrap.php';

$uName = current_user_name() ?: 'Pengguna';
$uId = current_user_id();
record_audit_log('LOGOUT', 'KEAMANAN', $uId, $uName, 'Pengguna keluar dari sistem');

logout_user();

// Mulai session baru setelah destroy
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION['flash_login'] = 'Anda telah berhasil keluar dari sistem.';
header('Location: ' . module_url('login.php'));
exit;
