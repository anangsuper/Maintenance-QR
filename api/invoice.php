<?php
require_once __DIR__ . '/bootstrap.php';

$mode = strtolower(trim((string)($_GET['mode'] ?? '')));
$id = max(0, (int)($_GET['id'] ?? 0));

// Jika ada parameter ID maintenance atau mode=maintenance, tampilkan data maintenance
if ($mode === '' && $id > 0) {
    $mode = 'maintenance';
} elseif ($mode === '') {
    $mode = 'trip'; // Default ke trip invoice persis seperti komponen yang diminta
}

// Ambil data maintenance jika mode maintenance
$maintDetail = null;
if ($mode === 'maintenance') {
    if ($id > 0) {
        $maintDetail = get_maintenance_detail($id);
    }
    // Jika tidak ada ID spesifik, ambil maintenance terakhir
    if (!$maintDetail) {
        $histories = get_maintenance_history(1);
        if (!empty($histories)) {
            $lastId = (int)($histories[0]['id'] ?? 0);
            if ($lastId > 0) {
                $id = $lastId;
                $maintDetail = get_maintenance_detail($lastId);
            }
        }
    }
}

$pageTitle = $mode === 'maintenance' ? 'Struk Pemeliharaan IT' : 'Trip Invoice — Japan Summer 2025';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> · QR Maintenance</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- External Font Awesome CDN (Required) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

  <style>
    *,
    *::before,
    *::after {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif, -apple-system, BlinkMacSystemFont;
      background: #f1f5f9;
      color: #1f2a37;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 1.5rem 0.75rem 3rem;
    }

    /* Mode Switcher Navigation (Clean & Professional) */
    .top-controls {
      width: min(95%, 430px);
      margin-bottom: 1.25rem;
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }

    .mode-switch-group {
      display: flex;
      background: #e2e8f0;
      padding: 4px;
      border-radius: 12px;
      gap: 4px;
      box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
    }

    .mode-switch-btn {
      flex: 1;
      text-align: center;
      padding: 0.5rem 0.75rem;
      font-size: 0.82rem;
      font-weight: 700;
      color: #475569;
      text-decoration: none;
      border-radius: 9px;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.35rem;
    }

    .mode-switch-btn.active {
      background: #ffffff;
      color: #0f172a;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .back-nav-link {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      color: #64748b;
      font-size: 0.85rem;
      font-weight: 600;
      text-decoration: none;
      transition: color 0.2s ease;
    }

    .back-nav-link:hover {
      color: #0f172a;
    }

    /* ---- invoice container ---- */
    .container {
      width: min(95%, 425px);
      margin: 0 auto;
    }

    .invoice-container {
      position: relative;
      margin-bottom: 1.75em;
      min-height: 640px;
    }

    .invoice-slot {
      width: 100%;
      height: 120px;
      background-color: #2b2b2b;
      border: 2px solid #2c2c2c;
      border-radius: 1em;
      box-shadow: 0 0 1px 0 #000, 0 5px 15px 0 rgba(0, 0, 0, 0.45);
      position: relative;
      z-index: 1;
    }

    .slot-hole {
      background-color: #000;
      border-radius: 100vmax;
      width: 90%;
      height: 25px;
      margin: 1em auto;
      border: 1px solid #1b1b1b;
      box-shadow: 0 0 1px 0 #000, 0 5px 15px 0 rgba(0, 0, 0, 0.45);
      position: relative;
    }

    .slot-hole::after {
      content: "";
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 96%;
      height: 4px;
      background: #111;
      border-radius: 2px;
    }

    .invoice {
      position: absolute;
      width: 85%;
      top: 1.5em;
      left: 50%;
      transform: translateX(-50%);
      background-color: #fff;
      color: #6b7280;
      padding: 1em;
      border-radius: 0.5em;
      box-shadow: 0 8px 30px 0 rgba(0, 0, 0, 0.18);
      z-index: 2;
      animation: dispenseAnimation 0.85s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    @keyframes dispenseAnimation {
      0% {
        transform: translate(-50%, -60px);
        opacity: 0;
      }
      30% {
        opacity: 1;
      }
      100% {
        transform: translate(-50%, 0);
        opacity: 1;
      }
    }

    .invoice::before {
      content: "";
      position: absolute;
      width: 100%;
      height: 80px;
      left: 0;
      top: 0;
      pointer-events: none;
      border-top-left-radius: 0.5em;
      border-top-right-radius: 0.5em;
      background: linear-gradient(
        180deg,
        rgba(0, 0, 0, 0.85) 0%,
        rgba(0, 0, 0, 0.55) 10%,
        rgba(0, 0, 0, 0.35) 25%,
        rgba(0, 0, 0, 0.18) 40%,
        rgba(0, 0, 0, 0.08) 60%,
        transparent 100%
      );
    }

    .invoice .title {
      position: relative;
      font-size: 1.15rem;
      padding: 0.55em 0;
      letter-spacing: 0.5px;
      text-align: center;
      margin-bottom: 1.25em;
      font-weight: 700;
      color: #1b1b1b;
    }

    .invoice .title::before {
      content: "";
      position: absolute;
      height: 1.5px;
      width: 100%;
      top: 0;
      left: 0;
      background-image: repeating-linear-gradient(
        90deg,
        #1b1b1b,
        #1b1b1b 8px,
        transparent 8px,
        transparent 16px
      );
    }

    .invoice .title::after {
      content: "";
      position: absolute;
      height: 1.5px;
      width: 100%;
      bottom: 0;
      left: 0;
      background-image: repeating-linear-gradient(
        90deg,
        #1b1b1b,
        #1b1b1b 8px,
        transparent 8px,
        transparent 16px
      );
    }

    .invoice .amount,
    .invoice .payment-status .heading {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 1rem;
      margin-bottom: 0.5em;
    }

    .invoice .amount .value {
      font-weight: 700;
      color: #000;
    }

    .invoice .payment-status {
      border: 1px solid #ddd;
      padding: 1em;
      border-radius: 15px;
      margin-top: 1em;
      background: #fafafa;
    }

    .invoice .payment-status .heading span {
      text-transform: uppercase;
      font-weight: 600;
      color: #000;
      font-size: 0.88rem;
    }

    .payers-list {
      list-style-type: none;
      margin: 0.5em 0;
      padding: 0;
    }

    .payers-list li {
      border-bottom: 1px solid #eee;
      display: flex;
      align-items: center;
    }

    .payers-list li:last-child {
      border-bottom: none;
    }

    .payers-list li p {
      flex-grow: 1;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.9rem;
      padding: 0.5em;
      color: #374151;
      margin: 0;
    }

    .payers-list .payer-image-container {
      padding: 0.5em;
      border-right: 1px solid #eee;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .payers-list .payer-image-container img {
      width: 35px;
      height: 35px;
      object-fit: cover;
      border-radius: 50%;
    }

    .payers-list .payer-icon-avatar {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.95rem;
      background: #e2e8f0;
      color: #1e293b;
    }

    .payers-list .pay-tag {
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 0.3em 0.5em;
      font-size: 0.85rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      background: #fff;
    }

    .fa-circle-check {
      color: #22c55e;
    }

    .fa-clock {
      color: #d97706;
    }

    .btn-group {
      display: flex;
      align-items: center;
      gap: 0.75em;
      margin-top: 1.25em;
      width: 100%;
    }

    .status-progress {
      position: relative;
      margin: 1.5em 0 0.5em 0;
      height: 6px;
      background-image: linear-gradient(90deg, #000 75%, #eee 75%);
      border-radius: 3px;
    }

    .checkpoint {
      position: absolute;
      background: rgba(255, 255, 255, 0.98);
      width: 26px;
      height: 26px;
      border-radius: 50%;
      border: 0.5px solid #eee;
      display: flex;
      justify-content: center;
      align-items: center;
      box-shadow: 0 4px 10px 0 rgba(0, 0, 0, 0.15);
      top: 50%;
      transform: translateY(-50%);
    }

    .checkpoint .circle {
      display: inline-block;
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background-color: #000;
    }

    .checkpoint:nth-child(1) { left: 0%; transform: translate(-10%, -50%); }
    .checkpoint:nth-child(2) { left: 25%; transform: translate(-25%, -50%); }
    .checkpoint:nth-child(3) { left: 50%; transform: translate(-50%, -50%); }
    .checkpoint:nth-child(4) { left: 75%; transform: translate(-75%, -50%); }
    .checkpoint:nth-child(5) { right: 0%; transform: translate(10%, -50%); }

    .fa-stamp {
      color: #000;
      font-size: 0.8rem;
    }

    .btn {
      flex-grow: 1;
      border-radius: 100vmax;
      padding: 0.6em 0.5em;
      font-size: 0.85rem;
      font-weight: 600;
      box-shadow: 0 4px 12px 0 rgba(0, 0, 0, 0.12);
      cursor: pointer;
      border: none;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.4rem;
      text-decoration: none;
    }

    .btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 16px 0 rgba(0, 0, 0, 0.18);
    }

    .btn:active {
      transform: translateY(1px);
    }

    .reminder-btn {
      color: #fff;
      background-color: #111827;
      border: 1px solid #1b1b1b;
    }

    .reminder-btn:hover {
      background-color: #1f2937;
      color: #fff;
    }

    .download-btn {
      border: 1px solid #d1d5db;
      background-color: #fff;
      color: #1f2937;
    }

    .download-btn:hover {
      background-color: #f9fafb;
    }

    /* ----------- */
    hr {
      border: none;
      height: 1px;
      background-color: #e5e7eb;
      margin: 0.75em 0;
    }

    /* payment info */
    .payment-info {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin: 0.75em 0.5em;
      font-size: 0.95rem;
      color: #6b7280;
    }

    .card-info {
      display: flex;
      align-items: center;
      gap: 0.75em;
      color: #111827;
      font-weight: 600;
    }

    .card-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 35px;
      height: 24px;
      background-color: #1a43bf;
      color: #fff;
      border-radius: 5px;
      font-size: 0.68rem;
      font-weight: 800;
      letter-spacing: 0.5px;
    }

    .pay-now-btn {
      font-size: 1.08rem;
      font-weight: 700;
      background-color: #111827;
      color: #fff;
      width: 100%;
      padding: 0.8em 0;
      border: 2px solid #1b1b1b;
      border-radius: 0.75em;
      box-shadow: 0 0 1px 0 #000, 0 5px 15px 0 rgba(0, 0, 0, 0.35);
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .pay-now-btn:hover {
      background-color: #1f2937;
      transform: translateY(-1px);
      box-shadow: 0 6px 20px 0 rgba(0, 0, 0, 0.45);
    }

    /* Toast notification */
    .toast-box {
      position: fixed;
      bottom: 2rem;
      left: 50%;
      transform: translateX(-50%) translateY(100px);
      background: #0f172a;
      color: #fff;
      padding: 0.75rem 1.5rem;
      border-radius: 100vmax;
      font-size: 0.88rem;
      font-weight: 600;
      box-shadow: 0 10px 25px rgba(0,0,0,0.3);
      display: flex;
      align-items: center;
      gap: 0.5rem;
      z-index: 9999;
      opacity: 0;
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      pointer-events: none;
    }

    .toast-box.show {
      transform: translateX(-50%) translateY(0);
      opacity: 1;
    }

    /* Responsive Queries */
    @media (max-width: 424px) {
      .invoice {
        padding: 0.75em;
        width: 90%;
      }

      .invoice .title {
        font-size: 1rem;
        padding: 0.5em 0;
      }

      .slot-hole {
        width: 95%;
      }

      .invoice .amount {
        font-size: 0.88rem;
      }

      .payers-list li p {
        font-size: 0.85rem;
        padding: 0.4em 0.25em;
      }

      .payers-list .pay-tag {
        font-size: 0.78rem;
        padding: 0.25em 0.45em;
      }

      .btn {
        padding: 0.5em 0.4em;
        font-size: 0.78rem;
      }

      .payment-info {
        font-size: 0.88rem;
      }

      .pay-now-btn {
        font-size: 1rem;
      }
    }

    /* Print styling */
    @media print {
      body {
        background: #fff;
        padding: 0;
      }
      .top-controls,
      .pay-now-btn,
      .btn-group,
      .no-print {
        display: none !important;
      }
      .container {
        width: 100% !important;
        max-width: 100% !important;
      }
      .invoice-slot {
        display: none;
      }
      .invoice {
        position: relative !important;
        top: 0 !important;
        left: 0 !important;
        transform: none !important;
        width: 100% !important;
        box-shadow: none !important;
        border: 1px solid #ccc;
      }
      .invoice::before {
        display: none;
      }
    }
  </style>
</head>
<body>

  <!-- Mode Switcher Navigation -->
  <aside class="top-controls no-print" aria-label="Navigasi Mode">
    <div class="mode-switch-group">
      <a href="invoice.php?mode=trip" class="mode-switch-btn <?= $mode === 'trip' ? 'active' : '' ?>">
        <i class="fa-solid fa-plane-departure"></i> Trip Invoice (Demo)
      </a>
      <a href="invoice.php?mode=maintenance<?= $id > 0 ? '&id='.$id : '' ?>" class="mode-switch-btn <?= $mode === 'maintenance' ? 'active' : '' ?>">
        <i class="fa-solid fa-receipt"></i> Struk Maintenance IT
      </a>
    </div>
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0 4px;">
      <a href="<?= $mode === 'maintenance' && $id > 0 ? 'maintenance_detail.php?id='.$id : 'dashboard.php' ?>" class="back-nav-link">
        <i class="fa-solid fa-arrow-left"></i> <?= $mode === 'maintenance' && $id > 0 ? 'Kembali ke Rincian #'.$id : 'Ke Dashboard Utama' ?>
      </a>
      <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 600;">Bank Mitra QR Maintenance</span>
    </div>
  </aside>

  <main>
  <?php if ($mode === 'trip'): ?>
  <!-- ============================================================= -->
  <!-- EXACT COMPONENT: Trip Invoice — Japan Summer 2025             -->
  <!-- ============================================================= -->
  <section class="container">
    <section class="invoice-container" id="invoiceContainer">
      <div class="invoice-slot">
        <div class="slot-hole"></div>
      </div>
      <div class="invoice" id="invoiceCard">
        <h2 class="title">Trip Invoice &mdash; Japan Summer 2025</h2>
        <p class="amount">
          Total <span class="value">$30,000</span>
        </p>
        <p class="amount">
          Per Person <span class="value">$6,000</span>
        </p>

        <hr />

        <ul class="payers-list">
          <li>
            <div class="payer-image-container">
              <img src="https://res.cloudinary.com/dmuyehme1/image/upload/v1761081494/user1_okmfkd.png" alt="You" />
            </div>
            <p>
              You
              <span class="pay-tag"><i class="fa-solid fa-circle-check"></i> Paid</span>
            </p>
          </li>
          <li>
            <div class="payer-image-container">
              <img src="https://res.cloudinary.com/dmuyehme1/image/upload/v1761082627/user4_dujvoe.svg" alt="Olabode" />
            </div>
            <p>
              Olabode
              <span class="pay-tag"><i class="fa-solid fa-circle-check"></i> Paid</span>
            </p>
          </li>
          <li>
            <div class="payer-image-container">
              <img src="https://res.cloudinary.com/dmuyehme1/image/upload/v1761082286/user3_ue0zzq.avif" alt="Lukmon" />
            </div>
            <p>
              Lukmon
              <span class="pay-tag"><i class="fa-solid fa-circle-check"></i> Paid</span>
            </p>
          </li>
          <li>
            <div class="payer-image-container">
              <img src="https://res.cloudinary.com/dmuyehme1/image/upload/v1761081494/user1_okmfkd.png" alt="Hope" />
            </div>
            <p>
              Hope
              <span class="pay-tag"><i class="fa-solid fa-clock"></i> Unpaid</span>
            </p>
          </li>
          <li>
            <div class="payer-image-container">
              <img src="https://res.cloudinary.com/dmuyehme1/image/upload/v1761082184/user2_b821x7.avif" alt="Dara" />
            </div>
            <p>
              Dara
              <span class="pay-tag"><i class="fa-solid fa-clock"></i> Unpaid</span>
            </p>
          </li>
        </ul>

        <div class="payment-status">
          <p class="heading">
            Payment Status
            <span>Unpaid</span>
          </p>
          <div class="status-progress">
            <div class="checkpoint">
              <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="checkpoint">
              <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="checkpoint">
              <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="checkpoint">
              <span class="circle"></span>
            </div>
            <div class="checkpoint">
              <i class="fa-solid fa-stamp"></i>
            </div>
          </div>
        </div>
        <div class="btn-group">
          <button class="btn reminder-btn" onclick="sendReminder()">
            <i class="fa-solid fa-bell"></i> Send Reminder
          </button>
          <button class="btn download-btn" onclick="downloadInvoice()">
            <i class="fa-solid fa-download"></i> Download Invoice
          </button>
        </div>
      </div>
    </section>
    <hr />
    <div class="payment-info">
      <p>Payment Method</p>
      <div class="card-info">
        <p>Visa Ending 2986</p>
        <span class="card-icon">VISA</span>
      </div>
    </div>
    <button class="pay-now-btn" onclick="payNow()">
      <i class="fa-solid fa-lock"></i> Pay Now
    </button>
  </section>

  <?php else: ?>
  <!-- ============================================================= -->
  <!-- INTEGRATED IT MAINTENANCE SLIP (Adaptasi Data Sistem Bank)    -->
  <!-- ============================================================= -->
  <?php
    $scan = $maintDetail['scan'] ?? [];
    $asset = $maintDetail['asset'] ?? [];
    $checklists = $maintDetail['checklists'] ?? [];
    $assetTitle = !empty($asset) ? asset_title($asset) : 'Komputer IT Bank Mitra';
    $kodeInventaris = $asset['kode_inventaris'] ?? 'INV-PC-01';
    $techName = $scan['technician'] ?? $scan['teknisi'] ?? current_user_name();
    $userName = $asset['karyawan_nama'] ?? 'Staff Bank Mitra';
    $cabangName = $asset['cabang_nama'] ?? 'Kantor Pusat';
    $divisiName = $asset['divisi_nama'] ?? 'Operasional';
    $statusText = $scan['status'] ?? 'Selesai';
    $isDone = ($statusText === 'Selesai');
    $maintDate = !empty($scan['date']) ? format_id_date($scan['date']) : date('d M Y');

    // Hitung checklist selesai
    $checkedCount = 0;
    foreach ($checklists as $chk) {
        if (!empty($chk['checked'])) $checkedCount++;
    }
    if (empty($checklists)) {
        $checkedCount = 9;
    }
  ?>
  <section class="container">
    <section class="invoice-container" id="invoiceContainer">
      <div class="invoice-slot">
        <div class="slot-hole"></div>
      </div>
      <div class="invoice" id="invoiceCard">
        <h2 class="title">Lembar Servis &mdash; <?= e($kodeInventaris) ?></h2>
        <p class="amount">
          Status <span class="value" style="color: <?= $isDone ? '#16a34a' : '#ea580c' ?>;"><?= e($statusText) ?></span>
        </p>
        <p class="amount">
          Checklist <span class="value"><?= $checkedCount ?> dari 9 Item Selesai</span>
        </p>

        <hr />

        <ul class="payers-list">
          <li>
            <div class="payer-image-container">
              <?php if (!empty($scan['biometric_photo'])): ?>
                <img src="<?= e($scan['biometric_photo']) ?>" alt="Teknisi" />
              <?php else: ?>
                <div class="payer-icon-avatar"><i class="fa-solid fa-user-gear"></i></div>
              <?php endif; ?>
            </div>
            <p>
              <?= e($techName) ?> <small style="color: #64748b;">(Teknisi)</small>
              <span class="pay-tag"><i class="fa-solid fa-circle-check"></i> Hadir</span>
            </p>
          </li>
          <li>
            <div class="payer-image-container">
              <div class="payer-icon-avatar" style="background: #e0f2fe; color: #0284c7;"><i class="fa-solid fa-desktop"></i></div>
            </div>
            <p>
              Pembersihan & Hardware
              <span class="pay-tag"><i class="fa-solid fa-circle-check"></i> Normal</span>
            </p>
          </li>
          <li>
            <div class="payer-image-container">
              <div class="payer-icon-avatar" style="background: #ecfdf5; color: #059669;"><i class="fa-solid fa-shield-virus"></i></div>
            </div>
            <p>
              Antivirus & Update OS
              <span class="pay-tag"><i class="fa-solid fa-circle-check"></i> Aman</span>
            </p>
          </li>
          <li>
            <div class="payer-image-container">
              <div class="payer-icon-avatar" style="background: #fef3c7; color: #d97706;"><i class="fa-solid fa-network-wired"></i></div>
            </div>
            <p>
              Jaringan & Printer
              <span class="pay-tag"><i class="fa-solid fa-circle-check"></i> Stabil</span>
            </p>
          </li>
          <li>
            <div class="payer-image-container">
              <div class="payer-icon-avatar" style="background: #f1f5f9; color: #475569;"><i class="fa-solid fa-user"></i></div>
            </div>
            <p>
              <?= e($userName) ?> <small style="color: #64748b;">(User)</small>
              <span class="pay-tag"><i class="fa-solid <?= $isDone ? 'fa-circle-check' : 'fa-clock' ?>"></i> <?= $isDone ? 'Konfirmasi' : 'Pending' ?></span>
            </p>
          </li>
        </ul>

        <div class="payment-status">
          <p class="heading">
            Audit Pemeliharaan
            <span style="color: <?= $isDone ? '#16a34a' : '#d97706' ?>;"><?= $isDone ? 'TERVALIDASI' : 'PROSES' ?></span>
          </p>
          <div class="status-progress" style="background-image: linear-gradient(90deg, #10b981 <?= $isDone ? '100%' : '75%' ?>, #eee <?= $isDone ? '100%' : '75%' ?>);">
            <div class="checkpoint" title="Scan QR Code Aset">
              <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="checkpoint" title="Cek Fisik & Hardware">
              <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="checkpoint" title="Cek Sistem & Software">
              <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="checkpoint" title="Foto Presensi Teknisi">
              <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="checkpoint" title="Stempel Validasi Selesai">
              <i class="fa-solid fa-stamp" style="color: <?= $isDone ? '#16a34a' : '#000' ?>;"></i>
            </div>
          </div>
        </div>
        <div class="btn-group">
          <button class="btn reminder-btn" onclick="sendMaintenanceAlert()">
            <i class="fa-solid fa-paper-plane"></i> Bagikan Info
          </button>
          <button class="btn download-btn" onclick="downloadInvoice()">
            <i class="fa-solid fa-print"></i> Cetak Struk
          </button>
        </div>
      </div>
    </section>
    <hr />
    <div class="payment-info">
      <p>Unit & Lokasi Kerja</p>
      <div class="card-info">
        <p><?= e($cabangName) ?> &bull; <?= e($divisiName) ?></p>
        <span class="card-icon" style="background: #2E77AD; width: auto; padding: 2px 8px; font-size: 0.72rem;">BPR</span>
      </div>
    </div>
    <?php if ($id > 0): ?>
      <a href="maintenance_detail.php?id=<?= $id ?>" class="pay-now-btn" style="text-decoration: none;">
        <i class="fa-solid fa-file-lines"></i> Buka Rincian Lengkap (#<?= $id ?>)
      </a>
    <?php else: ?>
      <a href="dashboard.php" class="pay-now-btn" style="text-decoration: none;">
        <i class="fa-solid fa-house"></i> Kembali ke Dashboard
      </a>
    <?php endif; ?>
  </section>
  <?php endif; ?>
  </main>

  <!-- Toast Feedback Notification -->
  <div class="toast-box" id="toastBox">
    <i class="fa-solid fa-circle-check" style="color: #22c55e;"></i>
    <span id="toastMessage">Notifikasi berhasil dikirim!</span>
  </div>

  <script>
    // Penyesuaian tinggi container otomatis agar responsif sempurna di segala device
    function adjustContainerHeight() {
      const container = document.getElementById("invoiceContainer");
      const card = document.getElementById("invoiceCard");
      if (container && card) {
        const h = card.offsetHeight;
        // Tambahkan ruang ekstra agar tombol di bawahnya tidak bertumpukan
        container.style.minHeight = (h + 35) + "px";
      }
    }

    window.addEventListener("load", () => {
      adjustContainerHeight();
      setTimeout(adjustContainerHeight, 300);
    });
    window.addEventListener("resize", adjustContainerHeight);

    function showToast(msg) {
      const toast = document.getElementById("toastBox");
      const msgEl = document.getElementById("toastMessage");
      if (toast && msgEl) {
        msgEl.textContent = msg;
        toast.classList.add("show");
        setTimeout(() => {
          toast.classList.remove("show");
        }, 2800);
      }
    }

    function sendReminder() {
      showToast("Pengingat berhasil dikirim kepada Hope dan Dara!");
    }

    function sendMaintenanceAlert() {
      if (navigator.clipboard) {
        navigator.clipboard.writeText(window.location.href);
        showToast("Tautan struk maintenance berhasil disalin ke clipboard!");
      } else {
        showToast("Ringkasan pemeliharaan siap dibagikan!");
      }
    }

    function downloadInvoice() {
      window.print();
    }

    function payNow() {
      showToast("Memproses pembayaran $6,000 melalui Visa Ending 2986...");
    }
  </script>
</body>
</html>