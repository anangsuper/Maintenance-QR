    <!-- Bootstrap 5 JS -->
    <script src="<?= module_url('assets/vendor/bootstrap.bundle.min.js') ?>"></script>
    
    <!-- Face API Library untuk Pengenalan Wajah -->
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.min.js"></script>

    <script>
        // Data Teknisi/Staf Terdaftar untuk Pencocokan Biometrik Wajah
        window.__ENROLLED_USERS = <?= json_encode($enrolledTechs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        const parsedEnrolledUsers = (Array.isArray(window.__ENROLLED_USERS) ? window.__ENROLLED_USERS : []).map(t => {
            let desc = t.descriptor || t.face_descriptor;
            if (typeof desc === "string") {
                try { desc = JSON.parse(desc); } catch (e) { desc = null; }
            }
            return {
                id: t.id,
                nama: t.nama,
                descriptor: (Array.isArray(desc) && desc.length >= 64) ? new Float32Array(desc) : null
            };
        }).filter(t => t.descriptor !== null);

        let bioModelsLoaded = false;
        let bioModelsLoading = false;
        let bioVideoStream = null;
        let bioTrackingTimer = null;
        let bioModalInstance = null;
        let bioCompleted = false;
        let bioFaceHoldFrames = 0;
        const HOLD_FRAMES_REQUIRED = 10;
        const MODEL_URL = "https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/";

                // Data Inventaris untuk Watermark Stempel Resmi
        window.__INVENTARIS_META = {
            rek: <?= json_encode((string)($card['nomor_rekening'] ?? '')) ?>,
            gabungan: <?= json_encode((string)($card['nomor_gabungan'] ?? '')) ?>,
            nama: <?= json_encode((string)($card['nama_barang'] ?? '')) ?>,
            kantorBank: <?= json_encode((string)($kantorBankInfo['kantor_bank_val'] ?? '01. Kantor Pusat Operasional')) ?>
        };
        // 1. Kompresi Gambar Cerdas di Browser (Client-side) sebelum Unggah (Mendukung 2 Foto)
        const fileInput = document.getElementById('fotoBarangInput');
        const fileInput2 = document.getElementById('fotoBarangInput2');
        const previewImg = document.getElementById('previewImg');
        const previewImg2 = document.getElementById('previewImg2');
        const emptyPlaceholder = document.getElementById('emptyPhotoPlaceholder');
        const emptyPlaceholder2 = document.getElementById('emptyPhotoPlaceholder2');
        const previewBadge = document.getElementById('previewBadge');
        const previewBadge2 = document.getElementById('previewBadge2');
        const compressBadge = document.getElementById('compressBadge');
        const photoWrapper = document.getElementById('photoWrapper');
        const photoWrapper2 = document.getElementById('photoWrapper2');

        function bukaSlotFoto2() {
            const col1 = document.getElementById('colDropzone1');
            const col2 = document.getElementById('colDropzone2');
            const btnBox = document.getElementById('boxBtnTambahFoto2');
            if (col1) { col1.classList.remove('col-12'); col1.classList.add('col-6'); }
            if (col2) { col2.classList.remove('d-none'); }
            if (btnBox) { btnBox.classList.add('d-none'); }
            const sc1 = document.getElementById('slotCol1');
            const sc2 = document.getElementById('slotCol2');
            if (sc1 && sc2 && !sc1.classList.contains('col-6')) {
                sc1.classList.remove('col-12'); sc1.classList.add('col-6');
                sc2.classList.remove('d-none'); sc2.classList.add('col-6');
            }
        }

        function tutupSlotFoto2() {
            const col1 = document.getElementById('colDropzone1');
            const col2 = document.getElementById('colDropzone2');
            const btnBox = document.getElementById('boxBtnTambahFoto2');
            const input2 = document.getElementById('fotoBarangInput2');
            if (input2) { input2.value = ''; }
            const sel2 = document.getElementById('dropzoneSelected2');
            const emp2 = document.getElementById('dropzoneEmpty2');
            if (sel2) sel2.classList.add('d-none');
            if (emp2) emp2.classList.remove('d-none');
            if (col2) { col2.classList.add('d-none'); }
            if (col1) { col1.classList.remove('col-6'); col1.classList.add('col-12'); }
            if (btnBox) { btnBox.classList.remove('d-none'); }
            const img2 = document.getElementById('previewImg2');
            if (img2 && (!img2.src || img2.src === window.location.href || img2.classList.contains('d-none'))) {
                const sc1 = document.getElementById('slotCol1');
                const sc2 = document.getElementById('slotCol2');
                if (sc1 && sc2) {
                    sc1.classList.remove('col-6'); sc1.classList.add('col-12');
                    sc2.classList.add('d-none'); sc2.classList.remove('col-6');
                }
            }
        }

        function openPhotoCollapseAndFocus(slot) {
            const colEl = document.getElementById('collapseUploadPhoto');
            if (colEl) {
                colEl.classList.add('show');
            }
            if (slot === 2) {
                bukaSlotFoto2();
                if (fileInput2) fileInput2.click();
            } else if (fileInput) {
                fileInput.click();
            }
        }

        // Kompresi & Auto-Fit Cerdas Rasio 4:5 Tanpa Memotong Foto (Grid / Kanvas Putih Bersih di Sisinya)
        function compressImageClientSide(file, maxDimension, quality, callback) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const outW = 1200;
                    const outH = 1500; // Standar Rasio Presisi 4:5 (Width 4 : Height 5)

                    const canvas = document.createElement('canvas');
                    canvas.width = outW;
                    canvas.height = outH;
                    const ctx = canvas.getContext('2d');

                    // 1. Berikan latar belakang kanvas putih bersih (grid / padding putih di samping foto agar terlihat penuh)
                    ctx.fillStyle = '#FFFFFF';
                    ctx.fillRect(0, 0, outW, outH);

                    // 2. Hitung skala fit-contain sehingga seluruh foto tampak utuh 100% tanpa terpotong
                    const imgW = img.naturalWidth || img.width;
                    const imgH = img.naturalHeight || img.height;
                    const scale = Math.min(outW / imgW, outH / imgH);
                    const drawW = Math.round(imgW * scale);
                    const drawH = Math.round(imgH * scale);
                    const dx = Math.round((outW - drawW) / 2);
                    const dy = Math.round((outH - drawH) / 2);

                    ctx.imageSmoothingEnabled = true;
                    ctx.imageSmoothingQuality = 'high';

                    // 3. Gambar seluruh foto di tengah kanvas putih
                    ctx.drawImage(img, 0, 0, imgW, imgH, dx, dy, drawW, drawH);

                    // 4. Stempel Watermark Otentikasi Audit Resmi (Anti-Slop Institutional)
                    try {
                        const meta = window.__INVENTARIS_META || {};
                        const bankName = "PT BPR MITRATAMA ARTHABUANA";
                        const kantor = meta.kantorBank || "Kantor Bank";
                        const assetCode = meta.rek || meta.gabungan || "INVENTARIS";

                        const now = new Date();
                        const pad = n => String(n).padStart(2, "0");
                        const dateStr = pad(now.getDate()) + "/" + pad(now.getMonth() + 1) + "/" + now.getFullYear() + " " + pad(now.getHours()) + ":" + pad(now.getMinutes()) + " WITA";

                        const barX = 24;
                        const barY = outH - 100;
                        const barW = outW - 48;
                        const barH = 76;
                        const cornerR = 8;

                        ctx.save();
                        // Background Bar Dark Navy Semi-transparan
                        ctx.fillStyle = "rgba(15, 23, 42, 0.88)";
                        ctx.beginPath();
                        if (ctx.roundRect) {
                            ctx.roundRect(barX, barY, barW, barH, cornerR);
                        } else {
                            ctx.rect(barX, barY, barW, barH);
                        }
                        ctx.fill();

                        // Garis Aksen Kombai Teal di Sisi Kiri
                        ctx.fillStyle = "#0E5C58";
                        ctx.beginPath();
                        if (ctx.roundRect) {
                            ctx.roundRect(barX, barY, 8, barH, [cornerR, 0, 0, cornerR]);
                        } else {
                            ctx.rect(barX, barY, 8, barH);
                        }
                        ctx.fill();

                        // Teks 1: Nama Bank & Kantor Cabang
                        ctx.fillStyle = "#FFFFFF";
                        ctx.font = "bold 20px 'Public Sans', system-ui, -apple-system, sans-serif";
                        ctx.fillText(bankName + " • " + kantor, barX + 24, barY + 32);

                        // Teks 2: Nomor Inventaris & Tanggal Audit
                        ctx.fillStyle = "#A7E2DC";
                        ctx.font = "500 15px 'Public Sans', system-ui, -apple-system, sans-serif";
                        ctx.fillText("NO INVENTARIS: " + assetCode + " • DOKUMENTASI FISIK RESMI • " + dateStr, barX + 24, barY + 58);

                        // Badge Sisi Kanan: VERIFIED ASSET
                        const badgeText = "VERIFIED ASSET";
                        ctx.font = "bold 13px system-ui, sans-serif";
                        const badgeW = ctx.measureText(badgeText).width + 20;
                        const badgeX = barX + barW - badgeW - 16;
                        const badgeY = barY + 22;

                        ctx.fillStyle = "rgba(14, 92, 88, 0.9)";
                        ctx.strokeStyle = "#A7E2DC";
                        ctx.lineWidth = 1;
                        ctx.beginPath();
                        if (ctx.roundRect) {
                            ctx.roundRect(badgeX, badgeY, badgeW, 32, 4);
                        } else {
                            ctx.rect(badgeX, badgeY, badgeW, 32);
                        }
                        ctx.fill();
                        ctx.stroke();

                        ctx.fillStyle = "#FFFFFF";
                        ctx.fillText(badgeText, badgeX + 10, badgeY + 21);
                        ctx.restore();
                    } catch(wmErr) {
                        console.warn("Watermark render notice:", wmErr);
                    }

                    canvas.toBlob(function(blob) {
                        callback(blob, canvas.toDataURL('image/jpeg', quality));
                    }, 'image/jpeg', quality);
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        function setupSlotPhotoCompression(inputEl, slotNum) {
            if (!inputEl) return;
            inputEl.addEventListener('change', function() {
                const file = this.files && this.files[0];
                if (!file) return;

                if (compressBadge) {
                    compressBadge.classList.remove('d-none');
                    compressBadge.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengompres Foto ' + slotNum + '...';
                }

                // Pastikan kedua kolom terlihat berdampingan ketika salah satu foto dipilih
                const sc1 = document.getElementById('slotCol1');
                const sc2 = document.getElementById('slotCol2');
                if (sc1) sc1.className = 'col-6';
                if (sc2) sc2.className = 'col-6';

                compressImageClientSide(file, 1600, 0.82, function(compressedBlob, previewDataUrl) {
                    const pImg = (slotNum === 2) ? previewImg2 : previewImg;
                    const ePlace = (slotNum === 2) ? emptyPlaceholder2 : emptyPlaceholder;
                    const pBadge = (slotNum === 2) ? previewBadge2 : previewBadge;
                    const pWrap = (slotNum === 2) ? photoWrapper2 : photoWrapper;
                    const dzEmpty = document.getElementById(slotNum === 2 ? 'dropzoneEmpty2' : 'dropzoneEmpty');
                    const dzSel = document.getElementById(slotNum === 2 ? 'dropzoneSelected2' : 'dropzoneSelected');
                    const selSize = document.getElementById(slotNum === 2 ? 'selectedFileSize2' : 'selectedFileSize');

                    if (pImg) {
                        pImg.src = previewDataUrl;
                        pImg.classList.remove('d-none');
                    }
                    if (ePlace) ePlace.classList.add('d-none');
                    if (pBadge) pBadge.classList.remove('d-none');
                    if (pWrap) pWrap.classList.add('has-image');
                    if (dzEmpty) dzEmpty.classList.add('d-none');
                    if (dzSel) dzSel.classList.remove('d-none');

                    try {
                        const compressedFile = new File([compressedBlob], "compressed_f" + slotNum + "_" + file.name.replace(/\.[^.]+$/, ".jpg"), {
                            type: "image/jpeg",
                            lastModified: Date.now()
                        });
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(compressedFile);
                        inputEl.files = dataTransfer.files;

                        const kbSize = Math.round(compressedBlob.size / 1024);
                        if (selSize) selSize.textContent = kbSize + ' KB';
                        if (compressBadge) {
                            compressBadge.innerHTML = '<i class="bi bi-shield-check me-1"></i> Foto ' + slotNum + ': ' + kbSize + ' KB';
                        }
                    } catch (e) {
                        console.warn("DataTransfer update fallback:", e);
                    }
                });
            });
        }

        setupSlotPhotoCompression(fileInput, 1);
        setupSlotPhotoCompression(fileInput2, 2);

        function hasAnyPhotoFormChange() {
            const hasF1 = fileInput && fileInput.files && fileInput.files.length > 0;
            const hasF2 = fileInput2 && fileInput2.files && fileInput2.files.length > 0;
            const chk1 = document.getElementById('chkHapusFoto1');
            const chk2 = document.getElementById('chkHapusFoto2');
            const hasDel1 = chk1 && chk1.checked;
            const hasDel2 = chk2 && chk2.checked;
            return (hasF1 || hasF2 || hasDel1 || hasDel2);
        }

        // 2. Tombol Otorisasi Wajah (Upload / Hapus / Edit Data Master)
        window.faceAuthMode = 'upload';

        function startFaceAuthForEditData() {
            var form = document.getElementById("editDataKartuForm");
            if (form && !form.checkValidity()) {
                form.reportValidity();
                return;
            }
            var modalEditEl = document.getElementById("modalEditDataKartu");
            if (modalEditEl) {
                try {
                    var m = (typeof bootstrap !== 'undefined' && bootstrap.Modal)
                        ? (bootstrap.Modal.getInstance ? bootstrap.Modal.getInstance(modalEditEl) : null)
                        : null;
                    if (m) m.hide();
                } catch(e) {}
            }
            window.faceAuthMode = 'edit_data';
            openBiometricModal();
        }

        function submitEditDataAsAuthorizedStaff() {
            var form = document.getElementById("editDataKartuForm");
            if (form && !form.checkValidity()) {
                form.reportValidity();
                return;
            }
            document.getElementById("editBioVerified").value = "1";
            document.getElementById("editBioUserName").value = <?= json_encode(current_user_name() ?: 'Admin / SDM') ?>;
            form.submit();
        }

        function startFaceAuthProcess() {
            window.faceAuthMode = 'upload';
            if (!hasAnyPhotoFormChange()) {
                alert('Silakan pilih minimal 1 file foto baru (Foto 1 / Foto 2) atau centang foto yang ingin dihilangkan terlebih dahulu!');
                if (fileInput) fileInput.focus();
                return;
            }
            openBiometricModal();
        }

        // =========================================================================
        // LOGIKA MODAL AMBIL & UPLOAD FOTO BARANG (MUDAH UNTUK ORANG AWAM)
        // =========================================================================
        function openUploadPhotoModal(slot) {
            var modalEl = document.getElementById("modalUploadFotoBarang");
            if (modalEl) {
                try {
                    var m = (typeof bootstrap !== 'undefined' && bootstrap.Modal)
                        ? (bootstrap.Modal.getOrCreateInstance ? bootstrap.Modal.getOrCreateInstance(modalEl) : new bootstrap.Modal(modalEl))
                        : null;
                    if (m) m.show();
                } catch(e) {
                    console.warn("Modal error:", e);
                }
            }
        }

        function triggerPhotoPick(mode, slot) {
            var inputId = (mode === 'cam') ? ('modalInputCam' + slot) : ('modalInputFile' + slot);
            var inputEl = document.getElementById(inputId);
            if (inputEl) {
                inputEl.click();
            }
        }

        function handleModalPhotoSelected(inputEl, slot) {
            if (!inputEl || !inputEl.files || !inputEl.files[0]) return;
            var file = inputEl.files[0];

            // Bersihkan input mode lain di slot yang sama agar tidak ganda
            var otherMode = inputEl.id.includes('Cam') ? 'File' : 'Cam';
            var otherInput = document.getElementById('modalInput' + otherMode + slot);
            if (otherInput) otherInput.value = '';

            var badge = document.getElementById('modalReadyBadge' + slot);
            var sizeSpan = document.getElementById('modalSize' + slot);
            var prevImg = document.getElementById('modalPreviewImg' + slot);
            var emptyBox = document.getElementById('modalEmptyPlace' + slot);

            if (badge) {
                badge.classList.remove('d-none');
                badge.className = "position-absolute bottom-0 start-0 end-0 py-1 text-center bg-primary text-white small fw-bold";
                badge.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengompres Foto ' + slot + '...';
            }

            // Kompresi cerdas di browser (Client-Side)
            compressImageClientSide(file, 1600, 0.82, function(compressedBlob, previewDataUrl) {
                if (prevImg) {
                    prevImg.src = previewDataUrl;
                    prevImg.style.objectFit = 'contain';
                    prevImg.style.backgroundColor = '#FFFFFF';
                    prevImg.classList.remove('d-none');
                }
                if (emptyBox) {
                    emptyBox.classList.add('d-none');
                }

                try {
                    var compressedFile = new File([compressedBlob], "f" + slot + "_" + file.name.replace(/\.[^.]+$/, ".jpg"), {
                        type: "image/jpeg",
                        lastModified: Date.now()
                    });
                    var dataTransfer = new DataTransfer();
                    dataTransfer.items.add(compressedFile);
                    inputEl.files = dataTransfer.files;

                    var kb = Math.round(compressedBlob.size / 1024);
                    if (sizeSpan) sizeSpan.textContent = kb + ' KB';
                    if (badge) {
                        badge.className = "position-absolute bottom-0 start-0 end-0 py-1 text-center bg-success text-white small fw-bold";
                        badge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Foto ' + slot + ' Siap Diunggah (' + kb + ' KB)';
                    }
                } catch(e) {
                    console.warn("DataTransfer update fallback:", e);
                    var kbFallback = Math.round(file.size / 1024);
                    if (badge) {
                        badge.className = "position-absolute bottom-0 start-0 end-0 py-1 text-center bg-success text-white small fw-bold";
                        badge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Foto ' + slot + ' Siap Diunggah (' + kbFallback + ' KB)';
                    }
                }

                // Update juga preview di halaman kartu utama
                var mainImg = (slot === 1) ? document.getElementById('previewImg') : document.getElementById('previewImg2');
                var mainEmpty = (slot === 1) ? document.getElementById('emptyPhotoPlaceholder') : document.getElementById('emptyPhotoPlaceholder2');
                var mainBadge = (slot === 1) ? document.getElementById('previewBadge') : document.getElementById('previewBadge2');
                var mainWrap = (slot === 1) ? document.getElementById('photoWrapper') : document.getElementById('photoWrapper2');
                if (mainImg) {
                    mainImg.src = previewDataUrl;
                    mainImg.classList.remove('d-none');
                }
                if (mainEmpty) mainEmpty.classList.add('d-none');
                if (mainBadge) mainBadge.classList.remove('d-none');
                if (mainWrap) mainWrap.classList.add('has-image');
            });
        }

        function togglePhotoDeleteCheck(slot, isChecked) {
            var prevImg = document.getElementById('modalPreviewImg' + slot);
            var badge = document.getElementById('modalReadyBadge' + slot);
            if (isChecked) {
                if (prevImg) prevImg.style.opacity = '0.35';
                if (badge) {
                    badge.classList.remove('d-none');
                    badge.className = "position-absolute bottom-0 start-0 end-0 py-1 text-center bg-danger text-white small fw-bold";
                    badge.innerHTML = '<i class="bi bi-trash3-fill me-1"></i> Foto ' + slot + ' Akan Dihilangkan';
                }
            } else {
                if (prevImg) prevImg.style.opacity = '1';
                if (badge) badge.classList.add('d-none');
            }
        }

        function startUploadPhotoWithFaceVerify() {
            var f1Cam = document.getElementById('modalInputCam1');
            var f1File = document.getElementById('modalInputFile1');
            var f2Cam = document.getElementById('modalInputCam2');
            var f2File = document.getElementById('modalInputFile2');
            var chkDel1 = document.getElementById('modalChkHapus1');
            var chkDel2 = document.getElementById('modalChkHapus2');

            var hasF1 = (f1Cam && f1Cam.files && f1Cam.files.length > 0) || (f1File && f1File.files && f1File.files.length > 0);
            var hasF2 = (f2Cam && f2Cam.files && f2Cam.files.length > 0) || (f2File && f2File.files && f2File.files.length > 0);
            var hasDel = (chkDel1 && chkDel1.checked) || (chkDel2 && chkDel2.checked);

            if (!hasF1 && !hasF2 && !hasDel) {
                alert('Silakan ambil foto barang menggunakan tombol "Buka Kamera" atau "Pilih dari Galeri" terlebih dahulu sebelum verifikasi wajah!');
                return;
            }

            var uploadModalEl = document.getElementById('modalUploadFotoBarang');
            if (uploadModalEl) {
                try {
                    var m = (typeof bootstrap !== 'undefined' && bootstrap.Modal)
                        ? (bootstrap.Modal.getInstance ? bootstrap.Modal.getInstance(uploadModalEl) : null)
                        : null;
                    if (m) m.hide();
                } catch(e) {}
            }

            window.faceAuthMode = 'upload';
            var titleEl = document.getElementById('bioModalTitle');
            var subEl = document.getElementById('bioModalSub');
            if (titleEl) titleEl.textContent = 'Verifikasi Wajah Petugas (Unggah Foto)';
            if (subEl) subEl.textContent = 'Arahkan wajah Anda ke kamera untuk validasi foto barang';

            openBiometricModal();
        }

        function startDeletePhotoWithFaceVerify() {
            var delModalEl = document.getElementById('modalConfirmDeletePhoto');
            if (delModalEl) {
                try {
                    var m = (typeof bootstrap !== 'undefined' && bootstrap.Modal)
                        ? (bootstrap.Modal.getInstance ? bootstrap.Modal.getInstance(delModalEl) : null)
                        : null;
                    if (m) m.hide();
                } catch(e) {}
            }

            window.faceAuthMode = 'delete';
            var titleEl = document.getElementById('bioModalTitle');
            var subEl = document.getElementById('bioModalSub');
            if (titleEl) titleEl.textContent = 'Verifikasi Wajah Petugas (Hapus Foto)';
            if (subEl) subEl.textContent = 'Arahkan wajah Anda ke kamera untuk konfirmasi penghapusan foto';

            openBiometricModal();
        }

        function handleModalPhotoSubmit(form) {
            var bioVer = document.getElementById('modalBioVerified');
            if (bioVer && bioVer.value === '1') {
                return true;
            }
            startUploadPhotoWithFaceVerify();
            return false;
        }

        function handleConfirmDeletePhotoSubmit(form) {
            var bioVer = document.getElementById('delBioVerified');
            if (bioVer && bioVer.value === '1') {
                return true;
            }
            startDeletePhotoWithFaceVerify();
            return false;
        }

        function setDeleteTargetChoice(val) {
            var targetInput = document.getElementById("delPhotoTargetInput");
            if (targetInput) targetInput.value = val || 'all';
        }

        function openDeletePhotoConfirmModal(event, targetSlot) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            var chosen = targetSlot || 'all';
            setDeleteTargetChoice(chosen);
            var radios = document.querySelectorAll('input[name="modal_del_target_radio"]');
            radios.forEach(function(r) {
                r.checked = (r.value === chosen);
            });
            var modalEl = document.getElementById("modalConfirmDeletePhoto");
            if (modalEl) {
                try {
                    var m = (typeof bootstrap !== 'undefined' && bootstrap.Modal)
                        ? (bootstrap.Modal.getOrCreateInstance ? bootstrap.Modal.getOrCreateInstance(modalEl) : new bootstrap.Modal(modalEl))
                        : null;
                    if (m) m.show();
                } catch(e) {
                    console.warn("Modal error:", e);
                }
            }
        }

        function startFaceDeleteProcess() {
            var modalEl = document.getElementById("modalConfirmDeletePhoto");
            if (modalEl) {
                try {
                    var m = (typeof bootstrap !== 'undefined' && bootstrap.Modal)
                        ? (bootstrap.Modal.getInstance ? bootstrap.Modal.getInstance(modalEl) : null)
                        : null;
                    if (m) m.hide();
                } catch(e) {}
            }
            window.faceAuthMode = 'delete';
            openBiometricModal();
        }

        function submitDeleteAsAuthorizedStaff() {
            document.getElementById("delBioVerified").value = "1";
            document.getElementById("delBioUserName").value = <?= json_encode(current_user_name() ?: 'Admin / SDM') ?>;
            document.getElementById("deletePhotoForm").submit();
        }

        function submitAsAuthorizedStaff() {
            if (!hasAnyPhotoFormChange()) {
                alert('Silakan pilih minimal 1 file foto baru (Foto 1 / Foto 2) atau centang foto yang ingin dihilangkan!');
                return;
            }
            const btn = document.getElementById('btnMulaiScanWajah');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Menyimpan perubahan foto...';
            }
            document.getElementById('bioVerified').value = "1";
            document.getElementById('bioUserName').value = <?= json_encode(current_user_name() ?: 'Staf SDM & Umum') ?>;
            document.getElementById('uploadPhotoForm').submit();
        }

        function submitAsLoggedInAdmin() {
            submitAsAuthorizedStaff();
        }

        async function loadBioModels() {
            if (bioModelsLoaded) return true;
            if (bioModelsLoading) return true;
            bioModelsLoading = true;
            const statusBox = document.getElementById("bioStatusBox");
            try {
                if (statusBox && !bioCompleted) {
                    statusBox.className = "alert alert-info py-2 px-3 small fw-semibold mb-2";
                    statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-primary"></span> Menyiapkan modul AI pengenal wajah...';
                }
                if (typeof faceapi !== "undefined" && faceapi.tf) {
                    try {
                        await faceapi.tf.setBackend("webgl");
                        await faceapi.tf.ready();
                    } catch(e) {}
                }
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                    faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_URL).catch(() => faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL)),
                    faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
                ]);
                bioModelsLoaded = true;
                bioModelsLoading = false;
                if (statusBox && !bioCompleted) {
                    statusBox.className = "alert alert-success py-2 px-3 small fw-semibold mb-2";
                    statusBox.innerHTML = '<i class="bi bi-check-circle me-1"></i> Modul AI siap. Arahkan wajah ke oval.';
                }
                return true;
            } catch (err) {
                console.error("Gagal load face-api:", err);
                bioModelsLoading = false;
                if (statusBox) {
                    statusBox.className = "alert alert-danger py-2 px-3 small mb-2";
                    statusBox.innerHTML = '<i class="bi bi-x-circle me-1"></i> Gagal memuat modul AI. Periksa jaringan internet.';
                }
                return false;
            }
        }

        function openBiometricModal() {
            const modalEl = document.getElementById("modalFaceVerify");
            if (!modalEl) return;

            bioCompleted = false;
            bioFaceHoldFrames = 0;

            ['pillFaceDetected', 'pillLivenessBlink', 'pillMatchId'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.className = "badge bg-secondary bg-opacity-50 text-white-50 small py-1 px-2.5 border border-secondary border-opacity-50";
            });
            document.getElementById("bioSuccessOverlay").classList.add("d-none");
            document.getElementById("bioScanLine").classList.add("d-none");
            document.getElementById("bioFaceOval").classList.remove("active");

            const instantBox = document.getElementById("bioInstantVerifyBox");
            if (instantBox) instantBox.classList.add("d-none");

            bioModalInstance = new bootstrap.Modal(modalEl);
            bioModalInstance.show();

            loadBioModels();
            startBioCamera();
        }

        function bypassBiometricAndSubmit() {
            closeBiometricModal();
            if (window.faceAuthMode === 'edit_data') {
                const f = document.getElementById("editDataKartuForm");
                if (f) f.submit();
                return;
            }
            if (window.faceAuthMode === 'delete') {
                const f = document.getElementById("deletePhotoForm");
                if (f) f.submit();
                return;
            }
            const f = document.getElementById("uploadPhotoForm");
            if (f) f.submit();
        }

        function closeBiometricModal(reopenPrevious) {
            bioCompleted = true;
            if (bioTrackingTimer) {
                cancelAnimationFrame(bioTrackingTimer);
                bioTrackingTimer = null;
            }
            if (bioVideoStream) {
                try {
                    bioVideoStream.getTracks().forEach(function(t) { t.stop(); });
                } catch(e) {}
                bioVideoStream = null;
            }
            if (bioModalInstance) {
                bioModalInstance.hide();
            }
            if (reopenPrevious) {
                if (window.faceAuthMode === 'upload') {
                    var upEl = document.getElementById('modalUploadFotoBarang');
                    if (upEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        var mUp = bootstrap.Modal.getOrCreateInstance(upEl);
                        if (mUp) mUp.show();
                    }
                } else if (window.faceAuthMode === 'delete') {
                    var delEl = document.getElementById('modalConfirmDeletePhoto');
                    if (delEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        var mDel = bootstrap.Modal.getOrCreateInstance(delEl);
                        if (mDel) mDel.show();
                    }
                }
            }
        }

        async function startBioCamera() {
            const video = document.getElementById("bioVideo");
            const statusBox = document.getElementById("bioStatusBox");

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                if (window.location.protocol === "http:") {
                    const httpsUrl = "https://" + window.location.host + window.location.pathname + window.location.search;
                    if (statusBox) {
                        statusBox.className = "alert alert-primary py-2.5 px-3 small mb-2";
                        statusBox.innerHTML = '<i class="bi bi-shield-exclamation text-primary me-1"></i> Kamera live memerlukan protokol <strong>HTTPS</strong>.<br><a href="' + httpsUrl + '" class="btn btn-primary btn-sm fw-bold mt-1 text-white shadow-xs"><i class="bi bi-box-arrow-up-right me-1"></i> Buka via HTTPS agar Kamera Aktif</a>';
                    }
                    return;
                }
            }

            try {
                if (statusBox && !bioModelsLoaded) {
                    statusBox.className = "alert alert-info py-2 px-3 small fw-semibold mb-2";
                    statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-info"></span> Mengaktifkan kamera selfie...';
                }

                video.setAttribute("playsinline", "");
                video.setAttribute("webkit-playsinline", "");

                bioVideoStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: "user",
                        width: { ideal: 640 },
                        height: { ideal: 480 },
                        frameRate: { ideal: 30, max: 30 }
                    },
                    audio: false
                });
                video.srcObject = bioVideoStream;
                await video.play();

                document.getElementById("bioScanLine").classList.remove("d-none");
                if (statusBox) {
                    statusBox.className = "alert alert-primary py-2 px-3 small fw-semibold mb-2";
                    statusBox.innerHTML = '<i class="bi bi-person-bounding-box me-1"></i> Arahkan wajah ke lingkaran oval (Tahan 1 detik atau kedipkan mata).';
                }

                startBioTracking();
            } catch (err) {
                console.error("Akses kamera gagal:", err);
                if (statusBox) {
                    statusBox.className = "alert alert-danger py-2 px-3 small mb-2";
                    statusBox.innerHTML = '<i class="bi bi-camera-video-off me-1"></i> Kamera tidak dapat diakses. Berikan izin di browser Anda.';
                }
            }
        }

        function calcEuclidean(a, b) {
            if (!a || !b || a.length !== b.length) return 999;
            let s = 0;
            for (let i = 0; i < a.length; i++) {
                const d = a[i] - b[i];
                s += d * d;
            }
            return Math.sqrt(s);
        }

        function captureBioSnapshot(videoEl) {
            try {
                if (!videoEl || !videoEl.videoWidth || !videoEl.videoHeight) return "";
                const c = document.createElement("canvas");
                const outSize = 180;
                c.width = outSize;
                c.height = outSize;
                const ctx = c.getContext("2d");
                ctx.imageSmoothingEnabled = true;
                const s = Math.min(videoEl.videoWidth, videoEl.videoHeight);
                const sx = (videoEl.videoWidth - s) / 2;
                const sy = (videoEl.videoHeight - s) / 2;
                ctx.translate(outSize, 0);
                ctx.scale(-1, 1);
                ctx.drawImage(videoEl, sx, sy, s, s, 0, 0, outSize, outSize);
                return c.toDataURL("image/jpeg", 0.65);
            } catch (err) {
                return "";
            }
        }

        function findBestMatch(queryVec) {
            let bestDist = 999;
            let bestUser = null;
            for (let i = 0; i < parsedEnrolledUsers.length; i++) {
                const u = parsedEnrolledUsers[i];
                const dist = calcEuclidean(queryVec, u.descriptor);
                if (dist < bestDist) {
                    bestDist = dist;
                    bestUser = u;
                }
            }
            return { user: bestUser, dist: bestDist };
        }

        function finalizeFaceVerification(user, dist, videoEl) {
            bioCompleted = true;
            const pMatchFin = document.getElementById("pillMatchId");
            if (pMatchFin) pMatchFin.className = "badge bg-success text-white small py-1 px-2.5 border border-success fw-bold";

            let conf = Math.round((1.0 - (dist / 0.60)) * 100);
            if (conf > 99) conf = 99;
            if (conf < 75) conf = 75;

            const matchedNameEl = document.getElementById("bioMatchedName");
            const matchConfEl = document.getElementById("bioMatchConfidence");
            const successOverlay = document.getElementById("bioSuccessOverlay");
            const statusBox = document.getElementById("bioStatusBox");

            // JIKA DALAM MODE EDIT DATA MASTER INVENTARIS
            if (window.faceAuthMode === 'edit_data') {
                document.getElementById("editBioVerified").value = "1";
                document.getElementById("editBioUserId").value = user.id;
                document.getElementById("editBioUserName").value = user.nama;
                document.getElementById("editBioConfidence").value = conf;

                if (matchedNameEl) matchedNameEl.textContent = user.nama;
                if (matchConfEl) matchConfEl.textContent = conf + "% Cocok";
                if (successOverlay) successOverlay.classList.remove("d-none");

                if (statusBox) {
                    statusBox.className = "alert alert-success py-2 px-3 small fw-bold mb-2";
                    statusBox.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Otorisasi Wajah Diterima: <strong>' + user.nama + '</strong>! Menyimpan perubahan data...';
                }

                if (bioVideoStream) {
                    try { bioVideoStream.getTracks().forEach(t => t.stop()); } catch(e) {}
                }

                setTimeout(() => {
                    document.getElementById("editDataKartuForm").submit();
                }, 600);
                return;
            }

            // JIKA DALAM MODE HAPUS FOTO
            if (window.faceAuthMode === 'delete') {
                var dVer = document.getElementById("delBioVerified");
                var dUid = document.getElementById("delBioUserId");
                var dUser = document.getElementById("delBioUserName");
                var dConf = document.getElementById("delBioConfidence");
                var dPhoto = document.getElementById("delBioPhoto");
                var snapDel = captureBioSnapshot(videoEl);
                if (dVer) dVer.value = "1";
                if (dUid) dUid.value = user.id;
                if (dUser) dUser.value = user.nama;
                if (dConf) dConf.value = conf;
                if (dPhoto) dPhoto.value = snapDel;

                if (matchedNameEl) matchedNameEl.textContent = user.nama;
                if (matchConfEl) matchConfEl.textContent = conf + "% Cocok";
                if (successOverlay) successOverlay.classList.remove("d-none");

                if (statusBox) {
                    statusBox.className = "alert alert-success py-2 px-3 small fw-bold mb-2";
                    statusBox.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Otorisasi Hapus Diterima: <strong>' + user.nama + '</strong>! Memindahkan foto ke Sampah...';
                }

                if (bioVideoStream) {
                    try { bioVideoStream.getTracks().forEach(function(t) { t.stop(); }); } catch(e) {}
                }

                var btnDel = document.getElementById('btnSubmitDeletePhoto');
                if (btnDel) {
                    btnDel.disabled = true;
                    btnDel.innerHTML = '<span class="spinner-border spinner-border-sm me-1.5"></span> Menghapus Foto...';
                }

                setTimeout(function() {
                    var fDel = document.getElementById("deletePhotoForm");
                    if (fDel) fDel.submit();
                }, 600);
                return;
            }

            // JIKA DALAM MODE UNGGAH FOTO BARANG (DEFAULT / UPLOAD)
            var mVer = document.getElementById("modalBioVerified");
            var mUid = document.getElementById("modalBioUserId");
            var mUser = document.getElementById("modalBioUserName");
            var mConf = document.getElementById("modalBioConfidence");
            var mPhoto = document.getElementById("modalBioPhoto");
            var snapUpload = captureBioSnapshot(videoEl);
            if (mVer) mVer.value = "1";
            if (mUid) mUid.value = user.id;
            if (mUser) mUser.value = user.nama;
            if (mConf) mConf.value = conf;
            if (mPhoto) mPhoto.value = snapUpload;

            // Fallback input lama jika ada
            var bVer = document.getElementById("bioVerified");
            var bUid = document.getElementById("bioUserId");
            var bUser = document.getElementById("bioUserName");
            var bConf = document.getElementById("bioConfidence");
            var bSnap = document.getElementById("bioPhotoSnap");
            if (bVer) bVer.value = "1";
            if (bUid) bUid.value = user.id;
            if (bUser) bUser.value = user.nama;
            if (bConf) bConf.value = conf;
            if (bSnap) bSnap.value = snapUpload;

            if (matchedNameEl) matchedNameEl.textContent = user.nama;
            if (matchConfEl) matchConfEl.textContent = conf + "% Cocok";
            if (successOverlay) successOverlay.classList.remove("d-none");
            if (statusBox) {
                statusBox.className = "alert alert-success py-2 px-3 small fw-bold mb-2";
                statusBox.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Wajah Terverifikasi: <strong>' + user.nama + '</strong>! Menyimpan foto ke server...';
            }

            if (bioVideoStream) {
                try { bioVideoStream.getTracks().forEach(function(t) { t.stop(); }); } catch(e) {}
            }

            var btnSubmit = document.getElementById('btnSubmitModalUpload');
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1.5"></span> Menyimpan Foto...';
            }

            setTimeout(function() {
                var fUpload = document.getElementById("modalUploadPhotoForm") || document.getElementById("uploadPhotoForm");
                if (fUpload) fUpload.submit();
            }, 600);
        }

        async function triggerInstantFaceVerify() {
            const video = document.getElementById("bioVideo");
            const statusBox = document.getElementById("bioStatusBox");
            if (!video || !video.videoWidth || bioCompleted) return;

            statusBox.className = "alert alert-primary py-2 px-3 small fw-bold mb-2";
            statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-primary"></span> Memproses pengenalan wajah...';

            const useTiny = faceapi.nets.faceLandmark68TinyNet && faceapi.nets.faceLandmark68TinyNet.isLoaded;
            const detectorOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.3 });

            try {
                const detection = await faceapi.detectSingleFace(video, detectorOptions)
                    .withFaceLandmarks(useTiny)
                    .withFaceDescriptor();

                if (detection && detection.descriptor) {
                    const matchResult = findBestMatch(detection.descriptor);
                    if (matchResult.user && matchResult.dist <= 0.55) {
                        finalizeFaceVerification(matchResult.user, matchResult.dist, video);
                        return;
                    }
                }
                statusBox.className = "alert alert-danger py-2 px-3 small fw-bold mb-2";
                statusBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Wajah belum cocok dengan staf resmi terdaftar. Posisikan wajah tegak.';
            } catch (err) {
                console.warn("Instant verify err:", err);
            }
        }

        function startBioTracking() {
            if (bioTrackingTimer) cancelAnimationFrame(bioTrackingTimer);
            const video = document.getElementById("bioVideo");
            const oval = document.getElementById("bioFaceOval");
            const statusBox = document.getElementById("bioStatusBox");
            const pillFace = document.getElementById("pillFaceDetected");
            const instantBox = document.getElementById("bioInstantVerifyBox");

            const useTiny = faceapi.nets.faceLandmark68TinyNet && faceapi.nets.faceLandmark68TinyNet.isLoaded;
            const detectorOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.30 });

            async function trackingLoop() {
                if (bioCompleted || !video || !video.videoWidth || video.paused || video.ended) {
                    if (!bioCompleted) {
                        bioTrackingTimer = requestAnimationFrame(trackingLoop);
                    }
                    return;
                }

                if (!bioModelsLoaded) {
                    bioTrackingTimer = requestAnimationFrame(trackingLoop);
                    return;
                }

                try {
                    const detection = await faceapi.detectSingleFace(video, detectorOptions)
                        .withFaceLandmarks(useTiny)
                        .withFaceDescriptor();

                    if (detection) {
                        oval.classList.add("active");
                        if (pillFace) pillFace.className = "badge bg-success text-white small py-1 px-2.5 border border-success fw-bold";
                        const pBlink = document.getElementById("pillLivenessBlink");
                        if (pBlink) pBlink.className = "badge bg-success text-white small py-1 px-2.5 border border-success fw-bold";
                        if (instantBox) instantBox.classList.remove("d-none");

                        if (detection.descriptor) {
                            const matchResult = findBestMatch(detection.descriptor);
                            if (matchResult.user && matchResult.dist <= 0.55) {
                                bioFaceHoldFrames++;
                                if (statusBox) {
                                    statusBox.className = "alert alert-success py-2 px-3 small fw-semibold mb-2";
                                    statusBox.innerHTML = '<i class="bi bi-check-circle me-1"></i> Mengenali: <strong>' + matchResult.user.nama + '</strong>. Tahan sebentar...';
                                }

                                if (bioFaceHoldFrames >= HOLD_FRAMES_REQUIRED) {
                                    finalizeFaceVerification(matchResult.user, matchResult.dist, video);
                                    return;
                                }
                            } else {
                                bioFaceHoldFrames = Math.max(0, bioFaceHoldFrames - 1);
                                if (statusBox) {
                                    statusBox.className = "alert alert-primary py-2 px-3 small fw-semibold mb-2";
                                    statusBox.innerHTML = '<i class="bi bi-person-exclamation text-primary me-1"></i> Wajah terdeteksi, mencocokkan dengan data staf...';
                                }
                            }
                        }
                    } else {
                        oval.classList.remove("active");
                        if (pillFace) pillFace.className = "badge bg-secondary bg-opacity-50 text-white-50 small py-1 px-2.5 border border-secondary border-opacity-50";
                        const pBlink = document.getElementById("pillLivenessBlink");
                        if (pBlink) pBlink.className = "badge bg-secondary bg-opacity-50 text-white-50 small py-1 px-2.5 border border-secondary border-opacity-50";
                        const pMatch = document.getElementById("pillMatchId");
                        if (pMatch) pMatch.className = "badge bg-secondary bg-opacity-50 text-white-50 small py-1 px-2.5 border border-secondary border-opacity-50";
                        bioFaceHoldFrames = 0;
                        if (statusBox) {
                            statusBox.className = "alert alert-primary py-2 px-3 small fw-semibold mb-2";
                            statusBox.innerHTML = '<i class="bi bi-person-bounding-box me-1"></i> Arahkan wajah ke lingkaran oval.';
                        }
                    }
                } catch (e) {
                    console.warn("Tracking error:", e);
                }

                if (!bioCompleted) {
                    bioTrackingTimer = requestAnimationFrame(trackingLoop);
                }
            }

            bioTrackingTimer = requestAnimationFrame(trackingLoop);
        }

        // 3. Render QR Code Langsung ke Halaman Web Ini
        <?php if ($card): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var qrBox = document.getElementById('cardQrCode');
            if (qrBox) {
                new QRCode(qrBox, {
                    text: <?= json_encode($canonicalQrUrl) ?>,
                    width: 82,
                    height: 82,
                    colorDark: "#002B49",
                    colorLight: "#FFFFFF",
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        });
        <?php endif; ?>

        function viewFullImage(src, title) {
            if (!src) return;
            document.getElementById('imageModalSrc').src = src;
            document.getElementById('imageModalTitle').innerText = title || 'Foto Barang';
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }

                function copyText(text, btnEl) {
            function onSuccess() {
                if (!btnEl) return;
                const oldHtml = btnEl.innerHTML;
                btnEl.innerHTML = '<i class="bi bi-check2 text-success me-1"></i>Tersalin!';
                btnEl.classList.add('border-success', 'text-success');
                setTimeout(() => {
                    btnEl.innerHTML = oldHtml;
                    btnEl.classList.remove('border-success', 'text-success');
                }, 1500);
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(onSuccess).catch(() => fallback(text, onSuccess));
            } else {
                fallback(text, onSuccess);
            }
            function fallback(t, cb) {
                const ta = document.createElement('textarea');
                ta.value = t;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try {
                    document.execCommand('copy');
                    cb();
                } catch(e) {
                    alert('Tersalin: ' + t);
                }
                document.body.removeChild(ta);
            }
        }

        function copyCardUrlDirect(btnEl) {
            const input = document.getElementById('inputCardUrl');
            const url = input ? input.value : window.location.href;
            copyText(url, btnEl);
        }

        function handleFileSelect(input) {
            if (!input || !input.files || input.files.length === 0) return;
            const f = input.files[0];
            const emptyBox = document.getElementById('dropzoneEmpty');
            const selectedBox = document.getElementById('dropzoneSelected');
            const nameEl = document.getElementById('selectedFileName');
            const sizeEl = document.getElementById('selectedFileSize');

            if (emptyBox) emptyBox.classList.add('d-none');
            if (selectedBox) selectedBox.classList.remove('d-none');
            if (nameEl) nameEl.textContent = f.name;
            if (sizeEl) sizeEl.textContent = (f.size / 1024).toFixed(1) + ' KB';
        }

        function copyCardLink() {
            navigator.clipboard.writeText(<?= json_encode($canonicalQrUrl) ?>).then(function() {
                alert('Tautan kartu inventaris berhasil disalin ke clipboard!');
            }).catch(function() {
                prompt('Salin tautan kartu ini:', <?= json_encode($canonicalQrUrl) ?>);
            });
        }
    </script>
    <?php if ($card): ?>
    <script>
        window.CURRENT_DETAIL_CARD = <?= json_encode($cardPhysicalPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        <?= get_card_png_exporter_js() ?>
    </script>
    <?php endif; ?>
