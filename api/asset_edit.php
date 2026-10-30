<?php
require __DIR__ . '/bootstrap.php';
require_it();

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

    if ($merk === '' && $model === '') {
        $error = 'Merk atau Model perangkat wajib diisi.';
    } else {
        $fotoUnit = upload_asset_image('foto_unit');
        if (!$fotoUnit && !empty($_POST['foto_url'])) {
            $fotoUnit = trim((string)$_POST['foto_url']);
        }
        $fotoUnit2 = upload_asset_image('foto_unit_2');

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
        ];

        $actorName = trim((string)($_POST['verifier_name'] ?? '')) ?: (current_user_name() ?: 'Teknisi IT');
        if (!empty($_POST['face_verified']) || $fotoUnit !== null || $fotoUnit2 !== null) {
            $payload['foto_updated_by'] = $actorName;
            $payload['foto_updated_at'] = date('Y-m-d H:i:s');
        }

        $oldF1 = trim((string)($asset['foto'] ?? ''));
        $oldF2 = trim((string)($asset['foto_2'] ?? ''));
        $delAll = !empty($_POST['hapus_semua_foto']);

        if ($fotoUnit !== null) {
            if ($oldF1 !== '' && $oldF1 !== $fotoUnit) {
                move_photo_to_trash('assets', $id, 1, $oldF1, $actorName, 'Diganti dengan Foto 1 baru pada Edit Aset');
            }
            $payload['foto'] = $fotoUnit;
        } elseif ($delAll || !empty($_POST['hapus_foto'])) {
            if ($oldF1 !== '') {
                move_photo_to_trash('assets', $id, 1, $oldF1, $actorName, 'Dihapus pada Edit Aset (Foto 1)');
            }
            $payload['foto'] = '';
        }

        if ($fotoUnit2 !== null) {
            if ($oldF2 !== '' && $oldF2 !== $fotoUnit2) {
                move_photo_to_trash('assets', $id, 2, $oldF2, $actorName, 'Diganti dengan Foto 2 baru pada Edit Aset');
            }
            $payload['foto_2'] = $fotoUnit2;
        } elseif ($delAll || !empty($_POST['hapus_foto_2'])) {
            if ($oldF2 !== '') {
                move_photo_to_trash('assets', $id, 2, $oldF2, $actorName, 'Dihapus pada Edit Aset (Foto 2)');
            }
            $payload['foto_2'] = '';
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

    $replInfo = function_exists('get_asset_replacement_info') ? get_asset_replacement_info($asset) : ['replaces' => null, 'replaced_by' => null, 'date' => null];
$crossLinkBannerHtml = '';

if (!empty($replInfo['replaces'])) {
    $oldA = $replInfo['replaces'];
    $oldTitle = asset_title($oldA);
    $rawDate = $replInfo['date'] ?? '';
    $dateText = $rawDate ? (strtotime($rawDate) ? format_id_date($rawDate) : $rawDate) : date('d/m/Y');
    $crossLinkBannerHtml = '
    <div class="alert alert-primary bg-primary bg-opacity-10 border-primary border-opacity-25 rounded-3 py-2.5 px-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2 shadow-xs">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-arrow-repeat text-primary fs-4 flex-shrink-0"></i>
        <div>
          <div class="fw-bold text-primary small"><i class="bi bi-link-45deg me-1"></i>Unit ini menggantikan: <span class="text-dark">'.e($oldA['kode_inventaris'] ?? '#'.$oldA['id']).'</span></div>
          <div class="text-secondary small">'.e($oldTitle).' &bull; Diganti pada: <strong>'.e($dateText).'</strong></div>
        </div>
      </div>
      <a href="'.e(module_url('asset_edit.php', ['id' => (int)$oldA['id']])).'" class="btn btn-sm btn-outline-primary py-1 px-2.5 fw-semibold">
        <i class="bi bi-pencil me-1"></i>Lihat Data Komputer Lama
      </a>
    </div>';
} elseif (!empty($replInfo['replaced_by'])) {
    $newA = $replInfo['replaced_by'];
    $newTitle = asset_title($newA);
    $rawDate = $replInfo['date'] ?? '';
    $dateText = $rawDate ? (strtotime($rawDate) ? format_id_date($rawDate) : $rawDate) : date('d/m/Y');
    $crossLinkBannerHtml = '
    <div class="alert alert-warning bg-warning bg-opacity-10 border-warning border-opacity-50 rounded-3 py-2.5 px-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2 shadow-xs">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-arrow-right-circle-fill text-warning fs-4 flex-shrink-0"></i>
        <div>
          <div class="fw-bold text-dark small"><i class="bi bi-link-45deg me-1"></i>Telah diganti oleh: <span class="text-primary fw-bold">'.e($newA['kode_inventaris'] ?? '#'.$newA['id']).'</span></div>
          <div class="text-secondary small">'.e($newTitle).' &bull; Pada tanggal <strong>'.e($dateText).'</strong></div>
        </div>
      </div>
      <a href="'.e(module_url('asset_edit.php', ['id' => (int)$newA['id']])).'" class="btn btn-sm btn-dark py-1 px-2.5 fw-semibold">
        <i class="bi bi-pencil me-1"></i>Buka Data Komputer Baru
      </a>
    </div>';
}

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

$errorHtml = $error ? '<div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="bi bi-exclamation-triangle-fill me-2"></i>'.e($error).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$existingFoto = trim((string)($asset['foto'] ?? ''));
$existingFotoSrc = '';
if ($existingFoto) {
    $existingFotoSrc = str_starts_with($existingFoto, 'http') ? $existingFoto : module_url($existingFoto);
}
$existingFoto2 = trim((string)($asset['foto_2'] ?? ''));
$existingFoto2Src = '';
if ($existingFoto2) {
    $existingFoto2Src = str_starts_with($existingFoto2, 'http') ? $existingFoto2 : module_url($existingFoto2);
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
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <a class="btn btn-warning btn-sm text-dark fw-bold d-inline-flex align-items-center gap-1 shadow-xs" href="'.e(module_url('asset_add.php', ['replace_id' => (int)$asset['id']])).'" title="Klik untuk mengganti komputer lama ini dengan unit baru"><i class="bi bi-arrow-repeat"></i> Ganti ke Unit Baru</a>
        <a class="btn btn-light border btn-sm d-inline-flex align-items-center gap-1" href="'.e(module_url('assets.php')).'"><i class="bi bi-arrow-left"></i> Kembali ke Aset</a>
      </div>
    </div>

    '.$errorHtml.'

    '.$crossLinkBannerHtml.'

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
            <label class="form-label text-secondary small fw-semibold">Status Operasional Aset</label>
            <select class="form-select" name="status">
              <option value="Aktif"'.(strtolower($curStatus) === 'aktif' || $curStatus === '' ? ' selected' : '').'>Aktif (Wajib Maintenance Bulanan)</option>
              <option value="Tidak Terpakai"'.(in_array(strtolower($curStatus), ['tidak terpakai', 'diganti baru', 'pensiun'], true) ? ' selected' : '').'>Tidak Terpakai / Diganti Unit Baru (Bebas Maintenance)</option>
              <option value="Backup"'.($curStatus === 'Backup' ? ' selected' : '').'>Backup / Cadangan</option>
              <option value="Perbaikan"'.($curStatus === 'Perbaikan' ? ' selected' : '').'>Sedang Dalam Perbaikan</option>
              <option value="Nonaktif"'.($curStatus === 'Nonaktif' ? ' selected' : '').'>Nonaktif (Bebas Maintenance)</option>
              <option value="Afkir"'.(in_array(strtolower($curStatus), ['afkir', 'disposal', 'rusak total'], true) ? ' selected' : '').'>Afkir / Dihapuskan (Bebas Maintenance)</option>
            </select>
            <div class="form-text small text-muted"><i class="bi bi-info-circle me-1 text-primary"></i>Komputer lama yang <strong>Tidak Terpakai / Diganti Unit Baru</strong> tetap tersimpan di riwayat data inventaris (spesifikasi, foto, histori), namun <strong>otomatis tidak masuk target pemeliharaan bulanan</strong>.</div>
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

        <!-- Section 3: Jaringan, Printer & Spesifikasi -->
        <div class="d-flex align-items-center gap-2 border-bottom pb-2 mb-3">
          <span class="tech-label text-primary">03 · JARINGAN & SPESIFIKASI TEKNIS</span>
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

        <!-- Section 4: Foto Fisik Unit / Perangkat (Mendukung 2 Foto + Folder Sampah 7 Hari) -->
        <div class="d-flex align-items-center justify-content-between gap-2 border-bottom pb-2 mb-3 flex-wrap">
          <span class="tech-label text-primary">04 · FOTO FISIK PERANGKAT / UNIT (MAKS. 2 FOTO)</span>
          <a href="'.e(module_url('photo_trash.php', ['source_type' => 'assets'])).'" class="btn btn-xs btn-outline-success rounded-pill px-2.5 py-1 fw-bold">
            <i class="bi bi-recycle me-1"></i>Buka Folder Sampah Foto (7 Hari)
          </a>
        </div>
        <div class="alert alert-success bg-success bg-opacity-10 border-success border-opacity-25 rounded-3 py-2 px-3 mb-3 small">
          <i class="bi bi-recycle text-success me-1"></i> Foto yang dihapus atau diganti tidak langsung hilang permanen, melainkan disimpan di <strong>Folder Sampah Foto</strong> selama <strong>7 hari</strong> dan dapat di-restore kapan saja.
        </div>
        <div class="row g-3 mb-4" id="editPhotoInputsRow">
          <div class="'.($existingFoto2Src !== '' ? 'col-md-6' : 'col-12').'" id="editSlot1Col">
            <div class="p-3 rounded-3 border bg-light h-100">
              <div class="d-flex justify-content-between align-items-center mb-1.5">
                <label class="form-label text-dark small fw-bold mb-0"><span class="badge bg-dark me-1">1</span> Foto 1 (Utama / Tampak Unit)</label>
                <span id="compressBadgeEdit" class="badge bg-primary bg-opacity-10 text-primary d-none" style="font-size: 0.70rem;">
                  <i class="bi bi-shield-check me-1"></i> Terkompres Cerdas
                </span>
              </div>
              <input type="file" class="form-control form-control-sm mb-2" name="foto_unit" id="inputFotoUnit" accept="image/*" capture="environment">
              <div id="fotoPreviewContainer" class="border rounded bg-white d-flex align-items-center justify-content-center position-relative overflow-hidden shadow-xs" style="height: 155px;">
                <img id="imgFotoPreview" src="'.e($existingFotoSrc).'" alt="Pratinjau Foto 1" class="w-100 h-100 object-fit-cover '.($existingFotoSrc ? '' : 'd-none').'">
                <div id="fotoPlaceholder" class="text-secondary small text-center '.($existingFotoSrc ? 'd-none' : '').'">
                  <i class="bi bi-camera fs-2 d-block text-muted mb-1"></i>
                  Belum ada Foto 1
                </div>
              </div>
              '.($existingFotoSrc ? '
              <div class="form-check text-start mt-2 mb-0">
                <input class="form-check-input" type="checkbox" name="hapus_foto" value="1" id="checkHapusFoto">
                <label class="form-check-label small text-danger fw-semibold" for="checkHapusFoto">
                  <i class="bi bi-trash"></i> Hilangkan Foto 1 Saja (Masuk Sampah 7 Hari)
                </label>
              </div>
              ' : '').'
            </div>
          </div>

          <div class="'.($existingFoto2Src !== '' ? 'col-md-6' : 'col-md-6 d-none').'" id="editSlot2Col">
            <div class="p-3 rounded-3 border bg-light h-100 position-relative">
              <button type="button" class="btn btn-xs btn-outline-danger position-absolute top-0 end-0 m-2 py-0 px-1 rounded" style="font-size: 0.62rem; z-index: 5;" onclick="tutupEditSlot2()" title="Batal tambah foto 2">
                <i class="bi bi-x-lg"></i>
              </button>
              <div class="d-flex justify-content-between align-items-center mb-1.5">
                <label class="form-label text-dark small fw-bold mb-0"><span class="badge bg-primary me-1">2</span> Foto 2 (Detail / S/N / Stiker)</label>
              </div>
              <input type="file" class="form-control form-control-sm mb-2" name="foto_unit_2" id="inputFotoUnit2" accept="image/*" capture="environment" onchange="if(this.files && this.files[0]){ var r=new FileReader(); r.onload=function(e){ var im=document.getElementById(\'imgFotoPreview2\'); var ph=document.getElementById(\'fotoPlaceholder2\'); if(im){im.src=e.target.result; im.classList.remove(\'d-none\');} if(ph) ph.classList.add(\'d-none\'); }; r.readAsDataURL(this.files[0]); }">
              <div id="fotoPreviewContainer2" class="border rounded bg-white d-flex align-items-center justify-content-center position-relative overflow-hidden shadow-xs" style="height: 155px;">
                <img id="imgFotoPreview2" src="'.e($existingFoto2Src).'" alt="Pratinjau Foto 2" class="w-100 h-100 object-fit-cover '.($existingFoto2Src ? '' : 'd-none').'">
                <div id="fotoPlaceholder2" class="text-secondary small text-center '.($existingFoto2Src ? 'd-none' : '').'">
                  <i class="bi bi-plus-circle-dotted fs-2 d-block text-primary mb-1"></i>
                  Belum ada Foto 2 (Opsional)
                </div>
              </div>
              '.($existingFoto2Src ? '
              <div class="form-check text-start mt-2 mb-0">
                <input class="form-check-input" type="checkbox" name="hapus_foto_2" value="1" id="checkHapusFoto2">
                <label class="form-check-label small text-danger fw-semibold" for="checkHapusFoto2">
                  <i class="bi bi-trash"></i> Hilangkan Foto 2 Saja (Masuk Sampah 7 Hari)
                </label>
              </div>
              ' : '').'
            </div>
          </div>

          <div id="editBtnTambahFoto2Box" class="'.($existingFoto2Src !== '' ? 'd-none' : 'col-12 text-center my-1').'">
            <button type="button" class="btn btn-xs btn-outline-primary fw-semibold px-3 py-1.5 rounded-pill shadow-xs" onclick="bukaEditSlot2()" style="font-size: 0.74rem;">
              <i class="bi bi-plus-circle me-1"></i> + Tambah Foto Ke-2 (Detail / S/N)
            </button>
          </div>
        </div>

          '.(($existingFotoSrc || $existingFoto2Src) ? '
          <div class="col-12">
            <div class="p-2.5 rounded-3 border border-danger border-opacity-25 bg-danger bg-opacity-10 d-flex align-items-center justify-content-between flex-wrap gap-2">
              <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="hapus_semua_foto" value="1" id="checkHapusSemuaFoto" onchange="if(this.checked){ var c1=document.getElementById(\'checkHapusFoto\'); var c2=document.getElementById(\'checkHapusFoto2\'); if(c1) c1.checked=true; if(c2) c2.checked=true; }">
                <label class="form-check-label small text-danger fw-bold" for="checkHapusSemuaFoto">
                  <i class="bi bi-trash3-fill me-1"></i> Hilangkan Semua Foto (Foto 1 &amp; Foto 2 dipindahkan ke Folder Sampah 7 Hari)
                </label>
              </div>
              '.(!empty($asset['foto_updated_by']) ? '
              <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1">
                <i class="bi bi-patch-check-fill me-1"></i> Diverifikasi: <strong>'.e($asset['foto_updated_by']).'</strong>
              </span>' : '').'
            </div>
          </div>' : '').'

          <div class="col-12">
            <label class="form-label text-secondary small fw-semibold">Atau URL Foto 1 Eksternal / Cloud (Opsional)</label>
            <input type="text" class="form-control form-control-sm font-monospace" name="foto_url" id="inputFotoUrl" value="'.e(str_starts_with($existingFoto, 'http') ? $existingFoto : '').'" placeholder="https://domain.com/path/foto.jpg">
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
</div>';

$script = '
<script>
function autoGenerateKode() {
  var rand = Math.floor(100 + Math.random() * 900);
  document.getElementById("kodeInventaris").value = "INV-IT-" + rand;
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

    fetch("divisi_ajax_add", {
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
        feedbackText.textContent = "Divisi \\"" + newNama + "\\" berhasil ditambahkan dan dipilih!";
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

  // Kompresi Gambar Cerdas di Browser (Canvas Client-side Rasio 4:5)
  function compressImageClientSide(file, maxDimension, quality, callback) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const img = new Image();
      img.onload = function() {
        const targetRatio = 4 / 5;
        let cropW = img.width;
        let cropH = img.height;
        let sx = 0;
        let sy = 0;

        if (img.width / img.height > targetRatio) {
          cropW = Math.round(img.height * targetRatio);
          cropH = img.height;
          sx = Math.round((img.width - cropW) / 2);
          sy = 0;
        } else {
          cropW = img.width;
          cropH = Math.round(img.width / targetRatio);
          sx = 0;
          sy = Math.round((img.height - cropH) / 2);
        }

        let outW = 1200;
        let outH = 1500;
        if (cropW < outW) {
          outW = cropW;
          outH = Math.round(outW / targetRatio);
        }

        const canvas = document.createElement("canvas");
        canvas.width = outW;
        canvas.height = outH;
        const ctx = canvas.getContext("2d");
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = "high";
        ctx.drawImage(img, sx, sy, cropW, cropH, 0, 0, outW, outH);
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
          compressBadgeEdit.innerHTML = "<span class=\\"spinner-border spinner-border-sm me-1\\"></span> Mengompres...";
        }
        compressImageClientSide(file, 1600, 0.82, function(compressedBlob, previewDataUrl) {
          imgPreview.src = previewDataUrl;
          imgPreview.classList.remove("d-none");
          placeholder.classList.add("d-none");
          if (checkHapus) checkHapus.checked = false;

          try {
            const compressedFile = new File([compressedBlob], "compressed_" + file.name.replace(/\\.[^.]+$/, ".jpg"), {
              type: "image/jpeg",
              lastModified: Date.now()
            });
            const dt = new DataTransfer();
            dt.items.add(compressedFile);
            inputFoto.files = dt.files;
            if (compressBadgeEdit) {
              const kbSize = Math.round(compressedBlob.size / 1024);
              compressBadgeEdit.innerHTML = "<i class=\\"bi bi-shield-check me-1\\"></i> " + kbSize + " KB (Hemat Ruang)";
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

function bukaEditSlot2() {
  const s1 = document.getElementById("editSlot1Col");
  const s2 = document.getElementById("editSlot2Col");
  const btn = document.getElementById("editBtnTambahFoto2Box");
  if (s1) { s1.classList.remove("col-12"); s1.classList.add("col-md-6"); }
  if (s2) { s2.classList.remove("d-none"); }
  if (btn) { btn.classList.add("d-none"); }
}

function tutupEditSlot2() {
  const s1 = document.getElementById("editSlot1Col");
  const s2 = document.getElementById("editSlot2Col");
  const btn = document.getElementById("editBtnTambahFoto2Box");
  const f2 = document.getElementById("inputFotoUnit2");
  if (f2) f2.value = "";
  const img2 = document.getElementById("imgFotoPreview2");
  const ph2 = document.getElementById("fotoPlaceholder2");
  if (img2) { img2.src = ""; img2.classList.add("d-none"); }
  if (ph2) { ph2.classList.remove("d-none"); }
  if (s2) { s2.classList.add("d-none"); }
  if (s1) { s1.classList.remove("col-md-6"); s1.classList.add("col-12"); }
  if (btn) { btn.classList.remove("d-none"); }
}
</script>';

render_page('Edit Data Komputer', $body, '', $script);
