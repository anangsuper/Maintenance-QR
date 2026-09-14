/**
 * Service Worker for QR Maintenance IT
 * Handles offline caching, asset caching, and offline fallback
 */
const CACHE_NAME = 'qr-maintenance-cache-v1';

const STATIC_ASSETS = [
  './',
  'scanner.php',
  'manifest.webmanifest',
  'pwa-offline-queue.js',
  'pwa_icons.php?size=192',
  'pwa_icons.php?size=512',
  'logo.png',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
  'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js'
];

const OFFLINE_FALLBACK_HTML = `<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Offline · QR Maintenance IT</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body { background: #08182F; color: #FFFFFF; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .offline-card { background: #0D2748; border: 1px solid #1E3A60; border-radius: 16px; padding: 32px 24px; max-width: 440px; width: 100%; text-align: center; box-shadow: 0 12px 30px rgba(0,0,0,0.4); }
    .offline-icon { width: 72px; height: 72px; border-radius: 50%; background: rgba(239, 68, 68, 0.15); color: #F87171; display: inline-flex; align-items: center; justify-content: center; font-size: 32px; margin-bottom: 20px; }
  </style>
</head>
<body>
  <div class="offline-card">
    <div class="offline-icon"><i class="bi bi-wifi-off"></i></div>
    <h4 class="fw-bold mb-2">Koneksi Terputus (Offline)</h4>
    <p class="text-secondary small mb-4">Anda sedang berada di area tanpa sinyal internet (seperti basement atau ruang server). Halaman ini belum tersimpan di memori offline perangkat Anda.</p>
    <div class="d-grid gap-2">
      <a href="scanner.php" class="btn btn-primary py-2 fw-semibold"><i class="bi bi-qr-code-scan me-2"></i> Buka Pemindai QR Offline</a>
      <button onclick="window.location.reload()" class="btn btn-outline-light py-2"><i class="bi bi-arrow-clockwise me-2"></i> Coba Muat Ulang</button>
    </div>
    <div class="mt-4 pt-3 border-top border-secondary border-opacity-25 small text-secondary">
      <i class="bi bi-shield-check text-success me-1"></i> Data checklist yang telah diisi tetap aman di antrean offline HP Anda.
    </div>
  </div>
</body>
</html>`;

// 1. Install: Pre-cache core shell
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(async (cache) => {
      // Add offline fallback page
      await cache.put(new Request('offline-fallback'), new Response(OFFLINE_FALLBACK_HTML, {
        headers: { 'Content-Type': 'text/html; charset=utf-8' }
      }));
      // Pre-cache static assets individually so one failure does not fail whole install
      return Promise.allSettled(
        STATIC_ASSETS.map((url) =>
          fetch(url, { cache: 'no-cache' })
            .then((res) => {
              if (res.ok) return cache.put(url, res);
            })
            .catch(() => {})
        )
      );
    }).then(() => self.skipWaiting())
  );
});

// 2. Activate: Clean up older cache versions
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      )
    ).then(() => self.clients.claim())
  );
});

// 3. Fetch strategy:
self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Let POST requests pass through without caching (handled by offline-queue script)
  if (req.method !== 'GET') {
    return;
  }

  const url = new URL(req.url);

  // Strategy A: HTML navigation requests -> Network-First, fall back to Cache, then Offline HTML
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req)
        .then((res) => {
          if (res.ok && res.status === 200) {
            const resClone = res.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(req, resClone));
          }
          return res;
        })
        .catch(async () => {
          const cached = await caches.match(req);
          if (cached) return cached;
          const fallback = await caches.match('offline-fallback');
          return fallback || new Response('Offline', { status: 503, statusText: 'Offline' });
        })
    );
    return;
  }

  // Strategy B: Static assets (CSS, JS, Fonts, Images) -> Cache-First / Stale-While-Revalidate
  const isStatic =
    url.pathname.endsWith('.css') ||
    url.pathname.endsWith('.js') ||
    url.pathname.endsWith('.png') ||
    url.pathname.endsWith('.jpg') ||
    url.pathname.endsWith('.svg') ||
    url.pathname.endsWith('.woff2') ||
    url.pathname.endsWith('.ttf') ||
    url.hostname.includes('cdn.jsdelivr.net') ||
    url.hostname.includes('unpkg.com') ||
    url.hostname.includes('fonts.googleapis.com') ||
    url.hostname.includes('fonts.gstatic.com');

  if (isStatic) {
    event.respondWith(
      caches.match(req).then((cached) => {
        if (cached) {
          // Revalidate in background
          fetch(req).then((res) => {
            if (res.ok && res.status === 200) {
              caches.open(CACHE_NAME).then((cache) => cache.put(req, res));
            }
          }).catch(() => {});
          return cached;
        }
        return fetch(req).then((res) => {
          if (res.ok && res.status === 200) {
            const resClone = res.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(req, resClone));
          }
          return res;
        });
      })
    );
    return;
  }

  // Default: Network with Cache fallback
  event.respondWith(
    fetch(req)
      .then((res) => {
        if (res.ok && res.status === 200) {
          const resClone = res.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(req, resClone));
        }
        return res;
      })
      .catch(() => caches.match(req))
  );
});
