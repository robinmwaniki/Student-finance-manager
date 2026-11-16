# Student Finance Manager

A personal finance management application being rebuilt from procedural PHP and MySQL/MariaDB into **Laravel 13, PHP 8.5 and PostgreSQL 18**.

The application is intended to remain extensible after its first release. New capabilities can be added as independent feature areas without replacing the existing finance workflows.

> **Active development branch:** `feature/laravel-13-postgresql-migration`  
> The legacy application remains on `main` as a reference until the migration is reviewed and merged.

## Current migration status

The Laravel foundation, PostgreSQL schema, authentication controllers and screens, and initial authentication tests are in place. The project is still under active migration and is **not yet a complete replacement or ready for production data**.

Implemented foundation:
- Laravel 13 application structure and PHP 8.5 requirement.
- PostgreSQL connection configuration and initial finance migrations.
- User registration, login, logout and password-reset flow implementation.
- Authenticated dashboard, income and expense route/controller foundations.
- GitHub Actions branch-name policy and PHP/PostgreSQL CI workflow.
- Legacy data migration planning documentation.

Still to complete and verify:
- Successful CI run covering migrations, feature tests and Pint.
- Full comparison of the legacy SQL schema with the new PostgreSQL schema.
- Porting and testing every legacy finance feature.
- A repeatable, audited legacy data importer and reconciliation report.
- UI polish, accessibility, exports, deployment hardening and backup/restore tests.

## Technology stack

| Layer | Technology |
| --- | --- |
| Runtime | PHP 8.5 |
| Framework | Laravel 13 |
| Database | PostgreSQL 18 |
| Data access | Eloquent ORM |
| Server-rendered UI | Blade |
| Frontend enhancements | Alpine.js and Tailwind CSS (planned) |
| Charts | Chart.js |
| Dependency management | Composer |
| Tests | PHPUnit |
| Code style | Laravel Pint |
| CI | GitHub Actions |

## Features being migrated

- Account registration, login, logout and password recovery.
- Personal finance dashboard and monthly summaries.
- Income and expense tracking.
- Monthly category budgets and budget alerts.
- Savings entries and savings goals.
- Debt tracking.
- Recurring income and expenses.
- In-app notifications.
- Achievements and points.
- Financial charts and reports.
- CSV/PDF exports.
- User profile and avatar management.

Features will be marked complete only after implementation and automated tests exist for the relevant workflows.

## Extensible architecture

The initial release will use a **modular monolith**: one Laravel application and one PostgreSQL database, with clear boundaries between feature areas. This is intentionally simpler to maintain than starting with microservices, while still allowing new features to be introduced safely.

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the feature-extension approach.

## Development setup

### Requirements

- PHP 8.5 and required Laravel extensions, including PDO PostgreSQL.
- Composer 2.
- PostgreSQL 18.
- Git.
- Node.js/npm when frontend asset compilation is introduced.

### 1. Clone and switch to the development branch

```bash
git clone https://github.com/robinmwaniki/Student-finance-manager.git
cd Student-finance-manager
git checkout feature/laravel-13-postgresql-migration
```

### 2. Install dependencies

```bash
composer install
```

### 3. Configure environment

PowerShell:

```powershell
Copy-Item .env.example .env
```

Update the PostgreSQL database name, username and password in `.env`. Create the empty development database in PostgreSQL before running migrations.

### 4. Generate the application key

```bash
php artisan key:generate
```

### 5. Run migrations

```bash
php artisan migrate
```

This creates the new Laravel schema. **It does not import the legacy financial records.** Do not run data migration against the only copy of the old database.

### 6. Run tests and code style checks

```bash
php artisan test
vendor/bin/pint --test
```

### 7. Start the local development server

```bash
php artisan serve
```

Open [http://localhost:8000](http://localhost:8000).

## Branch naming and contribution workflow

Development branches must follow:

```text
feature/<descriptive-name>
```

Examples:
- `feature/authentication`
- `feature/budget-management`
- `feature/financial-reports`
- `feature/mobile-dashboard`

The GitHub Actions branch policy rejects other development branch names. Pull requests into `main` must originate from a `feature/...` branch. The `main` branch is the integration-branch exception.

Recommended workflow:

1. Update your local `main` from GitHub.
2. Create a new `feature/... ` branch from the latest `main`.
3. Implement one focused feature or fix.
4. Add/update automated tests.
5. Run tests and Pint.
6. Push the branch and open a pull request into `main`.
7. Merge only after review and required checks pass.

## Legacy data safety

The old SQL export contains sensitive account and financial information. Do not publish its contents, copy real records into test fixtures, or import it directly into a production database.

Before importing legacy data:
- Keep an untouched, encrypted backup of the original database.
- Verify the old schema and password-hash algorithms.
- Map every old column and relationship to the new schema.
- Preserve primary keys and transaction history where safe.
- Validate record counts, ownership links, balances and date ranges.
- Reconcile the imported result and retain an audit log.
- Test the process against a disposable staging database first.

Read [docs/LEGACY-DATA-MIGRATION.md](docs/LEGACY-DATA-MIGRATION.md) before attempting an import.

## Security principles

- Validate all input on the server.
- Use Laravel CSRF protection, session regeneration and login throttling.
- Scope every finance query to the authenticated user.
- Enforce authorization policies for records and actions.
- Keep credentials and application keys outside source control.
- Use HTTPS and secure cookies in production.
- Keep uploaded documents private unless explicitly intended to be public.
- Use database transactions for multi-step financial operations.
- Use decimal database types for money; do not use floating-point arithmetic for stored amounts.
- Keep production debug mode disabled and maintain tested backups.

## Repository

[GitHub repository](https://github.com/robinmwaniki/Student-finance-manager)

Maintainer: **Robin Mwaniki**
