# Savings goals and contributions

The savings area uses a contribution ledger with optional goal associations.

- Existing legacy savings rows retain their goal text and remain valid with a null goal ID.
- New contributions can link to an owned savings goal. The goal title is copied to the contribution label for readable history.
- A goal's current amount is reconciled from linked contributions after each contribution create, edit or delete; it is not directly editable.
- Contribution forms carry per-user UUID submission tokens backed by a unique database key.
- Contribution and goal edits/deletes use optimistic lock versions. Stale writes return HTTP 409.
- Every lookup and mutation is scoped through the authenticated user's Eloquent relationship. Goal IDs are validated against the same user.
- Deleting a goal preserves its contribution history and clears only the nullable goal reference.
- Goal progress is capped at 100% in the UI. Overfunding remains visible and marks a target goal completed.
- Monthly totals use the existing PostgreSQL decimal ledger.

## Legacy compatibility

The migration is additive. It does not rewrite the original savings table or guess relationships from legacy goal-name strings. Existing contributions remain unlinked until a separate reviewed data-migration operation maps them to goal IDs. Never commit real user data, password hashes or transaction exports.

## Verification

Run Laravel migrations, the complete PHPUnit suite and Pint against the PostgreSQL test database.
