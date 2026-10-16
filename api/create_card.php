<?php
require __DIR__ . '/bootstrap.php';
require_login();

// Redirect langsung ke tab kartu inventaris dan otomatis membuka modal tambah kartu
header('Location: ' . module_url('dashboard.php', ['tab' => 'kartu', 'add' => 1]) . '#addCardModal');
exit;
