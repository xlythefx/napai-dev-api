<?php
/**
 * Admin dashboard - stats for files, chunks, tools
 * GET - returns counts and overview
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

$stats = [
    'documents_count' => 0,
    'chunks_count' => 0,
    'developers_count' => 0,
    'users_with_docs' => 0,
];

$r = mysqli_query($conn, 'SELECT COUNT(*) as n FROM documents');
if ($row = mysqli_fetch_assoc($r)) $stats['documents_count'] = (int) $row['n'];

$r = mysqli_query($conn, 'SELECT COUNT(*) as n FROM chunks');
if ($row = mysqli_fetch_assoc($r)) $stats['chunks_count'] = (int) $row['n'];

$r = mysqli_query($conn, 'SELECT COUNT(*) as n FROM developers');
if ($row = mysqli_fetch_assoc($r)) $stats['developers_count'] = (int) $row['n'];

$r = mysqli_query($conn, 'SELECT COUNT(DISTINCT user_email) as n FROM documents');
if ($row = mysqli_fetch_assoc($r)) $stats['users_with_docs'] = (int) $row['n'];

echo json_encode(['stats' => $stats]);
