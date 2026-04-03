<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
header('Content-Type: application/json');

/* =========================
   EXPENSES BY CATEGORY (all time)
========================= */
$stmtCat = $conn->prepare(
    "SELECT category, SUM(amount) AS total
     FROM expenses
     WHERE user_id=?
     GROUP BY category
     ORDER BY total DESC"
);
$stmtCat->bind_param("i", $user_id);
$stmtCat->execute();
$catRows = $stmtCat->get_result()->fetch_all(MYSQLI_ASSOC);

$expenseCategories = array_column($catRows, 'category');
$expenseAmounts    = array_map('floatval', array_column($catRows, 'total'));

/* =========================
   MONTHLY INCOME vs EXPENSES (current year)
========================= */
$currentYear = date('Y');

$stmtInc = $conn->prepare(
    "SELECT MONTH(date) AS m, SUM(amount) AS total
     FROM income
     WHERE user_id=? AND YEAR(date)=?
     GROUP BY MONTH(date)"
);
$stmtInc->bind_param("ii", $user_id, $currentYear);
$stmtInc->execute();
$incRows = $stmtInc->get_result()->fetch_all(MYSQLI_ASSOC);

$stmtExp = $conn->prepare(
    "SELECT MONTH(date) AS m, SUM(amount) AS total
     FROM expenses
     WHERE user_id=? AND YEAR(date)=?
     GROUP BY MONTH(date)"
);
$stmtExp->bind_param("ii", $user_id, $currentYear);
$stmtExp->execute();
$expRows = $stmtExp->get_result()->fetch_all(MYSQLI_ASSOC);

// Build month-indexed arrays
$incomeByMonth  = array_fill(1, 12, 0);
$expenseByMonth = array_fill(1, 12, 0);

foreach ($incRows as $r) $incomeByMonth[(int)$r['m']]  = (float)$r['total'];
foreach ($expRows as $r) $expenseByMonth[(int)$r['m']] = (float)$r['total'];

$months      = [];
$incomeData  = [];
$expenseData = [];

for ($m = 1; $m <= 12; $m++) {
    $months[]      = date('M', mktime(0, 0, 0, $m, 1));
    $incomeData[]  = $incomeByMonth[$m];
    $expenseData[] = $expenseByMonth[$m];
}

echo json_encode([
    'expenseCategories' => $expenseCategories,
    'expenseAmounts'    => $expenseAmounts,
    'months'            => $months,
    'income'            => $incomeData,
    'expenses'          => $expenseData,
]);