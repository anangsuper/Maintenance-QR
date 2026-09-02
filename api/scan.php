<?php
require __DIR__ . '/bootstrap.php';
// Buka akses scan agar teknisi bisa langsung scan QR dari HP tanpa terblokir login redirect

$token = trim((string)($_GET['t'] ?? $_POST['t'] ?? ''));
if ($token === '' || !preg_match('/^[a-zA-Z0-9\-_]{1,128}$/', $token)) {
    http_response_code(400);
    render_page('QR Tidak Valid', '<div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-octagon-fill me-2"></i><strong>QR tidak valid.</strong> Token QR tidak dikenali.</div>', '', '', false);
    exit;
}

$asset = get_asset_by_token($token);

if (!$asset) {
    http_response_code(404);
    render_page('QR Tidak Ditemukan', '<div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>QR tidak ditemukan atau sudah dinonaktifkan.</strong></div>', '', '', false);
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

    $statusChip = ($statusScan === 'Temuan') 
        ? '<span class="badge-chip chip-danger"><i class="bi bi-exclamation-triangle-fill"></i> Ada Temuan</span>' 
        : '<span class="badge-chip chip-success"><i class="bi bi-check-circle-fill"></i> Selesai</span>';

    $body = '
    <div class="row justify-content-center">
      <div class="col-md-8 col-lg-5">
        <div class="card p-4 border-0 shadow">
          <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning rounded-circle mb-3" style="width: 64px; height: 64px; font-size: 2rem;">
              <i class="bi bi-check2-all"></i>
            </div>
            <h4 class="fw-bold text-dark mb-1">Sudah Maintenance</h4>
            <div class="text-secondary small">Perangkat ini sudah dicatat untuk periode <strong>'.e(date('F Y')).'</strong></div>
            <div class="mt-2">'.$statusChip.'</div>
          </div>

          <div class="p-3 bg-light rounded-3 mb-4">
            <h6 class="text-primary fw-bold mb-3 border-bottom pb-2"><i class="bi bi-laptop me-2"></i>Informasi Perangkat:</h6>
            <div class="row g-2 small">
              <div class="col-5 text-secondary">Perangkat:</div>
              <div class="col-7 fw-bold text-dark">'.e(asset_title($asset)).'</div>

              <div class="col-5 text-secondary">Kode Inventaris:</div>
              <div class="col-7"><span class="badge-chip chip-primary">'.e($asset['kode_inventaris'] ?? '-').'</span></div>

              <div class="col-5 text-secondary">Pengguna:</div>
              <div class="col-7 fw-semibold text-dark">'.e($asset['karyawan_nama'] ?? '-').'</div>

              <div class="col-5 text-secondary">Lokasi Cabang:</div>
              <div class="col-7"><span class="badge-chip chip-secondary">'.e(($asset['cabang_nama'] ?? '-')).'</span></div>

              <div class="col-5 text-secondary">Waktu Scan:</div>
              <div class="col-7 text-dark">'.e(format_id_date($oldDate)).' pukul '.e(substr($oldTime,0,5)).'</div>

              <div class="col-5 text-secondary">Teknisi:</div>
              <div class="col-7 fw-bold text-dark">'.e($tech).'</div>
            </div>
          </div>

          <div class="d-grid gap-2">
            <a class="btn btn-danger btn-lg fw-bold py-3 shadow-sm" href="'.e(module_url('finding.php', ['log_id' => $oldLogId])).'">
              <i class="bi bi-exclamation-triangle-fill me-2"></i> Ada Temuan / Kerusakan
            </a>
            <a class="btn btn-outline-secondary fw-semibold py-2" href="'.e(module_url('scan.php', ['t' => $token, 'force' => 1])).'" onclick="return confirm(\'Update waktu dan teknisi maintenance untuk bulan ini?\')">
              <i class="bi bi-arrow-repeat me-1"></i> Scan / Maintenance Ulang
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
$alertHeading = $isUpdated ? 'Waktu Maintenance Diperbarui' : 'Maintenance Berhasil Dicatat!';

$body = '
<div class="row justify-content-center">
  <div class="col-md-8 col-lg-5">
    <div class="card p-4 border-0 shadow">
      <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle mb-3" style="width: 70px; height: 70px; font-size: 2.2rem;">
          <i class="bi bi-check-lg"></i>
        </div>
        <h4 class="fw-bold text-success mb-1">'.$alertHeading.'</h4>
        <div class="text-secondary small">Data maintenance periode <strong>'.e(date('F Y')).'</strong> otomatis tersimpan.</div>
      </div>

      <div class="p-3 bg-light rounded-3 mb-4">
        <h6 class="text-primary fw-bold mb-3 border-bottom pb-2"><i class="bi bi-pc-display me-2"></i>Detail Perangkat:</h6>
        <div class="row g-2 small">
          <div class="col-5 text-secondary">Perangkat:</div>
          <div class="col-7 fw-bold text-dark">'.e(asset_title($asset)).'</div>

          <div class="col-5 text-secondary">Kode Inventaris:</div>
          <div class="col-7"><span class="badge-chip chip-primary">'.e($asset['kode_inventaris'] ?? '-').'</span></div>

          <div class="col-5 text-secondary">Pengguna:</div>
          <div class="col-7 fw-semibold text-dark">'.e($asset['karyawan_nama'] ?? '-').'</div>

          <div class="col-5 text-secondary">Lokasi Cabang:</div>
          <div class="col-7"><span class="badge-chip chip-secondary">'.e(($asset['cabang_nama'] ?? '-')).'</span></div>

          <div class="col-5 text-secondary">Waktu Scan:</div>
          <div class="col-7 text-dark">'.e(date('d-m-Y')).' pukul '.e(date('H:i')).'</div>

          <div class="col-5 text-secondary">Teknisi:</div>
          <div class="col-7 fw-bold text-dark">'.e($techName).'</div>
        </div>
      </div>

      <div class="d-grid gap-2">
        <a class="btn btn-danger btn-lg fw-bold py-3 shadow-sm" href="'.e(module_url('finding.php', ['log_id' => $logId])).'">
          <i class="bi bi-exclamation-triangle-fill me-2"></i> Ada Temuan / Kerusakan
        </a>
      </div>
    </div>
  </div>
</div>';

render_page('Maintenance Tercatat', $body, '', '', false);
