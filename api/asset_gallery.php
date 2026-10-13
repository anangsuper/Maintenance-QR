<?php
require __DIR__ . '/bootstrap.php';
require_login();


// 1. Ambil Parameter Filter
$search = trim((string)($_GET['q'] ?? ''));
$cabangId = max(0, (int)($_GET['cabang'] ?? 0));
$divisiId = max(0, (int)($_GET['divisi'] ?? 0));
$photoFilter = trim((string)($_GET['photo'] ?? 'all')); // 'all', 'with_photo', 'no_photo'
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24; // 24 kartu per halaman agar grid 3/4 kolom rapi

// 2. Ambil Master Data
$cabangs = get_cabang_list();
$divisis = get_divisi_list();

// 3. Ambil Semua Data Aset
$rawAssets = [];
if (is_google_cloud_mode()) {
    $client = google_sheets_v4_client();
    if ($client) {
        $client->preloadSheets(['Assets', 'Cabang', 'Divisi', 'Karyawan', 'Kategori_Aset', 'Asset_QR_Tokens']);
    }
    $rawAssets = map_sheets_assets();
} else {
    try {
        $st = db()->query(asset_query_base() . " ORDER BY a.id DESC LIMIT 3000");
        $rawAssets = $st ? $st->fetchAll() : [];
    } catch (Throwable $e) {
        $rawAssets = [];
    }
}

// Hitung Statistik Foto
$totalAll = count($rawAssets);
$totalWithPhoto = 0;
$totalNoPhoto = 0;
$branchPhotoCounts = [];

foreach ($rawAssets as $a) {
    $cId = (int)($a['id_cabang'] ?? 0);
    $hasFoto = !empty(trim((string)($a['foto'] ?? '')));
    if ($hasFoto) {
        $totalWithPhoto++;
        $branchPhotoCounts[$cId] = ($branchPhotoCounts[$cId] ?? 0) + 1;
    } else {
        $totalNoPhoto++;
    }
}


// 4. Filter Data Aset Sesuai Pilihan Pengguna
$filteredAssets = array_filter($rawAssets, function($a) use ($search, $cabangId, $divisiId, $photoFilter) {
    if ($cabangId > 0 && (int)($a['id_cabang'] ?? 0) !== $cabangId) {
        return false;
    }
    if ($divisiId > 0 && (int)($a['id_divisi'] ?? 0) !== $divisiId) {
        return false;
    }
    
    $hasFoto = !empty(trim((string)($a['foto'] ?? '')));
    if ($photoFilter === 'with_photo' && !$hasFoto) {
        return false;
    }
    if ($photoFilter === 'no_photo' && $hasFoto) {
        return false;
    }

    if ($search !== '') {
        $q = strtolower($search);
        $haystack = strtolower(implode(' ', [
            $a['kode_inventaris'] ?? '',
            $a['merk'] ?? '',
            $a['model'] ?? '',
            $a['serial_number'] ?? '',
            $a['karyawan_nama'] ?? '',
            $a['cabang_nama'] ?? '',
            $a['divisi_nama'] ?? '',
            $a['kategori_nama'] ?? '',
            $a['ip_address'] ?? $a['ip'] ?? ''
        ]));
        if (strpos($haystack, $q) === false) {
            return false;
        }
    }

    return true;
});

// Urutkan: Aset yang memiliki foto di depan, lalu berdasarkan Cabang & Kode
usort($filteredAssets, function($a, $b) {
    $hasA = !empty(trim((string)($a['foto'] ?? ''))) ? 1 : 0;
    $hasB = !empty(trim((string)($b['foto'] ?? ''))) ? 1 : 0;
    if ($hasA !== $hasB) {
        return $hasB <=> $hasA; // yang punya foto tampil pertama
    }
    $cb = strcasecmp((string)($a['cabang_nama'] ?? ''), (string)($b['cabang_nama'] ?? ''));
    if ($cb !== 0) return $cb;
    return strcasecmp((string)($a['kode_inventaris'] ?? ''), (string)($b['kode_inventaris'] ?? ''));
});

// 5. Pagination
$totalFiltered = count($filteredAssets);
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;
$pageAssets = array_slice($filteredAssets, $offset, $perPage);

// Helper Query URL
function gallery_query(array $overrides = []): string {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    return module_url('asset_gallery.php', $params);
}

// 6. Bangun HTML Kartu Galeri
$galleryCardsHtml = '';
foreach ($pageAssets as $a) {
    $aid = (int)($a['id'] ?? 0);
    $kode = !empty($a['kode_inventaris']) ? $a['kode_inventaris'] : sprintf('INV-IT-%03d', $aid);
    $device = asset_title($a);
    $foto = trim((string)($a['foto'] ?? ''));
    $fotoSrc = function_exists('asset_image_url') ? asset_image_url($foto) : ($foto ? module_url($foto) : '');
    $user = !empty($a['karyawan_nama']) ? $a['karyawan_nama'] : '-';
    $cabang = !empty($a['cabang_nama']) ? $a['cabang_nama'] : '-';
    $divisi = !empty($a['divisi_nama']) ? $a['divisi_nama'] : '';
    $editUrl = module_url('asset_edit.php', ['id' => $aid]);
    $token = !empty($a['qr_token']) ? $a['qr_token'] : get_static_qr_token($aid);
    $qrData = [
        'id' => $aid,
        'kode' => $kode,
        'device' => $device,
        'user' => $user,
        'cabang' => $cabang,
        'divisi' => $divisi,
        'token' => $token,
        'foto' => $fotoSrc,
        'edit_url' => $editUrl,
        'scan_url' => module_url('scan.php', ['token' => $token])
    ];
    $qrJson = htmlspecialchars(json_encode($qrData, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');

    if ($fotoSrc !== '') {
        $photoContent = '
        <div class="position-relative overflow-hidden bg-light rounded-top-3" style="height: 190px; cursor: pointer;" onclick="openGalleryLightbox(\''.e($fotoSrc).'\', \''.e(addslashes($device)).'\', \''.e(addslashes($kode)).'\', \''.e(addslashes($user)).'\', \''.e(addslashes($cabang)).'\', \''.e(addslashes($editUrl)).'\')">
          <img src="'.e($fotoSrc).'" class="w-100 h-100 object-fit-cover gallery-img-thumb transition-transform" alt="Foto '.e($device).'" loading="lazy" onerror="this.onerror=null; this.parentElement.innerHTML=\'<div class=\\\'w-100 h-100 d-flex flex-column align-items-center justify-content-center text-muted bg-light\\\'><i class=\\\'bi bi-image text-secondary fs-1 mb-1\\\'></i><small>Foto tidak dapat dimuat</small></div>\';">
          <div class="gallery-hover-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-40 text-white opacity-0 transition-opacity">
            <span class="badge bg-dark bg-opacity-75 rounded-pill px-3 py-2 fw-semibold fs-7 shadow">
              <i class="bi bi-zoom-in me-1"></i> Perbesar Foto
            </span>
          </div>
          <span class="position-absolute top-2 start-2 badge bg-success bg-opacity-90 shadow-xs rounded-pill" style="font-size: 0.68rem;">
            <i class="bi bi-check-circle-fill me-1"></i>Ada Foto
          </span>
        </div>';
    } else {
        $photoContent = '
        <div class="position-relative bg-light rounded-top-3 d-flex flex-column align-items-center justify-content-center border-bottom text-muted" style="height: 190px;">
          <div class="text-secondary opacity-50 mb-2"><i class="bi bi-camera-fill" style="font-size: 2.5rem;"></i></div>
          <span class="small text-muted fw-semibold mb-2">Belum ada foto fisik</span>
          <a href="'.e($editUrl).'" class="btn btn-xs btn-outline-primary py-1 px-2 rounded-pill shadow-xs" style="font-size: 0.72rem;">
            <i class="bi bi-plus-circle me-1"></i> Upload Foto
          </a>
          <span class="position-absolute top-2 start-2 badge bg-secondary bg-opacity-75 shadow-xs rounded-pill" style="font-size: 0.68rem;">
            Belum Ada
          </span>
        </div>';
    }

    $galleryCardsHtml .= '
    <div class="col-12 col-sm-6 col-md-4 col-xl-3 mb-4">
      <div class="card h-100 border shadow-xs rounded-3 overflow-hidden bg-white gallery-card">
        '.$photoContent.'
        <div class="card-body p-3 d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
              <span class="badge bg-navy-subtle text-white font-monospace px-2 py-1 rounded" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                '.e($kode).'
              </span>
              <span class="badge bg-light text-secondary border font-sans" style="font-size: 0.68rem;">
                '.e($a['kategori_nama'] ?? 'Unit IT').'
              </span>
            </div>
            <h6 class="fw-bold text-dark mb-1 text-truncate" title="'.e($device).'">
              '.e($device).'
            </h6>
            <div class="small text-secondary mb-2">
              <div class="text-truncate mb-0" title="'.e($user).'"><i class="bi bi-person text-primary me-1"></i><span class="fw-semibold text-dark">'.e($user).'</span></div>
              <div class="text-truncate text-muted" style="font-size: 0.75rem;" title="'.e($cabang.($divisi ? ' - '.$divisi : '')).'"><i class="bi bi-building text-muted me-1"></i>'.e($cabang).($divisi ? ' &bull; '.e($divisi) : '').'</div>
            </div>
          </div>
          <div class="pt-2 border-top d-flex align-items-center justify-content-between gap-1">
            <button type="button" class="btn btn-xs btn-outline-primary d-inline-flex align-items-center gap-1 rounded-pill px-2 py-1 shadow-xs" onclick="openQrModal('.$qrJson.')" title="Lihat Stiker QR & Foto" style="font-size: 0.72rem;">
              <i class="bi bi-qr-code"></i> Lihat QR
            </button>
            <div class="d-flex align-items-center gap-1">
              <a href="'.e($editUrl).'" class="btn btn-xs btn-light border rounded-circle p-1 text-secondary" title="Ganti / Edit Foto" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content-center;">
                <i class="bi bi-camera"></i>
              </a>
              <a href="'.e(module_url('scan.php', ['token' => $token])).'" class="btn btn-xs btn-light border rounded-circle p-1 text-secondary" title="Buka Detail Aset" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content-center;">
                <i class="bi bi-arrow-up-right"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>';
}

if (empty($galleryCardsHtml)) {
    $galleryCardsHtml = '
    <div class="col-12 py-5 text-center">
      <div class="text-secondary opacity-50 mb-3"><i class="bi bi-images" style="font-size: 4rem;"></i></div>
      <h5 class="fw-bold text-dark">Tidak ada foto perangkat yang ditemukan</h5>
      <p class="text-secondary small">Coba ubah kata kunci pencarian atau ganti filter cabang di atas.</p>
      <a href="'.e(module_url('asset_gallery.php')).'" class="btn btn-outline-primary btn-sm rounded-pill px-3">
        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter
      </a>
    </div>';
}

// 7. Bangun Pagination HTML
$paginationHtml = '';
if ($totalPages > 1) {
    $paginationHtml = '<nav aria-label="Page navigation" class="mt-4"><ul class="pagination pagination-sm justify-content-center gap-1">';
    $prevDisabled = ($page <= 1) ? ' disabled' : '';
    $paginationHtml .= '<li class="page-item'.$prevDisabled.'"><a class="page-link rounded-pill px-3" href="'.e(gallery_query(['page' => max(1, $page - 1)])).'">&laquo; Sebelumnya</a></li>';
    for ($p = 1; $p <= $totalPages; $p++) {
        if ($p === 1 || $p === $totalPages || ($p >= $page - 2 && $p <= $page + 2)) {
            $act = ($p === $page) ? ' active' : '';
            $paginationHtml .= '<li class="page-item'.$act.'"><a class="page-link rounded-circle text-center" style="width: 32px; height: 32px; padding: 0; line-height: 30px;" href="'.e(gallery_query(['page' => $p])).'">'.$p.'</a></li>';
        } elseif ($p === $page - 3 || $p === $page + 3) {
            $paginationHtml .= '<li class="page-item disabled"><span class="page-link border-0">...</span></li>';
        }
    }
    $nextDisabled = ($page >= $totalPages) ? ' disabled' : '';
    $paginationHtml .= '<li class="page-item'.$nextDisabled.'"><a class="page-link rounded-pill px-3" href="'.e(gallery_query(['page' => min($totalPages, $page + 1)])).'">Selanjutnya &raquo;</a></li>';
    $paginationHtml .= '</ul></nav>';
}

// 8. Bangun Tab Cabang Pills
$branchPillsHtml = '<div class="branch-nav-bar custom-scrollbar mb-3">';
$allActive = ($cabangId === 0);
$branchPillsHtml .= '<a class="branch-nav-pill '.($allActive ? 'active' : '').'" href="'.e(gallery_query(['cabang' => 0, 'page' => 1])).'">
  <i class="bi bi-buildings"></i> Semua Cabang
  <span class="badge rounded-pill '.($allActive ? 'bg-white bg-opacity-25 text-white' : 'bg-light text-dark').'">'.$totalAll.'</span>
</a>';
foreach ($cabangs as $c) {
    $cid = (int)($c['id'] ?? 0);
    $cnama = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cid);
    $cPhotoCount = $branchPhotoCounts[$cid] ?? 0;
    $isActive = ($cid === $cabangId);
    $branchPillsHtml .= '<a class="branch-nav-pill '.($isActive ? 'active' : '').'" href="'.e(gallery_query(['cabang' => $cid, 'page' => 1])).'">
      <i class="bi bi-geo-alt'.($isActive ? '-fill' : '').'"></i> '.e($cnama).'
      <span class="badge rounded-pill '.($isActive ? 'bg-white bg-opacity-25 text-white' : 'bg-light text-dark').'">'.$cPhotoCount.' Foto</span>
    </a>';
}
$branchPillsHtml .= '</div>';

// Percent With Photo
$pctPhoto = $totalAll > 0 ? round(($totalWithPhoto / $totalAll) * 100) : 0;

$optDivHtml = '<option value="0">Semua Divisi</option>';
foreach ($divisis as $d) {
    $did = (int)($d['id'] ?? 0);
    $dnama = $d['nama'] ?? $d['nama_divisi'] ?? ('Divisi #' . $did);
    $optDivHtml .= '<option value="'.$did.'"'.($did === $divisiId ? ' selected' : '').'>'.e($dnama).'</option>';
}

$body = '
<!-- Header Page -->
<div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 mb-4">
  <div>
    <div class="tech-label mb-1">VISUAL INVENTORY ARCHIVE</div>
    <h1 class="h3 mb-1"><i class="bi bi-images text-primary me-2"></i>Galeri Foto Perangkat Aset</h1>
    <p class="text-secondary small mb-0">Koleksi foto fisik seluruh perangkat komputer, laptop, dan printer per cabang untuk verifikasi visual.</p>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <a href="'.e(module_url('inventaris_gallery.php')).'" class="btn btn-outline-primary d-inline-flex align-items-center gap-1 fw-semibold shadow-xs">
      <i class="bi bi-collection"></i> Galeri Kartu Inventaris
    </a>
    <a href="'.e(module_url('dashboard.php', ['tab' => 'kartu'])).'" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 fw-semibold shadow-xs">
      <i class="bi bi-credit-card-2-front"></i> Tabel Kartu
    </a>
    <a href="'.e(module_url('dashboard.php')).'" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 fw-semibold shadow-xs">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a href="'.e(module_url('assets.php')).'" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 fw-semibold shadow-xs">
      <i class="bi bi-table"></i> Tabel Aset
    </a>
    <a href="'.e(module_url('print_qr.php', ['cabang' => $cabangId])).'" target="_blank" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 fw-semibold shadow-xs">
      <i class="bi bi-printer"></i> Cetak Label QR
    </a>
  </div>
</div>

<!-- Mode Switcher Tab Bar -->
<div class="d-flex gap-2 mb-4 p-1 bg-light rounded-pill border d-inline-flex">
  <a href="'.e(module_url('asset_gallery.php')).'" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-xs">
    <i class="bi bi-pc-display me-1"></i> Galeri Unit Komputer / IT
  </a>
  <a href="'.e(module_url('inventaris_gallery.php')).'" class="btn btn-sm btn-light border-0 text-secondary rounded-pill px-3 fw-semibold">
    <i class="bi bi-collection me-1"></i> Galeri Kartu Inventaris (Bank)
  </a>
  <a href="'.e(module_url('dashboard.php', ['tab' => 'kartu'])).'" class="btn btn-sm btn-light border-0 text-secondary rounded-pill px-3 fw-semibold">
    <i class="bi bi-table me-1"></i> Tampilan Tabel Kartu
  </a>
</div>

<!-- Stats Card Row -->
<div class="row g-3 mb-4">
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card p-3 border-0 shadow-sm bg-white rounded-3 h-100 border-start border-primary border-4">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Total Perangkat</div>
          <div class="h4 fw-bold mb-0 text-dark">'.$totalAll.' <span class="fs-6 fw-normal text-muted">Unit</span></div>
        </div>
        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary fs-3"><i class="bi bi-pc-display"></i></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card p-3 border-0 shadow-sm bg-white rounded-3 h-100 border-start border-success border-4">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Sudah Ada Foto</div>
          <div class="h4 fw-bold mb-0 text-success">'.$totalWithPhoto.' <span class="fs-6 fw-normal text-muted">('.$pctPhoto.'%)</span></div>
        </div>
        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success fs-3"><i class="bi bi-camera-fill"></i></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card p-3 border-0 shadow-sm bg-white rounded-3 h-100 border-start border-warning border-4">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Belum Ada Foto</div>
          <div class="h4 fw-bold mb-0 text-warning">'.$totalNoPhoto.' <span class="fs-6 fw-normal text-muted">Unit</span></div>
        </div>
        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning fs-3"><i class="bi bi-exclamation-circle"></i></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card p-3 border-0 shadow-sm bg-white rounded-3 h-100 border-start border-info border-4">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Kantor Cabang</div>
          <div class="h4 fw-bold mb-0 text-dark">'.count($cabangs).' <span class="fs-6 fw-normal text-muted">Cabang</span></div>
        </div>
        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info fs-3">
          <i class="bi bi-buildings"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Navigasi Cabang -->
'.$branchPillsHtml.'

<!-- Filter & Search Toolbar -->
<div class="card p-3 border-0 shadow-sm bg-white rounded-3 mb-4">
  <form method="GET" action="'.e(module_url('asset_gallery.php')).'" class="row g-2 align-items-center">
    <input type="hidden" name="cabang" value="'.e($cabangId).'">
    <div class="col-12 col-md-5">
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control bg-light border-start-0" placeholder="Cari merk, model, nama pengguna, SN, atau kode..." value="'.e($search).'">
        '.($search !== '' ? '<a href="'.e(gallery_query(['q' => null, 'page' => 1])).'" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>' : '').'
      </div>
    </div>
    <div class="col-6 col-md-3">
      <select name="photo" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="all"'.($photoFilter === 'all' ? ' selected' : '').'>Semua Status Foto ('.$totalAll.')</option>
        <option value="with_photo"'.($photoFilter === 'with_photo' ? ' selected' : '').'>Hanya Yang Ada Foto ('.$totalWithPhoto.')</option>
        <option value="no_photo"'.($photoFilter === 'no_photo' ? ' selected' : '').'>Belum Difoto ('.$totalNoPhoto.')</option>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <select name="divisi" class="form-select form-select-sm" onchange="this.form.submit()">
        '.$optDivHtml.'
      </select>
    </div>
    <div class="col-12 col-md-1 d-grid">
      <button type="submit" class="btn btn-sm btn-primary">Filter</button>
    </div>
  </form>
</div>

<!-- Gallery Grid -->
<div class="row g-3">
  '.$galleryCardsHtml.'
</div>

<!-- Pagination -->
'.$paginationHtml.'

<!-- Modal Lightbox Pembesar Foto -->
<div class="modal fade" id="modalGalleryLightbox" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg overflow-hidden rounded-4">
      <div class="modal-header bg-navy-dark text-white py-3 px-4 border-0">
        <div>
          <div class="small text-uppercase text-info fw-bold" id="lightboxKode">KODE INVENTARIS</div>
          <h5 class="modal-title fw-bold mb-0 text-white" id="lightboxTitle">Nama Perangkat</h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0 bg-dark d-flex align-items-center justify-content-center text-center position-relative" style="min-height: 380px; max-height: 70vh;">
        <img id="lightboxImg" src="" alt="Foto Perangkat" class="img-fluid w-100 object-fit-contain" style="max-height: 68vh;">
      </div>
      <div class="modal-footer bg-light py-2 px-4 d-flex justify-content-between align-items-center">
        <div class="small text-secondary">
          <i class="bi bi-person me-1"></i><span id="lightboxUser">-</span> | <i class="bi bi-building me-1"></i><span id="lightboxCabang">-</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <a id="lightboxDownloadBtn" href="#" download class="btn btn-sm btn-outline-secondary rounded-pill px-3">
            <i class="bi bi-download me-1"></i> Unduh
          </a>
          <a id="lightboxEditBtn" href="#" class="btn btn-sm btn-primary rounded-pill px-3">
            <i class="bi bi-pencil me-1"></i> Ganti / Edit Foto
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Quick QR Preview (Sesuai Desain Quick QR) -->
<div class="modal fade" id="quickQrModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-navy-dark text-white py-3 px-4 border-bottom border-navy-subtle">
        <div class="d-flex align-items-center gap-2">
          <div class="bg-primary text-white rounded p-1 d-inline-flex"><i class="bi bi-qr-code fs-6"></i></div>
          <div>
            <div class="small text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.85) !important;">PRATINJAU STIKER QR &amp; FOTO UNIT</div>
            <h5 class="modal-title fw-bold mb-0 text-white" id="modalAssetTitle">Perangkat IT</h5>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 bg-white">
        <div class="row g-4 align-items-center mb-3">
          <div class="col-12 col-md-5 text-center border-end-md">
            <div class="d-inline-block p-3 bg-white border border-2 border-primary border-opacity-25 rounded-4 shadow-sm mb-2" style="background: #ffffff !important;">
              <canvas id="modalQrCanvas" width="180" height="180" style="width: 180px; height: 180px; display: block; margin: 0 auto;"></canvas>
            </div>
            <div class="font-monospace fw-bold text-dark fs-6 mt-1" id="modalAssetKode">INV-IT-000</div>
          </div>
          <div class="col-12 col-md-7">
            <div class="card p-2 bg-light border rounded-3 mb-2" style="height: 190px;">
              <img id="modalAssetPhotoImg" src="" alt="Foto Unit" class="w-100 h-100 object-fit-cover rounded border d-none" onerror="onGalleryModalPhotoError(this)">
              <div id="modalAssetPhotoPlaceholder" class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-muted p-3 text-center">
                <i class="bi bi-camera text-secondary fs-1 mb-1"></i>
                <span class="fw-semibold">Belum ada foto fisik unit</span>
                <span class="small text-muted mb-2">Upload foto bodi / serial number unit</span>
                <a id="modalAssetUploadPhotoBtn" href="#" class="btn btn-xs btn-primary rounded-pill px-3 py-1 shadow-xs" style="font-size: 0.75rem;">
                  <i class="bi bi-plus-circle me-1"></i> Upload Foto
                </a>
              </div>
            </div>
            <div class="d-flex align-items-center justify-content-between small text-muted px-1">
              <span><i class="bi bi-pc-display me-1 text-primary"></i> Foto Fisik Unit Perangkat</span>
              <a id="modalAssetEditPhotoBtn" href="#" class="btn btn-sm btn-link py-0 px-1 text-decoration-none d-none" style="font-size: 0.72rem;">(Ganti Foto)</a>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer bg-light py-2 px-4 d-flex justify-content-between align-items-center">
        <a id="modalAssetEditLink" href="#" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
          <i class="bi bi-pencil me-1"></i> Edit Lengkap
        </a>
        <button type="button" class="btn btn-sm btn-primary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
';

$extraHead = '
<style>
.gallery-img-thumb {
  transition: transform 0.35s ease;
}
.gallery-card:hover .gallery-img-thumb {
  transform: scale(1.06);
}
.gallery-hover-overlay {
  transition: opacity 0.25s ease;
}
.gallery-card:hover .gallery-hover-overlay {
  opacity: 1 !important;
}
.transition-transform {
  transition: transform 0.3s ease-in-out;
}
.transition-opacity {
  transition: opacity 0.2s ease-in-out;
}
@media (min-width: 768px) {
  .border-end-md {
    border-right: 1px solid var(--app-border, #e2e8f0);
  }
}
</style>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
';

$extraScript = '
<script>
function onGalleryModalPhotoError(img) {
  img.classList.add("d-none");
  var pl = document.getElementById("modalAssetPhotoPlaceholder");
  if (pl) pl.classList.remove("d-none");
  var editBtn = document.getElementById("modalAssetEditPhotoBtn");
  if (editBtn) editBtn.classList.add("d-none");
}

function openGalleryLightbox(src, title, kode, user, cabang, editUrl) {
  var img = document.getElementById("lightboxImg");
  var t = document.getElementById("lightboxTitle");
  var k = document.getElementById("lightboxKode");
  var u = document.getElementById("lightboxUser");
  var c = document.getElementById("lightboxCabang");
  var dl = document.getElementById("lightboxDownloadBtn");
  var ed = document.getElementById("lightboxEditBtn");

  if (img) img.src = src;
  if (t) t.textContent = title;
  if (k) k.textContent = kode;
  if (u) u.textContent = user;
  if (c) c.textContent = cabang;
  if (dl) dl.href = src;
  if (ed) ed.href = editUrl;

  var modal = new bootstrap.Modal(document.getElementById("modalGalleryLightbox"));
  modal.show();
}

function openQrModal(data) {
  var titleEl = document.getElementById("modalAssetTitle");
  var kodeEl = document.getElementById("modalAssetKode");
  var editLink = document.getElementById("modalAssetEditLink");
  var photoImg = document.getElementById("modalAssetPhotoImg");
  var photoPl = document.getElementById("modalAssetPhotoPlaceholder");
  var uploadBtn = document.getElementById("modalAssetUploadPhotoBtn");
  var editPhotoBtn = document.getElementById("modalAssetEditPhotoBtn");

  if (titleEl) titleEl.textContent = data.device || "Perangkat IT";
  if (kodeEl) kodeEl.textContent = data.kode || "-";
  if (editLink) editLink.href = data.edit_url || "#";
  if (uploadBtn) uploadBtn.href = data.edit_url || "#";
  if (editPhotoBtn) editPhotoBtn.href = data.edit_url || "#";

  if (photoImg && photoPl) {
    if (data.foto) {
      photoImg.src = data.foto;
      photoImg.classList.remove("d-none");
      photoPl.classList.add("d-none");
      if (editPhotoBtn) editPhotoBtn.classList.remove("d-none");
    } else {
      photoImg.src = "";
      photoImg.classList.add("d-none");
      photoPl.classList.remove("d-none");
      if (editPhotoBtn) editPhotoBtn.classList.add("d-none");
    }
  }

  var canvas = document.getElementById("modalQrCanvas");
  if (canvas && window.QRCode) {
    var ctx = canvas.getContext("2d");
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    QRCode.toCanvas(canvas, data.scan_url, {
      width: 180,
      margin: 1,
      color: { dark: "#08182f", light: "#ffffff" }
    });
  }

  var modal = new bootstrap.Modal(document.getElementById("quickQrModal"));
  modal.show();
}
</script>
';

render_page('Galeri Foto Unit', $body, $extraHead, $extraScript);
