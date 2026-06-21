<?php
/**
 * Update a chat session (owner only): rename, persist the resumable session id,
 * and/or save the transcript. Autosave target after each turn.
 * POST { user_email, id, title?, claude_session_id?, messages? }
 * -> { success }
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { chat_jsonResponse(['error' => 'Method not allowed'], 405); exit; }
if (!$conn) { chat_jsonResponse(['error' => 'Database connection failed'], 500); exit; }

$input     = json_decode(file_get_contents('php://input'), true) ?: [];
$userEmail = trim($input['user_email'] ?? '');
$id        = (int) ($input['id'] ?? 0);

if ($userEmail === '' || $id <= 0) {
    chat_jsonResponse(['error' => 'user_email and id required'], 400);
    exit;
}

// Verify ownership.
$stmt = mysqli_prepare($conn, 'SELECT id FROM content_chats WHERE id = ? AND user_email = ?');
mysqli_stmt_bind_param($stmt, 'is', $id, $userEmail);
mysqli_stmt_execute($stmt);
$owns = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$owns) { chat_jsonResponse(['error' => 'Chat not found'], 404); exit; }

$updates = [];
$params  = [];
$types   = '';

if (isset($input['title']) && trim($input['title']) !== '') {
    $updates[] = 'title = ?';
    $params[]  = trim($input['title']);
    $types    .= 's';
}
if (array_key_exists('claude_session_id', $input)) {
    $updates[] = 'claude_session_id = ?';
    $params[]  = $input['claude_session_id'] !== null ? (string) $input['claude_session_id'] : null;
    $types    .= 's';
}
if (isset($input['messages']) && is_array($input['messages'])) {
    $updates[] = 'messages_json = ?';
    $params[]  = json_encode($input['messages']);
    $types    .= 's';
}

if (empty($updates)) { chat_jsonResponse(['error' => 'Nothing to update'], 400); exit; }

$params[] = $id;
$types   .= 'i';
$stmt = mysqli_prepare($conn, 'UPDATE content_chats SET ' . implode(', ', $updates) . ' WHERE id = ?');
mysqli_stmt_bind_param($stmt, $types, ...$params);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if (!$ok) { chat_jsonResponse(['error' => 'Update failed'], 500); exit; }

chat_jsonResponse(['success' => true]);
