# Redis and staged load testing

## Redis configuration

The app now exposes Redis cache, session and queue backends. Redis connections use separate logical databases by default: cache 1, sessions 2 and queues 3. The existing local defaults remain file sessions, file cache and database queues, so local development does not require Redis.

The container image enables the PHP Redis extension. For multi-instance staging, provision a private Redis service and set:

- SESSION_DRIVER=redis
- SESSION_CONNECTION=session
- CACHE_STORE=redis
- QUEUE_CONNECTION=redis
- REDIS_HOST, REDIS_PORT, REDIS_USERNAME and REDIS_PASSWORD to the private service values

Use TLS and authentication where supported. Do not expose Redis publicly. Configure Redis persistence/availability according to the recovery objective. Keep web, queue workers and scheduler as separate processes. Set worker counts only after measuring memory and PostgreSQL connection budgets.

This change makes Redis backends configurable; it does not itself prove multi-instance correctness. Before switching any shared environment, test session continuity, cache locks, rate limits and queue processing across at least two app instances, including Redis interruption and recovery. Keep the local defaults until those tests pass.

## k6 smoke workload

The opt-in workload is in tests/load/k6-student-finance.js. It logs in a dedicated synthetic account, repeatedly reads the dashboard and optionally creates small synthetic income/expense records.

Install k6 and run against an isolated staging deployment only:

```bash
CONFIRM_STAGING=true BASE_URL=https://staging.example.test TEST_EMAIL=load-test@example.test TEST_PASSWORD='dedicated-secret' VUS=10 DURATION=2m MODE=dashboard k6 run tests/load/k6-student-finance.js
```

For a mixed read/write workload set MODE=mixed. In PowerShell, also set $env:CONFIRM_STAGING='true' after verifying the target is isolated staging; set the other values using $env:BASE_URL, $env:TEST_EMAIL, $env:TEST_PASSWORD, $env:VUS, $env:DURATION and $env:MODE.

The mixed mode writes synthetic KES 1.00 transactions tagged k6-synthetic-load-test. Clean up only those tagged rows in staging after a run. Never use real user accounts or production data.

Start with 5–10 VUs. Increase gradually only when the load generator and staging infrastructure are monitored and stable. The script's default thresholds (p95 1.5s, p99 3s, error rate below 2%) are provisional smoke-test guardrails, not agreed production SLOs.

Record VU count, duration, generator capacity, app instance count/type, PHP-FPM worker count, Redis tier, PostgreSQL tier, RPS, p50/p95/p99 latency, errors, DB connections, CPU/memory and queue lag. One million connected sessions and one million requests per second are different workloads. A million-user claim requires a distributed test plan, budget approval and measured evidence.


## Request timing and slow-request telemetry

Every response now includes X-Request-ID and X-Response-Time-Ms headers. Requests slower than PERFORMANCE_SLOW_REQUEST_THRESHOLD_MS (default 1000 ms) emit a structured warning with the route name, HTTP method, response status, elapsed time and peak memory. Request bodies, query strings, raw paths and financial payloads are deliberately excluded. Correlate these events with database/queue metrics and the request ID; this is diagnostic instrumentation, not a substitute for distributed tracing or an agreed SLO.
