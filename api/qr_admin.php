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
        $_SESSION['flash'] = "{$created} token QR baru berhasil di-generate secara otomatis.";
        header('Location: ' . module_url('qr_admin.php', ['cabang'=>$cabangId]));
        exit;
    }

    if ($action === 'regenerate') {
        $assetId = (int)($_POST['asset_id'] ?? 0);
        if ($assetId > 0) {
            regenerate_qr_token($assetId);
            $_SESSION['flash'] = "Token QR berhasil diperbarui. QR lama otomatis di-revoke demi keamanan.";
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
        ? '<span class="badge-chip chip-success font-monospace" style="font-size: 0.72rem;"><i class="bi bi-check-circle-fill"></i> TOKEN VALID</span>'
        : '<span class="badge-chip chip-secondary font-monospace" style="font-size: 0.72rem;"><i class="bi bi-dash-circle"></i> BELUM ADA</span>';

    $action = '<div class="btn-group btn-group-sm">';
    $action .= '<a class="btn btn-outline-secondary" href="'.e(module_url('asset_edit.php', ['id'=>(int)$r['id']])).'" title="Edit Detail Aset"><i class="bi bi-pencil-square"></i></a>';
    if ($url) {
        $action .= '<a class="btn btn-outline-secondary" target="_blank" href="'.e(module_url('print_qr.php', ['asset_id'=>(int)$r['id']])).'" title="Cetak Stiker Label"><i class="bi bi-printer"></i></a>';
    }
    $action .= '</div>';

    $action .= '<form method="post" class="d-inline ms-1">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="regenerate">
      <input type="hidden" name="asset_id" value="'.(int)$r['id'].'">
      <button class="btn btn-sm btn-outline-secondary" title="Regenerate & Revoke Token QR" onclick="return confirm(\'Generate ulang token QR ini? QR lama akan otomatis tidak berlaku lagi.\')"><i class="bi bi-arrow-repeat"></i></button>
    </form>';

    $action .= '<a class="btn btn-sm btn-outline-danger ms-1" href="'.e(module_url('asset_delete.php', ['id'=>(int)$r['id']])).'" title="Hapus Aset" onclick="return confirm(\'Hapus aset '.e(addslashes($r['kode_inventaris'] ?? '')).' ini dari sistem?\')"><i class="bi bi-trash"></i></a>';

    $table .= '<tr style="border-bottom: 1px solid var(--app-border);">
      <td class="text-center font-monospace text-muted small">'.sprintf('%02d', $num).'</td>
      <td>
        <span class="font-monospace fw-bold text-primary" style="font-size: 0.88rem;">'.e($r['kode_inventaris'] ?? '-').'</span>
      </td>
      <td>
        <div class="fw-semibold text-dark">'.e(asset_title($r)).'</div>
        <div class="text-muted small" style="font-size: 0.75rem;">SN: '.e($r['nomor_seri'] ?? '-').' · Tipe: '.e($r['kategori'] ?? 'Komputer').'</div>
      </td>
      <td>
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-person text-secondary"></i>
          <span>'.e($r['karyawan_nama'] ?? '-').'</span>
        </div>
      </td>
      <td><span class="badge-chip chip-secondary">'.e($r['cabang_nama'] ?? '-').'</span></td>
      <td class="text-center">'.$qrStatus.'</td>
      <td class="text-nowrap text-end">'.$action.'</td>
    </tr>';
}
if (!$table) {
    $table = '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-qr-code fs-1 d-block mb-2 opacity-50"></i>Tidak ada inventaris aset yang memerlukan label QR.</td></tr>';
}

$flashHtml = $flash ? '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" style="border-left: 4px solid #10B981 !important;"><i class="bi bi-check-circle-fill me-2 text-success"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$body = '
'.$flashHtml.'

<!-- Header Kicker & Action Bar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <div class="text-uppercase small fw-bold" style="letter-spacing: 0.08em; color: var(--app-accent); font-size: 0.72rem; margin-bottom: 2px;">SYSTEM / QR ASSET LABEL REGISTRY</div>
    <h2 class="h3 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
      <i class="bi bi-qr-code text-primary"></i> Manajemen Label QR Aset
    </h2>
    <div class="text-muted small">Generate token keamanan QR terenkripsi, sinkronisasi token verifikasi inspeksi, dan cetak stiker label bodi fisik perangkat.</div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary fw-semibold btn-sm" href="'.e(module_url('dashboard.php')).'"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
    <a class="btn btn-action-add fw-semibold btn-sm" href="'.e(module_url('asset_add.php')).'"><i class="bi bi-plus-lg me-1"></i> Tambah Aset</a>
    <a class="btn btn-primary fw-semibold btn-sm" target="_blank" href="'.e(module_url('print_qr.php', ['cabang'=>$cabangId])).'"><i class="bi bi-printer-fill me-1"></i> Cetak Stiker QR Cabang Ini</a>
  </div>
</div>

<!-- Toolbar Box -->
<div class="card p-3 mb-4 border shadow-sm" style="border-radius: 8px; border-color: var(--app-border) !important; background: #fff;">
  <div class="row g-3 align-items-end">
    <div class="col-md-7">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-8">
          <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.04em;">Filter Kantor Cabang</label>
          <select class="form-select form-select-sm" name="cabang">
            <option value="0">Semua Kantor Cabang</option>
            '.$opts.'
          </select>
        </div>
        <div class="col-4">
          <button class="btn btn-primary btn-sm w-100 fw-semibold"><i class="bi bi-filter me-1"></i> Terapkan</button>
        </div>
      </form>
    </div>
    <div class="col-md-5">
      <form method="post">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="action" value="generate_missing">
        <button class="btn btn-outline-primary btn-sm w-100 fw-semibold"><i class="bi bi-shield-lock me-1"></i> Batch Generate Token Kosong</button>
      </form>
    </div>
  </div>
</div>

<!-- Table Card -->
<div class="card p-0 border shadow-sm" style="border-radius: 8px; border-color: var(--app-border) !important; background: #fff;">
  <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center" style="border-bottom: 1px solid var(--app-border);">
    <div class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.05em;">
      <i class="bi bi-qr-code-scan text-primary me-2"></i>Daftar Label QR Aset Terdaftar ('.count($rows).' Unit)
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead style="background-color: var(--app-navy); color: #ffffff;">
        <tr>
          <th style="width: 50px; background-color: var(--app-navy); color: #ffffff;" class="text-center font-monospace">NO</th>
          <th style="width: 150px; background-color: var(--app-navy); color: #ffffff;">KODE INVENTARIS</th>
          <th style="background-color: var(--app-navy); color: #ffffff;">DETAIL PERANGKAT</th>
          <th style="background-color: var(--app-navy); color: #ffffff;">PENGGUNA / PIC</th>
          <th style="background-color: var(--app-navy); color: #ffffff;">KANTOR CABANG</th>
          <th style="width: 140px; background-color: var(--app-navy); color: #ffffff;" class="text-center">STATUS TOKEN</th>
          <th style="width: 140px; background-color: var(--app-navy); color: #ffffff;" class="text-end">AKSI</th>
        </tr>
      </thead>
      <tbody>'.$table.'</tbody>
    </table>
  </div>
</div>';

render_page('QR Aset', $body);
