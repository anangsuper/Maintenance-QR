<?php
/**
 * Modul Kepatuhan SDM & Tata Kelola OJK
 * PT BPR Mitratama Arthabuana
 * 
 * Standar Kepatuhan OJK:
 * 1. Sertifikasi Profesi OJK (SKKNI / LSP) - POJK Tata Kelola
 * 2. Realisasi Anggaran Pelatihan Wajib 5% - POJK No. 47/POJK.03/2017 & APU PPT
 * 3. Mandatory Block Leave (Cuti Wajib 5 Hari) - POJK No. 62/POJK.03/2020 & Strategi Anti-Fraud
 */

require __DIR__ . '/bootstrap.php';
require_login();

// Validasi hak akses: IT, SDM/HRD, dan Admin
if (!is_it() && !is_hrd()) {
    require_admin();
}

ensure_sdm_kepatuhan_schema();

$error = $_SESSION['error'] ?? flash_alert('danger') ?? '';
$flash = $_SESSION['flash'] ?? flash_alert('success') ?? '';
unset($_SESSION['error'], $_SESSION['flash']);

$tab = $_GET['tab'] ?? 'sertifikasi';
if (!in_array($tab, ['sertifikasi', 'pelatihan', 'block_leave'], true)) {
    $tab = 'sertifikasi';
}

$tahunAktif = (int)($_GET['tahun'] ?? date('Y'));
$cabangList = get_cabang_list();

// ==========================================
// POST ACTION HANDLERS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // 1. Simpan / Edit Sertifikasi Profesi
    if ($action === 'save_sertifikasi') {
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama_pegawai'] ?? '');
        $nip = trim($_POST['nip'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? '');
        $cabang = trim($_POST['cabang_nama'] ?? '');
        $bidang = trim($_POST['bidang_sertifikasi'] ?? '');
        $lembaga = trim($_POST['lembaga_penerbit'] ?? 'LSP Certif');
        $nomor = trim($_POST['nomor_sertifikat'] ?? '');
        $tglTerbit = $_POST['tanggal_terbit'] ?? '';
        $tglExp = $_POST['tanggal_kadaluarsa'] ?? '';
        $catatan = trim($_POST['catatan'] ?? '');

        if (!$nama || !$bidang || !$nomor || !$tglTerbit || !$tglExp) {
            flash_alert('danger', 'Harap lengkapi semua kolom wajib sertifikasi.');
            redirect(module_url('sdm_kepatuhan.php', ['tab' => 'sertifikasi']));
        }

        $fileUploaded = '';
        if (!empty($_FILES['file_sertifikat']['name'])) {
            $fileUploaded = sdm_upload_file($_FILES['file_sertifikat'], 'sertifikat');
        }

        if ($id > 0) {
            if ($fileUploaded !== '') {
                $stmt = db()->prepare("
                    UPDATE sdm_sertifikasi SET
                        nama_pegawai = ?, nip = ?, jabatan = ?, cabang_nama = ?,
                        bidang_sertifikasi = ?, lembaga_penerbit = ?, nomor_sertifikat = ?,
                        tanggal_terbit = ?, tanggal_kadaluarsa = ?, file_sertifikat = ?, catatan = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nama, $nip, $jabatan, $cabang, $bidang, $lembaga, $nomor, $tglTerbit, $tglExp, $fileUploaded, $catatan, $id]);
            } else {
                $stmt = db()->prepare("
                    UPDATE sdm_sertifikasi SET
                        nama_pegawai = ?, nip = ?, jabatan = ?, cabang_nama = ?,
                        bidang_sertifikasi = ?, lembaga_penerbit = ?, nomor_sertifikat = ?,
                        tanggal_terbit = ?, tanggal_kadaluarsa = ?, catatan = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nama, $nip, $jabatan, $cabang, $bidang, $lembaga, $nomor, $tglTerbit, $tglExp, $catatan, $id]);
            }
            flash_alert('success', 'Data sertifikasi pegawai berhasil diperbarui.');
        } else {
            $stmt = db()->prepare("
                INSERT INTO sdm_sertifikasi (
                    nama_pegawai, nip, jabatan, cabang_nama, bidang_sertifikasi,
                    lembaga_penerbit, nomor_sertifikat, tanggal_terbit, tanggal_kadaluarsa,
                    file_sertifikat, catatan
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nama, $nip, $jabatan, $cabang, $bidang, $lembaga, $nomor, $tglTerbit, $tglExp, $fileUploaded, $catatan]);
            flash_alert('success', 'Sertifikasi profesi baru berhasil dicatat.');
        }
        redirect(module_url('sdm_kepatuhan.php', ['tab' => 'sertifikasi']));
    }

    // 2. Hapus Sertifikasi
    if ($action === 'delete_sertifikasi') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = db()->prepare("DELETE FROM sdm_sertifikasi WHERE id = ?");
            $stmt->execute([$id]);
            flash_alert('success', 'Data sertifikasi berhasil dihapus.');
        }
        redirect(module_url('sdm_kepatuhan.php', ['tab' => 'sertifikasi']));
    }

    // 3. Simpan / Edit Pelatihan (Alokasi 5%)
    if ($action === 'save_pelatihan') {
        $id = (int)($_POST['id'] ?? 0);
        $tahun = (int)($_POST['tahun_anggaran'] ?? date('Y'));
        $judul = trim($_POST['judul_pelatihan'] ?? '');
        $jenis = trim($_POST['jenis_pelatihan'] ?? 'APU PPT Wajib');
        $penyelenggara = trim($_POST['penyelenggara'] ?? '');
        $tglMulai = $_POST['tanggal_mulai'] ?? '';
        $tglSelesai = $_POST['tanggal_selesai'] ?? '';
        $peserta = (int)($_POST['jumlah_peserta'] ?? 1);
        $daftarPeserta = trim($_POST['daftar_peserta'] ?? '');
        $biaya = (float)str_replace(['.', ','], '', $_POST['total_biaya'] ?? '0');
        $catatan = trim($_POST['catatan'] ?? '');

        if (!$judul || !$penyelenggara || !$tglMulai || !$tglSelesai) {
            flash_alert('danger', 'Harap lengkapi semua kolom wajib pelatihan.');
            redirect(module_url('sdm_kepatuhan.php', ['tab' => 'pelatihan', 'tahun' => $tahun]));
        }

        $fileUploaded = '';
        if (!empty($_FILES['bukti_file']['name'])) {
            $fileUploaded = sdm_upload_file($_FILES['bukti_file'], 'pelatihan');
        }

        if ($id > 0) {
            if ($fileUploaded !== '') {
                $stmt = db()->prepare("
                    UPDATE sdm_pelatihan SET
                        tahun_anggaran = ?, judul_pelatihan = ?, jenis_pelatihan = ?, penyelenggara = ?,
                        tanggal_mulai = ?, tanggal_selesai = ?, jumlah_peserta = ?, daftar_peserta = ?,
                        total_biaya = ?, bukti_file = ?, catatan = ?
                    WHERE id = ?
                ");
                $stmt->execute([$tahun, $judul, $jenis, $penyelenggara, $tglMulai, $tglSelesai, $peserta, $daftarPeserta, $biaya, $fileUploaded, $catatan, $id]);
            } else {
                $stmt = db()->prepare("
                    UPDATE sdm_pelatihan SET
                        tahun_anggaran = ?, judul_pelatihan = ?, jenis_pelatihan = ?, penyelenggara = ?,
                        tanggal_mulai = ?, tanggal_selesai = ?, jumlah_peserta = ?, daftar_peserta = ?,
                        total_biaya = ?, catatan = ?
                    WHERE id = ?
                ");
                $stmt->execute([$tahun, $judul, $jenis, $penyelenggara, $tglMulai, $tglSelesai, $peserta, $daftarPeserta, $biaya, $catatan, $id]);
            }
            flash_alert('success', 'Catatan pelatihan berhasil diperbarui.');
        } else {
            $stmt = db()->prepare("
                INSERT INTO sdm_pelatihan (
                    tahun_anggaran, judul_pelatihan, jenis_pelatihan, penyelenggara,
                    tanggal_mulai, tanggal_selesai, jumlah_peserta, daftar_peserta,
                    total_biaya, bukti_file, catatan
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tahun, $judul, $jenis, $penyelenggara, $tglMulai, $tglSelesai, $peserta, $daftarPeserta, $biaya, $fileUploaded, $catatan]);
            flash_alert('success', 'Kegiatan pelatihan baru berhasil dicatat.');
        }
        redirect(module_url('sdm_kepatuhan.php', ['tab' => 'pelatihan', 'tahun' => $tahun]));
    }

    // 4. Hapus Pelatihan
    if ($action === 'delete_pelatihan') {
        $id = (int)($_POST['id'] ?? 0);
        $tahun = (int)($_POST['tahun'] ?? date('Y'));
        if ($id > 0) {
            $stmt = db()->prepare("DELETE FROM sdm_pelatihan WHERE id = ?");
            $stmt->execute([$id]);
            flash_alert('success', 'Catatan pelatihan berhasil dihapus.');
        }
        redirect(module_url('sdm_kepatuhan.php', ['tab' => 'pelatihan', 'tahun' => $tahun]));
    }

    // 5. Update Pagu Biaya Pegawai (Untuk Target 5% OJK)
    if ($action === 'update_pagu') {
        $tahun = (int)($_POST['tahun'] ?? date('Y'));
        $pagu = (float)str_replace(['.', ','], '', $_POST['pagu_biaya_pegawai'] ?? '0');
        $target = (float)($_POST['target_persen'] ?? 5.00);

        $stmt = db()->prepare("
            INSERT INTO sdm_anggaran_tahunan (tahun, pagu_biaya_pegawai, target_persen)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE pagu_biaya_pegawai = VALUES(pagu_biaya_pegawai), target_persen = VALUES(target_persen)
        ");
        $stmt->execute([$tahun, $pagu, $target]);
        flash_alert('success', 'Pagu biaya pegawai tahun '.$tahun.' berhasil diperbarui.');
        redirect(module_url('sdm_kepatuhan.php', ['tab' => 'pelatihan', 'tahun' => $tahun]));
    }

    // 6. Simpan / Edit Mandatory Block Leave
    if ($action === 'save_block_leave') {
        $id = (int)($_POST['id'] ?? 0);
        $tahun = (int)($_POST['tahun'] ?? date('Y'));
        $nama = trim($_POST['nama_pegawai'] ?? '');
        $nip = trim($_POST['nip'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? '');
        $cabang = trim($_POST['cabang_nama'] ?? '');
        $fungsiRawan = trim($_POST['fungsi_rawan'] ?? 'Ya');
        $tglMulai = $_POST['tanggal_mulai'] ?? '';
        $tglSelesai = $_POST['tanggal_selesai'] ?? '';
        $hari = (int)($_POST['jumlah_hari'] ?? 5);
        $pengganti = trim($_POST['petugas_pengganti'] ?? '');
        $status = trim($_POST['status'] ?? 'Terjadwal');
        $catatan = trim($_POST['catatan'] ?? '');

        if (!$nama || !$jabatan || !$tglMulai || !$tglSelesai || !$pengganti) {
            flash_alert('danger', 'Harap lengkapi semua kolom jadwal block leave.');
            redirect(module_url('sdm_kepatuhan.php', ['tab' => 'block_leave', 'tahun' => $tahun]));
        }

        if ($id > 0) {
            $stmt = db()->prepare("
                UPDATE sdm_block_leave SET
                    tahun = ?, nama_pegawai = ?, nip = ?, jabatan = ?, cabang_nama = ?,
                    fungsi_rawan = ?, tanggal_mulai = ?, tanggal_selesai = ?, jumlah_hari = ?,
                    petugas_pengganti = ?, status = ?, catatan = ?
                WHERE id = ?
            ");
            $stmt->execute([$tahun, $nama, $nip, $jabatan, $cabang, $fungsiRawan, $tglMulai, $tglSelesai, $hari, $pengganti, $status, $catatan, $id]);
            flash_alert('success', 'Jadwal Mandatory Block Leave berhasil diperbarui.');
        } else {
            $stmt = db()->prepare("
                INSERT INTO sdm_block_leave (
                    tahun, nama_pegawai, nip, jabatan, cabang_nama, fungsi_rawan,
                    tanggal_mulai, tanggal_selesai, jumlah_hari, petugas_pengganti, status, catatan
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tahun, $nama, $nip, $jabatan, $cabang, $fungsiRawan, $tglMulai, $tglSelesai, $hari, $pengganti, $status, $catatan]);
            flash_alert('success', 'Jadwal Mandatory Block Leave baru berhasil didaftarkan.');
        }
        redirect(module_url('sdm_kepatuhan.php', ['tab' => 'block_leave', 'tahun' => $tahun]));
    }

    // 7. Hapus Block Leave
    if ($action === 'delete_block_leave') {
        $id = (int)($_POST['id'] ?? 0);
        $tahun = (int)($_POST['tahun'] ?? date('Y'));
        if ($id > 0) {
            $stmt = db()->prepare("DELETE FROM sdm_block_leave WHERE id = ?");
            $stmt->execute([$id]);
            flash_alert('success', 'Jadwal block leave berhasil dihapus.');
        }
        redirect(module_url('sdm_kepatuhan.php', ['tab' => 'block_leave', 'tahun' => $tahun]));
    }
}

// ==========================================
// DATA FETCHING & KPI CALCULATIONS
// ==========================================

// TAB 1: Sertifikasi Profesi
$sertifikasiList = db()->query("SELECT * FROM sdm_sertifikasi ORDER BY tanggal_kadaluarsa ASC")->fetchAll();
$totalSertifikat = count($sertifikasiList);
$aktifCount = 0;
$expiringCount = 0; // < 90 hari
$expiredCount = 0;
$today = new DateTime();

foreach ($sertifikasiList as &$s) {
    $expDate = new DateTime($s['tanggal_kadaluarsa']);
    $diff = (int)$today->diff($expDate)->format('%r%a');
    if ($diff < 0) {
        $s['status_badge'] = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="bi bi-x-circle me-1"></i>Expired</span>';
        $expiredCount++;
    } elseif ($diff <= 90) {
        $s['status_badge'] = '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1"><i class="bi bi-clock-history me-1"></i>H-'.$diff.' Hari</span>';
        $expiringCount++;
    } else {
        $s['status_badge'] = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle me-1"></i>Aktif ('.$diff.' Hari)</span>';
        $aktifCount++;
    }
}
unset($s);

// TAB 2: Pelatihan Wajib 5%
$paguData = db()->query("SELECT * FROM sdm_anggaran_tahunan WHERE tahun = {$tahunAktif}")->fetch();
$paguBiayaPegawai = (float)($paguData['pagu_biaya_pegawai'] ?? 1200000000);
$targetPersen = (float)($paguData['target_persen'] ?? 5.00);
$targetNominal = ($paguBiayaPegawai * $targetPersen) / 100;

$pelatihanList = db()->query("SELECT * FROM sdm_pelatihan WHERE tahun_anggaran = {$tahunAktif} ORDER BY tanggal_mulai DESC")->fetchAll();
$totalBiayaPelatihan = 0;
$totalPeserta = 0;
foreach ($pelatihanList as $p) {
    $totalBiayaPelatihan += (float)$p['total_biaya'];
    $totalPeserta += (int)$p['jumlah_peserta'];
}
$persenTercapai = ($paguBiayaPegawai > 0) ? ($totalBiayaPelatihan / $paguBiayaPegawai) * 100 : 0;

// TAB 3: Block Leave
$blockLeaveList = db()->query("SELECT * FROM sdm_block_leave WHERE tahun = {$tahunAktif} ORDER BY tanggal_mulai ASC")->fetchAll();
$totalLeave = count($blockLeaveList);
$selesaiLeaveCount = 0;
$terjadwalLeaveCount = 0;
foreach ($blockLeaveList as $bl) {
    if ($bl['status'] === 'Sudah Dilaksanakan') {
        $selesaiLeaveCount++;
    } else {
        $terjadwalLeaveCount++;
    }
}

// ==========================================
// RENDER HTML VIEW
// ==========================================

$head = '
<style>
.kpi-card {
  border-radius: 14px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.tab-custom .nav-link {
  font-weight: 600;
  color: #64748b;
  border-radius: 8px;
  padding: 8px 16px;
}
.tab-custom .nav-link.active {
  background: #0f172a;
  color: #ffffff !important;
}
</style>
';

$body = '
'.($flash ? '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" style="border-left: 4px solid #16A34A !important;"><i class="bi bi-check-circle-fill me-2 text-success"></i>'.e($flash).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '').'
'.($error ? '<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" style="border-left: 4px solid #DC2626 !important;"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>'.e($error).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '').'

<!-- Header Banner -->
<div class="card border-0 mb-4 overflow-hidden shadow-sm" style="background: linear-gradient(135deg, #ffffff 0%, #eff6ff 100%); border: 1px solid #bfdbfe !important; border-left: 5px solid #2563eb !important; border-radius: 16px;">
  <div class="card-body p-3 p-md-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="badge bg-primary px-2.5 py-1" style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; border-radius: 6px;">OJK COMPLIANCE &amp; GOVERNANCE</span>
          <span class="text-muted small">| Pengawasan Tata Kelola SDM &amp; Anti-Fraud BPR</span>
        </div>
        <h3 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
          <i class="bi bi-shield-check text-primary"></i> Kepatuhan SDM &amp; Tata Kelola OJK
        </h3>
        <p class="text-secondary small mb-0">Pemantauan sertifikasi profesi perbankan, realisasi anggaran pelatihan minimal 5% (POJK 47/2017), dan cuti wajib 5 hari (*mandatory block leave*).</p>
      </div>
      <div class="d-flex flex-wrap align-items-center gap-2">
        <a href="'.e(module_url('memo_pengajuan.php')).'" class="btn btn-outline-warning text-dark fw-bold btn-sm px-3 bg-white d-inline-flex align-items-center gap-1.5">
          <i class="bi bi-file-earmark-text"></i> Memo Pengajuan
        </a>
        <a href="'.e(module_url('dashboard.php', ['tab' => 'kartu'])).'" class="btn btn-outline-secondary fw-semibold btn-sm px-3 bg-white d-inline-flex align-items-center gap-1.5">
          <i class="bi bi-card-heading"></i> Kartu Inventaris
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Nav Tabs -->
<ul class="nav nav-pills tab-custom gap-2 mb-4">
  <li class="nav-item">
    <a class="nav-link '.($tab === 'sertifikasi' ? 'active' : 'bg-white border').'" href="'.e(module_url('sdm_kepatuhan.php', ['tab' => 'sertifikasi'])).'">
      <i class="bi bi-award me-1.5"></i> 1. Sertifikasi Profesi OJK / LSP ('.count($sertifikasiList).')
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link '.($tab === 'pelatihan' ? 'active' : 'bg-white border').'" href="'.e(module_url('sdm_kepatuhan.php', ['tab' => 'pelatihan', 'tahun' => $tahunAktif])).'">
      <i class="bi bi-pie-chart me-1.5"></i> 2. Pelatihan Wajib &amp; Alokasi 5% OJK
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link '.($tab === 'block_leave' ? 'active' : 'bg-white border').'" href="'.e(module_url('sdm_kepatuhan.php', ['tab' => 'block_leave', 'tahun' => $tahunAktif])).'">
      <i class="bi bi-calendar-check me-1.5"></i> 3. Mandatory Block Leave (Cuti Wajib 5 Hari)
    </a>
  </li>
</ul>
';

// ==========================================
// KONTEN TAB 1: SERTIFIKASI PROFESI OJK
// ==========================================
if ($tab === 'sertifikasi') {
    $rows = '';
    if (empty($sertifikasiList)) {
        $rows = '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-award fs-1 d-block mb-2 opacity-50"></i>Belum ada data sertifikasi profesi yang didaftarkan.<br><button type="button" class="btn btn-primary btn-sm mt-3 fw-bold" onclick="openSertifikasiModal()"><i class="bi bi-plus-lg me-1"></i> Tambah Sertifikasi Pertama</button></td></tr>';
    } else {
        $no = 1;
        foreach ($sertifikasiList as $row) {
            $rJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
            $fileLink = '';
            if (!empty($row['file_sertifikat'])) {
                $fUrl = sdm_file_url($row['file_sertifikat']);
                $fileLink = '<a href="'.e($fUrl).'" target="_blank" class="badge bg-light text-primary border text-decoration-none d-inline-flex align-items-center gap-1 mt-1"><i class="bi bi-file-earmark-pdf"></i> Lihat Berkas</a>';
            }

            $rows .= '
            <tr>
              <td class="text-center font-monospace text-muted small">'.$no++.'</td>
              <td>
                <strong class="text-dark d-block">'.e($row['nama_pegawai']).'</strong>
                <div class="small text-muted font-monospace">'.e($row['nip'] ?: '-').'</div>
              </td>
              <td>
                <div class="small fw-semibold text-dark">'.e($row['jabatan']).'</div>
                <span class="badge bg-light text-secondary border px-2 py-0.5" style="font-size:0.68rem;">'.e($row['cabang_nama']).'</span>
              </td>
              <td>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold mb-1">'.e($row['bidang_sertifikasi']).'</span>
                <div class="small text-muted font-monospace" style="font-size:0.75rem;">No: '.e($row['nomor_sertifikat']).'</div>
                <div class="small text-muted">Lembaga: '.e($row['lembaga_penerbit']).'</div>
                '.$fileLink.'
              </td>
              <td>
                <div class="small text-muted">Terbit: '.date('d/m/Y', strtotime($row['tanggal_terbit'])).'</div>
                <div class="small fw-bold text-dark">Exp: '.date('d/m/Y', strtotime($row['tanggal_kadaluarsa'])).'</div>
              </td>
              <td class="text-center text-nowrap">
                '.$row['status_badge'].'
              </td>
              <td class="text-end text-nowrap">
                <div class="btn-group btn-group-sm shadow-xs">
                  <button type="button" class="btn btn-light border" title="Edit Data" onclick=\'editSertifikasi('.$rJson.')\'>
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button type="button" class="btn btn-light border text-danger" title="Hapus Data" onclick="confirmDeleteSertifikasi('.(int)$row['id'].')">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </td>
            </tr>';
        }
    }

    $body .= '
    <!-- KPI Row Sertifikasi -->
    <div class="row g-3 mb-4">
      <div class="col-sm-3 col-6">
        <div class="card p-3 kpi-card bg-white" style="border-left: 4px solid #3b82f6 !important;">
          <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.7rem;">Total Sertifikat</div>
          <div class="fs-4 fw-bold text-dark mt-1">'.$totalSertifikat.' <small class="fs-6 fw-normal text-muted">Orang</small></div>
        </div>
      </div>
      <div class="col-sm-3 col-6">
        <div class="card p-3 kpi-card bg-white" style="border-left: 4px solid #16a34a !important;">
          <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.7rem;">Sertifikat Aktif</div>
          <div class="fs-4 fw-bold text-success mt-1">'.$aktifCount.'</div>
        </div>
      </div>
      <div class="col-sm-3 col-6">
        <div class="card p-3 kpi-card bg-white" style="border-left: 4px solid #eab308 !important;">
          <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.7rem;">Segera Expired (&le; 90 Hari)</div>
          <div class="fs-4 fw-bold text-warning mt-1">'.$expiringCount.'</div>
        </div>
      </div>
      <div class="col-sm-3 col-6">
        <div class="card p-3 kpi-card bg-white" style="border-left: 4px solid #dc2626 !important;">
          <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.7rem;">Sudah Expired</div>
          <div class="fs-4 fw-bold text-danger mt-1">'.$expiredCount.'</div>
        </div>
      </div>
    </div>

    <!-- Table Card -->
    <div class="card p-0 overflow-hidden shadow-sm border-0 mb-4" style="border-radius: 14px;">
      <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
        <div class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.05em;">
          <i class="bi bi-award-fill text-primary me-2"></i>Daftar Sertifikasi Profesi Perbankan OJK / LSP ('.count($sertifikasiList).')
        </div>
        <button type="button" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm d-inline-flex align-items-center gap-1.5" onclick="openSertifikasiModal()">
          <i class="bi bi-plus-circle"></i> Tambah Sertifikasi
        </button>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 45px;" class="text-center font-monospace">NO</th>
              <th style="width: 200px;">NAMA PEGAWAI</th>
              <th style="width: 180px;">JABATAN &amp; CABANG</th>
              <th>BIDANG SERTIFIKASI &amp; LEMBAGA</th>
              <th style="width: 160px;">MASA BERLAKU</th>
              <th style="width: 140px;" class="text-center">STATUS</th>
              <th style="width: 90px;" class="text-end">AKSI</th>
            </tr>
          </thead>
          <tbody>
            '.$rows.'
          </tbody>
        </table>
      </div>
    </div>
    ';
}

// ==========================================
// KONTEN TAB 2: PELATIHAN & 5% ANGGARAN
// ==========================================
if ($tab === 'pelatihan') {
    $rows = '';
    if (empty($pelatihanList)) {
        $rows = '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-pie-chart fs-1 d-block mb-2 opacity-50"></i>Belum ada catatan pelatihan untuk tahun '.$tahunAktif.'.<br><button type="button" class="btn btn-primary btn-sm mt-3 fw-bold" onclick="openPelatihanModal()"><i class="bi bi-plus-lg me-1"></i> Catat Pelatihan Pertama</button></td></tr>';
    } else {
        $no = 1;
        foreach ($pelatihanList as $row) {
            $rJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
            $fileLink = '';
            if (!empty($row['bukti_file'])) {
                $fUrl = sdm_file_url($row['bukti_file']);
                $fileLink = '<a href="'.e($fUrl).'" target="_blank" class="badge bg-light text-primary border text-decoration-none d-inline-flex align-items-center gap-1 mt-1"><i class="bi bi-file-earmark-pdf"></i> Sertifikat / Bukti</a>';
            }

            $badgeJenis = match($row['jenis_pelatihan']) {
                'APU PPT Wajib' => 'bg-danger-subtle text-danger border-danger-subtle',
                'Manajemen Risiko' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                'Tata Kelola & Anti-Fraud' => 'bg-primary-subtle text-primary border-primary-subtle',
                default => 'bg-secondary-subtle text-secondary border-secondary-subtle'
            };

            $rows .= '
            <tr>
              <td class="text-center font-monospace text-muted small">'.$no++.'</td>
              <td>
                <strong class="text-dark d-block">'.e($row['judul_pelatihan']).'</strong>
                <div class="small text-muted">Lembaga: '.e($row['penyelenggara']).'</div>
                '.$fileLink.'
              </td>
              <td>
                <span class="badge '.$badgeJenis.' border px-2 py-1">'.e($row['jenis_pelatihan']).'</span>
              </td>
              <td class="small text-muted">
                <i class="bi bi-calendar3 me-1"></i>'.date('d/m/Y', strtotime($row['tanggal_mulai'])).' s/d '.date('d/m/Y', strtotime($row['tanggal_selesai'])).'
              </td>
              <td class="text-center">
                <span class="badge bg-light text-dark border px-2 py-1 fw-bold">'.(int)$row['jumlah_peserta'].' Org</span>
              </td>
              <td class="text-nowrap fw-bold text-dark">
                Rp. '.number_format((float)$row['total_biaya'], 0, ',', '.').',-
              </td>
              <td class="text-end text-nowrap">
                <div class="btn-group btn-group-sm shadow-xs">
                  <button type="button" class="btn btn-light border" title="Edit Pelatihan" onclick=\'editPelatihan('.$rJson.')\'>
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button type="button" class="btn btn-light border text-danger" title="Hapus Pelatihan" onclick="confirmDeletePelatihan('.(int)$row['id'].')">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </td>
            </tr>';
        }
    }

    $progressBarColor = ($persenTercapai >= 5.0) ? 'bg-success' : (($persenTercapai >= 3.0) ? 'bg-warning' : 'bg-danger');

    $body .= '
    <!-- Alokasi 5% KPI Panel -->
    <div class="card p-4 border-0 shadow-sm mb-4" style="border-radius: 14px; background: #ffffff;">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div>
          <span class="badge bg-warning text-dark px-2.5 py-1 mb-1 fw-bold" style="font-size:0.68rem;">POJK NO. 47/POJK.03/2017</span>
          <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
            Realisasi Anggaran Pelatihan SDM Tahun '.$tahunAktif.' (Wajib &ge; 5%)
          </h5>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-outline-secondary btn-sm fw-semibold" onclick="openPaguModal('.$tahunAktif.', '.$paguBiayaPegawai.', '.$targetPersen.')">
            <i class="bi bi-gear me-1"></i> Atur Pagu Biaya Pegawai
          </button>
          <button type="button" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm" onclick="openPelatihanModal()">
            <i class="bi bi-plus-circle me-1"></i> Catat Pelatihan
          </button>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-4 col-12">
          <div class="p-3 bg-light rounded-3 border">
            <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.7rem;">Pagu Realisasi Biaya Pegawai (Th Sebelumnya)</div>
            <div class="fs-5 fw-bold text-dark mt-1">Rp. '.number_format($paguBiayaPegawai, 0, ',', '.').',-</div>
          </div>
        </div>
        <div class="col-md-4 col-12">
          <div class="p-3 bg-light rounded-3 border">
            <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.7rem;">Target Minimal OJK ('.$targetPersen.'%)</div>
            <div class="fs-5 fw-bold text-primary mt-1">Rp. '.number_format($targetNominal, 0, ',', '.').',-</div>
          </div>
        </div>
        <div class="col-md-4 col-12">
          <div class="p-3 bg-light rounded-3 border" style="border-left: 4px solid '.($persenTercapai >= 5.0 ? '#16a34a' : '#eab308').' !important;">
            <div class="text-muted small fw-semibold text-uppercase" style="font-size:0.7rem;">Total Realisasi Biaya Pelatihan (YTD)</div>
            <div class="fs-5 fw-bold text-dark mt-1">Rp. '.number_format($totalBiayaPelatihan, 0, ',', '.').',- <span class="fs-6 fw-bold '.($persenTercapai >= 5.0 ? 'text-success' : 'text-warning').'">('.number_format($persenTercapai, 2, ',', '.').'%)</span></div>
          </div>
        </div>
      </div>

      <div>
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span class="small fw-bold text-secondary">Progres Pemenuhan Kewajiban 5% OJK</span>
          <span class="small fw-bold text-dark">'.number_format($persenTercapai, 2, ',', '.').'% / 5.00%</span>
        </div>
        <div class="progress" style="height: 12px; border-radius: 6px;">
          <div class="progress-bar '.$progressBarColor.' progress-bar-striped progress-bar-animated" role="progressbar" style="width: '.min(100, ($persenTercapai / 5.0) * 100).'%;"></div>
        </div>
        <div class="small text-muted mt-1" style="font-size:0.72rem;">
          '.($persenTercapai >= 5.0 ? '<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i>Sesuai Ketentuan:</span> Telah memenuhi kuota minimal 5% alokasi pengembangan kompetensi SDM sesuai POJK.' : '<span class="text-warning-emphasis fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i>Perhatian:</span> Realisasi pelatihan belum mencapai kuota 5% dari biaya pegawai. Jadwalkan pelatihan tambahan sebelum akhir tahun buku.').'
        </div>
      </div>
    </div>

    <!-- Table Pelatihan -->
    <div class="card p-0 overflow-hidden shadow-sm border-0 mb-4" style="border-radius: 14px;">
      <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
        <div class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.05em;">
          <i class="bi bi-journal-check text-primary me-2"></i>Catatan Kegiatan Pelatihan &amp; Workshop ('.count($pelatihanList).')
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 45px;" class="text-center font-monospace">NO</th>
              <th>JUDUL PELATIHAN &amp; PENYELENGGARA</th>
              <th style="width: 170px;">JENIS MATERI</th>
              <th style="width: 180px;">TANGGAL PELAKSANAAN</th>
              <th style="width: 100px;" class="text-center">PESERTA</th>
              <th style="width: 170px;">BIAYA PELATIHAN</th>
              <th style="width: 90px;" class="text-end">AKSI</th>
            </tr>
          </thead>
          <tbody>
            '.$rows.'
          </tbody>
        </table>
      </div>
    </div>
    ';
}

// ==========================================
// KONTEN TAB 3: MANDATORY BLOCK LEAVE
// ==========================================
if ($tab === 'block_leave') {
    $rows = '';
    if (empty($blockLeaveList)) {
        $rows = '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-calendar-check fs-1 d-block mb-2 opacity-50"></i>Belum ada jadwal Mandatory Block Leave untuk tahun '.$tahunAktif.'.<br><button type="button" class="btn btn-primary btn-sm mt-3 fw-bold" onclick="openBlockLeaveModal()"><i class="bi bi-plus-lg me-1"></i> Jadwalkan Block Leave Pertama</button></td></tr>';
    } else {
        $no = 1;
        foreach ($blockLeaveList as $row) {
            $rJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
            $stBadge = ($row['status'] === 'Sudah Dilaksanakan') 
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle me-1"></i>Sudah Selesai</span>'
                : '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1"><i class="bi bi-clock me-1"></i>Terjadwal</span>';

            $rows .= '
            <tr>
              <td class="text-center font-monospace text-muted small">'.$no++.'</td>
              <td>
                <strong class="text-dark d-block">'.e($row['nama_pegawai']).'</strong>
                <div class="small text-muted font-monospace">'.e($row['nip'] ?: '-').'</div>
              </td>
              <td>
                <div class="small fw-semibold text-dark">'.e($row['jabatan']).'</div>
                <span class="badge bg-light text-secondary border px-2 py-0.5" style="font-size:0.68rem;">'.e($row['cabang_nama']).'</span>
                '.($row['fungsi_rawan'] === 'Ya' ? '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5" style="font-size:0.65rem;">Fungsi Rawan Fraud</span>' : '').'
              </td>
              <td>
                <div class="small text-dark fw-bold"><i class="bi bi-calendar-event me-1 text-primary"></i>'.date('d/m/Y', strtotime($row['tanggal_mulai'])).' s/d '.date('d/m/Y', strtotime($row['tanggal_selesai'])).'</div>
                <div class="small text-muted">Durasi: '.(int)$row['jumlah_hari'].' Hari Kerja Berturut-turut</div>
              </td>
              <td>
                <div class="small fw-bold text-dark"><i class="bi bi-person-badge me-1 text-secondary"></i>'.e($row['petugas_pengganti']).'</div>
                <div class="small text-muted" style="font-size:0.72rem;">Menggantikan wewenang &amp; akses sistem</div>
              </td>
              <td class="text-center text-nowrap">
                '.$stBadge.'
              </td>
              <td class="text-end text-nowrap">
                <div class="btn-group btn-group-sm shadow-xs">
                  <button type="button" class="btn btn-light border" title="Edit Jadwal" onclick=\'editBlockLeave('.$rJson.')\'>
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button type="button" class="btn btn-light border text-danger" title="Hapus Jadwal" onclick="confirmDeleteBlockLeave('.(int)$row['id'].')">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </td>
            </tr>';
        }
    }

    $body .= '
    <!-- Anti-Fraud Info Banner -->
    <div class="alert alert-warning border-0 shadow-sm mb-4" style="border-left: 4px solid #f59e0b !important; border-radius: 12px;">
      <div class="d-flex align-items-start gap-3">
        <i class="bi bi-shield-exclamation fs-3 text-warning"></i>
        <div>
          <h6 class="fw-bold text-dark mb-1">Kewajiban Mandatory Block Leave (POJK No. 62/POJK.03/2020 &amp; Tata Kelola BPR)</h6>
          <p class="small text-secondary mb-0">Pegawai pada unit kerja operasional dan fungsi berisiko/rawan fraud (Teller, Kasir, Bagian Umum/Logistik, Account Officer) <strong>wajib mengambil cuti berturut-turut minimal 5 (lima) hari kerja per tahun</strong>. Selama cuti, wewenang dan akun sistem wajib dialihkan penuh kepada petugas pengganti untuk mendeteksi transaksi janggal.</p>
        </div>
      </div>
    </div>

    <!-- Table Block Leave -->
    <div class="card p-0 overflow-hidden shadow-sm border-0 mb-4" style="border-radius: 14px;">
      <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
        <div class="fw-bold text-dark text-uppercase small" style="letter-spacing: 0.05em;">
          <i class="bi bi-calendar-check text-primary me-2"></i>Jadwal Pemenuhan Cuti Wajib Pegawai ('.count($blockLeaveList).')
        </div>
        <button type="button" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm d-inline-flex align-items-center gap-1.5" onclick="openBlockLeaveModal()">
          <i class="bi bi-plus-circle"></i> Jadwalkan Block Leave
        </button>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 45px;" class="text-center font-monospace">NO</th>
              <th style="width: 200px;">NAMA PEGAWAI</th>
              <th style="width: 200px;">JABATAN &amp; CABANG</th>
              <th>PERIODE CUTI WAJIB (&ge; 5 HARI)</th>
              <th style="width: 200px;">PETUGAS PENGGANTI</th>
              <th style="width: 140px;" class="text-center">STATUS</th>
              <th style="width: 90px;" class="text-end">AKSI</th>
            </tr>
          </thead>
          <tbody>
            '.$rows.'
          </tbody>
        </table>
      </div>
    </div>
    ';
}

// ==========================================
// MODALS
// ==========================================

// MODAL 1: SERTIFIKASI
$body .= '
<div class="modal fade" id="modalSertifikasi" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="post" action="'.e(module_url('sdm_kepatuhan.php')).'" enctype="multipart/form-data" class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="save_sertifikasi">
      <input type="hidden" name="id" id="sertifikasiId" value="0">
      
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title d-flex align-items-center gap-2" id="modalSertifikasiTitle">
          <i class="bi bi-award text-warning"></i> Form Sertifikasi Profesi OJK / LSP
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body p-4">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Nama Lengkap Pegawai <span class="text-danger">*</span></label>
            <input type="text" name="nama_pegawai" id="sertifNama" class="form-control" required placeholder="Contoh: Budi Santoso, SE">
          </div>
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">NIP Pegawai</label>
            <input type="text" name="nip" id="sertifNip" class="form-control" placeholder="Nomor Induk Pegawai">
          </div>
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Jabatan / Posisi <span class="text-danger">*</span></label>
            <input type="text" name="jabatan" id="sertifJabatan" class="form-control" required placeholder="Contoh: Direktur Kepatuhan / PE Manajemen Risiko">
          </div>
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Kantor Cabang <span class="text-danger">*</span></label>
            <select name="cabang_nama" id="sertifCabang" class="form-select" required>';
foreach ($cabangList as $c) {
    $cName = $c['nama_cabang'] ?? '';
    $body .= '<option value="'.e($cName).'">'.e($cName).'</option>';
}
$body .= '
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Bidang Sertifikasi Profesi <span class="text-danger">*</span></label>
            <input type="text" name="bidang_sertifikasi" id="sertifBidang" class="form-control" list="bidangSuggestions" required placeholder="Pilih atau ketik bidang...">
            <datalist id="bidangSuggestions">
              <option value="Manajemen Risiko BPR Tk. 1">
              <option value="Manajemen Risiko BPR Tk. 2">
              <option value="Direksi BPR (Executive)">
              <option value="Dewan Komisaris BPR">
              <option value="Kepatuhan & APU PPT">
              <option value="Audit Intern BPR (SKAI)">
              <option value="Operasional & Sarana Prasarana">
            </datalist>
          </div>
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Lembaga Sertifikasi (LSP) <span class="text-danger">*</span></label>
            <input type="text" name="lembaga_penerbit" id="sertifLembaga" class="form-control" required value="LSP Certif" placeholder="Contoh: LSP Certif / LSP LJKP / IBI">
          </div>
          <div class="col-md-12">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Nomor Registrasi Sertifikat <span class="text-danger">*</span></label>
            <input type="text" name="nomor_sertifikat" id="sertifNomor" class="form-control font-monospace" required placeholder="Contoh: CERT/MR-BPR/2026/0012">
          </div>
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Tanggal Terbit <span class="text-danger">*</span></label>
            <input type="date" name="tanggal_terbit" id="sertifTglTerbit" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Tanggal Kadaluarsa (Expired) <span class="text-danger">*</span></label>
            <input type="date" name="tanggal_kadaluarsa" id="sertifTglExp" class="form-control" required>
          </div>
          <div class="col-md-12">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Upload File Sertifikat (PDF / Foto JPG)</label>
            <input type="file" name="file_sertifikat" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
            <small class="text-muted">Format yang didukung: PDF, JPG, PNG (Maks 10MB)</small>
          </div>
          <div class="col-md-12">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Catatan Tambahan</label>
            <textarea name="catatan" id="sertifCatatan" class="form-control" rows="2" placeholder="Catatan perpanjangan, nomor SK, dll."></textarea>
          </div>
        </div>
      </div>

      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary fw-bold px-4">Simpan Sertifikasi</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL 2: PELATIHAN -->
<div class="modal fade" id="modalPelatihan" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="post" action="'.e(module_url('sdm_kepatuhan.php')).'" enctype="multipart/form-data" class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="save_pelatihan">
      <input type="hidden" name="id" id="pelatihanId" value="0">
      <input type="hidden" name="tahun_anggaran" value="'.$tahunAktif.'">

      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title d-flex align-items-center gap-2" id="modalPelatihanTitle">
          <i class="bi bi-journal-check text-warning"></i> Form Catatan Pelatihan SDM (Alokasi 5%)
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body p-4">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Nama / Judul Pelatihan <span class="text-danger">*</span></label>
            <input type="text" name="judul_pelatihan" id="pelatihanJudul" class="form-control" required placeholder="Contoh: Workshop Penerapan Program APU PPT Terkini">
          </div>
          <div class="col-md-4">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Jenis Materi Pelatihan <span class="text-danger">*</span></label>
            <select name="jenis_pelatihan" id="pelatihanJenis" class="form-select" required>
              <option value="APU PPT Wajib">APU PPT Wajib</option>
              <option value="Manajemen Risiko">Manajemen Risiko</option>
              <option value="Tata Kelola & Anti-Fraud">Tata Kelola & Anti-Fraud</option>
              <option value="Teknis Operasional / IT">Teknis Operasional / IT</option>
              <option value="Service Excellence / HR">Service Excellence / HR</option>
              <option value="Lainnya">Lainnya</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Lembaga Penyelenggara <span class="text-danger">*</span></label>
            <input type="text" name="penyelenggara" id="pelatihanPenyelenggara" class="form-control" required placeholder="Contoh: Perbarindo / OJK / Yayasan Dharma Bhakti">
          </div>
          <div class="col-md-3">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Tgl Mulai <span class="text-danger">*</span></label>
            <input type="date" name="tanggal_mulai" id="pelatihanTglMulai" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Tgl Selesai <span class="text-danger">*</span></label>
            <input type="date" name="tanggal_selesai" id="pelatihanTglSelesai" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Jumlah Peserta <span class="text-danger">*</span></label>
            <input type="number" name="jumlah_peserta" id="pelatihanPeserta" class="form-control" required min="1" value="1">
          </div>
          <div class="col-md-8">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Total Biaya Pelatihan (Rp) <span class="text-danger">*</span></label>
            <input type="text" name="total_biaya" id="pelatihanBiaya" class="form-control fw-bold" required placeholder="Contoh: 7500000">
            <small class="text-muted">Biaya pendaftaran, materi, dan sertifikat yang diakui dalam alokasi 5%</small>
          </div>
          <div class="col-md-12">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Daftar Nama Pegawai Peserta</label>
            <textarea name="daftar_peserta" id="pelatihanDaftarPeserta" class="form-control" rows="2" placeholder="Tuliskan nama pegawai peserta pelatihan..."></textarea>
          </div>
          <div class="col-md-12">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Upload Bukti / Sertifikat Pelatihan (PDF / JPG)</label>
            <input type="file" name="bukti_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
          </div>
          <div class="col-md-12">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Catatan Tambahan</label>
            <textarea name="catatan" id="pelatihanCatatan" class="form-control" rows="2" placeholder="Catatan hasil pelatihan / nomor sertifikat peserta..."></textarea>
          </div>
        </div>
      </div>

      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary fw-bold px-4">Simpan Pelatihan</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL 3: PAGU ANGGARAN -->
<div class="modal fade" id="modalPagu" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="'.e(module_url('sdm_kepatuhan.php')).'" class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="update_pagu">
      <input type="hidden" name="tahun" id="paguTahun" value="'.$tahunAktif.'">

      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title d-flex align-items-center gap-2">
          <i class="bi bi-cash-stack text-warning"></i> Atur Pagu Biaya Pegawai Tahun '.$tahunAktif.'
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body p-4">
        <p class="text-secondary small">Sesuai <strong>POJK No. 47/POJK.03/2017</strong>, BPR wajib mengalokasikan anggaran pelatihan & pengembangan SDM minimal <strong>5% dari realisasi biaya pegawai tahun sebelumnya</strong>.</p>
        <div class="mb-3">
          <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Pagu Realisasi Biaya Pegawai (Rp) <span class="text-danger">*</span></label>
          <input type="text" name="pagu_biaya_pegawai" id="paguBiayaInput" class="form-control fw-bold fs-5" required>
        </div>
        <div class="mb-3">
          <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Target Persentase Kewajiban OJK (%)</label>
          <input type="number" step="0.01" name="target_persen" id="paguTargetInput" class="form-control" value="5.00" required>
        </div>
      </div>

      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary fw-bold px-4">Simpan Pagu</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL 4: BLOCK LEAVE -->
<div class="modal fade" id="modalBlockLeave" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="post" action="'.e(module_url('sdm_kepatuhan.php')).'" class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
      <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
      <input type="hidden" name="action" value="save_block_leave">
      <input type="hidden" name="id" id="blId" value="0">
      <input type="hidden" name="tahun" value="'.$tahunAktif.'">

      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title d-flex align-items-center gap-2" id="modalBlockLeaveTitle">
          <i class="bi bi-calendar-check text-warning"></i> Form Mandatory Block Leave (Cuti Wajib 5 Hari)
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body p-4">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Nama Pegawai <span class="text-danger">*</span></label>
            <input type="text" name="nama_pegawai" id="blNama" class="form-control" required placeholder="Contoh: Siti Rahmawati">
          </div>
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">NIP Pegawai</label>
            <input type="text" name="nip" id="blNip" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Jabatan / Posisi <span class="text-danger">*</span></label>
            <input type="text" name="jabatan" id="blJabatan" class="form-control" required placeholder="Contoh: Teller / Kasir / Kabag Umum">
          </div>
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Kantor Cabang <span class="text-danger">*</span></label>
            <select name="cabang_nama" id="blCabang" class="form-select" required>';
foreach ($cabangList as $c) {
    $cName = $c['nama_cabang'] ?? '';
    $body .= '<option value="'.e($cName).'">'.e($cName).'</option>';
}
$body .= '
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Fungsi Rawan Fraud? <span class="text-danger">*</span></label>
            <select name="fungsi_rawan" id="blFungsi" class="form-select" required>
              <option value="Ya">Ya (Teller, Kasir, Pengadaan, AO)</option>
              <option value="Tidak">Tidak</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Tanggal Mulai Cuti <span class="text-danger">*</span></label>
            <input type="date" name="tanggal_mulai" id="blTglMulai" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Tanggal Selesai Cuti <span class="text-danger">*</span></label>
            <input type="date" name="tanggal_selesai" id="blTglSelesai" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Petugas Pengganti (Dual Control) <span class="text-danger">*</span></label>
            <input type="text" name="petugas_pengganti" id="blPengganti" class="form-control" required placeholder="Nama pegawai pengganti tugas">
            <small class="text-muted">Akses user/password operasional dialihkan ke pengganti</small>
          </div>
          <div class="col-md-6">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Status Pelaksanaan <span class="text-danger">*</span></label>
            <select name="status" id="blStatus" class="form-select" required>
              <option value="Terjadwal">Terjadwal</option>
              <option value="Sudah Dilaksanakan">Sudah Dilaksanakan</option>
            </select>
          </div>
          <div class="col-md-12">
            <label class="form-label text-uppercase fw-bold text-muted small" style="font-size:0.72rem;">Catatan Tambahan</label>
            <textarea name="catatan" id="blCatatan" class="form-control" rows="2" placeholder="Catatan serah terima wewenang..."></textarea>
          </div>
        </div>
      </div>

      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary fw-bold px-4">Simpan Jadwal</button>
      </div>
    </form>
  </div>
</div>

<!-- HIDDEN FORMS FOR DELETION -->
<form id="deleteFormSertifikasi" method="post" action="'.e(module_url('sdm_kepatuhan.php')).'" style="display:none;">
  <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
  <input type="hidden" name="action" value="delete_sertifikasi">
  <input type="hidden" name="id" id="deleteSertifikasiId" value="0">
</form>

<form id="deleteFormPelatihan" method="post" action="'.e(module_url('sdm_kepatuhan.php')).'" style="display:none;">
  <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
  <input type="hidden" name="action" value="delete_pelatihan">
  <input type="hidden" name="id" id="deletePelatihanId" value="0">
  <input type="hidden" name="tahun" value="'.$tahunAktif.'">
</form>

<form id="deleteFormBlockLeave" method="post" action="'.e(module_url('sdm_kepatuhan.php')).'" style="display:none;">
  <input type="hidden" name="_csrf" value="'.e(csrf_token()).'">
  <input type="hidden" name="action" value="delete_block_leave">
  <input type="hidden" name="id" id="deleteBlockLeaveId" value="0">
  <input type="hidden" name="tahun" value="'.$tahunAktif.'">
</form>
';

$script = '
<script>
function openSertifikasiModal() {
  document.getElementById("modalSertifikasiTitle").innerHTML = \'<i class="bi bi-award text-warning"></i> Tambah Sertifikasi Profesi OJK / LSP\';
  document.getElementById("sertifikasiId").value = "0";
  document.getElementById("sertifNama").value = "";
  document.getElementById("sertifNip").value = "";
  document.getElementById("sertifJabatan").value = "";
  document.getElementById("sertifBidang").value = "";
  document.getElementById("sertifLembaga").value = "LSP Certif";
  document.getElementById("sertifNomor").value = "";
  document.getElementById("sertifTglTerbit").value = "";
  document.getElementById("sertifTglExp").value = "";
  document.getElementById("sertifCatatan").value = "";
  new bootstrap.Modal(document.getElementById("modalSertifikasi")).show();
}

function editSertifikasi(row) {
  document.getElementById("modalSertifikasiTitle").innerHTML = \'<i class="bi bi-pencil-square text-warning"></i> Edit Sertifikasi: \' + row.nama_pegawai;
  document.getElementById("sertifikasiId").value = row.id;
  document.getElementById("sertifNama").value = row.nama_pegawai;
  document.getElementById("sertifNip").value = row.nip || "";
  document.getElementById("sertifJabatan").value = row.jabatan;
  document.getElementById("sertifCabang").value = row.cabang_nama;
  document.getElementById("sertifBidang").value = row.bidang_sertifikasi;
  document.getElementById("sertifLembaga").value = row.lembaga_penerbit;
  document.getElementById("sertifNomor").value = row.nomor_sertifikat;
  document.getElementById("sertifTglTerbit").value = row.tanggal_terbit;
  document.getElementById("sertifTglExp").value = row.tanggal_kadaluarsa;
  document.getElementById("sertifCatatan").value = row.catatan || "";
  new bootstrap.Modal(document.getElementById("modalSertifikasi")).show();
}

function confirmDeleteSertifikasi(id) {
  if (confirm("Apakah Anda yakin ingin menghapus catatan sertifikasi ini?")) {
    document.getElementById("deleteSertifikasiId").value = id;
    document.getElementById("deleteFormSertifikasi").submit();
  }
}

function openPelatihanModal() {
  document.getElementById("modalPelatihanTitle").innerHTML = \'<i class="bi bi-journal-check text-warning"></i> Catat Kegiatan Pelatihan SDM\';
  document.getElementById("pelatihanId").value = "0";
  document.getElementById("pelatihanJudul").value = "";
  document.getElementById("pelatihanJenis").value = "APU PPT Wajib";
  document.getElementById("pelatihanPenyelenggara").value = "";
  document.getElementById("pelatihanTglMulai").value = "";
  document.getElementById("pelatihanTglSelesai").value = "";
  document.getElementById("pelatihanPeserta").value = "1";
  document.getElementById("pelatihanBiaya").value = "";
  document.getElementById("pelatihanDaftarPeserta").value = "";
  document.getElementById("pelatihanCatatan").value = "";
  new bootstrap.Modal(document.getElementById("modalPelatihan")).show();
}

function editPelatihan(row) {
  document.getElementById("modalPelatihanTitle").innerHTML = \'<i class="bi bi-pencil-square text-warning"></i> Edit Pelatihan\';
  document.getElementById("pelatihanId").value = row.id;
  document.getElementById("pelatihanJudul").value = row.judul_pelatihan;
  document.getElementById("pelatihanJenis").value = row.jenis_pelatihan;
  document.getElementById("pelatihanPenyelenggara").value = row.penyelenggara;
  document.getElementById("pelatihanTglMulai").value = row.tanggal_mulai;
  document.getElementById("pelatihanTglSelesai").value = row.tanggal_selesai;
  document.getElementById("pelatihanPeserta").value = row.jumlah_peserta;
  document.getElementById("pelatihanBiaya").value = row.total_biaya;
  document.getElementById("pelatihanDaftarPeserta").value = row.daftar_peserta || "";
  document.getElementById("pelatihanCatatan").value = row.catatan || "";
  new bootstrap.Modal(document.getElementById("modalPelatihan")).show();
}

function confirmDeletePelatihan(id) {
  if (confirm("Hapus catatan pelatihan ini?")) {
    document.getElementById("deletePelatihanId").value = id;
    document.getElementById("deleteFormPelatihan").submit();
  }
}

function openPaguModal(tahun, pagu, target) {
  document.getElementById("paguTahun").value = tahun;
  document.getElementById("paguBiayaInput").value = pagu;
  document.getElementById("paguTargetInput").value = target;
  new bootstrap.Modal(document.getElementById("modalPagu")).show();
}

function openBlockLeaveModal() {
  document.getElementById("modalBlockLeaveTitle").innerHTML = \'<i class="bi bi-calendar-check text-warning"></i> Jadwalkan Mandatory Block Leave\';
  document.getElementById("blId").value = "0";
  document.getElementById("blNama").value = "";
  document.getElementById("blNip").value = "";
  document.getElementById("blJabatan").value = "";
  document.getElementById("blFungsi").value = "Ya";
  document.getElementById("blTglMulai").value = "";
  document.getElementById("blTglSelesai").value = "";
  document.getElementById("blPengganti").value = "";
  document.getElementById("blStatus").value = "Terjadwal";
  document.getElementById("blCatatan").value = "";
  new bootstrap.Modal(document.getElementById("modalBlockLeave")).show();
}

function editBlockLeave(row) {
  document.getElementById("modalBlockLeaveTitle").innerHTML = \'<i class="bi bi-pencil-square text-warning"></i> Edit Jadwal Block Leave\';
  document.getElementById("blId").value = row.id;
  document.getElementById("blNama").value = row.nama_pegawai;
  document.getElementById("blNip").value = row.nip || "";
  document.getElementById("blJabatan").value = row.jabatan;
  document.getElementById("blCabang").value = row.cabang_nama;
  document.getElementById("blFungsi").value = row.fungsi_rawan;
  document.getElementById("blTglMulai").value = row.tanggal_mulai;
  document.getElementById("blTglSelesai").value = row.tanggal_selesai;
  document.getElementById("blPengganti").value = row.petugas_pengganti;
  document.getElementById("blStatus").value = row.status;
  document.getElementById("blCatatan").value = row.catatan || "";
  new bootstrap.Modal(document.getElementById("modalBlockLeave")).show();
}

function confirmDeleteBlockLeave(id) {
  if (confirm("Hapus jadwal block leave ini?")) {
    document.getElementById("deleteBlockLeaveId").value = id;
    document.getElementById("deleteFormBlockLeave").submit();
  }
}
</script>
';

render_page('Kepatuhan SDM & Tata Kelola OJK - Bank Mitra', $body, $head, $script);
