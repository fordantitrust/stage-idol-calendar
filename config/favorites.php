<?php
/**
 * Favorites System Configuration
 * Idol Stage Timetable
 *
 * FAVORITES_HMAC_SECRET — generate using:
 *   php tools/generate-favorites-secret.php
 * Paste the 64-char hex string below.
 */

// Storage directory (inside cache/ so parent .htaccess protects JSON files)
define('FAVORITES_DIR', dirname(__DIR__) . '/cache/favorites');

// TTL: delete file if not accessed for this many seconds (365 days)
define('FAVORITES_TTL', 365 * 24 * 3600);

// Throttle: only update last_access if older than this (reduce I/O)
define('FAVORITES_LAST_ACCESS_THROTTLE', 3600); // 1 hour

// Limits
define('FAVORITES_MAX_ARTISTS', 50);          // max artists per token
define('FAVORITES_RATE_LIMIT',  10);           // new tokens per IP per window
define('FAVORITES_RATE_WINDOW', 12 * 3600);    // 12-hour window

// Rate-limit cache dir (shares existing cache/)
define('FAVORITES_RL_DIR', dirname(__DIR__) . '/cache');

// HMAC — run: php tools/generate-favorites-secret.php
define('FAVORITES_HMAC_SECRET', 'REPLACE_WITH_GENERATED_SECRET');
define('FAVORITES_HMAC_LENGTH', 12); // hex chars appended to UUID in URL
