<?php
/**
 * Dokumen Resmi Lampiran Dokumentasi Fisik & Nota Pembelian
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

$nomorMemo = e($memo['nomor_memo']);
$docNo = $nomorMemo;

$perihal = e($memo['perihal']);
$cabang = e($memo['cabang_nama']);
$isiMemo = e($memo['isi_memo']);
$catatanRealisasi = e($memo['catatan_realisasi'] ?: '-');

$biayaEstimasi = (float)($memo['total_estimasi'] ?? 0);
$biayaRealisasi = !empty($memo['biaya_realisasi']) ? (float)$memo['biaya_realisasi'] : $biayaEstimasi;
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
<title>Dokumentasi Fisik &amp; Nota Pembelian - <?= $docNo ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
@page {
  size: A4 portrait;
  margin: 8mm 12mm 8mm 12mm;
}

body {
  background: #E2E8F0;
  font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  color: #0F172A;
  padding: 24px 10px;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}

.doc-sheet {
  max-width: 210mm;
  min-height: 297mm;
  margin: 0 auto;
  background: #FFFFFF;
  padding: 12mm 14mm;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
  border-radius: 4px;
  position: relative;
  box-sizing: border-box;
}

.bank-logo {
  max-height: 46px;
  width: auto;
  object-fit: contain;
}

.kop-title {
  font-size: 1.10rem;
  font-weight: 800;
  color: #003B73;
  letter-spacing: 0.04em;
  line-height: 1.2;
}

.kop-sub {
  font-size: 0.80rem;
  color: #334155;
  margin-top: 1px;
}

.kop-address {
  font-size: 0.70rem;
  color: #64748B;
}

.kop-double-line {
  border-top: 2.5px solid #003B73;
  border-bottom: 0.75px solid #003B73;
  height: 4px;
  margin: 8px 0 14px 0;
}

.doc-heading {
  font-size: 1.12rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #003B73;
  text-align: center;
  margin-bottom: 3px;
}

.doc-subheading {
  font-size: 0.85rem;
  font-weight: 600;
  text-align: center;
  color: #475569;
  margin-bottom: 14px;
}

.info-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
  margin-bottom: 14px;
}

.info-table td {
  padding: 5px 8px;
  border: 1px solid #CBD5E1;
  vertical-align: middle;
}

.info-label {
  background-color: #F8FAFC;
  font-weight: 600;
  color: #334155;
}

.price-highlight-box {
  background: #ECFDF5;
  border: 1.5px solid #10B981;
  border-radius: 8px;
  padding: 10px 14px;
  margin-bottom: 14px;
}

.doc-img-frame {
  height: 290px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #FFFFFF;
  border: 1px solid #CBD5E1;
  border-radius: 6px;
  padding: 4px;
  overflow: hidden;
}

.doc-img-frame img {
  max-height: 280px;
  width: auto;
  max-width: 100%;
  object-fit: contain;
  border-radius: 4px;
}

.signature-table {
  width: 100%;
  margin-top: 14px;
  font-size: 0.84rem;
  border-collapse: collapse;
  page-break-inside: avoid;
  break-inside: avoid;
}

.signature-table td {
  text-align: center;
  vertical-align: top;
  padding: 2px 6px;
  width: 33.33%;
}

.sig-space {
  height: 48px;
}

.sig-name {
  font-weight: 700;
  text-decoration: underline;
  font-size: 0.88rem;
  color: #0F172A;
}

.sig-role {
  font-size: 0.76rem;
  color: #475569;
  margin-top: 1px;
}

.floating-action-bar {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 9999;
  display: flex;
  gap: 8px;
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
  .doc-sheet {
    box-shadow: none !important;
    margin: 0 !important;
    padding: 0 !important;
    border-radius: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
    min-height: auto !important;
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
    <i class="bi bi-file-earmark-text me-1"></i> Dokumen Memo
  </a>
  <?php if (!empty($memo['memo_disetujui_file'])): ?>
    <a href="<?= e(memo_file_url($memo['memo_disetujui_file'])) ?>" target="_blank" class="btn btn-outline-info bg-white btn-sm fw-semibold shadow-lg px-3 py-2">
      <i class="bi bi-file-earmark-check me-1"></i> Scan Memo Disetujui
    </a>
  <?php endif; ?>
  <button type="button" class="btn btn-primary btn-sm fw-bold shadow-lg px-4 py-2" onclick="window.print()">
    <i class="bi bi-printer-fill me-1"></i> Cetak Lampiran (A4)
  </button>
</div>

<!-- Lembar Dokumen Resmi 1 Lembar Penuh A4 -->
<div class="doc-sheet">
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
      Tanggal Cetak: <?= date('d/m/Y') ?><br>
      Tanggal Pembelian: <?= date('d/m/Y', $dateTs) ?>
    </div>
  </div>

  <div class="kop-double-line"></div>

  <!-- Judul Dokumen Resmi -->
  <div class="doc-heading">LAMPIRAN DOKUMENTASI FISIK &amp; NOTA PEMBELIAN</div>
  <div class="doc-subheading">
    Referensi Dokumen : <strong><?= $docNo ?></strong> &middot; Perihal: <strong><?= $perihal ?></strong>
  </div>

  <!-- Tabel Ringkasan Pengadaan & Harga Fix Realisasi -->
  <table class="info-table">
    <tbody>
      <tr>
        <td class="info-label" style="width: 20%;">Nomor Memo</td>
        <td class="font-monospace fw-bold" style="width: 32%;"><?= $nomorMemo ?></td>
        <td class="info-label" style="width: 22%;">Tanggal Pembelian</td>
        <td style="width: 26%;"><?= date('d/m/Y', $dateTs) ?></td>
      </tr>
      <tr>
        <td class="info-label">Kantor Cabang</td>
        <td><strong><?= $cabang ?></strong></td>
        <td class="info-label">Pemohon / Penerima</td>
        <td><?= $namaPengaju ?> <span class="text-muted small">(<?= $jabatanPengaju ?>)</span></td>
      </tr>
      <tr>
        <td class="info-label">Perihal Pengadaan</td>
        <td colspan="3"><strong><?= $perihal ?></strong> &mdash; <span class="text-secondary"><?= nl2br($isiMemo) ?></span></td>
      </tr>
      <?php if (!empty($memo['catatan_realisasi'])): ?>
      <tr>
        <td class="info-label">Catatan Realisasi</td>
        <td colspan="3" class="fst-italic text-secondary"><?= $catatanRealisasi ?></td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- Box Penegasan Harga Fix Sesuai Nota Toko -->
  <div class="price-highlight-box d-flex align-items-center justify-content-between">
    <div>
      <div class="text-uppercase fw-bold text-success small" style="font-size: 0.72rem; letter-spacing: 0.05em;">
        <i class="bi bi-shield-check me-1"></i> HARGA FIX REALISASI PEMBELIAN (SESUAI NOTA TOKO RESMI)
      </div>
      <div class="fs-4 fw-bold text-dark font-monospace mt-0.5">
        Rp. <?= number_format($biayaRealisasi, 0, ',', '.') ?>,-
      </div>
      <div class="small text-muted fst-italic" style="font-size: 0.78rem;">
        Terbilang: <strong>// <?= $terbilangBiaya ?> //</strong>
      </div>
    </div>
    <div class="text-end">
      <span class="badge bg-success text-white font-monospace px-3 py-1.5" style="font-size: 0.80rem;">
        <i class="bi bi-check2-circle me-1"></i> SELESAI DIBELI
      </span>
      <div class="text-muted small mt-1 font-monospace" style="font-size: 0.70rem;">
        Estimasi Awal: Rp. <?= number_format($biayaEstimasi, 0, ',', '.') ?>,-
      </div>
    </div>
  </div>

  <!-- Dokumentasi Foto Fisik & Nota Toko Berdampingan -->
  <div class="row g-2.5 mb-2">
    <!-- 1. Foto Fisik Barang Baru yang Diterima -->
    <div class="col-6">
      <div class="border rounded p-2 text-center bg-light h-100">
        <div class="fw-bold small text-dark mb-1.5 d-flex align-items-center justify-content-center gap-1.5" style="font-size: 0.82rem;">
          <i class="bi bi-camera-fill text-primary"></i> 1. Foto Fisik Barang yang Diterima
        </div>
        <div class="doc-img-frame">
          <?php if ($hasFoto): ?>
            <img src="<?= e(memo_file_url($memo['foto_barang_file'])) ?>" alt="Foto Fisik Barang">
          <?php else: ?>
            <div class="text-muted small fst-italic">Foto fisik belum diunggah</div>
          <?php endif; ?>
        </div>
        <div class="small text-muted mt-1 fst-italic" style="font-size: 0.70rem;">
          Kondisi barang: <strong>100% Baru, Lengkap &amp; Berfungsi Baik</strong> di unit <?= $cabang ?>.
        </div>
      </div>
    </div>

    <!-- 2. Bukti Nota / Kuitansi Toko Asli -->
    <div class="col-6">
      <div class="border rounded p-2 text-center bg-light h-100">
        <div class="fw-bold small text-dark mb-1.5 d-flex align-items-center justify-content-center gap-1.5" style="font-size: 0.82rem;">
          <i class="bi bi-receipt-cutoff text-success"></i> 2. Bukti Kuitansi / Faktur / Nota Toko Asli
        </div>
        <div class="doc-img-frame">
          <?php if ($hasNota): ?>
            <?php if (str_ends_with(strtolower($memo['bukti_beli_file']), '.pdf')): ?>
              <div class="p-4 text-center">
                <i class="bi bi-file-earmark-pdf-fill text-danger fs-1"></i>
                <div class="fw-bold small mt-2">Dokumen Nota Format PDF</div>
                <div class="small text-muted mb-3 font-monospace">Rp. <?= number_format($biayaRealisasi, 0, ',', '.') ?>,-</div>
                <a href="<?= e(memo_file_url($memo['bukti_beli_file'])) ?>" target="_blank" class="btn btn-sm btn-outline-danger fw-semibold px-3 no-print">
                  <i class="bi bi-box-arrow-up-right me-1"></i> Buka File PDF
                </a>
              </div>
            <?php else: ?>
              <img src="<?= e(memo_file_url($memo['bukti_beli_file'])) ?>" alt="Bukti Nota Toko">
            <?php endif; ?>
          <?php else: ?>
            <div class="text-muted small fst-italic">Bukti nota toko belum diunggah</div>
          <?php endif; ?>
        </div>
        <div class="small text-muted mt-1 fst-italic font-monospace" style="font-size: 0.70rem;">
          Nilai Total Nota: <strong>Rp. <?= number_format($biayaRealisasi, 0, ',', '.') ?>,-</strong> (Sesuai Fisik Nota)
        </div>
      </div>
    </div>
  </div>

  <!-- Kolom Tanda Tangan Pengesahan 3 Pihak -->
  <table class="signature-table">
    <tr>
      <td>
        <div class="small text-muted" style="font-size:0.74rem;">Pemohon / Yang Menerima,</div>
        <div class="sig-space"></div>
        <div class="sig-name"><?= $namaPengaju ?></div>
        <div class="sig-role"><?= $jabatanPengaju ?></div>
      </td>
      <td>
        <div class="small text-muted" style="font-size:0.74rem;">Mengetahui (Pemeriksa Sarpras),</div>
        <div class="sig-space"></div>
        <div class="sig-name"><?= $namaMengetahui ?></div>
        <div class="sig-role"><?= $jabatanMengetahui ?></div>
      </td>
      <td>
        <div class="small text-muted" style="font-size:0.74rem;">Menyetujui (Otorisator),</div>
        <div class="sig-space"></div>
        <div class="sig-name"><?= $namaMenyetujui ?></div>
        <div class="sig-role"><?= $jabatanMenyetujui ?></div>
      </td>
    </tr>
  </table>

  <div class="d-flex justify-content-between align-items-center text-muted small mt-2.5 pt-1.5 border-top font-monospace" style="font-size: 0.68rem;">
    <span>PT BPR Mitratama Arthabuana &middot; Arsip Bukti Pembelian &amp; Fisik Inventaris</span>
    <span>Dokumen Sah &middot; Tercatat pada Buku Kartu Inventaris</span>
  </div>
</div>

</body>
</html>
