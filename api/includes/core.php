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

if (!function_exists('get_client_ip')) {
    function get_client_ip(): string {
        $candidates = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        foreach ($candidates as $key) {
            if (!empty($_SERVER[$key])) {
                $ipList = explode(',', (string)$_SERVER[$key]);
                $ip = trim($ipList[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    }
}

// Enable GZIP Output Compression for ultra-lightweight and faster payload transfer
if (!ob_get_level() && !headers_sent()) {
    if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
        @ini_set('zlib.output_compression', '1');
        @ini_set('zlib.output_compression_level', '6');
    }
}

// HTTP Security Headers (Proteksi Clickjacking, MIME-sniffing, XSS)
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Connection: close');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (getenv('VERCEL') || !empty($_SERVER['VERCEL']) || str_ends_with(strtolower((string)($_SERVER['HTTP_HOST'] ?? '')), '.vercel.app')) {
        $tmpSession = sys_get_temp_dir();
        if (is_dir($tmpSession) && is_writable($tmpSession)) {
            @session_save_path($tmpSession);
        }
    }

    $idleTimeout = (int)cfg('session_timeout', envv('SESSION_TIMEOUT', '1800')); // Idle timeout 30 menit (1800 detik)
    ini_set('session.gc_maxlifetime', (string)$idleTimeout);

    $isHttps = is_https();
    $sessionName = $isHttps ? 'PHPSESSID_SEC' : 'PHPSESSID_LAN';
    if (!empty($_COOKIE['PHPSESSID']) && empty($_COOKIE[$sessionName])) {
        $sessionName = 'PHPSESSID';
    }
    session_name($sessionName);

    session_set_cookie_params([
        'lifetime' => 0, // 0 = Cookie sesi berakhir dan otomatis logout saat browser ditutup
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
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

    $dbUrl = envv('DATABASE_URL', envv('MYSQL_URL', ''));
    if ($dbUrl && (str_starts_with($dbUrl, 'mysql://') || str_starts_with($dbUrl, 'mysql2://'))) {
        $parsed = parse_url($dbUrl);
        if (!empty($parsed['host'])) $host = $parsed['host'];
        if (!empty($parsed['port'])) $port = (string)$parsed['port'];
        if (!empty($parsed['user'])) $user = urldecode($parsed['user']);
        if (!empty($parsed['pass'])) $pass = urldecode($parsed['pass']);
        if (!empty($parsed['path'])) $name = ltrim(urldecode($parsed['path']), '/');
    }

    $ssl = cfg('db_ssl', envv('DB_SSL', envv('MYSQL_SSL', '')));
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if ($ssl === 'true' || $ssl === '1' || $ssl === 'require' || envv('DATABASE_URL') || str_contains($host, 'aivencloud') || str_contains($host, 'tidbcloud') || str_contains($host, 'railway') || str_contains($host, 'planetscale')) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        // Sinkronisasi timezone database dengan PHP (WITA / UTC+8 untuk Bank Mitra)
        try {
            $pdo->exec("SET time_zone = '+08:00'");
        } catch (Throwable $eTz) {}
        return $pdo;
    } catch (PDOException $e) {
        if (!empty($_SERVER['VERCEL']) || str_ends_with(strtolower((string)($_SERVER['HTTP_HOST'] ?? '')), '.vercel.app')) {
            http_response_code(500);
            echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Koneksi Database Vercel</title>';
            echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
            echo '<link href="assets/vendor/bootstrap.min.css" rel="stylesheet">';
            echo '</head><body class="bg-light p-4"><div class="container" style="max-width: 680px; margin-top: 50px;">';
            echo '<div class="card shadow-sm border-0 rounded-4 p-4 text-center">';
            echo '<div class="text-warning mb-3" style="font-size: 3rem;">⚠️</div>';
            echo '<h4 class="fw-bold mb-2">Konfigurasi Database Diperlukan</h4>';
            echo '<p class="text-muted">Aplikasi telah berhasil berjalan di <strong>Vercel Serverless</strong>, namun belum terhubung ke database cloud (MySQL) atau Google Sheets API.</p>';
            echo '<div class="alert alert-primary text-start fs-6"><p class="mb-2 fw-semibold">Silakan tambahkan Environment Variables di Dashboard Vercel Anda (Settings &rarr; Environment Variables):</p>';
            echo '<ul class="mb-0"><li><strong>Opsi 1 (MySQL Cloud):</strong> <code>DB_HOST</code>, <code>DB_USER</code>, <code>DB_PASS</code>, <code>DB_NAME</code>, <code>DB_PORT</code> (atau <code>DATABASE_URL</code>)</li>';
            echo '<li><strong>Opsi 2 (Google Sheets Serverless):</strong> <code>GOOGLE_SPREADSHEET_ID</code>, <code>GOOGLE_CLIENT_EMAIL</code>, <code>GOOGLE_PRIVATE_KEY</code></li></ul></div>';
            echo '<div class="text-secondary small text-start"><em>Pesan Teknis: ' . htmlspecialchars($e->getMessage()) . '</em></div>';
            echo '</div></div></body></html>';
            exit;
        }
        throw $e;
    }
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

function normalize_kode_inventaris(?string $kode): string {
    if ($kode === null) return '';
    $kode = trim((string)$kode, " '\t\n\r\0\x0B");
    if ($kode === '' || $kode === '-') return $kode;

    // Jika kode inventaris berupa angka murni (atau diawali angka) dan angka 0 di depannya terpotong
    // oleh spreadsheet / format numerik (misal 450007417122025 menjadi 0450007417122025, 150047417122025 menjadi 0150047417122025)
    // Format nomor inventaris standar perbankan terdiri dari 10-18 digit yang diawali angka 0 (01, 02, 04, dll.)
    if (preg_match('/^[1-9]\d{9,18}(-[A-Za-z0-9]+)?$/', $kode)) {
        return '0' . $kode;
    }

    return $kode;
}

function sheet_cell_text(string $val): string {
    $val = trim($val);
    if ($val === '') return '';
    if (str_starts_with($val, "'")) return $val;
    // Jika diawali angka 0 atau berpola nomor rekening (misal 02.05.1983 / 01.05.0493),
    // beri prefix tanda kutip satu (') agar Google Sheets menyimpannya sebagai plain text dan TIDAK otomatis berubah jadi format tanggal
    if (preg_match('/^0\d+/', $val) || preg_match('/^\d{1,4}[\.\-]\d{1,4}[\.\-]\d+/', $val)) {
        return "'" . $val;
    }
    return $val;
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

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
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

    http_response_code(419);
    render_page('Sesi Tidak Valid', '<div class="alert alert-danger">Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.</div>');
    exit;
}

function csrf_validate(): void {
    verify_csrf();
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

function app_logo_data_uri(): string {
    static $cached = null;
    if ($cached !== null) return $cached;

    $candidates = [
        dirname(__DIR__) . '/logo.png',
        dirname(__DIR__) . '/Logo Storek Putih Di text.png',
        dirname(__DIR__, 2) . '/Logo Storek Putih Di text.png',
        dirname(__DIR__, 2) . '/logo.png'
    ];

    foreach ($candidates as $p) {
        if (file_exists($p)) {
            $data = @file_get_contents($p);
            if ($data !== false && strlen($data) > 0) {
                $cached = 'data:image/png;base64,' . base64_encode($data);
                return $cached;
            }
        }
    }

    return module_url('logo.png');
}

function app_logo_url(): string {
    return module_url('logo.png');
}

/**
 * Sanitasi cell CSV untuk mencegah kerentanan CSV / Formula Injection (CWE-1236)
 * Saat file CSV dibuka di Microsoft Excel atau Calc, karakter '=', '+', '-', '@', dll
 * dapat memicu eksekusi rumus atau command DDE eksternal.
 */
function sanitize_csv_cell(mixed $val): string {
    $str = (string)($val ?? '');
    if ($str === '') return '';
    $first = $str[0];
    if (in_array($first, ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $str;
    }
    return $str;
}



