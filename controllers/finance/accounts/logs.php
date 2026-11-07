<?php
/**
 * Read the bank-account change trail.
 * POST { requester_email, account_id?, limit? }
 *   account_id omitted -> recent changes across all accounts.
 * -> { logs: [{ id, account_id, account_name, action, changes:[{field,from,to}],
 *               balance_before, balance_after, balance_delta, currency, note,
 *               changed_by, changed_by_email, changed_at }] }
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

$accountId = isset($input['account_id']) ? (int) $input['account_id'] : 0;
$limit     = isset($input['limit']) ? (int) $input['limit'] : 50;
if ($limit < 1)   $limit = 1;
if ($limit > 200) $limit = 200;

if ($accountId > 0) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT * FROM finance_account_logs WHERE account_id = ? ORDER BY changed_at DESC, id DESC LIMIT ?'
    );
    mysqli_stmt_bind_param($stmt, 'ii', $accountId, $limit);
} else {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT * FROM finance_account_logs ORDER BY changed_at DESC, id DESC LIMIT ?'
    );
    mysqli_stmt_bind_param($stmt, 'i', $limit);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$logs = [];
while ($row = mysqli_fetch_assoc($res)) {
    $logs[] = [
        'id'               => (int) $row['id'],
        'account_id'       => $row['account_id'] !== null ? (int) $row['account_id'] : null,
        'account_name'     => $row['account_name'],
        'action'           => $row['action'],
        'changes'          => $row['changes'] ? json_decode($row['changes'], true) : null,
        'balance_before'   => $row['balance_before'] !== null ? (float) $row['balance_before'] : null,
        'balance_after'    => $row['balance_after']  !== null ? (float) $row['balance_after']  : null,
        'balance_delta'    => $row['balance_delta']  !== null ? (float) $row['balance_delta']  : null,
        'currency'         => $row['currency'],
        'note'             => $row['note'],
        'changed_by'       => $row['changed_by'] !== null ? (int) $row['changed_by'] : null,
        'changed_by_email' => $row['changed_by_email'],
        'changed_at'       => $row['changed_at'],
    ];
}
mysqli_stmt_close($stmt);

echo json_encode(['logs' => $logs]);
