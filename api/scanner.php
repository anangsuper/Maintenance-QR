<?php
require __DIR__ . '/bootstrap.php';

$head = '<style>
.scanner-container-dark {
  max-width: 500px;
  margin: 0 auto;
  background-color: var(--navy-deep);
  border: 1px solid var(--navy-subtle);
  border-radius: 14px;
  padding: 24px 20px;
  color: #FFFFFF;
  box-shadow: 0 10px 25px -5px rgba(8, 24, 47, 0.4);
}

.scanner-viewport-wrapper {
  position: relative;
  width: 100%;
  margin: 16px auto;
  border-radius: 12px;
  overflow: hidden;
  background-color: #000000;
}

#reader {
  width: 100% !important;
  border: none !important;
  background: #000;
}

/* Pastikan video TIDAK ter-crop/ter-zoom (gunakan contain) */
#reader video {
  border-radius: 8px;
  object-fit: contain !important;
  width: 100% !important;
  height: auto !important;
  max-height: 480px;
  background: #000;
}

#reader__scan_region {
  background: transparent !important;
}

#reader button {
  background-color: var(--blue-corporate) !important;
  color: #FFFFFF !important;
  border: 1px solid rgba(255, 255, 255, 0.2) !important;
  border-radius: 6px !important;
  padding: 6px 14px !important;
  font-size: 0.82rem !important;
  margin: 6px 4px !important;
  cursor: pointer;
}

#reader select {
  background-color: #0D2748 !important;
  color: #FFFFFF !important;
  border: 1px solid var(--navy-subtle) !important;
  border-radius: 6px !important;
  padding: 6px 10px !important;
  font-size: 0.82rem !important;
  max-width: 90% !important;
  margin-bottom: 8px;
}

/* Corner Scan Frame Overlay */
.scan-corner-frame {
  position: absolute;
  inset: 20px;
  pointer-events: none;
  z-index: 5;
}

.scan-corner {
  position: absolute;
  width: 22px;
  height: 22px;
  border-color: var(--blue-accent);
  border-style: solid;
}

.corner-tl { top: 0; left: 0; border-width: 3px 0 0 3px; }
.corner-tr { top: 0; right: 0; border-width: 3px 3px 0 0; }
.corner-bl { bottom: 0; left: 0; border-width: 0 0 3px 3px; }
.corner-br { bottom: 0; right: 0; border-width: 0 3px 3px 0; }

.scan-laser-line {
  position: absolute;
  top: 25%;
  left: 20px;
  right: 20px;
  height: 2px;
  background-color: var(--blue-accent);
  box-shadow: 0 0 8px rgba(46, 124, 246, 0.8);
  pointer-events: none;
  z-index: 6;
  animation: scanLaserAnim 2.2s infinite ease-in-out;
}

@keyframes scanLaserAnim {
  0% { top: 20%; opacity: 0; }
  50% { opacity: 1; }
  100% { top: 80%; opacity: 0; }
}

.scanner-status-text {
  font-size: 0.78rem;
  color: #98A2B3;
  text-align: center;
  margin-top: 10px;
}

.zoom-controls-bar {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 12px;
}

.btn-zoom-opt {
  font-size: 0.78rem;
  font-weight: 600;
  padding: 4px 14px;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.25);
  color: #E2E8F0;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-zoom-opt:hover, .btn-zoom-opt.active {
  background: var(--blue-accent) !important;
  border-color: var(--blue-accent) !important;
  color: #FFFFFF !important;
  box-shadow: 0 0 8px rgba(46, 124, 246, 0.5);
}
</style>';

$body = '
<div class="row justify-content-center py-2">
  <div class="col-12 col-md-8 col-lg-6">
    <div class="scanner-container-dark">
      <div class="d-flex align-items-center justify-content-between border-bottom border-navy-subtle pb-3 mb-3">
        <div>
          <div class="tech-label" style="color: var(--blue-accent);">OPTICAL SCANNER</div>
          <h1 class="h5 mb-0 text-white fw-bold">SCAN ASSET QR</h1>
        </div>
        '.(is_logged_in() 
            ? '<a href="'.e(module_url('dashboard.php')).'" class="btn btn-sm btn-outline-light py-1 px-2" style="font-size: 0.78rem;"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>' 
            : '<a href="'.e(module_url('login.php')).'" class="btn btn-sm btn-outline-light py-1 px-2" style="font-size: 0.78rem;"><i class="bi bi-person me-1"></i> Login</a>').'
      </div>

      <div class="text-center text-muted small mb-2" style="font-size: 0.8rem; color: #CBD5E1 !important;">
        Arahkan kamera ke stiker QR Code yang tertera pada perangkat komputer.
      </div>

      <div class="scanner-viewport-wrapper">
        <div class="scan-corner-frame">
          <div class="scan-corner corner-tl"></div>
          <div class="scan-corner corner-tr"></div>
          <div class="scan-corner corner-bl"></div>
          <div class="scan-corner corner-br"></div>
          <div class="scan-laser-line"></div>
        </div>
        <div id="reader"></div>
      </div>

      <!-- Kontrol Zoom Kamera (jika didukung perangkat) -->
      <div id="zoomControlWrapper" class="d-none zoom-controls-bar">
        <span class="text-secondary small me-1"><i class="bi bi-search"></i> Zoom:</span>
        <div id="zoomButtonsContainer" class="d-inline-flex gap-2"></div>
      </div>

      <div id="scannerStatus" class="scanner-status-text">
        <span class="spinner-border spinner-border-sm me-1 text-primary"></span> Menyiapkan kamera scanner...
      </div>

      <div class="text-center my-3">
        <div class="d-flex align-items-center gap-2">
          <hr class="flex-grow-1 border-secondary opacity-25">
          <span class="tech-label" style="color: #667085;">ATAU MASUKKAN KODE MANUAL</span>
          <hr class="flex-grow-1 border-secondary opacity-25">
        </div>
      </div>

      <form onsubmit="return handleManualToken(event)">
        <div class="input-group">
          <input type="text" class="form-control form-control-sm font-monospace" id="manualTokenInput" placeholder="Ketik token / kode QR..." required style="background: #0D2748; border-color: #1E3A60; color: #FFFFFF;">
          <button class="btn btn-primary btn-sm px-3 fw-semibold" type="submit">
            <i class="bi bi-arrow-right me-1"></i> Buka
          </button>
        </div>
        <div class="text-muted mt-1 text-center" style="font-size: 0.72rem;">Contoh: token yang tertera pada label QR komputer.</div>
      </form>
    </div>
  </div>
</div>';

$script = <<<'HTML'
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
let html5QrcodeScanner = null;
let currentCameraTrack = null;

function playScanAudioFeedback() {
  try {
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (AudioContext) {
      const ctx = new AudioContext();
      if (ctx.state === 'suspended') {
        ctx.resume();
      }
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();

      osc.type = 'sine';
      osc.frequency.setValueAtTime(880, ctx.currentTime); // 880 Hz (A5 pleasant scanner beep)

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
      navigator.vibrate([100, 40, 100]); // Dual pulse confirmation
    }
  } catch (e) {}
}

function onScanSuccess(decodedText) {
  // 1. Umpan balik audio (beep) & getar (haptic) instan
  playScanAudioFeedback();
  triggerScanHapticFeedback();

  const statusEl = document.getElementById("scannerStatus");
  if (statusEl) {
    statusEl.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> QR Terdeteksi! Membuka data aset...</span>';
  }
  try {
    if (html5QrcodeScanner) {
      html5QrcodeScanner.clear();
    }
  } catch (e) {}

  setTimeout(function() {
    if (decodedText.startsWith("http://") || decodedText.startsWith("https://")) {
      window.location.href = decodedText;
    } else if (decodedText.includes("scan.php?t=")) {
      window.location.href = decodedText;
    } else {
      window.location.href = "scan.php?t=" + encodeURIComponent(decodedText.trim());
    }
  }, 180);
}

function handleManualToken(e) {
  e.preventDefault();
  const val = document.getElementById("manualTokenInput").value.trim();
  if (val) {
    if (val.startsWith("http://") || val.startsWith("https://") || val.includes("scan.php?t=")) {
      window.location.href = val;
    } else {
      window.location.href = "scan.php?t=" + encodeURIComponent(val);
    }
  }
  return false;
}

function applyCameraOptimizations() {
  const video = document.querySelector("#reader video");
  if (!video) return;

  // Pastikan video tidak dicrop (tidak ter-zoom)
  video.style.objectFit = "contain";
  video.style.width = "100%";
  video.style.height = "auto";

  if (video.srcObject) {
    const tracks = video.srcObject.getVideoTracks();
    if (tracks && tracks.length > 0) {
      const track = tracks[0];
      currentCameraTrack = track;
      try {
        const capabilities = (typeof track.getCapabilities === "function") ? track.getCapabilities() : {};
        // Reset zoom ke minimum (1x atau 0.5x) jika didukung perangkat
        if (capabilities.zoom) {
          const minZoom = capabilities.zoom.min || 1.0;
          track.applyConstraints({
            advanced: [{ zoom: minZoom }]
          }).catch(function(e) {});

          setupZoomControls(track, capabilities.zoom);
        }
      } catch (e) {}
    }
  }
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

  presets.forEach(function(val, idx) {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "btn-zoom-opt" + (val === minZ || (minZ >= 1.0 && val === 1.0) ? " active" : "");
    btn.textContent = val + "x";
    btn.onclick = function() {
      setCameraZoom(val);
      container.querySelectorAll(".btn-zoom-opt").forEach(function(b) { b.classList.remove("active"); });
      btn.classList.add("active");
    };
    container.appendChild(btn);
  });

  wrapper.classList.remove("d-none");
}

function setCameraZoom(val) {
  if (!currentCameraTrack) return;
  try {
    const caps = (typeof currentCameraTrack.getCapabilities === "function") ? currentCameraTrack.getCapabilities() : {};
    if (caps.zoom) {
      const target = Math.min(Math.max(val, caps.zoom.min), caps.zoom.max);
      currentCameraTrack.applyConstraints({
        advanced: [{ zoom: target }]
      }).catch(function(err) {});
    }
  } catch (e) {}
}

document.addEventListener("DOMContentLoaded", () => {
  try {
    html5QrcodeScanner = new Html5QrcodeScanner(
      "reader",
      {
        fps: 20,
        qrbox: function(viewfinderWidth, viewfinderHeight) {
          const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
          const size = Math.max(Math.floor(minEdge * 0.72), 180);
          return { width: size, height: size };
        },
        aspectRatio: 1.333333, // 4:3 rasio alami sensor kamera ponsel (bukan 1.0 yang memotong & meng-crop sensor)
        showTorchButtonIfSupported: true,
        videoConstraints: {
          facingMode: { ideal: "environment" },
          focusMode: { ideal: "continuous" }
        }
      },
      false
    );
    html5QrcodeScanner.render(onScanSuccess, (err) => {});

    // Pasang observer untuk mendeteksi saat elemen video aktif di dalam #reader
    const observer = new MutationObserver(function() {
      const vid = document.querySelector("#reader video");
      if (vid) {
        vid.addEventListener("loadedmetadata", applyCameraOptimizations);
        vid.addEventListener("play", applyCameraOptimizations);
        applyCameraOptimizations();
      }
    });
    const rdr = document.getElementById("reader");
    if (rdr) {
      observer.observe(rdr, { childList: true, subtree: true });
    }

    const statusEl = document.getElementById("scannerStatus");
    if (statusEl) {
      statusEl.innerHTML = '<span class="text-success"><i class="bi bi-camera-fill me-1"></i> Kamera siap. Bidik ke stiker QR.</span>';
    }
  } catch (err) {
    console.error(err);
    const statusEl = document.getElementById("scannerStatus");
    if (statusEl) {
      statusEl.innerHTML = '<span class="text-muted">Kamera tidak aktif. Masukkan token QR secara manual di atas.</span>';
    }
  }
});
</script>
HTML;

render_page('Pindai QR Komputer', $body, $head, $script, false);
