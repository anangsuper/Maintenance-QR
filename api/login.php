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
            $baseErr = $result['error'] ?? 'Username atau kata sandi tidak valid.';
            $attempts = $failInfo['attempts'];
            $error = $baseErr . ' (Percobaan ke-' . $attempts . ' dari 5. Akun akan terkunci 15 menit jika 5 kali berturut-turut gagal sesuai standar OJK).';
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
    ? '<div class="alert-custom alert-warning-custom"><i class="bi bi-clock-history alert-icon"></i><div><strong>Sesi Telah Berakhir</strong><div class="alert-text">Waktu sesi Anda telah habis karena tidak ada aktivitas. Silakan masuk kembali.</div></div></div>'
    : '';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>Portal Masuk Petugas &bull; IT Operations Bank Mitra</title>
<link rel="manifest" href="<?= module_url('manifest.webmanifest') ?>">
<meta name="theme-color" content="#07162C">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="IT Bank Mitra">
<link rel="apple-touch-icon" href="<?= module_url('pwa_icons.php', ['size' => 192]) ?>">
<link rel="icon" type="image/png" href="<?= module_url('logo.png') ?>">

<!-- Local Vendor Assets (Zero External CDN Dependency) -->
<link href="<?= module_url('assets/vendor/bootstrap.min.css') ?>" rel="stylesheet">
<link rel="stylesheet" href="<?= module_url('assets/vendor/bootstrap-icons.min.css') ?>">

<style>
:root {
  --navy-darkest: #050E1D;
  --navy-dark: #07162C;
  --navy-medium: #0D2748;
  --navy-surface: rgba(13, 39, 72, 0.65);
  --blue-brand: #2563EB;
  --blue-hover: #1D4ED8;
  --blue-accent: #38BDF8;
  --emerald-live: #10B981;
  --text-main: #0F172A;
  --text-muted: #64748B;
  --border-light: #E2E8F0;
  --border-focus: #2563EB;
}

* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  min-height: 100vh;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  background-color: var(--navy-darkest);
  color: var(--text-main);
  display: flex;
  overflow-x: hidden;
}

.login-split-wrapper {
  display: flex;
  width: 100%;
  min-height: 100vh;
}

/* ==========================================================================
   LEFT PANEL: ENTERPRISE BANKING BRANDING & LIVE METRICS
   ========================================================================== */
.brand-visual-panel {
  flex: 1.15;
  background-color: var(--navy-dark);
  background-image: 
    radial-gradient(circle at 15% 20%, rgba(37, 99, 235, 0.22) 0%, transparent 45%),
    radial-gradient(circle at 85% 85%, rgba(56, 189, 248, 0.15) 0%, transparent 45%),
    linear-gradient(rgba(255, 255, 255, 0.035) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.035) 1px, transparent 1px);
  background-size: 100% 100%, 100% 100%, 36px 36px, 36px 36px;
  color: #FFFFFF;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 56px 64px;
  position: relative;
  overflow: hidden;
}

.brand-visual-panel::before {
  content: "";
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  width: 1px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.05) 0%, rgba(56, 189, 248, 0.3) 50%, rgba(255, 255, 255, 0.05) 100%);
}

/* Brand Header */
.brand-top-header {
  display: flex;
  align-items: center;
  gap: 18px;
  z-index: 2;
}

.brand-logo-container {
  width: 58px;
  height: 58px;
  background: rgba(255, 255, 255, 0.95);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.2);
  padding: 8px;
}

.brand-logo-container img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.brand-title-meta h1 {
  font-size: 1.28rem;
  font-weight: 800;
  letter-spacing: -0.01em;
  color: #FFFFFF;
  margin-bottom: 2px;
}

.brand-title-meta p {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--blue-accent);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin: 0;
}

/* Hero Center Body */
.brand-center-body {
  max-width: 540px;
  margin: 40px 0;
  z-index: 2;
}

.enterprise-tag {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  background: rgba(37, 99, 235, 0.15);
  border: 1px solid rgba(56, 189, 248, 0.35);
  border-radius: 100px;
  font-size: 0.78rem;
  font-weight: 600;
  color: #7DD3FC;
  margin-bottom: 22px;
}

.enterprise-tag i {
  color: #38BDF8;
}

.main-headline {
  font-size: 2.25rem;
  font-weight: 800;
  line-height: 1.25;
  letter-spacing: -0.03em;
  color: #FFFFFF;
  margin-bottom: 16px;
}

.main-headline span {
  background: linear-gradient(135deg, #60A5FA 0%, #38BDF8 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.main-description {
  font-size: 0.95rem;
  line-height: 1.6;
  color: #CBD5E1;
  margin-bottom: 0;
}


/* Brand Footer */
.brand-bottom-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  padding-top: 24px;
  font-size: 0.78rem;
  color: #94A3B8;
  z-index: 2;
}

.security-assurance {
  display: flex;
  align-items: center;
  gap: 8px;
}

.security-assurance i {
  color: var(--emerald-live);
  font-size: 1rem;
}

/* ==========================================================================
   RIGHT PANEL: CLEAN, ENTERPRISE FORM LOGIN
   ========================================================================== */
.auth-form-panel {
  flex: 0.95;
  background-color: #FFFFFF;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  padding: 48px;
  position: relative;
}

.auth-form-card {
  width: 100%;
  max-width: 440px;
}

/* Mobile Top Header (Displayed on Small Screens) */
.mobile-brand-hero {
  display: none;
  text-align: center;
  margin-bottom: 28px;
}

.mobile-brand-hero img {
  width: 54px;
  height: 54px;
  object-fit: contain;
  margin-bottom: 12px;
}

.mobile-brand-hero h2 {
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--text-main);
  margin-bottom: 2px;
}

.mobile-brand-hero p {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--blue-brand);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin: 0;
}

/* Card Header */
.auth-card-header {
  margin-bottom: 28px;
}

.role-badge-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  background: #EFF6FF;
  border: 1px solid #DBEAFE;
  border-radius: 6px;
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--blue-brand);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 12px;
}

.auth-main-title {
  font-size: 1.75rem;
  font-weight: 800;
  letter-spacing: -0.025em;
  color: var(--text-main);
  margin-bottom: 6px;
}

.auth-sub-title {
  font-size: 0.88rem;
  color: var(--text-muted);
  line-height: 1.5;
  margin: 0;
}

/* Alerts */
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
  background-color: #FEF2F2;
  border: 1px solid #FEE2E2;
  color: #991B1B;
}

.alert-success-custom {
  background-color: #F0FDF4;
  border: 1px solid #DCFCE7;
  color: #166534;
}

.alert-warning-custom {
  background-color: #FFFBEB;
  border: 1px solid #FEF3C7;
  color: #92400E;
}

.alert-icon {
  font-size: 1.15rem;
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

/* Modern Form Fields */
.form-field-group {
  margin-bottom: 20px;
}

.field-label {
  display: block;
  font-size: 0.82rem;
  font-weight: 700;
  color: #334155;
  margin-bottom: 8px;
  letter-spacing: -0.01em;
}

.input-box-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.input-icon-decor {
  position: absolute;
  left: 15px;
  color: #94A3B8;
  font-size: 1.15rem;
  pointer-events: none;
  transition: color 0.2s;
}

.input-control-styled {
  width: 100%;
  height: 48px;
  padding: 0 16px 0 46px;
  background: #FFFFFF;
  border: 1.5px solid var(--border-light);
  border-radius: 10px;
  color: var(--text-main);
  font-family: inherit;
  font-size: 0.92rem;
  font-weight: 500;
  transition: all 0.2s ease;
}

.input-control-styled:focus {
  outline: none;
  border-color: var(--blue-brand);
  background: #FFFFFF;
  box-shadow: 0 0 0 3.5px rgba(37, 99, 235, 0.15);
}

.input-control-styled:focus + .input-icon-decor,
.input-box-wrapper:focus-within .input-icon-decor {
  color: var(--blue-brand);
}

.input-control-styled::placeholder {
  color: #94A3B8;
  font-weight: 400;
}

.btn-toggle-eye {
  position: absolute;
  right: 12px;
  background: none;
  border: none;
  color: #94A3B8;
  font-size: 1.15rem;
  padding: 6px;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.btn-toggle-eye:hover {
  color: #334155;
  background-color: #F1F5F9;
}

/* Submit Action Button */
.btn-submit-banking {
  width: 100%;
  height: 50px;
  background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
  color: #FFFFFF;
  border: none;
  border-radius: 10px;
  font-family: inherit;
  font-size: 0.95rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.28);
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  margin-top: 24px;
}

.btn-submit-banking:hover {
  background: linear-gradient(135deg, #1D4ED8 0%, #1E40AF 100%);
  box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
  transform: translateY(-1px);
}

.btn-submit-banking:active {
  transform: translateY(1px);
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
}

/* Card Footer */
.auth-panel-footer {
  text-align: center;
  padding-top: 28px;
  font-size: 0.78rem;
  color: #94A3B8;
}

/* Responsive Rules */
@media (max-width: 1080px) {
  .brand-visual-panel {
    padding: 40px 36px;
  }
  .auth-form-panel {
    padding: 36px 32px;
  }
}

@media (max-width: 960px) {
  .login-split-wrapper {
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 24px 16px;
    background-color: #F8FAFC;
  }

  .brand-visual-panel {
    display: none;
  }

  .auth-form-panel {
    background: transparent;
    max-width: 440px;
    width: 100%;
    padding: 0;
  }

  .auth-form-card {
    background: #FFFFFF;
    border-radius: 20px;
    padding: 36px 28px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border-light);
  }

  .mobile-brand-hero {
    display: block;
  }
}

@media (max-width: 480px) {
  .auth-form-card {
    padding: 28px 20px;
    border-radius: 16px;
  }
  .auth-main-title {
    font-size: 1.45rem;
  }
}
</style>
</head>
<body>

<div class="login-split-wrapper">
  
  <!-- SISI KIRI: BRANDING & LIVE MONITORING STATUS PANEL -->
  <aside class="brand-visual-panel">
    <!-- Brand Header -->
    <div class="brand-top-header">
      <div class="brand-logo-container">
        <img src="<?= module_url('logo.png') ?>" alt="Logo Bank Mitra" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'%231d4ed8\'><path d=\'M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z\'/></svg>'">
      </div>
      <div class="brand-title-meta">
        <h1>PT BPR MITRATAMA ARTHABUANA</h1>
        <p>Divisi IT Operations &amp; Infrastructure</p>
      </div>
    </div>

    <!-- Center Hero Content -->
    <div class="brand-center-body">
      <div class="enterprise-tag">
        <i class="bi bi-shield-check"></i>
        <span>Sistem Operasional Pemeliharaan Perbankan</span>
      </div>
      
      <h2 class="main-headline">
        Portal Manajemen Operasional &amp; <span>Aset IT Berbasis QR</span>
      </h2>

      <p class="main-description">
        Infrastruktur terintegrasi untuk pemantauan preventif, riwayat servis berkala, pengawasan suhu ruang server, serta inventarisasi perangkat perbankan seluruh kantor cabang.
      </p>


    </div>

    <!-- Panel Footer -->
    <div class="brand-bottom-footer">
      <div class="security-assurance">
        <i class="bi bi-lock-fill"></i>
        <span>Enkripsi Bank-Grade 256-Bit SSL/TLS</span>
      </div>
      <div>
        <span>Status: <strong>Sistem Operasional Siap</strong></span>
      </div>
    </div>
  </aside>

  <!-- SISI KANAN: AREA LOGIN RESMI -->
  <main class="auth-form-panel">
    <div class="auth-form-card">
      
      <!-- Mobile Brand Header (Tampil pada layar HP / Tablet) -->
      <div class="mobile-brand-hero">
        <img src="<?= module_url('logo.png') ?>" alt="Logo Bank Mitra" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'%231d4ed8\'><path d=\'M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z\'/></svg>'">
        <h2>BPR MITRATAMA ARTHABUANA</h2>
        <p>IT Operations &amp; Infrastructure Portal</p>
      </div>

      <!-- Auth Header -->
      <div class="auth-card-header">
        <div class="role-badge-pill">
          <i class="bi bi-shield-lock"></i>
          <span>Portal Petugas IT</span>
        </div>
        <h2 class="auth-main-title">Selamat Datang</h2>
        <p class="auth-sub-title">Masukkan kredensial akun IT Anda untuk mengakses dashboard.</p>
      </div>

      <!-- Flash & Error Messages -->
      <?= $successHtml ?>
      <?= $expiredHtml ?>
      <?= $errorHtml ?>

      <!-- Form Login Kredensial -->
      <form method="post" id="loginForm" autocomplete="on">
        <div class="form-field-group">
          <label class="field-label" for="inputUser">Username / ID Petugas</label>
          <div class="input-box-wrapper">
            <i class="bi bi-person input-icon-decor"></i>
            <input 
              type="text" 
              class="input-control-styled" 
              id="inputUser" 
              name="username" 
              placeholder="Contoh: admin / teknisi" 
              value="<?= e($username ?? '') ?>"
              required 
              autofocus 
              autocomplete="username"
              spellcheck="false"
              enterkeyhint="next"
            >
          </div>
        </div>

        <div class="form-field-group">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="field-label mb-0" for="inputPass">Kata Sandi</label>
          </div>
          <div class="input-box-wrapper">
            <i class="bi bi-key input-icon-decor"></i>
            <input 
              type="password" 
              class="input-control-styled" 
              id="inputPass" 
              name="password" 
              placeholder="Masukkan kata sandi akun..." 
              required
              autocomplete="current-password"
              enterkeyhint="go"
              style="padding-right: 48px;"
            >
            <button 
              type="button" 
              class="btn-toggle-eye" 
              id="togglePassBtn"
              onclick="togglePassword()" 
              title="Lihat / Sembunyikan Kata Sandi"
              aria-label="Toggle Password Visibility"
            >
              <i class="bi bi-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-submit-banking" id="submitBtn">
          <i class="bi bi-box-arrow-in-right fs-5" id="submitIcon"></i>
          <span id="submitText">Masuk Sekarang</span>
        </button>
      </form>

      <!-- Panel Footer -->
      <div class="auth-panel-footer">
        <div>&copy; <?= date('Y') ?> PT BPR Mitratama Arthabuana</div>
        <div style="font-size: 0.72rem; color: #94A3B8; margin-top: 4px;">Divisi IT Operations &amp; Digital Banking</div>
      </div>
    </div>
  </main>

</div>

<!-- Bootstrap 5 Bundle JS (Modal & Interactive Controls) -->
<script src="<?= module_url('assets/vendor/bootstrap.bundle.min.js') ?>"></script>

<script>
// Fungsi Lihat / Sembunyikan Kata Sandi
function togglePassword() {
  const inp = document.getElementById("inputPass");
  const ico = document.getElementById("eyeIcon");
  if (!inp || !ico) return;
  if (inp.type === "password") {
    inp.type = "text";
    ico.className = "bi bi-eye-slash";
  } else {
    inp.type = "password";
    ico.className = "bi bi-eye";
  }
}

// Efek Loading Saat Formulir Dikirim
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
      submitBtn.style.pointerEvents = "none";
    }
  });
}

// Reset status tombol jika user menekan Back/Forward browser
window.addEventListener("pageshow", function() {
  if (submitBtn) {
    submitBtn.removeAttribute("disabled");
    submitBtn.style.pointerEvents = "";
    submitBtn.style.opacity = "";
    if (submitText) submitText.textContent = "Masuk Sekarang";
    if (submitIcon) submitIcon.className = "bi bi-box-arrow-in-right fs-5";
  }
});

// Countdown Timer jika Akun Terkunci Sementara (Standar Keamanan OJK)
const lockRemainingSecs = <?= (int)$lockRemainingSeconds ?>;
if (lockRemainingSecs > 0) {
  let remaining = lockRemainingSecs;
  const timerSpan = document.getElementById("lockTimer");
  const inpUser = document.getElementById("inputUser");
  const inpPass = document.getElementById("inputPass");
  const toggleBtn = document.getElementById("togglePassBtn");

  function updateLockCountdown() {
    if (remaining <= 0) {
      if (timerSpan) timerSpan.innerText = "Waktu penguncian selesai. Silakan muat ulang halaman.";
      if (submitBtn) {
        submitBtn.removeAttribute("disabled");
        submitBtn.style.opacity = "1";
        submitBtn.style.cursor = "pointer";
        submitBtn.style.pointerEvents = "";
      }
      if (submitText) submitText.innerText = "Masuk Sekarang";
      if (submitIcon) submitIcon.className = "bi bi-box-arrow-in-right fs-5";
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

// Bersihkan timestamp aktivitas lokal saat berada di login
try {
  localStorage.removeItem("app_last_active");
} catch(e) {}

// Auto-focus username field begitu halaman selesai dimuat
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

// PWA Service Worker Registration
if ("serviceWorker" in navigator) {
  window.addEventListener("load", function() {
    navigator.serviceWorker.getRegistrations().then(function(regs){ for(var r of regs){ r.unregister(); } }); if(window.caches){ caches.keys().then(function(k){ for(var x of k){ caches.delete(x); } }); }
      .catch(function(err) {
        console.warn("SW reg error:", err);
      });
  });
}
</script>
</body>
</html>
