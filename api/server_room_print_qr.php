<?php
require __DIR__ . '/bootstrap.php';
require_login();

$cabang = trim((string)($_GET['cabang'] ?? 'Kantor Pusat (KPO)'));
$ruangan = trim((string)($_GET['ruangan'] ?? 'Ruang Server & Data Center Lt. 2'));

// URL Scan Masuk, Keluar, dan Cek Log via HP
$checkinUrl = module_base_url() . '/server_room_log.php?action=checkin_scan&cabang=' . urlencode($cabang);
$checkoutUrl = module_base_url() . '/server_room_log.php?action=checkout_scan&cabang=' . urlencode($cabang);
$viewLogsUrl = module_base_url() . '/server_room_log.php?action=view_logs&cabang=' . urlencode($cabang);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Stiker QR Masuk, Keluar & Cek Log Ruang Server · Bank Mitra</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <style>
    body {
      font-family: "Public Sans", sans-serif;
      background: #F4F5FA;
      color: #2E263D;
      padding: 24px 15px;
    }
    .print-sheet {
      background: #FFFFFF;
      max-width: 860px;
      margin: 0 auto;
      padding: 28px 32px;
      border-radius: 20px;
      border: 3px solid #8C57FF;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
      position: relative;
    }
    .badge-ojk {
      background: rgba(140, 87, 255, 0.12);
      color: #8C57FF;
      font-weight: 700;
      font-size: 0.70rem;
      padding: 5px 14px;
      border-radius: 30px;
      display: inline-block;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }
    .qr-card-checkin {
      background: #F0FDF4;
      border: 2px solid #86EFAC;
      border-radius: 14px;
      padding: 14px 10px;
      text-align: center;
      height: 100%;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.08);
    }
    .qr-card-checkout {
      background: #FEF2F2;
      border: 2px solid #FCA5A5;
      border-radius: 14px;
      padding: 14px 10px;
      text-align: center;
      height: 100%;
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.08);
    }
    .qr-card-logs {
      background: #F5F3FF;
      border: 2px solid #C4B5FD;
      border-radius: 14px;
      padding: 14px 10px;
      text-align: center;
      height: 100%;
      box-shadow: 0 4px 12px rgba(140, 87, 255, 0.08);
    }
    .qr-box {
      background: #FFFFFF;
      padding: 10px;
      display: inline-block;
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .step-box {
      background: #F8F9FD;
      border-radius: 12px;
      padding: 10px 12px;
      border: 1px solid rgba(76, 78, 100, 0.12);
      height: 100%;
    }
    .step-num {
      width: 24px;
      height: 24px;
      background: #8C57FF;
      color: #FFFFFF;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.78rem;
      margin-bottom: 5px;
    }
    .warning-banner {
      background: #FFFBEB;
      border: 1px solid #FCD34D;
      border-radius: 12px;
      padding: 10px 14px;
      color: #92400E;
      font-size: 0.76rem;
    }
    @media print {
      body {
        background: #FFFFFF;
        padding: 0;
      }
      .no-print {
        display: none !important;
      }
      .print-sheet {
        border: 2px solid #000000 !important;
        box-shadow: none !important;
        max-width: 100% !important;
        padding: 16px 20px !important;
      }
    }
  </style>
</head>
<body>

  <!-- Tombol Aksi Layar -->
  <div class="text-center mb-3 no-print">
    <button onclick="window.print()" class="btn btn-primary fw-bold px-4 py-2 rounded-pill shadow-sm me-2">
      <i class="bi bi-printer-fill me-1"></i> Cetak Lembar Stiker Pintu A4
    </button>
    <a href="<?= module_url('server_room_log.php') ?>" class="btn btn-outline-secondary fw-semibold px-4 py-2 rounded-pill shadow-xs">
      <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard Log
    </a>
  </div>

  <!-- Lembar Stiker Resmi Siap Tempel -->
  <div class="print-sheet text-center">
    <!-- Header Brand Bank -->
    <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-2">
      <img src="<?= app_logo_url() ?>" alt="Bank Mitra Logo" style="height: 44px; width: auto; object-fit: contain;">
      <div class="text-end">
        <div class="fw-bold fs-6 text-dark">PT BPR MITRATAMA ARTHABUANA</div>
        <div class="text-muted small" style="font-size: 0.75rem;">Divisi Teknologi Informasi &amp; Keamanan Fisik DC</div>
      </div>
    </div>

    <!-- Badge Kepatuhan -->
    <div class="mb-1">
      <span class="badge-ojk">
        <i class="bi bi-shield-lock-fill me-1"></i> KEPATUHAN OJK POJK NO. 75/POJK.03/2016 &middot; STANDAR FISIK ISO/IEC 27001
      </span>
    </div>

    <!-- Judul Stiker Pintu -->
    <h1 class="h5 fw-bold mb-0.5 text-dark" style="letter-spacing: -0.02em;">PORTAL AKSES RESMI RUANG SERVER &amp; DATA CENTER</h1>
    <div class="small fw-semibold text-primary mb-2"><?= e($ruangan) ?> &mdash; <?= e($cabang) ?></div>

    <p class="text-muted small mx-auto mb-3" style="max-width: 680px; font-size: 0.80rem;">
      Area terbatas berkeamanan tinggi. Setiap petugas IT, auditor, dan vendor luar <strong>wajib memindai QR code via smartphone</strong> sesuai kebutuhan akses di bawah ini:
    </p>

    <!-- TRIPLE QR CODE: MASUK, KELUAR & CEK LOG -->
    <div class="row g-2 mb-3">
      <!-- QR 1: CHECK-IN MASUK -->
      <div class="col-4">
        <div class="qr-card-checkin">
          <span class="badge bg-success text-white px-2.5 py-1 rounded-pill fw-bold mb-1 shadow-xs" style="font-size: 0.72rem;">
            <i class="bi bi-door-open-fill me-1"></i> 1. CHECK-IN MASUK
          </span>
          <div class="text-muted mb-1.5" style="font-size: 0.70rem;">Scan saat tiba di depan pintu untuk izin masuk</div>
          <div class="qr-box mb-1.5">
            <div id="doorQrCheckin"></div>
          </div>
          <div class="font-monospace text-muted" style="font-size: 0.65rem;">
            <i class="bi bi-phone me-1"></i>Arahkan Kamera HP
          </div>
        </div>
      </div>

      <!-- QR 2: CHECK-OUT KELUAR -->
      <div class="col-4">
        <div class="qr-card-checkout">
          <span class="badge bg-danger text-white px-2.5 py-1 rounded-pill fw-bold mb-1 shadow-xs" style="font-size: 0.72rem;">
            <i class="bi bi-box-arrow-right me-1"></i> 2. CHECK-OUT KELUAR
          </span>
          <div class="text-muted mb-1.5" style="font-size: 0.70rem;">Scan saat pekerjaan selesai sebelum pintu dikunci</div>
          <div class="qr-box mb-1.5">
            <div id="doorQrCheckout"></div>
          </div>
          <div class="font-monospace text-muted" style="font-size: 0.65rem;">
            <i class="bi bi-phone me-1"></i>Arahkan Kamera HP
          </div>
        </div>
      </div>

      <!-- QR 3: CEK LOG & RIWAYAT -->
      <div class="col-4">
        <div class="qr-card-logs">
          <span class="badge text-white px-2.5 py-1 rounded-pill fw-bold mb-1 shadow-xs" style="background:#8C57FF; font-size: 0.72rem;">
            <i class="bi bi-clock-history me-1"></i> 3. CEK LOG KELUAR-MASUK
          </span>
          <div class="text-muted mb-1.5" style="font-size: 0.70rem;">Scan untuk melihat daftar orang &amp; riwayat log</div>
          <div class="qr-box mb-1.5">
            <div id="doorQrLogs"></div>
          </div>
          <div class="font-monospace text-muted" style="font-size: 0.65rem;">
            <i class="bi bi-phone me-1"></i>Arahkan Kamera HP
          </div>
        </div>
      </div>
    </div>

    <!-- 3 Langkah Alur SOP -->
    <div class="row g-2 text-start mb-2.5">
      <div class="col-4">
        <div class="step-box">
          <div class="step-num">1</div>
          <div class="fw-bold text-dark small" style="font-size: 0.75rem;">Check-In Masuk</div>
          <div class="text-muted" style="font-size: 0.68rem;">Pindai QR Hijau, catat suhu awal ruangan &amp; foto wajah kedatangan.</div>
        </div>
      </div>
      <div class="col-4">
        <div class="step-box">
          <div class="step-num">2</div>
          <div class="fw-bold text-dark small" style="font-size: 0.75rem;">Check-Out Keluar</div>
          <div class="text-muted" style="font-size: 0.68rem;">Pindai QR Merah saat akan keluar, catat suhu akhir &amp; catatan selesai.</div>
        </div>
      </div>
      <div class="col-4">
        <div class="step-box">
          <div class="step-num">3</div>
          <div class="fw-bold text-dark small" style="font-size: 0.75rem;">Pantau Log Digital</div>
          <div class="text-muted" style="font-size: 0.68rem;">Pindai QR Ungu kapan pun untuk memantau siapa saja yang keluar-masuk.</div>
        </div>
      </div>
    </div>

    <!-- Peringatan Keamanan Perbankan -->
    <div class="warning-banner text-start">
      <div class="fw-bold mb-1 d-flex align-items-center gap-1.5">
        <i class="bi bi-exclamation-triangle-fill text-warning"></i>
        <span>PERINGATAN STANDAR PENGAMANAN RUANG DATA CENTER:</span>
      </div>
      <ul class="mb-0 ps-3" style="font-size: 0.72rem;">
        <li>Ruangan dipantau CCTV 24 Jam dengan pencatatan audit log otomatis dan deteksi suhu server.</li>
        <li>Tamu / vendor wajib selalu didampingi petugas IT Bank Mitra yang berwenang.</li>
        <li>Dilarang keras membawa makanan, minuman, korek api, atau bahan berbahaya ke dalam ruang server.</li>
      </ul>
    </div>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", function() {
      // 1. QR Code Masuk (Check-In)
      new QRCode(document.getElementById("doorQrCheckin"), {
        text: <?= json_encode($checkinUrl) ?>,
        width: 145,
        height: 145,
        colorDark: "#065F46",
        colorLight: "#FFFFFF",
        correctLevel: QRCode.CorrectLevel.M
      });

      // 2. QR Code Keluar (Check-Out)
      new QRCode(document.getElementById("doorQrCheckout"), {
        text: <?= json_encode($checkoutUrl) ?>,
        width: 145,
        height: 145,
        colorDark: "#991B1B",
        colorLight: "#FFFFFF",
        correctLevel: QRCode.CorrectLevel.M
      });

      // 3. QR Code Cek Log Keluar-Masuk
      new QRCode(document.getElementById("doorQrLogs"), {
        text: <?= json_encode($viewLogsUrl) ?>,
        width: 145,
        height: 145,
        colorDark: "#5B21B6",
        colorLight: "#FFFFFF",
        correctLevel: QRCode.CorrectLevel.M
      });
    });
  </script>
</body>
</html>
