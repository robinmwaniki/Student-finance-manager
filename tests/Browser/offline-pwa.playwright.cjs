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
        await page.goto(baseUrl + '/income', { waitUntil: 'domcontentloaded' });
        await assertAuthenticated(page);
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

        await context.setOffline(true);
        await incomeForm.locator('button[type="submit"]').click();
        await page.locator('#offline-sync-detail').filter({ hasText: 'Saved on this device' }).waitFor({ timeout: 10000 });
        assert.equal(await outboxCount(page), 1, 'offline transaction must be persisted in IndexedDB');

        await page.goto(baseUrl + '/dashboard', { waitUntil: 'domcontentloaded', timeout: 15000 });
        await page.getByRole('heading', { name: 'You are offline' }).waitFor({ timeout: 10000 });
        console.log('PASS: a controlled service worker served the offline navigation fallback.');

        await context.setOffline(false);
        await context.close();
        context = await openContext();
        page = context.pages()[0] || await context.newPage();
        await page.goto(baseUrl + '/income', { waitUntil: 'domcontentloaded' });
        await assertAuthenticated(page);
        await page.evaluate(async () => { await navigator.serviceWorker.ready; return true; });
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
        assert.equal(await outboxCount(page), 0, 'reconnected browser must clear the outbox only after successful server acknowledgement');
        console.log('PASS: pending transaction survived browser-context restart and reconnect sync.');
    } finally {
        if (context) await context.close();
        fs.rmSync(profileDir, { recursive: true, force: true });
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
