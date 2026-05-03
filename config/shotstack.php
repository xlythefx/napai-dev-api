<?php
/**
 * Shotstack config for video stitching.
 * Set via:
 * 1. Environment: SHOTSTACK_API_KEY, SHOTSTACK_OWNER_ID (optional)
 * 2. Or config/shotstack_key.php returning ['api_key'=>'...', 'owner_id'=>'...']
 */
$shotstack_api_key = getenv('SHOTSTACK_API_KEY');
$shotstack_owner_id = getenv('SHOTSTACK_OWNER_ID');

if (empty($shotstack_api_key) && file_exists(__DIR__ . '/shotstack_key.php')) {
    $keys = include __DIR__ . '/shotstack_key.php';
    if (is_array($keys)) {
        $shotstack_api_key = $keys['api_key'] ?? '';
        $shotstack_owner_id = $keys['owner_id'] ?? ($shotstack_owner_id ?: '');
    }
}

$shotstack_configured = !empty($shotstack_api_key);
