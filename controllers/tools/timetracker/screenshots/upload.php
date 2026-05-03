<?php
/**
 * Upload a screenshot for a session.
 * POST multipart: file field "screenshot",
 *                 form fields: session_id, taken_at (Y-m-d H:i:s, optional, defaults NOW),
 *                              activity_level (0-100, optional)
 * -> { success, screenshot_id, image_path }
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

$sessionId = (int) ($_POST['session_id'] ?? 0);
if ($sessionId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'session_id required']);
    exit;
}

if (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'screenshot file required']);
    exit;
}

// Look up session -> developer
$stmt = mysqli_prepare($conn, 'SELECT developer_id FROM time_sessions WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $sessionId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);
if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Session not found']);
    exit;
}
$developerId = (int) $row['developer_id'];

$takenAt = isset($_POST['taken_at']) && trim($_POST['taken_at']) !== ''
    ? trim($_POST['taken_at'])
    : date('Y-m-d H:i:s');
$activityLevel = isset($_POST['activity_level']) && $_POST['activity_level'] !== ''
    ? max(0, min(255, (int) $_POST['activity_level']))
    : null;

$ext = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) $ext = 'png';

$dateFolder = date('Y-m-d', strtotime($takenAt));
$relDir = "uploads/timetracker/{$developerId}/{$dateFolder}";
$absDir = __DIR__ . '/../../../../' . $relDir;
if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create upload directory']);
    exit;
}

$filename = uniqid('shot_', true) . '.' . $ext;
$absPath = $absDir . '/' . $filename;
$relPath = $relDir . '/' . $filename;

if (!move_uploaded_file($_FILES['screenshot']['tmp_name'], $absPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save uploaded file']);
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    'INSERT INTO time_screenshots (session_id, developer_id, taken_at, image_path, activity_level) VALUES (?, ?, ?, ?, ?)'
);
mysqli_stmt_bind_param($stmt, 'iissi', $sessionId, $developerId, $takenAt, $relPath, $activityLevel);
if (!mysqli_stmt_execute($stmt)) {
    @unlink($absPath);
    http_response_code(500);
    echo json_encode(['error' => 'DB insert failed: ' . mysqli_error($conn)]);
    exit;
}
$shotId = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

echo json_encode([
    'success' => true,
    'screenshot_id' => $shotId,
    'image_path' => $relPath,
]);
