<?php
/**
 * Create a chat session (tab) for a project.
 * POST { user_email, project_type, project_id, title? }
 * -> { success, id, title }
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { chat_jsonResponse(['error' => 'Method not allowed'], 405); exit; }
if (!$conn) { chat_jsonResponse(['error' => 'Database connection failed'], 500); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];

$userEmail   = trim($input['user_email'] ?? '');
$projectType = chat_validType($input['project_type'] ?? '');
$projectId   = trim($input['project_id'] ?? '');
$title       = trim($input['title'] ?? '');
if ($title === '') $title = 'Chat 1';

if ($userEmail === '' || $projectType === '' || $projectId === '') {
    chat_jsonResponse(['error' => 'user_email, project_type and project_id required'], 400);
    exit;
}

$empty = '[]';
$stmt = mysqli_prepare(
    $conn,
    'INSERT INTO content_chats (user_email, project_type, project_id, title, messages_json)
     VALUES (?, ?, ?, ?, ?)'
);
mysqli_stmt_bind_param($stmt, 'sssss', $userEmail, $projectType, $projectId, $title, $empty);
$ok = mysqli_stmt_execute($stmt);
$id = (int) mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

if (!$ok) { chat_jsonResponse(['error' => 'Insert failed'], 500); exit; }

chat_jsonResponse(['success' => true, 'id' => $id, 'title' => $title]);
