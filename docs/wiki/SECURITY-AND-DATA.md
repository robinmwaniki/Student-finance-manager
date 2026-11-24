# Security and financial data protection

Student Finance Manager stores sensitive personal and financial information. Security and data integrity are core application requirements.

## Account and access controls

- Require authentication for personal finance workspaces.
- Enforce authorization and ownership on every read, update, export and delete operation.
- Regenerate sessions appropriately during authentication transitions.
- Apply throttling to sensitive endpoints such as login, password reset and account changes.
- Respect inactive/deactivated account state.
- Do not expose one user's records to another user through identifiers, filters, exports or background jobs.

## Secrets and configuration

- Keep populated `.env` files, application keys, database passwords and provider credentials out of Git.
- Use environment-specific secrets management in hosted environments.
- Disable debug output in production.
- Enforce HTTPS and secure cookie settings in production.
- Rotate credentials if they are accidentally exposed; deleting a leaked value from the latest commit is not sufficient.

## Financial data integrity

- Use PostgreSQL decimal/numeric columns for money.
- Use database transactions for multi-record operations such as transfers.
- Add database constraints for uniqueness and important invariants.
- Protect against duplicate recurring transaction processing and replayed requests.
- Scope data queries by authenticated owner.
- Validate imported records before confirmation.
- Keep an auditable trail for important financial corrections.

See [Financial data integrity](../FINANCIAL-DATA-INTEGRITY.md), [CSV import](../CSV_IMPORT.md) and [Multi-wallets](../MULTI_WALLET.md).

## File uploads and exports

- Validate file type, size and content; never trust a filename or browser-provided MIME type alone.
- Store private files on a non-public disk and authorize downloads.
- Avoid including secrets or unnecessary personal information in generated reports.
- Apply appropriate throttling to expensive export and email endpoints.

## Logs and monitoring

- Never log passwords, API keys, session cookies, full payment messages or private transaction payloads.
- Prefer request IDs, event types and redacted metadata for diagnostics.
- Restrict access to error monitoring and operational logs.
- Review retention and access policies for third-party monitoring providers.

## Backups and recovery

- Back up the database before risky schema changes or production migrations.
- Encrypt backups and restrict access.
- Test restoration; a backup that has never been restored is not a verified recovery plan.
- Define recovery objectives before production use.
- Keep legacy exports encrypted and separate from source control.

## Legacy data

The original MySQL/MariaDB export may contain real personal and financial records. Do not publish it, add it to tests, or import it directly into production. Follow the controlled staging, reconciliation and approval process in [Legacy data migration](../LEGACY-DATA-MIGRATION.md).
