<?php

function chat_jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data);
}

// Allowed studio surfaces a chat can belong to.
function chat_validType($t): string {
    $t = trim((string) $t);
    return in_array($t, ['video', 'content', 'slideshow', 'template'], true) ? $t : '';
}
