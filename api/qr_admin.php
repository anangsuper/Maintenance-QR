<?php
require __DIR__ . '/bootstrap.php';
require_admin();

$cabangId = max(0, (int)($_GET['cabang'] ?? 0));
$cabangs = get_cabang_list();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_missing') {
        $created = generate_missing_qr_tokens($cabangId);
        $_SESSION['flash'] = "{$created} token QR baru berhasil di-generate secara otomatis.";
        header('Location: ' . module_url('qr_admin.php', ['cabang'=>$cabangId]));
        exit;
    }

    if ($action === 'regenerate') {
        $assetId = (int)($_POST['asset_id'] ?? 0);
        if ($assetId > 0) {
            regenerate_qr_token($assetId);
            $_SESSION['flash'] = "Token QR berhasil diperbarui. QR lama otomatis di-revoke demi keamanan.";
        }
        header('Location: ' . module_url('qr_admin.php', ['cabang'=>$cabangId]));
        exit;
    }
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$rows = get_qr_admin_rows($cabangId);

// Hitung Statistik Token
$totalUnits = count($rows);
$countValid = 0;
$countMissing = 0;
$activeBranchName = 'Semua Kantor Cabang';

$opts = '';
foreach ($cabangs as $c) {
    $cId = (int)($c['id'] ?? 0);
    $cNama = $c['nama'] ?? $c['nama_cabang'] ?? 'Cabang #' . $cId;
    if ($cId === $cabangId) {
        $activeBranchName = $cNama;
    }
    $opts .= '<option value="'.$cId.'"'.($cId===$cabangId?' selected':'').'>'.e($cNama).'</option>';
}

$table = '';
$num = 0;
foreach ($rows as $r) {
    $num++;
    $aid = (int)($r['id'] ?? 0);
    $tok = $r['qr_token'] ?? '';
    $hasToken = !empty($tok);

    if ($hasToken) {
        $countValid++;
    } else {
        $countMissing++;
    }

    $url = $hasToken ? module_url('scan.php', ['t' => $tok]) : '';
    $kode = $r['kode_inventaris'] ?? ('ASET-' . $aid);
    $deviceTitle = asset_title($r);
    $userNama = !empty($r['karyawan_nama']) && $r['karyawan_nama'] !== '-' ? $r['karyawan_nama'] : 'Umum / Pool';
    $divisiNama = !empty($r['divisi_nama']) && $r['divisi_nama'] !== '-' ? $r['divisi_nama'] : '';
    $cabangNama = !empty($r['cabang_nama']) && $r['cabang_nama'] !== '-' ? $r['cabang_nama'] : 'KPO';
    $sn = $r['nomor_seri'] ?? '-';
    $kategori = $r['kategori'] ?? 'Komputer';

    $searchKeyword = strtolower($kode . ' ' . $deviceTitle . ' ' . $userNama . ' ' . $divisiNama . ' ' . $cabangNama . ' ' . $sn . ' ' . $kategori);

    if ($hasToken) {
        $qrStatus = '
        <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 py-1 px-2 rounded-pill shadow-xs" 
          onclick="openQrModal('.htmlspecialchars(json_encode([
              'id' => $aid,
              'kode' => $kode,
              'device' => $deviceTitle,
              'user' => $userNama . ($divisiNama ? " ({$divisiNama})" : ''),
              'cabang' => $cabangNama,
              'url' => $url,
              'token' => $tok
          ]), ENT_QUOTES, 'UTF-8').')" title="Klik untuk Pratinjau & Unduh QR">
          <i class="bi bi-qr-code fs-6"></i>
          <span class="small fw-semibold" style="font-size: 0.72rem;">LIHAT QR</span>
        </button>';
    } else {
        $qrStatus = '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1 rounded-pill font-monospace" style="font-size: 0.72rem;"><i class="bi bi-dash-circle me-1"></i>BELUM ADA</span>';
    }

    $action = '<div class="btn-group btn-group-sm">';
    $action .= '<a class="btn btn-outline-secondary" href="'.e(module_url('asset_edit.php', ['id'=>$aid])).'" title="Edit Detail Aset"><i class="bi bi-pencil-square"></i></a>';
    if ($url) {
        $action .= '<a class="btn btn-outline-primary" target="_blank" href="'.e(module_url('print_qr.php', ['asset_id'=>$aid])).'" title="Cetak Stiker Label"><i class="bi bi-printer"></i></a>';
    }
    $action .= '</div>';

    $action .= '<form method="post" class="d-inline ms-1">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="regenerate">
      <input type="hidden" name="asset_id" value="'.$aid.'">
      <button class="btn btn-sm btn-outline-warning text-dark" title="Generate Ulang & Revoke Token QR" onclick="return confirm(\'Generate ulang token QR ini? QR lama otomatis tidak berlaku lagi demi keamanan.\')"><i class="bi bi-arrow-repeat"></i></button>
    </form>';

    $action .= '<a class="btn btn-sm btn-outline-danger ms-1" href="'.e(module_url('asset_delete.php', ['id'=>$aid])).'" title="Hapus Aset" onclick="return confirm(\'Hapus aset '.e(addslashes($kode)).' ini dari sistem?\')"><i class="bi bi-trash"></i></a>';

    $table .= '<tr class="asset-table-row" id="row-asset-'.$aid.'" data-search="'.e($searchKeyword).'" data-has-token="'.($hasToken ? '1' : '0').'">
      <td class="text-center">
        <input type="checkbox" class="form-check-input row-chk" value="'.$aid.'" onchange="onRowCheckChange()">
      </td>
      <td class="text-center font-monospace text-muted small">'.sprintf('%02d', $num).'</td>
      <td>
        <span class="font-monospace fw-bold text-primary" style="font-size: 0.88rem;">'.e($kode).'</span>
      </td>
      <td>
        <div class="fw-semibold text-dark">'.e($deviceTitle).'</div>
        <div class="text-muted small" style="font-size: 0.75rem;">SN: '.e($sn).' · Tipe: '.e($kategori).'</div>
      </td>
      <td>
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-person text-secondary"></i>
          <div>
            <div class="fw-semibold text-dark">'.e($userNama).'</div>
            '.($divisiNama ? '<div class="text-muted small" style="font-size: 0.72rem;">'.e($divisiNama).'</div>' : '').'
          </div>
        </div>
      </td>
      <td><span class="badge bg-light text-dark border">'.e($cabangNama).'</span></td>
      <td class="text-center">'.$qrStatus.'</td>
      <td class="text-nowrap text-end">'.$action.'</td>
    </tr>';
}

if (!$table) {
    $table = '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-qr-code fs-1 d-block mb-2 opacity-50"></i>Tidak ada inventaris aset yang memerlukan label QR di cabang ini.</td></tr>';
}

$flashHtml = $flash ? '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" style="border-left: 4px solid #10B981 !important;"><i class="bi bi-check-circle-fill me-2 text-success"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$head = '
<style>
.kpi-qr-card {
  border-radius: 12px;
  border: 1px solid #E2E8F0;
  background: #ffffff;
  padding: 16px 20px;
  box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.kpi-qr-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 16px rgba(15, 23, 42, 0.08);
}
.floating-bulk-bar {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%) translateY(120px);
  background: rgba(15, 23, 42, 0.94);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  color: #ffffff;
  border-radius: 40px;
  padding: 10px 22px;
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
  display: flex;
  align-items: center;
  gap: 14px;
  z-index: 1050;
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease;
  opacity: 0;
  pointer-events: none;
}
.floating-bulk-bar.active {
  transform: translateX(-50%) translateY(0);
  opacity: 1;
  pointer-events: auto;
}
.filter-chip-btn {
  font-size: 0.78rem;
  font-weight: 600;
  border-radius: 20px;
  padding: 4px 14px;
  border: 1px solid #E2E8F0;
  background: #ffffff;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease;
}
.filter-chip-btn:hover, .filter-chip-btn.active {
  background: #2563EB;
  border-color: #2563EB;
  color: #ffffff;
  box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
}
</style>';

$body = '
'.$flashHtml.'

<!-- Header Kicker & Action Bar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <div class="text-uppercase small fw-bold" style="letter-spacing: 0.08em; color: var(--app-accent); font-size: 0.72rem; margin-bottom: 2px;">SYSTEM / QR ASSET LABEL REGISTRY</div>
    <h2 class="h3 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
      <i class="bi bi-qr-code text-primary"></i> Manajemen Label QR Aset
    </h2>
    <div class="text-muted small">Kelola token QR terenkripsi, pratinjau kode QR langsung di layar, dan cetak stiker label bodi komputer.</div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary fw-semibold btn-sm rounded-pill px-3" href="'.e(module_url('dashboard.php')).'"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
    <a class="btn btn-action-add fw-semibold btn-sm rounded-pill px-3" href="'.e(module_url('asset_add.php')).'"><i class="bi bi-plus-lg me-1"></i> Tambah Aset</a>
    <a class="btn btn-primary fw-semibold btn-sm rounded-pill px-3 shadow-sm" target="_blank" href="'.e(module_url('print_qr.php', ['cabang'=>$cabangId])).'"><i class="bi bi-printer-fill me-1"></i> Cetak Stiker QR Cabang Ini</a>
    <a class="btn btn-outline-primary fw-semibold btn-sm rounded-pill px-3" target="_blank" href="'.e(module_url('print_inventory_card.php', ['cabang'=>$cabangId])).'"><i class="bi bi-credit-card-2-front me-1"></i> Cetak Kartu Inventaris</a>
  </div>
</div>

<!-- KPI Metrics Cards Row -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="kpi-qr-card">
      <div class="text-secondary small fw-semibold mb-1"><i class="bi bi-pc-display me-1 text-primary"></i>Total Unit Aset</div>
      <div class="fs-4 fw-bold text-dark">'.$totalUnits.' <span class="fs-6 fw-normal text-muted">Unit</span></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-qr-card">
      <div class="text-secondary small fw-semibold mb-1"><i class="bi bi-qr-code-scan me-1 text-success"></i>Token QR Aktif</div>
      <div class="fs-4 fw-bold text-success">'.$countValid.' <span class="fs-6 fw-normal text-muted">Siap Scan</span></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-qr-card">
      <div class="text-secondary small fw-semibold mb-1"><i class="bi bi-exclamation-circle me-1 text-warning"></i>Belum Punya Token</div>
      <div class="fs-4 fw-bold '.($countMissing > 0 ? 'text-warning' : 'text-muted').'">'.$countMissing.' <span class="fs-6 fw-normal text-muted">Unit</span></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-qr-card">
      <div class="text-secondary small fw-semibold mb-1"><i class="bi bi-building me-1 text-info"></i>Kantor Terpilih</div>
      <div class="fs-5 fw-bold text-dark text-truncate" title="'.e($activeBranchName).'">'.e($activeBranchName).'</div>
    </div>
  </div>
</div>

<!-- Toolbar Box & Instant Filter -->
<div class="card p-3 mb-4 border shadow-sm" style="border-radius: 12px; border-color: var(--app-border) !important; background: #fff;">
  <div class="row g-3 align-items-center">
    <!-- Filter Cabang -->
    <div class="col-md-4">
      <form method="get" class="d-flex gap-2">
        <select class="form-select form-select-sm" name="cabang" onchange="this.form.submit()">
          <option value="0">🏢 Semua Kantor Cabang</option>
          '.$opts.'
        </select>
        <noscript><button class="btn btn-primary btn-sm">Terapkan</button></noscript>
      </form>
    </div>

    <!-- Live Search Table -->
    <div class="col-md-5">
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
        <input type="text" id="tableSearchInput" class="form-control border-start-0" placeholder="Cari kode, nama user, seri, perangkat..." oninput="onFilterTable()">
        <button class="btn btn-outline-secondary border-start-0" type="button" onclick="clearTableSearch()" title="Reset"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>

    <!-- Batch Generate Action -->
    <div class="col-md-3 text-md-end">
      '.($countMissing > 0 ? '
      <form method="post" class="d-inline">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="action" value="generate_missing">
        <button class="btn btn-warning btn-sm w-100 fw-semibold text-dark shadow-xs rounded-pill">
          <i class="bi bi-magic me-1"></i> Generate '.$countMissing.' Token Kosong
        </button>
      </form>' : '
      <button class="btn btn-outline-secondary btn-sm w-100 rounded-pill" disabled>
        <i class="bi bi-shield-check text-success me-1"></i> Semua Token Sudah Ada
      </button>').'
    </div>
  </div>

  <!-- Filter Status Tabs -->
  <div class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top">
    <span class="small fw-bold text-secondary me-1">Filter Status:</span>
    <button type="button" class="filter-chip-btn active" id="chipAll" onclick="setTokenFilter(\'all\')">
      Semua (<span id="countChipAll">'.$totalUnits.'</span>)
    </button>
    <button type="button" class="filter-chip-btn" id="chipValid" onclick="setTokenFilter(\'valid\')">
      ✓ Token Valid (<span id="countChipValid">'.$countValid.'</span>)
    </button>
    <button type="button" class="filter-chip-btn" id="chipMissing" onclick="setTokenFilter(\'missing\')">
      ✗ Belum Ada (<span id="countChipMissing">'.$countMissing.'</span>)
    </button>
    <span class="ms-auto small text-muted" id="filterStatusText">Menampilkan '.$totalUnits.' aset</span>
  </div>
</div>

<!-- Table Card -->
<div class="card p-0 border shadow-sm" style="border-radius: 12px; border-color: var(--app-border) !important; background: #fff; overflow: hidden;">
  <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center" style="border-bottom: 1px solid var(--app-border);">
    <div class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.05em;">
      <i class="bi bi-qr-code-scan text-primary me-2"></i>Daftar Label QR Aset Terdaftar
    </div>
    <div class="small text-muted">
      Centang kotak di sebelah nomor untuk memilih aset dan mencetak secara kolektif.
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" id="qrAssetTable">
      <thead style="background-color: var(--app-navy); color: #ffffff;">
        <tr>
          <th style="width: 40px; background-color: var(--app-navy); color: #ffffff;" class="text-center">
            <input type="checkbox" class="form-check-input" id="checkAllRows" onchange="toggleSelectAllRows(this)" title="Pilih Semua Aset">
          </th>
          <th style="width: 45px; background-color: var(--app-navy); color: #ffffff;" class="text-center font-monospace">NO</th>
          <th style="width: 140px; background-color: var(--app-navy); color: #ffffff;">KODE INVENTARIS</th>
          <th style="background-color: var(--app-navy); color: #ffffff;">DETAIL PERANGKAT</th>
          <th style="background-color: var(--app-navy); color: #ffffff;">PENGGUNA / PIC</th>
          <th style="background-color: var(--app-navy); color: #ffffff;">KANTOR CABANG</th>
          <th style="width: 130px; background-color: var(--app-navy); color: #ffffff;" class="text-center">STATUS TOKEN</th>
          <th style="width: 130px; background-color: var(--app-navy); color: #ffffff;" class="text-end pe-3">AKSI</th>
        </tr>
      </thead>
      <tbody>'.$table.'</tbody>
    </table>
  </div>
</div>

<!-- Floating Bulk Action Bar -->
<div id="bulkActionBar" class="floating-bulk-bar shadow-lg">
  <div class="d-flex align-items-center gap-2">
    <span class="badge bg-primary fs-6 px-3 py-2 rounded-pill"><span id="bulkSelectedCount">0</span> Aset Dipilih</span>
  </div>
  <div class="vr bg-secondary opacity-50 mx-1"></div>
  <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold shadow-xs" onclick="printBulkSelected(\'qr\')">
    <i class="bi bi-printer-fill me-1"></i> Cetak Stiker QR Terpilih
  </button>
  <button type="button" class="btn btn-outline-light btn-sm rounded-pill px-3 fw-semibold" onclick="printBulkSelected(\'card\')">
    <i class="bi bi-credit-card-2-front me-1"></i> Cetak Kartu Inventaris
  </button>
  <button type="button" class="btn btn-sm btn-link text-white text-decoration-none ms-1" onclick="clearBulkSelection()">
    <i class="bi bi-x-circle me-1"></i> Batal
  </button>
</div>

<!-- Modal Quick QR Preview -->
<div class="modal fade" id="quickQrModal" tabindex="-1" aria-labelledby="quickQrModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header text-white" style="background: linear-gradient(135deg, #1E3A60 0%, #2E77AD 100%);">
        <div>
          <div class="small text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.85) !important;">PRATINJAU STIKER QR</div>
          <h5 class="modal-title fw-bold text-white mb-0" id="modalAssetKode" style="color: #ffffff !important;">INV-IT-001</h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <!-- QR Code Canvas Container -->
        <div class="d-inline-block p-3 bg-white border border-2 border-primary border-opacity-25 rounded-3 shadow-sm mb-3 position-relative">
          <div id="modalQrBox" style="width: 200px; height: 200px; margin: 0 auto; display: flex; align-items: center; justify-content: center;"></div>
        </div>

        <!-- Detail Perangkat -->
        <div class="bg-light p-3 rounded-3 text-start mb-3 border">
          <div class="row g-2 small">
            <div class="col-4 text-muted">Perangkat:</div>
            <div class="col-8 fw-bold text-dark text-truncate" id="modalAssetDevice">-</div>
            <div class="col-4 text-muted">Pengguna:</div>
            <div class="col-8 fw-semibold text-primary text-truncate" id="modalAssetUser">-</div>
            <div class="col-4 text-muted">Kantor Cabang:</div>
            <div class="col-8 text-dark" id="modalAssetCabang">-</div>
          </div>
        </div>

        <!-- Input Link Scan & Copy -->
        <div class="input-group input-group-sm mb-3">
          <input type="text" id="modalScanUrlInput" class="form-control font-monospace" readonly style="background: #F1F5F9; font-size: 0.75rem;">
          <button class="btn btn-outline-secondary" type="button" onclick="copyModalScanUrl(this)" title="Salin Link">
            <i class="bi bi-clipboard"></i> Salin
          </button>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex flex-wrap gap-2 justify-content-center pt-2">
          <a id="modalBtnPrint" href="#" target="_blank" class="btn btn-primary btn-sm px-3 rounded-pill fw-semibold shadow-xs">
            <i class="bi bi-printer-fill me-1"></i> Cetak Stiker
          </a>
          <button type="button" class="btn btn-outline-primary btn-sm px-3 rounded-pill fw-semibold" onclick="downloadModalQrPng()">
            <i class="bi bi-download me-1"></i> Unduh Gambar QR (PNG)
          </button>
          <a id="modalBtnOpenScan" href="#" target="_blank" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
            <i class="bi bi-box-arrow-up-right me-1"></i> Buka Halaman Scan
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Floating Copy Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
  <div id="qrAdminToast" class="toast align-items-center text-white bg-dark border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill text-success fs-5"></i>
        <span>Link scan QR berhasil disalin!</span>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>';

$logoDataUri = app_logo_url();

$script = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
var appLogoUri = '.json_encode($logoDataUri).';
var currentTokenFilter = "all";
var currentModalData = null;

// =========================================================================
// 1. FILTER TABEL REALTIME & CHIPS
// =========================================================================
function setTokenFilter(filterType) {
  currentTokenFilter = filterType;
  var chips = {
    all: document.getElementById("chipAll"),
    valid: document.getElementById("chipValid"),
    missing: document.getElementById("chipMissing")
  };
  Object.keys(chips).forEach(function(k){
    if (chips[k]) chips[k].classList.toggle("active", k === filterType);
  });
  onFilterTable();
}

function onFilterTable() {
  var searchInput = document.getElementById("tableSearchInput");
  var keyword = (searchInput ? searchInput.value : "").toLowerCase().trim();
  var rows = document.querySelectorAll(".asset-table-row");
  var visibleCount = 0;
  var totalCount = rows.length;

  rows.forEach(function(row) {
    var text = row.getAttribute("data-search") || "";
    var hasToken = row.getAttribute("data-has-token") === "1";

    var matchKeyword = (keyword === "" || text.indexOf(keyword) !== -1);
    var matchFilter = true;
    if (currentTokenFilter === "valid") {
      matchFilter = hasToken;
    } else if (currentTokenFilter === "missing") {
      matchFilter = !hasToken;
    }

    if (matchKeyword && matchFilter) {
      row.style.display = "";
      visibleCount++;
    } else {
      row.style.display = "none";
    }
  });

  var statusText = document.getElementById("filterStatusText");
  if (statusText) {
    statusText.textContent = "Menampilkan " + visibleCount + " dari " + totalCount + " aset";
  }
}

function clearTableSearch() {
  var searchInput = document.getElementById("tableSearchInput");
  if (searchInput) {
    searchInput.value = "";
    onFilterTable();
  }
}

// =========================================================================
// 2. CHECKBOX BULK SELECTION
// =========================================================================
function toggleSelectAllRows(masterChk) {
  var rows = document.querySelectorAll(".asset-table-row");
  rows.forEach(function(row) {
    if (row.style.display !== "none") {
      var chk = row.querySelector(".row-chk");
      if (chk) chk.checked = masterChk.checked;
    }
  });
  onRowCheckChange();
}

function onRowCheckChange() {
  var selected = document.querySelectorAll(".row-chk:checked");
  var count = selected.length;
  var bulkBar = document.getElementById("bulkActionBar");
  var countSpan = document.getElementById("bulkSelectedCount");

  if (countSpan) countSpan.textContent = count;

  if (bulkBar) {
    if (count > 0) {
      bulkBar.classList.add("active");
    } else {
      bulkBar.classList.remove("active");
    }
  }

  var allVisible = document.querySelectorAll(".asset-table-row:not([style*=\'display: none\']) .row-chk");
  var allChecked = (allVisible.length > 0 && document.querySelectorAll(".asset-table-row:not([style*=\'display: none\']) .row-chk:checked").length === allVisible.length);
  var masterChk = document.getElementById("checkAllRows");
  if (masterChk) masterChk.checked = allChecked;
}

function clearBulkSelection() {
  document.querySelectorAll(".row-chk").forEach(function(c){ c.checked = false; });
  var masterChk = document.getElementById("checkAllRows");
  if (masterChk) masterChk.checked = false;
  onRowCheckChange();
}

function printBulkSelected(type) {
  var selected = document.querySelectorAll(".row-chk:checked");
  var ids = [];
  selected.forEach(function(c){ ids.push(c.value); });
  if (ids.length === 0) {
    alert("Pilih minimal 1 aset untuk dicetak.");
    return;
  }
  var targetUrl = (type === "qr")
    ? "'.e(module_url('print_qr.php')).'?ids=" + encodeURIComponent(ids.join(","))
    : "'.e(module_url('print_inventory_card.php')).'?ids=" + encodeURIComponent(ids.join(","));
  window.open(targetUrl, "_blank");
}

// =========================================================================
// 3. MODAL QUICK QR PREVIEW
// =========================================================================
function openQrModal(data) {
  currentModalData = data;
  document.getElementById("modalAssetKode").textContent = data.kode || "-";
  document.getElementById("modalAssetDevice").textContent = data.device || "-";
  document.getElementById("modalAssetUser").textContent = data.user || "-";
  document.getElementById("modalAssetCabang").textContent = data.cabang || "-";
  document.getElementById("modalScanUrlInput").value = data.url || "";

  document.getElementById("modalBtnPrint").href = "'.e(module_url('print_qr.php')).'?asset_id=" + data.id;
  document.getElementById("modalBtnOpenScan").href = data.url;

  var qrBox = document.getElementById("modalQrBox");
  qrBox.innerHTML = "";

  if (typeof QRCode !== "undefined") {
    new QRCode(qrBox, {
      text: data.url,
      width: 200,
      height: 200,
      correctLevel: QRCode.CorrectLevel.H
    });

    // Attach logo to QR canvas
    attachLogoToQr(qrBox, appLogoUri);
  }

  var modalEl = document.getElementById("quickQrModal");
  if (modalEl && typeof bootstrap !== "undefined") {
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  }
}

function attachLogoToQr(containerEl, logoUri) {
  if (!logoUri) return;
  var tries = 0;
  var timer = setInterval(function(){
    tries++;
    var canvas = containerEl.querySelector("canvas");
    if (canvas && canvas.width > 0) {
      clearInterval(timer);
      drawLogoOnCanvas(canvas, logoUri, containerEl);
    } else if (tries > 25) {
      clearInterval(timer);
    }
  }, 40);
}

function drawLogoOnCanvas(canvas, logoUri, containerEl) {
  var ctx = canvas.getContext("2d");
  var img = new Image();
  img.onload = function() {
    var size = canvas.width;
    var logoSize = Math.round(size * 0.22);
    var center = Math.round((size - logoSize) / 2);
    var pad = Math.round(size * 0.025);

    var bgX = center - pad;
    var bgY = center - pad;
    var bgW = logoSize + (pad * 2);
    var bgH = logoSize + (pad * 2);
    var rad = Math.round(size * 0.035);

    // Rounded background
    ctx.save();
    ctx.fillStyle = "#FFFFFF";
    ctx.beginPath();
    ctx.moveTo(bgX + rad, bgY);
    ctx.lineTo(bgX + bgW - rad, bgY);
    ctx.quadraticCurveTo(bgX + bgW, bgY, bgX + bgW, bgY + rad);
    ctx.lineTo(bgX + bgW, bgY + bgH - rad);
    ctx.quadraticCurveTo(bgX + bgW, bgY + bgH, bgX + bgW - rad, bgY + bgH);
    ctx.lineTo(bgX + rad, bgY + bgH);
    ctx.quadraticCurveTo(bgX, bgY + bgH, bgX, bgY + bgH - rad);
    ctx.lineTo(bgX, bgY + rad);
    ctx.quadraticCurveTo(bgX, bgY, bgX + rad, bgY);
    ctx.closePath();
    ctx.fill();

    // Subtle cyan border
    ctx.strokeStyle = "#30B0E0";
    ctx.lineWidth = Math.max(1, Math.round(size * 0.012));
    ctx.stroke();
    ctx.restore();

    // Draw logo
    var aspect = (img.naturalWidth && img.naturalHeight) ? (img.naturalWidth / img.naturalHeight) : 1;
    var dw = logoSize;
    var dh = logoSize;
    if (aspect > 1) {
      dh = logoSize / aspect;
    } else {
      dw = logoSize * aspect;
    }
    var dx = center + (logoSize - dw) / 2;
    var dy = center + (logoSize - dh) / 2;

    ctx.drawImage(img, dx, dy, dw, dh);

    var qImg = containerEl.querySelector("img");
    if (qImg) {
      try { qImg.src = canvas.toDataURL("image/png"); } catch(e) {}
    }
  };
  img.src = logoUri;
}

function downloadModalQrPng() {
  var canvas = document.querySelector("#modalQrBox canvas");
  if (!canvas) {
    alert("Gambar QR belum selesai dirender.");
    return;
  }
  var kode = (currentModalData && currentModalData.kode) ? currentModalData.kode : "aset";
  var a = document.createElement("a");
  a.download = "QR-" + kode + ".png";
  a.href = canvas.toDataURL("image/png");
  a.click();
}

function copyModalScanUrl(btn) {
  var input = document.getElementById("modalScanUrlInput");
  if (!input || !input.value) return;
  var url = input.value;
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(url).then(function() {
      showToastNotification("Link scan QR berhasil disalin!");
    }).catch(function() {
      fallbackCopyText(url);
    });
  } else {
    fallbackCopyText(url);
  }
}

function fallbackCopyText(text) {
  var ta = document.createElement("textarea");
  ta.value = text;
  document.body.appendChild(ta);
  ta.select();
  document.execCommand("copy");
  document.body.removeChild(ta);
  showToastNotification("Link scan QR berhasil disalin!");
}

function showToastNotification(msg) {
  var toastEl = document.getElementById("qrAdminToast");
  if (toastEl) {
    var span = toastEl.querySelector(".toast-body span");
    if (span) span.textContent = msg;
    if (typeof bootstrap !== "undefined" && bootstrap.Toast) {
      var bsToast = new bootstrap.Toast(toastEl, { delay: 2200 });
      bsToast.show();
    } else {
      toastEl.classList.add("show");
      setTimeout(function(){ toastEl.classList.remove("show"); }, 2200);
    }
  } else {
    alert(msg);
  }
}
</script>';

render_page('QR Aset', $body, $head, $script);
