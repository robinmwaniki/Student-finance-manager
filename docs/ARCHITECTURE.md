# Application architecture and future feature development

## Architectural direction

Use a **modular monolith** for the first production release. It keeps deployment, database operations and debugging straightforward while giving each business capability a clear boundary.

Do not split the application into microservices just to make it appear scalable. Revisit that decision only when there is a measurable operational need.

## Feature boundaries

The application is expected to contain these feature areas:

| Feature area | Responsibilities |
| --- | --- |
| Identity and access | Registration, login, password recovery, profile, sessions and authorization |
| Dashboard | User-specific summaries, trends and recent activity |
| Income | Income records, sources, categories and filters |
| Expenses | Expense records, categories, tags and filters |
| Budgets | Monthly limits, progress and threshold alerts |
| Savings | Contributions, savings goals and progress |
| Debts | Borrowed/owed records, due dates and repayments |
| Recurring transactions | Schedules, processing and duplicate prevention |
| Notifications | In-app alerts and delivery preferences |
| Reports | Period summaries, charts, CSV and PDF exports |
| Achievements | Points, milestones and achievement history |

## Laravel implementation conventions

- Controllers should coordinate HTTP requests, not contain large business rules.
- Use Form Request classes for validation once a feature has more than simple validation.
- Use policies or explicit authorization checks for ownership and access control.
- Use Eloquent relationships and scope records to the authenticated user.
- Use service/action classes for business operations that are reused or involve multiple models.
- Wrap multi-record financial changes in database transactions.
- Dispatch events for cross-feature reactions such as budget threshold notifications.
- Use queued jobs for slow work such as report generation and scheduled recurring transactions.
- Keep money as PostgreSQL `numeric/decimal` values and perform calculations using decimal-safe approaches.
- Add database constraints and indexes for important invariants; application validation alone is not enough.
- Avoid a generic repository layer unless it solves a real testing or persistence problem.

## Suggested application organization as the project grows

Keep Laravel conventions initially. As the codebase expands, group feature-specific classes under clear namespaces, for example:

```text
app/
  Actions/
    Expenses/
    Income/
    Budgets/
  Events/
  Http/
    Controllers/
    Requests/
  Jobs/
  Listeners/
  Models/
  Policies/
  Services/
    Reports/
routes/
  web.php
  console.php
tests/
  Feature/
  Unit/
```

This is a direction, not a requirement to create empty folders now. Introduce each class when a real use case needs it.

## Safe process for adding a feature later

1. Create a branch named `feature/<feature-name>`.
2. Write down the user problem, expected behavior and acceptance criteria.
3. Check whether an existing feature already owns the relevant data.
4. Add a migration that is safe for existing installations; do not rewrite migrations that have already run in shared environments.
5. Implement validation, authorization and business rules.
6. Add automated tests for normal behavior, invalid input, permissions and edge cases.
7. Update navigation, UI, reports and notifications only where relevant.
8. Run the complete test suite and code-style checks.
9. Open a pull request and review the database and security impact before merging.
10. Add release notes and deployment/migration instructions.

## Backward compatibility and data protection

- Prefer additive schema changes.
- For destructive changes, use an expand-and-contract migration: add the replacement, migrate data, deploy code that uses it, then remove the old field in a later release.
- Never edit a migration that has already been applied to shared or production databases.
- Back up before production migrations and test restore procedures.
- Do not delete or silently reinterpret a user's financial history.
- Add explicit audit records for important financial corrections or administrative actions.

## Extensibility roadmap

Potential future additions can include:
- Multiple wallets/accounts and transfers between them.
- Mobile-friendly API or dedicated mobile client.
- Custom categories and tags.
- Shared household budgets.
- Recurring bill reminders and email/push notifications.
- Import from bank statements or CSV files.
- Savings automation and financial goals.
- Multi-currency support with recorded exchange rates.
- Subscription/bill tracking.
- Role-based support/admin tools.

These are future possibilities, not promises that every item will be included. Each should be assessed against user need, privacy, security, maintenance cost and data model impact.
