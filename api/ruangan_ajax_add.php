<?php
require __DIR__ . '/bootstrap.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metode request tidak diizinkan']);
    exit;
}

verify_csrf();

$nama = strtoupper(trim((string)($_POST['nama_ruangan'] ?? '')));
$kode = trim((string)($_POST['kode_ruangan'] ?? ''));

if ($nama === '') {
    echo json_encode(['success' => false, 'error' => 'Nama ruangan penempatan wajib diisi']);
    exit;
}

if ($kode === '') {
    $existing = get_master_ruangan_list();
    $kode = sprintf('%02d', count($existing) + 1);
}

$ok = insert_master_ruangan($kode, $nama);
if ($ok) {
    $formatted = "{$kode} : {$nama}";
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
