# CSV transaction import

The authenticated Import transactions page accepts one bank CSV statement at a time or pasted M-PESA confirmation messages. CSV files and SMS messages are parsed into a user-scoped preview. Nothing is written to the ledger until the user confirms.

## Supported bank CSV layouts

The importer recognises common header names and maps them automatically. Header matching ignores case and punctuation.

| Field | Recognised examples |
|---|---|
| Date | `date`, `transaction date`, `value date`, `posting date`, `posted date`, `booking date` |
| Description | `description`, `details`, `narration`, `transaction details`, `particulars`, `reference`, `merchant`, `payee` |
| Amount | `amount`, `transaction amount`, `value` |
| Debit | `debit`, `withdrawal`, `money out`, `paid out` |
| Credit | `credit`, `deposit`, `money in`, `paid in` |
| Type | `type`, `transaction type`, `direction`, `dr/cr` |
| Category | `category`, `expense category` |
| Tag | `tag`, `tags`, `notes`, `note` |

A statement must include a date, description/details, and either an amount column or debit/credit columns. Separate debit and credit values are converted to expenses and income respectively. A single signed amount can also infer direction (negative is expense, positive is income); a type column can use common labels such as `income`, `expense`, `credit`, `debit`, `CR` and `DR`.

Supported date formats include `YYYY-MM-DD`, `DD/MM/YYYY`, `DD-MM-YYYY`, `DD.MM.YYYY`, and day/month-name/year. Kenyan day-first interpretation is used for slash and dash dates. Amounts may include thousands separators, a KES/Ksh prefix, or parentheses for negative values. Values are imported as KES; the importer does not perform currency conversion.

## Category suggestions

If an expense has no category in the source statement, local keyword rules suggest a category such as Food & Groceries, Transport, Housing, Utilities, Airtime & Data, Health, Education or Entertainment. Unmatched descriptions are labelled Other. These are suggestions, not authoritative classifications; review the preview before confirming. An explicit category in the source file is preserved.

## Standard CSV layout

The standard layout is `type,date,description,amount,category,tag`. Example:

```csv
type,date,description,amount,category,tag
income,2026-11-01,Salary,15000.00,,Work
expense,2026-11-02,Groceries,1250.50,Food & Groceries,Home
```

## Safety and limits

- Maximum file size: 2 MB.
- Maximum rows per import: 500.
- The entire file is validated before a preview is created. One invalid row rejects the whole file; no financial records are written.
- A successful upload creates a 30-minute, user-scoped preview in the authenticated session. The preview must be explicitly confirmed.
- Confirmation writes all rows inside one database transaction. Per-row submission UUIDs make confirmation retries idempotent.
- Preview batches cannot be viewed or confirmed by another user. Discarding a preview does not change the ledger.
- Duplicate-looking bank rows are not automatically removed. Review the preview and remove duplicates from the source file before confirmation. M-PESA SMS receipt references have stable per-user tokens to prevent re-importing the same receipt.
- The importer processes uploaded files locally in the application. It does not connect to bank accounts, request bank credentials, or send statements to an AI provider.
- This workflow imports only new Laravel ledger rows. It is not a substitute for the separately reviewed legacy MySQL data migration procedure.

## Routes

- `GET /imports`: upload instructions.
- `POST /imports/preview`: validate CSV or M-PESA SMS and create preview.
- `GET /imports/preview/{batch}`: view the authenticated user's preview.
- `POST /imports/preview/{batch}/confirm`: commit the preview.
- `DELETE /imports/preview/{batch}`: discard the preview.

All routes except the guest-facing application pages require authentication and the existing authenticated-session middleware.
