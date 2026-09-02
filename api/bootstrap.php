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

function require_login(): void {
    if (is_google_cloud_mode()) return;
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

function record_scan(int $assetId, int $userId, string $techName, string $date, string $time, int $month, int $year, bool $force = false): array {
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return ['success' => false, 'error' => 'Sheets Client unavailable'];

        $client->createSheetIfNotExists('Maintenance_Scan');
        $scans = $client->getSheetData('Maintenance_Scan');
        foreach ($scans as $s) {
            if ((int)($s['asset_id'] ?? 0) === $assetId && (int)($s['maintenance_month'] ?? 0) === $month && (int)($s['maintenance_year'] ?? 0) === $year) {
                if ($force) {
                    $rowNum = (int)($s['_row_num'] ?? 0);
                    if ($rowNum > 1) {
                        $client->updateValues("Maintenance_Scan!C{$rowNum}:H{$rowNum}", [[
                            $userId, $techName, $date, $time, $month, $year
                        ]]);
                    }
                    return ['success' => true, 'log_id' => (int)($s['id'] ?? 0), 'is_updated' => true];
                }
                return ['success' => false, 'is_duplicate' => true, 'existing' => $s];
            }
        }

        $newId = count($scans) + 1;
        $newRow = [
            $newId,
            $assetId,
            $userId,
            $techName,
            $date,
            $time,
            $month,
            $year,
            'Selesai',
            'QR',
            date('Y-m-d H:i:s')
        ];
        $client->appendValues('Maintenance_Scan!A:K', [$newRow]);
        return ['success' => true, 'log_id' => $newId];
    }

    $existing = db()->prepare("
        SELECT * FROM maintenance_scan
        WHERE asset_id = ? AND maintenance_month = ? AND maintenance_year = ?
        LIMIT 1
    ");
    $existing->execute([$assetId, $month, $year]);
    $old = $existing->fetch();
    if ($old) {
        if ($force) {
            $up = db()->prepare("
                UPDATE maintenance_scan
                SET technician_user_id = ?, maintenance_date = ?, maintenance_time = ?
                WHERE id = ?
            ");
            $up->execute([$userId, $date, $time, $old['id']]);
            return ['success' => true, 'log_id' => (int)$old['id'], 'is_updated' => true];
        }
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
                'kode_inventaris' => $a['kode_inventaris'] ?? '-',
                'serial_number' => $a['serial_number'] ?? '-',
                'merk' => $a['merk'] ?? '',
                'model' => $a['model'] ?? '',
                'kategori_nama' => $a['kategori_nama'] ?? '-',
                'karyawan_nama' => $a['karyawan_nama'] ?? '-',
                'cabang_nama' => $a['cabang_nama'] ?? '-'
            ];
        }
        return array_values($rows);
    }

    $cName = name_column('cabang') ?: 'id';
    $kName = name_column('karyawan') ?: 'id';
    $uName = name_column('users') ?: 'id';

    $sql = "
    SELECT ms.*, a.kode_inventaris, a.serial_number, a.merk, a.model,
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
        <nav class="navbar navbar-expand-lg bg-primary navbar-dark mb-4 shadow-sm">
          <div class="container">
            <a class="navbar-brand fw-bold" href="'.e(module_url('dashboard.php')).'"><i class="bi bi-qr-code-scan me-2"></i>QR Maintenance</a>
            <div class="d-flex flex-wrap gap-2 align-items-center">
              <a class="btn btn-sm '.($currentPage==='dashboard.php'?'btn-light fw-semibold text-primary':'btn-outline-light').'" href="'.e(module_url('dashboard.php')).'"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
              <a class="btn btn-sm '.($currentPage==='history.php'?'btn-light fw-semibold text-primary':'btn-outline-light').'" href="'.e(module_url('history.php')).'"><i class="bi bi-clock-history me-1"></i> Riwayat</a>
              <a class="btn btn-sm '.($currentPage==='qr_admin.php'?'btn-light fw-semibold text-primary':'btn-outline-light').'" href="'.e(module_url('qr_admin.php')).'"><i class="bi bi-qr-code me-1"></i> QR Aset</a>
              <a class="btn btn-sm '.($currentPage==='asset_add.php'?'btn-warning text-dark fw-bold border-2':'btn-warning text-dark fw-semibold').'" href="'.e(module_url('asset_add.php')).'"><i class="bi bi-plus-lg me-1"></i> + Tambah Komputer</a>
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
<title>'.e($title).'</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body{background:#f6f8fb}
.card{border:0;box-shadow:0 8px 24px rgba(0,0,0,.05)}
.stat{font-size:1.75rem;font-weight:700}
.small-muted{font-size:.875rem;color:#6c757d}
.qr-label{background:#fff;border:1px solid #dee2e6;border-radius:14px;padding:14px;break-inside:avoid}
#top-progress-bar{position:fixed;top:0;left:0;height:3px;background:#22c55e;z-index:9999;transition:width .2s ease;width:0}
@media print{.no-print,nav,header,#top-progress-bar{display:none!important}.qr-label{box-shadow:none}.container{max-width:none!important;width:100%!important;padding:0!important;margin:0!important}}
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
  
  // Smooth click feedback
  document.addEventListener("click", function(e){
    var a = e.target.closest("a");
    if (a && a.href && !a.target && a.origin === location.origin && a.href.indexOf("#") === -1 && a.getAttribute("target") !== "_blank") {
      var bar = document.getElementById("top-progress-bar");
      if (bar) { bar.style.width = "70%"; }
    }
  });
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
