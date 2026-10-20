<?php
require __DIR__ . '/bootstrap.php';
require_admin_or_hrd();

$error = '';
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Handle Form POST (Tambah / Edit Pengguna / Face Action / Hapus)
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
                get_user_list(true);
                $_SESSION['flash'] = "Akun pengguna '{$nama}' (@{$username}) berhasil dibuat.";
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
                get_user_list(true);
                if ($id === current_user_id()) {
                    $_SESSION['nama'] = $nama;
                    $effectiveRole = in_array(strtolower($role), ['hrd', 'umum', 'hrd_umum', 'sdm_umum', 'sdm & umum', 'sdm/hrd'], true) || str_contains(strtolower($role), 'sdm') ? 'sdm' : strtolower($role);
                    $_SESSION['role'] = $effectiveRole;
                }
                $_SESSION['flash'] = "Data akun pengguna '{$nama}' berhasil diperbarui.";
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
            get_user_list(true);
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
            get_user_list(true);
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
                get_user_list(true);
                $_SESSION['flash'] = "Akun pengguna #{$id} berhasil dihapus dari sistem.";
                header('Location: ' . module_url('users_admin.php'));
                exit;
            } else {
                $error = $res['error'] ?? 'Gagal menghapus akun pengguna.';
            }
        }
    }
}

// Cek Pengguna yang Sedang Diedit
$editId = max(0, (int)($_GET['edit'] ?? 0));
$editUser = null;
if ($editId > 0) {
    $editUser = get_user_by_id($editId, false);
    if ($editUser && !is_admin() && in_array(strtolower($editUser['role'] ?? ''), ['admin', 'administrator'], true)) {
        $_SESSION['flash'] = 'Akses ditolak: Anda tidak memiliki izin untuk mengedit akun Administrator.';
        header('Location: ' . module_url('users_admin.php'));
        exit;
    }
}

// Ambil Daftar Pengguna
$users = get_user_list(false);

// Hitung Statistik & Metrik Pengguna
$totalUsers = count($users);
$countTeknisi = 0;
$countSdm = 0;
$countAdmin = 0;
$countAuditor = 0;
$countActive = 0;
$countNonactive = 0;
$countFaceVerified = 0;
$countFacePending = 0;
$countFaceNone = 0;
$pendingFaceUsers = [];

foreach ($users as $u) {
    $r = strtolower((string)($u['role'] ?? 'teknisi'));
    $st = strtolower((string)($u['status'] ?? 'aktif'));
    $fDesc = trim((string)($u['face_descriptor'] ?? ''));
    $fStat = strtolower(trim((string)($u['face_status'] ?? '')));

    if ($r === 'admin' || $r === 'administrator') {
        $countAdmin++;
    } elseif (in_array($r, ['sdm', 'hrd', 'umum', 'hrd_umum', 'sdm_umum', 'sdm & umum', 'sdm/hrd'], true) || str_contains($r, 'sdm')) {
        $countSdm++;
    } elseif ($r === 'auditor' || $r === 'skai') {
        $countAuditor++;
    } else {
        $countTeknisi++;
    }

    if ($st === 'nonaktif') {
        $countNonactive++;
    } else {
        $countActive++;
    }

    if ($fDesc !== '' && ($fStat === 'pending' || $fStat === 'menunggu')) {
        $countFacePending++;
        $pendingFaceUsers[] = $u;
    } elseif ($fStat === 'verified' || $fStat === 'terverifikasi') {
        $countFaceVerified++;
    } else {
        $countFaceNone++;
    }
}

// Hitung Persentase Biometrik Terverifikasi
$bioPercent = $totalUsers > 0 ? round(($countFaceVerified / $totalUsers) * 100) : 100;
$ratingScore = number_format(min(5.0, 4.0 + ($bioPercent / 100)), 1);

// Pending Approval Banner HTML
$pendingBannerHtml = '';
if (!empty($pendingFaceUsers)) {
    $pendingItemsHtml = '';
    foreach ($pendingFaceUsers as $pu) {
        $puId = (int)$pu['id'];
        $puNama = (string)($pu['nama'] ?? 'Tanpa Nama');
        $puUsername = (string)($pu['username'] ?? '');
        $puRole = (string)($pu['role'] ?? 'teknisi');
        $puPhoto = trim((string)($pu['face_photo'] ?? ''));

        $puImg = $puPhoto !== ''
            ? '<img src="'.e($puPhoto).'" class="rounded-circle border shadow-sm js-preview-face cursor-pointer" style="width: 48px; height: 48px; object-fit: cover;" data-photo="'.e($puPhoto).'" data-nama="'.e($puNama).'" data-username="'.e($puUsername).'" data-role="'.e($puRole).'" title="Klik untuk memperbesar foto">'
            : '<div class="rounded-circle d-flex align-items-center justify-content-center text-warning fw-bold border border-warning" style="width: 48px; height: 48px; background: #FFFBEB;"><i class="bi bi-person-bounding-box fs-5"></i></div>';

        $pendingItemsHtml .= '
        <div class="col-md-6 col-lg-4">
          <div class="card p-3 h-100 bg-white border border-warning shadow-sm" style="border-radius: 18px;">
            <div class="d-flex align-items-center gap-3 mb-3">
              '.$puImg.'
              <div class="min-w-0 flex-grow-1">
                <h6 class="fw-bold mb-0 text-dark text-truncate" style="font-size: 0.92rem;">'.e($puNama).'</h6>
                <div class="text-muted font-monospace small text-truncate">@'.e($puUsername).' · '.ucfirst(e($puRole)).'</div>
                <span class="badge-chip chip-warning mt-1" style="font-size: 0.7rem; border-radius: 9999px;"><i class="bi bi-hourglass-split"></i> Menunggu Verifikasi</span>
              </div>
            </div>
            <div class="d-flex gap-2 mt-auto pt-2 border-top" style="border-color: #FEF3C7 !important;">
              <form method="post" class="flex-fill m-0" onsubmit="return confirm(\'Setujui dan aktifkan biometrik wajah '.addslashes($puNama).'?\')">
                <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
                <input type="hidden" name="action" value="approve_face">
                <input type="hidden" name="user_id" value="'.$puId.'">
                <button type="submit" class="btn btn-success btn-sm fw-bold w-100 py-1.5" style="font-size: 0.78rem; border-radius: 10px;">
                  <i class="bi bi-check-circle-fill me-1"></i> Setujui
                </button>
              </form>
              <form method="post" class="m-0" onsubmit="return confirm(\'Tolak sampel foto wajah '.addslashes($puNama).'?\')">
                <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
                <input type="hidden" name="action" value="reject_face">
                <input type="hidden" name="user_id" value="'.$puId.'">
                <button type="submit" class="btn btn-outline-danger btn-sm py-1.5 px-2.5" style="font-size: 0.78rem; border-radius: 10px;" title="Tolak Pendaftaran">
                  <i class="bi bi-x-circle me-1"></i> Tolak
                </button>
              </form>
            </div>
          </div>
        </div>';
    }

    $pendingBannerHtml = '
    <div class="card shadow-sm mb-4 p-4 border-0" style="border-radius: 20px; border-left: 6px solid #F59E0B !important; background: linear-gradient(135deg, #FFFDF5 0%, #FEF9C3 100%);">
      <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
        <div>
          <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge-chip chip-warning fw-bold" style="border-radius: 9999px;"><i class="bi bi-shield-exclamation"></i> MEMERLUKAN PERSETUJUAN ADMIN</span>
            <span class="badge bg-warning text-dark fw-bold" style="font-size: 0.72rem; border-radius: 9999px;">'.count($pendingFaceUsers).' Permintaan</span>
          </div>
          <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-person-bounding-box text-warning"></i> Verifikasi Biometrik Wajah Teknisi Mobile
          </h5>
          <div class="text-muted small mt-1">Petugas telah mendaftarkan sampel wajah melalui smartphone saat bertugas. Silakan tinjau dan setujui untuk autentikasi checklist &amp; login presensi.</div>
        </div>
      </div>
      <div class="row g-3">
        '.$pendingItemsHtml.'
      </div>
    </div>';
}

// Generate User Rows HTML with Symmetrical Cell Heights & Action Buttons
$userRowsHtml = '';
$no = 0;
foreach ($users as $u) {
    $no++;
    $uId = (int)($u['id'] ?? 0);
    $uNama = (string)($u['nama'] ?? '');
    $uNick = trim((string)($u['nama_panggilan'] ?? ''));
    $uUsername = (string)($u['username'] ?? '');
    $uRole = strtolower((string)($u['role'] ?? 'teknisi'));
    $uTelRaw = (string)($u['telepon'] ?? '');
    $uTel = format_phone_number($uTelRaw);
    $uStatus = (string)($u['status'] ?? 'Aktif');
    $fDesc = trim((string)($u['face_descriptor'] ?? ''));
    $fStat = strtolower(trim((string)($u['face_status'] ?? '')));
    $uPhoto = trim((string)($u['face_photo'] ?? ''));
    $isBeingEdited = ($editId === $uId);

    // Filter Group Data Attributes for Instant JS Search
    $filterRoleGroup = ($uRole === 'admin' || $uRole === 'administrator') ? 'admin' : ((in_array($uRole, ['sdm', 'hrd', 'umum', 'hrd_umum', 'sdm_umum', 'sdm & umum', 'sdm/hrd'], true) || str_contains($uRole, 'sdm')) ? 'sdm' : (($uRole === 'auditor' || $uRole === 'skai') ? 'auditor' : 'teknisi'));
    $filterBioGroup = ($fStat === 'verified' || $fStat === 'terverifikasi') ? 'verified' : (($fStat === 'pending' || $fStat === 'menunggu') ? 'pending' : 'none');
    $filterStatusGroup = (strcasecmp($uStatus, 'Nonaktif') === 0) ? 'nonaktif' : 'aktif';

    // Nickname Badge
    $nickBadge = ($uNick !== '') ? '<span class="badge-chip chip-secondary font-monospace" title="Nama Panggilan / Paraf Kartu Kontrol IT" style="font-size: 0.68rem; border-radius: 9999px;"><i class="bi bi-pen"></i> Paraf: '.e($uNick).'</span>' : '';

    // Role Badges
    if ($filterRoleGroup === 'admin') {
        $roleBadge = '<span class="badge-chip chip-primary fw-bold" style="border-radius: 9999px;"><i class="bi bi-shield-lock-fill"></i> Administrator</span>';
    } elseif ($filterRoleGroup === 'sdm') {
        $roleBadge = '<span class="badge-chip chip-warning text-dark fw-bold" style="border-radius: 9999px;"><i class="bi bi-person-workspace"></i> SDM &amp; Umum</span>';
    } elseif ($filterRoleGroup === 'auditor') {
        $roleBadge = '<span class="badge-chip chip-secondary fw-bold" style="border-radius: 9999px;"><i class="bi bi-eye-fill"></i> Auditor</span>';
    } else {
        $roleBadge = '<span class="badge-chip" style="background:#E0F2FE; color:#0369A1; border:1px solid #BAE6FD; font-weight:600; border-radius: 9999px;"><i class="bi bi-tools"></i> Teknisi IT</span>';
    }

    // Status Badge
    $statusBadge = ($filterStatusGroup === 'nonaktif')
        ? '<span class="badge-chip chip-danger" style="border-radius: 9999px;"><i class="bi bi-x-circle-fill"></i> Nonaktif</span>'
        : '<span class="badge-chip chip-success" style="border-radius: 9999px;"><span class="pulse-dot-green me-1"></span> Aktif</span>';

    // WhatsApp Direct Link
    $telHtml = '';
    if ($uTel !== '-' && $uTel !== '') {
        $cleanPhone = preg_replace('/[^0-9]/', '', $uTel);
        if (str_starts_with($cleanPhone, '0')) {
            $waPhone = '62' . substr($cleanPhone, 1);
        } else {
            $waPhone = $cleanPhone;
        }
        $telHtml = '
        <div class="small mt-0.5 text-muted" style="font-size: 0.78rem;">
          <a href="https://wa.me/'.e($waPhone).'" target="_blank" rel="noopener noreferrer" class="text-decoration-none text-muted hover-primary d-inline-flex align-items-center gap-1" title="Kirim Pesan WhatsApp">
            <i class="bi bi-whatsapp text-success"></i>
            <span>'.e($uTel).'</span>
          </a>
        </div>';
    }

    // Avatar Profil (Symmetrical 42px size)
    if ($uPhoto !== '') {
        $avatarHtml = '
        <div class="flex-shrink-0 position-relative js-preview-face cursor-pointer" data-photo="'.e($uPhoto).'" data-nama="'.e($uNama).'" data-username="'.e($uUsername).'" data-role="'.e($uRole).'" title="Klik untuk melihat foto wajah petugas">
          <img src="'.e($uPhoto).'" class="rounded-circle border border-2 border-white shadow-xs" style="width: 42px; height: 42px; object-fit: cover;" alt="Foto '.e($uNama).'">
          <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-success p-0 border border-white" style="width: 10px; height: 10px;" title="Biometrik Terverifikasi"></span>
        </div>';
    } else {
        $initial = strtoupper(substr($uNama ?: $uUsername, 0, 1));
        $avatarHtml = '<div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white shadow-xs flex-shrink-0" style="width: 42px; height: 42px; font-size: 1rem; background: linear-gradient(135deg, #06B6D4 0%, #2563EB 100%);">'.$initial.'</div>';
    }

    // Biometric Status (Uniform Height Layout to ensure 100% Symmetrical Table Rows)
    if ($fDesc === '') {
        $bioBadge = '
        <div class="d-inline-flex align-items-center justify-content-center gap-1">
          <span class="badge-chip chip-secondary" style="font-size: 0.72rem; border-radius: 9999px;"><i class="bi bi-dash-circle text-muted"></i> Belum Ada</span>
        </div>';
    } elseif ($fStat === 'verified' || $fStat === 'terverifikasi') {
        $bioPhotoThumb = ($uPhoto !== '') ? '<img src="'.e($uPhoto).'" class="rounded-circle border shadow-xs js-preview-face cursor-pointer me-1" style="width: 26px; height: 26px; object-fit: cover;" data-photo="'.e($uPhoto).'" data-nama="'.e($uNama).'" data-username="'.e($uUsername).'" data-role="'.e($uRole).'" title="Klik untuk memperbesar foto">' : '';
        $bioBadge = '
        <div class="d-inline-flex align-items-center justify-content-center gap-1">
          '.$bioPhotoThumb.'
          <span class="badge-chip chip-success" style="font-size: 0.72rem; border-radius: 9999px;"><i class="bi bi-shield-check"></i> Terverifikasi</span>
        </div>';
    } elseif ($fStat === 'pending' || $fStat === 'menunggu') {
        $bioPhotoThumb = ($uPhoto !== '') ? '<img src="'.e($uPhoto).'" class="rounded-circle border border-warning shadow-xs js-preview-face cursor-pointer me-1" style="width: 26px; height: 26px; object-fit: cover;" data-photo="'.e($uPhoto).'" data-nama="'.e($uNama).'" data-username="'.e($uUsername).'" data-role="'.e($uRole).'" title="Klik untuk memperbesar foto">' : '';
        $bioBadge = '
        <div class="d-inline-flex align-items-center justify-content-center gap-1">
          '.$bioPhotoThumb.'
          <span class="badge-chip chip-warning" style="font-size: 0.72rem; border-radius: 9999px;"><i class="bi bi-hourglass-split"></i> Menunggu</span>
        </div>';
    } else {
        $bioBadge = '<span class="badge-chip chip-danger" style="font-size: 0.72rem; border-radius: 9999px;"><i class="bi bi-x-circle"></i> Ditolak</span>';
    }

    $isUserAdmin = in_array($uRole, ['admin', 'administrator'], true);
    $canManageUser = is_admin() || !$isUserAdmin;

    // Delete Button with Guard Checks (Uniform 32x32px Button)
    $delBtnHtml = '';
    if ($uId !== current_user_id()) {
        if ($canManageUser) {
            $confirmMsg = json_encode("Apakah Anda yakin ingin menghapus akun {$uNama} (@{$uUsername})? Tindakan ini permanen.");
            $delBtnHtml = '
            <form method="post" class="d-inline m-0" onsubmit="return confirm(' . htmlspecialchars($confirmMsg, ENT_QUOTES, 'UTF-8') . ')">
              <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="user_id" value="'.$uId.'">
              <button type="submit" class="btn btn-outline-danger btn-sm action-btn-sq" title="Hapus Akun Pengguna"><i class="bi bi-trash"></i></button>
            </form>';
        } else {
            $delBtnHtml = '<span class="action-btn-sq-placeholder" title="Hanya Administrator yang dapat mengelola akun ini"><i class="bi bi-shield-lock-fill text-muted"></i></span>';
        }
    } else {
        $delBtnHtml = '<span class="action-btn-sq-placeholder" title="Akun Anda yang sedang aktif"><i class="bi bi-person-check-fill text-primary"></i></span>';
    }

    // Edit Button (Uniform 32x32px Button)
    $editBtnHtml = $canManageUser
        ? '<a class="btn btn-outline-primary btn-sm action-btn-sq '.($isBeingEdited ? 'active' : '').'" href="'.e(module_url('users_admin.php', ['edit'=>$uId])).'" title="Edit Profil / Password"><i class="bi bi-pencil-square"></i></a>'
        : '<span class="action-btn-sq-placeholder"></span>';

    // Biometric Enrollment Shortcut (Uniform 32x32px Button)
    $enrollBtnHtml = '<a class="btn btn-outline-secondary btn-sm action-btn-sq" href="'.e(module_url('user_biometric_enroll.php', ['id'=>$uId])).'" title="Daftarkan / Perbarui Wajah Biometrik"><i class="bi bi-camera"></i></a>';

    $rowClass = $isBeingEdited ? 'table-warning border-start border-4 border-warning' : '';

    $userRowsHtml .= '
    <tr class="user-row '.$rowClass.'" data-nama="'.strtolower(e($uNama)).'" data-username="'.strtolower(e($uUsername)).'" data-phone="'.strtolower(e($uTel)).'" data-role="'.$filterRoleGroup.'" data-bio="'.$filterBioGroup.'" data-status="'.$filterStatusGroup.'">
      <td class="text-center font-monospace text-muted small align-middle">'.sprintf('%02d', $no).'</td>
      <td class="align-middle">
        <div class="d-flex align-items-center gap-3">
          '.$avatarHtml.'
          <div class="min-w-0">
            <div class="fw-bold text-dark d-flex align-items-center flex-wrap gap-1.5">
              <span>'.e($uNama).'</span>
              '.$nickBadge.'
            </div>
            <div class="text-muted font-monospace small" style="font-size: 0.78rem;">@'.e($uUsername).'</div>
            '.$telHtml.'
          </div>
        </div>
      </td>
      <td class="text-center align-middle">'.$roleBadge.'</td>
      <td class="text-center align-middle">'.$bioBadge.'</td>
      <td class="text-center align-middle">'.$statusBadge.'</td>
      <td class="text-center align-middle">
        <div class="d-inline-flex align-items-center justify-content-center gap-1.5">
          '.$enrollBtnHtml.'
          '.$editBtnHtml.'
          '.$delBtnHtml.'
        </div>
      </td>
    </tr>';
}

if (!$userRowsHtml) {
    $userRowsHtml = '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-people fs-1 d-block mb-2 opacity-50"></i>Belum ada data pengguna. Silakan buat akun pengguna baru pada form di samping.</td></tr>';
}

$flashHtml = $flash ? '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" style="border-left: 5px solid #10B981 !important; border-radius: 14px;"><div class="d-flex align-items-center"><i class="bi bi-check-circle-fill me-2 fs-5 text-success"></i><div>'.e($flash).'</div></div><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';
$errorHtml = $error ? '<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" style="border-left: 5px solid #EF4444 !important; border-radius: 14px;"><div class="d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2 fs-5 text-danger"></i><div>'.e($error).'</div></div><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

// Form State: Add vs Edit
if ($editUser) {
    $editNama = $editUser['nama'] ?? '';
    $editNick = $editUser['nama_panggilan'] ?? '';
    $editUsername = $editUser['username'] ?? '';
    $editRole = strtolower($editUser['role'] ?? 'teknisi');
    $editTelepon = format_phone_number((string)($editUser['telepon'] ?? ''));
    $editTeleponVal = ($editTelepon !== '-' && $editTelepon !== '') ? $editTelepon : '';
    $editStatus = $editUser['status'] ?? 'Aktif';
    $editPhoto = trim((string)($editUser['face_photo'] ?? ''));
    $editFaceStat = strtolower(trim((string)($editUser['face_status'] ?? 'none')));

    $formCardHeader = '
    <div class="card-header bg-white py-3.5 px-4 border-bottom d-flex align-items-center justify-content-between" style="border-top: 4px solid #F59E0B; border-top-left-radius: 20px; border-top-right-radius: 20px;">
      <div>
        <div class="text-uppercase small fw-bold text-warning" style="font-size: 0.72rem; letter-spacing: 0.06em;">MODIFIKASI PENGGUNA #'.(int)$editUser['id'].'</div>
        <h6 class="fw-bold mb-0 text-dark d-flex align-items-center gap-1.5"><i class="bi bi-pencil-square text-warning"></i> Edit Akun / Password</h6>
      </div>
      <a href="'.e(module_url('users_admin.php')).'" class="btn btn-outline-secondary btn-sm py-1 px-3 btn-pill" title="Batal & Kembali ke Tambah Pengguna"><i class="bi bi-x-lg me-1"></i> Batal</a>
    </div>';

    $editPhotoBox = '';
    if ($editPhoto !== '') {
        $editPhotoBox = '
        <div class="p-3 mb-3 bg-light rounded-4 border d-flex align-items-center gap-3">
          <img src="'.e($editPhoto).'" class="rounded-circle border shadow-xs js-preview-face cursor-pointer" style="width: 48px; height: 48px; object-fit: cover;" data-photo="'.e($editPhoto).'" data-nama="'.e($editNama).'" data-username="'.e($editUsername).'" data-role="'.e($editRole).'" title="Perbesar foto">
          <div class="flex-grow-1 min-w-0">
            <div class="fw-bold text-dark small">Biometrik: '.ucfirst($editFaceStat).'</div>
            <a href="'.e(module_url('user_biometric_enroll.php', ['id'=>(int)$editUser['id']])).'" class="small text-primary text-decoration-none fw-semibold"><i class="bi bi-camera me-1"></i> Perbarui Foto Wajah</a>
          </div>
        </div>';
    } else {
        $editPhotoBox = '
        <div class="p-3 mb-3 bg-light rounded-4 border d-flex align-items-center justify-content-between">
          <div class="small text-muted"><i class="bi bi-camera text-secondary me-1"></i> Belum ada biometrik wajah</div>
          <a href="'.e(module_url('user_biometric_enroll.php', ['id'=>(int)$editUser['id']])).'" class="btn btn-xs btn-outline-primary py-1 px-3 btn-pill" style="font-size: 0.75rem;"><i class="bi bi-camera me-1"></i> Daftarkan</a>
        </div>';
    }

    $formContent = '
    <form method="post" class="p-4">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="user_id" value="'.(int)$editUser['id'].'">

      '.$editPhotoBox.'

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted small form-label-custom">Username Login</label>
        <div class="input-group">
          <span class="input-group-text bg-light text-muted input-icon-box"><i class="bi bi-at"></i></span>
          <input type="text" class="form-control bg-light font-monospace form-control-custom" value="'.e($editUsername).'" readonly disabled>
        </div>
        <div class="form-text small" style="font-size: 0.72rem;">Username permanen dan tidak dapat diubah demi konsistensi log audit.</div>
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted small form-label-custom">Nama Lengkap Petugas <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text bg-white text-muted input-icon-box"><i class="bi bi-person"></i></span>
          <input type="text" class="form-control fw-bold form-control-custom" name="nama" required value="'.e($editNama).'" placeholder="Contoh: Budi Santoso, S.Kom">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted small form-label-custom">Nama Panggilan / Paraf Kartu</label>
        <div class="input-group">
          <span class="input-group-text bg-white text-muted input-icon-box"><i class="bi bi-pen"></i></span>
          <input type="text" class="form-control form-control-custom" name="nama_panggilan" value="'.e($editNick).'" placeholder="Contoh: Budi">
        </div>
        <div class="form-text small" style="font-size: 0.72rem;">Nama singkat yang dicetak pada kolom PARAF kartu inspeksi fisik.</div>
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted small form-label-custom">Hak Akses / Peran <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text bg-white text-muted input-icon-box"><i class="bi bi-shield-check"></i></span>
          <select class="form-select form-control-custom" name="role">
            <option value="teknisi" '.($editRole==='teknisi'?'selected':'').'>Teknisi IT (Inspeksi & Checklist)</option>
            <option value="sdm" '.($editRole==='sdm'||$editRole==='hrd'||$editRole==='umum'||$editRole==='hrd_umum'||str_contains($editRole,'sdm')?'selected':'').'>SDM & Umum / HRD (Inventaris & Karyawan)</option>
            <option value="auditor" '.($editRole==='auditor'?'selected':'').'>Auditor / SKAI (Read-Only Audit Trail)</option>
            '.(is_admin() ? '<option value="admin" '.($editRole==='admin'?'selected':'').'>Administrator (Akses Penuh Sistem)</option>' : '').'
          </select>
        </div>
        <div class="p-3 mt-2 bg-light rounded-4 border small" style="font-size: 0.72rem; line-height: 1.45;">
          <div class="fw-bold text-dark mb-1"><i class="bi bi-info-circle-fill text-primary me-1"></i> Penjelasan Hak Akses:</div>
          <ul class="mb-0 ps-3 text-muted">
            <li><strong>Teknisi IT:</strong> Checklist pemeliharaan, scanner QR, &amp; biometrik wajah.</li>
            <li><strong>SDM &amp; Umum:</strong> Kartu inventaris non-komputer, mutasi &amp; cetak CR80.</li>
            <li><strong>Auditor:</strong> Monitoring inspeksi &amp; unduh log audit trail.</li>
            '.(is_admin() ? '<li><strong>Admin:</strong> Akses penuh seluruh sistem dan kelola pengguna.</li>' : '').'
          </ul>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted small form-label-custom">Reset Password</label>
        <div class="input-group">
          <span class="input-group-text bg-white text-muted input-icon-box"><i class="bi bi-key"></i></span>
          <input type="password" class="form-control" name="password" id="editPasswordInput" placeholder="Kosongkan jika tidak diubah..." autocomplete="new-password" style="height: 42px; border-color: #E2E8F0; border-radius: 0;">
          <button class="btn btn-outline-secondary" style="border-radius: 0 12px 12px 0; border-color: #E2E8F0; width: 42px; height: 42px; padding: 0; display: flex; align-items: center; justify-content: center;" type="button" id="btnToggleEditPassword" title="Lihat Password"><i class="bi bi-eye"></i></button>
        </div>
        <div class="form-text small" style="font-size: 0.72rem;">Biarkan kosong jika password lama tetap dipertahankan.</div>
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted small form-label-custom">No. WhatsApp / Telepon</label>
        <div class="input-group">
          <span class="input-group-text bg-white text-muted input-icon-box"><i class="bi bi-telephone"></i></span>
          <input type="text" class="form-control form-control-custom" name="telepon" value="'.e($editTeleponVal).'" placeholder="Contoh: 08123456789">
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label text-uppercase fw-bold text-muted small form-label-custom">Status Akses Akun</label>
        <div class="input-group">
          <span class="input-group-text bg-white text-muted input-icon-box"><i class="bi bi-power"></i></span>
          <select class="form-select form-control-custom" name="status">
            <option value="Aktif" '.($editStatus==='Aktif'?'selected':'').'>Aktif (Diberikan Akses Login)</option>
            <option value="Nonaktif" '.($editStatus==='Nonaktif'?'selected':'').'>Nonaktif (Akses Ditangguhkan)</option>
          </select>
        </div>
      </div>

      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary flex-fill fw-semibold py-2.5 btn-pill" href="'.e(module_url('users_admin.php')).'">Batal</a>
        <button type="submit" class="btn btn-primary flex-fill fw-bold py-2.5 btn-pill"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
      </div>
    </form>';
} else {
    $formCardHeader = '
    <div class="card-header bg-white py-3.5 px-4 border-bottom" style="border-top: 4px solid #2563EB; border-top-left-radius: 20px; border-top-right-radius: 20px;">
      <div class="text-uppercase small fw-bold text-primary" style="font-size: 0.72rem; letter-spacing: 0.06em;">REGISTRASI PENGGUNA</div>
      <h6 class="fw-bold mb-0 text-dark d-flex align-items-center gap-1.5"><i class="bi bi-person-plus-fill text-primary"></i> Tambah Akun Petugas Baru</h6>
    </div>';

    $formContent = '
    <form method="post" class="p-4" autocomplete="off" id="formAddUser">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="add">

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted small form-label-custom">Username Login <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text bg-white text-muted input-icon-box"><i class="bi bi-at"></i></span>
          <input type="text" class="form-control font-monospace form-control-custom" name="username" required placeholder="Contoh: teknisi.budi" autocomplete="off">
        </div>
        <div class="form-text small" style="font-size: 0.72rem;">Gunakan huruf kecil tanpa spasi (a-z, 0-9, titik).</div>
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted small form-label-custom">Password <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text bg-white text-muted input-icon-box"><i class="bi bi-key"></i></span>
          <input type="password" class="form-control" name="password" id="addPasswordInput" required placeholder="Password login..." autocomplete="new-password" style="height: 42px; border-color: #E2E8F0; border-radius: 0;">
          <button class="btn btn-outline-secondary" style="border-radius: 0 12px 12px 0; border-color: #E2E8F0; width: 42px; height: 42px; padding: 0; display: flex; align-items: center; justify-content: center;" type="button" id="btnToggleAddPassword" title="Lihat Password"><i class="bi bi-eye"></i></button>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted small form-label-custom">Nama Lengkap Petugas <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text bg-white text-muted input-icon-box"><i class="bi bi-person"></i></span>
          <input type="text" class="form-control form-control-custom" name="nama" required placeholder="Contoh: Budi Santoso, S.Kom">
        </div>
        <div class="form-text small" style="font-size: 0.72rem;">Nama tercatat pada histori checklist &amp; log audit trail.</div>
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted small form-label-custom">Nama Panggilan / Paraf Kartu</label>
        <div class="input-group">
          <span class="input-group-text bg-white text-muted input-icon-box"><i class="bi bi-pen"></i></span>
          <input type="text" class="form-control form-control-custom" name="nama_panggilan" placeholder="Contoh: Budi">
        </div>
        <div class="form-text small" style="font-size: 0.72rem;">Nama singkat untuk paraf kartu inspeksi fisik.</div>
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted small form-label-custom">Hak Akses / Peran <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text bg-white text-muted input-icon-box"><i class="bi bi-shield-check"></i></span>
          <select class="form-select form-control-custom" name="role">
            <option value="teknisi" selected>Teknisi IT (Inspeksi & Checklist)</option>
            <option value="sdm">SDM & Umum / HRD (Inventaris & Karyawan)</option>
            <option value="auditor">Auditor / SKAI (Read-Only Audit Trail)</option>
            '.(is_admin() ? '<option value="admin">Administrator (Akses Penuh Sistem)</option>' : '').'
          </select>
        </div>
        <div class="p-3 mt-2 bg-light rounded-4 border small" style="font-size: 0.72rem; line-height: 1.45;">
          <div class="fw-bold text-dark mb-1"><i class="bi bi-info-circle-fill text-primary me-1"></i> Penjelasan Hak Akses:</div>
          <ul class="mb-0 ps-3 text-muted">
            <li><strong>Teknisi IT:</strong> Checklist pemeliharaan, scanner QR, &amp; biometrik wajah.</li>
            <li><strong>SDM &amp; Umum:</strong> Kartu inventaris non-komputer, mutasi &amp; cetak CR80.</li>
            <li><strong>Auditor:</strong> Monitoring inspeksi &amp; unduh log audit trail.</li>
            '.(is_admin() ? '<li><strong>Admin:</strong> Akses penuh seluruh sistem dan kelola pengguna.</li>' : '').'
          </ul>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label text-uppercase fw-bold text-muted small form-label-custom">No. WhatsApp / Telepon</label>
        <div class="input-group">
          <span class="input-group-text bg-white text-muted input-icon-box"><i class="bi bi-telephone"></i></span>
          <input type="text" class="form-control form-control-custom" name="telepon" placeholder="Contoh: 08123456789">
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label text-uppercase fw-bold text-muted small form-label-custom">Status Akses Akun</label>
        <div class="input-group">
          <span class="input-group-text bg-white text-muted input-icon-box"><i class="bi bi-power"></i></span>
          <select class="form-select form-control-custom" name="status">
            <option value="Aktif" selected>Aktif (Dapat langsung login)</option>
            <option value="Nonaktif">Nonaktif (Akses ditangguhkan)</option>
          </select>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100 fw-bold py-2.5 btn-pill shadow-sm"><i class="bi bi-person-plus-fill me-1.5"></i> Buat Akun Pengguna</button>
    </form>';
}

$body = '
'.$flashHtml.'
'.$errorHtml.'
'.$pendingBannerHtml.'

<!-- BROWSER MOCKUP CONTAINER & HERO BANNER (PERFECTLY SYMMETRICAL RECREATION OF REFERENCE IMAGE) -->
<div class="browser-mockup-frame mb-4">
  <!-- Topbar Mockup Header: 3 Mathematically Balanced Sections so Address Pill is DEAD-CENTERED -->
  <div class="browser-mockup-topbar">
    <div class="browser-topbar-col-left">
      <div class="browser-traffic-dots">
        <span class="browser-traffic-dot browser-dot-amber"></span>
        <span class="browser-traffic-dot browser-dot-cyan"></span>
        <span class="browser-traffic-dot browser-dot-green"></span>
      </div>
    </div>
    
    <div class="browser-topbar-col-center">
      <div class="browser-address-pill">
        <i class="bi bi-shield-lock-fill text-success" style="font-size: 0.75rem;"></i>
        <span>bankmitra.id/users_admin.php</span>
      </div>
    </div>

    <div class="browser-topbar-col-right">
      <div class="floating-toast-pill">
        <div class="floating-toast-icon-amber">
          <i class="bi bi-star-fill"></i>
        </div>
        <div class="text-start">
          <div class="fw-bold text-dark" style="font-size: 0.76rem; line-height: 1.2;">IT Security Review</div>
          <div class="text-muted" style="font-size: 0.68rem;">Operations Center · Active</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Inner Canvas with Gradient Hero Banner & Modern Metric Cards -->
  <div class="p-3 p-md-4">
    <!-- Hero Banner Card with Layered Smooth Waves and Exact Reference Icons -->
    <div class="card-hero-neo mb-4">
      <!-- Wave Layer Overlays at Bottom -->
      <svg class="position-absolute bottom-0 start-0 w-100" style="height: 60px; pointer-events: none; opacity: 0.22;" preserveAspectRatio="none" viewBox="0 0 1200 120">
        <path d="M0,0 C150,90 400,100 600,40 C800,-20 1050,80 1200,30 L1200,120 L0,120 Z" fill="#FFFFFF"></path>
      </svg>
      <svg class="position-absolute bottom-0 start-0 w-100" style="height: 40px; pointer-events: none; opacity: 0.32;" preserveAspectRatio="none" viewBox="0 0 1200 120">
        <path d="M0,40 C300,10 600,80 900,30 C1050,10 1150,50 1200,60 L1200,120 L0,120 Z" fill="#FFFFFF"></path>
      </svg>

      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 position-relative" style="z-index: 2;">
        <div style="max-width: 620px;">
          <h2 class="display-6 fw-bold mb-1 text-white" style="letter-spacing: -0.025em;">Bank Mitra IT Operations</h2>
          <p class="text-white text-opacity-95 mb-3" style="font-size: 0.98rem; font-weight: 500;">
            Kelola akun petugas, otoritas hak akses cabang, serta verifikasi autentikasi biometrik wajah presensi.
          </p>
          <div class="d-flex flex-wrap align-items-center gap-2.5">
            <a href="#formAddUser" class="btn-hero-pill text-decoration-none d-inline-flex align-items-center gap-2">
              <i class="bi bi-person-plus-fill"></i> Tambah Pengguna Baru
            </a>
          </div>
        </div>

        <!-- Decorative Right Badges (Exact Teardrop Pin & Radiating Sun Icon from Reference) -->
        <div class="d-none d-md-flex align-items-center gap-4">
          <!-- Teardrop Location Marker -->
          <div class="hero-pin-marker" title="Data Center IT Bank Mitra">
            <svg width="40" height="48" viewBox="0 0 44 52" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M22 0C9.85 0 0 9.85 0 22C0 36 22 52 22 52C22 52 44 36 44 22C44 9.85 34.15 0 22 0Z" fill="#1D4ED8"/>
              <circle cx="22" cy="22" r="9" fill="#FFFFFF"/>
            </svg>
          </div>

          <!-- Radiating Yellow Sun Icon -->
          <div class="hero-sun-container" title="System Operational & Normal">
            <svg width="68" height="68" viewBox="0 0 68 68" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="34" cy="34" r="17" fill="#FDE047"/>
              <rect x="31" y="2" width="6" height="10" rx="3" fill="#FDE047"/>
              <rect x="31" y="56" width="6" height="10" rx="3" fill="#FDE047"/>
              <rect x="2" y="31" width="10" height="6" rx="3" fill="#FDE047"/>
              <rect x="56" y="31" width="10" height="6" rx="3" fill="#FDE047"/>
              <rect x="11.5" y="11.5" width="6" height="10" rx="3" transform="rotate(-45 11.5 11.5)" fill="#FDE047"/>
              <rect x="49.5" y="49.5" width="6" height="10" rx="3" transform="rotate(-45 49.5 49.5)" fill="#FDE047"/>
              <rect x="49.5" y="11.5" width="10" height="6" rx="3" transform="rotate(-45 49.5 11.5)" fill="#FDE047"/>
              <rect x="11.5" y="49.5" width="10" height="6" rx="3" transform="rotate(-45 11.5 49.5)" fill="#FDE047"/>
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- 3 Symmetrical Metric Cards (Exact Proportions, Balanced Heights) -->
    <div class="row g-3 mb-2">
      <!-- Card 1: Visitors Today / Total Pengguna -->
      <div class="col-md-4">
        <div class="card-metric-modern">
          <div>
            <div class="metric-title-muted">Total Pengguna Terdaftar</div>
            <div class="metric-number-bold">'.number_format($totalUsers).'</div>
          </div>
          <div class="small text-muted mt-2 d-flex align-items-center gap-2" style="font-size: 0.78rem;">
            <span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> '.$countActive.' Aktif</span>
            <span class="text-secondary">·</span>
            <span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill"></i> '.$countNonactive.' Nonaktif</span>
          </div>
        </div>
      </div>

      <!-- Card 2: Customer Rating / Biometrik Wajah Rating -->
      <div class="col-md-4">
        <div class="card-metric-modern">
          <div>
            <div class="metric-title-muted">Verifikasi Biometrik Wajah</div>
            <div class="metric-number-bold d-flex align-items-center gap-2">
              <span>'.$ratingScore.'</span>
              <i class="bi bi-star-fill text-warning" style="font-size: 1.5rem;"></i>
            </div>
          </div>
          <div class="small text-muted mt-2" style="font-size: 0.78rem;">
            <span class="fw-bold text-dark">'.$countFaceVerified.'</span> dari '.$totalUsers.' petugas terverifikasi
          </div>
        </div>
      </div>

      <!-- Card 3: Open Now / Status Sistem Mint Green -->
      <div class="col-md-4">
        <div class="card-status-mint">
          <div>
            <div class="metric-title-muted" style="color: #047857 !important;">Status Layanan Sistem</div>
            <div class="status-heading mt-1">
              <span class="pulse-dot-green"></span>
              <span>Sistem Aktif</span>
            </div>
          </div>
          <div class="status-subtext mt-2">
            <i class="bi bi-shield-check me-1"></i> 24/7 IT Operations Center · Online
          </div>
        </div>
      </div>
    </div>

    <!-- Upward Trend Chart with Overlapping Floating Trend Badge (Exact Reference Layout) -->
    <div class="position-relative mt-2 pt-1 mb-2">
      <div class="w-100 position-relative overflow-hidden rounded-4 trend-chart-box" style="height: 72px; border: 1px solid #E2E8F0;">
        <svg viewBox="0 0 1000 72" preserveAspectRatio="none" style="width: 100%; height: 100%;">
          <defs>
            <linearGradient id="chartGrad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#10B981" stop-opacity="0.28"/>
              <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
            </linearGradient>
          </defs>
          <path d="M0,58 Q250,48 500,34 T1000,10 L1000,72 L0,72 Z" fill="url(#chartGrad)"/>
          <path d="M0,58 Q250,48 500,34 T1000,10" fill="none" stroke="#10B981" stroke-width="3" stroke-linecap="round"/>
          <circle cx="994" cy="10" r="5" fill="#10B981" stroke="#FFFFFF" stroke-width="2"/>
        </svg>
        <div class="position-absolute top-0 end-0 p-2 text-muted" style="font-size: 0.72rem; font-weight: 600;">
          Aktivitas Petugas · 7 Hari Terakhir
        </div>
      </div>

      <!-- Floating Trend Badge Overlapping Left of Chart -->
      <div class="position-absolute" style="left: 16px; bottom: 10px; z-index: 4;">
        <div class="badge-floating-trend shadow">
          <div class="trend-icon-box">
            <i class="bi bi-graph-up-arrow"></i>
          </div>
          <div>
            <div class="fw-bold text-dark" style="font-size: 0.88rem;">+'.$countTeknisi.' teknisi bertugas</div>
            <div class="text-muted small" style="font-size: 0.74rem;">'.$bioPercent.'% cakupan biometrik wajah terverifikasi</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- DATA SECTION: TABLE & STICKY FORM (PERFECTLY SYMMETRICAL TOP ACCENT STRIPES & INPUTS) -->
<div class="row g-4 align-items-start">
  <!-- Kolom Kiri: Tabel Daftar Pengguna & Live Search Toolbar -->
  <div class="col-xl-8 col-lg-7">
    <div class="card p-0 overflow-hidden shadow-sm h-100 border-0" style="border-radius: 20px;">
      
      <!-- Table Header & Live Search Bar with Symmetrical Responsive Inputs -->
      <div class="card-header bg-white py-3.5 px-4 border-bottom" style="border-top: 4px solid #0EA5E9; border-top-left-radius: 20px; border-top-right-radius: 20px;">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
          <div class="fw-bold text-dark text-uppercase small d-flex align-items-center gap-2" style="letter-spacing: 0.05em;">
            <i class="bi bi-person-badge text-primary fs-5"></i>
            <span>Daftar Pengguna Sistem</span>
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-2.5 py-1 font-monospace" id="badgeTotalUsers">'.$totalUsers.'</span>
          </div>
          <div class="small text-muted" id="userFilteredCounter">
            Menampilkan <strong id="shownCount" class="text-dark">'.$totalUsers.'</strong> pengguna
          </div>
        </div>

        <!-- Filter & Search Toolbar Controls (All 38px High, Perfectly Symmetrical) -->
        <div class="row g-2 align-items-center">
          <div class="col-lg-5 col-md-12">
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0 text-muted" style="border-radius: 9999px 0 0 9999px; height: 38px;"><i class="bi bi-search"></i></span>
              <input type="text" id="userSearchInput" class="form-control bg-light border-start-0 ps-0" style="border-radius: 0 9999px 9999px 0; font-size: 0.84rem; height: 38px;" placeholder="Cari nama, username, no. telp...">
              <button class="btn btn-outline-secondary border" type="button" id="btnClearSearch" title="Reset Pencarian" style="display: none; border-radius: 9999px; height: 38px;"><i class="bi bi-x-lg"></i></button>
            </div>
          </div>
          <div class="col-lg-7 col-md-12">
            <div class="row g-2">
              <div class="col-4">
                <select id="userFilterRole" class="form-select form-select-sm" style="border-radius: 9999px; height: 38px; font-size: 0.8rem;">
                  <option value="all">Semua Peran</option>
                  <option value="teknisi">Teknisi IT</option>
                  <option value="sdm">SDM &amp; Umum</option>
                  <option value="auditor">Auditor</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
              <div class="col-4">
                <select id="userFilterBio" class="form-select form-select-sm" style="border-radius: 9999px; height: 38px; font-size: 0.8rem;">
                  <option value="all">Biometrik</option>
                  <option value="verified">Terverifikasi</option>
                  <option value="pending">Menunggu</option>
                  <option value="none">Belum Ada</option>
                </select>
              </div>
              <div class="col-4">
                <select id="userFilterStatus" class="form-select form-select-sm" style="border-radius: 9999px; height: 38px; font-size: 0.8rem;">
                  <option value="all">Status</option>
                  <option value="aktif">Aktif</option>
                  <option value="nonaktif">Nonaktif</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- User List Table with Symmetrical Column Widths and Cell Alignments -->
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="userTable">
          <thead>
            <tr class="text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em; background: #F8FAFC;">
              <th style="width: 50px;" class="text-center font-monospace">NO</th>
              <th style="min-width: 220px;">PROFIL PETUGAS</th>
              <th style="width: 140px;" class="text-center">PERAN</th>
              <th style="width: 160px;" class="text-center">BIOMETRIK</th>
              <th style="width: 100px;" class="text-center">STATUS</th>
              <th style="width: 120px;" class="text-center">AKSI</th>
            </tr>
          </thead>
          <tbody id="userTableBody">
            '.$userRowsHtml.'
          </tbody>
        </table>
      </div>

      <!-- No Results State -->
      <div id="noResultsState" class="text-center py-5 d-none">
        <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center p-3 mb-3 text-muted">
          <i class="bi bi-search fs-2"></i>
        </div>
        <h6 class="fw-bold text-dark mb-1">Pengguna Tidak Ditemukan</h6>
        <p class="text-muted small mb-3">Tidak ada data pengguna yang sesuai dengan kata kunci atau filter yang Anda pilih.</p>
        <button type="button" class="btn btn-outline-primary btn-sm btn-pill" id="btnResetAllFilters">
          <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Semua Filter
        </button>
      </div>
    </div>
  </div>

  <!-- Kolom Kanan: Form Tambah / Edit Pengguna (Sticky, Perfectly Symmetrical Field Groups) -->
  <div class="col-xl-4 col-lg-5">
    <div class="card p-0 overflow-hidden shadow-sm border-0 sticky-form" style="border-radius: 20px;">
      '.$formCardHeader.'
      '.$formContent.'
    </div>
  </div>
</div>';

// Modal Pratinjau Foto Wajah Petugas (High Elevation & Symmetrical Centered Layout)
$modalHtml = <<<HTML
<div class="modal fade" id="modalPreviewFace" tabindex="-1" aria-labelledby="modalPreviewFaceLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
      <div class="modal-header py-3 px-4 bg-dark text-white border-0" style="background: linear-gradient(135deg, #051329 0%, #0A1E38 100%) !important;">
        <h6 class="modal-title fs-6 fw-bold mb-0 d-flex align-items-center gap-2" id="modalPreviewFaceLabel">
          <i class="bi bi-person-bounding-box text-primary"></i> Foto Sampel Biometrik
        </h6>
        <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4">
        <div class="position-relative d-inline-block mb-3">
          <img id="previewFaceImg" src="" alt="Foto Wajah Petugas" class="rounded-circle shadow-sm border border-4 border-white" style="width: 150px; height: 150px; object-fit: cover; box-shadow: 0 4px 18px rgba(37,99,235,0.2) !important;">
          <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-success p-2 border border-2 border-white" title="Biometrik Terdaftar">
            <i class="bi bi-shield-check fs-6"></i>
          </span>
        </div>
        <h5 class="fw-bold mb-1 text-dark" id="previewFaceNama">-</h5>
        <div class="text-muted small font-monospace mb-2" id="previewFaceUser">@-</div>
        <div id="previewFaceRoleBadge" class="mb-3"></div>
        <div class="p-3 bg-light rounded-4 text-muted small border text-start" style="font-size: 0.78rem; line-height: 1.5;">
          <div class="d-flex align-items-center gap-1.5 fw-bold text-dark mb-1">
            <i class="bi bi-shield-lock-fill text-primary"></i> Basis Data Biometrik Terverifikasi
          </div>
          Vektor wajah disimpan secara terenkripsi untuk memvalidasi presensi petugas &amp; kepemilikan checklist kartu pemeliharaan.
        </div>
      </div>
      <div class="modal-footer py-2.5 px-4 bg-light border-0 justify-content-between">
        <a id="btnPreviewEnrollAgain" href="#" class="btn btn-outline-primary btn-sm btn-pill fw-semibold"><i class="bi bi-camera me-1"></i> Foto Ulang</a>
        <button type="button" class="btn btn-secondary btn-sm px-4 btn-pill fw-semibold" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<style>
/* SYMMETRY & BALANCED LAYOUT SYSTEM */
.cursor-pointer { cursor: pointer; }
.hover-primary:hover { color: #2563EB !important; }

/* Balanced 3-Column Topbar for Pure Mathematical Center */
.browser-topbar-col-left {
  width: 220px;
  display: flex;
  align-items: center;
}
.browser-topbar-col-center {
  flex-grow: 1;
  display: flex;
  justify-content: center;
}
.browser-topbar-col-right {
  width: 220px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
}
@media (max-width: 768px) {
  .browser-topbar-col-left { width: auto; }
  .browser-topbar-col-right { display: none !important; }
}

/* Symmetrical Action Buttons (Uniform 32x32px square) */
.action-btn-sq {
  width: 32px !important;
  height: 32px !important;
  padding: 0 !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  border-radius: 8px !important;
  font-size: 0.85rem !important;
}
.action-btn-sq-placeholder {
  width: 32px !important;
  height: 32px !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  font-size: 0.85rem !important;
}

/* Symmetrical Form Input Groups */
.form-label-custom {
  font-size: 0.72rem !important;
  letter-spacing: 0.04em !important;
  margin-bottom: 5px !important;
}
.input-icon-box {
  width: 42px !important;
  height: 42px !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  border-radius: 12px 0 0 12px !important;
  border-color: #E2E8F0 !important;
}
.form-control-custom {
  height: 42px !important;
  border-radius: 0 12px 12px 0 !important;
  border-color: #E2E8F0 !important;
  font-size: 0.86rem !important;
}

/* Reference Image Icons */
.hero-pin-marker {
  display: flex;
  align-items: center;
  justify-content: center;
  filter: drop-shadow(0 4px 12px rgba(29, 78, 216, 0.4));
  transition: transform 0.2s ease;
}
.hero-pin-marker:hover {
  transform: translateY(-2px);
}
.hero-sun-container {
  display: flex;
  align-items: center;
  justify-content: center;
  filter: drop-shadow(0 0 16px rgba(253, 224, 71, 0.6));
  animation: sunGlow 4s infinite ease-in-out;
}
@keyframes sunGlow {
  0%, 100% { transform: scale(1); filter: drop-shadow(0 0 14px rgba(253, 224, 71, 0.5)); }
  50% { transform: scale(1.05); filter: drop-shadow(0 0 22px rgba(253, 224, 71, 0.8)); }
}

@media (min-width: 992px) {
  .sticky-form {
    position: sticky;
    top: 84px;
    z-index: 10;
  }
}
</style>

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
  var roleStr = (role || "").toLowerCase();
  if (roleStr === "admin" || roleStr === "administrator") {
    roleBadge = '<span class="badge bg-primary" style="border-radius: 9999px;"><i class="bi bi-shield-lock-fill me-1"></i> Administrator</span>';
  } else if (roleStr.indexOf("sdm") !== -1 || roleStr.indexOf("hrd") !== -1 || roleStr.indexOf("umum") !== -1) {
    roleBadge = '<span class="badge bg-warning text-dark" style="border-radius: 9999px;"><i class="bi bi-person-workspace me-1"></i> SDM & Umum</span>';
  } else if (roleStr === "auditor" || roleStr === "skai") {
    roleBadge = '<span class="badge bg-secondary" style="border-radius: 9999px;"><i class="bi bi-eye-fill me-1"></i> Auditor</span>';
  } else {
    roleBadge = '<span class="badge bg-info text-dark" style="border-radius: 9999px;"><i class="bi bi-tools me-1"></i> Teknisi IT</span>';
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
  // Password Visibility Toggle (Add Form)
  var btnToggleAdd = document.getElementById("btnToggleAddPassword");
  var inputAdd = document.getElementById("addPasswordInput");
  if (btnToggleAdd && inputAdd) {
    btnToggleAdd.addEventListener("click", function() {
      var isPw = inputAdd.type === "password";
      inputAdd.type = isPw ? "text" : "password";
      btnToggleAdd.innerHTML = isPw ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
    });
  }

  // Password Visibility Toggle (Edit Form)
  var btnToggleEdit = document.getElementById("btnToggleEditPassword");
  var inputEdit = document.getElementById("editPasswordInput");
  if (btnToggleEdit && inputEdit) {
    btnToggleEdit.addEventListener("click", function() {
      var isPw = inputEdit.type === "password";
      inputEdit.type = isPw ? "text" : "password";
      btnToggleEdit.innerHTML = isPw ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
    });
  }

  // Face Photo Preview Trigger
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

  // Client-Side Instant Live Search & Multi-Filter
  var searchInput = document.getElementById("userSearchInput");
  var clearSearchBtn = document.getElementById("btnClearSearch");
  var filterRole = document.getElementById("userFilterRole");
  var filterBio = document.getElementById("userFilterBio");
  var filterStatus = document.getElementById("userFilterStatus");
  var rows = document.querySelectorAll(".user-row");
  var shownCountEl = document.getElementById("shownCount");
  var noResultsEl = document.getElementById("noResultsState");
  var tableEl = document.getElementById("userTable");
  var btnResetAll = document.getElementById("btnResetAllFilters");

  function runUserFilter() {
    var q = (searchInput ? searchInput.value : "").trim().toLowerCase();
    var rVal = filterRole ? filterRole.value : "all";
    var bVal = filterBio ? filterBio.value : "all";
    var sVal = filterStatus ? filterStatus.value : "all";

    if (clearSearchBtn) {
      clearSearchBtn.style.display = q ? "inline-block" : "none";
    }

    var visibleCount = 0;
    rows.forEach(function(row) {
      var rNama = row.getAttribute("data-nama") || "";
      var rUser = row.getAttribute("data-username") || "";
      var rPhone = row.getAttribute("data-phone") || "";
      var rRole = row.getAttribute("data-role") || "";
      var rBio = row.getAttribute("data-bio") || "";
      var rStat = row.getAttribute("data-status") || "";

      var matchesSearch = !q || (rNama.indexOf(q) !== -1 || rUser.indexOf(q) !== -1 || rPhone.indexOf(q) !== -1);
      var matchesRole = (rVal === "all") || (rRole === rVal);
      var matchesBio = (bVal === "all") || (rBio === bVal);
      var matchesStat = (sVal === "all") || (rStat === sVal);

      if (matchesSearch && matchesRole && matchesBio && matchesStat) {
        row.style.display = "";
        visibleCount++;
      } else {
        row.style.display = "none";
      }
    });

    if (shownCountEl) {
      shownCountEl.innerText = visibleCount;
    }

    if (visibleCount === 0 && rows.length > 0) {
      if (noResultsEl) noResultsEl.classList.remove("d-none");
      if (tableEl) tableEl.classList.add("d-none");
    } else {
      if (noResultsEl) noResultsEl.classList.add("d-none");
      if (tableEl) tableEl.classList.remove("d-none");
    }
  }

  if (searchInput) searchInput.addEventListener("input", runUserFilter);
  if (filterRole) filterRole.addEventListener("change", runUserFilter);
  if (filterBio) filterBio.addEventListener("change", runUserFilter);
  if (filterStatus) filterStatus.addEventListener("change", runUserFilter);

  if (clearSearchBtn) {
    clearSearchBtn.addEventListener("click", function() {
      if (searchInput) {
        searchInput.value = "";
        searchInput.focus();
      }
      runUserFilter();
    });
  }

  if (btnResetAll) {
    btnResetAll.addEventListener("click", function() {
      if (searchInput) searchInput.value = "";
      if (filterRole) filterRole.value = "all";
      if (filterBio) filterBio.value = "all";
      if (filterStatus) filterStatus.value = "all";
      runUserFilter();
    });
  }
});
</script>
HTML;

$body .= $modalHtml;

render_page('Kelola Akun Pengguna', $body);
