<?php
/**
 * Cache Functions
 * Idol Stage Timetable v1.0.0
 */

/**
 * Get data version from database with caching
 */
function get_data_version() {
    $cacheFile = DATA_VERSION_CACHE_FILE;
    $cacheTTL = DATA_VERSION_CACHE_TTL;

    if (file_exists($cacheFile)) {
        $cacheContent = file_get_contents($cacheFile);
        $cache = json_decode($cacheContent, true);
        if ($cache && isset($cache['timestamp']) && (time() - $cache['timestamp']) < $cacheTTL) {
            return $cache['version'];
        }
    }

    try {
        $dbPath = dirname(__DIR__) . '/calendar.db';
        if (!file_exists($dbPath)) {
            return date('j/n/Y H:i');
        }

        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $db->query("SELECT MAX(updated_at) as last_update FROM events");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['last_update']) {
            $version = date('j/n/Y H:i', strtotime($result['last_update']));
        } else {
            $version = date('j/n/Y H:i');
        }

        $cacheDir = dirname($cacheFile);
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
 *
 * @return array Array of credits ordered by display_order
 */
function get_cached_credits() {
    $cacheFile = CREDITS_CACHE_FILE;
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
        $dbPath = dirname(__DIR__) . '/calendar.db';

        if (!file_exists($dbPath)) {
            return [];
        }

        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $db->query("SELECT * FROM credits ORDER BY display_order ASC, created_at ASC");
        $credits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Save to cache
        $cacheDir = dirname($cacheFile);
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
 * @return bool True if cache was deleted, false otherwise
 */
function invalidate_credits_cache() {
    $cacheFile = CREDITS_CACHE_FILE;

    if (file_exists($cacheFile)) {
        return unlink($cacheFile);
    }

    return true;
}
