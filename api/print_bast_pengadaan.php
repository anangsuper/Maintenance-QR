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

.bast-sheet {
  max-width: 210mm;
  min-height: 297mm;
  margin: 0 auto;
  background: #FFFFFF;
  padding: 22mm 22mm 20mm 22mm;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
  border-radius: 4px;
  position: relative;
  box-sizing: border-box;
}

.bank-logo {
  max-height: 52px;
  width: auto;
  object-fit: contain;
}

.kop-title {
  font-size: 1.15rem;
  font-weight: 800;
  color: #003B73;
  letter-spacing: 0.04em;
  line-height: 1.2;
}

.kop-sub {
  font-size: 0.85rem;
  color: #475569;
  font-family: "Plus Jakarta Sans", sans-serif;
  margin-top: 2px;
}

.kop-address {
  font-size: 0.75rem;
  color: #64748B;
  font-family: "Plus Jakarta Sans", sans-serif;
}

.kop-line {
  border-bottom: 2.5px solid #003B73;
  margin: 12px 0 20px 0;
}

.doc-heading {
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  margin-bottom: 4px;
  text-align: center;
  text-decoration: underline;
}

.doc-subheading {
  font-size: 0.95rem;
  font-weight: 700;
  text-align: center;
  margin-bottom: 22px;
  font-family: "Times New Roman", Times, serif;
}

.text-content {
  font-size: 1.02rem;
  line-height: 1.55;
  text-align: justify;
}

.table-party {
  width: 100%;
  font-size: 1rem;
  line-height: 1.45;
  margin-bottom: 12px;
}

.table-party td {
  vertical-align: top;
  padding: 2px 4px;
}

.table-items {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.95rem;
  margin: 16px 0;
}

.table-items th, .table-items td {
  border: 1px solid #000000;
  padding: 6px 10px;
}

.table-items th {
  background-color: #f1f5f9;
  font-weight: 700;
  text-align: center;
}

.signature-table {
  width: 100%;
  margin-top: 24px;
  font-size: 0.95rem;
  border-collapse: collapse;
}

.signature-table td {
  text-align: center;
  vertical-align: top;
  padding: 6px 4px;
  width: 33.33%;
}

.sig-space {
  height: 70px;
}

@media print {
  body {
    background: transparent !important;
    padding: 0 !important;
  }
  .no-print {
    display: none !important;
  }
  .bast-sheet {
    box-shadow: none !important;
    margin: 0 !important;
    padding: 15mm 20mm 15mm 20mm !important;
    border-radius: 0 !important;
    max-width: 100% !important;
    min-height: auto !important;
  }
}
</style>
</head>
<body>

<!-- Action Bar (Hidden when printed) -->
<div class="container-fluid no-print mb-4" style="max-width: 210mm;">
  <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded-3 shadow-sm border">
    <div class="d-flex align-items-center gap-2">
      <a href="<?= e(module_url('memo_pengajuan.php')) ?>" class="btn btn-outline-secondary btn-sm fw-semibold">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Memo
      </a>
      <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 small">
        <i class="bi bi-check-circle-fill me-1"></i> Status: Selesai Dibeli
      </span>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="<?= e(module_url('print_memo.php', ['id' => $id])) ?>" target="_blank" class="btn btn-outline-primary btn-sm fw-semibold">
        <i class="bi bi-file-earmark-text me-1"></i> Cetak Memo Pengajuan
      </a>
      <button type="button" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm" onclick="window.print()">
        <i class="bi bi-printer me-1"></i> Cetak BAST Pengadaan
      </button>
    </div>
  </div>
</div>

<!-- Lembar Dokumen BAST Resmi -->
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
    <div class="text-end font-monospace small text-muted" style="font-size:0.75rem;">
      FORMULIR: BAST-PENG-01<br>
      Tgl: <?= date('d/m/Y', $dateTs) ?>
    </div>
  </div>

  <div class="kop-line"></div>

  <!-- Judul Dokumen -->
  <div class="doc-heading">BERITA ACARA SERAH TERIMA PENGADAAN BARANG</div>
  <div class="doc-subheading">Nomor : <?= $bastNo ?></div>

  <div class="text-content mb-3">
    Pada hari ini, <strong><?= $hari ?></strong> tanggal <strong><?= $tgl ?></strong> bulan <strong><?= $bln ?></strong> tahun <strong><?= $thn ?></strong> (<?= date('d/m/Y', $dateTs) ?>), bertempat di Kantor <strong>PT BPR Mitratama Arthabuana</strong>, telah dilaksanakan serah terima dan pemeriksaan fisik barang pengadaan sarana/prasarana inventaris oleh dan antara:
  </div>

  <!-- Identitas Para Pihak -->
  <table class="table-party mb-3">
    <tr>
      <td style="width: 28px;"><strong>I.</strong></td>
      <td style="width: 140px;"><strong>Nama</strong></td>
      <td style="width: 12px;">:</td>
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
      <td colspan="3" class="fst-italic text-secondary">
        Selaku pihak yang menyerahkan barang hasil realisasi pengadaan inventaris, selanjutnya disebut <strong>PIHAK PERTAMA</strong>.
      </td>
    </tr>
  </table>

  <table class="table-party mb-3">
    <tr>
      <td style="width: 28px;"><strong>II.</strong></td>
      <td style="width: 140px;"><strong>Nama</strong></td>
      <td style="width: 12px;">:</td>
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
      <td colspan="3" class="fst-italic text-secondary">
        Selaku pihak pemohon yang menerima barang hasil pengadaan, selanjutnya disebut <strong>PIHAK KEDUA</strong>.
      </td>
    </tr>
  </table>

  <div class="text-content mb-2">
    Kedua belah pihak secara bersama-sama menerangkan bahwa <strong>PIHAK PERTAMA</strong> telah menyerahkan kepada <strong>PIHAK KEDUA</strong>, dan <strong>PIHAK KEDUA</strong> telah menerima serta melakukan pemeriksaan fisik terhadap barang hasil pengadaan berdasarkan Memo Internal No. <strong><?= $nomorMemo ?></strong> dengan rincian sebagai berikut:
  </div>

  <!-- Rincian Barang Pengadaan -->
  <table class="table-items">
    <thead>
      <tr>
        <th style="width: 35px;">No.</th>
        <th>Uraian / Spesifikasi Barang Pengadaan</th>
        <th style="width: 130px;">Tgl Realisasi</th>
        <th style="width: 150px;">Biaya Riil Beli</th>
        <th style="width: 130px;">Kondisi Fisik</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="text-center">1.</td>
        <td>
          <strong><?= $perihal ?></strong>
          <div class="small text-muted mt-1" style="font-size:0.85rem;"><?= nl2br($isiMemo) ?></div>
          <?php if (!empty($memo['catatan_realisasi'])): ?>
            <div class="small mt-1 text-secondary fst-italic"><strong>Catatan Pembelian:</strong> <?= $catatanRealisasi ?></div>
          <?php endif; ?>
        </td>
        <td class="text-center"><?= date('d/m/Y', $dateTs) ?></td>
        <td class="text-end fw-bold">Rp. <?= number_format($biayaRealisasi, 0, ',', '.') ?>,-</td>
        <td class="text-center fw-bold text-success">BARU &amp; LENGKAP</td>
      </tr>
      <tr>
        <td colspan="3" class="text-end fw-bold">TOTAL NILAI PEMBELIAN :</td>
        <td class="text-end fw-bold">Rp. <?= number_format($biayaRealisasi, 0, ',', '.') ?>,-</td>
        <td class="text-center small text-muted">Nota Toko <?= $hasNota ? '(&#10003; Ada)' : '(-)' ?></td>
      </tr>
    </tbody>
  </table>

  <div class="small text-muted fst-italic mb-3" style="font-size:0.88rem;">
    Terbilang: <strong>// <?= $terbilangBiaya ?> //</strong>
  </div>

  <!-- Klausul Pernyataan BAST -->
  <div class="text-content mb-3" style="font-size: 0.98rem;">
    Dengan ketentuan dan kesepakatan sebagai berikut:
    <ol class="mb-0 ps-3">
      <li>Barang tersebut di atas telah diterima dalam kondisi 100% baru, lengkap dengan perlengkapan/aksesorisnya, dan telah diuji/diperiksa fisik dengan hasil berfungsi baik.</li>
      <li>Pembelian telah dilengkapi dengan dokumen pertanggungjawaban berupa <strong>Nota/Faktur Resmi Toko <?= $hasNota ? '(&#10003; Terlampir)' : '' ?></strong> dan <strong>Dokumentasi Foto Fisik Barang <?= $hasFoto ? '(&#10003; Terlampir)' : '' ?></strong>.</li>
      <li>Sejak tanggal penandatanganan Berita Acara ini, barang tersebut resmi tercatat sebagai aset inventaris kantor pada unit kerja <strong><?= $cabang ?></strong> dan wajib didaftarkan ke dalam <strong>Buku Kartu Inventaris Aktif</strong> serta dirawat dengan sebaik-baiknya.</li>
    </ol>
  </div>

  <div class="text-content mb-3">
    Demikian Berita Acara Serah Terima (BAST) ini dibuat dan ditandatangani dalam rangkap secukupnya untuk dipergunakan sebagaimana mestinya dan sebagai bahan kelengkapan audit pembukuan.
  </div>

  <!-- Kolom Tanda Tangan 3 Pihak -->
  <table class="signature-table">
    <tr>
      <td>
        <div><strong>PIHAK PERTAMA</strong></div>
        <div class="small text-muted">Yang Menyerahkan,</div>
        <div class="sig-space"></div>
        <div class="fw-bold text-decoration-underline"><?= $namaMengetahui ?></div>
        <div class="small"><?= $jabatanMengetahui ?></div>
      </td>
      <td>
        <div><strong>PIHAK KEDUA</strong></div>
        <div class="small text-muted">Yang Menerima,</div>
        <div class="sig-space"></div>
        <div class="fw-bold text-decoration-underline"><?= $namaPengaju ?></div>
        <div class="small"><?= $jabatanPengaju ?></div>
      </td>
      <td>
        <div><strong>MENYETUJUI,</strong></div>
        <div class="small text-muted">Direksi Operasional,</div>
        <div class="sig-space"></div>
        <div class="fw-bold text-decoration-underline"><?= $namaMenyetujui ?></div>
        <div class="small"><?= $jabatanMenyetujui ?></div>
      </td>
    </tr>
  </table>
</div>

</body>
</html>
