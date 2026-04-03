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
   PARAMS
========================= */
$type  = $_GET['type']  ?? 'summary';
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
$monthLabel = date('F Y', strtotime("$month-01"));

/* =========================
   FETCH USER
========================= */
$stmtU = $conn->prepare("SELECT fullname FROM users WHERE id = ?");
$stmtU->bind_param("i", $user_id);
$stmtU->execute();
$userName = $stmtU->get_result()->fetch_assoc()['fullname'] ?? 'User';

/* =========================
   FETCH DATA
========================= */
// Income
$s = $conn->prepare("SELECT source, amount, date FROM income WHERE user_id=? AND income_month=? ORDER BY date DESC");
$s->bind_param("is", $user_id, $month);
$s->execute();
$income = $s->get_result()->fetch_all(MYSQLI_ASSOC);

// Expenses
$s = $conn->prepare("SELECT category, amount, date FROM expenses WHERE user_id=? AND DATE_FORMAT(date,'%Y-%m')=? ORDER BY date DESC");
$s->bind_param("is", $user_id, $month);
$s->execute();
$expenses = $s->get_result()->fetch_all(MYSQLI_ASSOC);

// Savings — column is `goal` (not goal_name)
$s = $conn->prepare("SELECT goal, amount, date FROM savings WHERE user_id=? AND savings_month=? ORDER BY date DESC");
$s->bind_param("is", $user_id, $month);
$s->execute();
$savings = $s->get_result()->fetch_all(MYSQLI_ASSOC);

// Budget
$s = $conn->prepare("SELECT category, amount, date FROM budget WHERE user_id=? AND budget_month=? ORDER BY date DESC");
$s->bind_param("is", $user_id, $month);
$s->execute();
$budget = $s->get_result()->fetch_all(MYSQLI_ASSOC);

// Savings goals (active) for overview
$s = $conn->prepare("SELECT title, current_amount, target_amount, due_date FROM savings_goals WHERE user_id=? AND status='active' ORDER BY due_date ASC");
$s->bind_param("i", $user_id);
$s->execute();
$savingsGoals = $s->get_result()->fetch_all(MYSQLI_ASSOC);

// Totals
$totalIncome   = array_sum(array_column($income,   'amount'));
$totalExpenses = array_sum(array_column($expenses, 'amount'));
$totalSavings  = array_sum(array_column($savings,  'amount'));
$totalBudget   = array_sum(array_column($budget,   'amount'));
$balance       = $totalIncome - $totalExpenses;

// Expense by category
$catMap = [];
foreach ($expenses as $e) {
    $catMap[$e['category']] = ($catMap[$e['category']] ?? 0) + $e['amount'];
}
arsort($catMap);

// Savings by goal (for breakdown)
$savGoalMap = [];
foreach ($savings as $s) {
    $savGoalMap[$s['goal']] = ($savGoalMap[$s['goal']] ?? 0) + $s['amount'];
}
arsort($savGoalMap);

// JSON for JS
$jsIncome      = json_encode($income);
$jsExpenses    = json_encode($expenses);
$jsSavings     = json_encode($savings);
$jsBudget      = json_encode($budget);
$jsCatMap      = json_encode($catMap);
$jsSavGoalMap  = json_encode($savGoalMap);
$jsSavingsGoals = json_encode($savingsGoals);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export PDF — <?= htmlspecialchars($monthLabel) ?></title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <style>
        .export-page { max-width: 700px; margin: 40px auto; }
        .export-card {
            background: #fff;
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            margin-bottom: 20px;
        }
        .export-card h3 { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
        .export-card p  { font-size: 13px; color: #64748b; margin-bottom: 20px; }

        .export-section-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .export-options { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }

        .btn-pdf {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 20px;
            background: #1e293b;
            color: #f8fafc;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
            font-family: inherit;
        }
        .btn-pdf:hover { background: #334155; transform: translateY(-1px); }
        .btn-pdf.blue   { background: #2563eb; }
        .btn-pdf.blue:hover   { background: #1d4ed8; }
        .btn-pdf.green  { background: #16a34a; }
        .btn-pdf.green:hover  { background: #15803d; }
        .btn-pdf.red    { background: #dc2626; }
        .btn-pdf.red:hover    { background: #b91c1c; }
        .btn-pdf.amber  { background: #d97706; }
        .btn-pdf.amber:hover  { background: #b45309; }
        .btn-pdf.purple { background: #7c3aed; }
        .btn-pdf.purple:hover { background: #6d28d9; }

        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 24px;
        }
        .summary-item {
            background: #f8fafc;
            border-radius: 10px;
            padding: 14px 16px;
            border-left: 3px solid #e2e8f0;
        }
        .summary-item.income  { border-left-color: #22c55e; }
        .summary-item.expense { border-left-color: #ef4444; }
        .summary-item.savings { border-left-color: #3b82f6; }
        .summary-item.budget  { border-left-color: #f59e0b; }
        .summary-item.balance { border-left-color: #8b5cf6; grid-column: span 2; }
        .summary-item label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; display: block; margin-bottom: 4px; }
        .summary-item span  { font-size: 18px; font-weight: 700; color: #0f172a; }

        .divider { border: none; border-top: 1px solid #f1f5f9; margin: 20px 0; }

        .back-link { font-size: 13px; color: #3b82f6; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 20px; }
        .back-link:hover { color: #1d4ed8; }

        #status {
            font-size: 13px; color: #16a34a; font-weight: 600;
            margin-top: 12px; display: none;
            padding: 10px 14px; background: #f0fdf4;
            border-radius: 8px; border: 1px solid #bbf7d0;
        }
    </style>
</head>
<body>
<div class="main-content">
<div class="export-page">

    <a href="dashboard.php?month=<?= $month ?>" class="back-link">← Back to Dashboard</a>

    <div class="export-card">
        <h3>📄 Export PDF Report</h3>
        <p>Generate a PDF for <strong><?= htmlspecialchars($monthLabel) ?></strong>. All exports download instantly — no server upload needed.</p>

        <!-- Summary preview -->
        <div class="summary-grid">
            <div class="summary-item income">
                <label>Income</label>
                <span>KES <?= number_format($totalIncome, 2) ?></span>
            </div>
            <div class="summary-item expense">
                <label>Expenses</label>
                <span>KES <?= number_format($totalExpenses, 2) ?></span>
            </div>
            <div class="summary-item savings">
                <label>Savings</label>
                <span>KES <?= number_format($totalSavings, 2) ?></span>
            </div>
            <div class="summary-item budget">
                <label>Budget</label>
                <span>KES <?= number_format($totalBudget, 2) ?></span>
            </div>
            <div class="summary-item balance">
                <label>Net Balance</label>
                <span style="color:<?= $balance >= 0 ? '#16a34a' : '#dc2626' ?>">
                    KES <?= number_format($balance, 2) ?>
                    <?= $balance >= 0 ? ' ✅' : ' ⚠️' ?>
                </span>
            </div>
        </div>

        <!-- Full report -->
        <p class="export-section-title">Complete Report</p>
        <div class="export-options">
            <button class="btn-pdf blue" onclick="exportPDF('full')">📊 Full Monthly Report (All Pages)</button>
        </div>

        <div class="divider"></div>

        <!-- Individual exports -->
        <p class="export-section-title">Individual Sections</p>
        <div class="export-options">
            <button class="btn-pdf green"  onclick="exportPDF('income')">💰 Income</button>
            <button class="btn-pdf red"    onclick="exportPDF('expenses')">💸 Expenses</button>
            <button class="btn-pdf"        onclick="exportPDF('savings')">🏦 Savings</button>
            <button class="btn-pdf amber"  onclick="exportPDF('budget')">📅 Budget</button>
            <button class="btn-pdf purple" onclick="exportPDF('goals')">🎯 Savings Goals</button>
        </div>

        <div id="status">✅ PDF generated and downloading...</div>
    </div>

</div>
</div>

<script>
const { jsPDF } = window.jspdf;

const income       = <?= $jsIncome ?>;
const expenses     = <?= $jsExpenses ?>;
const savings      = <?= $jsSavings ?>;
const budget       = <?= $jsBudget ?>;
const catMap       = <?= $jsCatMap ?>;
const savGoalMap   = <?= $jsSavGoalMap ?>;
const savingsGoals = <?= $jsSavingsGoals ?>;

const month      = "<?= $month ?>";
const monthLabel = "<?= addslashes($monthLabel) ?>";
const userName   = "<?= addslashes(htmlspecialchars($userName)) ?>";

const totalIncome   = <?= $totalIncome ?>;
const totalExpenses = <?= $totalExpenses ?>;
const totalSavings  = <?= $totalSavings ?>;
const totalBudget   = <?= $totalBudget ?>;
const balance       = <?= $balance ?>;

// Palette
const C_BLUE   = [37,  99,  235];
const C_INK    = [15,  23,  42];
const C_MID    = [100, 116, 139];
const C_GREEN  = [22,  163, 74];
const C_RED    = [220, 38,  38];
const C_AMBER  = [217, 119, 6];
const C_STRIPE = [248, 250, 252];
const C_WHITE  = [255, 255, 255];

function fmt(n) {
    return 'KES ' + parseFloat(n).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtDate(d) {
    if (!d) return '';
    const dt = new Date(d);
    return dt.toLocaleDateString('en-KE', { day: '2-digit', month: 'short', year: 'numeric' });
}

// ── Page header ──────────────────────────────────────────
function drawHeader(doc, subtitle) {
    const pw = doc.internal.pageSize.getWidth();
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
    doc.text('Period: ' + monthLabel, 14, 38);

    doc.setDrawColor(226, 232, 240);
    doc.setLineWidth(0.4);
    doc.line(14, 41, pw - 14, 41);

    return 48;
}

// ── Section label ─────────────────────────────────────────
function drawSection(doc, text, y) {
    const pw = doc.internal.pageSize.getWidth();
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9);
    doc.setTextColor(...C_BLUE);
    doc.text(text.toUpperCase(), 14, y);
    doc.setDrawColor(...C_BLUE);
    doc.setLineWidth(0.5);
    doc.line(14, y + 2, pw - 14, y + 2);
    return y + 9;
}

// ── "No records" message ──────────────────────────────────
function noRecords(doc, y) {
    doc.setFont('helvetica', 'italic');
    doc.setFontSize(9);
    doc.setTextColor(...C_MID);
    doc.text('No records found for this period.', 14, y + 5);
    return y + 14;
}

// ── Table with totals footer ──────────────────────────────
function drawTable(doc, head, rows, y, colStyles, totalLabel, totalVal) {
    if (!rows.length) return noRecords(doc, y);

    const foot = totalLabel
        ? [Array(head.length).fill('').map((_, i) => i === head.length - 2 ? 'TOTAL' : (i === head.length - 1 ? totalVal : ''))]
        : undefined;

    doc.autoTable({
        startY: y,
        head: [head],
        body: rows,
        foot: foot,
        theme: 'striped',
        headStyles: { fillColor: C_INK, textColor: C_WHITE, fontSize: 8.5, fontStyle: 'bold' },
        footStyles: { fillColor: C_INK, textColor: C_WHITE, fontSize: 9,   fontStyle: 'bold' },
        bodyStyles: { fontSize: 8.5, textColor: C_INK },
        alternateRowStyles: { fillColor: C_STRIPE },
        columnStyles: colStyles || {},
        margin: { left: 14, right: 14 },
        showFoot: 'lastPage',
    });

    return doc.lastAutoTable.finalY + 10;
}

// ── Summary table ─────────────────────────────────────────
function drawSummaryTable(doc, y) {
    const rows = [
        ['💰 Total Income',   fmt(totalIncome),   'green'],
        ['💸 Total Expenses', fmt(totalExpenses), 'red'],
        ['🏦 Total Savings',  fmt(totalSavings),  'blue'],
        ['📅 Total Budget',   fmt(totalBudget),   'amber'],
        ['💼 Net Balance',    fmt(balance),       balance >= 0 ? 'green' : 'red'],
    ];

    doc.autoTable({
        startY: y,
        head: [['Category', 'Amount']],
        body: rows.map(r => [r[0], r[1]]),
        theme: 'grid',
        headStyles: { fillColor: C_INK, textColor: C_WHITE, fontSize: 9, fontStyle: 'bold' },
        bodyStyles: { fontSize: 9, textColor: C_INK },
        columnStyles: {
            0: { cellWidth: 110 },
            1: { halign: 'right', fontStyle: 'bold' }
        },
        alternateRowStyles: { fillColor: C_STRIPE },
        margin: { left: 14, right: 14 },
        didParseCell: (data) => {
            if (data.section !== 'body' || data.column.index !== 1) return;
            const c = rows[data.row.index][2];
            if (c === 'green') data.cell.styles.textColor = C_GREEN;
            if (c === 'red')   data.cell.styles.textColor = C_RED;
            if (c === 'amber') data.cell.styles.textColor = C_AMBER;
            if (c === 'blue')  data.cell.styles.textColor = C_BLUE;
        }
    });

    return doc.lastAutoTable.finalY + 10;
}

// ── Page numbers ──────────────────────────────────────────
function addPageNumbers(doc) {
    const total = doc.internal.getNumberOfPages();
    const pw = doc.internal.pageSize.getWidth();
    const ph = doc.internal.pageSize.getHeight();
    for (let i = 1; i <= total; i++) {
        doc.setPage(i);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7.5);
        doc.setTextColor(...C_MID);
        doc.text('Finance Manager — Confidential', 14, ph - 8);
        doc.text(`Page ${i} of ${total}`, pw / 2, ph - 8, { align: 'center' });
        doc.text(new Date().toLocaleDateString('en-KE'), pw - 14, ph - 8, { align: 'right' });
    }
}

// ── Main export ───────────────────────────────────────────
function exportPDF(type) {
    const doc = new jsPDF({ unit: 'mm', format: 'a4' });
    let y;

    if (type === 'income') {
        y = drawHeader(doc, 'Income Report');
        y = drawSection(doc, 'Income Records — ' + monthLabel, y);
        const rows = income.map(r => [r.source, fmtDate(r.date), fmt(r.amount)]);
        drawTable(doc, ['Source / Category', 'Date', 'Amount (KES)'], rows, y,
            { 2: { halign: 'right', fontStyle: 'bold' } }, 'TOTAL', fmt(totalIncome));

    } else if (type === 'expenses') {
        y = drawHeader(doc, 'Expenses Report');
        y = drawSection(doc, 'Expense Records — ' + monthLabel, y);
        const rows = expenses.map(r => [r.category, fmtDate(r.date), fmt(r.amount)]);
        y = drawTable(doc, ['Category', 'Date', 'Amount (KES)'], rows, y,
            { 2: { halign: 'right', fontStyle: 'bold' } }, 'TOTAL', fmt(totalExpenses));

        if (Object.keys(catMap).length) {
            y = drawSection(doc, 'Breakdown by Category', y);
            const catRows = Object.entries(catMap).map(([k, v]) => [k, fmt(v)]);
            drawTable(doc, ['Category', 'Total (KES)'], catRows, y,
                { 1: { halign: 'right', fontStyle: 'bold' } }, null);
        }

    } else if (type === 'savings') {
        y = drawHeader(doc, 'Savings Report');
        y = drawSection(doc, 'Savings Records — ' + monthLabel, y);
        // savings column is `goal`
        const rows = savings.map(r => [r.goal, fmtDate(r.date), fmt(r.amount)]);
        y = drawTable(doc, ['Goal', 'Date', 'Amount (KES)'], rows, y,
            { 2: { halign: 'right', fontStyle: 'bold' } }, 'TOTAL', fmt(totalSavings));

        if (Object.keys(savGoalMap).length) {
            y = drawSection(doc, 'Breakdown by Goal', y);
            const goalRows = Object.entries(savGoalMap).map(([k, v]) => [k, fmt(v)]);
            drawTable(doc, ['Goal', 'Total Saved (KES)'], goalRows, y,
                { 1: { halign: 'right', fontStyle: 'bold' } }, null);
        }

    } else if (type === 'budget') {
        y = drawHeader(doc, 'Budget Report');
        y = drawSection(doc, 'Budget Records — ' + monthLabel, y);
        const rows = budget.map(r => [r.category, fmtDate(r.date), fmt(r.amount)]);
        drawTable(doc, ['Category', 'Date', 'Amount (KES)'], rows, y,
            { 2: { halign: 'right', fontStyle: 'bold' } }, 'TOTAL', fmt(totalBudget));

    } else if (type === 'goals') {
        y = drawHeader(doc, 'Savings Goals Report');
        y = drawSection(doc, 'Active Savings Goals', y);
        if (!savingsGoals.length) {
            noRecords(doc, y);
        } else {
            const rows = savingsGoals.map(g => {
                const pct = g.target_amount > 0
                    ? Math.min(100, Math.round((g.current_amount / g.target_amount) * 100))
                    : 0;
                return [
                    g.title,
                    fmt(g.current_amount),
                    g.target_amount > 0 ? fmt(g.target_amount) : 'Open',
                    fmtDate(g.due_date),
                    pct + '%'
                ];
            });
            drawTable(doc,
                ['Goal', 'Saved', 'Target', 'Due Date', 'Progress'],
                rows, y,
                {
                    1: { halign: 'right' },
                    2: { halign: 'right' },
                    4: { halign: 'center', fontStyle: 'bold' }
                }, null);
        }

    } else {
        // ── FULL REPORT ──────────────────────────────────

        // Page 1: Summary
        y = drawHeader(doc, 'Monthly Financial Report');
        y = drawSection(doc, 'Monthly Summary — ' + monthLabel, y);
        drawSummaryTable(doc, y);

        // Page 2: Income
        doc.addPage();
        y = drawHeader(doc, 'Monthly Financial Report');
        y = drawSection(doc, 'Income Records', y);
        const incRows = income.map(r => [r.source, fmtDate(r.date), fmt(r.amount)]);
        drawTable(doc, ['Source / Category', 'Date', 'Amount (KES)'], incRows, y,
            { 2: { halign: 'right', fontStyle: 'bold' } }, 'TOTAL', fmt(totalIncome));

        // Page 3: Expenses
        doc.addPage();
        y = drawHeader(doc, 'Monthly Financial Report');
        y = drawSection(doc, 'Expense Records', y);
        const expRows = expenses.map(r => [r.category, fmtDate(r.date), fmt(r.amount)]);
        y = drawTable(doc, ['Category', 'Date', 'Amount (KES)'], expRows, y,
            { 2: { halign: 'right', fontStyle: 'bold' } }, 'TOTAL', fmt(totalExpenses));
        if (Object.keys(catMap).length) {
            y = drawSection(doc, 'Breakdown by Category', y);
            drawTable(doc, ['Category', 'Total (KES)'],
                Object.entries(catMap).map(([k, v]) => [k, fmt(v)]), y,
                { 1: { halign: 'right', fontStyle: 'bold' } }, null);
        }

        // Page 4: Savings + Goals
        doc.addPage();
        y = drawHeader(doc, 'Monthly Financial Report');
        y = drawSection(doc, 'Savings Records', y);
        const savRows = savings.map(r => [r.goal, fmtDate(r.date), fmt(r.amount)]);
        y = drawTable(doc, ['Goal', 'Date', 'Amount (KES)'], savRows, y,
            { 2: { halign: 'right', fontStyle: 'bold' } }, 'TOTAL', fmt(totalSavings));
        if (Object.keys(savGoalMap).length) {
            y = drawSection(doc, 'Savings Breakdown by Goal', y);
            y = drawTable(doc, ['Goal', 'Total Saved (KES)'],
                Object.entries(savGoalMap).map(([k, v]) => [k, fmt(v)]), y,
                { 1: { halign: 'right', fontStyle: 'bold' } }, null);
        }
        if (savingsGoals.length) {
            y = drawSection(doc, 'Active Savings Goals', y);
            const goalRows = savingsGoals.map(g => {
                const pct = g.target_amount > 0
                    ? Math.min(100, Math.round((g.current_amount / g.target_amount) * 100))
                    : 0;
                return [g.title, fmt(g.current_amount), g.target_amount > 0 ? fmt(g.target_amount) : 'Open', fmtDate(g.due_date), pct + '%'];
            });
            drawTable(doc, ['Goal', 'Saved', 'Target', 'Due Date', 'Progress'], goalRows, y,
                { 1: {halign:'right'}, 2: {halign:'right'}, 4: {halign:'center', fontStyle:'bold'} }, null);
        }

        // Page 5: Budget
        doc.addPage();
        y = drawHeader(doc, 'Monthly Financial Report');
        y = drawSection(doc, 'Budget Records', y);
        const budRows = budget.map(r => [r.category, fmtDate(r.date), fmt(r.amount)]);
        drawTable(doc, ['Category', 'Date', 'Amount (KES)'], budRows, y,
            { 2: { halign: 'right', fontStyle: 'bold' } }, 'TOTAL', fmt(totalBudget));
    }

    addPageNumbers(doc);
    doc.save(`finance_${type}_${month}.pdf`);

    const el = document.getElementById('status');
    el.style.display = 'block';
    setTimeout(() => el.style.display = 'none', 3500);
}
</script>
</body>
</html>