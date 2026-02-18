<?php
/**
 * Helper Functions
 * Idol Stage Timetable v1.0.0
 */

/**
 * Generate asset URL with cache busting
 */
function asset_url($path) {
    return get_base_path() . '/' . $path . '?v=' . APP_VERSION;
}

// =============================================================================
// MULTI-EVENT HELPER FUNCTIONS
// =============================================================================

/**
 * Get current event slug from URL parameter or default
 *
 * @return string Event slug
 */
function get_current_event_slug() {
    if (!MULTI_EVENT_MODE) {
        return DEFAULT_EVENT_SLUG;
    }
    $slug = $_GET['event'] ?? DEFAULT_EVENT_SLUG;
    // Sanitize: only allow alphanumeric, hyphens, underscores
    return preg_replace('/[^a-zA-Z0-9\-_]/', '', $slug);
}

/**
 * Get event meta data by slug
 *
 * @param string $slug Event slug
 * @return array|null Event meta data or null if not found
 */
function get_event_meta_by_slug($slug) {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $db->prepare("SELECT * FROM events_meta WHERE slug = :slug AND is_active = 1");
        $stmt->execute([':slug' => $slug]);
        $meta = $stmt->fetch(PDO::FETCH_ASSOC);

        return $meta ?: null;
    } catch (PDOException $e) {
        error_log("Failed to get event meta: " . $e->getMessage());
        return null;
    }
}

/**
 * Get event meta ID by slug
 *
 * @param string $slug Event slug
 * @return int|null Event meta ID or null
 */
function get_event_meta_id($slug) {
    $meta = get_event_meta_by_slug($slug);
    return $meta ? intval($meta['id']) : null;
}

/**
 * Get all active events (for event selector dropdown)
 *
 * @return array List of active events_meta
 */
function get_all_active_events() {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if events_meta table exists
        $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='events_meta'");
        if (!$tableCheck->fetch()) {
            return [];
        }

        $stmt = $db->query("SELECT * FROM events_meta WHERE is_active = 1 ORDER BY start_date DESC, name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get active events: " . $e->getMessage());
        return [];
    }
}

/**
 * Get venue mode for an event meta
 * Falls back to global VENUE_MODE constant
 *
 * @param array|null $eventMeta Event meta data
 * @return string 'multi' or 'single'
 */
function get_event_venue_mode($eventMeta) {
    if ($eventMeta && !empty($eventMeta['venue_mode'])) {
        return $eventMeta['venue_mode'];
    }
    return VENUE_MODE;
}

/**
 * Get the application base path (for subdirectory deployments)
 *
 * @return string Base path (e.g., '' for root, '/subdir' for subdirectory)
 */
function get_base_path() {
    static $basePath = null;
    if ($basePath === null) {
        if (php_sapi_name() === 'cli') {
            $basePath = '';
        } else {
            $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
            $basePath = rtrim(dirname($scriptName), '/\\');
            if ($basePath === '.' || $basePath === '\\') {
                $basePath = '';
            }
        }
    }
    return $basePath;
}

/**
 * Build URL with clean paths and event support
 *
 * Examples:
 *   event_url('index.php')                    → '/'
 *   event_url('credits.php')                  → '/credits'
 *   event_url('index.php', 'feb-2026')        → '/event/feb-2026'
 *   event_url('credits.php', 'feb-2026')      → '/event/feb-2026/credits'
 *
 * @param string $path Base path (e.g., 'credits.php')
 * @param string|null $eventSlug Event slug (null = use current)
 * @param array $extraParams Additional query parameters
 * @return string Clean URL
 */
function event_url($path, $eventSlug = null, $extraParams = []) {
    if ($eventSlug === null) {
        $eventSlug = get_current_event_slug();
    }

    $basePath = get_base_path();

    // Clean URL: remove .php extension for public pages
    $page = $path;
    if (substr($path, -4) === '.php' && strpos($path, 'admin/') === false) {
        $page = substr($path, 0, -4);
    }

    // Build path: /event/slug/page or just /page
    $useEventPath = MULTI_EVENT_MODE && $eventSlug !== DEFAULT_EVENT_SLUG;
    if ($useEventPath) {
        if ($page === 'index' || $page === '') {
            $url = $basePath . '/event/' . $eventSlug;
        } else {
            $url = $basePath . '/event/' . $eventSlug . '/' . $page;
        }
    } else {
        if ($page === 'index') {
            $url = $basePath . '/';
        } else {
            $url = $basePath . '/' . $page;
        }
    }

    if (!empty($extraParams)) {
        return $url . '?' . http_build_query($extraParams);
    }
    return $url;
}
