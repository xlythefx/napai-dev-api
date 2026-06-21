<?php
/**
 * Finance accounts & runway overview (base currency = PHP).
 * GET/POST { requester_email }
 * -> {
 *      base_currency,
 *      accounts: [...account, balance_base],
 *      total_balance_base,
 *      balances_by_currency: [{ currency, total }],
 *      monthly_income_base,
 *      monthly_expense_base,
 *      net_monthly_base,        // breakeven figure: >=0 means at/above breakeven
 *      runway_months,           // null = infinite (not burning)
 *      runway_projection: [{ month, balance }],
 *      expense_breakdown: [{ category, total_base }]
 *    }
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

$BASE_CURRENCY = 'PHP';

// ---- Accounts ----
$accounts          = [];
$totalBalanceBase  = 0.0;
$byCurrency        = []; // currency => raw total

$res = mysqli_query($conn, 'SELECT * FROM finance_accounts ORDER BY sort_order ASC, id ASC');
while ($row = mysqli_fetch_assoc($res)) {
    $balance     = (float) $row['balance'];
    $fx          = (float) $row['fx_to_base'];
    $balanceBase = $balance * $fx;

    $row['id']           = (int) $row['id'];
    $row['balance']      = $balance;
    $row['fx_to_base']   = $fx;
    $row['sort_order']   = (int) $row['sort_order'];
    $row['balance_base'] = round($balanceBase, 2);
    $accounts[] = $row;

    $totalBalanceBase += $balanceBase;
    $cur = $row['currency'];
    $byCurrency[$cur] = ($byCurrency[$cur] ?? 0) + $balance;
}

$balancesByCurrency = [];
foreach ($byCurrency as $cur => $total) {
    $balancesByCurrency[] = ['currency' => $cur, 'total' => round($total, 2)];
}

// ---- Capital allocations (reserved capital) ----
// Each allocation amount (converted to base PHP) is deducted from Total Balance
// to produce the Spendable Balance, which drives Runway + Goals progress below.
$allocations         = [];
$totalAllocationBase = 0.0;
$allocTable = mysqli_query($conn, 'SHOW TABLES LIKE "finance_capital_allocations"');
if ($allocTable && mysqli_num_rows($allocTable) > 0) {
    $res = mysqli_query($conn, 'SELECT * FROM finance_capital_allocations ORDER BY sort_order ASC, amount DESC, id ASC');
    while ($row = mysqli_fetch_assoc($res)) {
        $amount     = (float) $row['amount'];
        $fx         = (float) $row['fx_to_base'];
        $amountBase = $amount * $fx;

        $row['id']          = (int)   $row['id'];
        $row['amount']      = $amount;
        $row['fx_to_base']  = $fx;
        $row['sort_order']  = (int)   $row['sort_order'];
        $row['amount_base'] = round($amountBase, 2);
        $allocations[] = $row;

        $totalAllocationBase += $amountBase;
    }
}

$spendableBalanceBase = $totalBalanceBase - $totalAllocationBase;

// ---- Active recurring items ----
$monthlyIncomeBase  = 0.0;
$monthlyExpenseBase = 0.0;
$expenseByCategory  = []; // category => total_base

$res = mysqli_query($conn, "SELECT type, category, amount, fx_to_base FROM finance_recurring WHERE active = 1");
while ($row = mysqli_fetch_assoc($res)) {
    $amountBase = (float) $row['amount'] * (float) $row['fx_to_base'];
    if ($row['type'] === 'income') {
        $monthlyIncomeBase += $amountBase;
    } else {
        $monthlyExpenseBase += $amountBase;
        $cat = $row['category'];
        $expenseByCategory[$cat] = ($expenseByCategory[$cat] ?? 0) + $amountBase;
    }
}

$netMonthlyBase = $monthlyIncomeBase - $monthlyExpenseBase;

$expenseBreakdown = [];
foreach ($expenseByCategory as $cat => $total) {
    $expenseBreakdown[] = ['category' => $cat, 'total_base' => round($total, 2)];
}
usort($expenseBreakdown, fn($a, $b) => $b['total_base'] <=> $a['total_base']);

// ---- Runway (measured against Spendable Balance) ----
$runwayMonths = null; // null = infinite (net >= 0)
if ($netMonthlyBase < 0) {
    $runwayMonths = round($spendableBalanceBase / abs($netMonthlyBase), 1);
}

// ---- Runway projection: next 24 months (or until balance <= 0) ----
$runwayProjection = [];
$balance = $spendableBalanceBase;
$y = (int) date('Y');
$m = (int) date('n');
// month 0 = current balance now
$runwayProjection[] = ['month' => sprintf('%04d-%02d', $y, $m), 'balance' => round($balance, 2)];
for ($i = 1; $i <= 24; $i++) {
    $balance += $netMonthlyBase;
    $m++;
    if ($m > 12) { $m = 1; $y++; }
    $runwayProjection[] = ['month' => sprintf('%04d-%02d', $y, $m), 'balance' => round($balance, 2)];
    if ($netMonthlyBase < 0 && $balance <= 0) break;
}

// ---- Financial goals: progress measured against Spendable Balance ----
$goals      = [];
$goalsTable = mysqli_query($conn, 'SHOW TABLES LIKE "finance_goals"');
if ($goalsTable && mysqli_num_rows($goalsTable) > 0) {
    $res = mysqli_query($conn, 'SELECT * FROM finance_goals ORDER BY sort_order ASC, target_amount ASC, id ASC');
    while ($row = mysqli_fetch_assoc($res)) {
        $target     = (float) $row['target_amount'];
        $fx         = (float) $row['fx_to_base'];
        $targetBase = $target * $fx;

        $progressPct   = $targetBase > 0 ? min(100.0, max(0.0, $spendableBalanceBase) / $targetBase * 100.0) : 0.0;
        $remainingBase = max(0.0, $targetBase - $spendableBalanceBase);

        $row['id']            = (int)   $row['id'];
        $row['target_amount'] = $target;
        $row['fx_to_base']    = $fx;
        $row['sort_order']    = (int)   $row['sort_order'];
        $row['target_base']   = round($targetBase, 2);
        $row['progress_pct']  = round($progressPct, 1);
        $row['remaining_base']= round($remainingBase, 2);
        $row['achieved']      = $spendableBalanceBase >= $targetBase;
        $goals[] = $row;
    }
}

echo json_encode([
    'base_currency'         => $BASE_CURRENCY,
    'accounts'              => $accounts,
    'total_balance_base'    => round($totalBalanceBase, 2),
    'total_allocation_base' => round($totalAllocationBase, 2),
    'spendable_balance_base'=> round($spendableBalanceBase, 2),
    'capital_allocations'   => $allocations,
    'balances_by_currency'  => $balancesByCurrency,
    'monthly_income_base'  => round($monthlyIncomeBase, 2),
    'monthly_expense_base' => round($monthlyExpenseBase, 2),
    'net_monthly_base'     => round($netMonthlyBase, 2),
    'runway_months'        => $runwayMonths,
    'runway_projection'    => $runwayProjection,
    'expense_breakdown'    => $expenseBreakdown,
    'goals'                => $goals,
]);
