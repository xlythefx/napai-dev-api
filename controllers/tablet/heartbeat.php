<?php
/**
 * Public endpoint hit by the Flutter app every ~5 min.
 * POST { serial, androidId, batteryPct?, version? }
 * → { ok, revoked, reason, kind, expiresAt }
 *
 * revoked=true also fires when a trial has expired or the binding has been
 * transferred. The Flutter app wipes its local token in that case.
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
$batteryPct = isset($body['batteryPct']) ? (int) $body['batteryPct'] : null;
$appVersion = isset($body['version']) ? substr((string) $body['version'], 0, 32) : null;

if ($serial === '' || $androidId === '') tablet_json_response(400, ['ok' => false, 'reason' => 'missing_fields']);

$stmt = mysqli_prepare($conn, 'SELECT android_id, revoked, kind, type, sell_type, expires_at FROM license_keys WHERE serial = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 's', $serial);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) {
    echo json_encode(['ok' => true, 'revoked' => true, 'reason' => 'unknown']);
    exit;
}

$revoked = (int) $row['revoked'] === 1;
$bindingMismatch = !empty($row['android_id']) && $row['android_id'] !== $androidId;
$expired = $row['kind'] === 'trial'
    && $row['expires_at'] !== null
    && strtotime($row['expires_at']) < time();

$ins = mysqli_prepare($conn, 'INSERT INTO heartbeats (serial, android_id, battery_pct, app_version) VALUES (?, ?, ?, ?)');
mysqli_stmt_bind_param($ins, 'ssis', $serial, $androidId, $batteryPct, $appVersion);
mysqli_stmt_execute($ins);
mysqli_stmt_close($ins);

$reason = $revoked ? 'revoked'
    : ($bindingMismatch ? 'transferred'
    : ($expired ? 'trial_expired' : null));

echo json_encode([
    'ok' => true,
    'revoked' => $revoked || $bindingMismatch || $expired,
    'reason' => $reason,
    'kind' => $row['kind'],
    'type' => $row['type'],
    'sellType' => $row['sell_type'],
    'expiresAt' => $row['expires_at'],
]);
