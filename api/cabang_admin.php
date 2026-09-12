<?php
require __DIR__ . '/bootstrap.php';
require_login();

$error = '';
$success = '';

// Handle Form POST (Tambah / Edit Cabang)
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
    }
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$editId = max(0, (int)($_GET['edit'] ?? 0));
$editCabang = null;
if ($editId > 0) {
    $editCabang = get_cabang_by_id($editId);
}

$month = (int)date('n');
$year = (int)date('Y');
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
      <td>
        <div class="d-flex justify-content-between small mb-1" style="font-size: 0.78rem;">
          <span class="text-muted">Progres Bulan Ini:</span>
          <strong>'.$done.'/'.$tot.' <span style="color: '.$barColor.'; font-family: monospace;">('.$pct.'%)</span></strong>
        </div>
        <div class="progress" style="height: 6px; background-color: #E2E8F0; border-radius: 999px;">
          <div class="progress-bar" style="width: '.$pct.'%; background-color: '.$barColor.'; border-radius: 999px;"></div>
        </div>
      </td>
      <td class="text-nowrap text-end">
        <div class="btn-group btn-group-sm">
          <a class="btn btn-outline-secondary" href="'.e(module_url('cabang_admin.php', ['edit'=>$cId])).'" title="Edit Cabang"><i class="bi bi-pencil-square"></i></a>
          <a class="btn btn-outline-secondary" href="'.e(module_url('dashboard.php', ['cabang'=>$cId])).'" title="Lihat Dashboard"><i class="bi bi-speedometer2"></i></a>
          <a class="btn btn-outline-secondary" target="_blank" href="'.e(module_url('print_report.php', ['cabang'=>$cId])).'" title="Cetak Laporan"><i class="bi bi-printer"></i></a>
          <a class="btn btn-outline-secondary" target="_blank" href="'.e(module_url('print_qr.php', ['cabang'=>$cId])).'" title="Cetak Label QR"><i class="bi bi-qr-code"></i></a>
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
        <a class="btn btn-outline-secondary flex-fill fw-semibold" href="'.e(module_url('cabang_admin.php')).'">Batal</a>
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
    <div class="text-muted small">Registri kantor cabang, penanggung jawab operasional, dan cakupan pemeliharaan perangkat per wilayah.</div>
  </div>
  <div class="d-flex align-items-center gap-2">
    '.$modeBadge.'
    <a class="btn btn-outline-secondary fw-semibold btn-sm" href="'.e(module_url('dashboard.php')).'"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
    <a class="btn btn-action-add fw-semibold btn-sm" href="'.e(module_url('asset_add.php')).'"><i class="bi bi-plus-lg me-1"></i> Tambah Aset</a>
  </div>
</div>

<div class="row g-4">
  <!-- Kolom Kiri: Tabel Daftar Cabang -->
  <div class="col-lg-8">
    <div class="card p-0 border shadow-sm h-100" style="border-radius: 8px; border-color: var(--app-border) !important; background: #fff;">
      <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center" style="border-bottom: 1px solid var(--app-border);">
        <div class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.05em;">
          <i class="bi bi-building-check text-primary me-2"></i>Daftar Cabang Aktif ('.count($branchSummaries).')
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead style="background-color: var(--app-navy); color: #ffffff;">
            <tr>
              <th style="width: 50px; background-color: var(--app-navy); color: #ffffff;" class="text-center font-monospace">NO</th>
              <th style="background-color: var(--app-navy); color: #ffffff;">KANTOR CABANG / ALAMAT</th>
              <th style="width: 130px; background-color: var(--app-navy); color: #ffffff;" class="text-center">TOTAL ASET</th>
              <th style="width: 200px; background-color: var(--app-navy); color: #ffffff;">PROGRES BULAN INI</th>
              <th style="width: 140px; background-color: var(--app-navy); color: #ffffff;" class="text-end">AKSI</th>
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
    <div class="card p-0 border shadow-sm" style="border-radius: 8px; border-color: var(--app-border) !important; background: #fff;">
      '.$formCardHeader.'
      '.$formContent.'
    </div>
  </div>
</div>';

render_page('Kelola Cabang', $body);
