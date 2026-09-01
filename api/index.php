<?php
require __DIR__ . '/bootstrap.php';
require_login();
header('Location: ' . module_url('dashboard.php'));
exit;
