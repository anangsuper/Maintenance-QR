<?php
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
              <span class="tech-label text-primary font-monospace fw-bold px-2 py-1 bg-light border rounded" style="font-size: 0.8rem;">'.sprintf('%02d', $num).'</span>
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
      background: #f8fafc;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
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
                  <a href="'.e(module_url('user_biometric_enroll.php', ['ret' => module_url('scan.php', ['t' => $token, 'action' => 'form'])])).'" class="badge py-1 px-2 rounded-2" style="background-color: #EFF6FF !important; color: #1D4ED8 !important; border: 1.5px solid #BFDBFE !important; font-weight: 700; text-decoration: none;">
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
    </div>';

    $body .= render_biometric_modal_html($token, 'form');

    $checklistJs = <<<'JS'
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
    JS;

    $formHeadStyle .= render_biometric_css();
    $formScript = render_biometric_js($enrolledTechs, $checklistJs);

    render_page($formTitle, $body, $formHeadStyle, $formScript, false);
    exit;
}
