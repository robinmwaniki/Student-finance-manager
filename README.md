# Student Finance Manager

A full-stack personal finance management application built with **Laravel 13, PHP 8.5 and PostgreSQL 18**. It helps users record and understand income, expenses, budgets, savings, debts and recurring transactions, with reporting, learning tools and optional market information.

> **Development status:** The Laravel application and the current feature roadmap are implemented on `develop`. The project is not yet verified as a production deployment. The repository's default branch is still `main`; use `develop` for the current application source and integration work.

## Current capabilities

- Account registration, authentication, password recovery, profile and account settings.
- Personal dashboard with income, expenses, budgets, savings, debt, trends and forecasts.
- Income and expense capture, preview-first CSV transaction import (up to 500 rows), recurring transactions and category budgets.
- Savings goals and contributions, debt tracking, reminders and financial journal.
- Reports, charts, CSV/PDF exports and email delivery configuration.
- Achievements, financial learning quizzes and streaks.
- Financial calculators, currency-aware expense handling and a browser-local QR code generator.
- Optional AI financial assistance and Kenya/market information widgets.
- User-scoped financial records, account controls and security-focused validation.

See the feature documentation in [docs/](docs/) for behavior, configuration and limitations, including [CSV transaction import](docs/CSV_IMPORT.md). Optional integrations require server-side credentials and may be disabled when no provider is configured.

## Technology

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.5, Laravel 13 |
| Database | PostgreSQL 18 |
| UI | Blade, HTML, CSS and JavaScript |
| Tests | PHPUnit / Laravel tests, Node.js built-in test runner |
| Code style | Laravel Pint |
| CI | GitHub Actions |

The application uses a **modular monolith**: one Laravel runtime and one PostgreSQL database with feature boundaries. See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Run locally

### Requirements

- PHP 8.5 with PDO PostgreSQL and the extensions required by Laravel.
- Composer 2.
- PostgreSQL 18 (or a compatible supported PostgreSQL release).
- Node.js 22+ for the QR payload/browser tests.
- Git.

### Setup

```bash
git clone https://github.com/robinmwaniki/Student-finance-manager.git
cd Student-finance-manager
git switch develop
composer install
```

Copy the environment template and configure a dedicated, empty development database:

PowerShell:
```powershell
Copy-Item .env.example .env
```

macOS/Linux:
```bash
cp .env.example .env
```

Set the PostgreSQL database name, username and password in `.env`, then run:

```bash
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve
```

Open [http://localhost:8000](http://localhost:8000).

**Important:** `php artisan migrate` creates the Laravel schema; it does not import historical records from the legacy PHP/MySQL application. Do not point the new application at the only copy of a legacy database.

### Validate changes

```bash
composer validate --strict
php artisan test
vendor/bin/pint --test
node --test tests/JavaScript/qr-payload.test.cjs
```

The GitHub Actions workflow runs the Laravel suite against PostgreSQL 18, the QR payload policy tests and changed-file Pint checks.

## Branch and pull request workflow

- `main` is the repository default branch and has not yet been promoted to the current feature-complete integration state.
- `develop` is the active integration branch for the current Laravel application.
- Create focused branches from the latest `develop` using `feature/<descriptive-name>`.
- Open one pull request per issue, targeting `develop`.
- Include tests and relevant documentation with each feature.
- Merge only after the latest pull request commit has passed required CI and review checks.

Do not create new feature work from the old `feature/laravel-13-postgresql-migration` branch.

## Deployment

This repository does **not** currently establish a verified production host. The GitHub Pages homepage setting is not a deployment target for this application: GitHub Pages serves static files and cannot run Laravel/PHP, scheduled jobs, queue workers or PostgreSQL.

Use a PHP-capable application host and a managed or separately provisioned PostgreSQL database. Follow [docs/PRODUCTION-DEPLOYMENT.md](docs/PRODUCTION-DEPLOYMENT.md) for the deployment checklist, required environment configuration, scheduler/queue operation, backup and restore guidance, and post-deployment smoke tests. For the disposable Render staging setup, see [docs/RENDER-STAGING.md](docs/RENDER-STAGING.md).

Do not describe the application as live until a real deployment has been configured and the smoke tests have been completed.

## Legacy data and privacy

The legacy SQL export may contain personal and financial information. Never commit it, use real customer records in tests, or import it directly into production without a separately reviewed migration plan.

Before any legacy import:
- Keep an encrypted, verified backup of the original database.
- Map the legacy schema and password-hash algorithms to the new model.
- Test the importer against a disposable staging database.
- Reconcile record counts, ownership, balances, dates and transaction totals.
- Retain an auditable import report and a recovery plan.

## Security principles

- Keep `.env`, `APP_KEY`, database passwords and provider API keys out of version control.
- Disable debug output and enforce HTTPS in production.
- Scope financial records to the authenticated user and enforce authorization.
- Use database transactions and decimal-safe storage for financial operations.
- Keep private uploads outside the public web root.
- Back up production data and regularly test restoration.

## Repository

[GitHub repository](https://github.com/robinmwaniki/Student-finance-manager)

Maintainer: **Robin Mwaniki**
