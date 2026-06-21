<?php
/**
 * Public endpoint hit by the Flutter app on first activation.
 * POST { serial, androidId } → { ok:true, signedToken, kind, expiresAt? } or 4xx.
 *
 * For trial keys (kind='trial'):
 *   - Refuses if this android_id has already activated any trial (one-trial-per-device).
 *   - Sets expires_at = NOW() + INTERVAL TABLET_TRIAL_DAYS DAY on first bind.
 *   - Refuses with reason='trial_expired' if the key is already past expiry.
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/tablet.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') tablet_json_response(405, ['ok' => false, 'reason' => 'method_not_allowed']);
if (!$conn) tablet_json_response(500, ['ok' => false, 'reason' => 'db_error']);
tablet_guard_maintenance($conn);

$body = tablet_json_input();
$serial      = strtoupper(trim((string) ($body['serial'] ?? '')));
$androidId   = trim((string) ($body['androidId'] ?? ''));
// Optional composite hardware fingerprint from the Flutter app. Older APKs
// won't include this; in that case we fall back to android_id-only trial
// detection. New APKs report a sha256 over manufacturer/model/build/
// per-install-uuid so factory resets can't hide a repeat trial.
$deviceFp    = trim((string) ($body['deviceFingerprint'] ?? ''));
if ($deviceFp !== '' && (strlen($deviceFp) > 64 || !preg_match('/^[A-Za-z0-9_-]+$/', $deviceFp))) {
    // Bad input shape — silently drop rather than error out so a buggy build
    // doesn't brick activations. Treated as "no fingerprint".
    $deviceFp = '';
}

if ($serial === '' || $androidId === '') tablet_json_response(400, ['ok' => false, 'reason' => 'missing_fields']);

// ---- Anti-bruteforce ----
// 5 failed activations from the same android_id within 5 minutes locks
// further attempts for the rest of the rolling window. Stops resellers
// (or attackers) from spamming serials. Successful activations naturally
// age out of the window in 5 minutes.
$lockStmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS c,
            UNIX_TIMESTAMP(MIN(created_at)) AS oldest_ts,
            UNIX_TIMESTAMP(NOW()) AS now_ts
     FROM activations_log
     WHERE android_id = ?
       AND result IN ('unknown', 'already_bound', 'revoked', 'trial_already_used', 'trial_expired')
       AND created_at > NOW() - INTERVAL 5 MINUTE"
);
mysqli_stmt_bind_param($lockStmt, 's', $androidId);
mysqli_stmt_execute($lockStmt);
$lockRow = mysqli_fetch_assoc(mysqli_stmt_get_result($lockStmt));
mysqli_stmt_close($lockStmt);
$failedCount = (int) ($lockRow['c'] ?? 0);
if ($failedCount >= 5) {
    // Use MySQL's clock to avoid PHP timezone drift.
    $oldestTs  = (int) $lockRow['oldest_ts'];
    $serverNow = (int) $lockRow['now_ts'];
    $retryAfter = max(1, ($oldestTs + 5 * 60) - $serverNow);
    // Don't log this — would extend the lockout window indefinitely.
    http_response_code(429);
    header('Retry-After: ' . $retryAfter);
    echo json_encode([
        'ok' => false,
        'reason' => 'too_many_attempts',
        'retry_after_seconds' => $retryAfter,
    ]);
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT id, android_id, revoked, kind, type, sell_type, expires_at FROM license_keys WHERE serial = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 's', $serial);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) {
    tablet_log_activation($conn, $serial, $androidId, 'unknown');
    tablet_json_response(404, ['ok' => false, 'reason' => 'unknown']);
}
if ((int) $row['revoked'] === 1) {
    tablet_log_activation($conn, $serial, $androidId, 'revoked');
    tablet_json_response(410, ['ok' => false, 'reason' => 'revoked']);
}

// Trial-already-expired check (e.g. trial activated long ago, never reset).
if ($row['kind'] === 'trial' && $row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
    tablet_log_activation($conn, $serial, $androidId, 'trial_expired');
    tablet_json_response(410, ['ok' => false, 'reason' => 'trial_expired']);
}

$boundId = $row['android_id'];

// Trial-abuse check: if this is a trial AND this device has previously
// activated any other trial, refuse — one trial per device. We match on
// android_id (always present) OR device_fingerprint when both ends report
// it; the fingerprint catches factory resets that rotate android_id.
if ($row['kind'] === 'trial' && ($boundId === null || $boundId === '')) {
    if ($deviceFp !== '') {
        $check = mysqli_prepare(
            $conn,
            'SELECT 1 FROM license_keys
             WHERE kind = "trial" AND id != ?
               AND (android_id = ? OR device_fingerprint = ?)
             LIMIT 1'
        );
        $rid = (int) $row['id'];
        mysqli_stmt_bind_param($check, 'iss', $rid, $androidId, $deviceFp);
    } else {
        $check = mysqli_prepare(
            $conn,
            'SELECT 1 FROM license_keys WHERE android_id = ? AND kind = "trial" AND id != ? LIMIT 1'
        );
        $rid = (int) $row['id'];
        mysqli_stmt_bind_param($check, 'si', $androidId, $rid);
    }
    mysqli_stmt_execute($check);
    $hit = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
    mysqli_stmt_close($check);
    if ($hit) {
        tablet_log_activation($conn, $serial, $androidId, 'trial_already_used');
        tablet_json_response(409, ['ok' => false, 'reason' => 'trial_already_used']);
    }
}

if ($boundId !== null && $boundId !== '' && $boundId !== $androidId) {
    tablet_log_activation($conn, $serial, $androidId, 'already_bound');
    tablet_json_response(409, ['ok' => false, 'reason' => 'already_bound']);
}

if ($boundId === null || $boundId === '') {
    // Only store fingerprint when supplied; legacy APKs send empty string.
    $fpForDb = $deviceFp !== '' ? $deviceFp : null;
    if ($row['kind'] === 'trial') {
        $up = mysqli_prepare(
            $conn,
            'UPDATE license_keys SET android_id = ?, device_fingerprint = ?, activated_at = NOW(),
             expires_at = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?'
        );
        $rid  = (int) $row['id'];
        $days = TABLET_TRIAL_DAYS;
        mysqli_stmt_bind_param($up, 'ssii', $androidId, $fpForDb, $days, $rid);
        mysqli_stmt_execute($up);
        mysqli_stmt_close($up);
        $row['expires_at'] = date('Y-m-d H:i:s', time() + $days * 86400);
    } else {
        $up = mysqli_prepare(
            $conn,
            'UPDATE license_keys SET android_id = ?, device_fingerprint = ?, activated_at = NOW() WHERE id = ?'
        );
        $rid = (int) $row['id'];
        mysqli_stmt_bind_param($up, 'ssi', $androidId, $fpForDb, $rid);
        mysqli_stmt_execute($up);
        mysqli_stmt_close($up);
    }
}

$token = tablet_sign_token($serial, $androidId);
tablet_log_activation($conn, $serial, $androidId, 'ok');
echo json_encode([
    'ok' => true,
    'signedToken' => $token,
    'kind' => $row['kind'],
    'type' => $row['type'],
    'sellType' => $row['sell_type'],
    'expiresAt' => $row['expires_at'],
]);
