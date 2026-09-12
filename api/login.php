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
        $redirect = $_SESSION['after_login'] ?? module_url('dashboard.php');
        unset($_SESSION['after_login']);
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = $result['error'] ?? 'Username atau password salah.';
    }
}

$errorHtml = $error ? '<div class="alert alert-danger border py-2 px-3 d-flex align-items-center gap-2 mb-3" style="background:#FEF3F2; border-color:#FECDCA; color:#B42318; border-radius:8px;"><i class="bi bi-shield-exclamation fs-5"></i><span class="small">'.e($error).'</span></div>' : '';

$flashLogin = $_SESSION['flash_login'] ?? '';
unset($_SESSION['flash_login']);
$successHtml = $flashLogin ? '<div class="alert alert-success border py-2 px-3 d-flex align-items-center gap-2 mb-3" style="background:#ECFDF3; border-color:#A6F4C5; color:#16803C; border-radius:8px;"><i class="bi bi-check-circle-fill fs-5"></i><span class="small">'.e($flashLogin).'</span></div>' : '';

$expiredHtml = (!empty($_GET['expired']) && !$error && !$flashLogin)
    ? '<div class="alert alert-warning border py-2 px-3 d-flex align-items-center gap-2 mb-3" style="background:#FFFAEB; border-color:#FEDF89; color:#B54708; border-radius:8px;"><i class="bi bi-clock-history fs-5"></i><span class="small">Sesi Anda telah berakhir. Silakan masuk kembali untuk melanjutkan.</span></div>'
    : '';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Masuk ke Sistem · IT Operations Bank Mitra</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root {
  --navy-deep: #08182F;
  --navy-primary: #0D2748;
  --navy-subtle: #1E3A60;
  --blue-corporate: #124E96;
  --blue-accent: #2E7CF6;
  --bg-app: #F4F7FB;
  --border-subtle: #E4E9F0;
  --border-strong: #CBD5E1;
  --text-primary: #182230;
  --text-secondary: #667085;
  --text-muted: #98A2B3;
}

* { box-sizing: border-box; }

body {
  margin: 0;
  min-height: 100vh;
  font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  background-color: var(--bg-app);
  color: var(--text-primary);
  display: flex;
}

.login-split-wrapper {
  display: flex;
  width: 100%;
  min-height: 100vh;
}

/* Left Brand Panel */
.brand-panel {
  flex: 1;
  background-color: var(--navy-deep);
  color: #FFFFFF;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 48px 56px;
  position: relative;
  overflow: hidden;
  border-right: 1px solid var(--navy-subtle);
}

/* Subtle Technical Grid Motif (No AI neon glow) */
.brand-panel::before {
  content: "";
  position: absolute;
  inset: 0;
  background-image: 
    linear-gradient(to right, rgba(255, 255, 255, 0.03) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
  background-size: 32px 32px;
  pointer-events: none;
}

.brand-panel-header {
  position: relative;
  z-index: 1;
}

.brand-badge {
  width: 44px;
  height: 44px;
  background-color: var(--blue-corporate);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  color: #FFFFFF;
  margin-bottom: 16px;
}

.brand-title {
  font-size: 1.35rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  color: #FFFFFF;
}

.brand-sub {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  color: var(--blue-accent);
  text-transform: uppercase;
}

.brand-panel-content {
  position: relative;
  z-index: 1;
  max-width: 520px;
}

.brand-tagline {
  font-size: 2.1rem;
  font-weight: 700;
  line-height: 1.25;
  letter-spacing: -0.02em;
  color: #FFFFFF;
  margin-bottom: 16px;
}

.brand-desc {
  font-size: 0.95rem;
  color: #98A2B3;
  line-height: 1.6;
}

.brand-features {
  margin-top: 32px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.feature-item {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 0.88rem;
  color: #E4E9F0;
}

.feature-icon {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  background-color: rgba(46, 124, 246, 0.15);
  border: 1px solid rgba(46, 124, 246, 0.3);
  color: var(--blue-accent);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  flex-shrink: 0;
}

.brand-panel-footer {
  position: relative;
  z-index: 1;
  font-size: 0.75rem;
  color: #667085;
}

/* Right Form Panel */
.form-panel {
  width: 100%;
  max-width: 540px;
  background-color: #FFFFFF;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 48px 44px;
}

.form-panel-content {
  width: 100%;
  max-width: 400px;
  margin: auto;
}

.form-header {
  margin-bottom: 32px;
}

.form-title {
  font-size: 1.55rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--text-primary);
  margin-bottom: 6px;
}

.form-subtitle {
  font-size: 0.88rem;
  color: var(--text-secondary);
}

.field-label {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 6px;
  display: block;
}

.field-wrapper {
  position: relative;
  margin-bottom: 20px;
}

.field-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
  font-size: 1rem;
  z-index: 2;
  pointer-events: none;
}

.form-control-custom {
  width: 100%;
  height: 44px;
  padding: 8px 14px 8px 42px;
  border: 1px solid var(--border-strong);
  border-radius: 8px;
  font-size: 0.92rem;
  color: var(--text-primary);
  background-color: #FFFFFF;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.form-control-custom:focus {
  outline: none;
  border-color: var(--blue-corporate);
  box-shadow: 0 0 0 3px rgba(18, 78, 150, 0.14);
}

.toggle-pass-btn {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 4px;
  font-size: 1rem;
  z-index: 3;
}

.toggle-pass-btn:hover {
  color: var(--text-primary);
}

.btn-submit-login {
  width: 100%;
  height: 44px;
  background-color: var(--blue-corporate);
  border: 1px solid var(--blue-corporate);
  border-radius: 8px;
  color: #FFFFFF;
  font-size: 0.92rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease;
}

.btn-submit-login:hover {
  background-color: #0E3E77;
  border-color: #0E3E77;
}

.security-notice {
  background-color: #F8FAFC;
  border: 1px solid var(--border-subtle);
  border-radius: 8px;
  padding: 12px 14px;
  margin-top: 24px;
  font-size: 0.78rem;
  color: var(--text-secondary);
  line-height: 1.5;
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.form-panel-footer {
  text-align: center;
  font-size: 0.78rem;
  color: var(--text-muted);
  padding-top: 24px;
}

@media (max-width: 991px) {
  .brand-panel {
    display: none;
  }
  .form-panel {
    max-width: 100%;
    padding: 32px 24px;
  }
}
</style>
</head>
<body>

<div class="login-split-wrapper">
  <!-- Left Side: Corporate Identity & Technical Overview -->
  <div class="brand-panel">
    <div class="brand-panel-header">
      <div class="brand-badge">
        <i class="bi bi-shield-check"></i>
      </div>
      <div class="brand-title">BANK MITRA</div>
      <div class="brand-sub">PT. BPR MITRATAMA ARTHABUANA</div>
    </div>

    <div class="brand-panel-content">
      <h1 class="brand-tagline">IT Asset & Maintenance Operations Center</h1>
      <p class="brand-desc">
        Sistem internal pemantauan kepatuhan aset teknologi informasi, inspeksi checklist berkala, kendali QR Code, dan pelaporan audit operasional perbankan.
      </p>

      <div class="brand-features">
        <div class="feature-item">
          <div class="feature-icon"><i class="bi bi-check2"></i></div>
          <span>Pemeriksaan 9 poin checklist teknis komputer kantor cabang</span>
        </div>
        <div class="feature-item">
          <div class="feature-icon"><i class="bi bi-shield-lock"></i></div>
          <span>Verifikasi presensi inspeksi dan audit trail terenkripsi</span>
        </div>
        <div class="feature-item">
          <div class="feature-icon"><i class="bi bi-qr-code"></i></div>
          <span>Pindai QR fisik dan rekam jejak riwayat pemeliharaan perangkat</span>
        </div>
      </div>
    </div>

    <div class="brand-panel-footer">
      <div>IT Operations & Infrastructure Division · Versi 2.4 Enterprise</div>
      <div class="mt-1">Hak Cipta © <?= date('Y') ?> PT. BPR Mitratama Arthabuana. Seluruh hak cipta dilindungi.</div>
    </div>
  </div>

  <!-- Right Side: Clean Enterprise Login Form -->
  <div class="form-panel">
    <div></div>
    <div class="form-panel-content">
      <div class="form-header">
        <div class="d-lg-none mb-3">
          <div class="brand-badge" style="width:38px; height:38px; font-size:1.15rem; margin-bottom:8px;">
            <i class="bi bi-shield-check"></i>
          </div>
          <div class="brand-title text-dark" style="font-size:1.1rem;">BANK MITRA</div>
          <div class="brand-sub">IT OPERATIONS</div>
        </div>
        <h2 class="form-title">Masuk ke Sistem</h2>
        <div class="form-subtitle">Gunakan kredensial akun petugas untuk melanjutkan.</div>
      </div>

      <?= $successHtml ?>
      <?= $expiredHtml ?>
      <?= $errorHtml ?>

      <form method="post" autocomplete="off">
        <div class="field-wrapper">
          <label class="field-label" for="inputUser">Username</label>
          <div style="position: relative;">
            <i class="bi bi-person field-icon"></i>
            <input type="text" class="form-control-custom" id="inputUser" name="username" placeholder="Masukkan username..." required autofocus>
          </div>
        </div>

        <div class="field-wrapper">
          <label class="field-label" for="inputPass">Password</label>
          <div style="position: relative;">
            <i class="bi bi-lock field-icon"></i>
            <input type="password" class="form-control-custom" id="inputPass" name="password" placeholder="Masukkan password..." required style="padding-right: 40px;">
            <button type="button" class="toggle-pass-btn" onclick="togglePassword()" title="Tampilkan / Sembunyikan Password">
              <i class="bi bi-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-submit-login mt-3">
          <i class="bi bi-box-arrow-in-right"></i>
          <span>Masuk ke Sistem</span>
        </button>
      </form>

      <div class="security-notice">
        <i class="bi bi-shield-exclamation text-secondary fs-5 mt-1"></i>
        <div>
          <strong>Akses Terbatas:</strong> Sistem internal ini diperuntukkan khusus teknisi IT dan petugas yang berwenang di lingkungan PT. BPR Mitratama Arthabuana.
        </div>
      </div>
    </div>

    <div class="form-panel-footer">
      <div>Sistem Operasional Pemeliharaan Aset IT</div>
      <div class="mt-1">Authorized personnel only</div>
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
