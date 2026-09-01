<?php
require __DIR__ . '/bootstrap.php';
require_login();

$token = trim((string)($_GET['t'] ?? $_POST['t'] ?? ''));
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    http_response_code(400);
    render_page('QR Tidak Valid', '<div class="alert alert-danger"><strong>QR tidak valid.</strong><br>Token QR tidak dikenali.</div>');
    exit;
}

$sql = asset_query_base() . " WHERE q.token = ? AND q.is_active = 1 LIMIT 1";
$st = db()->prepare($sql);
$st->execute([$token]);
$asset = $st->fetch();

if (!$asset) {
    http_response_code(404);
    render_page('QR Tidak Ditemukan', '<div class="alert alert-danger"><strong>QR tidak ditemukan atau sudah dinonaktifkan.</strong></div>');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $csrf = csrf_token();
    $assetName = e(asset_title($asset));
    $body = '
    <div class="row justify-content-center">
      <div class="col-md-7 col-lg-6">
        <div class="card p-4 text-center">
          <div class="spinner-border text-primary mx-auto mb-3" role="status" aria-hidden="true"></div>
          <h4 class="mb-2">Mencatat maintenance...</h4>
          <div class="text-secondary mb-4">'.$assetName.'</div>
          <form id="autoScanForm" method="post" action="'.e(module_url('scan.php')).'">
            <input type="hidden" name="_csrf" value="'.e($csrf).'">
            <input type="hidden" name="t" value="'.e($token).'">
            <button class="btn btn-primary w-100" type="submit">Catat Maintenance</button>
          </form>
          <div class="small-muted mt-3">Halaman akan mencatat otomatis. Tombol di atas hanya cadangan jika JavaScript tidak aktif.</div>
        </div>
      </div>
    </div>';
    $script = '<script>
      setTimeout(function(){
        var f=document.getElementById("autoScanForm");
        if(f) f.submit();
      }, 120);
    </script>';
    render_page('Mencatat Maintenance', $body, '', $script);
    exit;
}

verify_csrf();

$uid = current_user_id();
$month = (int)date('n');
$year = (int)date('Y');
$date = date('Y-m-d');
$time = date('H:i:s');

$existing = db()->prepare("
    SELECT * FROM maintenance_scan
    WHERE asset_id = ? AND maintenance_month = ? AND maintenance_year = ?
    LIMIT 1
");
$existing->execute([(int)$asset['id'], $month, $year]);
$old = $existing->fetch();

if ($old) {
    $tech = technician_name((int)$old['technician_user_id']);
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
            <dt class="col-5">Tanggal</dt><dd class="col-7">'.e(format_id_date($old['maintenance_date'])).'</dd>
            <dt class="col-5">Jam</dt><dd class="col-7">'.e(substr($old['maintenance_time'],0,5)).' WITA</dd>
            <dt class="col-5">Teknisi</dt><dd class="col-7">'.e($tech).'</dd>
          </dl>
          <a class="btn btn-outline-primary mt-4" href="'.e(module_url('dashboard.php')).'">Kembali ke Dashboard</a>
        </div>
      </div>
    </div>';
    render_page('Sudah Maintenance', $body);
    exit;
}

try {
    $ins = db()->prepare("
        INSERT INTO maintenance_scan
        (asset_id, technician_user_id, maintenance_date, maintenance_time, maintenance_month, maintenance_year, status, source)
        VALUES (?, ?, ?, ?, ?, ?, 'Selesai', 'QR')
    ");
    $ins->execute([(int)$asset['id'], $uid, $date, $time, $month, $year]);
    $logId = (int)db()->lastInsertId();
} catch (PDOException $e) {
    // Perlindungan tambahan bila dua scan masuk hampir bersamaan.
    if (($e->errorInfo[1] ?? 0) == 1062) {
        header('Location: ' . module_url('scan.php', ['t' => $token]));
        exit;
    }
    throw $e;
}

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
        <dt class="col-5">Teknisi</dt><dd class="col-7">'.e(current_user_name()).'</dd>
      </dl>
      <div class="d-grid gap-2 mt-4">
        <a class="btn btn-danger" href="'.e(module_url('finding.php', ['log_id' => $logId])).'">Ada Temuan / Kerusakan</a>
        <a class="btn btn-outline-primary" href="'.e(module_url('dashboard.php')).'">Dashboard Maintenance</a>
      </div>
    </div>
  </div>
</div>';
render_page('Maintenance Tercatat', $body);
