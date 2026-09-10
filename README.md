# 🖥️ QR Maintenance — Sistem Pemeliharaan IT Berbasis QR Code & Kartu Kontrol 12 Bulan

Aplikasi web modern berbasis PHP untuk manajemen inventaris komputer/aset IT dan pencatatan pemeliharaan berkala (*preventive maintenance*) secara cepat, akurat, dan transparan menggunakan pemindaian **QR Code** dan **Kartu Kontrol Digital 12 Bulan**.

Aplikasi ini mendukung **Dual-Storage Engine**: dapat berjalan secara *serverless* menggunakan **Google Cloud Sheets API v4** (sangat cocok untuk hosting di **Vercel**) maupun menggunakan **Database MySQL**.

---

## 📑 Daftar Isi
1. [Alur Kerja Utama (System Workflow)](#-alur-kerja-utama-system-workflow)
2. [Alur Detail Step-by-Step](#-alur-detail-step-by-step)
   - [A. Alur Pendaftaran Akun Teknisi & Nama Panggilan](#a-alur-pendaftaran-akun-teknisi--nama-panggilan)
   - [B. Alur Registrasi Komputer & Cetak Stiker QR](#b-alur-registrasi-komputer--cetak-stiker-qr)
   - [C. Alur Scan Maintenance Rutin di Lapangan](#c-alur-scan-maintenance-rutin-di-lapangan)
   - [D. Alur Temuan Kendala & Tindak Lanjut Masalah](#d-alur-temuan-kendala--tindak-lanjut-masalah)
   - [E. Alur Kartu Kontrol IT 12 Bulan & Kolom Paraf](#e-alur-kartu-kontrol-it-12-bulan--kolom-paraf)
3. [Fitur Unggulan Sistem](#-fitur-unggulan-sistem)
4. [Hak Akses & Multi-Role Pengguna](#-hak-akses--multi-role-pengguna)
5. [9 Standar Checklist Pemeliharaan Komputer](#-9-standar-checklist-pemeliharaan-komputer)
6. [Struktur File & Peta Modul](#-struktur-file--peta-modul)
7. [Panduan Instalasi & Konfigurasi](#-panduan-instalasi--konfigurasi)
8. [Akun Default Awal](#-akun-default-awal)

---

## 🚀 Alur Kerja Utama (System Workflow)

```mermaid
flowchart TD
    A["👑 Admin / IT Head"] -->|"Buat Akun & Nama Panggilan"| B["👥 Data Akun Teknisi"]
    A -->|"Input Data Aset Komputer"| C["💻 Master Komputer"]
    C -->|"Generate & Cetak Stiker QR"| D["🏷️ Stiker QR Tertempel di PC"]
    
    E["🛠️ Teknisi Lapangan"] -->|"1. Datang & Periksa Fisik PC"| D
    E -->|"2. Scan QR pakai Kamera Smartphone"| F["📱 Halaman Scan & Status"]
    
    F -->|"3. Isi 9 Checklist Maintenance"| G{"Ada Kerusakan / Kendala?"}
    G -->|"Tidak (Normal)"| H["Simpan Pemeliharaan Selesai"]
    G -->|"Ya (Ada Masalah)"| I["Catat Temuan Kerusakan & Pending"]
    
    I -->|"Perbaikan Dilakukan"| J["Tindak Lanjut & Selesaikan"]
    J -->|"Selesai Diperbaiki"| H
    
    H -->|"Otomatis Masuk Log"| K["📊 Matriks Kartu Kontrol 12 Bulan"]
    K -->|"Cetak Kartu Fisik / Laporan"| L["🖨️ Kartu Kontrol IT (Paraf Nama Panggilan)"]
```

---

## 🔍 Alur Detail Step-by-Step

### A. Alur Pendaftaran Akun Teknisi & Nama Panggilan
1. **Pembuatan Akun Petugas**:
   - Admin membuka menu **Kelola Data -> Kelola Pengguna**.
   - Admin mengisi: Username, Password, **Nama Lengkap**, **Nama Panggilan Teknisi**, Role (Teknisi/Admin/Auditor), dan No. HP.
   - *Nama Panggilan* ini yang nantinya otomatis tercetak pada kolom **PARAF** kartu kontrol 12 bulan (misal: *Budi, Ahmad, Roni*).

---

### B. Alur Registrasi Komputer & Cetak Stiker QR
1. **Input Data Komputer**:
   - Masuk ke menu **Komputer -> Tambah Komputer**.
   - Masukkan Kode Inventaris, Nama Pengguna / Karyawan, Cabang, Divisi, IP Address, Merk/Model, serta tipe unit (PC Desktop, Laptop, Server).
2. **Cetak Stiker QR Code**:
   - Buka menu **Cetak -> Stiker QR**.
   - Pilih filter cabang/divisi atau pilih komputer tertentu.
   - Klik Cetak. Sistem menghasilkan layout stiker 3 kolom per baris berisi:
     - Logo & Judul "KARTU PEMELIHARAAN IT"
     - Nama Perangkat (membungkus rapi jika panjang)
     - Kode Inventaris & IP Address
     - **Nama Pengguna Lengkap** + Divisi
     - Lokasi Cabang Lengkap
     - QR Code unik siap scan
3. **Penempelan Stiker**:
   - Stiker dicetak pada kertas stiker dan ditempelkan pada casing CPU / bodi laptop.

---

### C. Alur Scan Maintenance Rutin di Lapangan
1. **Kunjungan Teknisi**:
   - Teknisi mendatangi unit komputer pengguna sesuai jadwal bulanan.
   - Teknisi melakukan pembersihan fisik (debu casing, fan) dan pemeriksaan sistem.
2. **Pemindaian QR Code**:
   - Teknisi membuka kamera smartphone (browser Android atau browser apa pun) dan mengarahkan ke stiker QR.
   - Sistem membuka URL aman `scan.php?token=...`.
3. **Tampilan Halaman Scan**:
   - Menampilkan identitas lengkap komputer (Nama Pengguna, Divisi, IP, Kode).
   - Menampilkan status bulan berjalan:
     - **Belum Dirawat**: Form checklist terbuka siap diisi.
     - **Sudah Selesai**: Menampilkan rincian tanggal, teknisi pemeriksa, dan riwayat checklist.
   - Menampilkan **Pratinjau Kartu Kontrol IT 12 Bulan** (Januari – Desember) lengkap dengan paraf teknisi pada bulan-bulan yang sudah selesai.
4. **Pengisian Checklist**:
   - Teknisi mencentang 9 butir checklist pemeriksaan standar.
   - Jika semua normal, pilih status **"Normal / Selesai"**.
   - Klik tombol **Simpan Pemeliharaan**.

---

### D. Alur Temuan Kendala & Tindak Lanjut Masalah
1. **Pencatatan Masalah (Finding)**:
   - Jika saat pemeriksaan ditemukan kerusakan (misal: *Fan mati, HDD bad sector, Windows corrupt*), teknisi mengisi kolom **Temuan Masalah & Rekomendasi**.
   - Sistem mencatat status log sebagai **"Ada Masalah / Perlu Tindak Lanjut"**.
   - Banner peringatan oranye/merah muncul di halaman kartu komputer tersebut.
2. **Proses Tindak Lanjut (Follow-Up)**:
   - Setelah suku cadang tersedia atau perbaikan selesai dikerjakan, teknisi membuka kembali halaman scan atau menu audit.
   - Klik tombol **"TINDAK LANJUTI / SELESAIKAN SEKARANG"**.
   - Masukkan catatan tindakan perbaikan yang telah dilakukan.
3. **Kembali Normal**:
   - Status temuan ditandai **Selesai (Resolved)**.
   - Status maintenance komputer otomatis kembali menjadi **Normal / Selesai**, dan kartu kontrol terupdate.

---

### E. Alur Kartu Kontrol IT 12 Bulan & Kolom Paraf
1. **Struktur Kartu Kontrol**:
   - Tabel berisi 12 baris mewakili bulan Januari hingga Desember.
   - Header kartu menampilkan:
     - **NAMA**: Nama lengkap karyawan pengguna komputer beserta divisi (contoh: *Bpk. Roni Wijaya, S.Kom (IT / MIS)*).
     - **IP / KODE**: Alamat IP dan Kode Inventaris aset.
     - **UNIT / PRT**: Merk tipe komputer dan tipe printer yang terhubung.
     - **CABANG**: Kantor cabang / KPO.
2. **Kolom Check 1 s/d 9**:
   - Menampilkan tanda centang (**✓**) jika butir pemeriksaan telah dilakukan pada bulan tersebut.
3. **Kolom PARAF**:
   - Diisi otomatis dengan **Nama Panggilan Teknisi** yang merawat unit tersebut (misal: *Budi, Ahmad, Roni*).
   - Menghasilkan tampilan kartu yang rapi, pas di kolom tabel, dan mudah dibaca tanpa terpotong.
4. **Pilihan Cetak Fisik**:
   - Format **Grid 6** (6 kartu per lembar A4).
   - Format **Grid 8** (8 kartu per lembar A4).
   - Format **Single** (1 kartu besar per halaman).

---

## ✨ Fitur Unggulan Sistem

| Fitur | Penjelasan |
|---|---|
| **📱 Mobile-First Scan** | Halaman scan responsif, ringan, dan cepat dibuka di browser smartphone apa pun tanpa perlu aplikasi khusus. |
| **🛡️ Anti-Duplikasi Log** | Mencegah dobel input log jika komputer yang sama discan berulang dalam 1 bulan. |
| **📝 Kartu Kontrol 12 Bulan** | Rekap visual otomatis 12 bulan mirip kartu gantung manual pemeliharaan IT. |
| **🏷️ Cetak Stiker QR Presisi** | Desain stiker rapi dengan multiline nama komputer dan nama cabang lengkap. |
| **🔄 Dual-Storage Engine** | Pilihan penyimpanan: Google Sheets (Cloud Serverless) atau Database MySQL. |
| **📊 Dashboard Monitoring** | Grafik persentase penyelesaian maintenance per cabang, divisi, dan bulan. |
| **📋 Laporan Siap Cetak (PDF)** | Laporan bulanan formal yang siap ditandatangani teknisi, pimpinan, dan auditor. |

---

## 👥 Hak Akses & Multi-Role Pengguna

1. **👑 Administrator**:
   - Memiliki akses tak terbatas ke seluruh sistem.
   - Menambah, mengedit, dan menghapus data Cabang, Divisi, Komputer, dan Pengguna.
   - Mengatur nama panggilan paraf teknisi.
2. **🛠️ Teknisi IT**:
   - Melakukan scan QR maintenance pada komputer kantor.
   - Mengisi 9 butir checklist pemeliharaan bulanan.
   - Mencatat temuan kerusakan dan menindaklanjuti perbaikan hingga tuntas.
3. **👁️ Auditor / SKAI**:
   - Akses pengawasan (*read-only audit*).
   - Memantau persentase kepatuhan pemeliharaan rutin seluruh cabang.
   - Meninjau log riwayat pemeliharaan dan temuan kerusakan.
   - Mengunduh dan mencetak laporan resmi untuk keperluan audit internal.

---

## 📋 9 Standar Checklist Pemeliharaan Komputer

Setiap kali melakukan pemindaian bulanan, teknisi melakukan pengecekan berdasarkan 9 standar:
1. **Pembersihan Fisik**: Pembersihan debu pada bodi casing, motherboard, dan komponen luar.
2. **Pemeriksaan Kipas (Airflow)**: Pengecekan kipas PSU, casing, dan heatsink processor agar tidak macet.
3. **Suhu & Thermal Paste**: Pemantauan suhu CPU dan penggantian thermal paste jika sudah kering.
4. **Kesehatan Storage (SSD/HDD)**: Pengecekan status kesehatan drive menggunakan indikator S.M.A.R.T.
5. **Update OS & Patch Keamanan**: Memastikan sistem operasi mendapatkan update patch keamanan terbaru.
6. **Antivirus & Scan Malware**: Pembaruan database virus dan pemindaian cepat terhadap potensi ancaman.
7. **Pembersihan File Sampah (Cache/Temp)**: Mengosongkan temporary files, cache browser, dan recycle bin.
8. **Optimasi Startup & Memori**: Mematikan aplikasi startup yang tidak perlu agar RAM bekerja optimal.
9. **Verifikasi Cadangan Data (Backup)**: Memastikan data penting pengguna telah tercadangkan dengan aman.

---

## 📂 Struktur File & Peta Modul

```text
Maintenance-QR/
├── api/                            # Modul Aplikasi & Endpoint Utama
│   ├── bootstrap.php               # Master loader modular aplikasi
│   ├── includes/                   # [MODULAR] Modul domain logika & database
│   │   ├── core.php                # Config, session, database PDO / Sheets, CSRF, escaping
│   │   ├── auth.php                # Autentikasi sesi, role, cookie, WebAuthn passkeys
│   │   ├── users.php               # CRUD user, biometrik wajah, nama panggilan paraf
│   │   ├── master_data.php         # Referensi cabang, divisi, kategori, karyawan
│   │   ├── assets.php              # CRUD komputer, token QR, mapping sheets
│   │   ├── maintenance.php         # 9 Checklist, log pemeliharaan, temuan masalah
│   │   ├── dashboard.php           # Rekap statistik dashboard & data audit
│   │   └── view.php                # Render layout HTML & navbar global
│   │
│   ├── views/                      # [MODULAR] Sub-view & komponen antarmuka
│   │   └── scan/
│   │       ├── biometric_modal.php # Modal Face Recognition, CSS, & script Face-API
│   │       ├── success_view.php    # Layar konfirmasi berhasil simpan
│   │       ├── form_tindak_lanjut.php # Form penanganan & perbaikan temuan
│   │       ├── form_checklist.php  # Form 9 checklist pemeliharaan bulanan
│   │       └── asset_card.php      # Tampilan utama kartu kontrol 12 bulan & spek PC
│   │
│   ├── google_sheets_v4.php        # Client REST Google Sheets API v4 (Service Account)
│   ├── setup_sheets.php            # Inisialisasi otomatis tab sheet & header kolom
│   │
│   ├── login.php / logout.php      # Autentikasi sesi pengguna
│   ├── dashboard.php               # Dashboard monitoring progres & statistik
│   │
│   ├── scan.php                    # Controller scan QR, form checklist, & kartu kontrol
│   ├── maintenance_detail.php      # Rincian log pemeriksaan & edit checklist
│   ├── finding.php                 # Modul pencatatan & tindak lanjut temuan masalah
│   ├── history.php                 # Riwayat pemeliharaan per unit komputer
│   ├── monthly_history.php         # Riwayat pemeliharaan bulanan seluruh unit
│   ├── audit.php                   # Halaman audit kepatuhan (Auditor & Admin)
│   │
│   ├── assets.php                  # Daftar master komputer/aset
│   ├── asset_add.php               # Form tambah komputer baru
│   ├── asset_edit.php              # Form ubah spesifikasi & user komputer
│   ├── asset_delete.php            # Aksi hapus komputer
│   ├── import_kpo.php              # Import massal data aset dari spreadsheet/CSV
│   │
│   ├── cabang_admin.php            # Kelola master kantor cabang
│   ├── divisi_admin.php            # Kelola master divisi / unit kerja
│   ├── users_admin.php             # Kelola akun admin/teknisi & nama panggilan paraf
│   ├── qr_admin.php                # Kelola & regenerasi token QR aset
│   │
│   ├── print_qr.php                # Cetak stiker QR code siap tempel (3 kolom)
│   ├── print_card.php              # Cetak kartu kontrol fisik 12 bulan (Grid 6 / 8 / Single)
│   ├── print_report.php            # Cetak laporan resmi bulanan (PDF ready)
│   └── export_csv.php              # Ekspor data ke spreadsheet CSV
│
├── sql/
│   └── 01_qr_maintenance.sql      # Skema database MySQL lengkap
├── vercel.json                     # Konfigurasi deployment Vercel serverless
├── README_PRESENTASI.md            # Materi presentasi eksekutif & stakeholder
└── README.md                       # Dokumentasi lengkap sistem
```

---

## ⚙️ Panduan Instalasi & Konfigurasi

### Opsi A: Deployment ke Vercel (Google Sheets API v4)

Aplikasi dapat dijalankan secara *serverless* tanpa database MySQL menggunakan Google Sheets:

1. **Google Cloud Service Account**:
   - Buka [Google Cloud Console](https://console.cloud.google.com/).
   - Buat Project baru -> Aktifkan **Google Sheets API**.
   - Buka **Credentials** -> Buat **Service Account** -> Unduh file **Key (JSON)**.
2. **Persiapan Spreadsheet**:
   - Buat Google Spreadsheet baru di Google Drive.
   - Bagikan (*Share*) spreadsheet ke email `client_email` dari file JSON sebagai **Editor**.
   - Salin **Spreadsheet ID** dari URL browser.
3. **Atur Environment Variables di Vercel**:
   - Masuk ke dashboard Vercel -> Settings -> Environment Variables:
     - `GOOGLE_SPREADSHEET_ID` : ID Spreadsheet Anda
     - `GOOGLE_CLIENT_EMAIL` : Nilai `client_email` dari JSON
     - `GOOGLE_PRIVATE_KEY` : Nilai `private_key` dari JSON (termasuk `-----BEGIN PRIVATE KEY-----`)
4. **Inisialisasi Otomatis**:
   - Buka URL: `https://domain-anda.vercel.app/setup_sheets.php` untuk membuat seluruh tab sheet otomatis.

---

### Opsi B: Instalasi Lokal (XAMPP / Laragon / Linux VPS)

1. **Clone Repository**:
   ```bash
   git clone https://github.com/anangsuper/Maintenance-QR.git
   ```
2. **Import Database MySQL**:
   - Buat database baru di phpMyAdmin/MySQL (misal: `db_maintenance_qr`).
   - Import file: `sql/01_qr_maintenance.sql`.
3. **Konfigurasi Environment Server**:
   Atur file `.env` atau konfigurasi server Apache/Nginx:
   ```ini
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=db_maintenance_qr
   DB_USER=root
   DB_PASS=
   ```
4. Buka di browser: `http://localhost/Maintenance-QR/api/`.

---

## 🔑 Akun Default Awal

Saat pertama kali diinisialisasi, sistem menyediakan akun default:

| Role | Username | Password Default | Nama Panggilan |
|---|---|---|---|
| **Administrator** | `admin` | `admin123` | Admin |
| **Teknisi IT** | `teknisi` | `teknisi123` | Teknisi |

> 🔒 **Keamanan**: Segera ubah password default Anda setelah login pertama kali melalui menu **Kelola Data -> Kelola Pengguna**.
