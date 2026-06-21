<?php
/**
 * Public APK download endpoint. Hit by Android during device-owner
 * provisioning. Streams the apk_versions row marked is_active = 1.
 *
 * URL: https://nap-ai.com/napai-api/apk/vendotab   (no extension via .htaccess)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/tablet.php';

if (!$conn) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Database connection failed.\n";
    exit;
}

$res = mysqli_query($conn, 'SELECT filename, size_bytes FROM apk_versions WHERE is_active = 1 ORDER BY uploaded_at DESC LIMIT 1');
$row = $res ? mysqli_fetch_assoc($res) : null;
if (!$row) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo "No active APK is published. Upload one in the admin dashboard.\n";
    exit;
}

$path = TABLET_APK_STORAGE_DIR . '/' . $row['filename'];
if (!is_file($path)) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Active APK file is missing on disk.\n";
    exit;
}

header('Content-Type: application/vnd.android.package-archive');
header('Content-Length: ' . (int) $row['size_bytes']);
header('Content-Disposition: attachment; filename="vendotab.apk"');
header('Cache-Control: public, max-age=300');
readfile($path);
exit;
