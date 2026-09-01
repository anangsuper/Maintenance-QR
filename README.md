# QR Maintenance — Scan QR = Maintenance Selesai

Modul ini dibuat untuk sistem Rekap IT berbasis PHP dengan dukungan **MySQL Database** maupun **Google Spreadsheet API** (tanpa database serverless di Vercel).

## Tujuan

Teknisi melakukan maintenance seperti biasa. Setelah selesai:

1. Scan QR yang ditempel di meja / CPU / laptop.
2. Browser membuka URL QR.
3. Sistem otomatis mencatat maintenance bulan berjalan.
4. Tanggal, jam, bulan, tahun, aset, dan teknisi diambil otomatis.
5. Jika aset yang sama discan lagi pada bulan yang sama, sistem **tidak membuat data ganda**.
6. Bila ada kerusakan, tekan **Ada Temuan / Kerusakan**.

---

## ⚡ Deployment ke Vercel & Google Spreadsheet API

Anda dapat menjalankan modul ini secara gratis di **Vercel** menggunakan **Google Sheets** sebagai database-nya.

### Langkah Setup Google Sheets (API):

1. **Buka Google Sheets Baru** atau spreadsheet rekap yang sudah ada.
2. Klik menu **Ekstensi** -> **Apps Script**.
3. Buka berkas [google_apps_script.js](file:///c:/Users/MIS%20&%20IT/Downloads/qr_maintenance_rekap_it_full/qr_maintenance_module/google_apps_script.js), lalu **salin dan tempel (copy-paste)** seluruh kodenya ke editor Apps Script.
4. Klik tombol **Deploy** di pojok kanan atas -> **New deployment**.
5. Klik ikon gerigi (Select type) -> pilih **Web app**.
6. Atur pengaturan berikut:
   - **Description**: `QR Maintenance API`
   - **Execute as**: `Me` (Email Google Anda)
   - **Who has access**: `Anyone` (Siapa saja, agar server Vercel dapat membaca & menulis ke sheet)
7. Klik **Deploy** dan berikan izin akses (**Authorize Access**).
8. Salin **Web App URL** (URL berakhiran `/exec`).

### Langkah Deployment ke Vercel:

1. Push folder ini ke GitHub: `https://github.com/anangsuper/Maintenance-QR.git`.
2. Buka dashboard [Vercel](https://vercel.com) dan klik **Add New Project** -> **Import Git Repository**.
3. Tambahkan **Environment Variable** di Vercel:
   - Name: `SPREADSHEET_API_URL`
   - Value: *(URL Web App Google Apps Script dari langkah di atas)*
4. Klik **Deploy**. Modul akan otomatis aktif dan berjalan di Vercel!

---

## Struktur yang Diasumsikan dari Rekap IT (Mode MySQL)

Jika menggunakan MySQL, project utama memiliki tabel:

- `users`
- `cabang`
- `divisi`
- `karyawan`
- `kategori_aset`
- `assets`

Kolom aset yang digunakan:

- `assets.id`
- `assets.kode_inventaris`
- `assets.serial_number`
- `assets.merk`
- `assets.model`
- `assets.id_cabang`
- `assets.id_divisi`
- `assets.id_karyawan`
- `assets.id_kategori`
- `assets.status`

---

## 1. Import SQL (Mode MySQL)

Jika menggunakan MySQL lokal/server, import file:

```text
sql/01_qr_maintenance.sql
```

Tabel baru:

- `asset_qr_tokens`
- `maintenance_scan`
- `maintenance_findings`

---

## 2. Database Connection

### Railway / Serverless MySQL

Modul membaca variabel:

```text
DB_HOST
DB_PORT
DB_NAME
DB_USER
DB_PASS
```

### Google Spreadsheet Mode (Vercel)

Cukup tambahkan variabel:

```text
SPREADSHEET_API_URL=https://script.google.com/macros/s/.../exec
```

---

## 3. Generate & Cetak QR

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
