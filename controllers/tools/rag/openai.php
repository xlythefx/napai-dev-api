<?php

$config = include __DIR__ . '/config.php';
$apiKey = $config['openai_api_key'] ?? '';

function rag_openaiEmbed(string $text): array {
    global $apiKey;
    $ch = curl_init('https://api.openai.com/v1/embeddings');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'text-embedding-3-small',
            'input' => $text,
        ]),
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($res, true);
    if ($code !== 200 || !isset($data['data'][0]['embedding'])) {
        $msg = $data['error']['message'] ?? "Embeddings API error: $code";
        throw new RuntimeException($msg);
    }
    return $data['data'][0]['embedding'];
}

function rag_openaiEmbedBatch(array $texts): array {
    global $apiKey;
    if (empty($texts)) return [];
    $ch = curl_init('https://api.openai.com/v1/embeddings');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'text-embedding-3-small',
            'input' => $texts,
        ]),
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($res, true);
    if ($code !== 200 || !isset($data['data'])) {
        $msg = $data['error']['message'] ?? "Embeddings API error: $code";
        throw new RuntimeException($msg);
    }
    $sorted = $data['data'];
    usort($sorted, fn($a, $b) => $a['index'] <=> $b['index']);
    return array_map(fn($d) => $d['embedding'], $sorted);
}

function rag_openaiChat(array $messages): string {
    global $apiKey;
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
        ]),
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($res, true);
    if ($code !== 200 || !isset($data['choices'][0]['message']['content'])) {
        $msg = $data['error']['message'] ?? "Chat API error: $code";
        throw new RuntimeException($msg);
    }
    return trim($data['choices'][0]['message']['content']);
}
