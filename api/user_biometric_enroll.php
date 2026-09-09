<?php
require __DIR__ . '/bootstrap.php';

// Ambil seluruh daftar pengguna / teknisi untuk dipilih di HP
$allUsers = get_user_list();
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

// Handle AJAX POST simpan biometrik langsung dari HP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $descriptor = trim((string)($data['descriptor'] ?? ''));
    $photo = trim((string)($data['photo'] ?? ''));
    $targetUserId = (int)($data['user_id'] ?? $selectedUserId);
    $newTechName = trim((string)($data['new_name'] ?? ''));

    if (empty($descriptor)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Data vektor biometrik tidak boleh kosong.']);
        exit;
    }

    // Jika teknisi mendaftar dengan nama baru secara mandiri
    if ($targetUserId === -1 && $newTechName !== '') {
        $cleanUser = strtolower(preg_replace('/[^a-z0-9]/', '', $newTechName));
        if (strlen($cleanUser) < 3) {
            $cleanUser = 'teknisi_' . substr(uniqid(), -5);
        }
        $created = create_new_user([
            'username' => $cleanUser,
            'password' => 'teknisi123',
            'nama' => $newTechName,
            'role' => 'teknisi',
            'telepon' => '-',
            'status' => 'Aktif'
        ]);
        if (!empty($created['id'])) {
            $targetUserId = (int)$created['id'];
        } elseif (!empty($created['success'])) {
            $list = get_user_list();
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

    $res = save_user_biometrics($targetUserId, $descriptor, $photo);
    header('Content-Type: application/json');
    echo json_encode($res);
    exit;
}

$user = get_user_by_id($selectedUserId);
if (!$user && !empty($allUsers)) {
    $user = $allUsers[0];
    $selectedUserId = (int)$user['id'];
}

$hasBiometrics = !empty($user['face_descriptor']);
$existingPhoto = $user['face_photo'] ?? '';

$badgeStatus = $hasBiometrics 
    ? '<span class="badge bg-success bg-opacity-15 text-success px-3 py-2 border border-success border-opacity-25"><i class="bi bi-shield-check me-1"></i> Sudah Ada Wajah Terdaftar</span>'
    : '<span class="badge bg-warning bg-opacity-15 text-warning-emphasis px-3 py-2 border border-warning border-opacity-25"><i class="bi bi-exclamation-circle me-1"></i> Belum Didaftarkan</span>';

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
    $unama = $u['nama'] ?? $u['username'] ?? 'User';
    $urole = ucfirst($u['role'] ?? 'Teknisi');
    $uHasBio = !empty($u['face_descriptor']);
    $sel = ($uid === $selectedUserId) ? 'selected' : '';
    $bioMark = $uHasBio ? ' [✓ Terdaftar]' : ' [⚠️ Belum Wajah]';
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
      <div class="mb-3 d-none" id="newTechBox">
        <label class="form-label small fw-bold text-success mb-1">
          <i class="bi bi-person-plus-fill me-1"></i> Masukkan Nama Lengkap Teknisi Baru:
        </label>
        <input type="text" class="form-control form-control-lg fw-semibold" id="newTechName" placeholder="Contoh: Budi Santoso">
        <div class="form-text text-muted" style="font-size: 0.75rem;">Nama ini akan otomatis didaftarkan sebagai akun teknisi resmi.</div>
      </div>

      '.$avatarHtml.'

      <!-- Panduan Singkat Smartphone -->
      <div class="alert alert-light border border-primary border-opacity-25 rounded-3 p-3 mb-3 small">
        <div class="fw-bold text-primary mb-1 d-flex align-items-center gap-1">
          <i class="bi bi-lightning-charge-fill text-warning"></i> Cara Scan Wajah via HP:
        </div>
        <ol class="mb-0 ps-3 text-secondary">
          <li>Klik tombol <strong>"Aktifkan Kamera Depan HP"</strong> di bawah.</li>
          <li>Pegang HP tegak lurus mengarah ke wajah Anda.</li>
          <li>Arahkan wajah ke lingkaran oval hingga garis berubah <strong class="text-success">HIJAU</strong>.</li>
          <li><strong>Kedipkan mata Anda 1 kali</strong> (uji keaslian / liveness check).</li>
          <li>Tekan <strong>"SIMPAN BIOMETRIK WAJAH"</strong>.</li>
        </ol>
      </div>

      <!-- Scanner Container (Mobile Optimized) -->
      <div class="scanner-container mb-3" id="scannerContainer">
        <video id="videoElement" class="scanner-video" autoplay playsinline muted></video>
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
        <button type="button" class="btn btn-success btn-lg fw-bold py-3 shadow d-none" id="btnSaveBiometric" onclick="saveBiometrics()">
          <i class="bi bi-check-circle-fill me-2"></i> SIMPAN BIOMETRIK WAJAH SAYA
        </button>
      </div>

      <!-- Success Action Box (Tampil setelah berhasil) -->
      <div class="mt-3 p-3 bg-success bg-opacity-10 border border-success rounded-3 text-center d-none" id="successBox">
        <h5 class="fw-bold text-success mb-1"><i class="bi bi-check-circle-fill me-1"></i> Wajah Berhasil Didaftarkan!</h5>
        <div class="small text-secondary mb-3">Anda sekarang dapat menyelesaikan pemeliharaan dengan verifikasi scan wajah di HP.</div>
        <div class="d-grid gap-2">
          '.($retUrl !== '' ? '<a href="'.e($retUrl).'" class="btn btn-success fw-bold"><i class="bi bi-arrow-return-left me-1"></i> Kembali Lanjutkan Maintenance</a>' : '<a href="'.e(module_url('dashboard.php')).'" class="btn btn-primary fw-bold"><i class="bi bi-qr-code-scan me-1"></i> Buka Scan QR Maintenance</a>').'
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

$script = '
<!-- Load face-api.js dari CDN yang stabil -->
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.min.js"></script>
<script>
let modelsLoaded = false;
let videoStream = null;
let detectedDescriptor = null;
let capturedPhotoBase64 = null;
let isLivenessVerified = false;
let blinkDetected = false;
let lastEyeState = "open";

const MODEL_URL = "https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/";

const video = document.getElementById("videoElement");
const faceOval = document.getElementById("faceOval");
const scanLine = document.getElementById("scanLine");
const statusMsg = document.getElementById("statusMessage");
const progressBar = document.getElementById("progressBar");
const btnSave = document.getElementById("btnSaveBiometric");
const btnStart = document.getElementById("btnStartCapture");
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
  try {
    statusMsg.innerHTML = \'<span class="spinner-border spinner-border-sm me-2 text-primary"></span> Memuat model AI TinyFace & Landmark 68...\';
    progressBar.style.width = "40%";
    
    await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
    progressBar.style.width = "65%";
    await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
    progressBar.style.width = "85%";
    await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
    progressBar.style.width = "100%";
    
    modelsLoaded = true;
    statusMsg.className = "alert alert-success py-2 px-3 text-center mb-3 small fw-semibold";
    statusMsg.innerHTML = \'<i class="bi bi-check-circle me-1"></i> Modul AI siap. Silakan klik "AKTIFKAN KAMERA DEPAN HP".\';
  } catch (err) {
    console.error("Gagal memuat model:", err);
    statusMsg.className = "alert alert-danger py-2 px-3 text-center mb-3 small";
    statusMsg.innerHTML = \'<i class="bi bi-x-circle me-1"></i> Gagal memuat modul AI. Pastikan smartphone terhubung internet.\';
  }
}

async function startCamera() {
  if (!modelsLoaded) {
    await loadModels();
  }
  
  btnStart.disabled = true;
  statusMsg.className = "alert alert-info py-2 px-3 text-center mb-3 small fw-semibold";
  statusMsg.innerHTML = \'<span class="spinner-border spinner-border-sm me-2 text-info"></span> Mengakses kamera depan smartphone...\';

  try {
    videoStream = await navigator.mediaDevices.getUserMedia({
      video: {
        facingMode: "user",
        width: { ideal: 640 },
        height: { ideal: 480 }
      },
      audio: false
    });
    video.srcObject = videoStream;
    await video.play();

    btnStart.classList.add("d-none");
    scanLine.classList.remove("d-none");
    statusMsg.className = "alert alert-primary py-2 px-3 text-center mb-3 small fw-semibold";
    statusMsg.innerHTML = \'<i class="bi bi-person-bounding-box me-1"></i> Posisikan wajah Anda tegak di dalam bingkai oval.\';
    
    startFaceTracking();
  } catch (err) {
    console.error("Akses kamera gagal:", err);
    statusMsg.className = "alert alert-danger py-2 px-3 text-center mb-3 small";
    statusMsg.innerHTML = \'<i class="bi bi-camera-video-off me-1"></i> Kamera depan tidak dapat diakses. Berikan izin kamera di browser HP Anda.\';
    btnStart.disabled = false;
  }
}

function calculateEAR(eye) {
  const distA = Math.hypot(eye[1].x - eye[5].x, eye[1].y - eye[5].y);
  const distB = Math.hypot(eye[2].x - eye[4].x, eye[2].y - eye[4].y);
  const distC = Math.hypot(eye[0].x - eye[3].x, eye[0].y - eye[3].y);
  return (distA + distB) / (2.0 * distC);
}

let trackingInterval = null;
function startFaceTracking() {
  if (trackingInterval) clearInterval(trackingInterval);

  trackingInterval = setInterval(async () => {
    if (!video.videoWidth || !video.videoHeight || video.paused || video.ended) return;

    const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.48 }))
      .withFaceLandmarks()
      .withFaceDescriptor();

    if (detection) {
      faceOval.classList.add("active");
      const landmarks = detection.landmarks;
      const leftEye = landmarks.getLeftEye();
      const rightEye = landmarks.getRightEye();

      const leftEAR = calculateEAR(leftEye);
      const rightEAR = calculateEAR(rightEye);
      const avgEAR = (leftEAR + rightEAR) / 2.0;

      // Liveness Detection: Deteksi Kedipan Mata
      if (avgEAR < 0.23) {
        lastEyeState = "closed";
      } else if (avgEAR > 0.28 && lastEyeState === "closed") {
        blinkDetected = true;
        isLivenessVerified = true;
      }

      if (!isLivenessVerified) {
        statusMsg.className = "alert alert-warning py-2 px-3 text-center mb-3 fw-bold small";
        statusMsg.innerHTML = \'<i class="bi bi-eye-fill me-1"></i> Wajah Terdeteksi! Silakan <u>KEDIPKAN MATA</u> Anda (Uji Liveness)...\';
      } else {
        // Liveness Terkonfirmasi!
        statusMsg.className = "alert alert-success py-2 px-3 text-center mb-3 fw-bold small";
        statusMsg.innerHTML = \'<i class="bi bi-check-circle-fill me-1"></i> Liveness Lolos! Tekan "SIMPAN BIOMETRIK WAJAH SAYA" di bawah.\';
        
        detectedDescriptor = Array.from(detection.descriptor);
        captureSnapshot();
        
        btnSave.classList.remove("d-none");
        clearInterval(trackingInterval);
      }
    } else {
      faceOval.classList.remove("active");
      if (!isLivenessVerified) {
        statusMsg.className = "alert alert-secondary py-2 px-3 text-center mb-3 small";
        statusMsg.innerHTML = \'<i class="bi bi-person-exclamation me-1"></i> Sesuaikan posisi wajah Anda tepat di dalam lingkaran oval.\';
      }
    }
  }, 250);
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
      statusMsg.innerHTML = \'<i class="bi bi-check-circle-fill me-1"></i> Wajah berhasil didaftarkan ke sistem!\';
      
      if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
      }
      btnSave.classList.add("d-none");
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

document.addEventListener("DOMContentLoaded", () => {
  loadModels();
});
</script>';

render_page('Daftar Wajah Teknisi (HP)', $body, $head, $script, false);
