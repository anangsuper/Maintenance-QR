<?php
require __DIR__ . '/bootstrap.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metode request tidak diizinkan']);
    exit;
}

verify_csrf();

$nama = trim((string)($_POST['nama_divisi'] ?? ''));
$keterangan = trim((string)($_POST['keterangan'] ?? ''));

if ($nama === '') {
    echo json_encode(['success' => false, 'error' => 'Nama divisi / unit kerja wajib diisi']);
    exit;
}

$res = create_new_divisi([
    'nama_divisi' => $nama,
    'keterangan' => $keterangan
]);

if (!empty($res['success'])) {
    echo json_encode([
        'success' => true,
        'id' => (int)$res['id'],
        'nama' => $res['nama'],
        'message' => 'Divisi "' . $res['nama'] . '" berhasil ditambahkan'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => $res['error'] ?? 'Gagal menambahkan divisi baru'
    ]);
}
exit;
