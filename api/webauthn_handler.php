<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function b64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
}

function get_rp_id(): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $parts = explode(':', $host);
    return strtolower($parts[0]);
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

// =========================================================================
// 1. REGISTER OPTIONS: Buat tantangan kriptografi untuk pendaftaran Face ID
// =========================================================================
if ($action === 'register_options') {
    $userId = (int)($_GET['user_id'] ?? current_user_id() ?? 0);
    if ($userId <= 0) {
        $allUsers = get_user_list(true);
        if (!empty($allUsers)) {
            $userId = (int)($allUsers[0]['id'] ?? 0);
        }
    }

    $user = get_user_by_id($userId, true);
    if (!$user) {
        json_out(['success' => false, 'error' => 'Pengguna tidak ditemukan.'], 404);
    }

    $challenge = random_bytes(32);
    $_SESSION['webauthn_reg_challenge'] = b64url_encode($challenge);
    $_SESSION['webauthn_reg_user_id'] = $userId;

    $rpId = get_rp_id();
    $userIdHandle = b64url_encode(pack('N', $userId));

    // Ambil daftar credential yang sudah ada agar tidak didaftarkan ulang di perangkat yang sama
    $existing = get_user_passkeys($userId);
    $excludeList = [];
    foreach ($existing as $ex) {
        $exId = trim((string)($ex['id'] ?? ''));
        if ($exId !== '') {
            $excludeList[] = [
                'type' => 'public-key',
                'id' => $exId
            ];
        }
    }

    $options = [
        'challenge' => b64url_encode($challenge),
        'rp' => [
            'name' => 'QR Maintenance System',
            'id' => $rpId
        ],
        'user' => [
            'id' => $userIdHandle,
            'name' => (string)$user['username'],
            'displayName' => (string)$user['nama']
        ],
        'pubKeyCredParams' => [
            ['type' => 'public-key', 'alg' => -7],   // ES256 (Standar Apple iOS Face ID & Android)
            ['type' => 'public-key', 'alg' => -257]  // RS256 (Windows Hello / Mac)
        ],
        'authenticatorSelection' => [
            'authenticatorAttachment' => 'platform', // Sensor Face ID / Touch ID fisik HP
            'userVerification' => 'required',
            'residentKey' => 'preferred'
        ],
        'timeout' => 60000,
        'attestation' => 'none',
        'excludeCredentials' => $excludeList
    ];

    json_out(['success' => true, 'options' => $options]);
}

// =========================================================================
// 2. REGISTER VERIFY: Simpan Kredensial Face ID ke Google Sheets / Database
// =========================================================================
if ($action === 'register_verify') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = $_POST;
    }

    $sessChallenge = (string)($_SESSION['webauthn_reg_challenge'] ?? '');
    $sessUserId = (int)($_SESSION['webauthn_reg_user_id'] ?? 0);
    unset($_SESSION['webauthn_reg_challenge'], $_SESSION['webauthn_reg_user_id']);

    if ($sessChallenge === '' || $sessUserId <= 0) {
        json_out(['success' => false, 'error' => 'Sesi pendaftaran kedaluwarsa. Silakan ulangi.'], 400);
    }

    $credId = trim((string)($body['id'] ?? ''));
    $rawId = trim((string)($body['rawId'] ?? $credId));
    $response = $body['response'] ?? [];
    $clientDataJSONB64 = (string)($response['clientDataJSON'] ?? '');

    if ($credId === '' || $clientDataJSONB64 === '') {
        json_out(['success' => false, 'error' => 'Data biometrik tidak lengkap.'], 400);
    }

    // Verifikasi kesesuaian tantangan (challenge)
    $clientDataJSON = b64url_decode($clientDataJSONB64);
    $clientData = json_decode($clientDataJSON, true);
    if (!is_array($clientData) || (($clientData['challenge'] ?? '') !== $sessChallenge)) {
        json_out(['success' => false, 'error' => 'Verifikasi token biometrik tidak valid.'], 400);
    }

    $deviceName = trim((string)($body['device_name'] ?? 'Apple iPhone (Face ID)'));

    // Simpan ke Google Sheets (Kolom L) atau MySQL
    $saved = save_user_passkey($sessUserId, [
        'id' => $credId,
        'raw_id' => $rawId,
        'public_key' => $response['attestationObject'] ?? '',
        'device_name' => $deviceName
    ]);

    if (!empty($saved['success'])) {
        json_out([
            'success' => true,
            'message' => 'Face ID iPhone berhasil didaftarkan! Anda kini dapat masuk atau verifikasi checklist secara instan.'
        ]);
    } else {
        json_out(['success' => false, 'error' => $saved['error'] ?? 'Gagal menyimpan ke Google Sheets'], 500);
    }
}

// =========================================================================
// 3. AUTH OPTIONS: Buat tantangan untuk Login / Scan Maintenance via Face ID
// =========================================================================
if ($action === 'auth_options') {
    $challenge = random_bytes(32);
    $_SESSION['webauthn_auth_challenge'] = b64url_encode($challenge);

    $rpId = get_rp_id();
    $userId = (int)($_GET['user_id'] ?? 0);

    $allowCredentials = [];
    if ($userId > 0) {
        $pks = get_user_passkeys($userId);
        foreach ($pks as $pk) {
            $pkId = trim((string)($pk['id'] ?? ''));
            if ($pkId !== '') {
                $allowCredentials[] = [
                    'type' => 'public-key',
                    'id' => $pkId
                ];
            }
        }
    } else {
        // Mode Discoverable Credential: izinkan seluruh credential teknisi yang terdaftar
        $allUsers = get_user_list(true);
        foreach ($allUsers as $u) {
            $raw = trim((string)($u['passkey_credential'] ?? ''));
            if ($raw === '') continue;
            $passkeys = json_decode($raw, true);
            if (is_array($passkeys)) {
                foreach ($passkeys as $pk) {
                    $pkId = trim((string)($pk['id'] ?? ''));
                    if ($pkId !== '') {
                        $allowCredentials[] = [
                            'type' => 'public-key',
                            'id' => $pkId
                        ];
                    }
                }
            }
        }
    }

    $options = [
        'challenge' => b64url_encode($challenge),
        'rpId' => $rpId,
        'timeout' => 60000,
        'userVerification' => 'required',
        'allowCredentials' => $allowCredentials
    ];

    json_out(['success' => true, 'options' => $options]);
}

// =========================================================================
// 4. AUTH VERIFY: Validasi Face ID & Login Sesi / Selesaikan Maintenance
// =========================================================================
if ($action === 'auth_verify') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = $_POST;
    }

    $sessChallenge = (string)($_SESSION['webauthn_auth_challenge'] ?? '');
    unset($_SESSION['webauthn_auth_challenge']);

    if ($sessChallenge === '') {
        json_out(['success' => false, 'error' => 'Sesi verifikasi telah kedaluwarsa. Silakan coba lagi.'], 400);
    }

    $credId = trim((string)($body['id'] ?? ''));
    $response = $body['response'] ?? [];
    $clientDataJSONB64 = (string)($response['clientDataJSON'] ?? '');

    if ($credId === '' || $clientDataJSONB64 === '') {
        json_out(['success' => false, 'error' => 'Data verifikasi tidak valid.'], 400);
    }

    // Verifikasi kesesuaian tantangan
    $clientDataJSON = b64url_decode($clientDataJSONB64);
    $clientData = json_decode($clientDataJSON, true);
    if (!is_array($clientData) || (($clientData['challenge'] ?? '') !== $sessChallenge)) {
        json_out(['success' => false, 'error' => 'Tanda tangan biometrik tidak cocok.'], 400);
    }

    // Cari pengguna pemilik Face ID di Google Sheets / database
    $user = find_user_by_passkey_cred_id($credId);
    if (!$user) {
        json_out(['success' => false, 'error' => 'Face ID ini belum terdaftar pada akun mana pun.'], 404);
    }

    if (strcasecmp((string)($user['status'] ?? 'Aktif'), 'Nonaktif') === 0) {
        json_out(['success' => false, 'error' => 'Akun pengguna ini berstatus nonaktif.'], 403);
    }

    // Autentikasi sesi login pengguna
    login_user_session($user);

    $purpose = trim((string)($body['purpose'] ?? 'login'));
    $redirectUrl = $_SESSION['after_login'] ?? module_url('dashboard.php');
    unset($_SESSION['after_login']);

    json_out([
        'success' => true,
        'message' => 'Verifikasi Face ID berhasil!',
        'user' => [
            'id' => (int)$user['id'],
            'nama' => (string)$user['nama'],
            'username' => (string)$user['username'],
            'role' => (string)$user['role']
        ],
        'redirect' => $redirectUrl
    ]);
}

// =========================================================================
// 5. STATUS: Cek apakah user memiliki Face ID terdaftar
// =========================================================================
if ($action === 'check_passkey') {
    $userId = (int)($_GET['user_id'] ?? current_user_id() ?? 0);
    $pks = get_user_passkeys($userId);
    json_out([
        'success' => true,
        'has_passkey' => !empty($pks),
        'count' => count($pks),
        'devices' => array_map(function($p) {
            return [
                'device_name' => $p['device_name'] ?? 'iPhone Face ID',
                'created_at' => $p['created_at'] ?? ''
            ];
        }, $pks)
    ]);
}

json_out(['success' => false, 'error' => 'Aksi tidak dikenali.'], 400);
