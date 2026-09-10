<?php
function is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') return true;
    if (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower((string)$_SERVER['HTTP_FRONT_END_HTTPS']) === 'on') return true;
    if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) return true;
    if (!empty($_SERVER['VERCEL'])) return true;
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if (str_ends_with($host, '.vercel.app')) return true;
    return false;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $lifetime = (int)cfg('session_timeout', envv('SESSION_TIMEOUT', '2592000')); // 30 hari (2592000 detik)
    ini_set('session.gc_maxlifetime', (string)$lifetime);

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

date_default_timezone_set('Asia/Makassar');

require_once dirname(__DIR__) . '/google_sheets_v4.php';

function envv(string $key, ?string $fallback = null): ?string {
    $v = getenv($key);
    if ($v === false || $v === '') {
        $v = $_ENV[$key] ?? $_SERVER[$key] ?? false;
    }
    return ($v === false || $v === '') ? $fallback : (string)$v;
}

function local_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $cfg = [];
    $file = dirname(__DIR__) . '/config.local.php';
    if (!is_file($file)) {
        $file = dirname(__DIR__, 2) . '/config.local.php';
    }
    if (is_file($file)) {
        $loaded = require $file;
        if (is_array($loaded)) $cfg = $loaded;
    }
    return $cfg;
}

function cfg(string $key, ?string $fallback = null): ?string {
    $local = local_config();
    if (array_key_exists($key, $local) && $local[$key] !== '') {
        return (string)$local[$key];
    }
    return $fallback;
}

function app_auth_secret(): string {
    static $key = null;
    if ($key !== null) return $key;
    $k = cfg('app_key', envv('APP_KEY', envv('APP_SECRET', '')));
    if ($k !== '') {
        $key = $k;
        return $key;
    }
    $salt = cfg('google_spreadsheet_id', envv('GOOGLE_SPREADSHEET_ID', 'maintenance_qr_default_salt_2026'));
    $key = hash('sha256', $salt . '_auth_secret_v2');
    return $key;
}


function is_google_cloud_mode(): bool {
    $sheetId = cfg('google_spreadsheet_id', envv('GOOGLE_SPREADSHEET_ID', ''));
    $email = cfg('google_client_email', envv('GOOGLE_CLIENT_EMAIL', ''));
    return (!empty($sheetId) && !empty($email));
}

function google_sheets_v4_client(): ?GoogleSheetsV4Client {
    static $client = null;
    if ($client !== null) return $client;

    $sheetId = cfg('google_spreadsheet_id', envv('GOOGLE_SPREADSHEET_ID', ''));
    $email = cfg('google_client_email', envv('GOOGLE_CLIENT_EMAIL', ''));
    $key = cfg('google_private_key', envv('GOOGLE_PRIVATE_KEY', ''));

    if ($sheetId && $email && $key) {
        $client = new GoogleSheetsV4Client($sheetId, $email, $key);
    }
    return $client;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $host = cfg('db_host', envv('DB_HOST', envv('MYSQLHOST', '127.0.0.1')));
    $port = cfg('db_port', envv('DB_PORT', envv('MYSQLPORT', '3306')));
    $name = cfg('db_name', envv('DB_NAME', envv('MYSQLDATABASE', 'rekap_it')));
    $user = cfg('db_user', envv('DB_USER', envv('MYSQLUSER', 'root')));
    $pass = cfg('db_pass', envv('DB_PASS', envv('MYSQLPASSWORD', '')));

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}


function format_phone_number(?string $phone): string {
    if ($phone === null) return '-';
    $phone = trim($phone, " '\t\n\r\0\x0B");
    if ($phone === '' || $phone === '-') return '-';

    // Jika diawali 8 (misal 89523140757 karena 0-nya terpotong oleh spreadsheet), tambahkan 0 di depan
    if (preg_match('/^8[0-9]{8,12}$/', $phone)) {
        return '0' . $phone;
    }
    // Jika diawali 628, ubah ke 08
    if (preg_match('/^628[0-9]{8,12}$/', $phone)) {
        return '0' . substr($phone, 2);
    }
    // Jika diawali kode area tanpa 0 (misal 218765432, 248765432)
    if (preg_match('/^[2-9][0-9]{6,10}$/', $phone) && strlen($phone) >= 7 && strlen($phone) <= 11) {
        return '0' . $phone;
    }

    return $phone;
}


function format_id_date(string $date): string {
    $date = trim($date);
    if ($date === '' || $date === '-' || str_starts_with($date, '0000')) return '-';
    $ts = strtotime($date);
    return $ts ? date('d-m-Y', $ts) : $date;
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        if (!empty($_COOKIE['_csrf_token']) && preg_match('/^[a-f0-9]{32,64}$/i', (string)$_COOKIE['_csrf_token'])) {
            $_SESSION['_csrf'] = (string)$_COOKIE['_csrf_token'];
        } else {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
            setcookie('_csrf_token', $_SESSION['_csrf'], [
                'expires' => time() + 86400 * 30,
                'path' => '/',
                'domain' => '',
                'secure' => is_https(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    }
    return $_SESSION['_csrf'];
}

function verify_csrf(): void {
    $sent = (string)($_POST['_csrf'] ?? '');
    $expected = (string)($_SESSION['_csrf'] ?? $_COOKIE['_csrf_token'] ?? '');

    if ($sent !== '' && $expected !== '' && hash_equals($expected, $sent)) {
        return;
    }

    // Jika cookie _csrf_token cocok dengan token yang dikirim
    $cookieToken = (string)($_COOKIE['_csrf_token'] ?? '');
    if ($sent !== '' && $cookieToken !== '' && hash_equals($cookieToken, $sent)) {
        $_SESSION['_csrf'] = $cookieToken;
        return;
    }

    // Jika pengguna adalah admin terverifikasi lewat signed auth session, izinkan aksi form admin
    if (is_admin()) {
        if ($sent !== '') {
            $_SESSION['_csrf'] = $sent;
        }
        return;
    }

    http_response_code(419);
    render_page('Sesi Tidak Valid', '<div class="alert alert-danger">Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.</div>');
    exit;
}

function e(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function table_columns(string $table): array {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];

    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) return [];
    try {
        $rows = db()->query("SHOW COLUMNS FROM `{$table}`")->fetchAll();
        $cache[$table] = array_column($rows, 'Field');
        return $cache[$table];
    } catch (Throwable $e) {
        return [];
    }
}

function col(string $table, array $candidates, ?string $fallback = null): ?string {
    $cols = table_columns($table);
    foreach ($candidates as $c) {
        if (in_array($c, $cols, true)) return $c;
    }
    return $fallback;
}

function name_column(string $table): ?string {
    $map = [
        'cabang' => ['nama_cabang', 'nama', 'cabang'],
        'divisi' => ['nama_divisi', 'nama', 'divisi'],
        'karyawan' => ['nama_karyawan', 'nama_lengkap', 'nama', 'karyawan'],
        'kategori_aset' => ['nama_kategori', 'nama', 'kategori'],
        'users' => ['nama', 'name', 'nama_user', 'username'],
    ];
    return col($table, $map[$table] ?? ['nama', 'name']);
}

