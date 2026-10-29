<?php
require __DIR__ . '/bootstrap.php';
require_login();

// Pastikan struktur tabel kartu inventaris terupdate
ensure_inventaris_kartu_columns();

$isHrdOnly = (is_hrd() && !is_admin());

// 1. Tentukan Sub-Tab Aktif ('inventaris' atau 'unit')
$subTab = trim((string)($_GET['sub'] ?? 'inventaris'));
if ($isHrdOnly || !in_array($subTab, ['inventaris', 'unit'], true)) {
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
        '01' => ['name' => 'Kantor Pusat', 'count' => 0, 'photos' => 0, 'color' => '#0E5C58', 'icon' => 'bi-building'],
        '02' => ['name' => 'Batulicin', 'count' => 0, 'photos' => 0, 'color' => '#10B981', 'icon' => 'bi-geo-alt'],
        '03' => ['name' => 'Martapura', 'count' => 0, 'photos' => 0, 'color' => '#D97706', 'icon' => 'bi-geo-alt'],
        '04' => ['name' => 'Tanjung', 'count' => 0, 'photos' => 0, 'color' => '#6366F1', 'icon' => 'bi-geo-alt'],
        '05' => ['name' => 'Handil Bakti', 'count' => 0, 'photos' => 0, 'color' => '#0284C7', 'icon' => 'bi-geo-alt'],
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
        $c['branch_color'] = $branchStats[$assignedCode]['color'] ?? '#0E5C58';
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

    // Kartu HTML Galeri Inventaris (Kombai Teal Design)
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
            <div class="position-relative overflow-hidden bg-[#F4F5F7] rounded-top-[9px]" style="height: 195px; cursor: pointer;" onclick="openInvGalleryLightbox('.$cardJson.')">
              <img src="'.e($fotoSrc).'" class="w-100 h-100 object-fit-cover gallery-img-thumb transition-transform" alt="Foto '.e($nama).'" loading="lazy" onerror="handleGalleryImgError(this)">
              <div class="gallery-hover-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-40 text-white opacity-0 transition-opacity">
                <span class="px-3 py-1.5 bg-[#0E5C58] text-white rounded-full text-[12px] font-semibold shadow-xs">
                  <i class="bi bi-zoom-in me-1"></i> Perbesar Foto
                </span>
              </div>
              <span class="position-absolute top-2.5 start-2.5 px-2 py-0.5 bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0] rounded-full text-[11px] font-bold shadow-xs">
                <i class="bi bi-check-circle-fill me-1"></i>Ada Foto
              </span>
              '.($fotoBy !== '' ? '
              <span class="position-absolute bottom-2.5 start-2.5 px-2 py-0.5 bg-black bg-opacity-70 text-white rounded-full text-[10px] font-medium shadow-xs" title="Diverifikasi oleh: '.e($fotoBy).'">
                <i class="bi bi-patch-check-fill text-[#34D399] me-1"></i>'.e(mb_strimwidth($fotoBy, 0, 18, '...')).'
              </span>' : '').'
            </div>';
        } else {
            $photoContent = '
            <div class="position-relative bg-[#F8FAF9] rounded-top-[9px] d-flex flex-column align-items-center justify-content-center border-b border-[#D1D5D8] text-[#53595F]" style="height: 195px;">
              <div class="text-[#9CA3AF] mb-2"><i class="bi bi-box-seam" style="font-size: 2.5rem;"></i></div>
              <span class="text-[12px] text-[#53595F] font-medium mb-2">Belum ada foto fisik barang</span>
              <a href="'.e($detailUrl).'" class="h-[28px] px-3 bg-white border border-[#0E5C58] text-[#0E5C58] hover:bg-[#D9F1EF] rounded-[6px] text-[11px] font-semibold transition-colors inline-flex items-center gap-1 text-decoration-none shadow-xs">
                <i class="bi bi-camera"></i> Ambil / Upload
              </a>
              <span class="position-absolute top-2.5 start-2.5 px-2 py-0.5 bg-[#F3F4F6] text-[#6B7280] border border-[#E5E7EB] rounded-full text-[10px] font-semibold shadow-xs">
                Belum Difoto
              </span>
            </div>';
        }

        $galleryCardsHtml .= '
        <div class="col-12 col-sm-6 col-md-4 col-xl-3 mb-4">
          <div class="gallery-card bg-white border border-[#D1D5D8] rounded-[10px] overflow-hidden shadow-xs h-100 flex flex-col justify-between">
            <div>
              '.$photoContent.'
              <div class="p-3">
                <div class="flex items-center justify-between gap-1 mb-1.5 flex-wrap">
                  <span class="px-2 py-0.5 text-[10px] font-mono font-bold rounded bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC]">
                    '.e($c['branch_code']).' &bull; '.e($c['branch_name']).'
                  </span>
                  '.($jenis !== '' ? '
                  <span class="px-2 py-0.5 text-[10px] font-sans rounded bg-[#F4F5F7] text-[#53595F] border border-[#D1D5D8]" title="'.e($jenis).'">
                    '.e(mb_strimwidth($jenis, 0, 16, '...')).'
                  </span>' : '').'
                </div>

                <div class="font-mono text-[12px] font-bold text-[#0E5C58] mb-1 flex items-center justify-between">
                  <span><i class="bi bi-hash me-0.5"></i>'.e($rek).'</span>
                  '.($noSeri !== '' ? '<span class="text-[#53595F] font-normal text-[11px]" title="No Seri: '.e($noSeri).'">SN: '.e(mb_strimwidth($noSeri, 0, 10, '...')).'</span>' : '').'
                </div>

                <h6 class="font-bold text-[#1B2A3A] mb-1.5 text-[13px] leading-snug line-clamp-2" title="'.e($nama).'" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.4rem;">
                  <a href="'.e($detailUrl).'" class="text-[#1B2A3A] hover:text-[#0E5C58] text-decoration-none">'.e($nama).'</a>
                </h6>

                <div class="text-[11px] text-[#53595F]">
                  <div class="truncate mb-0.5" title="Ruangan: '.e($ruangan).'"><i class="bi bi-geo-alt text-[#0E5C58] me-1"></i><span class="text-[#1B2A3A] font-medium">'.e($ruangan).'</span></div>
                  <div class="truncate" title="Perolehan: '.e(format_indo_date($tgl)).'"><i class="bi bi-calendar3 me-1"></i>'.e(format_indo_date($tgl)).'</div>
                </div>
              </div>
            </div>

            <div class="p-3 pt-0">
              <div class="pt-2 border-t border-[#E5E7EB] flex items-center justify-between gap-1.5">
                <a href="'.e($detailUrl).'" class="h-[30px] px-3 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[6px] text-[12px] font-semibold transition-colors inline-flex items-center gap-1 text-decoration-none border-0 shadow-xs">
                  <i class="bi bi-credit-card-2-front"></i> Buka Kartu
                </a>
                <div class="flex items-center gap-1">
                  <button type="button" class="h-[30px] w-[30px] bg-white border border-[#D1D5D8] rounded-[6px] text-[#53595F] hover:bg-[#F4F5F7] transition-colors inline-flex items-center justify-center cursor-pointer shadow-xs" title="Lihat Barcode &amp; Info Cepat" onclick="openInvQrModal('.$cardJson.')">
                    <i class="bi bi-qr-code"></i>
                  </button>
                  <a href="'.e($printUrl).'" target="_blank" class="h-[30px] w-[30px] bg-white border border-[#D1D5D8] rounded-[6px] text-[#53595F] hover:bg-[#F4F5F7] transition-colors inline-flex items-center justify-center text-decoration-none shadow-xs" title="Cetak Kartu A4">
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
          <div class="text-[#9CA3AF] opacity-50 mb-3"><i class="bi bi-collection" style="font-size: 3.5rem;"></i></div>
          <h5 class="font-bold text-[#1B2A3A]">Tidak ada kartu inventaris yang ditemukan</h5>
          <p class="text-[#53595F] text-[13px]">Coba ubah kata kunci pencarian atau ganti filter cabang di atas.</p>
          <a href="'.e(gallery_query(['sub' => 'inventaris', 'q' => null, 'cabang' => null, 'photo' => 'all', 'jenis' => 'all'])).'" class="h-[34px] px-3.5 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1 text-decoration-none shadow-xs">
            <i class="bi bi-arrow-counterclockwise"></i> Reset Filter
          </a>
        </div>';
    }

    // Branch Nav Pills (Kombai Clean Design)
    $branchPillsHtml = '';
    if (!$isHrdOnly) {
        $branchPillsHtml = '
        <div class="bg-white border border-[#D1D5D8] rounded-[10px] p-3 mb-3 shadow-xs">
          <div class="flex items-center justify-between mb-2 pb-1.5 border-b border-[#E5E7EB]">
            <div class="text-[12px] font-bold text-[#1B2A3A] uppercase tracking-wide flex items-center gap-1.5">
              <i class="bi bi-geo-alt-fill text-[#0E5C58]"></i> Filter Berdasarkan Kantor Cabang
            </div>
            <span class="text-[11px] font-semibold text-[#53595F] bg-[#F4F5F7] px-2 py-0.5 rounded border border-[#D1D5D8]">5 Cabang Operasional</span>
          </div>
          <div class="branch-nav-bar flex items-center gap-2 overflow-x-auto pb-1">';
        
        $allActive = ($cabangCode === '');
        $branchPillsHtml .= '<a class="branch-nav-pill '.($allActive ? 'active' : '').'" href="'.e(gallery_query(['cabang' => null, 'page' => 1])).'">
          <i class="bi bi-grid-fill"></i>
          <span class="branch-name">Semua Cabang</span>
          <span class="badge rounded-full px-2 py-0.5 text-[10px] '.($allActive ? 'bg-white text-[#0E5C58] font-bold' : 'bg-[#F4F5F7] text-[#53595F] border border-[#D1D5D8]').'">'.$totalAll.'</span>
        </a>';

        foreach ($branchStats as $code => $bs) {
            $isActive = ($cabangCode === $code);
            $branchPillsHtml .= '<a class="branch-nav-pill '.($isActive ? 'active' : '').'" href="'.e(gallery_query(['cabang' => $code, 'page' => 1])).'">
              <span class="font-mono text-[11px] font-bold me-1">'.$code.'</span>
              <span class="branch-name">'.e($bs['name']).'</span>
              <span class="badge rounded-full px-2 py-0.5 text-[10px] '.($isActive ? 'bg-white text-[#0E5C58] font-bold' : 'bg-[#F4F5F7] text-[#53595F] border border-[#D1D5D8]').'">'.$bs['count'].'</span>
              '.($bs['photos'] > 0 ? '<span class="badge rounded-full px-1.5 py-0.5 text-[10px] '.($isActive ? 'bg-white text-[#0E5C58] font-bold' : 'bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0]').'" title="'.$bs['photos'].' berfoto"><i class="bi bi-camera-fill me-0.5"></i>'.$bs['photos'].'</span>' : '').'
            </a>';
        }
        $branchPillsHtml .= '</div></div>';
    }

    // Dropdown Jenis Inventaris
    $optJenisHtml = '<option value="all">Semua Jenis Inventaris</option>';
    foreach ($masterJenis as $jKey => $jVal) {
        $optJenisHtml .= '<option value="'.$jKey.'"'.($jenisFilter === (string)$jKey ? ' selected' : '').'>'.e($jVal).'</option>';
    }

    // Toolbar Filter Simetris 36px
    $filterToolbarHtml = '
    <div class="bg-white border border-[#D1D5D8] rounded-[10px] p-3.5 mb-4 shadow-xs">
      <form method="GET" action="'.e(module_url('asset_gallery.php')).'" class="row g-2 align-items-center m-0">
        <input type="hidden" name="sub" value="inventaris">
        '.($cabangCode !== '' ? '<input type="hidden" name="cabang" value="'.e($cabangCode).'">' : '').'
        
        <div class="col-12 col-md-5 p-0 pe-md-1">
          <div class="input-group">
            <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 8px 0 0 8px; height: 36px; border-color: #D1D5D8;"><i class="bi bi-search"></i></span>
            <input type="text" name="q" class="form-control border-start-0 ps-0" style="border-radius: 0 8px 8px 0; font-size: 13px; height: 36px; border-color: #D1D5D8;" placeholder="Ketik nama barang, no rekening, ruangan, SN..." value="'.e($search).'">
            '.($search !== '' ? '<a href="'.e(gallery_query(['q' => null, 'page' => 1])).'" class="btn btn-outline-secondary border" style="border-radius: 8px; height: 36px; border-color: #D1D5D8;" title="Reset Kata Kunci"><i class="bi bi-x-lg"></i></a>' : '').'
          </div>
        </div>

        <div class="col-12 col-sm-6 col-md-3 p-0 px-md-1">
          <select name="photo" class="form-select form-select-sm" style="border-radius: 8px; height: 36px; font-size: 13px; border-color: #D1D5D8;" onchange="this.form.submit()">
            <option value="all"'.($photoFilter === 'all' ? ' selected' : '').'>Semua Status Foto ('.$totalAll.')</option>
            <option value="with_photo"'.($photoFilter === 'with_photo' ? ' selected' : '').'>Sudah Ada Foto ('.$totalWithPhoto.')</option>
            <option value="no_photo"'.($photoFilter === 'no_photo' ? ' selected' : '').'>Belum Difoto ('.$totalNoPhoto.')</option>
          </select>
        </div>

        <div class="col-12 col-sm-6 col-md-3 p-0 px-md-1">
          <select name="jenis" class="form-select form-select-sm" style="border-radius: 8px; height: 36px; font-size: 13px; border-color: #D1D5D8;" onchange="this.form.submit()">
            '.$optJenisHtml.'
          </select>
        </div>

        <div class="col-12 col-md-1 p-0 ps-md-1 d-flex gap-1">
          <button type="submit" class="h-[36px] px-3 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[13px] font-semibold transition-colors flex-fill inline-flex items-center justify-center gap-1 border-0 shadow-xs cursor-pointer" title="Terapkan Filter">
            <i class="bi bi-funnel"></i>
          </button>
          '.($search !== '' || $photoFilter !== 'all' || $jenisFilter !== 'all' ? '
          <a href="'.e(module_url('asset_gallery.php', array_filter(['sub' => 'inventaris', 'cabang' => $cabangCode]))).'" class="h-[36px] w-[36px] bg-white border border-[#D1D5D8] rounded-[8px] text-[#53595F] hover:bg-[#F4F5F7] transition-colors inline-flex items-center justify-center text-decoration-none shadow-xs shrink-0" title="Reset Semua Filter">
            <i class="bi bi-arrow-counterclockwise"></i>
          </a>' : '').'
        </div>
      </form>
    </div>';

    $pctPhoto = $totalAll > 0 ? round(($totalWithPhoto / $totalAll) * 100, 1) : 0;

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

    // Kartu HTML Galeri Unit IT (Kombai Teal Design)
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
            'scan_url' => module_url('scan.php', ['token' => $token]),
            'edit_url' => $editUrl
        ];
        $qrJson = htmlspecialchars(json_encode($qrData, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');

        $unitJsonData = [
            'id' => $aid,
            'nomor_rekening' => $kode,
            'nama_barang' => $device,
            'ruangan' => $divisi ? ($cabang . ' - ' . $divisi) : $cabang,
            'cabang' => $cabang,
            'foto' => $fotoSrc,
            'foto_by' => $user,
            'detail_url' => $editUrl,
            'barcode' => $kode
        ];
        $unitJson = htmlspecialchars(json_encode($unitJsonData, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');

        if ($fotoSrc !== '') {
            $photoContent = '
            <div class="position-relative overflow-hidden bg-[#F4F5F7] rounded-top-[9px]" style="height: 195px; cursor: pointer;" onclick="openUnitLightbox('.$unitJson.')">
              <img src="'.e($fotoSrc).'" class="w-100 h-100 object-fit-cover gallery-img-thumb transition-transform" alt="Foto '.e($device).'" loading="lazy" onerror="handleGalleryImgError(this)">
              <div class="gallery-hover-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-40 text-white opacity-0 transition-opacity">
                <span class="px-3 py-1.5 bg-[#0E5C58] text-white rounded-full text-[12px] font-semibold shadow-xs">
                  <i class="bi bi-zoom-in me-1"></i> Perbesar Foto
                </span>
              </div>
              <span class="position-absolute top-2.5 start-2.5 px-2 py-0.5 bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0] rounded-full text-[11px] font-bold shadow-xs">
                <i class="bi bi-check-circle-fill me-1"></i>Ada Foto
              </span>
            </div>';
        } else {
            $photoContent = '
            <div class="position-relative bg-[#F8FAF9] rounded-top-[9px] d-flex flex-column align-items-center justify-content-center border-b border-[#D1D5D8] text-[#53595F]" style="height: 195px;">
              <div class="text-[#9CA3AF] mb-2"><i class="bi bi-pc-display" style="font-size: 2.5rem;"></i></div>
              <span class="text-[12px] text-[#53595F] font-medium mb-2">Belum ada foto fisik unit</span>
              <a href="'.e($editUrl).'" class="h-[28px] px-3 bg-white border border-[#0E5C58] text-[#0E5C58] hover:bg-[#D9F1EF] rounded-[6px] text-[11px] font-semibold transition-colors inline-flex items-center gap-1 text-decoration-none shadow-xs">
                <i class="bi bi-camera"></i> Upload Foto
              </a>
              <span class="position-absolute top-2.5 start-2.5 px-2 py-0.5 bg-[#F3F4F6] text-[#6B7280] border border-[#E5E7EB] rounded-full text-[10px] font-semibold shadow-xs">
                Belum Difoto
              </span>
            </div>';
        }

        $galleryCardsHtml .= '
        <div class="col-12 col-sm-6 col-md-4 col-xl-3 mb-4">
          <div class="gallery-card bg-white border border-[#D1D5D8] rounded-[10px] overflow-hidden shadow-xs h-100 flex flex-col justify-between">
            <div>
              '.$photoContent.'
              <div class="p-3">
                <div class="flex items-center justify-between gap-1 mb-1.5 flex-wrap">
                  <span class="px-2 py-0.5 text-[10px] font-mono font-bold rounded bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC]">
                    <i class="bi bi-qr-code me-0.5"></i> '.e($kode).'
                  </span>
                  <span class="px-2 py-0.5 text-[10px] font-sans rounded bg-[#F4F5F7] text-[#53595F] border border-[#D1D5D8]">
                    '.e($cabang).'
                  </span>
                </div>

                <h6 class="font-bold text-[#1B2A3A] mb-1.5 text-[13px] leading-snug line-clamp-2" title="'.e($device).'" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.4rem;">
                  <a href="'.e($editUrl).'" class="text-[#1B2A3A] hover:text-[#0E5C58] text-decoration-none">'.e($device).'</a>
                </h6>

                <div class="text-[11px] text-[#53595F]">
                  <div class="truncate mb-0.5" title="Pengguna: '.e($user).'"><i class="bi bi-person text-[#0E5C58] me-1"></i><span class="text-[#1B2A3A] font-medium">'.e($user).'</span></div>
                  <div class="truncate" title="'.e($cabang.($divisi ? ' - '.$divisi : '')).'"><i class="bi bi-building me-1"></i>'.e($cabang).($divisi ? ' &bull; '.e($divisi) : '').'</div>
                </div>
              </div>
            </div>

            <div class="p-3 pt-0">
              <div class="pt-2 border-t border-[#E5E7EB] flex items-center justify-between gap-1.5">
                <button type="button" class="h-[30px] px-3 bg-white border border-[#0E5C58] text-[#0E5C58] hover:bg-[#D9F1EF] rounded-[6px] text-[12px] font-semibold transition-colors inline-flex items-center gap-1 shadow-xs cursor-pointer" onclick="openQrModal('.$qrJson.')" title="Lihat Stiker QR &amp; Foto">
                  <i class="bi bi-qr-code"></i> Lihat QR
                </button>
                <div class="flex items-center gap-1">
                  <a href="'.e($editUrl).'" class="h-[30px] w-[30px] bg-white border border-[#D1D5D8] rounded-[6px] text-[#53595F] hover:bg-[#F4F5F7] transition-colors inline-flex items-center justify-center text-decoration-none shadow-xs" title="Ganti / Edit Foto">
                    <i class="bi bi-camera"></i>
                  </a>
                  <a href="'.e(module_url('scan.php', ['token' => $token])).'" class="h-[30px] w-[30px] bg-white border border-[#D1D5D8] rounded-[6px] text-[#53595F] hover:bg-[#F4F5F7] transition-colors inline-flex items-center justify-center text-decoration-none shadow-xs" title="Buka Detail Aset">
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
          <div class="text-[#9CA3AF] opacity-50 mb-3"><i class="bi bi-images" style="font-size: 3.5rem;"></i></div>
          <h5 class="font-bold text-[#1B2A3A]">Tidak ada foto perangkat yang ditemukan</h5>
          <p class="text-[#53595F] text-[13px]">Coba ubah kata kunci pencarian atau ganti filter cabang di atas.</p>
          <a href="'.e(gallery_query(['sub' => 'unit', 'q' => null, 'cabang' => null, 'divisi' => null, 'photo' => 'all'])).'" class="h-[34px] px-3.5 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1 text-decoration-none shadow-xs">
            <i class="bi bi-arrow-counterclockwise"></i> Reset Filter
          </a>
        </div>';
    }

    // Branch Nav Pills (Kombai Clean Design)
    $branchPillsHtml = '
    <div class="bg-white border border-[#D1D5D8] rounded-[10px] p-3 mb-3 shadow-xs">
      <div class="flex items-center justify-between mb-2 pb-1.5 border-b border-[#E5E7EB]">
        <div class="text-[12px] font-bold text-[#1B2A3A] uppercase tracking-wide flex items-center gap-1.5">
          <i class="bi bi-geo-alt-fill text-[#0E5C58]"></i> Filter Berdasarkan Kantor Cabang
        </div>
        <span class="text-[11px] font-semibold text-[#53595F] bg-[#F4F5F7] px-2 py-0.5 rounded border border-[#D1D5D8]">'.count($cabangs).' Cabang Terdaftar</span>
      </div>
      <div class="branch-nav-bar flex items-center gap-2 overflow-x-auto pb-1">';
    
    $allActive = ($cabangId === 0);
    $branchPillsHtml .= '<a class="branch-nav-pill '.($allActive ? 'active' : '').'" href="'.e(gallery_query(['cabang' => null, 'page' => 1])).'">
      <i class="bi bi-grid-fill"></i>
      <span class="branch-name">Semua Cabang</span>
      <span class="badge rounded-full px-2 py-0.5 text-[10px] '.($allActive ? 'bg-white text-[#0E5C58] font-bold' : 'bg-[#F4F5F7] text-[#53595F] border border-[#D1D5D8]').'">'.$totalAll.'</span>
    </a>';

    foreach ($cabangs as $c) {
        $cId = (int)($c['id'] ?? 0);
        $cNama = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cId);
        $isActive = ($cId === $cabangId);
        $cCount = $branchPhotoCounts[$cId] ?? 0;
        $branchPillsHtml .= '<a class="branch-nav-pill '.($isActive ? 'active' : '').'" href="'.e(gallery_query(['cabang' => $cId, 'page' => 1])).'">
          <span class="branch-name">'.e($cNama).'</span>
          '.($cCount > 0 ? '<span class="badge rounded-full px-1.5 py-0.5 text-[10px] '.($isActive ? 'bg-white text-[#0E5C58] font-bold' : 'bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0]').'" title="'.$cCount.' unit berfoto"><i class="bi bi-camera-fill me-0.5"></i>'.$cCount.'</span>' : '').'
        </a>';
    }
    $branchPillsHtml .= '</div></div>';

    // Dropdown Divisi
    $optDivHtml = '<option value="0">Semua Divisi</option>';
    foreach ($divisis as $d) {
        $dId = (int)($d['id'] ?? 0);
        $dNama = $d['nama'] ?? $d['nama_divisi'] ?? ('Divisi #' . $dId);
        $optDivHtml .= '<option value="'.$dId.'"'.($divisiId === $dId ? ' selected' : '').'>'.e($dNama).'</option>';
    }

    // Toolbar Filter Unit Simetris 36px
    $filterToolbarHtml = '
    <div class="bg-white border border-[#D1D5D8] rounded-[10px] p-3.5 mb-4 shadow-xs">
      <form method="GET" action="'.e(module_url('asset_gallery.php')).'" class="row g-2 align-items-center m-0">
        <input type="hidden" name="sub" value="unit">
        '.($cabangId > 0 ? '<input type="hidden" name="cabang" value="'.$cabangId.'">' : '').'
        
        <div class="col-12 col-md-5 p-0 pe-md-1">
          <div class="input-group">
            <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 8px 0 0 8px; height: 36px; border-color: #D1D5D8;"><i class="bi bi-search"></i></span>
            <input type="text" name="q" class="form-control border-start-0 ps-0" style="border-radius: 0 8px 8px 0; font-size: 13px; height: 36px; border-color: #D1D5D8;" placeholder="Cari nama perangkat, merk, model, pengguna, SN..." value="'.e($search).'">
            '.($search !== '' ? '<a href="'.e(gallery_query(['q' => null, 'page' => 1])).'" class="btn btn-outline-secondary border" style="border-radius: 8px; height: 36px; border-color: #D1D5D8;" title="Reset Kata Kunci"><i class="bi bi-x-lg"></i></a>' : '').'
          </div>
        </div>

        <div class="col-12 col-sm-6 col-md-3 p-0 px-md-1">
          <select name="photo" class="form-select form-select-sm" style="border-radius: 8px; height: 36px; font-size: 13px; border-color: #D1D5D8;" onchange="this.form.submit()">
            <option value="all"'.($photoFilter === 'all' ? ' selected' : '').'>Semua Status Foto ('.$totalAll.')</option>
            <option value="with_photo"'.($photoFilter === 'with_photo' ? ' selected' : '').'>Sudah Ada Foto ('.$totalWithPhoto.')</option>
            <option value="no_photo"'.($photoFilter === 'no_photo' ? ' selected' : '').'>Belum Difoto ('.$totalNoPhoto.')</option>
          </select>
        </div>

        <div class="col-12 col-sm-6 col-md-3 p-0 px-md-1">
          <select name="divisi" class="form-select form-select-sm" style="border-radius: 8px; height: 36px; font-size: 13px; border-color: #D1D5D8;" onchange="this.form.submit()">
            '.$optDivHtml.'
          </select>
        </div>

        <div class="col-12 col-md-1 p-0 ps-md-1 d-flex gap-1">
          <button type="submit" class="h-[36px] px-3 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[13px] font-semibold transition-colors flex-fill inline-flex items-center justify-center gap-1 border-0 shadow-xs cursor-pointer" title="Terapkan Filter">
            <i class="bi bi-funnel"></i>
          </button>
          '.($search !== '' || $photoFilter !== 'all' || $divisiId > 0 ? '
          <a href="'.e(module_url('asset_gallery.php', array_filter(['sub' => 'unit', 'cabang' => $cabangId]))).'" class="h-[36px] w-[36px] bg-white border border-[#D1D5D8] rounded-[8px] text-[#53595F] hover:bg-[#F4F5F7] transition-colors inline-flex items-center justify-center text-decoration-none shadow-xs shrink-0" title="Reset Semua Filter">
            <i class="bi bi-arrow-counterclockwise"></i>
          </a>' : '').'
        </div>
      </form>
    </div>';

    $pctPhoto = $totalAll > 0 ? round(($totalWithPhoto / $totalAll) * 100, 1) : 0;
}

// 7. Bangun Pagination HTML
$paginationHtml = '';
if ($totalPages > 1) {
    $paginationHtml = '<nav aria-label="Page navigation" class="mt-4"><ul class="pagination pagination-sm justify-content-center gap-1.5 m-0">';
    $prevDisabled = ($page <= 1) ? ' disabled' : '';
    $paginationHtml .= '<li class="page-item'.$prevDisabled.'"><a class="page-link rounded-[6px] px-3 h-[32px] inline-flex items-center border-[#D1D5D8] text-[#1B2A3A]" href="'.e(gallery_query(['page' => max(1, $page - 1)])).'">&laquo; Sebelumnya</a></li>';
    for ($p = 1; $p <= $totalPages; $p++) {
        if ($p === 1 || $p === $totalPages || ($p >= $page - 2 && $p <= $page + 2)) {
            $act = ($p === $page) ? ' active' : '';
            $activeStyle = ($p === $page) ? 'style="background-color: #0E5C58 !important; border-color: #0E5C58 !important; color: white !important;"' : 'style="border-color: #D1D5D8; color: #1B2A3A;"';
            $paginationHtml .= '<li class="page-item'.$act.'"><a class="page-link rounded-[6px] text-center w-[32px] h-[32px] p-0 inline-flex items-center justify-center" '.$activeStyle.' href="'.e(gallery_query(['page' => $p])).'">'.$p.'</a></li>';
        } elseif ($p === $page - 3 || $p === $page + 3) {
            $paginationHtml .= '<li class="page-item disabled"><span class="page-link border-0 text-[#A0A5AA]">...</span></li>';
        }
    }
    $nextDisabled = ($page >= $totalPages) ? ' disabled' : '';
    $paginationHtml .= '<li class="page-item'.$nextDisabled.'"><a class="page-link rounded-[6px] px-3 h-[32px] inline-flex items-center border-[#D1D5D8] text-[#1B2A3A]" href="'.e(gallery_query(['page' => min($totalPages, $page + 1)])).'">Selanjutnya &raquo;</a></li>';
    $paginationHtml .= '</ul></nav>';
}

ob_start();
?>

<style>
.gallery-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}
.gallery-card:hover {
  transform: translateY(-2px);
  border-color: #0E5C58 !important;
  box-shadow: 0 8px 20px -4px rgba(14, 92, 88, 0.12), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
}
.gallery-img-thumb {
  transition: transform 0.3s ease;
}
.gallery-card:hover .gallery-img-thumb {
  transform: scale(1.04);
}
.gallery-hover-overlay {
  transition: opacity 0.2s ease;
}
.gallery-card:hover .gallery-hover-overlay {
  opacity: 1 !important;
}

/* Branch Filter Navigation Pills */
.branch-nav-bar {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding: 2px 2px 6px 2px;
  white-space: nowrap;
}
.branch-nav-pill {
  padding: 6px 14px;
  font-size: 13px;
  font-weight: 600;
  border-radius: 8px;
  background: #ffffff;
  border: 1px solid #D1D5D8;
  color: #1B2A3A;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.15s ease;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}
.branch-nav-pill:hover {
  background: #F4F5F7;
  border-color: #A0A5AA;
  color: #0E5C58;
}
.branch-nav-pill.active {
  background: #0E5C58 !important;
  border-color: #0E5C58 !important;
  color: #ffffff !important;
  box-shadow: 0 2px 8px rgba(14, 92, 88, 0.25) !important;
}
.branch-nav-pill.active .badge {
  background: #ffffff !important;
  color: #0E5C58 !important;
  font-weight: 700 !important;
}
.cursor-pointer { cursor: pointer; }
</style>

<?php
$extraHead = ob_get_clean();
ob_start();
?>
<!-- HEADER UTAMA & BREADCRUMB -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
  <div>
    <div class="text-[11px] font-bold text-[#0E5C58] uppercase tracking-wider mb-1 flex items-center gap-1.5">
      <i class="bi bi-images"></i> VISUAL INVENTORY ARCHIVE
    </div>
    <div class="flex items-center gap-2.5 flex-wrap">
      <h1 class="text-[24px] font-bold text-[#1B2A3A] m-0 leading-tight">Galeri Foto Inventaris &amp; Unit</h1>
      <span class="px-2.5 py-0.5 text-[11px] font-semibold bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC] rounded-full inline-flex items-center gap-1">
        <i class="bi bi-camera-fill"></i> Arsip Fisik
      </span>
    </div>
    <div class="text-[13px] text-[#53595F] mt-1">
      Dokumentasi visual foto fisik perangkat IT dan kartu inventaris operasional Bank Mitra.
    </div>
  </div>

  <div class="flex flex-wrap items-center gap-2 shrink-0">
    <a href="<?= e(module_url('dashboard.php', ['tab' => 'kartu'])) ?>" class="h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-credit-card-2-front text-[#0E5C58]"></i> <span>Tabel Kartu</span>
    </a>
    <a href="<?= e(module_url('assets.php')) ?>" class="h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-pc-display text-[#53595F]"></i> <span>Tabel Aset IT</span>
    </a>
    <a href="<?= e(module_url('dashboard.php')) ?>" class="h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-speedometer2 text-[#53595F]"></i> <span>Dashboard</span>
    </a>
  </div>
</div>

<?php if (!$isHrdOnly): ?>
<!-- SUB-TAB SWITCHER BAR (SEGMENTED CONTROL) -->
<div class="flex flex-wrap items-center gap-2 mb-4">
  <a href="<?= e(gallery_query(['sub' => 'inventaris', 'page' => 1, 'q' => null, 'cabang' => null, 'photo' => 'all'])) ?>" class="h-[38px] px-4 rounded-[8px] text-[13px] font-semibold inline-flex items-center gap-2 text-decoration-none transition-colors <?= ($subTab === 'inventaris' ? 'bg-[#0E5C58] text-white shadow-xs' : 'bg-white border border-[#D1D5D8] text-[#53595F] hover:bg-[#F4F5F7]') ?>">
    <i class="bi bi-collection-fill"></i>
    <span>Galeri Foto Inventaris (Bank Core)</span>
    <span class="px-2 py-0.5 text-[11px] font-bold rounded-full <?= ($subTab === 'inventaris' ? 'bg-white text-[#0E5C58]' : 'bg-[#F4F5F7] text-[#53595F] border border-[#D1D5D8]') ?>"><?= $totalInvCount ?></span>
  </a>
  <a href="<?= e(gallery_query(['sub' => 'unit', 'page' => 1, 'q' => null, 'cabang' => null, 'photo' => 'all'])) ?>" class="h-[38px] px-4 rounded-[8px] text-[13px] font-semibold inline-flex items-center gap-2 text-decoration-none transition-colors <?= ($subTab === 'unit' ? 'bg-[#0E5C58] text-white shadow-xs' : 'bg-white border border-[#D1D5D8] text-[#53595F] hover:bg-[#F4F5F7]') ?>">
    <i class="bi bi-pc-display-horizontal"></i>
    <span>Galeri Unit Perangkat (IT Ops)</span>
    <span class="px-2 py-0.5 text-[11px] font-bold rounded-full <?= ($subTab === 'unit' ? 'bg-white text-[#0E5C58]' : 'bg-[#F4F5F7] text-[#53595F] border border-[#D1D5D8]') ?>"><?= $totalUnitCount ?></span>
  </a>
</div>
<?php endif; ?>

<!-- 3 SYMMETRICAL KOMBAI METRIC CARDS (88px) -->
<div class="bg-[#FFFFFF] border border-[#D1D5D8] rounded-[10px] h-[88px] flex overflow-hidden shrink-0 mb-4">
  <!-- Card 1: Total Item Terdaftar -->
  <div class="flex-1 flex flex-col justify-center px-6 border-r border-[#D1D5D8] relative hover:bg-[#F4F5F7] transition-colors">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0E5C58]"></div>
    <div class="text-[28px] font-bold text-[#1B2A3A] tabular-nums leading-none">
      <?= number_format($totalAll) ?> <span class="text-[13px] font-normal text-[#53595F]">Item</span>
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-2">
      <i class="bi bi-boxes text-[#0E5C58]"></i>
      <span>Seluruh register <?= ($subTab === 'inventaris' ? 'Bank Core' : 'Perangkat IT') ?></span>
    </div>
  </div>

  <!-- Card 2: Sudah Ada Foto Fisik -->
  <div class="flex-1 flex flex-col justify-center px-6 border-r border-[#D1D5D8] relative hover:bg-[#F4F5F7] transition-colors">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#10B981]"></div>
    <div class="text-[28px] font-bold text-[#10B981] tabular-nums leading-none">
      <?= number_format($totalWithPhoto) ?> <span class="text-[13px] font-bold text-[#10B981]">(<?= $pctPhoto ?>%)</span>
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
      <i class="bi bi-check-circle-fill text-[#10B981]"></i>
      <span class="text-[#065F46] font-semibold">Terverifikasi dokumentasi foto fisik</span>
    </div>
  </div>

  <!-- Card 3: Belum Ada Foto Fisik -->
  <div class="flex-1 flex flex-col justify-center px-6 relative hover:bg-[#F4F5F7] transition-colors">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#D97706]"></div>
    <div class="text-[28px] font-bold text-[#B45309] tabular-nums leading-none">
      <?= number_format($totalNoPhoto) ?> <span class="text-[13px] font-normal text-[#53595F]">Item</span>
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
      <i class="bi bi-exclamation-triangle-fill text-[#D97706]"></i>
      <span class="text-[#92400E] font-semibold">Perlu pengambilan foto fisik lapangan</span>
    </div>
  </div>
</div>

<!-- Navigasi Cabang -->
<?= $branchPillsHtml ?>

<!-- Filter & Search Toolbar -->
<?= $filterToolbarHtml ?>

<!-- Header Info Hasil Pencarian -->
<div class="flex items-center justify-between mb-3 text-[#53595F] text-[12px]">
  <div>
    Menampilkan <strong class="text-[#1B2A3A]"><?= count($pageCards) ?></strong> dari <strong class="text-[#1B2A3A]"><?= $totalFiltered ?></strong> <?= ($subTab === 'inventaris' ? 'barang inventaris' : 'perangkat IT') ?>
    <?= ($search !== '' ? ' untuk pencarian "<em>'.e($search).'</em>"' : '') ?>
  </div>
  <div>Halaman <strong class="text-[#1B2A3A]"><?= $page ?></strong> dari <strong class="text-[#1B2A3A]"><?= $totalPages ?></strong></div>
</div>

<!-- Grid Kartu Foto -->
<div class="row">
  <?= $galleryCardsHtml ?>
</div>

<!-- Pagination -->
<?= $paginationHtml ?>

<!-- Modal Lightbox Zoom Foto Inventaris -->
<div class="modal fade" id="invGalleryLightboxModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-header bg-[#0E5C58] text-white border-0 py-3 px-4">
        <div class="flex items-center gap-2">
          <i class="bi bi-image text-white fs-5"></i>
          <div>
            <h6 class="modal-title font-bold mb-0 text-white" id="lightboxInvTitle">Foto Fisik Barang</h6>
            <div class="text-[12px] text-white text-opacity-80 font-mono" id="lightboxInvSubtitle"></div>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0 bg-black text-center position-relative flex items-center justify-center" style="min-height: 380px; max-height: 70vh;">
        <img id="lightboxInvImage" src="" class="img-fluid" style="max-height: 70vh; object-fit: contain;" alt="Foto Fisik Barang">
      </div>
      <div class="modal-footer bg-[#F8FAF9] border-t border-[#D1D5D8] py-2.5 px-4 flex justify-between">
        <div class="text-[12px] text-[#53595F]" id="lightboxInvMeta"></div>
        <div class="flex gap-2">
          <a id="lightboxInvOpenBtn" href="" target="_blank" class="h-[32px] px-3 bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] font-semibold text-[#53595F] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1 text-decoration-none shadow-xs">
            <i class="bi bi-box-arrow-up-right"></i> Buka Asli
          </a>
          <a id="lightboxInvDetailBtn" href="" class="h-[32px] px-3 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[6px] text-[12px] font-semibold transition-colors inline-flex items-center gap-1 text-decoration-none shadow-xs">
            <i class="bi bi-credit-card-2-front"></i> Buka Kartu
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Quick Info & Barcode Inventaris -->
<div class="modal fade" id="invQuickQrModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-header bg-[#0E5C58] text-white border-0 py-3 px-4">
        <h6 class="modal-title font-bold mb-0 text-white flex items-center gap-1.5"><i class="bi bi-qr-code"></i> Quick Barcode</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4">
        <div class="p-2 border border-[#D1D5D8] rounded-[8px] bg-white d-inline-block shadow-xs mb-3" style="min-width: 176px; min-height: 176px;">
          <div id="invModalQrContainer" style="display: flex; align-items: center; justify-content: center; width: 160px; height: 160px; margin: 0 auto;">
            <canvas id="invModalQrCanvas" width="160" height="160" style="display: block;"></canvas>
          </div>
        </div>
        <div class="font-mono font-bold text-[#0E5C58] text-[13px] mb-1" id="invModalRekening"></div>
        <div class="font-bold text-[#1B2A3A] text-[13px] mb-1" id="invModalNama"></div>
        <div class="inline-flex px-2 py-0.5 rounded bg-[#F4F5F7] text-[#53595F] border border-[#D1D5D8] text-[11px] mb-3" id="invModalRuangan"></div>
        <div class="d-grid gap-2">
          <a id="invModalDetailBtn" href="" class="h-[34px] bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[6px] text-[12px] font-semibold transition-colors flex items-center justify-center gap-1.5 text-decoration-none shadow-xs">
            <i class="bi bi-credit-card-2-front"></i> Detail Kartu Inventaris
          </a>
          <a id="invModalPrintBtn" href="" target="_blank" class="h-[34px] bg-white border border-[#D1D5D8] text-[#53595F] hover:bg-[#F4F5F7] rounded-[6px] text-[12px] font-semibold transition-colors flex items-center justify-center gap-1.5 text-decoration-none shadow-xs">
            <i class="bi bi-printer"></i> Cetak Kartu A4
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Quick QR Stiker Unit IT -->
<div class="modal fade" id="quickQrModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-header bg-[#0E5C58] text-white border-0 py-3 px-4">
        <h6 class="modal-title font-bold mb-0 text-white flex items-center gap-1.5"><i class="bi bi-qr-code"></i> QR Label Unit</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4">
        <div class="p-2 border border-[#D1D5D8] rounded-[8px] bg-white d-inline-block shadow-xs mb-3">
          <canvas id="modalQrCanvas" width="160" height="160" style="display: block; margin: 0 auto;"></canvas>
        </div>
        <div class="font-mono font-bold text-[#0E5C58] text-[13px] mb-1" id="modalQrKode"></div>
        <div class="font-bold text-[#1B2A3A] text-[13px] mb-1" id="modalQrDevice"></div>
        <div class="inline-flex px-2 py-0.5 rounded bg-[#F4F5F7] text-[#53595F] border border-[#D1D5D8] text-[11px] mb-3" id="modalQrUser"></div>
        <div class="d-grid gap-2">
          <a id="modalQrScanBtn" href="" class="h-[34px] bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[6px] text-[12px] font-semibold transition-colors flex items-center justify-center gap-1.5 text-decoration-none shadow-xs">
            <i class="bi bi-arrow-up-right"></i> Buka Detail Unit
          </a>
          <a id="modalQrEditBtn" href="" class="h-[34px] bg-white border border-[#D1D5D8] text-[#53595F] hover:bg-[#F4F5F7] rounded-[6px] text-[12px] font-semibold transition-colors flex items-center justify-center gap-1.5 text-decoration-none shadow-xs">
            <i class="bi bi-pencil"></i> Edit Data / Foto
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$body = ob_get_clean();

ob_start();
?>

<script src="<?= e(module_url('assets/vendor/qrcode.min.js')) ?>"></script>
<script>
function handleGalleryImgError(el) {
  if (!el || !el.parentElement) return;
  el.parentElement.innerHTML = '<div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-[#53595F] bg-[#F4F5F7]"><i class="bi bi-image text-[#9CA3AF] fs-1 mb-1"></i><small>Foto tidak dapat dimuat</small></div>';
}

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
    metaEl.innerHTML = "<i class=\"bi bi-patch-check-fill text-[#10B981] me-1\"></i> Foto diverifikasi: <strong>" + data.foto_by + "</strong>";
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
    container.innerHTML = '<canvas id="invModalQrCanvas" width="160" height="160" style="display: block;"></canvas>';
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
  container.innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' + encoded + '" width="160" height="160" style="display:block; border-radius: 4px;" alt="QR Code">';
}

// Lightbox Unit IT
function openUnitLightbox(data) {
  if (!data) return;
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

<?php
$extraScript = ob_get_clean();
render_page('Galeri Foto Inventaris & Unit', $body, $extraHead, $extraScript);
