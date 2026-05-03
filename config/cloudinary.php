<?php
/**
 * Cloudinary config for video uploads.
 * Set via:
 * 1. Environment variables: CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET
 * 2. Or create config/cloudinary_key.php that returns ['cloud_name'=>'...', 'api_key'=>'...', 'api_secret'=>'...']
 */
$cloudinary_cloud_name = getenv('CLOUDINARY_CLOUD_NAME');
$cloudinary_api_key = getenv('CLOUDINARY_API_KEY');
$cloudinary_api_secret = getenv('CLOUDINARY_API_SECRET');

if (empty($cloudinary_cloud_name) && file_exists(__DIR__ . '/cloudinary_key.php')) {
    $keys = include __DIR__ . '/cloudinary_key.php';
    if (is_array($keys)) {
        $cloudinary_cloud_name = $keys['cloud_name'] ?? '';
        $cloudinary_api_key = $keys['api_key'] ?? '';
        $cloudinary_api_secret = $keys['api_secret'] ?? '';
    }
}

$cloudinary_configured = !empty($cloudinary_cloud_name) && !empty($cloudinary_api_key) && !empty($cloudinary_api_secret);
