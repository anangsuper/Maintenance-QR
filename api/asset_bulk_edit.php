<?php
require __DIR__ . '/bootstrap.php';
require_login();

// Handle Form POST (Simpan Semua Perubahan Multi-Row Super Cepat & Anti-Timeout)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_bulk') {
    verify_csrf();

    $assetsData = $_POST['assets'] ?? [];
    if (!is_array($assetsData) || empty($assetsData)) {
        $_SESSION['flash_error'] = 'Tidak ada data aset yang dikirim untuk diperbarui.';
        header('Location: ' . module_url('assets.php'));
        exit;
    }

    $res = bulk_update_assets_spreadsheet($assetsData);
    if (!empty($res['success'])) {
        $cnt = (int)($res['updated'] ?? count($assetsData));
        $_SESSION['flash'] = "Berhasil memperbarui data {$cnt} unit komputer sekaligus.";
    } else {
        $_SESSION['flash_error'] = "Gagal memperbarui data: " . ($res['error'] ?? 'Terjadi kesalahan sistem.');
    }

    header('Location: ' . module_url('assets.php'));
    exit;
}

// Ambil daftar ID aset dari GET atau POST
$rawIds = trim((string)($_GET['ids'] ?? $_POST['ids'] ?? ''));
$idList = [];
if ($rawIds !== '') {
    foreach (explode(',', $rawIds) as $item) {
        $cleanId = (int)trim($item);
        if ($cleanId > 0) $idList[] = $cleanId;
    }
}
$idList = array_values(array_unique($idList));

if (empty($idList)) {
    $body = '
    <div class="card p-5 text-center shadow-sm border-0" style="border-radius: 16px;">
      <i class="bi bi-exclamation-circle text-warning fs-1 mb-3"></i>
      <h4 class="fw-bold text-dark">Tidak Ada Aset yang Dipilih</h4>
      <p class="text-secondary mb-4">Silakan kembali ke halaman Registri Aset dan centang (checklist) komputer yang ingin Anda edit secara massal.</p>
      <div>
        <a href="'.e(module_url('assets.php')).'" class="btn btn-primary px-4 fw-semibold">
          <i class="bi bi-arrow-left me-1"></i> Ke Registri Aset
        </a>
      </div>
    </div>';
    render_page('Edit Massal Aset', $body);
    exit;
}

// Ambil data aset yang akan diedit secara batch (1 request anti-timeout)
$assetsToEdit = get_assets_by_ids($idList);

if (empty($assetsToEdit)) {
    $body = '
    <div class="card p-5 text-center shadow-sm border-0" style="border-radius: 16px;">
      <i class="bi bi-x-circle text-danger fs-1 mb-3"></i>
      <h4 class="fw-bold text-dark">Aset Tidak Ditemukan</h4>
      <p class="text-secondary mb-4">Unit komputer yang dipilih tidak ditemukan dalam sistem database.</p>
      <div>
        <a href="'.e(module_url('assets.php')).'" class="btn btn-primary px-4 fw-semibold">
          <i class="bi bi-arrow-left me-1"></i> Ke Registri Aset
        </a>
      </div>
    </div>';
    render_page('Edit Massal Aset', $body);
    exit;
}

// Ambil Master Data
$cabangs = get_cabang_list();
$divisis = get_divisi_list();
$kategoris = get_kategori_list();
$karyawans = get_karyawan_list();

// Opsi Cabang
$optCabang = '';
foreach ($cabangs as $c) {
    $cid = (int)($c['id'] ?? 0);
    $cnama = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cid);
    $optCabang .= '<option value="'.$cid.'">'.e($cnama).'</option>';
}

// Opsi Divisi
$optDivisi = '';
foreach ($divisis as $d) {
    $did = (int)($d['id'] ?? 0);
    $dnama = $d['nama'] ?? $d['nama_divisi'] ?? ('Divisi #' . $did);
    $optDivisi .= '<option value="'.$did.'">'.e($dnama).'</option>';
}

// Opsi Kategori
$optKategori = '';
foreach ($kategoris as $k) {
    $kid = (int)($k['id'] ?? 0);
    $knama = $k['nama'] ?? $k['nama_kategori'] ?? ('Kategori #' . $kid);
    $optKategori .= '<option value="'.$kid.'">'.e($knama).'</option>';
}

// Datalist Karyawan
$datalistKaryawan = '';
$uniqueKaryawan = [];
foreach ($karyawans as $kar) {
    $kn = trim((string)($kar['nama_karyawan'] ?? $kar['nama'] ?? ''));
    if ($kn !== '' && !isset($uniqueKaryawan[$kn])) {
        $uniqueKaryawan[$kn] = true;
        $datalistKaryawan .= '<option value="'.e($kn).'">';
    }
}

// Render baris tabel aset
$tableRows = '';
foreach ($assetsToEdit as $idx => $a) {
    $aid = (int)$a['id'];
    $kode = $a['kode_inventaris'] ?? ('#' . $aid);
    $curCabId = (int)($a['id_cabang'] ?? 0);
    $curDivId = (int)($a['id_divisi'] ?? 0);
    $curKatId = (int)($a['id_kategori'] ?? 0);
    $curStatus = $a['status'] ?? 'Aktif';
    $curPlacement = $a['placement_label'] ?? 'Bodi Casing';
    $curUser = $a['karyawan_nama'] ?? $a['nama_karyawan'] ?? '';
    if ($curUser === '-') $curUser = '';
    $curIp = $a['ip_address'] ?? $a['ip'] ?? '';
    if ($curIp === '-') $curIp = '';
    $curPrinter = $a['printer'] ?? '';
    if ($curPrinter === '-') $curPrinter = '';

    // Dropdown Cabang per baris
    $rowOptCab = '';
    foreach ($cabangs as $c) {
        $cid = (int)($c['id'] ?? 0);
        $cnama = $c['nama'] ?? $c['nama_cabang'] ?? ('Cabang #' . $cid);
        $sel = ($cid === $curCabId) ? ' selected' : '';
        $rowOptCab .= '<option value="'.$cid.'"'.$sel.'>'.e($cnama).'</option>';
    }

    // Dropdown Divisi per baris
    $rowOptDiv = '';
    foreach ($divisis as $d) {
        $did = (int)($d['id'] ?? 0);
        $dnama = $d['nama'] ?? $d['nama_divisi'] ?? ('Divisi #' . $did);
        $sel = ($did === $curDivId) ? ' selected' : '';
        $rowOptDiv .= '<option value="'.$did.'"'.$sel.'>'.e($dnama).'</option>';
    }

    // Dropdown Kategori per baris
    $rowOptKat = '';
    foreach ($kategoris as $k) {
        $kid = (int)($k['id'] ?? 0);
        $knama = $k['nama'] ?? $k['nama_kategori'] ?? ('Kategori #' . $kid);
        $sel = ($kid === $curKatId) ? ' selected' : '';
        $rowOptKat .= '<option value="'.$kid.'"'.$sel.'>'.e($knama).'</option>';
    }

    $tableRows .= '
    <tr id="row-edit-'.$aid.'">
      <td class="text-center text-muted small fw-semibold">'.($idx + 1).'</td>
      <td>
        <div class="fw-bold font-monospace text-primary">'.e($kode).'</div>
        <small class="text-muted" style="font-size: 0.72rem;">ID: #'.$aid.'</small>
      </td>
      <td>
        <input type="text" class="form-control form-control-sm mb-1 fw-semibold" name="assets['.$aid.'][merk]" value="'.e($a['merk'] ?? '').'" placeholder="Merk (HP, Dell...)">
        <input type="text" class="form-control form-control-sm text-secondary" name="assets['.$aid.'][model]" value="'.e($a['model'] ?? '').'" placeholder="Model/Tipe">
      </td>
      <td>
        <input type="text" class="form-control form-control-sm font-monospace" name="assets['.$aid.'][serial_number]" value="'.e($a['serial_number'] ?? '').'" placeholder="Serial Number">
      </td>
      <td>
        <select class="form-select form-select-sm row-cabang" name="assets['.$aid.'][id_cabang]">
          '.$rowOptCab.'
        </select>
      </td>
      <td>
        <select class="form-select form-select-sm row-divisi" name="assets['.$aid.'][id_divisi]">
          '.$rowOptDiv.'
        </select>
      </td>
      <td>
        <input type="text" class="form-control form-control-sm row-karyawan" name="assets['.$aid.'][nama_karyawan]" value="'.e($curUser).'" list="listKaryawan" placeholder="Nama pengguna...">
      </td>
      <td>
        <select class="form-select form-select-sm row-status" name="assets['.$aid.'][status]">
          <option value="Aktif"'.($curStatus === 'Aktif' ? ' selected' : '').'>Aktif</option>
          <option value="Backup"'.($curStatus === 'Backup' ? ' selected' : '').'>Backup</option>
          <option value="Perbaikan"'.($curStatus === 'Perbaikan' ? ' selected' : '').'>Perbaikan</option>
          <option value="Nonaktif"'.($curStatus === 'Nonaktif' ? ' selected' : '').'>Nonaktif</option>
        </select>
      </td>
      <td>
        <input type="text" class="form-control form-control-sm font-monospace" name="assets['.$aid.'][ip_address]" value="'.e($curIp).'" placeholder="192.168...">
      </td>
      <td>
        <input type="text" class="form-control form-control-sm" name="assets['.$aid.'][printer]" value="'.e($curPrinter).'" placeholder="Printer...">
      </td>
      <td>
        <select class="form-select form-select-sm row-placement" name="assets['.$aid.'][placement_label]">
          <option value="Bodi Casing"'.($curPlacement === 'Bodi Casing' ? ' selected' : '').'>Bodi Casing</option>
          <option value="Cover Atas Laptop"'.($curPlacement === 'Cover Atas Laptop' ? ' selected' : '').'>Cover Atas Laptop</option>
          <option value="Samping CPU"'.($curPlacement === 'Samping CPU' ? ' selected' : '').'>Samping CPU</option>
          <option value="Belakang Monitor"'.($curPlacement === 'Belakang Monitor' ? ' selected' : '').'>Belakang Monitor</option>
          <option value="Meja Kerja"'.($curPlacement === 'Meja Kerja' ? ' selected' : '').'>Meja Kerja</option>
          <option value="Badan Printer"'.($curPlacement === 'Badan Printer' ? ' selected' : '').'>Badan Printer</option>
        </select>
      </td>
    </tr>';
}

$body = '
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <div class="text-secondary small mb-1">
      <a href="'.e(module_url('assets.php')).'" class="text-decoration-none text-muted"><i class="bi bi-arrow-left me-1"></i>Kembali ke Registri Aset</a>
    </div>
    <h1 class="h4 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
      <i class="bi bi-pencil-square text-warning"></i> Edit Massal Aset (Spreadsheet Editor)
      <span class="badge bg-primary rounded-pill fs-6 px-3 py-1">'.count($assetsToEdit).' Unit Komputer</span>
    </h1>
    <div class="text-secondary small mt-1">Edit data masing-masing komputer secara bebas pada satu halaman tanpa perlu membuka form satu per satu.</div>
  </div>
  <div class="d-flex gap-2">
    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalQuickAddDivisi">
      <i class="bi bi-plus-circle me-1"></i>+ Tambah Divisi Baru
    </button>
    <a href="'.e(module_url('assets.php')).'" class="btn btn-outline-secondary btn-sm">Batal</a>
    <button type="submit" form="formBulkEditMulti" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm">
      <i class="bi bi-check2-circle me-1"></i> Simpan Semua Perubahan
    </button>
  </div>
</div>

<!-- Quick Fill Helper Toolbar -->
<div class="card p-3 mb-4 bg-light border-0 shadow-sm" style="border-radius: 14px;">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
    <div class="fw-semibold text-dark small d-flex align-items-center gap-1">
      <i class="bi bi-magic text-primary me-1"></i> Terapkan Cepat ke Semua Baris di Bawah (Opsional):
    </div>
    <span class="text-muted small">Fitur cepat untuk menyeragamkan kolom tertentu, setelah itu Anda tetap bebas mengubah tiap baris.</span>
  </div>
  <div class="row g-2 align-items-center">
    <div class="col-lg-3 col-md-6">
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-white small text-muted">Cabang</span>
        <select class="form-select form-select-sm" id="quickFillCabang">
          <option value="">-- Pilih Cabang --</option>
          '.$optCabang.'
        </select>
        <button type="button" class="btn btn-outline-primary" onclick="applyQuickFill(\'cabang\')">Set Semua</button>
      </div>
    </div>
    <div class="col-lg-3 col-md-6">
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-white small text-muted">Divisi</span>
        <select class="form-select form-select-sm" id="quickFillDivisi">
          <option value="">-- Pilih Divisi --</option>
          '.$optDivisi.'
        </select>
        <button type="button" class="btn btn-outline-primary" onclick="applyQuickFill(\'divisi\')">Set Semua</button>
      </div>
    </div>
    <div class="col-lg-3 col-md-6">
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-white small text-muted">Status</span>
        <select class="form-select form-select-sm" id="quickFillStatus">
          <option value="">-- Pilih Status --</option>
          <option value="Aktif">Aktif</option>
          <option value="Backup">Backup</option>
          <option value="Perbaikan">Perbaikan</option>
          <option value="Nonaktif">Nonaktif</option>
        </select>
        <button type="button" class="btn btn-outline-primary" onclick="applyQuickFill(\'status\')">Set Semua</button>
      </div>
    </div>
    <div class="col-lg-3 col-md-6">
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-white small text-muted">Posisi Stiker</span>
        <select class="form-select form-select-sm" id="quickFillPlacement">
          <option value="">-- Posisi Stiker QR --</option>
          <option value="Bodi Casing">Bodi Casing</option>
          <option value="Cover Atas Laptop">Cover Atas Laptop</option>
          <option value="Samping CPU">Samping CPU</option>
          <option value="Belakang Monitor">Belakang Monitor</option>
          <option value="Meja Kerja">Meja Kerja</option>
          <option value="Badan Printer">Badan Printer</option>
        </select>
        <button type="button" class="btn btn-outline-primary" onclick="applyQuickFill(\'placement\')">Set Semua</button>
      </div>
    </div>
  </div>
</div>

<form method="post" id="formBulkEditMulti" action="'.e(module_url('asset_bulk_edit.php')).'">
  <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
  <input type="hidden" name="action" value="save_bulk">
  
  <div class="card shadow-sm border-0 mb-5 overflow-hidden" style="border-radius: 14px;">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" style="min-width: 1250px;">
        <thead class="table-light text-secondary small">
          <tr>
            <th style="width: 45px;" class="text-center">#</th>
            <th style="width: 170px;">Kode & Identitas</th>
            <th style="width: 190px;">Merk / Model</th>
            <th style="width: 140px;">Serial Number</th>
            <th style="width: 170px;">Cabang</th>
            <th style="width: 170px;">Divisi</th>
            <th style="width: 170px;">Pengguna / PIC</th>
            <th style="width: 130px;">Status</th>
            <th style="width: 130px;">IP Address</th>
            <th style="width: 140px;">Printer</th>
            <th style="width: 160px;">Posisi Stiker QR</th>
          </tr>
        </thead>
        <tbody>
          '.$tableRows.'
        </tbody>
      </table>
    </div>
  </div>

  <datalist id="listKaryawan">
    '.$datalistKaryawan.'
  </datalist>

  <!-- Sticky Bottom Save Bar -->
  <div class="position-fixed bottom-0 start-50 translate-middle-x mb-4 shadow-lg rounded-pill px-4 py-2 bg-dark text-white d-flex align-items-center gap-3" style="z-index: 1050; border: 1px solid rgba(255,255,255,0.2);">
    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-warning text-dark rounded-pill px-2 py-1 fw-bold">'.count($assetsToEdit).'</span>
      <span class="small fw-semibold">Unit Sedang Diedit</span>
    </div>
    <div class="vr bg-secondary opacity-50"></div>
    <div class="d-flex gap-2">
      <a href="'.e(module_url('assets.php')).'" class="btn btn-sm btn-outline-light rounded-pill px-3">Batal</a>
      <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-bold shadow-sm" id="btnStickySave">
        <i class="bi bi-check2-circle me-1"></i> Simpan Semua Perubahan
      </button>
    </div>
  </div>
</form>

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
';

$extraScript = '
<script>
// Fungsi Quick-Fill ke semua baris
function applyQuickFill(type) {
  if (type === "cabang") {
    const val = document.getElementById("quickFillCabang").value;
    if (!val) return;
    document.querySelectorAll(".row-cabang").forEach(function(el) { el.value = val; });
  } else if (type === "divisi") {
    const val = document.getElementById("quickFillDivisi").value;
    if (!val) return;
    document.querySelectorAll(".row-divisi").forEach(function(el) { el.value = val; });
  } else if (type === "status") {
    const val = document.getElementById("quickFillStatus").value;
    if (!val) return;
    document.querySelectorAll(".row-status").forEach(function(el) { el.value = val; });
  } else if (type === "placement") {
    const val = document.getElementById("quickFillPlacement").value;
    if (!val) return;
    document.querySelectorAll(".row-placement").forEach(function(el) { el.value = val; });
  }
}

// Bersihkan session storage pilihan aset saat form disubmit berhasil
document.getElementById("formBulkEditMulti")?.addEventListener("submit", function() {
  try {
    sessionStorage.removeItem("asset_registry_selected_assets_v1");
  } catch (e) {}
});

// Quick Add Divisi Handler
document.addEventListener("DOMContentLoaded", function() {
  const modalDivisiEl = document.getElementById("modalQuickAddDivisi");
  const inputQuickNama = document.getElementById("inputQuickNamaDivisi");
  const inputQuickKet = document.getElementById("inputQuickKetDivisi");
  const btnQuickDiv = document.getElementById("btnQuickSubmitDivisi");
  const alertQuickDiv = document.getElementById("quickDivisiAlert");
  const spinnerQuickDiv = document.getElementById("quickDivisiSpinner");
  const iconQuickDiv = document.getElementById("quickDivisiBtnIcon");
  const quickFillDiv = document.getElementById("quickFillDivisi");

  if (modalDivisiEl) {
    modalDivisiEl.addEventListener("shown.bs.modal", function() {
      if (inputQuickNama) {
        inputQuickNama.value = "";
        inputQuickNama.focus();
      }
      if (inputQuickKet) inputQuickKet.value = "";
      if (alertQuickDiv) alertQuickDiv.classList.add("d-none");
    });
  }

  if (btnQuickDiv) {
    btnQuickDiv.addEventListener("click", submitQuickDivisi);
  }

  if (inputQuickNama) {
    inputQuickNama.addEventListener("keydown", function(e) {
      if (e.key === "Enter") {
        e.preventDefault();
        submitQuickDivisi();
      }
    });
  }

  function submitQuickDivisi() {
    const nama = inputQuickNama ? inputQuickNama.value.trim() : "";
    const ket = inputQuickKet ? inputQuickKet.value.trim() : "";

    if (!nama) {
      if (alertQuickDiv) {
        alertQuickDiv.textContent = "Nama divisi tidak boleh kosong.";
        alertQuickDiv.classList.remove("d-none");
      }
      if (inputQuickNama) inputQuickNama.focus();
      return;
    }

    if (alertQuickDiv) alertQuickDiv.classList.add("d-none");
    if (spinnerQuickDiv) spinnerQuickDiv.classList.remove("d-none");
    if (iconQuickDiv) iconQuickDiv.classList.add("d-none");
    if (btnQuickDiv) btnQuickDiv.disabled = true;

    const csrfToken = "'.csrf_token().'";
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
      if (spinnerQuickDiv) spinnerQuickDiv.classList.add("d-none");
      if (iconQuickDiv) iconQuickDiv.classList.remove("d-none");
      if (btnQuickDiv) btnQuickDiv.disabled = false;

      if (!data.success) {
        if (alertQuickDiv) {
          alertQuickDiv.textContent = data.error || "Gagal menambahkan divisi baru.";
          alertQuickDiv.classList.remove("d-none");
        }
        return;
      }

      const newId = String(data.id);
      const newNama = data.nama;

      // Tambahkan ke dropdown quick fill
      if (quickFillDiv) {
        const opt = document.createElement("option");
        opt.value = newId;
        opt.textContent = newNama;
        quickFillDiv.appendChild(opt);
        quickFillDiv.value = newId;
      }

      // Tambahkan ke seluruh dropdown divisi di baris tabel
      document.querySelectorAll(".row-divisi").forEach(function(sel) {
        const opt = document.createElement("option");
        opt.value = newId;
        opt.textContent = newNama;
        sel.appendChild(opt);
      });

      if (modalDivisiEl) {
        const bsModal = bootstrap.Modal.getInstance(modalDivisiEl);
        if (bsModal) bsModal.hide();
      }
    })
    .catch(function(err) {
      if (spinnerQuickDiv) spinnerQuickDiv.classList.add("d-none");
      if (iconQuickDiv) iconQuickDiv.classList.remove("d-none");
      if (btnQuickDiv) btnQuickDiv.disabled = false;
      if (alertQuickDiv) {
        alertQuickDiv.textContent = "Terjadi kesalahan jaringan atau server.";
        alertQuickDiv.classList.remove("d-none");
      }
    });
  }
});
</script>
';

render_page('Edit Massal Aset Komputer', $body, '', $extraScript);
