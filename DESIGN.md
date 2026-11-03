# DESIGN DIRECTION — PT BPR MITRATAMA ARTHABUANA
## IT Operations & Paspor Aset Inventaris Portal

> Dokumen ini adalah acuan arah visual dan identitas (*design direction*) resmi untuk seluruh antarmuka web sistem operasi IT dan kartu inventaris PT BPR Mitratama Arthabuana. Digunakan bersamaan dengan aturan filter **Anti-Slop** (`.agents/rules/antislop.md`) agar seluruh antarmuka terasa dirancang oleh desainer profesional, berkarakter perbankan tinggi (*high-trust banking*), bersih, dan tidak menghasilkan "AI Slop".

---

### 1. Karakter & Prinsip Desain
- **Karakter Utama**: Berwibawa (*Institutional*), Tenang (*Calm & Executive*), Fungsional (*Utilitarian*), dan Presisi (*Accurate*).
- **Bebas AI Slop**: 
  - Tidak ada gradien ungu-kebiruan murahan (*generic purple/blue AI gradient*).
  - Tidak ada icon gimmick AI seperti tongkat sihir (`bi-magic`) atau bintang berkilau (`bi-stars`).
  - Tidak ada warna kuning neon menyilaukan mata (*eye-searing yellow*).
  - Tidak ada teks omong kosong AI (*AI hype buzzwords*), tanpa em dash (`—`), dan tanpa klaim statistik fiktif.
  - Setiap tombol dan tautan wajib berfungsi nyata (*100% functional*), tidak ada elemen hiasan yang mati (*dead controls*).

---

### 2. Palet Warna Resmi (Kombai Teal & Banking Slate)

| Peran | Hex Code | Penggunaan |
|:---|:---|:---|
| **Primary Brand Teal** | `#0E5C58` | Header bar, tombol utama, icon penanda prioritas, border aksen aktif. |
| **Primary Dark Teal** | `#094340` | Hover state tombol utama, header border bawah, teks kontras tinggi. |
| **Teal Light Accent** | `#E6F4F2` / `#D9F1EF` | Background badge data master, highlight pill aktif, copy button background. |
| **Teal Border Accent**| `#A7E2DC` | Border pada badge teal, pemisah elemen data penting. |
| **Background Aplikasi**| `#F4F6F9` | Latar belakang seluruh halaman, memberikan kontras lembut pada kartu putih. |
| **Surface Card** | `#FFFFFF` | Latar belakang kartu data, container galeri foto, tabel informasi. |
| **Secondary Surface** | `#F8FAFC` | Strip tabel zebra, container sampah foto, area placeholder. |
| **Dark Neutral Text** | `#1B2A3A` | Judul, nama perangkat, nilai kode inventaris, teks utama (kontras tinggi WCAG AA). |
| **Secondary Muted Text**| `#53595F` | Label kolom, timestamp, petunjuk kecil, teks sekunder. |
| **Structural Border** | `#D1D5D8` / `#E2E8F0` | Garis tepi kartu, pembatas tabel, input border. |
| **Status Hijau (Aktif)**| `#10B981` / `#059669` | Pulse dot status aktif, badge terverifikasi, tombol WhatsApp resmi. |
| **Status Bahaya (Hapus)**| `#DC2626` | Tombol hapus foto, peringatan konfirmasi pemindahan ke folder sampah. |
| **Status Netral** | `#F1F5F9` | Badge jenis inventaris, ruangan, golongan (pengganti warna kuning). |

---

### 3. Tipografi
- **Antarmuka Utama (Sans-serif)**: `'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif`
  - Bobot: `400` (Regular), `500` (Medium), `600` (Semi-bold), `700` (Bold).
  - Memberikan keterbacaan modern, bersih, dan formal.
- **Data Teknis & Finansial (Monospace)**: `'JetBrains Mono', monospace`
  - Digunakan untuk: Kode Inventaris (`01.5.00496`), Nomor Seri (`01.33.02.261026.535`), Alamat IP (`192.168.x.x`), token QR, dan tanggal perolehan.

---

### 4. Komposisi & Tata Letak
- **Struktur Kartu Berbobot Nyata**:
  - Border radius seragam: `14px` hingga `16px` untuk kartu utama, `10px` hingga `12px` untuk elemen internal.
  - Bayangan lembut terarah: `box-shadow: 0 2px 10px rgba(14, 92, 88, 0.06);` (tidak mengapung berlebihan).
- **Responsif Ponsel (Mobile-First)**:
  - Lebar tap target minimum `44px` hingga `50px` pada tombol ponsel.
  - Tidak ada duplikasi judul barang antara header atas dan konten bawah pada tampilan ponsel.
  - Tidak ada teks yang terpotong pada kotak input atau URL tautan stiker.
  - Tabel core banking otomatis beralih ke format kartu vertikal (*stacked rows*) di layar kecil sehingga data tidak gepeng.

---

### 5. Komunikasi & Copywriting
- Menggunakan Bahasa Indonesia formal perbankan yang lugas dan manusiawi.
- Contoh label yang disetujui:
  - *Data Master Inventaris*
  - *Nomor Seri Inventaris*
  - *Ruangan (Lokasi Penempatan)*
  - *Unduh Kartu (PNG)*
  - *Kirim WA*
  - *Scan Wajah & Simpan*
- **Larangan Khusus**:
  - Dilarang keras menggunakan karakter em dash (`—`). Gunakan tanda strip biasa (`-`), titik dua (`:`), atau bullet (`•`).
  - Dilarang keras menyebut atau mencantumkan istilah "POJK" atau "OJK" dalam teks antarmuka maupun dokumentasi sistem.
