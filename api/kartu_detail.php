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

    if ($bioVerified !== '1' || empty($bioUserName)) {
        $dataError = "Perubahan data master ditolak! Wajib diverifikasi melalui pemindaian biometrik wajah staf/teknisi.";
    } else {
        $verifierLabel = $bioUserName . ($bioConfidence > 0 ? " ({$bioConfidence}% Cocok - Scan Wajah)" : " (Scan Wajah)");

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
            border-bottom: 2px solid #D4AF37;
            box-shadow: 0 4px 15px rgba(0, 27, 48, 0.25);
        }
        .status-pulse-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10B981;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: statusPulseGreen 1.8s infinite;
        }
        @keyframes statusPulseGreen {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .btn-navy-primary {
            background: linear-gradient(135deg, #002B49 0%, #003B66 100%) !important;
            color: #FFFFFF !important;
            border: 1px solid #001B30 !important;
            box-shadow: 0 2px 6px rgba(0, 43, 73, 0.2) !important;
            transition: all 0.2s ease;
        }
        .btn-navy-primary:hover {
            background: linear-gradient(135deg, #001B30 0%, #002B49 100%) !important;
            color: #FFFFFF !important;
            box-shadow: 0 4px 12px rgba(0, 43, 73, 0.35) !important;
            transform: translateY(-1px);
        }
        .btn-outline-neutral {
            color: #334155 !important;
            border: 1.5px solid #94A3B8 !important;
            background: #FFFFFF !important;
            transition: all 0.2s ease;
        }
        .btn-outline-neutral:hover {
            background: #1E293B !important;
            color: #FFFFFF !important;
            border-color: #1E293B !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.12) !important;
        }
        .copy-btn {
            font-size: 0.70rem;
            border: 1px solid #CBD5E1;
            background: #F8FAFC;
            color: #475569;
            padding: 2px 8px;
            line-height: 1.3;
            border-radius: 20px;
            transition: all 0.15s ease;
            cursor: pointer;
        }
        .copy-btn:hover {
            background: #E2E8F0;
            color: #0F172A;
            border-color: #94A3B8;
        }
        .photo-dropzone {
            border: 2px dashed #CBD5E1;
            border-radius: 12px;
            background: #FFFFFF;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .photo-dropzone:hover {
            border-color: #002B49;
            background: #F8FAFC;
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
            aspect-ratio: 4 / 5;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px dashed #CBD5E1;
            transition: all 0.2s ease;
            width: 100%;
            max-width: 320px;
            margin: 0 auto;
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
        .bank-data-table .td-label {
            width: 32%;
            min-width: 190px;
            white-space: nowrap;
            color: #53595F;
            font-weight: 700;
        }
        
        /* Gaya Biometric Scanner */
        .bio-scanner-wrapper {
            position: relative;
            width: 100%;
            max-width: 320px;
            aspect-ratio: 1 / 1;
            border-radius: 16px;
            overflow: hidden;
            background: #000;
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
            width: 72%;
            height: 85%;
            border: 2.5px dashed rgba(59, 130, 246, 0.85);
            border-radius: 50%;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.35);
            pointer-events: none;
            transition: all 0.3s ease;
        }
        .bio-face-oval.active {
            border: 2.5px solid #22c55e !important;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.35), 0 0 20px rgba(34, 197, 94, 0.6) !important;
        }
        @keyframes scanRayAnim {
            0% { top: 10%; opacity: 0.3; }
            50% { top: 85%; opacity: 1; }
            100% { top: 10%; opacity: 0.3; }
        }
        @keyframes bounceIn {
            0% { transform: scale(0.5); opacity: 0; }
            60% { transform: scale(1.1); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
        .animate-bounce {
            animation: bounceIn 0.5s ease-out;
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
            /* Stacked Card Layout on Smartphones: Never squish table columns! */
            .table-responsive {
                overflow: visible !important;
            }
            .bank-data-table,
            .bank-data-table tbody {
                display: block !important;
                width: 100% !important;
            }
            .bank-data-table tr {
                display: flex !important;
                flex-direction: column !important;
                padding: 11px 14px !important;
                border-bottom: 1px solid #E5E7EB !important;
                background: #FFFFFF !important;
            }
            .bank-data-table tr:nth-of-type(odd) {
                background-color: #F9FAFB !important;
            }
            .bank-data-table tr:last-child {
                border-bottom: none !important;
            }
            .bank-data-table td {
                display: block !important;
                width: 100% !important;
                padding: 0 !important;
                border: none !important;
                word-break: normal !important;
                overflow-wrap: break-word !important;
            }
            .bank-data-table td.td-label,
            .bank-data-table td:first-child {
                font-size: 0.68rem !important;
                font-weight: 700 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.05em !important;
                color: #53595F !important;
                margin-bottom: 5px !important;
                line-height: 1.25 !important;
                width: 100% !important;
                white-space: normal !important;
            }
            .bank-data-table td:last-child {
                font-size: 0.90rem !important;
                color: #1B2A3A !important;
                width: 100% !important;
            }
            .bank-data-table td:last-child .d-flex {
                width: 100% !important;
            }
            .bank-data-table .badge {
                font-size: 0.82rem !important;
                padding: 5px 12px !important;
                white-space: normal !important;
                text-align: left !important;
                line-height: 1.35 !important;
                max-width: 100% !important;
            }
            .bank-data-table .copy-btn {
                padding: 3px 10px !important;
                font-size: 0.72rem !important;
            }
            .item-photo-wrapper {
                border-radius: 12px;
                aspect-ratio: 4 / 5;
                max-width: 280px;
                margin: 0 auto;
            }
            .qr-card-box {
                margin: 0 auto;
            }
            .mobile-action-btn {
                width: 100% !important;
                height: 48px !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 10px !important;
                border: 1px solid #D1D5D8 !important;
                background: #FFFFFF !important;
                color: #1B2A3A !important;
                font-size: 0.72rem !important;
                font-weight: 600 !important;
                text-decoration: none !important;
                padding: 0 !important;
                transition: all 0.15s ease !important;
                box-shadow: 0 1px 2px rgba(0,0,0,0.04) !important;
            }
            .mobile-action-btn i {
                font-size: 1.15rem !important;
                line-height: 1 !important;
                margin-bottom: 2px !important;
            }
            .mobile-action-btn:active, .mobile-action-btn:hover {
                background: #F4F5F7 !important;
                border-color: #0E5C58 !important;
                transform: translateY(-1px) !important;
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
    <header class="header-brand py-2.5 px-3 px-md-4 shadow-sm" style="background: #0E5C58 !important;">
        <div class="container max-w-4xl d-flex align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2.5 min-w-0">
                <div class="bg-white p-1 rounded-[8px] shadow-2xs flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                    <img src="<?= module_url('logo_img.php') ?>" alt="Logo Bank Mitra" style="max-height: 26px; max-width: 26px; object-fit: contain;" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'32\' height=\'32\' viewBox=\'0 0 24 24\' fill=\'%230E5C58\'%3E%3Cpath d=\'M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5\'/%3E%3C/svg%3E';">
                </div>
                <div class="min-w-0">
                    <h1 class="h6 mb-0 text-white fw-bold tracking-tight text-truncate" style="font-size: 0.88rem;">BANK MITRA</h1>
                    <div class="small text-[#D9F1EF] text-truncate fw-medium" style="font-size: 0.70rem; letter-spacing: 0.3px;">KARTU INVENTARIS RESMI</div>
                </div>
            </div>
            <div class="flex-shrink-0">
                <?php if (is_logged_in()): ?>
                    <a href="<?= module_url('dashboard.php', ['tab' => 'kartu']) ?>" class="btn btn-outline-light btn-sm rounded-pill px-3 py-1 d-inline-flex align-items-center gap-1.5 fw-semibold" style="font-size: 0.76rem;">
                        <i class="bi bi-arrow-left"></i> <span class="d-none d-sm-inline">Dashboard</span><span class="d-sm-none">Menu</span>
                    </a>
                <?php else: ?>
                    <a href="<?= module_url('login.php') ?>" class="btn btn-outline-light btn-sm rounded-pill px-3 py-1 d-inline-flex align-items-center gap-1.5 fw-semibold" style="font-size: 0.76rem;">
                        <i class="bi bi-box-arrow-in-right"></i> <span>Login</span>
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
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2 py-0.5 d-inline-flex align-items-center gap-1.5 fw-bold" style="font-size: 0.70rem;"><span class="status-pulse-dot"></span><span><?= e($cleanStatus) ?></span></span>
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill font-mono" style="font-size: 0.68rem;">#<?= (int)$card['id'] ?></span>
                            </div>
                            <h2 class="h6 fw-bold text-dark mb-1 lh-sm" style="font-size: 0.95rem;"><?= e($card['nama_barang']) ?></h2>
                            <div class="font-mono text-primary fw-bold small mb-1" style="font-size: 0.78rem;"><?= e($kodeInventaris) ?></div>
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
                </div>

                <!-- Desktop Header Bar -->
                <div class="p-3 p-md-4 border-bottom bg-light bg-opacity-75 d-none d-md-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge badge-gold px-3 py-1.5 rounded-pill text-uppercase">
                            <i class="bi bi-shield-check me-1"></i> Data Master Inventaris
                        </span>
                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1.5 rounded-pill font-mono">
                            ID: #<?= (int)$card['id'] ?>
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="<?= module_url('inventaris_mutation.php', ['id' => $card['id']]) ?>" class="btn btn-outline-neutral btn-sm rounded-pill px-3 fw-semibold">
                            <i class="bi bi-arrow-left-right me-1"></i> Mutasi Barang
                        </a>
                        <button type="button" class="btn btn-outline-neutral btn-sm rounded-pill px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalEditDataKartu">
                            <i class="bi bi-pencil-square me-1"></i> Edit Data
                        </button>
                        <a href="<?= module_url('print_inventory_card.php', ['source' => 'inventaris_kartu', 'id' => $card['id']]) ?>" target="_blank" class="btn btn-navy-primary btn-sm rounded-pill px-3.5 fw-bold shadow-xs">
                            <i class="bi bi-printer-fill text-warning me-1"></i> Cetak CR80
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
                        <div class="d-flex align-items-center gap-3 flex-wrap flex-sm-nowrap">
                            <div class="qr-card-box shadow-xs">
                                <div id="cardQrCode"></div>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1 text-dark d-flex align-items-center gap-1.5 justify-content-center justify-content-md-start">
                                    <i class="bi bi-qr-code text-primary"></i> Barcode &amp; QR Code Resmi Bank
                                </h6>
                                <p class="text-secondary small mb-2" style="max-width: 480px; font-size: 0.78rem;">
                                    Hasil scan stiker fisik inventaris mengarah langsung ke paspor aset ini di server lokal BPR Mitratama.
                                </p>
                                <!-- Kotak Tautan QR Code Share Link Modern -->
                                <div class="input-group input-group-sm" style="max-width: 480px;">
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
                
                <form method="post" id="editDataKartuForm" action="<?= module_url('kartu_detail.php', ['id' => $card['id']]) ?>">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_card_data">
                    <input type="hidden" name="bio_verified" id="editBioVerified" value="0">
                    <input type="hidden" name="bio_user_id" id="editBioUserId" value="">
                    <input type="hidden" name="bio_user_name" id="editBioUserName" value="">
                    <input type="hidden" name="bio_confidence" id="editBioConfidence" value="">

                    <div class="modal-body p-4 bg-light">
                        <div class="row g-3">
                            
                            <!-- Kode Inventaris (Read Only) -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Kode Inventaris</label>
                                <input type="text" class="form-control form-control-sm font-mono bg-white fw-bold text-primary" value="<?= e($kodeInventaris) ?>" readonly>
                                <div class="form-text text-muted" style="font-size: 0.70rem;">Format resmi perbankan: <code>00.0.00000</code></div>
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

                            <!-- Ruangan Penempatan (Lokasi Fisik) -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Ruangan Penempatan (Lokasi Fisik)</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-geo-alt-fill text-warning"></i></span>
                                    <input type="text" name="ruangan_custom" id="ruanganInput" class="form-control font-mono" list="listLokasiPresets" value="<?= e($ruanganVal) ?>" placeholder="Contoh: 33 : RUANG KADIV KREDIT LT 4">
                                </div>
                                <datalist id="listLokasiPresets">
                                    <?php foreach ($masterRuangan as $r): ?>
                                        <option value="<?= e(trim(($r['kode_ruangan'] ?? '') . ' : ' . ($r['nama_ruangan'] ?? ''))) ?>"><?= e($r['nama_ruangan']) ?></option>
                                    <?php endforeach; ?>
                                </datalist>
                                <div class="form-text text-muted" style="font-size: 0.70rem;">Pilih dari daftar master ruangan (contoh: <code>33 : RUANG KADIV KREDIT LT 4</code>).</div>
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

                    <div class="modal-footer bg-white border-top py-2.5 px-3 d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-primary btn-sm rounded-pill px-3.5 fw-bold shadow-xs d-inline-flex align-items-center gap-1.5" onclick="startFaceAuthForEditData()">
                                <i class="bi bi-person-bounding-box fs-6"></i>
                                <span>Validasi Muka &amp; Simpan</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===================================================================== -->
    <!-- MODAL KONFIRMASI OTORISASI HAPUS FOTO                                 -->
    <!-- ===================================================================== -->
    <div class="modal fade" id="modalConfirmDeletePhoto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-danger text-white border-0 py-2.5 px-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-shield-exclamation fs-5"></i>
                        <h6 class="modal-title fw-bold mb-0">Otorisasi Hapus Foto</h6>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3 text-center">
                    <div class="text-danger mb-2">
                        <i class="bi bi-trash-fill fs-1"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">Kosongkan Foto Barang?</h6>
                    <p class="text-muted small mb-3" style="font-size: 0.75rem;">
                        Foto dokumentasi fisik <strong><?= e($card['nama_barang'] ?? 'Aset') ?></strong> akan dikosongkan. Sesuai standar kepatuhan bank, penghapusan foto wajib melalui verifikasi scan wajah staf atau otorisasi admin.
                    </p>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary btn-sm rounded-pill fw-bold py-2 shadow-xs d-flex align-items-center justify-content-center gap-2" onclick="startFaceDeleteProcess()">
                            <i class="bi bi-person-bounding-box fs-5"></i>
                            <span>Scan Wajah untuk Hapus</span>
                        </button>
                        <?php if (is_logged_in() && (is_admin() || is_hrd())): ?>
                            <button type="button" class="btn btn-outline-secondary btn-xs py-1.5 rounded-pill fw-semibold" onclick="submitDeleteAsAuthorizedStaff()">
                                <i class="bi bi-shield-check text-success me-1"></i> Hapus via Sesi <?= e(is_admin() ? 'Admin' : 'SDM & Umum') ?> (<?= e(current_user_name()) ?>)
                            </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-light btn-xs py-1.5 rounded-pill text-muted border" data-bs-dismiss="modal">
                            Batal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL BIOMETRIK SCAN WAJAH (FACE RECOGNITION AI)                      -->
    <!-- ===================================================================== -->
    <div class="modal fade" id="modalFaceVerify" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-xl rounded-[16px] overflow-hidden bg-white text-[#1B2A3A]">
                <div class="modal-header py-3 px-4 text-white" style="background: #0E5C58 !important;">
                    <div>
                        <h6 class="modal-title fw-bold text-white mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-person-bounding-box text-[#D9F1EF] fs-5"></i>
                            <span>Verifikasi Biometrik Wajah Petugas</span>
                        </h6>
                        <small class="text-[#D9F1EF] opacity-90 fw-medium d-block mt-0.5" style="font-size: 0.78rem;">Arahkan wajah Anda ke lingkaran pemindai kamera</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" onclick="closeBiometricModal()"></button>
                </div>

                <div class="modal-body p-3.5 text-center position-relative bg-white">
                    <!-- Scanner Viewport -->
                    <div class="bio-scanner-wrapper position-relative mx-auto rounded-3 overflow-hidden bg-black shadow-inner" style="max-width: 320px; aspect-ratio: 1/1;">
                        <video id="bioVideo" class="bio-video-el w-100 h-100 object-fit-cover" autoplay playsinline webkit-playsinline muted style="transform: scaleX(-1);"></video>
                        <canvas id="bioCanvas" class="bio-canvas-el position-absolute top-0 start-0 w-100 h-100 pointer-events-none" style="transform: scaleX(-1);"></canvas>
                        
                        <!-- Oval Guide Frame -->
                        <div id="bioFaceOval" class="position-absolute top-50 start-50 translate-middle pointer-events-none bio-face-oval" style="width: 72%; height: 85%; border: 2.5px dashed #0E5C58; border-radius: 50%; box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.45);"></div>
                        
                        <!-- Scanning Ray Animation -->
                        <div id="bioScanLine" class="position-absolute start-0 w-100" style="height: 3px; background: linear-gradient(90deg, transparent, #0E5C58, #2DD4BF, transparent); animation: scanRayAnim 2s infinite ease-in-out;"></div>

                        <!-- Success Overlay -->
                        <div id="bioSuccessOverlay" class="position-absolute top-0 start-0 w-100 h-100 bg-success bg-opacity-75 d-flex flex-column align-items-center justify-content-center text-white d-none" style="backdrop-filter: blur(4px);">
                            <i class="bi bi-check-circle-fill display-3 mb-2 animate-bounce"></i>
                            <h5 class="fw-bold text-white mb-1" id="bioMatchedName">Wajah Terverifikasi!</h5>
                            <span class="badge bg-white text-success fw-bold px-3 py-1 rounded-pill" id="bioMatchConfidence">100% Cocok</span>
                        </div>
                    </div>

                    <!-- Status Text -->
                    <div id="bioStatusBox" class="alert alert-info py-2 px-3 small fw-bold mt-3 mb-2 text-start text-[#0E5C58] bg-[#D9F1EF] border border-[#A7E2DC]">
                        <span class="spinner-border spinner-border-sm me-2 text-[#0E5C58]"></span>
                        Menyiapkan kamera dan modul pengenalan wajah...
                    </div>

                    <div class="text-[#1B2A3A] small mb-2.5 fw-semibold" style="font-size: 0.80rem; line-height: 1.4;">
                        Pastikan wajah berada di dalam oval, tidak memakai masker, dan pencahayaan memadai.
                    </div>

                    <!-- Tombol Verifikasi Cepat (Muncul saat wajah terdeteksi) -->
                    <div class="mb-2 d-none" id="bioInstantVerifyBox"></div>

                    <!-- Checklist Indikator -->
                    <div class="d-flex justify-content-center gap-2 mb-2">
                        <span id="pillFaceDetected" class="badge bg-[#F1F5F9] text-[#475569] small py-1.5 px-3 border border-[#CBD5E1] fw-semibold">
                            <i class="bi bi-person me-1"></i> Wajah Terdeteksi
                        </span>
                        <span id="pillMatchId" class="badge bg-[#F1F5F9] text-[#475569] small py-1.5 px-3 border border-[#CBD5E1] fw-semibold">
                            <i class="bi bi-check-all me-1"></i> Identitas Cocok
                        </span>
                    </div>

                    <div class="mt-2.5 text-center text-[#53595F] small" style="font-size: 0.78rem;">
                        <i class="bi bi-camera me-1"></i> Belum rekam wajah? <a href="<?= module_url('user_biometric_enroll.php') ?>" class="text-[#0E5C58] fw-bold text-decoration-underline">Daftarkan di sini</a>
                    </div>
                </div>

                <div class="modal-footer bg-[#F8FAFC] border-top border-[#E2E8F0] py-2.5 px-3.5 d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3.5 fw-semibold" onclick="closeBiometricModal()">
                        Batal
                    </button>
                    <button type="button" id="btnManualTriggerFace" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-xs d-inline-flex align-items-center gap-1.5" onclick="triggerInstantFaceVerify()">
                        <i class="bi bi-camera-fill"></i>
                        <span>Verifikasi Sekarang</span>
                    </button>
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
    <script src="<?= module_url('assets/vendor/bootstrap.bundle.min.js') ?>"></script>
    
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

        // Kompresi & Auto-Crop Cerdas Rasio 4:5 (Width 4 : Height 5)
        function compressImageClientSide(file, maxDimension, quality, callback) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const targetRatio = 4 / 5; // 0.8
                    let cropW = img.width;
                    let cropH = img.height;
                    let sx = 0;
                    let sy = 0;

                    if (img.width / img.height > targetRatio) {
                        // Foto lebih lebar dari 4:5 -> potong sisi kiri & kanan (center crop)
                        cropW = Math.round(img.height * targetRatio);
                        cropH = img.height;
                        sx = Math.round((img.width - cropW) / 2);
                        sy = 0;
                    } else {
                        // Foto lebih tinggi dari 4:5 -> potong sisi atas & bawah (center crop)
                        cropW = img.width;
                        cropH = Math.round(img.width / targetRatio);
                        sx = 0;
                        sy = Math.round((img.height - cropH) / 2);
                    }

                    // Skala resolusi output dengan rasio tepat 4:5 (maksimal 1200 x 1500)
                    let outW = 1200;
                    let outH = 1500;
                    if (cropW < outW) {
                        outW = cropW;
                        outH = Math.round(outW / targetRatio);
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = outW;
                    canvas.height = outH;
                    const ctx = canvas.getContext('2d');
                    ctx.imageSmoothingEnabled = true;
                    ctx.imageSmoothingQuality = 'high';
                    ctx.drawImage(img, sx, sy, cropW, cropH, 0, 0, outW, outH);

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

        // 2. Tombol Otorisasi Wajah (Upload / Hapus / Edit Data Master)
        window.faceAuthMode = 'upload';

        function startFaceAuthForEditData() {
            var form = document.getElementById("editDataKartuForm");
            if (form && !form.checkValidity()) {
                form.reportValidity();
                return;
            }
            var modalEditEl = document.getElementById("modalEditDataKartu");
            if (modalEditEl) {
                try {
                    var m = (typeof bootstrap !== 'undefined' && bootstrap.Modal)
                        ? (bootstrap.Modal.getInstance ? bootstrap.Modal.getInstance(modalEditEl) : null)
                        : null;
                    if (m) m.hide();
                } catch(e) {}
            }
            window.faceAuthMode = 'edit_data';
            openBiometricModal();
        }

        function startFaceAuthProcess() {
            window.faceAuthMode = 'upload';
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                alert('Silakan pilih file foto barang atau jepret kamera terlebih dahulu sebelum melakukan verifikasi wajah!');
                fileInput.focus();
                return;
            }
            openBiometricModal();
        }

        function openDeletePhotoConfirmModal(event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            var modalEl = document.getElementById("modalConfirmDeletePhoto");
            if (modalEl) {
                try {
                    var m = (typeof bootstrap !== 'undefined' && bootstrap.Modal)
                        ? (bootstrap.Modal.getOrCreateInstance ? bootstrap.Modal.getOrCreateInstance(modalEl) : new bootstrap.Modal(modalEl))
                        : null;
                    if (m) m.show();
                } catch(e) {
                    console.warn("Modal error:", e);
                }
            }
        }

        function startFaceDeleteProcess() {
            var modalEl = document.getElementById("modalConfirmDeletePhoto");
            if (modalEl) {
                try {
                    var m = (typeof bootstrap !== 'undefined' && bootstrap.Modal)
                        ? (bootstrap.Modal.getInstance ? bootstrap.Modal.getInstance(modalEl) : null)
                        : null;
                    if (m) m.hide();
                } catch(e) {}
            }
            window.faceAuthMode = 'delete';
            openBiometricModal();
        }

        function submitDeleteAsAuthorizedStaff() {
            if (!confirm('Konfirmasi: Anda login sebagai Admin/SDM. Lanjutkan menghapus foto inventaris ini?')) {
                return;
            }
            document.getElementById("delBioVerified").value = "1";
            document.getElementById("delBioUserName").value = <?= json_encode(current_user_name() ?: 'Admin / SDM') ?>;
            document.getElementById("deletePhotoForm").submit();
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
            const statusBox = document.getElementById("bioStatusBox");
            try {
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

            document.getElementById("pillFaceDetected").className = "badge bg-[#F1F5F9] text-[#475569] small py-1.5 px-3 border border-[#CBD5E1] fw-semibold";
            document.getElementById("pillMatchId").className = "badge bg-[#F1F5F9] text-[#475569] small py-1.5 px-3 border border-[#CBD5E1] fw-semibold";
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
            document.getElementById("pillMatchId").className = "badge bg-success text-white small py-1.5 px-3 border border-success fw-bold";

            let conf = Math.round((1.0 - (dist / 0.60)) * 100);
            if (conf > 99) conf = 99;
            if (conf < 75) conf = 75;

            const matchedNameEl = document.getElementById("bioMatchedName");
            const matchConfEl = document.getElementById("bioMatchConfidence");
            const successOverlay = document.getElementById("bioSuccessOverlay");
            const statusBox = document.getElementById("bioStatusBox");

            // JIKA DALAM MODE EDIT DATA MASTER INVENTARIS
            if (window.faceAuthMode === 'edit_data') {
                document.getElementById("editBioVerified").value = "1";
                document.getElementById("editBioUserId").value = user.id;
                document.getElementById("editBioUserName").value = user.nama;
                document.getElementById("editBioConfidence").value = conf;

                if (matchedNameEl) matchedNameEl.textContent = user.nama;
                if (matchConfEl) matchConfEl.textContent = conf + "% Cocok";
                if (successOverlay) successOverlay.classList.remove("d-none");

                if (statusBox) {
                    statusBox.className = "alert alert-success py-2 px-3 small fw-bold mb-2";
                    statusBox.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Otorisasi Wajah Diterima: <strong>' + user.nama + '</strong>! Menyimpan perubahan data...';
                }

                if (bioVideoStream) {
                    try { bioVideoStream.getTracks().forEach(t => t.stop()); } catch(e) {}
                }

                setTimeout(() => {
                    document.getElementById("editDataKartuForm").submit();
                }, 600);
                return;
            }

            // JIKA DALAM MODE HAPUS FOTO
            if (window.faceAuthMode === 'delete') {
                document.getElementById("delBioVerified").value = "1";
                document.getElementById("delBioUserId").value = user.id;
                document.getElementById("delBioUserName").value = user.nama;
                document.getElementById("delBioConfidence").value = conf;

                if (matchedNameEl) matchedNameEl.textContent = user.nama;
                if (matchConfEl) matchConfEl.textContent = conf + "% Cocok";
                if (successOverlay) successOverlay.classList.remove("d-none");

                if (statusBox) {
                    statusBox.className = "alert alert-success py-2 px-3 small fw-bold mb-2";
                    statusBox.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Otorisasi Hapus Diterima: <strong>' + user.nama + '</strong>! Mengosongkan foto...';
                }

                if (bioVideoStream) {
                    try { bioVideoStream.getTracks().forEach(t => t.stop()); } catch(e) {}
                }

                setTimeout(() => {
                    document.getElementById("deletePhotoForm").submit();
                }, 600);
                return;
            }

            document.getElementById("bioVerified").value = "1";
            document.getElementById("bioUserId").value = user.id;
            document.getElementById("bioUserName").value = user.nama;
            document.getElementById("bioConfidence").value = conf;
            
            const snap = captureBioSnapshot(videoEl);
            document.getElementById("bioPhotoSnap").value = snap;

            if (matchedNameEl) matchedNameEl.textContent = user.nama;
            if (matchConfEl) matchConfEl.textContent = conf + "% Cocok";
            if (successOverlay) successOverlay.classList.remove("d-none");
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
                        pillFace.className = "badge bg-success text-white small py-1.5 px-3 border border-success fw-bold";
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
                        pillFace.className = "badge bg-[#F1F5F9] text-[#475569] small py-1.5 px-3 border border-[#CBD5E1] fw-semibold";
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

                function copyText(text, btnEl) {
            function onSuccess() {
                if (!btnEl) return;
                const oldHtml = btnEl.innerHTML;
                btnEl.innerHTML = '<i class="bi bi-check2 text-success me-1"></i>Tersalin!';
                btnEl.classList.add('border-success', 'text-success');
                setTimeout(() => {
                    btnEl.innerHTML = oldHtml;
                    btnEl.classList.remove('border-success', 'text-success');
                }, 1500);
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(onSuccess).catch(() => fallback(text, onSuccess));
            } else {
                fallback(text, onSuccess);
            }
            function fallback(t, cb) {
                const ta = document.createElement('textarea');
                ta.value = t;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try {
                    document.execCommand('copy');
                    cb();
                } catch(e) {
                    alert('Tersalin: ' + t);
                }
                document.body.removeChild(ta);
            }
        }

        function copyCardUrlDirect(btnEl) {
            const input = document.getElementById('inputCardUrl');
            const url = input ? input.value : window.location.href;
            copyText(url, btnEl);
        }

        function handleFileSelect(input) {
            if (!input || !input.files || input.files.length === 0) return;
            const f = input.files[0];
            const emptyBox = document.getElementById('dropzoneEmpty');
            const selectedBox = document.getElementById('dropzoneSelected');
            const nameEl = document.getElementById('selectedFileName');
            const sizeEl = document.getElementById('selectedFileSize');

            if (emptyBox) emptyBox.classList.add('d-none');
            if (selectedBox) selectedBox.classList.remove('d-none');
            if (nameEl) nameEl.textContent = f.name;
            if (sizeEl) sizeEl.textContent = (f.size / 1024).toFixed(1) + ' KB';
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
