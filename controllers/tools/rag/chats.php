<?php
/**
 * Chats CRUD: list, create, get, update, delete
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

$userEmail = trim($_GET['user_email'] ?? $_POST['user_email'] ?? '');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
if ($userEmail === '') $userEmail = trim($input['user_email'] ?? '');

if ($userEmail === '') {
    rag_jsonResponse(['error' => 'user_email required'], 400);
    exit;
}

// LIST
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = mysqli_prepare($conn, 'SELECT id, name, created_at, updated_at FROM chats WHERE user_email = ? ORDER BY updated_at DESC');
    mysqli_stmt_bind_param($stmt, 's', $userEmail);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $chats = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $chats[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }
    mysqli_stmt_close($stmt);
    rag_jsonResponse(['chats' => $chats]);
    exit;
}

// CREATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($input['name'] ?? 'New Chat');
    if ($name === '') $name = 'New Chat';
    $stmt = mysqli_prepare($conn, 'INSERT INTO chats (user_email, name) VALUES (?, ?)');
    mysqli_stmt_bind_param($stmt, 'ss', $userEmail, $name);
    mysqli_stmt_execute($stmt);
    $id = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    rag_jsonResponse(['success' => true, 'chat' => ['id' => $id, 'name' => $name]]);
    exit;
}

rag_jsonResponse(['error' => 'Method not allowed'], 405);
