<?php
/**
 * GET ?id=&requester_email=
 * Response: { key, activations[], last_heartbeat }
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/tablet.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if (!$conn) tablet_json_response(500, ['error' => 'Database connection failed']);

require_admin($conn, tablet_requester_email());

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) tablet_json_response(400, ['error' => 'id required']);

$stmt = mysqli_prepare($conn, 'SELECT id, serial, android_id, activated_at, revoked, note, created_at, kind, expires_at, buyer_email FROM license_keys WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$key = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$key) tablet_json_response(404, ['error' => 'Key not found']);
$key['id'] = (int) $key['id'];
$key['revoked'] = (int) $key['revoked'];

$stmt = mysqli_prepare($conn, 'SELECT android_id, ip, result, created_at FROM activations_log WHERE serial = ? ORDER BY created_at DESC LIMIT 50');
mysqli_stmt_bind_param($stmt, 's', $key['serial']);
mysqli_stmt_execute($stmt);
$activations = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, 'SELECT android_id, battery_pct, app_version, created_at FROM heartbeats WHERE serial = ? ORDER BY created_at DESC LIMIT 1');
mysqli_stmt_bind_param($stmt, 's', $key['serial']);
mysqli_stmt_execute($stmt);
$lastHb = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

echo json_encode([
    'key' => $key,
    'activations' => $activations,
    'last_heartbeat' => $lastHb,
]);
