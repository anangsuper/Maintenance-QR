<?php
require __DIR__ . '/bootstrap.php';
$isLoggedIn = is_logged_in();

$id = max(0, (int)($_GET['id'] ?? 0));
if ($id <= 0) {
    render_page('Parameter Tidak Valid', '<div class="alert alert-danger">ID log maintenance tidak valid.</div>');
    exit;
}

$error = '';
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Handle Edit / Update Checklist POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'update_detail')) {
    require_login();
    verify_csrf();

    $newStatus = trim((string)($_POST['status'] ?? 'Selesai'));
    $newFindings = trim((string)($_POST['findings'] ?? ''));
    $newRecommendation = trim((string)($_POST['recommendation'] ?? ''));
    $newDate = trim((string)($_POST['maintenance_date'] ?? ''));
    $newTime = trim((string)($_POST['maintenance_time'] ?? ''));
    $newTechName = trim((string)($_POST['technician_name'] ?? ''));

    $newChecklists = [];
    $fixedItems = get_fixed_checklists();
    foreach ($fixedItems as $num => $name) {
        $checked = !empty($_POST['chk_' . $num]) ? 1 : 0;
        $notes = trim((string)($_POST['notes_' . $num] ?? ''));
        $newChecklists[$num] = [
            'checked' => $checked,
            'notes' => $notes
        ];
    }

    $res = update_maintenance_detail($id, [
        'status' => $newStatus,
        'findings' => $newFindings,
        'recommendation' => $newRecommendation,
        'maintenance_date' => $newDate,
        'maintenance_time' => $newTime,
        'technician_name' => $newTechName,
        'checklists' => $newChecklists
    ]);

    if (!empty($res['success'])) {
        $_SESSION['flash'] = "Data maintenance #{$id} berhasil diperbarui.";
        header('Location: ' . module_url('maintenance_detail.php', ['id' => $id]));
        exit;
    } else {
        $error = $res['error'] ?? 'Gagal memperbarui data maintenance.';
    }
}

$detail = get_maintenance_detail($id);
if (!$detail) {
    render_page('Data Tidak Ditemukan', '<div class="alert alert-warning">Data rincian maintenance dengan ID #'.$id.' tidak ditemukan.</div>');
    exit;
}

$scan = $detail['scan'];
$asset = $detail['asset'];
$checklists = $detail['checklists'];

$status = $scan['status'] ?? 'Selesai';
$statusBadge = ($status === 'Temuan' || $status === 'Perlu Perbaikan')
    ? '<span class="badge bg-danger fs-6 px-3 py-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> Perlu Perbaikan</span>'
    : ($status === 'Proses'
        ? '<span class="badge bg-warning text-dark fs-6 px-3 py-2"><i class="bi bi-hourglass-split me-1"></i> Sedang Proses</span>'
        : '<span class="badge bg-success fs-6 px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i> Selesai</span>');

// Checklist 9 item table & Edit form inputs
$chkTableRows = '';
$editChecklistRows = '';
$totalChecked = 0;

foreach ($checklists as $num => $c) {
    $isDone = !empty($c['checked']);
    if ($isDone) $totalChecked++;

    $icon = $isDone
        ? '<span class="badge bg-success bg-opacity-10 text-success fs-6 fw-bold px-3 py-1 border border-success border-opacity-25"><i class="bi bi-check2-circle me-1"></i> OK / Normal</span>'
        : '<span class="badge bg-danger bg-opacity-10 text-danger fs-6 fw-bold px-3 py-1 border border-danger border-opacity-25"><i class="bi bi-exclamation-triangle me-1"></i> Belum Selesai</span>';

    $noteText = !empty($c['notes']) ? e($c['notes']) : ($isDone ? 'Normal' : '-');

    $chkTableRows .= '
    <tr class="'.($isDone ? '' : 'table-warning text-dark').'">
      <td class="text-center fw-bold text-secondary" style="width: 45px;">'.$num.'</td>
      <td class="fw-semibold text-dark">'.e($c['name']).'</td>
      <td class="text-center" style="width: 150px;">'.$icon.'</td>
      <td><span class="fw-semibold text-dark">'.$noteText.'</span></td>
    </tr>';

    $editChecklistRows .= '
    <div class="col-md-6 mb-3">
      <div class="p-3 border rounded-3 bg-light h-100">
        <div class="form-check form-switch mb-2">
          <input class="form-check-input" type="checkbox" role="switch" name="chk_'.$num.'" id="modal_chk_'.$num.'" value="1" '.($isDone ? 'checked' : '').'>
          <label class="form-check-label fw-bold text-dark" for="modal_chk_'.$num.'">'.$num.'. '.e($c['name']).'</label>
        </div>
        <input type="text" class="form-control form-control-sm" name="notes_'.$num.'" value="'.e($c['notes'] ?? ($isDone ? 'Normal' : '')).'" placeholder="Catatan / keterangan...">
      </div>
    </div>';
}

$dateStr = format_id_date($scan['maintenance_date'] ?? '');
$timeStr = substr((string)($scan['maintenance_time'] ?? ''), 0, 5);
$techName = $scan['technician_name'] ?? 'Teknisi';
$findings = $scan['findings'] ?? '-';
$recommendation = $scan['recommendation'] ?? '-';
$mType = $scan['source'] ?? $scan['maintenance_type'] ?? 'Maintenance';

$isBioVerified = !empty($scan['biometric_verified']);
$bioConfidence = (int)($scan['biometric_confidence'] ?? 0);
$bioPhoto = trim((string)($scan['biometric_photo'] ?? ''));
$lat = trim((string)($scan['latitude'] ?? ''));
$lng = trim((string)($scan['longitude'] ?? ''));

if ($isBioVerified) {
    $photoThumb = $bioPhoto !== ''
        ? '<img src="'.e($bioPhoto).'" class="rounded-circle border border-2 border-success shadow-sm" style="width: 56px; height: 56px; object-fit: cover;" alt="Foto Verifikasi">'
        : '<div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center border border-success" style="width: 56px; height: 56px;"><i class="bi bi-person-check fs-3"></i></div>';

    $gpsLink = ($lat !== '' && $lng !== '')
        ? '<a href="https://maps.google.com/?q='.e($lat).','.e($lng).'" target="_blank" class="badge bg-danger bg-opacity-10 text-danger border border-danger text-decoration-none py-2 px-3"><i class="bi bi-geo-alt-fill me-1"></i> Lokasi GPS: '.e(round((float)$lat, 4)).', '.e(round((float)$lng, 4)).' (Peta)</a>'
        : '<span class="text-muted small"><i class="bi bi-geo-alt me-1"></i> GPS: Tidak terdeteksi</span>';

    $bioAuditHtml = '
    <div class="p-3 rounded-3 mb-4 border border-success border-opacity-50 shadow-sm" style="background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);">
      <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
          '.$photoThumb.'
          <div>
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
              <span class="badge bg-success fw-bold py-1 px-2"><i class="bi bi-shield-fill-check me-1"></i> Terverifikasi Biometrik AI</span>
              <span class="badge bg-primary bg-opacity-10 text-primary border border-primary fw-bold py-1 px-2"><i class="bi bi-activity me-1"></i> Kecocokan: '.$bioConfidence.'%</span>
              <span class="badge bg-secondary bg-opacity-10 text-dark border py-1 px-2"><i class="bi bi-eye-fill text-success me-1"></i> Liveness OK</span>
            </div>
            <div class="text-dark small">Petugas <strong>'.e($techName).'</strong> terverifikasi melalui Biometric Face Recognition & Eye-Blink Liveness saat penyelesaian checklist.</div>
          </div>
        </div>
        <div>
          '.$gpsLink.'
        </div>
      </div>
    </div>';
} else {
    $bioAuditHtml = '
    <div class="p-2 px-3 rounded-3 mb-4 bg-light border d-flex align-items-center justify-content-between flex-wrap gap-2 small">
      <div class="text-secondary"><i class="bi bi-person-badge me-1"></i> Metode Verifikasi: <span class="fw-bold text-dark">Pencatatan Manual (Non-Biometrik)</span></div>
      <span class="badge bg-secondary bg-opacity-10 text-secondary border">Belum Pakai Scan Wajah</span>
    </div>';
}

$karyawanList = get_karyawan_list();
$techOptions = '';
foreach ($karyawanList as $k) {
    $kn = $k['nama_karyawan'] ?? $k['nama'] ?? '';
    if ($kn !== '') {
        $techOptions .= '<option value="'.e($kn).'">';
    }
}

$flashHtml = $flash ? '<div class="alert alert-success alert-dismissible fade show no-print"><i class="bi bi-check-circle-fill me-2"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';
$errorHtml = $error ? '<div class="alert alert-danger alert-dismissible fade show no-print"><i class="bi bi-exclamation-triangle-fill me-2"></i>'.e($error).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$editBtnTop = $isLoggedIn
    ? '<button type="button" class="btn btn-warning text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#editChecklistModal"><i class="bi bi-pencil-square me-1"></i> Edit Data & Checklist</button>'
    : '<a class="btn btn-outline-primary" href="'.e(module_url('login.php')).'"><i class="bi bi-box-arrow-in-right me-1"></i> Login untuk Edit</a>';

$backBtnTop = $isLoggedIn
    ? '<a class="btn btn-outline-secondary" href="'.e(module_url('audit.php')).'"><i class="bi bi-arrow-left me-1"></i> Riwayat Audit</a>'
    : (!empty($asset['token'])
        ? '<a class="btn btn-outline-secondary" href="'.e(module_url('scan.php', ['t' => $asset['token']])).'"><i class="bi bi-card-checklist me-1"></i> Kartu Perangkat</a>'
        : '<a class="btn btn-outline-secondary" href="javascript:history.back()"><i class="bi bi-arrow-left me-1"></i> Kembali</a>');

$body = '
'.$flashHtml.'
'.$errorHtml.'

<div class="row justify-content-center">
  <div class="col-lg-10 col-md-11">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 no-print">
      <div>
        <h3 class="fw-bold mb-1 text-dark"><i class="bi bi-file-earmark-medical text-primary me-2"></i>Rincian Hasil Maintenance #'.$id.'</h3>
        <div class="text-secondary">Pencatatan 9 checklist pemeliharaan perangkat IT resmi untuk keperluan audit & verifikasi.</div>
      </div>
      <div class="d-flex gap-2">
        '.$backBtnTop.'
        '.$editBtnTop.'
        <button class="btn btn-primary fw-semibold" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak Detail</button>
      </div>
    </div>

    <!-- Card 1: Detail Perangkat -->
    <div class="card p-4 border-0 shadow-sm mb-4">
      <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
        <h5 class="fw-bold text-primary mb-0"><i class="bi bi-laptop me-2"></i>1. DETAIL PERANGKAT</h5>
        <span class="badge bg-light text-dark border">ID Aset #'.(int)($asset['id'] ?? 0).'</span>
      </div>
      <div class="row g-3 small">
        <div class="col-md-4">
          <div class="text-secondary">Kode Inventaris:</div>
          <div class="fw-bold text-primary fs-6">'.e($asset['kode_inventaris'] ?? '-').'</div>
        </div>
        <div class="col-md-4">
          <div class="text-secondary">Serial Number:</div>
          <div class="fw-semibold text-dark">'.e($asset['serial_number'] ?? '-').'</div>
        </div>
        <div class="col-md-4">
          <div class="text-secondary">Jenis / Kategori:</div>
          <div class="fw-semibold text-dark">'.e($asset['kategori_nama'] ?? 'Perangkat IT').'</div>
        </div>
        <div class="col-md-4">
          <div class="text-secondary">Perangkat (Merk & Model):</div>
          <div class="fw-bold text-dark">'.e(asset_title($asset)).'</div>
        </div>
        <div class="col-md-4">
          <div class="text-secondary">Pemilik / User:</div>
          <div class="fw-bold text-dark"><i class="bi bi-person-circle text-primary me-1"></i>'.e($asset['karyawan_nama'] ?? '-').'</div>
        </div>
        <div class="col-md-4">
          <div class="text-secondary">Lokasi Cabang & Divisi:</div>
          <div class="fw-semibold text-dark">'.e($asset['cabang_nama'] ?? '-').' · '.e($asset['divisi_nama'] ?? '-').'</div>
        </div>
        <div class="col-md-4">
          <div class="text-secondary">Alamat IP (IP Address):</div>
          <div class="fw-bold font-monospace text-primary">'.e($asset['ip_address'] ?? $asset['ip'] ?? '-').'</div>
        </div>
        <div class="col-md-4">
          <div class="text-secondary">Printer Terhubung:</div>
          <div class="fw-semibold text-dark">'.e($asset['printer'] ?? '-').'</div>
        </div>
      </div>
    </div>

    <!-- Card 2: Detail Pelaksanaan Maintenance -->
    <div class="card p-4 border-0 shadow-sm mb-4">
      <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
        <h5 class="fw-bold text-primary mb-0"><i class="bi bi-calendar2-check me-2"></i>2. DETAIL PELAKSANAAN MAINTENANCE</h5>
        '.$statusBadge.'
      </div>
      <div class="row g-3 small mb-4">
        <div class="col-md-3">
          <div class="text-secondary">Tanggal Pelaksanaan:</div>
          <div class="fw-bold text-dark fs-6">'.e($dateStr).'</div>
        </div>
        <div class="col-md-3">
          <div class="text-secondary">Waktu / Jam:</div>
          <div class="fw-semibold text-dark">'.e($timeStr).' WITA</div>
        </div>
        <div class="col-md-3">
          <div class="text-secondary">Petugas / Teknisi:</div>
          <div class="fw-bold text-primary">'.e($techName).'</div>
        </div>
        <div class="col-md-3">
          <div class="text-secondary">Jenis Maintenance:</div>
          <div class="fw-semibold text-dark">'.e($mType).'</div>
        </div>
      </div>

      <!-- Bukti Audit Biometrik Wajah & GPS -->
      '.$bioAuditHtml.'

      <!-- 3. Checklist 9 Item -->
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-check2-square text-primary me-1"></i>HASIL 9 CHECKLIST PEMELIHARAAN ('.$totalChecked.'/9 OK):</h6>
        '.($isLoggedIn ? '<button type="button" class="btn btn-sm btn-outline-primary no-print" data-bs-toggle="modal" data-bs-target="#editChecklistModal"><i class="bi bi-pencil me-1"></i> Ubah Catatan Checklist</button>' : '').'
      </div>
      
      <div class="table-responsive rounded-3 border mb-4">
        <table class="table table-bordered align-middle mb-0 small">
          <thead class="table-light">
            <tr class="text-center fw-bold">
              <th style="width: 45px;">No</th>
              <th class="text-start">Item Pemeliharaan</th>
              <th style="width: 150px;">Status Checklist</th>
              <th class="text-start">Keterangan / Hasil Pemeriksaan</th>
            </tr>
          </thead>
          <tbody>'.$chkTableRows.'</tbody>
        </table>
      </div>

      <!-- 4. Temuan & Rekomendasi -->
      <div class="row g-3">
        <div class="col-md-6">
          <div class="p-3 bg-light rounded-3 border h-100">
            <h6 class="fw-bold text-danger mb-2"><i class="bi bi-exclamation-triangle-fill me-1"></i>Temuan / Masalah:</h6>
            <div class="small text-dark">'.nl2br(e($findings)).'</div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="p-3 bg-light rounded-3 border h-100">
            <h6 class="fw-bold text-success mb-2"><i class="bi bi-lightbulb-fill me-1"></i>Rekomendasi / Tindakan:</h6>
            <div class="small text-dark">'.nl2br(e($recommendation)).'</div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>';

// Render Modal Edit jika pengguna sudah login
if ($isLoggedIn) {
    $body .= '
<!-- Modal Edit Checklist & Data Maintenance -->
<div class="modal fade" id="editChecklistModal" tabindex="-1" aria-labelledby="editChecklistModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="post">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="action" value="update_detail">

        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold" id="editChecklistModalLabel"><i class="bi bi-pencil-square me-2"></i>Perbarui Data Maintenance & Checklist #'.$id.'</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">
          <!-- 1. Edit Tanggal & Petugas Pelaksana -->
          <div class="p-3 bg-light rounded-3 border mb-4">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-calendar-event text-primary me-2"></i>Waktu Pelaksanaan & Petugas:</h6>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label small fw-bold text-secondary">Tanggal Pelaksanaan <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="maintenance_date" value="'.e(substr((string)($scan['maintenance_date'] ?? ''), 0, 10)).'" required>
                <div class="form-text text-muted" style="font-size: 0.73rem;">Dapat diubah jika scan QR terlambat / beda hari. Bulan pada Kartu Kontrol akan otomatis disesuaikan.</div>
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-bold text-secondary">Jam / Waktu</label>
                <input type="time" class="form-control" name="maintenance_time" value="'.e(substr((string)($scan['maintenance_time'] ?? '10:00'), 0, 5)).'">
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-bold text-secondary">Petugas / Teknisi <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="technician_name" list="listTeknisiEdit" value="'.e($techName).'" required placeholder="Nama petugas">
                <datalist id="listTeknisiEdit">'.$techOptions.'</datalist>
              </div>
            </div>
          </div>

          <!-- 2. Status, Temuan, Rekomendasi -->
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <label class="form-label fw-bold">Status Hasil Maintenance</label>
              <select class="form-select" name="status">
                <option value="Selesai" '.($status==='Selesai'?'selected':'').'>✅ Selesai (Normal)</option>
                <option value="Temuan" '.($status==='Temuan'?'selected':'').'>⚠️ Temuan (Ada Masalah)</option>
                <option value="Perlu Perbaikan" '.($status==='Perlu Perbaikan'?'selected':'').'>🚨 Perlu Perbaikan</option>
                <option value="Proses" '.($status==='Proses'?'selected':'').'>⏳ Sedang Proses</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Temuan / Catatan Kerusakan</label>
              <input type="text" class="form-control" name="findings" value="'.e($findings !== '-' ? $findings : '').'" placeholder="Ketik temuan jika ada...">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Rekomendasi / Tindakan</label>
              <input type="text" class="form-control" name="recommendation" value="'.e($recommendation !== '-' ? $recommendation : '').'" placeholder="Tindakan yang dilakukan...">
            </div>
          </div>

          <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-check2-square text-primary me-1"></i>9 Item Pemeriksaan:</h6>
          <div class="row">
            '.$editChecklistRows.'
          </div>
        </div>

        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary fw-bold px-4"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
</div>';
}

render_page('Detail Maintenance #' . $id, $body, '', '', $isLoggedIn);
