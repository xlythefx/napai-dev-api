<?php

function rag_chunkText(string $text, int $chunkSize = 500, int $overlap = 50): array {
    $trimmed = trim($text);
    if ($trimmed === '') return [];
    $chunks = [];
    $start = 0;
    $len = mb_strlen($trimmed);
    while ($start < $len) {
        $end = $start + $chunkSize;
        if ($end < $len) {
            $lastSpace = mb_strrpos(mb_substr($trimmed, 0, $end + 1), ' ');
            if ($lastSpace !== false && $lastSpace > $start) {
                $end = $lastSpace;
            }
        }
        $chunk = trim(mb_substr($trimmed, $start, $end - $start));
        if ($chunk !== '') $chunks[] = $chunk;
        $start = $end - $overlap;
        if ($start >= $len) break;
    }
    return $chunks;
}

function rag_cosineSimilarity(array $a, array $b): float {
    if (count($a) !== count($b)) return 0.0;
    $dot = 0;
    $normA = 0;
    $normB = 0;
    foreach ($a as $i => $v) {
        $dot += $v * ($b[$i] ?? 0);
        $normA += $v * $v;
        $normB += ($b[$i] ?? 0) ** 2;
    }
    $denom = sqrt($normA) * sqrt($normB);
    return $denom === 0.0 ? 0.0 : $dot / $denom;
}

function rag_jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data);
}
