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

// Pastikan skema tabel inventaris_kartu & master_ruangan siap
ensure_inventaris_kartu_columns();
ensure_inventaris_kartu_print_columns();
csrf_token();

$cardId = (int)($_GET['id'] ?? 0);
$rekParam = trim((string)($_GET['rek'] ?? $_GET['no_rek'] ?? ''));

$card = null;
if ($cardId > 0) {
    $card = get_inventaris_kartu_by_id($cardId);
} elseif ($rekParam !== '') {
    $card = get_inventaris_kartu_by_norek($rekParam);
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
$uploadSuccess = null;
$uploadError = null;
$dataSuccess = null;
$dataError = null;

// =========================================================================
// 0. HANDLE HAPUS / KOSONGKAN FOTO KARTU DENGAN OTORISASI BIOMETRIK / ADMIN
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_foto' && $card) {
    verify_csrf();

    $faceVerified = (string)($_POST['face_verified'] ?? '0');
    $verifierName = trim((string)($_POST['verifier_name'] ?? ''));
    $verifierUserId = (int)($_POST['verifier_user_id'] ?? 0);
    $verifierConfidence = (int)($_POST['verifier_confidence'] ?? 0);

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
            db()->prepare("UPDATE inventaris_kartu SET foto = NULL, foto_updated_by = NULL, foto_updated_at = NULL WHERE id = ?")->execute([(int)$card['id']]);
            $card['foto'] = null;
            $card['foto_updated_by'] = null;
            $card['foto_updated_at'] = null;
            $_SESSION['flash'] = '✓ Foto kartu inventaris berhasil dikosongkan dengan otorisasi: ' . e($verifierLabel) . '.';
            header('Location: ' . module_url('kartu_detail.php', ['id' => (int)$card['id']]));
            exit;
        } catch (Throwable $e) {
            $uploadError = 'Gagal menghapus foto: ' . $e->getMessage();
        }
    }
}

// =========================================================================
// 1. HANDLE UPLOAD / GANTI FOTO DENGAN KOMPRESI CERDAS & VERIFIKASI WAJAH
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
    } elseif (!empty($_FILES['foto_barang']['name'])) {
        $file = $_FILES['foto_barang'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $maxSize = 25 * 1024 * 1024; // 25MB batas awal sebelum kompresi
            if ($file['size'] > $maxSize) {
                $uploadError = "Ukuran file terlalu besar (maksimal 25MB).";
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                    $ext = 'jpg';
                }
                $uploadDir = __DIR__ . '/uploads/assets/';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                    @chmod($uploadDir, 0777);
                }
                // Simpan selalu dalam format jpg terkompresi hemat ruang
                $fileName = 'kartu_' . $card['id'] . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.jpg';
                $targetPath = $uploadDir . $fileName;

                // Kompresi & auto-crop gambar rasio 4:5 (max 1200x1500px, quality 82, file size ~150-250KB)
                if (compress_and_save_uploaded_image($file['tmp_name'], $targetPath, 1600, 82, true)) {
                    $relativePhoto = 'uploads/assets/' . $fileName;
                    
                    // Simpan ke database HANYA untuk ID kartu ini
                    update_inventaris_kartu_foto((int)$card['id'], $relativePhoto, null, $verifierLabel);
                    $card['foto'] = $relativePhoto;
                    $card['foto_updated_by'] = $verifierLabel;
                    $card['foto_updated_at'] = date('Y-m-d H:i:s');

                    // Catat Audit Log Aktivitas
                    try {
                        record_audit_log(
                            'UPDATE',
                            'INVENTARIS_KARTU',
                            (int)$card['id'],
                            (string)$card['nama_barang'],
                            "Foto barang diperbarui via Biometrik Wajah oleh: {$verifierLabel}",
                            ['user_name' => $verifierName, 'user_role' => 'teknisi']
                        );
                    } catch (Throwable $e) {}

                    $uploadSuccess = "Foto barang berhasil dikompres dan disimpan ke server! Diverifikasi oleh: " . $verifierLabel;
                } else {
                    $uploadError = "Gagal memproses dan mengompres file foto ke folder uploads server.";
                }
            }
        } else {
            $uploadError = "Terjadi kesalahan saat mengunggah foto (kode error: " . $file['error'] . ").";
        }
    } else {
        $uploadError = "Silakan pilih file foto terlebih dahulu.";
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

// Resolusi foto final: Hanya gunakan foto asli milik kartu inventaris ini
$displayPhoto = !empty($card['foto']) ? $card['foto'] : null;

$photoUrl = '';
if ($displayPhoto) {
    $photoUrl = str_starts_with($displayPhoto, 'http') ? $displayPhoto : module_url($displayPhoto);
}

// URL QR Code Resmi
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$canonicalQrUrl = $card ? module_url('kartu_detail.php', ['id' => $card['id']]) : $currentUrl;

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

    <!-- Header Brand BPR Mitratama -->
    <header class="header-brand py-3.5 px-3 px-md-4 shadow-sm" style="background: #0E5C58 !important; border-bottom: 3px solid #094340;">
        <div class="container d-flex align-items-center justify-content-between gap-3" style="max-width: 1200px;">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <div class="bg-white px-3 py-1.5 rounded-3 shadow-sm flex-shrink-0 d-flex align-items-center justify-content-center" style="height: 54px; min-width: 75px;">
                    <img src="<?= module_url('logo_img.php') ?>" alt="Logo Bank Mitra" style="height: 42px; width: auto; max-width: 160px; object-fit: contain;" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'32\' height=\'32\' viewBox=\'0 0 24 24\' fill=\'%230E5C58\'%3E%3Cpath d=\'M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5\'/%3E%3C/svg%3E';">
                </div>
                <div class="min-w-0">
                    <h1 class="h5 mb-0 text-white fw-bold tracking-tight text-truncate" style="font-size: 1.25rem; line-height: 1.2;">
                        PT. BPR MITRATAMA ARTHABUANA
                    </h1>
                    <div class="text-teal-light text-truncate fw-semibold mt-0.5" style="font-size: 0.85rem; letter-spacing: 0.5px;">DATA MASTER KARTU INVENTARIS RESMI</div>
                </div>
            </div>
            <div class="flex-shrink-0">
                <?php if (is_logged_in()): ?>
                    <a href="<?= module_url('dashboard.php', ['tab' => 'kartu']) ?>" class="btn btn-outline-light rounded-pill px-4 py-2 d-inline-flex align-items-center gap-2 fw-bold shadow-xs" style="font-size: 0.92rem; border-color: rgba(255,255,255,0.6);">
                        <i class="bi bi-arrow-left fs-6"></i> <span>Dashboard</span>
                    </a>
                <?php else: ?>
                    <a href="<?= module_url('login.php') ?>" class="btn btn-outline-light rounded-pill px-4 py-2 d-inline-flex align-items-center gap-2 fw-bold shadow-xs" style="font-size: 0.92rem; border-color: rgba(255,255,255,0.6);">
                        <i class="bi bi-box-arrow-in-right fs-6"></i> <span>Login</span>
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
                <!-- TAMPILAN KHUSUS SMARTPHONE / MOBILE (1X BUKA LANGSUNG TERLIHAT FOTO & DETAIL) -->
                <div class="d-md-none p-3 border-bottom bg-white">
                    <div class="row g-3 align-items-center mb-2">
                        <!-- Foto Barang (Kiri 42%) -->
                        <div class="col-5">
                            <div class="item-photo-wrapper shadow-xs mb-0 <?= $photoUrl ? 'has-image' : '' ?>" style="max-height: 175px; aspect-ratio: 4 / 5; cursor: pointer; border-radius: 12px;" onclick="<?= $photoUrl ? "viewFullImage('" . e(addslashes($photoUrl)) . "', '" . e(addslashes($card['nama_barang'])) . "')" : "" ?>" title="Klik untuk memperbesar">
                                <?php if ($photoUrl): ?>
                                    <img src="<?= e($photoUrl) ?>" alt="Foto <?= e($card['nama_barang']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;">
                                    <div class="position-absolute bottom-0 end-0 p-1 bg-dark bg-opacity-75 text-white rounded-start" style="font-size: 0.65rem;">
                                        <i class="bi bi-arrows-fullscreen"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center p-2">
                                        <i class="bi bi-camera text-muted fs-3 d-block mb-1"></i>
                                        <div class="text-muted fw-semibold" style="font-size: 0.68rem;">Belum Ada Foto</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Ringkasan Identitas Utama (Kanan 58%) -->
                        <div class="col-7">
                            <div class="d-flex align-items-center justify-content-between mb-1 flex-wrap gap-1">
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2 py-0.5 d-inline-flex align-items-center gap-1.5 fw-bold" style="font-size: 0.70rem;"><span class="status-pulse-dot"></span><span><?= e($cleanStatus) ?></span></span>
                                <?php if ($isPrintedDetail): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2 py-0.5 fw-bold" style="font-size: 0.66rem;" title="Dicetak pada <?= e($printedTimeDetail) ?>">
                                        <i class="bi bi-check-circle-fill me-0.5"></i>Sudah Dicetak
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning bg-opacity-15 text-warning-emphasis border border-warning border-opacity-50 rounded-pill px-2 py-0.5 fw-bold" style="font-size: 0.66rem;">
                                        <i class="bi bi-hourglass-split me-0.5"></i>Belum Dicetak
                                    </span>
                                <?php endif; ?>
                            </div>
                            <h2 class="h6 fw-bold text-dark mb-1 lh-sm" style="font-size: 0.95rem;"><?= e($card['nama_barang']) ?></h2>
                            <div class="font-mono text-primary fw-bold small mb-1" style="font-size: 0.78rem;"><?= e($kodeInventaris) ?> <span class="text-muted fw-normal">(#<?= (int)$card['id'] ?>)</span></div>
                            <div class="small text-muted mb-0.5" style="font-size: 0.72rem;">
                                <i class="bi bi-geo-alt-fill text-warning me-1"></i><strong><?= e($lokasiBarangVal) ?></strong>
                            </div>
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
                        <?php else: ?>
                            <span class="badge bg-warning bg-opacity-15 text-warning-emphasis border border-warning border-opacity-50 px-3 py-2 rounded-pill fw-bold shadow-2xs" style="font-size: 0.84rem;">
                                <i class="bi bi-hourglass-split me-1"></i> Belum Dicetak
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
                        
                        <!-- Kolom Foto Barang & Biometric Action (Kiri: 5 Kolom) -->
                        <div class="col-lg-5 col-md-5 text-center">
                            <div class="item-photo-wrapper mb-2 shadow-xs d-none d-md-flex position-relative <?= $photoUrl ? 'has-image' : '' ?>" id="photoWrapper">
                                <img id="previewImg" src="<?= $photoUrl ? e($photoUrl) : '' ?>" alt="Foto <?= e($card['nama_barang']) ?>" class="<?= $photoUrl ? '' : 'd-none' ?>" onclick="viewFullImage(this.src, '<?= e(addslashes($card['nama_barang'])) ?>')" title="Klik untuk memperbesar">
                                
                                <div id="emptyPhotoPlaceholder" class="text-center p-4 <?= $photoUrl ? 'd-none' : '' ?>">
                                    <i class="bi bi-camera text-muted display-4 mb-2 d-block"></i>
                                    <div class="small text-secondary fw-semibold">Belum Ada Foto Barang</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">Pilih foto & scan wajah di bawah</div>
                                </div>

                                <div id="previewBadge" class="preview-alert-badge d-none">
                                    <i class="bi bi-eye me-1"></i> Pratinjau Terkompresi Siap Simpan
                                </div>

                                <?php if ($photoUrl): ?>
                                    <button type="button" class="btn btn-danger btn-xs position-absolute top-0 end-0 m-2 rounded-circle shadow-sm" style="width: 28px; height: 28px; padding: 0; display: inline-flex; align-items: center; justify-content: center; z-index: 3;" data-bs-toggle="modal" data-bs-target="#modalConfirmDeletePhoto" onclick="event.stopPropagation(); openDeletePhotoConfirmModal(event);" title="Kosongkan Foto">
                                        <i class="bi bi-trash3-fill" style="font-size: 0.75rem;"></i>
                                    </button>
                                <?php endif; ?>
                            </div>

                            <!-- Info Badge Verifikasi Wajah (2 Baris Rapi & Terstruktur) -->
                            <?php if (!empty($card['foto'])): ?>
                                <?php 
                                    $rawVerifier = (string)($card['foto_updated_by'] ?? '');
                                    $isFaceScan = str_contains($rawVerifier, 'Scan Wajah');
                                    $verifierCleanName = preg_replace('/\s*\(.*?\)/', '', $rawVerifier) ?: 'Petugas IT';
                                    $verifierTime = !empty($card['foto_updated_at']) ? date('d/m/Y H:i', strtotime($card['foto_updated_at'])) : '-';
                                ?>
                                <div class="bg-success bg-opacity-10 border border-success border-opacity-25 rounded-3 p-2.5 mb-2 text-start">
                                    <div class="d-flex align-items-center gap-1.5 text-success fw-bold" style="font-size: 0.78rem;">
                                        <i class="bi bi-patch-check-fill fs-6 text-success"></i>
                                        <span>Foto Terverifikasi <?= $isFaceScan ? '(Scan Wajah)' : '(Sesi Resmi)' ?></span>
                                    </div>
                                    <div class="text-secondary small mt-0.5" style="font-size: 0.70rem;">
                                        Oleh <strong class="text-dark"><?= e($verifierCleanName) ?></strong> &bull; <?= e($verifierTime) ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Tombol Aksi Foto (Perbarui Foto & Kosongkan Foto Sejajar - Desktop Only) -->
                            <div class="d-none d-md-flex align-items-center justify-content-center gap-2 mb-2">
                                <button class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1.5 fw-semibold shadow-2xs" type="button" data-bs-toggle="collapse" data-bs-target="#collapseUploadPhoto" aria-expanded="<?= empty($card['foto']) ? 'true' : 'false' ?>" id="btnToggleUpload">
                                    <i class="bi bi-camera me-1"></i> <?= !empty($card['foto']) ? 'Perbarui Foto' : 'Pilih Foto' ?>
                                </button>
                                <?php if (!empty($card['foto'])): ?>
                                    <form method="post" id="deletePhotoForm" class="d-inline">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_foto">
                                        <input type="hidden" name="face_verified" id="delBioVerified" value="0">
                                        <input type="hidden" name="verifier_user_id" id="delBioUserId" value="">
                                        <input type="hidden" name="verifier_name" id="delBioUserName" value="">
                                        <input type="hidden" name="verifier_confidence" id="delBioConfidence" value="">
                                        <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1.5 fw-semibold shadow-2xs" data-bs-toggle="modal" data-bs-target="#modalConfirmDeletePhoto" onclick="openDeletePhotoConfirmModal(event)">
                                            <i class="bi bi-trash3 me-1"></i> Kosongkan
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <!-- Form Upload Foto Barang (Collapsible: tertutup jika sudah ada foto) -->
                            <div class="collapse <?= empty($card['foto']) ? 'show' : '' ?> text-start" id="collapseUploadPhoto">
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

                                        <div class="d-flex justify-content-between align-items-center mb-1.5">
                                            <label class="form-label small fw-bold text-secondary mb-0">
                                                <i class="bi bi-image me-1"></i> 1. Pilih / Jepret Foto:
                                            </label>
                                            <span id="compressBadge" class="badge bg-primary bg-opacity-10 text-primary d-none" style="font-size: 0.68rem;">
                                                <i class="bi bi-shield-check me-1"></i> Kompres Cerdas
                                            </span>
                                        </div>

                                        <!-- Modern Dashed Dropzone -->
                                        <div class="photo-dropzone p-3 text-center mb-2" id="dropzoneBox" onclick="document.getElementById('fotoBarangInput').click()">
                                            <input type="file" id="fotoBarangInput" name="foto_barang" class="d-none" accept="image/*" capture="environment" required onchange="handleFileSelect(this)">
                                            <div id="dropzoneEmpty">
                                                <div class="d-inline-flex align-items-center justify-content-center p-2 bg-primary bg-opacity-10 text-primary rounded-circle mb-1">
                                                    <i class="bi bi-camera fs-4"></i>
                                                </div>
                                                <div class="small fw-bold text-dark">Klik untuk Pilih / Jepret Foto</div>
                                                <div class="text-muted" style="font-size: 0.68rem;">Format JPG/PNG, dikompres otomatis hemat memori</div>
                                            </div>
                                            <div id="dropzoneSelected" class="d-none">
                                                <div class="small fw-bold text-success mb-1">
                                                    <i class="bi bi-check-circle-fill me-1"></i> <span id="selectedFileName">Foto Terpilih</span>
                                                </div>
                                                <span class="badge bg-light text-secondary border px-2 py-0.5" id="selectedFileSize" style="font-size: 0.68rem;"></span>
                                                <div class="small text-primary mt-1" style="font-size: 0.68rem;"><i class="bi bi-arrow-repeat me-1"></i>Klik untuk ganti foto</div>
                                            </div>
                                        </div>

                                        <label class="form-label small fw-bold text-secondary mb-1">
                                            <i class="bi bi-person-bounding-box me-1"></i> 2. Otorisasi Perubahan Foto:
                                        </label>
                                        <button type="button" id="btnMulaiScanWajah" class="btn btn-primary btn-sm w-100 rounded-pill fw-bold py-2 shadow-xs d-flex align-items-center justify-content-center gap-2" onclick="startFaceAuthProcess()">
                                            <i class="bi bi-person-bounding-box fs-5"></i>
                                            <span>Scan Wajah &amp; Simpan Foto</span>
                                        </button>

                                        <?php if (is_logged_in() && (is_admin() || is_hrd())): ?>
                                            <div class="mt-2 text-center">
                                                <button type="button" class="btn btn-outline-primary btn-sm w-100 rounded-pill fw-semibold py-1.5 shadow-xs d-flex align-items-center justify-content-center gap-1.5" onclick="submitAsAuthorizedStaff()" style="font-size: 0.76rem;">
                                                    <i class="bi bi-shield-check text-success"></i>
                                                    <span>Simpan via Sesi <?= e(is_admin() ? 'Admin' : 'SDM & Umum') ?> (<?= e(current_user_name()) ?>)</span>
                                                </button>
                                                <div class="form-text text-muted mt-1" style="font-size: 0.68rem;">Tanpa pemindaian biometrik wajah kamera</div>
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
                        <a href="<?= module_url('scan.php', ['t' => $matchedScanToken]) ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 w-100 w-sm-auto text-center">
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
