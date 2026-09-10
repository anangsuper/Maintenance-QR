<?php
require __DIR__ . '/bootstrap.php';

$token = trim((string)($_GET['t'] ?? $_POST['t'] ?? ''));
if ($token === '' || !preg_match('/^[a-zA-Z0-9\-_]{1,128}$/', $token)) {
    render_page('QR Tidak Valid', '<div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-octagon-fill me-2"></i><strong>QR tidak valid.</strong> Token QR tidak dikenali.</div>', '', '', false);
    exit;
}

$asset = get_asset_by_token($token);

if (!$asset) {
    render_page('QR Tidak Ditemukan', '<div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>QR tidak ditemukan atau belum terdaftar di sistem.</strong> Pastikan kode QR sudah di-generate di menu admin QR Aset.</div>', '', '', false);
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
$successTindakLanjut = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'save_maintenance')) {
    // Verifikasi CSRF session jika ada, atau verifikasi token QR fisik jika sesi mobile serverless ter-reset
    $sentCsrf = (string)($_POST['_csrf'] ?? '');
    $sessionCsrf = (string)($_SESSION['_csrf'] ?? '');
    $postToken = trim((string)($_POST['t'] ?? ''));
    $csrfValid = ($sessionCsrf !== '' && $sentCsrf !== '' && hash_equals($sessionCsrf, $sentCsrf));
    $tokenValid = ($postToken !== '' && hash_equals($token, $postToken));
    if (!$csrfValid && !$tokenValid) {
        verify_csrf();
    }

    $techName = trim((string)($_POST['technician_name'] ?? ''));
    if ($techName === '') {
        $techName = is_logged_in() ? current_user_name() : 'Teknisi';
    }
    $mDate = trim((string)($_POST['maintenance_date'] ?? $currentDateStr));
    $mStatus = trim((string)($_POST['status'] ?? 'Selesai'));
    $mType = trim((string)($_POST['maintenance_type'] ?? 'Maintenance'));
    $findings = trim((string)($_POST['findings'] ?? ''));
    $recommendation = trim((string)($_POST['recommendation'] ?? ''));

    // Biometric audit fields
    $bioVerified = !empty($_POST['biometric_verified']) ? 1 : 0;
    $bioConfidence = (float)($_POST['biometric_confidence'] ?? 0);
    $bioPhoto = trim((string)($_POST['biometric_photo'] ?? ''));
    $latitude = trim((string)($_POST['latitude'] ?? ''));
    $longitude = trim((string)($_POST['longitude'] ?? ''));

    // Update IP & Printer jika ada perubahan saat scan
    $inputIp = trim((string)($_POST['ip_address'] ?? ''));
    $inputPrinter = trim((string)($_POST['printer'] ?? ''));
    $curIp = $asset['ip_address'] ?? $asset['ip'] ?? '';
    $curPrt = $asset['printer'] ?? '';
    if (($inputIp !== '' && $inputIp !== $curIp) || ($inputPrinter !== '' && $inputPrinter !== $curPrt)) {
        $upData = $asset;
        if ($inputIp !== '') $upData['ip_address'] = $inputIp;
        if ($inputPrinter !== '') $upData['printer'] = $inputPrinter;
        update_asset($assetId, $upData);
        $asset['ip_address'] = $upData['ip_address'];
        $asset['printer'] = $upData['printer'];
    }

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

    $techUserId = is_logged_in() ? current_user_id() : 0;
    if ($techUserId <= 0 && $techName !== '' && strcasecmp($techName, 'Teknisi') !== 0) {
        $allUsers = get_user_list(true);
        $foundUser = false;
        foreach ($allUsers as $u) {
            if (strcasecmp((string)($u['nama'] ?? ''), $techName) === 0) {
                $techUserId = (int)($u['id'] ?? 0);
                $foundUser = true;
                break;
            }
        }
        if (!$foundUser) {
            $created = create_new_user([
                'nama' => $techName,
                'role' => 'teknisi',
                'status' => 'Aktif'
            ]);
            if (!empty($created['id'])) {
                $techUserId = (int)$created['id'];
            }
        }
    }

    $payload = [
        'asset_id' => $assetId,
        'technician_user_id' => $techUserId,
        'technician_name' => $techName,
        'maintenance_date' => $mDate,
        'maintenance_time' => date('H:i:s'),
        'maintenance_month' => (int)date('n', strtotime($mDate)),
        'maintenance_year' => (int)date('Y', strtotime($mDate)),
        'status' => $mStatus,
        'maintenance_type' => $mType,
        'findings' => $findings,
        'recommendation' => $recommendation,
        'biometric_verified' => $bioVerified,
        'biometric_confidence' => $bioConfidence,
        'biometric_photo' => $bioPhoto,
        'latitude' => $latitude,
        'longitude' => $longitude,
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
            'recommendation' => $recommendation,
            'biometric_verified' => $bioVerified,
            'biometric_confidence' => $bioConfidence,
            'biometric_photo' => $bioPhoto,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    } else {
        $error = $res['error'] ?? 'Gagal menyimpan data maintenance.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'save_tindak_lanjut')) {
    $sentCsrf = (string)($_POST['_csrf'] ?? '');
    $sessionCsrf = (string)($_SESSION['_csrf'] ?? '');
    $postToken = trim((string)($_POST['t'] ?? ''));
    $csrfValid = ($sessionCsrf !== '' && $sentCsrf !== '' && hash_equals($sessionCsrf, $sentCsrf));
    $tokenValid = ($postToken !== '' && hash_equals($token, $postToken));
    if (!$csrfValid && !$tokenValid) {
        verify_csrf();
    }

    $logId = (int)($_POST['log_id'] ?? 0);
    $findingId = (int)($_POST['finding_id'] ?? 0);
    $techName = trim((string)($_POST['technician_name'] ?? ''));
    if ($techName === '') {
        $techName = is_logged_in() ? current_user_name() : 'Teknisi';
    }
    $actionTaken = trim((string)($_POST['action_taken'] ?? ''));
    $newStatus = trim((string)($_POST['status'] ?? 'Selesai'));
    $updateChecklists = !empty($_POST['update_checklists']);
    $tglTindakLanjut = trim((string)($_POST['tindak_lanjut_date'] ?? date('Y-m-d')));
    $jamTindakLanjut = trim((string)($_POST['tindak_lanjut_time'] ?? date('H:i:s')));

    $res = resolve_asset_finding($assetId, [
        'log_id' => $logId,
        'finding_id' => $findingId,
        'technician_name' => $techName,
        'action_taken' => $actionTaken,
        'status' => $newStatus,
        'update_checklists' => $updateChecklists,
        'date' => $tglTindakLanjut,
        'time' => $jamTindakLanjut
    ]);

    if (!empty($res['success'])) {
        $successTindakLanjut = [
            'technician' => $techName,
            'action_taken' => $actionTaken,
            'status' => $newStatus,
            'date' => $tglTindakLanjut,
            'time' => $jamTindakLanjut,
            'log_id' => $res['log_id'] ?? $logId
        ];
    } else {
        $error = $res['error'] ?? 'Gagal menyimpan data tindak lanjut.';
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

    $bioSuccessHtml = '';
    if (!empty($successData['biometric_verified'])) {
        $photoThumb = !empty($successData['biometric_photo'])
            ? '<img src="'.e($successData['biometric_photo']).'" class="rounded-circle border border-2 border-success me-2" style="width: 52px; height: 52px; object-fit: cover;">'
            : '';
        $bioSuccessHtml = '
        <div class="alert alert-success py-3 px-3 d-flex align-items-center mb-3 text-start border-0 bg-success bg-opacity-10 shadow-sm">
          '.$photoThumb.'
          <div>
            <div class="fw-bold text-success"><i class="bi bi-shield-check-fill me-1"></i> Identitas Terverifikasi Biometrik Wajah!</div>
            <div class="small text-muted">Tingkat Kecocokan: <strong>'.round($successData['biometric_confidence']).'%</strong> · Liveness Detection Lolos · Bukti audit tersimpan.</div>
          </div>
        </div>';
    }

    $ipStr = $asset['ip_address'] ?? $asset['ip'] ?? '-';
    $prtStr = $asset['printer'] ?? '-';

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
          <p class="text-secondary small mb-3">Hasil checklist pemeliharaan telah dicatat ke dalam Kartu Kontrol & Database.</p>

          '.$bioSuccessHtml.'

          <div class="bg-light p-3 rounded-3 text-start mb-4 border">
            <div class="row g-2 small">
              <div class="col-5 text-muted">Perangkat:</div>
              <div class="col-7 fw-bold text-dark">'.e(asset_title($asset)).'</div>
              <div class="col-5 text-muted">Kode Inventaris:</div>
              <div class="col-7 text-primary fw-bold">'.e($asset['kode_inventaris'] ?? '-').'</div>
              <div class="col-5 text-muted">Alamat IP:</div>
              <div class="col-7 fw-bold text-success font-monospace">'.e($ipStr).'</div>
              <div class="col-5 text-muted">Printer:</div>
              <div class="col-7 fw-semibold text-dark">'.e($prtStr).'</div>
              <div class="col-5 text-muted">Tanggal:</div>
              <div class="col-7 fw-semibold">'.e(format_id_date($successData['date'])).'</div>
              <div class="col-5 text-muted">Petugas/Teknisi:</div>
              <div class="col-7 fw-bold text-dark">'.e($successData['technician']).'</div>
              <div class="col-5 text-muted">Status:</div>
              <div class="col-7">'.$statusBadge.'</div>
            </div>
          </div>

          <div class="d-grid gap-2">
            <a class="btn btn-primary fw-bold py-3 shadow-sm" href="scan.php?t='.urlencode($token).'">
              <i class="bi bi-card-checklist me-1"></i> Lihat Kartu Kontrol Perangkat
            </a>
            <a class="btn btn-outline-secondary py-2" href="maintenance_detail.php?id='.((int)$successData['log_id']).'">
              <i class="bi bi-file-earmark-text me-1"></i> Rincian Audit Lengkap
            </a>
          </div>
          <div class="text-center mt-3 text-muted small">
            <span class="spinner-border spinner-border-sm me-1 text-primary"></span> Otomatis membuka Kartu Kontrol dalam 3 detik...
          </div>
        </div>
      </div>
    </div>
    <script>
    setTimeout(function() {
      window.location.href = "scan.php?t=" + encodeURIComponent("'.e($token).'");
    }, 2800);
    </script>';

    render_page('Maintenance Berhasil Disimpan', $body, '', '', false);
    exit;
}

if ($successTindakLanjut) {
    $statusBadge = ($successTindakLanjut['status'] === 'Selesai')
        ? '<span class="badge bg-success fs-6 px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i> Selesai (Normal Kembali)</span>'
        : '<span class="badge bg-warning text-dark fs-6 px-3 py-2"><i class="bi bi-hourglass-split me-1"></i> Sedang Proses</span>';

    $body = '
    <div class="row justify-content-center">
      <div class="col-md-8 col-lg-6">
        <div class="card p-4 p-md-5 border-0 shadow-sm text-center">
          <div class="mb-3">
            <span class="d-inline-flex p-3 rounded-circle bg-success bg-opacity-10 text-success fs-1">
              <i class="bi bi-tools"></i>
            </span>
          </div>
          <h3 class="fw-bold text-success mb-1">Tindak Lanjut Berhasil Disimpan!</h3>
          <p class="text-secondary small mb-3">Tindakan perbaikan telah dicatat. Status temuan di Dashboard telah diperbarui.</p>

          <div class="bg-light p-3 rounded-3 text-start mb-4 border">
            <div class="row g-2 small">
              <div class="col-5 text-muted">Perangkat:</div>
              <div class="col-7 fw-bold text-dark">'.e(asset_title($asset)).'</div>
              <div class="col-5 text-muted">Kode Inventaris:</div>
              <div class="col-7 text-primary fw-bold font-monospace">'.e($asset['kode_inventaris'] ?? '-').'</div>
              <div class="col-5 text-muted">Petugas Teknisi:</div>
              <div class="col-7 fw-bold text-dark">'.e($successTindakLanjut['technician']).'</div>
              <div class="col-5 text-muted">Waktu:</div>
              <div class="col-7 fw-semibold">'.e(format_id_date($successTindakLanjut['date'])).' '.$successTindakLanjut['time'].'</div>
              <div class="col-5 text-muted">Tindakan Perbaikan:</div>
              <div class="col-7 text-dark fw-semibold">'.nl2br(e($successTindakLanjut['action_taken'])).'</div>
              <div class="col-5 text-muted">Status Baru:</div>
              <div class="col-7">'.$statusBadge.'</div>
            </div>
          </div>

          <div class="d-grid gap-2">
            <a class="btn btn-primary fw-bold py-3 shadow-sm" href="scan.php?t='.urlencode($token).'">
              <i class="bi bi-card-checklist me-1"></i> Buka Kartu Kontrol Perangkat
            </a>
            '.(!empty($successTindakLanjut['log_id']) ? '
            <a class="btn btn-outline-secondary py-2" href="maintenance_detail.php?id='.((int)$successTindakLanjut['log_id']).'">
              <i class="bi bi-file-earmark-text me-1"></i> Lihat Rincian Log Audit
            </a>' : '').'
          </div>
          <div class="text-center mt-3 text-muted small">
            <span class="spinner-border spinner-border-sm me-1 text-primary"></span> Membuka Kartu Kontrol dalam 3 detik...
          </div>
        </div>
      </div>
    </div>
    <script>
    setTimeout(function() {
      window.location.href = "scan.php?t=" + encodeURIComponent("'.e($token).'");
    }, 2800);
    </script>';

    render_page('Tindak Lanjut Berhasil Disimpan', $body, '', '', false);
    exit;
}

$currentMonthLog = get_asset_maintenance_status_month($assetId, $month, $year);
$pendingFinding = get_asset_active_finding($assetId);
$action = trim((string)($_GET['action'] ?? $_GET['amp;action'] ?? $_POST['action_type'] ?? ''));
$autoOpenLogin = trim((string)($_GET['open_login'] ?? ''));

// =========================================================================
// 2.5 TAMPILAN FORM TINDAK LANJUT TEMUAN (action = tindak_lanjut)
// =========================================================================
if ($action === 'tindak_lanjut') {
    $allUsers = get_user_list(true);
    $userOptionsHtml = '';
    $currentTech = is_logged_in() ? current_user_name() : '';

    $techNames = [];
    foreach ($allUsers as $u) {
        $un = trim((string)($u['nama'] ?? ''));
        if ($un !== '' && !in_array($un, $techNames, true)) {
            $techNames[] = $un;
        }
    }
    if ($currentTech !== '' && !in_array($currentTech, $techNames, true)) {
        $techNames[] = $currentTech;
    }
    foreach ($techNames as $tn) {
        $sel = ($tn === $currentTech) ? 'selected' : '';
        $userOptionsHtml .= '<option value="'.e($tn).'" '.$sel.'>'.e($tn).'</option>';
    }

    $findingDesc = $pendingFinding['finding'] ?? ($currentMonthLog['findings'] ?? 'Pemeriksaan lanjutan perangkat');
    $findingReporter = $pendingFinding['reporter'] ?? ($currentMonthLog['technician_name'] ?? 'Teknisi');
    $findingDate = !empty($pendingFinding['date']) ? format_id_date($pendingFinding['date']) : (!empty($currentMonthLog['maintenance_date']) ? format_id_date($currentMonthLog['maintenance_date']) : date('d/m/Y'));
    $findingLogId = (int)($pendingFinding['log_id'] ?? ($currentMonthLog['id'] ?? 0));
    $findingId = (int)($pendingFinding['finding_id'] ?? 0);
    $initialRecom = $pendingFinding['recommendation'] ?? ($currentMonthLog['recommendation'] ?? '');

    $body = '
    <div class="row justify-content-center">
      <div class="col-md-9 col-lg-7">
        <div class="card p-3 p-md-4 border-0 shadow-sm mb-4">
          <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
            <div>
              <span class="badge bg-danger bg-opacity-10 text-danger fw-bold px-2 py-1 mb-1"><i class="bi bi-tools me-1"></i> Form Tindak Lanjut</span>
              <h4 class="fw-bold text-dark mb-0">Tindak Lanjuti Perbaikan</h4>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="'.e(module_url('scan.php', ['t' => $token])).'"><i class="bi bi-x-lg"></i> Batal</a>
          </div>

          <!-- Ringkasan Perangkat -->
          <div class="p-3 bg-light rounded-3 mb-3 border">
            <div class="row g-2 small">
              <div class="col-4 text-muted">Perangkat:</div>
              <div class="col-8 fw-bold text-dark">'.e(asset_title($asset)).'</div>
              <div class="col-4 text-muted">Kode Inventaris:</div>
              <div class="col-8 text-primary fw-bold font-monospace">'.e($asset['kode_inventaris'] ?? '-').'</div>
              <div class="col-4 text-muted">User / Lokasi:</div>
              <div class="col-8">'.e($asset['karyawan_nama'] ?? '-').' · '.e($asset['cabang_nama'] ?? '-').'</div>
            </div>
          </div>

          <!-- Alert Temuan yang Perlu Diperbaiki -->
          <div class="alert alert-danger border-2 border-danger bg-white p-3 rounded-3 shadow-sm mb-3">
            <div class="d-flex align-items-center justify-content-between mb-1">
              <span class="fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Masalah / Temuan Kerusakan:</span>
              <span class="badge bg-danger">Perlu Tindak Lanjut</span>
            </div>
            <div class="fs-6 fw-bold text-dark my-1">"'.e($findingDesc).'"</div>
            <div class="small text-muted mt-2">
              <i class="bi bi-person-badge me-1"></i> Dilaporkan oleh: <strong>'.e($findingReporter).'</strong> ('.e($findingDate).')
              '.($initialRecom !== '' && $initialRecom !== '-' ? '<div class="mt-1"><i class="bi bi-lightbulb me-1"></i> Catatan awal: '.e($initialRecom).'</div>' : '').'
            </div>
          </div>

          '.($error ? '<div class="alert alert-danger py-2 mb-3">'.e($error).'</div>' : '').'

          <form method="post" action="'.e(module_url('scan.php', ['t' => $token, 'action' => 'tindak_lanjut'])).'">
            <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
            <input type="hidden" name="action" value="save_tindak_lanjut">
            <input type="hidden" name="t" value="'.e($token).'">
            <input type="hidden" name="log_id" value="'.$findingLogId.'">
            <input type="hidden" name="finding_id" value="'.$findingId.'">

            <!-- 1. Teknisi yang Menindaklanjuti -->
            <div class="mb-3">
              <label class="form-label small fw-bold text-secondary">Teknisi yang Menindaklanjuti <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-person-check text-primary"></i></span>
                <input type="text" class="form-control" name="technician_name" list="listTeknisiTindak" value="'.e($currentTech).'" placeholder="Pilih atau ketik nama Anda..." required>
                <datalist id="listTeknisiTindak">'.$userOptionsHtml.'</datalist>
              </div>
              <div class="form-text text-muted" style="font-size: 0.75rem;">Nama teknisi yang melakukan penanganan / perbaikan di lokasi.</div>
            </div>

            <!-- 2. Tanggal & Jam Perbaikan -->
            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label small fw-bold text-secondary">Tanggal Tindak Lanjut</label>
                <input type="date" class="form-control" name="tindak_lanjut_date" value="'.date('Y-m-d').'" required>
              </div>
              <div class="col-6">
                <label class="form-label small fw-bold text-secondary">Jam / Waktu</label>
                <input type="time" class="form-control" name="tindak_lanjut_time" value="'.date('H:i').'">
              </div>
            </div>

            <!-- 3. Tindakan Perbaikan / Solusi -->
            <div class="mb-3">
              <label class="form-label small fw-bold text-secondary">Tindakan Perbaikan / Solusi yang Dilakukan <span class="text-danger">*</span></label>
              <textarea class="form-control" name="action_taken" id="action_taken_box" rows="3" placeholder="Contoh: Sudah dibersihkan file temp, optimasi startup, scan antivirus, dan periksa hardware..." required></textarea>
              
              <!-- Quick Chips -->
              <div class="mt-2">
                <div class="small text-muted mb-1"><i class="bi bi-tag me-1"></i> Klik untuk isi cepat tindakan:</div>
                <div class="d-flex flex-wrap gap-1">
                  <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" onclick="appendAction(\'Pembersihan cache & disk cleanup\')">🧹 Disk Cleanup</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" onclick="appendAction(\'Optimasi startup & services\')">⚡ Optimasi Startup</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" onclick="appendAction(\'Scan & hapus malware/virus\')">🛡️ Scan Antivirus</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" onclick="appendAction(\'Update sistem & driver\')">🔄 Update OS/Driver</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" onclick="appendAction(\'Pembersihan hardware & thermal paste\')">💨 Bersih Hardware</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" onclick="appendAction(\'Perbaikan printer / koneksi\')">🖨️ Perbaikan Printer</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" onclick="appendAction(\'Reinstall OS Windows\')">💻 Reinstall OS</button>
                </div>
              </div>
            </div>

            <!-- 4. Status Baru Hasil Tindak Lanjut -->
            <div class="mb-3 p-3 bg-light rounded-3 border">
              <label class="form-label small fw-bold text-dark mb-2"><i class="bi bi-check2-circle text-success me-1"></i> Status Hasil Tindak Lanjut:</label>
              <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="status" id="statusSelesai" value="Selesai" checked>
                <label class="form-check-label fw-bold text-success" for="statusSelesai">
                  <i class="bi bi-check-circle-fill me-1"></i> Selesai (Masalah Telah Teratasi - Komputer Normal Kembali)
                </label>
                <div class="small text-muted ps-4">Temuan otomatis ditutup dan hilang dari daftar temuan tertunda di Dashboard.</div>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="status" id="statusProses" value="Proses">
                <label class="form-check-label fw-bold text-warning text-dark" for="statusProses">
                  <i class="bi bi-hourglass-split me-1"></i> Sedang Proses (Menunggu Sparepart / Tindakan Tambahan)
                </label>
              </div>
            </div>

            <!-- 5. Opsi Checklist Pemeliharaan -->
            <div class="mb-4 form-check form-switch ps-4">
              <input class="form-check-input" type="checkbox" name="update_checklists" id="chkUpdateChecklist" value="1" checked>
              <label class="form-check-label small fw-semibold text-dark" for="chkUpdateChecklist">
                Tandai 9 item checklist pemeliharaan pada Kartu Kontrol sebagai <strong>Normal / Selesai</strong>
              </label>
            </div>

            <!-- Tombol Simpan -->
            <div class="d-grid gap-2">
              <button type="submit" class="btn btn-danger btn-lg fw-bold py-3 shadow" onclick="return confirm(\'Simpan hasil perbaikan dan selesaikan tindak lanjut temuan ini?\')">
                <i class="bi bi-save-fill me-2"></i> SIMPAN HASIL TINDAK LANJUT
              </button>
              <a class="btn btn-outline-secondary py-2" href="'.e(module_url('scan.php', ['t' => $token])).'">Batal</a>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script>
    function appendAction(text) {
      var box = document.getElementById("action_taken_box");
      if (!box) return;
      var cur = box.value.trim();
      if (cur === "") {
        box.value = text;
      } else if (cur.indexOf(text) === -1) {
        box.value = cur + ", " + text;
      }
      box.focus();
    }
    </script>';

    render_page('Tindak Lanjut Temuan - ' . ($asset['kode_inventaris'] ?? 'Aset'), $body, '', '', false);
    exit;
}

$currentMonthLog = get_asset_maintenance_status_month($assetId, $month, $year);
$pendingFinding = get_asset_active_finding($assetId);
$action = trim((string)($_GET['action'] ?? $_GET['amp;action'] ?? $_POST['action_type'] ?? ''));
$autoOpenLogin = trim((string)($_GET['open_login'] ?? ''));

// =========================================================================
// 3. TAMPILAN FORM CHECKLIST 9 ITEM (action = start ATAU form)
// =========================================================================
if ($action === 'start' || $action === 'form' || $action === 'ulang') {
    $fixedItems = get_fixed_checklists();
    $isUlang = ($action === 'ulang' || ($currentMonthLog && $action === 'start'));
    $enrolledTechs = get_enrolled_technicians(true);
    $hasEnrolledTechs = !empty($enrolledTechs);

    $itemIcons = [
        1 => 'bi-shield-check',
        2 => 'bi-arrow-repeat',
        3 => 'bi-trash3',
        4 => 'bi-keyboard',
        5 => 'bi-mouse',
        6 => 'bi-display',
        7 => 'bi-droplet-half',
        8 => 'bi-box-seam',
        9 => 'bi-printer',
    ];

    $itemCategoryLabels = [
        1 => 'Keamanan / Software',
        2 => 'Update Sistem',
        3 => 'Pembersihan Storage',
        4 => 'Hardware / Input',
        5 => 'Hardware / Input',
        6 => 'Hardware Utama',
        7 => 'Perangkat Printer',
        8 => 'Perangkat Printer',
        9 => 'Perangkat Printer',
    ];

    $defaultNotes = [
        1 => 'Bersih',
        2 => 'Sudah update',
        3 => 'Sudah dibersihkan',
        4 => 'Normal',
        5 => 'Normal',
        6 => 'Normal',
        7 => 'Normal',
        8 => 'Normal',
        9 => 'Normal',
    ];

    $itemTagSuggestions = [
        1 => ['Bersih', 'Scan Bersih', 'Ada Virus Dibersihkan', 'N/A'],
        2 => ['Sudah update', 'Update Terbaru', 'Gagal Update', 'N/A'],
        3 => ['Sudah dibersihkan', 'Temp Bersih', 'Disk Penuh', 'N/A'],
        4 => ['Normal', 'Tombol Lengket', 'Ada Tombol Rusak', 'N/A'],
        5 => ['Normal', 'Scroll Macet', 'Optik Lemah', 'N/A'],
        6 => ['Normal', 'Kipas Bunyi', 'Debu Tebal', 'Layar Bergaris', 'N/A'],
        7 => ['Normal', 'Tinta Cukup', 'Tinta Habis', 'N/A (Bukan Printer)'],
        8 => ['Normal', 'Cartridge OK', 'Perlu Ganti', 'N/A (Bukan Printer)'],
        9 => ['Normal', 'Nozzle Bersih', 'Nozzle Tersumbat', 'N/A (Bukan Printer)'],
    ];

    $checklistCardsHtml = '';
    foreach ($fixedItems as $num => $name) {
        $defNote = $defaultNotes[$num] ?? 'Normal';
        $icon = $itemIcons[$num] ?? 'bi-check2-circle';
        $catLabel = $itemCategoryLabels[$num] ?? 'Pemeliharaan';
        $tags = $itemTagSuggestions[$num] ?? ['Normal', 'Bersih', 'Bermasalah', 'N/A'];

        $tagChipsHtml = '';
        foreach ($tags as $tagText) {
            $tagChipsHtml .= '<button type="button" class="btn btn-tag-chip" onclick="setNote('.$num.', \''.e(addslashes($tagText)).'\')">'.e($tagText).'</button>';
        }

        $checklistCardsHtml .= '
        <div class="card p-3 mb-2 rounded-3 checklist-card border-success border-opacity-50" id="card_item_'.$num.'">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-2 py-1 rounded-pill">#'.$num.'</span>
              <div>
                <div class="d-flex align-items-center gap-2">
                  <i class="bi '.$icon.' text-primary fs-5"></i>
                  <strong class="text-dark fs-6">'.e($name).'</strong>
                </div>
                <small class="text-secondary" style="font-size: 0.78rem;">'.e($catLabel).'</small>
              </div>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-success bg-opacity-10 text-success fw-bold status-pill d-none d-sm-inline-block" id="pill_'.$num.'">✓ OK</span>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input chk-box" type="checkbox" role="switch" id="chk_'.$num.'" name="chk_'.$num.'" value="1" checked onchange="toggleItem('.$num.')">
              </div>
            </div>
          </div>
          
          <div class="mt-2 pt-2 border-top border-light">
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-pencil-square"></i></span>
              <input type="text" class="form-control border-start-0 note-input bg-white" id="notes_'.$num.'" name="notes_'.$num.'" value="'.e($defNote).'" placeholder="Catatan/keterangan...">
            </div>
            <div class="d-flex flex-wrap gap-1 mt-2">
              '.$tagChipsHtml.'
            </div>
          </div>
        </div>';
    }

    $techDefault = is_logged_in() ? current_user_name() : '';
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

    $formHeadStyle = '
    <style>
    .checklist-card {
      transition: all 0.2s ease-in-out;
      border: 1.5px solid #e2e8f0;
      background: #ffffff;
      box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .checklist-card.border-success {
      border-color: #10b981 !important;
      background-color: #f0fdf4 !important;
    }
    .checklist-card.border-warning {
      border-color: #f59e0b !important;
      background-color: #fffbeb !important;
    }
    .btn-tag-chip {
      font-size: 0.74rem;
      padding: 2px 9px;
      border-radius: 999px;
      background-color: #f1f5f9;
      border: 1px solid #cbd5e1;
      color: #334155;
      font-weight: 500;
      transition: all 0.15s ease;
      cursor: pointer;
    }
    .btn-tag-chip:hover {
      background-color: #2563eb;
      color: #ffffff;
      border-color: #2563eb;
      transform: translateY(-1px);
    }
    .form-switch .form-check-input {
      width: 2.85em;
      height: 1.5em;
      cursor: pointer;
    }
    .form-check-input:checked {
      background-color: #10b981;
      border-color: #10b981;
    }
    .quick-action-box {
      background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
      border: 1.5px solid #93c5fd;
    }
    .bio-scanner-wrapper {
      position: relative;
      width: 320px;
      height: 380px;
      max-width: 100%;
      border-radius: 20px;
      overflow: hidden;
      background: #0f172a;
      box-shadow: 0 10px 25px rgba(0,0,0,0.25);
      margin: 0 auto;
    }
    .bio-video-el {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transform: scaleX(-1);
    }
    .bio-canvas-el {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      transform: scaleX(-1);
    }
    .bio-face-oval {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 190px;
      height: 240px;
      border: 3px dashed #38bdf8;
      border-radius: 50%;
      box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.65);
      pointer-events: none;
      transition: all 0.3s ease;
    }
    .bio-face-oval.active {
      border-color: #22c55e;
      border-style: solid;
      box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.4), 0 0 25px rgba(34, 197, 94, 0.6);
    }
    .bio-scanline {
      position: absolute;
      top: 25%;
      left: calc(50% - 95px);
      width: 190px;
      height: 3px;
      background: linear-gradient(90deg, transparent, #38bdf8, transparent);
      animation: bioScanMove 2s infinite ease-in-out;
      pointer-events: none;
    }
    @keyframes bioScanMove {
      0% { top: 20%; opacity: 0; }
      50% { opacity: 1; }
      100% { top: 80%; opacity: 0; }
    }
    .bio-success-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(15, 23, 42, 0.92);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10;
    }
    </style>';

    $body = '
    <div class="row justify-content-center">
      <div class="col-md-9 col-lg-8">
        <div class="card p-3 p-md-4 border-0 shadow-sm mb-4">
          <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
            <div>
              <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-2 py-1 mb-1">Periode '.$monthName.' '.$year.'</span>
              <h4 class="fw-bold text-dark mb-0"><i class="bi bi-clipboard-check text-primary me-2"></i>'.$formTitle.'</h4>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="'.e(module_url('scan.php', ['t' => $token])).'"><i class="bi bi-x-lg"></i> Batal</a>
          </div>

          <!-- Ringkasan Perangkat -->
          <div class="p-3 bg-light rounded-3 mb-3 border">
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

          <form method="post" action="scan.php?t='.urlencode($token).'&action='.urlencode($action).'" id="formMaintenance">
            <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
            <input type="hidden" name="action" value="save_maintenance">
            <input type="hidden" name="action_type" value="'.e($action).'">
            <input type="hidden" name="t" value="'.e($token).'">
            <input type="hidden" name="maintenance_type" value="'.e($mTypeVal).'">

            <!-- Hidden Inputs Biometrik & Lokasi -->
            <input type="hidden" name="biometric_verified" id="bioVerified" value="0">
            <input type="hidden" name="biometric_confidence" id="bioConfidence" value="0">
            <input type="hidden" name="biometric_photo" id="bioPhoto" value="">
            <input type="hidden" name="latitude" id="bioLat" value="">
            <input type="hidden" name="longitude" id="bioLng" value="">

            <!-- Konfigurasi Jaringan & Printer Aset (Bisa diisi teknisi langsung) -->
            <div class="p-3 bg-white rounded-3 mb-3 border border-primary border-opacity-25 shadow-sm">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="fw-bold text-dark small"><i class="bi bi-hdd-network text-primary me-1"></i> Data Jaringan & Printer Aset</span>
                <span class="badge bg-primary bg-opacity-10 text-primary small">Sinkronisasi Otomatis ke Kartu</span>
              </div>
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label small text-muted mb-1">Alamat IP (IP Address)</label>
                  <input type="text" class="form-control form-control-sm font-monospace" name="ip_address" value="'.e($asset['ip_address'] ?? $asset['ip'] ?? '').'" placeholder="cth: 192.168.1.120">
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-muted mb-1">Printer Terhubung</label>
                  <input type="text" class="form-control form-control-sm" name="printer" value="'.e($asset['printer'] ?? '').'" placeholder="cth: Epson L3210 (USB / LAN)">
                </div>
              </div>
            </div>

            <!-- Quick Action Box 1-Klik -->
            <div class="quick-action-box p-3 rounded-3 shadow-sm mb-3">
              <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                <div>
                  <div class="fw-bold text-primary"><i class="bi bi-lightning-charge-fill text-warning me-1"></i> Aksi Cepat Teknisi</div>
                  <div class="text-secondary small">Isi otomatis seluruh item jika kondisi perangkat normal</div>
                </div>
                <div class="d-flex flex-wrap gap-2 w-100 w-sm-auto">
                  <button type="button" class="btn btn-success fw-bold shadow-sm flex-fill flex-sm-grow-0" id="btnQuickNormal" onclick="setAllNormal()">
                    <i class="bi bi-check2-all me-1"></i> ⚡ SEMUA NORMAL (1-KLIK)
                  </button>
                  <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAllCheck(false)" title="Kosongkan centang">
                    <i class="bi bi-dash-circle"></i> Reset
                  </button>
                </div>
              </div>
            </div>

            <!-- 1. 9 Items Checklist Modern Cards -->
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="fw-bold text-dark mb-0"><i class="bi bi-check2-square text-primary me-2"></i>9 Item Checklist Pemeliharaan:</h6>
              <span class="text-muted small">Sentuh switch untuk ubah status</span>
            </div>

            <div class="mb-4">
              '.$checklistCardsHtml.'
            </div>

            <!-- 2. Data Pelaksanaan Maintenance -->
            <h6 class="fw-bold text-dark mb-3 border-top pt-3"><i class="bi bi-person-badge text-primary me-2"></i>Data Pelaksanaan:</h6>
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label small fw-bold text-secondary">Tanggal Maintenance</label>
                <input type="date" class="form-control py-2" name="maintenance_date" value="'.$currentDateStr.'" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-bold text-secondary"><i class="bi bi-person me-1"></i>Petugas / Teknisi <span class="text-danger">*</span></label>
                <input type="text" class="form-control py-2" name="technician_name" id="technicianNameInput" list="listTeknisi" value="'.e($techDefault).'" placeholder="Ketik atau pilih nama petugas..." required>
                <datalist id="listTeknisi">'.$techOptions.'</datalist>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 mt-1">
                  <div class="form-text text-muted mb-0" style="font-size: 0.75rem;">Pilih nama atau ketik nama baru (otomatis terdaftar ke sistem).</div>
                  <a href="'.e(module_url('user_biometric_enroll.php', ['ret' => module_url('scan.php', ['t' => $token, 'action' => 'form'])])).'" class="badge bg-primary bg-opacity-10 text-primary text-decoration-none border border-primary border-opacity-25 py-1 px-2">
                    <i class="bi bi-person-plus-fill me-1"></i> + Daftar Teknisi / Face ID
                  </a>
                </div>
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
              <select class="form-select fw-bold py-2" name="status" id="selectStatus">
                <option value="Selesai" class="text-success" selected>✓ Selesai (Kondisi Normal & Berfungsi Baik)</option>
                <option value="Proses" class="text-warning">⏳ Proses (Sedang Ditangani / Butuh Waktu)</option>
                <option value="Perlu Perbaikan" class="text-danger">⚠️ Perlu Perbaikan (Ada Kerusakan / Perlu Sparepart)</option>
              </select>
            </div>

            <!-- Submit Button Area -->
            <div class="d-grid gap-2 pt-2">
              '.($hasEnrolledTechs ? '
              <button type="button" class="btn btn-primary btn-lg fw-bold py-3 shadow" id="btnSelesaiBio" onclick="openBiometricModal()">
                <i class="bi bi-person-bounding-box me-2"></i> SELESAI MAINTENANCE (SCAN WAJAH KAMERA)
              </button>
              <div class="d-flex justify-content-between align-items-center px-1">
                <button type="submit" class="btn btn-link btn-sm text-decoration-none text-muted p-0" onclick="return confirm(\'Simpan hasil checklist tanpa verifikasi biometrik wajah?\')">
                  <i class="bi bi-shield-slash me-1"></i> Simpan Manual (Bypass Biometrik)
                </button>
                <a class="btn btn-link btn-sm text-decoration-none text-secondary p-0" href="'.e(module_url('scan.php', ['t' => $token])).'">Batal</a>
              </div>
              ' : '
              <button type="submit" class="btn btn-success btn-lg fw-bold py-3 shadow" onclick="return confirm(\'Simpan hasil checklist maintenance sekarang?\')">
                <i class="bi bi-save-fill me-2"></i> SIMPAN MAINTENANCE
              </button>
              <div class="alert alert-light border py-2 px-3 small mb-0 d-flex align-items-center justify-content-between flex-wrap gap-2 text-muted">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-info-circle text-primary fs-5"></i>
                  <div>Belum ada biometrik teknisi yang disetujui Admin.</div>
                </div>
                <a href="'.e(module_url('user_biometric_enroll.php', ['ret' => module_url('scan.php', ['t' => $token, 'action' => 'form'])])).'" class="btn btn-sm btn-primary fw-bold">
                  <i class="bi bi-camera-fill me-1"></i> Daftarkan Wajah di HP
                </a>
              </div>
              <a class="btn btn-outline-secondary py-2" href="'.e(module_url('scan.php', ['t' => $token])).'">Batal</a>
              ').'
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal Biometric Face Recognition & Liveness Check -->
    <div class="modal fade" id="modalBiometricScan" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
          <div class="modal-header bg-dark text-white border-0 py-3">
            <div class="d-flex align-items-center gap-2">
              <div class="p-2 bg-primary bg-opacity-25 rounded-circle text-primary fs-5">
                <i class="bi bi-shield-check"></i>
              </div>
              <div>
                <h6 class="modal-title fw-bold mb-0">Biometric Face Recognition & Liveness</h6>
                <div class="text-white-50 small" style="font-size: 0.72rem;">Verifikasi kehadiran teknisi resmi PT BPR Mitratama Arthabuana</div>
              </div>
            </div>
            <button type="button" class="btn-close btn-close-white" onclick="closeBiometricModal()"></button>
          </div>

          <div class="modal-body p-3 p-md-4 text-center bg-light">
            <!-- Shortcut Apple Face ID di Modal -->
            <div id="modalNativeFaceIdAlert" class="alert alert-dark border-0 p-2 mb-3 d-none d-flex align-items-center justify-content-between rounded-3">
              <div class="d-flex align-items-center gap-2 text-start">
                <i class="bi bi-apple text-primary fs-5"></i>
                <div>
                  <div class="fw-bold text-white small">Face ID Bawaan iPhone</div>
                  <div class="text-white-50" style="font-size: 0.7rem;">Paling cepat (< 0.5 detik) tanpa kamera</div>
                </div>
              </div>
              <button type="button" class="btn btn-primary btn-sm fw-bold rounded-pill px-3" onclick="verifyWithNativeFaceId()">Verifikasi</button>
            </div>

            <!-- Scanner Box -->
            <div class="bio-scanner-wrapper mb-3">
              <video id="bioVideo" class="bio-video-el" autoplay playsinline webkit-playsinline muted></video>
              <canvas id="bioCanvas" class="bio-canvas-el"></canvas>
              <div id="bioFaceOval" class="bio-face-oval"></div>
              <div id="bioScanLine" class="bio-scanline d-none"></div>

              <!-- Success Overlay -->
              <div id="bioSuccessOverlay" class="bio-success-overlay d-none">
                <div class="text-center p-3">
                  <div class="display-3 text-success mb-2">
                    <i class="bi bi-check-circle-fill"></i>
                  </div>
                  <h5 class="fw-bold text-white mb-1" id="bioMatchedName">Nama Teknisi</h5>
                  <div class="badge bg-success bg-opacity-75 fs-6 mb-2" id="bioMatchConfidence">98% Cocok</div>
                  <div class="text-white-50 small">Verifikasi Liveness & Identitas Berhasil!<br>Menyimpan data checklist...</div>
                </div>
              </div>
            </div>

            <!-- Status & Instruction Badge -->
            <div id="bioStatusBox" class="alert alert-info py-2 px-3 small fw-semibold mb-2">
              <span class="spinner-border spinner-border-sm me-2 text-primary"></span>
              Menyiapkan kamera & modul AI...
            </div>

            <!-- Steady Hold Progress Bar -->
            <div class="progress mb-2 d-none" id="bioHoldProgress" style="height: 6px;">
              <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" id="bioHoldProgressBar" style="width: 0%"></div>
            </div>

            <!-- Tombol Aksi Cepat Verifikasi Instan (Muncul saat wajah terdeteksi) -->
            <div class="mb-2 d-none" id="bioInstantVerifyBox">
              <button type="button" class="btn btn-warning text-dark btn-sm w-100 fw-bold py-2 shadow-sm rounded-pill" onclick="triggerInstantBioVerify()">
                <i class="bi bi-lightning-charge-fill me-1"></i> Wajah Terdeteksi &mdash; Tekan untuk Verifikasi Langsung
              </button>
            </div>

            <!-- Liveness Checklist Pills -->
            <div class="d-flex justify-content-center gap-2 mb-3">
              <span id="pillFaceDetected" class="badge bg-secondary bg-opacity-25 text-dark small py-2 px-3 border">
                <i class="bi bi-person me-1"></i> Wajah
              </span>
              <span id="pillLivenessBlink" class="badge bg-secondary bg-opacity-25 text-dark small py-2 px-3 border">
                <i class="bi bi-eye me-1"></i> Kedip (Liveness)
              </span>
              <span id="pillMatchId" class="badge bg-secondary bg-opacity-25 text-dark small py-2 px-3 border">
                <i class="bi bi-check-all me-1"></i> Identitas Cocok
              </span>
            </div>

            <!-- Link Pendaftaran Wajah di HP jika belum terdaftar -->
            <div class="mb-3">
              <a href="'.e(module_url('user_biometric_enroll.php', ['ret' => module_url('scan.php', ['t' => $token, 'action' => 'form'])])).'" class="btn btn-outline-primary btn-sm w-100 rounded-pill">
                <i class="bi bi-phone-fill me-1"></i> Wajah Belum Terdaftar? Daftarkan di HP Sekarang
              </a>
            </div>

            <div class="d-flex justify-content-between align-items-center">
              <button type="button" class="btn btn-outline-secondary btn-sm" onclick="closeBiometricModal()">
                Batal
              </button>
              <button type="button" class="btn btn-link btn-sm text-danger text-decoration-none p-0" onclick="bypassBiometricAndSubmit()">
                <i class="bi bi-exclamation-octagon me-1"></i> Lewati & Simpan Manual
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>';

    $techsJson = json_encode($enrolledTechs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    $formScript = <<<'HTML'
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.min.js"></script>
    <script>
    const defaultItemNotes = {
      1: "Bersih",
      2: "Sudah update",
      3: "Sudah dibersihkan",
      4: "Normal",
      5: "Normal",
      6: "Normal",
      7: "Normal",
      8: "Normal",
      9: "Normal"
    };

    function toggleItem(num) {
      const chk = document.getElementById("chk_" + num);
      const card = document.getElementById("card_item_" + num);
      const pill = document.getElementById("pill_" + num);
      if (!chk || !card) return;

      if (chk.checked) {
        card.classList.remove("border-warning");
        card.classList.add("border-success");
        if (pill) {
          pill.className = "badge bg-success bg-opacity-10 text-success fw-bold status-pill d-none d-sm-inline-block";
          pill.innerHTML = "✓ OK";
        }
      } else {
        card.classList.remove("border-success");
        card.classList.add("border-warning");
        if (pill) {
          pill.className = "badge bg-warning text-dark fw-bold status-pill d-none d-sm-inline-block";
          pill.innerHTML = "⚠️ Perlu Dicek";
        }
      }
    }

    function setNote(num, text) {
      const input = document.getElementById("notes_" + num);
      if (input) {
        input.value = text;
        input.focus();
      }
    }

    function setAllNormal() {
      for (let i = 1; i <= 9; i++) {
        const chk = document.getElementById("chk_" + i);
        const note = document.getElementById("notes_" + i);
        if (chk) {
          chk.checked = true;
          toggleItem(i);
        }
        if (note && defaultItemNotes[i]) {
          note.value = defaultItemNotes[i];
        }
      }
      const selectStatus = document.getElementById("selectStatus");
      if (selectStatus) {
        selectStatus.value = "Selesai";
      }

      const btn = document.getElementById("btnQuickNormal");
      if (btn) {
        const orig = btn.innerHTML;
        btn.innerHTML = "<i class=\"bi bi-check-circle-fill me-1\"></i> Terisi Normal!";
        btn.classList.remove("btn-success");
        btn.classList.add("btn-dark");
        setTimeout(() => {
          btn.innerHTML = orig;
          btn.classList.remove("btn-dark");
          btn.classList.add("btn-success");
        }, 1200);
      }
    }

    function setAllCheck(val) {
      for (let i = 1; i <= 9; i++) {
        const chk = document.getElementById("chk_" + i);
        if (chk) {
          chk.checked = val;
          toggleItem(i);
        }
      }
    }
HTML;

    $formScript .= "\n    window.__ENROLLED_TECHS = " . $techsJson . ";\n";

    $formScript .= <<<'HTML'

    // Pre-parse vektor teknisi satu kali saat halaman dimuat (menghilangkan overhead JSON.parse di loop per-frame)
    const parsedEnrolledTechs = (Array.isArray(window.__ENROLLED_TECHS) ? window.__ENROLLED_TECHS : []).map(t => {
      let desc = t.descriptor || t.face_descriptor;
      if (typeof desc === "string") {
        try { desc = JSON.parse(desc); } catch (e) { desc = null; }
      }
      return {
        id: t.id,
        nama: t.nama,
        descriptor: (Array.isArray(desc) && desc.length >= 64) ? new Float32Array(desc) : null
      };
    }).filter(t => t.descriptor !== null);

    let bioModelsLoaded = false;
    let bioModelsLoading = false;
    let bioVideoStream = null;
    let bioTrackingTimer = null;
    let bioBlinkDetected = false;
    let bioLastEyeState = "open";
    let bioModalInstance = null;
    let bioCompleted = false;
    let bioFaceHoldFrames = 0;
    const HOLD_FRAMES_REQUIRED = 12; // ~0.7-0.9 detik tahan posisi wajah untuk auto-pass tanpa harus kedip

    const MODEL_URL = "https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/";

    // Otomatis minta izin lokasi GPS di latar belakang
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          const latEl = document.getElementById("bioLat");
          const lngEl = document.getElementById("bioLng");
          if (latEl) latEl.value = pos.coords.latitude;
          if (lngEl) lngEl.value = pos.coords.longitude;
        },
        (err) => console.log("GPS Notice:", err.message),
        { enableHighAccuracy: true, timeout: 6000, maximumAge: 60000 }
      );
    }

    async function loadBioModels() {
      if (bioModelsLoaded) return true;
      if (bioModelsLoading) return true;
      bioModelsLoading = true;
      try {
        const statusBox = document.getElementById("bioStatusBox");
        if (statusBox && !bioCompleted) {
          statusBox.className = "alert alert-info py-2 px-3 small fw-semibold mb-2";
          statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-primary"></span> Menyiapkan modul AI GPU...';
        }
        if (typeof faceapi !== "undefined" && faceapi.tf) {
          try {
            await faceapi.tf.setBackend("webgl");
            await faceapi.tf.ready();
          } catch(e) {}
        }
        await Promise.all([
          faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
          faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_URL).catch(() => faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL)),
          faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
        ]);
        bioModelsLoaded = true;
        bioModelsLoading = false;
        if (statusBox && !bioCompleted) {
          statusBox.className = "alert alert-success py-2 px-3 small fw-semibold mb-2";
          statusBox.innerHTML = '<i class="bi bi-check-circle me-1"></i> Modul AI GPU siap. Posisikan wajah di oval.';
        }
        return true;
      } catch (err) {
        console.error("Gagal memuat modul face-api:", err);
        bioModelsLoading = false;
        const statusBox = document.getElementById("bioStatusBox");
        if (statusBox) {
          statusBox.className = "alert alert-danger py-2 px-3 small mb-2";
          statusBox.innerHTML = '<i class="bi bi-x-circle me-1"></i> Gagal memuat modul AI. Periksa koneksi internet.';
        }
        return false;
      }
    }

    function openBiometricModal() {
      const modalEl = document.getElementById("modalBiometricScan");
      if (!modalEl) return;

      bioCompleted = false;
      bioBlinkDetected = false;
      bioLastEyeState = "open";
      bioFaceHoldFrames = 0;

      // Reset UI pills & elements
      document.getElementById("pillFaceDetected").className = "badge bg-secondary bg-opacity-25 text-dark small py-2 px-3 border";
      document.getElementById("pillLivenessBlink").className = "badge bg-secondary bg-opacity-25 text-dark small py-2 px-3 border";
      document.getElementById("pillMatchId").className = "badge bg-secondary bg-opacity-25 text-dark small py-2 px-3 border";
      document.getElementById("bioSuccessOverlay").classList.add("d-none");
      document.getElementById("bioScanLine").classList.add("d-none");
      document.getElementById("bioFaceOval").classList.remove("active");

      const holdProgress = document.getElementById("bioHoldProgress");
      if (holdProgress) holdProgress.classList.add("d-none");
      const holdBar = document.getElementById("bioHoldProgressBar");
      if (holdBar) holdBar.style.width = "0%";
      const instantBox = document.getElementById("bioInstantVerifyBox");
      if (instantBox) instantBox.classList.add("d-none");

      bioModalInstance = new bootstrap.Modal(modalEl);
      bioModalInstance.show();

      // Mulai kamera dan pemuatan model secara PARALEL (tidak saling memblokir)
      loadBioModels();
      startBioCamera();
    }

    function closeBiometricModal() {
      bioCompleted = true;
      if (bioTrackingTimer) {
        cancelAnimationFrame(bioTrackingTimer);
        bioTrackingTimer = null;
      }
      if (bioVideoStream) {
        try {
          bioVideoStream.getTracks().forEach(t => t.stop());
        } catch(e) {}
        bioVideoStream = null;
      }
      if (bioModalInstance) {
        bioModalInstance.hide();
      }
    }

    async function startBioCamera() {
      const video = document.getElementById("bioVideo");
      const statusBox = document.getElementById("bioStatusBox");

      try {
        if (statusBox && !bioModelsLoaded) {
          statusBox.className = "alert alert-info py-2 px-3 small fw-semibold mb-2";
          statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-info"></span> Mengaktifkan kamera depan HP...';
        }

        video.setAttribute("playsinline", "");
        video.setAttribute("webkit-playsinline", "");

        bioVideoStream = await navigator.mediaDevices.getUserMedia({
          video: {
            facingMode: "user",
            width: { ideal: 640 },
            height: { ideal: 480 },
            frameRate: { ideal: 30, max: 30 }
          },
          audio: false
        });
        video.srcObject = bioVideoStream;
        await video.play();

        document.getElementById("bioScanLine").classList.remove("d-none");
        if (statusBox) {
          statusBox.className = "alert alert-primary py-2 px-3 small fw-semibold mb-2";
          statusBox.innerHTML = '<i class="bi bi-person-bounding-box me-1"></i> Arahkan wajah ke lingkaran oval (Tahan 1 detik atau kedipkan mata).';
        }

        startBioTracking();
      } catch (err) {
        console.error("Akses kamera gagal:", err);
        if (statusBox) {
          statusBox.className = "alert alert-danger py-2 px-3 small mb-2";
          statusBox.innerHTML = '<i class="bi bi-camera-video-off me-1"></i> Kamera tidak dapat diakses. Berikan izin di browser atau pilih "Lewati & Simpan Manual".';
        }
      }
    }

    function calcEAR(eye) {
      const distA = Math.hypot(eye[1].x - eye[5].x, eye[1].y - eye[5].y);
      const distB = Math.hypot(eye[2].x - eye[4].x, eye[2].y - eye[4].y);
      const distC = Math.hypot(eye[0].x - eye[3].x, eye[0].y - eye[3].y);
      return (distA + distB) / (2.0 * distC);
    }

    function calcEuclidean(a, b) {
      if (!a || !b || a.length !== b.length) return 999;
      let s = 0;
      for (let i = 0; i < a.length; i++) {
        const d = a[i] - b[i];
        s += d * d;
      }
      return Math.sqrt(s);
    }

    function captureBioSnapshot(videoEl) {
      try {
        if (!videoEl || !videoEl.videoWidth || !videoEl.videoHeight) return "";
        const c = document.createElement("canvas");
        c.width = 120;
        c.height = 120;
        const ctx = c.getContext("2d");
        const s = Math.min(videoEl.videoWidth, videoEl.videoHeight);
        const sx = (videoEl.videoWidth - s) / 2;
        const sy = (videoEl.videoHeight - s) / 2;
        ctx.translate(120, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(videoEl, sx, sy, s, s, 0, 0, 120, 120);
        return c.toDataURL("image/jpeg", 0.7);
      } catch (err) {
        console.warn("Capture snapshot err:", err);
        return "";
      }
    }

    function findBestMatch(queryVec) {
      let bestDist = 999;
      let bestTech = null;
      for (let i = 0; i < parsedEnrolledTechs.length; i++) {
        const t = parsedEnrolledTechs[i];
        const dist = calcEuclidean(queryVec, t.descriptor);
        if (dist < bestDist) {
          bestDist = dist;
          bestTech = t;
        }
      }
      return { tech: bestTech, dist: bestDist };
    }

    function finalizeVerification(tech, dist, videoEl) {
      bioCompleted = true;
      document.getElementById("pillMatchId").className = "badge bg-success text-white small py-2 px-3 border";
      document.getElementById("pillLivenessBlink").className = "badge bg-success text-white small py-2 px-3 border";

      let conf = Math.round((1.0 - (dist / 0.60)) * 100);
      if (conf > 99) conf = 99;
      if (conf < 75) conf = 75;

      document.getElementById("bioVerified").value = "1";
      document.getElementById("bioConfidence").value = conf;
      document.getElementById("bioPhoto").value = captureBioSnapshot(videoEl);

      const techInput = document.getElementById("technicianNameInput");
      if (techInput) techInput.value = tech.nama;

      document.getElementById("bioMatchedName").textContent = tech.nama;
      document.getElementById("bioMatchConfidence").textContent = conf + "% Cocok (Terverifikasi)";
      document.getElementById("bioSuccessOverlay").classList.remove("d-none");

      const statusBox = document.getElementById("bioStatusBox");
      if (statusBox) {
        statusBox.className = "alert alert-success py-2 px-3 small fw-bold mb-2";
        statusBox.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Wajah Dikenali: <strong>' + tech.nama + '</strong>! Menyimpan...';
      }

      if (bioVideoStream) {
        try { bioVideoStream.getTracks().forEach(t => t.stop()); } catch(e) {}
      }

      setTimeout(() => {
        const formEl = document.getElementById("formMaintenance");
        if (formEl) formEl.submit();
      }, 400);
    }

    async function triggerInstantBioVerify() {
      const video = document.getElementById("bioVideo");
      const statusBox = document.getElementById("bioStatusBox");
      if (!video || !video.videoWidth || bioCompleted) return;

      statusBox.className = "alert alert-warning py-2 px-3 small fw-bold mb-2";
      statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-warning"></span> Memproses verifikasi instan...';

      const useTinyLandmarks = faceapi.nets.faceLandmark68TinyNet && faceapi.nets.faceLandmark68TinyNet.isLoaded;
      const fastDetectorOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 160, scoreThreshold: 0.3 });

      try {
        const detection = await faceapi.detectSingleFace(video, fastDetectorOptions)
          .withFaceLandmarks(useTinyLandmarks)
          .withFaceDescriptor();

        if (detection && detection.descriptor) {
          const matchResult = findBestMatch(detection.descriptor);
          if (matchResult.tech && matchResult.dist <= 0.55) {
            finalizeVerification(matchResult.tech, matchResult.dist, video);
            return;
          }
        }
        statusBox.className = "alert alert-danger py-2 px-3 small fw-bold mb-2";
        statusBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Wajah belum cocok dengan teknisi terdaftar. Posisikan wajah tegak.';
      } catch (err) {
        console.warn("Instant verify error:", err);
      }
    }

    let isTrackingFrame = false;
    function startBioTracking() {
      if (bioTrackingTimer) cancelAnimationFrame(bioTrackingTimer);
      const video = document.getElementById("bioVideo");
      const oval = document.getElementById("bioFaceOval");
      const statusBox = document.getElementById("bioStatusBox");
      const pillFace = document.getElementById("pillFaceDetected");
      const pillBlink = document.getElementById("pillLivenessBlink");
      const holdProgress = document.getElementById("bioHoldProgress");
      const holdProgressBar = document.getElementById("bioHoldProgressBar");
      const instantBox = document.getElementById("bioInstantVerifyBox");

      const useTinyLandmarks = faceapi.nets.faceLandmark68TinyNet && faceapi.nets.faceLandmark68TinyNet.isLoaded;
      // inputSize 160 sangat cepat dan stabil pada GPU iPhone & Android
      const fastDetectorOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 160, scoreThreshold: 0.30 });

      async function trackingLoop() {
        if (bioCompleted || !video || !video.videoWidth || video.paused || video.ended) {
          if (!bioCompleted) {
            bioTrackingTimer = requestAnimationFrame(trackingLoop);
          }
          return;
        }

        if (!bioModelsLoaded) {
          bioTrackingTimer = requestAnimationFrame(trackingLoop);
          return;
        }

        if (!isTrackingFrame) {
          isTrackingFrame = true;
          try {
            // TAHAP 1: Deteksi Wajah & Landmarks
            const detection = await faceapi.detectSingleFace(video, fastDetectorOptions).withFaceLandmarks(useTinyLandmarks);

            if (detection) {
              oval.classList.add("active");
              pillFace.className = "badge bg-success text-white small py-2 px-3 border";
              if (instantBox) instantBox.classList.remove("d-none");

              // Cek Kedipan Mata (EAR) dengan batas toleransi adaptif
              const landmarks = detection.landmarks;
              const leftEye = landmarks.getLeftEye();
              const rightEye = landmarks.getRightEye();
              const avgEAR = (calcEAR(leftEye) + calcEAR(rightEye)) / 2.0;

              if (avgEAR < 0.26) {
                bioLastEyeState = "closed";
              } else if (avgEAR > 0.27 && bioLastEyeState === "closed") {
                bioBlinkDetected = true;
              }

              // Akumulasi Steady-Hold (Auto-Pass jika wajah dipertahankan 1 detik)
              bioFaceHoldFrames++;
              if (holdProgress) holdProgress.classList.remove("d-none");
              const progressPct = Math.min(100, Math.round((bioFaceHoldFrames / HOLD_FRAMES_REQUIRED) * 100));
              if (holdProgressBar) holdProgressBar.style.width = progressPct + "%";

              const passedLiveness = bioBlinkDetected || (bioFaceHoldFrames >= HOLD_FRAMES_REQUIRED);

              if (passedLiveness) {
                pillBlink.className = "badge bg-success text-white small py-2 px-3 border";
                statusBox.className = "alert alert-info py-2 px-3 small fw-bold mb-2";
                statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-primary"></span> Wajah stabil! Mencocokkan data teknisi...';

                // TAHAP 2: Hitung Descriptor & Cocokkan dengan data teknisi
                const fullDetection = await faceapi.detectSingleFace(video, fastDetectorOptions)
                  .withFaceLandmarks(useTinyLandmarks)
                  .withFaceDescriptor();

                if (fullDetection && fullDetection.descriptor) {
                  const matchResult = findBestMatch(fullDetection.descriptor);

                  // Threshold toleransi pencocokan Euclidean (<= 0.54)
                  if (matchResult.tech && matchResult.dist <= 0.54) {
                    finalizeVerification(matchResult.tech, matchResult.dist, video);
                    return;
                  } else {
                    statusBox.className = "alert alert-danger py-2 px-3 small fw-bold mb-2";
                    statusBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Wajah tidak cocok dengan teknisi terdaftar (' + (matchResult.dist !== 999 ? "Skor: " + matchResult.dist.toFixed(2) : "Belum ada") + ').';
                    bioFaceHoldFrames = 0;
                    bioBlinkDetected = false;
                    bioLastEyeState = "open";
                  }
                }
              } else {
                statusBox.className = "alert alert-warning py-2 px-3 small fw-bold mb-2";
                statusBox.innerHTML = '<i class="bi bi-eye-fill me-1"></i> Wajah Terdeteksi! <u>Tahan posisi 1 detik</u> atau <u>kedipkan mata</u>...';
              }
            } else {
              oval.classList.remove("active");
              bioFaceHoldFrames = Math.max(0, bioFaceHoldFrames - 2);
              if (holdProgressBar) holdProgressBar.style.width = "0%";
              statusBox.className = "alert alert-secondary py-2 px-3 small mb-2";
              statusBox.innerHTML = '<i class="bi bi-person-bounding-box me-1"></i> Posisikan wajah tepat di dalam lingkaran oval...';
            }
          } catch(e) {
            console.warn("Tracking error:", e);
          } finally {
            isTrackingFrame = false;
          }
        }

        if (!bioCompleted) {
          bioTrackingTimer = requestAnimationFrame(trackingLoop);
        }
      }

      bioTrackingTimer = requestAnimationFrame(trackingLoop);
    }

    function bypassBiometricAndSubmit() {
      if (confirm("Simpan hasil checklist tanpa verifikasi biometrik wajah?")) {
        closeBiometricModal();
        document.getElementById("bioVerified").value = "0";
        const formEl = document.getElementById("formMaintenance");
        if (formEl) formEl.submit();
      }
    }

    // Preload modul AI di background saat halaman dimuat agar saat klik "Selesai Maintenance" langsung 0 detik instan
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", () => {
        setTimeout(loadBioModels, 300);
      });
    } else {
      setTimeout(loadBioModels, 300);
    }
    </script>
HTML;

    render_page($formTitle, $body, $formHeadStyle, $formScript, false);
    exit;
}

// =========================================================================
// 4. TAMPILAN UTAMA: DETAIL PERANGKAT & KARTU KONTROL CHECKLIST 12 BULAN
// =========================================================================

// Histori & Yearly Card Matrix
$historyList = get_asset_maintenance_history($assetId);
$cardMatrix = get_asset_yearly_card_matrix($assetId, $year);
$loggedIn = is_logged_in();

// Status Bulan Berjalan
if ($currentMonthLog) {
    $cDate = $currentMonthLog['maintenance_date'] ?? date('Y-m-d');
    $cTech = $currentMonthLog['technician_name'] ?? 'Teknisi';
    $cStatus = $currentMonthLog['status'] ?? 'Selesai';
    $cLogId = (int)($currentMonthLog['id'] ?? 0);
    $cFindings = $currentMonthLog['findings'] ?? '';
    $cRecom = $currentMonthLog['recommendation'] ?? '';

    $hasActiveIssue = ($pendingFinding || in_array(strtolower($cStatus), ['temuan', 'perlu perbaikan', 'perlu tindak lanjut', 'proses'], true));
    $cardBg = $hasActiveIssue ? 'bg-danger bg-opacity-10 border-danger' : 'bg-success bg-opacity-10 border-success';
    $cardTitleColor = $hasActiveIssue ? 'text-danger' : 'text-success';

    $btnTindakLanjut = $hasActiveIssue
        ? '<a class="btn btn-danger fw-bold px-3 py-2 shadow-sm" href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'tindak_lanjut'])).'"><i class="bi bi-tools me-1"></i> TINDAK LANJUTI SEKARANG</a>'
        : '';

    $badgeColor = ($cStatus === 'Temuan' || $cStatus === 'Perlu Perbaikan') ? 'danger' : ($cStatus === 'Proses' ? 'warning text-dark' : 'success');
    $badgeIcon = ($cStatus === 'Temuan' || $cStatus === 'Perlu Perbaikan') ? 'bi-exclamation-triangle-fill' : ($cStatus === 'Proses' ? 'bi-hourglass-split' : 'bi-check-circle-fill');

    $btnDetail = $cLogId > 0
        ? '<a class="btn btn-primary fw-semibold" href="'.e(module_url('maintenance_detail.php', ['id' => $cLogId])).'"><i class="bi bi-file-earmark-text me-1"></i> DETAIL LENGKAP AUDIT</a>'
        : '';

    $btnUlang = '<a class="btn btn-outline-primary fw-semibold" href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'ulang'])).'"><i class="bi bi-arrow-repeat me-1"></i> MAINTENANCE ULANG</a>';

    $statusCardHtml = '
    <div class="card border-0 shadow-sm mb-4 '.$cardBg.' border-start border-4 p-3 p-md-4">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="'.$cardTitleColor.' fw-bold fs-6"><i class="bi bi-calendar-check-fill me-1"></i> STATUS BULAN BERJALAN:</span>
        <span class="badge bg-'.$badgeColor.' px-3 py-2 fs-6"><i class="bi '.$badgeIcon.' me-1"></i> '.e($cStatus).'</span>
      </div>
      <h5 class="fw-bold text-dark mb-1">Periode: '.$monthName.' '.$year.'</h5>
      <p class="text-secondary small mb-2">Perangkat ini <strong>sudah dilakukan maintenance</strong> pada <strong>'.e(format_id_date($cDate)).'</strong> oleh <strong>'.e($cTech).'</strong>.</p>
      
      '.($cFindings !== '' ? '<div class="alert alert-danger py-2 px-3 small my-2"><strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Temuan:</strong> '.e($cFindings).'</div>' : '').'
      '.($cRecom !== '' ? '<div class="alert alert-info py-2 px-3 small my-2"><strong><i class="bi bi-lightbulb-fill me-1"></i>Rekomendasi:</strong> '.e($cRecom).'</div>' : '').'

      <div class="d-flex flex-wrap gap-2 mt-3 pt-2">
        '.$btnTindakLanjut.'
        '.$btnUlang.'
        '.$btnDetail.'
      </div>
    </div>';
} else {
    $btnStartAction = '<a class="btn btn-success btn-lg fw-bold py-3 px-4 shadow-sm w-100" href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'start'])).'">
      <i class="bi bi-play-circle-fill me-2"></i> MULAI MAINTENANCE SEKARANG
    </a>
    <div class="text-center mt-2"><small class="text-muted"><i class="bi bi-check2-circle text-success me-1"></i>Cukup pilih/masukkan nama petugas saat mengisi checklist (tidak wajib login).</small></div>';

    $statusCardHtml = '
    <div class="card border-0 shadow-sm mb-4 bg-danger bg-opacity-10 border-start border-danger border-4 p-3 p-md-4">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-danger fw-bold fs-6"><i class="bi bi-exclamation-circle-fill me-1"></i> STATUS BULAN BERJALAN:</span>
        <span class="badge bg-danger px-3 py-2 fs-6"><i class="bi bi-x-circle-fill me-1"></i> Belum Maintenance</span>
      </div>
      <h5 class="fw-bold text-dark mb-1">Periode: '.$monthName.' '.$year.'</h5>
      <p class="text-secondary small mb-3">Perangkat ini belum dilakukan pemeliharaan hardware & OS untuk bulan ini.</p>
      
      '.$btnStartAction.'
    </div>';
}

$pendingAlertHtml = '';
if ($pendingFinding) {
    $pendingAlertHtml = '
    <div class="card border-0 shadow-sm mb-4 border-start border-danger border-4 p-3 p-md-4" style="background-color: #fff5f5;">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-danger fw-bold fs-6"><i class="bi bi-exclamation-triangle-fill me-1"></i> PERANGKAT INI MEMBUTUHKAN TINDAK LANJUT TEKNISI!</span>
        <span class="badge bg-danger px-3 py-2 fs-6"><i class="bi bi-tools me-1"></i> '.e($pendingFinding['status']).'</span>
      </div>
      <h5 class="fw-bold text-dark mb-1">Temuan Kerusakan: <span class="text-danger">"'.e($pendingFinding['finding']).'"</span></h5>
      <p class="text-secondary small mb-2">Dilaporkan pada <strong>'.e(format_id_date($pendingFinding['date'])).'</strong> oleh <strong>'.e($pendingFinding['reporter']).'</strong>.</p>
      '.(!empty($pendingFinding['recommendation']) && $pendingFinding['recommendation'] !== '-' ? '<div class="alert alert-white bg-white border py-2 px-3 small my-2 text-dark"><strong><i class="bi bi-lightbulb me-1"></i>Catatan Rekomendasi:</strong> '.e($pendingFinding['recommendation']).'</div>' : '').'
      <div class="d-flex flex-wrap gap-2 mt-3 pt-1">
        <a class="btn btn-danger btn-lg fw-bold py-2 px-4 shadow-sm" href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'tindak_lanjut'])).'">
          <i class="bi bi-tools me-2"></i> TINDAK LANJUTI / SELESAIKAN SEKARANG
        </a>
        '.(!empty($pendingFinding['log_id']) ? '<a class="btn btn-outline-secondary py-2" href="'.e(module_url('maintenance_detail.php', ['id' => (int)$pendingFinding['log_id']])).'"><i class="bi bi-file-earmark-text me-1"></i> Rincian Audit</a>' : '').'
      </div>
    </div>';
}

// Build Matrix Table Rows 12 Bulan (Persis Excel)
$cardMatrixRows = '';
for ($m = 1; $m <= 12; $m++) {
    $row = $cardMatrix[$m];
    $dateLabel = $row['date_str'];
    $isDone = $row['is_done'];
    $paraf = $isDone ? e($row['paraf']) : '&nbsp;';

    $cols1to9 = '';
    for ($num = 1; $num <= 9; $num++) {
        $chkVal = $row['checklists'][$num] ?? 0;
        if ($isDone) {
            $cols1to9 .= '<td style="border: 1px solid #000; width: 34px; padding: 3px 0;" class="fw-bold text-dark text-center">'.($chkVal ? '✓' : '-').'</td>';
        } else {
            $cols1to9 .= '<td style="border: 1px solid #000; width: 34px; padding: 3px 0;">&nbsp;</td>';
        }
    }

    $cardMatrixRows .= '
    <tr style="height: 27px;">
      <td style="border: 1px solid #000; width: 95px; padding: 3px 4px;" class="fw-bold text-dark text-center font-monospace">'.e($dateLabel).'</td>
      '.$cols1to9.'
      <td style="border: 1px solid #000; min-width: 90px; padding: 3px 6px;" class="text-center font-monospace small text-dark">'.$paraf.'</td>
    </tr>';
}

$userDisplay = !empty($asset['karyawan_nama']) && $asset['karyawan_nama'] !== '-' ? $asset['karyawan_nama'] : '';
$ipDisplay = !empty($asset['ip_address']) ? $asset['ip_address'] : (!empty($asset['ip']) ? $asset['ip'] : '');
$printerDisplay = !empty($asset['printer']) ? $asset['printer'] : '';

// Maintenance Terakhir
$lastMaintStr = !empty($historyList[0])
    ? format_id_date($historyList[0]['maintenance_date'] ?? '') . ' oleh ' . ($historyList[0]['technician_name'] ?? 'Teknisi')
    : 'Belum pernah';

$headStyle = '<style>
.excel-card-wrapper {
  background: #ffffff;
  border: 1.5px solid #000000;
  border-radius: 4px;
  padding: 16px 20px;
  font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
  box-sizing: border-box;
  max-width: 100%;
}
@media (max-width: 576px) {
  .excel-card-wrapper {
    padding: 12px 10px;
  }
}
.excel-header-table {
  width: 100%;
  max-width: 100%;
  border-collapse: collapse;
  margin-bottom: 12px;
  font-weight: bold;
  font-size: 11pt;
  color: #000000;
  table-layout: auto;
}
.excel-header-table td {
  padding: 3px 2px;
}
.excel-header-line {
  border-bottom: 1.5px solid #000000;
  width: 100%;
  padding-left: 6px;
  font-family: "Segoe UI", Arial, sans-serif;
  font-weight: 600;
  word-break: break-word;
}
.excel-grid-table {
  width: 100%;
  border-collapse: collapse;
  border: 1.5px solid #000000;
  margin-bottom: 10px;
  color: #000000;
}
.excel-grid-table th {
  background-color: #8ea9db !important;
  color: #000000 !important;
  border: 1.5px solid #000000 !important;
  font-weight: bold;
  font-size: 9.5pt;
  text-align: center;
  padding: 5px 2px;
}
.excel-grid-table td {
  border: 1px solid #000000;
  font-size: 9pt;
}
.excel-legend-box {
  font-size: 8.5pt;
  color: #000000;
  line-height: 1.45;
}
@media print {
  body { background: #fff !important; margin: 0 !important; }
  .no-print, nav, header { display: none !important; }
  .container, main.container { max-width: 100% !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
  .excel-card-wrapper { box-shadow: none !important; border: 1.5px solid #000 !important; margin: 0 auto !important; }
}
</style>';

$userStatusStrip = $loggedIn
    ? '<div class="d-flex flex-wrap justify-content-between align-items-center bg-white p-2 px-3 rounded-3 shadow-sm mb-3 border gap-2">
         <span class="small fw-bold text-dark"><i class="bi bi-person-check-fill text-success me-1"></i> Status Login: <span class="text-primary">'.e(current_user_name()).'</span></span>
         <div class="d-flex gap-2">
           <a href="'.e(module_url('dashboard.php')).'" class="btn btn-sm btn-outline-primary"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
           <a href="'.e(module_url('logout.php')).'" class="btn btn-sm btn-outline-danger" title="Keluar"><i class="bi bi-box-arrow-right"></i> Keluar</a>
         </div>
       </div>'
    : '<div class="d-flex flex-wrap justify-content-between align-items-center bg-white p-2 px-3 rounded-3 shadow-sm mb-3 border gap-2">
         <span class="small text-secondary"><i class="bi bi-info-circle text-primary me-1"></i> Mode Cek Info Perangkat</span>
         <a href="'.e(module_url('login.php')).'" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-in-right me-1"></i> Login Admin</a>
       </div>';

$body = '
<div class="row justify-content-center">
  <div class="col-md-11 col-lg-10">

    <!-- Status Strip Login / Tamu -->
    '.$userStatusStrip.'
    '.($error ? '<div class="alert alert-danger py-2 px-3 mb-3 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Gagal Menyimpan:</strong> '.e($error).'</div>' : '').'

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
      </div>
    </div>

    <!-- Alert Temuan Kerusakan / Tindak Lanjut -->
    '.$pendingAlertHtml.'

    <!-- Card Status Maintenance Bulan Berjalan -->
    '.$statusCardHtml.'

    <!-- KARTU KONTROL CHECKLIST 12 BULAN (PERSIS FORMAT GAMBAR) -->
    <div class="card p-3 p-md-4 border-0 shadow-sm mb-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-card-checklist text-primary me-2"></i>KARTU CHECKLIST MAINTENANCE IT '.$year.'</h5>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-outline-primary fw-semibold" target="_blank" href="'.e(module_url('print_card.php', ['id'=>$assetId, 'tahun'=>$year])).'"><i class="bi bi-printer me-1"></i> Cetak Kartu</a>
        </div>
      </div>

      <!-- Excel Style Card Box -->
      <div class="excel-card-wrapper">
        
        <table class="excel-header-table">
          <tr>
            <td style="width: 80px; white-space: nowrap;">NAMA</td>
            <td style="width: 15px; text-align: center;">:</td>
            <td class="excel-header-line">'.e($userDisplay).'</td>
          </tr>
          <tr>
            <td style="white-space: nowrap;">IP</td>
            <td style="text-align: center;">:</td>
            <td class="excel-header-line">'.e($ipDisplay).'</td>
          </tr>
          <tr>
            <td style="white-space: nowrap;">PRINTER</td>
            <td style="text-align: center;">:</td>
            <td class="excel-header-line">'.e($printerDisplay).'</td>
          </tr>
        </table>

        <!-- Table Matrix 12 Bulan -->
        <div class="table-responsive">
          <table class="excel-grid-table">
            <thead>
              <tr>
                <th style="width: 100px;">TANGGAL</th>
                <th style="width: 32px;">1</th>
                <th style="width: 32px;">2</th>
                <th style="width: 32px;">3</th>
                <th style="width: 32px;">4</th>
                <th style="width: 32px;">5</th>
                <th style="width: 32px;">6</th>
                <th style="width: 32px;">7</th>
                <th style="width: 32px;">8</th>
                <th style="width: 32px;">9</th>
                <th style="min-width: 90px;">PARAF</th>
              </tr>
            </thead>
            <tbody>
              '.$cardMatrixRows.'
            </tbody>
          </table>
        </div>

        <!-- Legend Keterangan 9 Item -->
        <div class="excel-legend-box">
          <div class="fw-bold mb-1">Ket</div>
          <div class="row g-1">
            <div class="col-md-4 col-12">
              <div>1. Scan Virus</div>
              <div>2. Update Anti Virus</div>
              <div>3. Deleting Temporary File</div>
            </div>
            <div class="col-md-4 col-12">
              <div>4. Cek Keyboard</div>
              <div>5. Cek Mouse</div>
              <div>6. Cek CPU & Monitor</div>
            </div>
            <div class="col-md-4 col-12">
              <div>7. Cek Tinta</div>
              <div>8. Cek Cartidge</div>
              <div>9. Cek Nozel</div>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div>
</div>';
render_page('Detail Perangkat · ' . ($asset['kode_inventaris'] ?? 'QR'), $body, $headStyle, '', false);

