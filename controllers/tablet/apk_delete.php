<?php
/**
 * POST { requester_email, id }
 * Refuses to delete the active version.
 * Response: { ok: true } or { ok: false, error }
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

$stmt = mysqli_prepare($conn, 'SELECT filename, is_active FROM apk_versions WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) tablet_json_response(404, ['error' => 'Version not found']);
if ((int) $row['is_active'] === 1) {
    tablet_json_response(409, ['ok' => false, 'error' => 'Cannot delete the active version. Activate another first.']);
}

$f = TABLET_APK_STORAGE_DIR . '/' . $row['filename'];
if (is_file($f)) @unlink($f);

$del = mysqli_prepare($conn, 'DELETE FROM apk_versions WHERE id = ?');
mysqli_stmt_bind_param($del, 'i', $id);
mysqli_stmt_execute($del);
mysqli_stmt_close($del);

tablet_audit($conn, $actorEmail, 'apk.delete', null, [
    'id'       => $id,
    'filename' => $row['filename'],
]);

echo json_encode(['ok' => true]);
