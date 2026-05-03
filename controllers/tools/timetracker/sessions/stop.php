<?php
/**
 * Stop a time tracking session.
 * POST { session_id, active_seconds?, idle_seconds? } -> { success }
 */

require_once __DIR__ . '/../../../../config/cors.php';
require_once __DIR__ . '/../../../../config/db.php';

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
$sessionId = (int) ($input['session_id'] ?? 0);
if ($sessionId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'session_id required']);
    exit;
}

$hasActive = isset($input['active_seconds']);
$hasIdle = isset($input['idle_seconds']);

if ($hasActive || $hasIdle) {
    $active = (int) ($input['active_seconds'] ?? 0);
    $idle = (int) ($input['idle_seconds'] ?? 0);
    $stmt = mysqli_prepare(
        $conn,
        'UPDATE time_sessions SET ended_at = NOW(), active_seconds = ?, idle_seconds = ? WHERE id = ?'
    );
    mysqli_stmt_bind_param($stmt, 'iii', $active, $idle, $sessionId);
} else {
    $stmt = mysqli_prepare(
        $conn,
        'UPDATE time_sessions SET ended_at = NOW() WHERE id = ?'
    );
    mysqli_stmt_bind_param($stmt, 'i', $sessionId);
}

$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
if (!$ok) {
    http_response_code(500);
    echo json_encode(['error' => 'Stop failed']);
    exit;
}
echo json_encode(['success' => true]);
