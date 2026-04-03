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
   FETCH USER INFO
========================= */
$stmtUser = $conn->prepare("SELECT fullname, points FROM users WHERE id = ?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$user       = $stmtUser->get_result()->fetch_assoc();
$userName   = $user['fullname'] ?? 'User';
$userPoints = $user['points'] ?? 0;

/* =========================
   FETCH UNREAD NOTIFICATIONS
========================= */
$stmtNotify = $conn->prepare(
    "SELECT id, message, created_at
     FROM notifications
     WHERE user_id = ? AND status = 'unread'
     ORDER BY created_at DESC"
);
$stmtNotify->bind_param("i", $user_id);
$stmtNotify->execute();
$notifyResult = $stmtNotify->get_result();

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

/* =========================
   CATEGORY FILTER
========================= */
$filterCategory = trim($_GET['filter_cat'] ?? '');

// All distinct categories for this month's expenses (for the dropdown)
$catAllStmt = $conn->prepare(
    "SELECT DISTINCT category
     FROM expenses
     WHERE user_id = ? AND DATE_FORMAT(date,'%Y-%m') = ?
     ORDER BY category ASC"
);
$catAllStmt->bind_param("is", $user_id, $selectedMonth);
$catAllStmt->execute();
$allCategories = $catAllStmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* =========================
   DASHBOARD TOTALS
========================= */
function getMonthTotal($conn, $table, $amountCol, $dateCol, $user_id, $month) {
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM($amountCol), 0) AS total
         FROM $table
         WHERE user_id = ? AND DATE_FORMAT($dateCol, '%Y-%m') = ?"
    );
    $stmt->bind_param("is", $user_id, $month);
    $stmt->execute();
    return (float)$stmt->get_result()->fetch_assoc()['total'];
}

function getMonthTotalDirect($conn, $table, $amountCol, $monthCol, $user_id, $month) {
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM($amountCol), 0) AS total
         FROM $table
         WHERE user_id = ? AND $monthCol = ?"
    );
    $stmt->bind_param("is", $user_id, $month);
    $stmt->execute();
    return (float)$stmt->get_result()->fetch_assoc()['total'];
}

$totalIncome   = getMonthTotal($conn, 'income',   'amount', 'date', $user_id, $selectedMonth);
$totalExpenses = getMonthTotal($conn, 'expenses', 'amount', 'date', $user_id, $selectedMonth);
$totalSavings  = getMonthTotalDirect($conn, 'savings', 'amount', 'savings_month', $user_id, $selectedMonth);
$totalBudget   = getMonthTotalDirect($conn, 'budget',  'amount', 'budget_month',  $user_id, $selectedMonth);
$balance       = $totalIncome - $totalExpenses;

$prevIncome   = getMonthTotal($conn, 'income',   'amount', 'date', $user_id, $prevMonth);
$prevExpenses = getMonthTotal($conn, 'expenses', 'amount', 'date', $user_id, $prevMonth);
$prevSavings  = getMonthTotalDirect($conn, 'savings', 'amount', 'savings_month', $user_id, $prevMonth);
$prevBudget   = getMonthTotalDirect($conn, 'budget',  'amount', 'budget_month',  $user_id, $prevMonth);

$predIncome   = round(($totalIncome   + $prevIncome)   / 2, 2);
$predExpenses = round(($totalExpenses + $prevExpenses) / 2, 2);
$predSavings  = round(($totalSavings  + $prevSavings)  / 2, 2);
$predBudget   = round(($totalBudget   + $prevBudget)   / 2, 2);

/* =========================
   DAILY CHART DATA
========================= */
$daysInMonth = (int)date('t', strtotime("$selectedMonth-01"));
$daysLabels  = range(1, $daysInMonth);

$incomeByDay  = array_fill(1, $daysInMonth, 0);
$expenseByDay = array_fill(1, $daysInMonth, 0);
$savingsByDay = array_fill(1, $daysInMonth, 0);
$budgetByDay  = array_fill(1, $daysInMonth, 0);

$stmtInc = $conn->prepare("SELECT DAY(date) AS d, SUM(amount) AS total FROM income WHERE user_id=? AND DATE_FORMAT(date,'%Y-%m')=? GROUP BY DAY(date)");
$stmtInc->bind_param("is", $user_id, $selectedMonth);
$stmtInc->execute();
foreach ($stmtInc->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $incomeByDay[$r['d']] = (float)$r['total'];

$stmtExp = $conn->prepare("SELECT DAY(date) AS d, SUM(amount) AS total FROM expenses WHERE user_id=? AND DATE_FORMAT(date,'%Y-%m')=? GROUP BY DAY(date)");
$stmtExp->bind_param("is", $user_id, $selectedMonth);
$stmtExp->execute();
foreach ($stmtExp->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $expenseByDay[$r['d']] = (float)$r['total'];

$stmtSav = $conn->prepare("SELECT DAY(date) AS d, SUM(amount) AS total FROM savings WHERE user_id=? AND savings_month=? GROUP BY DAY(date)");
$stmtSav->bind_param("is", $user_id, $selectedMonth);
$stmtSav->execute();
foreach ($stmtSav->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $savingsByDay[$r['d']] = (float)$r['total'];

$stmtBud = $conn->prepare("SELECT DAY(date) AS d, SUM(amount) AS total FROM budget WHERE user_id=? AND budget_month=? GROUP BY DAY(date)");
$stmtBud->bind_param("is", $user_id, $selectedMonth);
$stmtBud->execute();
foreach ($stmtBud->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $budgetByDay[$r['d']] = (float)$r['total'];

$incomeData  = array_values($incomeByDay);
$expenseData = array_values($expenseByDay);
$savingsData = array_values($savingsByDay);
$budgetData  = array_values($budgetByDay);

/* =========================
   EXPENSE CATEGORY BREAKDOWN (chart)
========================= */
$catStmt = $conn->prepare(
    "SELECT category, SUM(amount) AS total
     FROM expenses
     WHERE user_id = ? AND DATE_FORMAT(date,'%Y-%m') = ?
     GROUP BY category
     ORDER BY total DESC"
);
$catStmt->bind_param("is", $user_id, $selectedMonth);
$catStmt->execute();
$catRows        = $catStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$categories     = array_column($catRows, 'category');
$categoryTotals = array_column($catRows, 'total');

/* =========================
   RECENT TRANSACTIONS (income + expenses mixed, no filter — always shows latest 8)
========================= */
$recentStmt = $conn->prepare(
    "(SELECT 'Income' AS type, source AS label, amount, date
      FROM income WHERE user_id = ? ORDER BY date DESC LIMIT 5)
     UNION ALL
     (SELECT 'Expense' AS type, category AS label, amount, date
      FROM expenses WHERE user_id = ? ORDER BY date DESC LIMIT 5)
     ORDER BY date DESC LIMIT 8"
);
$recentStmt->bind_param("ii", $user_id, $user_id);
$recentStmt->execute();
$recentRows = $recentStmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* =========================
   EXPENSES BY CATEGORY (filtered table — separate from recent transactions)
========================= */
if ($filterCategory !== '') {
    $expFilterStmt = $conn->prepare(
        "SELECT category, amount, date
         FROM expenses
         WHERE user_id = ? AND DATE_FORMAT(date,'%Y-%m') = ? AND category = ?
         ORDER BY date DESC"
    );
    $expFilterStmt->bind_param("iss", $user_id, $selectedMonth, $filterCategory);
} else {
    $expFilterStmt = $conn->prepare(
        "SELECT category, amount, date
         FROM expenses
         WHERE user_id = ? AND DATE_FORMAT(date,'%Y-%m') = ?
         ORDER BY date DESC"
    );
    $expFilterStmt->bind_param("is", $user_id, $selectedMonth);
}
$expFilterStmt->execute();
$expFilterRows = $expFilterStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Total for filtered result
$filteredTotal = array_sum(array_column($expFilterRows, 'amount'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Finance Manager</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="main-content dashboard-wrapper">

    <!-- NOTIFICATIONS -->
    <?php if ($notifyResult->num_rows > 0): ?>
    <div class="notification-wrapper">
        <h3>🔔 Budget Alerts</h3>
        <?php while ($note = $notifyResult->fetch_assoc()): ?>
            <div class="notification-card">
                <span><?= htmlspecialchars($note['message']) ?></span>
                <small><?= date('d M Y H:i', strtotime($note['created_at'])) ?></small>
                <a href="mark_read.php?id=<?= (int)$note['id'] ?>" class="mark-read">✔ Dismiss</a>
            </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>

    <div class="dashboard-header">
        <div>
            <h2>📊 Dashboard — <?= date('F Y', strtotime("$selectedMonth-01")) ?></h2>
            <p class="welcome-msg">👋 Welcome back, <strong><?= htmlspecialchars($userName) ?></strong> &nbsp;|&nbsp; ⭐ Points: <strong><?= (int)$userPoints ?></strong></p>
        </div>
        <button onclick="quickExportPDF()" class="btn-export" style="background:#2563eb; border:none; cursor:pointer; font-family:inherit;">
            📄 Export PDF
        </button>
    </div>

    <!-- MONTH NAVIGATION -->
    <div class="month-nav">
        <a href="?month=<?= $prevMonth ?>" class="btn-nav">⬅ <?= date('M Y', strtotime("$prevMonth-01")) ?></a>
        <?php if ($selectedMonth < $currentMonth): ?>
            <a href="?month=<?= $nextMonth ?>" class="btn-nav"><?= date('M Y', strtotime("$nextMonth-01")) ?> ➡</a>
        <?php endif; ?>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="dashboard-cards">
        <div class="card income-card">
            <h3>💰 Income</h3>
            <p>KES <?= number_format($totalIncome, 2) ?></p>
            <?php $incDiff = $totalIncome - $prevIncome; ?>
            <small class="<?= $incDiff >= 0 ? 'trend-up' : 'trend-down' ?>">
                <?= $incDiff >= 0 ? '▲' : '▼' ?> KES <?= number_format(abs($incDiff), 2) ?> vs last month
            </small>
        </div>
        <div class="card expense-card">
            <h3>💸 Expenses</h3>
            <p>KES <?= number_format($totalExpenses, 2) ?></p>
            <?php $expDiff = $totalExpenses - $prevExpenses; ?>
            <small class="<?= $expDiff <= 0 ? 'trend-up' : 'trend-down' ?>">
                <?= $expDiff >= 0 ? '▲' : '▼' ?> KES <?= number_format(abs($expDiff), 2) ?> vs last month
            </small>
        </div>
        <div class="card savings-card">
            <h3>🏦 Savings</h3>
            <p>KES <?= number_format($totalSavings, 2) ?></p>
        </div>
        <div class="card budget-card">
            <h3>📅 Budget</h3>
            <p>KES <?= number_format($totalBudget, 2) ?></p>
            <?php if ($totalBudget > 0): ?>
                <?php $budgetUsed = min(100, round(($totalExpenses / $totalBudget) * 100)); ?>
                <div class="mini-progress"><div style="width:<?= $budgetUsed ?>%"></div></div>
                <small><?= $budgetUsed ?>% used</small>
            <?php endif; ?>
        </div>
        <div class="card balance-card <?= $balance >= 0 ? 'positive' : 'negative' ?>">
            <h3>💼 Balance</h3>
            <p>KES <?= number_format($balance, 2) ?></p>
            <small><?= $balance >= 0 ? '✅ You\'re in the green' : '⚠️ Expenses exceed income' ?></small>
        </div>
    </div>

    <!-- RECENT TRANSACTIONS (always shows latest income + expenses, no filter) -->
    <?php if (!empty($recentRows)): ?>
    <div class="recent-transactions">
        <h3>🕐 Recent Transactions</h3>
        <table class="data-table">
            <thead>
                <tr><th>Type</th><th>Label</th><th>Amount (KES)</th><th>Date</th></tr>
            </thead>
            <tbody>
            <?php foreach ($recentRows as $tx): ?>
                <tr>
                    <td><span class="badge <?= $tx['type'] === 'Income' ? 'badge-income' : 'badge-expense' ?>"><?= $tx['type'] ?></span></td>
                    <td><?= htmlspecialchars($tx['label']) ?></td>
                    <td><?= number_format($tx['amount'], 2) ?></td>
                    <td><?= date('d M Y', strtotime($tx['date'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- EXPENSES BY CATEGORY (separate filterable table) -->
    <div class="recent-transactions" style="margin-top: 20px;">

        <!-- Filter form sits inside this section -->
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
            <h3>💸 Expenses Breakdown — <?= date('F Y', strtotime("$selectedMonth-01")) ?></h3>
            <?php if (!empty($allCategories)): ?>
            <form method="GET" class="filter-form" style="margin-bottom:0;">
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
                <?php if ($filterCategory !== ''): ?>
                    <a href="?month=<?= $selectedMonth ?>" class="btn-cancel">✕ Clear</a>
                <?php endif; ?>
            </form>
            <?php endif; ?>
        </div>

        <?php if (!empty($expFilterRows)): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Amount (KES)</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($expFilterRows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['category']) ?></td>
                    <td><?= number_format($row['amount'], 2) ?></td>
                    <td><?= date('d M Y', strtotime($row['date'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td><strong>Total<?= $filterCategory !== '' ? ' (' . htmlspecialchars($filterCategory) . ')' : '' ?></strong></td>
                    <td><strong>KES <?= number_format($filteredTotal, 2) ?></strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        <?php else: ?>
            <p class="empty-row">No expenses found<?= $filterCategory !== '' ? ' for category: ' . htmlspecialchars($filterCategory) : '' ?>.</p>
        <?php endif; ?>
    </div>

    <!-- DAILY CHART -->
    <div class="chart-wrapper">
        <canvas id="financeChart"></canvas>
    </div>

    <!-- MONTHLY TREND -->
    <div class="chart-wrapper">
        <canvas id="trendChart"></canvas>
    </div>

    <!-- CATEGORY BREAKDOWN -->
    <?php if (!empty($categories)): ?>
    <div class="chart-wrapper chart-half">
        <canvas id="categoryChart"></canvas>
    </div>
    <?php endif; ?>

</div>

<script>
const chartDefaults = { responsive: true, maintainAspectRatio: false };

new Chart(document.getElementById('financeChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($daysLabels) ?>,
        datasets: [
            { label: 'Income',   data: <?= json_encode($incomeData) ?>,  borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.15)',  fill: true, tension: 0.4 },
            { label: 'Expenses', data: <?= json_encode($expenseData) ?>, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.15)',  fill: true, tension: 0.4 },
            { label: 'Savings',  data: <?= json_encode($savingsData) ?>, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.15)', fill: true, tension: 0.4 },
            { label: 'Budget',   data: <?= json_encode($budgetData) ?>,  borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.15)', fill: true, tension: 0.4 },
        ]
    },
    options: {
        ...chartDefaults,
        plugins: {
            legend: { position: 'top' },
            title: { display: true, text: 'Daily Finance Overview — <?= date("F Y", strtotime("$selectedMonth-01")) ?>' }
        },
        scales: {
            x: { title: { display: true, text: 'Day of Month' } },
            y: { title: { display: true, text: 'KES' }, beginAtZero: true }
        }
    }
});

new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: [
            '<?= date("M Y", strtotime("$prevMonth-01")) ?>',
            '<?= date("M Y", strtotime("$selectedMonth-01")) ?>',
            'Next Month (Est.)'
        ],
        datasets: [
            { label: 'Income',   data: [<?= $prevIncome ?>,   <?= $totalIncome ?>,   <?= $predIncome ?>],   backgroundColor: '#22c55e' },
            { label: 'Expenses', data: [<?= $prevExpenses ?>, <?= $totalExpenses ?>, <?= $predExpenses ?>], backgroundColor: '#ef4444' },
            { label: 'Savings',  data: [<?= $prevSavings ?>,  <?= $totalSavings ?>,  <?= $predSavings ?>],  backgroundColor: '#3b82f6' },
            { label: 'Budget',   data: [<?= $prevBudget ?>,   <?= $totalBudget ?>,   <?= $predBudget ?>],   backgroundColor: '#f59e0b' },
        ]
    },
    options: {
        ...chartDefaults,
        plugins: {
            legend: { position: 'top' },
            title: { display: true, text: 'Monthly Trend & Prediction' }
        },
        scales: { y: { beginAtZero: true, title: { display: true, text: 'KES' } } }
    }
});

<?php if (!empty($categories)): ?>
new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($categories) ?>,
        datasets: [{
            data: <?= json_encode($categoryTotals) ?>,
            backgroundColor: ['#f87171','#34d399','#60a5fa','#fbbf24','#a78bfa','#f472b6','#38bdf8','#facc15','#6ee7b7','#fb7185']
        }]
    },
    options: {
        ...chartDefaults,
        plugins: {
            legend: { position: 'right' },
            title: { display: true, text: 'Expense Categories' }
        }
    }
});
<?php endif; ?>
</script>

    <!-- jsPDF direct download from dashboard -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <script>
    function quickExportPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ unit: 'mm', format: 'a4' });

        const pw = doc.internal.pageSize.getWidth();
        const ph = doc.internal.pageSize.getHeight();

        const C_BLUE   = [37,  99,  235];
        const C_INK    = [15,  23,  42];
        const C_MID    = [100, 116, 139];
        const C_GREEN  = [22,  163, 74];
        const C_RED    = [220, 38,  38];
        const C_STRIPE = [248, 250, 252];
        const C_WHITE  = [255, 255, 255];

        const month    = "<?= $selectedMonth ?>";
        const label    = "<?= date('F Y', strtotime("$selectedMonth-01")) ?>";
        const userName = "<?= addslashes(htmlspecialchars($userName)) ?>";

        const totalIncome   = <?= $totalIncome ?>;
        const totalExpenses = <?= $totalExpenses ?>;
        const totalSavings  = <?= $totalSavings ?>;
        const totalBudget   = <?= $totalBudget ?>;
        const balance       = <?= $balance ?>;

        function fmt(n) {
            return 'KES ' + parseFloat(n).toLocaleString('en-KE', {
                minimumFractionDigits: 2, maximumFractionDigits: 2
            });
        }

        // ── Header ───────────────────────────────────────
        function drawHeader(subtitle) {
            doc.setFillColor(...C_BLUE);
            doc.rect(0, 0, pw, 18, 'F');
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(10);
            doc.setTextColor(255, 255, 255);
            doc.text('Finance Manager', 14, 11.5);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8);
            doc.text(userName + '  |  Generated: ' + new Date().toLocaleDateString('en-KE'), pw - 14, 11.5, { align: 'right' });
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(15);
            doc.setTextColor(...C_INK);
            doc.text(subtitle, 14, 31);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(9);
            doc.setTextColor(...C_MID);
            doc.text('Period: ' + label, 14, 38);
            doc.setDrawColor(226, 232, 240);
            doc.setLineWidth(0.4);
            doc.line(14, 41, pw - 14, 41);
            return 48;
        }

        function drawSection(text, y) {
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(9);
            doc.setTextColor(...C_BLUE);
            doc.text(text.toUpperCase(), 14, y);
            doc.setDrawColor(...C_BLUE);
            doc.setLineWidth(0.5);
            doc.line(14, y + 2, pw - 14, y + 2);
            return y + 9;
        }

        function addPageNumbers() {
            const total = doc.internal.getNumberOfPages();
            for (let i = 1; i <= total; i++) {
                doc.setPage(i);
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(7.5);
                doc.setTextColor(...C_MID);
                doc.text('Finance Manager — Confidential', 14, ph - 8);
                doc.text('Page ' + i + ' of ' + total, pw / 2, ph - 8, { align: 'center' });
                doc.text(new Date().toLocaleDateString('en-KE'), pw - 14, ph - 8, { align: 'right' });
            }
        }

        // ── Page 1: Summary ───────────────────────────────
        let y = drawHeader('Monthly Financial Report');
        y = drawSection('Monthly Summary — ' + label, y);

        const summaryRows = [
            ['Total Income',   fmt(totalIncome),   C_GREEN],
            ['Total Expenses', fmt(totalExpenses), C_RED],
            ['Total Savings',  fmt(totalSavings),  C_BLUE],
            ['Total Budget',   fmt(totalBudget),   [217,119,6]],
            ['Net Balance',    fmt(balance),       balance >= 0 ? C_GREEN : C_RED],
        ];

        doc.autoTable({
            startY: y,
            head: [['Category', 'Amount']],
            body: summaryRows.map(r => [r[0], r[1]]),
            theme: 'grid',
            headStyles: { fillColor: C_INK, textColor: C_WHITE, fontSize: 9, fontStyle: 'bold' },
            bodyStyles: { fontSize: 10, textColor: C_INK },
            columnStyles: {
                0: { cellWidth: 110 },
                1: { halign: 'right', fontStyle: 'bold' }
            },
            alternateRowStyles: { fillColor: C_STRIPE },
            margin: { left: 14, right: 14 },
            didParseCell: (data) => {
                if (data.section === 'body' && data.column.index === 1) {
                    data.cell.styles.textColor = summaryRows[data.row.index][2];
                }
            }
        });

        y = doc.lastAutoTable.finalY + 14;

        // Balance status banner
        doc.setFillColor(...(balance >= 0 ? [240, 253, 244] : [254, 242, 242]));
        doc.roundedRect(14, y, pw - 28, 14, 3, 3, 'F');
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(9);
        doc.setTextColor(...(balance >= 0 ? C_GREEN : C_RED));
        const statusText = balance >= 0
            ? 'You are in the green — income exceeds expenses by ' + fmt(Math.abs(balance))
            : 'Warning: Expenses exceed income by ' + fmt(Math.abs(balance));
        doc.text(statusText, pw / 2, y + 9, { align: 'center' });

        y += 22;

        // ── Daily data from chart (income/expense by day) ──
        const incomeData  = <?= json_encode($incomeData) ?>;
        const expenseData = <?= json_encode($expenseData) ?>;
        const daysLabels  = <?= json_encode($daysLabels) ?>;

        // Build top 5 income days and top 5 expense days
        const combined = daysLabels.map((d, i) => ({
            day: d,
            income:  incomeData[i]  || 0,
            expense: expenseData[i] || 0,
        })).filter(r => r.income > 0 || r.expense > 0);

        if (combined.length) {
            y = drawSection('Daily Activity Snapshot', y);
            const dayRows = combined.map(r => [
                'Day ' + r.day,
                r.income  > 0 ? fmt(r.income)  : '—',
                r.expense > 0 ? fmt(r.expense) : '—',
            ]);
            doc.autoTable({
                startY: y,
                head: [['Day', 'Income (KES)', 'Expenses (KES)']],
                body: dayRows,
                theme: 'striped',
                headStyles: { fillColor: C_INK, textColor: C_WHITE, fontSize: 8.5, fontStyle: 'bold' },
                bodyStyles: { fontSize: 8.5, textColor: C_INK },
                alternateRowStyles: { fillColor: C_STRIPE },
                columnStyles: {
                    1: { halign: 'right', textColor: C_GREEN },
                    2: { halign: 'right', textColor: C_RED }
                },
                margin: { left: 14, right: 14 },
            });
            y = doc.lastAutoTable.finalY + 10;
        }

        // ── Page 2: Category breakdown ────────────────────
        const categories     = <?= json_encode($categories) ?>;
        const categoryTotals = <?= json_encode($categoryTotals) ?>;

        if (categories.length) {
            doc.addPage();
            y = drawHeader('Monthly Financial Report');
            y = drawSection('Expense Breakdown by Category', y);

            const catRows = categories.map((c, i) => [c, fmt(categoryTotals[i])]);
            doc.autoTable({
                startY: y,
                head: [['Category', 'Total (KES)']],
                body: catRows,
                foot: [['TOTAL', fmt(totalExpenses)]],
                theme: 'striped',
                headStyles: { fillColor: C_INK, textColor: C_WHITE, fontSize: 8.5, fontStyle: 'bold' },
                footStyles: { fillColor: C_INK, textColor: C_WHITE, fontSize: 9, fontStyle: 'bold' },
                bodyStyles: { fontSize: 9, textColor: C_INK },
                alternateRowStyles: { fillColor: C_STRIPE },
                columnStyles: { 1: { halign: 'right', fontStyle: 'bold' } },
                margin: { left: 14, right: 14 },
                showFoot: 'lastPage',
            });
        }

        addPageNumbers();
        doc.save('dashboard_report_' + month + '.pdf');
    }
    </script>

</body>
</html>