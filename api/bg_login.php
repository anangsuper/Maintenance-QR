<?php
/**
 * Endpoint Pengirim Gambar Background Login Bank Mitra
 * Mendukung Vercel Serverless & Localhost
 */
$imgPath = __DIR__ . '/login_bg.png';
if (!file_exists($imgPath)) {
    $imgPath = dirname(__DIR__) . '/ChatGPT Image Sep 10, 2026, 02_16_08 PM.png';
}

if (file_exists($imgPath)) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800');
    header('Content-Length: ' . filesize($imgPath));
    readfile($imgPath);
    exit;
}

http_response_code(404);
echo "Background image not found";
