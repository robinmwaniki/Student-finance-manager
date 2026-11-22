import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = (__ENV.BASE_URL || '').replace(/\/$/, '');
const EMAIL = __ENV.TEST_EMAIL || '';
const PASSWORD = __ENV.TEST_PASSWORD || '';
const MODE = __ENV.MODE || 'dashboard';
const VUS = Number(__ENV.VUS || 10);
const DURATION = __ENV.DURATION || '2m';

if (__ENV.CONFIRM_STAGING !== 'true') throw new Error('Refusing to run: set CONFIRM_STAGING=true only after verifying BASE_URL is an isolated staging deployment.');
if (!BASE_URL) throw new Error('Set BASE_URL to an isolated staging environment.');
if (!EMAIL || !PASSWORD) throw new Error('Set TEST_EMAIL and TEST_PASSWORD for a dedicated synthetic account.');
if (!['dashboard', 'mixed'].includes(MODE)) throw new Error('MODE must be dashboard or mixed.');

export const options = {
    stages: [{ duration: '30s', target: VUS }, { duration: DURATION, target: VUS }, { duration: '30s', target: 0 }],
    thresholds: { http_req_failed: ['rate<0.02'], http_req_duration: ['p(95)<1500', 'p(99)<3000'] },
};

let authenticated = false;

function extract(pattern, body, label) {
    const match = body.match(pattern);
    if (!match) throw new Error('Could not find ' + label + ' in the rendered page.');
    return match[1];
}

function login() {
    const page = http.get(BASE_URL + '/login');
    check(page, { 'login page loads': response => response.status === 200 });
    const csrf = extract(/name="csrf-token" content="([^"]+)"/, page.body, 'CSRF token');
    const response = http.post(BASE_URL + '/login', { _token: csrf, email: EMAIL, password: PASSWORD }, { redirects: 5 });
    if (response.status < 200 || response.status >= 400) throw new Error('Test account login failed: HTTP ' + response.status);
    const dashboard = http.get(BASE_URL + '/dashboard', { redirects: 0 });
    const authenticatedDashboard = dashboard.status === 200
        && dashboard.url.endsWith('/dashboard')
        && dashboard.body.includes('Financial overview');
    check(dashboard, { 'authenticated dashboard loads without redirecting to login': () => authenticatedDashboard });
    if (!authenticatedDashboard) {
        throw new Error('Test account did not reach the authenticated dashboard; check credentials, account state and staging configuration.');
    }
    authenticated = true;
}

function readSyntheticFinancePage() {
    const paths = ['/reports', '/income', '/expenses', '/wallets', '/budgets'];
    const path = paths[Math.floor(Math.random() * paths.length)];
    const response = http.get(BASE_URL + path);
    check(response, { [path + ' read succeeds']: result => result.status === 200 });
    if (response.status === 401 || response.status === 419) authenticated = false;
}

function writeSyntheticTransaction(kind) {
    const path = kind === 'income' ? '/income' : '/expenses';
    const page = http.get(BASE_URL + path);
    if (page.status !== 200) { authenticated = false; return; }
    const csrf = extract(/name="csrf-token" content="([^"]+)"/, page.body, 'CSRF token');
    const token = extract(/name="submission_token" value="([^"]+)"/, page.body, 'idempotency token');
    const wallet = page.body.match(/name="wallet_id"[^>]*>[\s\S]*?<option value="(\d+)"/);
    const payload = {
        submission_token: token, amount: '1.00',
        transaction_date: new Date(Date.now() + 3 * 60 * 60 * 1000).toISOString().slice(0, 10),
        tag: 'k6-synthetic-load-test',
    };
    if (wallet) payload.wallet_id = wallet[1];
    if (kind === 'income') payload.source = 'k6 synthetic income';
    else { payload.category = 'k6 synthetic expense'; payload.currency = 'KES'; }
    const response = http.post(BASE_URL + path, payload, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
    check(response, { [kind + ' write accepted']: result => result.status === 201 || result.status === 200 });
}

export default function () {
    if (!authenticated) login();
    const dashboard = http.get(BASE_URL + '/dashboard', { redirects: 0 });
    const dashboardLoaded = dashboard.status === 200
        && dashboard.url.endsWith('/dashboard')
        && dashboard.body.includes('Financial overview');
    check(dashboard, { 'dashboard read succeeds for an authenticated session': () => dashboardLoaded });
    if (dashboard.status === 401 || dashboard.status === 419 || dashboard.status === 302) authenticated = false;
    if (MODE === 'mixed') {
        const scenario = Math.random();

        if (scenario < 0.2) {
            writeSyntheticTransaction(Math.random() < 0.5 ? 'income' : 'expense');
        } else if (scenario < 0.4) {
            readSyntheticFinancePage();
        }
    }
    sleep(1);
}
