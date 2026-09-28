<?php
require __DIR__ . '/bootstrap.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metode request tidak diizinkan']);
    exit;
}

verify_csrf();

$rawIds = $_POST['ids'] ?? '';
$ids = [];
if (is_array($rawIds)) {
    $ids = $rawIds;
} elseif (is_string($rawIds) && $rawIds !== '') {
    $decoded = json_decode($rawIds, true);
    if (is_array($decoded)) {
        $ids = $decoded;
    } else {
        $ids = explode(',', $rawIds);
    }
}

if (empty($ids)) {
    echo json_encode(['success' => false, 'error' => 'Silakan pilih minimal 1 unit aset untuk diedit']);
    exit;
}

$idCabang = (int)($_POST['id_cabang'] ?? 0);
$idDivisi = (int)($_POST['id_divisi'] ?? 0);
$idKategori = (int)($_POST['id_kategori'] ?? 0);
$status = trim((string)($_POST['status'] ?? ''));
$placement = trim((string)($_POST['placement_label'] ?? ''));
$changePrinter = !empty($_POST['change_printer']);
$printer = trim((string)($_POST['printer'] ?? ''));
$changeKaryawan = !empty($_POST['change_karyawan']);
$namaKaryawan = trim((string)($_POST['nama_karyawan'] ?? ''));

// Jika ada divisi baru yang dibuat langsung
$namaDivisiBaru = trim((string)($_POST['nama_divisi_baru'] ?? ''));
if ($namaDivisiBaru !== '') {
    $resDiv = create_new_divisi(['nama_divisi' => $namaDivisiBaru]);
    if (!empty($resDiv['success'])) {
        $idDivisi = (int)$resDiv['id'];
    }
}

// Cek apakah ada setidaknya satu bidang yang dipilih untuk diubah
$hasChanges = (
    $idCabang > 0 ||
    $idDivisi > 0 ||
    $idKategori > 0 ||
    ($status !== '' && $status !== 'keep') ||
    ($placement !== '' && $placement !== 'keep') ||
    $changePrinter ||
    $changeKaryawan
);

if (!$hasChanges) {
    echo json_encode(['success' => false, 'error' => 'Pilih minimal satu kolom data yang ingin diubah secara massal']);
    exit;
}

$updates = [
    'id_cabang' => $idCabang,
    'id_divisi' => $idDivisi,
    'id_kategori' => $idKategori,
    'status' => $status,
    'placement_label' => $placement,
    'change_printer' => $changePrinter,
    'printer' => $printer,
    'change_karyawan' => $changeKaryawan,
    'nama_karyawan' => $namaKaryawan
];

$res = bulk_update_assets($ids, $updates);

if (!empty($res['success'])) {
    echo json_encode([
        'success' => true,
        'updated_count' => $res['updated_count'],
        'total_requested' => $res['total_requested'],
        'message' => "Berhasil memperbarui {$res['updated_count']} unit komputer terpilih secara massal.",
        'errors' => $res['errors'] ?? []
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => $res['error'] ?? (!empty($res['errors']) ? implode(', ', $res['errors']) : 'Gagal memperbarui aset secara massal')
    ]);
}
exit;
