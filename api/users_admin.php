<?php
require __DIR__ . '/bootstrap.php';
require_admin();

$error = '';
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Handle Form POST (Tambah / Edit Pengguna)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = trim((string)($_POST['action'] ?? 'add'));

    if ($action === 'add') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = trim((string)($_POST['password'] ?? ''));
        $nama = trim((string)($_POST['nama'] ?? ''));
        $role = trim((string)($_POST['role'] ?? 'teknisi'));
        $telepon = trim((string)($_POST['telepon'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'Aktif'));

        $res = create_new_user([
            'username' => $username,
            'password' => $password,
            'nama' => $nama,
            'role' => $role,
            'telepon' => $telepon,
            'status' => $status
        ]);

        if (!empty($res['success'])) {
            $_SESSION['flash'] = "Akun '{$nama}' (@{$username}) berhasil dibuat.";
            header('Location: ' . module_url('users_admin.php'));
            exit;
        } else {
            $error = $res['error'] ?? 'Gagal membuat akun pengguna baru.';
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['user_id'] ?? 0);
        $nama = trim((string)($_POST['nama'] ?? ''));
        $role = trim((string)($_POST['role'] ?? 'teknisi'));
        $telepon = trim((string)($_POST['telepon'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'Aktif'));
        $password = trim((string)($_POST['password'] ?? ''));

        $res = update_user($id, [
            'nama' => $nama,
            'role' => $role,
            'telepon' => $telepon,
            'status' => $status,
            'password' => $password
        ]);

        if (!empty($res['success'])) {
            $_SESSION['flash'] = "Data akun '{$nama}' berhasil diperbarui.";
            header('Location: ' . module_url('users_admin.php'));
            exit;
        } else {
            $error = $res['error'] ?? 'Gagal memperbarui data pengguna.';
        }
    }
}

$editId = max(0, (int)($_GET['edit'] ?? 0));
$editUser = null;
if ($editId > 0) {
    $editUser = get_user_by_id($editId);
}

$users = get_user_list();

$userRowsHtml = '';
$no = 0;
foreach ($users as $u) {
    $no++;
    $uId = (int)($u['id'] ?? 0);
    $uNama = (string)($u['nama'] ?? '');
    $uUsername = (string)($u['username'] ?? '');
    $uRole = strtolower((string)($u['role'] ?? 'teknisi'));
    $uTel = (string)($u['telepon'] ?? '-');
    $uStatus = (string)($u['status'] ?? 'Aktif');
    $isBeingEdited = ($editId === $uId);

    $roleBadge = ($uRole === 'admin')
        ? '<span class="badge text-bg-primary px-2 py-1"><i class="bi bi-shield-lock-fill me-1"></i> Administrator</span>'
        : (($uRole === 'auditor')
            ? '<span class="badge text-bg-secondary px-2 py-1"><i class="bi bi-eye-fill me-1"></i> Auditor / SKAI</span>'
            : '<span class="badge bg-info bg-opacity-15 text-info-emphasis px-2 py-1 border border-info border-opacity-25"><i class="bi bi-tools me-1"></i> Teknisi IT</span>');

    $statusBadge = (strcasecmp($uStatus, 'Nonaktif') === 0)
        ? '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">Nonaktif</span>'
        : '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">Aktif</span>';

    $telHtml = ($uTel !== '-' && $uTel !== '') ? '<div class="small text-secondary mt-1"><i class="bi bi-telephone me-1"></i>'.e($uTel).'</div>' : '';

    $userRowsHtml .= '
    <tr class="'.($isBeingEdited ? 'table-warning' : '').'">
      <td class="text-center fw-semibold text-secondary">'.$no.'</td>
      <td>
        <div class="fw-bold text-dark fs-6"><i class="bi bi-person-circle text-primary me-2"></i>'.e($uNama).'</div>
        <div class="small text-muted font-monospace">@'.e($uUsername).'</div>
        '.$telHtml.'
      </td>
      <td>'.$roleBadge.'</td>
      <td class="text-center">'.$statusBadge.'</td>
      <td class="text-nowrap text-end">
        <a class="btn btn-sm btn-outline-secondary" href="'.e(module_url('users_admin.php', ['edit'=>$uId])).'"><i class="bi bi-pencil-square me-1"></i> Edit / Reset</a>
      </td>
    </tr>';
}

if (!$userRowsHtml) {
    $userRowsHtml = '<tr><td colspan="5" class="text-center py-4 text-secondary">Belum ada data pengguna. Silakan buat akun teknisi pertama Anda melalui form di samping.</td></tr>';
}

$flashHtml = $flash ? '<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';
$errorHtml = $error ? '<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle-fill me-2"></i>'.e($error).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$modeBadge = is_google_cloud_mode() ? '<span class="badge text-bg-info mb-2"><i class="bi bi-google me-1"></i> Google Cloud Sheets API v4</span>' : '<span class="badge text-bg-secondary mb-2"><i class="bi bi-database me-1"></i> MySQL Database</span>';

// Form State: Add vs Edit
if ($editUser) {
    $editNama = $editUser['nama'] ?? '';
    $editUsername = $editUser['username'] ?? '';
    $editRole = strtolower($editUser['role'] ?? 'teknisi');
    $editTelepon = $editUser['telepon'] ?? '';
    $editStatus = $editUser['status'] ?? 'Aktif';

    $formCardTitle = '<h5 class="fw-bold text-warning mb-3 border-bottom pb-2"><i class="bi bi-pencil-square me-2"></i>Edit Akun / Reset Password</h5>';
    $formContent = '
    <form method="post">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="user_id" value="'.(int)$editUser['id'].'">

      <div class="mb-3">
        <label class="form-label fw-semibold">Username</label>
        <input type="text" class="form-control bg-light font-monospace" value="'.e($editUsername).'" readonly disabled>
        <div class="form-text small">Username tidak dapat diubah setelah dibuat.</div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Nama Lengkap Petugas / Teknisi <span class="text-danger">*</span></label>
        <input type="text" class="form-control fw-bold" name="nama" required value="'.e($editNama).'" placeholder="Contoh: Budi Santoso, S.Kom">
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Peran / Hak Akses <span class="text-danger">*</span></label>
        <select class="form-select" name="role">
          <option value="teknisi" '.($editRole==='teknisi'?'selected':'').'>🛠️ Teknisi IT (Melakukan scan & checklist maintenance)</option>
          <option value="admin" '.($editRole==='admin'?'selected':'').'>👑 Administrator (Akses penuh seluruh master & akun)</option>
          <option value="auditor" '.($editRole==='auditor'?'selected':'').'>👁️ Auditor / SKAI (Hanya lihat & verifikasi laporan)</option>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Reset Password Baru</label>
        <input type="password" class="form-control" name="password" placeholder="Kosongkan jika tidak ingin mengubah password...">
        <div class="form-text small">Hanya diisi jika ingin mengganti password akun ini.</div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">No. HP / WhatsApp</label>
        <input type="text" class="form-control" name="telepon" value="'.e($editTelepon !== '-' ? $editTelepon : '').'" placeholder="Contoh: 08123456789">
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold">Status Akun</label>
        <select class="form-select" name="status">
          <option value="Aktif" '.($editStatus==='Aktif'?'selected':'').'>✅ Aktif (Dapat login ke sistem)</option>
          <option value="Nonaktif" '.($editStatus==='Nonaktif'?'selected':'').'>⛔ Nonaktif (Blokir akses login)</option>
        </select>
      </div>

      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary flex-fill" href="'.e(module_url('users_admin.php')).'">Batal</a>
        <button type="submit" class="btn btn-warning text-dark flex-fill fw-bold py-2"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
      </div>
    </form>';
} else {
    $formCardTitle = '<h5 class="fw-bold text-primary mb-3 border-bottom pb-2"><i class="bi bi-person-plus-fill me-2"></i>Buat Akun Teknisi / User Baru</h5>';
    $formContent = '
    <form method="post">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="add">

      <div class="mb-3">
        <label class="form-label fw-semibold">Username Login <span class="text-danger">*</span></label>
        <input type="text" class="form-control font-monospace" name="username" required placeholder="Contoh: teknisi.budi, ahmad.it" autocomplete="off">
        <div class="form-text small">Gunakan huruf kecil tanpa spasi.</div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
        <input type="password" class="form-control" name="password" required placeholder="Masukkan password login..." autocomplete="new-password">
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Nama Lengkap Petugas / Teknisi <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="nama" required placeholder="Contoh: Budi Santoso, S.Kom">
        <div class="form-text small">Nama ini yang akan tercatat di log setiap scan maintenance.</div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Peran / Hak Akses <span class="text-danger">*</span></label>
        <select class="form-select" name="role">
          <option value="teknisi" selected>🛠️ Teknisi IT (Melakukan scan & checklist maintenance)</option>
          <option value="admin">👑 Administrator (Akses penuh seluruh master & akun)</option>
          <option value="auditor">👁️ Auditor / SKAI (Hanya lihat & verifikasi laporan)</option>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">No. HP / WhatsApp</label>
        <input type="text" class="form-control" name="telepon" placeholder="Contoh: 08123456789">
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold">Status Akun</label>
        <select class="form-select" name="status">
          <option value="Aktif" selected>✅ Aktif (Dapat langsung login)</option>
          <option value="Nonaktif">⛔ Nonaktif (Tangguhkan sementara)</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary w-100 fw-bold py-2"><i class="bi bi-save me-1"></i> Buat Akun Pengguna</button>
    </form>';
}

$body = '
'.$flashHtml.'
'.$errorHtml.'

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    '.$modeBadge.'
    <h2 class="fw-bold mb-0 text-dark"><i class="bi bi-people-fill text-primary me-2"></i>Kelola Akun Teknisi & Pengguna</h2>
    <div class="text-secondary">Daftar akun petugas IT, teknisi lapangan, dan hak akses sistem QR Maintenance.</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="'.e(module_url('dashboard.php')).'"><i class="bi bi-arrow-left"></i> Ke Dashboard</a>
    <a class="btn btn-warning text-dark fw-bold" href="'.e(module_url('asset_add.php')).'"><i class="bi bi-plus-lg"></i> Tambah Komputer</a>
  </div>
</div>

<div class="row g-4">
  <!-- Kolom Kiri: Tabel Daftar Pengguna -->
  <div class="col-lg-8">
    <div class="card p-4 border-0 shadow-sm h-100">
      <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-badge text-primary me-2"></i>Daftar Pengguna Aktif ('.count($users).')</h5>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 40px;" class="text-center">No</th>
              <th>Nama & Username</th>
              <th>Peran / Role</th>
              <th class="text-center">Status</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
            '.$userRowsHtml.'
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Kolom Kanan: Form Tambah / Edit Pengguna -->
  <div class="col-lg-4">
    <div class="card p-4 border-0 shadow-sm '.($editUser ? 'border-warning border-2' : '').'">
      '.$formCardTitle.'
      '.$formContent.'
    </div>
  </div>
</div>';

render_page('Kelola Akun Pengguna', $body);
