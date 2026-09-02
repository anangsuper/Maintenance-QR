<?php
require __DIR__ . '/bootstrap.php';

logout_user();

// Mulai session baru setelah destroy
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION['flash_login'] = 'Anda telah berhasil keluar dari sistem.';
header('Location: ' . module_url('login.php'));
exit;
