<?php
require __DIR__ . '/bootstrap.php';
// Buka akses scan agar teknisi bisa langsung scan QR dari HP tanpa terblokir login redirect

$token = trim((string)($_GET['t'] ?? $_POST['t'] ?? ''));
if ($token === '' || !preg_match('/^[a-zA-Z0-9\-_]{1,128}$/', $token)) {
    http_response_code(400);
    render_page('QR Tidak Valid', '<div class="alert alert-danger"><strong>QR tidak valid.</strong><br>Token QR tidak dikenali.</div>', '', '', false);
    exit;
}

$asset = get_asset_by_token($token);

if (!$asset) {
    http_response_code(404);
    render_page('QR Tidak Ditemukan', '<div class="alert alert-danger"><strong>QR tidak ditemukan atau sudah dinonaktifkan.</strong></div>', '', '', false);
    exit;
}

$uid = current_user_id();
$month = (int)date('n');
$year = (int)date('Y');
$date = date('Y-m-d');
$time = date('H:i:s');
$techName = current_user_name();
$force = (!empty($_GET['force']) || !empty($_POST['force']));

$res = record_scan((int)$asset['id'], $uid, $techName, $date, $time, $month, $year, $force);

// Jika Aset Sudah Pernah Dicatat Bulan Ini
if (!empty($res['is_duplicate'])) {
    $old = $res['existing'] ?? [];
    $tech = $old['technician_name'] ?? technician_name((int)($old['technician_user_id'] ?? 0));
    $oldDate = $old['maintenance_date'] ?? date('Y-m-d');
    $oldTime = $old['maintenance_time'] ?? date('H:i:s');
    $oldLogId = (int)($old['id'] ?? 0);
    $statusScan = $old['status'] ?? 'Selesai';

    $statusBadge = ($statusScan === 'Temuan') 
        ? '<span class="badge text-bg-danger ms-2">Ada Temuan</span>' 
        : '<span class="badge text-bg-success ms-2">Selesai</span>';

    $body = '
    <div class="row justify-content-center">
      <div class="col-md-8 col-lg-6">
        <div class="card p-4 border-0 shadow-sm">
          <div class="alert alert-warning mb-4">
            <h4 class="alert-heading fw-bold mb-1"><i class="bi bi-info-circle-fill me-2"></i>Sudah Maintenance Bulan Ini</h4>
            <div>Perangkat ini sudah tercatat maintenance untuk periode <strong>'.e(date('F Y')).'</strong>.'.$statusBadge.'</div>
          </div>

          <h6 class="text-secondary fw-semibold border-bottom pb-2 mb-3">Informasi Perangkat:</h6>
          <dl class="row mb-0">
            <dt class="col-5 text-secondary">Perangkat</dt><dd class="col-7 fw-bold">'.e(asset_title($asset)).'</dd>
            <dt class="col-5 text-secondary">Kode Inventaris</dt><dd class="col-7"><span class="badge text-bg-primary">'.e($asset['kode_inventaris'] ?? '-').'</span></dd>
            <dt class="col-5 text-secondary">Pengguna</dt><dd class="col-7">'.e($asset['karyawan_nama'] ?? '-').'</dd>
            <dt class="col-5 text-secondary">Divisi & Cabang</dt><dd class="col-7">'.e(($asset['divisi_nama'] ?? '-').' · '.($asset['cabang_nama'] ?? '-')).'</dd>
            <dt class="col-5 text-secondary">Tanggal Terakhir</dt><dd class="col-7">'.e(format_id_date($oldDate)).' pukul '.e(substr($oldTime,0,5)).' WITA</dd>
            <dt class="col-5 text-secondary">Teknisi</dt><dd class="col-7">'.e($tech).'</dd>
          </dl>

          <hr class="my-4">

          <div class="d-grid gap-2">
            <a class="btn btn-danger btn-lg fw-semibold" href="'.e(module_url('finding.php', ['log_id' => $oldLogId])).'">
              <i class="bi bi-exclamation-triangle-fill me-1"></i> Ada Temuan / Laporkan Kerusakan
            </a>
            <a class="btn btn-outline-warning text-dark fw-semibold" href="'.e(module_url('scan.php', ['t' => $token, 'force' => 1])).'" onclick="return confirm(\'Update waktu dan teknisi maintenance untuk bulan ini?\')">
              <i class="bi bi-arrow-repeat me-1"></i> Update Waktu Maintenance Ulang
            </a>
          </div>
        </div>
      </div>
    </div>';

    render_page('Sudah Maintenance', $body, '', '', false);
    exit;
}

// Maintenance Baru Berhasil Dicatat (atau baru saja Diperbarui)
$logId = (int)($res['log_id'] ?? 0);
$isUpdated = !empty($res['is_updated']);
$alertHeading = $isUpdated ? '✓ Waktu Maintenance Berhasil Diperbarui' : '✓ Maintenance Berhasil Dicatat';
$alertClass = $isUpdated ? 'alert-info' : 'alert-success';

$body = '
<div class="row justify-content-center">
  <div class="col-md-8 col-lg-6">
    <div class="card p-4 border-0 shadow-sm">
      <div class="alert '.$alertClass.' mb-4">
        <h4 class="alert-heading fw-bold mb-1"><i class="bi bi-check-circle-fill me-2"></i>'.$alertHeading.'</h4>
        <div>Maintenance periode <strong>'.e(date('F Y')).'</strong> telah otomatis tersimpan ke sistem.</div>
      </div>

      <h6 class="text-secondary fw-semibold border-bottom pb-2 mb-3">Detail Maintenance:</h6>
      <dl class="row mb-0">
        <dt class="col-5 text-secondary">Perangkat</dt><dd class="col-7 fw-bold">'.e(asset_title($asset)).'</dd>
        <dt class="col-5 text-secondary">Kode Inventaris</dt><dd class="col-7"><span class="badge text-bg-primary">'.e($asset['kode_inventaris'] ?? '-').'</span></dd>
        <dt class="col-5 text-secondary">Pengguna</dt><dd class="col-7">'.e($asset['karyawan_nama'] ?? '-').'</dd>
        <dt class="col-5 text-secondary">Divisi & Cabang</dt><dd class="col-7">'.e(($asset['divisi_nama'] ?? '-').' · '.($asset['cabang_nama'] ?? '-')).'</dd>
        <dt class="col-5 text-secondary">Waktu Scan</dt><dd class="col-7">'.e(date('d-m-Y')).' pukul '.e(date('H:i')).' WITA</dd>
        <dt class="col-5 text-secondary">Teknisi</dt><dd class="col-7">'.e($techName).'</dd>
      </dl>

      <hr class="my-4">

      <div class="d-grid gap-2">
        <a class="btn btn-danger btn-lg fw-semibold" href="'.e(module_url('finding.php', ['log_id' => $logId])).'">
          <i class="bi bi-exclamation-triangle-fill me-1"></i> Ada Temuan / Kerusakan
        </a>
      </div>
    </div>
  </div>
</div>';

render_page('Maintenance Tercatat', $body, '', '', false);
