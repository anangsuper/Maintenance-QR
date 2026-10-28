<?php
/**
 * Real-Time Sync & Fallback API Endpoint
 * PT BPR Mitratama Arthabuana - QR Maintenance System
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

// Endpoint publik untuk status WS
$action = (string)($_GET['action'] ?? 'sync');
if ($action === 'ws_status') {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    $wsPort = (int)(getenv('WS_PORT') ?: 8080);
    $wsHost = getenv('WS_HOST') ?: '127.0.0.1';
    $fp = @fsockopen($wsHost, $wsPort, $errno, $errstr, 0.2);
    $online = (bool)$fp;
    if ($fp) @fclose($fp);

    echo json_encode([
        'online' => $online,
        'ws_port' => $wsPort,
        'ws_url' => 'ws://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ':' . $wsPort
    ]);
    exit;
}

// Cek apakah user masih login. Jika sesi habis, langsung respons 401 tanpa menjalankan query database berat!
if (!is_logged_in()) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo json_encode([
        'success' => false,
        'error' => 'session_expired',
        'message' => 'Sesi telah berakhir karena tidak ada aktivitas. Silakan masuk kembali.',
        'redirect' => module_url('login.php', ['expired' => 1])
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Lepaskan lock sesi secepat mungkin agar background polling tidak memblokir tab/klik lain
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
require_once __DIR__ . '/helpers/websocket_broadcaster.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$since = (int)($_GET['since'] ?? 0);
$month = max(1, min(12, (int)($_GET['bulan'] ?? date('n'))));
$year = max(2020, min(2050, (int)($_GET['tahun'] ?? date('Y'))));
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));

// Ambil event fallback terbaru
$newEvents = get_fallback_events($since);

$latestEventId = 0;
foreach ($newEvents as $ev) {
    $eId = (int)($ev['event_id'] ?? 0);
    if ($eId > $latestEventId) {
        $latestEventId = $eId;
    }
}
if ($latestEventId === 0) {
    $latestEventId = $since > 0 ? $since : (int)(microtime(true) * 1000);
}

// Optimasi Kinerja Tinggi: Jika polling berkala ($since > 0) dan tidak ada aktivitas baru,
// langsung return respons instan tanpa menjalankan puluhan query agregasi database!
if ($since > 0 && empty($newEvents)) {
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'latest_event_id' => $latestEventId,
        'has_new_events' => false,
        'events' => [],
        'metrics' => null,
        'branches' => null
    ]);
    exit;
}

// Hanya jika ada event baru ATAU initial load ($since === 0), hitung snapshot metrik
$data = get_dashboard_data($month, $year, $cabangId);
$total = (int)($data['total'] ?? 0);
$done = (int)($data['done'] ?? 0);
$findings = (int)($data['findings'] ?? 0);
$pending = max(0, $total - $done);
$percent = $total > 0 ? round(($done / $total) * 100) : 0;
$branchSummaries = get_branch_maintenance_summary($month, $year);

echo json_encode([
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'latest_event_id' => $latestEventId,
    'has_new_events' => !empty($newEvents),
    'events' => $newEvents,
    'metrics' => [
        'total' => $total,
        'done' => $done,
        'pending' => $pending,
        'findings' => $findings,
        'percent' => $percent,
    ],
    'branches' => $branchSummaries
]);
