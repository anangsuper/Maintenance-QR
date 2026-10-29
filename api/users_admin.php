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
    } elseif ($action === 'approve_face' || $action === 'verify_face') {
        $id = (int)($_POST['user_id'] ?? 0);
        $targetUser = get_user_by_id($id);
        $targetNama = $targetUser['nama'] ?? "#{$id}";
        $res = update_user_face_status($id, 'verified');
        if (!empty($res['success'])) {
            get_user_list(true);
            if (function_exists('record_audit_log')) {
                record_audit_log('FACE_VERIFIED', 'PENGGUNA', current_user_id(), current_user_name(), "Biometrik wajah pengguna '{$targetNama}' telah diverifikasi & disetujui.");
            }
            $_SESSION['flash'] = "Biometrik wajah pengguna {$targetNama} (#{$id}) telah berhasil DIVERIFIKASI & DISETUJUI.";
            header('Location: ' . module_url('users_admin.php'));
            exit;
        } else {
            $error = $res['error'] ?? 'Gagal memverifikasi biometrik wajah.';
        }
    } elseif ($action === 'unverify_face') {
        $id = (int)($_POST['user_id'] ?? 0);
        $targetUser = get_user_by_id($id);
        $targetNama = $targetUser['nama'] ?? "#{$id}";
        $res = update_user_face_status($id, 'pending');
        if (!empty($res['success'])) {
            get_user_list(true);
            if (function_exists('record_audit_log')) {
                record_audit_log('FACE_UNVERIFIED', 'PENGGUNA', current_user_id(), current_user_name(), "Status verifikasi biometrik wajah pengguna '{$targetNama}' dikembalikan ke menunggu.");
            }
            $_SESSION['flash'] = "Status biometrik wajah pengguna {$targetNama} (#{$id}) dikembalikan ke Menunggu Verifikasi.";
            header('Location: ' . module_url('users_admin.php'));
            exit;
        } else {
            $error = $res['error'] ?? 'Gagal membatalkan status verifikasi biometrik wajah.';
        }
    } elseif ($action === 'delete_face' || $action === 'reject_face') {
        $id = (int)($_POST['user_id'] ?? 0);
        $targetUser = get_user_by_id($id);
        $targetNama = $targetUser['nama'] ?? "#{$id}";
        $res = update_user_face_status($id, 'deleted');
        if (!empty($res['success'])) {
            get_user_list(true);
            if (function_exists('record_audit_log')) {
                record_audit_log('FACE_DELETED', 'PENGGUNA', current_user_id(), current_user_name(), "Data biometrik wajah pengguna '{$targetNama}' telah dihapus.");
            }
            $_SESSION['flash'] = "Data biometrik wajah pengguna {$targetNama} (#{$id}) telah berhasil DIHAPUS dari sistem.";
            header('Location: ' . module_url('users_admin.php'));
            exit;
        } else {
            $error = $res['error'] ?? 'Gagal menghapus biometrik wajah.';
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

// -------------------------------------------------------------
// 5. Render Halaman (Kombai Teal Design System)
// -------------------------------------------------------------
$page_title = 'Kelola Akun Pengguna';

ob_start();
?>
<style>
/* Kombai Table & Badges */
.status-pill {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 9999px;
  white-space: nowrap !important;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  line-height: 1.2;
}
.table thead th {
  background: #F4F5F7;
  color: #53595F;
  font-size: 11px;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  font-weight: 700;
  border-bottom: 1px solid #D1D5D8;
  padding: 10px 14px;
  vertical-align: middle;
}
.table tbody td {
  padding: 11px 14px;
  font-size: 13px;
  color: #1B2A3A;
  vertical-align: middle;
  border-bottom: 1px solid #E5E7EB;
}
.table-hover tbody tr:hover {
  background-color: #F8FAF9 !important;
}

/* Symmetrical Action Buttons (30x30px) */
.action-btn-sq {
  width: 30px !important;
  height: 30px !important;
  padding: 0 !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  border-radius: 6px !important;
  font-size: 0.82rem !important;
  transition: all 0.15s ease;
}
.action-btn-sq-placeholder {
  width: 30px !important;
  height: 30px !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  font-size: 0.82rem !important;
}

/* Symmetrical Form Input Controls */
.form-label-kombai {
  font-size: 11px !important;
  font-weight: 700 !important;
  color: #1B2A3A !important;
  text-transform: uppercase !important;
  letter-spacing: 0.04em !important;
  margin-bottom: 4px !important;
}
.input-icon-box {
  width: 38px !important;
  height: 38px !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  border-radius: 8px 0 0 8px !important;
  border-color: #D1D5D8 !important;
  background-color: #F8FAF9 !important;
  color: #53595F !important;
}
.form-control-kombai {
  height: 38px !important;
  border-radius: 0 8px 8px 0 !important;
  border-color: #D1D5D8 !important;
  font-size: 13px !important;
  color: #1B2A3A !important;
}
.form-control-kombai:focus, .form-select:focus {
  border-color: #0E5C58 !important;
  box-shadow: 0 0 0 2px rgba(14, 92, 88, 0.15) !important;
}
.cursor-pointer { cursor: pointer; }

@media (min-width: 992px) {
  .sticky-form {
    position: sticky;
    top: 84px;
    z-index: 10;
  }
}
</style>

<!-- HEADER UTAMA & BREADCRUMB -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
  <div>
    <div class="text-[11px] font-bold text-[#0E5C58] uppercase tracking-wider mb-1 flex items-center gap-1.5">
      <i class="bi bi-shield-lock-fill"></i> MANAJEMEN AKSES &amp; KEAMANAN
    </div>
    <div class="flex items-center gap-2.5 flex-wrap">
      <h1 class="text-[24px] font-bold text-[#1B2A3A] m-0 leading-tight">Kelola Akun Pengguna</h1>
      <span class="px-2.5 py-0.5 text-[11px] font-semibold bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC] rounded-full inline-flex items-center gap-1">
        <i class="bi bi-people-fill"></i> IT Operations
      </span>
    </div>
    <div class="text-[13px] text-[#53595F] mt-1">
      Manajemen kredensial login, wewenang hak akses peran sistem, dan verifikasi sampel biometrik wajah presensi teknisi.
    </div>
  </div>

  <div class="flex flex-wrap items-center gap-2 shrink-0">
    <a href="#formUserSection" class="h-[36px] px-3.5 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[13px] font-semibold transition-colors inline-flex items-center gap-1.5 border-0 shadow-xs text-decoration-none cursor-pointer">
      <i class="bi bi-person-plus-fill"></i> <span>Tambah Pengguna</span>
    </a>
    <a href="<?= e(module_url('user_biometric_enroll.php')) ?>" class="h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-camera text-[#0E5C58]"></i> <span>Pendaftaran Wajah</span>
    </a>
    <a href="<?= e(module_url('dashboard.php')) ?>" class="h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-speedometer2 text-[#53595F]"></i> <span>Dashboard</span>
    </a>
  </div>
</div>

<!-- ALERT / FLASH MESSAGES -->
<?php if ($flash): ?>
<div class="alert alert-success alert-dismissible fade show border-0 shadow-xs mb-4" style="border-left: 4px solid #10B981 !important; border-radius: 8px; background: #ECFDF5; color: #065F46;">
  <div class="d-flex align-items-center">
    <i class="bi bi-check-circle-fill me-2 fs-5 text-[#10B981]"></i>
    <div class="text-[13px]"><?= e($flash) ?></div>
  </div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show border-0 shadow-xs mb-4" style="border-left: 4px solid #EF4444 !important; border-radius: 8px; background: #FEF2F2; color: #991B1B;">
  <div class="d-flex align-items-center">
    <i class="bi bi-exclamation-triangle-fill me-2 fs-5 text-[#EF4444]"></i>
    <div class="text-[13px]"><?= e($error) ?></div>
  </div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- PENDING FACE APPROVAL BANNER (JIKA ADA PERMINTAAN SAMPEL WAJAH) -->
<?php if (!empty($pendingFaceUsers)): ?>
<div class="bg-[#FFFDF5] border border-[#FDE68A] rounded-[10px] p-4 mb-4 shadow-xs" style="border-left: 5px solid #F59E0B !important;">
  <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
    <div>
      <div class="flex items-center gap-2 mb-1">
        <span class="px-2 py-0.5 text-[11px] font-bold bg-[#FEF3C7] text-[#92400E] border border-[#FCD34D] rounded-full inline-flex items-center gap-1">
          <i class="bi bi-shield-exclamation"></i> MEMERLUKAN PERSETUJUAN ADMIN
        </span>
        <span class="px-2 py-0.5 text-[11px] font-bold bg-[#F59E0B] text-white rounded-full font-mono"><?= count($pendingFaceUsers) ?> Permintaan</span>
      </div>
      <h6 class="text-[15px] font-bold text-[#1B2A3A] mb-0 flex items-center gap-1.5">
        <i class="bi bi-person-bounding-box text-[#F59E0B]"></i> Verifikasi Sampel Biometrik Wajah Teknisi Mobile
      </h6>
      <div class="text-[12px] text-[#53595F] mt-0.5">
        Petugas telah mendaftarkan sampel foto wajah melalui smartphone saat bertugas. Silakan tinjau dan setujui untuk autentikasi checklist &amp; presensi.
      </div>
    </div>
  </div>

  <div class="row g-3">
    <?php foreach ($pendingFaceUsers as $pu): 
      $puId = (int)$pu['id'];
      $puNama = (string)($pu['nama'] ?? 'Tanpa Nama');
      $puUsername = (string)($pu['username'] ?? '');
      $puRole = (string)($pu['role'] ?? 'teknisi');
      $puPhoto = trim((string)($pu['face_photo'] ?? ''));
    ?>
    <div class="col-md-6 col-lg-4">
      <div class="bg-white border border-[#E5E7EB] rounded-[10px] p-3 shadow-xs h-100 flex flex-col justify-between">
        <div class="flex items-center gap-3 mb-2.5">
          <?php if ($puPhoto !== ''): ?>
            <img src="<?= e($puPhoto) ?>" class="rounded-full border border-[#D1D5D8] shadow-xs js-preview-face cursor-pointer shrink-0" style="width: 44px; height: 44px; object-fit: cover;" data-photo="<?= e($puPhoto) ?>" data-nama="<?= e($puNama) ?>" data-username="<?= e($puUsername) ?>" data-role="<?= e($puRole) ?>" data-id="<?= $puId ?>" data-stat="pending" data-enroll-url="<?= e(module_url('user_biometric_enroll.php', ['id' => $puId])) ?>" title="Klik untuk pratinjau wajah">
          <?php else: ?>
            <div class="rounded-full bg-[#FEF3C7] text-[#D97706] font-bold flex items-center justify-center shrink-0 border border-[#FCD34D]" style="width: 44px; height: 44px;">
              <i class="bi bi-person-bounding-box"></i>
            </div>
          <?php endif; ?>
          <div class="min-w-0 flex-1">
            <div class="text-[13px] font-bold text-[#1B2A3A] truncate"><?= e($puNama) ?></div>
            <div class="text-[11px] font-mono text-[#53595F] truncate">@<?= e($puUsername) ?> &middot; <?= ucfirst(e($puRole)) ?></div>
            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-[#D97706] bg-[#FEF3C7] px-1.5 py-0.5 rounded-full mt-0.5">
              <i class="bi bi-hourglass-split"></i> Menunggu Verifikasi
            </span>
          </div>
        </div>
        <div class="flex gap-2 pt-2 border-t border-[#F3F4F6] mt-auto">
          <form method="post" class="flex-1 m-0" onsubmit="return confirm('Setujui dan aktifkan biometrik wajah <?= addslashes($puNama) ?>?')">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="approve_face">
            <input type="hidden" name="user_id" value="<?= $puId ?>">
            <button type="submit" class="w-full h-[30px] bg-[#10B981] hover:bg-[#059669] text-white rounded-[6px] text-[12px] font-semibold transition-colors flex items-center justify-center gap-1 border-0 cursor-pointer">
              <i class="bi bi-check-circle-fill"></i> Setujui
            </button>
          </form>
          <form method="post" class="m-0" onsubmit="return confirm('Tolak sampel foto wajah <?= addslashes($puNama) ?>?')">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="reject_face">
            <input type="hidden" name="user_id" value="<?= $puId ?>">
            <button type="submit" class="h-[30px] px-2.5 bg-white hover:bg-[#FEE2E2] text-[#DC2626] border border-[#FECACA] rounded-[6px] text-[12px] font-semibold transition-colors flex items-center justify-center gap-1 cursor-pointer" title="Tolak Pendaftaran">
              <i class="bi bi-x-circle"></i> Tolak
            </button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- 3 SYMMETRICAL KOMBAI METRIC CARDS (88px) -->
<div class="bg-[#FFFFFF] border border-[#D1D5D8] rounded-[10px] h-[88px] flex overflow-hidden shrink-0 mb-4">
  <!-- Card 1: Total Pengguna Terdaftar -->
  <div class="flex-1 flex flex-col justify-center px-6 border-r border-[#D1D5D8] relative hover:bg-[#F4F5F7] transition-colors">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0E5C58]"></div>
    <div class="text-[28px] font-bold text-[#1B2A3A] tabular-nums leading-none">
      <?= number_format($totalUsers) ?> <span class="text-[13px] font-normal text-[#53595F]">Pengguna</span>
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-2">
      <span class="text-[#10B981] font-semibold"><i class="bi bi-check-circle-fill me-0.5"></i> <?= $countActive ?> Aktif</span>
      <span>&middot;</span>
      <span class="text-[#EF4444] font-semibold"><i class="bi bi-x-circle-fill me-0.5"></i> <?= $countNonactive ?> Nonaktif</span>
      <span class="text-[#53595F] ms-auto text-[11px] font-mono d-none d-lg-inline">100% Terkelola</span>
    </div>
  </div>

  <!-- Card 2: Biometrik Wajah Terverifikasi -->
  <div class="flex-1 flex flex-col justify-center px-6 border-r border-[#D1D5D8] relative hover:bg-[#F4F5F7] transition-colors">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#10B981]"></div>
    <div class="text-[28px] font-bold text-[#10B981] tabular-nums leading-none">
      <?= $countFaceVerified ?> <span class="text-[13px] font-normal text-[#53595F]">/ <?= $totalUsers ?> Petugas</span>
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
      <span class="text-[#0E5C58] font-bold"><?= $bioPercent ?>% Terverifikasi</span>
      <span>&middot;</span>
      <?php if ($countFacePending > 0): ?>
        <span class="text-[#D97706] font-semibold"><i class="bi bi-hourglass-split"></i> <?= $countFacePending ?> Menunggu Approval</span>
      <?php else: ?>
        <span class="text-[#53595F]"><i class="bi bi-check-all text-[#10B981]"></i> Semua Wajah Lengkap</span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Card 3: Distribusi Peran Akses -->
  <div class="flex-1 flex flex-col justify-center px-6 relative hover:bg-[#F4F5F7] transition-colors">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0284C7]"></div>
    <div class="text-[28px] font-bold text-[#1B2A3A] tabular-nums leading-none">
      <?= $countTeknisi ?> <span class="text-[13px] font-normal text-[#53595F]">Teknisi IT</span>
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-2">
      <span class="text-[#0284C7] font-semibold"><i class="bi bi-tools me-0.5"></i> <?= $countTeknisi ?> IT</span>
      <span>&middot;</span>
      <span class="text-[#D97706] font-semibold"><i class="bi bi-person-workspace me-0.5"></i> <?= $countSdm ?> SDM</span>
      <span>&middot;</span>
      <span class="text-[#0E5C58] font-semibold"><i class="bi bi-shield-lock-fill me-0.5"></i> <?= $countAdmin ?> Admin</span>
      <span>&middot;</span>
      <span class="text-[#64748B] font-semibold"><i class="bi bi-eye-fill me-0.5"></i> <?= $countAuditor ?> Auditor</span>
    </div>
  </div>
</div>

<!-- SECTION DATA: TABEL PENGGUNA (KIRI) & FORM STICKY (KANAN) -->
<div class="row g-4 align-items-start">
  <!-- KOLOM KIRI: TABEL DAFTAR PENGGUNA & FILTER TOOLBAR -->
  <div class="col-xl-8 col-lg-7">
    <div class="bg-white border border-[#D1D5D8] rounded-[12px] shadow-xs overflow-hidden mb-4">
      
      <!-- Table Header & Counter -->
      <div class="px-5 py-3.5 border-b border-[#D1D5D8] flex flex-wrap items-center justify-between gap-3 bg-white">
        <div class="flex items-center gap-2">
          <i class="bi bi-people-fill text-[#0E5C58] text-[16px]"></i>
          <span class="text-[14px] font-bold text-[#1B2A3A] uppercase tracking-wide">Daftar Pengguna Sistem</span>
          <span class="px-2 py-0.5 text-[11px] font-bold bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC] rounded-full font-mono" id="badgeTotalUsers"><?= $totalUsers ?></span>
        </div>
        <div class="text-[12px] text-[#53595F]" id="userFilteredCounter">
          Menampilkan <strong id="shownCount" class="text-[#1B2A3A]"><?= $totalUsers ?></strong> pengguna
        </div>
      </div>

      <!-- Filter Toolbar (Symmetrical 36px) -->
      <div class="p-3.5 border-b border-[#D1D5D8] bg-[#F8FAF9]">
        <div class="row g-2 align-items-center m-0">
          <div class="col-lg-5 col-md-12 p-0 pe-md-1">
            <div class="input-group">
              <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 8px 0 0 8px; height: 36px; border-color: #D1D5D8;"><i class="bi bi-search"></i></span>
              <input type="text" id="userSearchInput" class="form-control border-start-0 ps-0" style="border-radius: 0 8px 8px 0; font-size: 13px; height: 36px; border-color: #D1D5D8;" placeholder="Cari nama, username, telepon...">
              <button class="btn btn-outline-secondary border" type="button" id="btnClearSearch" title="Reset Pencarian" style="display: none; border-radius: 8px; height: 36px; border-color: #D1D5D8;"><i class="bi bi-x-lg"></i></button>
            </div>
          </div>
          <div class="col-lg-7 col-md-12 p-0 ps-md-1">
            <div class="row g-2 m-0">
              <div class="col-4 p-0 pe-1">
                <select id="userFilterRole" class="form-select form-select-sm" style="border-radius: 8px; height: 36px; font-size: 13px; border-color: #D1D5D8;">
                  <option value="all">Semua Peran</option>
                  <option value="teknisi">Teknisi IT</option>
                  <option value="sdm">SDM &amp; Umum</option>
                  <option value="auditor">Auditor</option>
                  <option value="admin">Administrator</option>
                </select>
              </div>
              <div class="col-4 p-0 px-1">
                <select id="userFilterBio" class="form-select form-select-sm" style="border-radius: 8px; height: 36px; font-size: 13px; border-color: #D1D5D8;">
                  <option value="all">Semua Biometrik</option>
                  <option value="verified">Terverifikasi</option>
                  <option value="pending">Menunggu</option>
                  <option value="none">Belum Ada</option>
                </select>
              </div>
              <div class="col-4 p-0 ps-1">
                <select id="userFilterStatus" class="form-select form-select-sm" style="border-radius: 8px; height: 36px; font-size: 13px; border-color: #D1D5D8;">
                  <option value="all">Semua Status</option>
                  <option value="aktif">Aktif</option>
                  <option value="nonaktif">Nonaktif</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabel Pengguna -->
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="userTable">
          <thead>
            <tr>
              <th style="width: 45px;" class="text-center">NO</th>
              <th style="min-width: 220px;">PROFIL PETUGAS</th>
              <th style="width: 130px;" class="text-center">PERAN</th>
              <th style="width: 150px;" class="text-center">BIOMETRIK WAJAH</th>
              <th style="width: 95px;" class="text-center">STATUS</th>
              <th style="width: 145px;" class="text-center">AKSI</th>
            </tr>
          </thead>
          <tbody id="userTableBody">
            <?php 
            $no = 0;
            foreach ($users as $u):
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

              // Filter Group
              $filterRoleGroup = ($uRole === 'admin' || $uRole === 'administrator') ? 'admin' : ((in_array($uRole, ['sdm', 'hrd', 'umum', 'hrd_umum', 'sdm_umum', 'sdm & umum', 'sdm/hrd'], true) || str_contains($uRole, 'sdm')) ? 'sdm' : (($uRole === 'auditor' || $uRole === 'skai') ? 'auditor' : 'teknisi'));
              $filterBioGroup = ($fStat === 'verified' || $fStat === 'terverifikasi') ? 'verified' : (($fStat === 'pending' || $fStat === 'menunggu') ? 'pending' : 'none');
              $filterStatusGroup = (strcasecmp($uStatus, 'Nonaktif') === 0) ? 'nonaktif' : 'aktif';

              // Role Badge
              if ($filterRoleGroup === 'admin') {
                $roleBadge = '<span class="status-pill" style="background:#D9F1EF; color:#0E5C58; border:1px solid #A7E2DC;"><i class="bi bi-shield-lock-fill me-1"></i> Admin</span>';
              } elseif ($filterRoleGroup === 'sdm') {
                $roleBadge = '<span class="status-pill" style="background:#FEF3C7; color:#92400E; border:1px solid #FCD34D;"><i class="bi bi-person-workspace me-1"></i> SDM &amp; Umum</span>';
              } elseif ($filterRoleGroup === 'auditor') {
                $roleBadge = '<span class="status-pill" style="background:#F1F5F9; color:#475569; border:1px solid #CBD5E1;"><i class="bi bi-eye-fill me-1"></i> Auditor</span>';
              } else {
                $roleBadge = '<span class="status-pill" style="background:#E0F2FE; color:#0369A1; border:1px solid #BAE6FD;"><i class="bi bi-tools me-1"></i> Teknisi IT</span>';
              }

              // Status Badge
              if ($filterStatusGroup === 'nonaktif') {
                $statusBadge = '<span class="status-pill" style="background:#FEF2F2; color:#991B1B; border:1px solid #FECACA;"><i class="bi bi-x-circle-fill me-1"></i> Nonaktif</span>';
              } else {
                $statusBadge = '<span class="status-pill" style="background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0;"><i class="bi bi-check-circle-fill me-1"></i> Aktif</span>';
              }

              // Biometric Status
              $hasFace = ($uPhoto !== '' || $fDesc !== '');
              $isFaceVerified = ($fStat === 'verified' || $fStat === 'terverifikasi');
              $isFacePending = ($fStat === 'pending' || $fStat === 'menunggu');

              if (!$hasFace) {
                $bioBadge = '<a href="'.e(module_url('user_biometric_enroll.php', ['id'=>$uId])).'" class="status-pill text-decoration-none" style="background:#F3F4F6; color:#6B7280; border:1px solid #E5E7EB;" title="Klik untuk daftarkan wajah"><i class="bi bi-camera me-1"></i> Belum Ada</a>';
              } elseif ($isFaceVerified) {
                $thumb = $uPhoto ? '<img src="'.e($uPhoto).'" class="rounded-full border shadow-xs js-preview-face cursor-pointer me-1.5" style="width: 22px; height: 22px; object-fit: cover;" data-photo="'.e($uPhoto).'" data-nama="'.e($uNama).'" data-username="'.e($uUsername).'" data-role="'.e($uRole).'" data-id="'.$uId.'" data-stat="'.e($fStat).'" data-enroll-url="'.e(module_url('user_biometric_enroll.php', ['id'=>$uId])).'">' : '';
                $bioBadge = '<div class="d-inline-flex items-center">'.$thumb.'<span class="status-pill js-preview-face cursor-pointer" style="background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0;" data-photo="'.e($uPhoto).'" data-nama="'.e($uNama).'" data-username="'.e($uUsername).'" data-role="'.e($uRole).'" data-id="'.$uId.'" data-stat="'.e($fStat).'" data-enroll-url="'.e(module_url('user_biometric_enroll.php', ['id'=>$uId])).'"><i class="bi bi-shield-check me-1"></i> Terverifikasi</span></div>';
              } elseif ($isFacePending) {
                $thumb = $uPhoto ? '<img src="'.e($uPhoto).'" class="rounded-full border border-warning shadow-xs js-preview-face cursor-pointer me-1.5" style="width: 22px; height: 22px; object-fit: cover;" data-photo="'.e($uPhoto).'" data-nama="'.e($uNama).'" data-username="'.e($uUsername).'" data-role="'.e($uRole).'" data-id="'.$uId.'" data-stat="'.e($fStat).'" data-enroll-url="'.e(module_url('user_biometric_enroll.php', ['id'=>$uId])).'">' : '';
                $bioBadge = '<div class="d-inline-flex items-center">'.$thumb.'<span class="status-pill js-preview-face cursor-pointer" style="background:#FEF3C7; color:#92400E; border:1px solid #FCD34D;" data-photo="'.e($uPhoto).'" data-nama="'.e($uNama).'" data-username="'.e($uUsername).'" data-role="'.e($uRole).'" data-id="'.$uId.'" data-stat="'.e($fStat).'" data-enroll-url="'.e(module_url('user_biometric_enroll.php', ['id'=>$uId])).'"><i class="bi bi-hourglass-split me-1"></i> Menunggu</span></div>';
              } else {
                $thumb = $uPhoto ? '<img src="'.e($uPhoto).'" class="rounded-full border border-danger shadow-xs js-preview-face cursor-pointer me-1.5" style="width: 22px; height: 22px; object-fit: cover;" data-photo="'.e($uPhoto).'" data-nama="'.e($uNama).'" data-username="'.e($uUsername).'" data-role="'.e($uRole).'" data-id="'.$uId.'" data-stat="'.e($fStat).'" data-enroll-url="'.e(module_url('user_biometric_enroll.php', ['id'=>$uId])).'">' : '';
                $bioBadge = '<div class="d-inline-flex items-center">'.$thumb.'<span class="status-pill js-preview-face cursor-pointer" style="background:#FEF2F2; color:#991B1B; border:1px solid #FECACA;" data-photo="'.e($uPhoto).'" data-nama="'.e($uNama).'" data-username="'.e($uUsername).'" data-role="'.e($uRole).'" data-id="'.$uId.'" data-stat="'.e($fStat).'" data-enroll-url="'.e(module_url('user_biometric_enroll.php', ['id'=>$uId])).'"><i class="bi bi-x-circle me-1"></i> Ditolak</span></div>';
              }

              // WhatsApp Link
              $telHtml = '';
              if ($uTel !== '-' && $uTel !== '') {
                $cleanPhone = preg_replace('/[^0-9]/', '', $uTel);
                $waPhone = str_starts_with($cleanPhone, '0') ? ('62' . substr($cleanPhone, 1)) : $cleanPhone;
                $telHtml = '<div class="text-[11px] text-[#53595F] mt-0.5"><a href="https://wa.me/'.e($waPhone).'" target="_blank" rel="noopener noreferrer" class="text-decoration-none text-[#53595F] hover:text-[#0E5C58] inline-flex items-center gap-1" title="Kirim Pesan WhatsApp"><i class="bi bi-whatsapp text-[#16A34A]"></i> <span>'.e($uTel).'</span></a></div>';
              }

              // Avatar
              if ($uPhoto !== '') {
                $dot = $isFaceVerified ? '<span class="position-absolute bottom-0 end-0 badge rounded-full bg-success p-0 border border-white" style="width: 9px; height: 9px;"></span>' : ($isFacePending ? '<span class="position-absolute bottom-0 end-0 badge rounded-full bg-warning p-0 border border-white" style="width: 9px; height: 9px;"></span>' : '');
                $avatarHtml = '<div class="position-relative js-preview-face cursor-pointer shrink-0" data-photo="'.e($uPhoto).'" data-nama="'.e($uNama).'" data-username="'.e($uUsername).'" data-role="'.e($uRole).'" data-id="'.$uId.'" data-stat="'.e($fStat).'" data-enroll-url="'.e(module_url('user_biometric_enroll.php', ['id'=>$uId])).'" title="Klik untuk kelola foto wajah"><img src="'.e($uPhoto).'" class="rounded-full border border-[#D1D5D8] shadow-xs" style="width: 40px; height: 40px; object-fit: cover;">'.$dot.'</div>';
              } else {
                $initial = strtoupper(substr($uNama ?: $uUsername, 0, 1));
                $avatarHtml = '<div class="rounded-full d-flex align-items-center justify-content-center font-bold text-white shadow-xs shrink-0" style="width: 40px; height: 40px; font-size: 0.95rem; background: linear-gradient(135deg, #0E5C58 0%, #1B2A3A 100%);">'.$initial.'</div>';
              }

              // Permission check
              $isTargetAdmin = in_array($filterRoleGroup, ['admin'], true);
              $canManageUser = is_admin() || (!$isTargetAdmin);
            ?>
            <tr class="user-row <?= ($isBeingEdited ? 'bg-[#FEF9C3] font-medium' : '') ?>" data-nama="<?= strtolower(e($uNama)) ?>" data-username="<?= strtolower(e($uUsername)) ?>" data-phone="<?= strtolower(e($uTel)) ?>" data-role="<?= $filterRoleGroup ?>" data-bio="<?= $filterBioGroup ?>" data-status="<?= $filterStatusGroup ?>">
              <td class="text-center font-mono text-[#53595F] text-[12px]"><?= sprintf('%02d', $no) ?></td>
              <td>
                <div class="flex items-center gap-2.5">
                  <?= $avatarHtml ?>
                  <div class="min-w-0">
                    <div class="font-bold text-[#1B2A3A] text-[13px] flex items-center flex-wrap gap-1.5 leading-tight">
                      <span><?= e($uNama) ?></span>
                      <?php if ($uNick !== ''): ?>
                        <span class="px-1.5 py-0.2 text-[10px] font-mono bg-[#F4F5F7] text-[#53595F] border border-[#D1D5D8] rounded" title="Paraf Kartu IT">Paraf: <?= e($uNick) ?></span>
                      <?php endif; ?>
                    </div>
                    <div class="text-[11px] font-mono text-[#53595F]">@<?= e($uUsername) ?></div>
                    <?= $telHtml ?>
                  </div>
                </div>
              </td>
              <td class="text-center"><?= $roleBadge ?></td>
              <td class="text-center"><?= $bioBadge ?></td>
              <td class="text-center"><?= $statusBadge ?></td>
              <td class="text-center">
                <div class="d-inline-flex align-items-center justify-content-center gap-1">
                  <!-- 1. Enroll Button -->
                  <a href="<?= e(module_url('user_biometric_enroll.php', ['id' => $uId])) ?>" class="btn btn-outline-primary action-btn-sq" title="Daftarkan / Foto Ulang Biometrik Wajah"><i class="bi bi-camera"></i></a>

                  <!-- 2. Verify Face Button -->
                  <?php if ($hasFace): ?>
                    <?php if (!$isFaceVerified): ?>
                      <form method="post" class="d-inline m-0" onsubmit="return confirm('Verifikasi dan setujui biometrik wajah <?= addslashes($uNama) ?>?')">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="approve_face">
                        <input type="hidden" name="user_id" value="<?= $uId ?>">
                        <button type="submit" class="btn btn-outline-success action-btn-sq" title="Verifikasi / Setujui Wajah"><i class="bi bi-shield-check"></i></button>
                      </form>
                    <?php else: ?>
                      <form method="post" class="d-inline m-0" onsubmit="return confirm('Batalkan status verifikasi wajah <?= addslashes($uNama) ?>?')">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="unverify_face">
                        <input type="hidden" name="user_id" value="<?= $uId ?>">
                        <button type="submit" class="btn btn-outline-secondary action-btn-sq text-success border-success" title="Wajah Terverifikasi (Klik batalkan)"><i class="bi bi-patch-check-fill"></i></button>
                      </form>
                    <?php endif; ?>

                    <!-- 3. Delete Face Button -->
                    <form method="post" class="d-inline m-0" onsubmit="return confirm('Apakah Anda yakin ingin MENGHAPUS sampel biometrik wajah <?= addslashes($uNama) ?>?')">
                      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete_face">
                      <input type="hidden" name="user_id" value="<?= $uId ?>">
                      <button type="submit" class="btn btn-outline-danger action-btn-sq" title="Reset Biometrik Wajah"><i class="bi bi-person-x"></i></button>
                    </form>
                  <?php endif; ?>

                  <!-- 4. Edit Button -->
                  <?php if ($canManageUser): ?>
                    <a href="<?= e(module_url('users_admin.php', ['edit' => $uId])) ?>" class="btn btn-outline-primary action-btn-sq <?= ($isBeingEdited ? 'active' : '') ?>" title="Edit Profil / Password"><i class="bi bi-pencil-square"></i></a>
                  <?php endif; ?>

                  <!-- 5. Delete User Button -->
                  <?php if ($uId !== current_user_id() && $canManageUser): ?>
                    <form method="post" class="d-inline m-0" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun <?= addslashes($uNama) ?> (@<?= addslashes($uUsername) ?>)? Tindakan ini permanen.')">
                      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="user_id" value="<?= $uId ?>">
                      <button type="submit" class="btn btn-outline-danger action-btn-sq" title="Hapus Akun Pengguna"><i class="bi bi-trash"></i></button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
              <tr>
                <td colspan="6" class="text-center py-5 text-muted">
                  <i class="bi bi-people fs-1 d-block mb-2 opacity-50"></i>
                  Belum ada data pengguna. Silakan buat akun pengguna baru pada form di samping.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- No Results State -->
      <div id="noResultsState" class="text-center py-5 d-none">
        <div class="rounded-full bg-[#F4F5F7] d-inline-flex align-items-center justify-content-center p-3 mb-3 text-muted">
          <i class="bi bi-search fs-2"></i>
        </div>
        <h6 class="font-bold text-[#1B2A3A] mb-1">Pengguna Tidak Ditemukan</h6>
        <p class="text-[#53595F] text-[13px] mb-3">Tidak ada data pengguna yang sesuai dengan filter atau kata kunci pencarian.</p>
        <button type="button" class="h-[34px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 cursor-pointer shadow-xs" id="btnResetAllFilters">
          <i class="bi bi-arrow-counterclockwise"></i> Reset Semua Filter
        </button>
      </div>

    </div>
  </div>

  <!-- KOLOM KANAN: FORM TAMBAH / EDIT PENGGUNA (STICKY) -->
  <div class="col-xl-4 col-lg-5" id="formUserSection">
    <div class="bg-white border border-[#D1D5D8] rounded-[12px] shadow-xs overflow-hidden sticky-form">
      
      <?php if ($editUser): 
        $editNama = $editUser['nama'] ?? '';
        $editNick = $editUser['nama_panggilan'] ?? '';
        $editUsername = $editUser['username'] ?? '';
        $editRole = strtolower($editUser['role'] ?? 'teknisi');
        $editTelepon = format_phone_number((string)($editUser['telepon'] ?? ''));
        $editTeleponVal = ($editTelepon !== '-' && $editTelepon !== '') ? $editTelepon : '';
        $editStatus = $editUser['status'] ?? 'Aktif';
        $editPhoto = trim((string)($editUser['face_photo'] ?? ''));
        $editFaceStat = strtolower(trim((string)($editUser['face_status'] ?? 'none')));
      ?>
        <!-- HEADER EDIT -->
        <div class="px-4 py-3.5 border-b border-[#D1D5D8] bg-[#F8FAF9] flex items-center justify-between">
          <div>
            <div class="text-[11px] font-bold text-[#D97706] uppercase tracking-wider">MODIFIKASI PENGGUNA #<?= (int)$editUser['id'] ?></div>
            <h6 class="text-[14px] font-bold text-[#1B2A3A] mb-0 flex items-center gap-1.5">
              <i class="bi bi-pencil-square text-[#D97706]"></i> Edit Akun / Password
            </h6>
          </div>
          <a href="<?= e(module_url('users_admin.php')) ?>" class="h-[30px] px-2.5 bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] font-medium text-[#53595F] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1 text-decoration-none shadow-xs" title="Batal Edit">
            <i class="bi bi-x-lg"></i> Batal
          </a>
        </div>

        <form method="post" action="<?= e(module_url('users_admin.php')) ?>" class="p-4 m-0">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="user_id" value="<?= (int)$editUser['id'] ?>">

          <!-- Status Biometrik Eksisting User -->
          <?php if ($editPhoto !== ''): 
            $isEditVerified = ($editFaceStat === 'verified' || $editFaceStat === 'terverifikasi');
            $isEditPending = ($editFaceStat === 'pending' || $editFaceStat === 'menunggu');
          ?>
          <div class="p-3 mb-3 bg-[#F8FAF9] rounded-[8px] border border-[#D1D5D8]">
            <div class="flex items-center gap-3 mb-2">
              <img src="<?= e($editPhoto) ?>" class="rounded-full border border-[#D1D5D8] shadow-xs" style="width: 44px; height: 44px; object-fit: cover;">
              <div class="min-w-0 flex-1">
                <div class="text-[12px] font-bold text-[#1B2A3A] truncate"><?= e($editNama) ?></div>
                <div class="text-[11px] font-mono text-[#53595F]">Biometrik Wajah Tersimpan</div>
                <div class="mt-0.5">
                  <?php if ($isEditVerified): ?>
                    <span class="status-pill" style="background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; font-size:10px; padding:2px 8px;"><i class="bi bi-shield-check me-1"></i> Terverifikasi</span>
                  <?php elseif ($isEditPending): ?>
                    <span class="status-pill" style="background:#FEF3C7; color:#92400E; border:1px solid #FCD34D; font-size:10px; padding:2px 8px;"><i class="bi bi-hourglass-split me-1"></i> Menunggu Approval</span>
                  <?php else: ?>
                    <span class="status-pill" style="background:#FEF2F2; color:#991B1B; border:1px solid #FECACA; font-size:10px; padding:2px 8px;"><i class="bi bi-x-circle me-1"></i> Ditolak</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div class="flex gap-1.5 pt-2 border-t border-[#E5E7EB]">
              <?php if (!$isEditVerified): ?>
                <button type="submit" form="sideVerifyFaceForm" class="flex-1 h-[28px] bg-[#10B981] hover:bg-[#059669] text-white rounded-[6px] text-[11px] font-semibold border-0 transition-colors flex items-center justify-center gap-1 cursor-pointer">
                  <i class="bi bi-shield-check"></i> Verifikasi
                </button>
              <?php else: ?>
                <button type="submit" form="sideUnverifyFaceForm" class="flex-1 h-[28px] bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#53595F] rounded-[6px] text-[11px] font-semibold transition-colors flex items-center justify-center gap-1 cursor-pointer">
                  <i class="bi bi-arrow-counterclockwise"></i> Batal Verifikasi
                </button>
              <?php endif; ?>
              <button type="submit" form="sideDeleteFaceForm" class="h-[28px] px-2.5 bg-white border border-[#FECACA] hover:bg-[#FEE2E2] text-[#DC2626] rounded-[6px] text-[11px] font-semibold transition-colors flex items-center justify-center gap-1 cursor-pointer" title="Hapus Sampel Wajah">
                <i class="bi bi-trash"></i> Hapus
              </button>
            </div>
          </div>
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label-kombai">Username (Login ID)</label>
            <div class="input-group">
              <span class="input-group-text input-icon-box"><i class="bi bi-person-fill"></i></span>
              <input type="text" class="form-control form-control-kombai bg-[#F4F5F7]" value="<?= e($editUsername) ?>" disabled>
            </div>
            <div class="text-[11px] text-[#53595F] mt-1">Username tidak dapat diubah setelah dibuat.</div>
          </div>

          <div class="mb-3">
            <label class="form-label-kombai">Nama Lengkap &amp; Gelar <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text input-icon-box"><i class="bi bi-person-badge"></i></span>
              <input type="text" class="form-control form-control-kombai" name="nama" value="<?= e($editNama) ?>" required placeholder="Contoh: Budi Santoso, S.Kom">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label-kombai">Nama Panggilan / Paraf Kartu</label>
            <div class="input-group">
              <span class="input-group-text input-icon-box"><i class="bi bi-pen"></i></span>
              <input type="text" class="form-control form-control-kombai" name="nama_panggilan" value="<?= e($editNick) ?>" placeholder="Contoh: Budi">
            </div>
            <div class="text-[11px] text-[#53595F] mt-1">Nama singkat yang dicetak pada paraf kartu inspeksi fisik.</div>
          </div>

          <div class="mb-3">
            <label class="form-label-kombai">Hak Akses / Peran <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text input-icon-box"><i class="bi bi-shield-check"></i></span>
              <select class="form-select form-control-kombai" name="role">
                <option value="teknisi" <?= ($editRole==='teknisi'?'selected':'') ?>>Teknisi IT (Inspeksi &amp; Checklist)</option>
                <option value="sdm" <?= ($editRole==='sdm'||$editRole==='hrd'||$editRole==='umum'||$editRole==='hrd_umum'||str_contains($editRole,'sdm')?'selected':'') ?>>SDM &amp; Umum / HRD (Inventaris &amp; Karyawan)</option>
                <option value="auditor" <?= ($editRole==='auditor'?'selected':'') ?>>Auditor / SKAI (Read-Only Audit Trail)</option>
                <?php if (is_admin()): ?>
                  <option value="admin" <?= ($editRole==='admin'?'selected':'') ?>>Administrator (Akses Penuh Sistem)</option>
                <?php endif; ?>
              </select>
            </div>
            <div class="p-2.5 mt-2 bg-[#F8FAF9] rounded-[8px] border border-[#D1D5D8] text-[11px] text-[#53595F] leading-relaxed">
              <div class="font-bold text-[#1B2A3A] mb-1"><i class="bi bi-info-circle-fill text-[#0E5C58] me-1"></i> Penjelasan Hak Akses:</div>
              <ul class="mb-0 ps-3">
                <li><strong>Teknisi IT:</strong> Checklist pemeliharaan, scanner QR, &amp; biometrik.</li>
                <li><strong>SDM &amp; Umum:</strong> Kartu inventaris fisik, mutasi, &amp; cetak CR80.</li>
                <li><strong>Auditor:</strong> Monitoring inspeksi &amp; unduh log audit trail.</li>
                <?php if (is_admin()): ?>
                  <li><strong>Admin:</strong> Akses penuh seluruh sistem &amp; kelola pengguna.</li>
                <?php endif; ?>
              </ul>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label-kombai">Reset Password</label>
            <div class="input-group">
              <span class="input-group-text input-icon-box"><i class="bi bi-key"></i></span>
              <input type="password" class="form-control form-control-kombai" name="password" id="editPasswordInput" placeholder="Kosongkan jika tidak diubah..." autocomplete="new-password">
              <button class="btn btn-outline-secondary" style="border-radius: 0 8px 8px 0; border-color: #D1D5D8; width: 38px; height: 38px; padding: 0; display: flex; align-items: center; justify-content: center;" type="button" id="btnToggleEditPassword" title="Lihat Password"><i class="bi bi-eye"></i></button>
            </div>
            <div class="text-[11px] text-[#53595F] mt-1">Biarkan kosong jika password lama tetap dipertahankan.</div>
          </div>

          <div class="mb-3">
            <label class="form-label-kombai">No. WhatsApp / Telepon</label>
            <div class="input-group">
              <span class="input-group-text input-icon-box"><i class="bi bi-telephone"></i></span>
              <input type="text" class="form-control form-control-kombai" name="telepon" value="<?= e($editTeleponVal) ?>" placeholder="Contoh: 08123456789">
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label-kombai">Status Akses Akun</label>
            <div class="input-group">
              <span class="input-group-text input-icon-box"><i class="bi bi-power"></i></span>
              <select class="form-select form-control-kombai" name="status">
                <option value="Aktif" <?= ($editStatus==='Aktif'?'selected':'') ?>>Aktif (Diberikan Akses Login)</option>
                <option value="Nonaktif" <?= ($editStatus==='Nonaktif'?'selected':'') ?>>Nonaktif (Akses Ditangguhkan)</option>
              </select>
            </div>
          </div>

          <button type="submit" class="h-[40px] bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[13px] font-semibold w-full transition-colors flex items-center justify-center gap-1.5 border-0 shadow-xs cursor-pointer">
            <i class="bi bi-check-lg"></i> Simpan Perubahan Pengguna
          </button>
        </form>

        <!-- Hidden Side Face Forms for Edit Mode -->
        <form id="sideVerifyFaceForm" method="post" class="d-none" onsubmit="return confirm('Verifikasi dan setujui biometrik wajah <?= addslashes($editNama) ?>?')">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="approve_face">
          <input type="hidden" name="user_id" value="<?= (int)$editUser['id'] ?>">
        </form>
        <form id="sideUnverifyFaceForm" method="post" class="d-none" onsubmit="return confirm('Batalkan status verifikasi biometrik wajah <?= addslashes($editNama) ?>?')">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="unverify_face">
          <input type="hidden" name="user_id" value="<?= (int)$editUser['id'] ?>">
        </form>
        <form id="sideDeleteFaceForm" method="post" class="d-none" onsubmit="return confirm('Apakah Anda yakin ingin MENGHAPUS data biometrik wajah <?= addslashes($editNama) ?>?')">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="delete_face">
          <input type="hidden" name="user_id" value="<?= (int)$editUser['id'] ?>">
        </form>

      <?php else: ?>
        <!-- HEADER TAMBAH -->
        <div class="px-4 py-3.5 border-b border-[#D1D5D8] bg-[#F8FAF9]">
          <div class="text-[11px] font-bold text-[#0E5C58] uppercase tracking-wider">REGISTRASI PENGGUNA</div>
          <h6 class="text-[14px] font-bold text-[#1B2A3A] mb-0 flex items-center gap-1.5">
            <i class="bi bi-person-plus-fill text-[#0E5C58]"></i> Buat Akun Pengguna Baru
          </h6>
        </div>

        <form method="post" action="<?= e(module_url('users_admin.php')) ?>" class="p-4 m-0">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="add">

          <div class="mb-3">
            <label class="form-label-kombai">Username (Login ID) <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text input-icon-box"><i class="bi bi-person-fill"></i></span>
              <input type="text" class="form-control form-control-kombai" name="username" required placeholder="Contoh: budi.santoso" autocomplete="username">
            </div>
            <div class="text-[11px] text-[#53595F] mt-1">Gunakan huruf kecil tanpa spasi.</div>
          </div>

          <div class="mb-3">
            <label class="form-label-kombai">Password Awal <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text input-icon-box"><i class="bi bi-key"></i></span>
              <input type="password" class="form-control form-control-kombai" name="password" id="addPasswordInput" required placeholder="Minimal 6 karakter" autocomplete="new-password">
              <button class="btn btn-outline-secondary" style="border-radius: 0 8px 8px 0; border-color: #D1D5D8; width: 38px; height: 38px; padding: 0; display: flex; align-items: center; justify-content: center;" type="button" id="btnToggleAddPassword" title="Lihat Password"><i class="bi bi-eye"></i></button>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label-kombai">Nama Lengkap &amp; Gelar <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text input-icon-box"><i class="bi bi-person-badge"></i></span>
              <input type="text" class="form-control form-control-kombai" name="nama" required placeholder="Contoh: Budi Santoso, S.Kom">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label-kombai">Nama Panggilan / Paraf Kartu</label>
            <div class="input-group">
              <span class="input-group-text input-icon-box"><i class="bi bi-pen"></i></span>
              <input type="text" class="form-control form-control-kombai" name="nama_panggilan" placeholder="Contoh: Budi">
            </div>
            <div class="text-[11px] text-[#53595F] mt-1">Nama singkat untuk paraf kartu inspeksi fisik.</div>
          </div>

          <div class="mb-3">
            <label class="form-label-kombai">Hak Akses / Peran <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text input-icon-box"><i class="bi bi-shield-check"></i></span>
              <select class="form-select form-control-kombai" name="role">
                <option value="teknisi" selected>Teknisi IT (Inspeksi &amp; Checklist)</option>
                <option value="sdm">SDM &amp; Umum / HRD (Inventaris &amp; Karyawan)</option>
                <option value="auditor">Auditor / SKAI (Read-Only Audit Trail)</option>
                <?php if (is_admin()): ?>
                  <option value="admin">Administrator (Akses Penuh Sistem)</option>
                <?php endif; ?>
              </select>
            </div>
            <div class="p-2.5 mt-2 bg-[#F8FAF9] rounded-[8px] border border-[#D1D5D8] text-[11px] text-[#53595F] leading-relaxed">
              <div class="font-bold text-[#1B2A3A] mb-1"><i class="bi bi-info-circle-fill text-[#0E5C58] me-1"></i> Penjelasan Hak Akses:</div>
              <ul class="mb-0 ps-3">
                <li><strong>Teknisi IT:</strong> Checklist pemeliharaan, scanner QR, &amp; biometrik.</li>
                <li><strong>SDM &amp; Umum:</strong> Kartu inventaris fisik, mutasi, &amp; cetak CR80.</li>
                <li><strong>Auditor:</strong> Monitoring inspeksi &amp; unduh log audit trail.</li>
                <?php if (is_admin()): ?>
                  <li><strong>Admin:</strong> Akses penuh seluruh sistem &amp; kelola pengguna.</li>
                <?php endif; ?>
              </ul>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label-kombai">No. WhatsApp / Telepon</label>
            <div class="input-group">
              <span class="input-group-text input-icon-box"><i class="bi bi-telephone"></i></span>
              <input type="text" class="form-control form-control-kombai" name="telepon" placeholder="Contoh: 08123456789">
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label-kombai">Status Akses Akun</label>
            <div class="input-group">
              <span class="input-group-text input-icon-box"><i class="bi bi-power"></i></span>
              <select class="form-select form-control-kombai" name="status">
                <option value="Aktif" selected>Aktif (Dapat langsung login)</option>
                <option value="Nonaktif">Nonaktif (Akses ditangguhkan)</option>
              </select>
            </div>
          </div>

          <button type="submit" class="h-[40px] bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[13px] font-semibold w-full transition-colors flex items-center justify-center gap-1.5 border-0 shadow-xs cursor-pointer">
            <i class="bi bi-person-plus-fill"></i> Buat Akun Pengguna Baru
          </button>
        </form>
      <?php endif; ?>

    </div>
  </div>
</div>

<!-- MODAL PRATINJAU & KELOLA FOTO WAJAH PETUGAS -->
<div class="modal fade" id="modalPreviewFace" tabindex="-1" aria-labelledby="modalPreviewFaceLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-header py-3 px-4 bg-[#0E5C58] text-white border-0">
        <h6 class="modal-title fs-6 font-bold mb-0 flex items-center gap-2" id="modalPreviewFaceLabel">
          <i class="bi bi-person-bounding-box text-white"></i> Kelola Biometrik Wajah Petugas
        </h6>
        <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4">
        <div class="position-relative d-inline-block mb-3">
          <img id="previewFaceImg" src="" alt="Foto Wajah Petugas" class="rounded-full shadow-sm border border-4 border-white" style="width: 140px; height: 140px; object-fit: cover; box-shadow: 0 4px 18px rgba(14,92,88,0.2) !important;">
        </div>
        <h5 class="font-bold mb-1 text-[#1B2A3A]" id="previewFaceNama">-</h5>
        <div class="text-[#53595F] text-[12px] font-mono mb-2" id="previewFaceUser">@-</div>
        <div class="flex items-center justify-center gap-2 mb-3">
          <div id="previewFaceRoleBadge"></div>
          <div id="previewFaceStatusPill"></div>
        </div>
        <div class="p-3 bg-[#F8FAF9] rounded-[8px] text-[#53595F] text-[12px] border border-[#D1D5D8] text-start leading-relaxed">
          <div class="flex items-center gap-1.5 font-bold text-[#1B2A3A] mb-1">
            <i class="bi bi-shield-lock-fill text-[#0E5C58]"></i> Autentikasi Biometrik Petugas
          </div>
          Vektor wajah disimpan secara terenkripsi untuk validasi presensi &amp; kepemilikan checklist kartu pemeliharaan.
        </div>
      </div>
      <div class="modal-footer py-3 px-4 bg-[#F8FAF9] border-t border-[#D1D5D8] flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-1.5">
          <!-- Form Verifikasi Muka Modal -->
          <form method="post" id="formModalVerifyFace" class="m-0" onsubmit="return confirm('Verifikasi dan setujui biometrik wajah petugas ini?')">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="approve_face">
            <input type="hidden" name="user_id" id="modalUserIdVerify" value="0">
            <button type="submit" id="btnModalVerifyFace" class="h-[32px] px-3 bg-[#10B981] hover:bg-[#059669] text-white rounded-[6px] text-[12px] font-semibold border-0 transition-colors flex items-center justify-center gap-1 cursor-pointer">
              <i class="bi bi-shield-check"></i> Verifikasi Muka
            </button>
          </form>

          <!-- Form Batal Verifikasi Modal -->
          <form method="post" id="formModalUnverifyFace" class="m-0" onsubmit="return confirm('Batalkan status verifikasi biometrik wajah petugas ini?')">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="unverify_face">
            <input type="hidden" name="user_id" id="modalUserIdUnverify" value="0">
            <button type="submit" id="btnModalUnverifyFace" class="h-[32px] px-3 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#53595F] rounded-[6px] text-[12px] font-semibold transition-colors flex items-center justify-center gap-1 cursor-pointer" style="display: none;">
              <i class="bi bi-arrow-counterclockwise"></i> Batal Verifikasi
            </button>
          </form>

          <!-- Form Hapus Muka Modal -->
          <form method="post" id="formModalDeleteFace" class="m-0" onsubmit="return confirm('Apakah Anda yakin ingin MENGHAPUS data biometrik wajah petugas ini? Foto dan sampel biometrik akan dihapus.')">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete_face">
            <input type="hidden" name="user_id" id="modalUserIdDelete" value="0">
            <button type="submit" id="btnModalDeleteFace" class="h-[32px] px-2.5 bg-white border border-[#FECACA] hover:bg-[#FEE2E2] text-[#DC2626] rounded-[6px] text-[12px] font-semibold transition-colors flex items-center justify-center gap-1 cursor-pointer">
              <i class="bi bi-trash"></i> Hapus Muka
            </button>
          </form>
        </div>

        <div class="flex items-center gap-1.5">
          <a id="btnPreviewEnrollAgain" href="#" class="h-[32px] px-3 bg-white border border-[#0E5C58] text-[#0E5C58] hover:bg-[#D9F1EF] rounded-[6px] text-[12px] font-semibold transition-colors inline-flex items-center gap-1 text-decoration-none shadow-xs"><i class="bi bi-camera"></i> Foto Ulang</a>
          <button type="button" class="h-[32px] px-3 bg-white border border-[#D1D5D8] text-[#53595F] hover:bg-[#F4F5F7] rounded-[6px] text-[12px] font-semibold transition-colors" data-bs-dismiss="modal">Tutup</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function previewFacePhoto(imgSrc, nama, username, role, userId, faceStat, enrollUrl) {
  if (!imgSrc) return;
  var imgEl = document.getElementById("previewFaceImg");
  var namaEl = document.getElementById("previewFaceNama");
  var userEl = document.getElementById("previewFaceUser");
  var badgeEl = document.getElementById("previewFaceRoleBadge");
  var statusPillEl = document.getElementById("previewFaceStatusPill");
  var modalUserIdVerify = document.getElementById("modalUserIdVerify");
  var modalUserIdUnverify = document.getElementById("modalUserIdUnverify");
  var modalUserIdDelete = document.getElementById("modalUserIdDelete");
  var btnVerifyFace = document.getElementById("btnModalVerifyFace");
  var btnUnverifyFace = document.getElementById("btnModalUnverifyFace");
  var btnDeleteFace = document.getElementById("btnModalDeleteFace");
  var btnEnrollAgain = document.getElementById("btnPreviewEnrollAgain");

  if (imgEl) imgEl.src = imgSrc;
  if (namaEl) namaEl.innerText = nama || "-";
  if (userEl) userEl.innerText = "@" + (username || "-");
  if (modalUserIdVerify) modalUserIdVerify.value = userId || "0";
  if (modalUserIdUnverify) modalUserIdUnverify.value = userId || "0";
  if (modalUserIdDelete) modalUserIdDelete.value = userId || "0";
  if (btnEnrollAgain && enrollUrl) btnEnrollAgain.href = enrollUrl;

  var roleBadge = '<span class="status-pill" style="background:#F1F5F9; color:#475569; border:1px solid #CBD5E1;">' + (role || "Pengguna") + '</span>';
  var roleStr = (role || "").toLowerCase();
  if (roleStr === "admin" || roleStr === "administrator") {
    roleBadge = '<span class="status-pill" style="background:#D9F1EF; color:#0E5C58; border:1px solid #A7E2DC;"><i class="bi bi-shield-lock-fill me-1"></i> Administrator</span>';
  } else if (roleStr.indexOf("sdm") !== -1 || roleStr.indexOf("hrd") !== -1 || roleStr.indexOf("umum") !== -1) {
    roleBadge = '<span class="status-pill" style="background:#FEF3C7; color:#92400E; border:1px solid #FCD34D;"><i class="bi bi-person-workspace me-1"></i> SDM &amp; Umum</span>';
  } else if (roleStr === "auditor" || roleStr === "skai") {
    roleBadge = '<span class="status-pill" style="background:#F1F5F9; color:#475569; border:1px solid #CBD5E1;"><i class="bi bi-eye-fill me-1"></i> Auditor</span>';
  } else {
    roleBadge = '<span class="status-pill" style="background:#E0F2FE; color:#0369A1; border:1px solid #BAE6FD;"><i class="bi bi-tools me-1"></i> Teknisi IT</span>';
  }
  if (badgeEl) badgeEl.innerHTML = roleBadge;

  var fStatStr = (faceStat || "").toLowerCase();
  var isVerified = (fStatStr === "verified" || fStatStr === "terverifikasi");
  if (statusPillEl) {
    if (isVerified) {
      statusPillEl.innerHTML = '<span class="status-pill" style="background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0;"><i class="bi bi-shield-check me-1"></i> Terverifikasi</span>';
    } else if (fStatStr === "pending" || fStatStr === "menunggu") {
      statusPillEl.innerHTML = '<span class="status-pill" style="background:#FEF3C7; color:#92400E; border:1px solid #FCD34D;"><i class="bi bi-hourglass-split me-1"></i> Menunggu Verifikasi</span>';
    } else {
      statusPillEl.innerHTML = '<span class="status-pill" style="background:#FEF2F2; color:#991B1B; border:1px solid #FECACA;"><i class="bi bi-x-circle me-1"></i> Ditolak</span>';
    }
  }

  if (btnVerifyFace) {
    btnVerifyFace.style.display = isVerified ? "none" : "inline-flex";
  }
  if (btnUnverifyFace) {
    btnUnverifyFace.style.display = isVerified ? "inline-flex" : "none";
  }
  if (btnDeleteFace) {
    btnDeleteFace.style.display = "inline-flex";
  }

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
  // Password Visibility Toggle for Add Form
  var btnToggleAdd = document.getElementById("btnToggleAddPassword");
  var inputAdd = document.getElementById("addPasswordInput");
  if (btnToggleAdd && inputAdd) {
    btnToggleAdd.addEventListener("click", function() {
      var isPw = inputAdd.type === "password";
      inputAdd.type = isPw ? "text" : "password";
      btnToggleAdd.innerHTML = isPw ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
    });
  }

  // Password Visibility Toggle for Edit Form
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
    var userId = trigger.getAttribute("data-id") || "0";
    var stat = trigger.getAttribute("data-stat") || "";
    var enrollUrl = trigger.getAttribute("data-enroll-url") || "#";
    previewFacePhoto(photo, nama, username, role, userId, stat, enrollUrl);
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
<?php
$body = ob_get_clean();

render_page($page_title, $body);
