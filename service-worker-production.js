const CACHE_VERSION = 'taskmesh-v3.0.0';
const CACHE_NAME = `${CACHE_VERSION}-cache`;

// Minimal list - only essentials
const STATIC_ASSETS = [
  '/task/offline.html',
  '/task/assets/fontawesome.min.css',
  '/task/assets/webfonts/fa-solid-900.woff2'
];

// Install - cache only offline page
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

// Activate - clean old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(
        keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

// Fetch - show offline page ONLY when offline
self.addEventListener('fetch', event => {
  // Skip non-GET requests
  if (event.request.method !== 'GET') return;
  
  event.respondWith(
    fetch(event.request)
      .catch(() => {
        // Only show offline page for navigation requests
        if (event.request.mode === 'navigate') {
          return caches.match('/task/offline.html');
        }
        // For other requests, try cache
        return caches.match(event.request);
      })
  );
});
