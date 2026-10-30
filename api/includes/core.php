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


function get_google_service_account_credentials(): array {
    static $creds = null;
    if ($creds !== null) return $creds;

    $sheetId = cfg('google_spreadsheet_id', envv('GOOGLE_SPREADSHEET_ID', envv('GOOGLE_SHEET_ID', envv('SPREADSHEET_ID', '19V_qkLtFmaH93dA9h1ZqM2T7JqZ6H_IEI94PVkPnNIc'))));
    $email = cfg('google_client_email', envv('GOOGLE_CLIENT_EMAIL', ''));
    $key = cfg('google_private_key', envv('GOOGLE_PRIVATE_KEY', ''));

    // Cek jika kredensial utuh diberikan dalam format JSON di environment variable
    $rawJson = envv('GOOGLE_SERVICE_ACCOUNT_JSON', envv('GOOGLE_CREDENTIALS', envv('GCP_SERVICE_ACCOUNT', '')));
    if (!empty($rawJson)) {
        $decoded = json_decode($rawJson, true);
        if (is_array($decoded)) {
            if (empty($email) && !empty($decoded['client_email'])) $email = (string)$decoded['client_email'];
            if (empty($key) && !empty($decoded['private_key'])) $key = (string)$decoded['private_key'];
            if (empty($sheetId) && !empty($decoded['spreadsheet_id'])) $sheetId = (string)$decoded['spreadsheet_id'];
        }
    }

    // Cek berkas service-account.json lokal jika ada
    $filePaths = [
        dirname(__DIR__) . '/service-account.json',
        dirname(__DIR__) . '/credentials.json',
        dirname(__DIR__, 2) . '/service-account.json',
        dirname(__DIR__, 2) . '/config/service-account.json',
    ];
    foreach ($filePaths as $fp) {
        if (is_file($fp) && is_readable($fp)) {
            $decoded = json_decode((string)@file_get_contents($fp), true);
            if (is_array($decoded)) {
                if (empty($email) && !empty($decoded['client_email'])) $email = (string)$decoded['client_email'];
                if (empty($key) && !empty($decoded['private_key'])) $key = (string)$decoded['private_key'];
                if (empty($sheetId) && !empty($decoded['spreadsheet_id'])) $sheetId = (string)$decoded['spreadsheet_id'];
                break;
            }
        }
    }

    if (!empty($key)) {
        $key = str_replace(['\\n', "\r"], ["\n", ''], trim($key));
    }

    $creds = [
        'spreadsheet_id' => trim((string)$sheetId),
        'client_email' => trim((string)$email),
        'private_key' => trim((string)$key)
    ];
    return $creds;
}

function is_google_cloud_mode(): bool {
    $c = get_google_service_account_credentials();
    return (!empty($c['spreadsheet_id']) && !empty($c['client_email']) && !empty($c['private_key']));
}

function google_sheets_v4_client(): ?GoogleSheetsV4Client {
    static $client = null;
    if ($client !== null) return $client;

    $c = get_google_service_account_credentials();
    if (!empty($c['spreadsheet_id']) && !empty($c['client_email']) && !empty($c['private_key'])) {
        $client = new GoogleSheetsV4Client($c['spreadsheet_id'], $c['client_email'], $c['private_key']);
    }
    return $client;
}

function render_vercel_spreadsheet_setup_guide(?string $technicalMsg = null): void {
    http_response_code(503);
    $c = get_google_service_account_credentials();
    $currSheetId = htmlspecialchars($c['spreadsheet_id'] ?: '19V_qkLtFmaH93dA9h1ZqM2T7JqZ6H_IEI94PVkPnNIc');

    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">';
    echo '<title>Aktivasi Google Spreadsheet &bull; Vercel</title>';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<link href="assets/vendor/bootstrap.min.css" rel="stylesheet">';
    echo '<style>
        body { background: #0B192C; color: #E2E8F0; font-family: system-ui, -apple-system, sans-serif; }
        .setup-card { background: #1E293B; border: 1px solid rgba(255,255,255,0.08); border-radius: 1.25rem; }
        .step-num { width: 28px; height: 28px; border-radius: 50%; background: #38BDF8; color: #0B192C; font-weight: bold; display: inline-flex; align-items: center; justify-content: center; }
        code { color: #38BDF8; background: rgba(56, 189, 248, 0.12); padding: 2px 6px; border-radius: 4px; }
    </style>';
    echo '</head><body class="p-4 d-flex align-items-center justify-content-center min-vh-100">';
    echo '<div class="container" style="max-width: 680px;">';
    echo '<div class="setup-card shadow-lg p-4 p-md-5 text-start">';
    
    echo '<div class="text-center mb-4">';
    echo '<div class="mb-3" style="font-size: 3rem;">📊</div>';
    echo '<h3 class="fw-bold text-white mb-2">Aktivasi Database Google Spreadsheet</h3>';
    echo '<p class="text-secondary small">Aplikasi berjalan di <strong>Vercel Serverless</strong> dan menggunakan <strong>Google Cloud Sheets API v4</strong> (tanpa membutuhkan server database lokal).</p>';
    echo '</div>';

    echo '<div class="p-3 mb-4 rounded-3" style="background: rgba(14, 165, 233, 0.1); border: 1px solid rgba(14, 165, 233, 0.25);">';
    echo '<div class="d-flex align-items-center justify-content-between">';
    echo '<span class="small text-secondary">Target Spreadsheet ID:</span>';
    echo '<span class="badge bg-primary text-wrap text-break font-monospace">' . $currSheetId . '</span>';
    echo '</div>';
    echo '</div>';

    echo '<h6 class="text-white fw-bold mb-3">Langkah Aktivasi di Vercel Dashboard:</h6>';
    echo '<ol class="list-unstyled d-flex flex-column gap-3 small">';
    echo '<li class="d-flex gap-3 align-items-start"><span class="step-num flex-shrink-0">1</span><div>Buka <strong>Vercel Dashboard</strong> &rarr; Pilih Proyek <strong>Maintenance-QR</strong> &rarr; Tab <strong>Settings</strong> &rarr; <strong>Environment Variables</strong>.</div></li>';
    echo '<li class="d-flex gap-3 align-items-start"><span class="step-num flex-shrink-0">2</span><div>Tambahkan salah satu opsi kredensial berikut:';
    echo '<div class="mt-2 p-3 rounded-2" style="background: #0F172A;">';
    echo '<div class="fw-bold text-info mb-1">Opsi A (Paling Praktis - 1 Variabel):</div>';
    echo '<div class="mb-2">Nama: <code>GOOGLE_SERVICE_ACCOUNT_JSON</code><br>Nilai: Salin dan tempel seluruh teks dari berkas Service Account <code>.json</code> Google Anda.</div>';
    echo '<div class="fw-bold text-info mb-1">Opsi B (Terpisah):</div>';
    echo '<div>- <code>GOOGLE_CLIENT_EMAIL</code>: Email Service Account Google Anda<br>- <code>GOOGLE_PRIVATE_KEY</code>: Private key dari Service Account Anda</div>';
    echo '</div></div></li>';
    echo '<li class="d-flex gap-3 align-items-start"><span class="step-num flex-shrink-0">3</span><div>Pastikan Google Sheet Anda telah dibagikan (<strong>Share / Bagikan</strong>) ke email Service Account tersebut dengan hak akses <strong>Editor</strong>.</div></li>';
    echo '<li class="d-flex gap-3 align-items-start"><span class="step-num flex-shrink-0">4</span><div>Klik <strong>Redeploy</strong> pada deployment terakhir Anda di Vercel. Aplikasi akan langsung aktif!</div></li>';
    echo '</ol>';

    if ($technicalMsg) {
        echo '<div class="mt-4 pt-3 border-top border-secondary border-opacity-25 text-secondary small"><em>Status Sistem: ' . htmlspecialchars($technicalMsg) . '</em></div>';
    }

    echo '</div></div></body></html>';
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $isVercelEnv = (
        !empty($_SERVER['VERCEL']) ||
        !empty($_ENV['VERCEL']) ||
        getenv('VERCEL') === '1' ||
        str_ends_with(strtolower((string)($_SERVER['HTTP_HOST'] ?? '')), '.vercel.app')
    );

    $host = cfg('db_host', envv('DB_HOST', envv('MYSQLHOST', '')));
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

    // Jika berjalan di Vercel dan tidak ada MySQL remote eksternal yang dikonfigurasi,
    // cegah loopback ke 127.0.0.1:3306 yang pasti gagal dan tampilkan panduan aktivasi Google Sheets.
    if ($isVercelEnv && (empty($host) || $host === '127.0.0.1' || $host === 'localhost') && empty($dbUrl)) {
        render_vercel_spreadsheet_setup_guide();
        exit;
    }

    if (empty($host)) {
        $host = '127.0.0.1';
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
        if ($isVercelEnv) {
            render_vercel_spreadsheet_setup_guide($e->getMessage());
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
            if (!headers_sent()) setcookie('_csrf_token', $_SESSION['_csrf'], [
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
    if (is_google_cloud_mode()) {
        $defaultCols = [
            'users' => ['id', 'nama', 'username', 'password', 'role', 'id_cabang', 'face_photo', 'passkey_credential', 'created_at'],
            'cabang' => ['id', 'nama_cabang', 'nama', 'alamat', 'telepon', 'penanggung_jawab'],
            'divisi' => ['id', 'nama_divisi', 'nama', 'id_cabang'],
            'kategori_aset' => ['id', 'nama_kategori', 'nama'],
            'karyawan' => ['id', 'nama_karyawan', 'nama', 'nip', 'id_cabang', 'id_divisi', 'jabatan'],
            'assets' => ['id', 'kode_inventaris', 'merk', 'model', 'serial_number', 'id_kategori', 'id_cabang', 'id_divisi', 'id_karyawan', 'status', 'keterangan', 'ruangan', 'ip_address', 'printer', 'created_at'],
            'asset_qr_tokens' => ['id', 'asset_id', 'token', 'placement_label', 'is_active', 'created_at'],
            'maintenance_scan' => ['id', 'asset_id', 'technician_user_id', 'technician_name', 'maintenance_date', 'maintenance_time', 'maintenance_month', 'maintenance_year', 'status', 'source', 'findings', 'created_at'],
            'maintenance_findings' => ['id', 'maintenance_scan_id', 'asset_id', 'kategori_temuan', 'deskripsi_temuan', 'tindakan_diperlukan', 'status', 'reported_by', 'reported_at', 'resolved_by', 'resolved_at', 'catatan_penyelesaian'],
            'inventaris_kartu' => ['id', 'kode_inventaris', 'nama_barang', 'kategori', 'lokasi', 'tahun_perolehan', 'kondisi', 'created_at'],
        ];
        $cache[$table] = $defaultCols[strtolower($table)] ?? [];
        return $cache[$table];
    }
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



