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
   ADD BUDGET
========================= */
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $amount   = (float)$_POST['amount'];
    $category = trim($_POST['category']);
    $date     = $_POST['date'];

    if ($amount <= 0 || empty($category) || empty($date)) {
        $flashMsg  = 'Please fill in all fields correctly.';
        $flashType = 'error';
    } else {
        $budgetMonth = date('Y-m', strtotime($date));
        $stmt = $conn->prepare(
            "INSERT INTO budget (user_id, category, amount, date, budget_month)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("isdss", $user_id, $category, $amount, $date, $budgetMonth);
        $stmt->execute();
        $_SESSION['flash'] = ['msg' => 'Budget added!', 'type' => 'success'];
        header("Location: add_budget.php?month=$budgetMonth");
        exit();
    }
}

/* =========================
   EDIT BUDGET
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
        $budgetMonth = date('Y-m', strtotime($date));
        $stmt = $conn->prepare(
            "UPDATE budget SET category=?, amount=?, date=?, budget_month=?
             WHERE id=? AND user_id=?"
        );
        $stmt->bind_param("sdssii", $category, $amount, $date, $budgetMonth, $id, $user_id);
        $stmt->execute();
        $_SESSION['flash'] = ['msg' => 'Budget updated!', 'type' => 'success'];
        header("Location: add_budget.php?month=$budgetMonth");
        exit();
    }
}

/* =========================
   DELETE BUDGET
========================= */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM budget WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $_SESSION['flash'] = ['msg' => 'Budget entry deleted.', 'type' => 'success'];
    header("Location: add_budget.php?month=$selectedMonth");
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
   EDIT MODE
========================= */
$editRow = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM budget WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $editId, $user_id);
    $stmt->execute();
    $editRow = $stmt->get_result()->fetch_assoc();
}

/* =========================
   FETCH BUDGETS + EXPENSE TOTALS
========================= */
$stmt = $conn->prepare(
    "SELECT b.id, b.category, b.amount AS budget_amount, b.date,
            COALESCE(SUM(e.amount), 0) AS spent
     FROM budget b
     LEFT JOIN expenses e
       ON e.user_id = b.user_id
       AND e.category = b.category
       AND e.expense_month = b.budget_month
     WHERE b.user_id=? AND b.budget_month=?
     GROUP BY b.id
     ORDER BY b.date DESC"
);
$stmt->bind_param("is", $user_id, $selectedMonth);
$stmt->execute();
$result = $stmt->get_result();

/* Monthly total budget */
$stmtTotal = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM budget WHERE user_id=? AND budget_month=?");
$stmtTotal->bind_param("is", $user_id, $selectedMonth);
$stmtTotal->execute();
$monthTotal = (float)$stmtTotal->get_result()->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget - Finance Manager</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="main-content">
<div class="page-wrapper">

    <div class="page-header">
        <h2>📅 Budget — <?= date('F Y', strtotime("$selectedMonth-01")) ?></h2>
        <span class="month-total">Total Budget: KES <?= number_format($monthTotal, 2) ?></span>
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
        <h3><?= $editRow ? '✏️ Edit Budget' : '➕ Add Budget' ?></h3>
        <input type="hidden" name="action"  value="<?= $editRow ? 'edit' : 'add' ?>">
        <?php if ($editRow): ?>
            <input type="hidden" name="edit_id" value="<?= (int)$editRow['id'] ?>">
        <?php endif; ?>

        <div class="form-row">
            <input type="number" step="0.01" min="0.01" name="amount"
                   placeholder="Budget Amount (KES)" required
                   value="<?= $editRow ? htmlspecialchars($editRow['amount']) : '' ?>">

            <input type="text" name="category"
                   placeholder="Category" required
                   value="<?= $editRow ? htmlspecialchars($editRow['category']) : '' ?>">

            <input type="date" name="date" required
                   value="<?= $editRow ? htmlspecialchars($editRow['date']) : '' ?>">

            <button type="submit"><?= $editRow ? '💾 Update' : '➕ Add Budget' ?></button>
            <?php if ($editRow): ?>
                <a href="add_budget.php?month=<?= $selectedMonth ?>" class="btn-cancel">✕ Cancel</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- BUDGET TABLE -->
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Budget (KES)</th>
                    <th>Spent (KES)</th>
                    <th>Remaining (KES)</th>
                    <th>Usage</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()):
                    $spent     = (float)$row['spent'];
                    $budget    = (float)$row['budget_amount'];
                    $remaining = $budget - $spent;
                    $usedPct   = $budget > 0 ? min(100, round(($spent / $budget) * 100)) : 0;
                    $barClass  = $usedPct >= 100 ? 'bar-danger' : ($usedPct >= 80 ? 'bar-warning' : 'bar-ok');
                ?>
                    <tr class="<?= $usedPct >= 100 ? 'row-exceeded' : '' ?>">
                        <td><?= htmlspecialchars($row['category']) ?></td>
                        <td><?= number_format($budget, 2) ?></td>
                        <td><?= number_format($spent, 2) ?></td>
                        <td class="<?= $remaining < 0 ? 'text-danger' : 'text-success' ?>">
                            <?= number_format($remaining, 2) ?>
                        </td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill <?= $barClass ?>" style="width:<?= $usedPct ?>%"></div>
                            </div>
                            <small><?= $usedPct ?>%</small>
                        </td>
                        <td><?= date('d M Y', strtotime($row['date'])) ?></td>
                        <td class="action-btns">
                            <a href="?month=<?= $selectedMonth ?>&edit=<?= (int)$row['id'] ?>" class="btn-edit">✏️ Edit</a>
                            <a href="?month=<?= $selectedMonth ?>&delete=<?= (int)$row['id'] ?>"
                               class="btn-delete"
                               onclick="return confirm('Delete this budget entry?')">🗑 Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" class="empty-row">No budget recorded for this month.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- CSV EXPORT -->
    <div class="export-bar">
        <a href="export.php?type=budget&month=<?= $selectedMonth ?>" class="btn-export">⬇ Export CSV</a>
    </div>

</div>
</div>
</body>
</html>