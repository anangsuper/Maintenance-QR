<?php
    // --- TAMPILAN TAB TEMUAN KENDALA & STATUS PERBAIKAN (KOMBAI TEAL DESIGN SYSTEM) ---
    $statusTemuanFilter = trim((string)($_GET['status_temuan'] ?? 'all'));
    $tingkatTemuanFilter = trim((string)($_GET['tingkat_temuan'] ?? 'all'));
    $qTemuan = trim((string)($_GET['q_temuan'] ?? ''));
    $bulanTemuan = isset($_GET['bulan_temuan']) ? (int)$_GET['bulan_temuan'] : $month;
    $tahunTemuan = isset($_GET['tahun_temuan']) ? (int)$_GET['tahun_temuan'] : $year;

    // Ambil data temuan dari tabel/sheet Maintenance_Findings & Maintenance_Scan
    $allFindingsRaw = get_findings_report($bulanTemuan, $tahunTemuan, $cabangId);

    // KONSOLIDASI DENGAN ACTIVE FINDINGS DARI DASHBOARD
    // Menjamin bahwa temuan kendala aktif yang dihitung pada kartu KPI Dashboard pasti tampil di sini
    $existingAssetIds = [];
    foreach ($allFindingsRaw as $item) {
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

            $allFindingsRaw[] = [
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

    // Hitung statistik
    $countTotalTemuan = count($allFindingsRaw);
    $countPerluPerbaikan = 0;
    $countInProgress = 0;
    $countResolved = 0;

    foreach ($allFindingsRaw as $f) {
        $st = strtolower(trim((string)($f['repair_status'] ?? '')));
        if (in_array($st, ['resolved', 'selesai', 'closed', 'ok'], true)) {
            $countResolved++;
        } elseif (in_array($st, ['in progress', 'proses', 'sedang perbaikan', 'dalam proses'], true)) {
            $countInProgress++;
        } else {
            $countPerluPerbaikan++;
        }
    }

    // Filter daftar temuan sesuai pencarian dan filter
    $filteredFindings = array_filter($allFindingsRaw, function($f) use ($statusTemuanFilter, $tingkatTemuanFilter, $qTemuan) {
        $st = strtolower(trim((string)($f['repair_status'] ?? '')));
        if ($statusTemuanFilter === 'perlu_perbaikan') {
            if (in_array($st, ['resolved', 'selesai', 'closed', 'ok', 'in progress', 'proses', 'sedang perbaikan', 'dalam proses'], true)) return false;
        } elseif ($statusTemuanFilter === 'proses') {
            if (!in_array($st, ['in progress', 'proses', 'sedang perbaikan', 'dalam proses'], true)) return false;
        } elseif ($statusTemuanFilter === 'selesai') {
            if (!in_array($st, ['resolved', 'selesai', 'closed', 'ok'], true)) return false;
        }

        if ($tingkatTemuanFilter !== 'all' && $tingkatTemuanFilter !== '') {
            if (strcasecmp(trim((string)($f['severity'] ?? '')), $tingkatTemuanFilter) !== 0) return false;
        }

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
    });

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
            <div class="text-[13px] text-[#53595F] mt-1 max-w-[420px] mx-auto">Seluruh perangkat beroperasi normal atau silakan sesuaikan filter bulan, status, dan tingkat keparahan.</div>
          </td>
        </tr>';
        $findingsCardsHtml = '
        <div class="text-center py-10 px-4 bg-white rounded-[8px] border border-[#D1D5D8] text-[#53595F]">
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
            $devName = $f['merk_model'] ?? 'Perangkat IT';
            $pic = $f['karyawan_nama'] ?? '-';
            $cabang = $f['cabang_nama'] ?? '-';
            $divisi = $f['divisi_nama'] ?? '-';
            $findingDesc = $f['finding'] ?? 'Kendala perangkat dilaporkan';
            $actionTaken = $f['action_taken'] ?? '-';
            $prosesPerbaikan = $f['proses_perbaikan'] ?? $f['catatan_penyelesaian'] ?? '';
            $severity = $f['severity'] ?? 'Sedang';
            $repairStatus = $f['repair_status'] ?? 'Perlu Perbaikan';
            $teknisi = $f['teknisi'] ?? $f['reported_by'] ?? 'Teknisi';
            $rawDate = !empty($f['created_at']) ? $f['created_at'] : (!empty($f['reported_at']) ? $f['reported_at'] : '');
            $tgl = $rawDate !== '' ? format_id_date($rawDate) : '-';

            $lowStatus = strtolower($repairStatus);
            if (in_array($lowStatus, ['resolved', 'selesai', 'closed', 'ok'], true)) {
                $statusBadge = '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-[#ECFDF5] text-[#059669] border border-[#A7F3D0]"><i class="bi bi-check-circle-fill"></i> Selesai</span>';
                $cardBorderColor = '#10B981';
                $rowStripe = '';
                $isIssueActive = false;
            } elseif (in_array($lowStatus, ['in progress', 'proses', 'sedang perbaikan', 'dalam proses'], true)) {
                $statusBadge = '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-[#FFFBEB] text-[#D97706] border border-[#FDE68A]"><i class="bi bi-hourglass-split"></i> Sedang Proses</span>';
                $cardBorderColor = '#F59E0B';
                $rowStripe = 'border-l-4 border-l-[#F59E0B]';
                $isIssueActive = true;
            } else {
                $statusBadge = '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-[#FEF2F2] text-[#DC2626] border border-[#FECACA]"><span class="w-2 h-2 rounded-full bg-[#DC2626] animate-pulse"></span> Perlu Perbaikan</span>';
                $cardBorderColor = '#EF4444';
                $rowStripe = 'border-l-4 border-l-[#DC2626]';
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

            // Action Buttons
            $actionsDesktop = '';
            if ($isIssueActive) {
                if ($token !== '') {
                    $actionsDesktop .= '<a href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'tindak_lanjut'])).'" class="h-[30px] px-2.5 bg-[#DC2626] hover:bg-[#B91C1C] text-white rounded-[6px] text-[11px] font-bold transition-all inline-flex items-center gap-1 text-decoration-none shadow-xs" title="Buka Form Tindak Lanjut"><i class="bi bi-tools"></i> Selesaikan</a>';
                }
                $actionsDesktop .= '<button type="button" class="h-[30px] w-[30px] bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] text-[#53595F] hover:bg-[#F4F5F7] transition-all inline-flex items-center justify-center cursor-pointer" onclick="openProsesPerbaikanModal('.$jsonModalAttr.')" title="Catat / Update Catatan Progres"><i class="bi bi-pencil-square"></i></button>';
                if ($token !== '') {
                    $actionsDesktop .= '<a href="'.e(module_url('scan.php', ['t' => $token])).'" target="_blank" class="h-[30px] w-[30px] bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] text-[#0E5C58] hover:bg-[#F4F5F7] transition-all inline-flex items-center justify-center text-decoration-none" title="Buka Kartu QR Komputer"><i class="bi bi-qr-code"></i></a>';
                }
            } else {
                $actionsDesktop .= '<button type="button" class="h-[30px] px-2.5 bg-[#ECFDF5] hover:bg-[#D1FAE5] text-[#059669] border border-[#A7F3D0] rounded-[6px] text-[11px] font-bold transition-all inline-flex items-center gap-1 cursor-pointer" onclick="openProsesPerbaikanModal('.$jsonModalAttr.')"><i class="bi bi-check2-circle"></i> Rincian</button>';
                if ($scanId > 0) {
                    $actionsDesktop .= '<a href="'.e(module_url('maintenance_detail.php', ['id' => $scanId])).'" class="h-[30px] px-2 bg-white border border-[#D1D5D8] rounded-[6px] text-[11px] text-[#53595F] hover:bg-[#F4F5F7] transition-all inline-flex items-center gap-0.5 text-decoration-none" title="Hasil Audit"><i class="bi bi-file-earmark-medical"></i> #'.$scanId.'</a>';
                }
                if ($token !== '') {
                    $actionsDesktop .= '<a href="'.e(module_url('scan.php', ['t' => $token])).'" target="_blank" class="h-[30px] w-[30px] bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] text-[#0E5C58] hover:bg-[#F4F5F7] transition-all inline-flex items-center justify-center text-decoration-none" title="Buka Kartu QR Komputer"><i class="bi bi-qr-code"></i></a>';
                }
            }

            // Box Uraian Kendala
            if ($isIssueActive) {
                $findingBoxHtml = '
                <div class="bg-[#FEF2F2] border border-[#FECACA] rounded-[6px] p-2 text-start">
                  <div class="font-bold text-[#DC2626] text-[12px] flex items-start gap-1.5 leading-snug">
                    <i class="bi bi-exclamation-triangle-fill text-[#DC2626] shrink-0 mt-0.5"></i>
                    <span>'.e($findingDesc).'</span>
                  </div>
                  '.($actionTaken !== '-' && $actionTaken !== '' ? '<div class="text-[11px] text-[#53595F] mt-1.5 pt-1.5 border-t border-[#FEE2E2] leading-snug"><span class="font-bold text-[#1B2A3A]">Catatan/Rekomendasi:</span> '.e($actionTaken).'</div>' : '').'
                </div>';
            } else {
                $findingBoxHtml = '
                <div class="bg-[#F9FAFB] border border-[#E5E7EB] rounded-[6px] p-2 text-start">
                  <div class="font-semibold text-[#1B2A3A] text-[12px] flex items-start gap-1.5 leading-snug">
                    <i class="bi bi-check-circle-fill text-[#10B981] shrink-0 mt-0.5"></i>
                    <span>'.e($findingDesc).'</span>
                  </div>
                  '.($actionTaken !== '-' && $actionTaken !== '' ? '<div class="text-[11px] text-[#53595F] mt-1.5 pt-1.5 border-t border-[#E5E7EB] leading-snug"><span class="font-semibold text-[#53595F]">Tindakan:</span> '.e($actionTaken).'</div>' : '').'
                </div>';
            }

            $findingsTableRows .= '
            <tr class="border-b border-[#E5E7EB] hover:bg-[#F9FAFB] transition-colors '.$rowStripe.'">
              <td class="text-center font-mono font-bold text-[#53595F] text-[12px]" style="width: 40px;">'.$noF.'</td>
              <td style="min-width: 180px; max-width: 240px;">
                <div class="font-mono font-bold text-[#0E5C58] text-[12px] flex items-center gap-1.5">
                  '.e($kode).'
                </div>
                <div class="font-bold text-[#1B2A3A] text-[13px] truncate mt-0.5" title="'.e($devName).'">'.e($devName).'</div>
                <div class="text-[11px] text-[#53595F] truncate mt-0.5 flex items-center gap-1">
                  <i class="bi bi-person text-[#0E5C58]"></i> <span class="truncate">'.e($pic).'</span> &middot; <span class="px-1.5 py-0.2 rounded bg-[#F4F5F7] border border-[#E5E7EB] text-[10px] text-[#53595F] shrink-0">'.e($cabang).'</span>
                </div>
              </td>
              <td style="min-width: 140px;">
                <div class="font-semibold text-[#1B2A3A] text-[12px] flex items-center gap-1.5">
                  <i class="bi bi-calendar3 text-[#53595F]"></i> '.$tgl.'
                </div>
                <div class="text-[11px] text-[#53595F] mt-0.5 flex items-center gap-1">
                  <i class="bi bi-person-badge text-[#0E5C58]"></i> '.e($teknisi).'
                </div>
              </td>
              <td style="min-width: 260px;">
                '.$findingBoxHtml.'
              </td>
              <td class="text-center" style="width: 90px;">
                '.$sevBadge.'
              </td>
              <td style="min-width: 170px;">
                <div>'.$statusBadge.'</div>
                '.($prosesPerbaikan !== '' ? '<div class="text-[11px] text-[#53595F] bg-white p-1.5 rounded border border-[#E5E7EB] mt-1.5 leading-snug"><i class="bi bi-clock-history me-1 text-[#0E5C58]"></i>'.e($prosesPerbaikan).'</div>' : '<div class="text-[11px] text-[#9CA3AF] italic mt-1">Belum ada catatan progres</div>').'
              </td>
              <td class="text-end" style="width: 150px;">
                <div class="flex items-center justify-end gap-1">
                  '.$actionsDesktop.'
                </div>
              </td>
            </tr>';

            // Mobile Card
            $findingsCardsHtml .= '
            <div class="bg-white rounded-[8px] border border-[#D1D5D8] p-3.5 shadow-xs relative" style="border-left: 4px solid '.$cardBorderColor.' !important;">
              <div class="flex items-center justify-between gap-2 mb-2">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span class="px-2 py-0.5 rounded bg-[#F4F5F7] border border-[#E5E7EB] font-mono font-bold text-[11px] text-[#0E5C58]">'.e($kode).'</span>
                  <span class="px-1.5 py-0.5 rounded bg-[#F4F5F7] border border-[#E5E7EB] text-[10px] text-[#53595F]">'.e($cabang).'</span>
                  '.$sevBadge.'
                </div>
                <div>'.$statusBadge.'</div>
              </div>

              <div class="font-bold text-[#1B2A3A] text-[14px] leading-snug">'.e($devName).'</div>
              <div class="text-[12px] text-[#53595F] mt-0.5 mb-2.5 flex items-center gap-1.5">
                <i class="bi bi-person text-[#0E5C58]"></i> <span>PIC: <strong>'.e($pic).'</strong></span> &middot; <span>Divisi: '.e($divisi).'</span>
              </div>

              <div class="mb-2.5">
                '.$findingBoxHtml.'
              </div>

              '.($prosesPerbaikan !== '' ? '<div class="text-[11px] text-[#53595F] bg-[#F9FAFB] p-2 rounded border border-[#E5E7EB] mb-2.5 leading-snug"><strong class="text-[#1B2A3A] block mb-0.5"><i class="bi bi-clock-history me-1 text-[#0E5C58]"></i>Catatan Progres Terakhir:</strong>'.e($prosesPerbaikan).'</div>' : '').'

              <div class="pt-2 border-t border-[#E5E7EB] flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div class="text-[11px] text-[#53595F] flex items-center gap-1.5">
                  <i class="bi bi-calendar3"></i> '.$tgl.' oleh <strong>'.e($teknisi).'</strong>
                </div>
                <div class="flex items-center gap-1.5 w-full sm:w-auto justify-end">
                  '.($isIssueActive ? '
                    '.($token !== '' ? '<a href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'tindak_lanjut'])).'" class="h-[34px] px-3 bg-[#DC2626] hover:bg-[#B91C1C] text-white rounded-[6px] text-[12px] font-bold transition-all flex-fill sm:flex-initial inline-flex items-center justify-center gap-1.5 text-decoration-none shadow-xs"><i class="bi bi-tools"></i> Selesaikan</a>' : '').'
                    <button type="button" class="h-[34px] px-3 bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] font-semibold text-[#1B2A3A] hover:bg-[#F4F5F7] transition-all inline-flex items-center justify-center gap-1 cursor-pointer" onclick="openProsesPerbaikanModal('.$jsonModalAttr.')"><i class="bi bi-pencil-square"></i> Progres</button>
                    '.($token !== '' ? '<a href="'.e(module_url('scan.php', ['t' => $token])).'" target="_blank" class="h-[34px] w-[34px] bg-white border border-[#D1D5D8] rounded-[6px] text-[13px] text-[#0E5C58] hover:bg-[#F4F5F7] transition-all inline-flex items-center justify-center text-decoration-none" title="Buka Kartu QR"><i class="bi bi-qr-code"></i></a>' : '').'
                  ' : '
                    <button type="button" class="h-[34px] px-3 bg-[#ECFDF5] hover:bg-[#D1FAE5] text-[#059669] border border-[#A7F3D0] rounded-[6px] text-[12px] font-bold transition-all flex-fill inline-flex items-center justify-center gap-1.5 cursor-pointer" onclick="openProsesPerbaikanModal('.$jsonModalAttr.')"><i class="bi bi-check2-circle"></i> Lihat Rincian Penyelesaian</button>
                    '.($scanId > 0 ? '<a href="'.e(module_url('maintenance_detail.php', ['id' => $scanId])).'" class="h-[34px] px-2.5 bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] text-[#53595F] hover:bg-[#F4F5F7] transition-all inline-flex items-center gap-1 text-decoration-none" title="Audit Log"><i class="bi bi-file-earmark-medical"></i> #'.$scanId.'</a>' : '').'
                  ').'
                </div>
              </div>
            </div>';
        }
    }

    $body .= '
    <!-- Action Bar & Kicker Tab Temuan -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3.5">
      <div>
        <h3 class="text-[16px] font-bold text-[#1B2A3A] flex items-center gap-2 mb-0.5">
          <i class="bi bi-tools text-[#DC2626]"></i> Monitoring Temuan &amp; Status Perbaikan
        </h3>
        <div class="text-[12px] text-[#53595F]">
          Rekapitulasi seluruh kendala teknis dari pemeriksaan berkala dan keluhan pengguna komputer (<strong>'.($bulanTemuan > 0 ? ($monthNames[$bulanTemuan].' '.$year) : 'Semua Periode').'</strong>).
        </div>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <a href="'.e(module_url('audit.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'" class="h-[34px] px-3 bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] font-semibold text-[#1B2A3A] hover:bg-[#F4F5F7] transition-all inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
          <i class="bi bi-file-earmark-bar-graph text-[#0E5C58]"></i> Rekap Audit Bulanan
        </a>
        <a href="'.e(module_url('print_report.php', ['bulan'=>($bulanTemuan ?: $month),'tahun'=>$year,'cabang'=>$cabangId])).'" target="_blank" class="h-[34px] px-3 bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] font-semibold text-[#1B2A3A] hover:bg-[#F4F5F7] transition-all inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
          <i class="bi bi-printer text-[#53595F]"></i> Cetak Laporan
        </a>
      </div>
    </div>

    <!-- Mini KPI Metric Cards (Kombai Styling) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 md:gap-3 mb-3.5">
      <!-- 1. Total Kasus -->
      <div class="bg-white border border-[#D1D5D8] rounded-[8px] p-3 shadow-xs">
        <div class="text-[12px] font-semibold text-[#53595F] flex items-center justify-between">
          <span>Total Temuan</span>
          <i class="bi bi-collection text-[#53595F]"></i>
        </div>
        <div class="text-[22px] font-bold text-[#1B2A3A] mt-1">
          '.$countTotalTemuan.' <span class="text-[12px] font-normal text-[#53595F]">kasus</span>
        </div>
        <div class="text-[11px] text-[#53595F] mt-1 flex items-center gap-1">
          <i class="bi bi-calendar3"></i> '.($bulanTemuan > 0 ? $monthNames[$bulanTemuan] : 'Semua Periode').'
        </div>
      </div>

      <!-- 2. Perlu Perbaikan -->
      <div class="bg-[#FEF2F2] border border-[#FECACA] rounded-[8px] p-3 shadow-xs">
        <div class="text-[12px] font-bold text-[#DC2626] flex items-center justify-between">
          <span>Perlu Perbaikan</span>
          <span class="flex h-2 w-2 relative">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-red-600"></span>
          </span>
        </div>
        <div class="text-[22px] font-bold text-[#DC2626] mt-1">
          '.$countPerluPerbaikan.' <span class="text-[12px] font-normal text-[#991B1B]">unit</span>
        </div>
        <div class="text-[11px] text-[#991B1B] mt-1 font-semibold flex items-center gap-1">
          <i class="bi bi-exclamation-triangle-fill"></i> Menunggu penanganan
        </div>
      </div>

      <!-- 3. Sedang Dikerjakan -->
      <div class="bg-[#FFFBEB] border border-[#FDE68A] rounded-[8px] p-3 shadow-xs">
        <div class="text-[12px] font-bold text-[#D97706] flex items-center justify-between">
          <span>Sedang Dikerjakan</span>
          <i class="bi bi-hourglass-split text-[#D97706]"></i>
        </div>
        <div class="text-[22px] font-bold text-[#D97706] mt-1">
          '.$countInProgress.' <span class="text-[12px] font-normal text-[#92400E]">unit</span>
        </div>
        <div class="text-[11px] text-[#92400E] mt-1 flex items-center gap-1 font-medium">
          <i class="bi bi-tools"></i> Proses perbaikan
        </div>
      </div>

      <!-- 4. Terselesaikan -->
      <div class="bg-[#ECFDF5] border border-[#A7F3D0] rounded-[8px] p-3 shadow-xs">
        <div class="text-[12px] font-bold text-[#059669] flex items-center justify-between">
          <span>Terselesaikan</span>
          <i class="bi bi-check-circle-fill text-[#059669]"></i>
        </div>
        <div class="text-[22px] font-bold text-[#059669] mt-1">
          '.$countResolved.' <span class="text-[12px] font-normal text-[#065F46]">unit</span>
        </div>
        <div class="text-[11px] text-[#065F46] mt-1 flex items-center gap-1 font-medium">
          <i class="bi bi-shield-check"></i> Normal kembali
        </div>
      </div>
    </div>

    <!-- Quick Status Filter Pills -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 mb-3 scrollbar-thin">
      <a href="'.e(module_url('dashboard.php', array_filter(['tab'=>'temuan', 'bulan_temuan'=>$bulanTemuan, 'tahun'=>$year, 'cabang'=>$cabangId, 'q_temuan'=>$qTemuan, 'status_temuan'=>'all', 'tingkat_temuan'=>$tingkatTemuanFilter]))).'" class="h-[32px] px-3 rounded-[6px] text-[12px] font-bold whitespace-nowrap transition-colors inline-flex items-center gap-1.5 text-decoration-none '.($statusTemuanFilter === 'all' ? 'bg-[#0E5C58] text-white shadow-xs' : 'bg-white border border-[#D1D5D8] text-[#53595F] hover:bg-[#F4F5F7]').'">
        Semua Status ('.$countTotalTemuan.')
      </a>
      <a href="'.e(module_url('dashboard.php', array_filter(['tab'=>'temuan', 'bulan_temuan'=>$bulanTemuan, 'tahun'=>$year, 'cabang'=>$cabangId, 'q_temuan'=>$qTemuan, 'status_temuan'=>'perlu_perbaikan', 'tingkat_temuan'=>$tingkatTemuanFilter]))).'" class="h-[32px] px-3 rounded-[6px] text-[12px] font-bold whitespace-nowrap transition-colors inline-flex items-center gap-1.5 text-decoration-none '.($statusTemuanFilter === 'perlu_perbaikan' ? 'bg-[#DC2626] text-white shadow-xs' : 'bg-white border border-[#FECACA] text-[#DC2626] hover:bg-[#FEF2F2]').'">
        <span class="w-2 h-2 rounded-full '.($statusTemuanFilter === 'perlu_perbaikan' ? 'bg-white' : 'bg-[#DC2626]').'"></span> Perlu Perbaikan ('.$countPerluPerbaikan.')
      </a>
      <a href="'.e(module_url('dashboard.php', array_filter(['tab'=>'temuan', 'bulan_temuan'=>$bulanTemuan, 'tahun'=>$year, 'cabang'=>$cabangId, 'q_temuan'=>$qTemuan, 'status_temuan'=>'proses', 'tingkat_temuan'=>$tingkatTemuanFilter]))).'" class="h-[32px] px-3 rounded-[6px] text-[12px] font-bold whitespace-nowrap transition-colors inline-flex items-center gap-1.5 text-decoration-none '.($statusTemuanFilter === 'proses' ? 'bg-[#D97706] text-white shadow-xs' : 'bg-white border border-[#FDE68A] text-[#D97706] hover:bg-[#FFFBEB]').'">
        <i class="bi bi-hourglass-split"></i> Sedang Proses ('.$countInProgress.')
      </a>
      <a href="'.e(module_url('dashboard.php', array_filter(['tab'=>'temuan', 'bulan_temuan'=>$bulanTemuan, 'tahun'=>$year, 'cabang'=>$cabangId, 'q_temuan'=>$qTemuan, 'status_temuan'=>'selesai', 'tingkat_temuan'=>$tingkatTemuanFilter]))).'" class="h-[32px] px-3 rounded-[6px] text-[12px] font-bold whitespace-nowrap transition-colors inline-flex items-center gap-1.5 text-decoration-none '.($statusTemuanFilter === 'selesai' ? 'bg-[#059669] text-white shadow-xs' : 'bg-white border border-[#A7F3D0] text-[#059669] hover:bg-[#ECFDF5]').'">
        <i class="bi bi-check-circle-fill"></i> Terselesaikan ('.$countResolved.')
      </a>
    </div>

    <!-- Toolbar Filter & Pencarian -->
    <div class="bg-white border border-[#D1D5D8] rounded-[8px] p-3 shadow-xs mb-3.5">
      <form method="get" class="row g-2 align-items-center">
        <input type="hidden" name="tab" value="temuan">
        <input type="hidden" name="tahun" value="'.$year.'">
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
            <option value="0" '.($bulanTemuan === 0 ? 'selected' : '').'>Semua Periode (Bulan)</option>';
    for ($bm = 1; $bm <= 12; $bm++) {
        $body .= '<option value="'.$bm.'" '.($bulanTemuan === $bm ? 'selected' : '').'>'.$monthNames[$bm].' '.$year.'</option>';
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
          <button type="submit" class="h-[32px] px-3 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[6px] text-[12px] font-bold transition-all flex-fill inline-flex items-center justify-center gap-1.5 shadow-xs cursor-pointer border-0">
            <i class="bi bi-funnel"></i> Terapkan
          </button>
          '.(($qTemuan !== '' || $statusTemuanFilter !== 'all' || $tingkatTemuanFilter !== 'all' || $bulanTemuan !== $month) ? '<a href="'.e(module_url('dashboard.php', ['tab'=>'temuan', 'cabang'=>$cabangId, 'bulan'=>$month, 'tahun'=>$year])).'" class="h-[32px] w-[32px] bg-white border border-[#D1D5D8] text-[#53595F] hover:bg-[#F4F5F7] rounded-[6px] inline-flex items-center justify-center text-decoration-none shadow-xs" title="Reset Filter"><i class="bi bi-x-lg text-[11px]"></i></a>' : '').'
        </div>
      </form>
    </div>

    <!-- Data Table (Desktop) & Cards (Mobile) -->
    <div class="bg-white border border-[#D1D5D8] rounded-[8px] overflow-hidden shadow-xs mb-4">
      <div class="h-[44px] px-4 bg-[#F8F9FA] border-b border-[#D1D5D8] flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-2">
          <i class="bi bi-table text-[#0E5C58]"></i>
          <span class="font-bold text-[13px] text-[#1B2A3A] uppercase tracking-wide">Daftar Rekapitulasi Temuan &amp; Tindakan Perbaikan</span>
        </div>
        <span class="px-2 py-0.5 rounded bg-[#F4F5F7] border border-[#D1D5D8] font-mono text-[11px] font-semibold text-[#53595F]">
          '.count($filteredFindings).' Baris Data
        </span>
      </div>

      <!-- Desktop View: Clean Table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="table-auto w-full text-left align-middle mb-0">
          <thead class="bg-[#F8F9FA] border-b border-[#D1D5D8] text-[11px] font-bold text-[#53595F] uppercase tracking-wider">
            <tr>
              <th class="py-2.5 px-3 text-center" style="width: 40px;">NO</th>
              <th class="py-2.5 px-3" style="width: 22%;">PERANGKAT &amp; KODE</th>
              <th class="py-2.5 px-3" style="width: 15%;">WAKTU &amp; PELAPOR</th>
              <th class="py-2.5 px-3" style="width: 32%;">URAIAN KENDALA / KERUSAKAN</th>
              <th class="py-2.5 px-3 text-center" style="width: 9%;">TINGKAT</th>
              <th class="py-2.5 px-3" style="width: 12%;">STATUS &amp; PROGRES</th>
              <th class="py-2.5 px-3 text-right" style="width: 10%;">AKSI</th>
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
