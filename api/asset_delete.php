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
    $_SESSION['flash'] = 'Aset dengan ID #' . $id . ' tidak ditemukan atau sudah dihapus.';
    header('Location: ' . module_url('qr_admin.php'));
    exit;
}

$kode = $asset['kode_inventaris'] ?? ('#' . $id);
$namaPerangkat = trim(($asset['merk'] ?? '') . ' ' . ($asset['model'] ?? ''));

// Handle POST deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $res = delete_asset($id);
    if (!empty($res['success'])) {
        $_SESSION['flash'] = "Aset \"{$kode} - {$namaPerangkat}\" berhasil dihapus dari sistem.";
        $redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : module_url('qr_admin.php');
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = $res['error'] ?? 'Gagal menghapus aset.';
    }
}

// Tampilan Konfirmasi Hapus (GET)
$body = '
<div class="row justify-content-center">
  <div class="col-lg-6 col-md-8">
    <div class="card p-4 p-md-5 border-0 shadow-sm text-center">
      <div class="mb-3">
        <span class="d-inline-flex p-3 rounded-circle bg-danger bg-opacity-10 text-danger fs-1">
          <i class="bi bi-trash3-fill"></i>
        </span>
      </div>
      <h3 class="fw-bold text-danger mb-2">Hapus Aset Komputer?</h3>
      <p class="text-secondary mb-4">Apakah Anda yakin ingin menghapus data aset berikut dari sistem?</p>

      <div class="bg-light p-3 rounded-3 text-start mb-4 border">
        <div class="row g-2 small">
          <div class="col-5 text-muted">Kode Inventaris:</div>
          <div class="col-7 fw-bold text-primary">'.e($kode).'</div>
          <div class="col-5 text-muted">Perangkat:</div>
          <div class="col-7 fw-semibold text-dark">'.e($namaPerangkat).'</div>
          <div class="col-5 text-muted">Serial Number:</div>
          <div class="col-7">'.e($asset['serial_number'] ?? '-').'</div>
          <div class="col-5 text-muted">Pengguna:</div>
          <div class="col-7">'.e($asset['karyawan_nama'] ?? '-').'</div>
          <div class="col-5 text-muted">Cabang:</div>
          <div class="col-7">'.e($asset['cabang_nama'] ?? '-').'</div>
        </div>
      </div>

      '.(!empty($error) ? '<div class="alert alert-danger py-2 mb-3">'.e($error).'</div>' : '').'

      <div class="d-flex gap-2 justify-content-center">
        <a class="btn btn-outline-secondary px-4 py-2" href="'.e(module_url('qr_admin.php')).'"><i class="bi bi-x-lg me-1"></i> Batal</a>
        <form method="post" class="d-inline">
          <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
          <input type="hidden" name="id" value="'.$id.'">
          <input type="hidden" name="redirect" value="'.e(module_url('qr_admin.php')).'">
          <button type="submit" class="btn btn-danger px-4 py-2 fw-bold">
            <i class="bi bi-trash3-fill me-1"></i> Ya, Hapus Sekarang
          </button>
        </form>
      </div>
    </div>
  </div>
</div>';

render_page('Hapus Aset · ' . $kode, $body);
