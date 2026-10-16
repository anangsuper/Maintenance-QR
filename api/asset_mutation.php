<?php
/**
 * Modul Khusus: Mutasi & Serah Terima Komputer / IT
 * PT BPR Mitratama Arthabuana
 * Dikhususkan untuk Tim IT Operations & Administrasi Komputer
 */

require __DIR__ . '/bootstrap.php';
require_login();

// Validasi hak akses: IT, SDM/HRD, dan Admin
if (!is_it() && !is_hrd()) {
    require_admin();
}

// Jika request bermaksud mutasi inventaris, arahkan langsung ke halaman khusus mutasi inventaris
if (isset($_GET['type']) && strtolower(trim((string)$_GET['type'])) === 'inventaris') {
    $redirectParams = array_filter(['id' => (int)($_GET['id'] ?? 0)]);
    header('Location: ' . module_url('inventaris_mutation.php', $redirectParams));
    exit;
}

$error = '';
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$selectedId = (int)($_GET['id'] ?? 0);

// Ambil master data untuk form
$cabangList = get_cabang_list();
$divisiList = get_divisi_list();
$masterRuanganList = function_exists('get_master_ruangan_list') ? get_master_ruangan_list() : [];

// Handle Submit POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $assetId = (int)($_POST['asset_id'] ?? 0);
    $cabangTujuan = trim((string)($_POST['cabang_tujuan'] ?? ''));
    $ruanganTujuan = trim((string)($_POST['ruangan_tujuan'] ?? ''));
    $penggunaTujuan = trim((string)($_POST['pengguna_tujuan'] ?? ''));
    $divisiTujuan = trim((string)($_POST['divisi_tujuan'] ?? ''));
    $kondisiFisik = trim((string)($_POST['kondisi_fisik'] ?? 'Baik / Normal'));
    $alasan = trim((string)($_POST['alasan_mutasi'] ?? ''));
    $keterangan = trim((string)($_POST['keterangan'] ?? ''));

    if ($assetId <= 0) {
        $error = 'Silakan pilih perangkat komputer yang akan dimutasi.';
    } elseif ($cabangTujuan === '') {
        $error = 'Kantor Cabang tujuan wajib dipilih.';
    } elseif ($penggunaTujuan === '') {
        $error = 'Nama pengguna / pemegang baru wajib diisi.';
    } elseif ($alasan === '') {
        $error = 'Alasan mutasi wajib diisi.';
    } else {
        $result = process_asset_mutation([
            'asset_id'        => $assetId,
            'asset_type'      => 'komputer',
            'cabang_tujuan'   => $cabangTujuan,
            'ruangan_tujuan'  => $ruanganTujuan,
            'pengguna_tujuan' => $penggunaTujuan,
            'divisi_tujuan'   => $divisiTujuan,
            'alasan_mutasi'   => $alasan,
            'kondisi_fisik'   => $kondisiFisik,
            'keterangan'      => $keterangan,
            'petugas_nama'    => $_SESSION['nama'] ?? 'Petugas IT'
        ]);

        if (!empty($result['success'])) {
            $_SESSION['flash'] = 'Mutasi komputer berhasil diproses dan dicatat ke log sistem.';
            header('Location: ' . module_url('print_bast.php', ['id' => $result['id']]));
            exit;
        } else {
            $error = $result['error'] ?? 'Gagal memproses mutasi perangkat komputer.';
        }
    }
}

// Ambil daftar seluruh aset komputer aktif
$assetsList = [];
$assetsForJs = [];
$cabangStats = [];

foreach ($cabangList as $c) {
    $cName = $c['nama_cabang'] ?? '';
    if ($cName !== '') {
        $cabangStats[$cName] = 0;
    }
}

if (is_google_cloud_mode()) {
    $rawAssets = map_sheets_assets();
} else {
    try {
        $st = db()->query(asset_query_base() . " WHERE a.status = 'Aktif' ORDER BY a.kode_inventaris ASC LIMIT 2000");
        $rawAssets = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        $rawAssets = [];
    }
}

foreach ($rawAssets as $ka) {
    $aid = (int)($ka['id'] ?? 0);
    $kKode = (string)($ka['kode_inventaris'] ?? ('PC-' . $aid));
    $kNama = asset_title($ka);
    $rawCab = (string)($ka['cabang_nama'] ?? '');
    $kCabang = resolve_cabang_match($kKode, (string)($ka['lokasi'] ?? ''), $rawCab, $cabangList);
    $kRuangan = (string)($ka['ruangan'] ?? $ka['lokasi'] ?? '-');
    $kPengguna = (string)($ka['karyawan_nama'] ?? 'Umum');
    $kDivisi = (string)($ka['divisi_nama'] ?? '-');
    $kSn = (string)($ka['serial_number'] ?? '');
    $kKategori = (string)($ka['kategori_nama'] ?? 'Komputer/IT');

    $item = [
        'id' => $aid,
        'kode' => $kKode,
        'nama' => $kNama,
        'cabang' => $kCabang,
        'ruangan' => $kRuangan,
        'pengguna' => $kPengguna,
        'divisi' => $kDivisi,
        'golongan' => '-',
        'jenis' => $kKategori,
        'sn' => $kSn,
        'tgl' => (string)($ka['created_at'] ?? ''),
        'kategori' => $kKategori,
        'search_text' => strtolower($kKode . ' ' . $kNama . ' ' . $kCabang . ' ' . $kRuangan . ' ' . $kPengguna . ' ' . $kDivisi . ' ' . $kSn . ' ' . $kKategori)
    ];
    $assetsList[] = $item;
    $assetsForJs[$aid] = $item;
    $cabangStats[$kCabang] = ($cabangStats[$kCabang] ?? 0) + 1;
}

// Data aset awal jika pre-selected
$currentCabang = '';
$currentRuangan = '';
$currentPengguna = '';
$currentDivisi = '';
$currentKode = '';
$currentNama = '';
$currentSn = '';

if ($selectedId > 0 && isset($assetsForJs[$selectedId])) {
    $sel = $assetsForJs[$selectedId];
    $currentKode = $sel['kode'];
    $currentNama = $sel['nama'];
    $currentCabang = $sel['cabang'];
    $currentRuangan = $sel['ruangan'];
    $currentPengguna = $sel['pengguna'];
    $currentDivisi = $sel['divisi'];
    $currentSn = $sel['sn'];
}

$head = '
<style>
.mutation-page-header {
  background: linear-gradient(135deg, #003B73 0%, #005691 50%, #0077B6 100%);
  color: #FFFFFF;
  padding: 26px 32px;
  border-radius: 18px;
  margin-bottom: 24px;
  box-shadow: 0 10px 25px -5px rgba(0, 59, 115, 0.25);
  position: relative;
  overflow: hidden;
}
.mutation-page-header::after {
  content: "";
  position: absolute;
  top: -50px;
  right: -50px;
  width: 220px;
  height: 220px;
  background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
  border-radius: 50%;
  pointer-events: none;
}
.mutation-page-header h1 {
  font-size: 1.5rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  margin: 0;
}
.mutation-page-header p {
  color: rgba(255, 255, 255, 0.85);
  margin-top: 4px;
  margin-bottom: 0;
  font-size: 0.88rem;
}
.mutation-page-header .tech-label {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #90E0EF;
}
.mutation-page-header .header-actions .btn {
  background: rgba(255, 255, 255, 0.18);
  border: 1px solid rgba(255, 255, 255, 0.35);
  color: #FFFFFF;
  font-weight: 600;
  font-size: 0.82rem;
  padding: 8px 16px;
  border-radius: 10px;
  transition: all 0.2s ease;
  backdrop-filter: blur(4px);
}
.mutation-page-header .header-actions .btn:hover {
  background: #FFFFFF;
  color: #003B73;
  border-color: #FFFFFF;
}

.mutation-card {
  background: #FFFFFF;
  border-radius: 16px;
  border: 1px solid #E2E8F0;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  overflow: hidden;
  height: 100%;
  display: flex;
  flex-direction: column;
}
.mutation-card-header {
  padding: 16px 20px;
  background: #F8FAFC;
  border-bottom: 1px solid #E2E8F0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.mutation-card-header h5 {
  font-size: 0.96rem;
  font-weight: 700;
  margin: 0;
  color: #0F172A;
  display: flex;
  align-items: center;
  gap: 8px;
}
.mutation-card-body {
  padding: 22px;
  flex: 1;
}

.step-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 800;
}

.btn-catalog-pulse {
  background: linear-gradient(135deg, #0077B6 0%, #0096C7 100%);
  color: #FFFFFF;
  font-weight: 700;
  padding: 13px 18px;
  border-radius: 12px;
  border: none;
  box-shadow: 0 4px 14px rgba(0, 119, 182, 0.35);
  transition: all 0.25s ease;
}
.btn-catalog-pulse:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0, 119, 182, 0.45);
  color: #FFFFFF;
}
.btn-catalog-badge {
  background: rgba(255, 255, 255, 0.25);
  color: #FFFFFF;
  font-size: 0.72rem;
  padding: 3px 8px;
  border-radius: 6px;
}

.or-divider {
  display: flex;
  align-items: center;
  text-align: center;
  margin: 14px 0;
  color: #94A3B8;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.or-divider::before, .or-divider::after {
  content: "";
  flex: 1;
  border-bottom: 1px dashed #CBD5E1;
}
.or-divider span {
  padding: 0 10px;
}

.location-box {
  background: #F8FAFC;
  border: 1px dashed #CBD5E1;
  border-radius: 12px;
  padding: 14px 16px;
}

.info-bast {
  background: #F0F9FF;
  border-left: 4px solid #0284C7;
  border-radius: 0 10px 10px 0;
  padding: 12px 14px;
  font-size: 0.8rem;
  color: #0369A1;
  margin-top: 16px;
}

.btn-submit-mutation {
  background: linear-gradient(135deg, #003B73 0%, #005691 100%);
  color: #FFFFFF;
  font-weight: 700;
  padding: 11px 24px;
  border-radius: 10px;
  border: none;
  box-shadow: 0 4px 12px rgba(0, 59, 115, 0.3);
  transition: all 0.2s ease;
}
.btn-submit-mutation:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(0, 59, 115, 0.4);
  color: #FFFFFF;
}

.btn-cancel-mutation {
  background: #F1F5F9;
  color: #475569;
  font-weight: 600;
  padding: 11px 20px;
  border-radius: 10px;
  border: 1px solid #CBD5E1;
}
.btn-cancel-mutation:hover {
  background: #E2E8F0;
  color: #0F172A;
}

.table-catalog-asset tbody tr {
  cursor: pointer;
  transition: background 0.15s ease;
}
.table-catalog-asset tbody tr:hover {
  background-color: #F8FAFC !important;
}
.table-catalog-asset tbody tr.table-primary {
  background-color: #E0F2FE !important;
}
.modal-branch-pill {
  border-radius: 50px;
  font-size: 0.75rem;
  padding: 4px 12px;
  font-weight: 600;
}
</style>
';

$errorHtml = $error ? '<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" style="border-left: 4px solid #DC2626 !important;"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>'.e($error).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$cancelUrl = module_url('assets.php');

$body = '
<!-- Header Premium IT -->
<div class="mutation-page-header">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <div>
      <div class="tech-label">ASSET LIFECYCLE &amp; IT OPERATIONS</div>
      <h1 class="d-flex align-items-center gap-2 mb-1">
        <i class="bi bi-pc-display"></i> Mutasi &amp; Serah Terima Komputer (IT)
      </h1>
      <p>Relokasi perangkat PC, laptop, server antar-cabang, rotasi pemegang aset, dan penerbitan BAST-IT resmi.</p>
    </div>
    <div class="header-actions d-flex flex-wrap align-items-center gap-2 flex-shrink-0">
      <a href="'.e(module_url('inventaris_mutation.php')).'" class="btn" title="Buka mutasi khusus mebel, perabot, dan fasilitas gedung">
        <i class="bi bi-archive-fill me-1"></i> Ke Mutasi Inventaris
      </a>
      <a href="'.e(module_url('asset_mutations.php', ['type' => 'komputer'])).'" class="btn">
        <i class="bi bi-journal-text me-1"></i> Riwayat Mutasi
      </a>
      <a href="'.e($cancelUrl).'" class="btn">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Data PC
      </a>
    </div>
  </div>
</div>

'.$errorHtml.'

<form method="post">
  <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
  <input type="hidden" name="asset_type" value="komputer">

  <div class="row g-4">
    <!-- Kolom Kiri: Pemilihan & Informasi Komputer Saat Ini -->
    <div class="col-lg-5">
      <div class="mutation-card">
        <div class="mutation-card-header">
          <h5>
            <span class="step-badge bg-primary text-white">1</span>
            <i class="bi bi-box-seam-fill text-primary"></i> Perangkat IT yang Dimutasi
          </h5>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 small fw-semibold">
            <i class="bi bi-pc-display me-1"></i>Perangkat Komputer
          </span>
        </div>
        <div class="mutation-card-body">

        <!-- Tombol Utama: Buka Katalog Modal Pencarian -->
        <button type="button" class="btn btn-catalog-pulse w-100 mb-3 d-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#modalPilihAset">
          <i class="bi bi-grid-3x3-gap-fill fs-5"></i>
          <span>Buka Katalog &amp; Cari Perangkat</span>
          <span class="btn-catalog-badge badge ms-2">'.count($assetsList).' Unit</span>
        </button>

        <div class="or-divider">
          <span>Atau Pilih Cepat di Bawah</span>
        </div>

        <!-- Filter Cepat: Cabang & Ketik Pencarian -->
        <div class="row g-2 mb-2 mt-1">
          <div class="col-sm-6">
            <label class="form-label text-uppercase fw-bold text-muted small mb-1" style="font-size: 0.68rem;">Filter Cabang Asal</label>
            <select id="filterCabangAsal" class="form-select form-select-sm" onchange="filterAssetList()">
              <option value="">-- Semua Cabang ('.count($assetsList).' Unit) --</option>';

foreach ($cabangList as $c) {
    $cName = $c['nama_cabang'] ?? '';
    $cCount = $cabangStats[$cName] ?? 0;
    $body .= '<option value="'.e($cName).'">'.e($cName).' ('.$cCount.' unit)</option>';
}

$body .= '
            </select>
          </div>
          <div class="col-sm-6">
            <label class="form-label text-uppercase fw-bold text-muted small mb-1" style="font-size: 0.68rem;">Ketik Nama / User / Kode</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
              <input type="text" id="quickSearchAsset" class="form-control border-start-0" placeholder="Ketik kata kunci..." oninput="filterAssetList()" autocomplete="off">
              <button class="btn btn-outline-secondary" type="button" onclick="resetQuickFilter()" title="Reset pencarian"><i class="bi bi-x"></i></button>
            </div>
          </div>
        </div>

        <!-- Dropdown Seleksi Komputer -->
        <div class="mb-3">
          <label class="form-label text-uppercase fw-bold text-muted small mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Pilih Perangkat / Unit <span class="text-danger">*</span></label>
          <select name="asset_id" id="selectAssetId" class="form-select" onchange="onAssetChange(this.value)" required style="border-radius: 10px; font-weight: 500;">
            <option value="">-- Pilih Perangkat Terdaftar --</option>';

foreach ($assetsList as $a) {
    $aid = (int)$a['id'];
    $sel = ($aid === $selectedId) ? 'selected' : '';
    $label = $a['kode'] . ' - ' . $a['nama'] . ' (' . $a['pengguna'] . ' | ' . $a['cabang'] . ')';
    $body .= '<option value="'.$aid.'" '.$sel.'>'.e($label).'</option>';
}

$body .= '
          </select>
          <div class="d-flex justify-content-between align-items-center mt-1">
            <span class="form-text small" style="font-size: 0.72rem;" id="matchCountText">Menampilkan '.count($assetsList).' dari '.count($assetsList).' unit terdaftar</span>
            <span class="text-muted small" style="font-size: 0.72rem;"><i class="bi bi-shield-check text-success me-1"></i>Hanya unit aktif</span>
          </div>
        </div>

        <!-- Info Box Kondisi Asal Terpilih -->
        <div class="location-box mt-2" id="lokasiAsalBox">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="badge bg-primary px-3 py-2" style="font-size: 0.68rem; border-radius: 8px; letter-spacing:0.05em;">LOKASI ASAL SAAT INI</span>
            <span class="text-muted small" style="font-size: 0.72rem;"><i class="bi bi-shield-check text-success me-1"></i>Terverifikasi</span>
          </div>
          
          <div class="mb-2">
            <div class="small text-muted" style="font-size: 0.72rem;">KODE &amp; NAMA ASET:</div>
            <strong class="text-dark fs-6" id="displayKode">'.e($currentKode ?: 'Belum dipilih').'</strong>
            <div class="text-secondary small fw-semibold" id="displayNama">'.e($currentNama).'</div>
            <div class="small font-monospace text-muted mt-0.5" id="displaySnWrapper" style="'.($currentSn ? '' : 'display:none;').'">
              <span>SN:</span> <span id="displaySn">'.e($currentSn).'</span>
            </div>
          </div>
          
          <hr class="my-2 border-secondary opacity-15">

          <div class="row g-2 small">
            <div class="col-6">
              <span class="text-muted d-block" style="font-size: 0.7rem;">KANTOR CABANG:</span>
              <strong class="text-dark" id="displayCabang">'.e($currentCabang ?: '-').'</strong>
            </div>
            <div class="col-6">
              <span class="text-muted d-block" style="font-size: 0.7rem;">RUANGAN / AREA:</span>
              <strong class="text-dark" id="displayRuangan">'.e($currentRuangan ?: '-').'</strong>
            </div>
            <div class="col-6 mt-2">
              <span class="text-muted d-block" style="font-size: 0.7rem;">PENGGUNA / PIC LAMA:</span>
              <strong class="text-dark" id="displayPengguna">'.e($currentPengguna ?: '-').'</strong>
            </div>
            <div class="col-6 mt-2">
              <span class="text-muted d-block" style="font-size: 0.7rem;">DIVISI:</span>
              <strong class="text-dark" id="displayDivisi">'.e($currentDivisi ?: '-').'</strong>
            </div>
          </div>
        </div>

        <div class="info-bast">
          <i class="bi bi-info-circle-fill text-primary me-1"></i>
          Setelah mutasi diproses, sistem otomatis memperbarui pemegang unit, mencatat log audit, dan menerbitkan formulir <strong>BAST Komputer (BAST-IT-01)</strong> resmi.
        </div>
        </div><!-- /mutation-card-body -->
      </div><!-- /mutation-card left -->
    </div>

    <!-- Kolom Kanan: Tujuan Baru & Alasan Mutasi -->
    <div class="col-lg-7">
      <div class="mutation-card">
        <div class="mutation-card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">
            <span class="step-badge bg-primary text-white">2</span>
            <i class="bi bi-geo-alt-fill text-primary"></i> Tujuan Mutasi &amp; Serah Terima Komputer
          </h5>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 small fw-semibold" style="font-size: 0.72rem;">
            <i class="bi bi-pc-display me-1"></i>Mutasi Aset Komputer/IT
          </span>
        </div>
        <div class="mutation-card-body">
        <div class="row g-3">

          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Kantor Cabang Tujuan <span class="text-danger">*</span></label>
            <select name="cabang_tujuan" class="form-select" required>
              <option value="">-- Pilih Cabang Tujuan --</option>';

foreach ($cabangList as $c) {
    $cName = $c['nama_cabang'] ?? '';
    $body .= '<option value="'.e($cName).'">'.e($cName).'</option>';
}

$body .= '
            </select>
            <div class="form-text small" style="font-size: 0.72rem;">Kantor cabang penerima perangkat komputer.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Ruangan / Lokasi Penempatan Baru</label>
            <input type="text" list="listMasterRuangan" class="form-control" name="ruangan_tujuan" placeholder="Contoh: Meja CS 2 / Ruang Teller">
            <datalist id="listMasterRuangan">';

foreach ($masterRuanganList as $mr) {
    $rName = is_array($mr) ? ($mr['nama_ruangan'] ?? '') : (string)$mr;
    if ($rName !== '') {
        $body .= '<option value="'.e($rName).'">';
    }
}

$body .= '
            </datalist>
            <div class="form-text small" style="font-size: 0.72rem;">Ruangan spesifik penempatan perangkat.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Nama Pengguna / Pemegang Baru <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="pengguna_tujuan" placeholder="Contoh: Siti Rahayu (CS)" required>
            <div class="form-text small" style="font-size: 0.72rem;">Karyawan yang menerima dan bertanggung jawab atas unit ini.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Divisi / Bagian Baru</label>
            <select name="divisi_tujuan" class="form-select">
              <option value="">-- Pilih Divisi (Opsional) --</option>';

foreach ($divisiList as $d) {
    $dName = $d['nama_divisi'] ?? '';
    $body .= '<option value="'.e($dName).'">'.e($dName).'</option>';
}

$body .= '
            </select>
            <div class="form-text small" style="font-size: 0.72rem;">Divisi penugasan karyawan penerima unit.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Kondisi Operasional Saat Mutasi <span class="text-danger">*</span></label>
            <select name="kondisi_fisik" class="form-select" required>
              <option value="Baik / Normal" selected>Baik / Normal (Siap Pakai)</option>
              <option value="Perlu Perbaikan / Servis">Perlu Perbaikan / Servis</option>
              <option value="Rusak Berat / Afkir">Rusak Berat / Pengajuan Afkir</option>
            </select>
            <div class="form-text small" style="font-size: 0.72rem;">Kondisi operasional unit PC/laptop saat serah terima.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Alasan Mutasi Komputer <span class="text-danger">*</span></label>
            <select name="alasan_mutasi" class="form-select" required>
              <option value="">-- Pilih Alasan Mutasi --</option>';

$reasonsIT = [
    'Rotasi / Perpindahan Tugas Karyawan',
    'Peremajaan / Penggantian Perangkat Baru',
    'Kebutuhan Penambahan Unit di Kantor Cabang',
    'Unit Cadangan Sementara (Unit Back-up)',
    'Relokasi Kantor / Renovasi Ruangan',
    'Penarikan ke Kantor Pusat (Pool)',
    'Lainnya'
];
foreach ($reasonsIT as $reason) {
    $body .= '<option value="'.e($reason).'">'.e($reason).'</option>';
}

$body .= '
            </select>
            <div class="form-text small" style="font-size: 0.72rem;">Alasan resmi perpindahan unit komputer.</div>
          </div>

          <div class="col-12">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Catatan / Kelengkapan Aksesoris</label>
            <textarea class="form-control" name="keterangan" rows="3" placeholder="Contoh: Disertakan Adaptor Charger original, Kabel Power, Mouse, dan Tas Laptop dalam kondisi baik."></textarea>
            <div class="form-text small" style="font-size: 0.72rem;">Sebutkan kelengkapan aksesoris dan nomor seri adaptor jika ada.</div>
          </div>

        </div>

        <hr class="my-4 border-secondary opacity-15">

        <div class="d-flex justify-content-between align-items-center">
          <a href="'.e($cancelUrl).'" class="btn-cancel-mutation btn">
            <i class="bi bi-x-circle me-1"></i> Batal
          </a>
          <button type="submit" class="btn-submit-mutation btn d-inline-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill"></i> Proses Mutasi &amp; Terbitkan BAST IT
          </button>
        </div>
        </div><!-- /mutation-card-body -->
      </div><!-- /mutation-card -->
    </div>
  </div>
</form>

<!-- Modal Pop-up: Katalog Pemilihan Komputer -->
<div class="modal fade" id="modalPilihAset" tabindex="-1" aria-labelledby="modalPilihAsetLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title d-flex align-items-center gap-2" id="modalPilihAsetLabel">
            <i class="bi bi-grid-3x3-gap-fill text-primary"></i> Katalog Pemilihan Perangkat Komputer (IT)
          </h5>
          <p class="mb-0 text-muted small">Pilih unit PC, laptop, atau perangkat IT aktif yang ingin dipindahkan ke kantor cabang lain.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-3 p-md-4">
        <!-- Pencarian & Filter Cepat Cabang -->
        <div class="row g-2 mb-3">
          <div class="col-md-7">
            <div class="input-group">
              <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-primary"></i></span>
              <input type="text" id="modalSearchInput" class="form-control border-start-0" placeholder="Ketik nama karyawan, merk, kode inventaris, serial number..." oninput="filterModalTable()" autocomplete="off">
              <button class="btn btn-outline-secondary" type="button" onclick="clearModalSearch()"><i class="bi bi-x-circle me-1"></i>Reset</button>
            </div>
          </div>
          <div class="col-md-5">
            <select id="modalBranchFilter" class="form-select" onchange="filterModalTable()">
              <option value="">-- Seluruh Kantor Cabang ('.count($assetsList).' Unit) --</option>';

foreach ($cabangList as $c) {
    $cName = $c['nama_cabang'] ?? '';
    $cCount = $cabangStats[$cName] ?? 0;
    $body .= '<option value="'.e($cName).'">'.e($cName).' ('.$cCount.' unit)</option>';
}

$body .= '
            </select>
          </div>
        </div>

        <!-- Tabel Katalog Komputer -->
        <div class="table-responsive border rounded-3" style="max-height: 480px;">
          <table class="table table-catalog-asset table-hover align-middle mb-0" id="tableCatalogAsset">
            <thead class="table-light sticky-top" style="z-index: 2;">
              <tr>
                <th style="width: 45px;" class="text-center font-monospace small">NO</th>
                <th style="width: 150px;">KODE INVENTARIS</th>
                <th>PERANGKAT &amp; MERK</th>
                <th style="width: 180px;">CABANG &amp; RUANGAN</th>
                <th style="width: 160px;">PENGGUNA / PIC</th>
                <th style="width: 110px;" class="text-center">AKSI</th>
              </tr>
            </thead>
            <tbody id="catalogTableBody">
              <!-- Render via JavaScript -->
            </tbody>
          </table>
        </div>
      </div>

      <div class="modal-footer bg-light d-flex justify-content-between">
        <span class="text-muted small" id="modalAssetCount">Menampilkan '.count($assetsList).' unit</span>
        <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
';

$extraScript = '
<script>
const ALL_ASSETS = ' . json_encode($assetsList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';
const ASSETS_MAP = ' . json_encode($assetsForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';
let currentSelectedId = ' . (int)$selectedId . ';

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
    if (box) box.classList.remove("border-primary");
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

  if (box) box.classList.add("border-primary");
  renderModalRows();
}

function selectAssetAndCloseModal(assetId) {
  const sel = document.getElementById("selectAssetId");
  if (sel) {
    sel.value = assetId;
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

  const q = (document.getElementById("modalSearchInput") ? document.getElementById("modalSearchInput").value : "").toLowerCase().trim();
  const branch = document.getElementById("modalBranchFilter") ? document.getElementById("modalBranchFilter").value : "";

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

    count++;
    const isSelected = (item.id === currentSelectedId);
    const rowClass = isSelected ? "table-primary fw-semibold" : "";

    html += `
      <tr class="${rowClass}" onclick="selectAssetAndCloseModal(${item.id})">
        <td class="text-center font-monospace text-muted small">${count}</td>
        <td>
          <div class="fw-bold font-monospace text-primary">${escapeHtml(item.kode)}</div>
          <div class="small text-muted">${item.sn ? "SN: " + escapeHtml(item.sn) : ""}</div>
        </td>
        <td>
          <div class="fw-bold text-dark">${escapeHtml(item.nama)}</div>
          <span class="badge bg-light text-secondary border small mt-1">${escapeHtml(item.kategori)}</span>
        </td>
        <td>
          <div class="fw-semibold text-dark">${escapeHtml(item.cabang)}</div>
          <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i>${escapeHtml(item.ruangan)}</div>
        </td>
        <td>
          <div class="text-dark small">${escapeHtml(item.pengguna)}</div>
          <div class="text-muted" style="font-size:0.7rem;">${escapeHtml(item.divisi)}</div>
        </td>
        <td class="text-center" onclick="event.stopPropagation()">
          <button type="button" class="btn btn-sm ${isSelected ? "btn-primary" : "btn-outline-primary"} fw-bold px-3 py-1.5 shadow-sm" onclick="selectAssetAndCloseModal(${item.id})">
            <i class="bi ${isSelected ? "bi-check-circle-fill" : "bi-check-lg"} me-1"></i> ${isSelected ? "Terpilih" : "Pilih Unit"}
          </button>
        </td>
      </tr>
    `;
  }

  if (count === 0) {
    html = `
      <tr>
        <td colspan="6" class="text-center py-5 text-muted">
          <i class="bi bi-search fs-2 d-block mb-2 opacity-50"></i>
          Tidak ada komputer yang sesuai dengan pencarian.<br>
          <button type="button" class="btn btn-link btn-sm mt-2 text-decoration-none" onclick="clearModalSearch()">Reset Pencarian</button>
        </td>
      </tr>
    `;
  }

  tbody.innerHTML = html;
  const countEl = document.getElementById("modalAssetCount");
  if (countEl) {
    countEl.textContent = `Menampilkan ${count} dari ${ALL_ASSETS.length} unit komputer`;
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
    text.textContent = `Menampilkan ${matched} dari ${ALL_ASSETS.length} unit terdaftar`;
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
  renderModalRows();
});
</script>
';

render_page('Mutasi & Serah Terima Komputer / IT', $body, $head, $extraScript);
