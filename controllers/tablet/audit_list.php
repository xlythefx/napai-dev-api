<?php
/**
 * GET /controllers/tablet/audit_list.php
 *   ?requester_email=<admin>
 *   &page=1 &per_page=50
 *   &action=<exact match, e.g. keys.revoke>     (optional)
 *   &serial=<substring>                          (optional)
 *   &actor=<substring of actor_email>            (optional)
 *
 * Returns rows newest-first with total count for pagination.
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/tablet.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'GET') tablet_json_response(405, ['ok' => false, 'error' => 'method_not_allowed']);
if (!$conn) tablet_json_response(500, ['ok' => false, 'error' => 'db_error']);

require_admin($conn, tablet_requester_email());

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = max(1, min(200, (int) ($_GET['per_page'] ?? 50)));
$offset  = ($page - 1) * $perPage;

$action = trim((string) ($_GET['action'] ?? ''));
$serial = trim((string) ($_GET['serial'] ?? ''));
$actor  = trim((string) ($_GET['actor']  ?? ''));

$where  = [];
$params = [];
$types  = '';

if ($action !== '') { $where[] = 'action = ?';              $params[] = $action;          $types .= 's'; }
if ($serial !== '') { $where[] = 'target_serial LIKE ?';    $params[] = '%' . $serial . '%'; $types .= 's'; }
if ($actor  !== '') { $where[] = 'actor_email LIKE ?';      $params[] = '%' . $actor  . '%'; $types .= 's'; }

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Total
$countSql = "SELECT COUNT(*) c FROM tablet_admin_audit $whereSql";
$countStmt = mysqli_prepare($conn, $countSql);
if ($params) mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$total = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['c'] ?? 0);
mysqli_stmt_close($countStmt);

// Page
$rowSql = "SELECT id, actor_email, action, target_serial, ip, payload_json, created_at
           FROM tablet_admin_audit $whereSql
           ORDER BY id DESC
           LIMIT ? OFFSET ?";
$rowStmt = mysqli_prepare($conn, $rowSql);
$rowParams = array_merge($params, [$perPage, $offset]);
$rowTypes  = $types . 'ii';
mysqli_stmt_bind_param($rowStmt, $rowTypes, ...$rowParams);
mysqli_stmt_execute($rowStmt);
$rows = mysqli_fetch_all(mysqli_stmt_get_result($rowStmt), MYSQLI_ASSOC);
mysqli_stmt_close($rowStmt);

// Decode payload_json client-side instead of leaving it as a string blob.
foreach ($rows as &$r) {
    $r['payload'] = $r['payload_json'] !== null ? json_decode($r['payload_json'], true) : null;
    unset($r['payload_json']);
}
unset($r);

echo json_encode([
    'ok'          => true,
    'rows'        => $rows,
    'total'       => $total,
    'page'        => $page,
    'per_page'    => $perPage,
    'total_pages' => max(1, (int) ceil($total / $perPage)),
]);
