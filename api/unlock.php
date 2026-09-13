<?php
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metode permintaan tidak diizinkan.']);
    exit;
}

// Pastikan user masih memiliki sesi
$userId = current_user_id();
$username = (string)($_SESSION['username'] ?? '');

if ($userId <= 0 || $username === '') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Sesi Anda telah berakhir. Silakan masuk kembali.',
        'expired' => true,
        'redirect' => module_url('login.php', ['expired' => 1])
    ]);
    exit;
}

// Ambil input JSON atau form POST
$input = json_decode((string)file_get_contents('php://input'), true);
$password = '';
if (is_array($input) && isset($input['password'])) {
    $password = (string)$input['password'];
} elseif (isset($_POST['password'])) {
    $password = (string)$_POST['password'];
}

$password = trim($password);

if ($password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Kata sandi wajib diisi.']);
    exit;
}

// Verifikasi password pengguna saat ini
$res = verify_current_user_password($password);

if (!empty($res['success'])) {
    $_SESSION['last_activity'] = time();
    record_audit_log('SESSION_UNLOCK', 'KEAMANAN', $userId, current_user_name(), 'Sesi dibuka kembali setelah terkunci (idle)');
    echo json_encode([
        'success' => true,
        'message' => 'Sesi berhasil dibuka kembali.',
        'user' => [
            'name' => current_user_name(),
            'username' => $username,
            'role' => current_user_role()
        ]
    ]);
    exit;
}

// Password salah
record_audit_log('UNLOCK_FAILED', 'KEAMANAN', $userId, current_user_name(), 'Percobaan buka kunci sesi gagal: Kata sandi salah');
http_response_code(401);
echo json_encode([
    'success' => false,
    'error' => $res['error'] ?? 'Kata sandi tidak sesuai. Silakan coba lagi.'
]);
exit;
