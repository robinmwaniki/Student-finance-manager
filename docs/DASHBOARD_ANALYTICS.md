# Dashboard trends, comparisons and forecasts

The dashboard is scoped to the signed-in user's finance records. Select any valid calendar month with the month picker; all monthly summaries, charts, comparisons and budget alerts follow that selection.

## Actual monthly figures

- Income, expenses and savings are summed from their transaction dates within the selected calendar month.
- Budget is the sum of budget limits for that month.
- Available balance follows the application's existing definition: income minus expenses minus savings contributions.
- Expense categories are grouped by exact category name.
- Daily chart series include every day in the selected month, including zero-activity days.
- Recent activity combines the user's latest income, expense and savings entries.

## Month comparison

Each metric compares the selected month with the immediately preceding calendar month. Absolute and percentage changes are displayed. When the prior month is zero, percentage change is undefined: the dashboard displays a no-baseline label rather than dividing by zero. If both months are zero, the calculated percentage is zero.

## Cash-flow estimate

The estimate for the month after the selected month is a simple arithmetic average of the three complete calendar months immediately preceding the selected month. Zero-activity months are included. The forecast separately estimates income, expenses and savings; estimated net balance is income minus estimated expenses and savings.

This is a transparent baseline, not a predictive model. It does not account for seasonality, growth, inflation, planned one-off transactions or changes in user behavior. Forecast values are labeled as estimates and never mixed into actual totals.

## Budget alerts

For every budget category in the selected month, the dashboard calculates spending from the owner's expense ledger. Usage at 80% or more is flagged as near-limit; usage above 100% is flagged as exceeded. No alerts are shown for categories below 80%.

## Data handling

All queries are scoped through the authenticated user's relationships and bounded by the selected month. Aggregates are calculated on demand; no sensitive financial totals are cached.
