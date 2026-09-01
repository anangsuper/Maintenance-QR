# QR Maintenance — Scan QR = Maintenance Selesai

Modul ini dibuat untuk sistem Rekap IT berbasis PHP dengan dukungan **MySQL Database** maupun **Google Cloud API v4 (Google Sheets API)** tanpa serverless database di Vercel.

## Tujuan

Teknisi melakukan maintenance seperti biasa. Setelah selesai:

1. Scan QR yang ditempel di meja / CPU / laptop.
2. Browser membuka URL QR.
3. Sistem otomatis mencatat maintenance bulan berjalan.
4. Tanggal, jam, bulan, tahun, aset, dan teknisi diambil otomatis.
5. Jika aset yang sama discan lagi pada bulan yang sama, sistem **tidak membuat data ganda**.
6. Bila ada kerusakan, tekan **Ada Temuan / Kerusakan**.

---

## ⚡ Deployment ke Vercel dengan Google Cloud API (Google Sheets API v4)

Anda dapat menghubungkan modul ini langsung ke **Google Sheets API v4 (REST API)** resmi menggunakan **Google Cloud Service Account** tanpa Apps Script.

### 1. Buat Service Account di Google Cloud Console:

1. Buka [Google Cloud Console](https://console.cloud.google.com/).
2. Buat Project baru atau pilih Project yang sudah ada.
3. Buka menu **APIs & Services** -> **Library** -> Cari **Google Sheets API** -> Klik **Enable**.
4. Buka menu **APIs & Services** -> **Credentials** -> Klik **Create Credentials** -> Pilih **Service Account**.
5. Isi nama Service Account (misal: `qr-maintenance-sa`), lalu klik **Create and Continue** -> **Done**.
6. Klik pada Service Account yang baru dibuat -> Masuk ke tab **Keys** -> Klik **Add Key** -> **Create new key** -> Pilih **JSON**.
7. Berkas JSON kredensial akan terunduh ke komputer Anda. Buka berkas JSON tersebut dengan Text Editor.

### 2. Bagikan Google Sheet ke Service Account:

1. Buka Google Sheet rekap aset Anda.
2. Salin email Service Account dari JSON (kolom `"client_email"`, contoh: `qr-maintenance-sa@project-name.iam.gserviceaccount.com`).
3. Klik tombol **Bagikan (Share)** di Google Sheet -> Tempel email Service Account -> Berikan akses **Editor** -> Klik **Kirim (Send)**.
4. Salin **Spreadsheet ID** dari URL Google Sheet Anda:
   - URL: `https://docs.google.com/spreadsheets/d/1aBcDeFgHiJkLmNoPqRsTuVwXyZ/edit`
   - ID: `1aBcDeFgHiJkLmNoPqRsTuVwXyZ`

### 3. Masukkan Environment Variable di Vercel:

Buka dashboard [Vercel](https://vercel.com) -> Masuk ke project Anda -> **Settings** -> **Environment Variables** -> Tambahkan 3 variabel berikut:

1. `GOOGLE_SPREADSHEET_ID`: *(Spreadsheet ID Anda)*
2. `GOOGLE_CLIENT_EMAIL`: *(Isi `"client_email"` dari JSON)*
3. `GOOGLE_PRIVATE_KEY`: *(Isi `"private_key"` dari JSON, termasuk `-----BEGIN PRIVATE KEY-----` dan `-----END PRIVATE KEY-----`)*

Klik **Deploy** atau **Redeploy**. Modul akan otomatis berjalan menggunakan Google Cloud Sheets API v4!

---

## Struktur Tabel Google Sheet yang Digunakan:

Modul ini membaca/menulis tab sheet berikut di Google Sheet Anda:
- `Assets`
- `Cabang`
- `Divisi`
- `Karyawan`
- `Kategori_Aset`
- `Asset_QR_Tokens`
- `Maintenance_Scan`
- `Maintenance_Findings`

---

## Mode MySQL (Opsional)

Jika menggunakan MySQL lokal/server (seperti Railway), cukup atur variabel database berikut:

```text
DB_HOST
DB_PORT
DB_NAME
DB_USER
DB_PASS
```

Import file SQL:
```text
sql/01_qr_maintenance.sql
```

---

## Generate & Cetak QR

Buka menu **QR Aset** -> Tekan **Generate QR yang Belum Ada** -> **Cetak Semua QR**.

---

## Hasil Akhir

Kegiatan teknisi menjadi:

```text
Datang ke PC
→ lakukan maintenance
→ scan QR
→ muncul "Maintenance Berhasil Dicatat"
→ pindah ke PC berikutnya
```
