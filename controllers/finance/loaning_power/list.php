<?php
/**
 * List finance loaning-power sources (borrowing capacity added to Buying Power).
 * GET/POST { requester_email } -> { loaning: [...] }
 * Raw rows only — Buying Power is computed in overview.php.
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
    'SELECT * FROM finance_loaning_power ORDER BY sort_order ASC, limit_amount DESC, id ASC'
);

$loaning = [];
while ($row = mysqli_fetch_assoc($res)) {
    $row['id']           = (int)   $row['id'];
    $row['limit_amount'] = (float) $row['limit_amount'];
    $row['used_amount']  = (float) $row['used_amount'];
    $row['fx_to_base']   = (float) $row['fx_to_base'];
    $row['sort_order']   = (int)   $row['sort_order'];
    $loaning[] = $row;
}

echo json_encode(['loaning' => $loaning]);
