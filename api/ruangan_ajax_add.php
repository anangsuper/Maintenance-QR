<?php
require __DIR__ . '/bootstrap.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metode request tidak diizinkan']);
    exit;
}

verify_csrf();
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$nama = strtoupper(trim((string)($_POST['nama_ruangan'] ?? '')));
$kode = trim((string)($_POST['kode_ruangan'] ?? ''));

if ($nama === '') {
    echo json_encode(['success' => false, 'error' => 'Nama ruangan penempatan wajib diisi']);
    exit;
}

$formatted = add_master_ruangan($nama, $kode !== '' ? $kode : null);
if ($formatted !== '') {
    echo json_encode([
        'success' => true,
        'kode' => $kode,
        'nama' => $nama,
        'value' => $formatted,
        'message' => 'Ruangan "' . $formatted . '" berhasil ditambahkan'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Gagal menyimpan ruangan atau ruangan sudah terdaftar'
    ]);
}
exit;
