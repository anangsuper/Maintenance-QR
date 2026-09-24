<?php
/**
 * Emergency Unlock Tool - Membuka Kunci IP / Akun yang Terblokir Akibat Salah Password
 * Akses: https://domain-anda/api/unlock_login.php
 */
require __DIR__ . '/bootstrap.php';

$clientIp = get_client_ip();
$status = check_login_throttle('', $clientIp);
$isLocked = $status['locked'];
$remainingSecs = $status['remaining_seconds'] ?? 0;

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_unlock'])) {
    clear_all_login_lockouts();
    if (function_exists('record_audit_log')) {
        record_audit_log('LOCKOUT_RESET', 'KEAMANAN', null, 'system', 'Kunci akses IP ' . $clientIp . ' dan seluruh akun dibuka secara manual.');
    }
    $_SESSION['flash_login'] = 'Kunci akses berhasil dibuka! Silakan login kembali dengan username dan password yang benar.';
    header('Location: ' . module_url('login.php'));
    exit;
}

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Buka Kunci Akses Login · IT Operations</title>
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
    max-width: 480px;
    width: 100%;
    padding: 32px;
  }
</style>
</head>
<body>

<div class="card-unlock text-center">
  <div class="mb-3">
    <div class="d-inline-flex p-3 rounded-circle <?= $isLocked ? 'bg-danger bg-opacity-25 text-danger' : 'bg-success bg-opacity-25 text-success' ?>">
      <i class="bi <?= $isLocked ? 'bi-lock-fill' : 'bi-unlock-fill' ?>" style="font-size: 2.5rem;"></i>
    </div>
  </div>

  <h4 class="fw-bold mb-1">Buka Kunci Akses Login</h4>
  <p class="text-secondary small mb-4">Pemulihan Akses Cepat (Emergency Unlock Tool)</p>

  <div class="card bg-dark border-secondary p-3 text-start mb-4">
    <div class="d-flex justify-content-between mb-2">
      <span class="text-secondary small">Alamat IP Anda:</span>
      <span class="badge bg-secondary font-monospace"><?= e($clientIp) ?></span>
    </div>
    <div class="d-flex justify-content-between mb-2">
      <span class="text-secondary small">Status Kunci:</span>
      <?php if ($isLocked): ?>
        <span class="badge bg-danger">Terkunci (<?= (int)ceil($remainingSecs / 60) ?> Menit Tersisa)</span>
      <?php else: ?>
        <span class="badge bg-success">Normal / Tidak Terkunci</span>
      <?php endif; ?>
    </div>
    <div class="d-flex justify-content-between">
      <span class="text-secondary small">Percobaan Gagal:</span>
      <span class="text-light small"><?= (int)$status['attempts'] ?> dari 5 kali</span>
    </div>
  </div>

  <form method="POST">
    <button type="submit" name="action_unlock" value="1" class="btn btn-primary w-100 py-2 fw-bold mb-3">
      <i class="bi bi-key-fill me-2"></i>Buka Kunci Akses IP &amp; Akun Sekarang
    </button>
  </form>

  <a href="<?= module_url('login.php') ?>" class="text-secondary small text-decoration-none">
    <i class="bi bi-arrow-left me-1"></i>Kembali ke Halaman Login
  </a>
</div>

</body>
</html>
