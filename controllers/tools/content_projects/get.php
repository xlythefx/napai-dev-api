<?php
/**
 * Get one project (owner only) with full canvases.
 * GET ?id=1&user_email=...
 * -> { project: {id, name, canvases, created_at, updated_at} }
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

$id        = (int) ($_GET['id'] ?? 0);
$userEmail = trim($_GET['user_email'] ?? '');
if ($id <= 0 || $userEmail === '') {
    project_jsonResponse(['error' => 'id and user_email required'], 400);
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    'SELECT id, name, project_json, created_at, updated_at
     FROM content_projects WHERE id = ? AND user_email = ?'
);
mysqli_stmt_bind_param($stmt, 'is', $id, $userEmail);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) { project_jsonResponse(['error' => 'Project not found'], 404); exit; }

$proj     = json_decode($row['project_json'], true);
$canvases = is_array($proj['canvases'] ?? null) ? $proj['canvases'] : [];

project_jsonResponse([
    'project' => [
        'id'         => (int) $row['id'],
        'name'       => $row['name'],
        'canvases'   => $canvases,
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
    ],
]);
