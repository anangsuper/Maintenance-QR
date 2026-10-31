<?php
require __DIR__ . '/bootstrap.php';
require_login();

// 1. Parameter Filter
$currentYear = (int)date('Y');
$currentMonth = (int)date('n');

$cabangId = max(1, (int)($_GET['cabang'] ?? 1));
$month = max(1, min(12, (int)($_GET['bulan'] ?? $currentMonth)));
$year = max(2020, min(2035, (int)($_GET['tahun'] ?? $currentYear)));

$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$currentMonthName = $monthNames[$month] ?? date('F');

// 2. Data Cabang & Info Cabang Terpilih
$allCabangs = get_cabang_list();
$currentCabang = null;
foreach ($allCabangs as $cb) {
    if ((int)($cb['id'] ?? 0) === $cabangId) {
        $currentCabang = $cb;
        break;
    }
}
if (!$currentCabang && !empty($allCabangs)) {
    $currentCabang = $allCabangs[0];
    $cabangId = (int)$currentCabang['id'];
}
$namaCabang = $currentCabang['nama_cabang'] ?? $currentCabang['nama'] ?? ('Cabang #' . $cabangId);

// 3. Ambil seluruh aset di cabang terpilih beserta posisi tersimpan
$assets = [];
try {
    $hasAdp = db()->query("SHOW TABLES LIKE 'asset_desk_positions'")->fetch();
    $cCab = name_column('cabang') ?: 'id';
    $cDiv = name_column('divisi') ?: 'id';
    $cKar = name_column('karyawan') ?: 'id';

    if ($hasAdp) {
        $sql = "
            SELECT a.*, 
                   c.`{$cCab}` AS cabang_nama, 
                   d.`{$cDiv}` AS divisi_nama, 
                   k.`{$cKar}` AS karyawan_nama,
                   q.token AS qr_token,
                   adp.zone_key AS custom_zone_key,
                   adp.sort_order AS custom_sort_order
            FROM assets a
            LEFT JOIN cabang c ON c.id = a.id_cabang
            LEFT JOIN divisi d ON d.id = a.id_divisi
            LEFT JOIN karyawan k ON k.id = a.id_karyawan
            LEFT JOIN asset_qr_tokens q ON q.asset_id = a.id
            LEFT JOIN asset_desk_positions adp ON adp.asset_id = a.id AND adp.cabang_id = a.id_cabang
            WHERE a.id_cabang = ?
            ORDER BY COALESCE(adp.sort_order, 9999) ASC, d.`{$cDiv}` ASC, a.status ASC, a.kode_inventaris ASC
        ";
    } else {
        $sql = asset_query_base() . " WHERE a.id_cabang = ? ORDER BY d.nama_divisi ASC, a.status ASC, a.kode_inventaris ASC";
    }

    $st = db()->prepare($sql);
    $st->execute([$cabangId]);
    $assets = $st->fetchAll();
} catch (Throwable $e) {
    $assets = [];
}

// 4. Ambil status maintenance bulan ini untuk seluruh aset di cabang ini
$assetIds = array_filter(array_map(function($a) { return (int)($a['id'] ?? 0); }, $assets));
$maintStatusMap = [];
if (!empty($assetIds) && !is_google_cloud_mode()) {
    try {
        $idList = implode(',', $assetIds);
        $scans = db()->query("
            SELECT asset_id, status, maintenance_date, examiner_name, finding_notes 
            FROM maintenance_scan 
            WHERE asset_id IN ({$idList}) AND maintenance_month = {$month} AND maintenance_year = {$year}
            ORDER BY id DESC
        ")->fetchAll();
        foreach ($scans as $sc) {
            $aid = (int)$sc['asset_id'];
            if (!isset($maintStatusMap[$aid])) {
                $maintStatusMap[$aid] = $sc;
            }
        }
    } catch (Throwable $e) {}
}

// 5. Hitung Statistik Cabang
$statTotal = count($assets);
$statDone = 0;
$statPending = 0;
$statRepair = 0;
$statInactive = 0;

foreach ($assets as $a) {
    $aid = (int)($a['id'] ?? 0);
    $stAsetRaw = strtolower(trim((string)($a['status'] ?? 'Aktif')));
    $isAktif = ($stAsetRaw === 'aktif' || $stAsetRaw === '');

    if (!$isAktif) {
        $statInactive++;
        continue;
    }

    $m = $maintStatusMap[$aid] ?? null;
    if ($m) {
        $mStatus = $m['status'] ?? 'Selesai';
        if (in_array($mStatus, ['Temuan', 'Perlu Perbaikan', 'Proses'], true)) {
            $statRepair++;
        } else {
            $statDone++;
        }
    } else {
        $statPending++;
    }
}

// 6. Definisi Default & Muat Zona Ruangan Kantor Bank dari Database
$defaultZonesDef = [
    'front_office' => [
        'title' => 'ZONA 1 · FRONT OFFICE & PELAYANAN NASABAH',
        'subtitle' => 'Counter Kasir / Teller, Customer Service (CS) & Meja Operasional Depan',
        'icon' => 'bi-shop',
        'color' => '#0284C7',
        'bg_light' => '#F0F9FF',
        'border_color' => '#BAE6FD',
        'sort_order' => 1,
    ],
    'kredit' => [
        'title' => 'ZONA 2 · AREA KREDIT, ANALIS & PEMBIAYAAN',
        'subtitle' => 'Meja Analis Kredit, Account Officer (AO), Remedial & Collection',
        'icon' => 'bi-briefcase',
        'color' => '#D97706',
        'bg_light' => '#FFFBEB',
        'border_color' => '#FDE68A',
        'sort_order' => 2,
    ],
    'back_office' => [
        'title' => 'ZONA 3 · BACK OFFICE, AKUNTANSI & KEPATUHAN',
        'subtitle' => 'Meja Akuntansi, Pembukuan, SKAI, Kepatuhan, ICU & SDM/Umum',
        'icon' => 'bi-journal-check',
        'color' => '#059669',
        'bg_light' => '#ECFDF5',
        'border_color' => '#A7F3D0',
        'sort_order' => 3,
    ],
    'it_server' => [
        'title' => 'ZONA 4 · RUANG TEKNOLOGI INFORMASI (IT) & SERVER',
        'subtitle' => 'Workstation Staf IT / MIS, Rak Switch Jaringan & Server Lokal',
        'icon' => 'bi-hdd-network',
        'color' => '#4F46E5',
        'bg_light' => '#EEF2FF',
        'border_color' => '#C7D2FE',
        'sort_order' => 4,
    ],
    'pimpinan' => [
        'title' => 'ZONA 5 · RUANG PIMPINAN & RAPAT',
        'subtitle' => 'Ruang Rapat Utama, Meja Direksi & Pimpinan Cabang',
        'icon' => 'bi-person-badge',
        'color' => '#9333EA',
        'bg_light' => '#FAF5FF',
        'border_color' => '#E9D5FF',
        'sort_order' => 5,
    ],
    'other' => [
        'title' => 'ZONA 6 · WORKSTATION LAINNYA / CADANGAN',
        'subtitle' => 'Perangkat Arsip, Laptop Pool & Fasilitas Umum',
        'icon' => 'bi-grid',
        'color' => '#64748B',
        'bg_light' => '#F8FAFC',
        'border_color' => '#E2E8F0',
        'sort_order' => 6,
    ],
];

// Cek apakah tabel floor_plan_zones ada dan ambil daftar zona untuk cabang terpilih
$zones = [];
try {
    $hasFpz = db()->query("SHOW TABLES LIKE 'floor_plan_zones'")->fetch();
    if ($hasFpz) {
        $stZones = db()->prepare("SELECT * FROM floor_plan_zones WHERE cabang_id = ? ORDER BY sort_order ASC, id ASC");
        $stZones->execute([$cabangId]);
        $rows = $stZones->fetchAll();

        // Jika belum ada zona sama sekali untuk cabang ini, buatkan default 6 zona
        if (empty($rows)) {
            $insZ = db()->prepare("
                INSERT INTO floor_plan_zones 
                (cabang_id, zone_key, title, subtitle, icon, color, bg_light, border_color, sort_order, is_default)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            foreach ($defaultZonesDef as $k => $def) {
                $insZ->execute([
                    $cabangId,
                    $k,
                    $def['title'],
                    $def['subtitle'],
                    $def['icon'],
                    $def['color'],
                    $def['bg_light'],
                    $def['border_color'],
                    $def['sort_order']
                ]);
            }
            $stZones->execute([$cabangId]);
            $rows = $stZones->fetchAll();
        }

        foreach ($rows as $r) {
            $zk = $r['zone_key'];
            $zones[$zk] = [
                'id' => (int)$r['id'],
                'zone_key' => $zk,
                'title' => $r['title'],
                'subtitle' => $r['subtitle'] ?? '',
                'icon' => $r['icon'] ?? 'bi-grid',
                'color' => $r['color'] ?? '#0284C7',
                'bg_light' => $r['bg_light'] ?? '#F0F9FF',
                'border' => $r['border_color'] ?? '#BAE6FD',
                'desks' => []
            ];
        }
    }
} catch (Throwable $e) {}

if (empty($zones)) {
    // Fallback jika database belum ada
    foreach ($defaultZonesDef as $k => $def) {
        $zones[$k] = [
            'id' => 0,
            'zone_key' => $k,
            'title' => $def['title'],
            'subtitle' => $def['subtitle'],
            'icon' => $def['icon'],
            'color' => $def['color'],
            'bg_light' => $def['bg_light'],
            'border' => $def['border_color'],
            'desks' => []
        ];
    }
}

// Kelompokkan Aset ke Zona: prioritaskan custom_zone_key jika pernah di-drag & drop
foreach ($assets as $a) {
    $customZone = trim((string)($a['custom_zone_key'] ?? ''));
    if ($customZone !== '' && isset($zones[$customZone])) {
        $zoneKey = $customZone;
    } else {
        // Otomatis berdasarkan nama divisi / ruangan / pemegang
        $divisi = strtolower((string)($a['divisi_nama'] ?? ''));
        $ruangan = strtolower((string)($a['ruangan'] ?? ''));
        $pemegang = (string)($a['karyawan_nama'] ?? 'Umum');
        $pemegangLower = strtolower($pemegang);

        $targetKey = 'other';
        if (str_contains($divisi, 'it') || str_contains($divisi, 'mis') || str_contains($ruangan, 'server') || str_contains($ruangan, 'it')) {
            $targetKey = 'it_server';
        } elseif (str_contains($divisi, 'direksi') || str_contains($pemegangLower, 'pimpinan') || str_contains($pemegangLower, 'kepala cabang') || str_contains($ruangan, 'direksi') || str_contains($ruangan, 'pimpinan') || str_contains($ruangan, 'rapat') || str_contains($pemegangLower, 'kadiv') || str_contains($pemegangLower, 'meeting')) {
            $targetKey = 'pimpinan';
        } elseif (str_contains($divisi, 'kredit') || str_contains($divisi, 'analis') || str_contains($divisi, 'collect') || str_contains($divisi, 'remedial') || str_contains($divisi, 'bisnis')) {
            $targetKey = 'kredit';
        } elseif (str_contains($divisi, 'teller') || str_contains($divisi, 'kasir') || str_contains($divisi, 'customer') || str_contains($divisi, 'cs') || str_contains($divisi, 'operasional') || str_contains($ruangan, 'teller') || str_contains($ruangan, 'kasir') || str_contains($ruangan, 'cs')) {
            $targetKey = 'front_office';
        } elseif (str_contains($divisi, 'akunting') || str_contains($divisi, 'akuntansi') || str_contains($divisi, 'kepatuhan') || str_contains($divisi, 'skai') || str_contains($divisi, 'icu') || str_contains($divisi, 'sdm') || str_contains($divisi, 'umum') || str_contains($divisi, 'pembukuan')) {
            $targetKey = 'back_office';
        }

        if (isset($zones[$targetKey])) {
            $zoneKey = $targetKey;
        } else {
            $allKeys = array_keys($zones);
            $zoneKey = $allKeys[0] ?? 'other';
        }
    }

    if (isset($zones[$zoneKey])) {
        $zones[$zoneKey]['desks'][] = $a;
    }
}

// Urutkan meja dalam tiap zona berdasarkan custom_sort_order jika ada
foreach ($zones as $zKey => &$z) {
    usort($z['desks'], function($d1, $d2) {
        $o1 = isset($d1['custom_sort_order']) && $d1['custom_sort_order'] !== null ? (int)$d1['custom_sort_order'] : 9999;
        $o2 = isset($d2['custom_sort_order']) && $d2['custom_sort_order'] !== null ? (int)$d2['custom_sort_order'] : 9999;
        if ($o1 === $o2) {
            return strcmp((string)($d1['kode_inventaris'] ?? ''), (string)($d2['kode_inventaris'] ?? ''));
        }
        return $o1 <=> $o2;
    });
}
unset($z);

// 7. Filter options dropdown Cabang
$optCabang = '';
foreach ($allCabangs as $cb) {
    $cid = (int)($cb['id'] ?? 0);
    $cName = $cb['nama_cabang'] ?? $cb['nama'] ?? ('Cabang #' . $cid);
    $sel = ($cid === $cabangId) ? ' selected' : '';
    $optCabang .= '<option value="'.$cid.'"'.$sel.'>'.e($cName).'</option>';
}

// 8. Filter options dropdown Bulan
$optBulan = '';
foreach ($monthNames as $mNum => $mLabel) {
    $sel = ($mNum === $month) ? ' selected' : '';
    $optBulan .= '<option value="'.$mNum.'"'.$sel.'>'.e($mLabel).'</option>';
}

// 9. Extra CSS Styling untuk Denah & Direct Drag & Drop
$headStyle = '
<style>
/* Floor Plan Styles */
.floor-zone {
  background: #FFFFFF;
  border-radius: 14px;
  border: 1.5px solid #E2E8F0;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
  transition: all 0.25s ease;
  overflow: hidden;
  margin-bottom: 26px;
}
.floor-zone-header {
  padding: 14px 20px;
  border-bottom: 1px solid rgba(0,0,0,0.06);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.floor-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
  gap: 14px;
  padding: 18px;
  min-height: 96px;
  transition: background-color 0.2s ease, outline 0.2s ease;
}
@media (max-width: 576px) {
  .floor-grid {
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 10px;
    padding: 12px;
  }
}

/* Desk Workstation Card - Draggable via SortableJS */
.desk-card {
  background: #FFFFFF;
  border: 1.5px solid #CBD5E1;
  border-radius: 10px;
  padding: 12px;
  cursor: grab;
  position: relative;
  transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease, opacity 0.2s ease;
  user-select: none;
  -webkit-user-select: none;
  touch-action: none;
  box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
}
.desk-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 20px rgba(15, 23, 42, 0.09);
  border-color: #3B82F6;
}
.desk-card:active {
  cursor: grabbing;
}
.desk-card.status-done {
  border-top: 4px solid #10B981;
}
.desk-card.status-pending {
  border-top: 4px solid #F59E0B;
}
.desk-card.status-repair {
  border-top: 4px solid #EF4444;
}
.desk-card.status-inactive {
  border-top: 4px solid #94A3B8;
  background-color: #F8FAFC;
}

.desk-card.is-dimmed {
  opacity: 0.25;
  filter: grayscale(80%);
}

/* SortableJS Ghost & Drag Styles */
.sortable-ghost {
  opacity: 0.35 !important;
  border: 2px dashed #2563EB !important;
  background-color: rgba(37, 99, 235, 0.08) !important;
  box-shadow: none !important;
}
.sortable-chosen {
  cursor: grabbing !important;
  border-color: #2563EB !important;
}
.sortable-drag {
  opacity: 0.95 !important;
  transform: rotate(1.5deg) scale(1.02) !important;
  box-shadow: 0 20px 40px rgba(15, 23, 42, 0.25) !important;
  z-index: 99999 !important;
}

.drag-handle {
  display: inline-block;
  opacity: 0.55;
  cursor: grab;
  transition: opacity 0.15s ease;
}
.desk-card:hover .drag-handle {
  opacity: 1;
}

.floor-grid.drag-over {
  background-color: rgba(59, 130, 246, 0.08);
  outline: 2px dashed #3B82F6;
  outline-offset: -2px;
  border-radius: 8px;
}

.empty-zone-placeholder {
  grid-column: 1 / -1;
  border: 2px dashed #CBD5E1;
  border-radius: 8px;
  padding: 24px;
  text-align: center;
  color: #64748B;
  font-size: 0.85rem;
  background-color: rgba(248, 250, 252, 0.6);
  pointer-events: none;
}

.desk-icon-box {
  width: 38px;
  height: 38px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.15rem;
}

.pulse-indicator {
  display: inline-block;
  width: 9px;
  height: 9px;
  border-radius: 50%;
}
.pulse-done { background-color: #10B981; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2); }
.pulse-pending { background-color: #F59E0B; box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2); }
.pulse-repair { background-color: #EF4444; box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.3); animation: pulseAnim 1.8s infinite; }
.pulse-inactive { background-color: #94A3B8; }

@keyframes pulseAnim {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.6); }
  70% { transform: scale(1.1); box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

@media print {
  .no-print { display: none !important; }
  .floor-zone { page-break-inside: avoid; border: 1px solid #94A3B8 !important; }
  .desk-card { box-shadow: none !important; border: 1px solid #CBD5E1 !important; cursor: default !important; }
  .drag-handle { display: none !important; }
  .btn-delete-zone { display: none !important; }
}
</style>
';

// Helper function untuk merender kartu meja HTML
function render_desk_card_html(array $ast, int $deskCounter, array $maintStatusMap, string $namaCabang, string $zKey): string {
    $aid = (int)($ast['id'] ?? 0);
    $kode = (string)($ast['kode_inventaris'] ?? '-');
    $merk = (string)($ast['merk'] ?? 'Komputer');
    $model = (string)($ast['model'] ?? '');
    $pemegang = (string)($ast['karyawan_nama'] ?? 'Umum / Pool');
    $divisi = (string)($ast['divisi_nama'] ?? '-');
    $ip = (string)($ast['ip_address'] ?? '');
    $token = (string)($ast['qr_token'] ?? '');
    $statusAset = strtolower(trim((string)($ast['status'] ?? 'Aktif')));
    $isAktif = ($statusAset === 'aktif' || $statusAset === '');

    $m = $maintStatusMap[$aid] ?? null;
    $mStatus = $m ? ($m['status'] ?? 'Selesai') : 'Belum Diperiksa';
    $mDate = $m ? ($m['maintenance_date'] ?? '') : '';
    $mExaminer = $m ? ($m['examiner_name'] ?? '') : '';
    $mNotes = $m ? ($m['finding_notes'] ?? '') : '';

    if (!$isAktif) {
        $cardClass = 'status-inactive';
        $statusCat = 'inactive';
        $badgeText = ucfirst($statusAset);
        $badgeBg = 'bg-secondary text-white';
        $dotClass = 'pulse-inactive';
    } elseif ($m) {
        if (in_array($mStatus, ['Temuan', 'Perlu Perbaikan', 'Proses'], true)) {
            $cardClass = 'status-repair';
            $statusCat = 'repair';
            $badgeText = 'Temuan Kendala';
            $badgeBg = 'bg-danger text-white';
            $dotClass = 'pulse-repair';
        } else {
            $cardClass = 'status-done';
            $statusCat = 'done';
            $badgeText = 'Selesai Maint';
            $badgeBg = 'bg-success text-white';
            $dotClass = 'pulse-done';
        }
    } else {
        $cardClass = 'status-pending';
        $statusCat = 'pending';
        $badgeText = 'Belum Diperiksa';
        $badgeBg = 'bg-warning text-dark';
        $dotClass = 'pulse-pending';
    }

    $iconClass = 'bi-pc-display';
    if (stripos($merk . ' ' . $model, 'laptop') !== false || stripos($kode, 'lap') !== false) {
        $iconClass = 'bi-laptop';
    } elseif (stripos($merk . ' ' . $model, 'printer') !== false) {
        $iconClass = 'bi-printer';
    } elseif (stripos($merk . ' ' . $model, 'server') !== false) {
        $iconClass = 'bi-hdd-rack';
    }

    $modalData = [
        'id' => $aid,
        'kode' => $kode,
        'title' => trim($merk . ' ' . $model),
        'pemegang' => $pemegang,
        'divisi' => $divisi,
        'cabang' => $namaCabang,
        'ip' => $ip ?: '-',
        'status_aset' => ucfirst($statusAset),
        'status_maint' => $badgeText,
        'maint_date' => $mDate ? format_id_date($mDate) : '-',
        'examiner' => $mExaminer ?: '-',
        'notes' => $mNotes ?: 'Tidak ada catatan temuan.',
        'token' => $token,
        'foto' => !empty($ast['foto']) ? $ast['foto'] : '',
        'scan_url' => $token ? qr_url('scan.php', ['t' => $token]) : '',
        'edit_url' => module_url('asset_edit.php', ['id' => $aid]),
        'replace_url' => module_url('asset_add.php', ['replace_id' => $aid])
    ];
    $jsonAttr = htmlspecialchars(json_encode($modalData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');

    return '
    <div class="desk-card '.$cardClass.'" data-id="'.$aid.'" data-zone="'.e($zKey).'" data-status="'.$statusCat.'" data-json="'.$jsonAttr.'" onclick="handleDeskCardClick(this, event)">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="d-flex align-items-center gap-1.5">
          <span class="drag-handle me-1 text-secondary" title="Geser langsung untuk memindahkan meja"><i class="bi bi-grip-vertical"></i></span>
          <span class="pulse-indicator '.$dotClass.'"></span>
          <span class="badge '.$badgeBg.' py-0.5 px-2 font-monospace" style="font-size: 0.65rem;">'.$badgeText.'</span>
        </div>
        <div class="text-secondary small font-monospace fw-bold desk-seq-num" style="font-size: 0.72rem;">#'.$deskCounter.'</div>
      </div>

      <div class="d-flex align-items-start gap-2 mb-2">
        <div class="desk-icon-box bg-light text-primary flex-shrink-0">
          <i class="bi '.$iconClass.'"></i>
        </div>
        <div class="overflow-hidden flex-fill">
          <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem;" title="'.e($pemegang).'">'.e($pemegang).'</div>
          <div class="text-secondary text-truncate" style="font-size: 0.73rem;">'.e($divisi).'</div>
        </div>
      </div>

      <div class="border-top pt-2 mt-1 d-flex align-items-center justify-content-between" style="font-size: 0.72rem;">
        <div class="text-muted font-monospace text-truncate me-1" title="'.e($kode).'">
          <i class="bi bi-upc me-1"></i>'.e($kode).'
        </div>
        '.($ip ? '<span class="text-success font-monospace" title="IP: '.e($ip).'"><i class="bi bi-hdd-network"></i></span>' : '').'
      </div>
    </div>';
}

// 10. Generate HTML untuk Tiap Zona & Meja
$zonesHtml = '';
$deskCounter = 0;

foreach ($zones as $zKey => $z) {
    $desks = $z['desks'];
    $deskCardsHtml = '';
    foreach ($desks as $ast) {
        $deskCounter++;
        $deskCardsHtml .= render_desk_card_html($ast, $deskCounter, $maintStatusMap, $namaCabang, $zKey);
    }

    $isZoneEmpty = (count($desks) === 0);
    $placeholderDisplay = $isZoneEmpty ? '' : 'd-none';
    $deleteBtnDisplay = $isZoneEmpty ? 'd-inline-flex' : 'd-none';

    $zonesHtml .= '
    <div class="floor-zone" id="zone-card-'.e($zKey).'" data-zone-key="'.e($zKey).'" style="border-left: 5px solid '.e($z['color']).';">
      <div class="floor-zone-header" style="background-color: '.e($z['bg_light']).';">
        <div class="d-flex align-items-center gap-2">
          <div class="fs-5 zone-icon-disp" style="color: '.e($z['color']).';"><i class="bi '.e($z['icon']).'"></i></div>
          <div>
            <h6 class="fw-bold mb-0 zone-title-disp" style="color: #0F172A; font-size: 0.95rem;">'.e($z['title']).'</h6>
            <div class="small text-muted zone-subtitle-disp" style="font-size: 0.75rem;">'.e($z['subtitle']).'</div>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <!-- Tombol Edit Nama / Detail Ruangan -->
          <button type="button" 
                  class="btn btn-outline-secondary btn-sm py-1 px-2.5 fw-semibold btn-edit-zone no-print" 
                  data-zone-key="'.e($zKey).'" 
                  data-zone-title="'.e($z['title']).'" 
                  data-zone-subtitle="'.e($z['subtitle']).'" 
                  data-zone-color="'.e($z['color']).'" 
                  data-zone-icon="'.e($z['icon']).'" 
                  onclick="openEditZoneModal(this)"
                  title="Ubah nama ruangan, keterangan, atau warna zona">
            <i class="bi bi-pencil me-1"></i> Edit Zona
          </button>

          <!-- Tombol Hapus Zona: Tampil bila zona kosong (0 meja) -->
          <button type="button" 
                  class="btn btn-outline-danger btn-sm py-1 px-2.5 fw-semibold btn-delete-zone '.$deleteBtnDisplay.'" 
                  data-zone-key="'.e($zKey).'" 
                  onclick="deleteEmptyZone(\''.e($zKey).'\')"
                  title="Hapus ruangan ini dari denah karena sudah tidak ada meja">
            <i class="bi bi-trash3 me-1"></i> Hapus Zona Kosong
          </button>

          <div class="badge rounded-pill px-3 py-1.5 fw-semibold zone-count-badge" style="background-color: #FFFFFF; color: '.e($z['color']).'; border: 1px solid '.e($z['border']).'; font-size: 0.75rem;">
            <span class="count-num">'.count($desks).'</span> Meja Workstation
          </div>
        </div>
      </div>
      <div class="floor-grid" data-zone-key="'.e($zKey).'">
        '.$deskCardsHtml.'
        <div class="empty-zone-placeholder '.$placeholderDisplay.'"><i class="bi bi-arrow-down-circle me-1"></i>Ruangan ini kosong. Geser dan letakkan meja di sini.</div>
      </div>
    </div>';
}

// 11. Modal Popup Detail Meja
$modalHtml = '
<div class="modal fade" id="modalDeskDetail" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-dark text-white py-3 px-4">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-display fs-5 text-info"></i>
          <div>
            <h6 class="modal-title fw-bold mb-0" id="deskModalTitle">Detail Komputer Workstation</h6>
            <div class="small text-white-50" style="font-size: 0.72rem;" id="deskModalSub">Lokasi Meja Kerja</div>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-4">
        <!-- Foto & Header Profil -->
        <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
          <div id="deskFotoBox" class="rounded-3 bg-light border d-flex align-items-center justify-content-center overflow-hidden shadow-xs" style="width: 76px; height: 76px; min-width: 76px;">
            <i class="bi bi-pc-display text-secondary fs-1"></i>
          </div>
          <div class="overflow-hidden flex-fill">
            <span class="badge py-1 px-2.5 rounded-pill mb-1 fw-bold" id="deskBadgeMaint">Status Maint</span>
            <h5 class="fw-bold text-dark mb-0 text-truncate" id="deskPemegang">-</h5>
            <div class="small text-secondary" id="deskDivisiCabang">-</div>
          </div>
        </div>

        <!-- Spesifikasi & Detail -->
        <div class="row g-2 mb-3 small">
          <div class="col-6">
            <div class="p-2 rounded bg-light border">
              <div class="text-muted" style="font-size: 0.7rem;">Nomor Inventaris:</div>
              <div class="fw-bold text-primary font-monospace text-truncate" id="deskKode">-</div>
            </div>
          </div>
          <div class="col-6">
            <div class="p-2 rounded bg-light border">
              <div class="text-muted" style="font-size: 0.7rem;">IP Address Jaringan:</div>
              <div class="fw-bold text-dark font-monospace text-truncate" id="deskIp">-</div>
            </div>
          </div>
          <div class="col-6">
            <div class="p-2 rounded bg-light border">
              <div class="text-muted" style="font-size: 0.7rem;">Tanggal Inspeksi:</div>
              <div class="fw-bold text-dark" id="deskTgl">-</div>
            </div>
          </div>
          <div class="col-6">
            <div class="p-2 rounded bg-light border">
              <div class="text-muted" style="font-size: 0.7rem;">Petugas Pemeriksa:</div>
              <div class="fw-bold text-dark text-truncate" id="deskExaminer">-</div>
            </div>
          </div>
        </div>

        <!-- Catatan Temuan / Hasil Periksa -->
        <div class="p-2.5 rounded-3 border bg-light mb-4">
          <div class="text-secondary fw-semibold mb-1" style="font-size: 0.73rem;"><i class="bi bi-chat-left-text me-1"></i>Catatan Pemeliharaan Bulan Ini:</div>
          <div class="text-dark small" id="deskNotes" style="font-size: 0.82rem; line-height: 1.4;">-</div>
        </div>

        <!-- Tombol Aksi Cepat -->
        <div class="d-grid gap-2">
          <a id="btnDeskScan" href="#" class="btn btn-success fw-semibold py-2 shadow-xs d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-qr-code-scan"></i> Buka Kartu Kontrol / Checklist Pemeliharaan
          </a>
          <div class="d-flex gap-2">
            <a id="btnDeskEdit" href="#" class="btn btn-outline-secondary btn-sm fw-semibold flex-fill py-2">
              <i class="bi bi-pencil-square me-1"></i> Edit Data Meja
            </a>
            <a id="btnDeskReplace" href="#" class="btn btn-outline-primary btn-sm fw-semibold flex-fill py-2">
              <i class="bi bi-arrow-repeat me-1"></i> Ganti Unit Baru
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Tambah Ruangan / Zona Baru -->
<div class="modal fade" id="modalAddZone" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-primary text-white py-3 px-4">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-plus-circle-fill fs-5"></i>
          <div>
            <h6 class="modal-title fw-bold mb-0">Tambah Ruangan / Zona Baru</h6>
            <div class="small text-white-50" style="font-size: 0.72rem;">Denah Kantor &bull; '.e($namaCabang).'</div>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form id="formAddZone" onsubmit="submitAddZone(event)">
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Nama Ruangan / Zona <span class="text-danger">*</span></label>
            <input type="text" id="newZoneTitle" class="form-control" placeholder="Contoh: ZONA 7 · RUANG CUSTOMER SERVICE" required>
            <div class="form-text" style="font-size: 0.72rem;">Tulis nama ruangan atau unit kerja denah.</div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Keterangan / Subtitle</label>
            <input type="text" id="newZoneSubtitle" class="form-control" placeholder="Contoh: Meja Pelayanan Nasabah, Pembukaan Rekening & Pengaduan">
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold text-dark">Tema Warna</label>
              <select id="newZoneColor" class="form-select">
                <option value="#0284C7">🔵 Biru Samudera</option>
                <option value="#D97706">🟠 Oranye Amber</option>
                <option value="#059669">🟢 Hijau Emerald</option>
                <option value="#4F46E5">🟣 Indigo Royal</option>
                <option value="#9333EA">🟪 Ungu Purple</option>
                <option value="#DC2626">🔴 Merah Coral</option>
                <option value="#64748B">⚪ Abu-abu Slate</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold text-dark">Ikon Ruangan</label>
              <select id="newZoneIcon" class="form-select">
                <option value="bi-door-closed">🚪 Pintu / Ruang Umum</option>
                <option value="bi-shop">🏦 Front Office / Teller</option>
                <option value="bi-briefcase">💼 Kredit / Bisnis</option>
                <option value="bi-journal-check">📋 Akuntansi / SKAI</option>
                <option value="bi-hdd-network">🖥️ IT / Server</option>
                <option value="bi-person-badge">👔 Direksi / Pimpinan</option>
                <option value="bi-people">👥 Ruang Rapat / Tamu</option>
                <option value="bi-archive">📦 Ruang Arsip / Gudang</option>
                <option value="bi-grid">🔲 Ruang Lainnya</option>
              </select>
            </div>
          </div>

          <div class="p-2.5 rounded-3 border bg-light text-muted small" style="font-size: 0.75rem;">
            <i class="bi bi-info-circle text-primary me-1"></i> Setelah ruangan ditambahkan, Anda dapat langsung <strong>menyeret (drag &amp; drop)</strong> kartu meja ke ruangan ini. Jika ruangan kosong, tombol <strong>Hapus Zona</strong> akan muncul otomatis.
          </div>
        </div>

        <div class="modal-footer bg-light px-4 py-2.5 border-top">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
          <button type="submit" id="btnSubmitZone" class="btn btn-primary btn-sm fw-bold px-3">
            <i class="bi bi-check-lg me-1"></i> Simpan Ruangan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Ubah / Edit Nama & Informasi Ruangan -->
<div class="modal fade" id="modalEditZone" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-dark text-white py-3 px-4">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-pencil-square fs-5 text-warning"></i>
          <div>
            <h6 class="modal-title fw-bold mb-0">Ubah Nama &amp; Informasi Ruangan</h6>
            <div class="small text-white-50" style="font-size: 0.72rem;">Denah Kantor &bull; '.e($namaCabang).'</div>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form id="formEditZone" onsubmit="submitEditZone(event)">
        <input type="hidden" id="editZoneKey" value="">
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Nama Ruangan / Zona <span class="text-danger">*</span></label>
            <input type="text" id="editZoneTitle" class="form-control fw-semibold" placeholder="Contoh: ZONA 1 · FRONT OFFICE &amp; PELAYANAN NASABAH" required>
            <div class="form-text" style="font-size: 0.72rem;">Ubah nama zona sesuai kebutuhan operasional cabang Anda.</div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Keterangan / Subtitle</label>
            <input type="text" id="editZoneSubtitle" class="form-control" placeholder="Contoh: Counter Kasir / Teller, Customer Service (CS) &amp; Meja Operasional Depan">
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold text-dark">Tema Warna</label>
              <select id="editZoneColor" class="form-select">
                <option value="#0284C7">🔵 Biru Samudera</option>
                <option value="#D97706">🟠 Oranye Amber</option>
                <option value="#059669">🟢 Hijau Emerald</option>
                <option value="#4F46E5">🟣 Indigo Royal</option>
                <option value="#9333EA">🟪 Ungu Purple</option>
                <option value="#DC2626">🔴 Merah Coral</option>
                <option value="#64748B">⚪ Abu-abu Slate</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold text-dark">Ikon Ruangan</label>
              <select id="editZoneIcon" class="form-select">
                <option value="bi-door-closed">🚪 Pintu / Ruang Umum</option>
                <option value="bi-shop">🏦 Front Office / Teller</option>
                <option value="bi-briefcase">💼 Kredit / Bisnis</option>
                <option value="bi-journal-check">📋 Akuntansi / SKAI</option>
                <option value="bi-hdd-network">🖥️ IT / Server</option>
                <option value="bi-person-badge">👔 Direksi / Pimpinan</option>
                <option value="bi-people">👥 Ruang Rapat / Tamu</option>
                <option value="bi-archive">📦 Ruang Arsip / Gudang</option>
                <option value="bi-grid">🔲 Ruang Lainnya</option>
              </select>
            </div>
          </div>
        </div>

        <div class="modal-footer bg-light px-4 py-2.5 border-top">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
          <button type="submit" id="btnSubmitEditZone" class="btn btn-primary btn-sm fw-bold px-3">
            <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Floating Auto-Save Toast Notification -->
<div class="position-fixed bottom-0 end-0 p-3 no-print" style="z-index: 1090;">
  <div id="saveToast" class="toast align-items-center text-white bg-dark border-0 shadow-lg rounded-3" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex align-items-center">
      <div class="toast-body d-flex align-items-center gap-2 py-2.5 px-3">
        <div id="saveToastSpinner" class="spinner-border spinner-border-sm text-info d-none" role="status"></div>
        <i id="saveToastIcon" class="bi bi-check-circle-fill text-success fs-5"></i>
        <div>
          <div class="fw-bold" style="font-size: 0.85rem;" id="saveToastTitle">Posisi Meja Otomatis Tersimpan</div>
          <div class="small text-white-50" style="font-size: 0.73rem;" id="saveToastSub">Perubahan tata letak langsung diperbarui ke database.</div>
        </div>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>
';

// 12. Main Body Content
$body = '
<div class="container-fluid px-2 px-md-3 py-2">
  <!-- Header & Toolbar -->
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
        <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-2.5 py-1 rounded-pill" style="font-size: 0.75rem;">
          <i class="bi bi-buildings me-1"></i> VISUAL WORKSTATION MAP
        </span>
        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1 rounded-pill" style="font-size: 0.75rem;">
          <i class="bi bi-arrows-move me-1"></i> Drag &amp; Drop Aktif (Auto-Save)
        </span>
        <span class="text-muted small">&bull; Periode '.$currentMonthName.' '.$year.'</span>
      </div>
      <h3 class="fw-bold text-dark mb-0">Denah Meja Kantor Cabang</h3>
      <div class="text-secondary small">Tata letak meja 2D &bull; Langsung geser meja untuk atur posisi &bull; Tambah ruangan baru atau hapus ruangan yang kosong &bull; Klik meja untuk detail.</div>
    </div>

    <!-- Pilihan Cabang, Tambah Ruangan, Reset & Cetak -->
    <div class="d-flex align-items-center gap-2 no-print flex-wrap">
      <form method="GET" class="d-flex align-items-center gap-2 m-0">
        <select name="cabang" class="form-select form-select-sm fw-semibold border-primary shadow-xs" style="min-width: 200px;" onchange="this.form.submit()">
          '.$optCabang.'
        </select>
        <select name="bulan" class="form-select form-select-sm shadow-xs" onchange="this.form.submit()">
          '.$optBulan.'
        </select>
        <input type="hidden" name="tahun" value="'.$year.'">
      </form>

      <button type="button" class="btn btn-primary btn-sm fw-semibold shadow-xs" data-bs-toggle="modal" data-bs-target="#modalAddZone" title="Tambah ruangan atau zona baru di cabang ini">
        <i class="bi bi-plus-circle me-1"></i> Tambah Ruangan / Zona
      </button>

      <button type="button" class="btn btn-outline-secondary btn-sm fw-semibold shadow-xs" onclick="resetDeskPositions()" title="Kembalikan urutan tata letak ke bawaan divisi">
        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Urutan
      </button>

      <button type="button" class="btn btn-outline-dark btn-sm fw-semibold shadow-xs" onclick="window.print()" title="Cetak Denah Meja A4">
        <i class="bi bi-printer me-1"></i> Cetak Denah
      </button>
    </div>
  </div>

  <!-- Metric Summary Cards -->
  <div class="row g-2 g-md-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card p-3 border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #003B73 !important;">
        <div class="text-secondary small fw-semibold" style="font-size: 0.72rem;">TOTAL WORKSTATION</div>
        <div class="fw-bold text-dark fs-4 my-1">'.$statTotal.' <span class="fs-6 text-muted fw-normal">Meja</span></div>
        <div class="small text-muted" style="font-size: 0.7rem;"><i class="bi bi-geo-alt me-1"></i>'.e($namaCabang).'</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card p-3 border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #10B981 !important;">
        <div class="text-success small fw-semibold" style="font-size: 0.72rem;">SUDAH DIPERIKSA</div>
        <div class="fw-bold text-success fs-4 my-1">'.$statDone.' <span class="fs-6 text-muted fw-normal">Unit</span></div>
        <div class="small text-muted" style="font-size: 0.7rem;">Tertib bulan ini</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card p-3 border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #F59E0B !important;">
        <div class="text-warning small fw-semibold" style="font-size: 0.72rem;">BELUM DIPERIKSA</div>
        <div class="fw-bold text-warning fs-4 my-1">'.$statPending.' <span class="fs-6 text-muted fw-normal">Unit</span></div>
        <div class="small text-muted" style="font-size: 0.7rem;">Menunggu inspeksi IT</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card p-3 border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #EF4444 !important;">
        <div class="text-danger small fw-semibold" style="font-size: 0.72rem;">TEMUAN KENDALA</div>
        <div class="fw-bold text-danger fs-4 my-1">'.$statRepair.' <span class="fs-6 text-muted fw-normal">Unit</span></div>
        <div class="small text-muted" style="font-size: 0.7rem;">Perlu perbaikan</div>
      </div>
    </div>
  </div>

  <!-- Filter Cepat Status Lampu Meja -->
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom no-print">
    <div class="d-flex align-items-center gap-1.5 flex-wrap">
      <span class="small fw-semibold text-secondary me-1"><i class="bi bi-funnel me-1"></i>Sorot Status:</span>
      <button type="button" class="btn btn-sm btn-dark rounded-pill px-3 fw-bold filter-btn active" data-filter="all" onclick="filterDeskStatus(\'all\', this)">
        Semua ('.$statTotal.')
      </button>
      <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold filter-btn" data-filter="done" onclick="filterDeskStatus(\'done\', this)">
        <span class="pulse-indicator pulse-done me-1"></span> Sudah Diperiksa ('.$statDone.')
      </button>
      <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3 fw-bold text-dark filter-btn" data-filter="pending" onclick="filterDeskStatus(\'pending\', this)">
        <span class="pulse-indicator pulse-pending me-1"></span> Belum Diperiksa ('.$statPending.')
      </button>
      <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold filter-btn" data-filter="repair" onclick="filterDeskStatus(\'repair\', this)">
        <span class="pulse-indicator pulse-repair me-1"></span> Ada Temuan ('.$statRepair.')
      </button>
      <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold filter-btn" data-filter="inactive" onclick="filterDeskStatus(\'inactive\', this)">
        <span class="pulse-indicator pulse-inactive me-1"></span> Bebas Maint ('.$statInactive.')
      </button>
    </div>
    <div class="text-muted small" style="font-size: 0.78rem;">
      <i class="bi bi-hand-index-thumb me-1 text-primary"></i><strong>Langsung geser kartu meja</strong> antar ruangan &bull; Klik meja untuk melihat spesifikasi &amp; kontrol.
    </div>
  </div>

  <!-- Denah Ruangan Bertingkat / Per Zona -->
  <div id="floorPlanContainer">
    '.$zonesHtml.'
  </div>
</div>

'.$modalHtml.'
';

// 13. Extra Scripts untuk Direct Drag & Drop, Auto-Save, Tambah & Hapus Zona
$sortableJsContent = '';
$sortableFile = __DIR__ . '/sortable.min.js';
if (file_exists($sortableFile)) {
    $sortableJsContent = file_get_contents($sortableFile);
}

$extraScript = '
<script>
' . $sortableJsContent . '
</script>
<script>
let sortableInstances = [];
let isCurrentlyDragging = false;
let dragStartTime = 0;
let autoSaveTimer = null;
let saveToastInstance = null;
const currentCabangId = '.$cabangId.';

function filterDeskStatus(cat, btn) {
  document.querySelectorAll(".filter-btn").forEach(function(b) {
    b.classList.remove("active");
    if (b.dataset.filter === "all") {
      b.className = "btn btn-sm btn-outline-dark rounded-pill px-3 fw-bold filter-btn";
    }
  });

  btn.classList.add("active");
  if (cat === "all") {
    btn.className = "btn btn-sm btn-dark rounded-pill px-3 fw-bold filter-btn active";
  }

  const desks = document.querySelectorAll(".desk-card");
  desks.forEach(function(card) {
    if (cat === "all" || card.dataset.status === cat) {
      card.classList.remove("is-dimmed");
    } else {
      card.classList.add("is-dimmed");
    }
  });
}

function handleDeskCardClick(cardEl, event) {
  if (isCurrentlyDragging || (Date.now() - dragStartTime < 350)) {
    event.preventDefault();
    event.stopPropagation();
    return;
  }
  openDeskModal(cardEl);
}

function openDeskModal(cardEl) {
  const jsonStr = cardEl.getAttribute("data-json");
  if (!jsonStr) return;

  try {
    const data = JSON.parse(jsonStr);
    
    document.getElementById("deskModalTitle").textContent = data.title || "Detail Komputer";
    document.getElementById("deskModalSub").textContent = (data.cabang || "") + " · " + (data.divisi || "");
    document.getElementById("deskPemegang").textContent = data.pemegang || "-";
    document.getElementById("deskDivisiCabang").textContent = (data.divisi || "-") + " · Status Aset: " + (data.status_aset || "Aktif");
    document.getElementById("deskKode").textContent = data.kode || "-";
    document.getElementById("deskIp").textContent = data.ip || "-";
    document.getElementById("deskTgl").textContent = data.maint_date || "-";
    document.getElementById("deskExaminer").textContent = data.examiner || "-";
    document.getElementById("deskNotes").textContent = data.notes || "Kondisi operasional normal.";

    const badgeEl = document.getElementById("deskBadgeMaint");
    badgeEl.textContent = data.status_maint || "Normal";
    badgeEl.className = "badge py-1 px-2.5 rounded-pill mb-1 fw-bold ";
    if (data.status_maint.includes("Selesai")) {
      badgeEl.className += "bg-success text-white";
    } else if (data.status_maint.includes("Temuan")) {
      badgeEl.className += "bg-danger text-white";
    } else if (data.status_maint.includes("Belum")) {
      badgeEl.className += "bg-warning text-dark";
    } else {
      badgeEl.className += "bg-secondary text-white";
    }

    const fotoBox = document.getElementById("deskFotoBox");
    if (data.foto && (data.foto.startsWith("data:image") || data.foto.startsWith("http") || data.foto.startsWith("/"))) {
      fotoBox.innerHTML = \'<img src="\' + data.foto + \'" class="w-100 h-100 object-fit-cover" alt="Foto Unit">\';
    } else {
      fotoBox.innerHTML = \'<i class="bi bi-pc-display text-secondary fs-1"></i>\';
    }

    const btnScan = document.getElementById("btnDeskScan");
    if (data.scan_url) {
      btnScan.href = data.scan_url;
      btnScan.classList.remove("d-none");
    } else {
      btnScan.classList.add("d-none");
    }

    document.getElementById("btnDeskEdit").href = data.edit_url || "#";
    document.getElementById("btnDeskReplace").href = data.replace_url || "#";

    const modal = new bootstrap.Modal(document.getElementById("modalDeskDetail"));
    modal.show();
  } catch (e) {
    console.error("Gagal parse data meja:", e);
  }
}

// =========================================================================
// DIRECT DRAG & DROP + AUTO-SAVE
// =========================================================================

function updatePlaceholders() {
  document.querySelectorAll(".floor-grid").forEach(function(grid) {
    const cardCount = grid.querySelectorAll(".desk-card").length;
    const ph = grid.querySelector(".empty-zone-placeholder");
    if (ph) {
      if (cardCount === 0) {
        ph.classList.remove("d-none");
      } else {
        ph.classList.add("d-none");
      }
    }
  });

  // Perbarui total meja per zona & tampilkan tombol hapus zona jika kosong
  document.querySelectorAll(".floor-zone").forEach(function(zoneEl) {
    const totalInZone = zoneEl.querySelectorAll(".desk-card").length;
    const badge = zoneEl.querySelector(".zone-count-badge .count-num");
    if (badge) badge.textContent = totalInZone;

    const delBtn = zoneEl.querySelector(".btn-delete-zone");
    if (delBtn) {
      if (totalInZone === 0) {
        delBtn.classList.remove("d-none");
        delBtn.classList.add("d-inline-flex");
      } else {
        delBtn.classList.add("d-none");
        delBtn.classList.remove("d-inline-flex");
      }
    }
  });

  // Perbarui nomor urut sekuensial (#1, #2, ...)
  let seq = 0;
  document.querySelectorAll(".desk-card").forEach(function(c) {
    seq++;
    const seqEl = c.querySelector(".desk-seq-num");
    if (seqEl) seqEl.textContent = "#" + seq;
  });
}

function initFloorPlanSortable() {
  if (typeof Sortable === "undefined") {
    console.error("SortableJS belum termuat.");
    return;
  }

  // Bersihkan instans lama jika ada
  sortableInstances.forEach(function(s) {
    try { s.destroy(); } catch(e) {}
  });
  sortableInstances = [];

  const grids = document.querySelectorAll(".floor-grid");
  grids.forEach(function(grid) {
    const s = new Sortable(grid, {
      group: "shared-floor-plan",
      animation: 180,
      forceFallback: true,
      fallbackClass: "sortable-drag",
      ghostClass: "sortable-ghost",
      chosenClass: "sortable-chosen",
      dragClass: "sortable-drag",
      draggable: ".desk-card",
      filter: ".empty-zone-placeholder",
      preventOnFilter: false,
      fallbackTolerance: 3,
      emptyInsertThreshold: 25,
      onStart: function(evt) {
        isCurrentlyDragging = true;
        dragStartTime = Date.now();
        document.querySelectorAll(".empty-zone-placeholder").forEach(function(p) {
          p.classList.remove("d-none");
        });
      },
      onEnd: function(evt) {
        // Update data-zone on moved card
        const targetGrid = evt.to;
        const targetZoneKey = targetGrid.getAttribute("data-zone-key");
        evt.item.setAttribute("data-zone", targetZoneKey);

        updatePlaceholders();
        autoSaveDeskPositions();

        setTimeout(function() {
          isCurrentlyDragging = false;
        }, 200);
      }
    });
    sortableInstances.push(s);
  });
}

// Auto-Save Posisi Meja ke Database
function autoSaveDeskPositions() {
  clearTimeout(autoSaveTimer);
  autoSaveTimer = setTimeout(function() {
    const positions = [];
    document.querySelectorAll(".floor-zone").forEach(function(zoneEl) {
      const zoneKey = zoneEl.dataset.zoneKey;
      const cards = zoneEl.querySelectorAll(".desk-card");
      cards.forEach(function(cardEl, idx) {
        positions.push({
          asset_id: parseInt(cardEl.dataset.id),
          zone_key: zoneKey,
          sort_order: idx + 1
        });
      });
    });

    showSaveToastSpinner("Menyimpan Posisi...", "Memperbarui tata letak ke database...");

    fetch("floor_plan_save", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "save",
        cabang_id: currentCabangId,
        positions: positions
      })
    })
    .then(function(res) { return res.json(); })
    .then(function(res) {
      if (res.success) {
        showSaveToastResult(true, "Posisi Meja Tersimpan!", (res.saved_count || "") + " meja otomatis diperbarui.");
      } else {
        showSaveToastResult(false, "Gagal Menyimpan", res.error || "Terjadi kesalahan.");
      }
    })
    .catch(function(err) {
      showSaveToastResult(false, "Error Jaringan", "Tidak dapat terhubung ke server.");
    });
  }, 100);
}

function showSaveToastSpinner(title, sub) {
  const toastEl = document.getElementById("saveToast");
  if (!saveToastInstance && window.bootstrap && bootstrap.Toast) {
    saveToastInstance = new bootstrap.Toast(toastEl, { delay: 2400 });
  }
  const spinner = document.getElementById("saveToastSpinner");
  const icon = document.getElementById("saveToastIcon");
  if (spinner) spinner.classList.remove("d-none");
  if (icon) icon.classList.add("d-none");
  document.getElementById("saveToastTitle").textContent = title;
  document.getElementById("saveToastSub").textContent = sub;
  if (saveToastInstance) saveToastInstance.show();
}

function showSaveToastResult(isSuccess, title, sub) {
  const spinner = document.getElementById("saveToastSpinner");
  const icon = document.getElementById("saveToastIcon");
  if (spinner) spinner.classList.add("d-none");
  if (icon) {
    icon.classList.remove("d-none");
    if (isSuccess) {
      icon.className = "bi bi-check-circle-fill text-success fs-5";
    } else {
      icon.className = "bi bi-exclamation-triangle-fill text-danger fs-5";
    }
  }
  document.getElementById("saveToastTitle").textContent = title;
  document.getElementById("saveToastSub").textContent = sub;
}

function resetDeskPositions() {
  if (!confirm("Apakah Anda yakin ingin mereset tata letak denah ke pengaturan bawaan divisi?")) {
    return;
  }

  fetch("floor_plan_save", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      action: "reset",
      cabang_id: currentCabangId
    })
  })
  .then(function(res) { return res.json(); })
  .then(function(res) {
    if (res.success) {
      alert("✅ Tata letak berhasil direset. Halaman akan dimuat ulang.");
      window.location.reload();
    } else {
      alert("❌ Gagal mereset: " + (res.error || ""));
    }
  })
  .catch(function(err) {
    alert("❌ Error jaringan: " + err);
  });
}

// =========================================================================
// TAMBAH ZONA / RUANGAN BARU
// =========================================================================

function submitAddZone(e) {
  e.preventDefault();
  const title = document.getElementById("newZoneTitle").value.trim();
  const subtitle = document.getElementById("newZoneSubtitle").value.trim();
  const color = document.getElementById("newZoneColor").value;
  const icon = document.getElementById("newZoneIcon").value;

  if (!title) {
    alert("Nama ruangan wajib diisi.");
    return;
  }

  const btn = document.getElementById("btnSubmitZone");
  btn.disabled = true;
  btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...\';

  fetch("floor_plan_save", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      action: "add_zone",
      cabang_id: currentCabangId,
      title: title,
      subtitle: subtitle,
      color: color,
      icon: icon
    })
  })
  .then(function(res) { return res.json(); })
  .then(function(res) {
    btn.disabled = false;
    btn.innerHTML = \'<i class="bi bi-check-lg me-1"></i> Simpan Ruangan\';
    if (res.success) {
      alert("✅ " + (res.message || "Ruangan berhasil ditambahkan!"));
      window.location.reload();
    } else {
      alert("❌ Gagal menambah ruangan: " + (res.error || "Terjadi kesalahan"));
    }
  })
  .catch(function(err) {
    btn.disabled = false;
    btn.innerHTML = \'<i class="bi bi-check-lg me-1"></i> Simpan Ruangan\';
    alert("❌ Error koneksi: " + err);
  });
}

// =========================================================================
// UBAH / EDIT NAMA & TEMA ZONA
// =========================================================================
let editZoneModalInstance = null;

function openEditZoneModal(btn) {
  const zoneKey = btn.getAttribute("data-zone-key");
  const title = btn.getAttribute("data-zone-title");
  const subtitle = btn.getAttribute("data-zone-subtitle") || "";
  const color = btn.getAttribute("data-zone-color") || "#0284C7";
  const icon = btn.getAttribute("data-zone-icon") || "bi-door-closed";

  document.getElementById("editZoneKey").value = zoneKey;
  document.getElementById("editZoneTitle").value = title;
  document.getElementById("editZoneSubtitle").value = subtitle;
  document.getElementById("editZoneColor").value = color;
  document.getElementById("editZoneIcon").value = icon;

  const modalEl = document.getElementById("modalEditZone");
  if (!editZoneModalInstance && window.bootstrap && bootstrap.Modal) {
    editZoneModalInstance = new bootstrap.Modal(modalEl);
  }
  if (editZoneModalInstance) {
    editZoneModalInstance.show();
  }
}

function submitEditZone(e) {
  e.preventDefault();
  const zoneKey = document.getElementById("editZoneKey").value;
  const title = document.getElementById("editZoneTitle").value.trim();
  const subtitle = document.getElementById("editZoneSubtitle").value.trim();
  const color = document.getElementById("editZoneColor").value;
  const icon = document.getElementById("editZoneIcon").value;

  if (!title) {
    alert("Nama ruangan wajib diisi.");
    return;
  }

  const btn = document.getElementById("btnSubmitEditZone");
  btn.disabled = true;
  btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...\';

  fetch("floor_plan_save", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      action: "edit_zone",
      cabang_id: currentCabangId,
      zone_key: zoneKey,
      title: title,
      subtitle: subtitle,
      color: color,
      icon: icon
    })
  })
  .then(function(res) { return res.json(); })
  .then(function(res) {
    btn.disabled = false;
    btn.innerHTML = \'<i class="bi bi-check-lg me-1"></i> Simpan Perubahan\';
    if (res.success) {
      if (editZoneModalInstance) editZoneModalInstance.hide();
      
      // Update tampilan langsung di DOM tanpa perlu reload
      const zoneEl = document.getElementById("zone-card-" + zoneKey);
      if (zoneEl) {
        zoneEl.style.borderLeftColor = color;
        const titleEl = zoneEl.querySelector(".zone-title-disp");
        if (titleEl) titleEl.textContent = title;
        const subEl = zoneEl.querySelector(".zone-subtitle-disp");
        if (subEl) subEl.textContent = subtitle;
        const iconEl = zoneEl.querySelector(".zone-icon-disp");
        if (iconEl) {
          iconEl.style.color = color;
          iconEl.innerHTML = \'<i class="bi \' + icon + \'"></i>\';
        }
        const headerEl = zoneEl.querySelector(".floor-zone-header");
        if (headerEl && res.zone && res.zone.bg_light) {
          headerEl.style.backgroundColor = res.zone.bg_light;
        }
        const editBtn = zoneEl.querySelector(".btn-edit-zone");
        if (editBtn) {
          editBtn.setAttribute("data-zone-title", title);
          editBtn.setAttribute("data-zone-subtitle", subtitle);
          editBtn.setAttribute("data-zone-color", color);
          editBtn.setAttribute("data-zone-icon", icon);
        }
      }
      showSaveToastResult(true, "Zona Diperbarui!", res.message || "Nama zona berhasil diubah.");
    } else {
      alert("❌ Gagal mengubah zona: " + (res.error || "Terjadi kesalahan"));
    }
  })
  .catch(function(err) {
    btn.disabled = false;
    btn.innerHTML = \'<i class="bi bi-check-lg me-1"></i> Simpan Perubahan\';
    alert("❌ Error koneksi: " + err);
  });
}

// =========================================================================
// HAPUS ZONA KOSONG
// =========================================================================

function deleteEmptyZone(zoneKey) {
  const zoneEl = document.getElementById("zone-card-" + zoneKey);
  if (!zoneEl) return;

  const cardCount = zoneEl.querySelectorAll(".desk-card").length;
  if (cardCount > 0) {
    alert("❌ Zona ini masih memiliki " + cardCount + " meja workstation. Pindahkan semua meja ke ruangan lain terlebih dahulu sebelum menghapus zona ini.");
    return;
  }

  const titleEl = zoneEl.querySelector(".zone-title-disp");
  const zoneTitle = titleEl ? titleEl.textContent : "Ruangan";

  if (!confirm("Apakah Anda yakin ingin menghapus ruangan / zona \"" + zoneTitle + "\"?\\n\\nRuangan yang kosong akan dihapus permanen dari denah cabang ini.")) {
    return;
  }

  fetch("floor_plan_save", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      action: "delete_zone",
      cabang_id: currentCabangId,
      zone_key: zoneKey
    })
  })
  .then(function(res) { return res.json(); })
  .then(function(res) {
    if (res.success) {
      zoneEl.style.transition = "all 0.35s ease";
      zoneEl.style.opacity = "0";
      zoneEl.style.transform = "scale(0.95)";
      setTimeout(function() {
        zoneEl.remove();
        showSaveToastResult(true, "Ruangan Dihapus", res.message || "Zona kosong berhasil dihapus dari denah.");
      }, 350);
    } else {
      alert("❌ Gagal menghapus zona: " + (res.error || "Terjadi kesalahan."));
    }
  })
  .catch(function(err) {
    alert("❌ Error koneksi: " + err);
  });
}

function bootSortable() {
  if (typeof Sortable !== "undefined") {
    initFloorPlanSortable();
    updatePlaceholders();
  } else {
    let retries = 0;
    const interval = setInterval(function() {
      retries++;
      if (typeof Sortable !== "undefined") {
        clearInterval(interval);
        initFloorPlanSortable();
        updatePlaceholders();
      } else if (retries > 30) {
        clearInterval(interval);
        console.error("Gagal memuat SortableJS.");
      }
    }, 50);
  }
}

document.addEventListener("DOMContentLoaded", bootSortable);
</script>
';

render_page('Denah Meja Kantor · ' . $namaCabang, $body, $headStyle, $extraScript, true);
