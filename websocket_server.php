<?php
/**
 * Standalone High-Performance WebSocket Server (RFC 6455)
 * PT BPR Mitratama Arthabuana - QR Maintenance System
 * 
 * Fitur:
 * 1. Native PHP WebSocket Server - Tanpa dependensi Composer / C-Extension.
 * 2. Mendukung WebSocket browser (ws:// dan wss:// via reverse-proxy).
 * 3. Endpoint HTTP Internal (POST /publish) untuk menerima event dari scan.php / maintenance.php.
 * 4. Broadcast real-time ke semua dashboard yang sedang terbuka dalam hitungan milidetik.
 * 5. Ping-Pong Heartbeat otomatis untuk menjaga koneksi tetap hidup.
 * 
 * Penggunaan:
 *   php websocket_server.php [port]
 *   Default port: 8080
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(0);
ob_implicit_flush(true);

$host = '0.0.0.0';
$port = !empty($argv[1]) ? (int)$argv[1] : (int)(getenv('WS_PORT') ?: 8080);
$wsMagic = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

$context = stream_context_create([
    'socket' => [
        'so_reuseport' => 1,
        'so_reuseaddr' => 1,
        'backlog' => 128
    ]
]);

$server = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);

if (!$server) {
    echo "[ERROR] Tidak dapat membuka server pada port {$port}: {$errstr} ({$errno})\n";
    exit(1);
}

stream_set_blocking($server, false);

echo "=================================================================\n";
echo "🚀 QR MAINTENANCE REAL-TIME WEBSOCKET SERVER (RFC 6455)\n";
echo "=================================================================\n";
echo "📍 Listening on     : ws://{$host}:{$port}\n";
echo "📢 Publish Endpoint : http://127.0.0.1:{$port}/publish\n";
echo "⏰ Started At       : " . date('Y-m-d H:i:s') . "\n";
echo "💡 Tekan Ctrl+C untuk menghentikan server\n";
echo "-----------------------------------------------------------------\n\n";

/** @var array<int, resource> */
$clients = [];
/** @var array<int, array> */
$clientMeta = [];
$lastHeartbeat = time();

while (true) {
    $read = array_merge([$server], $clients);
    $write = null;
    $except = null;

    // Timeout 1 detik untuk stream_select
    $changed = @stream_select($read, $write, $except, 1);

    if ($changed === false) {
        break;
    }

    // 1. Terima koneksi baru
    if (in_array($server, $read, true)) {
        $newSocket = @stream_socket_accept($server, 0);
        if ($newSocket) {
            stream_set_blocking($newSocket, false);
            $id = (int)$newSocket;
            $clients[$id] = $newSocket;
            $clientMeta[$id] = [
                'handshake' => false,
                'connected_at' => time(),
                'ip' => stream_socket_get_name($newSocket, true) ?: 'unknown',
                'buffer' => ''
            ];
            echo "[" . date('H:i:s') . "] [+] Koneksi masuk dari {$clientMeta[$id]['ip']} (Client #{$id})\n";
        }
        $key = array_search($server, $read, true);
        unset($read[$key]);
    }

    // 2. Baca data dari klien
    foreach ($read as $socket) {
        $id = (int)$socket;
        $data = @fread($socket, 8192);

        if ($data === false || $data === '') {
            disconnect_client($socket, $id, $clients, $clientMeta, 'EOF / Closed by client');
            continue;
        }

        if (!isset($clientMeta[$id])) {
            continue;
        }

        // Jika belum handshake, periksa apakah HTTP Upgrade atau HTTP Publish
        if (!$clientMeta[$id]['handshake']) {
            $clientMeta[$id]['buffer'] .= $data;

            // Tunggu sampai header HTTP lengkap (\r\n\r\n)
            if (str_contains($clientMeta[$id]['buffer'], "\r\n\r\n")) {
                $rawHeaders = $clientMeta[$id]['buffer'];

                // Kasus A: HTTP POST /publish (dari kode PHP aplikasi)
                if (preg_match('/^POST \/(publish|broadcast)/i', $rawHeaders)) {
                    handle_http_publish($socket, $id, $rawHeaders, $clients, $clientMeta);
                    continue;
                }

                // Kasus B: HTTP WebSocket Handshake
                if (preg_match('/Sec-WebSocket-Key:\s*([^\r\n]+)/i', $rawHeaders, $m)) {
                    $key = trim($m[1]);
                    $accept = base64_encode(sha1($key . $wsMagic, true));
                    $upgrade = "HTTP/1.1 101 Switching Protocols\r\n" .
                               "Upgrade: websocket\r\n" .
                               "Connection: Upgrade\r\n" .
                               "Sec-WebSocket-Accept: {$accept}\r\n" .
                               "Sec-WebSocket-Version: 13\r\n\r\n";

                    @fwrite($socket, $upgrade);
                    $clientMeta[$id]['handshake'] = true;
                    $clientMeta[$id]['buffer'] = '';

                    echo "[" . date('H:i:s') . "] [✓] WebSocket Handshake sukses untuk Client #{$id} ({$clientMeta[$id]['ip']})\n";

                    // Kirim pesan selamat datang & info koneksi
                    $welcome = json_encode([
                        'type' => 'welcome',
                        'message' => 'Terhubung ke Real-Time WebSocket Server QR Maintenance',
                        'timestamp' => date('Y-m-d H:i:s'),
                        'client_id' => $id,
                        'total_clients' => count_active_ws($clientMeta)
                    ]);
                    @fwrite($socket, ws_encode($welcome));
                    continue;
                }

                // Kasus C: HTTP Biasa (healthcheck/info)
                $body = "QR Maintenance WebSocket Server Active. Connected clients: " . count_active_ws($clientMeta);
                $resp = "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body;
                @fwrite($socket, $resp);
                disconnect_client($socket, $id, $clients, $clientMeta, 'HTTP info response completed');
            }
            continue;
        }

        // 3. Klien yang sudah handshake mengirim frame WebSocket
        $decoded = ws_decode($data);
        if ($decoded === null) {
            continue;
        }

        $opcode = $decoded['opcode'];
        $payload = $decoded['payload'];

        // Opcode 8 = Close frame
        if ($opcode === 0x8) {
            disconnect_client($socket, $id, $clients, $clientMeta, 'Received close frame');
            continue;
        }

        // Opcode 9 = Ping frame -> Balas dengan Pong (Opcode 10)
        if ($opcode === 0x9) {
            @fwrite($socket, ws_encode($payload, 0xA));
            continue;
        }

        // Opcode 1 = Text frame
        if ($opcode === 0x1) {
            // Bisa menerima ping dari JS
            $msg = json_decode($payload, true);
            if (is_array($msg) && ($msg['action'] ?? '') === 'ping') {
                $pong = json_encode(['type' => 'pong', 'time' => time()]);
                @fwrite($socket, ws_encode($pong));
            }
        }
    }

    // 4. Heartbeat berkala setiap 30 detik untuk mendeteksi dead clients
    if (time() - $lastHeartbeat >= 30) {
        $lastHeartbeat = time();
        $pingFrame = ws_encode('', 0x9); // Ping
        foreach ($clients as $cId => $cSocket) {
            if (!empty($clientMeta[$cId]['handshake'])) {
                if (@fwrite($cSocket, $pingFrame) === false) {
                    disconnect_client($cSocket, $cId, $clients, $clientMeta, 'Ping failed / broken pipe');
                }
            }
        }
    }
}

fclose($server);

// =========================================================================
// HELPER FUNCTIONS
// =========================================================================

function count_active_ws(array $meta): int {
    $c = 0;
    foreach ($meta as $m) {
        if (!empty($m['handshake'])) $c++;
    }
    return $c;
}

function disconnect_client($socket, int $id, array &$clients, array &$clientMeta, string $reason = ''): void {
    if (is_resource($socket)) {
        @fclose($socket);
    }
    unset($clients[$id], $clientMeta[$id]);
    echo "[" . date('H:i:s') . "] [-] Client #{$id} terputus" . ($reason ? " ({$reason})" : "") . ". Total aktif: " . count_active_ws($clientMeta) . "\n";
}

function handle_http_publish($socket, int $id, string $rawHeaders, array &$clients, array &$clientMeta): void {
    $parts = explode("\r\n\r\n", $rawHeaders, 2);
    $body = $parts[1] ?? '';
    
    // Cari Content-Length jika payload panjang
    if (preg_match('/Content-Length:\s*(\d+)/i', $parts[0], $cl)) {
        $expectedLen = (int)$cl[1];
        while (strlen($body) < $expectedLen) {
            $chunk = @fread($socket, 8192);
            if ($chunk === false || $chunk === '') break;
            $body .= $chunk;
        }
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        $errResp = "HTTP/1.1 400 Bad Request\r\nContent-Type: application/json\r\n\r\n{\"error\":\"Invalid JSON\"}";
        @fwrite($socket, $errResp);
        disconnect_client($socket, $id, $clients, $clientMeta, 'Invalid publish JSON');
        return;
    }

    $event = $data['event'] ?? 'status_change';
    $payload = $data['payload'] ?? $data;

    echo "[" . date('H:i:s') . "] 📢 BROADCAST EVENT: {$event} -> menyebarkan ke " . count_active_ws($clientMeta) . " dashboard aktif\n";

    // Encode WebSocket Frame
    $wsMessage = json_encode([
        'type' => $event,
        'data' => $payload,
        'broadcast_at' => date('Y-m-d H:i:s')
    ]);
    $encodedFrame = ws_encode($wsMessage);

    $sentCount = 0;
    foreach ($clients as $cId => $cSocket) {
        if (!empty($clientMeta[$cId]['handshake']) && is_resource($cSocket)) {
            $res = @fwrite($cSocket, $encodedFrame);
            if ($res !== false) {
                $sentCount++;
            }
        }
    }

    // Balas HTTP response ke pengirim (PHP script)
    $respBody = json_encode(['success' => true, 'broadcast_to' => $sentCount]);
    $resp = "HTTP/1.1 200 OK\r\n" .
            "Content-Type: application/json\r\n" .
            "Content-Length: " . strlen($respBody) . "\r\n" .
            "Connection: close\r\n\r\n" .
            $respBody;
    @fwrite($socket, $resp);
    disconnect_client($socket, $id, $clients, $clientMeta, 'HTTP publish finished');
}

/**
 * Encode payload string ke format frame WebSocket RFC 6455
 */
function ws_encode(string $payload, int $opcode = 0x1): string {
    $length = strlen($payload);
    $firstByte = 0x80 | ($opcode & 0x0f); // FIN = 1
    
    if ($length <= 125) {
        $header = pack('CC', $firstByte, $length);
    } elseif ($length <= 65535) {
        $header = pack('CCn', $firstByte, 126, $length);
    } else {
        $header = pack('CCNN', $firstByte, 127, 0, $length);
    }

    return $header . $payload;
}

/**
 * Decode frame WebSocket dari client
 */
function ws_decode(string $data): ?array {
    $len = strlen($data);
    if ($len < 2) {
        return null;
    }

    $firstByte = ord($data[0]);
    $secondByte = ord($data[1]);

    $opcode = $firstByte & 0x0f;
    $isMasked = ($secondByte & 0x80) !== 0;
    $payloadLen = $secondByte & 0x7f;

    $offset = 2;

    if ($payloadLen === 126) {
        if ($len < 4) return null;
        $payloadLen = unpack('n', substr($data, 2, 2))[1];
        $offset = 4;
    } elseif ($payloadLen === 127) {
        if ($len < 10) return null;
        $payloadLen = unpack('J', substr($data, 2, 8))[1];
        $offset = 10;
    }

    if ($isMasked) {
        if ($len < $offset + 4) return null;
        $mask = substr($data, $offset, 4);
        $offset += 4;
        $payload = substr($data, $offset, $payloadLen);
        $unmasked = '';
        for ($i = 0; $i < strlen($payload); $i++) {
            $unmasked .= $payload[$i] ^ $mask[$i % 4];
        }
        $payload = $unmasked;
    } else {
        $payload = substr($data, $offset, $payloadLen);
    }

    return [
        'opcode' => $opcode,
        'payload' => $payload
    ];
}
