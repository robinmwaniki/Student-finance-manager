# Bill reminders, journal and notifications

## Bill reminders

A reminder has a current due date, amount, optional category and recurrence: one-time, weekly, monthly or yearly. Monthly and yearly schedules preserve the originally configured day (and month for yearly schedules). Dates are clamped to the last valid day in short months; the configured anchor is retained so a 31st-day reminder returns to the 31st when possible, and a February 29 yearly reminder returns to February 29 in leap years.

Reminders can be paused and resumed. Completed one-time reminders remain visible as completed and cannot be resumed. All reminder operations are owner-scoped. Updates and pause/resume operations increment a version so stale edit forms are rejected.

## Notification generation

The `finance:generate-reminders` Artisan command is scheduled daily at 08:00 in `APP_TIMEZONE`. It generates a due-soon notification when a bill is due within the next three local calendar days, and an overdue notification after its due date.

Each notification has a unique owner-scoped deduplication key containing reminder ID, occurrence date and notification kind. Re-running the command cannot create a duplicate for the same reminder occurrence. Overdue recurring reminders are advanced oldest-first; up to 366 missed occurrences are handled per run. One-time reminders are marked completed after the overdue notification is created.

The command uses row locks and a database uniqueness constraint to protect against concurrent scheduler runs. Notification reads are also owner-scoped. Mark-read and mark-all-read actions preserve the notification history.

## Scheduler operations

The scheduler definition lives in `routes/console.php`. Production must invoke Laravel's scheduler every minute, for example:

```cron
* * * * * cd /path/to/Student-finance-manager && php artisan schedule:run >> /dev/null 2>&1
```

For local development, run `php artisan schedule:work` in a persistent terminal. Confirm `APP_TIMEZONE` is set to the intended application timezone. The reminder command runs directly and does not require a queue worker.

## Financial journal

One journal entry is allowed per user and calendar month. Each entry stores private note content and a mood of great, good, okay or bad. The unique user/month constraint protects against duplicate entries; optimistic version checks prevent stale edits from overwriting newer reflections.

## Privacy and data handling

Reminders, journal entries and notifications are always queried through the authenticated user's relationships. No reminder, note or notification data is shared across accounts.
