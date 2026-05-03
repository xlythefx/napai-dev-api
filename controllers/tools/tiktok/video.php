<?php
/**
 * TikTok Affiliate - Image to Video via Fal AI Kling
 * POST body: { "image": "data:image/...;base64,...", "prompt": "..." }
 */

error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/../../../config/fal.php';

ob_end_clean();

// Video generation polls Fal for up to 180s; PHP must not timeout first
set_time_limit(200);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    http_response_code(405);
    exit;
}

if (empty($fal_key)) {
    echo json_encode(['error' => 'Fal API key not configured. Create napai-api/config/fal_key.php']);
    http_response_code(500);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$imageInput = $input['image'] ?? $input['image_url'] ?? '';
$prompt = trim($input['prompt'] ?? '');
$duration = in_array($input['duration'] ?? '', ['5', '10']) ? $input['duration'] : '10';
$aspectRatio = in_array($input['aspect_ratio'] ?? '', ['16:9', '9:16', '1:1']) ? $input['aspect_ratio'] : '9:16';

if (empty($imageInput)) {
    echo json_encode(['error' => 'Missing image (base64 data URI) or image_url (https URL)']);
    http_response_code(400);
    exit;
}

$imageUrl = $imageInput;
if (preg_match('/^data:image\/\w+;base64,/', $imageInput)) {
    $imageUrl = $imageInput;
} elseif (filter_var($imageInput, FILTER_VALIDATE_URL) && preg_match('/^https:\/\//', $imageInput)) {
    $imageUrl = $imageInput;
} else {
    echo json_encode(['error' => 'Invalid image. Use data:image/png;base64,... or https:// URL']);
    http_response_code(400);
    exit;
}

if (empty($prompt)) {
    $prompt = 'A person showcasing a product in a professional TikTok affiliate style. Smooth cinematic motion, engaging presentation, clean background.';
}

$modelId = 'fal-ai/kling-video/v2.1/pro/image-to-video';
$falUrl = 'https://queue.fal.run/' . $modelId;

$payload = [
    'prompt' => $prompt,
    'image_url' => $imageUrl,
    'duration' => $duration,
    'aspect_ratio' => $aspectRatio,
];

$ch = curl_init($falUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Key ' . $fal_key,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 && $httpCode !== 202) {
    $err = json_decode($response, true);
    echo json_encode([
        'error' => $err['detail'] ?? $err['message'] ?? 'Fal API error: ' . substr($response, 0, 500),
        'logs' => $err['logs'] ?? null,
    ]);
    http_response_code($httpCode >= 400 ? $httpCode : 500);
    exit;
}

$data = json_decode($response, true);
$requestId = $data['request_id'] ?? null;
$statusUrl = $data['status_url'] ?? null;
$responseUrl = $data['response_url'] ?? null;

if (!$requestId || !$statusUrl) {
    echo json_encode(['error' => 'Invalid Fal API response', 'raw' => $response]);
    http_response_code(500);
    exit;
}

$maxWait = 180;
$pollInterval = 3;
$elapsed = 0;

while ($elapsed < $maxWait) {
    sleep($pollInterval);
    $elapsed += $pollInterval;

    $ch = curl_init($statusUrl . '?logs=1');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Key ' . $fal_key],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $statusRes = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $statusData = json_decode($statusRes, true);
    $status = $statusData['status'] ?? '';

    if ($status === 'COMPLETED') {
        $ch = curl_init($responseUrl);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Key ' . $fal_key],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $resultRes = curl_exec($ch);
        curl_close($ch);
        $resultData = json_decode($resultRes, true);
        $video = $resultData['response']['video'] ?? $resultData['video'] ?? null;
        if ($video && isset($video['url'])) {
            echo json_encode(['success' => true, 'video_url' => $video['url']]);
            exit;
        }
        echo json_encode(['error' => 'No video in response', 'logs' => null]);
        http_response_code(500);
        exit;
    }

    if ($status === 'FAILED' || $statusCode >= 400) {
        $logs = $statusData['logs'] ?? [];
        $logMessages = array_map(fn($l) => $l['message'] ?? json_encode($l), is_array($logs) ? $logs : []);
        echo json_encode([
            'error' => $statusData['detail'] ?? $statusData['message'] ?? 'Video generation failed',
            'logs' => $logMessages,
        ]);
        http_response_code(500);
        exit;
    }
}

echo json_encode([
    'error' => 'Video generation timed out after ' . $maxWait . ' seconds',
    'logs' => [],
]);
http_response_code(504);
exit;
