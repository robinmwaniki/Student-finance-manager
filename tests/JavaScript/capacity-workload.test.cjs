const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const workload = fs.readFileSync(
    path.resolve(__dirname, '../load/k6-capacity.js'),
    'utf8',
);

test('capacity workload reuses one authenticated session per virtual user', () => {
    assert.match(workload, /let authenticated = false;/);
    assert.match(workload, /if \(!authenticated\)\s*\{\s*login\(\);\s*\}/);
    assert.doesNotMatch(workload, /export default function \(\)\s*\{\s*login\(\);/);
    assert.match(workload, /authenticated = false;/);
    assert.match(workload, /Financial overview/);
});

test('capacity workload remains restricted to explicitly confirmed staging hosts', () => {
    assert.match(workload, /__ENV\.CONFIRM_STAGING !== 'true'/);
    assert.match(workload, /parsed\.hostname\.toLowerCase\(\) !== CONFIRMED_HOST/);
    assert.match(workload, /parsed\.protocol !== 'https:'/);
    assert.match(workload, /VUS > 1000/);
    assert.match(workload, /ALLOW_SYNTHETIC_WRITES !== 'true'/);
});

test('capacity workload rejects invalid or excessive run durations before starting', () => {
    assert.match(workload, /function durationMilliseconds\\(value\\)/);
    assert.match(workload, /MAX_DURATION_MS = 60 \\* 60 \\* 1000/);
    assert.match(workload, /durationMs <= 0 \\|\\| durationMs > MAX_DURATION_MS/);
    assert.match(workload, /no longer than 1h per run/);
});
