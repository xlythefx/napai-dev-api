<?php
/**
 * TikTok Affiliate - Stitch scene videos with Shotstack
 * POST body: { "video_urls": ["https://...", "https://..."] }
 */

error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/../../../config/shotstack.php';

ob_end_clean();

set_time_limit(320);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    http_response_code(405);
    exit;
}

if (!$shotstack_configured) {
    echo json_encode(['error' => 'Shotstack API key not configured. Create napai-api/config/shotstack_key.php']);
    http_response_code(500);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$videoUrls = $input['video_urls'] ?? [];

if (!is_array($videoUrls) || count($videoUrls) < 1) {
    echo json_encode(['error' => 'video_urls must be a non-empty array of video URLs']);
    http_response_code(400);
    exit;
}

$videoUrls = array_values(array_filter(array_map('trim', $videoUrls)));
foreach ($videoUrls as $u) {
    if (!filter_var($u, FILTER_VALIDATE_URL) || !preg_match('/^https:\/\//', $u)) {
        echo json_encode(['error' => 'Invalid URL: ' . substr($u, 0, 80)]);
        http_response_code(400);
        exit;
    }
}

// Build Shotstack timeline: sequential clips, 5s each (Kling default)
$clips = [];
$start = 0;
$clipLength = 5; // Kling outputs ~5s per scene
foreach ($videoUrls as $url) {
    $clips[] = [
        'asset' => [
            'type' => 'video',
            'src' => $url,
        ],
        'start' => $start,
        'length' => $clipLength,
    ];
    $start += $clipLength;
}

$edit = [
    'timeline' => [
        'tracks' => [
            ['clips' => $clips],
        ],
    ],
    'output' => [
        'format' => 'mp4',
        'size' => [
            'width' => 1080,
            'height' => 1920,
        ],
    ],
];

$ch = curl_init('https://api.shotstack.io/edit/stage/render');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($edit),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'x-api-key: ' . $shotstack_api_key,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode < 200 || $httpCode >= 300) {
    $err = json_decode($response, true);
    $errMsg = $err['message'] ?? $err['error'] ?? null;
    if (is_array($errMsg)) {
        $errMsg = json_encode($errMsg);
    }
    if (!$errMsg) {
        $errMsg = 'Shotstack render request failed: ' . substr($response, 0, 500);
    }
    echo json_encode([
        'error' => $errMsg,
        'logs' => $err['errors'] ?? $err['response'] ?? (is_string($response) ? [$response] : null),
    ]);
    http_response_code($httpCode >= 400 ? $httpCode : 500);
    exit;
}

$data = json_decode($response, true);
$renderId = $data['response']['id'] ?? null;

if (!$renderId) {
    echo json_encode(['error' => 'Invalid Shotstack response', 'raw' => $response]);
    http_response_code(500);
    exit;
}

// Poll for completion
$maxWait = 300;
$pollInterval = 5;
$elapsed = 0;

while ($elapsed < $maxWait) {
    sleep($pollInterval);
    $elapsed += $pollInterval;

    $ch = curl_init('https://api.shotstack.io/edit/stage/render/' . $renderId);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'x-api-key: ' . $shotstack_api_key,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $statusRes = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $statusData = json_decode($statusRes, true);
    $status = $statusData['response']['status'] ?? '';

    if ($status === 'done') {
        $finalUrl = $statusData['response']['url'] ?? null;
        if ($finalUrl) {
            echo json_encode([
                'success' => true,
                'video_url' => $finalUrl,
            ]);
            exit;
        }
    }

    if ($status === 'failed') {
        $errMsg = $statusData['response']['error'] ?? 'Shotstack render failed';
        echo json_encode([
            'error' => $errMsg,
            'logs' => null,
        ]);
        http_response_code(500);
        exit;
    }
}

echo json_encode([
    'error' => 'Shotstack render timed out after ' . $maxWait . ' seconds',
    'logs' => [],
]);
http_response_code(504);
exit;
