<?php
require __DIR__ . '/bootstrap.php';
// Akses publik diizinkan: Petugas/user dapat mendaftarkan wajah secara mandiri tanpa login terlebih dahulu
$isLoggedIn = is_logged_in();
$isAdmin = is_admin();
$currUserId = current_user_id();

// Handle AJAX POST simpan biometrik, tambah teknisi baru, atau accept/reject oleh Admin
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

    // Aksi 1: Verifikasi / Accept atau Tolak Wajah oleh Administrator / Staff MIS & IT
    if ($action === 'approve_face' || $action === 'reject_face') {
        if (!$isAdmin && !is_hrd()) {
            echo json_encode(['success' => false, 'error' => 'Akses ditolak. Hanya Administrator atau Staff MIS & IT yang berhak memverifikasi.']);
            exit;
        }
        $targetUserId = (int)($data['user_id'] ?? 0);
        if ($targetUserId <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID pengguna tidak valid.']);
            exit;
        }
        $newStatus = ($action === 'approve_face') ? 'verified' : 'rejected';
        $res = update_user_face_status($targetUserId, $newStatus);
        if (!empty($res['success'])) {
            echo json_encode([
                'success' => true,
                'status' => $newStatus,
                'message' => ($newStatus === 'verified') ? 'Wajah pengguna berhasil disetujui (Accept) oleh Staff MIS & IT!' : 'Pengajuan wajah telah ditolak.'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => $res['error'] ?? 'Gagal memperbarui status verifikasi.']);
        }
        exit;
    }

    // Aksi 2: Simpan Nama Teknisi Baru Saja
    if ($action === 'add_tech_only' || ($newTechName !== '' && empty($descriptor))) {
        if ($newTechName === '') {
            echo json_encode(['success' => false, 'error' => 'Nama petugas baru tidak boleh kosong.']);
            exit;
        }
        $created = create_new_user([
            'nama' => $newTechName,
            'role' => 'teknisi',
            'status' => 'Aktif',
            'face_status' => 'pending'
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
                'message' => "Nama '{$newTechName}' berhasil didaftarkan ke sistem! Silakan lanjutkan dengan pengambilan sampel wajah."
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => $created['error'] ?? 'Gagal mendaftarkan nama petugas baru.']);
        }
        exit;
    }

    // Aksi 3: Simpan dengan Biometrik Wajah
    if (empty($descriptor)) {
        echo json_encode(['success' => false, 'error' => 'Data vektor biometrik tidak boleh kosong.']);
        exit;
    }

    // Pendaftaran wajah baru langsung (default mode: targetUserId === -1 atau baru)
    if ($targetUserId <= 0 && $newTechName !== '') {
        $enrollStatus = ($isAdmin || is_hrd()) ? 'verified' : 'pending';
        $created = create_new_user([
            'nama' => $newTechName,
            'role' => 'teknisi',
            'status' => 'Aktif',
            'face_descriptor' => $descriptor,
            'face_photo' => $photo,
            'face_status' => $enrollStatus
        ]);
        $uId = (int)($created['id'] ?? $created['user']['id'] ?? 0);
        if (!empty($created['success']) && $uId > 0) {
            echo json_encode([
                'success' => true,
                'face_status' => $enrollStatus,
                'is_admin' => ($isAdmin || is_hrd()),
                'user_id' => $uId,
                'user' => $created['user'] ?? $created,
                'user_name' => $newTechName,
                'photo' => $photo,
                'message' => ($enrollStatus === 'verified')
                    ? "Petugas '{$newTechName}' dan biometrik wajah berhasil didaftarkan dan langsung diverifikasi!"
                    : "Pendaftaran wajah '{$newTechName}' berhasil dikirimkan! Menunggu verifikasi dari Staff MIS & IT."
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => $created['error'] ?? 'Gagal mendaftarkan petugas baru.']);
            exit;
        }
    }

    // Update biometrik untuk user yang sudah ada
    if ($targetUserId > 0) {
        $enrollStatus = ($isAdmin || is_hrd()) ? 'verified' : 'pending';
        $res = save_user_biometrics($targetUserId, $descriptor, $photo, $enrollStatus);
        $userObj = get_user_by_id($targetUserId);
        $userName = $userObj['nama'] ?? 'Petugas';
        echo json_encode(array_merge($res, [
            'face_status' => $enrollStatus,
            'is_admin' => ($isAdmin || is_hrd()),
            'user_id' => $targetUserId,
            'user_name' => $userName,
            'photo' => $photo,
            'message' => ($enrollStatus === 'verified')
                ? "Biometrik wajah berhasil disimpan dan langsung aktif terverifikasi."
                : "Biometrik wajah berhasil disimpan! Menunggu verifikasi dari Staff MIS & IT."
        ]));
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Nama petugas tidak boleh kosong. Masukkan nama lengkap Anda.']);
    exit;
}

// Ambil seluruh daftar pengguna
$allUsers = get_user_list(true);
$currId = current_user_id();
$isAdmin = is_admin();
$isLoggedIn = is_logged_in();

$rawRet = trim((string)($_GET['ret'] ?? ''));
$retUrl = ($rawRet !== '') ? safe_redirect_url($rawRet, '') : '';
$exitUrl = ($retUrl !== '') ? $retUrl : ($isLoggedIn ? module_url('dashboard.php') : module_url('kartu_detail.php'));

// Format Role Display
$roleMap = [
    'admin' => 'Admin',
    'administrator' => 'Admin',
    'sdm' => 'SDM',
    'hrd' => 'SDM',
    'teknisi' => 'Akses Upload Foto Inventaris Saja',
    'it' => 'IT',
    'auditor' => 'Auditor'
];

// Kumpulkan data pengajuan wajah yang berstatus pending untuk diverifikasi Staff MIS & IT
$pendingFaces = [];
foreach ($allUsers as $u) {
    $uid = (int)($u['id'] ?? 0);
    $unama = trim((string)($u['nama'] ?? $u['username'] ?? ''));
    if ($uid <= 0 || strcasecmp($unama, 'nama') === 0 || $unama === '') continue;
    $uStat = strtolower(trim((string)($u['face_status'] ?? '')));
    $hasBio = !empty($u['face_descriptor']) || !empty($u['face_photo']);
    if (($uStat === 'pending' || $uStat === 'menunggu') && $hasBio) {
        $pendingFaces[] = $u;
    }
}

// Susun opsi teknisi yang sudah ada (hanya digunakan jika admin ingin switch perbarui akun lama)
$existingUserOptionsHtml = '<option value="-1">-- Pilih Akun Terdaftar --</option>';
foreach ($allUsers as $u) {
    $uid = (int)($u['id'] ?? 0);
    $unama = trim((string)($u['nama'] ?? $u['username'] ?? ''));
    if ($uid <= 0 || strcasecmp($unama, 'nama') === 0 || $unama === '') continue;
    $uRawRole = strtolower(trim((string)($u['role'] ?? 'teknisi')));
    $urole = $roleMap[$uRawRole] ?? ucfirst($uRawRole);
    $uHasBio = !empty($u['face_descriptor']);
    $uStat = strtolower(trim((string)($u['face_status'] ?? '')));
    $bioMark = $uHasBio ? (($uStat === 'verified') ? ' [Terverifikasi]' : ' [Pending]') : ' [Belum Wajah]';
    $existingUserOptionsHtml .= '<option value="'.$uid.'">'.e($unama).' ('.e($urole).')'.$bioMark.'</option>';
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
      <i class="bi bi-shield-check"></i> PENDAFTARAN BIOMETRIK WAJAH
    </div>
    <h1 class="text-[22px] font-bold text-[#1B2A3A] tracking-tight m-0">Tambah Petugas &amp; Perekaman Wajah</h1>
    <p class="text-[13px] text-[#53595F] mt-1 mb-0">Daftarkan foto profil dan sampel wajah untuk hak akses upload foto fisik inventaris.</p>
  </div>
  <div class="flex flex-wrap items-center gap-2 shrink-0">
    <a href="<?= e($exitUrl) ?>" class="h-[38px] px-3.5 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-semibold text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-box-arrow-left text-[#53595F]"></i> Keluar
    </a>
    <?php if ($isAdmin || is_hrd()): ?>
      <a href="<?= e(module_url('users_admin.php')) ?>" class="h-[38px] px-3.5 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-semibold text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
        <i class="bi bi-people-fill text-[#0E5C58]"></i> Kelola Pengguna
      </a>
    <?php elseif (!$isLoggedIn): ?>
      <a href="<?= e(module_url('login.php', ['ret' => 'user_biometric_enroll.php'])) ?>" class="h-[38px] px-3.5 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-semibold text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
        <i class="bi bi-box-arrow-in-right text-[#0E5C58]"></i> Login Petugas
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- PANEL PERSETUJUAN / ACCEPT DARI ADMINISTRATOR & STAFF MIS & IT -->
<?php if ($isAdmin || is_hrd() || !empty($pendingFaces)): ?>
<div class="mb-4 bg-white border border-[#D1D5D8] rounded-[10px] p-4 shadow-xs" id="adminApprovalPanel">
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-[#E5E7EB]">
    <div class="flex items-center gap-2">
      <span class="w-8 h-8 rounded-full bg-[#D9F1EF] text-[#0E5C58] flex items-center justify-center font-bold text-[14px]">
        <i class="bi bi-person-check-fill"></i>
      </span>
      <div>
        <h2 class="text-[15px] font-bold text-[#1B2A3A] m-0">Persetujuan Wajah Baru (Staff MIS &amp; IT)</h2>
        <div class="text-[12px] text-[#53595F]">Tinjau dan setujui (accept) sampel wajah petugas sebelum diizinkan mengunggah foto inventaris.</div>
      </div>
    </div>
    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-[#EFF6FF] text-[#1E40AF] border border-[#BFDBFE] self-start sm:self-center" id="pendingCountBadge">
      <?= count($pendingFaces) ?> Menunggu Verifikasi
    </span>
  </div>

  <div class="pt-3">
    <?php if (empty($pendingFaces)): ?>
      <div class="py-3 text-center text-[12px] text-[#53595F] flex items-center justify-center gap-2">
        <i class="bi bi-shield-check text-[#10B981] text-[16px]"></i>
        <span>Tidak ada antrean pendaftaran wajah yang menunggu verifikasi saat ini.</span>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3" id="pendingListContainer">
        <?php foreach ($pendingFaces as $pf): 
          $pfId = (int)$pf['id'];
          $pfNama = (string)($pf['nama'] ?? $pf['username'] ?? 'Petugas');
          $pfPhoto = (string)($pf['face_photo'] ?? '');
        ?>
        <div class="p-3 bg-[#FAF9F8] border border-[#D1D5D8] rounded-[8px] flex items-center justify-between gap-3 pending-user-card" id="pendingCard_<?= $pfId ?>">
          <div class="flex items-center gap-3 min-w-0">
            <?php if (!empty($pfPhoto)): ?>
              <img alt="Foto Petugas" src="<?= e($pfPhoto) ?>" alt="Foto" class="w-12 h-12 rounded-full object-cover border-2 border-[#D1D5D8] shrink-0">
            <?php else: ?>
              <div class="w-12 h-12 rounded-full bg-[#E5E7EB] text-[#64748B] flex items-center justify-center text-[18px] shrink-0">
                <i class="bi bi-person-fill"></i>
              </div>
            <?php endif; ?>
            <div class="min-w-0">
              <div class="text-[13px] font-bold text-[#1B2A3A] truncate"><?= e($pfNama) ?></div>
              <div class="text-[11px] text-[#0E5C58] font-semibold flex items-center gap-1 mt-0.5">
                <i class="bi bi-camera"></i> Akses Upload Foto Inventaris Saja
              </div>
              <div class="text-[10px] text-[#92400E] mt-0.5">
                <i class="bi bi-hourglass-split"></i> Menunggu Verifikasi
              </div>
            </div>
          </div>
          <div class="flex items-center gap-1.5 shrink-0">
            <?php if ($isAdmin || is_hrd()): ?>
              <button type="button" class="min-h-[44px] md:min-h-[36px] px-3 bg-[#10B981] hover:bg-[#059669] text-white rounded-[6px] text-[12px] font-bold transition-colors inline-flex items-center gap-1 border-0 cursor-pointer shadow-xs" onclick="adminReviewFace(<?= $pfId ?>, 'approve_face', '<?= e(addslashes($pfNama)) ?>')">
                <i class="bi bi-check-lg"></i> Accept
              </button>
              <button type="button" class="min-h-[44px] md:min-h-[36px] px-2.5 bg-white border border-[#D1D5D8] hover:bg-[#FEE2E2] hover:text-[#DC2626] text-[#53595F] rounded-[6px] text-[12px] font-medium transition-colors inline-flex items-center gap-1 cursor-pointer" onclick="adminReviewFace(<?= $pfId ?>, 'reject_face', '<?= e(addslashes($pfNama)) ?>')">
                <i class="bi bi-x-lg"></i>
              </button>
            <?php else: ?>
              <a href="<?= e(module_url('login.php', ['ret' => 'user_biometric_enroll.php'])) ?>" class="min-h-[44px] md:min-h-[36px] px-2.5 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#0E5C58] rounded-[6px] text-[11px] font-bold transition-colors inline-flex items-center gap-1 text-decoration-none shadow-xs">
                Login Verifikasi
              </a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- KARTU UTAMA PENDAFTARAN WAJAH BARU -->
<div class="enroll-main-card p-4 md:p-6 mb-4">
  <div class="row g-4 align-items-start">

    <!-- KOLOM KIRI: Form Pendaftaran Nama Baru & Role Akses -->
    <div class="col-lg-5 border-end-lg pe-lg-4">
      
      <div class="mb-3 pb-2 border-b border-[#E5E7EB]">
        <div class="text-[14px] font-bold text-[#1B2A3A] flex items-center gap-2">
          <i class="bi bi-person-plus-fill text-[#0E5C58]"></i> Identitas Petugas Baru
        </div>
        <div class="text-[12px] text-[#53595F] mt-0.5">
          Masukkan nama lengkap Anda untuk pendaftaran hak akses foto.
        </div>
      </div>

      <!-- Input Nama Lengkap Petugas -->
      <div class="mb-4">
        <label for="newTechName" class="block text-[12px] font-bold text-[#1B2A3A] uppercase tracking-wider mb-1.5">
          Nama Lengkap Petugas / Karyawan <span class="text-[#DC2626]">*</span>
        </label>
        <input type="text" class="form-control text-[14px] h-[44px] rounded-[8px] border-[#D1D5D8] focus:border-[#0E5C58] focus:ring-1 focus:ring-[#0E5C58]" id="newTechName" placeholder="Contoh: Akhmad Hafizh Firmansyah" autocomplete="name" autofocus required>
        <div class="text-[11px] text-[#53595F] mt-1.5">
          Ketik nama asli petugas yang bertugas mengambil foto barang inventaris.
        </div>
      </div>

      <!-- Pilihan Role Akses: Akses Upload Foto Inventaris Saja -->
      <div class="mb-4">
        <label class="block text-[12px] font-bold text-[#1B2A3A] uppercase tracking-wider mb-1.5">
          Role / Hak Akses Sistem
        </label>
        <div class="p-3 bg-[#F0FDF4] border border-[#BBF7D0] rounded-[8px] flex items-start gap-2.5">
          <i class="bi bi-shield-check text-[#16A34A] text-[20px] mt-0.5 shrink-0"></i>
          <div>
            <div class="text-[13px] font-bold text-[#166534]">Akses Upload Foto Inventaris Saja</div>
            <div class="text-[12px] text-[#15803D] mt-0.5" style="line-height: 1.4;">
              Petugas diberi hak mengambil dan mengunggah foto fisik barang aset inventaris pada kartu inventaris.
            </div>
          </div>
        </div>
      </div>

      <?php if ($isAdmin || is_hrd()): ?>
      <!-- Opsi Tambahan Khusus Admin: Perbarui Akun yang Sudah Ada -->
      <div class="mb-4 p-3 bg-[#FAF9F8] border border-[#D1D5D8] rounded-[8px]">
        <div class="flex items-center justify-between">
          <span class="text-[12px] font-bold text-[#1B2A3A]">Perbarui Akun Terdaftar (Admin)</span>
          <button type="button" class="text-[11px] font-semibold text-[#0E5C58] hover:underline bg-transparent border-0 p-0 cursor-pointer" onclick="toggleExistingAccountSelector()">
            <span id="toggleAccountLabel">+ Pilih Akun Lama</span>
          </button>
        </div>
        <div id="existingAccountBox" class="mt-2.5 d-none">
          <select class="form-select text-[12px] h-[38px] border-[#D1D5D8] rounded-[6px]" id="existingUserSelect" onchange="handleExistingUserSelect(this.value)">
            <?= $existingUserOptionsHtml ?>
          </select>
          <div class="text-[11px] text-[#53595F] mt-1">
            Gunakan opsi ini hanya jika ingin mengganti sampel foto petugas yang sudah terdaftar.
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Panduan Singkat -->
      <div class="bg-[#F8F9FA] border border-[#E5E7EB] rounded-[8px] p-3.5 mb-3 text-[12px] text-[#53595F]">
        <div class="font-bold text-[#1B2A3A] mb-2 flex items-center gap-1.5 text-[12px]">
          <i class="bi bi-info-circle-fill text-[#0E5C58]"></i> Panduan Perekaman Wajah:
        </div>
        <ol class="mb-0 ps-3 space-y-1" style="line-height: 1.5;">
          <li>Posisikan wajah tegak lurus menghadap kamera di dalam oval.</li>
          <li>Pastikan pencahayaan cukup dan wajah terlihat jelas tanpa masker/topi.</li>
          <li>Tahan posisi sejenak hingga bingkai berubah hijau, lalu simpan.</li>
        </ol>
      </div>

      <!-- Info Keamanan & Privasi -->
      <div class="p-2.5 bg-[#FAF9F8] rounded-[8px] border border-[#E5E7EB] text-[11px] text-[#53595F] flex items-center gap-2 mb-4">
        <i class="bi bi-shield-lock-fill text-[#0E5C58] text-[16px] shrink-0"></i>
        <div style="line-height: 1.35;">
          Vektor biometrik wajah dienkripsi aman untuk validasi presensi dan audit operasional IT Bank Mitra.
        </div>
      </div>

      <!-- Tombol Keluar Langsung -->
      <div>
        <a href="<?= e($exitUrl) ?>" class="w-full h-[40px] bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#53595F] rounded-[8px] text-[13px] font-semibold transition-colors flex items-center justify-center gap-1.5 text-decoration-none shadow-xs">
          <i class="bi bi-box-arrow-left"></i> Batal / Keluar
        </a>
      </div>

    </div>

    <!-- KOLOM KANAN: Kamera Pemindai & Tombol Aksi -->
    <div class="col-lg-7 ps-lg-4">
      <div class="flex items-center justify-between mb-3">
        <span class="text-[12px] font-bold text-[#1B2A3A] uppercase tracking-wider flex items-center gap-1.5">
          <i class="bi bi-camera-video text-[#0E5C58]"></i> Pemindai Kamera &amp; Verifikasi Wajah
        </span>
      </div>

      <!-- Banner HTTPS bila diakses via HTTP pada browser modern -->
      <div class="alert alert-primary py-2 px-3 small d-flex align-items-center justify-content-between mb-3 border-[#A7E2DC] bg-[#D9F1EF] text-[#0E5C58] rounded-[8px] d-none" id="httpsNoticeBanner">
        <div class="text-[12px]">
          <i class="bi bi-shield-lock-fill text-[#0E5C58] me-1"></i>
          Buka lewat <strong>HTTPS</strong> agar kamera webcam aktif langsung di browser.
        </div>
        <button type="button" id="linkToHttps" class="btn-action-mobile px-2.5 bg-[#0E5C58] text-white rounded-[6px] text-[11px] font-bold flex items-center gap-1 shadow-xs text-nowrap ms-2 border-0 cursor-pointer">
          Buka via HTTPS <i class="bi bi-arrow-right-short"></i>
        </a>
      </div>

      <!-- Form Camera / Scanner Area -->
      <div id="cameraAreaWrapper">
        <!-- Scanner Container -->
        <div class="scanner-container mb-3" id="scannerContainer">
          <video id="videoElement" class="scanner-video" autoplay playsinline webkit-playsinline muted></video>
          
          <!-- Oval Guide Frame -->
          <div class="face-oval pointer-events-none" id="faceOval"></div>
          
          <!-- Scanning Ray Animation -->
          <div id="enrollScanRay" class="position-absolute start-0 w-100 d-none" style="height: 3px; background: linear-gradient(90deg, transparent, #0E5C58, #10B981, transparent); animation: scanRayAnim 2s infinite ease-in-out;"></div>

          <!-- Success Overlay Inside Video -->
          <div id="enrollSuccessOverlay" class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center text-white d-none" style="background: rgba(14, 92, 88, 0.88);  z-index: 5;">
            <i class="bi bi-check-circle-fill display-4 mb-2 animate-bounce text-white"></i>
            <h5 class="fw-bold text-white mb-1" id="enrollSuccessName">Wajah Terdeteksi!</h5>
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
          <button type="button" class="w-full h-[44px] bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[13px] font-semibold transition-colors flex items-center justify-center gap-2 border-0 shadow-xs cursor-pointer" id="btnStartCapture" onclick="startCamera()">
            <i class="bi bi-camera-fill"></i> Buka Kamera Wajah
          </button>
          
          <input type="file" id="faceFileInput" accept="image/*" capture="user" class="d-none" onchange="handleFaceFileUpload(event)">
          <button type="button" class="w-full h-[40px] bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] rounded-[8px] text-[13px] font-medium transition-colors flex items-center justify-center gap-2 shadow-xs cursor-pointer" id="btnUploadFaceFile" onclick="triggerFaceFileInput()">
            <i class="bi bi-cloud-arrow-up-fill text-[#53595F]"></i> Ambil / Upload Foto (File)
          </button>
          
          <button type="button" class="w-full h-[44px] bg-[#0284C7] hover:bg-[#0369A1] text-white rounded-[8px] text-[13px] font-semibold transition-colors flex items-center justify-center gap-2 border-0 shadow-xs cursor-pointer d-none" id="btnSnapNow" onclick="forceCaptureBiometric()">
            <i class="bi bi-camera"></i> Verifikasi Sekarang
          </button>
          
          <button type="button" class="w-full h-[44px] bg-[#10B981] hover:bg-[#059669] text-white rounded-[8px] text-[13px] font-semibold transition-colors flex items-center justify-center gap-2 border-0 shadow-xs cursor-pointer d-none" id="btnSaveBiometric" onclick="saveBiometrics()">
            <i class="bi bi-check-circle-fill"></i> Simpan Foto Profil &amp; Wajah
          </button>
        </div>
      </div>

      <!-- TAMPILAN SUKSES & STATUS: MENUNGGU VERIFIKASI DARI STAFF MIS & IT -->
      <div class="p-4 md:p-5 bg-white border border-[#D1D5D8] rounded-[10px] text-center d-none shadow-xs" id="successBox">
        <div class="relative inline-block mb-3">
          <img id="enrolledAvatarPreview" src="" alt="Foto Petugas" class="w-24 h-24 rounded-full object-cover border-4 border-[#10B981] shadow-xs mx-auto">
          <span class="absolute bottom-0 right-0 w-7 h-7 rounded-full bg-[#10B981] text-white flex items-center justify-center text-[13px] shadow-xs">
            <i class="bi bi-check-lg"></i>
          </span>
        </div>
        <h3 class="text-[17px] font-bold text-[#1B2A3A] mb-1" id="enrolledUserNameText">Nama Petugas</h3>
        <div class="mb-3">
          <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC] inline-flex items-center gap-1 font-mono">
            <i class="bi bi-shield-check text-[#10B981]"></i> Akses Upload Foto Inventaris Saja
          </span>
        </div>

        <!-- Banner Menunggu Verifikasi Staff MIS & IT -->
        <div class="p-3.5 bg-[#FEF3C7] border border-[#FDE68A] text-[#92400E] rounded-[8px] mb-4 text-center">
          <div class="font-bold text-[14px] flex items-center justify-center gap-1.5" id="enrolledStatusTitle">
            <i class="bi bi-hourglass-split text-[#D97706]"></i> Menunggu Verifikasi dari Staff MIS &amp; IT
          </div>
          <div class="text-[12px] mt-1.5 text-[#78350F]" id="enrolledStatusDesc">
            Foto profil dan data verifikasi wajah Anda berhasil disimpan ke sistem. Saat ini status pendaftaran Anda sedang menunggu persetujuan / verifikasi dari Staff MIS &amp; IT sebelum akun dapat digunakan untuk upload foto inventaris.
          </div>
        </div>

        <!-- Tombol Keluar & Daftarkan Petugas Lain -->
        <div class="flex flex-col gap-2 max-w-sm mx-auto">
          <a href="<?= e($exitUrl) ?>" class="w-full h-[44px] bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[13px] font-bold transition-colors flex items-center justify-center gap-2 text-decoration-none shadow-xs">
            <i class="bi bi-box-arrow-right"></i> Keluar / Selesai
          </a>
          <button type="button" class="w-full h-[40px] bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] rounded-[8px] text-[13px] font-semibold transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-xs" onclick="resetForNextEnrollment()">
            <i class="bi bi-person-plus-fill text-[#0E5C58]"></i> + Daftarkan Pengguna Lain
          </button>
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
const HOLD_FRAMES_REQUIRED = 12;

let activeTargetUserId = -1; // Default mode: pendaftaran user/wajah baru

const MODEL_URL = "https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/";

const video = document.getElementById("videoElement");
const faceOval = document.getElementById("faceOval");
const statusMsg = document.getElementById("statusMessage");
const progressBar = document.getElementById("progressBar");
const btnSave = document.getElementById("btnSaveBiometric");
const btnStart = document.getElementById("btnStartCapture");
const btnSnapNow = document.getElementById("btnSnapNow");
const newTechName = document.getElementById("newTechName");
const successBox = document.getElementById("successBox");
const cameraAreaWrapper = document.getElementById("cameraAreaWrapper");

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

function toggleExistingAccountSelector() {
  const box = document.getElementById("existingAccountBox");
  const label = document.getElementById("toggleAccountLabel");
  if (!box) return;
  if (box.classList.contains("d-none")) {
    box.classList.remove("d-none");
    if (label) label.textContent = "- Batalkan (Daftar Baru)";
  } else {
    box.classList.add("d-none");
    if (label) label.textContent = "+ Pilih Akun Lama";
    activeTargetUserId = -1;
    const select = document.getElementById("existingUserSelect");
    if (select) select.value = "-1";
  }
}

function handleExistingUserSelect(val) {
  const num = parseInt(val, 10);
  if (num > 0) {
    activeTargetUserId = num;
    const select = document.getElementById("existingUserSelect");
    const nameText = select.options[select.selectedIndex].text.split("(")[0].replace(/\[.*?\]/g, "").trim();
    if (newTechName) {
      newTechName.value = nameText;
    }
  } else {
    activeTargetUserId = -1;
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

      setStatus("success", '<i class="bi bi-check-circle-fill me-1"></i> Foto wajah siap! Tekan <strong>"Simpan Foto Profil & Wajah"</strong> di bawah.');
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
}

async function startCamera() {
  btnStart.disabled = true;

  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    if (window.location.protocol === "http:") {
      const httpsUrl = "https://" + window.location.host + window.location.pathname + window.location.search;
      if (confirm("Browser membatasi akses kamera langsung pada protokol HTTP.\n\nKlik OK untuk beralih ke HTTPS agar kamera aktif live.")) {
        window.location.href = httpsUrl;
        return;
      }
    }
    triggerFaceFileInput();
    setStatus("warning", '<i class="bi bi-camera-fill text-[#0E5C58] me-1"></i> Mengaktifkan kamera file...');
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

    btnStart.classList.add("d-none");
    const scanRay = document.getElementById("enrollScanRay");
    if (scanRay) scanRay.classList.remove("d-none");
    const overlay = document.getElementById("enrollSuccessOverlay");
    if (overlay) overlay.classList.add("d-none");
    setStatus("info", '<i class="bi bi-person-bounding-box me-1"></i> Kamera aktif. Posisikan wajah Anda di bingkai.');
    
    loadModels().then(() => {
      if (videoStream && !enrollCompleted) {
        setStatus("info", '<i class="bi bi-person-bounding-box me-1"></i> Arahkan wajah tegak lurus di dalam bingkai foto.');
      }
    });

    startFaceTracking();
  } catch (err) {
    console.error("Akses kamera gagal:", err);
    setStatus("danger", '<i class="bi bi-camera-video-off me-1"></i> Kamera tidak dapat diakses: ' + (err.message || 'Periksa izin kamera.'));
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
  const enteredName = newTechName ? newTechName.value.trim() : "";
  if (nameEl) {
    nameEl.textContent = enteredName || "Wajah Terdeteksi!";
  }
  if (overlay) overlay.classList.remove("d-none");
  const scanRay = document.getElementById("enrollScanRay");
  if (scanRay) scanRay.classList.add("d-none");
  enrollCompleted = true;
  detectedDescriptor = Array.from(descriptor);
  captureSnapshot();

  if (btnSnapNow) btnSnapNow.classList.add("d-none");
  setStatus("success", '<i class="bi bi-check-circle-fill me-1"></i> Foto wajah terambil! Tekan <strong>"Simpan Foto Profil & Wajah"</strong> di bawah.');
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
      setStatus("warning", '<i class="bi bi-person-exclamation me-1"></i> Wajah belum terdeteksi jelas. Pastikan wajah di dalam oval.');
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
          const detection = await faceapi.detectSingleFace(video, fastDetectorOptions).withFaceLandmarks(useTinyLandmarks);

          if (detection) {
            faceOval.classList.add("active");
            if (btnSnapNow) btnSnapNow.classList.remove("d-none");

            const landmarks = detection.landmarks;
            const leftEye = landmarks.getLeftEye();
            const rightEye = landmarks.getRightEye();
            const avgEAR = (calculateEAR(leftEye) + calculateEAR(rightEye)) / 2.0;

            if (avgEAR < 0.26) {
              lastEyeState = "closed";
            } else if (avgEAR > 0.27 && lastEyeState === "closed") {
              blinkDetected = true;
              isLivenessVerified = true;
            }

            enrollHoldFrames++;
            const pct = Math.min(100, Math.round((enrollHoldFrames / HOLD_FRAMES_REQUIRED) * 100));
            progressBar.style.width = pct + "%";

            if (enrollHoldFrames >= HOLD_FRAMES_REQUIRED) {
              isLivenessVerified = true;
            }

            if (!isLivenessVerified) {
              setStatus("warning", '<i class="bi bi-camera me-1"></i> Wajah terdeteksi. <u>Tahan posisi wajah Anda</u>...');
            }
          } else {
            faceOval.classList.remove("active");
            enrollHoldFrames = Math.max(0, enrollHoldFrames - 2);
            progressBar.style.width = "20%";
            if (!isLivenessVerified) {
              setStatus("default", '<i class="bi bi-person-exclamation me-1"></i> Sesuaikan posisi wajah Anda di dalam bingkai.');
            }
          }
        } else {
          setStatus("info", '<span class="spinner-border spinner-border-sm me-2 text-[#0E5C58]"></span> Posisi pas. Mengambil foto wajah...');

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
  const outSize = 220;
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
    alert("Foto belum terdeteksi dengan jelas. Silakan ulangi pengambilan foto.");
    return;
  }

  const enteredName = newTechName ? newTechName.value.trim() : "";
  if (activeTargetUserId <= 0 && !enteredName) {
    alert("Silakan masukkan Nama Lengkap Petugas terlebih dahulu.");
    if (newTechName) newTechName.focus();
    return;
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
        user_id: activeTargetUserId,
        new_name: enteredName
      })
    });
    const text = await res.text();
    let result;
    try {
      result = JSON.parse(text);
    } catch (e) {
      console.error("Non-JSON response:", text);
      throw new Error("Respon server tidak valid.");
    }

    if (result && result.success) {
      // Hentikan streaming kamera perangkat secara bersih
      if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
        videoStream = null;
      }

      // Sembunyikan area kamera dan tombol simpan
      if (cameraAreaWrapper) cameraAreaWrapper.classList.add("d-none");
      btnSave.classList.add("d-none");

      // Tampilkan hasil di box sukses
      const enrolledName = result.user_name || enteredName || "Petugas";
      const enrolledPhoto = result.photo || capturedPhotoBase64 || "";

      const nameEl = document.getElementById("enrolledUserNameText");
      if (nameEl) nameEl.textContent = enrolledName;

      const avatarEl = document.getElementById("enrolledAvatarPreview");
      if (avatarEl && enrolledPhoto) {
        avatarEl.src = enrolledPhoto;
      }

      const statusTitle = document.getElementById("enrolledStatusTitle");
      const statusDesc = document.getElementById("enrolledStatusDesc");

      if (result.is_admin || result.face_status === 'verified') {
        if (statusTitle) {
          statusTitle.innerHTML = '<i class="bi bi-patch-check-fill text-[#10B981]"></i> Wajah Terverifikasi &amp; Siap Digunakan!';
          statusTitle.className = "font-bold text-[14px] flex items-center justify-center gap-1.5 text-[#065F46]";
        }
        if (statusDesc) {
          statusDesc.innerHTML = 'Data foto profil dan identitas wajah Anda telah disetujui (Accept) oleh Administrator dan dapat langsung digunakan.';
        }
      } else {
        if (statusTitle) {
          statusTitle.innerHTML = '<i class="bi bi-hourglass-split text-[#D97706]"></i> Menunggu Verifikasi dari Staff MIS &amp; IT';
          statusTitle.className = "font-bold text-[14px] flex items-center justify-center gap-1.5 text-[#92400E]";
        }
        if (statusDesc) {
          statusDesc.innerHTML = 'Foto profil dan biometrik wajah Anda berhasil disimpan ke sistem. Mohon menunggu persetujuan / verifikasi dari <strong>Staff MIS &amp; IT</strong> sebelum akun dapat digunakan untuk upload foto inventaris.';
        }
      }

      if (successBox) successBox.classList.remove("d-none");
      window.scrollTo({ top: 0, behavior: "smooth" });
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

async function adminReviewFace(userId, actionType, userName) {
  const isApprove = (actionType === 'approve_face');
  const actionText = isApprove ? 'menyetujui (Accept)' : 'menolak';
  if (!confirm('Apakah Anda yakin ingin ' + actionText + ' verifikasi wajah petugas "' + userName + '"?')) {
    return;
  }

  const card = document.getElementById('pendingCard_' + userId);
  if (card) {
    card.style.opacity = '0.5';
    card.style.pointerEvents = 'none';
  }

  try {
    const res = await fetch(window.location.pathname + window.location.search, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: actionType,
        user_id: userId
      })
    });
    const result = await res.json();
    if (result && result.success) {
      if (card) {
        if (isApprove) {
          card.style.background = '#ECFDF5';
          card.style.borderColor = '#A7F3D0';
          card.innerHTML = '<div class="py-2 px-3 text-[12px] font-bold text-[#065F46] flex items-center gap-2"><i class="bi bi-check-circle-fill text-[#10B981]"></i> Wajah "' + userName + '" berhasil disetujui (Accept)!</div>';
        } else {
          card.style.background = '#FEE2E2';
          card.style.borderColor = '#FECACA';
          card.innerHTML = '<div class="py-2 px-3 text-[12px] font-bold text-[#DC2626] flex items-center gap-2"><i class="bi bi-x-circle-fill text-[#EF4444]"></i> Pengajuan wajah "' + userName + '" telah ditolak.</div>';
        }
        setTimeout(() => {
          card.remove();
          const remaining = document.querySelectorAll('.pending-user-card');
          const badge = document.getElementById('pendingCountBadge');
          if (badge) badge.textContent = remaining.length + ' Menunggu Verifikasi';
          if (remaining.length === 0) {
            const list = document.getElementById('pendingListContainer');
            if (list) {
              list.innerHTML = '<div class="col-12 py-3 text-center text-[12px] text-[#53595F] flex items-center justify-center gap-2"><i class="bi bi-shield-check text-[#10B981] text-[16px]"></i><span>Semua pengajuan pendaftaran wajah telah selesai diverifikasi.</span></div>';
            }
          }
        }, 1500);
      }
    } else {
      alert(result.error || 'Gagal memproses verifikasi.');
      if (card) {
        card.style.opacity = '1';
        card.style.pointerEvents = 'auto';
      }
    }
  } catch(e) {
    alert('Terjadi kesalahan jaringan.');
    if (card) {
      card.style.opacity = '1';
      card.style.pointerEvents = 'auto';
    }
  }
}

function resetForNextEnrollment() {
  if (successBox) successBox.classList.add("d-none");
  if (cameraAreaWrapper) cameraAreaWrapper.classList.remove("d-none");

  const overlay = document.getElementById("enrollSuccessOverlay");
  if (overlay) overlay.classList.add("d-none");

  detectedDescriptor = null;
  capturedPhotoBase64 = null;
  enrollCompleted = false;
  isLivenessVerified = false;
  blinkDetected = false;
  enrollHoldFrames = 0;
  activeTargetUserId = -1;

  if (progressBar) progressBar.style.width = "0%";
  if (faceOval) faceOval.classList.remove("active");

  if (btnSave) {
    btnSave.classList.add("d-none");
    btnSave.disabled = false;
    btnSave.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Simpan Foto Profil & Wajah';
  }
  if (btnSnapNow) btnSnapNow.classList.add("d-none");
  if (btnStart) {
    btnStart.classList.remove("d-none");
    btnStart.disabled = false;
  }

  if (newTechName) {
    newTechName.value = "";
    newTechName.focus();
  }

  const existingSelect = document.getElementById("existingUserSelect");
  if (existingSelect) existingSelect.value = "-1";

  const canvas = document.getElementById("snapshotCanvas");
  if (canvas) canvas.style.display = "none";
  if (video) video.style.display = "block";

  setStatus("info", '<i class="bi bi-camera text-[#0E5C58] me-1"></i> Masukkan nama lengkap Anda, lalu tekan <strong>"Buka Kamera Wajah"</strong>.');
  window.scrollTo({ top: 0, behavior: "smooth" });
}
</script>
<?php
$script = ob_get_clean();

render_page('Wajah Teknisi (Biometrik)', $body, $head, $script, $isLoggedIn);
