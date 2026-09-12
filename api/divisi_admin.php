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

    $ketHtml = $dKet !== '' ? '<div class="small text-muted mt-1" style="font-size: 0.78rem;"><i class="bi bi-info-circle me-1 text-secondary"></i>'.e($dKet).'</div>' : '';
    $barColor = $pct >= 100 ? '#10B981' : ($pct >= 50 ? '#2E7CF6' : '#F59E0B');

    $divisiRowsHtml .= '
    <tr class="'.($isBeingEdited ? 'table-active' : '').'" style="border-bottom: 1px solid var(--app-border);">
      <td class="text-center font-monospace text-muted small">'.sprintf('%02d', $no).'</td>
      <td>
        <div class="fw-bold text-dark d-flex align-items-center gap-2">
          <i class="bi bi-diagram-3 text-primary"></i>
          <span>'.e($dName).'</span>
          <span class="badge-chip chip-secondary font-monospace" style="font-size: 0.7rem;">#'.$dId.'</span>
        </div>
        '.$ketHtml.'
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
          <a class="btn btn-outline-secondary" href="'.e(module_url('divisi_admin.php', ['edit'=>$dId])).'" title="Edit Divisi"><i class="bi bi-pencil-square"></i></a>
          <a class="btn btn-outline-secondary" href="'.e(module_url('audit.php', ['divisi'=>$dId])).'" title="Daftar Aset Divisi"><i class="bi bi-list-check"></i></a>
        </div>
      </td>
    </tr>';
}

if (!$divisiRowsHtml) {
    $divisiRowsHtml = '<tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-diagram-3 fs-1 d-block mb-2 opacity-50"></i>Belum ada data divisi. Silakan tambahkan divisi baru.</td></tr>';
}

$flashHtml = $flash ? '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" style="border-left: 4px solid #10B981 !important;"><i class="bi bi-check-circle-fill me-2 text-success"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';
$errorHtml = $error ? '<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" style="border-left: 4px solid #EF4444 !important;"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>'.e($error).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$modeBadge = is_google_cloud_mode() ? '<span class="badge-chip chip-primary"><i class="bi bi-google"></i> Google Sheets API v4 Sync</span>' : '<span class="badge-chip chip-secondary"><i class="bi bi-database"></i> MySQL Enterprise</span>';

// Form State: Add vs Edit
if ($editDivisi) {
    $editName = $editDivisi['nama_divisi'] ?? $editDivisi['nama'] ?? '';
    $editKet = $editDivisi['keterangan'] ?? '';

    $formCardHeader = '
    <div class="card-header bg-white py-3 px-4" style="border-bottom: 1px solid var(--app-border);">
      <div class="text-uppercase small fw-bold text-warning" style="font-size: 0.72rem; letter-spacing: 0.06em;">MODIFIKASI DIVISI</div>
      <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Divisi / Unit Kerja</h6>
    </div>';

    $formContent = '
    <form method="post" class="p-4">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="divisi_id" value="'.(int)$editDivisi['id'].'">

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Nama Divisi / Unit Kerja <span class="text-danger">*</span></label>
        <input type="text" class="form-control fw-bold" name="nama_divisi" required value="'.e($editName).'" placeholder="Contoh: Operasional, IT / MIS, Finance...">
      </div>

      <div class="mb-4">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Keterangan / Fungsi Kerja</label>
        <textarea class="form-control" name="keterangan" rows="3" placeholder="Contoh: Divisi operasional layanan nasabah dan kasir">'.e($editKet).'</textarea>
      </div>

      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary flex-fill fw-semibold" href="'.e(module_url('divisi_admin.php')).'">Batal</a>
        <button type="submit" class="btn btn-primary flex-fill fw-bold py-2"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
      </div>
    </form>';
} else {
    $formCardHeader = '
    <div class="card-header bg-white py-3 px-4" style="border-bottom: 1px solid var(--app-border);">
      <div class="text-uppercase small fw-bold" style="font-size: 0.72rem; letter-spacing: 0.06em; color: var(--app-accent);">ENTITAS BARU</div>
      <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-plus-circle text-primary me-2"></i>Tambah Divisi Baru</h6>
    </div>';

    $formContent = '
    <form method="post" class="p-4">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="add">

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Nama Divisi / Unit Kerja <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="nama_divisi" required placeholder="Contoh: IT / MIS, SDM & HRD, Legal, Marketing...">
        <div class="form-text" style="font-size: 0.75rem;">Nama divisi / bagian kerja yang akan muncul di filter dan registri aset.</div>
      </div>

      <div class="mb-4">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Keterangan / Fungsi Kerja</label>
        <textarea class="form-control" name="keterangan" rows="3" placeholder="Keterangan opsional fungsi divisi ini..."></textarea>
      </div>

      <button type="submit" class="btn btn-primary w-100 fw-bold py-2"><i class="bi bi-plus-lg me-1"></i> Simpan Divisi Baru</button>
    </form>';
}

$body = '
'.$flashHtml.'
'.$errorHtml.'

<!-- Header Kicker & Action Bar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <div class="text-uppercase small fw-bold" style="letter-spacing: 0.08em; color: var(--app-accent); font-size: 0.72rem; margin-bottom: 2px;">MANAGEMENT / DIVISIONS & DEPARTMENTS</div>
    <h2 class="h3 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
      <i class="bi bi-diagram-3 text-primary"></i> Data Divisi & Unit Kerja
    </h2>
    <div class="text-muted small">Daftar bagian/divisi kerja untuk pemetaan inventaris komputer dan pemeliharaan rutin.</div>
  </div>
  <div class="d-flex align-items-center gap-2">
    '.$modeBadge.'
    <a class="btn btn-outline-secondary fw-semibold btn-sm" href="'.e(module_url('dashboard.php')).'"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
    <a class="btn btn-action-add fw-semibold btn-sm" href="'.e(module_url('asset_add.php')).'"><i class="bi bi-plus-lg me-1"></i> Tambah Aset</a>
  </div>
</div>

<div class="row g-4">
  <!-- Kolom Kiri: Tabel Daftar Divisi -->
  <div class="col-lg-8">
    <div class="card p-0 border shadow-sm h-100" style="border-radius: 8px; border-color: var(--app-border) !important; background: #fff;">
      <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center" style="border-bottom: 1px solid var(--app-border);">
        <div class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.05em;">
          <i class="bi bi-diagram-3-fill text-primary me-2"></i>Daftar Divisi / Bagian ('.count($divisiSummaries).')
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead style="background-color: var(--app-navy); color: #ffffff;">
            <tr>
              <th style="width: 50px; background-color: var(--app-navy); color: #ffffff;" class="text-center font-monospace">NO</th>
              <th style="background-color: var(--app-navy); color: #ffffff;">DIVISI / UNIT KERJA</th>
              <th style="width: 130px; background-color: var(--app-navy); color: #ffffff;" class="text-center">TOTAL ASET</th>
              <th style="width: 200px; background-color: var(--app-navy); color: #ffffff;">PROGRES BULAN INI</th>
              <th style="width: 120px; background-color: var(--app-navy); color: #ffffff;" class="text-end">AKSI</th>
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
    <div class="card p-0 border shadow-sm" style="border-radius: 8px; border-color: var(--app-border) !important; background: #fff;">
      '.$formCardHeader.'
      '.$formContent.'
    </div>
  </div>
</div>';

render_page('Kelola Divisi / Unit Kerja', $body);
