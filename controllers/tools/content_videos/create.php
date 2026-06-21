<?php
/**
 * Create a video.
 * POST { user_email, name, video_json: { fps, scenes: [...] } }
 * -> { success, id }
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

$input = json_decode(file_get_contents('php://input'), true) ?: [];

$userEmail = trim($input['user_email'] ?? '');
$name      = trim($input['name'] ?? '');
if ($name === '') $name = 'Untitled video';
$videoJson = $input['video_json'] ?? null;

if ($userEmail === '' || !is_array($videoJson)) {
    video_jsonResponse(['error' => 'user_email and video_json required'], 400);
    exit;
}

$jsonStr = json_encode($videoJson);

$stmt = mysqli_prepare($conn, 'INSERT INTO content_videos (user_email, name, video_json) VALUES (?, ?, ?)');
mysqli_stmt_bind_param($stmt, 'sss', $userEmail, $name, $jsonStr);
$ok = mysqli_stmt_execute($stmt);
$id = (int) mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

if (!$ok) { video_jsonResponse(['error' => 'Insert failed'], 500); exit; }

video_jsonResponse(['success' => true, 'id' => $id]);
