<?php
/**
 * Real-time WebSocket & Event Broadcaster
 * PT BPR Mitratama Arthabuana - QR Maintenance System
 */

declare(strict_types=1);

/**
 * Kirim notifikasi perubahan status/data ke WebSocket Server & Fallback State
 * 
 * @param string $event Tipe event (misal: 'status_change', 'scan_created', 'finding_resolved')
 * @param array $payload Data detail perubahan (asset, teknisi, status, totals baru, dll)
 * @return bool
 */
function broadcast_dashboard_change(string $event, array $payload): bool {
    $now = date('Y-m-d H:i:s');
    $eventId = (int)(microtime(true) * 1000);

    $fullMessage = [
        'event_id' => $eventId,
        'event' => $event,
        'timestamp' => $now,
        'payload' => $payload
    ];

    // 1. Simpan ke Local State Buffer (untuk Fallback Polling / SSE jika WebSocket daemon tidak berjalan)
    save_event_to_fallback_buffer($fullMessage);

    // 2. Kirim ke WebSocket Server via HTTP POST /publish (non-blocking, timeout 200ms)
    $wsPort = (int)(getenv('WS_PORT') ?: 8080);
    $wsHost = getenv('WS_HOST') ?: '127.0.0.1';

    $jsonBody = json_encode($fullMessage);

    $ch = curl_init("http://{$wsHost}:{$wsPort}/publish");
    if ($ch) {
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonBody),
                'Connection: close'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => 250, // sangat cepat, tidak menahan eksekusi pengguna
            CURLOPT_CONNECTTIMEOUT_MS => 150
        ]);
        @curl_exec($ch);
        @curl_close($ch);
    } else {
        // Fallback socket jika cURL tidak aktif
        $fp = @fsockopen($wsHost, $wsPort, $errno, $errstr, 0.2);
        if ($fp) {
            stream_set_timeout($fp, 0, 200000);
            $out = "POST /publish HTTP/1.1\r\n" .
                   "Host: {$wsHost}:{$wsPort}\r\n" .
                   "Content-Type: application/json\r\n" .
                   "Content-Length: " . strlen($jsonBody) . "\r\n" .
                   "Connection: close\r\n\r\n" .
                   $jsonBody;
            @fwrite($fp, $out);
            @fclose($fp);
        }
    }

    return true;
}

/**
 * Menyimpan buffer 50 event terakhir di folder temporary sistem
 */
function save_event_to_fallback_buffer(array $event): void {
    $bufferFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qr_maint_realtime_events.json';
    
    $events = [];
    if (file_exists($bufferFile)) {
        $raw = @file_get_contents($bufferFile);
        if ($raw) {
            $parsed = json_decode($raw, true);
            if (is_array($parsed)) {
                $events = $parsed;
            }
        }
    }

    $events[] = $event;
    // Simpan maksimal 50 event terakhir
    if (count($events) > 50) {
        $events = array_slice($events, -50);
    }

    @file_put_contents($bufferFile, json_encode($events), LOCK_EX);
}

/**
 * Ambil daftar event fallback sejak timestamp / event_id tertentu
 */
function get_fallback_events(int $sinceEventId = 0): array {
    $bufferFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qr_maint_realtime_events.json';
    if (!file_exists($bufferFile)) {
        return [];
    }

    $raw = @file_get_contents($bufferFile);
    if (!$raw) return [];

    $events = json_decode($raw, true);
    if (!is_array($events)) return [];

    if ($sinceEventId <= 0) {
        return array_slice($events, -10);
    }

    return array_values(array_filter($events, function($e) use ($sinceEventId) {
        return (int)($e['event_id'] ?? 0) > $sinceEventId;
    }));
}
