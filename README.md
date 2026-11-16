# Student Finance Manager

A PHP and MySQL/MariaDB application for recording day-to-day finances, planning budgets, monitoring savings, and reviewing spending patterns. Monetary values are presented in Kenyan shillings (KES).

> **Status:** Self-hosted portfolio project. Review the security and sample-data notes before using it with real financial information.

## Overview

Student Finance Manager gives users a central place to manage income, expenses, budgets, savings, debts, and recurring transactions, with monthly summaries and export options.

## Features

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

## Technology stack

| Area | Technology |
| --- | --- |
| Server-side | PHP 8.2+ |
| Database | MySQL / MariaDB |
| Local environment | XAMPP (Apache and MariaDB) |
| Frontend | HTML5, CSS3, JavaScript |
| Database access | MySQLi and prepared statements |
| Charts / exports | Chart data endpoint, CSV and PDF handlers |

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

### 2. Place it in the web root

For XAMPP on Windows, the application directory should be:

~~~text
C:\xampp\htdocs\Student-finance-manager\student_finance
~~~

Start Apache and MySQL in the XAMPP Control Panel.

### 3. Create and import the database

In phpMyAdmin, create the database:

~~~sql
CREATE DATABASE finance_manager
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;
~~~

Import student_finance/finance_manager (2).sql.

**Data hygiene:** the committed SQL dump contains example records. Review and sanitize the dump before sharing it publicly or importing it into an environment with real user data. For a clean setup, remove sample rows while retaining the schema, indexes, and constraints.

### 4. Configure the connection

Edit student_finance/auth/config.php to match your local database:

~~~php
<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "finance_manager";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
~~~

The empty password shown is a common fresh-XAMPP local default only. Use a dedicated database account and a strong password outside local development. Do not commit real credentials.

### 5. Open the application

~~~text
http://localhost/Student-finance-manager/student_finance/
~~~

Create an account through the sign-up page, then sign in.

## Database overview

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

- Use HTTPS for hosted deployments.
- Keep database credentials outside source control.
- Review CSRF protection for state-changing requests.
- Ensure all reads, updates, and deletes are scoped to the authenticated user.
- Review session-cookie settings and file-upload validation.
- Remove identifying or personal records from SQL dumps before publishing them.
- Do not treat this project as a regulated financial service.

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
