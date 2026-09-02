<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/google_sheets_v4.php';

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

function current_user_id(): int {
    foreach (['user_id', 'id_user', 'id'] as $k) {
        if (!empty($_SESSION[$k]) && ctype_digit((string)$_SESSION[$k])) {
            return (int)$_SESSION[$k];
        }
    }
    if (is_google_cloud_mode() && empty($_SESSION['user_id'])) {
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
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/' . ltrim($u, '/');
}

function is_logged_in(): bool {
    $timeout = (int)cfg('session_timeout', envv('SESSION_TIMEOUT', '900'));
    $hasUser = (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0)
        || (!is_google_cloud_mode() && current_user_id() > 0);
    if (!$hasUser) return false;
    if (!empty($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity'] > $timeout)) {
        return false;
    }
    return true;
}

function require_login(): void {
    $timeout = (int)cfg('session_timeout', envv('SESSION_TIMEOUT', '900')); // 15 menit default (900 detik)

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
            return ['success' => true, 'name' => $envUser];
        }
    }

    // 2. Default admin: admin / admin123 (jika tidak ada env dan tidak ada MySQL)
    if ($envUser === '' && $envPass === '') {
        $defaultUsers = [
            ['username' => 'admin', 'password' => 'admin123', 'name' => 'Administrator', 'role' => 'admin'],
            ['username' => 'teknisi', 'password' => 'teknisi123', 'name' => 'Teknisi IT', 'role' => 'teknisi'],
        ];

        foreach ($defaultUsers as $du) {
            if ($username === $du['username'] && $password === $du['password']) {
                $_SESSION['user_id'] = ($du['role'] === 'admin') ? 1 : 2;
                $_SESSION['nama'] = $du['name'];
                $_SESSION['username'] = $du['username'];
                $_SESSION['role'] = $du['role'];
                $_SESSION['last_activity'] = time();
                return ['success' => true, 'name' => $du['name']];
            }
        }
    }

    // 3. Cek dari tabel users MySQL (jika mode MySQL)
    if (!is_google_cloud_mode()) {
        try {
            $nameCol = name_column('users') ?: 'nama';
            $st = db()->prepare("SELECT id, `{$nameCol}` AS nama, username, password, role FROM users WHERE username = ? LIMIT 1");
            $st->execute([$username]);
            $user = $st->fetch();

            if ($user) {
                $passMatch = false;
                $storedPass = (string)($user['password'] ?? '');

                // Support password_hash atau plain (legacy)
                if (str_starts_with($storedPass, '$2y$') || str_starts_with($storedPass, '$2a$')) {
                    $passMatch = password_verify($password, $storedPass);
                } else {
                    $passMatch = ($password === $storedPass);
                }

                if ($passMatch) {
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['nama'] = (string)($user['nama'] ?? $user['username']);
                    $_SESSION['username'] = (string)$user['username'];
                    $_SESSION['role'] = strtolower((string)($user['role'] ?? 'teknisi'));
                    $_SESSION['last_activity'] = time();
                    return ['success' => true, 'name' => (string)($user['nama'] ?? $user['username'])];
                }
            }
        } catch (Throwable $e) {
            // Tabel users tidak ada, lanjut ke default
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
    session_destroy();
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
    if (is_google_cloud_mode()) return current_user_name();
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

// DATA ABSTRACTION LAYER FOR GOOGLE CLOUD SHEETS API V4 VS MYSQL

function get_static_qr_token(int $assetId): string {
    return substr(hash('sha256', 'STATIC_QR_MAINTENANCE_KEY_SALT_' . $assetId), 0, 32);
}

function map_sheets_assets(bool $refresh = false): array {
    static $cachedAssets = null;
    if ($cachedAssets !== null && !$refresh) {
        return $cachedAssets;
    }

    $client = google_sheets_v4_client();
    if (!$client) return [];

    $assets = $client->getSheetData('Assets', $refresh);
    // Filter baris kosong / yang sudah dihapus
    $assets = array_values(array_filter($assets, function($a) {
        return !empty($a['id']) && (int)$a['id'] > 0;
    }));
    $cabangRows = $client->getSheetData('Cabang', $refresh);
    $divRows = $client->getSheetData('Divisi', $refresh);
    $karRows = $client->getSheetData('Karyawan', $refresh);
    $katRows = $client->getSheetData('Kategori_Aset', $refresh);
    $qrRows = $client->getSheetData('Asset_QR_Tokens', $refresh);

    $cabangMap = []; foreach ($cabangRows as $r) $cabangMap[$r['id'] ?? 0] = $r['nama_cabang'] ?? $r['nama'] ?? '';
    $divMap = []; foreach ($divRows as $r) $divMap[$r['id'] ?? 0] = $r['nama_divisi'] ?? $r['nama'] ?? '';
    $karMap = []; foreach ($karRows as $r) $karMap[$r['id'] ?? 0] = $r['nama_karyawan'] ?? $r['nama'] ?? '';
    $katMap = []; foreach ($katRows as $r) $katMap[$r['id'] ?? 0] = $r['nama_kategori'] ?? $r['nama'] ?? '';
    $qrMap = []; foreach ($qrRows as $r) { $aid = (int)($r['asset_id'] ?? 0); if ($aid > 0) $qrMap[$aid] = $r; }

    $cachedAssets = array_map(function($a) use ($cabangMap, $divMap, $karMap, $katMap, $qrMap) {
        $id = (int)($a['id'] ?? 0);
        $qr = $qrMap[$id] ?? [];
        $isAct = strtolower(trim((string)($qr['is_active'] ?? '1')));
        $qrActive = ($isAct === '0' || $isAct === 'false' || $isAct === 'off') ? 0 : 1;
        $token = trim((string)($qr['token'] ?? ''));
        if ($token === '' && $id > 0) {
            $token = get_static_qr_token($id);
        }
        return [
            'id' => $id,
            'kode_inventaris' => $a['kode_inventaris'] ?? '',
            'merk' => $a['merk'] ?? '',
            'model' => $a['model'] ?? '',
            'serial_number' => $a['serial_number'] ?? '',
            'id_kategori' => (int)($a['id_kategori'] ?? 0),
            'id_cabang' => (int)($a['id_cabang'] ?? 0),
            'id_divisi' => (int)($a['id_divisi'] ?? 0),
            'id_karyawan' => (int)($a['id_karyawan'] ?? 0),
            'status' => $a['status'] ?? 'Aktif',
            'keterangan' => $a['keterangan'] ?? '',
            'cabang_nama' => $cabangMap[$a['id_cabang'] ?? 0] ?? '-',
            'divisi_nama' => $divMap[$a['id_divisi'] ?? 0] ?? '-',
            'karyawan_nama' => $karMap[$a['id_karyawan'] ?? 0] ?? '-',
            'kategori_nama' => $katMap[$a['id_kategori'] ?? 0] ?? '-',
            'qr_token' => $token,
            'placement_label' => $qr['placement_label'] ?? 'Bodi Top',
            'qr_active' => $qrActive
        ];
    }, $assets);

    return $cachedAssets;
}

function get_cabang_list(): array {
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        return $client ? $client->getSheetData('Cabang') : [];
    }
    try {
        $cName = name_column('cabang') ?: 'id';
        return db()->query("SELECT id, `{$cName}` AS nama, `{$cName}` AS nama_cabang FROM cabang ORDER BY `{$cName}`")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function get_cabang_by_id(int $id): ?array {
    if ($id <= 0) return null;
    $cabangs = get_cabang_list();
    foreach ($cabangs as $c) {
        if ((int)($c['id'] ?? 0) === $id) return $c;
    }
    return null;
}

function create_new_cabang(array $data): array {
    $nama = trim((string)($data['nama_cabang'] ?? $data['nama'] ?? ''));
    $alamat = trim((string)($data['alamat'] ?? ''));
    $telepon = trim((string)($data['telepon'] ?? ''));
    $penanggungJawab = trim((string)($data['penanggung_jawab'] ?? ''));

    if ($nama === '') {
        return ['success' => false, 'error' => 'Nama cabang wajib diisi'];
    }

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];

        $rows = $client->getSheetData('Cabang', true);
        $maxId = 0;
        foreach ($rows as $r) {
            $cid = (int)($r['id'] ?? 0);
            if ($cid > $maxId) $maxId = $cid;
            $existName = trim((string)($r['nama_cabang'] ?? $r['nama'] ?? ''));
            if (strcasecmp($existName, $nama) === 0) {
                return ['success' => false, 'error' => 'Nama cabang sudah terdaftar'];
            }
        }

        $newId = max(count($rows) + 1, $maxId + 1);
        $appended = $client->appendValues('Cabang!A:E', [[
            $newId,
            $nama,
            $alamat,
            $telepon,
            $penanggungJawab
        ]]);

        if (!$appended) {
            return ['success' => false, 'error' => 'Gagal menyimpan data cabang ke Google Sheets'];
        }

        $client->clearCache('Cabang');

        return [
            'success' => true,
            'id' => $newId,
            'nama' => $nama
        ];
    }

    // MySQL Mode
    try {
        $cName = name_column('cabang') ?: 'nama_cabang';
        $checkSt = db()->prepare("SELECT id FROM cabang WHERE LOWER(`{$cName}`) = LOWER(?) LIMIT 1");
        $checkSt->execute([$nama]);
        if ($checkSt->fetchColumn()) {
            return ['success' => false, 'error' => 'Nama cabang sudah terdaftar'];
        }

        $ins = db()->prepare("INSERT INTO cabang (`{$cName}`) VALUES (?)");
        $ins->execute([$nama]);
        $newId = (int)db()->lastInsertId();

        return [
            'success' => true,
            'id' => $newId,
            'nama' => $nama
        ];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function update_cabang(int $id, array $data): array {
    if ($id <= 0) return ['success' => false, 'error' => 'ID cabang tidak valid'];
    $nama = trim((string)($data['nama_cabang'] ?? $data['nama'] ?? ''));
    $alamat = trim((string)($data['alamat'] ?? ''));
    $telepon = trim((string)($data['telepon'] ?? ''));
    $penanggungJawab = trim((string)($data['penanggung_jawab'] ?? ''));

    if ($nama === '') {
        return ['success' => false, 'error' => 'Nama cabang wajib diisi'];
    }

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];

        $rows = $client->getSheetData('Cabang', true);
        $targetRow = null;
        foreach ($rows as $r) {
            if ((int)($r['id'] ?? 0) === $id) {
                $targetRow = $r;
                break;
            }
        }

        if (!$targetRow) {
            return ['success' => false, 'error' => 'Data cabang tidak ditemukan'];
        }

        $rowNum = (int)($targetRow['_row_num'] ?? 0);
        if ($rowNum <= 1) {
            return ['success' => false, 'error' => 'Gagal menentukan baris data cabang'];
        }

        $updated = $client->updateValues("Cabang!A{$rowNum}:E{$rowNum}", [[
            $id,
            $nama,
            $alamat,
            $telepon,
            $penanggungJawab
        ]]);

        if (!$updated) {
            return ['success' => false, 'error' => 'Gagal memperbarui data di Google Sheets'];
        }

        $client->clearCache('Cabang');
        map_sheets_assets(true);

        return ['success' => true, 'id' => $id, 'nama' => $nama];
    }

    // MySQL Mode
    try {
        $cName = name_column('cabang') ?: 'nama_cabang';
        $up = db()->prepare("UPDATE cabang SET `{$cName}` = ? WHERE id = ?");
        $up->execute([$nama, $id]);
        return ['success' => true, 'id' => $id, 'nama' => $nama];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function get_divisi_list(): array {
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        return $client ? $client->getSheetData('Divisi') : [];
    }
    try {
        $dName = name_column('divisi') ?: 'id';
        return db()->query("SELECT id, `{$dName}` AS nama, `{$dName}` AS nama_divisi FROM divisi ORDER BY `{$dName}`")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function get_kategori_list(): array {
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        return $client ? $client->getSheetData('Kategori_Aset') : [];
    }
    try {
        $kName = name_column('kategori_aset') ?: 'id';
        return db()->query("SELECT id, `{$kName}` AS nama, `{$kName}` AS nama_kategori FROM kategori_aset ORDER BY `{$kName}`")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function get_karyawan_list(int $cabangId = 0): array {
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        $rows = $client ? $client->getSheetData('Karyawan') : [];
        if ($cabangId > 0) {
            $rows = array_filter($rows, function($k) use ($cabangId) {
                return (int)($k['id_cabang'] ?? 0) === $cabangId;
            });
        }
        return array_values($rows);
    }
    try {
        $kName = name_column('karyawan') ?: 'id';
        $sql = "SELECT id, `{$kName}` AS nama, `{$kName}` AS nama_karyawan, id_cabang, id_divisi FROM karyawan";
        $params = [];
        if ($cabangId > 0) {
            $sql .= " WHERE id_cabang = ?";
            $params[] = $cabangId;
        }
        $sql .= " ORDER BY `{$kName}`";
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function create_new_asset(array $data): array {
    $kode = trim((string)($data['kode_inventaris'] ?? ''));
    $merk = trim((string)($data['merk'] ?? ''));
    $model = trim((string)($data['model'] ?? ''));
    $sn = trim((string)($data['serial_number'] ?? ''));
    $idKat = (int)($data['id_kategori'] ?? 0);
    $idCab = (int)($data['id_cabang'] ?? 0);
    $idDiv = (int)($data['id_divisi'] ?? 0);
    $idKar = (int)($data['id_karyawan'] ?? 0);
    $namaKar = trim((string)($data['nama_karyawan'] ?? $data['custom_karyawan'] ?? ''));
    $status = trim((string)($data['status'] ?? 'Aktif')) ?: 'Aktif';
    $ket = trim((string)($data['keterangan'] ?? ''));
    $placement = trim((string)($data['placement_label'] ?? 'Bodi Casing')) ?: 'Bodi Casing';

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) {
            return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];
        }

        // Cari atau buat karyawan jika nama diisi
        if ($namaKar !== '') {
            $karRows = $client->getSheetData('Karyawan');
            $foundKarId = 0;
            foreach ($karRows as $kr) {
                $kName = trim((string)($kr['nama_karyawan'] ?? $kr['nama'] ?? ''));
                if (strcasecmp($kName, $namaKar) === 0) {
                    $foundKarId = (int)($kr['id'] ?? 0);
                    break;
                }
            }
            if ($foundKarId > 0) {
                $idKar = $foundKarId;
            } else {
                $newKarId = count($karRows) + 1;
                $client->appendValues('Karyawan!A:D', [[$newKarId, $namaKar, $idCab, $idDiv]]);
                $idKar = $newKarId;
            }
        }

        $assets = $client->getSheetData('Assets');
        $maxId = 0;
        foreach ($assets as $a) {
            $aid = (int)($a['id'] ?? 0);
            if ($aid > $maxId) $maxId = $aid;
        }
        $newAssetId = max(count($assets) + 1, $maxId + 1);

        if ($kode === '') {
            $kode = sprintf('INV-IT-%03d', $newAssetId);
        }

        $assetRow = [
            $newAssetId,
            $kode,
            $merk,
            $model,
            $sn,
            $idKat,
            $idCab,
            $idDiv,
            $idKar,
            $status,
            $ket
        ];

        $appended = $client->appendValues('Assets!A:K', [$assetRow]);
        if (!$appended) {
            return ['success' => false, 'error' => 'Gagal menyimpan data ke tab Assets'];
        }

        // Generate Token QR
        $qrRows = $client->getSheetData('Asset_QR_Tokens');
        $nextQrId = count($qrRows) + 1;
        $token = bin2hex(random_bytes(16));

        $client->appendValues('Asset_QR_Tokens!A:F', [[
            $nextQrId,
            $newAssetId,
            $token,
            $placement,
            1,
            date('Y-m-d H:i:s')
        ]]);

        return [
            'success' => true,
            'asset_id' => $newAssetId,
            'kode_inventaris' => $kode,
            'qr_token' => $token,
            'karyawan_nama' => $namaKar
        ];
    }

    // MySQL Mode
    try {
        if ($namaKar !== '') {
            $kName = name_column('karyawan') ?: 'nama_karyawan';
            $findSt = db()->prepare("SELECT id FROM karyawan WHERE LOWER(`{$kName}`) = LOWER(?) LIMIT 1");
            $findSt->execute([$namaKar]);
            $existingKarId = (int)$findSt->fetchColumn();
            if ($existingKarId > 0) {
                $idKar = $existingKarId;
            } else {
                $insKar = db()->prepare("INSERT INTO karyawan (`{$kName}`, id_cabang, id_divisi) VALUES (?, ?, ?)");
                $insKar->execute([$namaKar, $idCab, $idDiv]);
                $idKar = (int)db()->lastInsertId();
            }
        }

        if ($kode === '') {
            $countSt = db()->query("SELECT MAX(id) FROM assets");
            $nextVal = ((int)$countSt->fetchColumn()) + 1;
            $kode = sprintf('INV-IT-%03d', $nextVal);
        }

        $ins = db()->prepare("
            INSERT INTO assets
            (kode_inventaris, merk, model, serial_number, id_kategori, id_cabang, id_divisi, id_karyawan, status, keterangan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $kode, $merk, $model, $sn, $idKat, $idCab, $idDiv, $idKar, $status, $ket
        ]);
        $assetId = (int)db()->lastInsertId();

        $token = bin2hex(random_bytes(16));
        $insQr = db()->prepare("
            INSERT INTO asset_qr_tokens (asset_id, token, placement_label, is_active)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE token = VALUES(token), placement_label = VALUES(placement_label), is_active = 1
        ");
        $insQr->execute([$assetId, $token, $placement]);

        return [
            'success' => true,
            'asset_id' => $assetId,
            'kode_inventaris' => $kode,
            'qr_token' => $token,
            'karyawan_nama' => $namaKar
        ];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function get_asset_by_id(int $id): ?array {
    if ($id <= 0) return null;
    if (is_google_cloud_mode()) {
        $assets = map_sheets_assets(true);
        foreach ($assets as $a) {
            if ((int)($a['id'] ?? 0) === $id) {
                return $a;
            }
        }
        return null;
    }

    try {
        $base = asset_query_base();
        $st = db()->prepare($base . " WHERE a.id = ? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function update_asset(int $id, array $data): array {
    if ($id <= 0) return ['success' => false, 'error' => 'ID aset tidak valid'];

    $kode = trim((string)($data['kode_inventaris'] ?? ''));
    $merk = trim((string)($data['merk'] ?? ''));
    $model = trim((string)($data['model'] ?? ''));
    $sn = trim((string)($data['serial_number'] ?? ''));
    $idKat = (int)($data['id_kategori'] ?? 0);
    $idCab = (int)($data['id_cabang'] ?? 0);
    $idDiv = (int)($data['id_divisi'] ?? 0);
    $idKar = (int)($data['id_karyawan'] ?? 0);
    $namaKar = trim((string)($data['nama_karyawan'] ?? $data['custom_karyawan'] ?? ''));
    $status = trim((string)($data['status'] ?? 'Aktif')) ?: 'Aktif';
    $ket = trim((string)($data['keterangan'] ?? ''));
    $placement = trim((string)($data['placement_label'] ?? 'Bodi Casing')) ?: 'Bodi Casing';

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) {
            return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];
        }

        // Cari atau buat karyawan jika nama diisi
        if ($namaKar !== '') {
            $karRows = $client->getSheetData('Karyawan');
            $foundKarId = 0;
            foreach ($karRows as $kr) {
                $kName = trim((string)($kr['nama_karyawan'] ?? $kr['nama'] ?? ''));
                if (strcasecmp($kName, $namaKar) === 0) {
                    $foundKarId = (int)($kr['id'] ?? 0);
                    break;
                }
            }
            if ($foundKarId > 0) {
                $idKar = $foundKarId;
            } else {
                $newKarId = count($karRows) + 1;
                $client->appendValues('Karyawan!A:D', [[$newKarId, $namaKar, $idCab, $idDiv]]);
                $idKar = $newKarId;
            }
        }

        $assets = $client->getSheetData('Assets');
        $targetRow = null;
        foreach ($assets as $a) {
            if ((int)($a['id'] ?? 0) === $id) {
                $targetRow = $a;
                break;
            }
        }

        if (!$targetRow) {
            return ['success' => false, 'error' => 'Aset dengan ID tersebut tidak ditemukan'];
        }

        $rowNum = (int)($targetRow['_row_num'] ?? 0);
        if ($rowNum <= 1) {
            return ['success' => false, 'error' => 'Gagal menentukan baris data aset'];
        }

        if ($kode === '') {
            $kode = sprintf('INV-IT-%03d', $id);
        }

        $assetRow = [
            $id,
            $kode,
            $merk,
            $model,
            $sn,
            $idKat,
            $idCab,
            $idDiv,
            $idKar,
            $status,
            $ket
        ];

        $updated = $client->updateValues("Assets!A{$rowNum}:K{$rowNum}", [$assetRow]);
        if (!$updated) {
            return ['success' => false, 'error' => 'Gagal memperbarui data di Google Sheets'];
        }

        // Update placement label di Asset_QR_Tokens jika ada
        $qrRows = $client->getSheetData('Asset_QR_Tokens');
        foreach ($qrRows as $q) {
            if ((int)($q['asset_id'] ?? 0) === $id) {
                $qrRowNum = (int)($q['_row_num'] ?? 0);
                if ($qrRowNum > 1) {
                    $client->updateValues("Asset_QR_Tokens!D{$qrRowNum}", [[$placement]]);
                }
                break;
            }
        }

        map_sheets_assets(true);

        return [
            'success' => true,
            'asset_id' => $id,
            'kode_inventaris' => $kode,
            'karyawan_nama' => $namaKar
        ];
    }

    // MySQL Mode
    try {
        if ($namaKar !== '') {
            $kName = name_column('karyawan') ?: 'nama_karyawan';
            $findSt = db()->prepare("SELECT id FROM karyawan WHERE LOWER(`{$kName}`) = LOWER(?) LIMIT 1");
            $findSt->execute([$namaKar]);
            $existingKarId = (int)$findSt->fetchColumn();
            if ($existingKarId > 0) {
                $idKar = $existingKarId;
            } else {
                $insKar = db()->prepare("INSERT INTO karyawan (`{$kName}`, id_cabang, id_divisi) VALUES (?, ?, ?)");
                $insKar->execute([$namaKar, $idCab, $idDiv]);
                $idKar = (int)db()->lastInsertId();
            }
        }

        if ($kode === '') {
            $kode = sprintf('INV-IT-%03d', $id);
        }

        $upSt = db()->prepare("
            UPDATE assets
            SET kode_inventaris = ?, merk = ?, model = ?, serial_number = ?,
                id_kategori = ?, id_cabang = ?, id_divisi = ?, id_karyawan = ?,
                status = ?, keterangan = ?
            WHERE id = ?
        ");
        $upSt->execute([
            $kode, $merk, $model, $sn, $idKat, $idCab, $idDiv, $idKar, $status, $ket, $id
        ]);

        $upQr = db()->prepare("UPDATE asset_qr_tokens SET placement_label = ? WHERE asset_id = ?");
        $upQr->execute([$placement, $id]);

        return [
            'success' => true,
            'asset_id' => $id,
            'kode_inventaris' => $kode,
            'karyawan_nama' => $namaKar
        ];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function delete_asset(int $id): array {
    if ($id <= 0) return ['success' => false, 'error' => 'ID aset tidak valid'];

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) {
            return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];
        }

        $assets = $client->getSheetData('Assets', true);
        $targetRow = null;
        foreach ($assets as $a) {
            if ((int)($a['id'] ?? 0) === $id) {
                $targetRow = $a;
                break;
            }
        }

        if (!$targetRow) {
            return ['success' => false, 'error' => 'Data aset tidak ditemukan'];
        }

        $rowNum = (int)($targetRow['_row_num'] ?? 0);
        if ($rowNum > 1) {
            $client->clearValues("Assets!A{$rowNum}:K{$rowNum}");
        }

        // Hapus token QR terkait jika ada
        $qrRows = $client->getSheetData('Asset_QR_Tokens', true);
        foreach ($qrRows as $q) {
            if ((int)($q['asset_id'] ?? 0) === $id) {
                $qrRowNum = (int)($q['_row_num'] ?? 0);
                if ($qrRowNum > 1) {
                    $client->clearValues("Asset_QR_Tokens!A{$qrRowNum}:F{$qrRowNum}");
                }
            }
        }

        $client->clearCache();
        map_sheets_assets(true);
        return ['success' => true];
    }

    // MySQL Mode
    try {
        $st = db()->prepare("DELETE FROM asset_qr_tokens WHERE asset_id = ?");
        $st->execute([$id]);

        $st = db()->prepare("DELETE FROM maintenance_sessions WHERE asset_id = ?");
        $st->execute([$id]);

        $st = db()->prepare("DELETE FROM assets WHERE id = ?");
        $st->execute([$id]);

        return ['success' => true];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => 'Gagal menghapus aset dari database: ' . $e->getMessage()];
    }
}

function get_dashboard_data(int $month, int $year, int $cabangId): array {
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return ['total' => 0, 'done' => 0, 'findings' => 0, 'pendingRows' => [], 'recentRows' => [], 'cabangs' => []];

        $assets = array_filter(map_sheets_assets(), function($a) use ($cabangId) {
            $st = strtolower($a['status']);
            $active = ($st === 'aktif' || $st === '');
            if (!$active) return false;
            if ($cabangId > 0 && $a['id_cabang'] !== $cabangId) return false;
            return true;
        });

        $scans = $client->getSheetData('Maintenance_Scan');
        $scannedAssetIds = [];
        $findingAssetIds = [];

        foreach ($scans as $s) {
            if ((int)($s['maintenance_month'] ?? 0) === $month && (int)($s['maintenance_year'] ?? 0) === $year) {
                $aid = (int)($s['asset_id'] ?? 0);
                $scannedAssetIds[$aid] = true;
                if (($s['status'] ?? '') === 'Temuan') {
                    $findingAssetIds[$aid] = true;
                }
            }
        }

        $total = count($assets);
        $done = 0;
        $findings = 0;
        $pendingRows = [];

        foreach ($assets as $a) {
            if (!empty($scannedAssetIds[$a['id']])) {
                $done++;
                if (!empty($findingAssetIds[$a['id']])) $findings++;
            } else {
                $pendingRows[] = $a;
            }
        }

        $assetMap = []; foreach (map_sheets_assets() as $a) $assetMap[$a['id']] = $a;
        $recentRows = [];

        foreach ($scans as $s) {
            if ((int)($s['maintenance_month'] ?? 0) === $month && (int)($s['maintenance_year'] ?? 0) === $year) {
                $a = $assetMap[(int)($s['asset_id'] ?? 0)] ?? [];
                if ($cabangId > 0 && ($a['id_cabang'] ?? 0) !== $cabangId) continue;

                $recentRows[] = [
                    'id' => $s['id'] ?? 0,
                    'asset_id' => $s['asset_id'] ?? 0,
                    'maintenance_date' => substr((string)($s['maintenance_date'] ?? ''), 0, 10),
                    'maintenance_time' => substr((string)($s['maintenance_time'] ?? ''), 0, 8),
                    'status' => $s['status'] ?? 'Selesai',
                    'kode_inventaris' => $a['kode_inventaris'] ?? '-',
                    'merk' => $a['merk'] ?? '',
                    'model' => $a['model'] ?? '',
                    'karyawan_nama' => $a['karyawan_nama'] ?? '-',
                    'cabang_nama' => $a['cabang_nama'] ?? '-'
                ];
            }
        }

        return [
            'total' => $total,
            'done' => $done,
            'findings' => $findings,
            'pendingRows' => array_values($pendingRows),
            'recentRows' => array_values($recentRows),
            'cabangs' => get_cabang_list()
        ];
    }

    // MySQL mode
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

function get_branch_maintenance_summary(int $month, int $year): array {
    $cabangs = get_cabang_list();
    $results = [];

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        $allAssets = array_filter(map_sheets_assets(), function($a) {
            $st = strtolower($a['status']);
            return ($st === 'aktif' || $st === '');
        });

        $scans = $client ? $client->getSheetData('Maintenance_Scan') : [];
        $scannedAssetIds = [];
        $findingAssetIds = [];

        foreach ($scans as $s) {
            if ((int)($s['maintenance_month'] ?? 0) === $month && (int)($s['maintenance_year'] ?? 0) === $year) {
                $aid = (int)($s['asset_id'] ?? 0);
                $scannedAssetIds[$aid] = true;
                if (($s['status'] ?? '') === 'Temuan') {
                    $findingAssetIds[$aid] = true;
                }
            }
        }

        foreach ($cabangs as $c) {
            $cId = (int)($c['id'] ?? 0);
            $cName = $c['nama'] ?? $c['nama_cabang'] ?? 'Cabang #' . $cId;
            
            $bAssets = array_filter($allAssets, fn($a) => (int)($a['id_cabang'] ?? 0) === $cId);
            $total = count($bAssets);
            $done = 0;
            $findings = 0;

            foreach ($bAssets as $a) {
                if (!empty($scannedAssetIds[$a['id']])) {
                    $done++;
                    if (!empty($findingAssetIds[$a['id']])) $findings++;
                }
            }

            $pending = max(0, $total - $done);
            $percent = $total > 0 ? round(($done / $total) * 100) : 0;

            $results[] = [
                'id' => $cId,
                'nama' => $cName,
                'total' => $total,
                'done' => $done,
                'pending' => $pending,
                'findings' => $findings,
                'percent' => $percent
            ];
        }

        return $results;
    }

    // MySQL Mode
    $cName = name_column('cabang') ?: 'id';
    foreach ($cabangs as $c) {
        $cId = (int)($c['id'] ?? 0);
        $cNama = $c['nama'] ?? $c['nama_cabang'] ?? 'Cabang #' . $cId;

        $totSt = db()->prepare("SELECT COUNT(*) FROM assets WHERE (status = 'Aktif' OR status = 'aktif' OR status IS NULL OR status = '') AND id_cabang = ?");
        $totSt->execute([$cId]);
        $total = (int)$totSt->fetchColumn();

        $doneSt = db()->prepare("
            SELECT COUNT(*) FROM maintenance_scan ms
            JOIN assets a ON a.id = ms.asset_id
            WHERE (a.status = 'Aktif' OR a.status = 'aktif' OR a.status IS NULL OR a.status = '')
              AND a.id_cabang = ?
              AND ms.maintenance_month = ?
              AND ms.maintenance_year = ?
        ");
        $doneSt->execute([$cId, $month, $year]);
        $done = (int)$doneSt->fetchColumn();

        $findSt = db()->prepare("
            SELECT COUNT(*) FROM maintenance_scan ms
            JOIN assets a ON a.id = ms.asset_id
            WHERE (a.status = 'Aktif' OR a.status = 'aktif' OR a.status IS NULL OR a.status = '')
              AND a.id_cabang = ?
              AND ms.maintenance_month = ?
              AND ms.maintenance_year = ?
              AND ms.status = 'Temuan'
        ");
        $findSt->execute([$cId, $month, $year]);
        $findings = (int)$findSt->fetchColumn();

        $pending = max(0, $total - $done);
        $percent = $total > 0 ? round(($done / $total) * 100) : 0;

        $results[] = [
            'id' => $cId,
            'nama' => $cNama,
            'total' => $total,
            'done' => $done,
            'pending' => $pending,
            'findings' => $findings,
            'percent' => $percent
        ];
    }

    return $results;
}

function get_asset_by_token(string $token): ?array {
    $token = trim($token);
    if ($token === '') return null;

    if (is_google_cloud_mode()) {
        $assets = map_sheets_assets();
        foreach ($assets as $a) {
            $aid = (int)($a['id'] ?? 0);
            $staticTok = get_static_qr_token($aid);
            $qrToken = (string)($a['qr_token'] ?? '');
            $kode = (string)($a['kode_inventaris'] ?? '');

            if (
                strcasecmp($qrToken, $token) === 0 ||
                strcasecmp($staticTok, $token) === 0 ||
                (is_numeric($token) && (int)$token === $aid) ||
                ($kode !== '' && strcasecmp($kode, $token) === 0)
            ) {
                return $a;
            }
        }
        // Direct fallback check on Asset_QR_Tokens tab
        $client = google_sheets_v4_client();
        if ($client) {
            $qrRows = $client->getSheetData('Asset_QR_Tokens');
            foreach ($qrRows as $q) {
                $qToken = trim((string)($q['token'] ?? ''));
                if ($qToken !== '' && strcasecmp($qToken, $token) === 0) {
                    $targetAssetId = (int)($q['asset_id'] ?? 0);
                    foreach ($assets as $a) {
                        if ((int)$a['id'] === $targetAssetId) {
                            return $a;
                        }
                    }
                }
            }
        }
        return null;
    }

    $sql = asset_query_base() . " WHERE (q.token = ? OR a.kode_inventaris = ? " . (is_numeric($token) ? " OR a.id = ? " : "") . ") LIMIT 1";
    $st = db()->prepare($sql);
    $params = is_numeric($token) ? [$token, $token, (int)$token] : [$token, $token];
    $st->execute($params);
    $asset = $st->fetch();
    return $asset ?: null;
}

function get_fixed_checklists(): array {
    return [
        1 => 'Scan Virus',
        2 => 'Update Anti Virus',
        3 => 'Deleting Temporary File',
        4 => 'Cek Keyboard',
        5 => 'Cek Mouse',
        6 => 'Cek CPU & Monitor',
        7 => 'Cek Tinta',
        8 => 'Cek Cartridge',
        9 => 'Cek Nozzle'
    ];
}

function get_asset_maintenance_status_month(int $assetId, int $month, int $year): ?array {
    if ($assetId <= 0) return null;
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return null;
        $scans = $client->getSheetData('Maintenance_Scan');
        $latest = null;
        foreach ($scans as $s) {
            if ((int)($s['asset_id'] ?? 0) === $assetId && (int)($s['maintenance_month'] ?? 0) === $month && (int)($s['maintenance_year'] ?? 0) === $year) {
                $latest = $s;
            }
        }
        return $latest;
    }
    try {
        $st = db()->prepare("
            SELECT ms.*, u.nama AS teknisi_nama
            FROM maintenance_scan ms
            LEFT JOIN users u ON u.id = ms.technician_user_id
            WHERE ms.asset_id = ? AND ms.maintenance_month = ? AND ms.maintenance_year = ?
            ORDER BY ms.id DESC LIMIT 1
        ");
        $st->execute([$assetId, $month, $year]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function get_asset_maintenance_history(int $assetId): array {
    if ($assetId <= 0) return [];
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return [];
        $scans = $client->getSheetData('Maintenance_Scan');
        $history = [];
        foreach ($scans as $s) {
            if ((int)($s['asset_id'] ?? 0) === $assetId) {
                $history[] = [
                    'id' => (int)($s['id'] ?? 0),
                    'asset_id' => (int)($s['asset_id'] ?? 0),
                    'maintenance_date' => substr((string)($s['maintenance_date'] ?? ''), 0, 10),
                    'maintenance_time' => substr((string)($s['maintenance_time'] ?? ''), 0, 8),
                    'maintenance_month' => (int)($s['maintenance_month'] ?? 0),
                    'maintenance_year' => (int)($s['maintenance_year'] ?? 0),
                    'technician_name' => $s['technician_name'] ?? 'Teknisi',
                    'maintenance_type' => $s['source'] ?? $s['maintenance_type'] ?? 'Maintenance',
                    'findings' => $s['findings'] ?? '',
                    'recommendation' => $s['recommendation'] ?? '',
                    'status' => $s['status'] ?? 'Selesai'
                ];
            }
        }
        usort($history, function($a, $b) {
            return strcmp((string)($b['maintenance_date'] ?? ''), (string)($a['maintenance_date'] ?? ''));
        });
        return $history;
    }
    try {
        $st = db()->prepare("
            SELECT ms.*, COALESCE(ms.technician_name, u.nama, 'Teknisi') AS technician_name
            FROM maintenance_scan ms
            LEFT JOIN users u ON u.id = ms.technician_user_id
            WHERE ms.asset_id = ?
            ORDER BY ms.maintenance_date DESC, ms.id DESC
        ");
        $st->execute([$assetId]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function get_asset_yearly_maintenance_grid(int $assetId, int $year): array {
    $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $grid = [];
    for ($m = 1; $m <= 12; $m++) {
        $grid[$m] = [
            'month' => $m,
            'month_name' => $monthNames[$m],
            'status' => 'pending', // 'done' or 'pending'
            'is_done' => false,
            'date' => '',
            'technician' => '',
            'log_id' => 0
        ];
    }

    $history = get_asset_maintenance_history($assetId);
    foreach ($history as $h) {
        $hYear = (int)($h['maintenance_year'] ?? (int)date('Y', strtotime($h['maintenance_date'] ?? '')));
        $hMonth = (int)($h['maintenance_month'] ?? (int)date('n', strtotime($h['maintenance_date'] ?? '')));
        if ($hYear === $year && isset($grid[$hMonth])) {
            $grid[$hMonth]['status'] = 'done';
            $grid[$hMonth]['is_done'] = true;
            $grid[$hMonth]['date'] = substr((string)($h['maintenance_date'] ?? ''), 0, 10);
            $grid[$hMonth]['technician'] = $h['technician_name'] ?? 'Teknisi';
            $grid[$hMonth]['log_id'] = (int)($h['id'] ?? 0);
        }
    }
    return $grid;
}

function get_asset_yearly_card_matrix(int $assetId, int $year): array {
    $yrSuffix = sprintf('%02d', $year % 100);
    $matrix = [];
    for ($m = 1; $m <= 12; $m++) {
        $matrix[$m] = [
            'month' => $m,
            'month_tag' => sprintf('/%02d/%s', $m, $yrSuffix),
            'date_str' => sprintf('/%02d/%s', $m, $yrSuffix),
            'is_done' => false,
            'log_id' => 0,
            'checklists' => [1=>0, 2=>0, 3=>0, 4=>0, 5=>0, 6=>0, 7=>0, 8=>0, 9=>0],
            'paraf' => '',
            'status' => ''
        ];
    }

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if ($client) {
            $scans = $client->getSheetData('Maintenance_Scan');
            $chkRows = $client->getSheetData('Maintenance_Checklists');

            $chkMap = [];
            foreach ($chkRows as $c) {
                $mid = (int)($c['maintenance_id'] ?? 0);
                $num = (int)($c['checklist_number'] ?? 0);
                $checked = !empty($c['checked']) ? 1 : 0;
                if ($mid > 0 && $num >= 1 && $num <= 9) {
                    $chkMap[$mid][$num] = $checked;
                }
            }

            foreach ($scans as $s) {
                if ((int)($s['asset_id'] ?? 0) === $assetId) {
                    $sYear = (int)($s['maintenance_year'] ?? (int)date('Y', strtotime($s['maintenance_date'] ?? '')));
                    $sMonth = (int)($s['maintenance_month'] ?? (int)date('n', strtotime($s['maintenance_date'] ?? '')));
                    if ($sYear === $year && isset($matrix[$sMonth])) {
                        $logId = (int)($s['id'] ?? 0);
                        $d = substr((string)($s['maintenance_date'] ?? ''), 0, 10);
                        $dDay = $d ? date('d', strtotime($d)) : '';
                        $dateFormatted = $dDay ? "{$dDay}/" . sprintf('%02d/%s', $sMonth, $yrSuffix) : sprintf('/%02d/%s', $sMonth, $yrSuffix);

                        $matrix[$sMonth]['is_done'] = true;
                        $matrix[$sMonth]['log_id'] = $logId;
                        $matrix[$sMonth]['date_str'] = $dateFormatted;
                        $matrix[$sMonth]['paraf'] = $s['technician_name'] ?? 'Teknisi';
                        $matrix[$sMonth]['status'] = $s['status'] ?? 'Selesai';
                        if (isset($chkMap[$logId])) {
                            $matrix[$sMonth]['checklists'] = $chkMap[$logId];
                        } else {
                            $matrix[$sMonth]['checklists'] = [1=>1, 2=>1, 3=>1, 4=>1, 5=>1, 6=>1, 7=>1, 8=>1, 9=>1];
                        }
                    }
                }
            }
        }
        return $matrix;
    }

    // MySQL Mode
    $st = db()->prepare("
        SELECT ms.* 
        FROM maintenance_scan ms
        WHERE ms.asset_id = ? AND ms.maintenance_year = ?
        ORDER BY ms.maintenance_date ASC
    ");
    $st->execute([$assetId, $year]);
    $scans = $st->fetchAll();

    foreach ($scans as $s) {
        $sMonth = (int)($s['maintenance_month'] ?? (int)date('n', strtotime($s['maintenance_date'] ?? '')));
        if (isset($matrix[$sMonth])) {
            $logId = (int)$s['id'];
            $d = substr((string)($s['maintenance_date'] ?? ''), 0, 10);
            $dDay = $d ? date('d', strtotime($d)) : '';
            $dateFormatted = $dDay ? "{$dDay}/" . sprintf('%02d/%s', $sMonth, $yrSuffix) : sprintf('/%02d/%s', $sMonth, $yrSuffix);

            $matrix[$sMonth]['is_done'] = true;
            $matrix[$sMonth]['log_id'] = $logId;
            $matrix[$sMonth]['date_str'] = $dateFormatted;
            $matrix[$sMonth]['paraf'] = $s['technician_name'] ?: 'Teknisi';
            $matrix[$sMonth]['status'] = $s['status'] ?: 'Selesai';

            $chkSt = db()->prepare("SELECT checklist_number, checked FROM maintenance_checklists WHERE maintenance_id = ?");
            $chkSt->execute([$logId]);
            $chks = $chkSt->fetchAll();
            if ($chks) {
                foreach ($chks as $c) {
                    $matrix[$sMonth]['checklists'][(int)$c['checklist_number']] = (int)$c['checked'];
                }
            } else {
                $matrix[$sMonth]['checklists'] = [1=>1, 2=>1, 3=>1, 4=>1, 5=>1, 6=>1, 7=>1, 8=>1, 9=>1];
            }
        }
    }
    return $matrix;
}

function save_maintenance_record(array $data): array {
    $assetId = (int)($data['asset_id'] ?? 0);
    if ($assetId <= 0) return ['success' => false, 'error' => 'Asset ID tidak valid'];

    $date = !empty($data['maintenance_date']) ? substr((string)$data['maintenance_date'], 0, 10) : date('Y-m-d');
    $time = !empty($data['maintenance_time']) ? substr((string)$data['maintenance_time'], 0, 8) : date('H:i:s');
    $month = (int)date('n', strtotime($date));
    $year = (int)date('Y', strtotime($date));
    $userId = (int)($data['technician_user_id'] ?? current_user_id());
    $techName = trim((string)($data['technician_name'] ?? ''));
    if ($techName === '') {
        $techName = current_user_name();
    }
    $status = trim((string)($data['status'] ?? 'Selesai'));
    if (!in_array($status, ['Selesai', 'Proses', 'Perlu Perbaikan', 'Temuan'], true)) {
        $status = 'Selesai';
    }
    $mType = trim((string)($data['maintenance_type'] ?? 'Maintenance'));
    $findings = trim((string)($data['findings'] ?? ''));
    $recommendation = trim((string)($data['recommendation'] ?? ''));
    $checklists = (array)($data['checklists'] ?? []);

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];

        // 1. Simpan ke Maintenance_Scan
        $client->createSheetIfNotExists('Maintenance_Scan');
        $scans = $client->getSheetData('Maintenance_Scan');
        $maxId = 0;
        foreach ($scans as $s) {
            $sid = (int)($s['id'] ?? 0);
            if ($sid > $maxId) $maxId = $sid;
        }
        $newScanId = max(count($scans) + 1, $maxId + 1);

        $newScanRow = [
            $newScanId,
            $assetId,
            $userId,
            $techName,
            $date,
            $time,
            $month,
            $year,
            $status,
            $mType,
            date('Y-m-d H:i:s'),
            $findings,
            $recommendation
        ];
        $client->appendValues('Maintenance_Scan!A:M', [$newScanRow]);

        // 2. Simpan 9 items ke Maintenance_Checklists
        $client->createSheetIfNotExists('Maintenance_Checklists');
        $existingChk = $client->getSheetData('Maintenance_Checklists');
        $maxChkId = 0;
        foreach ($existingChk as $c) {
            $cid = (int)($c['id'] ?? 0);
            if ($cid > $maxChkId) $maxChkId = $cid;
        }
        $nextChkId = $maxChkId + 1;

        $chkRows = [];
        $fixedItems = get_fixed_checklists();
        foreach ($fixedItems as $num => $name) {
            $chkItem = $checklists[$num] ?? [];
            $checked = !empty($chkItem['checked']) ? 1 : 0;
            $notes = trim((string)($chkItem['notes'] ?? ''));
            $chkRows[] = [
                $nextChkId,
                $newScanId,
                $assetId,
                $num,
                $name,
                $checked,
                $notes,
                date('Y-m-d H:i:s')
            ];
            $nextChkId++;
        }
        $client->appendValues('Maintenance_Checklists!A:H', $chkRows);

        // 3. Jika ada temuan / kerusakan, catat juga di Maintenance_Findings
        if ($findings !== '' || $status === 'Perlu Perbaikan' || $status === 'Proses') {
            $client->createSheetIfNotExists('Maintenance_Findings');
            $existingFindings = $client->getSheetData('Maintenance_Findings');
            $newFindId = count($existingFindings) + 1;
            $client->appendValues('Maintenance_Findings!A:L', [[
                $newFindId,
                $newScanId,
                $assetId,
                'Maintenance Temuan',
                $findings,
                $recommendation,
                $status,
                $techName,
                date('Y-m-d H:i:s'),
                '', '', ''
            ]]);
        }

        $client->clearCache();
        return ['success' => true, 'log_id' => $newScanId];
    }

    // MySQL Mode
    try {
        db()->exec("
            CREATE TABLE IF NOT EXISTS maintenance_scan (
                id INT AUTO_INCREMENT PRIMARY KEY,
                asset_id INT NOT NULL,
                technician_user_id INT NULL,
                technician_name VARCHAR(150) NULL,
                maintenance_date DATE NOT NULL,
                maintenance_time TIME NOT NULL,
                maintenance_month TINYINT NOT NULL,
                maintenance_year SMALLINT NOT NULL,
                status VARCHAR(50) DEFAULT 'Selesai',
                source VARCHAR(50) DEFAULT 'Maintenance',
                findings TEXT NULL,
                recommendation TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS maintenance_checklists (
                id INT AUTO_INCREMENT PRIMARY KEY,
                maintenance_id INT NOT NULL,
                asset_id INT NOT NULL,
                checklist_number TINYINT NOT NULL,
                checklist_name VARCHAR(150) NOT NULL,
                checked TINYINT(1) DEFAULT 0,
                notes VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $ins = db()->prepare("
            INSERT INTO maintenance_scan
            (asset_id, technician_user_id, technician_name, maintenance_date, maintenance_time, maintenance_month, maintenance_year, status, source, findings, recommendation, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $ins->execute([$assetId, $userId, $techName, $date, $time, $month, $year, $status, $mType, $findings, $recommendation]);
        $logId = (int)db()->lastInsertId();

        $fixedItems = get_fixed_checklists();
        $chkSt = db()->prepare("
            INSERT INTO maintenance_checklists
            (maintenance_id, asset_id, checklist_number, checklist_name, checked, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        foreach ($fixedItems as $num => $name) {
            $chkItem = $checklists[$num] ?? [];
            $checked = !empty($chkItem['checked']) ? 1 : 0;
            $notes = trim((string)($chkItem['notes'] ?? ''));
            $chkSt->execute([$logId, $assetId, $num, $name, $checked, $notes]);
        }

        return ['success' => true, 'log_id' => $logId];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function get_maintenance_detail(int $logId): ?array {
    if ($logId <= 0) return null;
    $fixedItems = get_fixed_checklists();

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return null;

        $scans = $client->getSheetData('Maintenance_Scan');
        $targetScan = null;
        foreach ($scans as $s) {
            if ((int)($s['id'] ?? 0) === $logId) {
                $targetScan = $s;
                break;
            }
        }
        if (!$targetScan) return null;

        $assetId = (int)($targetScan['asset_id'] ?? 0);
        $asset = get_asset_by_id($assetId);

        // Checklist items
        $chkRows = $client->getSheetData('Maintenance_Checklists');
        $checklists = [];
        foreach ($fixedItems as $num => $name) {
            $checklists[$num] = [
                'number' => $num,
                'name' => $name,
                'checked' => 0,
                'notes' => ''
            ];
        }
        foreach ($chkRows as $c) {
            if ((int)($c['maintenance_id'] ?? 0) === $logId) {
                $cnum = (int)($c['checklist_number'] ?? 0);
                if (isset($checklists[$cnum])) {
                    $isCh = strtolower(trim((string)($c['checked'] ?? '0')));
                    $checklists[$cnum]['checked'] = ($isCh === '1' || $isCh === 'true' || $isCh === 'yes' || $isCh === 'v' || $isCh === '✓') ? 1 : 0;
                    $checklists[$cnum]['notes'] = (string)($c['notes'] ?? '');
                }
            }
        }

        return [
            'scan' => $targetScan,
            'asset' => $asset,
            'checklists' => $checklists
        ];
    }

    // MySQL Mode
    try {
        $st = db()->prepare("
            SELECT ms.*, COALESCE(ms.technician_name, u.nama, 'Teknisi') AS technician_name
            FROM maintenance_scan ms
            LEFT JOIN users u ON u.id = ms.technician_user_id
            WHERE ms.id = ? LIMIT 1
        ");
        $st->execute([$logId]);
        $scan = $st->fetch();
        if (!$scan) return null;

        $asset = get_asset_by_id((int)$scan['asset_id']);

        $chkSt = db()->prepare("SELECT * FROM maintenance_checklists WHERE maintenance_id = ? ORDER BY checklist_number ASC");
        $chkSt->execute([$logId]);
        $chkRows = $chkSt->fetchAll();

        $checklists = [];
        foreach ($fixedItems as $num => $name) {
            $checklists[$num] = [
                'number' => $num,
                'name' => $name,
                'checked' => 0,
                'notes' => ''
            ];
        }
        foreach ($chkRows as $c) {
            $cnum = (int)($c['checklist_number'] ?? 0);
            if (isset($checklists[$cnum])) {
                $checklists[$cnum]['checked'] = (int)($c['checked'] ?? 0);
                $checklists[$cnum]['notes'] = (string)($c['notes'] ?? '');
            }
        }

        return [
            'scan' => $scan,
            'asset' => $asset,
            'checklists' => $checklists
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function record_scan(int $assetId, int $userId, string $techName, string $date, string $time, int $month, int $year, bool $force = false): array {
    return save_maintenance_record([
        'asset_id' => $assetId,
        'technician_user_id' => $userId,
        'technician_name' => $techName,
        'maintenance_date' => $date,
        'maintenance_time' => $time,
        'status' => 'Selesai',
        'maintenance_type' => $force ? 'Maintenance Ulang' : 'Maintenance'
    ]);
}

function get_log_by_id(int $logId): ?array {
    if (is_google_cloud_mode()) {
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
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return ['success' => false];

        $findings = $client->getSheetData('Maintenance_Findings');
        $newId = count($findings) + 1;
        $newRow = [
            $newId,
            $logId,
            $assetId,
            $severity,
            $finding,
            $action,
            'Open',
            $reporter,
            date('Y-m-d H:i:s'),
            '',
            '',
            ''
        ];
        $client->appendValues('Maintenance_Findings!A:L', [$newRow]);

        // Update scan status to 'Temuan'
        $scans = $client->getSheetData('Maintenance_Scan');
        foreach ($scans as $s) {
            if ((int)($s['id'] ?? 0) === $logId) {
                $rowNum = (int)($s['_row_num'] ?? 0);
                if ($rowNum > 1) {
                    $client->updateValues("Maintenance_Scan!I{$rowNum}", [['Temuan']]);
                }
                break;
            }
        }
        return ['success' => true, 'finding_id' => $newId];
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

function get_findings_report(int $month, int $year, int $cabangId): array {
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return [];
        $findings = $client->getSheetData('Maintenance_Findings');
        $scans = $client->getSheetData('Maintenance_Scan');
        $scanMap = []; foreach ($scans as $s) $scanMap[$s['id'] ?? 0] = $s;
        $assets = map_sheets_assets();
        $assetMap = []; foreach ($assets as $a) $assetMap[$a['id'] ?? 0] = $a;

        $results = [];
        foreach ($findings as $f) {
            $scanId = (int)($f['maintenance_scan_id'] ?? 0);
            $scan = $scanMap[$scanId] ?? [];
            if ($month > 0 && (int)($scan['maintenance_month'] ?? 0) !== $month) continue;
            if ($year > 0 && (int)($scan['maintenance_year'] ?? 0) !== $year) continue;

            $aid = (int)($f['asset_id'] ?? $scan['asset_id'] ?? 0);
            $a = $assetMap[$aid] ?? [];
            if ($cabangId > 0 && (int)($a['id_cabang'] ?? 0) !== $cabangId) continue;

            $results[] = [
                'id' => $f['id'] ?? 0,
                'kode_inventaris' => $a['kode_inventaris'] ?? '-',
                'merk_model' => trim(($a['merk'] ?? '').' '.($a['model'] ?? '')),
                'karyawan_nama' => $a['karyawan_nama'] ?? '-',
                'cabang_nama' => $a['cabang_nama'] ?? '-',
                'finding' => $f['finding'] ?? $f['deskripsi_temuan'] ?? '-',
                'action_taken' => $f['action_taken'] ?? $f['tindakan_diperlukan'] ?? '-',
                'severity' => $f['severity'] ?? $f['kategori_temuan'] ?? 'Ringan',
                'repair_status' => $f['repair_status'] ?? $f['status'] ?? 'Open',
                'created_at' => substr((string)($f['created_at'] ?? $f['reported_at'] ?? ''), 0, 10),
            ];
        }
        return $results;
    }

    // MySQL Mode
    try {
        $cName = name_column('cabang') ?: 'id';
        $kName = name_column('karyawan') ?: 'id';
        $sql = "
            SELECT mf.*, a.kode_inventaris, a.merk, a.model,
                   c.`{$cName}` AS cabang_nama,
                   k.`{$kName}` AS karyawan_nama
            FROM maintenance_findings mf
            JOIN maintenance_scan ms ON ms.id = mf.maintenance_scan_id
            JOIN assets a ON a.id = mf.asset_id
            LEFT JOIN cabang c ON c.id = a.id_cabang
            LEFT JOIN karyawan k ON k.id = a.id_karyawan
            WHERE ms.maintenance_month = ? AND ms.maintenance_year = ?
        ";
        $params = [$month, $year];
        if ($cabangId > 0) {
            $sql .= " AND a.id_cabang = ? ";
            $params[] = $cabangId;
        }
        $sql .= " ORDER BY mf.id DESC";
        $st = db()->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        return array_map(function($r) {
            return [
                'id' => $r['id'],
                'kode_inventaris' => $r['kode_inventaris'] ?? '-',
                'merk_model' => trim(($r['merk'] ?? '').' '.($r['model'] ?? '')),
                'karyawan_nama' => $r['karyawan_nama'] ?? '-',
                'cabang_nama' => $r['cabang_nama'] ?? '-',
                'finding' => $r['finding'] ?? '-',
                'action_taken' => $r['action_taken'] ?? '-',
                'severity' => $r['severity'] ?? 'Ringan',
                'repair_status' => $r['repair_status'] ?? 'Perlu Tindak Lanjut',
                'created_at' => substr((string)($r['created_at'] ?? ''), 0, 10),
            ];
        }, $rows);
    } catch (Throwable $e) {
        return [];
    }
}

function get_history_rows(int $month, int $year, int $cabangId, string $status = ''): array {
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return [];

        $scans = $client->getSheetData('Maintenance_Scan');
        $assetMap = []; foreach (map_sheets_assets() as $a) $assetMap[$a['id']] = $a;

        $rows = [];
        foreach ($scans as $s) {
            if ($month > 0 && (int)($s['maintenance_month'] ?? 0) !== $month) continue;
            if ($year > 0 && (int)($s['maintenance_year'] ?? 0) !== $year) continue;
            if ($status !== '' && ($s['status'] ?? '') !== $status) continue;

            $a = $assetMap[(int)($s['asset_id'] ?? 0)] ?? [];
            if ($cabangId > 0 && ($a['id_cabang'] ?? 0) !== $cabangId) continue;

            $rows[] = [
                'id' => (int)($s['id'] ?? 0),
                'asset_id' => (int)($s['asset_id'] ?? 0),
                'maintenance_date' => substr((string)($s['maintenance_date'] ?? ''), 0, 10),
                'maintenance_time' => substr((string)($s['maintenance_time'] ?? ''), 0, 8),
                'status' => $s['status'] ?? 'Selesai',
                'technician_name' => $s['technician_name'] ?? 'Teknisi',
                'maintenance_type' => $s['source'] ?? $s['maintenance_type'] ?? 'Maintenance',
                'findings' => $s['findings'] ?? '',
                'recommendation' => $s['recommendation'] ?? '',
                'kode_inventaris' => $a['kode_inventaris'] ?? '-',
                'serial_number' => $a['serial_number'] ?? '-',
                'merk' => $a['merk'] ?? '',
                'model' => $a['model'] ?? '',
                'kategori_nama' => $a['kategori_nama'] ?? '-',
                'karyawan_nama' => $a['karyawan_nama'] ?? '-',
                'cabang_nama' => $a['cabang_nama'] ?? '-'
            ];
        }
        usort($rows, fn($a, $b) => strcmp($b['maintenance_date'], $a['maintenance_date']));
        return array_values($rows);
    }

    $cName = name_column('cabang') ?: 'id';
    $kName = name_column('karyawan') ?: 'id';
    $uName = name_column('users') ?: 'id';

    $sql = "
    SELECT ms.*, a.kode_inventaris, a.serial_number, a.merk, a.model,
           c.`{$cName}` AS cabang_nama,
           k.`{$kName}` AS karyawan_nama,
           COALESCE(ms.technician_name, u.`{$uName}`, 'Teknisi') AS technician_name
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

    $sql .= " ORDER BY ms.maintenance_date DESC, ms.id DESC LIMIT 1000";

    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function get_audit_maintenance_data(array $filters): array {
    $month = (int)($filters['bulan'] ?? date('n'));
    $year = (int)($filters['tahun'] ?? date('Y'));
    $cabangId = (int)($filters['cabang'] ?? 0);
    $divisiId = (int)($filters['divisi'] ?? 0);
    $filterKat = (int)($filters['kategori'] ?? 0);
    $filterTech = trim((string)($filters['teknisi'] ?? ''));
    $filterStatus = trim((string)($filters['status'] ?? ''));

    // 1. Ambil semua master aset aktif
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        $allAssets = array_filter(map_sheets_assets(), function($a) use ($cabangId, $divisiId, $filterKat) {
            $st = strtolower($a['status']);
            if ($st !== 'aktif' && $st !== '') return false;
            if ($cabangId > 0 && (int)($a['id_cabang'] ?? 0) !== $cabangId) return false;
            if ($divisiId > 0 && (int)($a['id_divisi'] ?? 0) !== $divisiId) return false;
            if ($filterKat > 0 && (int)($a['id_kategori'] ?? 0) !== $filterKat) return false;
            return true;
        });

        $scans = $client ? $client->getSheetData('Maintenance_Scan') : [];
        $chkRows = $client ? $client->getSheetData('Maintenance_Checklists') : [];
        
        // Buat map checklist per maintenance_id
        $chkMap = [];
        foreach ($chkRows as $c) {
            $mid = (int)($c['maintenance_id'] ?? 0);
            if ($mid > 0) {
                $chkMap[$mid][] = $c;
            }
        }

        // Map maintenance terbaru per asset_id untuk bulan & tahun ini
        $assetScanMap = [];
        foreach ($scans as $s) {
            if ((int)($s['maintenance_month'] ?? 0) === $month && (int)($s['maintenance_year'] ?? 0) === $year) {
                $aid = (int)($s['asset_id'] ?? 0);
                // Jika teknisi difilter
                if ($filterTech !== '' && strcasecmp(trim($s['technician_name'] ?? ''), $filterTech) !== 0) {
                    continue;
                }
                $assetScanMap[$aid] = $s;
            }
        }

        $rows = [];
        $total = 0;
        $done = 0;
        $pending = 0;
        $repair = 0;

        foreach ($allAssets as $a) {
            $aid = (int)$a['id'];
            $scan = $assetScanMap[$aid] ?? null;

            $isDone = ($scan !== null);
            $st = $isDone ? ($scan['status'] ?? 'Selesai') : 'Belum Maintenance';
            
            // Filter status
            if ($filterStatus !== '') {
                if ($filterStatus === 'done' && !$isDone) continue;
                if ($filterStatus === 'pending' && $isDone) continue;
                if ($filterStatus === 'repair' && (!in_array($st, ['Temuan', 'Perlu Perbaikan', 'Proses'], true))) continue;
                if (!in_array($filterStatus, ['done', 'pending', 'repair'], true) && strcasecmp($st, $filterStatus) !== 0) continue;
            }

            $total++;
            if ($isDone) {
                $done++;
                if (in_array($st, ['Temuan', 'Perlu Perbaikan', 'Proses'], true)) {
                    $repair++;
                }
            } else {
                $pending++;
            }

            $mid = $scan ? (int)($scan['id'] ?? 0) : 0;
            $chks = $mid > 0 ? ($chkMap[$mid] ?? []) : [];

            $rows[] = [
                'asset_id' => $aid,
                'kode_inventaris' => $a['kode_inventaris'] ?? '-',
                'serial_number' => $a['serial_number'] ?? '-',
                'perangkat' => trim(($a['merk'] ?? '').' '.($a['model'] ?? '')),
                'merk' => $a['merk'] ?? '',
                'model' => $a['model'] ?? '',
                'karyawan_nama' => $a['karyawan_nama'] ?? '-',
                'divisi_nama' => $a['divisi_nama'] ?? '-',
                'cabang_nama' => $a['cabang_nama'] ?? '-',
                'kategori_nama' => $a['kategori_nama'] ?? 'Perangkat IT',
                'is_done' => $isDone,
                'status' => $st,
                'log_id' => $mid,
                'maintenance_date' => $scan ? substr((string)($scan['maintenance_date'] ?? ''), 0, 10) : '-',
                'technician_name' => $scan ? ($scan['technician_name'] ?? 'Teknisi') : '-',
                'findings' => $scan ? ($scan['findings'] ?? '-') : '-',
                'recommendation' => $scan ? ($scan['recommendation'] ?? '-') : '-',
                'checklists' => $chks
            ];
        }

        $percent = $total > 0 ? round(($done / $total) * 100) : 0;
        return [
            'stats' => ['total' => $total, 'done' => $done, 'pending' => $pending, 'repair' => $repair, 'percent' => $percent],
            'rows' => $rows
        ];
    }

    // MySQL Mode
    $where = " WHERE (a.status = 'Aktif' OR a.status = 'aktif' OR a.status IS NULL OR a.status = '') ";
    $params = [];
    if ($cabangId > 0) { $where .= " AND a.id_cabang = ? "; $params[] = $cabangId; }
    if ($divisiId > 0) { $where .= " AND a.id_divisi = ? "; $params[] = $divisiId; }
    if ($filterKat > 0) { $where .= " AND a.id_kategori = ? "; $params[] = $filterKat; }

    $cName = name_column('cabang') ?: 'id';
    $kName = name_column('karyawan') ?: 'id';
    $dName = name_column('divisi') ?: 'id';
    $katName = name_column('kategori_aset') ?: 'id';
    $uName = name_column('users') ?: 'id';

    $sql = "
        SELECT a.id AS asset_id, a.kode_inventaris, a.serial_number, a.merk, a.model,
               c.`{$cName}` AS cabang_nama,
               k.`{$kName}` AS karyawan_nama,
               d.`{$dName}` AS divisi_nama,
               kat.`{$katName}` AS kategori_nama,
               ms.id AS log_id,
               ms.maintenance_date,
               ms.status AS scan_status,
               ms.findings,
               ms.recommendation,
               COALESCE(ms.technician_name, u.`{$uName}`, 'Teknisi') AS technician_name
        FROM assets a
        LEFT JOIN cabang c ON c.id = a.id_cabang
        LEFT JOIN karyawan k ON k.id = a.id_karyawan
        LEFT JOIN divisi d ON d.id = a.id_divisi
        LEFT JOIN kategori_aset kat ON kat.id = a.id_kategori
        LEFT JOIN maintenance_scan ms ON ms.asset_id = a.id AND ms.maintenance_month = {$month} AND ms.maintenance_year = {$year}
        LEFT JOIN users u ON u.id = ms.technician_user_id
        {$where}
        ORDER BY c.`{$cName}`, a.kode_inventaris ASC
    ";
    $st = db()->prepare($sql);
    $st->execute($params);
    $allRows = $st->fetchAll();

    $rows = [];
    $total = 0;
    $done = 0;
    $pending = 0;
    $repair = 0;

    foreach ($allRows as $r) {
        $isDone = !empty($r['log_id']);
        $stVal = $isDone ? ($r['scan_status'] ?: 'Selesai') : 'Belum Maintenance';

        if ($filterTech !== '' && $isDone && strcasecmp(trim($r['technician_name'] ?? ''), $filterTech) !== 0) {
            continue;
        }

        if ($filterStatus !== '') {
            if ($filterStatus === 'done' && !$isDone) continue;
            if ($filterStatus === 'pending' && $isDone) continue;
            if ($filterStatus === 'repair' && (!in_array($stVal, ['Temuan', 'Perlu Perbaikan', 'Proses'], true))) continue;
            if (!in_array($filterStatus, ['done', 'pending', 'repair'], true) && strcasecmp($stVal, $filterStatus) !== 0) continue;
        }

        $total++;
        if ($isDone) {
            $done++;
            if (in_array($stVal, ['Temuan', 'Perlu Perbaikan', 'Proses'], true)) $repair++;
        } else {
            $pending++;
        }

        $rows[] = [
            'asset_id' => (int)$r['asset_id'],
            'kode_inventaris' => $r['kode_inventaris'] ?? '-',
            'serial_number' => $r['serial_number'] ?? '-',
            'perangkat' => trim(($r['merk'] ?? '').' '.($r['model'] ?? '')),
            'merk' => $r['merk'] ?? '',
            'model' => $r['model'] ?? '',
            'karyawan_nama' => $r['karyawan_nama'] ?? '-',
            'divisi_nama' => $r['divisi_nama'] ?? '-',
            'cabang_nama' => $r['cabang_nama'] ?? '-',
            'kategori_nama' => $r['kategori_nama'] ?? 'Perangkat IT',
            'is_done' => $isDone,
            'status' => $stVal,
            'log_id' => (int)($r['log_id'] ?? 0),
            'maintenance_date' => $isDone ? substr((string)($r['maintenance_date'] ?? ''), 0, 10) : '-',
            'technician_name' => $isDone ? ($r['technician_name'] ?? 'Teknisi') : '-',
            'findings' => $isDone ? ($r['findings'] ?? '-') : '-',
            'recommendation' => $isDone ? ($r['recommendation'] ?? '-') : '-',
            'checklists' => []
        ];
    }

    $percent = $total > 0 ? round(($done / $total) * 100) : 0;
    return [
        'stats' => ['total' => $total, 'done' => $done, 'pending' => $pending, 'repair' => $repair, 'percent' => $percent],
        'rows' => $rows
    ];
}

function get_monthly_overview(int $year, int $cabangId = 0): array {
    $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $currentMonth = (int)date('n');
    $currentYear = (int)date('Y');

    $overview = [];
    for ($m = 1; $m <= 12; $m++) {
        $audit = get_audit_maintenance_data([
            'bulan' => $m,
            'tahun' => $year,
            'cabang' => $cabangId
        ]);
        $stats = $audit['stats'];
        $isFuture = ($year > $currentYear) || ($year === $currentYear && $m > $currentMonth);
        $isCurrent = ($year === $currentYear && $m === $currentMonth);

        $overview[$m] = [
            'month' => $m,
            'month_name' => $monthNames[$m],
            'total' => $stats['total'],
            'done' => $stats['done'],
            'pending' => $stats['pending'],
            'repair' => $stats['repair'],
            'percent' => $stats['percent'],
            'is_future' => $isFuture,
            'is_current' => $isCurrent
        ];
    }
    return $overview;
}

function get_qr_admin_rows(int $cabangId): array {
    if (is_google_cloud_mode()) {
        $assets = map_sheets_assets();
        if ($cabangId > 0) {
            $assets = array_filter($assets, function($a) use ($cabangId) {
                return $a['id_cabang'] === $cabangId;
            });
        }
        return array_values($assets);
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
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return 0;

        $assets = map_sheets_assets();
        $qrRows = $client->getSheetData('Asset_QR_Tokens');
        $existingAssetIds = [];
        foreach ($qrRows as $q) $existingAssetIds[(int)($q['asset_id'] ?? 0)] = true;

        $created = 0;
        $nextId = count($qrRows) + 1;
        $newRows = [];

        foreach ($assets as $a) {
            if ($cabangId > 0 && $a['id_cabang'] !== $cabangId) continue;
            if (empty($existingAssetIds[$a['id']])) {
                $newRows[] = [
                    $nextId++,
                    $a['id'],
                    bin2hex(random_bytes(16)),
                    'Bodi Top',
                    1,
                    date('Y-m-d H:i:s')
                ];
                $created++;
            }
        }
        if ($newRows) {
            $client->appendValues('Asset_QR_Tokens!A:F', $newRows);
        }
        return $created;
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
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return false;

        $qrRows = $client->getSheetData('Asset_QR_Tokens');
        $newToken = bin2hex(random_bytes(16));

        foreach ($qrRows as $q) {
            if ((int)($q['asset_id'] ?? 0) === $assetId) {
                $rowNum = (int)($q['_row_num'] ?? 0);
                if ($rowNum > 1) {
                    $client->updateValues("Asset_QR_Tokens!C{$rowNum}:E{$rowNum}", [[$newToken, 'Bodi Top', 1]]);
                    return true;
                }
            }
        }
        // If not found, append
        $newId = count($qrRows) + 1;
        $client->appendValues('Asset_QR_Tokens!A:F', [[$newId, $assetId, $newToken, 'Bodi Top', 1, date('Y-m-d H:i:s')]]);
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

function render_page(string $title, string $content, string $extraHead = '', string $extraScript = '', bool $showNav = true): void {
    $nav = '';
    $currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');

    if ($showNav) {
        $nav = '
        <nav class="navbar navbar-expand-lg navbar-dark main-navbar mb-4 sticky-top">
          <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="'.e(module_url('dashboard.php')).'">
              <span class="brand-icon"><i class="bi bi-qr-code-scan"></i></span>
              <span class="brand-text">QR Maintenance</span>
            </a>
            <div class="d-flex flex-wrap gap-2 align-items-center">
              <a class="nav-pill-btn '.($currentPage==='dashboard.php'?'active':'').'" href="'.e(module_url('dashboard.php')).'"><i class="bi bi-speedometer2"></i> Dashboard</a>
              <a class="nav-pill-btn '.(in_array($currentPage, ['audit.php', 'monthly_history.php', 'history.php', 'maintenance_detail.php'], true)?'active':'').'" href="'.e(module_url('audit.php')).'"><i class="bi bi-clock-history"></i> Riwayat Maintenance</a>
              <a class="nav-pill-btn '.($currentPage==='qr_admin.php'?'active':'').'" href="'.e(module_url('qr_admin.php')).'"><i class="bi bi-qr-code"></i> QR Aset</a>
              <a class="nav-pill-btn '.($currentPage==='cabang_admin.php'?'active':'').'" href="'.e(module_url('cabang_admin.php')).'"><i class="bi bi-buildings"></i> Cabang</a>
              <a class="btn btn-sm btn-action-add fw-bold" href="'.e(module_url('asset_add.php')).'"><i class="bi bi-plus-circle-fill me-1"></i> + Tambah Komputer</a>
              <span class="d-none d-lg-inline-flex align-items-center gap-1 ms-2 text-white-50 small"><i class="bi bi-person-circle"></i> '.e(current_user_name()).'</span>
              <a class="nav-pill-btn text-danger-emphasis" href="'.e(module_url('logout.php')).'" title="Keluar / Logout"><i class="bi bi-box-arrow-right"></i></a>
            </div>
          </div>
        </nav>';
    } else {
        $nav = '
        <header class="text-center py-3 mb-4 bg-white border-bottom shadow-sm">
          <span class="fw-bold text-primary fs-5"><i class="bi bi-qr-code-scan me-2"></i>QR Maintenance System</span>
        </header>';
    }

    echo '<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>'.e($title).' · QR Maintenance</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root {
  --primary-gradient: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
  --success-gradient: linear-gradient(135deg, #059669 0%, #10b981 100%);
  --warning-gradient: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
  --danger-gradient: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
}

body {
  background: #f8fafc;
  font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  color: #1e293b;
  -webkit-font-smoothing: antialiased;
}

/* Navbar Modern */
.main-navbar {
  background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #2563eb 100%);
  backdrop-filter: blur(12px);
  box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.15);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  padding: 0.75rem 0;
}

.brand-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  background: rgba(255, 255, 255, 0.18);
  border-radius: 9px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.brand-text {
  font-size: 1.15rem;
  letter-spacing: -0.3px;
}

.nav-pill-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  font-size: 0.85rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.85);
  text-decoration: none;
  border-radius: 20px;
  transition: all 0.2s ease;
}

.nav-pill-btn:hover {
  color: #ffffff;
  background: rgba(255, 255, 255, 0.15);
  transform: translateY(-1px);
}

.nav-pill-btn.active {
  color: #1e3a8a;
  background: #ffffff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
}

.btn-action-add {
  background: #fbbf24;
  color: #78350f;
  border: none;
  border-radius: 20px;
  padding: 6px 16px;
  box-shadow: 0 3px 10px rgba(251, 191, 36, 0.35);
  transition: all 0.2s ease;
}

.btn-action-add:hover {
  background: #f59e0b;
  color: #451a03;
  transform: translateY(-1px);
  box-shadow: 0 5px 14px rgba(251, 191, 36, 0.45);
}

/* Card & Elevated Components */
.card {
  border: 1px solid rgba(226, 232, 240, 0.8);
  border-radius: 16px;
  background: #ffffff;
  box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.04), 0 2px 6px -1px rgba(0, 0, 0, 0.02);
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.card-hover:hover {
  transform: translateY(-3px);
  box-shadow: 0 14px 28px -4px rgba(15, 23, 42, 0.08), 0 4px 10px -2px rgba(15, 23, 42, 0.03);
}

.stat-card {
  position: relative;
  overflow: hidden;
}

.stat-icon-wrapper {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}

.stat {
  font-size: 1.85rem;
  font-weight: 800;
  letter-spacing: -0.5px;
  line-height: 1.1;
}

.small-muted {
  font-size: 0.78rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Modern Form Controls */
.form-control, .form-select {
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  padding: 0.55rem 0.85rem;
  font-size: 0.9rem;
  transition: all 0.2s ease;
}

.form-control:focus, .form-select:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
}

/* Modern Badges & Chips */
.badge-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.78rem;
  font-weight: 700;
}

.chip-success { background: #dcfce7; color: #15803d; }
.chip-warning { background: #fef3c7; color: #b45309; }
.chip-danger { background: #fee2e2; color: #b91c1c; }
.chip-primary { background: #dbeafe; color: #1d4ed8; }
.chip-secondary { background: #f1f5f9; color: #475569; }

/* Table Enhancements */
.table {
  font-size: 0.9rem;
}

.table thead th {
  background: #f8fafc;
  color: #475569;
  font-weight: 700;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  border-bottom: 2px solid #e2e8f0;
  padding: 12px 14px;
}

.table tbody td {
  padding: 12px 14px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.table-hover tbody tr:hover {
  background-color: #f8fafc;
}

/* Loading Progress Bar */
#top-progress-bar {
  position: fixed;
  top: 0;
  left: 0;
  height: 3px;
  background: #22c55e;
  z-index: 9999;
  transition: width .2s ease;
  width: 0;
}

@media print {
  .no-print, nav, header, #top-progress-bar { display: none !important; }
  .qr-label { box-shadow: none; }
  .container { max-width: none !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
}
</style>
'.$extraHead.'
</head>
<body>
<div id="top-progress-bar"></div>
'.$nav.'
<main class="container pb-5">
'.$content.'
</main>
<script>
// Ultra-fast instant prefetching on hover / touch
(function(){
  var preloaded = {};
  function doPrefetch(url) {
    if (!url || preloaded[url]) return;
    if (url.indexOf("javascript:") === 0 || url.indexOf("#") !== -1) return;
    try {
      var u = new URL(url, location.href);
      if (u.origin !== location.origin) return;
      preloaded[url] = true;
      var link = document.createElement("link");
      link.rel = "prefetch";
      link.href = url;
      document.head.appendChild(link);
    } catch(e){}
  }
  document.addEventListener("mouseover", function(e){
    var a = e.target.closest("a");
    if (a && a.href && !a.target && a.origin === location.origin) doPrefetch(a.href);
  }, {passive: true});
  document.addEventListener("touchstart", function(e){
    var a = e.target.closest("a");
    if (a && a.href && !a.target && a.origin === location.origin) doPrefetch(a.href);
  }, {passive: true});
  
  // Auto-Lock jika ditinggal lama tanpa aktivitas (15 menit = 900 detik)
  var idleTimeoutMs = 900 * 1000;
  var idleTimer;
  function resetIdleTimer() {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(function(){
      window.location.href = "'.e(module_url('login.php', ['expired' => 1])).'";
    }, idleTimeoutMs);
  }
  ["mousemove", "mousedown", "keydown", "scroll", "touchstart", "click"].forEach(function(evt){
    document.addEventListener(evt, resetIdleTimer, {passive: true});
  });
  resetIdleTimer();
})();
</script>
'.$extraScript.'
</body>
</html>';
}

function format_id_date(string $date): string {
    $ts = strtotime($date);
    return $ts ? date('d-m-Y', $ts) : $date;
}
