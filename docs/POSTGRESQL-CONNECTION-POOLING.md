# PostgreSQL connection pooling and capacity budgets

## Status and deployment boundary

This guide defines a sizing method. It does not configure a pooler, change the current staging service, or establish a production connection limit. The Render staging Blueprint currently uses a single web service and a PostgreSQL database; it does not provision PgBouncer or enable Redis-backed sessions/cache/queues. Keep that staging topology unchanged until a separate staging change is reviewed and measured.

The application Docker image currently configures PHP-FPM with:

```ini
pm = ondemand
pm.max_children = 2
pm.process_idle_timeout = 10s
pm.max_requests = 500
```

This is a per-container PHP request-worker ceiling, not a database connection limit and not a capacity result. Nginx and PHP-FPM run in the same container. The current Supervisor configuration starts PHP-FPM and Nginx; queue workers and scheduler processes must be budgeted separately when deployed.

## 1. Calculate the application-side connection ceiling

Start by inventorying every process that can open a PostgreSQL connection. Use peak configured process counts, not average traffic.

```text
C_app =
    (web_instances × PHP_FPM_children × DB_connections_per_web_process)
  + (queue_worker_instances × worker_processes × DB_connections_per_worker)
  + scheduler_connections
  + release_or_migration_connections
  + monitoring_connections
```

Also account for one-off admin/maintenance clients and any separate services using the same database role. If the application uses multiple Laravel database connections, count the maximum simultaneously open connections per process rather than assuming one.

### Worked inventory example (illustrative only)

Suppose a proposed topology has:

| Process | Count | Maximum DB connections per process | Subtotal |
| --- | ---: | ---: | ---: |
| Web containers | 4 | 2 PHP-FPM children × 1 | 8 |
| Queue worker processes | 2 | 1 | 2 |
| Scheduler process | 1 | 1 | 1 |
| Release/migration task | 1 | 1 | 1 |
| **Application-side ceiling** | | | **12** |

These are example process counts to demonstrate the arithmetic. They are not recommended instance counts, provider limits, or a capacity claim. Replace every count with the actual deployment configuration and verify the maximum connections held by the running Laravel/PDO processes.

For the current single-container PHP-FPM setting, the web contribution is at most two concurrently executing PHP-FPM children. It does not include independently deployed queue workers, release tasks, monitoring, or other clients.

## 2. Reserve the PostgreSQL connection budget

Obtain the actual `max_connections` and reserved-connection settings from the selected PostgreSQL provider. Do not infer them from a hosting plan name or copy a number from this guide.

Define:

- `M`: PostgreSQL maximum client connections available to the database.
- `R`: connections reserved by PostgreSQL/provider for superusers, administration or failover operations.
- `O`: measured or explicitly budgeted connections used by other applications, monitoring, maintenance and provider services.
- `B_app`: the maximum server-side connections that this application and its pooler may consume.

Use:

```text
B_app = M - R - O
```

Keep additional operational headroom inside `B_app`; do not plan to run at the theoretical maximum. Confirm whether the provider reports total connections, customer-available connections, or a limit that already excludes reserved slots.

For PgBouncer, sum the configured server pools across every database/user pool. A `default_pool_size` applies per pool, not necessarily once for the entire PgBouncer process. Include reserve pools and any other PgBouncer instances in the total server-connection budget.

## 3. Understand PgBouncer client and server limits

These controls are not interchangeable:

- `max_client_conn` limits client connections accepted by PgBouncer. It does not cap PostgreSQL server connections.
- `default_pool_size` controls the normal number of server connections per database/user pool.
- `reserve_pool_size` permits additional server connections under configured conditions; include these in the database budget.
- `pool_mode` determines when a server connection is returned to the pool.
- `max_db_connections` and `max_user_connections`, where supported by the deployed PgBouncer version, can add aggregate guardrails. Verify exact semantics and version support before relying on them.

Choose pool sizes from the measured application-side concurrency, provider connection budget, query duration, pool wait time and workload mix. Do not set `max_client_conn` equal to PostgreSQL `max_connections`, and do not raise PHP-FPM children or worker concurrency merely because PgBouncer accepts more clients.

## 4. Transaction-pooling compatibility review

Transaction pooling can multiplex many application client connections over fewer PostgreSQL server connections, but it does not preserve server-session state between transactions.

Before enabling `pool_mode = transaction`, test the exact PgBouncer version and Laravel/PDO configuration for:

- Prepared statements. Confirm whether the deployed PgBouncer version supports protocol-level prepared statements and configure `max_prepared_statements` only when supported and tested.
- Startup parameters and session settings, especially the application's PostgreSQL `search_path` configuration. Verify the effective schema on every request; do not assume a session-level `SET` survives transaction boundaries.
- Temporary tables, session-level advisory locks, `LISTEN/NOTIFY`, cursors and any other feature that expects the same backend session across transactions.
- Transaction boundaries, nested transactions/savepoints and connection reuse under concurrent requests.
- Laravel migrations, schema inspection and release tasks. Prefer a direct PostgreSQL endpoint or a separately reviewed session-pooled connection for migrations and administrative operations.
- Connection reset behavior after errors, cancellation, worker termination and deploy/restart events.
- PDO persistent connections. Keep them disabled unless the exact pooler topology has been explicitly tested.

If any required feature depends on backend session affinity, use session pooling or a direct connection for that workload until it is redesigned and verified. Do not silently ignore unsupported startup parameters to make a test pass.

## 5. Application configuration

Laravel already reads the PostgreSQL endpoint through the standard environment configuration:

- Direct database: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSLMODE`.
- URL-based database: `DB_URL`, when supplied by the hosting provider.

When a pooler is approved, point the application connection at the pooler's private host and port using the provider's documented connection string or the existing PostgreSQL variables. Keep credentials in the hosting secret store. Use TLS/authentication according to the provider's requirements and restrict the pooler to trusted application networks.

Do not add provider-specific pooler credentials to source control. Do not change `render.yaml` or the current free staging service as part of this documentation-only profile.

## 6. Staging validation checklist

Use a dedicated staging database and synthetic accounts. Record the commit SHA and exact topology for every run.

- [ ] Capture PostgreSQL `max_connections`, reserved slots, current connections and provider limits.
- [ ] Record web instance count, PHP-FPM children, queue worker count/concurrency, scheduler count and release-task overlap.
- [ ] Confirm the app-side connection ceiling using the formula above.
- [ ] Verify pooler server connections never exceed the agreed database budget, including reserve pools and other services.
- [ ] Measure pool wait time, active/idle server connections, PostgreSQL query latency, lock waits and connection churn.
- [ ] Exercise login, dashboard reads, income/expense writes, wallet transfers, reports, recurring jobs and queue workers.
- [ ] Test deploy/migration overlap, worker restarts, slow queries, connection saturation, pooler restart and database failover.
- [ ] Verify bounded timeouts and predictable HTTP/job failure behavior; no unbounded connection retries.
- [ ] Confirm that a failed/retried financial write cannot create a duplicate record.
- [ ] Restore the direct-connection configuration and verify rollback.
- [ ] Publish the tested limits, p50/p95/p99 latency, throughput, errors, pool wait time, DB connections, resource usage and cost.

Stop a test if it reaches the database/provider connection ceiling, produces sustained pool waits or error growth, saturates the load generator, or risks affecting a shared environment. Do not automatically provision or scale paid resources.

## 7. Rollout and rollback

1. Capture the existing database endpoint and environment configuration.
2. Provision a private pooler only after the provider's connection limits and cost are known and approved.
3. Test with a staging copy and synthetic data; keep the direct PostgreSQL endpoint available.
4. Start with a conservative server pool below the measured application budget, then increase only from observed pool wait and query behavior.
5. Verify authentication, financial writes, migrations, reports, queue jobs and recovery.
6. Roll back by restoring the original direct database endpoint and restarting application processes so no stale connections remain.
7. Reconcile synthetic staging writes and document any failed or retried jobs.

## Non-goals

- No PgBouncer resource is provisioned by this change.
- No production or staging connection limit is changed.
- No PHP-FPM or queue concurrency is increased.
- No million-user capacity claim is made. A connection budget is one prerequisite; it does not replace distributed load testing.
