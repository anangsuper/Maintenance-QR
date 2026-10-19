<?php
/**
 * Dokumen Resmi Berita Acara Serah Terima (BAST) Pengadaan Barang
 * PT BPR Mitratama Arthabuana
 */

require __DIR__ . '/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$memo = get_memo_by_id($id);

if (!$memo) {
    http_response_code(404);
    die('Dokumen Memo Pengadaan tidak ditemukan.');
}

$dateBeli = !empty($memo['tanggal_beli']) ? $memo['tanggal_beli'] : $memo['tanggal_memo'];
$dateTs = strtotime($dateBeli);

$dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$hari = $dayNames[(int)date('w', $dateTs)];
$tgl = date('j', $dateTs);
$bln = $monthNames[(int)date('n', $dateTs)];
$thn = date('Y', $dateTs);

$nomorMemo = e($memo['nomor_memo']);
$bastNo = 'BAST-PENG/' . $nomorMemo;

$perihal = e($memo['perihal']);
$cabang = e($memo['cabang_nama']);
$isiMemo = e($memo['isi_memo']);
$catatanRealisasi = e($memo['catatan_realisasi'] ?: '-');

$biayaRealisasi = !empty($memo['biaya_realisasi']) ? (float)$memo['biaya_realisasi'] : (float)$memo['total_estimasi'];
$terbilangBiaya = terbilang_rupiah($biayaRealisasi);

$namaPengaju = e($memo['nama_pengaju']);
$jabatanPengaju = e($memo['jabatan_pengaju'] ?: 'Kepala Cabang');

$namaMengetahui = e($memo['nama_mengetahui'] ?: 'Defie Damayanti');
$jabatanMengetahui = e($memo['jabatan_mengetahui'] ?: 'Kabag. Umum & SDM');

$namaMenyetujui = e($memo['nama_menyetujui'] ?: 'Kahar Muzaqir, SH');
$jabatanMenyetujui = e($memo['jabatan_menyetujui'] ?: 'Direktur Operasional');

$hasNota = !empty($memo['bukti_beli_file']);
$hasFoto = !empty($memo['foto_barang_file']);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>BAST Pengadaan - <?= $bastNo ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
@page {
  size: A4 portrait;
  margin: 10mm 15mm 10mm 15mm;
}

body {
  background: #E2E8F0;
  font-family: "Times New Roman", Times, Georgia, serif;
  color: #000000;
  padding: 24px 10px;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}

.bast-sheet {
  max-width: 210mm;
  min-height: 297mm;
  margin: 0 auto;
  background: #FFFFFF;
  padding: 16mm 18mm 16mm 18mm;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
  border-radius: 4px;
  position: relative;
  box-sizing: border-box;
}

.bank-logo {
  max-height: 48px;
  width: auto;
  object-fit: contain;
}

.kop-title {
  font-size: 1.12rem;
  font-weight: 800;
  color: #003B73;
  letter-spacing: 0.04em;
  line-height: 1.2;
}

.kop-sub {
  font-size: 0.82rem;
  color: #334155;
  font-family: "Plus Jakarta Sans", -apple-system, sans-serif;
  margin-top: 1px;
}

.kop-address {
  font-size: 0.72rem;
  color: #64748B;
  font-family: "Plus Jakarta Sans", -apple-system, sans-serif;
}

.kop-double-line {
  border-top: 2.5px solid #003B73;
  border-bottom: 0.75px solid #003B73;
  height: 4px;
  margin: 8px 0 16px 0;
}

.doc-heading {
  font-size: 1.08rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  margin-bottom: 2px;
  text-align: center;
  text-decoration: underline;
}

.doc-subheading {
  font-size: 0.90rem;
  font-weight: 700;
  text-align: center;
  margin-bottom: 14px;
  font-family: "Times New Roman", Times, serif;
}

.text-content {
  font-size: 0.94rem;
  line-height: 1.45;
  text-align: justify;
}

.table-party {
  width: 100%;
  font-size: 0.92rem;
  line-height: 1.4;
  margin-bottom: 8px;
}

.table-party td {
  vertical-align: top;
  padding: 1.5px 3px;
}

.table-items {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.88rem;
  margin: 10px 0;
}

.table-items th, .table-items td {
  border: 1px solid #000000;
  padding: 5px 8px;
}

.table-items th {
  background-color: #F1F5F9;
  font-weight: 700;
  text-align: center;
}

.signature-table {
  width: 100%;
  margin-top: 18px;
  font-size: 0.90rem;
  border-collapse: collapse;
  page-break-inside: avoid;
  break-inside: avoid;
}

.signature-table td {
  text-align: center;
  vertical-align: top;
  padding: 4px 6px;
  width: 33.33%;
}

.sig-space {
  height: 58px;
}

.sig-name {
  font-weight: 700;
  text-decoration: underline;
  font-size: 0.94rem;
}

.sig-role {
  font-size: 0.82rem;
  color: #1E293B;
  margin-top: 1px;
}

.floating-action-bar {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 9999;
  display: flex;
  gap: 8px;
  font-family: "Plus Jakarta Sans", sans-serif;
}

@media print {
  body {
    background: #FFFFFF !important;
    padding: 0 !important;
    margin: 0 !important;
  }
  .no-print, .floating-action-bar {
    display: none !important;
  }
  .bast-sheet {
    box-shadow: none !important;
    margin: 0 !important;
    padding: 0 !important;
    border-radius: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
    min-height: auto !important;
  }
  .page-break {
    page-break-before: always !important;
    break-before: page !important;
    height: 0;
    margin: 0;
    padding: 0;
  }
  .sheet-page-2 {
    padding-top: 8mm !important;
  }
}
</style>
</head>
<body>

<!-- Floating Action Bar (Hidden when printed) -->
<div class="floating-action-bar no-print">
  <a href="<?= e(module_url('memo_pengajuan.php')) ?>" class="btn btn-secondary btn-sm fw-semibold shadow-lg px-3 py-2">
    <i class="bi bi-arrow-left me-1"></i> Kembali ke Memo
  </a>
  <a href="<?= e(module_url('print_memo.php', ['id' => $id])) ?>" target="_blank" class="btn btn-outline-dark bg-white btn-sm fw-semibold shadow-lg px-3 py-2">
    <i class="bi bi-file-earmark-text me-1"></i> Memo Pengajuan
  </a>
  <button type="button" class="btn btn-primary btn-sm fw-bold shadow-lg px-4 py-2" onclick="window.print()">
    <i class="bi bi-printer-fill me-1"></i> Cetak BAST (A4)
  </button>
</div>

<!-- Lembar Dokumen BAST Resmi (Halaman 1) -->
<div class="bast-sheet">
  <!-- Kop Surat Resmi Bank Mitra -->
  <div class="d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-3">
      <img src="<?= app_logo_url() ?>" alt="Bank Mitra Logo" class="bank-logo">
      <div>
        <div class="kop-title">PT BPR MITRATAMA ARTHABUANA</div>
        <div class="kop-sub">Kantor Pusat Operasional &middot; Bagian Umum &amp; Sarana Prasarana</div>
        <div class="kop-address">Jl. Kapten Piere Tendean No. 170, Banjarmasin &middot; Telp. (0511) 3271222</div>
      </div>
    </div>
    <div class="text-end font-monospace text-muted" style="font-size:0.72rem; line-height: 1.35;">
      FORMULIR: BAST-PENG-01<br>
      Tgl: <?= date('d/m/Y', $dateTs) ?>
    </div>
  </div>

  <div class="kop-double-line"></div>

  <!-- Judul Dokumen -->
  <div class="doc-heading">BERITA ACARA SERAH TERIMA PENGADAAN BARANG</div>
  <div class="doc-subheading">Nomor : <?= $bastNo ?></div>

  <div class="text-content mb-2">
    Pada hari ini, <strong><?= $hari ?></strong> tanggal <strong><?= $tgl ?></strong> bulan <strong><?= $bln ?></strong> tahun <strong><?= $thn ?></strong> (<?= date('d/m/Y', $dateTs) ?>), bertempat di Kantor <strong>PT BPR Mitratama Arthabuana</strong>, telah dilaksanakan serah terima dan pemeriksaan fisik barang pengadaan sarana/prasarana inventaris oleh dan antara:
  </div>

  <!-- Identitas Para Pihak -->
  <table class="table-party mb-2">
    <tr>
      <td style="width: 24px;"><strong>I.</strong></td>
      <td style="width: 130px;"><strong>Nama</strong></td>
      <td style="width: 10px;">:</td>
      <td><strong><?= $namaMengetahui ?></strong></td>
    </tr>
    <tr>
      <td></td>
      <td>Jabatan</td>
      <td>:</td>
      <td><?= $jabatanMengetahui ?></td>
    </tr>
    <tr>
      <td></td>
      <td>Unit Kerja</td>
      <td>:</td>
      <td>Bagian Umum &amp; SDM / Pengadaan Sarpras</td>
    </tr>
    <tr>
      <td></td>
      <td colspan="3" class="fst-italic text-secondary" style="font-size:0.86rem;">
        Selaku pihak yang menyerahkan barang hasil pengadaan, selanjutnya disebut <strong>PIHAK PERTAMA</strong>.
      </td>
    </tr>
  </table>

  <table class="table-party mb-2">
    <tr>
      <td style="width: 24px;"><strong>II.</strong></td>
      <td style="width: 130px;"><strong>Nama</strong></td>
      <td style="width: 10px;">:</td>
      <td><strong><?= $namaPengaju ?></strong></td>
    </tr>
    <tr>
      <td></td>
      <td>Jabatan</td>
      <td>:</td>
      <td><?= $jabatanPengaju ?></td>
    </tr>
    <tr>
      <td></td>
      <td>Unit / Cabang</td>
      <td>:</td>
      <td><?= $cabang ?></td>
    </tr>
    <tr>
      <td></td>
      <td colspan="3" class="fst-italic text-secondary" style="font-size:0.86rem;">
        Selaku pihak pemohon yang menerima barang hasil pengadaan, selanjutnya disebut <strong>PIHAK KEDUA</strong>.
      </td>
    </tr>
  </table>

  <div class="text-content mb-1">
    Kedua belah pihak secara bersama-sama menerangkan bahwa <strong>PIHAK PERTAMA</strong> telah menyerahkan kepada <strong>PIHAK KEDUA</strong>, dan <strong>PIHAK KEDUA</strong> telah menerima serta melakukan pemeriksaan fisik terhadap barang hasil pengadaan berdasarkan Memo No. <strong><?= $nomorMemo ?></strong> dengan rincian:
  </div>

  <!-- Rincian Barang Pengadaan -->
  <table class="table-items">
    <thead>
      <tr>
        <th style="width: 32px;">No.</th>
        <th>Uraian / Spesifikasi Barang Pengadaan</th>
        <th style="width: 110px;">Tgl Realisasi</th>
        <th style="width: 140px;">Biaya Riil Beli</th>
        <th style="width: 130px;">Kondisi Fisik</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="text-center">1.</td>
        <td>
          <strong><?= $perihal ?></strong>
          <div class="small text-muted mt-0.5" style="font-size:0.82rem;"><?= nl2br($isiMemo) ?></div>
          <?php if (!empty($memo['catatan_realisasi'])): ?>
            <div class="small mt-0.5 text-secondary fst-italic" style="font-size:0.80rem;"><strong>Catatan Toko:</strong> <?= $catatanRealisasi ?></div>
          <?php endif; ?>
        </td>
        <td class="text-center"><?= date('d/m/Y', $dateTs) ?></td>
        <td class="text-end fw-bold">Rp. <?= number_format($biayaRealisasi, 0, ',', '.') ?>,-</td>
        <td class="text-center fw-bold text-success" style="font-size:0.82rem;">BARU &amp; LENGKAP</td>
      </tr>
      <tr>
        <td colspan="3" class="text-end fw-bold">TOTAL NILAI PEMBELIAN :</td>
        <td class="text-end fw-bold">Rp. <?= number_format($biayaRealisasi, 0, ',', '.') ?>,-</td>
        <td class="text-center small text-muted font-monospace" style="font-size:0.75rem;">Nota Toko <?= $hasNota ? '(&#10003; Ada)' : '(-)' ?></td>
      </tr>
    </tbody>
  </table>

  <div class="text-muted fst-italic mb-2" style="font-size:0.84rem;">
    Terbilang: <strong>// <?= $terbilangBiaya ?> //</strong>
  </div>

  <!-- Klausul Pernyataan BAST -->
  <div class="text-content mb-2" style="font-size: 0.88rem; line-height: 1.4;">
    Dengan ketentuan dan kesepakatan sebagai berikut:
    <ol class="mb-0 ps-3">
      <li>Barang tersebut telah diterima dalam kondisi 100% baru, lengkap, dan telah diuji/diperiksa fisik berfungsi baik.</li>
      <li>Pembelian dilengkapi bukti <strong>Nota Toko Resmi <?= $hasNota ? '(&#10003; Terlampir)' : '' ?></strong> dan <strong>Dokumentasi Foto Fisik <?= $hasFoto ? '(&#10003; Terlampir)' : '' ?></strong>.</li>
      <li>Sejak tanggal BAST ini, barang resmi tercatat sebagai aset inventaris kantor unit <strong><?= $cabang ?></strong> dan wajib didaftarkan ke <strong>Buku Kartu Inventaris Aktif</strong>.</li>
    </ol>
  </div>

  <div class="text-content mb-2" style="font-size: 0.88rem;">
    Demikian Berita Acara Serah Terima (BAST) ini dibuat dan ditandatangani dalam rangkap secukupnya untuk kelengkapan administrasi dan audit pembukuan.
  </div>

  <!-- Kolom Tanda Tangan 3 Pihak -->
  <table class="signature-table">
    <tr>
      <td>
        <div class="fw-bold">PIHAK PERTAMA</div>
        <div class="small text-muted" style="font-size:0.78rem;">Yang Menyerahkan,</div>
        <div class="sig-space"></div>
        <div class="sig-name"><?= $namaMengetahui ?></div>
        <div class="sig-role"><?= $jabatanMengetahui ?></div>
      </td>
      <td>
        <div class="fw-bold">PIHAK KEDUA</div>
        <div class="small text-muted" style="font-size:0.78rem;">Yang Menerima,</div>
        <div class="sig-space"></div>
        <div class="sig-name"><?= $namaPengaju ?></div>
        <div class="sig-role"><?= $jabatanPengaju ?></div>
      </td>
      <td>
        <div class="fw-bold">MENYETUJUI,</div>
        <div class="small text-muted" style="font-size:0.78rem;">Direksi Operasional,</div>
        <div class="sig-space"></div>
        <div class="sig-name"><?= $namaMenyetujui ?></div>
        <div class="sig-role"><?= $jabatanMenyetujui ?></div>
      </td>
    </tr>
  </table>
</div>

<?php if ($hasFoto || $hasNota): ?>
<!-- Lembar Lampiran Bukti Fisik & Nota Toko (Halaman 2 Otomatis Saat Print) -->
<div class="page-break"></div>
<div class="bast-sheet sheet-page-2 mt-4">
  <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
    <div>
      <div class="fw-bold text-uppercase" style="font-size: 0.95rem; color: #003B73; letter-spacing: 0.04em;">
        LAMPIRAN DOKUMENTASI FISIK &amp; NOTA PEMBELIAN
      </div>
      <div class="text-muted small" style="font-size: 0.80rem;">
        Referensi Dokumen : <strong><?= $bastNo ?></strong> &middot; Perihal: <strong><?= $perihal ?></strong>
      </div>
    </div>
    <span class="badge bg-light text-dark border font-monospace px-2.5 py-1" style="font-size: 0.72rem;">LAMPIRAN BAST</span>
  </div>

  <div class="row g-3">
    <?php if ($hasFoto): ?>
      <div class="<?= $hasNota ? 'col-6' : 'col-12' ?>">
        <div class="border rounded-3 p-3 text-center bg-light h-100">
          <div class="fw-bold small text-dark mb-2 d-flex align-items-center justify-content-center gap-1.5" style="font-size: 0.85rem;">
            <i class="bi bi-camera-fill text-primary"></i> 1. Foto Fisik Barang Baru yang Diterima
          </div>
          <div class="p-2 bg-white rounded border d-flex align-items-center justify-content-center" style="min-height: 320px;">
            <img src="<?= e(memo_file_url($memo['foto_barang_file'])) ?>" alt="Foto Fisik Barang" style="max-height: 380px; width: auto; max-width: 100%; object-fit: contain;" class="rounded">
          </div>
          <div class="small text-muted mt-2 fst-italic" style="font-size: 0.76rem;">
            Telah diperiksa kondisinya lengkap, berfungsi baik, dan tidak ada cacat fisik di unit <?= $cabang ?>.
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($hasNota): ?>
      <div class="<?= $hasFoto ? 'col-6' : 'col-12' ?>">
        <div class="border rounded-3 p-3 text-center bg-light h-100">
          <div class="fw-bold small text-dark mb-2 d-flex align-items-center justify-content-center gap-1.5" style="font-size: 0.85rem;">
            <i class="bi bi-receipt-cutoff text-success"></i> 2. Bukti Kuitansi / Faktur / Nota Toko Asli
          </div>
          <div class="p-2 bg-white rounded border d-flex align-items-center justify-content-center" style="min-height: 320px;">
            <?php if (str_ends_with(strtolower($memo['bukti_beli_file']), '.pdf')): ?>
              <div class="p-4 text-center">
                <i class="bi bi-file-earmark-pdf-fill text-danger fs-1"></i>
                <div class="fw-bold small mt-2">Dokumen Nota Format PDF</div>
                <div class="small text-muted mb-3">Tersimpan aman di arsip digital server</div>
                <a href="<?= e(memo_file_url($memo['bukti_beli_file'])) ?>" target="_blank" class="btn btn-sm btn-outline-danger fw-semibold px-3 no-print">
                  <i class="bi bi-box-arrow-up-right me-1"></i> Buka File PDF
                </a>
              </div>
            <?php else: ?>
              <img src="<?= e(memo_file_url($memo['bukti_beli_file'])) ?>" alt="Nota Toko" style="max-height: 380px; width: auto; max-width: 100%; object-fit: contain;" class="rounded">
            <?php endif; ?>
          </div>
          <div class="small text-muted mt-2 fst-italic" style="font-size: 0.76rem;">
            Nilai Realisasi Pembelian: <strong>Rp. <?= number_format($biayaRealisasi, 0, ',', '.') ?>,-</strong>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="text-end text-muted small mt-4 pt-2 border-top font-monospace" style="font-size: 0.70rem;">
    Dokumen Lampiran BAST Pengadaan &middot; PT BPR Mitratama Arthabuana &middot; Dicetak pada: <?= date('d/m/Y H:i') ?>
  </div>
</div>
<?php endif; ?>

</body>
</html>
