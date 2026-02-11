<?php
/**
 * Security Configuration
 * Idol Stage Timetable v1.0.0
 */

// =============================================================================
// RATE LIMITING SETTINGS
// =============================================================================

/**
 * Rate Limit Maximum Requests
 * Maximum number of requests allowed per time window
 */
define('RATE_LIMIT_MAX', 10);

/**
 * Rate Limit Time Window (seconds)
 * Time window for rate limiting
 * Default: 3600 seconds (1 hour)
 */
define('RATE_LIMIT_WINDOW', 3600);
