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
    $pj = $raw['penanggung_jawab'] ?? $raw['kepala_cabang'] ?? '';

    $extraInfo = '';
    if ($alamat || $pj) {
        $extraInfo = '<div class="small text-secondary mt-1">';
        if ($alamat) $extraInfo .= '<i class="bi bi-geo-alt me-1"></i>'.e($alamat).' &nbsp;';
        if ($pj) $extraInfo .= '<i class="bi bi-person me-1"></i>'.e($pj);
        $extraInfo .= '</div>';
    }

    $cabangRowsHtml .= '
    <tr class="'.($isBeingEdited ? 'table-warning' : '').'">
      <td class="text-center">'.$no.'</td>
      <td>
        <div class="fw-bold text-dark fs-6"><i class="bi bi-building text-primary me-2"></i>'.e($cName).'</div>
        <small class="text-muted">ID: #'.$cId.'</small>
        '.$extraInfo.'
      </td>
      <td class="text-center">
        <span class="badge text-bg-primary fs-6">'.$tot.' Unit</span>
      </td>
      <td>
        <div class="d-flex justify-content-between small mb-1">
          <span>Progres Bulan Ini:</span>
          <strong>'.$done.'/'.$tot.' ('.$pct.'%)</strong>
        </div>
        <div class="progress" style="height: 8px;">
          <div class="progress-bar '.($pct === 100 ? 'bg-success' : 'bg-primary').'" style="width: '.$pct.'%"></div>
        </div>
      </td>
      <td class="text-nowrap text-end">
        <a class="btn btn-sm btn-outline-secondary me-1" href="'.e(module_url('cabang_admin.php', ['edit'=>$cId])).'"><i class="bi bi-pencil-square me-1"></i> Edit</a>
        <a class="btn btn-sm btn-outline-primary me-1" href="'.e(module_url('dashboard.php', ['cabang'=>$cId])).'"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a class="btn btn-sm btn-outline-secondary me-1" target="_blank" href="'.e(module_url('print_report.php', ['cabang'=>$cId])).'"><i class="bi bi-printer"></i></a>
        <a class="btn btn-sm btn-outline-secondary" target="_blank" href="'.e(module_url('print_qr.php', ['cabang'=>$cId])).'"><i class="bi bi-qr-code"></i></a>
      </td>
    </tr>';
}

if (!$cabangRowsHtml) {
    $cabangRowsHtml = '<tr><td colspan="5" class="text-center py-4 text-secondary">Belum ada data cabang. Silakan tambahkan cabang pertama Anda di bawah.</td></tr>';
}

$flashHtml = $flash ? '<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';
$errorHtml = $error ? '<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle-fill me-2"></i>'.e($error).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$modeBadge = is_google_cloud_mode() ? '<span class="badge text-bg-info mb-2"><i class="bi bi-google me-1"></i> Google Cloud Sheets API v4</span>' : '<span class="badge text-bg-secondary mb-2"><i class="bi bi-database me-1"></i> MySQL Database</span>';

// Form State: Add vs Edit
if ($editCabang) {
    $editName = $editCabang['nama_cabang'] ?? $editCabang['nama'] ?? '';
    $editAlamat = $editCabang['alamat'] ?? $editCabang['lokasi'] ?? '';
    $editTelepon = $editCabang['telepon'] ?? $editCabang['kontak'] ?? '';
    $editPJ = $editCabang['penanggung_jawab'] ?? $editCabang['kepala_cabang'] ?? '';

    $formCardTitle = '<h5 class="fw-bold text-warning mb-3 border-bottom pb-2"><i class="bi bi-pencil-square me-2"></i>Edit Data Cabang</h5>';
    $formContent = '
    <form method="post">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="cabang_id" value="'.(int)$editCabang['id'].'">

      <div class="mb-3">
        <label class="form-label fw-semibold">Nama Cabang / Unit <span class="text-danger">*</span></label>
        <input type="text" class="form-control fw-bold" name="nama_cabang" required value="'.e($editName).'" placeholder="Contoh: Cabang Semarang, Cabang Bali...">
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Kota / Alamat</label>
        <input type="text" class="form-control" name="alamat" value="'.e($editAlamat).'" placeholder="Contoh: Jl. Pemuda No. 45, Semarang">
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">No. Telepon / Kontak</label>
        <input type="text" class="form-control" name="telepon" value="'.e($editTelepon).'" placeholder="Contoh: (024) 8765432 / 0812...">
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Penanggung Jawab / Kepala Cabang</label>
        <input type="text" class="form-control" name="penanggung_jawab" value="'.e($editPJ).'" placeholder="Contoh: Bpk. Hendra">
      </div>

      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary flex-fill" href="'.e(module_url('cabang_admin.php')).'">Batal</a>
        <button type="submit" class="btn btn-warning text-dark flex-fill fw-bold py-2"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
      </div>
    </form>';
} else {
    $formCardTitle = '<h5 class="fw-bold text-primary mb-3 border-bottom pb-2"><i class="bi bi-plus-circle me-2"></i>Tambah Cabang Baru</h5>';
    $formContent = '
    <form method="post">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="add">

      <div class="mb-3">
        <label class="form-label fw-semibold">Nama Cabang / Unit <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="nama_cabang" required placeholder="Contoh: Cabang Semarang, Cabang Bali, Gudang Barat...">
        <div class="form-text">Nama cabang yang akan muncul di dropdown dan laporan.</div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Kota / Alamat</label>
        <input type="text" class="form-control" name="alamat" placeholder="Contoh: Jl. Pemuda No. 45, Semarang">
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">No. Telepon / Kontak</label>
        <input type="text" class="form-control" name="telepon" placeholder="Contoh: (024) 8765432 / 0812...">
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Penanggung Jawab / Kepala Cabang</label>
        <input type="text" class="form-control" name="penanggung_jawab" placeholder="Contoh: Bpk. Hendra">
      </div>

      <button type="submit" class="btn btn-primary w-100 fw-bold py-2"><i class="bi bi-save me-1"></i> Simpan Cabang Baru</button>
    </form>';
}

$body = '
'.$flashHtml.'
'.$errorHtml.'

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    '.$modeBadge.'
    <h2 class="fw-bold mb-0 text-dark"><i class="bi bi-buildings text-primary me-2"></i>Kelola Cabang & Lokasi</h2>
    <div class="text-secondary">Daftar cabang/unit kantor untuk manajemen pemeliharaan komputer terpusat.</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="'.e(module_url('dashboard.php')).'"><i class="bi bi-arrow-left"></i> Ke Dashboard</a>
    <a class="btn btn-warning text-dark fw-bold" href="'.e(module_url('asset_add.php')).'"><i class="bi bi-plus-lg"></i> Tambah Komputer</a>
  </div>
</div>

<div class="row g-4">
  <!-- Kolom Kiri: Tabel Daftar Cabang -->
  <div class="col-lg-8">
    <div class="card p-4 border-0 shadow-sm h-100">
      <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-building-check text-primary me-2"></i>Daftar Cabang Aktif ('.count($branchSummaries).')</h5>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 40px;" class="text-center">No</th>
              <th>Nama Cabang</th>
              <th class="text-center">Total Komputer</th>
              <th style="width: 190px;">Progres Maintenance</th>
              <th class="text-end">Aksi</th>
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
    <div class="card p-4 border-0 shadow-sm '.($editCabang ? 'border-warning border-2' : '').'">
      '.$formCardTitle.'
      '.$formContent.'
    </div>
  </div>
</div>';

render_page('Kelola Cabang', $body);
