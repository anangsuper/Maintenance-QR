<?php
declare(strict_types=1);

function set_auth_cookie(array $userData, int $lifetime = 2592000): void {
    $uid = (int)($userData['id'] ?? $userData['user_id'] ?? 0);
    $nama = (string)($userData['nama'] ?? $userData['name'] ?? '');
    $username = (string)($userData['username'] ?? '');
    $role = (string)($userData['role'] ?? 'teknisi');

    if ($uid <= 0 && $username === '') return;

    $payload = json_encode([
        'uid' => $uid,
        'nama' => $nama,
        'username' => $username,
        'role' => $role,
        'exp' => time() + $lifetime,
    ], JSON_UNESCAPED_UNICODE);

    $sig = hash_hmac('sha256', $payload, app_auth_secret());
    $cookieVal = base64url_encode($payload) . '.' . $sig;

    setcookie('_auth_session', $cookieVal, [
        'expires' => time() + $lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function restore_auth_from_cookie(): bool {
    // Jika $_SESSION sudah punya data valid, cukup perbarui
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
    if (!is_array($data) || empty($data['exp']) || $data['exp'] < time()) {
        return false;
    }

    $uid = (int)($data['uid'] ?? 0);
    if ($uid <= 0 && empty($data['username'])) {
        return false;
    }

    $_SESSION['user_id'] = $uid;
    $_SESSION['nama'] = (string)($data['nama'] ?? '');
    $_SESSION['username'] = (string)($data['username'] ?? '');
    $_SESSION['role'] = (string)($data['role'] ?? 'teknisi');
    $_SESSION['last_activity'] = time();

    return true;
}

// Jalankan pemulihan autentikasi otomatis di setiap request
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

function is_logged_in(): bool {
    if (empty($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
        restore_auth_from_cookie();
    }
    $timeout = (int)cfg('session_timeout', envv('SESSION_TIMEOUT', '2592000')); // 30 hari default
    $hasUser = current_user_id() > 0;
    if (!$hasUser) return false;
    if (!empty($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity'] > $timeout)) {
        return false;
    }
    // Refresh waktu aktivitas jika masih aktif
    $_SESSION['last_activity'] = time();
    return true;
}

function require_login(): void {
    $timeout = (int)cfg('session_timeout', envv('SESSION_TIMEOUT', '2592000')); // 30 hari default

    // Cek apakah user sudah login
    $isLoggedIn = is_logged_in();

    if ($isLoggedIn) {
        // Cek sesi kedaluwarsa karena tidak ada aktivitas (idle)
        if (!empty($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity'] > $timeout)) {
            $savedRedirect = request_uri_full();
            logout_user();
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION['after_login'] = $savedRedirect;
            header('Location: ' . module_url('login.php', ['expired' => 1]));
            exit;
        }

        // Perbarui waktu aktivitas terakhir
        $_SESSION['last_activity'] = time();
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

