# Financial reports and exports

The authenticated Reports page supports inclusive custom date ranges up to 366 days and filters for all data, income, expenses, savings, or budget limits. Every query is built from the authenticated user's Eloquent relationships.

## Exports
- CSV includes a UTF-8 BOM. Text cells beginning with spreadsheet formula markers (=, +, @, or - after leading whitespace/control characters) are prefixed with an apostrophe.
- PDF includes a financial summary and transaction/budget rows, split across pages by a dependency-free PDF renderer.
- Downloads are returned with private/no-store headers; no report contents are persisted to temporary files or written to logs.

## Email
Email delivery queues a PDF attachment through the configured Laravel mail transport. Configure MAIL_* values and run a supervised queue worker in production. A queued confirmation means accepted by the queue, not delivered.

## Privacy and limits
All routes require authentication. Future dates and ranges longer than 366 days are rejected. Do not put provider credentials or report contents in logs.
