<?php
$query = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
if (is_file(__DIR__ . '/api/users_admin.php')) {
    require __DIR__ . '/api/users_admin.php';
} else {
    header('Location: /api/users_admin.php' . $query);
    exit;
}
