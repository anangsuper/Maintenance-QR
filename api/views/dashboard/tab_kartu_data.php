<?php
// =========================================================================
// 5. GENERASI DATA TABEL KARTU INVENTARIS
// =========================================================================
$optCardJenis = '';
foreach (get_master_jenis_inventaris() as $k => $v) {
    $optCardJenis .= '<option value="'.e($v).'">'.e($v).'</option>';
}

$optCardGolongan = '';
foreach (get_master_golongan_inventaris() as $k => $v) {
    $optCardGolongan .= '<option value="'.e($v).'">'.e($v).'</option>';
}

$optCardRuangan = '';
$masterRuanganList = get_master_ruangan_list();
foreach ($masterRuanganList as $r) {
    $valR = trim($r['kode_ruangan'] . ' : ' . $r['nama_ruangan']);
    $optCardRuangan .= '<option value="'.e($valR).'">'.e($valR).'</option>';
}

$optCardSumber = '';
foreach (get_master_sumber_perolehan() as $k => $v) {
    $optCardSumber .= '<option value="'.e($v).'">'.e($v).'</option>';
}

$optCardStatusInv = '';
foreach (get_master_status_inventaris() as $k => $v) {
    $optCardStatusInv .= '<option value="'.e($v).'">'.e($v).'</option>';
}

// Pagination untuk Tab Kartu Inventaris
$totalFilteredCards = count($filteredCards);
$perPageCards = max(10, min(100, (int)($_GET['limit_kartu'] ?? 25)));
$pageKartu = max(1, (int)($_GET['p_kartu'] ?? 1));
$totalPagesCards = max(1, (int)ceil($totalFilteredCards / $perPageCards));
if ($pageKartu > $totalPagesCards) $pageKartu = $totalPagesCards;
$offsetCards = ($pageKartu - 1) * $perPageCards;
$displayCards = array_slice($filteredCards, $offsetCards, $perPageCards);

$cardsTableRowsHtml = '';
$cardsMobileCardsHtml = '';
if (empty($displayCards)) {
    $cardsTableRowsHtml = '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>Tidak ada data kartu inventaris yang ditemukan.</td></tr>';
    $cardsMobileCardsHtml = '<div class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>Tidak ada data kartu inventaris yang ditemukan.</div>';
} else {
    foreach ($displayCards as $c) {
        $cId = (int)$c['id'];
        $rek = $c['nomor_rekening'] ?? '';
        $nama = $c['nama_barang'] ?? '';
        $tglRaw = $c['tanggal_perolehan'] ?? '';
        $tglCard = format_card_date($tglRaw);
        $gabungan = get_nomor_asset_gabungan($rek, $tglRaw);
        $barcode = $c['barcode_data'] ?? '';
        $lokasi = !empty($c['ruangan']) ? $c['ruangan'] : (!empty($c['lokasi']) ? $c['lokasi'] : 'KPO');
        $jsonPayload = json_encode(array_merge($c, ['gabungan' => $gabungan]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        // Deteksi apakah item baru diperoleh hari ini
        $isCardToday = false;
        $tglClean = trim((string)$tglRaw);
        $createdClean = trim((string)($c['created_at'] ?? ''));
        if ($tglClean !== '' && $tglClean !== '0000-00-00') {
            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $tglClean, $m)) {
                $tglNorm = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            } else {
                $tglNorm = substr($tglClean, 0, 10);
            }
            if ($tglNorm === $todayDateStr) {
                $isCardToday = true;
            }
        }
        if (!$isCardToday && $createdClean !== '') {
            if (str_starts_with($createdClean, $todayDateStr) || str_starts_with($createdClean, $todayDateDmy) || str_starts_with($createdClean, $todayDateDmY)) {
                $isCardToday = true;
            }
        }
        $todayBadge = $isCardToday ? '<span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 0.65rem; font-weight: 700;"><i class="bi bi-stars me-0.5"></i>Hari Ini</span>' : '';

        $photoThumbHtml = '';
        if (!empty($c['foto'])) {
            $cFotoSrc = function_exists('asset_image_url') ? asset_image_url($c['foto']) : module_url($c['foto']);
            $photoThumbHtml = '<a href="'.e(module_url('kartu_detail.php', ['id'=>$cId])).'" target="_blank" class="flex-shrink-0 position-relative text-decoration-none" title="Lihat Foto Fisik Inventaris">
                <img src="'.e($cFotoSrc).'" alt="Foto" class="rounded border shadow-xs" style="width: 36px; height: 36px; object-fit: cover;">
                <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-success p-0 border border-white" style="width: 8px; height: 8px;" title="Ada Foto Fisik"></span>
            </a>';
        } else {
            $photoThumbHtml = '<a href="'.e(module_url('kartu_detail.php', ['id'=>$cId])).'" target="_blank" class="flex-shrink-0 d-inline-flex align-items-center justify-content-center rounded border bg-light text-muted text-decoration-none" style="width: 36px; height: 36px;" title="Belum Ada Foto - Klik untuk Ambil/Upload Foto">
                <i class="bi bi-camera text-secondary opacity-50" style="font-size: 1.05rem;"></i>
            </a>';
        }

        // Tentukan style badge lokasi
        $lokasiNorm = strtoupper(trim((string)$lokasi));
        $lokasiBadgeClass = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
        if (str_contains($lokasiNorm, '01') || str_contains($lokasiNorm, 'PUSAT') || str_contains($lokasiNorm, 'KPO')) {
            $lokasiBadgeClass = 'bg-primary-subtle text-primary border border-primary-subtle';
        } elseif (str_contains($lokasiNorm, '02') || str_contains($lokasiNorm, 'BATULICIN')) {
            $lokasiBadgeClass = 'bg-success-subtle text-success border border-success-subtle';
        } elseif (str_contains($lokasiNorm, '03') || str_contains($lokasiNorm, 'MARTAPURA')) {
            $lokasiBadgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
        } elseif (str_contains($lokasiNorm, '04') || str_contains($lokasiNorm, 'TANJUNG')) {
            $lokasiBadgeClass = 'bg-info-subtle text-info-emphasis border border-info-subtle';
        } elseif (str_contains($lokasiNorm, '05') || str_contains($lokasiNorm, 'HANDIL')) {
            $lokasiBadgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
        }

        $noSeriBadge = !empty($c['nomor_seri_inventaris']) ? '<span class="badge bg-light text-dark border font-monospace mt-0.5" style="font-size: 0.68rem;"><i class="bi bi-hash me-0.5"></i>'.e($c['nomor_seri_inventaris']).'</span>' : '';

        $isPrintedCard = !empty($c['printed_at']);
        $printedDateTooltip = $isPrintedCard ? date('d/m/Y H:i', strtotime((string)$c['printed_at'])) : '';
        $printStatusBadgeHtml = $isPrintedCard
            ? '<button type="button" id="print-badge-'.$cId.'" data-printed="1" onclick="toggleCardPrintStatus('.$cId.', this)" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#ECFDF5] text-[#059669] border border-[#A7F3D0] hover:bg-[#D1FAE5] transition-colors inline-flex items-center gap-1 cursor-pointer" title="Sudah dicetak pada '.e($printedDateTooltip).' — Klik untuk ubah ke Belum Dicetak"><i class="bi bi-check-circle-fill"></i><span>Sudah Dicetak</span></button>'
            : '<button type="button" id="print-badge-'.$cId.'" data-printed="0" onclick="toggleCardPrintStatus('.$cId.', this)" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#FFFBEB] text-[#D97706] border border-[#FDE68A] hover:bg-[#FEF3C7] transition-colors inline-flex items-center gap-1 cursor-pointer" title="Belum pernah dicetak — Klik untuk tandai Sudah Dicetak"><i class="bi bi-hourglass-split"></i><span>Belum Dicetak</span></button>';

        $printStatusBadgeMobHtml = $isPrintedCard
            ? '<button type="button" id="print-badge-mob-'.$cId.'" data-printed="1" onclick="toggleCardPrintStatus('.$cId.', this)" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#ECFDF5] text-[#059669] border border-[#A7F3D0] inline-flex items-center gap-1 cursor-pointer" title="Sudah dicetak pada '.e($printedDateTooltip).'"><i class="bi bi-check-circle-fill"></i><span>Sudah Dicetak</span></button>'
            : '<button type="button" id="print-badge-mob-'.$cId.'" data-printed="0" onclick="toggleCardPrintStatus('.$cId.', this)" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#FFFBEB] text-[#D97706] border border-[#FDE68A] inline-flex items-center gap-1 cursor-pointer" title="Belum pernah dicetak"><i class="bi bi-hourglass-split"></i><span>Belum Dicetak</span></button>';

        $cardsTableRowsHtml .= '
        <tr id="card-row-'.$cId.'" class="hover:bg-[#F9FAFB] transition-colors">
          <td class="text-center py-2.5" style="width: 38px;">
            <input class="form-check-input card-checkbox cursor-pointer" type="checkbox" value="'.$cId.'" onchange="updateCardCounters(this)">
          </td>
          <td style="width: 155px;" class="py-2.5">
            <div class="font-mono font-bold text-[#0E5C58] text-[13px]">'.e($rek).'</div>
            <div class="text-[11px] text-[#53595F] font-mono mt-0.5" title="Nomor Aset Gabungan">'.e($gabungan).'</div>
            '.$noSeriBadge.'
          </td>
          <td class="py-2.5">
            <div class="flex items-center gap-2.5">
              '.$photoThumbHtml.'
              <div class="min-w-0">
                <a href="'.e(module_url('kartu_detail.php', ['id'=>$cId])).'" target="_blank" class="font-semibold text-[#1B2A3A] hover:text-[#0E5C58] transition-colors text-decoration-none text-break text-[13px]" title="Buka Detail &amp; Foto Fisik">'.e($nama).'</a>
                <div class="text-[11px] text-[#53595F] font-mono mt-0.5 flex items-center gap-1.5 flex-wrap">
                  <span><i class="bi bi-calendar-event text-[#0E5C58] me-1"></i>'.e($tglCard).'</span>
                  '.$todayBadge.'
                  '.$printStatusBadgeHtml.'
                </div>
              </div>
            </div>
          </td>
          <td style="width: 75px;" class="text-center py-2.5">
            '.($barcode ? '<a href="'.e($barcode).'" target="_blank" class="px-2 py-0.5 rounded-[4px] text-[11px] font-bold bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC] text-decoration-none inline-flex items-center gap-1 shadow-2xs" title="'.e($barcode).'"><i class="bi bi-qr-code"></i>QR</a>' : '<span class="text-[#A0A5AA] text-[12px]">-</span>').'
          </td>
          <td style="width: 95px;" class="text-center py-2.5">
            <span class="badge px-2 py-1 rounded-pill '.$lokasiBadgeClass.'" style="font-size: 0.72rem; font-weight: 600;">'.e($lokasi).'</span>
          </td>
          <td class="text-end py-2.5 pe-3" style="width: 235px;">
            <div class="inline-flex items-center gap-1 justify-end">
              <button type="button" class="h-[28px] px-2 bg-[#D9F1EF] border border-[#A7E2DC] hover:bg-[#0E5C58] hover:text-white text-[#0E5C58] rounded-[5px] text-[11px] font-semibold transition-colors inline-flex items-center gap-1 shadow-2xs cursor-pointer" title="Unduh Gambar Kartu PNG Ukuran Asli CR80 (85.6mm x 54mm)" onclick=\'downloadCardPngFromRow('.$jsonPayload.', this)\'>
                <i class="bi bi-download"></i><span class="d-none d-xl-inline"> PNG</span>
              </button>
              <a class="h-[28px] px-2 bg-white border border-[#D1D5D8] hover:bg-[#ECFDF5] text-[#10B981] rounded-[5px] text-[11px] font-medium transition-colors inline-flex items-center gap-1 text-decoration-none shadow-2xs" target="_blank" href="'.e(module_url('inventaris_mutation.php', ['id'=>$cId])).'" title="Mutasi Fasilitas &amp; Kartu Inventaris">
                <i class="bi bi-arrow-left-right"></i><span class="d-none d-xxl-inline"> Mutasi</span>
              </a>
              <a class="h-[28px] px-2 bg-white border border-[#D1D5D8] hover:bg-[#D9F1EF] text-[#0E5C58] rounded-[5px] text-[11px] font-medium transition-colors inline-flex items-center gap-1 text-decoration-none shadow-2xs" target="_blank" href="'.e(module_url('kartu_detail.php', ['id'=>$cId])).'" title="Buka Detail Web &amp; Foto">
                <i class="bi bi-box-arrow-up-right"></i><span class="d-none d-xxl-inline"> Detail</span>
              </a>
              <button type="button" class="h-[28px] px-2 bg-white border border-[#D1D5D8] hover:bg-[#E0F2FE] text-[#0284C7] rounded-[5px] text-[11px] font-medium transition-colors inline-flex items-center gap-1 shadow-2xs cursor-pointer" title="Pratinjau Fisik Kartu &amp; Kirim WA" onclick=\'openCardPreviewModal('.$jsonPayload.')\'>
                <i class="bi bi-eye"></i><span class="d-none d-xl-inline"> Lihat</span>
              </button>
              <a class="h-[28px] px-2 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] rounded-[5px] text-[11px] font-medium transition-colors inline-flex items-center gap-1 text-decoration-none shadow-2xs" target="_blank" href="'.e(module_url('print_inventory_card.php', ['source'=>'inventaris_kartu', 'id'=>$cId])).'" title="Cetak Kartu Ini">
                <i class="bi bi-printer"></i><span class="d-none d-xl-inline"> Cetak</span>
              </a>
              <button type="button" class="h-[28px] px-2 bg-white border border-[#D1D5D8] hover:bg-[#FEF3C7] text-[#D97706] rounded-[5px] text-[11px] font-medium transition-colors inline-flex items-center gap-1 shadow-2xs cursor-pointer" title="Edit Kartu" onclick=\'openCardEditModal('.$jsonPayload.')\'>
                <i class="bi bi-pencil"></i>
              </button>
              <button type="button" class="h-[28px] px-2 bg-white border border-[#D1D5D8] hover:bg-[#FEE2E2] text-[#DC2626] rounded-[5px] text-[11px] font-medium transition-colors inline-flex items-center gap-1 shadow-2xs cursor-pointer" title="Hapus Kartu" onclick="confirmDeleteCard('.$cId.', \''.e(addslashes($rek . ' - ' . $nama)).'\')">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </td>
        </tr>';

        // Mobile Card Row (Tanpa Scroll Horizontal pada Smartphone)
        $cardsMobileCardsHtml .= '
        <div class="p-3 border-b border-[#E5E7EB] bg-white hover:bg-[#F9FAFB] transition-colors" id="card-mobile-'.$cId.'">
          <div class="flex items-start justify-between gap-2 mb-2">
            <div class="flex items-center gap-2">
              <input class="form-check-input card-checkbox cursor-pointer" type="checkbox" value="'.$cId.'" onchange="updateCardCounters(this)">
              <div>
                <div class="font-mono font-bold text-[#0E5C58] text-[13px]">'.e($rek).'</div>
                <div class="text-[11px] text-[#53595F] font-mono" title="Nomor Aset Gabungan">'.e($gabungan).'</div>
              </div>
            </div>
            <div class="flex items-center gap-1.5 flex-wrap justify-end">
              '.$printStatusBadgeMobHtml.'
              <span class="badge px-2 py-1 rounded-pill '.$lokasiBadgeClass.'" style="font-size: 0.70rem; font-weight: 600;">'.e($lokasi).'</span>
            </div>
          </div>

          <div class="flex items-center gap-2.5 mb-2.5">
            '.$photoThumbHtml.'
            <div class="min-w-0 flex-1">
              <a href="'.e(module_url('kartu_detail.php', ['id'=>$cId])).'" target="_blank" class="font-semibold text-[#1B2A3A] hover:text-[#0E5C58] transition-colors text-decoration-none text-[13px] line-clamp-2" title="Buka Detail &amp; Foto Fisik">'.e($nama).'</a>
              <div class="text-[11px] text-[#53595F] font-mono mt-0.5 flex items-center gap-1">
                <i class="bi bi-calendar-event text-[#0E5C58]"></i>'.e($tglCard).'
                '.$todayBadge.'
              </div>
              '.$noSeriBadge.'
            </div>
          </div>

          <div class="grid grid-cols-7 gap-1 pt-2 border-t border-[#F3F4F6]">
            <button type="button" class="h-[30px] bg-[#D9F1EF] border border-[#A7E2DC] text-[#0E5C58] rounded-[6px] text-[11px] font-semibold flex items-center justify-center shadow-2xs cursor-pointer" title="Unduh Gambar Kartu PNG Ukuran Asli" onclick=\'downloadCardPngFromRow('.$jsonPayload.', this)\'>
              <i class="bi bi-download"></i>
            </button>
            <a class="h-[30px] bg-white border border-[#D1D5D8] hover:bg-[#ECFDF5] text-[#10B981] rounded-[6px] text-[11px] font-medium transition-colors flex items-center justify-center text-decoration-none shadow-2xs" target="_blank" href="'.e(module_url('inventaris_mutation.php', ['id'=>$cId])).'" title="Mutasi">
              <i class="bi bi-arrow-left-right"></i>
            </a>
            <a class="h-[30px] bg-white border border-[#D1D5D8] hover:bg-[#D9F1EF] text-[#0E5C58] rounded-[6px] text-[11px] font-medium transition-colors flex items-center justify-center text-decoration-none shadow-2xs" target="_blank" href="'.e(module_url('kartu_detail.php', ['id'=>$cId])).'" title="Detail">
              <i class="bi bi-box-arrow-up-right"></i>
            </a>
            <button type="button" class="h-[30px] bg-white border border-[#D1D5D8] hover:bg-[#E0F2FE] text-[#0284C7] rounded-[6px] text-[11px] font-medium transition-colors flex items-center justify-center shadow-2xs cursor-pointer" title="Lihat" onclick=\'openCardPreviewModal('.$jsonPayload.')\'>
              <i class="bi bi-eye"></i>
            </button>
            <a class="h-[30px] bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] rounded-[6px] text-[11px] font-medium transition-colors flex items-center justify-center text-decoration-none shadow-2xs" target="_blank" href="'.e(module_url('print_inventory_card.php', ['source'=>'inventaris_kartu', 'id'=>$cId])).'" title="Cetak">
              <i class="bi bi-printer"></i>
            </a>
            <button type="button" class="h-[30px] bg-white border border-[#D1D5D8] hover:bg-[#FEF3C7] text-[#D97706] rounded-[6px] text-[11px] font-medium transition-colors flex items-center justify-center shadow-2xs cursor-pointer" title="Edit" onclick=\'openCardEditModal('.$jsonPayload.')\'>
              <i class="bi bi-pencil"></i>
            </button>
            <button type="button" class="h-[30px] bg-white border border-[#D1D5D8] hover:bg-[#FEE2E2] text-[#DC2626] rounded-[6px] text-[11px] font-medium transition-colors flex items-center justify-center shadow-2xs cursor-pointer" title="Hapus" onclick="confirmDeleteCard('.$cId.', \''.e(addslashes($rek . ' - ' . $nama)).'\')">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </div>';
    }
}

// Widget Ringkasan Kartu di Tab Monitoring
$quickCardsRowsHtml = '';
foreach (array_slice($allCards, 0, 5) as $qc) {
    $qId = (int)$qc['id'];
    $qRek = $qc['nomor_rekening'] ?? '';
    $qNama = $qc['nama_barang'] ?? '';
    $qTglRaw = $qc['tanggal_perolehan'] ?? '';
    $qTgl = format_card_date($qTglRaw);
    $qGab = get_nomor_asset_gabungan($qRek, $qTglRaw);

    $isQToday = false;
    $qTglClean = trim((string)$qTglRaw);
    $qCreatedClean = trim((string)($qc['created_at'] ?? ''));
    if ($qTglClean !== '' && $qTglClean !== '0000-00-00') {
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $qTglClean, $qm)) {
            $qTglNorm = sprintf('%04d-%02d-%02d', $qm[3], $qm[2], $qm[1]);
        } else {
            $qTglNorm = substr($qTglClean, 0, 10);
        }
        if ($qTglNorm === $todayDateStr) {
            $isQToday = true;
        }
    }
    if (!$isQToday && $qCreatedClean !== '') {
        if (str_starts_with($qCreatedClean, $todayDateStr) || str_starts_with($qCreatedClean, $todayDateDmy) || str_starts_with($qCreatedClean, $todayDateDmY)) {
            $isQToday = true;
        }
    }
    $qTodayBadge = $isQToday ? '<span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 0.65rem; font-weight: 700;">Hari Ini</span>' : '';

    $quickCardsRowsHtml .= '
    <tr>
      <td><span class="font-monospace fw-bold text-primary small">'.e($qRek).'</span></td>
      <td><span class="fw-semibold text-dark small">'.e($qNama).'</span></td>
      <td><span class="small text-secondary font-monospace">'.e($qTgl).'</span> '.$qTodayBadge.'</td>
      <td><span class="badge-chip chip-primary font-monospace">'.e($qGab).'</span></td>
      <td><span class="badge-chip chip-secondary">'.e($qc['lokasi'] ?? 'KPO').'</span></td>
      <td class="text-end">
        <a class="btn btn-sm btn-light border py-1 px-2.5 text-nowrap fw-medium" target="_blank" href="'.e(module_url('print_inventory_card.php', ['source'=>'inventaris_kartu', 'id'=>$qId])).'" title="Cetak Kartu" style="font-size: 0.78rem;"><i class="bi bi-printer me-1"></i>Cetak</a>
      </td>
    </tr>';
}
