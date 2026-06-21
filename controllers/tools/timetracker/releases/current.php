<?php
/**
 * Get the current release for a platform (public).
 * GET ?platform=win&currentVersion=1.1
 *   - platform: win | mac | linux (default win)
 *   - currentVersion: optional; if provided and matches/exceeds the current
 *                     release version, response includes upToDate: true.
 *
 * Response shapes:
 *   - { success, release: null }                                // nothing published yet
 *   - { success, upToDate: true, version: "1.2" }               // client is current
 *   - { success, release: { version, downloadUrl, size, sha256,
 *                            autoDownload, releaseNotes, releasedAt, ... } }
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
$clientVersion = isset($_GET['currentVersion']) ? trim((string) $_GET['currentVersion']) : '';

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, version, platform, filename, file_size, sha256, notes,
            auto_download, uploaded_at
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

/**
 * Numeric semver-ish compare. Handles "1.2", "1.2.3", "1.10", "1.2-beta".
 * Returns negative if $a < $b, 0 if equal, positive if $a > $b.
 */
function compare_versions(string $a, string $b): int {
    $normalize = function (string $v): array {
        $v = preg_replace('/[-+].*$/', '', $v);
        $parts = array_map('intval', explode('.', $v));
        while (count($parts) < 3) $parts[] = 0;
        return $parts;
    };
    $pa = $normalize($a);
    $pb = $normalize($b);
    for ($i = 0; $i < 3; $i++) {
        if ($pa[$i] !== $pb[$i]) return $pa[$i] - $pb[$i];
    }
    return 0;
}

if ($clientVersion !== '' && compare_versions($clientVersion, $row['version']) >= 0) {
    echo json_encode([
        'success'  => true,
        'upToDate' => true,
        'version'  => $row['version'],
    ]);
    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base   = preg_replace('#/controllers/.*$#', '', $_SERVER['SCRIPT_NAME']);
$downloadUrl = sprintf('%s://%s%s/controllers/tools/timetracker/releases/download.php?id=%d',
    $scheme, $host, $base, (int) $row['id']);

$releasedAt = $row['uploaded_at']
    ? gmdate('Y-m-d\TH:i:s\Z', strtotime($row['uploaded_at']))
    : null;

echo json_encode([
    'success' => true,
    'release' => [
        'id'           => (int) $row['id'],
        'version'      => $row['version'],
        'platform'     => $row['platform'],
        'filename'     => $row['filename'],
        'size'         => (int) $row['file_size'],
        'file_size'    => (int) $row['file_size'],
        'sha256'       => $row['sha256'],
        'autoDownload' => (bool) $row['auto_download'],
        'releaseNotes' => $row['notes'],
        'notes'        => $row['notes'],
        'releasedAt'   => $releasedAt,
        'uploaded_at'  => $row['uploaded_at'],
        'downloadUrl'  => $downloadUrl,
        'download_url' => $downloadUrl,
    ],
]);
