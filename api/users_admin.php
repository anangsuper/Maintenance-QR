<?php
require __DIR__ . '/bootstrap.php';
require_admin_or_hrd();

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
        $nama_panggilan = trim((string)($_POST['nama_panggilan'] ?? ''));
        $role = trim((string)($_POST['role'] ?? 'teknisi'));
        $telepon = trim((string)($_POST['telepon'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'Aktif'));

        if (!is_admin() && strtolower($role) === 'admin') {
            $error = 'Akses ditolak: Hanya Administrator yang berwenang membuat akun dengan peran Administrator.';
        } else {
            $res = create_new_user([
                'username' => $username,
                'password' => $password,
                'nama' => $nama,
                'nama_panggilan' => $nama_panggilan,
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
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['user_id'] ?? 0);
        $nama = trim((string)($_POST['nama'] ?? ''));
        $nama_panggilan = trim((string)($_POST['nama_panggilan'] ?? ''));
        $role = trim((string)($_POST['role'] ?? 'teknisi'));
        $telepon = trim((string)($_POST['telepon'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'Aktif'));
        $password = trim((string)($_POST['password'] ?? ''));

        $targetUser = get_user_by_id($id);
        $targetRole = strtolower($targetUser['role'] ?? '');
        $isTargetAdmin = in_array($targetRole, ['admin', 'administrator'], true);

        if (!$targetUser) {
            $error = 'Pengguna tidak ditemukan.';
        } elseif (!is_admin() && $isTargetAdmin) {
            $error = 'Akses ditolak: Hanya Administrator yang berwenang mengubah akun Administrator.';
        } elseif (!is_admin() && strtolower($role) === 'admin') {
            $error = 'Akses ditolak: Hanya Administrator yang dapat memberikan hak akses Administrator.';
        } else {
            $res = update_user($id, [
                'nama' => $nama,
                'nama_panggilan' => $nama_panggilan,
                'role' => $role,
                'telepon' => $telepon,
                'status' => $status,
                'password' => $password
            ]);

            if (!empty($res['success'])) {
                if ($id === current_user_id()) {
                    $_SESSION['nama'] = $nama;
                    $effectiveRole = in_array(strtolower($role), ['hrd', 'umum', 'hrd_umum', 'sdm_umum', 'sdm & umum', 'sdm/hrd'], true) || str_contains(strtolower($role), 'sdm') ? 'sdm' : strtolower($role);
                    $_SESSION['role'] = $effectiveRole;
                }
                $_SESSION['flash'] = "Data akun '{$nama}' berhasil diperbarui.";
                header('Location: ' . module_url('users_admin.php'));
                exit;
            } else {
                $error = $res['error'] ?? 'Gagal memperbarui data pengguna.';
            }
        }
    } elseif ($action === 'approve_face') {
        $id = (int)($_POST['user_id'] ?? 0);
        $res = update_user_face_status($id, 'verified');
        if (!empty($res['success'])) {
            $_SESSION['flash'] = "Biometrik wajah pengguna #{$id} telah berhasil DIVERIFIKASI & DISETUJUI.";
            header('Location: ' . module_url('users_admin.php'));
            exit;
        } else {
            $error = $res['error'] ?? 'Gagal memverifikasi biometrik wajah.';
        }
    } elseif ($action === 'reject_face') {
        $id = (int)($_POST['user_id'] ?? 0);
        $res = update_user_face_status($id, 'rejected');
        if (!empty($res['success'])) {
            $_SESSION['flash'] = "Biometrik wajah pengguna #{$id} telah ditolak/dihapus.";
            header('Location: ' . module_url('users_admin.php'));
            exit;
        } else {
            $error = $res['error'] ?? 'Gagal menolak biometrik wajah.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['user_id'] ?? 0);
        $targetUser = get_user_by_id($id);
        $targetRole = strtolower($targetUser['role'] ?? '');
        $isTargetAdmin = in_array($targetRole, ['admin', 'administrator'], true);

        if (!is_admin() && $isTargetAdmin) {
            $error = 'Akses ditolak: Akun Administrator hanya dapat dihapus oleh Administrator.';
        } else {
            $res = delete_user($id);
            if (!empty($res['success'])) {
                $_SESSION['flash'] = "Akun pengguna #{$id} berhasil dihapus dari sistem.";
                header('Location: ' . module_url('users_admin.php'));
                exit;
            } else {
                $error = $res['error'] ?? 'Gagal menghapus akun pengguna.';
            }
        }
    }
}

$editId = max(0, (int)($_GET['edit'] ?? 0));
$editUser = null;
if ($editId > 0) {
    $editUser = get_user_by_id($editId, true);
    if ($editUser && !is_admin() && in_array(strtolower($editUser['role'] ?? ''), ['admin', 'administrator'], true)) {
        $_SESSION['flash'] = 'Akses ditolak: Anda tidak memiliki izin untuk mengedit akun Administrator.';
        header('Location: ' . module_url('users_admin.php'));
        exit;
    }
}

$users = get_user_list(true);

// Cek pengguna yang wajahnya berstatus 'pending' (menunggu verifikasi admin)
$pendingFaceUsers = [];
foreach ($users as $u) {
    $fDesc = trim((string)($u['face_descriptor'] ?? ''));
    $fStat = strtolower(trim((string)($u['face_status'] ?? '')));
    if ($fDesc !== '' && ($fStat === 'pending' || $fStat === 'menunggu')) {
        $pendingFaceUsers[] = $u;
    }
}

$pendingBannerHtml = '';
if (!empty($pendingFaceUsers)) {
    $pendingItemsHtml = '';
    foreach ($pendingFaceUsers as $pu) {
        $puId = (int)$pu['id'];
        $puPhoto = trim((string)($pu['face_photo'] ?? ''));
        $puImg = $puPhoto !== ''
            ? '<img src="'.e($puPhoto).'" class="rounded border shadow-sm" style="width: 52px; height: 52px; object-fit: cover; border-color: var(--app-border) !important;">'
            : '<div class="rounded d-flex align-items-center justify-content-center text-dark fw-bold" style="width: 52px; height: 52px; background-color: #FEF3C7; border: 1px solid #FCD34D;"><i class="bi bi-person fs-4 text-warning"></i></div>';

        $pendingItemsHtml .= '
        <div class="col-md-6 col-lg-4">
          <div class="card border p-3 h-100 bg-white shadow-sm" style="border-radius: 8px; border-color: #FCD34D !important;">
            <div class="d-flex align-items-center gap-3 mb-2">
              '.$puImg.'
              <div>
                <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.9rem;">'.e($pu['nama']).'</h6>
                <div class="text-muted font-monospace small">@'.e($pu['username']).' · '.ucfirst($pu['role']).'</div>
                <span class="badge-chip chip-warning mt-1" style="font-size: 0.7rem;"><i class="bi bi-hourglass-split"></i> Menunggu Verifikasi</span>
              </div>
            </div>
            <div class="d-flex gap-2 mt-auto pt-2 border-top" style="border-color: var(--app-border) !important;">
              <form method="post" class="flex-fill" onsubmit="return confirm(\'Setujui dan verifikasi biometrik wajah '.addslashes($pu['nama']).'?\')">
                <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
                <input type="hidden" name="action" value="approve_face">
                <input type="hidden" name="user_id" value="'.$puId.'">
                <button type="submit" class="btn btn-primary btn-sm fw-bold w-100 py-1" style="font-size: 0.78rem;">
                  <i class="bi bi-check-circle-fill me-1"></i> Setujui
                </button>
              </form>
              <form method="post" onsubmit="return confirm(\'Tolak sampel biometrik '.addslashes($pu['nama']).'?\')">
                <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
                <input type="hidden" name="action" value="reject_face">
                <input type="hidden" name="user_id" value="'.$puId.'">
                <button type="submit" class="btn btn-outline-danger btn-sm py-1" style="font-size: 0.78rem;">
                  <i class="bi bi-x-circle"></i> Tolak
                </button>
              </form>
            </div>
          </div>
        </div>';
    }

    $pendingBannerHtml = '
    <div class="card border shadow-sm mb-4 p-4" style="border-radius: 8px; border-left: 4px solid #F59E0B !important; background-color: #FFFDF5; border-color: var(--app-border);">
      <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
        <div>
          <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge-chip chip-warning"><i class="bi bi-shield-exclamation"></i> ACTION REQUIRED</span>
            <span class="text-muted small">ID: VERIFY-BIO-REQ</span>
          </div>
          <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-bounding-box text-warning me-2"></i>Verifikasi Biometrik Wajah Teknisi ('.count($pendingFaceUsers).')</h5>
          <div class="text-muted small">Teknisi telah mendaftarkan foto wajah via perangkat mobile. Wajib diverifikasi admin untuk autentikasi checklist.</div>
        </div>
      </div>
      <div class="row g-3">
        '.$pendingItemsHtml.'
      </div>
    </div>';
}

$userRowsHtml = '';
$no = 0;
foreach ($users as $u) {
    $no++;
    $uId = (int)($u['id'] ?? 0);
    $uNama = (string)($u['nama'] ?? '');
    $uNick = trim((string)($u['nama_panggilan'] ?? ''));
    $uUsername = (string)($u['username'] ?? '');
    $uRole = strtolower((string)($u['role'] ?? 'teknisi'));
    $uTel = format_phone_number((string)($u['telepon'] ?? '-'));
    $uStatus = (string)($u['status'] ?? 'Aktif');
    $fDesc = trim((string)($u['face_descriptor'] ?? ''));
    $fStat = strtolower(trim((string)($u['face_status'] ?? '')));
    $uPhoto = trim((string)($u['face_photo'] ?? ''));
    $isBeingEdited = ($editId === $uId);

    $nickBadge = ($uNick !== '') ? '<span class="badge-chip chip-secondary ms-1 font-monospace" title="Nama Panggilan untuk Kolom Paraf Kartu Kontrol IT" style="font-size: 0.7rem;"><i class="bi bi-pen"></i> Paraf: '.e($uNick).'</span>' : '';

    $roleBadge = ($uRole === 'admin')
        ? '<span class="badge-chip chip-primary"><i class="bi bi-shield-lock-fill"></i> Admin</span>'
        : (($uRole === 'hrd' || $uRole === 'umum' || $uRole === 'hrd_umum' || $uRole === 'sdm' || str_contains($uRole, 'sdm'))
            ? '<span class="badge-chip chip-warning text-dark"><i class="bi bi-person-workspace"></i> SDM & Umum</span>'
            : (($uRole === 'auditor')
                ? '<span class="badge-chip chip-secondary"><i class="bi bi-eye-fill"></i> Auditor</span>'
                : '<span class="badge-chip chip-info"><i class="bi bi-tools"></i> Teknisi IT</span>'));

    $statusBadge = (strcasecmp($uStatus, 'Nonaktif') === 0)
        ? '<span class="badge-chip chip-danger"><i class="bi bi-x-circle-fill"></i> Nonaktif</span>'
        : '<span class="badge-chip chip-success"><i class="bi bi-check-circle-fill"></i> Aktif</span>';

    // Avatar Foto Wajah Petugas
    $avatarHtml = '';
    if ($uPhoto !== '') {
        $avatarHtml = '
        <div class="flex-shrink-0 position-relative js-preview-face" style="cursor: pointer;" data-photo="'.e($uPhoto).'" data-nama="'.e($uNama).'" data-username="'.e($uUsername).'" data-role="'.e($uRole).'" title="Klik untuk melihat foto wajah petugas">
          <img src="'.e($uPhoto).'" class="rounded-circle border shadow-xs" style="width: 42px; height: 42px; object-fit: cover;" alt="Foto '.e($uNama).'">
          <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-success p-0 border border-white" style="width: 9px; height: 9px;" title="Terverifikasi"></span>
        </div>';
    } else {
        $initial = strtoupper(substr($uNama ?: $uUsername, 0, 1));
        $avatarHtml = '<div class="rounded-circle bg-light border d-flex align-items-center justify-content-center fw-bold text-secondary shadow-xs flex-shrink-0" style="width: 42px; height: 42px; font-size: 0.95rem;">'.$initial.'</div>';
    }

    // Status Biometrik & Tombol Aksi Verifikasi Admin
    if ($fDesc === '') {
        $bioBadge = '<a href="'.e(module_url('user_biometric_enroll.php', ['id'=>$uId])).'" class="badge-chip chip-secondary text-decoration-none" style="font-size: 0.72rem;"><i class="bi bi-camera"></i> Daftarkan</a>';
    } elseif ($fStat === 'verified' || $fStat === 'terverifikasi') {
        $bioPhotoThumb = ($uPhoto !== '') ? '<img src="'.e($uPhoto).'" class="rounded-circle border shadow-xs me-1.5 js-preview-face" style="width: 30px; height: 30px; object-fit: cover; cursor: pointer;" data-photo="'.e($uPhoto).'" data-nama="'.e($uNama).'" data-username="'.e($uUsername).'" data-role="'.e($uRole).'" title="Klik untuk memperbesar foto wajah">' : '';
        $bioBadge = '<div class="d-inline-flex align-items-center gap-1">
          '.$bioPhotoThumb.'
          <span class="badge-chip chip-success" style="font-size: 0.72rem;"><i class="bi bi-shield-check"></i> Terverifikasi</span>
          <form method="post" class="d-inline" onsubmit="return confirm(\'Hapus/reset data biometrik wajah '.addslashes($uNama).'?\')">
            <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
            <input type="hidden" name="action" value="reject_face">
            <input type="hidden" name="user_id" value="'.$uId.'">
            <button type="submit" class="btn btn-sm btn-link text-danger p-0 ms-1 text-decoration-none" title="Hapus Biometrik"><i class="bi bi-x-circle"></i></button>
          </form>
        </div>';
    } elseif ($fStat === 'pending' || $fStat === 'menunggu') {
        $bioPhotoThumb = ($uPhoto !== '') ? '<img src="'.e($uPhoto).'" class="rounded-circle border shadow-xs mb-1 js-preview-face" style="width: 34px; height: 34px; object-fit: cover; cursor: pointer;" data-photo="'.e($uPhoto).'" data-nama="'.e($uNama).'" data-username="'.e($uUsername).'" data-role="'.e($uRole).'" title="Klik untuk memperbesar foto wajah">' : '';
        $bioBadge = '<div class="d-flex flex-column align-items-center gap-1">
          '.$bioPhotoThumb.'
          <span class="badge-chip chip-warning" style="font-size: 0.72rem;"><i class="bi bi-hourglass-split"></i> Menunggu</span>
          <div class="d-flex gap-1 mt-1">
            <form method="post" class="d-inline" onsubmit="return confirm(\'Setujui biometrik '.addslashes($uNama).'?\')">
              <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
              <input type="hidden" name="action" value="approve_face">
              <input type="hidden" name="user_id" value="'.$uId.'">
              <button type="submit" class="btn btn-xs btn-primary py-0 px-2 fw-bold" style="font-size: 0.7rem;">Setujui</button>
            </form>
            <form method="post" class="d-inline" onsubmit="return confirm(\'Tolak biometrik '.addslashes($uNama).'?\')">
              <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
              <input type="hidden" name="action" value="reject_face">
              <input type="hidden" name="user_id" value="'.$uId.'">
              <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-1" style="font-size: 0.7rem;">Tolak</button>
            </form>
          </div>
        </div>';
    } else {
        $bioBadge = '<span class="badge-chip chip-danger" style="font-size: 0.72rem;"><i class="bi bi-x-circle"></i> Ditolak</span>';
    }

    $telHtml = ($uTel !== '-' && $uTel !== '') ? '<div class="small text-muted mt-0.5" style="font-size: 0.76rem;"><i class="bi bi-telephone me-1 text-secondary"></i>'.e($uTel).'</div>' : '';

    $isUserAdmin = in_array($uRole, ['admin', 'administrator'], true);
    $canManageUser = is_admin() || !$isUserAdmin;

    $delBtnHtml = '';
    if ($uId !== current_user_id()) {
        if ($canManageUser) {
            $confirmMsg = json_encode("Apakah Anda yakin ingin menghapus akun {$uNama} (@{$uUsername})? Tindakan ini permanen dan tidak dapat dibatalkan.");
            $delBtnHtml = '
            <form method="post" class="d-inline" onsubmit="return confirm(' . htmlspecialchars($confirmMsg, ENT_QUOTES, 'UTF-8') . ')">
              <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="user_id" value="'.$uId.'">
              <button type="submit" class="btn btn-outline-danger" title="Hapus Pengguna"><i class="bi bi-trash"></i></button>
            </form>';
        } else {
            $delBtnHtml = ' <span class="badge-chip chip-secondary ms-1" style="font-size: 0.7rem;" title="Hanya Administrator yang dapat mengelola akun ini"><i class="bi bi-shield-lock-fill"></i> Terkunci</span>';
        }
    } else {
        $delBtnHtml = ' <span class="badge-chip chip-secondary ms-1" style="font-size: 0.7rem;" title="Akun Anda yang sedang aktif"><i class="bi bi-person-check-fill"></i> Anda</span>';
    }

    $editBtnHtml = $canManageUser
        ? '<a class="btn btn-outline-secondary" href="'.e(module_url('users_admin.php', ['edit'=>$uId])).'" title="Edit Pengguna"><i class="bi bi-pencil-square"></i></a>'
        : '';

    $userRowsHtml .= '
    <tr class="'.($isBeingEdited ? 'table-active' : '').'" style="border-bottom: 1px solid var(--app-border);">
      <td class="text-center font-monospace text-muted small">'.sprintf('%02d', $no).'</td>
      <td>
        <div class="d-flex align-items-center gap-2.5">
          '.$avatarHtml.'
          <div>
            <div class="fw-bold text-dark d-flex align-items-center flex-wrap gap-1">
              <span>'.e($uNama).'</span>
              '.$nickBadge.'
            </div>
            <div class="text-muted font-monospace small" style="font-size: 0.78rem;">@'.e($uUsername).'</div>
            '.$telHtml.'
          </div>
        </div>
      </td>
      <td>'.$roleBadge.'</td>
      <td class="text-center">'.$bioBadge.'</td>
      <td class="text-center">'.$statusBadge.'</td>
      <td class="text-nowrap text-end">
        <div class="btn-group btn-group-sm">
          <a class="btn btn-outline-secondary" href="'.e(module_url('user_biometric_enroll.php', ['id'=>$uId])).'" title="Daftarkan / Perbarui Wajah Biometrik"><i class="bi bi-camera"></i></a>
          '.$editBtnHtml.'
        </div>
        '.$delBtnHtml.'
      </td>
    </tr>';
}

if (!$userRowsHtml) {
    $userRowsHtml = '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-people fs-1 d-block mb-2 opacity-50"></i>Belum ada data pengguna. Silakan buat akun pengguna baru.</td></tr>';
}

$flashHtml = $flash ? '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" style="border-left: 4px solid #10B981 !important;"><i class="bi bi-check-circle-fill me-2 text-success"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';
$errorHtml = $error ? '<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" style="border-left: 4px solid #EF4444 !important;"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>'.e($error).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$modeBadge = is_google_cloud_mode() ? '<span class="badge-chip chip-primary"><i class="bi bi-google"></i> Google Sheets API v4 Sync</span>' : '<span class="badge-chip chip-secondary"><i class="bi bi-database"></i> MySQL Enterprise</span>';

// Form State: Add vs Edit
if ($editUser) {
    $editNama = $editUser['nama'] ?? '';
    $editNick = $editUser['nama_panggilan'] ?? '';
    $editUsername = $editUser['username'] ?? '';
    $editRole = strtolower($editUser['role'] ?? 'teknisi');
    $editTelepon = format_phone_number((string)($editUser['telepon'] ?? ''));
    $editTeleponVal = ($editTelepon !== '-' && $editTelepon !== '') ? $editTelepon : '';
    $editStatus = $editUser['status'] ?? 'Aktif';

    $formCardHeader = '
    <div class="card-header bg-white py-3 px-4" style="border-bottom: 1px solid var(--app-border);">
      <div class="text-uppercase small fw-bold text-warning" style="font-size: 0.72rem; letter-spacing: 0.06em;">MODIFIKASI PENGGUNA</div>
      <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Akun / Password</h6>
    </div>';

    $formContent = '
    <form method="post" class="p-4">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="user_id" value="'.(int)$editUser['id'].'">

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Username</label>
        <input type="text" class="form-control bg-light font-monospace" value="'.e($editUsername).'" readonly disabled>
        <div class="form-text" style="font-size: 0.75rem;">Username permanen dan tidak dapat diubah.</div>
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Nama Lengkap Petugas <span class="text-danger">*</span></label>
        <input type="text" class="form-control fw-bold" name="nama" required value="'.e($editNama).'" placeholder="Contoh: Budi Santoso, S.Kom">
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Nama Panggilan (Paraf Kartu)</label>
        <input type="text" class="form-control" name="nama_panggilan" value="'.e($editNick).'" placeholder="Contoh: Budi">
        <div class="form-text" style="font-size: 0.75rem;">Dicetak pada kolom PARAF kartu inspeksi fisik.</div>
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Hak Akses / Peran <span class="text-danger">*</span></label>
        <select class="form-select" name="role">
          <option value="teknisi" '.($editRole==='teknisi'?'selected':'').'>Teknisi IT (Inspeksi & Checklist)</option>
          <option value="sdm" '.($editRole==='sdm'||$editRole==='hrd'||$editRole==='umum'||$editRole==='hrd_umum'||str_contains($editRole,'sdm')?'selected':'').'>SDM & Umum / HRD (Kartu Inventaris, Cabang, Divisi & Karyawan)</option>
          <option value="auditor" '.($editRole==='auditor'?'selected':'').'>Auditor / SKAI (Read-Only Audit Trail)</option>
          '.(is_admin() ? '<option value="admin" '.($editRole==='admin'?'selected':'').'>Administrator (Akses Penuh Sistem)</option>' : '').'
        </select>
        <div class="p-2.5 mt-2 bg-light rounded-3 border small" style="font-size: 0.72rem; line-height: 1.45;">
          <div class="fw-bold text-dark mb-1"><i class="bi bi-info-circle-fill text-primary me-1"></i> Penjelasan Hak Akses:</div>
          <ul class="mb-0 ps-3 text-muted">
            <li><strong>Teknisi IT:</strong> Checklist pemeliharaan, scanner QR, &amp; verifikasi wajah.</li>
            <li><strong>SDM &amp; Umum:</strong> Kartu inventaris barang non-komputer &amp; cetak CR80.</li>
            <li><strong>Auditor:</strong> Read-only monitoring &amp; ekspor log audit trail.</li>
            '.(is_admin() ? '<li><strong>Admin:</strong> Akses penuh seluruh sistem dan kelola pengguna.</li>' : '').'
          </ul>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Reset Password</label>
        <input type="password" class="form-control" name="password" placeholder="Kosongkan jika tidak ingin merubah...">
        <div class="form-text" style="font-size: 0.75rem;">Biarkan kosong kecuali ingin mengganti password.</div>
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">No. Kontak WhatsApp</label>
        <input type="text" class="form-control" name="telepon" value="'.e($editTeleponVal).'" placeholder="Contoh: 08123456789">
      </div>

      <div class="mb-4">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Status Akses Akun</label>
        <select class="form-select" name="status">
          <option value="Aktif" '.($editStatus==='Aktif'?'selected':'').'>Aktif (Diberikan Akses Login)</option>
          <option value="Nonaktif" '.($editStatus==='Nonaktif'?'selected':'').'>Nonaktif (Akses Ditangguhkan)</option>
        </select>
      </div>

      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary flex-fill fw-semibold" href="'.e(module_url('users_admin.php')).'">Batal</a>
        <button type="submit" class="btn btn-primary flex-fill fw-bold py-2"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
      </div>
    </form>';
} else {
    $formCardHeader = '
    <div class="card-header bg-white py-3 px-4" style="border-bottom: 1px solid var(--app-border);">
      <div class="text-uppercase small fw-bold" style="font-size: 0.72rem; letter-spacing: 0.06em; color: var(--app-accent);">PENGGUNA BARU</div>
      <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-person-plus text-primary me-2"></i>Tambah Akun Petugas</h6>
    </div>';

    $formContent = '
    <form method="post" class="p-4">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="add">

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Username Login <span class="text-danger">*</span></label>
        <input type="text" class="form-control font-monospace" name="username" required placeholder="Contoh: teknisi.budi" autocomplete="off">
        <div class="form-text" style="font-size: 0.75rem;">Gunakan huruf kecil tanpa spasi.</div>
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Password <span class="text-danger">*</span></label>
        <input type="password" class="form-control" name="password" required placeholder="Password login akun..." autocomplete="new-password">
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Nama Lengkap Petugas <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="nama" required placeholder="Contoh: Budi Santoso, S.Kom">
        <div class="form-text" style="font-size: 0.75rem;">Nama ini tercatat di riwayat audit dan checklist.</div>
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Nama Panggilan (Paraf Kartu)</label>
        <input type="text" class="form-control" name="nama_panggilan" placeholder="Contoh: Budi">
        <div class="form-text" style="font-size: 0.75rem;">Dicetak pada kolom PARAF kartu inspeksi fisik.</div>
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Hak Akses / Peran <span class="text-danger">*</span></label>
        <select class="form-select" name="role">
          <option value="teknisi" selected>Teknisi IT (Inspeksi & Checklist)</option>
          <option value="sdm">SDM & Umum / HRD (Kartu Inventaris, Cabang, Divisi & Karyawan)</option>
          <option value="auditor">Auditor / SKAI (Read-Only Audit Trail)</option>
          '.(is_admin() ? '<option value="admin">Administrator (Akses Penuh Sistem)</option>' : '').'
        </select>
        <div class="p-2.5 mt-2 bg-light rounded-3 border small" style="font-size: 0.72rem; line-height: 1.45;">
          <div class="fw-bold text-dark mb-1"><i class="bi bi-info-circle-fill text-primary me-1"></i> Penjelasan Hak Akses:</div>
          <ul class="mb-0 ps-3 text-muted">
            <li><strong>Teknisi IT:</strong> Checklist pemeliharaan, scanner QR, &amp; verifikasi wajah.</li>
            <li><strong>SDM &amp; Umum:</strong> Kartu inventaris barang non-komputer &amp; cetak CR80.</li>
            <li><strong>Auditor:</strong> Read-only monitoring &amp; ekspor log audit trail.</li>
            '.(is_admin() ? '<li><strong>Admin:</strong> Akses penuh seluruh sistem dan kelola pengguna.</li>' : '').'
          </ul>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">No. Kontak WhatsApp</label>
        <input type="text" class="form-control" name="telepon" placeholder="Contoh: 08123456789">
      </div>

      <div class="mb-4">
        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Status Akses Akun</label>
        <select class="form-select" name="status">
          <option value="Aktif" selected>Aktif (Dapat langsung login)</option>
          <option value="Nonaktif">Nonaktif (Akses ditangguhkan)</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary w-100 fw-bold py-2"><i class="bi bi-person-plus-fill me-1"></i> Buat Akun Pengguna</button>
    </form>';
}

$body = '
'.$flashHtml.'
'.$errorHtml.'
'.$pendingBannerHtml.'

<!-- Header Kicker & Action Bar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <div class="text-uppercase small fw-bold" style="letter-spacing: 0.08em; color: var(--app-accent); font-size: 0.72rem; margin-bottom: 2px;">MANAGEMENT / ACCESS CONTROL & STAFF</div>
    <h2 class="h3 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
      <i class="bi bi-people text-primary"></i> Akun Petugas & Hak Akses
    </h2>
    <div class="text-muted small">Registri akun petugas teknisi IT, verifikasi data biometrik wajah, dan pembagian hak akses operasional.</div>
  </div>
  <div class="d-flex align-items-center gap-2">
    '.$modeBadge.'
    <a class="btn btn-outline-warning fw-semibold btn-sm text-dark" href="'.e(module_url('unlock_login.php')).'"><i class="bi bi-shield-lock me-1"></i> Buka Kunci Login</a>
    <a class="btn btn-outline-secondary fw-semibold btn-sm" href="'.e(module_url('dashboard.php')).'"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
    <a class="btn btn-primary fw-semibold btn-sm" href="'.e(module_url('users_admin.php')).'"><i class="bi bi-person-plus me-1"></i> Pengguna Baru</a>
  </div>
</div>

<div class="row g-4">
  <!-- Kolom Kiri: Tabel Daftar Pengguna -->
  <div class="col-lg-8">
    <div class="card p-0 border shadow-sm h-100" style="border-radius: 8px; border-color: var(--app-border) !important; background: #fff;">
      <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center" style="border-bottom: 1px solid var(--app-border);">
        <div class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.05em;">
          <i class="bi bi-person-badge text-primary me-2"></i>Daftar Pengguna Aktif ('.count($users).')
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead style="background-color: var(--app-navy); color: #ffffff;">
            <tr>
              <th style="width: 50px; background-color: var(--app-navy); color: #ffffff;" class="text-center font-monospace">NO</th>
              <th style="background-color: var(--app-navy); color: #ffffff;">NAMA & USERNAME</th>
              <th style="background-color: var(--app-navy); color: #ffffff;">PERAN</th>
              <th style="width: 150px; background-color: var(--app-navy); color: #ffffff;" class="text-center">BIOMETRIK WAJAH</th>
              <th style="width: 110px; background-color: var(--app-navy); color: #ffffff;" class="text-center">STATUS</th>
              <th style="width: 120px; background-color: var(--app-navy); color: #ffffff;" class="text-end">AKSI</th>
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
    <div class="card p-0 border shadow-sm" style="border-radius: 8px; border-color: var(--app-border) !important; background: #fff;">
      '.$formCardHeader.'
      '.$formContent.'
    </div>
  </div>
</div>';

// Modal Pratinjau Foto Wajah Petugas
$modalHtml = <<<HTML
<div class="modal fade" id="modalPreviewFace" tabindex="-1" aria-labelledby="modalPreviewFaceLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
      <div class="modal-header py-2 px-3 bg-dark text-white border-0">
        <h6 class="modal-title fs-7 fw-bold mb-0 d-flex align-items-center gap-2" id="modalPreviewFaceLabel">
          <i class="bi bi-person-bounding-box text-primary"></i> Foto Biometrik Petugas
        </h6>
        <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4">
        <div class="position-relative d-inline-block mb-3">
          <img id="previewFaceImg" src="" alt="Foto Wajah Petugas" class="rounded-circle shadow-sm border border-3 border-primary" style="width: 150px; height: 150px; object-fit: cover;">
          <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-success p-2 border border-white" title="Biometrik Aktif">
            <i class="bi bi-shield-check"></i>
          </span>
        </div>
        <h5 class="fw-bold mb-1 text-dark" id="previewFaceNama">-</h5>
        <div class="text-muted small font-monospace mb-2" id="previewFaceUser">@-</div>
        <div id="previewFaceRoleBadge" class="mb-3"></div>
        <div class="p-2 bg-light rounded-3 text-muted small border">
          <i class="bi bi-info-circle text-primary me-1"></i> Foto ini terdaftar dalam basis data biometrik untuk pengenalan wajah login & absensi tugas.
        </div>
      </div>
      <div class="modal-footer py-2 px-3 bg-light border-0 justify-content-center">
        <button type="button" class="btn btn-secondary btn-sm px-4 fw-semibold" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
function previewFacePhoto(imgSrc, nama, username, role) {
  if (!imgSrc) return;
  var imgEl = document.getElementById("previewFaceImg");
  var namaEl = document.getElementById("previewFaceNama");
  var userEl = document.getElementById("previewFaceUser");
  var badgeEl = document.getElementById("previewFaceRoleBadge");

  if (imgEl) imgEl.src = imgSrc;
  if (namaEl) namaEl.innerText = nama || "-";
  if (userEl) userEl.innerText = "@" + (username || "-");
  
  var roleBadge = '<span class="badge bg-secondary">' + (role || "Pengguna") + '</span>';
  if (role === "admin") {
    roleBadge = '<span class="badge bg-primary"><i class="bi bi-shield-lock-fill me-1"></i> Administrator</span>';
  } else if (role === "hrd" || role === "umum" || role === "hrd_umum" || role === "sdm" || (typeof role === "string" && (role.indexOf("sdm") !== -1 || role.indexOf("hrd") !== -1))) {
    roleBadge = '<span class="badge bg-warning text-dark"><i class="bi bi-person-workspace me-1"></i> SDM & Umum</span>';
  } else if (role === "auditor" || role === "skai") {
    roleBadge = '<span class="badge bg-secondary"><i class="bi bi-eye-fill me-1"></i> Auditor</span>';
  } else {
    roleBadge = '<span class="badge bg-info text-dark"><i class="bi bi-tools me-1"></i> Teknisi IT</span>';
  }
  if (badgeEl) badgeEl.innerHTML = roleBadge;

  var modalEl = document.getElementById("modalPreviewFace");
  if (modalEl) {
    if (window.bootstrap && bootstrap.Modal) {
      var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      modal.show();
    } else {
      modalEl.classList.add("show");
      modalEl.style.display = "block";
    }
  }
}

document.addEventListener("DOMContentLoaded", function() {
  document.addEventListener("click", function(e) {
    var trigger = e.target.closest(".js-preview-face");
    if (!trigger) return;
    e.preventDefault();
    var photo = trigger.getAttribute("data-photo") || "";
    var nama = trigger.getAttribute("data-nama") || "";
    var username = trigger.getAttribute("data-username") || "";
    var role = trigger.getAttribute("data-role") || "";
    previewFacePhoto(photo, nama, username, role);
  });
});
</script>
HTML;

$body .= $modalHtml;

render_page('Kelola Akun Pengguna', $body);
