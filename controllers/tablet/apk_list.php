<?php
/**
 * GET ?requester_email=
 * Response: { rows: [...], active: row|null, max_upload: ini_value }
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/tablet.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if (!$conn) tablet_json_response(500, ['error' => 'Database connection failed']);

require_admin($conn, tablet_requester_email());

$res = mysqli_query($conn, 'SELECT * FROM apk_versions ORDER BY uploaded_at DESC');
$rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
$active = null;
foreach ($rows as &$r) {
    $r['id'] = (int) $r['id'];
    $r['size_bytes'] = (int) $r['size_bytes'];
    $r['is_active'] = (int) $r['is_active'];
    if ($r['is_active'] === 1) $active = $r;
}
unset($r);

echo json_encode([
    'rows' => $rows,
    'active' => $active,
    'max_upload' => ini_get('upload_max_filesize'),
    'public_url' => TABLET_APK_PUBLIC_URL,
]);
