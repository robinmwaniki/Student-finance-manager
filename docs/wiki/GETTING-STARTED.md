# Getting started

This page describes a clean local development setup for the current application on `develop`.

## Requirements

- Git.
- PHP 8.5 with the PHP extensions required by Laravel, including PDO PostgreSQL.
- Composer 2.
- PostgreSQL 18 (or a compatible PostgreSQL version supported by the project).
- Node.js 22 or newer for JavaScript/browser policy tests.
- A terminal such as PowerShell, Bash or zsh.

## 1. Clone the repository

```bash
git clone https://github.com/robinmwaniki/Student-finance-manager.git
cd Student-finance-manager
git switch develop
```

Always confirm the active branch before making changes:

```bash
git branch --show-current
```

## 2. Install PHP dependencies

```bash
composer install
```

Validate the dependency manifest:

```bash
composer validate --strict
```

## 3. Configure the environment

PowerShell:

```powershell
Copy-Item .env.example .env
```

macOS/Linux:

```bash
cp .env.example .env
```

Edit `.env` and configure a dedicated local PostgreSQL database. Do not reuse production credentials or commit the populated `.env` file.

Generate the application key:

```bash
php artisan key:generate
```

## 4. Prepare PostgreSQL

Create an empty local database and database role with permissions limited to that database. Set the matching values in `.env`:

- `DB_CONNECTION=pgsql`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

Use the actual configuration keys in `.env.example` as the source of truth. Do not paste passwords into source files, issues or chat logs.

## 5. Run migrations and storage setup

```bash
php artisan migrate
php artisan storage:link
```

Migrations create the Laravel database schema. They do **not** import data from the legacy PHP/MySQL application. See [Legacy data migration](../LEGACY-DATA-MIGRATION.md) before attempting any historical-data transfer.

## 6. Start the application

```bash
php artisan serve
```

Open http://localhost:8000.

If the app reports a database connection error, verify that PostgreSQL is running, the database exists, the role has access and the `.env` values match. After changing environment configuration, clear cached configuration:

```bash
php artisan config:clear
```

## 7. Run tests and formatting checks

```bash
php artisan test
vendor/bin/pint --test
node --test tests/JavaScript/*.test.cjs
```

The CI workflow is the authoritative automated validation pipeline; consult [Testing and CI](TESTING-AND-CI.md).

## Common local commands

```bash
php artisan route:list
php artisan migrate:status
php artisan test
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Troubleshooting

### PHP version mismatch
Check `php -v`. The current Composer manifest requires PHP 8.5. Switch the CLI to the supported PHP version before installing dependencies.

### Missing PostgreSQL extension
Check `php -m` for `pdo_pgsql`. Enable/install the extension for the PHP CLI runtime, not only the web server runtime.

### Database authentication or connection failure
Check PostgreSQL service status, host/port, database name, role permissions and environment variables. Do not solve local connection problems by weakening production database security.

### Stale configuration
Run `php artisan config:clear` after changing `.env`.

### Storage or uploaded-file links
Run `php artisan storage:link` and verify the configured filesystem disk. Do not place private financial documents in a publicly readable location.

## Safe development data

Use synthetic test accounts and fake transactions. Never copy real financial records, passwords, API keys or production database dumps into local fixtures or screenshots.
