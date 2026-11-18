# Financial data integrity and concurrency

## Implemented safeguards

### Income and expense submissions
- Each rendered create form receives a UUID submission token.
- The database enforces a unique (user_id, submission_token) key.
- Replayed submissions and concurrent retries resolve to the original record instead of creating duplicates.
- Tokens are scoped to the authenticated owner.

### Concurrent edits and deletes
- Income and expense rows carry a monotonically increasing lock_version.
- Update and delete operations require the version read by the browser.
- Writes are conditional on that version; successful updates increment it atomically.
- A stale write receives HTTP 409 and cannot overwrite or delete a newer change.
- Queries remain scoped through the authenticated user's Eloquent relationship.

### Budgets and recurring processing
- A unique database constraint prevents more than one budget per user, category and month.
- The migration checks for pre-existing duplicate monthly budgets and aborts with an actionable error rather than deleting or merging financial records.
- The existing unique recurring_log(recurring_id, applied_month) constraint prevents a recurring transaction from being applied twice in the same month.

## Caching decision

Financial aggregates are calculated from PostgreSQL on each dashboard request. No financial data is cached. This is intentional: there is no production profiling evidence that caching is needed, and stale or cross-user financial data would be a correctness and privacy risk. If measurement later justifies caching, keys must include the authenticated user ID and relevant period, and every mutation must invalidate the affected keys.

## Remaining migration scope

The current Laravel HTTP application exposes income and expense write workflows. Savings, debt, budget management and recurring transaction execution are not yet exposed through Laravel controllers/routes. Their legacy PHP counterparts are still part of the migration work. These workflows must receive equivalent ownership, transaction and concurrency tests when ported; this issue does not claim that unported legacy workflows have been fully audited.

## Operational notes

- Use PostgreSQL transactions when one business operation changes multiple finance records.
- Keep money in PostgreSQL numeric(14,2) columns; avoid floating-point arithmetic for persisted amounts.
- Do not introduce Redis or a cache server until profiling demonstrates a need.
- If the unique budget migration refuses to run, inspect duplicates in a staging copy, reconcile them with the account owner, then rerun. Never auto-delete duplicate financial records.
