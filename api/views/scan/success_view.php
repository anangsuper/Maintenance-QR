<?php
if ($successData) {
    $statusBadge = ($successData['status'] === 'Temuan' || $successData['status'] === 'Perlu Perbaikan')
        ? '<span class="badge bg-danger fs-6 px-3 py-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> Perlu Perbaikan</span>'
        : ($successData['status'] === 'Proses'
            ? '<span class="badge bg-warning text-dark fs-6 px-3 py-2"><i class="bi bi-hourglass-split me-1"></i> Sedang Proses</span>'
            : '<span class="badge bg-success fs-6 px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i> Selesai</span>');

    $bioSuccessHtml = '';
    if (!empty($successData['biometric_verified'])) {
        $photoThumb = !empty($successData['biometric_photo'])
            ? '<img src="'.e($successData['biometric_photo']).'" class="rounded-circle border border-2 border-success me-2" style="width: 52px; height: 52px; object-fit: cover;">'
            : '';
        $bioSuccessHtml = '
        <div class="alert alert-success py-3 px-3 d-flex align-items-center mb-3 text-start border-0 bg-success bg-opacity-10 shadow-sm">
          '.$photoThumb.'
          <div>
            <div class="fw-bold text-success"><i class="bi bi-shield-check-fill me-1"></i> Identitas Terverifikasi Biometrik Wajah!</div>
            <div class="small text-muted">Tingkat Kecocokan: <strong>'.round($successData['biometric_confidence']).'%</strong> · Liveness Detection Lolos · Bukti audit tersimpan.</div>
          </div>
        </div>';
    }

    $ipStr = $asset['ip_address'] ?? $asset['ip'] ?? '-';
    $prtStr = $asset['printer'] ?? '-';

    $body = '
    <div class="row justify-content-center">
      <div class="col-md-8 col-lg-6">
        <div class="card p-4 p-md-5 border-0 shadow-sm text-center">
          <div class="mb-3">
            <span class="d-inline-flex p-3 rounded-circle bg-success bg-opacity-10 text-success fs-1">
              <i class="bi bi-check2-circle"></i>
            </span>
          </div>
          <h3 class="fw-bold text-success mb-1">Maintenance Berhasil Disimpan!</h3>
          <p class="text-secondary small mb-3">Hasil checklist pemeliharaan telah dicatat ke dalam Kartu Kontrol & Database.</p>

          '.$bioSuccessHtml.'

          <div class="bg-light p-3 rounded-3 text-start mb-4 border">
            <div class="row g-2 small">
              <div class="col-5 text-muted">Perangkat:</div>
              <div class="col-7 fw-bold text-dark">'.e(asset_title($asset)).'</div>
              <div class="col-5 text-muted">Kode Inventaris:</div>
              <div class="col-7 text-primary fw-bold">'.e($asset['kode_inventaris'] ?? '-').'</div>
              <div class="col-5 text-muted">Alamat IP:</div>
              <div class="col-7 fw-bold text-success font-monospace">'.e($ipStr).'</div>
              <div class="col-5 text-muted">Printer:</div>
              <div class="col-7 fw-semibold text-dark">'.e($prtStr).'</div>
              <div class="col-5 text-muted">Tanggal:</div>
              <div class="col-7 fw-semibold">'.e(format_id_date($successData['date'])).'</div>
              <div class="col-5 text-muted">Petugas/Teknisi:</div>
              <div class="col-7 fw-bold text-dark">'.e($successData['technician']).'</div>
              <div class="col-5 text-muted">Status:</div>
              <div class="col-7">'.$statusBadge.'</div>
            </div>
          </div>

          <div class="d-grid gap-2">
            <a class="btn btn-primary fw-bold py-3 shadow-sm" href="scan.php?t='.urlencode($token).'">
              <i class="bi bi-card-checklist me-1"></i> Lihat Kartu Kontrol Perangkat
            </a>
            <a class="btn btn-dark fw-bold py-2 shadow-sm" href="invoice.php?id='.((int)$successData['log_id']).'">
              <i class="fa-solid fa-receipt me-1 text-warning"></i> Lihat Struk Mesin (Invoice)
            </a>
            <a class="btn btn-outline-secondary py-2" href="maintenance_detail.php?id='.((int)$successData['log_id']).'">
              <i class="bi bi-file-earmark-text me-1"></i> Rincian Audit Lengkap
            </a>
          </div>
          <div class="text-center mt-3 text-muted small">
            <span class="spinner-border spinner-border-sm me-1 text-primary"></span> Otomatis membuka Kartu Kontrol dalam 3 detik...
          </div>
        </div>
      </div>
    </div>
    <script>
    setTimeout(function() {
      window.location.href = "scan.php?t=" + encodeURIComponent("'.e($token).'");
    }, 2800);
    </script>';

    render_page('Maintenance Berhasil Disimpan', $body, '', '', false);
    exit;
}

if ($successTindakLanjut) {
    $statusBadge = ($successTindakLanjut['status'] === 'Selesai')
        ? '<span class="badge bg-success fs-6 px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i> Selesai (Normal Kembali)</span>'
        : '<span class="badge bg-warning text-dark fs-6 px-3 py-2"><i class="bi bi-hourglass-split me-1"></i> Sedang Proses</span>';

    $bioSuccessHtml = '';
    if (!empty($successTindakLanjut['biometric_verified'])) {
        $photoThumb = !empty($successTindakLanjut['biometric_photo'])
            ? '<img src="'.e($successTindakLanjut['biometric_photo']).'" class="rounded-circle border border-2 border-success me-2" style="width: 52px; height: 52px; object-fit: cover;">'
            : '';
        $bioSuccessHtml = '
        <div class="alert alert-success py-3 px-3 d-flex align-items-center mb-3 text-start border-0 bg-success bg-opacity-10 shadow-sm">
          '.$photoThumb.'
          <div>
            <div class="fw-bold text-success"><i class="bi bi-shield-check-fill me-1"></i> Identitas Terverifikasi Biometrik Wajah!</div>
            <div class="small text-muted">Tingkat Kecocokan: <strong>'.round($successTindakLanjut['biometric_confidence']).'%</strong> · Liveness Detection Lolos · Bukti audit tersimpan.</div>
          </div>
        </div>';
    }

    $body = '
    <div class="row justify-content-center">
      <div class="col-md-8 col-lg-6">
        <div class="card p-4 p-md-5 border-0 shadow-sm text-center">
          <div class="mb-3">
            <span class="d-inline-flex p-3 rounded-circle bg-success bg-opacity-10 text-success fs-1">
              <i class="bi bi-tools"></i>
            </span>
          </div>
          <h3 class="fw-bold text-success mb-1">Tindak Lanjut Berhasil Disimpan!</h3>
          <p class="text-secondary small mb-3">Tindakan perbaikan telah dicatat. Status temuan di Dashboard telah diperbarui.</p>

          '.$bioSuccessHtml.'

          <div class="bg-light p-3 rounded-3 text-start mb-4 border">
            <div class="row g-2 small">
              <div class="col-5 text-muted">Perangkat:</div>
              <div class="col-7 fw-bold text-dark">'.e(asset_title($asset)).'</div>
              <div class="col-5 text-muted">Kode Inventaris:</div>
              <div class="col-7 text-primary fw-bold font-monospace">'.e($asset['kode_inventaris'] ?? '-').'</div>
              <div class="col-5 text-muted">Petugas Teknisi:</div>
              <div class="col-7 fw-bold text-dark">'.e($successTindakLanjut['technician']).'</div>
              <div class="col-5 text-muted">Waktu:</div>
              <div class="col-7 fw-semibold">'.e(format_id_date($successTindakLanjut['date'])).' '.$successTindakLanjut['time'].'</div>
              <div class="col-5 text-muted">Tindakan Perbaikan:</div>
              <div class="col-7 text-dark fw-semibold">'.nl2br(e($successTindakLanjut['action_taken'])).'</div>
              <div class="col-5 text-muted">Status Baru:</div>
              <div class="col-7">'.$statusBadge.'</div>
            </div>
          </div>

          <div class="d-grid gap-2">
            <a class="btn btn-primary fw-bold py-3 shadow-sm" href="scan.php?t='.urlencode($token).'">
              <i class="bi bi-card-checklist me-1"></i> Buka Kartu Kontrol Perangkat
            </a>
            '.(!empty($successTindakLanjut['log_id']) ? '
            <a class="btn btn-outline-secondary py-2" href="maintenance_detail.php?id='.((int)$successTindakLanjut['log_id']).'">
              <i class="bi bi-file-earmark-text me-1"></i> Lihat Rincian Log Audit
            </a>' : '').'
          </div>
          <div class="text-center mt-3 text-muted small">
            <span class="spinner-border spinner-border-sm me-1 text-primary"></span> Membuka Kartu Kontrol dalam 3 detik...
          </div>
        </div>
      </div>
    </div>
    <script>
    setTimeout(function() {
      window.location.href = "scan.php?t=" + encodeURIComponent("'.e($token).'");
    }, 2800);
    </script>';

    render_page('Tindak Lanjut Berhasil Disimpan', $body, '', '', false);
    exit;
}
