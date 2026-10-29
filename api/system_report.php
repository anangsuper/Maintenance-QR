<?php
require_once __DIR__ . '/bootstrap.php';
require_login();

// Batasi akses: Peran SDM & Umum tidak dapat mengakses buku panduan & laporan teknis ini
if (is_hrd() && !is_admin()) {
    header('Location: ' . module_url('dashboard.php'));
    exit;
}

// Penanganan Unduh Langsung File PDF Laporan Operasional
if (isset($_GET['action']) && $_GET['action'] === 'download_pdf') {
    $pdfPath = __DIR__ . '/uploads/Laporan_Sistem_Maintenance_IT_Bank_Mitra.pdf';
    if (!file_exists($pdfPath)) {
        $scriptPath = dirname(__DIR__) . '/scratch/generate_system_report_pdf.py';
        if (file_exists($scriptPath)) {
            shell_exec('python ' . escapeshellarg($scriptPath));
        }
    }
    
    if (file_exists($pdfPath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Laporan_Sistem_Maintenance_IT_Bank_Mitra.pdf"');
        header('Content-Length: ' . filesize($pdfPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        readfile($pdfPath);
        exit;
    } else {
        http_response_code(404);
        exit('File PDF laporan belum dibuat.');
    }
}

// Penanganan Unduh Langsung File PDF Dokumentasi Teknis Rekayasa Aplikasi
if (isset($_GET['action']) && $_GET['action'] === 'download_doc_pdf') {
    $pdfPath = __DIR__ . '/uploads/Dokumentasi_Pembuatan_Aplikasi_Bank_Mitra.pdf';
    if (!file_exists($pdfPath)) {
        $scriptPath = dirname(__DIR__) . '/scratch/generate_app_documentation_pdf.py';
        if (file_exists($scriptPath)) {
            shell_exec('python ' . escapeshellarg($scriptPath));
        }
    }
    
    if (file_exists($pdfPath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Dokumentasi_Pembuatan_Aplikasi_Bank_Mitra.pdf"');
        header('Content-Length: ' . filesize($pdfPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        readfile($pdfPath);
        exit;
    } else {
        http_response_code(404);
        exit('File PDF dokumentasi aplikasi belum dibuat.');
    }
}

$pageTitle = 'Buku Panduan & Laporan Sistem Informasi Manajemen Aset, IT & Sarpras Terpadu — PT. BPR Mitratama Arthabuana';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= module_url('assets/vendor/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= module_url('assets/vendor/bootstrap-icons.min.css') ?>">
  <style>
    :root {
      --bm-primary: #003B73;
      --bm-secondary: #0A192F;
      --bm-accent: #D97706;
      --bm-dark: #0F172A;
      --bm-muted: #64748B;
      --bm-light: #F8FAFC;
      --bm-border: #E2E8F0;
    }
    body {
      background-color: #F1F5F9;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      color: #1E293B;
      line-height: 1.55;
    }
    .action-bar {
      position: sticky;
      top: 0;
      z-index: 1050;
      background: rgba(15, 23, 42, 0.95);
      backdrop-filter: blur(8px);
      padding: 12px 20px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    }
    .document-page {
      max-width: 1020px;
      margin: 28px auto 60px auto;
      background: #FFFFFF;
      padding: 48px 58px;
      border-radius: 8px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
      border: 1px solid #E2E8F0;
    }
    .kop-surat {
      border-bottom: 3px double var(--bm-primary);
      padding-bottom: 16px;
      margin-bottom: 24px;
    }
    .kop-logo {
      font-size: 24px;
      font-weight: 800;
      color: var(--bm-primary);
      letter-spacing: -0.5px;
      line-height: 1.1;
    }
    .kop-sub {
      font-size: 11px;
      color: var(--bm-muted);
      text-transform: uppercase;
      letter-spacing: 1px;
      font-weight: 600;
    }
    .kop-meta {
      font-size: 11px;
      color: var(--bm-muted);
      text-align: right;
    }
    .report-title {
      font-size: 21px;
      font-weight: 800;
      color: var(--bm-primary);
      text-align: center;
      margin-bottom: 6px;
      line-height: 1.3;
    }
    .report-subtitle {
      font-size: 13px;
      color: var(--bm-muted);
      text-align: center;
      margin-bottom: 24px;
    }
    .summary-card {
      background: #EFF6FF;
      border: 1px solid #BFDBFE;
      border-left: 5px solid var(--bm-primary);
      border-radius: 6px;
      padding: 18px 22px;
      margin-bottom: 28px;
      font-size: 13px;
    }
    .section-banner {
      background: var(--bm-primary);
      color: #FFFFFF;
      padding: 8px 14px;
      font-size: 13.5px;
      font-weight: 700;
      letter-spacing: 0.5px;
      border-radius: 4px;
      margin-top: 36px;
      margin-bottom: 16px;
      text-transform: uppercase;
    }
    .sub-heading {
      font-size: 14px;
      font-weight: 700;
      color: var(--bm-secondary);
      margin-top: 20px;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .doc-text {
      font-size: 12.5px;
      color: #334155;
      margin-bottom: 10px;
      text-align: justify;
    }
    .doc-table {
      font-size: 11.5px;
      margin-bottom: 18px;
      border-color: #CBD5E1;
    }
    .doc-table th {
      background-color: var(--bm-secondary);
      color: #FFFFFF;
      font-weight: 600;
      padding: 8px 10px;
      vertical-align: middle;
    }
    .doc-table td {
      padding: 8px 10px;
      vertical-align: middle;
    }
    .sign-table {
      width: 100%;
      margin-top: 25px;
      font-size: 11.5px;
      border-collapse: collapse;
      page-break-inside: avoid;
    }
    .sign-table td {
      border: 1px solid #CBD5E1;
      padding: 14px;
      text-align: center;
      background-color: #FAFAFA;
      vertical-align: top;
      width: 50%;
    }
    .sign-space {
      height: 65px;
    }

    @media print {
      body {
        background: #FFFFFF !important;
        color: #000000 !important;
        font-size: 10pt;
      }
      .action-bar {
        display: none !important;
      }
      .document-page {
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: none !important;
        max-width: 100% !important;
      }
      .section-banner {
        background-color: #003B73 !important;
        color: #FFFFFF !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .doc-table th {
        background-color: #0A192F !important;
        color: #FFFFFF !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .summary-card {
        background-color: #EFF6FF !important;
        border-color: #93C5FD !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .sign-table td {
        background-color: #F8FAFC !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .page-break {
        page-break-before: always;
      }
    }
  </style>
</head>
<body>

  <!-- Top Action Toolbar -->
  <div class="action-bar text-white">
    <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div class="d-flex align-items-center gap-3">
        <a href="dashboard.php" class="btn btn-sm btn-outline-light d-flex align-items-center gap-1">
          <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
        </a>
        <div class="vr bg-secondary d-none d-md-block"></div>
        <span class="fw-semibold small d-none d-md-inline">
          <i class="bi bi-shield-lock-fill text-warning me-1"></i>
          Buku Panduan & Laporan Sistem Tata Kelola Aset, IT & Sarpras Terpadu (10 Halaman Lengkap)
        </span>
      </div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="system_report.php?action=download_doc_pdf" class="btn btn-sm btn-warning text-dark fw-bold d-flex align-items-center gap-1.5 shadow-sm" title="Dokumentasi 10 Bab Rekayasa Perangkat Lunak">
          <i class="bi bi-journal-code"></i> PDF Dokumentasi Aplikasi
        </a>
        <a href="system_report.php?action=download_pdf" class="btn btn-sm btn-danger fw-bold d-flex align-items-center gap-1.5 shadow-sm" title="Laporan Operasional & Tata Kelola IT/Sarpras">
          <i class="bi bi-file-earmark-pdf-fill"></i> PDF Laporan Operasional
        </a>
        <a href="uploads/Dokumentasi_Pembuatan_Aplikasi_Bank_Mitra.pdf" target="_blank" class="btn btn-sm btn-outline-info d-flex align-items-center gap-1">
          <i class="bi bi-box-arrow-up-right"></i> Buka Dokumen
        </a>
        <button onclick="window.print()" class="btn btn-sm btn-primary d-flex align-items-center gap-2 shadow-sm">
          <i class="bi bi-printer-fill"></i> Cetak A4
        </button>
      </div>
    </div>
  </div>

  <!-- Document Page -->
  <div class="document-page">
    
    <!-- Kop Surat Resmi Bank Mitra -->
    <div class="kop-surat d-flex justify-content-between align-items-center">
      <div>
        <div class="kop-logo">PT. BPR MITRATAMA ARTHABUANA</div>
        <div class="kop-sub">Bank Mitra &bull; Kantor Pusat Operasional & Jaringan Kantor Cabang</div>
        <div class="small text-muted mt-1" style="font-size: 11px;">
          Sinergi Divisi Teknologi Informasi, Divisi SDM & Umum (Sarpras), dan Satuan Kerja Audit Intern (SKAI)
        </div>
      </div>
      <div class="kop-meta">
        <div><strong>DOKUMEN RESMI TATA KELOLA</strong></div>
        <div>No: BPR-MITRA/IT-SDM-MAN/2026/X</div>
        <div>Tanggal Terbit: 22 Oktober 2026</div>
        <div>Klasifikasi: <span class="badge bg-danger-subtle text-danger border border-danger-subtle">RAHASIA INTERNAL</span></div>
      </div>
    </div>

    <!-- Judul Dokumen -->
    <div class="report-title">
      BUKU PANDUAN, ARSITEKTUR & LAPORAN OPERASIONAL KOMPREHENSIF<br>
      SISTEM INFORMASI MANAJEMEN ASET, PEMELIHARAAN IT & SARPRAS TERPADU
    </div>
    <div class="report-subtitle">
      Kolaborasi Strategis Divisi IT, Divisi SDM & Umum (Sarana Prasarana), Auditor Internal SKAI & Pimpinan Cabang
    </div>

    <!-- Ringkasan Eksekutif -->
    <div class="summary-card">
      <div class="fw-bold text-primary mb-1 d-flex align-items-center gap-1">
        <i class="bi bi-info-circle-fill"></i> RINGKASAN EKSEKUTIF & TUJUAN PENERAPAN SISTEM
      </div>
      <p class="mb-2 text-secondary" style="line-height: 1.6;">
        Sistem Informasi Manajemen Aset, Pemeliharaan IT & Sarana Prasarana (<i>QR-Maintenance & Facility Management System</i>) PT. BPR Mitratama Arthabuana dikembangkan untuk menjawab kebutuhan digitalisasi tata kelola operasional bank secara menyeluruh. Sistem ini <strong>bukan semata-mata aplikasi teknis untuk Divisi IT</strong>, melainkan platform kolaborasi terpadu antara <strong>Divisi SDM & Umum (Sarpras)</strong> sebagai penanggung jawab kepemilikan fisik fasilitas kantor, <strong>Divisi IT</strong> sebagai pengelola kelaikan perangkat komputasi, <strong>Auditor Internal (SKAI)</strong> sebagai pengawas kepatuhan regulasi perbankan, serta <strong>Pimpinan Cabang</strong> sebagai pengendali operasional unit kerja.
      </p>
      <p class="mb-0 text-secondary" style="line-height: 1.6;">
        Melalui kode stiker QR unik, denah meja interaktif 2D CAD layout, mutasi Berita Acara Serah Terima (BAST) otomatis, memo pengadaan barang digital, log keamanan ruang server, serta jejak audit permanen (<i>audit trail</i>), sistem ini memastikan kepatuhan penuh terhadap <strong>Standar Ketentuan Penyelenggaraan Teknologi Informasi, Manajemen Risiko &amp; Ketahanan Siber Perbankan Terkini</strong>.
      </p>
    </div>

    <!-- ========================================================================= -->
    <!-- BAB 1 -->
    <!-- ========================================================================= -->
    <div class="section-banner">
      BAB 1. LANDASAN SISTEM, SINERGI LINTAS DIVISI & ARSITEKTUR TEKNOLOGI
    </div>

    <div class="sub-heading">
      <i class="bi bi-diagram-3-fill text-primary"></i> 1.1 Mengapa Bukan Hanya untuk IT: Sinergi IT dan SDM & Umum (Sarpras)
    </div>
    <p class="doc-text">
      Dalam operasional perbankan di PT. BPR Mitratama Arthabuana, pengelolaan fasilitas kantor terbagi ke dalam dua divisi utama yang saling berkaitan erat:
    </p>
    <ul class="doc-text">
      <li><strong>Peran Divisi SDM & Umum (Sarana & Prasarana):</strong> Memiliki wewenang kepemilikan dan inventarisasi seluruh barang modal fisik kantor (mebeler, kursi teller, brankas khazanah, pendingin udara AC, genset cadangan, dan kendaraan dinas operasional). Divisi ini bertanggung jawab atas pembukuan Kartu Inventaris Ruangan (KIR 531+ item Core Banking), pengajuan anggaran belanja barang baru melalui Memo Pengajuan, pemindahan barang antar-cabang dengan Berita Acara Serah Terima (BAST), serta proses pelelangan/pemusnahan aset afkir (disposal).</li>
      <li><strong>Peran Divisi Teknologi Informasi (IT):</strong> Bertanggung jawab atas kelaikan teknis, spesifikasi hardware, kestabilan jaringan LAN/VPN, pembaruan antivirus, backup data nasabah, serta pemeliharaan preventif (Preventive Maintenance) seluruh komputer, server, dan printer kasir.</li>
    </ul>
    <p class="doc-text">
      Sebelum sistem ini diimplementasikan, terjadi pemisahan data (<i>data silo</i>): Divisi SDM memiliki daftar pembukuan inventaris sendiri di spreadsheet Core Banking, sementara Divisi IT memiliki catatan servis komputer terpisah. Melalui aplikasi terpadu ini, <strong>keduanya terintegrasi dalam satu sistem</strong>: setiap unit komputer diakui sebagai aset sarpras oleh SDM sekaligus sebagai objek pemeliharaan teknis oleh IT.
    </p>

    <div class="sub-heading">
      <i class="bi bi-cpu-fill text-primary"></i> 1.2 Arsitektur Perangkat Lunak & Komponen Teknologi
    </div>
    <div class="table-responsive">
      <table class="table table-bordered table-striped doc-table">
        <thead>
          <tr>
            <th style="width: 20%;">Lapisan Komponen</th>
            <th style="width: 30%;">Teknologi Terpilih</th>
            <th style="width: 50%;">Keunggulan Strategis bagi Bank Mitra</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="fw-bold text-primary">Backend Core</td>
            <td>PHP 8.2 Native + PDO Driver</td>
            <td>Eksekusi kode ultra-cepat (0ms latency), konsumsi RAM sangat rendah, bebas resiko ketergantungan library pihak ketiga, aman dari SQL Injection berkat prepared statements.</td>
          </tr>
          <tr>
            <td class="fw-bold text-primary">Database RDBMS</td>
            <td>MySQL / MariaDB (InnoDB)</td>
            <td>Mendukung transaksi perbankan ACID, integritas Foreign Key relasional, cascade trigger pada tabel log audit, dan pencadangan harian otomatis.</td>
          </tr>
          <tr>
            <td class="fw-bold text-primary">Frontend UI / UX</td>
            <td>Bootstrap 5.3 + Custom Micro-CSS</td>
            <td>Tampilan antarmuka responsif ramah monitor desktop pimpinan maupun layar smartphone teknisi lapangan. Dilengkapi auto Dark/Light mode.</td>
          </tr>
          <tr>
            <td class="fw-bold text-primary">Interactive CAD Engine</td>
            <td>SortableJS 1.15.2 (Pointer Fallback)</td>
            <td>Mekanisme penataan denah meja 2D secara drag-and-drop langsung tanpa batas browser, mendukung mouse biasa dan sentuhan jari pada tablet/HP.</td>
          </tr>
          <tr>
            <td class="fw-bold text-primary">Offline PWA Engine</td>
            <td>Service Worker + LocalStorage Queue</td>
            <td>Teknisi tetap dapat menginspeksi PC di ruang khazanah/basement tanpa sinyal internet; data checklist otomatis tersinkronisasi saat kembali online.</td>
          </tr>
          <tr>
            <td class="fw-bold text-primary">Autentikasi & Kriptografi</td>
            <td>Argon2ID + WebAuthn Biometrik</td>
            <td>Enkripsi kata sandi standar keamanan perbankan internasional, dukungan sidik jari/wajah FIDO2, proteksi lockout brute-force otomatis setelah 5x gagal.</td>
          </tr>
          <tr>
            <td class="fw-bold text-primary">Tokenisasi Stiker QR</td>
            <td>Cryptographic Random SHA-256 Hash</td>
            <td>URL QR tidak menampilkan ID database internal, mencegah manipulasi URL atau crawling data oleh pihak tidak bertanggung jawab.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ========================================================================= -->
    <!-- BAB 2 -->
    <!-- ========================================================================= -->
    <div class="section-banner page-break">
      BAB 2. MATRIKS HAK AKSES PENGGUNA (ROLE-BASED ACCESS CONTROL)
    </div>

    <div class="sub-heading">
      <i class="bi bi-people-fill text-primary"></i> 2.1 Siapa Saja yang Memiliki Akses ke Sistem (8 Profil Pengguna)
    </div>
    <p class="doc-text">
      Aplikasi menerapkan prinsip keamanan perbankan <i>Least Privilege Principle</i> dan <i>Segregation of Duties (SoD)</i>. Hak akses dibagi secara ketat ke dalam 8 kategori pengguna:
    </p>

    <div class="table-responsive">
      <table class="table table-bordered table-striped doc-table">
        <thead>
          <tr>
            <th style="width: 5%;">No</th>
            <th style="width: 22%;">Peran (Role)</th>
            <th style="width: 23%;">Unit Kerja</th>
            <th style="width: 50%;">Wewenang, Menu & Batasan Tindakan Operasional</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="text-center fw-bold">1</td>
            <td class="fw-bold text-dark">Administrator IT</td>
            <td>Divisi IT Pusat</td>
            <td>Akses mutlak (Superuser): Master cabang/divisi, akun pengguna, reset password, unlock user lockout, manajemen denah meja cabang, registri PC, cetak stiker QR massal, dan log server.</td>
          </tr>
          <tr>
            <td class="text-center fw-bold">2</td>
            <td class="fw-bold text-dark">Teknisi IT Lapangan</td>
            <td>Staff Support IT Cabang/Pusat</td>
            <td>Eksekusi pemeliharaan lapangan: Pindai QR di casing PC, isi 9 butir checklist, unggah foto bukti kerusakan fisik, input tindakan servis part, verifikasi lampu denah meja, dan offline PWA.</td>
          </tr>
          <tr>
            <td class="text-center fw-bold">3</td>
            <td class="fw-bold text-dark">SDM & Umum (Sarpras)</td>
            <td>Divisi SDM & Bagian Umum</td>
            <td>Pengelola inventaris fisik kantor non-IT: Kartu Inventaris Ruangan (KIR 531+ item), galeri foto mebel/AC/brankas, pengajuan memo pengadaan baru, mutasi sarpras, cetak BAST, dan disposal aset.</td>
          </tr>
          <tr>
            <td class="text-center fw-bold">4</td>
            <td class="fw-bold text-dark">Supervisor SDM & IT</td>
            <td>Kasubdiv / Team Lead</td>
            <td>Pemeriksa & verifikator: Memvalidasi hasil inspeksi teknisi, memverifikasi draft memo pengajuan anggaran, menyetujui mutasi ruangan, serta mengawasi kelengkapan foto fisik barang inventaris.</td>
          </tr>
          <tr>
            <td class="text-center fw-bold">5</td>
            <td class="fw-bold text-dark">Auditor Internal (SKAI)</td>
            <td>Satuan Kerja Audit Intern</td>
            <td>Pengawasan kepatuhan independen (Read-Only): Mengakses seluruh log audit trail, riwayat inspeksi bulanan, rekap temuan kendala operasional cabang, log buku tamu ruang server, dan ekspor CSV audit kepatuhan.</td>
          </tr>
          <tr>
            <td class="text-center fw-bold">6</td>
            <td class="fw-bold text-dark">Kepala Cabang (Pincab)</td>
            <td>Pimpinan Kantor Cabang</td>
            <td>Monitoring visual denah meja cabang: Memantau kondisi kerja teller/CS, menandatangani persetujuan memo pengadaan lokal, menandatangani BAST serah terima karyawan, dan evaluasi kesiapan operasional.</td>
          </tr>
          <tr>
            <td class="text-center fw-bold">7</td>
            <td class="fw-bold text-dark">Karyawan Cabang / Staff</td>
            <td>Teller, CS, AO, Back Office</td>
            <td>Pengguna meja kerja: Memindai stiker QR di PC masing-masing untuk melihat spesifikasi unit, status pemeliharaan, serta melaporkan kendala komputer langsung ke IT tanpa perlu login kompleks.</td>
          </tr>
          <tr>
            <td class="text-center fw-bold">8</td>
            <td class="fw-bold text-dark">Tamu / Vendor Eksternal</td>
            <td>Vendor AC, Telco, Auditor Pengawas, Pihak Ketiga</td>
            <td>Akses terbatas portal publik ruang server: Memindai stiker QR check-in pintu server, mengisi form izin masuk, mencatat nama instansi, suhu AC, foto selfie/tanda pengenal, dan check-out saat selesai.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="sub-heading">
      <i class="bi bi-shield-check text-primary"></i> 2.2 Matriks Wewenang CRUD (Create, Read, Update, Delete) per Modul
    </div>
    <div class="table-responsive">
      <table class="table table-bordered table-striped doc-table text-center">
        <thead>
          <tr>
            <th class="text-start" style="width: 30%;">Modul Sistem</th>
            <th style="width: 14%;">Admin IT</th>
            <th style="width: 14%;">Teknisi IT</th>
            <th style="width: 14%;">SDM & Umum</th>
            <th style="width: 14%;">Auditor SKAI</th>
            <th style="width: 14%;">Kepala Cabang</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="text-start fw-bold">Master Komputer & PC (81 Unit)</td>
            <td><span class="badge bg-success">CRUD Penuh</span></td>
            <td><span class="badge bg-primary">Read / Update</span></td>
            <td><span class="badge bg-secondary">Read Only</span></td>
            <td><span class="badge bg-secondary">Read Only</span></td>
            <td><span class="badge bg-secondary">Read Only</span></td>
          </tr>
          <tr>
            <td class="text-start fw-bold">Kartu Inventaris Core Banking (531 Item)</td>
            <td><span class="badge bg-success">CRUD Penuh</span></td>
            <td><span class="badge bg-secondary">Read Only</span></td>
            <td><span class="badge bg-success">CRUD Penuh</span></td>
            <td><span class="badge bg-secondary">Read Only</span></td>
            <td><span class="badge bg-secondary">Read Only</span></td>
          </tr>
          <tr>
            <td class="text-start fw-bold">Denah Meja Cabang (Drag & Drop)</td>
            <td><span class="badge bg-primary">Atur & Geser</span></td>
            <td><span class="badge bg-primary">Lihat & Geser</span></td>
            <td><span class="badge bg-primary">Lihat & Geser</span></td>
            <td><span class="badge bg-secondary">Read Only</span></td>
            <td><span class="badge bg-info text-dark">Read & Monitor</span></td>
          </tr>
          <tr>
            <td class="text-start fw-bold">Checklist 9 Butir Pemeliharaan</td>
            <td><span class="badge bg-success">Verifikasi</span></td>
            <td><span class="badge bg-primary">Input & Foto</span></td>
            <td><span class="badge bg-secondary">Read Only</span></td>
            <td><span class="badge bg-warning text-dark">Audit & Rekap</span></td>
            <td><span class="badge bg-secondary">Read Only</span></td>
          </tr>
          <tr>
            <td class="text-start fw-bold">Memo Pengajuan Barang Baru</td>
            <td><span class="badge bg-success">Persetujuan</span></td>
            <td><span class="badge bg-primary">Draft Usulan</span></td>
            <td><span class="badge bg-success">Buat & Proses</span></td>
            <td><span class="badge bg-secondary">Audit Anggaran</span></td>
            <td><span class="badge bg-success">Persetujuan Cabang</span></td>
          </tr>
          <tr>
            <td class="text-start fw-bold">Mutasi Inventaris & Cetak BAST</td>
            <td><span class="badge bg-primary">Proses Aset IT</span></td>
            <td><span class="badge bg-secondary">Read Only</span></td>
            <td><span class="badge bg-success">Proses Sarpras</span></td>
            <td><span class="badge bg-secondary">Audit Berkas</span></td>
            <td><span class="badge bg-info text-dark">Tanda Tangan BAST</span></td>
          </tr>
          <tr>
            <td class="text-start fw-bold">Penghapusan Aset (Afkir/Disposal)</td>
            <td><span class="badge bg-primary">Rekomendasi IT</span></td>
            <td><span class="badge bg-secondary">Cek Kondisi</span></td>
            <td><span class="badge bg-danger">Eksekusi Afkir</span></td>
            <td><span class="badge bg-secondary">Audit Pemusnahan</span></td>
            <td><span class="badge bg-success">Persetujuan</span></td>
          </tr>
          <tr>
            <td class="text-start fw-bold">Log Buku Tamu Ruang Server</td>
            <td><span class="badge bg-info text-dark">Monitoring</span></td>
            <td><span class="badge bg-primary">Pendamping</span></td>
            <td><span class="badge bg-secondary">Read Only</span></td>
            <td><span class="badge bg-warning text-dark">Audit Kepatuhan</span></td>
            <td><span class="badge bg-secondary">Read Only</span></td>
          </tr>
          <tr>
            <td class="text-start fw-bold">Log Aktivitas (Audit Trail)</td>
            <td><span class="badge bg-info text-dark">Lihat & Filter</span></td>
            <td><span class="badge bg-light text-muted border">No Access</span></td>
            <td><span class="badge bg-light text-muted border">No Access</span></td>
            <td><span class="badge bg-success">Ekspor & Analisis</span></td>
            <td><span class="badge bg-light text-muted border">No Access</span></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ========================================================================= -->
    <!-- BAB 3 -->
    <!-- ========================================================================= -->
    <div class="section-banner page-break">
      BAB 3. MODUL SARANA & PRASARANA (DIVISI SDM & UMUM)
    </div>

    <div class="sub-heading">
      <i class="bi bi-box-seam text-primary"></i> 3.1 Pengelolaan 531+ Kartu Inventaris Core Banking & KIR
    </div>
    <p class="doc-text">
      Divisi SDM & Umum bertanggung jawab atas pencatatan seluruh barang modal dan sarana fisik kantor PT. BPR Mitratama Arthabuana. Sistem menyediakan modul terpisah untuk mengelola 531+ aset fisik yang terdaftar pada neraca Core Banking bank:
    </p>
    <ul class="doc-text">
      <li><strong>Struktur Nomor Rekening Resmi:</strong> Sistem memetakan kode rekening aset sesuai standar pembukuan akuntansi bank, contoh: <code>01.164.01.0001</code> (01 = Kantor Pusat, 164 = Inventaris Kantor, 01 = Golongan Mebeler, 0001 = Urutan Barang).</li>
      <li><strong>Kartu Inventaris Ruangan (KIR):</strong> Setiap ruangan kantor cabang (Customer Service, Teller, Ruang Arsip, Ruang Rapat, Dapur, Server) memiliki daftar inventaris digital yang mencantumkan nama barang, merk, nomor seri pabrik, tahun perolehan, sumber dana, dan kondisi terkini.</li>
      <li><strong>Cetak Kartu Standar Format A4 & CR80:</strong> Setiap kartu inventaris dapat dicetak sewaktu-waktu dalam format lembar kerja resmi perbankan atau kartu CR80 untuk disematkan pada lemari kaca ruangan.</li>
    </ul>

    <div class="sub-heading">
      <i class="bi bi-images text-primary"></i> 3.2 Galeri Foto Fisik Inventaris & Audit Visual Lapangan
    </div>
    <p class="doc-text">
      Sesuai standar audit fisik, keberadaan barang tidak boleh hanya tercatat di atas kertas melainkan wajib diverifikasi secara visual:
    </p>
    <ul class="doc-text">
      <li><strong>Audit Foto Fisik Terverifikasi:</strong> Dari 531 item inventaris bank, sistem memonitor persentase barang yang telah memiliki foto fisik asli vs barang yang belum difoto (<i>Unphotographed Backlog</i>).</li>
      <li><strong>Watermark Integritas Digital:</strong> Foto yang diunggah staf SDM otomatis dilengkapi metadata terenkripsi: tanggal unggah, jam, nama staf pengunggah, dan nomor rekening inventaris untuk mencegah manipulasi gambar.</li>
      <li><strong>Filter Multifaset Cabang:</strong> Staf SDM dapat memfilter galeri foto secara instan berdasarkan kantor cabang (01 Pusat, 02 Batulicin, 03 Martapura, 04 Tanjung, 05 Handil Bakti) maupun kategori jenis barang.</li>
    </ul>

    <div class="sub-heading">
      <i class="bi bi-arrow-left-right text-primary"></i> 3.3 Alur Mutasi Inventaris & Berita Acara Serah Terima (BAST) Otomatis
    </div>
    <p class="doc-text">
      Pemindahan fasilitas fisik kantor wajib memiliki dasar administrasi hukum yang sah:
    </p>
    <ol class="doc-text">
      <li>Staf SDM memilih barang yang akan dipindahkan dari menu <strong>Mutasi Inventaris</strong>.</li>
      <li>Masukkan kantor cabang/ruangan penerima serta nama pejabat penanggung jawab baru.</li>
      <li>Sistem secara otomatis menerbitkan dokumen <strong>Berita Acara Serah Terima (BAST)</strong> lengkap dengan nomor dinas resmi, rincian spesifikasi barang, tanggal penyerahan, dan klausul tanggung jawab pemeliharaan.</li>
      <li>Kedua belah pihak (Pihak Pertama Yang Menyerahkan dan Pihak Kedua Yang Menerima) membubuhkan tanda tangan dinas yang diarsip secara elektronik.</li>
    </ol>

    <div class="sub-heading">
      <i class="bi bi-trash3 text-primary"></i> 3.4 Memo Pengajuan Pengadaan & Prosedur Afkir (Disposal)
    </div>
    <ul class="doc-text">
      <li><strong>Memo Pengajuan Digital:</strong> Apabila diperlukan penambahan AC, kursi ergonomis, genset, atau perangkat IT baru, staf membuat memo dinas berjenjang. Memo memuat latar belakang, justifikasi kebutuhan, estimasi anggaran, dan tanda tangan bertingkat hingga disetujui Direksi.</li>
      <li><strong>Penghapusan Aset (Afkir / Disposal):</strong> Barang yang rusak permanen atau usia ekonomisnya telah habis didaftarkan ke modul Disposal. Sistem menerbitkan <strong>Berita Acara Penghapusan Aset</strong> untuk dasar akuntansi penghapusan dari aktiva tetap neraca bank.</li>
    </ul>

    <!-- ========================================================================= -->
    <!-- BAB 4 -->
    <!-- ========================================================================= -->
    <div class="section-banner page-break">
      BAB 4. MODUL OPERASIONAL & PEMELIHARAAN IT (DIVISI IT)
    </div>

    <div class="sub-heading">
      <i class="bi bi-pc-display text-primary"></i> 4.1 Manajemen 81 Unit Komputer, Laptop, Printer & Server Cabang
    </div>
    <p class="doc-text">
      Divisi IT mengelola seluruh armada perangkat komputasi yang menunjang operasional harian kasir teller, customer service, analisis kredit, akuntansi, dan server kantor pusat:
    </p>
    <ul class="doc-text">
      <li><strong>Registri Komprehensif:</strong> Mencatat merk, tipe prosesor, besaran RAM, kapasitas SSD/HDD, kartu grafis, alamat IP static, sistem operasi resmi berlisensi, nama karyawan pemegang komputer, dan nomor seri pabrikan.</li>
      <li><strong>Stiker Label QR Fisik:</strong> Setiap casing PC dan monitor ditempeli stiker QR Code tahan luntur. Pindai stiker langsung membuka Kartu Kontrol Digital tanpa perlu mencari manual di komputer.</li>
    </ul>

    <div class="sub-heading">
      <i class="bi bi-clipboard-check text-primary"></i> 4.2 Standar 9 Butir Pemeriksaan Rutin Bulanan (Preventive Maintenance)
    </div>
    <div class="table-responsive">
      <table class="table table-bordered table-striped doc-table">
        <thead>
          <tr>
            <th style="width: 5%;">No</th>
            <th style="width: 25%;">Parameter Pemeriksaan</th>
            <th style="width: 35%;">Metode & Standar Kelayakan</th>
            <th style="width: 35%;">Tindakan Korektif Jika Tidak Lolos</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="text-center fw-bold">1</td>
            <td class="fw-bold">Kebersihan Fisik CPU & Kipas</td>
            <td>Bebas debu tebal, kipas pendingin berputar normal tanpa suara bising, ventilasi lancar.</td>
            <td>Pembersihan menggunakan blower udara & penggantian thermal paste prosesor.</td>
          </tr>
          <tr>
            <td class="text-center fw-bold">2</td>
            <td class="fw-bold">Display Monitor & Kabel VGA/HDMI</td>
            <td>Tampilan stabil, resolusi tajam, tidak ada garis cacat (dead pixel), kabel kencang.</td>
            <td>Kencangkan baut port display atau penggantian unit kabel/monitor.</td>
          </tr>
          <tr>
            <td class="text-center fw-bold">3</td>
            <td class="fw-bold">Keyboard, Mouse & USB Port</td>
            <td>Seluruh tombol merespons cepat, kursor mouse stabil, port USB membaca normal.</td>
            <td>Pembersihan kontak atau penggantian peripheral cadangan.</td>
          </tr>
          <tr>
            <td class="text-center fw-bold">4</td>
            <td class="fw-bold">Antivirus Terupdate & Real-time</td>
            <td>Definisi virus up-to-date, proteksi aktif, firewall menyala, tidak ada notifikasi malware.</td>
            <td>Pembaruan database virus manual, karantina file, atau re-install proteksi.</td>
          </tr>
          <tr>
            <td class="text-center fw-bold">5</td>
            <td class="fw-bold">OS Patch & Lisensi Windows</td>
            <td>Sistem operasi genuine, patch keamanan Microsoft terpasang, OS boot normal.</td>
            <td>Jalankan Windows Update atau perbaikan integritas file sistem (SFC scan).</td>
          </tr>
          <tr>
            <td class="text-center fw-bold">6</td>
            <td class="fw-bold">Kesehatan Penyimpanan (HDD/SSD)</td>
            <td>S.M.A.R.T status Good, suhu di bawah 50&deg;C, sisa kapasitas storage di atas 20%.</td>
            <td>Pembersihan cache/temp files, defragmentasi, atau kloning SSD baru.</td>
          </tr>
          <tr>
            <td class="text-center fw-bold">7</td>
            <td class="fw-bold">Koneksi Jaringan LAN & IP Static</td>
            <td>Konektor RJ45 kokoh, ping ke Core Banking server &lt; 2ms tanpa packet loss.</td>
            <td>Crimping ulang konektor RJ45 atau pergantian port switch hub.</td>
          </tr>
          <tr>
            <td class="text-center fw-bold">8</td>
            <td class="fw-bold">Kelistrikan & Baterai UPS Meja</td>
            <td>Grounding stop kontak &lt; 2 Volt AC, UPS mampu menahan daya saat listrik PLN padam.</td>
            <td>Kalibrasi kelistrikan teknisi gedung atau pergantian baterai UPS 12V.</td>
          </tr>
          <tr>
            <td class="text-center fw-bold">9</td>
            <td class="fw-bold">Pencadangan (Backup) Data Kerja</td>
            <td>Data pekerjaan nasabah tersimpan di server jaringan atau OneDrive bank terenkripsi.</td>
            <td>Sinkronisasi folder data kerja staf ke NAS Server Pusat.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ========================================================================= -->
    <!-- BAB 5 -->
    <!-- ========================================================================= -->
    <div class="section-banner page-break">
      BAB 5. DENAH MEJA INTERAKTIF 2D KANTOR CABANG (INTERACTIVE FLOOR PLAN)
    </div>

    <div class="sub-heading">
      <i class="bi bi-layout-text-window-reverse text-primary"></i> 5.1 Konsep CAD Layout Realistis & Visualisasi Tata Ruang Kerja
    </div>
    <p class="doc-text">
      Modul <b>Denah Meja Kantor</b> merepresentasikan denah fisik kantor cabang dalam format visual 2D modern. Setiap zona ruangan kantor cabang ditata menyerupai denah tata ruang sesungguhnya:
    </p>
    <ul class="doc-text">
      <li><strong>Zonasi Ruangan Kantor:</strong> Front Office (Teller 1 s/d Teller 4), Customer Service (CS Desk 1-3), Ruang Analis Kredit & AO, Back Office (Akuntansi, Kliring & Pembukuan), Ruang Khazanah / Brankas, Ruang Server IT, serta Ruang Pimpinan Cabang.</li>
      <li><strong>Kartu Meja Individual:</strong> Setiap meja memuat label nama meja (contoh: <code>TELLER-01</code>), nama pemegang komputer, nomor seri inventaris, alamat IP, dan tombol aksi cepat.</li>
    </ul>

    <div class="sub-heading">
      <i class="bi bi-broadcast text-primary"></i> 5.2 Lampu Indikator Kesehatan Meja Real-Time (Pulse Dot Alert)
    </div>
    <ul class="doc-text">
      <li><span class="badge bg-success">HIJAU SOLID</span>: Unit komputer dalam kondisi normal prima, seluruh 9 butir checklist pemeliharaan bulan berjalan telah selesai dilaksanakan tanpa temuan kendala.</li>
      <li><span class="badge bg-warning text-dark">KUNING SOLID</span>: Komputer belum diperiksa pada siklus pemeliharaan bulan ini. Menjadi pengingat teknisi untuk segera melakukan kunjungan servis.</li>
      <li><span class="badge bg-danger">MERAH BERKEDIP (ALERT)</span>: Terjadi <strong>temuan kerusakan aktif</strong> pada workstation tersebut (misal monitor mati, OS terserang virus, atau harddisk bad sector). Teknisi dan Pincab dapat langsung melihat posisi fisik meja yang rusak secara seketika.</li>
    </ul>

    <div class="sub-heading">
      <i class="bi bi-arrows-move text-primary"></i> 5.3 Direct Drag & Drop Interaktif (SortableJS + Pointer Fallback)
    </div>
    <p class="doc-text">
      Penataan tata letak meja kantor dilakukan secara sangat mudah dan intuitif: Cukup seret (drag) kartu meja dari satu ruangan (misal: Ruang CS) dan jatuhkan (drop) ke ruangan lain (misal: Ruang Kredit) menggunakan mouse komputer atau jari pada layar sentuh tablet/HP. Sistem secara langsung merekam koordinat zona dan nomor urut meja ke tabel database <code>asset_desk_positions</code> tanpa perlu menekan tombol 'Simpan' manual.
    </p>

    <!-- ========================================================================= -->
    <!-- BAB 6 -->
    <!-- ========================================================================= -->
    <div class="section-banner page-break">
      BAB 6. KEAMANAN RUANG SERVER & BUKU TAMU DIGITAL (SERVER ROOM LOG)
    </div>

    <div class="sub-heading">
      <i class="bi bi-door-closed-fill text-primary"></i> 6.1 Regulasi Pengamanan Fisik Ruang Server Perbankan (Physical Security Compliance)
    </div>
    <p class="doc-text">
      Sesuai ketentuan regulator perbankan, data center kantor pusat dan server room kantor cabang BPR merupakan area terbatas (<i>restricted access zone</i>) berisiko tinggi. Pintu ruang server wajib selalu terkunci dan setiap individu yang masuk wajib tercatat dalam Buku Tamu Resmi. Sistem ini mendigitalkan formulir buku tamu kertas konvensional menjadi <strong>Sistem Registrasi Tamu Ruang Server Berbasis QR Code</strong>.
    </p>

    <div class="sub-heading">
      <i class="bi bi-qr-code text-primary"></i> 6.2 Alur Pemindaian QR Check-In & Check-Out Pintu Ruang Server
    </div>
    <ol class="doc-text">
      <li><strong>Pemindaian Awal (Check-In):</strong> Personel IT, teknisi vendor pendingin AC, teknisi fiber optik Telkom, maupun auditor eksternal memindai QR menggunakan kamera smartphone.</li>
      <li><strong>Formulir Izin Kunjungan:</strong> Pengunjung memasukkan Nama Lengkap, Instansi Asal, Nomor HP, Keperluan Pekerjaan (misal: Maintenance UPS, Tarik Kabel FO, Audit Fisik Server), serta mengambil foto selfie / kartu identitas.</li>
      <li><strong>Pencatatan Suhu Awal:</strong> Pengunjung mencatat suhu termometer ruangan saat pertama kali masuk (standar: 18&deg;C - 22&deg;C).</li>
      <li><strong>Pelaksanaan Pekerjaan:</strong> Pekerjaan dilakukan dengan didampingi oleh staf IT bank.</li>
      <li><strong>Pencatatan Keluar (Check-Out):</strong> Sebelum meninggalkan ruangan, pengunjung memindai ulang QR atau menekan tombol check-out, mencatat jam keluar, suhu akhir AC ruangan, serta ringkasan hasil pekerjaan.</li>
    </ol>

    <!-- ========================================================================= -->
    <!-- BAB 7 -->
    <!-- ========================================================================= -->
    <div class="section-banner page-break">
      BAB 7. STANDAR PENYELENGGARAAN TI, KETAHANAN SIBER & JEJAK AUDIT (AUDIT TRAIL)
    </div>

    <div class="sub-heading">
      <i class="bi bi-shield-lock-fill text-success"></i> 7.1 Keselarasan dengan Ketentuan Penyelenggaraan TI & Ketahanan Siber Perbankan Terkini
    </div>
    <div class="table-responsive">
      <table class="table table-bordered table-striped doc-table">
        <thead>
          <tr>
            <th style="width: 20%;">Pilar Pengaturan</th>
            <th style="width: 38%;">Amanat Ketentuan Regulator Terkini</th>
            <th style="width: 42%;">Penerapan Nyata Pada Sistem Bank Mitra</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="fw-bold">1. Penguatan Tata Kelola & Komite TI</td>
            <td>Kewajiban bank menetapkan tanggung jawab strategis Direksi/Komisaris dan komite pengarah TI lintas fungsi operasional.</td>
            <td>Integrasi wewenang berjenjang antara Direksi, Kepala Divisi IT, SDM/Umum, serta penandatanganan memo pengadaan terpadu.</td>
          </tr>
          <tr>
            <td class="fw-bold">2. Ketahanan Siber (Cyber Resilience)</td>
            <td>Mitigasi serangan siber, pembaruan keamanan berkala, dan standar proteksi infrastruktur jaringan serta endpoint.</td>
            <td>Checklist antivirus real-time, patch keamanan OS, enkripsi password Argon2ID, biometrik FIDO2, dan tokenisasi stiker QR SHA-256.</td>
          </tr>
          <tr>
            <td class="fw-bold">3. Pengamanan Data & Kerahasiaan</td>
            <td>Penerapan prinsip Least Privilege, enkripsi data sensitif, dan perlindungan kerahasiaan data nasabah.</td>
            <td>Matriks RBAC 8 profil pengguna, enkripsi PDO prepared statements, dan proteksi URL QR acak tanpa mengekspos ID database.</td>
          </tr>
          <tr>
            <td class="fw-bold">4. Risiko Vendor / Pihak Ketiga (PPJTI)</td>
            <td>Mitigasi risiko operasional dari pihak penyedia jasa TI dan vendor sarana pendukung (AC, Genset, Hardware).</td>
            <td>Buku tamu digital ruang server dengan pencatatan instansi vendor, keperluan, foto tanda pengenal, dan pendampingan staf IT.</td>
          </tr>
          <tr>
            <td class="fw-bold">5. Pemulihan Bencana (DRP / BCP)</td>
            <td>Kesiapan cadangan sistem, pengujian DRP berkala, dan jaminan kelangsungan layanan operasional nasabah.</td>
            <td>Inspeksi kelaikan baterai UPS meja, kelistrikan grounding, backup data kerja nasabah harian, dan monitor S.M.A.R.T storage.</td>
          </tr>
          <tr>
            <td class="fw-bold">6. Deteksi & Penanganan Insiden</td>
            <td>Prosedur tanggap darurat saat terjadi kegagalan sistemik komputer teller atau anomali jaringan perbankan.</td>
            <td>Indikator meja berkedip merah (alert) saat ada temuan kerusakan aktif, penugasan tiket servis cepat, dan log audit trail permanen.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ========================================================================= -->
    <!-- BAB 8 -->
    <!-- ========================================================================= -->
    <div class="section-banner page-break">
      BAB 8. ALUR PROSEDUR OPERASIONAL TERPADU (CROSS-DEPARTMENT SOP)
    </div>

    <div class="sub-heading">
      <i class="bi bi-diagram-2-fill text-primary"></i> 8.1 Ringkasan 5 Prosedur Operasional Lintas Divisi
    </div>
    <div class="table-responsive">
      <table class="table table-bordered table-striped doc-table">
        <thead>
          <tr>
            <th style="width: 5%;">No</th>
            <th style="width: 20%;">Prosedur Kerja</th>
            <th style="width: 15%;">Divisi Terlibat</th>
            <th style="width: 35%;">Langkah Kerja Step-by-Step</th>
            <th style="width: 25%;">Output & Dokumen Bukti</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="text-center fw-bold">1</td>
            <td class="fw-bold">Pengadaan Barang Baru</td>
            <td>SDM + IT + Pincab + Direksi</td>
            <td>1. Staf/Teknisi buat Memo Pengajuan.<br>2. Disetujui Pincab & Direksi.<br>3. SDM belanja & catat di KIR.<br>4. IT pasang stiker QR & setup OS.</td>
            <td>&bull; Memo Pengajuan PDF<br>&bull; KIR Terupdate<br>&bull; Stiker QR Aset</td>
          </tr>
          <tr>
            <td class="text-center fw-bold">2</td>
            <td class="fw-bold">Rotasi Meja & Staf Cabang</td>
            <td>SDM + IT + Staf Cabang</td>
            <td>1. SK Mutasi staf diterbitkan SDM.<br>2. Admin buka Denah Meja 2D.<br>3. Drag meja ke ruangan baru.<br>4. Cetak BAST Mutasi Aset.</td>
            <td>&bull; Posisi Denah Terupdate<br>&bull; BAST Serah Terima PDF<br>&bull; Auto-Save Database</td>
          </tr>
          <tr>
            <td class="text-center fw-bold">3</td>
            <td class="fw-bold">Pemeliharaan Rutin Bulanan</td>
            <td>Teknisi IT + Karyawan</td>
            <td>1. Teknisi scan QR casing PC.<br>2. Isi 9 butir checklist & foto.<br>3. Jika ada part rusak, buat tiket.<br>4. Indikator meja berubah hijau.</td>
            <td>&bull; Kartu Kontrol Digital<br>&bull; Lampu Meja Hijau<br>&bull; Log Servis Komputer</td>
          </tr>
          <tr>
            <td class="text-center fw-bold">4</td>
            <td class="fw-bold">Penghapusan Aset Rusak (Afkir)</td>
            <td>IT + SDM + Pincab + Direksi</td>
            <td>1. IT beri rekomendasi afkir.<br>2. SDM input ke modul Disposal.<br>3. Penerbitan Berita Acara Afkir.<br>4. Hapus aktiva tetap di neraca.</td>
            <td>&bull; Rekomendasi Teknis IT<br>&bull; Berita Acara Afkir PDF<br>&bull; Penyesuaian Neraca</td>
          </tr>
          <tr>
            <td class="text-center fw-bold">5</td>
            <td class="fw-bold">Pemeriksaan Kepatuhan SKAI / Auditor Pengawas</td>
            <td>Auditor SKAI + Tim Pemeriksa</td>
            <td>1. Auditor buka menu Audit Trail.<br>2. Filter riwayat maintenance cabang.<br>3. Cek buku tamu ruang server.<br>4. Ekspor laporan resmi CSV/PDF.</td>
            <td>&bull; Rekap Audit Kepatuhan<br>&bull; Export File CSV/PDF<br>&bull; Bukti Kesiapan Audit</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ========================================================================= -->
    <!-- BAB 9 -->
    <!-- ========================================================================= -->
    <div class="section-banner page-break">
      BAB 9. LEMBAR PENGESAHAN RESMI DOKUMEN TATA KELOLA SISTEM
    </div>
    <p class="doc-text mb-3">
      Dokumen Panduan, Arsitektur, dan Laporan Operasional Sistem Informasi Manajemen Aset, Pemeliharaan IT & Sarana Prasarana (Sarpras) Terpadu PT. BPR Mitratama Arthabuana ini dinyatakan sah dan berlaku secara resmi di seluruh kantor pusat operasional serta jaringan kantor cabang:
    </p>

    <table class="sign-table">
      <tr>
        <td>
          <strong>Dipersiapkan Oleh:</strong><br>
          <span class="text-muted">Administrator IT / Teknisi Pelaksana</span>
          <div class="sign-space"></div>
          <strong>( ..................................................... )</strong><br>
          <span class="small text-muted">Tanggal: 22 Oktober 2026</span>
        </td>
        <td>
          <strong>Dipersiapkan Oleh:</strong><br>
          <span class="text-muted">Staf SDM & Umum (Sarana Prasarana)</span>
          <div class="sign-space"></div>
          <strong>( ..................................................... )</strong><br>
          <span class="small text-muted">Tanggal: 22 Oktober 2026</span>
        </td>
      </tr>
      <tr>
        <td>
          <strong>Diperiksa & Diverifikasi Oleh:</strong><br>
          <span class="text-muted">Kasubdiv / Supervisor IT & Operasional</span>
          <div class="sign-space"></div>
          <strong>( ..................................................... )</strong><br>
          <span class="small text-muted">Tanggal: 22 Oktober 2026</span>
        </td>
        <td>
          <strong>Diperiksa & Diverifikasi Oleh:</strong><br>
          <span class="text-muted">Kasubdiv / Supervisor SDM & Umum</span>
          <div class="sign-space"></div>
          <strong>( ..................................................... )</strong><br>
          <span class="small text-muted">Tanggal: 22 Oktober 2026</span>
        </td>
      </tr>
      <tr>
        <td>
          <strong>Diawasi & Ditinjau Kepatuhannya Oleh:</strong><br>
          <span class="text-muted">Satuan Kerja Audit Intern (Auditor SKAI)</span>
          <div class="sign-space"></div>
          <strong>( ..................................................... )</strong><br>
          <span class="small text-muted">Tanggal: 22 Oktober 2026</span>
        </td>
        <td>
          <strong>Disetujui & Disahkan Oleh:</strong><br>
          <span class="text-muted">Direktur Operasional & Kepatuhan</span>
          <div class="sign-space"></div>
          <strong>( ..................................................... )</strong><br>
          <span class="small text-muted">Tanggal: 22 Oktober 2026</span>
        </td>
      </tr>
    </table>

  </div>

  <script src="<?= module_url('assets/vendor/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
