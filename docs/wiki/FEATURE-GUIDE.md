# Feature guide

This page is a map of the major application areas. The linked documents contain feature-specific workflows and implementation details.

## Authentication and account

Users can register, sign in, sign out and request password resets. Authenticated account pages include profile and preference management plus account deactivation/deletion controls.

- [Account settings and profile](../ACCOUNT_SETTINGS.md)

## Dashboard

The dashboard presents a user-scoped summary of financial activity and trends. Treat dashboard figures as derived views of saved transaction data, not as a separate source of truth.

- [Dashboard analytics](../DASHBOARD_ANALYTICS.md)

## Income and expenses

Income and expense records form the core transaction history. Changes should preserve ownership checks, validation, date handling and decimal-safe money values.

- [Expense capture workflows](../EXPENSE_CAPTURE.md)
- [CSV transaction import](../CSV_IMPORT.md)
- [Financial data integrity](../FINANCIAL-DATA-INTEGRITY.md)

## Wallets and transfers

Wallets allow a user to organize balances. Transfers move value between wallets and must preserve total value across the transfer operation. Review the wallet documentation before changing transfer logic.

- [Multi-wallets](../MULTI_WALLET.md)

## Budgets and savings

Monthly budgets compare categorized spending with configured limits. Savings records and goals track contributions and progress.

- [Monthly budgets](../MONTHLY_BUDGETS.md)
- [Savings](../SAVINGS.md)

## Debts and recurring transactions

Debt records track obligations and due dates. Recurring transactions represent scheduled income or expenses; processing must avoid duplicate applications.

- [Debt tracking](../DEBT_TRACKING.md)
- [Recurring transactions](../RECURRING_TRANSACTIONS.md)

## Reminders, journal and notifications

These features support reminders, personal financial notes and in-app notification workflows.

- [Reminders, journal and notifications](../REMINDERS_JOURNAL_NOTIFICATIONS.md)

## Reports and tools

Reports provide summaries and supported exports. Financial tools include calculators and QR-related functionality.

- [Financial reports](../FINANCIAL_REPORTS.md)
- [Financial tools](../FINANCIAL_TOOLS.md)
- [QR generator](../QR_GENERATOR.md)

## Learning and optional integrations

Learning content supports financial literacy. AI assistance and market information depend on optional provider configuration and external availability; do not assume those services are always enabled.

- [Financial learning](../FINANCIAL_LEARNING.md)
- [AI financial assistant](../AI_FINANCIAL_ASSISTANT.md)
- [Market information](../MARKET_INFORMATION.md)

## Offline and browser behavior

The PWA/offline layer has browser-specific behavior and synchronization constraints. Test changes across the supported browser matrix and follow the documented outbox and conflict behavior.

- [Offline mode](../OFFLINE-MODE.md)

## Feature completion standard

A feature should only be described as complete when:
1. Its user workflow is implemented.
2. Server-side validation and authorization are present.
3. Data ownership and financial invariants are enforced.
4. Relevant automated tests cover normal and failure paths.
5. Documentation and configuration instructions are current.
