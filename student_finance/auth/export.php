<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$type    = $_GET['type'] ?? '';
$month   = $_GET['month'] ?? date('Y-m');

// Validate month
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

// Allowed types
$allowed = ['income', 'expenses', 'budget', 'savings', 'report'];
if (!in_array($type, $allowed)) {
    die('Invalid export type.');
}

$filename = "{$type}_{$month}.csv";
header('Content-Type: text/csv');
header("Content-Disposition: attachment; filename=\"$filename\"");

$out = fopen('php://output', 'w');

switch ($type) {
    case 'income':
        fputcsv($out, ['Source / Category', 'Amount (KES)', 'Date']);
        $stmt = $conn->prepare("SELECT source, amount, date FROM income WHERE user_id=? AND income_month=? ORDER BY date DESC");
        $stmt->bind_param("is", $user_id, $month);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            fputcsv($out, [$r['source'], number_format($r['amount'], 2), date('d M Y', strtotime($r['date']))]);
        }
        break;

    case 'expenses':
        fputcsv($out, ['Category', 'Amount (KES)', 'Date']);
        $stmt = $conn->prepare("SELECT category, amount, date FROM expenses WHERE user_id=? AND expense_month=? ORDER BY date DESC");
        $stmt->bind_param("is", $user_id, $month);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            fputcsv($out, [$r['category'], number_format($r['amount'], 2), date('d M Y', strtotime($r['date']))]);
        }
        break;

    case 'budget':
        fputcsv($out, ['Category', 'Budget (KES)', 'Date']);
        $stmt = $conn->prepare("SELECT category, amount, date FROM budget WHERE user_id=? AND budget_month=? ORDER BY date DESC");
        $stmt->bind_param("is", $user_id, $month);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            fputcsv($out, [$r['category'], number_format($r['amount'], 2), date('d M Y', strtotime($r['date']))]);
        }
        break;

    case 'savings':
        fputcsv($out, ['Goal', 'Amount (KES)', 'Date']);
        $stmt = $conn->prepare("SELECT goal, amount, date FROM savings WHERE user_id=? AND savings_month=? ORDER BY date DESC");
        $stmt->bind_param("is", $user_id, $month);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            fputcsv($out, [$r['goal'], number_format($r['amount'], 2), date('d M Y', strtotime($r['date']))]);
        }
        break;

    case 'report':
        $year = (int)($_GET['year'] ?? date('Y'));
        $filename = "annual_report_{$year}.csv";
        header("Content-Disposition: attachment; filename=\"$filename\"");

        fputcsv($out, ["Annual Finance Report — $year"]);
        fputcsv($out, []);
        fputcsv($out, ['Month', 'Income (KES)', 'Expenses (KES)', 'Savings (KES)', 'Budget (KES)', 'Balance (KES)']);

        $months = [];
        $totInc = $totExp = $totSav = $totBud = 0;

        for ($m = 1; $m <= 12; $m++) {
            $mName = date('F', mktime(0,0,0,$m,1));

            $stI = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS t FROM income WHERE user_id=? AND YEAR(date)=? AND MONTH(date)=?");
            $stI->bind_param("iii", $user_id, $year, $m); $stI->execute();
            $inc = (float)$stI->get_result()->fetch_assoc()['t'];

            $stE = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE user_id=? AND YEAR(date)=? AND MONTH(date)=?");
            $stE->bind_param("iii", $user_id, $year, $m); $stE->execute();
            $exp = (float)$stE->get_result()->fetch_assoc()['t'];

            $stS = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS t FROM savings WHERE user_id=? AND YEAR(date)=? AND MONTH(date)=?");
            $stS->bind_param("iii", $user_id, $year, $m); $stS->execute();
            $sav = (float)$stS->get_result()->fetch_assoc()['t'];

            $stB = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS t FROM budget WHERE user_id=? AND YEAR(date)=? AND MONTH(date)=?");
            $stB->bind_param("iii", $user_id, $year, $m); $stB->execute();
            $bud = (float)$stB->get_result()->fetch_assoc()['t'];

            $bal = $inc - $exp;
            fputcsv($out, [$mName, number_format($inc,2), number_format($exp,2), number_format($sav,2), number_format($bud,2), number_format($bal,2)]);

            $totInc += $inc; $totExp += $exp; $totSav += $sav; $totBud += $bud;
        }

        fputcsv($out, []);
        fputcsv($out, ['TOTAL', number_format($totInc,2), number_format($totExp,2), number_format($totSav,2), number_format($totBud,2), number_format($totInc-$totExp,2)]);
        break;
}

fclose($out);
exit();