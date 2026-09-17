# Panduan Lengkap Migrasi & Kustomisasi: Modul Cetak Kartu Inventaris (CR80) + Database Google Sheets Otomatis

Panduan ini disusun untuk memudahkan Anda memindahkan (*porting*) fitur **Cetak Kartu Inventaris & Manajemen Aset** berstandar kartu ATM (**CR80: 85.6mm x 54.0mm**), menyesuaikan desain fisik kartu maupun antarmuka website, serta menghubungkan database Google Spreadsheet agar bertambah dan tersinkronisasi secara otomatis tanpa perlu setup tabel manual.

---

## 📑 Daftar Isi
1. [Fitur Unggulan Modul](#1-fitur-unggulan-modul)
2. [Arsitektur & File yang Dibutuhkan](#2-arsitektur--file-yang-dibutuhkan)
3. [Setup Database Google Spreadsheet Otomatis (Apps Script)](#3-setup-database-google-spreadsheet-otomatis)
4. [Helper PHP & Model Data](#4-helper-php--model-data)
5. [Panduan Kustomisasi Desain](#5-panduan-kustomisasi-desain)
   - [A. Menyesuaikan Desain Kartu Fisik ATM (CR80)](#a-menyesuaikan-desain-kartu-fisik-atm-cr80)
   - [B. Menyesuaikan Tema Antarmuka Web (UI)](#b-menyesuaikan-tema-antarmuka-web-ui)
   - [C. Menyesuaikan Layout Cetak Kertas A4](#c-menyesuaikan-layout-cetak-kertas-a4)
6. [Panduan Integrasi ke Berbagai Framework](#6-panduan-integrasi-ke-berbagai-framework)
   - [Opsi 1: PHP Native / Website Sederhana](#opsi-1-php-native--website-sederhana)
   - [Opsi 2: Laravel (Blade & Controller)](#opsi-2-laravel-blade--controller)
   - [Opsi 3: CodeIgniter 3 / 4](#opsi-3-codeigniter-3--4)
7. [Tips Cetak Presisi & Troubleshooting](#7-tips-cetak-presisi--troubleshooting)

---

## 1. Fitur Unggulan Modul

Modul Cetak Kartu Inventaris ini memiliki kapabilitas kelas industri:

- **Ukuran Standar Kartu ATM (CR80)**: Berdimensi presisi **85.6mm x 54.0mm** yang cocok dicetak pada kertas PVC kartu ATM, stiker chromo/vinyl, ataupun kertas HVS/karton A4.
- **Layout Cetak Massal A4 Fleksibel**:
  - **8 Kartu / Lembar**: Format 2 x 4 (Portrait) – ideal dengan jarak potong longgar.
  - **10 Kartu / Lembar**: Format 2 x 5 (Portrait) – standar hemat kertas kartu nama.
  - **12 Kartu / Lembar**: Format 3 x 4 (Landscape) – kapasitas maksimal per lembar A4.
- **Generator QR Code Otomatis**: Menggunakan library JavaScript client-side (`qrcodejs`), QR Code digenerate secara instan dan dapat diarahkan ke teks barcode maupun tautan URL verifikasi aset online.
- **Live Preview Interaktif**: Sebelum mencetak atau mendownload, pengguna dapat melihat pratinjau kartu 1-per-1 langsung di layar monitor dengan skala kartu nyata.
- **Input Lokasi Cepat**: Opsi input lokasi aset secara fleksibel—bisa diset massal ("Terapkan ke Semua") atau diketik manual per kartu saat sesi cetak.
- **Ekspor Multi-Format**:
  - Cetak langsung via browser dialog (*print preview* / Simpan sebagai PDF).
  - Ekspor ke dokumen Microsoft Word (`.doc`) dengan tabel kartu siap cetak offline.
  - Ekspor data ke CSV / Excel.
- **Database Google Sheets Otomatis**: Menambahkan data baru langsung membuat tab sheet `inventaris_kartu` beserta kolom header-nya di Google Spreadsheet Anda tanpa perlu buat manual.

---

## 2. Arsitektur & File yang Dibutuhkan

Untuk memindahkan fitur ini ke website lain, berikut adalah daftar komponen yang dibutuhkan:

```text
📁 website-tujuan/
├── 📁 assets/
│   └── 🖼️ logo_kartu.png          <-- Logo instansi/perusahaan untuk kartu
├── 📁 config/
│   └── 📄 google_sheets.php        <-- Konfigurasi URL Google Apps Script
├── 📁 helpers/
│   └── 📄 GoogleSheetsBridge.php   <-- Helper komunikasi cURL ke Google Sheets
├── 📁 models/
│   └── 📄 InventarisKartu.php      <-- Model CRUD data kartu
├── 📁 views/ (atau root script)
│   └── 📄 print_inventory_card.php <-- File tampilan utama (UI, CSS, JS Cetak)
└── 📄 .env                         <-- Menyimpan URL Apps Script & kredensial
```

### Library Frontend yang Digunakan (via CDN):
- **Bootstrap 5.3**: `<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">`
- **Bootstrap Icons**: `<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">`
- **QRCode.js**: `<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>`
- **Google Fonts (Plus Jakarta Sans)**: Digunakan agar tipografi kartu terlihat modern dan tajam.

---

## 3. Setup Database Google Spreadsheet Otomatis

Agar database Google Spreadsheet Anda otomatis membuat sheet tab `inventaris_kartu` dan otomatis menambahkan baris baru saat Anda menambah data dari website, ikuti langkah mudah berikut:

### Langkah 1: Buat Spreadsheet Baru
1. Buka [Google Sheets](https://sheets.new/) di browser Anda.
2. Beri nama Spreadsheet, misalnya: `Database Inventaris & Kartu Aset`.
3. Anda **TIDAK PERLU** membuat kolom atau nama tab apa pun secara manual. Skrip di Langkah 2 akan membuatnya otomatis.

### Langkah 2: Pasang Google Apps Script (Bridge Otomatis)
1. Di Google Spreadsheet Anda, klik menu **Extensions (Ekstensi)** > **Apps Script**.
2. Hapus semua kode default yang ada di editor, lalu salin dan tempel kode berikut:

```javascript
/**
 * GOOGLE APPS SCRIPT - DATABASE OTOMATIS KARTU INVENTARIS & ASET
 * Mendukung auto-create tab sheet, auto-header kolom, insert, update, delete, dan read.
 */

// 1. Otorisasi Awal (Jalankan fungsi ini 1x pertama kali lalu klik Review Permissions)
function runAuthorization() {
  SpreadsheetApp.getActiveSpreadsheet();
  Logger.log("Otorisasi Google Sheets Berhasil!");
}

// 2. Fungsi Pembantu: Mengambil atau Otomatis Membuat Tab Sheet & Kolom Header
function getOrCreateSheet(sheetName, defaultHeaders) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = ss.getSheetByName(sheetName);
  if (!sheet) {
    sheet = ss.insertSheet(sheetName);
  }
  var dataRange = sheet.getDataRange();
  var values = dataRange.getValues();
  
  // Jika sheet baru atau baris pertama masih kosong, buat baris header otomatis
  if (values.length === 0 || values[0].length === 0 || (values.length === 1 && values[0][0] === "")) {
    sheet.clear();
    sheet.appendRow(defaultHeaders);
    // Format visual header (latar gelap, teks tebal putih)
    var headerRange = sheet.getRange(1, 1, 1, defaultHeaders.length);
    headerRange.setBackground("#003b73")
               .setFontColor("#ffffff")
               .setFontWeight("bold");
    sheet.setFrozenRows(1);
  }
  return sheet;
}

// 3. Menangani Permintaan HTTP GET (Membaca Data)
function doGet(e) {
  var action = (e && e.parameter && e.parameter.action) ? e.parameter.action : 'readAll';
  var tableName = (e && e.parameter && e.parameter.table) ? e.parameter.table : 'inventaris_kartu';
  
  if (action === 'readAll') {
    var ss = SpreadsheetApp.getActiveSpreadsheet();
    var sheet = ss.getSheetByName(tableName);
    if (!sheet) {
      return ContentService.createTextOutput(JSON.stringify([]))
        .setMimeType(ContentService.MimeType.JSON);
    }
    var data = sheet.getDataRange().getValues();
    if (data.length <= 1) {
      return ContentService.createTextOutput(JSON.stringify([]))
        .setMimeType(ContentService.MimeType.JSON);
    }
    var headers = data[0];
    var rows = [];
    for (var i = 1; i < data.length; i++) {
      var row = {};
      var isEmpty = true;
      for (var j = 0; j < headers.length; j++) {
        var val = data[i][j];
        if (val instanceof Date) {
          val = Utilities.formatDate(val, Session.getScriptTimeZone(), "yyyy-MM-dd");
        }
        row[headers[j]] = val;
        if (val !== "" && val !== null && val !== undefined) isEmpty = false;
      }
      if (!isEmpty) rows.push(row);
    }
    return ContentService.createTextOutput(JSON.stringify(rows))
      .setMimeType(ContentService.MimeType.JSON);
  }
  return ContentService.createTextOutput(JSON.stringify({ error: "Invalid action" }))
    .setMimeType(ContentService.MimeType.JSON);
}

// 4. Menangani Permintaan HTTP POST (Tambah, Edit, Hapus)
function doPost(e) {
  try {
    var postData = JSON.parse(e.postData.contents);
    var action = postData.action;
    var table = postData.table || 'inventaris_kartu';
    var data = postData.data || {};
    
    // Header default untuk tabel inventaris_kartu
    var defaultHeaders = ['id', 'nomor_rekening', 'nama_barang', 'tanggal_perolehan', 'lokasi', 'pengguna', 'barcode_data', 'created_at'];
    var sheet = getOrCreateSheet(table, defaultHeaders);
    
    // === AKSI: INSERT (TAMBAH DATA BARU) ===
    if (action === 'insert') {
      var values = sheet.getDataRange().getValues();
      var headers = values[0];
      var idIndex = headers.indexOf('id');
      var maxId = 0;
      for (var i = 1; i < values.length; i++) {
        var curId = parseInt(values[i][idIndex]);
        if (!isNaN(curId) && curId > maxId) maxId = curId;
      }
      var newId = (data.id && parseInt(data.id) > 0) ? parseInt(data.id) : (maxId + 1);
      data['id'] = newId;
      if (!data['created_at']) {
        data['created_at'] = Utilities.formatDate(new Date(), Session.getScriptTimeZone(), "yyyy-MM-dd HH:mm:ss");
      }
      var newRow = [];
      headers.forEach(function(h) {
        newRow.push(data[h] !== undefined ? data[h] : "");
      });
      sheet.appendRow(newRow);
      return ContentService.createTextOutput(JSON.stringify({ success: true, id: newId }))
        .setMimeType(ContentService.MimeType.JSON);
    }
    // === AKSI: UPDATE (PERBARUI DATA) ===
    else if (action === 'update') {
      var targetId = postData.id;
      var values = sheet.getDataRange().getValues();
      var headers = values[0];
      var idIndex = headers.indexOf('id');
      var rowIndex = -1;
      for (var i = 1; i < values.length; i++) {
        if (String(values[i][idIndex]) === String(targetId)) {
          rowIndex = i + 1;
          break;
        }
      }
      if (rowIndex === -1) {
        return ContentService.createTextOutput(JSON.stringify({ error: "ID tidak ditemukan" }))
          .setMimeType(ContentService.MimeType.JSON);
      }
      headers.forEach(function(h, colIdx) {
        if (h !== 'id' && data[h] !== undefined) {
          sheet.getRange(rowIndex, colIdx + 1).setValue(data[h]);
        }
      });
      return ContentService.createTextOutput(JSON.stringify({ success: true }))
        .setMimeType(ContentService.MimeType.JSON);
    }
    // === AKSI: DELETE (HAPUS DATA) ===
    else if (action === 'delete') {
      var idsToDelete = Array.isArray(postData.id) ? postData.id : [postData.id];
      var values = sheet.getDataRange().getValues();
      var headers = values[0];
      var idIndex = headers.indexOf('id');
      var deletedCount = 0;
      for (var i = values.length - 1; i >= 1; i--) {
        var rowId = String(values[i][idIndex]);
        if (idsToDelete.map(String).indexOf(rowId) !== -1) {
          sheet.deleteRow(i + 1);
          deletedCount++;
        }
      }
      return ContentService.createTextOutput(JSON.stringify({ success: true, deleted: deletedCount }))
        .setMimeType(ContentService.MimeType.JSON);
    }
    return ContentService.createTextOutput(JSON.stringify({ error: "Aksi tidak dikenal" }))
      .setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({ error: err.message }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}
```

### Langkah 3: Deploy sebagai Web App
1. Di editor Apps Script, klik tombol biru **Deploy** (kanan atas) > pilih **New deployment**.
2. Klik ikon gerigi (*Select type*) > pilih **Web app**.
3. Konfigurasi:
   - **Description**: `API Database Inventaris Kartu`
   - **Execute as**: `Me (email Anda)`
   - **Who has access**: `Anyone` *(Sangat penting: pilih Anyone agar server website dapat mengirim & mengambil data tanpa hambatan)*
4. Klik **Deploy**.
5. Jika muncul permintaan izin, klik **Authorize access** > pilih akun Google Anda > klik **Advanced** > klik **Go to Untitled project (unsafe)** > klik **Allow**.
6. Salin **Web app URL** yang muncul (format: `https://script.google.com/macros/s/AKfycb.../exec`). Simpan URL ini ke konfigurasi Anda!

---

## 4. Helper PHP & Model Data

### File: `api/helpers/GoogleSheetsBridge.php`
```php
<?php
class GoogleSheetsBridge {
    private static $webAppUrl = '';

    public static function setUrl(string $url): void {
        self::$webAppUrl = trim($url);
    }

    public static function getUrl(): string {
        if (!empty(self::$webAppUrl)) {
            return self::$webAppUrl;
        }
        return getenv('GOOGLE_SHEETS_WEBAPP_URL') ?: ($_SESSION['google_sheets_webapp_url'] ?? '');
    }

    public static function post(string $action, array $data = [], $id = null, string $table = 'inventaris_kartu'): array {
        $url = self::getUrl();
        if (empty($url)) {
            return ['error' => 'URL Google Apps Script belum dikonfigurasi.'];
        }

        $payload = json_encode([
            'action' => $action,
            'table'  => $table,
            'id'     => $id,
            'data'   => $data
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['error' => $curlError];
        }

        return json_decode($response, true) ?: ['error' => 'Respon tidak valid'];
    }

    public static function get(string $table = 'inventaris_kartu'): array {
        $url = self::getUrl();
        if (empty($url)) {
            return [];
        }

        $requestUrl = $url . '?action=readAll&table=' . urlencode($table);
        $ch = curl_init($requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?: [];
    }
}
```

---

## 5. Panduan Kustomisasi Desain

### A. Menyesuaikan Desain Kartu Fisik ATM (CR80)
Desain fisik kartu dikendalikan oleh class CSS khusus di dalam file `print_inventory_card.php`. Anda dapat menyesuaikannya agar selaras dengan Corporate Brand Identity:

#### 1. Mengganti Logo & Nama Instansi
```html
<!-- Header Logo Box -->
<div class="header-logo-box">
  <img src="<?= app_logo_url() ?>" alt="Logo Bank">
</div>
<!-- Judul Utama & Sub-Title -->
<div class="header-title-box">
  <div class="header-main-title">PT BPR MITRATAMA ARTHABUANA</div>
  <div class="header-sub-sec">
    <div class="green-banner-wrapper">
      <div class="teal-stripe"></div>
      <div class="green-banner">KARTU INVENTARIS ASET</div>
    </div>
  </div>
</div>
```

#### 2. Mengubah Skema Warna Kartu
```css
/* 1. Header Utama & Field Label (Navy Bank Mitra #003b73) */
.header-main-title, .field-icon-box {
  background-color: #003b73 !important;
}
.field-lbl {
  color: #003b73 !important;
}

/* 2. Banner Diagonal & Garis Pemisah (Emerald Green #10b981 / #7ac142) */
.green-banner {
  background: #7ac142 !important;
}
.field-sep, .card-bottom-sec {
  border-color: #7ac142 !important;
}

/* 3. Garis Aksen Miring (Teal #009ca6) */
.teal-stripe {
  background-color: #009ca6 !important;
}
```

#### 3. Mengatur Konten QR Code
Secara default, QR Code menghasilkan tautan verifikasi token pemeliharaan aset sistem:
```javascript
// Membuka scan kontrol maintenance aset saat di-scan kamera HP
const verificationUrl = `${window.location.origin}/scan.php?t=${encodeURIComponent(item.token)}`;
const qrBase64 = await generateQrBase64(verificationUrl);
```

---

## 6. Panduan Integrasi ke Berbagai Framework

### Opsi 1: PHP Native / Website Sederhana
1. Buat folder `helpers/` dan letakkan `GoogleSheetsBridge.php`.
2. Buat tabel database (jika menggunakan MySQL):
   ```sql
   CREATE TABLE IF NOT EXISTS inventaris_kartu (
       id INT AUTO_INCREMENT PRIMARY KEY,
       nomor_rekening VARCHAR(100) NOT NULL,
       nama_barang VARCHAR(255) NOT NULL,
       tanggal_perolehan DATE NOT NULL,
       barcode_data TEXT NOT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   ```
3. Panggil halaman menggunakan routing atau link modul:
   ```php
   // index.php
   include 'views/print_inventory_card.php';
   ```

### Opsi 2: Laravel (Blade & Controller)
1. Salin `GoogleSheetsBridge.php` ke `app/Services/GoogleSheetsBridge.php`.
2. Buat Controller:
   ```bash
   php artisan make:controller KartuInventarisController
   ```
3. Panggil data aset dan render view Blade:
   ```php
   public function index() {
       $items = Asset::orderBy('id', 'desc')->get();
       return view('kartu.inventory_card', compact('items'));
   }
   ```

### Opsi 3: CodeIgniter 3 / 4
1. Letakkan `GoogleSheetsBridge.php` di `app/Libraries/` atau `app/Helpers/`.
2. Di Controller:
   ```php
   public function cetak_kartu() {
       $data['items'] = $this->assetModel->findAll();
       return view('cetak_kartu', $data);
   }
   ```

---

## 7. Tips Cetak Presisi & Troubleshooting

### Tips Saat Mencetak di Browser (Chrome / Edge):
1. **Centang Opsi "Background Graphics" (Grafik Latar Belakang)**:
   - Pada dialog print browser, klik **More settings (Setelan lainnya)**.
   - Pastikan kotak **"Background graphics"** dicentang agar warna latar kartu, gradien, dan logo tercetak tajam.
2. **Atur Margin ke "None"**:
   - Pada pilihan **Margins**, pilih **None** agar tata letak grid A4 presisi sesuai koordinat milimeter (mm).
3. **Simpan sebagai PDF Berkualitas Tinggi**:
   - Pada pilihan **Destination (Tujuan)**, pilih **"Save as PDF" / "Simpan sebagai PDF"** untuk menghasilkan dokumen PDF vektor siap cetak.

### Troubleshooting:
- **Masalah: QR Code tidak muncul.**
  *Solusi*: Pastikan koneksi internet aktif untuk memuat library `qrcode.min.js` dari CDN atau pastikan file JS termuat lengkap.
- **Masalah: Data baru tidak masuk ke Google Sheet.**
  *Solusi*: Periksa kembali deployment Google Apps Script Anda. Pastikan opsi *Who has access* dipilih **"Anyone"**, dan jalankan fungsi `runAuthorization()` sekali untuk memberikan izin akses spreadsheet.
- **Masalah: Logo perusahaan pecah atau tidak muncul saat dicetak.**
  *Solusi*: Gunakan format gambar PNG transparan beresolusi minimal 300 DPI dengan dimensi proporsional.

---
*Dibuat untuk sistem manajemen pemeliharaan aset PT BPR MITRATAMA ARTHABUANA.*
