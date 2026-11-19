const test = require('node:test');
const assert = require('node:assert/strict');
const { buildPayload, validatePayload, MAX_BYTES } = require('../../public/js/qr-payload.js');

test('safe text is accepted only after the privacy confirmation', () => {
    const accepted = buildPayload('text', { text: 'Hello from my finance workspace.' }, true);
    const unconfirmed = buildPayload('text', { text: 'Hello from my finance workspace.' }, false);

    assert.equal(accepted.valid, true);
    assert.equal(accepted.payload, 'Hello from my finance workspace.');
    assert.equal(unconfirmed.valid, false);
    assert.match(unconfirmed.error, /Confirm/);
});

test('empty, oversized, control-character and credential-like payloads are rejected', () => {
    assert.equal(validatePayload('', true).valid, false);
    assert.equal(validatePayload('x'.repeat(MAX_BYTES + 1), true).valid, false);
    assert.equal(validatePayload('Line\u0000break', true).valid, false);
    assert.equal(validatePayload('password=correct-horse-battery-staple', true).valid, false);
    assert.equal(validatePayload('My PIN is 1234', true).valid, false);
    assert.equal(validatePayload('-----BEGIN PRIVATE KEY-----abc', true).valid, false);
});

test('UTF-8 byte length is enforced rather than JavaScript character count', () => {
    const result = validatePayload('🙂'.repeat(251), true);

    assert.equal(result.valid, false);
    assert.ok(result.bytes > MAX_BYTES);
});

test('public HTTP and HTTPS links are accepted and unsafe URLs are rejected', () => {
    const valid = buildPayload('url', { url: 'https://example.test/payment?ref=INV-123' }, true);

    assert.equal(valid.valid, true);
    assert.equal(valid.payload, 'https://example.test/payment?ref=INV-123');
    assert.equal(buildPayload('url', { url: 'javascript:alert(1)' }, true).valid, false);
    assert.equal(buildPayload('url', { url: 'https://user:pass@example.test' }, true).valid, false);
    assert.equal(buildPayload('url', { url: 'https://example.test/?access_token=abc' }, true).valid, false);
    assert.equal(buildPayload('url', { url: 'https://example.test/?token=abc' }, true).valid, false);
    assert.equal(buildPayload('url', { url: 'https://example.test/?session-id=abc' }, true).valid, false);
    assert.equal(buildPayload('url', { url: 'https://example.test/#access_token=abc' }, true).valid, false);
});

test('M-Pesa payment references create a clear non-payment instruction payload', () => {
    const result = buildPayload('payment', {
        method: 'paybill',
        code: '123456',
        reference: 'INV-2026-0042',
        amount: '250.50',
    }, true);

    assert.equal(result.valid, true);
    assert.match(result.payload, /PAYMENT REFERENCE — NOT A PAYMENT/);
    assert.match(result.payload, /Paybill: 123456/);
    assert.match(result.payload, /Account \/ Reference: INV-2026-0042/);
    assert.match(result.payload, /Amount: KES 250\.50/);
    assert.match(result.payload, /does not initiate a payment/);
});

test('payment references reject invalid merchant codes, references and amounts', () => {
    const fields = { method: 'till', code: '1234', reference: 'ORDER-1', amount: '10.00' };
    assert.equal(buildPayload('payment', fields, true).valid, false);
    assert.equal(buildPayload('payment', { ...fields, method: 'bank-transfer', code: '123456' }, true).valid, false);
    assert.equal(buildPayload('payment', { ...fields, code: '123456', reference: 'bad<>ref' }, true).valid, false);
    assert.equal(buildPayload('payment', { ...fields, code: '123456', amount: '-10' }, true).valid, false);
    assert.equal(buildPayload('payment', { ...fields, code: '123456', amount: '10.999' }, true).valid, false);
});

test('unknown content types are rejected', () => {
    assert.equal(buildPayload('credential', { text: 'secret' }, true).valid, false);
});

test('browser generator renders a QR preview and exposes a local image download', () => {
    const fs = require('node:fs');
    const path = require('node:path');
    const vm = require('node:vm');
    const source = fs.readFileSync(path.join(__dirname, '../../public/js/qr-generator.js'), 'utf8');
    const ids = [
        'qr-generator-form', 'qr-content-type', 'qr-safe-confirmation', 'qr-error-level',
        'qr-status', 'qr-preview', 'qr-preview-panel', 'qr-preview-empty', 'qr-download',
        'qr-byte-count', 'qr-text', 'qr-url', 'qr-payment-method', 'qr-payment-code',
        'qr-payment-reference', 'qr-payment-amount',
    ];
    const elements = Object.fromEntries(ids.map(id => [id, {
        id,
        value: id === 'qr-content-type' ? 'text' : id === 'qr-error-level' ? 'M' : id === 'qr-text' ? 'Browser-local QR payload' : '',
        checked: id === 'qr-safe-confirmation',
        hidden: false,
        dataset: {},
        listeners: {},
        textContent: '',
        addEventListener(name, callback) { this.listeners[name] = callback; },
        removeAttribute(name) { delete this[name]; },
    }]));
    const panels = ['text', 'url', 'payment'].map(name => ({ dataset: { qrPanel: name }, hidden: false }));
    let encodedPayload = null;
    let made = false;
    const document = {
        getElementById(id) { return elements[id]; },
        querySelectorAll() { return panels; },
    };
    const window = {
        QrPayload: require('../../public/js/qr-payload.js'),
        qrcode(type, level) {
            assert.equal(type, 0);
            assert.equal(level, 'M');
            return {
                addData(value) { encodedPayload = value; },
                make() { made = true; },
                createDataURL() { return 'data:image/gif;base64,QUJD'; },
            };
        },
        setTimeout(callback) { callback(); },
    };

    vm.runInNewContext(source, { document, window, console });
    let prevented = false;
    elements['qr-generator-form'].listeners.submit({ preventDefault() { prevented = true; } });

    assert.equal(prevented, true);
    assert.equal(encodedPayload, 'Browser-local QR payload');
    assert.equal(made, true);
    assert.equal(elements['qr-preview-panel'].hidden, false);
    assert.equal(elements['qr-preview-empty'].hidden, true);
    assert.equal(elements['qr-preview'].src, 'data:image/gif;base64,QUJD');
    assert.equal(elements['qr-download'].href, 'data:image/gif;base64,QUJD');
    assert.equal(elements['qr-download'].download, 'student-finance-qr-code.gif');
    assert.equal(elements['qr-download'].hidden, false);
    assert.match(elements['qr-byte-count'].textContent, /UTF-8 bytes/);
});
