# Isolated staging load-test runbook

## Purpose and claim boundary

This runbook describes the current opt-in workload in `tests/load/k6-capacity.js`. It is a controlled test procedure, not evidence that the application supports any particular concurrency level. A k6 virtual user (VU), an authenticated but idle session, an active user and an in-flight HTTP request are different measurements; record them separately.

The current script supports at most 1,000 VUs per generator. Larger tests require multiple independently monitored generators and a coordinated plan. Do not extrapolate linearly from a small run or claim one-million-user capacity without measured evidence at the agreed workload.

## Safety gate

Use only a disposable, isolated staging deployment and a dedicated synthetic account. Never target production, shared user environments, or a database containing real financial records.

The script refuses to start unless all of the following are true:

- `CONFIRM_STAGING=true`.
- `BASE_URL` and `STAGING_HOST` are set, and the URL hostname exactly matches the confirmed hostname.
- Non-local targets use HTTPS; URL credentials, query strings and fragments are rejected.
- `TEST_EMAIL` and `TEST_PASSWORD` belong to a dedicated synthetic staging account.
- `VUS` is an integer from 1 through 1,000 per generator.
- Read-only mode is used for the initial smoke test. Any write mode additionally requires `ALLOW_SYNTHETIC_WRITES=true`.

Keep credentials in a protected local environment or secret store. Never commit credentials, paste them into issue comments, or include them in test output or screenshots. Stop if the target, account, or data isolation is uncertain.

## Current workload modes

The canonical script is `tests/load/k6-capacity.js`.

| Mode | Behavior | Writes |
|---|---|---|
| `read-only` | Authenticates each VU, reads the dashboard, then idles for the configured think time | None |
| `mixed` | Reads the dashboard and one randomly selected reports, ledger, wallet, budget, savings, debt, recurring or notifications page | None |
| `wallet-transfer` | Reads the dashboard and wallet page, submits a small transfer, then verifies its unique note appears in wallet history | Synthetic wallet transfer per iteration |

Each VU uses its own k6 cookie jar and reuses its authenticated session across iterations. It re-authenticates only after an authenticated request fails. The dashboard check requires the expected authenticated-page marker; a login redirect or login page must not count as a successful dashboard read.

Wallet-transfer mode creates ledger records in staging. Use two dedicated synthetic wallets with sufficient synthetic balance, and clean up only records created by the dedicated test account after the run. This mode is not part of the initial read-only smoke test.

The current script does not yet exercise income/expense writes, imports, exports, recurring scheduler execution, or external provider traffic. Design and validate those as separate workloads with explicit synthetic-data and idempotency controls before including them in capacity claims.

## Initial read-only smoke test

Start with one generator and five VUs for two minutes. Set the following variables in a protected shell; replace the host and synthetic account values with the exact isolated staging details.

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

Do not run the test until the dedicated synthetic account and the staging target have been verified. The script is not a substitute for that operational confirmation.

## Progressive test sequence

Run one stage at a time. Review application, database, Redis and generator health before increasing load.

| Stage | VUs per generator | Duration | Mode |
|---|---:|---:|---|
| Smoke | 5 | 2 minutes | read-only |
| Baseline | 25 | 5 minutes | read-only |
| Small mixed | 50 | 5 minutes | mixed |
| Growth 1 | 100 | 10 minutes | mixed |
| Growth 2 | 250 | 10 minutes | mixed |
| Growth 3 | 500 | 10 minutes | mixed |
| Upper bound | Up to 1,000 | 10 minutes | only after review |

Mixed mode is currently read-only despite its name: it broadens the page mix but does not create transactions. Any future write workload must be separately documented, opt-in and verified to use only synthetic records.

Do not increase VUs if the load generator, web service, database or Redis is saturated. A distributed run must record generator count, size, region, CPU/memory and achieved load per generator. Agree any paid-resource budget before provisioning or scaling infrastructure.

## Measurements to capture

For every run, retain a report containing:

- Application commit SHA, timestamp, region, anonymized staging service identifier, instance count/type and relevant runtime settings (including PHP-FPM worker limits).
- Generator count/type/region, CPU and memory, configured and achieved VUs, iterations, offered and completed RPS, in-flight requests and dropped iterations.
- Workload mode, request mix, session lifecycle, payload sizes and synthetic data cardinality.
- p50/p95/p99 latency, HTTP failure rate, check success rate, timeouts and endpoint-level latency.
- Web CPU/memory, PHP-FPM active/idle workers and saturation, restarts and request queueing.
- PostgreSQL active/max connections, pool wait time, query latency, locks, CPU/memory and storage I/O.
- Redis connections, CPU/memory, command latency, cache hit/miss ratio and evictions.
- Queue depth, oldest-job age, worker throughput/retries/failures where queue workloads are exercised.
- Exact topology cost and the first observed saturation point.

The script's current thresholds are provisional guardrails: HTTP failure rate below 2%, p95 below 1.5 seconds, p99 below 3 seconds and check success above 98%. These are not approved product SLOs; agree numeric SLOs and stop thresholds before formal capacity sign-off.

## Stop conditions

Stop immediately if the target hostname does not match the confirmed staging host; any production or real-user data is detected; synthetic writes affect an unexpected account; errors exceed agreed limits; latency breaches the agreed stop threshold; database connections approach their ceiling; queue backlog grows without recovery; or the load generator saturates. Do not automatically provision or scale paid resources.

## What this runbook does not prove

The current workload does not prove one million concurrent sessions, one million active users, one million requests per second, multi-region behavior, recurring scheduler throughput, full failover/recovery, or a maximum sustainable capacity. It does not replace multi-container correctness tests, database pool-budget validation, query-plan evidence, fleet-wide telemetry, or a costed distributed load test. Report only the measured result for the exact tested commit and topology, including limitations.
