<?php
require __DIR__ . '/bootstrap.php';
require_login();

// Pastikan skema penyimpanan siap
ensure_audit_log_storage();

// Ambil parameter filter
$module = trim((string)($_GET['module'] ?? 'ALL'));
$action = trim((string)($_GET['action'] ?? 'ALL'));
$search = trim((string)($_GET['search'] ?? ''));
$month = (int)($_GET['bulan'] ?? 0);
$year = (int)($_GET['tahun'] ?? 0);

// Pagination
$page = max(1, (int)($_GET['p'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$filters = [
    'module' => $module,
    'action' => $action,
    'search' => $search,
    'bulan' => $month,
    'tahun' => $year
];

$auditData = get_audit_logs($filters, $limit, $offset);
$logs = $auditData['rows'];
$totalLogs = $auditData['total'];
$totalPages = ceil($totalLogs / $limit);

$stats = get_audit_stats();

// Buat baris tabel HTML
$rowsHtml = '';
$modalDataScript = [];

if (!empty($logs)) {
    $no = $offset;
    foreach ($logs as $log) {
        $no++;
        $logId = (int)($log['id'] ?? 0);
        $createdAt = (string)($log['created_at'] ?? '');
        $ts = strtotime($createdAt);
        $timeFormatted = $ts ? date('d-m-Y H:i:s', $ts) : $createdAt;

        $userName = (string)($log['user_name'] ?? 'System');
        $userRole = strtolower(trim((string)($log['user_role'] ?? 'system')));
        $ip = (string)($log['ip_address'] ?? '-');
        $act = strtoupper(trim((string)($log['action'] ?? '-')));
        $mod = strtoupper(trim((string)($log['module'] ?? '-')));
        $targetLabel = (string)($log['target_label'] ?? '-');
        $details = (string)($log['details'] ?? '-');
        $userAgent = (string)($log['user_agent'] ?? '-');

        // Badge Aksi
        $actBadgeClass = 'bg-secondary';
        $actIcon = 'bi-circle';
        if ($act === 'CREATE') {
            $actBadgeClass = 'text-success bg-success bg-opacity-10 border border-success border-opacity-25';
            $actIcon = 'bi-plus-circle-fill';
        } elseif ($act === 'UPDATE') {
            $actBadgeClass = 'text-primary bg-primary bg-opacity-10 border border-primary border-opacity-25';
            $actIcon = 'bi-pencil-square';
        } elseif ($act === 'DELETE') {
            $actBadgeClass = 'text-danger bg-danger bg-opacity-10 border border-danger border-opacity-25';
            $actIcon = 'bi-trash-fill';
        } elseif ($act === 'LOGIN_SUCCESS') {
            $actBadgeClass = 'text-info bg-info bg-opacity-10 border border-info border-opacity-25';
            $actIcon = 'bi-box-arrow-in-right';
        } elseif ($act === 'LOGIN_FAILED') {
            $actBadgeClass = 'text-danger bg-danger bg-opacity-10 border border-danger border-opacity-25';
            $actIcon = 'bi-shield-slash-fill';
        } elseif ($act === 'LOGOUT') {
            $actBadgeClass = 'text-secondary bg-secondary bg-opacity-10 border border-secondary border-opacity-25';
            $actIcon = 'bi-box-arrow-right';
        }

        // Badge Modul
        $modBadgeColor = '#475467';
        if ($mod === 'ASET') $modBadgeColor = '#026AA2';
        elseif ($mod === 'PENGGUNA') $modBadgeColor = '#7A5AF8';
        elseif ($mod === 'CABANG' || $mod === 'DIVISI') $modBadgeColor = '#0E7090';
        elseif ($mod === 'KEAMANAN') $modBadgeColor = '#B54708';
        elseif ($mod === 'MAINTENANCE') $modBadgeColor = '#16803C';

        // Format Rincian Perubahan (Highlight tanda panah ➔)
        $detailsFormatted = e($details);
        $detailsFormatted = str_replace('➔', '<span class="text-primary fw-bold mx-1">➔</span>', $detailsFormatted);
        $detailsFormatted = str_replace(' | ', '<span class="text-muted opacity-50 mx-1">|</span>', $detailsFormatted);

        // Role badge
        $roleClass = ($userRole === 'admin') ? 'bg-danger text-white' : (($userRole === 'auditor') ? 'bg-warning text-dark' : 'bg-primary text-white');

        $rowsHtml .= '
        <tr>
          <td class="text-muted small text-center" style="width: 50px;">'.$no.'</td>
          <td class="text-nowrap small font-monospace">
            <div class="fw-bold text-dark"><i class="bi bi-clock-history me-1 text-muted"></i>'.e($timeFormatted).'</div>
          </td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <span class="badge rounded-pill px-2 py-0 '.$roleClass.'" style="font-size: 0.65rem; text-transform: uppercase;">'.e($userRole).'</span>
              <span class="fw-bold text-dark small">'.e($userName).'</span>
            </div>
            <div class="small text-muted font-monospace" style="font-size: 0.72rem;">
              <i class="bi bi-broadcast me-1"></i>'.e($ip).'
            </div>
          </td>
          <td>
            <span class="badge rounded-pill px-2 py-1" style="background-color: '.$modBadgeColor.'15; color: '.$modBadgeColor.'; border: 1px solid '.$modBadgeColor.'30; font-size: 0.72rem; font-weight: 600;">
              '.e($mod).'
            </span>
            <div class="small fw-semibold text-dark mt-1 text-truncate" style="max-width: 220px;" title="'.e($targetLabel).'">
              '.e($targetLabel).'
            </div>
          </td>
          <td class="text-nowrap">
            <span class="badge px-2 py-1 rounded-2 '.$actBadgeClass.'" style="font-size: 0.72rem; font-weight: 600;">
              <i class="bi '.$actIcon.' me-1"></i>'.e($act).'
            </span>
          </td>
          <td class="small text-secondary">
            <div style="max-height: 52px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
              '.$detailsFormatted.'
            </div>
          </td>
          <td class="text-end text-nowrap">
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" onclick="showAuditDetail('.htmlspecialchars(json_encode([
                'id' => $logId,
                'time' => $timeFormatted,
                'user' => $userName,
                'role' => $userRole,
                'ip' => $ip,
                'module' => $mod,
                'action' => $act,
                'target' => $targetLabel,
                'details' => $details,
                'user_agent' => $userAgent
            ]), ENT_QUOTES, 'UTF-8').')">
              <i class="bi bi-eye me-1"></i>Detail
            </button>
          </td>
        </tr>';
    }
} else {
    $rowsHtml = '
    <tr>
      <td colspan="7" class="text-center py-5 text-muted">
        <i class="bi bi-journal-x fs-1 d-block mb-2 opacity-50"></i>
        Tidak ada catatan log aktivitas yang sesuai dengan kriteria filter saat ini.
      </td>
    </tr>';
}

// Build Pagination Links
$paginationHtml = '';
if ($totalPages > 1) {
    $queryParams = $_GET;
    $paginationHtml .= '<nav><ul class="pagination pagination-sm mb-0 justify-content-center">';
    
    // Prev
    if ($page > 1) {
        $queryParams['p'] = $page - 1;
        $paginationHtml .= '<li class="page-item"><a class="page-link" href="?'.http_build_query($queryParams).'">‹ Sebelumnya</a></li>';
    } else {
        $paginationHtml .= '<li class="page-item disabled"><span class="page-link">‹ Sebelumnya</span></li>';
    }

    $startP = max(1, $page - 2);
    $endP = min($totalPages, $page + 2);
    for ($i = $startP; $i <= $endP; $i++) {
        $queryParams['p'] = $i;
        $activeClass = ($i === $page) ? 'active' : '';
        $paginationHtml .= '<li class="page-item '.$activeClass.'"><a class="page-link" href="?'.http_build_query($queryParams).'">'.$i.'</a></li>';
    }

    // Next
    if ($page < $totalPages) {
        $queryParams['p'] = $page + 1;
        $paginationHtml .= '<li class="page-item"><a class="page-link" href="?'.http_build_query($queryParams).'">Berikutnya ›</a></li>';
    } else {
        $paginationHtml .= '<li class="page-item disabled"><span class="page-link">Berikutnya ›</span></li>';
    }

    $paginationHtml .= '</ul></nav>';
}

$exportUrl = module_url('export_audit_trail_csv.php', $filters);

$body = '
<div class="row g-3 mb-4">
  <!-- KPI: Total Log -->
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm p-3 h-100 bg-white" style="border-left: 4px solid #124E96 !important; border-radius: 8px;">
      <div class="d-flex align-items-center justify-content-between mb-1">
        <span class="text-uppercase small fw-bold text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;">TOTAL AKTIVITAS</span>
        <div class="p-2 rounded bg-primary bg-opacity-10 text-primary"><i class="bi bi-journal-text fs-5"></i></div>
      </div>
      <div class="h3 fw-bold mb-0 text-dark">'.number_format($stats['total']).'</div>
      <div class="small text-muted" style="font-size: 0.75rem;">Semua log tercatat</div>
    </div>
  </div>

  <!-- KPI: Hari Ini -->
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm p-3 h-100 bg-white" style="border-left: 4px solid #16803C !important; border-radius: 8px;">
      <div class="d-flex align-items-center justify-content-between mb-1">
        <span class="text-uppercase small fw-bold text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;">HARI INI</span>
        <div class="p-2 rounded bg-success bg-opacity-10 text-success"><i class="bi bi-calendar-check fs-5"></i></div>
      </div>
      <div class="h3 fw-bold mb-0 text-success">'.number_format($stats['today']).'</div>
      <div class="small text-muted" style="font-size: 0.75rem;">Aktivitas tanggal '.date('d/m/Y').'</div>
    </div>
  </div>

  <!-- KPI: Perubahan Aset -->
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm p-3 h-100 bg-white" style="border-left: 4px solid #2E7CF6 !important; border-radius: 8px;">
      <div class="d-flex align-items-center justify-content-between mb-1">
        <span class="text-uppercase small fw-bold text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;">UPDATE ASET & IP</span>
        <div class="p-2 rounded bg-info bg-opacity-10 text-info"><i class="bi bi-pencil-square fs-5"></i></div>
      </div>
      <div class="h3 fw-bold mb-0 text-primary">'.number_format($stats['asset_updates']).'</div>
      <div class="small text-muted" style="font-size: 0.75rem;">Perubahan IP/Pengguna/Divisi</div>
    </div>
  </div>

  <!-- KPI: Hapus Unit & Keamanan -->
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm p-3 h-100 bg-white" style="border-left: 4px solid #B42318 !important; border-radius: 8px;">
      <div class="d-flex align-items-center justify-content-between mb-1">
        <span class="text-uppercase small fw-bold text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;">PENGHAPUSAN UNIT</span>
        <div class="p-2 rounded bg-danger bg-opacity-10 text-danger"><i class="bi bi-trash3-fill fs-5"></i></div>
      </div>
      <div class="h3 fw-bold mb-0 text-danger">'.number_format($stats['asset_deletes']).'</div>
      <div class="small text-muted" style="font-size: 0.75rem;">Unit dihapus dari inventaris</div>
    </div>
  </div>
</div>

<!-- FILTER & SEARCH PANEL -->
<div class="card border-0 shadow-sm mb-4 bg-white" style="border-radius: 8px;">
  <div class="card-body p-3 p-md-4">
    <form method="get" class="row g-2 align-items-end">
      <!-- Filter Modul -->
      <div class="col-6 col-md-2">
        <label class="form-label small fw-bold text-muted mb-1" style="font-size: 0.72rem;">MODUL</label>
        <select name="module" class="form-select form-select-sm">
          <option value="ALL" '.($module === 'ALL' ? 'selected' : '').'>Semua Modul</option>
          <option value="ASET" '.($module === 'ASET' ? 'selected' : '').'>💻 Aset Komputer</option>
          <option value="PENGGUNA" '.($module === 'PENGGUNA' ? 'selected' : '').'>👥 Pengguna / Akun</option>
          <option value="CABANG" '.($module === 'CABANG' ? 'selected' : '').'>🏢 Kantor Cabang</option>
          <option value="DIVISI" '.($module === 'DIVISI' ? 'selected' : '').'>🗂️ Divisi Kerja</option>
          <option value="KEAMANAN" '.($module === 'KEAMANAN' ? 'selected' : '').'>🔐 Login & Keamanan</option>
        </select>
      </div>

      <!-- Filter Aksi -->
      <div class="col-6 col-md-2">
        <label class="form-label small fw-bold text-muted mb-1" style="font-size: 0.72rem;">TIPE AKSI</label>
        <select name="action" class="form-select form-select-sm">
          <option value="ALL" '.($action === 'ALL' ? 'selected' : '').'>Semua Aksi</option>
          <option value="CREATE" '.($action === 'CREATE' ? 'selected' : '').'>➕ CREATE (Tambah)</option>
          <option value="UPDATE" '.($action === 'UPDATE' ? 'selected' : '').'>✏️ UPDATE (Ubah/Edit)</option>
          <option value="DELETE" '.($action === 'DELETE' ? 'selected' : '').'>🗑️ DELETE (Hapus)</option>
          <option value="LOGIN_SUCCESS" '.($action === 'LOGIN_SUCCESS' ? 'selected' : '').'>🔑 LOGIN Sukses</option>
          <option value="LOGIN_FAILED" '.($action === 'LOGIN_FAILED' ? 'selected' : '').'>⚠️ LOGIN Gagal</option>
          <option value="LOGOUT" '.($action === 'LOGOUT' ? 'selected' : '').'>🚪 LOGOUT</option>
        </select>
      </div>

      <!-- Filter Bulan -->
      <div class="col-4 col-md-2">
        <label class="form-label small fw-bold text-muted mb-1" style="font-size: 0.72rem;">BULAN</label>
        <select name="bulan" class="form-select form-select-sm">
          <option value="0">Semua Bulan</option>';
          for ($m = 1; $m <= 12; $m++) {
              $mSelected = ($month === $m) ? 'selected' : '';
              $mName = date('F', mktime(0, 0, 0, $m, 1));
              $body .= '<option value="'.$m.'" '.$mSelected.'>'.$mName.'</option>';
          }
$body .= '
        </select>
      </div>

      <!-- Filter Tahun -->
      <div class="col-4 col-md-2">
        <label class="form-label small fw-bold text-muted mb-1" style="font-size: 0.72rem;">TAHUN</label>
        <select name="tahun" class="form-select form-select-sm">
          <option value="0">Semua Tahun</option>';
          $currY = (int)date('Y');
          for ($y = $currY - 2; $y <= $currY + 1; $y++) {
              $ySelected = ($year === $y) ? 'selected' : '';
              $body .= '<option value="'.$y.'" '.$ySelected.'>'.$y.'</option>';
          }
$body .= '
        </select>
      </div>

      <!-- Pencarian Kata Kunci -->
      <div class="col-4 col-md-2">
        <label class="form-label small fw-bold text-muted mb-1" style="font-size: 0.72rem;">PENCARIAN</label>
        <input type="text" name="search" class="form-control form-select-sm" placeholder="User, IP, aset..." value="'.e($search).'">
      </div>

      <!-- Tombol Aksi -->
      <div class="col-12 col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary flex-grow-1" style="background-color: var(--blue-corporate); border-color: var(--blue-corporate);">
          <i class="bi bi-funnel-fill me-1"></i> Filter
        </button>
        <a href="'.e(module_url('audit_trail.php')).'" class="btn btn-sm btn-outline-secondary" title="Reset Filter">
          <i class="bi bi-arrow-counterclockwise"></i>
        </a>
      </div>
    </form>
  </div>
</div>

<!-- AUDIT TRAIL TABLE -->
<div class="card border-0 shadow-sm bg-white" style="border-radius: 8px;">
  <div class="card-header bg-white py-3 px-4 d-flex align-items-center justify-content-between border-bottom">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-shield-lock-fill text-primary fs-5"></i>
      <div>
        <h6 class="fw-bold mb-0 text-dark">Daftar Log Aktivitas Sistem</h6>
        <div class="text-muted small" style="font-size: 0.72rem;">Memenuhi regulasi POJK & ISO 27001 · Menampilkan '.$totalLogs.' entri aktivitas</div>
      </div>
    </div>
    <div>
      <a href="'.e($exportUrl).'" class="btn btn-sm btn-outline-success" target="_blank">
        <i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> Ekspor CSV / Excel
      </a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" style="font-size: 0.82rem;">
      <thead class="table-light">
        <tr>
          <th class="text-center" style="width: 50px;">No</th>
          <th>Waktu Kejadian</th>
          <th>Petugas & IP</th>
          <th>Modul & Target</th>
          <th>Aksi</th>
          <th>Rincian Perubahan</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        '.$rowsHtml.'
      </tbody>
    </table>
  </div>

  <div class="card-footer bg-white py-3 px-4 d-flex align-items-center justify-content-between border-top">
    <div class="small text-muted">
      Menampilkan <strong>'.count($logs).'</strong> dari <strong>'.$totalLogs.'</strong> entri
    </div>
    '.$paginationHtml.'
  </div>
</div>

<!-- DETAIL MODAL -->
<div class="modal fade" id="auditDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-navy-dark text-white border-0 py-3 px-4" style="background-color: var(--navy-primary);">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-shield-check fs-5 text-info"></i>
          <h6 class="modal-title fw-bold text-white mb-0">Rincian Log Aktivitas Sistem</h6>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4 bg-light">
        <div class="bg-white p-3 rounded-3 border mb-3">
          <div class="row g-2 small">
            <div class="col-sm-4 text-muted">Waktu Pencatatan:</div>
            <div class="col-sm-8 fw-bold text-dark font-monospace" id="modalTime">-</div>

            <div class="col-sm-4 text-muted">Petugas Pelaksana:</div>
            <div class="col-sm-8" id="modalUser">-</div>

            <div class="col-sm-4 text-muted">Alamat IP & Perangkat:</div>
            <div class="col-sm-8 font-monospace text-secondary" id="modalIp">-</div>

            <div class="col-sm-4 text-muted">Modul & Target:</div>
            <div class="col-sm-8 fw-bold text-primary" id="modalTarget">-</div>

            <div class="col-sm-4 text-muted">Tipe Operasi (Aksi):</div>
            <div class="col-sm-8" id="modalAction">-</div>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label small fw-bold text-muted text-uppercase mb-1">Rincian Perubahan / Diff</label>
          <div class="p-3 bg-white rounded-3 border font-monospace small text-dark" id="modalDetails" style="white-space: pre-wrap; word-break: break-word; line-height: 1.6;">
            -
          </div>
        </div>

        <div>
          <label class="form-label small fw-bold text-muted text-uppercase mb-1">Browser User Agent</label>
          <div class="p-2 bg-white rounded-2 border text-muted small font-monospace" id="modalUserAgent" style="font-size: 0.72rem; word-break: break-all;">
            -
          </div>
        </div>
      </div>
      <div class="modal-footer bg-white border-top py-2 px-4">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
function showAuditDetail(data) {
  document.getElementById("modalTime").textContent = data.time || "-";
  document.getElementById("modalUser").innerHTML = "<span class=\"badge bg-primary me-1\">" + (data.role || "user") + "</span> <strong>" + (data.user || "System") + "</strong>";
  document.getElementById("modalIp").textContent = (data.ip || "-");
  document.getElementById("modalTarget").textContent = "[" + (data.module || "-") + "] " + (data.target || "-");
  document.getElementById("modalAction").innerHTML = "<span class=\"badge bg-secondary\">" + (data.action || "-") + "</span>";
  document.getElementById("modalDetails").textContent = data.details || "-";
  document.getElementById("modalUserAgent").textContent = data.user_agent || "-";

  var modal = new bootstrap.Modal(document.getElementById("auditDetailModal"));
  modal.show();
}
</script>
';

render_page('Log Aktivitas & Audit Trail', $body, 'audit_trail.php');
