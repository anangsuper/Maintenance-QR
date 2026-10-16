<?php
require __DIR__ . '/bootstrap.php';
require_login();

$q = trim((string)($_GET['q'] ?? ''));
$filterType = strtolower(trim((string)($_GET['type'] ?? 'all')));
if (!in_array($filterType, ['all', 'komputer', 'inventaris'], true)) {
    $filterType = 'all';
}

$allMutations = get_asset_mutations(['q' => $q]);
if ($filterType !== 'all') {
    $mutations = array_values(array_filter($allMutations, fn($m) => ($m['asset_type'] ?? 'komputer') === $filterType));
} else {
    $mutations = $allMutations;
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$flashHtml = $flash ? '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" style="border-left: 4px solid #16A34A !important;"><i class="bi bi-check-circle-fill me-2 text-success"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$tableRows = '';
if (empty($mutations)) {
    $tableRows = '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-arrow-left-right fs-1 d-block mb-2 opacity-50"></i>Belum ada data mutasi aset yang sesuai filter.</td></tr>';
} else {
    $no = 1;
    foreach ($mutations as $m) {
        $mid = (int)$m['id'];
        $bast = e($m['nomor_bast']);
        $tgl = date('d/m/Y H:i', strtotime($m['tanggal_mutasi'] ?? $m['created_at']));
        $kode = e($m['kode_aset']);
        $nama = e($m['nama_aset']);
        $type = ($m['asset_type'] ?? 'komputer');
        $isInv = ($type === 'inventaris');
        $typeBadge = $isInv 
            ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5" style="font-size:0.65rem;"><i class="bi bi-archive-fill me-1"></i>INVENTARIS</span>'
            : '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0.5" style="font-size:0.65rem;"><i class="bi bi-pc-display me-1"></i>KOMPUTER IT</span>';

        $asal = e($m['cabang_asal']) . ($m['ruangan_asal'] ? ' <small class="text-muted">(' . e($m['ruangan_asal']) . ')</small>' : '');
        $tujuan = e($m['cabang_tujuan']) . ($m['ruangan_tujuan'] ? ' <small class="text-muted">(' . e($m['ruangan_tujuan']) . ')</small>' : '');
        $pengguna = '<span class="text-muted">' . e($m['pengguna_asal'] ?: 'Umum') . '</span> &rarr; <strong class="text-dark">' . e($m['pengguna_tujuan'] ?: 'Umum') . '</strong>';
        $alasan = e($m['alasan_mutasi']);
        $kondisi = !empty($m['kondisi_fisik']) ? '<span class="badge bg-light text-secondary border small ms-1">' . e($m['kondisi_fisik']) . '</span>' : '';
        $printUrl = module_url('print_bast.php', ['id' => $mid]);

        $tableRows .= '
        <tr>
          <td class="text-center font-monospace text-muted small">'.$no++.'</td>
          <td>
            <div class="mb-1">'.$typeBadge.'</div>
            <a href="'.$printUrl.'" target="_blank" class="fw-bold '.($isInv ? 'text-success' : 'text-primary').' font-monospace text-decoration-none">
              <i class="bi bi-file-earmark-text me-1"></i>'.$bast.'
            </a>
            <div class="small text-muted" style="font-size:0.75rem;">'.$tgl.'</div>
          </td>
          <td>
            <div class="fw-bold text-dark font-monospace small">'.$kode.'</div>
            <div class="small text-secondary">'.$nama.'</div>
          </td>
          <td>
            <div class="small">
              <span class="badge bg-secondary-subtle text-secondary border mb-1">Asal:</span> '.$asal.'
            </div>
            <div class="small mt-1">
              <span class="badge '.($isInv ? 'bg-success-subtle text-success border-success-subtle' : 'bg-primary-subtle text-primary border-primary-subtle').' border mb-1">Tujuan:</span> <strong>'.$tujuan.'</strong>
            </div>
          </td>
          <td class="small">'.$pengguna.'</td>
          <td>
            <span class="badge bg-light text-dark border small fw-normal">'.$alasan.'</span>'.$kondisi.'
            '.(!empty($m['petugas_nama']) ? '<div class="small text-muted mt-1" style="font-size: 0.7rem;"><i class="bi bi-person me-1"></i>'.e($m['petugas_nama']).'</div>' : '').'
          </td>
          <td class="text-end text-nowrap">
            <a href="'.$printUrl.'" target="_blank" class="btn btn-sm '.($isInv ? 'btn-outline-success' : 'btn-outline-primary').' fw-semibold px-2.5 py-1">
              <i class="bi bi-printer me-1"></i> Cetak BAST
            </a>
          </td>
        </tr>';
    }
}

$title = 'Riwayat Mutasi & Serah Terima Aset';
$body = '
'.$flashHtml.'

<!-- Header Kicker & Action Bar -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <div class="tech-label mb-1">ASSET MANAGEMENT &amp; GOVERNANCE</div>
    <h1 class="h3 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
      <i class="bi bi-arrow-left-right text-primary"></i> Riwayat Mutasi &amp; Serah Terima Aset
    </h1>
    <p class="text-secondary small mb-0">Arsip pencatatan perpindahan fisik komputer, rotasi pemegang aset, dan dokumen resmi Berita Acara Serah Terima (BAST).</p>
  </div>
  <div class="d-flex flex-wrap align-items-center gap-2">
    <a class="btn btn-outline-secondary fw-semibold btn-sm px-3 bg-white" href="'.e(module_url('dashboard.php', ['tab' => 'kartu'])).'">
      <i class="bi bi-card-heading me-1"></i> Data Kartu
    </a>
    <a class="btn btn-outline-secondary fw-semibold btn-sm px-3 bg-white" href="'.e(module_url('assets.php')).'">
      <i class="bi bi-pc-display me-1"></i> Data Komputer
    </a>
    <a class="btn btn-success fw-bold btn-sm px-3 shadow-xs d-inline-flex align-items-center gap-1.5" href="'.e(module_url('inventaris_mutation.php')).'">
      <i class="bi bi-archive-fill"></i> Mutasi Inventaris
    </a>
    <a class="btn btn-primary fw-bold btn-sm px-3 shadow-sm d-inline-flex align-items-center gap-1.5" href="'.e(module_url('asset_mutation.php')).'">
      <i class="bi bi-pc-display"></i> Mutasi Komputer
    </a>
  </div>
</div>

<!-- Search & Filter Bar -->
<div class="card p-3 mb-4 border-0 shadow-sm" style="border-radius: 14px;">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="btn-group btn-group-sm" role="group">
      <a href="'.e(module_url('asset_mutations.php', ['type' => 'all', 'q' => $q])).'" class="btn '.($filterType === 'all' ? 'btn-dark fw-bold' : 'btn-outline-secondary bg-white').'">Semua Mutasi</a>
      <a href="'.e(module_url('asset_mutations.php', ['type' => 'komputer', 'q' => $q])).'" class="btn '.($filterType === 'komputer' ? 'btn-primary fw-bold' : 'btn-outline-secondary bg-white').'"><i class="bi bi-pc-display me-1"></i>Komputer IT</a>
      <a href="'.e(module_url('asset_mutations.php', ['type' => 'inventaris', 'q' => $q])).'" class="btn '.($filterType === 'inventaris' ? 'btn-success fw-bold' : 'btn-outline-secondary bg-white').'"><i class="bi bi-archive-fill me-1"></i>Kartu Inventaris</a>
    </div>
    <span class="text-muted small">Menampilkan <strong>'.count($mutations).'</strong> mutasi tercatat</span>
  </div>

  <form method="get" class="d-flex gap-2">
    <input type="hidden" name="type" value="'.e($filterType).'">
    <div class="input-group">
      <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
      <input type="text" name="q" class="form-control border-start-0 ps-0" placeholder="Cari nomor BAST, kode aset / nomor rekening, nama barang/komputer, atau cabang tujuan..." value="'.e($q).'">
    </div>
    <button type="submit" class="btn btn-primary px-3 fw-semibold">Cari</button>
    '.($q ? '<a href="'.e(module_url('asset_mutations.php', ['type' => $filterType])).'" class="btn btn-light border px-3">Reset</a>' : '').'
  </form>
</div>

<!-- Table Card -->
<div class="card p-0 overflow-hidden shadow-sm border-0" style="border-radius: 14px;">
  <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
    <div class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.05em;">
      <i class="bi bi-journal-check text-primary me-2"></i>Daftar Arsip Mutasi Aset ('.count($mutations).')
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th style="width: 50px;" class="text-center font-monospace">NO</th>
          <th style="width: 220px;">NO. BAST &amp; TANGGAL</th>
          <th>ASET YANG DIMUTASI</th>
          <th>PERPINDAHAN CABANG &amp; LOKASI</th>
          <th>MUTASI PENGGUNA</th>
          <th>ALASAN MUTASI</th>
          <th style="width: 130px;" class="text-end">DOKUMEN</th>
        </tr>
      </thead>
      <tbody>
        '.$tableRows.'
      </tbody>
    </table>
  </div>
</div>';

render_page($title, $body);
