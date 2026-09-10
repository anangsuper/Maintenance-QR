<?php
/**
 * Single Entrypoint Router for Vercel Hobby Plan (1 Serverless Function)
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = trim((string)$uri, '/');

if ($path === '' || $path === 'index.php') {
    $path = 'dashboard.php';
}

// Support serving static images/assets directly on Vercel
$staticExtensions = ['png', 'jpg', 'jpeg', 'svg', 'gif', 'webp', 'ico', 'css', 'js'];
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if (in_array($ext, $staticExtensions, true)) {
    $filePath = __DIR__ . '/' . basename($path);
    if (!is_file($filePath)) {
        $filePath = dirname(__DIR__) . '/' . $path;
    }
    if (is_file($filePath)) {
        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'css' => 'text/css',
            'js' => 'application/javascript'
        ];
        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=604800');
        readfile($filePath);
        exit;
    }
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
