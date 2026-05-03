<?php
/**
 * RAG Query: Get relevant chunks for a question
 * POST body: { "question": "...", "user_email": "user@example.com", "top_k": 5 }
 */

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/openai.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    rag_jsonResponse([]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rag_jsonResponse(['error' => 'Method not allowed'], 405);
    exit;
}

if (!$conn) {
    rag_jsonResponse(['error' => 'Database connection failed'], 500);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$question = trim($input['question'] ?? '');
$userEmail = trim($input['user_email'] ?? '');
$topK = (int) ($input['top_k'] ?? 5);

if ($question === '' || $userEmail === '') {
    rag_jsonResponse(['error' => 'Missing question or user_email'], 400);
    exit;
}

try {
    $queryEmbedding = rag_openaiEmbed($question);

    $stmt = mysqli_prepare($conn, '
        SELECT c.id, c.chunk_text, c.embedding
        FROM chunks c
        JOIN documents d ON c.document_id = d.id
        WHERE d.user_email = ?
    ');
    mysqli_stmt_bind_param($stmt, 's', $userEmail);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    if (empty($rows)) {
        rag_jsonResponse(['chunks' => []]);
        exit;
    }

    $scored = [];
    foreach ($rows as $row) {
        $embedding = json_decode($row['embedding'], true) ?: [];
        $score = rag_cosineSimilarity($queryEmbedding, $embedding);
        $scored[] = ['text' => $row['chunk_text'], 'score' => $score];
    }
    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
    $top = array_slice($scored, 0, $topK);

    rag_jsonResponse(['chunks' => array_map(fn($s) => $s['text'], $top)]);
} catch (Exception $e) {
    rag_jsonResponse(['error' => $e->getMessage()], 500);
}
