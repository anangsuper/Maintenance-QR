<?php
require __DIR__ . '/bootstrap.php';

$token = trim((string)($_GET['t'] ?? $_POST['t'] ?? ''));
if ($token === '' || !preg_match('/^[a-zA-Z0-9\-_]{1,128}$/', $token)) {
    http_response_code(400);
    render_page('QR Tidak Valid', '<div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-octagon-fill me-2"></i><strong>QR tidak valid.</strong> Token QR tidak dikenali.</div>', '', '', false);
    exit;
}

$asset = get_asset_by_token($token);

if (!$asset) {
    http_response_code(404);
    render_page('QR Tidak Ditemukan', '<div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>QR tidak ditemukan atau sudah dinonaktifkan.</strong></div>', '', '', false);
    exit;
}

$assetId = (int)$asset['id'];
$month = (int)date('n');
$year = (int)date('Y');
$currentDateStr = date('Y-m-d');
$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$monthName = $monthNames[$month] ?? date('F');

// =========================================================================
// 1. PROSES SIMPAN FORM MAINTENANCE (POST)
// =========================================================================
$successData = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'save_maintenance')) {
    verify_csrf();

    $techName = trim((string)($_POST['technician_name'] ?? ''));
    if ($techName === '') {
        $techName = current_user_name();
    }
    $mDate = trim((string)($_POST['maintenance_date'] ?? $currentDateStr));
    $mStatus = trim((string)($_POST['status'] ?? 'Selesai'));
    $mType = trim((string)($_POST['maintenance_type'] ?? 'Maintenance'));
    $findings = trim((string)($_POST['findings'] ?? ''));
    $recommendation = trim((string)($_POST['recommendation'] ?? ''));

    // Checklists 1..9
    $checklists = [];
    $fixedItems = get_fixed_checklists();
    foreach ($fixedItems as $num => $name) {
        $checked = !empty($_POST['chk_' . $num]) ? 1 : 0;
        $notes = trim((string)($_POST['notes_' . $num] ?? ''));
        $checklists[$num] = [
            'checked' => $checked,
            'notes' => $notes
        ];
    }

    $payload = [
        'asset_id' => $assetId,
        'technician_user_id' => current_user_id(),
        'technician_name' => $techName,
        'maintenance_date' => $mDate,
        'maintenance_time' => date('H:i:s'),
        'maintenance_month' => (int)date('n', strtotime($mDate)),
        'maintenance_year' => (int)date('Y', strtotime($mDate)),
        'status' => $mStatus,
        'maintenance_type' => $mType,
        'findings' => $findings,
        'recommendation' => $recommendation,
        'checklists' => $checklists
    ];

    $res = save_maintenance_record($payload);
    if (!empty($res['success'])) {
        $successData = [
            'log_id' => $res['log_id'],
            'date' => $mDate,
            'technician' => $techName,
            'status' => $mStatus,
            'type' => $mType,
            'findings' => $findings,
            'recommendation' => $recommendation
        ];
    } else {
        $error = $res['error'] ?? 'Gagal menyimpan data maintenance.';
    }
}

// =========================================================================
// 2. TAMPILAN SETELAH BERHASIL SIMPAN
// =========================================================================
if ($successData) {
    $statusBadge = ($successData['status'] === 'Temuan' || $successData['status'] === 'Perlu Perbaikan')
        ? '<span class="badge bg-danger fs-6 px-3 py-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> Perlu Perbaikan</span>'
        : ($successData['status'] === 'Proses'
            ? '<span class="badge bg-warning text-dark fs-6 px-3 py-2"><i class="bi bi-hourglass-split me-1"></i> Sedang Proses</span>'
            : '<span class="badge bg-success fs-6 px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i> Selesai</span>');

    $body = '
    <div class="row justify-content-center">
      <div class="col-md-8 col-lg-6">
        <div class="card p-4 p-md-5 border-0 shadow-sm text-center">
          <div class="mb-3">
            <span class="d-inline-flex p-3 rounded-circle bg-success bg-opacity-10 text-success fs-1">
              <i class="bi bi-check2-circle"></i>
            </span>
          </div>
          <h3 class="fw-bold text-success mb-1">Maintenance Berhasil Disimpan!</h3>
          <p class="text-secondary small mb-4">Data pemeliharaan perangkat telah tercatat ke dalam histori sistem & audit trail.</p>

          <div class="bg-light p-3 rounded-3 text-start mb-4 border">
            <div class="row g-2 small">
              <div class="col-5 text-muted">Perangkat:</div>
              <div class="col-7 fw-bold text-dark">'.e(asset_title($asset)).'</div>
              <div class="col-5 text-muted">Kode Inventaris:</div>
              <div class="col-7 text-primary fw-bold">'.e($asset['kode_inventaris'] ?? '-').'</div>
              <div class="col-5 text-muted">Tanggal:</div>
              <div class="col-7 fw-semibold">'.e(format_id_date($successData['date'])).'</div>
              <div class="col-5 text-muted">Petugas/Teknisi:</div>
              <div class="col-7 fw-bold text-dark">'.e($successData['technician']).'</div>
              <div class="col-5 text-muted">Status:</div>
              <div class="col-7">'.$statusBadge.'</div>
            </div>
          </div>

          <div class="d-grid gap-2">
            <a class="btn btn-primary fw-bold py-3 shadow-sm" href="'.e(module_url('maintenance_detail.php', ['id' => $successData['log_id']])).'">
              <i class="bi bi-file-earmark-text me-1"></i> Lihat Detail Hasil Maintenance
            </a>
            <a class="btn btn-outline-secondary py-2" href="'.e(module_url('scan.php', ['t' => $token])).'">
              <i class="bi bi-arrow-left me-1"></i> Kembali ke Detail Perangkat
            </a>
          </div>
        </div>
      </div>
    </div>';

    render_page('Maintenance Berhasil Disimpan', $body, '', '', false);
    exit;
}

// Cek status maintenance bulan berjalan
$currentMonthLog = get_asset_maintenance_status_month($assetId, $month, $year);
$action = trim((string)($_GET['action'] ?? ''));

// =========================================================================
// 3. TAMPILAN FORM CHECKLIST 9 ITEM (action = start ATAU form)
// =========================================================================
if ($action === 'start' || $action === 'form' || $action === 'ulang') {
    $fixedItems = get_fixed_checklists();
    $isUlang = ($action === 'ulang' || ($currentMonthLog && $action === 'start'));

    $checklistRowsHtml = '';
    foreach ($fixedItems as $num => $name) {
        $checklistRowsHtml .= '
        <div class="p-3 border rounded-3 mb-2 bg-white checklist-item">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="form-check form-switch fs-5 mb-0">
              <input class="form-check-input" type="checkbox" role="switch" id="chk_'.$num.'" name="chk_'.$num.'" value="1">
              <label class="form-check-label fw-bold fs-6 text-dark ms-2" for="chk_'.$num.'">
                '.$num.'. '.e($name).'
              </label>
            </div>
            <span class="badge bg-light text-secondary border small">Item #'.$num.'</span>
          </div>
          <div class="ps-md-4">
            <input type="text" class="form-control form-control-sm bg-light" name="notes_'.$num.'" placeholder="Keterangan / Catatan (Contoh: Normal, Bersih, OK, dll)">
          </div>
        </div>';
    }

    $techDefault = current_user_name();
    $karyawanList = get_karyawan_list();
    $techOptions = '';
    foreach ($karyawanList as $k) {
        $kn = $k['nama_karyawan'] ?? $k['nama'] ?? '';
        if ($kn !== '') {
            $techOptions .= '<option value="'.e($kn).'">';
        }
    }

    $formTitle = $isUlang ? 'Form Maintenance Ulang' : 'Form Checklist Maintenance';
    $mTypeVal = $isUlang ? 'Maintenance Ulang' : 'Maintenance';

    $body = '
    <div class="row justify-content-center">
      <div class="col-md-9 col-lg-7">
        <div class="card p-3 p-md-4 border-0 shadow-sm mb-4">
          <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
            <div>
              <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-2 py-1 mb-1">Periode '.$monthName.' '.$year.'</span>
              <h4 class="fw-bold text-dark mb-0"><i class="bi bi-clipboard-check text-primary me-2"></i>'.$formTitle.'</h4>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="'.e(module_url('scan.php', ['t' => $token])).'"><i class="bi bi-x-lg"></i> Batal</a>
          </div>

          <!-- Ringkasan Perangkat -->
          <div class="p-3 bg-light rounded-3 mb-4 border">
            <div class="row g-2 small">
              <div class="col-4 text-muted">Perangkat:</div>
              <div class="col-8 fw-bold text-dark">'.e(asset_title($asset)).'</div>
              <div class="col-4 text-muted">Kode Inv:</div>
              <div class="col-8 text-primary fw-bold">'.e($asset['kode_inventaris'] ?? '-').'</div>
              <div class="col-4 text-muted">Pemilik:</div>
              <div class="col-8 fw-semibold text-dark">'.e($asset['karyawan_nama'] ?? '-').'</div>
              <div class="col-4 text-muted">Lokasi:</div>
              <div class="col-8">'.e($asset['cabang_nama'] ?? '-').' · '.e($asset['divisi_nama'] ?? '-').'</div>
            </div>
          </div>

          '.($error ? '<div class="alert alert-danger py-2 mb-3">'.e($error).'</div>' : '').'

          <form method="post">
            <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
            <input type="hidden" name="action" value="save_maintenance">
            <input type="hidden" name="t" value="'.e($token).'">
            <input type="hidden" name="maintenance_type" value="'.e($mTypeVal).'">

            <!-- 1. 9 Items Checklist -->
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-check2-square text-primary me-2"></i>9 Checklist Pemeliharaan:</h6>
            <div class="small text-muted mb-3">Centang item yang dikerjakan & isi catatan jika diperlukan:</div>
            <div class="mb-4">
              '.$checklistRowsHtml.'
            </div>

            <!-- 2. Data Pelaksanaan Maintenance -->
            <h6 class="fw-bold text-dark mb-3 border-top pt-3"><i class="bi bi-person-badge text-primary me-2"></i>Data Pelaksanaan:</h6>
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label small fw-bold text-secondary">Tanggal Maintenance</label>
                <input type="date" class="form-control" name="maintenance_date" value="'.$currentDateStr.'" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-bold text-secondary">Petugas / Teknisi</label>
                <input type="text" class="form-control" name="technician_name" list="listTeknisi" value="'.e($techDefault).'" placeholder="Nama teknisi" required>
                <datalist id="listTeknisi">'.$techOptions.'</datalist>
              </div>
            </div>

            <!-- 3. Temuan & Rekomendasi -->
            <div class="mb-3">
              <label class="form-label small fw-bold text-secondary">Temuan / Catatan Masalah</label>
              <textarea class="form-control" name="findings" rows="2" placeholder="Catat jika ada komponen rusak, tinta habis, virus, lemot, dll..."></textarea>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold text-secondary">Rekomendasi Tindakan</label>
              <textarea class="form-control" name="recommendation" rows="2" placeholder="Tindakan yang disarankan, misal: ganti SSD, isi tinta, upgrade RAM, dll..."></textarea>
            </div>

            <!-- 4. Status Hasil Maintenance -->
            <div class="mb-4">
              <label class="form-label small fw-bold text-secondary">Status Hasil Maintenance</label>
              <select class="form-select fw-bold py-2" name="status">
                <option value="Selesai" class="text-success" selected>✓ Selesai (Kondisi Normal & Berfungsi Baik)</option>
                <option value="Proses" class="text-warning">⏳ Proses (Sedang Ditangani / Butuh Waktu)</option>
                <option value="Perlu Perbaikan" class="text-danger">⚠️ Perlu Perbaikan (Ada Kerusakan / Perlu Sparepart)</option>
              </select>
            </div>

            <!-- Submit Button -->
            <div class="d-grid gap-2">
              <button type="submit" class="btn btn-success btn-lg fw-bold py-3 shadow-sm" onclick="return confirm(\'Simpan hasil checklist maintenance sekarang?\')">
                <i class="bi bi-save-fill me-2"></i> SIMPAN MAINTENANCE
              </button>
              <a class="btn btn-outline-secondary py-2" href="'.e(module_url('scan.php', ['t' => $token])).'">Batal</a>
            </div>
          </form>
        </div>
      </div>
    </div>';

    render_page($formTitle, $body, '', '', false);
    exit;
}

// =========================================================================
// 4. TAMPILAN UTAMA: DETAIL PERANGKAT & STATUS BULAN BERJALAN (STEP 1)
// =========================================================================

// Histori & Yearly Grid
$historyList = get_asset_maintenance_history($assetId);
$yearlyGrid = get_asset_yearly_maintenance_grid($assetId, $year);

// Komponen Status Bulan Berjalan
if ($currentMonthLog) {
    $cDate = $currentMonthLog['maintenance_date'] ?? date('Y-m-d');
    $cTech = $currentMonthLog['technician_name'] ?? 'Teknisi';
    $cStatus = $currentMonthLog['status'] ?? 'Selesai';
    $cLogId = (int)($currentMonthLog['id'] ?? 0);
    $cFindings = $currentMonthLog['findings'] ?? '';
    $cRecom = $currentMonthLog['recommendation'] ?? '';

    $mDetail = $cLogId > 0 ? get_maintenance_detail($cLogId) : null;
    $chkListItems = $mDetail ? $mDetail['checklists'] : [];

    // Render 9 Item Checklist dengan Logo / Ikon Centang
    $chkDisplayHtml = '';
    if (!empty($chkListItems)) {
        $chkDisplayHtml .= '
        <div class="mt-3 pt-3 border-top border-success border-opacity-25">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-bold text-dark small"><i class="bi bi-check2-all text-success me-1"></i> HASIL 9 CHECKLIST PEMELIHARAAN:</span>
            <span class="badge bg-success bg-opacity-25 text-success small">Tercatat</span>
          </div>
          <div class="row g-2">';
        
        foreach ($chkListItems as $num => $chk) {
            $isChk = !empty($chk['checked']);
            $chkNote = !empty($chk['notes']) ? '<div class="text-muted small fst-italic" style="font-size:0.75rem;"><i class="bi bi-chat-left-text me-1"></i>'.e($chk['notes']).'</div>' : '';

            if ($isChk) {
                $chkDisplayHtml .= '
                <div class="col-12 col-md-6">
                  <div class="p-2 rounded-2 bg-white border border-success d-flex align-items-start gap-2 shadow-sm">
                    <span class="text-success fs-5 lh-1"><i class="bi bi-check-circle-fill"></i></span>
                    <div class="small">
                      <div class="fw-bold text-dark">'.$num.'. '.e($chk['name']).'</div>
                      '.$chkNote.'
                    </div>
                  </div>
                </div>';
            } else {
                $chkDisplayHtml .= '
                <div class="col-12 col-md-6">
                  <div class="p-2 rounded-2 bg-white border d-flex align-items-start gap-2 opacity-75">
                    <span class="text-muted fs-5 lh-1"><i class="bi bi-circle"></i></span>
                    <div class="small">
                      <div class="text-secondary">'.$num.'. '.e($chk['name']).'</div>
                    </div>
                  </div>
                </div>';
            }
        }
        $chkDisplayHtml .= '</div></div>';
    }

    $findingsDisplayHtml = '';
    if ($cFindings !== '' || $cRecom !== '') {
        $findingsDisplayHtml = '
        <div class="row g-2 mt-2 pt-2 border-top border-success border-opacity-25 small">
          '.($cFindings !== '' ? '<div class="col-12"><div class="p-2 rounded bg-white border border-danger text-danger"><strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Temuan:</strong> '.e($cFindings).'</div></div>' : '').'
          '.($cRecom !== '' ? '<div class="col-12"><div class="p-2 rounded bg-white border border-success text-success"><strong><i class="bi bi-lightbulb-fill me-1"></i>Rekomendasi:</strong> '.e($cRecom).'</div></div>' : '').'
        </div>';
    }

    $badgeColor = ($cStatus === 'Temuan' || $cStatus === 'Perlu Perbaikan') ? 'danger' : ($cStatus === 'Proses' ? 'warning text-dark' : 'success');
    $badgeIcon = ($cStatus === 'Temuan' || $cStatus === 'Perlu Perbaikan') ? 'bi-exclamation-triangle-fill' : ($cStatus === 'Proses' ? 'bi-hourglass-split' : 'bi-check-circle-fill');

    $statusCardHtml = '
    <div class="card border-0 shadow-sm mb-4 bg-success bg-opacity-10 border-start border-success border-4 p-3 p-md-4">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-success fw-bold fs-6"><i class="bi bi-calendar-check-fill me-1"></i> STATUS BULAN BERJALAN:</span>
        <span class="badge bg-'.$badgeColor.' px-3 py-2 fs-6"><i class="bi '.$badgeIcon.' me-1"></i> '.e($cStatus).'</span>
      </div>
      <h5 class="fw-bold text-dark mb-1">Periode: '.$monthName.' '.$year.'</h5>
      <p class="text-secondary small mb-2">Perangkat ini <strong>sudah dilakukan maintenance</strong> pada <strong>'.e(format_id_date($cDate)).'</strong> oleh <strong>'.e($cTech).'</strong>.</p>
      
      '.$chkDisplayHtml.'
      '.$findingsDisplayHtml.'

      <div class="d-flex flex-wrap gap-2 mt-3 pt-2">
        '.($cLogId > 0 ? '<a class="btn btn-primary fw-semibold" href="'.e(module_url('maintenance_detail.php', ['id' => $cLogId])).'"><i class="bi bi-file-earmark-text me-1"></i> LIHAT DETAIL LENGKAP</a>' : '').'
        <a class="btn btn-outline-primary fw-semibold" href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'ulang'])).'"><i class="bi bi-arrow-repeat me-1"></i> MAINTENANCE ULANG</a>
      </div>
    </div>';
} else {
    $statusCardHtml = '
    <div class="card border-0 shadow-sm mb-4 bg-danger bg-opacity-10 border-start border-danger border-4 p-3 p-md-4">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-danger fw-bold fs-6"><i class="bi bi-exclamation-circle-fill me-1"></i> STATUS BULAN BERJALAN:</span>
        <span class="badge bg-danger px-3 py-2 fs-6"><i class="bi bi-x-circle-fill me-1"></i> Belum Maintenance</span>
      </div>
      <h5 class="fw-bold text-dark mb-1">Periode: '.$monthName.' '.$year.'</h5>
      <p class="text-secondary small mb-3">Perangkat ini belum dilakukan pemeliharaan hardware & OS untuk bulan ini.</p>
      
      <a class="btn btn-success btn-lg fw-bold py-3 px-4 shadow-sm w-100" href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'start'])).'">
        <i class="bi bi-play-circle-fill me-2"></i> MULAI MAINTENANCE SEKARANG
      </a>
    </div>';
}

// Komponen Riwayat Bulanan Tahun 2026 (12 Bulan Grid)
$gridHtml = '';
foreach ($yearlyGrid as $mNum => $mInfo) {
    $mIsDone = !empty($mInfo['is_done']);
    $cellClass = $mIsDone ? 'bg-success bg-opacity-10 text-success border-success' : 'bg-light text-muted border';
    $cellIcon = $mIsDone ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<span class="text-muted fw-bold">-</span>';
    $linkWrapOpen = ($mIsDone && $mInfo['log_id'] > 0) ? '<a href="'.e(module_url('maintenance_detail.php', ['id' => $mInfo['log_id']])).'" class="text-decoration-none" title="Klik untuk lihat detail">' : '<div>';
    $linkWrapClose = ($mIsDone && $mInfo['log_id'] > 0) ? '</a>' : '</div>';

    $gridHtml .= '
    <div class="col-4 col-md-3 col-lg-2">
      '.$linkWrapOpen.'
        <div class="p-2 rounded text-center border '.$cellClass.'">
          <div class="small fw-bold text-truncate">'.substr($mInfo['month_name'], 0, 3).'</div>
          <div class="fs-5 my-1">'.$cellIcon.'</div>
          <div class="small text-truncate" style="font-size: 0.72rem;">'.($mIsDone ? e($mInfo['date']) : 'Belum').'</div>
        </div>
      '.$linkWrapClose.'
    </div>';
}

// Komponen Tabel Histori Sebelumnya
$historyRowsHtml = '';
if (!empty($historyList)) {
    $hNum = 0;
    foreach ($historyList as $h) {
        $hNum++;
        $hStatus = $h['status'] ?? 'Selesai';
        $hBadge = ($hStatus === 'Temuan' || $hStatus === 'Perlu Perbaikan')
            ? '<span class="badge-chip chip-danger"><i class="bi bi-exclamation-triangle-fill"></i> '.$hStatus.'</span>'
            : ($hStatus === 'Proses'
                ? '<span class="badge-chip chip-warning"><i class="bi bi-hourglass-split"></i> '.$hStatus.'</span>'
                : '<span class="badge-chip chip-success"><i class="bi bi-check-circle-fill"></i> '.$hStatus.'</span>');

        $historyRowsHtml .= '
        <tr>
          <td class="text-center text-muted">'.$hNum.'</td>
          <td class="fw-semibold">'.e(format_id_date($h['maintenance_date'] ?? '')).'</td>
          <td>'.e($monthNames[(int)($h['maintenance_month'] ?? 0)] ?? '-').'</td>
          <td>'.e($h['maintenance_year'] ?? '-').'</td>
          <td class="fw-semibold text-dark">'.e($h['technician_name'] ?? 'Teknisi').'</td>
          <td>'.$hBadge.'</td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary" href="'.e(module_url('maintenance_detail.php', ['id' => (int)$h['id']])).'"><i class="bi bi-file-earmark-text"></i> Detail</a>
          </td>
        </tr>';
    }
} else {
    $historyRowsHtml = '<tr><td colspan="7" class="text-center py-4 text-secondary">Belum ada riwayat maintenance sebelumnya untuk perangkat ini.</td></tr>';
}

// Maintenance Terakhir
$lastMaintStr = !empty($historyList[0])
    ? format_id_date($historyList[0]['maintenance_date'] ?? '') . ' oleh ' . ($historyList[0]['technician_name'] ?? 'Teknisi')
    : 'Belum pernah';

$body = '
<div class="row justify-content-center">
  <div class="col-md-10 col-lg-8">

    <!-- Card Detail Perangkat Utama -->
    <div class="card p-3 p-md-4 border-0 shadow-sm mb-4">
      <div class="d-flex flex-wrap align-items-center justify-content-between border-bottom pb-3 mb-3 gap-2">
        <div>
          <span class="badge bg-primary px-3 py-1 mb-1">'.e($asset['kategori_nama'] ?? 'Perangkat IT').'</span>
          <h3 class="fw-bold text-dark mb-0">'.e(asset_title($asset)).'</h3>
        </div>
        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-2 border border-success">
          <i class="bi bi-check-circle-fill me-1"></i> '.e($asset['status'] ?? 'Aktif').'
        </span>
      </div>

      <!-- Detail Spesifikasi & Kepemilikan -->
      <div class="row g-3 small mb-2">
        <div class="col-6 col-md-4">
          <div class="text-secondary">Kode Inventaris:</div>
          <div class="fw-bold text-primary fs-6">'.e($asset['kode_inventaris'] ?? '-').'</div>
        </div>
        <div class="col-6 col-md-4">
          <div class="text-secondary">Serial Number:</div>
          <div class="fw-semibold text-dark">'.e($asset['serial_number'] ?? '-').'</div>
        </div>
        <div class="col-6 col-md-4">
          <div class="text-secondary">Merk / Model:</div>
          <div class="fw-semibold text-dark">'.e($asset['merk'] ?? '-').' / '.e($asset['model'] ?? '-').'</div>
        </div>
        <div class="col-6 col-md-4">
          <div class="text-secondary">Pengguna / Pemilik:</div>
          <div class="fw-bold text-dark"><i class="bi bi-person-circle text-primary me-1"></i>'.e($asset['karyawan_nama'] ?? '-').'</div>
        </div>
        <div class="col-6 col-md-4">
          <div class="text-secondary">Divisi:</div>
          <div class="fw-semibold text-dark">'.e($asset['divisi_nama'] ?? '-').'</div>
        </div>
        <div class="col-6 col-md-4">
          <div class="text-secondary">Cabang / Lokasi:</div>
          <div class="fw-semibold text-dark">'.e($asset['cabang_nama'] ?? '-').'</div>
        </div>
        <div class="col-12">
          <div class="text-secondary">Maintenance Terakhir:</div>
          <div class="fw-semibold text-dark"><i class="bi bi-clock-history me-1 text-secondary"></i>'.e($lastMaintStr).'</div>
        </div>
        '.(!empty($asset['keterangan']) ? '
        <div class="col-12">
          <div class="text-secondary">Catatan / Spesifikasi:</div>
          <div class="p-2 bg-light rounded text-dark fst-italic">'.e($asset['keterangan']).'</div>
        </div>' : '').'
      </div>
    </div>

    <!-- Card Status Maintenance Bulan Berjalan -->
    '.$statusCardHtml.'

    <!-- Card Riwayat Maintenance Tahun Ini (Grid 12 Bulan) -->
    <div class="card p-3 p-md-4 border-0 shadow-sm mb-4">
      <h6 class="fw-bold text-dark mb-3"><i class="bi bi-calendar3 text-primary me-2"></i>Riwayat Maintenance Tahun '.$year.':</h6>
      <div class="row g-2 mb-2">
        '.$gridHtml.'
      </div>
      <div class="small text-muted mt-2">Keterangan: <span class="text-success fw-bold">✓ Sudah</span> · <span class="text-muted fw-bold">- Belum</span> (Klik bulan untuk melihat rincian).</div>
    </div>

    <!-- Card Tabel Semua Histori Perangkat Ini -->
    <div class="card p-3 p-md-4 border-0 shadow-sm mb-4">
      <h6 class="fw-bold text-dark mb-3"><i class="bi bi-clock-history text-primary me-2"></i>Histori Maintenance Keseluruhan:</h6>
      <div class="table-responsive rounded-3 border">
        <table class="table table-hover align-middle mb-0 small">
          <thead>
            <tr>
              <th style="width:30px" class="text-center">No</th>
              <th>Tanggal</th>
              <th>Bulan</th>
              <th>Tahun</th>
              <th>Petugas</th>
              <th>Status</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>'.$historyRowsHtml.'</tbody>
        </table>
      </div>
    </div>

  </div>
</div>';

render_page('Detail Perangkat · ' . ($asset['kode_inventaris'] ?? 'QR'), $body, '', '', false);
