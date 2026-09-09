<?php
require __DIR__ . '/bootstrap.php';
require_login();

$currId = current_user_id();
$userId = max(0, (int)($_GET['id'] ?? $currId));
$isAdmin = function_exists('is_admin') ? is_admin() : in_array(current_user_role(), ['admin', 'administrator'], true);

// Hanya admin yang bisa mendaftarkan orang lain, atau teknisi mendaftarkan dirinya sendiri
if (!$isAdmin && $userId !== $currId) {
    $userId = $currId;
}

$user = get_user_by_id($userId);
if (!$user) {
    http_response_code(404);
    render_page('Pengguna Tidak Ditemukan', '<div class="alert alert-danger">Pengguna dengan ID #'.$userId.' tidak ditemukan.</div>');
    exit;
}

// Handle AJAX POST simpan biometrik
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $descriptor = trim((string)($data['descriptor'] ?? ''));
    $photo = trim((string)($data['photo'] ?? ''));

    if (empty($descriptor)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Data vektor biometrik tidak boleh kosong.']);
        exit;
    }

    $res = save_user_biometrics($userId, $descriptor, $photo);
    header('Content-Type: application/json');
    echo json_encode($res);
    exit;
}

$hasBiometrics = !empty($user['face_descriptor']);
$existingPhoto = $user['face_photo'] ?? '';

$badgeStatus = $hasBiometrics 
    ? '<span class="badge bg-success bg-opacity-15 text-success px-3 py-2 border border-success border-opacity-25 fs-6"><i class="bi bi-shield-check me-1"></i> Wajah Sudah Terdaftar</span>'
    : '<span class="badge bg-warning bg-opacity-15 text-warning-emphasis px-3 py-2 border border-warning border-opacity-25 fs-6"><i class="bi bi-exclamation-circle me-1"></i> Belum Didaftarkan</span>';

$avatarHtml = '';
if ($existingPhoto) {
    $avatarHtml = '
    <div class="text-center mb-3">
      <div class="d-inline-block position-relative">
        <img src="'.e($existingPhoto).'" alt="Foto Biometrik" class="rounded-circle border border-3 border-success shadow-sm" style="width: 90px; height: 90px; object-fit: cover;">
        <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-success" title="Terverifikasi"><i class="bi bi-check"></i></span>
      </div>
      <div class="small text-muted mt-1">Sampel Wajah Terdaftar</div>
    </div>';
}

$head = '
<style>
  .scanner-container {
    position: relative;
    max-width: 480px;
    margin: 0 auto;
    background: #0f172a;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
  }
  .scanner-video {
    width: 100%;
    height: 360px;
    object-fit: cover;
    transform: scaleX(-1); /* Mirror view */
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
    width: 200px;
    height: 260px;
    border: 3px dashed #38bdf8;
    border-radius: 50%;
    box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.65);
    transition: border-color 0.3s ease, transform 0.3s ease;
  }
  .face-oval.active {
    border-color: #22c55e;
    border-style: solid;
    box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.4), 0 0 20px rgba(34, 197, 94, 0.6);
  }
  .scanline {
    position: absolute;
    top: 25%;
    left: calc(50% - 100px);
    width: 200px;
    height: 2px;
    background: linear-gradient(90deg, transparent, #38bdf8, transparent);
    animation: scanMove 2s infinite ease-in-out;
  }
  @keyframes scanMove {
    0% { top: 20%; opacity: 0; }
    50% { opacity: 1; }
    100% { top: 75%; opacity: 0; }
  }
</style>';

$body = '
<div class="row justify-content-center">
  <div class="col-lg-8 col-xl-7">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="fw-bold mb-0 text-dark"><i class="bi bi-person-bounding-box me-2 text-primary"></i>Registrasi Wajah Biometrik</h2>
        <div class="text-secondary small">Daftarkan biometrik wajah teknisi resmi untuk verifikasi kehadiran pemeliharaan IT.</div>
      </div>
      <a class="btn btn-outline-secondary btn-sm" href="'.e(module_url('users_admin.php')).'"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <!-- Info Akun -->
    <div class="card p-3 mb-3 border-0 shadow-sm">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
          <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle fs-3">
            <i class="bi bi-person-gear"></i>
          </div>
          <div>
            <h5 class="fw-bold mb-0 text-dark">'.e($user['nama']).'</h5>
            <div class="text-muted small">Username: <code class="text-primary fw-bold">@'.e($user['username']).'</code> · Role: <span class="badge text-bg-light border text-capitalize">'.e($user['role']).'</span></div>
          </div>
        </div>
        <div>
          '.$badgeStatus.'
        </div>
      </div>
    </div>

    '.$avatarHtml.'

    <!-- Persetujuan UU PDP (Data Pribadi Sensitif) -->
    <div class="alert alert-info py-2 px-3 mb-3 small border-0 bg-info bg-opacity-10 text-dark">
      <div class="d-flex gap-2">
        <i class="bi bi-shield-lock-fill text-info fs-5 mt-1"></i>
        <div>
          <strong>Persetujuan Pemrosesan Data Biometrik (UU PDP No. 27/2022):</strong><br>
          Data biometrik yang disimpan adalah representasi matematis vektor (128-float embedding) terenkripsi, bukan foto mentah resolusi tinggi tanpa izin. Data ini hanya digunakan secara internal untuk verifikasi kehadiran teknisi saat melaksanakan pemeliharaan perangkat IT PT BPR Mitratama Arthabuana.
        </div>
      </div>
    </div>

    <!-- Scanner Box -->
    <div class="card p-4 border-0 shadow-sm mb-4">
      <div class="scanner-container mb-3" id="scannerContainer">
        <video id="videoElement" class="scanner-video" autoplay playsinline muted></video>
        <div class="scanner-overlay">
          <div class="face-oval" id="faceOval"></div>
          <div class="scanline" id="scanLine"></div>
        </div>
        <canvas id="snapshotCanvas" style="display:none;" width="320" height="320"></canvas>
      </div>

      <!-- Status Bar -->
      <div class="alert alert-secondary py-2 px-3 text-center mb-3" id="statusMessage">
        <span class="spinner-border spinner-border-sm me-2 text-primary" role="status"></span>
        <span>Menyiapkan kamera & modul AI pengenalan wajah...</span>
      </div>

      <!-- Progress Liveness -->
      <div class="progress mb-3" style="height: 6px;" id="progressBarContainer">
        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="progressBar" style="width: 20%;"></div>
      </div>

      <!-- Control Buttons -->
      <div class="d-flex justify-content-center gap-2 flex-wrap">
        <button type="button" class="btn btn-primary px-4 py-2 fw-semibold" id="btnStartCapture" onclick="startCamera()">
          <i class="bi bi-camera-video me-1"></i> Mulai Deteksi Kamera
        </button>
        <button type="button" class="btn btn-success px-4 py-2 fw-semibold d-none" id="btnSaveBiometric" onclick="saveBiometrics()">
          <i class="bi bi-check-circle-fill me-1"></i> Simpan Biometrik Wajah
        </button>
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
    statusMsg.className = "alert alert-success py-2 px-3 text-center mb-3";
    statusMsg.innerHTML = \'<i class="bi bi-check-circle me-1"></i> Modul AI siap. Silakan klik "Mulai Deteksi Kamera".\';
  } catch (err) {
    console.error("Gagal memuat model:", err);
    statusMsg.className = "alert alert-danger py-2 px-3 text-center mb-3";
    statusMsg.innerHTML = \'<i class="bi bi-x-circle me-1"></i> Gagal memuat model AI. Periksa koneksi internet Anda.\';
  }
}

async function startCamera() {
  if (!modelsLoaded) {
    await loadModels();
  }
  
  btnStart.disabled = true;
  statusMsg.className = "alert alert-info py-2 px-3 text-center mb-3";
  statusMsg.innerHTML = \'<span class="spinner-border spinner-border-sm me-2 text-info"></span> Mengakses kamera depan smartphone/laptop...\';

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
    statusMsg.className = "alert alert-primary py-2 px-3 text-center mb-3";
    statusMsg.innerHTML = \'<i class="bi bi-person-bounding-box me-1"></i> Posisikan wajah Anda tegak di dalam bingkai oval.\';
    
    startFaceTracking();
  } catch (err) {
    console.error("Akses kamera gagal:", err);
    statusMsg.className = "alert alert-danger py-2 px-3 text-center mb-3";
    statusMsg.innerHTML = \'<i class="bi bi-camera-video-off me-1"></i> Tidak dapat mengakses kamera. Pastikan izin kamera telah diberikan.\';
    btnStart.disabled = false;
  }
}

// Hitung Eye Aspect Ratio (EAR) untuk deteksi kedipan (Liveness)
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

    const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 }))
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

      // Liveness Detection: Cek Kedipan
      if (avgEAR < 0.23) {
        lastEyeState = "closed";
      } else if (avgEAR > 0.28 && lastEyeState === "closed") {
        blinkDetected = true;
        isLivenessVerified = true;
      }

      if (!isLivenessVerified) {
        statusMsg.className = "alert alert-warning py-2 px-3 text-center mb-3 fw-bold";
        statusMsg.innerHTML = \'<i class="bi bi-eye-fill me-1"></i> Wajah Terdeteksi! Silakan <u>KEDIPKAN MATA</u> untuk verifikasi keaslian (Liveness Check)...\';
      } else {
        // Liveness Lolos!
        statusMsg.className = "alert alert-success py-2 px-3 text-center mb-3 fw-bold";
        statusMsg.innerHTML = \'<i class="bi bi-check-circle-fill me-1"></i> Liveness Lolos! Vektor biometrik 128-dimensi berhasil diekstrak.\';
        
        detectedDescriptor = Array.from(detection.descriptor);
        
        // Ambil snapshot wajah lingkaran kecil
        captureSnapshot();
        
        btnSave.classList.remove("d-none");
        clearInterval(trackingInterval);
      }
    } else {
      faceOval.classList.remove("active");
      if (!isLivenessVerified) {
        statusMsg.className = "alert alert-secondary py-2 px-3 text-center mb-3";
        statusMsg.innerHTML = \'<i class="bi bi-person-exclamation me-1"></i> Sesuaikan posisi wajah Anda tepat di dalam lingkaran oval.\';
      }
    }
  }, 250);
}

function captureSnapshot() {
  const canvas = document.getElementById("snapshotCanvas");
  const ctx = canvas.getContext("2d");
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
  capturedPhotoBase64 = canvas.toDataURL("image/jpeg", 0.7);
}

async function saveBiometrics() {
  if (!detectedDescriptor || detectedDescriptor.length < 64) {
    alert("Vektor biometrik belum lengkap. Silakan lakukan pemindaian ulang.");
    return;
  }

  btnSave.disabled = true;
  btnSave.innerHTML = \'<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan ke database...\';

  try {
    const res = await fetch(window.location.href, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        descriptor: JSON.stringify(detectedDescriptor),
        photo: capturedPhotoBase64
      })
    });
    const result = await res.json();
    if (result && result.success) {
      statusMsg.className = "alert alert-success py-3 px-3 text-center mb-3";
      statusMsg.innerHTML = \'<h5 class="fw-bold mb-1"><i class="bi bi-check-circle-fill me-1"></i> Registrasi Biometrik Berhasil!</h5><div class="small">Wajah teknisi telah terdaftar dan siap digunakan untuk verifikasi pemeliharaan.</div>\';
      
      if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
      }
      setTimeout(() => {
        window.location.href = "users_admin.php";
      }, 1500);
    } else {
      alert("Gagal menyimpan biometrik: " + (result.error || "Kesalahan server"));
      btnSave.disabled = false;
      btnSave.innerHTML = \'<i class="bi bi-check-circle-fill me-1"></i> Simpan Biometrik Wajah\';
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

render_page('Registrasi Wajah Biometrik Teknisi', $body, $head, $script);
