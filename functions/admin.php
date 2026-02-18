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
// DATABASE AUTHENTICATION HELPERS
// =============================================================================

/**
 * Check if admin_users table exists in the database
 *
 * @return bool
 */
function admin_users_table_exists() {
    try {
        if (!defined('DB_PATH') || !file_exists(DB_PATH)) {
            return false;
        }
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'")->fetch();
        return (bool)$result;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get admin user from database by username
 *
 * @param string $username
 * @return array|null User row or null if not found / table doesn't exist
 */
function get_admin_user_by_username($username) {
    try {
        if (!defined('DB_PATH') || !file_exists(DB_PATH)) {
            return null;
        }
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if admin_users table exists
        $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'");
        if (!$tableCheck->fetch()) {
            return null;
        }

        $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = :username AND is_active = 1");
        $stmt->execute([':username' => $username]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Update last login timestamp for admin user
 *
 * @param int $userId
 */
function update_admin_last_login($userId) {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $db->prepare("UPDATE admin_users SET last_login_at = :now WHERE id = :id");
        $stmt->execute([':now' => date('Y-m-d H:i:s'), ':id' => $userId]);
    } catch (PDOException $e) {
        // Silently fail - non-critical
    }
}

/**
 * Change admin user's password
 *
 * @param int $userId User ID
 * @param string $currentPassword Current password for verification
 * @param string $newPassword New password (plain text, min 8 chars)
 * @return array ['success' => bool, 'message' => string]
 */
function change_admin_password($userId, $currentPassword, $newPassword) {
    if (strlen($newPassword) < 8) {
        return ['success' => false, 'message' => 'New password must be at least 8 characters'];
    }

    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $db->prepare("SELECT * FROM admin_users WHERE id = :id AND is_active = 1");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE admin_users SET password_hash = :hash, updated_at = :now WHERE id = :id");
        $stmt->execute([
            ':hash' => $newHash,
            ':now' => date('Y-m-d H:i:s'),
            ':id' => $userId,
        ]);

        return ['success' => true, 'message' => 'Password changed successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error'];
    }
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
    $passwordMatch = false;
    $usernameMatch = false;
    $userId = null;
    $displayName = null;

    // Try database first
    $dbUser = get_admin_user_by_username($username);

    $userRole = 'admin'; // Default role

    if ($dbUser !== null) {
        // Database path: user found in DB
        $usernameMatch = true;
        $passwordMatch = password_verify($password, $dbUser['password_hash']);
        $userId = $dbUser['id'];
        $displayName = $dbUser['display_name'] ?: $dbUser['username'];
        $userRole = $dbUser['role'] ?? 'admin';
    } else {
        // Fallback to config constants (backward compatibility)
        // Config fallback users are always admin role
        if (defined('ADMIN_USERNAME') && defined('ADMIN_PASSWORD_HASH')) {
            $usernameMatch = hash_equals((string)ADMIN_USERNAME, (string)$username);
            $passwordMatch = password_verify($password, ADMIN_PASSWORD_HASH);
            $displayName = $username;
        }
    }

    // Dummy password_verify when username not found to prevent timing attacks
    if (!$usernameMatch) {
        password_verify($password, '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012');
    }

    if ($usernameMatch && $passwordMatch) {
        safe_session_start();

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_user_id'] = $userId;
        $_SESSION['admin_display_name'] = $displayName;
        $_SESSION['admin_role'] = $userRole;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        // Update last login timestamp in database
        if ($userId !== null) {
            update_admin_last_login($userId);
        }

        return true;
    }

    return false;
}

// =============================================================================
// ROLE-BASED ACCESS CONTROL
// =============================================================================

/**
 * Get current admin user's role
 *
 * @return string 'admin' or 'agent'
 */
function get_admin_role() {
    return $_SESSION['admin_role'] ?? 'admin';
}

/**
 * Check if current user has admin role
 *
 * @return bool
 */
function is_admin_role() {
    return get_admin_role() === 'admin';
}

/**
 * Require admin role for page access (HTML 403)
 */
function require_admin_role() {
    if (!is_admin_role()) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head>';
        echo '<body style="font-family:sans-serif;text-align:center;padding:50px;">';
        echo '<h1>403 Forbidden</h1><p>Admin role required.</p></body></html>';
        exit;
    }
}

/**
 * Require admin role for API access (JSON 403)
 */
function require_api_admin_role() {
    if (!is_admin_role()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'data' => null,
            'message' => 'Admin role required'
        ]);
        exit;
    }
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
