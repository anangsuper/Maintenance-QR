<?php
require __DIR__ . '/bootstrap.php';
require_admin();

$error = '';
$success = '';

// Handle Form POST (Tambah / Edit Cabang / Skip Cabang)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = trim((string)($_POST['action'] ?? 'add'));

    if ($action === 'add') {
        $nama = trim((string)($_POST['nama_cabang'] ?? ''));
        $alamat = trim((string)($_POST['alamat'] ?? ''));
        $telepon = trim((string)($_POST['telepon'] ?? ''));
        $pj = trim((string)($_POST['penanggung_jawab'] ?? ''));

        $res = create_new_cabang([
            'nama_cabang' => $nama,
            'alamat' => $alamat,
            'telepon' => $telepon,
            'penanggung_jawab' => $pj
        ]);

        if (!empty($res['success'])) {
            $_SESSION['flash'] = "Cabang '{$nama}' berhasil ditambahkan.";
            header('Location: ' . module_url('cabang_admin.php'));
            exit;
        } else {
            $error = $res['error'] ?? 'Gagal menambahkan cabang baru.';
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['cabang_id'] ?? 0);
        $nama = trim((string)($_POST['nama_cabang'] ?? ''));
        $alamat = trim((string)($_POST['alamat'] ?? ''));
        $telepon = trim((string)($_POST['telepon'] ?? ''));
        $pj = trim((string)($_POST['penanggung_jawab'] ?? ''));

        $res = update_cabang($id, [
            'nama_cabang' => $nama,
            'alamat' => $alamat,
            'telepon' => $telepon,
            'penanggung_jawab' => $pj
        ]);

        if (!empty($res['success'])) {
            $_SESSION['flash'] = "Data cabang '{$nama}' berhasil diperbarui.";
            header('Location: ' . module_url('cabang_admin.php'));
            exit;
        } else {
            $error = $res['error'] ?? 'Gagal memperbarui data cabang.';
        }
    } elseif ($action === 'skip_cabang') {
        $cId = (int)($_POST['cabang_id'] ?? 0);
        $m = max(1, min(12, (int)($_POST['bulan'] ?? date('n'))));
        $y = max(2020, min(2100, (int)($_POST['tahun'] ?? date('Y'))));
        $reason = trim((string)($_POST['reason'] ?? 'Tidak ada jadwal kunjungan cabang bulan ini'));

        $ok = set_branch_exemption($cId, $m, $y, true, $reason, current_user_name());
        if ($ok) {
            $_SESSION['flash'] = "Cabang berhasil ditandai 'Dilewati' untuk pemeliharaan periode {$m}/{$y}.";
        } else {
            $_SESSION['flash_error'] = "Gagal menandai lewati cabang.";
        }
        header('Location: ' . module_url('cabang_admin.php', ['bulan' => $m, 'tahun' => $y]));
        exit;
    } elseif ($action === 'cancel_skip_cabang') {
        $cId = (int)($_POST['cabang_id'] ?? 0);
        $m = max(1, min(12, (int)($_POST['bulan'] ?? date('n'))));
        $y = max(2020, min(2100, (int)($_POST['tahun'] ?? date('Y'))));

        $ok = set_branch_exemption($cId, $m, $y, false, '', current_user_name());
        if ($ok) {
            $_SESSION['flash'] = "Jadwal pemeliharaan cabang berhasil diaktifkan kembali untuk periode {$m}/{$y}.";
        } else {
            $_SESSION['flash_error'] = "Gagal mengaktifkan kembali cabang.";
        }
        header('Location: ' . module_url('cabang_admin.php', ['bulan' => $m, 'tahun' => $y]));
        exit;
    }
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$editId = max(0, (int)($_GET['edit'] ?? 0));
$editCabang = null;
if ($editId > 0) {
    $editCabang = get_cabang_by_id($editId);
}

$month = max(1, min(12, (int)($_GET['bulan'] ?? date('n'))));
$year = max(2020, min(2100, (int)($_GET['tahun'] ?? date('Y'))));

$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$monthName = $monthNames[$month] ?? date('F');

$branchSummaries = get_branch_maintenance_summary($month, $year);
$allCabangs = get_cabang_list();
$cabangMap = [];
foreach ($allCabangs as $cb) {
    $cabangMap[(int)($cb['id'] ?? 0)] = $cb;
}

$cabangRowsHtml = '';
$no = 0;
foreach ($branchSummaries as $bs) {
    $no++;
    $cId = $bs['id'];
    $cName = $bs['nama'];
    $tot = $bs['total'];
    $done = $bs['done'];
    $pending = $bs['pending'];
    $pct = $bs['percent'];
    $isBeingEdited = ($editId === $cId);
    $isSkipped = !empty($bs['is_skipped']);
    $skipReason = (string)($bs['skip_reason'] ?? 'Tidak ada jadwal kunjungan cabang bulan ini');

    $raw = $cabangMap[$cId] ?? [];
    $alamat = $raw['alamat'] ?? $raw['lokasi'] ?? '';
    $tel = format_phone_number($raw['telepon'] ?? $raw['kontak'] ?? '');
    $pj = $raw['penanggung_jawab'] ?? $raw['kepala_cabang'] ?? '';

    $extraInfo = '';
    if ($alamat || $pj || ($tel !== '-' && $tel !== '')) {
        $extraInfo = '<div class="small text-muted mt-1 d-flex flex-wrap gap-2" style="font-size: 0.78rem;">';
        if ($alamat) $extraInfo .= '<span><i class="bi bi-geo-alt me-1 text-secondary"></i>'.e($alamat).'</span>';
        if ($tel !== '-' && $tel !== '') $extraInfo .= '<span><i class="bi bi-telephone me-1 text-secondary"></i>'.e($tel).'</span>';
        if ($pj) $extraInfo .= '<span><i class="bi bi-person-badge me-1 text-secondary"></i>PJ: <strong>'.e($pj).'</strong></span>';
        $extraInfo .= '</div>';
    }

    $barColor = $pct >= 100 ? '#10B981' : ($pct >= 50 ? '#2E7CF6' : '#F59E0B');

    // Kolom Progres & Status Pemeliharaan Bulan Ini
    if ($isSkipped) {
        $progressHtml = '
        <div class="d-flex align-items-center justify-content-between mb-1" style="font-size: 0.78rem;">
          <span class="badge bg-light text-secondary border px-2 py-0.5"><i class="bi bi-calendar-x me-1"></i>Dilewati Bulan Ini</span>
          <span class="text-muted small fst-italic">off-schedule</span>
        </div>
        <div class="small text-muted" style="font-size: 0.75rem;" title="'.e($skipReason).'"><i class="bi bi-info-circle me-1"></i>'.e($skipReason).'</div>';
    } else {
        $progressHtml = '
        <div class="d-flex justify-content-between small mb-1" style="font-size: 0.78rem;">
          <span class="text-muted">Progres:</span>
          <strong>'.$done.'/'.$tot.' <span style="color: '.$barColor.'; font-family: monospace;">('.$pct.'%)</span></strong>
        </div>
        <div class="progress" style="height: 6px; background-color: #E2E8F0; border-radius: 999px;">
          <div class="progress-bar" style="width: '.$pct.'%; background-color: '.$barColor.'; border-radius: 999px;"></div>
        </div>';
    }

    // Tombol Skip / Batal Skip
    if ($isSkipped) {
        $skipBtn = '
        <form method="post" class="d-inline" onsubmit="return confirm(\'Aktifkan kembali jadwal pemeliharaan untuk cabang ini di bulan '.$monthName.' '.$year.'?\');">
          '.csrf_field().'
          <input type="hidden" name="action" value="cancel_skip_cabang">
          <input type="hidden" name="cabang_id" value="'.$cId.'">
          <input type="hidden" name="bulan" value="'.$month.'">
          <input type="hidden" name="tahun" value="'.$year.'">
          <button type="submit" class="btn btn-outline-success btn-sm py-1 px-2" title="Aktifkan Kembali Pemeliharaan Bulan Ini">
            <i class="bi bi-check-circle me-1"></i> Aktifkan
          </button>
        </form>';
    } else {
        $skipBtn = '
        <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2" onclick="openSkipModal('.$cId.', \''.e(addslashes($cName)).'\')" title="Lewati Cabang Ini pada Bulan '.$monthName.' '.$year.'">
          <i class="bi bi-calendar-x text-muted me-1"></i> Lewati
        </button>';
    }

    $cabangRowsHtml .= '
    <tr class="'.($isBeingEdited ? 'table-active' : '').'" style="border-bottom: 1px solid var(--app-border);">
      <td class="text-center font-monospace text-muted small">'.sprintf('%02d', $no).'</td>
      <td>
        <div class="fw-bold text-dark d-flex align-items-center gap-2">
          <i class="bi bi-building text-primary"></i>
          <span>'.e($cName).'</span>
          <span class="badge-chip chip-secondary font-monospace" style="font-size: 0.7rem;">#'.$cId.'</span>
        </div>
        '.$extraInfo.'
      </td>
      <td class="text-center">
        <span class="badge-chip chip-primary font-monospace fw-bold">'.$tot.' Unit</span>
      </td>
      <td style="min-width: 190px;">
        '.$progressHtml.'
      </td>
      <td class="text-nowrap text-end">
        <div class="d-inline-flex align-items-center gap-1">
          '.$skipBtn.'
          <div class="btn-group btn-group-sm">
            <a class="btn btn-outline-secondary" href="'.e(module_url('cabang_admin.php', ['edit'=>$cId, 'bulan'=>$month, 'tahun'=>$year])).'" title="Edit Info Cabang"><i class="bi bi-pencil-square"></i></a>
            <a class="btn btn-outline-secondary" href="'.e(module_url('dashboard.php', ['cabang'=>$cId, 'bulan'=>$month, 'tahun'=>$year])).'" title="Lihat Dashboard Cabang"><i class="bi bi-speedometer2"></i></a>
            <a class="btn btn-outline-secondary" target="_blank" href="'.e(module_url('print_report.php', ['cabang'=>$cId])).'" title="Cetak Laporan"><i class="bi bi-printer"></i></a>
            <a class="btn btn-outline-secondary" target="_blank" href="'.e(module_url('print_qr.php', ['cabang'=>$cId])).'" title="Cetak Label QR"><i class="bi bi-qr-code"></i></a>
          </div>
        </div>
      </td>
    </tr>';
}

if (!$cabangRowsHtml) {
    $cabangRowsHtml = '<tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-building fs-1 d-block mb-2 opacity-50"></i>Belum ada data kantor cabang. Silakan daftarkan cabang baru.</td></tr>';
}

$flashHtml = $flash ? '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" style="border-left: 4px solid #10B981 !important;"><i class="bi bi-check-circle-fill me-2 text-success"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';
$errorHtml = $error ? '<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" style="border-left: 4px solid #EF4444 !important;"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>'.e($error).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$modeBadge = is_google_cloud_mode() ? '<span class="badge-chip chip-primary"><i class="bi bi-google"></i> Google Sheets API v4 Sync</span>' : '<span class="badge-chip chip-secondary"><i class="bi bi-database"></i> MySQL Enterprise</span>';

// Pilihan Periode
$periodDropdownOptionsHtml = '';
for ($offset = 0; $offset < 6; $offset++) {
    $t = strtotime("-$offset month");
    $mNum = (int)date('n', $t);
    $yNum = (int)date('Y', $t);
    $mLabel = ($monthNames[$mNum] ?? date('F', $t)) . ' ' . $yNum;
    $sel = ($mNum === $month && $yNum === $year) ? 'selected' : '';
    $u = module_url('cabang_admin.php', array_merge($_GET, ['bulan' => $mNum, 'tahun' => $yNum]));
    $periodDropdownOptionsHtml .= '<option value="'.e($u).'" '.$sel.'>'.e($mLabel).'</option>';
}

// Form State: Add vs Edit
if ($editCabang) {
    $editName = $editCabang['nama_cabang'] ?? $editCabang['nama'] ?? '';
    $editAlamat = $editCabang['alamat'] ?? $editCabang['lokasi'] ?? '';
    $editTelepon = format_phone_number($editCabang['telepon'] ?? $editCabang['kontak'] ?? '');
    $editTeleponVal = ($editTelepon !== '-' && $editTelepon !== '') ? $editTelepon : '';
    $editPJ = $editCabang['penanggung_jawab'] ?? $editCabang['kepala_cabang'] ?? '';

    $formCardHeader = '
    <div class="card-header bg-white py-3 px-4" style="border-bottom: 1px solid var(--app-border);">
      <div class="text-uppercase small fw-bold text-warning" style="font-size: 0.72rem; letter-spacing: 0.06em;">MODIFIKASI ENTITAS</div>
      <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Data Cabang</h6>
    </div>';

    $formContent = '
    <form method="post" class="p-4">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="cabang_id" value="'.(int)$editCabang['id'].'">

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Nama Cabang / Unit Kerja <span class="text-danger">*</span></label>
        <input type="text" class="form-control fw-bold" name="nama_cabang" required value="'.e($editName).'" placeholder="Contoh: Kantor Cabang Semarang">
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Kota / Alamat Kantor</label>
        <input type="text" class="form-control" name="alamat" value="'.e($editAlamat).'" placeholder="Contoh: Jl. Pemuda No. 45, Semarang">
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">No. Telepon / Saluran Resmi</label>
        <input type="text" class="form-control" name="telepon" value="'.e($editTeleponVal).'" placeholder="Contoh: (024) 8765432 / 0812...">
      </div>

      <div class="mb-4">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Penanggung Jawab / Kepala Cabang</label>
        <input type="text" class="form-control" name="penanggung_jawab" value="'.e($editPJ).'" placeholder="Contoh: Bpk. Hendra Kusuma">
      </div>

      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary flex-fill fw-semibold" href="'.e(module_url('cabang_admin.php', ['bulan'=>$month, 'tahun'=>$year])).'">Batal</a>
        <button type="submit" class="btn btn-primary flex-fill fw-bold py-2"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
      </div>
    </form>';
} else {
    $formCardHeader = '
    <div class="card-header bg-white py-3 px-4" style="border-bottom: 1px solid var(--app-border);">
      <div class="text-uppercase small fw-bold" style="font-size: 0.72rem; letter-spacing: 0.06em; color: var(--app-accent);">ENTITAS BARU</div>
      <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-plus-circle text-primary me-2"></i>Tambah Cabang Baru</h6>
    </div>';

    $formContent = '
    <form method="post" class="p-4">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="add">

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Nama Cabang / Unit Kerja <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="nama_cabang" required placeholder="Contoh: Kantor Cabang Semarang">
        <div class="form-text" style="font-size: 0.75rem;">Nama resmi kantor untuk klasifikasi aset dan cetak audit.</div>
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Kota / Alamat Kantor</label>
        <input type="text" class="form-control" name="alamat" placeholder="Contoh: Jl. Pemuda No. 45, Semarang">
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">No. Telepon / Saluran Resmi</label>
        <input type="text" class="form-control" name="telepon" placeholder="Contoh: (024) 8765432 / 0812...">
      </div>

      <div class="mb-4">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Penanggung Jawab / Kepala Cabang</label>
        <input type="text" class="form-control" name="penanggung_jawab" placeholder="Contoh: Bpk. Hendra Kusuma">
      </div>

      <button type="submit" class="btn btn-primary w-100 fw-bold py-2"><i class="bi bi-plus-lg me-1"></i> Simpan Cabang Baru</button>
    </form>';
}

$body = '
'.$flashHtml.'
'.$errorHtml.'

<!-- Header Kicker & Action Bar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <div class="text-uppercase small fw-bold" style="letter-spacing: 0.08em; color: var(--app-accent); font-size: 0.72rem; margin-bottom: 2px;">MANAGEMENT / BRANCH OFFICES</div>
    <h2 class="h3 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
      <i class="bi bi-buildings text-primary"></i> Data Kantor Cabang
    </h2>
    <div class="text-muted small">Registri kantor cabang, jadwal pemeliharaan, serta pengecualian cabang off-schedule per bulan.</div>
  </div>
  <div class="d-flex align-items-center gap-2">
    '.$modeBadge.'
    <a class="btn btn-outline-secondary fw-semibold btn-sm" href="'.e(module_url('dashboard.php', ['bulan'=>$month, 'tahun'=>$year])).'"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
    <a class="btn btn-action-add fw-semibold btn-sm" href="'.e(module_url('asset_add.php')).'"><i class="bi bi-plus-lg me-1"></i> Tambah Aset</a>
  </div>
</div>

<div class="row g-4">
  <!-- Kolom Kiri: Tabel Daftar Cabang -->
  <div class="col-lg-8">
    <div class="card p-0 overflow-hidden shadow-sm h-100">
      <div class="card-header bg-white py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
        <div class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.05em;">
          <i class="bi bi-building-check text-primary me-2"></i>Daftar Cabang Aktif ('.count($branchSummaries).')
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="text-muted small" style="font-size: 0.78rem;">Periode Jadwal:</span>
          <select class="form-select form-select-sm fw-semibold" style="width: auto; font-size: 0.8rem;" onchange="if(this.value) window.location.href=this.value;">
            '.$periodDropdownOptionsHtml.'
          </select>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th style="width: 50px;" class="text-center font-monospace">NO</th>
              <th>KANTOR CABANG / ALAMAT</th>
              <th style="width: 110px;" class="text-center">TOTAL ASET</th>
              <th style="width: 220px;">STATUS '.$monthName.' '.$year.'</th>
              <th style="width: 170px;" class="text-end">AKSI</th>
            </tr>
          </thead>
          <tbody>
            '.$cabangRowsHtml.'
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Kolom Kanan: Form Tambah / Edit Cabang -->
  <div class="col-lg-4">
    <div class="card p-0 overflow-hidden shadow-sm">
      '.$formCardHeader.'
      '.$formContent.'
    </div>
  </div>
</div>

<!-- MODAL: LEWATI / SKIP PEMELIHARAAN CABANG BULAN INI -->
<div class="modal fade" id="modalSkipCabang" tabindex="-1" aria-labelledby="modalSkipCabangLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content border-0 shadow">
      '.csrf_field().'
      <input type="hidden" name="action" value="skip_cabang">
      <input type="hidden" name="cabang_id" id="skipCabangId" value="0">
      <input type="hidden" name="bulan" value="'.$month.'">
      <input type="hidden" name="tahun" value="'.$year.'">
      
      <div class="modal-header bg-dark text-white py-3">
        <h5 class="modal-title fw-bold fs-6 mb-0" id="modalSkipCabangLabel">
          <i class="bi bi-calendar-x me-2 text-warning"></i>Lewati Pemeliharaan Cabang Bulan Ini
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div class="alert alert-info py-2 px-3 small mb-3 border-0" style="background-color: #EBF5FF; color: #1E429F;">
          <i class="bi bi-info-circle-fill me-1"></i>
          Menandai cabang ini <strong>Dilewati</strong> untuk periode <strong>'.$monthName.' '.$year.'</strong>. Unit aset di cabang ini tidak akan dihitung dalam target Kepatuhan SLA IT bulan ini.
        </div>

        <div class="mb-3">
          <label class="form-label small fw-bold text-muted text-uppercase">Nama Cabang</label>
          <input type="text" class="form-control fw-bold bg-light" id="skipCabangName" readonly>
        </div>

        <div class="mb-3">
          <label class="form-label small fw-bold text-dark">Alasan Lewati Cabang <span class="text-danger">*</span></label>
          <select class="form-select fw-semibold mb-2" id="skipReasonSelect" onchange="document.getElementById(\'skipReasonText\').value = this.value;">
            <option value="Tidak ada jadwal kunjungan cabang bulan ini">📅 Tidak ada jadwal kunjungan cabang bulan ini</option>
            <option value="Jadwal pemeliharaan berkala / triwulanan">🔄 Jadwal pemeliharaan berkala / triwulanan (off-schedule)</option>
            <option value="Kendala akses / dinas luar kota dialihkan">🚗 Kendala akses / dinas luar kota dialihkan</option>
            <option value="Kunjungan khusus bulan sebelumnya sudah menyeluruh">✓ Kunjungan khusus bulan sebelumnya sudah menyeluruh</option>
            <option value="Lainnya">✏️ Alasan lainnya (tulis di bawah)</option>
          </select>
          <input type="text" class="form-control" name="reason" id="skipReasonText" value="Tidak ada jadwal kunjungan cabang bulan ini" required>
        </div>
      </div>
      <div class="modal-footer bg-light py-2 px-4">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-warning btn-sm fw-bold"><i class="bi bi-calendar-x me-1"></i> Konfirmasi Lewati</button>
      </div>
    </form>
  </div>
</div>

<script>
function openSkipModal(id, name) {
  document.getElementById("skipCabangId").value = id;
  document.getElementById("skipCabangName").value = name;
  const modal = new bootstrap.Modal(document.getElementById("modalSkipCabang"));
  modal.show();
}
</script>
';

render_page('Kelola Cabang', $body);
