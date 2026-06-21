<?php
/**
 * POST multipart/form-data { requester_email, version, notes?, make_active, apk (file) }
 * Response: { ok, id, version, sha256_b64, size_bytes }
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/tablet.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') tablet_json_response(405, ['error' => 'Method not allowed']);
if (!$conn) tablet_json_response(500, ['error' => 'Database connection failed']);

$adminEmail = trim((string) ($_POST['requester_email'] ?? ''));
require_admin($conn, $adminEmail);

$version    = trim((string) ($_POST['version'] ?? ''));
$notes      = trim((string) ($_POST['notes'] ?? ''));
$makeActive = !empty($_POST['make_active']) && $_POST['make_active'] !== 'false' && $_POST['make_active'] !== '0';

if ($version === '') tablet_json_response(400, ['error' => 'version required']);
if (!isset($_FILES['apk']) || $_FILES['apk']['error'] !== UPLOAD_ERR_OK) {
    tablet_json_response(400, ['error' => 'Upload failed (code ' . ($_FILES['apk']['error'] ?? '?') . ')']);
}
if (!preg_match('/\.apk$/i', $_FILES['apk']['name'])) {
    tablet_json_response(400, ['error' => 'File must be an .apk']);
}

if (!is_dir(TABLET_APK_STORAGE_DIR)) @mkdir(TABLET_APK_STORAGE_DIR, 0775, true);
$safeVer  = preg_replace('/[^A-Za-z0-9._-]/', '_', $version);
$filename = sprintf('vendotab-%s-%d.apk', $safeVer, time());
$dest     = TABLET_APK_STORAGE_DIR . '/' . $filename;
if (!move_uploaded_file($_FILES['apk']['tmp_name'], $dest)) {
    tablet_json_response(500, ['error' => 'Could not save file to storage']);
}
$size = filesize($dest);
$raw  = hash_file('sha256', $dest, true);
$sha  = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

$stmt = mysqli_prepare(
    $conn,
    'INSERT INTO apk_versions (version, filename, size_bytes, sha256_b64, notes, is_active, uploaded_by_email) VALUES (?, ?, ?, ?, ?, 0, ?)'
);
mysqli_stmt_bind_param($stmt, 'ssisss', $version, $filename, $size, $sha, $notes, $adminEmail);
mysqli_stmt_execute($stmt);
$newId = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

if ($makeActive) {
    mysqli_query($conn, 'UPDATE apk_versions SET is_active = 0');
    $up = mysqli_prepare($conn, 'UPDATE apk_versions SET is_active = 1 WHERE id = ?');
    mysqli_stmt_bind_param($up, 'i', $newId);
    mysqli_stmt_execute($up);
    mysqli_stmt_close($up);
}

tablet_audit($conn, $adminEmail, 'apk.upload', null, [
    'id'          => $newId,
    'version'     => $version,
    'size_bytes'  => $size,
    'sha256_b64'  => $sha,
    'make_active' => $makeActive,
]);

echo json_encode([
    'ok' => true,
    'id' => $newId,
    'version' => $version,
    'sha256_b64' => $sha,
    'size_bytes' => $size,
    'is_active' => $makeActive ? 1 : 0,
]);
