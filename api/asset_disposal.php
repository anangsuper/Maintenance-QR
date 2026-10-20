<?php
/**
 * Halaman Pengelolaan Penghapusan & Penjualan Aset Inventaris
 * PT BPR Mitratama Arthabuana
 * Khusus Bagian SDM & Umum, IT Operations, dan Manajemen
 */

require __DIR__ . '/bootstrap.php';
require_login();

if (!is_admin() && !is_teknisi() && !is_hrd()) {
    http_response_code(403);
    die('Akses ditolak. Anda tidak memiliki hak akses untuk modul penghapusan aset.');
}

// 1. Ekspor ke Format Excel (CSV UTF-8 BOM)
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $exportFilters = [
        'search' => $_GET['search'] ?? '',
        'jenis_tindakan' => $_GET['jenis_tindakan'] ?? '',
        'cabang' => $_GET['cabang'] ?? '',
        'tahun' => $_GET['tahun'] ?? '',
    ];
    $exportRows = get_disposal_list($exportFilters);

    $filename = 'Rekap_Penghapusan_Penjualan_Aset_Bank_Mitra_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // Write UTF-8 BOM for Microsoft Excel compatibility
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($out, [
        'No', 'Nomor Berita Acara', 'Tanggal', 'Jenis Tindakan', 'Tipe Aset',
        'Kode Aset / No. Rek', 'Nama Barang', 'Merk / Model', 'Serial Number',
        'Kantor Cabang', 'Ruangan', 'Pengguna Terakhir', 'Kondisi Terakhir',
        'Nilai Penjualan (Rp)', 'Pembeli / Penerima', 'Metode Bayar',
        'Alasan Penghapusan', 'Petugas Pemeriksa', 'Mengetahui (Kabag SDM)',
        'Menyetujui (Direktur Ops)'
    ]);

    $no = 1;
    foreach ($exportRows as $r) {
        fputcsv($out, [
            $no++,
            $r['nomor_ba'],
            $r['tanggal_disposal'],
            $r['jenis_tindakan'],
            ($r['asset_type'] === 'inventaris' ? 'Kartu Inventaris SDM' : 'Perangkat IT'),
            $r['kode_aset'],
            $r['nama_aset'],
            $r['merk_model'],
            $r['serial_number'],
            $r['cabang'],
            $r['lokasi_ruangan'],
            $r['pengguna_terakhir'],
            $r['kondisi_terakhir'],
            $r['nilai_realisasi_penjualan'],
            $r['pembeli_penerima'],
            $r['metode_pembayaran'],
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

    if ($action === 'delete') {
        if (!is_admin() && !is_hrd()) {
            $_SESSION['flash_error'] = 'Hanya Administrator atau Kabag SDM & Umum yang berhak membatalkan arsip Berita Acara.';
        } else {
            $delId = (int)($_POST['id'] ?? 0);
            if (delete_asset_disposal($delId, true)) {
                $_SESSION['flash'] = 'Berita Acara berhasil dibatalkan dan status fisik aset telah dipulihkan ke status Aktif.';
            } else {
                $_SESSION['flash_error'] = 'Gagal memproses pembatalan Berita Acara.';
            }
        }
        header('Location: ' . module_url('asset_disposal.php'));
        exit;
    }

    if ($action === 'upload_acc') {
        $idAcc = (int)($_POST['id'] ?? 0);
        $fileUploaded = upload_disposal_file('dokumen_ba_disetujui', 'disposal_ba_acc');
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
    'jenis_tindakan' => trim((string)($_GET['jenis_tindakan'] ?? 'all')),
    'cabang' => trim((string)($_GET['cabang'] ?? 'all')),
    'tahun' => trim((string)($_GET['tahun'] ?? date('Y'))),
];

$disposalList = get_disposal_list($filters);
$kpi = get_disposal_kpi_summary();

// 4. Ambil Daftar Aset Aktif untuk Modal Dropdown (Non-Komputer & IT)
$activeInventaris = [];
try {
    $invRows = get_inventaris_kartu_rows();
    foreach ($invRows as $ir) {
        $st = (string)($ir['status_inventaris'] ?? '');
        if (str_contains($st, 'Dihapuskan') || str_contains($st, 'Dijual')) continue;
        $activeInventaris[] = [
            'id' => (int)$ir['id'],
            'type' => 'inventaris',
            'kode' => (string)$ir['nomor_rekening'],
            'nama' => (string)$ir['nama_barang'],
            'cabang' => (string)($ir['lokasi'] ?: 'Kantor Pusat (KPO)'),
            'ruangan' => (string)($ir['ruangan'] ?? ''),
            'pengguna' => (string)($ir['pengguna'] ?? 'Umum / Pool'),
            'sn' => (string)($ir['nomor_seri_inventaris'] ?? ''),
            'merk' => (string)($ir['golongan'] ?? 'Perabot / Fasilitas'),
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
            'kode' => (string)$cr['kode_inventaris'],
            'nama' => trim(($cr['merk'] ?? '') . ' ' . ($cr['model'] ?? '')),
            'cabang' => (string)($cr['nama_cabang'] ?: 'Kantor Pusat (KPO)'),
            'ruangan' => (string)($cr['ruangan'] ?? ''),
            'pengguna' => (string)($cr['nama_karyawan'] ?: 'Staff IT / Operasional'),
            'sn' => (string)($cr['serial_number'] ?? ''),
            'merk' => (string)($cr['merk'] ?? ''),
        ];
    }
} catch (Throwable $e) {}

$flashSuccess = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

// 5. Render Halaman
$body = '
<div class="container-fluid py-4 px-md-4">

  <!-- Flash Messages -->
  '.($flashSuccess ? '
  <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm border-0" role="alert">
    <i class="bi bi-check-circle-fill fs-5"></i>
    <div>'.e($flashSuccess).'</div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>' : '').'

  '.($flashError ? '
  <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm border-0" role="alert">
    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
    <div>'.e($flashError).'</div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>' : '').'

  <!-- HERO HEADER BANNER -->
  <div class="hero-gradient-header hero-gradient-red mb-4 text-white p-4 rounded-4 shadow-sm" style="background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%); border-left: 5px solid #EF4444;">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
          <span class="badge bg-danger text-white px-2.5 py-1 font-monospace fw-bold" style="font-size: 0.72rem; letter-spacing: 0.05em; border-radius: 6px;">PORTAL SDM &amp; UMUM</span>
          <span class="text-white text-opacity-75 small">Kepatuhan Aset &amp; Akuntansi Perbankan</span>
        </div>
        <h2 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
          <i class="bi bi-trash3-fill text-danger"></i> Penghapusan &amp; Penjualan Aset Inventaris
        </h2>
        <p class="text-white text-opacity-75 mb-0" style="max-width: 750px; font-size: 0.92rem;">
          Pencatatan legal berita acara pemusnahan (afkir fisik), penjualan/lelang barang bekas, dan hibah inventaris perabot kantor maupun perangkat IT Bank Mitra sesuai standar audit OJK &amp; SKAI.
        </p>
      </div>
      <div class="d-flex flex-wrap align-items-center gap-2 flex-shrink-0">
        <button type="button" class="btn btn-danger fw-bold shadow-sm d-flex align-items-center gap-1.5 px-3 py-2" data-bs-toggle="modal" data-bs-target="#modalDisposal" onclick="resetDisposalModal()">
          <i class="bi bi-plus-circle-fill"></i> Catat Berita Acara
        </button>
        <a href="'.e(module_url('asset_disposal.php', array_merge($_GET, ['export' => 'excel']))).'" class="btn btn-outline-light fw-semibold shadow-sm d-flex align-items-center gap-1.5 px-3 py-2">
          <i class="bi bi-file-earmark-spreadsheet"></i> Export Excel
        </a>
      </div>
    </div>
  </div>

  <!-- TOP KPI METRIC CARDS -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100 border-0 shadow-sm rounded-3 p-3 bg-white">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold">Total Aset Diproses</div>
            <div class="fs-3 fw-bold text-dark mt-1">'.number_format($kpi['total_unit']).' <span class="fs-6 fw-normal text-muted">Unit</span></div>
          </div>
          <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle">
            <i class="bi bi-boxes fs-4"></i>
          </div>
        </div>
        <div class="text-muted small mt-2" style="font-size: 0.74rem;">Seluruh status penghapusan</div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="card h-100 border-0 shadow-sm rounded-3 p-3 bg-white">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold">Pemusnahan / Afkir</div>
            <div class="fs-3 fw-bold text-danger mt-1">'.number_format($kpi['total_afkir']).' <span class="fs-6 fw-normal text-muted">Unit</span></div>
          </div>
          <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-circle">
            <i class="bi bi-trash3 fs-4"></i>
          </div>
        </div>
        <div class="text-muted small mt-2" style="font-size: 0.74rem;">Rusak berat / tidak bernilai jual</div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="card h-100 border-0 shadow-sm rounded-3 p-3 bg-white">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold">Penjualan / Lelang</div>
            <div class="fs-3 fw-bold text-success mt-1">'.number_format($kpi['total_penjualan']).' <span class="fs-6 fw-normal text-muted">Unit</span></div>
          </div>
          <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle">
            <i class="bi bi-tag-fill fs-4"></i>
          </div>
        </div>
        <div class="text-muted small mt-2" style="font-size: 0.74rem;">Dijual ke pihak ketiga / karyawan</div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="card h-100 border-0 shadow-sm rounded-3 p-3 bg-white" style="border-bottom: 3px solid #10B981 !important;">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold">Kas Masuk Hasil Jual</div>
            <div class="fs-4 fw-bold text-success mt-1">Rp. '.number_format($kpi['total_pendapatan_penjualan'], 0, ',', '.').'</div>
          </div>
          <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle">
            <i class="bi bi-cash-stack fs-4"></i>
          </div>
        </div>
        <div class="text-muted small mt-2" style="font-size: 0.74rem;">Pendapatan non-operasional kas bank</div>
      </div>
    </div>
  </div>

  <!-- FILTER & SEARCH TOOLBAR -->
  <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
    <div class="card-body p-3">
      <form method="GET" action="'.e(module_url('asset_disposal.php')).'" class="row g-2 align-items-center">
        <div class="col-md-4 col-12">
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control bg-light border-start-0" placeholder="Cari No. BA, Nama Aset, No. Rek, Pembeli..." value="'.e($filters['search']).'">
          </div>
        </div>
        <div class="col-md-3 col-6">
          <select name="jenis_tindakan" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="all" '.($filters['jenis_tindakan']==='all'?'selected':'').'>Semua Jenis Tindakan</option>
            <option value="Pemusnahan / Afkir Fisik" '.($filters['jenis_tindakan']==='Pemusnahan / Afkir Fisik'?'selected':'').'>Pemusnahan / Afkir Fisik</option>
            <option value="Penjualan / Lelang Barang Bekas" '.($filters['jenis_tindakan']==='Penjualan / Lelang Barang Bekas'?'selected':'').'>Penjualan / Lelang Barang Bekas</option>
            <option value="Hibah / Donasi" '.($filters['jenis_tindakan']==='Hibah / Donasi'?'selected':'').'>Hibah / Donasi</option>
            <option value="Tukar Tambah" '.($filters['jenis_tindakan']==='Tukar Tambah'?'selected':'').'>Tukar Tambah Unit Baru</option>
          </select>
        </div>
        <div class="col-md-3 col-6">
          <select name="cabang" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="all" '.($filters['cabang']==='all'?'selected':'').'>Semua Kantor Cabang</option>';
            foreach ($cabangMaster as $cb) {
                $cName = $cb['nama_cabang'] ?? '';
                if ($cName === '') continue;
                $sel = ($filters['cabang'] === $cName) ? 'selected' : '';
                $body .= '<option value="'.e($cName).'" '.$sel.'>'.e($cName).'</option>';
            }
$body .= '
          </select>
        </div>
        <div class="col-md-2 col-12 d-flex gap-1">
          <button type="submit" class="btn btn-sm btn-dark w-100 fw-semibold">
            <i class="bi bi-funnel"></i> Filter
          </button>
          '.(!empty($filters['search']) || $filters['jenis_tindakan']!=='all' || $filters['cabang']!=='all' ? '
          <a href="'.e(module_url('asset_disposal.php')).'" class="btn btn-sm btn-outline-secondary" title="Reset Filter">
            <i class="bi bi-arrow-counterclockwise"></i>
          </a>' : '').'
        </div>
      </form>
    </div>
  </div>

  <!-- DATA TABLE ARSIP BERITA ACARA -->
  <div class="card border-0 shadow-sm rounded-3 bg-white overflow-hidden">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
      <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
        <i class="bi bi-archive-fill text-danger"></i> Daftar Berita Acara Penghapusan &amp; Penjualan
      </h6>
      <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.72rem;">Total: '.count($disposalList).' Dokumen</span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
        <thead class="table-light text-secondary text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">
          <tr>
            <th class="ps-3" style="width: 17%;">No. BA &amp; Tanggal</th>
            <th style="width: 25%;">Identitas Aset</th>
            <th style="width: 14%;">Lokasi / Cabang</th>
            <th style="width: 16%;">Tindakan &amp; Nilai</th>
            <th style="width: 14%;">Dokumentasi</th>
            <th class="text-end pe-3" style="width: 14%;">Aksi</th>
          </tr>
        </thead>
        <tbody>';

        if (empty($disposalList)) {
            $body .= '
            <tr>
              <td colspan="6" class="text-center py-5 text-muted">
                <i class="bi bi-clipboard-x fs-1 text-secondary opacity-50 d-block mb-2"></i>
                <div class="fw-semibold">Belum Ada Catatan Berita Acara Penghapusan / Penjualan</div>
                <p class="small text-muted mb-3">Klik tombol "Catat Berita Acara" di atas untuk memproses afkir fisik atau penjualan aset inventaris.</p>
                <button type="button" class="btn btn-sm btn-danger fw-bold px-3 py-1.5" data-bs-toggle="modal" data-bs-target="#modalDisposal" onclick="resetDisposalModal()">
                  <i class="bi bi-plus-circle me-1"></i> Buat Berita Acara Pertama
                </button>
              </td>
            </tr>';
        } else {
            foreach ($disposalList as $row) {
                $isSell = str_contains($row['jenis_tindakan'], 'Penjualan') || str_contains($row['jenis_tindakan'], 'Lelang');
                $isScrap = str_contains($row['jenis_tindakan'], 'Afkir') || str_contains($row['jenis_tindakan'], 'Pemusnahan');
                $isDonate = str_contains($row['jenis_tindakan'], 'Hibah') || str_contains($row['jenis_tindakan'], 'Donasi');

                $badgeClass = $isSell ? 'bg-success text-white' : ($isScrap ? 'bg-danger text-white' : 'bg-primary text-white');
                $badgeIcon = $isSell ? 'bi-cash-coin' : ($isScrap ? 'bi-trash3' : 'bi-gift');

                $assetBadge = ($row['asset_type'] === 'inventaris') 
                    ? '<span class="badge bg-warning text-dark border border-warning" style="font-size: 0.65rem;"><i class="bi bi-credit-card-2-front"></i> Kartu SDM</span>' 
                    : '<span class="badge bg-info text-dark border border-info" style="font-size: 0.65rem;"><i class="bi bi-pc-display"></i> Perangkat IT</span>';

                $body .= '
                <tr>
                  <td class="ps-3">
                    <div class="font-monospace fw-bold text-dark text-break" style="font-size: 0.82rem;">'.e($row['nomor_ba']).'</div>
                    <div class="text-muted small mt-0.5"><i class="bi bi-calendar3 me-1"></i>'.date('d/m/Y', strtotime($row['tanggal_disposal'])).'</div>
                    <div class="text-secondary small mt-0.5" style="font-size: 0.72rem;">Petugas: '.e($row['petugas_nama']).'</div>
                  </td>
                  <td>
                    <div class="d-flex align-items-center gap-1.5 mb-1">
                      '.$assetBadge.'
                      <span class="font-monospace text-muted small">['.e($row['kode_aset']).']</span>
                    </div>
                    <div class="fw-bold text-dark">'.e($row['nama_aset']).'</div>
                    '.(!empty($row['merk_model']) && $row['merk_model'] !== '-' ? '<div class="text-muted small">'.e($row['merk_model']).'</div>' : '').'
                    <div class="text-danger small mt-1" style="font-size: 0.74rem;">
                      <i class="bi bi-exclamation-circle me-0.5"></i> Kondisi: '.e($row['kondisi_terakhir']).'
                    </div>
                  </td>
                  <td>
                    <div class="fw-semibold text-dark">'.e($row['cabang']).'</div>
                    <div class="text-muted small">Ruang: '.e($row['lokasi_ruangan'] ?: '-').'</div>
                    <div class="text-secondary small" style="font-size: 0.72rem;">PJ: '.e($row['pengguna_terakhir'] ?: '-').'</div>
                  </td>
                  <td>
                    <span class="badge '.$badgeClass.' fw-bold d-inline-flex align-items-center gap-1 px-2 py-1 mb-1" style="font-size: 0.72rem; border-radius: 4px;">
                      <i class="bi '.$badgeIcon.'"></i> '.e($row['jenis_tindakan']).'
                    </span>';
                    if ($isSell && $row['nilai_realisasi_penjualan'] > 0) {
                        $body .= '
                        <div class="fw-bold text-success" style="font-size: 0.90rem;">Rp. '.number_format($row['nilai_realisasi_penjualan'], 0, ',', '.').'</div>
                        <div class="text-muted small" style="font-size: 0.72rem;">Pembeli: '.e($row['pembeli_penerima'] ?: '-').'</div>';
                    } elseif ($isDonate && !empty($row['pembeli_penerima'])) {
                        $body .= '<div class="text-muted small" style="font-size: 0.72rem;">Penerima: '.e($row['pembeli_penerima']).'</div>';
                    }
                    $body .= '
                    <div class="text-secondary small mt-1 text-truncate" style="max-width: 220px;" title="'.e($row['alasan_penghapusan']).'">
                      '.e($row['alasan_penghapusan']).'
                    </div>
                  </td>
                  <td>
                    <div class="d-flex flex-column gap-1">';
                      if (!empty($row['foto_barang'])) {
                          $body .= '<a href="'.e(disposal_file_url($row['foto_barang'])).'" target="_blank" class="btn btn-xs btn-outline-secondary text-start py-0.5 px-2" style="font-size: 0.72rem;">
                            <i class="bi bi-camera me-1 text-primary"></i> Foto Fisik
                          </a>';
                      }
                      if (!empty($row['bukti_transaksi'])) {
                          $body .= '<a href="'.e(disposal_file_url($row['bukti_transaksi'])).'" target="_blank" class="btn btn-xs btn-outline-success text-start py-0.5 px-2" style="font-size: 0.72rem;">
                            <i class="bi bi-receipt me-1 text-success"></i> Kwitansi Bayar
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
                  <td class="text-end pe-3">
                    <div class="btn-group btn-group-sm">
                      <a href="'.e(module_url('print_bast_disposal.php', ['id' => $row['id']])).'" target="_blank" class="btn btn-primary fw-semibold" title="Cetak Berita Acara">
                        <i class="bi bi-printer-fill me-1"></i> Cetak
                      </a>
                      <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="visually-hidden">Toggle Dropdown</span>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li>
                          <a class="dropdown-item" href="'.e(module_url('print_bast_disposal.php', ['id' => $row['id']])).'" target="_blank">
                            <i class="bi bi-file-earmark-pdf me-2 text-primary"></i> Cetak Dokumen BA
                          </a>
                        </li>
                        <li>
                          <button class="dropdown-item" type="button" onclick="openUploadAccModal('.$row['id'].', \''.e(addslashes($row['nomor_ba'])).'\')">
                            <i class="bi bi-cloud-arrow-up me-2 text-info"></i> Upload Scan BA Disetujui
                          </button>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                          <form method="POST" action="'.e(module_url('asset_disposal.php')).'" onsubmit="return confirm(\'PERINGATAN: Membatalkan Berita Acara ini akan mengembalikan status fisik aset menjadi AKTIF di sistem register.\\n\\nApakah Anda yakin ingin membatalkan dokumen ini?\')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="'.$row['id'].'">
                            <button type="submit" class="dropdown-item text-danger">
                              <i class="bi bi-arrow-counterclockwise me-2"></i> Batalkan &amp; Pulihkan Aset
                            </button>
                          </form>
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

<!-- ========================================== -->
<!-- MODAL CATAT BERITA ACARA PENGHAPUSAN ASET -->
<!-- ========================================== -->
<div class="modal fade" id="modalDisposal" tabindex="-1" aria-labelledby="modalDisposalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg">
      <form method="POST" action="'.e(module_url('asset_disposal.php')).'" enctype="multipart/form-data" id="formDisposal">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="dispId" value="0">
        
        <div class="modal-header bg-dark text-white border-0 py-3">
          <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="modalDisposalLabel">
            <i class="bi bi-trash3-fill text-danger"></i> Catat Berita Acara Penghapusan / Penjualan Aset
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
            <label class="form-label fw-bold text-dark small" for="selectAssetItem">PILIH UNIT BARANG INVENTARIS <span class="text-danger">*</span></label>
            <select class="form-select" id="selectAssetItem" required onchange="onAssetSelected(this)">
              <option value="">-- Pilih Barang dari Register Aktif --</option>
            </select>
            <div class="form-text small">Hanya menampilkan barang inventaris yang saat ini berstatus aktif di bank.</div>
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
              <div class="form-text small" style="font-size: 0.72rem;">Kosongkan jika ingin digenerate otomatis standar format Bank Mitra.</div>
            </div>
            <div class="col-md-5 col-12">
              <label class="form-label fw-bold text-dark small" for="dispTanggal">TANGGAL PEMERIKSAAN / EKSEKUSI <span class="text-danger">*</span></label>
              <input type="date" name="tanggal_disposal" id="dispTanggal" class="form-control" value="'.date('Y-m-d').'" required>
            </div>
          </div>

          <!-- 4. Jenis Tindakan Keputusan -->
          <div class="mb-3">
            <label class="form-label fw-bold text-dark small">JENIS TINDAKAN KEPUTUSAN <span class="text-danger">*</span></label>
            <div class="row g-2">
              <div class="col-md-6 col-12">
                <div class="form-check border rounded-3 p-2 bg-white h-100">
                  <input class="form-check-input ms-0 me-2" type="radio" name="jenis_tindakan" id="actAfkir" value="Pemusnahan / Afkir Fisik" checked onchange="toggleActionFields()">
                  <label class="form-check-label fw-semibold text-danger" for="actAfkir" style="cursor: pointer;">
                    <i class="bi bi-trash3-fill me-1"></i> Pemusnahan / Afkir Fisik (Scrap)
                    <div class="text-muted fw-normal small" style="font-size: 0.72rem;">Barang rusak total, tidak ada nilai jual, dihancurkan/dibuang.</div>
                  </label>
                </div>
              </div>
              <div class="col-md-6 col-12">
                <div class="form-check border rounded-3 p-2 bg-white h-100">
                  <input class="form-check-input ms-0 me-2" type="radio" name="jenis_tindakan" id="actJual" value="Penjualan / Lelang Barang Bekas" onchange="toggleActionFields()">
                  <label class="form-check-label fw-semibold text-success" for="actJual" style="cursor: pointer;">
                    <i class="bi bi-cash-stack me-1"></i> Penjualan / Lelang Barang Bekas
                    <div class="text-muted fw-normal small" style="font-size: 0.72rem;">Dijual ke pihak ketiga/pengepul atau lelang karyawan (menghasilkan kas).</div>
                  </label>
                </div>
              </div>
              <div class="col-md-6 col-12">
                <div class="form-check border rounded-3 p-2 bg-white h-100">
                  <input class="form-check-input ms-0 me-2" type="radio" name="jenis_tindakan" id="actHibah" value="Hibah / Donasi" onchange="toggleActionFields()">
                  <label class="form-check-label fw-semibold text-primary" for="actHibah" style="cursor: pointer;">
                    <i class="bi bi-gift-fill me-1"></i> Hibah / Donasi Sosial
                    <div class="text-muted fw-normal small" style="font-size: 0.72rem;">Diserahkan ke yayasan, sekolah, atau pihak lain.</div>
                  </label>
                </div>
              </div>
              <div class="col-md-6 col-12">
                <div class="form-check border rounded-3 p-2 bg-white h-100">
                  <input class="form-check-input ms-0 me-2" type="radio" name="jenis_tindakan" id="actTukar" value="Tukar Tambah" onchange="toggleActionFields()">
                  <label class="form-check-label fw-semibold text-dark" for="actTukar" style="cursor: pointer;">
                    <i class="bi bi-arrow-repeat me-1"></i> Tukar Tambah Unit Baru
                    <div class="text-muted fw-normal small" style="font-size: 0.72rem;">Digunakan sebagai pengurang biaya unit pengadaan baru.</div>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Section Khusus Penjualan (Tampil jika pilih Penjualan) -->
          <div class="card bg-success bg-opacity-10 border-success rounded-3 p-3 mb-3 d-none" id="boxPenjualan">
            <h6 class="fw-bold text-success mb-2 d-flex align-items-center gap-1.5" style="font-size: 0.86rem;">
              <i class="bi bi-coin"></i> RINCIAN PENJUALAN / LELANG ASET
            </h6>
            <div class="row g-2">
              <div class="col-md-6 col-12">
                <label class="form-label small fw-semibold text-dark" for="dispNilaiJual">NILAI REALISASI PENJUALAN (RP) <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-white fw-bold">Rp</span>
                  <input type="number" name="nilai_realisasi_penjualan" id="dispNilaiJual" class="form-control" placeholder="0" min="0" step="1000">
                </div>
              </div>
              <div class="col-md-6 col-12">
                <label class="form-label small fw-semibold text-dark" for="dispPembeli">NAMA PEMBELI / PEMENANG LELANG <span class="text-danger">*</span></label>
                <input type="text" name="pembeli_penerima" id="dispPembeli" class="form-control form-control-sm" placeholder="Contoh: CV Surya Perkasa / Anang (Karyawan)">
              </div>
              <div class="col-md-6 col-12 mt-2">
                <label class="form-label small fw-semibold text-dark" for="dispMetodeBayar">METODE PEMBAYARAN KAS</label>
                <select name="metode_pembayaran" id="dispMetodeBayar" class="form-select form-select-sm">
                  <option value="Tunai / Kas Masuk Operasional">Tunai / Kas Masuk Operasional</option>
                  <option value="Transfer Rekening Operasional Bank Mitra">Transfer Rekening Operasional Bank Mitra</option>
                  <option value="Potong Gaji Pegawai (Lelang Karyawan)">Potong Gaji Pegawai (Lelang Karyawan)</option>
                </select>
              </div>
              <div class="col-md-6 col-12 mt-2">
                <label class="form-label small fw-semibold text-dark" for="dispBukti">UPLOAD STRUK / BUKTI BAYAR (OPSIONAL)</label>
                <input type="file" name="bukti_transaksi" id="dispBukti" class="form-control form-control-sm" accept="image/*,.pdf">
              </div>
            </div>
          </div>

          <!-- Section Khusus Hibah (Tampil jika pilih Hibah) -->
          <div class="card bg-primary bg-opacity-10 border-primary rounded-3 p-3 mb-3 d-none" id="boxHibah">
            <h6 class="fw-bold text-primary mb-2 d-flex align-items-center gap-1.5" style="font-size: 0.86rem;">
              <i class="bi bi-gift"></i> RINCIAN PENERIMA HIBAH / DONASI
            </h6>
            <div>
              <label class="form-label small fw-semibold text-dark" for="dispHibahPenerima">NAMA YAYASAN / SEKOLAH / PIHAK PENERIMA</label>
              <input type="text" name="pembeli_penerima_hibah" id="dispHibahPenerima" class="form-control form-control-sm" placeholder="Contoh: Panti Asuhan Harapan Ibu / SMK Mitra Mandiri">
            </div>
          </div>

          <!-- 5. Kondisi Fisik & Alasan Penghapusan -->
          <div class="mb-3">
            <label class="form-label fw-bold text-dark small" for="dispKondisi">KONDISI FISIK TERAKHIR <span class="text-danger">*</span></label>
            <select name="kondisi_terakhir" id="dispKondisi" class="form-select form-select-sm" required>
              <option value="Rusak Berat / Tidak Ekonomis Diperbaiki">Rusak Berat / Tidak Ekonomis Diperbaiki</option>
              <option value="Mati Total / Tidak Dapat Berfungsi">Mati Total / Tidak Dapat Berfungsi</option>
              <option value="Rangka Fisik Patah / Lapuk / Keropos">Rangka Fisik Patah / Lapuk / Keropos</option>
              <option value="Usang / Masa Manfaat Habis">Usang / Masa Manfaat Habis</option>
              <option value="Masih Berfungsi (Layak Lelang / Donasi)">Masih Berfungsi (Layak Lelang / Donasi)</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold text-dark small" for="dispAlasan">ALASAN &amp; PERTIMBANGAN TEKNIS PENGHAPUSAN <span class="text-danger">*</span></label>
            <div class="d-flex flex-wrap gap-1 mb-2">
              <button type="button" class="btn btn-xs btn-outline-secondary" onclick="appendAlasan(\'Biaya servis melebihi nilai beli unit baru.\')">+ Biaya Servis Mahal</button>
              <button type="button" class="btn btn-xs btn-outline-secondary" onclick="appendAlasan(\'Rangka fisik patah dan lapuk sehingga membahayakan keselamatan kerja.\')">+ Rangka Patah</button>
              <button type="button" class="btn btn-xs btn-outline-secondary" onclick="appendAlasan(\'Komponen elektronik terbakar dan suku cadang sudah tidak diproduksi.\')">+ Sparepart Tidak Ada</button>
              <button type="button" class="btn btn-xs btn-outline-secondary" onclick="appendAlasan(\'Peremajaan fasilitas kerja kantor cabang baru.\')">+ Peremajaan Fasilitas</button>
            </div>
            <textarea name="alasan_penghapusan" id="dispAlasan" class="form-control form-control-sm" rows="3" required placeholder="Jelaskan secara rinci alasan teknis mengapa barang ini dihapus atau dijual..."></textarea>
          </div>

          <!-- 6. Upload Foto Fisik Barang Terakhir -->
          <div class="mb-3">
            <label class="form-label fw-bold text-dark small" for="dispFoto">UPLOAD DOKUMENTASI FOTO FISIK ASET TERAKHIR</label>
            <input type="file" name="foto_barang" id="dispFoto" class="form-control form-control-sm" accept="image/*">
            <div class="form-text small" style="font-size: 0.72rem;">Lampirkan foto kondisi fisik barang sebelum dimusnahkan atau diserahkan ke pembeli.</div>
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
            <i class="bi bi-check-circle-fill me-1"></i> Simpan &amp; Proses Berita Acara
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- MODAL UPLOAD SCAN BERITA ACARA DISETUJUI -->
<!-- ========================================== -->
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
            Unggah dokumen scan Berita Acara resmi yang telah ditandatangani dan distempel basah untuk nomor:
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

<!-- DATA ASSETS FOR JAVASCRIPT -->
<script>
var gInventaris = '.json_encode($activeInventaris).';
var gKomputer = '.json_encode($activeComputers).';

function switchAssetType(type) {
  var select = document.getElementById("selectAssetItem");
  select.innerHTML = \'<option value="">-- Pilih Barang dari Register Aktif --</option>\';
  
  var list = (type === "komputer") ? gKomputer : gInventaris;
  list.forEach(function(item) {
    var opt = document.createElement("option");
    opt.value = item.id;
    opt.text = "[" + item.kode + "] " + item.nama + " (" + item.cabang + ")";
    opt.dataset.json = JSON.stringify(item);
    select.appendChild(opt);
  });

  document.getElementById("cardAsetDetail").classList.add("d-none");
  resetAssetHiddenFields();
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

function toggleActionFields() {
  var isJual = document.getElementById("actJual").checked;
  var isHibah = document.getElementById("actHibah").checked;

  var boxJual = document.getElementById("boxPenjualan");
  var boxHibah = document.getElementById("boxHibah");
  var inputNilaiJual = document.getElementById("dispNilaiJual");
  var inputPembeli = document.getElementById("dispPembeli");

  if (isJual) {
    boxJual.classList.remove("d-none");
    inputNilaiJual.required = true;
    inputPembeli.required = true;
  } else {
    boxJual.classList.add("d-none");
    inputNilaiJual.required = false;
    inputPembeli.required = false;
    inputNilaiJual.value = "";
  }

  if (isHibah) {
    boxHibah.classList.remove("d-none");
  } else {
    boxHibah.classList.add("d-none");
  }
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
  document.getElementById("actAfkir").checked = true;
  toggleActionFields();
  document.getElementById("dispAlasan").value = "";
  document.getElementById("cardAsetDetail").classList.add("d-none");
}

function openUploadAccModal(id, noBa) {
  document.getElementById("uploadAccId").value = id;
  document.getElementById("lblAccNomorBa").textContent = noBa;
  var modal = new bootstrap.Modal(document.getElementById("modalUploadAcc"));
  modal.show();
}

// Inisialisasi awal dropdown saat modal siap
document.addEventListener("DOMContentLoaded", function() {
  switchAssetType("inventaris");
});
</script>
';

echo render_view('Penghapusan & Penjualan Aset Inventaris', $body);
