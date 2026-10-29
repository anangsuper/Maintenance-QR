<?php
    // --- TAMPILAN TAB KARTU INVENTARIS (KOMBAI TEAL DESIGN SYSTEM) ---
    $body .= '
    <!-- Minimalist Action Toolbar Tab Kartu -->
    <div class="bg-white border border-[#D1D5D8] rounded-[10px] p-3.5 mb-4 shadow-xs">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="px-2.5 py-1 text-[11px] font-bold bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC] rounded-[6px] font-mono tracking-wider">
            KARTU INVENTARIS
          </span>
          <span class="text-[13px] text-[#53595F]">
            Kelola data kartu fisik CR80, penerbitan QR code, cetak massal A4, dan ekspor dokumen.
          </span>
        </div>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
          <button type="button" class="h-[34px] px-3.5 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[6px] text-[12px] font-semibold transition-colors inline-flex items-center gap-1.5 border-0 shadow-xs cursor-pointer" data-bs-toggle="modal" data-bs-target="#addCardModal">
            <i class="bi bi-plus-lg"></i> Tambah Data Kartu
          </button>
          <a href="'.e(module_url('print_inventory_card.php', ['source'=>'inventaris_kartu', 'status_cetak'=>'belum'])).'" target="_blank" class="h-[34px] px-3 bg-[#FFFBEB] border border-[#FDE68A] rounded-[6px] text-[12px] font-bold text-[#B45309] hover:bg-[#FEF3C7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs" title="Cetak massal A4 khusus kartu yang belum pernah dicetak">
            <i class="bi bi-printer-fill"></i> Cetak Belum Dicetak ('.$unprintedCardsCount.')
          </a>
          <a href="'.e(module_url('print_inventory_card.php', ['source'=>'inventaris_kartu'])).'" target="_blank" class="h-[34px] px-3 bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
            <i class="bi bi-printer text-[#0E5C58]"></i> Cetak Semua A4
          </a>
          <div class="dropdown">
            <button class="h-[34px] px-3 bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 shadow-xs dropdown-toggle cursor-pointer" type="button" data-bs-toggle="dropdown">
              <i class="bi bi-download text-[#53595F]"></i> Ekspor
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border border-[#D1D5D8] rounded-[8px]" style="font-size: 0.85rem;">
              <li>
                <a class="dropdown-item fw-semibold text-[#10B981]" href="'.e(module_url('print_inventory_card.php', ['export'=>'excel'])).'">
                  <i class="bi bi-file-earmark-excel-fill text-[#10B981] me-2"></i> Laporan Excel SDM (.xls)
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="'.e(module_url('print_inventory_card.php', ['export'=>'doc'])).'">
                  <i class="bi bi-file-earmark-word-fill text-[#0284C7] me-2"></i> Dokumen Microsoft Word (.doc)
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="'.e(module_url('print_inventory_card.php', ['export'=>'csv'])).'">
                  <i class="bi bi-file-earmark-spreadsheet text-[#53595F] me-2"></i> Spreadsheet CSV (.csv)
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- 4 Kotak Metrik Ringkasan Tab Kartu -->
    <div class="bg-[#FFFFFF] border border-[#D1D5D8] rounded-[10px] flex flex-col sm:flex-row overflow-hidden shrink-0 mb-4 shadow-xs">
      <!-- Card 1: Total Kartu -->
      <a href="'.e(module_url('dashboard.php', ['tab'=>'kartu'])).'" class="flex-1 flex flex-col justify-center px-5 py-3 border-b sm:border-b-0 sm:border-r border-[#D1D5D8] relative '.($statusCetakCard === '' && !$filterHariIni ? 'bg-[#D9F1EF]' : 'hover:bg-[#F4F5F7]').' transition-colors text-decoration-none">
        <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0E5C58]"></div>
        <div class="text-[26px] font-bold text-[#1B2A3A] tabular-nums leading-none">
          '.number_format($totalCardsCount).' <span class="text-[13px] font-normal text-[#53595F]">Unit</span>
        </div>
        <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
          <span class="text-[#0E5C58] font-bold">Total Kartu Inventaris</span>
          <span>&middot;</span>
          <span class="text-[#53595F] text-[11px]">100% Ber-QR Resmi</span>
        </div>
      </a>

      <!-- Card 2: Belum Dicetak -->
      <a href="'.e(module_url('dashboard.php', ['tab'=>'kartu', 'status_cetak'=>($statusCetakCard === 'belum' ? null : 'belum')])).'" class="flex-1 flex flex-col justify-center px-5 py-3 border-b sm:border-b-0 sm:border-r border-[#D1D5D8] relative '.($statusCetakCard === 'belum' ? 'bg-[#FFFBEB]' : 'hover:bg-[#F4F5F7]').' transition-colors text-decoration-none">
        <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#D97706]"></div>
        <div class="text-[26px] font-bold text-[#D97706] tabular-nums leading-none flex items-center justify-between">
          <span>'.number_format($unprintedCardsCount).' <span class="text-[13px] font-normal text-[#53595F]">Unit</span></span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#FFFBEB] text-[#D97706] border border-[#FDE68A]"><i class="bi bi-hourglass-split me-0.5"></i>Siap Cetak</span>
        </div>
        <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
          <span class="text-[#D97706] font-semibold">Belum Dicetak</span>
          <span>&middot;</span>
          <span class="text-[#53595F] text-[11px]">Klik untuk filter</span>
        </div>
      </a>

      <!-- Card 3: Sudah Dicetak -->
      <a href="'.e(module_url('dashboard.php', ['tab'=>'kartu', 'status_cetak'=>($statusCetakCard === 'sudah' ? null : 'sudah')])).'" class="flex-1 flex flex-col justify-center px-5 py-3 border-b sm:border-b-0 sm:border-r border-[#D1D5D8] relative '.($statusCetakCard === 'sudah' ? 'bg-[#ECFDF5]' : 'hover:bg-[#F4F5F7]').' transition-colors text-decoration-none">
        <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#10B981]"></div>
        <div class="text-[26px] font-bold text-[#10B981] tabular-nums leading-none flex items-center justify-between">
          <span>'.number_format($printedCardsCount).' <span class="text-[13px] font-normal text-[#53595F]">Unit</span></span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#ECFDF5] text-[#059669] border border-[#A7F3D0]"><i class="bi bi-check-circle-fill me-0.5"></i>Tercetak</span>
        </div>
        <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
          <span class="text-[#10B981] font-semibold">Sudah Dicetak</span>
          <span>&middot;</span>
          <span class="text-[#53595F] text-[11px]">'.($totalCardsCount > 0 ? round(($printedCardsCount / $totalCardsCount) * 100) : 0).'% Selesai</span>
        </div>
      </a>

      <!-- Card 4: Baru Hari Ini -->
      <a href="'.e(module_url('dashboard.php', ['tab'=>'kartu', 'hari_ini'=>($filterHariIni ? null : 1)])).'" class="flex-1 flex flex-col justify-center px-5 py-3 relative '.($filterHariIni ? 'bg-[#EFF6FF]' : 'hover:bg-[#F4F5F7]').' transition-colors text-decoration-none">
        <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0284C7]"></div>
        <div class="text-[26px] font-bold text-[#0284C7] tabular-nums leading-none flex items-center justify-between">
          <span>'.$todayCardsCount.' <span class="text-[13px] font-normal text-[#53595F]">Unit</span></span>
          '.($todayCardsCount > 0 ? '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#0284C7] text-white"><i class="bi bi-stars me-0.5"></i>Baru</span>' : '').'
        </div>
        <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
          <span class="text-[#0284C7] font-semibold"><i class="bi bi-stars me-0.5"></i> Baru Hari Ini</span>
          <span>&middot;</span>
          <span class="text-[#53595F] text-[11px]">'.date('d/m/Y').'</span>
        </div>
      </a>
    </div>

    <!-- Ringkasan Jumlah Kartu Inventaris Per Cabang (01 - 05) -->
    <div id="branchDistributionGrid" class="bg-white border border-[#D1D5D8] rounded-[10px] p-4 mb-4 shadow-xs">
      <div class="flex justify-between items-center mb-3 flex-wrap gap-2">
        <div>
          <div class="text-[11px] font-bold text-[#0E5C58] uppercase tracking-wider mb-0.5">DISTRIBUSI UNIT CABANG</div>
          <h3 class="text-[15px] font-bold text-[#1B2A3A] m-0 flex items-center gap-1.5">
            <i class="bi bi-buildings text-[#0E5C58]"></i> Jumlah Kartu Inventaris Per Cabang
          </h3>
        </div>
        <div class="text-[12px] text-[#53595F]">
          Total <strong>5 Kantor Cabang</strong> &middot; Klik cabang untuk filter cepat
        </div>
      </div>
      <div class="row g-2">
        '.$branchCardsGridHtml.'
      </div>
    </div>

    <!-- Compact Filter Bar (Sistem Filter Aset Komputer) -->
    <div class="bg-white border border-[#D1D5D8] rounded-[10px] p-4 mb-4 shadow-xs">
      <form method="get" class="row g-2 align-items-end">
        <input type="hidden" name="tab" value="kartu">
        <div class="col-lg-4 col-md-6">
          <label class="block text-[12px] font-bold text-[#1B2A3A] mb-1.5">Cari Nomor Rekening / Perangkat / PIC</label>
          <div class="relative">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-[#53595F] text-[13px]"></i>
            <input type="text" class="h-[36px] w-full bg-white border border-[#D1D5D8] rounded-[8px] pl-9 pr-3 text-[13px] text-[#1B2A3A] placeholder-[#9CA3AF] outline-none focus:border-[#0E5C58] transition-colors" name="q_kartu" value="'.e($searchCard).'" placeholder="Cari 05500032, nama barang, lokasi...">
          </div>
        </div>
        <div class="col-lg-3 col-md-3 col-sm-6">
          <label class="block text-[12px] font-bold text-[#1B2A3A] mb-1.5">Kantor Cabang</label>
          <select class="h-[36px] w-full bg-white border border-[#D1D5D8] rounded-[8px] px-3 text-[13px] text-[#1B2A3A] outline-none focus:border-[#0E5C58] transition-colors" name="cabang_kartu">
            <option value="">Semua Cabang (01 - 05)</option>
            <option value="01"'.($cabangCard === '01' ? ' selected' : '').'>01 - Kantor Pusat ('.$cabangStats['01']['count'].')</option>
            <option value="02"'.($cabangCard === '02' ? ' selected' : '').'>02 - Batulicin ('.$cabangStats['02']['count'].')</option>
            <option value="03"'.($cabangCard === '03' ? ' selected' : '').'>03 - Martapura ('.$cabangStats['03']['count'].')</option>
            <option value="04"'.($cabangCard === '04' ? ' selected' : '').'>04 - Tanjung ('.$cabangStats['04']['count'].')</option>
            <option value="05"'.($cabangCard === '05' ? ' selected' : '').'>05 - Handil Bakti ('.$cabangStats['05']['count'].')</option>
          </select>
        </div>
        <div class="col-lg-2 col-md-3 col-sm-6">
          <label class="block text-[12px] font-bold text-[#1B2A3A] mb-1.5">Tahun Perolehan</label>
          <select class="h-[36px] w-full bg-white border border-[#D1D5D8] rounded-[8px] px-3 text-[13px] text-[#1B2A3A] outline-none focus:border-[#0E5C58] transition-colors" name="tahun_kartu">
            <option value="">Semua Tahun</option>
            '.$optTahunKartuHtml.'
          </select>
        </div>
        <div class="col-lg-3 col-md-12 flex gap-1 justify-content-lg-end">
          <a class="h-[36px] px-3 w-full rounded-[8px] text-[13px] font-semibold transition-colors flex items-center justify-center gap-1.5 text-decoration-none '.($filterHariIni ? 'bg-[#10B981] hover:bg-[#059669] text-white shadow-xs' : 'bg-white border border-[#D1D5D8] text-[#10B981] hover:bg-[#ECFDF5]').'" href="'.e(module_url('dashboard.php', ['tab' => 'kartu', 'hari_ini' => ($filterHariIni ? null : 1)])).'" title="Filter aset yang baru diperoleh / didaftarkan hari ini">
            <i class="bi bi-stars"></i> Baru Hari Ini ('.$todayCardsCount.')
          </a>
        </div>
        <div class="col-12 flex justify-between items-center pt-3 border-t border-[#F3F4F6] mt-2 flex-wrap gap-2">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-[12px] font-bold text-[#1B2A3A] me-1"><i class="bi bi-printer me-1 text-[#0E5C58]"></i>Status Cetak:</span>
            <a href="'.e(module_url('dashboard.php', array_merge($_GET, ['tab'=>'kartu', 'status_cetak'=>null, 'p_kartu'=>1]))).'" class="px-2.5 py-1 rounded-full text-[11px] font-bold text-decoration-none transition-colors '.($statusCetakCard === '' ? 'bg-[#0E5C58] text-white' : 'bg-[#F4F5F7] text-[#53595F] hover:bg-[#E5E7EB]').'">
              Semua ('.$totalCardsCount.')
            </a>
            <a href="'.e(module_url('dashboard.php', array_merge($_GET, ['tab'=>'kartu', 'status_cetak'=>($statusCetakCard === 'belum' ? null : 'belum'), 'p_kartu'=>1]))).'" class="px-2.5 py-1 rounded-full text-[11px] font-bold text-decoration-none transition-colors inline-flex items-center gap-1 '.($statusCetakCard === 'belum' ? 'bg-[#D97706] text-white shadow-2xs' : 'bg-[#FFFBEB] text-[#D97706] border border-[#FDE68A] hover:bg-[#FEF3C7]').'">
              <i class="bi bi-hourglass-split"></i> Belum Dicetak ('.$unprintedCardsCount.')
            </a>
            <a href="'.e(module_url('dashboard.php', array_merge($_GET, ['tab'=>'kartu', 'status_cetak'=>($statusCetakCard === 'sudah' ? null : 'sudah'), 'p_kartu'=>1]))).'" class="px-2.5 py-1 rounded-full text-[11px] font-bold text-decoration-none transition-colors inline-flex items-center gap-1 '.($statusCetakCard === 'sudah' ? 'bg-[#059669] text-white shadow-2xs' : 'bg-[#ECFDF5] text-[#059669] border border-[#A7F3D0] hover:bg-[#D1FAE5]').'">
              <i class="bi bi-check-circle-fill"></i> Sudah Dicetak ('.$printedCardsCount.')
            </a>
            <span class="text-[12px] text-[#53595F] ms-1">
              &middot; Menampilkan <strong>'.count($filteredCards).'</strong> kartu
            </span>
          </div>
          <div class="flex gap-2">
            <a class="h-[34px] px-3 bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1 text-decoration-none" href="'.e(module_url('dashboard.php', ['tab' => 'kartu'])).'"><i class="bi bi-x-circle"></i> Reset</a>
            <button type="submit" class="h-[34px] px-3.5 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[6px] text-[12px] font-semibold transition-colors inline-flex items-center gap-1 border-0 shadow-xs cursor-pointer"><i class="bi bi-filter"></i> Terapkan</button>
          </div>
        </div>
      </form>
    </div>

    <!-- Table Container Card -->
    <div class="bg-white border border-[#D1D5D8] rounded-[10px] mb-4 shadow-xs overflow-hidden">
      <!-- Selection Card Bar -->
      <div id="selectionCardBar" class="border-b border-[#BFDBFE] px-4 py-2.5 d-none align-items-center justify-content-between flex-wrap gap-2" style="background-color: #EFF6FF !important;">
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-1 text-[12px] font-bold bg-[#0284C7] text-white rounded-[6px]"><i class="bi bi-check-square me-1"></i> <span id="countSelectedText">0</span> Dipilih</span>
          <span class="text-[13px] text-[#1E40AF] font-semibold">Aksi untuk kartu inventaris terpilih:</span>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <button type="button" class="h-[32px] px-3 bg-[#059669] hover:bg-[#047857] text-white rounded-[6px] text-[12px] font-semibold transition-colors flex items-center gap-1.5 border-0 shadow-xs cursor-pointer" onclick="bulkMarkPrintedCards()">
            <i class="bi bi-check2-all"></i> Tandai Sudah Dicetak
          </button>
          <button type="button" class="h-[32px] px-3 bg-[#10B981] hover:bg-[#059669] text-white rounded-[6px] text-[12px] font-semibold transition-colors flex items-center gap-1.5 border-0 shadow-xs cursor-pointer" id="btnExportExcelSelected" onclick="exportExcelSelectedCards()">
            <i class="bi bi-file-earmark-excel-fill"></i> Ekspor Excel (<span id="countExcel">0</span>)
          </button>
          <button type="button" class="h-[32px] px-3 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[6px] text-[12px] font-semibold transition-colors flex items-center gap-1.5 border-0 shadow-xs cursor-pointer" id="btnPrintSelected" onclick="printSelectedCards()">
            <i class="bi bi-printer-fill"></i> Cetak Kartu (<span id="countPrint">0</span>)
          </button>
          <button type="button" class="h-[32px] px-3 bg-[#DC2626] hover:bg-[#B91C1C] text-white rounded-[6px] text-[12px] font-semibold transition-colors flex items-center gap-1.5 border-0 shadow-xs cursor-pointer" id="btnDeleteSelected" onclick="bulkDeleteCards()">
            <i class="bi bi-trash"></i> Hapus Terpilih (<span id="countDelete">0</span>)
          </button>
          <button type="button" class="h-[32px] px-3 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] rounded-[6px] text-[12px] font-medium transition-colors flex items-center gap-1 cursor-pointer" onclick="deselectAllCards()">
            <i class="bi bi-x-circle"></i> Batal
          </button>
        </div>
      </div>

      <!-- Desktop View: Tabel Pas Lebar Layar (Tanpa Scroll Horizontal) -->
      <div class="d-none d-md-block w-100 overflow-hidden" role="region" aria-label="Daftar Kartu Inventaris" tabindex="0">
        <table class="table table-hover align-middle mb-0 w-100" style="font-size: 13px;">
          <thead style="background: #F4F5F7; border-bottom: 1px solid #D1D5D8;">
            <tr>
              <th style="width: 38px;" class="text-center py-2.5">
                <input class="form-check-input cursor-pointer" type="checkbox" id="checkAllRows" onchange="toggleSelectAllCards(this)" title="Pilih Semua Kartu">
              </th>
              <th style="width: 155px;" class="text-[11px] font-bold text-[#53595F] uppercase tracking-wider py-2.5">NOMOR INVENTARIS</th>
              <th class="text-[11px] font-bold text-[#53595F] uppercase tracking-wider py-2.5">NAMA BARANG &amp; TGL PEROLEHAN</th>
              <th style="width: 75px;" class="text-center text-[11px] font-bold text-[#53595F] uppercase tracking-wider py-2.5">LABEL QR</th>
              <th style="width: 95px;" class="text-center text-[11px] font-bold text-[#53595F] uppercase tracking-wider py-2.5">LOKASI</th>
              <th class="text-end text-[11px] font-bold text-[#53595F] uppercase tracking-wider py-2.5 pe-3" style="width: 210px;">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#E5E7EB]">
            '.$cardsTableRowsHtml.'
          </tbody>
        </table>
      </div>

      <!-- Mobile View: Kartu Vertikal (Tanpa Scroll Horizontal) -->
      <div class="d-block d-md-none divide-y divide-[#E5E7EB]">
        '.$cardsMobileCardsHtml.'
      </div>

      <div class="bg-white border-t border-[#D1D5D8] py-2.5 px-4 flex flex-wrap justify-between items-center gap-2">
        <div class="text-[12px] text-[#53595F]">
          Menampilkan <strong>'.($totalFilteredCards > 0 ? ($offsetCards + 1) : 0).' - '.min($offsetCards + $perPageCards, $totalFilteredCards).'</strong> dari <strong>'.$totalFilteredCards.'</strong> kartu inventaris terdaftar
        </div>
        '.($totalPagesCards > 1 ? '
        <nav aria-label="Halaman Kartu">
          <ul class="pagination pagination-sm m-0 flex items-center gap-1">
            <li class="page-item '.($pageKartu <= 1 ? 'disabled opacity-50' : '').'">
              <a class="h-[28px] px-2 bg-white border border-[#D1D5D8] rounded-[4px] text-[12px] text-[#1B2A3A] flex items-center text-decoration-none" href="'.e(module_url('dashboard.php', array_merge($_GET, ['tab'=>'kartu', 'p_kartu'=>max(1, $pageKartu - 1)]))).'"><i class="bi bi-chevron-left"></i></a>
            </li>
            '.implode('', array_map(function($p) use ($pageKartu) {
                if ($p === '...') return '<li class="page-item disabled px-1 text-[#A0A5AA]">&hellip;</li>';
                $isAct = ($p == $pageKartu);
                $cls = $isAct ? 'bg-[#0E5C58] text-white font-bold border-[#0E5C58]' : 'bg-white text-[#1B2A3A] border-[#D1D5D8] hover:bg-[#F4F5F7]';
                return '<li class="page-item"><a class="h-[28px] min-w-[28px] px-2 border rounded-[4px] text-[12px] flex items-center justify-center text-decoration-none '.$cls.'" href="'.e(module_url('dashboard.php', array_merge($_GET, ['tab'=>'kartu', 'p_kartu'=>$p]))).'">'.$p.'</a></li>';
            }, (function() use ($pageKartu, $totalPagesCards) {
                $pages = [];
                $start = max(1, $pageKartu - 2);
                $end = min($totalPagesCards, $pageKartu + 2);
                if ($start > 1) {
                    $pages[] = 1;
                    if ($start > 2) $pages[] = '...';
                }
                for ($i = $start; $i <= $end; $i++) $pages[] = $i;
                if ($end < $totalPagesCards) {
                    if ($end < $totalPagesCards - 1) $pages[] = '...';
                    $pages[] = $totalPagesCards;
                }
                return $pages;
            })())).'
            <li class="page-item '.($pageKartu >= $totalPagesCards ? 'disabled opacity-50' : '').'">
              <a class="h-[28px] px-2 bg-white border border-[#D1D5D8] rounded-[4px] text-[12px] text-[#1B2A3A] flex items-center text-decoration-none" href="'.e(module_url('dashboard.php', array_merge($_GET, ['tab'=>'kartu', 'p_kartu'=>min($totalPagesCards, $pageKartu + 1)]))).'"><i class="bi bi-chevron-right"></i></a>
            </li>
          </ul>
        </nav>
        ' : '').'
      </div>
    </div>
    ' ;

