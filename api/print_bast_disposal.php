<?php
/**
 * Dokumen Resmi Berita Acara Penghapusan & Penjualan Aset Inventaris
 * PT BPR Mitratama Arthabuana
 * Standar Template Resmi Bank Mitra
 */

require __DIR__ . '/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$disposal = get_disposal_by_id($id);

if (!$disposal) {
    http_response_code(404);
    die('Dokumen Berita Acara Penghapusan Aset tidak ditemukan.');
}

$dateTs = strtotime($disposal['tanggal_disposal'] ?? 'now');
$dayNames = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$hariFormat = $dayNames[date('l', $dateTs)] ?? date('l', $dateTs);
$tglFormat = date('j', $dateTs) . ' ' . $monthNames[(int)date('n', $dateTs)] . ' ' . date('Y', $dateTs);

$nomorBa = e($disposal['nomor_ba']);
$jenisTindakan = e($disposal['jenis_tindakan']);
$isPenjualan = str_contains($disposal['jenis_tindakan'], 'Penjualan') || str_contains($disposal['jenis_tindakan'], 'Lelang');
$isHibah = str_contains($disposal['jenis_tindakan'], 'Hibah') || str_contains($disposal['jenis_tindakan'], 'Donasi');

$namaAset = e($disposal['nama_aset']);
$kodeAset = e($disposal['kode_aset']);
$merkModel = e($disposal['merk_model'] ?: '-');
$serialNumber = e($disposal['serial_number'] ?: '-');
$cabang = e($disposal['cabang']);
$lokasiRuangan = e($disposal['lokasi_ruangan'] ?: '-');
$penggunaTerakhir = e($disposal['pengguna_terakhir'] ?: '-');
$kondisiTerakhir = e($disposal['kondisi_terakhir']);
$alasan = nl2br(e($disposal['alasan_penghapusan']));

$nilaiPenjualan = (float)($disposal['nilai_realisasi_penjualan'] ?? 0);
$terbilangPenjualan = e($disposal['terbilang_penjualan']);
if ($nilaiPenjualan > 0 && empty($terbilangPenjualan) && function_exists('terbilang_rupiah')) {
    $terbilangPenjualan = '://' . terbilang_rupiah($nilaiPenjualan) . '://';
}
$pembeliPenerima = e($disposal['pembeli_penerima'] ?: '-');
$metodeBayar = e($disposal['metode_pembayaran'] ?: 'Kas Masuk Operasional');

$petugasNama = e($disposal['petugas_nama']);
$petugasJabatan = e($disposal['petugas_jabatan'] ?: 'Staf Bagian Umum');
$namaMengetahui = e($disposal['nama_mengetahui'] ?: 'Defie Damayanti');
$jabatanMengetahui = e($disposal['jabatan_mengetahui'] ?: 'Kabag. Umum & SDM');
$namaMenyetujui = e($disposal['nama_menyetujui'] ?: 'Kahar Muzaqir, SH');
$jabatanMenyetujui = e($disposal['jabatan_menyetujui'] ?: 'Direktur Operasional');

$logoUrl = module_url('bank_mitra_memo_logo.png');
$footerUrl = module_url('bank_mitra_memo_footer.png');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>BA Penghapusan Aset - <?= $nomorBa ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
@page {
  size: A4 portrait;
  margin: 0;
}

body {
  background: #E2E8F0;
  font-family: "Times New Roman", Times, Georgia, serif;
  color: #000000;
  padding: 30px 10px;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}

.doc-sheet {
  max-width: 210mm;
  min-height: 297mm;
  margin: 0 auto;
  background: #FFFFFF;
  padding: 14mm 20mm 32mm 20mm;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
  border-radius: 4px;
  position: relative;
  box-sizing: border-box;
  overflow: hidden;
}

.doc-top-logo {
  width: 105px;
  max-width: 105px;
  height: auto;
  object-fit: contain;
  display: block;
  margin-bottom: 12px;
}

.doc-title-block {
  text-align: center;
  margin-bottom: 16px;
  border-bottom: 2px solid #000000;
  padding-bottom: 8px;
}

.doc-heading {
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  margin-bottom: 2px;
}

.doc-subheading {
  font-size: 0.95rem;
  font-family: "Times New Roman", Times, serif;
  font-weight: 600;
  letter-spacing: 0.02em;
}

.doc-intro {
  font-size: 0.96rem;
  line-height: 1.5;
  text-align: justify;
  margin-bottom: 14px;
}

.table-detail {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 14px;
  font-size: 0.92rem;
}

.table-detail th, .table-detail td {
  border: 1px solid #000000;
  padding: 5px 8px;
  vertical-align: top;
}

.table-detail th {
  background-color: #F1F5F9;
  font-weight: 700;
}

.doc-clause {
  font-size: 0.94rem;
  line-height: 1.5;
  text-align: justify;
  margin-bottom: 14px;
}

.doc-clause ol {
  margin-bottom: 0;
  padding-left: 20px;
}

.doc-clause li {
  margin-bottom: 4px;
}

.photo-gallery-box {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
  page-break-inside: avoid;
}

.photo-box-item {
  flex: 1;
  border: 1px dashed #94A3B8;
  padding: 6px;
  border-radius: 4px;
  text-align: center;
  background: #F8FAFC;
}

.photo-box-item img {
  max-height: 110px;
  max-width: 100%;
  object-fit: contain;
  display: block;
  margin: 0 auto;
  border: 1px solid #CBD5E1;
  border-radius: 3px;
}

.photo-box-item .photo-label {
  font-size: 0.76rem;
  font-weight: 600;
  color: #475569;
  margin-top: 4px;
  font-family: -apple-system, sans-serif;
}

.signature-row {
  margin-top: 24px;
  display: flex;
  justify-content: space-between;
  text-align: center;
  page-break-inside: avoid;
}

.sig-col {
  width: 32%;
  font-size: 0.94rem;
}

.sig-space {
  height: 58px;
}

.sig-name {
  font-weight: 700;
  text-decoration: underline;
  font-size: 0.98rem;
}

.sig-title {
  font-size: 0.86rem;
  color: #111111;
  margin-top: 1px;
}

.doc-page-footer {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: auto;
  max-height: 48mm;
  object-fit: cover;
  pointer-events: none;
}

.floating-action-bar {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 9999;
  display: flex;
  gap: 10px;
  font-family: "Plus Jakarta Sans", sans-serif;
}

@media print {
  body {
    background: #FFFFFF !important;
    padding: 0 !important;
    margin: 0 !important;
  }
  .doc-sheet {
    box-shadow: none !important;
    padding: 12mm 18mm 30mm 18mm !important;
    max-width: 100% !important;
    width: 100% !important;
    min-height: 297mm !important;
    height: 297mm !important;
    border-radius: 0 !important;
  }
  .doc-top-logo {
    width: 28mm !important;
    max-width: 28mm !important;
    margin-bottom: 3mm !important;
  }
  .doc-page-footer {
    position: fixed !important;
    bottom: 0 !important;
    left: 0 !important;
    width: 100% !important;
  }
  .floating-action-bar, .no-print {
    display: none !important;
  }
}
</style>
</head>
<body>

<!-- Floating Action Bar -->
<div class="floating-action-bar no-print">
  <?php if (!empty($disposal['dokumen_ba_disetujui'])): ?>
    <a href="<?= e(disposal_file_url($disposal['dokumen_ba_disetujui'])) ?>" target="_blank" class="btn btn-info text-dark fw-bold shadow-lg px-3 py-2">
      <i class="bi bi-file-earmark-check me-1"></i> Scan BA Disetujui
    </a>
  <?php endif; ?>
  <?php if (!empty($disposal['bukti_transaksi'])): ?>
    <a href="<?= e(disposal_file_url($disposal['bukti_transaksi'])) ?>" target="_blank" class="btn btn-warning text-dark fw-bold shadow-lg px-3 py-2">
      <i class="bi bi-receipt me-1"></i> Kwitansi / Bukti Bayar
    </a>
  <?php endif; ?>
  <button type="button" class="btn btn-primary fw-bold shadow-lg px-4 py-2" onclick="window.print()">
    <i class="bi bi-printer-fill me-1.5"></i> Cetak Berita Acara
  </button>
  <a href="<?= e(module_url('asset_disposal.php')) ?>" class="btn btn-dark fw-semibold shadow-lg px-3 py-2">
    <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
  </a>
</div>

<!-- Lembar Dokumen Resmi Berita Acara -->
<div class="doc-sheet">
  <!-- 1. Logo Resmi Bank Mitra di Sudut Kiri Atas -->
  <img src="<?= $logoUrl ?>" alt="Bank Mitra Logo" class="doc-top-logo">

  <!-- 2. Judul Dokumen & Nomor Berita Acara -->
  <div class="doc-title-block">
    <div class="doc-heading">BERITA ACARA PENGHAPUSAN DAN PENJUALAN ASET INVENTARIS</div>
    <div class="doc-subheading">Nomor: <?= $nomorBa ?></div>
  </div>

  <!-- 3. Paragraf Pembuka Legal Formal -->
  <div class="doc-intro">
    Pada hari ini <strong><?= $hariFormat ?></strong>, tanggal <strong><?= $tglFormat ?></strong>, bertempat di Kantor PT BPR Mitratama Arthabuana, telah dilakukan pemeriksaan fisik, verifikasi kelayakan teknis, dan penetapan status atas barang inventaris bank dengan perincian sebagai berikut:
  </div>

  <!-- 4. Tabel Rincian Barang yang Dihapuskan / Dijual -->
  <table class="table-detail">
    <tr>
      <th style="width: 26%;">Nomor Rekening / Kode Aset</th>
      <td style="width: 74%; font-family: monospace; font-weight: bold;"><?= $kodeAset ?></td>
    </tr>
    <tr>
      <th>Nama Barang / Inventaris</th>
      <td><strong><?= $namaAset ?></strong></td>
    </tr>
    <tr>
      <th>Merk / Tipe / Serial Number</th>
      <td><?= $merkModel ?> <?= ($serialNumber !== '-' ? ' | SN: ' . $serialNumber : '') ?></td>
    </tr>
    <tr>
      <th>Lokasi / Kantor Cabang Asal</th>
      <td><?= $cabang ?> <?= ($lokasiRuangan !== '-' ? ' (Ruangan: ' . $lokasiRuangan . ')' : '') ?></td>
    </tr>
    <tr>
      <th>Pengguna / Penanggung Jawab</th>
      <td><?= $penggunaTerakhir ?></td>
    </tr>
    <tr>
      <th>Kondisi Fisik Terakhir</th>
      <td><span style="color: #991B1B; font-weight: 700;"><?= $kondisiTerakhir ?></span></td>
    </tr>
    <tr>
      <th>Jenis Tindakan Keputusan</th>
      <td>
        <strong><?= $jenisTindakan ?></strong>
      </td>
    </tr>
    <?php if ($isPenjualan): ?>
    <tr style="background-color: #F0FDF4;">
      <th>Nilai Realisasi Penjualan</th>
      <td>
        <strong style="color: #166534; font-size: 1.02rem;">Rp. <?= number_format($nilaiPenjualan, 0, ',', '.') ?>,-</strong>
        <div style="font-size: 0.88rem; font-style: italic; margin-top: 2px;">Terbilang: <?= $terbilangPenjualan ?></div>
      </td>
    </tr>
    <tr style="background-color: #F0FDF4;">
      <th>Pihak Pembeli / Pemenang Lelang</th>
      <td><?= $pembeliPenerima ?> (Metode: <?= $metodeBayar ?>)</td>
    </tr>
    <?php elseif ($isHibah): ?>
    <tr style="background-color: #EFF6FF;">
      <th>Pihak Penerima Donasi / Hibah</th>
      <td><?= $pembeliPenerima ?></td>
    </tr>
    <?php endif; ?>
    <tr>
      <th>Alasan / Pertimbangan Teknis</th>
      <td><?= $alasan ?></td>
    </tr>
  </table>

  <!-- 5. Klausul Keputusan Penghapusan Aset -->
  <div class="doc-clause">
    <div style="font-weight: 700; margin-bottom: 3px;">Berdasarkan hasil pemeriksaan tersebut di atas, disepakati bahwa:</div>
    <ol>
      <li>Barang inventaris yang bersangkutan telah dinyatakan tidak memenuhi standar kelayakan operasional dan secara resmi <strong>ditarik serta dihapuskan dari Buku Register Aset Aktif PT BPR Mitratama Arthabuana</strong>.</li>
      <?php if ($isPenjualan): ?>
      <li>Penerimaan dana hasil penjualan barang inventaris sebesar <strong>Rp. <?= number_format($nilaiPenjualan, 0, ',', '.') ?>,-</strong> disetorkan secara penuh ke Kas / Rekening Operasional Bank Mitra sebagai penerimaan non-operasional lainnya.</li>
      <?php else: ?>
      <li>Fisik barang inventaris yang diafkirkan dimusnahkan / disingkirkan dari lingkungan kantor bank guna ketertiban dan kerapian fasilitas kerja.</li>
      <?php endif; ?>
      <li>Berita Acara ini dibuat dengan sebenarnya dalam rangkap secukupnya untuk dipergunakan sebagai dokumen pertanggungjawaban legal pembukuan dan kepatuhan audit intern (SKAI).</li>
    </ol>
  </div>

  <!-- 6. Lampiran Foto Fisik Barang & Kwitansi (Jika Ada) -->
  <?php if (!empty($disposal['foto_barang']) || !empty($disposal['bukti_transaksi'])): ?>
    <div class="photo-gallery-box">
      <?php if (!empty($disposal['foto_barang'])): ?>
        <div class="photo-box-item">
          <img src="<?= e(disposal_file_url($disposal['foto_barang'])) ?>" alt="Kondisi Fisik Barang">
          <div class="photo-label"><i class="bi bi-camera"></i> Dokumentasi Fisik Aset Terakhir</div>
        </div>
      <?php endif; ?>
      <?php if (!empty($disposal['bukti_transaksi'])): ?>
        <div class="photo-box-item">
          <img src="<?= e(disposal_file_url($disposal['bukti_transaksi'])) ?>" alt="Bukti Kwitansi / Transaksi">
          <div class="photo-label"><i class="bi bi-receipt"></i> Bukti Setor / Kwitansi Penjualan</div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- 7. Kolom Tanda Tangan 3 Pejabat Bank Mitra -->
  <div class="signature-row">
    <div class="sig-col">
      <div>Yang Memeriksa / Mengajukan,</div>
      <div class="sig-space"></div>
      <div class="sig-name"><?= $petugasNama ?></div>
      <div class="sig-title"><?= $petugasJabatan ?></div>
    </div>
    <div class="sig-col">
      <div>Mengetahui,</div>
      <div class="sig-space"></div>
      <div class="sig-name"><?= $namaMengetahui ?></div>
      <div class="sig-title"><?= $jabatanMengetahui ?></div>
    </div>
    <div class="sig-col">
      <div>Menyetujui,</div>
      <div class="sig-space"></div>
      <div class="sig-name"><?= $namaMenyetujui ?></div>
      <div class="sig-title"><?= $jabatanMenyetujui ?></div>
    </div>
  </div>

  <!-- 8. Footer Kop Surat Bank Mitra di Bawah Kertas -->
  <img src="<?= $footerUrl ?>" alt="Footer Kop Surat Bank Mitra" class="doc-page-footer">
</div>

</body>
</html>
