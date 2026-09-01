<?php
require __DIR__ . '/bootstrap.php';
require_admin();

$cabangId = max(0, (int)($_GET['cabang'] ?? 0));
$cName = name_column('cabang') ?: 'id';
$cabangs = db()->query("SELECT id, `{$cName}` AS nama FROM cabang ORDER BY `{$cName}`")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_missing') {
        $where = " WHERE (a.status = 'Aktif' OR a.status = 'aktif' OR a.status IS NULL OR a.status = '') ";
        $params = [];
        if ($cabangId) {
            $where .= " AND a.id_cabang = ? ";
            $params[] = $cabangId;
        }
        $st = db()->prepare("
            SELECT a.id FROM assets a
            LEFT JOIN asset_qr_tokens q ON q.asset_id = a.id
            {$where} AND q.id IS NULL
        ");
        $st->execute($params);
        $ids = $st->fetchAll(PDO::FETCH_COLUMN);

        $ins = db()->prepare("INSERT IGNORE INTO asset_qr_tokens (asset_id, token) VALUES (?, ?)");
        $created = 0;
        foreach ($ids as $id) {
            $ins->execute([(int)$id, bin2hex(random_bytes(16))]);
            $created += $ins->rowCount();
        }
        $_SESSION['flash'] = "{$created} QR baru berhasil dibuat.";
        header('Location: ' . module_url('qr_admin.php', ['cabang'=>$cabangId]));
        exit;
    }

    if ($action === 'regenerate') {
        $assetId = (int)($_POST['asset_id'] ?? 0);
        if ($assetId > 0) {
            $new = bin2hex(random_bytes(16));
            $st = db()->prepare("
                INSERT INTO asset_qr_tokens (asset_id, token, is_active)
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE token = VALUES(token), is_active = 1
            ");
            $st->execute([$assetId, $new]);
            $_SESSION['flash'] = "QR aset berhasil dibuat ulang. QR lama otomatis tidak berlaku.";
        }
        header('Location: ' . module_url('qr_admin.php', ['cabang'=>$cabangId]));
        exit;
    }
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$where = " WHERE (a.status = 'Aktif' OR a.status = 'aktif' OR a.status IS NULL OR a.status = '') ";
$params = [];
if ($cabangId) {
    $where .= " AND a.id_cabang = ? ";
    $params[] = $cabangId;
}

$st = db()->prepare(asset_query_base() . $where . " ORDER BY cabang_nama, karyawan_nama, a.kode_inventaris LIMIT 1000");
$st->execute($params);
$rows = $st->fetchAll();

$opts = '';
foreach ($cabangs as $c) {
    $opts .= '<option value="'.(int)$c['id'].'"'.((int)$c['id']===$cabangId?' selected':'').'>'.e($c['nama']).'</option>';
}

$table = '';
foreach ($rows as $r) {
    $url = !empty($r['qr_token']) ? module_url('scan.php', ['t'=>$r['qr_token']]) : '';
    $qrStatus = $url
        ? '<span class="badge text-bg-success">Siap</span>'
        : '<span class="badge text-bg-secondary">Belum dibuat</span>';

    $action = '';
    if ($url) {
        $action .= '<a class="btn btn-sm btn-outline-primary" target="_blank" href="'.e(module_url('print_qr.php', ['asset_id'=>(int)$r['id']])).'">Cetak</a> ';
    }
    $action .= '<form method="post" class="d-inline">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="regenerate">
      <input type="hidden" name="asset_id" value="'.(int)$r['id'].'">
      <button class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Buat ulang QR? QR lama akan tidak berlaku.\')">Buat Ulang</button>
    </form>';

    $table .= '<tr>
      <td>'.e($r['kode_inventaris'] ?? '-').'</td>
      <td>'.e(asset_title($r)).'</td>
      <td>'.e($r['karyawan_nama'] ?? '-').'</td>
      <td>'.e($r['cabang_nama'] ?? '-').'</td>
      <td>'.$qrStatus.'</td>
      <td class="text-nowrap">'.$action.'</td>
    </tr>';
}
if (!$table) $table = '<tr><td colspan="6" class="text-center py-4 text-secondary">Tidak ada aset.</td></tr>';

$flashHtml = $flash ? '<div class="alert alert-success">'.e($flash).'</div>' : '';

$body = $flashHtml.'
<div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-3">
  <div>
    <h2 class="mb-1">QR Aset</h2>
    <div class="text-secondary">Generate dan cetak QR untuk ditempel pada meja, CPU, atau laptop.</div>
  </div>
  <a class="btn btn-outline-primary" target="_blank" href="'.e(module_url('print_qr.php', ['cabang'=>$cabangId])).'">Cetak Semua QR</a>
</div>

<div class="card p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-md-6">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-8">
          <label class="form-label">Cabang</label>
          <select class="form-select" name="cabang"><option value="0">Semua Cabang</option>'.$opts.'</select>
        </div>
        <div class="col-4"><button class="btn btn-primary w-100">Filter</button></div>
      </form>
    </div>
    <div class="col-md-6">
      <form method="post">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="action" value="generate_missing">
        <button class="btn btn-success w-100">Generate QR yang Belum Ada</button>
      </form>
    </div>
  </div>
</div>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Kode</th><th>Perangkat</th><th>Pemilik</th><th>Cabang</th><th>QR</th><th>Aksi</th></tr></thead>
      <tbody>'.$table.'</tbody>
    </table>
  </div>
</div>';

render_page('QR Aset', $body);
