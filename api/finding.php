<?php
require __DIR__ . '/bootstrap.php';
// Buka akses finding agar teknisi yang scan dari HP bisa langsung lapor temuan

$logId = max(0, (int)($_GET['log_id'] ?? $_POST['log_id'] ?? 0));
if (!$logId) {
    http_response_code(400);
    render_page('Data Tidak Valid', '<div class="alert alert-danger">Log maintenance tidak valid.</div>');
    exit;
}

$log = get_log_by_id($logId);

if (!$log) {
    http_response_code(404);
    render_page('Tidak Ditemukan', '<div class="alert alert-danger">Log maintenance tidak ditemukan.</div>');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $finding = trim((string)($_POST['finding'] ?? ''));
    $action = trim((string)($_POST['action_taken'] ?? ''));
    $severity = $_POST['severity'] ?? 'Ringan';
    if (!in_array($severity, ['Ringan','Sedang','Berat'], true)) $severity = 'Ringan';

    if ($finding === '') {
        $error = 'Temuan wajib diisi.';
    } else {
        record_finding_issue($logId, (int)$log['asset_id'], $finding, $action, $severity, current_user_name());
        $body = '
        <div class="row justify-content-center"><div class="col-md-7">
          <div class="card p-4 border-0 shadow-sm">
            <div class="alert alert-success"><strong>✓ Temuan kerusakan berhasil dicatat.</strong></div>
            <div><strong>'.e($log['kode_inventaris'] ?? '-').'</strong> · '.e(trim(($log['merk'] ?? '').' '.($log['model'] ?? ''))).'</div>
            <div class="mt-3 bg-light p-3 rounded">'.nl2br(e($finding)).'</div>
          </div>
        </div></div>';
        render_page('Temuan Tercatat', $body, '', '', false);
        exit;
    }
}

$errorHtml = !empty($error) ? '<div class="alert alert-danger">'.e($error).'</div>' : '';

$body = '
<div class="row justify-content-center">
  <div class="col-md-7">
    '.$errorHtml.'
    <div class="card p-4 border-0 shadow-sm">
      <h4 class="fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Catat Temuan / Kerusakan</h4>
      <div class="text-secondary mb-4">'.e($log['kode_inventaris'] ?? '-').' · '.e(trim(($log['merk'] ?? '').' '.($log['model'] ?? ''))).'</div>
      <form method="post">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="log_id" value="'.$logId.'">
        <div class="mb-3">
          <label class="form-label fw-semibold">Deskripsi Temuan / Kerusakan <span class="text-danger">*</span></label>
          <textarea class="form-control" name="finding" rows="4" required placeholder="Contoh: printer bergaris, RAM kendor, kabel LAN putus, bluescreen..."></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Tindakan Awal yang Dilakukan</label>
          <textarea class="form-control" name="action_taken" rows="3" placeholder="Opsional (misal: sudah direstart, kabel sudah diganti)"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Tingkat Kerusakan</label>
          <select class="form-select" name="severity">
            <option>Ringan</option>
            <option>Sedang</option>
            <option>Berat</option>
          </select>
        </div>
        <div class="d-grid gap-2">
          <button class="btn btn-danger btn-lg fw-semibold"><i class="bi bi-save me-1"></i> Simpan Temuan</button>
        </div>
      </form>
    </div>
  </div>
</div>';
render_page('Catat Temuan', $body, '', '', false);
