<?php
/**
 * POST { requester_email, id }
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

mysqli_query($conn, 'UPDATE apk_versions SET is_active = 0');
$stmt = mysqli_prepare($conn, 'UPDATE apk_versions SET is_active = 1 WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

tablet_audit($conn, $actorEmail, 'apk.activate', null, ['id' => $id]);

echo json_encode(['ok' => true]);
