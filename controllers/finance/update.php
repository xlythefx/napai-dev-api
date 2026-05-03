<?php
/**
 * Update a finance transaction.
 * POST { requester_email, id, type?, category?, subcategory?, amount?,
 *         currency?, transaction_date?, description?, priority?,
 *         payment_method?, tags? }
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
require_admin($conn, $input['requester_email'] ?? '');

$id = (int) ($input['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'id required']); exit; }

// Fetch existing row
$stmt = mysqli_prepare($conn, 'SELECT * FROM finance_transactions WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$row) { http_response_code(404); echo json_encode(['error' => 'Transaction not found']); exit; }

// Apply updates
$type          = in_array($input['type'] ?? '', ['income','expense'], true) ? $input['type'] : $row['type'];
$category      = isset($input['category'])       ? trim($input['category'])       : $row['category'];
$subcategory   = array_key_exists('subcategory', $input) ? (trim($input['subcategory']) ?: null) : $row['subcategory'];
$amount        = isset($input['amount'])         ? (float) $input['amount']       : (float) $row['amount'];
$currency      = isset($input['currency'])       ? strtoupper(substr(trim($input['currency']),0,8)) : $row['currency'];
$date          = isset($input['transaction_date']) ? trim($input['transaction_date']) : $row['transaction_date'];
$description   = array_key_exists('description', $input) ? (trim($input['description']) ?: null) : $row['description'];
$priority      = in_array($input['priority'] ?? '', ['high','mid','low'], true) ? $input['priority'] : $row['priority'];
$paymentMethod = array_key_exists('payment_method', $input) ? (trim($input['payment_method']) ?: null) : $row['payment_method'];
$tags          = array_key_exists('tags', $input) ? (trim($input['tags']) ?: null) : $row['tags'];

if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'transaction_date must be YYYY-MM-DD']);
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    'UPDATE finance_transactions
     SET type=?, category=?, subcategory=?, amount=?, currency=?, transaction_date=?,
         description=?, priority=?, payment_method=?, tags=?
     WHERE id=?'
);
mysqli_stmt_bind_param(
    $stmt, 'sssdssssssi',
    $type, $category, $subcategory, $amount, $currency, $date,
    $description, $priority, $paymentMethod, $tags, $id
);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
if (!$ok) { http_response_code(500); echo json_encode(['error' => 'Update failed']); exit; }

$stmt = mysqli_prepare($conn, 'SELECT * FROM finance_transactions WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$row['id']     = (int)   $row['id'];
$row['amount'] = (float) $row['amount'];

echo json_encode(['success' => true, 'transaction' => $row]);
