<?php
/**
 * POST { requester_email, id, to_kind: 'lifetime'|'trial', extend_days? }
 *
 * - to_kind='lifetime' → SET kind='lifetime', expires_at=NULL (upgrade after purchase)
 * - to_kind='trial'    → SET kind='trial', extend_days from now (rare; mostly for testing)
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
$to = (string) ($body['to_kind'] ?? 'lifetime');
$extendDays = (int) ($body['extend_days'] ?? 0);
if ($id <= 0) tablet_json_response(400, ['error' => 'id required']);
if (!in_array($to, ['lifetime', 'trial'], true)) tablet_json_response(400, ['error' => 'invalid to_kind']);

if ($to === 'lifetime') {
    $stmt = mysqli_prepare($conn, 'UPDATE license_keys SET kind = "lifetime", expires_at = NULL WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
} else {
    $days = $extendDays > 0 ? $extendDays : TABLET_TRIAL_DAYS;
    $stmt = mysqli_prepare(
        $conn,
        'UPDATE license_keys SET kind = "trial", expires_at = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?'
    );
    mysqli_stmt_bind_param($stmt, 'ii', $days, $id);
}
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$serialLookup = mysqli_prepare($conn, 'SELECT serial FROM license_keys WHERE id = ?');
mysqli_stmt_bind_param($serialLookup, 'i', $id);
mysqli_stmt_execute($serialLookup);
$serial = (mysqli_fetch_assoc(mysqli_stmt_get_result($serialLookup)) ?: [])['serial'] ?? null;
mysqli_stmt_close($serialLookup);
tablet_audit($conn, $actorEmail, 'keys.convert', $serial, [
    'id' => $id,
    'to_kind' => $to,
    'extend_days' => $extendDays,
]);

echo json_encode(['ok' => true]);
