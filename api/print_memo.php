<?php
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
    $terbilang = '//' . terbilang_rupiah($totalNominal) . '//';
}

$namaPengaju = e($memo['nama_pengaju']);
$jabatanPengaju = e($memo['jabatan_pengaju'] ?: 'Kepala Cabang');
$namaMengetahui = e($memo['nama_mengetahui'] ?: 'Defie Damayanti');
$jabatanMengetahui = e($memo['jabatan_mengetahui'] ?: 'Kabag. Umum & SDM');
$namaMenyetujui = e($memo['nama_menyetujui'] ?: 'Kahar Muzaqir, SH');
$jabatanMenyetujui = e($memo['jabatan_menyetujui'] ?: 'Direktur Operasional');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Memo Internal - <?= $nomor ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Times+New+Roman&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body {
  background: #E2E8F0;
  font-family: "Times New Roman", Times, serif;
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
  padding: 25mm 25mm 25mm 25mm;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
  border-radius: 4px;
  position: relative;
  box-sizing: border-box;
}

.memo-logo {
  max-height: 48px;
  width: auto;
  object-fit: contain;
}

.kop-bank-name {
  font-size: 1.10rem;
  font-weight: 800;
  color: #003B73;
  letter-spacing: 0.04em;
  font-family: "Plus Jakarta Sans", -apple-system, sans-serif;
  line-height: 1.2;
}

.kop-sub-text {
  font-size: 0.80rem;
  color: #334155;
  font-family: "Plus Jakarta Sans", -apple-system, sans-serif;
  margin-top: 1px;
}

.kop-address-text {
  font-size: 0.70rem;
  color: #64748B;
  font-family: "Plus Jakarta Sans", -apple-system, sans-serif;
}

.memo-heading {
  font-size: 1.35rem;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  margin-bottom: 6px;
  font-family: "Times New Roman", Times, serif;
}

.double-line {
  border-top: 3px double #000000;
  margin: 6px 0 16px 0;
}

.header-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 1rem;
  line-height: 1.45;
}

.header-table td {
  vertical-align: top;
  padding: 2px 4px;
}

.memo-body {
  font-size: 1.05rem;
  line-height: 1.6;
  text-align: justify;
  margin-top: 20px;
}

.memo-body p {
  margin-bottom: 14px;
}

.signature-row {
  margin-top: 55px;
  display: flex;
  justify-content: space-between;
  text-align: center;
  page-break-inside: avoid;
}

.sig-col {
  width: 30%;
  font-size: 1rem;
}

.sig-space {
  height: 75px;
}

.sig-name {
  font-weight: 700;
  text-decoration: underline;
  font-size: 1.05rem;
}

.sig-title {
  font-size: 0.95rem;
  color: #111111;
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
  }
  .memo-sheet {
    box-shadow: none !important;
    padding: 15mm 20mm !important;
    max-width: 100% !important;
    width: 100% !important;
  }
  .floating-action-bar, .no-print {
    display: none !important;
  }
}
</style>
</head>
<body>

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
  <?php if (!empty($memo['foto_barang_file'])): ?>
    <a href="<?= e(memo_file_url($memo['foto_barang_file'])) ?>" target="_blank" class="btn btn-light border fw-semibold shadow-lg px-3 py-2">
      <i class="bi bi-camera me-1 text-primary"></i> Foto Barang
    </a>
  <?php endif; ?>
  <button type="button" class="btn btn-primary fw-bold shadow-lg px-4 py-2" onclick="window.print()">
    <i class="bi bi-printer-fill me-1.5"></i> Cetak Dokumen Memo
  </button>
  <a href="<?= e(module_url('memo_pengajuan.php')) ?>" class="btn btn-dark fw-semibold shadow-lg px-3 py-2">
    <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Memo
  </a>
</div>

<div class="memo-sheet">
  <!-- Kop Surat Bank Mitra dengan Logo -->
  <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
    <div class="d-flex align-items-center gap-3">
      <img src="<?= app_logo_url() ?>" alt="Bank Mitra Logo" class="memo-logo">
      <div>
        <div class="kop-bank-name">PT BPR MITRATAMA ARTHABUANA</div>
        <div class="kop-sub-text">Kantor Pusat Operasional &middot; Bagian Umum &amp; Sarana Prasarana</div>
        <div class="kop-address-text">Jl. Kapten Piere Tendean No. 170, Banjarmasin &middot; Telp. (0511) 3271222</div>
      </div>
    </div>
    <div class="text-end font-monospace text-muted small" style="font-size: 0.72rem; line-height: 1.35;">
      <span class="badge bg-light text-dark border px-2 py-0.5 mb-1 d-inline-block"><?= e($memo['cabang_nama'] ?: 'Kantor Pusat Operasional') ?></span><br>
      <?= $tglFormat ?>
    </div>
  </div>

  <!-- Judul Utama -->
  <div class="memo-heading">MEMO INTERNAL</div>
  <div class="double-line"></div>

  <!-- Header Memo Grid -->
  <table class="header-table">
    <tr>
      <!-- Kolom Kiri: Kepada Yth -->
      <td style="width: 45%;">
        <strong>Kepada Yth : <?= $kepada ?></strong>
      </td>

      <!-- Kolom Kanan: DARI, No, PERIHAL, TANGGAL, Lampiran -->
      <td style="width: 55%; padding-left: 20px;">
        <table style="width: 100%; border-collapse: collapse;">
          <tr>
            <td colspan="2">
              <strong>DARI :</strong><br>
              <span style="display: inline-block; margin-left: 8px;">- <?= $dari ?></span>
            </td>
          </tr>
          <tr>
            <td style="width: 80px; padding-top: 6px;">No:</td>
            <td style="padding-top: 6px;"><strong><?= $nomor ?></strong></td>
          </tr>
          <tr>
            <td style="vertical-align: top; padding-top: 6px;"><strong>PERIHAL</strong></td>
            <td style="padding-top: 6px;">: <strong><?= $perihal ?></strong></td>
          </tr>
          <tr>
            <td style="padding-top: 6px;"><strong>TANGGAL</strong></td>
            <td style="padding-top: 6px;">: <strong><?= $tglFormat ?></strong></td>
          </tr>
          <tr>
            <td style="padding-top: 4px;">Lampiran</td>
            <td style="padding-top: 4px;">: <?= $lampiran ?>
              <?php if (!empty($memo['lampiran_file'])): ?>
                <span class="no-print ms-2 small" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 0.82rem;">
                  (<a href="<?= e(memo_file_url($memo['lampiran_file'])) ?>" target="_blank" style="color: #475569; font-weight: 600; text-decoration: underline;"><i class="bi bi-file-earmark-text"></i> Penawaran</a>)
                </span>
              <?php endif; ?>
              <?php if (!empty($memo['bukti_beli_file'])): ?>
                <span class="no-print ms-1 small" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 0.82rem;">
                  (<a href="<?= e(memo_file_url($memo['bukti_beli_file'])) ?>" target="_blank" style="color: #16A34A; font-weight: 600; text-decoration: underline;"><i class="bi bi-receipt"></i> Nota Toko</a>)
                </span>
              <?php endif; ?>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <div class="double-line" style="margin-top: 14px; margin-bottom: 22px;"></div>

  <!-- Isi Memo -->
  <div class="memo-body">
    <p>Dengan hormat,</p>

    <div style="text-indent: 35px; line-height: 1.65;">
      <?= $isi ?>
      <?php if ($totalNominal > 0): ?>
        dengan estimasi biaya sebesar <strong>Rp. <?= number_format($totalNominal, 0, ',', '.') ?>,-</strong>
      <?php endif; ?>
    </div>

    <?php if (!empty($terbilang)): ?>
      <p style="margin-top: 12px; font-weight: 700; font-style: italic;">
        Terbilang Estimasi : <?= $terbilang ?>
      </p>
    <?php endif; ?>

    <p style="margin-top: 24px;">
      Demikian permohonan kami buat dan atas kerjasamanya kami ucapkan terima kasih.
    </p>
  </div>

  <!-- 3 Kolom Tanda Tangan Resmi Sesuai Format Memo Bank Mitra -->
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

</div>

</body>
</html>
