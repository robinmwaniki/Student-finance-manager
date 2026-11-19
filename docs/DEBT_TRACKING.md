# Debt tracking

The debt register distinguishes two directions:

- `borrowed`: an outstanding amount the authenticated user owes.
- `lent`: an outstanding amount owed to the authenticated user.

New records start as `pending`. Marking a record `paid` settles it. Paid records are terminal in this workflow and cannot be reopened through the ordinary edit form. A record is displayed as overdue when it is still pending, has a due date, and that date is earlier than the application's current date. Overdue is derived; it is not persisted as a status, so it automatically clears when a record is settled.

The original debts table fields (description, amount, type, due_date, status) are retained. The migration adds only nullable per-user submission tokens and a lock version, allowing existing legacy rows to remain valid. The application scopes all debt queries through the signed-in user's relationship. Amounts use the existing decimal(14,2) column and are validated as positive values with at most two decimal places.

## Data integrity

- A UUID submission token plus a per-user unique index makes create requests idempotent.
- Updates and deletes use optimistic concurrency; stale forms receive HTTP 409.
- A user's request cannot read or mutate another user's debt.
- No automatic payment, repayment transaction, or external bank integration is implied by marking a record paid.

## Legacy compatibility

The database schema's `type` field is a 16-character string. The Laravel workflow uses the explicit values `borrowed` and `lent`; verify and normalize any historical type values in a separate reviewed data migration before importing legacy records. Do not silently rewrite production user data.

## Verification

Run the PostgreSQL migrations, full PHPUnit suite, and Pint formatting check.
