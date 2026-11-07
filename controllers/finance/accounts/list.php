<?php
/**
 * List finance (bank) accounts, including archived ones and per-row base-currency
 * equivalents so the Bank Accounts page is self-contained (no overview call).
 * GET/POST { requester_email }
 * -> { base_currency, accounts: [{ ..., balance_base, status, notes }], total_balance_base }
 *    total_balance_base counts ACTIVE accounts only.
 */

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../lib/auth.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if (!$conn) { http_response_code(500); echo json_encode(['error' => 'DB fail']); exit; }

$input = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (json_decode(file_get_contents('php://input'), true) ?: [])
    : $_GET;

require_admin($conn, $input['requester_email'] ?? '');

$BASE_CURRENCY   = 'PHP';
$totalActiveBase = 0.0;

$res = mysqli_query(
    $conn,
    "SELECT * FROM finance_accounts ORDER BY (status = 'archived') ASC, sort_order ASC, id ASC"
);

$accounts = [];
while ($row = mysqli_fetch_assoc($res)) {
    $balance     = (float) $row['balance'];
    $fx          = (float) $row['fx_to_base'];
    $status      = $row['status'] ?? 'active';
    $balanceBase = round($balance * $fx, 2);

    $row['id']           = (int)   $row['id'];
    $row['balance']      = $balance;
    $row['fx_to_base']   = $fx;
    $row['sort_order']   = (int)   $row['sort_order'];
    $row['status']       = $status;
    $row['balance_base'] = $balanceBase;
    $accounts[] = $row;

    if ($status !== 'archived') $totalActiveBase += $balanceBase;
}

echo json_encode([
    'base_currency'      => $BASE_CURRENCY,
    'accounts'           => $accounts,
    'total_balance_base' => round($totalActiveBase, 2),
]);
