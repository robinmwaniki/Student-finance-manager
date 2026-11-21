# Scale-readiness audit and capacity plan

## Status

This is a source-code and configuration audit, not a load-test result. The target in issue #71 is **up to 1,000,000 concurrently connected/active sessions**. The application has not demonstrated that capacity. Connected sessions, active users, requests per second (RPS), and concurrent in-flight requests are different measurements.

## Workload definition required

Before capacity claims, record:

- Concurrent authenticated sessions and session lifetime.
- Active request rate per user, peak RPS and in-flight request concurrency.
- Read/write and endpoint mix, payload sizes, request duration and burst patterns.
- Login bursts, scheduled jobs, queue throughput and external-provider traffic.
- Geographic distribution, availability target, and p50/p95/p99 latency and error objectives.

Publish the maximum tested values, test-generator capacity, topology and cost. Do not extrapolate a million-session result from a small test without explicit limits.

## Current source findings

### 1. Shared application state: Redis backends are configurable, not yet validated across instances

- `config/cache.php` exposes a Redis store; `config/session.php` supports the Redis session driver; `config/queue.php` exposes a Redis queue connection.
- `config/database.php` defines separate Redis connections for default, cache, session and queue logical databases. Cache/session/queue connections intentionally use host/port/credentials and their own DB numbers; `REDIS_URL` is applied only to the default connection so it cannot silently collapse the logical DB separation.
- The PHP Redis extension is installed in the application image.
- Local defaults remain file sessions, file cache and database queues; Redis is opt-in.
- The k6 script now refuses to run unless `CONFIRM_STAGING=true` is explicitly set.

**Remaining risk:** configurable drivers do not prove shared session continuity, cache-lock correctness, distributed rate limiting, queue semantics or recovery across multiple app instances. These need integration tests against a private staging Redis service. Do not switch a shared environment solely because the configuration exists.

### 2. Container and process topology remains a bottleneck

- `docker/php/zz-pool.conf` uses `pm = ondemand` and `pm.max_children = 2`.
- The Docker image runs PHP-FPM and Nginx in one container under Supervisor.

**Impact:** two PHP-FPM workers constrain a single container. Raising the limit without measuring per-worker memory, CPU and database connections can cause resource exhaustion. Prefer separately scalable stateless web instances, queue workers and scheduler processes behind a load balancer, with explicit connection budgets.

### 3. Dashboard aggregation has been partially optimised

- Daily income, expense and savings chart series are now aggregated in SQL rather than hydrating every matching transaction row into PHP.
- The dashboard still performs several monthly aggregate queries for income, expenses, savings and budgets.
- Monetary aggregation and presentation paths still require a broader decimal-safety review; do not assume SQL aggregation alone proves financial correctness or optimal query plans.

**Next:** inspect representative query plans with `EXPLAIN (ANALYZE, BUFFERS)`, verify composite indexes against real query predicates, review unbounded lists and keep money calculations decimal-safe. Any derived dashboard cache must be user-scoped and have explicit invalidation or a short bounded TTL.

### 4. External data endpoints need bounded work

- `MarketInformationService` calls third-party APIs synchronously for market/news/weather/nearby data, with timeouts and cache/stale fallback in some methods.
- The dashboard's `dailyQuote()` is generated locally and does not call those providers.

**Impact:** external calls must not block critical financial writes. Refreshes should use scheduled or queued work with bounded concurrency, provider rate limits and stale-data behavior.

### 5. Rate limiting and observability need multi-instance verification

Laravel throttles exist on several authentication and API routes. Verify that limiter storage is shared when multiple app instances run; otherwise per-instance limits can multiply. Request correlation, endpoint latency/error metrics, DB query timing, queue age/depth, worker saturation, cache hit/miss and actionable alerts are not yet demonstrated by a tested production observability setup.

### 6. PostgreSQL connection topology is not production-sized

The application uses standard PostgreSQL host/port settings. No measured PgBouncer/managed-pooler topology, total connection budget, read/write split or representative query-plan baseline has been validated.

**Impact:** web and worker concurrency must be bounded by PostgreSQL capacity. Evaluate a pooler, cap connections across all process types, set query timeouts and test backup/restore and recovery.

## Delivery sequence

1. Instrument request latency/errors, correlation IDs, DB query timing, queue depth/age, worker saturation and cache hit/miss with privacy-safe retention.
2. Establish repeatable synthetic workloads for login, dashboard, income/expense creation, wallet transfers, reports and recurring jobs.
3. Review high-cost queries, N+1 behavior, pagination, decimal-safe calculations and indexes using representative staging data.
4. Run two-or-more-instance integration tests for Redis sessions, cache locks, rate limits, queues, duplicate financial writes and Redis interruption/recovery.
5. Separate web, queue workers and scheduler; set bounded concurrency and retry policies.
6. Introduce a PostgreSQL pooler and documented connection budgets; verify backup/restore.
7. Progressively test 100, 1,000, 10,000, 100,000 and the largest safely affordable session levels. Capture p50/p95/p99, RPS, error rate, CPU/memory, DB connections/query latency and queue lag.
8. Exercise overload, queue backlog, provider timeout, worker restart, Redis loss and recovery without duplicate writes.
9. Publish tested topology, instance sizes/counts, database/cache tiers, test-generator limits, cost and remaining gap to the one-million target.

## Guardrails

- Never claim one million concurrent users without reproducible evidence.
- Use synthetic accounts and data only; keep real financial data out of fixtures and telemetry.
- Never cache authenticated financial responses in a shared cache without user-scoped keys and a privacy review.
- Use idempotency keys and database constraints for retryable financial writes.
- Do not raise PHP-FPM or worker concurrency without memory and DB connection budgets.
- Do not provision paid infrastructure or run expensive load tests without explicit cost approval.
- Free-tier hosting is not evidence of million-session capacity.

## Remaining implementation slices

- Add multi-instance integration tests for Redis sessions, cache locks, rate limits and queues.
- Add privacy-safe observability and a repeatable staged baseline.
- Measure PostgreSQL query plans and connection-pool behavior.
- Run progressively larger isolated-staging tests and publish results/costs.
