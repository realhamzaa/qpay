const CACHE_NAME = 'qpay-pwa-v1';
const ASSETS = [
  '/assets/css/style.css',
  '/login.php',
  '/register.php',
  '/dashboard.php'
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS)));
});

self.addEventListener('fetch', (event) => {
  event.respondWith(caches.match(event.request).then((res) => res || fetch(event.request)));
});
