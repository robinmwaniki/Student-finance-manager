const assert = require('node:assert/strict');
const { chromium, firefox, webkit } = require('playwright');

const browserName = process.env.SFM_BROWSER_ENGINE || 'chromium';
const browserType = ({ chromium, firefox, webkit })[browserName];
if (!browserType) throw new Error(`Unsupported SFM_BROWSER_ENGINE: ${browserName}`);

const baseUrl = (process.env.SFM_BROWSER_BASE_URL || '').replace(/\/$/, '');
const sessionCookie = process.env.SFM_BROWSER_SESSION_COOKIE || '';
const submissionToken = process.env.SFM_OFFLINE_E2E_TOKEN || '';
for (const [name, value] of Object.entries({
    SFM_BROWSER_BASE_URL: baseUrl,
    SFM_BROWSER_SESSION_COOKIE: sessionCookie,
    SFM_OFFLINE_E2E_TOKEN: submissionToken,
})) {
    if (!value) throw new Error(`Missing required environment variable: ${name}`);
}

(async () => {
    const browser = await browserType.launch({
        headless: true,
        ...(browserName === 'chromium' ? { args: ['--no-sandbox'] } : {}),
    });
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const page = await context.newPage();
    const failedTransactionRequests = [];
    const pageErrors = [];

    try {
        await context.addCookies([{
            name: 'sfm-http-integration-session',
            value: sessionCookie,
            domain: '127.0.0.1',
            path: '/',
            expires: Math.floor(Date.now() / 1000) + 3600,
            httpOnly: true,
            secure: false,
            sameSite: 'Lax',
        }]);

        // Simulate browser privacy settings or storage policy denying IndexedDB.
        // The property remains present, but accessing it raises SecurityError.
        await context.addInitScript(() => {
            Object.defineProperty(window, 'indexedDB', {
                configurable: true,
                get() {
                    throw new DOMException('IndexedDB access denied by browser policy', 'SecurityError');
                },
            });
        });

        page.on('pageerror', error => pageErrors.push(error.message));
        page.on('request', request => {
            if (request.method() === 'POST' && ['/income', '/expenses'].includes(new URL(request.url()).pathname)) {
                failedTransactionRequests.push(request.url());
            }
        });

        await page.goto(baseUrl + '/income', { waitUntil: 'domcontentloaded' });
        const session = await page.request.get(baseUrl + '/offline/session');
        assert.equal(session.status(), 200, 'the synthetic browser session must be authenticated');

        await page.waitForFunction(() =>
            document.querySelector('#offline-sync-state')?.textContent === 'Offline queue unavailable'
        );
        const storageState = await page.evaluate(() => ({
            state: document.querySelector('#offline-sync-state')?.textContent || '',
            detail: document.querySelector('#offline-sync-detail')?.textContent || '',
            syncDisabled: document.querySelector('#offline-sync-now')?.disabled ?? false,
            liveRegion: document.querySelector('#offline-sync-detail')?.getAttribute('aria-live') || null,
            liveRole: document.querySelector('#offline-sync-detail')?.getAttribute('role') || null,
        }));
        assert.match(storageState.detail, /could not open local storage/i,
            `the UI must explain that browser storage is unavailable: ${JSON.stringify(storageState)}`);
        assert.equal(storageState.syncDisabled, true, 'sync must remain disabled when the outbox cannot be opened');
        assert.equal(storageState.liveRole, 'status', 'storage failure detail must use a status role');
        assert.equal(storageState.liveRegion, 'polite', 'storage failure must be announced through the status live region');

        const form = page.locator('form[action$="/income"]').first();
        await form.locator('input[name="source"]').fill('Storage Denial Synthetic Entry');
        await form.locator('input[name="amount"]').fill('19.50');
        const date = form.locator('input[name="transaction_date"]');
        await date.fill(await date.getAttribute('max'));
        await form.locator('select[name="wallet_id"]').selectOption({ index: 0 });
        await form.locator('input[name="submission_token"]').evaluate((element, token) => {
            element.value = token;
            element.dispatchEvent(new Event('input', { bubbles: true }));
            element.dispatchEvent(new Event('change', { bubbles: true }));
        }, submissionToken);

        await context.setOffline(true);
        await form.locator('button[type="submit"]').first().click();
        await page.waitForFunction(() =>
            /Local storage is unavailable\. This transaction was not saved\./.test(
                document.querySelector('#offline-sync-detail')?.textContent || ''
            )
        );

        assert.deepEqual(failedTransactionRequests, [],
            'a transaction must not be sent to the server when offline storage is denied');
        assert.deepEqual(pageErrors, [], 'storage denial must not produce uncaught browser errors');
        console.log(`PASS (${browserName}): IndexedDB denial is announced and offline financial submission is safely refused.`);
    } finally {
        await context.close();
        await browser.close();
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
