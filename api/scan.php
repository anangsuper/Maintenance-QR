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
// 0. PROSES LOGIN POPUP TEKNISI (AJAX / POST)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'ajax_login' || ($_POST['action'] ?? '') === 'modal_login')) {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = trim((string)($_POST['password'] ?? ''));
    $targetAction = trim((string)($_POST['target_action'] ?? 'start'));

    $res = authenticate_user($username, $password);

    if (!empty($_POST['is_ajax'])) {
        header('Content-Type: application/json; charset=utf-8');
        if (!empty($res['success'])) {
            $redirectUrl = module_url('scan.php', ['t' => $token, 'action' => ($targetAction ?: 'start')]);
            echo json_encode(['success' => true, 'redirect' => $redirectUrl, 'name' => $res['name'] ?? $username]);
        } else {
            echo json_encode(['success' => false, 'error' => $res['error'] ?? 'Username atau password salah.']);
        }
        exit;
    }

    if (!empty($res['success'])) {
        $redirectUrl = module_url('scan.php', ['t' => $token, 'action' => ($targetAction ?: 'start')]);
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// =========================================================================
// 1. PROSES SIMPAN FORM MAINTENANCE (POST)
// =========================================================================
$successData = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'save_maintenance')) {
    if (!is_logged_in()) {
        header('Location: ' . module_url('scan.php', ['t' => $token, 'open_login' => 1, 'target_action' => 'start']));
        exit;
    }

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

$currentMonthLog = get_asset_maintenance_status_month($assetId, $month, $year);
$action = trim((string)($_GET['action'] ?? ''));
$autoOpenLogin = trim((string)($_GET['open_login'] ?? ''));

// =========================================================================
// 3. TAMPILAN FORM CHECKLIST 9 ITEM (action = start ATAU form)
// =========================================================================
if ($action === 'start' || $action === 'form' || $action === 'ulang') {
    // Jika belum login, jangan redirect ke website login penuh, tapi tampilkan pop-up login
    if (!is_logged_in()) {
        $autoOpenLogin = $action;
    } else {
        $fixedItems = get_fixed_checklists();
        $isUlang = ($action === 'ulang' || ($currentMonthLog && $action === 'start'));

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

          <form method="post">
            <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
            <input type="hidden" name="action" value="save_maintenance">
            <input type="hidden" name="t" value="'.e($token).'">
            <input type="hidden" name="maintenance_type" value="'.e($mTypeVal).'">

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
                <label class="form-label small fw-bold text-secondary">Petugas / Teknisi</label>
                <input type="text" class="form-control py-2" name="technician_name" list="listTeknisi" value="'.e($techDefault).'" placeholder="Nama teknisi" required>
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
              <select class="form-select fw-bold py-2" name="status" id="selectStatus">
                <option value="Selesai" class="text-success" selected>✓ Selesai (Kondisi Normal & Berfungsi Baik)</option>
                <option value="Proses" class="text-warning">⏳ Proses (Sedang Ditangani / Butuh Waktu)</option>
                <option value="Perlu Perbaikan" class="text-danger">⚠️ Perlu Perbaikan (Ada Kerusakan / Perlu Sparepart)</option>
              </select>
            </div>

            <!-- Submit Button -->
            <div class="d-grid gap-2 pt-2">
              <button type="submit" class="btn btn-success btn-lg fw-bold py-3 shadow" onclick="return confirm(\'Simpan hasil checklist maintenance sekarang?\')">
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
    </script>';

    render_page($formTitle, $body, $formHeadStyle, $formScript, false);
    exit;
  }
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
        ? '<a class="btn btn-primary fw-semibold" href="'.e(module_url('maintenance_detail.php', ['id' => $cLogId])).'"><i class="bi bi-file-earmark-text me-1"></i> DETAIL LENGKAP AUDIT</a>'
        : '';

    $btnUlang = $loggedIn
        ? '<a class="btn btn-outline-primary fw-semibold" href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'ulang'])).'"><i class="bi bi-arrow-repeat me-1"></i> MAINTENANCE ULANG</a>'
        : '<button type="button" class="btn btn-outline-primary fw-semibold" onclick="openLoginModal(\'ulang\')"><i class="bi bi-arrow-repeat me-1"></i> MAINTENANCE ULANG</button>';

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
    if ($loggedIn) {
        $btnStartAction = '<a class="btn btn-success btn-lg fw-bold py-3 px-4 shadow-sm w-100" href="'.e(module_url('scan.php', ['t' => $token, 'action' => 'start'])).'">
          <i class="bi bi-play-circle-fill me-2"></i> MULAI MAINTENANCE SEKARANG
        </a>';
    } else {
        $btnStartAction = '<button type="button" class="btn btn-success btn-lg fw-bold py-3 px-4 shadow-sm w-100" onclick="openLoginModal(\'start\')">
          <i class="bi bi-play-circle-fill me-2"></i> MULAI MAINTENANCE SEKARANG
        </button>
        <div class="text-center mt-2"><small class="text-muted"><i class="bi bi-shield-lock me-1"></i>Teknisi cukup login sekali lewat pop-up untuk mulai mengisi checklist.</small></div>';
    }

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
         <button type="button" class="btn btn-sm btn-primary fw-semibold shadow-sm" onclick="openLoginModal(\'start\')"><i class="bi bi-box-arrow-in-right me-1"></i> Login Teknisi / Admin</button>
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

$modalLoginHtml = '
<!-- Modal Pop-up Login Teknisi IT -->
<div class="modal fade" id="technicianLoginModal" tabindex="-1" aria-labelledby="techLoginModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 410px;">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      
      <!-- Modal Header -->
      <div class="modal-header border-0 pb-0 pt-4 px-4 position-relative">
        <div class="w-100 text-center">
          <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-2" style="width: 58px; height: 58px;">
            <i class="bi bi-shield-lock-fill fs-2"></i>
          </div>
          <h5 class="modal-title fw-bold text-dark mb-1" id="techLoginModalLabel">Login Teknisi IT</h5>
          <p class="text-secondary small mb-0">Masuk untuk mengisi checklist pemeliharaan perangkat ini.</p>
        </div>
        <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body p-4 pt-3">
        <div id="loginModalAlert" class="alert alert-danger py-2 px-3 small d-none mb-3 border-0 shadow-sm">
          <i class="bi bi-exclamation-triangle-fill me-1"></i>
          <span id="loginModalAlertText"></span>
        </div>

        <form id="techLoginForm" onsubmit="handleTechLogin(event)">
          <input type="hidden" name="action" value="ajax_login">
          <input type="hidden" name="is_ajax" value="1">
          <input type="hidden" name="t" value="'.e($token).'">
          <input type="hidden" id="modalTargetAction" name="target_action" value="start">

          <div class="mb-3">
            <label class="form-label small fw-bold text-secondary mb-1">Username / NIK</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0 text-secondary"><i class="bi bi-person-fill"></i></span>
              <input type="text" name="username" id="modalUsername" class="form-control border-start-0 ps-0 bg-light" placeholder="Masukkan username" required autocomplete="username">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold text-secondary mb-1">Password</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0 text-secondary"><i class="bi bi-key-fill"></i></span>
              <input type="password" name="password" id="modalPassword" class="form-control border-start-0 border-end-0 ps-0 bg-light" placeholder="Masukkan password" required autocomplete="current-password">
              <button type="button" class="btn btn-outline-secondary border-start-0 bg-light text-secondary" onclick="toggleModalPassword()" title="Lihat Password">
                <i class="bi bi-eye" id="togglePasswordIcon"></i>
              </button>
            </div>
          </div>

          <button type="submit" id="btnSubmitModalLogin" class="btn btn-primary fw-bold py-2 px-3 w-100 shadow-sm rounded-3 mt-2">
            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk & Mulai Maintenance
          </button>
        </form>

        <div class="text-center mt-3 pt-2 border-top">
          <small class="text-muted d-block" style="font-size: 0.78rem;">
            <i class="bi bi-info-circle me-1"></i>Hanya akun <strong>Teknisi</strong> atau <strong>Admin</strong> yang dapat mengisi checklist.
          </small>
        </div>
      </div>

    </div>
  </div>
</div>';

$body .= $modalLoginHtml;

$mainScript = '
<script>
var loginModalInstance = null;

function getLoginModal() {
  var el = document.getElementById("technicianLoginModal");
  if (!el) return null;
  if (!loginModalInstance && typeof bootstrap !== "undefined") {
    loginModalInstance = new bootstrap.Modal(el);
  }
  return loginModalInstance;
}

function openLoginModal(targetAction) {
  var targetInp = document.getElementById("modalTargetAction");
  if (targetInp) targetInp.value = targetAction || "start";

  var alertBox = document.getElementById("loginModalAlert");
  if (alertBox) alertBox.classList.add("d-none");

  var passInp = document.getElementById("modalPassword");
  if (passInp) passInp.value = "";

  var modal = getLoginModal();
  if (modal) {
    modal.show();
    setTimeout(function() {
      var u = document.getElementById("modalUsername");
      if (u) u.focus();
    }, 350);
  }
}

function toggleModalPassword() {
  var passInp = document.getElementById("modalPassword");
  var icon = document.getElementById("togglePasswordIcon");
  if (!passInp || !icon) return;
  if (passInp.type === "password") {
    passInp.type = "text";
    icon.className = "bi bi-eye-slash";
  } else {
    passInp.type = "password";
    icon.className = "bi bi-eye";
  }
}

async function handleTechLogin(e) {
  e.preventDefault();
  var form = document.getElementById("techLoginForm");
  var btn = document.getElementById("btnSubmitModalLogin");
  var alertBox = document.getElementById("loginModalAlert");
  var alertText = document.getElementById("loginModalAlertText");

  if (alertBox) alertBox.classList.add("d-none");
  var origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = "<span class=\"spinner-border spinner-border-sm me-2\" role=\"status\" aria-hidden=\"true\"></span>Memverifikasi...";

  try {
    var formData = new FormData(form);
    var res = await fetch(window.location.href, {
      method: "POST",
      body: formData
    });
    var data = await res.json();
    if (data.success) {
      btn.className = "btn btn-success fw-bold py-2 px-3 w-100 shadow-sm rounded-3 mt-2";
      btn.innerHTML = "<i class=\"bi bi-check2-circle me-1\"></i> Berhasil Masuk! Membuka form...";
      setTimeout(function() {
        window.location.href = data.redirect || window.location.href;
      }, 400);
    } else {
      btn.disabled = false;
      btn.innerHTML = origHtml;
      if (alertBox && alertText) {
        alertText.textContent = data.error || "Username atau password salah.";
        alertBox.classList.remove("d-none");
      }
    }
  } catch (err) {
    btn.disabled = false;
    btn.innerHTML = origHtml;
    form.submit();
  }
}

document.addEventListener("DOMContentLoaded", function() {
  var autoOpen = ' . json_encode($autoOpenLogin) . ';
  if (autoOpen) {
    openLoginModal(autoOpen);
  }
});
</script>';

render_page('Detail Perangkat · ' . ($asset['kode_inventaris'] ?? 'QR'), $body, $headStyle, $mainScript, false);

