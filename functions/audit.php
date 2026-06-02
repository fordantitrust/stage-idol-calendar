<?php
/**
 * Admin Audit Log — Phase 1 (File-based)
 * v14.0.0
 *
 * Appends JSON Lines records to cache/logs/admin-audit-YYYY-MM-DD.log
 * Pattern mirrors Telegram log (v5.2.0/v5.3.0) — no DB, no breaking change.
 * Never throws — failures go to error_log() only.
 */

// ─── Redaction key list ──────────────────────────────────────────────────────

const AUDIT_REDACT_KEYS = [
    'password', 'current_password', 'new_password', 'password_hash',
    'twofa_secret', 'twofa_backup_codes', 'twofa_setup_secret',
    'csrf_token',
    'smtp_password',
    'telegram_bot_token', 'webhook_secret',
];

// ─── Request-scoped context (populated by audit_api_context()) ───────────────

$_AUDIT_CTX = [
    'request_id' => null,
    'ip'         => null,
    'ua'         => null,
];

// =============================================================================
// PUBLIC API
// =============================================================================

/**
 * Bootstrap audit context — creates request_id, reads IP/UA.
 * Call once at the top of admin/api.php or any HTTP entry point.
 * Safe to call multiple times; subsequent calls are no-ops.
 */
function audit_api_context(): void {
    global $_AUDIT_CTX;
    if ($_AUDIT_CTX['request_id'] !== null) {
        return;
    }
    try {
        $_AUDIT_CTX['request_id'] = bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        $_AUDIT_CTX['request_id'] = sprintf('%08x%08x', mt_rand(), mt_rand());
    }
    $_AUDIT_CTX['ip'] = $_SERVER['REMOTE_ADDR'] ?? null;
    $_AUDIT_CTX['ua'] = isset($_SERVER['HTTP_USER_AGENT'])
        ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 500)
        : null;
}

/**
 * Core audit write — appends 1 JSON line to the daily log file.
 * Never throws; failures are sent to error_log().
 *
 * @param array $params {
 *   'action':          string   (required)
 *   'outcome':         string   (required) — success/failure/blocked/partial
 *   'actor_user_id':   ?int
 *   'actor_username':  ?string
 *   'actor_role':      ?string
 *   'error_code':      ?string
 *   'entity_type':     ?string
 *   'entity_id':       ?int
 *   'entity_label':    ?string
 *   'metadata':        ?array
 * }
 * @return bool  true on successful write
 */
function audit_log(array $params): bool {
    global $_AUDIT_CTX;

    if (($_AUDIT_CTX['request_id'] ?? null) === null) {
        audit_api_context();
    }

    $outcome = $params['outcome'] ?? 'success';

    $record = [
        'ts'           => date('Y-m-d H:i:s'),
        'actor_id'     => isset($params['actor_user_id'])
                          ? ($params['actor_user_id'] === null ? null : (int)$params['actor_user_id'])
                          : null,
        'actor'        => isset($params['actor_username']) ? (string)$params['actor_username'] : null,
        'actor_role'   => isset($params['actor_role'])    ? (string)$params['actor_role']    : null,
        'action'       => (string)($params['action'] ?? ''),
        'entity_type'  => isset($params['entity_type'])  ? (string)$params['entity_type']  : null,
        'entity_id'    => isset($params['entity_id'])
                          ? ($params['entity_id'] === null ? null : (int)$params['entity_id'])
                          : null,
        'entity_label' => isset($params['entity_label']) ? (string)$params['entity_label'] : null,
        'outcome'      => $outcome,
        'error_code'   => ($outcome === 'success') ? null : (isset($params['error_code']) ? (string)$params['error_code'] : null),
        'ip'           => $_AUDIT_CTX['ip'] ?? null,
        'ua'           => $_AUDIT_CTX['ua'] ?? null,
        'request_id'   => $_AUDIT_CTX['request_id'] ?? null,
    ];

    if (!empty($params['metadata']) && is_array($params['metadata'])) {
        $record['metadata'] = audit_redact($params['metadata']);
    }

    return audit_write_to_file($record);
}

/**
 * Helper for admin action success — reads actor from active session.
 */
function audit_admin_success(string $action, ?string $entityType, ?int $entityId,
                              ?string $entityLabel, array $metadata = []): bool {
    return audit_log([
        'action'         => $action,
        'outcome'        => 'success',
        'actor_user_id'  => isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : null,
        'actor_username' => $_SESSION['admin_username'] ?? null,
        'actor_role'     => $_SESSION['admin_role'] ?? null,
        'entity_type'    => $entityType,
        'entity_id'      => $entityId,
        'entity_label'   => $entityLabel,
        'metadata'       => $metadata ?: null,
    ]);
}

/**
 * Helper for admin action failure — error_code must be in vocabulary.
 */
function audit_admin_failure(string $action, string $errorCode, ?string $entityType,
                              ?int $entityId, ?string $entityLabel, array $metadata = []): bool {
    return audit_log([
        'action'         => $action,
        'outcome'        => 'failure',
        'error_code'     => $errorCode,
        'actor_user_id'  => isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : null,
        'actor_username' => $_SESSION['admin_username'] ?? null,
        'actor_role'     => $_SESSION['admin_role'] ?? null,
        'entity_type'    => $entityType,
        'entity_id'      => $entityId,
        'entity_label'   => $entityLabel,
        'metadata'       => $metadata ?: null,
    ]);
}

/**
 * Read recent audit records for the viewer UI.
 * Reads daily log files for the last $days days.
 *
 * @param int   $days    Number of recent days to scan (default 7)
 * @param array $filters { 'action' => string, 'actor' => string, 'outcome' => string }
 * @return array { 'files' => string[], 'records' => array[] }  records newest-first
 */
function audit_read_recent(int $days = 7, array $filters = []): array {
    $logDir = _audit_log_dir();
    $files  = [];
    for ($i = 0; $i < $days; $i++) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $path = $logDir . "/admin-audit-{$date}.log";
        if (file_exists($path)) {
            $files[] = "admin-audit-{$date}.log";
        }
    }

    $records = [];
    foreach ($files as $f) {
        $content = @file_get_contents($logDir . '/' . $f);
        if ($content === false) {
            continue;
        }
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $rec = @json_decode($line, true);
            if (!is_array($rec)) {
                continue;
            }
            if (!empty($filters['action']) && ($rec['action'] ?? '') !== $filters['action']) {
                continue;
            }
            if (!empty($filters['actor']) && stripos($rec['actor'] ?? '', $filters['actor']) === false) {
                continue;
            }
            if (!empty($filters['outcome']) && ($rec['outcome'] ?? '') !== $filters['outcome']) {
                continue;
            }
            $records[] = $rec;
        }
    }

    return ['files' => $files, 'records' => array_reverse($records)];
}

// =============================================================================
// INTERNAL HELPERS (used by cron/rotate-admin-audit-logs.php too)
// =============================================================================

/**
 * Atomic append to today's audit log file with LOCK_EX.
 */
function audit_write_to_file(array $record): bool {
    $logDir = _audit_log_dir();

    if (!is_dir($logDir)) {
        if (!@mkdir($logDir, 0755, true)) {
            error_log('[audit_log] Cannot create log dir: ' . $logDir
                . ' action=' . ($record['action'] ?? '') . ' entity=' . ($record['entity_type'] ?? ''));
            return false;
        }
    }

    $logFile = $logDir . '/admin-audit-' . date('Y-m-d') . '.log';
    $line    = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

    $result = @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    if ($result === false) {
        error_log('[audit_log] Write failed: ' . $logFile
            . ' action=' . ($record['action'] ?? '') . ' actor=' . ($record['actor'] ?? ''));
        return false;
    }
    return true;
}

/**
 * Recursively redact sensitive keys from a metadata array.
 * Replaces values with "[REDACTED]".
 */
function audit_redact(array $data): array {
    static $keys = null;
    if ($keys === null) {
        $keys = defined('AUDIT_REDACT_KEYS') ? AUDIT_REDACT_KEYS : [
            'password', 'current_password', 'new_password', 'password_hash',
            'twofa_secret', 'twofa_backup_codes', 'twofa_setup_secret',
            'csrf_token', 'smtp_password',
            'telegram_bot_token', 'webhook_secret',
        ];
    }
    foreach ($data as $k => $v) {
        if (in_array($k, $keys, true)) {
            $data[$k] = '[REDACTED]';
        } elseif (is_array($v)) {
            $data[$k] = audit_redact($v);
        }
    }
    return $data;
}

/**
 * Delete archived audit log files older than $retentionDays.
 * Called by cron/rotate-admin-audit-logs.php.
 *
 * @return int  Number of files deleted
 */
function audit_rotate_old_files(int $retentionDays): int {
    $logDir = _audit_log_dir();
    if (!is_dir($logDir)) {
        return 0;
    }
    $cutoff  = time() - ($retentionDays * 86400);
    $deleted = 0;
    foreach (glob($logDir . '/admin-audit-*.log') ?: [] as $file) {
        if (@filemtime($file) < $cutoff) {
            if (@unlink($file)) {
                $deleted++;
            }
        }
    }
    return $deleted;
}

/** @internal */
function _audit_log_dir(): string {
    return defined('ADMIN_AUDIT_LOG_DIR')
        ? ADMIN_AUDIT_LOG_DIR
        : (dirname(__DIR__) . '/cache/logs');
}
