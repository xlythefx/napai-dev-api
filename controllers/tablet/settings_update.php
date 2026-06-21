<?php
/**
 * POST /controllers/tablet/settings_update.php
 * Body: { requester_email, email_keys_enabled?: bool, is_maintenance?: bool }
 *
 * Upserts the keys it receives. Omitted keys are left untouched, so the page
 * can patch one toggle at a time without round-tripping the whole state.
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/tablet.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') tablet_json_response(405, ['ok' => false, 'error' => 'method_not_allowed']);
if (!$conn) tablet_json_response(500, ['ok' => false, 'error' => 'db_error']);

$actorEmail = tablet_requester_email();
require_admin($conn, $actorEmail);

$body = tablet_json_input();

$updates = [];
if (array_key_exists('email_keys_enabled', $body)) {
    $updates['email_keys_enabled'] = $body['email_keys_enabled'] ? '1' : '0';
}
if (array_key_exists('is_maintenance', $body)) {
    $updates['is_maintenance'] = $body['is_maintenance'] ? '1' : '0';
}

if (count($updates) === 0) {
    tablet_json_response(400, ['ok' => false, 'error' => 'no_changes']);
}

$sql = "INSERT INTO tablet_settings (`key`, `value`) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";
$stmt = mysqli_prepare($conn, $sql);
foreach ($updates as $k => $v) {
    mysqli_stmt_bind_param($stmt, 'ss', $k, $v);
    if (!mysqli_stmt_execute($stmt)) {
        tablet_json_response(500, ['ok' => false, 'error' => 'db_write_failed', 'detail' => mysqli_error($conn)]);
    }
}
mysqli_stmt_close($stmt);

tablet_audit($conn, $actorEmail, 'settings.update', null, $updates);

echo json_encode([
    'ok' => true,
    'settings' => [
        'email_keys_enabled' => ($updates['email_keys_enabled'] ?? (tablet_email_enabled($conn) ? '1' : '0')) === '1',
        'is_maintenance'     => ($updates['is_maintenance']     ?? (tablet_is_maintenance($conn) ? '1' : '0')) === '1',
    ],
]);
