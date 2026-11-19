# Production deployment and release checklist

This guide describes the operational requirements for deploying the Laravel application. It is intentionally provider-neutral: no production hosting provider or environment has been verified for this repository.

## 1. Choose compatible hosting

The application is a server-rendered Laravel application, not a static website. The runtime must provide:

- PHP 8.5 and the Laravel-required extensions, including `pdo_pgsql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `xml`, `ctype`, `tokenizer`, `dom`, `filter`, `hash`, and `session`.
- Composer 2 and the ability to run Laravel CLI commands during release.
- A supported PostgreSQL database reachable from the application.
- A web server/document root configured to serve the Laravel `public/` directory, with URL rewriting to `public/index.php`.
- Persistent writable storage for the configured local filesystem, sessions, file cache and logs when those drivers are used.
- A way to run a long-lived queue worker and a scheduler trigger.

GitHub Pages cannot host this stack. It serves static assets and does not execute PHP, run Laravel workers or connect an application runtime to PostgreSQL. Do not publish this Laravel repository as a GitHub Pages application.

For a multi-instance deployment, do not rely on local file sessions, file cache or local upload storage shared only by one instance. Configure shared services/storage and verify session, cache and file behavior before scaling out.

## 2. Prepare the database

1. Provision a dedicated PostgreSQL database and least-privilege application user.
2. Restrict network access to the application host and require TLS when supported by the database provider.
3. Take a baseline backup before the first migration.
4. Confirm that the database is empty or contains only data explicitly approved for this application.
5. Never import the legacy PHP/MySQL financial records as part of a routine application deployment.

The Laravel migrations create the new schema; they do not migrate historical user or transaction records from the legacy system.

## 3. Configure production environment

Set environment variables in the hosting provider's secret/configuration interface. Do not commit production values to Git.

Minimum configuration:

| Variable | Production guidance |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Generate a unique key once and store it securely; preserve it across releases |
| `APP_URL` | The canonical HTTPS application URL |
| `APP_TIMEZONE` | `Africa/Nairobi` unless the deployment requires another business timezone |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST`, `DB_PORT` | Values supplied by the database provider |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Dedicated production database credentials |
| `DB_SSLMODE` | Use the TLS mode required by the database provider; verify its certificate requirements |
| `SESSION_SECURE_COOKIE` | `true` behind HTTPS |
| `SESSION_ENCRYPT` | `true` |
| `SESSION_SAME_SITE` | `lax` unless a reviewed cross-site authentication flow requires otherwise |
| `LOG_LEVEL` | A production-appropriate level; avoid logging secrets or financial payloads |
| `QUEUE_CONNECTION` | `database` unless a separately configured supported driver is selected |
| `CACHE_STORE` | Use a driver whose persistence and sharing characteristics match the hosting topology |
| `MAIL_MAILER` | Configure a real mail transport before enabling email delivery |

Optional integrations:
- `SENTRY_LARAVEL_DSN` enables server-side error reporting. Configure it only after reviewing event scrubbing and privacy settings.
- `AI_PROVIDER`, `AI_API_KEY`, `AI_MODEL`, `AI_BASE_URL`, `AI_TIMEOUT` and `AI_CONNECT_TIMEOUT` configure the optional AI assistant. Keep provider credentials server-side.
- `ALPHA_VANTAGE_API_KEY` enables optional stock/commodity quote retrieval.
- Mail host, port, username, password and sender values must come from the selected mail provider.

Do not copy example secrets or development credentials into production. Never rotate `APP_KEY` casually: existing encrypted application data and sessions may become unreadable.

## 4. Build and release

For each release:

1. Deploy an explicitly reviewed commit from the intended release branch. The current feature integration branch is `develop`; the repository default branch `main` has not yet been promoted to the same release state.
2. Install locked dependencies with `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`.
3. Ensure the provider's document root points to Laravel's `public/` directory. Do not expose the repository root, `.env`, `vendor/`, or private storage.
4. Ensure `storage/` and `bootstrap/cache/` are writable by the PHP process. Keep private uploads in the configured private disk.
5. Run `php artisan migrate --force` only after taking a database backup and reviewing the migrations for backward compatibility.
6. Run `php artisan storage:link` if public-disk assets are intentionally used.
7. Run `php artisan optimize` after production environment variables are available.
8. Restart/reload PHP workers using the hosting provider's supported procedure.
9. Restart queue workers with `php artisan queue:restart` after the new code is deployed.

Do not run `migrate:fresh`, `db:wipe`, or destructive seeders against production.

## 5. Queue worker and scheduler

The example environment uses the database queue driver. If queued work is dispatched in production, run a supervised worker process, for example:

```bash
php artisan queue:work database --sleep=3 --tries=3 --timeout=90
```

Keep the worker under the hosting provider's process manager so it restarts after failures and deployments. Monitor failed jobs and define an operational retry policy.

The application schedules reminder generation daily at 08:00 in the configured application timezone. Configure the platform scheduler or cron to invoke this command every minute:

```bash
php artisan schedule:run
```

The scheduled task uses `withoutOverlapping()`; verify the scheduler runs against the same application/database environment as the web process. If the host does not support a persistent scheduler, use its native scheduled-job facility.

## 6. Health checks and smoke tests

Laravel exposes the framework health endpoint at:

```text
GET /up
```

Use it for a basic process/health probe. It is not a substitute for verifying database connectivity, mail, queue processing or business workflows.

After deployment, verify in a non-production test account:

- [ ] HTTPS is enforced and cookies are marked secure.
- [ ] `/up` returns a successful response.
- [ ] Registration, login, logout and password recovery work.
- [ ] Authenticated dashboard loads without server errors.
- [ ] A test income and expense can be created, edited and deleted.
- [ ] Budgets, savings, debt and recurring transaction screens load and enforce ownership.
- [ ] Reports and CSV/PDF exports work for the test account.
- [ ] Uploads are private where expected and public storage links expose only intended files.
- [ ] Mail delivery works with the configured provider.
- [ ] Queue worker processes a test job and failed-job monitoring is available.
- [ ] Scheduler runs the reminder task at the expected local time.
- [ ] Optional AI/market providers fail gracefully when disabled or unavailable.
- [ ] Logs contain no credentials, session tokens or unnecessary financial details.

Do not create test transactions in a real user's account. Use a dedicated test account and remove its test data through the supported application workflow.

## 7. Backups, restore and rollback

- Schedule encrypted PostgreSQL backups and retain them according to a documented retention policy.
- Back up private uploaded files separately from the database.
- Restrict backup access and never store production backups in this public repository.
- Perform a restore rehearsal into an isolated database before accepting real user data.
- Record the deployed commit SHA and migration state for every release.
- Prefer forward-fix or a reviewed application rollback. A code rollback is safe only if the already-applied database schema remains compatible with the older code.
- Do not automatically roll back destructive migrations or use `migrate:rollback` as a generic production recovery step.
- Define who can approve restoration and how the restored database and file backup are reconciled.

## 8. Release gate

A deployment is not considered production-ready until all of the following are true:

- CI is green for the exact deployed commit.
- A hosting provider, runtime, domain and PostgreSQL instance are configured.
- Production secrets and HTTPS settings are verified.
- Database and private-file backups exist and a restore rehearsal has passed.
- Migrations have been reviewed and applied safely.
- Queue and scheduler processes are running where required.
- Post-deployment smoke tests have passed and monitoring is active.
- A rollback/recovery owner and procedure are documented.

This guide does not authorize importing legacy financial data or using the system with real financial records before the release gate is met.
