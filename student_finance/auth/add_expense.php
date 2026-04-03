<?php
session_start();
include 'config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$userName = $_SESSION['fullname'] ?? '';

include 'sidebar.php';

/* =========================
   MONTH HANDLING (validated)
========================= */
$selectedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}
$currentMonth = date('Y-m');
$prevMonth    = date('Y-m', strtotime("$selectedMonth-01 -1 month"));
$nextMonth    = date('Y-m', strtotime("$selectedMonth-01 +1 month"));

$flashMsg  = '';
$flashType = '';

/* =========================
   ADD EXPENSE
========================= */
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $amount   = (float)$_POST['amount'];
    $category = trim($_POST['category']);
    $date     = $_POST['date'];

    if ($amount <= 0 || empty($category) || empty($date)) {
        $flashMsg  = 'Please fill in all fields correctly.';
        $flashType = 'error';
    } else {
        $expenseMonth = date('Y-m', strtotime($date));

        /* Check budget for this category */
        $stmtBudget = $conn->prepare(
            "SELECT COALESCE(SUM(amount),0) AS total FROM budget
             WHERE user_id=? AND category=? AND budget_month=?"
        );
        $stmtBudget->bind_param("iss", $user_id, $category, $expenseMonth);
        $stmtBudget->execute();
        $budgetAmount = (float)$stmtBudget->get_result()->fetch_assoc()['total'];

        /* Current total expenses for that category */
        $stmtTotal = $conn->prepare(
            "SELECT COALESCE(SUM(amount),0) AS total FROM expenses
             WHERE user_id=? AND category=? AND expense_month=?"
        );
        $stmtTotal->bind_param("iss", $user_id, $category, $expenseMonth);
        $stmtTotal->execute();
        $currentTotal = (float)$stmtTotal->get_result()->fetch_assoc()['total'];
        $newTotal = $currentTotal + $amount;

        /* Insert expense */
        $stmtInsert = $conn->prepare(
            "INSERT INTO expenses (user_id, category, amount, date, expense_month)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmtInsert->bind_param("isdss", $user_id, $category, $amount, $date, $expenseMonth);
        $stmtInsert->execute();

        /* Create notification if budget exceeded */
        if ($budgetAmount > 0 && $newTotal > $budgetAmount) {
            $message = "Budget exceeded for '$category'. Budget: KES " .
                       number_format($budgetAmount, 2) . " | Spent: KES " .
                       number_format($newTotal, 2);
            $stmtNotify = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?,?)");
            $stmtNotify->bind_param("is", $user_id, $message);
            $stmtNotify->execute();
        }

        $_SESSION['flash'] = ['msg' => 'Expense added!', 'type' => 'success'];
        header("Location: add_expense.php?month=$expenseMonth");
        exit();
    }
}

/* =========================
   EDIT EXPENSE
========================= */
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id       = (int)$_POST['edit_id'];
    $amount   = (float)$_POST['amount'];
    $category = trim($_POST['category']);
    $date     = $_POST['date'];

    if ($amount <= 0 || empty($category) || empty($date)) {
        $flashMsg  = 'Please fill in all fields correctly.';
        $flashType = 'error';
    } else {
        $expenseMonth = date('Y-m', strtotime($date));
        $stmt = $conn->prepare(
            "UPDATE expenses SET category=?, amount=?, date=?, expense_month=?
             WHERE id=? AND user_id=?"
        );
        $stmt->bind_param("sdssii", $category, $amount, $date, $expenseMonth, $id, $user_id);
        $stmt->execute();
        $_SESSION['flash'] = ['msg' => 'Expense updated!', 'type' => 'success'];
        header("Location: add_expense.php?month=$expenseMonth");
        exit();
    }
}

/* =========================
   DELETE EXPENSE
========================= */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM expenses WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $_SESSION['flash'] = ['msg' => 'Expense deleted.', 'type' => 'success'];
    header("Location: add_expense.php?month=$selectedMonth");
    exit();
}

/* =========================
   FLASH MESSAGE
========================= */
if (empty($flashMsg) && isset($_SESSION['flash'])) {
    $flashMsg  = $_SESSION['flash']['msg'];
    $flashType = $_SESSION['flash']['type'];
    unset($_SESSION['flash']);
}

/* =========================
   EDIT MODE — pre-fill form
========================= */
$editRow = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM expenses WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $editId, $user_id);
    $stmt->execute();
    $editRow = $stmt->get_result()->fetch_assoc();
}

/* =========================
   CATEGORY FILTER
========================= */
$filterCategory = trim($_GET['filter_cat'] ?? '');

/* =========================
   FETCH MONTHLY EXPENSES
========================= */
if (!empty($filterCategory)) {
    $stmt = $conn->prepare(
        "SELECT id, category, amount, date FROM expenses
         WHERE user_id=? AND expense_month=? AND category=?
         ORDER BY date DESC"
    );
    $stmt->bind_param("iss", $user_id, $selectedMonth, $filterCategory);
} else {
    $stmt = $conn->prepare(
        "SELECT id, category, amount, date FROM expenses
         WHERE user_id=? AND expense_month=?
         ORDER BY date DESC"
    );
    $stmt->bind_param("is", $user_id, $selectedMonth);
}
$stmt->execute();
$result = $stmt->get_result();

/* Monthly total */
$stmtTotal = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE user_id=? AND expense_month=?");
$stmtTotal->bind_param("is", $user_id, $selectedMonth);
$stmtTotal->execute();
$monthTotal = (float)$stmtTotal->get_result()->fetch_assoc()['total'];

/* Categories for filter dropdown */
$stmtCats = $conn->prepare("SELECT DISTINCT category FROM expenses WHERE user_id=? AND expense_month=? ORDER BY category");
$stmtCats->bind_param("is", $user_id, $selectedMonth);
$stmtCats->execute();
$allCategories = $stmtCats->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses - Finance Manager</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="main-content">
<div class="page-wrapper">

    <div class="page-header">
        <h2>💸 Expenses — <?= date('F Y', strtotime("$selectedMonth-01")) ?></h2>
        <span class="month-total">Total: KES <?= number_format($monthTotal, 2) ?></span>
    </div>

    <!-- FLASH MESSAGE -->
    <?php if ($flashMsg): ?>
        <div class="alert alert-<?= $flashType ?>"><?= htmlspecialchars($flashMsg) ?></div>
    <?php endif; ?>

    <!-- MONTH NAVIGATION -->
    <div class="month-nav">
        <a href="?month=<?= $prevMonth ?>" class="btn-nav">⬅ <?= date('M Y', strtotime("$prevMonth-01")) ?></a>
        <?php if ($selectedMonth < $currentMonth): ?>
            <a href="?month=<?= $nextMonth ?>" class="btn-nav"><?= date('M Y', strtotime("$nextMonth-01")) ?> ➡</a>
        <?php endif; ?>
    </div>

    <!-- ADD / EDIT FORM -->
    <form class="finance-form" method="POST">
        <h3><?= $editRow ? '✏️ Edit Expense' : '➕ Add Expense' ?></h3>
        <input type="hidden" name="action"  value="<?= $editRow ? 'edit' : 'add' ?>">
        <?php if ($editRow): ?>
            <input type="hidden" name="edit_id" value="<?= (int)$editRow['id'] ?>">
        <?php endif; ?>

        <div class="form-row">
            <input type="number" step="0.01" min="0.01" name="amount"
                   placeholder="Amount (KES)" required
                   value="<?= $editRow ? htmlspecialchars($editRow['amount']) : '' ?>">

            <input type="text" name="category"
                   placeholder="Category" required
                   value="<?= $editRow ? htmlspecialchars($editRow['category']) : '' ?>">

            <input type="date" name="date" required
                   value="<?= $editRow ? htmlspecialchars($editRow['date']) : '' ?>">

            <button type="submit"><?= $editRow ? '💾 Update' : '➕ Add Expense' ?></button>
            <?php if ($editRow): ?>
                <a href="add_expense.php?month=<?= $selectedMonth ?>" class="btn-cancel">✕ Cancel</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- CATEGORY FILTER -->
    <?php if (!empty($allCategories)): ?>
    <form method="GET" class="filter-form">
        <input type="hidden" name="month" value="<?= $selectedMonth ?>">
        <select name="filter_cat" onchange="this.form.submit()">
            <option value="">— All Categories —</option>
            <?php foreach ($allCategories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['category']) ?>"
                    <?= $filterCategory === $cat['category'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['category']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($filterCategory): ?>
            <a href="?month=<?= $selectedMonth ?>" class="btn-cancel">✕ Clear Filter</a>
        <?php endif; ?>
    </form>
    <?php endif; ?>

    <!-- EXPENSE TABLE -->
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Amount (KES)</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['category']) ?></td>
                        <td><?= number_format($row['amount'], 2) ?></td>
                        <td><?= date('d M Y', strtotime($row['date'])) ?></td>
                        <td class="action-btns">
                            <a href="?month=<?= $selectedMonth ?>&edit=<?= (int)$row['id'] ?>" class="btn-edit">✏️ Edit</a>
                            <a href="?month=<?= $selectedMonth ?>&delete=<?= (int)$row['id'] ?>"
                               class="btn-delete"
                               onclick="return confirm('Delete this expense?')">🗑 Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" class="empty-row">No expenses recorded for this month.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- CSV EXPORT -->
    <div class="export-bar">
        <a href="export.php?type=expenses&month=<?= $selectedMonth ?>" class="btn-export">⬇ Export CSV</a>
    </div>

</div>
</div>
</body>
</html>