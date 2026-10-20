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

$cardId = (int)($_GET['id'] ?? 0);
$rekParam = trim((string)($_GET['rek'] ?? $_GET['no_rek'] ?? ''));

$card = null;
if ($cardId > 0) {
    $card = get_inventaris_kartu_by_id($cardId);
} elseif ($rekParam !== '') {
    $card = get_inventaris_kartu_by_norek($rekParam);
}

// State notifikasi
$uploadSuccess = null;
$uploadError = null;
$dataSuccess = null;
$dataError = null;

// =========================================================================
// 0. HANDLE HAPUS / KOSONGKAN FOTO KARTU
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_foto' && $card) {
    verify_csrf();
    try {
        db()->prepare("UPDATE inventaris_kartu SET foto = NULL, foto_updated_by = NULL, foto_updated_at = NULL WHERE id = ?")->execute([(int)$card['id']]);
        $card['foto'] = null;
        $card['foto_updated_by'] = null;
        $card['foto_updated_at'] = null;
        $_SESSION['flash'] = '✓ Foto kartu inventaris berhasil dihapus/dikosongkan.';
        header('Location: ' . module_url('kartu_detail.php', ['id' => (int)$card['id']]));
        exit;
    } catch (Throwable $e) {
        $uploadError = 'Gagal menghapus foto: ' . $e->getMessage();
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

                // Kompresi gambar otomatis (max 1600px, quality 82, file size ~150-250KB)
                if (compress_and_save_uploaded_image($file['tmp_name'], $targetPath, 1600, 82)) {
                    $relativePhoto = 'uploads/assets/' . $fileName;
                    
                    // Simpan ke database HANYA untuk ID kartu ini
                    update_inventaris_kartu_foto((int)$card['id'], $relativePhoto, null, $verifierLabel);
                    $card['foto'] = $relativePhoto;
                    $card['foto_updated_by'] = $verifierLabel;
                    $card['foto_updated_at'] = date('Y-m-d H:i:s');

                    // Catat Audit Log POJK
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

    $jenisInv = trim((string)($_POST['jenis_inventaris'] ?? ''));
    $golongan = trim((string)($_POST['golongan'] ?? ''));
    $ruanganSelect = trim((string)($_POST['ruangan_select'] ?? ''));
    $ruanganCustom = trim((string)($_POST['ruangan_custom'] ?? ''));
    $sumberPerolehan = trim((string)($_POST['sumber_perolehan'] ?? ''));
    $statusInv = trim((string)($_POST['status_inventaris'] ?? '1 : Aktif'));
    $nomorSeri = trim((string)($_POST['nomor_seri_inventaris'] ?? ''));
    $keterangan = trim((string)($_POST['keterangan'] ?? ''));

    // Jika user menambahkan ruangan baru secara kustom, masukkan ke master_ruangan
    $finalRuangan = $ruanganSelect;
    if (!empty($ruanganCustom)) {
        $finalRuangan = add_master_ruangan($ruanganCustom);
    }

    $updated = update_inventaris_kartu_data((int)$card['id'], [
        'nomor_seri_inventaris' => $nomorSeri,
        'jenis_inventaris' => $jenisInv,
        'golongan' => $golongan,
        'ruangan' => $finalRuangan,
        'sumber_perolehan' => $sumberPerolehan,
        'status_inventaris' => $statusInv,
        'keterangan' => $keterangan,
    ]);

    if ($updated) {
        $card = get_inventaris_kartu_by_id((int)$card['id']);
        $dataSuccess = "Rincian inventaris (Jenis, Ruangan, Golongan, No Seri) berhasil disimpan!";
        
        try {
            record_audit_log(
                'UPDATE',
                'INVENTARIS_KARTU',
                (int)$card['id'],
                (string)$card['nama_barang'],
                "Data spesifikasi master inventaris diperbarui (Ruangan: {$finalRuangan}, Jenis: {$jenisInv}, Gol: {$golongan})"
            );
        } catch (Throwable $e) {}
    } else {
        $dataError = "Gagal memperbarui rincian data kartu inventaris.";
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <!-- QRCode.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        :root {
            --app-navy: #002B49;
            --app-gold: #C5A059;
            --app-gold-light: #F7F3E9;
            --app-bg: #F4F6F9;
        }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--app-bg);
            color: #1E293B;
            min-height: 100vh;
        }
        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }
        .header-brand {
            background: linear-gradient(135deg, #001B30 0%, #003B66 100%);
            color: #ffffff;
            border-bottom: 3px solid var(--app-gold);
        }
        .card-custom {
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            box-shadow: 0 4px 20px -2px rgba(0, 43, 73, 0.08);
            background: #ffffff;
            overflow: hidden;
        }
        .badge-gold {
            background-color: var(--app-gold-light);
            color: #8C6D23;
            border: 1px solid rgba(197, 160, 89, 0.3);
            font-weight: 700;
        }
        .label-cell {
            color: #64748B;
            font-size: 0.76rem;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .value-cell {
            font-weight: 700;
            color: #0F172A;
            font-size: 0.95rem;
        }
        .item-photo-wrapper {
            position: relative;
            border-radius: 14px;
            overflow: hidden;
            background: #F1F5F9;
            aspect-ratio: 4 / 3;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px dashed #CBD5E1;
            transition: all 0.2s ease;
        }
        .item-photo-wrapper.has-image {
            border: 1px solid #E2E8F0;
        }
        .item-photo-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        .item-photo-wrapper img:hover {
            transform: scale(1.02);
        }
        .qr-card-box {
            background: #ffffff;
            padding: 10px;
            border-radius: 12px;
            border: 1px solid #CBD5E1;
            display: inline-block;
        }
        .preview-alert-badge {
            background: rgba(0, 43, 73, 0.88);
            backdrop-filter: blur(4px);
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 8px;
            position: absolute;
            bottom: 10px;
            left: 10px;
            right: 10px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .bank-data-table td, .bank-data-table th {
            padding: 9px 12px;
            vertical-align: middle;
            font-size: 0.88rem;
        }
        
        /* Gaya Biometric Scanner */
        .bio-scanner-wrapper {
            position: relative;
            width: 100%;
            max-width: 300px;
            aspect-ratio: 3 / 4;
            max-height: 380px;
            border-radius: 20px;
            overflow: hidden;
            background: #0f172a;
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
            margin: 0 auto;
        }
        .bio-video-el {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1);
        }
        .bio-canvas-el {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            transform: scaleX(-1);
        }
        .bio-face-oval {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 180px;
            height: 230px;
            border: 3px dashed #38bdf8;
            border-radius: 50%;
            box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.65);
            pointer-events: none;
            transition: all 0.3s ease;
        }
        .bio-face-oval.active {
            border-color: #22c55e;
            border-style: solid;
            box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.4), 0 0 25px rgba(34, 197, 94, 0.6);
        }
        .bio-scanline {
            position: absolute;
            top: 25%;
            left: calc(50% - 90px);
            width: 180px;
            height: 3px;
            background: linear-gradient(90deg, transparent, #38bdf8, transparent);
            animation: bioScanMove 2s infinite ease-in-out;
            pointer-events: none;
        }
        @keyframes bioScanMove {
            0% { top: 20%; opacity: 0; }
            50% { opacity: 1; }
            100% { top: 80%; opacity: 0; }
        }
        .bio-success-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.92);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        /* ── Mobile-First Optimization for Smartphones (HP) ── */
        @media (max-width: 768px) {
            body {
                background: #f8fafc;
            }
            .header-brand {
                padding: 12px 14px !important;
            }
            .header-brand h1 {
                font-size: 0.85rem !important;
            }
            main.container {
                padding-left: 10px !important;
                padding-right: 10px !important;
                padding-top: 14px !important;
            }
            .card-custom {
                border-radius: 14px !important;
                box-shadow: 0 4px 16px rgba(0, 43, 73, 0.06) !important;
            }
            .card-custom .p-3, .card-custom .p-md-4 {
                padding: 14px !important;
            }
            .bank-data-table {
                font-size: 0.82rem !important;
            }
            .bank-data-table td {
                padding: 8px 10px !important;
                word-break: break-word;
            }
            .item-photo-wrapper {
                border-radius: 12px;
                max-height: 240px;
            }
            .qr-card-box {
                margin: 0 auto;
            }
            .btn {
                padding: 9px 16px !important;
                font-size: 0.84rem !important;
            }
            /* Form inputs touch-friendly */
            .form-control, .form-select {
                font-size: 0.92rem !important;
                min-height: 42px;
                border-radius: 10px;
            }
        }
    </style>
</head>
<body>

    <!-- Header Brand BPR Mitratama -->
    <header class="header-brand py-3 px-4 shadow-sm">
        <div class="container max-w-4xl d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white p-2 rounded-3 shadow-xs">
                    <img src="<?= module_url('logo_img.php') ?>" alt="Logo Bank Mitra" style="height: 32px;" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'32\' height=\'32\' viewBox=\'0 0 24 24\' fill=\'%23002B49\'%3E%3Cpath d=\'M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5\'/%3E%3C/svg%3E';">
                </div>
                <div>
                    <h1 class="h6 mb-0 text-white fw-bold tracking-tight">PT. BPR MITRATAMA ARTHABUANA</h1>
                    <div class="small text-white-50" style="font-size: 0.72rem; letter-spacing: 0.5px;">DATA MASTER KARTU INVENTARIS RESMI</div>
                </div>
            </div>
            <div>
                <?php if (is_logged_in()): ?>
                    <a href="<?= module_url('dashboard.php', ['tab' => 'kartu']) ?>" class="btn btn-outline-light btn-sm rounded-pill px-3">
                        <i class="bi bi-arrow-left me-1"></i> Dashboard
                    </a>
                <?php else: ?>
                    <a href="<?= module_url('login.php') ?>" class="btn btn-outline-light btn-sm rounded-pill px-3">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="container py-4" style="max-width: 1020px;">

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
                $tglFormatted = format_indo_date($card['tanggal_perolehan']);
                $nomorGabungan = get_nomor_asset_gabungan($rek, $card['tanggal_perolehan']);
                $cBranch = get_cabang_from_nomor_rekening($rek);
                $kdCabang = $cBranch ? $cBranch['code'] : '01';
                $namaCabang = $cBranch ? strtoupper($cBranch['name']) : 'KANTOR PUSAT OPERASIONAL';
                
                // Nilai-nilai master inventaris
                $nomorSeriInv = !empty($card['nomor_seri_inventaris']) ? $card['nomor_seri_inventaris'] : generate_default_nomor_seri($card);
                $jenisInvVal = !empty($card['jenis_inventaris']) ? $card['jenis_inventaris'] : '3 : PERALATAN KANTOR';
                $golonganVal = !empty($card['golongan']) ? $card['golongan'] : '02 : GOLONGAN I';
                $ruanganVal = !empty($card['ruangan']) ? $card['ruangan'] : (!empty($card['lokasi']) ? $card['lokasi'] : '05 : LANTAI I GEDUNG DEPAN');
                $sumberVal = !empty($card['sumber_perolehan']) ? $card['sumber_perolehan'] : '02 : MODAL DISETOR';
                $statusVal = !empty($card['status_inventaris']) ? $card['status_inventaris'] : '1 : Aktif';
                $keteranganVal = !empty($card['keterangan']) ? $card['keterangan'] : $card['nama_barang'];
            ?>

            <!-- Kartu Paspor Inventaris Resmi -->
            <div class="card card-custom mb-4">
                <div class="p-3 p-md-4 border-bottom bg-light bg-opacity-75 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge badge-gold px-3 py-1.5 rounded-pill text-uppercase">
                            <i class="bi bi-shield-check me-1"></i> Data Master Inventaris
                        </span>
                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1.5 rounded-pill font-mono">
                            ID: #<?= (int)$card['id'] ?>
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="<?= module_url('inventaris_mutation.php', ['id' => $card['id']]) ?>" class="btn btn-outline-success btn-sm rounded-pill px-3 fw-semibold shadow-xs">
                            <i class="bi bi-arrow-left-right me-1"></i> Mutasi Barang Inventaris
                        </a>
                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalEditDataKartu">
                            <i class="bi bi-pencil-square me-1"></i> Edit Data Inventaris
                        </button>
                        <a href="<?= module_url('print_inventory_card.php', ['source' => 'inventaris_kartu', 'id' => $card['id']]) ?>" target="_blank" class="btn btn-dark btn-sm rounded-pill px-3 fw-semibold shadow-xs">
                            <i class="bi bi-printer me-1"></i> Cetak CR80
                        </a>
                    </div>
                </div>

                <div class="p-3 p-md-4">
                    <div class="row g-4 align-items-start">
                        
                        <!-- Kolom Foto Barang & Biometric Action (Kiri: 5 Kolom) -->
                        <div class="col-lg-5 col-md-5 text-center">
                            <div class="item-photo-wrapper mb-2 shadow-xs <?= $photoUrl ? 'has-image' : '' ?>" id="photoWrapper">
                                <img id="previewImg" src="<?= $photoUrl ? e($photoUrl) : '' ?>" alt="Foto <?= e($card['nama_barang']) ?>" class="<?= $photoUrl ? '' : 'd-none' ?>" onclick="viewFullImage(this.src, '<?= e(addslashes($card['nama_barang'])) ?>')" title="Klik untuk memperbesar">
                                
                                <div id="emptyPhotoPlaceholder" class="text-center p-4 <?= $photoUrl ? 'd-none' : '' ?>">
                                    <i class="bi bi-camera text-muted display-4 mb-2 d-block"></i>
                                    <div class="small text-secondary fw-semibold">Belum Ada Foto Barang</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">Pilih foto & scan wajah di bawah</div>
                                </div>

                                <div id="previewBadge" class="preview-alert-badge d-none">
                                    <i class="bi bi-eye me-1"></i> Pratinjau Terkompresi Siap Simpan
                                </div>
                            </div>

                            <!-- Label Verifikator Terakhir & Tombol Hapus Foto -->
                            <?php if (!empty($card['foto'])): ?>
                                <div class="mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <?php if (!empty($card['foto_updated_by'])): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1 text-wrap" style="font-size: 0.70rem;">
                                            <i class="bi bi-patch-check-fill me-1"></i> Foto Diverifikasi: <strong><?= e($card['foto_updated_by']) ?></strong>
                                            <?php if (!empty($card['foto_updated_at'])): ?>
                                                (<?= date('d/m/Y H:i', strtotime($card['foto_updated_at'])) ?>)
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.70rem;">
                                            <i class="bi bi-camera me-1"></i> Foto Tersimpan
                                        </span>
                                    <?php endif; ?>

                                    <form method="post" onsubmit="return confirm('Apakah Anda yakin ingin menghapus/mengosongkan foto kartu inventaris ini?');" class="d-inline">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_foto">
                                        <button type="submit" class="btn btn-outline-danger btn-xs py-1 px-2.5 rounded-pill" style="font-size: 0.72rem;">
                                            <i class="bi bi-trash me-1"></i> Kosongkan Foto
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <!-- Form Upload Foto Barang dengan Validasi Wajah -->
                            <div class="bg-light p-3 rounded-3 border text-start shadow-xs">
                                <form method="post" enctype="multipart/form-data" id="uploadPhotoForm">
                                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="upload_foto">
                                    
                                    <!-- Hidden Inputs untuk Biometrik Wajah -->
                                    <input type="hidden" name="face_verified" id="bioVerified" value="0">
                                    <input type="hidden" name="verifier_user_id" id="bioUserId" value="">
                                    <input type="hidden" name="verifier_name" id="bioUserName" value="">
                                    <input type="hidden" name="verifier_confidence" id="bioConfidence" value="">
                                    <input type="hidden" name="verifier_photo" id="bioPhotoSnap" value="">

                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label small fw-bold text-secondary mb-0">
                                            <i class="bi bi-image me-1"></i> 1. Pilih / Jepret Foto:
                                        </label>
                                        <span id="compressBadge" class="badge bg-primary bg-opacity-10 text-primary d-none" style="font-size: 0.68rem;">
                                            <i class="bi bi-shield-check me-1"></i> Terkompres Cerdas
                                        </span>
                                    </div>
                                    <input type="file" id="fotoBarangInput" name="foto_barang" class="form-control form-control-sm mb-2" accept="image/*" capture="environment" required>

                                    <label class="form-label small fw-bold text-secondary mb-1">
                                        <i class="bi bi-person-bounding-box me-1"></i> 2. Otorisasi Perubahan Foto:
                                    </label>
                                    <button type="button" id="btnMulaiScanWajah" class="btn btn-primary btn-sm w-100 rounded-pill fw-bold py-2 shadow-xs d-flex align-items-center justify-content-center gap-2" onclick="startFaceAuthProcess()">
                                        <i class="bi bi-person-bounding-box fs-5"></i>
                                        <span>Scan Wajah & Simpan Foto</span>
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

                        <!-- Kolom Detail Data Master Inventaris Sesuai Core System Bank (Kanan: 7 Kolom) -->
                        <div class="col-lg-7 col-md-7">
                            
                            <!-- Header Nama Barang -->
                            <div class="p-3 bg-light rounded-3 border mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="label-cell text-primary">NAMA BARANG</span>
                                    <span class="badge bg-success rounded-pill px-2.5 py-1 font-mono"><?= e($statusVal) ?></span>
                                </div>
                                <h3 class="h5 fw-bold text-dark mb-0"><?= e($card['nama_barang']) ?></h3>
                            </div>

                            <!-- Tabel Identitas Core Banking (Sesuai Form Master Gambar 1) -->
                            <div class="table-responsive rounded-3 border bg-white">
                                <table class="table table-sm table-striped bank-data-table mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-secondary small fw-bold" style="width: 38%;">Kantor Bank</td>
                                            <td class="fw-bold font-mono text-dark"><?= e($kdCabang) ?> : <?= e($namaCabang) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-secondary small fw-bold">Sub Kantor</td>
                                            <td class="fw-bold font-mono text-dark">00 : KAS INDUK</td>
                                        </tr>
                                        <tr>
                                            <td class="text-secondary small fw-bold">Nomor Rekening</td>
                                            <td class="fw-bold font-mono text-primary fs-6"><?= e($rek) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-secondary small fw-bold">Nomor Seri Inventaris</td>
                                            <td class="fw-bold font-mono text-danger"><?= e($nomorSeriInv) ?></td>
                                        </tr>
                                        <tr class="table-primary bg-opacity-25">
                                            <td class="text-primary small fw-bold">Jenis Inventaris</td>
                                            <td class="fw-bold text-primary"><?= e($jenisInvVal) ?></td>
                                        </tr>
                                        <tr class="table-warning bg-opacity-25">
                                            <td class="text-dark small fw-bold">Ruangan</td>
                                            <td class="fw-bold text-dark"><i class="bi bi-door-open-fill text-warning me-1"></i><?= e($ruanganVal) ?></td>
                                        </tr>
                                        <tr class="table-info bg-opacity-25">
                                            <td class="text-dark small fw-bold">Golongan</td>
                                            <td class="fw-bold text-dark"><?= e($golonganVal) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-secondary small fw-bold">Sumber Perolehan</td>
                                            <td class="fw-bold text-dark"><?= e($sumberVal) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-secondary small fw-bold">Tanggal Perolehan</td>
                                            <td class="fw-bold text-dark"><?= e($tglFormatted) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-secondary small fw-bold">Keterangan / Rincian</td>
                                            <td class="text-muted small"><?= e($keteranganVal) ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-3 text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalEditDataKartu">
                                    <i class="bi bi-gear-fill me-1"></i> Ubah Jenis, Ruangan, atau Golongan
                                </button>
                            </div>

                        </div>

                    </div>
                </div>

                <!-- Footer Kartu: QR Code Resmi Web Internal -->
                <div class="p-3 p-md-4 bg-light border-top">
                    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-3 text-center text-sm-start">
                        <div class="d-flex align-items-center gap-3">
                            <div class="qr-card-box shadow-xs">
                                <div id="cardQrCode"></div>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1 text-dark">Barcode & QR Code Resmi Bank</h6>
                                <p class="text-secondary small mb-1" style="max-width: 480px; font-size: 0.78rem;">
                                    Hasil pemindaian stiker fisik inventaris diarahkan langsung ke halaman ini di server lokal BPR Mitratama.
                                </p>
                                <div class="small font-mono text-muted text-break" style="font-size: 0.72rem;">
                                    <?= e($canonicalQrUrl) ?>
                                </div>
                            </div>
                        </div>

                        <div>
                            <button type="button" class="btn btn-light border btn-sm px-3 rounded-pill fw-semibold shadow-xs" onclick="copyCardLink()">
                                <i class="bi bi-share me-1"></i> Salin Tautan
                            </button>
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
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-info text-dark rounded-pill px-2.5 py-1">Aset TI Terhubung</span>
                            <h6 class="mb-0 fw-bold"><?= e($matchedNama) ?> (<?= e($matchedKode) ?>)</h6>
                        </div>
                        <a href="<?= module_url('scan.php', ['t' => $matchedScanToken]) ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                            <i class="bi bi-wrench me-1"></i> Buka Maintenance TI
                        </a>
                    </div>
                    <div class="row g-2 small text-muted">
                        <div class="col-sm-3 col-6">
                            <span>Penanggung Jawab:</span>
                            <strong class="d-block text-dark"><?= e($matchedKaryawan ?: '-') ?></strong>
                        </div>
                        <div class="col-sm-3 col-6">
                            <span>Divisi / Lokasi:</span>
                            <strong class="d-block text-dark"><?= e($matchedDivisi ?: '-') ?></strong>
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

    <!-- ===================================================================== -->
    <!-- MODAL EDIT DATA MASTER KARTU INVENTARIS                               -->
    <!-- ===================================================================== -->
    <?php if ($card): ?>
    <div class="modal fade" id="modalEditDataKartu" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4 shadow-2xl overflow-hidden">
                <div class="modal-header bg-dark text-white py-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-pencil-square fs-5 text-warning"></i>
                        <div>
                            <h6 class="modal-title fw-bold mb-0">Ubah Data Master Inventaris</h6>
                            <div class="text-white-50 small" style="font-size: 0.72rem;">Sesuai Standar Data Core Banking BPR Mitratama</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <form method="post" action="<?= module_url('kartu_detail.php', ['id' => $card['id']]) ?>">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_card_data">

                    <div class="modal-body p-4 bg-light">
                        <div class="row g-3">
                            
                            <!-- Nomor Rekening (Read Only) -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Nomor Rekening</label>
                                <input type="text" class="form-control form-control-sm font-mono bg-white" value="<?= e($card['nomor_rekening']) ?>" readonly>
                            </div>

                            <!-- Nomor Seri Inventaris -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Nomor Seri Inventaris</label>
                                <input type="text" name="nomor_seri_inventaris" class="form-control form-control-sm font-mono" value="<?= e($nomorSeriInv) ?>" placeholder="Contoh: 01.05.02.220124.001" required>
                                <div class="form-text text-muted" style="font-size: 0.70rem;">Format: [Cabang].[Ruangan].[Golongan].[ddMMyy].[Nomor]</div>
                            </div>

                            <!-- Jenis Inventaris (Gambar 2) -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Jenis Inventaris</label>
                                <select name="jenis_inventaris" class="form-select form-select-sm" required>
                                    <?php foreach ($masterJenis as $k => $val): ?>
                                        <option value="<?= e($val) ?>" <?= ($jenisInvVal === $val || str_starts_with($jenisInvVal, $k . ' :')) ? 'selected' : '' ?>>
                                            <?= e($val) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Golongan (Gambar 3) -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Golongan Inventaris</label>
                                <select name="golongan" class="form-select form-select-sm" required>
                                    <?php foreach ($masterGolongan as $k => $val): ?>
                                        <option value="<?= e($val) ?>" <?= ($golonganVal === $val || str_starts_with($golonganVal, $k . ' :')) ? 'selected' : '' ?>>
                                            <?= e($val) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Ruangan (Dinamis / Bisa Tambah Baru) -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Ruangan Penempatan</label>
                                <select name="ruangan_select" id="ruanganSelect" class="form-select form-select-sm">
                                    <?php foreach ($masterRuangan as $r): ?>
                                        <?php $rLabel = sprintf('%02d : %s', (int)$r['kode_ruangan'], $r['nama_ruangan']); ?>
                                        <option value="<?= e($rLabel) ?>" <?= ($ruanganVal === $rLabel || strcasecmp($ruanganVal, $r['nama_ruangan']) === 0) ? 'selected' : '' ?>>
                                            <?= e($rLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Ruangan Baru (Jika belum ada di list) -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-primary">
                                    <i class="bi bi-plus-circle me-1"></i> Tambah Ruangan Baru (Opsional)
                                </label>
                                <input type="text" name="ruangan_custom" class="form-control form-control-sm" placeholder="Ketik nama ruangan baru di sini..." style="border-color: #38bdf8;">
                                <div class="form-text text-muted" style="font-size: 0.70rem;">Jika diisi, akan otomatis tersimpan ke daftar master ruangan bank.</div>
                            </div>

                            <!-- Sumber Perolehan -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Sumber Perolehan</label>
                                <select name="sumber_perolehan" class="form-select form-select-sm">
                                    <?php foreach ($masterSumber as $k => $val): ?>
                                        <option value="<?= e($val) ?>" <?= ($sumberVal === $val || str_starts_with($sumberVal, $k . ' :')) ? 'selected' : '' ?>>
                                            <?= e($val) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Status Inventaris -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Status Inventaris</label>
                                <select name="status_inventaris" class="form-select form-select-sm">
                                    <?php foreach ($masterStatus as $k => $val): ?>
                                        <option value="<?= e($val) ?>" <?= ($statusVal === $val) ? 'selected' : '' ?>>
                                            <?= e($val) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Keterangan / Spesifikasi -->
                            <div class="col-12">
                                <label class="form-label small fw-bold text-secondary">Keterangan / Spesifikasi Barang</label>
                                <textarea name="keterangan" class="form-control form-control-sm" rows="3" placeholder="Rincian merek, tipe, kapasitas..."><?= e($keteranganVal) ?></textarea>
                            </div>

                        </div>
                    </div>

                    <div class="modal-footer bg-white border-top py-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold">
                            <i class="bi bi-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===================================================================== -->
    <!-- MODAL BIOMETRIK SCAN WAJAH (FACE RECOGNITION AI)                      -->
    <!-- ===================================================================== -->
    <div class="modal fade" id="modalFaceVerify" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-dark text-white border-0 py-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-primary bg-opacity-25 rounded-circle text-primary fs-5">
                            <i class="bi bi-person-bounding-box"></i>
                        </div>
                        <div>
                            <h6 class="modal-title fw-bold mb-0">Verifikasi Biometrik Wajah Petugas</h6>
                            <div class="text-white-50 small" style="font-size: 0.72rem;">Otorisasi perubahan foto kartu inventaris BPR Mitratama</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" onclick="closeBiometricModal()"></button>
                </div>

                <div class="modal-body p-3 p-md-4 text-center bg-light">
                    <!-- Scanner Viewport -->
                    <div class="bio-scanner-wrapper mb-3">
                        <video id="bioVideo" class="bio-video-el" autoplay playsinline webkit-playsinline muted></video>
                        <canvas id="bioCanvas" class="bio-canvas-el"></canvas>
                        <div id="bioFaceOval" class="bio-face-oval"></div>
                        <div id="bioScanLine" class="bio-scanline d-none"></div>

                        <!-- Success Overlay -->
                        <div id="bioSuccessOverlay" class="bio-success-overlay d-none">
                            <div class="text-center p-3">
                                <div class="display-3 text-success mb-2">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <h5 class="fw-bold text-white mb-1" id="bioMatchedName">Nama Petugas</h5>
                                <div class="badge bg-success bg-opacity-75 fs-6 mb-2" id="bioMatchConfidence">98% Cocok</div>
                                <div class="text-white-50 small">Verifikasi Wajah Berhasil!<br>Menyimpan foto ke server...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Text -->
                    <div id="bioStatusBox" class="alert alert-info py-2 px-3 small fw-semibold mb-2">
                        <span class="spinner-border spinner-border-sm me-2 text-primary"></span>
                        Menyiapkan kamera & modul AI...
                    </div>

                    <!-- Tombol Verifikasi Cepat (Muncul saat wajah terdeteksi) -->
                    <div class="mb-2 d-none" id="bioInstantVerifyBox">
                        <button type="button" class="btn btn-warning text-dark btn-sm w-100 fw-bold py-2 shadow-sm rounded-pill" onclick="triggerInstantFaceVerify()">
                            <i class="bi bi-lightning-charge-fill me-1"></i> Wajah Terdeteksi &mdash; Tekan untuk Verifikasi Langsung
                        </button>
                    </div>

                    <!-- Checklist Indikator -->
                    <div class="d-flex justify-content-center gap-2 mb-3">
                        <span id="pillFaceDetected" class="badge bg-secondary bg-opacity-25 text-dark small py-1.5 px-3 border">
                            <i class="bi bi-person me-1"></i> Wajah
                        </span>
                        <span id="pillMatchId" class="badge bg-secondary bg-opacity-25 text-dark small py-1.5 px-3 border">
                            <i class="bi bi-check-all me-1"></i> Identitas Cocok
                        </span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="closeBiometricModal()">
                            Batal
                        </button>
                        <a href="<?= module_url('user_biometric_enroll.php') ?>" class="btn btn-link btn-sm text-decoration-none p-0 text-primary" style="font-size: 0.75rem;">
                            <i class="bi bi-camera-fill me-1"></i> Daftarkan Wajah Baru
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Pratinjau Foto Penuh -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4 overflow-hidden shadow-2xl">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title h6 fw-bold" id="imageModalTitle">Pratinjau Foto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-3">
                    <img src="" id="imageModalSrc" class="img-fluid rounded-3" style="max-height: 80vh;" alt="Foto Barang Penuh">
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Face API Library untuk Pengenalan Wajah -->
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.min.js"></script>

    <script>
        // Data Teknisi/Staf Terdaftar untuk Pencocokan Biometrik Wajah
        window.__ENROLLED_USERS = <?= json_encode($enrolledTechs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        const parsedEnrolledUsers = (Array.isArray(window.__ENROLLED_USERS) ? window.__ENROLLED_USERS : []).map(t => {
            let desc = t.descriptor || t.face_descriptor;
            if (typeof desc === "string") {
                try { desc = JSON.parse(desc); } catch (e) { desc = null; }
            }
            return {
                id: t.id,
                nama: t.nama,
                descriptor: (Array.isArray(desc) && desc.length >= 64) ? new Float32Array(desc) : null
            };
        }).filter(t => t.descriptor !== null);

        let bioModelsLoaded = false;
        let bioModelsLoading = false;
        let bioVideoStream = null;
        let bioTrackingTimer = null;
        let bioModalInstance = null;
        let bioCompleted = false;
        let bioFaceHoldFrames = 0;
        const HOLD_FRAMES_REQUIRED = 10;
        const MODEL_URL = "https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/";

        // 1. Kompresi Gambar Cerdas di Browser (Client-side) sebelum Unggah
        const fileInput = document.getElementById('fotoBarangInput');
        const previewImg = document.getElementById('previewImg');
        const emptyPlaceholder = document.getElementById('emptyPhotoPlaceholder');
        const previewBadge = document.getElementById('previewBadge');
        const compressBadge = document.getElementById('compressBadge');
        const photoWrapper = document.getElementById('photoWrapper');

        function compressImageClientSide(file, maxDimension, quality, callback) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    let w = img.width;
                    let h = img.height;

                    if (w > maxDimension || h > maxDimension) {
                        if (w >= h) {
                            h = Math.round((h / w) * maxDimension);
                            w = maxDimension;
                        } else {
                            w = Math.round((w / h) * maxDimension);
                            h = maxDimension;
                        }
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = w;
                    canvas.height = h;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, w, h);

                    canvas.toBlob(function(blob) {
                        callback(blob, canvas.toDataURL('image/jpeg', quality));
                    }, 'image/jpeg', quality);
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        if (fileInput) {
            fileInput.addEventListener('change', function() {
                const file = this.files && this.files[0];
                if (file) {
                    // Tampilkan badge kompresi
                    if (compressBadge) {
                        compressBadge.classList.remove('d-none');
                        compressBadge.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengompres...';
                    }

                    compressImageClientSide(file, 1600, 0.82, function(compressedBlob, previewDataUrl) {
                        if (previewImg) {
                            previewImg.src = previewDataUrl;
                            previewImg.classList.remove('d-none');
                        }
                        if (emptyPlaceholder) {
                            emptyPlaceholder.classList.add('d-none');
                        }
                        if (previewBadge) {
                            previewBadge.classList.remove('d-none');
                        }
                        if (photoWrapper) {
                            photoWrapper.classList.add('has-image');
                        }

                        // Gantikan payload input file dengan file terkompres (~150-250KB)
                        try {
                            const compressedFile = new File([compressedBlob], "compressed_" + file.name.replace(/\.[^.]+$/, ".jpg"), {
                                type: "image/jpeg",
                                lastModified: Date.now()
                            });
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(compressedFile);
                            fileInput.files = dataTransfer.files;

                            if (compressBadge) {
                                const kbSize = Math.round(compressedBlob.size / 1024);
                                compressBadge.innerHTML = '<i class="bi bi-shield-check me-1"></i> ' + kbSize + ' KB (Hemat Ruang)';
                            }
                        } catch (e) {
                            console.warn("DataTransfer update fallback:", e);
                        }
                    });
                }
            });
        }

        // 2. Tombol Otorisasi Wajah
        function startFaceAuthProcess() {
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                alert('Silakan pilih file foto barang atau jepret kamera terlebih dahulu sebelum melakukan verifikasi wajah!');
                fileInput.focus();
                return;
            }
            openBiometricModal();
        }

        function submitAsAuthorizedStaff() {
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                alert('Silakan pilih file foto barang terlebih dahulu!');
                fileInput.focus();
                return;
            }
            const btn = document.getElementById('btnMulaiScanWajah');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Menyimpan foto...';
            }
            document.getElementById('bioVerified').value = "1";
            document.getElementById('bioUserName').value = <?= json_encode(current_user_name() ?: 'Staf SDM & Umum') ?>;
            document.getElementById('uploadPhotoForm').submit();
        }

        function submitAsLoggedInAdmin() {
            submitAsAuthorizedStaff();
        }

        async function loadBioModels() {
            if (bioModelsLoaded) return true;
            if (bioModelsLoading) return true;
            bioModelsLoading = true;
            try {
                const statusBox = document.getElementById("bioStatusBox");
                if (statusBox && !bioCompleted) {
                    statusBox.className = "alert alert-info py-2 px-3 small fw-semibold mb-2";
                    statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-primary"></span> Menyiapkan modul AI pengenal wajah...';
                }
                if (typeof faceapi !== "undefined" && faceapi.tf) {
                    try {
                        await faceapi.tf.setBackend("webgl");
                        await faceapi.tf.ready();
                    } catch(e) {}
                }
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                    faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_URL).catch(() => faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL)),
                    faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
                ]);
                bioModelsLoaded = true;
                bioModelsLoading = false;
                if (statusBox && !bioCompleted) {
                    statusBox.className = "alert alert-success py-2 px-3 small fw-semibold mb-2";
                    statusBox.innerHTML = '<i class="bi bi-check-circle me-1"></i> Modul AI siap. Arahkan wajah ke oval.';
                }
                return true;
            } catch (err) {
                console.error("Gagal load face-api:", err);
                bioModelsLoading = false;
                const statusBox = document.getElementById("bioStatusBox");
                if (statusBox) {
                    statusBox.className = "alert alert-danger py-2 px-3 small mb-2";
                    statusBox.innerHTML = '<i class="bi bi-x-circle me-1"></i> Gagal memuat modul AI. Periksa jaringan internet.';
                }
                return false;
            }
        }

        function openBiometricModal() {
            const modalEl = document.getElementById("modalFaceVerify");
            if (!modalEl) return;

            bioCompleted = false;
            bioFaceHoldFrames = 0;

            document.getElementById("pillFaceDetected").className = "badge bg-secondary bg-opacity-25 text-dark small py-1.5 px-3 border";
            document.getElementById("pillMatchId").className = "badge bg-secondary bg-opacity-25 text-dark small py-1.5 px-3 border";
            document.getElementById("bioSuccessOverlay").classList.add("d-none");
            document.getElementById("bioScanLine").classList.add("d-none");
            document.getElementById("bioFaceOval").classList.remove("active");

            const instantBox = document.getElementById("bioInstantVerifyBox");
            if (instantBox) instantBox.classList.add("d-none");

            bioModalInstance = new bootstrap.Modal(modalEl);
            bioModalInstance.show();

            loadBioModels();
            startBioCamera();
        }

        function closeBiometricModal() {
            bioCompleted = true;
            if (bioTrackingTimer) {
                cancelAnimationFrame(bioTrackingTimer);
                bioTrackingTimer = null;
            }
            if (bioVideoStream) {
                try {
                    bioVideoStream.getTracks().forEach(t => t.stop());
                } catch(e) {}
                bioVideoStream = null;
            }
            if (bioModalInstance) {
                bioModalInstance.hide();
            }
        }

        async function startBioCamera() {
            const video = document.getElementById("bioVideo");
            const statusBox = document.getElementById("bioStatusBox");

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                if (window.location.protocol === "http:") {
                    const httpsUrl = "https://" + window.location.host + window.location.pathname + window.location.search;
                    if (statusBox) {
                        statusBox.className = "alert alert-warning py-2.5 px-3 small mb-2";
                        statusBox.innerHTML = '<i class="bi bi-shield-exclamation text-warning me-1"></i> Kamera live memerlukan protokol <strong>HTTPS</strong>.<br><a href="' + httpsUrl + '" class="btn btn-warning btn-sm fw-bold mt-1 text-dark shadow-xs"><i class="bi bi-box-arrow-up-right me-1"></i> Buka via HTTPS agar Kamera Aktif</a>';
                    }
                    return;
                }
            }

            try {
                if (statusBox && !bioModelsLoaded) {
                    statusBox.className = "alert alert-info py-2 px-3 small fw-semibold mb-2";
                    statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-info"></span> Mengaktifkan kamera selfie...';
                }

                video.setAttribute("playsinline", "");
                video.setAttribute("webkit-playsinline", "");

                bioVideoStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: "user",
                        width: { ideal: 640 },
                        height: { ideal: 480 },
                        frameRate: { ideal: 30, max: 30 }
                    },
                    audio: false
                });
                video.srcObject = bioVideoStream;
                await video.play();

                document.getElementById("bioScanLine").classList.remove("d-none");
                if (statusBox) {
                    statusBox.className = "alert alert-primary py-2 px-3 small fw-semibold mb-2";
                    statusBox.innerHTML = '<i class="bi bi-person-bounding-box me-1"></i> Posisikan wajah di lingkaran oval (Tahan 1 detik).';
                }

                startBioTracking();
            } catch (err) {
                console.error("Akses kamera gagal:", err);
                if (statusBox) {
                    statusBox.className = "alert alert-danger py-2 px-3 small mb-2";
                    statusBox.innerHTML = '<i class="bi bi-camera-video-off me-1"></i> Kamera tidak dapat diakses. Berikan izin di browser Anda.';
                }
            }
        }

        function calcEuclidean(a, b) {
            if (!a || !b || a.length !== b.length) return 999;
            let s = 0;
            for (let i = 0; i < a.length; i++) {
                const d = a[i] - b[i];
                s += d * d;
            }
            return Math.sqrt(s);
        }

        function captureBioSnapshot(videoEl) {
            try {
                if (!videoEl || !videoEl.videoWidth || !videoEl.videoHeight) return "";
                const c = document.createElement("canvas");
                const outSize = 180;
                c.width = outSize;
                c.height = outSize;
                const ctx = c.getContext("2d");
                ctx.imageSmoothingEnabled = true;
                const s = Math.min(videoEl.videoWidth, videoEl.videoHeight);
                const sx = (videoEl.videoWidth - s) / 2;
                const sy = (videoEl.videoHeight - s) / 2;
                ctx.translate(outSize, 0);
                ctx.scale(-1, 1);
                ctx.drawImage(videoEl, sx, sy, s, s, 0, 0, outSize, outSize);
                return c.toDataURL("image/jpeg", 0.65);
            } catch (err) {
                return "";
            }
        }

        function findBestMatch(queryVec) {
            let bestDist = 999;
            let bestUser = null;
            for (let i = 0; i < parsedEnrolledUsers.length; i++) {
                const u = parsedEnrolledUsers[i];
                const dist = calcEuclidean(queryVec, u.descriptor);
                if (dist < bestDist) {
                    bestDist = dist;
                    bestUser = u;
                }
            }
            return { user: bestUser, dist: bestDist };
        }

        function finalizeFaceVerification(user, dist, videoEl) {
            bioCompleted = true;
            document.getElementById("pillMatchId").className = "badge bg-success text-white small py-1.5 px-3 border";

            let conf = Math.round((1.0 - (dist / 0.60)) * 100);
            if (conf > 99) conf = 99;
            if (conf < 75) conf = 75;

            document.getElementById("bioVerified").value = "1";
            document.getElementById("bioUserId").value = user.id;
            document.getElementById("bioUserName").value = user.nama;
            document.getElementById("bioConfidence").value = conf;
            
            const snap = captureBioSnapshot(videoEl);
            document.getElementById("bioPhotoSnap").value = snap;

            const matchedNameEl = document.getElementById("bioMatchedName");
            if (matchedNameEl) matchedNameEl.textContent = user.nama;
            const matchConfEl = document.getElementById("bioMatchConfidence");
            if (matchConfEl) matchConfEl.textContent = conf + "% Cocok (Otorisasi Diterima)";
            
            const successOverlay = document.getElementById("bioSuccessOverlay");
            if (successOverlay) successOverlay.classList.remove("d-none");

            const statusBox = document.getElementById("bioStatusBox");
            if (statusBox) {
                statusBox.className = "alert alert-success py-2 px-3 small fw-bold mb-2";
                statusBox.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Wajah Terverifikasi: <strong>' + user.nama + '</strong>! Menyimpan foto...';
            }

            if (bioVideoStream) {
                try { bioVideoStream.getTracks().forEach(t => t.stop()); } catch(e) {}
            }

            const btn = document.getElementById('btnMulaiScanWajah');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Menyimpan Foto ke Server...';
            }

            setTimeout(() => {
                document.getElementById("uploadPhotoForm").submit();
            }, 600);
        }

        async function triggerInstantFaceVerify() {
            const video = document.getElementById("bioVideo");
            const statusBox = document.getElementById("bioStatusBox");
            if (!video || !video.videoWidth || bioCompleted) return;

            statusBox.className = "alert alert-warning py-2 px-3 small fw-bold mb-2";
            statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-warning"></span> Memproses pengenalan wajah...';

            const useTiny = faceapi.nets.faceLandmark68TinyNet && faceapi.nets.faceLandmark68TinyNet.isLoaded;
            const detectorOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.3 });

            try {
                const detection = await faceapi.detectSingleFace(video, detectorOptions)
                    .withFaceLandmarks(useTiny)
                    .withFaceDescriptor();

                if (detection && detection.descriptor) {
                    const matchResult = findBestMatch(detection.descriptor);
                    if (matchResult.user && matchResult.dist <= 0.55) {
                        finalizeFaceVerification(matchResult.user, matchResult.dist, video);
                        return;
                    }
                }
                statusBox.className = "alert alert-danger py-2 px-3 small fw-bold mb-2";
                statusBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Wajah belum cocok dengan staf resmi terdaftar. Posisikan wajah tegak.';
            } catch (err) {
                console.warn("Instant verify err:", err);
            }
        }

        function startBioTracking() {
            if (bioTrackingTimer) cancelAnimationFrame(bioTrackingTimer);
            const video = document.getElementById("bioVideo");
            const oval = document.getElementById("bioFaceOval");
            const statusBox = document.getElementById("bioStatusBox");
            const pillFace = document.getElementById("pillFaceDetected");
            const instantBox = document.getElementById("bioInstantVerifyBox");

            const useTiny = faceapi.nets.faceLandmark68TinyNet && faceapi.nets.faceLandmark68TinyNet.isLoaded;
            const detectorOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.30 });

            async function trackingLoop() {
                if (bioCompleted || !video || !video.videoWidth || video.paused || video.ended) {
                    if (!bioCompleted) {
                        bioTrackingTimer = requestAnimationFrame(trackingLoop);
                    }
                    return;
                }

                if (!bioModelsLoaded) {
                    bioTrackingTimer = requestAnimationFrame(trackingLoop);
                    return;
                }

                try {
                    const detection = await faceapi.detectSingleFace(video, detectorOptions)
                        .withFaceLandmarks(useTiny)
                        .withFaceDescriptor();

                    if (detection) {
                        oval.classList.add("active");
                        pillFace.className = "badge bg-success text-white small py-1.5 px-3 border";
                        if (instantBox) instantBox.classList.remove("d-none");

                        if (detection.descriptor) {
                            const matchResult = findBestMatch(detection.descriptor);
                            if (matchResult.user && matchResult.dist <= 0.55) {
                                bioFaceHoldFrames++;
                                if (statusBox) {
                                    statusBox.className = "alert alert-success py-2 px-3 small fw-semibold mb-2";
                                    statusBox.innerHTML = '<i class="bi bi-check-circle me-1"></i> Mengenali: <strong>' + matchResult.user.nama + '</strong>. Tahan sebentar...';
                                }

                                if (bioFaceHoldFrames >= HOLD_FRAMES_REQUIRED) {
                                    finalizeFaceVerification(matchResult.user, matchResult.dist, video);
                                    return;
                                }
                            } else {
                                bioFaceHoldFrames = Math.max(0, bioFaceHoldFrames - 1);
                                if (statusBox) {
                                    statusBox.className = "alert alert-warning py-2 px-3 small fw-semibold mb-2";
                                    statusBox.innerHTML = '<i class="bi bi-person-exclamation me-1"></i> Wajah terdeteksi, mencocokkan dengan data staf...';
                                }
                            }
                        }
                    } else {
                        oval.classList.remove("active");
                        pillFace.className = "badge bg-secondary bg-opacity-25 text-dark small py-1.5 px-3 border";
                        bioFaceHoldFrames = 0;
                        if (statusBox) {
                            statusBox.className = "alert alert-primary py-2 px-3 small fw-semibold mb-2";
                            statusBox.innerHTML = '<i class="bi bi-person-bounding-box me-1"></i> Arahkan wajah ke lingkaran oval.';
                        }
                    }
                } catch (e) {
                    console.warn("Tracking error:", e);
                }

                if (!bioCompleted) {
                    bioTrackingTimer = requestAnimationFrame(trackingLoop);
                }
            }

            bioTrackingTimer = requestAnimationFrame(trackingLoop);
        }

        // 3. Render QR Code Langsung ke Halaman Web Ini
        <?php if ($card): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var qrBox = document.getElementById('cardQrCode');
            if (qrBox) {
                new QRCode(qrBox, {
                    text: <?= json_encode($canonicalQrUrl) ?>,
                    width: 82,
                    height: 82,
                    colorDark: "#002B49",
                    colorLight: "#FFFFFF",
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        });
        <?php endif; ?>

        function viewFullImage(src, title) {
            if (!src) return;
            document.getElementById('imageModalSrc').src = src;
            document.getElementById('imageModalTitle').innerText = title || 'Foto Barang';
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }

        function copyCardLink() {
            navigator.clipboard.writeText(<?= json_encode($canonicalQrUrl) ?>).then(function() {
                alert('Tautan kartu inventaris berhasil disalin ke clipboard!');
            }).catch(function() {
                prompt('Salin tautan kartu ini:', <?= json_encode($canonicalQrUrl) ?>);
            });
        }
    </script>
</body>
</html>
