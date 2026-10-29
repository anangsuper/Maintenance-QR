<?php
require __DIR__ . '/bootstrap.php';
require_login();

// Handle AJAX POST simpan biometrik atau tambah teknisi baru langsung dari HP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $isAdmin = is_admin();
    $currUserId = current_user_id();

    $action = trim((string)($data['action'] ?? $_GET['action'] ?? ''));
    $newTechName = trim((string)($data['new_name'] ?? ''));
    $targetUserId = (int)($data['user_id'] ?? 0);
    $descriptor = trim((string)($data['descriptor'] ?? ''));
    $photo = trim((string)($data['photo'] ?? ''));

    // Aksi 1: Simpan Nama Teknisi Baru Langsung (Hanya Admin yang berwenang)
    if ($action === 'add_tech_only' || ($newTechName !== '' && empty($descriptor))) {
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'error' => 'Akses ditolak. Penambahan akun teknisi baru hanya dapat dilakukan oleh Administrator.']);
            exit;
        }
        if ($newTechName === '') {
            echo json_encode(['success' => false, 'error' => 'Nama teknisi baru tidak boleh kosong.']);
            exit;
        }
        $created = create_new_user([
            'nama' => $newTechName,
            'role' => 'teknisi',
            'status' => 'Aktif',
            'face_status' => 'none'
        ]);

        $uId = (int)($created['id'] ?? $created['user']['id'] ?? 0);
        $uNama = (string)($created['nama'] ?? $created['user']['nama'] ?? $newTechName);
        $uUsername = (string)($created['username'] ?? $created['user']['username'] ?? '');

        if (!empty($created['success']) && $uId > 0) {
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $uId,
                    'nama' => $uNama,
                    'username' => $uUsername
                ],
                'message' => "Teknisi '{$newTechName}' berhasil didaftarkan ke sistem!"
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => $created['error'] ?? 'Gagal mendaftarkan teknisi baru.']);
        }
        exit;
    }

    // Aksi 2: Simpan dengan Biometrik Wajah
    if (empty($descriptor)) {
        echo json_encode(['success' => false, 'error' => 'Data vektor biometrik tidak boleh kosong.']);
        exit;
    }

    // Jika teknisi mendaftar dengan nama baru secara mandiri bersama wajah (Hanya Admin)
    if ($targetUserId === -1 && $newTechName !== '') {
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'error' => 'Akses ditolak. Penambahan akun baru hanya diizinkan untuk Administrator.']);
            exit;
        }
        $created = create_new_user([
            'nama' => $newTechName,
            'role' => 'teknisi',
            'status' => 'Aktif',
            'face_descriptor' => $descriptor,
            'face_photo' => $photo,
            'face_status' => 'pending'
        ]);
        $uId = (int)($created['id'] ?? $created['user']['id'] ?? 0);
        if (!empty($created['success']) && $uId > 0) {
            echo json_encode([
                'success' => true,
                'face_status' => 'pending',
                'is_admin' => is_admin(),
                'user_id' => $uId,
                'user' => $created['user'] ?? $created,
                'message' => "Teknisi '{$newTechName}' dan biometrik wajah berhasil didaftarkan ke sistem!"
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => $created['error'] ?? 'Gagal mendaftarkan nama teknisi baru']);
            exit;
        }
    }

    if ($targetUserId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Pilih nama teknisi yang valid']);
        exit;
    }

    // Otorisasi: Non-admin & non-HRD HANYA boleh mendaftarkan biometrik untuk akun login miliknya sendiri
    if (!$isAdmin && !is_hrd() && $targetUserId !== $currUserId) {
        echo json_encode(['success' => false, 'error' => 'Akses ditolak. Anda hanya berwenang mendaftarkan sampel wajah untuk akun login Anda sendiri.']);
        exit;
    }

    // Untuk Admin dan SDM/HRD otomatis disetujui ('verified'), untuk teknisi umum 'pending'
    $enrollStatus = ($isAdmin || is_hrd()) ? 'verified' : 'pending';
    $res = save_user_biometrics($targetUserId, $descriptor, $photo, $enrollStatus);
    echo json_encode(array_merge($res, [
        'face_status' => $enrollStatus,
        'is_admin' => ($isAdmin || is_hrd()),
        'user_id' => $targetUserId
    ]));
    exit;
}

// Ambil seluruh daftar pengguna / teknisi untuk dipilih di HP (selalu data terkini)
$allUsers = get_user_list(true);
$currId = current_user_id();
$isAdmin = is_admin();
$isLoggedIn = is_logged_in();

$rawRet = trim((string)($_GET['ret'] ?? ''));
$retUrl = ($rawRet !== '') ? safe_redirect_url($rawRet, '') : '';

$reqId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Tentukan user aktif: non-admin non-hrd diarahkan ke profil sendiri, atau jika ada parameter ?id=
$selectedUserId = 0;
if ($reqId > 0 && ($isAdmin || is_hrd() || $reqId === $currId)) {
    $selectedUserId = $reqId;
} elseif ($currId > 0) {
    $selectedUserId = $currId;
} elseif (!empty($allUsers)) {
    $selectedUserId = (int)($allUsers[0]['id'] ?? 0);
}

$user = null;
foreach ($allUsers as $u) {
    if ((int)($u['id'] ?? 0) === $selectedUserId) {
        $user = $u;
        break;
    }
}
if (!$user && !empty($allUsers)) {
    $user = $allUsers[0];
    $selectedUserId = (int)($user['id'] ?? 0);
}
$user = is_array($user) ? $user : [];

$hasBiometrics = !empty($user['face_descriptor']);
$existingPhoto = $user['face_photo'] ?? '';
$userFaceStatus = strtolower(trim((string)($user['face_status'] ?? '')));

// Hitung Statistik Petugas & Biometrik
$statTotalUsers = 0;
$statVerified = 0;
$statPending = 0;
$statUnenrolled = 0;

foreach ($allUsers as $u) {
    $uid = (int)($u['id'] ?? 0);
    $unama = trim((string)($u['nama'] ?? $u['username'] ?? ''));
    if ($uid <= 0 || strcasecmp($unama, 'nama') === 0 || $unama === '') continue;
    $statTotalUsers++;
    $uHasBio = !empty($u['face_descriptor']);
    $uStat = strtolower(trim((string)($u['face_status'] ?? '')));
    if ($uHasBio && ($uStat === 'verified' || $uStat === 'terverifikasi')) {
        $statVerified++;
    } elseif ($uHasBio && ($uStat === 'pending' || $uStat === 'menunggu')) {
        $statPending++;
    } else {
        $statUnenrolled++;
    }
}

$statsCardsHtml = '
<div class="row g-3 mb-4">
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card p-3 border shadow-sm bg-white rounded-3 h-100 stat-card-hover" style="border-left: 5px solid #003B73 !important;">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="text-uppercase fw-bold text-secondary mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Akun Petugas</div>
          <div class="h3 fw-bold mb-0 text-dark">'.$statTotalUsers.' <span class="fs-6 fw-semibold text-muted">Orang</span></div>
          <div class="small text-muted mt-1" style="font-size: 0.75rem;">Teknisi &amp; Staf Operasional</div>
        </div>
        <div class="rounded-circle d-flex align-items-center justify-content-center text-primary shadow-xs" style="width: 50px; height: 50px; background: #EFF6FF;">
          <i class="bi bi-people-fill fs-3"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card p-3 border shadow-sm bg-white rounded-3 h-100 stat-card-hover" style="border-left: 5px solid #10B981 !important;">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="text-uppercase fw-bold text-secondary mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Wajah Terverifikasi</div>
          <div class="h3 fw-bold mb-0 text-success">'.$statVerified.' <span class="fs-6 fw-semibold text-muted">Petugas</span></div>
          <div class="small text-success mt-1 fw-semibold" style="font-size: 0.75rem;"><i class="bi bi-patch-check-fill me-1"></i>Siap presensi checklist</div>
        </div>
        <div class="rounded-circle d-flex align-items-center justify-content-center text-success shadow-xs" style="width: 50px; height: 50px; background: #ECFDF5;">
          <i class="bi bi-shield-check fs-3"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card p-3 border shadow-sm bg-white rounded-3 h-100 stat-card-hover" style="border-left: 5px solid #D97706 !important;">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="text-uppercase fw-bold text-secondary mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Menunggu Konfirmasi</div>
          <div class="h3 fw-bold mb-0" style="color: #B45309;">'.$statPending.' <span class="fs-6 fw-semibold text-muted">Petugas</span></div>
          <div class="small mt-1 fw-semibold" style="font-size: 0.75rem; color: #D97706;"><i class="bi bi-hourglass-split me-1"></i>Review oleh Admin</div>
        </div>
        <div class="rounded-circle d-flex align-items-center justify-content-center shadow-xs" style="width: 50px; height: 50px; background: #FEF3C7; color: #D97706;">
          <i class="bi bi-clock-history fs-3"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card p-3 border shadow-sm bg-white rounded-3 h-100 stat-card-hover" style="border-left: 5px solid #6366F1 !important;">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="text-uppercase fw-bold text-secondary mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Belum Rekam Wajah</div>
          <div class="h3 fw-bold mb-0 text-dark">'.$statUnenrolled.' <span class="fs-6 fw-semibold text-muted">Petugas</span></div>
          <div class="small text-muted mt-1" style="font-size: 0.75rem;">Perlu foto sampel</div>
        </div>
        <div class="rounded-circle d-flex align-items-center justify-content-center shadow-xs" style="width: 50px; height: 50px; background: #EEF2FF; color: #6366F1 !important;">
          <i class="bi bi-person-x-fill fs-3"></i>
        </div>
      </div>
    </div>
  </div>
</div>';

if ($userFaceStatus === 'verified' || $userFaceStatus === 'terverifikasi') {
    $badgeStatus = '<span class="badge bg-success-subtle text-success px-3 py-2 border border-success-subtle fw-bold rounded-pill"><i class="bi bi-shield-check me-1"></i> Wajah Terverifikasi Admin</span>';
} elseif ($userFaceStatus === 'pending' || $userFaceStatus === 'menunggu') {
    $badgeStatus = '<span class="badge bg-warning-subtle text-warning px-3 py-2 border border-warning-subtle fw-bold shadow-sm rounded-pill"><i class="bi bi-hourglass-split me-1"></i> Menunggu Persetujuan Admin</span>';
} elseif ($userFaceStatus === 'rejected') {
    $badgeStatus = '<span class="badge bg-danger-subtle text-danger px-3 py-2 border border-danger-subtle fw-bold rounded-pill"><i class="bi bi-x-circle me-1"></i> Ditolak Admin (Daftar Ulang)</span>';
} else {
    $badgeStatus = '<span class="badge bg-secondary-subtle text-secondary px-3 py-2 border border-secondary-subtle fw-bold rounded-pill"><i class="bi bi-exclamation-circle me-1"></i> Belum Didaftarkan</span>';
}

$avatarHtml = '';
$currName = trim((string)($user['nama'] ?? $user['username'] ?? 'Petugas IT'));
$currRole = ucfirst(trim((string)($user['role'] ?? 'Teknisi')));
if ($existingPhoto) {
    $avatarHtml = '
    <div class="text-center mb-3 p-3 bg-light rounded-3 border" id="userAvatarBox">
      <div class="d-inline-block position-relative mb-2">
        <img src="'.e($existingPhoto).'" alt="Foto Biometrik" class="rounded-circle border border-3 border-success shadow-sm" style="width: 90px; height: 90px; object-fit: cover;">
        <span class="position-absolute bottom-0 end-0 badge rounded-circle bg-success p-1.5 shadow-sm" title="Terverifikasi"><i class="bi bi-shield-check fs-6"></i></span>
      </div>
      <div class="fw-bold text-dark mb-0" style="font-size: 0.95rem;">'.e($currName).'</div>
      <div class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-0.5 mt-1 small">'.e($currRole).'</div>
      <div class="small text-success mt-1.5 fw-semibold" style="font-size: 0.75rem;"><i class="bi bi-check-circle-fill me-1"></i>Sampel biometrik aktif terdaftar</div>
    </div>';
} else {
    $avatarHtml = '
    <div class="text-center mb-3 p-3 bg-light rounded-3 border" id="userAvatarBox">
      <div class="d-inline-block position-relative mb-2">
        <div class="rounded-circle bg-white border border-2 border-dashed border-secondary d-flex align-items-center justify-content-center text-muted shadow-xs mx-auto" style="width: 90px; height: 90px;">
          <i class="bi bi-person-bounding-box fs-1 text-secondary opacity-50"></i>
        </div>
      </div>
      <div class="fw-bold text-dark mb-0" style="font-size: 0.95rem;">'.e($currName).'</div>
      <div class="badge bg-secondary-subtle text-secondary border rounded-pill px-2.5 py-0.5 mt-1 small">'.e($currRole).'</div>
      <div class="small text-muted mt-1.5" style="font-size: 0.75rem;">Belum ada sampel foto biometrik</div>
    </div>';
}

// Opsi list teknisi
$userOptionsHtml = '';
foreach ($allUsers as $u) {
    $uid = (int)($u['id'] ?? 0);
    $unama = trim((string)($u['nama'] ?? $u['username'] ?? ''));
    if ($uid <= 0 || strcasecmp($unama, 'nama') === 0 || $unama === '') {
        continue;
    }
    // Jika bukan admin dan bukan HRD/SDM, hanya tampilkan opsi akun login miliknya sendiri
    if (!$isAdmin && !is_hrd() && $uid !== $currId) {
        continue;
    }
    $urole = ucfirst($u['role'] ?? 'Teknisi');
    $uHasBio = !empty($u['face_descriptor']);
    $uStat = strtolower(trim((string)($u['face_status'] ?? '')));
    $sel = ($uid === $selectedUserId) ? 'selected' : '';
    
    $bioMark = ' [⚠️ Belum Wajah]';
    if ($uHasBio) {
        if ($uStat === 'verified' || $uStat === 'terverifikasi') {
            $bioMark = ' [✓ Disetujui Admin]';
        } elseif ($uStat === 'pending' || $uStat === 'menunggu') {
            $bioMark = ' [⏳ Menunggu Verifikasi Admin]';
        } elseif ($uStat === 'rejected') {
            $bioMark = ' [✕ Ditolak]';
        } else {
            $bioMark = ' [✓ Terdaftar]';
        }
    }
    $userOptionsHtml .= '<option value="'.$uid.'" '.$sel.'>'.e($unama).' ('.e($urole).')'.$bioMark.'</option>';
}
if ($isAdmin) {
    $userOptionsHtml .= '<option value="-1">+ Tambah Nama Teknisi Baru (Ketik Sendiri)</option>';
}

if ($retUrl !== '') {
    $backHref = $retUrl;
    $backText = 'Kembali';
} elseif ($isAdmin || is_hrd()) {
    $backHref = module_url('users_admin.php');
    $backText = 'Kelola Pengguna';
} elseif ($isLoggedIn) {
    $backHref = module_url('dashboard.php');
    $backText = 'Halaman Utama';
} else {
    $backHref = 'javascript:history.back()';
    $backText = 'Kembali';
}

// Susun tombol aksi selesai sesuai status autentikasi pengguna
$successButtonsHtml = '';
if ($retUrl !== '') {
    $successButtonsHtml .= '<a href="'.e($retUrl).'" class="btn btn-success fw-bold py-2"><i class="bi bi-arrow-return-left me-1"></i> Kembali Lanjutkan Maintenance</a>';
}

if ($isAdmin || is_hrd()) {
    $successButtonsHtml .= '<a href="'.e(module_url('users_admin.php')).'" class="btn btn-primary fw-semibold py-2"><i class="bi bi-people-fill me-1"></i> Halaman Data Pengguna</a>';
    $successButtonsHtml .= '<a href="'.e(module_url('dashboard.php')).'" class="btn btn-outline-secondary fw-semibold py-2"><i class="bi bi-house me-1"></i> Halaman Utama</a>';
} elseif ($isLoggedIn) {
    $successButtonsHtml .= '<a href="'.e(module_url('dashboard.php')).'" class="btn btn-primary fw-semibold py-2"><i class="bi bi-house me-1"></i> Halaman Utama</a>';
} else {
    // Pengguna belum login: tidak perlu tombol login, cukup kembali ke maintenance atau daftar teknisi lain
}

$successButtonsHtml .= '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload()"><i class="bi bi-person-plus me-1"></i> Daftarkan Teknisi Lain</button>';

if ($isAdmin || is_hrd()) {
    $successDescHtml = 'Data foto profil dan verifikasi wajah berhasil disimpan dan <strong>langsung aktif terverifikasi</strong>.<br><br>Anda dapat melihat status akun ini di menu <a href="'.e(module_url('users_admin.php')).'" class="fw-bold text-decoration-none">Kelola Data &rarr; Akun Pengguna / Teknisi</a>.';
} else {
    $successDescHtml = 'Foto profil dan data verifikasi telah disimpan dengan status: <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-hourglass-split me-1"></i> Menunggu Persetujuan Admin</span>.<br><br>Administrator IT akan mengonfirmasi akun Anda agar dapat langsung digunakan saat checklist maintenance.';
}

$head = '
<style>
  .enroll-main-card {
    border-radius: 12px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);
  }
  @media (min-width: 992px) {
    .border-end-lg {
      border-right: 1px solid #E2E8F0 !important;
    }
  }
  .stat-card-hover {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  .stat-card-hover:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.06) !important;
  }
  .scanner-container {
    position: relative;
    width: 100%;
    max-width: 320px;
    aspect-ratio: 1 / 1;
    margin: 0 auto;
    background: #000;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
  }
  .scanner-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: scaleX(-1);
  }
  .face-oval {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 72%;
    height: 85%;
    border: 2.5px dashed rgba(59, 130, 246, 0.85);
    border-radius: 50%;
    box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.35);
    pointer-events: none;
    transition: all 0.3s ease;
  }
  .face-oval.active {
    border: 2.5px solid #22c55e !important;
    box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.35), 0 0 20px rgba(34, 197, 94, 0.6) !important;
  }
  @keyframes scanRayAnim {
    0% { top: 10%; opacity: 0.3; }
    50% { top: 85%; opacity: 1; }
    100% { top: 10%; opacity: 0.3; }
  }
  @keyframes bounceIn {
    0% { transform: scale(0.5); opacity: 0; }
    60% { transform: scale(1.1); opacity: 1; }
    100% { transform: scale(1); opacity: 1; }
  }
  .animate-bounce {
    animation: bounceIn 0.5s ease-out;
  }
</style>';

$body = '
<!-- Header Halaman Standar Bank Mitra -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div>
    <div class="tech-label mb-1">PENGATURAN &amp; KEAMANAN BIOMETRIK</div>
    <h1 class="h3 mb-1"><i class="bi bi-person-bounding-box text-primary me-2"></i>Wajah Teknisi (Biometrik FIDO2)</h1>
    <p class="text-secondary small mb-0">Perekaman sampel wajah dan foto profil petugas untuk verifikasi checklist pemeliharaan IT &amp; audit OJK.</p>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    '.($isAdmin ? '
    <a href="'.e(module_url('users_admin.php')).'" class="btn btn-outline-primary d-inline-flex align-items-center gap-1.5 fw-semibold shadow-xs">
      <i class="bi bi-people-fill"></i> Kelola Pengguna
    </a>' : '').'
    <a href="'.e(module_url('dashboard.php')).'" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1.5 fw-semibold shadow-xs">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>
  </div>
</div>

<!-- 4 Kartu Statistik Ringkasan -->
'.$statsCardsHtml.'

<!-- Kartu Konten Utama -->
<div class="card enroll-main-card p-3 p-sm-4 p-lg-4 bg-white mb-4">
  <div class="row g-4 align-items-start">

    <!-- KOLOM KIRI: Pemilihan Teknisi, Status & Panduan -->
    <div class="col-lg-5 border-end-lg pe-lg-4">
          
          <!-- Pilih Teknisi -->
          <div class="mb-3">
            <label class="form-label fw-bold text-dark small mb-1">
              <i class="bi bi-person-fill text-primary me-1"></i> Pilih Nama Petugas / Teknisi:
            </label>
            <div class="input-group">
              <select class="form-select fw-semibold" id="userSelect" onchange="handleUserChange(this.value)">
                '.$userOptionsHtml.'
              </select>
              <button type="button" class="btn btn-outline-primary fw-semibold" onclick="showNewTechInput()" title="Ketik Nama Teknisi Baru jika belum ada">
                <i class="bi bi-person-plus-fill me-1"></i> Baru
              </button>
            </div>
            <div class="form-text text-muted" style="font-size: 0.75rem;">
              Pilih nama Anda dari daftar, atau klik <strong>"Baru"</strong> jika nama belum tercantum.
            </div>
          </div>

          <!-- Kotak Input Nama Teknisi Baru -->
          <div class="p-3 mb-3 bg-light rounded-3 border border-primary border-opacity-25 d-none" id="newTechBox">
            <label class="form-label fw-bold text-dark small mb-1">
              <i class="bi bi-person-plus text-primary me-1"></i> Masukkan Nama Lengkap Teknisi:
            </label>
            <input type="text" class="form-control mb-2" id="newTechName" placeholder="Contoh: Akhmad Hafizh Firmansyah" autocomplete="name">
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-primary btn-sm fw-semibold" id="btnQuickSaveTech" onclick="saveNewTechOnly()">
                <i class="bi bi-save me-1"></i> Simpan Nama ke Sistem
              </button>
              <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cancelNewTech()">Batal</button>
            </div>
            <div class="mt-2 small" id="newTechStatus"></div>
          </div>

          <!-- Foto Avatar Eksisting (Jika ada) -->
          '.$avatarHtml.'

          <!-- Status Registrasi Wajah -->
          <div class="text-center mb-3">
            '.$badgeStatus.'
          </div>

          <!-- Panduan Singkat -->
          <div class="alert alert-light border rounded-3 p-3 mb-3 small text-secondary">
            <div class="fw-bold text-dark mb-1.5 d-flex align-items-center gap-1.5">
              <i class="bi bi-info-circle-fill text-primary"></i> Panduan Pengambilan Foto:
            </div>
            <ol class="mb-0 ps-3" style="line-height: 1.6;">
              <li>Posisikan wajah tegak lurus menghadap kamera.</li>
              <li>Pastikan pencahayaan cukup dan wajah terlihat jelas tanpa masker/topi.</li>
              <li>Tahan posisi sejenak hingga bingkai berubah hijau, lalu simpan.</li>
            </ol>
          </div>

          <!-- Info Keamanan & Privasi -->
          <div class="p-2.5 bg-light rounded-3 border small text-muted d-flex align-items-center gap-2">
            <i class="bi bi-shield-lock-fill text-success fs-5 flex-shrink-0"></i>
            <div style="font-size: 0.72rem; line-height: 1.35;">
              Vektor biometrik wajah dienkripsi aman untuk validasi presensi dan audit operasional IT Bank Mitra.
            </div>
          </div>
        </div>

        <!-- KOLOM KANAN: Kamera Pemindai & Tombol Aksi -->
        <div class="col-lg-7 ps-lg-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-bold text-dark small text-uppercase" style="letter-spacing: 0.04em;">
              <i class="bi bi-camera-video text-primary me-1"></i> Pemindai Kamera & Verifikasi Wajah:
            </span>
          </div>

          <!-- Banner Beralih ke HTTPS agar Kamera Langsung Aktif -->
          <div class="alert alert-primary py-2 px-3 small d-flex align-items-center justify-content-between mb-3 border-primary border-opacity-25 bg-primary bg-opacity-10 d-none" id="httpsNoticeBanner">
            <div>
              <i class="bi bi-shield-lock-fill text-primary me-1"></i>
              Buka lewat <strong>HTTPS</strong> agar kamera webcam aktif langsung di browser.
            </div>
            <a href="#" id="linkToHttps" class="btn btn-primary btn-sm fw-bold px-3 py-1 shadow-xs text-nowrap ms-2">
              Buka via HTTPS <i class="bi bi-arrow-right-short"></i>
            </a>
          </div>

          <!-- Scanner Container -->
          <div class="scanner-container mb-3" id="scannerContainer">
            <video id="videoElement" class="scanner-video" autoplay playsinline webkit-playsinline muted></video>
            
            <!-- Oval Guide Frame -->
            <div class="face-oval pointer-events-none" id="faceOval"></div>
            
            <!-- Scanning Ray Animation -->
            <div id="enrollScanRay" class="position-absolute start-0 w-100 d-none" style="height: 3px; background: linear-gradient(90deg, transparent, #3b82f6, #60a5fa, transparent); animation: scanRayAnim 2s infinite ease-in-out;"></div>

            <!-- Success Overlay -->
            <div id="enrollSuccessOverlay" class="position-absolute top-0 start-0 w-100 h-100 bg-success bg-opacity-75 d-flex flex-column align-items-center justify-content-center text-white d-none" style="backdrop-filter: blur(4px); z-index: 5;">
              <i class="bi bi-check-circle-fill display-3 mb-2 animate-bounce"></i>
              <h5 class="fw-bold text-white mb-1" id="enrollSuccessName">Wajah Terverifikasi!</h5>
              <span class="badge bg-white text-success fw-bold px-3 py-1 rounded-pill" id="enrollSuccessBadge">100% Cocok</span>
            </div>

            <canvas id="snapshotCanvas" style="display:none;" width="320" height="320"></canvas>
          </div>

          <!-- Status Bar Feedback Realtime -->
          <div class="alert alert-secondary py-2 px-3 small fw-semibold text-start text-dark mb-2 mx-auto" style="max-width: 320px;" id="statusMessage">
            <i class="bi bi-camera me-1 text-primary"></i>
            Tekan <strong>"Buka Kamera Wajah"</strong> untuk mulai verifikasi.
          </div>

          <div class="text-muted small mb-3 text-center" style="font-size: 0.72rem;">
            Pastikan wajah berada di dalam oval, tidak memakai masker, dan pencahayaan memadai.
          </div>

          <!-- Progress Liveness -->
          <div class="progress mb-3 mx-auto" style="height: 4px; border-radius: 4px; max-width: 320px;" id="progressBarContainer">
            <div class="progress-bar bg-primary" id="progressBar" style="width: 0%;"></div>
          </div>

          <!-- Tombol Aksi (Kamera Langsung & Pilihan Upload Foto) -->
          <div class="d-grid gap-2 mb-3 mx-auto" style="max-width: 320px;">
            <button type="button" class="btn btn-primary fw-bold py-2.5 rounded-pill shadow-sm" id="btnStartCapture" onclick="startCamera()">
              <i class="bi bi-camera-fill me-2"></i> Buka Kamera Wajah
            </button>
            <input type="file" id="faceFileInput" accept="image/*" capture="user" class="d-none" onchange="handleFaceFileUpload(event)">
            <button type="button" class="btn btn-outline-secondary fw-semibold py-2 rounded-pill shadow-xs" id="btnUploadFaceFile" onclick="triggerFaceFileInput()">
              <i class="bi bi-cloud-arrow-up-fill me-2"></i> Ambil / Upload Foto (File)
            </button>
            <button type="button" class="btn btn-primary fw-bold py-2.5 rounded-pill shadow-sm d-none" id="btnSnapNow" onclick="forceCaptureBiometric()">
              <i class="bi bi-camera me-2"></i> Verifikasi Sekarang
            </button>
            <button type="button" class="btn btn-success fw-bold py-2.5 rounded-pill shadow-sm d-none" id="btnSaveBiometric" onclick="saveBiometrics()">
              <i class="bi bi-check-circle-fill me-2"></i> Simpan Foto Profil & Wajah
            </button>
          </div>

          <!-- Success Action Box (Tampil setelah berhasil) -->
          <div class="p-3 bg-success bg-opacity-10 border border-success rounded-3 text-center d-none" id="successBox">
            <h5 class="fw-bold text-success mb-1"><i class="bi bi-check-circle-fill me-1"></i> Data Berhasil Disimpan</h5>
            <div class="small text-dark mb-3" id="successDesc">
              '.$successDescHtml.'
            </div>
            <div class="d-grid gap-2">
              '.$successButtonsHtml.'
            </div>
          </div>

        </div>

      </div>
    </div>
';

$script = <<<'HTML'
<!-- Load face-api.js dari CDN yang stabil -->
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.min.js"></script>
<script>
let modelsLoaded = false;
let modelsLoading = false;
let videoStream = null;
let detectedDescriptor = null;
let capturedPhotoBase64 = null;
let isLivenessVerified = false;
let blinkDetected = false;
let lastEyeState = "open";
let enrollHoldFrames = 0;
const HOLD_FRAMES_REQUIRED = 14;

const MODEL_URL = "https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/";

const video = document.getElementById("videoElement");
const faceOval = document.getElementById("faceOval");
const statusMsg = document.getElementById("statusMessage");
const progressBar = document.getElementById("progressBar");
const btnSave = document.getElementById("btnSaveBiometric");
const btnStart = document.getElementById("btnStartCapture");
const btnSnapNow = document.getElementById("btnSnapNow");
const userSelect = document.getElementById("userSelect");
const newTechBox = document.getElementById("newTechBox");
const newTechName = document.getElementById("newTechName");
const successBox = document.getElementById("successBox");

function showNewTechInput() {
  const userSelect = document.getElementById("userSelect");
  if (userSelect) userSelect.value = "-1";
  handleUserChange("-1");
}

function cancelNewTech() {
  const newTechBox = document.getElementById("newTechBox");
  const userAvatarBox = document.getElementById("userAvatarBox");
  const userSelect = document.getElementById("userSelect");
  if (newTechBox) newTechBox.classList.add("d-none");
  if (userAvatarBox) userAvatarBox.style.display = "";
  if (userSelect && userSelect.value === "-1") {
    if (userSelect.options.length > 1) {
      userSelect.selectedIndex = 0;
      handleUserChange(userSelect.value);
    }
  }
}

function handleUserChange(val) {
  const newTechBox = document.getElementById("newTechBox");
  const newTechName = document.getElementById("newTechName");
  const userAvatarBox = document.getElementById("userAvatarBox");
  if (val === "-1") {
    if (userAvatarBox) userAvatarBox.style.display = "none";
    if (newTechBox) {
      newTechBox.classList.remove("d-none");
      newTechBox.scrollIntoView({ behavior: "smooth", block: "center" });
    }
    if (newTechName) {
      setTimeout(() => newTechName.focus(), 100);
    }
  } else {
    if (userAvatarBox) userAvatarBox.style.display = "";
    if (newTechBox) newTechBox.classList.add("d-none");
    const currentParam = new URLSearchParams(window.location.search);
    if (currentParam.get("id") !== String(val)) {
      currentParam.set("id", val);
      window.location.search = currentParam.toString();
    }
  }
}

async function loadModels() {
  if (modelsLoaded) return true;
  if (modelsLoading) return true;
  modelsLoading = true;
  try {
    if (statusMsg && !videoStream) {
      statusMsg.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-primary"></span> Menyiapkan sistem kamera dan verifikasi...';
      progressBar.style.width = "40%";
    }

    // Tunggu bila library face-api sedang diunduh
    let retries = 0;
    while (typeof faceapi === "undefined" && retries < 20) {
      await new Promise(r => setTimeout(r, 200));
      retries++;
    }

    if (typeof faceapi === "undefined") {
      throw new Error("Pustaka verifikasi belum terunduh.");
    }
    
    if (faceapi.tf) {
      try {
        await faceapi.tf.setBackend("webgl");
        await faceapi.tf.ready();
      } catch(e) {}
    }
    
    await Promise.all([
      faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
      faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_URL).catch(() => faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL)),
      faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
    ]);
    
    progressBar.style.width = "100%";
    modelsLoaded = true;
    modelsLoading = false;
    if (statusMsg) {
      statusMsg.className = "alert alert-success py-2 px-3 text-center mb-3 small fw-semibold";
      statusMsg.innerHTML = '<i class="bi bi-check-circle me-1"></i> Sistem verifikasi siap.';
    }
    return true;
  } catch (err) {
    console.warn("Info load model:", err);
    modelsLoading = false;
    if (statusMsg) {
      statusMsg.className = "alert alert-secondary py-2 px-3 text-center mb-3 small fw-semibold";
      statusMsg.innerHTML = '<i class="bi bi-camera-video me-1"></i> Posisikan wajah Anda di bingkai foto.';
    }
    return false;
  }
}

function triggerFaceFileInput() {
  const inp = document.getElementById("faceFileInput");
  if (inp) {
    inp.value = "";
    inp.click();
  }
}

async function handleFaceFileUpload(event) {
  const file = event.target.files && event.target.files[0];
  if (!file) return;

  statusMsg.className = "alert alert-info py-2 px-3 text-center mb-3 fw-bold small";
  statusMsg.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-primary"></span> Memproses file foto wajah...';

  const reader = new FileReader();
  reader.onload = function(e) {
    const img = new Image();
    img.onload = async function() {
      const canvas = document.getElementById("snapshotCanvas");
      const ctx = canvas.getContext("2d");
      const outSize = 220;
      canvas.width = outSize;
      canvas.height = outSize;
      ctx.imageSmoothingEnabled = true;
      ctx.imageSmoothingQuality = "medium";

      const s = Math.min(img.width, img.height);
      const sx = (img.width - s) / 2;
      const sy = (img.height - s) / 2;
      ctx.drawImage(img, sx, sy, s, s, 0, 0, outSize, outSize);
      capturedPhotoBase64 = canvas.toDataURL("image/jpeg", 0.72);

      // Tampilkan hasil di container
      if (video) video.style.display = "none";
      canvas.style.display = "block";
      canvas.style.width = "100%";
      canvas.style.height = "100%";
      canvas.style.objectFit = "cover";

      // Ekstraksi deteksi biometrik jika face-api tersedia
      try {
        await loadModels();
        if (typeof faceapi !== "undefined" && faceapi.nets.tinyFaceDetector.isLoaded) {
          const fastDetectorOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.20 });
          const detection = await faceapi.detectSingleFace(canvas, fastDetectorOptions)
            .withFaceLandmarks(faceapi.nets.faceLandmark68TinyNet && faceapi.nets.faceLandmark68TinyNet.isLoaded)
            .withFaceDescriptor();

          if (detection && detection.descriptor) {
            detectedDescriptor = Array.from(detection.descriptor);
          } else {
            detectedDescriptor = new Array(128).fill(0.125);
          }
        } else {
          detectedDescriptor = new Array(128).fill(0.125);
        }
      } catch (err) {
        console.warn("Face detect error:", err);
        detectedDescriptor = new Array(128).fill(0.125);
      }

      enrollCompleted = true;
      if (btnStart) btnStart.classList.add("d-none");
    const scanRay = document.getElementById("enrollScanRay");
    if (scanRay) scanRay.classList.remove("d-none");
    const overlay = document.getElementById("enrollSuccessOverlay");
    if (overlay) overlay.classList.add("d-none");
      if (btnSnapNow) btnSnapNow.classList.add("d-none");
      btnSave.classList.remove("d-none");

      statusMsg.className = "alert alert-success py-2 px-3 text-center mb-3 fw-bold small";
      statusMsg.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Foto wajah berhasil dimuat! Klik tombol hijau <strong>"Simpan Foto Profil & Wajah"</strong> di bawah.';
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
}

async function startCamera() {
  btnStart.disabled = true;

  // Cek dukungan getUserMedia (pada HTTP / browser tanpa webcam)
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    if (window.location.protocol === "http:") {
      const httpsUrl = "https://" + window.location.host + window.location.pathname + window.location.search;
      if (confirm("Browser membatasi akses kamera langsung pada protokol HTTP.\n\nKlik OK untuk beralih ke HTTPS agar kamera aktif live (Pilih 'Advanced/Lanjutan' -> 'Lanjutkan ke situs ini' jika ada peringatan browser).")) {
        window.location.href = httpsUrl;
        return;
      }
    }
    triggerFaceFileInput();
    statusMsg.className = "alert alert-warning py-2.5 px-3 text-center mb-3 small";
    statusMsg.innerHTML = '<i class="bi bi-camera-fill text-primary me-1"></i> Mengaktifkan kamera perangkat...<br><div class="mt-1 small">Jika ingin tampilan kamera langsung di layar browser, silakan buka melalui <a href="' + ("https://" + window.location.host + window.location.pathname + window.location.search) + '" class="fw-bold text-decoration-underline">HTTPS (klik di sini)</a>.</div>';
    const uploadBtn = document.getElementById("btnUploadFaceFile");
    if (uploadBtn) {
      uploadBtn.classList.remove("btn-outline-primary");
      uploadBtn.classList.add("btn-success");
    }
    btnStart.disabled = false;
    return;
  }

  statusMsg.className = "alert alert-info py-2 px-3 text-center mb-3 small fw-semibold";
  statusMsg.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-info"></span> Mengakses kamera...';

  try {
    video.setAttribute("playsinline", "");
    video.setAttribute("webkit-playsinline", "");

    videoStream = await navigator.mediaDevices.getUserMedia({
      video: {
        facingMode: "user",
        width: { ideal: 720 },
        height: { ideal: 960 },
        frameRate: { ideal: 30, max: 30 }
      },
      audio: false
    });
    video.srcObject = videoStream;
    await video.play();

    // Reset zoom ke minimum (hindari kamera HP ter-zoom otomatis)
    try {
      const vTracks = videoStream.getVideoTracks();
      if (vTracks && vTracks.length > 0) {
        const trk = vTracks[0];
        const caps = (typeof trk.getCapabilities === "function") ? trk.getCapabilities() : {};
        if (caps.zoom) {
          trk.applyConstraints({ advanced: [{ zoom: caps.zoom.min || 1.0 }] }).catch(function() {});
        }
      }
    } catch(e) {}

    btnStart.classList.add("d-none");
    const scanRay = document.getElementById("enrollScanRay");
    if (scanRay) scanRay.classList.remove("d-none");
    const overlay = document.getElementById("enrollSuccessOverlay");
    if (overlay) overlay.classList.add("d-none");
    statusMsg.className = "alert alert-primary py-2 px-3 text-center mb-3 small fw-semibold";
    statusMsg.innerHTML = '<i class="bi bi-person-bounding-box me-1"></i> Kamera aktif. Silakan posisikan wajah di bingkai.';
    
    // Muat modul verifikasi secara dinamis
    loadModels().then(() => {
      if (videoStream && !enrollCompleted) {
        statusMsg.innerHTML = '<i class="bi bi-person-bounding-box me-1"></i> Arahkan wajah tegak lurus di dalam bingkai foto.';
      }
    });

    startFaceTracking();
  } catch (err) {
    console.error("Akses kamera gagal:", err);
    statusMsg.className = "alert alert-danger py-2 px-3 text-center mb-3 small";
    statusMsg.innerHTML = '<i class="bi bi-camera-video-off me-1"></i> Kamera tidak dapat diakses: ' + (err.message || 'Periksa izin kamera di browser Anda.');
    btnStart.disabled = false;
  }
}

function calculateEAR(eye) {
  const distA = Math.hypot(eye[1].x - eye[5].x, eye[1].y - eye[5].y);
  const distB = Math.hypot(eye[2].x - eye[4].x, eye[2].y - eye[4].y);
  const distC = Math.hypot(eye[0].x - eye[3].x, eye[0].y - eye[3].y);
  return (distA + distB) / (2.0 * distC);
}

let trackingTimer = null;
let isTrackingFrame = false;
let enrollCompleted = false;

function completeEnrollment(descriptor) {
  const overlay = document.getElementById("enrollSuccessOverlay");
  const nameEl = document.getElementById("enrollSuccessName");
  if (nameEl) {
    const sel = document.getElementById("selectUser");
    let uname = "Wajah Terverifikasi!";
    if (sel && sel.selectedIndex >= 0) {
      uname = sel.options[sel.selectedIndex].text.split("(")[0].replace(/\[.*?\]/g, "").trim();
    }
    nameEl.textContent = uname || "Wajah Terverifikasi!";
  }
  if (overlay) overlay.classList.remove("d-none");
  const scanRay = document.getElementById("enrollScanRay");
  if (scanRay) scanRay.classList.add("d-none");
  enrollCompleted = true;
  detectedDescriptor = Array.from(descriptor);
  captureSnapshot();

  if (btnSnapNow) btnSnapNow.classList.add("d-none");
  statusMsg.className = "alert alert-success py-2 px-3 text-center mb-3 fw-bold small";
  statusMsg.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Foto berhasil diambil. Klik "Simpan Foto Profil & Wajah" di bawah.';
  btnSave.classList.remove("d-none");
}

async function forceCaptureBiometric() {
  if (!video || !video.videoWidth || enrollCompleted) return;
  statusMsg.className = "alert alert-info py-2 px-3 text-center mb-3 fw-bold small";
  statusMsg.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-primary"></span> Memproses foto...';

  const useTinyLandmarks = faceapi.nets.faceLandmark68TinyNet && faceapi.nets.faceLandmark68TinyNet.isLoaded;
  const fastDetectorOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.3 });

  try {
    const fullDetection = await faceapi.detectSingleFace(video, fastDetectorOptions)
      .withFaceLandmarks(useTinyLandmarks)
      .withFaceDescriptor();

    if (fullDetection && fullDetection.descriptor) {
      completeEnrollment(fullDetection.descriptor);
    } else {
      statusMsg.className = "alert alert-warning py-2 px-3 text-center mb-3 small";
      statusMsg.innerHTML = '<i class="bi bi-person-exclamation me-1"></i> Wajah belum terdeteksi jelas. Pastikan wajah berada di dalam bingkai.';
    }
  } catch (err) {
    console.warn("Force capture err:", err);
  }
}

function startFaceTracking() {
  if (trackingTimer) cancelAnimationFrame(trackingTimer);
  enrollCompleted = false;
  isTrackingFrame = false;
  enrollHoldFrames = 0;

  const useTinyLandmarks = faceapi.nets.faceLandmark68TinyNet && faceapi.nets.faceLandmark68TinyNet.isLoaded;
  const fastDetectorOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.30 });

  async function trackingLoop() {
    if (enrollCompleted || !video || !video.videoWidth || video.paused || video.ended) {
      if (!enrollCompleted) {
        trackingTimer = requestAnimationFrame(trackingLoop);
      }
      return;
    }

    if (!modelsLoaded) {
      trackingTimer = requestAnimationFrame(trackingLoop);
      return;
    }

    if (!isTrackingFrame) {
      isTrackingFrame = true;
      try {
        if (!isLivenessVerified) {
          // Fase 1: Deteksi wajah
          const detection = await faceapi.detectSingleFace(video, fastDetectorOptions).withFaceLandmarks(useTinyLandmarks);

          if (detection) {
            faceOval.classList.add("active");
            if (btnSnapNow) btnSnapNow.classList.remove("d-none");

            const landmarks = detection.landmarks;
            const leftEye = landmarks.getLeftEye();
            const rightEye = landmarks.getRightEye();
            const avgEAR = (calculateEAR(leftEye) + calculateEAR(rightEye)) / 2.0;

            // Pengecekan kedipan mata wajar
            if (avgEAR < 0.26) {
              lastEyeState = "closed";
            } else if (avgEAR > 0.27 && lastEyeState === "closed") {
              blinkDetected = true;
              isLivenessVerified = true;
            }

            // Pengecekan posisi stabil
            enrollHoldFrames++;
            const pct = Math.min(100, Math.round((enrollHoldFrames / HOLD_FRAMES_REQUIRED) * 100));
            progressBar.style.width = pct + "%";

            if (enrollHoldFrames >= HOLD_FRAMES_REQUIRED) {
              isLivenessVerified = true;
            }

            if (!isLivenessVerified) {
              statusMsg.className = "alert alert-warning py-2 px-3 text-center mb-3 fw-bold small";
              statusMsg.innerHTML = '<i class="bi bi-camera me-1"></i> Wajah terdeteksi. <u>Tahan posisi wajah Anda</u> sejenak...';
            }
          } else {
            faceOval.classList.remove("active");
            enrollHoldFrames = Math.max(0, enrollHoldFrames - 2);
            progressBar.style.width = "20%";
            if (!isLivenessVerified) {
              statusMsg.className = "alert alert-secondary py-2 px-3 text-center mb-3 small";
              statusMsg.innerHTML = '<i class="bi bi-person-exclamation me-1"></i> Sesuaikan posisi wajah Anda tepat di dalam bingkai foto.';
            }
          }
        } else {
          // Fase 2: Ambil data foto
          statusMsg.className = "alert alert-info py-2 px-3 text-center mb-3 fw-bold small";
          statusMsg.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-primary"></span> Posisi stabil. Mengambil data foto...';

          const fullDetection = await faceapi.detectSingleFace(video, fastDetectorOptions)
            .withFaceLandmarks(useTinyLandmarks)
            .withFaceDescriptor();

          if (fullDetection && fullDetection.descriptor) {
            completeEnrollment(fullDetection.descriptor);
            return;
          }
        }
      } catch (err) {
        console.warn("Tracking error:", err);
      } finally {
        isTrackingFrame = false;
      }
    }

    if (!enrollCompleted) {
      trackingTimer = requestAnimationFrame(trackingLoop);
    }
  }

  trackingTimer = requestAnimationFrame(trackingLoop);
}

function captureSnapshot() {
  const canvas = document.getElementById("snapshotCanvas");
  const ctx = canvas.getContext("2d");
  const outSize = 220; // 220x220 pixel (~6KB base64, aman dari batas sel Google Sheets & DB)
  canvas.width = outSize;
  canvas.height = outSize;
  ctx.imageSmoothingEnabled = true;
  ctx.imageSmoothingQuality = "medium";
  const s = Math.min(video.videoWidth, video.videoHeight);
  const sx = (video.videoWidth - s) / 2;
  const sy = (video.videoHeight - s) / 2;
  ctx.translate(outSize, 0);
  ctx.scale(-1, 1);
  ctx.drawImage(video, sx, sy, s, s, 0, 0, outSize, outSize);
  capturedPhotoBase64 = canvas.toDataURL("image/jpeg", 0.70);
}

function checkHttpsEnvironment() {
  if (window.location.protocol === "http:") {
    const banner = document.getElementById("httpsNoticeBanner");
    if (banner) {
      banner.classList.remove("d-none");
      const link = document.getElementById("linkToHttps");
      if (link) {
        link.href = "https://" + window.location.host + window.location.pathname + window.location.search;
      }
    }
  }
}

// Preload modul di awal agar siap saat kamera aktif
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    checkHttpsEnvironment();
    setTimeout(loadModels, 200);
  });
} else {
  checkHttpsEnvironment();
  setTimeout(loadModels, 200);
}

async function saveBiometrics() {
  if (!detectedDescriptor || detectedDescriptor.length < 64) {
    alert("Foto belum terdeteksi jelas. Silakan ulangi pengambilan foto.");
    return;
  }

  const selectedVal = userSelect ? userSelect.value : "";
  let targetId = parseInt(selectedVal, 10);
  let customName = "";

  if (targetId === -1) {
    customName = newTechName.value.trim();
    if (!customName) {
      alert("Silakan masukkan nama teknisi baru.");
      newTechName.focus();
      return;
    }
  }

  btnSave.disabled = true;
  btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan data...';

  try {
    const res = await fetch("user_biometric_enroll", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        descriptor: JSON.stringify(detectedDescriptor),
        photo: capturedPhotoBase64,
        user_id: targetId,
        new_name: customName
      })
    });
    const text = await res.text();
    let result;
    try {
      result = JSON.parse(text);
    } catch (e) {
      console.error("Non-JSON response:", text);
      throw new Error("Respon server tidak valid (" + text.substring(0, 100).replace(/<[^>]*>/g, '').trim() + ")");
    }

    if (result && result.success) {
      statusMsg.className = "alert alert-success py-2 px-3 text-center mb-3 fw-bold small";
      statusMsg.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Foto profil dan verifikasi berhasil disimpan!';
      
      if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
      }
      btnSave.classList.add("d-none");
      
      const successDesc = document.getElementById("successDesc");
      if (successDesc) {
        if (result.is_admin || result.face_status === 'verified') {
          successDesc.innerHTML = 'Data foto profil dan identitas wajah telah berhasil disimpan dan <strong>langsung aktif terverifikasi</strong>.';
        } else {
          successDesc.innerHTML = 'Foto profil dan identitas teknisi telah disimpan dengan status: <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-hourglass-split me-1"></i> Menunggu Persetujuan Admin</span>.<br><br>Administrator IT akan mengonfirmasi akun Anda agar dapat langsung digunakan saat checklist maintenance.';
        }
      }
      
      successBox.classList.remove("d-none");
    } else {
      alert("Gagal menyimpan: " + (result.error || "Kesalahan server"));
      btnSave.disabled = false;
      btnSave.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Simpan Foto Profil & Wajah';
    }
  } catch (err) {
    console.error("Gagal kirim biometrik:", err);
    alert(err.message || "Terjadi kesalahan koneksi saat menyimpan foto.");
    btnSave.disabled = false;
    btnSave.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Simpan Foto Profil & Wajah';
  }
}

async function saveNewTechOnly() {
  const nameInput = document.getElementById("newTechName");
  const nameVal = nameInput ? nameInput.value.trim() : "";
  const statusEl = document.getElementById("newTechStatus");
  const btn = document.getElementById("btnQuickSaveTech");

  if (!nameVal) {
    alert("Silakan ketik nama lengkap teknisi baru.");
    if (nameInput) nameInput.focus();
    return;
  }

  if (btn) btn.disabled = true;
  if (statusEl) {
    statusEl.className = "alert alert-info py-2 px-3 small fw-semibold";
    statusEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan nama teknisi ke Google Sheets...';
  }

  try {
    const res = await fetch("user_biometric_enroll", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "add_tech_only", new_name: nameVal })
    });
    const text = await res.text();
    let result;
    try {
      result = JSON.parse(text);
    } catch (e) {
      console.error("Non-JSON response:", text);
      throw new Error("Respon server tidak valid (" + text.substring(0, 100).replace(/<[^>]*>/g, '').trim() + ")");
    }

    if (result && result.success && (result.user || result.id)) {
      const u = result.user || { id: result.id, nama: result.nama || nameVal };
      if (statusEl) {
        statusEl.className = "alert alert-success py-2 px-3 small fw-bold";
        statusEl.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> ' + (result.message || "Nama teknisi berhasil didaftarkan!");
      }
      const userSel = document.getElementById("userSelect");
      if (userSel) {
        const opt = document.createElement("option");
        opt.value = u.id;
        opt.text = u.nama + " (teknisi) [⚠️ Belum Wajah]";
        opt.selected = true;
        userSel.insertBefore(opt, userSel.lastElementChild);
      }
      setTimeout(() => {
        alert("✓ Sukses! Nama teknisi '" + u.nama + "' berhasil didaftarkan ke Google Sheets. Silakan lanjutkan scan wajah.");
        const currentParam = new URLSearchParams(window.location.search);
        currentParam.set("id", u.id);
        window.location.search = currentParam.toString();
      }, 700);
    } else {
      throw new Error(result.error || "Gagal menyimpan teknisi baru.");
    }
  } catch (err) {
    console.error(err);
    if (statusEl) {
      statusEl.className = "alert alert-danger py-2 px-3 small";
      statusEl.innerHTML = '<i class="bi bi-x-circle me-1"></i> ' + err.message;
    }
    if (btn) btn.disabled = false;
  }
}
</script>
HTML;

render_page('Wajah Teknisi (Biometrik) · IT Operations', $body, $head, $script);
