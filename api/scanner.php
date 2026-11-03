<?php
require __DIR__ . '/bootstrap.php';
// Pemindai QR Lapangan: Dapat diakses langsung melalui HP tanpa perlu login akun.
// Token QR yang dipindai langsung diarahkan ke form checklist atau kartu kontrol aset.

$head = '<style>
.scanner-mobile-card {
  max-width: 520px;
  margin: 0 auto;
  background-color: #FFFFFF;
  border: 1px solid #D1D5D8;
  border-radius: 20px;
  padding: 24px 20px;
  color: #1B2A3A;
  box-shadow: 0 10px 30px -5px rgba(14, 92, 88, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
}

[data-bs-theme="dark"] .scanner-mobile-card {
  background-color: #152232;
  border-color: #2D3D4E;
  color: #FFFFFF;
  box-shadow: 0 12px 36px -8px rgba(0, 0, 0, 0.45);
}

.scanner-viewport-wrapper {
  position: relative;
  width: 100%;
  margin: 16px auto;
  border-radius: 16px;
  overflow: hidden;
  background-color: #0B131E;
  box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.8), 0 2px 8px rgba(0, 0, 0, 0.06);
  border: 2px solid #0E5C58;
  min-height: 300px;
}

#reader {
  width: 100% !important;
  border: none !important;
  background: #0B131E !important;
  min-height: 300px;
}

#reader video {
  border-radius: 12px;
  object-fit: cover !important;
  width: 100% !important;
  height: auto !important;
  max-height: 52vh !important;
  background: #0B131E;
}

#reader__scan_region {
  background: transparent !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
}

/* Corner Scan Reticle - Kombai Teal & Emerald */
.scan-corner-frame {
  position: absolute;
  inset: 22px;
  pointer-events: none;
  z-index: 5;
}

.scan-corner {
  position: absolute;
  width: 28px;
  height: 28px;
  border-color: #10B981;
  border-style: solid;
}

.corner-tl { top: 0; left: 0; border-width: 4px 0 0 4px; border-top-left-radius: 8px; }
.corner-tr { top: 0; right: 0; border-width: 4px 4px 0 0; border-top-right-radius: 8px; }
.corner-bl { bottom: 0; left: 0; border-width: 0 0 4px 4px; border-bottom-left-radius: 8px; }
.corner-br { bottom: 0; right: 0; border-width: 0 4px 4px 0; border-bottom-right-radius: 8px; }

.scan-laser-line {
  position: absolute;
  top: 25%;
  left: 20px;
  right: 20px;
  height: 2.5px;
  background: linear-gradient(90deg, transparent, #10B981, #5EEAD4, #10B981, transparent);
  box-shadow: 0 0 12px rgba(16, 185, 129, 0.9);
  pointer-events: none;
  z-index: 6;
  animation: scanLaserAnim 2.2s infinite ease-in-out;
}

@keyframes scanLaserAnim {
  0% { top: 15%; opacity: 0; }
  20% { opacity: 1; }
  80% { opacity: 1; }
  100% { top: 85%; opacity: 0; }
}

.scanner-tool-btn {
  background: #F8FAFC;
  border: 1px solid #D1D5D8;
  color: #1B2A3A;
  border-radius: 12px;
  min-height: 44px;
  padding: 8px 14px;
  font-size: 0.82rem;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  flex: 1 1 calc(50% - 8px);
}

.scanner-tool-btn:hover {
  background: #E6F4F2;
  border-color: #0E5C58;
  color: #0E5C58;
}

.scanner-tool-btn.active {
  background: #0E5C58 !important;
  border-color: #0E5C58 !important;
  color: #FFFFFF !important;
  box-shadow: 0 2px 8px rgba(14, 92, 88, 0.3);
}

[data-bs-theme="dark"] .scanner-tool-btn {
  background: #1B2A3A;
  border-color: #334155;
  color: #E2E8F0;
}

[data-bs-theme="dark"] .scanner-tool-btn:hover {
  background: #0E5C58;
  border-color: #10B981;
  color: #FFFFFF;
}

.btn-zoom-pill {
  font-size: 0.78rem;
  font-weight: 600;
  min-height: 36px;
  padding: 4px 14px;
  border-radius: 20px;
  background: #F8FAFC;
  border: 1px solid #D1D5D8;
  color: #53595F;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-zoom-pill:hover, .btn-zoom-pill.active {
  background: #0E5C58 !important;
  border-color: #0E5C58 !important;
  color: #FFFFFF !important;
}

.pulse-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: #10B981;
  margin-right: 6px;
  box-shadow: 0 0 6px #10B981;
  animation: pulseDotAnim 1.8s infinite;
}

@keyframes pulseDotAnim {
  0% { transform: scale(0.9); opacity: 0.7; }
  50% { transform: scale(1.3); opacity: 1; }
  100% { transform: scale(0.9); opacity: 0.7; }
}

.status-pill-box {
  background-color: #F8FAFC;
  border: 1px solid #E2E8F0;
  border-radius: 12px;
  padding: 10px 14px;
  font-size: 0.82rem;
  color: #53595F;
}

[data-bs-theme="dark"] .status-pill-box {
  background-color: #1B2A3A;
  border-color: #334155;
  color: #94A3B8;
}

.manual-input-box {
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  border-radius: 14px;
  padding: 14px 16px;
}

[data-bs-theme="dark"] .manual-input-box {
  background: #1B2A3A;
  border-color: #334155;
}
</style>';

$body = '
<div class="row justify-content-center py-2 px-1">
  <div class="col-12 col-md-8 col-lg-6 col-xl-5">
    <div class="scanner-mobile-card">
      
      <!-- Header Mobile Bar -->
      <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-2" style="border-color: #E2E8F0 !important;">
        <div>
          <div class="d-inline-flex align-items-center gap-1.5 px-2.5 py-0.5 rounded-pill mb-1 fw-bold text-uppercase" style="background-color: #E6F4F2; color: #0E5C58; font-size: 0.65rem; letter-spacing: 0.05em; border: 1px solid #A7E2DC;">
            <i class="bi bi-shield-check"></i> PT BPR MITRATAMA ARTHABUANA
          </div>
          <h1 class="h5 mb-0 fw-bold d-flex align-items-center" style="color: #1B2A3A;">
            <i class="bi bi-qr-code-scan me-2" style="color: #0E5C58;"></i> Pindai QR Aset
          </h1>
        </div>
        <span class="badge rounded-pill d-inline-flex align-items-center px-2.5 py-1.5 fw-semibold" style="background-color: #E6F4F2; color: #0E5C58; border: 1px solid #A7E2DC; font-size: 0.72rem;">
          <span class="pulse-dot"></span> Kamera Aktif
        </span>
      </div>

      <div class="text-center small my-2.5" style="font-size: 0.80rem; color: #53595F;">
        Arahkan kamera ke stiker label QR komputer untuk memeriksa informasi aset atau mengisi checklist pemeliharaan bulanan.
      </div>

      <!-- Viewport Kamera -->
      <div class="scanner-viewport-wrapper" id="scannerViewport">
        <div class="scan-corner-frame">
          <div class="scan-corner corner-tl"></div>
          <div class="scan-corner corner-tr"></div>
          <div class="scan-corner corner-bl"></div>
          <div class="scan-corner corner-br"></div>
          <div class="scan-laser-line" id="scanLaserLine"></div>
        </div>
        <div id="reader"></div>

        <!-- Tombol Start Manual (jika browser butuh izin ketukan pengguna / iOS Safari) -->
        <div id="manualStartBox" class="text-center p-4 d-none" style="position: absolute; inset: 0; background: #0B131E; z-index: 8;">
          <div class="d-flex flex-column align-items-center justify-content-center h-100">
            <div class="rounded-circle p-3 mb-2" style="background: rgba(14, 92, 88, 0.2);">
              <i class="bi bi-camera-video-fill fs-1" style="color: #5EEAD4;"></i>
            </div>
            <h6 class="text-white fw-bold mb-1">Kamera Memerlukan Izin</h6>
            <p class="small text-light text-opacity-75 mb-3" style="font-size: 0.78rem; max-width: 280px;">Ketuk tombol di bawah untuk mengizinkan browser menyalakan kamera scanner:</p>
            <button type="button" class="btn text-white fw-bold px-4 py-2.5 rounded-pill shadow-sm min-h-[44px] d-inline-flex align-items-center justify-content-center gap-1.5" style="background-color: #0E5C58; border-color: #0E5C58;" onclick="requestStartCamera()">
              <i class="bi bi-camera"></i> <span>Buka Kamera Scanner</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Toolbar Kontrol Kamera: Senter, Balik Kamera, Foto Langsung, Unggah Galeri -->
      <div class="d-flex align-items-center justify-content-center flex-wrap gap-2 mb-2">
        <button type="button" class="scanner-tool-btn" id="btnToggleTorch" onclick="toggleCameraTorch()">
          <i class="bi bi-lightning-charge"></i> <span>Flash</span>
        </button>
        <button type="button" class="scanner-tool-btn" id="btnSwitchCam" onclick="switchCamera()">
          <i class="bi bi-camera-reverse"></i> <span>Balik Kamera</span>
        </button>
        <button type="button" class="scanner-tool-btn" onclick="document.getElementById(\'nativeCameraInput\').click()">
          <i class="bi bi-camera-fill" style="color: #0E5C58;"></i> <span>Foto Langsung</span>
        </button>
        <button type="button" class="scanner-tool-btn" onclick="document.getElementById(\'galleryInput\').click()">
          <i class="bi bi-image"></i> <span>Pilih dari Galeri</span>
        </button>
        <!-- Native Camera Input (langsung membuka app kamera HP) -->
        <input type="file" id="nativeCameraInput" accept="image/*" capture="environment" class="d-none" onchange="handleGalleryScan(this)">
        <!-- File Picker Input (pilih dari galeri foto) -->
        <input type="file" id="galleryInput" accept="image/*" class="d-none" onchange="handleGalleryScan(this)">
      </div>

      <!-- Zoom Slider / Buttons jika didukung kamera -->
      <div id="zoomControlWrapper" class="d-none text-center my-2">
        <span class="small me-1 fw-semibold" style="color: #53595F;"><i class="bi bi-search"></i> Zoom:</span>
        <div id="zoomButtonsContainer" class="d-inline-flex gap-1.5"></div>
      </div>

      <!-- Status Scanner -->
      <div id="scannerStatus" class="status-pill-box text-center mt-2.5">
        <span class="spinner-border spinner-border-sm me-1" style="color: #0E5C58;"></span> Menghubungkan ke kamera smartphone...
      </div>

      <!-- Input Manual Token (Jika QR Rusak/Kamera Terhalang) -->
      <div class="manual-input-box mt-3 pt-3">
        <div class="d-flex align-items-center justify-content-between mb-1.5">
          <span class="fw-bold d-inline-flex align-items-center gap-1" style="font-size: 0.74rem; color: #1B2A3A;">
            <i class="bi bi-keyboard" style="color: #0E5C58;"></i> Ketik Nomor / Token Manual
          </span>
          <span class="text-secondary small" style="font-size: 0.68rem;">(Jika stiker QR robek)</span>
        </div>
        <form onsubmit="return handleManualToken(event)">
          <div class="input-group">
            <input type="text" class="form-control font-monospace" id="manualTokenInput" placeholder="Contoh: 01.5.00492 atau token..." style="height: 44px; border-color: #D1D5D8; font-size: 0.84rem;" required>
            <button class="btn text-white fw-bold px-3.5 d-inline-flex align-items-center justify-content-center gap-1" style="background-color: #0E5C58; border-color: #0E5C58; min-height: 44px;" type="submit">
              <span>Buka</span> <i class="bi bi-arrow-right"></i>
            </button>
          </div>
        </form>
      </div>

      <!-- Tautan Cepat -->
      <div class="d-flex justify-content-between align-items-center mt-3 pt-2 text-muted" style="font-size: 0.75rem;">
        <span class="d-inline-flex align-items-center gap-1" style="color: #53595F;">
          <i class="bi bi-shield-check" style="color: #10B981;"></i> Mode Lapangan Bebas Login
        </span>
        ' . (is_logged_in() 
            ? '<a href="' . e(module_url('dashboard.php')) . '" class="fw-bold text-decoration-none d-inline-flex align-items-center gap-1" style="color: #0E5C58;"><i class="bi bi-speedometer2"></i> Dashboard</a>' 
            : '<a href="' . e(module_url('login.php')) . '" class="fw-bold text-decoration-none d-inline-flex align-items-center gap-1" style="color: #0E5C58;" title="Akses Admin Portal"><i class="bi bi-lock"></i> Portal Admin</a>') . '
      </div>

    </div>
  </div>
</div>';

$html5QrcodeUrl = module_url('html5-qrcode.min.js');

$script = '<script src="' . e($html5QrcodeUrl) . '"></script>'
    . '<script>if(typeof Html5Qrcode === "undefined"){ document.write(\'<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"><\\/script>\'); }</script>'
    . <<<'SCANNER_JS_CODE'
<script>
let html5QrCode = null;
let currentCameraTrack = null;
let isTorchOn = false;
let currentFacingMode = "environment";
let isScanLocked = false;
let isStarting = false;

function playScanAudioFeedback() {
  try {
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (AudioContext) {
      const ctx = new AudioContext();
      if (ctx.state === "suspended") ctx.resume();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = "sine";
      osc.frequency.setValueAtTime(880, ctx.currentTime);
      gain.gain.setValueAtTime(0.25, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.12);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.12);
    }
  } catch (e) {}
}

function triggerScanHapticFeedback() {
  try {
    if (navigator.vibrate) {
      navigator.vibrate([100, 40, 100]);
    }
  } catch (e) {}
}

function resolveScanDestination(rawInput) {
  var text = (rawInput || "").trim();
  if (!text) return "";

  var qrBase = window.QR_BASE_URL || "https://it.bankmitra.co.id";

  // 1. Kartu fisik lama dengan link Canva (misal: https://canva.link/axqdgjgztd1uu3r)
  if (text.includes("canva.link") || text.includes("canva.com")) {
    return qrBase + "/kartu_detail.php?barcode=" + encodeURIComponent(text);
  }

  // 2. URL lengkap berprotokol (http:// atau https://)
  if (text.startsWith("http://") || text.startsWith("https://")) {
    try {
      var parsedUrl = new URL(text);
      var tokenVal = parsedUrl.searchParams.get("t") || parsedUrl.searchParams.get("token");
      if (tokenVal) {
        return qrBase + "/scan.php?t=" + encodeURIComponent(tokenVal.trim());
      }
      var idVal = parsedUrl.searchParams.get("id");
      var rekVal = parsedUrl.searchParams.get("rek") || parsedUrl.searchParams.get("no_rek");
      var pathName = parsedUrl.pathname.replace(/^\/api\//, "/").replace(/^\//, "");

      // Jika URL mengarah ke kartu_detail (dari kartu fisik lama yang memakai IP LAN / domain lama)
      if (pathName.includes("kartu_detail") || idVal || rekVal) {
        return qrBase + "/kartu_detail.php" + (parsedUrl.search || "");
      }

      // Jika URL mengarah ke scan.php
      if (pathName.includes("scan")) {
        return qrBase + "/scan.php" + (parsedUrl.search || "");
      }

      if (pathName) {
        if (!pathName.endsWith(".php") && !pathName.includes(".")) {
          pathName += ".php";
        }
        return qrBase + "/" + pathName + (parsedUrl.search || "");
      }
    } catch (e) {}
  }

  // 3. Jika URL relatif scan.php
  if (text.indexOf("scan.php?t=") !== -1 || text.indexOf("scan.php?") !== -1) {
    var queryPart = text.split("?")[1] || "";
    var searchParams = new URLSearchParams(queryPart);
    var tVal = searchParams.get("t") || searchParams.get("token");
    if (tVal) {
      return qrBase + "/scan.php?t=" + encodeURIComponent(tVal.trim());
    }
  }

  // 4. Jika URL relatif kartu_detail.php
  if (text.indexOf("kartu_detail.php?") !== -1 || text.indexOf("kartu_detail?") !== -1) {
    return qrBase + "/" + text.replace(/^\/+/, "");
  }

  // 5. Default: Arahkan ke scan.php di domain resmi (scan.php otomatis meneruskan ke kartu_detail jika ini kartu fisik inventaris)
  return qrBase + "/scan.php?t=" + encodeURIComponent(text);
}

function onScanSuccess(decodedText) {
  if (isScanLocked) return;
  isScanLocked = true;

  playScanAudioFeedback();
  triggerScanHapticFeedback();

  const statusEl = document.getElementById("scannerStatus");
  if (statusEl) {
    statusEl.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-lightning-charge-fill me-1"></i> QR Terdeteksi! Membuka data aset...</span>';
  }

  const laser = document.getElementById("scanLaserLine");
  if (laser) {
    laser.style.backgroundColor = "#10B981";
    laser.style.boxShadow = "0 0 16px #10B981";
  }

  try {
    if (html5QrCode && html5QrCode.isScanning) {
      html5QrCode.stop().catch(function() {});
    }
  } catch (e) {}

  setTimeout(function() {
    var dest = resolveScanDestination(decodedText);
    if (dest) {
      window.location.href = dest;
    }
  }, 100);
}

function handleManualToken(e) {
  e.preventDefault();
  const val = document.getElementById("manualTokenInput").value.trim();
  if (val) {
    var dest = resolveScanDestination(val);
    if (dest) {
      window.location.href = dest;
    }
  }
  return false;
}

function handleGalleryScan(inputEl) {
  if (!inputEl || !inputEl.files || inputEl.files.length === 0) return;
  const file = inputEl.files[0];
  const statusEl = document.getElementById("scannerStatus");
  if (statusEl) {
    statusEl.innerHTML = '<span class="text-info"><span class="spinner-border spinner-border-sm me-1"></span> Menganalisis gambar...</span>';
  }

  runHtml5QrCodeScanFile(file);
}

function runHtml5QrCodeScanFile(file) {
  const statusEl = document.getElementById("scannerStatus");
  try {
    const fileScanner = new Html5Qrcode("reader");
    fileScanner.scanFile(file, true)
      .then(function(decodedText) {
        onScanSuccess(decodedText);
      })
      .catch(function(err) {
        if (statusEl) {
          statusEl.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-circle me-1"></i> QR tidak terbaca pada foto. Pastikan stiker QR terlihat jelas dan tegak, lalu coba lagi.</span>';
        }
      });
  } catch(e) {
    if (statusEl) {
      statusEl.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i> Gagal memproses gambar: ' + (e.message || e) + '</span>';
    }
  }
}

function applyCameraCapabilities() {
  const video = document.querySelector("#reader video");
  if (!video || !video.srcObject) return;

  const tracks = video.srcObject.getVideoTracks();
  if (!tracks || tracks.length === 0) return;

  currentCameraTrack = tracks[0];
  try {
    const caps = (typeof currentCameraTrack.getCapabilities === "function") ? currentCameraTrack.getCapabilities() : {};
    
    // Aktifkan continuous auto focus jika didukung kamera HP
    if (caps.focusMode && (caps.focusMode.includes("continuous") || caps.focusMode === "continuous")) {
      currentCameraTrack.applyConstraints({
        advanced: [{ focusMode: "continuous" }]
      }).catch(function() {});
    }

    // Check Torch capability
    const torchBtn = document.getElementById("btnToggleTorch");
    if (torchBtn && caps.torch) {
      torchBtn.classList.remove("d-none");
    }

    // Check Zoom capability
    if (caps.zoom) {
      setupZoomControls(currentCameraTrack, caps.zoom);
    }
  } catch (e) {}
}

function toggleCameraTorch() {
  if (!currentCameraTrack) return;
  try {
    const caps = (typeof currentCameraTrack.getCapabilities === "function") ? currentCameraTrack.getCapabilities() : {};
    if (!caps.torch) {
      alert("Lampu flash / torch tidak didukung oleh browser pada kamera ini.");
      return;
    }
    isTorchOn = !isTorchOn;
    currentCameraTrack.applyConstraints({
      advanced: [{ torch: isTorchOn }]
    }).then(function() {
      const btn = document.getElementById("btnToggleTorch");
      if (btn) {
        if (isTorchOn) {
          btn.classList.add("active");
          btn.innerHTML = '<i class="bi bi-lightning-fill"></i> Flash ON';
        } else {
          btn.classList.remove("active");
          btn.innerHTML = '<i class="bi bi-lightning"></i> Flash';
        }
      }
    }).catch(function(e) {
      isTorchOn = !isTorchOn;
    });
  } catch (e) {}
}

function setupZoomControls(track, zoomCap) {
  const wrapper = document.getElementById("zoomControlWrapper");
  const container = document.getElementById("zoomButtonsContainer");
  if (!wrapper || !container) return;

  container.innerHTML = "";
  const minZ = zoomCap.min || 1.0;
  const maxZ = zoomCap.max || 1.0;

  const presets = [];
  if (minZ < 0.9) presets.push(Number(minZ.toFixed(1)));
  if (!presets.includes(1.0) && maxZ >= 1.0) presets.push(1.0);
  if (maxZ >= 2.0 && !presets.includes(2.0)) presets.push(2.0);

  if (presets.length <= 1 && maxZ > minZ) {
    presets.push(Number(Math.min(minZ + 1.0, maxZ).toFixed(1)));
  }

  presets.forEach(function(val) {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "btn-zoom-pill" + (val === minZ || (minZ >= 1.0 && val === 1.0) ? " active" : "");
    btn.textContent = val + "x";
    btn.onclick = function() {
      if (track) {
        track.applyConstraints({ advanced: [{ zoom: val }] }).catch(function() {});
      }
      container.querySelectorAll(".btn-zoom-pill").forEach(function(b) { b.classList.remove("active"); });
      btn.classList.add("active");
    };
    container.appendChild(btn);
  });

  wrapper.classList.remove("d-none");
}

async function cleanupScanner() {
  if (html5QrCode) {
    try {
      if (html5QrCode.isScanning) {
        await html5QrCode.stop();
      }
    } catch (e) {}
    try {
      html5QrCode.clear();
    } catch (e) {}
    html5QrCode = null;
  }
  const readerEl = document.getElementById("reader");
  if (readerEl) {
    readerEl.innerHTML = "";
  }
}

function switchCamera() {
  currentFacingMode = (currentFacingMode === "environment") ? "user" : "environment";
  const statusEl = document.getElementById("scannerStatus");
  if (statusEl) {
    statusEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1 text-primary"></span> Membalik kamera...';
  }
  startCameraScanner(true);
}

async function startCameraScanner(isUserInitiated) {
  if (isStarting) return;
  isStarting = true;

  const manualBox = document.getElementById("manualStartBox");
  const statusEl = document.getElementById("scannerStatus");

  // 1. Cek Protokol Keamanan (Kamera WAJIB HTTPS atau localhost)
  if (!window.isSecureContext && location.hostname !== "localhost" && location.hostname !== "127.0.0.1") {
    isStarting = false;
    if (location.protocol === "http:" && location.hostname === "it.bankmitra.co.id") {
      window.location.replace("https://" + location.host + location.pathname + location.search);
      return;
    }
    if (statusEl) {
      statusEl.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-shield-x me-1"></i> Kamera membutuhkan koneksi HTTPS aman. Silakan buka melalui <a href="https://it.bankmitra.co.id/scanner" class="text-white text-decoration-underline fw-bold">https://it.bankmitra.co.id/scanner</a></span>';
    }
    if (manualBox) manualBox.classList.remove("d-none");
    return;
  }

  // 2. Cek Html5Qrcode Library
  if (typeof Html5Qrcode === "undefined") {
    isStarting = false;
    if (statusEl) {
      statusEl.innerHTML = '<span class="text-warning"><span class="spinner-border spinner-border-sm me-1 text-primary"></span> Menyiapkan modul scanner...</span>';
    }
    setTimeout(function() { startCameraScanner(isUserInitiated); }, 500);
    return;
  }

  if (statusEl) {
    statusEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1 text-primary"></span> Menghubungkan ke kamera HP...';
  }

  // Bersihkan instans sebelumnya agar aman dari state collision
  await cleanupScanner();

  // 3. Pada iOS Safari / Mobile, coba cek izin getUserMedia terlebih dahulu
  if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
    try {
      const testStream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: currentFacingMode } }
      });
      // Tutup test stream segera setelah izin didapat
      testStream.getTracks().forEach(function(tr) { tr.stop(); });
    } catch (permErr) {
      console.warn("Izin kamera awal butuh interaksi:", permErr);
      if (!isUserInitiated) {
        isStarting = false;
        if (manualBox) manualBox.classList.remove("d-none");
        if (statusEl) {
          statusEl.innerHTML = '<span class="text-warning"><i class="bi bi-camera me-1"></i> Ketuk tombol <b>"Buka Kamera Scanner"</b> untuk mengaktifkan kamera HP Anda.</span>';
        }
        return;
      }
    }
  }

  try {
    const readerEl = document.getElementById("reader");
    if (!readerEl) {
      isStarting = false;
      return;
    }

    html5QrCode = new Html5Qrcode("reader", {
      experimentalFeatures: {
        useBarCodeDetectorIfSupported: false
      },
      verbose: false
    });

    const qrConfig = {
      fps: 15,
      qrbox: function(viewfinderWidth, viewfinderHeight) {
        var minEdge = Math.min(viewfinderWidth, viewfinderHeight);
        var edge = Math.floor(minEdge * 0.72);
        return {
          width: Math.max(180, Math.min(280, edge)),
          height: Math.max(180, Math.min(280, edge))
        };
      },
      aspectRatio: 1.0,
      disableFlip: false
    };

    const cameraParam = { facingMode: currentFacingMode };

    await html5QrCode.start(
      cameraParam,
      qrConfig,
      onScanSuccess,
      function() {}
    );

    isStarting = false;
    if (manualBox) manualBox.classList.add("d-none");
    if (statusEl) {
      statusEl.innerHTML = '<span class="fw-bold" style="color: #0E5C58;"><i class="bi bi-camera-fill me-1 text-success"></i> Scanner aktif &bull; Arahkan ke stiker QR</span>';
    }

    // Pastikan video mobile memiliki atribut playsinline & muted (krusial di iOS Safari/Chrome)
    const video = document.querySelector("#reader video");
    if (video) {
      video.setAttribute("playsinline", "true");
      video.setAttribute("webkit-playsinline", "true");
      video.setAttribute("muted", "true");
      video.muted = true;
      video.play().catch(function() {});
    }

    setTimeout(applyCameraCapabilities, 300);

  } catch (err) {
    isStarting = false;
    console.error("Gagal memulai scanner:", err);
    await cleanupScanner();
    if (manualBox) manualBox.classList.remove("d-none");

    if (statusEl) {
      const errStr = String(err || "");
      if (errStr.includes("NotAllowedError") || errStr.includes("Permission") || errStr.includes("denied")) {
        statusEl.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-shield-x me-1"></i> Izin kamera diblokir browser. Di iPhone: Buka <b>Pengaturan HP > Safari / Chrome > Kamera > Izinkan</b>. Atau gunakan tombol <b>"Foto Langsung"</b> di bawah.</span>';
      } else if (errStr.includes("NotFoundError") || errStr.includes("DevicesNotFoundError")) {
        statusEl.innerHTML = '<span class="text-warning fw-bold"><i class="bi bi-camera-video-off me-1"></i> Perangkat kamera tidak ditemukan. Gunakan tombol <b>"Foto Langsung"</b> atau ketik token di bawah.</span>';
      } else if (errStr.includes("NotReadableError") || errStr.includes("TrackStartError")) {
        statusEl.innerHTML = '<span class="text-warning fw-bold"><i class="bi bi-exclamation-triangle me-1"></i> Kamera sedang dipakai oleh aplikasi lain. Tutup aplikasi lain lalu coba lagi.</span>';
      } else {
        statusEl.innerHTML = '<span class="text-warning"><i class="bi bi-camera me-1"></i> Ketuk tombol <b>"Buka Kamera Scanner"</b> di atas atau <b>"Foto Langsung"</b> di bawah.</span>';
      }
    }
  }
}

function requestStartCamera() {
  const manualBox = document.getElementById("manualStartBox");
  if (manualBox) manualBox.classList.add("d-none");
  const statusEl = document.getElementById("scannerStatus");
  if (statusEl) {
    statusEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1 text-primary"></span> Meminta izin kamera ke browser...';
  }
  isStarting = false;
  startCameraScanner(true);
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", function() {
    startCameraScanner(false);
  });
} else {
  startCameraScanner(false);
}
</script>
SCANNER_JS_CODE;

render_page('Pindai QR Komputer', $body, $head, $script, false);
