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
        sanitize_csv_cell($r['maintenance_date'] ?? '-'),
        sanitize_csv_cell($r['kode_inventaris'] ?? '-'),
        sanitize_csv_cell($r['ip_address'] ?? '-'),
        sanitize_csv_cell($r['printer'] ?? '-'),
        sanitize_csv_cell($r['serial_number'] ?? '-'),
        sanitize_csv_cell($r['perangkat'] ?? '-'),
        sanitize_csv_cell($r['karyawan_nama'] ?? '-'),
        sanitize_csv_cell($r['divisi_nama'] ?? '-'),
        sanitize_csv_cell($r['cabang_nama'] ?? '-'),
        sanitize_csv_cell($r['technician_name'] ?? '-'),
        sanitize_csv_cell($bioText),
        sanitize_csv_cell($r['status'] ?? '-'),
        sanitize_csv_cell($r['findings'] ?? '-'),
        sanitize_csv_cell($r['recommendation'] ?? '-'),
    ], ';');
}

fclose($out);
exit;
