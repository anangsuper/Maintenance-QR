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
$clientIp = get_client_ip();
$initialCheck = check_login_throttle('', $clientIp);
$isLocked = $initialCheck['locked'];
$lockRemainingSeconds = $initialCheck['remaining_seconds'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = trim((string)($_POST['password'] ?? ''));

    // 1. Cek Throttle / Lockout sebelum memproses login
    $throttleCheck = check_login_throttle($username, $clientIp);

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
        $result = authenticate_user($username, $password);

        if (!empty($result['success'])) {
            reset_login_throttle($username, $clientIp);
            record_audit_log('LOGIN_SUCCESS', 'KEAMANAN', (int)($_SESSION['user_id'] ?? 0), $username, 'Login berhasil ke sistem', [
                'user_name' => (string)($_SESSION['nama'] ?? $username),
                'user_role' => (string)($_SESSION['role'] ?? 'teknisi'),
                'user_id' => (int)($_SESSION['user_id'] ?? 0),
                'ip' => $clientIp
            ]);
            $redirect = safe_redirect_url($_SESSION['after_login'] ?? null, module_url('dashboard.php'));
            unset($_SESSION['after_login']);
            header('Location: ' . $redirect);
            exit;
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
}

$errorHtml = $error ? '<div class="alert-custom alert-danger-custom" id="lockoutAlertBox"><i class="bi ' . ($isLocked ? 'bi-shield-lock-fill' : 'bi-shield-x') . ' alert-icon" style="font-size: 1.25rem;"></i><div style="flex:1;"><strong>' . ($isLocked ? 'Akun / IP Terkunci Sementara (Standar OJK)' : 'Autentikasi Gagal') . '</strong><div class="alert-text mt-1">'.e($error).'</div>' . ($isLocked ? '<div class="mt-2 fw-bold text-danger d-flex align-items-center gap-1" id="timerBox"><i class="bi bi-hourglass-split"></i> Sisa waktu penguncian: <span id="lockTimer">Menghitung...</span></div><div class="mt-2 pt-2 border-top border-danger border-opacity-25 small text-light opacity-75"><i class="bi bi-info-circle me-1"></i>Untuk pembukaan kunci darurat, hubungi Administrator IT.</div>' : '') . '</div></div>' : '';

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
  border-right: 1px solid var(--border-glass);
}

.brand-header {
  display: flex;
  align-items: center;
  gap: 16px;
}

.brand-logo-badge {
  width: 54px;
  height: 54px;
  background: linear-gradient(135deg, rgba(29, 104, 216, 0.2), rgba(6, 182, 212, 0.2));
  border: 1px solid rgba(59, 130, 246, 0.4);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(10px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

.brand-logo-badge img {
  width: 34px;
  height: 34px;
  object-fit: contain;
}

.brand-title-group h1 {
  font-size: 1.35rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: #ffffff;
  margin-bottom: 2px;
}

.brand-title-group p {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--cyan-accent);
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
  background: rgba(29, 104, 216, 0.15);
  border: 1px solid rgba(59, 130, 246, 0.3);
  border-radius: 100px;
  font-size: 0.78rem;
  font-weight: 600;
  color: #93c5fd;
  margin-bottom: 24px;
}

.hero-pill i {
  color: var(--cyan-accent);
}

.hero-headline {
  font-size: 2.5rem;
  font-weight: 800;
  line-height: 1.2;
  letter-spacing: -0.03em;
  color: #ffffff;
  margin-bottom: 18px;
}

.hero-headline span {
  background: linear-gradient(135deg, #60a5fa 0%, #38bdf8 50%, #2dd4bf 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.hero-desc {
  font-size: 1rem;
  line-height: 1.6;
  color: var(--text-muted);
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
  background: rgba(16, 41, 77, 0.45);
  border: 1px solid var(--border-glass);
  padding: 16px;
  border-radius: 14px;
  backdrop-filter: blur(8px);
  transition: all 0.3s ease;
}

.feature-item:hover {
  background: rgba(29, 104, 216, 0.12);
  border-color: rgba(59, 130, 246, 0.4);
  transform: translateY(-2px);
}

.feature-icon-box {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: rgba(29, 104, 216, 0.2);
  color: #60a5fa;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
}

.feature-text h4 {
  font-size: 0.88rem;
  font-weight: 700;
  color: #ffffff;
  margin-bottom: 3px;
}

.feature-text p {
  font-size: 0.75rem;
  color: var(--text-muted);
  line-height: 1.4;
  margin: 0;
}

.hero-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-top: 1px solid var(--border-glass);
  padding-top: 24px;
}

.security-badge {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.8rem;
  color: var(--text-muted);
}

.security-badge i {
  color: var(--emerald);
  font-size: 1.1rem;
}

.system-status {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.75rem;
  font-weight: 600;
  color: #34d399;
  background: rgba(16, 185, 129, 0.1);
  padding: 4px 12px;
  border-radius: 100px;
  border: 1px solid rgba(16, 185, 129, 0.25);
}

.status-dot {
  width: 7px;
  height: 7px;
  background-color: var(--emerald);
  border-radius: 50%;
  box-shadow: 0 0 10px var(--emerald);
  animation: pulseDot 2s infinite ease-in-out;
}

@keyframes pulseDot {
  0% { transform: scale(0.95); opacity: 0.8; }
  50% { transform: scale(1.3); opacity: 1; }
  100% { transform: scale(0.95); opacity: 0.8; }
}

/* RIGHT AUTH PANEL */
.auth-panel {
  flex: 0.95;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  padding: 48px;
  background: radial-gradient(circle at top right, rgba(16, 41, 77, 0.5) 0%, rgba(5, 13, 26, 0.9) 100%);
  position: relative;
}

.auth-card {
  width: 100%;
  max-width: 440px;
  background: #ffffff;
  border-radius: 24px;
  padding: 40px;
  box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.1);
  color: #1e293b;
  position: relative;
}

.mobile-logo-header {
  display: none;
  text-align: center;
  margin-bottom: 24px;
}

.mobile-logo-header img {
  width: 48px;
  height: 48px;
  object-fit: contain;
  margin-bottom: 8px;
}

.mobile-brand-title {
  font-size: 1.15rem;
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
  font-size: 0.8rem;
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
  left: 16px;
  color: #94a3b8;
  font-size: 1.1rem;
  pointer-events: none;
  transition: color 0.2s ease;
}

.input-field {
  width: 100%;
  height: 48px;
  background-color: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  padding: 0 16px 0 46px;
  font-family: inherit;
  font-size: 0.92rem;
  color: #0f172a;
  font-weight: 500;
  transition: all 0.2s ease;
  outline: none;
}

.input-field:focus {
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
  background: linear-gradient(135deg, #1d68d8 0%, #1e40af 100%);
  color: #ffffff;
  border: none;
  border-radius: 12px;
  font-family: inherit;
  font-size: 0.95rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  cursor: pointer;
  box-shadow: 0 8px 20px -4px rgba(29, 104, 216, 0.45);
  transition: all 0.25s ease;
  margin-top: 24px;
}

.btn-login-submit:hover {
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  box-shadow: 0 12px 24px -4px rgba(29, 104, 216, 0.55);
  transform: translateY(-1px);
}

.btn-login-submit:active {
  transform: translateY(1px);
  box-shadow: 0 4px 12px -2px rgba(29, 104, 216, 0.4);
}

/* Security Notice Card */
.security-card {
  margin-top: 14px;
  padding: 12px 14px;
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
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
  padding-top: 20px;
  font-size: 0.75rem;
  color: #94a3b8;
}

/* RESPONSIVE DESIGN */
@media (max-width: 1080px) {
  .hero-panel {
    padding: 40px 36px;
  }
  .auth-panel {
    max-width: 460px;
    padding: 36px 32px;
  }
}

@media (max-width: 900px) {
  .login-container {
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 20px 16px;
  }

  .hero-panel {
    display: none;
  }

  .auth-panel {
    max-width: 440px;
    border-radius: 20px;
    padding: 32px 24px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.1);
  }

  .mobile-logo-header {
    display: block;
  }
}

@media (max-width: 480px) {
  .auth-panel {
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

<!-- Animated Ambient Glows & Cyber Grid Background -->
<div class="ambient-glow glow-top-left"></div>
<div class="ambient-glow glow-bottom-right"></div>
<div class="cyber-grid"></div>

<div class="login-container">
  
  <!-- LEFT HERO PANEL (Desktop Enterprise Branding) -->
  <div class="hero-panel">
    <div class="brand-header">
      <div class="brand-logo-badge">
        <img src="<?= app_logo_url() ?>" alt="Logo Bank" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'%2338bdf8\'><path d=\'M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z\'/></svg>'">
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
        <img src="<?= app_logo_url() ?>" alt="Logo Bank" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'%231d68d8\'><path d=\'M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z\'/></svg>'">
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
              value="<?= e($username ?? '') ?>"
              required 
              autofocus 
              spellcheck="false"
              <?= $isLocked ? 'disabled' : '' ?>
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
              <?= $isLocked ? 'disabled' : '' ?>
            >
            <button 
              type="button" 
              class="password-toggle-btn" 
              id="togglePassBtn"
              onclick="togglePassword()" 
              title="Lihat / Sembunyikan Kata Sandi"
              aria-label="Toggle Password Visibility"
              <?= $isLocked ? 'disabled' : '' ?>
            >
              <i class="bi bi-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-login-submit" id="submitBtn" <?= $isLocked ? 'disabled style="opacity: 0.6; cursor: not-allowed;"' : '' ?>>
          <i class="bi <?= $isLocked ? 'bi-lock-fill' : 'bi-box-arrow-in-right' ?>" id="submitIcon"></i>
          <span id="submitText"><?= $isLocked ? 'Akses Terkunci Sementara' : 'Masuk Sekarang' ?></span>
        </button>
      </form>

      <div class="security-card">
        <i class="bi bi-shield-exclamation"></i>
        <div class="security-card-text">
          <strong>Akses Terbatas:</strong> Sistem internal ini khusus untuk staf IT dan petugas berwenang PT. BPR Mitratama Arthabuana. Segala aktivitas diawasi &amp; dicatat ke log audit sesuai standar POJK No. 75/POJK.03/2016.
        </div>
      </div>
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
  loginForm.addEventListener("submit", function() {
    if (submitBtn && !submitBtn.disabled) {
      submitBtn.disabled = true;
      submitIcon.className = "spinner-border spinner-border-sm";
      submitText.textContent = "Memverifikasi Akun...";
    }
  });
}

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
