<?php
/**
 * Web Push Log Rotation Script
 *
 * - Renames webpush-cron.log to webpush-cron-YYYY-MM-DD.log (daily rotation)
 * - Deletes archived logs older than 7 days
 *
 * SECURITY: CLI only — blocked via cron/.htaccess (Deny from all)
 *
 * Cron entry (run daily at midnight):
 *   0 0 * * * php /path/to/cron/rotate-webpush-logs.php >> /path/to/cache/logs/rotate-cron.log 2>&1
 */

if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cli-server') {
    http_response_code(403);
    die('Forbidden: This script can only be executed from command line (cron job)');
}

date_default_timezone_set('Asia/Bangkok');

define('WEBPUSH_ROTATE_LOG_DIR', __DIR__ . '/../cache/logs');
define('WEBPUSH_ROTATE_LOG_FILE', WEBPUSH_ROTATE_LOG_DIR . '/webpush-cron.log');
define('WEBPUSH_ROTATE_RETENTION_DAYS', 7);

$rotated = 0;
$deleted  = 0;

function webpush_rotate_echo(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
}

// --------------------------------------------------------------------------
// Step 1 — Rotate active log
// --------------------------------------------------------------------------
if (file_exists(WEBPUSH_ROTATE_LOG_FILE) && filesize(WEBPUSH_ROTATE_LOG_FILE) > 0) {
    $archiveDate = date('Y-m-d', filemtime(WEBPUSH_ROTATE_LOG_FILE));
    $dest        = WEBPUSH_ROTATE_LOG_DIR . '/webpush-cron-' . $archiveDate . '.log';

    // Collision guard: same-date archive may already exist from the 10MB size rotation
    if (file_exists($dest)) {
        $dest = WEBPUSH_ROTATE_LOG_DIR . '/webpush-cron-' . $archiveDate . '-daily.log';
    }

    if (@rename(WEBPUSH_ROTATE_LOG_FILE, $dest)) {
        webpush_rotate_echo('Rotated: webpush-cron.log → ' . basename($dest));
        $rotated = 1;
    } else {
        webpush_rotate_echo('ERROR: Failed to rename webpush-cron.log → ' . basename($dest));
    }
} else {
    webpush_rotate_echo('No active log to rotate (file missing or empty)');
}

// --------------------------------------------------------------------------
// Step 2 — Delete archived logs older than WEBPUSH_ROTATE_RETENTION_DAYS days
// --------------------------------------------------------------------------
$cutoff = time() - (WEBPUSH_ROTATE_RETENTION_DAYS * 86400);
$files  = glob(WEBPUSH_ROTATE_LOG_DIR . '/webpush-cron-*.log') ?: [];

foreach ($files as $file) {
    if (filemtime($file) < $cutoff) {
        if (@unlink($file)) {
            webpush_rotate_echo('Deleted: ' . basename($file));
            $deleted++;
        } else {
            webpush_rotate_echo('ERROR: Failed to delete ' . basename($file));
        }
    }
}

if ($deleted === 0) {
    webpush_rotate_echo('No archived logs older than ' . WEBPUSH_ROTATE_RETENTION_DAYS . ' days found');
}

// --------------------------------------------------------------------------
// Summary
// --------------------------------------------------------------------------
webpush_rotate_echo('Web Push log rotation complete (' . $rotated . ' rotated, ' . $deleted . ' deleted)');
exit(0);
