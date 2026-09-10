<?php
/**
 * Master Bootstrap Loader for QR Maintenance System
 * 
 * Memuat modul-modul terpisah dari folder includes/
 * Menjaga 100% kompatibilitas mundur dengan seluruh halaman aplikasi.
 */
require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/users.php';
require_once __DIR__ . '/includes/master_data.php';
require_once __DIR__ . '/includes/assets.php';
require_once __DIR__ . '/includes/maintenance.php';
require_once __DIR__ . '/includes/dashboard.php';
require_once __DIR__ . '/includes/view.php';
