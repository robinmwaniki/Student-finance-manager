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
   MONTH HANDLING
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
   ADD INCOME
========================= */
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $amount = (float)$_POST['amount'];
    $source = trim($_POST['source']);
    $date   = $_POST['date'];

    if ($amount <= 0 || empty($source) || empty($date)) {
        $flashMsg  = 'Please fill in all fields correctly.';
        $flashType = 'error';
    } else {
        $incomeMonth = date('Y-m', strtotime($date));
        $stmt = $conn->prepare(
            "INSERT INTO income (user_id, source, amount, date, income_month)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("isdss", $user_id, $source, $amount, $date, $incomeMonth);
        $stmt->execute();
        $_SESSION['flash'] = ['msg' => 'Income added successfully!', 'type' => 'success'];
        header("Location: add_income.php?month=$incomeMonth");
        exit();
    }
}

/* =========================
   EDIT INCOME
========================= */
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id     = (int)$_POST['edit_id'];
    $amount = (float)$_POST['amount'];
    $source = trim($_POST['source']);
    $date   = $_POST['date'];

    if ($amount <= 0 || empty($source) || empty($date)) {
        $flashMsg  = 'Please fill in all fields correctly.';
        $flashType = 'error';
    } else {
        $incomeMonth = date('Y-m', strtotime($date));
        $stmt = $conn->prepare(
            "UPDATE income SET source=?, amount=?, date=?, income_month=?
             WHERE id=? AND user_id=?"
        );
        $stmt->bind_param("sdssii", $source, $amount, $date, $incomeMonth, $id, $user_id);
        $stmt->execute();
        $_SESSION['flash'] = ['msg' => 'Income updated!', 'type' => 'success'];
        header("Location: add_income.php?month=$incomeMonth");
        exit();
    }
}

/* =========================
   DELETE INCOME
========================= */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM income WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $_SESSION['flash'] = ['msg' => 'Income entry deleted.', 'type' => 'success'];
    header("Location: add_income.php?month=$selectedMonth");
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
    $stmt = $conn->prepare("SELECT * FROM income WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $editId, $user_id);
    $stmt->execute();
    $editRow = $stmt->get_result()->fetch_assoc();
}

/* =========================
   FILTERS
========================= */
$filterSource  = trim($_GET['filter_source'] ?? '');
$filterDateFrom = trim($_GET['date_from'] ?? '');
$filterDateTo   = trim($_GET['date_to'] ?? '');

// Validate dates
if ($filterDateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateFrom)) $filterDateFrom = '';
if ($filterDateTo   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateTo))   $filterDateTo   = '';

// All distinct sources for this month (for dropdown)
$srcStmt = $conn->prepare(
    "SELECT DISTINCT source FROM income
     WHERE user_id = ? AND income_month = ?
     ORDER BY source ASC"
);
$srcStmt->bind_param("is", $user_id, $selectedMonth);
$srcStmt->execute();
$allSources = $srcStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$filtersActive = $filterSource !== '' || $filterDateFrom !== '' || $filterDateTo !== '';

/* =========================
   FETCH INCOME (with filters)
========================= */
// Build query dynamically based on active filters
$sql    = "SELECT id, source, amount, date FROM income WHERE user_id=? AND income_month=?";
$types  = "is";
$params = [$user_id, $selectedMonth];

if ($filterSource !== '') {
    $sql    .= " AND source = ?";
    $types  .= "s";
    $params[] = $filterSource;
}
if ($filterDateFrom !== '') {
    $sql    .= " AND date >= ?";
    $types  .= "s";
    $params[] = $filterDateFrom;
}
if ($filterDateTo !== '') {
    $sql    .= " AND date <= ?";
    $types  .= "s";
    $params[] = $filterDateTo;
}
$sql .= " ORDER BY date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

/* =========================
   MONTHLY TOTAL (always full month, ignores filters)
========================= */
$stmtTotal = $conn->prepare(
    "SELECT COALESCE(SUM(amount),0) AS total FROM income WHERE user_id=? AND income_month=?"
);
$stmtTotal->bind_param("is", $user_id, $selectedMonth);
$stmtTotal->execute();
$monthTotal = (float)$stmtTotal->get_result()->fetch_assoc()['total'];

/* Filtered total (shown when filters are active) */
$filteredTotal = 0;
$filteredRows  = $result->fetch_all(MYSQLI_ASSOC);
foreach ($filteredRows as $r) $filteredTotal += (float)$r['amount'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income - Finance Manager</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="main-content">
<div class="page-wrapper">

    <div class="page-header">
        <h2>💰 Income — <?= date('F Y', strtotime("$selectedMonth-01")) ?></h2>
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
        <h3><?= $editRow ? '✏️ Edit Income' : '➕ Add Income' ?></h3>
        <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
        <?php if ($editRow): ?>
            <input type="hidden" name="edit_id" value="<?= (int)$editRow['id'] ?>">
        <?php endif; ?>

        <div class="form-row">
            <input type="number" step="0.01" min="0.01" name="amount"
                   placeholder="Amount (KES)" required
                   value="<?= $editRow ? htmlspecialchars($editRow['amount']) : '' ?>">

            <input type="text" name="source"
                   placeholder="Source (e.g. Salary, Freelance)" required
                   value="<?= $editRow ? htmlspecialchars($editRow['source']) : '' ?>">

            <input type="date" name="date" required
                   value="<?= $editRow ? htmlspecialchars($editRow['date']) : '' ?>">

            <button type="submit"><?= $editRow ? '💾 Update' : '➕ Add Income' ?></button>
            <?php if ($editRow): ?>
                <a href="add_income.php?month=<?= $selectedMonth ?>" class="btn-cancel">✕ Cancel</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- FILTER FORM -->
    <form method="GET" class="filter-form">
        <input type="hidden" name="month" value="<?= $selectedMonth ?>">

        <!-- Source / Category filter -->
        <select name="filter_source">
            <option value="">— All Sources —</option>
            <?php foreach ($allSources as $src): ?>
                <option value="<?= htmlspecialchars($src['source']) ?>"
                    <?= $filterSource === $src['source'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($src['source']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Date from -->
        <input type="date" name="date_from"
               value="<?= htmlspecialchars($filterDateFrom) ?>"
               title="From date"
               style="padding:9px 12px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; background:#fff; color:#0f172a;">

        <!-- Date to -->
        <input type="date" name="date_to"
               value="<?= htmlspecialchars($filterDateTo) ?>"
               title="To date"
               style="padding:9px 12px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; background:#fff; color:#0f172a;">

        <button type="submit"
                style="padding:9px 18px; background:#1e293b; color:#fff; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer;">
            🔍 Filter
        </button>

        <?php if ($filtersActive): ?>
            <a href="?month=<?= $selectedMonth ?>" class="btn-cancel">✕ Clear</a>
        <?php endif; ?>
    </form>

    <!-- FILTER SUMMARY -->
    <?php if ($filtersActive): ?>
    <div style="margin-bottom:12px; font-size:13px; color:#475569; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
        <span>🔎 Showing filtered results:</span>
        <?php if ($filterSource !== ''): ?>
            <span style="background:#eff6ff; color:#1d4ed8; padding:3px 10px; border-radius:20px; font-weight:600;"><?= htmlspecialchars($filterSource) ?></span>
        <?php endif; ?>
        <?php if ($filterDateFrom !== ''): ?>
            <span style="background:#f0fdf4; color:#15803d; padding:3px 10px; border-radius:20px; font-weight:600;">From: <?= date('d M Y', strtotime($filterDateFrom)) ?></span>
        <?php endif; ?>
        <?php if ($filterDateTo !== ''): ?>
            <span style="background:#f0fdf4; color:#15803d; padding:3px 10px; border-radius:20px; font-weight:600;">To: <?= date('d M Y', strtotime($filterDateTo)) ?></span>
        <?php endif; ?>
        <span style="margin-left:auto; font-weight:600; color:#0f172a;">
            Filtered Total: KES <?= number_format($filteredTotal, 2) ?>
            (<?= count($filteredRows) ?> <?= count($filteredRows) === 1 ? 'entry' : 'entries' ?>)
        </span>
    </div>
    <?php endif; ?>

    <!-- INCOME TABLE -->
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Source / Category</th>
                    <th>Amount (KES)</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($filteredRows)): ?>
                <?php foreach ($filteredRows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['source']) ?></td>
                        <td><?= number_format($row['amount'], 2) ?></td>
                        <td><?= date('d M Y', strtotime($row['date'])) ?></td>
                        <td class="action-btns">
                            <a href="?month=<?= $selectedMonth ?>&edit=<?= (int)$row['id'] ?>" class="btn-edit">✏️ Edit</a>
                            <a href="?month=<?= $selectedMonth ?>&delete=<?= (int)$row['id'] ?>"
                               class="btn-delete"
                               onclick="return confirm('Delete this income entry?')">🗑 Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="empty-row">
                        <?= $filtersActive ? 'No income entries match your filters.' : 'No income recorded for this month.' ?>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
            <?php if ($filtersActive && !empty($filteredRows)): ?>
            <tfoot>
                <tr style="background:#1e293b; color:#f8fafc;">
                    <td style="padding:12px 16px; font-weight:600;">Filtered Total</td>
                    <td style="padding:12px 16px; font-weight:700;">KES <?= number_format($filteredTotal, 2) ?></td>
                    <td colspan="2" style="padding:12px 16px; font-size:13px; color:#94a3b8;"><?= count($filteredRows) ?> <?= count($filteredRows) === 1 ? 'entry' : 'entries' ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>

    <!-- CSV EXPORT -->
    <div class="export-bar">
        <a href="export.php?type=income&month=<?= $selectedMonth ?><?= $filterSource !== '' ? '&source=' . urlencode($filterSource) : '' ?><?= $filterDateFrom !== '' ? '&date_from=' . $filterDateFrom : '' ?><?= $filterDateTo !== '' ? '&date_to=' . $filterDateTo : '' ?>" class="btn-export">⬇ Export CSV</a>
    </div>

</div>
</div>
</body>
</html>