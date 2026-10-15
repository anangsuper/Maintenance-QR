<?php
/**
 * Administrator Unlock Tool - Membuka Kunci Akun / IP yang Terblokir
 * 
 * Dapat diakses melalui:
 * 1. Terminal / CLI: php api/unlock_login.php
 * 2. Web Browser: http://domain/api/unlock_login.php
 */
require __DIR__ . '/bootstrap.php';

// 1. Eksekusi Cepat via Command Line / Terminal Server
if (php_sapi_name() === 'cli' || defined('STDIN')) {
    clear_all_login_lockouts();
    if (function_exists('record_audit_log')) {
        record_audit_log('LOCKOUT_RESET', 'KEAMANAN', 1, 'Terminal Admin', 'Administrator membuka kunci seluruh akun & IP via terminal CLI.');
    }
    echo "\n=======================================================\n";
    echo " [BERHASIL] Seluruh kunci akses login akun/IP dibuka!\n";
    echo " Seluruh pengguna kini dapat masuk kembali secara normal.\n";
    echo "=======================================================\n\n";
    exit(0);
}

$isAdminSession = is_admin();
$clientIp = get_client_ip();
$errorMsg = '';
$successMsg = '';

// Hitung jumlah akun/kunci yang sedang terblokir
$activeLockouts = [];
if (!is_google_cloud_mode()) {
    try {
        $st = db()->query("SELECT identifier, type, attempts, locked_until FROM login_lockouts WHERE locked_until > " . time());
        $activeLockouts = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

// 2. Proses Pembukaan Kunci
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    $canUnlock = false;
    $operatorName = 'Administrator';
    $operatorId = 1;

    if ($isAdminSession) {
        $canUnlock = true;
        $operatorName = current_user_name();
        $operatorId = current_user_id();
    } else {
        // Jika belum login admin, verifikasi kredensial admin secara langsung
        $adminUser = trim((string)($_POST['admin_username'] ?? ''));
        $adminPass = trim((string)($_POST['admin_password'] ?? ''));

        if ($adminUser === '' || $adminPass === '') {
            $errorMsg = 'Username dan kata sandi Administrator wajib diisi.';
        } else {
            // Verifikasi terhadap database / auth system
            $auth = authenticate_user($adminUser, $adminPass);
            if (!empty($auth['success']) && is_admin()) {
                $canUnlock = true;
                $operatorName = current_user_name();
                $operatorId = current_user_id();
            } else {
                $errorMsg = 'Kredensial Administrator salah atau akun bukan role Administrator.';
            }
        }
    }

    if ($canUnlock) {
        clear_all_login_lockouts();
        if (function_exists('record_audit_log')) {
            record_audit_log(
                'LOCKOUT_RESET',
                'KEAMANAN',
                $operatorId,
                $operatorName,
                "Administrator {$operatorName} (#{$operatorId}) membuka seluruh kunci akses login akun & IP yang terblokir."
            );
        }

        if ($isAdminSession) {
            $_SESSION['flash'] = 'Seluruh kunci akses akun & IP berhasil dibuka.';
            header('Location: ' . module_url('users_admin.php'));
            exit;
        } else {
            $_SESSION['flash_login'] = 'Seluruh kunci akun berhasil dibuka! Silakan masuk kembali dengan akun Anda.';
            header('Location: ' . module_url('login.php'));
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pemulihan Akses Login · IT Administrator</title>
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
    padding: 20px;
  }
  .card-unlock {
    background: #0c203c;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.6);
    max-width: 520px;
    width: 100%;
    padding: 32px;
  }
  .form-control {
    background-color: #071224;
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #f8fafc;
  }
  .form-control:focus {
    background-color: #0a1830;
    border-color: #3b82f6;
    color: #fff;
    box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.25);
  }
</style>
</head>
<body>

<div class="card-unlock text-center">
  <div class="mb-3">
    <div class="d-inline-flex p-3 rounded-circle <?= count($activeLockouts) > 0 ? 'bg-danger bg-opacity-25 text-danger' : 'bg-primary bg-opacity-25 text-primary' ?>">
      <i class="bi <?= count($activeLockouts) > 0 ? 'bi-shield-lock-fill' : 'bi-shield-check' ?>" style="font-size: 2.5rem;"></i>
    </div>
  </div>

  <h4 class="fw-bold mb-1">Pemulihan Akses Login</h4>
  <p class="text-secondary small mb-4">Membuka Kunci Akun / Pengguna yang Terblokir Akibat Salah Password</p>

  <?php if ($errorMsg): ?>
    <div class="alert alert-danger py-2 px-3 text-start small border-0 bg-danger bg-opacity-25 text-light mb-3">
      <i class="bi bi-exclamation-triangle-fill me-1 text-danger"></i> <?= e($errorMsg) ?>
    </div>
  <?php endif; ?>

  <?php if ($isAdminSession): ?>
    <div class="alert alert-success py-2 px-3 text-start small border-0 bg-success bg-opacity-15 text-light mb-3">
      <i class="bi bi-person-check-fill me-1 text-success"></i> Terotentikasi sebagai Administrator: <strong><?= e(current_user_name()) ?></strong>
    </div>
  <?php endif; ?>

  <div class="card bg-dark border-secondary border-opacity-50 p-3 text-start mb-4">
    <div class="d-flex justify-content-between mb-2">
      <span class="text-secondary small">Alamat IP Anda:</span>
      <span class="badge bg-secondary font-monospace"><?= e($clientIp) ?></span>
    </div>
    <div class="d-flex justify-content-between mb-2">
      <span class="text-secondary small">Akun Terkunci Saat Ini:</span>
      <?php if (count($activeLockouts) > 0): ?>
        <span class="badge bg-danger"><?= count($activeLockouts) ?> Akun Terblokir</span>
      <?php else: ?>
        <span class="badge bg-success">Tidak Ada Akun Terkunci</span>
      <?php endif; ?>
    </div>
    <?php if (!empty($activeLockouts)): ?>
      <div class="mt-2 pt-2 border-top border-secondary border-opacity-25 small text-secondary">
        Daftar: 
        <?php foreach ($activeLockouts as $l): ?>
          <span class="badge bg-danger bg-opacity-50 me-1"><?= e($l['identifier']) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <form method="POST">
    <?= csrf_field() ?>

    <?php if (!$isAdminSession): ?>
      <div class="text-start mb-3">
        <label class="form-label small text-secondary mb-1">Username Administrator</label>
        <div class="input-group">
          <span class="input-group-text bg-dark border-secondary border-opacity-50 text-secondary"><i class="bi bi-person"></i></span>
          <input type="text" name="admin_username" class="form-control form-control-sm" placeholder="Contoh: admin" required autofocus>
        </div>
      </div>
      <div class="text-start mb-3">
        <label class="form-label small text-secondary mb-1">Kata Sandi Administrator</label>
        <div class="input-group">
          <span class="input-group-text bg-dark border-secondary border-opacity-50 text-secondary"><i class="bi bi-key"></i></span>
          <input type="password" name="admin_password" class="form-control form-control-sm" placeholder="Kata sandi admin..." required>
        </div>
      </div>
    <?php endif; ?>

    <button type="submit" name="action_unlock" value="1" class="btn btn-warning w-100 py-2 fw-bold mb-3 text-dark">
      <i class="bi bi-unlock-fill me-2"></i>Buka Kunci Seluruh Akun Sekarang
    </button>
  </form>

  <div class="d-flex justify-content-between align-items-center pt-2 border-top border-secondary border-opacity-25">
    <a href="<?= module_url('login.php') ?>" class="text-secondary small text-decoration-none">
      <i class="bi bi-arrow-left me-1"></i>Halaman Login
    </a>
    <?php if ($isAdminSession): ?>
      <a href="<?= module_url('users_admin.php') ?>" class="text-secondary small text-decoration-none">
        <i class="bi bi-people me-1"></i>Kelola Pengguna
      </a>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
