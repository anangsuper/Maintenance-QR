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
        if (!empty($_FILES['memo_disetujui_file']['tmp_name'])) {
            $uploadedAcc = upload_memo_disetujui('memo_disetujui_file');
            if ($uploadedAcc) {
                $_POST['memo_disetujui_file'] = $uploadedAcc;
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

/* HERO RICH GRADIENT BANNERS (DARK NAVBAR THEME) */
.hero-gradient-header {
  color: #FFFFFF !important;
  padding: 24px 28px;
  border-radius: 18px;
  margin-bottom: 24px;
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #051329 0%, #0A1E38 55%, #162E52 100%) !important;
  border: 1px solid rgba(255, 255, 255, 0.08) !important;
  box-shadow: 0 12px 28px -6px rgba(5, 19, 41, 0.55);
}
.hero-gradient-header::after {
  content: "";
  position: absolute;
  top: -50px;
  right: -50px;
  width: 240px;
  height: 240px;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0) 70%);
  border-radius: 50%;
  pointer-events: none;
}
.hero-gradient-blue {
  background: linear-gradient(135deg, #051329 0%, #0A1E38 55%, #162E52 100%) !important;
  box-shadow: 0 12px 28px -6px rgba(5, 19, 41, 0.55);
}
.hero-gradient-green {
  background: linear-gradient(135deg, #051329 0%, #0A1E38 55%, #162E52 100%) !important;
  box-shadow: 0 12px 28px -6px rgba(5, 19, 41, 0.55);
}
.hero-gradient-header h1, .hero-gradient-header h2, .hero-gradient-header h3, .hero-gradient-header h4 {
  color: #FFFFFF !important;
  font-weight: 800;
  letter-spacing: -0.02em;
  margin: 0;
}
.hero-gradient-header p {
  color: rgba(255, 255, 255, 0.88) !important;
  margin-top: 4px;
  margin-bottom: 0;
  font-size: 0.85rem;
}
.hero-gradient-header .tech-label-blue {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #93C5FD;
}
.hero-gradient-header .tech-label-green {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #A7F3D0;
}
.hero-gradient-header .header-actions .btn-glass {
  background: rgba(255, 255, 255, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.22);
  color: #FFFFFF !important;
  font-weight: 600;
  font-size: 0.82rem;
  padding: 8px 16px;
  border-radius: 10px;
  transition: all 0.2s ease;
  backdrop-filter: blur(6px);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.hero-gradient-header .header-actions .btn-glass:hover {
  background: #FFFFFF;
  color: #0A1E38 !important;
  border-color: #FFFFFF;
  transform: translateY(-1px);
}
.hero-gradient-green .header-actions .btn-glass:hover {
  color: #0A1E38 !important;
}
.hero-gradient-header .header-actions .btn-solid-cta {
  background: #FFFFFF !important;
  color: #0A1E38 !important;
  border: 1px solid #FFFFFF !important;
  font-weight: 700;
  font-size: 0.82rem;
  padding: 8px 18px;
  border-radius: 10px;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
  transition: all 0.2s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.hero-gradient-green .header-actions .btn-solid-cta {
  color: #0A1E38 !important;
}
.hero-gradient-header .header-actions .btn-solid-cta:hover {
  background: #F1F5F9 !important;
  color: #051329 !important;
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35);
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
        if (!empty($m['memo_disetujui_file'])) {
            $accUrl = memo_file_url($m['memo_disetujui_file']);
            $isPdf = str_ends_with(strtolower($m['memo_disetujui_file']), '.pdf');
            $accIcon = $isPdf ? 'bi-file-earmark-pdf-fill text-info' : 'bi-file-earmark-check-fill text-info';
            $lampiranBadge .= '<a href="'.e($accUrl).'" target="_blank" class="badge bg-info-subtle text-info-emphasis border border-info-subtle shadow-xs text-decoration-none d-inline-flex align-items-center gap-1 px-2 py-1" style="font-size:0.68rem;" title="Berkas Scan Memo yang Sudah Disetujui Direksi"><i class="bi '.$accIcon.'"></i> Memo Disetujui ✓</a> ';
        }
        if ($lampiranBadge !== '') {
            $lampiranBadge = '<div class="mt-1.5 d-flex flex-wrap gap-1 align-items-center">' . $lampiranBadge . '</div>';
        }

        if ($st === 'Selesai Dibeli' && !empty($m['biaya_realisasi'])) {
            $costHtml = '
              <div class="fw-bold text-success" style="font-size:0.85rem;"><i class="bi bi-receipt me-1"></i>Rp. '.number_format((float)$m['biaya_realisasi'], 0, ',', '.').',-</div>
              <div class="text-muted small" style="font-size:0.72rem;">Estimasi: Rp. '.number_format($total, 0, ',', '.').'</div>';
        } else {
            $costHtml = '
              <div class="fw-bold text-dark" style="font-size:0.85rem;">Rp. '.number_format($total, 0, ',', '.').',-</div>
              <div class="text-secondary small fst-italic" style="font-size:0.72rem;"><i class="bi bi-hourglass-split me-0.5"></i>Estimasi Pengajuan</div>';
        }

        $primaryActionBtn = '';
        $menuItems = [];

        // 1. Opsi Cetak Dokumen Memo (selalu tersedia)
        $menuItems[] = '<li><a class="dropdown-item py-1.5" href="'.$printUrl.'" target="_blank"><i class="bi bi-printer text-primary me-2"></i> Cetak Dokumen Memo</a></li>';

        if ($st === 'Diajukan') {
            $hasLampiran = !empty($m['lampiran_file']);
            $primaryActionBtn = '
              <button type="button" class="btn btn-sm btn-success fw-bold d-inline-flex align-items-center gap-1 px-2.5 py-1 shadow-xs text-nowrap" style="font-size:0.75rem;" onclick=\'quickApproveMemo('.$mid.', "'.e($nomor).'")\' title="Setujui Memo ini agar siap dibeli">
                <i class="bi bi-check-circle-fill"></i> Setujui
              </button>';

            $menuItems[] = '<li><button class="dropdown-item py-1.5" type="button" onclick=\'openQuickUploadModal('.$memoJson.')\'><i class="bi bi-paperclip text-secondary me-2"></i> '.($hasLampiran ? 'Lihat / Ganti Penawaran' : '+ Upload Surat Penawaran').'</button></li>';
        } elseif ($st === 'Disetujui') {
            $hasNota = !empty($m['bukti_beli_file']);
            $primaryActionBtn = '
              <button type="button" class="btn btn-sm btn-warning text-dark fw-bold d-inline-flex align-items-center gap-1 px-2.5 py-1 shadow-xs text-nowrap" style="font-size:0.75rem;" onclick=\'openRealisasiModal('.$memoJson.')\' title="'.($hasNota ? 'Lihat / Edit Bukti Pembelian & Nota Toko' : 'Upload Bukti Pembelian & Nota Toko').'">
                <i class="bi bi-receipt"></i> '.($hasNota ? 'Nota Toko' : '+ Nota Toko').'
              </button>';
        } elseif ($st === 'Selesai Dibeli') {
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
            $primaryActionBtn = '
              <a href="'.e($cardUrl).'" class="btn btn-sm btn-success fw-bold d-inline-flex align-items-center gap-1 px-2.5 py-1 shadow-xs text-nowrap" style="font-size:0.75rem;" title="Daftarkan langsung barang ini ke Kartu Inventaris Aktif">
                <i class="bi bi-credit-card-2-front"></i> Daftarkan Kartu
              </a>';

            $bastUrl = module_url('print_bast_pengadaan.php', ['id' => $mid]);
            $menuItems[] = '<li><a class="dropdown-item py-1.5" href="'.e($bastUrl).'" target="_blank"><i class="bi bi-camera-fill text-primary me-2"></i> Dokumentasi Fisik &amp; Nota</a></li>';
            $menuItems[] = '<li><button class="dropdown-item py-1.5" type="button" onclick=\'openRealisasiModal('.$memoJson.')\'><i class="bi bi-receipt text-warning me-2"></i> Realisasi &amp; Nota Toko</button></li>';
        }

        // 2. Opsi Edit Data Memo
        $menuItems[] = '<li><hr class="dropdown-divider my-1"></li>';
        $menuItems[] = '<li><button class="dropdown-item py-1.5" type="button" onclick=\'openEditMemoModal('.$memoJson.')\'><i class="bi bi-pencil text-secondary me-2"></i> Edit Data Memo</button></li>';

        $actionHtml = '
          <div class="d-inline-flex align-items-center justify-content-end gap-1.5 flex-nowrap">
            '.$primaryActionBtn.'
            <a href="'.$printUrl.'" target="_blank" class="btn btn-sm btn-outline-primary px-2 py-1 shadow-xs" style="font-size:0.75rem;" title="Cetak Dokumen Memo Internal">
              <i class="bi bi-printer"></i>
            </a>
            <div class="dropdown d-inline-block">
              <button class="btn btn-sm btn-light border px-2 py-1 shadow-xs dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Menu Opsi Lainnya">
                <i class="bi bi-three-dots-vertical"></i>
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow border py-1" style="font-size:0.80rem; min-width: 190px;">
                '.implode('', $menuItems).'
              </ul>
            </div>
          </div>';

        $tableRows .= '
        <tr>
          <td class="text-center font-monospace text-muted small">'.$no++.'</td>
          <td>
            <a href="'.$printUrl.'" target="_blank" class="fw-bold text-dark font-monospace text-decoration-none d-block text-break" style="font-size:0.78rem; line-height: 1.3;" title="Lihat &amp; Cetak Dokumen Memo">
              <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i>'.$nomor.'
            </a>
            <div class="small text-muted mt-0.5" style="font-size:0.72rem;"><i class="bi bi-calendar3 me-1"></i>'.$tgl.'</div>
          </td>
          <td>
            <span class="badge bg-light text-dark border px-2 py-0.5 small mb-1 text-wrap d-inline-block" style="max-width: 140px; font-size:0.72rem;">'.$cabang.'</span>
            <div class="small text-secondary" style="font-size:0.72rem; line-height: 1.25;">'.$pengaju.'</div>
          </td>
          <td>
            <strong class="text-dark small d-block">'.$perihal.'</strong>
            '.$lampiranBadge.'
          </td>
          <td class="text-nowrap">
            '.$costHtml.'
          </td>
          <td class="text-center text-nowrap">
            <span class="status-pill '.$stBadge.'">'.e($st).'</span>
          </td>
          <td class="text-end text-nowrap">
            '.$actionHtml.'
          </td>
        </tr>';
    }
}

$cabangFilterOptionsHtml = '';
foreach ($cabangList as $c) {
    $cName = $c['nama_cabang'] ?? '';
    if ($cName === '') continue;
    $sel = ($filterCabang === $cName) ? 'selected' : '';
    $cabangFilterOptionsHtml .= '<option value="'.e($cName).'" '.$sel.'>'.e($cName).'</option>';
}

$totalNominalRealisasi = 0;
foreach ($allMemos as $m) {
    if (!empty($m['biaya_realisasi'])) {
        $totalNominalRealisasi += (float)$m['biaya_realisasi'];
    }
}

$body = '
'.$flashHtml.'
'.$errorHtml.'

<!-- BROWSER WINDOW NEO CONTAINER (WARM AMBER / ORANGE THEME UNTUK MEMO & PENGADAAN) -->
<div class="card p-0 shadow-sm border-0 browser-window-card mb-4" style="border-radius: 24px;">
  <!-- Browser Window Topbar with Traffic Lights -->
  <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2" style="border-top-left-radius: 24px; border-top-right-radius: 24px;">
    <div class="browser-topbar-col-left d-flex align-items-center gap-2">
      <span class="browser-traffic-dot bg-danger"></span>
      <span class="browser-traffic-dot bg-warning"></span>
      <span class="browser-traffic-dot bg-success"></span>
    </div>

    <div class="browser-topbar-col-center">
      <div class="browser-address-pill">
        <i class="bi bi-lock-fill text-warning"></i>
        <span>bankmitra.id/memo_pengajuan.php</span>
      </div>
    </div>

    <div class="browser-topbar-col-right">
      <div class="floating-toast-pill">
        <div class="floating-toast-icon-amber" style="background: linear-gradient(135deg, #EA580C 0%, #C2410C 100%); box-shadow: 0 4px 10px rgba(234, 88, 12, 0.25);">
          <i class="bi bi-file-earmark-text"></i>
        </div>
        <div class="text-start">
          <div class="fw-bold text-dark" style="font-size: 0.76rem; line-height: 1.2;">Procurement &amp; Memo</div>
          <div class="text-muted" style="font-size: 0.68rem;">Approval &amp; Realisasi Aset</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Inner Canvas with Gradient Hero Banner & Modern Metric Cards -->
  <div class="p-3 p-md-4">
    <!-- Hero Banner Card with Layered Smooth Waves (Amber/Orange Theme) -->
    <div class="card-hero-neo mb-4" style="background: linear-gradient(135deg, #C2410C 0%, #EA580C 45%, #F59E0B 100%) !important; box-shadow: 0 12px 32px -4px rgba(234, 88, 12, 0.3) !important;">
      <!-- Wave Layer Overlays at Bottom -->
      <svg class="position-absolute bottom-0 start-0 w-100" style="height: 60px; pointer-events: none; opacity: 0.22;" preserveAspectRatio="none" viewBox="0 0 1200 120">
        <path d="M0,0 C150,90 400,100 600,40 C800,-20 1050,80 1200,30 L1200,120 L0,120 Z" fill="#FFFFFF"></path>
      </svg>
      <svg class="position-absolute bottom-0 start-0 w-100" style="height: 40px; pointer-events: none; opacity: 0.32;" preserveAspectRatio="none" viewBox="0 0 1200 120">
        <path d="M0,40 C300,10 600,80 900,30 C1050,10 1150,50 1200,60 L1200,120 L0,120 Z" fill="#FFFFFF"></path>
      </svg>

      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 position-relative" style="z-index: 2;">
        <div style="max-width: 650px;">
          <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
            <span class="badge bg-white text-dark px-2.5 py-1 font-monospace fw-bold shadow-xs" style="font-size: 0.70rem; letter-spacing: 0.05em; border-radius: 6px;">PENGELOLAAN SARPRAS &amp; SDM</span>
            <span class="text-white text-opacity-85 small fw-semibold">Dokumen Resmi Pengadaan Inventaris Bank Mitra</span>
          </div>
          <h2 class="display-6 fw-bold mb-1 text-white" style="letter-spacing: -0.025em;">Memo Internal Pengadaan</h2>
          <p class="text-white text-opacity-95 mb-3" style="font-size: 0.95rem; font-weight: 500;">
            Format resmi permohonan pengadaan sarana/fasilitas baru dari kantor cabang kepada Direksi dan Bagian Umum &amp; SDM terintegrasi otomatis ke Kartu Inventaris.
          </p>
          <div class="d-flex flex-wrap align-items-center gap-2.5">
            <button type="button" class="btn-hero-pill text-decoration-none d-inline-flex align-items-center gap-2" style="color: #EA580C !important;" onclick="openCreateMemoModal()">
              <i class="bi bi-plus-circle-fill"></i> Buat Memo Baru
            </button>
            <a href="'.e(module_url('memo_pengajuan.php', array_filter(['export' => 'csv', 'cabang' => $filterCabang, 'q' => $q]))).'" class="btn btn-outline-light rounded-pill px-3.5 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-xs" style="font-size: 0.88rem;">
              <i class="bi bi-file-earmark-excel-fill"></i> Ekspor CSV
            </a>
            <a href="'.e(module_url('dashboard.php', ['tab' => 'kartu'])).'" class="btn btn-outline-light rounded-pill px-3 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-xs" style="font-size: 0.88rem;">
              <i class="bi bi-credit-card-2-front"></i> Data Kartu
            </a>
          </div>
        </div>

        <!-- Decorative Right Badges (Document Pin & Procurement Sun) -->
        <div class="d-none d-md-flex align-items-center gap-4">
          <div class="hero-pin-marker" title="Pengadaan Resmi Terverifikasi">
            <svg width="44" height="52" viewBox="0 0 44 52" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M22 0C9.85 0 0 9.85 0 22C0 36 22 52 22 52C22 52 44 36 44 22C44 9.85 34.15 0 22 0Z" fill="#C2410C"/>
              <circle cx="22" cy="22" r="9" fill="#FFFFFF"/>
            </svg>
          </div>

          <div class="hero-sun-container" title="SOP 5 Tahap Pengadaan">
            <svg width="68" height="68" viewBox="0 0 68 68" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="34" cy="34" r="17" fill="#FDE047"/>
              <rect x="31" y="2" width="6" height="10" rx="3" fill="#FDE047"/>
              <rect x="31" y="56" width="6" height="10" rx="3" fill="#FDE047"/>
              <rect x="2" y="31" width="10" height="6" rx="3" fill="#FDE047"/>
              <rect x="56" y="31" width="10" height="6" rx="3" fill="#FDE047"/>
              <rect x="11.5" y="11.5" width="6" height="10" rx="3" transform="rotate(-45 11.5 11.5)" fill="#FDE047"/>
              <rect x="49.5" y="49.5" width="6" height="10" rx="3" transform="rotate(-45 49.5 49.5)" fill="#FDE047"/>
              <rect x="49.5" y="11.5" width="10" height="6" rx="3" transform="rotate(-45 49.5 11.5)" fill="#FDE047"/>
              <rect x="11.5" y="49.5" width="10" height="6" rx="3" transform="rotate(-45 11.5 49.5)" fill="#FDE047"/>
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- 3 Symmetrical Metric Cards (Exact Proportions, Balanced Heights) -->
    <div class="row g-3 mb-2">
      <!-- Card 1: Total Memo Tercatat -->
      <div class="col-md-4">
        <div class="card-metric-modern">
          <div>
            <div class="metric-title-muted">Total Memo Pengajuan</div>
            <div class="metric-number-bold">'.number_format($totalCount).' <span class="fs-6 fw-normal text-muted">Dokumen</span></div>
          </div>
          <div class="small text-muted mt-2 d-flex align-items-center gap-2" style="font-size: 0.78rem;">
            <span class="text-warning fw-bold"><i class="bi bi-hourglass-split"></i> '.$diajukanCount.' Diajukan</span>
            <span class="text-secondary">·</span>
            <span class="text-success fw-bold"><i class="bi bi-check2-circle"></i> '.$disetujuiCount.' Disetujui/Selesai</span>
          </div>
        </div>
      </div>

      <!-- Card 2: Realisasi Anggaran Pengadaan -->
      <div class="col-md-4">
        <div class="card-metric-modern">
          <div>
            <div class="metric-title-muted">Realisasi Belanja Pengadaan</div>
            <div class="metric-number-bold d-flex align-items-center gap-2">
              <span class="text-dark" style="font-size: 1.45rem;">Rp. '.number_format($totalNominalRealisasi, 0, ',', '.').'</span>
            </div>
          </div>
          <div class="small text-muted mt-2" style="font-size: 0.78rem;">
            <span class="text-success fw-bold"><i class="bi bi-receipt"></i> Nota Toko Terverifikasi</span> · Transparan &amp; Siap Audit
          </div>
        </div>
      </div>

      <!-- Card 3: Status Alur SOP -->
      <div class="col-md-4">
        <div class="card-status-mint">
          <div>
            <div class="metric-title-muted" style="color: #047857 !important;">Kepatuhan Standar Pengadaan</div>
            <div class="status-heading mt-1">
              <span class="pulse-dot-green"></span>
              <span>SOP 5 Tahap Aktif</span>
            </div>
          </div>
          <div class="status-subtext mt-2">
            <i class="bi bi-check-circle me-1"></i> Terintegrasi Otomatis ke Kartu Inventaris &amp; Label QR
          </div>
        </div>
      </div>
    </div>

    <!-- Upward Trend Chart with Overlapping Floating Trend Badge -->
    <div class="position-relative mt-2 pt-1 mb-2">
      <div class="w-100 position-relative overflow-hidden rounded-4 trend-chart-box" style="height: 72px; border: 1px solid #E2E8F0;">
        <svg viewBox="0 0 1000 72" preserveAspectRatio="none" style="width: 100%; height: 100%;">
          <defs>
            <linearGradient id="chartGradMemo" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#EA580C" stop-opacity="0.25"/>
              <stop offset="100%" stop-color="#EA580C" stop-opacity="0.0"/>
            </linearGradient>
          </defs>
          <path d="M0,60 Q250,48 500,32 T1000,10 L1000,72 L0,72 Z" fill="url(#chartGradMemo)"/>
          <path d="M0,60 Q250,48 500,32 T1000,10" fill="none" stroke="#EA580C" stroke-width="3" stroke-linecap="round"/>
          <circle cx="994" cy="10" r="5" fill="#EA580C" stroke="#FFFFFF" stroke-width="2"/>
        </svg>
        <div class="position-absolute top-0 end-0 p-2 text-muted" style="font-size: 0.72rem; font-weight: 600;">
          Aktivitas Pengadaan · Buku Kas Sarpras
        </div>
      </div>

      <!-- Floating Trend Badge Overlapping Left of Chart -->
      <div class="position-absolute" style="left: 16px; bottom: 10px; z-index: 4;">
        <div class="badge-floating-trend shadow">
          <div class="trend-icon-box" style="background: #C2410C; box-shadow: 0 4px 10px rgba(194, 65, 12, 0.3);">
            <i class="bi bi-file-earmark-check-fill"></i>
          </div>
          <div>
            <div class="fw-bold text-dark" style="font-size: 0.88rem;">+'.$disetujuiCount.' memo berhasil diproses</div>
            <div class="text-muted small" style="font-size: 0.74rem;">Dilengkapi arsip nota toko &amp; BAST Pengadaan</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- SOP Alur Sistematis Pengadaan Inventaris -->
<div class="card mb-4 border shadow-xs bg-white" style="border-radius: 16px;">
  <div class="card-body py-3 px-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
      <span class="text-uppercase fw-bold text-dark small" style="font-size:0.75rem; letter-spacing: 0.5px;">
        <i class="bi bi-diagram-3-fill text-warning me-1"></i> Standar Alur Sistematis Pengadaan Inventaris Bank Mitra
      </span>
      <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1" style="font-size:0.72rem;">5 Tahapan Terintegrasi</span>
    </div>
    <div class="row g-2 text-center small pt-1">
      <div class="col-6 col-md">
        <div class="p-2.5 rounded-3 bg-light border h-100">
          <div class="fw-bold text-dark mb-0.5"><span class="badge bg-secondary me-1">1</span> Pengajuan Memo</div>
          <div class="text-muted" style="font-size:0.7rem;">Cabang input permohonan &amp; lampirkan penawaran</div>
        </div>
      </div>
      <div class="col-6 col-md">
        <div class="p-2.5 rounded-3 bg-light border h-100">
          <div class="fw-bold text-dark mb-0.5"><span class="badge bg-warning text-dark me-1">2</span> Persetujuan</div>
          <div class="text-muted" style="font-size:0.7rem;">Diverifikasi Umum &amp; disetujui Direksi Operasional</div>
        </div>
      </div>
      <div class="col-6 col-md">
        <div class="p-2.5 rounded-3 bg-light border h-100">
          <div class="fw-bold text-dark mb-0.5"><span class="badge bg-info text-dark me-1">3</span> Pembelian &amp; Nota</div>
          <div class="text-muted" style="font-size:0.7rem;">Barang dibeli, upload nota toko &amp; foto fisik</div>
        </div>
      </div>
      <div class="col-6 col-md">
        <div class="p-2.5 rounded-3 bg-light border h-100">
          <div class="fw-bold text-dark mb-0.5"><span class="badge bg-primary me-1">4</span> Cetak Berita Acara</div>
          <div class="text-muted" style="font-size:0.7rem;">Pemeriksaan kondisi fisik barang baru &amp; Berita Acara</div>
        </div>
      </div>
      <div class="col-6 col-md">
        <div class="p-2.5 rounded-3 bg-light border h-100">
          <div class="fw-bold text-dark mb-0.5"><span class="badge bg-success me-1">5</span> Masuk Inventaris</div>
          <div class="text-muted" style="font-size:0.7rem;">Otomatis terdaftar ke Kartu Inventaris &amp; QR Code</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Table Card dengan Toolbar Symmetrical -->
<div class="card p-0 overflow-hidden shadow-sm h-100 border-0 mb-4" style="border-radius: 20px;">
  <!-- Table Header & Live Search Bar with Symmetrical Responsive Inputs -->
  <div class="card-header bg-white py-3.5 px-4 border-bottom" style="border-top: 4px solid #EA580C; border-top-left-radius: 20px; border-top-right-radius: 20px;">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
      <div class="fw-bold text-dark text-uppercase small d-flex align-items-center gap-2" style="letter-spacing: 0.05em;">
        <i class="bi bi-journal-text text-warning fs-5"></i>
        <span>Daftar Arsip Memo Internal</span>
        <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 rounded-pill px-2.5 py-1 font-monospace">'.count($allMemos).'</span>
      </div>
      <div class="small text-muted">
        Menampilkan <strong class="text-dark">'.count($allMemos).'</strong> dokumen memo
      </div>
    </div>

    <!-- Filter & Search Toolbar Controls (All 38px High, Perfectly Symmetrical) -->
    <form method="get" class="row g-2 align-items-center m-0">
      <div class="col-lg-6 col-md-12 p-0 pe-md-1">
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0 text-muted" style="border-radius: 9999px 0 0 9999px; height: 38px;"><i class="bi bi-search"></i></span>
          <input type="text" name="q" class="form-control bg-light border-start-0 ps-0" style="border-radius: 0 9999px 9999px 0; font-size: 0.84rem; height: 38px;" placeholder="Cari nomor memo, perihal pengadaan, cabang, pengaju..." value="'.e($q).'">
        </div>
      </div>
      <div class="col-lg-4 col-md-8 p-0 px-md-1">
        <select name="cabang" class="form-select form-select-sm" style="border-radius: 9999px; height: 38px; font-size: 0.82rem;" onchange="this.form.submit()">
          <option value="">Semua Kantor Cabang</option>
          '.$cabangFilterOptionsHtml.'
        </select>
      </div>
      <div class="col-lg-2 col-md-4 p-0 ps-md-1 d-flex gap-1.5">
        <button type="submit" class="btn btn-warning btn-sm w-100 fw-bold text-dark" style="border-radius: 9999px; height: 38px; font-size: 0.82rem;">
          <i class="bi bi-funnel me-1"></i> Filter
        </button>
        '.(($q || $filterCabang) ? '
        <a href="'.e(module_url('memo_pengajuan.php')).'" class="btn btn-outline-secondary btn-sm" style="border-radius: 9999px; height: 38px; width: 38px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Reset Filter">
          <i class="bi bi-arrow-counterclockwise"></i>
        </a>' : '').'
      </div>
    </form>
  </div>
<style>
@media (min-width: 992px) {
  .table-fit-container {
    overflow-x: visible !important;
  }
}
.table-fit-container .table td, .table-fit-container .table th {
  vertical-align: middle;
}
</style>
  <div class="table-responsive table-fit-container" style="overflow-x: auto;">
    <table class="table table-hover align-middle mb-0 w-100" style="table-layout: auto;">
      <thead class="table-light">
        <tr>
          <th style="width: 38px;" class="text-center font-monospace">NO</th>
          <th style="width: 19%;">NOMOR MEMO &amp; TANGGAL</th>
          <th style="width: 15%;">CABANG &amp; PENGAJU</th>
          <th style="width: 25%;">PERIHAL PERMOHONAN</th>
          <th style="width: 14%;">ESTIMASI &amp; REALISASI</th>
          <th style="width: 9%;" class="text-center">STATUS</th>
          <th style="width: 18%;" class="text-end">AKSI</th>
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
          <div class="col-md-4">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Nomor Memo</label>
            <input type="text" id="realisasiNomorMemo" class="form-control font-monospace bg-light fw-bold" readonly>
          </div>
          <div class="col-md-4">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Kantor Cabang</label>
            <input type="text" id="realisasiCabang" class="form-control bg-light" readonly>
          </div>
          <div class="col-md-4">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Estimasi Awal Memo</label>
            <input type="text" id="realisasiEstimasiAwal" class="form-control bg-light font-monospace text-muted fw-semibold" readonly>
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
            <label class="form-label text-uppercase fw-bold text-success small" style="font-size:0.72rem;"><i class="bi bi-receipt me-1"></i>Nilai Riil Nota Pembelian (FIX) (Rp) <span class="text-danger">*</span></label>
            <input type="number" name="biaya_realisasi" id="realisasiBiaya" class="form-control fw-bold fs-6 border-success" placeholder="Total sesuai nota toko" required>
            <div class="form-text small" style="font-size:0.7rem;">Nilai total kuitansi/nota toko asli (nilai definitif fix untuk Berita Acara &amp; pencatatan inventaris).</div>
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
            <div class="p-3 rounded-3 bg-light border">
              <label class="form-label text-uppercase fw-bold text-dark small mb-1" style="font-size:0.75rem;">
                <i class="bi bi-file-earmark-check me-1 text-info"></i>Upload Scan Lembar Memo yang Sudah Disetujui / Bertanda Tangan (PDF / Gambar)
              </label>
              <input type="file" name="memo_disetujui_file" id="realisasiMemoDisetujuiFile" class="form-control" accept=".pdf,image/*">
              <div class="form-text text-muted small mt-1" style="font-size:0.72rem;">
                Format: <strong>PDF, JPG, PNG</strong>. Lembar dokumen cetak memo internal yang telah ditandatangani pejabat cabang &amp; disetujui Direksi.
              </div>
              <div id="realisasiMemoDisetujuiCurrent" class="mt-2 d-none"></div>
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
  const estVal = parseFloat(data.total_estimasi || 0);
  const estEl = document.getElementById("realisasiEstimasiAwal");
  if (estEl) {
    estEl.value = "Rp. " + estVal.toLocaleString("id-ID") + ",-";
  }
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

  const memoDisetujuiInput = document.getElementById("realisasiMemoDisetujuiFile");
  if (memoDisetujuiInput) memoDisetujuiInput.value = "";
  const curMemoDisetujui = document.getElementById("realisasiMemoDisetujuiCurrent");
  if (curMemoDisetujui) {
    if (data.memo_disetujui_file) {
      const isPdf = data.memo_disetujui_file.toLowerCase().endsWith(".pdf");
      const icon = isPdf ? "bi-file-earmark-pdf-fill text-danger" : "bi-file-earmark-check-fill text-info";
      const docUrl = (data.memo_disetujui_file.startsWith("http") || data.memo_disetujui_file.startsWith("/")) ? data.memo_disetujui_file : ("" + data.memo_disetujui_file);
      curMemoDisetujui.classList.remove("d-none");
      curMemoDisetujui.innerHTML = \'<div class="d-flex align-items-center justify-content-between flex-wrap gap-2"><div><i class="bi \' + icon + \' me-1"></i> <a href="\' + docUrl + \'" target="_blank" class="fw-bold text-decoration-none">Lihat Berkas Memo Disetujui</a></div><small class="text-muted">Pilih file baru jika ingin mengganti</small></div>\';
    } else {
      curMemoDisetujui.classList.add("d-none");
      curMemoDisetujui.innerHTML = "";
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
