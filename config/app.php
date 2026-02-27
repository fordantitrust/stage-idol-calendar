<?php
/**
 * Application Configuration
 * Idol Stage Timetable v1.0.0
 */

// =============================================================================
// APPLICATION SETTINGS
// =============================================================================

/**
 * Application Version (Semantic Versioning)
 * Change this to force browser cache refresh after updating CSS/JS
 */
define('APP_VERSION', '2.1.0');

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

/**
 * Google Analytics Measurement ID
 * Set to your GA4 Measurement ID (e.g. 'G-XXXXXXXXXX') to enable tracking.
 * Leave empty '' to disable Google Analytics entirely.
 */
define('GOOGLE_ANALYTICS_ID', '');
