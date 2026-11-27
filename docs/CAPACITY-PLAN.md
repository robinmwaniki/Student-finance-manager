# Capacity engineering plan

## Status and claim boundary

This document is an initial architecture audit and a reproducible test plan for issue #71. It is **not** evidence that the application currently supports one million concurrent users. No production-scale load test has been run and no expensive infrastructure is provisioned by this plan.

The target must be treated as a capacity objective to validate progressively. A browser holding an authenticated session, a user actively making requests, and a request in flight are different quantities:

- **Connected session:** a client has a valid session but may be idle.
- **Active user:** a client generates application work during a defined time window.
- **In-flight request:** a request is currently consuming web, database or downstream resources.
- **Throughput:** completed requests per second (RPS), measured separately from sessions.

## Initial repository audit

The following observations are based on the current `develop` source and deployment configuration:

| Area | Current implementation | Capacity implication |
| --- | --- | --- |
| Runtime | One container runs Nginx and PHP-FPM under Supervisor. | Horizontal web replicas are possible, but each replica has a tightly bounded PHP worker pool. |
| PHP-FPM | `pm = ondemand`, `pm.max_children = 2`, `pm.max_requests = 500`. | A single container can execute at most two PHP requests concurrently. This is a safe small-staging setting, not a production capacity setting. Raising it requires memory/CPU measurements. |
| Sessions | Session driver defaults to `file`; encryption is enabled. | File sessions are local to one container and are not suitable for multiple independent web replicas without sticky routing or a shared session store. Prefer shared Redis or database sessions after measuring and testing. |
| Cache | Cache store defaults to `file`. Redis store is configured. | Local file cache does not provide shared cache/locks across replicas. Use shared Redis for distributed locks, rate limits and shared cache where justified. |
| Queue | Queue defaults to `database`; Redis connection is configured. | Database-backed jobs can add contention to the primary database. Any async workload requires independently sized workers, bounded concurrency and queue-lag monitoring. |
| Database | PostgreSQL is the application database; no pooler is configured in the repository. | Connection budgets must be explicit before adding web replicas and workers. Evaluate managed pooling/PgBouncer and verify transaction/session-mode compatibility. |
| Recurring processing | The default scheduled command scans due users in bounded keyset batches and processes inline. An explicit `--async` option dispatches one job per due user to the `recurring` queue. Each job has 3 attempts, a 60-second timeout and 30/120/300-second backoff; each schedule catches up at most 366 occurrences per invocation. | Async dispatch is not enabled by the scheduler. A dedicated worker must consume `recurring`; without one, keep inline mode. Row locks and unique period keys protect financial writes on retries, while queue depth/age and job failures need monitoring. |
| Staging | The documented free Render Blueprint has ephemeral local files, no persistent disk, no dedicated worker or cron, and an expiring free database. | It is useful for functional checks only, not representative capacity testing or durable migration rehearsal. |

These are source-level findings, not measured bottleneck claims. See `Dockerfile`, `docker/php/zz-pool.conf`, `config/session.php`, `config/cache.php`, `config/queue.php`, `routes/console.php`, `app/Console/Commands/ProcessRecurringTransactions.php` and `docs/RENDER-STAGING.md`.

## Recurring processing rollout status

The recurring command now has two execution modes:

- **Inline (default):** the scheduler invokes `finance:process-recurring` without extra flags. It scans due users in bounded keyset batches and calls the processor directly. This remains the safe default for deployments without a queue worker.
- **Queued (opt-in):** an operator may invoke `finance:process-recurring --async`. The command dispatches one `ProcessRecurringTransactionsForUser` job per due user to the `recurring` queue. The job is retryable (3 attempts, 60-second timeout, 30/120/300-second backoff). A worker must explicitly consume that queue (for example, `php artisan queue:work --queue=recurring`).

The processor retains per-schedule row locking, transaction boundaries and unique period keys. Repeated delivery is covered by an idempotency regression test. The command emits structured start/completion events and low-cardinality run metrics; the worker emits a permanent-failure event without user identifiers.

**Rollout gate:** do not pass `--async` until a dedicated worker is deployed, monitored and sized against the PostgreSQL connection budget. The current free staging Blueprint does not provision that worker. Before production use, add queue-depth/oldest-job-age, job throughput, retry/failure and worker saturation monitoring, then validate worker restarts and backlog recovery with synthetic staging accounts. No queue throughput or capacity claim has yet been measured.

## Workload model

Do not convert one million sessions directly into one million RPS. Define the workload before selecting infrastructure.

Use this model for the initial planning exercise:

```text
peak_RPS = concurrent_sessions
           * active_fraction
           * requests_per_active_user_per_minute
           / 60
```

Illustrative arithmetic only (not measured product behavior):

| Concurrent sessions | Active fraction | Requests / active user / minute | Implied average RPS |
| ---: | ---: | ---: | ---: |
| 1,000,000 | 1% | 1 | ~167 |
| 1,000,000 | 5% | 1 | ~833 |
| 1,000,000 | 10% | 1 | ~1,667 |

These averages do not account for bursts, retries, long-running reports, mobile reconnect storms, background jobs or geographic latency. The workload owner must confirm realistic activity rates and read/write mix before any capacity claim is made.

For each run, record:
- Connected sessions and active virtual users separately.
- RPS offered and completed, by route/workload.
- Read/write mix, transaction size, payload size and test data cardinality.
- p50/p95/p99 latency, timeout rate, HTTP 4xx/5xx rate and retry rate.
- PHP-FPM active/idle workers, CPU, memory and container restarts.
- PostgreSQL active/idle connections, pool wait time, query latency, locks, slow queries, CPU, memory and I/O.
- Cache hit/miss rate, Redis memory/CPU/latency and connection count.
- Queue depth, oldest-job age, job throughput, retries and failures.
- Regional latency and burst/reconnect behavior.

## Progressive validation strategy

Run tests only against isolated staging infrastructure with synthetic users and synthetic financial records. Start small and increase load only when the prior stage is stable.

1. **Baseline:** 10, 25 and 50 active users; validate functional correctness and collect a baseline profile.
2. **Service envelope:** 100, 250 and 500 active users; identify first saturation points and verify graceful overload behavior.
3. **Scale-out:** 1,000, 2,500 and 5,000 active users across multiple web replicas and independent queue workers.
4. **Large-scale progression:** increase in controlled steps (for example 10k, 25k, 50k and above) only after database, cache, worker and network budgets are validated.
5. **Session-heavy test:** separately test large numbers of mostly idle authenticated sessions; do not represent this as active-request capacity.
6. **Failure tests:** kill a web replica, restart Redis, pause workers, induce bounded database-pool exhaustion and introduce queue backlog. Verify recovery without duplicate financial writes.

The one-million-session objective may require a distributed load generator and infrastructure beyond free-tier budgets. If the largest safely affordable test is below the target, report the tested maximum and the evidence gap. Do not extrapolate linearly across database, network or queue saturation boundaries.

## Workload scenarios

The load suite should model realistic user journeys rather than repeatedly calling a single health endpoint:

- Login/session refresh and authenticated dashboard reads.
- Transaction creation with unique idempotency keys and retry injection.
- Wallet balance reads and transfers, including concurrent transfers.
- Budget, savings and debt reads/writes.
- Reports and exports with bounded date ranges and larger data sets.
- Recurring schedule processing and reminder generation under backlog.
- Offline-sync batch submission and duplicate/retry behavior.

Use deterministic synthetic accounts and data. Never use real user financial data, credentials, emails or production database snapshots in load tests.

## Multi-instance deployment preflight

Run `php artisan finance:deployment-readiness` before deploying more than one web instance. The command reports whether the configured session, cache/lock and queue drivers are shared-store candidates. Use `--strict` in a deployment gate to return a non-zero exit code when file/array sessions, file/array cache, or sync/null queues are configured.

The check is intentionally configuration-only. A `READY` result does **not** prove that Redis or the database is reachable, that session/cache tables exist, that queue workers are running, or that connection limits and failover are safe. Those require environment-specific integration and multi-instance tests. Keep local file/array stores for single-instance local development where appropriate; do not change production drivers without provisioning and validating the corresponding shared service.

## Architecture changes to evaluate (not yet enabled)

1. **Shared session/cache/locks:** use a managed Redis deployment or compatible shared store; validate encryption, TTLs, failover, lock expiry and behavior during Redis outages.
2. **PHP-FPM sizing:** measure memory per worker and CPU saturation, then derive `pm.max_children` from the container memory budget. Do not choose a large value by guesswork.
3. **Database connection budget:** calculate maximum connections as (web replicas × workers per replica) + (queue workers × DB concurrency) + scheduler/admin headroom. Keep this below the database limit with reserved operational capacity. Evaluate PgBouncer/managed pooling.
4. **Queue isolation:** move expensive reports, notifications, imports and scheduled processing to bounded queues where appropriate. Use separate queues/priorities for latency-sensitive and batch work. Define retries, timeouts, backoff, dead-letter/failed-job handling and idempotency.
5. **Recurring work:** replace potentially long sequential catch-up with bounded, resumable batches or jobs. Preserve the unique period key and row-lock/database uniqueness invariants so retries cannot create duplicate financial records.
6. **Database performance:** inspect query plans using representative synthetic data. Add indexes only when supported by query plans and workload evidence; verify write overhead and migration safety.
7. **Observability:** expose low-cardinality metrics for request latency/status, DB pool wait, query duration, queue lag, worker saturation and Redis health. Keep user IDs, emails, transaction payloads and secrets out of metric labels and logs.
8. **Overload protection:** define request timeouts, rate limits, bounded queues, retry budgets and graceful 429/503 behavior. Avoid retry storms and unbounded work accumulation.

## Provisional acceptance gates

The following are proposed gates to agree before formal capacity sign-off; they are not current guarantees:

- No duplicate or lost financial writes under concurrent requests, retries and worker restarts.
- No cross-user data exposure under concurrent access and cache/session reuse.
- Stable p95/p99 latency and error rates at each declared test level, with numeric SLOs agreed before the run.
- Database connections and queue concurrency remain within documented budgets.
- A single web/worker/cache failure does not corrupt financial state; recovery time and any accepted degradation are recorded.
- Results include test scripts, generator configuration, synthetic dataset recipe, infrastructure sizing, cost estimate, raw metrics and limitations.

## Cost and safety controls

- Do not provision paid production infrastructure as part of issue #71 without a separate budget and explicit approval.
- Use disposable staging resources and synthetic data; apply spend limits and tear down test-only resources after the run.
- Do not run load tests against production or shared development databases.
- Do not claim one million concurrent active users until a repeatable test has demonstrated the agreed workload at that scale.
- Keep a rollback plan for each infrastructure/configuration change.

## Next engineering sequence

1. Agree the workload activity profile, read/write mix, latency SLOs, target region(s) and maximum test budget.
2. Add a reproducible load-test harness and synthetic data generator.
3. Add baseline metrics and capture a low-load profile.
4. Fix measured bottlenecks in isolated, issue-sized changes.
5. Repeat staged tests and publish evidence after each material change.
