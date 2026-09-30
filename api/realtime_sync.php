<?php
/**
 * Real-Time Sync & Fallback API Endpoint
 * PT BPR Mitratama Arthabuana - QR Maintenance System
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helpers/websocket_broadcaster.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Endpoint publik atau terotentikasi untuk pengecekan status
$action = (string)($_GET['action'] ?? 'sync');
$since = (int)($_GET['since'] ?? 0);
$month = max(1, min(12, (int)($_GET['bulan'] ?? date('n'))));
$year = max(2020, min(2050, (int)($_GET['tahun'] ?? date('Y'))));
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));

if ($action === 'ws_status') {
    // Cek apakah WebSocket server port 8080 aktif
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

// Ambil event fallback terbaru
$newEvents = get_fallback_events($since);

// Ambil juga snapshot metrik dashboard terbaru untuk verifikasi kepatuhan
$data = get_dashboard_data($month, $year, $cabangId);
$total = (int)($data['total'] ?? 0);
$done = (int)($data['done'] ?? 0);
$findings = (int)($data['findings'] ?? 0);
$pending = max(0, $total - $done);
$percent = $total > 0 ? round(($done / $total) * 100) : 0;
$branchSummaries = get_branch_maintenance_summary($month, $year);

$latestEventId = 0;
foreach ($newEvents as $ev) {
    $eId = (int)($ev['event_id'] ?? 0);
    if ($eId > $latestEventId) {
        $latestEventId = $eId;
    }
}
if ($latestEventId === 0) {
    $latestEventId = (int)(microtime(true) * 1000);
}

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
