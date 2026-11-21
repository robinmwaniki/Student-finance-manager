# Redis and staged load testing

## Redis configuration

The app exposes opt-in Redis cache, session and queue backends. Separate logical databases are configured by default: cache 1, sessions 2 and queues 3. The default Redis connection uses `REDIS_URL` when supplied; the dedicated cache/session/queue connections intentionally use `REDIS_HOST`, `REDIS_PORT`, `REDIS_USERNAME`, `REDIS_PASSWORD` and their respective DB variables so a shared URL cannot override their logical database selection.

The PHP Redis extension is enabled in the application image. Local defaults remain file sessions, file cache and database queues. For isolated multi-instance staging, provision a private Redis service and set:

- `SESSION_DRIVER=redis`
- `SESSION_CONNECTION=session`
- `CACHE_STORE=redis`
- `QUEUE_CONNECTION=redis`
- `REDIS_HOST`, `REDIS_PORT`, `REDIS_USERNAME` and `REDIS_PASSWORD`
- `REDIS_CACHE_DB=1`, `REDIS_SESSION_DB=2` and `REDIS_QUEUE_DB=3` (or deliberately selected non-overlapping databases)

Use TLS and authentication where supported. Never expose Redis publicly. Configure persistence and availability against the recovery objective. Run web, workers and scheduler as separately scalable processes; size workers only after measuring memory and PostgreSQL connection budgets.

Configuration availability is not proof of multi-instance correctness. Before changing any shared environment, test session continuity, cache locks, rate limiting, queue processing, duplicate-write behavior and Redis interruption/recovery across at least two app instances. Keep local defaults until these checks pass.

## Request timing and slow-request diagnostics

Every response includes `X-Request-ID` and `X-Response-Time-Ms`. Requests slower than `PERFORMANCE_SLOW_REQUEST_THRESHOLD_MS` (default 1,000 ms) emit a structured warning containing route name, HTTP method, response status, duration and peak memory. Request bodies, query strings, raw paths and financial payloads are deliberately excluded. This is diagnostic instrumentation, not fleet-wide metrics, distributed tracing or an agreed production SLO. Correlate slow-request warnings with the request ID while building out DB, queue and infrastructure metrics.

## k6 smoke workload

The opt-in workload is `tests/load/k6-student-finance.js`. It logs in a dedicated synthetic account, repeatedly reads the dashboard and optionally creates small synthetic income/expense records. The script fails closed unless `CONFIRM_STAGING=true` is supplied.

Install k6 and run only against an isolated staging deployment:

```bash
CONFIRM_STAGING=true BASE_URL=https://staging.example.test TEST_EMAIL=load-test@example.test TEST_PASSWORD='dedicated-secret' VUS=10 DURATION=2m MODE=dashboard k6 run tests/load/k6-student-finance.js
```

For a mixed read/write workload set `MODE=mixed`. In PowerShell, set `$env:CONFIRM_STAGING='true'` only after verifying the target is isolated staging; set the other variables using `$env:BASE_URL`, `$env:TEST_EMAIL`, `$env:TEST_PASSWORD`, `$env:VUS`, `$env:DURATION` and `$env:MODE`.

Mixed mode writes synthetic KES 1.00 transactions tagged `k6-synthetic-load-test`. Clean up only those tagged rows in staging after a run. Never use real user accounts or production data. Never point this script at production.

Start with 5–10 VUs. Increase gradually only while the generator and staging infrastructure are monitored and stable. Default thresholds (p95 1.5s, p99 3s, error rate below 2%) are provisional smoke-test guardrails, not agreed production SLOs.

Record VU count, duration, generator capacity, app instance count/type, PHP-FPM worker count, Redis tier, PostgreSQL tier, RPS, p50/p95/p99 latency, errors, DB connections, CPU/memory and queue lag. One million connected sessions and one million requests per second are different workloads. A million-user claim requires a distributed test plan, budget approval and measured evidence.
