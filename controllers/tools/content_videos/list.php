<?php
/**
 * List a user's videos (metadata + light preview).
 * GET ?user_email=...
 * -> { videos: [{id, name, sceneCount, updated_at, preview}] }
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

$userEmail = trim($_GET['user_email'] ?? '');
if ($userEmail === '') { video_jsonResponse(['error' => 'user_email required'], 400); exit; }

$stmt = mysqli_prepare(
    $conn,
    'SELECT id, name, video_json, created_at, updated_at
     FROM content_videos WHERE user_email = ? ORDER BY updated_at DESC'
);
mysqli_stmt_bind_param($stmt, 's', $userEmail);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$videos = [];
while ($row = mysqli_fetch_assoc($res)) {
    $vid    = json_decode($row['video_json'], true);
    $scenes = is_array($vid['scenes'] ?? null) ? $vid['scenes'] : [];
    $videos[] = [
        'id'         => (int) $row['id'],
        'name'       => $row['name'],
        'sceneCount' => count($scenes),
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
        'preview'    => video_buildPreview($vid),
    ];
}
mysqli_stmt_close($stmt);

video_jsonResponse(['videos' => $videos]);
