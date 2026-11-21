const assert = require('node:assert/strict');
const test = require('node:test');
const policy = require('../../public/offline-retry-policy.js');

test('automatic retry delay uses capped exponential backoff', () => {
    assert.deepEqual(
        [1, 2, 3, 4, 5, 6, 7, 8].map(policy.retryDelayMs),
        [5000, 10000, 20000, 40000, 80000, 160000, 300000, 300000],
    );
    assert.equal(policy.retryDelayMs(20), policy.MAX_DELAY_MS);
});

test('only transient network and server failures are automatically retried', () => {
    for (const status of [408, 429, 500, 502, 503, 599]) {
        assert.equal(policy.shouldRetryAutomatically(1, status, false), true);
    }

    for (const status of [400, 401, 403, 404, 409, 419, 422]) {
        assert.equal(policy.shouldRetryAutomatically(1, status, false), false);
    }

    assert.equal(policy.shouldRetryAutomatically(1, undefined, true), true);
});

test('automatic retries stop at the configured attempt limit', () => {
    assert.equal(policy.MAX_AUTOMATIC_ATTEMPTS, 8);
    assert.equal(policy.shouldRetryAutomatically(7, 503, false), true);
    assert.equal(policy.shouldRetryAutomatically(8, 503, false), false);
    assert.equal(policy.shouldRetryAutomatically(9, undefined, true), false);
});
