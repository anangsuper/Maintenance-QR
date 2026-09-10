<?php
require __DIR__ . '/bootstrap.php';

// Ambil seluruh daftar pengguna / teknisi untuk dipilih di HP (selalu fresh)
$allUsers = get_user_list(true);
$currId = current_user_id();
$reqId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$retUrl = trim((string)($_GET['ret'] ?? ''));

// Tentukan user aktif
$selectedUserId = 0;
if ($reqId > 0) {
    $selectedUserId = $reqId;
} elseif ($currId > 0) {
    $selectedUserId = $currId;
} elseif (!empty($allUsers)) {
    $selectedUserId = (int)($allUsers[0]['id'] ?? 0);
}

// Handle AJAX POST simpan biometrik atau tambah teknisi baru langsung dari HP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $action = trim((string)($data['action'] ?? $_GET['action'] ?? ''));
    $newTechName = trim((string)($data['new_name'] ?? ''));
    $targetUserId = (int)($data['user_id'] ?? $selectedUserId);
    $descriptor = trim((string)($data['descriptor'] ?? ''));
    $photo = trim((string)($data['photo'] ?? ''));

    // Aksi 1: Simpan Nama Teknisi Baru Langsung (Tanpa perlu wajah / biometrik)
    if ($action === 'add_tech_only' || ($newTechName !== '' && empty($descriptor))) {
        header('Content-Type: application/json');
        if ($newTechName === '') {
            echo json_encode(['success' => false, 'error' => 'Nama teknisi baru tidak boleh kosong.']);
            exit;
        }
        $created = create_new_user([
            'nama' => $newTechName,
            'role' => 'teknisi',
            'status' => 'Aktif'
        ]);
        if (!empty($created['success'])) {
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => (int)$created['id'],
                    'nama' => $created['nama'] ?? $newTechName,
                    'username' => $created['username'] ?? ''
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
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Data vektor biometrik tidak boleh kosong.']);
        exit;
    }

    // Jika teknisi mendaftar dengan nama baru secara mandiri
    if ($targetUserId === -1 && $newTechName !== '') {
        $created = create_new_user([
            'nama' => $newTechName,
            'role' => 'teknisi',
            'status' => 'Aktif'
        ]);
        if (!empty($created['id'])) {
            $targetUserId = (int)$created['id'];
        } elseif (!empty($created['success'])) {
            $list = get_user_list(true);
            foreach ($list as $lu) {
                if (strcasecmp($lu['nama'], $newTechName) === 0) {
                    $targetUserId = (int)$lu['id'];
                    break;
                }
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $created['error'] ?? 'Gagal mendaftarkan nama teknisi baru']);
            exit;
        }
    }

    if ($targetUserId <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Pilih nama teknisi yang valid']);
        exit;
    }

    // WAJIB: Seluruh pendaftaran biometrik berstatus 'pending' dan memerlukan approval dari Admin sebelum aktif
    $enrollStatus = 'pending';
    $res = save_user_biometrics($targetUserId, $descriptor, $photo, $enrollStatus);
    header('Content-Type: application/json');
    echo json_encode(array_merge($res, [
        'face_status' => 'pending',
        'is_admin' => is_admin(),
        'user_id' => $targetUserId
    ]));
    exit;
}

$user = get_user_by_id($selectedUserId, true);
if (!$user && !empty($allUsers)) {
    $user = $allUsers[0];
    $selectedUserId = (int)$user['id'];
}

$hasBiometrics = !empty($user['face_descriptor']);
$existingPhoto = $user['face_photo'] ?? '';
$userFaceStatus = strtolower(trim((string)($user['face_status'] ?? '')));

if ($userFaceStatus === 'verified' || $userFaceStatus === 'terverifikasi') {
    $badgeStatus = '<span class="badge bg-success bg-opacity-15 text-success px-3 py-2 border border-success border-opacity-25"><i class="bi bi-shield-check me-1"></i> Wajah Terverifikasi Admin</span>';
} elseif ($userFaceStatus === 'pending' || $userFaceStatus === 'menunggu') {
    $badgeStatus = '<span class="badge bg-warning text-dark px-3 py-2 border border-warning shadow-sm"><i class="bi bi-hourglass-split me-1"></i> Menunggu Persetujuan Admin</span>';
} elseif ($userFaceStatus === 'rejected') {
    $badgeStatus = '<span class="badge bg-danger bg-opacity-15 text-danger px-3 py-2 border border-danger"><i class="bi bi-x-circle me-1"></i> Ditolak Admin (Daftar Ulang)</span>';
} else {
    $badgeStatus = '<span class="badge bg-secondary bg-opacity-15 text-secondary px-3 py-2 border border-secondary border-opacity-25"><i class="bi bi-exclamation-circle me-1"></i> Belum Didaftarkan</span>';
}

$avatarHtml = '';
if ($existingPhoto) {
    $avatarHtml = '
    <div class="text-center mb-3">
      <div class="d-inline-block position-relative">
        <img src="'.e($existingPhoto).'" alt="Foto Biometrik" class="rounded-circle border border-3 border-success shadow-sm" style="width: 84px; height: 84px; object-fit: cover;">
        <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-success" title="Terverifikasi"><i class="bi bi-check-lg"></i></span>
      </div>
      <div class="small text-muted mt-1" style="font-size: 0.75rem;">Sampel Wajah Terdaftar Sebelumnya</div>
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
$userOptionsHtml .= '<option value="-1">+ Tambah Nama Teknisi Baru (Ketik Sendiri)</option>';

$backHref = $retUrl !== '' ? $retUrl : (is_logged_in() ? module_url('users_admin.php') : module_url('dashboard.php'));

$head = '
<style>
  .mobile-enroll-card {
    border-radius: 24px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
  }
  .scanner-container {
    position: relative;
    width: 320px;
    height: 380px;
    max-width: 100%;
    margin: 0 auto;
    background: #0f172a;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 12px 28px rgba(0,0,0,0.25);
  }
  .scanner-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: scaleX(-1); /* Mirror view untuk kamera depan HP */
  }
  .scanner-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .face-oval {
    width: 190px;
    height: 240px;
    border: 3px dashed #38bdf8;
    border-radius: 50%;
    box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.65);
    transition: all 0.3s ease;
  }
  .face-oval.active {
    border-color: #22c55e;
    border-style: solid;
    box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.4), 0 0 25px rgba(34, 197, 94, 0.6);
  }
  .scanline {
    position: absolute;
    top: 25%;
    left: calc(50% - 95px);
    width: 190px;
    height: 3px;
    background: linear-gradient(90deg, transparent, #38bdf8, transparent);
    animation: scanMove 2s infinite ease-in-out;
  }
  @keyframes scanMove {
    0% { top: 20%; opacity: 0; }
    50% { opacity: 1; }
    100% { top: 80%; opacity: 0; }
  }
</style>';

$body = '
<div class="row justify-content-center">
  <div class="col-md-8 col-lg-6 col-xl-5">
    
    <!-- Top Bar Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-2 py-1 mb-1">
          <i class="bi bi-phone-fill me-1"></i> Pendaftaran Mandiri via HP
        </span>
        <h4 class="fw-bold mb-0 text-dark">Daftar Wajah Teknisi</h4>
      </div>
      <a class="btn btn-outline-secondary btn-sm rounded-pill px-3" href="'.e($backHref).'">
        <i class="bi bi-arrow-left"></i> Kembali
      </a>
    </div>

    <!-- Card Utama Pendaftaran HP -->
    <div class="card p-3 p-sm-4 border-0 mobile-enroll-card bg-white mb-4">
      
      <!-- 1. Pemilihan Teknisi -->
      <div class="mb-3">
        <label class="form-label small fw-bold text-dark d-flex justify-content-between align-items-center mb-1">
          <span><i class="bi bi-person-circle text-primary me-1"></i> Pilih Nama Teknisi Anda:</span>
          '.$badgeStatus.'
        </label>
        <select class="form-select form-select-lg fw-bold shadow-sm" id="userSelect" onchange="handleUserChange(this.value)">
          '.$userOptionsHtml.'
        </select>
      </div>

      <!-- Field Tambah Nama Teknisi Baru (Tampil jika pilih + Tambah) -->
      <div class="card border border-success border-opacity-50 p-3 bg-success bg-opacity-10 rounded-4 mb-3 d-none" id="newTechBox">
        <label class="form-label small fw-bold text-success mb-1">
          <i class="bi bi-person-plus-fill me-1"></i> Masukkan Nama Lengkap Teknisi Baru:
        </label>
        <div class="input-group mb-2">
          <input type="text" class="form-control form-control-lg fw-semibold" id="newTechName" placeholder="Contoh: Budi Santoso" autocomplete="off">
          <button type="button" class="btn btn-success fw-bold px-3 shadow-sm" id="btnQuickSaveTech" onclick="saveNewTechOnly()">
            <i class="bi bi-check-lg me-1"></i> Simpan Nama
          </button>
        </div>
        <div class="form-text text-muted mb-1" style="font-size: 0.75rem;">
          Klik <strong>"Simpan Nama"</strong> untuk langsung mendaftarkan akun teknisi tanpa harus scan wajah.
        </div>
        <div id="newTechStatus" class="small"></div>
      </div>

      '.$avatarHtml.'

      <!-- Native Apple Face ID Passkey Section -->
      <div class="card p-3 border-0 bg-primary bg-opacity-10 rounded-4 mb-3" id="nativeFaceIdBox" style="display:none;">
        <div class="d-flex align-items-center gap-2 mb-2">
          <div class="p-2 bg-primary text-white rounded-circle fs-5 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
            <i class="bi bi-apple"></i>
          </div>
          <div>
            <div class="fw-bold text-primary">Face ID Bawaan iPhone (Rekomendasi)</div>
            <div class="small text-muted" style="font-size: 0.72rem;">Verifikasi instan via sensor TrueDepth Apple (< 0.5 detik)</div>
          </div>
        </div>
        <p class="small text-secondary mb-2">
          Daftarkan Face ID iPhone Anda sekarang agar dapat login dan verifikasi maintenance secara otomatis tanpa perlu membuka kamera web.
        </p>
        <button type="button" class="btn btn-primary fw-bold py-2 rounded-3 shadow-sm w-100" id="btnEnrollFaceId" onclick="enrollNativeFaceId()">
          <i class="bi bi-person-bounding-box me-1"></i> Daftarkan Face ID iPhone Ini
        </button>
        <div id="nativeFaceIdStatus" class="small mt-2"></div>
      </div>

      <!-- Panduan Singkat Smartphone -->
      <div class="alert alert-light border border-primary border-opacity-25 rounded-3 p-3 mb-3 small">
        <div class="fw-bold text-primary mb-1 d-flex align-items-center gap-1">
          <i class="bi bi-lightning-charge-fill text-warning"></i> Cara Scan Wajah via HP:
        </div>
        <ol class="mb-2 ps-3 text-secondary">
          <li>Klik tombol <strong>"Aktifkan Kamera Depan HP"</strong> di bawah.</li>
          <li>Pegang HP tegak lurus mengarah ke wajah Anda.</li>
          <li>Arahkan wajah ke lingkaran oval hingga garis berubah <strong class="text-success">HIJAU</strong>.</li>
          <li><strong>Kedipkan mata Anda 1 kali</strong> (uji keaslian / liveness check).</li>
          <li>Tekan <strong>"SIMPAN BIOMETRIK WAJAH"</strong>.</li>
        </ol>
        <div class="alert alert-warning border border-warning rounded-2 p-2 mb-0 mt-2 small text-dark d-flex align-items-start gap-2">
          <i class="bi bi-shield-lock-fill text-warning fs-5 flex-shrink-0"></i>
          <div>
            <strong>Penting: Verifikasi Admin Diperlukan</strong><br>
            Setelah wajah didaftarkan, Administrator IT akan memeriksa foto dan menyetujuinya (Approve) di panel Admin Pengguna. Wajah baru dapat digunakan untuk verifikasi checklist setelah disetujui.
          </div>
        </div>
      </div>

      <!-- Scanner Container (Mobile Optimized) -->
      <div class="scanner-container mb-3" id="scannerContainer">
        <video id="videoElement" class="scanner-video" autoplay playsinline webkit-playsinline muted></video>
        <div class="scanner-overlay">
          <div class="face-oval" id="faceOval"></div>
          <div class="scanline d-none" id="scanLine"></div>
        </div>
        <canvas id="snapshotCanvas" style="display:none;" width="320" height="320"></canvas>
      </div>

      <!-- Status Bar Feedback Realtime -->
      <div class="alert alert-secondary py-2 px-3 text-center mb-3 fw-semibold small" id="statusMessage">
        <span class="spinner-border spinner-border-sm me-2 text-primary"></span>
        Menyiapkan modul AI pengenalan wajah...
      </div>

      <!-- Progress Liveness -->
      <div class="progress mb-3" style="height: 6px;" id="progressBarContainer">
        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="progressBar" style="width: 20%;"></div>
      </div>

      <!-- Tombol Aksi HP -->
      <div class="d-grid gap-2">
        <button type="button" class="btn btn-primary btn-lg fw-bold py-3 shadow" id="btnStartCapture" onclick="startCamera()">
          <i class="bi bi-camera-video-fill me-2"></i> AKTIFKAN KAMERA DEPAN HP
        </button>
        <button type="button" class="btn btn-warning text-dark btn-lg fw-bold py-3 shadow d-none" id="btnSnapNow" onclick="forceCaptureBiometric()">
          <i class="bi bi-camera-fill me-2"></i> AMBIL SAMPEL WAJAH SEKARANG
        </button>
        <button type="button" class="btn btn-success btn-lg fw-bold py-3 shadow d-none" id="btnSaveBiometric" onclick="saveBiometrics()">
          <i class="bi bi-check-circle-fill me-2"></i> SIMPAN BIOMETRIK WAJAH SAYA
        </button>
      </div>

      <!-- Success Action Box (Tampil setelah berhasil) -->
      <div class="mt-3 p-3 bg-success bg-opacity-10 border border-success rounded-3 text-center d-none" id="successBox">
        <h5 class="fw-bold text-success mb-1"><i class="bi bi-check-circle-fill me-1"></i> Wajah Berhasil Diunggah!</h5>
        <div class="small text-dark mb-3" id="successDesc">
          Wajah teknisi berhasil direkam dengan status: <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i> Menunggu Persetujuan Admin</span>.<br><br>
          <strong>Wajib Disetujui Admin:</strong> Administrator IT harus memeriksa & menyetujui (Approve) biometrik wajah ini di menu <em>Kelola Data &rarr; Akun Pengguna / Teknisi</em> sebelum dapat digunakan untuk checklist maintenance.<br><br>
          <div class="alert alert-info py-2 px-3 small text-start mb-0 border-0 bg-info bg-opacity-10">
            <strong><i class="bi bi-info-circle-fill me-1 text-primary"></i>Catatan Penting:</strong> Pendaftaran wajah ini adalah untuk identitas akun teknisi Anda. Checklist pemeliharaan komputer belum disimpan. Silakan klik tombol <strong>"Kembali Lanjutkan Maintenance"</strong> di bawah untuk menyimpan checklist maintenance komputer ini.
          </div>
        </div>
        <div class="d-grid gap-2">
          '.($retUrl !== '' ? '<a href="'.e($retUrl).'" class="btn btn-success fw-bold"><i class="bi bi-arrow-return-left me-1"></i> Kembali Lanjutkan Maintenance</a>' : '<a href="'.e(module_url('dashboard.php')).'" class="btn btn-primary fw-bold"><i class="bi bi-qr-code-scan me-1"></i> Buka Dashboard QR</a>').'
          <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload()">Daftarkan Teknisi Lain</button>
        </div>
      </div>

      <div class="text-center mt-3">
        <small class="text-muted" style="font-size: 0.72rem;">
          <i class="bi bi-shield-lock me-1"></i> Enkripsi Vektor Biometrik AI Sesuai UU PDP No. 27/2022
        </small>
      </div>

    </div>
  </div>
</div>';

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
const scanLine = document.getElementById("scanLine");
const statusMsg = document.getElementById("statusMessage");
const progressBar = document.getElementById("progressBar");
const btnSave = document.getElementById("btnSaveBiometric");
const btnStart = document.getElementById("btnStartCapture");
const btnSnapNow = document.getElementById("btnSnapNow");
const userSelect = document.getElementById("userSelect");
const newTechBox = document.getElementById("newTechBox");
const newTechName = document.getElementById("newTechName");
const successBox = document.getElementById("successBox");

function handleUserChange(val) {
  if (val === "-1") {
    newTechBox.classList.remove("d-none");
    newTechName.focus();
  } else {
    newTechBox.classList.add("d-none");
    const currentParam = new URLSearchParams(window.location.search);
    currentParam.set("id", val);
    window.location.search = currentParam.toString();
  }
}

async function loadModels() {
  if (modelsLoaded) return true;
  if (modelsLoading) return true;
  modelsLoading = true;
  try {
    if (statusMsg && !videoStream) {
      statusMsg.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-primary"></span> Menyiapkan modul AI GPU...';
      progressBar.style.width = "40%";
    }
    
    if (typeof faceapi !== "undefined" && faceapi.tf) {
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
    if (statusMsg && !videoStream) {
      statusMsg.className = "alert alert-success py-2 px-3 text-center mb-3 small fw-semibold";
      statusMsg.innerHTML = '<i class="bi bi-check-circle me-1"></i> Modul AI GPU siap. Silakan klik "AKTIFKAN KAMERA DEPAN HP".';
    }
    return true;
  } catch (err) {
    console.error("Gagal memuat model:", err);
    modelsLoading = false;
    if (statusMsg) {
      statusMsg.className = "alert alert-danger py-2 px-3 text-center mb-3 small";
      statusMsg.innerHTML = '<i class="bi bi-x-circle me-1"></i> Gagal memuat modul AI. Pastikan smartphone terhubung internet.';
    }
    return false;
  }
}

async function startCamera() {
  btnStart.disabled = true;
  statusMsg.className = "alert alert-info py-2 px-3 text-center mb-3 small fw-semibold";
  statusMsg.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-info"></span> Mengakses kamera depan smartphone...';

  loadModels();

  try {
    video.setAttribute("playsinline", "");
    video.setAttribute("webkit-playsinline", "");

    videoStream = await navigator.mediaDevices.getUserMedia({
      video: {
        facingMode: "user",
        width: { ideal: 640 },
        height: { ideal: 480 },
        frameRate: { ideal: 30, max: 30 }
      },
      audio: false
    });
    video.srcObject = videoStream;
    await video.play();

    btnStart.classList.add("d-none");
    scanLine.classList.remove("d-none");
    statusMsg.className = "alert alert-primary py-2 px-3 text-center mb-3 small fw-semibold";
    statusMsg.innerHTML = '<i class="bi bi-person-bounding-box me-1"></i> Arahkan wajah Anda tegak di dalam bingkai oval.';
    
    startFaceTracking();
  } catch (err) {
    console.error("Akses kamera gagal:", err);
    statusMsg.className = "alert alert-danger py-2 px-3 text-center mb-3 small";
    statusMsg.innerHTML = '<i class="bi bi-camera-video-off me-1"></i> Kamera depan tidak dapat diakses. Berikan izin kamera di browser HP Anda.';
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
  enrollCompleted = true;
  detectedDescriptor = Array.from(descriptor);
  captureSnapshot();

  if (btnSnapNow) btnSnapNow.classList.add("d-none");
  statusMsg.className = "alert alert-success py-2 px-3 text-center mb-3 fw-bold small";
  statusMsg.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Sampel Wajah Berhasil Diambil! Tekan "SIMPAN BIOMETRIK WAJAH SAYA" di bawah.';
  btnSave.classList.remove("d-none");
}

async function forceCaptureBiometric() {
  if (!video || !video.videoWidth || enrollCompleted) return;
  statusMsg.className = "alert alert-info py-2 px-3 text-center mb-3 fw-bold small";
  statusMsg.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-primary"></span> Mengambil vektor biometrik instan...';

  const useTinyLandmarks = faceapi.nets.faceLandmark68TinyNet && faceapi.nets.faceLandmark68TinyNet.isLoaded;
  const fastDetectorOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 160, scoreThreshold: 0.3 });

  try {
    const fullDetection = await faceapi.detectSingleFace(video, fastDetectorOptions)
      .withFaceLandmarks(useTinyLandmarks)
      .withFaceDescriptor();

    if (fullDetection && fullDetection.descriptor) {
      completeEnrollment(fullDetection.descriptor);
    } else {
      statusMsg.className = "alert alert-warning py-2 px-3 text-center mb-3 small";
      statusMsg.innerHTML = '<i class="bi bi-person-exclamation me-1"></i> Wajah belum terdeteksi jelas. Posisikan wajah tepat di oval.';
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
  const fastDetectorOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 160, scoreThreshold: 0.30 });

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
          // Fase 1 Cepat: Deteksi wajah & landmarks
          const detection = await faceapi.detectSingleFace(video, fastDetectorOptions).withFaceLandmarks(useTinyLandmarks);

          if (detection) {
            faceOval.classList.add("active");
            if (btnSnapNow) btnSnapNow.classList.remove("d-none");

            const landmarks = detection.landmarks;
            const leftEye = landmarks.getLeftEye();
            const rightEye = landmarks.getRightEye();
            const avgEAR = (calculateEAR(leftEye) + calculateEAR(rightEye)) / 2.0;

            // Liveness Detection 1: Kedipan Mata (EAR)
            if (avgEAR < 0.26) {
              lastEyeState = "closed";
            } else if (avgEAR > 0.27 && lastEyeState === "closed") {
              blinkDetected = true;
              isLivenessVerified = true;
            }

            // Liveness Detection 2: Steady Hold (Tahan Wajah 1 Detik)
            enrollHoldFrames++;
            const pct = Math.min(100, Math.round((enrollHoldFrames / HOLD_FRAMES_REQUIRED) * 100));
            progressBar.style.width = pct + "%";

            if (enrollHoldFrames >= HOLD_FRAMES_REQUIRED) {
              isLivenessVerified = true;
            }

            if (!isLivenessVerified) {
              statusMsg.className = "alert alert-warning py-2 px-3 text-center mb-3 fw-bold small";
              statusMsg.innerHTML = '<i class="bi bi-eye-fill me-1"></i> Wajah Terdeteksi! <u>Tahan posisi 1 detik</u> atau <u>kedipkan mata</u>...';
            }
          } else {
            faceOval.classList.remove("active");
            enrollHoldFrames = Math.max(0, enrollHoldFrames - 2);
            progressBar.style.width = "20%";
            if (!isLivenessVerified) {
              statusMsg.className = "alert alert-secondary py-2 px-3 text-center mb-3 small";
              statusMsg.innerHTML = '<i class="bi bi-person-exclamation me-1"></i> Sesuaikan posisi wajah Anda tepat di dalam lingkaran oval.';
            }
          }
        } else {
          // Fase 2: Liveness lolos! Hitung descriptor
          statusMsg.className = "alert alert-info py-2 px-3 text-center mb-3 fw-bold small";
          statusMsg.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-primary"></span> Wajah stabil! Mengambil vektor biometrik...';

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
  const s = Math.min(video.videoWidth, video.videoHeight);
  const sx = (video.videoWidth - s) / 2;
  const sy = (video.videoHeight - s) / 2;
  ctx.translate(canvas.width, 0);
  ctx.scale(-1, 1);
  ctx.drawImage(video, sx, sy, s, s, 0, 0, canvas.width, canvas.height);
  capturedPhotoBase64 = canvas.toDataURL("image/jpeg", 0.8);
}

// Preload modul AI di awal agar saat klik "Aktifkan Kamera" langsung instan
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => setTimeout(loadModels, 200));
} else {
  setTimeout(loadModels, 200);
}

async function saveBiometrics() {
  if (!detectedDescriptor || detectedDescriptor.length < 64) {
    alert("Vektor biometrik belum lengkap. Silakan lakukan pemindaian ulang.");
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
  btnSave.innerHTML = \'<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan ke database...\';

  try {
    const res = await fetch("user_biometric_enroll.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        descriptor: JSON.stringify(detectedDescriptor),
        photo: capturedPhotoBase64,
        user_id: targetId,
        new_name: customName
      })
    });
    const result = await res.json();
    if (result && result.success) {
      statusMsg.className = "alert alert-success py-2 px-3 text-center mb-3 fw-bold small";
      statusMsg.innerHTML = \'<i class="bi bi-check-circle-fill me-1"></i> Wajah berhasil diunggah ke sistem!\';
      
      if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
      }
      btnSave.classList.add("d-none");
      
      const successDesc = document.getElementById("successDesc");
      if (successDesc) {
        successDesc.innerHTML = \'Wajah teknisi berhasil direkam dengan status: <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-hourglass-split me-1"></i> Menunggu Persetujuan Admin</span>.<br><br><strong>Wajib Disetujui Admin:</strong> Administrator IT harus menyetujui (Approve) foto wajah Anda melalui menu <em>Kelola Data &rarr; Akun Pengguna / Teknisi</em> sebelum wajah ini dapat digunakan untuk scan maintenance.<br><br><div class="alert alert-info py-2 px-3 small text-start mb-0 border-0 bg-info bg-opacity-10"><strong><i class="bi bi-info-circle-fill me-1 text-primary"></i>Catatan Penting:</strong> Pendaftaran wajah ini adalah untuk identitas akun teknisi Anda. Checklist pemeliharaan komputer belum disimpan. Silakan klik tombol <strong>"Kembali Lanjutkan Maintenance"</strong> di bawah untuk menyimpan checklist maintenance komputer ini.</div>\';
      }
      
      successBox.classList.remove("d-none");
    } else {
      alert("Gagal menyimpan biometrik: " + (result.error || "Kesalahan server"));
      btnSave.disabled = false;
      btnSave.innerHTML = \'<i class="bi bi-check-circle-fill me-1"></i> SIMPAN BIOMETRIK WAJAH SAYA\';
    }
  } catch (err) {
    console.error("Gagal kirim biometrik:", err);
    alert("Terjadi kesalahan koneksi saat menyimpan biometrik.");
    btnSave.disabled = false;
  }
}

function b64urlToBuffer(base64url) {
  let base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
  while (base64.length % 4) base64 += '=';
  const bin = atob(base64);
  const bytes = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
  return bytes.buffer;
}

function bufferToB64url(buffer) {
  const bytes = new Uint8Array(buffer);
  let str = '';
  for (const b of bytes) str += String.fromCharCode(b);
  return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

// Cek apakah perangkat iPhone / smartphone mendukung sensor Face ID / Sidik Jari fisik
if (window.PublicKeyCredential && PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) {
  PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().then(avail => {
    if (avail) {
      const box = document.getElementById("nativeFaceIdBox");
      if (box) {
        box.style.display = "block";
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        const isAndroid = /Android/.test(navigator.userAgent);
        const btn = document.getElementById("btnEnrollFaceId");
        if (isAndroid) {
          box.querySelector(".fw-bold.text-primary").textContent = "Sidik Jari / Biometrik Android (Rekomendasi)";
          box.querySelector(".text-muted").textContent = "Verifikasi instan via sensor sidik jari / face unlock Android (< 0.5 detik)";
          box.querySelector(".text-secondary").textContent = "Daftarkan sidik jari HP Android Anda sekarang agar dapat login dan verifikasi maintenance tanpa perlu membuka kamera web.";
          const iconEl = box.querySelector("i.bi-apple");
          if (iconEl) iconEl.className = "bi bi-fingerprint";
          if (btn) btn.innerHTML = '<i class="bi bi-fingerprint me-1"></i> Daftarkan Sidik Jari Android Ini';
        }
      }
    }
  }).catch(() => {});
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
    const res = await fetch("user_biometric_enroll.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "add_tech_only", new_name: nameVal })
    });
    const result = await res.json();
    if (result && result.success && result.user) {
      if (statusEl) {
        statusEl.className = "alert alert-success py-2 px-3 small fw-bold";
        statusEl.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> ' + (result.message || "Nama teknisi berhasil didaftarkan!");
      }
      const userSel = document.getElementById("userSelect");
      if (userSel) {
        const opt = document.createElement("option");
        opt.value = result.user.id;
        opt.text = result.user.nama + " (teknisi) [✓ Terdaftar]";
        opt.selected = true;
        userSel.insertBefore(opt, userSel.lastElementChild);
      }
      setTimeout(() => {
        alert("✓ Sukses! Nama teknisi '" + result.user.nama + "' berhasil didaftarkan ke Google Sheets dan siap digunakan.");
        const currentParam = new URLSearchParams(window.location.search);
        currentParam.set("id", result.user.id);
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
    alert(err.message || "Gagal mendaftarkan nama teknisi baru.");
    if (btn) btn.disabled = false;
  }
}

async function enrollNativeFaceId() {
  const btn = document.getElementById("btnEnrollFaceId");
  const status = document.getElementById("nativeFaceIdStatus");
  const userSel = document.getElementById("userSelect");
  let targetId = userSel ? parseInt(userSel.value, 10) : 0;

  if (targetId <= 0 && targetId !== -1) {
    alert("Silakan pilih nama teknisi Anda terlebih dahulu.");
    return;
  }

  btn.disabled = true;
  status.className = "small mt-2 text-primary fw-semibold";

  // Jika memilih tambah teknisi baru, daftarkan akunnya terlebih dahulu secara otomatis
  if (targetId === -1) {
    const nameInput = document.getElementById("newTechName");
    const customName = nameInput ? nameInput.value.trim() : "";
    if (!customName) {
      alert("Silakan ketik nama lengkap teknisi baru terlebih dahulu.");
      if (nameInput) nameInput.focus();
      btn.disabled = false;
      return;
    }
    status.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mendaftarkan nama teknisi ke Google Sheets...';
    try {
      const createRes = await fetch("user_biometric_enroll.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "add_tech_only", new_name: customName })
      });
      const createData = await createRes.json();
      if (!createData.success || !createData.user || !createData.user.id) {
        throw new Error(createData.error || "Gagal membuat akun teknisi baru.");
      }
      targetId = createData.user.id;
    } catch (createErr) {
      status.className = "small mt-2 text-danger";
      status.innerHTML = '<i class="bi bi-x-circle me-1"></i> ' + createErr.message;
      btn.disabled = false;
      return;
    }
  }

  status.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyiapkan sesi pendaftaran Face ID...';

  try {
    const res = await fetch("webauthn_handler.php?action=register_options&user_id=" + targetId);
    const data = await res.json();
    if (!data.success || !data.options) {
      throw new Error(data.error || "Gagal menyiapkan Face ID.");
    }

    const opts = data.options;
    opts.challenge = b64urlToBuffer(opts.challenge);
    opts.user.id = b64urlToBuffer(opts.user.id);
    if (Array.isArray(opts.excludeCredentials)) {
      opts.excludeCredentials = opts.excludeCredentials.map(c => ({
        type: c.type,
        id: b64urlToBuffer(c.id)
      }));
    }

    status.innerHTML = '<i class="bi bi-phone-fill me-1 text-primary"></i> Silakan verifikasi wajah di dialog Face ID iPhone...';
    const cred = await navigator.credentials.create({ publicKey: opts });
    if (!cred) throw new Error("Pendaftaran dibatalkan.");

    status.innerHTML = '<span class="spinner-border spinner-border-sm me-1 text-success"></span> Menyimpan Face ID ke Google Sheets...';

    const verifyRes = await fetch("webauthn_handler.php?action=register_verify", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        id: cred.id,
        rawId: bufferToB64url(cred.rawId),
        device_name: "Apple iPhone (Face ID)",
        response: {
          clientDataJSON: bufferToB64url(cred.response.clientDataJSON),
          attestationObject: bufferToB64url(cred.response.attestationObject)
        }
      })
    });

    const verifyData = await verifyRes.json();
    if (verifyData.success) {
      status.className = "small mt-2 text-success fw-bold";
      status.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> ' + verifyData.message;
      btn.className = "btn btn-success fw-bold py-2 rounded-3 shadow-sm w-100";
      btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Face ID iPhone Berhasil Didaftarkan!';
      btn.disabled = true;
    } else {
      throw new Error(verifyData.error || "Gagal menyimpan Face ID.");
    }
  } catch (err) {
    console.error(err);
    status.className = "small mt-2 text-danger";
    status.innerHTML = '<i class="bi bi-exclamation-circle-fill me-1"></i> ' + (err.message || "Gagal mendaftarkan Face ID.");
    btn.disabled = false;
  }
}

document.addEventListener("DOMContentLoaded", () => {
  loadModels();
});
</script>
HTML;

render_page('Daftar Wajah Teknisi (HP)', $body, $head, $script, false);
