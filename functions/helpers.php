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
// THEME HELPER
// =============================================================================

/**
 * Get the active theme for the current context
 *
 * Priority:
 *   1. Event-specific theme ($eventMeta['theme']) — if set and valid
 *   2. Global site theme from admin Settings (cache/site-theme.json)
 *   3. Default fallback: 'dark'
 *
 * @param array|null $eventMeta Event meta data (from get_event_by_slug()), or null
 * @return string Theme name (sakura|ocean|forest|midnight|sunset|dark|gray)
 */
function get_site_theme($eventMeta = null) {
    $validThemes = ['sakura', 'ocean', 'forest', 'midnight', 'sunset', 'dark', 'gray', 'crimson', 'teal', 'rose', 'amber', 'indigo'];

    // 1. Event-specific theme takes priority
    if ($eventMeta && !empty($eventMeta['theme']) && in_array($eventMeta['theme'], $validThemes)) {
        return $eventMeta['theme'];
    }

    // 2. Global theme from admin Settings
    $themeFile = dirname(__DIR__) . '/cache/site-theme.json';
    if (file_exists($themeFile)) {
        $data = json_decode(file_get_contents($themeFile), true);
        if (isset($data['theme']) && in_array($data['theme'], $validThemes)) {
            return $data['theme'];
        }
    }

    // 3. Default fallback
    return 'dark';
}

// =============================================================================
// SITE TITLE HELPER
// =============================================================================

/**
 * Get the site title
 *
 * Priority:
 *   1. Custom title saved by admin in cache/site-settings.json
 *   2. APP_NAME constant (config/app.php)
 *   3. Hard fallback: 'Idol Stage Timetable'
 *
 * @return string Site title (raw, not HTML-escaped — use htmlspecialchars() when outputting in HTML)
 */
function get_site_title() {
    $settingsFile = dirname(__DIR__) . '/cache/site-settings.json';
    if (file_exists($settingsFile)) {
        $data = json_decode(file_get_contents($settingsFile), true);
        if (!empty($data['site_title']) && trim($data['site_title']) !== '') {
            return trim($data['site_title']);
        }
    }
    return defined('APP_NAME') ? APP_NAME : 'Idol Stage Timetable';
}

// =============================================================================
// SITE DISCLAIMER HELPER
// =============================================================================

/**
 * Get the site disclaimer texts (3 languages)
 *
 * Returns array with keys 'th', 'en', 'ja'. Falls back to empty string per language
 * if not yet configured (contact.php will use translations.js defaults in that case).
 *
 * @return array ['th' => string, 'en' => string, 'ja' => string]
 */
function get_site_disclaimer() {
    $settingsFile = dirname(__DIR__) . '/cache/site-settings.json';
    $defaults = ['th' => '', 'en' => '', 'ja' => ''];
    if (file_exists($settingsFile)) {
        $data = json_decode(file_get_contents($settingsFile), true);
        if (is_array($data)) {
            return [
                'th' => isset($data['disclaimer_th']) ? $data['disclaimer_th'] : '',
                'en' => isset($data['disclaimer_en']) ? $data['disclaimer_en'] : '',
                'ja' => isset($data['disclaimer_ja']) ? $data['disclaimer_ja'] : '',
            ];
        }
    }
    return $defaults;
}

// =============================================================================
// DATABASE CONNECTION (SINGLETON)
// =============================================================================

/**
 * Get singleton PDO database connection
 * Creates connection once per request and reuses it
 *
 * @return PDO|null PDO instance or null if unavailable
 */
function get_db() {
    static $db       = null;
    static $attempted = false;
    if (!$attempted) {
        $attempted = true;
        if (defined('DB_PATH') && file_exists(DB_PATH)) {
            try {
                $db = new PDO('sqlite:' . DB_PATH);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
            }
        }
    }
    return $db;
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
function get_event_by_slug($slug) {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $db->prepare("SELECT * FROM events WHERE slug = :slug AND is_active = 1");
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
function get_event_id($slug) {
    $meta = get_event_by_slug($slug);
    return $meta ? intval($meta['id']) : null;
}

/**
 * Get all active events (for event selector dropdown)
 *
 * @return array List of active events
 */
function get_all_active_events() {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if events table exists
        $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='events'");
        if (!$tableCheck->fetch()) {
            return [];
        }

        $stmt = $db->query("SELECT * FROM events WHERE is_active = 1 ORDER BY start_date DESC, name ASC");
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
 * Get the timezone for an event
 *
 * Priority:
 *   1. Event-specific timezone ($eventMeta['timezone']) — if set and valid
 *   2. DEFAULT_TIMEZONE constant
 *   3. Hard fallback: 'Asia/Bangkok'
 *
 * @param array|null $eventMeta Event meta data, or null
 * @return string Valid PHP timezone identifier
 */
function get_event_timezone($eventMeta = null): string {
    if ($eventMeta && !empty($eventMeta['timezone'])) {
        try {
            new DateTimeZone($eventMeta['timezone']);
            return $eventMeta['timezone'];
        } catch (Exception $e) {
            // invalid timezone string, fall through
        }
    }
    return defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'Asia/Bangkok';
}

/**
 * Get a validated HTTP Host header value to prevent Host Header Injection.
 * Accepts only valid hostname[:port] or IPv4/IPv6 formats.
 * Falls back to SERVER_NAME (set by web server config, not by the client).
 *
 * @return string Safe host string
 */
function get_safe_host(): string {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    // Allow: hostname, hostname:port, IPv4, IPv4:port, [IPv6], [IPv6]:port
    if ($host !== '' && preg_match('/^(\[[\da-fA-F:]+\]|[\w.\-]+)(:\d{1,5})?$/', $host)) {
        return $host;
    }
    // Fallback to SERVER_NAME which is configured by the web server
    return $_SERVER['SERVER_NAME'] ?? 'localhost';
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
