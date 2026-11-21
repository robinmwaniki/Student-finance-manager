# Recurring income and expense processing

Recurring definitions are owned by the signed-in user. A definition can create income or expense ledger rows on a daily, weekly (ISO week), or monthly schedule. Creating a definition does not create a financial transaction; use **Process due transactions** to apply due occurrences.

## Processing semantics

- The first occurrence is the configured `next_due` date.
- Every missed due occurrence is applied once, oldest first, up to 366 occurrences per schedule per invocation. Run processing again to continue a larger backlog.
- Daily periods use `D:YYYY-MM-DD`; weekly periods use ISO week-year/week (`W:YYYY-WW`); monthly periods use `M:YYYY-MM`.
- Monthly schedules use the configured day 1–31. In shorter months, the occurrence is clamped to that month's last day; the next month returns to the configured day.
- Each ledger row, recurring log and `next_due` advance are committed in one database transaction. Row locking serializes competing processors and the unique period key is a second line of duplicate protection.
- Pausing prevents processing without deleting history. Resuming continues from the saved `next_due` date.
- Editing changes the schedule for future, unapplied occurrences only. Existing income/expense rows and logs are retained. Schedules with history cannot be deleted; pause them instead.
- Deleting a generated income/expense row leaves its recurring log as an audit marker; processing will not silently recreate that period.

## Operations

The authenticated **Process due transactions** action and the `finance:process-recurring` Artisan command invoke the same processor. Laravel schedules the command daily at 00:05 in the configured application timezone. The host must run `php artisan schedule:run` every minute (or `php artisan schedule:work` as a persistent process).

The command processes each affected user independently. A failure is reported and does not prevent other users from being processed; it exits unsuccessfully for monitoring. Failed occurrences roll back the ledger row, log and schedule advance.

Generated ledger rows are explicitly assigned to the user's default KES wallet. Run PostgreSQL migrations, PHPUnit and Pint before deployment.
