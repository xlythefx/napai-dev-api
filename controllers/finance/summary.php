<?php
/**
 * Finance summary + chart data.
 * GET/POST { requester_email, from_date?, to_date? }
 * -> { summary, monthly_trend, category_breakdown, equity_curve }
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/auth.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if (!$conn) { http_response_code(500); echo json_encode(['error' => 'DB fail']); exit; }

$input = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (json_decode(file_get_contents('php://input'), true) ?: [])
    : $_GET;

require_admin($conn, $input['requester_email'] ?? '');

$fromDate = !empty($input['from_date']) ? $input['from_date'] : null;
$toDate   = !empty($input['to_date'])   ? $input['to_date']   : null;

// --- Period totals ---
$periodWhere  = [];
$periodParams = [];
$periodTypes  = '';
if ($fromDate) { $periodWhere[] = 'transaction_date >= ?'; $periodParams[] = $fromDate; $periodTypes .= 's'; }
if ($toDate)   { $periodWhere[] = 'transaction_date <= ?'; $periodParams[] = $toDate;   $periodTypes .= 's'; }
$periodClause = $periodWhere ? ('WHERE ' . implode(' AND ', $periodWhere)) : '';

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) AS total_income,
        SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS total_expense
     FROM finance_transactions $periodClause"
);
if ($periodTypes) mysqli_stmt_bind_param($stmt, $periodTypes, ...$periodParams);
mysqli_stmt_execute($stmt);
$totalsRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$totalIncome  = (float) ($totalsRow['total_income']  ?? 0);
$totalExpense = (float) ($totalsRow['total_expense'] ?? 0);

// --- All-time equity ---
$stmt = mysqli_prepare(
    $conn,
    "SELECT SUM(CASE WHEN type='income' THEN amount ELSE -amount END) AS equity
     FROM finance_transactions"
);
mysqli_stmt_execute($stmt);
$eqRow  = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$equity = (float) ($eqRow['equity'] ?? 0);
mysqli_stmt_close($stmt);

// --- Monthly trend (last 12 months from today) ---
$stmt = mysqli_prepare(
    $conn,
    "SELECT
        DATE_FORMAT(transaction_date, '%Y-%m') AS month,
        SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) AS income,
        SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS expense,
        SUM(CASE WHEN type='income'  THEN amount ELSE -amount END) AS net
     FROM finance_transactions
     WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
     GROUP BY month
     ORDER BY month ASC"
);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$monthlyTrend = [];
while ($row = mysqli_fetch_assoc($result)) {
    $monthlyTrend[] = [
        'month'   => $row['month'],
        'income'  => (float) $row['income'],
        'expense' => (float) $row['expense'],
        'net'     => (float) $row['net'],
    ];
}
mysqli_stmt_close($stmt);

// --- Category breakdown (within period) ---
$stmt = mysqli_prepare(
    $conn,
    "SELECT type, category, SUM(amount) AS total
     FROM finance_transactions $periodClause
     GROUP BY type, category
     ORDER BY type, total DESC"
);
if ($periodTypes) mysqli_stmt_bind_param($stmt, $periodTypes, ...$periodParams);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$categoryBreakdown = [];
while ($row = mysqli_fetch_assoc($result)) {
    $categoryBreakdown[] = [
        'type'     => $row['type'],
        'category' => $row['category'],
        'total'    => (float) $row['total'],
    ];
}
mysqli_stmt_close($stmt);

// --- Equity curve (all months, cumulative) ---
$stmt = mysqli_prepare(
    $conn,
    "SELECT
        DATE_FORMAT(transaction_date, '%Y-%m') AS month,
        SUM(CASE WHEN type='income' THEN amount ELSE -amount END) AS net
     FROM finance_transactions
     GROUP BY month
     ORDER BY month ASC"
);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$equityCurve = [];
$running = 0.0;
while ($row = mysqli_fetch_assoc($result)) {
    $running += (float) $row['net'];
    $equityCurve[] = ['month' => $row['month'], 'equity' => round($running, 2)];
}
mysqli_stmt_close($stmt);

echo json_encode([
    'summary' => [
        'total_income'   => round($totalIncome,  2),
        'total_expense'  => round($totalExpense, 2),
        'net_cash_flow'  => round($totalIncome - $totalExpense, 2),
        'equity'         => round($equity, 2),
    ],
    'monthly_trend'      => $monthlyTrend,
    'category_breakdown' => $categoryBreakdown,
    'equity_curve'       => $equityCurve,
]);
