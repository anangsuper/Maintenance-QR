<?php
require __DIR__ . '/bootstrap.php';
require_login();

$pageTitle = 'Dokumen Perancangan Sistem (DFD & Use Case) · QR Maintenance';

$head = '
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script>
  mermaid.initialize({
    startOnLoad: true,
    theme: "neutral",
    fontFamily: "Plus Jakarta Sans, sans-serif",
    themeVariables: {
      fontSize: "13px",
      primaryColor: "#e0e7ff",
      primaryTextColor: "#0f172a",
      primaryBorderColor: "#3b82f6",
      lineColor: "#475569",
      secondaryColor: "#f8fafc",
      tertiaryColor: "#ffffff"
    }
  });
</script>
<style>
  @page {
    size: A4;
    margin: 15mm 12mm 15mm 12mm;
  }

  body {
    background: #f1f5f9;
    color: #1e293b;
    font-size: 13.5px;
  }

  .doc-card {
    max-width: 960px;
    margin: 20px auto 40px auto;
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.06);
    padding: 45px 55px;
  }

  /* Toolbar */
  .doc-toolbar {
    position: sticky;
    top: 75px;
    z-index: 1020;
    background: rgba(15, 23, 42, 0.92);
    backdrop-filter: blur(10px);
    border-radius: 50px;
    padding: 10px 20px;
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.25);
    max-width: 960px;
    margin: 0 auto 20px auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .doc-header {
    border-bottom: 3px solid #1e3a8a;
    padding-bottom: 24px;
    margin-bottom: 30px;
  }

  .doc-badge {
    display: inline-block;
    background: #dbeafe;
    color: #1e40af;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
    padding: 4px 14px;
    border-radius: 20px;
    margin-bottom: 10px;
  }

  .section-title {
    font-size: 17px;
    font-weight: 800;
    color: #0f172a;
    margin: 32px 0 16px 0;
    padding-bottom: 8px;
    border-bottom: 1.5px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .section-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #1e3a8a;
    color: #ffffff;
    width: 26px;
    height: 26px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
  }

  .diagram-container {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 22px;
    margin: 18px 0 24px 0;
    text-align: center;
    overflow-x: auto;
  }

  .diagram-caption {
    font-size: 11.5px;
    font-weight: 600;
    color: #64748b;
    margin-top: 12px;
    text-align: center;
    font-style: italic;
  }

  .doc-table {
    width: 100%;
    border-collapse: collapse;
    margin: 16px 0 24px 0;
    font-size: 12.5px;
  }

  .doc-table thead th {
    background: #0f172a;
    color: #ffffff;
    text-align: left;
    padding: 10px 14px;
    font-weight: 700;
    font-size: 11.5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .doc-table tbody td {
    padding: 9px 14px;
    border-bottom: 1px solid #e2e8f0;
    color: #334155;
    vertical-align: top;
  }

  .doc-table tbody tr:nth-child(even) {
    background: #f8fafc;
  }

  .callout-box {
    background: #eff6ff;
    border-left: 4px solid #3b82f6;
    padding: 14px 18px;
    border-radius: 0 10px 10px 0;
    margin: 18px 0;
    font-size: 13px;
    color: #1e40af;
  }

  .code-tag {
    font-family: monospace;
    background: #f1f5f9;
    color: #0f172a;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11.5px;
    border: 1px solid #cbd5e1;
  }

  .page-divider {
    page-break-before: always;
    break-before: page;
    margin-top: 25px;
  }

  @media print {
    .main-navbar,
    .doc-toolbar,
    .no-print {
      display: none !important;
    }

    body {
      background: #ffffff !important;
      padding: 0 !important;
    }

    .doc-card {
      box-shadow: none !important;
      border: none !important;
      padding: 0 !important;
      margin: 0 !important;
      max-width: 100% !important;
    }

    .diagram-container {
      border: 1px solid #cbd5e1;
      background: #ffffff !important;
      padding: 12px;
      page-break-inside: avoid;
    }

    .page-divider {
      page-break-before: always;
      break-before: page;
    }

    h2, h3 {
      page-break-after: avoid;
    }

    table {
      page-break-inside: auto;
    }

    tr {
      page-break-inside: avoid;
      page-break-after: auto;
    }
  }
</style>';

$body = '
<!-- Floating Toolbar -->
<div class="doc-toolbar no-print text-white">
  <div class="d-flex align-items-center gap-2">
    <span class="badge bg-primary px-3 py-2 rounded-pill"><i class="bi bi-file-earmark-pdf-fill me-1"></i> Format Siap PDF</span>
    <span class="small opacity-75 d-none d-md-inline">Gunakan opsi <strong>"Save as PDF"</strong> saat jendela cetak terbuka</span>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="'.e(module_url('dashboard.php')).'" class="btn btn-sm btn-outline-light rounded-pill px-3">
      <i class="bi bi-arrow-left"></i> Dashboard
    </a>
    <button type="button" class="btn btn-sm btn-primary rounded-pill px-4 fw-bold shadow" onclick="window.print()">
      <i class="bi bi-printer-fill me-1"></i> CETAK / SIMPAN KE PDF
    </button>
  </div>
</div>

<div class="doc-card">
  <!-- Header Dokumen -->
  <div class="doc-header">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
      <div>
        <span class="doc-badge">Dokumen Perancangan Sistem</span>
        <h1 class="h3 fw-bold text-dark mb-1">Sistem Pemeliharaan Komputer Berbasis QR Code</h1>
        <p class="text-muted mb-0 small">Analisis Use Case, Data Flow Diagram (DFD Konteks & Level 1), Flowchart, dan Kamus Data Store</p>
      </div>
      <div class="text-md-end small text-muted">
        <div>Versi: <strong class="text-dark">2.1 (Revisi Lapangan)</strong></div>
        <div>Tanggal: <strong class="text-dark">'.date('d F Y').'</strong></div>
        <div>Penyusun: <strong class="text-dark">Tim IT Support & Maintenance</strong></div>
      </div>
    </div>
  </div>

  <!-- Ringkasan Eksekutif -->
  <div class="callout-box">
    <strong>Ringkasan Sistem:</strong> Sistem QR Maintenance dirancang untuk mempercepat dan menertibkan pencatatan pemeliharaan berkala perangkat komputer dan printer di seluruh unit kerja / meja. Teknisi lapangan cukup memindai stiker QR pada meja kerja dan memasukkan nama teknisi tanpa harus login, kemudian mengisi 10 butir checklist. Administrator memiliki hak akses penuh untuk mengelola master data, mengoreksi tanggal pelaksanaan (backdate), dan memantau persentase capaian pemeliharaan bulanan melalui Dashboard.
  </div>

  <!-- BAB 1: USE CASE -->
  <div class="section-title">
    <span class="section-num">1</span>
    <span>Use Case Diagram & Deskripsi Aktor</span>
  </div>
  <p class="text-muted small">Diagram Use Case mendefinisikan interaksi antara dua aktor utama: <strong>Teknisi Lapangan</strong> dan <strong>Administrator</strong> terhadap fungsi-fungsi sistem.</p>

  <div class="diagram-container">
    <div class="mermaid">
flowchart LR
    Teknisi((Teknisi Lapangan))
    Admin((Administrator))

    subgraph Sistem_QR_Maintenance["Sistem QR Maintenance Komputer"]
        UC1["Scan QR Code Meja Kerja"]
        UC2["Input Nama Teknisi (Tanpa Password)"]
        UC3["Pengisian Form Checklist 10 Butir"]
        UC4["Melihat Kartu Kontrol Meja"]
        
        UC5["Login Akun Administrator"]
        UC6["Kelola Data Komputer & Cetak QR"]
        UC7["Kelola Master Cabang, Divisi & User"]
        UC8["Koreksi Tanggal Maintenance (Backdate)"]
        UC9["Monitoring Dashboard & Rekap Bulanan"]
        UC10["Cetak Laporan & Kartu Kontrol (PDF/Print)"]
    end

    Teknisi --> UC1
    UC1 -.->|include| UC2
    UC2 -.->|include| UC3
    Teknisi --> UC4

    Admin --> UC5
    Admin --> UC6
    Admin --> UC7
    Admin --> UC8
    Admin --> UC9
    Admin --> UC10
    Admin -.->|Bisa Mengakses| UC1
    </div>
    <div class="diagram-caption">Gambar 1.1 - Use Case Diagram Sistem QR Maintenance</div>
  </div>

  <table class="doc-table">
    <thead>
      <tr>
        <th style="width: 12%;">Kode</th>
        <th style="width: 25%;">Use Case</th>
        <th style="width: 20%;">Aktor</th>
        <th>Deskripsi & Aturan Bisnis</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><span class="code-tag">UC-01</span></td>
        <td><strong>Scan QR Meja Kerja</strong></td>
        <td>Teknisi / Admin</td>
        <td>Memindai stiker QR yang terpasang di meja/casing untuk membuka form checklist tanpa perlu memasukkan password.</td>
      </tr>
      <tr>
        <td><span class="code-tag">UC-02</span></td>
        <td><strong>Input Nama Teknisi</strong></td>
        <td>Teknisi</td>
        <td>Memasukkan nama teknisi pelaksana agar tercatat dalam log riwayat audit secara transparan.</td>
      </tr>
      <tr>
        <td><span class="code-tag">UC-03</span></td>
        <td><strong>Isi Checklist 10 Butir</strong></td>
        <td>Teknisi</td>
        <td>Memeriksa item fisik, OS, antivirus, jaringan. Khusus poin 7, 8, 9 (printer) dapat dikosongkan bila meja tidak memiliki printer (tercatat sebagai strip \'-\').</td>
      </tr>
      <tr>
        <td><span class="code-tag">UC-04</span></td>
        <td><strong>Lihat Kartu Kontrol</strong></td>
        <td>Teknisi / Admin</td>
        <td>Menampilkan lembar riwayat perawatan 12 bulan untuk komputer yang baru saja selesai dirawat.</td>
      </tr>
      <tr>
        <td><span class="code-tag">UC-05</span></td>
        <td><strong>Koreksi Tanggal (Backdate)</strong></td>
        <td>Administrator</td>
        <td>Mengubah tanggal pelaksanaan di log maintenance bila pemeliharaan fisik dilakukan di tanggal sebelumnya namun baru sempat di-scan di tanggal berikutnya.</td>
      </tr>
    </tbody>
  </table>

  <div class="page-divider"></div>

  <!-- BAB 2: DFD (DATA FLOW DIAGRAM) -->
  <div class="section-title">
    <span class="section-num">2</span>
    <span>Data Flow Diagram (DFD)</span>
  </div>

  <h6 class="fw-bold text-primary mt-3">2.1. DFD Level 0 (Diagram Konteks)</h6>
  <p class="text-muted small">Menggambarkan sistem secara global sebagai satu lingkaran proses utama, entitas eksternal yang terlibat, serta aliran data masuk dan keluar.</p>

  <div class="diagram-container">
    <div class="mermaid">
flowchart TD
    Teknisi["Entitas Luar: Teknisi Lapangan"]
    Admin["Entitas Luar: Administrator"]
    System["(0.0) Sistem Informasi QR Maintenance Komputer"]

    Teknisi -- "1. Scan QR Token Meja\n2. Nama Teknisi\n3. Checklist Poin 1-10 & Temuan" --> System
    System -- "1. Detail Spesifikasi Komputer\n2. Notifikasi Sukses Simpan\n3. Tampilan Kartu Kontrol" --> Teknisi

    Admin -- "1. Kredensial Login (User & Pass)\n2. Penambahan/Edit Aset Komputer\n3. Koreksi Tanggal Riwayat (Backdate)" --> System
    System -- "1. Visualisasi Progress Dashboard\n2. Master Stiker QR Siap Cetak\n3. Rekap Laporan Bulanan & Audit Trail" --> Admin
    </div>
    <div class="diagram-caption">Gambar 2.1 - DFD Level 0 (Diagram Konteks)</div>
  </div>

  <h6 class="fw-bold text-primary mt-4">2.2. DFD Level 1 (Dekomposisi Proses)</h6>
  <p class="text-muted small">Menguraikan sistem ke dalam 5 sub-proses operasional dan interaksinya dengan basis data (Google Sheets / RDBMS).</p>

  <div class="diagram-container">
    <div class="mermaid">
flowchart TB
    Teknisi["Teknisi Lapangan"]
    Admin["Administrator"]

    DS_Assets[("D1: Data Komputer (Assets)")]
    DS_Logs[("D2: Maintenance_Logs")]
    DS_Checklists[("D3: Maintenance_Checklists")]
    DS_Master[("D4: Master Cabang & Divisi")]
    DS_Users[("D5: Akun Pengguna / Admin")]

    P1["1.0 Autentikasi Admin"]
    P2["2.0 Manajemen Komputer & QR"]
    P3["3.0 Pelaksanaan Scan & Checklist"]
    P4["4.0 Koreksi Tanggal Maintenance"]
    P5["5.0 Rekapitulasi & Pelaporan"]

    Admin -->|Login Kredensial| P1
    P1 <-->|Cek Hash Password| DS_Users

    Admin -->|Input Spek Komputer| P2
    Admin -->|Kelola Cabang / Divisi| P2
    P2 <-->|Simpan Aset & Token| DS_Assets
    P2 <-->|Relasi Wilayah| DS_Master
    P2 -->|Output Gambar QR Code| Admin

    Teknisi -->|Scan Token URL| P3
    P3 <-->|Ambil Info Meja & Spesifikasi| DS_Assets
    Teknisi -->|Kirim Nama & Checklist 1-10| P3
    P3 -->|Simpan Header Log & Tanggal| DS_Logs
    P3 -->|Simpan Rincian Nilai 1-10| DS_Checklists
    P3 -->|Tampilkan Kartu Kontrol Meja| Teknisi

    Admin -->|Koreksi Tanggal Pelaksanaan| P4
    P4 <-->|Update Tanggal/Teknisi| DS_Logs
    P4 <-->|Update Checklist| DS_Checklists

    DS_Assets --> P5
    DS_Logs --> P5
    DS_Checklists --> P5
    P5 -->|Dashboard & Ekspor Laporan| Admin
    </div>
    <div class="diagram-caption">Gambar 2.2 - DFD Level 1 Sistem QR Maintenance</div>
  </div>

  <div class="page-divider"></div>

  <!-- BAB 3: FLOWCHART ALUR OPERASIONAL -->
  <div class="section-title">
    <span class="section-num">3</span>
    <span>Flowchart Alur Operasional (Activity Diagram)</span>
  </div>
  <p class="text-muted small">Alur interaksi berurutan antara teknisi, kamera smartphone, server aplikasi, dan database:</p>

  <div class="diagram-container">
    <div class="mermaid">
sequenceDiagram
    autonumber
    actor T as Teknisi Lapangan
    participant HP as Smartphone / Kamera
    participant Web as Sistem Web QR
    participant DB as Google Sheets / Database
    actor A as Administrator

    Note over T,DB: Skenario 1: Pemeliharaan Rutin Lapangan
    T->>HP: Scan Stiker QR pada Meja Komputer
    HP->>Web: Request URL (scan.php?token=xxx)
    Web->>DB: Validasi Token & Ambil Data Komputer
    DB-->>Web: Data Meja, Cabang, Divisi, IP Ditemukan
    Web-->>HP: Tampilkan Form Perawatan
    T->>HP: Masukkan Nama Teknisi (Tanpa Password)
    T->>HP: Isi Ceklis 1-6 (Fisik, OS, Antivirus, Jaringan)
    alt Meja Memiliki Printer
        T->>HP: Ceklis Poin 7, 8, 9 (Mekanik, Cartridge, Test Page)
    else Tidak Ada Printer di Meja
        T->>HP: Biarkan Poin 7, 8, 9 Tidak Tercentang (Tercatat '-')
    end
    T->>HP: Tulis Catatan / Temuan Khusus (Bila Ada)
    T->>HP: Klik Tombol "Simpan Maintenance"
    HP->>Web: POST Data Form
    Web->>DB: Simpan Header Log & Rincian Checklist
    DB-->>Web: Berhasil Disimpan
    Web-->>HP: Tampilkan Notifikasi & Update Kartu Kontrol

    Note over A,DB: Skenario 2: Koreksi Tanggal Retroaktif (Admin)
    opt Tanggal Scan Berbeda dengan Tanggal Fisik
        A->>Web: Buka Menu Riwayat (Login Admin)
        A->>Web: Pilih Transaksi & Ubah Tanggal ke Tanggal Riil
        Web->>DB: Update Kolom maintenance_date
        DB-->>Web: Record Terupdate
        Web-->>A: Laporan Bulanan Menyesuaikan Tanggal Baru
    end
    </div>
    <div class="diagram-caption">Gambar 3.1 - Sequence Diagram Pemeliharaan & Koreksi Tanggal</div>
  </div>

  <!-- BAB 4: KAMUS DATA STORE -->
  <div class="section-title">
    <span class="section-num">4</span>
    <span>Kamus Data Store (Tabel Database / Sheet)</span>
  </div>

  <table class="doc-table">
    <thead>
      <tr>
        <th>Tabel / Sheet</th>
        <th>Atribut Utama</th>
        <th>Tipe Data</th>
        <th>Keterangan</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td rowspan="4"><strong>Assets</strong><br><small class="text-muted">(Data Komputer)</small></td>
        <td><span class="code-tag">id</span> <em>(PK)</em></td>
        <td>String / Integer</td>
        <td>ID Unik Aset (contoh: <code>PC-KPO-001</code>)</td>
      </tr>
      <tr>
        <td><span class="code-tag">meja / divisi / cabang</span></td>
        <td>String</td>
        <td>Identitas lokasi & unit penanggung jawab</td>
      </tr>
      <tr>
        <td><span class="code-tag">ip_address</span></td>
        <td>String</td>
        <td>Alamat IP lokal komputer pengguna</td>
      </tr>
      <tr>
        <td><span class="code-tag">token</span></td>
        <td>String (Hash)</td>
        <td>Token acak keamanan URL QR Code</td>
      </tr>
      <tr>
        <td rowspan="4"><strong>Maintenance_Logs</strong><br><small class="text-muted">(Riwayat Header)</small></td>
        <td><span class="code-tag">id</span> <em>(PK)</em></td>
        <td>String</td>
        <td>ID Transaksi Log (contoh: <code>SCAN-20260908-01</code>)</td>
      </tr>
      <tr>
        <td><span class="code-tag">asset_id</span> <em>(FK)</em></td>
        <td>String / Integer</td>
        <td>Merujuk ke tabel Assets</td>
      </tr>
      <tr>
        <td><span class="code-tag">maintenance_date</span></td>
        <td>Date (YYYY-MM-DD)</td>
        <td>Tanggal pelaksanaan (dapat dikoreksi Admin)</td>
      </tr>
      <tr>
        <td><span class="code-tag">technician_name</span></td>
        <td>String</td>
        <td>Nama personil yang melakukan pemeliharaan</td>
      </tr>
      <tr>
        <td rowspan="3"><strong>Maintenance_Checklists</strong><br><small class="text-muted">(Detail 10 Poin)</small></td>
        <td><span class="code-tag">log_id</span> <em>(FK)</em></td>
        <td>String</td>
        <td>Merujuk ke Maintenance_Logs</td>
      </tr>
      <tr>
        <td><span class="code-tag">item_1 s/d item_6</span></td>
        <td>Boolean (1 / 0)</td>
        <td>Poin Komputer, OS, Antivirus, Jaringan</td>
      </tr>
      <tr>
        <td><span class="code-tag">item_7 s/d item_9</span></td>
        <td>Boolean / Null</td>
        <td>Poin Printer (0 = Ditandai strip \'-\' di Kartu Kontrol)</td>
      </tr>
    </tbody>
  </table>

  <div class="text-center text-muted small mt-5 pt-3 border-top">
    Dokumen ini digenerate secara otomatis untuk standarisasi operasional sistem QR Maintenance · Hak Cipta © '.date('Y').'
  </div>
</div>';

render_page($pageTitle, $body, $head, '', true);
