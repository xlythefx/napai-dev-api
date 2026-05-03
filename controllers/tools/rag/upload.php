<?php
/**
 * RAG Upload: Receive document text, chunk, embed, store in MySQL
 * POST body: { "text", "filename", "user_email", "chat_id" }
 * Each document is stored for a specific chat (unique KB per chat)
 */

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/openai.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { rag_jsonResponse([]); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { rag_jsonResponse(['error' => 'Method not allowed'], 405); exit; }
if (!$conn) { rag_jsonResponse(['error' => 'Database connection failed'], 500); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$text = trim($input['text'] ?? '');
$filename = trim($input['filename'] ?? 'document.txt');
$userEmail = trim($input['user_email'] ?? '');
$chatId = (int) ($input['chat_id'] ?? 0);

if ($text === '' || $userEmail === '' || $chatId <= 0) {
    rag_jsonResponse(['error' => 'Missing text, user_email, or chat_id'], 400);
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

$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$fileType = in_array($ext, ['txt', 'pdf']) ? $ext : 'txt';

try {
    $chunks = rag_chunkText($text);
    if (empty($chunks)) {
        rag_jsonResponse(['error' => 'No text extracted from document'], 400);
        exit;
    }

    $embeddings = rag_openaiEmbedBatch($chunks);

    $stmt = mysqli_prepare($conn, 'INSERT INTO documents (user_email, chat_id, file_name, file_type) VALUES (?, ?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'siss', $userEmail, $chatId, $filename, $fileType);
    mysqli_stmt_execute($stmt);
    $documentId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, 'INSERT INTO chunks (document_id, chunk_index, chunk_text, embedding) VALUES (?, ?, ?, ?)');
    foreach ($chunks as $i => $chunk) {
        $embedding = json_encode($embeddings[$i] ?? []);
        mysqli_stmt_bind_param($stmt, 'iiss', $documentId, $i, $chunk, $embedding);
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);

    rag_jsonResponse([
        'success' => true,
        'document_id' => $documentId,
        'chunks_count' => count($chunks),
    ]);
} catch (Exception $e) {
    rag_jsonResponse(['error' => $e->getMessage()], 500);
}
