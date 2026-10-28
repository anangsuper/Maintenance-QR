<?php
/**
 * Modul Khusus: Mutasi & Pemindahan Barang Inventaris / Sarpras
 * PT BPR Mitratama Arthabuana
 * Dikhususkan untuk Bagian Umum, SDM/HRD, dan Pimpinan Operasional
 */

require __DIR__ . '/bootstrap.php';
require_login();

// Validasi hak akses: IT, SDM/HRD, dan Admin
if (!is_it() && !is_hrd()) {
    require_admin();
}

$error = '';
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$selectedId = (int)($_GET['id'] ?? 0);

// Ambil master data untuk form mutasi inventaris
$cabangList = get_cabang_list();
$masterRuanganList = function_exists('get_master_ruangan_list') ? get_master_ruangan_list() : [];
$masterGolonganList = function_exists('get_master_golongan_inventaris') ? get_master_golongan_inventaris() : [];
$masterJenisList = function_exists('get_master_jenis_inventaris') ? get_master_jenis_inventaris() : [];

// AJAX Endpoint: Ambil katalog seluruh inventaris aktif secara asinkron
if (isset($_GET['action']) && $_GET['action'] === 'get_inventory_catalog') {
    header('Content-Type: application/json; charset=utf-8');
    $catalog = [];
    $stats = [];
    foreach ($cabangList as $c) {
        $cName = $c['nama_cabang'] ?? '';
        if ($cName !== '') $stats[$cName] = 0;
    }
    try {
        $st = db()->query("
            SELECT id, nomor_rekening, nama_barang, lokasi, ruangan, pengguna, golongan, jenis_inventaris, nomor_seri_inventaris, tanggal_perolehan, status_inventaris
            FROM inventaris_kartu
            WHERE (status_inventaris IS NULL OR (
                status_inventaris NOT LIKE '%Dihapuskan%'
                AND status_inventaris NOT LIKE '%Dijual%'
                AND status_inventaris NOT LIKE '%Afkir%'
            ))
            ORDER BY id DESC
        ");
        while ($ir = $st->fetch(PDO::FETCH_ASSOC)) {
            $aid = (int)$ir['id'];
            $kKode = (string)($ir['nomor_rekening'] ?? ('INV-' . $aid));
            $kNama = (string)($ir['nama_barang'] ?? 'Aset Inventaris');
            $kCabang = resolve_cabang_match($kKode, (string)($ir['lokasi'] ?? ''), '', $cabangList);
            $kRuangan = (string)(!empty($ir['ruangan']) ? $ir['ruangan'] : (!empty($ir['lokasi']) ? $ir['lokasi'] : '-'));
            $kPengguna = (string)(!empty($ir['pengguna']) ? $ir['pengguna'] : 'Umum / Pool');
            $kGolongan = (string)(!empty($ir['golongan']) ? $ir['golongan'] : '-');
            $kJenis = (string)(!empty($ir['jenis_inventaris']) ? $ir['jenis_inventaris'] : 'Peralatan / Fasilitas');
            $kDivisi = $kGolongan !== '-' ? $kGolongan : $kJenis;
            $kSn = (string)($ir['nomor_seri_inventaris'] ?? '');
            $kTgl = (string)($ir['tanggal_perolehan'] ?? '');
            $kKategori = $kJenis ?: ($kGolongan !== '-' ? $kGolongan : 'Inventaris Kantor');

            $item = [
                'id' => $aid,
                'kode' => $kKode,
                'nama' => $kNama,
                'cabang' => $kCabang,
                'ruangan' => $kRuangan,
                'pengguna' => $kPengguna,
                'divisi' => $kDivisi,
                'golongan' => $kGolongan,
                'jenis' => $kJenis,
                'sn' => $kSn,
                'tgl' => $kTgl,
                'kategori' => $kKategori,
                'search_text' => strtolower($kKode . ' ' . $kNama . ' ' . $kCabang . ' ' . $kRuangan . ' ' . $kPengguna . ' ' . $kDivisi . ' ' . $kSn . ' ' . $kKategori)
            ];
            $catalog[] = $item;
            $stats[$kCabang] = ($stats[$kCabang] ?? 0) + 1;
        }
    } catch (Throwable $e) {}

    echo json_encode(['success' => true, 'data' => $catalog, 'stats' => $stats], JSON_UNESCAPED_UNICODE);
    exit;
}

// Handle Submit POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $assetId = (int)($_POST['asset_id'] ?? 0);
    $cabangTujuan = trim((string)($_POST['cabang_tujuan'] ?? ''));
    $ruanganTujuan = trim((string)($_POST['ruangan_tujuan'] ?? ''));
    $penggunaTujuan = trim((string)($_POST['pengguna_tujuan'] ?? ''));
    $golonganTujuan = trim((string)($_POST['golongan_tujuan'] ?? ''));
    $kondisiFisik = trim((string)($_POST['kondisi_fisik'] ?? 'Baik / Utuh'));
    $alasan = trim((string)($_POST['alasan_mutasi'] ?? ''));
    $keterangan = trim((string)($_POST['keterangan'] ?? ''));

    if (empty($penggunaTujuan)) {
        $penggunaTujuan = 'Umum / Pool';
    }

    if ($assetId <= 0) {
        $error = 'Silakan pilih barang inventaris yang akan dipindahkan/dimutasi.';
    } elseif ($cabangTujuan === '') {
        $error = 'Kantor Cabang tujuan penempatan wajib dipilih.';
    } elseif ($alasan === '') {
        $error = 'Alasan mutasi pemindahan barang wajib dipilih.';
    } else {
        $result = process_asset_mutation([
            'asset_id'        => $assetId,
            'asset_type'      => 'inventaris',
            'cabang_tujuan'   => $cabangTujuan,
            'ruangan_tujuan'  => $ruanganTujuan,
            'pengguna_tujuan' => $penggunaTujuan,
            'divisi_tujuan'   => $golonganTujuan,
            'golongan_tujuan' => $golonganTujuan,
            'alasan_mutasi'   => $alasan,
            'kondisi_fisik'   => $kondisiFisik,
            'keterangan'      => $keterangan,
            'petugas_nama'    => $_SESSION['nama'] ?? 'Bagian Umum & SDM'
        ]);

        if (!empty($result['success'])) {
            $_SESSION['flash'] = 'Mutasi inventaris berhasil diproses dan dicatat ke log sistem.';
            header('Location: ' . module_url('print_bast.php', ['id' => $result['id']]));
            exit;
        } else {
            $error = $result['error'] ?? 'Gagal memproses mutasi inventaris.';
        }
    }
}

// Ambil total inventaris aktif secara instan tanpa menarik 535 row ke memori
$totalAssetCount = 0;
try {
    $totalAssetCount = (int)db()->query("
        SELECT COUNT(*) FROM inventaris_kartu
        WHERE (status_inventaris IS NULL OR (
            status_inventaris NOT LIKE '%Dihapuskan%'
            AND status_inventaris NOT LIKE '%Dijual%'
            AND status_inventaris NOT LIKE '%Afkir%'
        ))
    ")->fetchColumn();
} catch (Throwable $e) {}

// Data aset awal jika pre-selected melalui parameter GET ?id=
$currentCabang = '';
$currentRuangan = '';
$currentPengguna = '';
$currentDivisi = '';
$currentGolongan = '';
$currentKode = '';
$currentNama = '';
$currentSn = '';
$selectedAsset = null;

if ($selectedId > 0) {
    try {
        $st = db()->prepare("
            SELECT id, nomor_rekening, nama_barang, lokasi, ruangan, pengguna, golongan, jenis_inventaris, nomor_seri_inventaris, tanggal_perolehan
            FROM inventaris_kartu
            WHERE id = ? LIMIT 1
        ");
        $st->execute([$selectedId]);
        $ir = $st->fetch(PDO::FETCH_ASSOC);
        if ($ir) {
            $aid = (int)$ir['id'];
            $kKode = (string)($ir['nomor_rekening'] ?? ('INV-' . $aid));
            $kNama = (string)($ir['nama_barang'] ?? 'Aset Inventaris');
            $kCabang = resolve_cabang_match($kKode, (string)($ir['lokasi'] ?? ''), '', $cabangList);
            $kRuangan = (string)(!empty($ir['ruangan']) ? $ir['ruangan'] : (!empty($ir['lokasi']) ? $ir['lokasi'] : '-'));
            $kPengguna = (string)(!empty($ir['pengguna']) ? $ir['pengguna'] : 'Umum / Pool');
            $kGolongan = (string)(!empty($ir['golongan']) ? $ir['golongan'] : '-');
            $kJenis = (string)(!empty($ir['jenis_inventaris']) ? $ir['jenis_inventaris'] : 'Peralatan / Fasilitas');
            $kDivisi = $kGolongan !== '-' ? $kGolongan : $kJenis;
            $kSn = (string)($ir['nomor_seri_inventaris'] ?? '');
            $kTgl = (string)($ir['tanggal_perolehan'] ?? '');
            $kKategori = $kJenis ?: ($kGolongan !== '-' ? $kGolongan : 'Inventaris Kantor');

            $selectedAsset = [
                'id' => $aid,
                'kode' => $kKode,
                'nama' => $kNama,
                'cabang' => $kCabang,
                'ruangan' => $kRuangan,
                'pengguna' => $kPengguna,
                'divisi' => $kDivisi,
                'golongan' => $kGolongan,
                'jenis' => $kJenis,
                'sn' => $kSn,
                'tgl' => $kTgl,
                'kategori' => $kKategori,
                'search_text' => strtolower($kKode . ' ' . $kNama . ' ' . $kCabang . ' ' . $kRuangan . ' ' . $kPengguna . ' ' . $kDivisi . ' ' . $kSn . ' ' . $kKategori)
            ];

            $currentKode = $kKode;
            $currentNama = $kNama;
            $currentCabang = $kCabang;
            $currentRuangan = $kRuangan;
            $currentPengguna = $kPengguna;
            $currentDivisi = $kDivisi;
            $currentGolongan = $kGolongan;
            $currentSn = $kSn;
        }
    } catch (Throwable $e) {}
}

$head = '
<style>
/* Kombai Design System - Mutasi Inventaris */
.mutation-card {
  background: #FFFFFF;
  border: 1px solid #D1D5D8;
  border-radius: 10px;
  overflow: hidden;
  height: 100%;
  display: flex;
  flex-direction: column;
}
.mutation-card-header {
  padding: 16px 20px;
  background: #FFFFFF;
  border-bottom: 1px solid #D1D5D8;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.mutation-card-header h5 {
  font-size: 15px;
  font-weight: 700;
  margin: 0;
  color: #1B2A3A;
  display: flex;
  align-items: center;
  gap: 8px;
}
.mutation-card-body {
  padding: 20px;
  flex: 1;
}

.step-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  background: #0E5C58;
  color: #FFFFFF;
}

/* Location Summary Box */
.location-box {
  background: #F4F5F7;
  border: 1px solid #D1D5D8;
  border-radius: 8px;
  padding: 16px;
  transition: border-color 0.2s ease, background 0.2s ease;
}
.location-box.border-success {
  border-color: #0E5C58 !important;
  background: #F8FAF9;
}

/* Info Box */
.info-bast {
  background: #D9F1EF;
  border: 1px solid #A7E2DC;
  border-radius: 8px;
  padding: 12px 14px;
  font-size: 12px;
  color: #0E5C58;
  margin-top: 16px;
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

/* Form Controls */
.form-control, .form-select {
  border-color: #D1D5D8;
  color: #1B2A3A;
  font-size: 13px;
  border-radius: 8px;
}
.form-control:focus, .form-select:focus {
  border-color: #0E5C58;
  box-shadow: 0 0 0 2px rgba(14, 92, 88, 0.15);
}

/* Buttons */
.btn-primary-teal {
  background: #0E5C58;
  color: #FFFFFF;
  font-weight: 600;
  border: none;
  border-radius: 8px;
  transition: background-color 0.15s ease;
}
.btn-primary-teal:hover {
  background: #094340;
  color: #FFFFFF;
}

.btn-outline-teal {
  background: #FFFFFF;
  color: #0E5C58;
  border: 1px solid #0E5C58;
  font-weight: 600;
  border-radius: 8px;
  transition: all 0.15s ease;
}
.btn-outline-teal:hover {
  background: #D9F1EF;
  color: #0E5C58;
}

.btn-cancel-kombai {
  background: #FFFFFF;
  color: #53595F;
  border: 1px solid #D1D5D8;
  font-weight: 500;
  border-radius: 8px;
  font-size: 13px;
  transition: background-color 0.15s ease;
}
.btn-cancel-kombai:hover {
  background: #F4F5F7;
  color: #1B2A3A;
}

/* Table in Modal */
.table-catalog-asset thead th {
  background: #F4F5F7;
  color: #53595F;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 1px solid #D1D5D8;
  padding: 10px 14px;
}
.table-catalog-asset tbody td {
  padding: 10px 14px;
  font-size: 13px;
  color: #1B2A3A;
  border-bottom: 1px solid #F0F2F4;
}
.table-catalog-asset tbody tr {
  cursor: pointer;
  transition: background 0.15s ease;
}
.table-catalog-asset tbody tr:hover {
  background-color: #F8FAF9 !important;
}
.table-catalog-asset tbody tr.table-success {
  background-color: #D9F1EF !important;
}
</style>
';

$errorHtml = $error ? '<div class="alert alert-danger alert-dismissible fade show border-0 shadow-xs mb-4" style="background:#FDE8E8; color:#9B1C1C; border-left: 4px solid #E02424 !important; border-radius: 8px;"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>'.e($error).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$cancelUrl = module_url('dashboard.php', ['tab' => 'kartu']);

$body = '
<!-- Page Heading Row -->
<div class="mb-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
  <div>
    <div class="text-[11px] font-bold text-[#0E5C58] uppercase tracking-wider mb-1">Sarana Prasarana &amp; Fasilitas</div>
    <h1 class="text-[22px] font-bold text-[#1B2A3A] tracking-tight m-0">Mutasi &amp; Pemindahan Barang Inventaris</h1>
    <p class="text-[13px] text-[#53595F] mt-1 mb-0">Pencatatan perpindahan fisik perabot, mebel, dan fasilitas gedung serta penerbitan BAST resmi.</p>
  </div>
  <div class="flex flex-wrap items-center gap-2 shrink-0">
    <a href="'.e(module_url('asset_mutation.php')).'" class="h-[36px] px-3.5 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs" title="Buka mutasi khusus perangkat komputer">
      <i class="bi bi-pc-display text-[#53595F]"></i> Ke Mutasi Komputer IT
    </a>
    <a href="'.e(module_url('asset_mutations.php', ['type' => 'inventaris'])).'" class="h-[36px] px-3.5 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-journal-text text-[#53595F]"></i> Riwayat Mutasi
    </a>
    <a href="'.e($cancelUrl).'" class="h-[36px] px-3.5 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-arrow-left text-[#53595F]"></i> Kembali ke Kartu
    </a>
  </div>
</div>

'.$errorHtml.'

<form method="post">
  <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
  <input type="hidden" name="asset_type" value="inventaris">

  <div class="row g-4">
    <!-- Kolom Kiri: Pemilihan & Informasi Barang Saat Ini -->
    <div class="col-lg-5">
      <div class="mutation-card">
        <div class="mutation-card-header">
          <h5>
            <span class="step-badge">1</span>
            <i class="bi bi-archive text-[#0E5C58]"></i> Barang Inventaris yang Dimutasi
          </h5>
          <span class="badge bg-[#D9F1EF] text-[#0E5C58] px-2.5 py-1 text-[11px] font-semibold border-0">
            <i class="bi bi-card-heading me-1"></i>Kartu Inventaris
          </span>
        </div>
        <div class="mutation-card-body">

        <!-- Tombol Utama: Buka Katalog Modal Pencarian -->
        <div class="mb-3">
          <label class="form-label text-uppercase fw-bold text-muted small mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">Cari Melalui Katalog Lengkap</label>
          <button type="button" class="btn btn-outline-teal w-100 d-flex align-items-center justify-content-between py-2 px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#modalPilihAset" onclick="ensureCatalogLoaded(renderModalRows)">
            <span class="d-flex align-items-center gap-2">
              <i class="bi bi-grid-3x3-gap-fill text-[#0E5C58]"></i>
              <span class="text-[#0E5C58] fw-bold">Buka Katalog &amp; Cari Barang</span>
            </span>
            <span class="badge bg-[#D9F1EF] text-[#0E5C58] font-bold">'.number_format($totalAssetCount).' Barang</span>
          </button>
        </div>

        <div class="d-flex align-items-center text-center my-3">
          <span class="flex-grow-1 border-bottom" style="border-color: #E2E8F0 !important;"></span>
          <span class="px-2 text-uppercase fw-semibold" style="font-size: 0.68rem; color: #53595F;">Atau Pilih Cepat di Bawah</span>
          <span class="flex-grow-1 border-bottom" style="border-color: #E2E8F0 !important;"></span>
        </div>

        <!-- Filter Cepat: Cabang & Ketik Pencarian -->
        <div class="row g-2 mb-2">
          <div class="col-sm-6">
            <label class="form-label text-uppercase fw-bold text-muted small mb-1" style="font-size: 0.68rem;">Filter Cabang Asal</label>
            <select id="filterCabangAsal" class="form-select form-select-sm" onchange="filterAssetList()" onfocus="ensureCatalogLoaded()">
              <option value="">Semua Cabang ('.number_format($totalAssetCount).' Unit)</option>';

foreach ($cabangList as $c) {
    $cName = $c['nama_cabang'] ?? '';
    $body .= '<option value="'.e($cName).'">'.e($cName).'</option>';
}

$body .= '
            </select>
          </div>
          <div class="col-sm-6">
            <label class="form-label text-uppercase fw-bold text-muted small mb-1" style="font-size: 0.68rem;">Cari Nama / No. Rek / Ruangan</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
              <input type="text" id="quickSearchAsset" class="form-control border-start-0" placeholder="Ketik kata kunci..." oninput="filterAssetList()" onfocus="ensureCatalogLoaded()" autocomplete="off">
              <button class="btn btn-outline-secondary" type="button" onclick="resetQuickFilter()" title="Reset pencarian"><i class="bi bi-x"></i></button>
            </div>
          </div>
        </div>

        <!-- Dropdown Seleksi Barang Inventaris -->
        <div class="mb-3">
          <label class="form-label text-uppercase fw-bold text-muted small mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Pilih Barang / Unit Inventaris <span class="text-danger">*</span></label>
          <select name="asset_id" id="selectAssetId" class="form-select" onchange="onAssetChange(this.value)" onfocus="ensureCatalogLoaded()" required style="border-radius: 8px; font-weight: 500;">
            <option value="">-- Pilih Barang Terdaftar --</option>';

if ($selectedAsset) {
    $ruangInfo = ($selectedAsset['ruangan'] !== '-' && $selectedAsset['ruangan'] !== '') ? $selectedAsset['ruangan'] . ' | ' : '';
    $label = $selectedAsset['kode'] . ' - ' . $selectedAsset['nama'] . ' (' . $ruangInfo . $selectedAsset['cabang'] . ')';
    $body .= '<option value="'.$selectedAsset['id'].'" selected>'.e($label).'</option>';
}

$body .= '
          </select>
          <div class="d-flex justify-content-between align-items-center mt-1">
            <span class="form-text small text-muted" style="font-size: 0.72rem;" id="matchCountText">Total '.number_format($totalAssetCount).' barang terdaftar di sistem</span>
            <span class="text-muted small" style="font-size: 0.72rem;"><i class="bi bi-shield-check text-[#0E5C58] me-1"></i>Hanya barang aktif</span>
          </div>
        </div>

        <!-- Info Box Kondisi Asal Terpilih -->
        <div class="location-box mt-2" id="lokasiAsalBox">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="badge bg-[#D9F1EF] text-[#0E5C58] px-2.5 py-1 text-[11px] font-bold tracking-wider">LOKASI &amp; RUANGAN ASAL</span>
            <span class="text-muted small" style="font-size: 0.72rem;"><i class="bi bi-shield-check text-[#0E5C58] me-1"></i>Terverifikasi</span>
          </div>
          
          <div class="mb-2">
            <div class="small text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 600;">NOMOR REKENING &amp; NAMA BARANG:</div>
            <div class="fw-bold font-monospace text-[#1B2A3A] fs-6" id="displayKode">'.e($currentKode ?: 'Belum dipilih').'</div>
            <div class="text-[#1B2A3A] small fw-semibold" id="displayNama">'.e($currentNama).'</div>
            <div class="small font-monospace text-muted mt-0.5" id="displaySnWrapper" style="'.($currentSn ? '' : 'display:none;').'">
              <span>No. Seri:</span> <span id="displaySn">'.e($currentSn).'</span>
            </div>
          </div>
          
          <hr class="my-2 border-secondary opacity-15">

          <div class="row g-2 small">
            <div class="col-6">
              <span class="text-muted d-block" style="font-size: 0.7rem;">KANTOR CABANG ASAL:</span>
              <strong class="text-[#1B2A3A]" id="displayCabang">'.e($currentCabang ?: '-').'</strong>
            </div>
            <div class="col-6">
              <span class="text-muted d-block" style="font-size: 0.7rem;">RUANGAN SAAT INI:</span>
              <strong class="text-[#1B2A3A]" id="displayRuangan">'.e($currentRuangan ?: '-').'</strong>
            </div>
            <div class="col-6 mt-1">
              <span class="text-muted d-block" style="font-size: 0.7rem;">PENANGGUNG JAWAB ASAL:</span>
              <strong class="text-[#1B2A3A]" id="displayPengguna">'.e($currentPengguna ?: '-').'</strong>
            </div>
            <div class="col-6 mt-1">
              <span class="text-muted d-block" style="font-size: 0.7rem;">GOLONGAN / KATEGORI:</span>
              <strong class="text-[#1B2A3A]" id="displayDivisi">'.e($currentDivisi ?: '-').'</strong>
            </div>
          </div>
        </div>

        <div class="info-bast">
          <i class="bi bi-info-circle-fill text-[#0E5C58] me-1 shrink-0"></i>
          <span>Setelah mutasi diproses, sistem otomatis memperbarui lokasi kartu inventaris, mencatat log audit, dan menerbitkan <strong>BAST Inventaris</strong> resmi.</span>
        </div>
        </div><!-- /mutation-card-body -->
      </div><!-- /mutation-card left -->
    </div>

    <!-- Kolom Kanan: Tujuan Baru & Kondisi Pemindahan -->
    <div class="col-lg-7">
      <div class="mutation-card">
        <div class="mutation-card-header">
          <h5>
            <span class="step-badge">2</span>
            <i class="bi bi-geo-alt text-[#0E5C58]"></i> Tujuan Penempatan &amp; Kondisi Fisik Barang
          </h5>
          <span class="badge bg-[#D9F1EF] text-[#0E5C58] px-2.5 py-1 text-[11px] font-semibold border-0">
            <i class="bi bi-building-check me-1"></i>Fasilitas / Sarpras
          </span>
        </div>
        <div class="mutation-card-body">
        <div class="row g-3">

          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Kantor Cabang Tujuan <span class="text-danger">*</span></label>
            <select name="cabang_tujuan" id="cabangTujuanSelect" class="form-select" required>
              <option value="">-- Pilih Cabang Tujuan --</option>';

foreach ($cabangList as $c) {
    $cName = $c['nama_cabang'] ?? '';
    $body .= '<option value="'.e($cName).'">'.e($cName).'</option>';
}

$body .= '
            </select>
            <div class="form-text small text-muted" style="font-size: 0.72rem;">Kantor cabang bank penerima fisik barang inventaris.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Ruangan Penempatan Baru <span class="text-danger">*</span></label>
            <input type="text" list="listMasterRuangan" class="form-control" name="ruangan_tujuan" id="inputRuanganTujuan" placeholder="Pilih atau ketik ruangan baru..." required>
            <datalist id="listMasterRuangan">';

foreach ($masterRuanganList as $mr) {
    $kode = is_array($mr) ? ($mr['kode_ruangan'] ?? '') : '';
    $nama = is_array($mr) ? ($mr['nama_ruangan'] ?? '') : (string)$mr;
    $label = ($kode !== '') ? sprintf('%02d : %s', (int)$kode, $nama) : $nama;
    $body .= '<option value="'.e($label).'">';
}

$body .= '
            </datalist>
            <div class="form-text small text-muted" style="font-size: 0.72rem;">Pilih master ruangan resmi atau ketik nama ruangan baru.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Penanggung Jawab / PIC Baru <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="pengguna_tujuan" id="inputPenggunaTujuan" value="Umum / Pool" placeholder="Contoh: Umum / Pool atau Nama Kepala Ruangan" required>
            <div class="form-text small text-muted" style="font-size: 0.72rem;">Default "Umum / Pool" untuk fasilitas bersama ruangan/cabang.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Golongan Inventaris</label>
            <select name="golongan_tujuan" id="selectGolonganTujuan" class="form-select">
              <option value="">-- Tetap Sesuai Golongan Saat Ini --</option>';

foreach ($masterGolonganList as $k => $val) {
    $body .= '<option value="'.e($val).'">'.e($val).'</option>';
}

$body .= '
            </select>
            <div class="form-text small text-muted" style="font-size: 0.72rem;">Klasifikasi aktiva inventaris (otomatis mengikuti kartu).</div>
          </div>

          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Kondisi Fisik Saat Mutasi <span class="text-danger">*</span></label>
            <select name="kondisi_fisik" class="form-select" required>
              <option value="Baik / Utuh" selected>Baik / Utuh / Normal (Siap Pakai)</option>
              <option value="Rusak Ringan">Rusak Ringan (Perlu Rekondisi Ringan)</option>
              <option value="Rusak Berat">Rusak Berat (Disimpan di Gudang / Pengajuan Afkir)</option>
            </select>
            <div class="form-text small text-muted" style="font-size: 0.72rem;">Kondisi fisik barang saat serah terima antar unit/ruangan.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Alasan Mutasi Pemindahan Barang <span class="text-danger">*</span></label>
            <select name="alasan_mutasi" class="form-select" required>
              <option value="">-- Pilih Alasan Mutasi --</option>';

$reasonsInv = [
    'Relokasi Kantor / Renovasi Ruangan',
    'Penataan Ulang Fasilitas / Layout Ruangan',
    'Kebutuhan Penambahan Fasilitas di Kantor Cabang',
    'Rotasi / Perpindahan Fasilitas Antar Cabang',
    'Penarikan ke Gudang / Pool Kantor Pusat',
    'Peremajaan / Penggantian Fisik Barang',
    'Lainnya'
];
foreach ($reasonsInv as $r) {
    $body .= '<option value="'.e($r).'">'.e($r).'</option>';
}

$body .= '
            </select>
            <div class="form-text small text-muted" style="font-size: 0.72rem;">Keperluan resmi pemindahan fisik barang.</div>
          </div>

          <div class="col-12">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Catatan / Spesifikasi &amp; Kondisi Fisik Barang</label>
            <textarea class="form-control" name="keterangan" rows="3" placeholder="Contoh: Kondisi fisik utuh dan baik, kunci laci/lemari disertakan lengkap, tidak ada cacat atau kerusakan fisik."></textarea>
            <div class="form-text small text-muted" style="font-size: 0.72rem;">Catat kondisi fisik barang atau kelengkapan kunci/laci sebelum serah terima.</div>
          </div>

        </div>

        <hr class="my-4 border-secondary opacity-15">

        <div class="d-flex justify-content-between align-items-center">
          <a href="'.e($cancelUrl).'" class="btn btn-cancel-kombai px-4 py-2">
            <i class="bi bi-x-circle me-1"></i> Batal
          </a>
          <button type="submit" class="btn btn-primary-teal px-4 py-2 d-inline-flex align-items-center gap-2 shadow-xs">
            <i class="bi bi-check-circle-fill"></i> Proses Mutasi &amp; Terbitkan BAST
          </button>
        </div>
        </div><!-- /mutation-card-body -->
      </div><!-- /mutation-card -->
    </div>
  </div>
</form>

<!-- Modal Pop-up: Katalog Pemilihan Barang Inventaris -->
<div class="modal fade" id="modalPilihAset" tabindex="-1" aria-labelledby="modalPilihAsetLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
      <div class="modal-header border-bottom py-3 px-4 bg-white" style="border-color: #D1D5D8 !important;">
        <div>
          <h5 class="modal-title font-bold text-[16px] text-[#1B2A3A] d-flex align-items-center gap-2 m-0" id="modalPilihAsetLabel">
            <i class="bi bi-grid-3x3-gap-fill text-[#0E5C58]"></i> Katalog Pemilihan Barang Inventaris
          </h5>
          <p class="mb-0 text-muted small text-[12px]">Pilih barang inventaris yang ingin dimutasi ke ruangan atau cabang lain.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-4 bg-[#F4F5F7]">
        <!-- Pencarian & Filter Cepat Cabang -->
        <div class="row g-2 mb-3">
          <div class="col-md-7">
            <div class="input-group">
              <span class="input-group-text bg-white border-end-0 text-[#0E5C58]" style="border-color: #D1D5D8;"><i class="bi bi-search"></i></span>
              <input type="text" id="modalSearchInput" class="form-control border-start-0" placeholder="Ketik nama barang, nomor rekening inventaris, ruangan..." oninput="filterModalTable()" autocomplete="off" style="border-color: #D1D5D8;">
              <button class="btn btn-outline-secondary" type="button" onclick="clearModalSearch()"><i class="bi bi-x-circle me-1"></i>Reset</button>
            </div>
          </div>
          <div class="col-md-5">
            <select id="modalBranchFilter" class="form-select" onchange="filterModalTable()" style="border-color: #D1D5D8;">
              <option value="">Semua Kantor Cabang ('.number_format($totalAssetCount).' Unit)</option>';

foreach ($cabangList as $c) {
    $cName = $c['nama_cabang'] ?? '';
    $body .= '<option value="'.e($cName).'">'.e($cName).'</option>';
}

$body .= '
            </select>
          </div>
        </div>

        <!-- Tabel Katalog Barang -->
        <div class="table-responsive bg-white border rounded-[8px]" style="border-color: #D1D5D8 !important; max-height: 480px;">
          <table class="table table-catalog-asset align-middle mb-0" id="tableCatalogAsset">
            <thead class="sticky-top" style="z-index: 2;">
              <tr>
                <th style="width: 45px;" class="text-center font-monospace small">NO</th>
                <th style="width: 170px;">NOMOR REKENING</th>
                <th>NAMA BARANG &amp; GOLONGAN</th>
                <th style="width: 170px;">CABANG &amp; RUANGAN</th>
                <th style="width: 150px;">PENANGGUNG JAWAB</th>
                <th style="width: 120px;" class="text-center">AKSI</th>
              </tr>
            </thead>
            <tbody id="catalogTableBody">
              <!-- Render via JavaScript -->
            </tbody>
          </table>
        </div>
      </div>

      <div class="modal-footer bg-white border-top py-2.5 px-4 d-flex justify-content-between" style="border-color: #D1D5D8 !important;">
        <span class="text-muted small" id="modalAssetCount">Menampilkan '.number_format($totalAssetCount).' unit</span>
        <button type="button" class="btn btn-cancel-kombai px-4" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
';

$extraScript = '
<script>
let ALL_ASSETS = ' . json_encode($selectedAsset ? [$selectedAsset] : [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';
let ASSETS_MAP = ' . json_encode($selectedAsset ? [$selectedId => $selectedAsset] : (object)[], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';
let currentSelectedId = ' . (int)$selectedId . ';
let selectedModalBranch = "";
let isCatalogLoaded = false;
let isLoadingCatalog = false;
const MAX_CATALOG_DISPLAY = 60;

function ensureCatalogLoaded(callback) {
  if (isCatalogLoaded) {
    if (typeof callback === "function") callback();
    return;
  }
  if (isLoadingCatalog) {
    if (typeof callback === "function") {
      var checkTimer = setInterval(function() {
        if (isCatalogLoaded) {
          clearInterval(checkTimer);
          callback();
        }
      }, 100);
    }
    return;
  }
  isLoadingCatalog = true;

  var tbody = document.getElementById("catalogTableBody");
  if (tbody && (!ALL_ASSETS || ALL_ASSETS.length === 0)) {
    tbody.innerHTML = "<tr><td colspan=\"6\" class=\"text-center py-5 text-muted\"><div class=\"spinner-border spinner-border-sm text-success me-2\"></div>Memuat katalog barang inventaris...</td></tr>";
  }

  fetch("inventaris_mutation.php?action=get_inventory_catalog")
    .then(function(res) {
      if (!res.ok) throw new Error("HTTP " + res.status);
      return res.json();
    })
    .then(function(json) {
      if (json && json.data) {
        ALL_ASSETS = json.data;
        ASSETS_MAP = {};
        for (var i = 0; i < ALL_ASSETS.length; i++) {
          ASSETS_MAP[ALL_ASSETS[i].id] = ALL_ASSETS[i];
        }
        isCatalogLoaded = true;
        isLoadingCatalog = false;

        if (json.stats) {
          updateBranchFilterCounts(json.stats);
        }
        populateQuickSelectOptions();

        if (typeof callback === "function") {
          callback();
        } else {
          renderModalRows();
        }
      }
    })
    .catch(function(err) {
      isLoadingCatalog = false;
      if (tbody) {
        tbody.innerHTML = "<tr><td colspan=\"6\" class=\"text-center py-4 text-danger\"><i class=\"bi bi-exclamation-triangle me-1\"></i> Gagal memuat katalog: " + escapeHtml(err.message || "Koneksi terputus") + "<br><button class=\"btn btn-outline-success btn-sm mt-2\" onclick=\"ensureCatalogLoaded(renderModalRows)\"><i class=\"bi bi-arrow-clockwise me-1\"></i> Coba Lagi</button></td></tr>";
      }
    });
}

function updateBranchFilterCounts(stats) {
  var bSelect = document.getElementById("filterCabangAsal");
  var mSelect = document.getElementById("modalBranchFilter");
  [bSelect, mSelect].forEach(function(sel) {
    if (!sel) return;
    for (var i = 1; i < sel.options.length; i++) {
      var opt = sel.options[i];
      var cName = opt.value;
      var cCount = stats[cName] !== undefined ? stats[cName] : 0;
      opt.textContent = cName + " (" + cCount + " unit)";
    }
  });
}

function populateQuickSelectOptions() {
  var sel = document.getElementById("selectAssetId");
  if (!sel || ALL_ASSETS.length === 0) return;

  var currentVal = parseInt(sel.value) || currentSelectedId;
  var html = "<option value=\"\">-- Pilih Barang Terdaftar --</option>";

  for (var i = 0; i < ALL_ASSETS.length; i++) {
    var a = ALL_ASSETS[i];
    var isSel = (a.id === currentVal) ? " selected" : "";
    var rInfo = (a.ruangan && a.ruangan !== "-") ? a.ruangan + " | " : "";
    var label = a.kode + " - " + a.nama + " (" + rInfo + a.cabang + ")";
    html += "<option value=\"" + a.id + "\"" + isSel + ">" + escapeHtml(label) + "</option>";
  }

  sel.innerHTML = html;
  if (currentVal > 0) sel.value = currentVal;
}

function onAssetChange(assetId) {
  assetId = parseInt(assetId) || 0;
  currentSelectedId = assetId;

  const data = ASSETS_MAP[assetId];
  const box = document.getElementById("lokasiAsalBox");

  if (!data) {
    document.getElementById("displayKode").textContent = "Belum dipilih";
    document.getElementById("displayNama").textContent = "";
    document.getElementById("displayCabang").textContent = "-";
    document.getElementById("displayRuangan").textContent = "-";
    document.getElementById("displayPengguna").textContent = "-";
    document.getElementById("displayDivisi").textContent = "-";
    document.getElementById("displaySnWrapper").style.display = "none";
    if (box) box.classList.remove("border-success");
    return;
  }

  document.getElementById("displayKode").textContent = data.kode || "-";
  document.getElementById("displayNama").textContent = data.nama || "-";
  document.getElementById("displayCabang").textContent = data.cabang || "-";
  document.getElementById("displayRuangan").textContent = data.ruangan || "-";
  document.getElementById("displayPengguna").textContent = data.pengguna || "-";
  document.getElementById("displayDivisi").textContent = data.divisi || "-";

  if (data.sn && data.sn !== "-" && data.sn.trim() !== "") {
    document.getElementById("displaySn").textContent = data.sn;
    document.getElementById("displaySnWrapper").style.display = "block";
  } else {
    document.getElementById("displaySnWrapper").style.display = "none";
  }

  if (box) box.classList.add("border-success");

  // Auto-select golongan tujuan sesuai golongan barang
  const golSelect = document.getElementById("selectGolonganTujuan");
  if (golSelect && data.golongan && data.golongan !== "-") {
    for (let i = 0; i < golSelect.options.length; i++) {
      if (golSelect.options[i].value.toLowerCase().trim() === data.golongan.toLowerCase().trim()) {
        golSelect.selectedIndex = i;
        break;
      }
    }
  }

  if (isCatalogLoaded) {
    renderModalRows();
  }
}

function selectAssetAndCloseModal(assetId) {
  const sel = document.getElementById("selectAssetId");
  if (sel) {
    var found = false;
    for (var j = 0; j < sel.options.length; j++) {
      if (parseInt(sel.options[j].value) === assetId) {
        sel.selectedIndex = j;
        found = true;
        break;
      }
    }
    if (!found && ASSETS_MAP[assetId]) {
      var item = ASSETS_MAP[assetId];
      var rInfo = (item.ruangan && item.ruangan !== "-") ? item.ruangan + " | " : "";
      var opt = document.createElement("option");
      opt.value = item.id;
      opt.text = item.kode + " - " + item.nama + " (" + rInfo + item.cabang + ")";
      opt.selected = true;
      sel.appendChild(opt);
    }
    onAssetChange(assetId);
  }
  const modalEl = document.getElementById("modalPilihAset");
  if (modalEl) {
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
  }
}

function renderModalRows() {
  const tbody = document.getElementById("catalogTableBody");
  if (!tbody) return;

  if (!isCatalogLoaded) {
    ensureCatalogLoaded(renderModalRows);
    return;
  }

  const q = (document.getElementById("modalSearchInput") ? document.getElementById("modalSearchInput").value : "").toLowerCase().trim();
  const branch = document.getElementById("modalBranchFilter") ? document.getElementById("modalBranchFilter").value : "";

  let matched = 0;
  let count = 0;
  let html = "";

  for (let i = 0; i < ALL_ASSETS.length; i++) {
    const item = ALL_ASSETS[i];

    if (branch !== "" && item.cabang.toLowerCase() !== branch.toLowerCase()) {
      continue;
    }
    if (q !== "" && item.search_text.indexOf(q) === -1) {
      continue;
    }

    matched++;
    if (count >= MAX_CATALOG_DISPLAY) {
      continue;
    }
    count++;

    const isSelected = (item.id === currentSelectedId);
    const rowClass = isSelected ? "table-success fw-semibold" : "";

    html += `
      <tr class="${rowClass}" onclick="selectAssetAndCloseModal(${item.id})">
        <td class="text-center font-monospace text-muted small">${count}</td>
        <td>
          <div class="fw-bold font-monospace text-success">${escapeHtml(item.kode)}</div>
          <div class="small text-muted">${item.sn ? "SN: " + escapeHtml(item.sn) : ""}</div>
        </td>
        <td>
          <div class="fw-bold text-dark">${escapeHtml(item.nama)}</div>
          <span class="badge bg-[#F4F5F7] text-[#53595F] border small mt-1">${escapeHtml(item.golongan || item.jenis)}</span>
        </td>
        <td>
          <div class="fw-semibold text-dark">${escapeHtml(item.cabang)}</div>
          <div class="small text-muted"><i class="bi bi-door-open me-1"></i>${escapeHtml(item.ruangan)}</div>
        </td>
        <td>
          <div class="text-dark small">${escapeHtml(item.pengguna)}</div>
        </td>
        <td class="text-center" onclick="event.stopPropagation()">
          <button type="button" class="btn btn-sm ${isSelected ? "btn-primary-teal" : "btn-outline-teal"} fw-bold px-3 py-1.5 shadow-sm" onclick="selectAssetAndCloseModal(${item.id})">
            <i class="bi ${isSelected ? "bi-check-circle-fill" : "bi-check-lg"} me-1"></i> ${isSelected ? "Terpilih" : "Pilih Barang"}
          </button>
        </td>
      </tr>
    `;
  }

  if (matched === 0) {
    html = `
      <tr>
        <td colspan="6" class="text-center py-5 text-muted">
          <i class="bi bi-search fs-2 d-block mb-2 opacity-50"></i>
          Tidak ada barang inventaris yang sesuai dengan pencarian.<br>
          <button type="button" class="btn btn-link btn-sm mt-2 text-decoration-none" onclick="clearModalSearch()">Reset Pencarian</button>
        </td>
      </tr>
    `;
  }

  tbody.innerHTML = html;
  const countEl = document.getElementById("modalAssetCount");
  if (countEl) {
    if (matched > MAX_CATALOG_DISPLAY) {
      countEl.textContent = `Menampilkan ${count} dari ${matched} barang inventaris yang cocok`;
    } else {
      countEl.textContent = `Menampilkan ${matched} dari ${ALL_ASSETS.length} barang inventaris`;
    }
  }
}

function filterModalTable() {
  renderModalRows();
}

function clearModalSearch() {
  if (document.getElementById("modalSearchInput")) document.getElementById("modalSearchInput").value = "";
  if (document.getElementById("modalBranchFilter")) document.getElementById("modalBranchFilter").value = "";
  renderModalRows();
}

function filterAssetList() {
  if (!isCatalogLoaded) {
    ensureCatalogLoaded(filterAssetList);
    return;
  }

  const branch = document.getElementById("filterCabangAsal") ? document.getElementById("filterCabangAsal").value.toLowerCase().trim() : "";
  const query = document.getElementById("quickSearchAsset") ? document.getElementById("quickSearchAsset").value.toLowerCase().trim() : "";
  const select = document.getElementById("selectAssetId");
  if (!select) return;

  let matched = 0;
  const options = select.options;

  for (let i = 1; i < options.length; i++) {
    const opt = options[i];
    const assetId = parseInt(opt.value);
    const data = ASSETS_MAP[assetId];
    if (!data) {
      opt.style.display = "";
      continue;
    }

    let show = true;
    if (branch !== "" && data.cabang.toLowerCase().trim() !== branch) {
      show = false;
    }
    if (show && query !== "" && data.search_text.indexOf(query) === -1) {
      show = false;
    }

    opt.style.display = show ? "" : "none";
    if (show) matched++;
  }

  const text = document.getElementById("matchCountText");
  if (text) {
    text.textContent = `Menampilkan ${matched} dari ${ALL_ASSETS.length} barang terdaftar`;
  }
}

function resetQuickFilter() {
  if (document.getElementById("filterCabangAsal")) document.getElementById("filterCabangAsal").value = "";
  if (document.getElementById("quickSearchAsset")) document.getElementById("quickSearchAsset").value = "";
  filterAssetList();
}

function escapeHtml(str) {
  if (!str) return "";
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/\x27/g, "&#039;");
}

document.addEventListener("DOMContentLoaded", function() {
  if (currentSelectedId > 0) {
    onAssetChange(currentSelectedId);
  }
  var modalEl = document.getElementById("modalPilihAset");
  if (modalEl) {
    modalEl.addEventListener("show.bs.modal", function() {
      ensureCatalogLoaded(renderModalRows);
    });
  }
});
</script>
';

render_page('Mutasi & Pemindahan Barang Inventaris', $body, $head, $extraScript);
