<?php
require __DIR__ . '/bootstrap.php';
require_login();

$error = '';
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Handle Form POST (Tambah / Edit Divisi)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = trim((string)($_POST['action'] ?? 'add'));

    if ($action === 'add') {
        $nama = trim((string)($_POST['nama_divisi'] ?? ''));
        $keterangan = trim((string)($_POST['keterangan'] ?? ''));

        $res = create_new_divisi([
            'nama_divisi' => $nama,
            'keterangan' => $keterangan
        ]);

        if (!empty($res['success'])) {
            $_SESSION['flash'] = "Divisi / Unit Kerja '{$nama}' berhasil ditambahkan.";
            header('Location: ' . module_url('divisi_admin.php'));
            exit;
        } else {
            $error = $res['error'] ?? 'Gagal menambahkan divisi baru.';
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['divisi_id'] ?? 0);
        $nama = trim((string)($_POST['nama_divisi'] ?? ''));
        $keterangan = trim((string)($_POST['keterangan'] ?? ''));

        $res = update_divisi($id, [
            'nama_divisi' => $nama,
            'keterangan' => $keterangan
        ]);

        if (!empty($res['success'])) {
            $_SESSION['flash'] = "Data divisi '{$nama}' berhasil diperbarui.";
            header('Location: ' . module_url('divisi_admin.php'));
            exit;
        } else {
            $error = $res['error'] ?? 'Gagal memperbarui data divisi.';
        }
    }
}

$editId = max(0, (int)($_GET['edit'] ?? 0));
$editDivisi = null;
if ($editId > 0) {
    $editDivisi = get_divisi_by_id($editId);
}

$month = (int)date('n');
$year = (int)date('Y');
$divisiSummaries = get_divisi_maintenance_summary($month, $year);

$divisiRowsHtml = '';
$no = 0;
foreach ($divisiSummaries as $ds) {
    $no++;
    $dId = $ds['id'];
    $dName = $ds['nama'];
    $dKet = $ds['keterangan'] ?? '';
    $tot = $ds['total'];
    $done = $ds['done'];
    $pending = $ds['pending'];
    $pct = $ds['percent'];
    $isBeingEdited = ($editId === $dId);

    $ketHtml = $dKet !== '' ? '<div class="small text-secondary mt-1"><i class="bi bi-info-circle me-1"></i>'.e($dKet).'</div>' : '';

    $divisiRowsHtml .= '
    <tr class="'.($isBeingEdited ? 'table-warning' : '').'">
      <td class="text-center fw-semibold text-secondary">'.$no.'</td>
      <td>
        <div class="fw-bold text-dark fs-6"><i class="bi bi-diagram-3 text-primary me-2"></i>'.e($dName).'</div>
        <small class="text-muted">ID: #'.$dId.'</small>
        '.$ketHtml.'
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
        <a class="btn btn-sm btn-outline-secondary me-1" href="'.e(module_url('divisi_admin.php', ['edit'=>$dId])).'"><i class="bi bi-pencil-square me-1"></i> Edit</a>
        <a class="btn btn-sm btn-outline-primary" href="'.e(module_url('audit.php', ['divisi'=>$dId])).'"><i class="bi bi-list-check me-1"></i> Daftar Aset</a>
      </td>
    </tr>';
}

if (!$divisiRowsHtml) {
    $divisiRowsHtml = '<tr><td colspan="5" class="text-center py-4 text-secondary">Belum ada data divisi. Silakan tambahkan divisi pertama Anda melalui form di samping.</td></tr>';
}

$flashHtml = $flash ? '<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';
$errorHtml = $error ? '<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle-fill me-2"></i>'.e($error).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$modeBadge = is_google_cloud_mode() ? '<span class="badge text-bg-info mb-2"><i class="bi bi-google me-1"></i> Google Cloud Sheets API v4</span>' : '<span class="badge text-bg-secondary mb-2"><i class="bi bi-database me-1"></i> MySQL Database</span>';

// Form State: Add vs Edit
if ($editDivisi) {
    $editName = $editDivisi['nama_divisi'] ?? $editDivisi['nama'] ?? '';
    $editKet = $editDivisi['keterangan'] ?? '';

    $formCardTitle = '<h5 class="fw-bold text-warning mb-3 border-bottom pb-2"><i class="bi bi-pencil-square me-2"></i>Edit Divisi / Unit Kerja</h5>';
    $formContent = '
    <form method="post">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="divisi_id" value="'.(int)$editDivisi['id'].'">

      <div class="mb-3">
        <label class="form-label fw-semibold">Nama Divisi / Unit Kerja <span class="text-danger">*</span></label>
        <input type="text" class="form-control fw-bold" name="nama_divisi" required value="'.e($editName).'" placeholder="Contoh: Operasional, IT / MIS, Finance...">
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Keterangan / Fungsi</label>
        <textarea class="form-control" name="keterangan" rows="3" placeholder="Contoh: Divisi operasional layanan nasabah dan kasir">'.e($editKet).'</textarea>
      </div>

      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary flex-fill" href="'.e(module_url('divisi_admin.php')).'">Batal</a>
        <button type="submit" class="btn btn-warning text-dark flex-fill fw-bold py-2"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
      </div>
    </form>';
} else {
    $formCardTitle = '<h5 class="fw-bold text-primary mb-3 border-bottom pb-2"><i class="bi bi-plus-circle me-2"></i>Tambah Divisi Baru</h5>';
    $formContent = '
    <form method="post">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="add">

      <div class="mb-3">
        <label class="form-label fw-semibold">Nama Divisi / Unit Kerja <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="nama_divisi" required placeholder="Contoh: IT / MIS, SDM & HRD, Legal, Marketing...">
        <div class="form-text">Nama divisi / bagian kerja yang akan muncul di dropdown komputer.</div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Keterangan / Fungsi</label>
        <textarea class="form-control" name="keterangan" rows="3" placeholder="Keterangan opsional mengenai divisi ini..."></textarea>
      </div>

      <button type="submit" class="btn btn-primary w-100 fw-bold py-2"><i class="bi bi-save me-1"></i> Simpan Divisi Baru</button>
    </form>';
}

$body = '
'.$flashHtml.'
'.$errorHtml.'

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    '.$modeBadge.'
    <h2 class="fw-bold mb-0 text-dark"><i class="bi bi-diagram-3 text-primary me-2"></i>Kelola Divisi & Unit Kerja</h2>
    <div class="text-secondary">Daftar bagian/divisi kerja untuk pemetaan inventaris komputer dan pemeliharaan rutin.</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="'.e(module_url('dashboard.php')).'"><i class="bi bi-arrow-left"></i> Ke Dashboard</a>
    <a class="btn btn-warning text-dark fw-bold" href="'.e(module_url('asset_add.php')).'"><i class="bi bi-plus-lg"></i> Tambah Komputer</a>
  </div>
</div>

<div class="row g-4">
  <!-- Kolom Kiri: Tabel Daftar Divisi -->
  <div class="col-lg-8">
    <div class="card p-4 border-0 shadow-sm h-100">
      <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-diagram-3-fill text-primary me-2"></i>Daftar Divisi / Unit Kerja ('.count($divisiSummaries).')</h5>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 40px;" class="text-center">No</th>
              <th>Nama Divisi</th>
              <th class="text-center">Total Komputer</th>
              <th style="width: 190px;">Progres Maintenance</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
            '.$divisiRowsHtml.'
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Kolom Kanan: Form Tambah / Edit Divisi -->
  <div class="col-lg-4">
    <div class="card p-4 border-0 shadow-sm '.($editDivisi ? 'border-warning border-2' : '').'">
      '.$formCardTitle.'
      '.$formContent.'
    </div>
  </div>
</div>';

render_page('Kelola Divisi / Unit Kerja', $body);
