<?php

function vtpl_jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data);
}

/**
 * Validate the template_json blob shape. Two kinds share this table:
 *   - TSX template (default): `code` (TSX string) + `propsSchema` array.
 *   - Scene preset (`kind:"scene"`): a saved layer-graph `scene` (no code).
 * Validation stays light — the client sanitizes/compiles before it reaches the API.
 */
function vtpl_validTemplateJson($tj): bool {
    if (!is_array($tj)) return false;
    if (($tj['kind'] ?? '') === 'scene') {
        return isset($tj['scene']) && is_array($tj['scene']);
    }
    return isset($tj['code']) && is_string($tj['code']) && trim($tj['code']) !== ''
        && isset($tj['propsSchema']) && is_array($tj['propsSchema']);
}
