# Student Finance Manager

A personal finance application for tracking income, expenses, budgets, savings goals, debts and recurring transactions. The application is being migrated from procedural PHP and MySQL/MariaDB to **Laravel 13, PHP 8.5 and PostgreSQL 18**.

> **Migration branch:** `feature/laravel-13-postgresql-migration`. The original application remains on `main` as the legacy reference while the rebuild is developed and validated.

## Migration status

This branch contains the Laravel application foundation, PostgreSQL schema migrations and a legacy data migration plan. The original finance workflows are being ported incrementally; this branch is not yet a feature-complete replacement and is not ready for production data.

## Planned capabilities

- User registration, login, logout, and password-recovery workflow.
- Personal finance dashboard with monthly summaries.
- Income and expense recording and management.
- Monthly category budgets and budget-exceeded notifications.
- Savings entries and savings goals.
- Debt tracking for borrowed and owed amounts.
- Recurring income and expense records.
- In-app notifications.
- Charts and monthly financial summaries.
- CSV and PDF export endpoints.
- Profile and avatar-related functionality.

## Architecture

PHP pages and request handlers process user actions. PHP sessions maintain the authenticated user, while MySQL/MariaDB stores account and transaction records.

~~~mermaid
flowchart TD
    B[Browser] --> PHP[PHP Pages and Handlers]
    PHP --> S[Session Authentication]
    S --> W[Finance Workflows]
    W --> DB[(MySQL / MariaDB)]
    W --> CH[Chart Data Endpoint]
    W --> EX[CSV / PDF Export]
    CH --> B
    EX --> B
~~~

### Expense workflow

~~~mermaid
sequenceDiagram
    actor User
    participant Page as Expense Page
    participant PHP as PHP Handler
    participant DB as MySQL
    User->>Page: Submit expense
    Page->>PHP: POST amount, category and date
    PHP->>PHP: Check session and validate input
    PHP->>DB: Read budget and current monthly total
    PHP->>DB: Insert expense
    opt Budget threshold exceeded
        PHP->>DB: Create notification
    end
    PHP-->>User: Redirect with status message
~~~

## Technology

| Layer | Technology |
| --- | --- |
| Runtime | PHP 8.5 |
| Framework | Laravel 13 |
| Database | PostgreSQL 18 |
| Database access | Eloquent ORM |
| Frontend direction | Blade, Tailwind CSS, Alpine.js |
| Charts | Chart.js |
| Dependencies | Composer |
| Automated testing | PHPUnit |
| Code style | Laravel Pint |

## Getting started

### Prerequisites

- PHP 8.2 or later
- MySQL 8+ or MariaDB 10.4+
- Apache (XAMPP is a convenient local option)
- Git

### 1. Clone the repository

~~~bash
git clone https://github.com/robinmwaniki/Student-finance-manager.git
cd Student-finance-manager
~~~

- PHP 8.5 with PDO PostgreSQL, OpenSSL, Mbstring, Tokenizer, XML, Ctype, JSON and Fileinfo extensions.
- Composer 2.
- PostgreSQL 18.
- Node.js and npm when frontend assets are introduced.

## Local setup

~~~text
C:\xampp\htdocs\Student-finance-manager\student_finance
~~~

   ```bash
   git clone https://github.com/robinmwaniki/Student-finance-manager.git
   cd Student-finance-manager
   git checkout feature/laravel-13-postgresql-migration
   ```

2. Install PHP dependencies:

   ```bash
   composer install
   ```

~~~sql
CREATE DATABASE finance_manager
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;
~~~

Import student_finance/finance_manager (2).sql.

   On Windows PowerShell, use `Copy-Item .env.example .env`.

4. Set PostgreSQL credentials in `.env`. Never commit this file.

Edit student_finance/auth/config.php to match your local database:

~~~php
<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "finance_manager";

   ```bash
   php artisan migrate
   ```

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
~~~

   ```bash
   php artisan serve
   ```

   Open http://localhost:8000.

~~~text
http://localhost/Student-finance-manager/student_finance/
~~~

Laravel migrations define the new PostgreSQL schema. Legacy data is not imported by running `php artisan migrate`.

Read [docs/LEGACY-DATA-MIGRATION.md](docs/LEGACY-DATA-MIGRATION.md) before handling the old MySQL/MariaDB dump. It contains personal records and must be treated as sensitive. Never import it directly into production or publish copies of its data.

| Table | Purpose |
| --- | --- |
| users | User accounts and profile details |
| income, expenses | Income and expense records |
| budget | Monthly category budgets |
| savings, savings_goals | Savings activity and targets |
| debts | Borrowed and owed amounts |
| recurring_transactions | Scheduled income and expenses |
| recurring_log | Tracks recurring transaction applications |
| notifications | User notifications |
| achievements | Achievement records |

Finance records are associated with users through user_id. Queries should remain scoped to the authenticated user.

## Configuration

The current database connection is configured in student_finance/auth/config.php. The project does not currently provide a dedicated environment-variable template. For hosted use, move database credentials to server-side environment variables and keep local configuration out of version control.

## Project structure

~~~text
Student-finance-manager/
└── student_finance/
    ├── auth/       # Authentication and finance workflows
    ├── assets/
    │   ├── avatars/
    │   ├── css/
    │   └── img/
    ├── index.php
    └── finance_manager (2).sql
~~~

The auth directory contains dashboard, income, expense, budget, savings, chart, profile, notification, and export handlers.

## Security considerations

## Production readiness

## Troubleshooting

| Problem | Check |
| --- | --- |
| Database connection failed | MySQL is running, database name and credentials are correct. |
| 404 when opening the app | The project is under Apache's document root and the URL includes student_finance. |
| PHP source is displayed/downloaded | Open through Apache, not directly as a local file. |
| Tables do not exist | Import the SQL file into finance_manager. |
| Login session is lost | Check PHP session configuration and browser cookies. |

## License

No license file is currently declared. Unless a license is added, standard copyright restrictions apply.

## Maintainer

**Robin Mwaniki** · [GitHub](https://github.com/robinmwaniki)
