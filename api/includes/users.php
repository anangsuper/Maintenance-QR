<?php
function get_user_list(bool $forceRefresh = false): array {
    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return [];

        $rows = $client->getSheetData('Users', $forceRefresh);
        if (empty($rows)) {
            return [
                ['id' => 1, 'nama' => 'Administrator', 'username' => 'admin', 'role' => 'admin', 'telepon' => '081234567890', 'status' => 'Aktif'],
                ['id' => 2, 'nama' => 'Teknisi IT', 'username' => 'teknisi', 'role' => 'teknisi', 'telepon' => '081234567891', 'status' => 'Aktif'],
            ];
        }

        $users = [];
        foreach ($rows as $u) {
            $id = (int)($u['id'] ?? 0);
            $nama = trim((string)($u['nama'] ?? $u['name'] ?? $u['username'] ?? ''));
            $username = trim((string)($u['username'] ?? ''));
            $role = strtolower(trim((string)($u['role'] ?? 'teknisi')));

            // Recovery jika baris user tergeser ke kanan (mulai dari kolom O / index col_14)
            if ($id <= 0 && !empty($u['col_14']) && is_numeric($u['col_14'])) {
                $id = (int)$u['col_14'];
                $username = trim((string)($u['col_15'] ?? ''));
                $nama = trim((string)($u['col_17'] ?? ''));
                $role = strtolower(trim((string)($u['col_18'] ?? 'teknisi')));
                $telepon = trim((string)($u['col_19'] ?? '-'));
                $status = trim((string)($u['col_20'] ?? 'Aktif'));
                $createdAt = trim((string)($u['col_21'] ?? ''));
            } else {
                $telepon = (string)($u['telepon'] ?? $u['kontak'] ?? '-');
                $status = (string)($u['status'] ?? 'Aktif');
                $createdAt = (string)($u['created_at'] ?? '');
            }

            // Abaikan jika bukan user valid atau merupakan baris header yang bocor
            if ($id <= 0 || strcasecmp($nama, 'nama') === 0 || strcasecmp($username, 'username') === 0 || $nama === '') {
                continue;
            }

            $fDesc = (string)($u['face_descriptor'] ?? $u['col_8'] ?? '');
            $fStatus = (string)($u['face_status'] ?? $u['col_10'] ?? '');
            $fPhoto = (string)($u['face_photo'] ?? $u['col_9'] ?? '');
            if ($fStatus === '' && $fDesc !== '') {
                $fStatus = 'pending'; // Wajib: butuh persetujuan admin jika belum diverifikasi
            } elseif ($fStatus === '') {
                $fStatus = 'none';
            }

            $users[] = [
                'id' => $id,
                'nama' => $nama,
                'nama_panggilan' => trim((string)($u['nama_panggilan'] ?? $u['nickname'] ?? '')) ?: get_nickname($nama),
                'username' => $username,
                'role' => $role !== '' ? $role : 'teknisi',
                'telepon' => format_phone_number($telepon),
                'status' => $status,
                'created_at' => $createdAt,
                'face_descriptor' => $fDesc,
                'face_photo' => $fPhoto,
                'face_status' => $fStatus,
                'passkey_credential' => (string)($u['passkey_credential'] ?? '')
            ];
        }

        if (empty($users)) {
            return [
                ['id' => 1, 'nama' => 'Administrator', 'nama_panggilan' => 'Admin', 'username' => 'admin', 'role' => 'admin', 'telepon' => '081234567890', 'status' => 'Aktif'],
                ['id' => 2, 'nama' => 'Teknisi IT', 'nama_panggilan' => 'Teknisi', 'username' => 'teknisi', 'role' => 'teknisi', 'telepon' => '081234567891', 'status' => 'Aktif'],
            ];
        }

        return $users;
    }

    // MySQL Mode
    try {
        db()->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama VARCHAR(150) NOT NULL,
                nama_panggilan VARCHAR(50) NULL,
                username VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(50) NOT NULL DEFAULT 'teknisi',
                telepon VARCHAR(50) NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'Aktif',
                face_descriptor TEXT NULL,
                face_photo MEDIUMTEXT NULL,
                face_status VARCHAR(50) DEFAULT 'none',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            );
        ");

        $cols = table_columns('users');
        if (!in_array('nama_panggilan', $cols, true)) {
            try { db()->exec("ALTER TABLE users ADD COLUMN nama_panggilan VARCHAR(50) NULL"); } catch (Throwable $e) {}
            $cols = table_columns('users');
        }
        if (!in_array('face_descriptor', $cols, true)) {
            try { db()->exec("ALTER TABLE users ADD COLUMN face_descriptor TEXT NULL, ADD COLUMN face_photo MEDIUMTEXT NULL, ADD COLUMN face_status VARCHAR(50) DEFAULT 'none'"); } catch (Throwable $e) {}
            $cols = table_columns('users');
        } elseif (!in_array('face_status', $cols, true)) {
            try { db()->exec("ALTER TABLE users ADD COLUMN face_status VARCHAR(50) DEFAULT 'none'"); } catch (Throwable $e) {}
            $cols = table_columns('users');
        }
        $nameCol = in_array('nama', $cols, true) ? 'nama' : (in_array('name', $cols, true) ? 'name' : 'username');
        $nickCol = in_array('nama_panggilan', $cols, true) ? 'nama_panggilan' : "'' AS nama_panggilan";
        $telCol = in_array('telepon', $cols, true) ? 'telepon' : "'-' AS telepon";
        $stCol = in_array('status', $cols, true) ? 'status' : "'Aktif' AS status";
        $faceDescCol = in_array('face_descriptor', $cols, true) ? 'face_descriptor' : "'' AS face_descriptor";
        $facePhotoCol = in_array('face_photo', $cols, true) ? 'face_photo' : "'' AS face_photo";
        $faceStatCol = in_array('face_status', $cols, true) ? 'face_status' : "'none' AS face_status";

        $users = db()->query("SELECT id, `{$nameCol}` AS nama, {$nickCol}, username, role, {$telCol}, {$stCol}, {$faceDescCol}, {$facePhotoCol}, {$faceStatCol}, created_at FROM users ORDER BY id ASC")->fetchAll();
        if (empty($users)) {
            $adminHash = password_hash('admin123', PASSWORD_BCRYPT);
            $teknisiHash = password_hash('teknisi123', PASSWORD_BCRYPT);
            db()->exec("
                INSERT IGNORE INTO users (`{$nameCol}`, nama_panggilan, username, password, role, telepon, status, created_at)
                VALUES ('Administrator', 'Admin', 'admin', '{$adminHash}', 'admin', '081234567890', 'Aktif', NOW()),
                       ('Teknisi IT', 'Teknisi', 'teknisi', '{$teknisiHash}', 'teknisi', '081234567891', 'Aktif', NOW())
            ");
            $users = db()->query("SELECT id, `{$nameCol}` AS nama, {$nickCol}, username, role, {$telCol}, {$stCol}, {$faceDescCol}, {$facePhotoCol}, {$faceStatCol}, created_at FROM users ORDER BY id ASC")->fetchAll();
        }
        return array_map(function($u) {
            $fDesc = (string)($u['face_descriptor'] ?? '');
            $fStatus = (string)($u['face_status'] ?? '');
            if ($fStatus === '' && $fDesc !== '') {
                $fStatus = 'verified';
            } elseif ($fStatus === '') {
                $fStatus = 'none';
            }
            $u['face_status'] = $fStatus;
            $u['nama_panggilan'] = trim((string)($u['nama_panggilan'] ?? '')) ?: get_nickname((string)($u['nama'] ?? ''));
            return $u;
        }, $users);
    } catch (Throwable $e) {
        return [
            ['id' => 1, 'nama' => 'Administrator', 'nama_panggilan' => 'Admin', 'username' => 'admin', 'role' => 'admin', 'telepon' => '-', 'status' => 'Aktif', 'face_descriptor' => '', 'face_photo' => '', 'face_status' => 'none'],
            ['id' => 2, 'nama' => 'Teknisi IT', 'nama_panggilan' => 'Teknisi', 'username' => 'teknisi', 'role' => 'teknisi', 'telepon' => '-', 'status' => 'Aktif', 'face_descriptor' => '', 'face_photo' => '', 'face_status' => 'none'],
        ];
    }
}

function get_user_by_id(int $id, bool $forceRefresh = false): ?array {
    if ($id <= 0) return null;
    $users = get_user_list($forceRefresh);
    foreach ($users as $u) {
        if ((int)($u['id'] ?? 0) === $id) return $u;
    }
    return null;
}

function save_user_biometrics(int $userId, string $descriptorJson, string $photoBase64 = '', string $status = 'pending'): array {
    if ($userId <= 0) return ['success' => false, 'error' => 'ID pengguna tidak valid'];
    if (empty($descriptorJson)) return ['success' => false, 'error' => 'Data biometrik tidak boleh kosong'];

    // Kompres atau rapikan foto agar tidak merusak base64 jika terlalu panjang
    if (strlen($photoBase64) > 30000) {
        if (function_exists('imagecreatefromstring')) {
            $commaPos = strpos($photoBase64, ',');
            $rawB64 = ($commaPos !== false) ? substr($photoBase64, $commaPos + 1) : $photoBase64;
            $bin = base64_decode($rawB64);
            if ($bin) {
                $im = @imagecreatefromstring($bin);
                if ($im) {
                    ob_start();
                    imagejpeg($im, null, 55);
                    $comp = ob_get_clean();
                    $photoBase64 = 'data:image/jpeg;base64,' . base64_encode($comp);
                    imagedestroy($im);
                }
            }
        }
        if (strlen($photoBase64) > 40000) {
            $photoBase64 = '';
        }
    }

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];

        // Pastikan kolom sheet Users mencukupi (minimal 26 kolom A..Z)
        $client->ensureMinColumns('Users', 26);

        // Ambil data terkini langsung dari Google Sheets
        $rows = $client->getSheetData('Users', true);
        $targetRow = null;
        foreach ($rows as $u) {
            if ((int)($u['id'] ?? 0) === $userId) {
                $targetRow = $u;
                break;
            }
        }

        // Auto-create jika user ID 1 atau 2 belum tercatat di Google Sheet
        if (!$targetRow && ($userId === 1 || $userId === 2)) {
            $username = ($userId === 1) ? 'admin' : 'teknisi';
            $nama = ($userId === 1) ? 'Administrator' : 'Teknisi IT';
            $hashedPass = password_hash(($userId === 1 ? 'admin123' : 'teknisi123'), PASSWORD_BCRYPT);
            $appended = $client->appendValues('Users!A:M', [[
                $userId,
                $username,
                $hashedPass,
                $nama,
                ($userId === 1 ? 'admin' : 'teknisi'),
                '-',
                'Aktif',
                date('Y-m-d H:i:s'),
                $descriptorJson,
                $photoBase64,
                $status,
                '',
                ($userId === 1 ? 'Admin' : 'Teknisi')
            ]], 'RAW');
            $client->clearCache('Users');
            get_user_list(true);
            return ['success' => true];
        }

        if (!$targetRow) return ['success' => false, 'error' => 'Pengguna tidak ditemukan di Google Sheets'];

        $rowNum = (int)($targetRow['_row_num'] ?? 0);
        if ($rowNum <= 1) return ['success' => false, 'error' => 'Gagal menentukan baris data pengguna'];

        // Pastikan header baris 1 memiliki nama kolom face_descriptor, face_photo, face_status jika belum ada
        $client->updateValues("Users!I1:K1", [['face_descriptor', 'face_photo', 'face_status']], 'RAW');

        // Simpan vektor biometrik, foto, dan status verifikasi ke baris pengguna menggunakan mode RAW
        $ok = $client->updateValues("Users!I{$rowNum}:K{$rowNum}", [[$descriptorJson, $photoBase64, $status]], 'RAW');
        $client->clearCache('Users');
        get_user_list(true);

        if (!$ok) {
            $err = $client->getLastError();
            return ['success' => false, 'error' => 'Gagal memperbarui data biometrik ke Google Sheets' . ($err ? ' (' . $err . ')' : '')];
        }

        return ['success' => true];
    }

    // MySQL Mode
    try {
        $cols = table_columns('users');
        if (!in_array('face_descriptor', $cols, true)) {
            try { db()->exec("ALTER TABLE users ADD COLUMN face_descriptor TEXT NULL, ADD COLUMN face_photo MEDIUMTEXT NULL, ADD COLUMN face_status VARCHAR(50) DEFAULT 'pending'"); } catch (Throwable $e) {}
        } elseif (!in_array('face_status', $cols, true)) {
            try { db()->exec("ALTER TABLE users ADD COLUMN face_status VARCHAR(50) DEFAULT 'pending'"); } catch (Throwable $e) {}
        }
        $st = db()->prepare("UPDATE users SET face_descriptor = ?, face_photo = ?, face_status = ? WHERE id = ?");
        $st->execute([$descriptorJson, $photoBase64, $status, $userId]);
        return ['success' => true];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function update_user_face_status(int $userId, string $status): array {
    if ($userId <= 0) return ['success' => false, 'error' => 'ID pengguna tidak valid'];

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
        if (!$targetRow) return ['success' => false, 'error' => 'Pengguna tidak ditemukan'];

        $rowNum = (int)($targetRow['_row_num'] ?? 0);
        if ($rowNum <= 1) return ['success' => false, 'error' => 'Gagal baris pengguna'];

        // Pastikan header baris 1 terpasang
        $client->updateValues("Users!I1:K1", [['face_descriptor', 'face_photo', 'face_status']], 'RAW');

        if ($status === 'rejected' || $status === 'deleted') {
            $ok = $client->updateValues("Users!I{$rowNum}:K{$rowNum}", [['', '', 'none']], 'RAW');
        } else {
            $ok = $client->updateValues("Users!K{$rowNum}", [[$status]], 'RAW');
        }
        $client->clearCache('Users');
        get_user_list(true);

        if (!$ok) {
            $err = $client->getLastError();
            return ['success' => false, 'error' => 'Gagal memperbarui status verifikasi di Google Sheets' . ($err ? ' (' . $err . ')' : '')];
        }

        return ['success' => true];
    }

    // MySQL Mode
    try {
        if ($status === 'rejected' || $status === 'deleted') {
            $st = db()->prepare("UPDATE users SET face_descriptor = NULL, face_photo = NULL, face_status = 'none' WHERE id = ?");
            $st->execute([$userId]);
        } else {
            $st = db()->prepare("UPDATE users SET face_status = ? WHERE id = ?");
            $st->execute([$status, $userId]);
        }
        return ['success' => true];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function get_enrolled_technicians(bool $forceRefresh = false): array {
    $users = get_user_list($forceRefresh);
    $result = [];
    foreach ($users as $u) {
        $descStr = trim((string)($u['face_descriptor'] ?? ''));
        $fStatus = strtolower(trim((string)($u['face_status'] ?? '')));
        // WAJIB: Hanya teknisi yang biometrik wajahnya SUDAH DISETUJUI / DIVERIFIKASI ADMIN yang dapat melakukan scan wajah!
        $isVerified = ($fStatus === 'verified' || $fStatus === 'terverifikasi');
        if ($descStr !== '' && $isVerified && (strcasecmp($u['status'] ?? '', 'Nonaktif') !== 0)) {
            $descArr = json_decode($descStr, true);
            if (is_array($descArr) && count($descArr) >= 64) {
                $result[] = [
                    'id' => (int)$u['id'],
                    'nama' => (string)$u['nama'],
                    'username' => (string)$u['username'],
                    'role' => (string)$u['role'],
                    'descriptor' => $descArr,
                    'photo' => (string)($u['face_photo'] ?? '')
                ];
            }
        }
    }
    return $result;
}


function create_new_user(array $data): array {
    $nama = trim((string)($data['nama'] ?? ''));
    if ($nama === '') return ['success' => false, 'error' => 'Nama lengkap wajib diisi'];

    $namaPanggilan = trim((string)($data['nama_panggilan'] ?? ''));
    if ($namaPanggilan === '') {
        $namaPanggilan = get_nickname($nama);
    }

    $username = strtolower(trim((string)($data['username'] ?? '')));
    if ($username === '') {
        $clean = strtolower(preg_replace('/[^a-z0-9]/', '', $nama));
        $username = (strlen($clean) >= 3) ? $clean : 'teknisi_' . substr(uniqid(), -5);
    }

    $password = trim((string)($data['password'] ?? ''));
    if ($password === '') {
        $password = 'teknisi123';
    }

    $role = strtolower(trim((string)($data['role'] ?? 'teknisi')));
    $telepon = trim((string)($data['telepon'] ?? ''));
    $status = trim((string)($data['status'] ?? 'Aktif')) ?: 'Aktif';

    if (!in_array($role, ['admin', 'teknisi', 'auditor'], true)) {
        $role = 'teknisi';
    }

    $faceDescriptor = trim((string)($data['face_descriptor'] ?? ''));
    $facePhoto = trim((string)($data['face_photo'] ?? ''));
    $faceStatus = trim((string)($data['face_status'] ?? ''));
    if ($faceStatus === '') {
        $faceStatus = ($faceDescriptor !== '') ? 'pending' : 'none';
    }

    $hashedPass = password_hash($password, PASSWORD_BCRYPT);

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];

        // Pastikan kapasitas kolom sheet Users mencukupi (minimal 26 kolom A..Z)
        $client->ensureMinColumns('Users', 26);

        $rows = $client->getSheetData('Users', true);
        if (empty($rows)) {
            $client->appendValues('Users!A:M', [
                ['id', 'username', 'password', 'nama', 'role', 'telepon', 'status', 'created_at', 'face_descriptor', 'face_photo', 'face_status', 'passkey_credential', 'nama_panggilan']
            ], 'RAW');
            $rows = [];
        } else {
            $client->updateValues("Users!I1:M1", [['face_descriptor', 'face_photo', 'face_status', 'passkey_credential', 'nama_panggilan']], 'RAW');
        }

        $maxId = 0;
        $existingUsers = [];
        foreach ($rows as $r) {
            $uid = (int)($r['id'] ?? 0);
            if ($uid > $maxId) $maxId = $uid;
            $existUser = strtolower(trim((string)($r['username'] ?? '')));
            if ($existUser !== '') {
                $existingUsers[] = $existUser;
            }
        }

        // Hindari duplikasi username secara otomatis dengan menambah suffix jika ada collision
        $baseUser = $username;
        $counter = 1;
        while (in_array($username, $existingUsers, true)) {
            $counter++;
            $username = $baseUser . $counter;
        }

        $newId = max(count($rows) + 1, $maxId + 1);
        $teleponFormatted = format_phone_number($telepon);
        $teleponStored = ($teleponFormatted !== '-' && $teleponFormatted !== '') ? $teleponFormatted : $telepon;
        $teleponSheet = ($teleponStored !== '' && $teleponStored !== '-') ? "'" . $teleponStored : '-';

        $newRow = [
            $newId,
            $username,
            $hashedPass,
            $nama,
            $role,
            $teleponSheet,
            $status,
            date('Y-m-d H:i:s'),
            $faceDescriptor, // face_descriptor (col I)
            $facePhoto,      // face_photo (col J)
            $faceStatus,     // face_status (col K)
            '',              // passkey_credential (col L)
            $namaPanggilan   // nama_panggilan (col M)
        ];

        // Tentukan nomor baris target secara pasti berdasarkan Kolom A agar TIDAK TERGESER KE KOLOM O
        $colAValues = $client->getValues('Users!A:A');
        $nextRowNum = count($colAValues) + 1;
        if ($nextRowNum < 2) $nextRowNum = 2;

        $appended = $client->updateValues("Users!A{$nextRowNum}:M{$nextRowNum}", [$newRow], 'RAW');
        if (!$appended) {
            // Coba appendValues sebagai fallback darurat jika update gagal
            $appended = $client->appendValues('Users!A:M', [$newRow], 'RAW');
        }

        if (!$appended) {
            $err = $client->getLastError();
            return ['success' => false, 'error' => 'Gagal menyimpan user baru ke Google Sheets' . ($err ? ' (' . $err . ')' : '')];
        }

        $client->clearCache('Users');
        get_user_list(true);

        return [
            'success' => true,
            'id' => $newId,
            'nama' => $nama,
            'nama_panggilan' => $namaPanggilan,
            'username' => $username,
            'user' => [
                'id' => $newId,
                'nama' => $nama,
                'nama_panggilan' => $namaPanggilan,
                'username' => $username,
                'role' => $role,
                'telepon' => $teleponStored,
                'status' => $status,
                'face_status' => $faceStatus
            ]
        ];
    }

    // MySQL Mode
    try {
        db()->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama VARCHAR(150) NOT NULL,
                nama_panggilan VARCHAR(50) NULL,
                username VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(50) NOT NULL DEFAULT 'teknisi',
                telepon VARCHAR(50) NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'Aktif',
                face_descriptor TEXT NULL,
                face_photo MEDIUMTEXT NULL,
                face_status VARCHAR(50) DEFAULT 'none',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            );
        ");

        $checkSt = db()->prepare("SELECT id FROM users WHERE LOWER(username) = ? LIMIT 1");
        $checkSt->execute([$username]);
        if ($checkSt->fetchColumn()) {
            return ['success' => false, 'error' => 'Username sudah digunakan, silakan pilih username lain'];
        }

        $cols = table_columns('users');
        if (!in_array('nama_panggilan', $cols, true)) {
            try { db()->exec("ALTER TABLE users ADD COLUMN nama_panggilan VARCHAR(50) NULL"); } catch (Throwable $e) {}
            $cols = table_columns('users');
        }
        if (!in_array('face_descriptor', $cols, true)) {
            try { db()->exec("ALTER TABLE users ADD COLUMN face_descriptor TEXT NULL, ADD COLUMN face_photo MEDIUMTEXT NULL, ADD COLUMN face_status VARCHAR(50) DEFAULT 'none'"); } catch (Throwable $e) {}
            $cols = table_columns('users');
        }
        $nameCol = in_array('nama', $cols, true) ? 'nama' : (in_array('name', $cols, true) ? 'name' : 'username');

        $teleponFormatted = format_phone_number($telepon);
        $teleponStored = ($teleponFormatted !== '-' && $teleponFormatted !== '') ? $teleponFormatted : $telepon;

        $hasNick = in_array('nama_panggilan', $cols, true);
        $hasBio = in_array('face_descriptor', $cols, true);

        if ($hasNick && $hasBio) {
            $ins = db()->prepare("
                INSERT INTO users (`{$nameCol}`, nama_panggilan, username, password, role, telepon, status, face_descriptor, face_photo, face_status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $ins->execute([$nama, $namaPanggilan, $username, $hashedPass, $role, $teleponStored, $status, $faceDescriptor ?: null, $facePhoto ?: null, $faceStatus]);
        } elseif ($hasNick) {
            $ins = db()->prepare("
                INSERT INTO users (`{$nameCol}`, nama_panggilan, username, password, role, telepon, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $ins->execute([$nama, $namaPanggilan, $username, $hashedPass, $role, $teleponStored, $status]);
        } else {
            $ins = db()->prepare("
                INSERT INTO users (`{$nameCol}`, username, password, role, telepon, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $ins->execute([$nama, $username, $hashedPass, $role, $teleponStored, $status]);
        }
        $newId = (int)db()->lastInsertId();

        return ['success' => true, 'id' => $newId, 'username' => $username, 'nama' => $nama, 'nama_panggilan' => $namaPanggilan];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function update_user(int $id, array $data): array {
    if ($id <= 0) return ['success' => false, 'error' => 'ID pengguna tidak valid'];

    $nama = trim((string)($data['nama'] ?? ''));
    $namaPanggilan = trim((string)($data['nama_panggilan'] ?? ''));
    if ($namaPanggilan === '' && $nama !== '') {
        $namaPanggilan = get_nickname($nama);
    }
    $role = strtolower(trim((string)($data['role'] ?? 'teknisi')));
    $telepon = trim((string)($data['telepon'] ?? ''));
    $status = trim((string)($data['status'] ?? 'Aktif')) ?: 'Aktif';
    $password = trim((string)($data['password'] ?? ''));

    if ($nama === '') return ['success' => false, 'error' => 'Nama lengkap wajib diisi'];
    if (!in_array($role, ['admin', 'teknisi', 'auditor'], true)) {
        $role = 'teknisi';
    }

    $teleponFormatted = format_phone_number($telepon);
    $teleponStored = ($teleponFormatted !== '-' && $teleponFormatted !== '') ? $teleponFormatted : $telepon;
    $teleponSheet = ($teleponStored !== '' && $teleponStored !== '-') ? "'" . $teleponStored : '-';

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];

        $rows = $client->getSheetData('Users', true);
        $targetRow = null;
        foreach ($rows as $r) {
            if ((int)($r['id'] ?? 0) === $id) {
                $targetRow = $r;
                break;
            }
        }

        if (!$targetRow) {
            // Jika ID 1 atau 2 belum tercatat di sheet tapi ingin di-edit, buat barisnya
            if ($id === 1 || $id === 2) {
                $username = ($id === 1) ? 'admin' : 'teknisi';
                $hashedPass = $password !== '' ? password_hash($password, PASSWORD_BCRYPT) : password_hash(($id === 1 ? 'admin123' : 'teknisi123'), PASSWORD_BCRYPT);
                $client->appendValues('Users!A:M', [[
                    $id,
                    $username,
                    $hashedPass,
                    $nama,
                    $role,
                    $teleponSheet,
                    $status,
                    date('Y-m-d H:i:s'),
                    '', '', 'verified', '', $namaPanggilan
                ]]);
                $client->clearCache('Users');
                return ['success' => true, 'id' => $id, 'nama' => $nama, 'nama_panggilan' => $namaPanggilan];
            }
            return ['success' => false, 'error' => 'Pengguna tidak ditemukan'];
        }

        $rowNum = (int)($targetRow['_row_num'] ?? 0);
        if ($rowNum <= 1) return ['success' => false, 'error' => 'Gagal menentukan baris data pengguna'];

        $hashedPass = $password !== '' ? password_hash($password, PASSWORD_BCRYPT) : ($targetRow['password'] ?? '');
        $username = (string)($targetRow['username'] ?? '');

        $updated = $client->updateValues("Users!A{$rowNum}:H{$rowNum}", [[
            $id,
            $username,
            $hashedPass,
            $nama,
            $role,
            $teleponSheet,
            $status,
            $targetRow['created_at'] ?? date('Y-m-d H:i:s')
        ]]);

        if (!$updated) return ['success' => false, 'error' => 'Gagal memperbarui data pengguna di Google Sheets'];

        $client->updateValues("Users!M1", [['nama_panggilan']]);
        if ($namaPanggilan !== '') {
            $client->updateValues("Users!M{$rowNum}", [[$namaPanggilan]]);
        }

        $client->clearCache('Users');
        return ['success' => true, 'id' => $id, 'nama' => $nama, 'nama_panggilan' => $namaPanggilan];
    }

    // MySQL Mode
    try {
        $cols = table_columns('users');
        if (!in_array('nama_panggilan', $cols, true)) {
            try { db()->exec("ALTER TABLE users ADD COLUMN nama_panggilan VARCHAR(50) NULL"); } catch (Throwable $e) {}
            $cols = table_columns('users');
        }
        $nameCol = in_array('nama', $cols, true) ? 'nama' : (in_array('name', $cols, true) ? 'name' : 'username');
        $hasNick = in_array('nama_panggilan', $cols, true);

        if ($password !== '') {
            $hashedPass = password_hash($password, PASSWORD_BCRYPT);
            if ($hasNick) {
                $up = db()->prepare("UPDATE users SET `{$nameCol}` = ?, nama_panggilan = ?, role = ?, telepon = ?, status = ?, password = ? WHERE id = ?");
                $up->execute([$nama, $namaPanggilan, $role, $teleponStored, $status, $hashedPass, $id]);
            } else {
                $up = db()->prepare("UPDATE users SET `{$nameCol}` = ?, role = ?, telepon = ?, status = ?, password = ? WHERE id = ?");
                $up->execute([$nama, $role, $teleponStored, $status, $hashedPass, $id]);
            }
        } else {
            if ($hasNick) {
                $up = db()->prepare("UPDATE users SET `{$nameCol}` = ?, nama_panggilan = ?, role = ?, telepon = ?, status = ? WHERE id = ?");
                $up->execute([$nama, $namaPanggilan, $role, $teleponStored, $status, $id]);
            } else {
                $up = db()->prepare("UPDATE users SET `{$nameCol}` = ?, role = ?, telepon = ?, status = ? WHERE id = ?");
                $up->execute([$nama, $role, $teleponStored, $status, $id]);
            }
        }

        return ['success' => true, 'id' => $id, 'nama' => $nama, 'nama_panggilan' => $namaPanggilan];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function delete_user(int $id): array {
    if ($id <= 0) {
        return ['success' => false, 'error' => 'ID pengguna tidak valid'];
    }

    // Cegah admin menghapus akunnya sendiri yang sedang aktif login
    if ($id === current_user_id()) {
        return ['success' => false, 'error' => 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan.'];
    }

    if (is_google_cloud_mode()) {
        $client = google_sheets_v4_client();
        if (!$client) {
            return ['success' => false, 'error' => 'Google Sheets client tidak tersedia'];
        }

        $users = $client->getSheetData('Users', true);
        $targetRow = null;
        foreach ($users as $u) {
            if ((int)($u['id'] ?? 0) === $id) {
                $targetRow = $u;
                break;
            }
        }

        if (!$targetRow) {
            return ['success' => false, 'error' => 'Data pengguna tidak ditemukan'];
        }

        $rowNum = (int)($targetRow['_row_num'] ?? 0);
        if ($rowNum > 1) {
            $deleted = $client->deleteRow('Users', $rowNum);
            if (!$deleted) {
                $client->clearValues("Users!A{$rowNum}:L{$rowNum}");
            }
        }

        $client->clearCache('Users');
        get_user_list(true);
        return ['success' => true];
    }

    // MySQL Mode
    try {
        $st = db()->prepare("DELETE FROM users WHERE id = ?");
        $st->execute([$id]);
        return ['success' => true];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => 'Gagal menghapus pengguna: ' . $e->getMessage()];
    }
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

/**
 * Menghasilkan nama panggilan pendek dari nama lengkap (misal: "Bpk. Roni Wijaya, S.Kom" -> "Roni")
 */
function get_nickname(string $fullName): string {
    $fullName = trim($fullName);
    if ($fullName === '' || $fullName === '-') return '-';

    // Hapus gelar kehormatan di depan
    $clean = preg_replace('/^(bpk|bapak|ibu|sdr|sdri|mr|mrs|dr|drs|ir|prof)\.?\s+/i', '', $fullName);
    // Hapus gelar akademis / sertifikasi di belakang koma (cth: ", S.Kom", ", M.M", dsb)
    $clean = preg_replace('/,.*$/', '', $clean);
    // Hapus kurung divisi jika ada (cth: "Roni (IT / MIS)" -> "Roni")
    $clean = preg_replace('/\(.*?\)/', '', $clean);
    $clean = trim($clean);

    if ($clean === '') return $fullName;

    // Ambil kata pertama sebagai nama panggilan
    $words = preg_split('/\s+/', $clean);
    return !empty($words[0]) ? $words[0] : $fullName;
}

/**
 * Mencari nama panggilan teknisi berdasarkan nama lengkap, username, atau ID teknisi.
 * Jika teknisi memiliki nama_panggilan yang diatur di data akun, gunakan nama tersebut.
 * Jika tidak, fallback ke get_nickname($techNameOrId).
 */
function get_technician_nickname(string $techNameOrId): string {
    $techNameOrId = trim($techNameOrId);
    if ($techNameOrId === '' || $techNameOrId === '-') return '-';

    static $nickMap = null;
    if ($nickMap === null) {
        $nickMap = [];
        try {
            $users = get_user_list();
            foreach ($users as $u) {
                $panggilan = trim((string)($u['nama_panggilan'] ?? ''));
                $fullName = trim((string)($u['nama'] ?? ''));
                $uname = trim((string)($u['username'] ?? ''));
                $uid = (int)($u['id'] ?? 0);
                $effectiveNick = $panggilan !== '' ? $panggilan : get_nickname($fullName);
                if ($fullName !== '') $nickMap[mb_strtolower($fullName)] = $effectiveNick;
                if ($uname !== '') $nickMap[mb_strtolower($uname)] = $effectiveNick;
                if ($uid > 0) $nickMap[(string)$uid] = $effectiveNick;
            }
        } catch (Throwable $e) {}
    }

    $key = mb_strtolower($techNameOrId);
    if (isset($nickMap[$key]) && $nickMap[$key] !== '') {
        return $nickMap[$key];
    }
    // Cek juga kecocokan substring jika nama memiliki gelar atau formatting berbeda
    $cleanedKey = mb_strtolower(get_nickname($techNameOrId));
    if ($cleanedKey !== '' && $cleanedKey !== '-') {
        foreach ($nickMap as $mk => $mv) {
            $mkStr = (string)$mk;
            $mvStr = (string)$mv;
            if ($mkStr === '' || $mvStr === '' || is_numeric($mkStr)) continue;
            if (strpos($mkStr, $cleanedKey) !== false || strpos($cleanedKey, $mkStr) !== false) {
                return $mvStr;
            }
        }
    }

    return get_nickname($techNameOrId);
}


// DATA ABSTRACTION LAYER FOR GOOGLE CLOUD SHEETS API V4 VS MYSQL

