const CACHE_NAME = 'sfm-shell-v2';
const SHELL_FILES = ['/offline.html', '/manifest.webmanifest', '/offline-transactions.js', '/offline-retry-policy.js', '/icons/icon.svg'];
self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(SHELL_FILES)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', event => {
    event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith('sfm-') && key !== CACHE_NAME).map(key => caches.delete(key)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    // Never cache authenticated HTML, API responses, POST requests, or cross-origin data.
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (['/offline.html', '/manifest.webmanifest', '/offline-transactions.js', '/offline-retry-policy.js', '/icons/icon.svg'].includes(url.pathname)) {
        event.respondWith(caches.match(request).then(cached => cached || fetch(request)));
        return;
    }
    if (request.mode === 'navigate') event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
});
