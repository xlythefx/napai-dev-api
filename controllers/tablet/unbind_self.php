<?php
/**
 * Public self-service unbind. Lets an operator on the tablet release this
 * license so the same key can activate on a replacement tablet.
 *
 * POST { serial, androidId, signedToken }
 *
 * Auth: signedToken must equal HMAC-SHA256(secret, "serial|androidId").
 * Only the device that activated this key can have a valid token, so this
 * endpoint is safe to expose without admin auth.
 *
 * Trials are refused — unbinding wouldn't help (one-trial-per-device check
 * still blocks the same android_id from activating another trial).
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/tablet.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') tablet_json_response(405, ['ok' => false, 'reason' => 'method_not_allowed']);
if (!$conn) tablet_json_response(500, ['ok' => false, 'reason' => 'db_error']);

$body = tablet_json_input();
$serial    = strtoupper(trim((string) ($body['serial'] ?? '')));
$androidId = trim((string) ($body['androidId'] ?? ''));
$token     = trim((string) ($body['signedToken'] ?? ''));

if ($serial === '' || $androidId === '' || $token === '') {
    tablet_json_response(400, ['ok' => false, 'reason' => 'missing_fields']);
}

$expected = tablet_sign_token($serial, $androidId);
if (!hash_equals($expected, $token)) {
    tablet_log_activation($conn, $serial, $androidId, 'unbind_bad_token');
    tablet_json_response(403, ['ok' => false, 'reason' => 'invalid_token']);
}

$stmt = mysqli_prepare($conn, 'SELECT id, android_id, kind, revoked FROM license_keys WHERE serial = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 's', $serial);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) tablet_json_response(404, ['ok' => false, 'reason' => 'unknown']);

if ($row['kind'] === 'trial') {
    tablet_log_activation($conn, $serial, $androidId, 'unbind_trial_refused');
    tablet_json_response(409, ['ok' => false, 'reason' => 'trials_cannot_unbind']);
}

if ($row['android_id'] !== null && $row['android_id'] !== '' && $row['android_id'] !== $androidId) {
    // Token was valid for the supplied androidId, but the DB binding belongs
    // to a different device. Refuse — the owning device should be the one
    // that initiates the unbind.
    tablet_log_activation($conn, $serial, $androidId, 'unbind_wrong_device');
    tablet_json_response(409, ['ok' => false, 'reason' => 'wrong_device']);
}

$up = mysqli_prepare($conn, 'UPDATE license_keys SET android_id = NULL, activated_at = NULL WHERE id = ?');
$rid = (int) $row['id'];
mysqli_stmt_bind_param($up, 'i', $rid);
mysqli_stmt_execute($up);
mysqli_stmt_close($up);

tablet_log_activation($conn, $serial, $androidId, 'unbind_self');
echo json_encode(['ok' => true]);
