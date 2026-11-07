<?php
/**
 * Shared audit-trail writer for bank accounts.
 *
 * fin_account_log($conn, [
 *   'account_id'       => int|null,
 *   'account_name'     => string|null,
 *   'action'           => 'create'|'update'|'archive'|'restore'|'delete',
 *   'changes'          => array|null,   // [['field'=>..,'from'=>..,'to'=>..], ...]
 *   'balance_before'   => float|null,
 *   'balance_after'    => float|null,   // delta is derived from before/after
 *   'currency'         => string|null,
 *   'note'             => string|null,  // optional reason
 *   'changed_by'       => int|null,
 *   'changed_by_email' => string|null,
 * ]);
 *
 * Best-effort: never throws — a failed log write must not break the mutation.
 */
function fin_account_log($conn, array $d): void {
    $changesJson = (!empty($d['changes']) && is_array($d['changes']))
        ? json_encode(array_values($d['changes']), JSON_UNESCAPED_UNICODE)
        : null;

    $before = array_key_exists('balance_before', $d) && $d['balance_before'] !== null ? (float) $d['balance_before'] : null;
    $after  = array_key_exists('balance_after',  $d) && $d['balance_after']  !== null ? (float) $d['balance_after']  : null;
    $delta  = ($before !== null && $after !== null) ? ($after - $before) : null;

    $accId   = isset($d['account_id']) && $d['account_id'] !== null ? (int) $d['account_id'] : null;
    $name    = isset($d['account_name']) ? mb_substr((string) $d['account_name'], 0, 128) : null;
    $action  = (string) ($d['action'] ?? 'update');
    $cur     = isset($d['currency']) ? mb_substr((string) $d['currency'], 0, 8) : null;
    $note    = (isset($d['note']) && trim((string) $d['note']) !== '') ? mb_substr(trim((string) $d['note']), 0, 500) : null;
    $by      = isset($d['changed_by']) && $d['changed_by'] !== null ? (int) $d['changed_by'] : null;
    $byEmail = isset($d['changed_by_email']) ? mb_substr((string) $d['changed_by_email'], 0, 190) : null;

    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO finance_account_logs
            (account_id, account_name, action, changes, balance_before, balance_after, balance_delta, currency, note, changed_by, changed_by_email)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) return;
    mysqli_stmt_bind_param(
        $stmt, 'isssdddssis',
        $accId, $name, $action, $changesJson, $before, $after, $delta, $cur, $note, $by, $byEmail
    );
    @mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Build a changes array by diffing an old DB row against the new values.
 * $fields maps db_column => new_value (already normalised). Only columns whose
 * (string-cast) value actually changed are included.
 */
function fin_account_diff(array $old, array $new): array {
    $changes = [];
    foreach ($new as $field => $to) {
        $from = $old[$field] ?? null;
        // Normalise numeric compare so 100 vs 100.00 doesn't register as a change
        $numFrom = is_numeric($from) ? (float) $from : null;
        $numTo   = is_numeric($to)   ? (float) $to   : null;
        $same = ($numFrom !== null && $numTo !== null)
            ? abs($numFrom - $numTo) < 0.0000001
            : ((string) $from === (string) $to);
        if (!$same) {
            $changes[] = ['field' => $field, 'from' => $from, 'to' => $to];
        }
    }
    return $changes;
}
