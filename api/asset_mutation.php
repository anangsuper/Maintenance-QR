<?php
require __DIR__ . '/bootstrap.php';
require_login();

// Validasi hak akses: hanya IT, SDM/HRD, dan Admin yang dapat memutasi aset
if (!is_it() && !is_hrd()) {
    require_admin();
}

$error = '';
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$selectedId = (int)($_GET['id'] ?? 0);
$selectedType = strtolower(trim((string)($_GET['type'] ?? 'komputer')));
if (!in_array($selectedType, ['komputer', 'inventaris'], true)) {
    $selectedType = 'komputer';
}

// Ambil master data untuk form
$cabangList = get_cabang_list();
$divisiList = get_divisi_list();

// Handle Submit POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $assetId = (int)($_POST['asset_id'] ?? 0);
    $assetType = strtolower(trim((string)($_POST['asset_type'] ?? 'komputer')));
    $cabangTujuan = trim((string)($_POST['cabang_tujuan'] ?? ''));
    $ruanganTujuan = trim((string)($_POST['ruangan_tujuan'] ?? ''));
    $penggunaTujuan = trim((string)($_POST['pengguna_tujuan'] ?? ''));
    $divisiTujuan = trim((string)($_POST['divisi_tujuan'] ?? ''));
    $alasan = trim((string)($_POST['alasan_mutasi'] ?? ''));
    $keterangan = trim((string)($_POST['keterangan'] ?? ''));

    if ($assetId <= 0) {
        $error = 'Silakan pilih aset yang akan dimutasi.';
    } elseif ($cabangTujuan === '') {
        $error = 'Kantor Cabang tujuan wajib dipilih.';
    } elseif ($alasan === '') {
        $error = 'Alasan mutasi wajib diisi.';
    } else {
        $result = process_asset_mutation([
            'asset_id'        => $assetId,
            'asset_type'      => $assetType,
            'cabang_tujuan'   => $cabangTujuan,
            'ruangan_tujuan'  => $ruanganTujuan,
            'pengguna_tujuan' => $penggunaTujuan,
            'divisi_tujuan'   => $divisiTujuan,
            'alasan_mutasi'   => $alasan,
            'keterangan'      => $keterangan,
            'petugas_nama'    => $_SESSION['nama'] ?? 'Petugas IT'
        ]);

        if (!empty($result['success'])) {
            $_SESSION['flash'] = $result['message'];
            header('Location: ' . module_url('print_bast.php', ['id' => $result['id']]));
            exit;
        } else {
            $error = $result['error'] ?? 'Gagal memproses mutasi aset.';
        }
    }
}

// Ambil daftar seluruh aset aktif untuk dropdown pilihan dan katalog modal
$assetsList = [];
$assetsForJs = [];
$cabangStats = [];

if ($selectedType === 'komputer') {
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
        $kCabang = (string)($ka['cabang_nama'] ?? 'Kantor Pusat Operasional');
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
            'sn' => $kSn,
            'kategori' => $kKategori,
            'search_text' => strtolower($kKode . ' ' . $kNama . ' ' . $kCabang . ' ' . $kRuangan . ' ' . $kPengguna . ' ' . $kDivisi . ' ' . $kSn . ' ' . $kKategori)
        ];
        $assetsList[] = $item;
        $assetsForJs[$aid] = $item;
        $cabangStats[$kCabang] = ($cabangStats[$kCabang] ?? 0) + 1;
    }
} else {
    try {
        $invRows = get_inventaris_kartu_rows();
        foreach ($invRows as $ir) {
            $aid = (int)($ir['id'] ?? 0);
            $kKode = (string)($ir['nomor_rekening'] ?? ('INV-' . $aid));
            $kNama = (string)($ir['nama_barang'] ?? 'Aset Inventaris');
            $kCabang = (string)($ir['cabang_nama'] ?? 'Kantor Pusat');
            $kRuangan = (string)($ir['ruangan'] ?? '-');
            $kPengguna = (string)($ir['pengguna'] ?? 'Umum');
            $kDivisi = (string)($ir['golongan'] ?? '-');
            $kSn = (string)($ir['nomor_seri_inventaris'] ?? '');
            $kKategori = (string)($ir['golongan'] ?? 'Inventaris Kantor');

            $item = [
                'id' => $aid,
                'kode' => $kKode,
                'nama' => $kNama,
                'cabang' => $kCabang,
                'ruangan' => $kRuangan,
                'pengguna' => $kPengguna,
                'divisi' => $kDivisi,
                'sn' => $kSn,
                'kategori' => $kKategori,
                'search_text' => strtolower($kKode . ' ' . $kNama . ' ' . $kCabang . ' ' . $kRuangan . ' ' . $kPengguna . ' ' . $kDivisi . ' ' . $kSn . ' ' . $kKategori)
            ];
            $assetsList[] = $item;
            $assetsForJs[$aid] = $item;
            $cabangStats[$kCabang] = ($cabangStats[$kCabang] ?? 0) + 1;
        }
    } catch (Throwable $e) {
        // ignore
    }
}

// Data aset yang sedang dipilih
$currentCabang = '';
$currentRuangan = '';
$currentPengguna = '';
$currentDivisi = '';
$currentKode = '';
$currentNama = '';
$currentSn = '';

if ($selectedId > 0) {
    if (isset($assetsForJs[$selectedId])) {
        $ci = $assetsForJs[$selectedId];
        $currentKode = $ci['kode'];
        $currentNama = $ci['nama'];
        $currentSn = $ci['sn'];
        $currentCabang = $ci['cabang'];
        $currentRuangan = $ci['ruangan'];
        $currentPengguna = $ci['pengguna'];
        $currentDivisi = $ci['divisi'];
    } else {
        // Fallback fetch satuan
        $singleAsset = ($selectedType === 'komputer') ? get_asset_by_id($selectedId) : get_inventaris_kartu_by_id($selectedId);
        if ($singleAsset) {
            $currentKode = $singleAsset['kode_inventaris'] ?? $singleAsset['nomor_rekening'] ?? ('ID-' . $selectedId);
            $currentNama = ($selectedType === 'komputer') ? asset_title($singleAsset) : ($singleAsset['nama_barang'] ?? 'Aset');
            $currentSn = $singleAsset['serial_number'] ?? $singleAsset['nomor_seri_inventaris'] ?? '';
            $currentCabang = $singleAsset['cabang_nama'] ?? 'Kantor Pusat';
            $currentRuangan = $singleAsset['ruangan'] ?? $singleAsset['lokasi'] ?? '-';
            $currentPengguna = $singleAsset['karyawan_nama'] ?? $singleAsset['pengguna'] ?? 'Umum';
            $currentDivisi = $singleAsset['divisi_nama'] ?? $singleAsset['golongan'] ?? '-';
        }
    }
}

$title = 'Form Mutasi & Serah Terima Aset';
$head = '
<style>
/* ============================================================
   ASSET MUTATION PAGE — PREMIUM UI STYLES
   ============================================================ */

/* Page layout */
body { background: #F0F4F8; }

/* Heading area */
.mutation-page-header {
  background: linear-gradient(135deg, #1E3A5F 0%, #1E40AF 60%, #2563EB 100%);
  border-radius: 20px;
  padding: 28px 32px;
  color: #fff;
  margin-bottom: 28px;
  box-shadow: 0 8px 32px rgba(37,99,235,0.22);
  position: relative;
  overflow: hidden;
}
.mutation-page-header::before {
  content: "";
  position: absolute;
  top: -60px; right: -60px;
  width: 200px; height: 200px;
  background: rgba(255,255,255,0.06);
  border-radius: 50%;
}
.mutation-page-header .tech-label {
  font-size: 0.68rem;
  letter-spacing: 0.12em;
  font-weight: 700;
  opacity: 0.75;
  text-transform: uppercase;
  margin-bottom: 6px;
}
.mutation-page-header h1 {
  font-size: 1.5rem;
  font-weight: 800;
  margin-bottom: 4px;
}
.mutation-page-header p {
  font-size: 0.875rem;
  opacity: 0.8;
  margin: 0;
}
.mutation-page-header .header-actions .btn {
  background: rgba(255,255,255,0.12);
  border: 1px solid rgba(255,255,255,0.25);
  color: #fff;
  font-size: 0.8rem;
  font-weight: 600;
  padding: 7px 16px;
  border-radius: 10px;
  backdrop-filter: blur(4px);
  transition: all 0.2s;
}
.mutation-page-header .header-actions .btn:hover {
  background: rgba(255,255,255,0.22);
  transform: translateY(-1px);
}

/* Cards */
.mutation-card {
  border: none;
  border-radius: 20px;
  box-shadow: 0 4px 24px rgba(15,23,42,0.09);
  background: #fff;
  height: 100%;
  overflow: hidden;
}
.mutation-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px 0;
  margin-bottom: 20px;
}
.mutation-card-header h5 {
  font-size: 1rem;
  font-weight: 800;
  color: #0F172A;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}
.mutation-card-body { padding: 0 24px 24px; }

/* Catalog open button */
.btn-catalog-pulse {
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #1E40AF 0%, #3B82F6 100%) !important;
  border: none !important;
  border-radius: 14px !important;
  font-size: 0.95rem !important;
  font-weight: 700 !important;
  padding: 13px 20px !important;
  letter-spacing: 0.02em;
  box-shadow: 0 6px 20px rgba(37,99,235,0.35), 0 2px 8px rgba(37,99,235,0.2);
  transition: all 0.25s cubic-bezier(.4,0,.2,1);
}
.btn-catalog-pulse::after {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent);
  pointer-events: none;
}
.btn-catalog-pulse:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 28px rgba(37,99,235,0.45), 0 4px 12px rgba(37,99,235,0.25);
}
.btn-catalog-pulse:active { transform: translateY(0); }
.btn-catalog-badge {
  background: rgba(255,255,255,0.2) !important;
  border: 1px solid rgba(255,255,255,0.35) !important;
  color: #fff !important;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 3px 10px;
}

/* Divider with label */
.or-divider {
  position: relative;
  text-align: center;
  margin: 14px 0;
}
.or-divider::before {
  content: "";
  position: absolute;
  top: 50%; left: 0; right: 0;
  height: 1px;
  background: #E2E8F0;
}
.or-divider span {
  position: relative;
  background: #fff;
  padding: 0 10px;
  font-size: 0.65rem;
  font-weight: 700;
  color: #94A3B8;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

/* Filter row */
.filter-label {
  font-size: 0.67rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #64748B;
  margin-bottom: 5px;
  display: block;
}
.form-select, .form-control {
  border-radius: 10px !important;
  border-color: #E2E8F0 !important;
  font-size: 0.875rem;
  color: #1E293B;
  box-shadow: none !important;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.form-select:focus, .form-control:focus {
  border-color: #3B82F6 !important;
  box-shadow: 0 0 0 3px rgba(59,130,246,0.12) !important;
}
.input-group-text {
  border-radius: 10px 0 0 10px !important;
  border-color: #E2E8F0 !important;
  background: #F8FAFC;
}
.input-group .form-control { border-radius: 0 !important; }
.input-group .btn-outline-secondary {
  border-radius: 0 10px 10px 0 !important;
  border-color: #E2E8F0 !important;
}

/* Location info box */
.location-box {
  padding: 18px 20px;
  border-radius: 14px;
  background: linear-gradient(135deg, #F8FAFC 0%, #EFF6FF 100%);
  border: 1.5px solid #DBEAFE;
  transition: all 0.35s ease;
  position: relative;
}
.location-box.flash {
  background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
  border-color: #93C5FD;
}

/* Right column form */
.form-label {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #64748B;
  margin-bottom: 6px;
}
textarea.form-control { border-radius: 12px !important; }

/* Submit button */
.btn-submit-mutation {
  background: linear-gradient(135deg, #059669 0%, #10B981 100%);
  border: none;
  border-radius: 12px;
  font-size: 0.95rem;
  font-weight: 700;
  padding: 12px 28px;
  color: #fff;
  box-shadow: 0 6px 18px rgba(16,185,129,0.3);
  transition: all 0.25s;
  letter-spacing: 0.02em;
}
.btn-submit-mutation:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 24px rgba(16,185,129,0.4);
  color: #fff;
}
.btn-cancel-mutation {
  border-radius: 12px;
  font-weight: 600;
  font-size: 0.875rem;
  padding: 11px 22px;
  border: 1.5px solid #CBD5E1;
  color: #64748B;
  background: #fff;
  transition: all 0.2s;
}
.btn-cancel-mutation:hover {
  border-color: #94A3B8;
  color: #334155;
  background: #F8FAFC;
}

/* Type switcher */
.type-switcher .btn {
  font-size: 0.72rem;
  font-weight: 600;
  border-radius: 8px !important;
  padding: 5px 12px;
}

/* Info alert */
.info-bast {
  background: linear-gradient(135deg, #EFF6FF, #F0F9FF);
  border: 1px solid #BAE6FD;
  border-radius: 12px;
  padding: 14px 16px;
  font-size: 0.82rem;
  color: #0C4A6E;
  margin-top: 16px;
}

/* Modal styles */
.modal-content {
  border: none !important;
  border-radius: 20px !important;
  box-shadow: 0 24px 80px rgba(15,23,42,0.22) !important;
  overflow: hidden;
}
.modal-header {
  background: linear-gradient(135deg, #1E3A5F, #1E40AF);
  color: #fff;
  border: none !important;
  padding: 20px 24px;
}
.modal-header .modal-title { color: #fff; font-weight: 800; font-size: 1.05rem; }
.modal-header p { color: rgba(255,255,255,0.75); font-size: 0.82rem; margin: 3px 0 0; }
.modal-header .btn-close { filter: brightness(0) invert(1); opacity: 0.7; }
.modal-header .btn-close:hover { opacity: 1; }
.modal-footer {
  background: #F8FAFC;
  border-top: 1px solid #E2E8F0 !important;
  padding: 14px 24px;
}

/* Branch pills */
.modal-branch-pill {
  border-radius: 20px;
  font-size: 0.73rem;
  font-weight: 600;
  padding: 5px 14px;
  transition: all 0.15s ease;
}

/* Table */
.table-catalog-row { cursor: pointer; transition: background-color 0.12s ease; }
.table-catalog-row:hover { background-color: #EFF6FF !important; }
.table thead th {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #64748B;
  border-bottom: 2px solid #E2E8F0;
  padding: 12px 14px;
  background: #F8FAFC;
}
.table tbody td { padding: 12px 14px; vertical-align: middle; }

/* Section divider number badges */
.step-badge {
  width: 28px; height: 28px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.78rem;
  font-weight: 800;
  flex-shrink: 0;
}

/* Responsive tweaks */
@media (max-width: 768px) {
  .mutation-page-header { padding: 20px 18px; }
  .mutation-card-body { padding: 0 16px 20px; }
  .mutation-card-header { padding: 16px 16px 0; }
}
</style>';

$errorHtml = $error ? '<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" style="border-left: 4px solid #DC2626 !important;"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>'.e($error).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$body = '
<!-- Header Premium -->
<div class="mutation-page-header">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <div>
      <div class="tech-label">ASSET LIFECYCLE / TRANSFER &amp; BAST</div>
      <h1 class="d-flex align-items-center gap-2 mb-1">
        <i class="bi bi-arrow-left-right"></i> Mutasi &amp; Serah Terima Aset
      </h1>
      <p>Relokasi perangkat antar-kantor cabang, pergantian pengguna, dan penerbitan Berita Acara Serah Terima (BAST).</p>
    </div>
    <div class="header-actions d-flex align-items-center gap-2 flex-shrink-0">
      <a href="'.e(module_url('asset_mutations.php')).'" class="btn">
        <i class="bi bi-journal-text me-1"></i> Riwayat Mutasi
      </a>
      <a href="'.e(module_url('dashboard.php')).'" class="btn">
        <i class="bi bi-arrow-left me-1"></i> Dashboard
      </a>
    </div>
  </div>
</div>

'.$errorHtml.'

<form method="post" action="'.e(module_url('asset_mutation.php')).'">
  <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
  <input type="hidden" name="asset_type" value="'.e($selectedType).'">

  <div class="row g-4">
    <!-- Kolom Kiri: Pemilihan & Informasi Aset Saat Ini -->
    <div class="col-lg-5">
      <div class="mutation-card">
        <div class="mutation-card-header">
          <h5>
            <span class="step-badge bg-primary text-white">1</span>
            <i class="bi bi-box-seam-fill text-primary"></i> Aset yang Dimutasi
          </h5>
          <div class="type-switcher btn-group btn-group-sm" role="group">
            <a href="?type=komputer" class="btn '.($selectedType === 'komputer' ? 'btn-primary active fw-bold' : 'btn-outline-secondary bg-white').'">Komputer / IT</a>
            <a href="?type=inventaris" class="btn '.($selectedType === 'inventaris' ? 'btn-primary active fw-bold' : 'btn-outline-secondary bg-white').'">Inventaris Lain</a>
          </div>
        </div>
        <div class="mutation-card-body">

        <!-- Tombol Utama: Buka Katalog Modal Pencarian -->
        <button type="button" class="btn btn-catalog-pulse w-100 mb-3 d-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#modalPilihAset">
          <i class="bi bi-grid-3x3-gap-fill fs-5"></i>
          <span>Buka Katalog &amp; Cari Aset</span>
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
              <option value="">-- Semua Cabang --</option>';

foreach ($cabangList as $c) {
    $cName = $c['nama_cabang'] ?? '';
    $body .= '<option value="'.e($cName).'">'.e($cName).'</option>';
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

        <!-- Dropdown Seleksi Perangkat -->
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
              SN: <span id="displaySn">'.e($currentSn).'</span>
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
          Setelah mutasi diproses, sistem akan otomatis mencatat riwayat perpindahan di log audit dan menerbitkan formulir <strong>BAST (Berita Acara Serah Terima)</strong> resmi.
        </div>
        </div><!-- /mutation-card-body -->
      </div><!-- /mutation-card left -->
    </div>

    <!-- Kolom Kanan: Tujuan Baru & Alasan Mutasi -->
    <div class="col-lg-7">
      <div class="mutation-card">
        <div class="mutation-card-header">
          <h5>
            <span class="step-badge bg-success text-white">2</span>
            <i class="bi bi-geo-alt-fill text-success"></i> Tujuan Mutasi &amp; Serah Terima
          </h5>
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
          </div>

          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Ruangan / Lokasi Penempatan Baru</label>
            <input type="text" class="form-control" name="ruangan_tujuan" placeholder="Contoh: Meja CS 2 / Ruang Teller">
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
          </div>

          <div class="col-12">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Alasan Mutasi / Pemindahan Aset <span class="text-danger">*</span></label>
            <select name="alasan_mutasi" class="form-select mb-2" required>
              <option value="">-- Pilih Alasan Resmi Mutasi --</option>
              <option value="Rotasi / Perpindahan Tugas Karyawan">Rotasi / Perpindahan Tugas Karyawan</option>
              <option value="Peremajaan / Penggantian Perangkat Baru">Peremajaan / Penggantian Perangkat Baru</option>
              <option value="Kebutuhan Penambahan Unit di Kantor Cabang">Kebutuhan Penambahan Unit di Kantor Cabang</option>
              <option value="Unit Cadangan Sementara (Unit Back-up)">Unit Cadangan Sementara (Unit Back-up)</option>
              <option value="Relokasi Kantor / Renovasi Ruangan">Relokasi Kantor / Renovasi Ruangan</option>
              <option value="Penarikan ke Kantor Pusat (Pool)">Penarikan ke Kantor Pusat (Pool)</option>
              <option value="Lainnya">Lainnya (Tuliskan pada catatan)</option>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Catatan / Kelengkapan Aksesoris</label>
            <textarea class="form-control" name="keterangan" rows="3" placeholder="Contoh: Disertakan Adaptor Charger original, Kabel Power, Mouse, dan Tas Laptop dalam kondisi baik."></textarea>
          </div>
        </div>

        <hr class="my-4 border-secondary opacity-15">

        <div class="d-flex justify-content-between align-items-center">
          <a href="'.e(module_url('assets.php')).'" class="btn-cancel-mutation btn">
            <i class="bi bi-x-circle me-1"></i> Batal
          </a>
          <button type="submit" class="btn-submit-mutation btn d-inline-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill"></i> Proses Mutasi &amp; Terbitkan BAST
          </button>
        </div>
        </div><!-- /mutation-card-body -->
      </div><!-- /mutation-card -->
    </div>
  </div>
</form>

<!-- Modal Pop-up: Katalog Pemilihan Aset (Pencarian & Filter Interaktif) -->
<div class="modal fade" id="modalPilihAset" tabindex="-1" aria-labelledby="modalPilihAsetLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title d-flex align-items-center gap-2" id="modalPilihAsetLabel">
            <i class="bi bi-grid-3x3-gap-fill text-primary"></i> Katalog Pemilihan Aset Mutasi
          </h5>
          <p>Klik tombol <strong>"Pilih Unit"</strong> pada perangkat yang ingin dipindahkan ke kantor cabang lain.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-4">
        <!-- Search & Filter Controls -->
        <div class="row g-3 mb-3">
          <div class="col-md-7">
            <label class="form-label small fw-bold text-uppercase text-muted" style="font-size: 0.68rem;">Pencarian Kata Kunci Cepat</label>
            <div class="input-group">
              <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-primary"></i></span>
              <input type="text" id="modalSearchInput" class="form-control border-start-0" placeholder="Ketik nama karyawan (mis: Siti / Yanti), kode inventaris, merk, nomor seri..." oninput="filterModalTable()" autocomplete="off">
              <button class="btn btn-outline-secondary" type="button" onclick="clearModalSearch()"><i class="bi bi-x-circle me-1"></i>Reset</button>
            </div>
          </div>
          <div class="col-md-5">
            <label class="form-label small fw-bold text-uppercase text-muted" style="font-size: 0.68rem;">Filter Berdasarkan Kantor Cabang</label>
            <select id="modalSelectCabang" class="form-select" onchange="onModalCabangSelect(this.value)">
              <option value="">-- Semua Kantor Cabang ('.count($assetsList).' Unit) --</option>';

foreach ($cabangList as $c) {
    $cName = $c['nama_cabang'] ?? '';
    $cCount = $cabangStats[$cName] ?? 0;
    $body .= '<option value="'.e($cName).'">'.e($cName).' ('.$cCount.' unit)</option>';
}

$body .= '
            </select>
          </div>
        </div>

        <!-- Quick Branch Pills -->
        <div class="d-flex flex-wrap gap-1.5 align-items-center mb-3 pb-2 border-bottom">
          <span class="small fw-bold text-muted me-1" style="font-size: 0.72rem;">Cabang Cepat:</span>
          <button type="button" class="btn btn-sm btn-primary active modal-branch-pill" data-cabang="" onclick="setModalBranchFilter(\'\', this)">Semua ('.count($assetsList).')</button>';

foreach ($cabangStats as $cName => $cCount) {
    $body .= '<button type="button" class="btn btn-sm btn-outline-secondary modal-branch-pill" data-cabang="'.e($cName).'" onclick="setModalBranchFilter(\''.e(addslashes($cName)).'\', this)">'.e($cName).' ('.$cCount.')</button>';
}

$body .= '
        </div>

        <!-- Responsive Table List -->
        <div class="table-responsive border rounded-3" style="max-height: 440px; overflow-y: auto;">
          <table class="table table-hover align-middle mb-0" style="font-size: 0.84rem;">
            <thead class="table-light sticky-top" style="z-index: 1;">
              <tr>
                <th class="text-center" style="width: 50px;">No</th>
                <th style="min-width: 150px;">Kode &amp; SN</th>
                <th style="min-width: 220px;">Perangkat &amp; Tipe</th>
                <th style="min-width: 170px;">Lokasi Asal</th>
                <th style="min-width: 150px;">Pengguna Saat Ini</th>
                <th class="text-center" style="width: 120px;">Aksi</th>
              </tr>
            </thead>
            <tbody id="modalAssetTbody">
              <!-- Rendered via JavaScript -->
            </tbody>
          </table>
        </div>
      </div>

      <div class="modal-footer bg-light py-2.5 px-4 d-flex justify-content-between align-items-center">
        <span class="text-muted small" id="modalAssetCount">Menampilkan '.count($assetsList).' unit</span>
        <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>';

$script = '
<script>
const ASSETS_ARRAY = ' . json_encode(array_values($assetsList)) . ';
const ASSETS_MAP = ' . json_encode($assetsForJs) . ';
const CURRENT_TYPE = ' . json_encode($selectedType) . ';

let currentModalBranch = "";
let currentModalQuery = "";

function onAssetChange(val) {
  val = parseInt(val) || 0;
  if (!val || !ASSETS_MAP[val]) {
    document.getElementById("displayKode").textContent = "Belum dipilih";
    document.getElementById("displayNama").textContent = "-";
    document.getElementById("displaySnWrapper").style.display = "none";
    document.getElementById("displayCabang").textContent = "-";
    document.getElementById("displayRuangan").textContent = "-";
    document.getElementById("displayPengguna").textContent = "-";
    document.getElementById("displayDivisi").textContent = "-";
    return;
  }

  const asset = ASSETS_MAP[val];
  document.getElementById("displayKode").textContent = asset.kode || "-";
  document.getElementById("displayNama").textContent = asset.nama || "-";
  if (asset.sn) {
    document.getElementById("displaySn").textContent = asset.sn;
    document.getElementById("displaySnWrapper").style.display = "block";
  } else {
    document.getElementById("displaySnWrapper").style.display = "none";
  }
  document.getElementById("displayCabang").textContent = asset.cabang || "-";
  document.getElementById("displayRuangan").textContent = asset.ruangan || "-";
  document.getElementById("displayPengguna").textContent = asset.pengguna || "-";
  document.getElementById("displayDivisi").textContent = asset.divisi || "-";

  // Highlight flash effect
  const box = document.getElementById("lokasiAsalBox");
  if (box) {
    box.style.backgroundColor = "#EFF6FF";
    box.style.borderColor = "#93C5FD";
    setTimeout(() => {
      box.style.backgroundColor = "#F8FAFC";
      box.style.borderColor = "#E2E8F0";
    }, 500);
  }

  // Update URL history state tanpa reload
  try {
    const url = new URL(window.location.href);
    url.searchParams.set("id", val);
    url.searchParams.set("type", CURRENT_TYPE);
    window.history.replaceState({}, "", url.toString());
  } catch(e) {}
}

function filterAssetList() {
  const q = (document.getElementById("quickSearchAsset")?.value || "").toLowerCase().trim();
  const c = (document.getElementById("filterCabangAsal")?.value || "").toLowerCase().trim();
  const sel = document.getElementById("selectAssetId");
  if (!sel) return;

  const curVal = parseInt(sel.value) || 0;
  sel.innerHTML = \'<option value="">-- Pilih Perangkat Terdaftar --</option>\';

  let count = 0;
  for (let i = 0; i < ASSETS_ARRAY.length; i++) {
    const a = ASSETS_ARRAY[i];
    if (c && a.cabang.toLowerCase() !== c) continue;
    if (q && !a.search_text.includes(q)) continue;

    count++;
    const opt = document.createElement("option");
    opt.value = a.id;
    opt.textContent = a.kode + " - " + a.nama + " (" + a.pengguna + " | " + a.cabang + ")";
    if (a.id === curVal) {
      opt.selected = true;
    }
    sel.appendChild(opt);
  }

  // Jika aset terpilih sebelumnya tidak cocok dengan filter, pertahankan agar tidak hilang
  if (curVal && !sel.querySelector(\'option[value="\' + curVal + \'"]\')) {
    const curAsset = ASSETS_MAP[curVal];
    if (curAsset) {
      const opt = document.createElement("option");
      opt.value = curAsset.id;
      opt.textContent = curAsset.kode + " - " + curAsset.nama + " (" + curAsset.pengguna + " | " + curAsset.cabang + ") [Terpilih]";
      opt.selected = true;
      sel.appendChild(opt);
    }
  }

  const badge = document.getElementById("matchCountText");
  if (badge) {
    badge.textContent = "Menampilkan " + count + " dari " + ASSETS_ARRAY.length + " unit terdaftar";
  }
}

function resetQuickFilter() {
  const qInput = document.getElementById("quickSearchAsset");
  const cSelect = document.getElementById("filterCabangAsal");
  if (qInput) qInput.value = "";
  if (cSelect) cSelect.value = "";
  filterAssetList();
}

function renderModalTable() {
  const tbody = document.getElementById("modalAssetTbody");
  const countEl = document.getElementById("modalAssetCount");
  if (!tbody) return;

  const q = currentModalQuery.toLowerCase().trim();
  const c = currentModalBranch.toLowerCase().trim();
  const selectedId = parseInt(document.getElementById("selectAssetId")?.value) || 0;

  let matches = 0;
  let html = "";

  for (let i = 0; i < ASSETS_ARRAY.length; i++) {
    const item = ASSETS_ARRAY[i];
    if (c && item.cabang.toLowerCase() !== c) continue;
    if (q && !item.search_text.includes(q)) continue;

    matches++;
    const isSelected = (item.id === selectedId);
    html += `
      <tr class="table-catalog-row ${isSelected ? \'table-primary\' : \'\'}" onclick="selectAssetAndCloseModal(${item.id})">
        <td class="text-center text-muted small">${matches}</td>
        <td>
          <div class="fw-bold font-monospace text-primary">${escapeHtml(item.kode)}</div>
          ${item.sn ? `<div class="small font-monospace text-muted" style="font-size:0.72rem;">SN: ${escapeHtml(item.sn)}</div>` : \'\'}
        </td>
        <td>
          <div class="fw-semibold text-dark">${escapeHtml(item.nama)}</div>
          <span class="badge bg-light text-secondary border small" style="font-size:0.7rem;">${escapeHtml(item.kategori || \'Unit\')}</span>
        </td>
        <td>
          <div class="text-dark fw-medium">${escapeHtml(item.cabang)}</div>
          <div class="text-muted small" style="font-size:0.72rem;"><i class="bi bi-geo-alt me-1"></i>${escapeHtml(item.ruangan || \'-\')}</div>
        </td>
        <td>
          <div class="fw-bold text-dark">${escapeHtml(item.pengguna || \'Umum\')}</div>
          <div class="text-muted small" style="font-size:0.72rem;">Div: ${escapeHtml(item.divisi || \'-\')}</div>
        </td>
        <td class="text-center" onclick="event.stopPropagation()">
          <button type="button" class="btn btn-sm ${isSelected ? \'btn-success\' : \'btn-primary\'} fw-bold px-3 py-1.5 shadow-sm" onclick="selectAssetAndCloseModal(${item.id})">
            <i class="bi ${isSelected ? \'bi-check-circle-fill\' : \'bi-check-lg\'} me-1"></i> ${isSelected ? \'Terpilih\' : \'Pilih Unit\'}
          </button>
        </td>
      </tr>
    `;
  }

  if (matches === 0) {
    html = `
      <tr>
        <td colspan="6" class="text-center py-5 text-muted">
          <i class="bi bi-search fs-2 d-block mb-2 text-secondary opacity-50"></i>
          Tidak ada aset yang sesuai dengan kriteria pencarian.<br>
          <button type="button" class="btn btn-link btn-sm mt-2 text-decoration-none" onclick="clearModalSearch()">Reset Pencarian</button>
        </td>
      </tr>
    `;
  }

  tbody.innerHTML = html;
  if (countEl) countEl.textContent = "Menampilkan " + matches + " dari " + ASSETS_ARRAY.length + " unit";
}

function filterModalTable() {
  currentModalQuery = document.getElementById("modalSearchInput")?.value || "";
  renderModalTable();
}

function clearModalSearch() {
  const input = document.getElementById("modalSearchInput");
  if (input) input.value = "";
  currentModalQuery = "";
  renderModalTable();
}

function setModalBranchFilter(branch, btn) {
  currentModalBranch = branch;
  const sel = document.getElementById("modalSelectCabang");
  if (sel) sel.value = branch;

  document.querySelectorAll(".modal-branch-pill").forEach(b => {
    b.classList.remove("btn-primary", "active");
    b.classList.add("btn-outline-secondary");
  });
  if (btn) {
    btn.classList.add("btn-primary", "active");
    btn.classList.remove("btn-outline-secondary");
  }

  renderModalTable();
}

function onModalCabangSelect(branch) {
  currentModalBranch = branch;
  document.querySelectorAll(".modal-branch-pill").forEach(b => {
    if (b.getAttribute("data-cabang") === branch) {
      b.classList.add("btn-primary", "active");
      b.classList.remove("btn-outline-secondary");
    } else {
      b.classList.remove("btn-primary", "active");
      b.classList.add("btn-outline-secondary");
    }
  });
  renderModalTable();
}

function selectAssetAndCloseModal(id) {
  const sel = document.getElementById("selectAssetId");
  if (sel) {
    let opt = sel.querySelector(\'option[value="\' + id + \'"]\');
    if (!opt) {
      const a = ASSETS_MAP[id];
      if (a) {
        opt = document.createElement("option");
        opt.value = a.id;
        opt.textContent = a.kode + " - " + a.nama + " (" + a.pengguna + " | " + a.cabang + ")";
        sel.appendChild(opt);
      }
    }
    sel.value = id;
    onAssetChange(id);
  }

  const modalEl = document.getElementById("modalPilihAset");
  if (modalEl) {
    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.hide();
  }

  setTimeout(() => {
    const dest = document.querySelector(\'select[name="cabang_tujuan"]\');
    if (dest) dest.focus();
  }, 300);
}

function escapeHtml(text) {
  if (!text) return "";
  return String(text)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/\'/g, "&#039;");
}

document.addEventListener("DOMContentLoaded", () => {
  const modalEl = document.getElementById("modalPilihAset");
  if (modalEl) {
    modalEl.addEventListener("show.bs.modal", () => {
      renderModalTable();
    });
  }
});
</script>';

render_page($title, $body, $head, $script);
