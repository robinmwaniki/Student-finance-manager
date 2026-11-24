# Product overview

## Purpose

Student Finance Manager helps an individual understand and manage personal finances in one place. It brings day-to-day transactions together with budgeting, savings, debt tracking, reminders and reporting.

The application is a personal finance tool. It is not a bank, payment processor, accounting authority or source of guaranteed financial advice.

## Main capabilities

### Account and personal workspace
- Registration, login, logout and password recovery.
- Profile and account preferences.
- Account deactivation and account deletion controls.
- Authenticated, user-scoped financial workspace.

See [Account settings](../ACCOUNT_SETTINGS.md).

### Day-to-day finance
- Record and manage income and expenses.
- Organize activity by date, category, source or tag.
- Use wallets to separate balances and record transfers.
- Import supported CSV transaction files using a preview-and-confirm workflow.

See [CSV import](../CSV_IMPORT.md) and [Multi-wallets](../MULTI_WALLET.md).

### Planning and obligations
- Create monthly category budgets and monitor usage.
- Set savings goals and record contributions.
- Track debts and due dates.
- Configure recurring transactions and bill reminders.

See [Budgets](../MONTHLY_BUDGETS.md), [Savings](../SAVINGS.md), [Debt tracking](../DEBT_TRACKING.md), and [Recurring transactions](../RECURRING_TRANSACTIONS.md).

### Insights and productivity
- Review dashboard summaries, trends and charts.
- Generate financial reports and supported exports.
- Maintain a financial journal and in-app notifications.
- Use financial calculators and learning content.
- Access optional AI assistance and market-information integrations when configured.

See [Dashboard analytics](../DASHBOARD_ANALYTICS.md), [Reports](../FINANCIAL_REPORTS.md), [Financial tools](../FINANCIAL_TOOLS.md), [Learning](../FINANCIAL_LEARNING.md), [AI assistant](../AI_FINANCIAL_ASSISTANT.md), and [Market information](../MARKET_INFORMATION.md).

### Offline-capable experience
The application includes offline/PWA behavior for supported workflows. Offline behavior depends on the browser, service-worker registration, cached application shell and the feature's documented synchronization rules. Do not assume every screen or external integration works without a network.

See [Offline mode](../OFFLINE-MODE.md).

## Technology at a glance

| Area | Technology |
| --- | --- |
| Server application | PHP 8.5 and Laravel 13 |
| Database | PostgreSQL 18 |
| UI | Blade, HTML, CSS and JavaScript |
| Automated tests | Laravel/PHPUnit and Node.js tests |
| Code style | Laravel Pint |
| CI | GitHub Actions |
| Optional integrations | Configured server-side providers; availability depends on credentials and service status |

## Important limitations

- The project is under active development; verify the latest release and CI status before relying on it.
- Optional external services may be unavailable when credentials are not configured or a provider is down.
- The legacy MySQL/MariaDB data migration is a separate controlled process. Running Laravel migrations does not import legacy records.
- A configured staging environment is not proof of production readiness.
- Financial summaries depend on accurate user-entered or imported records. Review imported transactions before confirming them.

## Intended audience

- Individuals tracking their own income, expenses and financial goals.
- Developers maintaining or extending the Laravel application.
- Reviewers evaluating security, data integrity, test coverage and deployment readiness.
