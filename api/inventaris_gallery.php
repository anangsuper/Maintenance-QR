<?php
require __DIR__ . '/bootstrap.php';
require_login();

// Pastikan kolom tambahan seperti foto, nomor_seri_inventaris, dll sudah tersedia
ensure_inventaris_kartu_columns();

// 1. Ambil Parameter Filter
$search = trim((string)($_GET['q'] ?? ''));
$cabangCode = trim((string)($_GET['cabang'] ?? ''));
if ($cabangCode === 'all' || $cabangCode === '0') {
    $cabangCode = '';
}
$jenisFilter = trim((string)($_GET['jenis'] ?? 'all'));
$photoFilter = trim((string)($_GET['photo'] ?? 'all')); // 'all', 'with_photo', 'no_photo'
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24; // 24 kartu per halaman agar grid 3/4 kolom rapi

// 2. Ambil Master Data
$masterCabangs = get_standard_cabang_list();
$masterJenis = get_master_jenis_inventaris();
$masterRuangan = get_master_ruangan_list();

// 3. Ambil Seluruh Data Kartu Inventaris
$rawCards = get_inventaris_kartu_rows(!empty($_GET['refresh']));

// Map foto dari tabel assets (jika kartu inventaris belum memiliki foto langsung tapi nomor rekening cocok dengan unit IT)
$assetPhotoMap = [];
try {
    $stPhoto = db()->query("SELECT nomor_rekening, foto, updated_by FROM assets WHERE foto IS NOT NULL AND foto != ''");
    while ($ap = $stPhoto->fetch()) {
        $rRek = trim((string)($ap['nomor_rekening'] ?? ''));
        $rClean = preg_replace('/[^0-9]/', '', $rRek);
        if ($rClean !== '' && !isset($assetPhotoMap[$rClean])) {
            $assetPhotoMap[$rClean] = [
                'foto' => (string)$ap['foto'],
                'by'   => (string)($ap['updated_by'] ?? 'Sistem IT')
            ];
        }
    }
} catch (Throwable $ignore) {}

// Siapkan data kartu lengkap dengan cabang terstandar dan resolusi foto
$allPreparedCards = [];
$branchStats = [
    '01' => ['name' => 'Kantor Pusat', 'count' => 0, 'photos' => 0, 'color' => '#2E7CF6', 'icon' => 'bi-building'],
    '02' => ['name' => 'Batulicin', 'count' => 0, 'photos' => 0, 'color' => '#16803C', 'icon' => 'bi-geo-alt'],
    '03' => ['name' => 'Martapura', 'count' => 0, 'photos' => 0, 'color' => '#D97706', 'icon' => 'bi-geo-alt'],
    '04' => ['name' => 'Tanjung', 'count' => 0, 'photos' => 0, 'color' => '#8B5CF6', 'icon' => 'bi-geo-alt'],
    '05' => ['name' => 'Handil Bakti', 'count' => 0, 'photos' => 0, 'color' => '#EC4899', 'icon' => 'bi-geo-alt'],
];

$totalAll = count($rawCards);
$totalWithPhoto = 0;
$totalNoPhoto = 0;

foreach ($rawCards as $c) {
    $cid = (int)($c['id'] ?? 0);
    $rek = trim((string)($c['nomor_rekening'] ?? ''));
    $cleanRek = preg_replace('/[^0-9]/', '', $rek);
    $lokasi = trim((string)($c['lokasi'] ?? ''));
    
    // Deteksi Cabang
    $assignedCode = '01';
    if (preg_match('/^(\d{2})[\.\-]/', $rek, $m) && isset($branchStats[$m[1]])) {
        $assignedCode = $m[1];
    } else {
        $lokLower = strtolower($lokasi);
        if (str_contains($lokLower, 'batulicin')) $assignedCode = '02';
        elseif (str_contains($lokLower, 'martapura')) $assignedCode = '03';
        elseif (str_contains($lokLower, 'tanjung')) $assignedCode = '04';
        elseif (str_contains($lokLower, 'handil')) $assignedCode = '05';
    }

    if (isset($branchStats[$assignedCode])) {
        $branchStats[$assignedCode]['count']++;
    }

    // Resolusi Foto
    $foto = trim((string)($c['foto'] ?? ''));
    $fotoBy = trim((string)($c['foto_updated_by'] ?? ''));
    $fotoAt = trim((string)($c['foto_updated_at'] ?? ''));

    if ($foto === '' && isset($assetPhotoMap[$cleanRek])) {
        $foto = $assetPhotoMap[$cleanRek]['foto'];
        if ($fotoBy === '') $fotoBy = $assetPhotoMap[$cleanRek]['by'];
    }

    $hasFoto = ($foto !== '');
    if ($hasFoto) {
        $totalWithPhoto++;
        if (isset($branchStats[$assignedCode])) {
            $branchStats[$assignedCode]['photos']++;
        }
    } else {
        $totalNoPhoto++;
    }

    $c['branch_code'] = $assignedCode;
    $c['branch_name'] = $branchStats[$assignedCode]['name'] ?? 'Kantor Cabang';
    $c['branch_color'] = $branchStats[$assignedCode]['color'] ?? '#2E7CF6';
    $c['resolved_foto'] = $foto;
    $c['resolved_foto_by'] = $fotoBy;
    $c['resolved_foto_at'] = $fotoAt;

    $allPreparedCards[] = $c;
}

// 4. Filter Data Sesuai Pilihan Pengguna
$filteredCards = array_filter($allPreparedCards, function($c) use ($search, $cabangCode, $jenisFilter, $photoFilter) {
    if ($cabangCode !== '' && $c['branch_code'] !== $cabangCode) {
        return false;
    }

    if ($jenisFilter !== 'all' && $jenisFilter !== '') {
        $j = trim((string)($c['jenis_inventaris'] ?? ''));
        if ($j !== $jenisFilter && !str_starts_with($j, $jenisFilter . ' :')) {
            return false;
        }
    }

    $hasFoto = !empty($c['resolved_foto']);
    if ($photoFilter === 'with_photo' && !$hasFoto) {
        return false;
    }
    if ($photoFilter === 'no_photo' && $hasFoto) {
        return false;
    }

    if ($search !== '') {
        $q = strtolower($search);
        $cleanQ = preg_replace('/[^a-z0-9]/', '', $q);
        $haystack = strtolower(implode(' ', [
            $c['nomor_rekening'] ?? '',
            $c['nama_barang'] ?? '',
            $c['nomor_seri_inventaris'] ?? '',
            $c['ruangan'] ?? '',
            $c['lokasi'] ?? '',
            $c['branch_name'] ?? '',
            $c['jenis_inventaris'] ?? '',
            $c['golongan'] ?? '',
            $c['barcode_data'] ?? '',
            $c['keterangan'] ?? ''
        ]));
        $cleanHaystack = preg_replace('/[^a-z0-9]/', '', $haystack);

        if (strpos($haystack, $q) === false && ($cleanQ === '' || strpos($cleanHaystack, $cleanQ) === false)) {
            return false;
        }
    }

    return true;
});

// Urutkan: Barang yang memiliki foto di depan, lalu ID DESC
usort($filteredCards, function($a, $b) {
    $hasA = !empty($a['resolved_foto']) ? 1 : 0;
    $hasB = !empty($b['resolved_foto']) ? 1 : 0;
    if ($hasA !== $hasB) {
        return $hasB <=> $hasA;
    }
    return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
});

// 5. Pagination
$totalFiltered = count($filteredCards);
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;
$pageCards = array_slice($filteredCards, $offset, $perPage);

// Helper Query URL
function inv_gallery_query(array $overrides = []): string {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    return module_url('inventaris_gallery.php', $params);
}

// 6. Bangun HTML Kartu Galeri
$galleryCardsHtml = '';
foreach ($pageCards as $c) {
    $cid = (int)($c['id'] ?? 0);
    $rek = trim((string)($c['nomor_rekening'] ?? ''));
    $nama = trim((string)($c['nama_barang'] ?? ''));
    $tgl = trim((string)($c['tanggal_perolehan'] ?? ''));
    $ruangan = trim((string)($c['ruangan'] ?? $c['lokasi'] ?? '-'));
    $noSeri = trim((string)($c['nomor_seri_inventaris'] ?? ''));
    $jenis = trim((string)($c['jenis_inventaris'] ?? ''));
    $statusInv = trim((string)($c['status_inventaris'] ?? '1 : Aktif'));
    $barcode = trim((string)($c['barcode_data'] ?? ''));
    $nomorGabungan = get_nomor_asset_gabungan($rek, $tgl);

    $foto = $c['resolved_foto'] ?? '';
    $fotoBy = $c['resolved_foto_by'] ?? '';
    $fotoAt = $c['resolved_foto_at'] ?? '';
    $fotoSrc = function_exists('asset_image_url') ? asset_image_url($foto) : ($foto ? module_url($foto) : '');

    $detailUrl = module_url('kartu_detail.php', ['id' => $cid]);
    $printUrl = module_url('print_inventory_card.php', ['id' => $cid]);

    $cardJsonData = [
        'id' => $cid,
        'nomor_rekening' => $rek,
        'nama_barang' => $nama,
        'ruangan' => $ruangan,
        'cabang' => $c['branch_name'],
        'tanggal' => format_indo_date($tgl),
        'nomor_gabungan' => $nomorGabungan,
        'foto' => $fotoSrc,
        'foto_by' => $fotoBy,
        'detail_url' => $detailUrl,
        'print_url' => $printUrl,
        'barcode' => $barcode ?: $rek
    ];
    $cardJson = htmlspecialchars(json_encode($cardJsonData, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');

    if ($fotoSrc !== '') {
        $photoContent = '
        <div class="position-relative overflow-hidden bg-light rounded-top-3" style="height: 195px; cursor: pointer;" onclick="openInvGalleryLightbox('.$cardJson.')">
          <img src="'.e($fotoSrc).'" class="w-100 h-100 object-fit-cover gallery-img-thumb transition-transform" alt="Foto '.e($nama).'" loading="lazy" onerror="this.onerror=null; this.parentElement.innerHTML=\'<div class=\\\'w-100 h-100 d-flex flex-column align-items-center justify-content-center text-muted bg-light\\\'><i class=\\\'bi bi-image text-secondary fs-1 mb-1\\\'></i><small>Foto tidak dapat dimuat</small></div>\';">
          <div class="gallery-hover-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-40 text-white opacity-0 transition-opacity">
            <span class="badge bg-dark bg-opacity-75 rounded-pill px-3 py-2 fw-semibold fs-7 shadow">
              <i class="bi bi-zoom-in me-1"></i> Perbesar Foto
            </span>
          </div>
          <span class="position-absolute top-2 start-2 badge bg-success bg-opacity-90 shadow-xs rounded-pill" style="font-size: 0.68rem;">
            <i class="bi bi-check-circle-fill me-1"></i>Ada Foto
          </span>
          '.($fotoBy !== '' ? '
          <span class="position-absolute bottom-2 start-2 badge bg-dark bg-opacity-75 text-white shadow-xs rounded-pill" style="font-size: 0.65rem;" title="Diverifikasi oleh: '.e($fotoBy).'">
            <i class="bi bi-patch-check-fill text-info me-1"></i>'.e(mb_strimwidth($fotoBy, 0, 18, '...')).'
          </span>' : '').'
        </div>';
    } else {
        $photoContent = '
        <div class="position-relative bg-light rounded-top-3 d-flex flex-column align-items-center justify-content-center border-bottom text-muted" style="height: 195px;">
          <div class="text-secondary opacity-50 mb-2"><i class="bi bi-box-seam" style="font-size: 2.8rem;"></i></div>
          <span class="small text-muted fw-semibold mb-2" style="font-size: 0.78rem;">Belum ada foto fisik barang</span>
          <a href="'.e($detailUrl).'" class="btn btn-xs btn-outline-primary py-1 px-2.5 rounded-pill shadow-xs" style="font-size: 0.72rem;">
            <i class="bi bi-camera me-1"></i> Ambil / Upload Foto
          </a>
          <span class="position-absolute top-2 start-2 badge bg-secondary bg-opacity-75 shadow-xs rounded-pill" style="font-size: 0.68rem;">
            Belum Difoto
          </span>
        </div>';
    }

    $galleryCardsHtml .= '
    <div class="col-12 col-sm-6 col-md-4 col-xl-3 mb-4">
      <div class="card h-100 border shadow-xs rounded-3 overflow-hidden bg-white gallery-card d-flex flex-column justify-content-between">
        <div>
          '.$photoContent.'
          <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between gap-1 mb-1.5 flex-wrap">
              <span class="badge font-monospace px-2 py-1 rounded shadow-xs" style="background-color: '.e($c['branch_color']).'; color: #ffffff; font-size: 0.7rem; letter-spacing: 0.03em;">
                '.e($c['branch_code']).' &bull; '.e($c['branch_name']).'
              </span>
              '.($jenis !== '' ? '
              <span class="badge bg-light text-secondary border font-sans" style="font-size: 0.66rem;" title="'.e($jenis).'">
                '.e(mb_strimwidth($jenis, 0, 16, '...')).'
              </span>' : '').'
            </div>

            <div class="font-monospace text-primary fw-bold small mb-1 d-flex align-items-center justify-content-between" style="font-size: 0.76rem;">
              <span><i class="bi bi-hash me-0.5"></i>'.e($rek).'</span>
              '.($noSeri !== '' ? '<span class="text-muted fw-normal" style="font-size: 0.68rem;" title="No Seri: '.e($noSeri).'">SN: '.e(mb_strimwidth($noSeri, 0, 10, '...')).'</span>' : '').'
            </div>

            <h6 class="fw-bold text-dark mb-1.5 line-clamp-2" title="'.e($nama).'" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.4rem; line-height: 1.25; font-size: 0.88rem;">
              <a href="'.e($detailUrl).'" class="text-dark text-decoration-none hover-primary">'.e($nama).'</a>
            </h6>

            <div class="small text-secondary mb-1" style="font-size: 0.74rem;">
              <div class="text-truncate mb-0.5" title="Ruangan: '.e($ruangan).'"><i class="bi bi-geo-alt text-muted me-1"></i><span class="text-dark">'.e($ruangan).'</span></div>
              <div class="text-truncate text-muted" title="Perolehan: '.e(format_indo_date($tgl)).'"><i class="bi bi-calendar3 text-muted me-1"></i>'.e(format_indo_date($tgl)).'</div>
            </div>
          </div>
        </div>

        <div class="p-3 pt-0">
          <div class="pt-2 border-top d-flex align-items-center justify-content-between gap-1">
            <a href="'.e($detailUrl).'" class="btn btn-xs btn-primary d-inline-flex align-items-center gap-1 rounded-pill px-2.5 py-1 fw-semibold shadow-xs" style="font-size: 0.72rem;">
              <i class="bi bi-credit-card-2-front"></i> Buka Kartu
            </a>
            <div class="d-flex align-items-center gap-1">
              <button type="button" class="btn btn-xs btn-light border rounded-circle p-1 text-secondary" title="Lihat Barcode & Info Cepat" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content-center;" onclick="openInvQrModal('.$cardJson.')">
                <i class="bi bi-qr-code"></i>
              </button>
              <a href="'.e($printUrl).'" target="_blank" class="btn btn-xs btn-light border rounded-circle p-1 text-secondary" title="Cetak Kartu A4" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content-center;">
                <i class="bi bi-printer"></i>
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
      <div class="text-secondary opacity-50 mb-3"><i class="bi bi-collection" style="font-size: 4rem;"></i></div>
      <h5 class="fw-bold text-dark">Tidak ada kartu inventaris yang ditemukan</h5>
      <p class="text-secondary small">Coba ubah kata kunci pencarian atau ganti filter cabang di atas.</p>
      <a href="'.e(module_url('inventaris_gallery.php')).'" class="btn btn-outline-primary btn-sm rounded-pill px-3">
        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter
      </a>
    </div>';
}

// 7. Bangun Pagination HTML
$paginationHtml = '';
if ($totalPages > 1) {
    $paginationHtml = '<nav aria-label="Page navigation" class="mt-4"><ul class="pagination pagination-sm justify-content-center gap-1">';
    $prevDisabled = ($page <= 1) ? ' disabled' : '';
    $paginationHtml .= '<li class="page-item'.$prevDisabled.'"><a class="page-link rounded-pill px-3" href="'.e(inv_gallery_query(['page' => max(1, $page - 1)])).'">&laquo; Sebelumnya</a></li>';
    for ($p = 1; $p <= $totalPages; $p++) {
        if ($p === 1 || $p === $totalPages || ($p >= $page - 2 && $p <= $page + 2)) {
            $act = ($p === $page) ? ' active' : '';
            $paginationHtml .= '<li class="page-item'.$act.'"><a class="page-link rounded-circle text-center" style="width: 32px; height: 32px; padding: 0; line-height: 30px;" href="'.e(inv_gallery_query(['page' => $p])).'">'.$p.'</a></li>';
        } elseif ($p === $page - 3 || $p === $page + 3) {
            $paginationHtml .= '<li class="page-item disabled"><span class="page-link border-0">...</span></li>';
        }
    }
    $nextDisabled = ($page >= $totalPages) ? ' disabled' : '';
    $paginationHtml .= '<li class="page-item'.$nextDisabled.'"><a class="page-link rounded-pill px-3" href="'.e(inv_gallery_query(['page' => min($totalPages, $page + 1)])).'">Selanjutnya &raquo;</a></li>';
    $paginationHtml .= '</ul></nav>';
}

// 8. Bangun Tab Cabang Pills
$branchPillsHtml = '<div class="branch-nav-bar custom-scrollbar mb-3">';
$allActive = ($cabangCode === '');
$branchPillsHtml .= '<a class="branch-nav-pill '.($allActive ? 'active' : '').'" href="'.e(inv_gallery_query(['cabang' => null, 'page' => 1])).'">
  <i class="bi bi-grid-fill me-1"></i> Semua Cabang <span class="badge bg-secondary text-white rounded-pill ms-1">'.$totalAll.'</span>
</a>';

foreach ($branchStats as $code => $bs) {
    $isActive = ($cabangCode === $code);
    $bCount = $bs['count'];
    $bPhotos = $bs['photos'];
    $branchPillsHtml .= '<a class="branch-nav-pill '.($isActive ? 'active' : '').'" href="'.e(inv_gallery_query(['cabang' => $code, 'page' => 1])).'">
      <i class="bi '.e($bs['icon']).' me-1" style="color: '.e($bs['color']).'"></i> '.e($code).' '.e($bs['name']).'
      <span class="badge '.($isActive ? 'bg-primary' : 'bg-light text-dark border').' rounded-pill ms-1">'.$bCount.'</span>
      '.($bPhotos > 0 ? '<span class="badge bg-success text-white rounded-pill ms-0.5" title="'.$bPhotos.' unit berfoto"><i class="bi bi-camera me-0.5"></i>'.$bPhotos.'</span>' : '').'
    </a>';
}
$branchPillsHtml .= '</div>';

// 9. Dropdown Jenis Inventaris
$optJenisHtml = '<option value="all">Semua Jenis Inventaris</option>';
foreach ($masterJenis as $jKey => $jVal) {
    $optJenisHtml .= '<option value="'.$jKey.'"'.($jenisFilter === (string)$jKey ? ' selected' : '').'>'.e($jVal).'</option>';
}

$pctPhoto = $totalAll > 0 ? round(($totalWithPhoto / $totalAll) * 100, 1) : 0;

$extraHead = '
<style>
.gallery-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.gallery-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05) !important;
}
.gallery-img-thumb {
  transition: transform 0.3s ease;
}
.gallery-card:hover .gallery-img-thumb {
  transform: scale(1.05);
}
.gallery-hover-overlay {
  transition: opacity 0.2s ease;
}
.gallery-card:hover .gallery-hover-overlay {
  opacity: 1 !important;
}
.branch-nav-bar {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 6px;
  white-space: nowrap;
}
.branch-nav-pill {
  padding: 8px 16px;
  font-size: 0.82rem;
  font-weight: 600;
  border-radius: 9999px;
  background: #ffffff;
  border: 1px solid #E2E8F0;
  color: #475569;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  transition: all 0.15s ease;
}
.branch-nav-pill:hover {
  background: #F8FAFC;
  border-color: #CBD5E1;
  color: #0F172A;
}
.branch-nav-pill.active {
  background: #0284C7;
  border-color: #0284C7;
  color: #ffffff !important;
  box-shadow: 0 2px 4px rgba(2, 132, 199, 0.25);
}
.branch-nav-pill.active i {
  color: #ffffff !important;
}
.hover-primary:hover {
  color: #0284C7 !important;
}
</style>
';

$body = '
<!-- Header Page -->
<div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 mb-4">
  <div>
    <div class="tech-label mb-1">VISUAL CORE INVENTORY ARCHIVE</div>
    <h1 class="h3 mb-1"><i class="bi bi-collection text-primary me-2"></i>Galeri Foto Kartu Inventaris</h1>
    <p class="text-secondary small mb-0">Koleksi foto fisik seluruh barang inventaris Bank (peralatan kantor, mesin, kendaraan, gedung, dll) per kantor cabang.</p>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <a href="'.e(module_url('dashboard.php', ['tab' => 'kartu'])).'" class="btn btn-outline-primary d-inline-flex align-items-center gap-1 fw-semibold shadow-xs">
      <i class="bi bi-credit-card-2-front"></i> Tabel Kartu Inventaris
    </a>
    <a href="'.e(module_url('asset_gallery.php')).'" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 fw-semibold shadow-xs">
      <i class="bi bi-pc-display"></i> Galeri Unit Komputer (IT)
    </a>
    <a href="'.e(module_url('print_inventory_card.php', ['source'=>'inventaris_kartu'])).'" target="_blank" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 fw-semibold shadow-xs">
      <i class="bi bi-printer"></i> Cetak Massal A4
    </a>
    <a href="'.e(module_url('dashboard.php')).'" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 fw-semibold shadow-xs">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>
  </div>
</div>

<!-- Mode Switcher Tab Bar -->
<div class="d-flex gap-2 mb-4 p-1 bg-light rounded-pill border d-inline-flex">
  <a href="'.e(module_url('inventaris_gallery.php')).'" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-xs">
    <i class="bi bi-collection me-1"></i> Galeri Kartu Inventaris (Bank)
  </a>
  <a href="'.e(module_url('asset_gallery.php')).'" class="btn btn-sm btn-light border-0 text-secondary rounded-pill px-3 fw-semibold">
    <i class="bi bi-pc-display me-1"></i> Galeri Unit Komputer / IT
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
          <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Total Kartu Inventaris</div>
          <div class="h4 fw-bold mb-0 text-dark">'.$totalAll.' <span class="fs-6 fw-normal text-muted">Item</span></div>
        </div>
        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary fs-3"><i class="bi bi-boxes"></i></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card p-3 border-0 shadow-sm bg-white rounded-3 h-100 border-start border-success border-4">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Sudah Ada Foto Fisik</div>
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
          <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Belum Difoto</div>
          <div class="h4 fw-bold mb-0 text-warning">'.$totalNoPhoto.' <span class="fs-6 fw-normal text-muted">Item</span></div>
        </div>
        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning fs-3"><i class="bi bi-exclamation-circle"></i></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card p-3 border-0 shadow-sm bg-white rounded-3 h-100 border-start border-info border-4">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Sebaran Kantor Cabang</div>
          <div class="h4 fw-bold mb-0 text-dark">5 <span class="fs-6 fw-normal text-muted">Cabang</span></div>
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
  <form method="GET" action="'.e(module_url('inventaris_gallery.php')).'" class="row g-2 align-items-center">
    '.($cabangCode !== '' ? '<input type="hidden" name="cabang" value="'.e($cabangCode).'">' : '').'
    <div class="col-12 col-md-5">
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control bg-light border-start-0" placeholder="Cari nama barang, no rekening, ruangan, SN..." value="'.e($search).'">
        '.($search !== '' ? '<a href="'.e(inv_gallery_query(['q' => null, 'page' => 1])).'" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>' : '').'
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
      <select name="jenis" class="form-select form-select-sm" onchange="this.form.submit()">
        '.$optJenisHtml.'
      </select>
    </div>
    <div class="col-12 col-md-1 d-flex gap-1">
      <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold">
        <i class="bi bi-funnel"></i>
      </button>
      '.($search !== '' || $photoFilter !== 'all' || $jenisFilter !== 'all' ? '
      <a href="'.e(module_url('inventaris_gallery.php', $cabangCode !== '' ? ['cabang' => $cabangCode] : [])).'" class="btn btn-sm btn-light border text-danger" title="Reset Filter">
        <i class="bi bi-x-lg"></i>
      </a>' : '').'
    </div>
  </form>
</div>

<!-- Header Info Hasil Pencarian -->
<div class="d-flex align-items-center justify-content-between mb-3 text-muted small">
  <div>
    Menampilkan <strong>'.count($pageCards).'</strong> dari <strong>'.$totalFiltered.'</strong> barang inventaris
    '.($search !== '' ? ' untuk kata kunci "<em>'.e($search).'</em>"' : '').'
    '.($cabangCode !== '' ? ' di kantor <strong>'.e($branchStats[$cabangCode]['name'] ?? $cabangCode).'</strong>' : '').'
  </div>
  <div>Halaman <strong>'.$page.'</strong> dari <strong>'.$totalPages.'</strong></div>
</div>

<!-- Grid Kartu Inventaris -->
<div class="row">
  '.$galleryCardsHtml.'
</div>

<!-- Pagination -->
'.$paginationHtml.'

<!-- Modal Lightbox Zoom Foto Inventaris -->
<div class="modal fade" id="invGalleryLightboxModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-dark text-white border-0 py-2.5">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-image text-primary fs-5"></i>
          <div>
            <h6 class="modal-title fw-bold mb-0" id="lightboxInvTitle">Foto Fisik Barang</h6>
            <div class="small text-white-50" id="lightboxInvSubtitle" style="font-size: 0.75rem;"></div>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0 bg-black text-center position-relative d-flex align-items-center justify-content-center" style="min-height: 380px; max-height: 70vh;">
        <img id="lightboxInvImage" src="" class="img-fluid" style="max-height: 70vh; object-fit: contain;" alt="Foto Fisik Barang">
      </div>
      <div class="modal-footer bg-light border-0 py-2.5 d-flex justify-content-between">
        <div class="small text-muted" id="lightboxInvMeta"></div>
        <div class="d-flex gap-2">
          <a id="lightboxInvOpenBtn" href="" target="_blank" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-box-arrow-up-right me-1"></i> Buka Asli
          </a>
          <a id="lightboxInvDetailBtn" href="" class="btn btn-sm btn-primary">
            <i class="bi bi-credit-card-2-front me-1"></i> Buka Kartu Inventaris
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Quick Info & Barcode Inventaris -->
<div class="modal fade" id="invQuickQrModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-bold text-dark"><i class="bi bi-qr-code text-primary me-1"></i> Quick Barcode</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-3">
        <div class="p-2 border rounded-3 bg-white d-inline-block shadow-xs mb-2">
          <canvas id="invModalQrCanvas" width="160" height="160" style="display: block; margin: 0 auto;"></canvas>
        </div>
        <div class="font-monospace fw-bold text-dark mb-1" id="invModalRekening" style="font-size: 0.82rem;"></div>
        <div class="fw-semibold text-secondary small mb-1" id="invModalNama"></div>
        <div class="badge bg-light text-muted border mb-3" id="invModalRuangan"></div>
        <div class="d-grid gap-2">
          <a id="invModalDetailBtn" href="" class="btn btn-sm btn-primary rounded-pill">
            <i class="bi bi-credit-card-2-front me-1"></i> Detail Kartu Inventaris
          </a>
          <a id="invModalPrintBtn" href="" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill">
            <i class="bi bi-printer me-1"></i> Cetak Kartu A4
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
';

$extraScript = '
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
function openInvGalleryLightbox(data) {
  if (!data || !data.foto) return;
  document.getElementById("lightboxInvImage").src = data.foto;
  document.getElementById("lightboxInvTitle").textContent = data.nama_barang || "Foto Barang";
  document.getElementById("lightboxInvSubtitle").textContent = (data.nomor_rekening ? data.nomor_rekening + " • " : "") + (data.cabang || "");
  document.getElementById("lightboxInvOpenBtn").href = data.foto;
  document.getElementById("lightboxInvDetailBtn").href = data.detail_url || "#";
  
  var metaEl = document.getElementById("lightboxInvMeta");
  if (data.foto_by) {
    metaEl.innerHTML = "<i class=\"bi bi-patch-check-fill text-success me-1\"></i> Foto diverifikasi: <strong>" + data.foto_by + "</strong>";
  } else {
    metaEl.innerHTML = "<i class=\"bi bi-geo-alt me-1\"></i> Penempatan: <strong>" + (data.ruangan || "-") + "</strong>";
  }

  var modal = new bootstrap.Modal(document.getElementById("invGalleryLightboxModal"));
  modal.show();
}

function openInvQrModal(data) {
  if (!data) return;
  document.getElementById("invModalRekening").textContent = data.nomor_rekening || "";
  document.getElementById("invModalNama").textContent = data.nama_barang || "";
  document.getElementById("invModalRuangan").textContent = (data.cabang || "") + " • " + (data.ruangan || "");
  document.getElementById("invModalDetailBtn").href = data.detail_url || "#";
  document.getElementById("invModalPrintBtn").href = data.print_url || "#";

  var canvas = document.getElementById("invModalQrCanvas");
  if (canvas && window.QRCode) {
    var ctx = canvas.getContext("2d");
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    QRCode.toCanvas(canvas, data.barcode || data.nomor_rekening, {
      width: 160,
      margin: 1,
      color: { dark: "#08182f", light: "#ffffff" }
    });
  }

  var modal = new bootstrap.Modal(document.getElementById("invQuickQrModal"));
  modal.show();
}
</script>
';

render_page('Galeri Foto Kartu Inventaris', $body, $extraHead, $extraScript);
