const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { chromium } = require('playwright');

const baseUrl = (process.env.SFM_BROWSER_BASE_URL || '').replace(/\/$/, '');
const sessionCookie = process.env.SFM_BROWSER_SESSION_COOKIE || '';
const submissionToken = process.env.SFM_OFFLINE_E2E_TOKEN || '';

for (const [name, value] of Object.entries({ SFM_BROWSER_BASE_URL: baseUrl, SFM_BROWSER_SESSION_COOKIE: sessionCookie, SFM_OFFLINE_E2E_TOKEN: submissionToken })) {
    if (!value) throw new Error(`Missing required environment variable: ${name}`);
}

const profileDir = fs.mkdtempSync(path.join(os.tmpdir(), 'sfm-offline-browser-'));
const cookie = {
    name: 'sfm-http-integration-session',
    value: sessionCookie,
    domain: '127.0.0.1',
    path: '/',
    expires: Math.floor(Date.now() / 1000) + 3600,
    httpOnly: true,
    secure: false,
    sameSite: 'Lax',
};

async function openContext() {
    const context = await chromium.launchPersistentContext(profileDir, {
        headless: true,
        viewport: { width: 1365, height: 900 },
        serviceWorkers: 'allow',
        args: ['--no-sandbox'],
    });
    // Rehydrate the synthetic Laravel session minted by the preceding curl login.
    // This keeps browser UI login concerns separate from the offline transaction flow.
    await context.addCookies([cookie]);
    return context;
}

async function assertAuthenticated(page) {
    const response = await page.request.get(baseUrl + '/offline/session');
    assert.equal(response.status(), 200, 'the synthetic Redis-backed browser session must be authenticated');
    const body = await response.json();
    assert.ok(body.user_id, 'the offline session endpoint must identify the authenticated CI user');
}

async function outboxCount(page) {
    return page.evaluate(async () => {
        const db = await new Promise((resolve, reject) => {
            const request = indexedDB.open('student-finance-offline', 1);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
        return new Promise((resolve, reject) => {
            const request = db.transaction('outbox', 'readonly').objectStore('outbox').count();
            request.onsuccess = () => { db.close(); resolve(request.result); };
            request.onerror = () => reject(request.error);
        });
    });
}

(async () => {
    let context;
    try {
        context = await openContext();
        let page = context.pages()[0] || await context.newPage();
        const pageErrors = [];
        const browserConsole = [];
        const retryPolicyResponses = [];
        page.on('pageerror', error => pageErrors.push(error.message));
        page.on('console', message => browserConsole.push({ type: message.type(), text: message.text() }));
        page.on('requestfailed', request => {
            if (request.url().endsWith('/offline-retry-policy.js')) browserConsole.push({ type: 'requestfailed', text: request.failure()?.errorText || 'unknown' });
        });
        page.on('response', response => {
            if (response.url().endsWith('/offline-retry-policy.js')) {
                retryPolicyResponses.push({ status: response.status(), contentType: response.headers()['content-type'] || null });
            }
        });
        // Load the page at the mobile viewport from the start. Resizing an
        // already-rendered desktop page can preserve stale layout/scroll state
        // in Chromium and produce misleading element coordinates.
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(baseUrl + '/income', { waitUntil: 'domcontentloaded' });
        await assertAuthenticated(page);

        // This is browser emulation, not a substitute for a physical
        // Android/iOS device check.
        const mobileLayout = await page.evaluate(() => {
            const form = document.querySelector('form[action$="/income"]');
            const submit = form?.querySelector('button[type="submit"]');
            const formBounds = form?.getBoundingClientRect();
            const controls = form ? [...form.querySelectorAll('input:not([type="hidden"]), select, button')] : [];
            const controlBounds = controls.map(element => {
                const bounds = element.getBoundingClientRect();
                return { name: element.name || element.tagName.toLowerCase(), left: bounds.left, right: bounds.right };
            });
            const boundsOf = selector => {
                const element = document.querySelector(selector);
                const bounds = element?.getBoundingClientRect();
                return bounds ? { left: bounds.left, right: bounds.right, width: bounds.width } : null;
            };
            return {
                viewportWidth: window.innerWidth,
                visualViewportWidth: window.visualViewport?.width ?? null,
                scrollX: window.scrollX,
                documentWidth: document.documentElement.scrollWidth,
                bodyWidth: document.body.getBoundingClientRect().width,
                shellBounds: boundsOf('.shell'),
                sidebarBounds: boundsOf('.sidebar'),
                mainBounds: boundsOf('.main'),
                mainMarginLeft: document.querySelector('.main') ? getComputedStyle(document.querySelector('.main')).marginLeft : null,
                sidebarPosition: document.querySelector('.sidebar') ? getComputedStyle(document.querySelector('.sidebar')).position : null,
                bodyDisplay: getComputedStyle(document.body).display,
                gridColumns: form ? getComputedStyle(form).gridTemplateColumns.split(' ').length : 0,
                formBounds: formBounds ? { left: formBounds.left, right: formBounds.right, width: formBounds.width } : null,
                controlsInsideViewport: controlBounds.length > 0 && controlBounds.every(bounds => bounds.left >= 0 && bounds.right <= window.innerWidth + 1),
                controlBounds,
                submitVisible: Boolean(submit && submit.getBoundingClientRect().width > 0 && submit.getBoundingClientRect().height > 0),
            };
        });
        assert.ok(mobileLayout.documentWidth <= mobileLayout.viewportWidth + 1,
            `mobile layout must not introduce page-level horizontal scrolling: ${JSON.stringify(mobileLayout)}`);
        assert.equal(mobileLayout.gridColumns, 1,
            `income form must collapse to one column on a 390px viewport: ${JSON.stringify(mobileLayout)}`);
        assert.equal(mobileLayout.controlsInsideViewport, true,
            `income form controls must remain within the mobile viewport: ${JSON.stringify(mobileLayout)}`);
        assert.equal(mobileLayout.submitVisible, true,
            `income submit control must remain visible on mobile: ${JSON.stringify(mobileLayout)}`);

        const mobileSubmit = page.locator('form[action$="/income"] button[type="submit"]').first();
        await page.evaluate(() => document.activeElement?.blur());
        let submitReachedByKeyboard = false;
        for (let tab = 0; tab < 50; tab += 1) {
            await page.keyboard.press('Tab');
            if (await mobileSubmit.evaluate(element => element === document.activeElement)) {
                submitReachedByKeyboard = true;
                break;
            }
        }
        assert.equal(submitReachedByKeyboard, true,
            'income submit control must be reachable using keyboard Tab navigation');
        console.log('PASS: mobile viewport layout and keyboard access to income submission.');

        await page.setViewportSize({ width: 1365, height: 900 });
        await page.evaluate(async () => { await navigator.serviceWorker.ready; return true; });
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.waitForFunction(() => Boolean(navigator.serviceWorker.controller), null, { timeout: 15000 });

        const incomeForm = page.locator('form[action$="/income"]').first();
        const tokenInput = incomeForm.locator('input[name="submission_token"]');
        // The CSRF/idempotency token is intentionally a hidden input, so set it
        // through the DOM rather than asking Playwright to fill an invisible field.
        await tokenInput.evaluate((element, value) => {
            element.value = value;
            element.dispatchEvent(new Event('input', { bubbles: true }));
            element.dispatchEvent(new Event('change', { bubbles: true }));
        }, submissionToken);
        await incomeForm.locator('input[name="source"]').fill('Playwright Offline Income');
        await incomeForm.locator('input[name="amount"]').fill('17.25');
        const dateInput = incomeForm.locator('input[name="transaction_date"]');
        await dateInput.fill(await dateInput.getAttribute('max'));
        await incomeForm.locator('select[name="wallet_id"]').selectOption({ index: 0 });

        const runtime = await page.evaluate(() => ({
            userId: document.body.dataset.userId || null,
            retryPolicyLoaded: Boolean(window.OfflineRetryPolicy),
            queueType: document.querySelector('form[action$="/income"]')?.dataset.offlineQueue || null,
            scriptLoaded: [...document.scripts].some(script => script.src.endsWith('/offline-transactions.js')),
            retryScript: (() => { const script = [...document.scripts].find(item => item.src.endsWith('/offline-retry-policy.js')); return script ? { src: script.src, type: script.type, defer: script.defer, async: script.async } : null; })(),
            readyState: document.readyState,
            retryPolicyType: typeof window.OfflineRetryPolicy,
            invalidControls: [...document.querySelectorAll('form[action$="/income"] :invalid')].map(element => ({
                name: element.name || element.id || element.tagName,
                validity: element.validationMessage,
            })),
        }));
        assert.ok(runtime.userId, 'authenticated layout must expose the current user ID to the offline runtime');
        assert.equal(runtime.retryPolicyLoaded, true, `offline retry policy must be loaded; runtime=${JSON.stringify(runtime)}; responses=${JSON.stringify(retryPolicyResponses)}; pageErrors=${JSON.stringify(pageErrors)}; console=${JSON.stringify(browserConsole)}`);
        assert.equal(runtime.queueType, 'income', 'income form must be registered with the offline queue');
        assert.equal(runtime.scriptLoaded, true, 'offline transaction runtime script must be included');
        assert.deepEqual(runtime.invalidControls, [], 'income form must be valid before submission');

        await context.setOffline(true);
        assert.equal(await page.evaluate(() => navigator.onLine), false, 'Chromium must report offline before submitting');
        // requestSubmit dispatches the browser's native submit event; the offline
        // runtime must intercept it and persist the transaction without navigation.
        await incomeForm.evaluate(form => {
            const submitter = form.querySelector('button[type="submit"]');
            form.requestSubmit(submitter);
        });
        try {
            await page.waitForFunction(() => {
                const detail = document.querySelector('#offline-sync-detail')?.textContent || '';
                return detail.includes('Saved on this device') || detail.includes('unavailable') || detail.includes('could not') || detail.includes('missing') || detail.includes('changed');
            }, null, { timeout: 10000 });
        } catch (error) {
            const diagnostics = await page.evaluate(async () => {
                const detail = document.querySelector('#offline-sync-detail')?.textContent || null;
                const state = document.querySelector('#offline-sync-state')?.textContent || null;
                const database = await new Promise(resolve => {
                    const request = indexedDB.open('student-finance-offline', 1);
                    request.onsuccess = () => resolve({ opened: true, stores: [...request.result.objectStoreNames] });
                    request.onerror = () => resolve({ opened: false, error: request.error?.message || 'unknown' });
                });
                return { detail, state, online: navigator.onLine, database };
            });
            throw new Error(`offline submission did not reach a saved/diagnostic state: ${JSON.stringify(diagnostics)}; pageErrors=${JSON.stringify(pageErrors)}; console=${JSON.stringify(browserConsole)}; cause=${error.message}`);
        }
        const savedDetail = await page.locator('#offline-sync-detail').textContent();
        assert.ok(savedDetail.includes('Saved on this device'), `offline submission failed: ${savedDetail}`);
        assert.equal(await outboxCount(page), 1, 'offline transaction must be persisted in IndexedDB');
        const queuedAction = await page.evaluate(async () => {
            const db = await new Promise((resolve, reject) => {
                const request = indexedDB.open('student-finance-offline', 1);
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
            return new Promise((resolve, reject) => {
                const request = db.transaction('outbox', 'readonly').objectStore('outbox').getAll();
                request.onsuccess = () => { db.close(); resolve(request.result[0]?.action || null); };
                request.onerror = () => reject(request.error);
            });
        });
        assert.equal(queuedAction, '/income', 'offline queue must store a same-origin path, not an APP_URL-derived absolute URL');

        const offlineShell = await page.evaluate(async () => {
            const registrations = await navigator.serviceWorker.getRegistrations();
            const cacheNames = await caches.keys();
            const cacheEntries = await Promise.all(cacheNames.map(async name => {
                const cache = await caches.open(name);
                return { name, keys: (await cache.keys()).map(request => new URL(request.url).pathname) };
            }));
            const cachedShell = await caches.match(new URL('/offline.html', location.origin).href);
            return {
                controller: navigator.serviceWorker.controller?.scriptURL || null,
                registrations: registrations.map(registration => ({
                    scope: registration.scope,
                    active: registration.active?.state || null,
                    waiting: registration.waiting?.state || null,
                })),
                cacheEntries,
                cachedOfflineShell: Boolean(cachedShell),
            };
        });
        let offlineNavigationResponse = null;
        try {
            const response = await page.goto(baseUrl + '/dashboard', { waitUntil: 'domcontentloaded', timeout: 15000 });
            offlineNavigationResponse = response ? { status: response.status(), url: response.url() } : null;
        } catch (error) {
            offlineNavigationResponse = { error: error.message };
        }
        const offlineNavigationState = await page.evaluate(() => ({
            url: location.href,
            title: document.title,
            heading: document.querySelector('h1')?.textContent?.trim() || null,
            bodyText: document.body?.innerText?.slice(0, 500) || null,
            controller: navigator.serviceWorker.controller?.scriptURL || null,
        }));
        assert.match(offlineNavigationState.heading || '', /You are offline/i,
            `service worker must serve offline fallback; shell=${JSON.stringify(offlineShell)}; response=${JSON.stringify(offlineNavigationResponse)}; page=${JSON.stringify(offlineNavigationState)}; pageErrors=${JSON.stringify(pageErrors)}; console=${JSON.stringify(browserConsole)}`);
        console.log('PASS: a controlled service worker served the offline navigation fallback.');

        await context.setOffline(false);
        await context.close();
        context = await openContext();
        page = context.pages()[0] || await context.newPage();
        const reconnectRequests = [];
        const reconnectErrors = [];
        page.on('request', request => {
            if (['/offline/session', '/income', '/expenses'].some(path => new URL(request.url()).pathname === path)) {
                reconnectRequests.push({ method: request.method(), url: request.url() });
            }
        });
        page.on('response', response => {
            if (['/offline/session', '/income', '/expenses'].some(path => new URL(response.url()).pathname === path)) {
                reconnectRequests.push({ status: response.status(), url: response.url() });
            }
        });
        page.on('pageerror', error => reconnectErrors.push(error.message));
        await page.goto(baseUrl + '/income', { waitUntil: 'domcontentloaded' });
        await assertAuthenticated(page);
        await page.evaluate(async () => { await navigator.serviceWorker.ready; return true; });
        // Allow the page-load reconnect hook to sync first. If the browser
        // resumed before its session/outbox initialization completed, use the
        // explicit Sync now control as the supported recovery path.
        await page.waitForFunction(() => {
            const button = document.querySelector('#offline-sync-now');
            return button && !button.disabled;
        }, null, { timeout: 15000 }).catch(() => {});
        if (await outboxCount(page) > 0) {
            const syncControl = page.locator('#offline-sync-now');
            const syncState = await page.evaluate(() => ({
                online: navigator.onLine,
                state: document.querySelector('#offline-sync-state')?.textContent || null,
                detail: document.querySelector('#offline-sync-detail')?.textContent || null,
                disabled: document.querySelector('#offline-sync-now')?.disabled ?? true,
                userId: document.body.dataset.userId || null,
            }));
            assert.equal(syncState.online, true, 'reconnected browser must be online');
            assert.equal(syncState.disabled, false, `pending outbox must be eligible for a manual retry: ${JSON.stringify(syncState)}`);
            await syncControl.click();
        }
        try {
            await page.waitForFunction(async () => {
                if (!('indexedDB' in window)) return false;
                const db = await new Promise((resolve, reject) => {
                    const request = indexedDB.open('student-finance-offline', 1);
                    request.onsuccess = () => resolve(request.result);
                    request.onerror = () => reject(request.error);
                });
                return new Promise((resolve, reject) => {
                    const request = db.transaction('outbox', 'readonly').objectStore('outbox').count();
                    request.onsuccess = () => { db.close(); resolve(request.result === 0); };
                    request.onerror = () => reject(request.error);
                });
            }, null, { timeout: 20000 });
        } catch (error) {
            const syncDiagnostics = await page.evaluate(async () => ({
                online: navigator.onLine,
                state: document.querySelector('#offline-sync-state')?.textContent || null,
                detail: document.querySelector('#offline-sync-detail')?.textContent || null,
                buttonDisabled: document.querySelector('#offline-sync-now')?.disabled ?? true,
                csrfMetaPresent: Boolean(document.querySelector('meta[name="csrf-token"]')?.content),
                bodyUserId: document.body.dataset.userId || null,
                entries: await new Promise((resolve, reject) => {
                    const request = indexedDB.open('student-finance-offline', 1);
                    request.onsuccess = () => {
                        const db = request.result;
                        const read = db.transaction('outbox', 'readonly').objectStore('outbox').getAll();
                        read.onsuccess = () => { db.close(); resolve(read.result.map(entry => ({ id: entry.id, owner: entry.owner, action: entry.action, attempts: entry.attempts, status: entry.status, nextAttemptAt: entry.nextAttemptAt }))); };
                        read.onerror = () => reject(read.error);
                    };
                    request.onerror = () => reject(request.error);
                }),
            }));
            throw new Error(`reconnected browser did not clear the outbox: ${JSON.stringify(syncDiagnostics)}; requests=${JSON.stringify(reconnectRequests)}; pageErrors=${JSON.stringify(reconnectErrors)}; cause=${error.message}`);
        }
        const remainingOutbox = await outboxCount(page);
        if (remainingOutbox !== 0) {
            const syncDiagnostics = await page.evaluate(async () => ({
                online: navigator.onLine,
                state: document.querySelector('#offline-sync-state')?.textContent || null,
                detail: document.querySelector('#offline-sync-detail')?.textContent || null,
                buttonDisabled: document.querySelector('#offline-sync-now')?.disabled ?? true,
                csrfMetaPresent: Boolean(document.querySelector('meta[name="csrf-token"]')?.content),
                bodyUserId: document.body.dataset.userId || null,
                entries: await new Promise((resolve, reject) => {
                    const request = indexedDB.open('student-finance-offline', 1);
                    request.onsuccess = () => {
                        const db = request.result;
                        const read = db.transaction('outbox', 'readonly').objectStore('outbox').getAll();
                        read.onsuccess = () => { db.close(); resolve(read.result.map(entry => ({ id: entry.id, owner: entry.owner, action: entry.action, attempts: entry.attempts, status: entry.status, nextAttemptAt: entry.nextAttemptAt }))); };
                        read.onerror = () => reject(read.error);
                    };
                    request.onerror = () => reject(request.error);
                }),
            }));
            throw new Error(`outbox remained after reconnect acknowledgement wait: ${JSON.stringify(syncDiagnostics)}; requests=${JSON.stringify(reconnectRequests)}; pageErrors=${JSON.stringify(reconnectErrors)}`);
        }
        console.log('PASS: pending transaction survived browser-context restart and reconnect sync.');
    } finally {
        if (context) await context.close();
        fs.rmSync(profileDir, { recursive: true, force: true });
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
