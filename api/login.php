<?php
require __DIR__ . '/bootstrap.php';

// Simpan redirect URL jika dikirim via GET (Wajib divalidasi mencegah Open Redirect CWE-601)
if (!empty($_GET['redirect'])) {
    $_SESSION['after_login'] = safe_redirect_url((string)$_GET['redirect']);
}

// Jika sesi telah berakhir (idle timeout), pastikan auth session dibersihkan
if (!empty($_GET['expired'])) {
    logout_user();
} elseif (is_logged_in()) {
    // Jika sudah login aktif, langsung redirect ke halaman tujuan atau dashboard
    $redirect = safe_redirect_url($_SESSION['after_login'] ?? null, module_url('dashboard.php'));
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
        record_audit_log('LOGIN_SUCCESS', 'KEAMANAN', (int)($_SESSION['user']['id'] ?? 0), $username, 'Login berhasil ke sistem', [
            'user_name' => (string)($_SESSION['user']['nama'] ?? $username),
            'user_role' => (string)($_SESSION['user']['role'] ?? 'teknisi'),
            'user_id' => (int)($_SESSION['user']['id'] ?? 0)
        ]);
        $redirect = safe_redirect_url($_SESSION['after_login'] ?? null, module_url('dashboard.php'));
        unset($_SESSION['after_login']);
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = $result['error'] ?? 'Username atau password salah.';
        record_audit_log('LOGIN_FAILED', 'KEAMANAN', null, $username, 'Percobaan login gagal: ' . $error, [
            'user_name' => $username,
            'user_role' => 'tamu'
        ]);
    }
}

$errorHtml = $error ? '<div class="alert-custom alert-danger-custom"><i class="bi bi-shield-x alert-icon"></i><div><strong>Autentikasi Gagal</strong><div class="alert-text">'.e($error).'</div></div></div>' : '';

$flashLogin = $_SESSION['flash_login'] ?? '';
unset($_SESSION['flash_login']);
$successHtml = $flashLogin ? '<div class="alert-custom alert-success-custom"><i class="bi bi-check-circle-fill alert-icon"></i><div><strong>Informasi</strong><div class="alert-text">'.e($flashLogin).'</div></div></div>' : '';

$expiredHtml = (!empty($_GET['expired']) && !$error && !$flashLogin)
    ? '<div class="alert-custom alert-warning-custom"><i class="bi bi-clock-history alert-icon"></i><div><strong>Sesi Berakhir</strong><div class="alert-text">Sesi Anda telah habis. Silakan masuk kembali untuk melanjutkan pekerjaan.</div></div></div>'
    : '';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>Masuk ke Sistem · IT Operations Bank Mitra</title>
<link rel="manifest" href="<?= module_url('manifest.webmanifest') ?>">
<meta name="theme-color" content="#071224">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="QR Maint">
<link rel="apple-touch-icon" href="<?= module_url('pwa_icons.php', ['size' => 192]) ?>">
<link rel="icon" type="image/png" href="<?= app_logo_url() ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root {
  --navy-darkest: #050d1a;
  --navy-dark: #071529;
  --navy-card: #0c203c;
  --navy-surface: #10294d;
  --blue-primary: #1d68d8;
  --blue-hover: #1557ba;
  --blue-light: #3b82f6;
  --blue-glow: rgba(59, 130, 246, 0.35);
  --cyan-accent: #06b6d4;
  --emerald: #10b981;
  --emerald-glow: rgba(16, 185, 129, 0.3);
  --text-main: #f8fafc;
  --text-muted: #94a3b8;
  --text-subtle: #64748b;
  --border-glass: rgba(255, 255, 255, 0.08);
  --border-focus: rgba(59, 130, 246, 0.6);
  --glass-bg: rgba(13, 31, 56, 0.72);
  --form-card-bg: #ffffff;
}

* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  min-height: 100vh;
  font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  background-color: var(--navy-darkest);
  color: var(--text-main);
  display: flex;
  overflow-x: hidden;
  position: relative;
}

/* Ambient Animated Glows */
.ambient-glow {
  position: fixed;
  width: 500px;
  height: 500px;
  border-radius: 50%;
  pointer-events: none;
  filter: blur(120px);
  z-index: 0;
  opacity: 0.45;
  animation: pulseGlow 10s ease-in-out infinite alternate;
}

.glow-top-left {
  top: -150px;
  left: -100px;
  background: radial-gradient(circle, #1d68d8 0%, rgba(6, 182, 212, 0.4) 60%, transparent 80%);
}

.glow-bottom-right {
  bottom: -150px;
  right: -100px;
  background: radial-gradient(circle, #0e3f8a 0%, rgba(29, 104, 216, 0.3) 60%, transparent 80%);
  animation-delay: -5s;
}

@keyframes pulseGlow {
  0% { transform: scale(1) translate(0, 0); opacity: 0.35; }
  50% { transform: scale(1.15) translate(20px, 30px); opacity: 0.55; }
  100% { transform: scale(1) translate(0, 0); opacity: 0.35; }
}

/* Subtle Geometric Cyber Grid Background */
.cyber-grid {
  position: fixed;
  inset: 0;
  background-image: 
    linear-gradient(to right, rgba(255, 255, 255, 0.025) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.025) 1px, transparent 1px);
  background-size: 40px 40px;
  pointer-events: none;
  z-index: 0;
  mask-image: radial-gradient(ellipse at center, black 40%, transparent 85%);
  -webkit-mask-image: radial-gradient(ellipse at center, black 40%, transparent 85%);
}

.login-container {
  display: flex;
  width: 100%;
  min-height: 100vh;
  position: relative;
  z-index: 1;
}

/* LEFT HERO BRAND PANEL */
.hero-panel {
  flex: 1.15;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 56px 64px;
  position: relative;
  border-right: 1px solid rgba(255, 255, 255, 0.07);
  background: linear-gradient(135deg, rgba(7, 21, 41, 0.85) 0%, rgba(5, 13, 26, 0.95) 100%);
  backdrop-filter: blur(20px);
}

.brand-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.logo-wrapper {
  display: flex;
  align-items: center;
  gap: 14px;
}

.logo-badge {
  background: rgba(255, 255, 255, 0.98);
  padding: 8px 14px;
  border-radius: 12px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform 0.3s ease;
}

.logo-badge:hover {
  transform: translateY(-2px);
}

.logo-badge img {
  height: 48px;
  width: auto;
  max-width: 190px;
  object-fit: contain;
}

.system-status-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(16, 185, 129, 0.12);
  border: 1px solid rgba(16, 185, 129, 0.35);
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 0.76rem;
  font-weight: 600;
  color: #34d399;
  letter-spacing: 0.02em;
}

.status-dot {
  width: 7px;
  height: 7px;
  background-color: #10b981;
  border-radius: 50%;
  box-shadow: 0 0 8px #10b981;
  animation: pulseDot 2s infinite;
}

@keyframes pulseDot {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.4); opacity: 0.5; }
}

.hero-body {
  max-width: 580px;
  margin: 40px 0;
}

.hero-badge-tag {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: linear-gradient(90deg, rgba(29, 104, 216, 0.25) 0%, rgba(6, 182, 212, 0.15) 100%);
  border: 1px solid rgba(59, 130, 246, 0.4);
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 0.78rem;
  font-weight: 700;
  color: #60a5fa;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin-bottom: 20px;
}

.hero-title {
  font-size: 2.35rem;
  font-weight: 800;
  line-height: 1.22;
  letter-spacing: -0.03em;
  color: #ffffff;
  margin-bottom: 16px;
}

.hero-title .gradient-text {
  background: linear-gradient(135deg, #60a5fa 0%, #38bdf8 50%, #818cf8 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.hero-desc {
  font-size: 0.98rem;
  color: var(--text-muted);
  line-height: 1.65;
  margin-bottom: 34px;
}

/* Feature Showcase Cards */
.feature-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.feature-card {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 14px 18px;
  background: rgba(16, 41, 77, 0.45);
  border: 1px solid var(--border-glass);
  border-radius: 14px;
  backdrop-filter: blur(10px);
  transition: all 0.25s ease;
}

.feature-card:hover {
  background: rgba(29, 104, 216, 0.15);
  border-color: rgba(59, 130, 246, 0.35);
  transform: translateX(4px);
}

.feature-icon-box {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(135deg, rgba(29, 104, 216, 0.4) 0%, rgba(6, 182, 212, 0.2) 100%);
  border: 1px solid rgba(59, 130, 246, 0.4);
  color: #93c5fd;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.15rem;
  flex-shrink: 0;
}

.feature-info h4 {
  font-size: 0.92rem;
  font-weight: 700;
  color: #f1f5f9;
  margin-bottom: 3px;
}

.feature-info p {
  font-size: 0.8rem;
  color: #94a3b8;
  margin: 0;
  line-height: 1.4;
}

/* Hero Footer Badges */
.hero-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
  padding-top: 24px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.security-badge-group {
  display: flex;
  align-items: center;
  gap: 18px;
}

.sec-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.75rem;
  color: var(--text-muted);
}

.sec-pill i {
  color: #38bdf8;
  font-size: 0.88rem;
}

.version-tag {
  font-size: 0.75rem;
  color: var(--text-subtle);
  font-family: monospace;
}

/* RIGHT AUTH FORM PANEL */
.auth-panel {
  width: 100%;
  max-width: 520px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 48px 48px;
  background: #ffffff;
  position: relative;
  box-shadow: -20px 0 60px rgba(0, 0, 0, 0.35);
  color: #1e293b;
}

.auth-panel-inner {
  width: 100%;
  max-width: 390px;
  margin: auto;
}

.mobile-logo-header {
  display: none;
  text-align: center;
  margin-bottom: 28px;
}

.auth-header {
  margin-bottom: 28px;
}

.auth-header-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #eff6ff;
  color: #1d4ed8;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.74rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  margin-bottom: 12px;
}

.auth-title {
  font-size: 1.7rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.025em;
  margin-bottom: 6px;
}

.auth-subtitle {
  font-size: 0.88rem;
  color: #64748b;
  line-height: 1.5;
}

/* Modern Alerts */
.alert-custom {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 10px;
  font-size: 0.82rem;
  margin-bottom: 20px;
  animation: slideDown 0.3s ease;
}

@keyframes slideDown {
  from { opacity: 0; transform: translateY(-8px); }
  to { opacity: 1; transform: translateY(0); }
}

.alert-danger-custom {
  background-color: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
}

.alert-success-custom {
  background-color: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #166534;
}

.alert-warning-custom {
  background-color: #fffbeb;
  border: 1px solid #fde68a;
  color: #92400e;
}

.alert-icon {
  font-size: 1.15rem;
  flex-shrink: 0;
  margin-top: 1px;
}

.alert-text {
  font-size: 0.78rem;
  margin-top: 2px;
  opacity: 0.9;
}

/* Modern Input Controls */
.form-group-custom {
  margin-bottom: 20px;
}

.custom-label {
  display: block;
  font-size: 0.82rem;
  font-weight: 700;
  color: #334155;
  margin-bottom: 7px;
  letter-spacing: 0.01em;
}

.input-container {
  position: relative;
  display: flex;
  align-items: center;
}

.input-icon-left {
  position: absolute;
  left: 14px;
  color: #94a3b8;
  font-size: 1.1rem;
  pointer-events: none;
  transition: color 0.2s ease;
  z-index: 2;
}

.input-field {
  width: 100%;
  height: 48px;
  padding: 10px 14px 10px 44px;
  background-color: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-size: 0.92rem;
  font-weight: 500;
  color: #0f172a;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.input-field:focus {
  outline: none;
  background-color: #ffffff;
  border-color: #2563eb;
  box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
}

.input-field:focus + .input-icon-left,
.input-container:focus-within .input-icon-left {
  color: #2563eb;
}

.input-field::placeholder {
  color: #94a3b8;
  font-weight: 400;
}

.password-toggle-btn {
  position: absolute;
  right: 12px;
  background: transparent;
  border: none;
  color: #94a3b8;
  padding: 6px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 1.05rem;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: color 0.2s, background-color 0.2s;
  z-index: 3;
}

.password-toggle-btn:hover {
  color: #1e293b;
  background-color: #e2e8f0;
}

/* Submit Button */
.btn-login-submit {
  width: 100%;
  height: 48px;
  background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
  border: none;
  border-radius: 10px;
  color: #ffffff;
  font-size: 0.95rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
  transition: all 0.25s ease;
  margin-top: 10px;
}

.btn-login-submit:hover {
  background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);
  box-shadow: 0 6px 20px rgba(37, 99, 235, 0.45);
  transform: translateY(-1px);
}

.btn-login-submit:active {
  transform: translateY(0);
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
}

.btn-login-submit:disabled {
  opacity: 0.7;
  cursor: not-allowed;
  transform: none;
}

/* Security Notice Box */
.security-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 12px 14px;
  margin-top: 24px;
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.security-card i {
  color: #475569;
  font-size: 1.1rem;
  margin-top: 2px;
  flex-shrink: 0;
}

.security-card-text {
  font-size: 0.76rem;
  color: #64748b;
  line-height: 1.45;
}

.security-card-text strong {
  color: #334155;
}

/* Auth Panel Footer */
.auth-footer {
  text-align: center;
  padding-top: 24px;
  font-size: 0.76rem;
  color: #94a3b8;
}

/* RESPONSIVE DESIGN */
@media (max-width: 1080px) {
  .hero-panel {
    padding: 40px 36px;
  }
  .auth-panel {
    max-width: 460px;
    padding: 40px 32px;
  }
}

@media (max-width: 900px) {
  .login-container {
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 24px 16px;
  }

  .hero-panel {
    display: none;
  }

  .auth-panel {
    max-width: 440px;
    border-radius: 20px;
    padding: 36px 28px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.1);
  }

  .mobile-logo-header {
    display: block;
  }
}

@media (max-width: 480px) {
  .auth-panel {
    padding: 28px 20px;
    border-radius: 16px;
  }
  .auth-title {
    font-size: 1.45rem;
  }
}
</style>
</head>
<body>

<!-- Animated Ambient Glows & Cyber Grid Background -->
<div class="ambient-glow glow-top-left"></div>
<div class="ambient-glow glow-bottom-right"></div>
<div class="cyber-grid"></div>

<div class="login-container">
  
  <!-- LEFT HERO BRAND PANEL -->
  <div class="hero-panel">
    <!-- Header -->
    <div class="brand-header">
      <div class="logo-wrapper">
        <div class="logo-badge">
          <img src="<?= app_logo_url() ?>" alt="Bank Mitra Logo">
        </div>
      </div>
      <div class="system-status-pill">
        <span class="status-dot"></span>
        <span>SISTEM OPERASIONAL AKTIF</span>
      </div>
    </div>

    <!-- Body -->
    <div class="hero-body">
      <div class="hero-badge-tag">
        <i class="bi bi-cpu-fill"></i>
        <span>IT Infrastructure & Asset Control</span>
      </div>
      
      <h1 class="hero-title">
        Enterprise IT Asset & <br>
        <span class="gradient-text">Maintenance Operations</span>
      </h1>
      
      <p class="hero-desc">
        Pusat kendali dan monitoring terintegrasi pemeliharaan perangkat komputer, inspeksi checklist berkala, kendali QR Code fisik, serta audit trail operasional perbankan.
      </p>

      <!-- Feature Cards -->
      <div class="feature-list">
        <div class="feature-card">
          <div class="feature-icon-box">
            <i class="bi bi-clipboard2-check-fill"></i>
          </div>
          <div class="feature-info">
            <h4>Checklist Teknis 9 Poin</h4>
            <p>Standarisasi inspeksi preventif berkala perangkat komputer & jaringan kantor cabang.</p>
          </div>
        </div>

        <div class="feature-card">
          <div class="feature-icon-box">
            <i class="bi bi-qr-code-scan"></i>
          </div>
          <div class="feature-info">
            <h4>Presensi & Scan QR Fisik</h4>
            <p>Verifikasi riwayat pemeliharaan aset secara langsung di lokasi dengan validasi QR.</p>
          </div>
        </div>

        <div class="feature-card">
          <div class="feature-icon-box">
            <i class="bi bi-shield-lock-fill"></i>
          </div>
          <div class="feature-info">
            <h4>Audit Trail & Keamanan Bank</h4>
            <p>Rekam jejak setiap tindakan teknisi tercatat real-time dan aman terenkripsi.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="hero-footer">
      <div class="security-badge-group">
        <div class="sec-pill">
          <i class="bi bi-shield-check"></i>
          <span>256-Bit SSL Enkripsi</span>
        </div>
        <div class="sec-pill">
          <i class="bi bi-database-check"></i>
          <span>Auto Backup Cloud</span>
        </div>
      </div>
      <div class="version-tag">v2.4.0 Enterprise · PT. BPR Mitratama Arthabuana</div>
    </div>
  </div>

  <!-- RIGHT AUTH FORM PANEL -->
  <div class="auth-panel">
    <div></div>
    
    <div class="auth-panel-inner">
      <!-- Mobile Logo Header -->
      <div class="mobile-logo-header">
        <div class="logo-badge d-inline-flex mb-3">
          <img src="<?= app_logo_url() ?>" alt="Bank Mitra Logo">
        </div>
        <div>
          <span class="system-status-pill">
            <span class="status-dot"></span>
            <span>SISTEM OPERASIONAL AKTIF</span>
          </span>
        </div>
      </div>

      <div class="auth-header">
        <div class="auth-header-pill">
          <i class="bi bi-shield-lock"></i>
          <span>Portal Petugas</span>
        </div>
        <h2 class="auth-title">Masuk ke Akun</h2>
        <p class="auth-subtitle">Gunakan kredensial akun IT Anda untuk mengakses sistem pemeliharaan aset.</p>
      </div>

      <?= $successHtml ?>
      <?= $expiredHtml ?>
      <?= $errorHtml ?>

      <form method="post" id="loginForm" autocomplete="off">
        <div class="form-group-custom">
          <label class="custom-label" for="inputUser">Username / ID Petugas</label>
          <div class="input-container">
            <i class="bi bi-person input-icon-left"></i>
            <input 
              type="text" 
              class="input-field" 
              id="inputUser" 
              name="username" 
              placeholder="Contoh: teknisi_it" 
              required 
              autofocus 
              spellcheck="false"
            >
          </div>
        </div>

        <div class="form-group-custom">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="custom-label mb-0" for="inputPass">Kata Sandi</label>
          </div>
          <div class="input-container">
            <i class="bi bi-key input-icon-left"></i>
            <input 
              type="password" 
              class="input-field" 
              id="inputPass" 
              name="password" 
              placeholder="Masukkan kata sandi..." 
              required
              style="padding-right: 46px;"
            >
            <button 
              type="button" 
              class="password-toggle-btn" 
              id="togglePassBtn"
              onclick="togglePassword()" 
              title="Lihat / Sembunyikan Kata Sandi"
              aria-label="Toggle Password Visibility"
            >
              <i class="bi bi-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-login-submit" id="submitBtn">
          <i class="bi bi-box-arrow-in-right" id="submitIcon"></i>
          <span id="submitText">Masuk Sekarang</span>
        </button>
      </form>

      <div class="security-card">
        <i class="bi bi-shield-exclamation"></i>
        <div class="security-card-text">
          <strong>Akses Terbatas:</strong> Sistem internal ini khusus untuk staf IT dan petugas berwenang PT. BPR Mitratama Arthabuana. Segala aktivitas diawasi & dicatat ke log audit.
        </div>
      </div>
    </div>

    <!-- Panel Footer -->
    <div class="auth-footer">
      <div>© <?= date('Y') ?> PT. BPR Mitratama Arthabuana</div>
      <div style="font-size: 0.7rem; color: #94a3b8; margin-top: 4px;">Divisi IT Operations & Infrastructure</div>
    </div>
  </div>

</div>

<script>
function togglePassword() {
  const inp = document.getElementById("inputPass");
  const ico = document.getElementById("eyeIcon");
  if (inp.type === "password") {
    inp.type = "text";
    ico.className = "bi bi-eye-slash";
  } else {
    inp.type = "password";
    ico.className = "bi bi-eye";
  }
}

// Visual loading indicator on submit
const loginForm = document.getElementById("loginForm");
const submitBtn = document.getElementById("submitBtn");
const submitIcon = document.getElementById("submitIcon");
const submitText = document.getElementById("submitText");

if (loginForm) {
  loginForm.addEventListener("submit", function() {
    if (submitBtn) {
      submitBtn.disabled = true;
      submitIcon.className = "spinner-border spinner-border-sm";
      submitText.textContent = "Memverifikasi Akun...";
    }
  });
}

// Service Worker Registration for PWA
if ("serviceWorker" in navigator) {
  window.addEventListener("load", function() {
    navigator.serviceWorker.register("<?= module_url('sw.js') ?>")
      .catch(function(err) {
        console.warn("SW reg error:", err);
      });
  });
}
</script>
</body>
</html>
