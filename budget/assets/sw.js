// Service worker бюджета.
// ВАЖНО: при изменении style.css / app.js / chart.js обязательно поднимать CACHE_NAME,
// иначе у установленной PWA останутся старые ассеты (стратегия cache-first).
const CACHE_NAME = 'budget-v6';
const ASSETS = [
  '/budget/',
  '/budget/assets/style.css',
  '/budget/assets/app.js',
  '/budget/assets/chart.js',
  '/budget/assets/manifest.json'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(
        keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;

  if (request.method !== 'GET') {
    return;
  }

  // Навигация и API — сначала сеть: данные бюджета должны быть актуальными
  const url = new URL(request.url);
  if (request.mode === 'navigate' || url.pathname.startsWith('/budget/api/')) {
    event.respondWith(
      fetch(request).catch(() => caches.match(request))
    );
    return;
  }

  // Статика — из кэша, с сетью как источником обновления
  event.respondWith(
    caches.match(request).then(cached => {
      if (cached) {
        fetch(request).then(response => {
          if (response && response.ok) {
            caches.open(CACHE_NAME).then(cache => cache.put(request, response.clone()));
          }
        }).catch(() => {});
        return cached;
      }
      return fetch(request);
    })
  );
});
