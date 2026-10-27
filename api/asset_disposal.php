<?php
/**
 * Halaman Pengelolaan Penghapusan & Pemusnahan Aset Inventaris (BA-Afkir)
 * PT BPR Mitratama Arthabuana
 * Khusus Bagian SDM & Umum, IT Operations, dan Manajemen
 * Sesuai Kebijakan Bank: Aset tidak diperjualbelikan, murni penghapusan/pemusnahan barang rusak
 */

require __DIR__ . '/bootstrap.php';
require_login();

if (!is_admin() && !is_it() && !is_hrd()) {
    http_response_code(403);
    die('Akses ditolak. Anda tidak memiliki hak akses untuk modul penghapusan aset.');
}

// 0. API Endpoint: Ambil Daftar Aset Aktif secara Asinkron (Lazy-Load)
if (isset($_GET['action']) && $_GET['action'] === 'get_active_assets') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, max-age=60');

    $activeInventaris = [];
    try {
        $st = db()->query("
            SELECT id, nomor_rekening, nama_barang, lokasi, ruangan, pengguna, nomor_seri_inventaris, golongan, status_inventaris
            FROM inventaris_kartu
            WHERE (status_inventaris IS NULL OR (
                status_inventaris NOT LIKE '%Dihapuskan%'
                AND status_inventaris NOT LIKE '%Dijual%'
                AND status_inventaris NOT LIKE '%Afkir%'
            ))
            ORDER BY id DESC
        ");
        while ($ir = $st->fetch(PDO::FETCH_ASSOC)) {
            $activeInventaris[] = [
                'id' => (int)$ir['id'],
                'type' => 'inventaris',
                'kode' => (string)($ir['nomor_rekening'] ?? ''),
                'nama' => (string)($ir['nama_barang'] ?? ''),
                'cabang' => (string)(!empty($ir['lokasi']) ? $ir['lokasi'] : 'Kantor Pusat (KPO)'),
                'ruangan' => (string)($ir['ruangan'] ?? ''),
                'pengguna' => (string)(!empty($ir['pengguna']) ? $ir['pengguna'] : 'Umum / Pool'),
                'sn' => (string)($ir['nomor_seri_inventaris'] ?? ''),
                'merk' => (string)(!empty($ir['golongan']) ? $ir['golongan'] : 'Perabot / Fasilitas'),
            ];
        }
    } catch (Throwable $e) {}

    $activeComputers = [];
    try {
        $st = db()->query("
            SELECT a.id, a.kode_inventaris, a.merk, a.model, a.serial_number, a.ruangan, c.nama_cabang, k.nama_karyawan
            FROM assets a
            LEFT JOIN cabang c ON a.id_cabang = c.id
            LEFT JOIN karyawan k ON a.id_karyawan = k.id
            WHERE a.status = 'Aktif'
            ORDER BY a.id DESC
        ");
        while ($cr = $st->fetch(PDO::FETCH_ASSOC)) {
            $activeComputers[] = [
                'id' => (int)$cr['id'],
                'type' => 'komputer',
                'kode' => (string)($cr['kode_inventaris'] ?? ''),
                'nama' => trim(($cr['merk'] ?? '') . ' ' . ($cr['model'] ?? '')),
                'cabang' => (string)(!empty($cr['nama_cabang']) ? $cr['nama_cabang'] : 'Kantor Pusat (KPO)'),
                'ruangan' => (string)($cr['ruangan'] ?? ''),
                'pengguna' => (string)(!empty($cr['nama_karyawan']) ? $cr['nama_karyawan'] : 'Staff IT / Operasional'),
                'sn' => (string)($cr['serial_number'] ?? ''),
                'merk' => (string)($cr['merk'] ?? ''),
            ];
        }
    } catch (Throwable $e) {}

    echo json_encode([
        'success' => true,
        'inventaris' => $activeInventaris,
        'komputer' => $activeComputers,
    ]);
    exit;
}

// 1. Ekspor ke Format Excel (CSV UTF-8 BOM)
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $exportFilters = [
        'search' => $_GET['search'] ?? '',
        'cabang' => $_GET['cabang'] ?? '',
        'tahun' => $_GET['tahun'] ?? '',
    ];
    $exportRows = get_disposal_list($exportFilters);

    $filename = 'Rekap_Penghapusan_Aset_Afkir_Bank_Mitra_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // Write UTF-8 BOM for Microsoft Excel compatibility
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($out, [
        'No', 'Nomor Berita Acara', 'Tanggal Pemusnahan', 'Tipe Aset',
        'Kode Aset / No. Rek', 'Nama Barang', 'Merk / Model', 'Serial Number',
        'Kantor Cabang', 'Ruangan', 'Pengguna Terakhir', 'Kondisi Fisik',
        'Metode Pemusnahan', 'Alasan Penghapusan', 'Petugas Pemeriksa',
        'Mengetahui (Kabag SDM)', 'Menyetujui (Direktur Ops)'
    ]);

    $no = 1;
    foreach ($exportRows as $r) {
        fputcsv($out, [
            $no++,
            $r['nomor_ba'],
            $r['tanggal_disposal'],
            ($r['asset_type'] === 'inventaris' ? 'Kartu Inventaris SDM' : 'Perangkat IT'),
            $r['kode_aset'],
            $r['nama_aset'],
            $r['merk_model'],
            $r['serial_number'],
            $r['cabang'],
            $r['lokasi_ruangan'],
            $r['pengguna_terakhir'],
            $r['kondisi_terakhir'],
            $r['metode_pemusnahan'],
            $r['alasan_penghapusan'],
            $r['petugas_nama'],
            $r['nama_mengetahui'],
            $r['nama_menyetujui']
        ]);
    }
    fclose($out);
    exit;
}

// 2. Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $res = save_asset_disposal($_POST, $_FILES);
        if ($res['success']) {
            $_SESSION['flash'] = $res['message'];
            if (!empty($_POST['print_after'])) {
                header('Location: ' . module_url('print_bast_disposal.php', ['id' => $res['id']]));
                exit;
            }
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        header('Location: ' . module_url('asset_disposal.php'));
        exit;
    }

    if ($action === 'delete' || $action === 'restore') {
        if (!function_exists('is_admin_or_hrd') || !is_admin_or_hrd()) {
            $_SESSION['flash_error'] = 'Hanya Administrator atau Bagian SDM & Umum yang berhak membatalkan penghapusan aset.';
        } else {
            $delId = (int)($_POST['id'] ?? 0);
            $disp = get_disposal_by_id($delId);
            if (!$disp) {
                $_SESSION['flash_error'] = 'Data Berita Acara penghapusan tidak ditemukan.';
            } else {
                $check = is_disposal_restorable($disp);
                if (!$check['can_restore']) {
                    $_SESSION['flash_error'] = 'Penghapusan aset "' . e($disp['nama_aset']) . '" tidak dapat dibatalkan karena telah melewati batas masa tenggang 7 hari (status penghapusan sudah permanen).';
                } else {
                    if (delete_asset_disposal($delId, true, true)) {
                        $_SESSION['flash'] = '✓ Berhasil dibatalkan! Aset "' . e($disp['nama_aset']) . '" (' . e($disp['kode_aset']) . ') telah dipulihkan kembali ke register inventaris aktif.';
                    } else {
                        $_SESSION['flash_error'] = 'Gagal memproses pengembalian aset ke inventaris.';
                    }
                }
            }
        }
        header('Location: ' . module_url('asset_disposal.php'));
        exit;
    }

    if ($action === 'upload_acc') {
        $idAcc = (int)($_POST['id'] ?? 0);
        $fileUploaded = upload_disposal_file('dokumen_ba_disetujui', 'afkir_ba_acc');
        if ($idAcc > 0 && $fileUploaded) {
            db()->prepare("UPDATE asset_disposals SET dokumen_ba_disetujui = ? WHERE id = ?")->execute([$fileUploaded, $idAcc]);
            $_SESSION['flash'] = 'Berkas scan Berita Acara bertanda tangan berhasil diunggah.';
        } else {
            $_SESSION['flash_error'] = 'Gagal mengunggah berkas scan Berita Acara.';
        }
        header('Location: ' . module_url('asset_disposal.php'));
        exit;
    }
}

// 3. Ambil Master Data & Filter
$cabangMaster = get_cabang_list();
$filters = [
    'search' => trim((string)($_GET['search'] ?? '')),
    'cabang' => trim((string)($_GET['cabang'] ?? 'all')),
    'tahun' => trim((string)($_GET['tahun'] ?? date('Y'))),
];

$disposalList = get_disposal_list($filters);
$kpi = get_disposal_kpi_summary();

$flashSuccess = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

// Hitung unit dalam masa tenggang 7 hari
$countRestorable = 0;
foreach ($disposalList as $r) {
    $cCheck = is_disposal_restorable($r);
    if (!empty($cCheck['can_restore'])) {
        $countRestorable++;
    }
}

// Susun opsi cabang untuk filter toolbar
$cabangOptionsHtml = '';
foreach ($cabangMaster as $cb) {
    $cName = $cb['nama_cabang'] ?? '';
    if ($cName === '') continue;
    $sel = ($filters['cabang'] === $cName) ? 'selected' : '';
    $cabangOptionsHtml .= '<option value="'.e($cName).'" '.$sel.'>'.e($cName).'</option>';
}

// 5. Render Halaman
$body = '
<style>
#modalDisposal .modal-dialog, #modalCatalogPicker .modal-dialog {
  max-height: calc(100vh - 2rem);
}
#modalDisposal .modal-content, #modalCatalogPicker .modal-content {
  max-height: calc(100vh - 2rem);
  display: flex;
  flex-direction: column;
}
#modalDisposal .modal-body, #modalCatalogPicker .modal-body {
  overflow-y: auto !important;
  max-height: calc(100vh - 180px);
}
</style>

<div class="container-fluid py-3 px-md-3">

  <!-- Flash Messages -->
  '.($flashSuccess ? '
  <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm border-0 mb-3" role="alert">
    <i class="bi bi-check-circle-fill fs-5"></i>
    <div>'.e($flashSuccess).'</div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>' : '').'

  '.($flashError ? '
  <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm border-0 mb-3" role="alert">
    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
    <div>'.e($flashError).'</div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>' : '').'

  <!-- BROWSER WINDOW NEO CONTAINER (SAMA SEPERTI USERS_ADMIN) -->
  <div class="card p-0 shadow-sm border-0 browser-window-card mb-4" style="border-radius: 24px;">
    <!-- Browser Window Topbar with Traffic Lights -->
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2" style="border-top-left-radius: 24px; border-top-right-radius: 24px;">
      <div class="browser-topbar-col-left d-flex align-items-center gap-2">
        <span class="browser-traffic-dot bg-danger"></span>
        <span class="browser-traffic-dot bg-warning"></span>
        <span class="browser-traffic-dot bg-success"></span>
      </div>

      <div class="browser-topbar-col-center">
        <div class="browser-address-pill">
          <i class="bi bi-lock-fill text-danger"></i>
          <span>bankmitra.id/asset_disposal.php</span>
        </div>
      </div>

      <div class="browser-topbar-col-right">
        <div class="floating-toast-pill">
          <div class="floating-toast-icon-amber" style="background: linear-gradient(135deg, #E11D48 0%, #BE123C 100%); box-shadow: 0 4px 10px rgba(225, 29, 72, 0.25);">
            <i class="bi bi-shield-check"></i>
          </div>
          <div class="text-start">
            <div class="fw-bold text-dark" style="font-size: 0.76rem; line-height: 1.2;">Disposal Compliance</div>
            <div class="text-muted" style="font-size: 0.68rem;">SDM &amp; Umum · KPO Bank Mitra</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Inner Canvas with Gradient Hero Banner & Modern Metric Cards -->
    <div class="p-3 p-md-4">
      <!-- Hero Banner Card with Layered Smooth Waves (Rose/Crimson Theme) -->
      <div class="card-hero-neo mb-4" style="background: linear-gradient(135deg, #881337 0%, #991B1B 45%, #E11D48 100%) !important; box-shadow: 0 12px 32px -4px rgba(225, 29, 72, 0.3) !important;">
        <!-- Wave Layer Overlays at Bottom -->
        <svg class="position-absolute bottom-0 start-0 w-100" style="height: 60px; pointer-events: none; opacity: 0.22;" preserveAspectRatio="none" viewBox="0 0 1200 120">
          <path d="M0,0 C150,90 400,100 600,40 C800,-20 1050,80 1200,30 L1200,120 L0,120 Z" fill="#FFFFFF"></path>
        </svg>
        <svg class="position-absolute bottom-0 start-0 w-100" style="height: 40px; pointer-events: none; opacity: 0.32;" preserveAspectRatio="none" viewBox="0 0 1200 120">
          <path d="M0,40 C300,10 600,80 900,30 C1050,10 1150,50 1200,60 L1200,120 L0,120 Z" fill="#FFFFFF"></path>
        </svg>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 position-relative" style="z-index: 2;">
          <div style="max-width: 650px;">
            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
              <span class="badge bg-white text-danger px-2.5 py-1 font-monospace fw-bold shadow-xs" style="font-size: 0.70rem; letter-spacing: 0.05em; border-radius: 6px;">PORTAL SDM &amp; UMUM</span>
              <span class="text-white text-opacity-85 small fw-semibold">Kepatuhan Aset &amp; Akuntansi Bank Mitra</span>
            </div>
            <h2 class="display-6 fw-bold mb-1 text-white" style="letter-spacing: -0.025em;">Penghapusan &amp; Pemusnahan Aset</h2>
            <p class="text-white text-opacity-95 mb-3" style="font-size: 0.95rem; font-weight: 500;">
              Pencatatan resmi Berita Acara pemusnahan fisik (BA-Afkir) inventaris kantor &amp; perangkat IT yang aus atau rusak permanen guna penarikan resmi dari Buku Register Aset Bank.
            </p>
            <div class="d-flex flex-wrap align-items-center gap-3 mt-3 pt-1" style="gap: 12px !important;">
              <button type="button" class="btn btn-light rounded-pill px-4 py-2 fw-bold d-inline-flex align-items-center gap-2 shadow-sm text-decoration-none" style="color: #BE123C !important; font-size: 0.88rem;" data-bs-toggle="modal" data-bs-target="#modalDisposal" onclick="resetDisposalModal()">
                <i class="bi bi-plus-circle-fill" style="color: #BE123C;"></i> <span>Catat Berita Acara Afkir</span>
              </button>
              <a href="'.e(module_url('asset_disposal.php', array_merge($_GET, ['export' => 'excel']))).'" class="btn btn-outline-light rounded-pill px-3.5 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-xs" style="font-size: 0.88rem; background: rgba(255, 255, 255, 0.12); border-color: rgba(255, 255, 255, 0.28);">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i> <span>Export Excel</span>
              </a>
            </div>
          </div>

          <!-- Decorative Right Badges (Themed Shield & Grace Period Clock) -->
          <div class="d-none d-md-flex align-items-center gap-4">
            <!-- Shield Marker -->
            <div class="hero-pin-marker" title="Pemusnahan Terverifikasi Audit">
              <svg width="44" height="52" viewBox="0 0 44 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M22 0L44 8V24C44 38 22 52 22 52C22 52 0 38 0 24V8L22 0Z" fill="#BE123C"/>
                <path d="M14 26L19 31L30 20" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>

            <!-- Glowing Grace Period Badge -->
            <div class="hero-sun-container" title="Masa Tenggang 7 Hari Aktif">
              <svg width="68" height="68" viewBox="0 0 68 68" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="34" cy="34" r="28" fill="#F43F5E" fill-opacity="0.35"/>
                <circle cx="34" cy="34" r="20" fill="#F43F5E"/>
                <path d="M34 22V34L42 38" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round"/>
              </svg>
            </div>
          </div>
        </div>
      </div>

      <!-- 3 Symmetrical Metric Cards (Exact Proportions, Balanced Heights) -->
      <div class="row g-3 mb-2">
        <!-- Card 1: Total Aset Dihapuskan -->
        <div class="col-md-4">
          <div class="card-metric-modern">
            <div>
              <div class="metric-title-muted">Total Aset Diafkirkan</div>
              <div class="metric-number-bold">'.number_format($kpi['total_unit']).' <span class="fs-6 fw-normal text-muted">Unit</span></div>
            </div>
            <div class="small text-muted mt-2 d-flex align-items-center gap-2" style="font-size: 0.78rem;">
              <span class="text-warning fw-bold"><i class="bi bi-credit-card-2-front-fill"></i> '.$kpi['total_sdm'].' SDM &amp; Umum</span>
              <span class="text-secondary">·</span>
              <span class="text-info fw-bold"><i class="bi bi-pc-display"></i> '.$kpi['total_it'].' IT Operations</span>
            </div>
          </div>
        </div>

        <!-- Card 2: Masa Tenggang 7 Hari -->
        <div class="col-md-4">
          <div class="card-metric-modern">
            <div>
              <div class="metric-title-muted">Masa Tenggang 7 Hari</div>
              <div class="metric-number-bold d-flex align-items-center gap-2">
                <span class="text-success">'.$countRestorable.'</span>
                <span class="fs-6 fw-normal text-muted">Unit Bisa Dibatalkan</span>
              </div>
            </div>
            <div class="small text-muted mt-2" style="font-size: 0.78rem;">
              <span class="text-success fw-bold"><i class="bi bi-arrow-counterclockwise"></i> Kembalikan ke Inventaris</span> jika tidak jadi dihapus
            </div>
          </div>
        </div>

        <!-- Card 3: Status Kepatuhan SOP -->
        <div class="col-md-4">
          <div class="card-status-mint">
            <div>
              <div class="metric-title-muted" style="color: #047857 !important;">Kepatuhan SOP Afkir Bank Mitra</div>
              <div class="status-heading mt-1">
                <span class="pulse-dot-green"></span>
                <span>100% Berita Acara Terarsip</span>
              </div>
            </div>
            <div class="status-subtext mt-2">
              <i class="bi bi-check-all me-1"></i> Standar Cetak A4, Approval 3 Pihak &amp; Audit Trail
            </div>
          </div>
        </div>
      </div>

      <!-- Upward Trend Chart with Overlapping Floating Trend Badge -->
      <div class="position-relative mt-2 pt-1 mb-2">
        <div class="w-100 position-relative overflow-hidden rounded-4 trend-chart-box" style="height: 72px; border: 1px solid #E2E8F0;">
          <svg viewBox="0 0 1000 72" preserveAspectRatio="none" style="width: 100%; height: 100%;">
            <defs>
              <linearGradient id="chartGradDisposal" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#E11D48" stop-opacity="0.25"/>
                <stop offset="100%" stop-color="#E11D48" stop-opacity="0.0"/>
              </linearGradient>
            </defs>
            <path d="M0,60 Q250,52 500,38 T1000,12 L1000,72 L0,72 Z" fill="url(#chartGradDisposal)"/>
            <path d="M0,60 Q250,52 500,38 T1000,12" fill="none" stroke="#E11D48" stroke-width="3" stroke-linecap="round"/>
            <circle cx="994" cy="12" r="5" fill="#E11D48" stroke="#FFFFFF" stroke-width="2"/>
          </svg>
          <div class="position-absolute top-0 end-0 p-2 text-muted" style="font-size: 0.72rem; font-weight: 600;">
            Tren Penghapusan Aset · Periode Aktif
          </div>
        </div>

        <!-- Floating Trend Badge Overlapping Left of Chart -->
        <div class="position-absolute" style="left: 16px; bottom: 10px; z-index: 4;">
          <div class="badge-floating-trend shadow">
            <div class="trend-icon-box" style="background: #BE123C; box-shadow: 0 4px 10px rgba(190, 18, 60, 0.3);">
              <i class="bi bi-shield-check"></i>
            </div>
            <div>
              <div class="fw-bold text-dark" style="font-size: 0.88rem;">+'.$kpi['total_bulan_ini'].' aset diafkirkan bulan ini</div>
              <div class="text-muted small" style="font-size: 0.74rem;">Dilengkapi foto fisik &amp; dokumen BA 1 halaman A4</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- DATA TABLE ARSIP BERITA ACARA DENGAN TOOLBAR SYMMETRICAL -->
  <div class="card p-0 overflow-hidden shadow-sm h-100 border-0 mb-4" style="border-radius: 20px;">
    <!-- Table Header & Live Search Bar with Symmetrical Responsive Inputs -->
    <div class="card-header bg-white py-3.5 px-4 border-bottom" style="border-top: 4px solid #E11D48; border-top-left-radius: 20px; border-top-right-radius: 20px;">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <div class="fw-bold text-dark text-uppercase small d-flex align-items-center gap-2" style="letter-spacing: 0.05em;">
          <i class="bi bi-archive-fill text-danger fs-5"></i>
          <span>Daftar Berita Acara Penghapusan Aset (BA-Afkir)</span>
          <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-2.5 py-1 font-monospace">'.count($disposalList).'</span>
        </div>
        <div class="small text-muted">
          Menampilkan <strong class="text-dark">'.count($disposalList).'</strong> dokumen BA-Afkir
        </div>
      </div>

      <!-- Filter & Search Toolbar Controls (All 38px High, Perfectly Symmetrical) -->
      <form method="GET" action="'.e(module_url('asset_disposal.php')).'" class="row g-2 align-items-center m-0">
        <div class="col-lg-6 col-md-12 p-0 pe-md-1">
          <div class="input-group">
            <span class="input-group-text bg-light border-end-0 text-muted" style="border-radius: 9999px 0 0 9999px; height: 38px;"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control bg-light border-start-0 ps-0" style="border-radius: 0 9999px 9999px 0; font-size: 0.84rem; height: 38px;" placeholder="Cari No. BA, Nama Aset, No. Rek, Alasan..." value="'.e($filters['search']).'">
          </div>
        </div>
        <div class="col-lg-4 col-md-8 p-0 px-md-1">
          <select name="cabang" class="form-select form-select-sm" style="border-radius: 9999px; height: 38px; font-size: 0.82rem;" onchange="this.form.submit()">
            <option value="all" '.($filters['cabang']==='all'?'selected':'').'>Semua Kantor Cabang</option>
            '.$cabangOptionsHtml.'
          </select>
        </div>
        <div class="col-lg-2 col-md-4 p-0 ps-md-1 d-flex gap-1.5">
          <button type="submit" class="btn btn-danger btn-sm w-100 fw-bold" style="border-radius: 9999px; height: 38px; font-size: 0.82rem;">
            <i class="bi bi-funnel me-1"></i> Filter
          </button>
          '.(!empty($filters['search']) || $filters['cabang']!=='all' ? '
          <a href="'.e(module_url('asset_disposal.php')).'" class="btn btn-outline-secondary btn-sm" style="border-radius: 9999px; height: 38px; width: 38px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Reset Filter">
            <i class="bi bi-arrow-counterclockwise"></i>
          </a>' : '').'
        </div>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
        <thead class="table-light text-secondary text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">
          <tr>
            <th class="ps-3" style="width: 18%;">No. BA &amp; Tanggal</th>
            <th style="width: 28%;">Identitas Aset</th>
            <th style="width: 16%;">Lokasi / Cabang</th>
            <th style="width: 18%;">Kondisi &amp; Alasan Afkir</th>
            <th style="width: 10%;">Dokumentasi</th>
            <th class="text-end pe-3" style="width: 10%;">Aksi</th>
          </tr>
        </thead>
        <tbody>';

        if (empty($disposalList)) {
            $body .= '
            <tr>
              <td colspan="6" class="text-center py-5 text-muted">
                <i class="bi bi-trash3 fs-1 text-secondary opacity-50 d-block mb-2"></i>
                <div class="fw-semibold">Belum Ada Catatan Berita Acara Penghapusan Aset</div>
                <p class="small text-muted mb-3">Klik tombol "Catat Berita Acara Afkir" di atas untuk memproses penghapusan inventaris rusak.</p>
                <button type="button" class="btn btn-sm btn-danger fw-bold px-3 py-1.5" data-bs-toggle="modal" data-bs-target="#modalDisposal" onclick="resetDisposalModal()">
                  <i class="bi bi-plus-circle me-1"></i> Buat Berita Acara Pertama
                </button>
              </td>
            </tr>';
        } else {
            foreach ($disposalList as $row) {
                $assetBadge = ($row['asset_type'] === 'inventaris') 
                    ? '<span class="badge bg-warning text-dark border border-warning" style="font-size: 0.65rem;"><i class="bi bi-credit-card-2-front"></i> Kartu SDM</span>' 
                    : '<span class="badge bg-info text-dark border border-info" style="font-size: 0.65rem;"><i class="bi bi-pc-display"></i> Perangkat IT</span>';

                $restoreInfo = is_disposal_restorable($row);
                $canRestore = $restoreInfo['can_restore'];
                $daysRemaining = $restoreInfo['days_remaining'];

                $body .= '
                <tr>
                  <td class="ps-3">
                    <div class="font-monospace fw-bold text-dark text-break" style="font-size: 0.82rem;">'.e($row['nomor_ba']).'</div>
                    <div class="text-muted small mt-0.5"><i class="bi bi-calendar3 me-1"></i>'.date('d/m/Y', strtotime($row['tanggal_disposal'])).'</div>
                    <div class="text-secondary small mt-0.5" style="font-size: 0.72rem;">Petugas: '.e($row['petugas_nama']).'</div>
                    '.($canRestore ? '
                    <div class="mt-1">
                      <span class="badge bg-success-subtle text-success border border-success-subtle py-0.5 px-2 fw-semibold" style="font-size: 0.68rem;" title="Aset masih dapat dikembalikan ke inventaris dalam 7 hari">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Bisa Batal (Sisa '.$daysRemaining.' hari)
                      </span>
                    </div>' : '
                    <div class="mt-1">
                      <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle py-0.5 px-2" style="font-size: 0.68rem;" title="Masa tenggang 7 hari telah lewat, status permanen">
                        <i class="bi bi-lock-fill me-1"></i>Permanen (&gt;7 hari)
                      </span>
                    </div>').'
                  </td>
                  <td>
                    <div class="d-flex align-items-center gap-1.5 mb-1">
                      '.$assetBadge.'
                      <span class="font-monospace text-muted small">['.e($row['kode_aset']).']</span>
                    </div>
                    <div class="fw-bold text-dark">'.e($row['nama_aset']).'</div>
                    '.(!empty($row['merk_model']) && $row['merk_model'] !== '-' ? '<div class="text-muted small">'.e($row['merk_model']).'</div>' : '').'
                  </td>
                  <td>
                    <div class="fw-semibold text-dark">'.e($row['cabang']).'</div>
                    <div class="text-muted small">Ruang: '.e($row['lokasi_ruangan'] ?: '-').'</div>
                    <div class="text-secondary small" style="font-size: 0.72rem;">PJ: '.e($row['pengguna_terakhir'] ?: '-').'</div>
                  </td>
                  <td>
                    <div class="text-danger fw-bold small mb-1">
                      <i class="bi bi-exclamation-triangle-fill me-1"></i>'.e($row['kondisi_terakhir']).'
                    </div>
                    <div class="text-secondary small text-truncate" style="max-width: 240px;" title="'.e($row['alasan_penghapusan']).'">
                      '.e($row['alasan_penghapusan']).'
                    </div>
                    <div class="badge bg-light text-secondary border mt-1" style="font-size: 0.68rem;">
                      '.e($row['metode_pemusnahan']).'
                    </div>
                  </td>
                  <td>
                    <div class="d-flex flex-column gap-1">';
                      if (!empty($row['foto_barang'])) {
                          $body .= '<a href="'.e(disposal_file_url($row['foto_barang'])).'" target="_blank" class="btn btn-xs btn-outline-secondary text-start py-0.5 px-2" style="font-size: 0.72rem;">
                            <i class="bi bi-camera me-1 text-primary"></i> Foto Fisik
                          </a>';
                      }
                      if (!empty($row['dokumen_ba_disetujui'])) {
                          $body .= '<a href="'.e(disposal_file_url($row['dokumen_ba_disetujui'])).'" target="_blank" class="btn btn-xs btn-outline-info text-dark fw-bold text-start py-0.5 px-2" style="font-size: 0.72rem;">
                            <i class="bi bi-file-earmark-check me-1 text-info"></i> Scan BA Acc ✓
                          </a>';
                      } else {
                          $body .= '<button type="button" class="btn btn-xs btn-light border text-muted text-start py-0.5 px-2" style="font-size: 0.70rem;" onclick="openUploadAccModal('.$row['id'].', \''.e(addslashes($row['nomor_ba'])).'\')">
                            <i class="bi bi-cloud-arrow-up me-1"></i> Upload Scan BA
                          </button>';
                      }
                      $body .= '
                    </div>
                  </td>
                  <td class="text-end pe-3 text-nowrap">
                    <div class="btn-group btn-group-sm shadow-xs">
                      <a href="'.e(module_url('print_bast_disposal.php', ['id' => $row['id']])).'" target="_blank" class="btn btn-primary fw-semibold" title="Cetak Berita Acara">
                        <i class="bi bi-printer-fill me-1"></i> Cetak
                      </a>
                      '.($canRestore ? '
                      <button type="button" class="btn btn-outline-success fw-semibold" onclick="openRestoreModal('.$row['id'].', \''.e(addslashes($row['nama_aset'])).'\', '.$daysRemaining.')" title="Batalkan penghapusan &amp; kembalikan ke inventaris aktif (Masa tenggang sisa '.$daysRemaining.' hari)">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Batal Hapus
                      </button>' : '').'
                      <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="visually-hidden">Toggle Dropdown</span>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.85rem;">
                        <li>
                          <a class="dropdown-item" href="'.e(module_url('print_bast_disposal.php', ['id' => $row['id']])).'" target="_blank">
                            <i class="bi bi-file-earmark-pdf me-2 text-primary"></i> Cetak Dokumen BA-Afkir
                          </a>
                        </li>
                        <li>
                          <button class="dropdown-item" type="button" onclick="openUploadAccModal('.$row['id'].', \''.e(addslashes($row['nomor_ba'])).'\')">
                            <i class="bi bi-cloud-arrow-up me-2 text-info"></i> Upload Scan BA Disetujui
                          </button>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                          '.($canRestore ? '
                          <button type="button" class="dropdown-item text-success fw-semibold" onclick="openRestoreModal('.$row['id'].', \''.e(addslashes($row['nama_aset'])).'\', '.$daysRemaining.')">
                            <i class="bi bi-arrow-counterclockwise me-2 text-success"></i> Kembalikan ke Inventaris (Sisa '.$daysRemaining.' Hari)
                          </button>' : '
                          <span class="dropdown-item text-muted disabled" style="cursor: not-allowed;" title="Masa tenggang 7 hari telah berakhir">
                            <i class="bi bi-lock-fill me-2 text-secondary"></i> Permanen (Lewat 7 Hari)
                          </span>').'
                        </li>
                      </ul>
                    </div>
                  </td>
                </tr>';
            }
        }

$body .= '
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- ==================================================== -->
<!-- MODAL CATAT BERITA ACARA PENGHAPUSAN ASET (BA-AFKIR) -->
<!-- ==================================================== -->
<div class="modal fade" id="modalDisposal" tabindex="-1" aria-labelledby="modalDisposalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <form method="POST" action="'.e(module_url('asset_disposal.php')).'" enctype="multipart/form-data" id="formDisposal" class="modal-content border-0 shadow-lg">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="dispId" value="0">
        
        <div class="modal-header bg-dark text-white border-0 py-3">
          <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="modalDisposalLabel">
            <i class="bi bi-trash3-fill text-danger"></i> Catat Berita Acara Penghapusan Aset (BA-Afkir)
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">
          <!-- 1. Pilihan Kategori Sumber Aset -->
          <div class="mb-3">
            <label class="form-label fw-bold text-dark small">PILIH SUMBER BUKU REGISTER ASET <span class="text-danger">*</span></label>
            <div class="d-flex gap-3">
              <div class="form-check border rounded-3 p-2.5 flex-fill bg-light">
                <input class="form-check-input ms-0 me-2" type="radio" name="asset_type" id="typeInventaris" value="inventaris" checked onchange="switchAssetType(\'inventaris\')">
                <label class="form-check-label fw-semibold text-dark" for="typeInventaris" style="cursor: pointer;">
                  <i class="bi bi-credit-card-2-front text-warning me-1"></i> Kartu Inventaris Fisik (SDM &amp; Umum)
                  <div class="text-muted fw-normal small" style="font-size: 0.74rem;">Perabot, Meja, Kursi, AC, Brankas, Kipas, Dispenser, Kendaraan</div>
                </label>
              </div>
              <div class="form-check border rounded-3 p-2.5 flex-fill bg-light">
                <input class="form-check-input ms-0 me-2" type="radio" name="asset_type" id="typeKomputer" value="komputer" onchange="switchAssetType(\'komputer\')">
                <label class="form-check-label fw-semibold text-dark" for="typeKomputer" style="cursor: pointer;">
                  <i class="bi bi-pc-display text-info me-1"></i> Perangkat IT / Komputer (IT Ops)
                  <div class="text-muted fw-normal small" style="font-size: 0.74rem;">PC Dekstop, Laptop, Monitor, Printer, Scanner, Server</div>
                </label>
              </div>
            </div>
          </div>

          <!-- 2. Pilih Barang Aset Aktif -->
          <div class="mb-3">
            <div class="d-flex align-items-center justify-content-between mb-1.5">
              <label class="form-label fw-bold text-dark small mb-0" for="selectAssetItem">
                PILIH UNIT BARANG INVENTARIS YANG DIAFKIRKAN <span class="text-danger">*</span>
              </label>
              <button type="button" class="btn btn-xs btn-outline-danger fw-bold px-2 py-0.5" onclick="openCatalogModal()">
                <i class="bi bi-grid-3x3-gap-fill me-1"></i> Buka Katalog Tabel
              </button>
            </div>

            <!-- Tombol Utama: Buka Katalog Modal Pencarian -->
            <button type="button" class="btn btn-danger w-100 mb-2.5 d-flex align-items-center justify-content-center gap-2 py-2 shadow-xs" onclick="openCatalogModal()">
              <i class="bi bi-grid-3x3-gap-fill fs-5"></i>
              <span class="fw-bold">Buka Katalog &amp; Cari Barang di Tabel</span>
              <span class="badge bg-white text-danger fw-bold ms-1" id="badgeTotalItems">0 Barang</span>
            </button>

            <!-- Box Pencarian Cepat & Filter Cabang -->
            <div class="p-2.5 rounded-3 bg-light border mb-2">
              <div class="row g-2">
                <div class="col-sm-5 col-12">
                  <label class="form-label text-uppercase fw-bold text-muted small mb-1" style="font-size: 0.68rem;">Filter Cabang</label>
                  <select id="filterCabangAset" class="form-select form-select-sm" onchange="filterAssetDropdown()">
                    <option value="">-- Semua Cabang --</option>';
                    foreach ($cabangMaster as $cb) {
                        $cName = $cb['nama_cabang'] ?? '';
                        if ($cName === '') continue;
                        $body .= '<option value="'.e($cName).'">'.e($cName).'</option>';
                    }
$body .= '
                  </select>
                </div>
                <div class="col-sm-7 col-12">
                  <label class="form-label text-uppercase fw-bold text-muted small mb-1" style="font-size: 0.68rem;">Cari Nama / No. Rekening / Ruangan</label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="quickSearchAsset" class="form-control border-start-0" placeholder="Ketik kata kunci untuk mencari..." oninput="filterAssetDropdown()" autocomplete="off">
                    <button class="btn btn-outline-secondary" type="button" onclick="clearQuickSearch()" title="Reset pencarian"><i class="bi bi-x"></i></button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Dropdown Hasil Pencarian -->
            <select class="form-select" id="selectAssetItem" required onchange="onAssetSelected(this)">
              <option value="">-- Pilih Barang dari Hasil Pencarian --</option>
            </select>
            <div class="d-flex justify-content-between align-items-center mt-1">
              <div class="form-text small" id="lblMatchCount">Memuat daftar barang...</div>
              <button type="button" class="btn btn-link btn-xs text-danger text-decoration-none p-0 fw-semibold" onclick="clearQuickSearch()" style="font-size: 0.74rem;">Reset Pencarian</button>
            </div>
          </div>

          <!-- Hidden Input Detail Aset Otomatis -->
          <input type="hidden" name="asset_id" id="dispAssetId" value="0">
          <input type="hidden" name="kode_aset" id="dispKodeAset" value="">
          <input type="hidden" name="nama_aset" id="dispNamaAset" value="">
          <input type="hidden" name="merk_model" id="dispMerkModel" value="">
          <input type="hidden" name="serial_number" id="dispSerialNumber" value="">
          <input type="hidden" name="cabang" id="dispCabang" value="Kantor Pusat (KPO)">
          <input type="hidden" name="lokasi_ruangan" id="dispRuangan" value="">
          <input type="hidden" name="pengguna_terakhir" id="dispPengguna" value="">

          <!-- Card Informasi Aset Terpilih -->
          <div class="card bg-light border-0 rounded-3 p-3 mb-3 d-none" id="cardAsetDetail">
            <div class="row g-2 small">
              <div class="col-sm-6">
                <div class="text-muted" style="font-size: 0.74rem;">KODE / NO. REKENING:</div>
                <div class="fw-bold font-monospace" id="lblKodeAset">-</div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted" style="font-size: 0.74rem;">KANTOR CABANG &amp; RUANGAN:</div>
                <div class="fw-bold text-dark" id="lblLokasiAset">-</div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted" style="font-size: 0.74rem;">PENANGGUNG JAWAB:</div>
                <div class="fw-semibold text-dark" id="lblPenggunaAset">-</div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted" style="font-size: 0.74rem;">SERIAL NUMBER / MERK:</div>
                <div class="text-dark" id="lblSnAset">-</div>
              </div>
            </div>
          </div>

          <hr class="my-3 text-muted">

          <!-- 3. Nomor Dokumen & Tanggal Pelaksanaan -->
          <div class="row g-3 mb-3">
            <div class="col-md-7 col-12">
              <label class="form-label fw-bold text-dark small" for="dispNomorBa">NOMOR BERITA ACARA (AUTO)</label>
              <input type="text" name="nomor_ba" id="dispNomorBa" class="form-control font-monospace bg-light fw-bold" placeholder="Dibuat otomatis oleh sistem...">
              <div class="form-text small" style="font-size: 0.72rem;">Format resmi: 001/BA-AFKIR/SDM-UMUM/BANK-MITRA.KPO/X/2026.</div>
            </div>
            <div class="col-md-5 col-12">
              <label class="form-label fw-bold text-dark small" for="dispTanggal">TANGGAL PEMERIKSAAN / AFKIR <span class="text-danger">*</span></label>
              <input type="date" name="tanggal_disposal" id="dispTanggal" class="form-control" value="'.date('Y-m-d').'" required>
            </div>
          </div>

          <!-- 4. Kondisi Fisik & Metode Pemusnahan -->
          <div class="row g-3 mb-3">
            <div class="col-md-6 col-12">
              <label class="form-label fw-bold text-dark small" for="dispKondisi">KONDISI FISIK TERAKHIR <span class="text-danger">*</span></label>
              <select name="kondisi_terakhir" id="dispKondisi" class="form-select form-select-sm" required>
                <option value="Rusak Berat / Tidak Ekonomis Diperbaiki">Rusak Berat / Tidak Ekonomis Diperbaiki</option>
                <option value="Mati Total / Tidak Dapat Menyala">Mati Total / Tidak Dapat Menyala</option>
                <option value="Rangka Fisik Patah / Lapuk / Keropos">Rangka Fisik Patah / Lapuk / Keropos</option>
                <option value="Usang / Masa Manfaat Habis">Usang / Masa Manfaat Habis</option>
              </select>
            </div>
            <div class="col-md-6 col-12">
              <label class="form-label fw-bold text-dark small" for="dispMetode">METODE PEMUSNAHAN / PENYINGKIRAN <span class="text-danger">*</span></label>
              <select name="metode_pemusnahan" id="dispMetode" class="form-select form-select-sm" required>
                <option value="Dihancurkan / Dibuang Fisik">Dihancurkan / Dibuang Fisik</option>
                <option value="Dikeluarkan dari Area Lingkungan Bank">Dikeluarkan dari Area Lingkungan Bank</option>
                <option value="Kanibalisasi Suku Cadang (Untuk Unit Lain)">Kanibalisasi Suku Cadang (Untuk Unit Lain)</option>
                <option value="Penghapusan Buku Administratif (Write-Off)">Penghapusan Buku Administratif (Write-Off)</option>
              </select>
            </div>
          </div>

          <!-- 5. Alasan & Pertimbangan Teknis -->
          <div class="mb-3">
            <label class="form-label fw-bold text-dark small" for="dispAlasan">ALASAN &amp; PERTIMBANGAN TEKNIS PENGHAPUSAN <span class="text-danger">*</span></label>
            <div class="d-flex flex-wrap gap-1 mb-2">
              <button type="button" class="btn btn-xs btn-outline-secondary" onclick="appendAlasan(\'Biaya perbaikan melebihi nilai beli unit baru.\')">+ Biaya Servis Mahal</button>
              <button type="button" class="btn btn-xs btn-outline-secondary" onclick="appendAlasan(\'Rangka fisik patah dan lapuk sehingga tidak aman dipakai.\')">+ Rangka Patah</button>
              <button type="button" class="btn btn-xs btn-outline-secondary" onclick="appendAlasan(\'Komponen elektronik vital rusak dan sparepart tidak lagi tersedia.\')">+ Sparepart Tidak Ada</button>
              <button type="button" class="btn btn-xs btn-outline-secondary" onclick="appendAlasan(\'Masa manfaat ekonomis telah habis dan tidak layak operasi.\')">+ Masa Manfaat Habis</button>
            </div>
            <textarea name="alasan_penghapusan" id="dispAlasan" class="form-control form-control-sm" rows="3" required placeholder="Jelaskan pertimbangan teknis mengapa barang ini dihapus / diafkirkan..."></textarea>
          </div>

          <!-- 6. Upload Foto Fisik Barang Terakhir -->
          <div class="mb-3">
            <label class="form-label fw-bold text-dark small" for="dispFoto">UPLOAD FOTO KONDISI FISIK BARANG RUSAK</label>
            <input type="file" name="foto_barang" id="dispFoto" class="form-control form-control-sm" accept="image/*">
            <div class="form-text small" style="font-size: 0.72rem;">Lampirkan bukti foto fisik kerusakan barang sebagai kelengkapan audit.</div>
          </div>

          <!-- 7. Penandatangan Berita Acara -->
          <div class="card bg-light border-0 rounded-3 p-3 mb-3">
            <div class="fw-bold text-dark small mb-2"><i class="bi bi-pen"></i> PEJABAT PENANDATANGAN BERITA ACARA</div>
            <div class="row g-2">
              <div class="col-md-4 col-12">
                <label class="form-label small text-muted" style="font-size: 0.74rem;">YANG MEMERIKSA (PETUGAS)</label>
                <input type="text" name="petugas_nama" id="dispPetugasNama" class="form-control form-control-sm" value="'.e($_SESSION['nama'] ?? 'Staf Bagian Umum').'" required>
                <input type="text" name="petugas_jabatan" id="dispPetugasJabatan" class="form-control form-control-sm mt-1" value="'.(is_hrd() ? 'Staf Bagian Umum' : 'Staf IT Operations').'">
              </div>
              <div class="col-md-4 col-12">
                <label class="form-label small text-muted" style="font-size: 0.74rem;">MENGETAHUI</label>
                <input type="text" name="nama_mengetahui" id="dispNamaMengetahui" class="form-control form-control-sm" value="Defie Damayanti" required>
                <input type="text" name="jabatan_mengetahui" id="dispJabatanMengetahui" class="form-control form-control-sm mt-1" value="Kabag. Umum &amp; SDM">
              </div>
              <div class="col-md-4 col-12">
                <label class="form-label small text-muted" style="font-size: 0.74rem;">MENYETUJUI</label>
                <input type="text" name="nama_menyetujui" id="dispNamaMenyetujui" class="form-control form-control-sm" value="Kahar Muzaqir, SH" required>
                <input type="text" name="jabatan_menyetujui" id="dispJabatanMenyetujui" class="form-control form-control-sm mt-1" value="Direktur Operasional">
              </div>
            </div>
          </div>

          <!-- Opsi Cetak Langsung -->
          <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" name="print_after" value="1" id="chkPrintAfter" checked>
            <label class="form-check-label small fw-semibold text-dark" for="chkPrintAfter">
              Langsung buka dokumen Berita Acara siap cetak setelah data disimpan
            </label>
          </div>

        </div>

        <div class="modal-footer bg-light py-2 px-3 border-0">
          <button type="button" class="btn btn-secondary fw-semibold btn-sm px-3" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger fw-bold btn-sm px-4">
            <i class="bi bi-check-circle-fill me-1"></i> Simpan &amp; Proses BA-Afkir
          </button>
        </div>
    </form>
  </div>
</div>

<!-- ========================================== -->
<!-- MODAL UPLOAD SCAN BERITA ACARA DISETUJUI -->
<!-- ========================================== -->

<!-- Modal Konfirmasi Pengembalian Aset (Restore Sebelum 7 Hari) -->
<div class="modal fade" id="modalRestoreDisposal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="'.e(module_url('asset_disposal.php')).'" class="modal-content border-0 shadow-lg rounded-4">
      <input type="hidden" name="action" value="restore">
      <input type="hidden" name="id" id="restoreDisposalId" value="0">
      <div class="modal-header bg-success text-white border-0 py-3">
        <h6 class="modal-title fw-bold d-flex align-items-center gap-2">
          <i class="bi bi-arrow-counterclockwise fs-5"></i> Batalkan Penghapusan &amp; Kembalikan Aset
        </h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <div class="mb-3">
          <div class="d-inline-flex p-3 rounded-circle bg-success bg-opacity-10 text-success">
            <i class="bi bi-arrow-counterclockwise fs-1"></i>
          </div>
        </div>
        <h5 class="fw-bold text-dark mb-2">Kembalikan ke Inventaris Aktif?</h5>
        <p class="text-secondary small mb-3">
          Aset <strong id="restoreDisposalNama" class="text-dark">-</strong> akan dipulihkan status fisiknya menjadi <strong>Aktif</strong> di buku register inventaris Bank Mitra, dan Berita Acara afkir ini akan dibatalkan.
        </p>
        <div class="alert alert-success border-0 py-2 px-3 small d-inline-flex align-items-center gap-2 mb-0">
          <i class="bi bi-clock-history text-success"></i>
          <span>Masa tenggang tersisa: <strong id="restoreDisposalSisaHari">7</strong> hari</span>
        </div>
      </div>
      <div class="modal-footer bg-light border-0 py-2.5 px-4 justify-content-end gap-2">
        <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">Tutup</button>
        <button type="submit" class="btn btn-sm btn-success fw-bold px-3">
          <i class="bi bi-check-circle me-1"></i> Ya, Kembalikan ke Inventaris
        </button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalUploadAcc" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="'.e(module_url('asset_disposal.php')).'" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload_acc">
        <input type="hidden" name="id" id="uploadAccId" value="0">
        <div class="modal-header bg-dark text-white border-0 py-2.5">
          <h6 class="modal-title fw-bold d-flex align-items-center gap-1.5">
            <i class="bi bi-cloud-arrow-up text-info"></i> Upload Scan Berita Acara Disetujui
          </h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="small text-muted mb-2">
            Unggah dokumen scan Berita Acara fisik yang telah ditandatangani dan distempel basah untuk nomor:
            <div class="fw-bold font-monospace text-dark" id="lblAccNomorBa">-</div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">PILIH FILE BERKAS (PDF / GAMBAR)</label>
            <input type="file" name="dokumen_ba_disetujui" class="form-control form-control-sm" accept=".pdf,image/*" required>
          </div>
        </div>
        <div class="modal-footer bg-light py-2 px-3 border-0">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
          <button type="submit" class="btn btn-primary btn-sm fw-bold">Unggah Dokumen</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =================================================== -->
<!-- MODAL KATALOG PENCARIAN BARANG INVENTARIS LENGKAP -->
<!-- =================================================== -->
<div class="modal fade" id="modalCatalogPicker" tabindex="-1" aria-labelledby="modalCatalogPickerLabel" aria-hidden="true" style="z-index: 1070;">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-2xl">
      <div class="modal-header bg-dark text-white border-0 py-3">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="modalCatalogPickerLabel">
          <i class="bi bi-grid-3x3-gap-fill text-danger"></i> Katalog &amp; Pencarian Barang Inventaris Aktif
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-3 p-md-4">
        <!-- Pencarian & Filter Cabang di Modal -->
        <div class="row g-2 mb-3">
          <div class="col-md-7 col-12">
            <div class="input-group">
              <span class="input-group-text bg-white border-end-0 text-danger"><i class="bi bi-search"></i></span>
              <input type="text" id="modalCatalogSearchInput" class="form-control border-start-0" placeholder="Ketik nama barang, nomor rekening, ruangan, penanggung jawab..." oninput="renderCatalogRows()" autocomplete="off">
              <button class="btn btn-outline-secondary" type="button" onclick="clearCatalogSearch()"><i class="bi bi-x-circle me-1"></i>Reset</button>
            </div>
          </div>
          <div class="col-md-5 col-12">
            <select id="modalCatalogBranchFilter" class="form-select" onchange="renderCatalogRows()">
              <option value="">-- Seluruh Kantor Cabang --</option>';
              foreach ($cabangMaster as $cb) {
                  $cName = $cb['nama_cabang'] ?? '';
                  if ($cName === '') continue;
                  $body .= '<option value="'.e($cName).'">'.e($cName).'</option>';
              }
$body .= '
            </select>
          </div>
        </div>

        <!-- Tabel Katalog Barang -->
        <div class="table-responsive border rounded-3" style="max-height: 460px;">
          <table class="table table-hover align-middle mb-0" id="tableCatalogAsset" style="font-size: 0.88rem;">
            <thead class="table-light sticky-top" style="z-index: 2;">
              <tr>
                <th style="width: 45px;" class="text-center small font-monospace">NO</th>
                <th style="width: 170px;">NOMOR REKENING</th>
                <th>NAMA BARANG &amp; GOLONGAN</th>
                <th style="width: 180px;">CABANG &amp; RUANGAN</th>
                <th style="width: 160px;">PENANGGUNG JAWAB</th>
                <th style="width: 120px;" class="text-center">AKSI</th>
              </tr>
            </thead>
            <tbody id="catalogTableBody">
              <!-- Render via JavaScript -->
            </tbody>
          </table>
        </div>
      </div>

      <div class="modal-footer bg-light d-flex justify-content-between py-2.5 px-3">
        <span class="text-muted small" id="modalCatalogCount">Memuat data barang...</span>
        <button type="button" class="btn btn-secondary px-4 fw-semibold btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- DATA ASSETS FOR JAVASCRIPT -->
<script>
var gInventaris = [];
var gKomputer = [];
var gCurrentList = [];
var gAssetsLoaded = false;
var gIsLoadingAssets = false;
var gLoadCallbacks = [];

function loadActiveAssets(callback) {
  if (gAssetsLoaded) {
    if (callback) callback();
    return;
  }
  if (callback) gLoadCallbacks.push(callback);
  if (gIsLoadingAssets) return;
  gIsLoadingAssets = true;

  var lblCount = document.getElementById("lblMatchCount");
  if (lblCount) lblCount.innerHTML = "<span class=\"spinner-border spinner-border-sm text-danger me-1\"></span>Memuat daftar barang aktif...";

  fetch("asset_disposal.php?action=get_active_assets")
    .then(function(res) {
      if (!res.ok) throw new Error("HTTP " + res.status);
      return res.json();
    })
    .then(function(data) {
      gInventaris = data.inventaris || [];
      gKomputer = data.komputer || [];
      var curType = document.querySelector("input[name=\"asset_type\"]:checked");
      var t = curType ? curType.value : "inventaris";
      gCurrentList = (t === "komputer") ? gKomputer : gInventaris;
      gAssetsLoaded = true;
      gIsLoadingAssets = false;

      var badge = document.getElementById("badgeTotalItems");
      if (badge) badge.textContent = gCurrentList.length + " Barang";

      filterAssetDropdown();

      while (gLoadCallbacks.length > 0) {
        var cb = gLoadCallbacks.shift();
        try { cb(); } catch(e) { console.error(e); }
      }
    })
    .catch(function(err) {
      gIsLoadingAssets = false;
      console.error("Gagal memuat katalog aset:", err);
      if (lblCount) lblCount.innerHTML = "<span class=\"text-danger\"><i class=\"bi bi-exclamation-triangle me-1\"></i>Gagal memuat data. Coba lagi.</span>";
    });
}

function switchAssetType(type) {
  if (gAssetsLoaded) {
    gCurrentList = (type === "komputer") ? gKomputer : gInventaris;
  } else {
    gCurrentList = [];
    loadActiveAssets(function() {
      switchAssetType(type);
    });
    return;
  }

  // Reset pencarian & filter cabang
  var searchInput = document.getElementById("quickSearchAsset");
  if (searchInput) searchInput.value = "";
  var branchSelect = document.getElementById("filterCabangAset");
  if (branchSelect) branchSelect.value = "";

  // Update badge total
  var badge = document.getElementById("badgeTotalItems");
  if (badge) badge.textContent = gCurrentList.length + " Barang";

  filterAssetDropdown();
  document.getElementById("cardAsetDetail").classList.add("d-none");
  resetAssetHiddenFields();
}

function filterAssetDropdown() {
  var select = document.getElementById("selectAssetItem");
  if (!select) return;

  if (!gAssetsLoaded) {
    select.innerHTML = "<option value=\"\">-- Sedang memuat daftar barang... --</option>";
    return;
  }

  var branch = (document.getElementById("filterCabangAset") ? document.getElementById("filterCabangAset").value : "").toLowerCase().trim();
  var query = (document.getElementById("quickSearchAsset") ? document.getElementById("quickSearchAsset").value : "").toLowerCase().trim();

  select.innerHTML = "<option value=\"\">-- Pilih Barang dari Hasil Pencarian --</option>";

  var matched = 0;
  var MAX_OPTIONS = 150;
  for (var i = 0; i < gCurrentList.length; i++) {
    var item = gCurrentList[i];

    if (branch !== "" && item.cabang.toLowerCase().trim() !== branch) {
      continue;
    }
    if (query !== "") {
      var sText = (item.kode + " " + item.nama + " " + item.cabang + " " + (item.ruangan || "") + " " + (item.pengguna || "") + " " + (item.sn || "") + " " + (item.merk || "")).toLowerCase();
      if (sText.indexOf(query) === -1) {
        continue;
      }
    }

    matched++;
    if (matched <= MAX_OPTIONS) {
      var opt = document.createElement("option");
      opt.value = item.id;
      opt.text = "[" + item.kode + "] " + item.nama + " (" + item.cabang + (item.ruangan ? " - " + item.ruangan : "") + ")";
      opt.dataset.json = JSON.stringify(item);
      select.appendChild(opt);
    }
  }

  var lblCount = document.getElementById("lblMatchCount");
  if (lblCount) {
    if (query !== "" || branch !== "") {
      lblCount.innerHTML = "<strong>" + matched + "</strong> dari " + gCurrentList.length + " barang cocok" + (matched > MAX_OPTIONS ? " (ditampilkan " + MAX_OPTIONS + " teratas)" : "") + ".";
    } else {
      lblCount.textContent = "Menampilkan seluruh " + Math.min(matched, MAX_OPTIONS) + " barang aktif" + (matched > MAX_OPTIONS ? " (terbatas " + MAX_OPTIONS + ")" : "") + ".";
    }
  }
}

function clearQuickSearch() {
  var s = document.getElementById("quickSearchAsset");
  if (s) s.value = "";
  var b = document.getElementById("filterCabangAset");
  if (b) b.value = "";
  filterAssetDropdown();
}

function openCatalogModal() {
  var modalEl = document.getElementById("modalCatalogPicker");
  if (!modalEl) return;

  var myModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
  var tbody = document.getElementById("catalogTableBody");
  if (tbody && !gAssetsLoaded) {
    tbody.innerHTML = "<tr><td colspan=\"6\" class=\"text-center py-5 text-muted\"><div class=\"spinner-border spinner-border-sm text-danger me-2\"></div>Memuat katalog barang aktif...</td></tr>";
  }
  myModal.show();

  loadActiveAssets(function() {
    var q = document.getElementById("quickSearchAsset") ? document.getElementById("quickSearchAsset").value : "";
    var br = document.getElementById("filterCabangAset") ? document.getElementById("filterCabangAset").value : "";

    var modalQ = document.getElementById("modalCatalogSearchInput");
    if (modalQ) modalQ.value = q;
    var modalBr = document.getElementById("modalCatalogBranchFilter");
    if (modalBr) modalBr.value = br;

    renderCatalogRows();
  });
}

function clearCatalogSearch() {
  var q = document.getElementById("modalCatalogSearchInput");
  if (q) q.value = "";
  var b = document.getElementById("modalCatalogBranchFilter");
  if (b) b.value = "";
  renderCatalogRows();
}

function renderCatalogRows() {
  var tbody = document.getElementById("catalogTableBody");
  if (!tbody) return;

  if (!gAssetsLoaded) {
    tbody.innerHTML = "<tr><td colspan=\"6\" class=\"text-center py-5 text-muted\"><div class=\"spinner-border spinner-border-sm text-danger me-2\"></div>Memuat katalog barang aktif...</td></tr>";
    return;
  }

  var q = (document.getElementById("modalCatalogSearchInput") ? document.getElementById("modalCatalogSearchInput").value : "").toLowerCase().trim();
  var branch = (document.getElementById("modalCatalogBranchFilter") ? document.getElementById("modalCatalogBranchFilter").value : "").toLowerCase().trim();

  var currentSelectedId = parseInt(document.getElementById("dispAssetId").value) || 0;

  var matched = 0;
  var MAX_DISPLAY = 60;
  var html = "";

  for (var i = 0; i < gCurrentList.length; i++) {
    var item = gCurrentList[i];

    if (branch !== "" && item.cabang.toLowerCase().trim() !== branch) {
      continue;
    }
    if (q !== "") {
      var sText = (item.kode + " " + item.nama + " " + item.cabang + " " + (item.ruangan || "") + " " + (item.pengguna || "") + " " + (item.sn || "") + " " + (item.merk || "")).toLowerCase();
      if (sText.indexOf(q) === -1) {
        continue;
      }
    }

    matched++;
    if (matched > MAX_DISPLAY) {
      continue;
    }

    var isSelected = (item.id === currentSelectedId);
    var rowClass = isSelected ? "table-danger fw-semibold" : "";

    html += "<tr class=\"" + rowClass + "\" style=\"cursor: pointer;\" onclick=\"selectAssetAndCloseCatalog(" + item.id + ")\">";
    html += "<td class=\"text-center font-monospace text-muted small\">" + matched + "</td>";
    html += "<td>";
    html += "<div class=\"fw-bold font-monospace text-danger\">" + escapeHtml(item.kode) + "</div>";
    if (item.sn) {
      html += "<div class=\"small text-muted font-monospace\" style=\"font-size: 0.72rem;\">SN: " + escapeHtml(item.sn) + "</div>";
    }
    html += "</td>";
    html += "<td>";
    html += "<div class=\"fw-bold text-dark\">" + escapeHtml(item.nama) + "</div>";
    html += "<span class=\"badge bg-light text-secondary border small mt-0.5\">" + escapeHtml(item.merk || "Fasilitas") + "</span>";
    html += "</td>";
    html += "<td>";
    html += "<div class=\"fw-semibold text-dark\">" + escapeHtml(item.cabang) + "</div>";
    html += "<div class=\"small text-muted\"><i class=\"bi bi-door-open me-1\"></i>" + escapeHtml(item.ruangan || "-") + "</div>";
    html += "</td>";
    html += "<td><div class=\"text-dark small\">" + escapeHtml(item.pengguna || "-") + "</div></td>";
    html += "<td class=\"text-center\" onclick=\"event.stopPropagation()\">";
    html += "<button type=\"button\" class=\"btn btn-sm " + (isSelected ? "btn-danger" : "btn-outline-danger") + " fw-bold px-3 py-1 shadow-sm\" onclick=\"selectAssetAndCloseCatalog(" + item.id + ")\">";
    html += "<i class=\"bi " + (isSelected ? "bi-check-circle-fill" : "bi-check-lg") + " me-1\"></i> " + (isSelected ? "Terpilih" : "Pilih");
    html += "</button>";
    html += "</td>";
    html += "</tr>";
  }

  if (matched === 0) {
    html = "<tr><td colspan=\"6\" class=\"text-center py-5 text-muted\">" +
           "<i class=\"bi bi-search fs-2 d-block mb-2 opacity-50\"></i>" +
           "Tidak ada barang inventaris yang sesuai dengan filter pencarian.<br>" +
           "<button type=\"button\" class=\"btn btn-link btn-sm mt-2 text-decoration-none\" onclick=\"clearCatalogSearch()\">Reset Pencarian</button>" +
           "</td></tr>";
  } else if (matched > MAX_DISPLAY) {
    html += "<tr><td colspan=\"6\" class=\"text-center py-2.5 bg-light text-muted small border-top\">" +
            "<i class=\"bi bi-info-circle me-1\"></i> Menampilkan " + MAX_DISPLAY + " barang teratas dari " + matched + " hasil yang cocok. " +
            "Ketik nama atau nomor rekening di kotak pencarian untuk mempersempit.</td></tr>";
  }

  tbody.innerHTML = html;
  var countEl = document.getElementById("modalCatalogCount");
  if (countEl) {
    countEl.textContent = "Menampilkan " + Math.min(matched, MAX_DISPLAY) + " dari " + matched + " barang aktif";
  }
}

function selectAssetAndCloseCatalog(assetId) {
  var item = null;
  for (var i = 0; i < gCurrentList.length; i++) {
    if (gCurrentList[i].id === assetId) {
      item = gCurrentList[i];
      break;
    }
  }

  if (!item) return;

  var select = document.getElementById("selectAssetItem");
  var found = false;
  for (var j = 0; j < select.options.length; j++) {
    if (parseInt(select.options[j].value) === assetId) {
      select.selectedIndex = j;
      found = true;
      break;
    }
  }

  if (!found) {
    var opt = document.createElement("option");
    opt.value = item.id;
    opt.text = "[" + item.kode + "] " + item.nama + " (" + item.cabang + (item.ruangan ? " - " + item.ruangan : "") + ")";
    opt.dataset.json = JSON.stringify(item);
    select.appendChild(opt);
    select.value = item.id;
  }

  onAssetSelected(select);

  var modalEl = document.getElementById("modalCatalogPicker");
  if (modalEl) {
    var modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
  }
}

function onAssetSelected(select) {
  var val = select.value;
  if (!val) {
    document.getElementById("cardAsetDetail").classList.add("d-none");
    resetAssetHiddenFields();
    return;
  }

  var opt = select.options[select.selectedIndex];
  if (!opt || !opt.dataset.json) return;
  
  var data = JSON.parse(opt.dataset.json);
  document.getElementById("dispAssetId").value = data.id;
  document.getElementById("dispKodeAset").value = data.kode;
  document.getElementById("dispNamaAset").value = data.nama;
  document.getElementById("dispMerkModel").value = data.merk || "";
  document.getElementById("dispSerialNumber").value = data.sn || "";
  document.getElementById("dispCabang").value = data.cabang || "Kantor Pusat (KPO)";
  document.getElementById("dispRuangan").value = data.ruangan || "";
  document.getElementById("dispPengguna").value = data.pengguna || "Umum / Pool";

  // Tampilkan Ringkasan
  document.getElementById("lblKodeAset").textContent = data.kode;
  document.getElementById("lblLokasiAset").textContent = data.cabang + (data.ruangan ? " (" + data.ruangan + ")" : "");
  document.getElementById("lblPenggunaAset").textContent = data.pengguna;
  document.getElementById("lblSnAset").textContent = (data.merk ? data.merk : "") + (data.sn ? " [SN: " + data.sn + "]" : "");
  document.getElementById("cardAsetDetail").classList.remove("d-none");
}

function resetAssetHiddenFields() {
  document.getElementById("dispAssetId").value = "0";
  document.getElementById("dispKodeAset").value = "";
  document.getElementById("dispNamaAset").value = "";
  document.getElementById("dispMerkModel").value = "";
  document.getElementById("dispSerialNumber").value = "";
  document.getElementById("dispCabang").value = "Kantor Pusat (KPO)";
  document.getElementById("dispRuangan").value = "";
  document.getElementById("dispPengguna").value = "";
}

function appendAlasan(text) {
  var area = document.getElementById("dispAlasan");
  if (area.value.trim() === "") {
    area.value = text;
  } else {
    area.value += " " + text;
  }
}

function resetDisposalModal() {
  document.getElementById("dispId").value = "0";
  document.getElementById("typeInventaris").checked = true;
  switchAssetType("inventaris");
  document.getElementById("dispNomorBa").value = "";
  document.getElementById("dispAlasan").value = "";
  document.getElementById("cardAsetDetail").classList.add("d-none");
}

function openRestoreModal(id, nama, sisaHari) {
  document.getElementById("restoreDisposalId").value = id;
  document.getElementById("restoreDisposalNama").textContent = nama;
  document.getElementById("restoreDisposalSisaHari").textContent = sisaHari;
  var modal = new bootstrap.Modal(document.getElementById("modalRestoreDisposal"));
  modal.show();
}

function openUploadAccModal(id, noBa) {
  document.getElementById("uploadAccId").value = id;
  document.getElementById("lblAccNomorBa").textContent = noBa;
  var modal = new bootstrap.Modal(document.getElementById("modalUploadAcc"));
  modal.show();
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

// Pre-fetch asset catalog silently in background after window loads
window.addEventListener("load", function() {
  setTimeout(function() {
    loadActiveAssets();
  }, 600);
});

// Listener saat modal disposal dibuka
document.addEventListener("DOMContentLoaded", function() {
  var modalDispEl = document.getElementById("modalDisposal");
  if (modalDispEl) {
    modalDispEl.addEventListener("show.bs.modal", function() {
      loadActiveAssets(function() {
        filterAssetDropdown();
      });
    });
  }
  var selItem = document.getElementById("selectAssetItem");
  if (selItem) {
    selItem.addEventListener("focus", function() {
      if (!gAssetsLoaded) {
        loadActiveAssets(function() {
          filterAssetDropdown();
        });
      }
    });
  }
});
</script>
';

echo render_view('Penghapusan Aset (BA-Afkir)', $body);
