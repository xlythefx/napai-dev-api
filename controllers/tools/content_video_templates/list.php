<?php
/**
 * List all video templates (one shared/global library).
 * GET (no params required)
 * -> { templates: [{id, name, description, user_email, template_json, created_at, updated_at}] }
 *
 * Unlike content_templates/list.php (which strips canvas_json), this returns the
 * full template_json: the client renders live Remotion previews from `code` +
 * `previewProps`, so it needs the blob in the list.
 */
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/utils.php';

ob_end_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if (!$conn) { vtpl_jsonResponse(['error' => 'Database connection failed'], 500); exit; }

$res = mysqli_query(
    $conn,
    'SELECT id, user_email, name, description, template_json, created_at, updated_at
     FROM content_video_templates ORDER BY updated_at DESC'
);

$templates = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $row['id']            = (int) $row['id'];
        $row['template_json'] = json_decode($row['template_json'], true);
        $templates[]          = $row;
    }
}

vtpl_jsonResponse(['templates' => $templates]);
