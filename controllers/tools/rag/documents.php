<?php
/**
 * RAG Documents: List or delete user documents
 * GET ?user_email=... -> list documents
 * DELETE body: { "user_email": "...", "document_id": 1 }
 */

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/utils.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    rag_jsonResponse([]);
    exit;
}

if (!$conn) {
    rag_jsonResponse(['error' => 'Database connection failed'], 500);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userEmail = trim($_GET['user_email'] ?? '');
    $chatId = (int) ($_GET['chat_id'] ?? 0);
    if ($userEmail === '' || $chatId <= 0) {
        rag_jsonResponse(['error' => 'Missing user_email or chat_id'], 400);
        exit;
    }
    $stmt = mysqli_prepare($conn, '
        SELECT d.id, d.file_name, d.file_type, d.created_at,
               (SELECT COUNT(*) FROM chunks WHERE document_id = d.id) AS chunks_count
        FROM documents d
        WHERE d.user_email = ? AND d.chat_id = ?
        ORDER BY d.created_at DESC
    ');
    mysqli_stmt_bind_param($stmt, 'si', $userEmail, $chatId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $docs = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    rag_jsonResponse(['documents' => $docs]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $userEmail = trim($input['user_email'] ?? '');
    $documentId = (int) ($input['document_id'] ?? 0);
    if ($userEmail === '' || $documentId <= 0) {
        rag_jsonResponse(['error' => 'Missing user_email or document_id'], 400);
        exit;
    }
    $stmt = mysqli_prepare($conn, 'DELETE FROM documents WHERE id = ? AND user_email = ?');
    mysqli_stmt_bind_param($stmt, 'is', $documentId, $userEmail);
    mysqli_stmt_execute($stmt);
    $deleted = mysqli_affected_rows($conn) > 0;
    mysqli_stmt_close($stmt);
    rag_jsonResponse(['success' => true, 'deleted' => $deleted]);
    exit;
}

rag_jsonResponse(['error' => 'Method not allowed'], 405);
