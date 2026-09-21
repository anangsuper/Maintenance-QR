<?php
require __DIR__ . '/bootstrap.php';
require_login();

$pageTitle = 'Auto Import FBD3 (Excel Inventaris)';

// Muat data JSON hasil ekstraksi dari fbd3.xls
$jsonFile = __DIR__ . '/fbd3_data.json';
$items = [];
if (file_exists($jsonFile)) {
    $rawJson = file_get_contents($jsonFile);
    $items = json_decode($rawJson, true) ?: [];
}

$action = $_POST['action'] ?? '';
$importResult = null;

if ($action === 'import' && !empty($items)) {
    $targetTable = $_POST['target_table'] ?? 'both'; // 'kartu', 'assets', or 'both'
    $filterCategory = $_POST['filter_category'] ?? 'all'; // 'all', 'it_only', 'hrd_only'
    $selectedCabang = $_POST['filter_cabang'] ?? 'all';

    $filteredItems = array_filter($items, function($item) use ($filterCategory, $selectedCabang) {
        if ($filterCategory === 'it_only' && empty($item['IsIT'])) return false;
        if ($filterCategory === 'hrd_only' && !empty($item['IsIT'])) return false;
        if ($selectedCabang !== 'all' && ($item['Prefix'] ?? '') !== $selectedCabang) return false;
        return true;
    });

    $countKartuInserted = 0;
    $countKartuUpdated = 0;
    $countAssetsInserted = 0;
    $errors = [];

    // 1. IMPORT KE INVENTARIS_KARTU
    if ($targetTable === 'kartu' || $targetTable === 'both') {
        if (is_google_cloud_mode()) {
            $client = google_sheets_v4_client();
            if ($client) {
                $client->createSheetIfNotExists('inventaris_kartu');
                $existing = $client->getSheetData('inventaris_kartu', true);
                $existingMap = [];
                $maxId = 0;
                foreach ($existing as $r) {
                    $id = (int)($r['id'] ?? 0);
                    if ($id > $maxId) $maxId = $id;
                    $rek = trim((string)($r['nomor_rekening'] ?? ''));
                    if ($rek !== '') $existingMap[$rek] = $id;
                }

                $newRows = [];
                $nowStr = date('Y-m-d H:i:s');
                foreach ($filteredItems as $item) {
                    $rek = trim((string)($item['Kode'] ?? ''));
                    if ($rek === '') continue;
                    $nama = trim((string)($item['Nama'] ?? ''));
                    $tgl = trim((string)($item['TglPerolehan'] ?? date('Y-m-d')));
                    $lokasi = trim((string)($item['Lokasi'] ?? 'Kantor Pusat (KPO)'));
                    $barcode = '';

                    if (isset($existingMap[$rek])) {
                        // Sudah ada di sheets
                        $countKartuUpdated++;
                    } else {
                        $maxId++;
                        $newRows[] = [
                            $maxId,
                            sheet_cell_text($rek),
                            $nama,
                            $tgl,
                            $barcode,
                            $lokasi,
                            $nowStr
                        ];
                        $countKartuInserted++;
                    }
                }
                if (!empty($newRows)) {
                    $client->appendValues('inventaris_kartu!A:G', $newRows);
                }
            }
        } else {
            // Mode MySQL
            try {
                $pdo = db();
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS inventaris_kartu (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        nomor_rekening VARCHAR(100) NOT NULL,
                        nama_barang VARCHAR(255) NOT NULL,
                        tanggal_perolehan DATE NOT NULL,
                        barcode_data TEXT NOT NULL,
                        lokasi VARCHAR(150) NULL DEFAULT 'Kantor Pusat (KPO)',
                        pengguna VARCHAR(150) NULL DEFAULT 'Umum / Pool',
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        KEY idx_nomor_rekening (nomor_rekening)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");

                $stmt = $pdo->prepare("
                    INSERT INTO inventaris_kartu (nomor_rekening, nama_barang, tanggal_perolehan, barcode_data, lokasi, pengguna, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");

                foreach ($filteredItems as $item) {
                    $rek = trim((string)($item['Kode'] ?? ''));
                    if ($rek === '') continue;
                    $nama = trim((string)($item['Nama'] ?? ''));
                    $tgl = trim((string)($item['TglPerolehan'] ?? date('Y-m-d')));
                    $lokasi = trim((string)($item['Lokasi'] ?? 'Kantor Pusat (KPO)'));
                    
                    try {
                        $stmt->execute([$rek, $nama, $tgl, '', $lokasi, 'Umum / Pool']);
                        $countKartuInserted++;
                    } catch (Throwable $e) {
                        $errors[] = "Gagal simpan {$rek}: " . $e->getMessage();
                    }
                }
            } catch (Throwable $e) {
                $errors[] = "Koneksi DB Error: " . $e->getMessage();
            }
        }
    }

    $importResult = [
        'total_filtered' => count($filteredItems),
        'kartu_inserted' => $countKartuInserted,
        'kartu_updated'  => $countKartuUpdated,
        'errors'         => $errors
    ];
}

// Statistik
$totalItems = count($items);
$itCount = count(array_filter($items, fn($x) => !empty($x['IsIT'])));
$hrdCount = count(array_filter($items, fn($x) => empty($x['IsIT']) && !str_contains($x['Golongan'] ?? '', 'Tanah & Gedung')));
$gedungCount = count(array_filter($items, fn($x) => str_contains($x['Golongan'] ?? '', 'Tanah & Gedung')));

$cabangStats = [];
foreach ($items as $it) {
    $p = $it['Prefix'] ?? 'Lainnya';
    $l = $it['Lokasi'] ?? $p;
    if (!isset($cabangStats[$l])) $cabangStats[$l] = 0;
    $cabangStats[$l]++;
}

ob_start();
?>
<div class="container-fluid px-0">
  <!-- Page Header -->
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <a href="<?= module_url('dashboard.php') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
          <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
        <h1 class="h4 fw-bold text-dark mb-0">Auto-Import FBD3 (Excel Inventaris)</h1>
      </div>
      <p class="text-muted small mb-0">Impor data <strong>Rincian Nominatif Aktiva & Inventaris Bank Mitra</strong> langsung ke Database & Kartu Cetak.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= module_url('print_inventory_card.php') ?>" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm">
        <i class="bi bi-printer me-1"></i> Cetak Kartu Inventaris
      </a>
    </div>
  </div>

  <?php if ($importResult): ?>
    <div class="alert alert-success border-0 shadow-sm rounded-4 p-4 mb-4">
      <div class="d-flex align-items-start gap-3">
        <div class="rounded-circle bg-success text-white p-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
          <i class="bi bi-check-lg fs-4"></i>
        </div>
        <div class="flex-grow-1">
          <h5 class="fw-bold text-success mb-1">Proses Impor Berhasil!</h5>
          <p class="text-muted small mb-2">Sebanyak <strong><?= number_format($importResult['total_filtered']) ?></strong> data telah diproses:</p>
          <div class="d-flex flex-wrap gap-2">
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6">
              <i class="bi bi-card-checklist me-1"></i> <?= $importResult['kartu_inserted'] ?> Kartu Inventaris Ditambahkan
            </span>
            <?php if ($importResult['kartu_updated'] > 0): ?>
            <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-2 fs-6">
              <i class="bi bi-arrow-repeat me-1"></i> <?= $importResult['kartu_updated'] ?> Sudah Ada
            </span>
            <?php endif; ?>
          </div>
          <?php if (!empty($importResult['errors'])): ?>
            <div class="mt-3 alert alert-danger small mb-0">
              <strong>Catatan:</strong>
              <ul class="mb-0">
                <?php foreach (array_slice($importResult['errors'], 0, 5) as $err): ?>
                  <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <div class="mt-3">
            <a href="<?= module_url('print_inventory_card.php') ?>" class="btn btn-sm btn-success rounded-pill px-4 fw-bold">
              Lihat di Halaman Cetak Kartu &rarr;
            </a>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Summary Cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm rounded-4 h-100 bg-white p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Total Item di File</span>
          <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1"><i class="bi bi-file-earmark-excel"></i> fbd3.xls</span>
        </div>
        <h3 class="fw-bold text-dark mb-0"><?= number_format($totalItems) ?></h3>
        <small class="text-muted">Unit Aktiva & Inventaris</small>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm rounded-4 h-100 bg-white p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Inventaris HRD / Umum</span>
          <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1">Fasilitas</span>
        </div>
        <h3 class="fw-bold text-success mb-0"><?= number_format($hrdCount) ?></h3>
        <small class="text-muted">Furniture, AC, Lemari, dll.</small>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm rounded-4 h-100 bg-white p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Aset IT & Komputer</span>
          <span class="badge bg-info-subtle text-info rounded-pill px-2 py-1">Teknologi</span>
        </div>
        <h3 class="fw-bold text-info mb-0"><?= number_format($itCount) ?></h3>
        <small class="text-muted">Laptop, PC, Printer, Server</small>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm rounded-4 h-100 bg-white p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">Tanah & Gedung</span>
          <span class="badge bg-warning-subtle text-warning rounded-pill px-2 py-1">Property</span>
        </div>
        <h3 class="fw-bold text-warning mb-0"><?= number_format($gedungCount) ?></h3>
        <small class="text-muted">Gedung KPO, Kas, Renovasi</small>
      </div>
    </div>
  </div>

  <!-- Form Import & Filter Box -->
  <div class="row g-4 mb-4">
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-box-arrow-in-down text-primary me-2"></i>Pengaturan Impor</h5>
        
        <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin mengimpor data inventaris yang dipilih ke database?')">
          <input type="hidden" name="action" value="import">

          <div class="mb-3">
            <label class="form-label small fw-bold text-secondary">Target Tujuan Impor</label>
            <select name="target_table" class="form-select form-select-sm rounded-3">
              <option value="kartu" selected>Kartu Inventaris (inventaris_kartu - Cetak & QR)</option>
              <option value="both">Kartu Inventaris + Modul Maintenance</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold text-secondary">Filter Kategori Barang</label>
            <select name="filter_category" class="form-select form-select-sm rounded-3">
              <option value="all" selected>Semua Barang (531 Item)</option>
              <option value="hrd_only">Khusus HRD / Fasilitas Kantor (Furniture, AC, dll)</option>
              <option value="it_only">Khusus IT & Komputer (Laptop, PC, Printer)</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold text-secondary">Filter Cabang / Unit</label>
            <select name="filter_cabang" class="form-select form-select-sm rounded-3">
              <option value="all" selected>Semua Cabang (Konsolidasi)</option>
              <option value="01">01 - Kantor Pusat (KPO)</option>
              <option value="02">02 - Cabang Batulicin</option>
              <option value="03">03 - Kantor Kas Martapura</option>
              <option value="04">04 - Cabang Tanjung</option>
              <option value="05">05 - Cabang Handil Bakti</option>
            </select>
          </div>

          <div class="alert alert-info py-2 px-3 small border-0 rounded-3 mb-4">
            <i class="bi bi-info-circle me-1"></i> Data diimpor dengan format tanggal baku, nama lengkap aktiva, dan nomor rekening perolehan.
          </div>

          <button type="submit" class="btn btn-primary w-100 py-2 rounded-pill fw-bold shadow-sm">
            <i class="bi bi-cloud-arrow-down-fill me-1"></i> Mulai Auto-Import Sekarang
          </button>
        </form>
      </div>
    </div>

    <!-- Preview Table -->
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
          <div>
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-table text-primary me-2"></i>Pratinjau Data Aktiva (fbd3.xls)</h5>
            <small class="text-muted">Menampilkan sampel daftar barang yang siap diimpor</small>
          </div>
          <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">Total: <?= count($items) ?> Barang</span>
        </div>

        <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
          <table class="table table-hover align-middle table-sm small mb-0">
            <thead class="table-light sticky-top">
              <tr>
                <th style="width: 40px;">No</th>
                <th>Kode Rekening</th>
                <th>Nama Aktiva / Barang</th>
                <th>Tgl Perolehan</th>
                <th>Lokasi</th>
                <th>Tipe</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($items, 0, 100) as $it): ?>
                <tr>
                  <td class="text-muted"><?= htmlspecialchars($it['No'] ?? '') ?></td>
                  <td><span class="badge bg-light text-dark font-monospace border"><?= htmlspecialchars($it['Kode'] ?? '') ?></span></td>
                  <td class="fw-semibold text-dark"><?= htmlspecialchars($it['Nama'] ?? '') ?></td>
                  <td class="text-muted"><?= htmlspecialchars($it['TglPerolehanOri'] ?? $it['TglPerolehan'] ?? '') ?></td>
                  <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($it['Lokasi'] ?? '') ?></span></td>
                  <td>
                    <?php if (!empty($it['IsIT'])): ?>
                      <span class="badge bg-info-subtle text-info border border-info-subtle">IT</span>
                    <?php elseif (str_contains($it['Golongan'] ?? '', 'Tanah & Gedung')): ?>
                      <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Gedung</span>
                    <?php else: ?>
                      <span class="badge bg-success-subtle text-success border border-success-subtle">HRD</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="text-center text-muted small mt-2">
          <em>* Menampilkan 100 dari <?= count($items) ?> data barang.</em>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/includes/view.php';
render_view($pageTitle, $content);
