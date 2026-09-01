<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/spreadsheet_api.php';

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

function is_spreadsheet_mode(): bool {
    $url = cfg('spreadsheet_api_url', envv('SPREADSHEET_API_URL', ''));
    return !empty($url);
}

function spreadsheet_client(): ?SpreadsheetApiClient {
    static $client = null;
    if ($client !== null) return $client;
    $url = cfg('spreadsheet_api_url', envv('SPREADSHEET_API_URL', ''));
    if ($url) {
        $client = new SpreadsheetApiClient($url);
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

function current_user_id(): int {
    foreach (['user_id', 'id_user', 'id'] as $k) {
        if (!empty($_SESSION[$k]) && ctype_digit((string)$_SESSION[$k])) {
            return (int)$_SESSION[$k];
        }
    }
    if (is_spreadsheet_mode() && empty($_SESSION['user_id'])) {
        return 1;
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
    return 'admin';
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
    if (is_spreadsheet_mode()) return;
    if (current_user_id() > 0) return;
    $_SESSION['after_login'] = request_uri_full();
    header('Location: ' . login_url());
    exit;
}

function require_admin(): void {
    require_login();
    $role = current_user_role();
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
    if (is_spreadsheet_mode()) return current_user_name();
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

// DATA ABSTRACTION LAYER FOR SPREADSHEET VS MYSQL

function get_cabang_list(): array {
    if (is_spreadsheet_mode()) {
        $res = spreadsheet_client()->get('getCabangList');
        return $res['data'] ?? [];
    }
    try {
        $cName = name_column('cabang') ?: 'id';
        return db()->query("SELECT id, `{$cName}` AS nama, `{$cName}` AS nama_cabang FROM cabang ORDER BY `{$cName}`")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function get_dashboard_data(int $month, int $year, int $cabangId): array {
    if (is_spreadsheet_mode()) {
        $res = spreadsheet_client()->get('getDashboardData', [
            'bulan' => $month,
            'tahun' => $year,
            'cabang' => $cabangId
        ]);
        if ($res['success'] ?? false) {
            return [
                'total' => (int)($res['total'] ?? 0),
                'done' => (int)($res['done'] ?? 0),
                'findings' => (int)($res['findings'] ?? 0),
                'pendingRows' => $res['pendingRows'] ?? [],
                'recentRows' => $res['recentRows'] ?? [],
                'cabangs' => $res['cabangs'] ?? []
            ];
        }
        return ['total' => 0, 'done' => 0, 'findings' => 0, 'pendingRows' => [], 'recentRows' => [], 'cabangs' => []];
    }

    $cName = name_column('cabang') ?: 'id';
    $cabangs = db()->query("SELECT id, `{$cName}` AS nama FROM cabang ORDER BY `{$cName}`")->fetchAll();

    $params = [];
    $where = " WHERE (a.status = 'Aktif' OR a.status = 'aktif' OR a.status IS NULL OR a.status = '') ";
    if ($cabangId) {
        $where .= " AND a.id_cabang = ? ";
        $params[] = $cabangId;
    }

    $totalSt = db()->prepare("SELECT COUNT(*) FROM assets a {$where}");
    $totalSt->execute($params);
    $total = (int)$totalSt->fetchColumn();

    $scannedSql = "
        SELECT COUNT(*)
        FROM maintenance_scan ms
        JOIN assets a ON a.id = ms.asset_id
        {$where}
        AND ms.maintenance_month = ?
        AND ms.maintenance_year = ?
    ";
    $st = db()->prepare($scannedSql);
    $st->execute(array_merge($params, [$month, $year]));
    $done = (int)$st->fetchColumn();

    $findSql = "
        SELECT COUNT(*)
        FROM maintenance_scan ms
        JOIN assets a ON a.id = ms.asset_id
        {$where}
        AND ms.maintenance_month = ?
        AND ms.maintenance_year = ?
        AND ms.status = 'Temuan'
    ";
    $st = db()->prepare($findSql);
    $st->execute(array_merge($params, [$month, $year]));
    $findings = (int)$st->fetchColumn();

    $assetBase = asset_query_base();
    $pendingSql = $assetBase . "
        {$where}
        AND NOT EXISTS (
            SELECT 1 FROM maintenance_scan ms
            WHERE ms.asset_id = a.id
              AND ms.maintenance_month = ?
              AND ms.maintenance_year = ?
        )
        ORDER BY c.`{$cName}`, karyawan_nama, a.kode_inventaris
        LIMIT 300
    ";
    $st = db()->prepare($pendingSql);
    $st->execute(array_merge($params, [$month, $year]));
    $pendingRows = $st->fetchAll();

    $recentSql = "
        SELECT ms.*, a.kode_inventaris, a.merk, a.model,
               c.`{$cName}` AS cabang_nama,
               ".(name_column('karyawan') ? "k.`".name_column('karyawan')."`" : "k.id")." AS karyawan_nama
        FROM maintenance_scan ms
        JOIN assets a ON a.id = ms.asset_id
        LEFT JOIN cabang c ON c.id = a.id_cabang
        LEFT JOIN karyawan k ON k.id = a.id_karyawan
        WHERE ms.maintenance_month = ? AND ms.maintenance_year = ?
        ".($cabangId ? " AND a.id_cabang = ? " : "")."
        ORDER BY ms.maintenance_date DESC, ms.maintenance_time DESC
        LIMIT 12
    ";
    $recentParams = [$month, $year];
    if ($cabangId) $recentParams[] = $cabangId;
    $st = db()->prepare($recentSql);
    $st->execute($recentParams);
    $recentRows = $st->fetchAll();

    return [
        'total' => $total,
        'done' => $done,
        'findings' => $findings,
        'pendingRows' => $pendingRows,
        'recentRows' => $recentRows,
        'cabangs' => $cabangs
    ];
}

function get_asset_by_token(string $token): ?array {
    if (is_spreadsheet_mode()) {
        $res = spreadsheet_client()->get('getAssetByToken', ['token' => $token]);
        return ($res['success'] ?? false) ? ($res['asset'] ?? null) : null;
    }

    $sql = asset_query_base() . " WHERE q.token = ? AND q.is_active = 1 LIMIT 1";
    $st = db()->prepare($sql);
    $st->execute([$token]);
    $asset = $st->fetch();
    return $asset ?: null;
}

function record_scan(int $assetId, int $userId, string $techName, string $date, string $time, int $month, int $year): array {
    if (is_spreadsheet_mode()) {
        return spreadsheet_client()->post('saveMaintenanceScan', [
            'asset_id' => $assetId,
            'technician_user_id' => $userId,
            'technician_name' => $techName,
            'maintenance_date' => $date,
            'maintenance_time' => $time,
            'maintenance_month' => $month,
            'maintenance_year' => $year,
            'status' => 'Selesai',
            'source' => 'QR'
        ]);
    }

    $existing = db()->prepare("
        SELECT * FROM maintenance_scan
        WHERE asset_id = ? AND maintenance_month = ? AND maintenance_year = ?
        LIMIT 1
    ");
    $existing->execute([$assetId, $month, $year]);
    $old = $existing->fetch();
    if ($old) {
        return ['success' => false, 'is_duplicate' => true, 'existing' => $old];
    }

    try {
        $ins = db()->prepare("
            INSERT INTO maintenance_scan
            (asset_id, technician_user_id, maintenance_date, maintenance_time, maintenance_month, maintenance_year, status, source)
            VALUES (?, ?, ?, ?, ?, ?, 'Selesai', 'QR')
        ");
        $ins->execute([$assetId, $userId, $date, $time, $month, $year]);
        $logId = (int)db()->lastInsertId();
        return ['success' => true, 'log_id' => $logId];
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) == 1062) {
            return ['success' => false, 'is_duplicate' => true];
        }
        throw $e;
    }
}

function get_log_by_id(int $logId): ?array {
    if (is_spreadsheet_mode()) {
        $history = get_history_rows(0, 0, 0);
        foreach ($history as $h) {
            if ((int)($h['id'] ?? 0) === $logId) return $h;
        }
        return null;
    }
    $st = db()->prepare("
        SELECT ms.*, a.kode_inventaris, a.merk, a.model
        FROM maintenance_scan ms
        JOIN assets a ON a.id = ms.asset_id
        WHERE ms.id = ?
        LIMIT 1
    ");
    $st->execute([$logId]);
    $log = $st->fetch();
    return $log ?: null;
}

function record_finding_issue(int $logId, int $assetId, string $finding, string $action, string $severity, string $reporter): array {
    if (is_spreadsheet_mode()) {
        return spreadsheet_client()->post('saveFinding', [
            'maintenance_scan_id' => $logId,
            'asset_id' => $assetId,
            'kategori_temuan' => $severity,
            'deskripsi_temuan' => $finding,
            'tindakan_diperlukan' => $action,
            'reported_by' => $reporter
        ]);
    }
    db()->beginTransaction();
    try {
        $ins = db()->prepare("
            INSERT INTO maintenance_findings
            (maintenance_scan_id, asset_id, finding, action_taken, severity, repair_status, created_by)
            VALUES (?, ?, ?, ?, ?, 'Perlu Tindak Lanjut', ?)
        ");
        $ins->execute([$logId, $assetId, $finding, $action ?: null, $severity, current_user_id()]);

        $up = db()->prepare("UPDATE maintenance_scan SET status = 'Temuan' WHERE id = ?");
        $up->execute([$logId]);

        db()->commit();
        return ['success' => true];
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

function get_history_rows(int $month, int $year, int $cabangId, string $status = ''): array {
    if (is_spreadsheet_mode()) {
        $res = spreadsheet_client()->get('getHistory', [
            'bulan' => $month,
            'tahun' => $year,
            'cabang' => $cabangId,
            'status' => $status
        ]);
        return $res['rows'] ?? [];
    }

    $cName = name_column('cabang') ?: 'id';
    $kName = name_column('karyawan') ?: 'id';
    $uName = name_column('users') ?: 'id';

    $sql = "
    SELECT ms.*, a.kode_inventaris, a.merk, a.model,
           c.`{$cName}` AS cabang_nama,
           k.`{$kName}` AS karyawan_nama,
           u.`{$uName}` AS teknisi_nama
    FROM maintenance_scan ms
    JOIN assets a ON a.id = ms.asset_id
    LEFT JOIN cabang c ON c.id = a.id_cabang
    LEFT JOIN karyawan k ON k.id = a.id_karyawan
    LEFT JOIN users u ON u.id = ms.technician_user_id
    WHERE 1=1
    ";
    $params = [];
    if ($month > 0) { $sql .= " AND ms.maintenance_month = ? "; $params[] = $month; }
    if ($year > 0) { $sql .= " AND ms.maintenance_year = ? "; $params[] = $year; }
    if ($cabangId > 0) { $sql .= " AND a.id_cabang = ? "; $params[] = $cabangId; }
    if ($status !== '') { $sql .= " AND ms.status = ? "; $params[] = $status; }

    $sql .= " ORDER BY ms.maintenance_date DESC, ms.maintenance_time DESC LIMIT 1000";

    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function get_qr_admin_rows(int $cabangId): array {
    if (is_spreadsheet_mode()) {
        $res = spreadsheet_client()->get('getQrTokens', ['cabang' => $cabangId]);
        return $res['rows'] ?? [];
    }
    $where = " WHERE (a.status = 'Aktif' OR a.status = 'aktif' OR a.status IS NULL OR a.status = '') ";
    $params = [];
    if ($cabangId) {
        $where .= " AND a.id_cabang = ? ";
        $params[] = $cabangId;
    }
    $st = db()->prepare(asset_query_base() . $where . " ORDER BY cabang_nama, karyawan_nama, a.kode_inventaris LIMIT 1000");
    $st->execute($params);
    return $st->fetchAll();
}

function generate_missing_qr_tokens(int $cabangId): int {
    if (is_spreadsheet_mode()) {
        // Mode spreadsheet otomatis membuat QR token
        return 0;
    }
    $where = " WHERE (a.status = 'Aktif' OR a.status = 'aktif' OR a.status IS NULL OR a.status = '') ";
    $params = [];
    if ($cabangId) {
        $where .= " AND a.id_cabang = ? ";
        $params[] = $cabangId;
    }
    $st = db()->prepare("
        SELECT a.id FROM assets a
        LEFT JOIN asset_qr_tokens q ON q.asset_id = a.id
        {$where} AND q.id IS NULL
    ");
    $st->execute($params);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);

    $ins = db()->prepare("INSERT IGNORE INTO asset_qr_tokens (asset_id, token) VALUES (?, ?)");
    $created = 0;
    foreach ($ids as $id) {
        $ins->execute([(int)$id, bin2hex(random_bytes(16))]);
        $created += $ins->rowCount();
    }
    return $created;
}

function regenerate_qr_token(int $assetId): bool {
    if (is_spreadsheet_mode()) {
        return true;
    }
    if ($assetId > 0) {
        $new = bin2hex(random_bytes(16));
        $st = db()->prepare("
            INSERT INTO asset_qr_tokens (asset_id, token, is_active)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE token = VALUES(token), is_active = 1
        ");
        $st->execute([$assetId, $new]);
        return true;
    }
    return false;
}

function render_page(string $title, string $content, string $extraHead = '', string $extraScript = ''): void {
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
