<?php
    // --- TAMPILAN TAB TEMUAN KENDALA & STATUS PERBAIKAN ---
    $statusTemuanFilter = trim((string)($_GET['status_temuan'] ?? 'all'));
    $tingkatTemuanFilter = trim((string)($_GET['tingkat_temuan'] ?? 'all'));
    $qTemuan = trim((string)($_GET['q_temuan'] ?? ''));
    $bulanTemuan = isset($_GET['bulan_temuan']) ? (int)$_GET['bulan_temuan'] : 0;
    $tahunTemuan = isset($_GET['tahun_temuan']) ? (int)$_GET['tahun_temuan'] : 0;

    // Ambil data temuan dari tabel/sheet Maintenance_Findings & Maintenance_Scan
    $allFindingsRaw = get_findings_report($bulanTemuan, $tahunTemuan, $cabangId);

    // KONSOLIDASI DENGAN ACTIVE FINDINGS DARI DASHBOARD
    // Menjamin bahwa temuan kendala aktif yang dihitung pada kartu KPI Dashboard pasti tampil di sini
    $existingKeys = [];
    foreach ($allFindingsRaw as $item) {
        $aid = (int)($item['asset_id'] ?? 0);
        $sid = (int)($item['scan_id'] ?? 0);
        if ($aid > 0 && $sid > 0) {
            $existingKeys["{$aid}_{$sid}"] = true;
        }
        if ($aid > 0) {
            $existingKeys["asset_{$aid}"] = true;
        }
    }

    if (!empty($unresolvedFindings)) {
        foreach ($unresolvedFindings as $uf) {
            $aid = (int)($uf['asset_id'] ?? 0);
            $sid = (int)($uf['log_id'] ?? $uf['id'] ?? 0);
            if ($aid > 0 && isset($existingKeys["{$aid}_{$sid}"])) {
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
            $existingKeys["{$aid}_{$sid}"] = true;
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
        } elseif (in_array($st, ['in progress', 'proses', 'sedang perbaikan'], true)) {
            $countInProgress++;
        } else {
            $countPerluPerbaikan++;
        }
    }

    // Filter daftar temuan sesuai pencarian dan filter
    $filteredFindings = array_filter($allFindingsRaw, function($f) use ($statusTemuanFilter, $tingkatTemuanFilter, $qTemuan, $bulanTemuan) {
        if ($bulanTemuan > 0) {
            $rawD = $f['created_at'] ?? $f['reported_at'] ?? '';
            if ($rawD !== '') {
                $m = (int)date('n', strtotime(substr($rawD, 0, 10)));
                if ($m > 0 && $m !== $bulanTemuan) return false;
            }
        }

        $st = strtolower(trim((string)($f['repair_status'] ?? '')));
        if ($statusTemuanFilter === 'perlu_perbaikan') {
            if (in_array($st, ['resolved', 'selesai', 'closed', 'ok', 'in progress', 'proses', 'sedang perbaikan'], true)) return false;
        } elseif ($statusTemuanFilter === 'proses') {
            if (!in_array($st, ['in progress', 'proses', 'sedang perbaikan'], true)) return false;
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
          <td colspan="7" class="text-center py-5 text-muted">
            <i class="bi bi-shield-check text-success fs-1 d-block mb-2 opacity-75"></i>
            <div class="fw-semibold">Tidak ada temuan kendala yang sesuai filter.</div>
            <div class="small text-secondary mt-1">Perangkat beroperasi normal atau silakan sesuaikan filter bulan/status.</div>
          </td>
        </tr>';
        $findingsCardsHtml = '
        <div class="text-center py-5 text-muted bg-white rounded-3 border">
          <i class="bi bi-shield-check text-success fs-2 d-block mb-2"></i>
          <div class="fw-semibold">Tidak ada data temuan yang sesuai.</div>
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
            $tgl = !empty($f['created_at']) ? format_id_date($f['created_at']) : (!empty($f['reported_at']) ? format_id_date($f['reported_at']) : '-');

            $lowStatus = strtolower($repairStatus);
            if (in_array($lowStatus, ['resolved', 'selesai', 'closed', 'ok'], true)) {
                $statusBadge = '<span class="badge-chip chip-success"><i class="bi bi-check-circle-fill me-1"></i> Terselesaikan</span>';
                $cardBorderColor = '#10B981';
            } elseif (in_array($lowStatus, ['in progress', 'proses', 'sedang perbaikan'], true)) {
                $statusBadge = '<span class="badge-chip chip-warning"><i class="bi bi-hourglass-split me-1"></i> Sedang Dikerjakan</span>';
                $cardBorderColor = '#F59E0B';
            } else {
                $statusBadge = '<span class="badge-chip chip-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Perlu Perbaikan</span>';
                $cardBorderColor = '#EF4444';
            }

            $lowSev = strtolower($severity);
            if ($lowSev === 'berat') {
                $sevBadge = '<span class="badge bg-danger text-white px-2 py-0.5" style="font-size: 0.72rem;"><i class="bi bi-shield-fill-exclamation me-0.5"></i> Berat</span>';
            } elseif ($lowSev === 'sedang') {
                $sevBadge = '<span class="badge bg-warning text-dark px-2 py-0.5" style="font-size: 0.72rem;"><i class="bi bi-exclamation-diamond me-0.5"></i> Sedang</span>';
            } else {
                $sevBadge = '<span class="badge bg-secondary bg-opacity-75 text-white px-2 py-0.5" style="font-size: 0.72rem;">Ringan</span>';
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

            $logBtn = $scanId > 0
                ? '<a href="'.e(module_url('maintenance_detail.php', ['id' => $scanId])).'" class="btn btn-sm btn-outline-secondary py-1 px-2 small" title="Rincian Hasil Audit Pemeliharaan"><i class="bi bi-file-earmark-medical me-0.5"></i> #'.$scanId.'</a>'
                : '';

            $qrBtn = $token !== ''
                ? '<a href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'tindak_lanjut'])).'" class="btn btn-sm btn-danger py-1 px-2.5 small fw-semibold text-white text-decoration-none shadow-xs" title="Buka Form Selesaikan Temuan Langsung"><i class="bi bi-tools me-1"></i> Selesaikan</a><a href="'.e(module_url('scan.php', ['t' => $token])).'" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-2 small" title="Buka Kartu QR Komputer"><i class="bi bi-qr-code"></i></a>'
                : '';

            $findingsTableRows .= '
            <tr style="border-bottom: 1px solid var(--app-border);">
              <td class="text-center font-monospace fw-bold text-secondary" style="width: 45px;">'.$noF.'</td>
              <td style="min-width: 170px;">
                <div class="fw-bold font-monospace text-primary" style="font-size: 0.86rem;">'.e($kode).'</div>
                <div class="fw-semibold text-dark text-truncate" style="max-width: 220px;" title="'.e($devName).'">'.e($devName).'</div>
                <div class="small text-muted text-truncate" style="font-size: 0.74rem;">
                  <i class="bi bi-person me-0.5"></i>'.e($pic).' · <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:0.68rem;">'.e($cabang).'</span>
                </div>
              </td>
              <td style="min-width: 130px;">
                <div class="small text-dark fw-semibold">'.$tgl.'</div>
                <div class="text-muted" style="font-size: 0.72rem;"><i class="bi bi-person-badge me-1"></i>'.e($teknisi).'</div>
              </td>
              <td style="min-width: 220px;">
                <div class="p-2 rounded bg-light border" style="border-color: var(--app-border) !important;">
                  <div class="fw-semibold text-danger small"><i class="bi bi-exclamation-triangle-fill me-1"></i>'.e($findingDesc).'</div>
                  '.($actionTaken !== '-' && $actionTaken !== '' ? '<div class="text-muted small mt-1 pt-1 border-top" style="font-size: 0.72rem;"><span class="fw-semibold text-secondary">Rekomendasi:</span> '.e($actionTaken).'</div>' : '').'
                </div>
              </td>
              <td class="text-center" style="width: 90px;">'.$sevBadge.'</td>
              <td style="min-width: 180px;">
                <div class="mb-1">'.$statusBadge.'</div>
                '.($prosesPerbaikan !== '' ? '<div class="small text-secondary bg-white p-1.5 rounded border" style="font-size: 0.72rem; border-color: rgba(0,0,0,0.08) !important;"><i class="bi bi-chat-left-text me-1 opacity-75"></i>'.e($prosesPerbaikan).'</div>' : '<span class="text-muted small fst-italic" style="font-size:0.72rem;">Belum ada catatan tindak lanjut</span>').'
              </td>
              <td class="text-end text-nowrap" style="width: 160px;">
                <div class="d-flex justify-content-end align-items-center gap-1">
                  <button type="button" class="btn btn-sm btn-primary py-1 px-2 fw-semibold" onclick="openProsesPerbaikanModal('.$jsonModalAttr.')" title="Catat / Perbarui Progres Perbaikan">
                    <i class="bi bi-tools me-1"></i> Tindak Lanjut
                  </button>
                  '.$logBtn.'
                  '.$qrBtn.'
                </div>
              </td>
            </tr>';

            $findingsCardsHtml .= '
            <div class="p-3 mb-2.5 rounded-3 bg-white border shadow-xs" style="border-left: 4px solid '.$cardBorderColor.' !important; border-color: var(--app-border) !important;">
              <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                  <span class="badge bg-navy-subtle text-white font-monospace fw-bold" style="font-size: 0.72rem;">'.e($kode).'</span>
                  <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size: 0.7rem;">'.e($cabang).'</span>
                  '.$sevBadge.'
                </div>
                <div>'.$statusBadge.'</div>
              </div>
              <div class="fw-bold text-dark mb-1">'.e($devName).'</div>
              <div class="small text-muted mb-2"><i class="bi bi-person me-1"></i>PIC: <strong>'.e($pic).'</strong> · Divisi: '.e($divisi).'</div>
              <div class="p-2 rounded bg-light border mb-2" style="border-color: var(--app-border) !important;">
                <div class="fw-semibold text-danger small"><i class="bi bi-exclamation-triangle-fill me-1"></i>'.e($findingDesc).'</div>
                '.($actionTaken !== '-' && $actionTaken !== '' ? '<div class="text-muted small mt-1 pt-1 border-top" style="font-size: 0.72rem;"><span class="fw-semibold text-secondary">Rekomendasi:</span> '.e($actionTaken).'</div>' : '').'
              </div>
              '.($prosesPerbaikan !== '' ? '<div class="small text-secondary bg-light p-2 rounded mb-2 border" style="font-size: 0.76rem;"><strong class="text-dark d-block mb-0.5"><i class="bi bi-clock-history me-1"></i>Catatan Perbaikan Terakhir:</strong>'.e($prosesPerbaikan).'</div>' : '').'
              <div class="pt-2 border-top d-flex justify-content-between align-items-center flex-wrap gap-2" style="border-color: rgba(0,0,0,0.06) !important;">
                <span class="text-muted small" style="font-size: 0.72rem;"><i class="bi bi-calendar-event me-1"></i>'.$tgl.' oleh '.e($teknisi).'</span>
                <div class="d-flex gap-1.5 w-100 w-sm-auto justify-content-end">
                  <button type="button" class="btn btn-sm btn-primary py-1 px-3 fw-semibold flex-fill flex-sm-grow-0" onclick="openProsesPerbaikanModal('.$jsonModalAttr.')">
                    <i class="bi bi-tools me-1"></i> Tindak Lanjut
                  </button>
                  '.$logBtn.'
                  '.$qrBtn.'
                </div>
              </div>
            </div>';
        }
    }

    $body .= '
    <!-- Header Kicker & Action Bar Tab Temuan -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <div class="text-uppercase small fw-bold" style="letter-spacing: 0.08em; color: var(--app-accent); font-size: 0.72rem; margin-bottom: 2px;">OPERATIONS / TEMUAN KENDALA &amp; PERBAIKAN</div>
        <h2 class="h4 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
          <i class="bi bi-tools text-danger"></i> Daftar Temuan Kendala &amp; Status Perbaikan
        </h2>
        <div class="text-muted small">Monitoring seluruh temuan kendala dari scan pemeliharaan, tingkat keparahan, teknisi penanggung jawab, dan progres tindak lanjut (<strong>'.($bulanTemuan > 0 ? ($monthNames[$bulanTemuan].' '.$year) : 'Semua Periode / Riwayat Temuan').'</strong>).</div>
      </div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="'.e(module_url('audit.php', ['bulan'=>$month,'tahun'=>$year,'cabang'=>$cabangId])).'" class="btn btn-sm btn-outline-primary fw-semibold"><i class="bi bi-file-earmark-bar-graph me-1"></i> Rekapitulasi Audit Bulanan</a>
      </div>
    </div>

    <!-- Mini KPI Metrics Temuan -->
    <div class="row g-2 g-md-3 mb-3 mb-md-4">
      <div class="col-6 col-md-3">
        <div class="card card-metric h-100">
          <div class="metric-value text-dark mb-0">'.$countTotalTemuan.' <span class="fs-6 fw-normal text-muted">Kasus</span></div>
          <div class="metric-label">Total Temuan Terdata</div>
          <div class="small text-muted mt-2" style="font-size: 0.72rem;">'.($bulanTemuan > 0 ? ($monthNames[$bulanTemuan].' '.$year) : 'Semua Periode').'</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card card-metric h-100">
          <div class="metric-value text-danger mb-0">'.$countPerluPerbaikan.' <span class="fs-6 fw-normal text-danger">Unit</span></div>
          <div class="metric-label text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Perlu Perbaikan (Aktif)</div>
          <div class="small text-muted mt-2" style="font-size: 0.72rem;">Menunggu penanganan</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card card-metric h-100">
          <div class="metric-value text-warning mb-0">'.$countInProgress.' <span class="fs-6 fw-normal text-warning">Unit</span></div>
          <div class="metric-label text-warning-emphasis"><i class="bi bi-hourglass-split me-1"></i>Sedang Dikerjakan</div>
          <div class="small text-muted mt-2" style="font-size: 0.72rem;">Tunggu sparepart / instalasi</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card card-metric h-100">
          <div class="metric-value text-success mb-0">'.$countResolved.' <span class="fs-6 fw-normal text-success">Unit</span></div>
          <div class="metric-label text-success"><i class="bi bi-check-circle-fill me-1"></i>Terselesaikan</div>
          <div class="small text-muted mt-2" style="font-size: 0.72rem;">Normal kembali</div>
        </div>
      </div>
    </div>

    <!-- Toolbar Filter & Pencarian -->
    <div class="card p-3 mb-3 border shadow-xs" style="border-radius: 8px; border-color: var(--app-border) !important; background: #FAFBFD;">
      <form method="get" class="row g-2 align-items-center">
        <input type="hidden" name="tab" value="temuan">
        <input type="hidden" name="tahun" value="'.$year.'">
        <input type="hidden" name="cabang" value="'.$cabangId.'">

        <div class="col-12 col-md-3">
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="text" name="q_temuan" class="form-control" placeholder="Cari kode inventaris, merk, user, temuan..." value="'.e($qTemuan).'">
          </div>
        </div>
        <div class="col-6 col-md-2">
          <select name="bulan_temuan" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="0" '.($bulanTemuan === 0 ? 'selected' : '').'>Semua Periode (Bulan)</option>';
    for ($bm = 1; $bm <= 12; $bm++) {
        $body .= '<option value="'.$bm.'" '.($bulanTemuan === $bm ? 'selected' : '').'>'.$monthNames[$bm].'</option>';
    }
    $body .= '
          </select>
        </div>
        <div class="col-6 col-md-3">
          <select name="status_temuan" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="all" '.($statusTemuanFilter === 'all' ? 'selected' : '').'>Semua Status Temuan ('.$countTotalTemuan.')</option>
            <option value="perlu_perbaikan" '.($statusTemuanFilter === 'perlu_perbaikan' ? 'selected' : '').'>🚨 Perlu Perbaikan ('.$countPerluPerbaikan.')</option>
            <option value="proses" '.($statusTemuanFilter === 'proses' ? 'selected' : '').'>⏳ Sedang Proses ('.$countInProgress.')</option>
            <option value="selesai" '.($statusTemuanFilter === 'selesai' ? 'selected' : '').'>✅ Terselesaikan ('.$countResolved.')</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select name="tingkat_temuan" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="all" '.($tingkatTemuanFilter === 'all' ? 'selected' : '').'>Semua Tingkat</option>
            <option value="Berat" '.($tingkatTemuanFilter === 'Berat' ? 'selected' : '').'>Tingkat Berat</option>
            <option value="Sedang" '.($tingkatTemuanFilter === 'Sedang' ? 'selected' : '').'>Tingkat Sedang</option>
            <option value="Ringan" '.($tingkatTemuanFilter === 'Ringan' ? 'selected' : '').'>Tingkat Ringan</option>
          </select>
        </div>
        <div class="col-6 col-md-2 d-flex gap-1.5">
          <button type="submit" class="btn btn-sm btn-primary fw-semibold flex-fill"><i class="bi bi-funnel me-1"></i> Filter</button>
          '.(($qTemuan !== '' || $statusTemuanFilter !== 'all' || $tingkatTemuanFilter !== 'all' || $bulanTemuan !== 0) ? '<a href="'.e(module_url('dashboard.php', ['tab'=>'temuan', 'cabang'=>$cabangId])).'" class="btn btn-sm btn-outline-secondary" title="Reset Filter"><i class="bi bi-x-lg"></i></a>' : '').'
        </div>
      </form>
    </div>

    <!-- Tabel Temuan (Desktop) & Cards (Mobile) -->
    <div class="card p-0 border shadow-xs" style="border-radius: 8px; border-color: var(--app-border) !important; background: #fff;">
      <div class="card-header bg-white py-2.5 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2" style="border-bottom: 1px solid var(--app-border);">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-table text-primary"></i>
          <span class="fw-bold text-dark small text-uppercase" style="letter-spacing: 0.04em;">Daftar Rekapitulasi Temuan &amp; Tindakan Perbaikan</span>
        </div>
        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 font-monospace small px-2 py-0.5">'.count($filteredFindings).' Baris Data</span>
      </div>

      <!-- Desktop View: Tabel Standar -->
      <div class="d-none d-md-block table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead style="background-color: var(--app-navy); color: #ffffff;">
            <tr>
              <th class="text-center font-monospace" style="width: 45px; background-color: var(--app-navy); color: #ffffff;">NO</th>
              <th style="background-color: var(--app-navy); color: #ffffff;">KODE &amp; PERANGKAT</th>
              <th style="background-color: var(--app-navy); color: #ffffff;">WAKTU &amp; PELAPOR</th>
              <th style="background-color: var(--app-navy); color: #ffffff;">URAIAN TEMUAN / KENDALA</th>
              <th class="text-center" style="width: 90px; background-color: var(--app-navy); color: #ffffff;">TINGKAT</th>
              <th style="background-color: var(--app-navy); color: #ffffff;">STATUS &amp; PROGRES</th>
              <th class="text-end" style="width: 160px; background-color: var(--app-navy); color: #ffffff;">AKSI</th>
            </tr>
          </thead>
          <tbody>
            '.$findingsTableRows.'
          </tbody>
        </table>
      </div>

      <!-- Mobile View: Kartu -->
      <div class="d-block d-md-none p-2.5">
        '.$findingsCardsHtml.'
      </div>
    </div>';

