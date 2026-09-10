<?php
if ($action === 'tindak_lanjut') {
    $allUsers = get_user_list(true);
    $enrolledTechs = get_enrolled_technicians(true);
    $hasEnrolledTechs = !empty($enrolledTechs);
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

          <form method="post" action="'.e(module_url('scan.php', ['t' => $token, 'action' => 'tindak_lanjut'])).'" id="formTindakLanjut">
            <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
            <input type="hidden" name="action" value="save_tindak_lanjut">
            <input type="hidden" name="t" value="'.e($token).'">
            <input type="hidden" name="log_id" value="'.$findingLogId.'">
            <input type="hidden" name="finding_id" value="'.$findingId.'">
            <input type="hidden" name="biometric_verified" id="bioVerified" value="0">
            <input type="hidden" name="biometric_confidence" id="bioConfidence" value="0">
            <input type="hidden" name="biometric_photo" id="bioPhoto" value="">
            <input type="hidden" name="latitude" id="bioLat" value="">
            <input type="hidden" name="longitude" id="bioLng" value="">

            <!-- 1. Teknisi yang Menindaklanjuti -->
            <div class="mb-3">
              <label class="form-label small fw-bold text-secondary">Teknisi yang Menindaklanjuti <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-person-check text-primary"></i></span>
                <input type="text" class="form-control" name="technician_name" id="technicianNameInput" list="listTeknisiTindak" value="'.e($currentTech).'" placeholder="Pilih atau ketik nama Anda..." required>
                <datalist id="listTeknisiTindak">'.$userOptionsHtml.'</datalist>
              </div>
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 mt-1">
                <div class="form-text text-muted mb-0" style="font-size: 0.75rem;">Nama teknisi yang melakukan penanganan / perbaikan di lokasi.</div>
                <a href="'.e(module_url('user_biometric_enroll.php', ['ret' => module_url('scan.php', ['t' => $token, 'action' => 'tindak_lanjut'])])).'" class="badge py-1 px-2 rounded-2" style="background-color: #EFF6FF !important; color: #1D4ED8 !important; border: 1.5px solid #BFDBFE !important; font-weight: 700; text-decoration: none;">
                  <i class="bi bi-person-plus-fill me-1"></i> + Daftarkan Wajah / Face ID
                </a>
              </div>
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
              '.($hasEnrolledTechs ? '
              <button type="button" class="btn btn-danger btn-lg fw-bold py-3 shadow" id="btnSelesaiBio" onclick="openBiometricModal()">
                <i class="bi bi-person-bounding-box me-2"></i> SELESAIKAN TINDAK LANJUT (SCAN WAJAH KAMERA)
              </button>
              <div class="d-flex justify-content-between align-items-center px-1 mt-1">
                <button type="submit" class="btn btn-link btn-sm text-decoration-none text-muted p-0" onclick="return confirm(\'Simpan hasil perbaikan tanpa verifikasi biometrik wajah?\')">
                  <i class="bi bi-shield-slash me-1"></i> Simpan Manual (Bypass Biometrik)
                </button>
                <a class="btn btn-link btn-sm text-decoration-none text-secondary p-0" href="'.e(module_url('scan.php', ['t' => $token])).'">Batal</a>
              </div>
              ' : '
              <button type="submit" class="btn btn-danger btn-lg fw-bold py-3 shadow" onclick="return confirm(\'Simpan hasil perbaikan dan selesaikan tindak lanjut temuan ini?\')">
                <i class="bi bi-save-fill me-2"></i> SIMPAN HASIL TINDAK LANJUT
              </button>
              <div class="alert alert-light border py-2 px-3 small mb-0 d-flex align-items-center justify-content-between flex-wrap gap-2 text-muted mt-1">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-info-circle text-primary fs-5"></i>
                  <div>Belum ada wajah teknisi terdaftar. Daftarkan di HP untuk verifikasi audit biometrik.</div>
                </div>
                <a href="'.e(module_url('user_biometric_enroll.php', ['ret' => module_url('scan.php', ['t' => $token, 'action' => 'tindak_lanjut'])])).'" class="btn btn-sm btn-primary fw-bold">
                  <i class="bi bi-camera-fill me-1"></i> Daftarkan Wajah di HP
                </a>
              </div>
              <a class="btn btn-outline-secondary py-2 mt-1" href="'.e(module_url('scan.php', ['t' => $token])).'">Batal</a>
              ').'
            </div>
          </form>
        </div>
      </div>
    </div>
    ' . render_biometric_modal_html($token, 'tindak_lanjut');

    $extraActionJs = '
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
    }';

    $bioHeadStyle = render_biometric_css();
    $bioScript = render_biometric_js($enrolledTechs, $extraActionJs);

    render_page('Tindak Lanjut Temuan - ' . ($asset['kode_inventaris'] ?? 'Aset'), $body, $bioHeadStyle, $bioScript, false);
    exit;
}
