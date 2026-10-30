<?php
require __DIR__ . '/bootstrap.php';
require_login();

ensure_photo_trash_and_dual_photo_schema();
$autoPurgedCount = purge_expired_photo_trash();

// Proses Aksi POST (Restore / Hapus Permanen / Restore Semua)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = trim((string)($_POST['action'] ?? ''));
    $redirectTab = trim((string)($_POST['return_source'] ?? $_GET['source'] ?? 'all'));
    $redirectQ = trim((string)($_POST['return_q'] ?? $_GET['q'] ?? ''));
    $redirectParams = array_filter([
        'source' => $redirectTab !== 'all' ? $redirectTab : null,
        'q'      => $redirectQ !== '' ? $redirectQ : null,
    ]);

    if ($action === 'restore_photo') {
        $trashId = (int)($_POST['trash_id'] ?? 0);
        if ($trashId > 0) {
            $res = restore_photo_from_trash($trashId);
            set_flash($res['ok'] ? 'success' : 'danger', $res['message']);
        }
        redirect(module_url('photo_trash.php', $redirectParams));
    }

    if ($action === 'delete_permanent') {
        $trashId = (int)($_POST['trash_id'] ?? 0);
        if ($trashId > 0) {
            $res = permanently_delete_photo_from_trash($trashId);
            set_flash($res['ok'] ? 'success' : 'danger', $res['message']);
        }
        redirect(module_url('photo_trash.php', $redirectParams));
    }

    if ($action === 'restore_all') {
        $allItems = get_photo_trash_list(['source_type' => $redirectTab !== 'all' ? $redirectTab : '']);
        $okCount = 0;
        foreach ($allItems as $item) {
            $tid = (int)($item['id'] ?? 0);
            if ($tid > 0) {
                $r = restore_photo_from_trash($tid);
                if (!empty($r['ok'])) {
                    $okCount++;
                }
            }
        }
        if ($okCount > 0) {
            set_flash('success', "Berhasil memulihkan (restore) {$okCount} foto kembali ke data barang/perangkat asalnya.");
        } else {
            set_flash('warning', 'Tidak ada foto yang dapat dipulihkan.');
        }
        redirect(module_url('photo_trash.php', $redirectParams));
    }

    if ($action === 'empty_trash') {
        $allItems = get_photo_trash_list(['source_type' => $redirectTab !== 'all' ? $redirectTab : '']);
        $delCount = 0;
        foreach ($allItems as $item) {
            $tid = (int)($item['id'] ?? 0);
            if ($tid > 0) {
                $r = permanently_delete_photo_from_trash($tid);
                if (!empty($r['ok'])) {
                    $delCount++;
                }
            }
        }
        if ($delCount > 0) {
            set_flash('success', "{$delCount} foto di Folder Sampah telah dihapus secara permanen.");
        } else {
            set_flash('info', 'Folder Sampah sudah kosong.');
        }
        redirect(module_url('photo_trash.php', $redirectParams));
    }
}

$sourceFilter = trim((string)($_GET['source'] ?? 'all'));
if (!in_array($sourceFilter, ['all', 'inventaris_kartu', 'assets'], true)) {
    $sourceFilter = 'all';
}
$search = trim((string)($_GET['q'] ?? ''));

// Ambil seluruh daftar tanpa filter untuk statistik metrik atas
$allTrashRows = get_photo_trash_list([]);
$totalTrashCount = count($allTrashRows);
$countKartu = 0;
$countAssets = 0;
$countExpiringSoon = 0;

foreach ($allTrashRows as $tr) {
    if (($tr['source_type'] ?? '') === 'assets') {
        $countAssets++;
    } else {
        $countKartu++;
    }
    if ((int)($tr['days_left'] ?? 7) <= 2) {
        $countExpiringSoon++;
    }
}

// Ambil daftar terfilter untuk ditampilkan
$filteredTrashRows = get_photo_trash_list([
    'source_type' => $sourceFilter !== 'all' ? $sourceFilter : '',
    'q'           => $search,
]);

if (!function_exists('trash_query_url')) {
    function trash_query_url(array $overrides = []): string {
        $params = $_GET;
        foreach ($overrides as $k => $v) {
            if ($v === null || $v === '' || $v === 'all') {
                unset($params[$k]);
            } else {
                $params[$k] = $v;
            }
        }
        return module_url('photo_trash.php', $params);
    }
}

ob_start();
?>
<!-- HEADER UTAMA -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
  <div>
    <div class="text-[11px] font-bold text-[#0E5C58] uppercase tracking-wider mb-1 flex items-center gap-1.5">
      <i class="bi bi-recycle"></i> 7-DAY PHOTO RETENTION &amp; RECOVERY CENTER
    </div>
    <div class="flex items-center gap-2.5 flex-wrap">
      <h1 class="text-[22px] sm:text-[24px] font-bold text-[#1B2A3A] m-0 leading-tight">Folder Sampah Foto (Retensi 7 Hari)</h1>
      <span class="px-2.5 py-0.5 text-[11px] font-semibold bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC] rounded-full inline-flex items-center gap-1">
        <i class="bi bi-clock-history"></i> Auto-Purge 7 Hari
      </span>
    </div>
    <div class="text-[13px] text-[#53595F] mt-1">
      Foto yang dihapus atau diganti dari <strong>Kartu Inventaris</strong> maupun <strong>Perangkat IT</strong> disimpan sementara selama <strong>7 hari</strong> sebelum dihapus permanen dari server.
    </div>
  </div>

  <div class="flex flex-wrap items-center gap-2 shrink-0">
    <a href="<?= e(module_url('asset_gallery.php')) ?>" class="h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-images text-[#0E5C58]"></i> <span>Galeri Foto</span>
    </a>
    <a href="<?= e(module_url('dashboard.php', ['tab' => 'kartu'])) ?>" class="h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-credit-card-2-front text-[#0E5C58]"></i> <span>Kartu Inventaris</span>
    </a>
    <?php if ($totalTrashCount > 0): ?>
      <form method="post" action="<?= e(module_url('photo_trash.php')) ?>" class="m-0" onsubmit="return confirm('Pulihkan (Restore) seluruh foto di daftar ini kembali ke data barang/perangkat asalnya?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="restore_all">
        <input type="hidden" name="return_source" value="<?= e($sourceFilter) ?>">
        <input type="hidden" name="return_q" value="<?= e($search) ?>">
        <button type="submit" class="h-[36px] px-3.5 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[13px] font-semibold transition-colors inline-flex items-center gap-1.5 border-0 shadow-xs cursor-pointer">
          <i class="bi bi-arrow-counterclockwise"></i> <span>Pulihkan Semua (<?= $totalTrashCount ?>)</span>
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($autoPurgedCount > 0): ?>
<div class="alert alert-info d-flex align-items-center gap-2 rounded-[10px] mb-3 py-2.5 px-3.5 text-[13px]">
  <i class="bi bi-info-circle-fill fs-5"></i>
  <div><strong>Pembersihan Otomatis 7 Hari:</strong> Sebanyak <strong><?= (int)$autoPurgedCount ?> foto</strong> yang telah melewati masa simpan 7 hari berhasil dibersihkan secara permanen.</div>
</div>
<?php endif; ?>

<!-- 4 METRIK RINGKASAN FOLDER SAMPAH -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
  <div class="bg-white border border-[#D1D5D8] rounded-[10px] p-3.5 relative overflow-hidden shadow-xs">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0E5C58]"></div>
    <div class="text-[11px] font-bold uppercase tracking-wider text-[#53595F]">Total Foto di Sampah</div>
    <div class="text-[26px] font-bold text-[#1B2A3A] mt-1 leading-none"><?= number_format($totalTrashCount) ?> <span class="text-[12px] font-normal text-[#53595F]">Foto</span></div>
    <div class="text-[11px] text-[#0E5C58] font-medium mt-1.5"><i class="bi bi-shield-check me-1"></i>Dapat di-restore dalam 7 hari</div>
  </div>

  <div class="bg-white border border-[#D1D5D8] rounded-[10px] p-3.5 relative overflow-hidden shadow-xs">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0284C7]"></div>
    <div class="text-[11px] font-bold uppercase tracking-wider text-[#53595F]">Foto Kartu Inventaris</div>
    <div class="text-[26px] font-bold text-[#1B2A3A] mt-1 leading-none"><?= number_format($countKartu) ?> <span class="text-[12px] font-normal text-[#53595F]">Foto</span></div>
    <div class="text-[11px] text-[#53595F] mt-1.5"><i class="bi bi-credit-card-2-front text-[#0284C7] me-1"></i>Modul Kartu Inventaris Cabang</div>
  </div>

  <div class="bg-white border border-[#D1D5D8] rounded-[10px] p-3.5 relative overflow-hidden shadow-xs">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#10B981]"></div>
    <div class="text-[26px] font-bold text-[#1B2A3A] leading-none float-end"><?= number_format($countAssets) ?></div>
    <div class="text-[11px] font-bold uppercase tracking-wider text-[#53595F]">Foto Perangkat IT</div>
    <div class="text-[11px] text-[#53595F] mt-3"><i class="bi bi-pc-display text-[#10B981] me-1"></i>Modul Scan QR &amp; Master Aset IT</div>
  </div>

  <div class="bg-white border border-[#D1D5D8] rounded-[10px] p-3.5 relative overflow-hidden shadow-xs">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#D97706]"></div>
    <div class="text-[11px] font-bold uppercase tracking-wider text-[#53595F]">Segera Kedaluwarsa (&le; 2 Hari)</div>
    <div class="text-[26px] font-bold text-[#B45309] mt-1 leading-none"><?= number_format($countExpiringSoon) ?> <span class="text-[12px] font-normal text-[#53595F]">Foto</span></div>
    <div class="text-[11px] text-[#92400E] font-medium mt-1.5"><i class="bi bi-hourglass-bottom me-1"></i>Segera pulihkan bila masih diperlukan</div>
  </div>
</div>

<!-- INFO PANEL STRUKTUR NAMA DETAIL DI FOLDER SAMPAH -->
<div class="bg-[#F8FAF9] border border-[#A7E2DC] rounded-[10px] p-3 mb-4 shadow-xs">
  <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-2">
    <div class="flex items-start gap-2.5">
      <div class="w-8 h-8 rounded-[8px] bg-[#0E5C58] text-white flex items-center justify-center shrink-0 mt-0.5">
        <i class="bi bi-card-checklist"></i>
      </div>
      <div>
        <div class="text-[12px] font-bold text-[#0E5C58] uppercase tracking-wide">Informasi Nama Detail Lengkap pada Setiap Foto Sampah</div>
        <div class="text-[12px] text-[#53595F] mt-0.5">
          Setiap foto yang dihapus menyimpan <strong>8 atribut identitas lengkap</strong>:
          <span class="badge bg-white text-[#1B2A3A] border border-[#D1D5D8] font-medium">1. Nama Barang / Perangkat</span>
          <span class="badge bg-white text-[#1B2A3A] border border-[#D1D5D8] font-medium">2. Slot Foto (Foto Ke-1 Utama / Foto Ke-2 Detail)</span>
          <span class="badge bg-white text-[#1B2A3A] border border-[#D1D5D8] font-medium">3. Kode Inventaris / No. Rekening</span>
          <span class="badge bg-white text-[#1B2A3A] border border-[#D1D5D8] font-medium">4. Nomor Seri (S/N)</span>
          <span class="badge bg-white text-[#1B2A3A] border border-[#D1D5D8] font-medium">5. Asal Modul &amp; Kategori/Golongan</span>
          <span class="badge bg-white text-[#1B2A3A] border border-[#D1D5D8] font-medium">6. Kantor Cabang &amp; Ruangan/Lokasi</span>
          <span class="badge bg-white text-[#1B2A3A] border border-[#D1D5D8] font-medium">7. Pengguna / PIC &amp; Divisi</span>
          <span class="badge bg-white text-[#1B2A3A] border border-[#D1D5D8] font-medium">8. Petugas Penghapus, Alasan &amp; Hitung Mundur 7 Hari</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- FILTER TAB & PENCARIAN -->
<div class="bg-white border border-[#D1D5D8] rounded-[10px] p-3.5 mb-4 shadow-xs">
  <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
    <!-- Tab Modul -->
    <div class="flex flex-wrap items-center gap-1.5">
      <a href="<?= e(trash_query_url(['source' => null])) ?>" class="h-[34px] px-3 rounded-[8px] text-[12px] font-semibold inline-flex items-center gap-1.5 text-decoration-none transition-colors <?= $sourceFilter === 'all' ? 'bg-[#0E5C58] text-white' : 'bg-[#F4F5F7] text-[#53595F] hover:bg-[#E5E7EB]' ?>">
        <i class="bi bi-grid-fill"></i> Semua Foto <span class="px-1.5 py-0.2 rounded-full text-[10px] <?= $sourceFilter === 'all' ? 'bg-white text-[#0E5C58]' : 'bg-white text-[#53595F]' ?>"><?= $totalTrashCount ?></span>
      </a>
      <a href="<?= e(trash_query_url(['source' => 'inventaris_kartu'])) ?>" class="h-[34px] px-3 rounded-[8px] text-[12px] font-semibold inline-flex items-center gap-1.5 text-decoration-none transition-colors <?= $sourceFilter === 'inventaris_kartu' ? 'bg-[#0E5C58] text-white' : 'bg-[#F4F5F7] text-[#53595F] hover:bg-[#E5E7EB]' ?>">
        <i class="bi bi-credit-card-2-front"></i> Kartu Inventaris <span class="px-1.5 py-0.2 rounded-full text-[10px] <?= $sourceFilter === 'inventaris_kartu' ? 'bg-white text-[#0E5C58]' : 'bg-white text-[#53595F]' ?>"><?= $countKartu ?></span>
      </a>
      <a href="<?= e(trash_query_url(['source' => 'assets'])) ?>" class="h-[34px] px-3 rounded-[8px] text-[12px] font-semibold inline-flex items-center gap-1.5 text-decoration-none transition-colors <?= $sourceFilter === 'assets' ? 'bg-[#0E5C58] text-white' : 'bg-[#F4F5F7] text-[#53595F] hover:bg-[#E5E7EB]' ?>">
        <i class="bi bi-pc-display"></i> Perangkat IT <span class="px-1.5 py-0.2 rounded-full text-[10px] <?= $sourceFilter === 'assets' ? 'bg-white text-[#0E5C58]' : 'bg-white text-[#53595F]' ?>"><?= $countAssets ?></span>
      </a>
    </div>

    <!-- Form Pencarian -->
    <form method="GET" action="<?= e(module_url('photo_trash.php')) ?>" class="flex items-center gap-2 m-0 flex-1 lg:max-w-[440px]">
      <?php if ($sourceFilter !== 'all'): ?>
        <input type="hidden" name="source" value="<?= e($sourceFilter) ?>">
      <?php endif; ?>
      <div class="input-group">
        <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 8px 0 0 8px; height: 36px; border-color: #D1D5D8;"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control border-start-0 ps-0" style="border-radius: 0 8px 8px 0; font-size: 13px; height: 36px; border-color: #D1D5D8;" placeholder="Cari nama barang, no rekening, kode aset, cabang, ruangan..." value="<?= e($search) ?>">
      </div>
      <button type="submit" class="h-[36px] px-3 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[12px] font-semibold border-0 shadow-xs shrink-0 cursor-pointer">
        Cari
      </button>
      <?php if ($search !== '' || $sourceFilter !== 'all'): ?>
        <a href="<?= e(module_url('photo_trash.php')) ?>" class="h-[36px] px-2.5 bg-white border border-[#D1D5D8] rounded-[8px] text-[#53595F] hover:bg-[#F4F5F7] inline-flex items-center justify-center text-decoration-none shrink-0" title="Reset Filter">
          <i class="bi bi-arrow-counterclockwise"></i>
        </a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- DAFTAR KARTU FOTO DI FOLDER SAMPAH -->
<?php if (empty($filteredTrashRows)): ?>
  <div class="bg-white border border-[#D1D5D8] rounded-[12px] p-5 text-center shadow-xs">
    <div class="w-16 h-16 rounded-full bg-[#F4F5F7] text-[#0E5C58] inline-flex items-center justify-center mb-3" style="font-size: 2rem;">
      <i class="bi bi-recycle"></i>
    </div>
    <h5 class="font-bold text-[#1B2A3A] mb-1">Folder Sampah Foto Kosong</h5>
    <p class="text-[13px] text-[#53595F] mb-3 max-w-md mx-auto">
      Belum ada foto yang dihapus dalam 7 hari terakhir<?= $search !== '' ? ' untuk kata kunci pencarian tersebut' : '' ?>. Semua foto yang dihapus dari Kartu Inventaris maupun Perangkat IT akan otomatis tampil di halaman ini.
    </p>
    <div class="flex justify-center gap-2">
      <a href="<?= e(module_url('dashboard.php', ['tab' => 'kartu'])) ?>" class="h-[34px] px-3.5 bg-[#0E5C58] text-white rounded-[8px] text-[12px] font-semibold inline-flex items-center gap-1.5 text-decoration-none">
        <i class="bi bi-credit-card-2-front"></i> Ke Kartu Inventaris
      </a>
      <a href="<?= e(module_url('asset_gallery.php')) ?>" class="h-[34px] px-3.5 bg-white border border-[#D1D5D8] text-[#1B2A3A] rounded-[8px] text-[12px] font-semibold inline-flex items-center gap-1.5 text-decoration-none">
        <i class="bi bi-images"></i> Buka Galeri Foto
      </a>
    </div>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($filteredTrashRows as $tr):
      $tid = (int)($tr['id'] ?? 0);
      $sourceType = (string)($tr['source_type'] ?? 'inventaris_kartu');
      $itemId = (int)($tr['item_id'] ?? 0);
      $slot = (int)($tr['photo_slot'] ?? 1);
      $slotLabel = (string)($tr['slot_label'] ?? ($slot === 2 ? 'Foto Ke-2 (Detail)' : 'Foto Ke-1 (Utama)'));
      $photoUrl = (string)($tr['photo_url'] ?? '');
      $namaBarang = trim((string)($tr['nama_barang'] ?? '-'));
      $kodeInv = trim((string)($tr['kode_inventaris'] ?? '-'));
      $nomorSeri = trim((string)($tr['nomor_seri'] ?? '-'));
      $kategoriJenis = trim((string)($tr['kategori_jenis'] ?? '-'));
      $cabangLokasi = trim((string)($tr['cabang_lokasi'] ?? '-'));
      $penggunaDivisi = trim((string)($tr['pengguna_divisi'] ?? '-'));
      $ketDetail = trim((string)($tr['keterangan_detail'] ?? ''));
      $deletedBy = trim((string)($tr['deleted_by'] ?? 'Petugas'));
      $deletedReason = trim((string)($tr['deleted_reason'] ?? 'Dihapus oleh petugas'));
      $deletedAt = (string)($tr['deleted_at'] ?? '');
      $expiresAt = (string)($tr['expires_at'] ?? '');
      $countdownLabel = (string)($tr['countdown_label'] ?? '7 hari lagi');
      $daysLeft = (int)($tr['days_left'] ?? 7);

      $isAsset = ($sourceType === 'assets');
      $sourceBadgeLabel = $isAsset ? 'Perangkat IT / PC' : 'Kartu Inventaris';
      $sourceBadgeClass = $isAsset
          ? 'bg-[#ECFDF5] text-[#065F46] border-[#A7F3D0]'
          : 'bg-[#E0F2FE] text-[#0369A1] border-[#BAE6FD]';
      $itemDetailUrl = $isAsset
          ? module_url('scan.php', ['token' => get_static_qr_token($itemId)])
          : module_url('kartu_detail.php', ['id' => $itemId]);

      $countdownBadgeClass = $daysLeft <= 2
          ? 'bg-[#FEF2F2] text-[#DC2626] border-[#FECACA]'
          : 'bg-[#FFFBEB] text-[#B45309] border-[#FDE68A]';
    ?>
    <div class="col-12 col-lg-6">
      <div class="bg-white border border-[#D1D5D8] rounded-[12px] overflow-hidden shadow-xs h-100 flex flex-col justify-between">
        <div>
          <!-- Header Card: Modul, Slot Foto & Hitung Mundur 7 Hari -->
          <div class="px-3.5 py-2.5 bg-[#F8FAF9] border-b border-[#E5E7EB] flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center gap-1.5 flex-wrap">
              <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border <?= $sourceBadgeClass ?>">
                <i class="bi <?= $isAsset ? 'bi-pc-display' : 'bi-credit-card-2-front' ?> me-1"></i><?= e($sourceBadgeLabel) ?>
              </span>
              <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#D9F1EF] text-[#0E5C58] border border-[#A7E2DC]">
                <i class="bi bi-camera-fill me-1"></i><?= e($slotLabel) ?>
              </span>
            </div>
            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border <?= $countdownBadgeClass ?>" title="Dihapus permanen pada <?= e(date('d/m/Y H:i', strtotime($expiresAt))) ?>">
              <i class="bi bi-hourglass-split me-1"></i>Hilang Permanen: <?= e($countdownLabel) ?>
            </span>
          </div>

          <!-- Body Card: Preview Foto + Tabel Nama Detail Lengkap -->
          <div class="p-3.5">
            <div class="row g-3">
              <!-- Kolom Foto Preview -->
              <div class="col-12 col-sm-4">
                <div class="position-relative rounded-[10px] overflow-hidden border border-[#D1D5D8] bg-[#F4F5F7] flex items-center justify-center" style="height: 185px;">
                  <img
                    src="<?= e($photoUrl) ?>"
                    alt="<?= e($namaBarang) ?>"
                    fetchpriority="low"
                    loading="lazy"
                    class="w-100 h-100"
                    style="object-fit: cover; cursor: zoom-in;"
                    onclick="openTrashZoomModal('<?= e($photoUrl) ?>', '<?= e($namaBarang) ?> — <?= e($slotLabel) ?>', '<?= e($kodeInv) ?>')"
                  >
                  <button
                    type="button"
                    class="position-absolute bottom-2 end-2 px-2.5 py-1 bg-dark bg-opacity-75 text-white border-0 rounded-full text-[11px] font-semibold shadow-xs"
                    onclick="openTrashZoomModal('<?= e($photoUrl) ?>', '<?= e($namaBarang) ?> — <?= e($slotLabel) ?>', '<?= e($kodeInv) ?>')"
                  >
                    <i class="bi bi-arrows-fullscreen me-1"></i>Perbesar
                  </button>
                </div>
              </div>

              <!-- Kolom Nama Detail Lengkap (8 Atribut) -->
              <div class="col-12 col-sm-8">
                <div class="text-[11px] font-bold text-[#0E5C58] uppercase tracking-wider mb-0.5">
                  1. Nama Detail Barang / Perangkat
                </div>
                <h6 class="font-bold text-[#1B2A3A] text-[15px] mb-2 leading-snug">
                  <a href="<?= e($itemDetailUrl) ?>" class="text-[#1B2A3A] hover:text-[#0E5C58] text-decoration-none">
                    <?= e($namaBarang) ?>
                  </a>
                </h6>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-3 gap-y-1.5 text-[12px] bg-[#F8FAF9] border border-[#E5E7EB] rounded-[8px] p-2.5">
                  <div>
                    <span class="text-[#53595F] block text-[10px] uppercase font-bold">2. Kode / No. Rekening</span>
                    <span class="font-mono font-bold text-[#0E5C58]"><?= e($kodeInv) ?></span>
                  </div>
                  <div>
                    <span class="text-[#53595F] block text-[10px] uppercase font-bold">3. Nomor Seri (S/N)</span>
                    <span class="font-mono font-semibold text-[#1B2A3A]"><?= e($nomorSeri) ?></span>
                  </div>
                  <div>
                    <span class="text-[#53595F] block text-[10px] uppercase font-bold">4. Kategori / Golongan</span>
                    <span class="font-medium text-[#1B2A3A]"><?= e($kategoriJenis) ?></span>
                  </div>
                  <div>
                    <span class="text-[#53595F] block text-[10px] uppercase font-bold">5. Cabang &amp; Lokasi/Ruang</span>
                    <span class="font-semibold text-[#1B2A3A]"><i class="bi bi-geo-alt-fill text-[#0E5C58] me-0.5"></i><?= e($cabangLokasi) ?></span>
                  </div>
                  <div class="sm:col-span-2">
                    <span class="text-[#53595F] block text-[10px] uppercase font-bold">6. Pengguna / PIC &amp; Unit</span>
                    <span class="font-medium text-[#1B2A3A]"><i class="bi bi-person-badge text-[#0E5C58] me-1"></i><?= e($penggunaDivisi) ?></span>
                  </div>
                  <?php if ($ketDetail !== ''): ?>
                  <div class="sm:col-span-2 pt-1 border-t border-[#E5E7EB]">
                    <span class="text-[#53595F] block text-[10px] uppercase font-bold">7. Spesifikasi / Keterangan Detail</span>
                    <span class="text-[#53595F] text-[11px]"><?= e($ketDetail) ?></span>
                  </div>
                  <?php endif; ?>
                </div>

                <!-- Info Penghapusan & Jadwal Hilang Permanen -->
                <div class="mt-2 text-[11px] text-[#53595F] flex flex-wrap items-center justify-between gap-1">
                  <span>
                    <i class="bi bi-person-x text-danger me-1"></i>Dihapus oleh: <strong class="text-[#1B2A3A]"><?= e($deletedBy) ?></strong>
                    (<?= e(date('d/m/Y H:i', strtotime($deletedAt))) ?>)
                  </span>
                  <span class="text-[#B45309] font-semibold">
                    <i class="bi bi-calendar-x me-1"></i>Batas Restore: <?= e(date('d/m/Y H:i', strtotime($expiresAt))) ?>
                  </span>
                </div>
                <div class="text-[11px] text-[#53595F] mt-0.5">
                  <i class="bi bi-info-circle me-1"></i>Alasan: <em><?= e($deletedReason) ?></em>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer Aksi: Pulihkan (Restore), Buka Detail Barang, Hapus Permanen -->
        <div class="px-3.5 py-2.5 bg-[#F8FAF9] border-t border-[#E5E7EB] flex flex-wrap items-center justify-between gap-2">
          <div class="flex flex-wrap items-center gap-2">
            <form method="post" action="<?= e(module_url('photo_trash.php')) ?>" class="m-0">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="restore_photo">
              <input type="hidden" name="trash_id" value="<?= $tid ?>">
              <input type="hidden" name="return_source" value="<?= e($sourceFilter) ?>">
              <input type="hidden" name="return_q" value="<?= e($search) ?>">
              <button type="submit" class="h-[32px] px-3 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[12px] font-semibold inline-flex items-center gap-1.5 border-0 shadow-xs cursor-pointer">
                <i class="bi bi-arrow-counterclockwise"></i> Pulihkan ke <?= $slot === 2 ? 'Foto 2' : 'Foto 1' ?>
              </button>
            </form>

            <a href="<?= e($itemDetailUrl) ?>" class="h-[32px] px-3 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] rounded-[8px] text-[12px] font-medium inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
              <i class="bi bi-box-arrow-up-right text-[#0E5C58]"></i> Buka Detail Barang
            </a>
          </div>

          <form method="post" action="<?= e(module_url('photo_trash.php')) ?>" class="m-0" onsubmit="return confirm('Hapus foto ini secara PERMANEN sekarang? File fisik yang dihapus permanen tidak dapat dikembalikan lagi.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_permanent">
            <input type="hidden" name="trash_id" value="<?= $tid ?>">
            <input type="hidden" name="return_source" value="<?= e($sourceFilter) ?>">
            <input type="hidden" name="return_q" value="<?= e($search) ?>">
            <button type="submit" class="h-[32px] px-2.5 bg-white border border-[#FECACA] hover:bg-[#FEF2F2] text-[#DC2626] rounded-[8px] text-[11px] font-semibold inline-flex items-center gap-1 cursor-pointer" title="Hapus Permanen Sekarang Sebelum 7 Hari">
              <i class="bi bi-trash3-fill"></i> Hapus Permanen
            </button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- MODAL ZOOM PRATINJAU FOTO SAMPAH -->
<div class="modal fade" id="trashPhotoZoomModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius: 14px;">
      <div class="modal-header border-0 text-white py-2.5 px-3.5" style="background: #0E5C58;">
        <div>
          <h6 class="modal-title fw-bold mb-0" id="trashZoomTitle">Pratinjau Foto di Folder Sampah</h6>
          <div class="small opacity-75 font-mono" id="trashZoomCode"></div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body p-3 bg-dark text-center">
        <img id="trashZoomImg" src="" alt="Preview Foto Sampah" fetchpriority="low" class="img-fluid rounded" style="max-height: 75vh; object-fit: contain;">
      </div>
    </div>
  </div>
</div>

<script>
function openTrashZoomModal(src, title, code) {
  var img = document.getElementById('trashZoomImg');
  var ttl = document.getElementById('trashZoomTitle');
  var cd = document.getElementById('trashZoomCode');
  if (img) img.src = src;
  if (ttl) ttl.textContent = title || 'Pratinjau Foto Sampah';
  if (cd) cd.textContent = code || '';
  var modalEl = document.getElementById('trashPhotoZoomModal');
  if (modalEl && typeof bootstrap !== 'undefined') {
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }
}
</script>
<?php
$body = ob_get_clean();
render_page('Folder Sampah Foto (Retensi 7 Hari)', $body);
