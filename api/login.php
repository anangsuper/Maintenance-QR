<?php
require __DIR__ . '/bootstrap.php';

// Simpan redirect URL jika dikirim via GET
if (!empty($_GET['redirect'])) {
    $_SESSION['after_login'] = (string)$_GET['redirect'];
}

// Jika sudah login, langsung redirect ke halaman tujuan atau dashboard
if (is_logged_in()) {
    $redirect = $_SESSION['after_login'] ?? module_url('dashboard.php');
    unset($_SESSION['after_login']);
    header('Location: ' . $redirect);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = trim((string)($_POST['password'] ?? ''));

    $result = authenticate_user($username, $password);

    if (!empty($result['success'])) {
        // Redirect ke halaman sebelumnya atau dashboard
        $redirect = $_SESSION['after_login'] ?? module_url('dashboard.php');
        unset($_SESSION['after_login']);
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = $result['error'] ?? 'Username atau password salah.';
    }
}

$errorHtml = $error ? '<div class="alert alert-danger border-0 shadow-sm py-2 px-3 d-flex align-items-center gap-2 mb-3"><i class="bi bi-shield-exclamation fs-5"></i><span>'.e($error).'</span></div>' : '';

$flashLogin = $_SESSION['flash_login'] ?? '';
unset($_SESSION['flash_login']);
$successHtml = $flashLogin ? '<div class="alert alert-success border-0 shadow-sm py-2 px-3 d-flex align-items-center gap-2 mb-3"><i class="bi bi-check-circle-fill fs-5"></i><span>'.e($flashLogin).'</span></div>' : '';

$expiredHtml = (!empty($_GET['expired']) && !$error && !$flashLogin)
    ? '<div class="alert alert-warning border-0 shadow-sm py-2 px-3 d-flex align-items-center gap-2 mb-3"><i class="bi bi-clock-history fs-5 text-warning-emphasis"></i><span class="small">Sesi Anda telah berakhir. Silakan masuk kembali untuk melanjutkan.</span></div>'
    : '';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login · QR Maintenance System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
* { box-sizing: border-box; }

body {
  margin: 0;
  min-height: 100vh;
  font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  background-color: #0b1528;
  background-image: url('bg_login.php');
  background-repeat: no-repeat;
  background-position: center center;
  background-attachment: fixed;
  background-size: cover;
  display: flex;
  align-items: center;
  justify-content: center;
  -webkit-font-smoothing: antialiased;
  position: relative;
}

/* Overlay lembut agar teks tetap kontras tinggi */
body::before {
  content: "";
  position: fixed;
  inset: 0;
  background: radial-gradient(circle at 75% 50%, rgba(11, 21, 40, 0.15) 0%, rgba(11, 21, 40, 0.4) 100%);
  pointer-events: none;
  z-index: 0;
}

.login-wrapper {
  position: relative;
  z-index: 1;
  width: 100%;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px 20px;
}

@media (min-width: 992px) {
  .login-wrapper {
    justify-content: flex-end;
    padding-right: clamp(40px, 8vw, 130px);
  }
}

.login-container {
  width: 100%;
  max-width: 440px;
}

.login-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 36px 32px 28px;
  box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.2), 0 8px 10px -6px rgba(15, 23, 42, 0.2);
}

.login-logo {
  width: 54px;
  height: 54px;
  background: #1D4ED8;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 1.6rem;
  margin: 0 auto 16px;
}

.login-title {
  font-size: 1.35rem;
  font-weight: 700;
  color: #0f172a;
  text-align: center;
  margin-bottom: 4px;
  letter-spacing: -0.3px;
}

.login-subtitle {
  font-size: 0.88rem;
  color: #64748b;
  text-align: center;
  margin-bottom: 24px;
}

.form-floating > .form-control {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 16px 14px 8px 44px;
  font-size: 0.95rem;
  height: 52px;
  background: #f8fafc;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.form-floating > .form-control:focus {
  border-color: #1D4ED8;
  box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.15);
  background: #fff;
}

.form-floating > label {
  padding-left: 44px;
  color: #64748b;
  font-weight: 500;
}

.input-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: #64748b;
  font-size: 1.1rem;
  z-index: 5;
  pointer-events: none;
}

.field-wrapper {
  position: relative;
  margin-bottom: 16px;
}

.btn-login {
  width: 100%;
  padding: 12px;
  background: #1D4ED8;
  border: 1px solid #1D4ED8;
  border-radius: 8px;
  color: #fff;
  font-size: 0.95rem;
  font-weight: 600;
  letter-spacing: 0.2px;
  transition: background-color 0.15s ease, border-color 0.15s ease;
}

.btn-login:hover {
  background: #1E40AF;
  border-color: #1E40AF;
  color: #fff;
}

.btn-login:active {
  background: #1e3a8a;
  border-color: #1e3a8a;
}

.login-footer {
  text-align: center;
  color: rgba(255, 255, 255, 0.5);
  font-size: 0.78rem;
  margin-top: 24px;
}

.toggle-pass {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  z-index: 5;
  font-size: 1.1rem;
  padding: 4px;
}

.toggle-pass:hover {
  color: #3b82f6;
}
</style>
</head>
<body>

<div class="login-wrapper">
  <div class="login-container">
    <div class="login-card">
      <div class="login-logo">
        <i class="bi bi-qr-code-scan"></i>
      </div>
      <div class="text-center mb-2">
        <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-3 py-1 border border-primary border-opacity-25" style="letter-spacing: 0.5px; font-size: 0.75rem;">BANK MITRA · IT ASSET</span>
      </div>
      <h1 class="login-title">QR Maintenance</h1>
      <p class="login-subtitle">Masuk untuk mengelola data pemeliharaan komputer kantor.</p>

      <?= $successHtml ?>
      <?= $expiredHtml ?>
      <?= $errorHtml ?>

      <form method="post" autocomplete="off">
        <div class="field-wrapper">
          <i class="bi bi-person-fill input-icon"></i>
          <div class="form-floating">
            <input type="text" class="form-control" id="inputUser" name="username" placeholder="Username" required autofocus>
            <label for="inputUser">Username</label>
          </div>
        </div>

        <div class="field-wrapper">
          <i class="bi bi-lock-fill input-icon"></i>
          <div class="form-floating">
            <input type="password" class="form-control" id="inputPass" name="password" placeholder="Password" required>
            <label for="inputPass">Password</label>
          </div>
          <button type="button" class="toggle-pass" onclick="togglePassword()" title="Tampilkan / Sembunyikan Password">
            <i class="bi bi-eye" id="eyeIcon"></i>
          </button>
        </div>

        <button type="submit" class="btn btn-login mt-2">
          <i class="bi bi-box-arrow-in-right me-2"></i> Masuk ke Sistem
        </button>
      </form>
    </div>

    <div class="login-footer">
      <div class="fw-semibold text-white" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8); font-size: 0.86rem;">PT. BPR Mitratama Arthabuana</div>
      <div class="small opacity-75 text-white mt-1" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);"><i class="bi bi-shield-lock-fill me-1"></i> Sistem Pemeliharaan Aset IT — Akses Terbatas</div>
    </div>
  </div>
</div>

<script>
function togglePassword() {
  var inp = document.getElementById("inputPass");
  var ico = document.getElementById("eyeIcon");
  if (inp.type === "password") {
    inp.type = "text";
    ico.className = "bi bi-eye-slash";
  } else {
    inp.type = "password";
    ico.className = "bi bi-eye";
  }
}
</script>
</body>
</html>
