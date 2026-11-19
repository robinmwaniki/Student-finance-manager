# Profile, Account Security and Preferences

The authenticated **Settings** page is the single place to manage profile details, password, appearance, dashboard cards and account deletion.

## Profile and avatar

- Full name is validated to 100 characters.
- Email is displayed as the sign-in identity and is not editable through this workflow.
- Avatar uploads accept JPEG, PNG or WebP only, up to 2 MB and 2048 × 2048 pixels.
- Laravel validates the upload as an image and stores it under a generated name in the public `avatars/` directory. The client cannot supply a storage path or filename.
- Replacing or removing an avatar deletes the previous file only when its stored path is inside `avatars/`.
- Configure the public storage symlink during deployment:

```bash
php artisan storage:link
```

## Password changes and sessions

- The current password is required.
- New passwords follow the registration strength policy and require confirmation.
- A password change rotates the remember token and signs out the current browser session.
- Authenticated routes use Laravel's `auth.session` middleware. Other active sessions are invalidated when they next make a request with a password hash that no longer matches.
- Password update endpoints are rate limited.

## Appearance and dashboard preferences

The `users.theme` field stores `light` or `dark`. `users.dashboard_preferences` stores three booleans:

- `show_daily_quote`
- `show_weather`
- `show_forecast`

The dashboard defaults all three to enabled for existing accounts with no saved preference. Preferences are saved per user; no browser-local storage is used.

## Account deletion policy

Deletion is an immediate, permanent application-level hard delete. The user must:

1. Re-enter their current password.
2. Type the exact word `DELETE` into the confirmation field.
3. Submit the throttled delete action while authenticated.

The operation runs in a database transaction. User-owned records are removed through their foreign-key cascade rules. The legacy `achievements.user_id` foreign key uses `SET NULL`, so the user's achievement rows are explicitly deleted before the user record. The profile avatar is removed from public storage after the database transaction succeeds. The current session is logged out and invalidated.

The deletion includes income, expenses, budgets, savings, goals and contributions, debts, recurring schedules and logs, reminders, journal entries, notifications, quiz attempts, streaks, achievement awards, expense-capture drafts, webhook credentials and webhook event records.

No application-level financial record is retained after deletion. Infrastructure backups may retain encrypted database or file snapshots until the hosting provider's normal backup-retention period expires; those backups are not available through the application.

## Security and tests

All settings routes require authentication and the `auth.session` middleware. The deletion and password endpoints have independent rate limits. Avatar paths are generated server-side; only raster image formats are accepted. Profile updates cannot change the account email or another user's data.

Feature tests cover avatar storage and replacement, invalid file types, profile validation, current-password verification, session invalidation, preference persistence and dashboard visibility, deletion confirmation, cascade deletion, orphanable achievement cleanup and preserving another user's records.
