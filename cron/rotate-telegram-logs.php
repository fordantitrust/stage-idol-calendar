<?php
/**
 * Telegram Log Rotation Script
 *
 * - Renames telegram-cron.log to telegram-cron-YYYY-MM-DD.log (daily rotation)
 * - Deletes archived logs older than 7 days
 *
 * SECURITY: CLI only — must not be called via HTTP
 *
 * Cron entry (run daily at midnight):
 *   0 0 * * * php /path/to/cron/rotate-telegram-logs.php >> /path/to/cache/logs/rotate-cron.log 2>&1
 */

// Block HTTP access — CLI only
if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cli-server') {
    http_response_code(403);
    die('Forbidden: This script can only be executed from command line (cron job)');
}

date_default_timezone_set('Asia/Bangkok');

define('ROTATE_LOG_DIR', __DIR__ . '/../cache/logs');
define('ROTATE_LOG_FILE', ROTATE_LOG_DIR . '/telegram-cron.log');
define('ROTATE_RETENTION_DAYS', 7);

$rotated = 0;
$deleted  = 0;

// --------------------------------------------------------------------------
// Helper
// --------------------------------------------------------------------------
function rotate_echo(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
}

// --------------------------------------------------------------------------
// Step 1 — Rotate active log
// --------------------------------------------------------------------------
if (file_exists(ROTATE_LOG_FILE) && filesize(ROTATE_LOG_FILE) > 0) {
    // Use the file's last-modified date as the archive date
    $archiveDate = date('Y-m-d', filemtime(ROTATE_LOG_FILE));
    $dest        = ROTATE_LOG_DIR . '/telegram-cron-' . $archiveDate . '.log';

    // Collision guard: same-date file may already exist from the 10 MB size rotation
    if (file_exists($dest)) {
        $dest = ROTATE_LOG_DIR . '/telegram-cron-' . $archiveDate . '-daily.log';
    }

    if (@rename(ROTATE_LOG_FILE, $dest)) {
        rotate_echo('Rotated: telegram-cron.log → ' . basename($dest));
        $rotated = 1;
    } else {
        rotate_echo('ERROR: Failed to rename telegram-cron.log → ' . basename($dest));
    }
} else {
    rotate_echo('No active log to rotate (file missing or empty)');
}

// --------------------------------------------------------------------------
// Step 2 — Delete archived logs older than ROTATE_RETENTION_DAYS days
// --------------------------------------------------------------------------
$cutoff = time() - (ROTATE_RETENTION_DAYS * 86400);
$files  = glob(ROTATE_LOG_DIR . '/telegram-cron-*.log') ?: [];

foreach ($files as $file) {
    if (filemtime($file) < $cutoff) {
        if (@unlink($file)) {
            rotate_echo('Deleted: ' . basename($file));
            $deleted++;
        } else {
            rotate_echo('ERROR: Failed to delete ' . basename($file));
        }
    }
}

if ($deleted === 0) {
    rotate_echo('No archived logs older than ' . ROTATE_RETENTION_DAYS . ' days found');
}

// --------------------------------------------------------------------------
// Summary
// --------------------------------------------------------------------------
rotate_echo('Log rotation complete (' . $rotated . ' rotated, ' . $deleted . ' deleted)');
exit(0);
