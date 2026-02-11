<?php
/**
 * Admin Functions
 * Idol Stage Timetable v1.1.0
 */

// =============================================================================
// SESSION MANAGEMENT
// =============================================================================

/**
 * Safely start session
 *
 * Prevents race conditions by using session_start() only once
 * and handling concurrent requests properly
 *
 * @return void
 */
function safe_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set session cookie parameters for security
        // Use PHP 7.3+ array syntax if available, otherwise use legacy parameters
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0, // Session cookie (expires when browser closes)
                'path' => '/',
                'domain' => '', // Empty for localhost/IP compatibility
                'secure' => false, // Allow HTTP for local development
                'httponly' => true,
                'samesite' => 'Lax' // Changed from Strict to Lax for better compatibility
            ]);
        } else {
            // PHP 7.0-7.2 compatibility (no SameSite support)
            $lifetime = 0;
            $path = '/';
            $domain = ''; // Empty for localhost/IP compatibility
            $secure = false; // Allow HTTP for local development
            $httponly = true;

            session_set_cookie_params($lifetime, $path, $domain, $secure, $httponly);
        }

        session_start();
    }
}

/**
 * Check if session is valid and not expired
 *
 * @return bool True if session is valid
 */
function is_session_valid() {
    safe_session_start();

    // Check if logged in
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        return false;
    }

    // Check session timeout (default: 2 hours)
    $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 7200;
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $timeout) {
        // Session expired
        session_unset();
        session_destroy();
        return false;
    }

    // Update last activity time (touch session)
    $_SESSION['last_activity'] = time();

    return true;
}

// =============================================================================
// AUTHENTICATION FUNCTIONS
// =============================================================================

/**
 * Check if user is logged in
 *
 * @return bool True if logged in and session valid, false otherwise
 */
function is_logged_in() {
    return is_session_valid();
}

/**
 * Check if user is logged in
 *
 * Redirects to login page if not logged in
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Require user to be logged in (for API endpoints)
 *
 * Returns JSON error if not logged in
 */
function require_api_login() {
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'data' => null,
            'message' => 'Authentication required'
        ]);
        exit;
    }
}

/**
 * Login admin user
 *
 * @param string $username Username
 * @param string $password Password (plain text)
 * @return bool True if login successful, false otherwise
 */
function admin_login($username, $password) {
    // Use hash_equals() for constant-time comparison to prevent timing attacks
    $usernameMatch = hash_equals((string)ADMIN_USERNAME, (string)$username);
    $passwordMatch = password_verify($password, ADMIN_PASSWORD_HASH);

    if ($usernameMatch && $passwordMatch) {
        safe_session_start();

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        return true;
    }

    return false;
}

/**
 * Logout admin user
 *
 * Properly destroys session and regenerates session ID
 */
function admin_logout() {
    safe_session_start();

    // Unset all session variables
    $_SESSION = [];

    // Destroy session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // Regenerate session ID before destroying (prevent session fixation)
    session_regenerate_id(true);

    // Destroy session
    session_destroy();
}
