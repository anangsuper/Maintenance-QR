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
        <i class="bi bi-box-arrow-in-right me-2"></i> Masuk
      </button>

      <div class="d-flex align-items-center my-3 text-muted small" id="passkeyDivider" style="display:none!important;">
        <hr class="flex-grow-1 my-0 border-secondary-subtle">
        <span class="px-2 text-secondary fw-semibold" style="font-size: 0.75rem;">ATAU BIOMETRIK</span>
        <hr class="flex-grow-1 my-0 border-secondary-subtle">
      </div>

      <button type="button" class="btn btn-outline-dark w-100 py-3 rounded-4 fw-bold shadow-sm" id="btnFaceIdLogin" style="display:none;" onclick="loginWithFaceId()">
        <i class="bi bi-person-bounding-box text-primary me-2 fs-5"></i> Masuk dengan Face ID / Touch ID
      </button>
      <div id="passkeyStatus" class="mt-2 text-center small text-muted"></div>
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

function b64urlToBuffer(base64url) {
  let base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
  while (base64.length % 4) base64 += '=';
  const bin = atob(base64);
  const bytes = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
  return bytes.buffer;
}

function bufferToB64url(buffer) {
  const bytes = new Uint8Array(buffer);
  let str = '';
  for (const b of bytes) str += String.fromCharCode(b);
  return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

// Cek apakah perangkat iPhone / smartphone mendukung sensor Face ID / Sidik Jari fisik
if (window.PublicKeyCredential && PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) {
  PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().then(avail => {
    if (avail) {
      document.getElementById("passkeyDivider").style.setProperty("display", "flex", "important");
      const btn = document.getElementById("btnFaceIdLogin");
      btn.style.display = "block";
      const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
      const isAndroid = /Android/.test(navigator.userAgent);
      if (isIOS) {
        btn.innerHTML = '<i class="bi bi-apple text-primary me-2 fs-5"></i> Masuk dengan Face ID / Touch ID (iPhone)';
      } else if (isAndroid) {
        btn.innerHTML = '<i class="bi bi-fingerprint text-success me-2 fs-5"></i> Masuk dengan Sidik Jari / Biometrik (Android)';
      } else {
        btn.innerHTML = '<i class="bi bi-shield-lock text-primary me-2 fs-5"></i> Masuk dengan Biometrik Perangkat';
      }
    }
  }).catch(() => {});
}

async function loginWithFaceId() {
  const btn = document.getElementById("btnFaceIdLogin");
  const status = document.getElementById("passkeyStatus");
  btn.disabled = true;
  status.className = "mt-2 text-center small text-muted";
  status.innerHTML = '<span class="spinner-border spinner-border-sm me-1 text-primary"></span> Menyiapkan sensor Face ID...';

  try {
    const res = await fetch("webauthn_handler.php?action=auth_options");
    const data = await res.json();
    if (!data.success || !data.options) {
      throw new Error(data.error || "Gagal membuat sesi Face ID.");
    }

    const opts = data.options;
    opts.challenge = b64urlToBuffer(opts.challenge);
    if (Array.isArray(opts.allowCredentials)) {
      opts.allowCredentials = opts.allowCredentials.map(c => ({
        type: c.type,
        id: b64urlToBuffer(c.id)
      }));
    }

    status.innerHTML = '<i class="bi bi-phone-fill me-1 text-primary"></i> Silakan verifikasi wajah di iPhone Anda...';
    const assertion = await navigator.credentials.get({ publicKey: opts });
    if (!assertion) throw new Error("Verifikasi dibatalkan.");

    status.innerHTML = '<span class="spinner-border spinner-border-sm me-1 text-success"></span> Memvalidasi biometrik ke sistem...';

    const verifyRes = await fetch("webauthn_handler.php?action=auth_verify", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        id: assertion.id,
        rawId: bufferToB64url(assertion.rawId),
        response: {
          clientDataJSON: bufferToB64url(assertion.response.clientDataJSON),
          authenticatorData: bufferToB64url(assertion.response.authenticatorData),
          signature: bufferToB64url(assertion.response.signature),
          userHandle: assertion.response.userHandle ? bufferToB64url(assertion.response.userHandle) : null
        }
      })
    });

    const verifyData = await verifyRes.json();
    if (verifyData.success) {
      status.className = "mt-2 text-center small text-success fw-bold";
      status.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Face ID Terverifikasi: ' + (verifyData.user.nama || "") + '! Mengalihkan...';
      setTimeout(() => {
        window.location.href = verifyData.redirect || "dashboard.php";
      }, 500);
    } else {
      throw new Error(verifyData.error || "Face ID tidak terdaftar pada akun mana pun.");
    }
  } catch (err) {
    console.error(err);
    status.className = "mt-2 text-center small text-danger";
    status.innerHTML = '<i class="bi bi-exclamation-circle-fill me-1"></i> ' + (err.message || "Gagal masuk via Face ID.");
    btn.disabled = false;
  }
}
</script>
</body>
</html>
