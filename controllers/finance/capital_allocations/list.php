<?php
/**
 * List finance capital allocations (reserved capital deducted from Total Balance).
 * GET/POST { requester_email } -> { allocations: [...] }
 * Raw rows only — Spendable Balance is computed in overview.php.
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
    'SELECT * FROM finance_capital_allocations ORDER BY sort_order ASC, amount DESC, id ASC'
);

$allocations = [];
while ($row = mysqli_fetch_assoc($res)) {
    $row['id']         = (int)   $row['id'];
    $row['amount']     = (float) $row['amount'];
    $row['fx_to_base'] = (float) $row['fx_to_base'];
    $row['sort_order'] = (int)   $row['sort_order'];
    $allocations[] = $row;
}

echo json_encode(['allocations' => $allocations]);
