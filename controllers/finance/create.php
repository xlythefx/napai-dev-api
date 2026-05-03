<?php
/**
 * Create a finance transaction.
 * POST { requester_email, type, category, amount, transaction_date,
 *         subcategory?, description?, priority?, currency?, payment_method?, tags? }
 * -> { success, transaction }
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/auth.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }
if (!$conn) { http_response_code(500); echo json_encode(['error' => 'DB fail']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$admin = require_admin($conn, $input['requester_email'] ?? '');

$type          = trim($input['type'] ?? '');
$category      = trim($input['category'] ?? '');
$subcategory   = trim($input['subcategory'] ?? '') ?: null;
$amount        = isset($input['amount']) ? (float) $input['amount'] : -1;
$currency      = strtoupper(substr(trim($input['currency'] ?? 'PHP'), 0, 8));
$date          = trim($input['transaction_date'] ?? '');
$description   = trim($input['description'] ?? '') ?: null;
$priority      = in_array($input['priority'] ?? '', ['high', 'mid', 'low'], true) ? $input['priority'] : 'mid';
$paymentMethod = trim($input['payment_method'] ?? '') ?: null;
$tags          = trim($input['tags'] ?? '') ?: null;

if (!in_array($type, ['income', 'expense'], true) || $category === '' || $amount < 0 || $date === '') {
    http_response_code(400);
    echo json_encode(['error' => 'type (income/expense), category, non-negative amount, transaction_date required']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'transaction_date must be YYYY-MM-DD']);
    exit;
}

$adminId = (int) $admin['id'];

$stmt = mysqli_prepare(
    $conn,
    'INSERT INTO finance_transactions
       (type, category, subcategory, amount, currency, transaction_date,
        description, priority, payment_method, tags, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
mysqli_stmt_bind_param(
    $stmt, 'sssdssssssi',
    $type, $category, $subcategory, $amount, $currency, $date,
    $description, $priority, $paymentMethod, $tags, $adminId
);
$ok    = mysqli_stmt_execute($stmt);
$newId = (int) mysqli_stmt_insert_id($stmt);
mysqli_stmt_close($stmt);

if (!$ok) { http_response_code(500); echo json_encode(['error' => 'Insert failed: ' . mysqli_error($conn)]); exit; }

$stmt = mysqli_prepare($conn, 'SELECT * FROM finance_transactions WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $newId);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$row['id']     = (int)   $row['id'];
$row['amount'] = (float) $row['amount'];

echo json_encode(['success' => true, 'transaction' => $row]);
