<?php
    // --- TAMPILAN ANTREAN PEMELIHARAAN (KOMBAI DESIGN 1:1) ---
    $searchQueue = trim((string)($_GET['q_queue'] ?? ''));
    $pageQueue = max(1, (int)($_GET['p_queue'] ?? 1));
    $perPageQueue = 8;

    // Ambil seluruh daftar aset yang belum dilakukan maintenance di bulan & tahun terpilih langsung dari Database
    $queueAssets = [];
    try {
        $qWhere = " WHERE (a.status = 'Aktif' OR a.status IS NULL OR a.status = '') ";
        $qParams = [];
        if ($cabangId > 0) {
            $qWhere .= " AND a.id_cabang = ? ";
            $qParams[] = $cabangId;
        }

        // Filter aset yang belum pernah di-scan di bulan & tahun terpilih
        $qWhere .= " AND a.id NOT IN (
            SELECT asset_id FROM maintenance_scan 
            WHERE maintenance_month = ? AND maintenance_year = ?
        ) ";
        $qParams[] = $month;
        $qParams[] = $year;

        $cCab = name_column('cabang') ?: 'nama_cabang';
        $cDiv = name_column('divisi') ?: 'nama_divisi';
        $cKar = name_column('karyawan') ?: 'nama_karyawan';

        $qSql = "
            SELECT 
                a.id, a.kode_inventaris, a.merk, a.model, a.keterangan, a.ruangan,
                c.`{$cCab}` AS cabang_nama,
                d.`{$cDiv}` AS divisi_nama,
                k.`{$cKar}` AS karyawan_nama,
                q.token AS qr_token,
                last_m.max_date AS last_maint_date
            FROM assets a
            LEFT JOIN cabang c ON c.id = a.id_cabang
            LEFT JOIN divisi d ON d.id = a.id_divisi
            LEFT JOIN karyawan k ON k.id = a.id_karyawan
            LEFT JOIN asset_qr_tokens q ON q.asset_id = a.id
            LEFT JOIN (
                SELECT asset_id, MAX(maintenance_date) AS max_date 
                FROM maintenance_scan 
                GROUP BY asset_id
            ) last_m ON last_m.asset_id = a.id
            {$qWhere}
            ORDER BY last_m.max_date ASC, a.kode_inventaris ASC
        ";

        $qStmt = db()->prepare($qSql);
        $qStmt->execute($qParams);
        $dueRows = $qStmt->fetchAll();

        foreach ($dueRows as $r) {
            $aid = (int)$r['id'];
            $tok = !empty($r['qr_token']) ? $r['qr_token'] : get_static_qr_token($aid);
            
            // Format pemeliharaan terakhir
            $lastMaintStr = 'Belum pernah';
            $isOverdue = true;
            if (!empty($r['last_maint_date'])) {
                $lastTs = strtotime($r['last_maint_date']);
                $lastMonth = (int)date('n', $lastTs);
                $lastYear = (int)date('Y', $lastTs);
                $lastMaintStr = ($monthNames[$lastMonth] ?? date('M', $lastTs)) . ' ' . $lastYear;
                
                $diffMonths = (($year - $lastYear) * 12) + ($month - $lastMonth);
                $isOverdue = ($diffMonths >= 2);
            }

            $devName = trim(($r['merk'] ?? '') . ' ' . ($r['model'] ?? ''));
            if ($devName === '') $devName = 'Perangkat Komputer IT';

            $roomName = !empty($r['ruangan']) ? $r['ruangan'] : (!empty($r['divisi_nama']) ? $r['divisi_nama'] : 'Operasional');

            $queueAssets[] = [
                'id' => $aid,
                'kode_inventaris' => (string)($r['kode_inventaris'] ?? ('INV-IT-' . $aid)),
                'nama_perangkat' => $devName,
                'pengguna' => (string)($r['karyawan_nama'] ?? 'Umum / Staf'),
                'cabang' => (string)($r['cabang_nama'] ?? 'KPO'),
                'ruangan' => (string)$roomName,
                'token' => $tok,
                'last_maint' => $lastMaintStr,
                'is_overdue' => $isOverdue
            ];
        }
    } catch (Throwable $e) {}

    // Fallback jika database masih kosong
    if (empty($queueAssets)) {
        if (!empty($dashData['upcoming_list'])) {
            foreach ($dashData['upcoming_list'] as $u) {
                $aid = (int)($u['asset_id'] ?? 0);
                $queueAssets[] = [
                    'id' => $aid,
                    'kode_inventaris' => (string)($u['kode_inventaris'] ?? ('INV-IT-' . $aid)),
                    'nama_perangkat' => (string)($u['nama_perangkat'] ?? 'Perangkat IT'),
                    'pengguna' => (string)($u['karyawan_nama'] ?? '-'),
                    'cabang' => (string)($u['cabang_nama'] ?? 'KPO'),
                    'ruangan' => (string)($u['divisi_nama'] ?? 'Operasional'),
                    'token' => get_static_qr_token($aid),
                    'last_maint' => 'Sep 2026',
                    'is_overdue' => false
                ];
            }
        }
    }

    $filteredQueueAssets = $queueAssets;
    if ($searchQueue !== '') {
        $sqLower = strtolower($searchQueue);
        $filteredQueueAssets = array_filter($queueAssets, function($item) use ($sqLower) {
            $haystack = strtolower(
                ($item['kode_inventaris'] ?? '') . ' ' .
                ($item['nama_perangkat'] ?? '') . ' ' .
                ($item['pengguna'] ?? '') . ' ' .
                ($item['cabang'] ?? '') . ' ' .
                ($item['ruangan'] ?? '') . ' ' .
                ($item['last_maint'] ?? '')
            );
            return strpos($haystack, $sqLower) !== false;
        });
    }

    $totalQueueCount = ($totalDue > 0 && $searchQueue === '') ? $totalDue : count($filteredQueueAssets);
    $totalPagesQueue = max(1, (int)ceil($totalQueueCount / $perPageQueue));
    $pageQueue = min($pageQueue, $totalPagesQueue);
    $offsetQueue = ($pageQueue - 1) * $perPageQueue;
    $pagedQueueAssets = array_slice(array_values($filteredQueueAssets), $offsetQueue, $perPageQueue);

    $queueRowsHtml = '';
    $rIdx = 0;
    foreach ($pagedQueueAssets as $item) {
        $rIdx++;
        $dotColor = !empty($item['is_overdue']) ? 'bg-[#A5292B]' : 'bg-[#875315]';
        $textColor = !empty($item['is_overdue']) ? 'text-[#A5292B]' : 'text-[#875315]';
        $actionUrl = !empty($item['token'])
            ? module_url('scan.php', ['t' => $item['token']])
            : (!empty($item['id']) ? module_url('scan.php', ['id' => $item['id']]) : module_url('scanner.php'));

        $isSel = ($pageQueue === 1 && $rIdx === 3);
        $rowBg = $isSel ? 'bg-[#D9F1EF]' : 'hover:bg-[#F4F5F7]';
        $accentBar = $isSel ? '<div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0E5C58]" data-kid="1-2-2-1-7-3-3-1" data-name="teal left accent bar on selected row"></div>' : '';
        $borderClass = ($rIdx < count($pagedQueueAssets)) ? 'border-b border-[#D1D5D8]' : '';

        $queueRowsHtml .= '
        <!-- Desktop Table Row (md+) -->
        <div class="hidden md:flex h-[48px] px-4 items-center '.$borderClass.' '.$rowBg.' transition-colors relative" data-kid="1-2-2-1-7-3-'.$rIdx.'" data-name="queue table row ('.e($item['kode_inventaris']).')">
          '.$accentBar.'
          <div class="w-[24%] shrink-0 tabular-nums font-bold text-[12px] font-mono text-[#1B2A3A] truncate pr-3" data-kid="1-2-2-1-7-3-'.$rIdx.'-1" data-name=\'asset inventory code "'.e($item['kode_inventaris']).'"\' title="'.e($item['kode_inventaris']).'">'.e($item['kode_inventaris']).'</div>
          <div class="flex-1 min-w-0 flex items-baseline gap-2 truncate px-3" data-kid="1-2-2-1-7-3-'.$rIdx.'-2" data-name="device and user cell">
            <span class="text-[13px] font-semibold text-[#1B2A3A] truncate" data-kid="1-2-2-1-7-3-'.$rIdx.'-2-1" data-name=\'device name "'.e($item['nama_perangkat']).'"\' title="'.e($item['nama_perangkat']).'">'.e($item['nama_perangkat']).'</span>
            <span class="text-[12px] text-[#53595F] truncate shrink-0" data-kid="1-2-2-1-7-3-'.$rIdx.'-2-2" data-name=\'user name "'.e($item['pengguna']).'"\' title="'.e($item['pengguna']).'">'.e($item['pengguna']).'</span>
          </div>
          <div class="w-[20%] shrink-0 flex items-baseline gap-1.5 truncate px-3" data-kid="1-2-2-1-7-3-'.$rIdx.'-3" data-name="branch and room cell">
            <span class="text-[13px] font-semibold text-[#1B2A3A] truncate" data-kid="1-2-2-1-7-3-'.$rIdx.'-3-1" data-name=\'branch "'.e($item['cabang']).'"\' title="'.e($item['cabang']).'">'.e($item['cabang']).'</span>
            <span class="text-[12px] text-[#53595F] truncate" data-kid="1-2-2-1-7-3-'.$rIdx.'-3-2" data-name=\'room "/ '.e($item['ruangan']).'"\' title="'.e($item['ruangan']).'">/ '.e($item['ruangan']).'</span>
          </div>
          <div class="w-[18%] shrink-0 flex items-center gap-2 px-3" data-kid="1-2-2-1-7-3-'.$rIdx.'-4" data-name="last maintenance cell">
            <span class="w-2 h-2 rounded-full '.$dotColor.' shrink-0" data-kid="1-2-2-1-7-3-'.$rIdx.'-4-1" data-name="status dot"></span>
            <span class="text-[12px] '.$textColor.' font-semibold tabular-nums truncate" data-kid="1-2-2-1-7-3-'.$rIdx.'-4-2" data-name=\'last maintenance date "'.e($item['last_maint']).'"\'>'.e($item['last_maint']).'</span>
          </div>
          <div class="w-[110px] shrink-0 flex justify-end" data-kid="1-2-2-1-7-3-'.$rIdx.'-5" data-name="action cell">
            <a href="'.e($actionUrl).'" class="h-[32px] px-3 bg-[#FFFFFF] border border-[#D1D5D8] rounded-[6px] text-[12px] font-semibold text-[#1B2A3A] hover:bg-[#F4F5F7] hover:border-[#A0A5AA] transition-all cursor-pointer inline-flex items-center justify-center text-decoration-none shadow-xs" data-kid="1-2-2-1-7-3-'.$rIdx.'-5-1" data-name=\'row action button "Buka aset"\'>Buka aset</a>
          </div>
        </div>
        <!-- Mobile Card Row (< md) -->
        <div class="flex md:hidden flex-col gap-2 p-3.5 '.$borderClass.' '.$rowBg.' transition-colors relative">
          '.$accentBar.'
          <div class="flex items-center justify-between gap-2">
            <span class="px-2 py-0.5 rounded bg-[#F4F5F7] border border-[#E5E7EB] font-mono font-bold text-[11px] text-[#0E5C58] truncate">'.e($item['kode_inventaris']).'</span>
            <span class="inline-flex items-center gap-1.5 text-[11px] '.$textColor.' font-bold shrink-0">
              <span class="w-2 h-2 rounded-full '.$dotColor.'"></span>
              '.e($item['last_maint']).'
            </span>
          </div>
          <div>
            <div class="text-[14px] font-bold text-[#1B2A3A] leading-snug">'.e($item['nama_perangkat']).'</div>
            <div class="text-[12px] text-[#53595F] mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
              <span><i class="bi bi-person text-[#0E5C58] me-1"></i>'.e($item['pengguna']).'</span>
              <span>&middot;</span>
              <span><i class="bi bi-geo-alt text-[#0E5C58] me-1"></i>'.e($item['cabang']).' / '.e($item['ruangan']).'</span>
            </div>
          </div>
          <div class="pt-1 flex justify-end">
            <a href="'.e($actionUrl).'" class="h-[34px] w-full bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[12px] font-semibold transition-all inline-flex items-center justify-center gap-1.5 text-decoration-none shadow-xs">
              <i class="bi bi-qr-code-scan"></i> Buka &amp; Inspeksi Aset
            </a>
          </div>
        </div>';
    }

    if (empty($pagedQueueAssets)) {
        $queueRowsHtml = '
        <div class="py-12 text-center text-[#53595F]">
          <i class="bi bi-check-circle text-[#0E5C58] text-[36px] d-block mb-2"></i>
          <div class="font-semibold text-[15px] text-[#1B2A3A]">Tidak ada antrean pemeliharaan</div>
          <div class="text-[13px] mt-1">Semua unit telah selesai dipelihara atau tidak ada aset yang sesuai kriteria pencarian.</div>
        </div>';
    }

    $startItem = $totalQueueCount > 0 ? ($offsetQueue + 1) : 0;
    $endItem = min($totalQueueCount, $offsetQueue + count($pagedQueueAssets));

    $queuePaginationHtml = '';
    if ($pageQueue > 1) {
        $prevUrl = module_url('dashboard.php', array_merge($_GET, ['p_queue' => $pageQueue - 1]));
        $queuePaginationHtml .= '<a href="'.e($prevUrl).'" class="w-[28px] h-[28px] rounded-[6px] flex items-center justify-center border border-[#D1D5D8] bg-[#FFFFFF] text-[#1B2A3A] hover:bg-[#E9EAEC] transition-colors text-decoration-none cursor-pointer" data-kid="1-2-2-1-7-4-2-1" data-name="previous page button"><i class="bi bi-chevron-left" data-kid="1-2-2-1-7-4-2-1-1" data-name="chevron left icon"></i></a>';
    } else {
        $queuePaginationHtml .= '<button class="w-[28px] h-[28px] rounded-[6px] flex items-center justify-center border border-[#D1D5D8] bg-[#FFFFFF] text-[#A0A5AA] cursor-not-allowed" disabled data-kid="1-2-2-1-7-4-2-1" data-name="disabled previous page button"><i class="bi bi-chevron-left" data-kid="1-2-2-1-7-4-2-1-1" data-name="chevron left icon"></i></button>';
    }

    for ($p = 1; $p <= min(5, $totalPagesQueue); $p++) {
        if ($p === $pageQueue) {
            $queuePaginationHtml .= '<button class="w-[28px] h-[28px] rounded-[6px] flex items-center justify-center border border-[#D1D5D8] bg-[#FFFFFF] text-[#1B2A3A] font-bold shadow-[0_1px_2px_rgba(0,0,0,0.05)] cursor-pointer" data-kid="1-2-2-1-7-4-2-2" data-name=\'current page number "'.$p.'"\'>'.$p.'</button>';
        } else {
            $pUrl = module_url('dashboard.php', array_merge($_GET, ['p_queue' => $p]));
            $queuePaginationHtml .= '<a href="'.e($pUrl).'" class="w-[28px] h-[28px] rounded-[6px] flex items-center justify-center border border-transparent text-[#53595F] hover:bg-[#D1D5D8] transition-colors cursor-pointer text-decoration-none" data-kid="1-2-2-1-7-4-2-3" data-name=\'page number "'.$p.'" button\'>'.$p.'</a>';
        }
    }
    if ($totalPagesQueue > 5) {
        $queuePaginationHtml .= '<span class="px-1 text-[#A0A5AA]" data-kid="1-2-2-1-7-4-2-5" data-name="page ellipsis separator">...</span>';
    }

    if ($pageQueue < $totalPagesQueue) {
        $nextUrl = module_url('dashboard.php', array_merge($_GET, ['p_queue' => $pageQueue + 1]));
        $queuePaginationHtml .= '<a href="'.e($nextUrl).'" class="w-[28px] h-[28px] rounded-[6px] flex items-center justify-center border border-[#D1D5D8] bg-[#FFFFFF] text-[#1B2A3A] hover:bg-[#E9EAEC] transition-colors text-decoration-none cursor-pointer" data-kid="1-2-2-1-7-4-2-6" data-name="next page button"><i class="bi bi-chevron-right" data-kid="1-2-2-1-7-4-2-6-1" data-name="chevron right icon"></i></a>';
    } else {
        $queuePaginationHtml .= '<button class="w-[28px] h-[28px] rounded-[6px] flex items-center justify-center border border-[#D1D5D8] bg-[#FFFFFF] text-[#A0A5AA] cursor-not-allowed" disabled data-kid="1-2-2-1-7-4-2-6" data-name="disabled next page button"><i class="bi bi-chevron-right" data-kid="1-2-2-1-7-4-2-6-1" data-name="chevron right icon"></i></button>';
    }

    $body .= '
    <!-- Queue Surface -->
    <div class="bg-[#FFFFFF] border border-[#D1D5D8] rounded-[10px] flex flex-col shrink-0 overflow-hidden" data-kid="1-2-2-1-7" data-name="queue table card surface">
      <!-- Toolbar (Responsive Mobile & Desktop) -->
      <div class="min-h-[56px] py-2.5 px-3 md:px-4 flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-2.5 border-b border-[#D1D5D8] bg-[#FFFFFF]" data-kid="1-2-2-1-7-1" data-name="toolbar row with filter and scan">
        <form method="get" class="relative m-0 flex-1 sm:flex-initial" data-kid="1-2-2-1-7-1-1" data-name="queue filter input wrapper with icon">
          <input type="hidden" name="cabang" value="'.(int)$cabangId.'">
          <input type="hidden" name="bulan" value="'.(int)$month.'">
          <input type="hidden" name="tahun" value="'.(int)$year.'">
          <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-[#53595F] text-[14px]" data-kid="1-2-2-1-7-1-1-1" data-name="magnifying glass icon in input"></i>
          <input class="h-[36px] w-full sm:w-[280px] pl-9 pr-3 text-[13px] md:text-[14px] font-medium text-[#1B2A3A] border border-[#D1D5D8] rounded-[8px] outline-none focus:border-[#0E5C58] placeholder:text-[#A0A5AA] transition-colors" data-kid="1-2-2-1-7-1-1-2" data-name="queue filter search input field" placeholder="Saring antrean..." type="text" name="q_queue" value="'.e($searchQueue).'"/>
        </form>
        <div class="flex items-center gap-2 justify-end">
          <a href="'.e(module_url('scanner.php')).'" class="h-[36px] px-3.5 md:px-4 bg-[#0E5C58] hover:bg-[#0c4f4b] text-white rounded-[8px] font-semibold text-[13px] md:text-[14px] flex-1 sm:flex-initial flex items-center justify-center gap-2 transition-colors cursor-pointer text-decoration-none shadow-sm" data-kid="1-2-2-1-7-1-2" data-name=\'primary "Scan QR Unit" action button\'>
            <i class="bi bi-qr-code-scan" data-kid="1-2-2-1-7-1-2-1" data-name="QR code scan icon"></i>
            Scan QR Unit
          </a>
          <button type="button" class="h-[36px] px-3 bg-[#FFFFFF] border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#53595F] rounded-[8px] font-semibold text-[13px] flex items-center gap-1.5 transition-colors shrink-0" data-bs-toggle="modal" data-bs-target="#modalMobileScannerQr" title="Pindai di HP">
            <i class="bi bi-phone"></i>
          </button>
          <a href="'.e(module_url('print_report.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'" target="_blank" class="h-[36px] px-3 bg-[#FFFFFF] border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#53595F] rounded-[8px] font-semibold text-[13px] flex items-center gap-1.5 transition-colors text-decoration-none shrink-0" title="Cetak Rekap">
            <i class="bi bi-printer"></i>
          </a>
        </div>
      </div>

      <!-- Table Wrapper (No Horizontal Scroll: Desktop Table on md+, Mobile Cards on < md) -->
      <div class="w-full">
        <!-- Desktop Table Header (40px, hidden on mobile) -->
        <div class="hidden md:flex h-[40px] px-4 items-center bg-[#F4F5F7] border-b border-[#D1D5D8] text-[12px] font-bold text-[#53595F] uppercase tracking-wider" data-kid="1-2-2-1-7-2" data-name="table header row">
          <div class="w-[24%] shrink-0 pr-3" data-kid="1-2-2-1-7-2-1" data-name=\'column header "Kode Inventaris"\'>Kode Inventaris</div>
          <div class="flex-1 min-w-0 px-3" data-kid="1-2-2-1-7-2-2" data-name=\'column header "Perangkat &amp; Pengguna"\'>Perangkat &amp; Pengguna</div>
          <div class="w-[20%] shrink-0 px-3" data-kid="1-2-2-1-7-2-3" data-name=\'column header "Cabang &amp; Ruangan"\'>Cabang &amp; Ruangan</div>
          <div class="w-[18%] shrink-0 px-3" data-kid="1-2-2-1-7-2-4" data-name=\'column header "Pemeliharaan Terakhir"\'>Pemeliharaan Terakhir</div>
          <div class="w-[110px] shrink-0 text-right" data-kid="1-2-2-1-7-2-5" data-name=\'column header "Aksi"\'>Aksi</div>
        </div>

        <!-- Rows -->
        <div class="flex flex-col" data-kid="1-2-2-1-7-3" data-name="queue table body rows container">
          '.$queueRowsHtml.'
        </div>
      </div>

      <!-- Footer / Pagination -->
      <div class="min-h-[42px] py-2 px-3 md:px-4 flex flex-col sm:flex-row justify-between items-center gap-2 bg-[#F4F5F7] border-t border-[#D1D5D8] text-[12px] md:text-[13px] font-semibold text-[#53595F]" data-kid="1-2-2-1-7-4" data-name="pagination footer bar">
        <div data-kid="1-2-2-1-7-4-1" data-name="pagination results summary text">Menampilkan <span class="font-bold text-[#1B2A3A]">'.$startItem.'&ndash;'.$endItem.'</span> dari <span class="font-bold text-[#1B2A3A]">'.$totalQueueCount.'</span> aset</div>
        <div class="flex items-center gap-1.5" data-kid="1-2-2-1-7-4-2" data-name="pagination page numbers container">
          '.$queuePaginationHtml.'
        </div>
      </div>
    </div>

    <!-- Secondary Collapsible: Branch Compliance Matrix & Activity Stream -->
    <details class="mt-4 bg-[#FFFFFF] border border-[#D1D5D8] rounded-[10px] p-4 text-[13px] text-[#53595F] cursor-pointer group shadow-xs">
      <summary class="font-bold text-[#1B2A3A] flex items-center justify-between select-none">
        <div class="flex items-center gap-2">
          <i class="bi bi-buildings text-[#0E5C58]"></i>
          <span>Rekapitulasi Kepatuhan Cabang &amp; Activity Stream Audit</span>
        </div>
        <i class="bi bi-chevron-down text-[#53595F] group-open:rotate-180 transition-transform text-[12px]"></i>
      </summary>
      <div class="pt-4 mt-3 border-t border-[#D1D5D8] cursor-default">
        <div class="row g-4">
          <div class="col-lg-7">
            <div class="card border shadow-xs mb-3">
              <div class="card-header bg-white py-2 px-3 fw-bold small text-dark d-flex justify-content-between align-items-center">
                <span><i class="bi bi-diagram-3 text-primary me-1"></i> Kepatuhan Per Cabang</span>
                <a href="'.e(module_url('print_report.php', ['bulan'=>$month,'tahun'=>$year])).'" target="_blank" class="btn btn-xs btn-outline-secondary py-0.5 px-2 small"><i class="bi bi-printer me-1"></i> Cetak Rekap</a>
              </div>
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                  <thead class="table-light">
                    <tr>
                      <th>Kantor Cabang</th>
                      <th class="text-center">Target</th>
                      <th class="text-center">Selesai</th>
                      <th class="text-center">Belum</th>
                      <th class="text-center">Kepatuhan</th>
                    </tr>
                  </thead>
                  <tbody>
                    '.$branchRowsHtml.'
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="card border shadow-xs mb-3">
              <div class="card-header bg-white py-2 px-3 fw-bold small text-dark">
                <i class="bi bi-activity text-primary me-1"></i> Live Activity Stream
              </div>
              <div class="card-body p-3" style="max-height: 280px; overflow-y: auto;">
                '.$activityStreamHtml.'
              </div>
            </div>
          </div>
        </div>
      </div>
    </details>';
