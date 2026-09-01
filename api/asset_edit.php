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
    ];
}

$error = '';
$successData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $kode = trim((string)($_POST['kode_inventaris'] ?? ''));
    $merk = trim((string)($_POST['merk'] ?? ''));
    $model = trim((string)($_POST['model'] ?? ''));
    $sn = trim((string)($_POST['serial_number'] ?? ''));
    $idKat = (int)($_POST['id_kategori'] ?? 0);
    $idCab = (int)($_POST['id_cabang'] ?? 0);
    $idDiv = (int)($_POST['id_divisi'] ?? 0);
    $namaKar = trim((string)($_POST['nama_karyawan'] ?? ''));
    $placement = trim((string)($_POST['placement_label'] ?? 'Bodi Casing'));
    $status = trim((string)($_POST['status'] ?? 'Aktif'));
    $ket = trim((string)($_POST['keterangan'] ?? ''));

    if ($merk === '' && $model === '') {
        $error = 'Merk atau Model perangkat wajib diisi.';
    } else {
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
        ];

        $res = update_asset($id, $payload);
        if (!empty($res['success'])) {
            $cabangNama = '';
            foreach ($cabangs as $c) {
                if ((int)($c['id'] ?? 0) === $idCab) {
                    $cabangNama = $c['nama_cabang'] ?? $c['nama'] ?? '';
                    break;
                }
            }

            $successData = [
                'asset_id' => $id,
                'kode_inventaris' => $res['kode_inventaris'] ?? $kode,
                'merk' => $merk,
                'model' => $model,
                'qr_token' => $asset['qr_token'] ?? '',
                'cabang_nama' => $cabangNama,
                'karyawan_nama' => $namaKar ?: '-',
            ];
            // Update current asset variable for display
            $asset = get_asset_by_id($id) ?? $asset;
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
          <div class="text-center mb-4">
            <div class="display-5 text-success mb-2"><i class="bi bi-check-circle-fill"></i></div>
            <h3 class="fw-bold text-success">Perubahan Data Berhasil Disimpan!</h3>
            <p class="text-muted">Informasi komputer dan label QR telah diperbarui di sistem.</p>
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
            <a class="btn btn-primary btn-lg" target="_blank" href="'.e($printUrl).'"><i class="bi bi-printer-fill me-1"></i> Cetak Label QR</a>
            <a class="btn btn-outline-secondary btn-lg" href="'.e(module_url('asset_edit.php', ['id' => $id])).'"><i class="bi bi-pencil me-1"></i> Edit Lagi</a>
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

$modeBadge = is_google_cloud_mode() 
    ? '<span class="badge text-bg-info mb-2"><i class="bi bi-google me-1"></i> Penyimpanan: Google Cloud Sheets API v4</span>' 
    : '<span class="badge text-bg-secondary mb-2"><i class="bi bi-database me-1"></i> Penyimpanan: MySQL Database</span>';

$body = '
<div class="row justify-content-center">
  <div class="col-lg-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        '.$modeBadge.'
        <h2 class="fw-bold mb-0 text-dark"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Data Komputer / Aset</h2>
        <div class="text-secondary small">Perbarui informasi perangkat, pemilik, lokasi penempatan, atau status komputer #'.(int)$asset['id'].'.</div>
      </div>
      <a class="btn btn-outline-secondary btn-sm" href="'.e(module_url('qr_admin.php')).'"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    '.$errorHtml.'

    <div class="card p-4 border-0 shadow-sm">
      <form method="post" id="formEditAsset">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="id" value="'.(int)$asset['id'].'">

        <!-- Section 1: Identitas Perangkat -->
        <h5 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-info-circle me-2"></i>1. Identitas Perangkat</h5>
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Kategori Perangkat <span class="text-danger">*</span></label>
            <select class="form-select" name="id_kategori" required>
              '.$optKat.'
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Kode Inventaris</label>
            <div class="input-group">
              <input type="text" class="form-control fw-bold" name="kode_inventaris" id="kodeInventaris" value="'.e($asset['kode_inventaris'] ?? '').'" placeholder="Contoh: INV-IT-006">
              <button class="btn btn-outline-secondary" type="button" onclick="autoGenerateKode()" title="Generate Otomatis"><i class="bi bi-magic me-1"></i>Auto</button>
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Merk Perangkat <span class="text-danger">*</span></label>
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
            <label class="form-label fw-semibold">Tipe / Model <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="model" required value="'.e($asset['model'] ?? '').'" placeholder="Contoh: ThinkPad T14, OptiPlex 3080, Core i5 Rakitan...">
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Nomor Seri (Serial Number / S/N)</label>
            <input type="text" class="form-control" name="serial_number" value="'.e($asset['serial_number'] ?? '').'" placeholder="Contoh: SN-892347293 (atau - jika tidak ada)">
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Status Aset</label>
            <select class="form-select" name="status">
              <option value="Aktif"'.($curStatus === 'Aktif' ? ' selected' : '').'>Aktif (Digunakan)</option>
              <option value="Backup"'.($curStatus === 'Backup' ? ' selected' : '').'>Backup / Cadangan</option>
              <option value="Perbaikan"'.($curStatus === 'Perbaikan' ? ' selected' : '').'>Sedang Dalam Perbaikan</option>
              <option value="Nonaktif"'.($curStatus === 'Nonaktif' ? ' selected' : '').'>Nonaktif</option>
            </select>
          </div>
        </div>

        <!-- Section 2: Penempatan & Pemilik -->
        <h5 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-geo-alt me-2"></i>2. Lokasi & Penanggung Jawab</h5>
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Cabang / Lokasi <span class="text-danger">*</span></label>
            <select class="form-select" name="id_cabang" required>
              '.$optCab.'
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Divisi / Unit Kerja <span class="text-danger">*</span></label>
            <select class="form-select" name="id_divisi" required>
              '.$optDiv.'
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Pengguna / Pemilik Komputer</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-person"></i></span>
              <input type="text" class="form-control" name="nama_karyawan" id="inputNamaKaryawan" list="listKaryawan" value="'.e($curKarName).'" placeholder="Pilih nama atau ketik nama pemilik..." autocomplete="off">
            </div>
            <datalist id="listKaryawan">
              '.$datalistKaryawan.'
            </datalist>
            <div class="form-text mt-1">
              Pilihan cepat:
              <div class="mt-1">
                '.$badgeKaryawan.'
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Posisi Stiker QR</label>
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
        </div>

        <!-- Section 3: Spesifikasi & Catatan -->
        <h5 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-card-text me-2"></i>3. Spesifikasi & Keterangan Tambahan</h5>
        <div class="row g-3 mb-4">
          <div class="col-12">
            <label class="form-label fw-semibold">Spesifikasi / Catatan Hardware & OS</label>
            <textarea class="form-control" name="keterangan" rows="3" placeholder="Contoh: Core i5-12400 / RAM 16GB / SSD 512GB NVMe / Windows 11 Pro">'.e($asset['keterangan'] ?? '').'</textarea>
          </div>
        </div>

        <!-- Submit Buttons -->
        <div class="d-flex justify-content-between gap-2 pt-3 border-top">
          <a class="btn btn-outline-secondary px-4" href="'.e(module_url('qr_admin.php')).'">Batal</a>
          <button type="submit" class="btn btn-primary px-4 fw-semibold"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
        </div>
      </form>
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
</script>';

render_page('Edit Data Komputer', $body, '', $script);
