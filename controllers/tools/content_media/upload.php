<?php
/**
 * Upload an image or video for the Video Studio.
 * POST multipart: file field "file", form field: user_email
 * -> { success, url, kind, name }
 *
 * Stores under napai-api/media/content/<emailhash>/ and returns an absolute URL
 * derived from the request host, so the Remotion render service can fetch the
 * media by URL (dev: localhost; prod: nap-ai.com). The media/ tree has no
 * php_flag .htaccess (unlike uploads/), so static files serve directly.
 */
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../../../config/cors.php';

ob_end_clean();
header('Content-Type: application/json');

function media_respond(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') media_respond(['error' => 'Method not allowed'], 405);

$userEmail = trim($_POST['user_email'] ?? '');
if ($userEmail === '') media_respond(['error' => 'user_email required'], 400);

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    media_respond(['error' => 'file required'], 400);
}

$origName = (string) ($_FILES['file']['name'] ?? 'media');
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$imageExts = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'avif'];
$videoExts = ['mp4', 'webm', 'mov', 'm4v'];
$audioExts = ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'oga', 'flac'];
if (!in_array($ext, array_merge($imageExts, $videoExts, $audioExts), true)) {
    media_respond(['error' => 'Unsupported file type'], 400);
}
$kind = in_array($ext, $videoExts, true) ? 'video' : (in_array($ext, $audioExts, true) ? 'audio' : 'image');

// Storage: napai-api/media/content/<emailhash>/<uniqid>.<ext>
$sub = substr(md5($userEmail), 0, 12);
$absDir = __DIR__ . '/../../../media/content/' . $sub;
if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
    media_respond(['error' => 'Failed to create media directory'], 500);
}

$filename = uniqid('m_', true) . '.' . $ext;
$absPath = $absDir . '/' . $filename;
if (!move_uploaded_file($_FILES['file']['tmp_name'], $absPath)) {
    media_respond(['error' => 'Failed to save uploaded file'], 500);
}

// Absolute URL from the request: scheme://host + <apiRoot> + /media/content/...
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$pos = strpos($scriptName, '/controllers/');
$apiRoot = $pos !== false ? substr($scriptName, 0, $pos) : '';
$scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$url = $scheme . '://' . $host . $apiRoot . '/media/content/' . $sub . '/' . $filename;

media_respond([
    'success' => true,
    'url'     => $url,
    'kind'    => $kind,
    'name'    => $origName,
]);
