<?php
require __DIR__ . '/bootstrap.php';
require_login();

$logId = max(0, (int)($_GET['log_id'] ?? $_POST['log_id'] ?? 0));
if (!$logId) {
    http_response_code(400);
    render_page('Data Tidak Valid', '<div class="alert alert-danger">Log maintenance tidak valid.</div>');
    exit;
}

$st = db()->prepare("
    SELECT ms.*, a.kode_inventaris, a.merk, a.model
    FROM maintenance_scan ms
    JOIN assets a ON a.id = ms.asset_id
    WHERE ms.id = ?
    LIMIT 1
");
$st->execute([$logId]);
$log = $st->fetch();

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
        db()->beginTransaction();
        try {
            $ins = db()->prepare("
                INSERT INTO maintenance_findings
                (maintenance_scan_id, asset_id, finding, action_taken, severity, repair_status, created_by)
                VALUES (?, ?, ?, ?, ?, 'Perlu Tindak Lanjut', ?)
            ");
            $ins->execute([$logId, (int)$log['asset_id'], $finding, $action ?: null, $severity, current_user_id()]);

            $up = db()->prepare("UPDATE maintenance_scan SET status = 'Temuan' WHERE id = ?");
            $up->execute([$logId]);

            db()->commit();
            $body = '
            <div class="row justify-content-center"><div class="col-md-7">
              <div class="card p-4">
                <div class="alert alert-success"><strong>Temuan berhasil dicatat.</strong></div>
                <div><strong>'.e($log['kode_inventaris'] ?? '-').'</strong> · '.e(trim(($log['merk'] ?? '').' '.($log['model'] ?? ''))).'</div>
                <div class="mt-3">'.nl2br(e($finding)).'</div>
                <a class="btn btn-primary mt-4" href="'.e(module_url('dashboard.php')).'">Kembali ke Dashboard</a>
              </div>
            </div></div>';
            render_page('Temuan Tercatat', $body);
            exit;
        } catch (Throwable $e) {
            db()->rollBack();
            throw $e;
        }
    }
}

$errorHtml = !empty($error) ? '<div class="alert alert-danger">'.e($error).'</div>' : '';

$body = '
<div class="row justify-content-center">
  <div class="col-md-7">
    '.$errorHtml.'
    <div class="card p-4">
      <h3>Ada Temuan / Kerusakan</h3>
      <div class="text-secondary mb-4">'.e($log['kode_inventaris'] ?? '-').' · '.e(trim(($log['merk'] ?? '').' '.($log['model'] ?? ''))).'</div>
      <form method="post">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="log_id" value="'.$logId.'">
        <div class="mb-3">
          <label class="form-label">Temuan</label>
          <textarea class="form-control" name="finding" rows="4" required placeholder="Contoh: printer bergaris, RAM kendor, LAN putus..."></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Tindakan yang sudah dilakukan</label>
          <textarea class="form-control" name="action_taken" rows="3" placeholder="Opsional"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Tingkat</label>
          <select class="form-select" name="severity">
            <option>Ringan</option>
            <option>Sedang</option>
            <option>Berat</option>
          </select>
        </div>
        <button class="btn btn-danger">Simpan Temuan</button>
        <a class="btn btn-outline-secondary" href="'.e(module_url('dashboard.php')).'">Batal</a>
      </form>
    </div>
  </div>
</div>';
render_page('Catat Temuan', $body);
