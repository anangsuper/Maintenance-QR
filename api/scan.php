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
          <p class="text-secondary small mb-4">Hasil checklist pemeliharaan telah dicatat ke dalam Kartu Kontrol & Database.</p>

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
            <a class="btn btn-primary fw-bold py-3 shadow-sm" href="'.e(module_url('scan.php', ['t' => $token])).'">
              <i class="bi bi-card-checklist me-1"></i> Lihat Kartu Kontrol Perangkat
            </a>
            <a class="btn btn-outline-secondary py-2" href="'.e(module_url('maintenance_detail.php', ['id' => $successData['log_id']])).'">
              <i class="bi bi-file-earmark-text me-1"></i> Rincian Audit Lengkap
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
// 3. TAMPILAN FORM CHECKLIST 9 ITEM (action = start ATAU form) -> HARUS LOGIN
// =========================================================================
if ($action === 'start' || $action === 'form' || $action === 'ulang') {
    require_login(); // Teknisi / Petugas wajib login untuk mengisi checklist maintenance

    $fixedItems = get_fixed_checklists();
    $isUlang = ($action === 'ulang' || ($currentMonthLog && $action === 'start'));

    $checklistRowsHtml = '';
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

    foreach ($fixedItems as $num => $name) {
        $defNote = $defaultNotes[$num] ?? 'Normal';
        $checklistRowsHtml .= '
        <tr>
          <td class="text-center fw-bold text-muted" style="width: 40px; border: 1px solid #cbd5e1;">'.$num.'</td>
          <td class="fw-semibold text-dark" style="border: 1px solid #cbd5e1;">'.e($name).'</td>
          <td class="text-center" style="width: 80px; border: 1px solid #cbd5e1;">
            <input class="form-check-input chk-box fs-5" type="checkbox" id="chk_'.$num.'" name="chk_'.$num.'" value="1" checked>
          </td>
          <td style="border: 1px solid #cbd5e1;">
            <input type="text" class="form-control form-control-sm note-input bg-light" id="notes_'.$num.'" name="notes_'.$num.'" value="'.e($defNote).'" placeholder="Keterangan">
          </td>
        </tr>';
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

            <!-- 1. 9 Items Checklist Table -->
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="fw-bold text-dark mb-0"><i class="bi bi-check2-square text-primary me-2"></i>9 Item Checklist Pemeliharaan:</h6>
              <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="setAllCheck(true)"><i class="bi bi-check-all"></i> Centang Semua</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAllCheck(false)"><i class="bi bi-dash"></i> Batal Semua</button>
              </div>
            </div>

            <div class="table-responsive rounded-3 border bg-white mb-4">
              <table class="table table-bordered table-sm align-middle mb-0" style="font-size: 0.88rem; border-color: #cbd5e1;">
                <thead style="background-color: #93c5fd; color: #0f172a;">
                  <tr class="text-center fw-bold">
                    <th style="width: 40px; border: 1px solid #64748b;">No</th>
                    <th class="text-start" style="border: 1px solid #64748b;">Checklist</th>
                    <th style="width: 80px; border: 1px solid #64748b;">Checklist</th>
                    <th class="text-start" style="border: 1px solid #64748b;">Keterangan</th>
                  </tr>
                </thead>
                <tbody>
                  '.$checklistRowsHtml.'
                </tbody>
              </table>
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

    $formScript = '
    <script>
    function setAllCheck(val) {
      document.querySelectorAll(".chk-box").forEach(function(el){
        el.checked = val;
      });
    }
    </script>';

    render_page($formTitle, $body, '', $formScript, false);
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

    $badgeColor = ($cStatus === 'Temuan' || $cStatus === 'Perlu Perbaikan') ? 'danger' : ($cStatus === 'Proses' ? 'warning text-dark' : 'success');
    $badgeIcon = ($cStatus === 'Temuan' || $cStatus === 'Perlu Perbaikan') ? 'bi-exclamation-triangle-fill' : ($cStatus === 'Proses' ? 'bi-hourglass-split' : 'bi-check-circle-fill');

    $btnDetail = $cLogId > 0
        ? ($loggedIn
            ? '<a class="btn btn-primary fw-semibold" href="'.e(module_url('maintenance_detail.php', ['id' => $cLogId])).'"><i class="bi bi-file-earmark-text me-1"></i> DETAIL LENGKAP AUDIT</a>'
            : '<a class="btn btn-outline-primary fw-semibold" href="'.e(module_url('login.php', ['redirect' => module_url('maintenance_detail.php', ['id' => $cLogId])])).'"><i class="bi bi-shield-lock me-1"></i> LOGIN UNTUK DETAIL AUDIT</a>')
        : '';

    $btnUlang = $loggedIn
        ? '<a class="btn btn-outline-primary fw-semibold" href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'ulang'])).'"><i class="bi bi-arrow-repeat me-1"></i> MAINTENANCE ULANG</a>'
        : '<a class="btn btn-outline-secondary fw-semibold" href="'.e(module_url('login.php', ['redirect' => module_url('scan.php', ['t' => $token, 'action' => 'ulang'])])).'"><i class="bi bi-lock me-1"></i> LOGIN UNTUK MAINTENANCE ULANG</a>';

    $statusCardHtml = '
    <div class="card border-0 shadow-sm mb-4 bg-success bg-opacity-10 border-start border-success border-4 p-3 p-md-4">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-success fw-bold fs-6"><i class="bi bi-calendar-check-fill me-1"></i> STATUS BULAN BERJALAN:</span>
        <span class="badge bg-'.$badgeColor.' px-3 py-2 fs-6"><i class="bi '.$badgeIcon.' me-1"></i> '.e($cStatus).'</span>
      </div>
      <h5 class="fw-bold text-dark mb-1">Periode: '.$monthName.' '.$year.'</h5>
      <p class="text-secondary small mb-2">Perangkat ini <strong>sudah dilakukan maintenance</strong> pada <strong>'.e(format_id_date($cDate)).'</strong> oleh <strong>'.e($cTech).'</strong>.</p>
      
      '.($cFindings !== '' ? '<div class="alert alert-danger py-2 px-3 small my-2"><strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Temuan:</strong> '.e($cFindings).'</div>' : '').'
      '.($cRecom !== '' ? '<div class="alert alert-info py-2 px-3 small my-2"><strong><i class="bi bi-lightbulb-fill me-1"></i>Rekomendasi:</strong> '.e($cRecom).'</div>' : '').'

      <div class="d-flex flex-wrap gap-2 mt-3 pt-2">
        '.$btnUlang.'
        '.$btnDetail.'
      </div>
    </div>';
} else {
    $startMaintUrl = $loggedIn
        ? module_url('scan.php', ['t' => $token, 'action' => 'start'])
        : module_url('login.php', ['redirect' => module_url('scan.php', ['t' => $token, 'action' => 'start'])]);

    $startMaintBtnText = $loggedIn
        ? '<i class="bi bi-play-circle-fill me-2"></i> MULAI MAINTENANCE SEKARANG'
        : '<i class="bi bi-shield-lock-fill me-2"></i> LOGIN TEKNISI UNTUK MULAI MAINTENANCE';

    $statusCardHtml = '
    <div class="card border-0 shadow-sm mb-4 bg-danger bg-opacity-10 border-start border-danger border-4 p-3 p-md-4">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-danger fw-bold fs-6"><i class="bi bi-exclamation-circle-fill me-1"></i> STATUS BULAN BERJALAN:</span>
        <span class="badge bg-danger px-3 py-2 fs-6"><i class="bi bi-x-circle-fill me-1"></i> Belum Maintenance</span>
      </div>
      <h5 class="fw-bold text-dark mb-1">Periode: '.$monthName.' '.$year.'</h5>
      <p class="text-secondary small mb-3">Perangkat ini belum dilakukan pemeliharaan hardware & OS untuk bulan ini.</p>
      
      <a class="btn btn-success btn-lg fw-bold py-3 px-4 shadow-sm w-100" href="'.e($startMaintUrl).'">
        '.$startMaintBtnText.'
      </a>
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
}
.excel-header-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 10px;
  font-weight: bold;
  font-size: 11pt;
  color: #000000;
}
.excel-header-table td {
  padding: 3px 2px;
}
.excel-header-line {
  border-bottom: 1.5px solid #000000;
  min-width: 250px;
  padding-left: 6px;
  font-family: "Segoe UI", Arial, sans-serif;
  font-weight: 600;
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
         <span class="small text-secondary"><i class="bi bi-info-circle text-primary me-1"></i> Mode Cek Info Perangkat (Publik / Karyawan)</span>
         <a href="'.e(module_url('login.php', ['redirect' => module_url('scan.php', ['t' => $token])])).'" class="btn btn-sm btn-primary fw-semibold"><i class="bi bi-box-arrow-in-right me-1"></i> Login Teknisi / Admin</a>
       </div>';

$body = '
<div class="row justify-content-center">
  <div class="col-md-11 col-lg-10">

    <!-- Status Strip Login / Tamu -->
    '.$userStatusStrip.'

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
        
        <!-- Header Info -->
        <table class="excel-header-table">
          <tr>
            <td style="width: 100px;">NAMA</td>
            <td style="width: 20px;">:</td>
            <td class="excel-header-line">'.e($userDisplay).'</td>
          </tr>
          <tr>
            <td>IP</td>
            <td>:</td>
            <td class="excel-header-line">'.e($ipDisplay).'</td>
          </tr>
          <tr>
            <td>PRINTER</td>
            <td>:</td>
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
