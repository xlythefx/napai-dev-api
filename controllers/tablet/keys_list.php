<?php
/**
 * GET /napai-api/controllers/tablet/keys_list.php
 *   ?requester_email=admin@nap-ai.com
 *   &filter=all|active|unbound|revoked  (default all)
 *   &q=<search>                          (LIKE on serial/android_id/note)
 *   &page=1&per_page=10
 * Response: { rows[], total, page, per_page, counts:{active,unbound,revoked,total} }
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/tablet.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if (!$conn) tablet_json_response(500, ['error' => 'Database connection failed']);

require_admin($conn, tablet_requester_email());

$filter   = $_GET['filter'] ?? 'all';
$q        = trim((string) ($_GET['q'] ?? ''));
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = max(1, min(100, (int) ($_GET['per_page'] ?? 10)));

$where = [];
$params = [];
$types = '';
if ($filter === 'active')        $where[] = 'android_id IS NOT NULL AND revoked = 0 AND (kind = "lifetime" OR expires_at IS NULL OR expires_at > NOW())';
elseif ($filter === 'unbound')   $where[] = 'android_id IS NULL AND revoked = 0';
elseif ($filter === 'revoked')   $where[] = 'revoked = 1';
elseif ($filter === 'trial')     $where[] = 'kind = "trial"';
elseif ($filter === 'expired')   $where[] = 'kind = "trial" AND expires_at IS NOT NULL AND expires_at < NOW()';
if ($q !== '') {
    $where[] = '(serial LIKE ? OR android_id LIKE ? OR note LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM license_keys $whereSql");
if ($params) mysqli_stmt_bind_param($count, $types, ...$params);
mysqli_stmt_execute($count);
$totalRows = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($count))['c'];
mysqli_stmt_close($count);

$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$sql = "SELECT id, serial, android_id, activated_at, revoked, note, created_at,
               kind, expires_at, buyer_email, type, sell_type, custom_price,
               bulk_emailed_at, bulk_emailed_to
        FROM license_keys $whereSql
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $sql);
$pageParams = $params;
$pageTypes  = $types . 'ii';
$pageParams[] = $perPage;
$pageParams[] = $offset;
mysqli_stmt_bind_param($stmt, $pageTypes, ...$pageParams);
mysqli_stmt_execute($stmt);
$rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$cres = mysqli_query($conn, "SELECT
    SUM(android_id IS NOT NULL AND revoked = 0 AND (kind = 'lifetime' OR expires_at IS NULL OR expires_at > NOW())) AS active,
    SUM(android_id IS NULL AND revoked = 0) AS unbound,
    SUM(revoked = 1) AS revoked,
    SUM(kind = 'trial') AS trial,
    SUM(kind = 'trial' AND expires_at IS NOT NULL AND expires_at < NOW()) AS expired,
    COUNT(*) AS total
    FROM license_keys");
$counts = mysqli_fetch_assoc($cres) ?: ['active' => 0, 'unbound' => 0, 'revoked' => 0, 'trial' => 0, 'expired' => 0, 'total' => 0];
foreach ($counts as $k => $v) $counts[$k] = (int) $v;

echo json_encode([
    'rows' => array_map(function ($r) {
        $r['id'] = (int) $r['id'];
        $r['revoked'] = (int) $r['revoked'];
        return $r;
    }, $rows),
    'total' => $totalRows,
    'page' => $page,
    'per_page' => $perPage,
    'total_pages' => $totalPages,
    'counts' => $counts,
]);
