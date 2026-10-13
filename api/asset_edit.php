<?php
require __DIR__ . '/bootstrap.php';
require_login();

$id = max(0, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
if ($id <= 0) {
    http_response_code(400);
    render_page('Parameter Tidak Valid', '<div class="alert alert-danger">ID aset tidak valid.</div>');
    exit;
}

$asset = get_asset_by_id($id);
if (!$asset) {
    http_response_code(404);
    render_page('Aset Tidak Ditemukan', '
    <div class="alert alert-warning">
      <h4><i class="bi bi-exclamation-triangle-fill me-2"></i>Aset Tidak Ditemukan</h4>
      <p>Data aset dengan ID <strong>#'.$id.'</strong> tidak ditemukan atau sudah dihapus.</p>
      <a class="btn btn-primary" href="'.e(module_url('qr_admin.php')).'">← Kembali ke Daftar QR Aset</a>
    </div>');
    exit;
}

$cabangs = get_cabang_list();
$divisis = get_divisi_list();
$kategoris = get_kategori_list();
$karyawans = get_karyawan_list();

// Fallback master data jika kosong
if (empty($kategoris)) {
    $kategoris = [
        ['id' => 1, 'nama_kategori' => 'Laptop', 'nama' => 'Laptop'],
        ['id' => 2, 'nama_kategori' => 'PC Desktop', 'nama' => 'PC Desktop'],
        ['id' => 3, 'nama_kategori' => 'All-in-One PC', 'nama' => 'All-in-One PC'],
        ['id' => 4, 'nama_kategori' => 'Server', 'nama' => 'Server'],
        ['id' => 5, 'nama_kategori' => 'Mini PC', 'nama' => 'Mini PC'],
        ['id' => 6, 'nama_kategori' => 'Printer', 'nama' => 'Printer'],
        ['id' => 7, 'nama_kategori' => 'Monitor', 'nama' => 'Monitor'],
    ];
}

if (empty($cabangs)) {
    $cabangs = [
        ['id' => 1, 'nama_cabang' => 'Head Office', 'nama' => 'Head Office'],
        ['id' => 2, 'nama_cabang' => 'Cabang Utama', 'nama' => 'Cabang Utama'],
    ];
}

if (empty($divisis)) {
    $divisis = [
        ['id' => 1, 'nama_divisi' => 'IT / MIS', 'nama' => 'IT / MIS'],
        ['id' => 2, 'nama_divisi' => 'Operasional', 'nama' => 'Operasional'],
        ['id' => 3, 'nama_divisi' => 'Finance & Accounting', 'nama' => 'Finance & Accounting'],
        ['id' => 4, 'nama_divisi' => 'SDM / HRD', 'nama' => 'SDM / HRD'],
        ['id' => 5, 'nama_divisi' => 'Marketing / Sales', 'nama' => 'Marketing / Sales'],
        ['id' => 6, 'nama_divisi' => 'Audit / SKAI', 'nama' => 'Audit / SKAI'],
        ['id' => 7, 'nama_divisi' => 'Kepatuhan & Manajemen Risiko', 'nama' => 'Kepatuhan & Manajemen Risiko'],
        ['id' => 8, 'nama_divisi' => 'Umum / GA', 'nama' => 'Umum / GA'],
        ['id' => 9, 'nama_divisi' => 'Customer Service / Pelayanan', 'nama' => 'Customer Service / Pelayanan'],
        ['id' => 10, 'nama_divisi' => 'Kasir / Teller', 'nama' => 'Kasir / Teller'],
    ];
}

$error = '';
$successData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $kode = normalize_kode_inventaris(trim((string)($_POST['kode_inventaris'] ?? '')));
    $merk = trim((string)($_POST['merk'] ?? ''));
    $model = trim((string)($_POST['model'] ?? ''));
    $sn = trim((string)($_POST['serial_number'] ?? ''));
    $idKat = (int)($_POST['id_kategori'] ?? 0);
    $idCab = (int)($_POST['id_cabang'] ?? 0);
    $idDiv = (int)($_POST['id_divisi'] ?? 0);

    // Cek jika ada penambahan divisi baru secara langsung
    $namaDivBaru = trim((string)($_POST['nama_divisi_baru'] ?? ''));
    if ($namaDivBaru !== '') {
        $resDiv = create_new_divisi(['nama_divisi' => $namaDivBaru]);
        if (!empty($resDiv['success'])) {
            $idDiv = (int)$resDiv['id'];
        }
    }
    $namaKar = trim((string)($_POST['nama_karyawan'] ?? ''));
    $placement = trim((string)($_POST['placement_label'] ?? 'Bodi Casing'));
    $status = trim((string)($_POST['status'] ?? 'Aktif'));
    $ket = trim((string)($_POST['keterangan'] ?? ''));
    $ip = trim((string)($_POST['ip_address'] ?? ''));
    $printer = trim((string)($_POST['printer'] ?? ''));

    // Master Data Core Banking Bank
    $noSeri = trim((string)($_POST['nomor_seri_inventaris'] ?? ''));
    $jenisInv = trim((string)($_POST['jenis_inventaris'] ?? ''));
    $golongan = trim((string)($_POST['golongan'] ?? ''));
    $ruangan = trim((string)($_POST['ruangan'] ?? ''));
    $sumberPerolehan = trim((string)($_POST['sumber_perolehan'] ?? ''));
    $statusInv = trim((string)($_POST['status_inventaris'] ?? '1 : Aktif'));

    // Cek jika ada penambahan ruangan baru secara langsung
    $ruanganBaru = trim((string)($_POST['nama_ruangan_baru'] ?? ''));
    $kodeRuanganBaru = trim((string)($_POST['kode_ruangan_baru'] ?? ''));
    if ($ruanganBaru !== '') {
        $ruangan = add_master_ruangan($ruanganBaru, $kodeRuanganBaru !== '' ? $kodeRuanganBaru : null);
    }

    if ($merk === '' && $model === '') {
        $error = 'Merk atau Model perangkat wajib diisi.';
    } else {
        $fotoUnit = upload_asset_image('foto_unit');
        if (!$fotoUnit && !empty($_POST['foto_url'])) {
            $fotoUnit = trim((string)$_POST['foto_url']);
        }

        $payload = [
            'kode_inventaris' => $kode,
            'merk' => $merk,
            'model' => $model,
            'serial_number' => $sn,
            'id_kategori' => $idKat,
            'id_cabang' => $idCab,
            'id_divisi' => $idDiv,
            'nama_karyawan' => $namaKar,
            'placement_label' => $placement,
            'status' => $status,
            'keterangan' => $ket,
            'ip_address' => $ip,
            'printer' => $printer,
            'nomor_seri_inventaris' => $noSeri,
            'jenis_inventaris' => $jenisInv,
            'golongan' => $golongan,
            'ruangan' => $ruangan,
            'sumber_perolehan' => $sumberPerolehan,
            'status_inventaris' => $statusInv,
        ];

        // Biometric Face verification status jika disimpan dengan scan wajah
        if (!empty($_POST['face_verified'])) {
            $verifierName = trim((string)($_POST['verifier_name'] ?? ''));
            if ($verifierName === '') {
                $verifierName = current_user_name() ?: 'Teknisi IT';
            }
            $payload['foto_updated_by'] = $verifierName;
            $payload['foto_updated_at'] = date('Y-m-d H:i:s');
        }

        if ($fotoUnit !== null) {
            $payload['foto'] = $fotoUnit;
        } elseif (!empty($_POST['hapus_foto'])) {
            $payload['foto'] = '';
        }

        $res = update_asset($id, $payload);
        if (!empty($res['success'])) {
            $cabangNama = '';
            foreach ($cabangs as $c) {
                if ((int)($c['id'] ?? 0) === $idCab) {
                    $cabangNama = $c['nama_cabang'] ?? $c['nama'] ?? '';
                    break;
                }
            }

            $savedAsset = get_asset_by_id($id);
            $finalQrToken = !empty($savedAsset['qr_token']) ? $savedAsset['qr_token'] : (!empty($asset['qr_token']) ? $asset['qr_token'] : get_static_qr_token($id));

            $successData = [
                'asset_id' => $id,
                'kode_inventaris' => normalize_kode_inventaris($res['kode_inventaris'] ?? $kode),
                'merk' => $merk,
                'model' => $model,
                'qr_token' => $finalQrToken,
                'cabang_nama' => $cabangNama,
                'karyawan_nama' => $namaKar ?: '-',
            ];
            // Update current asset variable for display
            $asset = $savedAsset ?? $asset;
        } else {
            $error = $res['error'] ?? 'Terjadi kesalahan saat memperbarui data aset.';
        }
    }
}

// Tampilan Sukses Setelah Edit
if ($successData) {
    $printUrl = module_url('print_qr.php', ['asset_id' => $successData['asset_id']]);

    $body = '
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="card p-4 border-0 shadow-sm">
          <div class="text-center mb-3">
            <div class="display-5 text-success mb-2"><i class="bi bi-check-circle-fill"></i></div>
            <h3 class="fw-bold text-success">Perubahan Data Berhasil Disimpan!</h3>
            <p class="text-muted mb-0">Informasi spesifikasi dan penempatan perangkat telah diperbarui.</p>
          </div>

          <div class="alert alert-success border-0 shadow-sm d-flex align-items-center gap-3 my-3 py-3 px-3" style="background-color: #F0FDF4; border: 1px solid #BBF7D0 !important;">
            <i class="bi bi-shield-check text-success fs-2 flex-shrink-0"></i>
            <div>
              <div class="fw-bold text-success mb-1">Stiker QR Fisik Tetap Sama & Berlaku Selamanya</div>
              <div class="small text-secondary">
                Token QR (<code>'.e($successData['qr_token']).'</code>) <strong>tidak diganti atau diubah</strong>. Anda <u>tidak perlu mengganti stiker</u> pada casing komputer. Saat stiker fisik di-scan, sistem otomatis menampilkan informasi terbaru yang baru saja Anda simpan.
              </div>
            </div>
          </div>

          <div class="bg-light p-3 rounded-3 mb-4">
            <dl class="row mb-0">
              <dt class="col-sm-4 text-secondary">Kode Inventaris</dt>
              <dd class="col-sm-8 fw-bold text-primary fs-5 mb-2">'.e($successData['kode_inventaris']).'</dd>

              <dt class="col-sm-4 text-secondary">Perangkat</dt>
              <dd class="col-sm-8 fw-semibold mb-2">'.e(trim($successData['merk'].' '.$successData['model'])).'</dd>

              <dt class="col-sm-4 text-secondary">Pengguna / Pemilik</dt>
              <dd class="col-sm-8 mb-2">'.e($successData['karyawan_nama']).'</dd>

              <dt class="col-sm-4 text-secondary">Cabang</dt>
              <dd class="col-sm-8 mb-0">'.e($successData['cabang_nama'] ?: '-').'</dd>
            </dl>
          </div>

          <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
            <a class="btn btn-primary btn-lg px-4 fw-semibold" href="'.e(module_url('assets.php')).'"><i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Aset</a>
            <a class="btn btn-success btn-lg px-4 fw-semibold" target="_blank" href="'.e(module_url('scan.php', ['t' => $successData['qr_token']])).'"><i class="bi bi-qr-code-scan me-1"></i> Buka Tampilan Scan QR</a>
            <a class="btn btn-outline-secondary btn-lg" target="_blank" href="'.e($printUrl).'"><i class="bi bi-printer me-1"></i> Cetak Ulang Label (Opsional)</a>
            <a class="btn btn-light border btn-lg" href="'.e(module_url('asset_edit.php', ['id' => $id])).'"><i class="bi bi-pencil me-1"></i> Edit Lagi</a>
          </div>

          <hr class="my-4">

          <div class="d-flex justify-content-between flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="'.e(module_url('qr_admin.php')).'"><i class="bi bi-qr-code me-1"></i> Ke Daftar QR Aset</a>
            <a class="btn btn-sm btn-outline-primary" href="'.e(module_url('dashboard.php')).'"><i class="bi bi-speedometer2 me-1"></i> Ke Dashboard Maintenance</a>
          </div>
        </div>
      </div>
    </div>';

    render_page('Data Aset Berhasil Diperbarui', $body);
    exit;
}

// Current Values
$curKatId = (int)($asset['id_kategori'] ?? 0);
$curCabId = (int)($asset['id_cabang'] ?? 0);
$curDivId = (int)($asset['id_divisi'] ?? 0);
$curStatus = $asset['status'] ?? 'Aktif';
$curKarName = ($asset['karyawan_nama'] ?? '') !== '-' ? ($asset['karyawan_nama'] ?? '') : '';
$curQrToken = !empty($asset['qr_token']) ? $asset['qr_token'] : get_static_qr_token((int)$asset['id']);

// Dropdown Kategori
$optKat = '';
foreach ($kategoris as $kat) {
    $kId = (int)($kat['id'] ?? 0);
    $kNama = $kat['nama_kategori'] ?? $kat['nama'] ?? 'Kategori #' . $kId;
    $sel = ($kId === $curKatId) ? ' selected' : '';
    $optKat .= '<option value="'.$kId.'"'.$sel.'>'.e($kNama).'</option>';
}

// Dropdown Cabang
$optCab = '';
foreach ($cabangs as $cab) {
    $cId = (int)($cab['id'] ?? 0);
    $cNama = $cab['nama_cabang'] ?? $cab['nama'] ?? 'Cabang #' . $cId;
    $sel = ($cId === $curCabId) ? ' selected' : '';
    $optCab .= '<option value="'.$cId.'"'.$sel.'>'.e($cNama).'</option>';
}

// Dropdown Divisi
$optDiv = '';
foreach ($divisis as $div) {
    $dId = (int)($div['id'] ?? 0);
    $dNama = $div['nama_divisi'] ?? $div['nama'] ?? 'Divisi #' . $dId;
    $sel = ($dId === $curDivId) ? ' selected' : '';
    $optDiv .= '<option value="'.$dId.'"'.$sel.'>'.e($dNama).'</option>';
}

// Datalist Karyawan
$datalistKaryawan = '';
$badgeKaryawan = '';
$uniqueNames = [];
foreach ($karyawans as $kar) {
    $kn = trim((string)($kar['nama_karyawan'] ?? $kar['nama'] ?? ''));
    if ($kn !== '' && !isset($uniqueNames[$kn])) {
        $uniqueNames[$kn] = true;
        $datalistKaryawan .= '<option value="'.e($kn).'">';
        $badgeKaryawan .= '<button type="button" class="btn btn-sm btn-light border me-1 mb-1 py-0 px-2" onclick="setKaryawan(\''.e(addslashes($kn)).'\')">'.e($kn).'</button>';
    }
}

// Master Data Inventaris Core Banking Bank
$curJenis = trim((string)($asset['jenis_inventaris'] ?? ''));
$curGol = trim((string)($asset['golongan'] ?? ''));
$curRuang = trim((string)($asset['ruangan'] ?? ''));
$curSumber = trim((string)($asset['sumber_perolehan'] ?? ''));
$curStatusInv = trim((string)($asset['status_inventaris'] ?? ''));
$curNoSeri = trim((string)($asset['nomor_seri_inventaris'] ?? ''));

$optJenisInv = '';
foreach (get_master_jenis_inventaris() as $k => $v) {
    $sel = (strcasecmp($curJenis, $v) === 0 || strpos($curJenis, $k) === 0) ? 'selected' : '';
    $optJenisInv .= '<option value="'.e($v).'" '.$sel.'>'.e($v).'</option>';
}

$optGolongan = '';
foreach (get_master_golongan_inventaris() as $k => $v) {
    $sel = (strcasecmp($curGol, $v) === 0 || strpos($curGol, $k) === 0) ? 'selected' : '';
    $optGolongan .= '<option value="'.e($v).'" '.$sel.'>'.e($v).'</option>';
}

$optRuangan = '';
$ruanganList = get_master_ruangan_list();
$hasMatchedRuang = false;
foreach ($ruanganList as $r) {
    $val = trim($r['kode_ruangan'] . ' : ' . $r['nama_ruangan']);
    $sel = '';
    if (strcasecmp($curRuang, $val) === 0 || strcasecmp($curRuang, $r['nama_ruangan']) === 0) {
        $sel = 'selected';
        $hasMatchedRuang = true;
    }
    $optRuangan .= '<option value="'.e($val).'" '.$sel.'>'.e($val).'</option>';
}
if (!$hasMatchedRuang && $curRuang !== '') {
    $optRuangan = '<option value="'.e($curRuang).'" selected>'.e($curRuang).'</option>' . $optRuangan;
}

$optSumber = '';
foreach (get_master_sumber_perolehan() as $k => $v) {
    $sel = (strcasecmp($curSumber, $v) === 0 || strpos($curSumber, $k) === 0) ? 'selected' : '';
    $optSumber .= '<option value="'.e($v).'" '.$sel.'>'.e($v).'</option>';
}

$optStatusInv = '';
foreach (get_master_status_inventaris() as $k => $v) {
    $sel = (strcasecmp($curStatusInv, $v) === 0) ? 'selected' : '';
    $optStatusInv .= '<option value="'.e($v).'" '.$sel.'>'.e($v).'</option>';
}

$errorHtml = $error ? '<div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="bi bi-exclamation-triangle-fill me-2"></i>'.e($error).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$modeBadge = is_google_cloud_mode() 
    ? '<span class="badge text-bg-info mb-2"><i class="bi bi-google me-1"></i> Penyimpanan: Google Cloud Sheets API v4</span>' 
    : '<span class="badge text-bg-secondary mb-2"><i class="bi bi-database me-1"></i> Penyimpanan: MySQL Database</span>';

$existingFoto = trim((string)($asset['foto'] ?? ''));
$existingFotoSrc = '';
if ($existingFoto) {
    $existingFotoSrc = str_starts_with($existingFoto, 'http') ? $existingFoto : module_url($existingFoto);
}

$body = '
<div class="row justify-content-center">
  <div class="col-lg-9">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
      <div>
        <div class="tech-label mb-1">ASSET MANAGEMENT</div>
        <h1 class="h4 mb-1">Edit Perangkat #'.(int)$asset['id'].' · '.e(normalize_kode_inventaris($asset['kode_inventaris'] ?? '')).'</h1>
        <div class="text-secondary small">Perbarui konfigurasi spesifikasi, penempatan kantor, atau status operasional perangkat.</div>
      </div>
      <a class="btn btn-light border btn-sm d-inline-flex align-items-center gap-1" href="'.e(module_url('assets.php')).'"><i class="bi bi-arrow-left"></i> Kembali ke Aset</a>
    </div>

    '.$errorHtml.'

    <div class="card p-4 mb-4">
      <form method="post" id="formEditAsset" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="id" value="'.(int)$asset['id'].'">

        <!-- Section 1: Identitas Perangkat -->
        <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
          <span class="tech-label text-primary">01 · IDENTITAS PERANGKAT</span>
        </div>
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label text-secondary small fw-semibold">Kategori Perangkat <span class="text-danger">*</span></label>
            <select class="form-select" name="id_kategori" required>
              '.$optKat.'
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label text-secondary small fw-semibold">Kode Inventaris</label>
            <div class="input-group">
              <input type="text" class="form-control font-monospace" name="kode_inventaris" id="kodeInventaris" value="'.e(normalize_kode_inventaris($asset['kode_inventaris'] ?? '')).'" placeholder="Contoh: 0450007417122025">
              <button class="btn btn-light border" type="button" onclick="autoGenerateKode()" title="Generate Otomatis"><i class="bi bi-magic me-1"></i>Auto</button>
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label text-secondary small fw-semibold">Merk Perangkat <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="merk" list="listMerk" required value="'.e($asset['merk'] ?? '').'" placeholder="Contoh: Lenovo, Dell, HP, Asus, Acer, Rakitan...">
            <datalist id="listMerk">
              <option value="Lenovo">
              <option value="Dell">
              <option value="HP">
              <option value="Asus">
              <option value="Acer">
              <option value="Apple">
              <option value="Rakitan / Custom">
              <option value="Epson">
              <option value="Brother">
              <option value="Canon">
            </datalist>
          </div>

          <div class="col-md-6">
            <label class="form-label text-secondary small fw-semibold">Tipe / Model <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="model" required value="'.e($asset['model'] ?? '').'" placeholder="Contoh: ThinkPad T14, OptiPlex 3080, Core i5 Rakitan...">
          </div>

          <div class="col-md-6">
            <label class="form-label text-secondary small fw-semibold">Nomor Seri (Serial Number / S/N)</label>
            <input type="text" class="form-control font-monospace" name="serial_number" value="'.e($asset['serial_number'] ?? '').'" placeholder="Contoh: SN-892347293 (atau - jika tidak ada)">
          </div>

          <div class="col-md-6">
            <label class="form-label text-secondary small fw-semibold">Status Aset</label>
            <select class="form-select" name="status">
              <option value="Aktif"'.($curStatus === 'Aktif' ? ' selected' : '').'>Aktif (Digunakan)</option>
              <option value="Backup"'.($curStatus === 'Backup' ? ' selected' : '').'>Backup / Cadangan</option>
              <option value="Perbaikan"'.($curStatus === 'Perbaikan' ? ' selected' : '').'>Sedang Dalam Perbaikan</option>
              <option value="Nonaktif"'.($curStatus === 'Nonaktif' ? ' selected' : '').'>Nonaktif</option>
            </select>
          </div>
        </div>

        <!-- Section 2: Penempatan & Pemilik -->
        <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
          <span class="tech-label text-primary">02 · LOKASI & PENANGGUNG JAWAB</span>
        </div>
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label text-secondary small fw-semibold">Cabang / Lokasi <span class="text-danger">*</span></label>
            <select class="form-select" name="id_cabang" required>
              '.$optCab.'
            </select>
          </div>

          <div class="col-md-6">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label text-secondary small fw-semibold mb-0">Divisi / Unit Kerja <span class="text-danger">*</span></label>
              <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none fw-semibold text-primary" data-bs-toggle="modal" data-bs-target="#modalQuickAddDivisi" style="font-size: 0.8rem;">
                <i class="bi bi-plus-circle me-1"></i>+ Tambah Divisi
              </button>
            </div>
            <div class="input-group">
              <select class="form-select" name="id_divisi" id="selectDivisi" required>
                <option value="">-- Pilih Divisi / Unit Kerja --</option>
                '.$optDiv.'
                <option value="__add_new__" class="fw-bold text-primary bg-light">+ Tambah Divisi Baru...</option>
              </select>
              <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalQuickAddDivisi" title="Tambah Divisi Baru">
                <i class="bi bi-plus-lg text-primary"></i>
              </button>
            </div>
            <div id="divisiAddedFeedback" class="small text-success mt-1 d-none"><i class="bi bi-check-circle-fill me-1"></i><span id="divisiAddedText">Divisi baru berhasil dipilih.</span></div>
          </div>

          <div class="col-md-6">
            <label class="form-label text-secondary small fw-semibold">Pengguna / Pemilik Komputer</label>
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="bi bi-person text-muted"></i></span>
              <input type="text" class="form-control" name="nama_karyawan" id="inputNamaKaryawan" list="listKaryawan" value="'.e($curKarName).'" placeholder="Pilih atau ketik nama pemilik..." autocomplete="off">
            </div>
            <datalist id="listKaryawan">
              '.$datalistKaryawan.'
            </datalist>
            <div class="form-text small text-muted">Pilih dari daftar atau ketik nama staf secara langsung.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label text-secondary small fw-semibold">Posisi Stiker QR</label>
            <input type="text" class="form-control" name="placement_label" list="listPlacement" value="'.e($asset['placement_label'] ?? 'Bodi Casing').'" placeholder="Posisi stiker ditempel">
            <datalist id="listPlacement">
              <option value="Bodi Casing">
              <option value="Cover Atas Laptop">
              <option value="Samping CPU">
              <option value="Belakang Monitor">
              <option value="Meja Kerja">
              <option value="Badan Printer">
            </datalist>
          </div>

          <div class="col-md-6">
            <label class="form-label text-secondary small fw-semibold"><i class="bi bi-qr-code text-primary me-1"></i>Token QR (Permanen & Terkunci)</label>
            <div class="input-group">
              <input type="text" class="form-control font-monospace bg-light text-secondary" value="'.e($curQrToken).'" readonly>
              <span class="input-group-text bg-light text-success fw-semibold small" title="Token ini permanen, stiker fisik lama tetap berlaku selamanya"><i class="bi bi-lock-fill me-1"></i> Tetap</span>
            </div>
            <div class="form-text small text-muted">Token QR ini permanen dan tidak berubah saat diedit. Stiker fisik lama di komputer tetap berlaku.</div>
          </div>
        </div>

        <!-- Section 3: Master Data Inventaris Bank (Core Banking) -->
        <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
          <span class="tech-label text-primary">03 · MASTER DATA INVENTARIS BANK (CORE SYSTEM)</span>
        </div>
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label text-secondary small fw-semibold">Nomor Seri Inventaris Bank</label>
            <div class="input-group">
              <input type="text" class="form-control font-monospace" name="nomor_seri_inventaris" id="noSeriInventaris" value="'.e($curNoSeri).'" placeholder="Contoh: 01.05.02.220124.001">
              <button class="btn btn-light border" type="button" onclick="autoGenerateNoSeri()" title="Generate Format Bank"><i class="bi bi-magic me-1"></i>Auto</button>
            </div>
            <div class="form-text small text-muted">Format resmi: [Cabang].[Ruangan].[Golongan].[ddMMyy].[Nomor]</div>
          </div>

          <div class="col-md-6">
            <label class="form-label text-secondary small fw-semibold">Jenis Inventaris <span class="text-danger">*</span></label>
            <select class="form-select" name="jenis_inventaris">
              '.$optJenisInv.'
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label text-secondary small fw-semibold">Golongan Inventaris <span class="text-danger">*</span></label>
            <select class="form-select" name="golongan" id="selectGolongan">
              '.$optGolongan.'
            </select>
          </div>

          <div class="col-md-6">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label text-secondary small fw-semibold mb-0">Ruangan Penempatan</label>
              <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none fw-semibold text-primary" data-bs-toggle="modal" data-bs-target="#modalQuickAddRuangan" style="font-size: 0.8rem;">
                <i class="bi bi-plus-circle me-1"></i>+ Tambah Ruangan
              </button>
            </div>
            <div class="input-group">
              <select class="form-select" name="ruangan" id="selectRuangan">
                '.$optRuangan.'
              </select>
              <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalQuickAddRuangan" title="Tambah Ruangan Baru">
                <i class="bi bi-plus-lg text-primary"></i>
              </button>
            </div>
            <div id="ruanganAddedFeedback" class="small text-success mt-1 d-none"><i class="bi bi-check-circle-fill me-1"></i><span id="ruanganAddedText">Ruangan baru berhasil dipilih.</span></div>
          </div>

          <div class="col-md-6">
            <label class="form-label text-secondary small fw-semibold">Sumber Perolehan</label>
            <select class="form-select" name="sumber_perolehan">
              '.$optSumber.'
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label text-secondary small fw-semibold">Status Inventaris Bank</label>
            <select class="form-select" name="status_inventaris">
              '.$optStatusInv.'
            </select>
          </div>
        </div>

        <!-- Section 4: Jaringan, Printer & Spesifikasi -->
        <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
          <span class="tech-label text-primary">04 · JARINGAN & SPESIFIKASI TEKNIS</span>
        </div>
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label text-secondary small fw-semibold"><i class="bi bi-hdd-network me-1 text-success"></i>Alamat IP (IP Address)</label>
            <input type="text" class="form-control font-monospace" name="ip_address" value="'.e($asset['ip_address'] ?? $asset['ip'] ?? '').'" placeholder="Contoh: 192.168.1.50 (atau DHCP)">
            <div class="form-text small text-muted">Alamat IP ini otomatis tercetak pada Kartu Kontrol & scan teknisi.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label text-secondary small fw-semibold"><i class="bi bi-printer me-1 text-info"></i>Printer Terhubung</label>
            <input type="text" class="form-control" name="printer" value="'.e($asset['printer'] ?? '').'" placeholder="Contoh: Epson L3110, HP LaserJet, atau -">
            <div class="form-text small text-muted">Printer yang terhubung pada unit komputer ini.</div>
          </div>

          <div class="col-12">
            <label class="form-label text-secondary small fw-semibold">Spesifikasi Hardware & Sistem Operasi</label>
            <textarea class="form-control" name="keterangan" rows="3" placeholder="Contoh: Core i5-12400 / RAM 16GB / SSD 512GB NVMe / Windows 11 Pro">'.e($asset['keterangan'] ?? '').'</textarea>
          </div>
        </div>

        <!-- Section 5: Foto Fisik Unit / Perangkat -->
        <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
          <span class="tech-label text-primary">05 · FOTO FISIK PERANGKAT / UNIT</span>
        </div>
        <div class="row g-3 mb-4">
          <div class="col-md-7">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label text-secondary small fw-semibold mb-0">Ganti / Upload Foto Unit Baru</label>
              <span id="compressBadgeEdit" class="badge bg-primary bg-opacity-10 text-primary d-none" style="font-size: 0.72rem;">
                <i class="bi bi-shield-check me-1"></i> Terkompres Cerdas
              </span>
            </div>
            <input type="file" class="form-control" name="foto_unit" id="inputFotoUnit" accept="image/*" capture="environment">
            <div class="form-text small text-muted">Mendukung format JPG, PNG, WEBP. Foto otomatis dikompres hemat ruang server (~150-250KB).</div>

            '.(!empty($asset['foto_updated_by']) ? '
            <div class="mt-2">
              <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1">
                <i class="bi bi-patch-check-fill me-1"></i> Foto Terverifikasi: <strong>'.e($asset['foto_updated_by']).'</strong>
                '.(!empty($asset['foto_updated_at']) ? ' ('.date('d/m/Y H:i', strtotime($asset['foto_updated_at'])).')' : '').'
              </span>
            </div>
            ' : '').'

            <div class="mt-3">
              <label class="form-label text-secondary small fw-semibold">Atau URL Foto dari Server Immich / Cloud</label>
              <input type="text" class="form-control font-monospace" name="foto_url" id="inputFotoUrl" value="'.e(str_starts_with($existingFoto, 'http') ? $existingFoto : '').'" placeholder="http://192.168.88.163:2283/api/assets/... atau link foto">
              <div class="form-text small text-muted">Isi jika unit disimpan di Server Immich.</div>
            </div>
          </div>
          <div class="col-md-5 text-center">
            <label class="form-label text-secondary small fw-semibold d-block text-start">Pratinjau Foto Unit</label>
            <div id="fotoPreviewContainer" class="border rounded bg-light d-flex align-items-center justify-content-center position-relative overflow-hidden shadow-xs" style="height: 160px;">
              <img id="imgFotoPreview" src="'.e($existingFotoSrc).'" alt="Pratinjau" class="w-100 h-100 object-fit-cover '.($existingFotoSrc ? '' : 'd-none').'">
              <div id="fotoPlaceholder" class="text-secondary small '.($existingFotoSrc ? 'd-none' : '').'">
                <i class="bi bi-camera fs-1 d-block text-muted mb-1"></i>
                Belum ada foto unit
              </div>
            </div>
            '.($existingFotoSrc ? '
            <div class="form-check text-start mt-2">
              <input class="form-check-input" type="checkbox" name="hapus_foto" value="1" id="checkHapusFoto">
              <label class="form-check-label small text-danger fw-semibold" for="checkHapusFoto">
                <i class="bi bi-trash"></i> Hapus Foto Unit Ini
              </label>
            </div>
            ' : '').'
          </div>
        </div>

        <!-- Submit & Delete Buttons -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3 border-top">
          <div class="d-flex gap-2">
            <a class="btn btn-light border px-3" href="'.e(module_url('assets.php')).'">Batal</a>
            <a class="btn btn-light border text-danger px-3" href="'.e(module_url('asset_delete.php', ['id' => $id])).'" onclick="return confirm(\'Apakah Anda yakin ingin menghapus aset '.e(addslashes($asset['kode_inventaris'] ?? '')).' ini?\')">
              <i class="bi bi-trash me-1"></i> Hapus Aset
            </a>
          </div>
          <button type="submit" class="btn btn-primary px-4 fw-semibold"><i class="bi bi-check2 me-1"></i> Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Quick Add Divisi -->
<div class="modal fade" id="modalQuickAddDivisi" tabindex="-1" aria-labelledby="modalQuickAddDivisiLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow border-0" style="border-radius: 16px;">
      <div class="modal-header border-bottom py-3 px-4">
        <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="modalQuickAddDivisiLabel">
          <i class="bi bi-diagram-3 text-primary"></i> Tambah Divisi / Unit Kerja
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div id="quickDivisiAlert" class="alert alert-danger d-none py-2 small mb-3"></div>
        <div class="mb-3">
          <label class="form-label text-secondary small fw-semibold">Nama Divisi / Bagian <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="inputQuickNamaDivisi" placeholder="Contoh: Digital Banking, Logistik, Legal...">
          <div class="form-text small text-muted">Nama divisi yang akan muncul pada daftar unit kerja komputer.</div>
        </div>
        <div class="mb-2">
          <label class="form-label text-secondary small fw-semibold">Keterangan (Opsional)</label>
          <input type="text" class="form-control" id="inputQuickKetDivisi" placeholder="Keterangan singkat fungsi / lokasi divisi">
        </div>
      </div>
      <div class="modal-footer border-top bg-light py-2 px-4 d-flex justify-content-between">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-sm btn-primary fw-semibold" id="btnQuickSubmitDivisi">
          <span id="quickDivisiSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
          <i class="bi bi-check2 me-1" id="quickDivisiBtnIcon"></i> Simpan Divisi
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Quick Add Ruangan -->
<div class="modal fade" id="modalQuickAddRuangan" tabindex="-1" aria-labelledby="modalQuickAddRuanganLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow border-0" style="border-radius: 16px;">
      <div class="modal-header border-bottom py-3 px-4">
        <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="modalQuickAddRuanganLabel">
          <i class="bi bi-door-open text-primary"></i> Tambah Ruangan Penempatan
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div id="quickRuanganAlert" class="alert alert-danger d-none py-2 small mb-3"></div>
        <div class="mb-3">
          <label class="form-label text-secondary small fw-semibold">Kode Ruangan (2 Digit)</label>
          <input type="text" class="form-control font-monospace" id="inputQuickKodeRuangan" placeholder="Contoh: 12 (Kosongkan utk otomatis)">
          <div class="form-text small text-muted">Biarkan kosong untuk penomoran otomatis.</div>
        </div>
        <div class="mb-2">
          <label class="form-label text-secondary small fw-semibold">Nama Ruangan <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="inputQuickNamaRuangan" placeholder="Contoh: RUANG AUDIT & SKAI...">
        </div>
      </div>
      <div class="modal-footer border-top bg-light py-2 px-4 d-flex justify-content-between">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-sm btn-primary fw-semibold" id="btnQuickSubmitRuangan">
          <span id="quickRuanganSpinner" class="spinner-border spinner-border-sm me-1 d-none"></span>
          <i class="bi bi-check2 me-1" id="quickRuanganBtnIcon"></i> Simpan Ruangan
        </button>
      </div>
    </div>
  </div>
</div>';

$script = '
<script>
function autoGenerateKode() {
  var rand = Math.floor(100 + Math.random() * 900);
  document.getElementById("kodeInventaris").value = "INV-IT-" + rand;
}

function autoGenerateNoSeri() {
  var d = new Date();
  var dd = String(d.getDate()).padStart(2, "0");
  var mm = String(d.getMonth() + 1).padStart(2, "0");
  var yy = String(d.getFullYear()).slice(-2);
  var rand = String(Math.floor(1 + Math.random() * 999)).padStart(3, "0");
  
  // Ambil kode ruangan
  var selR = document.getElementById("selectRuangan");
  var kdR = "05";
  if (selR && selR.value) {
    var mR = selR.value.match(/^(\d{2})/);
    if (mR) kdR = mR[1];
  }

  // Ambil kode golongan
  var selG = document.getElementById("selectGolongan");
  var kdG = "02";
  if (selG && selG.value) {
    var mG = selG.value.match(/^(\d{2})/);
    if (mG) kdG = mG[1];
  }

  document.getElementById("noSeriInventaris").value = "01." + kdR + "." + kdG + "." + dd + mm + yy + "." + rand;
}

function setKaryawan(name) {
  var inp = document.getElementById("inputNamaKaryawan");
  inp.value = name;
  inp.focus();
}

// Quick Add Divisi Logic
document.addEventListener("DOMContentLoaded", function() {
  const selectDiv = document.getElementById("selectDivisi");
  const modalEl = document.getElementById("modalQuickAddDivisi");
  const inputNama = document.getElementById("inputQuickNamaDivisi");
  const inputKet = document.getElementById("inputQuickKetDivisi");
  const btnSubmit = document.getElementById("btnQuickSubmitDivisi");
  const alertEl = document.getElementById("quickDivisiAlert");
  const spinner = document.getElementById("quickDivisiSpinner");
  const btnIcon = document.getElementById("quickDivisiBtnIcon");
  const feedbackEl = document.getElementById("divisiAddedFeedback");
  const feedbackText = document.getElementById("divisiAddedText");

  let previousValue = selectDiv ? selectDiv.value : "";

  if (selectDiv) {
    selectDiv.addEventListener("change", function() {
      if (this.value === "__add_new__") {
        this.value = previousValue;
        if (modalEl) {
          const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
          bsModal.show();
        }
      } else {
        previousValue = this.value;
      }
    });
  }

  if (modalEl) {
    modalEl.addEventListener("shown.bs.modal", function() {
      if (inputNama) {
        inputNama.value = "";
        inputNama.focus();
      }
      if (inputKet) inputKet.value = "";
      if (alertEl) {
        alertEl.classList.add("d-none");
        alertEl.textContent = "";
      }
    });
  }

  if (btnSubmit) {
    btnSubmit.addEventListener("click", function() {
      submitQuickDivisi();
    });
  }

  if (inputNama) {
    inputNama.addEventListener("keydown", function(e) {
      if (e.key === "Enter") {
        e.preventDefault();
        submitQuickDivisi();
      }
    });
  }

  function submitQuickDivisi() {
    const nama = inputNama ? inputNama.value.trim() : "";
    const ket = inputKet ? inputKet.value.trim() : "";

    if (!nama) {
      if (alertEl) {
        alertEl.textContent = "Nama divisi tidak boleh kosong.";
        alertEl.classList.remove("d-none");
      }
      if (inputNama) inputNama.focus();
      return;
    }

    if (alertEl) alertEl.classList.add("d-none");
    if (spinner) spinner.classList.remove("d-none");
    if (btnIcon) btnIcon.classList.add("d-none");
    if (btnSubmit) btnSubmit.disabled = true;

    const csrfInput = document.querySelector(\'input[name="_csrf"]\');
    const csrfToken = csrfInput ? csrfInput.value : "";

    const formData = new FormData();
    formData.append("_csrf", csrfToken);
    formData.append("nama_divisi", nama);
    formData.append("keterangan", ket);

    fetch("divisi_ajax_add.php", {
      method: "POST",
      body: formData
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
      if (spinner) spinner.classList.add("d-none");
      if (btnIcon) btnIcon.classList.remove("d-none");
      if (btnSubmit) btnSubmit.disabled = false;

      if (!data.success) {
        if (alertEl) {
          alertEl.textContent = data.error || "Gagal menambahkan divisi baru.";
          alertEl.classList.remove("d-none");
        }
        return;
      }

      // Berhasil: masukkan opsi baru ke dalam selectDivisi
      const newId = String(data.id);
      const newNama = data.nama;

      if (selectDiv) {
        let optExists = selectDiv.querySelector(\'option[value="\' + newId + \'"]\');
        if (!optExists) {
          const newOpt = document.createElement("option");
          newOpt.value = newId;
          newOpt.textContent = newNama;
          const addNewOpt = selectDiv.querySelector(\'option[value="__add_new__"]\');
          if (addNewOpt) {
            selectDiv.insertBefore(newOpt, addNewOpt);
          } else {
            selectDiv.appendChild(newOpt);
          }
        }
        selectDiv.value = newId;
        previousValue = newId;
      }

      // Tampilkan feedback sukses
      if (feedbackEl && feedbackText) {
        feedbackText.textContent = "Divisi \"" + newNama + "\" berhasil ditambahkan dan dipilih!";
        feedbackEl.classList.remove("d-none");
        setTimeout(function() {
          feedbackEl.classList.add("d-none");
        }, 5000);
      }

      // Tutup modal
      if (modalEl) {
        const bsModal = bootstrap.Modal.getInstance(modalEl);
        if (bsModal) bsModal.hide();
      }
    })
    .catch(function(err) {
      if (spinner) spinner.classList.add("d-none");
      if (btnIcon) btnIcon.classList.remove("d-none");
      if (btnSubmit) btnSubmit.disabled = false;
      if (alertEl) {
        alertEl.textContent = "Terjadi kesalahan jaringan atau server.";
        alertEl.classList.remove("d-none");
      }
    });
  }

  // Quick Add Ruangan Logic
  const selectRuang = document.getElementById("selectRuangan");
  const modalRuangEl = document.getElementById("modalQuickAddRuangan");
  const inputNamaRuang = document.getElementById("inputQuickNamaRuangan");
  const inputKodeRuang = document.getElementById("inputQuickKodeRuangan");
  const btnSubmitRuang = document.getElementById("btnQuickSubmitRuangan");
  const alertRuangEl = document.getElementById("quickRuanganAlert");
  const spinnerRuang = document.getElementById("quickRuanganSpinner");
  const btnRuangIcon = document.getElementById("quickRuanganBtnIcon");
  const feedbackRuangEl = document.getElementById("ruanganAddedFeedback");
  const feedbackRuangText = document.getElementById("ruanganAddedText");

  if (modalRuangEl) {
    modalRuangEl.addEventListener("shown.bs.modal", function() {
      if (inputNamaRuang) {
        inputNamaRuang.value = "";
        inputNamaRuang.focus();
      }
      if (inputKodeRuang) inputKodeRuang.value = "";
      if (alertRuangEl) {
        alertRuangEl.classList.add("d-none");
        alertRuangEl.textContent = "";
      }
    });
  }

  if (btnSubmitRuang) {
    btnSubmitRuang.addEventListener("click", function() {
      submitQuickRuangan();
    });
  }

  if (inputNamaRuang) {
    inputNamaRuang.addEventListener("keydown", function(e) {
      if (e.key === "Enter") {
        e.preventDefault();
        submitQuickRuangan();
      }
    });
  }

  function submitQuickRuangan() {
    const nama = inputNamaRuang ? inputNamaRuang.value.trim() : "";
    const kode = inputKodeRuang ? inputKodeRuang.value.trim() : "";

    if (!nama) {
      if (alertRuangEl) {
        alertRuangEl.textContent = "Nama ruangan tidak boleh kosong.";
        alertRuangEl.classList.remove("d-none");
      }
      if (inputNamaRuang) inputNamaRuang.focus();
      return;
    }

    if (alertRuangEl) alertRuangEl.classList.add("d-none");
    if (spinnerRuang) spinnerRuang.classList.remove("d-none");
    if (btnRuangIcon) btnRuangIcon.classList.add("d-none");
    if (btnSubmitRuang) btnSubmitRuang.disabled = true;

    const csrfInput = document.querySelector(\'input[name="_csrf"]\');
    const csrfToken = csrfInput ? csrfInput.value : "";

    const formData = new FormData();
    formData.append("_csrf", csrfToken);
    formData.append("nama_ruangan", nama);
    formData.append("kode_ruangan", kode);

    fetch("ruangan_ajax_add.php", {
      method: "POST",
      body: formData
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
      if (spinnerRuang) spinnerRuang.classList.add("d-none");
      if (btnRuangIcon) btnRuangIcon.classList.remove("d-none");
      if (btnSubmitRuang) btnSubmitRuang.disabled = false;

      if (!data.success) {
        if (alertRuangEl) {
          alertRuangEl.textContent = data.error || "Gagal menambahkan ruangan baru.";
          alertRuangEl.classList.remove("d-none");
        }
        return;
      }

      const newVal = data.value || (data.kode + " : " + data.nama);
      if (selectRuang) {
        const newOpt = document.createElement("option");
        newOpt.value = newVal;
        newOpt.textContent = newVal;
        selectRuang.appendChild(newOpt);
        selectRuang.value = newVal;
      }

      if (feedbackRuangEl && feedbackRuangText) {
        feedbackRuangText.textContent = "Ruangan \"" + newVal + "\" berhasil ditambahkan!";
        feedbackRuangEl.classList.remove("d-none");
        setTimeout(function() {
          feedbackRuangEl.classList.add("d-none");
        }, 5000);
      }

      if (modalRuangEl) {
        const bsModal = bootstrap.Modal.getInstance(modalRuangEl);
        if (bsModal) bsModal.hide();
      }
    })
    .catch(function(err) {
      if (spinnerRuang) spinnerRuang.classList.add("d-none");
      if (btnRuangIcon) btnRuangIcon.classList.remove("d-none");
      if (btnSubmitRuang) btnSubmitRuang.disabled = false;
      if (alertRuangEl) {
        alertRuangEl.textContent = "Terjadi kesalahan jaringan atau server.";
        alertRuangEl.classList.remove("d-none");
      }
    });
  }

  // Kompresi Gambar Cerdas di Browser (Canvas Client-side)
  function compressImageClientSide(file, maxDimension, quality, callback) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const img = new Image();
      img.onload = function() {
        let w = img.width;
        let h = img.height;
        if (w > maxDimension || h > maxDimension) {
          if (w >= h) {
            h = Math.round((h / w) * maxDimension);
            w = maxDimension;
          } else {
            w = Math.round((w / h) * maxDimension);
            h = maxDimension;
          }
        }
        const canvas = document.createElement("canvas");
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext("2d");
        ctx.drawImage(img, 0, 0, w, h);
        canvas.toBlob(function(blob) {
          callback(blob, canvas.toDataURL("image/jpeg", quality));
        }, "image/jpeg", quality);
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  }

  // Live Preview Foto Unit dengan Kompresi Otomatis
  const inputFoto = document.getElementById("inputFotoUnit");
  const inputFotoUrl = document.getElementById("inputFotoUrl");
  const imgPreview = document.getElementById("imgFotoPreview");
  const placeholder = document.getElementById("fotoPlaceholder");
  const checkHapus = document.getElementById("checkHapusFoto");
  const compressBadgeEdit = document.getElementById("compressBadgeEdit");

  if (inputFoto && imgPreview && placeholder) {
    inputFoto.addEventListener("change", function(e) {
      const file = e.target.files && e.target.files[0];
      if (file) {
        if (compressBadgeEdit) {
          compressBadgeEdit.classList.remove("d-none");
          compressBadgeEdit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengompres...';
        }
        compressImageClientSide(file, 1600, 0.82, function(compressedBlob, previewDataUrl) {
          imgPreview.src = previewDataUrl;
          imgPreview.classList.remove("d-none");
          placeholder.classList.add("d-none");
          if (checkHapus) checkHapus.checked = false;

          try {
            const compressedFile = new File([compressedBlob], "compressed_" + file.name.replace(/\.[^.]+$/, ".jpg"), {
              type: "image/jpeg",
              lastModified: Date.now()
            });
            const dt = new DataTransfer();
            dt.items.add(compressedFile);
            inputFoto.files = dt.files;
            if (compressBadgeEdit) {
              const kbSize = Math.round(compressedBlob.size / 1024);
              compressBadgeEdit.innerHTML = '<i class="bi bi-shield-check me-1"></i> ' + kbSize + ' KB (Hemat Ruang)';
            }
          } catch (err) {
            console.warn("Fallback DataTransfer:", err);
          }
        });
      }
    });
  }

  if (inputFotoUrl && imgPreview && placeholder) {
    inputFotoUrl.addEventListener("input", function(e) {
      const val = e.target.value.trim();
      if (val) {
        imgPreview.src = val;
        imgPreview.classList.remove("d-none");
        placeholder.classList.add("d-none");
        if (checkHapus) checkHapus.checked = false;
      }
    });
  }

  if (checkHapus && imgPreview && placeholder) {
    checkHapus.addEventListener("change", function(e) {
      if (e.target.checked) {
        imgPreview.classList.add("d-none");
        placeholder.classList.remove("d-none");
      }
    });
  }
});
</script>';

render_page('Edit Data Komputer', $body, '', $script);
