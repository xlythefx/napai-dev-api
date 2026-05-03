<?php
/**
 * List all Time Tracker releases (admin).
 * GET ?requester_email=...&platform=win
 * -> { success, releases: [ ... ] }
 */
require_once __DIR__ . '/../../../../config/cors.php';
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../lib/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

require_admin($conn, $_GET['requester_email'] ?? '');

$platform = isset($_GET['platform']) ? trim($_GET['platform']) : null;

if ($platform) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT r.id, r.version, r.platform, r.filename, r.file_size, r.sha256, r.notes,
                r.is_current, r.uploaded_at, r.download_count, d.email AS uploaded_by_email
           FROM time_tracker_releases r
      LEFT JOIN developers d ON d.id = r.uploaded_by
          WHERE r.platform = ?
       ORDER BY r.uploaded_at DESC"
    );
    mysqli_stmt_bind_param($stmt, 's', $platform);
} else {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT r.id, r.version, r.platform, r.filename, r.file_size, r.sha256, r.notes,
                r.is_current, r.uploaded_at, r.download_count, d.email AS uploaded_by_email
           FROM time_tracker_releases r
      LEFT JOIN developers d ON d.id = r.uploaded_by
       ORDER BY r.uploaded_at DESC"
    );
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$releases = [];
while ($row = mysqli_fetch_assoc($res)) {
    $releases[] = [
        'id'                => (int) $row['id'],
        'version'           => $row['version'],
        'platform'          => $row['platform'],
        'filename'          => $row['filename'],
        'file_size'         => (int) $row['file_size'],
        'sha256'            => $row['sha256'],
        'notes'             => $row['notes'],
        'is_current'        => (bool) $row['is_current'],
        'uploaded_at'       => $row['uploaded_at'],
        'download_count'    => (int) $row['download_count'],
        'uploaded_by_email' => $row['uploaded_by_email'],
    ];
}
mysqli_stmt_close($stmt);

echo json_encode(['success' => true, 'releases' => $releases]);
