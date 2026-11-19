# Monthly budget management

Budgets are owned by the authenticated user and unique by user, category and budget month. The legacy form treats `amount` as the category's spending limit; `limit_amount` remains nullable for compatibility and is not used as a second competing limit.

## Monthly calculations

- The selected month is derived from the budget's date and stored as `YYYY-MM`.
- Spending is calculated from the owner's expense ledger using `transaction_date` within that calendar month and an exact category match.
- Remaining amount is budget minus spent. Negative values indicate overspending.
- Utilization is spent divided by budget. The display may exceed 100% so over-budget use remains visible.
- A category with no expenses has zero spent and 100% remaining. Exact-budget use is 100%; any amount above the budget is over budget.
- Totals are calculated from the categories with budgets, not from unrelated expense categories.

## Copying a month

Copying is an authenticated POST action protected by CSRF and scoped to the current user. The target must be later than the source.

- **Skip existing:** copy only categories that do not already exist in the target month and report skipped categories.
- **Report duplicates:** if any source category already exists in the target month, copy nothing and report the conflicting categories. If there are no conflicts, all source categories are copied.
- **Replace target:** requires an explicit confirmation checkbox and replaces all budgets in the target month with the source month's categories and amounts.

The database uniqueness constraint is the final protection against concurrent duplicate category/month writes. Copy operations run in a transaction.

## Write integrity

- Create requests carry a per-form UUID token; replaying the same submission does not create another budget.
- Updates and deletes require the row's `lock_version`; successful updates increment it, and stale writes return HTTP 409.
- Amounts use PostgreSQL decimal storage and Laravel numeric/decimal validation.
- No financial aggregates are cached.
