<?php
/**
 * Mark a release as current for its platform (admin).
 * POST { requester_email, id }
 * -> { success }
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

$id = (int) ($input['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'id required']);
    exit;
}

$lookup = mysqli_prepare($conn, 'SELECT platform FROM time_tracker_releases WHERE id = ?');
mysqli_stmt_bind_param($lookup, 'i', $id);
mysqli_stmt_execute($lookup);
$res = mysqli_stmt_get_result($lookup);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($lookup);

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Release not found']);
    exit;
}

mysqli_begin_transaction($conn);
try {
    $clear = mysqli_prepare($conn, 'UPDATE time_tracker_releases SET is_current = 0 WHERE platform = ?');
    mysqli_stmt_bind_param($clear, 's', $row['platform']);
    mysqli_stmt_execute($clear);
    mysqli_stmt_close($clear);

    $set = mysqli_prepare($conn, 'UPDATE time_tracker_releases SET is_current = 1 WHERE id = ?');
    mysqli_stmt_bind_param($set, 'i', $id);
    mysqli_stmt_execute($set);
    mysqli_stmt_close($set);

    mysqli_commit($conn);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
