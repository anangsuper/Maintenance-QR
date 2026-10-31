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

// 5. Render Halaman (Kombai Teal Design System)
$head = '
<style>
.status-pill {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 6px;
  white-space: nowrap !important;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  line-height: 1.2;
}
.table thead th {
  background: #F4F5F7;
  color: #53595F;
  font-size: 11px;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  font-weight: 700;
  border-bottom: 1px solid #D1D5D8;
  padding: 10px 14px;
}
.table tbody td {
  padding: 11px 14px;
  font-size: 13px;
  color: #1B2A3A;
  border-bottom: 1px solid #F0F2F4;
}
.table tbody tr:hover {
  background-color: #F8FAF9 !important;
}

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
  max-height: calc(100vh - 200px);
}

/* Kombai Form & Buttons */
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
.btn-kombai-white {
  background: #FFFFFF;
  color: #1B2A3A;
  border: 1px solid #D1D5D8;
  font-weight: 500;
  border-radius: 8px;
  font-size: 13px;
  transition: background-color 0.15s ease;
}
.btn-kombai-white:hover {
  background: #F4F5F7;
  color: #1B2A3A;
}
</style>
';

ob_start();
?>
<?php if ($flashSuccess): ?>
  <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" style="border-left: 4px solid #16A34A !important;">
    <i class="bi bi-check-circle-fill me-2 text-success"></i><?= e($flashSuccess) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php if ($flashError): ?>
  <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" style="border-left: 4px solid #DC2626 !important;">
    <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i><?= e($flashError) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Page Heading Row -->
<div class="mb-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
  <div>
    <div class="text-[11px] font-bold text-[#0E5C58] uppercase tracking-wider mb-1 flex items-center gap-1.5">
      <i class="bi bi-shield-check"></i> PENGELOLAAN SARPRAS &amp; SDM &middot; PT BPR MITRATAMA ARTHABUANA
    </div>
    <h1 class="text-[22px] font-bold text-[#1B2A3A] tracking-tight m-0">Penghapusan &amp; Pemusnahan Aset (BA-Afkir)</h1>
    <p class="text-[13px] text-[#53595F] mt-1 mb-0">Pencatatan resmi Berita Acara pemusnahan fisik inventaris &amp; perangkat IT yang aus atau rusak permanen guna penarikan resmi dari Buku Register Aset Bank.</p>
  </div>

  <!-- Action Buttons Group -->
  <div class="flex flex-wrap items-center gap-2 shrink-0">
    <button type="button" class="h-[36px] px-3.5 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[13px] font-semibold transition-colors inline-flex items-center gap-1.5 border-0 shadow-xs cursor-pointer" data-bs-toggle="modal" data-bs-target="#modalDisposal" onclick="resetDisposalModal()">
      <i class="bi bi-plus-lg"></i> <span>Catat BA-Afkir Baru</span>
    </button>
    <a href="<?= e(module_url('asset_disposal.php', array_merge($_GET, ['export' => 'excel']))) ?>" class="h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-file-earmark-excel text-[#16A34A]"></i> <span>Ekspor Excel</span>
    </a>
    <a href="<?= e(module_url('dashboard.php', ['tab' => 'kartu'])) ?>" class="h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-credit-card-2-front text-[#53595F]"></i> <span>Data Kartu</span>
    </a>
    <a href="<?= e(module_url('memo_pengajuan.php')) ?>" class="h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-file-earmark-text text-[#0E5C58]"></i> <span>Memo Pengadaan</span>
    </a>
  </div>
</div>

<!-- 3 SYMMETRICAL KOMBAI METRIC CARDS (88px) -->
<div class="bg-[#FFFFFF] border border-[#D1D5D8] rounded-[10px] h-[88px] flex overflow-hidden shrink-0 mb-4">
  <!-- Card 1: Total Aset Diafkirkan -->
  <div class="flex-1 flex flex-col justify-center px-6 border-r border-[#D1D5D8] relative hover:bg-[#F4F5F7] transition-colors">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0E5C58]"></div>
    <div class="text-[28px] font-bold text-[#1B2A3A] tabular-nums leading-none">
      <?= number_format($kpi['total_unit']) ?> <span class="text-[13px] font-normal text-[#53595F]">Unit</span>
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-2">
      <span class="text-[#D97706] font-semibold"><i class="bi bi-credit-card-2-front-fill me-0.5"></i> <?= $kpi['total_sdm'] ?> SDM</span>
      <span>&middot;</span>
      <span class="text-[#0284C7] font-semibold"><i class="bi bi-pc-display me-0.5"></i> <?= $kpi['total_it'] ?> IT</span>
      <span class="text-[#53595F] ms-auto small d-none d-lg-inline">(+<?= $kpi['total_bulan_ini'] ?> bulan ini)</span>
    </div>
  </div>

  <!-- Card 2: Masa Tenggang 7 Hari -->
  <div class="flex-1 flex flex-col justify-center px-6 border-r border-[#D1D5D8] relative hover:bg-[#F4F5F7] transition-colors">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#10B981]"></div>
    <div class="text-[28px] font-bold text-[#10B981] tabular-nums leading-none">
      <?= $countRestorable ?> <span class="text-[13px] font-normal text-[#53595F]">Unit</span>
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
      <i class="bi bi-arrow-counterclockwise text-[#10B981]"></i> Masa tenggang 7 hari (dapat dibatalkan)
    </div>
  </div>

  <!-- Card 3: Status Kepatuhan SOP Afkir -->
  <div class="flex-1 flex flex-col justify-center px-6 relative hover:bg-[#F4F5F7] transition-colors">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#3B82F6]"></div>
    <div class="text-[28px] font-bold text-[#1B2A3A] tabular-nums leading-none">
      100% <span class="text-[13px] font-normal text-[#53595F]">Terarsip</span>
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
      <i class="bi bi-check-all text-[#10B981]"></i> Standar Cetak A4, Approval 3 Pihak &amp; Audit Trail
    </div>
  </div>
</div>

<!-- DATA TABLE ARSIP BERITA ACARA -->
<div class="bg-white border border-[#D1D5D8] rounded-[12px] shadow-xs overflow-hidden mb-4">
  <!-- Table Header & Live Count -->
  <div class="px-5 py-3.5 border-b border-[#D1D5D8] flex flex-wrap items-center justify-between gap-3 bg-white">
    <div class="flex items-center gap-2">
      <i class="bi bi-archive-fill text-[#0E5C58] text-[16px]"></i>
      <span class="text-[14px] font-bold text-[#1B2A3A] uppercase tracking-wide">Daftar Berita Acara Penghapusan Aset (BA-Afkir)</span>
      <span class="px-2 py-0.5 text-[11px] font-bold bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC] rounded-full font-mono"><?= count($disposalList) ?> Dokumen</span>
    </div>
    <div class="text-[12px] text-[#53595F]">
      Menampilkan <strong class="text-[#1B2A3A]"><?= count($disposalList) ?></strong> dokumen BA-Afkir
    </div>
  </div>

  <!-- Filter Toolbar (Symmetrical 36px) -->
  <div class="p-3.5 border-b border-[#D1D5D8] bg-[#F8FAF9]">
    <form method="GET" action="<?= e(module_url('asset_disposal.php')) ?>" class="row g-2 align-items-center m-0">
      <div class="col-lg-6 col-md-12 p-0 pe-md-1">
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 8px 0 0 8px; height: 36px; border-color: #D1D5D8;"><i class="bi bi-search"></i></span>
          <input type="text" name="search" class="form-control border-start-0 ps-0" style="border-radius: 0 8px 8px 0; font-size: 13px; height: 36px; border-color: #D1D5D8;" placeholder="Cari No. BA, Nama Aset, No. Rekening, Alasan..." value="<?= e($filters['search']) ?>">
        </div>
      </div>
      <div class="col-lg-4 col-md-8 p-0 px-md-1">
        <select name="cabang" class="form-select form-select-sm" style="border-radius: 8px; height: 36px; font-size: 13px; border-color: #D1D5D8;" onchange="this.form.submit()">
          <option value="all" <?= ($filters['cabang']==='all'?'selected':'') ?>>Semua Kantor Cabang</option>
          <?= $cabangOptionsHtml ?>
        </select>
      </div>
      <div class="col-lg-2 col-md-4 p-0 ps-md-1 d-flex gap-1.5">
        <button type="submit" class="h-[36px] px-3.5 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[13px] font-semibold transition-colors flex-fill inline-flex items-center justify-center gap-1.5 border-0 shadow-xs cursor-pointer">
          <i class="bi bi-funnel"></i> <span>Filter</span>
        </button>
        <?php if (!empty($filters['search']) || $filters['cabang']!=='all'): ?>
        <a href="<?= e(module_url('asset_disposal.php')) ?>" class="h-[36px] w-[36px] bg-white border border-[#D1D5D8] rounded-[8px] text-[#53595F] hover:bg-[#F4F5F7] transition-colors inline-flex items-center justify-center text-decoration-none shadow-xs shrink-0" title="Reset Filter">
          <i class="bi bi-arrow-counterclockwise"></i>
        </a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
      <thead>
        <tr>
          <th class="ps-3" style="width: 18%;">No. BA &amp; Tanggal</th>
          <th style="width: 27%;">Identitas Aset</th>
          <th style="width: 17%;">Lokasi / Cabang</th>
          <th style="width: 18%;">Kondisi &amp; Alasan Afkir</th>
          <th style="width: 10%;">Dokumentasi</th>
          <th class="text-end pe-3" style="width: 10%;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($disposalList)): ?>
          <tr>
            <td colspan="6" class="text-center py-5 text-muted">
              <i class="bi bi-trash3 fs-1 text-secondary opacity-50 d-block mb-2"></i>
              <div class="fw-semibold text-[14px]">Belum Ada Catatan Berita Acara Penghapusan Aset</div>
              <p class="small text-muted mb-3">Klik tombol "Catat BA-Afkir Baru" di atas untuk memproses penghapusan inventaris rusak.</p>
              <button type="button" class="btn btn-sm btn-primary-teal fw-bold px-3 py-1.5" data-bs-toggle="modal" data-bs-target="#modalDisposal" onclick="resetDisposalModal()">
                <i class="bi bi-plus-circle me-1"></i> Buat Berita Acara Pertama
              </button>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($disposalList as $row): 
            $assetBadge = ($row['asset_type'] === 'inventaris') 
                ? '<span class="badge bg-[#FEF3C7] text-[#92400E] border border-[#FDE68A] px-2 py-0.5 text-[11px] font-semibold"><i class="bi bi-credit-card-2-front me-1"></i>Kartu SDM</span>' 
                : '<span class="badge bg-[#E0F2FE] text-[#0369A1] border border-[#BAE6FD] px-2 py-0.5 text-[11px] font-semibold"><i class="bi bi-pc-display me-1"></i>Perangkat IT</span>';

            $restoreInfo = is_disposal_restorable($row);
            $canRestore = $restoreInfo['can_restore'];
            $daysRemaining = $restoreInfo['days_remaining'];
            $printUrl = module_url('print_bast_disposal.php', ['id' => $row['id']]);
          ?>
          <tr>
            <td class="ps-3">
              <a href="<?= $printUrl ?>" target="_blank" class="fw-bold text-[#0E5C58] font-mono text-decoration-none d-block text-break" style="font-size:0.78rem; line-height: 1.3;">
                <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i><?= e($row['nomor_ba']) ?>
              </a>
              <div class="text-muted small mt-0.5" style="font-size:0.72rem;"><i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y', strtotime($row['tanggal_disposal'])) ?></div>
              <div class="text-secondary small mt-0.5" style="font-size:0.72rem;">Petugas: <?= e($row['petugas_nama']) ?></div>
              <?php if ($canRestore): ?>
                <div class="mt-1">
                  <span class="badge bg-[#DCFCE7] text-[#15803D] border border-[#86EFAC] py-0.5 px-2 font-semibold" style="font-size: 0.68rem;" title="Aset masih dapat dikembalikan ke inventaris dalam 7 hari">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Bisa Batal (Sisa <?= $daysRemaining ?> hari)
                  </span>
                </div>
              <?php else: ?>
                <div class="mt-1">
                  <span class="badge bg-[#F4F5F7] text-[#53595F] border border-[#D1D5D8] py-0.5 px-2" style="font-size: 0.68rem;" title="Masa tenggang 7 hari telah lewat, status permanen">
                    <i class="bi bi-lock-fill me-1"></i>Permanen (&gt;7 hari)
                  </span>
                </div>
              <?php endif; ?>
            </td>
            <td>
              <div class="d-flex align-items-center gap-1.5 mb-1">
                <?= $assetBadge ?>
                <span class="font-mono text-muted small ms-1">[<?= e($row['kode_aset']) ?>]</span>
              </div>
              <div class="fw-bold text-[#1B2A3A] text-[13px]"><?= e($row['nama_aset']) ?></div>
              <?php if (!empty($row['merk_model']) && $row['merk_model'] !== '-'): ?>
                <div class="text-[#53595F] text-[12px]"><?= e($row['merk_model']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge bg-light text-dark border px-2 py-0.5 text-[11px] font-semibold mb-1"><?= e($row['cabang']) ?></span>
              <div class="text-[#53595F] text-[12px]"><i class="bi bi-door-open me-1"></i><?= e($row['lokasi_ruangan'] ?: '-') ?></div>
              <div class="text-secondary text-[11px]">PJ: <?= e($row['pengguna_terakhir'] ?: '-') ?></div>
            </td>
            <td>
              <div class="text-[#DC2626] font-bold text-[12px] mb-1">
                <i class="bi bi-exclamation-triangle-fill me-1"></i><?= e($row['kondisi_terakhir']) ?>
              </div>
              <div class="text-[#53595F] text-[12px] text-truncate" style="max-width: 220px;" title="<?= e($row['alasan_penghapusan']) ?>">
                <?= e($row['alasan_penghapusan']) ?>
              </div>
              <div class="badge bg-[#F4F5F7] text-[#53595F] border border-[#D1D5D8] text-[11px] mt-1">
                <?= e($row['metode_pemusnahan']) ?>
              </div>
            </td>
            <td>
              <div class="d-flex flex-column gap-1">
                <?php if (!empty($row['foto_barang'])): ?>
                  <a href="<?= e(disposal_file_url($row['foto_barang'])) ?>" target="_blank" class="badge bg-light text-secondary border shadow-xs text-decoration-none d-inline-flex align-items-center gap-1 px-2 py-1" style="font-size: 0.68rem;">
                    <i class="bi bi-camera text-primary"></i> Foto Fisik
                  </a>
                <?php endif; ?>
                <?php if (!empty($row['dokumen_ba_disetujui'])): ?>
                  <a href="<?= e(disposal_file_url($row['dokumen_ba_disetujui'])) ?>" target="_blank" class="badge bg-[#DCFCE7] text-[#15803D] border border-[#86EFAC] shadow-xs text-decoration-none d-inline-flex align-items-center gap-1 px-2 py-1" style="font-size: 0.68rem;">
                    <i class="bi bi-file-earmark-check-fill"></i> Scan BA Acc ✓
                  </a>
                <?php else: ?>
                  <button type="button" class="btn btn-sm btn-kombai-white py-0.5 px-2 text-[11px] d-inline-flex align-items-center gap-1 shadow-xs" onclick="openUploadAccModal(<?= (int)$row['id'] ?>, <?= htmlspecialchars(json_encode($row['nomor_ba']), ENT_QUOTES, 'UTF-8') ?>)">
                    <i class="bi bi-cloud-arrow-up text-[#0E5C58]"></i> Upload Scan
                  </button>
                <?php endif; ?>
              </div>
            </td>
            <td class="text-end pe-3 text-nowrap">
              <div class="d-inline-flex align-items-center justify-content-end gap-1.5 flex-nowrap">
                <a href="<?= $printUrl ?>" target="_blank" class="btn btn-sm btn-outline-teal px-2 py-1 shadow-xs" style="font-size:0.75rem;" title="Cetak Berita Acara BA-Afkir">
                  <i class="bi bi-printer me-1"></i> Cetak
                </a>
                <?php if ($canRestore): ?>
                  <button type="button" class="btn btn-sm px-2 py-1 bg-[#DCFCE7] hover:bg-[#BBF7D0] text-[#15803D] border border-[#86EFAC] rounded-[8px] font-semibold text-[12px] shadow-xs cursor-pointer inline-flex items-center gap-1" onclick="openRestoreModal(<?= (int)$row['id'] ?>, <?= htmlspecialchars(json_encode($row['nama_aset']), ENT_QUOTES, 'UTF-8') ?>, <?= (int)$daysRemaining ?>)" title="Batalkan penghapusan &amp; kembalikan ke inventaris aktif">
                    <i class="bi bi-arrow-counterclockwise"></i> Batal
                  </button>
                <?php endif; ?>
                <div class="dropdown d-inline-block">
                  <button class="btn btn-sm btn-kombai-white px-2 py-1 shadow-xs dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Menu Opsi Lainnya">
                    <i class="bi bi-three-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow border py-1" style="font-size:0.80rem; min-width: 195px;">
                    <li>
                      <a class="dropdown-item py-1.5" href="<?= $printUrl ?>" target="_blank">
                        <i class="bi bi-file-earmark-pdf text-[#0E5C58] me-2"></i> Cetak Berita Acara
                      </a>
                    </li>
                    <li>
                      <button class="dropdown-item py-1.5" type="button" onclick="openUploadAccModal(<?= (int)$row['id'] ?>, <?= htmlspecialchars(json_encode($row['nomor_ba']), ENT_QUOTES, 'UTF-8') ?>)">
                        <i class="bi bi-cloud-arrow-up text-[#0284C7] me-2"></i> Upload Scan BA Disetujui
                      </button>
                    </li>
                    <?php if ($canRestore): ?>
                      <li><hr class="dropdown-divider my-1"></li>
                      <li>
                        <button class="dropdown-item py-1.5 text-success font-semibold" type="button" onclick="openRestoreModal(<?= (int)$row['id'] ?>, <?= htmlspecialchars(json_encode($row['nama_aset']), ENT_QUOTES, 'UTF-8') ?>, <?= (int)$daysRemaining ?>)">
                          <i class="bi bi-arrow-counterclockwise text-success me-2"></i> Kembalikan ke Inventaris (Sisa <?= $daysRemaining ?> Hari)
                        </button>
                      </li>
                    <?php endif; ?>
                  </ul>
                </div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ==================================================== -->
<!-- MODAL CATAT BERITA ACARA PENGHAPUSAN ASET (BA-AFKIR) -->
<!-- ==================================================== -->
<div class="modal fade" id="modalDisposal" tabindex="-1" aria-labelledby="modalDisposalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <form method="POST" action="<?= e(module_url('asset_disposal.php')) ?>" enctype="multipart/form-data" id="formDisposal" class="modal-content border-0 shadow-lg rounded-[12px] overflow-hidden">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="dispId" value="0">
        
      <div class="modal-header bg-[#1B2A3A] text-white border-0 py-3 px-4">
        <h5 class="modal-title fw-bold text-[16px] flex items-center gap-2 m-0" id="modalDisposalLabel">
          <i class="bi bi-trash3-fill text-[#EF4444]"></i> Catat Berita Acara Penghapusan Aset (BA-Afkir)
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-4">
        <!-- 1. Pilihan Kategori Sumber Aset -->
        <div class="mb-3">
          <label class="form-label fw-bold text-[#1B2A3A] text-[12px] uppercase">PILIH SUMBER BUKU REGISTER ASET <span class="text-danger">*</span></label>
          <div class="d-flex gap-3">
            <div class="form-check border border-[#D1D5D8] rounded-[8px] p-2.5 flex-fill bg-[#F8FAF9]">
              <input class="form-check-input ms-0 me-2" type="radio" name="asset_type" id="typeInventaris" value="inventaris" checked onchange="switchAssetType(this.value)">
              <label class="form-check-label fw-semibold text-[#1B2A3A]" for="typeInventaris" style="cursor: pointer;">
                <i class="bi bi-credit-card-2-front text-warning me-1"></i> Kartu Inventaris Fisik (SDM &amp; Umum)
                <div class="text-[#53595F] fw-normal text-[11px]">Perabot, Meja, Kursi, AC, Brankas, Kipas, Dispenser, Kendaraan</div>
              </label>
            </div>
            <div class="form-check border border-[#D1D5D8] rounded-[8px] p-2.5 flex-fill bg-[#F8FAF9]">
              <input class="form-check-input ms-0 me-2" type="radio" name="asset_type" id="typeKomputer" value="komputer" onchange="switchAssetType(this.value)">
              <label class="form-check-label fw-semibold text-[#1B2A3A]" for="typeKomputer" style="cursor: pointer;">
                <i class="bi bi-pc-display text-info me-1"></i> Perangkat IT / Komputer (IT Ops)
                <div class="text-[#53595F] fw-normal text-[11px]">PC Desktop, Laptop, Monitor, Printer, Scanner, Server</div>
              </label>
            </div>
          </div>
        </div>

        <!-- 2. Pilih Barang Aset Aktif -->
        <div class="mb-3">
          <div class="d-flex align-items-center justify-content-between mb-1.5">
            <label class="form-label fw-bold text-[#1B2A3A] text-[12px] uppercase mb-0" for="selectAssetItem">
              PILIH UNIT BARANG INVENTARIS YANG DIAFKIRKAN <span class="text-danger">*</span>
            </label>
            <button type="button" class="btn btn-xs btn-outline-teal px-2 py-0.5 font-semibold text-[11px]" onclick="openCatalogModal()">
              <i class="bi bi-grid-3x3-gap-fill me-1"></i> Buka Katalog Tabel
            </button>
          </div>

          <!-- Tombol Utama: Buka Katalog Modal Pencarian -->
          <button type="button" class="btn btn-primary-teal w-100 mb-2.5 d-flex align-items-center justify-content-center gap-2 py-2 shadow-xs" onclick="openCatalogModal()">
            <i class="bi bi-grid-3x3-gap-fill fs-5"></i>
            <span class="fw-bold">Buka Katalog &amp; Cari Barang di Tabel</span>
            <span class="badge bg-white text-[#0E5C58] fw-bold ms-1" id="badgeTotalItems">0 Barang</span>
          </button>

          <!-- Box Pencarian Cepat & Filter Cabang -->
          <div class="p-2.5 rounded-[8px] bg-[#F8FAF9] border border-[#D1D5D8] mb-2">
            <div class="row g-2">
              <div class="col-sm-5 col-12">
                <label class="form-label text-uppercase fw-bold text-[#53595F] text-[11px] mb-1">Filter Cabang</label>
                <select id="filterCabangAset" class="form-select form-select-sm" onchange="filterAssetDropdown()">
                  <option value="">-- Semua Cabang --</option>
                  <?php foreach ($cabangMaster as $cb): 
                    $cName = $cb['nama_cabang'] ?? '';
                    if ($cName === '') continue;
                  ?>
                    <option value="<?= e($cName) ?>"><?= e($cName) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-sm-7 col-12">
                <label class="form-label text-uppercase fw-bold text-[#53595F] text-[11px] mb-1">Cari Nama / No. Rekening / Ruangan</label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-white border-end-0 text-muted" style="border-color: #D1D5D8;"><i class="bi bi-search"></i></span>
                  <input type="text" id="quickSearchAsset" class="form-control border-start-0" style="border-color: #D1D5D8;" placeholder="Ketik kata kunci untuk mencari..." oninput="filterAssetDropdown()" autocomplete="off">
                  <button class="btn btn-kombai-white" type="button" onclick="clearQuickSearch()" title="Reset pencarian"><i class="bi bi-x"></i></button>
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
            <button type="button" class="btn btn-link btn-xs text-[#0E5C58] text-decoration-none p-0 fw-semibold" onclick="clearQuickSearch()" style="font-size: 0.74rem;">Reset Pencarian</button>
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
        <div class="card bg-[#F8FAF9] border border-[#D1D5D8] rounded-[8px] p-3 mb-3 d-none" id="cardAsetDetail">
          <div class="row g-2 small">
            <div class="col-sm-6">
              <div class="text-[#53595F] text-[11px] uppercase">KODE / NO. REKENING:</div>
              <div class="fw-bold font-mono text-[#0E5C58]" id="lblKodeAset">-</div>
            </div>
            <div class="col-sm-6">
              <div class="text-[#53595F] text-[11px] uppercase">KANTOR CABANG &amp; RUANGAN:</div>
              <div class="fw-bold text-[#1B2A3A]" id="lblLokasiAset">-</div>
            </div>
            <div class="col-sm-6">
              <div class="text-[#53595F] text-[11px] uppercase">PENANGGUNG JAWAB:</div>
              <div class="fw-semibold text-[#1B2A3A]" id="lblPenggunaAset">-</div>
            </div>
            <div class="col-sm-6">
              <div class="text-[#53595F] text-[11px] uppercase">SERIAL NUMBER / MERK:</div>
              <div class="text-[#1B2A3A]" id="lblSnAset">-</div>
            </div>
          </div>
        </div>

        <hr class="my-3 text-muted">

        <!-- 3. Nomor Dokumen & Tanggal Pelaksanaan -->
        <div class="row g-3 mb-3">
          <div class="col-md-7 col-12">
            <label class="form-label fw-bold text-[#1B2A3A] text-[12px] uppercase" for="dispNomorBa">NOMOR BERITA ACARA (AUTO)</label>
            <input type="text" name="nomor_ba" id="dispNomorBa" class="form-control font-mono bg-[#F8FAF9] fw-bold" placeholder="Dibuat otomatis oleh sistem...">
            <div class="form-text small" style="font-size: 0.72rem;">Format resmi: 001/BA-AFKIR/SDM-UMUM/BANK-MITRA.KPO/X/2026.</div>
          </div>
          <div class="col-md-5 col-12">
            <label class="form-label fw-bold text-[#1B2A3A] text-[12px] uppercase" for="dispTanggal">TANGGAL PEMERIKSAAN / AFKIR <span class="text-danger">*</span></label>
            <input type="date" name="tanggal_disposal" id="dispTanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>

        <!-- 4. Kondisi Fisik & Metode Pemusnahan -->
        <div class="row g-3 mb-3">
          <div class="col-md-6 col-12">
            <label class="form-label fw-bold text-[#1B2A3A] text-[12px] uppercase" for="dispKondisi">KONDISI FISIK TERAKHIR <span class="text-danger">*</span></label>
            <select name="kondisi_terakhir" id="dispKondisi" class="form-select form-select-sm" required>
              <option value="Rusak Berat / Tidak Ekonomis Diperbaiki">Rusak Berat / Tidak Ekonomis Diperbaiki</option>
              <option value="Mati Total / Tidak Dapat Menyala">Mati Total / Tidak Dapat Menyala</option>
              <option value="Rangka Fisik Patah / Lapuk / Keropos">Rangka Fisik Patah / Lapuk / Keropos</option>
              <option value="Usang / Masa Manfaat Habis">Usang / Masa Manfaat Habis</option>
            </select>
          </div>
          <div class="col-md-6 col-12">
            <label class="form-label fw-bold text-[#1B2A3A] text-[12px] uppercase" for="dispMetode">METODE PEMUSNAHAN / PENYINGKIRAN <span class="text-danger">*</span></label>
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
          <label class="form-label fw-bold text-[#1B2A3A] text-[12px] uppercase" for="dispAlasan">ALASAN &amp; PERTIMBANGAN TEKNIS PENGHAPUSAN <span class="text-danger">*</span></label>
          <div class="d-flex flex-wrap gap-1 mb-2">
            <button type="button" class="btn btn-xs btn-kombai-white" onclick="appendAlasan('Biaya perbaikan melebihi nilai beli unit baru.')">+ Biaya Servis Mahal</button>
            <button type="button" class="btn btn-xs btn-kombai-white" onclick="appendAlasan('Rangka fisik patah dan lapuk sehingga tidak aman dipakai.')">+ Rangka Patah</button>
            <button type="button" class="btn btn-xs btn-kombai-white" onclick="appendAlasan('Komponen elektronik vital rusak dan sparepart tidak lagi tersedia.')">+ Sparepart Tidak Ada</button>
            <button type="button" class="btn btn-xs btn-kombai-white" onclick="appendAlasan('Masa manfaat ekonomis telah habis dan tidak layak operasi.')">+ Masa Manfaat Habis</button>
          </div>
          <textarea name="alasan_penghapusan" id="dispAlasan" class="form-control form-control-sm" rows="3" required placeholder="Jelaskan pertimbangan teknis mengapa barang ini dihapus / diafkirkan..."></textarea>
        </div>

        <!-- 6. Upload Foto Fisik Barang Terakhir -->
        <div class="mb-3">
          <label class="form-label fw-bold text-[#1B2A3A] text-[12px] uppercase" for="dispFoto">UPLOAD FOTO KONDISI FISIK BARANG RUSAK</label>
          <input type="file" name="foto_barang" id="dispFoto" class="form-control form-control-sm" accept="image/*">
          <div class="form-text small" style="font-size: 0.72rem;">Lampirkan bukti foto fisik kerusakan barang sebagai kelengkapan audit.</div>
        </div>

        <!-- 7. Penandatangan Berita Acara -->
        <div class="card bg-[#F8FAF9] border border-[#D1D5D8] rounded-[8px] p-3 mb-3">
          <div class="fw-bold text-[#1B2A3A] text-[12px] uppercase mb-2"><i class="bi bi-pen"></i> PEJABAT PENANDATANGAN BERITA ACARA</div>
          <div class="row g-2">
            <div class="col-md-4 col-12">
              <label class="form-label text-[11px] text-[#53595F] uppercase">YANG MEMERIKSA (PETUGAS)</label>
              <input type="text" name="petugas_nama" id="dispPetugasNama" class="form-control form-control-sm" value="<?= e($_SESSION['nama'] ?? 'Staf Bagian Umum') ?>" required>
              <input type="text" name="petugas_jabatan" id="dispPetugasJabatan" class="form-control form-control-sm mt-1" value="<?= (is_hrd() ? 'Staf Bagian Umum' : 'Staf IT Operations') ?>">
            </div>
            <div class="col-md-4 col-12">
              <label class="form-label text-[11px] text-[#53595F] uppercase">MENGETAHUI</label>
              <input type="text" name="nama_menyetujui" id="dispNamaMengetahui" class="form-control form-control-sm" value="Defie Damayanti" required>
              <input type="text" name="jabatan_menyetujui" id="dispJabatanMengetahui" class="form-control form-control-sm mt-1" value="Kabag. Umum &amp; SDM">
            </div>
            <div class="col-md-4 col-12">
              <label class="form-label text-[11px] text-[#53595F] uppercase">MENYETUJUI</label>
              <input type="text" name="nama_menyetujui" id="dispNamaMenyetujui" class="form-control form-control-sm" value="Kahar Muzaqir, SH" required>
              <input type="text" name="jabatan_menyetujui" id="dispJabatanMenyetujui" class="form-control form-control-sm mt-1" value="Direktur Operasional">
            </div>
          </div>
        </div>

        <!-- Opsi Cetak Langsung -->
        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" name="print_after" value="1" id="chkPrintAfter" checked>
          <label class="form-check-label small fw-semibold text-[#1B2A3A]" for="chkPrintAfter">
            Langsung buka dokumen Berita Acara siap cetak setelah data disimpan
          </label>
        </div>

      </div>

      <div class="modal-footer bg-[#F8FAF9] py-2 px-4 border-t border-[#D1D5D8] flex items-center justify-end gap-2">
        <button type="button" class="btn btn-kombai-white btn-sm px-3" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary-teal fw-bold btn-sm px-4">
          <i class="bi bi-check-circle-fill me-1"></i> Simpan &amp; Proses BA-Afkir
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Konfirmasi Pengembalian Aset (Restore Sebelum 7 Hari) -->
<div class="modal fade" id="modalRestoreDisposal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="<?= e(module_url('asset_disposal.php')) ?>" class="modal-content border-0 shadow-lg rounded-[12px] overflow-hidden">
      <input type="hidden" name="action" value="restore">
      <input type="hidden" name="id" id="restoreDisposalId" value="0">
      <div class="modal-header bg-[#10B981] text-white border-0 py-3 px-4">
        <h6 class="modal-title fw-bold text-[15px] flex items-center gap-2 m-0">
          <i class="bi bi-arrow-counterclockwise fs-5"></i> Batalkan Penghapusan &amp; Kembalikan Aset
        </h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <div class="mb-3">
          <div class="d-inline-flex p-3 rounded-circle bg-[#DCFCE7] text-[#15803D]">
            <i class="bi bi-arrow-counterclockwise fs-1"></i>
          </div>
        </div>
        <h5 class="fw-bold text-[#1B2A3A] mb-2">Kembalikan ke Inventaris Aktif?</h5>
        <p class="text-[#53595F] text-[13px] mb-3">
          Aset <strong id="restoreDisposalNama" class="text-[#1B2A3A]">-</strong> akan dipulihkan status fisiknya menjadi <strong>Aktif</strong> di buku register inventaris Bank Mitra, dan Berita Acara afkir ini akan dibatalkan.
        </p>
        <div class="alert alert-success border-0 py-2 px-3 small d-inline-flex align-items-center gap-2 mb-0" style="background:#EDF7EE; color:#166534; border-radius:8px;">
          <i class="bi bi-clock-history text-success"></i>
          <span>Masa tenggang tersisa: <strong id="restoreDisposalSisaHari">7</strong> hari</span>
        </div>
      </div>
      <div class="modal-footer bg-[#F8FAF9] border-t border-[#D1D5D8] py-2.5 px-4 justify-content-end gap-2">
        <button type="button" class="btn btn-sm btn-kombai-white px-3" data-bs-dismiss="modal">Tutup</button>
        <button type="submit" class="btn btn-sm btn-primary-teal font-semibold px-3">
          <i class="bi bi-check-circle me-1"></i> Ya, Kembalikan ke Inventaris
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Upload Scan Berita Acara Disetujui -->
<div class="modal fade" id="modalUploadAcc" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-[12px] overflow-hidden">
      <form method="POST" action="<?= e(module_url('asset_disposal.php')) ?>" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload_acc">
        <input type="hidden" name="id" id="uploadAccId" value="0">
        <div class="modal-header bg-[#1B2A3A] text-white border-0 py-3 px-4">
          <h6 class="modal-title fw-bold text-[15px] flex items-center gap-2 m-0">
            <i class="bi bi-cloud-arrow-up text-[#0284C7]"></i> Upload Scan Berita Acara Disetujui
          </h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="text-[13px] text-[#53595F] mb-3">
            Unggah dokumen scan Berita Acara fisik yang telah ditandatangani dan distempel basah untuk nomor:
            <div class="fw-bold font-mono text-[#0E5C58] text-[14px] mt-1" id="lblAccNomorBa">-</div>
          </div>
          <div class="mb-3">
            <label class="form-label text-[12px] fw-bold text-[#1B2A3A] uppercase">PILIH FILE BERKAS (PDF / GAMBAR)</label>
            <input type="file" name="dokumen_ba_disetujui" class="form-control form-control-sm" accept=".pdf,image/*" required>
          </div>
        </div>
        <div class="modal-footer bg-[#F8FAF9] py-2 px-4 border-t border-[#D1D5D8] flex items-center justify-end gap-2">
          <button type="button" class="btn btn-kombai-white btn-sm" data-bs-dismiss="modal">Tutup</button>
          <button type="submit" class="btn btn-primary-teal btn-sm fw-bold px-3">Unggah Dokumen</button>
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
    <div class="modal-content border-0 shadow-2xl rounded-[12px] overflow-hidden">
      <div class="modal-header bg-[#1B2A3A] text-white border-0 py-3 px-4">
        <h5 class="modal-title fw-bold text-[16px] flex items-center gap-2 m-0" id="modalCatalogPickerLabel">
          <i class="bi bi-grid-3x3-gap-fill text-[#10B981]"></i> Katalog &amp; Pencarian Barang Inventaris Aktif
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-3 p-md-4">
        <!-- Pencarian & Filter Cabang di Modal -->
        <div class="row g-2 mb-3">
          <div class="col-md-7 col-12">
            <div class="input-group">
              <span class="input-group-text bg-white border-end-0 text-muted" style="border-color: #D1D5D8;"><i class="bi bi-search"></i></span>
              <input type="text" id="modalCatalogSearchInput" class="form-control border-start-0" style="border-color: #D1D5D8;" placeholder="Ketik nama barang, nomor rekening, ruangan, penanggung jawab..." oninput="renderCatalogRows()" autocomplete="off">
              <button class="btn btn-kombai-white" type="button" onclick="clearCatalogSearch()"><i class="bi bi-x-circle me-1"></i>Reset</button>
            </div>
          </div>
          <div class="col-md-5 col-12">
            <select id="modalCatalogBranchFilter" class="form-select" onchange="renderCatalogRows()" style="border-color: #D1D5D8;">
              <option value="">-- Seluruh Kantor Cabang --</option>
              <?php foreach ($cabangMaster as $cb): 
                $cName = $cb['nama_cabang'] ?? '';
                if ($cName === '') continue;
              ?>
                <option value="<?= e($cName) ?>"><?= e($cName) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Tabel Katalog Barang -->
        <div class="table-responsive border border-[#D1D5D8] rounded-[8px]" style="max-height: 460px;">
          <table class="table table-hover align-middle mb-0" id="tableCatalogAsset" style="font-size: 13px;">
            <thead class="sticky-top" style="z-index: 2;">
              <tr>
                <th style="width: 45px;" class="text-center font-mono">NO</th>
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

      <div class="modal-footer bg-[#F8FAF9] d-flex justify-content-between py-2.5 px-4 border-t border-[#D1D5D8]">
        <span class="text-[#53595F] text-[12px]" id="modalCatalogCount">Memuat data barang...</span>
        <button type="button" class="btn btn-kombai-white px-4 fw-semibold btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
<?php
$body = ob_get_clean();

ob_start();
?>
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
  if (lblCount) lblCount.innerHTML = '<span class="spinner-border spinner-border-sm text-[#0E5C58] me-1"></span>Memuat daftar barang aktif...';

  fetch("asset_disposal?action=get_active_assets")
    .then(function(res) {
      if (!res.ok) throw new Error("HTTP " + res.status);
      return res.json();
    })
    .then(function(data) {
      gInventaris = data.inventaris || [];
      gKomputer = data.komputer || [];
      var curType = document.querySelector('input[name="asset_type"]:checked');
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
      if (lblCount) lblCount.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Gagal memuat data. Coba lagi.</span>';
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
    select.innerHTML = '<option value="">-- Sedang memuat daftar barang... --</option>';
    return;
  }

  var branch = (document.getElementById("filterCabangAset") ? document.getElementById("filterCabangAset").value : "").toLowerCase().trim();
  var query = (document.getElementById("quickSearchAsset") ? document.getElementById("quickSearchAsset").value : "").toLowerCase().trim();

  select.innerHTML = '<option value="">-- Pilih Barang dari Hasil Pencarian --</option>';

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
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm text-[#0E5C58] me-2"></div>Memuat katalog barang aktif...</td></tr>';
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
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm text-[#0E5C58] me-2"></div>Memuat katalog barang aktif...</td></tr>';
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
    var rowClass = isSelected ? "bg-[#D9F1EF] font-semibold" : "";

    html += '<tr class="' + rowClass + '" style="cursor: pointer;" onclick="selectAssetAndCloseCatalog(' + item.id + ')">';
    html += '<td class="text-center font-mono text-muted small">' + matched + '</td>';
    html += '<td>';
    html += '<div class="fw-bold font-mono text-[#0E5C58]">' + escapeHtml(item.kode) + '</div>';
    if (item.sn) {
      html += '<div class="small text-muted font-mono" style="font-size: 0.72rem;">SN: ' + escapeHtml(item.sn) + '</div>';
    }
    html += '</td>';
    html += '<td>';
    html += '<div class="fw-bold text-[#1B2A3A]">' + escapeHtml(item.nama) + '</div>';
    html += '<span class="badge bg-light text-secondary border small mt-0.5">' + escapeHtml(item.merk || "Fasilitas") + '</span>';
    html += '</td>';
    html += '<td>';
    html += '<div class="fw-semibold text-[#1B2A3A]">' + escapeHtml(item.cabang) + '</div>';
    html += '<div class="small text-muted"><i class="bi bi-door-open me-1"></i>' + escapeHtml(item.ruangan || "-") + '</div>';
    html += '</td>';
    html += '<td><div class="text-[#1B2A3A] small">' + escapeHtml(item.pengguna || "-") + '</div></td>';
    html += '<td class="text-center" onclick="event.stopPropagation()">';
    html += '<button type="button" class="btn btn-sm ' + (isSelected ? "btn-primary-teal" : "btn-outline-teal") + ' fw-bold px-3 py-1 shadow-xs" onclick="selectAssetAndCloseCatalog(' + item.id + ')">';
    html += '<i class="bi ' + (isSelected ? "bi-check-circle-fill" : "bi-check-lg") + ' me-1"></i> ' + (isSelected ? "Terpilih" : "Pilih");
    html += '</button>';
    html += '</td>';
    html += '</tr>';
  }

  if (matched === 0) {
    html = '<tr><td colspan="6" class="text-center py-5 text-muted">' +
           '<i class="bi bi-search fs-2 d-block mb-2 opacity-50"></i>' +
           'Tidak ada barang inventaris yang sesuai dengan filter pencarian.<br>' +
           '<button type="button" class="btn btn-link btn-sm mt-2 text-decoration-none text-[#0E5C58]" onclick="clearCatalogSearch()">Reset Pencarian</button>' +
           '</td></tr>';
  } else if (matched > MAX_DISPLAY) {
    html += '<tr><td colspan="6" class="text-center py-2.5 bg-light text-muted small border-top">' +
            '<i class="bi bi-info-circle me-1"></i> Menampilkan ' + MAX_DISPLAY + ' barang teratas dari ' + matched + ' hasil yang cocok. ' +
            'Ketik nama atau nomor rekening di kotak pencarian untuk mempersempit.</td></tr>';
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
    .replace(/'/g, "&#039;");
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
<?php
$extraScript = ob_get_clean();

render_page('Penghapusan Aset (BA-Afkir) · IT Operations Bank Mitra', $body, $head, $extraScript);
