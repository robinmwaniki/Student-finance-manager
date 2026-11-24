# Student Finance Manager — Project Wiki

Welcome to the project knowledge base for **Student Finance Manager**, a personal finance application built with Laravel 13, PHP 8.5 and PostgreSQL 18.

This wiki is the starting point for users, developers and maintainers. It summarizes the application and links to the detailed feature and operational documents already maintained in `docs/`.

> **Project status:** Active development. The current application source and integration branch is `develop`. The repository default branch is `main`; do not assume it contains the latest application implementation. A successful CI run is not, by itself, proof of production readiness.

## Start here

| I want to… | Read |
| --- | --- |
| Understand what the application does | [Product overview](PRODUCT-OVERVIEW.md) |
| Install and run it locally | [Getting started](GETTING-STARTED.md) |
| Understand the code structure and design | [Architecture](../ARCHITECTURE.md) |
| Learn the main application workflows | [Feature guide](FEATURE-GUIDE.md) |
| Contribute code safely | [Developer guide](DEVELOPER-GUIDE.md) |
| Run tests and understand CI | [Testing and CI](TESTING-AND-CI.md) |
| Configure a hosted environment | [Deployment and operations](../PRODUCTION-DEPLOYMENT.md) |
| Understand the staging deployment | [Render staging](../RENDER-STAGING.md) |
| Review data protection and financial integrity | [Financial data integrity](../FINANCIAL-DATA-INTEGRITY.md) |
| Plan the legacy MySQL/MariaDB migration | [Legacy data migration](../LEGACY-DATA-MIGRATION.md) |

## Feature documentation

- [Account settings and profile](../ACCOUNT_SETTINGS.md)
- [Dashboard and analytics](../DASHBOARD_ANALYTICS.md)
- [Income and expense capture](../EXPENSE_CAPTURE.md)
- [CSV transaction import](../CSV_IMPORT.md)
- [Monthly budgets](../MONTHLY_BUDGETS.md)
- [Multi-wallets and transfers](../MULTI_WALLET.md)
- [Savings and goals](../SAVINGS.md)
- [Debt tracking](../DEBT_TRACKING.md)
- [Recurring transactions](../RECURRING_TRANSACTIONS.md)
- [Reminders, journal and notifications](../REMINDERS_JOURNAL_NOTIFICATIONS.md)
- [Financial reports](../FINANCIAL_REPORTS.md)
- [Financial tools and QR generator](../FINANCIAL_TOOLS.md) · [QR generator details](../QR_GENERATOR.md)
- [Financial learning](../FINANCIAL_LEARNING.md)
- [AI financial assistant](../AI_FINANCIAL_ASSISTANT.md)
- [Market information](../MARKET_INFORMATION.md)
- [Offline mode and PWA behavior](../OFFLINE-MODE.md)

## Technical and operational references

- [Architecture decisions](../ARCHITECTURE.md)
- [PostgreSQL connection pooling](../POSTGRESQL-CONNECTION-POOLING.md)
- [Scale readiness audit](../SCALE-READINESS-AUDIT.md)
- [Scale and load-test runbook](../SCALE-LOAD-TEST-RUNBOOK.md)
- [Redis and load testing](../SCALE-REDIS-AND-LOAD-TESTING.md)
- [Production deployment checklist](../PRODUCTION-DEPLOYMENT.md)

## Documentation rules

1. Describe behavior that exists on `develop`; mark planned, optional or environment-dependent behavior explicitly.
2. Never document credentials, real personal data, production tokens or private database records.
3. When a feature changes, update its feature page and relevant setup, security or test instructions in the same pull request.
4. Prefer links to the canonical document over maintaining duplicate instructions.
5. Do not claim a feature is production-ready solely because it works locally or its tests pass.
6. Record operational assumptions, supported versions and known limitations.

## Repository workflow

- Active integration branch: `develop`.
- Feature branches: `feature/<descriptive-name>`.
- Pull requests should target `develop`.
- Keep changes focused and add tests for behavior changes.
- Merge only after the latest commit has passed required checks and review.

Repository: https://github.com/robinmwaniki/Student-finance-manager
