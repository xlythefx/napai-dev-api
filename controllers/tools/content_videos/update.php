<?php
/**
 * Update a video (owner only). Autosave target.
 * POST { user_email, id, name?, video_json? }
 * -> { success }
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { video_jsonResponse(['error' => 'Method not allowed'], 405); exit; }
if (!$conn) { video_jsonResponse(['error' => 'Database connection failed'], 500); exit; }

$input     = json_decode(file_get_contents('php://input'), true) ?: [];
$userEmail = trim($input['user_email'] ?? '');
$id        = (int) ($input['id'] ?? 0);

if ($userEmail === '' || $id <= 0) {
    video_jsonResponse(['error' => 'user_email and id required'], 400);
    exit;
}

// Verify ownership.
$stmt = mysqli_prepare($conn, 'SELECT id FROM content_videos WHERE id = ? AND user_email = ?');
mysqli_stmt_bind_param($stmt, 'is', $id, $userEmail);
mysqli_stmt_execute($stmt);
$owns = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$owns) { video_jsonResponse(['error' => 'Video not found'], 404); exit; }

$updates = [];
$params  = [];
$types   = '';

if (isset($input['name']) && trim($input['name']) !== '') {
    $updates[] = 'name = ?';
    $params[]  = trim($input['name']);
    $types    .= 's';
}
if (isset($input['video_json']) && is_array($input['video_json'])) {
    $updates[] = 'video_json = ?';
    $params[]  = json_encode($input['video_json']);
    $types    .= 's';
}

if (empty($updates)) { video_jsonResponse(['error' => 'Nothing to update'], 400); exit; }

$params[] = $id;
$types   .= 'i';
$stmt = mysqli_prepare($conn, 'UPDATE content_videos SET ' . implode(', ', $updates) . ' WHERE id = ?');
mysqli_stmt_bind_param($stmt, $types, ...$params);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if (!$ok) { video_jsonResponse(['error' => 'Update failed'], 500); exit; }

video_jsonResponse(['success' => true]);
