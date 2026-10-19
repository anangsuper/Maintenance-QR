<?php
/**
 * Modul Manajemen & Pembuatan Memo Internal Pengajuan Inventaris
 * PT BPR Mitratama Arthabuana
 */

require __DIR__ . '/bootstrap.php';
require_login();

// Validasi hak akses: IT, SDM/HRD, dan Admin
if (!is_it() && !is_hrd()) {
    require_admin();
}

$error = '';
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$cabangList = get_cabang_list();

// Handle Submit POST (Simpan / Update Memo)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = trim((string)($_POST['action'] ?? 'save_memo'));

    if ($action === 'save_memo') {
        if (!empty($_FILES['lampiran_file']['tmp_name'])) {
            $uploadedPath = upload_memo_lampiran('lampiran_file');
            if ($uploadedPath) {
                $_POST['lampiran_file'] = $uploadedPath;
            }
        }
        $result = save_memo_pengajuan($_POST);
        if (!empty($result['success'])) {
            $_SESSION['flash'] = 'Memo Internal pengajuan berhasil disimpan (Nomor: ' . e($result['nomor_memo']) . ').';
            header('Location: ' . module_url('memo_pengajuan.php'));
            exit;
        } else {
            $error = $result['error'] ?? 'Gagal menyimpan memo internal.';
        }
    } elseif ($action === 'save_realisasi') {
        if (!empty($_FILES['bukti_beli_file']['tmp_name'])) {
            $uploadedBukti = upload_memo_bukti('bukti_beli_file');
            if ($uploadedBukti) {
                $_POST['bukti_beli_file'] = $uploadedBukti;
            }
        }
        if (!empty($_FILES['foto_barang_file']['tmp_name'])) {
            $uploadedFoto = upload_memo_foto_barang('foto_barang_file');
            if ($uploadedFoto) {
                $_POST['foto_barang_file'] = $uploadedFoto;
            }
        }
        $result = save_memo_realisasi($_POST);
        if (!empty($result['success'])) {
            $_SESSION['flash'] = 'Bukti realisasi pembelian & nota toko berhasil disimpan.';
            header('Location: ' . module_url('memo_pengajuan.php'));
            exit;
        } else {
            $error = $result['error'] ?? 'Gagal menyimpan bukti realisasi.';
        }
    } elseif ($action === 'update_status') {
        $memoId = (int)($_POST['memo_id'] ?? 0);
        $newStatus = trim((string)($_POST['new_status'] ?? 'Diajukan'));
        if ($memoId > 0 && in_array($newStatus, ['Diajukan', 'Disetujui', 'Ditolak', 'Selesai Dibeli'], true)) {
            try {
                $st = db()->prepare("UPDATE memo_pengajuan_inventaris SET status = ? WHERE id = ?");
                $st->execute([$newStatus, $memoId]);
                $_SESSION['flash'] = 'Status pengajuan memo berhasil diperbarui menjadi ' . e($newStatus) . '.';
            } catch (Throwable $e) {
                $error = 'Gagal memperbarui status: ' . $e->getMessage();
            }
        }
        header('Location: ' . module_url('memo_pengajuan.php'));
        exit;
    } elseif ($action === 'upload_lampiran_only') {
        $memoId = (int)($_POST['memo_id'] ?? 0);
        if ($memoId > 0 && !empty($_FILES['lampiran_file']['tmp_name'])) {
            $uploadedPath = upload_memo_lampiran('lampiran_file');
            if ($uploadedPath) {
                try {
                    $st = db()->prepare("UPDATE memo_pengajuan_inventaris SET lampiran_file = ? WHERE id = ?");
                    $st->execute([$uploadedPath, $memoId]);
                    $_SESSION['flash'] = 'Berkas lampiran / surat penawaran berhasil diunggah.';
                } catch (Throwable $e) {
                    $error = 'Gagal menyimpan lampiran: ' . $e->getMessage();
                }
            } else {
                $error = 'Format file tidak didukung atau ukuran melebihi batas (maks. 25MB).';
            }
        } else {
            $error = 'Silakan pilih file berkas terlebih dahulu.';
        }
        header('Location: ' . module_url('memo_pengajuan.php'));
        exit;
    }
}

// Filter pencarian
$q = trim((string)($_GET['q'] ?? ''));
$filterCabang = trim((string)($_GET['cabang'] ?? ''));

$allMemos = get_all_memo([
    'q' => $q,
    'cabang' => $filterCabang
]);

// Export CSV Rekap Memo Pengadaan
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'rekap_memo_pengajuan_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // BOM UTF-8 for Excel compatibility
    echo "\xEF\xBB\xBF";
    
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'No',
        'Nomor Memo',
        'Tanggal Memo',
        'Kantor Cabang',
        'Kepada',
        'Pengaju',
        'Jabatan Pengaju',
        'Perihal Pengadaan',
        'Estimasi Biaya (Rp)',
        'Realisasi Biaya (Rp)',
        'Status',
        'Tanggal Beli',
        'Catatan Realisasi',
        'Lampiran Penawaran',
        'Ada Nota Toko'
    ]);
    
    $no = 1;
    foreach ($allMemos as $row) {
        fputcsv($out, [
            $no++,
            $row['nomor_memo'],
            $row['tanggal_memo'],
            $row['cabang_nama'],
            $row['kepada'],
            $row['nama_pengaju'],
            $row['jabatan_pengaju'],
            $row['perihal'],
            (float)($row['total_estimasi'] ?? 0),
            (float)($row['biaya_realisasi'] ?? 0),
            $row['status'],
            $row['tanggal_beli'] ?? '',
            $row['catatan_realisasi'] ?? '',
            !empty($row['lampiran_file']) ? 'Ada' : 'Tidak Ada',
            !empty($row['bukti_beli_file']) ? 'Ada Nota' : 'Belum Ada'
        ]);
    }
    fclose($out);
    exit;
}

$totalCount = count($allMemos);
$diajukanCount = 0;
$disetujuiCount = 0;
$totalBiaya = 0;
foreach ($allMemos as $am) {
    if (($am['status'] ?? '') === 'Diajukan') $diajukanCount++;
    if (in_array($am['status'] ?? '', ['Disetujui', 'Selesai Dibeli'], true)) $disetujuiCount++;
    $totalBiaya += (float)($am['total_estimasi'] ?? 0);
}

$head = '
<style>
.status-pill {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 50px;
  white-space: nowrap !important;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  line-height: 1.2;
}
.table > :not(caption) > * > * {
  padding: 0.65rem 0.75rem;
}
.table th {
  font-size: 0.72rem;
  letter-spacing: 0.04em;
  font-weight: 700;
  color: #475569;
}
#modalMemoInternal .modal-dialog {
  max-height: calc(100vh - 2rem);
}
#modalMemoInternal .modal-content {
  max-height: calc(100vh - 2rem);
  display: flex;
  flex-direction: column;
}
#modalMemoInternal .modal-body {
  overflow-y: auto !important;
  max-height: calc(100vh - 200px);
}
</style>
';

$flashHtml = $flash ? '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" style="border-left: 4px solid #16A34A !important;"><i class="bi bi-check-circle-fill me-2 text-success"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';
$errorHtml = $error ? '<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" style="border-left: 4px solid #DC2626 !important;"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>'.e($error).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '';

$tableRows = '';
if (empty($allMemos)) {
    $tableRows = '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-file-earmark-text fs-1 d-block mb-2 opacity-50"></i>Belum ada memo internal pengajuan yang tercatat.<br><button type="button" class="btn btn-primary btn-sm mt-3 fw-bold" onclick="openCreateMemoModal()"><i class="bi bi-plus-lg me-1"></i> Buat Memo Pertama</button></td></tr>';
} else {
    $no = 1;
    foreach ($allMemos as $m) {
        $mid = (int)$m['id'];
        $nomor = e($m['nomor_memo']);
        $tgl = date('d/m/Y', strtotime($m['tanggal_memo']));
        $cabang = e($m['cabang_nama']);
        $perihal = e($m['perihal']);
        $total = (float)$m['total_estimasi'];
        $pengaju = e($m['nama_pengaju']) . '<br><small class="text-muted">' . e($m['jabatan_pengaju']) . '</small>';
        $st = $m['status'] ?? 'Diajukan';

        $stBadge = match($st) {
            'Disetujui' => 'bg-success-subtle text-success border border-success-subtle',
            'Selesai Dibeli' => 'bg-primary-subtle text-primary border border-primary-subtle',
            'Ditolak' => 'bg-danger-subtle text-danger border border-danger-subtle',
            default => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'
        };

        $printUrl = module_url('print_memo.php', ['id' => $mid]);
        $memoJson = htmlspecialchars(json_encode($m), ENT_QUOTES, 'UTF-8');

        $lampiranBadge = '';
        if (!empty($m['lampiran_file'])) {
            $fUrl = memo_file_url($m['lampiran_file']);
            $isPdf = str_ends_with(strtolower($m['lampiran_file']), '.pdf');
            $icon = $isPdf ? 'bi-file-earmark-pdf text-secondary' : 'bi-image text-secondary';
            $lampiranBadge .= '<a href="'.e($fUrl).'" target="_blank" class="badge bg-light text-secondary border shadow-xs text-decoration-none d-inline-flex align-items-center gap-1 px-2 py-1" style="font-size:0.68rem;" title="Berkas Penawaran Harga / Brosur"><i class="bi '.$icon.'"></i> Penawaran</a> ';
        }
        if (!empty($m['bukti_beli_file'])) {
            $bUrl = memo_file_url($m['bukti_beli_file']);
            $isPdf = str_ends_with(strtolower($m['bukti_beli_file']), '.pdf');
            $bIcon = $isPdf ? 'bi-receipt-cutoff text-success' : 'bi-receipt text-success';
            $lampiranBadge .= '<a href="'.e($bUrl).'" target="_blank" class="badge bg-success-subtle text-success border border-success-subtle shadow-xs text-decoration-none d-inline-flex align-items-center gap-1 px-2 py-1" style="font-size:0.68rem;" title="Nota Pembelian Toko Resmi"><i class="bi '.$bIcon.'"></i> Nota Toko ✓</a> ';
        }
        if (!empty($m['foto_barang_file'])) {
            $pUrl = memo_file_url($m['foto_barang_file']);
            $lampiranBadge .= '<a href="'.e($pUrl).'" target="_blank" class="badge bg-primary-subtle text-primary border border-primary-subtle shadow-xs text-decoration-none d-inline-flex align-items-center gap-1 px-2 py-1" style="font-size:0.68rem;" title="Foto Fisik Barang yang Tiba"><i class="bi bi-camera"></i> Foto Fisik</a> ';
        }
        if ($lampiranBadge !== '') {
            $lampiranBadge = '<div class="mt-1.5 d-flex flex-wrap gap-1 align-items-center">' . $lampiranBadge . '</div>';
        }

        $realisasiBiayaHtml = '';
        if (!empty($m['biaya_realisasi']) && (float)$m['biaya_realisasi'] > 0) {
            $realisasiBiayaHtml = '<div class="small text-success fw-bold mt-1" style="font-size:0.75rem;"><i class="bi bi-check2-circle me-1"></i>Riil: Rp. '.number_format((float)$m['biaya_realisasi'], 0, ',', '.').',-</div>';
        }

        $berkasBtn = '';
        $setujuiBtn = '';
        if ($st === 'Diajukan') {
            $hasLampiran = !empty($m['lampiran_file']);
            $berkasBtn = '
              <button type="button" class="btn btn-sm '.($hasLampiran ? 'btn-outline-secondary bg-white' : 'btn-primary text-white fw-bold').' d-inline-flex align-items-center gap-1 px-2.5 shadow-xs text-nowrap" style="font-size:0.75rem;" onclick=\'openQuickUploadModal('.$memoJson.')\' title="'.($hasLampiran ? 'Lihat / Ganti Berkas Surat Penawaran' : 'Upload Berkas Surat Penawaran / Brosur').'">
                <i class="bi bi-paperclip"></i> '.($hasLampiran ? 'Berkas' : '+ Berkas').'
              </button>';

            $setujuiBtn = '
              <button type="button" class="btn btn-sm btn-outline-success bg-white fw-bold d-inline-flex align-items-center gap-1 px-2.5 shadow-xs text-nowrap" style="font-size:0.75rem;" onclick=\'quickApproveMemo('.$mid.', "'.e($nomor).'")\' title="Setujui Memo ini agar siap dibeli">
                <i class="bi bi-check-circle-fill text-success"></i> Setujui
              </button>';
        }

        $realisasiBtn = '';
        if (in_array($st, ['Disetujui', 'Selesai Dibeli'], true)) {
            $hasNota = !empty($m['bukti_beli_file']);
            $realisasiBtn = '
              <button type="button" class="btn btn-sm '.($hasNota ? 'btn-outline-success bg-white' : 'btn-warning text-dark fw-bold').' d-inline-flex align-items-center gap-1 px-2.5 shadow-xs text-nowrap" style="font-size:0.75rem;" onclick=\'openRealisasiModal('.$memoJson.')\' title="'.($hasNota ? 'Lihat / Edit Bukti Pembelian & Nota Toko' : 'Upload Bukti Pembelian & Nota Toko').'">
                <i class="bi bi-receipt"></i> '.($hasNota ? 'Nota Toko' : '+ Nota Toko').'
              </button>';
        }

        $daftarkanKartuBtn = '';
        $bastPengadaanBtn = '';
        if ($st === 'Selesai Dibeli') {
            $cardUrl = module_url('dashboard.php', [
                'tab' => 'kartu',
                'add' => '1',
                'from_memo' => $mid,
                'nama' => $m['perihal'],
                'cabang' => $m['cabang_nama'],
                'tgl' => (!empty($m['tanggal_beli']) ? $m['tanggal_beli'] : $m['tanggal_memo']),
                'harga' => (!empty($m['biaya_realisasi']) ? (int)$m['biaya_realisasi'] : (int)$total),
                'ket' => 'Pengadaan via Memo No. ' . $nomor . (!empty($m['catatan_realisasi']) ? ' (' . $m['catatan_realisasi'] . ')' : '')
            ]);
            $daftarkanKartuBtn = '
              <a href="'.e($cardUrl).'" class="btn btn-sm btn-success fw-bold d-inline-flex align-items-center gap-1 px-2.5 shadow-xs text-nowrap" style="font-size:0.75rem;" title="Daftarkan langsung barang ini ke Kartu Inventaris Aktif">
                <i class="bi bi-credit-card-2-front"></i> Daftarkan Kartu
              </a>';

            $bastUrl = module_url('print_bast_pengadaan.php', ['id' => $mid]);
            $bastPengadaanBtn = '
              <a href="'.e($bastUrl).'" target="_blank" class="btn btn-outline-dark fw-semibold d-inline-flex align-items-center" style="font-size:0.75rem;" title="Cetak Berita Acara Serah Terima &amp; Pemeriksaan Fisik Barang (BAST Pengadaan)">
                <i class="bi bi-file-earmark-check text-primary me-1"></i> BAST
              </a>';
        }

        $tableRows .= '
        <tr>
          <td class="text-center font-monospace text-muted small">'.$no++.'</td>
          <td>
            <a href="'.$printUrl.'" target="_blank" class="fw-bold text-dark font-monospace text-decoration-none text-nowrap d-inline-block" style="font-size:0.82rem;" title="Lihat &amp; Cetak Dokumen Memo">
              <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i>'.$nomor.'
            </a>
            <div class="small text-muted mt-0.5" style="font-size:0.75rem;"><i class="bi bi-calendar3 me-1"></i>'.$tgl.'</div>
          </td>
          <td>
            <span class="badge bg-light text-dark border px-2 py-0.5 small mb-1">'.$cabang.'</span>
            <div class="small text-secondary">'.$pengaju.'</div>
          </td>
          <td>
            <strong class="text-dark small d-block">'.$perihal.'</strong>
            '.$lampiranBadge.'
          </td>
          <td class="text-nowrap">
            <div class="fw-bold text-dark" style="font-size:0.85rem;">Rp. '.number_format($total, 0, ',', '.').',-</div>
            '.$realisasiBiayaHtml.'
          </td>
          <td class="text-center text-nowrap">
            <span class="status-pill '.$stBadge.'">'.e($st).'</span>
          </td>
          <td class="text-end text-nowrap">
            <div class="d-inline-flex align-items-center justify-content-end gap-1.5 flex-nowrap">
              '.$berkasBtn.'
              '.$setujuiBtn.'
              '.$daftarkanKartuBtn.'
              '.$realisasiBtn.'
              <div class="btn-group btn-group-sm shadow-xs">
                <a href="'.$printUrl.'" target="_blank" class="btn btn-outline-primary fw-semibold d-inline-flex align-items-center" style="font-size:0.75rem;" title="Cetak Dokumen Memo Internal">
                  <i class="bi bi-printer me-1"></i> Memo
                </a>
                '.$bastPengadaanBtn.'
                <button type="button" class="btn btn-light border d-inline-flex align-items-center" style="font-size:0.75rem;" title="Edit Memo" onclick=\'openEditMemoModal('.$memoJson.')\'>
                  <i class="bi bi-pencil"></i>
                </button>
              </div>
            </div>
          </td>
        </tr>';
    }
}

$body = '
'.$flashHtml.'
'.$errorHtml.'

<!-- Header Banner (Clean Theme Bank Mitra) -->
<div class="card border-0 mb-4 overflow-hidden shadow-sm" style="background: linear-gradient(135deg, #ffffff 0%, #fefce8 100%); border: 1px solid #fef08a !important; border-left: 5px solid #eab308 !important; border-radius: 16px;">
  <div class="card-body p-3 p-md-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="badge bg-warning text-dark px-2.5 py-1" style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; border-radius: 6px;">PENGELOLAAN SARPRAS &amp; SDM</span>
          <span class="text-muted small">| Dokumen Resmi Pengadaan Inventaris</span>
        </div>
        <h3 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
          <i class="bi bi-file-earmark-text-fill text-warning"></i> Memo Internal Pengajuan Inventaris
        </h3>
        <p class="text-secondary small mb-0">Format resmi permohonan pengadaan sarana/fasilitas baru dari kantor cabang kepada Direksi dan Bagian Umum &amp; SDM.</p>
      </div>
      <div class="d-flex flex-wrap align-items-center gap-2">
        <button type="button" class="btn btn-warning text-dark fw-bold btn-sm px-3 shadow-sm d-inline-flex align-items-center gap-2" onclick="openCreateMemoModal()">
          <i class="bi bi-plus-circle-fill"></i> Buat Memo Baru
        </button>
        <a href="'.e(module_url('memo_pengajuan.php', array_filter(['export' => 'csv', 'cabang' => $filterCabang, 'q' => $q]))).'" class="btn btn-outline-success fw-semibold btn-sm px-3 bg-white d-inline-flex align-items-center gap-1.5" title="Unduh rekap data memo dalam format Excel / CSV">
          <i class="bi bi-file-earmark-excel"></i> Ekspor CSV
        </a>
        <a href="'.e(module_url('dashboard.php', ['tab' => 'kartu'])).'" class="btn btn-outline-secondary fw-semibold btn-sm px-3 bg-white d-inline-flex align-items-center gap-1.5">
          <i class="bi bi-card-heading"></i> Data Kartu
        </a>
        <a href="'.e(module_url('inventaris_mutation.php')).'" class="btn btn-outline-secondary fw-semibold btn-sm px-3 bg-white d-inline-flex align-items-center gap-1.5">
          <i class="bi bi-arrow-left-right"></i> Mutasi Barang
        </a>
      </div>
    </div>
  </div>
</div>

<!-- SOP Alur Sistematis Pengadaan Inventaris -->
<div class="card mb-4 border-0 shadow-sm bg-white" style="border-radius: 12px; border-left: 4px solid #2563EB !important;">
  <div class="card-body py-3 px-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
      <span class="text-uppercase fw-bold text-primary small" style="font-size:0.75rem; letter-spacing: 0.5px;">
        <i class="bi bi-diagram-3-fill me-1"></i> Standar Alur Sistematis Pengadaan Inventaris Bank Mitra
      </span>
      <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1" style="font-size:0.72rem;">5 Tahapan Terintegrasi</span>
    </div>
    <div class="row g-2 text-center small pt-1">
      <div class="col-6 col-md">
        <div class="p-2 rounded bg-light border h-100">
          <div class="fw-bold text-dark mb-0.5"><span class="badge bg-secondary me-1">1</span> Pengajuan Memo</div>
          <div class="text-muted" style="font-size:0.7rem;">Cabang input permohonan &amp; lampirkan penawaran</div>
        </div>
      </div>
      <div class="col-6 col-md">
        <div class="p-2 rounded bg-light border h-100">
          <div class="fw-bold text-dark mb-0.5"><span class="badge bg-warning text-dark me-1">2</span> Persetujuan</div>
          <div class="text-muted" style="font-size:0.7rem;">Diverifikasi Umum &amp; disetujui Direksi Operasional</div>
        </div>
      </div>
      <div class="col-6 col-md">
        <div class="p-2 rounded bg-light border h-100">
          <div class="fw-bold text-dark mb-0.5"><span class="badge bg-info text-dark me-1">3</span> Pembelian &amp; Nota</div>
          <div class="text-muted" style="font-size:0.7rem;">Barang dibeli, upload nota toko &amp; foto fisik</div>
        </div>
      </div>
      <div class="col-6 col-md">
        <div class="p-2 rounded bg-light border h-100">
          <div class="fw-bold text-dark mb-0.5"><span class="badge bg-primary me-1">4</span> Cetak BAST</div>
          <div class="text-muted" style="font-size:0.7rem;">Pemeriksaan kondisi fisik barang baru &amp; BAST</div>
        </div>
      </div>
      <div class="col-6 col-md">
        <div class="p-2 rounded bg-light border h-100">
          <div class="fw-bold text-dark mb-0.5"><span class="badge bg-success me-1">5</span> Masuk Inventaris</div>
          <div class="text-muted" style="font-size:0.7rem;">Otomatis terdaftar ke Kartu Inventaris &amp; QR Code</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- KPI Mini Stats -->
<div class="row g-3 mb-4">
  <div class="col-sm-4 col-12">
    <div class="card p-3 border-0 shadow-sm h-100" style="border-radius: 14px; border-left: 4px solid #3b82f6 !important;">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.7rem;">Total Memo Tercatat</div>
          <div class="fs-4 fw-bold text-dark mt-0.5">'.$totalCount.' Dokumen</div>
        </div>
        <div class="p-2.5 rounded-3 bg-primary-subtle text-primary fs-4"><i class="bi bi-journal-text"></i></div>
      </div>
    </div>
  </div>
  <div class="col-sm-4 col-12">
    <div class="card p-3 border-0 shadow-sm h-100" style="border-radius: 14px; border-left: 4px solid #f59e0b !important;">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.7rem;">Status Diajukan</div>
          <div class="fs-4 fw-bold text-warning mt-0.5">'.$diajukanCount.' Memo</div>
        </div>
        <div class="p-2.5 rounded-3 bg-warning-subtle text-warning fs-4"><i class="bi bi-hourglass-split"></i></div>
      </div>
    </div>
  </div>
  <div class="col-sm-4 col-12">
    <div class="card p-3 border-0 shadow-sm h-100" style="border-radius: 14px; border-left: 4px solid #10b981 !important;">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.7rem;">Disetujui / Selesai</div>
          <div class="fs-4 fw-bold text-success mt-0.5">'.$disetujuiCount.' Memo</div>
        </div>
        <div class="p-2.5 rounded-3 bg-success-subtle text-success fs-4"><i class="bi bi-check2-circle"></i></div>
      </div>
    </div>
  </div>
</div>

<!-- Search & Filter Card -->
<div class="card p-3 mb-4 border-0 shadow-sm" style="border-radius: 14px;">
  <form method="get" class="d-flex flex-wrap gap-2">
    <div class="input-group flex-grow-1">
      <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
      <input type="text" name="q" class="form-control border-start-0 ps-0" placeholder="Cari nomor memo, perihal pengadaan, kantor cabang, nama pengaju..." value="'.e($q).'">
    </div>
    <select name="cabang" class="form-select" style="max-width: 200px;">
      <option value="">-- Semua Cabang --</option>';

foreach ($cabangList as $c) {
    $cName = $c['nama_cabang'] ?? '';
    $sel = ($filterCabang === $cName) ? 'selected' : '';
    $body .= '<option value="'.e($cName).'" '.$sel.'>'.e($cName).'</option>';
}

$body .= '
    </select>
    <button type="submit" class="btn btn-primary px-3 fw-semibold">Filter</button>
    '.(($q || $filterCabang) ? '<a href="'.e(module_url('memo_pengajuan.php')).'" class="btn btn-light border px-3">Reset</a>' : '').'
  </form>
</div>

<!-- Table Card -->
<div class="card p-0 overflow-hidden shadow-sm border-0 mb-4" style="border-radius: 14px;">
  <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
    <div class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.05em;">
      <i class="bi bi-journal-text text-primary me-2"></i>Daftar Arsip Memo Internal ('.count($allMemos).')
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="min-width: 45px; width: 45px;" class="text-center font-monospace">NO</th>
          <th style="min-width: 220px; width: 220px;">NOMOR MEMO &amp; TANGGAL</th>
          <th style="min-width: 170px; width: 170px;">CABANG &amp; PENGAJU</th>
          <th style="min-width: 200px;">PERIHAL PERMOHONAN</th>
          <th style="min-width: 160px; width: 170px;">ESTIMASI &amp; REALISASI</th>
          <th style="min-width: 130px; width: 130px;" class="text-center">STATUS</th>
          <th style="min-width: 330px; width: 330px;" class="text-end">AKSI</th>
        </tr>
      </thead>
      <tbody>
        '.$tableRows.'
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL: FORM PEMBUATAN / EDIT MEMO INTERNAL -->
<div class="modal fade" id="modalMemoInternal" tabindex="-1" aria-labelledby="modalMemoLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form method="post" action="'.e(module_url('memo_pengajuan.php')).'" enctype="multipart/form-data" class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="save_memo">
      <input type="hidden" name="id" id="memoFormId" value="0">

      <div class="modal-header bg-dark text-white flex-shrink-0">
        <div>
          <h5 class="modal-title d-flex align-items-center gap-2" id="modalMemoLabel">
            <i class="bi bi-file-earmark-text-fill text-warning"></i> Form Memo Internal Pengajuan Inventaris
          </h5>
          <p class="text-white text-opacity-75 small mb-0">Format resmi permohonan pengadaan barang/fasilitas BPR Mitratama Arthabuana.</p>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-4 custom-scrollbar">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Kantor Cabang Pemohon <span class="text-danger">*</span></label>
              <select name="cabang_nama" id="memoCabangSelect" class="form-select" onchange="autoUpdateNomorMemo()" required>';

foreach ($cabangList as $c) {
    $cName = $c['nama_cabang'] ?? '';
    $body .= '<option value="'.e($cName).'">'.e($cName).'</option>';
}

$body .= '
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Tanggal Memo <span class="text-danger">*</span></label>
              <input type="date" name="tanggal_memo" id="memoTanggal" class="form-control" value="'.date('Y-m-d').'" required>
            </div>

            <div class="col-12">
              <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Nomor Memo <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="text" name="nomor_memo" id="memoNomor" class="form-control font-monospace fw-bold" placeholder="Contoh: 396/INT/MI/OPS/BANK-MITRA.KC.BTL/VIII/2026" required>
                <button type="button" class="btn btn-outline-secondary" onclick="autoUpdateNomorMemo()"><i class="bi bi-magic me-1"></i>Auto Nomor</button>
              </div>
              <div class="form-text small" style="font-size:0.72rem;">Dapat diisi otomatis sesuai cabang pemohon atau diedit manual sesuai nomor agenda surat.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Kepada Yth <span class="text-danger">*</span></label>
              <input type="text" name="kepada" id="memoKepada" class="form-control" value="Direksi" required>
            </div>

            <div class="col-md-6">
              <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Dari <span class="text-danger">*</span></label>
              <input type="text" name="dari_jabatan" id="memoDari" class="form-control" value="Kepala Cabang Kantor Batulicin" required>
            </div>

            <div class="col-md-8">
              <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Perihal Pengajuan <span class="text-danger">*</span></label>
              <input type="text" name="perihal" id="memoPerihal" class="form-control fw-bold" placeholder="Contoh: Permohonan Pengajuan Pembelian Monitor CCTV dan Penggantian Kamera CCTV di Lantai 2" required>
            </div>

            <div class="col-md-4">
              <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Keterangan Lampiran (Kop)</label>
              <input type="text" name="lampiran" id="memoLampiran" class="form-control" value="-" placeholder="Contoh: - atau 1 Berkas Penawaran">
            </div>

            <div class="col-12">
              <div class="p-3 rounded-3 bg-light border">
                <label class="form-label text-uppercase fw-bold text-dark small mb-1" style="font-size:0.75rem;">
                  <i class="bi bi-file-earmark-arrow-up me-1 text-primary"></i>Berkas Penawaran Harga / Brosur / Foto Kerusakan Lama (Opsional)
                </label>
                <input type="file" name="lampiran_file" id="memoLampiranFile" class="form-control" accept=".pdf,image/*">
                <div class="form-text text-muted small mt-1" style="font-size:0.72rem;">
                  <em>Opsional (Boleh dikosongkan):</em> Surat penawaran harga dari toko atau foto kondisi barang lama untuk bahan pertimbangan Direksi. <strong>Nota toko resmi di-upload nanti setelah memo disetujui &amp; dibeli.</strong>
                </div>
                <div id="memoLampiranCurrent" class="mt-2 d-none"></div>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Uraian / Isi Permohonan Pengajuan <span class="text-danger">*</span></label>
              <textarea name="isi_memo" id="memoIsi" rows="4" class="form-control" placeholder="Contoh: Sehubungan dengan adanya pembelian Monitor CCTV, Penggantian Camera CCTV di Lantai 2, Kabel HDMI dan Wiring Duck Pembungkus kabel. Maka kami dari Kepala Kantor Cabang Batulicin mengajukan permohonan pengajuan untuk biaya tersebut..." required></textarea>
            </div>

            <div class="col-md-6">
              <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Estimasi Biaya (Rp) <span class="text-danger">*</span></label>
              <input type="number" name="total_estimasi" id="memoEstimasi" class="form-control fw-bold fs-6" placeholder="2500000" oninput="updateTerbilangLive()" required>
            </div>

            <div class="col-md-6">
              <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Status Pengajuan</label>
              <select name="status" id="memoStatus" class="form-select">
                <option value="Diajukan">Diajukan</option>
                <option value="Disetujui">Disetujui</option>
                <option value="Selesai Dibeli">Selesai Dibeli</option>
                <option value="Ditolak">Ditolak</option>
              </select>
            </div>

            <div class="col-12">
              <div class="p-2.5 rounded bg-light border">
                <span class="text-muted small fw-semibold d-block">Terbilang (Otomatis):</span>
                <input type="text" name="terbilang" id="memoTerbilang" class="form-control-plaintext fw-bold fst-italic text-primary" readonly value="//Dua Juta Lima Ratus Ribu Rupiah//">
              </div>
            </div>

            <div class="col-12"><hr class="my-1 border-secondary opacity-15"></div>

            <div class="col-12">
              <h6 class="fw-bold text-dark small text-uppercase mb-2"><i class="bi bi-pen me-1 text-primary"></i>Pejabat Penandatangan Memo</h6>
            </div>

            <!-- Tanda Tangan 1: Pengaju -->
            <div class="col-md-4">
              <div class="p-2 border rounded bg-light">
                <span class="small fw-bold text-muted d-block mb-1">Yang Mengajukan:</span>
                <input type="text" name="nama_pengaju" id="memoPengajuNama" class="form-control form-control-sm mb-1 fw-bold" placeholder="Nama Kepala Cabang" value="Muhammad Azimal" required>
                <input type="text" name="jabatan_pengaju" id="memoPengajuJabatan" class="form-control form-control-sm" placeholder="Jabatan" value="Kepala Cabang" required>
              </div>
            </div>

            <!-- Tanda Tangan 2: Mengetahui -->
            <div class="col-md-4">
              <div class="p-2 border rounded bg-light">
                <span class="small fw-bold text-muted d-block mb-1">Mengetahui:</span>
                <input type="text" name="nama_mengetahui" id="memoMengetahuiNama" class="form-control form-control-sm mb-1 fw-bold" value="Defie Damayanti" required>
                <input type="text" name="jabatan_mengetahui" id="memoMengetahuiJabatan" class="form-control form-control-sm" value="Kabag. Umum & SDM" required>
              </div>
            </div>

            <!-- Tanda Tangan 3: Menyetujui -->
            <div class="col-md-4">
              <div class="p-2 border rounded bg-light">
                <span class="small fw-bold text-muted d-block mb-1">Menyetujui:</span>
                <input type="text" name="nama_menyetujui" id="memoMenyetujuiNama" class="form-control form-control-sm mb-1 fw-bold" value="Kahar Muzaqir, SH" required>
                <input type="text" name="jabatan_menyetujui" id="memoMenyetujuiJabatan" class="form-control form-control-sm" value="Direktur Operasional" required>
              </div>
            </div>

          </div>
        </div>

        <div class="modal-footer bg-light d-flex justify-content-between flex-shrink-0">
          <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary px-4 fw-bold">
            <i class="bi bi-save me-1"></i> Simpan Dokumen Memo
          </button>
        </div>
      </form>
  </div>
</div>

<!-- MODAL: REALISASI PEMBELIAN & UPLOAD NOTA TOKO -->
<div class="modal fade" id="modalUploadRealisasi" tabindex="-1" aria-labelledby="modalRealisasiLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <form method="post" action="'.e(module_url('memo_pengajuan.php')).'" enctype="multipart/form-data" class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="save_realisasi">
      <input type="hidden" name="id" id="realisasiMemoId" value="0">
      <input type="hidden" name="status" value="Selesai Dibeli">

      <div class="modal-header bg-success text-white flex-shrink-0">
        <div>
          <h5 class="modal-title d-flex align-items-center gap-2" id="modalRealisasiLabel">
            <i class="bi bi-receipt-cutoff"></i> Realisasi Pembelian &amp; Upload Nota Toko
          </h5>
          <p class="text-white text-opacity-85 small mb-0">Pertanggungjawaban pembelian barang setelah memo disetujui Direksi &amp; Bagian Umum.</p>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-4 custom-scrollbar">
        <div class="alert alert-success-subtle border-success-subtle d-flex align-items-center gap-2 mb-3 py-2 px-3 small">
          <i class="bi bi-check-circle-fill text-success fs-5"></i>
          <div>Barang telah disetujui dan dibeli. Unggah nota toko dan foto fisik barang sebagai arsip pertanggungjawaban operasional cabang.</div>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Nomor Memo</label>
            <input type="text" id="realisasiNomorMemo" class="form-control font-monospace bg-light fw-bold" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Kantor Cabang</label>
            <input type="text" id="realisasiCabang" class="form-control bg-light" readonly>
          </div>
          <div class="col-12">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Perihal Pengadaan</label>
            <input type="text" id="realisasiPerihal" class="form-control bg-light fw-bold" readonly>
          </div>

          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Tanggal Pembelian / Nota <span class="text-danger">*</span></label>
            <input type="date" name="tanggal_beli" id="realisasiTanggalBeli" class="form-control" value="'.date('Y-m-d').'" required>
          </div>
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Biaya Riil / Realisasi (Rp) <span class="text-danger">*</span></label>
            <input type="number" name="biaya_realisasi" id="realisasiBiaya" class="form-control fw-bold fs-6" placeholder="Total sesuai nota" required>
            <div class="form-text small" style="font-size:0.7rem;">Nominal total kuitansi/faktur toko (boleh berbeda dari estimasi awal jika ada diskon/ongkir).</div>
          </div>

          <div class="col-12">
            <div class="p-3 rounded-3 bg-light border">
              <label class="form-label text-uppercase fw-bold text-dark small mb-1" style="font-size:0.75rem;">
                <i class="bi bi-receipt me-1 text-success"></i>Upload Nota Pembelian / Kuitansi Toko Asli (PDF / Gambar) <span class="text-danger">*</span>
              </label>
              <input type="file" name="bukti_beli_file" id="realisasiBuktiFile" class="form-control" accept=".pdf,image/*">
              <div class="form-text text-muted small mt-1" style="font-size:0.72rem;">
                Format: <strong>PDF, JPG, JPEG, PNG, WEBP</strong> (Maks. 25MB). Foto struk nota kontan toko, invoice, atau kuitansi pembayaran resmi.
              </div>
              <div id="realisasiBuktiCurrent" class="mt-2 d-none"></div>
            </div>
          </div>

          <div class="col-12">
            <div class="p-3 rounded-3 bg-light border">
              <label class="form-label text-uppercase fw-bold text-dark small mb-1" style="font-size:0.75rem;">
                <i class="bi bi-camera me-1 text-primary"></i>Upload Foto Fisik Barang Baru yang Tiba / Terpasang
              </label>
              <input type="file" name="foto_barang_file" id="realisasiFotoFile" class="form-control" accept="image/*">
              <div class="form-text text-muted small mt-1" style="font-size:0.72rem;">
                Format: <strong>Foto JPG / PNG</strong> dari kamera HP. Foto barang yang baru tiba di kantor cabang atau sudah terpasang di ruangan.
              </div>
              <div id="realisasiFotoCurrent" class="mt-2 d-none"></div>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Catatan Realisasi Pembelian (Nama Toko / Keterangan Toko)</label>
            <textarea name="catatan_realisasi" id="realisasiCatatan" rows="2" class="form-control" placeholder="Contoh: Dibeli di Toko Surya Elektronik Batulicin, garansi 1 tahun resmi distributor."></textarea>
          </div>
        </div>
      </div>

      <div class="modal-footer bg-light d-flex justify-content-between flex-shrink-0">
        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-success px-4 fw-bold">
          <i class="bi bi-check-circle-fill me-1"></i> Simpan Realisasi &amp; Nota Pembelian
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: QUICK UPLOAD BERKAS PENAWARAN / LAMPIRAN -->
<div class="modal fade" id="modalQuickUpload" tabindex="-1" aria-labelledby="modalQuickUploadLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" action="'.e(module_url('memo_pengajuan.php')).'" enctype="multipart/form-data" class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="upload_lampiran_only">
      <input type="hidden" name="memo_id" id="quickUploadMemoId" value="0">

      <div class="modal-header bg-primary text-white flex-shrink-0">
        <div>
          <h5 class="modal-title d-flex align-items-center gap-2 mb-0" id="modalQuickUploadLabel">
            <i class="bi bi-paperclip"></i> Upload Berkas / Surat Penawaran
          </h5>
          <p class="text-white text-opacity-85 small mb-0 mt-1" style="font-size:0.75rem;">Lampirkan surat penawaran harga toko, brosur spesifikasi, atau foto barang lama.</p>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-4">
        <div class="mb-3">
          <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Dokumen Memo Terkait</label>
          <div id="quickUploadMemoTitle" class="fw-bold text-dark small bg-light p-2.5 rounded border"></div>
        </div>

        <div class="p-3 rounded-3 bg-light border">
          <label class="form-label text-uppercase fw-bold text-dark small mb-1" style="font-size:0.75rem;">
            <i class="bi bi-file-earmark-arrow-up me-1 text-primary"></i>Pilih File Berkas (PDF / Gambar) <span class="text-danger">*</span>
          </label>
          <input type="file" name="lampiran_file" id="quickUploadFileInput" class="form-control" accept=".pdf,image/*" required>
          <div class="form-text text-muted small mt-1" style="font-size:0.72rem;">
            Format yang didukung: <strong>PDF, JPG, JPEG, PNG, WEBP</strong> (Maksimal 25MB).
          </div>
          <div id="quickUploadCurrentFile" class="mt-2 d-none"></div>
        </div>
      </div>

      <div class="modal-footer bg-light d-flex justify-content-between flex-shrink-0">
        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary px-4 fw-bold">
          <i class="bi bi-cloud-arrow-up-fill me-1"></i> Simpan &amp; Unggah Berkas
        </button>
      </div>
    </form>
  </div>
</div>

<!-- FORM HIDDEN: QUICK APPROVE STATUS -->
<form id="formQuickApprove" method="post" action="'.e(module_url('memo_pengajuan.php')).'" class="d-none">
  <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
  <input type="hidden" name="action" value="update_status">
  <input type="hidden" name="memo_id" id="quickApproveMemoId" value="0">
  <input type="hidden" name="new_status" value="Disetujui">
</form>
';

$nextSeqNum = get_next_memo_sequence();
$nextSeqStr = str_pad((string)$nextSeqNum, 3, '0', STR_PAD_LEFT);

$extraScript = '
<script>
const defaultNextSeq = "'.e($nextSeqStr).'";
function terbilangJs(angka) {
  angka = Math.abs(parseInt(angka) || 0);
  const huruf = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];
  let hasil = "";

  if (angka < 12) {
    hasil = huruf[angka];
  } else if (angka < 20) {
    hasil = terbilangJs(angka - 10) + " Belas";
  } else if (angka < 100) {
    hasil = terbilangJs(Math.floor(angka / 10)) + " Puluh " + terbilangJs(angka % 10);
  } else if (angka < 200) {
    hasil = "Seratus " + terbilangJs(angka - 100);
  } else if (angka < 1000) {
    hasil = terbilangJs(Math.floor(angka / 100)) + " Ratus " + terbilangJs(angka % 100);
  } else if (angka < 2000) {
    hasil = "Seribu " + terbilangJs(angka - 1000);
  } else if (angka < 1000000) {
    hasil = terbilangJs(Math.floor(angka / 1000)) + " Ribu " + terbilangJs(angka % 1000);
  } else if (angka < 1000000000) {
    hasil = terbilangJs(Math.floor(angka / 1000000)) + " Juta " + terbilangJs(angka % 1000000);
  } else if (angka < 1000000000000) {
    hasil = terbilangJs(Math.floor(angka / 1000000000)) + " Milyar " + terbilangJs(angka % 1000000000);
  }

  return hasil.replace(/\\s+/g, " ").trim();
}

function updateTerbilangLive() {
  const num = parseInt(document.getElementById("memoEstimasi").value) || 0;
  if (num > 0) {
    const text = terbilangJs(num);
    document.getElementById("memoTerbilang").value = "//" + text + " Rupiah//";
  } else {
    document.getElementById("memoTerbilang").value = "";
  }
}

function resolveBranchCode(name) {
  name = (name || "").toLowerCase();
  if (name.indexOf("batulicin") !== -1) return "KC.BTL";
  if (name.indexOf("martapura") !== -1) return "KC.MTP";
  if (name.indexOf("tanjung") !== -1) return "KC.TJG";
  if (name.indexOf("handil") !== -1) return "KC.HDL";
  if (name.indexOf("pusat") !== -1 || name.indexOf("kpo") !== -1) return "KPO";
  return "KC";
}

function getRomawiMonth(m) {
  const r = ["I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X", "XI", "XII"];
  return r[m] || "I";
}

function autoUpdateNomorMemo(forceNext = false) {
  const cabangSel = document.getElementById("memoCabangSelect");
  const cabangName = cabangSel ? cabangSel.value : "Batulicin";
  const code = resolveBranchCode(cabangName);
  
  const d = new Date();
  const romawi = getRomawiMonth(d.getMonth());
  const year = d.getFullYear();

  document.getElementById("memoDari").value = "Kepala Cabang Kantor " + cabangName;

  // Gunakan urutan berikutnya jika baru, atau pertahankan jika sudah ada
  const currentNomor = document.getElementById("memoNomor").value;
  let numSeq = typeof defaultNextSeq !== "undefined" ? defaultNextSeq : "001";
  if (!forceNext && currentNomor) {
    const match = currentNomor.match(/^(\d+)/);
    if (match) {
      numSeq = match[1];
    }
  }

  document.getElementById("memoNomor").value = `${numSeq}/INT/MI/OPS/BANK-MITRA.${code}/${romawi}/${year}`;
}

function openCreateMemoModal() {
  document.getElementById("memoFormId").value = "0";
  document.getElementById("memoNomor").value = "";
  document.getElementById("memoPerihal").value = "";
  document.getElementById("memoIsi").value = "Sehubungan dengan adanya pengadaan inventaris/fasilitas, maka kami mengajukan permohonan pengajuan untuk biaya tersebut.";
  document.getElementById("memoEstimasi").value = "";
  document.getElementById("memoTerbilang").value = "";
  document.getElementById("memoLampiran").value = "-";

  const fileInput = document.getElementById("memoLampiranFile");
  if (fileInput) fileInput.value = "";
  const curBox = document.getElementById("memoLampiranCurrent");
  if (curBox) {
    curBox.classList.add("d-none");
    curBox.innerHTML = "";
  }

  document.getElementById("modalMemoLabel").innerHTML = \'<i class="bi bi-file-earmark-plus-fill text-warning"></i> Buat Memo Internal Pengajuan Baru\';

  autoUpdateNomorMemo(true);

  const modalEl = document.getElementById("modalMemoInternal");
  if (modalEl) {
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }
}

function openEditMemoModal(data) {
  document.getElementById("memoFormId").value = data.id || 0;
  document.getElementById("memoCabangSelect").value = data.cabang_nama || "";
  document.getElementById("memoTanggal").value = data.tanggal_memo || "";
  document.getElementById("memoNomor").value = data.nomor_memo || "";
  document.getElementById("memoKepada").value = data.kepada || "Direksi";
  document.getElementById("memoDari").value = data.dari_jabatan || "";
  document.getElementById("memoPerihal").value = data.perihal || "";
  document.getElementById("memoLampiran").value = data.lampiran || "-";
  document.getElementById("memoIsi").value = data.isi_memo || "";
  document.getElementById("memoEstimasi").value = data.total_estimasi || 0;
  document.getElementById("memoTerbilang").value = data.terbilang || "";
  document.getElementById("memoStatus").value = data.status || "Diajukan";

  const fileInput = document.getElementById("memoLampiranFile");
  if (fileInput) fileInput.value = "";
  const curBox = document.getElementById("memoLampiranCurrent");
  if (curBox) {
    if (data.lampiran_file) {
      const isPdf = (data.lampiran_file || "").toLowerCase().endsWith(".pdf");
      const icon = isPdf ? "bi-file-earmark-pdf-fill text-danger" : "bi-image-fill text-primary";
      const fileUrl = (data.lampiran_file.startsWith("http") || data.lampiran_file.startsWith("/")) ? data.lampiran_file : ("" + data.lampiran_file);
      curBox.classList.remove("d-none");
      curBox.innerHTML = \'<div class="d-flex align-items-center justify-content-between flex-wrap gap-2"><div><i class="bi \' + icon + \' me-1"></i> <a href="\' + fileUrl + \'" target="_blank" class="fw-bold text-decoration-none">Lihat Berkas Penawaran Tersimpan</a></div><small class="text-muted">Pilih file baru jika ingin mengganti</small></div>\';
    } else {
      curBox.classList.add("d-none");
      curBox.innerHTML = "";
    }
  }

  document.getElementById("memoPengajuNama").value = data.nama_pengaju || "";
  document.getElementById("memoPengajuJabatan").value = data.jabatan_pengaju || "Kepala Cabang";
  document.getElementById("memoMengetahuiNama").value = data.nama_mengetahui || "Defie Damayanti";
  document.getElementById("memoMengetahuiJabatan").value = data.jabatan_mengetahui || "Kabag. Umum & SDM";
  document.getElementById("memoMenyetujuiNama").value = data.nama_menyetujui || "Kahar Muzaqir, SH";
  document.getElementById("memoMenyetujuiJabatan").value = data.jabatan_menyetujui || "Direktur Operasional";

  document.getElementById("modalMemoLabel").innerHTML = \'<i class="bi bi-pencil-square text-warning"></i> Edit Dokumen Memo Internal\';

  const modalEl = document.getElementById("modalMemoInternal");
  if (modalEl) {
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }
}

function openRealisasiModal(data) {
  document.getElementById("realisasiMemoId").value = data.id || 0;
  document.getElementById("realisasiNomorMemo").value = data.nomor_memo || "";
  document.getElementById("realisasiCabang").value = data.cabang_nama || "";
  document.getElementById("realisasiPerihal").value = data.perihal || "";
  document.getElementById("realisasiTanggalBeli").value = data.tanggal_beli || new Date().toISOString().split("T")[0];
  document.getElementById("realisasiBiaya").value = data.biaya_realisasi || data.total_estimasi || "";
  document.getElementById("realisasiCatatan").value = data.catatan_realisasi || "";

  const buktiInput = document.getElementById("realisasiBuktiFile");
  if (buktiInput) buktiInput.value = "";
  const curBukti = document.getElementById("realisasiBuktiCurrent");
  if (curBukti) {
    if (data.bukti_beli_file) {
      const isPdf = (data.bukti_beli_file || "").toLowerCase().endsWith(".pdf");
      const icon = isPdf ? "bi-file-earmark-pdf-fill text-danger" : "bi-receipt text-success";
      const fileUrl = (data.bukti_beli_file.startsWith("http") || data.bukti_beli_file.startsWith("/")) ? data.bukti_beli_file : ("" + data.bukti_beli_file);
      curBukti.classList.remove("d-none");
      curBukti.innerHTML = \'<div class="d-flex align-items-center justify-content-between flex-wrap gap-2"><div><i class="bi \' + icon + \' me-1"></i> <a href="\' + fileUrl + \'" target="_blank" class="fw-bold text-decoration-none">Lihat Nota Toko Tersimpan</a></div><small class="text-muted">Pilih file baru jika ingin mengganti</small></div>\';
    } else {
      curBukti.classList.add("d-none");
      curBukti.innerHTML = "";
    }
  }

  const fotoInput = document.getElementById("realisasiFotoFile");
  if (fotoInput) fotoInput.value = "";
  const curFoto = document.getElementById("realisasiFotoCurrent");
  if (curFoto) {
    if (data.foto_barang_file) {
      const photoUrl = (data.foto_barang_file.startsWith("http") || data.foto_barang_file.startsWith("/")) ? data.foto_barang_file : ("" + data.foto_barang_file);
      curFoto.classList.remove("d-none");
      curFoto.innerHTML = \'<div class="d-flex align-items-center justify-content-between flex-wrap gap-2"><div><i class="bi bi-camera-fill text-primary me-1"></i> <a href="\' + photoUrl + \'" target="_blank" class="fw-bold text-decoration-none">Lihat Foto Fisik Barang</a></div><small class="text-muted">Pilih foto baru jika ingin mengganti</small></div>\';
    } else {
      curFoto.classList.add("d-none");
      curFoto.innerHTML = "";
    }
  }

  const modalEl = document.getElementById("modalUploadRealisasi");
  if (modalEl) {
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }
}

function openQuickUploadModal(data) {
  document.getElementById("quickUploadMemoId").value = data.id || 0;
  document.getElementById("quickUploadMemoTitle").innerText = (data.nomor_memo || "") + " - " + (data.perihal || "");
  const curBox = document.getElementById("quickUploadCurrentFile");
  const fileInput = document.getElementById("quickUploadFileInput");
  if (fileInput) fileInput.value = "";

  if (curBox) {
    if (data.lampiran_file) {
      const isPdf = (data.lampiran_file || "").toLowerCase().endsWith(".pdf");
      const icon = isPdf ? "bi-file-earmark-pdf-fill text-danger" : "bi-image-fill text-primary";
      const fileUrl = (data.lampiran_file.startsWith("http") || data.lampiran_file.startsWith("/")) ? data.lampiran_file : ("" + data.lampiran_file);
      curBox.classList.remove("d-none");
      curBox.innerHTML = \'<div class="d-flex align-items-center justify-content-between p-2 rounded bg-white border mt-2 small"><div><i class="bi \' + icon + \' me-1"></i> <a href="\' + fileUrl + \'" target="_blank" class="fw-bold text-decoration-none">Lihat Berkas Aktif</a></div><span class="badge bg-success-subtle text-success">Sudah Ada Berkas</span></div>\';
    } else {
      curBox.classList.add("d-none");
      curBox.innerHTML = "";
    }
  }

  const modalEl = document.getElementById("modalQuickUpload");
  if (modalEl) {
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }
}

function quickApproveMemo(id, nomor) {
  if (confirm("Apakah Anda yakin ingin MENYETUJUI Memo internal nomor " + nomor + "?\\n\\nSetelah disetujui, Bagian Umum / Cabang dapat langsung merealisasikan pembelian dan mengunggah nota toko.")) {
    document.getElementById("quickApproveMemoId").value = id;
    document.getElementById("formQuickApprove").submit();
  }
}
</script>
';

render_page('Memo Internal Pengajuan Inventaris', $body, $head, $extraScript);
