// Pwestora Service Worker v1.0.0
const CACHE_NAME = 'pwestora-v1.0.0';
const STATIC_ASSETS = [
  '/manifest.json',
  '/assets/css/style.css',
  '/assets/img/icon.png',
  '/assets/img/apple-touch-icon.png',
  '/assets/img/logo-web.png',
  '/assets/img/icons/Icon-192.png',
  '/assets/img/icons/Icon-512.png'
];

// Install: pre-cache critical shell assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS).catch((err) => {
        console.warn('[PWA] Cache prefetch error:', err);
      });
    }).then(() => self.skipWaiting())
  );
});

// Activate: clean up older caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch: Network-first for dynamic content & APIs; cache-first for static assets
self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // Skip non-GET and cross-origin requests
  if (req.method !== 'GET' || url.origin !== location.origin) {
    return;
  }

  // API endpoints: Pure network request to guarantee fresh real-time state
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(fetch(req));
    return;
  }

  // Static assets (CSS, images, fonts): Cache-first with network fallback
  if (url.pathname.startsWith('/assets/') || url.pathname.endsWith('.png') || url.pathname.endsWith('.css') || url.pathname === '/manifest.json') {
    event.respondWith(
      caches.match(req).then((cached) => {
        if (cached) return cached;
        return fetch(req).then((networkRes) => {
          if (networkRes && networkRes.status === 200) {
            const copy = networkRes.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
          }
          return networkRes;
        }).catch(() => cached);
      })
    );
    return;
  }

  // Dynamic pages & APIs: Network-first, fallback to cache if available
  event.respondWith(
    fetch(req).catch(() => {
      return caches.match(req);
    })
  );
});
