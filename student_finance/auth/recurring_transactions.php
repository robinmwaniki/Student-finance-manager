<?php
session_start();
include 'config.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id   = $_SESSION['user_id'];
$userName = $_SESSION['fullname'] ?? '';

include 'sidebar.php';
$flashMsg  = '';
$flashType = '';

/* =========================
   ADD RECURRING TRANSACTION
========================= */
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $type     = $_POST['type'];     // 'income' or 'expense'
    $label    = trim($_POST['label']);
    $amount   = (float)$_POST['amount'];
    $category = trim($_POST['category']);
    $day      = (int)$_POST['day_of_month'];  // 1–28

    if (!in_array($type, ['income', 'expense']) || empty($label) || $amount <= 0 || $day < 1 || $day > 28) {
        $flashMsg  = 'Please fill in all fields correctly. Day must be between 1 and 28.';
        $flashType = 'error';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO recurring_transactions (user_id, type, label, amount, category, day_of_month, active)
             VALUES (?, ?, ?, ?, ?, ?, 1)"
        );
        $stmt->bind_param("issdsi", $user_id, $type, $label, $amount, $category, $day);
        $stmt->execute();
        $_SESSION['flash'] = ['msg' => 'Recurring transaction added!', 'type' => 'success'];
        header("Location: recurring_transactions.php");
        exit();
    }
}

/* =========================
   TOGGLE ACTIVE / PAUSE
========================= */
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $conn->prepare(
        "UPDATE recurring_transactions SET active = NOT active
         WHERE id=? AND user_id=?"
    );
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    header("Location: recurring_transactions.php");
    exit();
}

/* =========================
   DELETE RECURRING
========================= */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM recurring_transactions WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $_SESSION['flash'] = ['msg' => 'Recurring transaction removed.', 'type' => 'success'];
    header("Location: recurring_transactions.php");
    exit();
}

/* =========================
   APPLY THIS MONTH MANUALLY
========================= */
if (isset($_GET['apply'])) {
    $id = (int)$_GET['apply'];
    $stmt = $conn->prepare(
        "SELECT * FROM recurring_transactions WHERE id=? AND user_id=? AND active=1"
    );
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $rec = $stmt->get_result()->fetch_assoc();

    if ($rec) {
        $currentMonth = date('Y-m');
        $date         = date('Y-m-') . str_pad($rec['day_of_month'], 2, '0', STR_PAD_LEFT);

        if ($rec['type'] === 'income') {
            $ins = $conn->prepare(
                "INSERT INTO income (user_id, source, amount, date, income_month)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $ins->bind_param("isdss", $user_id, $rec['label'], $rec['amount'], $date, $currentMonth);
            $ins->execute();
        } else {
            $ins = $conn->prepare(
                "INSERT INTO expenses (user_id, category, amount, date, expense_month)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $ins->bind_param("isdss", $user_id, $rec['category'], $rec['amount'], $date, $currentMonth);
            $ins->execute();
        }

        /* Log it */
        $logStmt = $conn->prepare(
            "INSERT INTO recurring_log (recurring_id, user_id, applied_month)
             VALUES (?, ?, ?)"
        );
        $logStmt->bind_param("iis", $id, $user_id, $currentMonth);
        $logStmt->execute();

        $_SESSION['flash'] = ['msg' => "Applied \"{$rec['label']}\" to " . date('F Y') . "!", 'type' => 'success'];
    }
    header("Location: recurring_transactions.php");
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
   FETCH ALL RECURRING
========================= */
$stmt = $conn->prepare(
    "SELECT r.*,
            (SELECT applied_month FROM recurring_log
             WHERE recurring_id = r.id AND user_id = r.user_id
             ORDER BY applied_at DESC LIMIT 1) AS last_applied
     FROM recurring_transactions r
     WHERE r.user_id = ?
     ORDER BY r.type ASC, r.label ASC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$currentMonth = date('Y-m');

/* Split into income and expense for display */
$incomeRows  = array_filter($rows, fn($r) => $r['type'] === 'income');
$expenseRows = array_filter($rows, fn($r) => $r['type'] === 'expense');

/* Monthly totals */
$recurringIncome  = array_sum(array_column(array_filter($rows, fn($r) => $r['type'] === 'income'  && $r['active']), 'amount'));
$recurringExpense = array_sum(array_column(array_filter($rows, fn($r) => $r['type'] === 'expense' && $r['active']), 'amount'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recurring Transactions - Finance Manager</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="main-content">
<div class="page-wrapper">

    <div class="page-header">
        <h2>🔁 Recurring Transactions</h2>
    </div>

    <!-- FLASH -->
    <?php if ($flashMsg): ?>
        <div class="alert alert-<?= $flashType ?>"><?= htmlspecialchars($flashMsg) ?></div>
    <?php endif; ?>

    <!-- SUMMARY CARDS -->
    <div class="dashboard-cards" style="margin-bottom:28px;">
        <div class="card income-card">
            <h3>💰 Monthly Recurring Income</h3>
            <p>KES <?= number_format($recurringIncome, 2) ?></p>
            <small>Active only</small>
        </div>
        <div class="card expense-card">
            <h3>💸 Monthly Recurring Expenses</h3>
            <p>KES <?= number_format($recurringExpense, 2) ?></p>
            <small>Active only</small>
        </div>
        <div class="card balance-card <?= ($recurringIncome - $recurringExpense) >= 0 ? 'positive' : 'negative' ?>">
            <h3>💼 Net Recurring</h3>
            <p>KES <?= number_format($recurringIncome - $recurringExpense, 2) ?></p>
            <small>Per month estimate</small>
        </div>
    </div>

    <!-- ADD FORM -->
    <form class="finance-form" method="POST">
        <h3>➕ Add Recurring Transaction</h3>
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <select name="type" required>
                <option value="">— Type —</option>
                <option value="income">💰 Income</option>
                <option value="expense">💸 Expense</option>
            </select>
            <input type="text"   name="label"        placeholder="Label (e.g. Rent, Salary)" required>
            <input type="text"   name="category"     placeholder="Category">
            <input type="number" name="amount" step="0.01" min="0.01" placeholder="Amount (KES)" required>
            <input type="number" name="day_of_month" min="1" max="28" placeholder="Day of month (1–28)" required>
            <button type="submit">➕ Add</button>
        </div>
        <small style="color:#94a3b8; margin-top:6px; display:block;">
            💡 Day is capped at 28 to work for all months. Each active transaction can be applied to the current month with one click.
        </small>
    </form>

    <!-- INCOME RECURRING -->
    <div class="recurring-section">
        <h3 class="section-label income-label">💰 Recurring Income</h3>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Label</th>
                        <th>Category</th>
                        <th>Amount (KES)</th>
                        <th>Day</th>
                        <th>Status</th>
                        <th>Last Applied</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($incomeRows)): ?>
                    <?php foreach ($incomeRows as $r): ?>
                    <tr class="<?= !$r['active'] ? 'row-paused' : '' ?>">
                        <td><?= htmlspecialchars($r['label']) ?></td>
                        <td><?= htmlspecialchars($r['category'] ?: '—') ?></td>
                        <td><?= number_format($r['amount'], 2) ?></td>
                        <td><?= (int)$r['day_of_month'] ?><?= date('S', mktime(0,0,0,1,(int)$r['day_of_month'])) ?> of month</td>
                        <td>
                            <?php if ($r['active']): ?>
                                <span class="badge badge-income">✅ Active</span>
                            <?php else: ?>
                                <span class="badge badge-expense">⏸ Paused</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $r['last_applied']
                                ? date('M Y', strtotime($r['last_applied'] . '-01'))
                                : '<span style="color:#94a3b8">Never</span>' ?>
                        </td>
                        <td class="action-btns">
                            <?php if ($r['active'] && $r['last_applied'] !== $currentMonth): ?>
                                <a href="?apply=<?= (int)$r['id'] ?>" class="btn-apply"
                                   onclick="return confirm('Apply to <?= date('F Y') ?>?')">▶ Apply</a>
                            <?php elseif ($r['active']): ?>
                                <span class="btn-applied">✔ Applied</span>
                            <?php endif; ?>
                            <a href="?toggle=<?= (int)$r['id'] ?>" class="btn-edit">
                                <?= $r['active'] ? '⏸ Pause' : '▶ Resume' ?>
                            </a>
                            <a href="?delete=<?= (int)$r['id'] ?>" class="btn-delete"
                               onclick="return confirm('Delete this recurring transaction?')">🗑 Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="empty-row">No recurring income set up.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- EXPENSE RECURRING -->
    <div class="recurring-section" style="margin-top:28px;">
        <h3 class="section-label expense-label">💸 Recurring Expenses</h3>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Label</th>
                        <th>Category</th>
                        <th>Amount (KES)</th>
                        <th>Day</th>
                        <th>Status</th>
                        <th>Last Applied</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($expenseRows)): ?>
                    <?php foreach ($expenseRows as $r): ?>
                    <tr class="<?= !$r['active'] ? 'row-paused' : '' ?>">
                        <td><?= htmlspecialchars($r['label']) ?></td>
                        <td><?= htmlspecialchars($r['category'] ?: '—') ?></td>
                        <td><?= number_format($r['amount'], 2) ?></td>
                        <td><?= (int)$r['day_of_month'] ?><?= date('S', mktime(0,0,0,1,(int)$r['day_of_month'])) ?> of month</td>
                        <td>
                            <?php if ($r['active']): ?>
                                <span class="badge badge-income">✅ Active</span>
                            <?php else: ?>
                                <span class="badge badge-expense">⏸ Paused</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $r['last_applied']
                                ? date('M Y', strtotime($r['last_applied'] . '-01'))
                                : '<span style="color:#94a3b8">Never</span>' ?>
                        </td>
                        <td class="action-btns">
                            <?php if ($r['active'] && $r['last_applied'] !== $currentMonth): ?>
                                <a href="?apply=<?= (int)$r['id'] ?>" class="btn-apply"
                                   onclick="return confirm('Apply to <?= date('F Y') ?>?')">▶ Apply</a>
                            <?php elseif ($r['active']): ?>
                                <span class="btn-applied">✔ Applied</span>
                            <?php endif; ?>
                            <a href="?toggle=<?= (int)$r['id'] ?>" class="btn-edit">
                                <?= $r['active'] ? '⏸ Pause' : '▶ Resume' ?>
                            </a>
                            <a href="?delete=<?= (int)$r['id'] ?>" class="btn-delete"
                               onclick="return confirm('Delete this recurring transaction?')">🗑 Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="empty-row">No recurring expenses set up.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</div>
</body>
</html>