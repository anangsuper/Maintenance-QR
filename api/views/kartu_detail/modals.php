    <!-- ===================================================================== -->
    <!-- MODAL EDIT DATA MASTER KARTU INVENTARIS                               -->
    <!-- ===================================================================== -->
    <?php if ($card): ?>
    <div class="modal fade" id="modalEditDataKartu" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4 shadow-2xl overflow-hidden">
                <div class="modal-header bg-dark text-white py-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-pencil-square fs-5 text-primary"></i>
                        <div>
                            <h6 class="modal-title fw-bold mb-0">Ubah Data Master Inventaris</h6>
                            <div class="text-white-50 small" style="font-size: 0.72rem;">Sesuai Standar Data Core Banking BPR Mitratama</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                
                <form method="post" id="editDataKartuForm" action="<?= module_url('kartu_detail.php', ['id' => $card['id']]) ?>">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_card_data">
                    <input type="hidden" name="bio_verified" id="editBioVerified" value="0">
                    <input type="hidden" name="bio_user_id" id="editBioUserId" value="">
                    <input type="hidden" name="bio_user_name" id="editBioUserName" value="">
                    <input type="hidden" name="bio_confidence" id="editBioConfidence" value="">

                    <div class="modal-body p-4 bg-light">
                        <div class="row g-3">
                            
                            <!-- Kantor Bank (Core Banking BPR Mitratama) -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Kantor Bank</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text font-mono fw-bold bg-white text-primary" id="editModalKdCabang" style="min-width: 44px; justify-content: center;"><?= e($kdCabang) ?></span>
                                    <select name="kantor_bank" id="editModalKantorBank" class="form-select form-select-sm font-mono fw-semibold" onchange="document.getElementById('editModalKdCabang').textContent = this.value.substring(0, 2);">
                                        <?php foreach ($masterKantorBank as $code => $kb): ?>
                                            <option value="<?= e($kb['value']) ?>" <?= ($kdCabang === $code || str_starts_with($kantorBankVal, $code)) ? 'selected' : '' ?>>
                                                <?= e($kb['label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-text text-muted" style="font-size: 0.70rem;">01. Kantor Pusat Operasional, 02. Batulicin, 03. Martapura, 04. Tanjung, 05. Handil Bakti.</div>
                            </div>

                            <!-- Kode Inventaris (Read Only) -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Kode Inventaris</label>
                                <input type="text" class="form-control form-control-sm font-mono bg-white fw-bold text-primary" value="<?= e($kodeInventaris) ?>" readonly>
                                <div class="form-text text-muted" style="font-size: 0.70rem;">Format resmi perbankan: <code>00.0.00000</code></div>
                            </div>

                            <!-- Nomor Seri Inventaris -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Nomor Seri Inventaris</label>
                                <input type="text" name="nomor_seri_inventaris" class="form-control form-control-sm font-mono" value="<?= e($nomorSeriInv) ?>" placeholder="Contoh: 01.05.02.220124.001" required>
                                <div class="form-text text-muted" style="font-size: 0.70rem;">Format: [Cabang].[Ruangan].[Golongan].[ddMMyy].[Nomor]</div>
                            </div>

                            <!-- Jenis Inventaris (Gambar 2) -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Jenis Inventaris</label>
                                <select name="jenis_inventaris" class="form-select form-select-sm" required>
                                    <?php foreach ($masterJenis as $k => $val): ?>
                                        <option value="<?= e($val) ?>" <?= ($jenisInvVal === $val || str_starts_with($jenisInvVal, $k . ' :')) ? 'selected' : '' ?>>
                                            <?= e($val) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Golongan (Gambar 3) -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Golongan Inventaris</label>
                                <select name="golongan" class="form-select form-select-sm" required>
                                    <?php foreach ($masterGolongan as $k => $val): ?>
                                        <option value="<?= e($val) ?>" <?= ($golonganVal === $val || str_starts_with($golonganVal, $k . ' :')) ? 'selected' : '' ?>>
                                            <?= e($val) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Ruangan Penempatan (Lokasi Fisik) -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Ruangan Penempatan (Lokasi Fisik)</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-geo-alt-fill text-primary"></i></span>
                                    <input type="text" name="ruangan_custom" id="ruanganInput" class="form-control font-mono" list="listLokasiPresets" value="<?= e($ruanganVal) ?>" placeholder="Contoh: 33 : RUANG KADIV KREDIT LT 4">
                                </div>
                                <datalist id="listLokasiPresets">
                                    <?php foreach ($masterRuangan as $r): ?>
                                        <option value="<?= e(trim(($r['kode_ruangan'] ?? '') . ' : ' . ($r['nama_ruangan'] ?? ''))) ?>"><?= e($r['nama_ruangan']) ?></option>
                                    <?php endforeach; ?>
                                </datalist>
                                <div class="form-text text-muted" style="font-size: 0.70rem;">Pilih dari daftar master ruangan (contoh: <code>33 : RUANG KADIV KREDIT LT 4</code>).</div>
                            </div>

                            <!-- Sumber Perolehan -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Sumber Perolehan</label>
                                <select name="sumber_perolehan" class="form-select form-select-sm">
                                    <?php foreach ($masterSumber as $k => $val): ?>
                                        <option value="<?= e($val) ?>" <?= ($sumberVal === $val || str_starts_with($sumberVal, $k . ' :')) ? 'selected' : '' ?>>
                                            <?= e($val) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Status Inventaris -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Status Inventaris</label>
                                <select name="status_inventaris" class="form-select form-select-sm">
                                    <?php foreach ($masterStatus as $k => $val): ?>
                                        <option value="<?= e($val) ?>" <?= ($statusVal === $val) ? 'selected' : '' ?>>
                                            <?= e($val) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Keterangan / Spesifikasi -->
                            <div class="col-12">
                                <label class="form-label small fw-bold text-secondary">Keterangan / Spesifikasi Barang</label>
                                <textarea name="keterangan" class="form-control form-control-sm" rows="3" placeholder="Rincian merek, tipe, kapasitas..."><?= e($keteranganVal) ?></textarea>
                            </div>

                        </div>
                    </div>

                    <div class="modal-footer bg-white border-top py-2.5 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <?php if (is_logged_in() && (is_admin() || is_hrd())): ?>
                                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold shadow-2xs d-inline-flex align-items-center gap-1.5" onclick="submitEditDataAsAuthorizedStaff()">
                                    <i class="bi bi-shield-check text-success"></i>
                                    <span>Simpan via Sesi <?= e(is_admin() ? 'Admin' : 'SDM & Umum') ?></span>
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-primary btn-sm rounded-pill px-3.5 fw-bold shadow-xs d-inline-flex align-items-center gap-1.5" onclick="startFaceAuthForEditData()">
                                <i class="bi bi-person-bounding-box fs-6"></i>
                                <span>Validasi Muka &amp; Simpan</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===================================================================== -->
    <!-- MODAL AMBIL & UPLOAD FOTO BARANG (MUDAH UNTUK ORANG AWAM)              -->
    <!-- ===================================================================== -->
    <div class="modal fade" id="modalUploadFotoBarang" tabindex="-1" aria-labelledby="modalUploadFotoLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
                <div class="modal-header text-white py-3 px-4" style="background-color: #0E5C58;">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-camera-fill fs-5 text-white"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold fs-6 mb-0" id="modalUploadFotoLabel">Ambil &amp; Upload Foto Barang</h5>
                            <small class="text-white-50" style="font-size: 0.74rem;">Paspor Fisik: <?= e($card['nama_barang']) ?> (<?= e($kodeInventaris) ?>)</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <form method="post" enctype="multipart/form-data" id="modalUploadPhotoForm" action="<?= module_url('kartu_detail.php', ['id' => $card['id']]) ?>" onsubmit="return handleModalPhotoSubmit(this)">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="upload_foto">
                    <input type="hidden" name="face_verified" id="modalBioVerified" value="0">
                    <input type="hidden" name="verifier_name" id="modalBioUserName" value="">
                    <input type="hidden" name="verifier_user_id" id="modalBioUserId" value="0">
                    <input type="hidden" name="verifier_confidence" id="modalBioConfidence" value="0">
                    <input type="hidden" name="verifier_photo" id="modalBioPhoto" value="">

                    <div class="modal-body p-3 p-md-4 bg-light">
                        <!-- Petunjuk Ramah & Jelas -->
                        <div class="alert alert-info py-2.5 px-3 small border-0 rounded-3 mb-3 d-flex align-items-center gap-2" style="background-color: #E0F2FE; color: #0369A1;">
                            <i class="bi bi-info-circle-fill fs-5 flex-shrink-0"></i>
                            <span style="font-size: 0.78rem;">Pilih <strong>Buka Kamera</strong> untuk langsung memotret barang dengan kamera HP / Laptop, atau <strong>Pilih dari Galeri</strong> untuk mengunggah foto yang sudah tersimpan.</span>
                        </div>

                        <div class="row g-3">
                            <!-- SLOT FOTO 1 (UTAMA) -->
                            <div class="col-12 col-md-6">
                                <div class="card h-100 border rounded-3 p-3 bg-white shadow-2xs">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge px-2 py-1 text-white font-mono fw-bold rounded" style="background-color: #0E5C58; font-size: 0.68rem;">
                                            FOTO 1 (UTAMA)
                                        </span>
                                        <span class="text-muted small" style="font-size: 0.70rem;">Tampak Depan</span>
                                    </div>

                                    <!-- Preview Box Foto 1 -->
                                    <div class="position-relative rounded-3 border bg-white d-flex align-items-center justify-content-center overflow-hidden mb-2.5" style="min-height: 180px; aspect-ratio: 4 / 5; background-color: #ffffff;">
                                        <img alt="Foto Fisik Barang" id="modalPreviewImg1" src="<?= $photoUrl ? e($photoUrl) : '' ?>" alt="Foto 1" class="<?= $photoUrl ? '' : 'd-none' ?> w-100 h-100 object-fit-contain bg-white">
                                        
                                        <div id="modalEmptyPlace1" class="<?= $photoUrl ? 'd-none' : '' ?> text-center p-3 text-muted">
                                            <i class="bi bi-camera fs-1 d-block text-secondary opacity-50 mb-1"></i>
                                            <span class="small fw-semibold d-block" style="font-size: 0.74rem;">Belum ada foto yang dipilih</span>
                                            <span class="text-secondary" style="font-size: 0.68rem;">Gunakan tombol di bawah untuk mengambil foto</span>
                                        </div>

                                        <span id="modalReadyBadge1" class="position-absolute bottom-0 start-0 end-0 py-1 text-center bg-success text-white small fw-bold d-none" style="font-size: 0.70rem;">
                                            <i class="bi bi-check-circle-fill me-1"></i> Foto 1 Siap Diunggah (<span id="modalSize1">0 KB</span>)
                                        </span>
                                    </div>

                                    <!-- Hidden Inputs: Camera vs File 1 -->
                                    <input type="file" id="modalInputCam1" name="foto_barang_1_cam" class="d-none" accept="image/*" capture="environment" onchange="handleModalPhotoSelected(this, 1)">
                                    <input type="file" id="modalInputFile1" name="foto_barang_1_file" class="d-none" accept="image/*" onchange="handleModalPhotoSelected(this, 1)">

                                    <!-- Dua Tombol Jelas: Kamera Langsung & Galeri -->
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill fw-semibold py-2 d-flex align-items-center justify-content-center gap-1.5" onclick="triggerPhotoPick('cam', 1)">
                                            <i class="bi bi-camera-fill text-primary"></i> <span>Buka Kamera (Foto Langsung)</span>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-light border rounded-pill fw-semibold py-1.5 text-secondary d-flex align-items-center justify-content-center gap-1.5" onclick="triggerPhotoPick('file', 1)">
                                            <i class="bi bi-images text-secondary"></i> <span>Pilih dari Galeri / File</span>
                                        </button>
                                    </div>

                                    <?php if ($photoUrl): ?>
                                        <div class="form-check mt-2 pt-2 border-top">
                                            <input class="form-check-input" type="checkbox" name="hapus_foto_1" value="1" id="modalChkHapus1" onchange="togglePhotoDeleteCheck(1, this.checked)">
                                            <label class="form-check-label text-danger small fw-semibold" for="modalChkHapus1" style="font-size: 0.72rem;">
                                                Hilangkan Foto 1 saat ini (Masuk Sampah)
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- SLOT FOTO 2 (OPSIONAL: DETAIL / NO SERI) -->
                            <div class="col-12 col-md-6">
                                <div class="card h-100 border rounded-3 p-3 bg-white shadow-2xs">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge px-2 py-1 text-white font-mono fw-bold rounded" style="background-color: #0284C7; font-size: 0.68rem;">
                                            FOTO 2 (OPSIONAL)
                                        </span>
                                        <span class="text-muted small" style="font-size: 0.70rem;">Tampak Belakang / No Seri</span>
                                    </div>

                                    <!-- Preview Box Foto 2 -->
                                    <div class="position-relative rounded-3 border bg-white d-flex align-items-center justify-content-center overflow-hidden mb-2.5" style="min-height: 180px; aspect-ratio: 4 / 5; background-color: #ffffff;">
                                        <img alt="Foto Fisik Barang" id="modalPreviewImg2" src="<?= $photoUrl2 ? e($photoUrl2) : '' ?>" alt="Foto 2" class="<?= $photoUrl2 ? '' : 'd-none' ?> w-100 h-100 object-fit-contain bg-white">
                                        
                                        <div id="modalEmptyPlace2" class="<?= $photoUrl2 ? 'd-none' : '' ?> text-center p-3 text-muted">
                                            <i class="bi bi-image fs-1 d-block text-secondary opacity-50 mb-1"></i>
                                            <span class="small fw-semibold d-block" style="font-size: 0.74rem;">Belum ada foto ke-2</span>
                                            <span class="text-secondary" style="font-size: 0.68rem;">Opsional: ambil foto label atau nomor seri</span>
                                        </div>

                                        <span id="modalReadyBadge2" class="position-absolute bottom-0 start-0 end-0 py-1 text-center bg-success text-white small fw-bold d-none" style="font-size: 0.70rem;">
                                            <i class="bi bi-check-circle-fill me-1"></i> Foto 2 Siap Diunggah (<span id="modalSize2">0 KB</span>)
                                        </span>
                                    </div>

                                    <!-- Hidden Inputs: Camera vs File 2 -->
                                    <input type="file" id="modalInputCam2" name="foto_barang_2_cam" class="d-none" accept="image/*" capture="environment" onchange="handleModalPhotoSelected(this, 2)">
                                    <input type="file" id="modalInputFile2" name="foto_barang_2_file" class="d-none" accept="image/*" onchange="handleModalPhotoSelected(this, 2)">

                                    <!-- Dua Tombol Jelas: Kamera Langsung & Galeri 2 -->
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill fw-semibold py-2 d-flex align-items-center justify-content-center gap-1.5" onclick="triggerPhotoPick('cam', 2)">
                                            <i class="bi bi-camera-fill text-primary"></i> <span>Buka Kamera (Foto Ke-2)</span>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-light border rounded-pill fw-semibold py-1.5 text-secondary d-flex align-items-center justify-content-center gap-1.5" onclick="triggerPhotoPick('file', 2)">
                                            <i class="bi bi-images text-secondary"></i> <span>Pilih dari Galeri / File</span>
                                        </button>
                                    </div>

                                    <?php if ($photoUrl2): ?>
                                        <div class="form-check mt-2 pt-2 border-top">
                                            <input class="form-check-input" type="checkbox" name="hapus_foto_2" value="1" id="modalChkHapus2" onchange="togglePhotoDeleteCheck(2, this.checked)">
                                            <label class="form-check-label text-danger small fw-semibold" for="modalChkHapus2" style="font-size: 0.72rem;">
                                                Hilangkan Foto 2 saat ini (Masuk Sampah)
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Otorisasi Biometrik Wajah (Muka) Staf / Teknisi -->
                        <div class="card border rounded-3 p-3 bg-white shadow-2xs mt-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; background-color: #E6F4F1; color: #0E5C58;">
                                    <i class="bi bi-person-bounding-box fs-5"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="fw-bold text-dark small mb-0.5" style="font-size: 0.82rem;">Otorisasi Wajah Staf / Teknisi (Biometrik AI)</div>
                                    <div class="text-secondary" style="font-size: 0.72rem; line-height: 1.35;">
                                        Tidak perlu mengetik nama. Cukup arahkan wajah Anda ke kamera saat menekan tombol simpan untuk memvalidasi identitas petugas secara resmi.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer py-2.5 px-4 bg-light d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-sm btn-light border rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="button" id="btnSubmitModalUpload" class="btn btn-sm rounded-pill fw-bold px-4 text-white shadow-xs d-inline-flex align-items-center gap-2" style="background-color: #0E5C58; border-color: #0E5C58;" onclick="startUploadPhotoWithFaceVerify()">
                            <i class="bi bi-person-bounding-box fs-6"></i>
                            <span>Scan Wajah &amp; Simpan Foto</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL KONFIRMASI HAPUS FOTO (PINDAH KE FOLDER SAMPAH 7 HARI)           -->
    <!-- ===================================================================== -->
    <div class="modal fade" id="modalConfirmDeletePhoto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-danger text-white border-0 py-2.5 px-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-trash3-fill fs-5"></i>
                        <h6 class="modal-title fw-bold mb-0">Hapus Foto Barang</h6>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <form method="post" id="deletePhotoForm" action="<?= module_url('kartu_detail.php', ['id' => $card['id']]) ?>" onsubmit="return handleConfirmDeletePhotoSubmit(this)">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_foto">
                    <input type="hidden" name="delete_target" id="delPhotoTargetInput" value="all">
                    <input type="hidden" name="face_verified" id="delBioVerified" value="0">
                    <input type="hidden" name="verifier_name" id="delBioUserName" value="">
                    <input type="hidden" name="verifier_user_id" id="delBioUserId" value="0">
                    <input type="hidden" name="verifier_confidence" id="delBioConfidence" value="0">
                    <input type="hidden" name="verifier_photo" id="delBioPhoto" value="">

                    <div class="modal-body p-3.5 text-start">
                        <div class="alert alert-success bg-success bg-opacity-10 border-success border-opacity-25 rounded-3 py-2 px-3 mb-3">
                            <div class="fw-bold text-success small"><i class="bi bi-recycle me-1"></i>Aman! Tersimpan di Folder Sampah</div>
                            <div class="text-secondary" style="font-size: 0.72rem;">
                                Foto yang dihapus akan disimpan di <strong>Folder Sampah Foto</strong> selama <strong>7 hari</strong> dan dapat dipulihkan (restore) kapan saja.
                            </div>
                        </div>

                        <label class="form-label small fw-bold text-dark mb-1.5">Pilih Foto yang Ingin Dihapus:</label>
                        <div class="d-flex flex-column gap-1.5 mb-3">
                            <?php if (!empty($photoUrl)): ?>
                                <label class="d-flex align-items-center gap-2 p-2 rounded-3 border bg-light cursor-pointer">
                                    <input type="radio" name="modal_del_target_radio" value="1" class="form-check-input m-0" onchange="setDeleteTargetChoice(this.value)">
                                    <span class="small fw-semibold text-dark">Hapus <strong>Foto 1 (Utama)</strong> Saja</span>
                                </label>
                            <?php endif; ?>
                            <?php if (!empty($photoUrl2)): ?>
                                <label class="d-flex align-items-center gap-2 p-2 rounded-3 border bg-light cursor-pointer">
                                    <input type="radio" name="modal_del_target_radio" value="2" class="form-check-input m-0" onchange="setDeleteTargetChoice(this.value)">
                                    <span class="small fw-semibold text-dark">Hapus <strong>Foto 2 (Detail)</strong> Saja</span>
                                </label>
                            <?php endif; ?>
                            <label class="d-flex align-items-center gap-2 p-2 rounded-3 border bg-danger bg-opacity-10 border-danger border-opacity-25 cursor-pointer">
                                <input type="radio" name="modal_del_target_radio" value="all" class="form-check-input m-0" checked onchange="setDeleteTargetChoice(this.value)">
                                <span class="small fw-bold text-danger">Hapus <strong>Semua Foto</strong> Barang Ini</span>
                            </label>
                        </div>

                        <!-- Otorisasi Biometrik Wajah Hapus -->
                        <div class="card border rounded-3 p-2.5 bg-white shadow-2xs mb-3">
                            <div class="d-flex align-items-center gap-2.5">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background-color: #FEE2E2; color: #DC2626;">
                                    <i class="bi bi-person-bounding-box fs-6"></i>
                                </div>
                                <div class="text-secondary" style="font-size: 0.72rem; line-height: 1.35;">
                                    Wajib otorisasi wajah staf resmi untuk memindahkan foto ke Folder Sampah (tanpa perlu mengetik nama).
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="button" id="btnSubmitDeletePhoto" class="btn btn-danger btn-sm rounded-pill fw-bold py-2 shadow-xs d-flex align-items-center justify-content-center gap-1.5" onclick="startDeletePhotoWithFaceVerify()">
                                <i class="bi bi-person-bounding-box"></i>
                                <span>Scan Wajah &amp; Hapus Foto</span>
                            </button>
                            <button type="button" class="btn btn-light btn-xs py-1.5 rounded-pill text-muted border" data-bs-dismiss="modal">
                                Batal
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL BIOMETRIK SCAN WAJAH (STANDAR RESMI SESUAI MAINTENANCE BANK MITRA)-->
    <!-- ===================================================================== -->
    <div class="modal fade" id="modalFaceVerify" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden bg-dark text-white">
                <div class="modal-header border-secondary border-opacity-25 py-3 px-4">
                    <div>
                        <h6 class="modal-title fw-bold text-white mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-person-bounding-box text-primary me-2"></i>
                            <span id="bioModalTitle">Verifikasi Biometrik Wajah</span>
                        </h6>
                        <small class="text-white-50" id="bioModalSub">Arahkan wajah Anda ke kamera</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" onclick="closeBiometricModal(true)" aria-label="Tutup"></button>
                </div>

                <div class="modal-body p-3 text-center position-relative">
                    <!-- Scanner Viewport -->
                    <div class="bio-scanner-wrapper position-relative mx-auto rounded-3 overflow-hidden bg-black shadow-inner" style="max-width: 320px; aspect-ratio: 1/1;">
                        <video id="bioVideo" class="bio-video-el w-100 h-100 object-fit-cover" autoplay playsinline webkit-playsinline muted style="transform: scaleX(-1);"></video>
                        <canvas id="bioCanvas" class="bio-canvas-el position-absolute top-0 start-0 w-100 h-100 pointer-events-none" style="transform: scaleX(-1);"></canvas>
                        
                        <!-- Oval Guide Frame -->
                        <div id="bioFaceOval" class="position-absolute top-50 start-50 translate-middle pointer-events-none bio-face-oval" style="width: 72%; height: 85%; border: 2.5px dashed rgba(59, 130, 246, 0.85); border-radius: 50%; box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.35);"></div>
                        
                        <!-- Scanning Ray Animation -->
                        <div id="bioScanLine" class="position-absolute start-0 w-100" style="height: 3px; background: linear-gradient(90deg, transparent, #3b82f6, #60a5fa, transparent); animation: scanRayAnim 2s infinite ease-in-out;"></div>

                        <!-- Success Overlay -->
                        <div id="bioSuccessOverlay" class="position-absolute top-0 start-0 w-100 h-100 bg-success bg-opacity-75 d-flex flex-column align-items-center justify-content-center text-white d-none" style="background-color: rgba(16, 185, 129, 0.95);">
                            <i class="bi bi-check-circle-fill display-3 mb-2 animate-bounce"></i>
                            <h5 class="fw-bold text-white mb-1" id="bioMatchedName">Wajah Terverifikasi!</h5>
                            <span class="badge bg-white text-success fw-bold px-3 py-1 rounded-pill" id="bioMatchConfidence">100% Cocok</span>
                        </div>
                    </div>

                    <!-- Status Text -->
                    <div id="bioStatusBox" class="alert alert-primary py-2 px-3 small fw-semibold mt-3 mb-2 text-start text-dark">
                        <i class="bi bi-person-bounding-box me-1"></i> Arahkan wajah ke lingkaran oval (Tahan 1 detik atau kedipkan mata).
                    </div>

                    <div class="text-white-50 small mb-2" style="font-size: 0.72rem;">
                        Pastikan wajah berada di dalam oval, tidak memakai masker, dan pencahayaan memadai.
                    </div>

                    <!-- Checklist Indikator (Wajah, Kedip, Identitas Cocok) -->
                    <div class="d-flex justify-content-center gap-1.5 mb-2">
                        <span id="pillFaceDetected" class="badge bg-secondary bg-opacity-50 text-white-50 small py-1 px-2.5 border border-secondary border-opacity-50">
                            <i class="bi bi-person me-1"></i> Wajah
                        </span>
                        <span id="pillLivenessBlink" class="badge bg-secondary bg-opacity-50 text-white-50 small py-1 px-2.5 border border-secondary border-opacity-50">
                            <i class="bi bi-eye me-1"></i> Kedip
                        </span>
                        <span id="pillMatchId" class="badge bg-secondary bg-opacity-50 text-white-50 small py-1 px-2.5 border border-secondary border-opacity-50">
                            <i class="bi bi-check-all me-1"></i> Identitas Cocok
                        </span>
                    </div>

                    <div class="mt-2 text-center text-white-50 small" style="font-size: 0.72rem;">
                        <i class="bi bi-phone me-1"></i> Wajah belum terdaftar? <a href="<?= module_url('user_biometric_enroll.php', ['ret' => module_url('kartu_detail.php', ['id' => $card['id']])]) ?>" class="text-primary text-decoration-underline">Daftarkan di sini</a>
                    </div>
                </div>

                <div class="modal-footer border-secondary border-opacity-25 py-2 px-3 d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="closeBiometricModal(true)">
                        Batal
                    </button>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" id="btnManualTriggerFace" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold" onclick="triggerInstantFaceVerify()">
                            <i class="bi bi-camera me-1"></i> Verifikasi Sekarang
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Pratinjau Foto Penuh -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4 overflow-hidden shadow-2xl">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title h6 fw-bold" id="imageModalTitle">Pratinjau Foto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body text-center p-3">
                    <img src="" id="imageModalSrc" class="img-fluid rounded-3" style="max-height: 80vh; object-fit: contain; background-color: #ffffff;" alt="Foto Barang Penuh">
                </div>
            </div>
        </div>
    </div>
