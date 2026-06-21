<?php
/**
 * List finance accounts (bank/wallet balances).
 * GET/POST { requester_email } -> { accounts: [...] }
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

$res = mysqli_query(
    $conn,
    'SELECT * FROM finance_accounts ORDER BY sort_order ASC, id ASC'
);

$accounts = [];
while ($row = mysqli_fetch_assoc($res)) {
    $row['id']         = (int)   $row['id'];
    $row['balance']    = (float) $row['balance'];
    $row['fx_to_base'] = (float) $row['fx_to_base'];
    $row['sort_order'] = (int)   $row['sort_order'];
    $accounts[] = $row;
}

echo json_encode(['accounts' => $accounts]);
