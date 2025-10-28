// Service Worker for Blood Donation PWA
const CACHE_NAME = 'blood-donation-pwa-v3';
const urlsToCache = [
  '/',
  '/index.php',
  '/css/style.css',
  '/manifest.json',
  '/donor-registration.php',
  '/login.php'
];

const adminUrls = [
  '/admin.php',
  '/admin-login.php'
];

// Install event
self.addEventListener('install', function(event) {
  // Ensure the new SW activates immediately
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        return cache.addAll(urlsToCache);
      })
  );
});

// Fetch event
self.addEventListener('fetch', function(event) {
  const requestUrl = new URL(event.request.url);

  // Do not intercept cross-origin requests (e.g., CDN assets)
  if (requestUrl.origin !== self.location.origin) {
    return; // let the browser handle it
  }

  // Bypass SW for admin-related pages so redirects work correctly
  const isAdminUrl = adminUrls.some(url => requestUrl.pathname.endsWith(url));
  if (isAdminUrl) {
    event.respondWith(fetch(event.request));
    return;
  }

  // Handle same-origin navigation requests
  if (event.request.mode === 'navigate') {
    event.respondWith(
      caches.match(event.request).then(function(response) {
        return response || fetch(event.request);
      })
    );
    return;
  }

  // Handle other same-origin requests with cache-first, then network
  event.respondWith(
    caches.match(event.request).then(function(response) {
      return response || fetch(event.request);
    })
  );
});

// Activate event
self.addEventListener('activate', function(event) {
  event.waitUntil(
    (async () => {
      const cacheNames = await caches.keys();
      await Promise.all(
        cacheNames.map(function(cacheName) {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
      // Take control immediately
      await self.clients.claim();
    })()
  );
});
