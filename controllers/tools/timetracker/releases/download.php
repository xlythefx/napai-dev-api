<?php
/**
 * Stream a release installer to the browser (public).
 * GET ?id=N           — specific release
 * GET ?platform=win   — current release for the platform (default win)
 * Increments download_count on success.
 */
require_once __DIR__ . '/../../../../config/cors.php';
require_once __DIR__ . '/../../../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}
if (!$conn) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$platform = isset($_GET['platform']) && $_GET['platform'] !== '' ? $_GET['platform'] : 'win';

if ($id > 0) {
    $stmt = mysqli_prepare($conn,
        'SELECT id, filename, file_path, file_size FROM time_tracker_releases WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
} else {
    $stmt = mysqli_prepare($conn,
        'SELECT id, filename, file_path, file_size FROM time_tracker_releases
          WHERE platform = ? AND is_current = 1
       ORDER BY uploaded_at DESC LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $platform);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$row) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Release not found']);
    exit;
}

$absPath = __DIR__ . '/../../../../' . $row['file_path'];
if (!is_file($absPath)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Installer file is missing on disk']);
    exit;
}

$size = filesize($absPath);
$ext  = strtolower(pathinfo($row['filename'], PATHINFO_EXTENSION));
$mimes = [
    'exe' => 'application/vnd.microsoft.portable-executable',
    'msi' => 'application/x-msi',
    'dmg' => 'application/x-apple-diskimage',
    'pkg' => 'application/x-newton-compatible-pkg',
    'zip' => 'application/zip',
    'deb' => 'application/vnd.debian.binary-package',
];
$mime = $mimes[$ext] ?? 'application/octet-stream';

// Bump download counter — best-effort; never block the download.
@mysqli_query(
    $conn,
    'UPDATE time_tracker_releases SET download_count = download_count + 1 WHERE id = ' . (int) $row['id']
);

// Disable any output buffering so the file streams directly.
while (ob_get_level()) { ob_end_clean(); }

header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $row['filename']) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');

$fh = fopen($absPath, 'rb');
if ($fh === false) {
    http_response_code(500);
    exit;
}
while (!feof($fh)) {
    echo fread($fh, 1024 * 256); // 256 KB chunks
    flush();
}
fclose($fh);
exit;
