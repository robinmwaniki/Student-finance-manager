<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

/* =========================
   COPY BUDGET — called via GET
========================= */
$fromMonth = $_GET['from'] ?? date('Y-m', strtotime('-1 month'));
$toMonth   = $_GET['to']   ?? date('Y-m');

// Validate formats
if (!preg_match('/^\d{4}-\d{2}$/', $fromMonth)) $fromMonth = date('Y-m', strtotime('-1 month'));
if (!preg_match('/^\d{4}-\d{2}$/', $toMonth))   $toMonth   = date('Y-m');

// Prevent copying to past months or same month
if ($toMonth <= $fromMonth) {
    $_SESSION['flash'] = ['msg' => 'Cannot copy to a past or same month.', 'type' => 'error'];
    header("Location: add_budget.php?month=$toMonth");
    exit();
}

// Check if target month already has budget entries
$stmtCheck = $conn->prepare(
    "SELECT COUNT(*) AS cnt FROM budget WHERE user_id=? AND budget_month=?"
);
$stmtCheck->bind_param("is", $user_id, $toMonth);
$stmtCheck->execute();
$existingCount = (int)$stmtCheck->get_result()->fetch_assoc()['cnt'];

if ($existingCount > 0 && !isset($_GET['force'])) {
    // Redirect to budget page with a confirmation prompt
    $_SESSION['flash'] = [
        'msg'  => "⚠️ " . date('F Y', strtotime("$toMonth-01")) . " already has $existingCount budget entries. " .
                  "<a href='copy_budget.php?from=$fromMonth&to=$toMonth&force=1' style='color:#2563eb;font-weight:600;'>Click here to copy anyway (will add on top)</a>",
        'type' => 'warning'
    ];
    header("Location: add_budget.php?month=$toMonth");
    exit();
}

// Fetch source budgets
$stmtFrom = $conn->prepare(
    "SELECT category, amount FROM budget WHERE user_id=? AND budget_month=?"
);
$stmtFrom->bind_param("is", $user_id, $fromMonth);
$stmtFrom->execute();
$sourceBudgets = $stmtFrom->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($sourceBudgets)) {
    $_SESSION['flash'] = [
        'msg'  => "No budget entries found in " . date('F Y', strtotime("$fromMonth-01")) . " to copy.",
        'type' => 'error'
    ];
    header("Location: add_budget.php?month=$toMonth");
    exit();
}

// Copy each entry
$stmtInsert = $conn->prepare(
    "INSERT INTO budget (user_id, category, amount, date, budget_month)
     VALUES (?, ?, ?, ?, ?)"
);

$copiedCount = 0;
$firstDayOfToMonth = "$toMonth-01";

foreach ($sourceBudgets as $b) {
    $stmtInsert->bind_param("isdss",
        $user_id,
        $b['category'],
        $b['amount'],
        $firstDayOfToMonth,
        $toMonth
    );
    $stmtInsert->execute();
    $copiedCount++;
}

$fromLabel = date('F Y', strtotime("$fromMonth-01"));
$toLabel   = date('F Y', strtotime("$toMonth-01"));

$_SESSION['flash'] = [
    'msg'  => "✅ Copied $copiedCount budget categories from $fromLabel to $toLabel!",
    'type' => 'success'
];

header("Location: add_budget.php?month=$toMonth");
exit();
?>