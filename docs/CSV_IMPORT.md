# CSV transaction import

The authenticated Import CSV page accepts one CSV file at a time and imports income and expense rows into the signed-in user's ledger.

## Format

Required header columns: `type,date,description,amount`. Optional columns: `category,tag`.

- `type` must be `income` or `expense`.
- `date` must be a valid `YYYY-MM-DD` date and cannot be in the future.
- `description` is required; it maps to income source or expense category when category is omitted.
- `amount` must be positive with at most two decimal places. Imported values are KES.
- `category` is used for expenses and limited to 50 characters.
- `tag` is optional and limited to 50 characters.

## Safety and limits

- Maximum file size: 2 MB.
- Maximum rows per import: 500.
- The entire file is validated before a preview is created. One invalid row rejects the whole file; no financial records are written.
- A successful upload creates a 30-minute, user-scoped preview in the authenticated session. The preview must be explicitly confirmed.
- Confirmation writes all rows inside one database transaction. The per-row submission UUIDs make confirmation retries idempotent.
- Preview batches cannot be viewed or confirmed by another user. Discarding a preview does not change the ledger.
- Duplicate-looking rows are not automatically removed. Review the preview and remove duplicates from the source file before confirmation.
- This workflow imports only new Laravel ledger rows. It is not a substitute for the separately reviewed legacy MySQL data migration procedure.

## Routes

- `GET /imports`: upload instructions.
- `POST /imports/preview`: validate CSV and create preview.
- `GET /imports/preview/{batch}`: view the authenticated user's preview.
- `POST /imports/preview/{batch}/confirm`: commit the preview.
- `DELETE /imports/preview/{batch}`: discard the preview.

All routes except the guest-facing application pages require authentication and the existing authenticated-session middleware.
