<?php
/**
 * Delete chat
 * DELETE body: { chat_id, user_email }
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
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    rag_jsonResponse(['error' => 'Method not allowed'], 405);
    exit;
}

if (!$conn) {
    rag_jsonResponse(['error' => 'Database connection failed'], 500);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$chatId = (int) ($input['chat_id'] ?? 0);
$userEmail = trim($input['user_email'] ?? '');

if ($chatId <= 0 || $userEmail === '') {
    rag_jsonResponse(['error' => 'chat_id and user_email required'], 400);
    exit;
}

$stmt = mysqli_prepare($conn, 'DELETE FROM chats WHERE id = ? AND user_email = ?');
mysqli_stmt_bind_param($stmt, 'is', $chatId, $userEmail);
mysqli_stmt_execute($stmt);
$deleted = mysqli_affected_rows($conn) > 0;
mysqli_stmt_close($stmt);

rag_jsonResponse(['success' => true, 'deleted' => $deleted]);
