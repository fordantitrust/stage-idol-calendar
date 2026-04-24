<?php
/**
 * Bootstrap Configuration File
 * Idol Stage Timetable v1.0.0
 *
 * This file loads all configuration and helper functions from organized folders.
 *
 * Directory Structure:
 * ├── config/          - Configuration constants
 * │   ├── app.php         - Application settings (version, mode flags)
 * │   ├── analytics.php   - Google Analytics ID (not touched by version script)
 * │   ├── admin.php       - Admin & authentication settings
 * │   ├── security.php    - Security settings
 * │   ├── database.php    - Database configuration
 * │   └── cache.php       - Cache settings
 * │
 * └── functions/       - Helper functions
 *     ├── helpers.php     - General utility functions
 *     ├── cache.php       - Cache-related functions
 *     ├── admin.php       - Authentication functions
 *     └── security.php    - Security functions
 *
 * For backward compatibility, this file includes all automatically.
 */

// =============================================================================
// LOAD CONFIGURATION CONSTANTS
// =============================================================================

require_once __DIR__ . '/config/app.php';         // Application settings
require_once __DIR__ . '/config/google.php';      // Google services (Analytics + AdSense)
require_once __DIR__ . '/config/admin.php';       // Admin & authentication
require_once __DIR__ . '/config/security.php';    // Security settings
require_once __DIR__ . '/config/database.php';    // Database configuration
require_once __DIR__ . '/config/cache.php';       // Cache settings
require_once __DIR__ . '/config/favorites.php';   // Favorites system
require_once __DIR__ . '/config/telegram.php';    // Telegram bot settings

// =============================================================================
// LOAD HELPER FUNCTIONS
// =============================================================================

require_once __DIR__ . '/functions/helpers.php';   // General helpers
require_once __DIR__ . '/functions/ads.php';       // Google Ads helper
require_once __DIR__ . '/functions/cache.php';     // Cache functions
require_once __DIR__ . '/functions/security.php';  // Security functions
require_once __DIR__ . '/functions/admin.php';     // Admin functions
require_once __DIR__ . '/functions/favorites.php'; // Favorites system
require_once __DIR__ . '/functions/telegram.php';  // Telegram bot functions
require_once __DIR__ . '/functions/seo.php';       // SEO meta helpers
