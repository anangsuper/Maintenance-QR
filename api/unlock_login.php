<?php
/**
 * Administrator Unlock Tool - Membuka Kunci IP / Akun yang Terblokir
 * Akses Terbatas: Hanya dapat diakses oleh Administrator Terotentikasi
 */
require __DIR__ . '/bootstrap.php';
require_admin();

$clientIp = get_client_ip();
$status = check_login_throttle('', $clientIp);
$isLocked = $status['locked'];
$remainingSecs = $status['remaining_seconds'] ?? 0;

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_unlock'])) {
    verify_csrf();
    clear_all_login_lockouts();
    
    $adminName = current_user_name();
    $adminId = current_user_id();
    if (function_exists('record_audit_log')) {
        record_audit_log(
            'LOCKOUT_RESET',
            'KEAMANAN',
            $adminId,
            $adminName,
            "Administrator {$adminName} (#{$adminId}) membuka kunci seluruh IP & akun yang terblokir."
        );
    }
    $_SESSION['flash'] = 'Kunci akses login (IP & Akun) berhasil dibuka oleh Administrator.';
    header('Location: ' . module_url('users_admin.php'));
    exit;
}

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Buka Kunci Akses Login · IT Administrator</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body {
    background-color: #071529;
    color: #f8fafc;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  }
  .card-unlock {
    background: #0c203c;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
    max-width: 520px;
    width: 100%;
    padding: 32px;
  }
</style>
</head>
<body>

<div class="card-unlock text-center">
  <div class="mb-3">
    <div class="d-inline-flex p-3 rounded-circle <?= $isLocked ? 'bg-danger bg-opacity-25 text-danger' : 'bg-primary bg-opacity-25 text-primary' ?>">
      <i class="bi <?= $isLocked ? 'bi-shield-lock-fill' : 'bi-shield-check' ?>" style="font-size: 2.5rem;"></i>
    </div>
  </div>

  <h4 class="fw-bold mb-1">Manajemen Kunci Akses Login</h4>
  <p class="text-secondary small mb-4">Panel Pembukaan Kunci Khusus IT Administrator</p>

  <div class="alert alert-info py-2 px-3 text-start small border-0 bg-info bg-opacity-10 text-info mb-3">
    <i class="bi bi-person-badge-fill me-1"></i> Terotentikasi sebagai: <strong><?= e(current_user_name()) ?></strong> (Administrator)
  </div>

  <div class="card bg-dark border-secondary p-3 text-start mb-4">
    <div class="d-flex justify-content-between mb-2">
      <span class="text-secondary small">Alamat IP Anda:</span>
      <span class="badge bg-secondary font-monospace"><?= e($clientIp) ?></span>
    </div>
    <div class="d-flex justify-content-between mb-2">
      <span class="text-secondary small">Status Kunci IP Ini:</span>
      <?php if ($isLocked): ?>
        <span class="badge bg-danger">Terkunci (<?= (int)ceil($remainingSecs / 60) ?> Menit Tersisa)</span>
      <?php else: ?>
        <span class="badge bg-success">Normal / Tidak Terkunci</span>
      <?php endif; ?>
    </div>
    <div class="d-flex justify-content-between">
      <span class="text-secondary small">Percobaan Gagal Terdeteksi:</span>
      <span class="text-light small"><?= (int)$status['attempts'] ?> kali</span>
    </div>
  </div>

  <form method="POST">
    <?= csrf_field() ?>
    <button type="submit" name="action_unlock" value="1" class="btn btn-warning w-100 py-2 fw-bold mb-3 text-dark">
      <i class="bi bi-key-fill me-2"></i>Buka Kunci Seluruh Akun &amp; IP Sekarang
    </button>
  </form>

  <div class="d-flex justify-content-between align-items-center pt-2 border-top border-secondary border-opacity-25">
    <a href="<?= module_url('users_admin.php') ?>" class="text-secondary small text-decoration-none">
      <i class="bi bi-people me-1"></i>Kelola Pengguna
    </a>
    <a href="<?= module_url('dashboard.php') ?>" class="text-secondary small text-decoration-none">
      <i class="bi bi-speedometer2 me-1"></i>Dashboard Utama
    </a>
  </div>
</div>

</body>
</html>
