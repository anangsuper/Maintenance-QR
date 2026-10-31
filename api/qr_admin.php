<?php
require __DIR__ . '/bootstrap.php';
require_admin_or_hrd();

$cabangId = max(0, (int)($_GET['cabang'] ?? 0));
$cabangs = get_cabang_list();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_missing') {
        $created = generate_missing_qr_tokens($cabangId);
        $_SESSION['flash'] = "{$created} token QR baru berhasil di-generate secara otomatis.";
        header('Location: ' . module_url('qr_admin.php', $cabangId > 0 ? ['cabang'=>$cabangId] : []));
        exit;
    }

    if ($action === 'regenerate') {
        $assetId = (int)($_POST['asset_id'] ?? 0);
        if ($assetId > 0) {
            regenerate_qr_token($assetId);
            $_SESSION['flash'] = "Token QR berhasil diperbarui. QR lama otomatis di-revoke demi keamanan.";
        }
        header('Location: ' . module_url('qr_admin.php', $cabangId > 0 ? ['cabang'=>$cabangId] : []));
        exit;
    }
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$rows = get_qr_admin_rows($cabangId);

// Hitung Statistik Token
$totalUnits = count($rows);
$countValid = 0;
$countMissing = 0;
$activeBranchName = 'Semua Kantor Cabang';

$opts = '';
foreach ($cabangs as $c) {
    $cId = (int)($c['id'] ?? 0);
    $cNama = $c['nama'] ?? $c['nama_cabang'] ?? 'Cabang #' . $cId;
    if ($cId === $cabangId) {
        $activeBranchName = $cNama;
    }
    $opts .= '<option value="'.$cId.'"'.($cId===$cabangId?' selected':'').'>'.e($cNama).'</option>';
}

$table = '';
$num = 0;
foreach ($rows as $r) {
    $num++;
    $aid = (int)($r['id'] ?? 0);
    $tok = $r['qr_token'] ?? '';
    $hasToken = !empty($tok);

    if ($hasToken) {
        $countValid++;
    } else {
        $countMissing++;
    }

    $url = $hasToken ? qr_url('scan.php', ['t' => $tok]) : '';
    $kode = $r['kode_inventaris'] ?? ('ASET-' . $aid);
    $deviceTitle = asset_title($r);
    $userNama = !empty($r['karyawan_nama']) && $r['karyawan_nama'] !== '-' ? $r['karyawan_nama'] : 'Umum / Pool';
    $divisiNama = !empty($r['divisi_nama']) && $r['divisi_nama'] !== '-' ? $r['divisi_nama'] : '';
    $cabangNama = !empty($r['cabang_nama']) && $r['cabang_nama'] !== '-' ? $r['cabang_nama'] : 'KPO';
    $sn = $r['nomor_seri'] ?? '-';
    $kategori = $r['kategori'] ?? 'Komputer';

    $searchKeyword = strtolower($kode . ' ' . $deviceTitle . ' ' . $userNama . ' ' . $divisiNama . ' ' . $cabangNama . ' ' . $sn . ' ' . $kategori);

    $rFoto = trim((string)($r['foto'] ?? ''));
    $rFotoSrc = function_exists('asset_image_url') ? asset_image_url($rFoto) : ($rFoto ? (str_starts_with($rFoto, 'http') ? $rFoto : module_url($rFoto)) : '');
    $rEditUrl = module_url('asset_edit.php', ['id' => $aid]);

    if ($hasToken) {
        $modalPayload = htmlspecialchars(json_encode([
            'id' => $aid,
            'kode' => $kode,
            'device' => $deviceTitle,
            'user' => $userNama . ($divisiNama ? " ({$divisiNama})" : ''),
            'cabang' => $cabangNama,
            'url' => $url,
            'token' => $tok,
            'foto' => $rFotoSrc,
            'edit_url' => $rEditUrl
        ]), ENT_QUOTES, 'UTF-8');

        $qrStatus = '
        <button type="button" class="h-[28px] px-2.5 bg-[#D9F1EF] text-[#0E5C58] hover:bg-[#0E5C58] hover:text-white border border-[#A7E2DC] rounded-[6px] text-[11px] font-bold transition-colors inline-flex items-center gap-1 cursor-pointer shadow-xs" 
          onclick="openQrModal('.$modalPayload.')" title="Klik untuk Pratinjau & Unduh QR">
          <i class="bi bi-qr-code text-[12px]"></i>
          <span>LIHAT QR</span>
        </button>';
    } else {
        $qrStatus = '<span class="px-2 py-0.5 rounded-[6px] text-[11px] font-medium bg-[#F3F4F6] text-[#6B7280] border border-[#E5E7EB] inline-flex items-center gap-1"><i class="bi bi-dash-circle text-[11px]"></i> BELUM ADA</span>';
    }

    $action = '<div class="flex items-center justify-end gap-1">';
    $action .= '<a class="h-[28px] w-[28px] bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#53595F] hover:text-[#0E5C58] rounded-[6px] inline-flex items-center justify-center transition-colors text-decoration-none" href="'.e(module_url('asset_edit.php', ['id'=>$aid])).'" title="Edit Detail Aset"><i class="bi bi-pencil-square text-[13px]"></i></a>';
    if ($url) {
        $action .= '<a class="h-[28px] w-[28px] bg-white border border-[#D1D5D8] hover:bg-[#D9F1EF] text-[#0E5C58] rounded-[6px] inline-flex items-center justify-center transition-colors text-decoration-none" target="_blank" href="'.e(module_url('print_qr.php', ['asset_id'=>$aid])).'" title="Cetak Stiker Label"><i class="bi bi-printer text-[13px]"></i></a>';
    }
    $action .= '<form method="post" class="inline m-0">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="regenerate">
      <input type="hidden" name="asset_id" value="'.$aid.'">
      <button type="submit" class="h-[28px] w-[28px] bg-white border border-[#D1D5D8] hover:bg-[#FEF3C7] text-[#D97706] rounded-[6px] inline-flex items-center justify-center transition-colors cursor-pointer" title="Generate Ulang & Revoke Token QR" onclick="return confirm(&#39;Generate ulang token QR ini? QR lama otomatis tidak berlaku lagi demi keamanan.&#39;)"><i class="bi bi-arrow-repeat text-[13px]"></i></button>
    </form>';
    $action .= '<a class="h-[28px] w-[28px] bg-white border border-[#D1D5D8] hover:bg-[#FEE2E2] text-[#DC2626] rounded-[6px] inline-flex items-center justify-center transition-colors text-decoration-none" href="'.e(module_url('asset_delete.php', ['id'=>$aid])).'" title="Hapus Aset" onclick="return confirm(&#39;Hapus aset '.e(addslashes($kode)).' ini dari sistem?&#39;)"><i class="bi bi-trash text-[13px]"></i></a>';
    $action .= '</div>';

    $table .= '<tr class="asset-table-row hover:bg-[#F9FAFB] transition-colors" id="row-asset-'.$aid.'" data-search="'.e($searchKeyword).'" data-has-token="'.($hasToken ? '1' : '0').'">
      <td class="text-center py-2.5">
        <input type="checkbox" class="form-check-input row-chk cursor-pointer" value="'.$aid.'" onchange="onRowCheckChange()">
      </td>
      <td class="text-center font-mono text-[#53595F] text-[12px] py-2.5">'.sprintf('%02d', $num).'</td>
      <td class="py-2.5">
        <span class="font-mono font-bold text-[#0E5C58] text-[13px]">'.e($kode).'</span>
      </td>
      <td class="py-2.5">
        <div class="font-semibold text-[#1B2A3A] text-[13px]">'.e($deviceTitle).'</div>
        <div class="text-[#53595F] text-[11px] mt-0.5">SN: '.e($sn).' &middot; Tipe: '.e($kategori).'</div>
      </td>
      <td class="py-2.5">
        <div class="flex items-center gap-2">
          <div class="w-6 h-6 rounded-full bg-[#F4F5F7] border border-[#D1D5D8] flex items-center justify-center text-[#53595F] text-[11px] shrink-0">
            <i class="bi bi-person"></i>
          </div>
          <div class="min-w-0">
            <div class="font-semibold text-[#1B2A3A] text-[13px] truncate">'.e($userNama).'</div>
            '.($divisiNama ? '<div class="text-[#53595F] text-[11px] truncate">'.e($divisiNama).'</div>' : '').'
          </div>
        </div>
      </td>
      <td class="py-2.5"><span class="px-2 py-0.5 rounded-[6px] text-[11px] font-medium bg-[#F4F5F7] border border-[#D1D5D8] text-[#1B2A3A]">'.e($cabangNama).'</span></td>
      <td class="text-center py-2.5">'.$qrStatus.'</td>
      <td class="text-nowrap text-end py-2.5 pe-3">'.$action.'</td>
    </tr>';
}

if (!$table) {
    $table = '<tr><td colspan="8" class="text-center py-5 text-[#53595F]"><i class="bi bi-qr-code text-[32px] text-[#9CA3AF] d-block mb-2"></i>Tidak ada inventaris aset yang memerlukan label QR di cabang ini.</td></tr>';
}

$flashHtml = $flash ? '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" style="border-left: 4px solid #10B981 !important;"><i class="bi bi-check-circle-fill me-2 text-success"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$head = '
<style>
.floating-bulk-bar {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%) translateY(120px);
  background: #1B2A3A;
  border: 1px solid #334155;
  color: #ffffff;
  border-radius: 40px;
  padding: 8px 20px;
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
  display: flex;
  align-items: center;
  gap: 12px;
  z-index: 1050;
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease;
  opacity: 0;
  pointer-events: none;
}
.floating-bulk-bar.active {
  transform: translateX(-50%) translateY(0);
  opacity: 1;
  pointer-events: auto;
}
.filter-chip-btn {
  font-size: 12px;
  font-weight: 500;
  height: 30px;
  padding: 0 12px;
  border-radius: 6px;
  border: 1px solid #D1D5D8;
  background: #ffffff;
  color: #53595F;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.15s ease;
}
.filter-chip-btn:hover {
  background: #F4F5F7;
  color: #1B2A3A;
}
.filter-chip-btn.active {
  background: #0E5C58;
  border-color: #0E5C58;
  color: #ffffff;
  font-weight: 600;
  box-shadow: 0 1px 2px rgba(14, 92, 88, 0.2);
}
</style>';

$body = '
<div class="max-w-[1152px] mx-auto flex flex-col">
'.$flashHtml.'

<!-- Header Kicker & Action Bar -->
<div class="mb-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
  <div>
    <div class="text-[11px] font-bold text-[#0E5C58] uppercase tracking-wider mb-1 flex items-center gap-1.5">
      <i class="bi bi-qr-code-scan"></i> SISTEM REGISTRASI LABEL QR ASET
    </div>
    <h1 class="text-[22px] font-bold text-[#1B2A3A] tracking-tight m-0">
      Manajemen Label QR Aset
    </h1>
    <p class="text-[13px] text-[#53595F] mt-1 mb-0">Kelola token QR terenkripsi, pratinjau kode QR langsung di layar, dan cetak stiker label bodi komputer.</p>
  </div>
  <div class="flex flex-wrap items-center gap-2 shrink-0">
    <a href="'.e(module_url('print_qr.php', $cabangId > 0 ? ['cabang'=>$cabangId] : [])).'" target="_blank" class="h-[36px] px-3.5 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[13px] font-semibold transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-printer-fill"></i> Cetak Stiker QR Cabang Ini
    </a>
    <a href="'.e(module_url('print_inventory_card.php', $cabangId > 0 ? ['cabang'=>$cabangId] : [])).'" target="_blank" class="h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-credit-card-2-front text-[#0E5C58]"></i> Cetak Kartu A4
    </a>
    <a href="'.e(module_url('asset_add.php')).'" class="h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#1B2A3A] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-plus-lg text-[#0E5C58]"></i> Tambah Aset
    </a>
    <a href="'.e(module_url('dashboard.php')).'" class="h-[36px] px-3 bg-white border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium text-[#53595F] hover:bg-[#F4F5F7] transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
      <i class="bi bi-arrow-left"></i> Dashboard
    </a>
  </div>
</div>

<!-- Symmetrical Metric Strip (88px) -->
<div class="bg-[#FFFFFF] border border-[#D1D5D8] rounded-[10px] flex flex-col sm:flex-row overflow-hidden shrink-0 mb-4 shadow-xs">
  <!-- Card 1: Total Unit Aset -->
  <div class="flex-1 flex flex-col justify-center px-5 py-3 border-b sm:border-b-0 sm:border-r border-[#D1D5D8] relative">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0E5C58]"></div>
    <div class="text-[26px] font-bold text-[#1B2A3A] tabular-nums leading-none">
      '.$totalUnits.' <span class="text-[13px] font-normal text-[#53595F]">Unit</span>
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
      <span class="text-[#0E5C58] font-bold">Total Unit Aset</span>
      <span>&middot;</span>
      <span class="text-[11px] text-[#53595F]">Semua Inventaris</span>
    </div>
  </div>

  <!-- Card 2: Token QR Aktif -->
  <div class="flex-1 flex flex-col justify-center px-5 py-3 border-b sm:border-b-0 sm:border-r border-[#D1D5D8] relative">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#10B981]"></div>
    <div class="text-[26px] font-bold text-[#10B981] tabular-nums leading-none">
      '.$countValid.' <span class="text-[13px] font-normal text-[#53595F]">Siap Scan</span>
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
      <span class="text-[#10B981] font-bold">Token QR Aktif</span>
      <span>&middot;</span>
      <span class="text-[11px] text-[#53595F]">Label Terbit</span>
    </div>
  </div>

  <!-- Card 3: Belum Punya Token -->
  <div class="flex-1 flex flex-col justify-center px-5 py-3 border-b sm:border-b-0 sm:border-r border-[#D1D5D8] relative">
    <div class="absolute left-0 top-0 bottom-0 w-1 '.($countMissing > 0 ? 'bg-[#F59E0B]' : 'bg-[#6B7280]').'"></div>
    <div class="text-[26px] font-bold '.($countMissing > 0 ? 'text-[#D97706]' : 'text-[#6B7280]').' tabular-nums leading-none">
      '.$countMissing.' <span class="text-[13px] font-normal text-[#53595F]">Unit</span>
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
      <span class="'.($countMissing > 0 ? 'text-[#D97706]' : 'text-[#6B7280]').' font-bold">Belum Ada Token</span>
      <span>&middot;</span>
      <span class="text-[11px] text-[#53595F]">Perlu Generate</span>
    </div>
  </div>

  <!-- Card 4: Cabang Terpilih -->
  <div class="flex-1 flex flex-col justify-center px-5 py-3 relative">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#0284C7]"></div>
    <div class="text-[20px] font-bold text-[#1B2A3A] truncate leading-none" title="'.e($activeBranchName).'">
      '.e($activeBranchName).'
    </div>
    <div class="text-[12px] font-medium text-[#53595F] mt-1.5 flex items-center gap-1.5">
      <span class="text-[#0284C7] font-bold">Kantor Cabang</span>
      <span>&middot;</span>
      <span class="text-[11px] text-[#53595F]">Filter Wilayah</span>
    </div>
  </div>
</div>

<!-- Toolbar Box & Instant Filter Card -->
<div class="bg-white border border-[#D1D5D8] rounded-[10px] p-4 mb-4 shadow-xs">
  <div class="row g-3 align-items-center">
    <!-- Filter Cabang -->
    <div class="col-md-4">
      <form method="get" class="m-0">
        <label class="block text-[12px] font-bold text-[#1B2A3A] mb-1.5">Pilih Kantor Cabang</label>
        <select class="h-[36px] w-full bg-white border border-[#D1D5D8] rounded-[8px] px-3 text-[13px] text-[#1B2A3A] outline-none focus:border-[#0E5C58] transition-colors" name="cabang" onchange="this.form.submit()">
          <option value="0">🏢 Semua Kantor Cabang</option>
          '.$opts.'
        </select>
        <noscript><button type="submit" class="h-[32px] px-3 bg-[#0E5C58] text-white rounded-[6px] text-[12px] mt-1">Terapkan</button></noscript>
      </form>
    </div>

    <!-- Live Search Table -->
    <div class="col-md-5">
      <label class="block text-[12px] font-bold text-[#1B2A3A] mb-1.5">Pencarian Cepat Aset</label>
      <div class="relative w-full">
        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-[#9CA3AF] text-[13px]"></i>
        <input type="text" id="tableSearchInput" class="h-[36px] w-full bg-white border border-[#D1D5D8] rounded-[8px] pl-9 pr-8 text-[13px] text-[#1B2A3A] placeholder-[#9CA3AF] outline-none focus:border-[#0E5C58] transition-colors" placeholder="Cari kode, nama user, serial number, perangkat..." oninput="onFilterTable()">
        <button type="button" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[#9CA3AF] hover:text-[#1B2A3A] bg-transparent border-0 cursor-pointer p-0" onclick="clearTableSearch()" title="Reset"><i class="bi bi-x-circle-fill"></i></button>
      </div>
    </div>

    <!-- Batch Generate Action -->
    <div class="col-md-3">
      <label class="block text-[12px] font-bold text-[#1B2A3A] mb-1.5">Tindakan Token Massal</label>
      '.($countMissing > 0 ? '
      <form method="post" class="m-0">
        <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
        <input type="hidden" name="action" value="generate_missing">
        <button type="submit" class="h-[36px] w-full px-3.5 bg-[#F59E0B] hover:bg-[#D97706] text-white rounded-[8px] text-[13px] font-semibold transition-colors inline-flex items-center justify-center gap-1.5 border-0 shadow-xs cursor-pointer">
          <i class="bi bi-magic"></i> Generate '.$countMissing.' Token Kosong
        </button>
      </form>' : '
      <button class="h-[36px] w-full px-3.5 bg-[#F4F5F7] text-[#6B7280] border border-[#D1D5D8] rounded-[8px] text-[13px] font-medium inline-flex items-center justify-center gap-1.5 cursor-not-allowed" disabled>
        <i class="bi bi-shield-check text-[#10B981]"></i> Semua Token Sudah Ada
      </button>').'
    </div>
  </div>

  <!-- Filter Status Tabs / Chips -->
  <div class="flex flex-wrap items-center justify-between gap-2 mt-3 pt-3 border-t border-[#F3F4F6]">
    <div class="flex items-center gap-1.5">
      <span class="text-[12px] font-bold text-[#53595F] me-1">Filter Status:</span>
      <button type="button" class="filter-chip-btn active" id="chipAll" onclick="setTokenFilter(\'all\')">
        Semua ('.$totalUnits.')
      </button>
      <button type="button" class="filter-chip-btn" id="chipValid" onclick="setTokenFilter(\'valid\')">
        <i class="bi bi-check2"></i> Token Valid ('.$countValid.')
      </button>
      <button type="button" class="filter-chip-btn" id="chipMissing" onclick="setTokenFilter(\'missing\')">
        <i class="bi bi-dash-circle"></i> Belum Ada ('.$countMissing.')
      </button>
    </div>
    <div class="text-[12px] text-[#53595F]" id="filterStatusText">Menampilkan '.$totalUnits.' aset</div>
  </div>
</div>

<!-- Table Card Container -->
<div class="bg-white border border-[#D1D5D8] rounded-[10px] mb-4 shadow-xs overflow-hidden">
  <!-- In-Card Selection Bar -->
  <div id="selectionCardBar" class="border-b border-[#BFDBFE] px-4 py-2.5 d-none align-items-center justify-content-between flex-wrap gap-2" style="background-color: #EFF6FF !important;">
    <div class="flex items-center gap-2">
      <span class="px-2.5 py-1 text-[12px] font-bold bg-[#0284C7] text-white rounded-[6px]"><i class="bi bi-check-square me-1"></i> <span id="countSelectedText">0</span> Dipilih</span>
      <span class="text-[13px] text-[#1E40AF] font-semibold">Aksi untuk aset terpilih:</span>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <button type="button" class="h-[32px] px-3 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[6px] text-[12px] font-semibold transition-colors flex items-center gap-1.5 border-0 shadow-xs cursor-pointer" onclick="printBulkSelected(\'qr\')">
        <i class="bi bi-printer-fill"></i> Cetak Stiker QR (<span id="countPrintQr">0</span>)
      </button>
      <button type="button" class="h-[32px] px-3 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] rounded-[6px] text-[12px] font-medium transition-colors flex items-center gap-1.5 shadow-xs cursor-pointer" onclick="printBulkSelected(\'card\')">
        <i class="bi bi-credit-card-2-front text-[#0E5C58]"></i> Cetak Kartu Inventaris (<span id="countPrintCard">0</span>)
      </button>
      <button type="button" class="h-[32px] px-3 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] rounded-[6px] text-[12px] font-medium transition-colors flex items-center gap-1 cursor-pointer" onclick="clearBulkSelection()">
        <i class="bi bi-x-circle"></i> Batal
      </button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" id="qrAssetTable" style="font-size: 13px;">
      <thead style="background: #F4F5F7; border-bottom: 1px solid #D1D5D8;">
        <tr>
          <th style="width: 40px;" class="text-center py-2.5">
            <input class="form-check-input cursor-pointer" type="checkbox" id="checkAllRows" onchange="toggleSelectAllRows(this)" title="Pilih Semua Aset">
          </th>
          <th style="width: 45px;" class="text-center font-mono text-[12px] text-[#53595F] font-bold py-2.5">NO</th>
          <th style="width: 150px;" class="text-[12px] text-[#53595F] font-bold py-2.5">KODE INVENTARIS</th>
          <th class="text-[12px] text-[#53595F] font-bold py-2.5">DETAIL PERANGKAT</th>
          <th class="text-[12px] text-[#53595F] font-bold py-2.5">PENGGUNA / PIC</th>
          <th class="text-[12px] text-[#53595F] font-bold py-2.5">KANTOR CABANG</th>
          <th style="width: 130px;" class="text-center text-[12px] text-[#53595F] font-bold py-2.5">STATUS TOKEN</th>
          <th style="width: 150px;" class="text-end pe-3 text-[12px] text-[#53595F] font-bold py-2.5">AKSI</th>
        </tr>
      </thead>
      <tbody>'.$table.'</tbody>
    </table>
  </div>
</div>

<!-- Floating Bulk Action Bar (For quick access while scrolling) -->
<div id="bulkActionBar" class="floating-bulk-bar shadow-lg">
  <div class="flex items-center gap-2">
    <span class="px-2.5 py-1 text-[12px] font-bold bg-[#0E5C58] text-white rounded-[6px]"><span id="bulkSelectedCount">0</span> Dipilih</span>
  </div>
  <div class="w-[1px] h-4 bg-white/20"></div>
  <button type="button" class="h-[30px] px-3 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[6px] text-[12px] font-semibold transition-colors flex items-center gap-1.5 border-0 shadow-xs cursor-pointer" onclick="printBulkSelected(\'qr\')">
    <i class="bi bi-printer-fill"></i> Cetak QR
  </button>
  <button type="button" class="h-[30px] px-3 bg-white/10 hover:bg-white/20 text-white rounded-[6px] text-[12px] font-medium transition-colors flex items-center gap-1.5 border border-white/20 cursor-pointer" onclick="printBulkSelected(\'card\')">
    <i class="bi bi-credit-card-2-front"></i> Cetak Kartu
  </button>
  <button type="button" class="h-[30px] px-2 text-white/80 hover:text-white text-[12px] font-medium transition-colors cursor-pointer bg-transparent border-0" onclick="clearBulkSelection()">
    <i class="bi bi-x-circle"></i> Batal
  </button>
</div>

<!-- Modal Quick QR Preview -->
<div class="modal fade" id="quickQrModal" tabindex="-1" aria-labelledby="quickQrModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg rounded-[10px] overflow-hidden">
      <div class="modal-header py-3 px-4 text-white" style="background: #0E5C58 !important;">
        <div>
          <div class="text-[11px] font-bold uppercase tracking-wider text-white text-opacity-80">PRATINJAU STIKER QR &amp; FOTO UNIT</div>
          <h5 class="modal-title font-bold text-white text-[18px] m-0" id="modalAssetKode">INV-IT-001</h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <!-- Baris QR Code & Foto Fisik Perangkat -->
        <div class="row g-3 align-items-center mb-3">
          <!-- Sisi Kiri: Kode QR -->
          <div class="col-12 col-md-6 text-center">
            <div class="p-3 bg-white border border-[#D1D5D8] rounded-[10px] shadow-xs inline-block">
              <div id="modalQrBox" style="width: 200px; height: 200px; margin: 0 auto; display: flex; align-items: center; justify-content: center;"></div>
            </div>
            <div class="text-[12px] text-[#53595F] mt-2 font-medium flex items-center justify-center gap-1">
              <i class="bi bi-qr-code text-[#0E5C58]"></i> Kode QR Maintenance IT
            </div>
          </div>

          <!-- Sisi Kanan: Foto Fisik Perangkat -->
          <div class="col-12 col-md-6 text-center">
            <div id="modalAssetPhotoContainer" class="p-2 bg-[#F9FAFB] border border-[#D1D5D8] rounded-[10px] relative overflow-hidden flex items-center justify-center" style="min-height: 200px; height: 220px;">
              <img id="modalAssetPhotoImg" src="" alt="Foto Unit" class="w-full h-full object-cover rounded-[8px] border border-[#D1D5D8] d-none" onerror="onModalPhotoError(this)">
              <div id="modalAssetPhotoPlaceholder" class="text-[#53595F] text-[12px] py-4 text-center">
                <i class="bi bi-camera text-[36px] d-block mb-1 text-[#9CA3AF]"></i>
                <span class="font-medium">Belum ada foto fisik unit</span>
                <div class="mt-2.5">
                  <a id="modalAssetAddPhotoBtn" href="#" class="h-[28px] px-3 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#0E5C58] rounded-[6px] text-[12px] font-semibold transition-colors inline-flex items-center gap-1 text-decoration-none shadow-xs">
                    <i class="bi bi-plus-circle"></i> Upload Foto
                  </a>
                </div>
              </div>
            </div>
            <div class="text-[12px] text-[#53595F] mt-2 font-medium flex justify-center items-center gap-1">
              <i class="bi bi-pc-display text-[#0E5C58]"></i> Foto Fisik Unit Perangkat
              <a id="modalAssetEditPhotoBtn" href="#" class="text-[#0E5C58] hover:underline text-[11px] font-semibold text-decoration-none d-none ms-1">(Ganti Foto)</a>
            </div>
          </div>
        </div>

        <!-- Detail Perangkat -->
        <div class="bg-[#F9FAFB] p-3.5 rounded-[10px] text-start mb-3 border border-[#D1D5D8]">
          <div class="row g-2 text-[12px]">
            <div class="col-4 text-[#53595F] font-medium">Perangkat:</div>
            <div class="col-8 font-bold text-[#1B2A3A] truncate" id="modalAssetDevice">-</div>
            <div class="col-4 text-[#53595F] font-medium">Pengguna:</div>
            <div class="col-8 font-semibold text-[#0E5C58] truncate" id="modalAssetUser">-</div>
            <div class="col-4 text-[#53595F] font-medium">Kantor Cabang:</div>
            <div class="col-8 text-[#1B2A3A]" id="modalAssetCabang">-</div>
          </div>
        </div>

        <!-- Input Link Scan & Copy -->
        <div class="flex gap-1.5 mb-3">
          <input type="text" id="modalScanUrlInput" class="h-[34px] flex-1 bg-[#F4F5F7] border border-[#D1D5D8] rounded-[6px] px-2.5 font-mono text-[12px] text-[#1B2A3A] outline-none" readonly>
          <button type="button" class="h-[34px] px-3 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] rounded-[6px] text-[12px] font-medium transition-colors flex items-center gap-1.5 cursor-pointer shadow-xs" onclick="copyModalScanUrl(this)" title="Salin Link">
            <i class="bi bi-clipboard"></i> Salin
          </button>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-2 justify-center pt-2">
          <a id="modalBtnPrint" href="#" target="_blank" class="h-[36px] px-3.5 bg-[#0E5C58] hover:bg-[#094340] text-white rounded-[8px] text-[13px] font-semibold transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
            <i class="bi bi-printer-fill"></i> Cetak Stiker
          </a>
          <button type="button" class="h-[36px] px-3 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#1B2A3A] rounded-[8px] text-[13px] font-medium transition-colors inline-flex items-center gap-1.5 cursor-pointer shadow-xs" onclick="downloadModalQrPng()">
            <i class="bi bi-download"></i> Unduh Gambar QR (PNG)
          </button>
          <a id="modalBtnOpenScan" href="#" target="_blank" class="h-[36px] px-3 bg-white border border-[#D1D5D8] hover:bg-[#F4F5F7] text-[#53595F] rounded-[8px] text-[13px] font-medium transition-colors inline-flex items-center gap-1.5 text-decoration-none shadow-xs">
            <i class="bi bi-box-arrow-up-right"></i> Buka Halaman Scan
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Floating Copy Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
  <div id="qrAdminToast" class="toast align-items-center text-white bg-[#1B2A3A] border border-[#334155] rounded-[8px] shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body d-flex align-items-center gap-2 text-[13px]">
        <i class="bi bi-check-circle-fill text-[#10B981] fs-5"></i>
        <span>Link scan QR berhasil disalin!</span>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>
</div><!-- End max-w-[1152px] -->
';

$logoDataUri = app_logo_url();

$script = '
<script src="' . e(module_url('assets/vendor/qrcode.min.js')) . '"></script>
<script>
var appLogoUri = '.json_encode($logoDataUri).';
var currentTokenFilter = "all";
var currentModalData = null;

// =========================================================================
// 1. FILTER TABEL REALTIME & CHIPS
// =========================================================================
function setTokenFilter(filterType) {
  currentTokenFilter = filterType;
  var chips = {
    all: document.getElementById("chipAll"),
    valid: document.getElementById("chipValid"),
    missing: document.getElementById("chipMissing")
  };
  Object.keys(chips).forEach(function(k){
    if (chips[k]) chips[k].classList.toggle("active", k === filterType);
  });
  onFilterTable();
}

function onFilterTable() {
  var searchInput = document.getElementById("tableSearchInput");
  var keyword = (searchInput ? searchInput.value : "").toLowerCase().trim();
  var rows = document.querySelectorAll(".asset-table-row");
  var visibleCount = 0;
  var totalCount = rows.length;

  rows.forEach(function(row) {
    var text = row.getAttribute("data-search") || "";
    var hasToken = row.getAttribute("data-has-token") === "1";

    var matchKeyword = (keyword === "" || text.indexOf(keyword) !== -1);
    var matchFilter = true;
    if (currentTokenFilter === "valid") {
      matchFilter = hasToken;
    } else if (currentTokenFilter === "missing") {
      matchFilter = !hasToken;
    }

    if (matchKeyword && matchFilter) {
      row.style.display = "";
      visibleCount++;
    } else {
      row.style.display = "none";
    }
  });

  var statusText = document.getElementById("filterStatusText");
  if (statusText) {
    statusText.textContent = "Menampilkan " + visibleCount + " dari " + totalCount + " aset";
  }
}

function clearTableSearch() {
  var searchInput = document.getElementById("tableSearchInput");
  if (searchInput) {
    searchInput.value = "";
    onFilterTable();
  }
}

// =========================================================================
// 2. CHECKBOX BULK SELECTION
// =========================================================================
function toggleSelectAllRows(masterChk) {
  var rows = document.querySelectorAll(".asset-table-row");
  rows.forEach(function(row) {
    if (row.style.display !== "none") {
      var chk = row.querySelector(".row-chk");
      if (chk) chk.checked = masterChk.checked;
    }
  });
  onRowCheckChange();
}

function onRowCheckChange() {
  var selected = document.querySelectorAll(".row-chk:checked");
  var count = selected.length;
  var inCardBar = document.getElementById("selectionCardBar");
  var bulkBar = document.getElementById("bulkActionBar");
  var countSpan = document.getElementById("bulkSelectedCount");
  var countText = document.getElementById("countSelectedText");
  var countPrintQr = document.getElementById("countPrintQr");
  var countPrintCard = document.getElementById("countPrintCard");

  if (countSpan) countSpan.textContent = count;
  if (countText) countText.textContent = count;
  if (countPrintQr) countPrintQr.textContent = count;
  if (countPrintCard) countPrintCard.textContent = count;

  if (inCardBar) {
    if (count > 0) {
      inCardBar.classList.remove("d-none");
      inCardBar.classList.add("d-flex");
    } else {
      inCardBar.classList.add("d-none");
      inCardBar.classList.remove("d-flex");
    }
  }

  if (bulkBar) {
    if (count > 0) {
      bulkBar.classList.add("active");
    } else {
      bulkBar.classList.remove("active");
    }
  }

  var allVisible = document.querySelectorAll(".asset-table-row:not([style*=\'display: none\']) .row-chk");
  var allChecked = (allVisible.length > 0 && document.querySelectorAll(".asset-table-row:not([style*=\'display: none\']) .row-chk:checked").length === allVisible.length);
  var masterChk = document.getElementById("checkAllRows");
  if (masterChk) masterChk.checked = allChecked;
}

function clearBulkSelection() {
  document.querySelectorAll(".row-chk").forEach(function(c){ c.checked = false; });
  var masterChk = document.getElementById("checkAllRows");
  if (masterChk) masterChk.checked = false;
  onRowCheckChange();
}

function printBulkSelected(type) {
  var selected = document.querySelectorAll(".row-chk:checked");
  var ids = [];
  selected.forEach(function(c){ ids.push(c.value); });
  if (ids.length === 0) {
    alert("Pilih minimal 1 aset untuk dicetak.");
    return;
  }
  var targetUrl = (type === "qr")
    ? "'.e(module_url('print_qr.php')).'?ids=" + encodeURIComponent(ids.join(","))
    : "'.e(module_url('print_inventory_card.php')).'?ids=" + encodeURIComponent(ids.join(","));
  window.open(targetUrl, "_blank");
}

// =========================================================================
// 3. MODAL QUICK QR PREVIEW
// =========================================================================
function openQrModal(data) {
  currentModalData = data;
  document.getElementById("modalAssetKode").textContent = data.kode || "-";
  document.getElementById("modalAssetDevice").textContent = data.device || "-";
  document.getElementById("modalAssetUser").textContent = data.user || "-";
  document.getElementById("modalAssetCabang").textContent = data.cabang || "-";
  document.getElementById("modalScanUrlInput").value = data.url || "";

  document.getElementById("modalBtnPrint").href = "'.e(module_url('print_qr.php')).'?asset_id=" + data.id;
  document.getElementById("modalBtnOpenScan").href = data.url;

  // Handle Foto Unit
  var photoImg = document.getElementById("modalAssetPhotoImg");
  var photoPlaceholder = document.getElementById("modalAssetPhotoPlaceholder");
  var editPhotoBtn = document.getElementById("modalAssetEditPhotoBtn");
  var addPhotoBtn = document.getElementById("modalAssetAddPhotoBtn");
  var editUrl = data.edit_url || (data.id ? ("'.e(module_url('asset_edit.php')).'?id=" + data.id) : "#");

  if (editPhotoBtn) editPhotoBtn.href = editUrl;
  if (addPhotoBtn) addPhotoBtn.href = editUrl;

  if (photoImg && photoPlaceholder) {
    if (data.foto) {
      photoImg.src = data.foto;
      photoImg.classList.remove("d-none");
      photoPlaceholder.classList.add("d-none");
      if (editPhotoBtn) editPhotoBtn.classList.remove("d-none");
    } else {
      photoImg.src = "";
      photoImg.classList.add("d-none");
      photoPlaceholder.classList.remove("d-none");
      if (editPhotoBtn) editPhotoBtn.classList.add("d-none");
    }
  }

  var qrBox = document.getElementById("modalQrBox");
  qrBox.innerHTML = "";

  if (typeof QRCode !== "undefined") {
    new QRCode(qrBox, {
      text: data.url,
      width: 200,
      height: 200,
      correctLevel: QRCode.CorrectLevel.H
    });

    // Attach logo to QR canvas
    attachLogoToQr(qrBox, appLogoUri);
  }

  var modalEl = document.getElementById("quickQrModal");
  if (modalEl && typeof bootstrap !== "undefined") {
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  }
}

function onModalPhotoError(img) {
  if (img) img.classList.add("d-none");
  var ph = document.getElementById("modalAssetPhotoPlaceholder");
  if (ph) ph.classList.remove("d-none");
}

function attachLogoToQr(containerEl, logoUri) {
  if (!logoUri) return;
  var tries = 0;
  var timer = setInterval(function(){
    tries++;
    var canvas = containerEl.querySelector("canvas");
    if (canvas && canvas.width > 0) {
      clearInterval(timer);
      drawLogoOnCanvas(canvas, logoUri, containerEl);
    } else if (tries > 25) {
      clearInterval(timer);
    }
  }, 40);
}

function drawLogoOnCanvas(canvas, logoUri, containerEl) {
  var ctx = canvas.getContext("2d");
  var img = new Image();
  img.onload = function() {
    var size = canvas.width;
    var logoSize = Math.round(size * 0.22);
    var center = Math.round((size - logoSize) / 2);
    var pad = Math.round(size * 0.025);

    var bgX = center - pad;
    var bgY = center - pad;
    var bgW = logoSize + (pad * 2);
    var bgH = logoSize + (pad * 2);
    var rad = Math.round(size * 0.035);

    // Rounded background
    ctx.save();
    ctx.fillStyle = "#FFFFFF";
    ctx.beginPath();
    ctx.moveTo(bgX + rad, bgY);
    ctx.lineTo(bgX + bgW - rad, bgY);
    ctx.quadraticCurveTo(bgX + bgW, bgY, bgX + bgW, bgY + rad);
    ctx.lineTo(bgX + bgW, bgY + bgH - rad);
    ctx.quadraticCurveTo(bgX + bgW, bgY + bgH, bgX + bgW - rad, bgY + bgH);
    ctx.lineTo(bgX + rad, bgY + bgH);
    ctx.quadraticCurveTo(bgX, bgY + bgH, bgX, bgY + bgH - rad);
    ctx.lineTo(bgX, bgY + rad);
    ctx.quadraticCurveTo(bgX, bgY, bgX + rad, bgY);
    ctx.closePath();
    ctx.fill();

    // Subtle teal border
    ctx.strokeStyle = "#0E5C58";
    ctx.lineWidth = Math.max(1, Math.round(size * 0.012));
    ctx.stroke();
    ctx.restore();

    // Draw logo
    var aspect = (img.naturalWidth && img.naturalHeight) ? (img.naturalWidth / img.naturalHeight) : 1;
    var dw = logoSize;
    var dh = logoSize;
    if (aspect > 1) {
      dh = logoSize / aspect;
    } else {
      dw = logoSize * aspect;
    }
    var dx = center + (logoSize - dw) / 2;
    var dy = center + (logoSize - dh) / 2;

    ctx.drawImage(img, dx, dy, dw, dh);

    var qImg = containerEl.querySelector("img");
    if (qImg) {
      try { qImg.src = canvas.toDataURL("image/png"); } catch(e) {}
    }
  };
  img.src = logoUri;
}

function downloadModalQrPng() {
  var canvas = document.querySelector("#modalQrBox canvas");
  if (!canvas) {
    alert("Gambar QR belum selesai dirender.");
    return;
  }
  var kode = (currentModalData && currentModalData.kode) ? currentModalData.kode : "aset";
  var a = document.createElement("a");
  a.download = "QR-" + kode + ".png";
  a.href = canvas.toDataURL("image/png");
  a.click();
}

function copyModalScanUrl(btn) {
  var input = document.getElementById("modalScanUrlInput");
  if (!input || !input.value) return;
  var url = input.value;
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(url).then(function() {
      showToastNotification("Link scan QR berhasil disalin!");
    }).catch(function() {
      fallbackCopyText(url);
    });
  } else {
    fallbackCopyText(url);
  }
}

function fallbackCopyText(text) {
  var ta = document.createElement("textarea");
  ta.value = text;
  document.body.appendChild(ta);
  ta.select();
  document.execCommand("copy");
  document.body.removeChild(ta);
  showToastNotification("Link scan QR berhasil disalin!");
}

function showToastNotification(msg) {
  var toastEl = document.getElementById("qrAdminToast");
  if (toastEl) {
    var span = toastEl.querySelector(".toast-body span");
    if (span) span.textContent = msg;
    if (typeof bootstrap !== "undefined" && bootstrap.Toast) {
      var bsToast = new bootstrap.Toast(toastEl, { delay: 2200 });
      bsToast.show();
    } else {
      toastEl.classList.add("show");
      setTimeout(function(){ toastEl.classList.remove("show"); }, 2200);
    }
  } else {
    alert(msg);
  }
}
</script>';

render_page('QR Aset', $body, $head, $script);
