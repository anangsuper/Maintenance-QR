<?php
require __DIR__ . '/bootstrap.php';
require_login();

$month = max(1, min(12, (int)($_GET['bulan'] ?? date('n'))));
$year = max(2020, min(2100, (int)($_GET['tahun'] ?? date('Y'))));
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));

$cName = name_column('cabang') ?: 'id';
$cabangs = db()->query("SELECT id, `{$cName}` AS nama FROM cabang ORDER BY `{$cName}`")->fetchAll();

$params = [];
$where = " WHERE (a.status = 'Aktif' OR a.status = 'aktif' OR a.status IS NULL OR a.status = '') ";
if ($cabangId) {
    $where .= " AND a.id_cabang = ? ";
    $params[] = $cabangId;
}

$totalSt = db()->prepare("SELECT COUNT(*) FROM assets a {$where}");
$totalSt->execute($params);
$total = (int)$totalSt->fetchColumn();

$scannedSql = "
SELECT COUNT(*)
FROM maintenance_scan ms
JOIN assets a ON a.id = ms.asset_id
{$where}
AND ms.maintenance_month = ?
AND ms.maintenance_year = ?
";
$st = db()->prepare($scannedSql);
$st->execute(array_merge($params, [$month, $year]));
$done = (int)$st->fetchColumn();

$findSql = "
SELECT COUNT(*)
FROM maintenance_scan ms
JOIN assets a ON a.id = ms.asset_id
{$where}
AND ms.maintenance_month = ?
AND ms.maintenance_year = ?
AND ms.status = 'Temuan'
";
$st = db()->prepare($findSql);
$st->execute(array_merge($params, [$month, $year]));
$findings = (int)$st->fetchColumn();

$pending = max(0, $total - $done);
$percent = $total > 0 ? round(($done / $total) * 100) : 0;

$assetBase = asset_query_base();
$pendingSql = $assetBase . "
{$where}
AND NOT EXISTS (
    SELECT 1 FROM maintenance_scan ms
    WHERE ms.asset_id = a.id
      AND ms.maintenance_month = ?
      AND ms.maintenance_year = ?
)
ORDER BY c.`{$cName}`, karyawan_nama, a.kode_inventaris
LIMIT 300
";
$st = db()->prepare($pendingSql);
$st->execute(array_merge($params, [$month, $year]));
$pendingRows = $st->fetchAll();

$recentSql = "
SELECT ms.*, a.kode_inventaris, a.merk, a.model,
       c.`{$cName}` AS cabang_nama,
       ".(name_column('karyawan') ? "k.`".name_column('karyawan')."`" : "k.id")." AS karyawan_nama
FROM maintenance_scan ms
JOIN assets a ON a.id = ms.asset_id
LEFT JOIN cabang c ON c.id = a.id_cabang
LEFT JOIN karyawan k ON k.id = a.id_karyawan
WHERE ms.maintenance_month = ? AND ms.maintenance_year = ?
".($cabangId ? " AND a.id_cabang = ? " : "")."
ORDER BY ms.maintenance_date DESC, ms.maintenance_time DESC
LIMIT 12
";
$recentParams = [$month, $year];
if ($cabangId) $recentParams[] = $cabangId;
$st = db()->prepare($recentSql);
$st->execute($recentParams);
$recentRows = $st->fetchAll();

$options = '';
foreach ($cabangs as $c) {
    $sel = ((int)$c['id'] === $cabangId) ? ' selected' : '';
    $options .= '<option value="'.(int)$c['id'].'"'.$sel.'>'.e($c['nama']).'</option>';
}

$pendingHtml = '';
foreach ($pendingRows as $r) {
    $pendingHtml .= '<tr>
      <td>'.e($r['kode_inventaris'] ?? '-').'</td>
      <td>'.e(asset_title($r)).'</td>
      <td>'.e($r['karyawan_nama'] ?? '-').'</td>
      <td>'.e($r['cabang_nama'] ?? '-').'</td>
      <td><span class="badge text-bg-secondary">Belum</span></td>
    </tr>';
}
if ($pendingHtml === '') $pendingHtml = '<tr><td colspan="5" class="text-center text-success py-4">Semua aset sudah maintenance untuk periode ini.</td></tr>';

$recentHtml = '';
foreach ($recentRows as $r) {
    $recentHtml .= '<tr>
      <td>'.e(format_id_date($r['maintenance_date'])).' '.e(substr($r['maintenance_time'],0,5)).'</td>
      <td>'.e($r['kode_inventaris'] ?? '-').'</td>
      <td>'.e(trim(($r['merk'] ?? '').' '.($r['model'] ?? ''))).'</td>
      <td>'.e($r['karyawan_nama'] ?? '-').'</td>
      <td>'.e($r['cabang_nama'] ?? '-').'</td>
      <td>'.($r['status'] === 'Temuan' ? '<span class="badge text-bg-danger">Temuan</span>' : '<span class="badge text-bg-success">Selesai</span>').'</td>
    </tr>';
}
if ($recentHtml === '') $recentHtml = '<tr><td colspan="6" class="text-center text-secondary py-4">Belum ada scan pada periode ini.</td></tr>';

$body = '
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
  <div>
    <h2 class="mb-1">Maintenance Bulanan</h2>
    <div class="text-secondary">Scan QR setelah maintenance selesai.</div>
  </div>
  <a class="btn btn-outline-success" href="'.e(module_url('export_csv.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'">Export CSV</a>
</div>

<div class="card p-3 mb-4">
<form class="row g-2 align-items-end" method="get">
  <div class="col-6 col-md-2">
    <label class="form-label">Bulan</label>
    <select class="form-select" name="bulan">';
for ($m=1;$m<=12;$m++) {
    $body .= '<option value="'.$m.'"'.($m===$month?' selected':'').'>'.date('F', mktime(0,0,0,$m,1)).'</option>';
}
$body .= '</select></div>
  <div class="col-6 col-md-2">
    <label class="form-label">Tahun</label>
    <input class="form-control" type="number" min="2020" max="2100" name="tahun" value="'.$year.'">
  </div>
  <div class="col-md-5">
    <label class="form-label">Cabang</label>
    <select class="form-select" name="cabang">
      <option value="0">Semua Cabang</option>'.$options.'
    </select>
  </div>
  <div class="col-md-3"><button class="btn btn-primary w-100">Tampilkan</button></div>
</form>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="card p-3"><div class="small-muted">Total Aset Aktif</div><div class="stat">'.$total.'</div></div></div>
  <div class="col-6 col-lg-3"><div class="card p-3"><div class="small-muted">Sudah Maintenance</div><div class="stat text-success">'.$done.'</div></div></div>
  <div class="col-6 col-lg-3"><div class="card p-3"><div class="small-muted">Belum Maintenance</div><div class="stat text-warning">'.$pending.'</div></div></div>
  <div class="col-6 col-lg-3"><div class="card p-3"><div class="small-muted">Ada Temuan</div><div class="stat text-danger">'.$findings.'</div></div></div>
</div>

<div class="card p-3 mb-4">
  <div class="d-flex justify-content-between"><strong>Progress</strong><strong>'.$percent.'%</strong></div>
  <div class="progress mt-2" style="height:18px"><div class="progress-bar" style="width:'.$percent.'%">'.$done.'/'.$total.'</div></div>
</div>

<div class="card p-3 mb-4">
  <h5>Belum Maintenance</h5>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Kode</th><th>Perangkat</th><th>Pemilik</th><th>Cabang</th><th>Status</th></tr></thead>
      <tbody>'.$pendingHtml.'</tbody>
    </table>
  </div>
</div>

<div class="card p-3">
  <h5>Scan Terbaru</h5>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Waktu</th><th>Kode</th><th>Perangkat</th><th>Pemilik</th><th>Cabang</th><th>Status</th></tr></thead>
      <tbody>'.$recentHtml.'</tbody>
    </table>
  </div>
</div>';

render_page('Dashboard QR Maintenance', $body);
