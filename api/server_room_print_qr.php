<?php
require __DIR__ . '/bootstrap.php';
require_login();

$cabang = trim((string)($_GET['cabang'] ?? 'Kantor Pusat (KPO)'));
$ruangan = trim((string)($_GET['ruangan'] ?? 'Ruang Server & Data Center Lt. 2'));

// URL yang akan di-scan di pintu
$scanUrl = module_base_url() . '/server_room_log.php?action=scan&cabang=' . urlencode($cabang);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Cetak Stiker QR Pintu Ruang Server &middot; Bank Mitra</title>
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
      padding: 30px 15px;
    }
    .print-sheet {
      background: #FFFFFF;
      max-width: 720px;
      margin: 0 auto;
      padding: 36px 40px;
      border-radius: 20px;
      border: 3px solid #8C57FF;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
      position: relative;
    }
    .badge-ojk {
      background: rgba(140, 87, 255, 0.12);
      color: #8C57FF;
      font-weight: 700;
      font-size: 0.75rem;
      padding: 6px 14px;
      border-radius: 30px;
      display: inline-block;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }
    .qr-container {
      background: #FFFFFF;
      padding: 16px;
      display: inline-block;
      border-radius: 16px;
      border: 2px dashed #8C57FF;
      box-shadow: 0 4px 16px rgba(140, 87, 255, 0.15);
    }
    .step-box {
      background: #F8F9FD;
      border-radius: 12px;
      padding: 14px 18px;
      border: 1px solid rgba(76, 78, 100, 0.12);
      height: 100%;
    }
    .step-num {
      width: 28px;
      height: 28px;
      background: #8C57FF;
      color: #FFFFFF;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.85rem;
      margin-bottom: 8px;
    }
    .warning-banner {
      background: #FFFBEB;
      border: 1px solid #FCD34D;
      border-radius: 12px;
      padding: 14px 18px;
      color: #92400E;
      font-size: 0.82rem;
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
        border: 3px solid #000000 !important;
        box-shadow: none !important;
        max-width: 100% !important;
        padding: 24px 28px !important;
      }
    }
  </style>
</head>
<body>

  <!-- Tombol Aksi Layar -->
  <div class="text-center mb-4 no-print">
    <button onclick="window.print()" class="btn btn-primary fw-bold px-4 py-2 rounded-pill shadow-sm me-2">
      <i class="bi bi-printer-fill me-1"></i> Cetak Lembar Stiker A4
    </button>
    <a href="<?= module_url('server_room_log.php') ?>" class="btn btn-outline-secondary fw-semibold px-4 py-2 rounded-pill shadow-xs">
      <i class="bi bi-arrow-left me-1"></i> Kembali ke Log Ruang Server
    </a>
  </div>

  <!-- Lembar Stiker Resmi Siap Laminating -->
  <div class="print-sheet text-center">
    <!-- Header Brand Bank -->
    <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
      <img src="<?= app_logo_url() ?>" alt="Bank Mitra Logo" style="height: 52px; width: auto; object-fit: contain;">
      <div class="text-end">
        <div class="fw-bold fs-6 text-dark">PT BPR MITRATAMA ARTHABUANA</div>
        <div class="text-muted small">Divisi Teknologi Informasi &amp; Keamanan Siber</div>
      </div>
    </div>

    <!-- Badge Kepatuhan -->
    <div class="mb-2">
      <span class="badge-ojk">
        <i class="bi bi-shield-lock-fill me-1"></i> STANDAR KEPATUHAN OJK &middot; POJK NO. 75/POJK.03/2016
      </span>
    </div>

    <!-- Judul Stiker Pintu -->
    <h1 class="h3 fw-bold mb-1 text-dark" style="letter-spacing: -0.02em;">STIKER AKSES RESMI RUANG SERVER</h1>
    <div class="h5 fw-semibold text-primary mb-3"><?= e($ruangan) ?> &mdash; <?= e($cabang) ?></div>

    <p class="text-muted small mx-auto mb-4" style="max-width: 540px;">
      Kawasan terbatas dengan pengamanan ketat. Setiap orang yang masuk (Teknisi IT Bank, Auditor SKAI, maupun Vendor Luar) <strong>wajib mencatat kehadiran secara digital</strong> dengan memindai kode QR di bawah ini.
    </p>

    <!-- QR Code Pintu Server -->
    <div class="qr-container mb-3">
      <div id="doorQrCode"></div>
    </div>

    <div class="font-monospace text-muted small mb-4" style="font-size: 0.78rem;">
      <i class="bi bi-link-45deg me-1"></i><?= e($scanUrl) ?>
    </div>

    <!-- 3 Langkah Mudah -->
    <div class="row g-2 text-start mb-4">
      <div class="col-md-4">
        <div class="step-box">
          <div class="step-num">1</div>
          <div class="fw-bold text-dark small">Pindai QR Pintu</div>
          <div class="text-muted" style="font-size: 0.74rem;">Buka kamera HP atau scanner barcode lalu arahkan ke QR ini.</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="step-box">
          <div class="step-num">2</div>
          <div class="fw-bold text-dark small">Konfirmasi Masuk</div>
          <div class="text-muted" style="font-size: 0.74rem;">Pilih nama &amp; keperluan lalu tekan tombol <em>Check-In</em>.</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="step-box">
          <div class="step-num">3</div>
          <div class="fw-bold text-dark small">Konfirmasi Keluar</div>
          <div class="text-muted" style="font-size: 0.74rem;">Setelah selesai, scan kembali atau tekan <em>Check-Out</em>.</div>
        </div>
      </div>
    </div>

    <!-- Peringatan Keamanan Perbankan -->
    <div class="warning-banner text-start">
      <div class="fw-bold mb-1 d-flex align-items-center gap-1.5">
        <i class="bi bi-exclamation-triangle-fill text-warning"></i>
        <span>PERINGATAN KETENTUAN KEAMANAN RUANG SERVER:</span>
      </div>
      <ul class="mb-0 ps-3">
        <li>Ruang server dipantau kamera CCTV 24 Jam dengan rekaman audit permanen.</li>
        <li>Tamu / vendor eksternal wajib didampingi petugas IT Bank Mitra berwenang.</li>
        <li>Dilarang keras membawa makanan, minuman, atau bahan mudah terbakar.</li>
      </ul>
    </div>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", function() {
      new QRCode(document.getElementById("doorQrCode"), {
        text: <?= json_encode($scanUrl) ?>,
        width: 200,
        height: 200,
        colorDark: "#28243D",
        colorLight: "#FFFFFF",
        correctLevel: QRCode.CorrectLevel.H
      });
    });
  </script>
</body>
</html>
