const CACHE_VERSION = 'taskmesh-v1.0.0';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const DYNAMIC_CACHE = `${CACHE_VERSION}-dynamic`;
const API_CACHE = `${CACHE_VERSION}-api`;

// Files to cache immediately on install
const STATIC_ASSETS = [
  '/TaskMesh/',
  '/TaskMesh/index.html',
  '/TaskMesh/dashboard.html',
  '/TaskMesh/register.html',
  '/TaskMesh/manifest.json',
  '/TaskMesh/offline.html',
  '/TaskMesh/icons/icon-192x192.svg'
];

// Install event - cache static assets
self.addEventListener('install', event => {
  console.log('[ServiceWorker] Installing...');
  
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then(cache => {
        console.log('[ServiceWorker] Caching static assets');
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => self.skipWaiting())
      .catch(err => console.error('[ServiceWorker] Cache failed:', err))
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('[ServiceWorker] Activating...');
  
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames
            .filter(name => name.startsWith('taskmesh-') && name !== STATIC_CACHE && name !== DYNAMIC_CACHE && name !== API_CACHE)
            .map(name => {
              console.log('[ServiceWorker] Deleting old cache:', name);
              return caches.delete(name);
            })
        );
      })
      .then(() => self.clients.claim())
  );
});

// Fetch event - network strategies
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip chrome extensions and non-http(s) requests
  if (!url.protocol.startsWith('http')) {
    return;
  }

  // API requests - Network first, cache fallback
  if (url.pathname.includes('/api/')) {
    event.respondWith(networkFirstStrategy(request, API_CACHE));
  }
  // Static assets - Cache first, network fallback
  else if (isStaticAsset(url)) {
    event.respondWith(cacheFirstStrategy(request, STATIC_CACHE));
  }
  // Dynamic content - Network first, cache fallback
  else {
    event.respondWith(networkFirstStrategy(request, DYNAMIC_CACHE));
  }
});

// Cache first strategy (for static assets)
async function cacheFirstStrategy(request, cacheName) {
  try {
    const cached = await caches.match(request);
    if (cached) {
      console.log('[ServiceWorker] Serving from cache:', request.url);
      return cached;
    }

    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    console.error('[ServiceWorker] Fetch failed:', error);
    
    // Return offline page for navigation requests
    if (request.mode === 'navigate') {
      return caches.match('/TaskMesh/offline.html');
    }
    
    throw error;
  }
}

// Network first strategy (for API and dynamic content)
async function networkFirstStrategy(request, cacheName) {
  try {
    const response = await fetch(request);
    
    // Only cache GET requests (POST/PUT/DELETE can't be cached)
    if (response.ok && request.method === 'GET') {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }
    
    return response;
  } catch (error) {
    console.log('[ServiceWorker] Network failed, trying cache:', request.url);
    
    const cached = await caches.match(request);
    if (cached) {
      return cached;
    }
    
    // Return offline page for navigation requests
    if (request.mode === 'navigate') {
      return caches.match('/TaskMesh/offline.html');
    }
    
    throw error;
  }
}

// Check if request is for a static asset
function isStaticAsset(url) {
  const staticExtensions = ['.js', '.css', '.png', '.jpg', '.jpeg', '.svg', '.gif', '.woff', '.woff2', '.ttf', '.ico'];
  return staticExtensions.some(ext => url.pathname.endsWith(ext));
}

// Background sync for offline actions
self.addEventListener('sync', event => {
  console.log('[ServiceWorker] Background sync:', event.tag);
  
  if (event.tag === 'sync-tasks') {
    event.waitUntil(syncPendingTasks());
  } else if (event.tag === 'sync-comments') {
    event.waitUntil(syncPendingComments());
  }
});

// Sync pending tasks from IndexedDB
async function syncPendingTasks() {
  try {
    const db = await openDB();
    const tx = db.transaction('pendingActions', 'readonly');
    const store = tx.objectStore('pendingActions');
    const pending = await store.getAll();
    
    for (const action of pending) {
      try {
        await fetch(action.url, action.options);
        // Remove from pending queue
        const deleteTx = db.transaction('pendingActions', 'readwrite');
        deleteTx.objectStore('pendingActions').delete(action.id);
      } catch (error) {
        console.error('[ServiceWorker] Sync failed for action:', action.id, error);
      }
    }
  } catch (error) {
    console.error('[ServiceWorker] Background sync failed:', error);
  }
}

async function syncPendingComments() {
  // Similar implementation for comments
  console.log('[ServiceWorker] Syncing pending comments...');
}

// Open IndexedDB for offline queue
function openDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('TaskMeshDB', 1);
    
    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);
    
    request.onupgradeneeded = (event) => {
      const db = event.target.result;
      if (!db.objectStoreNames.contains('pendingActions')) {
        db.createObjectStore('pendingActions', { keyPath: 'id', autoIncrement: true });
      }
    };
  });
}

// Push notification event
self.addEventListener('push', event => {
  console.log('[ServiceWorker] Push received:', event);
  
  let data = {
    title: 'TaskMesh Notification',
    body: 'You have a new update',
    icon: '/TaskMesh/icons/icon-192x192.png',
    badge: '/TaskMesh/icons/badge-72x72.png',
    data: { url: '/TaskMesh/dashboard.html' }
  };

  if (event.data) {
    try {
      data = { ...data, ...event.data.json() };
    } catch (e) {
      data.body = event.data.text();
    }
  }

  const options = {
    body: data.body,
    icon: data.icon,
    badge: data.badge,
    vibrate: [200, 100, 200],
    tag: data.tag || 'taskmesh-notification',
    requireInteraction: data.requireInteraction || false,
    data: data.data,
    actions: data.actions || [
      { action: 'view', title: 'View' },
      { action: 'dismiss', title: 'Dismiss' }
    ]
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Notification click event
self.addEventListener('notificationclick', event => {
  console.log('[ServiceWorker] Notification clicked:', event.action);
  
  event.notification.close();

  if (event.action === 'dismiss') {
    return;
  }

  const urlToOpen = event.notification.data?.url || '/TaskMesh/dashboard.html';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then(clientList => {
        // Check if there's already a window open
        for (const client of clientList) {
          if (client.url === urlToOpen && 'focus' in client) {
            return client.focus();
          }
        }
        // Open new window
        if (clients.openWindow) {
          return clients.openWindow(urlToOpen);
        }
      })
  );
});

// Message event for communication with main app
self.addEventListener('message', event => {
  console.log('[ServiceWorker] Message received:', event.data);
  
  if (event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  } else if (event.data.type === 'CLEAR_CACHE') {
    event.waitUntil(
      caches.keys().then(names => 
        Promise.all(names.map(name => caches.delete(name)))
      )
    );
  }
});
