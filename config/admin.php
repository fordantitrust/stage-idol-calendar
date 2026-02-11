<?php
/**
 * Admin Configuration
 * Idol Stage Timetable v1.0.0
 */

// =============================================================================
// AUTHENTICATION SETTINGS
// =============================================================================

/**
 * Admin Username
 *
 * ⚠️ IMPORTANT: Change this before deploying to production!
 */
define('ADMIN_USERNAME', 'admin');

/**
 * Admin Password Hash
 *
 * ⚠️ IMPORTANT: Change this before deploying to production!
 *
 * Generate hash using:
 * php -r "echo password_hash('your_secure_password', PASSWORD_DEFAULT);"
 *
 * Then paste the output here.
 *
 * Note: Never store plain text passwords!
 */
define('ADMIN_PASSWORD_HASH', '$2y$10$8xJ2LLw7X4TXOK9Eepeqv.DTzuU/canzio4HL9L1OG5RgnWVh/JVy');

/**
 * Session Timeout (in seconds)
 *
 * Default: 7200 seconds (2 hours)
 * After this time of inactivity, user will be logged out automatically
 */
define('SESSION_TIMEOUT', 7200);

// =============================================================================
// IP WHITELIST SETTINGS
// =============================================================================

/**
 * Enable IP Whitelist for Admin Access
 * Set to true to restrict admin panel to specific IP addresses
 */
define('ADMIN_IP_WHITELIST_ENABLED', false);

/**
 * Allowed IP Addresses for Admin Access
 *
 * Supports:
 * - Single IP: '192.168.1.100'
 * - CIDR notation: '192.168.1.0/24'
 * - IPv6: '::1'
 *
 * Examples:
 * - '192.168.1.0/24' = All IPs from 192.168.1.0 to 192.168.1.255
 * - '10.0.0.0/8' = All IPs from 10.0.0.0 to 10.255.255.255
 */
define('ADMIN_ALLOWED_IPS', [
    '127.0.0.1',        // localhost IPv4
    '::1',              // localhost IPv6
    // Add your allowed IPs here:
    // '192.168.1.0/24',  // Example: Entire subnet 192.168.1.x
    // '10.0.0.5',        // Example: Specific IP
]);
