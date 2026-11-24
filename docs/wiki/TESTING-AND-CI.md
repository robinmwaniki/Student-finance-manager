# Testing and continuous integration

## Local validation

Run the following from the repository root:

```bash
composer validate --strict
php artisan test
vendor/bin/pint --test
node --test tests/JavaScript/*.test.cjs
```

For changes involving the database, run migrations against a disposable PostgreSQL database. For service-worker or browser changes, run the relevant Playwright tests and inspect browser-specific behavior.

## GitHub Actions

The Laravel CI workflow is defined in `.github/workflows/ci.yml`. It provisions PostgreSQL 18 and Redis services and runs the application checks using PHP 8.5.

The workflow includes:
1. Repository checkout.
2. PHP 8.5 setup and Composer installation.
3. Strict Composer manifest validation.
4. Application environment preparation.
5. PostgreSQL migrations.
6. Laravel automated tests.
7. Node.js setup and JavaScript/browser policy tests.
8. Pint formatting validation for changed PHP files.
9. Docker image build.
10. Redis session-sharing integration test across two PHP-FPM containers.
11. Redis outage and AOF recovery integration test.

The branch policy workflow enforces the required feature branch naming convention. Additional review automation may appear as separate checks.

## Interpreting check results

- **Success:** the check completed and passed for the commit shown.
- **Failure:** the check completed and reported an error; inspect the failed step and logs.
- **Cancelled:** the run was interrupted or superseded. It is not a passing result and is not proof that the code itself failed.
- **Skipped:** the workflow decided the job was not applicable or its conditions were not met. Confirm whether the check is required for the pull request.
- **Queued / in progress:** no conclusion is available yet.

Always verify checks against the latest pull request head SHA. A successful run on an older commit does not validate later changes.

## Browser test guidance

Browser behavior can differ, particularly for service workers, CacheStorage, offline navigation and mobile viewport handling. Do not infer that WebKit is covered merely because Chromium passed. Read the browser test output and ensure each configured browser target has a completed result.

## Failure triage

1. Identify the exact failing check and run URL.
2. Confirm the commit SHA tested by that run.
3. Find the first meaningful error, not just the final generic failure line.
4. Determine whether the failure is code, environment, dependency, timing or infrastructure related.
5. Reproduce locally or in an isolated CI environment when practical.
6. Make a focused fix and add a regression test.
7. Rerun the affected workflow and verify the result on the new head SHA.

Never mark a pull request ready based only on a green subset when required checks remain pending, cancelled or failed.
