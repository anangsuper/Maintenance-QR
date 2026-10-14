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

// Ambil daftar seluruh aset aktif untuk dropdown pilihan jika id belum ditentukan
$komputerAssets = [];
if ($selectedType === 'komputer') {
    if (is_google_cloud_mode()) {
        $komputerAssets = map_sheets_assets();
    } else {
        try {
            $st = db()->query(asset_query_base() . " WHERE a.status = 'Aktif' ORDER BY a.kode_inventaris ASC LIMIT 1000");
            $komputerAssets = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            $komputerAssets = [];
        }
    }
}

$selectedAsset = null;
if ($selectedId > 0) {
    if ($selectedType === 'komputer') {
        $selectedAsset = get_asset_by_id($selectedId);
    } else {
        $selectedAsset = get_inventaris_kartu_by_id($selectedId);
    }
}

$title = 'Form Mutasi & Serah Terima Aset';
$head = '
<style>
.mutation-card-transfer {
  background: #FFFFFF;
  border: 1px solid #E2E8F0;
  border-radius: 16px;
  box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.06);
}
.transfer-arrow-circle {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: linear-gradient(135deg, #1D4ED8 0%, #2563EB 100%);
  color: #FFFFFF;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3rem;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
  margin: 0 auto;
}
.location-box {
  padding: 16px 20px;
  border-radius: 12px;
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
}
</style>';

$errorHtml = $error ? '<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" style="border-left: 4px solid #DC2626 !important;"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>'.e($error).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$currentCabang = '';
$currentRuangan = '';
$currentPengguna = '';
$currentDivisi = '';
$currentKode = '';
$currentNama = '';
$currentSn = '';

if ($selectedAsset) {
    if ($selectedType === 'komputer') {
        $currentKode = $selectedAsset['kode_inventaris'] ?? 'PC-' . $selectedId;
        $currentNama = asset_title($selectedAsset);
        $currentSn = $selectedAsset['serial_number'] ?? '';
        $currentCabang = $selectedAsset['cabang_nama'] ?? 'Kantor Pusat Operasional';
        $currentRuangan = $selectedAsset['ruangan'] ?? $selectedAsset['lokasi'] ?? '-';
        $currentPengguna = $selectedAsset['karyawan_nama'] ?? 'Umum';
        $currentDivisi = $selectedAsset['divisi_nama'] ?? '-';
    } else {
        $currentKode = $selectedAsset['nomor_rekening'] ?? 'INV-' . $selectedId;
        $currentNama = $selectedAsset['nama_barang'] ?? 'Aset Inventaris';
        $currentSn = $selectedAsset['nomor_seri_inventaris'] ?? '';
        $currentCabang = $selectedAsset['cabang_nama'] ?? 'Kantor Pusat';
        $currentRuangan = $selectedAsset['ruangan'] ?? '-';
        $currentPengguna = $selectedAsset['pengguna'] ?? 'Umum';
        $currentDivisi = $selectedAsset['golongan'] ?? '-';
    }
}

$body = '
<!-- Header Navigasi -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <div class="tech-label mb-1">ASSET LIFECYCLE / TRANSFER & BAST</div>
    <h1 class="h3 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
      <i class="bi bi-arrow-left-right text-primary"></i> Mutasi &amp; Serah Terima Aset
    </h1>
    <p class="text-secondary small mb-0">Relokasi perangkat antar-kantor cabang, pergantian pengguna, dan penerbitan Berita Acara Serah Terima (BAST).</p>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="'.e(module_url('asset_mutations.php')).'" class="btn btn-outline-secondary fw-semibold btn-sm px-3 bg-white">
      <i class="bi bi-journal-text me-1"></i> Riwayat Mutasi
    </a>
    <a href="'.e(module_url('dashboard.php')).'" class="btn btn-outline-secondary fw-semibold btn-sm px-3 bg-white">
      <i class="bi bi-arrow-left me-1"></i> Dashboard
    </a>
  </div>
</div>

'.$errorHtml.'

<form method="post" action="'.e(module_url('asset_mutation.php')).'">
  <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
  <input type="hidden" name="asset_type" value="'.e($selectedType).'">

  <div class="row g-4">
    <!-- Kolom Kiri: Pemilihan & Informasi Aset Saat Ini -->
    <div class="col-lg-5">
      <div class="card p-4 h-100 border-0 shadow-sm" style="border-radius: 16px;">
        <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
          <i class="bi bi-box-seam-fill text-primary"></i> 1. Aset yang Dimutasi
        </h5>

        <div class="mb-3">
          <label class="form-label text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Pilih Perangkat / Unit <span class="text-danger">*</span></label>
          <select name="asset_id" id="selectAssetId" class="form-select" onchange="onAssetChange(this.value)" required>
            <option value="">-- Pilih Aset Terdaftar --</option>';

if ($selectedType === 'komputer') {
    foreach ($komputerAssets as $ka) {
        $aid = (int)($ka['id'] ?? 0);
        $kTitle = e($ka['kode_inventaris'] ?? '') . ' - ' . e(asset_title($ka)) . ' (' . e($ka['cabang_nama'] ?? 'Pusat') . ')';
        $sel = ($aid === $selectedId) ? 'selected' : '';
        $body .= '<option value="'.$aid.'" '.$sel.'>'.$kTitle.'</option>';
    }
} elseif ($selectedAsset) {
    $body .= '<option value="'.$selectedId.'" selected>'.e($currentKode).' - '.e($currentNama).'</option>';
}

$body .= '
          </select>
          <div class="form-text small" style="font-size: 0.72rem;">Pilih perangkat komputer atau inventaris yang akan dipindahkan.</div>
        </div>

        <!-- Info Box Kondisi Asal -->
        <div class="location-box mt-3">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="badge bg-secondary px-2.5 py-1" style="font-size: 0.7rem; border-radius: 6px;">LOKASI ASAL SAAT INI</span>
            <span class="text-muted small" style="font-size: 0.72rem;"><i class="bi bi-shield-check text-success me-1"></i>Terverifikasi</span>
          </div>
          
          <div class="mb-2">
            <div class="small text-muted" style="font-size: 0.72rem;">KODE &amp; NAMA ASET:</div>
            <strong class="text-dark fs-6" id="displayKode">'.e($currentKode ?: 'Belum dipilih').'</strong>
            <div class="text-secondary small" id="displayNama">'.e($currentNama).'</div>
            '.($currentSn ? '<div class="small font-monospace text-muted mt-0.5">SN: '.e($currentSn).'</div>' : '').'
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

        <div class="alert alert-info border-0 mt-3 p-3 small mb-0" style="background: #EFF6FF; border-radius: 10px;">
          <i class="bi bi-info-circle-fill text-primary me-1"></i>
          Setelah mutasi diproses, sistem akan otomatis mencatat riwayat perpindahan di log audit dan menerbitkan formulir <strong>BAST (Berita Acara Serah Terima)</strong> resmi.
        </div>
      </div>
    </div>

    <!-- Kolom Kanan: Tujuan Baru & Alasan Mutasi -->
    <div class="col-lg-7">
      <div class="card p-4 h-100 border-0 shadow-sm" style="border-radius: 16px;">
        <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
          <i class="bi bi-geo-alt-fill text-success"></i> 2. Tujuan Mutasi &amp; Serah Terima
        </h5>

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
          <a href="'.e(module_url('assets.php')).'" class="btn btn-outline-secondary px-4 fw-semibold">Batal</a>
          <button type="submit" class="btn btn-primary px-4 py-2.5 fw-bold shadow-sm d-inline-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill"></i> Proses Mutasi &amp; Terbitkan BAST &raquo;
          </button>
        </div>
      </div>
    </div>
  </div>
</form>';

$script = '
<script>
function onAssetChange(val) {
  if (!val) return;
  // Reload form dengan aset yang dipilih
  const url = new URL(window.location.href);
  url.searchParams.set("id", val);
  url.searchParams.set("type", "'.e($selectedType).'");
  window.location.href = url.toString();
}
</script>';

render_page($title, $body, $head, $script);
