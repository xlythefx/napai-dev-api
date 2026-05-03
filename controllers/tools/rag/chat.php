<?php
/**
 * RAG Chat: Question + context -> OpenAI -> response
 * POST body: { "question", "user_email", "messages"?, "chat_id"?, "top_k"? }
 * If chat_id: saves messages to chat_messages, uses chat's documents (or all if empty)
 */

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/openai.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { rag_jsonResponse([]); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { rag_jsonResponse(['error' => 'Method not allowed'], 405); exit; }
if (!$conn) { rag_jsonResponse(['error' => 'Database connection failed'], 500); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$question = trim($input['question'] ?? '');
$userEmail = trim($input['user_email'] ?? '');
$history = $input['messages'] ?? [];
$chatId = (int) ($input['chat_id'] ?? 0);
$topK = (int) ($input['top_k'] ?? 5);

if ($question === '' || $userEmail === '') {
    rag_jsonResponse(['error' => 'Missing question or user_email'], 400);
    exit;
}

try {
    $queryEmbedding = rag_openaiEmbed($question);
    if ($chatId > 0) {
        $stmt = mysqli_prepare($conn, 'SELECT c.chunk_text, c.embedding FROM chunks c JOIN documents d ON c.document_id = d.id WHERE d.user_email = ? AND d.chat_id = ?');
        mysqli_stmt_bind_param($stmt, 'si', $userEmail, $chatId);
    } else {
        $stmt = mysqli_prepare($conn, 'SELECT c.chunk_text, c.embedding FROM chunks c JOIN documents d ON c.document_id = d.id WHERE d.user_email = ? AND d.chat_id IS NULL');
        mysqli_stmt_bind_param($stmt, 's', $userEmail);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $context = '';
    if (!empty($rows)) {
        $scored = [];
        foreach ($rows as $row) {
            $embedding = json_decode($row['embedding'], true) ?: [];
            $score = rag_cosineSimilarity($queryEmbedding, $embedding);
            $scored[] = ['text' => $row['chunk_text'], 'score' => $score];
        }
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($scored, 0, $topK);
        $context = implode("\n\n---\n\n", array_map(fn($s) => $s['text'], $top));
    }

    $systemContent = $context !== ''
        ? "You are a helpful assistant. Answer the user's question based on the following context from their documents. If the context doesn't contain relevant information, say so politely.\n\nContext:\n" . $context
        : "You are a helpful assistant. The user has not uploaded any documents yet, so answer from your general knowledge.";

    $messages = [['role' => 'system', 'content' => $systemContent]];
    foreach ($history as $m) {
        $role = $m['role'] ?? 'user';
        $content = $m['content'] ?? '';
        if (in_array($role, ['user', 'assistant']) && $content !== '') {
            $messages[] = ['role' => $role, 'content' => $content];
        }
    }
    $messages[] = ['role' => 'user', 'content' => $question];

    $response = rag_openaiChat($messages);

    if ($chatId > 0) {
        $role = 'user';
        $stmt = mysqli_prepare($conn, 'INSERT INTO chat_messages (chat_id, role, content) VALUES (?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'iss', $chatId, $role, $question);
        mysqli_stmt_execute($stmt);
        $role = 'assistant';
        mysqli_stmt_bind_param($stmt, 'iss', $chatId, $role, $response);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_query($conn, "UPDATE chats SET updated_at = NOW() WHERE id = " . (int) $chatId);
    }

    rag_jsonResponse(['response' => $response]);
} catch (Exception $e) {
    rag_jsonResponse(['error' => $e->getMessage()], 500);
}
