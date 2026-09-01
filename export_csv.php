<?php
require __DIR__ . '/bootstrap.php';
require_login();

$month = max(1, min(12, (int)($_GET['bulan'] ?? date('n'))));
$year = max(2020, min(2100, (int)($_GET['tahun'] ?? date('Y'))));
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));

$cName = name_column('cabang') ?: 'id';
$kName = name_column('karyawan') ?: 'id';
$uName = name_column('users') ?: 'id';

$sql = "
SELECT ms.maintenance_date, ms.maintenance_time, ms.status,
       a.kode_inventaris, a.serial_number, a.merk, a.model,
       c.`{$cName}` AS cabang_nama,
       k.`{$kName}` AS karyawan_nama,
       u.`{$uName}` AS teknisi_nama
FROM maintenance_scan ms
JOIN assets a ON a.id = ms.asset_id
LEFT JOIN cabang c ON c.id = a.id_cabang
LEFT JOIN karyawan k ON k.id = a.id_karyawan
LEFT JOIN users u ON u.id = ms.technician_user_id
WHERE ms.maintenance_month = ? AND ms.maintenance_year = ?
";
$params = [$month, $year];
if ($cabangId) {
    $sql .= " AND a.id_cabang = ? ";
    $params[] = $cabangId;
}
$sql .= " ORDER BY c.`{$cName}`, k.`{$kName}`, a.kode_inventaris";

$st = db()->prepare($sql);
$st->execute($params);

$filename = sprintf('maintenance_%04d_%02d.csv', $year, $month);
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Tanggal','Jam','Kode Inventaris','Serial Number','Merk','Model','Pemilik','Cabang','Teknisi','Status'], ';');
while ($r = $st->fetch()) {
    fputcsv($out, [
        $r['maintenance_date'],
        substr($r['maintenance_time'],0,5),
        $r['kode_inventaris'],
        $r['serial_number'],
        $r['merk'],
        $r['model'],
        $r['karyawan_nama'],
        $r['cabang_nama'],
        $r['teknisi_nama'],
        $r['status'],
    ], ';');
}
fclose($out);
exit;
