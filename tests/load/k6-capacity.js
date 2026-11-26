import http from 'k6/http';
import { check, sleep } from 'k6';

// This test is intentionally opt-in and allowlist-protected. It must only target
// a disposable staging deployment with synthetic accounts and synthetic data.
const BASE_URL = (__ENV.BASE_URL || '').replace(/\/$/, '');
const CONFIRMED_HOST = (__ENV.STAGING_HOST || '').trim().toLowerCase();
const MODE = (__ENV.MODE || 'read-only').toLowerCase();
const VUS = Number(__ENV.VUS || 5);
const DURATION = __ENV.DURATION || '2m';
const THINK_TIME = Number(__ENV.THINK_TIME_SECONDS || 1);
const FROM_WALLET_ID = (__ENV.TRANSFER_FROM_WALLET_ID || '').trim();
const TO_WALLET_ID = (__ENV.TRANSFER_TO_WALLET_ID || '').trim();
const TRANSFER_AMOUNT = (__ENV.TRANSFER_AMOUNT || '0.01').trim();

function assertSafeTarget() {
    if (__ENV.CONFIRM_STAGING !== 'true') {
        throw new Error('Refusing to run: set CONFIRM_STAGING=true only after verifying this is an isolated staging deployment.');
    }
    if (!BASE_URL || !CONFIRMED_HOST) {
        throw new Error('Set BASE_URL and STAGING_HOST to the exact isolated staging hostname.');
    }

    let parsed;
    try {
        parsed = new URL(BASE_URL);
    } catch {
        throw new Error('BASE_URL must be a valid absolute URL.');
    }

    if (parsed.protocol !== 'https:' && parsed.hostname !== 'localhost' && parsed.hostname !== '127.0.0.1') {
        throw new Error('Non-local staging targets must use HTTPS.');
    }
    if (parsed.hostname.toLowerCase() !== CONFIRMED_HOST) {
        throw new Error('BASE_URL hostname must exactly match STAGING_HOST. Wildcards and production aliases are not accepted.');
    }
    if (parsed.username || parsed.password || parsed.search || parsed.hash) {
        throw new Error('BASE_URL must not contain credentials, query parameters or a fragment.');
    }
    if (!['read-only', 'mixed', 'wallet-transfer'].includes(MODE)) {
        throw new Error('MODE must be read-only, mixed or wallet-transfer.');
    }
    if (!Number.isInteger(VUS) || VUS < 1 || VUS > 1000) {
        throw new Error('VUS must be an integer from 1 to 1000 per generator. Use distributed generators for larger tests.');
    }
    if (!Number.isFinite(THINK_TIME) || THINK_TIME < 0) {
        throw new Error('THINK_TIME_SECONDS must be zero or greater.');
    }
    if (MODE !== 'read-only' && __ENV.ALLOW_SYNTHETIC_WRITES !== 'true') {
        throw new Error('Write modes require ALLOW_SYNTHETIC_WRITES=true and a dedicated synthetic staging account.');
    }
    if (MODE === 'wallet-transfer') {
        if (!FROM_WALLET_ID || !TO_WALLET_ID || FROM_WALLET_ID === TO_WALLET_ID) {
            throw new Error('Wallet-transfer mode requires two different synthetic wallet IDs.');
        }
        if (!/^\d+(\.\d{1,2})?$/.test(TRANSFER_AMOUNT) || Number(TRANSFER_AMOUNT) <= 0) {
            throw new Error('TRANSFER_AMOUNT must be a positive amount with at most two decimal places.');
        }
    }
}

assertSafeTarget();

const EMAIL = __ENV.TEST_EMAIL || '';
const PASSWORD = __ENV.TEST_PASSWORD || '';
let authenticated = false;
if (!EMAIL || !PASSWORD) {
    throw new Error('Set TEST_EMAIL and TEST_PASSWORD for a dedicated synthetic account.');
}

export const options = {
    scenarios: {
        authenticated_sessions: {
            executor: 'constant-vus',
            vus: VUS,
            duration: DURATION,
            gracefulStop: '30s',
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.02'],
        http_req_duration: ['p(95)<1500', 'p(99)<3000'],
        checks: ['rate>0.98'],
    },
    userAgent: 'student-finance-manager-capacity-test/1.0',
};

const READ_PATHS = [
    '/reports',
    '/income',
    '/expenses',
    '/wallets',
    '/budgets',
    '/savings',
    '/debts',
    '/recurring',
    '/notifications',
];

function extract(pattern, body, label) {
    const match = String(body).match(pattern);
    if (!match) {
        throw new Error('Could not extract ' + label + ' from the staging page.');
    }
    return match[1];
}

function authenticatedPage(path) {
    const response = http.get(BASE_URL + path, {
        redirects: 0,
        tags: { endpoint: path, operation: 'read' },
    });
    const ok = response.status === 200
        && response.url.endsWith(path)
        && ![302, 401, 403, 419].includes(response.status)
        && (path !== '/dashboard' || String(response.body).includes('Financial overview'));

    if (!ok) {
        authenticated = false;
    } else {
        authenticated = true;
    }

    check(response, { [path + ' returns authenticated HTTP 200']: () => ok });

    return response;
}

function login() {
    const page = http.get(BASE_URL + '/login', {
        redirects: 0,
        tags: { endpoint: '/login', operation: 'login-page' },
    });
    check(page, { 'login page returns HTTP 200': response => response.status === 200 });
    if (page.status !== 200) {
        throw new Error('Staging login page failed with HTTP ' + page.status);
    }

    const csrf = extract(/name="csrf-token" content="([^"]+)"/, page.body, 'CSRF token');
    const response = http.post(BASE_URL + '/login', {
        _token: csrf,
        email: EMAIL,
        password: PASSWORD,
    }, {
        redirects: 0,
        tags: { endpoint: '/login', operation: 'login' },
    });

    if (![200, 302, 303].includes(response.status)) {
        throw new Error('Synthetic account login failed with HTTP ' + response.status);
    }

    const dashboard = authenticatedPage('/dashboard');
    if (dashboard.status !== 200 || !authenticated) {
        throw new Error('Synthetic account did not reach the authenticated dashboard.');
    }
}

function dashboardRead() {
    return authenticatedPage('/dashboard');
}

function walletTransfer() {
    const page = authenticatedPage('/wallets');
    if (page.status !== 200) return;

    const csrf = extract(/name="csrf-token" content="([^"]+)"/, page.body, 'CSRF token');
    const token = extract(/name="submission_token" value="([^"]+)"/, page.body, 'wallet transfer submission token');
    const date = extract(/name="transfer_date"[^>]*value="([^"]+)"/, page.body, 'wallet transfer date');
    const note = 'k6 synthetic capacity test VU ' + __VU + ' iteration ' + __ITER;
    const response = http.post(BASE_URL + '/wallets/transfers', {
        _token: csrf,
        submission_token: token,
        from_wallet_id: FROM_WALLET_ID,
        to_wallet_id: TO_WALLET_ID,
        amount: TRANSFER_AMOUNT,
        transfer_date: date,
        note,
    }, {
        redirects: 0,
        headers: { Accept: 'text/html' },
        tags: { endpoint: '/wallets/transfers', operation: 'synthetic-transfer' },
    });

    // Laravel redirects after successful HTML form submissions. Verify the
    // unique note appears in the transfer history so a validation redirect
    // cannot be mistaken for a successful write.
    const redirected = [302, 303].includes(response.status);
    const refreshed = http.get(BASE_URL + '/wallets', {
        redirects: 0,
        tags: { endpoint: '/wallets', operation: 'verify-transfer-page' },
    });
    const transferVisible = refreshed.status === 200 && String(refreshed.body).includes(note);
    check(response, {
        'wallet transfer form redirects after submission': () => redirected,
    });
    check(refreshed, {
        'synthetic transfer appears in wallet history': () => transferVisible,
    });
}

export default function () {
    // k6 maintains an isolated cookie jar per VU. Authenticate once and keep
    // the session alive across iterations to model connected users rather
    // than generating a fresh login storm on every request cycle.
    if (!authenticated) {
        login();
    }

    dashboardRead();
    if (!authenticated) {
        sleep(THINK_TIME);
        return;
    }

    if (MODE === 'mixed') {
        const path = READ_PATHS[Math.floor(Math.random() * READ_PATHS.length)];
        authenticatedPage(path);
    } else if (MODE === 'wallet-transfer') {
        walletTransfer();
    }

    sleep(THINK_TIME);
}
