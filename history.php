<?php
require __DIR__ . '/bootstrap.php';
require_login();

$month = max(1, min(12, (int)($_GET['bulan'] ?? date('n'))));
$year = max(2020, min(2100, (int)($_GET['tahun'] ?? date('Y'))));
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));

$cName = name_column('cabang') ?: 'id';
$kName = name_column('karyawan') ?: 'id';
$uName = name_column('users') ?: 'id';

$cabangs = db()->query("SELECT id, `{$cName}` AS nama FROM cabang ORDER BY `{$cName}`")->fetchAll();

$sql = "
SELECT ms.*, a.kode_inventaris, a.merk, a.model,
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
$sql .= " ORDER BY ms.maintenance_date DESC, ms.maintenance_time DESC LIMIT 1000";

$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$opts = '';
foreach ($cabangs as $c) {
    $opts .= '<option value="'.(int)$c['id'].'"'.((int)$c['id']===$cabangId?' selected':'').'>'.e($c['nama']).'</option>';
}

$trs = '';
foreach ($rows as $r) {
    $trs .= '<tr>
      <td>'.e(format_id_date($r['maintenance_date'])).' '.e(substr($r['maintenance_time'],0,5)).'</td>
      <td>'.e($r['kode_inventaris'] ?? '-').'</td>
      <td>'.e(trim(($r['merk'] ?? '').' '.($r['model'] ?? ''))).'</td>
      <td>'.e($r['karyawan_nama'] ?? '-').'</td>
      <td>'.e($r['cabang_nama'] ?? '-').'</td>
      <td>'.e($r['teknisi_nama'] ?? '-').'</td>
      <td>'.($r['status']==='Temuan'?'<span class="badge text-bg-danger">Temuan</span>':'<span class="badge text-bg-success">Selesai</span>').'</td>
    </tr>';
}
if (!$trs) $trs = '<tr><td colspan="7" class="text-center py-4 text-secondary">Belum ada data.</td></tr>';

$body = '
<h2>Riwayat Maintenance</h2>
<div class="card p-3 mb-4">
<form method="get" class="row g-2 align-items-end">
  <div class="col-6 col-md-2"><label class="form-label">Bulan</label><select class="form-select" name="bulan">';
for($m=1;$m<=12;$m++) $body .= '<option value="'.$m.'"'.($m===$month?' selected':'').'>'.date('F',mktime(0,0,0,$m,1)).'</option>';
$body .= '</select></div>
  <div class="col-6 col-md-2"><label class="form-label">Tahun</label><input class="form-control" type="number" name="tahun" value="'.$year.'"></div>
  <div class="col-md-5"><label class="form-label">Cabang</label><select class="form-select" name="cabang"><option value="0">Semua Cabang</option>'.$opts.'</select></div>
  <div class="col-md-3"><button class="btn btn-primary w-100">Tampilkan</button></div>
</form>
</div>
<div class="card p-3">
<div class="table-responsive">
<table class="table align-middle mb-0">
<thead><tr><th>Waktu</th><th>Kode</th><th>Perangkat</th><th>Pemilik</th><th>Cabang</th><th>Teknisi</th><th>Status</th></tr></thead>
<tbody>'.$trs.'</tbody>
</table>
</div>
</div>';
render_page('Riwayat Maintenance', $body);
