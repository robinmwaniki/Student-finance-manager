const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');
const serviceWorker = fs.readFileSync(path.join(root, 'public/sw.js'), 'utf8');
const offlineClient = fs.readFileSync(path.join(root, 'public/offline-transactions.js'), 'utf8');

test('PWA scripts are valid JavaScript', () => {
    assert.doesNotThrow(() => new vm.Script(serviceWorker, { filename: 'sw.js' }));
    assert.doesNotThrow(() => new vm.Script(offlineClient, { filename: 'offline-transactions.js' }));
});

test('service worker uses an explicit static-only same-origin cache policy', () => {
    assert.match(serviceWorker, /const SHELL_FILES = \['\/offline\.html', '\/manifest\.webmanifest', '\/offline-transactions\.js', '\/icons\/icon\.svg'\]/);
    assert.match(serviceWorker, /request\.method !== 'GET' \|\| url\.origin !== self\.location\.origin/);
    assert.match(serviceWorker, /Never cache authenticated HTML, API responses, POST requests, or cross-origin data/);
    assert.match(serviceWorker, /request\.mode === 'navigate'/);
    assert.doesNotMatch(serviceWorker, /caches\.put\(/);
});

test('offline outbox is account scoped and never stores credentials or CSRF tokens', () => {
    assert.match(offlineClient, /indexedDB\.open\('student-finance-offline'/);
    assert.match(offlineClient, /owner:String\(userId\)/);
    assert.match(offlineClient, /String\(entry\.owner\) === String\(userId\)/);
    assert.match(offlineClient, /key !== '_token' && key !== '_method'/);
    assert.match(offlineClient, /payload\.submission_token/);
    assert.match(offlineClient, /response\.status === 401 \|\| response\.status === 419/);
    assert.match(offlineClient, /data-edit/);
    assert.match(offlineClient, /data-discard/);
});
