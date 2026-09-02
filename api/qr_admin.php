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
        $_SESSION['flash'] = "{$created} QR baru berhasil dibuat.";
        header('Location: ' . module_url('qr_admin.php', ['cabang'=>$cabangId]));
        exit;
    }

    if ($action === 'regenerate') {
        $assetId = (int)($_POST['asset_id'] ?? 0);
        if ($assetId > 0) {
            regenerate_qr_token($assetId);
            $_SESSION['flash'] = "QR aset berhasil dibuat ulang. QR lama otomatis tidak berlaku.";
        }
        header('Location: ' . module_url('qr_admin.php', ['cabang'=>$cabangId]));
        exit;
    }
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$rows = get_qr_admin_rows($cabangId);

$opts = '';
foreach ($cabangs as $c) {
    $cId = (int)($c['id'] ?? 0);
    $cNama = $c['nama'] ?? $c['nama_cabang'] ?? 'Cabang #' . $cId;
    $opts .= '<option value="'.$cId.'"'.($cId===$cabangId?' selected':'').'>'.e($cNama).'</option>';
}

$table = '';
$num = 0;
foreach ($rows as $r) {
    $num++;
    $url = !empty($r['qr_token']) ? module_url('scan.php', ['t'=>$r['qr_token']]) : '';
    $qrStatus = $url
        ? '<span class="badge-chip chip-success"><i class="bi bi-check-circle-fill"></i> Siap Cetak</span>'
        : '<span class="badge-chip chip-secondary"><i class="bi bi-dash-circle"></i> Belum Dibuat</span>';

    $action = '<a class="btn btn-sm btn-outline-secondary me-1" href="'.e(module_url('asset_edit.php', ['id'=>(int)$r['id']])).'"><i class="bi bi-pencil-square me-1"></i> Edit</a> ';
    if ($url) {
        $action .= '<a class="btn btn-sm btn-outline-primary me-1" target="_blank" href="'.e(module_url('print_qr.php', ['asset_id'=>(int)$r['id']])).'"><i class="bi bi-printer me-1"></i> Cetak QR</a> ';
    }
    $action .= '<form method="post" class="d-inline">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="regenerate">
      <input type="hidden" name="asset_id" value="'.(int)$r['id'].'">
      <button class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Buat ulang QR? QR lama akan tidak berlaku.\')"><i class="bi bi-arrow-repeat"></i></button>
    </form>';

    $table .= '<tr>
      <td class="text-center text-muted">'.$num.'</td>
      <td><strong class="text-primary fs-6">'.e($r['kode_inventaris'] ?? '-').'</strong></td>
      <td class="fw-semibold text-dark">'.e(asset_title($r)).'</td>
      <td><span class="d-inline-flex align-items-center gap-1"><i class="bi bi-person-circle text-secondary"></i> '.e($r['karyawan_nama'] ?? '-').'</span></td>
      <td><span class="badge-chip chip-secondary">'.e($r['cabang_nama'] ?? '-').'</span></td>
      <td class="text-center">'.$qrStatus.'</td>
      <td class="text-nowrap text-end">'.$action.'</td>
    </tr>';
}
if (!$table) {
    $table = '<tr><td colspan="7" class="text-center py-5 text-secondary"><i class="bi bi-qr-code fs-1 d-block mb-2 opacity-50"></i>Tidak ada aset yang ditemukan.</td></tr>';
}

$flashHtml = $flash ? '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm"><i class="bi bi-check-circle-fill me-2"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$body = '
'.$flashHtml.'
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1 text-dark"><i class="bi bi-qr-code text-primary me-2"></i>Manajemen QR Aset IT</h2>
    <div class="text-secondary">Generate token QR dan cetak stiker barcode untuk bodi laptop, PC, monitor, atau printer.</div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-action-add fw-bold" href="'.e(module_url('asset_add.php')).'"><i class="bi bi-plus-lg me-1"></i> Tambah Komputer</a>
    <a class="btn btn-primary fw-semibold px-3" target="_blank" href="'.e(module_url('print_qr.php', ['cabang'=>$cabangId])).'"><i class="bi bi-printer-fill me-1"></i> Cetak Semua Stiker QR</a>
  </div>
</div>

<div class="card p-3 mb-4 border-0 shadow-sm">
  <div class="row g-3 align-items-end">
    <div class="col-md-7">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-8">
          <label class="form-label small fw-bold text-secondary">Filter Cabang / Lokasi</label>
          <select class="form-select" name="cabang"><option value="0">Semua Cabang</option>'.$opts.'</select>
        </div>
        <div class="col-4"><button class="btn btn-primary w-100 fw-bold py-2"><i class="bi bi-filter me-1"></i> Filter</button></div>
      </form>
    </div>
    <div class="col-md-5">
      <form method="post">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="action" value="generate_missing">
        <button class="btn btn-success w-100 fw-bold py-2"><i class="bi bi-magic me-1"></i> Generate QR yang Belum Ada</button>
      </form>
    </div>
  </div>
</div>

<div class="card p-4 border-0 shadow-sm">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-card-checklist text-primary me-2"></i>Daftar Stiker QR Aset ('.count($rows).' Unit)</h5>
  </div>
  <div class="table-responsive rounded-3 border">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th style="width: 40px;" class="text-center">No</th>
          <th>Kode Inventaris</th>
          <th>Perangkat</th>
          <th>Pengguna / Pemilik</th>
          <th>Cabang</th>
          <th class="text-center">Status QR</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>'.$table.'</tbody>
    </table>
  </div>
</div>';

render_page('QR Aset', $body);
