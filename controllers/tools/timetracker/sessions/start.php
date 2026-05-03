<?php
/**
 * Start a time tracking session.
 * POST { developer_email, task? } -> { success, session_id }
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
$dev = require_developer($conn, $input['developer_email'] ?? '');
$task = isset($input['task']) ? trim((string) $input['task']) : null;
if ($task === '') $task = null;
$projectId = isset($input['project_id']) && (int) $input['project_id'] > 0
    ? (int) $input['project_id'] : null;

$stmt = mysqli_prepare(
    $conn,
    'INSERT INTO time_sessions (developer_id, project_id, task, started_at) VALUES (?, ?, ?, NOW())'
);
$devId = (int) $dev['id'];
mysqli_stmt_bind_param($stmt, 'iis', $devId, $projectId, $task);
if (!mysqli_stmt_execute($stmt)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to start session: ' . mysqli_error($conn)]);
    exit;
}
$sessionId = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

echo json_encode(['success' => true, 'session_id' => $sessionId]);
