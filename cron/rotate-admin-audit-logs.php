<?php
/**
 * Admin Audit Log Rotation Script — v14.0.0
 *
 * - Renames admin-audit-{today}.log to admin-audit-YYYY-MM-DD.log (if not already named with date)
 * - Deletes archived logs older than ADMIN_AUDIT_RETENTION_DAYS (default 30 days)
 *
 * SECURITY: CLI only — blocked via cron/.htaccess (Deny from all)
 *
 * Cron entry (run daily at midnight):
 *   0 0 * * * php /path/to/cron/rotate-admin-audit-logs.php >> /path/to/cache/logs/rotate-cron.log 2>&1
 */

if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cli-server') {
    http_response_code(403);
    die('Forbidden: This script can only be executed from command line (cron job)');
}

// Bootstrap config (loads ADMIN_AUDIT_RETENTION_DAYS + _audit_log_dir())
require_once dirname(__DIR__) . '/config.php';

date_default_timezone_set('Asia/Bangkok');

$logDir       = _audit_log_dir();
$retentionDays = defined('ADMIN_AUDIT_RETENTION_DAYS') ? (int)ADMIN_AUDIT_RETENTION_DAYS : 30;
$deleted      = 0;

function rotate_audit_echo(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
}

// --------------------------------------------------------------------------
// Step 1 — Delete archived logs older than retention period
// --------------------------------------------------------------------------
if (!is_dir($logDir)) {
    rotate_audit_echo('Log directory does not exist: ' . $logDir);
    exit(0);
}

$cutoff = time() - ($retentionDays * 86400);
$files  = glob($logDir . '/admin-audit-*.log') ?: [];

foreach ($files as $file) {
    if (@filemtime($file) < $cutoff) {
        if (@unlink($file)) {
            rotate_audit_echo('Deleted: ' . basename($file));
            $deleted++;
        } else {
            rotate_audit_echo('ERROR: Failed to delete ' . basename($file));
        }
    }
}

if ($deleted === 0) {
    rotate_audit_echo('No audit logs older than ' . $retentionDays . ' days found');
}

// --------------------------------------------------------------------------
// Summary
// --------------------------------------------------------------------------
rotate_audit_echo('Audit log rotation complete (' . $deleted . ' deleted, retention=' . $retentionDays . 'd)');
exit(0);
