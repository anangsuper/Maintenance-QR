<?php
/**
 * HALAMAN DETAIL KARTU INVENTARIS RESMI (PASPOR ASET & BARANG)
 * 
 * Halaman tujuan ketika QR Code pada stiker kartu fisik di-scan.
 * Menampilkan rincian lengkap barang:
 * - Kantor Bank & Sub Kantor
 * - Nomor Rekening & Nomor Seri Inventaris
 * - Jenis Inventaris (1 s/d 7)
 * - Ruangan (Dinamis / Bisa Ditambahkan)
 * - Golongan (01 s/d 05)
 * - Sumber Perolehan, Tanggal Perolehan, Status, dan Keterangan
 * - Wadah Foto Barang dengan Kompresi Gambar Cerdas & Otorisasi Wajah
 * 
 * Menggantikan link Canva secara permanen.
 * 
 * Akses: https://192.168.88.163/kartu_detail.php?id=... (atau ?rek=...)
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/inventaris_kartu.php';
require_once __DIR__ . '/includes/users.php';
require_once __DIR__ . '/includes/audit_trail.php';

// Pastikan akses kartu inventaris diarahkan ke domain resmi ber-HTTPS https://it.bankmitra.co.id
enforce_qr_domain('kartu_detail.php');

// Pastikan skema tabel inventaris_kartu & master_ruangan siap
ensure_inventaris_kartu_columns();
ensure_inventaris_kartu_print_columns();
csrf_token();

$cardId = (int)($_GET['id'] ?? 0);
$rekParam = trim((string)($_GET['rek'] ?? $_GET['no_rek'] ?? ''));
$scanParam = trim((string)($_GET['barcode'] ?? $_GET['canva'] ?? $_GET['url'] ?? $_GET['t'] ?? $_GET['token'] ?? ''));

$card = null;
if ($cardId > 0) {
    $card = get_inventaris_kartu_by_id($cardId);
} elseif ($rekParam !== '') {
    $card = get_inventaris_kartu_by_norek($rekParam);
}

// Coba cari dari barcode / canva link / token hasil scan kartu fisik lama
if (!$card && $scanParam !== '') {
    $card = find_inventaris_kartu_by_scan($scanParam);
}

// Coba cari fleksibel menggunakan rekParam jika format rekening bervariasi
if (!$card && $rekParam !== '') {
    $card = find_inventaris_kartu_by_scan($rekParam);
}

// Fallback jika ID yang dikirim berasal dari tabel assets
if (!$card && ($cardId > 0 || !empty($_GET['asset_id']))) {
    $aid = $cardId > 0 ? $cardId : (int)($_GET['asset_id'] ?? 0);
    $foundAsset = get_asset_by_id($aid);
    if ($foundAsset && !empty($foundAsset['kode_inventaris'])) {
        $card = get_inventaris_kartu_by_norek($foundAsset['kode_inventaris']);
    }
}

// State notifikasi
$uploadSuccess = !empty($_SESSION['flash_kartu_success']) ? (string)$_SESSION['flash_kartu_success'] : null;
unset($_SESSION['flash_kartu_success']);
$uploadError = null;
$dataSuccess = null;
$dataError = null;

// =========================================================================
// -1. HANDLE PULIHKAN (RESTORE) FOTO DARI FOLDER SAMPAH (7 HARI)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore_trash_photo' && $card) {
    verify_csrf();
    $trashId = (int)($_POST['trash_id'] ?? 0);
    $actor = is_logged_in() ? (current_user_name() ?: 'Admin / SDM') : 'Petugas Inventaris';
    $resRestore = restore_photo_from_trash($trashId, $actor);
    if (!empty($resRestore['success'])) {
        $_SESSION['flash_kartu_success'] = "Foto Ke-{$resRestore['restored_slot']} berhasil dipulihkan dari Folder Sampah!";
        header('Location: ' . module_url('kartu_detail.php', ['id' => (int)$card['id']]));
        exit;
    } else {
        $uploadError = $resRestore['error'] ?? 'Gagal memulihkan foto dari Folder Sampah.';
    }
}

// =========================================================================
// 0. HANDLE HAPUS FOTO (SALAH SATU ATAU SEMUA) -> MASUK FOLDER SAMPAH 7 HARI
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_foto' && $card) {
    verify_csrf();

    $faceVerified = (string)($_POST['face_verified'] ?? '0');
    $verifierName = trim((string)($_POST['verifier_name'] ?? ''));
    $verifierUserId = (int)($_POST['verifier_user_id'] ?? 0);
    $verifierConfidence = (int)($_POST['verifier_confidence'] ?? 0);
    $deleteTarget = trim((string)($_POST['delete_target'] ?? 'all')); // '1', '2', atau 'all'
    if (!in_array($deleteTarget, ['1', '2', 'all'], true)) {
        $deleteTarget = 'all';
    }

    $isAuthorized = false;
    $verifierLabel = '';

    if ($faceVerified === '1' && !empty($verifierName)) {
        $isAuthorized = true;
        $verifierLabel = $verifierName . ($verifierConfidence > 0 ? " ({$verifierConfidence}% Cocok - Scan Wajah)" : " (Scan Wajah)");
    } elseif (is_logged_in() && (is_admin() || is_hrd())) {
        $isAuthorized = true;
        $staffRole = is_admin() ? 'Sesi Admin' : 'Sesi SDM & Umum';
        $staffName = current_user_name() ?: (is_admin() ? 'Administrator' : 'Staf SDM & Umum');
        $verifierLabel = $staffName . " ({$staffRole})";
    }

    if (!$isAuthorized) {
        $uploadError = "Penghapusan foto barang ditolak! Wajib diverifikasi melalui pemindaian biometrik wajah staf/teknisi atau otorisasi sesi Admin.";
    } else {
        try {
            $curF1 = trim((string)($card['foto'] ?? ''));
            $curF2 = trim((string)($card['foto_2'] ?? ''));
            $movedCount = 0;

            if (($deleteTarget === '1' || $deleteTarget === 'all') && $curF1 !== '') {
                move_photo_to_trash('inventaris_kartu', (int)$card['id'], 1, $curF1, $verifierLabel, 'Dihapus dari Kartu Inventaris (Foto 1)');
                $curF1 = '';
                $movedCount++;
            }
            if (($deleteTarget === '2' || $deleteTarget === 'all') && $curF2 !== '') {
                move_photo_to_trash('inventaris_kartu', (int)$card['id'], 2, $curF2, $verifierLabel, 'Dihapus dari Kartu Inventaris (Foto 2)');
                $curF2 = '';
                $movedCount++;
            }

            if ($curF1 === '' && $curF2 === '') {
                db()->prepare("UPDATE inventaris_kartu SET foto = NULL, foto_2 = NULL, foto_updated_by = NULL, foto_updated_at = NULL WHERE id = ?")
                    ->execute([(int)$card['id']]);
            } else {
                db()->prepare("UPDATE inventaris_kartu SET foto = ?, foto_2 = ?, foto_updated_by = ?, foto_updated_at = ? WHERE id = ?")
                    ->execute([$curF1 ?: null, $curF2 ?: null, $verifierLabel, date('Y-m-d H:i:s'), (int)$card['id']]);
            }

            $targetMsg = ($deleteTarget === '1') ? 'Foto Ke-1' : (($deleteTarget === '2') ? 'Foto Ke-2' : 'Semua Foto');
            $_SESSION['flash_kartu_success'] = "✓ {$targetMsg} berhasil dipindahkan ke Folder Sampah (tersimpan 7 hari & bisa di-restore) oleh: {$verifierLabel}.";
            header('Location: ' . module_url('kartu_detail.php', ['id' => (int)$card['id']]));
            exit;
        } catch (Throwable $e) {
            $uploadError = 'Gagal menghapus foto: ' . $e->getMessage();
        }
    }
}

// =========================================================================
// 1. HANDLE UPLOAD / EDIT 1 ATAU 2 FOTO SEKALIGUS & HAPUS SALAH SATU/SEMUA
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_foto' && $card) {
    verify_csrf();

    $faceVerified = (string)($_POST['face_verified'] ?? '0');
    $verifierName = trim((string)($_POST['verifier_name'] ?? ''));
    $verifierUserId = (int)($_POST['verifier_user_id'] ?? 0);
    $verifierConfidence = (int)($_POST['verifier_confidence'] ?? 0);
    $verifierPhotoSnap = trim((string)($_POST['verifier_photo'] ?? ''));

    $isAuthorized = false;
    $verifierLabel = '';

    if ($faceVerified === '1' && !empty($verifierName)) {
        $isAuthorized = true;
        $verifierLabel = $verifierName . ($verifierConfidence > 0 ? " ({$verifierConfidence}% Cocok - Scan Wajah)" : " (Scan Wajah)");
    } elseif (is_logged_in() && (is_admin() || is_hrd())) {
        $isAuthorized = true;
        $staffRole = is_admin() ? 'Sesi Admin' : 'Sesi SDM & Umum';
        $staffName = current_user_name() ?: (is_admin() ? 'Administrator' : 'Staf SDM & Umum');
        $verifierLabel = $staffName . " ({$staffRole})";
        $verifierName = $staffName;
    }

    if (!$isAuthorized) {
        $uploadError = "Perubahan foto barang ditolak! Wajib diverifikasi melalui pemindaian biometrik wajah staf/teknisi resmi PT BPR Mitratama Arthabuana.";
    } else {
        $uploadDir = __DIR__ . '/uploads/assets/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
            @chmod($uploadDir, 0777);
        }

        $newF1 = trim((string)($card['foto'] ?? ''));
        $newF2 = trim((string)($card['foto_2'] ?? ''));
        $changedParts = [];

        // Cek opsi hapus foto 1 atau foto 2 pada form edit
        if (!empty($_POST['hapus_foto_1']) && $newF1 !== '') {
            move_photo_to_trash('inventaris_kartu', (int)$card['id'], 1, $newF1, $verifierLabel, 'Dihapus saat edit foto (Foto 1)');
            $newF1 = '';
            $changedParts[] = 'Foto 1 dipindah ke Sampah';
        }
        if (!empty($_POST['hapus_foto_2']) && $newF2 !== '') {
            move_photo_to_trash('inventaris_kartu', (int)$card['id'], 2, $newF2, $verifierLabel, 'Dihapus saat edit foto (Foto 2)');
            $newF2 = '';
            $changedParts[] = 'Foto 2 dipindah ke Sampah';
        }

        // Proses upload Foto 1 (mendukung nama input foto_barang_1 atau foto_barang)
        $file1Key = !empty($_FILES['foto_barang_1']['name']) ? 'foto_barang_1' : (!empty($_FILES['foto_barang']['name']) ? 'foto_barang' : '');
        if ($file1Key !== '' && $_FILES[$file1Key]['error'] === UPLOAD_ERR_OK) {
            $f1 = $_FILES[$file1Key];
            if ($f1['size'] <= 25 * 1024 * 1024) {
                $fn1 = 'kartu_' . $card['id'] . '_f1_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.jpg';
                if (compress_and_save_uploaded_image($f1['tmp_name'], $uploadDir . $fn1, 1600, 82, true)) {
                    if ($newF1 !== '') {
                        move_photo_to_trash('inventaris_kartu', (int)$card['id'], 1, $newF1, $verifierLabel, 'Diganti dengan Foto 1 baru');
                    }
                    $newF1 = 'uploads/assets/' . $fn1;
                    $changedParts[] = 'Foto 1 diunggah';
                }
            } else {
                $uploadError = "Ukuran file Foto 1 terlalu besar (maksimal 25MB).";
            }
        }

        // Proses upload Foto 2
        if (empty($uploadError) && !empty($_FILES['foto_barang_2']['name']) && $_FILES['foto_barang_2']['error'] === UPLOAD_ERR_OK) {
            $f2 = $_FILES['foto_barang_2'];
            if ($f2['size'] <= 25 * 1024 * 1024) {
                $fn2 = 'kartu_' . $card['id'] . '_f2_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.jpg';
                if (compress_and_save_uploaded_image($f2['tmp_name'], $uploadDir . $fn2, 1600, 82, true)) {
                    if ($newF2 !== '') {
                        move_photo_to_trash('inventaris_kartu', (int)$card['id'], 2, $newF2, $verifierLabel, 'Diganti dengan Foto 2 baru');
                    }
                    $newF2 = 'uploads/assets/' . $fn2;
                    $changedParts[] = 'Foto 2 diunggah';
                }
            } else {
                $uploadError = "Ukuran file Foto 2 terlalu besar (maksimal 25MB).";
            }
        }

        if (empty($uploadError)) {
            if (!empty($changedParts)) {
                $nowStr = date('Y-m-d H:i:s');
                if ($newF1 === '' && $newF2 === '') {
                    db()->prepare("UPDATE inventaris_kartu SET foto = NULL, foto_2 = NULL, foto_updated_by = NULL, foto_updated_at = NULL WHERE id = ?")
                        ->execute([(int)$card['id']]);
                    $card['foto'] = null;
                    $card['foto_2'] = null;
                    $card['foto_updated_by'] = null;
                    $card['foto_updated_at'] = null;
                } else {
                    db()->prepare("UPDATE inventaris_kartu SET foto = ?, foto_2 = ?, foto_updated_by = ?, foto_updated_at = ? WHERE id = ?")
                        ->execute([$newF1 ?: null, $newF2 ?: null, $verifierLabel, $nowStr, (int)$card['id']]);
                    $card['foto'] = $newF1 ?: null;
                    $card['foto_2'] = $newF2 ?: null;
                    $card['foto_updated_by'] = $verifierLabel;
                    $card['foto_updated_at'] = $nowStr;
                }

                try {
                    record_audit_log(
                        'UPDATE',
                        'INVENTARIS_KARTU',
                        (int)$card['id'],
                        (string)$card['nama_barang'],
                        "Perubahan foto (" . implode(', ', $changedParts) . ") diverifikasi oleh: {$verifierLabel}",
                        ['user_name' => $verifierName, 'user_role' => 'teknisi']
                    );
                } catch (Throwable $e) {}

                $_SESSION['flash_kartu_success'] = "✓ Perubahan foto (" . implode(', ', $changedParts) . ") berhasil disimpan! Diverifikasi oleh: " . $verifierLabel;
                header('Location: ' . module_url('kartu_detail.php', ['id' => (int)$card['id']]));
                exit;
            } else {
                $uploadError = "Silakan pilih minimal 1 file foto baru (Foto 1 / Foto 2) atau centang foto yang ingin dihapus.";
            }
        }
    }
}

// =========================================================================
// 2. HANDLE UPDATE DATA MASTER INVENTARIS (JENIS, GOLONGAN, RUANGAN, NO SERI)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_card_data' && $card) {
    verify_csrf();

    $bioVerified = (string)($_POST['bio_verified'] ?? '0');
    $bioUserName = trim((string)($_POST['bio_user_name'] ?? ''));
    $bioUserId = (int)($_POST['bio_user_id'] ?? 0);
    $bioConfidence = (int)($_POST['bio_confidence'] ?? 0);

    $isAuthorizedEdit = false;
    $verifierLabel = '';

    if ($bioVerified === '1' && !empty($bioUserName)) {
        $isAuthorizedEdit = true;
        $verifierLabel = $bioUserName . ($bioConfidence > 0 ? " ({$bioConfidence}% Cocok - Scan Wajah)" : " (Scan Wajah)");
    } elseif (is_logged_in() && (is_admin() || is_hrd())) {
        $isAuthorizedEdit = true;
        $staffRole = is_admin() ? 'Sesi Admin' : 'Sesi SDM & Umum';
        $staffName = current_user_name() ?: (is_admin() ? 'Administrator' : 'Staf SDM & Umum');
        $verifierLabel = $staffName . " ({$staffRole})";
        $bioUserName = $staffName;
    }

    if (!$isAuthorizedEdit) {
        $dataError = "Perubahan data master ditolak! Wajib diverifikasi melalui pemindaian biometrik wajah staf/teknisi atau sesi resmi Admin/SDM.";
    } else {

        $jenisInv = trim((string)($_POST['jenis_inventaris'] ?? ''));
        $golongan = trim((string)($_POST['golongan'] ?? ''));
        $ruanganSelect = trim((string)($_POST['ruangan_select'] ?? ''));
        $ruanganCustom = trim((string)($_POST['ruangan_custom'] ?? ''));
        $sumberPerolehan = trim((string)($_POST['sumber_perolehan'] ?? ''));
        $statusInv = trim((string)($_POST['status_inventaris'] ?? '1 : Aktif'));
        $nomorSeri = trim((string)($_POST['nomor_seri_inventaris'] ?? ''));
        $keterangan = trim((string)($_POST['keterangan'] ?? ''));

        // Lokasi barang: utamakan custom input, atau select
        $finalRuangan = !empty($ruanganCustom) ? $ruanganCustom : $ruanganSelect;
        if (empty($finalRuangan)) {
            $finalRuangan = 'KPO/Lt-4/SDM';
        }

        $updated = update_inventaris_kartu_data((int)$card['id'], [
            'nomor_seri_inventaris' => $nomorSeri,
            'jenis_inventaris' => $jenisInv,
            'golongan' => $golongan,
            'ruangan' => $finalRuangan,
            'lokasi' => $finalRuangan,
            'sumber_perolehan' => $sumberPerolehan,
            'status_inventaris' => $statusInv,
            'keterangan' => $keterangan,
        ]);

        if ($updated) {
            $card = get_inventaris_kartu_by_id((int)$card['id']);
            $dataSuccess = "Rincian inventaris berhasil disimpan dan diverifikasi oleh: " . e($verifierLabel);
            
            try {
                record_audit_log(
                    'UPDATE',
                    'INVENTARIS_KARTU',
                    (int)$card['id'],
                    (string)$card['nama_barang'],
                    "Data master inventaris diperbarui via Verifikasi Wajah ({$verifierLabel}) - Ruangan: {$finalRuangan}, Jenis: {$jenisInv}, Gol: {$golongan}",
                    ['user_name' => $bioUserName, 'user_role' => 'teknisi']
                );
            } catch (Throwable $e) {}
        } else {
            $dataError = "Gagal memperbarui rincian data kartu inventaris.";
        }
    }
}

// Cek kecocokan data dengan tabel master IT assets
$matchedAsset = null;
if ($card) {
    $cleanRek = preg_replace('/[^0-9]/', '', (string)$card['nomor_rekening']);
    try {
        $stA = db()->prepare("
            SELECT a.*, c.nama_cabang, d.nama_divisi, k.nama_karyawan, kat.nama_kategori
            FROM assets a
            LEFT JOIN cabang c ON c.id = a.id_cabang
            LEFT JOIN divisi d ON d.id = a.id_divisi
            LEFT JOIN karyawan k ON k.id = a.id_karyawan
            LEFT JOIN kategori_aset kat ON kat.id = a.id_kategori
            WHERE a.kode_inventaris = ? 
               OR REPLACE(a.kode_inventaris, '.', '') = ?
               OR a.kode_inventaris LIKE ?
            LIMIT 1
        ");
        $stA->execute([$card['nomor_rekening'], $cleanRek, '%' . $cleanRek . '%']);
        $matchedAsset = $stA->fetch();
    } catch (Throwable $e) {}
}

// Master Pilihan Data Bank
$masterJenis = get_master_jenis_inventaris();
$masterGolongan = get_master_golongan_inventaris();
$masterRuangan = get_master_ruangan_list();
$masterSumber = get_master_sumber_perolehan();
$masterStatus = get_master_status_inventaris();

// Ambil daftar teknisi yang memiliki biometrik wajah terdaftar untuk pencocokan AI
$enrolledTechs = get_enrolled_technicians(false);
if (empty($enrolledTechs)) {
    try {
        $stBio = db()->query("SELECT id, nama, username, role, face_descriptor, face_photo FROM users WHERE face_descriptor IS NOT NULL AND face_descriptor != '' AND status != 'Nonaktif'");
        while ($rBio = $stBio->fetch()) {
            $descArr = json_decode((string)$rBio['face_descriptor'], true);
            if (is_array($descArr) && count($descArr) >= 64) {
                $enrolledTechs[] = [
                    'id' => (int)$rBio['id'],
                    'nama' => (string)$rBio['nama'],
                    'username' => (string)$rBio['username'],
                    'role' => (string)$rBio['role'],
                    'descriptor' => $descArr,
                    'photo' => (string)($rBio['face_photo'] ?? '')
                ];
            }
        }
    } catch (Throwable $e) {}
}

// Resolusi foto final: Mendukung hingga 2 Foto per Kartu Inventaris
$displayPhoto = !empty($card['foto']) ? trim((string)$card['foto']) : null;
$displayPhoto2 = !empty($card['foto_2']) ? trim((string)$card['foto_2']) : null;

$photoUrl = '';
if ($displayPhoto) {
    $photoUrl = str_starts_with($displayPhoto, 'http') ? $displayPhoto : module_url($displayPhoto);
}
$photoUrl2 = '';
if ($displayPhoto2) {
    $photoUrl2 = str_starts_with($displayPhoto2, 'http') ? $displayPhoto2 : module_url($displayPhoto2);
}
$hasAnyPhoto = ($photoUrl !== '' || $photoUrl2 !== '');
$itemTrashPhotos = $card ? get_photo_trash_list(['source_type' => 'inventaris_kartu', 'item_id' => (int)$card['id']]) : [];
$totalTrashCountGlobal = get_photo_trash_count();

// URL QR Code Resmi (Selalu https://it.bankmitra.co.id)
$canonicalQrUrl = $card ? qr_url('kartu_detail.php', ['id' => $card['id']]) : qr_url('kartu_detail.php');

$pageTitle = $card ? e($card['nama_barang']) . ' - Kartu Inventaris' : 'Kartu Inventaris Tidak Ditemukan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> &bull; PT BPR Mitratama Arthabuana</title>
    <!-- Bootstrap 5 CSS -->
    <link href="<?= module_url('assets/vendor/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= module_url('assets/vendor/bootstrap-icons.min.css') ?>">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <!-- QRCode.js -->
    <script src="<?= module_url('assets/vendor/qrcode.min.js') ?>"></script>

    <link rel="stylesheet" href="<?= module_url('assets/css/kartu-detail.css', ['v' => @filemtime(__DIR__ . '/assets/css/kartu-detail.css') ?: '20261029']) ?>">
</head>
<body>

    <!-- Header Brand BPR Mitratama (Rapi & Responsif di Semua Ukuran Layar HP & Desktop) -->
    <header class="header-brand py-2.5 py-md-3 px-2.5 px-md-4 shadow-sm" style="background: #0E5C58 !important; border-bottom: 3px solid #094340;">
        <div class="container d-flex align-items-center justify-content-between gap-2 gap-md-3" style="max-width: 1200px;">
            <div class="d-flex align-items-center gap-2 gap-md-3 min-w-0 flex-grow-1">
                <div class="bg-white px-2 px-md-3 py-1 py-md-1.5 rounded-3 shadow-sm flex-shrink-0 d-flex align-items-center justify-content-center" style="height: 42px; min-width: 52px; max-width: 80px;">
                    <img src="<?= module_url('logo_img.php') ?>" alt="Logo Bank Mitra" style="height: 32px; width: auto; max-width: 100%; object-fit: contain;" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'32\' height=\'32\' viewBox=\'0 0 24 24\' fill=\'%230E5C58\'%3E%3Cpath d=\'M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5\'/%3E%3C/svg%3E';">
                </div>
                <div class="min-w-0 pe-1">
                    <h1 class="h6 h5-md mb-0 text-white fw-bold tracking-tight text-truncate" style="font-size: clamp(0.82rem, 3.2vw, 1.25rem); line-height: 1.2;">
                        PT. BPR MITRATAMA ARTHABUANA
                    </h1>
                    <div class="text-teal-light text-truncate fw-semibold mt-0.5" style="font-size: clamp(0.66rem, 2.2vw, 0.85rem); letter-spacing: 0.3px;">DATA MASTER KARTU INVENTARIS RESMI</div>
                </div>
            </div>
            <div class="flex-shrink-0">
                <?php if (is_logged_in()): ?>
                    <a href="<?= module_url('dashboard.php', ['tab' => 'kartu']) ?>" class="btn btn-outline-light rounded-pill px-2.5 px-sm-3.5 py-1.5 d-inline-flex align-items-center gap-1.5 fw-bold shadow-xs" style="font-size: 0.80rem; border-color: rgba(255,255,255,0.6); white-space: nowrap;">
                        <i class="bi bi-arrow-left"></i> <span class="d-none d-sm-inline">Dashboard</span><span class="d-inline d-sm-none">Menu</span>
                    </a>
                <?php else: ?>
                    <a href="<?= module_url('login.php') ?>" class="btn btn-outline-light rounded-pill px-2.5 px-sm-3.5 py-1.5 d-inline-flex align-items-center gap-1.5 fw-bold shadow-xs" style="font-size: 0.80rem; border-color: rgba(255,255,255,0.6); white-space: nowrap;">
                        <i class="bi bi-box-arrow-in-right"></i> <span>Login</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="container py-4" style="max-width: 1200px;">

        <!-- Alert Notifikasi -->
        <?php if ($uploadSuccess): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-xs mb-3 border-success" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5 align-middle"></i>
                <strong>Sukses:</strong> <?= e($uploadSuccess) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($uploadError): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-xs mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5 align-middle"></i>
                <strong>Perhatian:</strong> <?= e($uploadError) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($dataSuccess): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-xs mb-3 border-success" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5 align-middle"></i>
                <strong>Tersimpan:</strong> <?= e($dataSuccess) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($dataError): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-xs mb-3" role="alert">
                <i class="bi bi-exclamation-circle-fill me-2 fs-5 align-middle"></i>
                <?= e($dataError) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!$card): ?>
            <!-- Tampilan Tidak Ditemukan -->
            <div class="card card-custom p-5 text-center">
                <div class="display-1 text-muted mb-3"><i class="bi bi-search"></i></div>
                <h2 class="h4 fw-bold text-danger mb-2">Kartu Inventaris Tidak Ditemukan</h2>
                <p class="text-secondary mb-4">
                    Nomor rekening atau ID kartu yang Anda scan tidak terdaftar pada sistem database inventaris BPR Mitratama.
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="<?= module_url('dashboard.php', ['tab' => 'kartu']) ?>" class="btn btn-primary rounded-pill px-4">
                        <i class="bi bi-grid-fill me-1"></i> Buka Daftar Kartu
                    </a>
                </div>
            </div>
        <?php else: ?>

            <?php
                $rek = (string)$card['nomor_rekening'];
                $kodeInventaris = format_kode_inventaris($rek);
                $tglFormatted = format_indo_date($card['tanggal_perolehan']);
                $nomorGabungan = get_nomor_asset_gabungan($kodeInventaris, $card['tanggal_perolehan']);
                $cBranch = get_cabang_from_nomor_rekening($kodeInventaris);
                $kdCabang = $cBranch ? $cBranch['code'] : '01';
                if ($kdCabang === '01') {
                    $namaCabang = 'KANTOR PUSAT OPERASIONAL (KPO)';
                } else {
                    $namaCabang = 'KANTOR CABANG ' . strtoupper($cBranch['name'] ?? '');
                }
                
                // Nilai-nilai master inventaris
                $nomorSeriInv = !empty($card['nomor_seri_inventaris']) ? $card['nomor_seri_inventaris'] : generate_default_nomor_seri($card);
                $jenisInvVal = !empty($card['jenis_inventaris']) ? $card['jenis_inventaris'] : '3 : PERALATAN KANTOR';
                $golonganVal = !empty($card['golongan']) ? $card['golongan'] : '02 : GOLONGAN I';
                
                // Ruangan / Lokasi Fisik Barang
                $ruanganVal = '';
                if (!empty($card['ruangan'])) {
                    $ruanganVal = trim($card['ruangan']);
                } elseif (!empty($card['lokasi']) && $card['lokasi'] !== 'Kantor Pusat (KPO)' && $card['lokasi'] !== 'KPO / Operasional') {
                    $ruanganVal = trim($card['lokasi']);
                } elseif (!empty($matchedAsset['spesifikasi']) && preg_match('/Lokasi:\s*([^\|]+)/i', $matchedAsset['spesifikasi'], $mLok)) {
                    $ruanganVal = trim($mLok[1]);
                } else {
                    $cabPrefix = ($kdCabang === '01') ? 'KPO' : 'KC-' . ($cBranch ? strtoupper(substr($cBranch['name'], 0, 3)) : 'CAB');
                    $ruanganVal = "{$cabPrefix}/Lt-4/SDM";
                }
                $lokasiBarangVal = $ruanganVal;
                $sumberVal = !empty($card['sumber_perolehan']) ? $card['sumber_perolehan'] : '02 : MODAL DISETOR';
                $statusVal = !empty($card['status_inventaris']) ? $card['status_inventaris'] : '1 : Aktif';
                $cleanStatus = preg_replace('/^\d+\s*:\s*/', '', $statusVal);
                $keteranganVal = !empty($card['keterangan']) ? $card['keterangan'] : $card['nama_barang'];

                $tglCardPhysical = format_card_date((string)($card['tanggal_perolehan'] ?? ''));
                $isPrintedDetail = !empty($card['printed_at']);
                $printedTimeDetail = $isPrintedDetail ? date('d/m/Y H:i', strtotime((string)$card['printed_at'])) : '';
                $cardPhysicalPayload = [
                    'id'          => (int)$card['id'],
                    'kode'        => $kodeInventaris,
                    'nomor_asset' => $nomorGabungan,
                    'nama'        => (string)$card['nama_barang'],
                    'tgl'         => $tglCardPhysical,
                    'lokasi'      => $lokasiBarangVal,
                    'qr_url'      => $canonicalQrUrl
                ];
            ?>

            <!-- Kartu Paspor Inventaris Resmi -->
            <div class="card card-custom mb-4">
                <!-- TAMPILAN KHUSUS SMARTPHONE / MOBILE (BERSIH, LEGA & TANPA DUPLIKASI FOTO KECIL) -->
                <div class="d-md-none p-3 border-bottom bg-white">
                    <div class="mb-2">
                        <div class="d-flex align-items-center justify-content-between mb-1.5 flex-wrap gap-1">
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1 d-inline-flex align-items-center gap-1.5 fw-bold" style="font-size: 0.72rem;"><span class="status-pulse-dot"></span><span><?= e($cleanStatus) ?></span></span>
                            <?php if ($isPrintedDetail): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.68rem;" title="Dicetak pada <?= e($printedTimeDetail) ?>">
                                    <i class="bi bi-check-circle-fill me-0.5"></i>Sudah Dicetak
                                </span>
                            <?php endif; ?>
                        </div>
                        <h2 class="h6 fw-bold text-dark mb-1 lh-sm" style="font-size: 1.05rem;"><?= e($card['nama_barang']) ?></h2>
                        <div class="font-mono text-primary fw-bold small mb-1" style="font-size: 0.82rem;"><?= e($kodeInventaris) ?> <span class="text-muted fw-normal">(#<?= (int)$card['id'] ?>)</span></div>
                        <div class="small text-muted mb-0.5" style="font-size: 0.76rem;">
                            <i class="bi bi-geo-alt-fill text-warning me-1"></i><strong><?= e($lokasiBarangVal) ?></strong>
                        </div>
                    </div>

                    <!-- Strip Detail Ringkas: Golongan, Seri, Tanggal -->
                    <div class="p-2 bg-light rounded-3 border mb-2 d-flex flex-wrap justify-content-between gap-1" style="font-size: 0.72rem;">
                        <div><span class="text-muted">No. Seri:</span> <span class="font-mono text-danger fw-bold"><?= e($nomorSeriInv) ?></span></div>
                        <div><span class="text-muted">Tanggal:</span> <strong class="text-dark"><?= e($tglFormatted) ?></strong></div>
                    </div>

                    <!-- Tombol Aksi Cepat Mobile (4 Kotak Simetris Rapi) -->
                    <div class="row g-2 pt-1">
                        <div class="col-3">
                            <button class="mobile-action-btn" type="button" data-bs-toggle="collapse" data-bs-target="#collapseUploadPhoto" aria-expanded="false" title="Unggah / Ambil Foto">
                                <i class="bi bi-camera-fill text-[#0E5C58]"></i>
                                <span><?= $photoUrl ? 'Ganti' : 'Foto' ?></span>
                            </button>
                        </div>
                        <div class="col-3">
                            <a href="<?= module_url('inventaris_mutation.php', ['id' => $card['id']]) ?>" class="mobile-action-btn" title="Mutasi Lokasi Barang">
                                <i class="bi bi-arrow-left-right text-[#0284C7]"></i>
                                <span>Mutasi</span>
                            </a>
                        </div>
                        <div class="col-3">
                            <button type="button" class="mobile-action-btn" data-bs-toggle="modal" data-bs-target="#modalEditDataKartu" title="Ubah Data Inventaris">
                                <i class="bi bi-pencil-square text-[#D97706]"></i>
                                <span>Edit</span>
                            </button>
                        </div>
                        <div class="col-3">
                            <a href="<?= module_url('print_inventory_card.php', ['source' => 'inventaris_kartu', 'id' => $card['id']]) ?>" target="_blank" class="mobile-action-btn" title="Cetak Kartu Fisik">
                                <i class="bi bi-printer-fill text-[#16A34A]"></i>
                                <span>Cetak</span>
                            </a>
                        </div>
                    </div>

                    <!-- Tombol Unduh Gambar Kartu (PNG Ukuran Asli CR80) & Bagikan WA -->
                    <div class="row g-2 mt-1">
                        <div class="col-7">
                            <button type="button" class="btn w-100 rounded-3 fw-bold text-white d-flex align-items-center justify-content-center gap-1.5 py-2 shadow-xs" style="background-color: #0E5C58; font-size: 0.80rem;" onclick="downloadOrSharePhysicalCard(window.CURRENT_DETAIL_CARD, 'download', this)">
                                <i class="bi bi-download"></i>
                                <span>Unduh Kartu (PNG)</span>
                            </button>
                        </div>
                        <div class="col-5">
                            <button type="button" class="btn btn-success w-100 rounded-3 fw-bold d-flex align-items-center justify-content-center gap-1.5 py-2 shadow-xs" style="font-size: 0.80rem;" onclick="downloadOrSharePhysicalCard(window.CURRENT_DETAIL_CARD, 'share', this)">
                                <i class="bi bi-whatsapp"></i>
                                <span>Kirim WA</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Desktop Header Bar -->
                <div class="p-3.5 p-md-4 border-bottom bg-light bg-opacity-75 d-none d-md-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-2.5 flex-wrap">
                        <span class="badge badge-gold px-3.5 py-2 rounded-pill text-uppercase shadow-2xs" style="font-size: 0.88rem;">
                            <i class="bi bi-shield-check me-1.5 fs-6"></i> Data Master Inventaris
                        </span>
                        <span class="badge bg-primary bg-opacity-10 text-primary px-3.5 py-2 rounded-pill font-mono shadow-2xs" style="font-size: 0.88rem;">
                            ID: #<?= (int)$card['id'] ?>
                        </span>
                        <?php if ($isPrintedDetail): ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill fw-bold shadow-2xs" style="font-size: 0.84rem;" title="Dicetak pada <?= e($printedTimeDetail) ?>">
                                <i class="bi bi-check-circle-fill me-1"></i> Sudah Dicetak (<?= e($printedTimeDetail) ?>)
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-neutral rounded-pill px-3.5 py-2 fw-bold" style="font-size: 0.86rem; border-color: #0E5C58; color: #0E5C58;" onclick="downloadOrSharePhysicalCard(window.CURRENT_DETAIL_CARD, 'download', this)" title="Unduh Gambar Kartu Fisik Ukuran Asli CR80 (85.6mm x 54.0mm - 300 DPI)">
                            <i class="bi bi-download me-1"></i> Unduh Kartu (PNG)
                        </button>
                        <button type="button" class="btn btn-success rounded-pill px-3.5 py-2 fw-bold shadow-2xs" style="font-size: 0.86rem;" onclick="downloadOrSharePhysicalCard(window.CURRENT_DETAIL_CARD, 'share', this)" title="Bagikan / Kirim Gambar Kartu ke WhatsApp">
                            <i class="bi bi-whatsapp me-1"></i> Kirim WA
                        </button>
                        <a href="<?= module_url('inventaris_mutation.php', ['id' => $card['id']]) ?>" class="btn btn-outline-neutral rounded-pill px-3.5 py-2 fw-semibold" style="font-size: 0.88rem;">
                            <i class="bi bi-arrow-left-right me-1.5"></i> Mutasi Barang
                        </a>
                        <button type="button" class="btn btn-outline-neutral rounded-pill px-3.5 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalEditDataKartu" style="font-size: 0.88rem;">
                            <i class="bi bi-pencil-square me-1.5"></i> Edit Data
                        </button>
                        <a href="<?= module_url('print_inventory_card.php', ['source' => 'inventaris_kartu', 'id' => $card['id']]) ?>" target="_blank" class="btn btn-navy-primary rounded-pill px-4 py-2 fw-bold shadow-xs" style="font-size: 0.88rem;">
                            <i class="bi bi-printer-fill text-warning me-1.5"></i> Cetak CR80
                        </a>
                    </div>
                </div>

                <div class="p-3 p-md-4">
                    <div class="row g-4 align-items-start">
                        
                        <!-- Kolom Foto Barang (2 Slot Foto) & Biometric Action (Kiri: 5 Kolom) -->
                        <div class="col-lg-5 col-md-5 text-center">
                            <!-- Galeri Foto Barang (1 Tampilan Default, Menjadi 2 Tampilan Hanya Bila Ada 2 Foto) -->
                            <?php 
                                $hasFoto1 = !empty($photoUrl);
                                $hasFoto2 = !empty($photoUrl2);
                                $hasBothPhotos = ($hasFoto1 && $hasFoto2);
                            ?>
                            <div class="row g-2 mb-2" id="cardGalleryRow">
                                <!-- SLOT FOTO 1 (UTAMA) -->
                                <div class="<?= $hasBothPhotos ? 'col-6' : ($hasFoto1 || !$hasFoto2 ? 'col-12' : 'col-12 d-none') ?>" id="slotCol1">
                                    <div class="item-photo-wrapper mb-0 shadow-xs d-flex position-relative <?= $photoUrl ? 'has-image' : '' ?>" id="photoWrapper" style="min-height: 185px; max-height: 250px; aspect-ratio: 4 / 5;">
                                        <span class="position-absolute top-0 start-0 m-2 badge bg-dark bg-opacity-75 text-white rounded-pill px-2 py-1" style="font-size: 0.65rem; z-index: 3;">
                                            <i class="bi bi-1-circle-fill me-0.5"></i> Foto 1 (Utama)
                                        </span>
                                        <img id="previewImg" src="<?= $photoUrl ? e($photoUrl) : '' ?>" alt="Foto 1 <?= e($card['nama_barang']) ?>" fetchpriority="high" class="<?= $photoUrl ? '' : 'd-none' ?>" onclick="viewFullImage(this.src, '<?= e(addslashes($card['nama_barang'] . ' - Foto 1 (Utama)')) ?>')" title="Klik untuk memperbesar Foto 1">
                                        
                                        <div id="emptyPhotoPlaceholder" class="text-center p-3 <?= $photoUrl ? 'd-none' : '' ?>" style="cursor: pointer;" onclick="openPhotoCollapseAndFocus(1)">
                                            <i class="bi bi-camera text-muted fs-2 mb-1 d-block"></i>
                                            <div class="small text-secondary fw-semibold" style="font-size: 0.74rem;">Belum Ada Foto Barang</div>
                                            <div class="text-primary fw-semibold mt-1" style="font-size: 0.68rem;"><i class="bi bi-plus-circle me-0.5"></i>Ambil / Upload Foto</div>
                                        </div>

                                        <div id="previewBadge" class="preview-alert-badge d-none">
                                            <i class="bi bi-eye me-1"></i> Pratinjau Foto 1
                                        </div>

                                        <?php if ($photoUrl): ?>
                                            <button type="button" class="btn btn-danger btn-xs position-absolute top-0 end-0 m-2 rounded-circle shadow-sm" style="width: 28px; height: 28px; padding: 0; display: inline-flex; align-items: center; justify-content: center; z-index: 3;" onclick="event.stopPropagation(); openDeletePhotoConfirmModal(event, '1');" title="Hapus Foto 1 Saja (Masuk Folder Sampah 7 Hari)">
                                                <i class="bi bi-trash3-fill" style="font-size: 0.75rem;"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- SLOT FOTO 2 (DETAIL) - Hanya Tampil Jika Ada Foto 2 -->
                                <div class="<?= $hasBothPhotos ? 'col-6' : ($hasFoto2 ? 'col-12' : 'col-6 d-none') ?>" id="slotCol2">
                                    <div class="item-photo-wrapper mb-0 shadow-xs d-flex position-relative <?= $photoUrl2 ? 'has-image' : '' ?>" id="photoWrapper2" style="min-height: 185px; max-height: 250px; aspect-ratio: 4 / 5;">
                                        <span class="position-absolute top-0 start-0 m-2 badge bg-primary text-white rounded-pill px-2 py-1" style="font-size: 0.65rem; z-index: 3;">
                                            <i class="bi bi-2-circle-fill me-0.5"></i> Foto 2 (Detail)
                                        </span>
                                        <img id="previewImg2" src="<?= $photoUrl2 ? e($photoUrl2) : '' ?>" alt="Foto 2 <?= e($card['nama_barang']) ?>" fetchpriority="low" loading="lazy" class="<?= $photoUrl2 ? '' : 'd-none' ?>" onclick="viewFullImage(this.src, '<?= e(addslashes($card['nama_barang'] . ' - Foto 2 (Detail)')) ?>')" title="Klik untuk memperbesar Foto 2">
                                        
                                        <div id="emptyPhotoPlaceholder2" class="text-center p-3 <?= $photoUrl2 ? 'd-none' : '' ?>" style="cursor: pointer;" onclick="openPhotoCollapseAndFocus(2)">
                                            <i class="bi bi-plus-circle-dotted text-primary fs-2 mb-1 d-block"></i>
                                            <div class="small text-secondary fw-semibold" style="font-size: 0.74rem;">Foto Ke-2 Siap</div>
                                            <div class="text-primary fw-bold mt-1" style="font-size: 0.68rem;">+ Upload Foto 2</div>
                                        </div>

                                        <div id="previewBadge2" class="preview-alert-badge d-none">
                                            <i class="bi bi-eye me-1"></i> Pratinjau Foto 2
                                        </div>

                                        <?php if ($photoUrl2): ?>
                                            <button type="button" class="btn btn-danger btn-xs position-absolute top-0 end-0 m-2 rounded-circle shadow-sm" style="width: 28px; height: 28px; padding: 0; display: inline-flex; align-items: center; justify-content: center; z-index: 3;" onclick="event.stopPropagation(); openDeletePhotoConfirmModal(event, '2');" title="Hapus Foto 2 Saja (Masuk Folder Sampah 7 Hari)">
                                                <i class="bi bi-trash3-fill" style="font-size: 0.75rem;"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Info Badge Verifikasi Wajah -->
                            <?php if ($hasAnyPhoto): ?>
                                <?php 
                                    $rawVerifier = (string)($card['foto_updated_by'] ?? '');
                                    $isFaceScan = str_contains($rawVerifier, 'Scan Wajah');
                                    $verifierCleanName = preg_replace('/\s*\(.*?\)/', '', $rawVerifier) ?: 'Petugas IT';
                                    $verifierTime = !empty($card['foto_updated_at']) ? date('d/m/Y H:i', strtotime($card['foto_updated_at'])) : '-';
                                    $activePhotoCount = ($photoUrl !== '' ? 1 : 0) + ($photoUrl2 !== '' ? 1 : 0);
                                ?>
                                <div class="bg-success bg-opacity-10 border border-success border-opacity-25 rounded-3 p-2.5 mb-2 text-start">
                                    <div class="d-flex align-items-center justify-content-between gap-1.5 text-success fw-bold" style="font-size: 0.78rem;">
                                        <span><i class="bi bi-patch-check-fill fs-6 text-success me-1"></i>Foto Terverifikasi <?= $isFaceScan ? '(Scan Wajah)' : '(Sesi Resmi)' ?></span>
                                        <span class="badge bg-success text-white rounded-pill" style="font-size: 0.66rem;"><?= $activePhotoCount ?>/2 Foto Aktif</span>
                                    </div>
                                    <div class="text-secondary small mt-0.5" style="font-size: 0.70rem;">
                                        Oleh <strong class="text-dark"><?= e($verifierCleanName) ?></strong> &bull; <?= e($verifierTime) ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Banner Foto di Folder Sampah (Khusus User yang Sedang Login - Tidak Ditampilkan ke Tamu/Publik) -->
                            <?php if (is_logged_in() && !empty($itemTrashPhotos)): ?>
                                <div class="bg-warning bg-opacity-10 border border-warning border-opacity-50 rounded-3 p-2.5 mb-2 text-start">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="fw-bold text-dark" style="font-size: 0.75rem;">
                                            <i class="bi bi-recycle text-success me-1"></i><?= count($itemTrashPhotos) ?> Foto Barang Ini di Folder Sampah
                                        </span>
                                        <a href="<?= module_url('photo_trash.php', ['source_type' => 'inventaris_kartu', 'item_id' => (int)$card['id']]) ?>" class="text-primary fw-bold text-decoration-none" style="font-size: 0.70rem;">
                                            Lihat Detail &rarr;
                                        </a>
                                    </div>
                                    <div class="d-flex flex-column gap-1.5 mt-1">
                                        <?php foreach (array_slice($itemTrashPhotos, 0, 2) as $tp): 
                                            $tpUrl = str_starts_with($tp['file_path'], 'http') ? $tp['file_path'] : module_url($tp['file_path']);
                                        ?>
                                            <div class="d-flex align-items-center justify-content-between bg-white rounded-2 p-1.5 border gap-2">
                                                <div class="d-flex align-items-center gap-2 min-w-0">
                                                    <img src="<?= e($tpUrl) ?>" alt="Trash" fetchpriority="low" loading="lazy" class="rounded border object-fit-cover flex-shrink-0" style="width: 34px; height: 34px; cursor: pointer;" onclick="viewFullImage('<?= e(addslashes($tpUrl)) ?>', 'Foto di Folder Sampah')">
                                                    <div class="min-w-0">
                                                        <div class="fw-bold text-dark text-truncate" style="font-size: 0.70rem;">Foto Ke-<?= (int)$tp['photo_slot'] ?> &bull; <span class="text-danger"><?= e($tp['countdown_label']) ?></span></div>
                                                        <div class="text-muted text-truncate" style="font-size: 0.64rem;">Dihapus: <?= date('d/m H:i', strtotime($tp['deleted_at'])) ?></div>
                                                    </div>
                                                </div>
                                                <form method="post" class="m-0 flex-shrink-0">
                                                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="restore_trash_photo">
                                                    <input type="hidden" name="trash_id" value="<?= (int)$tp['id'] ?>">
                                                    <button type="submit" class="btn btn-xs btn-success rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.68rem;">
                                                        <i class="bi bi-arrow-counterclockwise me-0.5"></i>Restore
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Tombol Aksi Foto (Kelola 2 Foto, Hapus Salah Satu/Semua, & Folder Sampah) -->
                            <div class="d-flex flex-wrap align-items-center justify-content-center gap-1.5 mb-2">
                                <button class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1.5 fw-semibold shadow-2xs" type="button" data-bs-toggle="collapse" data-bs-target="#collapseUploadPhoto" aria-expanded="<?= !$hasAnyPhoto ? 'true' : 'false' ?>" id="btnToggleUpload" style="font-size: 0.76rem;">
                                    <i class="bi bi-camera me-1"></i> <?= $hasAnyPhoto ? 'Edit / Tambah Foto (Maks 2)' : 'Upload 1 - 2 Foto' ?>
                                </button>
                                <?php if ($hasAnyPhoto): ?>
                                    <form method="post" id="deletePhotoForm" class="d-inline">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_foto">
                                        <input type="hidden" name="delete_target" id="delPhotoTargetInput" value="all">
                                        <input type="hidden" name="face_verified" id="delBioVerified" value="0">
                                        <input type="hidden" name="verifier_user_id" id="delBioUserId" value="">
                                        <input type="hidden" name="verifier_name" id="delBioUserName" value="">
                                        <input type="hidden" name="verifier_confidence" id="delBioConfidence" value="">
                                        <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-2.5 py-1.5 fw-semibold shadow-2xs" onclick="openDeletePhotoConfirmModal(event, 'all')" style="font-size: 0.76rem;">
                                            <i class="bi bi-trash3 me-1"></i> Hapus Foto
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if (is_logged_in()): ?>
                                    <a href="<?= module_url('photo_trash.php', ['source_type' => 'inventaris_kartu']) ?>" class="btn btn-light border btn-sm rounded-pill px-2.5 py-1.5 fw-semibold text-secondary shadow-2xs" style="font-size: 0.74rem;" title="Buka Folder Sampah Foto (Tersimpan 7 Hari)">
                                        <i class="bi bi-recycle text-success me-1"></i> Sampah Foto <?= $totalTrashCountGlobal > 0 ? '<span class="badge bg-danger text-white rounded-pill ms-0.5">' . $totalTrashCountGlobal . '</span>' : '' ?>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <!-- Form Upload & Edit 2 Foto Barang (Collapsible) -->
                            <div class="collapse <?= !$hasAnyPhoto ? 'show' : '' ?> text-start" id="collapseUploadPhoto">
                                <div class="bg-light p-3 rounded-3 border shadow-xs mb-3">
                                    <form method="post" enctype="multipart/form-data" id="uploadPhotoForm">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="upload_foto">
                                        
                                        <!-- Hidden Inputs untuk Biometrik Wajah -->
                                        <input type="hidden" name="face_verified" id="bioVerified" value="0">
                                        <input type="hidden" name="verifier_user_id" id="bioUserId" value="">
                                        <input type="hidden" name="verifier_name" id="bioUserName" value="">
                                        <input type="hidden" name="verifier_confidence" id="bioConfidence" value="">
                                        <input type="hidden" name="verifier_photo" id="bioPhotoSnap" value="">

                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label small fw-bold text-dark mb-0">
                                                <i class="bi bi-images me-1 text-primary"></i> 1. Kelola / Upload Foto (Bisa 2 Foto):
                                            </label>
                                            <span id="compressBadge" class="badge bg-primary bg-opacity-10 text-primary d-none" style="font-size: 0.68rem;">
                                                <i class="bi bi-shield-check me-1"></i> Kompres Cerdas
                                            </span>
                                        </div>

                                        <div class="row g-2 mb-2" id="rowDropzones">
                                            <!-- Input Foto 1 -->
                                            <div class="<?= $hasFoto2 ? 'col-6' : 'col-12' ?>" id="colDropzone1">
                                                <div class="photo-dropzone p-2.5 text-center h-100 d-flex flex-column justify-content-center" id="dropzoneBox" onclick="document.getElementById('fotoBarangInput').click()" style="cursor: pointer; min-height: 105px;">
                                                    <input type="file" id="fotoBarangInput" name="foto_barang_1" class="d-none" accept="image/*" capture="environment">
                                                    <div class="badge bg-dark text-white align-self-center mb-1" style="font-size: 0.62rem;">FOTO 1 (UTAMA)</div>
                                                    <div id="dropzoneEmpty">
                                                        <i class="bi bi-camera fs-5 text-primary d-block"></i>
                                                        <div class="fw-bold text-dark" style="font-size: 0.72rem;"><?= $photoUrl ? 'Ganti Foto 1' : 'Pilih Foto 1' ?></div>
                                                        <div class="text-muted" style="font-size: 0.62rem;">Tampak Depan</div>
                                                    </div>
                                                    <div id="dropzoneSelected" class="d-none">
                                                        <div class="fw-bold text-success" style="font-size: 0.70rem;">
                                                            <i class="bi bi-check-circle-fill me-0.5"></i> <span id="selectedFileName">Foto 1 Siap</span>
                                                        </div>
                                                        <span class="badge bg-white text-secondary border px-1.5 py-0.5 mt-0.5" id="selectedFileSize" style="font-size: 0.62rem;"></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Input Foto 2 (Hanya Tampil Jika Foto 2 Ada atau Tombol Tambah Ditekan) -->
                                            <div class="<?= $hasFoto2 ? 'col-6' : 'col-6 d-none' ?>" id="colDropzone2">
                                                <div class="photo-dropzone p-2.5 text-center h-100 d-flex flex-column justify-content-center position-relative" id="dropzoneBox2" onclick="document.getElementById('fotoBarangInput2').click()" style="cursor: pointer; min-height: 105px;">
                                                    <button type="button" class="btn btn-xs btn-outline-danger position-absolute top-0 end-0 m-1.5 py-0 px-1 rounded" style="font-size: 0.62rem; z-index: 5;" onclick="event.stopPropagation(); tutupSlotFoto2()" title="Batal tambah foto 2">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                    <input type="file" id="fotoBarangInput2" name="foto_barang_2" class="d-none" accept="image/*" capture="environment">
                                                    <div class="badge bg-primary text-white align-self-center mb-1" style="font-size: 0.62rem;">FOTO 2 (DETAIL)</div>
                                                    <div id="dropzoneEmpty2">
                                                        <i class="bi bi-plus-circle-dotted fs-5 text-primary d-block"></i>
                                                        <div class="fw-bold text-dark" style="font-size: 0.72rem;"><?= $photoUrl2 ? 'Ganti Foto 2' : 'Pilih Foto 2' ?></div>
                                                        <div class="text-muted" style="font-size: 0.62rem;">S/N / Detail</div>
                                                    </div>
                                                    <div id="dropzoneSelected2" class="d-none">
                                                        <div class="fw-bold text-success" style="font-size: 0.70rem;">
                                                            <i class="bi bi-check-circle-fill me-0.5"></i> <span id="selectedFileName2">Foto 2 Siap</span>
                                                        </div>
                                                        <span class="badge bg-white text-secondary border px-1.5 py-0.5 mt-0.5" id="selectedFileSize2" style="font-size: 0.62rem;"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Tombol Tambah Slot Foto 2 (Jika Belum Mau Ditambah, Tetap 1 Tampilan) -->
                                        <div id="boxBtnTambahFoto2" class="<?= $hasFoto2 ? 'd-none' : 'text-center mb-2' ?>">
                                            <button type="button" class="btn btn-xs btn-outline-primary fw-semibold px-3 py-1 rounded-pill shadow-2xs" onclick="bukaSlotFoto2()" style="font-size: 0.74rem;">
                                                <i class="bi bi-plus-circle me-1"></i> + Tambah Foto Ke-2 (Detail / S/N)
                                            </button>
                                        </div>

                                        <!-- Opsi Hapus Salah Satu atau Semua Foto saat Edit -->
                                        <?php if ($photoUrl || $photoUrl2): ?>
                                            <div class="bg-white rounded-3 p-2 border mb-2.5">
                                                <div class="text-secondary fw-bold mb-1" style="font-size: 0.68rem;">
                                                    <i class="bi bi-sliders me-1"></i>Opsi Hapus Saat Edit (Masuk Folder Sampah 7 Hari):
                                                </div>
                                                <div class="d-flex flex-wrap gap-3">
                                                    <?php if ($photoUrl): ?>
                                                        <div class="form-check mb-0">
                                                            <input class="form-check-input" type="checkbox" name="hapus_foto_1" value="1" id="chkHapusFoto1">
                                                            <label class="form-check-label text-danger fw-semibold" for="chkHapusFoto1" style="font-size: 0.72rem;">
                                                                Hilangkan Foto 1
                                                            </label>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($photoUrl2): ?>
                                                        <div class="form-check mb-0">
                                                            <input class="form-check-input" type="checkbox" name="hapus_foto_2" value="1" id="chkHapusFoto2">
                                                            <label class="form-check-label text-danger fw-semibold" for="chkHapusFoto2" style="font-size: 0.72rem;">
                                                                Hilangkan Foto 2
                                                            </label>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <label class="form-label small fw-bold text-secondary mb-1">
                                            <i class="bi bi-person-bounding-box me-1"></i> 2. Otorisasi Perubahan Foto:
                                        </label>
                                        <button type="button" id="btnMulaiScanWajah" class="btn btn-primary btn-sm w-100 rounded-pill fw-bold py-2 shadow-xs d-flex align-items-center justify-content-center gap-2" onclick="startFaceAuthProcess()">
                                            <i class="bi bi-person-bounding-box fs-5"></i>
                                            <span>Scan Wajah &amp; Simpan Perubahan Foto</span>
                                        </button>

                                        <?php if (is_logged_in() && (is_admin() || is_hrd())): ?>
                                            <div class="mt-2 text-center">
                                                <button type="button" class="btn btn-outline-primary btn-sm w-100 rounded-pill fw-semibold py-1.5 shadow-xs d-flex align-items-center justify-content-center gap-1.5" onclick="submitAsAuthorizedStaff()" style="font-size: 0.76rem;">
                                                    <i class="bi bi-shield-check text-success"></i>
                                                    <span>Simpan via Sesi <?= e(is_admin() ? 'Admin' : 'SDM & Umum') ?> (<?= e(current_user_name()) ?>)</span>
                                                </button>
                                                <div class="form-text text-muted mt-1" style="font-size: 0.68rem;">Foto yang dihapus/diganti otomatis disimpan 7 hari di Folder Sampah</div>
                                            </div>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Kolom Detail Data Master Inventaris Sesuai Core System Bank (Kanan: 7 Kolom) -->
                        <div class="col-lg-7 col-md-7">
                            
                            <!-- Header Nama Barang dengan Pulse Dot Status -->
                            <div class="p-3 bg-light rounded-3 border mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="label-cell text-primary">NAMA BARANG</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1 d-inline-flex align-items-center gap-1.5 fw-bold font-mono">
                                        <span class="status-pulse-dot"></span>
                                        <span><?= e($cleanStatus) ?></span>
                                    </span>
                                </div>
                                <h3 class="h5 fw-bold text-dark mb-0"><?= e($card['nama_barang']) ?></h3>
                            </div>

                            <!-- Header Rincian Data Master & Tombol Edit -->
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="label-cell text-primary mb-0"><i class="bi bi-card-checklist me-1"></i> RINCIAN DATA MASTER</span>
                                <button type="button" class="btn btn-outline-secondary btn-xs rounded-pill px-2.5 py-1 fw-semibold shadow-2xs" data-bs-toggle="modal" data-bs-target="#modalEditDataKartu">
                                    <i class="bi bi-pencil-square me-1"></i> Ubah Data
                                </button>
                            </div>

                            <!-- Tabel Identitas Core Banking (Bersih & Elegan dengan Badge Nilai) -->
                            <div class="table-responsive rounded-3 border bg-white mb-3">
                                <table class="table table-sm table-striped bank-data-table mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="td-label">Kode Inventaris</td>
                                            <td>
                                                <div class="d-flex align-items-center justify-content-between gap-2">
                                                    <span class="fw-bold font-mono text-primary fs-6"><?= e($kodeInventaris) ?></span>
                                                    <button type="button" class="copy-btn" onclick="copyText('<?= e($kodeInventaris) ?>', this)" title="Salin Kode Inventaris">
                                                        <i class="bi bi-clipboard me-1"></i>Salin
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="td-label">Nomor Seri Inventaris</td>
                                            <td>
                                                <div class="d-flex align-items-center justify-content-between gap-2">
                                                    <span class="fw-bold font-mono text-dark"><?= e($nomorSeriInv) ?></span>
                                                    <button type="button" class="copy-btn" onclick="copyText('<?= e($nomorSeriInv) ?>', this)" title="Salin Nomor Seri">
                                                        <i class="bi bi-clipboard me-1"></i>Salin
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="td-label">Jenis Inventaris</td>
                                            <td>
                                                <div class="d-flex align-items-center justify-content-between gap-2">
                                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-2.5 py-1 font-mono fw-semibold">
                                                        <?= e($jenisInvVal) ?>
                                                    </span>
                                                    <button type="button" class="btn btn-xs btn-link text-muted p-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#modalEditDataKartu" title="Ubah Jenis">
                                                        <i class="bi bi-pencil-fill" style="font-size: 0.72rem;"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="td-label">Ruangan (Lokasi Penempatan)</td>
                                            <td>
                                                <div class="d-flex align-items-center justify-content-between gap-2">
                                                    <span class="badge bg-warning bg-opacity-15 text-dark border border-warning border-opacity-40 rounded-pill px-2.5 py-1 fw-semibold font-mono">
                                                        <i class="bi bi-geo-alt-fill text-warning me-1"></i><?= e($ruanganVal) ?>
                                                    </span>
                                                    <button type="button" class="btn btn-xs btn-link text-muted p-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#modalEditDataKartu" title="Ubah Ruangan">
                                                        <i class="bi bi-pencil-fill" style="font-size: 0.72rem;"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="td-label">Golongan</td>
                                            <td>
                                                <div class="d-flex align-items-center justify-content-between gap-2">
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1 font-mono fw-semibold">
                                                        <?= e($golonganVal) ?>
                                                    </span>
                                                    <button type="button" class="btn btn-xs btn-link text-muted p-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#modalEditDataKartu" title="Ubah Golongan">
                                                        <i class="bi bi-pencil-fill" style="font-size: 0.72rem;"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="td-label">Sumber Perolehan</td>
                                            <td class="fw-semibold text-dark"><?= e($sumberVal) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="td-label">Tanggal Perolehan</td>
                                            <td class="fw-semibold text-dark"><?= e($tglFormatted) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="td-label">Keterangan / Rincian</td>
                                            <td class="text-muted small"><?= e($keteranganVal ?: '-') ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Footer Kartu: QR Code Resmi Web Internal -->
                <div class="p-3 p-md-4 bg-light border-top">
                    <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3 text-center text-md-start">
                        <div class="d-flex flex-column flex-sm-row align-items-center gap-3 w-100">
                            <div class="qr-card-box shadow-xs flex-shrink-0">
                                <div id="cardQrCode"></div>
                            </div>
                            <div class="w-100" style="min-width: 0;">
                                <h6 class="fw-bold mb-1 text-dark d-flex align-items-center gap-1.5 justify-content-center justify-content-sm-start">
                                    <i class="bi bi-qr-code text-primary"></i> Barcode &amp; QR Code Resmi Bank
                                </h6>
                                <p class="text-secondary small mb-2 mx-auto mx-sm-0" style="max-width: 520px; font-size: 0.78rem;">
                                    Hasil scan stiker fisik inventaris mengarah langsung ke paspor aset ini di server lokal BPR Mitratama.
                                </p>
                                <!-- Kotak Tautan QR Code Share Link Modern -->
                                <div class="input-group input-group-sm mx-auto mx-sm-0" style="max-width: 480px;">
                                    <span class="input-group-text bg-white text-muted border-end-0" style="font-size: 0.75rem;"><i class="bi bi-link-45deg"></i></span>
                                    <input type="text" class="form-control form-control-sm font-mono bg-white text-muted border-start-0 border-end-0" value="<?= e($canonicalQrUrl) ?>" id="inputCardUrl" readonly style="font-size: 0.74rem;">
                                    <button class="btn btn-primary px-3 fw-semibold shadow-2xs d-inline-flex align-items-center gap-1" type="button" onclick="copyCardUrlDirect(this)">
                                        <i class="bi bi-clipboard"></i> <span>Salin Tautan</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tautan Terkait IT Asset (Jika Terdaftar di Master TI) -->
            <?php if ($matchedAsset): 
                $matchedNama = function_exists('asset_title') ? asset_title($matchedAsset) : trim(($matchedAsset['merk'] ?? '') . ' ' . ($matchedAsset['model'] ?? ''));
                if ($matchedNama === '') $matchedNama = 'Perangkat TI #' . ($matchedAsset['id'] ?? '');
                $matchedKode = (string)($matchedAsset['kode_inventaris'] ?? $matchedAsset['id'] ?? '-');
                $matchedScanToken = (string)($matchedAsset['qr_token'] ?? $matchedAsset['kode_inventaris'] ?? $matchedAsset['id'] ?? '');
                $matchedKaryawan = (string)($matchedAsset['karyawan_nama'] ?? $matchedAsset['nama_karyawan'] ?? '-');
                $matchedDivisi = (string)($matchedAsset['divisi_nama'] ?? $matchedAsset['nama_divisi'] ?? '-');
            ?>
                <div class="card card-custom p-3 p-md-4 mb-4 bg-white">
                    <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-2 mb-3">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-info text-dark rounded-pill px-2.5 py-1">Aset TI Terhubung</span>
                            <h6 class="mb-0 fw-bold"><?= e($matchedNama) ?> (<?= e($matchedKode) ?>)</h6>
                        </div>
                        <a href="<?= qr_url('scan.php', ['t' => $matchedScanToken]) ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 w-100 w-sm-auto text-center">
                            <i class="bi bi-wrench me-1"></i> Buka Maintenance TI
                        </a>
                    </div>
                    <div class="row g-2 small text-muted">
                        <div class="col-sm-3 col-6">
                            <span>Penanggung Jawab:</span>
                            <strong class="d-block text-dark"><?= e($matchedKaryawan ?: '-') ?></strong>
                        </div>
                        <div class="col-sm-3 col-6">
                            <span>Ruangan:</span>
                            <strong class="d-block text-dark"><?= e($ruanganVal ?: '-') ?></strong>
                        </div>
                        <div class="col-sm-3 col-6">
                            <span>Alamat IP:</span>
                            <strong class="d-block text-dark font-mono"><?= e($matchedAsset['ip_address'] ?? '-') ?></strong>
                        </div>
                        <div class="col-sm-3 col-6">
                            <span>S/N Perangkat:</span>
                            <strong class="d-block text-dark font-mono"><?= e($matchedAsset['serial_number'] ?? '-') ?></strong>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </main>

    <?php require __DIR__ . '/views/kartu_detail/modals.php'; ?>
    <?php require __DIR__ . '/views/kartu_detail/scripts.php'; ?>
</body>
</html>
