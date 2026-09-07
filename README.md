# 🖥️ QR Maintenance — Sistem Rekap & Pemeliharaan IT Berbasis QR Code

Aplikasi web modern berbasis PHP untuk manajemen inventaris komputer/aset IT dan pencatatan pemeliharaan berkala (*preventive maintenance*) secara cepat dan akurat menggunakan pemindaian **QR Code**.

Aplikasi ini mendukung **Dual-Storage Engine**: dapat berjalan secara *serverless* menggunakan **Google Cloud Sheets API v4** (sangat cocok untuk hosting di **Vercel**) maupun menggunakan **MySQL Database**.

---

## 🚀 Alur Kerja Utama (*Workflow*)

```text
1. Teknisi mendatangi Komputer / Aset IT
   ⬇
2. Teknisi melakukan pembersihan & pengecekan fisik/software
   ⬇
3. Teknisi memindai (Scan) Stiker QR pada bodi komputer menggunakan kamera HP
   ⬇
4. Sistem otomatis mencatat log maintenance bulan berjalan (Teknisi, Tanggal & Jam, Status)
   ⬇
5. Teknisi mengisi / menyesuaikan 9 Checklist Standar & mencatat temuan kerusakan (jika ada)
   ⬇
6. Progres pada Dashboard Cabang & Divisi otomatis terupdate secara real-time
```

---

## ✨ Fitur-Fitur Utama

### 1. 👥 Manajemen Akses & Multi-Role Pengguna
- **👑 Administrator**: Akses penuh ke seluruh menu kelola data (Cabang, Divisi, Komputer, Akun Pengguna), generate QR, dan cetak laporan.
- **🛠️ Teknisi IT**: Melakukan scan QR, pengisian checklist 9 butir pemeliharaan, pencatatan kerusakan/temuan, dan melihat riwayat aset.
- **👁️ Auditor / SKAI**: Akses pemantauan kepatuhan jadwal maintenance, verifikasi data, dan cetak laporan resmi.
- Manajemen Pengguna lengkap: Tambah akun teknisi baru, edit profil, reset password, dan status aktif/nonaktif akun.

### 2. 🏢 Kelola Master Data Lengkap
- **Master Komputer / Aset IT**: Tambah, edit, hapus, filter pencarian cepat, status aktif/nonaktif, serta impor massal data aset.
- **Master Cabang**: Pengelompokan aset berdasarkan lokasi kantor cabang, lengkap dengan penanggung jawab dan monitoring persentase penyelesaian bulanan.
- **Master Divisi / Unit Kerja**: Klasifikasi divisi (misal: HRD, Operasional, Akuntansi, IT) dengan matriks pemeliharaan.

### 3. 📱 Pemindaian QR & 9 Butir Checklist Standar
- Setiap komputer memiliki token QR unik yang aman.
- **Anti-Duplikasi**: Mencegah entri ganda jika aset discan lebih dari satu kali dalam bulan yang sama.
- **9 Standar Checklist Pemeriksaan IT**:
  1. *Pembersihan debu fisik & casing*
  2. *Pemeriksaan kipas pendingin & sirkulasi udara (airflow)*
  3. *Pengecekan suhu prosesor & thermal paste*
  4. *Kesehatan media penyimpanan (SSD / Harddisk SMART check)*
  5. *Pembaruan sistem operasi & security patch*
  6. *Pembaruan antivirus & pemindaian ancaman/malware*
  7. *Pembersihan file sementara (temp files) & cache sampah*
  8. *Optimasi aplikasi startup & utilisasi memori (RAM)*
  9. *Verifikasi cadangan data (backup) penting pengguna*
- Pencatatan **Temuan Masalah / Kerusakan Hardware/Software** dengan status penanganan.

### 4. 🖨️ Generator & Pencetakan Label
- **Cetak Stiker QR Code**: Generator batch untuk mencetak stiker QR siap tempel di bodi CPU / monitor / laptop.
- **Cetak Kartu Pemeliharaan Fisik**: Format kartu gantung pemeliharaan fisik untuk arsip di dekat unit komputer.
- **Cetak Laporan Bulanan (PDF/Print Ready)**: Laporan rekapitulasi formal per cabang/divisi yang siap ditandatangani oleh Teknisi, Kepala Divisi, dan Auditor.
- **Ekspor Data ke CSV / Excel**: Unduh riwayat dan aset untuk olah data mandiri.

### 5. 📊 Dashboard & Audit Trail
- Statistik ringkasan total aset, unit selesai dirawat, unit menunggu perawatan, dan persentase kepatuhan.
- Filter cepat berdasarkan Cabang, Divisi, Kategori Aset, dan Bulan/Tahun.
- Riwayat per unit dan riwayat bulanan yang transparan.

---

## 🏗️ Struktur Direktori Proyek

```text
Maintenance-QR/
├── api/                        # Modul PHP & Handler Endpoint
│   ├── bootstrap.php           # Core logic, auth, DB & Google Sheets helper
│   ├── google_sheets_v4.php    # REST client Google Sheets API v4 (Service Account)
│   ├── setup_sheets.php        # Inisialisasi otomatis tab & header Google Sheets
│   ├── index.php               # Entrypoint redirect
│   ├── login.php / logout.php  # Autentikasi pengguna & session handler
│   ├── dashboard.php           # Dashboard monitoring & statistik utama
│   ├── scan.php                # Endpoint pemindaian QR & submit maintenance
│   ├── maintenance_detail.php  # Rincian 9 checklist & edit log pemeriksaan
│   ├── finding.php             # Input temuan kerusakan & masalah
│   ├── history.php             # Riwayat per aset
│   ├── monthly_history.php     # Riwayat bulanan sistem
│   ├── audit.php               # Halaman audit kepatuhan
│   ├── asset_add.php           # Form penambahan komputer
│   ├── asset_edit.php          # Form edit spesifikasi & data komputer
│   ├── asset_delete.php        # Hapus komputer
│   ├── import_kpo.php          # Impor data aset massal
│   ├── cabang_admin.php        # Kelola cabang kantor
│   ├── divisi_admin.php        # Kelola divisi / unit kerja
│   ├── users_admin.php         # Kelola akun admin, teknisi, & auditor
│   ├── qr_admin.php            # Manajemen & regenerasi token QR
│   ├── print_qr.php            # Format cetak stiker QR
│   ├── print_card.php          # Format cetak kartu gantung aset
│   ├── print_report.php        # Format cetak laporan rekap resmi
│   └── export_csv.php          # Ekspor data ke spreadsheet CSV
├── sql/
│   └── 01_qr_maintenance.sql  # Skema tabel MySQL (jika memakai mode MySQL)
├── vercel.json                 # Konfigurasi routing & runtime Vercel
└── README.md                   # Dokumentasi lengkap aplikasi
```

---

## ⚙️ Panduan Instalasi & Konfigurasi

### Opsi A: Deployment ke Vercel (Google Sheets API v4)

Aplikasi ini dapat langsung dideploy ke [Vercel](https://vercel.com/) tanpa perlu menyiapkan database SQL serverless.

1. **Buat Google Cloud Service Account**:
   - Buka [Google Cloud Console](https://console.cloud.google.com/).
   - Buat Project baru -> Masuk menu **APIs & Services** -> **Library** -> Cari dan **Enable** `Google Sheets API`.
   - Buka menu **Credentials** -> **Create Credentials** -> **Service Account**.
   - Masuk ke Service Account yang dibuat -> Tab **Keys** -> **Add Key** -> **Create new key (JSON)**. Berkas JSON akan terunduh.

2. **Siapkan Google Spreadsheet**:
   - Buat Google Spreadsheet baru di Google Drive Anda.
   - Klik tombol **Bagikan (Share)** -> Masukkan email Service Account (terdapat di file JSON kolom `client_email`) dengan hak akses **Editor**.
   - Salin **Spreadsheet ID** dari URL (contoh: `https://docs.google.com/spreadsheets/d/`**`[SPREADSHEET_ID]`**`/edit`).

3. **Konfigurasi Environment Variables di Vercel**:
   - Buka project di dashboard Vercel -> **Settings** -> **Environment Variables**, tambahkan:
     | Variabel | Deskripsi |
     |---|---|
     | `GOOGLE_SPREADSHEET_ID` | ID Google Spreadsheet Anda |
     | `GOOGLE_CLIENT_EMAIL` | Nilai `client_email` dari berkas JSON |
     | `GOOGLE_PRIVATE_KEY` | Nilai `private_key` dari berkas JSON (termasuk baris `-----BEGIN PRIVATE KEY-----`) |

4. **Inisialisasi Sheet Otomatis**:
   - Setelah deploy selesai, buka URL: `https://domain-anda.vercel.app/setup_sheets.php` untuk membuat seluruh tab sheet (`Assets`, `Cabang`, `Divisi`, `Karyawan`, `Kategori_Aset`, `Asset_QR_Tokens`, `Maintenance_Scan`, `Maintenance_Findings`, `Maintenance_Checklists`, `Users`) beserta header kolom secara otomatis.

---

### Opsi B: Instalasi Lokal / Hosting MySQL (XAMPP / Laragon / VPS)

1. **Clone Repository**:
   ```bash
   git clone https://github.com/anangsuper/Maintenance-QR.git
   ```
2. **Import Database**:
   - Buat database baru di MySQL (misal: `db_maintenance_qr`).
   - Import file skema: `sql/01_qr_maintenance.sql`.
3. **Konfigurasi `.env` atau Environment Server**:
   ```ini
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=db_maintenance_qr
   DB_USER=root
   DB_PASS=
   ```
4. Jalankan local web server dan buka di browser melalui `http://localhost/Maintenance-QR/api/`.

---

## 🔑 Akun Default Awal

Saat pertama kali diinisialisasi, sistem menyediakan akun bawaan:

| Peran (*Role*) | Username | Password Default |
|---|---|---|
| **Administrator** | `admin` | `admin123` |
| **Teknisi IT** | `teknisi` | `teknisi123` |

> ⚠️ **PENTING**: Segera ubah password akun default setelah login pertama kali melalui menu **Kelola Data -> Kelola Pengguna** demi keamanan.

---

## 🛡️ Keamanan & Perlindungan Data
- **CSRF Token Protection**: Setiap request POST dilindungi dengan token CSRF.
- **Password Hashing**: Menggunakan algoritma enkripsi `BCRYPT`.
- **Session Expiration**: Sesi login otomatis habis jika tidak ada aktivitas dalam batas waktu tertentu.
- **XSS Sanitization**: Proteksi escape output HTML pada setiap tampilan data.

---

## 📄 Lisensi & Kontribusi
Aplikasi ini dikembangkan untuk mempermudah dan mendisiplinkan proses pemeliharaan perangkat IT. Silakan kembangkan dan sesuaikan dengan kebutuhan organisasi Anda.
