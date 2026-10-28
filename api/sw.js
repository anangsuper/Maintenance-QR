/**
 * Service Worker Cleaner & Cache Purge for QR Maintenance IT
 * Unregisters legacy service workers and deletes all stale caches to ensure 100% direct native HTTP connection.
 */
self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.map((k) => caches.delete(k))))
      .then(() => self.registration.unregister())
      .then(() => self.clients.claim())
      .then(() => self.clients.matchAll())
      .then((clients) => {
        clients.forEach((c) => {
          if (c.url && c.navigate) {
            c.navigate(c.url);
          }
        });
      })
  );
});

// Pass all fetch requests directly to the network without any interception or caching
self.addEventListener('fetch', () => {
  return;
});
