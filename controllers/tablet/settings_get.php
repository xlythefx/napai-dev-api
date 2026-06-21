<?php
/**
 * GET /controllers/tablet/settings_get.php?requester_email=<admin>
 *
 * Returns the full tablet_settings KV row set as a typed object the React
 * admin page can render directly. Values are normalized to bool where the
 * frontend expects a toggle.
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/tablet.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'GET') tablet_json_response(405, ['ok' => false, 'error' => 'method_not_allowed']);
if (!$conn) tablet_json_response(500, ['ok' => false, 'error' => 'db_error']);

require_admin($conn, tablet_requester_email());

echo json_encode([
    'ok' => true,
    'settings' => [
        'email_keys_enabled' => tablet_email_enabled($conn),
        'is_maintenance'     => tablet_is_maintenance($conn),
    ],
]);
