<?php
/**
 * Endpoint Pengirim Gambar Logo Bank Mitra
 * Mendukung Vercel Serverless, Apache, XAMPP, & Localhost
 */
$candidates = [
    __DIR__ . '/logo.png',
    __DIR__ . '/Logo Storek Putih Di text.png',
    dirname(__DIR__) . '/Logo Storek Putih Di text.png',
    dirname(__DIR__) . '/logo.png'
];

$imgPath = null;
foreach ($candidates as $p) {
    if (file_exists($p)) {
        $imgPath = $p;
        break;
    }
}

if ($imgPath && file_exists($imgPath)) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800');
    header('Content-Length: ' . filesize($imgPath));
    readfile($imgPath);
    exit;
}

http_response_code(404);
echo "Logo image not found";
