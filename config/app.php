<?php
/**
 * Application Configuration
 * Idol Stage Timetable
 */

// =============================================================================
// APPLICATION SETTINGS
// =============================================================================

/**
 * Application Timezone
 * Must be set before any date/time functions are called.
 * All stored datetimes are in Asia/Bangkok (UTC+7).
 */
date_default_timezone_set('Asia/Bangkok');

/**
 * Application Version (Semantic Versioning)
 * Change this to force browser cache refresh after updating CSS/JS
 */
define('APP_VERSION', '2.10.2');

/**
 * Application Name (Site Title)
 * Default/fallback when no custom title is saved via admin Settings
 */
define('APP_NAME', 'Idol Track');

/**
 * Production Mode
 * Set to true in production to hide detailed error messages
 */
define('PRODUCTION_MODE', true);

/**
 * Venue Mode
 * 'multi' - Multiple venues (shows venue filter, venue columns, Gantt view)
 * 'single' - Single venue (hides venue filter, venue columns, Gantt view only)
 */
define('VENUE_MODE', 'multi');

/**
 * Multi-Event Mode
 * true  - Enable multi-event support (event selector, per-event filtering)
 * false - Single event mode (backward compatible)
 */
define('MULTI_EVENT_MODE', true);

/**
 * Default Event Slug
 * Used when no event is specified in URL parameter
 */
define('DEFAULT_EVENT_SLUG', 'default');

// Google Analytics ID moved to config/analytics.php
// (kept separate so version updates do not overwrite site-specific settings)
