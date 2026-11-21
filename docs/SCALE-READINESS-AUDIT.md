# Scale-readiness audit and capacity plan

## Status

This document records a source-code configuration audit. It is not a load-test result and does not certify a production capacity. The target in issue #71 is **up to 1,000,000 concurrently connected/active sessions**, subject to a measured workload definition and staged tests.

## What "one million concurrent users" means

A connected session is not the same as a user issuing a request every second. Before capacity testing, record at least:

- Concurrent authenticated sessions and their session lifetime.
- Active request rate per user and peak requests per second (RPS).
- Read/write mix, endpoint mix, payload sizes and request duration.
- Peak login bursts, background jobs and scheduled workloads.
- Geographic distribution, availability target and latency/error objectives.

Report the maximum tested values and the test environment. Do not extrapolate a million-session result from a small test without stating the limits of that extrapolation.

## Baseline findings from the current `develop` source

### 1. Shared application state is not configured

- `config/session.php` defaults to the local `file` driver.
- `config/cache.php` defaults to the local `file` driver and defines only array, file and database stores.
- `config/queue.php` defaults to the database queue and defines sync/database connections only.
- `.env.example` selects file sessions/cache and database queues.
- Although `config/database.php` has Redis connection settings, the app config does not currently expose Redis cache, session or queue drivers, and the Docker image does not install the PHP Redis extension.

**Impact:** separate application instances cannot safely rely on local file sessions/cache. A database queue can work at modest scale but needs measured polling, indexing and connection budgets. Redis support should be introduced deliberately and tested across multiple app instances before scaling out.

### 2. Container worker capacity is a development-sized default

- `docker/php/zz-pool.conf` uses `pm = ondemand` and `pm.max_children = 2`.
- The Docker image runs PHP-FPM and Nginx in one container under Supervisor.

**Impact:** two PHP-FPM workers are an explicit concurrency bottleneck for a single container. Increasing this number blindly can exhaust memory or database connections. Worker sizing must be based on measured per-worker memory, CPU, request latency and a per-instance DB connection budget. Prefer horizontally scaled stateless web instances behind a load balancer.

### 3. Dashboard query and aggregation work needs optimisation

- `DashboardController` calculates current, previous and three historical months by issuing repeated aggregate queries for income, expenses, savings and budgets.
- It separately loads all matching income, expense and savings rows for the selected month into PHP to build daily chart arrays.
- Monetary aggregates and chart values are cast to PHP floats.

**Impact:** dashboard query count and PHP memory/CPU rise with request volume and transaction history. Replace row hydration for chart series with database-side date aggregation, add/verify composite indexes using query plans, and keep money arithmetic decimal-safe. Cache only derived, user-scoped dashboard summaries with explicit invalidation or short bounded TTLs; never share private financial results across users.

### 4. External data endpoints need bounded work

- `MarketInformationService` calls third-party APIs synchronously for market/news/weather/nearby data, with request timeouts and cache/stale fallback in some methods.
- The dashboard's `dailyQuote()` is generated locally and does not call those providers.

**Impact:** external API calls should remain off critical financial write paths. Expensive refreshes should be scheduled or queued with bounded concurrency, provider rate limits and stale-data behavior. Avoid running live provider calls per user request during peak load.

### 5. Existing request throttles are present but need shared storage

Several authentication and API routes have Laravel throttles. Their behavior must be verified with a shared limiter store when multiple instances are introduced; otherwise per-instance limits can multiply.

### 6. Database topology and connection pooling are not yet production-scaled

The PostgreSQL connection uses standard host/port configuration and can be pointed at a pooler through connection settings, but no pooler topology, max-connection budget, read/write split, or measured query-plan baseline is documented.

**Impact:** app worker counts must never be increased independently of PostgreSQL connection capacity. Evaluate PgBouncer or a managed pooler, cap total connections across web and worker fleets, and measure slow queries/index selectivity before adding indexes.

## Recommended delivery sequence

1. **Instrument before tuning:** add request correlation IDs, structured logs, endpoint latency/error metrics, DB query timing, queue depth/age, worker saturation and cache hit/miss metrics. Define privacy-safe retention.
2. **Establish repeatable baseline:** add reproducible load scenarios for login, dashboard, income/expense creation, wallet transfer and report generation. Use synthetic accounts/data only.
3. **Fix high-cost request paths:** aggregate chart data in SQL, eliminate N+1 queries, paginate unbounded lists, review financial decimal handling, and validate indexes with `EXPLAIN (ANALYZE, BUFFERS)` on representative staging data.
4. **Make application instances stateless:** add and configure Redis-backed cache, session, locks/rate limits and queues; verify shared behavior with two or more app instances. Install/configure the Redis client explicitly and document required environment variables.
5. **Separate workloads:** run web, queue workers and scheduler as independently scaled processes. Bound worker concurrency and queue retries; move long reports, exports, imports, notifications and provider refreshes to background jobs where appropriate.
6. **Protect PostgreSQL:** introduce a connection pooler, connection budgets, query timeouts, backup/restore verification and tested recovery procedures.
7. **Scale progressively:** test 100, 1,000, 10,000, 100,000 and then the largest affordable concurrent-session levels. Capture p50/p95/p99 latency, RPS, error rate, CPU/memory, DB connections, query latency and queue lag at each stage.
8. **Failure testing:** test Redis loss, database saturation, queue backlog, provider timeouts, worker restarts, duplicate retries and recovery without duplicate financial writes.
9. **Publish evidence and cost:** document tested topology, instance sizes/counts, DB tier, Redis tier, region, test generator capacity, spend and the remaining gap to one million sessions.

## Initial engineering guardrails

- Never claim one million concurrent users until reproducible tests demonstrate it.
- Use synthetic data in load tests; never put real user financial data in test fixtures or telemetry.
- Do not cache authenticated financial responses in a shared cache without a user-scoped key and explicit privacy review.
- Use idempotency keys and database constraints for retryable financial writes.
- Do not increase PHP-FPM children or queue workers without calculating memory and database connection budgets.
- Do not provision paid infrastructure or run expensive load tests without explicit cost approval.
- The existing free-tier hosting setup is not evidence of million-session capacity.

## Immediate next implementation slices

- Add a reproducible load-test harness and a documented synthetic workload.
- Refactor dashboard daily series to database aggregation and add regression tests.
- Add Redis support behind environment-based configuration without changing the default local developer setup.
- Add a multi-instance integration test profile for shared sessions, cache locks, throttles and queues.
