# Capacity test workloads

This guide defines a safe, repeatable k6 workload for the Student Finance Manager. It does not claim that the current deployment supports one million users. A test result is valid only for the exact application commit, hosting topology, database tier, Redis configuration, and load-generator capacity recorded with it.

## Safety requirements

- Use a disposable, isolated staging deployment and a dedicated synthetic account.
- Use only synthetic financial data. Never run against production, a shared user environment, or a database containing real financial records.
- The script requires `CONFIRM_STAGING=true`, an exact `STAGING_HOST` hostname match, and HTTPS for non-local hosts.
- Read-only mode is the default. Write modes additionally require `ALLOW_SYNTHETIC_WRITES=true`.
- Wallet-transfer mode requires two dedicated synthetic wallet IDs. The source wallet must have enough synthetic balance for the transfer amount.
- Start with one generator and a small VU count. Do not increase load if the generator, app, database, or Redis is saturated.
- The per-generator hard cap is 1,000 VUs. Larger tests require multiple controlled generators and a coordinated run plan; do not set one generator to 100,000 or 1,000,000 VUs.

## Workload modes

| Mode | Requests | Writes |
|---|---|---|
| `read-only` | Login once per VU, dashboard, then idle/think time | None |
| `mixed` | Login, dashboard, and one randomly selected reports/wallets/budgets/savings/debts/recurring/notifications page per iteration | None |
| `wallet-transfer` | Login and dashboard, then wallet page | One small transfer between two synthetic wallets per iteration |

The wallet-transfer workload exercises the authenticated transfer endpoint and its transaction/locking path. It creates staging ledger entries; use a dedicated account and clean up only the test records after the run. It does not exercise income/expense creation, imports, exports, recurring schedule processing, or external providers. Those require separately designed test data and idempotency controls.

Each VU has its own k6 cookie jar and authenticates once, then reuses that session across iterations. It logs in again only after an authenticated request indicates that the session has expired or become invalid. This avoids accidentally turning every workload iteration into a new login storm. VUs represent active test clients, not necessarily one million simultaneously connected humans. Record achieved VUs, iterations, request rate, and in-flight requests separately.

## Setup

Install k6 on a machine or controlled load-generator image. Create a dedicated synthetic user in staging and, for transfer mode, two same-currency wallets with adequate synthetic balance.

### Bash

```bash
export CONFIRM_STAGING=true
export BASE_URL="https://your-isolated-staging-host"
export STAGING_HOST="your-isolated-staging-host"
export TEST_EMAIL="synthetic-load-user@example.test"
export TEST_PASSWORD="read-from-your-secret-store"
export MODE=read-only
export VUS=5
export DURATION=2m
k6 run tests/load/k6-capacity.js
```

For read-mix mode set `MODE=mixed`. For wallet transfers:

```bash
export MODE=wallet-transfer
export ALLOW_SYNTHETIC_WRITES=true
export TRANSFER_FROM_WALLET_ID="123"
export TRANSFER_TO_WALLET_ID="456"
export TRANSFER_AMOUNT="0.01"
k6 run tests/load/k6-capacity.js
```

### PowerShell

```powershell
$env:CONFIRM_STAGING = 'true'
$env:BASE_URL = 'https://your-isolated-staging-host'
$env:STAGING_HOST = 'your-isolated-staging-host'
$env:TEST_EMAIL = 'synthetic-load-user@example.test'
$env:TEST_PASSWORD = '<read-from-your-secret-store>'
$env:MODE = 'read-only'
$env:VUS = '5'
$env:DURATION = '2m'
k6 run tests/load/k6-capacity.js
```

For write modes, explicitly set `$env:ALLOW_SYNTHETIC_WRITES = 'true'`. Never save credentials in the repository, shell history, test output, or screenshots.

## Progressive execution

Run one stage at a time and capture results before increasing load:

| Stage | VUs per generator | Duration | Mode |
|---|---:|---:|---|
| Smoke | 5 | 2 minutes | read-only |
| Baseline | 25 | 5 minutes | read-only |
| Small mixed | 50 | 5 minutes | mixed |
| Growth 1 | 100 | 10 minutes | mixed |
| Growth 2 | 250 | 10 minutes | mixed |
| Growth 3 | 500 | 10 minutes | mixed |
| Upper bound | Up to 1,000 per generator | 10 minutes | only after review |

The progression is a test plan, not a forecast or a promise that the free staging tier can sustain these levels. A distributed run must document the number and size of generators, network location, achieved VUs, achieved RPS, and generator CPU/memory. Increase the number of generators only after a cost and safety review.

## Measurements to capture

For each run record:

- Commit SHA, timestamp, region, anonymized staging service identifier, app instance count/type, PHP-FPM worker settings, and configuration version.
- k6 generator count/type, CPU/memory, VUs, iterations, achieved RPS, in-flight requests, and dropped iterations.
- p50/p95/p99 latency, HTTP failure rate, check rate, timeouts, and endpoint-level latency.
- PHP-FPM active/idle workers and saturation, web CPU/memory, queue depth/age, and worker retry counts.
- PostgreSQL active/max connections, connection-pool wait time, slow-query latency, CPU/memory, locks, and storage I/O.
- Redis connections, CPU/memory, command latency, cache hit/miss ratio, and evictions.
- Cost of the exact tested topology and the first observed saturation point.

Initial script thresholds are provisional guardrails: HTTP failure rate below 2%, p95 below 1.5 seconds, p99 below 3 seconds, and check success above 98%. Agree product SLOs before treating these as release gates.

## Stop conditions

Stop immediately if the target hostname does not match the confirmed staging host, any production/real-user data is detected, synthetic writes affect an unexpected account, errors exceed agreed limits, latency breaches the agreed stop threshold, database connections approach the configured ceiling, queue backlog grows without recovery, or the load generator saturates.

## What this test does not prove

A k6 virtual user is not the same as a persistent connected session or an active request. This test does not validate one million concurrent sessions, one million requests per second, multi-region behavior, full failover/recovery, recurring scheduler throughput, or multi-instance Redis correctness. Those remain separate acceptance items for issue #71. Do not close the issue or advertise a one-million-user capacity claim until those results are measured and reviewed.
