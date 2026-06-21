<?php
/**
 * List recurring monthly income/expense items.
 * GET/POST { requester_email, type?, active? } -> { recurring: [...] }
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

$where  = [];
$params = [];
$types  = '';
if (!empty($input['type']) && in_array($input['type'], ['income', 'expense'], true)) {
    $where[] = 'type = ?'; $params[] = $input['type']; $types .= 's';
}
if (isset($input['active']) && $input['active'] !== '' && $input['active'] !== null) {
    $where[] = 'active = ?'; $params[] = (int) (bool) $input['active']; $types .= 'i';
}
$whereStr = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = mysqli_prepare($conn, "SELECT * FROM finance_recurring $whereStr ORDER BY type ASC, label ASC");
if ($types) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$recurring = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['id']           = (int)   $row['id'];
    $row['amount']       = (float) $row['amount'];
    $row['fx_to_base']   = (float) $row['fx_to_base'];
    $row['day_of_month'] = $row['day_of_month'] !== null ? (int) $row['day_of_month'] : null;
    $row['active']       = (int)   $row['active'];
    $recurring[] = $row;
}
mysqli_stmt_close($stmt);

echo json_encode(['recurring' => $recurring]);
