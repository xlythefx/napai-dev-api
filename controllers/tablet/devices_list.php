<?php
/**
 * GET ?requester_email=
 * Active (bound, non-revoked) license keys joined with their latest heartbeat.
 * Response: { rows: [...] }
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/tablet.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if (!$conn) tablet_json_response(500, ['error' => 'Database connection failed']);

require_admin($conn, tablet_requester_email());

$sql = "SELECT lk.id, lk.serial, lk.android_id, lk.activated_at, lk.note,
        h.battery_pct, h.app_version, h.created_at AS last_seen
        FROM license_keys lk
        LEFT JOIN (
            SELECT t1.* FROM heartbeats t1
            INNER JOIN (
                SELECT serial, MAX(created_at) AS m FROM heartbeats GROUP BY serial
            ) t2 ON t1.serial = t2.serial AND t1.created_at = t2.m
        ) h ON h.serial = lk.serial
        WHERE lk.android_id IS NOT NULL AND lk.revoked = 0
        ORDER BY h.created_at DESC, lk.activated_at DESC";

$res = mysqli_query($conn, $sql);
$rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
foreach ($rows as &$r) {
    $r['id'] = (int) $r['id'];
    $r['battery_pct'] = $r['battery_pct'] === null ? null : (int) $r['battery_pct'];
    $r['stale'] = $r['last_seen'] ? (strtotime($r['last_seen']) < time() - 30 * 60) : true;
}
unset($r);

echo json_encode(['rows' => $rows]);
