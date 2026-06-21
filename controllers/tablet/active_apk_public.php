<?php
/**
 * Public active-APK metadata endpoint (no auth).
 * Used by the buyer-facing /products/quantab/download page to render the
 * version badge, hotfix notes, size, and SHA-256 next to the download
 * buttons. Mirrors the row exposed to the admin via apk_list.php's
 * `active` field, but without exposing the full version history.
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/tablet.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$res = mysqli_query(
    $conn,
    'SELECT version, size_bytes, sha256_b64, notes, uploaded_at
     FROM apk_versions
     WHERE is_active = 1
     ORDER BY uploaded_at DESC
     LIMIT 1'
);
$row = $res ? mysqli_fetch_assoc($res) : null;

if (!$row) {
    echo json_encode(['active' => null, 'apk_url' => TABLET_APK_PUBLIC_URL]);
    exit;
}

$row['size_bytes'] = (int) $row['size_bytes'];

echo json_encode([
    'active' => $row,
    'apk_url' => TABLET_APK_PUBLIC_URL,
]);
