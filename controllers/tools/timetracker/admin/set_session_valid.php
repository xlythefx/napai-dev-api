<?php
/**
 * Mark a session as valid (1) or invalid (0). Admin-only.
 * POST { requester_email, session_id, is_valid: 0|1, reason? } -> { success }
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

$sessionId = (int) ($input['session_id'] ?? 0);
$isValid = isset($input['is_valid']) ? (int) !!$input['is_valid'] : -1;
$reason = isset($input['reason']) && trim($input['reason']) !== ''
    ? substr(trim($input['reason']), 0, 255) : null;

if ($sessionId <= 0 || ($isValid !== 0 && $isValid !== 1)) {
    http_response_code(400);
    echo json_encode(['error' => 'session_id and is_valid (0|1) required']);
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    'UPDATE time_sessions SET is_valid = ?, invalidated_reason = ? WHERE id = ?'
);
$reasonForDb = $isValid === 0 ? $reason : null;
mysqli_stmt_bind_param($stmt, 'isi', $isValid, $reasonForDb, $sessionId);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
if (!$ok) {
    http_response_code(500);
    echo json_encode(['error' => 'Update failed']);
    exit;
}
echo json_encode(['success' => true]);
