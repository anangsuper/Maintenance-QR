/**
 * PWA Offline Queue & Background Sync Engine
 * Handles offline storage for maintenance checklist forms & auto-sync when online.
 */
(function() {
  'use strict';

  const DB_NAME = 'QRMaintenanceDB';
  const DB_VERSION = 1;
  const STORE_NAME = 'offline_queue';

  let dbInstance = null;
  let isSyncing = false;
  let deferredInstallPrompt = null;

  // =========================================================================
  // 1. INDEXEDDB HELPERS
  // =========================================================================
  function openDB() {
    if (dbInstance) return Promise.resolve(dbInstance);
    return new Promise((resolve, reject) => {
      if (!window.indexedDB) {
        resolve(null);
        return;
      }
      const req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = (e) => {
        const db = e.target.result;
        if (!db.objectStoreNames.contains(STORE_NAME)) {
          db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
        }
      };
      req.onsuccess = (e) => {
        dbInstance = e.target.result;
        resolve(dbInstance);
      };
      req.onerror = () => resolve(null);
    });
  }

  async function getQueueItems() {
    const db = await openDB();
    if (db) {
      return new Promise((resolve) => {
        try {
          const tx = db.transaction(STORE_NAME, 'readonly');
          const store = tx.objectStore(STORE_NAME);
          const req = store.getAll();
          req.onsuccess = () => resolve(req.result || []);
          req.onerror = () => resolve(getLocalStorageQueue());
        } catch (e) {
          resolve(getLocalStorageQueue());
        }
      });
    }
    return getLocalStorageQueue();
  }

  async function saveQueueItem(item) {
    const db = await openDB();
    if (db) {
      return new Promise((resolve) => {
        try {
          const tx = db.transaction(STORE_NAME, 'readwrite');
          const store = tx.objectStore(STORE_NAME);
          const req = store.add(item);
          req.onsuccess = () => {
            updateQueueUI();
            resolve(true);
          };
          req.onerror = () => {
            saveLocalStorageQueue(item);
            resolve(true);
          };
        } catch (e) {
          saveLocalStorageQueue(item);
          resolve(true);
        }
      });
    }
    saveLocalStorageQueue(item);
    return true;
  }

  async function deleteQueueItem(id) {
    const db = await openDB();
    if (db) {
      return new Promise((resolve) => {
        try {
          const tx = db.transaction(STORE_NAME, 'readwrite');
          const store = tx.objectStore(STORE_NAME);
          const req = store.delete(id);
          req.onsuccess = () => {
            updateQueueUI();
            resolve(true);
          };
          req.onerror = () => {
            deleteLocalStorageQueue(id);
            resolve(true);
          };
        } catch (e) {
          deleteLocalStorageQueue(id);
          resolve(true);
        }
      });
    }
    deleteLocalStorageQueue(id);
    return true;
  }

  // Fallback LocalStorage methods
  function getLocalStorageQueue() {
    try {
      const data = localStorage.getItem('bm_offline_queue');
      return data ? JSON.parse(data) : [];
    } catch (e) {
      return [];
    }
  }

  function saveLocalStorageQueue(item) {
    try {
      const queue = getLocalStorageQueue();
      item.id = Date.now() + Math.floor(Math.random() * 1000);
      queue.push(item);
      localStorage.setItem('bm_offline_queue', JSON.stringify(queue));
      updateQueueUI();
    } catch (e) {}
  }

  function deleteLocalStorageQueue(id) {
    try {
      let queue = getLocalStorageQueue();
      queue = queue.filter(it => it.id !== id);
      localStorage.setItem('bm_offline_queue', JSON.stringify(queue));
      updateQueueUI();
    } catch (e) {}
  }

  // =========================================================================
  // 2. NETWORK STATUS & UI BANNER
  // =========================================================================
  function updateNetworkStatus() {
    const isOnline = navigator.onLine;
    let banner = document.getElementById('pwaNetworkBanner');

    if (!banner) {
      banner = document.createElement('div');
      banner.id = 'pwaNetworkBanner';
      banner.className = 'pwa-network-banner d-none';
      document.body.prepend(banner);
    }

    if (!isOnline) {
      banner.className = 'pwa-network-banner pwa-network-offline';
      banner.innerHTML = '<i class="bi bi-wifi-off me-2"></i><strong>Mode Offline:</strong> Koneksi internet terputus. Data pemeliharaan yang Anda simpan akan masuk ke antrean lokal HP.';
    } else {
      if (banner.classList.contains('pwa-network-offline')) {
        banner.className = 'pwa-network-banner pwa-network-online';
        banner.innerHTML = '<i class="bi bi-wifi me-2"></i><strong>Koneksi Terhubung:</strong> Sinyal kembali aktif. Menyinkronkan antrean pemeliharaan...';
        setTimeout(() => {
          banner.className = 'pwa-network-banner d-none';
        }, 3500);
        syncOfflineQueue();
      }
    }
  }

  // =========================================================================
  // 3. UI QUEUE BADGE & MODAL
  // =========================================================================
  async function updateQueueUI() {
    const items = await getQueueItems();
    const count = items.length;

    // Badges in desktop topbar / mobile topbar
    const badges = document.querySelectorAll('.pwa-queue-badge');
    badges.forEach(el => {
      el.textContent = count;
      if (count > 0) {
        el.classList.remove('d-none');
      } else {
        el.classList.add('d-none');
      }
    });

    // Buttons trigger modal
    const queueBtns = document.querySelectorAll('.pwa-queue-btn');
    queueBtns.forEach(btn => {
      if (count > 0) {
        btn.classList.remove('d-none');
      } else {
        btn.classList.add('d-none');
      }
    });

    // Populate modal table if modal exists
    renderQueueModalContent(items);
  }

  function renderQueueModalContent(items) {
    const tbody = document.getElementById('pwaQueueListBody');
    if (!tbody) return;

    if (!items || items.length === 0) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="bi bi-check2-circle text-success fs-3 d-block mb-1"></i>Tidak ada antrean data offline. Semua data telah sinkron ke server.</td></tr>';
      const btnSync = document.getElementById('btnSyncAllQueue');
      if (btnSync) btnSync.disabled = true;
      return;
    }

    const btnSync = document.getElementById('btnSyncAllQueue');
    if (btnSync) btnSync.disabled = false;

    let html = '';
    items.forEach((it) => {
      const dateStr = it.savedAt ? new Date(it.savedAt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '-';
      const typeLabel = it.actionType === 'tindak_lanjut' ? '<span class="badge bg-danger">Tindak Lanjut</span>' : '<span class="badge bg-primary">Maintenance Rutin</span>';
      html += `
        <tr>
          <td>
            <div class="fw-bold text-dark font-monospace">${escapeHtml(it.assetCode || 'Unit')}</div>
            <div class="small text-muted text-truncate" style="max-width:180px;">${escapeHtml(it.assetTitle || '-')}</div>
          </td>
          <td>
            <div class="small fw-semibold text-dark">${escapeHtml(it.technicianName || 'Teknisi')}</div>
            <div class="small text-muted" style="font-size:0.72rem;">${dateStr} WIB</div>
          </td>
          <td>${typeLabel}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="window.PWAOfflineQueue.deleteItem(${it.id})" title="Hapus"><i class="bi bi-trash"></i></button>
          </td>
        </tr>
      `;
    });
    tbody.innerHTML = html;
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);
  }

  // =========================================================================
  // 4. FORM INTERCEPTOR FOR OFFLINE SUBMISSION
  // =========================================================================
  function setupFormInterceptor() {
    const forms = [
      document.getElementById('formMaintenance'),
      document.getElementById('formTindakLanjut')
    ].filter(Boolean);

    forms.forEach(form => {
      if (form._pwaIntercepted) return;
      form._pwaIntercepted = true;

      form.addEventListener('submit', async function(e) {
        // If offline, intercept immediately and store
        if (!navigator.onLine) {
          e.preventDefault();
          await handleOfflineSubmission(form);
          return false;
        }

        // If online, attempt AJAX fetch with network error fallback
        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        const origBtnHtml = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan data...';
        }

        const formData = new FormData(form);
        const postUrl = form.action || window.location.href;

        try {
          const res = await fetch(postUrl, {
            method: 'POST',
            body: formData,
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          // If network failed (e.g. 504 Gateway / offline midway)
          if (!res.ok && res.status !== 200 && res.status !== 400 && res.status !== 422) {
            throw new Error('Network error: ' + res.status);
          }

          const contentType = res.headers.get('content-type') || '';
          if (contentType.includes('application/json')) {
            const data = await res.json();
            if (data.success) {
              // Redirect to success view or reload
              window.location.href = postUrl + (postUrl.includes('?') ? '&' : '?') + 'saved=1';
              return;
            } else {
              alert('Peringatan: ' + (data.error || 'Gagal menyimpan data'));
              if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origBtnHtml;
              }
              return;
            }
          }

          // Non-JSON standard HTML response (render normally)
          const text = await res.text();
          document.open();
          document.write(text);
          document.close();
        } catch (netErr) {
          console.warn('Network submission failed, storing to offline queue:', netErr);
          await handleOfflineSubmission(form);
        } finally {
          if (submitBtn && submitBtn.disabled) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = origBtnHtml;
          }
        }
      });
    });
  }

  async function handleOfflineSubmission(form) {
    const formData = new FormData(form);
    const dataObj = {};
    formData.forEach((value, key) => {
      dataObj[key] = value;
    });

    const assetTitleEl = document.querySelector('.card .col-8.fw-bold') || document.querySelector('.card h4');
    const assetCodeEl = document.querySelector('.card .font-monospace') || document.querySelector('.tech-label');

    const queueItem = {
      actionType: dataObj['action'] === 'save_tindak_lanjut' ? 'tindak_lanjut' : 'maintenance',
      token: dataObj['t'] || '',
      assetCode: assetCodeEl ? assetCodeEl.textContent.trim() : (dataObj['t'] || 'Aset'),
      assetTitle: assetTitleEl ? assetTitleEl.textContent.trim() : 'Komputer Kantor',
      technicianName: dataObj['technician_name'] || 'Teknisi',
      postUrl: form.action || window.location.href,
      payload: dataObj,
      savedAt: Date.now()
    };

    await saveQueueItem(queueItem);
    showOfflineSuccessCard(queueItem);
  }

  function showOfflineSuccessCard(item) {
    const mainCol = document.querySelector('.card.p-3') || document.querySelector('.container') || document.body;

    const html = `
      <div class="row justify-content-center py-3" id="offlineSavedConfirmation">
        <div class="col-12 col-md-8 col-lg-6">
          <div class="card p-4 border-0 shadow-lg rounded-4 text-center" style="background:#0D2748; color:#FFFFFF; border: 1px solid #1E3A60 !important;">
            <div class="mb-3">
              <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width:72px; height:72px; background: rgba(34, 197, 94, 0.2); color: #22C55E; font-size:36px;">
                <i class="bi bi-cloud-arrow-down-fill"></i>
              </span>
            </div>
            <h4 class="fw-bold text-white mb-2">Tersimpan di Antrean Offline!</h4>
            <p class="text-white-50 small mb-3">
              Koneksi internet sedang tidak tersedia di lokasi. Data pemeliharaan unit <strong>${escapeHtml(item.assetCode)}</strong> telah aman disimpan di memori HP Anda.
            </p>

            <div class="p-3 rounded-3 text-start mb-3" style="background:#08182F; border:1px solid #1E3A60;">
              <div class="row g-1 small">
                <div class="col-5 text-secondary">Aset:</div>
                <div class="col-7 fw-bold text-white">${escapeHtml(item.assetTitle)}</div>
                <div class="col-5 text-secondary">Kode Inv:</div>
                <div class="col-7 text-info font-monospace">${escapeHtml(item.assetCode)}</div>
                <div class="col-5 text-secondary">Teknisi:</div>
                <div class="col-7 text-white">${escapeHtml(item.technicianName)}</div>
                <div class="col-5 text-secondary">Waktu Simpan:</div>
                <div class="col-7 text-warning">${new Date(item.savedAt).toLocaleTimeString('id-ID')} WIB</div>
              </div>
            </div>

            <div class="alert alert-info py-2 px-3 small text-start border-0 mb-4" style="background: rgba(2, 106, 162, 0.2); color: #7CD4FD;">
              <i class="bi bi-info-circle-fill me-1"></i> Sistem akan secara otomatis mengunggah data ini ke server saat perangkat Anda kembali mendeteksi sinyal internet.
            </div>

            <div class="d-grid gap-2">
              <a href="scanner.php" class="btn btn-primary py-2 fw-bold"><i class="bi bi-qr-code-scan me-2"></i> Lanjut Scan Komputer Lain</a>
              <a href="dashboard.php" class="btn btn-outline-light py-2"><i class="bi bi-speedometer2 me-2"></i> Kembali ke Dashboard</a>
            </div>
          </div>
        </div>
      </div>
    `;

    const existingCard = document.querySelector('#formMaintenance') || document.querySelector('#formTindakLanjut');
    if (existingCard && existingCard.closest('.card')) {
      existingCard.closest('.card').parentElement.innerHTML = html;
    } else {
      mainCol.innerHTML = html;
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // =========================================================================
  // 5. AUTO-SYNC LOGIC
  // =========================================================================
  async function syncOfflineQueue() {
    if (!navigator.onLine || isSyncing) return;
    const items = await getQueueItems();
    if (items.length === 0) return;

    isSyncing = true;
    let successCount = 0;

    for (const item of items) {
      try {
        const formData = new FormData();
        Object.keys(item.payload || {}).forEach(k => {
          formData.append(k, item.payload[k]);
        });

        const res = await fetch(item.postUrl || 'scan.php', {
          method: 'POST',
          body: formData,
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        });

        if (res.ok) {
          const contentType = res.headers.get('content-type') || '';
          if (contentType.includes('application/json')) {
            const json = await res.json();
            if (json.success) {
              await deleteQueueItem(item.id);
              successCount++;
              continue;
            }
          } else {
            // HTML response ok
            await deleteQueueItem(item.id);
            successCount++;
            continue;
          }
        }
      } catch (e) {
        console.warn('Sync failed for item', item.id, e);
      }
    }

    isSyncing = false;
    await updateQueueUI();

    if (successCount > 0) {
      showSyncToast(`🎉 ${successCount} data pemeliharaan offline berhasil disinkronkan ke server!`);
    }
  }

  function showSyncToast(message) {
    let toast = document.getElementById('pwaSyncToast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'pwaSyncToast';
      toast.className = 'pwa-sync-toast';
      document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => {
      toast.classList.remove('show');
    }, 4500);
  }

  // =========================================================================
  // 6. PWA INSTALL PROMPT
  // =========================================================================
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredInstallPrompt = e;
    const installBtns = document.querySelectorAll('.btn-pwa-install');
    installBtns.forEach(btn => btn.classList.remove('d-none'));
  });

  window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    const installBtns = document.querySelectorAll('.btn-pwa-install');
    installBtns.forEach(btn => btn.classList.add('d-none'));
  });

  function triggerPWAInstall() {
    if (!deferredInstallPrompt) return;
    deferredInstallPrompt.prompt();
    deferredInstallPrompt.userChoice.then((choiceResult) => {
      if (choiceResult.outcome === 'accepted') {
        console.log('User accepted PWA installation');
      }
      deferredInstallPrompt = null;
    });
  }

  // =========================================================================
  // 7. INITIALIZATION
  // =========================================================================
  window.addEventListener('online', () => updateNetworkStatus());
  window.addEventListener('offline', () => updateNetworkStatus());

  document.addEventListener('DOMContentLoaded', () => {
    updateNetworkStatus();
    updateQueueUI();
    setupFormInterceptor();

    // Attach click to install buttons
    document.querySelectorAll('.btn-pwa-install').forEach(btn => {
      btn.addEventListener('click', triggerPWAInstall);
    });

    const btnSyncAll = document.getElementById('btnSyncAllQueue');
    if (btnSyncAll) {
      btnSyncAll.addEventListener('click', () => {
        syncOfflineQueue();
      });
    }
  });

  // Global API
  window.PWAOfflineQueue = {
    sync: syncOfflineQueue,
    deleteItem: async (id) => {
      if (confirm('Hapus item pemeliharaan ini dari antrean lokal?')) {
        await deleteQueueItem(id);
      }
    },
    install: triggerPWAInstall
  };
})();
