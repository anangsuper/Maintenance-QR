# QR Maintenance — Scan QR = Maintenance Selesai

Modul ini dibuat untuk sistem Rekap IT berbasis PHP + MySQL.

## Tujuan

Teknisi melakukan maintenance seperti biasa. Setelah selesai:

1. Scan QR yang ditempel di meja / CPU / laptop.
2. Browser membuka URL QR.
3. Sistem otomatis mencatat maintenance bulan berjalan.
4. Tanggal, jam, bulan, tahun, aset, dan teknisi diambil otomatis.
5. Jika aset yang sama discan lagi pada bulan yang sama, sistem **tidak membuat data ganda**.
6. Bila ada kerusakan, tekan **Ada Temuan / Kerusakan**.

Tidak ada checklist wajib.

---

## Struktur yang Diasumsikan dari Rekap IT

Project utama sudah memiliki tabel:

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

Modul mencoba mendeteksi otomatis nama kolom seperti `nama_cabang` / `nama`, `nama_divisi` / `nama`, dan seterusnya.

---

## 1. Copy Folder

Contoh penempatan:

```text
project-rekap-it/
├── assets/
├── config/
├── controllers/
├── models/
├── views/
├── modules/
│   └── qr_maintenance/
│       ├── bootstrap.php
│       ├── dashboard.php
│       ├── scan.php
│       ├── qr_admin.php
│       ├── print_qr.php
│       ├── finding.php
│       ├── history.php
│       ├── export_csv.php
│       └── sql/
└── login.php
```

---

## 2. Import SQL

Import file:

```text
sql/01_qr_maintenance.sql
```

Tabel baru:

- `asset_qr_tokens`
- `maintenance_scan`
- `maintenance_findings`

Data aset lama tidak dihapus.

---

## 3. Database Connection

### Railway

Modul otomatis membaca salah satu pasangan environment variable berikut:

```text
DB_HOST
DB_PORT
DB_NAME
DB_USER
DB_PASS
```

atau variable Railway MySQL:

```text
MYSQLHOST
MYSQLPORT
MYSQLDATABASE
MYSQLUSER
MYSQLPASSWORD
```

### Lokal

Copy:

```text
config.local.example.php
```

menjadi:

```text
config.local.php
```

kemudian isi database.

**Jangan commit `config.local.php` ke GitHub.**

---

## 4. Login / Session

Agar scan benar-benar mengetahui siapa teknisinya, user harus login.

Modul membaca ID user dari salah satu session berikut:

```php
$_SESSION['user_id']
$_SESSION['id_user']
$_SESSION['id']
```

Nama teknisi dibaca dari:

```php
$_SESSION['nama']
$_SESSION['name']
$_SESSION['nama_user']
$_SESSION['username']
```

Role admin dibaca dari:

```php
$_SESSION['role']
$_SESSION['user_role']
$_SESSION['level']
```

Jika nama session project Anda berbeda, edit fungsi berikut di `bootstrap.php`:

```php
current_user_id()
current_user_name()
current_user_role()
```

### Agar scan tetap "cukup scan saja"

Login ke Rekap IT **sekali** melalui HP teknisi dan biarkan session tetap aktif.

Setelah itu setiap scan QR langsung mencatat maintenance otomatis.

---

## 5. Generate QR

Buka:

```text
/modules/qr_maintenance/qr_admin.php
```

Tekan:

```text
Generate QR yang Belum Ada
```

Sistem membuat token acak 32 karakter untuk masing-masing aset.

Token tidak menyimpan password, IP, atau data sensitif.

QR berisi URL seperti:

```text
https://domain-anda.com/modules/qr_maintenance/scan.php?t=TOKEN_ACAK
```

---

## 6. Cetak dan Tempel

Dari menu QR Aset:

```text
Cetak
```

atau:

```text
Cetak Semua QR
```

Tempel pada:

- meja kerja;
- CPU;
- laptop;
- atau tempat yang paling mudah dipindai.

Satu aset sebaiknya memiliki satu QR.

Jika QR ditempel di meja, QR tersebut tetap mewakili aset/PC yang dipakai pada meja tersebut.

---

## 7. Alur Scan

```text
Maintenance perangkat selesai
        ↓
Scan QR pakai kamera HP
        ↓
URL QR terbuka
        ↓
User sudah login?
  ↓ Ya          ↓ Tidak
Auto POST       Login
  ↓              ↓
Sistem catat ← kembali ke URL QR
        ↓
Tanggal/jam otomatis
        ↓
Maintenance selesai
```

### Perlindungan scan ganda

Database memiliki UNIQUE KEY:

```text
(asset_id, maintenance_month, maintenance_year)
```

Artinya satu aset hanya dapat tercatat **satu kali per bulan**.

Scan kedua akan menampilkan:

```text
Sudah Maintenance
Tanggal: ...
Jam: ...
Teknisi: ...
```

tanpa membuat record baru.

---

## 8. Dashboard

Buka:

```text
/modules/qr_maintenance/dashboard.php
```

Menampilkan:

- total aset aktif;
- sudah maintenance;
- belum maintenance;
- persentase progress;
- jumlah temuan;
- aset yang belum maintenance;
- scan terbaru;
- filter bulan;
- filter tahun;
- filter cabang.

---

## 9. Temuan / Kerusakan

Setelah scan berhasil tersedia tombol:

```text
Ada Temuan / Kerusakan
```

Data yang disimpan:

- temuan;
- tindakan yang sudah dilakukan;
- tingkat Ringan / Sedang / Berat;
- status tindak lanjut.

Status maintenance berubah dari:

```text
Selesai
```

menjadi:

```text
Temuan
```

Maintenance tetap dianggap sudah dilakukan.

---

## 10. Export

Dashboard memiliki:

```text
Export CSV
```

File dapat langsung dibuka di Excel.

Kolom:

- tanggal;
- jam;
- kode inventaris;
- serial number;
- merk;
- model;
- pemilik;
- cabang;
- teknisi;
- status.

---

## 11. Menu yang Disarankan di Rekap IT

```text
DASHBOARD

ASET
├── Data Aset
├── Generate / Cetak QR

MAINTENANCE
├── Dashboard QR
├── Belum Maintenance
├── Riwayat
└── Temuan

PERBAIKAN
├── Proses
├── Selesai
└── Riwayat
```

Contoh link:

```php
<a href="/modules/qr_maintenance/dashboard.php">Maintenance QR</a>
<a href="/modules/qr_maintenance/qr_admin.php">QR Aset</a>
```

---

## 12. Catatan Keamanan

QR hanya berisi token acak dan URL.

Jangan masukkan ke QR:

- password;
- IP internal;
- username;
- informasi rahasia;
- data pribadi.

Pencatatan maintenance tetap mensyaratkan session login teknisi.

QR lama dapat dinonaktifkan dengan menu **Buat Ulang**. Setelah QR dibuat ulang, token lama tidak lagi berlaku.

---

## 13. Catatan QRCode.js

Halaman cetak QR menggunakan:

```text
QRCode.js 1.0.0 dari cdnjs
```

Jika komputer cetak tidak memiliki internet, download `qrcode.min.js` ke project secara lokal lalu ganti script pada `print_qr.php`.

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

Tidak perlu catat kertas dan tidak perlu input checklist untuk maintenance normal.
