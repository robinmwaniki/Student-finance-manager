const CACHE_NAME = 'sfm-shell-v3';
const SHELL_FILES = ['/offline.html', '/manifest.webmanifest', '/offline-transactions.js', '/offline-retry-policy.js', '/icons/icon.svg'];

function cachedShellResponse() {
    return caches.open(CACHE_NAME).then(cache => cache.match(new URL('/offline.html', self.location.origin)));
}

self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(SHELL_FILES)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
    event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith('sfm-') && key !== CACHE_NAME).map(key => caches.delete(key)))).then(() => self.clients.claim()));
});

// Track client connectivity because some engines expose stale online state in service workers.
const offlineClients = new Set();
self.addEventListener('message', event => {
    if (event.data?.type !== 'SFM_CLIENT_CONNECTIVITY') return;
    const clientId = event.source?.id;
    if (clientId) {
        if (event.data.online === false) offlineClients.add(clientId);
        else offlineClients.delete(clientId);
    }
    event.ports?.[0]?.postMessage({ type: 'SFM_CLIENT_CONNECTIVITY_ACK', online: event.data.online === true });
});

self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    // Never cache authenticated HTML, API responses, POST requests, or cross-origin data.
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (['/offline.html', '/manifest.webmanifest', '/offline-transactions.js', '/offline-retry-policy.js', '/icons/icon.svg'].includes(url.pathname)) {
        event.respondWith(caches.open(CACHE_NAME).then(cache => cache.match(request).then(cached => cached || fetch(request))));
        return;
    }
    if (request.mode === 'navigate') {
        // Bypass the browser HTTP cache for document navigations. Otherwise a
        // previously visited authenticated page can appear to load while the
        // device is offline, preventing the offline shell from being shown.
        const networkRequest = new Request(request, { cache: 'no-store' });
        event.respondWith((async () => {
            // Chromium's offline emulation can still allow a service-worker
            // initiated fetch to reach the test server. Check the worker's own
            // network state first, then retain the fetch failure fallback for
            // captive portals and other real-world connectivity failures.
            const activeClients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
            const activeClientIds = new Set(activeClients.map(client => client.id));
            for (const clientId of offlineClients) {
                if (!activeClientIds.has(clientId)) offlineClients.delete(clientId);
            }
            // Navigation requests may have a new resultingClientId. Any live
            // offline client therefore activates the fallback until it reports online.
            if (!self.navigator.onLine || offlineClients.size > 0 ||
                offlineClients.has(event.clientId) || offlineClients.has(event.resultingClientId)) {
                return cachedShellResponse();
            }

            try {
                return await fetch(networkRequest);
            } catch {
                return cachedShellResponse();
            }
        })());
    }
});
