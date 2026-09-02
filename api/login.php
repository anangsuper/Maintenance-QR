<?php
require __DIR__ . '/bootstrap.php';

// Jika sudah login, redirect ke dashboard
if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    header('Location: ' . module_url('dashboard.php'));
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

$errorHtml = $error ? '<div class="alert alert-danger border-0 shadow-sm py-2 px-3 d-flex align-items-center gap-2"><i class="bi bi-shield-exclamation fs-5"></i><span>'.e($error).'</span></div>' : '';

$flashLogin = $_SESSION['flash_login'] ?? '';
unset($_SESSION['flash_login']);
$successHtml = $flashLogin ? '<div class="alert alert-success border-0 shadow-sm py-2 px-3 d-flex align-items-center gap-2"><i class="bi bi-check-circle-fill fs-5"></i><span>'.e($flashLogin).'</span></div>' : '';

echo '<!doctype html>
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
  background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #3b82f6 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  -webkit-font-smoothing: antialiased;
}

.login-container {
  width: 100%;
  max-width: 420px;
  padding: 20px;
}

.login-card {
  background: #ffffff;
  border-radius: 24px;
  padding: 40px 36px 32px;
  box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.1);
}

.login-logo {
  width: 64px;
  height: 64px;
  background: linear-gradient(135deg, #1e40af, #3b82f6);
  border-radius: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 1.8rem;
  margin: 0 auto 20px;
  box-shadow: 0 8px 20px rgba(30, 64, 175, 0.35);
}

.login-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: #0f172a;
  text-align: center;
  margin-bottom: 4px;
  letter-spacing: -0.5px;
}

.login-subtitle {
  font-size: 0.88rem;
  color: #64748b;
  text-align: center;
  margin-bottom: 28px;
}

.form-floating > .form-control {
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  padding: 16px 14px 8px 44px;
  font-size: 0.95rem;
  height: 56px;
  background: #f8fafc;
  transition: all 0.2s ease;
}

.form-floating > .form-control:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
  background: #fff;
}

.form-floating > label {
  padding-left: 44px;
  color: #94a3b8;
  font-weight: 500;
}

.input-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
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
  padding: 14px;
  background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
  border: none;
  border-radius: 14px;
  color: #fff;
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: 0.3px;
  transition: all 0.25s ease;
  box-shadow: 0 6px 20px rgba(30, 64, 175, 0.3);
}

.btn-login:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 30px rgba(30, 64, 175, 0.4);
  background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
  color: #fff;
}

.btn-login:active {
  transform: translateY(0);
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

<div class="login-container">
  <div class="login-card">
    <div class="login-logo">
      <i class="bi bi-qr-code-scan"></i>
    </div>
    <h1 class="login-title">QR Maintenance</h1>
    <p class="login-subtitle">Masuk untuk mengelola data pemeliharaan komputer.</p>

    '.$successHtml.'
    '.$errorHtml.'

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
        <i class="bi bi-box-arrow-in-right me-2"></i> Masuk
      </button>
    </form>
  </div>

  <div class="login-footer">
    <i class="bi bi-shield-lock-fill me-1"></i> Sistem Maintenance IT — Akses Terbatas
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
</html>';
