<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('Asia/Makassar');

function envv(string $key, ?string $fallback = null): ?string {
    $v = getenv($key);
    return ($v === false || $v === '') ? $fallback : $v;
}

function local_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $cfg = [];
    $file = __DIR__ . '/config.local.php';
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
    foreach (['role', 'user_role', 'level'] as $k) {
        if (!empty($_SESSION[$k])) return strtolower((string)$_SESSION[$k]);
    }
    return '';
}

function request_uri_full(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return $scheme . '://' . $host . $uri;
}

function module_base_url(): string {
    $configured = cfg('app_url', envv('APP_URL', ''));
    if ($configured) return rtrim($configured, '/');

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $dir = rtrim(dirname($script), '/');
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
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/' . ltrim($u, '/');
}

function require_login(): void {
    if (current_user_id() > 0) return;
    $_SESSION['after_login'] = request_uri_full();
    header('Location: ' . login_url());
    exit;
}

function require_admin(): void {
    require_login();
    $role = current_user_role();
    // Jika project lama belum menyimpan role di session, jangan memblokir.
    if ($role !== '' && !in_array($role, ['admin', 'administrator'], true)) {
        http_response_code(403);
        render_page('Akses Ditolak', '<div class="alert alert-danger">Menu ini hanya untuk admin.</div>');
        exit;
    }
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verify_csrf(): void {
    $sent = $_POST['_csrf'] ?? '';
    if (!$sent || !hash_equals($_SESSION['_csrf'] ?? '', $sent)) {
        http_response_code(419);
        render_page('Sesi Tidak Valid', '<div class="alert alert-danger">Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.</div>');
        exit;
    }
}

function e(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function table_columns(string $table): array {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];

    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) return [];
    $rows = db()->query("SHOW COLUMNS FROM `{$table}`")->fetchAll();
    $cache[$table] = array_column($rows, 'Field');
    return $cache[$table];
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

function asset_query_base(): string {
    $cCab = name_column('cabang') ?: 'id';
    $cDiv = name_column('divisi') ?: 'id';
    $cKar = name_column('karyawan') ?: 'id';
    $cKat = name_column('kategori_aset') ?: 'id';

    return "
        SELECT
            a.*,
            c.`{$cCab}` AS cabang_nama,
            d.`{$cDiv}` AS divisi_nama,
            k.`{$cKar}` AS karyawan_nama,
            ka.`{$cKat}` AS kategori_nama,
            q.token AS qr_token,
            q.placement_label,
            q.is_active AS qr_active
        FROM assets a
        LEFT JOIN cabang c ON c.id = a.id_cabang
        LEFT JOIN divisi d ON d.id = a.id_divisi
        LEFT JOIN karyawan k ON k.id = a.id_karyawan
        LEFT JOIN kategori_aset ka ON ka.id = a.id_kategori
        LEFT JOIN asset_qr_tokens q ON q.asset_id = a.id
    ";
}

function asset_title(array $a): string {
    $parts = [];
    if (!empty($a['kategori_nama'])) $parts[] = $a['kategori_nama'];
    if (!empty($a['merk'])) $parts[] = $a['merk'];
    if (!empty($a['model'])) $parts[] = $a['model'];
    $title = trim(implode(' ', $parts));
    if ($title === '') $title = 'Aset #' . ($a['id'] ?? '-');
    return $title;
}

function technician_name(int $userId): string {
    try {
        $nameCol = name_column('users');
        if (!$nameCol) return current_user_name();
        $st = db()->prepare("SELECT `{$nameCol}` AS n FROM users WHERE id = ? LIMIT 1");
        $st->execute([$userId]);
        $row = $st->fetch();
        return $row && $row['n'] ? (string)$row['n'] : current_user_name();
    } catch (Throwable $e) {
        return current_user_name();
    }
}

function render_page(string $title, string $content, string $extraHead = '', string $extraScript = ''): void {
    $nav = '';
    if (current_user_id() > 0) {
        $nav = '
        <nav class="navbar navbar-expand-lg bg-primary navbar-dark mb-4">
          <div class="container">
            <a class="navbar-brand fw-semibold" href="'.e(module_url('dashboard.php')).'">QR Maintenance</a>
            <div class="d-flex flex-wrap gap-2">
              <a class="btn btn-sm btn-light" href="'.e(module_url('dashboard.php')).'">Dashboard</a>
              <a class="btn btn-sm btn-outline-light" href="'.e(module_url('history.php')).'">Riwayat</a>
              <a class="btn btn-sm btn-outline-light" href="'.e(module_url('qr_admin.php')).'">QR Aset</a>
            </div>
          </div>
        </nav>';
    }

    echo '<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>'.e($title).'</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f6f8fb}
.card{border:0;box-shadow:0 8px 24px rgba(0,0,0,.05)}
.stat{font-size:1.75rem;font-weight:700}
.small-muted{font-size:.875rem;color:#6c757d}
.qr-label{background:#fff;border:1px solid #dee2e6;border-radius:14px;padding:14px;break-inside:avoid}
@media print{.no-print,nav{display:none!important}.qr-label{box-shadow:none}}
</style>
'.$extraHead.'
</head>
<body>
'.$nav.'
<main class="container pb-5">
'.$content.'
</main>
'.$extraScript.'
</body>
</html>';
}

function format_id_date(string $date): string {
    $ts = strtotime($date);
    return $ts ? date('d-m-Y', $ts) : $date;
}
