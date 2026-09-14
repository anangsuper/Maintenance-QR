<?php
/**
 * PWA Dynamic Icon Server
 * Provides compliant square icons (192x192, 512x512) for PWA installation
 */
$size = isset($_GET['size']) ? (int)$_GET['size'] : 192;
if ($size !== 512 && $size !== 192) {
    $size = 192;
}

$candidates = [
    __DIR__ . '/logo.png',
    __DIR__ . '/Logo Storek Putih Di text.png',
    dirname(__DIR__) . '/logo.png',
    dirname(__DIR__) . '/Logo Storek Putih Di text.png'
];

$sourcePath = null;
foreach ($candidates as $c) {
    if (file_exists($c)) {
        $sourcePath = $c;
        break;
    }
}

if (!$sourcePath || !file_exists($sourcePath)) {
    http_response_code(404);
    exit("Icon source not found");
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');

// If GD extension is available, generate a clean square canvas with corporate navy backdrop
if (function_exists('imagecreatefrompng') && function_exists('imagecreatetruecolor') && function_exists('imagecopyresampled')) {
    $srcImg = @imagecreatefrompng($sourcePath);
    if ($srcImg) {
        $srcW = imagesx($srcImg);
        $srcH = imagesy($srcImg);

        $dstImg = imagecreatetruecolor($size, $size);
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);

        // Fill background with navy-deep (#08182F)
        $bg = imagecolorallocate($dstImg, 8, 24, 47);
        imagefilledrectangle($dstImg, 0, 0, $size, $size, $bg);
        imagealphablending($dstImg, true);

        // Compute aspect ratio fit with padding (15% margin)
        $pad = (int)round($size * 0.15);
        $availW = $size - ($pad * 2);
        $availH = $size - ($pad * 2);

        $scale = min($availW / $srcW, $availH / $srcH);
        $newW = (int)round($srcW * $scale);
        $newH = (int)round($srcH * $scale);
        $dstX = (int)round(($size - $newW) / 2);
        $dstY = (int)round(($size - $newH) / 2);

        imagecopyresampled($dstImg, $srcImg, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);
        imagepng($dstImg);
        imagedestroy($srcImg);
        imagedestroy($dstImg);
        exit;
    }
}

// Fallback: output source image directly
header('Content-Length: ' . filesize($sourcePath));
readfile($sourcePath);
exit;
