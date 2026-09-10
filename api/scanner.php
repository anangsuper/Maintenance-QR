<?php
require __DIR__ . '/bootstrap.php';

$head = '<style>
.scanner-box {
  max-width: 480px;
  margin: 0 auto;
}
#reader {
  width: 100%;
  border-radius: 16px;
  overflow: hidden;
  background: #000;
  min-height: 280px;
}
#reader video {
  border-radius: 16px;
  object-fit: cover;
}
</style>';

$body = '
<div class="row justify-content-center">
  <div class="col-md-7 col-lg-5">
    <div class="card border-0 shadow-sm rounded-4 p-3 p-sm-4 bg-white mb-3">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-2 py-1 mb-1">
            <i class="bi bi-camera-fill me-1"></i> Scanner Kamera
          </span>
          <h4 class="fw-bold mb-0 text-dark">Pindai QR Komputer</h4>
        </div>
        <a href="login.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
          <i class="bi bi-person-circle"></i> Login Admin
        </a>
      </div>

      <div class="alert alert-light border py-2 px-3 small text-secondary mb-3">
        <i class="bi bi-info-circle-fill text-primary me-1"></i>
        Arahkan kamera ke stiker kode QR komputer untuk membuka formulir maintenance.
      </div>

      <div class="scanner-box mb-3">
        <div id="reader"></div>
        <div id="scannerStatus" class="small text-center text-muted mt-2">
          <span class="spinner-border spinner-border-sm me-1 text-primary"></span> Menyiapkan kamera scanner...
        </div>
      </div>

      <div class="text-center my-2 text-muted small">
        <hr class="my-2">
        <span class="px-2 bg-white text-muted fw-semibold" style="font-size: 0.75rem;">ATAU MASUKKAN MANUAL KODE QR</span>
      </div>

      <form onsubmit="return handleManualToken(event)" class="mt-2">
        <div class="input-group">
          <input type="text" class="form-control" id="manualTokenInput" placeholder="Ketik token / kode QR..." required>
          <button class="btn btn-primary fw-bold px-3" type="submit">
            <i class="bi bi-arrow-right"></i> Buka
          </button>
        </div>
        <div class="form-text text-muted" style="font-size: 0.75rem;">Contoh: token yang tertera di stiker QR.</div>
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
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0,
        showTorchButtonIfSupported: true
      },
      false
    );
    html5QrcodeScanner.render(onScanSuccess, (err) => {});
    const statusEl = document.getElementById("scannerStatus");
    if (statusEl) {
      statusEl.innerHTML = '<span class="text-success"><i class="bi bi-camera-fill me-1"></i> Kamera aktif. Arahkan ke stiker QR.</span>';
    }
  } catch (err) {
    console.error(err);
    const statusEl = document.getElementById("scannerStatus");
    if (statusEl) {
      statusEl.innerHTML = '<span class="text-muted">Jika kamera tidak muncul, silakan ketik token QR secara manual di bawah.</span>';
    }
  }
});
</script>
HTML;

render_page('Pindai QR Komputer', $body, $head, $script, false);
