<?php
require __DIR__ . '/bootstrap.php';
require_login();

// Pastikan struktur tabel kartu inventaris terupdate
ensure_inventaris_kartu_columns();

// 1. Tentukan Sub-Tab Aktif ('inventaris' atau 'unit')
$subTab = trim((string)($_GET['sub'] ?? 'inventaris'));
if (!in_array($subTab, ['inventaris', 'unit'], true)) {
    $subTab = 'inventaris';
}


// 2. Ambil Master Data Umum
$standardCabangs = get_standard_cabang_list();
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24; // 24 kartu per halaman agar grid responsif rapi (2, 3, atau 4 kolom)
$search = trim((string)($_GET['q'] ?? ''));
$photoFilter = trim((string)($_GET['photo'] ?? 'all')); // 'all', 'with_photo', 'no_photo'

// Helper Query URL
if (!function_exists('gallery_query')) {
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
}

// Hitung total untuk badge switcher
$rawCardsForBadge = get_inventaris_kartu_rows();
$totalInvCount = count($rawCardsForBadge);

$rawAssetsForBadge = [];
if (is_google_cloud_mode()) {
    $rawAssetsForBadge = map_sheets_assets();
} else {
    try {
        $stB = db()->query(asset_query_base() . " ORDER BY a.id DESC LIMIT 3000");
        $rawAssetsForBadge = $stB ? $stB->fetchAll() : [];
    } catch (Throwable $e) {}
}
$totalUnitCount = count($rawAssetsForBadge);


// =========================================================================
// SUB-TAB 1: GALERI FOTO KARTU INVENTARIS (BANK CORE BANKING)
// =========================================================================
if ($subTab === 'inventaris') {
    $cabangCode = trim((string)($_GET['cabang'] ?? ''));
    if ($cabangCode === 'all' || $cabangCode === '0') $cabangCode = '';
    $jenisFilter = trim((string)($_GET['jenis'] ?? 'all'));

    $masterJenis = get_master_jenis_inventaris();
    $masterRuangan = get_master_ruangan_list();

    $branchStats = [
        '01' => ['name' => 'Kantor Pusat', 'count' => 0, 'photos' => 0, 'color' => '#2E7CF6', 'icon' => 'bi-building'],
        '02' => ['name' => 'Batulicin', 'count' => 0, 'photos' => 0, 'color' => '#16803C', 'icon' => 'bi-geo-alt'],
        '03' => ['name' => 'Martapura', 'count' => 0, 'photos' => 0, 'color' => '#D97706', 'icon' => 'bi-geo-alt'],
        '04' => ['name' => 'Tanjung', 'count' => 0, 'photos' => 0, 'color' => '#8B5CF6', 'icon' => 'bi-geo-alt'],
        '05' => ['name' => 'Handil Bakti', 'count' => 0, 'photos' => 0, 'color' => '#EC4899', 'icon' => 'bi-geo-alt'],
    ];

    $totalAll = $totalInvCount;
    $totalWithPhoto = 0;
    $totalNoPhoto = 0;
    $allPreparedCards = [];

    foreach ($rawCardsForBadge as $c) {
        $cid = (int)($c['id'] ?? 0);
        $rek = trim((string)($c['nomor_rekening'] ?? ''));
        $lokasi = trim((string)($c['lokasi'] ?? ''));

        // Deteksi Kode Cabang
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

        // Resolusi Foto: Hanya foto asli milik kartu inventaris ini
        $foto = trim((string)($c['foto'] ?? ''));
        $fotoBy = trim((string)($c['foto_updated_by'] ?? ''));
        $fotoAt = trim((string)($c['foto_updated_at'] ?? ''));

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

    // Filter Pencarian & Kriteria
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

    // Urutkan: Barang dengan foto di atas, lalu ID DESC
    usort($filteredCards, function($a, $b) {
        $hasA = !empty($a['resolved_foto']) ? 1 : 0;
        $hasB = !empty($b['resolved_foto']) ? 1 : 0;
        if ($hasA !== $hasB) return $hasB <=> $hasA;
        return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
    });

    // Pagination
    $totalFiltered = count($filteredCards);
    $totalPages = max(1, (int)ceil($totalFiltered / $perPage));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $perPage;
    $pageCards = array_slice($filteredCards, $offset, $perPage);

    // Kartu HTML Galeri Inventaris
    $galleryCardsHtml = '';
    foreach ($pageCards as $c) {
        $cid = (int)($c['id'] ?? 0);
        $rek = trim((string)($c['nomor_rekening'] ?? ''));
        $nama = trim((string)($c['nama_barang'] ?? ''));
        $tgl = trim((string)($c['tanggal_perolehan'] ?? ''));
        $ruangan = trim((string)($c['ruangan'] ?? $c['lokasi'] ?? '-'));
        $noSeri = trim((string)($c['nomor_seri_inventaris'] ?? ''));
        $jenis = trim((string)($c['jenis_inventaris'] ?? ''));
        $barcode = trim((string)($c['barcode_data'] ?? ''));
        $nomorGabungan = get_nomor_asset_gabungan($rek, $tgl);

        $foto = $c['resolved_foto'] ?? '';
        $fotoBy = $c['resolved_foto_by'] ?? '';
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
          <a href="'.e(gallery_query(['sub' => 'inventaris', 'q' => null, 'cabang' => null, 'photo' => 'all', 'jenis' => 'all'])).'" class="btn btn-outline-primary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter
          </a>
        </div>';
    }

    // Branch Nav Pills
    $branchPillsHtml = '<div class="branch-nav-bar custom-scrollbar mb-3">';
    $allActive = ($cabangCode === '');
    $branchPillsHtml .= '<a class="branch-nav-pill '.($allActive ? 'active' : '').'" href="'.e(gallery_query(['cabang' => null, 'page' => 1])).'">
      <i class="bi bi-grid-fill me-1"></i> Semua Cabang <span class="badge bg-secondary text-white rounded-pill ms-1">'.$totalAll.'</span>
    </a>';
    foreach ($branchStats as $code => $bs) {
        $isActive = ($cabangCode === $code);
        $branchPillsHtml .= '<a class="branch-nav-pill '.($isActive ? 'active' : '').'" href="'.e(gallery_query(['cabang' => $code, 'page' => 1])).'">
          <i class="bi '.e($bs['icon']).' me-1" style="color: '.e($bs['color']).'"></i> '.e($code).' '.e($bs['name']).'
          <span class="badge '.($isActive ? 'bg-primary' : 'bg-light text-dark border').' rounded-pill ms-1">'.$bs['count'].'</span>
          '.($bs['photos'] > 0 ? '<span class="badge bg-success text-white rounded-pill ms-0.5" title="'.$bs['photos'].' berfoto"><i class="bi bi-camera me-0.5"></i>'.$bs['photos'].'</span>' : '').'
        </a>';
    }
    $branchPillsHtml .= '</div>';

    // Dropdown Jenis Inventaris
    $optJenisHtml = '<option value="all">Semua Jenis Inventaris</option>';
    foreach ($masterJenis as $jKey => $jVal) {
        $optJenisHtml .= '<option value="'.$jKey.'"'.($jenisFilter === (string)$jKey ? ' selected' : '').'>'.e($jVal).'</option>';
    }

    // Toolbar Filter
    $filterToolbarHtml = '
    <div class="card p-3 border-0 shadow-sm bg-white rounded-3 mb-4">
      <form method="GET" action="'.e(module_url('asset_gallery.php')).'" class="row g-2 align-items-center">
        <input type="hidden" name="sub" value="inventaris">
        '.($cabangCode !== '' ? '<input type="hidden" name="cabang" value="'.e($cabangCode).'">' : '').'
        <div class="col-12 col-md-5">
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" name="q" class="form-control bg-light border-start-0" placeholder="Cari nama barang, no rekening, ruangan, SN..." value="'.e($search).'">
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
          <select name="jenis" class="form-select form-select-sm" onchange="this.form.submit()">
            '.$optJenisHtml.'
          </select>
        </div>
        <div class="col-12 col-md-1 d-flex gap-1">
          <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold">
            <i class="bi bi-funnel"></i>
          </button>
          '.($search !== '' || $photoFilter !== 'all' || $jenisFilter !== 'all' ? '
          <a href="'.e(module_url('asset_gallery.php', array_filter(['sub' => 'inventaris', 'cabang' => $cabangCode]))).'" class="btn btn-sm btn-light border text-danger" title="Reset Filter">
            <i class="bi bi-x-lg"></i>
          </a>' : '').'
        </div>
      </form>
    </div>';

    $pctPhoto = $totalAll > 0 ? round(($totalWithPhoto / $totalAll) * 100, 1) : 0;
    $statsCardsHtml = '
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
    </div>';

} else {
    // =========================================================================
    // SUB-TAB 2: GALERI FOTO UNIT PERANGKAT (ASET IT)
    // =========================================================================
    $cabangId = max(0, (int)($_GET['cabang'] ?? 0));
    $divisiId = max(0, (int)($_GET['divisi'] ?? 0));
    $cabangs = get_cabang_list();
    $divisis = get_divisi_list();

    $totalAll = $totalUnitCount;
    $totalWithPhoto = 0;
    $totalNoPhoto = 0;
    $branchPhotoCounts = [];

    foreach ($rawAssetsForBadge as $a) {
        $cId = (int)($a['id_cabang'] ?? 0);
        $hasFoto = !empty(trim((string)($a['foto'] ?? '')));
        if ($hasFoto) {
            $totalWithPhoto++;
            $branchPhotoCounts[$cId] = ($branchPhotoCounts[$cId] ?? 0) + 1;
        } else {
            $totalNoPhoto++;
        }
    }

    // Filter Aset IT
    $filteredCards = array_filter($rawAssetsForBadge, function($a) use ($search, $cabangId, $divisiId, $photoFilter) {
        if ($cabangId > 0 && (int)($a['id_cabang'] ?? 0) !== $cabangId) {
            return false;
        }
        if ($divisiId > 0 && (int)($a['id_divisi'] ?? 0) !== $divisiId) {
            return false;
        }
        $hasFoto = !empty(trim((string)($a['foto'] ?? '')));
        if ($photoFilter === 'with_photo' && !$hasFoto) return false;
        if ($photoFilter === 'no_photo' && $hasFoto) return false;

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
            if (strpos($haystack, $q) === false) return false;
        }
        return true;
    });

    // Urutkan
    usort($filteredCards, function($a, $b) {
        $hasA = !empty(trim((string)($a['foto'] ?? ''))) ? 1 : 0;
        $hasB = !empty(trim((string)($b['foto'] ?? ''))) ? 1 : 0;
        if ($hasA !== $hasB) return $hasB <=> $hasA;
        $cb = strcasecmp((string)($a['cabang_nama'] ?? ''), (string)($b['cabang_nama'] ?? ''));
        if ($cb !== 0) return $cb;
        return strcasecmp((string)($a['kode_inventaris'] ?? ''), (string)($b['kode_inventaris'] ?? ''));
    });

    // Pagination
    $totalFiltered = count($filteredCards);
    $totalPages = max(1, (int)ceil($totalFiltered / $perPage));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $perPage;
    $pageCards = array_slice($filteredCards, $offset, $perPage);

    // Kartu HTML Galeri Unit IT
    $galleryCardsHtml = '';
    foreach ($pageCards as $a) {
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
            <div class="position-relative overflow-hidden bg-light rounded-top-3" style="height: 195px; cursor: pointer;" onclick="openUnitLightbox(\''.e($fotoSrc).'\', \''.e(addslashes($device)).'\', \''.e(addslashes($kode)).'\', \''.e(addslashes($user)).'\', \''.e(addslashes($cabang)).'\', \''.e(addslashes($editUrl)).'\')">
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
            <div class="position-relative bg-light rounded-top-3 d-flex flex-column align-items-center justify-content-center border-bottom text-muted" style="height: 195px;">
              <div class="text-secondary opacity-50 mb-2"><i class="bi bi-camera-fill" style="font-size: 2.5rem;"></i></div>
              <span class="small text-muted fw-semibold mb-2">Belum ada foto fisik</span>
              <a href="'.e($editUrl).'" class="btn btn-xs btn-outline-primary py-1 px-2.5 rounded-pill shadow-xs" style="font-size: 0.72rem;">
                <i class="bi bi-plus-circle me-1"></i> Upload Foto
              </a>
              <span class="position-absolute top-2 start-2 badge bg-secondary bg-opacity-75 shadow-xs rounded-pill" style="font-size: 0.68rem;">
                Belum Ada
              </span>
            </div>';
        }

        $galleryCardsHtml .= '
        <div class="col-12 col-sm-6 col-md-4 col-xl-3 mb-4">
          <div class="card h-100 border shadow-xs rounded-3 overflow-hidden bg-white gallery-card d-flex flex-column justify-content-between">
            <div>
              '.$photoContent.'
              <div class="card-body p-3">
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
            </div>
            <div class="p-3 pt-0">
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
          <a href="'.e(gallery_query(['sub' => 'unit', 'q' => null, 'cabang' => null, 'divisi' => null, 'photo' => 'all'])).'" class="btn btn-outline-primary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter
          </a>
        </div>';
    }

    // Branch Nav Pills
    $branchPillsHtml = '<div class="branch-nav-bar custom-scrollbar mb-3">';
    $allActive = ($cabangId === 0);
    $branchPillsHtml .= '<a class="branch-nav-pill '.($allActive ? 'active' : '').'" href="'.e(gallery_query(['cabang' => null, 'page' => 1])).'">
      Semua Cabang <span class="badge bg-secondary text-white rounded-pill ms-1">'.$totalAll.'</span>
    </a>';
    foreach ($cabangs as $c) {
        $cId = (int)($c['id'] ?? 0);
        $cNama = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cId);
        $isActive = ($cId === $cabangId);
        $cCount = $branchPhotoCounts[$cId] ?? 0;
        $branchPillsHtml .= '<a class="branch-nav-pill '.($isActive ? 'active' : '').'" href="'.e(gallery_query(['cabang' => $cId, 'page' => 1])).'">
          '.e($cNama).'
          '.($cCount > 0 ? '<span class="badge bg-success text-white rounded-pill ms-1"><i class="bi bi-camera me-1"></i>'.$cCount.'</span>' : '').'
        </a>';
    }
    $branchPillsHtml .= '</div>';

    // Toolbar Filter
    $optDivHtml = '<option value="0">Semua Divisi</option>';
    foreach ($divisis as $d) {
        $did = (int)($d['id'] ?? 0);
        $dnama = $d['nama'] ?? $d['nama_divisi'] ?? ('Divisi #' . $did);
        $optDivHtml .= '<option value="'.$did.'"'.($did === $divisiId ? ' selected' : '').'>'.e($dnama).'</option>';
    }

    $filterToolbarHtml = '
    <div class="card p-3 border-0 shadow-sm bg-white rounded-3 mb-4">
      <form method="GET" action="'.e(module_url('asset_gallery.php')).'" class="row g-2 align-items-center">
        <input type="hidden" name="sub" value="unit">
        <input type="hidden" name="cabang" value="'.e($cabangId).'">
        <div class="col-12 col-md-5">
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" name="q" class="form-control bg-light border-start-0" placeholder="Cari merk, model, pengguna, SN..." value="'.e($search).'">
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
        <div class="col-12 col-md-1 d-flex gap-1">
          <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold">
            <i class="bi bi-funnel"></i>
          </button>
          '.($search !== '' || $photoFilter !== 'all' || $divisiId > 0 ? '
          <a href="'.e(module_url('asset_gallery.php', array_filter(['sub' => 'unit', 'cabang' => $cabangId]))).'" class="btn btn-sm btn-light border text-danger" title="Reset Filter">
            <i class="bi bi-x-lg"></i>
          </a>' : '').'
        </div>
      </form>
    </div>';

    $pctPhoto = $totalAll > 0 ? round(($totalWithPhoto / $totalAll) * 100, 1) : 0;
    $statsCardsHtml = '
    <div class="row g-3 mb-4">
      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card p-3 border-0 shadow-sm bg-white rounded-3 h-100 border-start border-primary border-4">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Total Perangkat IT</div>
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
.gallery-tab-btn {
  padding: 8px 20px;
  font-size: 0.88rem;
  font-weight: 600;
  border-radius: 9999px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  transition: all 0.15s ease;
}
</style>
';

$body = '
<!-- Header Page -->
<div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 mb-3">
  <div>
    <div class="tech-label mb-1">VISUAL INVENTORY ARCHIVE</div>
    <h1 class="h3 mb-1"><i class="bi bi-images text-primary me-2"></i>Galeri Foto Inventaris &amp; Unit</h1>
    <p class="text-secondary small mb-0">Arsip foto fisik perangkat IT dan kartu inventaris Core Banking PT BPR Mitratama Arthabuana.</p>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">

    <a href="'.e(module_url('dashboard.php', ['tab' => 'kartu'])).'" class="btn btn-outline-primary d-inline-flex align-items-center gap-1 fw-semibold shadow-xs">
      <i class="bi bi-credit-card-2-front"></i> Tabel Kartu
    </a>
    <a href="'.e(module_url('assets.php')).'" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 fw-semibold shadow-xs">
      <i class="bi bi-table"></i> Tabel Aset
    </a>
    <a href="'.e(module_url('dashboard.php')).'" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 fw-semibold shadow-xs">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>
  </div>
</div>

<!-- Sub-Tab Switcher Bar (1 Halaman, 2 Sub) -->
<div class="d-flex gap-2 mb-4 p-1 bg-light rounded-pill border d-inline-flex shadow-xs">
  <a href="'.e(gallery_query(['sub' => 'inventaris', 'page' => 1, 'q' => null, 'cabang' => null, 'photo' => 'all'])).'" class="gallery-tab-btn '.($subTab === 'inventaris' ? 'bg-primary text-white shadow-xs' : 'text-secondary hover-primary').'">
    <i class="bi bi-collection me-1.5"></i> Galeri Foto Inventaris (Bank)
    <span class="badge '.($subTab === 'inventaris' ? 'bg-white text-primary' : 'bg-secondary text-white').' rounded-pill ms-1.5" style="font-size: 0.72rem;">'.$totalInvCount.'</span>
  </a>
  <a href="'.e(gallery_query(['sub' => 'unit', 'page' => 1, 'q' => null, 'cabang' => null, 'photo' => 'all'])).'" class="gallery-tab-btn '.($subTab === 'unit' ? 'bg-primary text-white shadow-xs' : 'text-secondary hover-primary').'">
    <i class="bi bi-pc-display me-1.5"></i> Galeri Unit Perangkat (IT)
    <span class="badge '.($subTab === 'unit' ? 'bg-white text-primary' : 'bg-secondary text-white').' rounded-pill ms-1.5" style="font-size: 0.72rem;">'.$totalUnitCount.'</span>
  </a>
</div>

<!-- Stats Card Row -->
'.$statsCardsHtml.'

<!-- Navigasi Cabang -->
'.$branchPillsHtml.'

<!-- Filter & Search Toolbar -->
'.$filterToolbarHtml.'

<!-- Header Info Hasil Pencarian -->
<div class="d-flex align-items-center justify-content-between mb-3 text-muted small">
  <div>
    Menampilkan <strong>'.count($pageCards).'</strong> dari <strong>'.$totalFiltered.'</strong> '.($subTab === 'inventaris' ? 'barang inventaris' : 'perangkat IT').'
    '.($search !== '' ? ' untuk kata kunci "<em>'.e($search).'</em>"' : '').'
  </div>
  <div>Halaman <strong>'.$page.'</strong> dari <strong>'.$totalPages.'</strong></div>
</div>

<!-- Grid Kartu Foto -->
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
            <i class="bi bi-credit-card-2-front me-1"></i> Buka Kartu
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
        <div class="p-2 border rounded-3 bg-white d-inline-block shadow-xs mb-2" style="min-width: 176px; min-height: 176px;">
          <div id="invModalQrContainer" style="display: flex; align-items: center; justify-content: center; width: 160px; height: 160px; margin: 0 auto;">
            <canvas id="invModalQrCanvas" width="160" height="160" style="display: block;"></canvas>
          </div>
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

<!-- Modal Quick QR Stiker Unit IT -->
<div class="modal fade" id="quickQrModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-bold text-dark"><i class="bi bi-qr-code text-primary me-1"></i> QR Label Unit</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-3">
        <div class="p-2 border rounded-3 bg-white d-inline-block shadow-xs mb-2">
          <canvas id="modalQrCanvas" width="160" height="160" style="display: block; margin: 0 auto;"></canvas>
        </div>
        <div class="font-monospace fw-bold text-dark mb-1" id="modalQrKode" style="font-size: 0.82rem;"></div>
        <div class="fw-semibold text-secondary small mb-1" id="modalQrDevice"></div>
        <div class="badge bg-light text-muted border mb-3" id="modalQrUser"></div>
        <div class="d-grid gap-2">
          <a id="modalQrScanBtn" href="" class="btn btn-sm btn-primary rounded-pill">
            <i class="bi bi-arrow-up-right me-1"></i> Buka Detail Unit
          </a>
          <a id="modalQrEditBtn" href="" class="btn btn-sm btn-outline-secondary rounded-pill">
            <i class="bi bi-pencil me-1"></i> Edit Data / Foto
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
// Lightbox Kartu Inventaris
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

// Quick Barcode Kartu Inventaris
function openInvQrModal(data) {
  if (!data) return;
  document.getElementById("invModalRekening").textContent = data.nomor_rekening || "";
  document.getElementById("invModalNama").textContent = data.nama_barang || "";
  document.getElementById("invModalRuangan").textContent = (data.cabang || "") + " • " + (data.ruangan || "");
  document.getElementById("invModalDetailBtn").href = data.detail_url || "#";
  document.getElementById("invModalPrintBtn").href = data.print_url || "#";

  var qrPayload = data.detail_url || data.barcode || data.nomor_rekening || "";
  var container = document.getElementById("invModalQrContainer");
  if (container) {
    container.innerHTML = \'<canvas id="invModalQrCanvas" width="160" height="160" style="display: block;"></canvas>\';
    var canvas = document.getElementById("invModalQrCanvas");
    
    var rendered = false;
    if (window.QRCode && typeof window.QRCode.toCanvas === "function" && canvas) {
      try {
        window.QRCode.toCanvas(canvas, qrPayload, {
          width: 160,
          margin: 1,
          color: { dark: "#08182f", light: "#ffffff" }
        }, function(err) {
          if (err) {
            fallbackQrImg(container, qrPayload);
          }
        });
        rendered = true;
      } catch (e) {
        rendered = false;
      }
    }
    
    if (!rendered) {
      fallbackQrImg(container, qrPayload);
    }
  }

  var modal = new bootstrap.Modal(document.getElementById("invQuickQrModal"));
  modal.show();
}

function fallbackQrImg(container, text) {
  if (!container) return;
  var encoded = encodeURIComponent(text);
  container.innerHTML = \'<img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=\' + encoded + \'" width="160" height="160" style="display:block; border-radius: 4px;" alt="QR Code">\';
}

// Lightbox Unit IT
function openUnitLightbox(imgSrc, title, code, user, branch, editUrl) {
  var data = {
    foto: imgSrc,
    nama_barang: title,
    nomor_rekening: code,
    cabang: branch,
    detail_url: editUrl,
    foto_by: user
  };
  openInvGalleryLightbox(data);
}

// Quick QR Unit IT
function openQrModal(data) {
  if (!data) return;
  document.getElementById("modalQrKode").textContent = data.kode || "";
  document.getElementById("modalQrDevice").textContent = data.device || "";
  document.getElementById("modalQrUser").textContent = (data.cabang || "") + " • " + (data.user || "");
  document.getElementById("modalQrScanBtn").href = data.scan_url || "#";
  document.getElementById("modalQrEditBtn").href = data.edit_url || "#";

  var canvas = document.getElementById("modalQrCanvas");
  if (canvas && window.QRCode) {
    var ctx = canvas.getContext("2d");
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    QRCode.toCanvas(canvas, data.scan_url || data.kode, {
      width: 160,
      margin: 1,
      color: { dark: "#08182f", light: "#ffffff" }
    });
  }

  var modal = new bootstrap.Modal(document.getElementById("quickQrModal"));
  modal.show();
}
</script>
';

render_page('Galeri Foto Inventaris & Unit', $body, $extraHead, $extraScript);
