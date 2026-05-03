<?php
/**
 * Upload a new Time Tracker release (admin).
 * POST multipart:
 *   - file field: installer
 *   - form fields: requester_email, version, platform (default "win"),
 *                  notes (optional), set_current (optional "1")
 * -> { success, release: { id, version, platform, filename, file_size, sha256, is_current, uploaded_at } }
 */

require_once __DIR__ . '/../../../../config/cors.php';
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../lib/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$admin = require_admin($conn, $_POST['requester_email'] ?? '');

$version  = trim((string) ($_POST['version'] ?? ''));
$platform = trim((string) ($_POST['platform'] ?? 'win'));
$notes    = trim((string) ($_POST['notes'] ?? ''));
$setCurrent = !empty($_POST['set_current']);

if ($version === '') {
    http_response_code(400);
    echo json_encode(['error' => 'version is required']);
    exit;
}
if (!in_array($platform, ['win', 'mac', 'linux'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'platform must be win, mac, or linux']);
    exit;
}

if (!isset($_FILES['installer']) || $_FILES['installer']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['installer']['error'] ?? 'missing';
    $hints = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds php.ini upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE form directive',
        UPLOAD_ERR_PARTIAL    => 'Partial upload',
        UPLOAD_ERR_NO_FILE    => 'No file sent',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing tmp dir',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload',
    ];
    $msg = is_int($errCode) && isset($hints[$errCode]) ? $hints[$errCode] : 'Upload failed';
    http_response_code(400);
    echo json_encode(['error' => "installer file required ($msg)"]);
    exit;
}

$origName = $_FILES['installer']['name'];
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$allowedExts = ['exe', 'msi', 'dmg', 'pkg', 'AppImage', 'appimage', 'deb', 'zip'];
if (!in_array($ext, array_map('strtolower', $allowedExts), true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported installer extension: ' . $ext]);
    exit;
}

$relDir = "uploads/timetracker/releases";
$absDir = __DIR__ . '/../../../../' . $relDir;
if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create upload directory']);
    exit;
}

// Versioned filename so we can hold multiple releases.
$safeVersion  = preg_replace('/[^A-Za-z0-9._-]/', '_', $version);
$filename     = "napai-time-tracker-{$platform}-{$safeVersion}-" . time() . '.' . $ext;
$absPath      = $absDir . '/' . $filename;
$relFilePath  = $relDir . '/' . $filename;

if (!move_uploaded_file($_FILES['installer']['tmp_name'], $absPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save uploaded file']);
    exit;
}

$fileSize = filesize($absPath);
$sha256   = @hash_file('sha256', $absPath) ?: null;

mysqli_begin_transaction($conn);
try {
    if ($setCurrent) {
        $clear = mysqli_prepare($conn, 'UPDATE time_tracker_releases SET is_current = 0 WHERE platform = ?');
        mysqli_stmt_bind_param($clear, 's', $platform);
        mysqli_stmt_execute($clear);
        mysqli_stmt_close($clear);
    }

    $isCurrent = $setCurrent ? 1 : 0;
    $uploadedBy = (int) $admin['id'];
    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO time_tracker_releases
            (version, platform, filename, file_path, file_size, sha256, notes, is_current, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    mysqli_stmt_bind_param(
        $stmt, 'ssssisssi',
        $version, $platform, $filename, $relFilePath, $fileSize,
        $sha256, $notes, $isCurrent, $uploadedBy
    );
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('DB insert failed: ' . mysqli_error($conn));
    }
    $id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'release' => [
            'id'          => (int) $id,
            'version'     => $version,
            'platform'    => $platform,
            'filename'    => $filename,
            'file_size'   => (int) $fileSize,
            'sha256'      => $sha256,
            'notes'       => $notes,
            'is_current'  => (bool) $isCurrent,
            'uploaded_at' => date('Y-m-d H:i:s'),
        ],
    ]);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    @unlink($absPath);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
