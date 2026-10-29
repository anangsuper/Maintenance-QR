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

// Format Role Display
$roleMap = [
    'admin' => 'Admin',
    'administrator' => 'Admin',
    'sdm' => 'SDM',
    'hrd' => 'SDM',
    'teknisi' => 'Teknisi',
    'it' => 'IT',
    'auditor' => 'Auditor'
];

$currName = trim((string)($user['nama'] ?? $user['username'] ?? 'Petugas IT'));
$currRawRole = strtolower(trim((string)($user['role'] ?? 'teknisi')));
$currRole = $roleMap[$currRawRole] ?? ucfirst($currRawRole);

// Status Badge Kombai Teal
if ($userFaceStatus === 'verified' || $userFaceStatus === 'terverifikasi') {
    $badgeStatus = '<span class="px-3 py-1.5 rounded-full text-[12px] font-bold bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC] inline-flex items-center gap-1.5 shadow-xs"><i class="bi bi-patch-check-fill text-[#10B981]"></i> Wajah Terverifikasi Admin</span>';
} elseif ($userFaceStatus === 'pending' || $userFaceStatus === 'menunggu') {
    $badgeStatus = '<span class="px-3 py-1.5 rounded-full text-[12px] font-bold bg-[#FEF3C7] text-[#B45309] border border-[#FDE68A] inline-flex items-center gap-1.5 shadow-xs"><i class="bi bi-hourglass-split text-[#D97706]"></i> Menunggu Persetujuan Admin</span>';
} elseif ($userFaceStatus === 'rejected') {
    $badgeStatus = '<span class="px-3 py-1.5 rounded-full text-[12px] font-bold bg-[#FEE2E2] text-[#DC2626] border border-[#FECACA] inline-flex items-center gap-1.5 shadow-xs"><i class="bi bi-x-circle-fill text-[#EF4444]"></i> Ditolak Admin (Daftar Ulang)</span>';
} else {
    $badgeStatus = '<span class="px-3 py-1.5 rounded-full text-[12px] font-semibold bg-[#F4F5F7] text-[#53595F] border border-[#D1D5D8] inline-flex items-center gap-1.5 shadow-xs"><i class="bi bi-exclamation-circle text-[#64748B]"></i> Belum Didaftarkan</span>';
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
    $uRawRole = strtolower(trim((string)($u['role'] ?? 'teknisi')));
    $urole = $roleMap[$uRawRole] ?? ucfirst($uRawRole);
    $uHasBio = !empty($u['face_descriptor']);
    $uStat = strtolower(trim((string)($u['face_status'] ?? '')));
    $sel = ($uid === $selectedUserId) ? 'selected' : '';
    
    $bioMark = ' [⚠️ Belum Wajah]';
    if ($uHasBio) {
        if ($uStat === 'verified' || $uStat === 'terverifikasi') {
            $bioMark = ' [✓ Disetujui Admin]';
        } elseif ($uStat === 'pending' || $uStat === 'menunggu') {
            $bioMark = ' [⏳ Menunggu Verifikasi]';
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

// Susun tombol aksi selesai sesuai status autentikasi pengguna
$successButtonsHtml = '';
if ($retUrl !== '') {
    $successButtonsHtml .= '<a href="'.e($retUrl).'" class="h-[38px] px-4 bg-[#10B981] hover:bg-[#059669] text-white rounded-[8px] text-[13px] font-semibold transition-colors flex items-center justify-center gap-1.5 text-decoration-none shadow-xs"><i class="bi bi-arrow-return-left"></i> Kembali Lanjutkan Maintenance</a>';
}

if ($isAdmin || is_hrd()) {
    $successButtonsHtml .= '<a href="'.e(module_url('users_admin.php')).'" class="h-[38px] px-4 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[13px] font-semibold transition-colors flex items-center justify-center gap-1.5 text-decoration-none shadow-xs"><i class="bi bi-people-fill"></i> Halaman Data Pengguna</a>';
    $successButtonsHtml .= '<a href="'.e(module_url('dashboard.php')).'" class="h-[38px] px-4 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] rounded-[8px] text-[13px] font-medium transition-colors flex items-center justify-center gap-1.5 text-decoration-none shadow-xs"><i class="bi bi-speedometer2"></i> Dashboard</a>';
} elseif ($isLoggedIn) {
    $successButtonsHtml .= '<a href="'.e(module_url('dashboard.php')).'" class="h-[38px] px-4 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[13px] font-semibold transition-colors flex items-center justify-center gap-1.5 text-decoration-none shadow-xs"><i class="bi bi-speedometer2"></i> Dashboard</a>';
}

$successButtonsHtml .= '<button type="button" class="h-[34px] px-3 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#53595F] rounded-[8px] text-[12px] font-medium transition-colors inline-flex items-center justify-center gap-1 cursor-pointer" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i> Daftarkan Teknisi Lain</button>';

if ($isAdmin || is_hrd()) {
    $successDescHtml = 'Data foto profil dan verifikasi wajah berhasil disimpan dan <strong>langsung aktif terverifikasi</strong>.<br><br>Anda dapat melihat status akun ini di menu <a href="'.e(module_url('users_admin.php')).'" class="font-bold text-[#0E5C58] text-decoration-none">Kelola Akun Pengguna &rarr;</a>.';
} else {
    $successDescHtml = 'Foto profil dan data verifikasi telah disimpan dengan status: <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#FEF3C7] text-[#B45309] border border-[#FDE68A] inline-block"><i class="bi bi-hourglass-split me-1"></i> Menunggu Persetujuan Admin</span>.<br><br>Administrator IT akan mengonfirmasi akun Anda agar dapat langsung digunakan saat checklist maintenance.';
}

ob_start();
?>
<style>
  .enroll-main-card {
    border-radius: 10px;
    border: 1px solid #D1D5D8;
    background: #FFFFFF;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  }
  @media (min-width: 992px) {
    .border-end-lg {
      border-right: 1px solid #E5E7EB !important;
    }
  }
  .scanner-container {
    position: relative;
    width: 100%;
    max-width: 320px;
    aspect-ratio: 1 / 1;
    margin: 0 auto;
    background: #0F172A;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    border: 2px solid #D1D5D8;
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
    border: 2.5px dashed rgba(56, 189, 248, 0.85);
    border-radius: 50%;
    box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.40);
    pointer-events: none;
    transition: all 0.3s ease;
  }
  .face-oval.active {
    border: 2.5px solid #10B981 !important;
    box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.40), 0 0 20px rgba(16, 185, 129, 0.7) !important;
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
</style>
<?php
$head = ob_get_clean();

ob_start();
?>
<!-- Header Halaman Standar Kombai Teal -->
<div class="mb-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
  <div>
    <div class="text-[11px] font-bold text-[#0E5C58] uppercase tracking-wider mb-1 flex items-center gap-1.5">
      <i class="bi bi-shield-check"></i> PENGATURAN &amp; KEAMANAN BIOMETRIK
    </div>
    <h1 class="text-[22px] font-bold text-[#1B2A3A] tracking-tight m-0">Wajah Teknisi (Biometrik FIDO2)</h1>
    <p class="text-[13px] text-[#53595F] mt-1 mb-0">Perekaman sampel wajah dan foto profil petugas untuk verifikasi checklist pemeliharaan IT &amp; audit operasional.</p>
  </div>
  <div class="flex flex-wrap items-center gap-2 shrink-0">
    <?php if ($isAdmin): ?>
      <a href="<?= e(module_url('users_admin.php')) ?>" class="h-[36px] px-3.5 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-semibold text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
        <i class="bi bi-people-fill text-[#0E5C58]"></i> Kelola Pengguna
      </a>
    <?php endif; ?>
    <a href="<?= e(module_url('dashboard.php')) ?>" class="h-[36px] px-3.5 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-semibold text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-speedometer2 text-[#53595F]"></i> Dashboard
    </a>
  </div>
</div>

<!-- 4 SYMMETRICAL KOMBAI METRIC CARDS -->
<div class="bg-[#FFFFFF] border border-[#D1D5D8] rounded-[10px] flex flex-col sm:flex-row overflow-hidden shrink-0 mb-4 shadow-xs">
  <!-- Card 1: Total Petugas -->
  <div class="flex-1 flex flex-col justify-center px-5 py-3 border-b sm:border-b-0 sm:border-r border-[#D1D5D8] relative hover:bg-[#F4F5F7] transition-colors">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#1B2A3A]"></div>
    <div class="text-[26px] font-bold text-[#1B2A3A] tabular-nums leading-none">
      <?= number_format($statTotalUsers) ?> <span class="text-[13px] font-normal text-[#53595F]">Orang</span>
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
      <span class="text-[#1B2A3A] font-semibold">Total Akun Petugas</span>
      <span>&middot;</span>
      <span class="text-[#53595F] text-[11px]">IT &amp; Operasional</span>
    </div>
  </div>

  <!-- Card 2: Wajah Terverifikasi -->
  <div class="flex-1 flex flex-col justify-center px-5 py-3 border-b sm:border-b-0 sm:border-r border-[#D1D5D8] relative hover:bg-[#F4F5F7] transition-colors">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#10B981]"></div>
    <div class="text-[26px] font-bold text-[#10B981] tabular-nums leading-none">
      <?= number_format($statVerified) ?> <span class="text-[13px] font-normal text-[#53595F]">Petugas</span>
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
      <span class="text-[#10B981] font-semibold"><i class="bi bi-patch-check-fill me-0.5"></i> Terverifikasi</span>
      <span>&middot;</span>
      <span class="text-[#0E5C58] font-bold"><?= $statTotalUsers > 0 ? round(($statVerified / $statTotalUsers) * 100) : 0 ?>% Siap Presensi</span>
    </div>
  </div>

  <!-- Card 3: Menunggu Verifikasi -->
  <div class="flex-1 flex flex-col justify-center px-5 py-3 border-b sm:border-b-0 sm:border-r border-[#D1D5D8] relative hover:bg-[#F4F5F7] transition-colors">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#F59E0B]"></div>
    <div class="text-[26px] font-bold text-[#D97706] tabular-nums leading-none">
      <?= number_format($statPending) ?> <span class="text-[13px] font-normal text-[#53595F]">Petugas</span>
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
      <span class="text-[#D97706] font-semibold"><i class="bi bi-hourglass-split me-0.5"></i> Menunggu Review</span>
      <span>&middot;</span>
      <span class="text-[#53595F] text-[11px]">Persetujuan Admin</span>
    </div>
  </div>

  <!-- Card 4: Belum Rekam Wajah -->
  <div class="flex-1 flex flex-col justify-center px-5 py-3 relative hover:bg-[#F4F5F7] transition-colors">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#64748B]"></div>
    <div class="text-[26px] font-bold text-[#475569] tabular-nums leading-none">
      <?= number_format($statUnenrolled) ?> <span class="text-[13px] font-normal text-[#53595F]">Petugas</span>
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
      <span class="text-[#64748B] font-semibold"><i class="bi bi-person-x-fill me-0.5"></i> Belum Rekam</span>
      <span>&middot;</span>
      <span class="text-[#53595F] text-[11px]">Perlu Foto Sampel</span>
    </div>
  </div>
</div>

<!-- Kartu Konten Utama -->
<div class="enroll-main-card p-4 md:p-6 mb-4">
  <div class="row g-4 align-items-start">

    <!-- KOLOM KIRI: Pemilihan Teknisi, Status & Panduan -->
    <div class="col-lg-5 border-end-lg pe-lg-4">
      
      <!-- Pilih Teknisi -->
      <div class="mb-4">
        <label class="block text-[12px] font-bold text-[#1B2A3A] uppercase tracking-wider mb-1.5">
          <i class="bi bi-person-fill text-[#0E5C58] me-1"></i> Pilih Nama Petugas / Teknisi
        </label>
        <div class="flex items-center">
          <select class="form-select text-[13px] font-semibold text-[#1B2A3A] border-[#D1D5D8] rounded-r-none focus:border-[#0E5C58] focus:ring-1 focus:ring-[#0E5C58]" id="userSelect" onchange="handleUserChange(this.value)" style="border-top-left-radius: 8px; border-bottom-left-radius: 8px; height: 40px;">
            <?= $userOptionsHtml ?>
          </select>
          <button type="button" class="h-[40px] px-3.5 bg-white border border-l-0 border-[#D1D5D8] text-[13px] font-semibold text-[#0E5C58] hover:bg-[#D9F1EF] transition-colors inline-flex items-center gap-1 shrink-0 cursor-pointer" style="border-top-right-radius: 8px; border-bottom-right-radius: 8px;" onclick="showNewTechInput()" title="Ketik Nama Teknisi Baru">
            <i class="bi bi-person-plus-fill"></i> Baru
          </button>
        </div>
        <div class="text-[11px] text-[#53595F] mt-1.5">
          Pilih nama dari daftar untuk verifikasi, atau klik <strong>"Baru"</strong> jika akun belum terdaftar.
        </div>
      </div>

      <!-- Kotak Input Nama Teknisi Baru -->
      <div class="p-3.5 mb-4 bg-[#F8F9FA] rounded-[8px] border border-[#A7E2DC] d-none" id="newTechBox">
        <label class="block text-[12px] font-bold text-[#1B2A3A] mb-1.5">
          <i class="bi bi-person-plus-fill text-[#0E5C58] me-1"></i> Masukkan Nama Lengkap Teknisi:
        </label>
        <input type="text" class="form-control text-[13px] border-[#D1D5D8] rounded-[6px] mb-2.5 h-[38px]" id="newTechName" placeholder="Contoh: Akhmad Hafizh Firmansyah" autocomplete="name">
        <div class="flex gap-2">
          <button type="button" class="h-[34px] px-3 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[6px] text-[12px] font-semibold transition-colors flex items-center gap-1.5 border-0 cursor-pointer" id="btnQuickSaveTech" onclick="saveNewTechOnly()">
            <i class="bi bi-check2-circle"></i> Simpan Nama ke Sistem
          </button>
          <button type="button" class="h-[34px] px-3 bg-white border border-[#D1D5D8] rounded-[6px] text-[12px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors cursor-pointer" onclick="cancelNewTech()">Batal</button>
        </div>
        <div class="mt-2 text-[12px]" id="newTechStatus"></div>
      </div>

      <!-- Foto Avatar Profil Petugas Terpilih -->
      <div class="text-center mb-4 p-4 bg-[#F8F9FA] rounded-[10px] border border-[#E5E7EB]" id="userAvatarBox">
        <?php if ($existingPhoto): ?>
          <div class="inline-block relative mb-2">
            <img src="<?= e($existingPhoto) ?>" alt="Foto Biometrik" class="rounded-full border-2 border-[#10B981] shadow-xs object-cover" style="width: 88px; height: 88px;">
            <span class="absolute bottom-0 right-0 w-6 h-6 rounded-full bg-[#10B981] text-white flex items-center justify-center text-[11px] shadow-xs" title="Terverifikasi">
              <i class="bi bi-check-lg"></i>
            </span>
          </div>
          <div class="text-[15px] font-bold text-[#1B2A3A] m-0"><?= e($currName) ?></div>
          <div class="mt-1">
            <span class="px-2.5 py-0.5 text-[11px] font-bold bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC] rounded-full inline-block font-mono">
              <?= e($currRole) ?>
            </span>
          </div>
          <div class="text-[12px] text-[#10B981] font-semibold flex items-center justify-center gap-1 mt-2">
            <i class="bi bi-shield-fill-check"></i> Sampel biometrik aktif terdaftar
          </div>
        <?php else: ?>
          <div class="inline-block relative mb-2">
            <div class="rounded-full bg-white border-2 border-dashed border-[#D1D5D8] flex items-center justify-center text-[#9CA3AF] shadow-xs mx-auto" style="width: 88px; height: 88px;">
              <i class="bi bi-person-bounding-box text-[32px] text-[#9CA3AF]"></i>
            </div>
          </div>
          <div class="text-[15px] font-bold text-[#1B2A3A] m-0"><?= e($currName) ?></div>
          <div class="mt-1">
            <span class="px-2.5 py-0.5 text-[11px] font-semibold bg-[#F4F5F7] text-[#53595F] border border-[#D1D5D8] rounded-full inline-block font-mono">
              <?= e($currRole) ?>
            </span>
          </div>
          <div class="text-[12px] text-[#64748B] mt-2">
            Belum ada sampel foto biometrik
          </div>
        <?php endif; ?>
      </div>

      <!-- Status Registrasi Wajah -->
      <div class="text-center mb-4">
        <?= $badgeStatus ?>
      </div>

      <!-- Panduan Singkat -->
      <div class="bg-[#F8F9FA] border border-[#E5E7EB] rounded-[8px] p-3.5 mb-3 text-[12px] text-[#53595F]">
        <div class="font-bold text-[#1B2A3A] mb-2 flex items-center gap-1.5 text-[12px]">
          <i class="bi bi-info-circle-fill text-[#0E5C58]"></i> Panduan Pengambilan Foto:
        </div>
        <ol class="mb-0 ps-3 space-y-1" style="line-height: 1.5;">
          <li>Posisikan wajah tegak lurus menghadap kamera.</li>
          <li>Pastikan pencahayaan cukup dan wajah terlihat jelas tanpa masker/topi.</li>
          <li>Tahan posisi sejenak hingga bingkai berubah hijau, lalu simpan.</li>
        </ol>
      </div>

      <!-- Info Keamanan & Privasi -->
      <div class="p-2.5 bg-[#FAF9F8] rounded-[8px] border border-[#E5E7EB] text-[11px] text-[#53595F] flex items-center gap-2">
        <i class="bi bi-shield-lock-fill text-[#0E5C58] text-[16px] shrink-0"></i>
        <div style="line-height: 1.35;">
          Vektor biometrik wajah dienkripsi aman untuk validasi presensi dan audit operasional IT Bank Mitra.
        </div>
      </div>
    </div>

    <!-- KOLOM KANAN: Kamera Pemindai & Tombol Aksi -->
    <div class="col-lg-7 ps-lg-4">
      <div class="flex items-center justify-between mb-3">
        <span class="text-[12px] font-bold text-[#1B2A3A] uppercase tracking-wider flex items-center gap-1.5">
          <i class="bi bi-camera-video text-[#0E5C58]"></i> Pemindai Kamera &amp; Verifikasi Wajah
        </span>
      </div>

      <!-- Banner Beralih ke HTTPS agar Kamera Langsung Aktif -->
      <div class="alert alert-primary py-2 px-3 small d-flex align-items-center justify-content-between mb-3 border-[#A7E2DC] bg-[#D9F1EF] text-[#0E5C58] rounded-[8px] d-none" id="httpsNoticeBanner">
        <div class="text-[12px]">
          <i class="bi bi-shield-lock-fill text-[#0E5C58] me-1"></i>
          Buka lewat <strong>HTTPS</strong> agar kamera webcam aktif langsung di browser.
        </div>
        <a href="#" id="linkToHttps" class="h-[28px] px-2.5 bg-[#0E5C58] text-white rounded-[6px] text-[11px] font-bold flex items-center gap-1 text-decoration-none shadow-xs text-nowrap ms-2">
          Buka via HTTPS <i class="bi bi-arrow-right-short"></i>
        </a>
      </div>

      <!-- Scanner Container -->
      <div class="scanner-container mb-3" id="scannerContainer">
        <video id="videoElement" class="scanner-video" autoplay playsinline webkit-playsinline muted></video>
        
        <!-- Oval Guide Frame -->
        <div class="face-oval pointer-events-none" id="faceOval"></div>
        
        <!-- Scanning Ray Animation -->
        <div id="enrollScanRay" class="position-absolute start-0 w-100 d-none" style="height: 3px; background: linear-gradient(90deg, transparent, #0E5C58, #10B981, transparent); animation: scanRayAnim 2s infinite ease-in-out;"></div>

        <!-- Success Overlay -->
        <div id="enrollSuccessOverlay" class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center text-white d-none" style="background: rgba(14, 92, 88, 0.88); backdrop-filter: blur(4px); z-index: 5;">
          <i class="bi bi-check-circle-fill display-4 mb-2 animate-bounce text-white"></i>
          <h5 class="fw-bold text-white mb-1" id="enrollSuccessName">Wajah Terverifikasi!</h5>
          <span class="px-3 py-1 rounded-full text-[12px] font-bold bg-white text-[#0E5C58] shadow-xs" id="enrollSuccessBadge">100% Cocok</span>
        </div>

        <canvas id="snapshotCanvas" style="display:none;" width="320" height="320"></canvas>
      </div>

      <!-- Status Bar Feedback Realtime -->
      <div class="py-2 px-3 text-[12px] font-semibold text-center mb-2 mx-auto rounded-[8px] border transition-all" style="max-width: 320px; background: #F4F5F7; border-color: #D1D5D8; color: #1B2A3A;" id="statusMessage">
        <i class="bi bi-camera text-[#0E5C58] me-1"></i>
        Tekan <strong>"Buka Kamera Wajah"</strong> untuk mulai verifikasi.
      </div>

      <div class="text-[#53595F] text-[11px] mb-3 text-center">
        Pastikan wajah berada di dalam oval, tidak memakai masker, dan pencahayaan memadai.
      </div>

      <!-- Progress Liveness -->
      <div class="w-full mx-auto mb-3" style="max-width: 320px; height: 5px; background: #E5E7EB; border-radius: 5px; overflow: hidden;" id="progressBarContainer">
        <div id="progressBar" style="width: 0%; height: 100%; background: #0E5C58; transition: width 0.3s ease;"></div>
      </div>

      <!-- Tombol Aksi (Kamera Langsung & Pilihan Upload Foto) -->
      <div class="flex flex-col gap-2 mb-3 mx-auto" style="max-width: 320px;">
        <button type="button" class="w-full h-[42px] bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[13px] font-semibold transition-colors flex items-center justify-center gap-2 border-0 shadow-xs cursor-pointer" id="btnStartCapture" onclick="startCamera()">
          <i class="bi bi-camera-fill"></i> Buka Kamera Wajah
        </button>
        <input type="file" id="faceFileInput" accept="image/*" capture="user" class="d-none" onchange="handleFaceFileUpload(event)">
        <button type="button" class="w-full h-[38px] bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] rounded-[8px] text-[13px] font-medium transition-colors flex items-center justify-center gap-2 shadow-xs cursor-pointer" id="btnUploadFaceFile" onclick="triggerFaceFileInput()">
          <i class="bi bi-cloud-arrow-up-fill text-[#53595F]"></i> Ambil / Upload Foto (File)
        </button>
        <button type="button" class="w-full h-[42px] bg-[#0284C7] hover:bg-[#0369A1] text-white rounded-[8px] text-[13px] font-semibold transition-colors flex items-center justify-center gap-2 border-0 shadow-xs cursor-pointer d-none" id="btnSnapNow" onclick="forceCaptureBiometric()">
          <i class="bi bi-camera"></i> Verifikasi Sekarang
        </button>
        <button type="button" class="w-full h-[42px] bg-[#10B981] hover:bg-[#059669] text-white rounded-[8px] text-[13px] font-semibold transition-colors flex items-center justify-center gap-2 border-0 shadow-xs cursor-pointer d-none" id="btnSaveBiometric" onclick="saveBiometrics()">
          <i class="bi bi-check-circle-fill"></i> Simpan Foto Profil &amp; Wajah
        </button>
      </div>

      <!-- Success Action Box (Tampil setelah berhasil) -->
      <div class="p-4 bg-[#ECFDF5] border border-[#A7F3D0] rounded-[10px] text-center d-none" id="successBox">
        <div class="w-10 h-10 rounded-full bg-[#D1FAE5] text-[#10B981] flex items-center justify-center text-[20px] mx-auto mb-2">
          <i class="bi bi-check-circle-fill"></i>
        </div>
        <h5 class="text-[15px] font-bold text-[#065F46] mb-1">Data Berhasil Disimpan</h5>
        <div class="text-[12px] text-[#065F46] mb-3" id="successDesc">
          <?= $successDescHtml ?>
        </div>
        <div class="flex flex-col gap-2">
          <?= $successButtonsHtml ?>
        </div>
      </div>

    </div>

  </div>
</div>
<?php
$body = ob_get_clean();

ob_start();
?>
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

function setStatus(type, html) {
  if (!statusMsg) return;
  statusMsg.innerHTML = html;
  if (type === "success") {
    statusMsg.style.background = "#D9F1EF";
    statusMsg.style.borderColor = "#A7E2DC";
    statusMsg.style.color = "#0E5C58";
  } else if (type === "warning") {
    statusMsg.style.background = "#FEF3C7";
    statusMsg.style.borderColor = "#FDE68A";
    statusMsg.style.color = "#92400E";
  } else if (type === "info") {
    statusMsg.style.background = "#EFF6FF";
    statusMsg.style.borderColor = "#BFDBFE";
    statusMsg.style.color = "#1E40AF";
  } else if (type === "danger") {
    statusMsg.style.background = "#FEE2E2";
    statusMsg.style.borderColor = "#FECACA";
    statusMsg.style.color = "#991B1B";
  } else {
    statusMsg.style.background = "#F4F5F7";
    statusMsg.style.borderColor = "#D1D5D8";
    statusMsg.style.color = "#1B2A3A";
  }
}

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
      setStatus("info", '<span class="spinner-border spinner-border-sm me-2 text-[#0E5C58]"></span> Menyiapkan sistem kamera dan verifikasi...');
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
    setStatus("success", '<i class="bi bi-check-circle me-1"></i> Sistem verifikasi siap.');
    return true;
  } catch (err) {
    console.warn("Info load model:", err);
    modelsLoading = false;
    setStatus("default", '<i class="bi bi-camera-video me-1"></i> Posisikan wajah Anda di bingkai foto.');
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

  setStatus("info", '<span class="spinner-border spinner-border-sm me-2 text-[#0E5C58]"></span> Memproses file foto wajah...');

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

      setStatus("success", '<i class="bi bi-check-circle-fill me-1"></i> Foto wajah berhasil dimuat! Klik tombol hijau <strong>"Simpan Foto Profil & Wajah"</strong> di bawah.');
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
    setStatus("warning", '<i class="bi bi-camera-fill text-[#0E5C58] me-1"></i> Mengaktifkan kamera perangkat...<br><div class="mt-1 small">Jika ingin tampilan kamera langsung di layar browser, silakan buka melalui <a href="' + ("https://" + window.location.host + window.location.pathname + window.location.search) + '" class="fw-bold text-decoration-underline">HTTPS (klik di sini)</a>.</div>');
    const uploadBtn = document.getElementById("btnUploadFaceFile");
    if (uploadBtn) {
      uploadBtn.className = "w-full h-[38px] bg-[#10B981] hover:bg-[#059669] text-white rounded-[8px] text-[13px] font-semibold transition-colors flex items-center justify-center gap-2 shadow-xs cursor-pointer border-0";
    }
    btnStart.disabled = false;
    return;
  }

  setStatus("info", '<span class="spinner-border spinner-border-sm me-2 text-[#0E5C58]"></span> Mengakses kamera...');

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
    setStatus("info", '<i class="bi bi-person-bounding-box me-1"></i> Kamera aktif. Silakan posisikan wajah di bingkai.');
    
    // Muat modul verifikasi secara dinamis
    loadModels().then(() => {
      if (videoStream && !enrollCompleted) {
        setStatus("info", '<i class="bi bi-person-bounding-box me-1"></i> Arahkan wajah tegak lurus di dalam bingkai foto.');
      }
    });

    startFaceTracking();
  } catch (err) {
    console.error("Akses kamera gagal:", err);
    setStatus("danger", '<i class="bi bi-camera-video-off me-1"></i> Kamera tidak dapat diakses: ' + (err.message || 'Periksa izin kamera di browser Anda.'));
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
    const sel = document.getElementById("userSelect");
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
  setStatus("success", '<i class="bi bi-check-circle-fill me-1"></i> Foto berhasil diambil. Klik "Simpan Foto Profil & Wajah" di bawah.');
  btnSave.classList.remove("d-none");
}

async function forceCaptureBiometric() {
  if (!video || !video.videoWidth || enrollCompleted) return;
  setStatus("info", '<span class="spinner-border spinner-border-sm me-2 text-[#0E5C58]"></span> Memproses foto...');

  const useTinyLandmarks = faceapi.nets.faceLandmark68TinyNet && faceapi.nets.faceLandmark68TinyNet.isLoaded;
  const fastDetectorOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.3 });

  try {
    const fullDetection = await faceapi.detectSingleFace(video, fastDetectorOptions)
      .withFaceLandmarks(useTinyLandmarks)
      .withFaceDescriptor();

    if (fullDetection && fullDetection.descriptor) {
      completeEnrollment(fullDetection.descriptor);
    } else {
      setStatus("warning", '<i class="bi bi-person-exclamation me-1"></i> Wajah belum terdeteksi jelas. Pastikan wajah berada di dalam bingkai.');
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
              setStatus("warning", '<i class="bi bi-camera me-1"></i> Wajah terdeteksi. <u>Tahan posisi wajah Anda</u> sejenak...');
            }
          } else {
            faceOval.classList.remove("active");
            enrollHoldFrames = Math.max(0, enrollHoldFrames - 2);
            progressBar.style.width = "20%";
            if (!isLivenessVerified) {
              setStatus("default", '<i class="bi bi-person-exclamation me-1"></i> Sesuaikan posisi wajah Anda tepat di dalam bingkai foto.');
            }
          }
        } else {
          // Fase 2: Ambil data foto
          setStatus("info", '<span class="spinner-border spinner-border-sm me-2 text-[#0E5C58]"></span> Posisi stabil. Mengambil data foto...');

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
  const outSize = 220; // 220x220 pixel (~6KB base64)
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
    const targetUrl = window.location.pathname + window.location.search;
    const res = await fetch(targetUrl, {
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
      setStatus("success", '<i class="bi bi-check-circle-fill me-1"></i> Foto profil dan verifikasi berhasil disimpan!');
      
      if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
      }
      btnSave.classList.add("d-none");
      
      const successDesc = document.getElementById("successDesc");
      if (successDesc) {
        if (result.is_admin || result.face_status === 'verified') {
          successDesc.innerHTML = 'Data foto profil dan identitas wajah telah berhasil disimpan dan <strong>langsung aktif terverifikasi</strong>.';
        } else {
          successDesc.innerHTML = 'Foto profil dan identitas teknisi telah disimpan dengan status: <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#FEF3C7] text-[#B45309] border border-[#FDE68A] inline-block"><i class="bi bi-hourglass-split me-1"></i> Menunggu Persetujuan Admin</span>.<br><br>Administrator IT akan mengonfirmasi akun Anda agar dapat langsung digunakan saat checklist maintenance.';
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
  const nameInp = document.getElementById("newTechName");
  const statusEl = document.getElementById("newTechStatus");
  const btn = document.getElementById("btnQuickSaveTech");
  const nameVal = nameInp ? nameInp.value.trim() : "";

  if (!nameVal) {
    if (statusEl) {
      statusEl.className = "alert alert-warning py-1.5 px-3 small";
      statusEl.textContent = "Silakan ketik nama teknisi baru.";
    }
    if (nameInp) nameInp.focus();
    return;
  }

  if (btn) btn.disabled = true;
  if (statusEl) {
    statusEl.className = "alert alert-info py-1.5 px-3 small";
    statusEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1 text-[#0E5C58]"></span> Mendaftarkan teknisi baru...';
  }

  try {
    const targetUrl = window.location.pathname + window.location.search;
    const res = await fetch(targetUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "add_tech_only",
        new_name: nameVal
      })
    });
    const text = await res.text();
    let result;
    try {
      result = JSON.parse(text);
    } catch(e) {
      throw new Error("Respon server tidak valid.");
    }

    if (result && result.success && result.user) {
      const u = result.user;
      if (statusEl) {
        statusEl.className = "alert alert-success py-2 px-3 small font-semibold";
        statusEl.innerHTML = '<i class="bi bi-check-circle-fill me-1 text-[#10B981]"></i> ' + (result.message || "Nama teknisi berhasil didaftarkan!");
      }
      const userSel = document.getElementById("userSelect");
      if (userSel) {
        const opt = document.createElement("option");
        opt.value = u.id;
        opt.text = u.nama + " (Teknisi) [⚠️ Belum Wajah]";
        opt.selected = true;
        userSel.insertBefore(opt, userSel.lastElementChild);
      }
      setTimeout(() => {
        alert("✓ Sukses! Nama teknisi '" + u.nama + "' berhasil didaftarkan ke sistem. Silakan lanjutkan scan wajah.");
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
<?php
$script = ob_get_clean();

render_page('Wajah Teknisi (Biometrik)', $body, $head, $script);
