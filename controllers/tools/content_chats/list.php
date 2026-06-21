<?php
/**
 * List a project's chat sessions (tabs), newest-updated first.
 * GET ?user_email=...&project_type=video&project_id=123
 * -> { chats: [{id, title, claude_session_id, created_at, updated_at}] }
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
if (!$conn) { chat_jsonResponse(['error' => 'Database connection failed'], 500); exit; }

$userEmail   = trim($_GET['user_email'] ?? '');
$projectType = chat_validType($_GET['project_type'] ?? '');
$projectId   = trim($_GET['project_id'] ?? '');

if ($userEmail === '' || $projectType === '' || $projectId === '') {
    chat_jsonResponse(['error' => 'user_email, project_type and project_id required'], 400);
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    'SELECT id, title, claude_session_id, created_at, updated_at
     FROM content_chats
     WHERE user_email = ? AND project_type = ? AND project_id = ?
     ORDER BY updated_at ASC'
);
mysqli_stmt_bind_param($stmt, 'sss', $userEmail, $projectType, $projectId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$chats = [];
while ($row = mysqli_fetch_assoc($res)) {
    $chats[] = [
        'id'                => (int) $row['id'],
        'title'             => $row['title'],
        'claude_session_id' => $row['claude_session_id'],
        'created_at'        => $row['created_at'],
        'updated_at'        => $row['updated_at'],
    ];
}
mysqli_stmt_close($stmt);

chat_jsonResponse(['chats' => $chats]);
