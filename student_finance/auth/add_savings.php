<?php
session_start();
include 'config.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
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
   ADD SAVINGS
========================= */
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $goal_id  = (int)($_POST['goal_id'] ?? 0);
    $new_goal = trim($_POST['new_goal'] ?? '');
    $amount   = (float)$_POST['amount'];
    $date     = $_POST['date'];

    if ($amount <= 0 || empty($date)) {
        $flashMsg  = 'Please enter a valid amount and date.';
        $flashType = 'error';
    } elseif (empty($new_goal) && $goal_id <= 0) {
        $flashMsg  = 'Please select an existing goal or enter a new one.';
        $flashType = 'error';
    } else {
        $savingsMonth = date('Y-m', strtotime($date));

        if (!empty($new_goal)) {
            /* Create new goal */
            $stmt = $conn->prepare(
                "INSERT INTO savings_goals (user_id, title, current_amount)
                 VALUES (?, ?, ?)"
            );
            $stmt->bind_param("isd", $user_id, $new_goal, $amount);
            $stmt->execute();
            $goal_title = $new_goal;
        } else {
            /* Add to existing goal */
            $stmt = $conn->prepare(
                "UPDATE savings_goals
                 SET current_amount = current_amount + ?
                 WHERE id=? AND user_id=?"
            );
            $stmt->bind_param("dii", $amount, $goal_id, $user_id);
            $stmt->execute();

            $stmt2 = $conn->prepare("SELECT title FROM savings_goals WHERE id=? AND user_id=?");
            $stmt2->bind_param("ii", $goal_id, $user_id);
            $stmt2->execute();
            $goalRow    = $stmt2->get_result()->fetch_assoc();
            $goal_title = $goalRow['title'] ?? 'Unknown Goal';
        }

        /* Insert into savings log */
        $stmt3 = $conn->prepare(
            "INSERT INTO savings (user_id, goal, amount, date, savings_month)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt3->bind_param("isdss", $user_id, $goal_title, $amount, $date, $savingsMonth);
        $stmt3->execute();

        $_SESSION['flash'] = ['msg' => 'Savings recorded!', 'type' => 'success'];
        header("Location: add_savings.php?month=$savingsMonth");
        exit();
    }
}

/* =========================
   EDIT SAVINGS
========================= */
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id         = (int)$_POST['edit_id'];
    $amount     = (float)$_POST['amount'];
    $goal_title = trim($_POST['goal_title']);
    $date       = $_POST['date'];

    if ($amount <= 0 || empty($goal_title) || empty($date)) {
        $flashMsg  = 'Please fill in all fields correctly.';
        $flashType = 'error';
    } else {
        $savingsMonth = date('Y-m', strtotime($date));

        /* Fetch old amount to recalculate goal total */
        $stmtOld = $conn->prepare("SELECT amount, goal FROM savings WHERE id=? AND user_id=?");
        $stmtOld->bind_param("ii", $id, $user_id);
        $stmtOld->execute();
        $oldRow = $stmtOld->get_result()->fetch_assoc();

        if ($oldRow) {
            $diff = $amount - (float)$oldRow['amount'];
            /* Update the savings_goals current_amount accordingly */
            $stmtGoalUpd = $conn->prepare(
                "UPDATE savings_goals SET current_amount = current_amount + ?
                 WHERE user_id=? AND title=?"
            );
            $stmtGoalUpd->bind_param("dis", $diff, $user_id, $oldRow['goal']);
            $stmtGoalUpd->execute();
        }

        $stmt = $conn->prepare(
            "UPDATE savings SET goal=?, amount=?, date=?, savings_month=?
             WHERE id=? AND user_id=?"
        );
        $stmt->bind_param("sdssii", $goal_title, $amount, $date, $savingsMonth, $id, $user_id);
        $stmt->execute();

        $_SESSION['flash'] = ['msg' => 'Savings updated!', 'type' => 'success'];
        header("Location: add_savings.php?month=$savingsMonth");
        exit();
    }
}

/* =========================
   DELETE SAVINGS — also subtracts from goal total
========================= */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    /* Fetch the entry first so we can subtract from goal */
    $stmtFetch = $conn->prepare("SELECT amount, goal FROM savings WHERE id=? AND user_id=?");
    $stmtFetch->bind_param("ii", $id, $user_id);
    $stmtFetch->execute();
    $delRow = $stmtFetch->get_result()->fetch_assoc();

    if ($delRow) {
        /* Subtract from savings_goals */
        $stmtGoal = $conn->prepare(
            "UPDATE savings_goals
             SET current_amount = GREATEST(0, current_amount - ?)
             WHERE user_id=? AND title=?"
        );
        $stmtGoal->bind_param("dis", $delRow['amount'], $user_id, $delRow['goal']);
        $stmtGoal->execute();

        /* Delete the savings entry */
        $stmtDel = $conn->prepare("DELETE FROM savings WHERE id=? AND user_id=?");
        $stmtDel->bind_param("ii", $id, $user_id);
        $stmtDel->execute();
    }

    $_SESSION['flash'] = ['msg' => 'Savings entry deleted.', 'type' => 'success'];
    header("Location: add_savings.php?month=$selectedMonth");
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
    $stmt   = $conn->prepare("SELECT * FROM savings WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $editId, $user_id);
    $stmt->execute();
    $editRow = $stmt->get_result()->fetch_assoc();
}

/* =========================
   FETCH ACTIVE GOALS
========================= */
$stmtGoals = $conn->prepare(
    "SELECT id, title, target_amount, current_amount
     FROM savings_goals
     WHERE user_id=? AND status='active'
     ORDER BY title ASC"
);
$stmtGoals->bind_param("i", $user_id);
$stmtGoals->execute();
$goalsResult = $stmtGoals->get_result();
$goals = $goalsResult->fetch_all(MYSQLI_ASSOC);

/* =========================
   FETCH MONTHLY SAVINGS
========================= */
$stmt = $conn->prepare(
    "SELECT id, goal, amount, date
     FROM savings
     WHERE user_id=? AND savings_month=?
     ORDER BY date DESC"
);
$stmt->bind_param("is", $user_id, $selectedMonth);
$stmt->execute();
$result = $stmt->get_result();

/* Monthly total */
$stmtTotal = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM savings WHERE user_id=? AND savings_month=?");
$stmtTotal->bind_param("is", $user_id, $selectedMonth);
$stmtTotal->execute();
$monthTotal = (float)$stmtTotal->get_result()->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savings - Finance Manager</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="main-content">
<div class="page-wrapper">

    <div class="page-header">
        <h2>🏦 Savings — <?= date('F Y', strtotime("$selectedMonth-01")) ?></h2>
        <span class="month-total">This Month: KES <?= number_format($monthTotal, 2) ?></span>
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

    <!-- SAVINGS GOALS OVERVIEW -->
    <?php if (!empty($goals)): ?>
    <div class="goals-overview">
        <h3>🎯 Savings Goals</h3>
        <div class="goals-grid">
        <?php foreach ($goals as $g):
            $target  = (float)$g['target_amount'];
            $current = (float)$g['current_amount'];
            $pct     = ($target > 0) ? min(100, round(($current / $target) * 100)) : 0;
            $barClass = $pct >= 100 ? 'bar-ok' : ($pct >= 60 ? 'bar-warning' : 'bar-low');
        ?>
            <div class="goal-card">
                <h4><?= htmlspecialchars($g['title']) ?></h4>
                <p>KES <?= number_format($current, 2) ?> / <?= $target > 0 ? 'KES ' . number_format($target, 2) : 'No target set' ?></p>
                <div class="progress-bar">
                    <div class="progress-fill <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
                </div>
                <small><?= $pct ?>% <?= $pct >= 100 ? '🎉 Goal Reached!' : 'saved' ?></small>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ADD / EDIT FORM -->
    <form class="finance-form" method="POST">
        <h3><?= $editRow ? '✏️ Edit Savings Entry' : '➕ Add Savings' ?></h3>
        <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
        <?php if ($editRow): ?>
            <input type="hidden" name="edit_id" value="<?= (int)$editRow['id'] ?>">
        <?php endif; ?>

        <?php if ($editRow): ?>
            <div class="form-row">
                <input type="text" name="goal_title" placeholder="Goal Name" required
                       value="<?= htmlspecialchars($editRow['goal']) ?>">
                <input type="number" step="0.01" min="0.01" name="amount"
                       placeholder="Amount (KES)" required
                       value="<?= htmlspecialchars($editRow['amount']) ?>">
                <input type="date" name="date" required
                       value="<?= htmlspecialchars($editRow['date']) ?>">
                <button type="submit">💾 Update</button>
                <a href="add_savings.php?month=<?= $selectedMonth ?>" class="btn-cancel">✕ Cancel</a>
            </div>
        <?php else: ?>
            <div class="form-row">
                <select name="goal_id">
                    <option value="">— Select Existing Goal —</option>
                    <?php foreach ($goals as $g): ?>
                        <option value="<?= (int)$g['id'] ?>">
                            <?= htmlspecialchars($g['title']) ?>
                            (<?= number_format($g['current_amount'], 2) ?>
                            <?= $g['target_amount'] > 0 ? '/ ' . number_format($g['target_amount'], 2) : '' ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="new_goal" placeholder="Or type new goal name">
                <input type="number" step="0.01" min="0.01" name="amount"
                       placeholder="Amount Saved (KES)" required>
                <input type="date" name="date" required>
                <button type="submit">➕ Add Savings</button>
            </div>
        <?php endif; ?>
    </form>

    <!-- SAVINGS TABLE -->
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Goal</th>
                    <th>Amount (KES)</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['goal']) ?></td>
                        <td><?= number_format($row['amount'], 2) ?></td>
                        <td><?= date('d M Y', strtotime($row['date'])) ?></td>
                        <td class="action-btns">
                            <a href="?month=<?= $selectedMonth ?>&edit=<?= (int)$row['id'] ?>" class="btn-edit">✏️ Edit</a>
                            <a href="?month=<?= $selectedMonth ?>&delete=<?= (int)$row['id'] ?>"
                               class="btn-delete"
                               onclick="return confirm('Delete this savings entry?')">🗑 Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" class="empty-row">No savings recorded for this month.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- CSV EXPORT -->
    <div class="export-bar">
        <a href="export.php?type=savings&month=<?= $selectedMonth ?>" class="btn-export">⬇ Export CSV</a>
    <a href="export_pdf.php?month=<?= $selectedMonth ?>" class="btn-export" style="background:#2563eb;">📄 Export PDF</a>
</div>
    </div>

    
</div>
</div>
</body>
</html>