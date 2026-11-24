# Developer guide

## Branch strategy

- `develop` is the active integration branch for the current Laravel application.
- Create work branches from the latest `develop`.
- Use the required naming pattern `feature/<descriptive-name>`.
- Open pull requests targeting `develop`.
- Keep one focused change per pull request where practical.

Example:

```bash
git switch develop
git pull origin develop
git switch -c feature/short-description
```

## Implementation conventions

Follow the existing Laravel conventions and the project's architecture notes:

- Keep controllers focused on HTTP coordination.
- Validate incoming data server-side.
- Enforce ownership and authorization for every user-owned record.
- Use database transactions for multi-record financial operations.
- Store monetary values in decimal/numeric columns; avoid floating-point arithmetic for money.
- Use database constraints for invariants that must survive concurrent requests.
- Make migrations backward-compatible where possible.
- Never rewrite migrations that have already been applied in shared environments.
- Avoid introducing a new abstraction or dependency without a concrete need.

See [Architecture](../ARCHITECTURE.md) and [Financial data integrity](../FINANCIAL-DATA-INTEGRITY.md).

## Adding or changing a feature

1. Read the relevant feature documentation and inspect the existing implementation.
2. Define acceptance criteria and failure cases before editing.
3. Check authentication, authorization and record ownership.
4. Design database changes with rollback and existing-data behavior in mind.
5. Implement the smallest coherent change.
6. Add tests for successful behavior, validation errors, authorization failures and edge cases.
7. Update the relevant documentation and environment example if configuration changes.
8. Run local tests and formatting checks.
9. Review the final diff for secrets, personal data, debug code and unrelated changes.
10. Open a pull request and wait for all required checks on the exact head commit.

## Testing expectations

At minimum, test:
- A valid request and expected persisted result.
- Invalid or missing input.
- A user attempting to access another user's record.
- Duplicate/replayed requests where idempotency matters.
- Database failure or partial-operation behavior for multi-step financial writes.
- Relevant browser behavior for offline/PWA changes.

Do not use real customer records in automated tests.

## Pull request checklist

- [ ] Branch starts from latest `develop`.
- [ ] Branch name follows `feature/*`.
- [ ] Scope and acceptance criteria are clear.
- [ ] Migrations are safe and reversible where practical.
- [ ] Authorization and user ownership are enforced.
- [ ] Automated tests cover the change.
- [ ] Documentation is updated.
- [ ] No secrets or personal data are included.
- [ ] CI checks pass on the latest commit.
- [ ] No merge is performed while required checks are pending or cancelled.

## Security review

Treat financial data as sensitive. Review mass assignment, authorization, file upload handling, exports, logs, session behavior, CSRF protection, throttling and external provider requests. Do not log credentials, session identifiers, full payment messages or private financial payloads.
