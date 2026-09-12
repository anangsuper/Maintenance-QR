<?php
require __DIR__ . '/bootstrap.php';
require_login();

// Ambil parameter filter
$module = trim((string)($_GET['module'] ?? 'ALL'));
$action = trim((string)($_GET['action'] ?? 'ALL'));
$search = trim((string)($_GET['search'] ?? ''));
$month = (int)($_GET['bulan'] ?? 0);
$year = (int)($_GET['tahun'] ?? 0);

$filters = [
    'module' => $module,
    'action' => $action,
    'search' => $search,
    'bulan' => $month,
    'tahun' => $year
];

// Ambil hingga 5000 baris untuk ekspor audit
$auditData = get_audit_logs($filters, 5000, 0);
$rows = $auditData['rows'];

$filename = sprintf('Audit_Trail_Log_%s.csv', date('Ymd_His'));
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM untuk Microsoft Excel

// Header Kolom
$headers = [
    'No',
    'Waktu Kejadian (WITA)',
    'Petugas Pelaksana',
    'Hak Akses (Role)',
    'Alamat IP',
    'Modul',
    'Tipe Operasi (Aksi)',
    'Target / Objek',
    'Rincian Perubahan (Diff)'
];

fputcsv($out, $headers, ';');

$no = 0;
foreach ($rows as $r) {
    $no++;
    $createdAt = (string)($r['created_at'] ?? '');
    $ts = strtotime($createdAt);
    $timeFormatted = $ts ? date('d/m/Y H:i:s', $ts) : $createdAt;

    fputcsv($out, [
        $no,
        $timeFormatted,
        $r['user_name'] ?? 'System',
        strtoupper((string)($r['user_role'] ?? 'system')),
        $r['ip_address'] ?? '-',
        strtoupper((string)($r['module'] ?? '-')),
        strtoupper((string)($r['action'] ?? '-')),
        $r['target_label'] ?? '-',
        $r['details'] ?? '-'
    ], ';');
}

fclose($out);
exit;
