# Kebijakan Keamanan Siber (Security Policy)
**PT BPR Mitratama Arthabuana &bull; Sistem Pemeliharaan QR IT**

Sistem ini mematuhi regulasi perbankan **OJK POJK No. 75/POJK.03/2016** tentang Penerapan Manajemen Risiko dalam Penggunaan Teknologi Informasi oleh Bank Perkreditan Rakyat dan standar keamanan informasi **ISO/IEC 27001**.

---

## 1. Pelaporan Kerentanan Keamanan (Responsible Disclosure)

Jika Anda menemukan potensi kerentanan keamanan (*vulnerability*), kebocoran data, atau ancaman siber pada sistem atau repositori ini:

> **PERINGATAN KERAS:** Jangan membuat laporan kerentanan melalui Public GitHub Issue atau Pull Request terbuka.

Silakan laporkan temuan Anda secara rahasia dan aman melalui:
- **Email Tim Keamanan IT**: `it@bankmitra.co.id`
- **Subjek Email**: `[SECURITY VULNERABILITY] - Deskripsi Singkat Kerentanan`
- **Private Vulnerability Reporting**: Gunakan fitur **Security &rarr; Advisories &rarr; Report a vulnerability** di repositori GitHub ini.

Mohon sertakan rincian berikut:
1. Deskripsi kerentanan dan potensi dampak bisnis/risiko.
2. Langkah-langkah reproduksi langkah-demi-langkah (*Proof of Concept*).
3. Komponen atau berkas kode yang terdampak.
4. Saran atau rekomendasi mitigasi (bila ada).

Tim IT Security Bank Mitra akan menanggapi laporan maksimal dalam **1x24 jam kerja** dan melakukan audit mitigasi sesuai SLA insiden keamanan perbankan.

---

## 2. Batasan Lingkup Keamanan (Scope & Guardrails)

Dalam melakukan pengetesan keamanan, Anda **TIDAK DIPERKENANKAN**:
- Mengakses, menyalin, mengubah, atau menghancurkan data nasabah, aset fisik bank, maupun rekaman biometrik riil.
- Menjalankan serangan *Denial of Service (DoS/DDoS)* terhadap infrastruktur bank.
- Melakukan rekayasa sosial (*social engineering*) atau *phishing* terhadap staf bank.
- Membocorkan informasi atau *exploit code* ke ranah publik sebelum perbaikan dirilis resmi oleh Bank Mitra.

---

## 3. Standar Sanitasi & Kerahasiaan Repositori

1. **Zero Secret Policy**: Dilarang melakukan commit kredensial database riil, private key, token API, dan password ke repositori.
2. **Data Sanitization**: Berkas migrasi database di repositori hanya boleh berisi skema DDL atau data dummy. Cadangan database riil (`*.sql`, audit log, foto biometrik) tidak boleh dilacak oleh Git.
3. **Audit Trail**: Seluruh upaya akses tidak sah akan dicatat pada Audit Trail Terpusat yang mencakup IP Address, Timestamp, dan User Agent.
