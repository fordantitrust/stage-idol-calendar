<?php
/**
 * Cache Configuration
 * Idol Stage Timetable v1.0.0
 */

// =============================================================================
// CACHE SETTINGS
// =============================================================================

/**
 * Data Version Cache File Path
 * Stores the last update timestamp from database
 */
define('DATA_VERSION_CACHE_FILE', dirname(__DIR__) . '/cache/data_version.json');

/**
 * Data Version Cache TTL (Time To Live)
 * How long to cache the data version before rechecking (in seconds)
 * Default: 600 seconds (10 minutes)
 */
define('DATA_VERSION_CACHE_TTL', 600);

/**
 * Credits Cache File Path
 * Stores the credits data from database
 */
define('CREDITS_CACHE_FILE', dirname(__DIR__) . '/cache/credits.json');

/**
 * Credits Cache TTL (Time To Live)
 * How long to cache the credits data before rechecking (in seconds)
 * Default: 3600 seconds (1 hour)
 */
define('CREDITS_CACHE_TTL', 3600);
