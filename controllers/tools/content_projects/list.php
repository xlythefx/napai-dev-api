<?php
/**
 * List a user's projects (metadata + light preview, no full JSON / image data).
 * GET ?user_email=...
 * -> { projects: [{id, name, canvasCount, updated_at, preview}] }
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
if (!$conn) { project_jsonResponse(['error' => 'Database connection failed'], 500); exit; }

$userEmail = trim($_GET['user_email'] ?? '');
if ($userEmail === '') { project_jsonResponse(['error' => 'user_email required'], 400); exit; }

$stmt = mysqli_prepare(
    $conn,
    'SELECT id, name, project_json, created_at, updated_at
     FROM content_projects WHERE user_email = ? ORDER BY updated_at DESC'
);
mysqli_stmt_bind_param($stmt, 's', $userEmail);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$projects = [];
while ($row = mysqli_fetch_assoc($res)) {
    $proj     = json_decode($row['project_json'], true);
    $canvases = is_array($proj['canvases'] ?? null) ? $proj['canvases'] : [];
    $projects[] = [
        'id'          => (int) $row['id'],
        'name'        => $row['name'],
        'canvasCount' => count($canvases),
        'created_at'  => $row['created_at'],
        'updated_at'  => $row['updated_at'],
        'preview'     => project_buildPreview($proj),
    ];
}
mysqli_stmt_close($stmt);

project_jsonResponse(['projects' => $projects]);
