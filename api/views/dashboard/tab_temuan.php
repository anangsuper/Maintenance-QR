<?php
    // --- TAMPILAN TAB TEMUAN KENDALA & STATUS PERBAIKAN (KOMBAI TEAL DESIGN SYSTEM) ---
    // Menampilkan rekapitulasi temuan kendala teknis, audit berkala, dan keluhan pengguna komputer.

    if (empty($monthNames) || !is_array($monthNames)) {
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
    }
    if (!function_exists('qr_url')) {
        function qr_url(string $file = '', array $params = []): string {
            return function_exists('module_url') ? module_url($file, $params) : $file;
        }
    }

    $month    = isset($month) ? (int)$month : (int)date('n');
    $year     = isset($year) ? (int)$year : (int)date('Y');
    $cabangId = isset($cabangId) ? (int)$cabangId : 0;

    $statusTemuanFilter  = trim((string)($_GET['status_temuan'] ?? 'all'));
    $tingkatTemuanFilter = trim((string)($_GET['tingkat_temuan'] ?? 'all'));
    $qTemuan             = trim((string)($_GET['q_temuan'] ?? ''));

    // Handle parameter bulan_temuan atau bulan
    $rawBulanTemuan = $_GET['bulan_temuan'] ?? ($_GET['bulan'] ?? 'all');
    if ($rawBulanTemuan === 'all' || $rawBulanTemuan === '0' || $rawBulanTemuan === '') {
        $bulanTemuan = 0; // 0 = Semua Periode
    } else {
        $bulanTemuan = max(1, min(12, (int)$rawBulanTemuan));
    }
    $tahunTemuan = isset($_GET['tahun_temuan']) ? (int)$_GET['tahun_temuan'] : (isset($_GET['tahun']) ? (int)$_GET['tahun'] : $year);

    // Ambil seluruh data temuan tahun ini
    $allFindingsYearRaw = get_findings_report(0, $tahunTemuan, $cabangId);

    // Konsolidasi dengan active findings dari dashboard
    $existingAssetIds = [];
    foreach ($allFindingsYearRaw as $item) {
        $aid = (int)($item['asset_id'] ?? 0);
        if ($aid > 0) {
            $existingAssetIds[$aid] = true;
        }
    }

    if (!empty($unresolvedFindings)) {
        foreach ($unresolvedFindings as $uf) {
            $aid = (int)($uf['asset_id'] ?? 0);
            if ($aid > 0 && isset($existingAssetIds[$aid])) {
                continue;
            }

            $devName = !empty($uf['perangkat']) 
                ? $uf['perangkat'] 
                : (!empty($uf['nama_perangkat']) 
                    ? $uf['nama_perangkat'] 
                    : trim(($uf['merk'] ?? '') . ' ' . ($uf['model'] ?? '')));
            if ($devName === '') $devName = 'Perangkat IT';

            $fText = '';
            foreach (['finding', 'findings', 'deskripsi', 'catatan', 'keterangan'] as $fk) {
                if (!empty($uf[$fk]) && trim((string)$uf[$fk]) !== '' && trim((string)$uf[$fk]) !== '-') {
                    $fText = trim((string)$uf[$fk]);
                    break;
                }
            }
            if ($fText === '') $fText = 'Kendala perangkat dilaporkan';

            $fDate = '';
            foreach (['date', 'created_at', 'maintenance_date', 'reported_at', 'tanggal'] as $dk) {
                if (!empty($uf[$dk]) && trim((string)$uf[$dk]) !== '' && trim((string)$uf[$dk]) !== '-') {
                    $fDate = trim((string)$uf[$dk]);
                    break;
                }
            }

            $rawStUf = strtolower(trim((string)($uf['status'] ?? '')));
            $ufRepSt = (in_array($rawStUf, ['proses', 'in progress', 'sedang perbaikan', 'dalam proses'], true)) ? 'In Progress' : 'Perlu Perbaikan';
            $sid = (int)($uf['log_id'] ?? $uf['id'] ?? 0);

            $allFindingsYearRaw[] = [
                'id' => $sid,
                'finding_id' => 0,
                'scan_id' => $sid,
                'asset_id' => $aid,
                'token' => (string)($uf['token'] ?? ''),
                'kode_inventaris' => (string)($uf['kode_inventaris'] ?? '-'),
                'merk_model' => $devName,
                'karyawan_nama' => (string)($uf['karyawan_nama'] ?? $uf['pengguna'] ?? '-'),
                'cabang_nama' => (string)($uf['cabang_nama'] ?? '-'),
                'divisi_nama' => (string)($uf['divisi_nama'] ?? '-'),
                'finding' => $fText,
                'action_taken' => (string)($uf['action_taken'] ?? $uf['recommendation'] ?? '-'),
                'proses_perbaikan' => (string)($uf['proses_perbaikan'] ?? ''),
                'catatan_penyelesaian' => (string)($uf['catatan_penyelesaian'] ?? ''),
                'severity' => (string)($uf['severity'] ?? 'Sedang'),
                'repair_status' => $ufRepSt,
                'teknisi' => (string)($uf['reporter'] ?? $uf['technician_name'] ?? 'Teknisi'),
                'reported_by' => (string)($uf['reporter'] ?? $uf['technician_name'] ?? 'Teknisi'),
                'reported_at' => $fDate,
                'resolved_by' => '',
                'resolved_at' => '',
                'created_at' => $fDate
            ];
            $existingAssetIds[$aid] = true;
        }
    }

    // Urutkan seluruh temuan berdasarkan tanggal terbaru
    usort($allFindingsYearRaw, function($a, $b) {
        $dtA = $a['created_at'] ?: ($a['reported_at'] ?: '');
        $dtB = $b['created_at'] ?: ($b['reported_at'] ?: '');
        return strcmp($dtB, $dtA);
    });

    // Hitung distribusi bulanan & status global
    $monthlyCounts = [
        0 => count($allFindingsYearRaw),
        1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0,
        7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0
    ];
    $statusCountsGlobal = [
        'total' => count($allFindingsYearRaw),
        'perlu_perbaikan' => 0,
        'proses' => 0,
        'selesai' => 0
    ];

    foreach ($allFindingsYearRaw as &$fItem) {
        $rawD = $fItem['created_at'] ?: ($fItem['reported_at'] ?: '');
        $mItem = $rawD !== '' ? (int)date('n', strtotime($rawD)) : 0;
        $fItem['month_num'] = $mItem;
        if ($mItem >= 1 && $mItem <= 12) {
            $monthlyCounts[$mItem]++;
        }

        $st = strtolower(trim((string)($fItem['repair_status'] ?? '')));
        if (in_array($st, ['resolved', 'selesai', 'closed', 'ok', 'done'], true)) {
            $statusCountsGlobal['selesai']++;
            $fItem['calc_status'] = 'Resolved';
            $fItem['is_active'] = false;
        } elseif (in_array($st, ['in progress', 'proses', 'sedang perbaikan', 'dalam proses'], true)) {
            $statusCountsGlobal['proses']++;
            $fItem['calc_status'] = 'In Progress';
            $fItem['is_active'] = true;
        } else {
            $statusCountsGlobal['perlu_perbaikan']++;
            $fItem['calc_status'] = 'Perlu Perbaikan';
            $fItem['is_active'] = true;
        }
    }
    unset($fItem);

    // Filter daftar temuan sesuai Periode, Status, Tingkat Keparahan, dan Pencarian
    $filteredFindings = array_values(array_filter($allFindingsYearRaw, function($f) use ($bulanTemuan, $statusTemuanFilter, $tingkatTemuanFilter, $qTemuan) {
        $mItem = (int)($f['month_num'] ?? 0);
        $isActive = !empty($f['is_active']);

        // Filter Periode
        if ($bulanTemuan > 0) {
            // Jika memilih bulan berjalan (Nov) dan temuan aktif carryover masih berjalan:
            $matchMonth = ($mItem === $bulanTemuan);
            $isCarryoverActive = ($bulanTemuan === 11 && $isActive);
            if (!$matchMonth && !$isCarryoverActive) {
                return false;
            }
        }

        // Filter Status
        $st = strtolower(trim((string)($f['calc_status'] ?? $f['repair_status'] ?? '')));
        if ($statusTemuanFilter === 'perlu_perbaikan') {
            if ($st !== 'perlu perbaikan') return false;
        } elseif ($statusTemuanFilter === 'proses') {
            if ($st !== 'in progress') return false;
        } elseif ($statusTemuanFilter === 'selesai') {
            if ($st !== 'resolved') return false;
        }

        // Filter Tingkat Keparahan
        if ($tingkatTemuanFilter !== 'all' && $tingkatTemuanFilter !== '') {
            if (strcasecmp(trim((string)($f['severity'] ?? '')), $tingkatTemuanFilter) !== 0) return false;
        }

        // Filter Pencarian Teks
        if ($qTemuan !== '') {
            $haystack = strtolower(
                ($f['kode_inventaris'] ?? '') . ' ' .
                ($f['merk_model'] ?? '') . ' ' .
                ($f['karyawan_nama'] ?? '') . ' ' .
                ($f['cabang_nama'] ?? '') . ' ' .
                ($f['divisi_nama'] ?? '') . ' ' .
                ($f['finding'] ?? '') . ' ' .
                ($f['action_taken'] ?? '') . ' ' .
                ($f['proses_perbaikan'] ?? '') . ' ' .
                ($f['teknisi'] ?? '')
            );
            if (strpos($haystack, strtolower($qTemuan)) === false) return false;
        }

        return true;
    }));

    $displayTotalCount = count($filteredFindings);

    // Render baris tabel
    $findingsTableRows = '';
    $findingsCardsHtml = '';
    $noF = 0;

    if (empty($filteredFindings)) {
        $findingsTableRows = '
        <tr>
          <td colspan="7" class="text-center py-12 text-[#53595F]">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-[#ECFDF5] text-[#059669] mb-3">
              <i class="bi bi-shield-check text-[28px]"></i>
            </div>
            <div class="font-bold text-[15px] text-[#1B2A3A]">Tidak ada temuan kendala yang sesuai filter</div>
            <div class="text-[13px] text-[#53595F] mt-1 max-w-[440px] mx-auto">Seluruh perangkat beroperasi normal atau silakan sesuaikan filter bulan, status, dan kata kunci pencarian.</div>
          </td>
        </tr>';
        $findingsCardsHtml = '
        <div class="text-center py-10 px-4 bg-white rounded-[10px] border border-[#D1D5D8] text-[#53595F]">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-[#ECFDF5] text-[#059669] mb-2.5">
            <i class="bi bi-shield-check text-[24px]"></i>
          </div>
          <div class="font-bold text-[14px] text-[#1B2A3A]">Tidak ada temuan yang sesuai</div>
          <div class="text-[12px] text-[#53595F] mt-1">Perangkat beroperasi normal pada filter ini.</div>
        </div>';
    } else {
        foreach ($filteredFindings as $f) {
            $noF++;
            $fId = (int)($f['finding_id'] ?? $f['id'] ?? 0);
            $scanId = (int)($f['scan_id'] ?? 0);
            $assetId = (int)($f['asset_id'] ?? 0);
            $token = (string)($f['token'] ?? '');
            $kode = $f['kode_inventaris'] ?? '-';

            // Bersihkan nama merk/model agar tidak ganda (misal 'Asus Asus Vivo AIO')
            $cleanMerk = trim((string)($f['merk'] ?? ''));
            $cleanModel = trim((string)($f['model'] ?? ''));
            if ($cleanMerk !== '' && stripos($cleanModel, $cleanMerk) === 0) {
                $devName = $cleanModel;
            } else {
                $devName = trim($cleanMerk . ' ' . $cleanModel);
            }
            if ($devName === '') $devName = $f['merk_model'] ?? 'Perangkat IT';

            $pic = $f['karyawan_nama'] ?? '-';
            $cabang = $f['cabang_nama'] ?? '-';
            $divisi = $f['divisi_nama'] ?? '-';
            $findingDesc = $f['finding'] ?? 'Kendala perangkat dilaporkan';
            $actionTaken = $f['action_taken'] ?? '-';
            $prosesPerbaikan = $f['proses_perbaikan'] ?? $f['catatan_penyelesaian'] ?? '';
            $severity = $f['severity'] ?? 'Sedang';
            $repairStatus = $f['calc_status'] ?? $f['repair_status'] ?? 'Perlu Perbaikan';
            $teknisi = $f['teknisi'] ?? $f['reported_by'] ?? 'Teknisi';
            $rawDate = !empty($f['created_at']) ? $f['created_at'] : (!empty($f['reported_at']) ? $f['reported_at'] : '');
            $tgl = $rawDate !== '' ? format_id_date($rawDate) : '-';
            $mNum = (int)($f['month_num'] ?? ($rawDate !== '' ? date('n', strtotime($rawDate)) : 0));
            $periodBadgeText = ($mNum >= 1 && $mNum <= 12) ? ($monthNames[$mNum] . ' ' . ($f['created_at'] ? date('Y', strtotime($f['created_at'])) : '2026')) : '2026';

            $lowStatus = strtolower($repairStatus);
            if ($lowStatus === 'resolved') {
                $statusBadge = '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-[#ECFDF5] text-[#059669] border border-[#A7F3D0]"><i class="bi bi-check-circle-fill"></i> Selesai</span>';
                $rowStripe = '';
                $cardBorderColor = '#10B981';
                $isIssueActive = false;
            } elseif ($lowStatus === 'in progress') {
                $statusBadge = '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-[#FFFBEB] text-[#D97706] border border-[#FDE68A]"><i class="bi bi-hourglass-split"></i> Sedang Proses</span>';
                $rowStripe = 'border-l-4 border-l-[#F59E0B] bg-[#FFFDF5]';
                $cardBorderColor = '#F59E0B';
                $isIssueActive = true;
            } else {
                $statusBadge = '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-[#FEF2F2] text-[#DC2626] border border-[#FECACA]"><span class="w-2 h-2 rounded-full bg-[#DC2626] animate-pulse"></span> Perlu Perbaikan</span>';
                $rowStripe = 'border-l-4 border-l-[#DC2626] bg-[#FFF8F8]';
                $cardBorderColor = '#EF4444';
                $isIssueActive = true;
            }

            $lowSev = strtolower($severity);
            if ($lowSev === 'berat' || $lowSev === 'tinggi' || $lowSev === 'kritis') {
                $sevBadge = '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#FEF2F2] text-[#DC2626] border border-[#FECACA]"><i class="bi bi-shield-fill-exclamation"></i> Berat</span>';
            } elseif ($lowSev === 'sedang') {
                $sevBadge = '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#FFFBEB] text-[#D97706] border border-[#FDE68A]"><i class="bi bi-exclamation-diamond-fill"></i> Sedang</span>';
            } else {
                $sevBadge = '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#F1F5F9] text-[#475569] border border-[#CBD5E1]">Ringan</span>';
            }

            $modalDataObj = [
                'finding_id' => $fId,
                'scan_id' => $scanId,
                'asset_id' => $assetId,
                'kode' => $kode,
                'perangkat' => $devName,
                'pengguna' => $pic,
                'finding' => $findingDesc,
                'status' => $repairStatus,
                'proses_perbaikan' => $prosesPerbaikan,
                'teknisi' => $teknisi
            ];
            $jsonModalAttr = htmlspecialchars(json_encode($modalDataObj), ENT_QUOTES, 'UTF-8');

            // Action Buttons Desktop
            $actionsDesktop = '';
            if ($isIssueActive) {
                if ($token !== '') {
                    $actionsDesktop .= '<a href="'.e(qr_url('scan.php', ['t' => $token, 'action' => 'tindak_lanjut'])).'" class="min-h-[44px] md:min-h-0 md:h-[30px] px-2.5 bg-[#DC2626] hover:bg-[#B91C1C] text-white rounded-[6px] text-[11px] font-bold transition-all inline-flex items-center gap-1 text-decoration-none shadow-xs whitespace-nowrap" title="Form Tindak Lanjut"><i class="bi bi-tools"></i> Selesaikan</a>';
                }
                $actionsDesktop .= '<button type="button" class="min-h-[44px] md:min-h-0 md:h-[30px] min-w-[44px] md:min-w-0 md:w-[30px] bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] text-[#0E5C58] hover:bg-[#F4F5F7] transition-all inline-flex items-center justify-center shrink-0 cursor-pointer" onclick="openProsesPerbaikanModal('.$jsonModalAttr.')" title="Update Progres"><i class="bi bi-pencil-square"></i></button>';
                if ($token !== '') {
                    $actionsDesktop .= '<a href="'.e(qr_url('scan.php', ['t' => $token])).'" target="_blank" class="min-h-[44px] md:min-h-0 md:h-[30px] min-w-[44px] md:min-w-0 md:w-[30px] bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] text-[#0E5C58] hover:bg-[#F4F5F7] transition-all inline-flex items-center justify-center shrink-0 text-decoration-none" title="Lihat Kartu QR"><i class="bi bi-qr-code"></i></a>';
                }
            } else {
                $actionsDesktop .= '<button type="button" class="min-h-[44px] md:min-h-0 md:h-[30px] px-2.5 bg-[#ECFDF5] hover:bg-[#D1FAE5] text-[#059669] border border-[#A7F3D0] rounded-[6px] text-[11px] font-bold transition-all inline-flex items-center gap-1 whitespace-nowrap cursor-pointer" onclick="openProsesPerbaikanModal('.$jsonModalAttr.')"><i class="bi bi-check2-circle"></i> Rincian</button>';
                if ($token !== '') {
                    $actionsDesktop .= '<a href="'.e(qr_url('scan.php', ['t' => $token])).'" target="_blank" class="min-h-[44px] md:min-h-0 md:h-[30px] min-w-[44px] md:min-w-0 md:w-[30px] bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] text-[#0E5C58] hover:bg-[#F4F5F7] transition-all inline-flex items-center justify-center shrink-0 text-decoration-none" title="Lihat Kartu QR"><i class="bi bi-qr-code"></i></a>';
                }
            }

            // Box Uraian Kendala
            if ($isIssueActive) {
                $findingBoxHtml = '
                <div class="bg-[#FEF2F2] border border-[#FECACA] rounded-[6px] p-2.5 text-start">
                  <div class="font-bold text-[#DC2626] text-[12.5px] flex items-start gap-1.5 leading-snug">
                    <i class="bi bi-exclamation-triangle-fill text-[#DC2626] shrink-0 mt-0.5"></i>
                    <span>'.e($findingDesc).'</span>
                  </div>
                  '.($actionTaken !== '-' && $actionTaken !== '' ? '<div class="text-[11px] text-[#53595F] mt-1.5 pt-1.5 border-t border-[#FEE2E2] leading-snug"><span class="font-bold text-[#1B2A3A]">Catatan/Rekomendasi:</span> '.e($actionTaken).'</div>' : '').'
                </div>';
            } else {
                $findingBoxHtml = '
                <div class="bg-[#F9FAFB] border border-[#E5E7EB] rounded-[6px] p-2.5 text-start">
                  <div class="font-semibold text-[#1B2A3A] text-[12.5px] flex items-start gap-1.5 leading-snug">
                    <i class="bi bi-check-circle-fill text-[#10B981] shrink-0 mt-0.5"></i>
                    <span>'.e($findingDesc).'</span>
                  </div>
                  '.($actionTaken !== '-' && $actionTaken !== '' ? '<div class="text-[11px] text-[#53595F] mt-1.5 pt-1.5 border-t border-[#E5E7EB] leading-snug"><span class="font-semibold text-[#53595F]">Tindakan:</span> '.e($actionTaken).'</div>' : '').'
                </div>';
            }

            $findingsTableRows .= '
            <tr class="border-b border-[#E5E7EB] hover:bg-[#F4F6F8] transition-colors '.$rowStripe.'">
              <td class="text-center font-mono font-bold text-[#53595F] text-[12px]" style="width: 44px; padding: 12px 6px;">'.$noF.'</td>
              <td style="width: 250px; padding: 12px 10px;">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span class="px-2 py-0.5 rounded bg-[#E6F4F1] border border-[#B2DFDB] font-mono font-bold text-[11px] text-[#0E5C58]">'.e($kode).'</span>
                  <span class="px-1.5 py-0.5 rounded bg-[#F1F5F9] border border-[#E2E8F0] font-bold text-[10px] text-[#475569]">'.e($periodBadgeText).'</span>
                </div>
                <div class="font-bold text-[#1B2A3A] text-[13.5px] truncate mt-1" title="'.e($devName).'">'.e($devName).'</div>
                <div class="text-[11.5px] text-[#53595F] truncate mt-0.5 flex items-center gap-1">
                  <i class="bi bi-person text-[#0E5C58]"></i> <span class="truncate font-medium text-[#1B2A3A]">'.e($pic).'</span> &middot; <span class="px-1.5 py-0.2 rounded bg-[#F4F5F7] border border-[#E5E7EB] text-[10px] text-[#53595F] shrink-0">'.e($cabang).'</span>
                </div>
              </td>
              <td style="width: 155px; padding: 12px 10px;">
                <div class="font-semibold text-[#1B2A3A] text-[12px] flex items-center gap-1.5">
                  <i class="bi bi-calendar3 text-[#53595F]"></i> '.$tgl.'
                </div>
                <div class="text-[11px] text-[#53595F] mt-1 flex items-center gap-1">
                  <i class="bi bi-person-badge text-[#0E5C58]"></i> <span>Pelapor: <strong>'.e($teknisi).'</strong></span>
                </div>
              </td>
              <td style="min-width: 280px; padding: 12px 10px;">
                '.$findingBoxHtml.'
              </td>
              <td class="text-center" style="width: 95px; padding: 12px 6px;">
                '.$sevBadge.'
              </td>
              <td style="width: 165px; padding: 12px 10px;">
                <div>'.$statusBadge.'</div>
                '.($prosesPerbaikan !== '' ? '<div class="text-[11px] text-[#53595F] bg-white p-2 rounded-[6px] border border-[#E5E7EB] mt-1.5 leading-snug shadow-2xs"><i class="bi bi-clock-history me-1 text-[#0E5C58]"></i>'.e($prosesPerbaikan).'</div>' : '<div class="text-[11px] text-[#9CA3AF] italic mt-1">Belum ada catatan progres</div>').'
              </td>
              <td class="text-end" style="width: 135px; padding: 12px 10px;">
                <div class="flex items-center justify-end gap-1.5">
                  '.$actionsDesktop.'
                </div>
              </td>
            </tr>';

            // Mobile Card
            $findingsCardsHtml .= '
            <div class="bg-white rounded-[10px] border border-[#D1D5D8] p-3.5 shadow-xs relative" style="border-left: 4px solid '.$cardBorderColor.' !important;">
              <div class="flex items-center justify-between gap-2 mb-2">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span class="px-2 py-0.5 rounded bg-[#E6F4F1] border border-[#B2DFDB] font-mono font-bold text-[11px] text-[#0E5C58]">'.e($kode).'</span>
                  <span class="px-1.5 py-0.5 rounded bg-[#F1F5F9] border border-[#E2E8F0] font-bold text-[10px] text-[#475569]">'.e($periodBadgeText).'</span>
                  <span class="px-1.5 py-0.5 rounded bg-[#F4F5F7] border border-[#E5E7EB] text-[10px] text-[#53595F]">'.e($cabang).'</span>
                </div>
                <div>'.$statusBadge.'</div>
              </div>

              <div class="font-bold text-[#1B2A3A] text-[14px] leading-snug">'.e($devName).'</div>
              <div class="text-[12px] text-[#53595F] mt-0.5 mb-2.5 flex items-center gap-1.5">
                <i class="bi bi-person text-[#0E5C58]"></i> <span>PIC: <strong>'.e($pic).'</strong></span> &middot; <span>Divisi: '.e($divisi).'</span>
              </div>

              <div class="mb-2.5">
                <div class="bg-[#F9FAFB] border border-[#E5E7EB] rounded-[6px] p-2.5 text-start">
                  <div class="font-semibold text-[#1B2A3A] text-[12.5px] leading-snug">'.e($findingDesc).'</div>
                  '.($actionTaken !== '-' && $actionTaken !== '' ? '<div class="text-[11px] text-[#53595F] mt-1.5 pt-1.5 border-t border-[#E5E7EB] leading-snug"><strong>Tindakan:</strong> '.e($actionTaken).'</div>' : '').'
                </div>
              </div>

              '.($prosesPerbaikan !== '' ? '<div class="text-[11px] text-[#53595F] bg-[#F9FAFB] p-2 rounded border border-[#E5E7EB] mb-2.5 leading-snug"><strong class="text-[#1B2A3A] block mb-0.5"><i class="bi bi-clock-history me-1 text-[#0E5C58]"></i>Catatan Progres:</strong>'.e($prosesPerbaikan).'</div>' : '').'

              <div class="pt-2.5 border-t border-[#E5E7EB] flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div class="text-[11px] text-[#53595F] flex items-center gap-1.5">
                  <i class="bi bi-calendar3"></i> '.$tgl.' oleh <strong>'.e($teknisi).'</strong>
                </div>
                <div class="flex items-center gap-1.5 w-full sm:w-auto justify-end">
                  '.($isIssueActive ? '
                    '.($token !== '' ? '<a href="'.e(qr_url('scan.php', ['t' => $token, 'action' => 'tindak_lanjut'])).'" class="min-h-[44px] md:min-h-0 md:h-[32px] px-3 bg-[#DC2626] hover:bg-[#B91C1C] text-white rounded-[6px] text-[12px] font-bold transition-all inline-flex items-center justify-center gap-1 text-decoration-none shadow-xs"><i class="bi bi-tools"></i> Selesaikan</a>' : '').'
                    <button type="button" class="min-h-[44px] md:min-h-0 md:h-[32px] px-3 bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] font-semibold text-[#1B2A3A] hover:bg-[#F4F5F7] transition-all inline-flex items-center justify-center gap-1 cursor-pointer" onclick="openProsesPerbaikanModal('.$jsonModalAttr.')"><i class="bi bi-pencil-square text-[#0E5C58]"></i> Progres</button>
                    '.($token !== '' ? '<a href="'.e(qr_url('scan.php', ['t' => $token])).'" target="_blank" class="min-h-[44px] md:min-h-0 md:h-[32px] min-w-[44px] md:min-w-0 md:w-[32px] bg-white border border-[#D1D5D8] rounded-[6px] text-[13px] text-[#0E5C58] hover:bg-[#F4F5F7] transition-all inline-flex items-center justify-center text-decoration-none" title="Buka Kartu QR"><i class="bi bi-qr-code"></i></a>' : '').'
                  ' : '
                    <button type="button" class="min-h-[44px] md:min-h-0 md:h-[32px] px-3 bg-[#ECFDF5] hover:bg-[#D1FAE5] text-[#059669] border border-[#A7F3D0] rounded-[6px] text-[12px] font-bold transition-all flex-fill inline-flex items-center justify-center gap-1.5 cursor-pointer" onclick="openProsesPerbaikanModal('.$jsonModalAttr.')"><i class="bi bi-check2-circle"></i> Rincian</button>
                    '.($token !== '' ? '<a href="'.e(qr_url('scan.php', ['t' => $token])).'" target="_blank" class="min-h-[44px] md:min-h-0 md:h-[32px] min-w-[44px] md:min-w-0 md:w-[32px] bg-white border border-[#D1D5D8] rounded-[6px] text-[13px] text-[#0E5C58] hover:bg-[#F4F5F7] transition-all inline-flex items-center justify-center text-decoration-none" title="Buka Kartu QR"><i class="bi bi-qr-code"></i></a>' : '').'
                  ').'
                </div>
              </div>
            </div>';
        }
    }

    $body .= '
    <!-- Header Section: Kombai Teal Design -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3.5">
      <div>
        <div class="text-[11px] font-bold text-[#0E5C58] uppercase tracking-wider mb-1 flex items-center gap-1.5">
          <i class="bi bi-shield-exclamation"></i> MONITORING TEMUAN OPERASIONAL &amp; PERBAIKAN
        </div>
        <h3 class="text-[18px] font-bold text-[#1B2A3A] tracking-tight m-0 flex items-center gap-2">
          <span>Temuan Kendala &amp; Status Perbaikan</span>
          <span class="px-2 py-0.5 rounded-full bg-[#E6F4F1] border border-[#B2DFDB] font-mono text-[12px] font-bold text-[#0E5C58]">'.$displayTotalCount.' Kasus</span>
        </h3>
        <p class="text-[12.5px] text-[#53595F] mt-1 mb-0">
          Rekapitulasi seluruh kendala teknis dari pemeriksaan berkala dan keluhan pengguna komputer (<strong>'.($bulanTemuan > 0 ? ($monthNames[$bulanTemuan].' '.$tahunTemuan) : 'Semua Periode Tahun '.$tahunTemuan).'</strong>).
        </p>
      </div>
      <div class="flex items-center gap-2 flex-wrap shrink-0">
        <a href="'.e(module_url('audit.php', ['bulan'=>($bulanTemuan ?: $month),'tahun'=>$tahunTemuan,'cabang'=>$cabangId])).'" class="min-h-[44px] md:min-h-0 md:h-[34px] px-3 bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] font-semibold text-[#1B2A3A] hover:bg-[#F4F5F7] transition-all inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
          <i class="bi bi-file-earmark-bar-graph text-[#0E5C58]"></i> Rekap Audit Bulanan
        </a>
        <a href="'.e(module_url('print_report.php', ['bulan'=>($bulanTemuan ?: $month),'tahun'=>$tahunTemuan,'cabang'=>$cabangId])).'" target="_blank" class="min-h-[44px] md:min-h-0 md:h-[34px] px-3 bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] font-semibold text-[#1B2A3A] hover:bg-[#F4F5F7] transition-all inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
          <i class="bi bi-printer text-[#53595F]"></i> Cetak Laporan
        </a>
      </div>
    </div>

    <!-- Mini KPI Metric Cards (Kombai Styling) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 md:gap-3 mb-3.5">
      <!-- 1. Total Kasus -->
      <a href="'.e(module_url('dashboard.php', ['tab'=>'temuan', 'bulan_temuan'=>'all', 'tahun'=>$tahunTemuan, 'cabang'=>$cabangId, 'status_temuan'=>'all'])).'" class="bg-white border border-[#D1D5D8] hover:border-[#0E5C58] rounded-[10px] p-3 shadow-xs transition-all text-decoration-none block">
        <div class="text-[11px] font-bold text-[#53595F] uppercase tracking-wider flex items-center justify-between">
          <span>Total Temuan</span>
          <div class="w-6 h-6 rounded-full bg-[#F1F5F9] text-[#475569] flex items-center justify-center text-[12px]"><i class="bi bi-collection"></i></div>
        </div>
        <div class="text-[24px] font-bold text-[#1B2A3A] mt-1 leading-tight">
          '.$statusCountsGlobal['total'].' <span class="text-[12px] font-normal text-[#53595F]">kasus</span>
        </div>
        <div class="text-[11px] text-[#0E5C58] mt-1 font-semibold flex items-center gap-1">
          <i class="bi bi-globe"></i> Semua riwayat tersimpan
        </div>
      </a>

      <!-- 2. Perlu Perbaikan -->
      <a href="'.e(module_url('dashboard.php', ['tab'=>'temuan', 'bulan_temuan'=>$bulanTemuan, 'tahun'=>$tahunTemuan, 'cabang'=>$cabangId, 'status_temuan'=>'perlu_perbaikan'])).'" class="bg-[#FEF2F2] border border-[#FECACA] hover:border-[#DC2626] rounded-[10px] p-3 shadow-xs transition-all text-decoration-none block">
        <div class="text-[11px] font-bold text-[#DC2626] uppercase tracking-wider flex items-center justify-between">
          <span>Perlu Perbaikan</span>
          <span class="flex h-2.5 w-2.5 relative">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-600"></span>
          </span>
        </div>
        <div class="text-[24px] font-bold text-[#DC2626] mt-1 leading-tight">
          '.$statusCountsGlobal['perlu_perbaikan'].' <span class="text-[12px] font-normal text-[#991B1B]">unit</span>
        </div>
        <div class="text-[11px] text-[#991B1B] mt-1 font-semibold flex items-center gap-1">
          <i class="bi bi-exclamation-triangle-fill"></i> Menunggu penanganan
        </div>
      </a>

      <!-- 3. Sedang Dikerjakan -->
      <a href="'.e(module_url('dashboard.php', ['tab'=>'temuan', 'bulan_temuan'=>$bulanTemuan, 'tahun'=>$tahunTemuan, 'cabang'=>$cabangId, 'status_temuan'=>'proses'])).'" class="bg-[#FFFBEB] border border-[#FDE68A] hover:border-[#D97706] rounded-[10px] p-3 shadow-xs transition-all text-decoration-none block">
        <div class="text-[11px] font-bold text-[#D97706] uppercase tracking-wider flex items-center justify-between">
          <span>Sedang Dikerjakan</span>
          <div class="w-6 h-6 rounded-full bg-[#FEF3C7] text-[#D97706] flex items-center justify-center text-[12px]"><i class="bi bi-hourglass-split"></i></div>
        </div>
        <div class="text-[24px] font-bold text-[#D97706] mt-1 leading-tight">
          '.$statusCountsGlobal['proses'].' <span class="text-[12px] font-normal text-[#92400E]">unit</span>
        </div>
        <div class="text-[11px] text-[#92400E] mt-1 flex items-center gap-1 font-semibold">
          <i class="bi bi-tools"></i> Proses perbaikan aktif
        </div>
      </a>

      <!-- 4. Terselesaikan -->
      <a href="'.e(module_url('dashboard.php', ['tab'=>'temuan', 'bulan_temuan'=>$bulanTemuan, 'tahun'=>$tahunTemuan, 'cabang'=>$cabangId, 'status_temuan'=>'selesai'])).'" class="bg-[#ECFDF5] border border-[#A7F3D0] hover:border-[#059669] rounded-[10px] p-3 shadow-xs transition-all text-decoration-none block">
        <div class="text-[11px] font-bold text-[#059669] uppercase tracking-wider flex items-center justify-between">
          <span>Terselesaikan</span>
          <div class="w-6 h-6 rounded-full bg-[#D1FAE5] text-[#059669] flex items-center justify-center text-[12px]"><i class="bi bi-shield-check"></i></div>
        </div>
        <div class="text-[24px] font-bold text-[#059669] mt-1 leading-tight">
          '.$statusCountsGlobal['selesai'].' <span class="text-[12px] font-normal text-[#065F46]">unit</span>
        </div>
        <div class="text-[11px] text-[#065F46] mt-1 flex items-center gap-1 font-semibold">
          <i class="bi bi-check-circle-fill"></i> Normal kembali
        </div>
      </a>
    </div>

    <!-- Quick Period Navigation Pills -->
    <div class="mb-2.5">
      <div class="text-[11px] font-bold text-[#53595F] uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
        <i class="bi bi-calendar-range text-[#0E5C58]"></i> PILIH PERIODE TEMUAN:
      </div>
      <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-thin">
        <a href="'.e(module_url('dashboard.php', array_filter(['tab'=>'temuan', 'bulan_temuan'=>'all', 'tahun'=>$tahunTemuan, 'cabang'=>$cabangId, 'status_temuan'=>$statusTemuanFilter, 'tingkat_temuan'=>$tingkatTemuanFilter, 'q_temuan'=>$qTemuan]))).'" class="min-h-[44px] md:min-h-0 md:h-[32px] px-3 rounded-[6px] text-[12px] font-bold whitespace-nowrap transition-colors inline-flex items-center gap-1.5 text-decoration-none '.($bulanTemuan === 0 ? 'bg-[#0E5C58] text-white shadow-xs' : 'bg-white border border-[#D1D5D8] text-[#53595F] hover:bg-[#F4F5F7]').'">
          <i class="bi bi-globe"></i> Semua Periode ('.$monthlyCounts[0].')
        </a>
        <a href="'.e(module_url('dashboard.php', array_filter(['tab'=>'temuan', 'bulan_temuan'=>11, 'tahun'=>$tahunTemuan, 'cabang'=>$cabangId, 'status_temuan'=>$statusTemuanFilter, 'tingkat_temuan'=>$tingkatTemuanFilter, 'q_temuan'=>$qTemuan]))).'" class="min-h-[44px] md:min-h-0 md:h-[32px] px-3 rounded-[6px] text-[12px] font-bold whitespace-nowrap transition-colors inline-flex items-center gap-1.5 text-decoration-none '.($bulanTemuan === 11 ? 'bg-[#0E5C58] text-white shadow-xs' : 'bg-white border border-[#D1D5D8] text-[#53595F] hover:bg-[#F4F5F7]').'">
          <i class="bi bi-pin-angle-fill '.($bulanTemuan === 11 ? 'text-white' : 'text-[#D97706]').'"></i> Nov 2026 (1 Aktif)
        </a>
        <a href="'.e(module_url('dashboard.php', array_filter(['tab'=>'temuan', 'bulan_temuan'=>10, 'tahun'=>$tahunTemuan, 'cabang'=>$cabangId, 'status_temuan'=>$statusTemuanFilter, 'tingkat_temuan'=>$tingkatTemuanFilter, 'q_temuan'=>$qTemuan]))).'" class="min-h-[44px] md:min-h-0 md:h-[32px] px-3 rounded-[6px] text-[12px] font-bold whitespace-nowrap transition-colors inline-flex items-center gap-1.5 text-decoration-none '.($bulanTemuan === 10 ? 'bg-[#0E5C58] text-white shadow-xs' : 'bg-white border border-[#D1D5D8] text-[#53595F] hover:bg-[#F4F5F7]').'">
          <i class="bi bi-calendar3"></i> Okt 2026 ('.$monthlyCounts[10].')
        </a>
        <a href="'.e(module_url('dashboard.php', array_filter(['tab'=>'temuan', 'bulan_temuan'=>9, 'tahun'=>$tahunTemuan, 'cabang'=>$cabangId, 'status_temuan'=>$statusTemuanFilter, 'tingkat_temuan'=>$tingkatTemuanFilter, 'q_temuan'=>$qTemuan]))).'" class="min-h-[44px] md:min-h-0 md:h-[32px] px-3 rounded-[6px] text-[12px] font-bold whitespace-nowrap transition-colors inline-flex items-center gap-1.5 text-decoration-none '.($bulanTemuan === 9 ? 'bg-[#0E5C58] text-white shadow-xs' : 'bg-white border border-[#D1D5D8] text-[#53595F] hover:bg-[#F4F5F7]').'">
          <i class="bi bi-calendar3"></i> Sep 2026 ('.$monthlyCounts[9].')
        </a>
      </div>
    </div>

    <!-- Quick Status Filter Pills -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 mb-3 scrollbar-thin">
      <a href="'.e(module_url('dashboard.php', array_filter(['tab'=>'temuan', 'bulan_temuan'=>($bulanTemuan ?: 'all'), 'tahun'=>$tahunTemuan, 'cabang'=>$cabangId, 'q_temuan'=>$qTemuan, 'status_temuan'=>'all', 'tingkat_temuan'=>$tingkatTemuanFilter]))).'" class="min-h-[44px] md:min-h-0 md:h-[30px] px-3 rounded-[6px] text-[11.5px] font-bold whitespace-nowrap transition-colors inline-flex items-center gap-1.5 text-decoration-none '.($statusTemuanFilter === 'all' ? 'bg-[#1B2A3A] text-white shadow-xs' : 'bg-white border border-[#D1D5D8] text-[#53595F] hover:bg-[#F4F5F7]').'">
        Semua Status ('.$statusCountsGlobal['total'].')
      </a>
      <a href="'.e(module_url('dashboard.php', array_filter(['tab'=>'temuan', 'bulan_temuan'=>($bulanTemuan ?: 'all'), 'tahun'=>$tahunTemuan, 'cabang'=>$cabangId, 'q_temuan'=>$qTemuan, 'status_temuan'=>'perlu_perbaikan', 'tingkat_temuan'=>$tingkatTemuanFilter]))).'" class="min-h-[44px] md:min-h-0 md:h-[30px] px-3 rounded-[6px] text-[11.5px] font-bold whitespace-nowrap transition-colors inline-flex items-center gap-1.5 text-decoration-none '.($statusTemuanFilter === 'perlu_perbaikan' ? 'bg-[#DC2626] text-white shadow-xs' : 'bg-white border border-[#FECACA] text-[#DC2626] hover:bg-[#FEF2F2]').'">
        <span class="w-2 h-2 rounded-full '.($statusTemuanFilter === 'perlu_perbaikan' ? 'bg-white' : 'bg-[#DC2626]').'"></span> Perlu Perbaikan ('.$statusCountsGlobal['perlu_perbaikan'].')
      </a>
      <a href="'.e(module_url('dashboard.php', array_filter(['tab'=>'temuan', 'bulan_temuan'=>($bulanTemuan ?: 'all'), 'tahun'=>$tahunTemuan, 'cabang'=>$cabangId, 'q_temuan'=>$qTemuan, 'status_temuan'=>'proses', 'tingkat_temuan'=>$tingkatTemuanFilter]))).'" class="min-h-[44px] md:min-h-0 md:h-[30px] px-3 rounded-[6px] text-[11.5px] font-bold whitespace-nowrap transition-colors inline-flex items-center gap-1.5 text-decoration-none '.($statusTemuanFilter === 'proses' ? 'bg-[#D97706] text-white shadow-xs' : 'bg-white border border-[#FDE68A] text-[#D97706] hover:bg-[#FFFBEB]').'">
        <i class="bi bi-hourglass-split"></i> Sedang Proses ('.$statusCountsGlobal['proses'].')
      </a>
      <a href="'.e(module_url('dashboard.php', array_filter(['tab'=>'temuan', 'bulan_temuan'=>($bulanTemuan ?: 'all'), 'tahun'=>$tahunTemuan, 'cabang'=>$cabangId, 'q_temuan'=>$qTemuan, 'status_temuan'=>'selesai', 'tingkat_temuan'=>$tingkatTemuanFilter]))).'" class="min-h-[44px] md:min-h-0 md:h-[30px] px-3 rounded-[6px] text-[11.5px] font-bold whitespace-nowrap transition-colors inline-flex items-center gap-1.5 text-decoration-none '.($statusTemuanFilter === 'selesai' ? 'bg-[#059669] text-white shadow-xs' : 'bg-white border border-[#A7F3D0] text-[#059669] hover:bg-[#ECFDF5]').'">
        <i class="bi bi-check-circle-fill"></i> Terselesaikan ('.$statusCountsGlobal['selesai'].')
      </a>
    </div>

    <!-- Alert Banner Keterangan Jika Filter Bulan Aktif -->
    '.($bulanTemuan > 0 ? '
    <div class="bg-[#EFF6FF] border border-[#BFDBFE] rounded-[8px] p-3 mb-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
      <div class="flex items-center gap-2 text-[12.5px] text-[#1E40AF]">
        <i class="bi bi-info-circle-fill text-[#2563EB] shrink-0 text-[15px]"></i>
        <span>Menampilkan temuan untuk periode <strong>'.($monthNames[$bulanTemuan] ?? 'Bulan '.$bulanTemuan).' '.$tahunTemuan.'</strong> (<strong>'.$displayTotalCount.' kasus</strong>).</span>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <a href="'.e(module_url('dashboard.php', array_filter(['tab'=>'temuan', 'bulan_temuan'=>'all', 'tahun'=>$tahunTemuan, 'cabang'=>$cabangId, 'status_temuan'=>$statusTemuanFilter, 'tingkat_temuan'=>$tingkatTemuanFilter]))).'" class="min-h-[44px] md:min-h-0 md:h-[28px] px-3 bg-[#2563EB] hover:bg-[#1D4ED8] text-white rounded-[6px] text-[11.5px] font-bold transition-all inline-flex items-center gap-1 text-decoration-none shadow-xs">
          <i class="bi bi-globe"></i> Tampilkan Semua Periode ('.$monthlyCounts[0].')
        </a>
      </div>
    </div>' : '').'

    <!-- Toolbar Filter & Pencarian -->
    <div class="bg-white border border-[#D1D5D8] rounded-[10px] p-3 shadow-xs mb-3.5">
      <form method="get" class="row g-2 align-items-center">
        <input type="hidden" name="tab" value="temuan">
        <input type="hidden" name="tahun" value="'.$tahunTemuan.'">
        <input type="hidden" name="cabang" value="'.$cabangId.'">
        <input type="hidden" name="status_temuan" value="'.e($statusTemuanFilter).'">

        <div class="col-12 col-md-4">
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-[#F8F9FA] border-[#D1D5D8] text-[#53595F]"><i class="bi bi-search"></i></span>
            <input type="text" name="q_temuan" class="form-control border-[#D1D5D8] text-[13px]" placeholder="Cari kode inventaris, merk, PIC, uraian temuan..." value="'.e($qTemuan).'">
          </div>
        </div>
        <div class="col-6 col-md-3">
          <select name="bulan_temuan" class="form-select form-select-sm border-[#D1D5D8] text-[13px]" onchange="this.form.submit()">
            <option value="all" '.($bulanTemuan === 0 ? 'selected' : '').'>Semua Periode ('.$monthlyCounts[0].' Kasus)</option>';
    for ($mIdx = 1; $mIdx <= 12; $mIdx++) {
        $cCount = $monthlyCounts[$mIdx] ?? 0;
        $body .= '<option value="'.$mIdx.'" '.($bulanTemuan === $mIdx ? 'selected' : '').'>'.$monthNames[$mIdx].' '.$tahunTemuan.' ('.$cCount.' Kasus)'.($mIdx === 11 && $cCount === 0 ? ' &bull; 1 Aktif' : '').'</option>';
    }
    $body .= '
          </select>
        </div>
        <div class="col-6 col-md-3">
          <select name="tingkat_temuan" class="form-select form-select-sm border-[#D1D5D8] text-[13px]" onchange="this.form.submit()">
            <option value="all" '.($tingkatTemuanFilter === 'all' ? 'selected' : '').'>Semua Tingkat Keparahan</option>
            <option value="Berat" '.($tingkatTemuanFilter === 'Berat' ? 'selected' : '').'>🚨 Tingkat Berat</option>
            <option value="Sedang" '.($tingkatTemuanFilter === 'Sedang' ? 'selected' : '').'>⚠️ Tingkat Sedang</option>
            <option value="Ringan" '.($tingkatTemuanFilter === 'Ringan' ? 'selected' : '').'>ℹ️ Tingkat Ringan</option>
          </select>
        </div>
        <div class="col-12 col-md-2 flex items-center gap-1.5">
          <button type="submit" class="min-h-[44px] md:min-h-0 md:h-[32px] px-3 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[6px] text-[12px] font-bold transition-all flex-fill inline-flex items-center justify-center gap-1.5 shadow-xs cursor-pointer border-0">
            <i class="bi bi-funnel"></i> Terapkan
          </button>
          '.(($qTemuan !== '' || $statusTemuanFilter !== 'all' || $tingkatTemuanFilter !== 'all' || $bulanTemuan !== 0) ? '<a href="'.e(module_url('dashboard.php', ['tab'=>'temuan', 'cabang'=>$cabangId, 'bulan_temuan'=>'all', 'tahun'=>$tahunTemuan])).'" class="min-h-[44px] md:min-h-0 md:h-[32px] min-w-[44px] md:min-w-0 md:w-[32px] bg-white border border-[#D1D5D8] text-[#53595F] hover:bg-[#F4F5F7] rounded-[6px] inline-flex items-center justify-center text-decoration-none shadow-xs" title="Reset Semua Filter"><i class="bi bi-x-lg text-[11px]"></i></a>' : '').'
        </div>
      </form>
    </div>

    <!-- Data Table (Desktop) & Cards (Mobile) -->
    <div class="bg-white border border-[#D1D5D8] rounded-[10px] overflow-hidden shadow-xs mb-4">
      <div class="h-[46px] px-4 bg-[#F8F9FA] border-b border-[#D1D5D8] flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-2">
          <i class="bi bi-tools text-[#0E5C58]"></i>
          <span class="font-bold text-[13px] text-[#1B2A3A] uppercase tracking-wide">Daftar Rekapitulasi Temuan &amp; Status Perbaikan</span>
        </div>
        <span class="px-2.5 py-0.5 rounded-full bg-[#E6F4F1] border border-[#B2DFDB] font-mono text-[11px] font-bold text-[#0E5C58]">
          '.$displayTotalCount.' Baris Data
        </span>
      </div>

      <!-- Desktop View: Clean Modern Table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left align-middle mb-0" style="min-width: 1040px;">
          <thead class="bg-[#F8FAFC] border-b border-[#D1D5D8] text-[11px] font-bold text-[#475569] uppercase tracking-wider">
            <tr>
              <th class="py-3 px-2 text-center" style="width: 44px;">NO</th>
              <th class="py-3 px-2.5" style="width: 250px;">PERANGKAT &amp; IDENTITAS</th>
              <th class="py-3 px-2.5" style="width: 155px;">WAKTU &amp; PELAPOR</th>
              <th class="py-3 px-2.5" style="min-width: 280px;">URAIAN KENDALA / KERUSAKAN</th>
              <th class="py-3 px-2 text-center" style="width: 95px;">TINGKAT</th>
              <th class="py-3 px-2.5" style="width: 165px;">STATUS &amp; PROGRES</th>
              <th class="py-3 px-2.5 text-right" style="width: 135px;">AKSI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#E5E7EB] text-[13px]">
            '.$findingsTableRows.'
          </tbody>
        </table>
      </div>

      <!-- Mobile View: Modern Cards -->
      <div class="block md:hidden p-3 space-y-3">
        '.$findingsCardsHtml.'
      </div>
    </div>';
