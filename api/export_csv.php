<?php
require __DIR__ . '/bootstrap.php';
require_login();

$month = max(1, min(12, (int)($_GET['bulan'] ?? date('n'))));
$year = max(2020, min(2100, (int)($_GET['tahun'] ?? date('Y'))));
$cabangId = (int)($_GET['cabang'] ?? 0);

$audit = get_audit_maintenance_data([
    'bulan' => $month,
    'tahun' => $year,
    'cabang' => $cabangId
]);
$rows = $audit['rows'];

$filename = sprintf('Audit_Maintenance_IT_%04d_%02d.csv', $year, $month);
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

// Header Row
$headers = [
    'No',
    'Tanggal Maintenance',
    'Kode Inventaris',
    'Alamat IP',
    'Printer Terhubung',
    'Serial Number',
    'Perangkat',
    'Pengguna / User',
    'Divisi',
    'Cabang',
    'Teknisi Pelaksana',
    'Verifikasi Biometrik',
    'Status Pemeliharaan',
    'Temuan Masalah',
    'Rekomendasi Tindakan'
];

fputcsv($out, $headers, ';');

$no = 0;
foreach ($rows as $r) {
    $no++;
    $bioText = !empty($r['biometric_verified']) ? 'Terverifikasi Wajah (' . round($r['biometric_confidence'] ?? 0) . '%)' : 'Manual / Non-Biometrik';
    fputcsv($out, [
        $no,
        $r['maintenance_date'] ?? '-',
        $r['kode_inventaris'] ?? '-',
        $r['ip_address'] ?? '-',
        $r['printer'] ?? '-',
        $r['serial_number'] ?? '-',
        $r['perangkat'] ?? '-',
        $r['karyawan_nama'] ?? '-',
        $r['divisi_nama'] ?? '-',
        $r['cabang_nama'] ?? '-',
        $r['technician_name'] ?? '-',
        $bioText,
        $r['status'] ?? '-',
        $r['findings'] ?? '-',
        $r['recommendation'] ?? '-',
    ], ';');
}

fclose($out);
exit;
