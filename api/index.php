<?php
/**
 * Single Entrypoint Router for Vercel Hobby Plan (1 Serverless Function)
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = trim((string)$uri, '/');

if ($path === '' || $path === 'index.php') {
    $pageParam = trim((string)($_GET['page'] ?? ''));
    if ($pageParam !== '') {
        $path = $pageParam;
    } else {
        $path = 'dashboard.php';
    }
}

// Blokir akses langsung ke direktori terlarang atau dotfiles
if (
    str_starts_with($path, '.') ||
    str_contains($path, '/.') ||
    str_starts_with($path, 'sql/') ||
    str_starts_with($path, 'api/includes/') ||
    str_starts_with($path, 'api/helpers/') ||
    str_starts_with($path, 'includes/') ||
    str_starts_with($path, 'helpers/')
) {
    http_response_code(403);
    exit('Akses Ditolak');
}

// Blokir ekstensi file sensitif (CWE-552 Information Exposure)
$blockedExts = ['env', 'sql', 'rar', 'zip', 'tar', 'gz', '7z', 'xlsx', 'xls', 'csv', 'bak', 'log', 'md', 'example'];
$requestExt = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if (in_array($requestExt, $blockedExts, true)) {
    http_response_code(403);
    exit('Akses Ditolak');
}

// Support serving static images/assets and PWA files directly on Vercel
$staticExtensions = ['png', 'jpg', 'jpeg', 'svg', 'gif', 'webp', 'ico', 'css', 'js', 'json', 'webmanifest'];
$ext = $requestExt;
if (in_array($ext, $staticExtensions, true)) {
    // Mencegah Directory Traversal (CWE-22)
    if (str_contains($path, '..') || str_contains($path, '\\')) {
        http_response_code(403);
        exit('Akses Ditolak');
    }

    $fileName = basename($path);
    // Larang akses ke file konfigurasi, template internal, data JSON selain manifest, atau dotfile tersembunyi
    $blockedFiles = ['vercel.json', 'composer.json', 'package.json', 'package-lock.json', 'tsconfig.json', 'fbd3_data.json', 'kartu_template.html'];
    if (
        in_array(strtolower($fileName), $blockedFiles, true) ||
        str_starts_with($fileName, '.') ||
        ($ext === 'json' && !in_array($fileName, ['manifest.json', 'manifest.webmanifest'], true))
    ) {
        http_response_code(403);
        exit('Akses Ditolak');
    }

    $baseDir = dirname(__DIR__);
    $filePath = __DIR__ . '/' . $fileName;
    if (!is_file($filePath)) {
        $filePath = $baseDir . '/' . $path;
    }

    $realPath = realpath($filePath);
    $realBase = realpath($baseDir);

    if ($realPath && $realBase && str_starts_with($realPath, $realBase) && is_file($realPath)) {
        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'webmanifest' => 'application/manifest+json',
            'html' => 'text/html; charset=utf-8'
        ];
        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
        if ($fileName === 'sw.js') {
            header('Service-Worker-Allowed: /');
            header('Cache-Control: no-cache, no-store, must-revalidate');
        } else {
            header('Cache-Control: public, max-age=604800');
        }
        readfile($realPath);
        exit;
    }
}

$page = basename($path);
if (!str_ends_with($page, '.php')) {
    $page .= '.php';
}

$forbiddenPages = ['config.local.php', 'config.local.example.php', 'bootstrap.php'];
if (in_array(strtolower($page), $forbiddenPages, true) || str_starts_with($page, 'config.')) {
    http_response_code(403);
    exit('Akses Ditolak');
}

$targetFile = __DIR__ . '/' . $page;

if ($page !== 'index.php' && is_file($targetFile)) {
    require $targetFile;
} else {
    require __DIR__ . '/dashboard.php';
}
