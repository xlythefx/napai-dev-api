<?php
/**
 * GET ?requester_email=
 * Tablet-saas analytics: headline metrics, time series, recent activations.
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/tablet.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if (!$conn) tablet_json_response(500, ['error' => 'Database connection failed']);

require_admin($conn, tablet_requester_email());

$one = function (string $sql) use ($conn): int {
    $r = mysqli_query($conn, $sql);
    return $r ? (int) (mysqli_fetch_assoc($r)['n'] ?? 0) : 0;
};

$today      = $one("SELECT COUNT(*) n FROM license_keys WHERE activated_at IS NOT NULL AND DATE(activated_at) = CURDATE()");
$yesterday  = $one("SELECT COUNT(*) n FROM license_keys WHERE activated_at IS NOT NULL AND DATE(activated_at) = CURDATE() - INTERVAL 1 DAY");
$thisWeek   = $one("SELECT COUNT(*) n FROM license_keys WHERE activated_at IS NOT NULL AND YEARWEEK(activated_at, 1) = YEARWEEK(CURDATE(), 1)");
$lastWeek   = $one("SELECT COUNT(*) n FROM license_keys WHERE activated_at IS NOT NULL AND YEARWEEK(activated_at, 1) = YEARWEEK(CURDATE() - INTERVAL 7 DAY, 1)");
$thisMonth  = $one("SELECT COUNT(*) n FROM license_keys WHERE activated_at IS NOT NULL AND YEAR(activated_at) = YEAR(CURDATE()) AND MONTH(activated_at) = MONTH(CURDATE())");
$lastMonth  = $one("SELECT COUNT(*) n FROM license_keys WHERE activated_at IS NOT NULL AND YEAR(activated_at) = YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(activated_at) = MONTH(CURDATE() - INTERVAL 1 MONTH)");
$totalSold  = $one("SELECT COUNT(*) n FROM license_keys WHERE activated_at IS NOT NULL");
$activeDevices = $one("SELECT COUNT(*) n FROM license_keys WHERE android_id IS NOT NULL AND revoked = 0");
$unbound    = $one("SELECT COUNT(*) n FROM license_keys WHERE android_id IS NULL AND revoked = 0");
$revoked    = $one("SELECT COUNT(*) n FROM license_keys WHERE revoked = 1");

$pct = function (int $now, int $prev): ?float {
    if ($prev === 0) return $now > 0 ? 100.0 : null;
    return round((($now - $prev) / $prev) * 100, 1);
};

$daily = [];
$res = mysqli_query($conn, "SELECT DATE(activated_at) d, COUNT(*) n FROM license_keys WHERE activated_at >= CURDATE() - INTERVAL 29 DAY GROUP BY DATE(activated_at) ORDER BY d ASC");
while ($r = mysqli_fetch_assoc($res)) $daily[] = ['date' => $r['d'], 'count' => (int) $r['n']];

$weekly = [];
$res = mysqli_query($conn, "SELECT YEARWEEK(activated_at, 1) yw, COUNT(*) n FROM license_keys WHERE activated_at >= CURDATE() - INTERVAL 8 WEEK GROUP BY yw ORDER BY yw ASC");
while ($r = mysqli_fetch_assoc($res)) $weekly[] = ['yearweek' => (int) $r['yw'], 'count' => (int) $r['n']];

$monthly = [];
$res = mysqli_query($conn, "SELECT DATE_FORMAT(activated_at, '%Y-%m') ym, COUNT(*) n FROM license_keys WHERE activated_at >= CURDATE() - INTERVAL 6 MONTH GROUP BY ym ORDER BY ym ASC");
while ($r = mysqli_fetch_assoc($res)) $monthly[] = ['month' => $r['ym'], 'count' => (int) $r['n']];

$res = mysqli_query($conn, "SELECT serial, android_id, activated_at FROM license_keys WHERE activated_at IS NOT NULL ORDER BY activated_at DESC LIMIT 8");
$recent = mysqli_fetch_all($res, MYSQLI_ASSOC);

echo json_encode([
    'price' => TABLET_LICENSE_PRICE,
    'currency' => TABLET_LICENSE_CURRENCY,
    'today' => $today,
    'yesterday' => $yesterday,
    'this_week' => $thisWeek,
    'last_week' => $lastWeek,
    'this_month' => $thisMonth,
    'last_month' => $lastMonth,
    'total_sold' => $totalSold,
    'active_devices' => $activeDevices,
    'unbound_keys' => $unbound,
    'revoked_keys' => $revoked,
    'deltas' => [
        'today_vs_yesterday' => $pct($today, $yesterday),
        'week_vs_last' => $pct($thisWeek, $lastWeek),
        'month_vs_last' => $pct($thisMonth, $lastMonth),
    ],
    'series' => [
        'daily' => $daily,
        'weekly' => $weekly,
        'monthly' => $monthly,
    ],
    'recent' => $recent,
]);
