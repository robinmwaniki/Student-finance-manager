(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    } else {
        root.QrPayload = api;
    }
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    const MAX_BYTES = 1000;
    const sensitiveField = /(?:^|[\s&?;])(?:password|passwd|passcode|pin|otp|one[-_ ]time[-_ ]password|secret|api[_-]?key|access[_-]?token|refresh[_-]?token|session(?:[_-]?(?:id|token))?|private[_-]?key|authorization)\s*[:=]/i;
    const sensitiveQueryKeys = new Set([
        'password', 'passwd', 'passcode', 'pin', 'otp', 'secret', 'token', 'auth',
        'api_key', 'apikey', 'access_token', 'refresh_token',
        'session', 'session_id', 'session_token', 'authorization',
        'private_key', 'credential', 'account_number', 'card_number', 'cvv', 'cvc', 'sessionid',
    ]);

    function byteLength(value) {
        if (typeof TextEncoder !== 'undefined') {
            return new TextEncoder().encode(value).length;
        }

        return unescape(encodeURIComponent(value)).length;
    }

    function validatePayload(payload, confirmedSafe) {
        if (typeof payload !== 'string' || payload.trim().length === 0) {
            return { valid: false, error: 'Enter content before generating a QR code.' };
        }

        const bytes = byteLength(payload);
        if (bytes > MAX_BYTES) {
            return { valid: false, error: 'The QR payload is too large. Keep it to 1,000 UTF-8 bytes or fewer.', bytes };
        }

        if (/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/.test(payload)) {
            return { valid: false, error: 'The content contains unsupported control characters.', bytes };
        }

        if (sensitiveField.test(payload) || /-----BEGIN [A-Z ]*PRIVATE KEY-----/i.test(payload)) {
            return { valid: false, error: 'This looks like a password, PIN, token, secret or private key. Remove confidential information before generating a QR code.', bytes };
        }

        if (!confirmedSafe) {
            return { valid: false, error: 'Confirm that the content contains no credentials or confidential financial information.', bytes };
        }

        return { valid: true, payload, bytes };
    }

    function buildUrl(value) {
        let parsed;
        try {
            parsed = new URL(String(value || '').trim());
        } catch {
            return { valid: false, error: 'Enter a valid web address beginning with https:// or http://.' };
        }

        if (!['https:', 'http:'].includes(parsed.protocol) || !parsed.hostname || parsed.username || parsed.password) {
            return { valid: false, error: 'Only public HTTP or HTTPS links without embedded usernames or passwords are allowed.' };
        }

        for (const key of parsed.searchParams.keys()) {
            if (sensitiveQueryKeys.has(key.toLowerCase().replaceAll('-', '_'))) {
                return { valid: false, error: 'Remove password, token, session or credential parameters from the link.' };
            }
        }

        return { valid: true, payload: parsed.toString() };
    }

    function buildPaymentReference(fields) {
        const method = fields.method === 'till' ? 'Till' : 'Paybill';
        const code = String(fields.code || '').trim();
        const reference = String(fields.reference || '').trim();
        const amount = String(fields.amount || '').trim();

        if (!/^\d{5,10}$/.test(code)) {
            return { valid: false, error: 'Enter a 5–10 digit Paybill or Till number.' };
        }

        if (!/^[A-Za-z0-9][A-Za-z0-9 ./_-]{0,39}$/.test(reference)) {
            return { valid: false, error: 'Enter an account or payment reference using up to 40 letters, numbers, spaces, dots, slashes, underscores or hyphens.' };
        }

        if (amount && (!/^(?:0|[1-9]\d{0,8})(?:\.\d{1,2})?$/.test(amount) || Number(amount) <= 0)) {
            return { valid: false, error: 'Enter an optional positive KES amount with up to two decimal places.' };
        }

        const payload = [
            'PAYMENT REFERENCE — NOT A PAYMENT',
            'Method: M-Pesa ' + method,
            method + ': ' + code,
            'Account / Reference: ' + reference,
            'Amount: ' + (amount ? 'KES ' + Number(amount).toFixed(2) : 'Not specified'),
            'This QR only shares reference details. It does not initiate a payment.',
        ].join('\n');

        return { valid: true, payload };
    }

    function buildPayload(type, fields, confirmedSafe) {
        let result;

        if (type === 'text') {
            result = { valid: true, payload: String(fields.text || '') };
        } else if (type === 'url') {
            result = buildUrl(fields.url);
        } else if (type === 'payment') {
            result = buildPaymentReference(fields);
        } else {
            return { valid: false, error: 'Choose a supported QR content type.' };
        }

        if (!result.valid) {
            return result;
        }

        return validatePayload(result.payload, confirmedSafe);
    }

    return { MAX_BYTES, validatePayload, buildPayload };
});
