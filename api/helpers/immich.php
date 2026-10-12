<?php
/**
 * Immich API Helper & Integration Module
 * Digunakan untuk integrasi web Maintenance QR dengan Server Immich
 * Dokumentasi API: https://immich.app/docs/api/
 */

function immich_config(): array {
    return [
        'base_url' => rtrim(cfg('immich_url', envv('IMMICH_URL', 'http://127.0.0.1:2283')), '/'),
        'api_key'  => cfg('immich_api_key', envv('IMMICH_API_KEY', '')),
    ];
}

/**
 * Melakukan HTTP Request ke Immich REST API
 */
function immich_request(string $endpoint, string $method = 'GET', ?array $data = null, array $extraHeaders = []): array {
    $cfg = immich_config();
    $url = $cfg['base_url'] . '/api/' . ltrim($endpoint, '/');
    $apiKey = $cfg['api_key'];

    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'IMMICH_API_KEY belum dikonfigurasi di config.local.php'];
    }

    $ch = curl_init();
    $headers = [
        'x-api-key: ' . $apiKey,
        'Accept: application/json',
    ];
    $headers = array_merge($headers, $extraHeaders);

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif (strtoupper($method) !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['success' => false, 'error' => 'Koneksi ke Immich gagal: ' . $err];
    }

    $json = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $json, 'http_code' => $httpCode];
    }

    return [
        'success' => false,
        'error' => $json['message'] ?? 'HTTP Error ' . $httpCode,
        'http_code' => $httpCode,
        'raw' => $response
    ];
}

/**
 * Cek status koneksi ke Immich Server
 */
function immich_ping(): array {
    return immich_request('server-info/version');
}

/**
 * Mengambil daftar album dari Immich
 */
function immich_get_albums(): array {
    return immich_request('albums');
}

/**
 * Mengambil daftar foto terbaru dari Immich
 */
function immich_get_recent_photos(int $limit = 50): array {
    return immich_request('search/metadata', 'POST', [
        'size' => $limit,
        'order' => 'desc'
    ]);
}

/**
 * Mengambil URL thumbnail foto dari Immich
 */
function immich_asset_thumbnail_url(string $assetId, string $size = 'thumbnail'): string {
    $cfg = immich_config();
    return $cfg['base_url'] . '/api/assets/' . urlencode($assetId) . '/thumbnail?size=' . urlencode($size);
}

/**
 * Upload foto ke server Immich secara otomatis
 */
function immich_upload_photo(string $filePath, string $deviceAssetId = ''): array {
    if (!is_file($filePath)) {
        return ['success' => false, 'error' => 'File tidak ditemukan di disk lokal.'];
    }

    $cfg = immich_config();
    $url = $cfg['base_url'] . '/api/assets';
    $apiKey = $cfg['api_key'];

    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'IMMICH_API_KEY belum dikonfigurasi.'];
    }

    $cfile = new CURLFile($filePath, mime_content_type($filePath) ?: 'image/jpeg', basename($filePath));
    $postData = [
        'assetData' => $cfile,
        'deviceAssetId' => $deviceAssetId ?: uniqid('asset_', true),
        'deviceId' => 'maintenance-qr-web',
        'fileCreatedAt' => date('c'),
        'fileModifiedAt' => date('c'),
        'isFavorite' => 'false'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-api-key: ' . $apiKey,
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['success' => false, 'error' => 'Gagal upload ke Immich: ' . $err];
    }

    $json = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'asset_id' => $json['id'] ?? '', 'data' => $json];
    }

    return ['success' => false, 'error' => $json['message'] ?? 'Upload error ' . $httpCode];
}
