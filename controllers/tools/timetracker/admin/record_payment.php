<?php
/**
 * Record a payment for a developer for a specific day.
 * Multipart POST (preferred when proof file is attached):
 *   form fields: requester_email, developer_id, payment_date (YYYY-MM-DD),
 *                amount, currency?, note?
 *   file field "proof" (optional): jpg/png/pdf
 * OR JSON POST: { requester_email, developer_id, payment_date, amount, currency?, note? }
 *
 * Upserts on (developer_id, payment_date). -> { success, payment: {...} }
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

$isMultipart = isset($_SERVER['CONTENT_TYPE'])
    && stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false;

$input = $isMultipart ? $_POST : (json_decode(file_get_contents('php://input'), true) ?: []);
$admin = require_admin($conn, $input['requester_email'] ?? '');

$developerId = (int) ($input['developer_id'] ?? 0);
$paymentDate = isset($input['payment_date']) ? trim((string) $input['payment_date']) : '';
$amount = isset($input['amount']) ? (float) $input['amount'] : -1;
$currency = isset($input['currency']) && trim($input['currency']) !== ''
    ? strtoupper(substr(trim($input['currency']), 0, 8)) : 'PHP';
$note = isset($input['note']) && trim($input['note']) !== ''
    ? substr(trim($input['note']), 0, 500) : null;

if ($developerId <= 0 || $paymentDate === '' || $amount < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'developer_id, payment_date, non-negative amount required']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
    http_response_code(400);
    echo json_encode(['error' => 'payment_date must be YYYY-MM-DD']);
    exit;
}

// Compute active seconds for that day from valid sessions
$stmt = mysqli_prepare(
    $conn,
    'SELECT COALESCE(SUM(active_seconds),0) AS s FROM time_sessions
     WHERE developer_id = ? AND DATE(started_at) = ? AND is_valid = 1'
);
mysqli_stmt_bind_param($stmt, 'is', $developerId, $paymentDate);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$activeRow = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);
$activeSeconds = (int) ($activeRow['s'] ?? 0);

// Optional proof file
$proofPath = null;
if ($isMultipart && isset($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'pdf'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'proof must be png/jpg/webp/pdf']);
        exit;
    }
    $relDir = "uploads/timetracker/payments/{$developerId}";
    $absDir = __DIR__ . '/../../../../' . $relDir;
    if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create upload directory']);
        exit;
    }
    $filename = uniqid('proof_', true) . '.' . $ext;
    $absPath = $absDir . '/' . $filename;
    if (!move_uploaded_file($_FILES['proof']['tmp_name'], $absPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save proof']);
        exit;
    }
    $proofPath = $relDir . '/' . $filename;
}

$adminId = (int) $admin['id'];

// Upsert
$sql = '
    INSERT INTO developer_payments
      (developer_id, payment_date, amount, currency, active_seconds, proof_path, note, paid_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      amount = VALUES(amount),
      currency = VALUES(currency),
      active_seconds = VALUES(active_seconds),
      proof_path = COALESCE(VALUES(proof_path), proof_path),
      note = VALUES(note),
      paid_by = VALUES(paid_by),
      paid_at = CURRENT_TIMESTAMP
';
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param(
    $stmt,
    'isdsissi',
    $developerId,
    $paymentDate,
    $amount,
    $currency,
    $activeSeconds,
    $proofPath,
    $note,
    $adminId
);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
if (!$ok) {
    http_response_code(500);
    echo json_encode(['error' => 'Insert failed: ' . mysqli_error($conn)]);
    exit;
}

// Fetch the row
$stmt = mysqli_prepare(
    $conn,
    'SELECT id, developer_id, payment_date, amount, currency, active_seconds,
            proof_path, note, paid_by, paid_at
     FROM developer_payments WHERE developer_id = ? AND payment_date = ?'
);
mysqli_stmt_bind_param($stmt, 'is', $developerId, $paymentDate);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

// Auto-log to finance_transactions (upsert via unique reference key)
$paymentId = (int) $row['id'];
$devStmt = mysqli_prepare($conn, 'SELECT name, email FROM developers WHERE id = ?');
mysqli_stmt_bind_param($devStmt, 'i', $developerId);
mysqli_stmt_execute($devStmt);
$devRow   = mysqli_fetch_assoc(mysqli_stmt_get_result($devStmt));
mysqli_stmt_close($devStmt);
$devLabel = ($devRow['name'] ?? '') ?: ($devRow['email'] ?? 'Developer');
$finDesc  = "Developer salary: {$devLabel} for {$paymentDate}";
$refType  = 'developer_payment';
$finStmt  = mysqli_prepare(
    $conn,
    'INSERT INTO finance_transactions
       (type, category, amount, currency, transaction_date, description,
        priority, reference_id, reference_type, created_by)
     VALUES ("expense","developer_salary",?,?,?,?,"high",?,?,?)
     ON DUPLICATE KEY UPDATE
       amount=VALUES(amount), currency=VALUES(currency),
       transaction_date=VALUES(transaction_date), description=VALUES(description)'
);
mysqli_stmt_bind_param($finStmt, 'dsssiis', $amount, $currency, $paymentDate, $finDesc, $paymentId, $refType, $adminId);
mysqli_stmt_execute($finStmt);
mysqli_stmt_close($finStmt);

echo json_encode([
    'success' => true,
    'payment' => [
        'id' => (int) $row['id'],
        'developer_id' => (int) $row['developer_id'],
        'payment_date' => $row['payment_date'],
        'amount' => (float) $row['amount'],
        'currency' => $row['currency'],
        'active_seconds' => (int) $row['active_seconds'],
        'proof_path' => $row['proof_path'],
        'note' => $row['note'],
        'paid_at' => $row['paid_at'],
    ],
]);
