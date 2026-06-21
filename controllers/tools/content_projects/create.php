<?php
/**
 * Create a project.
 * POST { user_email, name, project_json: { canvases: [...] } }
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { project_jsonResponse(['error' => 'Method not allowed'], 405); exit; }
if (!$conn) { project_jsonResponse(['error' => 'Database connection failed'], 500); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];

$userEmail   = trim($input['user_email'] ?? '');
$name        = trim($input['name'] ?? '');
if ($name === '') $name = 'Untitled project';
$projectJson = $input['project_json'] ?? null;

if ($userEmail === '' || !is_array($projectJson)) {
    project_jsonResponse(['error' => 'user_email and project_json required'], 400);
    exit;
}

$jsonStr = json_encode($projectJson);

$stmt = mysqli_prepare($conn, 'INSERT INTO content_projects (user_email, name, project_json) VALUES (?, ?, ?)');
mysqli_stmt_bind_param($stmt, 'sss', $userEmail, $name, $jsonStr);
$ok = mysqli_stmt_execute($stmt);
$id = (int) mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

if (!$ok) { project_jsonResponse(['error' => 'Insert failed'], 500); exit; }

project_jsonResponse(['success' => true, 'id' => $id]);
