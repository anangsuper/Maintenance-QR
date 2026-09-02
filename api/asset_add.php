<?php
require __DIR__ . '/bootstrap.php';
require_login();

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

        $res = create_new_asset($payload);
        if (!empty($res['success'])) {
            $cabangNama = '';
            foreach ($cabangs as $c) {
                if ((int)($c['id'] ?? 0) === $idCab) {
                    $cabangNama = $c['nama_cabang'] ?? $c['nama'] ?? '';
                    break;
                }
            }

            $successData = [
                'asset_id' => $res['asset_id'],
                'kode_inventaris' => $res['kode_inventaris'],
                'merk' => $merk,
                'model' => $model,
                'qr_token' => $res['qr_token'] ?? '',
                'cabang_nama' => $cabangNama,
                'karyawan_nama' => $namaKar ?: '-',
            ];
        } else {
            $error = $res['error'] ?? 'Terjadi kesalahan saat menyimpan aset baru.';
        }
    }
}

// Tampilan Sukses Setelah Input
if ($successData) {
    $printUrl = module_url('print_qr.php', ['asset_id' => $successData['asset_id']]);

    $body = '
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="card p-4 border-0 shadow-sm">
          <div class="text-center mb-4">
            <div class="display-5 text-success mb-2"><i class="bi bi-check-circle-fill"></i></div>
            <h3 class="fw-bold text-success">Komputer / Aset Berhasil Didaftarkan!</h3>
            <p class="text-muted">Data aset dan Token QR otomatis dibuat dan siap digunakan.</p>
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
              <dd class="col-sm-8 mb-2">'.e($successData['cabang_nama'] ?: '-').'</dd>

              <dt class="col-sm-4 text-secondary">QR Token</dt>
              <dd class="col-sm-8 mb-0"><code class="text-dark bg-white px-2 py-1 border rounded">'.e($successData['qr_token']).'</code></dd>
            </dl>
          </div>

          <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
            <a class="btn btn-primary btn-lg" target="_blank" href="'.e($printUrl).'"><i class="bi bi-printer-fill me-1"></i> Cetak Label QR Sekarang</a>
            <a class="btn btn-outline-success btn-lg" href="'.e(module_url('asset_add.php')).'"><i class="bi bi-plus-circle me-1"></i> Tambah Komputer Lagi</a>
          </div>

          <hr class="my-4">

          <div class="d-flex justify-content-between flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="'.e(module_url('qr_admin.php')).'"><i class="bi bi-qr-code me-1"></i> Ke Daftar QR Aset</a>
            <a class="btn btn-sm btn-outline-primary" href="'.e(module_url('dashboard.php')).'"><i class="bi bi-speedometer2 me-1"></i> Ke Dashboard Maintenance</a>
          </div>
        </div>
      </div>
    </div>';

    render_page('Aset Berhasil Ditambahkan', $body);
    exit;
}

// Opsi Dropdown Kategori
$optKat = '';
foreach ($kategoris as $kat) {
    $kId = (int)($kat['id'] ?? 0);
    $kNama = $kat['nama_kategori'] ?? $kat['nama'] ?? 'Kategori #' . $kId;
    $optKat .= '<option value="'.$kId.'">'.e($kNama).'</option>';
}

// Opsi Dropdown Cabang
$optCab = '';
foreach ($cabangs as $cab) {
    $cId = (int)($cab['id'] ?? 0);
    $cNama = $cab['nama_cabang'] ?? $cab['nama'] ?? 'Cabang #' . $cId;
    $optCab .= '<option value="'.$cId.'">'.e($cNama).'</option>';
}

// Opsi Dropdown Divisi
$optDiv = '';
foreach ($divisis as $div) {
    $dId = (int)($div['id'] ?? 0);
    $dNama = $div['nama_divisi'] ?? $div['nama'] ?? 'Divisi #' . $dId;
    $optDiv .= '<option value="'.$dId.'">'.e($dNama).'</option>';
}

// Daftar saran Karyawan untuk Datalist
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
        <h2 class="fw-bold mb-0 text-dark"><i class="bi bi-pc-display me-2 text-primary"></i>Tambah Komputer / Aset Baru</h2>
        <div class="text-secondary small">Daftarkan komputer atau perangkat baru untuk langsung dibuatkan label QR maintenance.</div>
      </div>
      <a class="btn btn-outline-secondary btn-sm" href="'.e(module_url('qr_admin.php')).'"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    '.$errorHtml.'

    <div class="card p-4 border-0 shadow-sm">
      <form method="post" id="formAddAsset">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">

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
              <input type="text" class="form-control" name="kode_inventaris" id="kodeInventaris" placeholder="Contoh: INV-IT-006 (Kosongkan utk auto)">
              <button class="btn btn-outline-secondary" type="button" onclick="autoGenerateKode()" title="Generate Otomatis"><i class="bi bi-magic me-1"></i>Auto</button>
            </div>
            <div class="form-text">Biarkan kosong jika ingin dibuatkan otomatis oleh sistem.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Merk Perangkat <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="merk" list="listMerk" required placeholder="Contoh: Lenovo, Dell, HP, Asus, Acer, Rakitan...">
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
            <input type="text" class="form-control" name="model" required placeholder="Contoh: ThinkPad T14, OptiPlex 3080, Core i5 Rakitan...">
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Nomor Seri (Serial Number / S/N)</label>
            <input type="text" class="form-control" name="serial_number" placeholder="Contoh: SN-892347293 (atau - jika tidak ada)">
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Status Aset</label>
            <select class="form-select" name="status">
              <option value="Aktif" selected>Aktif (Digunakan)</option>
              <option value="Backup">Backup / Cadangan</option>
              <option value="Perbaikan">Sedang Dalam Perbaikan</option>
              <option value="Nonaktif">Nonaktif</option>
            </select>
          </div>
        </div>

        <!-- Section 2: Penempatan & Pemilik -->
        <h5 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-geo-alt me-2"></i>2. Lokasi & Penanggung Jawab</h5>
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Cabang / Lokasi <span class="text-danger">*</span> <a href="'.e(module_url('cabang_admin.php')).'" class="small text-primary text-decoration-none float-end" target="_blank">+ Tambah Cabang Baru</a></label>
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
              <input type="text" class="form-control" name="nama_karyawan" id="inputNamaKaryawan" list="listKaryawan" placeholder="Pilih nama atau ketik nama pemilik baru..." autocomplete="off">
            </div>
            <datalist id="listKaryawan">
              '.$datalistKaryawan.'
            </datalist>
            <div class="form-text mt-1">
              Bisa ketik nama pemilik baru langsung atau klik pilihan cepat:
              <div class="mt-1">
                '.$badgeKaryawan.'
                <button type="button" class="btn btn-sm btn-outline-primary border py-0 px-2 mb-1" onclick="document.getElementById(\'inputNamaKaryawan\').focus()">+ Ketik Nama Lain</button>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Rencana Posisi Stiker QR</label>
            <input type="text" class="form-control" name="placement_label" list="listPlacement" value="Bodi Casing" placeholder="Posisi stiker ditempel">
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
            <textarea class="form-control" name="keterangan" rows="3" placeholder="Contoh: Core i5-12400 / RAM 16GB / SSD 512GB NVMe / Windows 11 Pro / Microsoft Office 2021"></textarea>
            <div class="form-text">Catatan spesifikasi ini akan membantu teknisi saat melakukan maintenance rutin.</div>
          </div>
        </div>

        <!-- Submit Buttons -->
        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
          <a class="btn btn-outline-secondary px-4" href="'.e(module_url('qr_admin.php')).'">Batal</a>
          <button type="submit" class="btn btn-primary px-4 fw-semibold"><i class="bi bi-save me-1"></i> Simpan & Buat QR</button>
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

render_page('Tambah Komputer / Aset Baru', $body, '', $script);
