<?php
/**
 * Dokumen Resmi Memo Internal Pengajuan Inventaris
 * PT BPR Mitratama Arthabuana
 * Sesuai Standar Template Resmi Bank Mitra
 */

require __DIR__ . '/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$memo = get_memo_by_id($id);

if (!$memo) {
    http_response_code(404);
    die('Dokumen Memo Internal Pengajuan tidak ditemukan.');
}

$dateTs = strtotime($memo['tanggal_memo'] ?? 'now');
$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$tglFormat = date('j', $dateTs) . ' ' . $monthNames[(int)date('n', $dateTs)] . ' ' . date('Y', $dateTs);

$nomor = e($memo['nomor_memo']);
$kepada = e($memo['kepada'] ?: 'Direksi');
$dari = e($memo['dari_jabatan']);
$perihal = e($memo['perihal']);
$lampiran = e($memo['lampiran'] ?: '-');
$isi = nl2br(e($memo['isi_memo']));
$totalNominal = (float)($memo['total_estimasi'] ?? 0);
$terbilang = e($memo['terbilang']);
if (empty($terbilang) && $totalNominal > 0) {
    $terbilang = '://' . terbilang_rupiah($totalNominal) . '://';
} elseif (!empty($terbilang) && !str_starts_with($terbilang, '://')) {
    $terbilang = '://' . trim($terbilang, ' /') . '://';
}

$namaPengaju = e($memo['nama_pengaju']);
$jabatanPengaju = e($memo['jabatan_pengaju'] ?: 'Kepala Cabang');
$namaMengetahui = e($memo['nama_mengetahui'] ?: 'Defie Damayanti');
$jabatanMengetahui = e($memo['jabatan_mengetahui'] ?: 'Kabag. Umum & SDM');
$namaMenyetujui = e($memo['nama_menyetujui'] ?: 'Kahar Muzaqir, SH');
$jabatanMenyetujui = e($memo['jabatan_menyetujui'] ?: 'Direktur Operasional');

$logoUrl = module_url('bank_mitra_memo_logo.png');
$footerUrl = module_url('bank_mitra_memo_footer.png');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Memo Internal - <?= $nomor ?></title>
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

.memo-sheet {
  max-width: 210mm;
  min-height: 297mm;
  margin: 0 auto;
  background: #FFFFFF;
  padding: 16mm 22mm 34mm 22mm;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
  border-radius: 4px;
  position: relative;
  box-sizing: border-box;
  overflow: hidden;
}

.memo-top-logo {
  max-width: 195px;
  height: auto;
  object-fit: contain;
  display: block;
  margin-bottom: 22px;
}

.memo-title-block {
  margin-bottom: 0;
}

.memo-heading {
  font-size: 1.30rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin-bottom: 4px;
  font-family: "Times New Roman", Times, Georgia, serif;
}

/* Tabel Kotak Grid Header Memo Sesuai Format Bank Mitra */
.memo-grid-table {
  width: 100%;
  border-collapse: collapse;
  border-top: 2.5px solid #000000;
  border-bottom: 2.5px solid #000000;
  margin-bottom: 22px;
  font-family: "Times New Roman", Times, Georgia, serif;
  font-size: 0.96rem;
  line-height: 1.35;
}

.memo-left-cell {
  width: 44%;
  vertical-align: top;
  padding: 10px 12px;
  border-right: 1.5px solid #000000;
}

.memo-right-cell {
  width: 56%;
  vertical-align: top;
  padding: 0;
}

.meta-row {
  padding: 6px 12px;
  border-bottom: 1.5px solid #000000;
}

.meta-row:last-child {
  border-bottom: none;
}

.memo-body {
  font-size: 1.02rem;
  line-height: 1.6;
  text-align: justify;
  font-family: "Times New Roman", Times, Georgia, serif;
}

.memo-body p {
  margin-bottom: 16px;
}

.signature-row {
  margin-top: 48px;
  display: flex;
  justify-content: space-between;
  text-align: center;
  page-break-inside: avoid;
  font-family: "Times New Roman", Times, Georgia, serif;
}

.sig-col {
  width: 32%;
  font-size: 0.98rem;
}

.sig-space {
  height: 64px;
}

.sig-name {
  font-weight: 700;
  text-decoration: underline;
  font-size: 1.02rem;
}

.sig-title {
  font-size: 0.90rem;
  color: #111111;
  margin-top: 2px;
}

.memo-page-footer {
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
  .memo-sheet {
    box-shadow: none !important;
    padding: 14mm 20mm 32mm 20mm !important;
    max-width: 100% !important;
    width: 100% !important;
    min-height: 297mm !important;
    height: 297mm !important;
    border-radius: 0 !important;
  }
  .memo-page-footer {
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

<!-- Floating Action Bar (Hidden when printed) -->
<div class="floating-action-bar no-print">
  <?php if (!empty($memo['lampiran_file'])): ?>
    <a href="<?= e(memo_file_url($memo['lampiran_file'])) ?>" target="_blank" class="btn btn-light border fw-semibold shadow-lg px-3 py-2">
      <i class="bi bi-file-earmark-text me-1"></i> Penawaran / Brosur
    </a>
  <?php endif; ?>
  <?php if (!empty($memo['bukti_beli_file'])): ?>
    <a href="<?= e(memo_file_url($memo['bukti_beli_file'])) ?>" target="_blank" class="btn btn-warning text-dark fw-bold shadow-lg px-3 py-2">
      <i class="bi bi-receipt me-1"></i> Nota Toko
    </a>
  <?php endif; ?>
  <?php if (!empty($memo['memo_disetujui_file'])): ?>
    <a href="<?= e(memo_file_url($memo['memo_disetujui_file'])) ?>" target="_blank" class="btn btn-info text-dark fw-bold shadow-lg px-3 py-2">
      <i class="bi bi-file-earmark-check me-1"></i> Scan Memo Disetujui
    </a>
  <?php endif; ?>
  <button type="button" class="btn btn-primary fw-bold shadow-lg px-4 py-2" onclick="window.print()">
    <i class="bi bi-printer-fill me-1.5"></i> Cetak Dokumen Memo
  </button>
  <a href="<?= e(module_url('memo_pengajuan.php')) ?>" class="btn btn-dark fw-semibold shadow-lg px-3 py-2">
    <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Memo
  </a>
</div>

<!-- Lembar Dokumen Resmi Memo Internal -->
<div class="memo-sheet">
  <!-- 1. Logo Resmi Bank Mitra di Sudut Kiri Atas -->
  <img src="<?= $logoUrl ?>" alt="Bank Mitra Logo" class="memo-top-logo">

  <!-- 2. Judul Dokumen -->
  <div class="memo-title-block">
    <div class="memo-heading">MEMO INTERNAL</div>
  </div>

  <!-- 3. Tabel Kotak Grid Header Memo (Format Resmi Bank Mitra) -->
  <table class="memo-grid-table">
    <tr>
      <!-- Kolom Kiri: Kepada Yth -->
      <td class="memo-left-cell">
        <strong>Kepada Yth : <?= $kepada ?></strong>
      </td>

      <!-- Kolom Kanan: DARI, No, PERIHAL, TANGGAL, Lampiran -->
      <td class="memo-right-cell">
        <div class="meta-row">
          <strong>DARI :</strong><br>
          <span style="display: inline-block; margin-left: 8px;">- <?= $dari ?></span>
        </div>
        <div class="meta-row">
          <strong>No:</strong><br>
          <span class="font-monospace fw-bold"><?= $nomor ?></span>
        </div>
        <div class="meta-row">
          <strong>PERIHAL : <?= $perihal ?></strong>
        </div>
        <div class="meta-row">
          <strong>TANGGAL :</strong><br>
          <span><?= $tglFormat ?></span><br>
          <span style="display: inline-block; margin-top: 4px;">Lampiran : <?= $lampiran ?></span>
          <?php if (!empty($memo['lampiran_file'])): ?>
            <span class="no-print ms-2 small" style="font-family: -apple-system, sans-serif; font-size: 0.78rem;">
              (<a href="<?= e(memo_file_url($memo['lampiran_file'])) ?>" target="_blank" style="color: #475569; font-weight: 600; text-decoration: underline;"><i class="bi bi-file-earmark-text"></i> Berkas Penawaran</a>)
            </span>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  </table>

  <!-- 4. Uraian Isi Memo Permohonan -->
  <div class="memo-body">
    <p>Dengan hormat,</p>
    <p style="text-indent: 32px;">
      <?= $isi ?><?php if ($totalNominal > 0 && !str_contains($isi, number_format($totalNominal, 0, ',', '.'))): ?> dengan estimasi biaya sebesar <strong>Rp. <?= number_format($totalNominal, 0, ',', '.') ?>,-</strong> Terbilang <strong><?= $terbilang ?></strong><?php endif; ?>
    </p>
    <p>Demikian permohonan kami buat dan atas kerjasamanya kami ucapkan terima kasih.</p>
  </div>

  <!-- 5. Kolom Tanda Tangan 3 Pejabat Bank -->
  <div class="signature-row">
    <div class="sig-col">
      <div>Yang mengajukan,</div>
      <div class="sig-space"></div>
      <div class="sig-name"><?= $namaPengaju ?></div>
      <div class="sig-title"><?= $jabatanPengaju ?></div>
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

  <!-- 6. Footer Kop Surat Resmi Bank Mitra di Bagian Bawah Kertas -->
  <img src="<?= $footerUrl ?>" alt="Footer Kop Surat Bank Mitra" class="memo-page-footer">
</div>

</body>
</html>
