# Isolated staging load-test runbook

## Safety gate

The k6 script refuses to start unless `CONFIRM_STAGING=true` is explicitly set. Use only an isolated staging deployment with dedicated synthetic accounts and synthetic financial data. Never point this script at production, a shared user environment, or a database containing real financial records.

The test account must have a funded/default wallet and permission to view the tested pages. Keep credentials in the CI secret store or a protected local environment; do not commit them or paste them into logs.

## Workloads

- `MODE=dashboard`: authenticated dashboard reads.
- `MODE=mixed`: every iteration reads the dashboard; 20% of iterations also read a page selected from reports, income, expenses, wallets and budgets, while a separate 20% of iterations also submit a synthetic income/expense write. The remaining 60% perform only the dashboard read. Each write fetches a fresh form token and uses its unique submission token.
- Writes use KES and a small synthetic amount. They still create real records in the staging database; clean up only the dedicated test account's synthetic data after the run.

The mixed scenario is a repeatable baseline, not a complete production traffic model. It does not yet cover wallet transfers, imports, PDF/CSV exports, login storms, recurring processing, or third-party provider traffic.

## Progressive test sequence

Run each stage separately and record the maximum stable level before moving up:

| Stage | Virtual users | Suggested duration |
|---|---:|---:|
| Smoke | 5 | 2 minutes |
| Baseline | 100 | 5 minutes |
| Growth 1 | 1,000 | 10 minutes |
| Growth 2 | 10,000 | 10 minutes |
| Growth 3 | 100,000 | 10 minutes |
| Upper bound | Largest safely affordable level | Agreed before execution |

A virtual user is not automatically one concurrent in-flight request or one continuously active human. Record request rate, request concurrency and the number of authenticated sessions separately. Do not extrapolate beyond the test generator's capacity.

## Example

Set the variables in a protected environment, then run:

```bash
export CONFIRM_STAGING=true
export BASE_URL="https://your-isolated-staging-host"
export TEST_EMAIL="dedicated-synthetic-user@example.test"
export TEST_PASSWORD="from-your-secret-store"
export MODE=mixed
export VUS=100
export DURATION=5m
k6 run tests/load/k6-student-finance.js
```

## Record for every run

- Commit SHA, timestamp, staging URL identifier and application topology.
- k6 generator type/size and achieved VUs, iterations, RPS and in-flight requests.
- Read/write mix, payload sizes and login/session behavior.
- p50/p95/p99 latency, HTTP error rate, timeouts and endpoint-level failures.
- Web CPU/memory/PHP-FPM saturation, worker count, queue depth/age and retry counts.
- PostgreSQL active/max connections, query latency, slow queries and pool wait time.
- Redis memory, CPU, command latency, connection count, cache hit/miss and evictions.
- Cost of the tested topology and the exact point at which latency/error thresholds were breached.

The current k6 thresholds are an initial guardrail (less than 2% HTTP failures, p95 below 1.5 seconds and p99 below 3 seconds). Agree production SLOs before using these as release gates.

## Stop conditions

Stop the run if the target URL or account is not the dedicated staging environment, synthetic writes are reaching unexpected records, the database/Redis approaches its resource ceiling, error rate exceeds the agreed threshold, or the test generator is saturated. Do not automatically scale paid resources.
