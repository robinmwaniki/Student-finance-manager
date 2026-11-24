const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium, firefox, webkit } = require('playwright');

const browserName = process.env.SFM_BROWSER_ENGINE || 'chromium';
const browserType = ({ chromium, firefox, webkit })[browserName];
if (!browserType) throw new Error(`Unsupported SFM_BROWSER_ENGINE: ${browserName}`);

const baseUrl = (process.env.SFM_BROWSER_BASE_URL || '').replace(/\/$/, '');
const sessionCookie = process.env.SFM_BROWSER_SESSION_COOKIE || '';
for (const [name, value] of Object.entries({
    SFM_BROWSER_BASE_URL: baseUrl,
    SFM_BROWSER_SESSION_COOKIE: sessionCookie,
})) {
    if (!value) throw new Error(`Missing required environment variable: ${name}`);
}

const source = fs.readFileSync(path.resolve(__dirname, '../../public/sw.js'), 'utf8');
if (!source.includes("const CACHE_NAME = 'sfm-shell-v3'")) {
    throw new Error('The baseline service-worker cache version changed; update this lifecycle test deliberately.');
}
const updatedSource = source.replace("const CACHE_NAME = 'sfm-shell-v3'", "const CACHE_NAME = 'sfm-shell-v4'");

(async () => {
    const browser = await browserType.launch({
        headless: true,
        ...(browserName === 'chromium' ? { args: ['--no-sandbox'] } : {}),
    });
    const context = await browser.newContext({
        viewport: { width: 1280, height: 800 },
        serviceWorkers: 'allow',
    });
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
        const page = await context.newPage();
        const pageErrors = [];
        page.on('pageerror', error => pageErrors.push(error.message));
        await page.goto(baseUrl + '/income', { waitUntil: 'domcontentloaded' });

        const registration = await page.evaluate(async () => {
            await navigator.serviceWorker.ready;
            return Boolean(await navigator.serviceWorker.getRegistration(location.origin + '/'));
        });
        assert.ok(registration, 'the app must register a service worker');
        await page.waitForFunction(() => Boolean(navigator.serviceWorker.controller), null, { timeout: 15000 });
        await page.waitForFunction(async () => (await caches.keys()).includes('sfm-shell-v3'), null, { timeout: 15000 });

        // A failed update download must leave the currently active worker/cache intact.
        await context.route('**/sw.js', route => route.abort('failed'));
        await page.evaluate(async () => {
            const current = await navigator.serviceWorker.getRegistration(location.origin + '/');
            await current.update().catch(() => {});
        });
        assert.ok(await page.evaluate(async () => (await caches.keys()).includes('sfm-shell-v3')),
            'a failed update must preserve the previously active shell cache');
        assert.ok(await page.evaluate(async () => Boolean((await navigator.serviceWorker.getRegistration(location.origin + '/'))?.active)),
            'a failed update must not unregister the active service worker');

        // Serve a new worker version at the same URL to exercise the browser's real update algorithm.
        await context.unroute('**/sw.js');
        await context.route('**/sw.js', route => route.fulfill({
            status: 200,
            contentType: 'application/javascript; charset=utf-8',
            body: updatedSource,
        }));
        await page.evaluate(async () => {
            const current = await navigator.serviceWorker.getRegistration(location.origin + '/');
            await current.update();
        });
        await page.waitForFunction(async () => {
            const keys = await caches.keys();
            return keys.includes('sfm-shell-v4') && !keys.includes('sfm-shell-v3');
        }, null, { timeout: 20000 });

        const finalState = await page.evaluate(async () => ({
            caches: await caches.keys(),
            controller: Boolean(navigator.serviceWorker.controller),
            activeState: (await navigator.serviceWorker.getRegistration(location.origin + '/'))?.active?.state || null,
            shellCached: Boolean(await (await caches.open('sfm-shell-v4')).match('/offline.html')),
        }));
        assert.ok(finalState.caches.includes('sfm-shell-v4'), `updated worker cache must exist: ${JSON.stringify(finalState)}`);
        assert.equal(finalState.caches.includes('sfm-shell-v3'), false,
            `activation must remove the previous app-owned cache: ${JSON.stringify(finalState)}`);
        assert.equal(finalState.controller, true, 'the updated worker must control the page');
        assert.equal(finalState.activeState, 'activated', 'the updated worker must be activated');
        assert.equal(finalState.shellCached, true, 'the updated worker must precache its offline fallback');
        assert.deepEqual(pageErrors, [], 'service-worker update must not create uncaught page errors');
        console.log(`PASS (${browserName}): failed update preserves the active worker; successful update activates v4 and removes the stale shell cache.`);
    } finally {
        await context.close();
        await browser.close();
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
