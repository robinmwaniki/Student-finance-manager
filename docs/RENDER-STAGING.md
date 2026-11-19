# Render staging deployment

This guide provisions a **disposable staging environment only**. It is not a production deployment and must never be connected to the legacy MySQL database or any real financial records.

## What the Blueprint creates

The repository's `render.yaml` defines:

- One Render Docker web service named `student-finance-manager-staging`.
- One separate Render PostgreSQL 18 database named `student-finance-manager-staging-db`.
- Both resources in Singapore.
- Deployment from the `develop` branch, with automatic deploys disabled.
- A generated Laravel `APP_KEY`, HTTPS secure cookies, stderr logging and the `/up` health check.
- Opt-in migrations for this dedicated staging database only.

The web service builds the PHP 8.5 / Nginx / PHP-FPM Docker image from the repository's root `Dockerfile`.

## Important free-plan limitations

Render's free web services can sleep after inactivity and may restart. A free Render PostgreSQL database expires after 30 days. Free services do not include persistent disks, background workers or cron jobs.

Therefore this Blueprint is suitable only for temporary evaluation:
- Uploaded avatars and other local-disk files are ephemeral and may disappear on restart/redeploy.
- The configured file sessions/cache are ephemeral; users may need to sign in again after a restart.
- The database-backed queue is configured but no separate worker is provisioned in this free staging Blueprint.
- The daily reminder scheduler is not running in this Blueprint.
- Do not use the staging database as the only copy of any data.

For longer-lived staging, configure a paid database/compute plan and persistent or external object storage, then add supervised queue and cron services. Review Render's current plan limits before provisioning.

## Deploy through Render

1. Confirm the PR that adds this configuration is merged into `develop`.
2. Sign in to the Render Dashboard and connect the GitHub repository `robinmwaniki/Student-finance-manager`.
3. Create a new **Blueprint** and select the `develop` branch.
4. Render reads `render.yaml`. Review the resource names, Singapore region and free-plan limitations before applying it.
5. Apply the Blueprint to create the staging web service and its dedicated staging database.
6. Wait for the initial Docker build and deployment. The entrypoint waits for PostgreSQL and runs `php artisan migrate --force` because `RUN_MIGRATIONS=true` is set in this staging Blueprint.
7. Open the generated Render service URL and confirm `GET /up` responds successfully.
8. Register a dedicated fake/test user and exercise login, dashboard, transactions, budgets, reports and exports. Use fictional values only.
9. Confirm the service logs do not expose credentials, session tokens or financial payloads.

Do not set a production database URL, legacy database URL, or real API credentials in this staging service. Keep optional AI, market-data and email integrations disabled until their staging configuration has been reviewed.

## Migration behavior

The container's entrypoint waits for PostgreSQL and runs migrations only when `RUN_MIGRATIONS=true`. The Blueprint sets this flag for the disposable staging database. The Docker image default is safe: migrations are disabled unless explicitly enabled.

For a production deployment, use a reviewed pre-deploy migration step supported by the chosen hosting plan. Do not copy the staging Blueprint into production unchanged.

## Troubleshooting

- **Database connection timeout:** confirm the Blueprint-created database is available and that the service's `DB_URL` is linked to the database's internal connection string.
- **Container exits before serving:** inspect Render logs for the bounded PostgreSQL readiness check, migration error or PHP-FPM/Nginx startup failure.
- **Health check fails:** inspect the Nginx access/error logs and confirm `/up` returns HTTP 200 from the service URL.
- **Assets or generated links use HTTP:** confirm `RENDER_EXTERNAL_URL` is available and the entrypoint derives `APP_URL` and `ASSET_URL` from it when not explicitly configured.
- **Staging data disappears:** this is expected for free-plan ephemeral filesystems and expiring free databases. Never treat this plan as durable storage.

## Official Render references

- [Laravel with Docker](https://render.com/docs/deploy-php-laravel-docker)
- [Docker deployments](https://render.com/docs/docker)
- [Render Blueprints](https://render.com/docs/blueprint-spec)
- [Persistent disks](https://render.com/docs/disks)
- [Laravel hosting and plan limitations](https://render.com/hosting/laravel)
