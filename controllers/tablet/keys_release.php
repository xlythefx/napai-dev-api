<?php
/**
 * POST { requester_email, id }
 * Releases device binding so the key can activate on a new device.
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
if ($id <= 0) tablet_json_response(400, ['error' => 'id required']);

$lookup = mysqli_prepare($conn, 'SELECT serial, android_id FROM license_keys WHERE id = ?');
mysqli_stmt_bind_param($lookup, 'i', $id);
mysqli_stmt_execute($lookup);
$prev = mysqli_fetch_assoc(mysqli_stmt_get_result($lookup)) ?: [];
mysqli_stmt_close($lookup);

$stmt = mysqli_prepare($conn, 'UPDATE license_keys SET android_id = NULL, activated_at = NULL WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

tablet_audit($conn, $actorEmail, 'keys.release', $prev['serial'] ?? null, [
    'id' => $id,
    'released_android_id' => $prev['android_id'] ?? null,
]);

echo json_encode(['ok' => true]);
