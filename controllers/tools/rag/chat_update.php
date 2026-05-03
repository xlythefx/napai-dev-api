<?php
/**
 * Update chat name or documents
 * PATCH body: { chat_id, user_email, name?, document_ids? }
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
if ($_SERVER['REQUEST_METHOD'] !== 'PATCH' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
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
$name = isset($input['name']) ? trim($input['name']) : null;
$documentIds = $input['document_ids'] ?? null;

if ($chatId <= 0 || $userEmail === '') {
    rag_jsonResponse(['error' => 'chat_id and user_email required'], 400);
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT id FROM chats WHERE id = ? AND user_email = ?');
mysqli_stmt_bind_param($stmt, 'is', $chatId, $userEmail);
mysqli_stmt_execute($stmt);
if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
    mysqli_stmt_close($stmt);
    rag_jsonResponse(['error' => 'Chat not found'], 404);
    exit;
}
mysqli_stmt_close($stmt);

if ($name !== null && $name !== '') {
    $stmt = mysqli_prepare($conn, 'UPDATE chats SET name = ? WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'si', $name, $chatId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

if ($documentIds !== null && is_array($documentIds)) {
    mysqli_query($conn, "DELETE FROM chat_documents WHERE chat_id = $chatId");
    $stmt = mysqli_prepare($conn, 'INSERT INTO chat_documents (chat_id, document_id) VALUES (?, ?)');
    foreach ($documentIds as $docId) {
        $d = (int) $docId;
        if ($d > 0) {
            mysqli_stmt_bind_param($stmt, 'ii', $chatId, $d);
            mysqli_stmt_execute($stmt);
        }
    }
    mysqli_stmt_close($stmt);
}

rag_jsonResponse(['success' => true]);
