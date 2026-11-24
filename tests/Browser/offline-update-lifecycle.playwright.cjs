const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { chromium, firefox, webkit } = require('playwright');

const browserName = process.env.SFM_BROWSER_ENGINE || 'chromium';
const browserType = ({ chromium, firefox, webkit })[browserName];
if (!browserType) throw new Error(`Unsupported SFM_BROWSER_ENGINE: ${browserName}`);

const source = fs.readFileSync(path.resolve(__dirname, '../../public/sw.js'), 'utf8');
if (!source.includes("const CACHE_NAME = 'sfm-shell-v3'")) {
    throw new Error('The baseline service-worker cache version changed; update this lifecycle test deliberately.');
}
const updatedSource = source.replace("const CACHE_NAME = 'sfm-shell-v3'", "const CACHE_NAME = 'sfm-shell-v4'");

(async () => {
    let workerMode = 'baseline';
    const server = http.createServer((request, response) => {
        const url = new URL(request.url, 'http://127.0.0.1');
        response.setHeader('Cache-Control', 'no-store');
        if (url.pathname === '/sw.js') {
            if (workerMode === 'fail') {
                response.writeHead(503, { 'Content-Type': 'text/plain' });
                response.end('simulated update download failure');
                return;
            }
            response.writeHead(200, { 'Content-Type': 'application/javascript; charset=utf-8' });
            response.end(workerMode === 'updated' ? updatedSource : source);
            return;
        }
        if (url.pathname === '/offline.html') {
            response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            response.end('<!doctype html><html lang="en"><meta charset="utf-8"><title>Offline</title><h1>Offline</h1>');
            return;
        }
        if (url.pathname === '/manifest.webmanifest') {
            response.writeHead(200, { 'Content-Type': 'application/manifest+json' });
            response.end(JSON.stringify({ name: 'Offline lifecycle test', short_name: 'Offline test', start_url: '/' }));
            return;
        }
        if (url.pathname === '/icons/icon.svg') {
            response.writeHead(200, { 'Content-Type': 'image/svg+xml' });
            response.end('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><circle cx=".5" cy=".5" r=".5"/></svg>');
            return;
        }
        if (url.pathname === '/offline-transactions.js' || url.pathname === '/offline-retry-policy.js') {
            response.writeHead(200, { 'Content-Type': 'application/javascript' });
            response.end('// lifecycle fixture');
            return;
        }
        response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        response.end('<!doctype html><html lang="en"><meta charset="utf-8"><title>Worker lifecycle</title><body>Service worker update test</body><script>navigator.serviceWorker.register("/sw.js");</script></html>');
    });

    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const baseUrl = `http://127.0.0.1:${server.address().port}`;
    const browser = await browserType.launch({
        headless: true,
        ...(browserName === 'chromium' ? { args: ['--no-sandbox'] } : {}),
    });
    const context = await browser.newContext({ serviceWorkers: 'allow' });
    try {
        const page = await context.newPage();
        const pageErrors = [];
        page.on('pageerror', error => pageErrors.push(error.message));
        await page.goto(baseUrl, { waitUntil: 'domcontentloaded' });
        await page.waitForFunction(() => Boolean(navigator.serviceWorker.controller), null, { timeout: 15000 });
        await page.waitForFunction(async () => (await caches.keys()).includes('sfm-shell-v3'), null, { timeout: 15000 });

        workerMode = 'fail';
        await page.evaluate(async () => {
            const registration = await navigator.serviceWorker.getRegistration('/');
            await registration.update().catch(() => {});
        });
        assert.ok(await page.evaluate(async () => (await caches.keys()).includes('sfm-shell-v3')),
            'a failed update must preserve the previously active shell cache');
        assert.ok(await page.evaluate(async () => Boolean((await navigator.serviceWorker.getRegistration('/'))?.active)),
            'a failed update must not unregister the active service worker');

        workerMode = 'updated';
        await page.evaluate(async () => {
            const registration = await navigator.serviceWorker.getRegistration('/');
            await registration.update();
        });
        await page.waitForFunction(async () => {
            const keys = await caches.keys();
            return keys.includes('sfm-shell-v4') && !keys.includes('sfm-shell-v3');
        }, null, { timeout: 20000 });

        const finalState = await page.evaluate(async () => ({
            caches: await caches.keys(),
            controller: Boolean(navigator.serviceWorker.controller),
            activeState: (await navigator.serviceWorker.getRegistration('/'))?.active?.state || null,
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
        await new Promise(resolve => server.close(resolve));
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
