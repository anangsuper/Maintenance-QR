<?php
/**
 * Single Entrypoint Router for Vercel Hobby Plan (1 Serverless Function)
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = trim((string)$uri, '/');

if ($path === '' || $path === 'index.php') {
    $path = 'dashboard.php';
}

$page = basename($path);
if (!str_ends_with($page, '.php')) {
    $page .= '.php';
}

$targetFile = __DIR__ . '/' . $page;

if ($page !== 'index.php' && is_file($targetFile)) {
    require $targetFile;
} else {
    require __DIR__ . '/dashboard.php';
}
