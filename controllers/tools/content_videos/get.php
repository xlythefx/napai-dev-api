<?php
/**
 * Get one video (owner only) with full scenes.
 * GET ?id=1&user_email=...
 * -> { video: {id, name, fps, scenes, created_at, updated_at} }
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
if (!$conn) { video_jsonResponse(['error' => 'Database connection failed'], 500); exit; }

$id        = (int) ($_GET['id'] ?? 0);
$userEmail = trim($_GET['user_email'] ?? '');
if ($id <= 0 || $userEmail === '') {
    video_jsonResponse(['error' => 'id and user_email required'], 400);
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    'SELECT id, name, video_json, created_at, updated_at
     FROM content_videos WHERE id = ? AND user_email = ?'
);
mysqli_stmt_bind_param($stmt, 'is', $id, $userEmail);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) { video_jsonResponse(['error' => 'Video not found'], 404); exit; }

// Pass the full stored project JSON through (schemaVersion, fps, width, height,
// scenes, templates, assets, …) so AI-written templates and dimensions survive a
// reload. id/name/timestamps come from the row columns.
$vid = json_decode($row['video_json'], true);
if (!is_array($vid)) $vid = [];
$vid['id']         = (int) $row['id'];
$vid['name']       = $row['name'];
$vid['created_at'] = $row['created_at'];
$vid['updated_at'] = $row['updated_at'];

video_jsonResponse(['video' => $vid]);
