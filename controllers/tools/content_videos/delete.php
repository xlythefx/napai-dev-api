<?php
/**
 * Delete a video (owner only).
 * POST/DELETE { user_email, id }
 * -> { success, deleted }
 */
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/utils.php';

ob_end_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'], true)) {
    video_jsonResponse(['error' => 'Method not allowed'], 405);
    exit;
}
if (!$conn) { video_jsonResponse(['error' => 'Database connection failed'], 500); exit; }

$input     = json_decode(file_get_contents('php://input'), true) ?: [];
$userEmail = trim($input['user_email'] ?? '');
$id        = (int) ($input['id'] ?? 0);

if ($userEmail === '' || $id <= 0) {
    video_jsonResponse(['error' => 'user_email and id required'], 400);
    exit;
}

$stmt = mysqli_prepare($conn, 'DELETE FROM content_videos WHERE id = ? AND user_email = ?');
mysqli_stmt_bind_param($stmt, 'is', $id, $userEmail);
mysqli_stmt_execute($stmt);
$deleted = mysqli_affected_rows($conn) > 0;
mysqli_stmt_close($stmt);

video_jsonResponse(['success' => true, 'deleted' => $deleted]);
