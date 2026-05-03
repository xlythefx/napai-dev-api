<?php
/**
 * Get the current release for a platform (public).
 * GET ?platform=win  (default win)
 * -> { success, release: { id, version, platform, filename, file_size, sha256, notes, uploaded_at, download_url } }
 *    or { success, release: null } when nothing has been published.
 */
require_once __DIR__ . '/../../../../config/cors.php';
require_once __DIR__ . '/../../../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$platform = isset($_GET['platform']) && $_GET['platform'] !== '' ? $_GET['platform'] : 'win';

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, version, platform, filename, file_size, sha256, notes, uploaded_at
       FROM time_tracker_releases
      WHERE platform = ? AND is_current = 1
   ORDER BY uploaded_at DESC LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 's', $platform);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$row) {
    echo json_encode(['success' => true, 'release' => null]);
    exit;
}

// Build a self-referential download URL the frontend can hand to the user.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base   = preg_replace('#/controllers/.*$#', '', $_SERVER['SCRIPT_NAME']); // /napai-api
$downloadUrl = sprintf('%s://%s%s/controllers/tools/timetracker/releases/download.php?id=%d',
    $scheme, $host, $base, (int) $row['id']);

echo json_encode([
    'success' => true,
    'release' => [
        'id'           => (int) $row['id'],
        'version'      => $row['version'],
        'platform'     => $row['platform'],
        'filename'     => $row['filename'],
        'file_size'    => (int) $row['file_size'],
        'sha256'       => $row['sha256'],
        'notes'        => $row['notes'],
        'uploaded_at'  => $row['uploaded_at'],
        'download_url' => $downloadUrl,
    ],
]);
