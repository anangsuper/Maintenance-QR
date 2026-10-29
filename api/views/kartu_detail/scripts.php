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

        // 1. Kompresi Gambar Cerdas di Browser (Client-side) sebelum Unggah
        const fileInput = document.getElementById('fotoBarangInput');
        const previewImg = document.getElementById('previewImg');
        const emptyPlaceholder = document.getElementById('emptyPhotoPlaceholder');
        const previewBadge = document.getElementById('previewBadge');
        const compressBadge = document.getElementById('compressBadge');
        const photoWrapper = document.getElementById('photoWrapper');

        // Kompresi & Auto-Crop Cerdas Rasio 4:5 (Width 4 : Height 5)
        function compressImageClientSide(file, maxDimension, quality, callback) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const targetRatio = 4 / 5; // 0.8
                    let cropW = img.width;
                    let cropH = img.height;
                    let sx = 0;
                    let sy = 0;

                    if (img.width / img.height > targetRatio) {
                        // Foto lebih lebar dari 4:5 -> potong sisi kiri & kanan (center crop)
                        cropW = Math.round(img.height * targetRatio);
                        cropH = img.height;
                        sx = Math.round((img.width - cropW) / 2);
                        sy = 0;
                    } else {
                        // Foto lebih tinggi dari 4:5 -> potong sisi atas & bawah (center crop)
                        cropW = img.width;
                        cropH = Math.round(img.width / targetRatio);
                        sx = 0;
                        sy = Math.round((img.height - cropH) / 2);
                    }

                    // Skala resolusi output dengan rasio tepat 4:5 (maksimal 1200 x 1500)
                    let outW = 1200;
                    let outH = 1500;
                    if (cropW < outW) {
                        outW = cropW;
                        outH = Math.round(outW / targetRatio);
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = outW;
                    canvas.height = outH;
                    const ctx = canvas.getContext('2d');
                    ctx.imageSmoothingEnabled = true;
                    ctx.imageSmoothingQuality = 'high';
                    ctx.drawImage(img, sx, sy, cropW, cropH, 0, 0, outW, outH);

                    canvas.toBlob(function(blob) {
                        callback(blob, canvas.toDataURL('image/jpeg', quality));
                    }, 'image/jpeg', quality);
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        if (fileInput) {
            fileInput.addEventListener('change', function() {
                const file = this.files && this.files[0];
                if (file) {
                    // Tampilkan badge kompresi
                    if (compressBadge) {
                        compressBadge.classList.remove('d-none');
                        compressBadge.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengompres...';
                    }

                    compressImageClientSide(file, 1600, 0.82, function(compressedBlob, previewDataUrl) {
                        if (previewImg) {
                            previewImg.src = previewDataUrl;
                            previewImg.classList.remove('d-none');
                        }
                        if (emptyPlaceholder) {
                            emptyPlaceholder.classList.add('d-none');
                        }
                        if (previewBadge) {
                            previewBadge.classList.remove('d-none');
                        }
                        if (photoWrapper) {
                            photoWrapper.classList.add('has-image');
                        }

                        // Gantikan payload input file dengan file terkompres (~150-250KB)
                        try {
                            const compressedFile = new File([compressedBlob], "compressed_" + file.name.replace(/\.[^.]+$/, ".jpg"), {
                                type: "image/jpeg",
                                lastModified: Date.now()
                            });
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(compressedFile);
                            fileInput.files = dataTransfer.files;

                            if (compressBadge) {
                                const kbSize = Math.round(compressedBlob.size / 1024);
                                compressBadge.innerHTML = '<i class="bi bi-shield-check me-1"></i> ' + kbSize + ' KB (Hemat Ruang)';
                            }
                        } catch (e) {
                            console.warn("DataTransfer update fallback:", e);
                        }
                    });
                }
            });
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
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                alert('Silakan pilih file foto barang atau jepret kamera terlebih dahulu sebelum melakukan verifikasi wajah!');
                fileInput.focus();
                return;
            }
            openBiometricModal();
        }

        function openDeletePhotoConfirmModal(event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
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
            if (!confirm('Konfirmasi: Anda login sebagai Admin/SDM. Lanjutkan menghapus foto inventaris ini?')) {
                return;
            }
            document.getElementById("delBioVerified").value = "1";
            document.getElementById("delBioUserName").value = <?= json_encode(current_user_name() ?: 'Admin / SDM') ?>;
            document.getElementById("deletePhotoForm").submit();
        }

        function submitAsAuthorizedStaff() {
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                alert('Silakan pilih file foto barang terlebih dahulu!');
                fileInput.focus();
                return;
            }
            const btn = document.getElementById('btnMulaiScanWajah');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Menyimpan foto...';
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

            document.getElementById("pillFaceDetected").className = "badge bg-[#F1F5F9] text-[#475569] small py-1.5 px-3 border border-[#CBD5E1] fw-semibold";
            document.getElementById("pillMatchId").className = "badge bg-[#F1F5F9] text-[#475569] small py-1.5 px-3 border border-[#CBD5E1] fw-semibold";
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

        function closeBiometricModal() {
            bioCompleted = true;
            if (bioTrackingTimer) {
                cancelAnimationFrame(bioTrackingTimer);
                bioTrackingTimer = null;
            }
            if (bioVideoStream) {
                try {
                    bioVideoStream.getTracks().forEach(t => t.stop());
                } catch(e) {}
                bioVideoStream = null;
            }
            if (bioModalInstance) {
                bioModalInstance.hide();
            }
        }

        async function startBioCamera() {
            const video = document.getElementById("bioVideo");
            const statusBox = document.getElementById("bioStatusBox");

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                if (window.location.protocol === "http:") {
                    const httpsUrl = "https://" + window.location.host + window.location.pathname + window.location.search;
                    if (statusBox) {
                        statusBox.className = "alert alert-warning py-2.5 px-3 small mb-2";
                        statusBox.innerHTML = '<i class="bi bi-shield-exclamation text-warning me-1"></i> Kamera live memerlukan protokol <strong>HTTPS</strong>.<br><a href="' + httpsUrl + '" class="btn btn-warning btn-sm fw-bold mt-1 text-dark shadow-xs"><i class="bi bi-box-arrow-up-right me-1"></i> Buka via HTTPS agar Kamera Aktif</a>';
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
                    statusBox.innerHTML = '<i class="bi bi-person-bounding-box me-1"></i> Posisikan wajah di lingkaran oval (Tahan 1 detik).';
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
            document.getElementById("pillMatchId").className = "badge bg-success text-white small py-1.5 px-3 border border-success fw-bold";

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
                document.getElementById("delBioVerified").value = "1";
                document.getElementById("delBioUserId").value = user.id;
                document.getElementById("delBioUserName").value = user.nama;
                document.getElementById("delBioConfidence").value = conf;

                if (matchedNameEl) matchedNameEl.textContent = user.nama;
                if (matchConfEl) matchConfEl.textContent = conf + "% Cocok";
                if (successOverlay) successOverlay.classList.remove("d-none");

                if (statusBox) {
                    statusBox.className = "alert alert-success py-2 px-3 small fw-bold mb-2";
                    statusBox.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Otorisasi Hapus Diterima: <strong>' + user.nama + '</strong>! Mengosongkan foto...';
                }

                if (bioVideoStream) {
                    try { bioVideoStream.getTracks().forEach(t => t.stop()); } catch(e) {}
                }

                setTimeout(() => {
                    document.getElementById("deletePhotoForm").submit();
                }, 600);
                return;
            }

            document.getElementById("bioVerified").value = "1";
            document.getElementById("bioUserId").value = user.id;
            document.getElementById("bioUserName").value = user.nama;
            document.getElementById("bioConfidence").value = conf;
            
            const snap = captureBioSnapshot(videoEl);
            document.getElementById("bioPhotoSnap").value = snap;

            if (matchedNameEl) matchedNameEl.textContent = user.nama;
            if (matchConfEl) matchConfEl.textContent = conf + "% Cocok";
            if (successOverlay) successOverlay.classList.remove("d-none");
            if (statusBox) {
                statusBox.className = "alert alert-success py-2 px-3 small fw-bold mb-2";
                statusBox.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Wajah Terverifikasi: <strong>' + user.nama + '</strong>! Menyimpan foto...';
            }

            if (bioVideoStream) {
                try { bioVideoStream.getTracks().forEach(t => t.stop()); } catch(e) {}
            }

            const btn = document.getElementById('btnMulaiScanWajah');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Menyimpan Foto ke Server...';
            }

            setTimeout(() => {
                document.getElementById("uploadPhotoForm").submit();
            }, 600);
        }

        async function triggerInstantFaceVerify() {
            const video = document.getElementById("bioVideo");
            const statusBox = document.getElementById("bioStatusBox");
            if (!video || !video.videoWidth || bioCompleted) return;

            statusBox.className = "alert alert-warning py-2 px-3 small fw-bold mb-2";
            statusBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-warning"></span> Memproses pengenalan wajah...';

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
                        pillFace.className = "badge bg-success text-white small py-1.5 px-3 border border-success fw-bold";
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
                                    statusBox.className = "alert alert-warning py-2 px-3 small fw-semibold mb-2";
                                    statusBox.innerHTML = '<i class="bi bi-person-exclamation me-1"></i> Wajah terdeteksi, mencocokkan dengan data staf...';
                                }
                            }
                        }
                    } else {
                        oval.classList.remove("active");
                        pillFace.className = "badge bg-[#F1F5F9] text-[#475569] small py-1.5 px-3 border border-[#CBD5E1] fw-semibold";
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
