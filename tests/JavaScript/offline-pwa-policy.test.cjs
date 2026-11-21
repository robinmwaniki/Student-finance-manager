const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');
const serviceWorker = fs.readFileSync(path.join(root, 'public/sw.js'), 'utf8');
const offlineClient = fs.readFileSync(path.join(root, 'public/offline-transactions.js'), 'utf8');
const retryPolicySource = fs.readFileSync(path.join(root, 'public/offline-retry-policy.js'), 'utf8');

test('PWA scripts are valid JavaScript', () => {
    assert.doesNotThrow(() => new vm.Script(serviceWorker, { filename: 'sw.js' }));
    assert.doesNotThrow(() => new vm.Script(offlineClient, { filename: 'offline-transactions.js' }));
    assert.doesNotThrow(() => new vm.Script(retryPolicySource, { filename: 'offline-retry-policy.js' }));
});

test('service worker uses an explicit static-only same-origin cache policy', () => {
    assert.match(serviceWorker, /const SHELL_FILES = \['\/offline\.html', '\/manifest\.webmanifest', '\/offline-transactions\.js', '\/offline-retry-policy\.js', '\/icons\/icon\.svg'\]/);
    assert.match(serviceWorker, /request\.method !== 'GET' \|\| url\.origin !== self\.location\.origin/);
    assert.match(serviceWorker, /Never cache authenticated HTML, API responses, POST requests, or cross-origin data/);
    assert.match(serviceWorker, /request\.mode === 'navigate'/);
    assert.doesNotMatch(serviceWorker, /caches\.put\(/);
});

test('offline outbox is account scoped and never stores credentials or CSRF tokens', () => {
    assert.match(offlineClient, /indexedDB\.open\('student-finance-offline'/);
    assert.match(offlineClient, /owner:\s*String\(userId\)/);
    assert.match(offlineClient, /String\(entry\.owner\) === String\(userId\)/);
    assert.match(offlineClient, /fetch\('\/offline\/session'/);
    assert.match(offlineClient, /body\.set\('offline_owner_id', String\(entry\.owner\)\)/);
    assert.match(offlineClient, /fetch\(['"]\/offline\/session['"]\)/);
    assert.match(offlineClient, /fetch\\(['\"]\\/offline\\/session['\"]\\)/);\n    assert.match(offlineClient, /accountMismatch/);
    assert.match(offlineClient, /visibilitychange/);
    assert.match(offlineClient, /key !== '_token' && key !== '_method'/);
    assert.match(offlineClient, /payload\.submission_token/);
    assert.match(offlineClient, /response\.status === 401 \|\| response\.status === 419/);
    assert.match(offlineClient, /data-edit/);
    assert.match(offlineClient, /data-discard/);
    assert.match(offlineClient, /nextAttemptAt/);
    assert.match(offlineClient, /scheduleNextRetry/);
    assert.match(offlineClient, /sync\(true\)/);
    assert.match(offlineClient, /retryPolicy\.shouldRetryAutomatically/);
});


function createServiceWorkerHarness(fetchImplementation = async () => 'network-response') {
    const listeners = new Map();
    const deletedCaches = [];
    const cachedResponses = new Map([
        ['/offline.html', 'offline-fallback'],
        ['/manifest.webmanifest', 'manifest'],
    ]);
    let openedCache = null;
    let addedShellFiles = null;
    let skipWaitingCalled = false;
    let clientsClaimed = false;

    const cache = {
        async addAll(files) { addedShellFiles = files; },
        async match(request) {
            const key = typeof request === 'string' ? request : new URL(request.url).pathname;
            return cachedResponses.get(key);
        },
    };
    const cacheStorage = {
        async open(name) { openedCache = name; return cache; },
        async keys() { return ['unrelated-cache', 'sfm-shell-v0', 'sfm-user-cache', 'another-app-cache']; },
        async delete(name) { deletedCaches.push(name); return true; },
        async match(request) { return cache.match(request); },
    };
    const self = {
        location: { origin: 'https://finance.example.test' },
        clients: { async claim() { clientsClaimed = true; } },
        addEventListener(name, callback) { listeners.set(name, callback); },
        async skipWaiting() { skipWaitingCalled = true; },
    };

    vm.runInNewContext(serviceWorker, {
        self,
        caches: cacheStorage,
        fetch: fetchImplementation,
        URL,
        Promise,
    }, { filename: 'sw.js' });

    return {
        listeners,
        deletedCaches,
        cachedResponses,
        get openedCache() { return openedCache; },
        get addedShellFiles() { return addedShellFiles; },
        get skipWaitingCalled() { return skipWaitingCalled; },
        get clientsClaimed() { return clientsClaimed; },
    };
}

test('service worker installation precaches only the declared static shell and activates immediately', async () => {
    const harness = createServiceWorkerHarness();
    const waits = [];

    harness.listeners.get('install')({ waitUntil(promise) { waits.push(promise); } });
    await Promise.all(waits);

    assert.equal(harness.openedCache, 'sfm-shell-v2');
    assert.deepEqual(Array.from(harness.addedShellFiles), [
        '/offline.html',
        '/manifest.webmanifest',
        '/offline-transactions.js',
        '/offline-retry-policy.js',
        '/icons/icon.svg',
    ]);
    assert.equal(harness.skipWaitingCalled, true);
});

test('activation deletes only old app-owned caches and claims clients', async () => {
    const harness = createServiceWorkerHarness();
    const waits = [];

    harness.listeners.get('activate')({ waitUntil(promise) { waits.push(promise); } });
    await Promise.all(waits);

    assert.deepEqual(harness.deletedCaches, ['sfm-shell-v0', 'sfm-user-cache']);
    assert.equal(harness.clientsClaimed, true);
});

test('service worker leaves POST and cross-origin requests completely untouched', () => {
    const harness = createServiceWorkerHarness();
    const post = {
        request: { method: 'POST', url: 'https://finance.example.test/income', mode: 'cors' },
        respondWith() { assert.fail('POST requests must not be intercepted'); },
    };
    const crossOrigin = {
        request: { method: 'GET', url: 'https://api.example.test/rates', mode: 'cors' },
        respondWith() { assert.fail('Cross-origin requests must not be intercepted'); },
    };

    harness.listeners.get('fetch')(post);
    harness.listeners.get('fetch')(crossOrigin);
});

test('service worker serves a cached shell asset without fetching it again', async () => {
    let fetchCalls = 0;
    const harness = createServiceWorkerHarness(async () => { fetchCalls++; return 'network-response'; });
    let response;

    harness.listeners.get('fetch')({
        request: { method: 'GET', url: 'https://finance.example.test/offline.html', mode: 'cors' },
        respondWith(promise) { response = promise; },
    });

    assert.equal(await response, 'offline-fallback');
    assert.equal(fetchCalls, 0);
});

test('navigation falls back to the offline page only when the network request fails', async () => {
    const harness = createServiceWorkerHarness(async () => { throw new TypeError('offline'); });
    let response;

    harness.listeners.get('fetch')({
        request: { method: 'GET', url: 'https://finance.example.test/dashboard', mode: 'navigate' },
        respondWith(promise) { response = promise; },
    });

    assert.equal(await response, 'offline-fallback');
});
