<?php
/**
 * List finance goals (net-worth milestones / savings targets).
 * GET/POST { requester_email } -> { goals: [...] }
 * Raw rows only — progress vs Total Balance is computed in overview.php.
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
    'SELECT * FROM finance_goals ORDER BY sort_order ASC, target_amount ASC, id ASC'
);

$goals = [];
while ($row = mysqli_fetch_assoc($res)) {
    $row['id']            = (int)   $row['id'];
    $row['target_amount'] = (float) $row['target_amount'];
    $row['fx_to_base']    = (float) $row['fx_to_base'];
    $row['sort_order']    = (int)   $row['sort_order'];
    $goals[] = $row;
}

echo json_encode(['goals' => $goals]);
