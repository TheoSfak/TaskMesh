// Service Worker removed - unregister any existing installations
self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => caches.delete(cacheName))
        );
      })
      .then(() => self.clients.claim())
      .then(() => self.registration.unregister())
  );
});
