<?php
require __DIR__ . '/bootstrap.php';

$cabang = trim((string)($_GET['cabang'] ?? 'Kantor Pusat (KPO)'));
$ruangan = trim((string)($_GET['ruangan'] ?? 'Ruang Server & Data Center Lt. 2'));

// URL Tunggal Portal Akses Resmi Ruang Server via HP (Selalu https://it.bankmitra.co.id)
$portalUrl = qr_url('server_room_log.php');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Stiker QR Resmi Portal Ruang Server · Bank Mitra</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= module_url('assets/vendor/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= module_url('assets/vendor/bootstrap-icons.min.css') ?>">
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
      max-width: 800px;
      margin: 0 auto;
      padding: 26px 30px;
      border-radius: 20px;
      border: 3px solid #8C57FF;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
      position: relative;
    }
    .qr-card-main {
      background: #FAF9FF;
      border: 2px solid #C4B5FD;
      border-radius: 16px;
      padding: 16px 20px;
      text-align: center;
      max-width: 440px;
      margin: 0 auto;
      box-shadow: 0 4px 16px rgba(140, 87, 255, 0.08);
    }
    .qr-box {
      background: #FFFFFF;
      padding: 12px;
      display: inline-block;
      border-radius: 12px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
      border: 1px solid rgba(140, 87, 255, 0.2);
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
    @page {
      size: A4 portrait;
      margin: 8mm 10mm;
    }
    @media print {
      * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
      body {
        background: #FFFFFF !important;
        padding: 0 !important;
      }
      .no-print {
        display: none !important;
      }
      .print-sheet {
        border: 2.5px solid #8C57FF !important;
        box-shadow: none !important;
        max-width: 100% !important;
        padding: 16px 20px !important;
        border-radius: 16px !important;
        page-break-inside: avoid;
      }
      .qr-card-main {
        border-color: #8C57FF !important;
        box-shadow: none !important;
        padding: 12px 16px !important;
        margin-bottom: 12px !important;
      }
      .qr-box {
        padding: 8px !important;
        box-shadow: none !important;
      }
      .step-box {
        padding: 8px 10px !important;
      }
      .warning-banner {
        padding: 8px 12px !important;
      }
    }
  </style>
</head>
<body>

  <!-- Tombol Aksi Layar -->
  <div class="text-center mb-3 no-print d-flex align-items-center justify-content-center gap-2 flex-wrap">
    <button onclick="window.print()" class="btn btn-primary fw-bold px-4 py-2 rounded-pill shadow-sm">
      <i class="bi bi-printer-fill me-1"></i> Cetak Lembar Stiker Pintu A4
    </button>
    <a href="<?= e($portalUrl) ?>" target="_blank" class="btn btn-outline-primary fw-semibold px-4 py-2 rounded-pill shadow-xs">
      <i class="bi bi-phone me-1"></i> Tes Buka Portal HP
    </a>
    <a href="<?= module_url('server_room_log.php') ?>" class="btn btn-outline-secondary fw-semibold px-4 py-2 rounded-pill shadow-xs">
      <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard Log
    </a>
  </div>

  <!-- Lembar Stiker Resmi Siap Tempel -->
  <div class="print-sheet text-center">
    <!-- Header Brand Bank -->
    <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
      <img src="<?= app_logo_url() ?>" alt="Bank Mitra Logo" style="height: 48px; width: auto; object-fit: contain;">
      <div class="text-end">
        <div class="fw-bold fs-6 text-dark">BANK MITRA</div>
        <div class="text-muted small" style="font-size: 0.75rem;">Divisi Teknologi Informasi</div>
      </div>
    </div>

    <!-- Judul Stiker Pintu -->
    <h1 class="h5 fw-bold mb-2 text-dark" style="letter-spacing: -0.02em;">PORTAL AKSES RESMI RUANG SERVER &amp; DATA CENTER</h1>

    <p class="text-muted small mx-auto mb-3" style="max-width: 650px; font-size: 0.80rem;">
      Area terbatas. Setiap petugas IT, auditor, dan vendor luar <strong>wajib memindai QR code via smartphone</strong> untuk membuka Portal Buku Tamu Digital (Check-In Masuk &amp; Check-Out Keluar):
    </p>

    <!-- SINGLE GRAND QR CODE CARD -->
    <div class="qr-card-main mb-3">
      <span class="badge text-white px-3 py-1.5 rounded-pill fw-bold mb-1 shadow-xs" style="background: linear-gradient(135deg, #8C57FF 0%, #6827EB 100%); font-size: 0.76rem;">
        <i class="bi bi-qr-code-scan me-1"></i> QR RESMI BUKU TAMU DIGITAL
      </span>
      <div class="text-muted mb-2" style="font-size: 0.72rem;">Satu QR untuk Check-In Masuk, Check-Out Keluar &amp; Cek Log Digital</div>
      <div class="qr-box mb-2">
        <div id="doorQrPortal"></div>
      </div>
      <div class="font-monospace text-dark fw-bold mt-1" style="font-size: 0.75rem;">
        <i class="bi bi-phone me-1 text-primary"></i>Arahkan Kamera HP ke QR Code
      </div>
    </div>

    <!-- 3 Langkah Alur SOP -->
    <div class="row g-2 text-start mb-2.5">
      <div class="col-4">
        <div class="step-box">
          <div class="step-num" style="background: #10B981;">1</div>
          <div class="fw-bold text-dark small" style="font-size: 0.75rem;">Check-In Masuk</div>
          <div class="text-muted" style="font-size: 0.68rem;">Pindai QR saat tiba di pintu, isi data diri, catat suhu awal &amp; ambil foto selfie.</div>
        </div>
      </div>
      <div class="col-4">
        <div class="step-box">
          <div class="step-num" style="background: #EF4444;">2</div>
          <div class="fw-bold text-dark small" style="font-size: 0.75rem;">Check-Out Keluar</div>
          <div class="text-muted" style="font-size: 0.68rem;">Pindai QR yang sama saat selesai kerja, catat suhu akhir &amp; konfirmasi keluar.</div>
        </div>
      </div>
      <div class="col-4">
        <div class="step-box">
          <div class="step-num" style="background: #8C57FF;">3</div>
          <div class="fw-bold text-dark small" style="font-size: 0.75rem;">Pantau Log Digital</div>
          <div class="text-muted" style="font-size: 0.68rem;">Riwayat &amp; personil di dalam ruangan terekam otomatis secara digital.</div>
        </div>
      </div>
    </div>

    <!-- Peringatan Keamanan -->
    <div class="warning-banner text-start">
      <div class="fw-bold mb-1 d-flex align-items-center gap-1.5">
        <i class="bi bi-exclamation-triangle-fill text-warning"></i>
        <span>PERINGATAN PENGAMANAN RUANG DATA CENTER:</span>
      </div>
      <ul class="mb-0 ps-3" style="font-size: 0.72rem;">
        <li>Ruangan dipantau CCTV 24 Jam dengan pencatatan log otomatis dan deteksi suhu server.</li>
        <li>Tamu / vendor wajib selalu didampingi petugas IT yang berwenang.</li>
        <li>Dilarang keras membawa makanan, minuman, korek api, atau bahan berbahaya ke dalam ruang server.</li>
      </ul>
    </div>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", function() {
      // QR Code Tunggal untuk Portal Akses Ruang Server
      new QRCode(document.getElementById("doorQrPortal"), {
        text: <?= json_encode($portalUrl) ?>,
        width: 210,
        height: 210,
        colorDark: "#1E1B4B",
        colorLight: "#FFFFFF",
        correctLevel: QRCode.CorrectLevel.M
      });
    });
  </script>
</body>
</html>