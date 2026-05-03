<?php
/**
 * Get single chat with messages and document ids
 * GET ?chat_id=1&user_email=...
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

if (!$conn) {
    rag_jsonResponse(['error' => 'Database connection failed'], 500);
    exit;
}

$chatId = (int) ($_GET['chat_id'] ?? 0);
$userEmail = trim($_GET['user_email'] ?? '');

if ($chatId <= 0 || $userEmail === '') {
    rag_jsonResponse(['error' => 'chat_id and user_email required'], 400);
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT id, name, created_at FROM chats WHERE id = ? AND user_email = ?');
mysqli_stmt_bind_param($stmt, 'is', $chatId, $userEmail);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$chat = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$chat) {
    rag_jsonResponse(['error' => 'Chat not found'], 404);
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT role, content FROM chat_messages WHERE chat_id = ? ORDER BY created_at ASC');
mysqli_stmt_bind_param($stmt, 'i', $chatId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$messages = [];
while ($row = mysqli_fetch_assoc($res)) {
    $messages[] = ['role' => $row['role'], 'content' => $row['content']];
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, 'SELECT id FROM documents WHERE chat_id = ? AND user_email = ?');
mysqli_stmt_bind_param($stmt, 'is', $chatId, $userEmail);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$documentIds = [];
while ($row = mysqli_fetch_assoc($res)) {
    $documentIds[] = (int) $row['id'];
}
mysqli_stmt_close($stmt);

rag_jsonResponse([
    'chat' => [
        'id' => (int) $chat['id'],
        'name' => $chat['name'],
        'created_at' => $chat['created_at'],
    ],
    'messages' => $messages,
    'document_ids' => $documentIds,
]);
