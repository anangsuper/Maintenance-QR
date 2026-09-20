<?php
require __DIR__ . '/bootstrap.php';

// =========================================================================
// HANDLER AJAX: BIOMETRIC LOGIN (FACE RECOGNITION AI & PASSKEY)
// =========================================================================
$ajaxAction = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

if ($ajaxAction !== '') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');

    // 1. Ambil daftar teknisi yang memiliki data wajah terdaftar untuk matching client-side
    if ($ajaxAction === 'get_bio_users') {
        $allUsers = get_user_list(true);
        $enrolled = [];
        foreach ($allUsers as $u) {
            $status = strtolower(trim((string)($u['status'] ?? 'Aktif')));
            if ($status === 'nonaktif') continue;

            $fStat = strtolower(trim((string)($u['face_status'] ?? '')));
            $fDesc = trim((string)($u['face_descriptor'] ?? ''));

            // Izinkan semua akun aktif yang memiliki descriptor wajah (kecuali ditolak/rejected)
            if ($fDesc !== '' && $fStat !== 'rejected' && $fStat !== 'ditolak') {
                $descArr = json_decode($fDesc, true);
                if (is_array($descArr) && count($descArr) >= 64) {
                    $enrolled[] = [
                        'id' => (int)$u['id'],
                        'nama' => (string)$u['nama'],
                        'username' => (string)$u['username'],
                        'role' => (string)$u['role'],
                        'descriptor' => $descArr
                    ];
                }
            }
        }
        echo json_encode(['success' => true, 'users' => $enrolled], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. Verifikasi dan Login menggunakan Biometrik Wajah
    if ($ajaxAction === 'biometric_face_login') {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            $body = $_POST;
        }

        $userId = (int)($body['user_id'] ?? 0);
        $submittedDesc = $body['descriptor'] ?? null;
        if (is_string($submittedDesc)) {
            $submittedDesc = json_decode($submittedDesc, true);
        }

        if ($userId <= 0 || !is_array($submittedDesc) || count($submittedDesc) < 64) {
            echo json_encode(['success' => false, 'error' => 'Data biometrik wajah tidak valid atau tidak lengkap.']);
            exit;
        }

        $user = get_user_by_id($userId, true);
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'Akun pengguna tidak ditemukan.']);
            exit;
        }

        if (strcasecmp((string)($user['status'] ?? 'Aktif'), 'Nonaktif') === 0) {
            echo json_encode(['success' => false, 'error' => 'Akun Anda dinonaktifkan. Silakan hubungi Administrator.']);
            exit;
        }

        $storedDesc = trim((string)($user['face_descriptor'] ?? ''));
        if ($storedDesc === '') {
            echo json_encode(['success' => false, 'error' => 'Biometrik wajah belum terdaftar untuk akun ini.']);
            exit;
        }

        // Validasi jarak Euclidean di sisi server (mencegah manipulasi client-side)
        $storedArr = json_decode($storedDesc, true);
        if (!is_array($storedArr) || count($storedArr) < 64) {
            echo json_encode(['success' => false, 'error' => 'Format sampel wajah server tidak valid.']);
            exit;
        }

        $sum = 0.0;
        $cnt = min(count($storedArr), count($submittedDesc));
        for ($i = 0; $i < $cnt; $i++) {
            $d = (float)$storedArr[$i] - (float)$submittedDesc[$i];
            $sum += $d * $d;
        }
        $distance = sqrt($sum);

        // Ambang batas toleransi Euclidean distance (<= 0.55)
        if ($distance > 0.55) {
            record_audit_log('LOGIN_FAILED_BIOMETRIC', 'KEAMANAN', $userId, (string)$user['username'], 'Gagal login biometrik wajah: deviasi jarak ' . round($distance, 3), [
                'user_name' => (string)$user['nama'],
                'user_role' => (string)$user['role']
            ]);
            echo json_encode(['success' => false, 'error' => 'Verifikasi biometrik tidak cocok (tingkat kesamaan kurang).']);
            exit;
        }

        // Login Berhasil!
        login_user_session($user);
        record_audit_log('LOGIN_SUCCESS_BIOMETRIC', 'KEAMANAN', $userId, (string)$user['username'], 'Login berhasil via Biometrik Wajah AI (Jarak: ' . round($distance, 3) . ')', [
            'user_name' => (string)$user['nama'],
            'user_role' => (string)$user['role'],
            'user_id' => $userId
        ]);

        $redirect = safe_redirect_url($_SESSION['after_login'] ?? null, module_url('dashboard.php'));
        unset($_SESSION['after_login']);

        echo json_encode([
            'success' => true,
            'message' => 'Autentikasi wajah berhasil! Selamat datang, ' . $user['nama'],
            'user' => [
                'id' => $userId,
                'nama' => (string)$user['nama'],
                'username' => (string)$user['username'],
                'role' => (string)$user['role']
            ],
            'redirect' => $redirect
        ]);
        exit;
    }
}

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
        record_audit_log('LOGIN_SUCCESS', 'KEAMANAN', (int)($_SESSION['user_id'] ?? 0), $username, 'Login berhasil ke sistem', [
            'user_name' => (string)($_SESSION['nama'] ?? $username),
            'user_role' => (string)($_SESSION['role'] ?? 'teknisi'),
            'user_id' => (int)($_SESSION['user_id'] ?? 0)
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

/* Biometric Divider */
.bio-auth-divider {
  display: flex;
  align-items: center;
  text-align: center;
  margin: 22px 0 16px 0;
  color: #94a3b8;
  font-size: 0.76rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.bio-auth-divider::before,
.bio-auth-divider::after {
  content: '';
  flex: 1;
  border-bottom: 1px solid #e2e8f0;
}

.bio-auth-divider span {
  padding: 0 12px;
}

/* Biometric Buttons Grid */
.bio-buttons-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 22px;
}

.btn-bio-card {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 12px;
  background-color: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  color: #1e293b;
  font-family: inherit;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-bio-card i {
  font-size: 1.15rem;
}

.btn-bio-card:hover {
  background-color: #eff6ff;
  border-color: #bfdbfe;
  color: #1d4ed8;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.08);
}

.btn-bio-card:active {
  transform: translateY(0);
}

/* Security Notice Card */
.security-card {
  margin-top: 10px;
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

/* BIOMETRIC SCANNER MODAL */
.bio-login-modal-wrapper {
  position: relative;
  width: 320px;
  height: 380px;
  max-width: 100%;
  border-radius: 20px;
  overflow: hidden;
  background: #0f172a;
  box-shadow: 0 10px 25px rgba(0,0,0,0.3);
  margin: 0 auto;
}

.bio-login-video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transform: scaleX(-1);
}

.bio-login-oval {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 190px;
  height: 240px;
  border: 3px dashed #38bdf8;
  border-radius: 50%;
  box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.65);
  pointer-events: none;
  transition: all 0.3s ease;
}

.bio-login-oval.active {
  border-color: #22c55e;
  border-style: solid;
  box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.4), 0 0 25px rgba(34, 197, 94, 0.6);
}

.bio-login-scanline {
  position: absolute;
  top: 25%;
  left: calc(50% - 95px);
  width: 190px;
  height: 3px;
  background: linear-gradient(90deg, transparent, #38bdf8, transparent);
  animation: bioLoginScan 2s infinite ease-in-out;
  pointer-events: none;
}

@keyframes bioLoginScan {
  0% { top: 20%; opacity: 0; }
  50% { opacity: 1; }
  100% { top: 80%; opacity: 0; }
}

.bio-login-success {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(15, 23, 42, 0.94);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10;
  text-align: center;
  padding: 20px;
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
  .bio-buttons-grid {
    grid-template-columns: 1fr;
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
            <i class="bi bi-person-bounding-box"></i>
          </div>
          <div class="feature-text">
            <h4>AI Biometrics</h4>
            <p>Verifikasi wajah & passkey untuk keamanan presensi dan audit.</p>
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
        <p class="auth-subtitle">Gunakan kredensial akun IT atau autentikasi biometrik Anda.</p>
      </div>

      <?= $successHtml ?>
      <?= $expiredHtml ?>
      <?= $errorHtml ?>

      <div id="bioAlertPlaceholder"></div>

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

      <!-- BIOMETRIC OPTIONS DIVIDER -->
      <div class="bio-auth-divider">
        <span>Atau Masuk dengan Biometrik</span>
      </div>

      <!-- BIOMETRIC BUTTONS GRID -->
      <div class="bio-buttons-grid">
        <button type="button" class="btn-bio-card" onclick="openBioFaceLoginModal()">
          <i class="bi bi-camera-video-fill text-primary"></i>
          <span>Wajah (Kamera AI)</span>
        </button>

        <button type="button" class="btn-bio-card" onclick="startPasskeyLogin()">
          <i class="bi bi-fingerprint text-success"></i>
          <span>Face ID / Touch ID</span>
        </button>
      </div>

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

<!-- ========================================================================= -->
<!-- MODAL SCANNER BIOMETRIC FACE LOGIN -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalBioFaceLogin" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-dark text-white border-0 py-3">
        <div class="d-flex align-items-center gap-2">
          <div class="p-2 bg-primary bg-opacity-25 rounded-circle text-primary fs-5">
            <i class="bi bi-person-bounding-box"></i>
          </div>
          <div>
            <h6 class="modal-title fw-bold mb-0">Login Biometrik Wajah AI</h6>
            <div class="text-white-50 small" style="font-size: 0.72rem;">Arahkan wajah Anda ke kamera depan HP / Webcam</div>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" onclick="closeBioFaceLoginModal()"></button>
      </div>

      <div class="modal-body p-3 p-md-4 text-center bg-light">
        <!-- Scanner Box -->
        <div class="bio-login-modal-wrapper mb-3">
          <video id="bioLoginVideo" class="bio-login-video" autoplay playsinline webkit-playsinline muted></video>
          <div id="bioLoginOval" class="bio-login-oval"></div>
          <div id="bioLoginScanline" class="bio-login-scanline d-none"></div>

          <!-- Success Overlay -->
          <div id="bioLoginSuccessOverlay" class="bio-login-success d-none">
            <div class="text-center p-3 text-white">
              <div class="display-4 text-success mb-2">
                <i class="bi bi-check-circle-fill"></i>
              </div>
              <h5 class="fw-bold mb-1" id="bioLoginSuccessName">Nama Teknisi</h5>
              <div class="badge bg-success bg-opacity-75 fs-6 mb-2" id="bioLoginSuccessConf">98% Cocok</div>
              <div class="text-white-50 small">Autentikasi Berhasil! Mengalihkan ke dashboard...</div>
            </div>
          </div>
        </div>

        <!-- Status Box -->
        <div id="bioLoginStatusBox" class="alert alert-info py-2 px-3 small fw-semibold mb-2">
          <span class="spinner-border spinner-border-sm me-2 text-primary"></span>
          Menyiapkan modul AI & kamera...
        </div>

        <!-- Steady Hold Progress Bar -->
        <div class="progress mb-3 d-none" id="bioLoginHoldProgress" style="height: 6px;">
          <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" id="bioLoginHoldProgressBar" style="width: 0%"></div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-2">
          <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="closeBioFaceLoginModal()">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Password
          </button>
          <button type="button" class="btn btn-link btn-sm text-primary text-decoration-none" onclick="startPasskeyLogin()">
            <i class="bi bi-fingerprint me-1"></i> Coba Sensor HP
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap 5 Bundle JS (Modal & Interactive Controls) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Load face-api.js dari CDN -->
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.min.js"></script>

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
    if (submitBtn) {
      submitBtn.disabled = true;
      submitIcon.className = "spinner-border spinner-border-sm";
      submitText.textContent = "Memverifikasi Akun...";
    }
  });
}

function showBioAlert(msg, type = "danger") {
  const pl = document.getElementById("bioAlertPlaceholder");
  if (!pl) return;
  const icon = (type === "success") ? "bi-check-circle-fill" : "bi-shield-x";
  pl.innerHTML = `<div class="alert-custom alert-${type}-custom"><i class="bi ${icon} alert-icon"></i><div><strong>${type === 'success' ? 'Informasi' : 'Autentikasi Biometrik'}</strong><div class="alert-text">${msg}</div></div></div>`;
}

// =========================================================================
// 1. WEBAUTHN / PASSKEY / FINGERPRINT / FACE ID NATIVE SENSOR LOGIN
// =========================================================================
function base64urlToUint8Array(base64url) {
  const padding = "=".repeat((4 - (base64url.length % 4)) % 4);
  const base64 = (base64url + padding).replace(/\-/g, "+").replace(/_/g, "/");
  const rawData = atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

function arrayBufferToBase64Url(buffer) {
  const bytes = new Uint8Array(buffer);
  let binary = "";
  for (let i = 0; i < bytes.byteLength; i++) {
    binary += String.fromCharCode(bytes[i]);
  }
  return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=/g, "");
}

async function startPasskeyLogin() {
  closeBioFaceLoginModal();
  if (!window.PublicKeyCredential) {
    showBioAlert("Perangkat atau browser Anda belum mendukung autentikasi sensor biometrik WebAuthn.");
    return;
  }

  showBioAlert("Menghubungkan ke sensor biometrik perangkat...", "warning");

  try {
    const optRes = await fetch("<?= module_url('webauthn_handler.php', ['action' => 'auth_options']) ?>");
    const optData = await optRes.json();
    if (!optData.success || !optData.options) {
      throw new Error(optData.error || "Gagal menyiapkan tantangan biometrik.");
    }

    const options = optData.options;
    options.challenge = base64urlToUint8Array(options.challenge);
    if (options.allowCredentials && options.allowCredentials.length > 0) {
      options.allowCredentials = options.allowCredentials.map(c => ({
        type: c.type,
        id: base64urlToUint8Array(c.id)
      }));
    }

    // Panggil dialog sensor bawaan HP / Windows Hello
    const assertion = await navigator.credentials.get({ publicKey: options });
    if (!assertion) {
      throw new Error("Autentikasi biometrik dibatalkan.");
    }

    const credentialData = {
      id: assertion.id,
      rawId: arrayBufferToBase64Url(assertion.rawId),
      response: {
        authenticatorData: arrayBufferToBase64Url(assertion.response.authenticatorData),
        clientDataJSON: arrayBufferToBase64Url(assertion.response.clientDataJSON),
        signature: arrayBufferToBase64Url(assertion.response.signature),
        userHandle: assertion.response.userHandle ? arrayBufferToBase64Url(assertion.response.userHandle) : null
      }
    };

    const verifyRes = await fetch("<?= module_url('webauthn_handler.php', ['action' => 'auth_verify']) ?>", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(credentialData)
    });

    const verifyData = await verifyRes.json();
    if (verifyData.success) {
      showBioAlert("Login Berhasil via Sensor Biometrik! Mengalihkan...", "success");
      setTimeout(() => {
        window.location.href = verifyData.redirect || "<?= module_url('dashboard.php') ?>";
      }, 500);
    } else {
      showBioAlert(verifyData.error || "Verifikasi sensor biometrik gagal.");
    }
  } catch (err) {
    console.warn("Passkey error:", err);
    if (err.name !== "NotAllowedError") {
      showBioAlert(err.message || "Gagal memproses autentikasi biometrik.");
    }
  }
}

// =========================================================================
// 2. FACE RECOGNITION AI CAMERA LOGIN
// =========================================================================
let bioLoginModelsLoaded = false;
let bioLoginModelsLoading = false;
let bioLoginVideoStream = null;
let bioLoginTrackingTimer = null;
let bioLoginCompleted = false;
let bioLoginModalInstance = null;
let bioLoginFaceHoldFrames = 0;
let bioLoginEnrolledUsers = [];
const BIO_HOLD_REQUIRED = 8; // ~0.4-0.6 detik tahan posisi wajah
const MODEL_URLS = [
  "https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/",
  "https://unpkg.com/@vladmandic/face-api@1.7.12/model/",
  "https://raw.githubusercontent.com/vladmandic/face-api/master/model/"
];

async function loadBioLoginModels() {
  if (bioLoginModelsLoaded) return true;
  if (bioLoginModelsLoading) return true;
  bioLoginModelsLoading = true;
  const statusBox = document.getElementById("bioLoginStatusBox");

  // Tunggu pustaka face-api.js jika masih diunduh browser
  let retries = 0;
  while (typeof faceapi === "undefined" && retries < 25) {
    await new Promise(r => setTimeout(r, 200));
    retries++;
  }

  if (typeof faceapi === "undefined") {
    bioLoginModelsLoading = false;
    if (statusBox) {
      statusBox.className = "alert alert-danger py-2 px-3 small mb-2";
      statusBox.innerHTML = '<i class="bi bi-x-circle me-1"></i> Library AI gagal dimuat. Periksa koneksi internet.';
    }
    return false;
  }

  try {
    if (faceapi.tf) {
      try {
        await faceapi.tf.setBackend("webgl");
        await faceapi.tf.ready();
      } catch (e) {
        try {
          await faceapi.tf.setBackend("cpu");
          await faceapi.tf.ready();
        } catch(e2) {}
      }
    }

    let loaded = false;
    for (const url of MODEL_URLS) {
      try {
        await Promise.all([
          faceapi.nets.tinyFaceDetector.loadFromUri(url),
          faceapi.nets.faceLandmark68TinyNet.loadFromUri(url).catch(() => faceapi.nets.faceLandmark68Net.loadFromUri(url)),
          faceapi.nets.faceRecognitionNet.loadFromUri(url)
        ]);
        loaded = true;
        break;
      } catch(mErr) {
        console.warn("Mencoba CDN model berikutnya...", mErr);
      }
    }

    if (!loaded) {
      throw new Error("Tidak dapat mengunduh model face-api dari CDN.");
    }

    bioLoginModelsLoaded = true;
    bioLoginModelsLoading = false;
    if (statusBox && !bioLoginCompleted) {
      if (bioLoginEnrolledUsers.length > 0) {
        statusBox.className = "alert alert-success py-2 px-3 small fw-semibold mb-2";
        statusBox.innerHTML = '<i class="bi bi-check-circle me-1"></i> Modul AI Siap. Posisikan wajah di oval.';
      } else {
        statusBox.className = "alert alert-warning py-2 px-3 small fw-semibold mb-2";
        statusBox.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> Belum ada data wajah terdaftar. Silakan login manual dahulu.';
      }
    }
    return true;
  } catch (err) {
    console.error("Gagal muat model face-api:", err);
    bioLoginModelsLoading = false;
    if (statusBox) {
      statusBox.className = "alert alert-danger py-2 px-3 small mb-2";
      statusBox.innerHTML = '<i class="bi bi-x-circle me-1"></i> Gagal memuat modul AI. Periksa koneksi internet.';
    }
    return false;
  }
}

async function fetchBioUsers() {
  const statusBox = document.getElementById("bioLoginStatusBox");
  try {
    const res = await fetch("<?= module_url('login.php', ['action' => 'get_bio_users']) ?>");
    const data = await res.json();
    if (data.success && Array.isArray(data.users)) {
      bioLoginEnrolledUsers = data.users.map(u => ({
        id: u.id,
        nama: u.nama,
        username: u.username,
        role: u.role,
        descriptor: new Float32Array(u.descriptor)
      }));

      if (bioLoginEnrolledUsers.length === 0 && statusBox) {
        statusBox.className = "alert alert-warning py-2 px-3 small fw-semibold mb-2";
        statusBox.innerHTML = '<i class="bi bi-info-circle me-1"></i> Belum ada akun teknisi dengan biometrik wajah terdaftar. Silakan masuk via password.';
      }
    }
  } catch (e) {
    console.warn("Fetch bio users error:", e);
  }
}

function calcEuclideanDist(a, b) {
  if (!a || !b || a.length !== b.length) return 999;
  let sum = 0;
  for (let i = 0; i < a.length; i++) {
    const d = a[i] - b[i];
    sum += d * d;
  }
  return Math.sqrt(sum);
}

function findBestMatchedUser(queryVec) {
  let bestDist = 999;
  let bestUser = null;
  for (let i = 0; i < bioLoginEnrolledUsers.length; i++) {
    const u = bioLoginEnrolledUsers[i];
    const dist = calcEuclideanDist(queryVec, u.descriptor);
    if (dist < bestDist) {
      bestDist = dist;
      bestUser = u;
    }
  }
  return { user: bestUser, dist: bestDist };
}

function openBioFaceLoginModal() {
  const modalEl = document.getElementById("modalBioFaceLogin");
  if (!modalEl) return;

  bioLoginCompleted = false;
  bioLoginFaceHoldFrames = 0;

  document.getElementById("bioLoginSuccessOverlay").classList.add("d-none");
  document.getElementById("bioLoginScanline").classList.add("d-none");
  document.getElementById("bioLoginOval").classList.remove("active");
  const holdBar = document.getElementById("bioLoginHoldProgressBar");
  if (holdBar) holdBar.style.width = "0%";
  const holdProgress = document.getElementById("bioLoginHoldProgress");
  if (holdProgress) holdProgress.classList.add("d-none");

  const statusBox = document.getElementById("bioLoginStatusBox");
  if (statusBox) {
    statusBox.className = "alert alert-info py-2 px-3 small fw-semibold mb-2";
    statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-primary"></span> Menyiapkan kamera & modul AI...';
  }

  // Tampilkan modal (Bootstrap 5 atau Fallback CSS)
  try {
    if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
      bioLoginModalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
      bioLoginModalInstance.show();
    } else {
      modalEl.classList.add("show");
      modalEl.style.display = "block";
    }
  } catch(e) {
    modalEl.classList.add("show");
    modalEl.style.display = "block";
  }

  loadBioLoginModels();
  fetchBioUsers();
  startBioLoginCamera();
}

function closeBioFaceLoginModal() {
  bioLoginCompleted = true;
  if (bioLoginTrackingTimer) {
    cancelAnimationFrame(bioLoginTrackingTimer);
    bioLoginTrackingTimer = null;
  }
  if (bioLoginVideoStream) {
    try {
      bioLoginVideoStream.getTracks().forEach(t => t.stop());
    } catch (e) {}
    bioLoginVideoStream = null;
  }
  const modalEl = document.getElementById("modalBioFaceLogin");
  if (bioLoginModalInstance) {
    bioLoginModalInstance.hide();
  } else if (modalEl) {
    modalEl.classList.remove("show");
    modalEl.style.display = "none";
  }
}

async function startBioLoginCamera() {
  const video = document.getElementById("bioLoginVideo");
  const statusBox = document.getElementById("bioLoginStatusBox");

  try {
    if (statusBox && !bioLoginModelsLoaded) {
      statusBox.className = "alert alert-info py-2 px-3 small fw-semibold mb-2";
      statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-info"></span> Mengaktifkan kamera depan...';
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      throw new Error("Akses kamera memerlukan koneksi aman (HTTPS atau localhost) pada browser ini.");
    }

    video.setAttribute("playsinline", "");
    video.setAttribute("webkit-playsinline", "");

    try {
      bioLoginVideoStream = await navigator.mediaDevices.getUserMedia({
        video: {
          facingMode: "user",
          width: { ideal: 1280, min: 480 },
          height: { ideal: 720, min: 360 }
        },
        audio: false
      });
    } catch(eMin) {
      // Fallback untuk webcam beresolusi standar
      bioLoginVideoStream = await navigator.mediaDevices.getUserMedia({
        video: true,
        audio: false
      });
    }

    video.srcObject = bioLoginVideoStream;
    await video.play();

    document.getElementById("bioLoginScanline").classList.remove("d-none");
    if (statusBox && bioLoginModelsLoaded) {
      statusBox.className = "alert alert-primary py-2 px-3 small fw-semibold mb-2";
      statusBox.innerHTML = '<i class="bi bi-person-bounding-box me-1"></i> Arahkan wajah ke lingkaran oval (Tahan 1 detik).';
    }

    startBioLoginTracking();
  } catch (err) {
    console.error("Akses kamera gagal:", err);
    if (statusBox) {
      statusBox.className = "alert alert-danger py-2 px-3 small mb-2";
      statusBox.innerHTML = '<i class="bi bi-camera-video-off me-1"></i> Kamera tidak dapat diakses: ' + (err.message || "Periksa izin browser.");
    }
  }
}

async function processFaceLoginSubmit(user, dist, descriptor) {
  bioLoginCompleted = true;
  const statusBox = document.getElementById("bioLoginStatusBox");
  if (statusBox) {
    statusBox.className = "alert alert-warning py-2 px-3 small fw-bold mb-2";
    statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-warning"></span> Wajah cocok (' + user.nama + '). Mengautentikasi akun...';
  }

  try {
    const res = await fetch("<?= module_url('login.php', ['action' => 'biometric_face_login']) ?>", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        user_id: user.id,
        descriptor: Array.from(descriptor)
      })
    });

    const data = await res.json();
    if (data.success) {
      let conf = Math.round((1.0 - (dist / 0.60)) * 100);
      if (conf > 99) conf = 99;
      if (conf < 75) conf = 75;

      const nameEl = document.getElementById("bioLoginSuccessName");
      if (nameEl) nameEl.textContent = user.nama;
      const confEl = document.getElementById("bioLoginSuccessConf");
      if (confEl) confEl.textContent = conf + "% Cocok (Terverifikasi)";
      const overlay = document.getElementById("bioLoginSuccessOverlay");
      if (overlay) overlay.classList.remove("d-none");

      if (bioLoginVideoStream) {
        try { bioLoginVideoStream.getTracks().forEach(t => t.stop()); } catch (e) {}
      }

      setTimeout(() => {
        window.location.href = data.redirect || "<?= module_url('dashboard.php') ?>";
      }, 700);
    } else {
      bioLoginCompleted = false;
      if (statusBox) {
        statusBox.className = "alert alert-danger py-2 px-3 small fw-bold mb-2";
        statusBox.innerHTML = '<i class="bi bi-x-circle me-1"></i> ' + (data.error || "Verifikasi biometrik gagal.");
      }
    }
  } catch (err) {
    bioLoginCompleted = false;
    if (statusBox) {
      statusBox.className = "alert alert-danger py-2 px-3 small mb-2";
      statusBox.innerHTML = '<i class="bi bi-x-circle me-1"></i> Gagal terhubung ke server autentikasi.';
    }
  }
}

let isBioTrackingBusy = false;
function startBioLoginTracking() {
  if (bioLoginTrackingTimer) cancelAnimationFrame(bioLoginTrackingTimer);
  const video = document.getElementById("bioLoginVideo");
  const oval = document.getElementById("bioLoginOval");
  const statusBox = document.getElementById("bioLoginStatusBox");
  const holdProgress = document.getElementById("bioLoginHoldProgress");
  const holdProgressBar = document.getElementById("bioLoginHoldProgressBar");

  const useTinyLandmarks = !!(faceapi.nets.faceLandmark68TinyNet && faceapi.nets.faceLandmark68TinyNet.isLoaded);
  const detectorOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.28 });

  async function trackingLoop() {
    if (bioLoginCompleted || !video || !video.videoWidth || video.paused || video.ended) {
      if (!bioLoginCompleted) {
        bioLoginTrackingTimer = requestAnimationFrame(trackingLoop);
      }
      return;
    }

    if (!bioLoginModelsLoaded || typeof faceapi === "undefined") {
      bioLoginTrackingTimer = requestAnimationFrame(trackingLoop);
      return;
    }

    if (bioLoginEnrolledUsers.length === 0) {
      bioLoginTrackingTimer = requestAnimationFrame(trackingLoop);
      return;
    }

    if (isBioTrackingBusy) {
      bioLoginTrackingTimer = requestAnimationFrame(trackingLoop);
      return;
    }
    isBioTrackingBusy = true;

    try {
      const detection = await faceapi.detectSingleFace(video, detectorOptions)
        .withFaceLandmarks(useTinyLandmarks)
        .withFaceDescriptor();

      if (detection && detection.descriptor) {
        if (oval) oval.classList.add("active");

        const match = findBestMatchedUser(detection.descriptor);
        if (match.user && match.dist <= 0.55) {
          bioLoginFaceHoldFrames++;
          if (holdProgress) holdProgress.classList.remove("d-none");
          const pct = Math.min(100, Math.round((bioLoginFaceHoldFrames / BIO_HOLD_REQUIRED) * 100));
          if (holdProgressBar) holdProgressBar.style.width = pct + "%";

          if (statusBox) {
            statusBox.className = "alert alert-info py-2 px-3 small fw-bold mb-2";
            statusBox.innerHTML = '<i class="bi bi-person-check-fill me-1"></i> Wajah dikenali (' + match.user.nama + ')! Tahan (' + pct + '%)...';
          }

          if (bioLoginFaceHoldFrames >= BIO_HOLD_REQUIRED) {
            await processFaceLoginSubmit(match.user, match.dist, detection.descriptor);
            isBioTrackingBusy = false;
            return;
          }
        } else {
          bioLoginFaceHoldFrames = Math.max(0, bioLoginFaceHoldFrames - 1);
          if (holdProgressBar) holdProgressBar.style.width = "0%";
          if (statusBox) {
            statusBox.className = "alert alert-warning py-2 px-3 small fw-semibold mb-2";
            statusBox.innerHTML = '<i class="bi bi-person-exclamation me-1"></i> Wajah belum cocok dengan akun teknisi. Posisikan wajah tegak.';
          }
        }
      } else {
        if (oval) oval.classList.remove("active");
        bioLoginFaceHoldFrames = Math.max(0, bioLoginFaceHoldFrames - 1);
        if (holdProgressBar) holdProgressBar.style.width = "0%";
      }
    } catch (err) {
      console.warn("Bio login tracking error:", err);
    } finally {
      isBioTrackingBusy = false;
    }

    bioLoginTrackingTimer = requestAnimationFrame(trackingLoop);
  }

  bioLoginTrackingTimer = requestAnimationFrame(trackingLoop);
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
