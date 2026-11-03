// Minimal app-shell cache for the farmer portal. Dashboard pages are
// server-rendered with live session data, so only static, non-personal
// assets are cached here — network-first, falling back to cache when offline.
const CACHE_NAME = 'scaims-farmer-shell-v1';
const APP_SHELL = [
  'farmerLogin/farmerlogin.html',
  'manifest.json',
  '../assets/pwa-icons/icon-192.png',
  '../assets/pwa-icons/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)).catch(() => {})
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((names) =>
      Promise.all(names.filter((name) => name !== CACHE_NAME).map((name) => caches.delete(name)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        const copy = response.clone();
        caches.open(CACHE_NAME).then((cache) => {
          if (APP_SHELL.some((path) => event.request.url.endsWith(path))) {
            cache.put(event.request, copy);
          }
        });
        return response;
      })
      .catch(() => caches.match(event.request))
  );
});
