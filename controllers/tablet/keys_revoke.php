<?php
/**
 * POST { requester_email, id, revoke: 0|1 }
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
$revoke = (int) ($body['revoke'] ?? 1) === 1 ? 1 : 0;
if ($id <= 0) tablet_json_response(400, ['error' => 'id required']);

// Capture the serial before the update so the audit row is human-readable.
$lookup = mysqli_prepare($conn, 'SELECT serial FROM license_keys WHERE id = ?');
mysqli_stmt_bind_param($lookup, 'i', $id);
mysqli_stmt_execute($lookup);
$serial = (mysqli_fetch_assoc(mysqli_stmt_get_result($lookup)) ?: [])['serial'] ?? null;
mysqli_stmt_close($lookup);

$stmt = mysqli_prepare($conn, 'UPDATE license_keys SET revoked = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'ii', $revoke, $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

tablet_audit($conn, $actorEmail, $revoke ? 'keys.revoke' : 'keys.unrevoke', $serial, ['id' => $id]);

echo json_encode(['ok' => true]);
