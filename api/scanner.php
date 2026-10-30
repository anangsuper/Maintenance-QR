<?php
require __DIR__ . '/bootstrap.php';
// Pemindai QR Lapangan: Dapat diakses langsung melalui HP tanpa perlu login akun.
// Token QR yang dipindai langsung diarahkan ke form checklist atau kartu kontrol aset.

$head = '<style>
.scanner-mobile-card {
  max-width: 500px;
  margin: 0 auto;
  background: linear-gradient(180deg, #051329 0%, #0A1E38 100%);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 20px;
  padding: 20px 18px;
  color: #FFFFFF;
  box-shadow: 0 16px 36px -10px rgba(5, 19, 41, 0.65);
}

.scanner-viewport-wrapper {
  position: relative;
  width: 100%;
  margin: 14px auto;
  border-radius: 14px;
  overflow: hidden;
  background-color: #000000;
  box-shadow: inset 0 0 16px rgba(0, 0, 0, 0.8);
  min-height: 290px;
}

#reader {
  width: 100% !important;
  border: none !important;
  background: #000 !important;
  min-height: 290px;
}

#reader video {
  border-radius: 10px;
  object-fit: cover !important;
  width: 100% !important;
  height: auto !important;
  max-height: 52vh !important;
  background: #000;
}

#reader__scan_region {
  background: transparent !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
}

/* Corner Scan Reticle */
.scan-corner-frame {
  position: absolute;
  inset: 18px;
  pointer-events: none;
  z-index: 5;
}

.scan-corner {
  position: absolute;
  width: 26px;
  height: 26px;
  border-color: #38BDF8;
  border-style: solid;
}

.corner-tl { top: 0; left: 0; border-width: 3.5px 0 0 3.5px; border-top-left-radius: 6px; }
.corner-tr { top: 0; right: 0; border-width: 3.5px 3.5px 0 0; border-top-right-radius: 6px; }
.corner-bl { bottom: 0; left: 0; border-width: 0 0 3.5px 3.5px; border-bottom-left-radius: 6px; }
.corner-br { bottom: 0; right: 0; border-width: 0 3.5px 3.5px 0; border-bottom-right-radius: 6px; }

.scan-laser-line {
  position: absolute;
  top: 25%;
  left: 18px;
  right: 18px;
  height: 2.5px;
  background: linear-gradient(90deg, transparent, #38BDF8, #60A5FA, #38BDF8, transparent);
  box-shadow: 0 0 12px rgba(56, 189, 248, 0.95);
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
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.18);
  color: #E2E8F0;
  border-radius: 12px;
  padding: 8px 14px;
  font-size: 0.8rem;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.scanner-tool-btn:hover, .scanner-tool-btn:active {
  background: rgba(56, 189, 248, 0.2);
  border-color: #38BDF8;
  color: #FFFFFF;
}

.scanner-tool-btn.active {
  background: #38BDF8 !important;
  border-color: #38BDF8 !important;
  color: #0A1E38 !important;
  font-weight: 700;
  box-shadow: 0 0 10px rgba(56, 189, 248, 0.6);
}

.btn-zoom-pill {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 3px 12px;
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: #CBD5E1;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-zoom-pill:hover, .btn-zoom-pill.active {
  background: #2563EB !important;
  border-color: #38BDF8 !important;
  color: #FFFFFF !important;
  box-shadow: 0 0 8px rgba(37, 99, 235, 0.6);
}

.pulse-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: #22C55E;
  margin-right: 6px;
  box-shadow: 0 0 8px #22C55E;
  animation: pulseDotAnim 1.8s infinite;
}

@keyframes pulseDotAnim {
  0% { transform: scale(0.9); opacity: 0.7; }
  50% { transform: scale(1.3); opacity: 1; }
  100% { transform: scale(0.9); opacity: 0.7; }
}
</style>';

$body = '
<div class="row justify-content-center py-2 px-1">
  <div class="col-12 col-md-8 col-lg-6 col-xl-5">
    <div class="scanner-mobile-card">
      
      <!-- Header Mobile Bar -->
      <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-2" style="border-color: rgba(255,255,255,0.1) !important;">
        <div>
          <div class="text-uppercase fw-bold text-info" style="font-size: 0.68rem; letter-spacing: 0.08em;">PEMINDAI QR SMARTPHONE</div>
          <h1 class="h6 mb-0 text-white fw-bold d-flex align-items-center">
            <i class="bi bi-qr-code-scan me-2 text-primary"></i> SCAN ASSET LAPANGAN
          </h1>
        </div>
        <span class="badge rounded-pill bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-2.5 py-1.5" style="font-size: 0.72rem;">
          <span class="pulse-dot"></span> Langsung Scan
        </span>
      </div>

      <div class="text-center text-muted small my-2" style="font-size: 0.78rem; color: #94A3B8 !important;">
        Arahkan kamera HP ke stiker label QR komputer untuk memeriksa info & mengisi checklist bulanan.
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
        <div id="manualStartBox" class="text-center p-4 d-none" style="position: absolute; inset: 0; background: #07172F; z-index: 8;">
          <div class="d-flex flex-column align-items-center justify-content-center h-100">
            <div class="rounded-circle bg-primary bg-opacity-20 p-3 mb-2">
              <i class="bi bi-camera-video-fill fs-1 text-primary"></i>
            </div>
            <h6 class="text-white fw-bold mb-1">Kamera Belum Aktif</h6>
            <p class="small text-light text-opacity-75 mb-3" style="font-size: 0.78rem; max-width: 280px;">Browser HP meminta izin Anda untuk menyalakan kamera scanner:</p>
            <button type="button" class="btn btn-primary fw-bold px-4 py-2.5 rounded-pill shadow-lg" onclick="requestStartCamera()">
              <i class="bi bi-camera me-1.5"></i> Buka Kamera Scanner
            </button>
          </div>
        </div>
      </div>

      <!-- Toolbar Kontrol Kamera: Senter, Balik Kamera, Foto Langsung, Unggah Galeri -->
      <div class="d-flex align-items-center justify-content-center flex-wrap gap-2 mb-2">
        <button type="button" class="scanner-tool-btn" id="btnToggleTorch" onclick="toggleCameraTorch()">
          <i class="bi bi-lightning-fill"></i> Flash
        </button>
        <button type="button" class="scanner-tool-btn" id="btnSwitchCam" onclick="switchCamera()">
          <i class="bi bi-camera-reverse"></i> Balik Kamera
        </button>
        <button type="button" class="scanner-tool-btn" onclick="document.getElementById(\'nativeCameraInput\').click()">
          <i class="bi bi-camera-fill text-warning"></i> Foto Langsung
        </button>
        <button type="button" class="scanner-tool-btn" onclick="document.getElementById(\'galleryInput\').click()">
          <i class="bi bi-image"></i> Dari Galeri
        </button>
        <!-- Native Camera Input (langsung membuka app kamera HP) -->
        <input type="file" id="nativeCameraInput" accept="image/*" capture="environment" class="d-none" onchange="handleGalleryScan(this)">
        <!-- File Picker Input (pilih dari galeri foto) -->
        <input type="file" id="galleryInput" accept="image/*" class="d-none" onchange="handleGalleryScan(this)">
      </div>

      <!-- Zoom Slider / Buttons jika didukung kamera -->
      <div id="zoomControlWrapper" class="d-none text-center my-2">
        <span class="text-secondary small me-1"><i class="bi bi-search"></i> Zoom:</span>
        <div id="zoomButtonsContainer" class="d-inline-flex gap-1"></div>
      </div>

      <!-- Status Scanner -->
      <div id="scannerStatus" class="text-center mt-2" style="font-size: 0.78rem; color: #98A2B3;">
        <span class="spinner-border spinner-border-sm me-1 text-primary"></span> Menghubungkan ke kamera HP...
      </div>

      <!-- Input Manual Token (Jika QR Rusak/Kamera Terhalang) -->
      <div class="mt-3 pt-3 border-top" style="border-color: rgba(255,255,255,0.1) !important;">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-uppercase fw-semibold" style="font-size: 0.7rem; color: #94A3B8;">Ketik Token / Kode Manual</span>
          <span class="text-muted" style="font-size: 0.7rem;">(Alternatif jika stiker robek)</span>
        </div>
        <form onsubmit="return handleManualToken(event)">
          <div class="input-group">
            <input type="text" class="form-control form-control-sm font-monospace" id="manualTokenInput" placeholder="Ketik nomor/token aset..." style="background: #091C35; border-color: #1E3A60; color: #FFFFFF;" required>
            <button class="btn btn-primary btn-sm px-3 fw-bold" type="submit">
              <i class="bi bi-arrow-right"></i> Buka
            </button>
          </div>
        </form>
      </div>

      <!-- Tautan Cepat -->
      <div class="d-flex justify-content-between align-items-center mt-3 pt-2 text-muted" style="font-size: 0.72rem;">
        <span class="text-secondary"><i class="bi bi-shield-check text-success me-1"></i> Mode Lapangan Bebas Login</span>
        '.(is_logged_in() 
            ? '<a href="'.e(module_url('dashboard.php')).'" class="text-info text-decoration-none"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>' 
            : '<a href="'.e(module_url('login.php')).'" class="text-secondary text-decoration-none" title="Akses Admin Portal"><i class="bi bi-lock me-1"></i> Portal Admin</a>').'
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

  if (text.startsWith("http://") || text.startsWith("https://")) {
    try {
      var parsedUrl = new URL(text);
      var tokenVal = parsedUrl.searchParams.get("t") || parsedUrl.searchParams.get("token");
      if (tokenVal) {
        return "scan.php?t=" + encodeURIComponent(tokenVal.trim());
      }
      var pathName = parsedUrl.pathname.replace(/^\/api\//, "/").replace(/^\//, "");
      if (pathName && pathName.endsWith(".php")) {
        return pathName + (parsedUrl.search || "");
      }
    } catch (e) {}
  }

  if (text.indexOf("scan.php?t=") !== -1 || text.indexOf("scan.php?") !== -1) {
    var queryPart = text.split("?")[1] || "";
    var searchParams = new URLSearchParams(queryPart);
    var tVal = searchParams.get("t") || searchParams.get("token");
    if (tVal) {
      return "scan.php?t=" + encodeURIComponent(tVal.trim());
    }
  }

  return "scan.php?t=" + encodeURIComponent(text);
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
    laser.style.backgroundColor = "#22C55E";
    laser.style.boxShadow = "0 0 16px #22C55E";
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
      statusEl.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-lightning-charge-fill text-warning me-1"></i> Scanner aktif &bull; Arahkan ke stiker QR</span>';
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
        statusEl.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-shield-x me-1"></i> Izin kamera diblokir browser. Di iPhone: Buka <b>Pengaturan HP &rarr; Safari / Chrome &rarr; Kamera &rarr; Izinkan</b>. Atau gunakan tombol <b>"Foto Langsung"</b> di bawah.</span>';
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
