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
   ADD GOAL
========================= */
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $title       = trim($_POST['title']);
    $target      = (float)$_POST['target_amount'];
    $due_date = $_POST['due_date'];
    $notes       = trim($_POST['notes'] ?? '');

    if (empty($title) || $target <= 0 || empty($due_date)) {
        $flashMsg  = 'Please fill in all fields.';
        $flashType = 'error';
    } elseif ($due_date <= date('Y-m-d')) {
        $flashMsg  = 'Target date must be in the future.';
        $flashType = 'error';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO savings_goals (user_id, title, target_amount, current_amount, due_date, notes, status)
             VALUES (?, ?, ?, 0, ?, ?, 'active')"
        );
        $stmt->bind_param("isdss", $user_id, $title, $target, $due_date, $notes);
        $stmt->execute();
        $_SESSION['flash'] = ['msg' => 'Savings goal created!', 'type' => 'success'];
        header("Location: savings_goals.php");
        exit();
    }
}

/* =========================
   EDIT GOAL
========================= */
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id          = (int)$_POST['edit_id'];
    $title       = trim($_POST['title']);
    $target      = (float)$_POST['target_amount'];
    $due_date = $_POST['due_date'];
    $notes       = trim($_POST['notes'] ?? '');

    if (empty($title) || $target <= 0 || empty($due_date)) {
        $flashMsg  = 'Please fill in all fields.';
        $flashType = 'error';
    } else {
        $stmt = $conn->prepare(
            "UPDATE savings_goals SET title=?, target_amount=?, due_date=?, notes=?
             WHERE id=? AND user_id=?"
        );
        $stmt->bind_param("sdssii", $title, $target, $due_date, $notes, $id, $user_id);
        $stmt->execute();
        $_SESSION['flash'] = ['msg' => 'Goal updated!', 'type' => 'success'];
        header("Location: savings_goals.php");
        exit();
    }
}

/* =========================
   MARK COMPLETE
========================= */
if (isset($_GET['complete'])) {
    $id = (int)$_GET['complete'];
    $stmt = $conn->prepare("UPDATE savings_goals SET status='completed' WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $_SESSION['flash'] = ['msg' => '🎉 Goal marked as completed!', 'type' => 'success'];
    header("Location: savings_goals.php");
    exit();
}

/* =========================
   DELETE GOAL
========================= */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM savings_goals WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $_SESSION['flash'] = ['msg' => 'Goal deleted.', 'type' => 'success'];
    header("Location: savings_goals.php");
    exit();
}

/* =========================
   FLASH
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
    $stmt   = $conn->prepare("SELECT * FROM savings_goals WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $editId, $user_id);
    $stmt->execute();
    $editRow = $stmt->get_result()->fetch_assoc();
}

/* =========================
   FETCH ALL GOALS
========================= */
$stmt = $conn->prepare(
    "SELECT * FROM savings_goals WHERE user_id=?
     ORDER BY status ASC, due_date ASC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$goals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* Helper: months remaining */
function monthsRemaining($due_date) {
    $today  = new DateTime();
    $target = new DateTime($due_date);
    if ($target <= $today) return 0;
    $diff = $today->diff($target);
    return ($diff->y * 12) + $diff->m + ($diff->d > 0 ? 1 : 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savings Goals - Finance Manager</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="main-content">
<div class="page-wrapper">

    <div class="page-header">
        <h2>🎯 Savings Goals</h2>
    </div>

    <?php if ($flashMsg): ?>
        <div class="alert alert-<?= $flashType ?>"><?= htmlspecialchars($flashMsg) ?></div>
    <?php endif; ?>

    <!-- ADD / EDIT FORM -->
    <form class="finance-form" method="POST">
        <h3><?= $editRow ? '✏️ Edit Goal' : '➕ New Savings Goal' ?></h3>
        <input type="hidden" name="action"  value="<?= $editRow ? 'edit' : 'add' ?>">
        <?php if ($editRow): ?>
            <input type="hidden" name="edit_id" value="<?= (int)$editRow['id'] ?>">
        <?php endif; ?>

        <div class="form-row">
            <input type="text" name="title" placeholder="Goal name (e.g. New Laptop)" required
                   value="<?= $editRow ? htmlspecialchars($editRow['title']) : '' ?>">

            <input type="number" name="target_amount" step="0.01" min="1"
                   placeholder="Target Amount (KES)" required
                   value="<?= $editRow ? htmlspecialchars($editRow['target_amount']) : '' ?>">

            <input type="date" name="due_date"
                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required
                   value="<?= $editRow ? htmlspecialchars($editRow['due_date']) : '' ?>">

            <input type="text" name="notes" placeholder="Notes (optional)"
                   value="<?= $editRow ? htmlspecialchars($editRow['notes'] ?? '') : '' ?>">

            <button type="submit"><?= $editRow ? '💾 Update Goal' : '➕ Create Goal' ?></button>
            <?php if ($editRow): ?>
                <a href="savings_goals.php" class="btn-cancel">✕ Cancel</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- GOALS LIST -->
    <?php if (empty($goals)): ?>
        <div class="empty-goals">
            <p>🎯 No savings goals yet. Create your first one above!</p>
        </div>
    <?php else: ?>

    <?php
    $activeGoals    = array_filter($goals, fn($g) => $g['status'] === 'active');
    $completedGoals = array_filter($goals, fn($g) => $g['status'] === 'completed');
    ?>

    <!-- ACTIVE GOALS -->
    <?php if (!empty($activeGoals)): ?>
    <h3 class="section-label" style="margin-bottom:16px;">🏃 Active Goals</h3>
    <div class="goals-detail-grid">
        <?php foreach ($activeGoals as $g):
            $target   = (float)$g['target_amount'];
            $current  = (float)$g['current_amount'];
            $remaining = max(0, $target - $current);
            $pct      = $target > 0 ? min(100, round(($current / $target) * 100)) : 0;
            $barClass = $pct >= 100 ? 'bar-ok' : ($pct >= 60 ? 'bar-warning' : 'bar-low');
            $months   = monthsRemaining($g['due_date']);
            $perMonth = ($months > 0 && $remaining > 0) ? ceil($remaining / $months) : 0;
            $daysLeft = (new DateTime())->diff(new DateTime($g['due_date']))->days;
            $isOverdue = strtotime($g['due_date']) < time();
        ?>
        <div class="goal-detail-card <?= $isOverdue ? 'goal-overdue' : '' ?>">
            <div class="goal-detail-header">
                <h4><?= htmlspecialchars($g['title']) ?></h4>
                <?php if ($isOverdue): ?>
                    <span class="badge badge-expense">⚠️ Overdue</span>
                <?php elseif ($pct >= 100): ?>
                    <span class="badge badge-income">✅ Ready</span>
                <?php else: ?>
                    <span class="badge" style="background:#eff6ff;color:#2563eb;"><?= $daysLeft ?> days left</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($g['notes'])): ?>
                <p class="goal-notes"><?= htmlspecialchars($g['notes']) ?></p>
            <?php endif; ?>

            <!-- Progress -->
            <div class="progress-bar" style="margin:12px 0 4px;">
                <div class="progress-fill <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
            </div>
            <div class="goal-progress-labels">
                <span>KES <?= number_format($current, 2) ?> saved</span>
                <span><?= $pct ?>%</span>
                <span>KES <?= number_format($target, 2) ?> goal</span>
            </div>

            <!-- Stats -->
            <div class="goal-stats">
                <div class="goal-stat">
                    <span class="stat-label">Still needed</span>
                    <span class="stat-value text-danger">KES <?= number_format($remaining, 2) ?></span>
                </div>
                <div class="goal-stat">
                    <span class="stat-label">Target date</span>
                    <span class="stat-value"><?= date('d M Y', strtotime($g['due_date'])) ?></span>
                </div>
                <div class="goal-stat">
                    <span class="stat-label">Months left</span>
                    <span class="stat-value"><?= $months > 0 ? $months : '—' ?></span>
                </div>
                <div class="goal-stat">
                    <span class="stat-label">Save per month</span>
                    <span class="stat-value <?= $perMonth > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= $perMonth > 0 ? 'KES ' . number_format($perMonth, 2) : '🎉 Done!' ?>
                    </span>
                </div>
            </div>

            <!-- Actions -->
            <div class="goal-detail-actions">
                <a href="add_savings.php" class="btn-edit">➕ Add Savings</a>
                <a href="?edit=<?= (int)$g['id'] ?>" class="btn-edit">✏️ Edit</a>
                <?php if ($pct >= 100): ?>
                    <a href="?complete=<?= (int)$g['id'] ?>"
                       onclick="return confirm('Mark as completed?')"
                       class="btn-complete">🎉 Mark Complete</a>
                <?php endif; ?>
                <a href="?delete=<?= (int)$g['id'] ?>"
                   onclick="return confirm('Delete this goal permanently?')"
                   class="btn-delete">🗑 Delete</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- COMPLETED GOALS -->
    <?php if (!empty($completedGoals)): ?>
    <h3 class="section-label" style="margin:32px 0 16px;">🏆 Completed Goals</h3>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr><th>Goal</th><th>Amount Saved</th><th>Target</th><th>Notes</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($completedGoals as $g): ?>
                <tr>
                    <td>🏆 <?= htmlspecialchars($g['title']) ?></td>
                    <td class="text-success">KES <?= number_format($g['current_amount'], 2) ?></td>
                    <td>KES <?= number_format($g['target_amount'], 2) ?></td>
                    <td><?= htmlspecialchars($g['notes'] ?? '—') ?></td>
                    <td>
                        <a href="?delete=<?= (int)$g['id'] ?>"
                           onclick="return confirm('Delete this completed goal?')"
                           class="btn-delete">🗑 Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php endif; ?>
     <!-- PDF EXPORT -->
    <div class="export-bar" style="margin-top:24px;">
        <a href="export_pdf.php?type=goals" class="btn-export" style="background:#7c3aed;">📄 Export Goals PDF</a>
        <a href="export_pdf.php" class="btn-export" style="background:#2563eb;">📊 Full Monthly Report</a>
    </div>
</div>
</div>
</body>
</html>