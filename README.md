# 🖥️ QR Maintenance — Sistem Manajemen Pemeliharaan IT Berbasis QR Code, Biometrik & Kartu Kontrol 12 Bulan

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![PWA Ready](https://img.shields.io/badge/PWA-Offline%20First-5A0FC8?style=flat&logo=pwa&logoColor=white)](https://web.dev/progressive-web-apps/)
[![WebAuthn / Passkeys](https://img.shields.io/badge/Auth-WebAuthn%20%2F%20Passkeys-green?style=flat&logo=fido&logoColor=white)](https://fidoalliance.org/)
[![Face Recognition](https://img.shields.io/badge/AI-Face--API.js%20Liveness-FF6F00?style=flat&logo=tensorflow&logoColor=white)](https://github.com/justadudewhohacks/face-api.js)
[![WebSocket](https://img.shields.io/badge/WebSocket-Native%20RFC%206455-007ACC?style=flat&logo=websocket&logoColor=white)](https://developer.mozilla.org/en-US/docs/Web/API/WebSockets_API)
[![Storage](https://img.shields.io/badge/Storage-MySQL%20%7C%20Google%20Sheets%20v4-blue?style=flat&logo=mysql&logoColor=white)](https://cloud.google.com/docs)
[![Compliance](https://img.shields.io/badge/Compliance-POJK%20%2F%20ISO%2027001-red?style=flat&logo=shield&logoColor=white)](https://www.ojk.go.id/)

Aplikasi web modern berbasis **PHP Native Modular** untuk tata kelola aset IT, pencatatan pemeliharaan berkala (*preventive maintenance*), monitoring kepatuhan audit secara transparan, serta otomatisasi **Kartu Kontrol IT 12 Bulan** dan **Kartu Inventaris Standar ATM (CR80)** dengan pemindaian **QR Code**, **PWA Offline-First**, **AI Biometrik Wajah**, dan sinkronisasi **Real-Time WebSocket**.

Aplikasi ini mendukung **Dual-Storage Engine**: dapat berjalan secara *serverless* menggunakan **Google Cloud Sheets API v4** (sangat cocok untuk hosting di **Vercel** tanpa biaya server database) maupun menggunakan **Database MySQL** di hosting/VPS/server lokal (XAMPP / Laragon).

---

## 📑 Daftar Isi
1. [Alur Kerja Sistem (System Architecture & Workflow)](#-alur-kerja-sistem-system-architecture--workflow)
2. [Fitur Unggulan Sistem (Feature Matrix)](#-fitur-unggulan-sistem-feature-matrix)
3. [Alur Operasional Step-by-Step](#-alur-operasional-step-by-step)
   - [A. Manajemen Master Data, Cabang, & Akun Teknisi](#a-manajemen-master-data-cabang--akun-teknisi)
   - [B. Registrasi Aset IT, Import Excel, & Cetak Stiker QR](#b-registrasi-aset-it-import-excel--cetak-stiker-qr)
   - [C. In-App QR Scanner & PWA Mobile App](#c-in-app-qr-scanner--pwa-mobile-app)
   - [D. Pemeliharaan Rutin, Susulan, & Verifikasi Biometrik](#d-pemeliharaan-rutin-susulan--verifikasi-biometrik)
   - [E. Penanganan Temuan Kendala (Findings & Follow-Up)](#e-penanganan-temuan-kendala-findings--follow-up)
   - [F. Kartu Kontrol 12 Bulan & Cetak Fisik](#f-kartu-kontrol-12-bulan--cetak-fisik)
   - [G. Cetak Kartu Inventaris Standar ATM (CR80)](#g-cetak-kartu-inventaris-standar-atm-cr80)
   - [H. Dashboard Monitoring Real-Time WebSocket](#h-dashboard-monitoring-real-time-websocket)
   - [I. Audit Trail & Kepatuhan Keamanan Perbankan](#i-audit-trail--kepatuhan-keamanan-perbankan)
4. [9 Standar Checklist Pemeliharaan IT](#-9-standar-checklist-pemeliharaan-it)
5. [Hak Akses & Multi-Role Pengguna (RBAC)](#-hak-akses--multi-role-pengguna-rbac)
6. [Struktur Direktori & Peta Modul](#-struktur-direktori--peta-modul)
7. [Panduan Instalasi & Konfigurasi](#-panduan-instalasi--konfigurasi)
   - [Opsi A: Instalasi Lokal / VPS (MySQL Database)](#opsi-a-instalasi-lokal--vps-mysql-database)
   - [Opsi B: Deployment Serverless ke Vercel (Google Sheets API v4)](#opsi-b-deployment-serverless-ke-vercel-google-sheets-api-v4)
   - [Menjalankan Server Real-Time WebSocket](#menjalankan-server-real-time-websocket)
8. [Akun Default & Keamanan Awal](#-akun-default--keamanan-awal)

---

## 🚀 Alur Kerja Sistem (System Architecture & Workflow)

```mermaid
flowchart TD
    subgraph PERSIAPAN["1. Master Data & Penempelan Label"]
        A["👑 Admin / HRD & Umum"] -->|"Input / Import Excel"| B["💻 Master Aset Komputer & Printer"]
        A -->|"Daftarkan Akun & Panggilan Paraf"| C["👥 Akun Teknisi & Perekaman Wajah"]
        B -->|"Cetak Stiker QR & Kartu CR80"| D["🏷️ Label QR Tertempel di Unit"]
    end

    subgraph PELAKSANAAN["2. Pemeriksaan Lapangan & Validasi"]
        E["🛠️ Teknisi IT Lapangan"] -->|"Scan via Kamera HP / In-App Scanner"| D
        D -->|"Buka URL Token Aman"| F["📱 Halaman Scan (PWA Offline / Online)"]
        F -->|"Isi 9 Butir Checklist IT"| G["📋 Form Checklist Pemeliharaan"]
        G -->|"Verifikasi Kehadiran Fisik"| H{"AI Face Recognition / Passkey"}
        H -->|"Liveness Lolos"| I{"Ada Kerusakan / Temuan?"}
        H -->|"Gagal Liveness"| G
    end

    subgraph HASIL["3. Monitoring, Log, & Cetak"]
        I -->|"Tidak (Normal)"| J["✅ Simpan Pemeliharaan Selesai"]
        I -->|"Ya (Ada Masalah)"| K["⚠️ Catat Temuan Kendala (Pending)"]
        K -->|"Perbaikan Fisik Dilakukan"| L["🔧 Tindak Lanjut & Selesaikan"]
        L --> J

        J -->|"Broadcast WebSocket"| M["📡 Dashboard Live Monitoring (Realtime)"]
        J -->|"Sinkronisasi Log"| N["📊 Matriks Kartu Kontrol 12 Bulan"]
        J -->|"Audit Logging Terpusat"| O["🛡️ Audit Trail (POJK / ISO 27001)"]
        N -->|"Cetak A4"| P["🖨️ Kartu Kontrol Fisik (Paraf Otomatis)"]
        B -->|"Cetak PVC / A4"| Q["💳 Kartu Inventaris ATM CR80 (Word/PDF)"]
    end
```

---

## ✨ Fitur Unggulan Sistem (Feature Matrix)

| Kategori | Fitur | Penjelasan & Keunggulan |
|---|---|---|
| **📱 PWA & Offline** | **Progressive Web App (PWA)** | Dapat diinstal ke *Home Screen* smartphone (Android/iOS) dan Desktop layaknya aplikasi native tanpa Google Play Store / App Store. |
| | **Offline Queue & Background Sync** | Teknisi tetap dapat mengisi checklist maintenance di area tanpa sinyal (misal basement server/ruang arsip). Data tersimpan di IndexedDB dan otomatis tersinkronisasi saat online kembali. |
| **🔐 Keamanan & Biometrik** | **WebAuthn / Passkeys** | Login ultra-cepat dan aman tanpa password menggunakan sensor biometrik bawaan perangkat (Touch ID, Face ID, Windows Hello, YubiKey). |
| | **AI Face Recognition & Liveness** | Perekaman wajah teknisi via smartphone dan verifikasi biometrik dengan deteksi *liveness* (anti-spoofing) saat submit checklist untuk menjamin teknisi hadir langsung di depan unit komputer. |
| | **Standar Keamanan POJK No. 75** | Dilengkapi sistem *Anti-Brute Force* (lockout otomatis 15 menit jika 5x berturut-turut gagal login), modul *Emergency Unlock*, serta perlindungan XSS, CSRF, dan pencegahan Open Redirect (CWE-601). |
| **⚡ Real-Time Engine** | **Native WebSocket Server (RFC 6455)** | Server WebSocket murni PHP tanpa dependensi Composer eksternal. Dashboard diperbarui secara langsung dalam milidetik setiap ada aktivitas maintenance di lapangan. |
| | **Auto-Sync Fallback** | Jika server WebSocket tidak berjalan, antarmuka dashboard otomatis beralih ke mode sinkronisasi HTTP cerdas tanpa mengganggu pengguna. |
| **📋 Kartu Kontrol & Inventaris** | **Kartu Kontrol 12 Bulan** | Matriks rekap Januari–Desember dengan centang otomatis untuk 9 butir checklist dan pencantuman otomatis **Nama Panggilan Paraf Teknisi**. Tersedia opsi cetak Grid 6, Grid 8, dan Single. |
| | **Kartu Inventaris ATM (CR80)** | Modul cetak kartu inventaris berdimensi presisi kartu ATM (**85.6mm x 54.0mm**). Mendukung layout cetak massal A4 (8, 10, atau 12 kartu/lembar), Live Preview 1-per-1, ekspor ke Word (`.doc`) dan PDF. |
| | **Pemeliharaan Susulan & Ulang** | Mendukung pengisian checklist untuk bulan-bulan sebelumnya yang tertunda/terlewat dengan validasi historis, serta opsi update pemeliharaan bulan berjalan. |
| **🔍 Scan & Pencarian** | **In-App QR Scanner** | Fitur scanner QR bawaan browser web (`scanner.php`) berkamera ganda (kamera depan/belakang) dengan pengatur lampu flash/torch tanpa butuh aplikasi scan terpisah. |
| | **Global Quick Search (Ctrl + K)** | Shortcut keyboard global `Ctrl + K` untuk pencarian instan nama aset, kode inventaris, serial number, pengguna, maupun IP address. |
| **📦 Data & Import** | **Import Massal Excel / CSV** | Import ribuan data komputer dari file Excel/CSV lengkap dengan deteksi pemisah otomatis, pembersihan UTF-8 BOM, dan registrasi kantor cabang/divisi otomatis. |
| | **Dual-Storage Engine** | Mendukung database relasional **MySQL** (ACID compliant) dan **Google Cloud Sheets API v4** (*serverless hosting* di Vercel). |
| **📊 Audit & Pelaporan** | **Audit Trail Terpusat** | Logging komprehensif atas semua operasi sistem (login, tambah/edit aset, checklist, finding, pendaftaran biometrik) mencatat IP Address, User Agent, dan detail mutasi. |
| | **Laporan Resmi Siap Cetak (PDF)** | Cetak laporan rekapitulasi kepatuhan pemeliharaan bulanan per cabang lengkap dengan lembar pengesahan teknisi, atasan, dan auditor SKAI. |

---

## 🔍 Alur Operasional Step-by-Step

### A. Manajemen Master Data, Cabang, & Akun Teknisi
1. **Master Cabang & Divisi**:
   - Admin / HRD & Umum membuka menu **MANAGEMENT -> Kantor Cabang** dan **Divisi / Unit Kerja**.
   - Input nama cabang (misal: *KPO, KC Bandung, KC Surabaya*) dan divisi kerja (*Pelayanan Nasabah, Kredit, IT/MIS, Akuntansi*).
2. **Pendaftaran Akun Pengguna**:
   - Masuk ke menu **Akun Pengguna**.
   - Masukkan Username, Nama Lengkap, **Nama Panggilan Paraf**, Role (Administrator, Teknisi IT, HRD/Umum, Auditor/SKAI), dan No. HP/WhatsApp.
   - *Nama Panggilan*: Sangat krusial karena otomatis tercetak pada kolom **PARAF** kartu kontrol 12 bulan (misal: *Ahmad, Budi, Rian*) agar pas dengan lebar kolom kartu.
3. **Perekaman Biometrik Wajah Teknisi (Face Enrollment)**:
   - Teknisi membuka menu **Wajah Teknisi (HP)** di smartphone masing-masing.
   - Posisikan wajah di dalam bingkai oval kamera HP, sistem AI Face-API.js mengekstrak 128 vektor deskriptor wajah dan menyimpannya secara terenkripsi.

---

### B. Registrasi Aset IT, Import Excel, & Cetak Stiker QR
1. **Registrasi Mandiri atau Import Massal**:
   - **Tambah Satuan**: Melalui menu **Asset Registry -> Tambah Komputer**. Isi Kode Inventaris, Serial Number, Nama Pengguna, Cabang, Divisi, IP Address, Merk/Model CPU, Monitor, dan Printer.
   - **Import Excel / CSV**: Melalui menu **Asset Registry -> Import Massal**. Unduh template resmi, unggah file Excel/CSV. Sistem otomatis membaca kolom, mendeteksi cabang, dan meng-generate QR Token unik untuk tiap unit.
2. **Cetak Stiker QR Code**:
   - Buka menu **SYSTEM -> QR Aset Label** atau menu **Cetak -> Stiker QR**.
   - Pilih filter cabang/divisi atau pilih unit tertentu.
   - Cetak pada kertas stiker vinyl/chromo. Layout 3 kolom siap tempel memuat:
     - Logo Bank Mitra & Judul "KARTU PEMELIHARAAN IT"
     - Nama Perangkat & Kode Inventaris
     - IP Address & Nama Karyawan Pengguna + Divisi
     - Lokasi Kantor Cabang
     - Kode QR unik berkeamanan token anti-tamper.

---

### C. In-App QR Scanner & PWA Mobile App
1. **Pemasangan PWA (Install App)**:
   - Buka website melalui browser Chrome / Edge / Safari di smartphone.
   - Klik tombol **Pasang App** pada topbar atau pilih *Add to Home Screen*.
   - Aplikasi siap digunakan kapan saja dari layar beranda HP.
2. **Pemindaian QR Lapangan**:
   - Buka menu **OPERATIONS -> QR Scanner** di aplikasi, arahkan kamera smartphone ke stiker unit.
   - Atau scan stiker menggunakan aplikasi kamera smartphone biasa; tautan URL langsung membuka halaman unit terkait secara instan.

---

### D. Pemeliharaan Rutin, Susulan, & Verifikasi Biometrik
1. **Pemeriksaan Fisik**:
   - Teknisi memeriksa kondisi fisik CPU, membersihkan debu, memeriksa kipas, serta mengetes printer.
2. **Pengisian 9 Checklist Standar**:
   - Teknisi mencentang 9 butir checklist pemeliharaan pada halaman unit.
   - **Mode Susulan**: Jika ada pemeliharaan bulan lalu yang terlewat, teknisi dapat memilih dropdown bulan susulan untuk melengkapi catatan historis.
3. **Validasi Wajah / Biometrik (Liveness Check)**:
   - Saat tombol simpan diklik, sistem membuka kamera verifikasi wajah teknisi.
   - Fitur deteksi *liveness* memastikan wajah asli (bukan foto/layar). Setelah terkonfirmasi cocok, checklist tersimpan secara sah.
4. **Dukungan Offline**:
   - Jika koneksi internet terputus saat berada di ruangan tertutup, data pemeliharaan otomatis masuk ke **Antrean Offline (PWA Offline Queue)** di IndexedDB perangkat.
   - Begitu smartphone mendeteksi sinyal internet, tombol sinkronisasi otomatis mengirimkan data ke database pusat.

---

### E. Penanganan Temuan Kendala (Findings & Follow-Up)
1. **Pencatatan Masalah (Finding)**:
   - Jika ditemukan masalah (misal: *Cartridge buntu, HDD bad sector, PSU bunyi kasar*), teknisi mengisi kolom **Temuan Masalah & Rekomendasi**.
   - Status pemeliharaan unit otomatis ditandai **"Ada Masalah / Perlu Tindak Lanjut"** dan muncul peringatan oranye/merah di halaman kartu dan dashboard.
2. **Tindak Lanjut & Penyelesaian (Follow-Up)**:
   - Setelah suku cadang diganti atau perbaikan tuntas, teknisi/admin membuka menu **Temuan Kendala** di dashboard atau halaman detail unit.
   - Klik tombol **"TINDAK LANJUTI / SELESAIKAN SEKARANG"**, masukkan rincian tindakan perbaikan dan tanggal penyelesaian.
   - Status temuan otomatis ditandai **Resolved** dan status unit kembali menjadi **Normal / Selesai**.

---

### F. Kartu Kontrol 12 Bulan & Cetak Fisik
1. **Tampilan Matriks Digital**:
   - Menyajikan ringkasan 12 baris (Januari hingga Desember) berisi tanda centang (✓) untuk checklist 1 s/d 9 serta kolom **PARAF** terisi nama panggilan teknisi.
2. **Pilihan Cetak Fisik ke Kertas A4**:
   - **Grid 6**: 6 kartu kontrol per lembar A4 (format hemat standar).
   - **Grid 8**: 8 kartu kontrol per lembar A4.
   - **Single Card**: 1 kartu kontrol ukuran besar per lembar A4 untuk arsip dokumen.

---

### G. Cetak Kartu Inventaris Standar ATM (CR80)
1. **Standar Ukuran Kartu ATM (CR80)**:
   - Berdimensi **85.6mm x 54.0mm** dengan sudut lengkung, sesuai format kartu PVC ID card perbankan.
2. **Pilihan Format Cetak Massal**:
   - **8 Kartu / Lembar A4**: Format 2 x 4 (Portrait) dengan jarak potong longgar.
   - **10 Kartu / Lembar A4**: Format 2 x 5 (Portrait) standar kartu nama.
   - **12 Kartu / Lembar A4**: Format 3 x 4 (Landscape) kapasitas maksimal per lembar A4.
3. **Ekspor Multi-Format**:
   - Cetak langsung melalui browser (*Print to PDF*).
   - Ekspor ke dokumen Microsoft Word (`.doc`) dengan tabel format kartu siap edit dan cetak offline.
   - Ekspor data aset ke format CSV / Excel.

---

### H. Dashboard Monitoring Real-Time WebSocket
1. **Live Broadcast WebSocket**:
   - Setiap kali teknisi menyimpan checklist atau menyelesaikan temuan kendala di lapangan, server WebSocket menyiarkan event secara instan.
2. **Tampilan Interaktif Tanpa Reload**:
   - Kartu statistik persentase kepatuhan bulanan langsung bertambah.
   - Progress bar pencapaian per cabang ter-update otomatis.
   - Feed log aktivitas terkini menampilkan entri terbaru dengan efek animasi visual.

---

### I. Audit Trail & Kepatuhan Keamanan Perbankan
1. **Pencatatan Log Kepatuhan**:
   - Setiap mutasi data dicatat ke dalam tabel `system_audit_logs` atau Google Sheet `Audit_Trail`:
     - Waktu pasti (Timestamp), User ID, Nama Pengguna, dan Role.
     - Alamat IP (`ip_address`) dan User Agent perangkat.
     - Kategori modul (*ASET, PEMELIHARAAN, TEMUAN, KEAMANAN, MASTER DATA*).
     - Rincian parameter perubahan dalam format JSON terstruktur.
2. **Filter & Ekspor CSV**:
   - Auditor internal/eksternal dapat memfilter log berdasarkan tanggal, modul, teknisi, dan keyword, serta mengekspornya langsung ke format spreadsheet CSV.

---

## 📋 9 Standar Checklist Pemeliharaan IT

Sistem menerapkan 9 butir checklist pemeliharaan berkala terstandarisasi untuk memastikan keandalan komputer kerja dan perangkat cetak:

```text
┌───┬───────────────────────────────┬─────────────────────────┬─────────────────────────────┐
│ # │ Item Pemeriksaan              │ Kategori                │ Catatan Standar Normal      │
├───┼───────────────────────────────┼─────────────────────────┼─────────────────────────────┤
│ 1 │ Scan Virus                    │ Keamanan & Antivirus    │ Bersih, tidak ada malware   │
│ 2 │ Update Anti Virus             │ Pembaruan Sistem        │ Database virus terupdate    │
│ 3 │ Deleting Temporary File       │ Optimasi Penyimpanan    │ File cache/temp dibersihkan │
│ 4 │ Cek Keyboard                  │ Hardware Input          │ Semua tombol responsif      │
│ 5 │ Cek Mouse                     │ Hardware Input          │ Klik & sensor optik normal  │
│ 6 │ Cek CPU & Monitor             │ Hardware Utama & Layar  │ Kipas hening & layar jernih │
│ 7 │ Cek Tinta                     │ Printer & Peripheral    │ Level tinta aman / mencukupi│
│ 8 │ Cek Cartridge                 │ Printer & Peripheral    │ Cartridge bersih & presisi  │
│ 9 │ Cek Nozzle                    │ Printer & Peripheral    │ Nozzle test tajam & tidak putus│
└───┴───────────────────────────────┴─────────────────────────┴─────────────────────────────┘
```

---

## 👥 Hak Akses & Multi-Role Pengguna (RBAC)

Sistem membagi wewenang ke dalam 4 kelompok peran pengguna:

```mermaid
graph LR
    subgraph RBAC["Role-Based Access Control"]
        ADM["👑 Administrator"]
        TEK["🛠️ Teknisi IT"]
        HRD["👔 HRD / SDM & Umum"]
        AUD["👁️ Auditor / SKAI"]
    end

    ADM -->|"Akses Penuh"| ALL["Seluruh Modul, Konfigurasi, & Unlock Akun"]
    TEK -->|"Operasional"| OPS["Scan QR, Checklist 9 Item, Biometrik, & Temuan"]
    HRD -->|"Kelola Aset & Unit"| MGT["Master Cabang, Divisi, User, & Kartu Inventaris"]
    AUD -->|"Pengawasan"| MON["Audit Trail, Riwayat Bulanan, & Cetak Laporan PDF"]
```

1. **👑 Administrator**:
   - Memiliki kendali penuh atas konfigurasi sistem, database, dan pemeliharaan server.
   - Mengelola master data, akun pengguna, reset token QR, dan membuka akun yang terkunci (*emergency unlock*).
2. **🛠️ Teknisi IT**:
   - Melakukan pemindaian QR code di lapangan dan pengisian checklist bulanan/susulan.
   - Melakukan verifikasi kehadiran biometrik wajah dan passkey.
   - Mencatat temuan masalah dan menindaklanjuti perbaikan hingga tuntas.
3. **👔 HRD / SDM & Umum**:
   - Mengelola data master kantor cabang dan divisi/unit kerja.
   - Memantau distribusi fisik aset komputer dan mencetak Kartu Inventaris Standar ATM (CR80).
4. **👁️ Auditor / SKAI (Satuan Kerja Audit Internal)**:
   - Hak akses pengawasan (*read-only audit*).
   - Memantau persentase kepatuhan pemeliharaan rutin seluruh cabang.
   - Menganalisis log riwayat pemeliharaan, temuan kendala, dan menelusuri log jejak audit (*Audit Trail*).
   - Mengunduh dan mencetak laporan resmi bertanda tangan.

---

## 📂 Struktur Direktori & Peta Modul

```text
Maintenance-QR/
├── api/                                  # Folder Utama Aplikasi Web
│   ├── bootstrap.php                     # Master initialization & modular bootstrap loader
│   ├── index.php                         # Entry point / routing utama
│   │
│   ├── includes/                         # Modul Logika Inti & Akses Database
│   │   ├── core.php                      # DB connection (PDO/Sheets), sesi, CSRF, escaping e()
│   │   ├── auth.php                      # Autentikasi sesi, role RBAC, cookie, login throttle
│   │   ├── users.php                     # CRUD user, pendaftaran biometrik, paraf nickname
│   │   ├── master_data.php               # CRUD kantor cabang, divisi kerja, dan kategori aset
│   │   ├── assets.php                    # CRUD master komputer, token QR, mapping sheets
│   │   ├── maintenance.php               # 9 Checklist IT, log pemeliharaan bulanan & susulan
│   │   ├── inventaris_kartu.php          # Model data kartu inventaris CR80 & sinkronisasi
│   │   ├── audit_trail.php               # Logging audit kepatuhan terpusat (POJK / ISO 27001)
│   │   ├── dashboard.php                 # Agregasi metrik analitik dashboard & progress cabang
│   │   └── view.php                      # Global layout renderer, topbar, modal search, sidebar
│   │
│   ├── views/scan/                       # Sub-view Khusus Alur Pemeliharaan QR
│   │   ├── asset_card.php                # Pratinjau identitas aset, spek PC, & kartu kontrol 12 bulan
│   │   ├── form_checklist.php            # Form 9 checklist bulanan & opsi pemeliharaan susulan
│   │   ├── biometric_modal.php           # Modal AI Face Recognition, liveness test, & instant verify
│   │   ├── form_tindak_lanjut.php        # Form penyelesaian & tindak lanjut temuan kendala
│   │   └── success_view.php              # Tampilan konfirmasi hasil pemeliharaan berhasil
│   │
│   ├── helpers/                          # Helper Komunikasi & Utilitas
│   │   ├── websocket_broadcaster.php     # Client broadcaster push event ke WebSocket Server
│   │   ├── GoogleSheetsBridge.php        # Bridge cURL Apps Script untuk Kartu Inventaris
│   │   └── fbd3_data_list.php            # Dataset migrasi & referensi komputer perbankan
│   │
│   ├── login.php / logout.php            # Halaman login modern, lockout timer, & WebAuthn
│   ├── webauthn_handler.php              # REST API endpoint autentikasi Passkeys / WebAuthn
│   ├── user_biometric_enroll.php         # Halaman perekaman biometrik wajah teknisi (HP)
│   ├── unlock.php / unlock_login.php     # Modul pembukaan akun/IP yang terkunci (Emergency Unlock)
│   │
│   ├── dashboard.php                     # Dashboard analitik utama, tab temuan & kartu inventaris
│   ├── realtime_dashboard.js             # Client WebSocket realtime & visual live updater
│   ├── realtime_sync.php                 # HTTP fallback sync data realtime jika WS offline
│   │
│   ├── scan.php                          # Controller utama alur scan QR token
│   ├── scanner.php                       # Fitur in-app QR scanner kamera HP bawaan browser
│   ├── maintenance_detail.php            # Halaman detail rincian checklist & edit log
│   ├── finding.php                       # Modul pencatatan cepat temuan masalah
│   ├── history.php                       # Riwayat log pemeliharaan per unit aset
│   ├── monthly_history.php               # Riwayat pemeliharaan bulanan seluruh kantor cabang
│   ├── audit.php                         # Halaman pengawasan & audit kepatuhan cabang
│   ├── audit_trail.php                   # Halaman penelusuran log aktivitas sistem terpusat
│   ├── export_audit_trail_csv.php        # Ekspor data audit trail ke file CSV
│   │
│   ├── assets.php                        # Daftar inventaris master aset komputer
│   ├── asset_add.php / asset_edit.php    # Form tambah & edit spesifikasi teknis komputer
│   ├── asset_delete.php                  # Handler hapus aset
│   ├── asset_import.php                  # Fitur import massal aset dari file Excel (XLSX) / CSV
│   ├── asset_import_template.php         # Generator unduh template resmi import Excel
│   ├── import_kpo.php / import_fbd3.php  # Utilitas migrasi data cabang khusus
│   │
│   ├── cabang_admin.php                  # Kelola kantor cabang
│   ├── divisi_admin.php                  # Kelola divisi / unit kerja
│   ├── users_admin.php                   # Kelola akun petugas & nama panggilan paraf
│   ├── qr_admin.php                      # Kelola label QR, regenerasi token, & pencetakan
│   │
│   ├── print_qr.php                      # Cetak stiker QR code siap tempel (3 kolom)
│   ├── print_card.php                    # Cetak kartu kontrol fisik 12 bulan (Grid 6, 8, Single)
│   ├── print_inventory_card.php          # Cetak Kartu Inventaris ATM CR80 (Word, PDF, 8/10/12 A4)
│   ├── print_report.php                  # Cetak laporan resmi bulanan formal (PDF ready)
│   ├── export_csv.php                    # Ekspor seluruh data aset ke spreadsheet CSV
│   ├── system_design.php                 # Dokumen teknis arsitektur sistem, DFD, & Mermaid ERD
│   │
│   ├── sw.js                             # PWA Service Worker (caching & background sync)
│   ├── pwa-offline-queue.js              # PWA IndexedDB queue & auto-sync engine
│   ├── pwa_icons.php                     # Generator ikon PWA dinamis berbagai resolusi
│   ├── manifest.webmanifest              # Konfigurasi manifest instalasi aplikasi PWA
│   ├── google_sheets_v4.php              # REST client Google Sheets API v4 (Service Account)
│   └── setup_sheets.php                  # Otomatisasi inisialisasi tab sheet & header kolom
│
├── sql/
│   └── 01_qr_maintenance.sql            # Skema lengkap database MySQL
├── websocket_server.php                  # Server WebSocket Native PHP (RFC 6455)
├── run_websocket_server.bat              # Script jalan 1-klik WebSocket Server di Windows
├── vercel.json                           # Konfigurasi deployment serverless Vercel
├── config.local.example.php              # Contoh file override konfigurasi database lokal
├── PANDUAN_MIGRASI_KARTU_INVENTARIS.md   # Panduan teknis porting modul Kartu ATM CR80
├── README_PRESENTASI.md                  # Materi slide presentasi eksekutif & stakeholder
└── README.md                             # Dokumentasi komprehensif sistem
```

---

## ⚙️ Panduan Instalasi & Konfigurasi

### Opsi A: Instalasi Lokal / VPS (MySQL Database)

1. **Clone Repository**:
   ```bash
   git clone https://github.com/anangsuper/Maintenance-QR.git
   cd Maintenance-QR
   ```

2. **Siapkan Database MySQL**:
   - Buka phpMyAdmin, HeidiSQL, atau terminal MySQL.
   - Buat database baru:
     ```sql
     CREATE DATABASE db_maintenance_qr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     ```
   - Import skema database dari file: `sql/01_qr_maintenance.sql`.

3. **Konfigurasi Koneksi Database**:
   - Buat file `config.local.php` di dalam root direktori atau atur environment variable:
     ```php
     <?php
     // config.local.php
     define('DB_HOST', '127.0.0.1');
     define('DB_PORT', '3306');
     define('DB_NAME', 'db_maintenance_qr');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     ```
   - Atau atur file `.env` pada server web (Apache / Nginx):
     ```ini
     DB_HOST=127.0.0.1
     DB_PORT=3306
     DB_NAME=db_maintenance_qr
     DB_USER=root
     DB_PASS=
     ```

4. **Jalankan Aplikasi Web**:
   - Jika menggunakan **XAMPP / Laragon**: Letakkan repositori di folder `htdocs` atau `www`, lalu akses melalui browser:
     ```text
     http://localhost/Maintenance-QR/api/
     ```
   - Jika menggunakan **PHP Built-in Server**:
     ```bash
     php -S localhost:8000 -t api
     ```
     Lalu buka `http://localhost:8000` di browser Anda.

---

### Opsi B: Deployment Serverless ke Vercel (Google Sheets API v4)

Sistem dapat berjalan 100% *serverless* tanpa database MySQL dengan memanfaatkan Google Sheets sebagai basis data:

1. **Google Cloud Service Account**:
   - Masuk ke [Google Cloud Console](https://console.cloud.google.com/).
   - Buat Project baru -> Masuk ke **APIs & Services** -> Aktifkan **Google Sheets API**.
   - Buka **Credentials** -> Klik **Create Credentials** -> Pilih **Service Account**.
   - Buat kunci baru bertipe **JSON** dan unduh file kredensial tersebut ke komputer Anda.
2. **Persiapan Spreadsheet**:
   - Buat spreadsheet baru di [Google Sheets](https://sheets.new/).
   - Bagikan (*Share*) spreadsheet tersebut kepada email `client_email` yang tercantum di file JSON Service Account dengan hak akses **Editor**.
   - Salin **Spreadsheet ID** dari URL browser Anda:
     `https://docs.google.com/spreadsheets/d/`**[SPREADSHEET_ID]**`/edit`
3. **Konfigurasi Environment Variable di Vercel**:
   - Masuk ke dashboard project Vercel Anda -> Pilih **Settings** -> **Environment Variables**:
     - `GOOGLE_SPREADSHEET_ID` : Masukkan Spreadsheet ID Anda
     - `GOOGLE_CLIENT_EMAIL` : Nilai `client_email` dari file JSON
     - `GOOGLE_PRIVATE_KEY` : Nilai `private_key` dari file JSON (pastikan mencakup baris `-----BEGIN PRIVATE KEY-----` dan `-----END PRIVATE KEY-----`)
4. **Inisialisasi Otomatis Tab Sheets**:
   - Buka tautan berikut di browser Anda:
     ```text
     https://nama-project-anda.vercel.app/setup_sheets.php
     ```
   - Skrip akan otomatis membuat seluruh tab sheet yang dibutuhkan (`Assets`, `Maintenance_Scan`, `Maintenance_Items`, `Findings`, `Branches`, `Departments`, `Users`, `Audit_Trail`) beserta kolom headernya secara lengkap.

---

### Menjalankan Server Real-Time WebSocket

Untuk mengaktifkan sinkronisasi dashboard secara langsung (*real-time live update*):

- **Di Windows**:
  Cukup klik dua kali (*double-click*) file `run_websocket_server.bat` di root direktori proyek.
- **Di Linux VPS / macOS via Terminal**:
  ```bash
  php websocket_server.php 8080
  ```
- **Menjalankan sebagai Background Service di Linux (systemd)**:
  Buat service `/etc/systemd/system/qr-websocket.service`:
  ```ini
  [Unit]
  Description=QR Maintenance Realtime WebSocket Server
  After=network.target

  [Service]
  Type=simple
  User=www-data
  WorkingDirectory=/var/www/html/Maintenance-QR
  ExecStart=/usr/bin/php /var/www/html/Maintenance-QR/websocket_server.php 8080
  Restart=always
  RestartSec=5

  [Install]
  WantedBy=multi-user.target
  ```
  Aktifkan service:
  ```bash
  sudo systemctl daemon-reload
  sudo systemctl enable qr-websocket
  sudo systemctl start qr-websocket
  ```

> 💡 **Catatan**: Jika server WebSocket tidak diaktifkan, sistem akan otomatis beralih ke mode **Auto-Sync Fallback** melalui HTTP polling tanpa kendala.

---

## 🔑 Akun Default & Keamanan Awal

Saat database pertama kali diinisialisasi, sistem menyediakan dua akun default:

| Peran (Role) | Username | Password Default | Nama Panggilan Paraf |
|---|---|---|---|
| **Administrator** | `admin` | `admin123` | Admin |
| **Teknisi IT** | `teknisi` | `teknisi123` | Teknisi |

> 🔒 **Perhatian Keamanan**: 
> 1. Segera lakukan perubahan kata sandi default setelah Anda berhasil masuk ke sistem melalui menu **Akun Pengguna**.
> 2. Daftarkan kredensial biometrik **Passkeys (WebAuthn)** atau **Wajah Teknisi** untuk autentikasi yang lebih cepat dan aman saat bertugas di lapangan.
> 3. Sistem menerapkan kepatuhan standar perbankan: Percobaan login gagal sebanyak 5 kali akan memicu penguncian akun dan IP selama 15 menit. Administrator dapat melakukan pembukaan kunci darurat melalui menu *Emergency Unlock*.

---

## 📄 Lisensi & Kontribusi

Dikembangkan untuk pemeliharaan dan tata kelola aset teknologi informasi yang andal, akuntabel, dan transparan. Silakan berkontribusi atau mengajukan perbaikan melalui *Pull Request* dan *Issue Tracker*.
