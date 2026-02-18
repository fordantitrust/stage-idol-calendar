<?php
/**
 * Cache Functions
 * Idol Stage Timetable v1.0.0
 */

/**
 * Get data version from database with caching
 *
 * @param int|null $eventMetaId Filter by event_meta_id (null = all events)
 * @return string Version string
 */
function get_data_version($eventMetaId = null) {
    $cacheDir = dirname(DATA_VERSION_CACHE_FILE);
    $cacheSuffix = $eventMetaId !== null ? "_$eventMetaId" : '';
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

        if ($eventMetaId !== null) {
            $stmt = $db->prepare("SELECT MAX(updated_at) as last_update FROM events WHERE event_meta_id = :id");
            $stmt->execute([':id' => $eventMetaId]);
        } else {
            $stmt = $db->query("SELECT MAX(updated_at) as last_update FROM events");
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
 * Returns global credits (event_meta_id IS NULL) + event-specific credits
 *
 * @param int|null $eventMetaId Filter by event_meta_id (null = global only)
 * @return array Array of credits ordered by display_order
 */
function get_cached_credits($eventMetaId = null) {
    $cacheDir = dirname(CREDITS_CACHE_FILE);
    $cacheSuffix = $eventMetaId !== null ? "_$eventMetaId" : '';
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

        if ($eventMetaId !== null) {
            // Global credits (NULL) + event-specific credits
            $stmt = $db->prepare("SELECT * FROM credits WHERE event_meta_id IS NULL OR event_meta_id = :id ORDER BY display_order ASC, created_at ASC");
            $stmt->execute([':id' => $eventMetaId]);
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
 * Invalidate credits cache
 * Call this function after creating, updating, or deleting credits
 *
 * @param int|null $eventMetaId Specific event cache to invalidate (null = all)
 * @return bool True if cache was deleted, false otherwise
 */
function invalidate_credits_cache($eventMetaId = null) {
    $cacheDir = dirname(CREDITS_CACHE_FILE);

    if ($eventMetaId !== null) {
        // Delete specific event cache + global cache
        $files = [
            $cacheDir . "/credits_$eventMetaId.json",
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
 * Invalidate all caches (data_version + credits)
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
    $patterns = ['data_version*.json', 'credits*.json'];

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
