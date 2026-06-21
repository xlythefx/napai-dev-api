<?php
/**
 * Production AI endpoint for Slideshow Studio (Claude via the Anthropic API).
 * Mirrors the dev-only Vite Claude bridge (/api/slideshow-edit) so the AI chat
 * works on the live site. In local dev the frontend targets the CLI bridge instead.
 *
 * POST { instruction, scope, targetSlideId, deck (sanitized), history, model }
 * -> { ok: true, summary, slides: [...] }  |  { ok: false, error }
 */
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/../../../config/anthropic.php'; // sets $anthropic_key

ob_end_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { slideshow_jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405); exit; }

if (!is_string($anthropic_key) || $anthropic_key === '') {
    slideshow_jsonResponse(['ok' => false, 'error' => 'The AI assistant is not configured on the server (missing ANTHROPIC_API_KEY).'], 200);
    exit;
}

$input       = json_decode(file_get_contents('php://input'), true) ?: [];
$instruction = trim($input['instruction'] ?? '');
$scope       = ($input['scope'] ?? 'slide') === 'deck' ? 'deck' : 'slide';
$targetId    = (string) ($input['targetSlideId'] ?? '');
$deck        = $input['deck'] ?? null;
$history     = is_array($input['history'] ?? null) ? $input['history'] : [];
$images      = is_array($input['images'] ?? null) ? $input['images'] : [];
$modelShort  = strtolower((string) ($input['model'] ?? 'haiku'));

if ($instruction === '' || !is_array($deck) || !isset($deck['slides'])) {
    slideshow_jsonResponse(['ok' => false, 'error' => 'instruction and deck are required'], 400);
    exit;
}

$MODEL_MAP = [
    'haiku'  => 'claude-haiku-4-5',
    'sonnet' => 'claude-sonnet-4-6',
    'opus'   => 'claude-opus-4-8',
];
$model = $MODEL_MAP[$modelShort] ?? $MODEL_MAP['haiku'];

$prompt = slideshow_buildDeckEditPrompt($instruction, $scope, $targetId, $deck, $history);

// Attach screenshots/images as vision content blocks when provided.
$content = $prompt;
if (!empty($images)) {
    $blocks = [['type' => 'text', 'text' => $prompt]];
    foreach ($images as $dataUrl) {
        if (preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.*)$/s', (string) $dataUrl, $m)) {
            $blocks[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $m[1], 'data' => $m[2]]];
        }
    }
    $content = $blocks;
}

$payload = json_encode([
    'model'      => $model,
    'max_tokens' => 16000,
    'messages'   => [['role' => 'user', 'content' => $content]],
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'content-type: application/json',
        'x-api-key: ' . $anthropic_key,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT        => 120,
]);
$raw  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr = curl_error($ch);
curl_close($ch);

if ($raw === false) {
    slideshow_jsonResponse(['ok' => false, 'error' => 'Could not reach the AI service: ' . $cerr], 200);
    exit;
}

$resp = json_decode($raw, true);
if ($code !== 200 || !is_array($resp)) {
    $msg = $resp['error']['message'] ?? ('AI service returned status ' . $code);
    slideshow_jsonResponse(['ok' => false, 'error' => $msg], 200);
    exit;
}

// Concatenate all text blocks from the response.
$text = '';
foreach (($resp['content'] ?? []) as $block) {
    if (($block['type'] ?? '') === 'text') $text .= $block['text'];
}

$parsed = slideshow_extractJson($text);
if ($parsed === null) {
    slideshow_jsonResponse(['ok' => false, 'error' => 'The AI did not return valid JSON.'], 200);
    exit;
}

slideshow_jsonResponse([
    'ok'      => true,
    'summary' => isset($parsed['summary']) && is_string($parsed['summary']) ? $parsed['summary'] : 'Updated the slideshow.',
    'slides'  => is_array($parsed['slides'] ?? null) ? $parsed['slides'] : [],
]);
