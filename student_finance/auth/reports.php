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

/* ========================= YEAR HANDLING ========================= */
$selectedYear = (int)($_GET['year'] ?? date('Y'));
$currentYear  = (int)date('Y');
if ($selectedYear < 2000 || $selectedYear > $currentYear) $selectedYear = $currentYear;
$prevYear = $selectedYear - 1;
$nextYear = $selectedYear + 1;

/* ========================= FILTERS ========================= */
$filterMonth    = $_GET['filter_month'] ?? '';
$filterExpCat   = $_GET['filter_cat']   ?? '';
$filterDateFrom = $_GET['date_from']    ?? '';
$filterDateTo   = $_GET['date_to']      ?? '';
if ($filterMonth    && !preg_match('/^\d{4}-\d{2}$/',       $filterMonth))    $filterMonth    = '';
if ($filterDateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateFrom)) $filterDateFrom = '';
if ($filterDateTo   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateTo))   $filterDateTo   = '';
$filtersActive = $filterMonth || $filterExpCat || $filterDateFrom || $filterDateTo;

/* ========================= MONTHLY DATA ========================= */
$monthlyIncome   = array_fill(1, 12, 0.0);
$monthlyExpenses = array_fill(1, 12, 0.0);
$monthlySavings  = array_fill(1, 12, 0.0);
$monthlyBudget   = array_fill(1, 12, 0.0);

$stmtInc = $conn->prepare("SELECT MONTH(date) AS m, SUM(amount) AS total FROM income WHERE user_id=? AND YEAR(date)=? GROUP BY MONTH(date)");
$stmtInc->bind_param("ii", $user_id, $selectedYear); $stmtInc->execute();
foreach ($stmtInc->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $monthlyIncome[(int)$r['m']] = (float)$r['total'];

$stmtExp = $conn->prepare("SELECT MONTH(date) AS m, SUM(amount) AS total FROM expenses WHERE user_id=? AND YEAR(date)=? GROUP BY MONTH(date)");
$stmtExp->bind_param("ii", $user_id, $selectedYear); $stmtExp->execute();
foreach ($stmtExp->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $monthlyExpenses[(int)$r['m']] = (float)$r['total'];

$stmtSav = $conn->prepare("SELECT MONTH(date) AS m, SUM(amount) AS total FROM savings WHERE user_id=? AND YEAR(date)=? GROUP BY MONTH(date)");
$stmtSav->bind_param("ii", $user_id, $selectedYear); $stmtSav->execute();
foreach ($stmtSav->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $monthlySavings[(int)$r['m']] = (float)$r['total'];

$stmtBud = $conn->prepare("SELECT MONTH(date) AS m, SUM(amount) AS total FROM budget WHERE user_id=? AND YEAR(date)=? GROUP BY MONTH(date)");
$stmtBud->bind_param("ii", $user_id, $selectedYear); $stmtBud->execute();
foreach ($stmtBud->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $monthlyBudget[(int)$r['m']] = (float)$r['total'];

/* ========================= FILTERED EXPENSES ========================= */
$expSql = "SELECT category, amount, date FROM expenses WHERE user_id=? AND YEAR(date)=?";
$expTypes = "ii"; $expParams = [$user_id, $selectedYear];
if ($filterMonth)    { $expSql .= " AND DATE_FORMAT(date,'%Y-%m')=?"; $expTypes .= "s"; $expParams[] = $filterMonth; }
if ($filterExpCat)   { $expSql .= " AND category=?";                  $expTypes .= "s"; $expParams[] = $filterExpCat; }
if ($filterDateFrom) { $expSql .= " AND date>=?";                      $expTypes .= "s"; $expParams[] = $filterDateFrom; }
if ($filterDateTo)   { $expSql .= " AND date<=?";                      $expTypes .= "s"; $expParams[] = $filterDateTo; }
$expSql .= " ORDER BY date DESC";
$stmtFE = $conn->prepare($expSql); $stmtFE->bind_param($expTypes, ...$expParams); $stmtFE->execute();
$filteredExpenses = $stmtFE->get_result()->fetch_all(MYSQLI_ASSOC);
$filteredExpTotal = array_sum(array_column($filteredExpenses, 'amount'));

/* ========================= FILTERED INCOME ========================= */
$incSql = "SELECT source, amount, date FROM income WHERE user_id=? AND YEAR(date)=?";
$incTypes = "ii"; $incParams = [$user_id, $selectedYear];
if ($filterMonth)    { $incSql .= " AND DATE_FORMAT(date,'%Y-%m')=?"; $incTypes .= "s"; $incParams[] = $filterMonth; }
if ($filterDateFrom) { $incSql .= " AND date>=?";                      $incTypes .= "s"; $incParams[] = $filterDateFrom; }
if ($filterDateTo)   { $incSql .= " AND date<=?";                      $incTypes .= "s"; $incParams[] = $filterDateTo; }
$incSql .= " ORDER BY date DESC";
$stmtFI = $conn->prepare($incSql); $stmtFI->bind_param($incTypes, ...$incParams); $stmtFI->execute();
$filteredIncome   = $stmtFI->get_result()->fetch_all(MYSQLI_ASSOC);
$filteredIncTotal = array_sum(array_column($filteredIncome, 'amount'));
$filteredBalance  = $filteredIncTotal - $filteredExpTotal;

/* ========================= YEARLY TOTALS ========================= */
$yearIncome   = array_sum($monthlyIncome);  $yearExpenses = array_sum($monthlyExpenses);
$yearSavings  = array_sum($monthlySavings); $yearBudget   = array_sum($monthlyBudget);
$yearBalance  = $yearIncome - $yearExpenses;
$savingsRate  = $yearIncome > 0 ? round(($yearSavings  / $yearIncome)  * 100, 1) : 0;
$budgetUsed   = $yearBudget > 0 ? round(($yearExpenses / $yearBudget)  * 100, 1) : 0;

/* ========================= BEST/WORST MONTHS ========================= */
$monthBalances = [];
for ($m = 1; $m <= 12; $m++) $monthBalances[$m] = $monthlyIncome[$m] - $monthlyExpenses[$m];
$bestMonthNum        = array_search(max($monthBalances), $monthBalances);
$worstMonthNum       = array_search(min($monthBalances), $monthBalances);
$bestMonthName       = date('F', mktime(0,0,0,$bestMonthNum,1));
$worstMonthName      = date('F', mktime(0,0,0,$worstMonthNum,1));
$highestIncomeMonth  = array_search(max($monthlyIncome),   $monthlyIncome);
$highestExpenseMonth = array_search(max($monthlyExpenses), $monthlyExpenses);

/* ========================= CATEGORIES ========================= */
$stmtCats = $conn->prepare("SELECT category, SUM(amount) AS total FROM expenses WHERE user_id=? AND YEAR(date)=? GROUP BY category ORDER BY total DESC LIMIT 8");
$stmtCats->bind_param("ii", $user_id, $selectedYear); $stmtCats->execute();
$catRows   = $stmtCats->get_result()->fetch_all(MYSQLI_ASSOC);
$catLabels = array_column($catRows, 'category');
$catTotals = array_map('floatval', array_column($catRows, 'total'));

$stmtAllCats = $conn->prepare("SELECT DISTINCT category FROM expenses WHERE user_id=? AND YEAR(date)=? ORDER BY category ASC");
$stmtAllCats->bind_param("ii", $user_id, $selectedYear); $stmtAllCats->execute();
$allCategories = $stmtAllCats->get_result()->fetch_all(MYSQLI_ASSOC);

/* ========================= SAVINGS GOALS ========================= */
$stmtGoals = $conn->prepare("SELECT title, target_amount, current_amount, status FROM savings_goals WHERE user_id=? ORDER BY status ASC, current_amount DESC");
$stmtGoals->bind_param("i", $user_id); $stmtGoals->execute();
$goals = $stmtGoals->get_result()->fetch_all(MYSQLI_ASSOC);

/* ========================= CHART DATA ========================= */
$monthLabels = []; $chartIncome = []; $chartExpenses = []; $chartSavings = []; $chartBalance = [];
for ($m = 1; $m <= 12; $m++) {
    $monthLabels[]   = date('M', mktime(0,0,0,$m,1));
    $chartIncome[]   = $monthlyIncome[$m];
    $chartExpenses[] = $monthlyExpenses[$m];
    $chartSavings[]  = $monthlySavings[$m];
    $chartBalance[]  = round($monthlyIncome[$m] - $monthlyExpenses[$m], 2);
}

$filterLabel = '';
if ($filterMonth)    $filterLabel .= date('F Y', strtotime("$filterMonth-01"));
if ($filterExpCat)   $filterLabel .= ($filterLabel ? ' - ' : '') . $filterExpCat;
if ($filterDateFrom) $filterLabel .= ($filterLabel ? ' - ' : '') . 'From ' . date('d M Y', strtotime($filterDateFrom));
if ($filterDateTo)   $filterLabel .= ($filterLabel ? ' to ' : '') . date('d M Y', strtotime($filterDateTo));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Finance Manager</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <style>
        .filter-card { background:#fff; border-radius:14px; padding:20px 24px; margin-bottom:24px; box-shadow:0 1px 6px rgba(0,0,0,.06); border:1px solid #e2e8f0; }
        .filter-card h4 { font-size:13px; font-weight:700; color:#0f172a; margin-bottom:14px; }
        .filter-row { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; }
        .filter-group { display:flex; flex-direction:column; gap:5px; }
        .filter-group label { font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#94a3b8; font-weight:600; }
        .filter-group select, .filter-group input[type="date"], .filter-group input[type="month"] { padding:9px 12px; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; font-family:inherit; color:#0f172a; background:#f8fafc; min-width:155px; }
        .filter-group select:focus, .filter-group input:focus { outline:none; border-color:#2563eb; background:#fff; }
        .btn-filter { padding:9px 20px; background:#1e293b; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; font-family:inherit; transition:background .2s; }
        .btn-filter:hover { background:#334155; }
        .filter-active-bar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:16px; font-size:13px; color:#475569; }
        .filter-tag { background:#eff6ff; color:#1d4ed8; padding:3px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .filtered-section { background:#fff; border-radius:14px; padding:20px 24px; margin-bottom:24px; box-shadow:0 1px 6px rgba(0,0,0,.06); border:1px solid #e2e8f0; }
        .filtered-section h3 { font-size:15px; font-weight:700; color:#0f172a; margin-bottom:14px; }
        .filtered-totals { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:18px; }
        .ftotal { border-radius:10px; padding:12px 18px; border-left:3px solid #e2e8f0; }
        .ftotal label { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:.5px; display:block; margin-bottom:3px; }
        .ftotal span { font-size:18px; font-weight:700; color:#0f172a; display:block; }
        .ftotal small { font-size:12px; color:#64748b; }
        .pdf-export-bar { display:flex; gap:10px; flex-wrap:wrap; margin-top:16px; }
        .btn-pdf { display:inline-flex; align-items:center; gap:8px; padding:11px 22px; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; font-family:inherit; transition:background .2s, transform .15s; }
        .btn-pdf:hover { transform:translateY(-1px); }
        .btn-pdf.blue  { background:#2563eb; } .btn-pdf.blue:hover  { background:#1d4ed8; }
        .btn-pdf.green { background:#16a34a; } .btn-pdf.green:hover { background:#15803d; }
        #pdf-progress { font-size:13px; color:#2563eb; font-weight:600; padding:10px 14px; background:#eff6ff; border-radius:8px; border:1px solid #bfdbfe; display:none; margin-top:10px; }
        #pdf-status   { font-size:13px; color:#16a34a; font-weight:600; padding:10px 14px; background:#f0fdf4; border-radius:8px; border:1px solid #bbf7d0; display:none; margin-top:10px; }
    </style>
</head>
<body>
<div class="main-content">
<div class="page-wrapper">

    <div class="page-header">
        <h2>📈 Annual Report — <?= $selectedYear ?></h2>
        <div class="year-nav">
            <a href="?year=<?= $prevYear ?>" class="btn-nav">⬅ <?= $prevYear ?></a>
            <?php if ($selectedYear < $currentYear): ?>
                <a href="?year=<?= $nextYear ?>" class="btn-nav"><?= $nextYear ?> ➡</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- FILTER CARD -->
    <div class="filter-card">
        <h4>🔍 Filter Report</h4>
        <form method="GET" class="filter-row">
            <input type="hidden" name="year" value="<?= $selectedYear ?>">
            <div class="filter-group">
                <label>Month</label>
                <input type="month" name="filter_month" value="<?= htmlspecialchars($filterMonth) ?>" max="<?= $currentYear ?>-12">
            </div>
            <div class="filter-group">
                <label>Expense Category</label>
                <select name="filter_cat">
                    <option value="">— All Categories —</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $filterExpCat === $cat['category'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>">
            </div>
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>">
            </div>
            <button type="submit" class="btn-filter">Apply</button>
            <?php if ($filtersActive): ?>
                <a href="?year=<?= $selectedYear ?>" class="btn-cancel" style="padding:9px 16px;">&#x2715; Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ACTIVE FILTER TAGS + FILTERED RESULTS -->
    <?php if ($filtersActive): ?>
    <div class="filter-active-bar">
        <span>Filtered by:</span>
        <?php if ($filterMonth):    ?><span class="filter-tag">📅 <?= date('F Y', strtotime("$filterMonth-01")) ?></span><?php endif; ?>
        <?php if ($filterExpCat):   ?><span class="filter-tag">🏷 <?= htmlspecialchars($filterExpCat) ?></span><?php endif; ?>
        <?php if ($filterDateFrom): ?><span class="filter-tag">From: <?= date('d M Y', strtotime($filterDateFrom)) ?></span><?php endif; ?>
        <?php if ($filterDateTo):   ?><span class="filter-tag">To: <?= date('d M Y', strtotime($filterDateTo)) ?></span><?php endif; ?>
    </div>

    <div class="filtered-section">
        <h3>📊 Filtered Results<?= $filterLabel ? ' — ' . htmlspecialchars($filterLabel) : '' ?></h3>
        <div class="filtered-totals">
            <div class="ftotal" style="background:#f0fdf4; border-left-color:#22c55e;">
                <label>Income</label><span>KES <?= number_format($filteredIncTotal, 2) ?></span>
                <small><?= count($filteredIncome) ?> entries</small>
            </div>
            <div class="ftotal" style="background:#fef2f2; border-left-color:#ef4444;">
                <label>Expenses</label><span>KES <?= number_format($filteredExpTotal, 2) ?></span>
                <small><?= count($filteredExpenses) ?> entries</small>
            </div>
            <?php $fb = $filteredBalance; ?>
            <div class="ftotal" style="background:<?= $fb>=0?'#f0fdf4':'#fef2f2' ?>; border-left-color:<?= $fb>=0?'#22c55e':'#ef4444' ?>;">
                <label>Balance</label>
                <span style="color:<?= $fb>=0?'#16a34a':'#dc2626' ?>">KES <?= number_format($fb, 2) ?></span>
            </div>
        </div>

        <?php if (!empty($filteredExpenses)): ?>
        <h4 style="font-size:13px;font-weight:600;color:#475569;margin-bottom:10px;">Expenses</h4>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Category</th><th>Amount (KES)</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($filteredExpenses as $r): ?>
                <tr><td><?= htmlspecialchars($r['category']) ?></td><td><?= number_format($r['amount'],2) ?></td><td><?= date('d M Y',strtotime($r['date'])) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td><strong>Total</strong></td><td><strong>KES <?= number_format($filteredExpTotal,2) ?></strong></td><td></td></tr></tfoot>
            </table>
        </div>
        <?php endif; ?>

        <?php if (!empty($filteredIncome)): ?>
        <h4 style="font-size:13px;font-weight:600;color:#475569;margin:16px 0 10px;">Income</h4>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Source</th><th>Amount (KES)</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($filteredIncome as $r): ?>
                <tr><td><?= htmlspecialchars($r['source']) ?></td><td><?= number_format($r['amount'],2) ?></td><td><?= date('d M Y',strtotime($r['date'])) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td><strong>Total</strong></td><td><strong>KES <?= number_format($filteredIncTotal,2) ?></strong></td><td></td></tr></tfoot>
            </table>
        </div>
        <?php endif; ?>

        <?php if (empty($filteredExpenses) && empty($filteredIncome)): ?>
            <p style="color:#94a3b8;font-size:13px;font-style:italic;">No records match the selected filters.</p>
        <?php endif; ?>

        <div class="pdf-export-bar">
            <button class="btn-pdf green" onclick="exportFilteredPDF()">📄 Export Filtered Results as PDF</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- YEARLY SUMMARY CARDS -->
    <div class="dashboard-cards" style="margin-bottom:30px;">
        <div class="card income-card">
            <h3>💰 Total Income</h3><p>KES <?= number_format($yearIncome,2) ?></p>
            <small>Full year <?= $selectedYear ?></small>
        </div>
        <div class="card expense-card">
            <h3>💸 Total Expenses</h3><p>KES <?= number_format($yearExpenses,2) ?></p>
            <small>Full year <?= $selectedYear ?></small>
        </div>
        <div class="card savings-card">
            <h3>🏦 Total Savings</h3><p>KES <?= number_format($yearSavings,2) ?></p>
            <small>Savings rate: <strong><?= $savingsRate ?>%</strong></small>
        </div>
        <div class="card budget-card">
            <h3>📅 Budget Used</h3><p><?= $budgetUsed ?>%</p>
            <div class="mini-progress"><div style="width:<?= min(100,$budgetUsed) ?>%"></div></div>
            <small>KES <?= number_format($yearExpenses,2) ?> of <?= number_format($yearBudget,2) ?></small>
        </div>
        <div class="card balance-card <?= $yearBalance>=0?'positive':'negative' ?>">
            <h3>💼 Net Balance</h3><p>KES <?= number_format($yearBalance,2) ?></p>
            <small><?= $yearBalance>=0?'✅ Surplus':'⚠️ Deficit' ?></small>
        </div>
    </div>

    <!-- INSIGHTS -->
    <div class="insights-grid">
        <div class="insight-card insight-green"><div class="insight-icon">🏆</div>
            <div><h4>Best Month</h4><p><?= $bestMonthName ?></p><small>Balance: KES <?= number_format($monthBalances[$bestMonthNum],2) ?></small></div></div>
        <div class="insight-card insight-red"><div class="insight-icon">⚠️</div>
            <div><h4>Hardest Month</h4><p><?= $worstMonthName ?></p><small>Balance: KES <?= number_format($monthBalances[$worstMonthNum],2) ?></small></div></div>
        <div class="insight-card insight-blue"><div class="insight-icon">📈</div>
            <div><h4>Highest Income</h4><p><?= date('F',mktime(0,0,0,$highestIncomeMonth,1)) ?></p><small>KES <?= number_format(max($monthlyIncome),2) ?></small></div></div>
        <div class="insight-card insight-orange"><div class="insight-icon">📉</div>
            <div><h4>Highest Spending</h4><p><?= date('F',mktime(0,0,0,$highestExpenseMonth,1)) ?></p><small>KES <?= number_format(max($monthlyExpenses),2) ?></small></div></div>
        <div class="insight-card insight-purple"><div class="insight-icon">💡</div>
            <div><h4>Savings Rate</h4><p><?= $savingsRate ?>%</p>
            <small><?= $savingsRate>=20?'🎉 Excellent!':($savingsRate>=10?'👍 Good':'📌 Needs improvement') ?></small></div></div>
    </div>

    <!-- CHARTS -->
    <div class="chart-wrapper"><canvas id="monthlyChart"></canvas></div>
    <div class="chart-wrapper"><canvas id="balanceChart"></canvas></div>

    <div class="report-two-col">
        <?php if (!empty($catLabels)): ?>
        <div class="chart-wrapper" style="margin-top:0;flex:1;"><canvas id="categoryChart"></canvas></div>
        <?php endif; ?>
        <?php if (!empty($goals)): ?>
        <div class="report-goals-panel">
            <h3>🎯 Savings Goals</h3>
            <?php foreach ($goals as $g):
                $target=$g['target_amount']; $current=$g['current_amount'];
                $pct=($target>0)?min(100,round(($current/$target)*100)):0;
                $barClass=$pct>=100?'bar-ok':($pct>=60?'bar-warning':'bar-low');
            ?>
            <div class="goal-report-row">
                <div class="goal-report-top">
                    <span class="goal-title"><?= htmlspecialchars($g['title']) ?></span>
                    <span class="badge <?= $g['status']==='active'?'badge-income':'badge-expense' ?>"><?= ucfirst($g['status']) ?></span>
                </div>
                <div class="progress-bar"><div class="progress-fill <?= $barClass ?>" style="width:<?= $pct ?>%"></div></div>
                <div class="goal-report-bottom">
                    <small>KES <?= number_format($current,2) ?> / <?= $target>0?'KES '.number_format($target,2):'No target' ?></small>
                    <small><strong><?= $pct ?>%</strong></small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- MONTHLY TABLE -->
    <div class="table-section">
        <h3>📋 Monthly Breakdown</h3>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Month</th><th>Income (KES)</th><th>Expenses (KES)</th><th>Savings (KES)</th><th>Budget (KES)</th><th>Balance (KES)</th><th>Status</th></tr></thead>
                <tbody>
                <?php for ($m=1;$m<=12;$m++):
                    $inc=$monthlyIncome[$m]; $exp=$monthlyExpenses[$m];
                    $sav=$monthlySavings[$m]; $bud=$monthlyBudget[$m];
                    $bal=$inc-$exp; $hasData=($inc+$exp+$sav+$bud)>0;
                ?>
                <tr class="<?= !$hasData?'row-empty-month':($bal<0?'row-exceeded':'') ?>">
                    <td><strong><?= date('F',mktime(0,0,0,$m,1)) ?></strong></td>
                    <td><?= $inc>0?number_format($inc,2):'—' ?></td>
                    <td><?= $exp>0?number_format($exp,2):'—' ?></td>
                    <td><?= $sav>0?number_format($sav,2):'—' ?></td>
                    <td><?= $bud>0?number_format($bud,2):'—' ?></td>
                    <td class="<?= $bal>0?'text-success':($bal<0?'text-danger':'') ?>"><?= $hasData?number_format($bal,2):'—' ?></td>
                    <td>
                        <?php if (!$hasData): ?><span class="badge" style="background:#f1f5f9;color:#94a3b8;">No data</span>
                        <?php elseif ($bal>=0): ?><span class="badge badge-income">✅ Surplus</span>
                        <?php else: ?><span class="badge badge-expense">⚠️ Deficit</span><?php endif; ?>
                    </td>
                </tr>
                <?php endfor; ?>
                </tbody>
                <tfoot>
                    <tr class="totals-row">
                        <td><strong>TOTAL</strong></td>
                        <td><strong><?= number_format($yearIncome,2) ?></strong></td>
                        <td><strong><?= number_format($yearExpenses,2) ?></strong></td>
                        <td><strong><?= number_format($yearSavings,2) ?></strong></td>
                        <td><strong><?= number_format($yearBudget,2) ?></strong></td>
                        <td class="<?= $yearBalance>=0?'text-success':'text-danger' ?>"><strong><?= number_format($yearBalance,2) ?></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="export-bar" style="margin-top:16px;">
        <a href="export.php?type=report&year=<?= $selectedYear ?>" class="btn-export">⬇ Export Annual Report CSV</a>
    </div>

    <div class="pdf-export-bar">
        <button class="btn-pdf blue" onclick="exportFullReportPDF()">📊 Export Full Annual PDF (with Charts)</button>
    </div>
    <div id="pdf-progress">Capturing charts, please wait...</div>
    <div id="pdf-status">PDF downloaded successfully!</div>

</div>
</div>

<script>
/* -- PHP data ------------------------------------------------ */
const months       = <?php echo json_encode($monthLabels) ?>;
const incomeData   = <?php echo json_encode($chartIncome) ?>;
const expenseData  = <?php echo json_encode($chartExpenses) ?>;
const savingsData  = <?php echo json_encode($chartSavings) ?>;
const balData      = <?php echo json_encode($chartBalance) ?>;
const catLabels    = <?php echo json_encode($catLabels) ?>;
const catTotals    = <?php echo json_encode($catTotals) ?>;
const selectedYear = <?php echo (int)$selectedYear ?>;
const userName     = "<?php echo addslashes(htmlspecialchars($userName ?? '')); ?>";

/* -- PDF colour constants ------------------------------------ */
var C_BLUE   = [37,99,235];
var C_INK    = [15,23,42];
var C_MID    = [100,116,139];
var C_GREEN  = [22,163,74];
var C_RED    = [220,38,38];
var C_STRIPE = [248,250,252];
var C_WHITE  = [255,255,255];

/* -- PDF helper functions (global scope) -------------------- */
function drawHeader(doc, subtitle) {
    var pw = doc.internal.pageSize.getWidth();
    doc.setFillColor.apply(doc, C_BLUE);
    doc.rect(0, 0, pw, 18, 'F');
    doc.setFont('helvetica','bold'); doc.setFontSize(10);
    doc.setTextColor(255,255,255);
    doc.text('Finance Manager', 14, 11.5);
    doc.setFont('helvetica','normal'); doc.setFontSize(8);
    doc.text(userName + '  |  ' + new Date().toLocaleDateString('en-KE'), pw-14, 11.5, {align:'right'});
    doc.setFont('helvetica','bold'); doc.setFontSize(15);
    doc.setTextColor.apply(doc, C_INK);
    doc.text(subtitle, 14, 31);
    doc.setFont('helvetica','normal'); doc.setFontSize(9);
    doc.setTextColor.apply(doc, C_MID);
    doc.text('Year: ' + selectedYear, 14, 38);
    doc.setDrawColor(226,232,240); doc.setLineWidth(0.4);
    doc.line(14, 41, pw-14, 41);
    return 48;
}

function drawSection(doc, text, y) {
    var pw = doc.internal.pageSize.getWidth();
    doc.setFont('helvetica','bold'); doc.setFontSize(9);
    doc.setTextColor.apply(doc, C_BLUE);
    doc.text(text.toUpperCase(), 14, y);
    doc.setDrawColor.apply(doc, C_BLUE);
    doc.setLineWidth(0.5);
    doc.line(14, y+2, pw-14, y+2);
    return y + 9;
}

function addPageNumbers(doc) {
    var total = doc.internal.getNumberOfPages();
    var pw = doc.internal.pageSize.getWidth();
    var ph = doc.internal.pageSize.getHeight();
    for (var i = 1; i <= total; i++) {
        doc.setPage(i);
        doc.setFont('helvetica','normal'); doc.setFontSize(7.5);
        doc.setTextColor.apply(doc, C_MID);
        doc.text('Finance Manager - Annual Report ' + selectedYear, 14, ph-8);
        doc.text('Page ' + i + ' of ' + total, pw/2, ph-8, {align:'center'});
        doc.text(new Date().toLocaleDateString('en-KE'), pw-14, ph-8, {align:'right'});
    }
}

async function addChartToPDF(doc, canvasId, y, label) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) return y;
    var pw = doc.internal.pageSize.getWidth();
    var ph = doc.internal.pageSize.getHeight();
    var off = document.createElement('canvas');
    off.width  = canvas.width  * 2;
    off.height = canvas.height * 2;
    var ctx = off.getContext('2d');
    ctx.scale(2, 2);
    ctx.drawImage(canvas, 0, 0);
    var imgData = off.toDataURL('image/png', 1.0);
    var imgW = pw - 28;
    var imgH = Math.min(imgW * (canvas.height / canvas.width), 90);
    if (y + imgH + 14 > ph - 16) {
        doc.addPage();
        y = drawHeader(doc, 'Annual Report - ' + selectedYear);
    }
    if (label) y = drawSection(doc, label, y);
    doc.addImage(imgData, 'PNG', 14, y, imgW, imgH);
    return y + imgH + 10;
}

/* -- Full annual PDF ---------------------------------------- */
async function exportFullReportPDF() {
    document.getElementById('pdf-progress').style.display = 'block';
    document.getElementById('pdf-status').style.display   = 'none';
    await new Promise(function(r){ setTimeout(r, 700); });

    var jsPDF = window.jspdf.jsPDF;
    var doc = new jsPDF({unit:'mm', format:'a4'});
    var y;

    y = drawHeader(doc, 'Annual Financial Report - ' + selectedYear);
    y = drawSection(doc, 'Yearly Summary', y);
    doc.autoTable({
        startY: y,
        head: [['Metric','Value']],
        body: [
            ['Total Income',   '<?php echo number_format($yearIncome,2) ?>'],
            ['Total Expenses', '<?php echo number_format($yearExpenses,2) ?>'],
            ['Total Savings',  '<?php echo number_format($yearSavings,2) ?>'],
            ['Total Budget',   '<?php echo number_format($yearBudget,2) ?>'],
            ['Net Balance',    '<?php echo number_format($yearBalance,2) ?>'],
            ['Savings Rate',   '<?php echo $savingsRate ?>%'],
            ['Budget Used',    '<?php echo $budgetUsed ?>%']
        ],
        theme: 'grid',
        headStyles: {fillColor:C_INK, textColor:C_WHITE, fontSize:9, fontStyle:'bold'},
        bodyStyles: {fontSize:9.5, textColor:C_INK},
        columnStyles: {1:{halign:'right', fontStyle:'bold'}},
        alternateRowStyles: {fillColor:C_STRIPE},
        margin: {left:14, right:14}
    });
    y = doc.lastAutoTable.finalY + 10;

    y = drawSection(doc, 'Key Insights', y);
    doc.autoTable({
        startY: y,
        head: [['Insight','Detail']],
        body: [
            ['Best Month',           '<?php echo $bestMonthName ?> (KES <?php echo number_format($monthBalances[$bestMonthNum],2) ?>)'],
            ['Hardest Month',        '<?php echo $worstMonthName ?> (KES <?php echo number_format($monthBalances[$worstMonthNum],2) ?>)'],
            ['Highest Income Month', '<?php echo date("F",mktime(0,0,0,$highestIncomeMonth,1)) ?> (KES <?php echo number_format(max($monthlyIncome),2) ?>)'],
            ['Highest Spending',     '<?php echo date("F",mktime(0,0,0,$highestExpenseMonth,1)) ?> (KES <?php echo number_format(max($monthlyExpenses),2) ?>)'],
            ['Savings Rate',         '<?php echo $savingsRate ?>%']
        ],
        theme: 'striped',
        headStyles: {fillColor:C_INK, textColor:C_WHITE, fontSize:9, fontStyle:'bold'},
        bodyStyles: {fontSize:9, textColor:C_INK},
        alternateRowStyles: {fillColor:C_STRIPE},
        margin: {left:14, right:14}
    });

    doc.addPage();
    y = drawHeader(doc, 'Annual Financial Report - ' + selectedYear);
    y = await addChartToPDF(doc, 'monthlyChart',  y, 'Monthly Income vs Expenses vs Savings');
    y = await addChartToPDF(doc, 'balanceChart',  y, 'Monthly Balance Trend');

    if (catLabels.length > 0) {
        doc.addPage();
        y = drawHeader(doc, 'Annual Financial Report - ' + selectedYear);
        y = await addChartToPDF(doc, 'categoryChart', y, 'Expense Category Breakdown');
        y = drawSection(doc, 'Category Totals', y);
        doc.autoTable({
            startY: y,
            head: [['Category','Total (KES)']],
            body: catLabels.map(function(c,i){ return [c, parseFloat(catTotals[i]).toLocaleString('en-KE',{minimumFractionDigits:2})]; }),
            foot: [['TOTAL', '<?php echo number_format($yearExpenses,2) ?>']],
            theme: 'striped',
            headStyles: {fillColor:C_INK, textColor:C_WHITE, fontSize:8.5, fontStyle:'bold'},
            footStyles: {fillColor:C_INK, textColor:C_WHITE, fontSize:9, fontStyle:'bold'},
            bodyStyles: {fontSize:8.5, textColor:C_INK},
            alternateRowStyles: {fillColor:C_STRIPE},
            columnStyles: {1:{halign:'right', fontStyle:'bold'}},
            margin: {left:14, right:14},
            showFoot: 'lastPage'
        });
    }

    doc.addPage();
    y = drawHeader(doc, 'Annual Financial Report - ' + selectedYear);
    y = drawSection(doc, 'Monthly Breakdown', y);
    doc.autoTable({
        startY: y,
        head: [['Month','Income','Expenses','Savings','Budget','Balance']],
        body: <?php
            $rows = [];
            for ($m = 1; $m <= 12; $m++) {
                $rows[] = [
                    date('F', mktime(0,0,0,$m,1)),
                    $monthlyIncome[$m]   > 0 ? number_format($monthlyIncome[$m],2)   : '-',
                    $monthlyExpenses[$m] > 0 ? number_format($monthlyExpenses[$m],2) : '-',
                    $monthlySavings[$m]  > 0 ? number_format($monthlySavings[$m],2)  : '-',
                    $monthlyBudget[$m]   > 0 ? number_format($monthlyBudget[$m],2)   : '-',
                    ($monthlyIncome[$m]+$monthlyExpenses[$m]) > 0 ? number_format($monthlyIncome[$m]-$monthlyExpenses[$m],2) : '-',
                ];
            }
            echo json_encode($rows);
        ?>,
        foot: [['TOTAL','<?php echo number_format($yearIncome,2) ?>','<?php echo number_format($yearExpenses,2) ?>','<?php echo number_format($yearSavings,2) ?>','<?php echo number_format($yearBudget,2) ?>','<?php echo number_format($yearBalance,2) ?>']],
        theme: 'striped',
        headStyles: {fillColor:C_INK, textColor:C_WHITE, fontSize:8, fontStyle:'bold'},
        footStyles: {fillColor:C_INK, textColor:C_WHITE, fontSize:8.5, fontStyle:'bold'},
        bodyStyles: {fontSize:8, textColor:C_INK},
        alternateRowStyles: {fillColor:C_STRIPE},
        columnStyles: {1:{halign:'right'},2:{halign:'right'},3:{halign:'right'},4:{halign:'right'},5:{halign:'right',fontStyle:'bold'}},
        margin: {left:14, right:14},
        showFoot: 'lastPage'
    });

    <?php if (!empty($goals)): ?>
    doc.addPage();
    y = drawHeader(doc, 'Annual Financial Report - ' + selectedYear);
    y = drawSection(doc, 'Savings Goals Progress', y);
    doc.autoTable({
        startY: y,
        head: [['Goal','Saved (KES)','Target (KES)','Status','Progress']],
        body: <?php echo json_encode(array_map(function($g){
            return [
                $g['title'],
                number_format($g['current_amount'],2),
                $g['target_amount'] > 0 ? number_format($g['target_amount'],2) : 'Open',
                ucfirst($g['status']),
                ($g['target_amount'] > 0 ? min(100,round(($g['current_amount']/$g['target_amount'])*100)) : 0).'%'
            ];
        }, $goals)); ?>,
        theme: 'striped',
        headStyles: {fillColor:C_INK, textColor:C_WHITE, fontSize:8.5, fontStyle:'bold'},
        bodyStyles: {fontSize:8.5, textColor:C_INK},
        alternateRowStyles: {fillColor:C_STRIPE},
        columnStyles: {1:{halign:'right'},2:{halign:'right'},3:{halign:'center'},4:{halign:'center',fontStyle:'bold'}},
        margin: {left:14, right:14}
    });
    <?php endif; ?>

    addPageNumbers(doc);
    document.getElementById('pdf-progress').style.display = 'none';
    doc.save('annual_report_' + selectedYear + '.pdf');
    document.getElementById('pdf-status').style.display = 'block';
    setTimeout(function(){ document.getElementById('pdf-status').style.display='none'; }, 4000);
}

/* -- Filtered PDF ------------------------------------------- */
function exportFilteredPDF() {
    var jsPDF = window.jspdf.jsPDF;
    var doc = new jsPDF({unit:'mm', format:'a4'});
    var fl  = "<?php echo addslashes($filterLabel) ?>";
    var y   = drawHeader(doc, 'Filtered Report' + (fl ? ' - ' + fl : ''));

    y = drawSection(doc, 'Filter Summary', y);
    doc.autoTable({
        startY: y,
        head: [['Type','Total (KES)','Entries']],
        body: [
            ['Income',   '<?php echo number_format($filteredIncTotal,2) ?>', '<?php echo count($filteredIncome) ?>'],
            ['Expenses', '<?php echo number_format($filteredExpTotal,2) ?>', '<?php echo count($filteredExpenses) ?>'],
            ['Balance',  '<?php echo number_format($filteredBalance,2) ?>',  '']
        ],
        theme: 'grid',
        headStyles: {fillColor:C_INK, textColor:C_WHITE, fontSize:9, fontStyle:'bold'},
        bodyStyles: {fontSize:9.5, textColor:C_INK},
        columnStyles: {1:{halign:'right', fontStyle:'bold'}},
        alternateRowStyles: {fillColor:C_STRIPE},
        margin: {left:14, right:14}
    });
    y = doc.lastAutoTable.finalY + 10;

    <?php if (!empty($filteredExpenses)): ?>
    y = drawSection(doc, 'Expense Records', y);
    doc.autoTable({
        startY: y,
        head: [['Category','Amount (KES)','Date']],
        body: <?php echo json_encode(array_map(function($r){
            return [$r['category'], number_format($r['amount'],2), date('d M Y',strtotime($r['date']))];
        }, $filteredExpenses)); ?>,
        foot: [['TOTAL', '<?php echo number_format($filteredExpTotal,2) ?>', '']],
        theme: 'striped',
        headStyles: {fillColor:C_INK, textColor:C_WHITE, fontSize:8.5, fontStyle:'bold'},
        footStyles: {fillColor:C_INK, textColor:C_WHITE, fontSize:9, fontStyle:'bold'},
        bodyStyles: {fontSize:8.5, textColor:C_INK},
        alternateRowStyles: {fillColor:C_STRIPE},
        columnStyles: {1:{halign:'right', fontStyle:'bold'}},
        margin: {left:14, right:14},
        showFoot: 'lastPage'
    });
    y = doc.lastAutoTable.finalY + 10;
    <?php endif; ?>

    <?php if (!empty($filteredIncome)): ?>
    y = drawSection(doc, 'Income Records', y);
    doc.autoTable({
        startY: y,
        head: [['Source','Amount (KES)','Date']],
        body: <?php echo json_encode(array_map(function($r){
            return [$r['source'], number_format($r['amount'],2), date('d M Y',strtotime($r['date']))];
        }, $filteredIncome)); ?>,
        foot: [['TOTAL', '<?php echo number_format($filteredIncTotal,2) ?>', '']],
        theme: 'striped',
        headStyles: {fillColor:C_INK, textColor:C_WHITE, fontSize:8.5, fontStyle:'bold'},
        footStyles: {fillColor:C_INK, textColor:C_WHITE, fontSize:9, fontStyle:'bold'},
        bodyStyles: {fontSize:8.5, textColor:C_INK},
        alternateRowStyles: {fillColor:C_STRIPE},
        columnStyles: {1:{halign:'right', fontStyle:'bold'}},
        margin: {left:14, right:14},
        showFoot: 'lastPage'
    });
    <?php endif; ?>

    addPageNumbers(doc);
    doc.save('filtered_report_' + selectedYear + '.pdf');
}

/* -- Chart initialisation (runs after DOM ready) ------------ */
window.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') {
        document.querySelectorAll('.chart-wrapper').forEach(function(el) {
            el.innerHTML = '<p style="color:#ef4444;padding:20px;text-align:center;">Chart.js failed to load. Check your internet connection.</p>';
        });
        return;
    }

    var opts = { responsive: true, maintainAspectRatio: false };

    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: {
            labels: months,
            datasets: [
                { label:'Income',   data:incomeData,  backgroundColor:'rgba(34,197,94,0.75)',  borderColor:'#16a34a', borderWidth:1, borderRadius:5 },
                { label:'Expenses', data:expenseData, backgroundColor:'rgba(239,68,68,0.75)',  borderColor:'#b91c1c', borderWidth:1, borderRadius:5 },
                { label:'Savings',  data:savingsData, backgroundColor:'rgba(59,130,246,0.75)', borderColor:'#2563eb', borderWidth:1, borderRadius:5 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: {position:'top'},
                title:  {display:true, text:'Monthly Income vs Expenses vs Savings - ' + selectedYear}
            },
            scales: { y: {beginAtZero:true, title:{display:true, text:'KES'}} }
        }
    });

    new Chart(document.getElementById('balanceChart'), {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'Monthly Balance',
                data:  balData,
                borderColor: '#8b5cf6',
                backgroundColor: 'rgba(139,92,246,0.12)',
                fill: true, tension: 0.4,
                pointBackgroundColor: balData.map(function(v){ return v >= 0 ? '#22c55e' : '#ef4444'; }),
                pointRadius: 5
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: {position:'top'},
                title:  {display:true, text:'Monthly Balance Trend - ' + selectedYear}
            },
            scales: { y: {title:{display:true, text:'KES'}} }
        }
    });

    var catCanvas = document.getElementById('categoryChart');
    if (catCanvas && catLabels.length > 0) {
        new Chart(catCanvas, {
            type: 'doughnut',
            data: {
                labels: catLabels,
                datasets: [{
                    data: catTotals,
                    backgroundColor: ['#f87171','#34d399','#60a5fa','#fbbf24','#a78bfa','#f472b6','#38bdf8','#facc15'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: {position:'right'},
                    title:  {display:true, text:'Expense Categories - ' + selectedYear}
                }
            }
        });
    }
});
</script>
</body>
</html>