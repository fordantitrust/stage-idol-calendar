<?php
/**
 * Email Log Rotation Script
 *
 * - Renames email.log to email-YYYY-MM-DD.log (daily rotation)
 * - Deletes archived logs older than 7 days
 *
 * SECURITY: CLI only — blocked via cron/.htaccess (Deny from all)
 *
 * Cron entry (run daily at midnight):
 *   0 0 * * * php /path/to/cron/rotate-email-logs.php >> /path/to/cache/logs/rotate-cron.log 2>&1
 */

if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cli-server') {
    http_response_code(403);
    die('Forbidden: This script can only be executed from command line (cron job)');
}

date_default_timezone_set('Asia/Bangkok');

define('EMAIL_ROTATE_LOG_DIR', __DIR__ . '/../cache/logs');
define('EMAIL_ROTATE_LOG_FILE', EMAIL_ROTATE_LOG_DIR . '/email.log');
define('EMAIL_ROTATE_RETENTION_DAYS', 7);

$rotated = 0;
$deleted  = 0;

function email_rotate_echo(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
}

// --------------------------------------------------------------------------
// Step 1 — Rotate active log
// --------------------------------------------------------------------------
if (file_exists(EMAIL_ROTATE_LOG_FILE) && filesize(EMAIL_ROTATE_LOG_FILE) > 0) {
    $archiveDate = date('Y-m-d', filemtime(EMAIL_ROTATE_LOG_FILE));
    $dest        = EMAIL_ROTATE_LOG_DIR . '/email-' . $archiveDate . '.log';

    // Collision guard: if same-date archive already exists
    if (file_exists($dest)) {
        $dest = EMAIL_ROTATE_LOG_DIR . '/email-' . $archiveDate . '-daily.log';
    }

    if (@rename(EMAIL_ROTATE_LOG_FILE, $dest)) {
        email_rotate_echo('Rotated: email.log → ' . basename($dest));
        $rotated = 1;
    } else {
        email_rotate_echo('ERROR: Failed to rename email.log → ' . basename($dest));
    }
} else {
    email_rotate_echo('No active log to rotate (file missing or empty)');
}

// --------------------------------------------------------------------------
// Step 2 — Delete archived logs older than EMAIL_ROTATE_RETENTION_DAYS days
// --------------------------------------------------------------------------
$cutoff = time() - (EMAIL_ROTATE_RETENTION_DAYS * 86400);
$files  = glob(EMAIL_ROTATE_LOG_DIR . '/email-*.log') ?: [];

foreach ($files as $file) {
    if (filemtime($file) < $cutoff) {
        if (@unlink($file)) {
            email_rotate_echo('Deleted: ' . basename($file));
            $deleted++;
        } else {
            email_rotate_echo('ERROR: Failed to delete ' . basename($file));
        }
    }
}

if ($deleted === 0) {
    email_rotate_echo('No archived logs older than ' . EMAIL_ROTATE_RETENTION_DAYS . ' days found');
}

// --------------------------------------------------------------------------
// Summary
// --------------------------------------------------------------------------
email_rotate_echo('Email log rotation complete (' . $rotated . ' rotated, ' . $deleted . ' deleted)');
exit(0);
