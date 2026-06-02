<?php
/**
 * Web Push Notification Cron Script
 * Exit code: 0 (always, errors logged to file + stderr)
 *
 * SECURITY: This script must only run via CLI (cron), never via HTTP
 *
 * Logging: All activity logged to cache/logs/webpush-cron.log (rotates at 10MB)
 *
 * Cron setup example (every 15 minutes):
 *   * /15 * * * * php /path/to/cron/send-web-push-notifications.php >> /path/to/cache/logs/webpush-cron.log 2>&1
 */

// Block HTTP access - only allow CLI execution
if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cli-server') {
    http_response_code(403);
    die('Forbidden: This script can only be executed from command line (cron job)');
}

require_once __DIR__ . '/../config.php';

// ===== Logging System =====
define('WEBPUSH_LOG_DIR', __DIR__ . '/../cache/logs');
define('WEBPUSH_LOG_FILE', WEBPUSH_LOG_DIR . '/webpush-cron.log');
define('WEBPUSH_LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB

if (!is_dir(WEBPUSH_LOG_DIR)) {
    @mkdir(WEBPUSH_LOG_DIR, 0755, true);
}

function webpush_log($level, $message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $logLine = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";
    @file_put_contents(WEBPUSH_LOG_FILE, $logLine, FILE_APPEND | LOCK_EX);
    fwrite(STDERR, $logLine);
    webpush_rotate_log();
}

function webpush_rotate_log() {
    if (!file_exists(WEBPUSH_LOG_FILE)) return;
    if (filesize(WEBPUSH_LOG_FILE) > WEBPUSH_LOG_MAX_SIZE) {
        $timestamp = date('Y-m-d-His');
        $rotated = WEBPUSH_LOG_DIR . '/webpush-cron-' . $timestamp . '.log';
        @rename(WEBPUSH_LOG_FILE, $rotated);
        $files = glob(WEBPUSH_LOG_DIR . '/webpush-cron-*.log');
        if (count($files) > 10) {
            usort($files, function($a, $b) { return filemtime($a) - filemtime($b); });
            @unlink($files[0]);
        }
    }
}

// Check if Web Push is enabled
if (!webpush_is_enabled()) {
    webpush_log('WARN', 'Web Push not enabled or VAPID keys not configured', [
        'enabled' => defined('WEBPUSH_ENABLED') ? WEBPUSH_ENABLED : false,
        'has_public_key' => !empty(defined('WEBPUSH_VAPID_PUBLIC_KEY') ? WEBPUSH_VAPID_PUBLIC_KEY : '')
    ]);
    exit(0);
}

webpush_log('INFO', 'Starting Web Push notifications cron');

// Notification window: [now + N - halfWindow, now + N + halfWindow]
// halfWindow scales down for short notify times (notify=5 → ±2.5 min, notify≥15 → ±7.5 min)
$notifyBefore = WEBPUSH_NOTIFY_BEFORE_MINUTES * 60;
$halfWindow   = (int)(min(WEBPUSH_NOTIFY_BEFORE_MINUTES / 2, 7.5) * 60);
$windowStart  = time() + $notifyBefore - $halfWindow;
$windowEnd    = time() + $notifyBefore + $halfWindow;

webpush_log('DEBUG', 'Notification window', [
    'notify_before_minutes' => WEBPUSH_NOTIFY_BEFORE_MINUTES,
    'half_window_seconds'   => $halfWindow,
    'window_start'          => date('Y-m-d H:i:s', $windowStart),
    'window_end'            => date('Y-m-d H:i:s', $windowEnd),
]);

// Build VAPID config array once (re-used for every webpush_send() call)
$vapidCfg = [
    'vapid_public_key'      => WEBPUSH_VAPID_PUBLIC_KEY,
    'vapid_private_key_pem' => WEBPUSH_VAPID_PRIVATE_KEY_PEM,
    'vapid_subject'         => WEBPUSH_VAPID_SUBJECT,
];

$notifiedCount  = 0;
$skippedCount   = 0;
$errorCount     = 0;
$expiredCount   = 0;
$filesProcessed = 0;
$usersWithPush  = 0;

try {
    $favDir = FAVORITES_DIR;
    if (!is_dir($favDir)) {
        throw new Exception("Favorites directory not found: $favDir");
    }

    webpush_log('DEBUG', 'Scanning favorites directory', ['directory' => $favDir]);

    $shards = scandir($favDir);
    foreach ($shards as $shard) {
        if ($shard === '.' || $shard === '..' || !is_dir($favDir . '/' . $shard)) continue;

        $shardPath = $favDir . '/' . $shard;
        $files = scandir($shardPath);

        foreach ($files as $filename) {
            if (substr($filename, -5) !== '.json') continue;
            $filesProcessed++;
            $filePath = $shardPath . '/' . $filename;
            wp_process_favorites_file($filePath, $windowStart, $windowEnd, $vapidCfg,
                $notifiedCount, $skippedCount, $errorCount, $expiredCount, $usersWithPush);
        }
    }

    webpush_log('INFO', 'Cron completed successfully', [
        'files_processed'      => $filesProcessed,
        'users_with_push'      => $usersWithPush,
        'notifications_sent'   => $notifiedCount,
        'notifications_skipped'=> $skippedCount,
        'expired_removed'      => $expiredCount,
        'errors'               => $errorCount,
    ]);
    exit(0);

} catch (Exception $e) {
    webpush_log('ERROR', 'Cron exception', ['error' => $e->getMessage()]);
    exit(0);
}

// ---------------------------------------------------------------------------

/**
 * Process a single favorites JSON file for web push notifications.
 */
function wp_process_favorites_file($filePath, $windowStart, $windowEnd, $vapidCfg,
    &$notifiedCount, &$skippedCount, &$errorCount, &$expiredCount, &$usersWithPush)
{
    $filename = basename($filePath);

    // Read with shared lock
    $fh = @fopen($filePath, 'r');
    if (!$fh) {
        webpush_log('WARN', "Cannot open favorites file: $filename");
        return;
    }
    if (!flock($fh, LOCK_SH)) {
        fclose($fh);
        webpush_log('WARN', "Cannot lock favorites file: $filename");
        return;
    }
    $content = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);

    $favData = json_decode($content, true);
    if (!is_array($favData)) return;

    $subs = $favData['push_subscriptions'] ?? [];
    if (empty($subs) || empty($favData['artists'])) return;

    // Honour opt-out
    if (isset($favData['push_notify_enabled']) && $favData['push_notify_enabled'] === false) {
        webpush_log('DEBUG', "Push notifications disabled for user, skipping", ['file' => $filename]);
        return;
    }

    // Honour mute
    if (!empty($favData['push_mute_until']) && $favData['push_mute_until'] > time()) {
        webpush_log('DEBUG', "User muted, skipping", ['file' => $filename]);
        return;
    }

    $usersWithPush++;
    $artistIds = $favData['artists'];

    // Resolve parent groups (follow solo → also get group-level programs)
    $allArtistIds = $artistIds;
    try {
        $db = get_db();
        $placeholders = implode(',', array_fill(0, count($artistIds), '?'));
        $stmt = $db->prepare("
            SELECT DISTINCT group_id FROM artists
            WHERE id IN ($placeholders) AND group_id IS NOT NULL
        ");
        $stmt->execute($artistIds);
        $parentGroupIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $stmt = null;
        $allArtistIds = array_values(array_unique(array_merge($artistIds, $parentGroupIds)));
    } catch (Exception $e) {
        webpush_log('ERROR', "Error resolving groups", ['error' => $e->getMessage()]);
    }

    // Query programs in the notification window
    $programs = [];
    try {
        $db = get_db();
        $placeholders = implode(',', array_fill(0, count($allArtistIds), '?'));

        // Expand SQL window by ±14 h to cover all event timezone offsets; exact check in PHP.
        // Programs are stored in the event's own timezone (e.g. Asia/Taipei), not DEFAULT_TIMEZONE,
        // so a Bangkok window string cannot be compared directly against Taipei event start times.
        $tzObj        = new DateTimeZone(defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'Asia/Bangkok');
        $defaultTzName = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'Asia/Bangkok';
        $maxTzOffset   = 14 * 3600;
        $looseStartStr = (new DateTime('@' . ($windowStart - $maxTzOffset)))->setTimezone($tzObj)->format('Y-m-d H:i:s');
        $looseEndStr   = (new DateTime('@' . ($windowEnd   + $maxTzOffset)))->setTimezone($tzObj)->format('Y-m-d H:i:s');

        $stmt = $db->prepare("
            SELECT DISTINCT
                p.id, p.title, p.start, p.end, p.location, p.program_type, p.stream_url,
                e.id as event_id, e.name as event_name, e.slug as event_slug,
                COALESCE(e.timezone, :defaultTz) AS event_timezone
            FROM programs p
            JOIN events e ON p.event_id = e.id
            JOIN program_artists pa ON p.id = pa.program_id
            WHERE pa.artist_id IN ($placeholders)
                AND e.is_active = 1
                AND datetime(p.start) BETWEEN datetime(:looseStart) AND datetime(:looseEnd)
        ");
        $stmt->execute(array_merge($allArtistIds, [
            ':defaultTz'  => $defaultTzName,
            ':looseStart' => $looseStartStr,
            ':looseEnd'   => $looseEndStr,
        ]));
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = null;

        webpush_log('DEBUG', "Programs in window", ['count' => count($programs), 'window_start' => $looseStartStr, 'window_end' => $looseEndStr]);
    } catch (Exception $e) {
        webpush_log('ERROR', "DB query failed", ['file' => $filename, 'error' => $e->getMessage()]);
        $errorCount++;
        return;
    }

    if (empty($programs)) return;

    // Pre-compute exact-check constants (center ± half of the UTC window).
    $notifyTargetUtc = (int)(($windowStart + $windowEnd) / 2);
    $halfWindowDur   = (int)(($windowEnd - $windowStart) / 2);

    // Send notifications to each subscription × each program
    $modified    = false;
    $removeSubs  = []; // indices of expired subscriptions

    foreach ($subs as $subIdx => $sub) {
        if (empty($sub['endpoint']) || empty($sub['keys'])) continue;

        foreach ($programs as $prog) {
            $programId = (int)$prog['id'];

            // Exact UTC-based window check using the event's own timezone.
            // SQL pre-filter was expanded by ±14 h; this confirms the program
            // actually falls within [notifyBefore − halfWindow, notifyBefore + halfWindow].
            $evTzName = $prog['event_timezone'] ?: $defaultTzName;
            try { $evTz = new DateTimeZone($evTzName); } catch (Exception $e) { $evTz = $tzObj; }
            $programUtc = (new DateTime($prog['start'], $evTz))->getTimestamp();
            if (abs($programUtc - $notifyTargetUtc) > $halfWindowDur) {
                continue;
            }

            $notifiedMap = $sub['notified'] ?? [];

            // De-duplicate: skip if notified within ±7.5 minutes
            if (isset($notifiedMap[$programId])) {
                $diff = abs(time() - $notifiedMap[$programId]);
                if ($diff < 450) {
                    $skippedCount++;
                    continue;
                }
            }

            // Build notification payload — show time in event's own timezone
            $startTime = (new DateTime($prog['start'], $evTz))->format('H:i');
            // Resolve the viewer timezone for this device: manual override > per-device tz > favorites tz.
            // Falls back to the site default (legacy behaviour) when nothing usable is known.
            $viewerTz = fav_resolve_user_timezone($favData, $sub['tz'] ?? null);
            $annotTzName = ($viewerTz && $viewerTz !== $evTzName)
                ? $viewerTz
                : (($viewerTz === null && $evTzName !== $defaultTzName) ? $defaultTzName : null);
            if ($annotTzName !== null) {
                try {
                    $annotTz    = new DateTimeZone($annotTzName);
                    $localStart = (new DateTime($prog['start'], $evTz))->setTimezone($annotTz);
                    // Label viewer-specific zones so the recipient knows whose clock it is
                    $label      = ($annotTzName === $defaultTzName) ? '' : ' ' . $annotTzName;
                    $startTime .= ' (' . $localStart->format('H:i') . $label . ')';
                } catch (Exception $e) {
                    // invalid annotation timezone — skip the parenthetical
                }
            }
            $location  = !empty($prog['location']) ? $prog['location'] : '';
            $body      = $startTime . ($location ? ' • ' . $location : '');

            // Build event URL using configured site URL
            $baseUrl = WEBPUSH_SITE_URL; // e.g. "https://example.com/stage-idol-calendar"
            if (!empty($prog['event_slug'])) {
                $url = $baseUrl . '/event/' . $prog['event_slug'];
            } else {
                $url = $baseUrl . '/';
            }

            $eventName = $prog['event_name'] ?? '';
            $title     = $prog['title'] . (!empty($eventName) ? ' [' . $eventName . ']' : '');

            $payload = json_encode([
                'title' => $title,
                'body'  => $body,
                'url'   => $url,
                'icon'  => $baseUrl . '/icon/icon-192.png',
                'badge' => $baseUrl . '/icon/icon-72.png',
                'tag'   => 'program-' . $programId,
            ], JSON_UNESCAPED_UNICODE);

            // Send
            $result = webpush_send($sub, $payload, $vapidCfg);

            if ($result['success']) {
                webpush_log('INFO', "Push sent", [
                    'program_id' => $programId,
                    'title'      => $prog['title'],
                    'endpoint'   => substr($sub['endpoint'], 0, 40) . '…',
                ]);
                $subs[$subIdx]['notified'][$programId] = time();
                $modified = true;
                $notifiedCount++;
            } elseif (!empty($result['expired'])) {
                // HTTP 410 — subscription is no longer valid, remove it
                webpush_log('WARN', "Subscription expired (410), removing", [
                    'endpoint' => substr($sub['endpoint'], 0, 40) . '…',
                ]);
                $removeSubs[] = $subIdx;
                $expiredCount++;
                $modified = true;
                break; // no point sending more programs to an expired sub
            } else {
                webpush_log('ERROR', "Push send failed", [
                    'program_id'  => $programId,
                    'status_code' => $result['status_code'] ?? 0,
                    'error'       => $result['error'] ?? '',
                ]);
                $errorCount++;
            }
        }
    }

    // Prune expired subscriptions
    foreach (array_unique($removeSubs) as $idx) {
        unset($subs[$idx]);
    }
    $subs = array_values($subs);

    if (!$modified && empty($removeSubs)) return;

    // Cleanup notified entries older than 7 days
    $sevenDaysAgo = time() - (7 * 86400);
    foreach ($subs as $subIdx => $sub) {
        if (!empty($sub['notified'])) {
            foreach ($sub['notified'] as $pid => $ts) {
                if ($ts < $sevenDaysAgo) unset($subs[$subIdx]['notified'][$pid]);
            }
        }
    }

    $favData['push_subscriptions'] = $subs;

    // Read-modify-write under exclusive lock.
    // Re-read the file inside LOCK_EX so we pick up any concurrent writes
    // (Telegram cron updating telegram_notified, web routes updating
    // artists/last_access) and only overwrite our own push_subscriptions key.
    // Without this merge step, a concurrent writer reading the file before
    // our HTTP send and writing after our HTTP send would clobber its own
    // update — and likewise we would clobber theirs.
    $fh = @fopen($filePath, 'r+');
    if ($fh && flock($fh, LOCK_EX)) {
        rewind($fh);
        $latestRaw = stream_get_contents($fh);
        $latest = json_decode($latestRaw, true);
        if (!is_array($latest)) {
            // File is empty or corrupt — fall back to our in-memory state
            $latest = $favData;
        } else {
            $latest['push_subscriptions'] = $subs;
        }

        rewind($fh);
        ftruncate($fh, 0);
        fwrite($fh, json_encode($latest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        flock($fh, LOCK_UN);
        fclose($fh);
        webpush_log('DEBUG', "Favorites file updated", ['file' => $filename]);
    } else {
        if ($fh) fclose($fh);
        webpush_log('WARN', "Could not update favorites file", ['file' => $filename]);
    }
}
