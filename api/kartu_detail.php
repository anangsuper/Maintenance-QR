<?php
/**
 * HALAMAN DETAIL KARTU INVENTARIS RESMI (PASPOR ASET & BARANG)
 * 
 * Halaman tujuan ketika QR Code pada stiker kartu fisik di-scan.
 * Menampilkan rincian lengkap barang, lokasi, nomor rekening, dan foto barang.
 * Menggantikan link eksternal Canva secara permanen.
 * 
 * Akses: https://192.168.88.163/kartu_detail.php?id=...
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/inventaris_kartu.php';

$cardId = (int)($_GET['id'] ?? 0);
$rekParam = trim((string)($_GET['rek'] ?? $_GET['no_rek'] ?? ''));

$card = null;
if ($cardId > 0) {
    $card = get_inventaris_kartu_by_id($cardId);
} elseif ($rekParam !== '') {
    $card = get_inventaris_kartu_by_norek($rekParam);
}

// Handle Upload / Ganti Foto Barang
$uploadSuccess = null;
$uploadError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_foto' && $card) {
    verify_csrf();
    if (!empty($_FILES['foto_barang']['name'])) {
        $file = $_FILES['foto_barang'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $maxSize = 25 * 1024 * 1024; // 25MB
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
                $fileName = 'kartu_' . $card['id'] . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    @chmod($targetPath, 0666);
                    $relativePhoto = 'uploads/assets/' . $fileName;
                    
                    // Simpan ke database
                    update_inventaris_kartu_foto((int)$card['id'], $relativePhoto);
                    $card['foto'] = $relativePhoto;
                    $uploadSuccess = "Foto barang berhasil disimpan!";
                } else {
                    $uploadError = "Gagal memindahkan file foto ke server.";
                }
            }
        } else {
            $uploadError = "Terjadi kesalahan saat mengunggah foto (kode: " . $file['error'] . ").";
        }
    } else {
        $uploadError = "Silakan pilih foto terlebih dahulu.";
    }
}

// Cek apakah ada kecocokan data dengan tabel master IT assets
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

// Resolusi foto final
$displayPhoto = null;
if (!empty($card['foto'])) {
    $displayPhoto = $card['foto'];
} elseif (!empty($matchedAsset['foto'])) {
    $displayPhoto = $matchedAsset['foto'];
}

$photoUrl = '';
if ($displayPhoto) {
    $photoUrl = str_starts_with($displayPhoto, 'http') ? $displayPhoto : module_url($displayPhoto);
}

// QR Code URL resmi untuk kartu ini
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$canonicalQrUrl = $card ? module_url('kartu_detail.php', ['id' => $card['id']]) : $currentUrl;

$pageTitle = $card ? e($card['nama_barang']) . ' - Kartu Inventaris' : 'Kartu Inventaris Tidak Ditemukan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> &bull; BPR Mitratama</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <!-- QRCode.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        :root {
            --app-navy: #002B49;
            --app-gold: #C5A059;
            --app-gold-light: #F7F3E9;
            --app-bg: #F8FAFC;
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
            font-size: 0.82rem;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .value-cell {
            font-weight: 700;
            color: #0F172A;
            font-size: 0.98rem;
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
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #CBD5E1;
            display: inline-block;
        }
    </style>
</head>
<body>

    <!-- Header App -->
    <header class="header-brand py-3 px-4 shadow-sm">
        <div class="container max-w-4xl d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white p-2 rounded-3 shadow-xs">
                    <img src="<?= module_url('logo_img.php') ?>" alt="Logo Bank Mitra" style="height: 32px;" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'32\' height=\'32\' viewBox=\'0 0 24 24\' fill=\'%23002B49\'%3E%3Cpath d=\'M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5\'/%3E%3C/svg%3E';">
                </div>
                <div>
                    <h1 class="h6 mb-0 text-white fw-bold tracking-tight">PT. BPR MITRATAMA ARTHABUANA</h1>
                    <div class="small text-white-50" style="font-size: 0.72rem; letter-spacing: 0.5px;">SISTEM VERIFIKASI KARTU INVENTARIS RESMI</div>
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

    <main class="container py-4" style="max-w: 860px; max-width: 860px;">

        <?php if ($uploadSuccess): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-xs mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5 align-middle"></i>
                <strong>Berhasil!</strong> <?= e($uploadSuccess) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($uploadError): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-xs mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5 align-middle"></i>
                <strong>Perhatian:</strong> <?= e($uploadError) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!$card): ?>
            <!-- Card Not Found View -->
            <div class="card card-custom p-5 text-center">
                <div class="display-1 text-muted mb-3"><i class="bi bi-search"></i></div>
                <h2 class="h4 fw-bold text-danger mb-2">Kartu Inventaris Tidak Ditemukan</h2>
                <p class="text-secondary mb-4">
                    Nomor rekening atau ID kartu yang Anda scan tidak terdaftar pada database sistem inventaris.
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="<?= module_url('dashboard.php', ['tab' => 'kartu']) ?>" class="btn btn-primary rounded-pill px-4">
                        <i class="bi bi-grid-fill me-1"></i> Buka Daftar Kartu
                    </a>
                </div>
            </div>
        <?php else: ?>

            <?php
                $rek = $card['nomor_rekening'];
                $tglFormatted = format_indo_date($card['tanggal_perolehan']);
                $nomorGabungan = get_nomor_asset_gabungan($rek, $card['tanggal_perolehan']);
                $cBranch = get_cabang_from_nomor_rekening($rek);
                $namaCabang = $cBranch ? $cBranch['name'] : 'Kantor Pusat Operasional';
            ?>

            <!-- Passport Identity Card -->
            <div class="card card-custom mb-4">
                <div class="p-3 p-md-4 border-bottom bg-light bg-opacity-50 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge badge-gold px-3 py-1.5 rounded-pill text-uppercase">
                            <i class="bi bi-shield-check me-1"></i> Terverifikasi Resmi
                        </span>
                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1.5 rounded-pill font-mono">
                            ID: #<?= (int)$card['id'] ?>
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="<?= module_url('print_inventory_card.php', ['source' => 'inventaris_kartu', 'id' => $card['id']]) ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold">
                            <i class="bi bi-printer me-1"></i> Cetak Kartu CR80
                        </a>
                    </div>
                </div>

                <div class="p-4 p-md-5">
                    <div class="row g-4 align-items-center">
                        
                        <!-- Kolom Foto Barang -->
                        <div class="col-md-5 text-center">
                            <div class="item-photo-wrapper mb-3 shadow-xs">
                                <?php if ($photoUrl): ?>
                                    <img src="<?= e($photoUrl) ?>" alt="Foto <?= e($card['nama_barang']) ?>" onclick="viewFullImage('<?= e($photoUrl) ?>', '<?= e(addslashes($card['nama_barang'])) ?>')" title="Klik untuk memperbesar">
                                <?php else: ?>
                                    <div class="text-center p-4">
                                        <i class="bi bi-camera text-muted display-4 mb-2 d-block"></i>
                                        <div class="small text-secondary fw-semibold">Belum Ada Foto Barang</div>
                                        <div class="text-muted" style="font-size: 0.72rem;">Klik tombol di bawah untuk upload</div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Tombol Upload Foto -->
                            <button type="button" class="btn btn-sm btn-outline-secondary w-100 rounded-pill py-2 fw-semibold" data-bs-toggle="collapse" data-bs-target="#uploadPhotoSection">
                                <i class="bi bi-camera me-1"></i> <?= $photoUrl ? 'Ganti Foto Barang' : 'Ambil / Upload Foto' ?>
                            </button>

                            <div class="collapse mt-3 text-start" id="uploadPhotoSection">
                                <div class="bg-light p-3 rounded-3 border">
                                    <form method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="upload_foto">
                                        
                                        <label class="form-label small fw-bold text-secondary mb-1">Pilih / Jepret Foto:</label>
                                        <input type="file" name="foto_barang" class="form-control form-control-sm mb-2" accept="image/*" capture="environment" required>
                                        
                                        <button type="submit" class="btn btn-primary btn-sm w-100 rounded-2 fw-semibold">
                                            <i class="bi bi-cloud-arrow-up me-1"></i> Simpan Foto
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Kolom Spesifikasi & Detail -->
                        <div class="col-md-7">
                            <div class="mb-4">
                                <div class="label-cell mb-1 text-primary">NAMA BARANG / PERANGKAT</div>
                                <h2 class="h3 fw-bold text-dark mb-0"><?= e($card['nama_barang']) ?></h2>
                            </div>

                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="label-cell">Nomor Rekening</div>
                                    <div class="value-cell font-mono text-primary fs-5"><?= e($rek) ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="label-cell">Nomor Gabungan Aset</div>
                                    <div class="value-cell font-mono text-dark"><?= e($nomorGabungan) ?></div>
                                </div>
                                
                                <div class="col-6">
                                    <div class="label-cell">Tanggal Perolehan</div>
                                    <div class="value-cell"><?= e($tglFormatted) ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="label-cell">Kantor Cabang</div>
                                    <div class="value-cell text-dark"><i class="bi bi-buildings me-1 text-muted"></i><?= e($namaCabang) ?></div>
                                </div>

                                <div class="col-6">
                                    <div class="label-cell">Lokasi Penempatan</div>
                                    <div class="value-cell"><?= e($card['lokasi'] ?: 'Kantor Pusat (KPO)') ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="label-cell">Pengguna / Pengelola</div>
                                    <div class="value-cell"><?= e($card['pengguna'] ?: 'Umum / Pool') ?></div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Bagian Bawah: QR Code Generator & Info Fisik -->
                <div class="p-4 bg-light border-top">
                    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-3 text-center text-sm-start">
                        <div class="d-flex align-items-center gap-3">
                            <div class="qr-card-box shadow-xs">
                                <div id="cardQrCode"></div>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1 text-dark">Barcode & QR Code Resmi</h6>
                                <p class="text-secondary small mb-1" style="max-width: 380px;">
                                    Stiker QR fisik kartu ini langsung terhubung ke alamat web ini. Tidak lagi dialihkan ke pihak ketiga atau Canva.
                                </p>
                                <div class="small font-mono text-muted text-break" style="font-size: 0.72rem;">
                                    <?= e($canonicalQrUrl) ?>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light border btn-sm px-3 rounded-pill fw-semibold shadow-xs" onclick="copyCardLink()">
                                <i class="bi bi-share me-1"></i> Salin Link
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($matchedAsset): ?>
                <!-- Informasi Tambahan Aset IT (Jika Terkait Komputer/Printer IT) -->
                <div class="card card-custom p-4 mb-4 border-primary border-opacity-25 shadow-sm">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge bg-primary text-white p-2 rounded-2"><i class="bi bi-hdd-network"></i></span>
                        <div>
                            <h5 class="h6 mb-0 fw-bold text-dark">Integrasi Spesifikasi Master IT</h5>
                            <div class="text-muted small">Data teknis perangkat tersinkron dari Master Hardware IT</div>
                        </div>
                    </div>
                    <div class="row g-3 small">
                        <div class="col-sm-3 col-6">
                            <span class="text-secondary d-block">Merk & Model:</span>
                            <strong><?= e(trim(($matchedAsset['merk'] ?? '') . ' ' . ($matchedAsset['model'] ?? '')) ?: '-') ?></strong>
                        </div>
                        <div class="col-sm-3 col-6">
                            <span class="text-secondary d-block">Serial Number:</span>
                            <span class="font-mono fw-bold"><?= e($matchedAsset['serial_number'] ?: '-') ?></span>
                        </div>
                        <div class="col-sm-3 col-6">
                            <span class="text-secondary d-block">Alamat IP:</span>
                            <span class="font-mono"><?= e($matchedAsset['ip_address'] ?: '-') ?></span>
                        </div>
                        <div class="col-sm-3 col-6">
                            <span class="text-secondary d-block">Perangkat Printer:</span>
                            <span><?= e($matchedAsset['printer'] ?: '-') ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </main>

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

    <script>
        // Render QR Code Langsung ke Halaman Web Ini
        <?php if ($card): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var qrBox = document.getElementById('cardQrCode');
            if (qrBox) {
                new QRCode(qrBox, {
                    text: <?= json_encode($canonicalQrUrl) ?>,
                    width: 84,
                    height: 84,
                    colorDark: "#002B49",
                    colorLight: "#FFFFFF",
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        });
        <?php endif; ?>

        function viewFullImage(src, title) {
            document.getElementById('imageModalSrc').src = src;
            document.getElementById('imageModalTitle').innerText = title || 'Foto Barang';
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }

        function copyCardLink() {
            navigator.clipboard.writeText(<?= json_encode($canonicalQrUrl) ?>).then(function() {
                alert('Tautan halaman kartu berhasil disalin ke clipboard!');
            }).catch(function() {
                prompt('Salin tautan kartu ini:', <?= json_encode($canonicalQrUrl) ?>);
            });
        }
    </script>
</body>
</html>
