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
// SITE COVER BACKGROUND HELPERS
// =============================================================================

/**
 * Get the site-wide header cover background image path
 *
 * @return string Relative path like 'uploads/site/cover_bg_abc.jpg', or '' if none
 */
function get_site_cover_bg(): string {
    $settingsFile = dirname(__DIR__) . '/cache/site-settings.json';
    if (!file_exists($settingsFile)) return '';
    $data = json_decode(file_get_contents($settingsFile), true);
    $path = $data['site_cover_bg'] ?? '';
    if ($path === '') return '';
    if (!file_exists(dirname(__DIR__) . '/' . ltrim($path, '/'))) return '';
    return $path;
}

/**
 * Get the effective header cover background image path, with per-event override.
 *
 * Priority:
 *   1. $eventMeta['header_cover_image'] — event-specific header cover (4:1 banner)
 *   2. get_site_cover_bg()              — site-wide setting from admin Settings
 *   3. ''                               — no image; caller renders plain gradient
 *
 * @param array|null $eventMeta Event meta row from DB, or null for non-event pages
 * @return string Relative path, or '' when no image is configured
 */
function get_header_cover_bg(?array $eventMeta = null): string {
    if (!empty($eventMeta['header_cover_image'])) {
        return $eventMeta['header_cover_image'];
    }
    return get_site_cover_bg();
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
 * Validate an IANA timezone identifier.
 *
 * @param mixed $tz Candidate timezone string (e.g. "Asia/Tokyo")
 * @return bool True if $tz is a non-empty string accepted by DateTimeZone
 */
function is_valid_timezone($tz): bool {
    if (!is_string($tz) || $tz === '') {
        return false;
    }
    try {
        new DateTimeZone($tz);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Resolve the viewer's effective timezone from a favorites record.
 *
 * Priority:
 *   - Manual override (user_timezone_manual === true): user_timezone wins for
 *     every channel, ignoring any per-device timezone.
 *   - Auto mode: per-device $subTz (web push) first, then user_timezone.
 *
 * Returns null when no usable viewer timezone is known — callers then fall back
 * to their legacy behaviour (e.g. annotate the site default timezone).
 *
 * @param array       $favData Favorites JSON data
 * @param string|null $subTz   Per-device timezone (web push subscription), or null
 * @return string|null Valid IANA timezone, or null
 */
function fav_resolve_user_timezone(array $favData, ?string $subTz = null): ?string {
    $userTz = $favData['user_timezone'] ?? null;
    $manual = !empty($favData['user_timezone_manual']);

    if ($manual && is_valid_timezone($userTz)) {
        return $userTz;
    }
    if (is_valid_timezone($subTz)) {
        return $subTz;
    }
    if (is_valid_timezone($userTz)) {
        return $userTz;
    }
    return null;
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

// =============================================================================
// EVENT COVER IMAGE HELPER
// =============================================================================

/**
 * Get the cover image path for an event (hero carousel / event cards)
 *
 * Priority:
 *   1. events.cover_image (dedicated 16:9 cover, uploaded via admin crop UI)
 *   2. $fallbackPicture (first event_picture, pre-fetched by caller)
 *   3. null → caller should render theme gradient placeholder
 *
 * @param array       $event           Event row from DB (must include 'cover_image' key)
 * @param string|null $fallbackPicture Relative path to first event_picture, or null
 * @return string|null Relative path (e.g. 'uploads/events/3/cover_abc.jpg'), or null
 */
function get_event_cover_image(array $event, ?string $fallbackPicture = null): ?string {
    if (!empty($event['cover_image'])) {
        return $event['cover_image'];
    }
    return $fallbackPicture ?: null;
}

// =============================================================================
// URL HELPERS
// =============================================================================

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
