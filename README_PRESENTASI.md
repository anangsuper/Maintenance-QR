# 🖥️ SISTEM QR MAINTENANCE IT
## Solusi Pemeliharaan Perangkat IT Berbasis QR Code & Biometrik Wajah
> **Panduan & Materi Presentasi Eksekutif / Manajemen / Stakeholder**

---

## 🎯 Ringkasan Eksekutif (*Executive Summary*)

**QR Maintenance IT** adalah platform manajemen inventaris dan pemeliharaan berkala (*preventive maintenance*) perangkat komputer yang dirancang untuk menggantikan proses manual berbasis kertas menjadi sistem digital yang **cepat, akuntabel, dan transparan**.

Sistem ini menggabungkan pemindaian **QR Code pada bodi perangkat** dengan **Verifikasi Biometrik Wajah (Face Recognition)** pada smartphone teknisi, sehingga setiap pemeliharaan terbukti dilakukan di tempat secara valid dan tidak dapat dimanipulasi (*anti-fraud*).

---

## 🛑 Masalah Sebelum Ada Sistem (*The Pain Points*)

Sebelum sistem ini diterapkan, pemeliharaan perangkat IT di kantor menghadapi berbagai kendala:

1. **Kartu Kontrol Kertas Rentan Rusak & Hilang**:
   - Kartu gantung yang ditempel pada bodi komputer sering sobek, kotor, terkena cairan, atau hilang.
2. **Rawan Formalitas & "Titip Paraf"**:
   - Sulit memastikan apakah teknisi benar-benar datang memeriksa unit di meja pengguna atau hanya menandatangani kartu sekaligus di akhir bulan.
3. **Data Rekap Lambat & Tersebar**:
   - Pimpinan dan Auditor (SKAI) harus menunggu berhari-hari mengumpulkan rekapan kartu manual dari berbagai divisi dan cabang.
4. **Temuan Kerusakan Lambat Ditindaklanjuti**:
   - Kerusakan kecil (seperti kipas berisik, suhu CPU panas, penyimpanan penuh) sering tidak terdokumentasi sampai akhirnya perangkat mati total (*downtime* operasional).

---

## 💡 Solusi yang Dihadirkan (*Our Solution*)

| Fitur Solusi | Manfaat Nyata bagi Manajemen |
|---|---|
| **🏷️ Stiker QR Unik per Unit** | Identifikasi instan: scan 1 detik langsung menampilkan spesifikasi, pengguna, IP address, dan riwayat pemeliharaan. |
| **📸 Verifikasi Biometrik Wajah** | Memastikan teknisi yang bertugas benar-benar hadir secara fisik di lokasi perangkat saat menyelesaikan perawatan atau perbaikan. |
| **📋 9 Standar Checklist IT** | Standarisasi kualitas perawatan perangkat kantor (fisik, hardware, update OS, antivirus, dan backup data). |
| **🛠️ Pelacakan Temuan Kerusakan** | Kerusakan tercatat dengan status *Pending*, dan otomatis kembali *Normal* setelah teknisi melakukan perbaikan dan verifikasi wajah. |
| **📊 Dashboard Audit Real-Time** | Manajemen dan Auditor dapat memantau persentase kepatuhan pemeliharaan seluruh cabang secara *live* detik itu juga. |
| **🖨️ Cetak Kartu & Laporan Resmi** | Format cetak kartu kontrol 12 bulan (dengan nama panggilan paraf) dan laporan rekap resmi bertanda tangan. |

---

## 🔄 Alur Kerja Sistem dalam 4 Langkah Mudah (*How It Works*)

```
[ 1. Registrasi & Tempel QR ]
       ⬇
[ 2. Teknisi Scan QR di Komputer Pengguna ]
       ⬇
[ 3. Checklist 9 Butir + Verifikasi Wajah ]
       ⬇
[ 4. Data Otomatis Masuk Kartu Kontrol 12 Bulan & Dashboard ]
```

### 1. Registrasi & Cetak Stiker
- Setiap komputer didaftarkan lengkap dengan data: **Nama Pengguna**, **Divisi**, **IP Address**, dan **Kantor Cabang**.
- Sistem menghasilkan stiker QR profesional yang ditempelkan di casing CPU / bodi laptop.

### 2. Kunjungan Teknisi di Lapangan
- Teknisi mendatangi meja kerja pengguna komputer sesuai jadwal rutin.
- Teknisi membuka kamera smartphone dan memindai stiker QR komputer.
- Layar smartphone langsung menampilkan status unit dan kartu kontrol 12 bulan.

### 3. Pemeriksaan 9 Standar & Validasi Wajah
- Teknisi melakukan pembersihan fisik dan pengujian sistem sesuai 9 checklist standar.
- Kamera smartphone memindai wajah teknisi untuk memastikan keabsahan petugas.
- Jika ada kerusakan suku cadang, teknisi mencatat temuan kerusakan untuk ditindaklanjuti.

### 4. Pelaporan Otomatis & Terpusat
- Seketika log pemeliharaan masuk ke sistem:
  - Kolom **PARAF** kartu kontrol 12 bulan otomatis terisi **Nama Panggilan Teknisi**.
  - Dashboard manajemen otomatis bertambah persentasenya.
  - Jika ada temuan kerusakan, unit ditandai hingga teknisi menuntaskan perbaikannya.

---

## 📋 9 Standar Checklist Pemeliharaan IT

Standar operasional prosedur (SOP) pengecekan yang diwajibkan sistem:

1. 🧹 **Pembersihan Fisik**: Pembersihan debu pada casing, motherboard, ventilasi, dan monitor.
2. 💨 **Pemeriksaan Sirkulasi Udara (Airflow)**: Pengecekan kipas prosesor & power supply agar berputar lancar.
3. 🌡️ **Suhu CPU & Thermal Paste**: Pengukuran suhu prosesor dan penggantian pasta pendingin jika kering.
4. 💾 **Kesehatan Media Penyimpanan**: Pemeriksaan status S.M.A.R.T SSD / Harddisk untuk mencegah kehilangan data mendadak.
5. 🛡️ **Update Sistem Operasi**: Verifikasi patch keamanan Windows/OS terbaru terpasang.
6. 🦠 **Antivirus & Pemindaian Malware**: Update database antivirus dan pengecekan ancaman keamanan.
7. 🗑️ **Pembersihan File Sampah**: Penghapusan file cache, temporary files, dan sisa instalasi aplikasi.
8. ⚡ **Optimasi Startup & Memori (RAM)**: Penonaktifan program yang memperlambat kinerja booting.
9. 📦 **Verifikasi Cadangan Data (Backup)**: Memastikan data penting pengguna sudah tersimpan di server/cloud.

---

## 👥 Struktur Hak Akses (Multi-Role)

Sistem membagi akses secara tegas sesuai tanggung jawab organisasi:

- **👑 Administrator (IT Management)**:
  - Memiliki kontrol penuh atas data aset, kantor cabang, divisi, dan akun pengguna.
  - Memverifikasi sampel biometrik wajah teknisi baru.
  - Menetapkan nama panggilan teknisi untuk paraf kartu kontrol.

- **🛠️ Teknisi IT (Lapangan)**:
  - Memindai QR code perangkat di meja pengguna.
  - Mengisi formulir checklist 9 butir pemeliharaan.
  - Mencatat kerusakan dan menyelesaikan tindak lanjut perbaikan dengan verifikasi wajah.

- **👁️ Auditor / SKAI (Satuan Pengawasan Internal)**:
  - Akses pemantauan independen (*read-only*).
  - Memantau grafik persentase kepatuhan pemeliharaan seluruh kantor cabang.
  - Meninjau histori perbaikan dan mengunduh laporan resmi untuk bukti audit regulasi.

---

## 💎 Nilai Tambah & Manfaat Bisnis (*Business Value & ROI*)

1. **Zero Paper & Biaya Cetak Rendah (*Paperless*)**:
   - Menghilangkan tumpukan formulir kertas yang membebani arsip gudang.
2. **Mengurangi *Downtime* Komputer Kantor**:
   - Masalah komponen (seperti kipas macet atau harddisk melemah) terdeteksi sebelum komputer mati total, mencegah terhentinya operasional kerja karyawan.
3. **Akuntabilitas Mutlak (*Audit Trail & Anti-Fraud*)**:
   - Setiap entri pemeliharaan mencatat jam pasti, tanggal, lokasi cabang, checklist spesifik, serta wajah teknisi pemeriksa.
4. **Efisiensi Anggaran Pengadaan Perangkat**:
   - Perangkat IT yang dirawat rutin terbukti memiliki masa pakai (*lifespan*) 30%–50% lebih panjang.
5. **Fleksibilitas Infrastruktur (Cloud Serverless / On-Premise)**:
   - Dapat dioperasikan tanpa biaya server database mahal menggunakan Google Cloud Sheets API v4 atau menggunakan server database MySQL internal perusahaan.

---

## 🎬 Skenario Demonstrasi Saat Presentasi

Bila Anda mendemonstrasikan sistem ini di hadapan audiens:

### Skenario 1: Pemeliharaan Rutin Normal
1. Tunjukkan stiker QR pada laptop/komputer.
2. Pindai stiker dengan kamera HP -> Halaman scan langsung terbuka.
3. Tunjukkan pratinjau **Kartu Kontrol 12 Bulan** yang sudah ada paraf bulan-bulan sebelumnya.
4. Centang 9 checklist -> Tekan **Selesaikan**.
5. Tunjukkan bahwa baris bulan berjalan langsung terisi tanggal dan **Paraf Nama Panggilan Teknisi**.

### Skenario 2: Ditemukan Masalah & Tindak Lanjut
1. Buka scan pada unit yang mengalami kerusakan (misal: *Fan CPU Macet*).
2. Tulis catatan temuan masalah -> Simpan.
3. Tunjukkan bahwa sistem menandai komputer dengan status **"Ada Masalah / Perlu Tindak Lanjut"**.
4. Klik tombol **"TINDAK LANJUTI / SELESAIKAN"**.
5. Lakukan verifikasi wajah teknisi via kamera -> Masukkan catatan perbaikan -> Submit.
6. Tunjukkan bahwa status pemeliharaan langsung **kembali Normal** dan siap digunakan pengguna.

---

## 📌 Kesimpulan

Aplikasi **QR Maintenance IT** bukan sekadar alat pencatat, melainkan instrumen tata kelola aset IT (*IT Governance*) modern yang menjamin transparansi kerja teknisi, memperpanjang usia pakai aset perusahaan, dan memudahkan pengawasan audit secara *real-time*.
