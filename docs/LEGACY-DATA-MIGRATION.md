# Legacy MySQL/MariaDB to PostgreSQL migration

This document defines the controlled migration path from the original procedural PHP application to Laravel 13 and PostgreSQL 18.

## Safety rules

1. The existing `main` branch remains the legacy reference during this migration. Work only on `feature/laravel-13-postgresql-migration`.
2. Never import the checked-in legacy SQL dump directly into a production database. It contains user names, email addresses, password hashes and financial records.
3. Treat the original dump as sensitive personal data. Do not copy it into fixtures, tests, logs, screenshots or documentation.
4. Before a real migration, create an encrypted, access-controlled backup of the original database and verify that it can be restored.
5. Import first into an isolated staging PostgreSQL database. Do not point an importer at production.
6. Preserve legacy primary keys and bcrypt password hashes. Laravel's bcrypt hasher can verify compatible existing hashes; users should not be forced to reset passwords solely because of the database migration.
7. Do not delete, overwrite or transform the original MySQL database during import. Cutover is a separate, approved operation after reconciliation.

## Legacy-to-new table mapping

| Legacy table | PostgreSQL table | Mapping |
| --- | --- | --- |
| users | users | Keep id, fullname, email, password hash, points, avatar. Add Laravel timestamps and remember token. |
| income | income | Keep id, user_id, amount, source, date -> transaction_date, tag. Derive income_month from transaction_date where needed. |
| expenses | expenses | Keep id, user_id, category, amount, date -> transaction_date, tag. Derive expense_month from transaction_date. |
| budget | budgets | Keep id, user_id, category, amount, limit_amount, budget_month, date. |
| savings | savings | Keep id, user_id, goal, amount, date -> transaction_date, savings_month. |
| debts | debts | Keep id, user_id, description, amount, type, due_date, status. |
| savings_goals | savings_goals | Keep ids, owner, target/current amounts, due date, status and notes. |
| achievements | achievements | Keep ids, optional owner, title, description, points and unlocked_at. |
| notifications | user_notifications | Keep id, owner, message, status, created_at, type and read_at. |
| recurring_transactions | recurring_transactions | Keep id, owner, amount, type, frequency, next_due, status, label, day_of_month and active. |
| recurring_log | recurring_log | Keep ids, recurring_id, user_id, applied_month and applied_at. |

## Captured legacy schema baseline

The maintainer supplied a phpMyAdmin export named `finance_manager.sql`, generated on 2026-11-23 from MariaDB 10.4.32. A sanitized DDL-only reference is preserved at `tests/Fixtures/legacy-finance-schema.sql`. It contains all 15 table definitions, indexes, auto-increment declarations and source foreign keys, but no INSERT statements, account details, password hashes, tokens or financial rows. The fixture is useful for mapping and regression checks; it is not a substitute for confirming the current live schema before a real migration. The source export itself must remain private and must never be committed.

The captured schema includes `bill_reminders`, `financial_notes`, `quiz_attempts` and `quiz_streaks`. These four tables are intentionally outside issue #66's current importer scope, as noted below; their records remain untouched in the source until a separately reviewed mapping is approved.

## Compatibility decisions

- Legacy debt type `owed` maps to target `lent`; `borrowed` remains `borrowed`. Legacy recurring status `inactive` maps to target `paused` plus `active=false`.
- Legacy recurring-log rows receive frequency-specific target keys: `D:YYYY-MM-DD` for daily schedules and `W:YYYY-Www` (ISO week) for weekly schedules, both derived from `applied_at`; monthly schedules use `M:YYYY-MM` from `applied_month`. The target unique constraint prevents duplicate periods.
- Income and expense rows are assigned to each imported user's default KES `Main wallet`, matching the wallet provisioned for newly registered accounts.
- Legacy `bill_reminders`, `financial_notes`, quiz history and reset tokens are not part of issue #66's import scope. They remain in the untouched source and require a separately reviewed mapping before any later migration.

- Amounts use PostgreSQL `numeric(14,2)` to avoid floating-point money calculations.
- Legacy `date` fields become `transaction_date` in income, expenses and savings. Month strings remain where they represent a budget or recurring-application period.
- Legacy MySQL enums become strings. Application validation and database constraints will enforce allowed values.
- The old dump has foreign keys for only some tables. The new schema adds owner foreign keys consistently. Orphaned owner references must be reported and resolved before import; never silently discard them.
- Retain the recurring-log unique key on recurring_id + period_key to prevent duplicate monthly processing; the imported monthly key is normalized to M:YYYY-MM.
- Old reset tokens are not carried forward. Laravel password-reset tokens use the dedicated password_reset_tokens table. Existing sessions are intentionally not migrated; users sign in again after cutover.
- Avatar files must be copied to controlled storage and their paths checked. Do not trust legacy filenames as safe paths.

## Guarded importer

The `finance:import-legacy` Artisan command is dry-run by default. It reads from the named MySQL/MariaDB connection in a read-only transaction and writes only to a PostgreSQL connection when `--execute` is explicitly supplied. It refuses to run unless `APP_ENV=staging`, the resolved source host:port and database exactly match `LEGACY_IMPORT_ALLOWED_SOURCE_HOSTS` and `LEGACY_IMPORT_ALLOWED_SOURCE_DATABASES`, and the resolved target host:port and database exactly match `LEGACY_IMPORT_ALLOWED_TARGET_HOSTS` and `LEGACY_IMPORT_ALLOWED_TARGET_DATABASES`. It also verifies the source and target drivers, parses connection URLs before validation, and rejects production-looking database names. The original source is never updated.

Configure `LEGACY_DB_HOST`, `LEGACY_DB_PORT`, `LEGACY_DB_DATABASE`, `LEGACY_DB_USERNAME` and `LEGACY_DB_PASSWORD` in the staging secret store. Configure the four `LEGACY_IMPORT_ALLOWED_*` variables with exact host:port endpoints and database names for the restored MySQL/MariaDB source and isolated PostgreSQL target. Keep the allowlists narrow and do not use wildcards. Do not place real credentials in `.env.example`, source control or chat logs.

Run the preflight/dry run first:

```bash
APP_ENV=staging php artisan finance:import-legacy --source=legacy_mysql --target=pgsql
```

Review the private JSON report under `storage/app/private/legacy-import/`. It contains row IDs and per-user financial totals, so restrict access and do not attach it to issues or pull requests. Resolve every rejected row and rerun the dry run. Only after staging backup/restore has been verified and the report is reviewed should an operator run:

```bash
APP_ENV=staging php artisan finance:import-legacy --source=legacy_mysql --target=pgsql --execute
```

The execute mode is transactional on PostgreSQL, preserves IDs, treats identical existing rows as already imported, rejects conflicting primary keys, provisions default wallets, and resets identity sequences. It does not perform production cutover.

## Migration procedure

1. Stop writes to the legacy application during the final snapshot window.
2. Export schema and data using a consistent transaction/snapshot.
3. Encrypt and checksum the export; record row counts for every table.
4. Restore the export into an isolated MySQL staging database.
5. Run the guarded importer above in dry-run mode; reconcile and resolve rejected rows before explicitly enabling execute mode.
6. Review explicit date/month, boolean, enum and amount conversions; resolve invalid values and orphan references from the private exception report.
7. Verify PostgreSQL identity sequences are set to the maximum imported ID after explicit-ID inserts.
8. Compare per-table row counts, per-user totals, monthly income/expense/savings totals, debt balances, budget totals, recurring-log uniqueness and foreign-key integrity.
9. Test sign-in with representative existing bcrypt hashes in staging.
10. Obtain approval before production cutover. Keep the encrypted backup and original database untouched until the rollback window has expired.

## Backup, restore and rollback runbook

Use a dedicated staging database and separate credentials. The following commands are operator examples; confirm host, port and database names before running them. Passwords must be entered at the prompt or supplied through the deployment secret manager, never embedded in command arguments or committed.

### Capture and verify the legacy source backup

```bash
mysqldump --host="$ORIGINAL_MYSQL_HOST" --port="$ORIGINAL_MYSQL_PORT" \
  --user="$ORIGINAL_MYSQL_USERNAME" --password --single-transaction --routines --triggers \
  --hex-blob "$ORIGINAL_MYSQL_DATABASE" > legacy-finance.sql
sha256sum legacy-finance.sql > legacy-finance.sql.sha256
gpg --symmetric --cipher-algo AES256 --output legacy-finance.sql.gpg legacy-finance.sql
```

Restore only into a newly created isolated MySQL/MariaDB staging database:

```bash
gpg --decrypt --output legacy-finance.sql legacy-finance.sql.gpg
sha256sum --check legacy-finance.sql.sha256
mysql --host="$LEGACY_DB_HOST" --port="$LEGACY_DB_PORT" \
  --user="$LEGACY_DB_USERNAME" --password "$LEGACY_DB_DATABASE" < legacy-finance.sql
```

Then verify the checksum and compare source table counts with the export manifest. Keep the original database read-only during the final snapshot and do not restore over it.

### Back up and restore PostgreSQL staging

```bash
export PGHOST="$STAGING_PGHOST"
export PGPORT="$STAGING_PGPORT"
export PGUSER="$STAGING_PGUSER"
export PGDATABASE="$STAGING_PGDATABASE"
pg_dump --format=custom --no-owner --file=student-finance-staging.dump
sha256sum student-finance-staging.dump > student-finance-staging.dump.sha256
pg_restore --list student-finance-staging.dump
```

For a staging restore drill, create a fresh empty staging database, set the PostgreSQL connection variables above to that isolated target, and run `pg_restore --no-owner --dbname="$PGDATABASE" student-finance-staging.dump`. Let PostgreSQL prompt for the password or use a permission-restricted `.pgpass` file; do not put a connection URL or password in command arguments. Run migrations and the synthetic/integration test suite after restore.

### Rollback

If a staging import or reconciliation fails, stop the importer, retain its private report, drop and recreate only the isolated PostgreSQL staging database, and restore the last verified staging backup. Restore the isolated MySQL staging copy from its encrypted source backup if its schema/data needs to be reset. The importer does not write to the original MySQL source, and no production database is part of this rollback procedure. Production cutover and its rollback plan require a separate approved change.

## Reconciliation checklist

- [ ] Backup encrypted, checksummed and restore-tested.
- [ ] Row counts match for every imported table.
- [ ] All legacy IDs are retained.
- [ ] No orphaned foreign keys remain.
- [ ] Per-user balances and month/category totals match.
- [ ] Bcrypt password hashes validate in Laravel.
- [ ] Avatar files are present and mapped safely.
- [ ] PostgreSQL identity sequences are reset after explicit-ID inserts.
- [ ] Authentication, exports, charts and recurring processing pass tests.
- [ ] Rollback procedure tested before cutover.

The importer is implemented as a guarded Artisan command. Its private report includes per-table row counts, rejected row IDs, per-user financial totals, monthly/category aggregates, target foreign-key orphan counts and recurring-period duplicate groups. A real isolated-staging run must still verify those figures against the fresh restored snapshot; the backup/restore and rollback drill and database integration tests remain required before issue #66 can be closed.
