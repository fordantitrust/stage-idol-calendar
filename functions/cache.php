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
        ]));

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

        // Save to cache
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        file_put_contents($cacheFile, json_encode([
            'data' => $credits,
            'timestamp' => time()
        ]));

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

    return $result;
}

/**
 * Invalidate all caches (data_version + credits + feed)
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
    $patterns = ['data_version*.json', 'credits*.json', 'feed_*.ics'];

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
