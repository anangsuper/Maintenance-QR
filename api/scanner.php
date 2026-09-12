<?php
require __DIR__ . '/bootstrap.php';

$head = '<style>
.scanner-container-dark {
  max-width: 480px;
  margin: 0 auto;
  background-color: var(--navy-deep);
  border: 1px solid var(--navy-subtle);
  border-radius: 12px;
  padding: 24px 20px;
  color: #FFFFFF;
  box-shadow: 0 10px 25px -5px rgba(8, 24, 47, 0.4);
}

.scanner-viewport-wrapper {
  position: relative;
  width: 100%;
  max-width: 360px;
  margin: 16px auto;
  border-radius: 10px;
  overflow: hidden;
  background-color: #000000;
}

#reader {
  width: 100%;
  border: none !important;
  background: #000;
  min-height: 280px;
}

#reader video {
  border-radius: 8px;
  object-fit: cover;
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

function onScanSuccess(decodedText) {
  const statusEl = document.getElementById("scannerStatus");
  if (statusEl) {
    statusEl.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> QR Terdeteksi! Membuka data aset...</span>';
  }
  try {
    if (html5QrcodeScanner) {
      html5QrcodeScanner.clear();
    }
  } catch (e) {}

  if (decodedText.startsWith("http://") || decodedText.startsWith("https://")) {
    window.location.href = decodedText;
  } else if (decodedText.includes("scan.php?t=")) {
    window.location.href = decodedText;
  } else {
    window.location.href = "scan.php?t=" + encodeURIComponent(decodedText.trim());
  }
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

document.addEventListener("DOMContentLoaded", () => {
  try {
    html5QrcodeScanner = new Html5QrcodeScanner(
      "reader",
      {
        fps: 15,
        qrbox: { width: 220, height: 220 },
        aspectRatio: 1.0,
        showTorchButtonIfSupported: true
      },
      false
    );
    html5QrcodeScanner.render(onScanSuccess, (err) => {});
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
