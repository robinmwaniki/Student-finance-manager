const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const test = require('node:test');
const retryPolicy = require('../../public/offline-retry-policy.js');

const clientSource = fs.readFileSync(
    path.resolve(__dirname, '../../public/offline-transactions.js'),
    'utf8',
);

function clone(value) {
    return JSON.parse(JSON.stringify(value));
}

function createClientHarness({ userId = 'user-a', sessionUserId = userId, seedEntries = [], postStatus = 201 } = {}) {
    const entries = new Map(seedEntries.map(entry => [entry.id, clone(entry)]));
    const fetchCalls = [];
    const timers = [];
    const windowListeners = new Map();
    const documentListeners = new Map();
    const itemListeners = new Map();
    const buttonListeners = new Map();

    const elements = {
        'offline-sync-panel': {},
        'offline-sync-state': { textContent: 'Online' },
        'offline-sync-detail': { textContent: '' },
        'offline-sync-items': {
            innerHTML: '',
            addEventListener(name, callback) { itemListeners.set(name, callback); },
        },
        'offline-sync-now': {
            disabled: false,
            style: {},
            addEventListener(name, callback) { buttonListeners.set(name, callback); },
        },
    };

    const database = {
        objectStoreNames: {
            names: new Set(),
            contains(name) { return this.names.has(name); },
        },
        createObjectStore(name) {
            this.objectStoreNames.names.add(name);
        },
        transaction() {
            const transaction = {
                oncomplete: null,
                onerror: null,
                onabort: null,
            };
            const store = {
                put(entry) {
                    entries.set(entry.id, clone(entry));
                    queueMicrotask(() => transaction.oncomplete?.());
                    return { result: entry };
                },
                delete(id) {
                    entries.delete(id);
                    queueMicrotask(() => transaction.oncomplete?.());
                    return { result: id };
                },
                getAll() {
                    const request = { result: [] };
                    queueMicrotask(() => {
                        request.result = [...entries.values()].map(clone);
                        request.onsuccess?.();
                    });
                    return request;
                },
            };
            transaction.objectStore = () => store;
            return transaction;
        },
    };

    const indexedDB = {
        open() {
            const request = { result: database };
            queueMicrotask(() => {
                request.onupgradeneeded?.();
                request.onsuccess?.();
            });
            return request;
        },
    };

    const fetch = async (url, options = {}) => {
        const requestUrl = String(url);
        fetchCalls.push({ url: requestUrl, options });

        if (requestUrl === '/offline/session') {
            return {
                ok: true,
                status: 200,
                async json() { return { user_id: sessionUserId }; },
            };
        }

        if (requestUrl.endsWith('/income') || requestUrl.endsWith('/expenses')) {
            if (postStatus >= 400) {
                return {
                    ok: false,
                    status: postStatus,
                    async json() { return { message: 'Temporary server failure' }; },
                };
            }

            return {
                ok: true,
                status: 201,
                async json() { return { message: 'Saved', record_id: 42 }; },
            };
        }

        throw new Error('Unexpected request: ' + requestUrl);
    };

    const window = {
        OfflineRetryPolicy: retryPolicy,
        location: {
            origin: 'https://finance.example.test',
            reload() {},
        },
        crypto: { randomUUID: () => 'new-submission-token' },
        addEventListener(name, callback) { windowListeners.set(name, callback); },
        setTimeout(callback, delay) {
            timers.push({ callback, delay });
            return timers.length;
        },
        clearTimeout(id) {
            if (timers[id - 1]) timers[id - 1].cleared = true;
        },
    };

    const document = {
        body: { dataset: { userId } },
        visibilityState: 'visible',
        getElementById(id) { return elements[id]; },
        addEventListener(name, callback) { documentListeners.set(name, callback); },
        querySelector(selector) {
            return selector === 'meta[name="csrf-token"]' ? { content: 'test-csrf-token' } : null;
        },
        querySelectorAll() { return []; },
    };

    vm.runInNewContext(clientSource, {
        document,
        window,
        navigator: { onLine: true },
        indexedDB,
        fetch,
        URL,
        URLSearchParams,
        Date,
        Promise,
        TypeError,
        Math,
        Object,
        String,
        Number,
        Array,
    }, { filename: 'offline-transactions.js' });

    return {
        entries,
        fetchCalls,
        timers,
        elements,
        windowListeners,
        documentListeners,
        itemListeners,
        buttonListeners,
    };
}

const pendingIncome = (owner = 'user-a') => ({
    id: 'submission-token-1',
    owner,
    kind: 'income',
    action: 'https://finance.example.test/income',
    payload: {
        submission_token: 'submission-token-1',
        source: 'Allowance',
        amount: '1250.00',
        transaction_date: '2026-11-21',
    },
    status: 'Pending',
    attempts: 0,
    nextAttemptAt: null,
});

const waitForClientStartup = () => new Promise(resolve => setTimeout(resolve, 10));

test('offline outbox syncs once after verifying the same signed-in account', async () => {
    const harness = createClientHarness({ seedEntries: [pendingIncome()] });

    await waitForClientStartup();

    const posts = harness.fetchCalls.filter(call => call.options.method === 'POST');
    assert.equal(posts.length, 1);
    assert.equal(posts[0].options.body.get('offline_owner_id'), 'user-a');
    assert.equal(posts[0].options.headers['X-CSRF-TOKEN'], 'test-csrf-token');
    assert.equal(harness.entries.size, 0);
});

test('account switching hides and preserves the previous account outbox without posting it', async () => {
    const harness = createClientHarness({
        userId: 'user-a',
        sessionUserId: 'user-b',
        seedEntries: [pendingIncome('user-a')],
    });

    await waitForClientStartup();

    assert.equal(harness.fetchCalls.some(call => call.options.method === 'POST'), false);
    assert.equal(harness.entries.size, 1);
    assert.equal(harness.elements['offline-sync-items'].innerHTML, '');
    assert.equal(harness.elements['offline-sync-now'].disabled, true);
    assert.match(harness.elements['offline-sync-detail'].textContent, /different account/);
});

test('transient server failures persist the pending item and schedule bounded backoff', async () => {
    const harness = createClientHarness({
        seedEntries: [pendingIncome()],
        postStatus: 503,
    });

    await waitForClientStartup();

    const [entry] = [...harness.entries.values()];
    assert.equal(entry.attempts, 1);
    assert.ok(entry.nextAttemptAt > Date.now());
    assert.match(entry.status, /Automatic retry 1\/8 in 5s/);
    assert.ok(harness.timers.some(timer => timer.delay === retryPolicy.BASE_DELAY_MS));
});
