<?php
/**
 * Cache Functions
 * Idol Stage Timetable v1.0.0
 */

/**
 * Get data version from database with caching
 *
 * @param int|null $eventId Filter by event_id (null = all events)
 * @return string Version string
 */
function get_data_version($eventId = null) {
    $cacheDir = dirname(DATA_VERSION_CACHE_FILE);
    $cacheSuffix = $eventId !== null ? "_$eventId" : '';
    $cacheFile = $cacheDir . "/data_version$cacheSuffix.json";
    $cacheTTL = DATA_VERSION_CACHE_TTL;

    if (file_exists($cacheFile)) {
        $cacheContent = file_get_contents($cacheFile);
        $cache = json_decode($cacheContent, true);
        if ($cache && isset($cache['timestamp']) && (time() - $cache['timestamp']) < $cacheTTL) {
            return $cache['version'];
        }
    }

    try {
        $dbPath = DB_PATH;
        if (!file_exists($dbPath)) {
            return date('j/n/Y H:i');
        }

        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($eventId !== null) {
            $stmt = $db->prepare("SELECT MAX(updated_at) as last_update FROM programs WHERE event_id = :id");
            $stmt->execute([':id' => $eventId]);
        } else {
            $stmt = $db->query("SELECT MAX(updated_at) as last_update FROM programs");
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $stmt = null;
        $db = null;

        if ($result && $result['last_update']) {
            $version = date('j/n/Y H:i', strtotime($result['last_update']));
        } else {
            $version = date('j/n/Y H:i');
        }

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        file_put_contents($cacheFile, json_encode([
            'version' => $version,
            'timestamp' => time()
        ]), LOCK_EX);

        return $version;
    } catch (Exception $e) {
        return date('j/n/Y H:i');
    }
}

/**
 * Get credits from database with caching
 * Returns global credits (event_id IS NULL) + event-specific credits
 *
 * @param int|null $eventId Filter by event_id (null = global only)
 * @return array Array of credits ordered by display_order
 */
function get_cached_credits($eventId = null) {
    $cacheDir = dirname(CREDITS_CACHE_FILE);
    $cacheSuffix = $eventId !== null ? "_$eventId" : '';
    $cacheFile = $cacheDir . "/credits$cacheSuffix.json";
    $cacheTTL = CREDITS_CACHE_TTL;

    // Check if cache exists and is still valid
    if (file_exists($cacheFile)) {
        $cacheContent = file_get_contents($cacheFile);
        $cache = json_decode($cacheContent, true);

        if ($cache && isset($cache['timestamp']) && (time() - $cache['timestamp']) < $cacheTTL) {
            return $cache['data'];
        }
    }

    // Cache miss or expired - fetch from database
    try {
        $dbPath = DB_PATH;

        if (!file_exists($dbPath)) {
            return [];
        }

        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($eventId !== null) {
            // Global credits (NULL) + event-specific credits
            $stmt = $db->prepare("SELECT * FROM credits WHERE event_id IS NULL OR event_id = :id ORDER BY display_order ASC, created_at ASC");
            $stmt->execute([':id' => $eventId]);
        } else {
            $stmt = $db->query("SELECT * FROM credits ORDER BY display_order ASC, created_at ASC");
        }
        $credits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $stmt = null;
        $db = null;

        // Save to cache
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        file_put_contents($cacheFile, json_encode([
            'data' => $credits,
            'timestamp' => time()
        ]), LOCK_EX);

        return $credits;

    } catch (PDOException $e) {
        error_log("Failed to fetch credits: " . $e->getMessage());
        return [];
    }
}

/**
 * Invalidate data version cache
 * Call this function after creating, updating, or deleting programs
 *
 * @param int|null $eventId Specific event cache to invalidate (null = all)
 * @return bool True if cache was deleted, false otherwise
 */
function invalidate_data_version_cache($eventId = null) {
    $cacheDir = dirname(DATA_VERSION_CACHE_FILE);

    if ($eventId !== null) {
        $files = [
            $cacheDir . "/data_version_$eventId.json",
            $cacheDir . "/data_version.json"
        ];
    } else {
        $files = glob($cacheDir . '/data_version*.json') ?: [];
    }

    $result = true;
    foreach ($files as $file) {
        if (file_exists($file)) {
            $result = unlink($file) && $result;
        }
    }

    return $result;
}

/**
 * Invalidate credits cache
 * Call this function after creating, updating, or deleting credits
 *
 * @param int|null $eventId Specific event cache to invalidate (null = all)
 * @return bool True if cache was deleted, false otherwise
 */
function invalidate_credits_cache($eventId = null) {
    $cacheDir = dirname(CREDITS_CACHE_FILE);

    if ($eventId !== null) {
        // Delete specific event cache + global cache
        $files = [
            $cacheDir . "/credits_$eventId.json",
            $cacheDir . "/credits.json"
        ];
    } else {
        // Delete all credits cache files
        $files = glob($cacheDir . '/credits*.json') ?: [];
    }

    $result = true;
    foreach ($files as $file) {
        if (file_exists($file)) {
            $result = unlink($file) && $result;
        }
    }

    return $result;
}

/**
 * Invalidate feed ICS cache
 * Call this function after creating, updating, or deleting programs.
 * Feed cache files are named feed_{eventId}_{hash}.ics
 *
 * @param int|null $eventId Specific event cache to invalidate (null = all)
 * @return bool True if cache was deleted, false otherwise
 */
function invalidate_feed_cache($eventId = null) {
    $cacheDir = FEED_CACHE_DIR;

    if (!is_dir($cacheDir)) {
        return true;
    }

    if ($eventId !== null) {
        // Delete specific event cache + global cache (eventId = 0)
        $files = array_merge(
            glob($cacheDir . "/feed_{$eventId}_*.ics") ?: [],
            glob($cacheDir . '/feed_0_*.ics') ?: []
        );
    } else {
        $files = glob($cacheDir . '/feed_*.ics') ?: [];
    }

    $result = true;
    foreach ($files as $file) {
        if (file_exists($file)) {
            $result = unlink($file) && $result;
        }
    }

    // Artist feed caches span all events — always invalidate them on any program change
    foreach (glob($cacheDir . '/feed_artist_*.ics') ?: [] as $file) {
        if (file_exists($file)) {
            $result = unlink($file) && $result;
        }
    }

    // Also invalidate image caches for this event
    invalidate_image_cache($eventId);

    return $result;
}

/**
 * Invalidate cached schedule PNG images for a given event (or all events).
 *
 * @param int|null $eventId Event ID, or null to clear all image caches
 */
function invalidate_image_cache($eventId = null) {
    $cacheDir = defined('IMAGE_CACHE_DIR') ? IMAGE_CACHE_DIR : (dirname(__DIR__) . '/cache');

    if (!is_dir($cacheDir)) {
        return;
    }

    if ($eventId !== null) {
        $files = array_merge(
            glob($cacheDir . "/img_{$eventId}_*.png") ?: [],
            glob($cacheDir . '/img_0_*.png') ?: []
        );
    } else {
        $files = glob($cacheDir . '/img_*.png') ?: [];
    }

    foreach ($files as $file) {
        if (file_exists($file)) {
            @unlink($file);
        }
    }
}

/**
 * Get cached DB query data
 *
 * @param string $filename Cache filename (without path), e.g. 'query_event_5.json'
 * @return array|false Decoded data array, or false on cache miss / expiry / error
 */
function get_query_cache(string $filename) {
    $cacheFile = QUERY_CACHE_DIR . '/' . $filename;
    if (!file_exists($cacheFile)) return false;
    if (time() - filemtime($cacheFile) > QUERY_CACHE_TTL) return false;
    $raw = @file_get_contents($cacheFile);
    if ($raw === false) return false;
    $data = json_decode($raw, true);
    return (is_array($data) && json_last_error() === JSON_ERROR_NONE) ? $data : false;
}

/**
 * Save DB query data to cache
 *
 * @param string $filename Cache filename (without path)
 * @param array  $data     Data to cache
 */
function save_query_cache(string $filename, array $data): void {
    $cacheDir = QUERY_CACHE_DIR;
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    file_put_contents($cacheDir . '/' . $filename, json_encode($data), LOCK_EX);
}

/**
 * Invalidate event query cache (query_event_*.json)
 * Call this after creating, updating, or deleting programs
 *
 * @param int|null $eventId Specific event to invalidate; null = all events
 * @return bool
 */
function invalidate_query_cache(?int $eventId = null): bool {
    $cacheDir = QUERY_CACHE_DIR;
    if (!is_dir($cacheDir)) return true;

    if ($eventId !== null) {
        $files = [
            $cacheDir . "/query_event_{$eventId}.json",
            $cacheDir . '/query_event_0.json', // global (no-event-filter) page
        ];
    } else {
        $files = glob($cacheDir . '/query_event_*.json') ?: [];
    }

    $result = true;
    foreach ($files as $file) {
        if (file_exists($file)) {
            $result = unlink($file) && $result;
        }
    }
    return $result;
}

/**
 * Invalidate all artist profile query caches (query_artist_*.json)
 * Call this after modifying artists, programs, or artist_variants
 *
 * @return bool
 */
function invalidate_artist_query_cache(): bool {
    $cacheDir = QUERY_CACHE_DIR;
    if (!is_dir($cacheDir)) return true;

    $files = glob($cacheDir . '/query_artist_*.json') ?: [];
    $result = true;
    foreach ($files as $file) {
        if (file_exists($file)) {
            $result = unlink($file) && $result;
        }
    }
    return $result;
}

/**
 * Invalidate all caches (data_version + credits + feed + query)
 * Call this function after restoring database
 *
 * @return bool True if all caches were deleted successfully
 */
function invalidate_all_caches() {
    $cacheDir = dirname(DATA_VERSION_CACHE_FILE);

    if (!is_dir($cacheDir)) {
        return true;
    }

    $result = true;
    $patterns = [
        'data_version*.json',
        'credits*.json',
        'feed_*.ics',
        'query_event_*.json',
        'query_artist_*.json',
    ];

    foreach ($patterns as $pattern) {
        $files = glob($cacheDir . '/' . $pattern) ?: [];
        foreach ($files as $file) {
            if (file_exists($file)) {
                $result = unlink($file) && $result;
            }
        }
    }

    return $result;
}
