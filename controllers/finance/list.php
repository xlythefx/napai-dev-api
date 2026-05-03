<?php
/**
 * List finance transactions with pagination and filters.
 * GET/POST { requester_email, page?, per_page?, type?, category?, priority?,
 *             from_date?, to_date? }
 * -> { transactions, total, page, per_page, total_pages }
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

$page    = max(1, (int) ($input['page'] ?? 1));
$perPage = min(100, max(1, (int) ($input['per_page'] ?? 20)));
$offset  = ($page - 1) * $perPage;

$where  = [];
$params = [];
$types  = '';

if (!empty($input['type']) && in_array($input['type'], ['income', 'expense'], true)) {
    $where[] = 'type = ?';
    $params[] = $input['type'];
    $types .= 's';
}
if (!empty($input['category'])) {
    $where[] = 'category = ?';
    $params[] = $input['category'];
    $types .= 's';
}
if (!empty($input['priority']) && in_array($input['priority'], ['high', 'mid', 'low'], true)) {
    $where[] = 'priority = ?';
    $params[] = $input['priority'];
    $types .= 's';
}
if (!empty($input['from_date'])) {
    $where[] = 'transaction_date >= ?';
    $params[] = $input['from_date'];
    $types .= 's';
}
if (!empty($input['to_date'])) {
    $where[] = 'transaction_date <= ?';
    $params[] = $input['to_date'];
    $types .= 's';
}

$whereStr = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS n FROM finance_transactions $whereStr");
if ($types && $params) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$total = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['n'];
mysqli_stmt_close($stmt);

// Data
$stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM finance_transactions $whereStr ORDER BY transaction_date DESC, id DESC LIMIT ? OFFSET ?"
);
$fullTypes  = $types . 'ii';
$fullParams = array_merge($params, [$perPage, $offset]);
mysqli_stmt_bind_param($stmt, $fullTypes, ...$fullParams);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$transactions = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['id']     = (int)   $row['id'];
    $row['amount'] = (float) $row['amount'];
    $transactions[] = $row;
}
mysqli_stmt_close($stmt);

echo json_encode([
    'transactions' => $transactions,
    'total'        => $total,
    'page'         => $page,
    'per_page'     => $perPage,
    'total_pages'  => max(1, (int) ceil($total / $perPage)),
]);
