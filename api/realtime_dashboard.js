/**
 * Real-time Dashboard WebSocket Client & Fallback Auto-Sync
 * PT BPR Mitratama Arthabuana - QR Maintenance System
 */

(function () {
  'use strict';

  // Config parameters passed from PHP
  const cfg = window.REALTIME_CONFIG || {};
  const currentMonth = parseInt(cfg.month || new Date().getMonth() + 1, 10);
  const currentYear = parseInt(cfg.year || new Date().getFullYear(), 10);
  const currentCabang = parseInt(cfg.cabang || 0, 10);
  const wsPort = parseInt(cfg.wsPort || 8080, 10);

  let socket = null;
  let isWsConnected = false;
  let reconnectTimer = null;
  let pingTimer = null;
  let fallbackPollTimer = null;
  let lastEventId = parseInt(cfg.initialEventId || Date.now(), 10);

  // DOM Elements
  const statusBadge = document.getElementById('wsStatusBadge');
  const statusDot = document.getElementById('wsStatusDot');
  const statusText = document.getElementById('wsStatusText');

  const elTotalAll = document.getElementById('kpiTotalAll');
  const elTotalDone = document.getElementById('kpiTotalDone');
  const elTotalDue = document.getElementById('kpiTotalDue');
  const elTotalFindings = document.getElementById('kpiTotalFindings');
  const elTotalActive = document.getElementById('kpiTotalActive');
  const elPercentDone = document.getElementById('kpiPercentDoneText');

  const panelPercent = document.getElementById('panelPercentDone');
  const panelProgress = document.getElementById('panelProgressBar');
  const panelDoneSide = document.getElementById('panelTotalDoneSide');
  const panelDoneFoot = document.getElementById('panelTotalDoneFoot');
  const panelDueFoot = document.getElementById('panelTotalDueFoot');
  const panelFindingsFoot = document.getElementById('panelTotalFindingsFoot');

  const streamContainer = document.getElementById('liveActivityStream');

  // Inject CSS for sleek real-time pulse animation
  const styleEl = document.createElement('style');
  styleEl.textContent = `
    @keyframes kpiPulseGreen {
      0% { transform: scale(1); background-color: transparent; }
      30% { transform: scale(1.08); background-color: rgba(34, 197, 94, 0.15); border-radius: 6px; }
      100% { transform: scale(1); background-color: transparent; }
    }
    @keyframes kpiPulseRed {
      0% { transform: scale(1); background-color: transparent; }
      30% { transform: scale(1.08); background-color: rgba(239, 68, 68, 0.15); border-radius: 6px; }
      100% { transform: scale(1); background-color: transparent; }
    }
    @keyframes slideInDown {
      0% { opacity: 0; transform: translateY(-16px); background-color: #ecfdf5; }
      100% { opacity: 1; transform: translateY(0); background-color: transparent; }
    }
    .kpi-animate-success {
      display: inline-block;
      animation: kpiPulseGreen 1.2s ease-out;
    }
    .kpi-animate-danger {
      display: inline-block;
      animation: kpiPulseRed 1.2s ease-out;
    }
    .activity-new-item {
      animation: slideInDown 0.8s ease-out;
      border-left: 3px solid #16a34a !important;
      padding-left: 8px;
    }
  `;
  document.head.appendChild(styleEl);

  function updateStatusUI(connected, mode) {
    if (!statusBadge || !statusDot || !statusText) return;

    if (connected) {
      statusBadge.style.background = '#ECFDF3';
      statusBadge.style.borderColor = '#A6F4C5';
      statusBadge.style.color = '#16803C';
      statusDot.className = 'status-dot operational';
      statusDot.style.background = '#16a34a';
      statusText.innerHTML = '<i class="bi bi-broadcast me-1"></i>WebSocket Live';
      statusBadge.title = 'Real-time WebSocket terhubung (Port ' + wsPort + '). Update otomatis tanpa reload.';
    } else {
      statusBadge.style.background = '#FFFBEB';
      statusBadge.style.borderColor = '#FDE68A';
      statusBadge.style.color = '#B45309';
      statusDot.className = 'status-dot warning';
      statusDot.style.background = '#f59e0b';
      statusText.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Auto-Sync';
      statusBadge.title = 'WebSocket offline — Menggunakan Auto-Sync background fallback (sinkron otomatis).';
    }
  }

  function showToastNotification(title, message, isSuccess = true) {
    let container = document.getElementById('realtimeToastContainer');
    if (!container) {
      container = document.createElement('div');
      container.id = 'realtimeToastContainer';
      container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      container.style.zIndex = '9999';
      document.body.appendChild(container);
    }

    const toastId = 'toast_' + Date.now();
    const borderClass = isSuccess ? 'border-success' : 'border-danger';
    const iconClass = isSuccess ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-danger';

    const toastHtml = `
      <div id="${toastId}" class="toast align-items-center bg-white ${borderClass} shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border-radius: 10px; border-width: 2px;">
        <div class="d-flex">
          <div class="toast-body d-flex align-items-start gap-2 py-2.5 px-3">
            <i class="bi ${iconClass} fs-5 mt-0.5"></i>
            <div>
              <div class="fw-bold text-dark small mb-0.5">${title}</div>
              <div class="text-secondary small" style="font-size: 0.78rem;">${message}</div>
            </div>
          </div>
          <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    `;

    container.insertAdjacentHTML('beforeend', toastHtml);
    const toastEl = document.getElementById(toastId);
    if (toastEl && window.bootstrap && window.bootstrap.Toast) {
      const bsToast = new window.bootstrap.Toast(toastEl, { delay: 6000 });
      bsToast.show();
      toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    }
  }

  function animateElement(el, className = 'kpi-animate-success') {
    if (!el) return;
    el.classList.remove('kpi-animate-success', 'kpi-animate-danger');
    void el.offsetWidth; // trigger reflow
    el.classList.add(className);
  }

  let soundEnabled = localStorage.getItem('qr_maint_sound') !== '0';

  function playNotificationSound(type = 'success') {
    if (!soundEnabled) return;
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      const ctx = new AudioCtx();
      if (ctx.state === 'suspended') {
        ctx.resume();
      }
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);

      if (type === 'critical') {
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(440, ctx.currentTime);
        osc.frequency.setValueAtTime(330, ctx.currentTime + 0.15);
        gain.gain.setValueAtTime(0.18, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.4);
      } else {
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, ctx.currentTime);
        osc.frequency.setValueAtTime(880, ctx.currentTime + 0.1);
        gain.gain.setValueAtTime(0.12, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.35);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.35);
      }
    } catch (e) {
      // Audio autoplay policy
    }
  }

  function handleRealtimeEvent(payload) {
    if (!payload) return;

    const data = payload.data || payload.payload || payload;
    const action = data.action || payload.type || payload.event || 'status_change';

    const targetMonth = parseInt(data.maintenance_month || currentMonth, 10);
    const targetYear = parseInt(data.maintenance_year || currentYear, 10);
    const cabangId = parseInt(data.cabang_id || 0, 10);

    const isCurrentPeriod = (targetMonth === currentMonth && targetYear === currentYear);
    const isCabangMatch = (currentCabang === 0 || currentCabang === cabangId);

    const assetKode = data.kode_inventaris || 'Aset #' + (data.asset_id || '-');
    const deviceName = data.perangkat || 'Perangkat IT';
    const techName = data.technician_name || 'Teknisi IT';
    const statusVal = data.status || 'Selesai';
    const isTemuan = (statusVal === 'Temuan' || statusVal === 'Perlu Perbaikan');

    // 0. Bunyikan nada alert
    playNotificationSound(isTemuan ? 'critical' : 'success');

    // 1. Tampilkan Toast Alert
    const toastTitle = isTemuan ? '⚠️ Temuan Kendala Baru Dilaporkan' : '✓ Pemeliharaan Baru Berhasil Dicatat';
    const toastMsg = `<strong>${assetKode}</strong> (${deviceName}) dicatat sebagai <em>${statusVal}</em> oleh <strong>${techName}</strong>.`;
    showToastNotification(toastTitle, toastMsg, !isTemuan);

    // 2. Jika dalam periode & cabang yang sedang dibuka pada dashboard, refresh metrik via sync endpoint
    if (isCurrentPeriod && isCabangMatch) {
      syncDashboardMetrics(true);
    }

    // 3. Tambahkan item baru ke Live Activity Stream
    prependLiveActivityItem(data);
  }

  function prependLiveActivityItem(data) {
    if (!streamContainer) return;

    const timeStr = data.maintenance_time ? data.maintenance_time.substring(0, 5) : 'Baru saja';
    const dateStr = data.maintenance_date || new Date().toISOString().substring(0, 10);
    const statusVal = data.status || 'Selesai';
    const isTemuan = (statusVal === 'Temuan' || statusVal === 'Perlu Perbaikan');

    const dotClass = isTemuan ? 'critical' : 'operational';
    const statusLabel = isTemuan ? 'Temuan Kerusakan' : 'Maintenance Selesai';
    const kode = data.kode_inventaris || 'Aset';
    const device = data.perangkat || 'Perangkat IT';
    const cabang = data.cabang_nama || 'Cabang';
    const tech = data.technician_name || 'Teknisi';
    const logId = data.log_id || 0;

    const detailBtn = logId > 0 
      ? `<a href="maintenance_detail.php?id=${logId}" class="btn btn-sm btn-light border py-1 px-2" title="Detail"><i class="bi bi-chevron-right"></i></a>` 
      : '';

    const itemHtml = `
      <div class="activity-item activity-new-item d-flex align-items-start gap-3 py-2 border-bottom">
        <div class="activity-time font-monospace text-muted small mt-1">${timeStr}</div>
        <span class="status-dot ${dotClass} mt-2"></span>
        <div class="flex-grow-1 min-w-0">
          <div class="d-flex align-items-center justify-content-between">
            <span class="fw-semibold text-dark small">${statusLabel} <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 0.65rem;">LIVE</span></span>
            <span class="text-muted" style="font-size: 0.72rem;">${dateStr}</span>
          </div>
          <div class="small text-truncate text-secondary">
            <strong class="text-primary">${kode}</strong> · ${device} (${cabang})
          </div>
          <div class="text-muted" style="font-size: 0.72rem;">Teknisi: ${tech}</div>
        </div>
        ${detailBtn}
      </div>
    `;

    // Hilangkan teks 'Belum ada aktivitas' jika ada
    const emptyState = streamContainer.querySelector('.text-center.py-4');
    if (emptyState) {
      emptyState.remove();
    }

    streamContainer.insertAdjacentHTML('afterbegin', itemHtml);
  }

  function syncDashboardMetrics(triggerAnimation = false) {
    const url = `realtime_sync.php?action=sync&since=${lastEventId}&bulan=${currentMonth}&tahun=${currentYear}&cabang=${currentCabang}`;
    
    fetch(url)
      .then(resp => resp.json())
      .then(res => {
        if (!res || !res.metrics) return;

        const m = res.metrics;
        lastEventId = res.latest_event_id || lastEventId;

        // Update KPI Card Numbers
        if (elTotalDone && m.done !== undefined) {
          if (elTotalDone.textContent != m.done) animateElement(elTotalDone, 'kpi-animate-success');
          elTotalDone.textContent = m.done;
        }
        if (elTotalDue && m.pending !== undefined) {
          if (elTotalDue.textContent != m.pending) animateElement(elTotalDue, 'kpi-animate-success');
          elTotalDue.textContent = m.pending;
        }
        if (elTotalFindings && m.findings !== undefined) {
          if (elTotalFindings.textContent != m.findings) animateElement(elTotalFindings, m.findings > 0 ? 'kpi-animate-danger' : 'kpi-animate-success');
          elTotalFindings.textContent = m.findings;
        }
        if (elPercentDone && m.percent !== undefined) {
          elPercentDone.textContent = m.percent + '%';
        }

        // Update Right Column Progress Panel
        if (panelPercent && m.percent !== undefined) panelPercent.textContent = m.percent + '%';
        if (panelProgress && m.percent !== undefined) {
          panelProgress.style.width = m.percent + '%';
          panelProgress.className = 'progress-bar ' + (m.percent >= 80 ? 'bg-success' : (m.percent >= 50 ? 'bg-primary' : 'bg-warning'));
        }
        if (panelDoneSide && m.done !== undefined) panelDoneSide.textContent = m.done;
        if (panelDoneFoot && m.done !== undefined) panelDoneFoot.textContent = m.done;
        if (panelDueFoot && m.pending !== undefined) panelDueFoot.textContent = m.pending;
        if (panelFindingsFoot && m.findings !== undefined) panelFindingsFoot.textContent = m.findings;

        // Update Branch Compliance Table Rows
        if (res.branches && Array.isArray(res.branches)) {
          res.branches.forEach(b => {
            const bId = b.id;
            const elTotal = document.getElementById('branchTotal_' + bId);
            const elDone = document.getElementById('branchDone_' + bId);
            const elPending = document.getElementById('branchPending_' + bId);
            const elFindings = document.getElementById('branchFindings_' + bId);
            const elCompliance = document.getElementById('branchCompliance_' + bId);

            if (elTotal) elTotal.textContent = b.total;
            if (elDone) {
              if (elDone.textContent != b.done) animateElement(elDone, 'kpi-animate-success');
              elDone.textContent = b.done;
            }
            if (elPending) elPending.textContent = b.pending;
            if (elFindings) {
              if (elFindings.textContent != b.findings) animateElement(elFindings, b.findings > 0 ? 'kpi-animate-danger' : 'kpi-animate-success');
              elFindings.innerHTML = b.findings > 0 ? `<span class="text-danger fw-bold">${b.findings}</span>` : `<span class="text-muted">0</span>`;
            }
            if (elCompliance) {
              const p = b.percent;
              const chipClass = p === 100 ? 'chip-success' : (p >= 75 ? 'chip-primary' : 'chip-warning');
              elCompliance.innerHTML = `<span class="badge-chip ${chipClass}">${p}%</span>`;
            }
          });
        }

        // Process any new fallback events if polling mode
        if (!isWsConnected && res.events && res.events.length > 0) {
          res.events.forEach(ev => handleRealtimeEvent(ev));
        }
      })
      .catch(err => {
        console.warn('[RealtimeSync] Sync error:', err);
      });
  }

  function startFallbackPolling() {
    if (fallbackPollTimer) return;
    fallbackPollTimer = setInterval(() => {
      syncDashboardMetrics(false);
    }, 12000);
  }

  function stopFallbackPolling() {
    if (fallbackPollTimer) {
      clearInterval(fallbackPollTimer);
      fallbackPollTimer = null;
    }
  }

  function connectWebSocket() {
    if (reconnectTimer) clearTimeout(reconnectTimer);
    if (pingTimer) clearInterval(pingTimer);

    const protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
    const host = location.hostname || 'localhost';
    const wsUrl = `${protocol}//${host}:${wsPort}`;

    try {
      socket = new WebSocket(wsUrl);

      socket.onopen = function () {
        isWsConnected = true;
        updateStatusUI(true, 'ws');
        stopFallbackPolling();
        console.log('[WebSocket] Connected to', wsUrl);

        // Ping heartbeat every 25s
        pingTimer = setInterval(() => {
          if (socket && socket.readyState === WebSocket.OPEN) {
            socket.send(JSON.stringify({ action: 'ping' }));
          }
        }, 25000);
      };

      socket.onmessage = function (event) {
        try {
          const msg = JSON.parse(event.data);
          if (msg.type === 'welcome') {
            console.log('[WebSocket]', msg.message);
            return;
          }
          if (msg.type === 'pong') {
            return;
          }
          handleRealtimeEvent(msg);
        } catch (e) {
          console.warn('[WebSocket] Malformed message:', event.data);
        }
      };

      socket.onerror = function () {
        // Handled in onclose
      };

      socket.onclose = function () {
        isWsConnected = false;
        updateStatusUI(false, 'fallback');
        startFallbackPolling();

        if (pingTimer) clearInterval(pingTimer);

        // Try reconnecting in 8 seconds
        reconnectTimer = setTimeout(() => {
          connectWebSocket();
        }, 8000);
      };
    } catch (e) {
      isWsConnected = false;
      updateStatusUI(false, 'fallback');
      startFallbackPolling();
    }
  }

  // Click on status badge to view details or reconnect
  if (statusBadge) {
    statusBadge.addEventListener('click', function () {
      if (!isWsConnected) {
        showToastNotification('Menghubungkan Ulang WebSocket...', 'Mencoba terhubung kembali ke port ' + wsPort + '...', true);
        connectWebSocket();
      } else {
        showToastNotification('WebSocket Live Aktif', 'Terhubung ke ws://' + location.hostname + ':' + wsPort + '. Pembaruan status berlangsung instan.', true);
      }
    });

    // Add sound toggle button next to badge
    if (statusBadge.parentNode) {
      const soundToggle = document.createElement('div');
      soundToggle.id = 'wsSoundToggle';
      soundToggle.className = 'ops-header-badge';
      soundToggle.style.cursor = 'pointer';
      soundToggle.style.background = soundEnabled ? '#F0FDF4' : '#F3F4F6';
      soundToggle.style.borderColor = soundEnabled ? '#BBF7D0' : '#E5E7EB';
      soundToggle.style.color = soundEnabled ? '#15803D' : '#6B7280';
      soundToggle.title = soundEnabled ? 'Suara Notifikasi: Aktif (Klik untuk matikan)' : 'Suara Notifikasi: Hening (Klik untuk aktifkan)';
      soundToggle.innerHTML = `<i class="bi bi-volume-${soundEnabled ? 'up' : 'mute'}-fill me-1"></i><span>${soundEnabled ? 'Suara On' : 'Suara Off'}</span>`;
      
      soundToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        soundEnabled = !soundEnabled;
        localStorage.setItem('qr_maint_sound', soundEnabled ? '1' : '0');
        soundToggle.style.background = soundEnabled ? '#F0FDF4' : '#F3F4F6';
        soundToggle.style.borderColor = soundEnabled ? '#BBF7D0' : '#E5E7EB';
        soundToggle.style.color = soundEnabled ? '#15803D' : '#6B7280';
        soundToggle.title = soundEnabled ? 'Suara Notifikasi: Aktif (Klik untuk matikan)' : 'Suara Notifikasi: Hening (Klik untuk aktifkan)';
        soundToggle.innerHTML = `<i class="bi bi-volume-${soundEnabled ? 'up' : 'mute'}-fill me-1"></i><span>${soundEnabled ? 'Suara On' : 'Suara Off'}</span>`;
        if (soundEnabled) playNotificationSound('success');
      });

      statusBadge.parentNode.insertBefore(soundToggle, statusBadge.nextSibling);
    }
  }

  // Initialize connection
  connectWebSocket();
})();
