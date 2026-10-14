<?php
require __DIR__ . '/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$mutation = get_asset_mutation_by_id($id);

if (!$mutation) {
    http_response_code(404);
    die('Dokumen Berita Acara Serah Terima (BAST) tidak ditemukan.');
}

$dateTs = strtotime($mutation['tanggal_mutasi'] ?? $mutation['created_at'] ?? 'now');
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
$jam = date('H:i', $dateTs);

$bastNo = e($mutation['nomor_bast']);
$kodeAset = e($mutation['kode_aset']);
$namaAset = e($mutation['nama_aset']);
$sn = e($mutation['serial_number'] ?: '-');
$cabangAsal = e($mutation['cabang_asal']);
$cabangTujuan = e($mutation['cabang_tujuan']);
$ruanganAsal = e($mutation['ruangan_asal'] ?: '-');
$ruanganTujuan = e($mutation['ruangan_tujuan'] ?: '-');
$penggunaAsal = e($mutation['pengguna_asal'] ?: 'Umum');
$penggunaTujuan = e($mutation['pengguna_tujuan'] ?: 'Umum');
$alasan = e($mutation['alasan_mutasi']);
$keterangan = e($mutation['keterangan'] ?: 'Perangkat dalam kondisi operasional normal dan layak pakai.');
$petugas = e($mutation['petugas_nama'] ?: 'Petugas IT');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>BAST Mutasi Aset - <?= $bastNo ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Newsreader:ital,opsz,wght@0,6..72,500;0,6..72,700;1,6..72,500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body {
  background: #E2E8F0;
  font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  color: #0F172A;
  padding: 30px 10px;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}

.bast-document-sheet {
  max-width: 210mm;
  min-height: 297mm;
  margin: 0 auto;
  background: #FFFFFF;
  padding: 20mm 20mm 20mm 20mm;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
  border-radius: 4px;
  position: relative;
  box-sizing: border-box;
}

.kop-header {
  border-bottom: 2.5px solid #003B73;
  padding-bottom: 12px;
  margin-bottom: 20px;
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
  margin-bottom: 2px;
}

.kop-subtitle {
  font-size: 0.72rem;
  color: #475569;
  line-height: 1.35;
}

.document-title {
  text-align: center;
  margin: 22px 0 18px;
}

.doc-main-heading {
  font-size: 1.1rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #0F172A;
  text-decoration: underline;
  margin-bottom: 4px;
}

.doc-number {
  font-family: monospace;
  font-size: 0.84rem;
  font-weight: 700;
  color: #1E293B;
}

.doc-paragraph {
  font-size: 0.84rem;
  line-height: 1.6;
  text-align: justify;
  margin-bottom: 12px;
}

.table-bast {
  width: 100%;
  border-collapse: collapse;
  margin: 14px 0 16px;
  font-size: 0.8rem;
}

.table-bast th, .table-bast td {
  border: 1px solid #CBD5E1;
  padding: 8px 10px;
}

.table-bast th {
  background: #F8FAFC !important;
  color: #1E293B;
  font-weight: 700;
  text-transform: uppercase;
  font-size: 0.72rem;
  letter-spacing: 0.04em;
}

.signature-section {
  margin-top: 35px;
  page-break-inside: avoid;
}

.sig-box {
  text-align: center;
  font-size: 0.8rem;
}

.sig-space {
  height: 65px;
}

.sig-name {
  font-weight: 700;
  text-decoration: underline;
  color: #0F172A;
}

.sig-role {
  font-size: 0.72rem;
  color: #64748B;
}

.floating-action-bar {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 9999;
  display: flex;
  gap: 10px;
}

@media print {
  body {
    background: #FFFFFF !important;
    padding: 0 !important;
  }
  .bast-document-sheet {
    box-shadow: none !important;
    padding: 12mm 15mm !important;
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
  <button type="button" class="btn btn-primary fw-bold shadow-lg px-4 py-2" onclick="window.print()">
    <i class="bi bi-printer-fill me-1.5"></i> Cetak Dokumen BAST
  </button>
  <a href="<?= e(module_url('asset_mutations.php')) ?>" class="btn btn-dark fw-semibold shadow-lg px-3 py-2">
    <i class="bi bi-arrow-left me-1"></i> Kembali ke Riwayat
  </a>
</div>

<div class="bast-document-sheet">
  <!-- Kop Surat Resmi Bank Mitra -->
  <div class="kop-header d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-3">
      <img src="<?= app_logo_url() ?>" alt="Logo Bank Mitra" class="bank-logo">
      <div>
        <div class="kop-title">PT. BPR MITRATAMA ARTHABUANA</div>
        <div class="kop-subtitle">
          Kantor Operasional &amp; Manajemen Aset Teknologi Informasi<br>
          SK Otoritas Jasa Keuangan (OJK) · POJK No. 75/POJK.03/2016
        </div>
      </div>
    </div>
    <div class="text-end small text-muted font-monospace" style="font-size: 0.72rem;">
      FORMULIR: BAST-IT-01<br>
      TGL: <?= date('d/m/Y', $dateTs) ?>
    </div>
  </div>

  <!-- Judul Dokumen -->
  <div class="document-title">
    <div class="doc-main-heading">BERITA ACARA SERAH TERIMA (BAST) &amp; MUTASI ASET</div>
    <div class="doc-number">Nomor: <?= $bastNo ?></div>
  </div>

  <!-- Pembuka Dokumen -->
  <p class="doc-paragraph">
    Pada hari ini <strong><?= $hari ?></strong>, tanggal <strong><?= $tgl ?></strong> bulan <strong><?= $bln ?></strong> tahun <strong><?= $thn ?></strong> (<?= date('d/m/Y', $dateTs) ?> pukul <?= $jam ?> WIB), bertempat di <strong><?= $cabangTujuan ?></strong>, telah dilakukan serah terima fisik dan mutasi tanggung jawab atas perangkat operasional inventaris bank antara para pihak:
  </p>

  <!-- Pihak I & Pihak II -->
  <div class="row g-2 mb-3 small" style="font-size: 0.82rem;">
    <div class="col-6">
      <div class="p-2.5 border rounded bg-light h-100">
        <strong class="text-primary d-block mb-1"><i class="bi bi-arrow-up-right-circle me-1"></i> PIHAK PERTAMA (Yang Menyerahkan):</strong>
        <table class="table-borderless w-100 mt-1">
          <tr><td style="width: 85px;" class="text-muted">Nama</td><td>: <strong><?= $penggunaAsal ?></strong></td></tr>
          <tr><td class="text-muted">Cabang Asal</td><td>: <?= $cabangAsal ?></td></tr>
          <tr><td class="text-muted">Ruangan Asal</td><td>: <?= $ruanganAsal ?></td></tr>
        </table>
      </div>
    </div>
    <div class="col-6">
      <div class="p-2.5 border rounded bg-light h-100">
        <strong class="text-success d-block mb-1"><i class="bi bi-arrow-down-left-circle me-1"></i> PIHAK KEDUA (Yang Menerima):</strong>
        <table class="table-borderless w-100 mt-1">
          <tr><td style="width: 85px;" class="text-muted">Nama</td><td>: <strong><?= $penggunaTujuan ?></strong></td></tr>
          <tr><td class="text-muted">Cabang Tujuan</td><td>: <?= $cabangTujuan ?></td></tr>
          <tr><td class="text-muted">Ruangan Baru</td><td>: <?= $ruanganTujuan ?></td></tr>
        </table>
      </div>
    </div>
  </div>

  <p class="doc-paragraph">
    PIHAK PERTAMA telah menyerahkan kepada PIHAK KEDUA, dan PIHAK KEDUA menyatakan telah menerima dengan baik dan lengkap barang/perangkat inventaris bank dengan rincian sebagai berikut:
  </p>

  <!-- Tabel Rincian Aset -->
  <table class="table-bast">
    <thead>
      <tr>
        <th style="width: 35px;" class="text-center">No</th>
        <th style="width: 140px;">Kode Inventaris</th>
        <th>Nama Perangkat / Spesifikasi</th>
        <th style="width: 120px;">Nomor Seri (SN)</th>
        <th style="width: 110px;" class="text-center">Kondisi Fisik</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="text-center">1</td>
        <td class="font-monospace fw-bold"><?= $kodeAset ?></td>
        <td>
          <strong><?= $namaAset ?></strong>
          <div class="text-muted small">Jenis: <?= strtoupper($mutation['asset_type']) ?></div>
        </td>
        <td class="font-monospace"><?= $sn ?></td>
        <td class="text-center text-success fw-bold">BAIK / NORMAL</td>
      </tr>
    </tbody>
  </table>

  <!-- Alasan & Keterangan -->
  <div class="p-2.5 border rounded bg-light mb-3 small" style="font-size: 0.8rem; line-height: 1.5;">
    <div><strong>Alasan Mutasi:</strong> <?= $alasan ?></div>
    <div class="mt-1"><strong>Kelengkapan &amp; Keterangan:</strong> <?= $keterangan ?></div>
  </div>

  <p class="doc-paragraph mb-4">
    Sejak tanggal penandatanganan Berita Acara ini, tanggung jawab pemeliharaan rutin, operasional, dan keberadaan fisik perangkat tersebut sepenuhnya beralih kepada <strong>PIHAK KEDUA</strong> di <strong><?= $cabangTujuan ?></strong>. Demikian Berita Acara Serah Terima ini dibuat dengan sebenarnya dalam rangkap secukupnya untuk dipergunakan sebagaimana mestinya.
  </p>

  <!-- Bagian 4 Tanda Tangan -->
  <div class="signature-section">
    <div class="row text-center">
      <div class="col-3 sig-box">
        <div class="text-muted small">Pihak Yang Menyerahkan,</div>
        <div class="sig-space"></div>
        <div class="sig-name"><?= $penggunaAsal ?></div>
        <div class="sig-role">Pemegang Asal</div>
      </div>
      <div class="col-3 sig-box">
        <div class="text-muted small">Pihak Yang Menerima,</div>
        <div class="sig-space"></div>
        <div class="sig-name"><?= $penggunaTujuan ?></div>
        <div class="sig-role">Pemegang Baru</div>
      </div>
      <div class="col-3 sig-box">
        <div class="text-muted small">Petugas Pelaksana IT,</div>
        <div class="sig-space"></div>
        <div class="sig-name"><?= $petugas ?></div>
        <div class="sig-role">Staf IT Operations</div>
      </div>
      <div class="col-3 sig-box">
        <div class="text-muted small">Mengetahui,</div>
        <div class="sig-space"></div>
        <div class="sig-name">( .................................... )</div>
        <div class="sig-role">Pimpinan Kantor Cabang</div>
      </div>
    </div>
  </div>

</div>

</body>
</html>
