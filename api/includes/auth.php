<?php
if (!function_exists('base64url_encode')) {
    function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('base64url_decode')) {
    function base64url_decode(string $data): string {
        return (string)base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}

function set_auth_cookie(array $userData, int $lifetime = 0): void {
    $uid = (int)($userData['id'] ?? $userData['user_id'] ?? 0);
    $nama = (string)($userData['nama'] ?? $userData['name'] ?? '');
    $username = (string)($userData['username'] ?? '');
    $role = (string)($userData['role'] ?? 'teknisi');

    if ($uid <= 0 && $username === '') return;

    $idleTimeout = (int)cfg('session_timeout', envv('SESSION_TIMEOUT', '7200')); // Idle timeout 2 jam
    $payload = json_encode([
        'uid' => $uid,
        'nama' => $nama,
        'username' => $username,
        'role' => $role,
        'exp' => time() + $idleTimeout,
    ], JSON_UNESCAPED_UNICODE);

    $sig = hash_hmac('sha256', $payload, app_auth_secret());
    $cookieVal = base64url_encode($payload) . '.' . $sig;

    // expires = 0 (Session Cookie): otomatis dihapus saat browser ditutup.
    // Selama browser masih terbuka, cookie ini memastikan navigasi antar-halaman
    // di serverless / Vercel tetap login dan tidak ter-logout tiba-tiba.
    setcookie('_auth_session', $cookieVal, [
        'expires' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function restore_auth_from_cookie(): bool {
    // Jika $_SESSION sudah punya data valid, sesi sudah aktif
    if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
        return true;
    }

    $raw = $_COOKIE['_auth_session'] ?? '';
    if (!$raw || !str_contains($raw, '.')) return false;

    [$b64Payload, $sig] = explode('.', $raw, 2);
    $payload = base64url_decode($b64Payload);
    if (!$payload) return false;

    $expectedSig = hash_hmac('sha256', $payload, app_auth_secret());
    if (!hash_equals($expectedSig, $sig)) return false;

    $data = json_decode($payload, true);
    if (!is_array($data)) return false;

    if (!empty($data['exp']) && $data['exp'] < time()) {
        logout_user();
        return false;
    }

    $uid = (int)($data['uid'] ?? 0);
    if ($uid <= 0 && empty($data['username'])) {
        return false;
    }

    // Pulihkan ke sesi serverless saat ini
    $_SESSION['user_id'] = $uid;
    $_SESSION['nama'] = (string)($data['nama'] ?? '');
    $_SESSION['username'] = (string)($data['username'] ?? '');
    $_SESSION['role'] = (string)($data['role'] ?? 'teknisi');
    $_SESSION['last_activity'] = time();

    return true;
}

// Jalankan pemulihan autentikasi otomatis di setiap request jika sesi PHP di container kosong
restore_auth_from_cookie();



function current_user_id(): int {
    foreach (['user_id', 'id_user', 'id'] as $k) {
        if (!empty($_SESSION[$k]) && ctype_digit((string)$_SESSION[$k])) {
            return (int)$_SESSION[$k];
        }
    }
    return 0;
}

function current_user_name(): string {
    foreach (['nama', 'name', 'nama_user', 'username'] as $k) {
        if (!empty($_SESSION[$k])) return (string)$_SESSION[$k];
    }
    return 'Teknisi';
}

function current_user_role(): string {
    if (!is_logged_in()) {
        return 'guest';
    }
    foreach (['role', 'user_role', 'level'] as $k) {
        if (!empty($_SESSION[$k])) return strtolower((string)$_SESSION[$k]);
    }
    return 'teknisi';
}

function request_uri_full(): string {
    $scheme = is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return $scheme . '://' . $host . $uri;
}

function module_base_url(): string {
    $configured = cfg('app_url', envv('APP_URL', ''));
    if ($configured) return rtrim($configured, '/');

    $scheme = is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $dir = rtrim(dirname($script), '/');
    // Strip /api prefix for Vercel deployment (routes rewrite /xxx.php -> /api/xxx.php)
    $dir = preg_replace('~/api$~', '', $dir);
    return $scheme . '://' . $host . ($dir === '/' ? '' : $dir);
}

function module_url(string $file = '', array $params = []): string {
    $url = module_base_url() . ($file ? '/' . ltrim($file, '/') : '');
    if ($params) $url .= '?' . http_build_query($params);
    return $url;
}

function login_url(): string {
    $u = cfg('login_url', envv('LOGIN_URL', '/login.php'));
    if (preg_match('~^https?://~i', $u)) return $u;
    $scheme = is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/' . ltrim($u, '/');
}

/**
 * Validasi URL redirect untuk mencegah kerentanan Open Redirect (CWE-601) dan CRLF Injection
 */
function safe_redirect_url(?string $url, ?string $fallback = null): string {
    if ($fallback === null) {
        $fallback = module_url('dashboard.php');
    }
    if ($url === null || $url === '') {
        return $fallback;
    }

    // Hapus karakter control CRLF & null bytes untuk mencegah response splitting
    $url = str_replace(["\r", "\n", "\0"], '', trim($url));
    if ($url === '') {
        return $fallback;
    }

    // Cegah protocol-relative URLs (//evil.com, /\evil.com, \\evil.com)
    if (str_starts_with($url, '//') || str_starts_with($url, '/\\') || str_starts_with($url, '\\')) {
        return $fallback;
    }

    // Cegah skema berbahaya (javascript:, data:, vbscript:, file:)
    if (preg_match('/^(javascript|data|vbscript|file):/i', $url)) {
        return $fallback;
    }

    // Relative path yang aman diawali satu slash '/'
    if (str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_starts_with($url, '/\\')) {
        return $url;
    }

    // Parsing komponen URL
    $parsed = parse_url($url);
    if ($parsed === false) {
        return $fallback;
    }

    // Relative tanpa slash (seperti dashboard.php atau scan.php?t=...)
    if (empty($parsed['scheme']) && empty($parsed['host'])) {
        if (str_contains($url, ':')) {
            return $fallback;
        }
        return '/' . ltrim($url, '/');
    }

    // Jika berupa URL absolut, verifikasi bahwa host sama dengan domain aplikasi saat ini
    $currentHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
    if (!empty($parsed['host']) && !empty($currentHost)) {
        $parsedHost = strtolower(explode(':', $parsed['host'])[0]);
        $expectedHost = strtolower(explode(':', $currentHost)[0]);
        if ($parsedHost === $expectedHost) {
            $scheme = strtolower($parsed['scheme'] ?? 'https');
            if ($scheme === 'http' || $scheme === 'https') {
                return $url;
            }
        }
    }

    return $fallback;
}

function is_logged_in(): bool {
    if (empty($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
        restore_auth_from_cookie();
    }
    $timeout = (int)cfg('session_timeout', envv('SESSION_TIMEOUT', '7200')); // 2 jam default
    $hasUser = current_user_id() > 0;
    if (!$hasUser) return false;
    if (!empty($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity'] > $timeout)) {
        return false;
    }
    return true;
}

function require_login(): void {
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    $timeout = (int)cfg('session_timeout', envv('SESSION_TIMEOUT', '7200')); // 2 jam default

    // Cek apakah user ada di sesi dan sudah kedaluwarsa karena tidak ada aktivitas (idle)
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['last_activity'])) {
        if (time() - (int)$_SESSION['last_activity'] > $timeout) {
            $savedRedirect = request_uri_full();
            logout_user();
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION['after_login'] = $savedRedirect;
            header('Location: ' . module_url('login.php', ['expired' => 1]));
            exit;
        }
    }

    // Cek apakah user sudah login
    $isLoggedIn = is_logged_in();

    if ($isLoggedIn) {
        $last = (int)($_SESSION['last_activity'] ?? 0);
        $now = time();
        $_SESSION['last_activity'] = $now;
        // Refresh token sesi jika sudah berjalan lebih dari 5 menit agar batas idle 2 jam ter-refresh saat aktif
        if (($now - $last > 300 || empty($_COOKIE['_auth_session'])) && !empty($_SESSION['user_id'])) {
            set_auth_cookie([
                'id' => $_SESSION['user_id'],
                'nama' => $_SESSION['nama'] ?? '',
                'username' => $_SESSION['username'] ?? '',
                'role' => $_SESSION['role'] ?? 'teknisi'
            ]);
        }
        return;
    }

    // Belum login: redirect ke halaman login
    $_SESSION['after_login'] = request_uri_full();
    header('Location: ' . module_url('login.php'));
    exit;
}

function require_admin(): void {
    require_login();
    $role = current_user_role();
    if ($role !== '' && !in_array($role, ['admin', 'administrator'], true)) {
        http_response_code(403);
        render_page('Akses Ditolak', '<div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-shield-exclamation me-2"></i>Menu ini hanya untuk admin.</div>');
        exit;
    }
}

function is_admin(): bool {
    if (!is_logged_in()) {
        return false;
    }
    $role = current_user_role();
    return in_array($role, ['admin', 'administrator'], true);
}


function login_user_session(array $user): void {
    $_SESSION['user_id'] = (int)($user['id'] ?? 1);
    $_SESSION['nama'] = (string)($user['nama'] ?? $user['name'] ?? $user['username'] ?? 'Pengguna');
    $_SESSION['username'] = (string)($user['username'] ?? 'user');
    $_SESSION['role'] = strtolower((string)($user['role'] ?? 'teknisi'));
    $_SESSION['last_activity'] = time();
    set_auth_cookie($user);
}

function get_user_passkeys(int $userId): array {
    if ($userId <= 0) return [];
    $user = get_user_by_id($userId);
    if (!$user) return [];
    $raw = trim((string)($user['passkey_credential'] ?? ''));
    if ($raw === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function save_user_passkey(int $userId, array $newCred): array {
    if ($userId <= 0) return ['success' => false, 'error' => 'ID pengguna tidak valid'];
    $credId = trim((string)($newCred['id'] ?? ''));
    if ($credId === '') return ['success' => false, 'error' => 'Credential ID tidak boleh kosong'];

    $existing = get_user_passkeys($userId);
    $filtered = [];
    foreach ($existing as $item) {
        if (($item['id'] ?? '') !== $credId) {
            $filtered[] = $item;
        }
    }
    $filtered[] = [
        'id' => $credId,
        'raw_id' => (string)($newCred['raw_id'] ?? $credId),
        'public_key' => (string)($newCred['public_key'] ?? ''),
        'device_name' => (string)($newCred['device_name'] ?? 'iPhone Face ID'),
        'created_at' => date('Y-m-d H:i:s')
    ];
    $json = json_encode($filtered, JSON_UNESCAPED_UNICODE);

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];

        $client->ensureMinColumns('Users', 26);
        $rows = $client->getSheetData('Users', true);
        $targetRow = null;
        foreach ($rows as $u) {
            if ((int)($u['id'] ?? 0) === $userId) {
                $targetRow = $u;
                break;
            }
        }
        if (!$targetRow) return ['success' => false, 'error' => 'Pengguna tidak ditemukan di Google Sheets'];

        $rowNum = (int)($targetRow['_row_num'] ?? 0);
        if ($rowNum <= 1) return ['success' => false, 'error' => 'Gagal menentukan baris data pengguna'];

        // Pastikan kolom L1 bernama passkey_credential
        $client->updateValues("Users!L1", [['passkey_credential']]);

        // Simpan JSON kredensial passkey ke kolom L
        $ok = $client->updateValues("Users!L{$rowNum}", [[$json]]);
        $client->clearCache('Users');

        if (!$ok) {
            return ['success' => false, 'error' => 'Gagal menyimpan data Face ID ke Google Sheets'];
        }
        return ['success' => true];
    }

    // MySQL Mode
    try {
        $cols = table_columns('users');
        if (!in_array('passkey_credential', $cols, true)) {
            try { db()->exec("ALTER TABLE users ADD COLUMN passkey_credential TEXT NULL"); } catch (Throwable $e) {}
        }
        $st = db()->prepare("UPDATE users SET passkey_credential = ? WHERE id = ?");
        $st->execute([$json, $userId]);
        return ['success' => true];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function find_user_by_passkey_cred_id(string $credId): ?array {
    $credId = trim($credId);
    if ($credId === '') return null;
    $users = get_user_list(true);
    foreach ($users as $u) {
        $raw = trim((string)($u['passkey_credential'] ?? ''));
        if ($raw === '') continue;
        $passkeys = json_decode($raw, true);
        if (is_array($passkeys)) {
            foreach ($passkeys as $pk) {
                if (($pk['id'] ?? '') === $credId || ($pk['raw_id'] ?? '') === $credId) {
                    return $u;
                }
            }
        }
    }
    return null;
}


function authenticate_user(string $username, string $password): array {
    $username = trim($username);
    $password = trim($password);

    if ($username === '' || $password === '') {
        return ['success' => false, 'error' => 'Username dan password wajib diisi.'];
    }

    // 1. Cek kredensial built-in dari environment / config (untuk deployment cepat)
    $envUser = cfg('admin_username', envv('ADMIN_USERNAME', ''));
    $envPass = cfg('admin_password', envv('ADMIN_PASSWORD', ''));

    if ($envUser !== '' && $envPass !== '') {
        if ($username === $envUser && $password === $envPass) {
            $_SESSION['user_id'] = 1;
            $_SESSION['nama'] = $envUser;
            $_SESSION['username'] = $envUser;
            $_SESSION['role'] = 'admin';
            $_SESSION['last_activity'] = time();
            set_auth_cookie([
                'id' => 1,
                'nama' => $envUser,
                'username' => $envUser,
                'role' => 'admin'
            ]);
            return ['success' => true, 'name' => $envUser];
        }
    }

    // 2. Cek dari tab Users di Google Sheets (jika Google Cloud Mode)
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if ($client) {
            $users = $client->getSheetData('Users');
            foreach ($users as $u) {
                $uName = trim((string)($u['username'] ?? ''));
                if (strcasecmp($uName, $username) === 0) {
                    $uStatus = trim((string)($u['status'] ?? 'Aktif'));
                    if (strcasecmp($uStatus, 'Nonaktif') === 0) {
                        return ['success' => false, 'error' => 'Akun Anda dinonaktifkan. Silakan hubungi Administrator.'];
                    }

                    $storedPass = (string)($u['password'] ?? '');
                    $passMatch = false;
                    if (str_starts_with($storedPass, '$2y$') || str_starts_with($storedPass, '$2a$') || str_starts_with($storedPass, '$argon2')) {
                        $passMatch = password_verify($password, $storedPass);
                    } else {
                        $passMatch = ($password === $storedPass);
                    }

                    if ($passMatch) {
                        $uid = (int)($u['id'] ?? 1);
                        $uRealName = (string)($u['nama'] ?? $u['name'] ?? $u['username']);
                        $uRole = strtolower((string)($u['role'] ?? 'teknisi'));
                        $_SESSION['user_id'] = $uid;
                        $_SESSION['nama'] = $uRealName;
                        $_SESSION['username'] = $uName;
                        $_SESSION['role'] = $uRole;
                        $_SESSION['last_activity'] = time();
                        set_auth_cookie([
                            'id' => $uid,
                            'nama' => $uRealName,
                            'username' => $uName,
                            'role' => $uRole
                        ]);
                        return ['success' => true, 'name' => $uRealName];
                    }
                }
            }
        }
    }

    // 3. Cek dari tabel users MySQL (jika mode MySQL)
    if (!is_google_cloud_mode()) {
        try {
            $cols = table_columns('users');
            $nameCol = in_array('nama', $cols, true) ? 'nama' : (in_array('name', $cols, true) ? 'name' : 'username');
            $st = db()->prepare("SELECT id, `{$nameCol}` AS nama, username, password, role, status FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
            $st->execute([$username]);
            $user = $st->fetch();

            if ($user) {
                if (!empty($user['status']) && strcasecmp($user['status'], 'Nonaktif') === 0) {
                    return ['success' => false, 'error' => 'Akun Anda dinonaktifkan. Silakan hubungi Administrator.'];
                }

                $passMatch = false;
                $storedPass = (string)($user['password'] ?? '');

                if (str_starts_with($storedPass, '$2y$') || str_starts_with($storedPass, '$2a$') || str_starts_with($storedPass, '$argon2')) {
                    $passMatch = password_verify($password, $storedPass);
                } else {
                    $passMatch = ($password === $storedPass);
                }

                if ($passMatch) {
                    $uid = (int)$user['id'];
                    $uRealName = (string)($user['nama'] ?? $user['username']);
                    $uRole = strtolower((string)($user['role'] ?? 'teknisi'));
                    $_SESSION['user_id'] = $uid;
                    $_SESSION['nama'] = $uRealName;
                    $_SESSION['username'] = (string)$user['username'];
                    $_SESSION['role'] = $uRole;
                    $_SESSION['last_activity'] = time();
                    set_auth_cookie([
                        'id' => $uid,
                        'nama' => $uRealName,
                        'username' => (string)$user['username'],
                        'role' => $uRole
                    ]);
                    return ['success' => true, 'name' => $uRealName];
                }
            }
        } catch (Throwable $e) {
            // Lanjut ke default
        }
    }

    // 4. Fallback Default admin & teknisi
    $defaultUsers = [
        ['username' => 'admin', 'password' => 'admin123', 'name' => 'Administrator', 'role' => 'admin'],
        ['username' => 'teknisi', 'password' => 'teknisi123', 'name' => 'Teknisi IT', 'role' => 'teknisi'],
    ];

    foreach ($defaultUsers as $du) {
        if (strcasecmp($username, $du['username']) === 0 && $password === $du['password']) {
            $uid = ($du['role'] === 'admin') ? 1 : 2;
            $_SESSION['user_id'] = $uid;
            $_SESSION['nama'] = $du['name'];
            $_SESSION['username'] = $du['username'];
            $_SESSION['role'] = $du['role'];
            $_SESSION['last_activity'] = time();
            set_auth_cookie([
                'id' => $uid,
                'nama' => $du['name'],
                'username' => $du['username'],
                'role' => $du['role']
            ]);
            return ['success' => true, 'name' => $du['name']];
        }
    }

    return ['success' => false, 'error' => 'Username atau password salah.'];
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    setcookie('_auth_session', '', time() - 42000, '/', '', is_https(), true);
    @session_destroy();
}

function verify_current_user_password(string $password): array {
    $password = trim($password);
    if ($password === '') {
        return ['success' => false, 'error' => 'Kata sandi tidak boleh kosong.'];
    }
    $username = (string)($_SESSION['username'] ?? '');
    if ($username === '') {
        return ['success' => false, 'error' => 'Sesi pengguna tidak ditemukan. Silakan login kembali.', 'expired' => true];
    }

    $auth = authenticate_user($username, $password);
    if (!empty($auth['success'])) {
        $_SESSION['last_activity'] = time();
        return ['success' => true, 'name' => $auth['name'] ?? $username];
    }

    return ['success' => false, 'error' => 'Kata sandi tidak sesuai. Silakan coba lagi.'];
}

/**
 * =========================================================================
 * PROTEKSI ANTI BRUTE-FORCE & ACCOUNT LOCKOUT (STANDAR KETAHANAN SIBER OJK)
 * Sesuai regulasi:
 * - POJK No. 75/POJK.03/2016 (Standar Penyelenggaraan TI BPR/BPRS)
 * - POJK No. 11/POJK.03/2022 (Ketahanan Siber & Penyelenggaraan TI Bank)
 * 
 * Aturan:
 * 1. Maksimal percobaan gagal: 5 kali berturut-turut.
 * 2. Durasi penguncian akun/IP: 15 menit (900 detik).
 * 3. Pemulihan otomatis setelah masa penguncian berakhir atau reset saat login berhasil.
 * =========================================================================
 */
const OJK_MAX_LOGIN_ATTEMPTS = 5;
const OJK_LOCKOUT_DURATION_SECONDS = 900; // 15 menit

function get_lockout_storage_record(string $identifier, string $type): array {
    // 1. Coba dari MySQL jika bukan Google Cloud Mode
    if (!is_google_cloud_mode()) {
        try {
            $pdo = db();
            $pdo->exec("CREATE TABLE IF NOT EXISTS login_lockouts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(150) NOT NULL UNIQUE,
                type VARCHAR(20) NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                locked_until INT NOT NULL DEFAULT 0,
                last_attempt INT NOT NULL DEFAULT 0,
                INDEX idx_identifier (identifier),
                INDEX idx_locked_until (locked_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $st = $pdo->prepare("SELECT attempts, locked_until, last_attempt FROM login_lockouts WHERE identifier = ? LIMIT 1");
            $st->execute([$identifier]);
            $row = $st->fetch();
            if ($row) {
                return [
                    'attempts' => (int)$row['attempts'],
                    'locked_until' => (int)$row['locked_until'],
                    'last_attempt' => (int)$row['last_attempt'],
                ];
            }
        } catch (Throwable $e) {}
    }

    // 2. Fallback File Cache di /tmp (bertahan antar request serverless di container yang sama)
    $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ojk_lock_' . md5($identifier) . '.json';
    if (file_exists($tmpFile)) {
        $raw = @file_get_contents($tmpFile);
        if ($raw) {
            $data = @json_decode($raw, true);
            if (is_array($data)) {
                return [
                    'attempts' => (int)($data['attempts'] ?? 0),
                    'locked_until' => (int)($data['locked_until'] ?? 0),
                    'last_attempt' => (int)($data['last_attempt'] ?? 0),
                ];
            }
        }
    }

    // 3. Fallback Sesi PHP
    $sessKey = '_ojk_lock_' . md5($identifier);
    if (!empty($_SESSION[$sessKey]) && is_array($_SESSION[$sessKey])) {
        return [
            'attempts' => (int)($_SESSION[$sessKey]['attempts'] ?? 0),
            'locked_until' => (int)($_SESSION[$sessKey]['locked_until'] ?? 0),
            'last_attempt' => (int)($_SESSION[$sessKey]['last_attempt'] ?? 0),
        ];
    }

    return ['attempts' => 0, 'locked_until' => 0, 'last_attempt' => 0];
}

function save_lockout_storage_record(string $identifier, string $type, int $attempts, int $lockedUntil, int $lastAttempt): void {
    // 1. Simpan ke MySQL
    if (!is_google_cloud_mode()) {
        try {
            $pdo = db();
            $st = $pdo->prepare("INSERT INTO login_lockouts (identifier, type, attempts, locked_until, last_attempt)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                attempts = VALUES(attempts),
                locked_until = VALUES(locked_until),
                last_attempt = VALUES(last_attempt)");
            $st->execute([$identifier, $type, $attempts, $lockedUntil, $lastAttempt]);
        } catch (Throwable $e) {}
    }

    // 2. Simpan ke File Cache di /tmp
    $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ojk_lock_' . md5($identifier) . '.json';
    $data = [
        'identifier' => $identifier,
        'type' => $type,
        'attempts' => $attempts,
        'locked_until' => $lockedUntil,
        'last_attempt' => $lastAttempt,
    ];
    @file_put_contents($tmpFile, json_encode($data));

    // 3. Simpan ke Sesi PHP
    $sessKey = '_ojk_lock_' . md5($identifier);
    $_SESSION[$sessKey] = $data;
}

function clear_lockout_storage_record(string $identifier): void {
    if (!is_google_cloud_mode()) {
        try {
            $pdo = db();
            $st = $pdo->prepare("DELETE FROM login_lockouts WHERE identifier = ?");
            $st->execute([$identifier]);
        } catch (Throwable $e) {}
    }

    $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ojk_lock_' . md5($identifier) . '.json';
    if (file_exists($tmpFile)) {
        @unlink($tmpFile);
    }

    $sessKey = '_ojk_lock_' . md5($identifier);
    unset($_SESSION[$sessKey]);
}

function check_login_throttle(string $username, string $ip): array {
    $now = time();

    // Periksa throttle berdasarkan Username (jika diisi) dan IP Address
    $targets = [];
    if ($username !== '') {
        $targets[] = ['id' => 'user:' . strtolower($username), 'type' => 'username', 'label' => "Akun '{$username}'"];
    }
    if ($ip !== '') {
        $targets[] = ['id' => 'ip:' . $ip, 'type' => 'ip', 'label' => "Alamat IP ({$ip})"];
    }

    foreach ($targets as $t) {
        $rec = get_lockout_storage_record($t['id'], $t['type']);
        if ($rec['locked_until'] > $now) {
            $remSeconds = $rec['locked_until'] - $now;
            $remMinutes = (int)ceil($remSeconds / 60);
            return [
                'locked' => true,
                'remaining_seconds' => $remSeconds,
                'attempts' => $rec['attempts'],
                'message' => "Akses {$t['label']} terkunci sementara selama 15 menit sesuai standar OJK akibat 5 kali salah kata sandi berturut-turut. Silakan tunggu {$remMinutes} menit lagi sebelum mencoba kembali."
            ];
        }

        // Jika masa lockout sudah lewat, bersihkan otomatis
        if ($rec['locked_until'] > 0 && $rec['locked_until'] <= $now) {
            clear_lockout_storage_record($t['id']);
        }
    }

    // Ambil data attempt tertinggi saat ini
    $maxAttempts = 0;
    foreach ($targets as $t) {
        $rec = get_lockout_storage_record($t['id'], $t['type']);
        // Jika jeda dari percobaan terakhir > 15 menit, reset hitungan
        if ($rec['last_attempt'] > 0 && ($now - $rec['last_attempt'] > OJK_LOCKOUT_DURATION_SECONDS)) {
            clear_lockout_storage_record($t['id']);
            continue;
        }
        if ($rec['attempts'] > $maxAttempts) {
            $maxAttempts = $rec['attempts'];
        }
    }

    return [
        'locked' => false,
        'remaining_seconds' => 0,
        'attempts' => $maxAttempts,
        'remaining_attempts' => max(0, OJK_MAX_LOGIN_ATTEMPTS - $maxAttempts),
        'message' => ''
    ];
}

function record_login_failure(string $username, string $ip): array {
    $now = time();
    $targets = [];
    if ($username !== '') {
        $targets[] = ['id' => 'user:' . strtolower($username), 'type' => 'username', 'label' => "Akun '{$username}'"];
    }
    if ($ip !== '') {
        $targets[] = ['id' => 'ip:' . $ip, 'type' => 'ip', 'label' => "Alamat IP ({$ip})"];
    }

    $isLockedNow = false;
    $maxAttempts = 0;
    $lockedLabel = '';

    foreach ($targets as $t) {
        $rec = get_lockout_storage_record($t['id'], $t['type']);
        // Jika jeda dari percobaan sebelumnya > 15 menit, mulai dari 1
        if ($rec['last_attempt'] > 0 && ($now - $rec['last_attempt'] > OJK_LOCKOUT_DURATION_SECONDS)) {
            $currentAttempts = 1;
        } else {
            $currentAttempts = $rec['attempts'] + 1;
        }

        $lockedUntil = 0;
        if ($currentAttempts >= OJK_MAX_LOGIN_ATTEMPTS) {
            $lockedUntil = $now + OJK_LOCKOUT_DURATION_SECONDS;
            $isLockedNow = true;
            $lockedLabel = $t['label'];
        }

        save_lockout_storage_record($t['id'], $t['type'], $currentAttempts, $lockedUntil, $now);

        if ($currentAttempts > $maxAttempts) {
            $maxAttempts = $currentAttempts;
        }
    }

    if ($isLockedNow) {
        if (function_exists('record_audit_log')) {
            record_audit_log('ACCOUNT_LOCKED', 'KEAMANAN', null, $username ?: 'unknown', "{$lockedLabel} terkunci otomatis selama 15 menit sesuai aturan OJK setelah 5 kali gagal login berturut-turut.", [
                'ip' => $ip,
                'duration_seconds' => OJK_LOCKOUT_DURATION_SECONDS,
                'standard' => 'POJK No. 75/POJK.03/2016 & POJK No. 11/POJK.03/2022'
            ]);
        }

        return [
            'locked' => true,
            'attempts' => $maxAttempts,
            'remaining_attempts' => 0,
            'remaining_seconds' => OJK_LOCKOUT_DURATION_SECONDS,
            'message' => "Akses {$lockedLabel} terkunci sementara selama 15 menit sesuai standar OJK akibat 5 kali salah kata sandi. Silakan tunggu 15 menit lagi sebelum mencoba kembali."
        ];
    }

    return [
        'locked' => false,
        'attempts' => $maxAttempts,
        'remaining_attempts' => max(0, OJK_MAX_LOGIN_ATTEMPTS - $maxAttempts),
        'remaining_seconds' => 0,
        'message' => ''
    ];
}

function reset_login_throttle(string $username, string $ip): void {
    if ($username !== '') {
        clear_lockout_storage_record('user:' . strtolower($username));
    }
    if ($ip !== '') {
        clear_lockout_storage_record('ip:' . $ip);
    }
}

function clear_all_login_lockouts(): void {
    // 1. Bersihkan dari MySQL jika mode database
    if (!is_google_cloud_mode()) {
        try {
            $pdo = db();
            $pdo->exec("DELETE FROM login_lockouts");
        } catch (Throwable $e) {}
    }

    // 2. Bersihkan seluruh file cache di /tmp
    $tmpDir = sys_get_temp_dir();
    $files = @glob($tmpDir . DIRECTORY_SEPARATOR . 'ojk_lock_*.json');
    if (is_array($files)) {
        foreach ($files as $f) {
            @unlink($f);
        }
    }

    // 3. Bersihkan dari Sesi PHP aktif
    if (session_status() === PHP_SESSION_ACTIVE) {
        foreach ($_SESSION as $k => $v) {
            if (str_starts_with($k, '_ojk_lock_')) {
                unset($_SESSION[$k]);
            }
        }
    }
}


