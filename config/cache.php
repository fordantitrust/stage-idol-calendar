<?php
/**
 * Cache Configuration
 * Idol Stage Timetable
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

/**
 * Feed Cache Directory
 * Directory where rendered ICS feed files are cached
 */
define('FEED_CACHE_DIR', dirname(__DIR__) . '/cache');

/**
 * Feed Cache TTL (Time To Live)
 * How long to serve the cached ICS feed before regenerating (in seconds)
 * Default: 3600 seconds (1 hour)
 */
define('FEED_CACHE_TTL', 3600);

/**
 * Query Cache Directory
 * Directory where DB query result JSON files are stored
 * Files: query_event_{id}.json, query_artist_{id}.json
 */
define('QUERY_CACHE_DIR', dirname(__DIR__) . '/cache');

/**
 * Query Cache TTL (Time To Live)
 * How long to serve cached query results before re-querying the DB (in seconds)
 * Default: 3600 seconds (1 hour)
 */
define('QUERY_CACHE_TTL', 3600);

/**
 * Image Cache Directory
 * Directory where generated PNG schedule images are cached
 */
define('IMAGE_CACHE_DIR', dirname(__DIR__) . '/cache/images');

/**
 * Image Cache TTL (Time To Live)
 * How long to serve a cached PNG before regenerating (in seconds)
 * Default: 3600 seconds (1 hour)
 */
define('IMAGE_CACHE_TTL', 3600);
