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
    // Jika sudah login aktif, langsung redirect ke halaman tujuan atau dashboard (SDM diarahkan ke tab kartu)
    $defaultRedirect = is_hrd() ? module_url('dashboard.php', ['tab' => 'kartu']) : module_url('dashboard.php');
    $redirect = safe_redirect_url($_SESSION['after_login'] ?? null, $defaultRedirect);
    unset($_SESSION['after_login']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Location: ' . $redirect);
    exit;
}

// Lepaskan lock session lebih awal pada request GET agar tidak mengunci antrean request lain
$flashLogin = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $flashLogin = $_SESSION['flash_login'] ?? '';
    unset($_SESSION['flash_login']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

$error = '';
$isLocked = false;
$lockRemainingSeconds = 0;
$clientIp = get_client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = trim((string)($_POST['password'] ?? ''));

    // 1. Cek status throttle awal
    $throttleCheck = check_login_throttle($username, $clientIp);

    // 2. Coba autentikasi pengguna: Jika kata sandi benar, segera buka kunci dan beri akses!
    $result = authenticate_user($username, $password);

    if (!empty($result['success'])) {
        reset_login_throttle($username, $clientIp);
        record_audit_log('LOGIN_SUCCESS', 'KEAMANAN', (int)($_SESSION['user_id'] ?? 0), $username, 'Login berhasil ke sistem', [
            'user_name' => (string)($_SESSION['nama'] ?? $username),
            'user_role' => (string)($_SESSION['role'] ?? 'teknisi'),
            'user_id' => (int)($_SESSION['user_id'] ?? 0),
            'ip' => $clientIp
        ]);
        $defaultRedirect = is_hrd() ? module_url('dashboard.php', ['tab' => 'kartu']) : module_url('dashboard.php');
        $redirect = safe_redirect_url($_SESSION['after_login'] ?? null, $defaultRedirect);
        unset($_SESSION['after_login']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header('Location: ' . $redirect);
        exit;
    }

    // 3. Jika kata sandi SALAH:
    if ($throttleCheck['locked']) {
        $error = $throttleCheck['message'];
        $isLocked = true;
        $lockRemainingSeconds = $throttleCheck['remaining_seconds'];
        record_audit_log('LOGIN_BLOCKED', 'KEAMANAN', null, $username ?: 'unknown', 'Percobaan login diblokir karena akun/IP sedang dalam status terkunci sementara (Standar OJK).', [
            'ip' => $clientIp,
            'remaining_seconds' => $throttleCheck['remaining_seconds'],
            'standard' => 'POJK No. 75/POJK.03/2016'
        ]);
    } else {
        $failInfo = record_login_failure($username, $clientIp);
        if ($failInfo['locked']) {
            $error = $failInfo['message'];
            $isLocked = true;
            $lockRemainingSeconds = $failInfo['remaining_seconds'];
        } else {
            $baseErr = $result['error'] ?? 'Username atau password salah.';
            $attempts = $failInfo['attempts'];
            $error = $baseErr . ' (Percobaan ke-' . $attempts . ' dari 5. Akun akan dikunci 15 menit jika 5 kali gagal sesuai standar OJK).';
        }
        record_audit_log('LOGIN_FAILED', 'KEAMANAN', null, $username, 'Percobaan login gagal: ' . $error, [
            'user_name' => $username,
            'user_role' => 'tamu',
            'ip' => $clientIp,
            'attempts' => $failInfo['attempts'] ?? 1
        ]);
    }
}

$errorHtml = $error ? '<div class="alert-custom alert-danger-custom" id="lockoutAlertBox"><i class="bi ' . ($isLocked ? 'bi-shield-lock-fill' : 'bi-shield-x') . ' alert-icon" style="font-size: 1.25rem;"></i><div style="flex:1;"><strong>' . ($isLocked ? 'Akun / IP Terkunci Sementara (Standar OJK)' : 'Autentikasi Gagal') . '</strong><div class="alert-text mt-1">'.e($error).'</div>' . ($isLocked ? '<div class="mt-2 fw-bold text-danger d-flex align-items-center gap-1" id="timerBox"><i class="bi bi-hourglass-split"></i> Sisa waktu penguncian: <span id="lockTimer">Menghitung...</span></div><div class="mt-2 pt-2 border-top border-danger border-opacity-25 small text-light opacity-75"><i class="bi bi-info-circle me-1"></i>Untuk pembukaan kunci darurat, hubungi Administrator IT.</div>' : '') . '</div></div>' : '';

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
<meta name="theme-color" content="#ffffff">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="QR Maint">
<link rel="apple-touch-icon" href="<?= module_url('pwa_icons.php', ['size' => 192]) ?>">
<link rel="icon" type="image/png" href="<?= module_url('logo.png') ?>">
<link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"></noscript>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root {
  --blue-primary: #2563eb;
  --blue-hover: #1d4ed8;
  --blue-light: #3b82f6;
  --text-main: #0f172a;
  --text-muted: #64748b;
  --text-subtle: #94a3b8;
  --border-color: #e2e8f0;
  --border-focus: #3b82f6;
  --bg-page: #f8fafc;
  --bg-card: #ffffff;
}

* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  min-height: 100vh;
  font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  background-color: var(--bg-page);
  color: var(--text-main);
  display: flex;
  overflow-x: hidden;
  position: relative;
}

.login-container {
  display: flex;
  width: 100%;
  min-height: 100vh;
  background-color: #ffffff;
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
  background-color: #f8fafc;
  border-right: 1px solid #e2e8f0;
  position: relative;
}

.brand-header {
  display: flex;
  align-items: center;
  gap: 16px;
}

.brand-logo-badge {
  width: 56px;
  height: 56px;
  background: #ffffff;
  border: 1.5px solid #e2e8f0;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
}

.brand-logo-badge img {
  width: 36px;
  height: 36px;
  object-fit: contain;
}

.brand-title-group h1 {
  font-size: 1.25rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: #0f172a;
  margin-bottom: 2px;
}

.brand-title-group p {
  font-size: 0.8rem;
  font-weight: 600;
  color: #2563eb;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin: 0;
}

.hero-content {
  max-width: 540px;
  margin: 40px 0;
}

.hero-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  background: #eff6ff;
  border: 1px solid #dbeafe;
  border-radius: 100px;
  font-size: 0.78rem;
  font-weight: 600;
  color: #1d4ed8;
  margin-bottom: 24px;
}

.hero-pill i {
  color: #2563eb;
}

.hero-headline {
  font-size: 2.35rem;
  font-weight: 800;
  line-height: 1.25;
  letter-spacing: -0.03em;
  color: #0f172a;
  margin-bottom: 18px;
}

.hero-headline span {
  color: #2563eb;
}

.hero-desc {
  font-size: 0.98rem;
  line-height: 1.6;
  color: #475569;
  margin-bottom: 36px;
}

/* Feature Grid in Hero */
.feature-list {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.feature-item {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  padding: 16px;
  border-radius: 14px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
  transition: all 0.25s ease;
}

.feature-item:hover {
  border-color: #cbd5e1;
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05);
  transform: translateY(-2px);
}

.feature-icon-box {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: #eff6ff;
  color: #2563eb;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.15rem;
  flex-shrink: 0;
}

.feature-text h4 {
  font-size: 0.88rem;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 3px;
}

.feature-text p {
  font-size: 0.75rem;
  color: #64748b;
  line-height: 1.4;
  margin: 0;
}

.hero-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-top: 1px solid #e2e8f0;
  padding-top: 24px;
}

.security-badge {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.8rem;
  color: #64748b;
}

.security-badge i {
  color: #059669;
  font-size: 1.1rem;
}

.system-status {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.75rem;
  font-weight: 600;
  color: #059669;
  background: #ecfdf5;
  padding: 4px 12px;
  border-radius: 100px;
  border: 1px solid #a7f3d0;
}

.status-dot {
  width: 7px;
  height: 7px;
  background-color: #10b981;
  border-radius: 50%;
  box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);
}

/* RIGHT AUTH PANEL */
.auth-panel {
  flex: 0.95;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  padding: 48px;
  background-color: #ffffff;
  position: relative;
}

.auth-card {
  width: 100%;
  max-width: 440px;
  background: #ffffff;
  border-radius: 20px;
  padding: 40px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.03);
  border: 1px solid #e2e8f0;
  color: #0f172a;
  position: relative;
}

.mobile-logo-header {
  display: none;
  text-align: center;
  margin-bottom: 24px;
}

.mobile-logo-header img {
  width: 52px;
  height: 52px;
  object-fit: contain;
  margin-bottom: 10px;
}

.mobile-brand-title {
  font-size: 1.2rem;
  font-weight: 800;
  color: #0f172a;
}

.auth-header {
  margin-bottom: 28px;
}

.auth-header-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  background: #eff6ff;
  border: 1px solid #dbeafe;
  border-radius: 6px;
  font-size: 0.72rem;
  font-weight: 700;
  color: #2563eb;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 12px;
}

.auth-title {
  font-size: 1.75rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: #0f172a;
  margin-bottom: 6px;
}

.auth-subtitle {
  font-size: 0.88rem;
  color: #64748b;
  line-height: 1.5;
  margin: 0;
}

/* Custom Alerts */
.alert-custom {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 12px;
  font-size: 0.83rem;
  line-height: 1.45;
  margin-bottom: 20px;
  animation: fadeInDown 0.3s ease;
}

.alert-danger-custom {
  background-color: #fef2f2;
  border: 1px solid #fee2e2;
  color: #991b1b;
}

.alert-success-custom {
  background-color: #f0fdf4;
  border: 1px solid #dcfce7;
  color: #166534;
}

.alert-warning-custom {
  background-color: #fffbeb;
  border: 1px solid #fef3c7;
  color: #92400e;
}

.alert-icon {
  font-size: 1.1rem;
  flex-shrink: 0;
  margin-top: 1px;
}

.alert-text {
  font-weight: 500;
}

@keyframes fadeInDown {
  from { opacity: 0; transform: translateY(-8px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Form Styling */
.form-group-custom {
  margin-bottom: 20px;
}

.custom-label {
  display: block;
  font-size: 0.82rem;
  font-weight: 700;
  color: #334155;
  margin-bottom: 8px;
  letter-spacing: -0.01em;
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
  font-size: 1.15rem;
  pointer-events: none;
}

.input-field {
  width: 100%;
  height: 48px;
  padding: 0 16px 0 44px;
  background: #ffffff;
  border: 1.5px solid #cbd5e1;
  border-radius: 10px;
  color: #0f172a;
  font-family: inherit;
  font-size: 0.92rem;
  font-weight: 500;
  transition: all 0.2s ease;
}

.input-field:focus {
  outline: none;
  border-color: #3b82f6;
  background: #ffffff;
  box-shadow: 0 0 0 3.5px rgba(59, 130, 246, 0.15);
}

.input-field::placeholder {
  color: #94a3b8;
  font-weight: 400;
}

.password-toggle-btn {
  position: absolute;
  right: 12px;
  background: none;
  border: none;
  color: #94a3b8;
  font-size: 1.15rem;
  padding: 6px;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.password-toggle-btn:hover {
  color: #334155;
  background-color: #f1f5f9;
}

/* Submit Button */
.btn-login-submit {
  width: 100%;
  height: 50px;
  background: #2563eb;
  color: #ffffff;
  border: none;
  border-radius: 10px;
  font-family: inherit;
  font-size: 0.95rem;
  font-weight: 600;
  letter-spacing: -0.01em;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
  transition: all 0.2s ease;
  margin-top: 24px;
}

.btn-login-submit:hover {
  background: #1d4ed8;
  box-shadow: 0 6px 18px rgba(37, 99, 235, 0.4);
  transform: translateY(-1px);
}

.btn-login-submit:active {
  transform: translateY(1px);
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
}

/* Auth Panel Footer */
.auth-footer {
  text-align: center;
  padding-top: 24px;
  font-size: 0.78rem;
  color: #94a3b8;
}

/* RESPONSIVE DESIGN */
@media (max-width: 1080px) {
  .hero-panel {
    padding: 40px 36px;
  }
  .auth-panel {
    padding: 36px 32px;
  }
}

@media (max-width: 900px) {
  .login-container {
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 24px 16px;
    background-color: #f8fafc;
  }

  .hero-panel {
    display: none;
  }

  .auth-panel {
    background: transparent;
    max-width: 440px;
    width: 100%;
    padding: 0;
  }

  .auth-card {
    border-radius: 20px;
    padding: 32px 24px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
  }

  .mobile-logo-header {
    display: block;
  }
}

@media (max-width: 480px) {
  .auth-card {
    padding: 26px 18px;
    border-radius: 16px;
  }
  .auth-title {
    font-size: 1.45rem;
  }
}
</style>
</head>
<body>

<div class="login-container">
  
  <!-- LEFT HERO PANEL (Desktop Enterprise Branding) -->
  <div class="hero-panel">
    <div class="brand-header">
      <div class="brand-logo-badge">
        <img src="<?= module_url('logo.png') ?>" alt="Logo Bank" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'%2338bdf8\'><path d=\'M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z\'/></svg>'">
      </div>
      <div class="brand-title-group">
        <h1>PT. BPR MITRATAMA ARTHABUANA</h1>
        <p>Divisi Teknologi Informasi & Operasional</p>
      </div>
    </div>

    <div class="hero-content">
      <div class="hero-pill">
        <i class="bi bi-shield-check"></i>
        <span>Enterprise Maintenance Management System</span>
      </div>
      
      <h2 class="hero-headline">
        Sistem Manajemen &amp; Pemeliharaan <span>Aset IT Berbasis QR</span>
      </h2>

      <p class="hero-desc">
        Platform operasional terintegrasi untuk pemantauan berkala, pemeliharaan preventif, logbook perbaikan, dan manajemen inventaris perangkat perbankan.
      </p>

      <div class="feature-list">
        <div class="feature-item">
          <div class="feature-icon-box">
            <i class="bi bi-qr-code-scan"></i>
          </div>
          <div class="feature-text">
            <h4>Fast QR Scanner</h4>
            <p>Akses riwayat & catat pemeliharaan aset instan lewat barcode.</p>
          </div>
        </div>

        <div class="feature-item">
          <div class="feature-icon-box">
            <i class="bi bi-shield-check"></i>
          </div>
          <div class="feature-text">
            <h4>Security & Audit</h4>
            <p>Pencatatan riwayat audit dan integritas sistem pemeliharaan.</p>
          </div>
        </div>

        <div class="feature-item">
          <div class="feature-icon-box">
            <i class="bi bi-database-check"></i>
          </div>
          <div class="feature-text">
            <h4>Sync Google Sheets</h4>
            <p>Penyimpanan cloud ganda otomatis real-time & backup aman.</p>
          </div>
        </div>

        <div class="feature-item">
          <div class="feature-icon-box">
            <i class="bi bi-graph-up-arrow"></i>
          </div>
          <div class="feature-text">
            <h4>Live Analytics</h4>
            <p>Pemantauan KPI performa perangkat & jadwal service berkala.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="hero-footer">
      <div class="security-badge">
        <i class="bi bi-lock-fill"></i>
        <span>Bank-Grade 256-Bit SSL Encryption</span>
      </div>
      <div class="system-status">
        <span class="status-dot"></span>
        <span>Operational Ready</span>
      </div>
    </div>
  </div>

  <!-- RIGHT AUTH PANEL (Login Card) -->
  <div class="auth-panel">
    <div class="auth-card">
      
      <!-- Mobile Logo Header -->
      <div class="mobile-logo-header">
        <img src="<?= module_url('logo.png') ?>" alt="Logo Bank" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'%231d68d8\'><path d=\'M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z\'/></svg>'">
        <div class="mobile-brand-title">BPR MITRATAMA ARTHABUANA</div>
        <div class="text-muted small">Sistem Operasional Pemeliharaan IT</div>
        <div class="mt-2">
          <span class="system-status d-inline-flex">
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
        <p class="auth-subtitle">Gunakan kredensial akun IT Anda untuk masuk ke sistem.</p>
      </div>

      <?= $successHtml ?>
      <?= $expiredHtml ?>
      <?= $errorHtml ?>

      <!-- FORM LOGIN PASSWORD -->
      <form method="post" id="loginForm" autocomplete="on">
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
              value="<?= e($username ?? '') ?>"
              required 
              autofocus 
              autocomplete="username"
              spellcheck="false"
              enterkeyhint="next"
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
              autocomplete="current-password"
              enterkeyhint="go"
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
    </div>

    <!-- Panel Footer -->
    <div class="auth-footer">
      <div>© <?= date('Y') ?> PT. BPR Mitratama Arthabuana</div>
      <div style="font-size: 0.7rem; color: #94a3b8; margin-top: 4px;">Divisi IT Operations &amp; Infrastructure</div>
    </div>
  </div>

</div>

<!-- Bootstrap 5 Bundle JS (Modal & Interactive Controls) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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

// Visual loading indicator on submit password
const loginForm = document.getElementById("loginForm");
const submitBtn = document.getElementById("submitBtn");
const submitIcon = document.getElementById("submitIcon");
const submitText = document.getElementById("submitText");

if (loginForm) {
  loginForm.addEventListener("submit", function(e) {
    if (loginForm.checkValidity && !loginForm.checkValidity()) {
      return;
    }
    if (submitBtn) {
      if (submitIcon) submitIcon.className = "spinner-border spinner-border-sm me-2";
      if (submitText) submitText.textContent = "Memverifikasi Akun...";
      submitBtn.style.opacity = "0.85";
    }
  });
}

// Reset button state on page show (handles browser back/forward cache)
window.addEventListener("pageshow", function() {
  if (submitBtn) {
    submitBtn.removeAttribute("disabled");
    submitBtn.style.pointerEvents = "";
    submitBtn.style.opacity = "";
    if (submitText) submitText.textContent = "Masuk Sekarang";
    if (submitIcon) submitIcon.className = "bi bi-box-arrow-in-right";
  }
});

// Countdown Timer jika akun/IP terkunci (Standar OJK 15 Menit)
const lockRemainingSecs = <?= (int)$lockRemainingSeconds ?>;
if (lockRemainingSecs > 0) {
  let remaining = lockRemainingSecs;
  const timerSpan = document.getElementById("lockTimer");
  const inpUser = document.getElementById("inputUser");
  const inpPass = document.getElementById("inputPass");
  const toggleBtn = document.getElementById("togglePassBtn");

  function updateLockCountdown() {
    if (remaining <= 0) {
      if (timerSpan) timerSpan.innerText = "Waktu habis. Silakan muat ulang halaman.";
      if (submitBtn) {
        submitBtn.removeAttribute("disabled");
        submitBtn.style.opacity = "1";
        submitBtn.style.cursor = "pointer";
      }
      if (submitText) submitText.innerText = "Masuk Sekarang";
      if (submitIcon) submitIcon.className = "bi bi-box-arrow-in-right";
      if (inpUser) inpUser.removeAttribute("disabled");
      if (inpPass) inpPass.removeAttribute("disabled");
      if (toggleBtn) toggleBtn.removeAttribute("disabled");
      return;
    }
    const mins = Math.floor(remaining / 60);
    const secs = remaining % 60;
    if (timerSpan) {
      timerSpan.innerText = mins + " menit " + (secs < 10 ? "0" : "") + secs + " detik";
    }
    remaining--;
    setTimeout(updateLockCountdown, 1000);
  }
  updateLockCountdown();
}

// Bersihkan sisa timer aktivitas lokal saat berada di halaman login
try {
  localStorage.removeItem("app_last_active");
} catch(e) {}

// Auto-focus username field instan begitu halaman terbuka (Bisa langsung ketik tanpa nunggu)
(function() {
  function focusUsername() {
    var u = document.getElementById("inputUser");
    if (u && !u.disabled) {
      if (!u.value) {
        u.focus();
      } else {
        var p = document.getElementById("inputPass");
        if (p && !p.disabled) p.focus();
      }
    }
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", focusUsername);
  } else {
    focusUsername();
  }
  window.addEventListener("pageshow", focusUsername);
})();

// Service Worker Registration for PWA
if ("serviceWorker" in navigator) {
  window.addEventListener("load", function() {
    navigator.serviceWorker.register("<?= module_url('sw.js', ['v' => 15]) ?>")
      .catch(function(err) {
        console.warn("SW reg error:", err);
      });
  });
}
</script>
</body>
</html>
