<?php
/**
 * TikTok Affiliate - Image Edit via Fal AI Nano Banana Edit
 * POST body: { "image_urls": ["data:image/...;base64,...", ...], "prompt": "...", "aspect_ratio": "9:16" }
 * Combines model + background + products with a per-scene prompt
 */

error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/../../../config/fal.php';

ob_end_clean();
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
$imageUrls = $input['image_urls'] ?? [];
$prompt = trim($input['prompt'] ?? '');
$aspectRatio = in_array($input['aspect_ratio'] ?? '', ['auto', '21:9', '16:9', '3:2', '4:3', '5:4', '1:1', '4:5', '3:4', '2:3', '9:16']) ? $input['aspect_ratio'] : '9:16';

if (empty($prompt)) {
    echo json_encode(['error' => 'Missing prompt']);
    http_response_code(400);
    exit;
}

if (empty($imageUrls) || !is_array($imageUrls)) {
    echo json_encode(['error' => 'Missing image_urls (array of base64 data URIs)']);
    http_response_code(400);
    exit;
}

$validUrls = [];
foreach ($imageUrls as $url) {
    if (is_string($url) && preg_match('/^data:image\/\w+;base64,/', $url)) {
        $validUrls[] = $url;
    }
}
if (empty($validUrls)) {
    echo json_encode(['error' => 'Invalid image_urls. Use data:image/png;base64,... format']);
    http_response_code(400);
    exit;
}

$modelId = 'fal-ai/nano-banana/edit';
$falUrl = 'https://queue.fal.run/' . $modelId;

$payload = [
    'prompt' => $prompt,
    'image_urls' => $validUrls,
    'aspect_ratio' => $aspectRatio,
    'num_images' => 1,
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

$maxWait = 90;
$pollInterval = 2;
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
        $images = $resultData['response']['images'] ?? $resultData['images'] ?? [];
        if (!empty($images) && isset($images[0]['url'])) {
            echo json_encode(['success' => true, 'image_url' => $images[0]['url']]);
            exit;
        }
        echo json_encode(['error' => 'No image in response', 'logs' => null]);
        http_response_code(500);
        exit;
    }

    if ($status === 'FAILED' || $statusCode >= 400) {
        $logs = $statusData['logs'] ?? [];
        $logMessages = array_map(fn($l) => $l['message'] ?? json_encode($l), is_array($logs) ? $logs : []);
        echo json_encode([
            'error' => $statusData['detail'] ?? $statusData['message'] ?? 'Image edit failed',
            'logs' => $logMessages,
        ]);
        http_response_code(500);
        exit;
    }
}

echo json_encode([
    'error' => 'Image edit timed out after ' . $maxWait . ' seconds',
    'logs' => [],
]);
http_response_code(504);
exit;
