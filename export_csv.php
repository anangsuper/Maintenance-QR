<?php
require __DIR__ . '/bootstrap.php';
require_login();

$month = max(1, min(12, (int)($_GET['bulan'] ?? date('n'))));
$year = max(2020, min(2100, (int)($_GET['tahun'] ?? date('Y'))));
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));

$rows = get_history_rows($month, $year, $cabangId);

$filename = sprintf('maintenance_%04d_%02d.csv', $year, $month);
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Tanggal','Jam','Kode Inventaris','Serial Number','Merk','Model','Pemilik','Cabang','Teknisi','Status'], ';');
foreach ($rows as $r) {
    fputcsv($out, [
        $r['maintenance_date'] ?? '',
        substr($r['maintenance_time'] ?? '', 0, 5),
        $r['kode_inventaris'] ?? '',
        $r['serial_number'] ?? '',
        $r['merk'] ?? '',
        $r['model'] ?? '',
        $r['karyawan_nama'] ?? '',
        $r['cabang_nama'] ?? '',
        $r['teknisi_nama'] ?? $r['technician_name'] ?? '',
        $r['status'] ?? '',
    ], ';');
}
fclose($out);
exit;
