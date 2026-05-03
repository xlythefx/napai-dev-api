<?php
/**
 * Delete (undo) a payment record.
 * POST { requester_email, payment_id } -> { success }
 */

require_once __DIR__ . '/../../../../config/cors.php';
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../lib/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
require_admin($conn, $input['requester_email'] ?? '');

$paymentId = (int) ($input['payment_id'] ?? 0);
if ($paymentId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'payment_id required']);
    exit;
}

// Pull proof_path so we can clean up the file
$stmt = mysqli_prepare($conn, 'SELECT proof_path FROM developer_payments WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $paymentId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);
if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Payment not found']);
    exit;
}

$stmt = mysqli_prepare($conn, 'DELETE FROM developer_payments WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $paymentId);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
if (!$ok) {
    http_response_code(500);
    echo json_encode(['error' => 'Delete failed']);
    exit;
}

if (!empty($row['proof_path'])) {
    $abs = __DIR__ . '/../../../../' . $row['proof_path'];
    if (is_file($abs)) @unlink($abs);
}

// Remove linked finance_transaction if it was auto-logged
$finStmt = mysqli_prepare(
    $conn,
    'DELETE FROM finance_transactions WHERE reference_type = "developer_payment" AND reference_id = ?'
);
mysqli_stmt_bind_param($finStmt, 'i', $paymentId);
mysqli_stmt_execute($finStmt);
mysqli_stmt_close($finStmt);

echo json_encode(['success' => true]);
