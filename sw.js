// Service Worker for Blood Donation PWA
const CACHE_NAME = 'blood-donation-pwa-v2';
const urlsToCache = [
  '/blood-donation-pwa/',
  '/blood-donation-pwa/index.php',
  '/blood-donation-pwa/css/style.css',
  '/blood-donation-pwa/manifest.json',
  '/blood-donation-pwa/donor-registration.php',
  '/blood-donation-pwa/login.php',
  '/blood-donation-pwa/admin.php'
];

// Install event
self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
  );
});

// Fetch event
self.addEventListener('fetch', function(event) {
  // Handle navigation requests with preload
  if (event.request.mode === 'navigate') {
    event.respondWith(
      event.preloadResponse.then(function(preloadResponse) {
        if (preloadResponse) {
          return preloadResponse;
        }
        return caches.match(event.request).then(function(response) {
          if (response) {
            return response;
          }
          return fetch(event.request);
        });
      })
    );
  } else {
    // Handle other requests normally
    event.respondWith(
      caches.match(event.request)
        .then(function(response) {
          // Cache hit - return response
          if (response) {
            return response;
          }
          return fetch(event.request);
        })
    );
  }
});

// Activate event
self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.map(function(cacheName) {
          if (cacheName !== CACHE_NAME) {
            console.log('Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});
