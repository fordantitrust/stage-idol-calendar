<?php
/**
 * Send security headers
 *
 * Sends HTTP headers to improve security:
 * - Prevents MIME type sniffing
 * - Prevents clickjacking
 * - Enables XSS filter
 * - Sets referrer policy
 * - Sets permissions policy
 */
function send_security_headers() {
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');

    // Prevent clickjacking
    header('X-Frame-Options: SAMEORIGIN');

    // Enable browser XSS filter
    header('X-XSS-Protection: 1; mode=block');

    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Permissions Policy (disable unused features)
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// =============================================================================
// INPUT SANITIZATION FUNCTIONS
// =============================================================================

/**
 * Sanitize string input
 *
 * @param string $input Raw input string
 * @param int $maxLength Maximum allowed length
 * @return string Sanitized string
 */
function sanitize_string($input, $maxLength = 200) {
    // Convert to string and remove null bytes
    $input = (string)$input;
    $input = str_replace("\0", '', $input);

    // Trim whitespace
    $input = trim($input);

    // Limit length
    if (strlen($input) > $maxLength) {
        $input = substr($input, 0, $maxLength);
    }

    return $input;
}

/**
 * Sanitize array of strings
 *
 * @param array $array Raw input array
 * @param int $maxLength Maximum length per item
 * @param int $maxItems Maximum number of items
 * @return array Sanitized array
 */
function sanitize_string_array($array, $maxLength = 200, $maxItems = 100) {
    if (!is_array($array)) {
        return [];
    }

    // Limit number of items
    $array = array_slice($array, 0, $maxItems);

    // Sanitize each item
    $array = array_map(function($item) use ($maxLength) {
        return sanitize_string($item, $maxLength);
    }, $array);

    // Remove empty values
    $array = array_filter($array, function($item) {
        return $item !== '';
    });

    return array_values($array); // Re-index
}

/**
 * Safely get and sanitize GET parameter (string)
 *
 * @param string $key Parameter name
 * @param string $default Default value
 * @param int $maxLength Maximum length
 * @return string Sanitized value
 */
function get_sanitized_param($key, $default = '', $maxLength = 200) {
    if (!isset($_GET[$key])) {
        return $default;
    }

    return sanitize_string($_GET[$key], $maxLength);
}

/**
 * Safely get and sanitize GET parameter (array)
 *
 * @param string $key Parameter name
 * @param int $maxLength Maximum length per item
 * @param int $maxItems Maximum number of items
 * @return array Sanitized array
 */
function get_sanitized_array_param($key, $maxLength = 200, $maxItems = 100) {
    if (!isset($_GET[$key])) {
        return [];
    }

    $value = $_GET[$key];

    // Convert single value to array
    if (!is_array($value)) {
        $value = [$value];
    }

    return sanitize_string_array($value, $maxLength, $maxItems);
}

// =============================================================================
// ERROR HANDLING FUNCTIONS
// =============================================================================

/**
 * Create safe error message
 *
 * In production mode, hides detailed error messages
 *
 * @param string $message Generic error message
 * @param string $details Detailed error message (shown only in development)
 * @return string Safe error message
 */
function safe_error_message($message, $details = '') {
    // Check if in production mode (default to false if not defined)
    $isProduction = defined('PRODUCTION_MODE') ? PRODUCTION_MODE : false;

    if ($isProduction) {
        return $message;
    }
    return $details ? "$message: $details" : $message;
}

// =============================================================================
// CSRF PROTECTION FUNCTIONS
// =============================================================================

/**
 * Generate or retrieve CSRF token from session
 *
 * @return string CSRF token
 */
function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 *
 * @param string $token Token to verify
 * @return bool True if valid, false otherwise
 */
function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require valid CSRF token for API requests
 *
 * Checks for CSRF token in POST, PUT, DELETE requests
 * Returns 403 error if token is missing or invalid
 */
function require_csrf_token() {
    if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verify_csrf_token($token)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'data' => null,
                'message' => 'Invalid or missing CSRF token'
            ]);
            exit;
        }
    }
}

// =============================================================================
// IP WHITELIST FUNCTIONS
// =============================================================================

/**
 * Get client IP address safely
 *
 * Uses REMOTE_ADDR only (most secure)
 *
 * Note: X-Forwarded-For can be easily spoofed
 * If behind trusted proxy, configure proxy to send real IP via REMOTE_ADDR
 *
 * @return string Client IP address
 */
function get_client_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/**
 * Check if IP is in CIDR range
 *
 * @param string $ip IP address to check
 * @param string $cidr CIDR notation (e.g., "192.168.1.0/24")
 * @return bool True if IP is in range
 */
function ip_in_cidr($ip, $cidr) {
    // If no /, it's a single IP
    if (strpos($cidr, '/') === false) {
        return $ip === $cidr;
    }

    list($subnet, $mask) = explode('/', $cidr);

    // Support IPv4 only for CIDR
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        return $ip === $subnet; // IPv6 must match exactly
    }

    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask_long = -1 << (32 - (int)$mask);

    return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
}

/**
 * Check if IP is in whitelist
 *
 * @param string|null $ip IP address to check (null = auto-detect)
 * @return bool True if IP is allowed
 */
function is_ip_allowed($ip = null) {
    if (!ADMIN_IP_WHITELIST_ENABLED) {
        return true;
    }

    if ($ip === null) {
        $ip = get_client_ip();
    }

    foreach (ADMIN_ALLOWED_IPS as $allowed) {
        if (ip_in_cidr($ip, $allowed)) {
            return true;
        }
    }

    return false;
}

/**
 * Require IP to be in whitelist (for HTML pages)
 *
 * Shows 403 Forbidden page if IP is not allowed
 */
function require_allowed_ip() {
    if (!is_ip_allowed()) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head>';
        echo '<body style="font-family: sans-serif; text-align: center; padding: 50px;">';
        echo '<h1>403 Forbidden</h1>';
        echo '<p>Access denied. Your IP address is not allowed.</p>';
        if (!PRODUCTION_MODE) {
            echo '<p style="color: #999;">Your IP: ' . htmlspecialchars(get_client_ip()) . '</p>';
        }
        echo '</body></html>';
        exit;
    }
}

/**
 * Require IP to be in whitelist (for API endpoints)
 *
 * Returns JSON error if IP is not allowed
 */
function require_api_allowed_ip() {
    if (!is_ip_allowed()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'data' => null,
            'message' => 'Access denied. IP not allowed.'
        ]);
        exit;
    }
}