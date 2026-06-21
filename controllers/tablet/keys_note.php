<?php
/**
 * POST { requester_email, id, note }
 * Response: { ok: true }
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/tablet.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') tablet_json_response(405, ['error' => 'Method not allowed']);
if (!$conn) tablet_json_response(500, ['error' => 'Database connection failed']);

$actorEmail = tablet_requester_email();
require_admin($conn, $actorEmail);

$body = tablet_json_input();
$id = (int) ($body['id'] ?? 0);
$note = trim((string) ($body['note'] ?? ''));
if ($id <= 0) tablet_json_response(400, ['error' => 'id required']);

$lookup = mysqli_prepare($conn, 'SELECT serial FROM license_keys WHERE id = ?');
mysqli_stmt_bind_param($lookup, 'i', $id);
mysqli_stmt_execute($lookup);
$serial = (mysqli_fetch_assoc(mysqli_stmt_get_result($lookup)) ?: [])['serial'] ?? null;
mysqli_stmt_close($lookup);

$stmt = mysqli_prepare($conn, 'UPDATE license_keys SET note = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'si', $note, $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

tablet_audit($conn, $actorEmail, 'keys.note', $serial, ['id' => $id, 'note' => $note]);

echo json_encode(['ok' => true]);
