<?php
/**
 * Admin - list all uploaded files with chunks
 * GET - returns all documents with their chunks
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['error' => 'Method not allowed']);
    http_response_code(405);
    exit;
}

if (!$conn) {
    echo json_encode(['error' => 'Database connection failed']);
    http_response_code(500);
    exit;
}

$res = mysqli_query($conn, '
    SELECT d.id, d.user_email, d.file_name, d.file_type, d.created_at,
           (SELECT COUNT(*) FROM chunks WHERE document_id = d.id) as chunks_count
    FROM documents d
    ORDER BY d.created_at DESC
');
$documents = [];
while ($row = mysqli_fetch_assoc($res)) {
    $docId = (int) $row['id'];
    $chRes = mysqli_query($conn, "SELECT id, chunk_index, chunk_text FROM chunks WHERE document_id = $docId ORDER BY chunk_index");
    $chunks = [];
    while ($ch = mysqli_fetch_assoc($chRes)) {
        $chunks[] = [
            'id' => (int) $ch['id'],
            'index' => (int) $ch['chunk_index'],
            'text' => mb_substr($ch['chunk_text'], 0, 200) . (mb_strlen($ch['chunk_text']) > 200 ? '...' : ''),
        ];
    }
    $documents[] = [
        'id' => $docId,
        'user_email' => $row['user_email'],
        'file_name' => $row['file_name'],
        'file_type' => $row['file_type'],
        'created_at' => $row['created_at'],
        'chunks_count' => (int) $row['chunks_count'],
        'chunks' => $chunks,
    ];
}

echo json_encode(['documents' => $documents]);
