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

## Compatibility decisions

- Legacy debt type `owed` maps to target `lent`; `borrowed` remains `borrowed`. Legacy recurring status `inactive` maps to target `paused` plus `active=false`.
- Legacy monthly recurring-log rows receive the frequency-neutral target key `M:YYYY-MM`; the target unique constraint prevents duplicate periods.
- Income and expense rows are assigned to each imported user's default KES `Main wallet`, matching the wallet provisioned for newly registered accounts.
- Legacy `bill_reminders`, `financial_notes`, quiz history and reset tokens are not part of issue #66's import scope. They remain in the untouched source and require a separately reviewed mapping before any later migration.

- Amounts use PostgreSQL `numeric(14,2)` to avoid floating-point money calculations.
- Legacy `date` fields become `transaction_date` in income, expenses and savings. Month strings remain where they represent a budget or recurring-application period.
- Legacy MySQL enums become strings. Application validation and database constraints will enforce allowed values.
- The old dump has foreign keys for only some tables. The new schema adds owner foreign keys consistently. Orphaned owner references must be reported and resolved before import; never silently discard them.
- Retain the recurring-log unique key on recurring_id + applied_month to prevent duplicate monthly processing.
- Old reset tokens are not carried forward. Laravel password-reset tokens use the dedicated password_reset_tokens table. Existing sessions are intentionally not migrated; users sign in again after cutover.
- Avatar files must be copied to controlled storage and their paths checked. Do not trust legacy filenames as safe paths.

## Guarded importer

The `finance:import-legacy` Artisan command is dry-run by default. It reads from the named MySQL/MariaDB connection in a read-only transaction and writes only to a PostgreSQL connection when `--execute` is explicitly supplied. It refuses to run unless `APP_ENV=staging`, the target database name exactly matches `LEGACY_IMPORT_ALLOWED_TARGET_DATABASES`, the source driver is MySQL/MariaDB, and the target driver is PostgreSQL. Production-looking target database names are rejected. The original source is never updated.

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
5. Restore the fresh export to the isolated MySQL staging connection and run the guarded importer above in dry-run mode; reconcile and resolve rejected rows before explicitly enabling execute mode.
6. Convert MySQL date/month fields and booleans explicitly. Reject invalid dates, unsupported enum values, negative amounts where disallowed, and orphan references into an exception report.
7. Set PostgreSQL identity sequences to at least the maximum imported ID after explicit-ID inserts.
8. Compare per-table row counts, per-user totals, monthly income/expense/savings totals, debt balances, budget totals, recurring-log uniqueness and foreign-key integrity.
9. Test sign-in with representative existing bcrypt hashes in staging.
10. Obtain approval before production cutover. Keep the encrypted backup and original database untouched until the rollback window has expired.

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
