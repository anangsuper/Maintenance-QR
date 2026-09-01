<?php
require __DIR__ . '/bootstrap.php';
require_login();

$token = trim((string)($_GET['t'] ?? $_POST['t'] ?? ''));
if ($token === '' || !preg_match('/^[a-zA-Z0-9\-_]{1,128}$/', $token)) {
    http_response_code(400);
    render_page('QR Tidak Valid', '<div class="alert alert-danger"><strong>QR tidak valid.</strong><br>Token QR tidak dikenali.</div>');
    exit;
}

$asset = get_asset_by_token($token);

if (!$asset) {
    http_response_code(404);
    render_page('QR Tidak Ditemukan', '<div class="alert alert-danger"><strong>QR tidak ditemukan atau sudah dinonaktifkan.</strong></div>');
    exit;
}

$uid = current_user_id();
$month = (int)date('n');
$year = (int)date('Y');
$date = date('Y-m-d');
$time = date('H:i:s');
$techName = current_user_name();

$res = record_scan((int)$asset['id'], $uid, $techName, $date, $time, $month, $year);

if (!empty($res['is_duplicate'])) {
    $old = $res['existing'] ?? [];
    $tech = $old['technician_name'] ?? technician_name((int)($old['technician_user_id'] ?? 0));
    $oldDate = $old['maintenance_date'] ?? date('Y-m-d');
    $oldTime = $old['maintenance_time'] ?? date('H:i:s');
    $body = '
    <div class="row justify-content-center">
      <div class="col-md-7 col-lg-6">
        <div class="card p-4">
          <div class="alert alert-warning mb-4">
            <h4 class="alert-heading">Sudah Maintenance</h4>
            QR ini sudah tercatat pada periode '.e(date('F Y')).'.
          </div>
          <dl class="row mb-0">
            <dt class="col-5">Perangkat</dt><dd class="col-7">'.e(asset_title($asset)).'</dd>
            <dt class="col-5">Kode Inventaris</dt><dd class="col-7">'.e($asset['kode_inventaris'] ?? '-').'</dd>
            <dt class="col-5">Pemilik</dt><dd class="col-7">'.e($asset['karyawan_nama'] ?? '-').'</dd>
            <dt class="col-5">Cabang</dt><dd class="col-7">'.e($asset['cabang_nama'] ?? '-').'</dd>
            <dt class="col-5">Tanggal</dt><dd class="col-7">'.e(format_id_date($oldDate)).'</dd>
            <dt class="col-5">Jam</dt><dd class="col-7">'.e(substr($oldTime,0,5)).' WITA</dd>
            <dt class="col-5">Teknisi</dt><dd class="col-7">'.e($tech).'</dd>
          </dl>
          <a class="btn btn-outline-primary mt-4" href="'.e(module_url('dashboard.php')).'">Kembali ke Dashboard</a>
        </div>
      </div>
    </div>';
    render_page('Sudah Maintenance', $body);
    exit;
}

$logId = (int)($res['log_id'] ?? 0);

$body = '
<div class="row justify-content-center">
  <div class="col-md-7 col-lg-6">
    <div class="card p-4">
      <div class="alert alert-success mb-4">
        <h4 class="alert-heading">✓ Maintenance Berhasil Dicatat</h4>
        Tidak perlu mengisi form tambahan.
      </div>
      <dl class="row mb-0">
        <dt class="col-5">Perangkat</dt><dd class="col-7">'.e(asset_title($asset)).'</dd>
        <dt class="col-5">Kode Inventaris</dt><dd class="col-7">'.e($asset['kode_inventaris'] ?? '-').'</dd>
        <dt class="col-5">Pemilik</dt><dd class="col-7">'.e($asset['karyawan_nama'] ?? '-').'</dd>
        <dt class="col-5">Divisi</dt><dd class="col-7">'.e($asset['divisi_nama'] ?? '-').'</dd>
        <dt class="col-5">Cabang</dt><dd class="col-7">'.e($asset['cabang_nama'] ?? '-').'</dd>
        <dt class="col-5">Tanggal</dt><dd class="col-7">'.e(date('d-m-Y')).'</dd>
        <dt class="col-5">Jam</dt><dd class="col-7">'.e(date('H:i')).' WITA</dd>
        <dt class="col-5">Teknisi</dt><dd class="col-7">'.e($techName).'</dd>
      </dl>
      <div class="d-grid gap-2 mt-4">
        <a class="btn btn-danger" href="'.e(module_url('finding.php', ['log_id' => $logId])).'">Ada Temuan / Kerusakan</a>
        <a class="btn btn-outline-primary" href="'.e(module_url('dashboard.php')).'">Dashboard Maintenance</a>
      </div>
    </div>
  </div>
</div>';
render_page('Maintenance Tercatat', $body);
