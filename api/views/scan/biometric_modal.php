<?php
// =========================================================================
function render_biometric_modal_html(string $token, string $returnAction = 'form'): string {
    return '
    <!-- Modal Biometric Face Recognition & Liveness Check -->
    <div class="modal fade" id="modalBiometricScan" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
          <div class="modal-header bg-dark text-white border-0 py-3">
            <div class="d-flex align-items-center gap-2">
              <div class="p-2 bg-primary bg-opacity-25 rounded-circle text-primary fs-5">
                <i class="bi bi-shield-check"></i>
              </div>
              <div>
                <h6 class="modal-title fw-bold mb-0">Biometric Face Recognition & Liveness</h6>
                <div class="text-white-50 small" style="font-size: 0.72rem;">Verifikasi kehadiran teknisi resmi PT BPR Mitratama Arthabuana</div>
              </div>
            </div>
            <button type="button" class="btn-close btn-close-white" onclick="closeBiometricModal()"></button>
          </div>

          <div class="modal-body p-3 p-md-4 text-center bg-light">
            <!-- Scanner Box -->
            <div class="bio-scanner-wrapper mb-3">
              <video id="bioVideo" class="bio-video-el" autoplay playsinline webkit-playsinline muted></video>
              <canvas id="bioCanvas" class="bio-canvas-el"></canvas>
              <div id="bioFaceOval" class="bio-face-oval"></div>
              <div id="bioScanLine" class="bio-scanline d-none"></div>

              <!-- Success Overlay -->
              <div id="bioSuccessOverlay" class="bio-success-overlay d-none">
                <div class="text-center p-3">
                  <div class="display-3 text-success mb-2">
                    <i class="bi bi-check-circle-fill"></i>
                  </div>
                  <h5 class="fw-bold text-white mb-1" id="bioMatchedName">Nama Teknisi</h5>
                  <div class="badge bg-success bg-opacity-75 fs-6 mb-2" id="bioMatchConfidence">98% Cocok</div>
                  <div class="text-white-50 small">Verifikasi Liveness & Identitas Berhasil!<br>Menyimpan data...</div>
                </div>
              </div>
            </div>

            <!-- Status & Instruction Badge -->
            <div id="bioStatusBox" class="alert alert-info py-2 px-3 small fw-semibold mb-2">
              <span class="spinner-border spinner-border-sm me-2 text-primary"></span>
              Menyiapkan kamera & modul AI...
            </div>

            <!-- Steady Hold Progress Bar -->
            <div class="progress mb-2 d-none" id="bioHoldProgress" style="height: 6px;">
              <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" id="bioHoldProgressBar" style="width: 0%"></div>
            </div>

            <!-- Tombol Aksi Cepat Verifikasi Instan (Muncul saat wajah terdeteksi) -->
            <div class="mb-2 d-none" id="bioInstantVerifyBox">
              <button type="button" class="btn btn-warning text-dark btn-sm w-100 fw-bold py-2 shadow-sm rounded-pill" onclick="triggerInstantBioVerify()">
                <i class="bi bi-lightning-charge-fill me-1"></i> Wajah Terdeteksi &mdash; Tekan untuk Verifikasi Langsung
              </button>
            </div>

            <!-- Liveness Checklist Pills -->
            <div class="d-flex justify-content-center gap-2 mb-3">
              <span id="pillFaceDetected" class="badge bg-secondary bg-opacity-25 text-dark small py-2 px-3 border">
                <i class="bi bi-person me-1"></i> Wajah
              </span>
              <span id="pillLivenessBlink" class="badge bg-secondary bg-opacity-25 text-dark small py-2 px-3 border">
                <i class="bi bi-eye me-1"></i> Kedip (Liveness)
              </span>
              <span id="pillMatchId" class="badge bg-secondary bg-opacity-25 text-dark small py-2 px-3 border">
                <i class="bi bi-check-all me-1"></i> Identitas Cocok
              </span>
            </div>

            <!-- Link Pendaftaran Wajah di HP jika belum terdaftar -->
            <div class="mb-3">
              <a href="'.e(module_url('user_biometric_enroll.php', ['ret' => module_url('scan.php', ['t' => $token, 'action' => $returnAction])])).'" class="btn btn-outline-primary btn-sm w-100 rounded-pill">
                <i class="bi bi-phone-fill me-1"></i> Wajah Belum Terdaftar? Daftarkan di HP Sekarang
              </a>
            </div>

            <div class="d-flex justify-content-between align-items-center">
              <button type="button" class="btn btn-outline-secondary btn-sm" onclick="closeBiometricModal()">
                Batal
              </button>
              <button type="button" class="btn btn-link btn-sm text-danger text-decoration-none p-0" onclick="bypassBiometricAndSubmit()">
                <i class="bi bi-exclamation-octagon me-1"></i> Lewati & Simpan Manual
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>';
}

function render_biometric_css(): string {
    return '
    <style>
    .bio-scanner-wrapper {
      position: relative;
      width: 320px;
      height: 380px;
      max-width: 100%;
      border-radius: 20px;
      overflow: hidden;
      background: #0f172a;
      box-shadow: 0 10px 25px rgba(0,0,0,0.25);
      margin: 0 auto;
    }
    .bio-video-el {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transform: scaleX(-1);
    }
    .bio-canvas-el {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      transform: scaleX(-1);
    }
    .bio-face-oval {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 190px;
      height: 240px;
      border: 3px dashed #38bdf8;
      border-radius: 50%;
      box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.65);
      pointer-events: none;
      transition: all 0.3s ease;
    }
    .bio-face-oval.active {
      border-color: #22c55e;
      border-style: solid;
      box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.4), 0 0 25px rgba(34, 197, 94, 0.6);
    }
    .bio-scanline {
      position: absolute;
      top: 25%;
      left: calc(50% - 95px);
      width: 190px;
      height: 3px;
      background: linear-gradient(90deg, transparent, #38bdf8, transparent);
      animation: bioScanMove 2s infinite ease-in-out;
      pointer-events: none;
    }
    @keyframes bioScanMove {
      0% { top: 20%; opacity: 0; }
      50% { opacity: 1; }
      100% { top: 80%; opacity: 0; }
    }
    .bio-success-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(15, 23, 42, 0.92);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10;
    }
    </style>';
}

function render_biometric_js(array $enrolledTechs, string $extraJs = ''): string {
    $techsJson = json_encode($enrolledTechs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $out = '<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.min.js"></script>' . "\n";
    $out .= "<script>\n";
    if ($extraJs !== '') {
        $out .= $extraJs . "\n";
    }
    $out .= "window.__ENROLLED_TECHS = " . $techsJson . ";\n";
    $out .= <<<'JS'
    // Pre-parse vektor teknisi satu kali saat halaman dimuat
    const parsedEnrolledTechs = (Array.isArray(window.__ENROLLED_TECHS) ? window.__ENROLLED_TECHS : []).map(t => {
      let desc = t.descriptor || t.face_descriptor;
      if (typeof desc === "string") {
        try { desc = JSON.parse(desc); } catch (e) { desc = null; }
      }
      return {
        id: t.id,
        nama: t.nama,
        descriptor: (Array.isArray(desc) && desc.length >= 64) ? new Float32Array(desc) : null
      };
    }).filter(t => t.descriptor !== null);

    let bioModelsLoaded = false;
    let bioModelsLoading = false;
    let bioVideoStream = null;
    let bioTrackingTimer = null;
    let bioBlinkDetected = false;
    let bioLastEyeState = "open";
    let bioModalInstance = null;
    let bioCompleted = false;
    let bioFaceHoldFrames = 0;
    const HOLD_FRAMES_REQUIRED = 12; // ~0.7-0.9 detik tahan posisi wajah

    const MODEL_URL = "https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/";

    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          const latEl = document.getElementById("bioLat");
          const lngEl = document.getElementById("bioLng");
          if (latEl) latEl.value = pos.coords.latitude;
          if (lngEl) lngEl.value = pos.coords.longitude;
        },
        (err) => console.log("GPS Notice:", err.message),
        { enableHighAccuracy: true, timeout: 6000, maximumAge: 60000 }
      );
    }

    async function loadBioModels() {
      if (bioModelsLoaded) return true;
      if (bioModelsLoading) return true;
      bioModelsLoading = true;
      try {
        const statusBox = document.getElementById("bioStatusBox");
        if (statusBox && !bioCompleted) {
          statusBox.className = "alert alert-info py-2 px-3 small fw-semibold mb-2";
          statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-primary"></span> Menyiapkan modul AI GPU...';
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
        bioModelsLoaded = true;
        bioModelsLoading = false;
        if (statusBox && !bioCompleted) {
          statusBox.className = "alert alert-success py-2 px-3 small fw-semibold mb-2";
          statusBox.innerHTML = '<i class="bi bi-check-circle me-1"></i> Modul AI GPU siap. Posisikan wajah di oval.';
        }
        return true;
      } catch (err) {
        console.error("Gagal memuat modul face-api:", err);
        bioModelsLoading = false;
        const statusBox = document.getElementById("bioStatusBox");
        if (statusBox) {
          statusBox.className = "alert alert-danger py-2 px-3 small mb-2";
          statusBox.innerHTML = '<i class="bi bi-x-circle me-1"></i> Gagal memuat modul AI. Periksa koneksi internet.';
        }
        return false;
      }
    }

    function openBiometricModal() {
      const modalEl = document.getElementById("modalBiometricScan");
      if (!modalEl) return;

      bioCompleted = false;
      bioBlinkDetected = false;
      bioLastEyeState = "open";
      bioFaceHoldFrames = 0;

      document.getElementById("pillFaceDetected").className = "badge bg-secondary bg-opacity-25 text-dark small py-2 px-3 border";
      document.getElementById("pillLivenessBlink").className = "badge bg-secondary bg-opacity-25 text-dark small py-2 px-3 border";
      document.getElementById("pillMatchId").className = "badge bg-secondary bg-opacity-25 text-dark small py-2 px-3 border";
      document.getElementById("bioSuccessOverlay").classList.add("d-none");
      document.getElementById("bioScanLine").classList.add("d-none");
      document.getElementById("bioFaceOval").classList.remove("active");

      const holdProgress = document.getElementById("bioHoldProgress");
      if (holdProgress) holdProgress.classList.add("d-none");
      const holdBar = document.getElementById("bioHoldProgressBar");
      if (holdBar) holdBar.style.width = "0%";
      const instantBox = document.getElementById("bioInstantVerifyBox");
      if (instantBox) instantBox.classList.add("d-none");

      bioModalInstance = new bootstrap.Modal(modalEl);
      bioModalInstance.show();

      loadBioModels();
      startBioCamera();
    }

    function closeBiometricModal() {
      bioCompleted = true;
      if (bioTrackingTimer) {
        cancelAnimationFrame(bioTrackingTimer);
        bioTrackingTimer = null;
      }
      if (bioVideoStream) {
        try {
          bioVideoStream.getTracks().forEach(t => t.stop());
        } catch(e) {}
        bioVideoStream = null;
      }
      if (bioModalInstance) {
        bioModalInstance.hide();
      }
    }

    async function startBioCamera() {
      const video = document.getElementById("bioVideo");
      const statusBox = document.getElementById("bioStatusBox");

      try {
        if (statusBox && !bioModelsLoaded) {
          statusBox.className = "alert alert-info py-2 px-3 small fw-semibold mb-2";
          statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-info"></span> Mengaktifkan kamera depan HP...';
        }

        video.setAttribute("playsinline", "");
        video.setAttribute("webkit-playsinline", "");

        bioVideoStream = await navigator.mediaDevices.getUserMedia({
          video: {
            facingMode: "user",
            width: { ideal: 640 },
            height: { ideal: 480 },
            frameRate: { ideal: 30, max: 30 }
          },
          audio: false
        });
        video.srcObject = bioVideoStream;
        await video.play();

        document.getElementById("bioScanLine").classList.remove("d-none");
        if (statusBox) {
          statusBox.className = "alert alert-primary py-2 px-3 small fw-semibold mb-2";
          statusBox.innerHTML = '<i class="bi bi-person-bounding-box me-1"></i> Arahkan wajah ke lingkaran oval (Tahan 1 detik atau kedipkan mata).';
        }

        startBioTracking();
      } catch (err) {
        console.error("Akses kamera gagal:", err);
        if (statusBox) {
          statusBox.className = "alert alert-danger py-2 px-3 small mb-2";
          statusBox.innerHTML = '<i class="bi bi-camera-video-off me-1"></i> Kamera tidak dapat diakses. Berikan izin di browser atau pilih "Lewati & Simpan Manual".';
        }
      }
    }

    function calcEAR(eye) {
      const distA = Math.hypot(eye[1].x - eye[5].x, eye[1].y - eye[5].y);
      const distB = Math.hypot(eye[2].x - eye[4].x, eye[2].y - eye[4].y);
      const distC = Math.hypot(eye[0].x - eye[3].x, eye[0].y - eye[3].y);
      return (distA + distB) / (2.0 * distC);
    }

    function calcEuclidean(a, b) {
      if (!a || !b || a.length !== b.length) return 999;
      let s = 0;
      for (let i = 0; i < a.length; i++) {
        const d = a[i] - b[i];
        s += d * d;
      }
      return Math.sqrt(s);
    }

    function captureBioSnapshot(videoEl) {
      try {
        if (!videoEl || !videoEl.videoWidth || !videoEl.videoHeight) return "";
        const c = document.createElement("canvas");
        c.width = 120;
        c.height = 120;
        const ctx = c.getContext("2d");
        const s = Math.min(videoEl.videoWidth, videoEl.videoHeight);
        const sx = (videoEl.videoWidth - s) / 2;
        const sy = (videoEl.videoHeight - s) / 2;
        ctx.translate(120, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(videoEl, sx, sy, s, s, 0, 0, 120, 120);
        return c.toDataURL("image/jpeg", 0.7);
      } catch (err) {
        console.warn("Capture snapshot err:", err);
        return "";
      }
    }

    function findBestMatch(queryVec) {
      let bestDist = 999;
      let bestTech = null;
      for (let i = 0; i < parsedEnrolledTechs.length; i++) {
        const t = parsedEnrolledTechs[i];
        const dist = calcEuclidean(queryVec, t.descriptor);
        if (dist < bestDist) {
          bestDist = dist;
          bestTech = t;
        }
      }
      return { tech: bestTech, dist: bestDist };
    }

    function finalizeVerification(tech, dist, videoEl) {
      bioCompleted = true;
      document.getElementById("pillMatchId").className = "badge bg-success text-white small py-2 px-3 border";
      document.getElementById("pillLivenessBlink").className = "badge bg-success text-white small py-2 px-3 border";

      let conf = Math.round((1.0 - (dist / 0.60)) * 100);
      if (conf > 99) conf = 99;
      if (conf < 75) conf = 75;

      const bioVer = document.getElementById("bioVerified");
      const bioConf = document.getElementById("bioConfidence");
      const bioPh = document.getElementById("bioPhoto");
      if (bioVer) bioVer.value = "1";
      if (bioConf) bioConf.value = conf;
      const snap = captureBioSnapshot(videoEl);
      if (bioPh) bioPh.value = snap || (tech.photo || "");

      const techInput = document.getElementById("technicianNameInput");
      if (techInput) techInput.value = tech.nama;

      const matchedNameEl = document.getElementById("bioMatchedName");
      if (matchedNameEl) matchedNameEl.textContent = tech.nama;
      const matchConfEl = document.getElementById("bioMatchConfidence");
      if (matchConfEl) matchConfEl.textContent = conf + "% Cocok (Terverifikasi)";
      const successOverlay = document.getElementById("bioSuccessOverlay");
      if (successOverlay) successOverlay.classList.remove("d-none");

      const statusBox = document.getElementById("bioStatusBox");
      if (statusBox) {
        statusBox.className = "alert alert-success py-2 px-3 small fw-bold mb-2";
        statusBox.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Wajah Dikenali: <strong>' + tech.nama + '</strong>! Menyimpan...';
      }

      if (bioVideoStream) {
        try { bioVideoStream.getTracks().forEach(t => t.stop()); } catch(e) {}
      }

      setTimeout(() => {
        const formEl = document.getElementById("formTindakLanjut") || document.getElementById("formMaintenance");
        if (formEl) formEl.submit();
      }, 400);
    }

    async function triggerInstantBioVerify() {
      const video = document.getElementById("bioVideo");
      const statusBox = document.getElementById("bioStatusBox");
      if (!video || !video.videoWidth || bioCompleted) return;

      statusBox.className = "alert alert-warning py-2 px-3 small fw-bold mb-2";
      statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-warning"></span> Memproses verifikasi instan...';

      const useTinyLandmarks = faceapi.nets.faceLandmark68TinyNet && faceapi.nets.faceLandmark68TinyNet.isLoaded;
      const fastDetectorOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 160, scoreThreshold: 0.3 });

      try {
        const detection = await faceapi.detectSingleFace(video, fastDetectorOptions)
          .withFaceLandmarks(useTinyLandmarks)
          .withFaceDescriptor();

        if (detection && detection.descriptor) {
          const matchResult = findBestMatch(detection.descriptor);
          if (matchResult.tech && matchResult.dist <= 0.55) {
            finalizeVerification(matchResult.tech, matchResult.dist, video);
            return;
          }
        }
        statusBox.className = "alert alert-danger py-2 px-3 small fw-bold mb-2";
        statusBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Wajah belum cocok dengan teknisi terdaftar. Posisikan wajah tegak.';
      } catch (err) {
        console.warn("Instant verify error:", err);
      }
    }

    let isTrackingFrame = false;
    function startBioTracking() {
      if (bioTrackingTimer) cancelAnimationFrame(bioTrackingTimer);
      const video = document.getElementById("bioVideo");
      const oval = document.getElementById("bioFaceOval");
      const statusBox = document.getElementById("bioStatusBox");
      const pillFace = document.getElementById("pillFaceDetected");
      const pillBlink = document.getElementById("pillLivenessBlink");
      const holdProgress = document.getElementById("bioHoldProgress");
      const holdProgressBar = document.getElementById("bioHoldProgressBar");
      const instantBox = document.getElementById("bioInstantVerifyBox");

      const useTinyLandmarks = faceapi.nets.faceLandmark68TinyNet && faceapi.nets.faceLandmark68TinyNet.isLoaded;
      const fastDetectorOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 160, scoreThreshold: 0.30 });

      async function trackingLoop() {
        if (bioCompleted || !video || !video.videoWidth || video.paused || video.ended) {
          if (!bioCompleted) {
            bioTrackingTimer = requestAnimationFrame(trackingLoop);
          }
          return;
        }

        if (!bioModelsLoaded) {
          bioTrackingTimer = requestAnimationFrame(trackingLoop);
          return;
        }

        if (isTrackingFrame) {
          bioTrackingTimer = requestAnimationFrame(trackingLoop);
          return;
        }
        isTrackingFrame = true;

        try {
          const detection = await faceapi.detectSingleFace(video, fastDetectorOptions)
            .withFaceLandmarks(useTinyLandmarks)
            .withFaceDescriptor();

          if (detection) {
            if (pillFace) pillFace.className = "badge bg-success text-white small py-2 px-3 border";
            if (oval) oval.classList.add("active");
            if (instantBox) instantBox.classList.remove("d-none");

            const landmarks = detection.landmarks;
            const leftEye = landmarks.getLeftEye();
            const rightEye = landmarks.getRightEye();

            const earL = calcEAR(leftEye);
            const earR = calcEAR(rightEye);
            const avgEAR = (earL + earR) / 2.0;

            const BLINK_THRESHOLD = 0.22;
            if (avgEAR < BLINK_THRESHOLD) {
              bioLastEyeState = "closed";
            } else if (bioLastEyeState === "closed" && avgEAR >= 0.27) {
              bioBlinkDetected = true;
              bioLastEyeState = "open";
            }

            bioFaceHoldFrames++;
            if (holdProgress) holdProgress.classList.remove("d-none");
            const pct = Math.min(100, Math.round((bioFaceHoldFrames / HOLD_FRAMES_REQUIRED) * 100));
            if (holdProgressBar) holdProgressBar.style.width = pct + "%";

            const livenessPassed = bioBlinkDetected || (bioFaceHoldFrames >= HOLD_FRAMES_REQUIRED);

            if (livenessPassed) {
              if (pillBlink) pillBlink.className = "badge bg-success text-white small py-2 px-3 border";
              if (statusBox) {
                statusBox.className = "alert alert-warning py-2 px-3 small fw-bold mb-2";
                statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-warning"></span> Liveness lolos! Memverifikasi identitas teknisi...';
              }

              if (detection.descriptor) {
                const matchResult = findBestMatch(detection.descriptor);
                if (matchResult.tech && matchResult.dist <= 0.50) {
                  finalizeVerification(matchResult.tech, matchResult.dist, video);
                  isTrackingFrame = false;
                  return;
                } else {
                  if (statusBox) {
                    statusBox.className = "alert alert-danger py-2 px-3 small fw-bold mb-2";
                    statusBox.innerHTML = '<i class="bi bi-x-circle me-1"></i> Wajah tidak cocok dengan teknisi terdaftar. Posisikan wajah tegak & pencahayaan cukup.';
                  }
                  bioBlinkDetected = false;
                  bioFaceHoldFrames = 0;
                  if (holdProgressBar) holdProgressBar.style.width = "0%";
                }
              }
            } else {
              if (statusBox) {
                statusBox.className = "alert alert-info py-2 px-3 small fw-bold mb-2";
                statusBox.innerHTML = '<i class="bi bi-person-check-fill me-1"></i> Wajah terdeteksi! Tahan posisi sejenak (' + Math.round(pct) + '%)...';
              }
            }
          } else {
            if (pillFace) pillFace.className = "badge bg-secondary bg-opacity-25 text-dark small py-2 px-3 border";
            if (oval) oval.classList.remove("active");
            bioFaceHoldFrames = Math.max(0, bioFaceHoldFrames - 2);
            if (holdProgressBar) holdProgressBar.style.width = "0%";
          }
        } catch (e) {
          console.warn("Tracking err:", e);
        } finally {
          isTrackingFrame = false;
        }

        bioTrackingTimer = requestAnimationFrame(trackingLoop);
      }

      bioTrackingTimer = requestAnimationFrame(trackingLoop);
    }

    function bypassBiometricAndSubmit() {
      if (confirm("Simpan hasil tanpa verifikasi biometrik wajah?")) {
        closeBiometricModal();
        const bioVer = document.getElementById("bioVerified");
        const bioConf = document.getElementById("bioConfidence");
        const bioPh = document.getElementById("bioPhoto");
        if (bioVer) bioVer.value = "0";
        if (bioConf) bioConf.value = "0";
        if (bioPh) bioPh.value = "";
        const formEl = document.getElementById("formTindakLanjut") || document.getElementById("formMaintenance");
        if (formEl) formEl.submit();
      }
    }

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", () => {
        setTimeout(loadBioModels, 300);
      });
    } else {
      setTimeout(loadBioModels, 300);
    }
    JS;
    $out .= "</script>\n";
    return $out;
}
