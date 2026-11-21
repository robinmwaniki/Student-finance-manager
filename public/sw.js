const CACHE_NAME = 'student-finance-offline-shell-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.add(OFFLINE_URL))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key.startsWith('student-finance-') && key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Never intercept writes, cross-origin requests, or authenticated app data.
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    // Navigation is network-only. If unavailable, show a static offline notice;
    // never cache dashboards, account pages, balances, or financial responses.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(async () => {
                const cache = await caches.open(CACHE_NAME);
                return (await cache.match(OFFLINE_URL)) || Response.error();
            })
        );
    }
});
