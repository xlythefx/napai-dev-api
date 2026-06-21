<?php
/**
 * Get one chat session (owner only) with its full transcript.
 * GET ?id=1&user_email=...
 * -> { chat: {id, title, claude_session_id, messages: [{role, content, thinking?}]} }
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

$id        = (int) ($_GET['id'] ?? 0);
$userEmail = trim($_GET['user_email'] ?? '');
if ($id <= 0 || $userEmail === '') {
    chat_jsonResponse(['error' => 'id and user_email required'], 400);
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    'SELECT id, title, claude_session_id, messages_json, created_at, updated_at
     FROM content_chats WHERE id = ? AND user_email = ?'
);
mysqli_stmt_bind_param($stmt, 'is', $id, $userEmail);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) { chat_jsonResponse(['error' => 'Chat not found'], 404); exit; }

$messages = json_decode($row['messages_json'] ?? '[]', true);
if (!is_array($messages)) $messages = [];

chat_jsonResponse(['chat' => [
    'id'                => (int) $row['id'],
    'title'             => $row['title'],
    'claude_session_id' => $row['claude_session_id'],
    'messages'          => $messages,
    'created_at'        => $row['created_at'],
    'updated_at'        => $row['updated_at'],
]]);
