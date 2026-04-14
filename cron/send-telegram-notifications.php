<?php
/**
 * Telegram Notification Cron Script
 * Exit code: 0 (always, errors logged to file + stderr)
 *
 * SECURITY: This script must only run via CLI (cron), never via HTTP
 *
 * Logging: All activity logged to cache/logs/telegram-cron.log (rotates at 10MB)
 */

// Block HTTP access - only allow CLI execution
if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cli-server') {
    http_response_code(403);
    die('Forbidden: This script can only be executed from command line (cron job)');
}

require_once __DIR__ . '/../config.php';

// ===== Logging System =====
define('TELEGRAM_LOG_DIR', __DIR__ . '/../cache/logs');
define('TELEGRAM_LOG_FILE', TELEGRAM_LOG_DIR . '/telegram-cron.log');
define('TELEGRAM_LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB

// Ensure log directory exists
if (!is_dir(TELEGRAM_LOG_DIR)) {
    @mkdir(TELEGRAM_LOG_DIR, 0755, true);
}

/**
 * Write log entry to file
 * @param string $level Log level (INFO, WARN, ERROR, DEBUG)
 * @param string $message Log message
 * @param array $context Optional context data
 */
function telegram_log($level, $message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $logLine = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";

    // Write to file
    @file_put_contents(TELEGRAM_LOG_FILE, $logLine, FILE_APPEND | LOCK_EX);

    // Also write to stderr for cron visibility
    fwrite(STDERR, $logLine);

    // Rotate log if too large
    telegram_rotate_log();
}

/**
 * Rotate log file when it exceeds max size
 */
function telegram_rotate_log() {
    if (!file_exists(TELEGRAM_LOG_FILE)) {
        return;
    }

    if (filesize(TELEGRAM_LOG_FILE) > TELEGRAM_LOG_MAX_SIZE) {
        $timestamp = date('Y-m-d-His');
        $rotated = TELEGRAM_LOG_DIR . '/telegram-cron-' . $timestamp . '.log';
        @rename(TELEGRAM_LOG_FILE, $rotated);

        // Keep only last 10 rotated files
        $files = glob(TELEGRAM_LOG_DIR . '/telegram-cron-*.log');
        if (count($files) > 10) {
            usort($files, function($a, $b) { return filemtime($a) - filemtime($b); });
            @unlink($files[0]);
        }
    }
}

// Check if Telegram is enabled
if (!TELEGRAM_ENABLED || !TELEGRAM_BOT_TOKEN) {
    telegram_log('WARN', 'Telegram bot not enabled or token not configured', [
        'enabled' => TELEGRAM_ENABLED,
        'has_token' => !empty(TELEGRAM_BOT_TOKEN)
    ]);
    exit(0);
}

// Get current timestamp for logging
$runTime = date('Y-m-d H:i:s');
telegram_log('INFO', 'Starting Telegram notifications cron');

// Calculate notification window
// Notify programs starting in [now + N - 7.5min, now + N + 7.5min]
// This window (±7.5min) means we notify reliably even if cron is delayed, without duplicates
$notifyBefore = TELEGRAM_NOTIFY_BEFORE_MINUTES * 60; // Convert to seconds
$windowStart = time() + $notifyBefore - 450;  // 7.5 minutes = 450 seconds
$windowEnd = time() + $notifyBefore + 450;

telegram_log('DEBUG', 'Notification window', [
    'notify_before_minutes' => TELEGRAM_NOTIFY_BEFORE_MINUTES,
    'window_start' => date('Y-m-d H:i:s', $windowStart),
    'window_end' => date('Y-m-d H:i:s', $windowEnd)
]);

$notifiedCount = 0;
$skippedCount = 0;
$errorCount = 0;
$summaryCount = 0;
$summarySkippedCount = 0;
$filesProcessed = 0;
$usersWithTelegram = 0;

try {
    // Scan favorites directory
    $favDir = FAVORITES_DIR;
    if (!is_dir($favDir)) {
        throw new Exception("Favorites directory not found: $favDir");
    }

    telegram_log('DEBUG', 'Scanning favorites directory', ['directory' => $favDir]);

    // Scan shard directories (3-char hex directories)
    $shards = scandir($favDir);
    foreach ($shards as $shard) {
        // Skip . and .. and non-directory entries
        if ($shard === '.' || $shard === '..' || !is_dir($favDir . '/' . $shard)) {
            continue;
        }

        // Scan JSON files in this shard directory
        $shardPath = $favDir . '/' . $shard;
        $files = scandir($shardPath);

        foreach ($files as $filename) {
            if (substr($filename, -5) !== '.json') {
                continue;
            }

            $filesProcessed++;
            $filePath = $shardPath . '/' . $filename;
            process_favorites_file($filePath, $windowStart, $windowEnd, $notifiedCount, $skippedCount, $errorCount, $summaryCount, $summarySkippedCount, $usersWithTelegram);
        }
    }

    telegram_log('INFO', 'Cron completed successfully', [
        'files_processed' => $filesProcessed,
        'users_with_telegram' => $usersWithTelegram,
        'notifications_sent' => $notifiedCount,
        'notifications_skipped' => $skippedCount,
        'summaries_sent' => $summaryCount,
        'summaries_skipped' => $summarySkippedCount,
        'errors' => $errorCount
    ]);
    exit(0);

} catch (Exception $e) {
    telegram_log('ERROR', 'Cron exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    exit(0);
}

/**
 * Process a single favorites file
 *
 * @param string $filePath Path to favorites JSON file
 * @param int $windowStart Unix timestamp for immediate notifications
 * @param int $windowEnd Unix timestamp for immediate notifications
 * @param int &$notifiedCount Counter (passed by reference)
 * @param int &$skippedCount Counter (passed by reference)
 * @param int &$errorCount Counter (passed by reference)
 * @param int &$summaryCount Summary notifications sent (passed by reference)
 * @param int &$summarySkippedCount Summary notifications skipped (passed by reference)
 * @param int &$usersWithTelegram Users with Telegram linked (passed by reference)
 */
function process_favorites_file($filePath, $windowStart, $windowEnd, &$notifiedCount, &$skippedCount, &$errorCount, &$summaryCount, &$summarySkippedCount, &$usersWithTelegram) {
    $filename = basename($filePath);

    // Read file with lock
    $fh = @fopen($filePath, 'r');
    if (!$fh) {
        telegram_log('WARN', "Cannot open favorites file: $filename");
        return;
    }

    if (!flock($fh, LOCK_SH)) {
        fclose($fh);
        telegram_log('WARN', "Cannot lock favorites file: $filename");
        return;
    }

    $content = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);

    $favData = json_decode($content, true);
    if (!is_array($favData) || empty($favData['telegram_chat_id']) || empty($favData['artists'])) {
        // No Telegram linked for this user, skip silently
        return;
    }

    // Honor mute and notify settings
    if (!telegram_notify_is_enabled($favData)) {
        telegram_log('DEBUG', "Notifications disabled for user, skipping", ['file' => $filename]);
        return;
    }
    if (telegram_is_muted($favData)) {
        telegram_log('DEBUG', "User is muted, skipping", ['file' => $filename]);
        return;
    }

    $usersWithTelegram++;
    telegram_log('DEBUG', "Processing user with Telegram", ['file' => $filename, 'artists' => count($favData['artists'])]);

    $chatId = $favData['telegram_chat_id'];
    $artistIds = $favData['artists'];
    $telegramNotified = $favData['telegram_notified'] ?? [];

    telegram_log('DEBUG', "User details", ['chat_id' => substr($chatId, 0, 4) . '***', 'artists_count' => count($artistIds)]);

    // Resolve group members (same logic as my.php)
    $allArtistIds = $artistIds;
    try {
        $db = get_db();
        $placeholders = implode(',', array_fill(0, count($artistIds), '?'));
        $stmt = $db->prepare("
            SELECT DISTINCT a.id
            FROM artists a
            WHERE a.group_id IN (SELECT DISTINCT group_id FROM artists WHERE id IN ($placeholders))
                AND a.is_group = 0
        ");
        $stmt->execute($artistIds);
        $groupMembers = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $stmt = null;
        $allArtistIds = array_unique(array_merge($artistIds, $groupMembers));
        telegram_log('DEBUG', "Group members resolved", ['original_count' => count($artistIds), 'total_with_members' => count($allArtistIds)]);
    } catch (Exception $e) {
        telegram_log('ERROR', "Error resolving group members", ['error' => $e->getMessage()]);
    }

    // Query upcoming programs
    try {
        $db = get_db();
        $placeholders = implode(',', array_fill(0, count($allArtistIds), '?'));

        $stmt = $db->prepare("
            SELECT DISTINCT
                p.id, p.title, p.start, p.end, p.location,
                p.program_type, p.stream_url, p.event_id,
                e.name as event_name
            FROM programs p
            JOIN events e ON p.event_id = e.id
            JOIN program_artists pa ON p.id = pa.program_id
            WHERE pa.artist_id IN ($placeholders)
                AND e.is_active = 1
                AND CAST(strftime('%s', p.start) AS INTEGER) BETWEEN :windowStart AND :windowEnd
        ");

        $params = $allArtistIds;
        $params[] = $windowStart;
        $params[] = $windowEnd;

        $stmt->execute(array_merge($allArtistIds, [
            ':windowStart' => $windowStart,
            ':windowEnd' => $windowEnd
        ]));

        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = null;

        telegram_log('DEBUG', "Programs in notification window", ['count' => count($programs), 'window' => $windowStart . '-' . $windowEnd]);

        // Send notifications
        foreach ($programs as $prog) {
            $programId = (int)$prog['id'];

            if (!telegram_should_notify($telegramNotified, $programId)) {
                telegram_log('DEBUG', "Skipping already notified program", ['program_id' => $programId, 'title' => $prog['title']]);
                $skippedCount++;
                continue;
            }

            // Send message
            $message = telegram_format_notification($prog);
            if (telegram_send_message($chatId, $message)) {
                telegram_log('INFO', "Notification sent", [
                    'program_id' => $programId,
                    'title' => $prog['title'],
                    'event' => $prog['event_name'],
                    'start' => $prog['start']
                ]);
                $telegramNotified[$programId] = time();
                $notifiedCount++;
            } else {
                telegram_log('ERROR', "Failed to send notification", [
                    'program_id' => $programId,
                    'title' => $prog['title'],
                    'chat_id' => substr($chatId, 0, 4) . '***'
                ]);
                $errorCount++;
            }
        }

        // Cleanup old notifications
        telegram_cleanup_old_notifications($favData);
        $favData['telegram_notified'] = $telegramNotified;

        // Send daily summary at 9:00 AM
        if (telegram_should_send_summary($favData)) {
            $today = date('Y-m-d');
            // Get user's language preference (default to Thai)
            $userLanguage = $favData['telegram_language'] ?? 'th';

            telegram_log('DEBUG', "Sending daily summary", ['date' => $today, 'language' => $userLanguage]);

            $summaryStmt = $db->prepare("
                SELECT DISTINCT
                    p.id, p.title, p.start, p.end, p.location, p.program_type, p.stream_url,
                    e.name as event_name
                FROM programs p
                JOIN events e ON p.event_id = e.id
                JOIN program_artists pa ON p.id = pa.program_id
                WHERE pa.artist_id IN ($placeholders)
                    AND e.is_active = 1
                    AND DATE(p.start) = :today
                ORDER BY e.name, p.start ASC
            ");

            $summaryParams = $allArtistIds;
            $summaryParams[] = $today;
            $summaryStmt->execute(array_merge($allArtistIds, [':today' => $today]));
            $summaryPrograms = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);
            $summaryStmt = null;

            if (!empty($summaryPrograms)) {
                telegram_log('DEBUG', "Summary programs found", ['count' => count($summaryPrograms)]);
                $summaryMsg = telegram_format_summary($summaryPrograms, $today, $userLanguage);
                if (telegram_send_message($chatId, $summaryMsg)) {
                    telegram_log('INFO', "Daily summary sent", ['date' => $today, 'programs_count' => count($summaryPrograms)]);
                    $favData['telegram_summary_date'] = $today;
                    $summaryCount++;
                } else {
                    telegram_log('ERROR', "Failed to send daily summary", ['date' => $today]);
                    $errorCount++;
                }
            } else {
                telegram_log('DEBUG', "No programs for summary today", ['date' => $today]);
            }
        } else {
            telegram_log('DEBUG', "Daily summary skipped (not in time window)");
            $summarySkippedCount++;
        }

        // Write back with lock
        $fh = @fopen($filePath, 'r+');
        if ($fh && flock($fh, LOCK_EX)) {
            rewind($fh);
            ftruncate($fh, 0);
            fwrite($fh, json_encode($favData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            flock($fh, LOCK_UN);
            fclose($fh);
            telegram_log('DEBUG', "Favorites file updated", ['file' => $filename]);
        } else {
            telegram_log('WARN', "Could not update favorites file", ['file' => $filename]);
        }

    } catch (Exception $e) {
        telegram_log('ERROR', "Exception processing favorites file", ['file' => $filename, 'error' => $e->getMessage()]);
        $errorCount++;
    }
}
