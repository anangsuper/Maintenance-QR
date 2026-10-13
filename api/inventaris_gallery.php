<?php
require __DIR__ . '/bootstrap.php';
require_login();

// Redirect ke halaman galeri terpadu dengan sub-tab inventaris
$params = $_GET;
$params['sub'] = 'inventaris';
header('Location: ' . module_url('asset_gallery.php', $params));
exit;
