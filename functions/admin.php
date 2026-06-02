<?php
/**
 * Admin Functions
 * Idol Stage Timetable v1.1.0
 */

// =============================================================================
// LOGIN RATE LIMITING
// =============================================================================

/**
 * Check if an IP is currently rate-limited for login attempts
 *
 * @param string $ip Client IP address
 * @return array ['blocked' => bool, 'wait' => int (seconds remaining)]
 */
function check_login_rate_limit($ip) {
    $limitFile = dirname(__DIR__) . '/cache/login_attempts.json';
    $now       = time();
    $window    = 900; // 15 minutes
    $maxAttempts = 5;

    $data = [];
    if (file_exists($limitFile)) {
        $content = @file_get_contents($limitFile);
        if ($content) {
            $data = json_decode($content, true) ?? [];
        }
    }

    $attempts = isset($data[$ip]) ? array_values(array_filter($data[$ip], function($ts) use ($now, $window) {
        return ($now - $ts) < $window;
    })) : [];

    if (count($attempts) >= $maxAttempts) {
        $oldest = min($attempts);
        return ['blocked' => true, 'wait' => max(0, $window - ($now - $oldest))];
    }

    return ['blocked' => false, 'wait' => 0];
}

/**
 * Record a failed login attempt for an IP
 *
 * @param string $ip Client IP address
 */
function record_failed_login($ip) {
    $limitFile = dirname(__DIR__) . '/cache/login_attempts.json';
    $now    = time();
    $window = 900;

    $data = [];
    if (file_exists($limitFile)) {
        $content = @file_get_contents($limitFile);
        if ($content) {
            $data = json_decode($content, true) ?? [];
        }
    }

    if (!isset($data[$ip])) {
        $data[$ip] = [];
    }
    $data[$ip][] = $now;

    // Keep only attempts within the window
    $data[$ip] = array_values(array_filter($data[$ip], function($ts) use ($now, $window) {
        return ($now - $ts) < $window;
    }));

    @file_put_contents($limitFile, json_encode($data), LOCK_EX);
}

/**
 * Clear login attempt records for an IP (after successful login)
 *
 * @param string $ip Client IP address
 */
function clear_login_attempts($ip) {
    $limitFile = dirname(__DIR__) . '/cache/login_attempts.json';
    if (!file_exists($limitFile)) return;

    $content = @file_get_contents($limitFile);
    if (!$content) return;

    $data = json_decode($content, true) ?? [];
    unset($data[$ip]);
    @file_put_contents($limitFile, json_encode($data), LOCK_EX);
}

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
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                   || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                   || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0, // Session cookie (expires when browser closes)
                'path' => '/',
                'domain' => '', // Empty for localhost/IP compatibility
                'secure' => $isHttps, // Send cookie over HTTPS only when available
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        } else {
            // PHP 7.0-7.2 compatibility (no SameSite support)
            $lifetime = 0;
            $path = '/';
            $domain = ''; // Empty for localhost/IP compatibility
            $secure = $isHttps; // Send cookie over HTTPS only when available
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

function get_admin_user_by_id($userId) {
    try {
        if (!defined('DB_PATH') || !file_exists(DB_PATH)) {
            return null;
        }
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $db->prepare("SELECT * FROM admin_users WHERE id = :id AND is_active = 1");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function admin_user_has_enabled_twofa(array $user): bool {
    return !empty($user['twofa_enabled']) && !empty($user['twofa_secret']);
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
function admin_finalize_login($username, $userId, $displayName, $userRole) {
    safe_session_start();

    session_regenerate_id(true);

    unset(
        $_SESSION['twofa_pending_user_id'],
        $_SESSION['twofa_pending_username'],
        $_SESSION['twofa_pending_display_name'],
        $_SESSION['twofa_pending_role'],
        $_SESSION['twofa_pending_expires']
    );

    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = $username;
    $_SESSION['admin_user_id'] = $userId;
    $_SESSION['admin_display_name'] = $displayName;
    $_SESSION['admin_role'] = $userRole;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();

    if ($userId !== null) {
        update_admin_last_login($userId);
    }
}

function admin_login_attempt($username, $password) {
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
        if ($dbUser !== null && admin_user_has_enabled_twofa($dbUser)) {
            safe_session_start();
            session_regenerate_id(true);
            $_SESSION['twofa_pending_user_id'] = $userId;
            $_SESSION['twofa_pending_username'] = $username;
            $_SESSION['twofa_pending_display_name'] = $displayName;
            $_SESSION['twofa_pending_role'] = $userRole;
            $_SESSION['twofa_pending_expires'] = time() + 300;
            unset($_SESSION['admin_logged_in']);

            if (function_exists('audit_log')) {
                audit_log([
                    'action'         => 'twofa_required',
                    'outcome'        => 'success',
                    'actor_user_id'  => $userId,
                    'actor_username' => $username,
                    'actor_role'     => $userRole,
                    'entity_type'    => 'session',
                    'entity_id'      => null,
                    'entity_label'   => $username,
                ]);
            }
            return ['success' => false, 'twofa_required' => true, 'message' => 'Two-factor authentication required'];
        }

        admin_finalize_login($username, $userId, $displayName, $userRole);
        return ['success' => true, 'twofa_required' => false, 'message' => 'Login successful'];
    }

    // Login failure — audit with distinct error code
    $errorCode = !$usernameMatch ? 'user_not_found' : 'invalid_password';
    if (function_exists('audit_log')) {
        audit_log([
            'action'         => 'login_failure',
            'outcome'        => 'failure',
            'error_code'     => $errorCode,
            'actor_user_id'  => null,
            'actor_username' => $username,
            'actor_role'     => null,
            'entity_type'    => 'session',
            'entity_id'      => null,
            'entity_label'   => $username,
        ]);
    }
    return ['success' => false, 'twofa_required' => false, 'message' => 'Invalid credentials'];
}

function admin_login($username, $password) {
    $result = admin_login_attempt($username, $password);
    return !empty($result['success']);
}

function admin_complete_twofa(string $code): array {
    safe_session_start();

    $userId = $_SESSION['twofa_pending_user_id'] ?? null;
    $expires = intval($_SESSION['twofa_pending_expires'] ?? 0);
    if (!$userId || $expires < time()) {
        unset(
            $_SESSION['twofa_pending_user_id'],
            $_SESSION['twofa_pending_username'],
            $_SESSION['twofa_pending_display_name'],
            $_SESSION['twofa_pending_role'],
            $_SESSION['twofa_pending_expires']
        );
        return ['success' => false, 'message' => 'Two-factor session expired'];
    }

    $user = get_admin_user_by_id($userId);
    if (!$user || !admin_user_has_enabled_twofa($user)) {
        return ['success' => false, 'message' => 'Two-factor authentication is not available'];
    }

    try {
        safe_session_start();
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $trimmed = trim($code);

        $isBackupCode = !preg_match('/^\d{6}$/', $trimmed);
        if (!$isBackupCode) {
            $check = totp_verify($user['twofa_secret'], $trimmed, null, 1, 30, 6, isset($user['twofa_last_used_step']) ? intval($user['twofa_last_used_step']) : null);
            if (empty($check['valid'])) {
                if (function_exists('audit_log')) {
                    audit_log([
                        'action'         => 'twofa_failure',
                        'outcome'        => 'failure',
                        'error_code'     => 'twofa_invalid',
                        'actor_user_id'  => $userId,
                        'actor_username' => $user['username'],
                        'actor_role'     => $user['role'] ?? 'admin',
                        'entity_type'    => 'session',
                        'entity_id'      => null,
                        'entity_label'   => $user['username'],
                    ]);
                }
                return ['success' => false, 'message' => 'Invalid authentication code'];
            }
            $stmt = $db->prepare("UPDATE admin_users SET twofa_last_used_step = :step, updated_at = :now WHERE id = :id");
            $stmt->execute([':step' => $check['step'], ':now' => date('Y-m-d H:i:s'), ':id' => $userId]);
        } else {
            $backup = twofa_consume_backup_code($user['twofa_backup_codes'] ?? '[]', $trimmed);
            if (empty($backup['valid'])) {
                if (function_exists('audit_log')) {
                    audit_log([
                        'action'         => 'twofa_failure',
                        'outcome'        => 'failure',
                        'error_code'     => 'twofa_backup_invalid',
                        'actor_user_id'  => $userId,
                        'actor_username' => $user['username'],
                        'actor_role'     => $user['role'] ?? 'admin',
                        'entity_type'    => 'session',
                        'entity_id'      => null,
                        'entity_label'   => $user['username'],
                    ]);
                }
                return ['success' => false, 'message' => 'Invalid recovery code'];
            }
            $stmt = $db->prepare("UPDATE admin_users SET twofa_backup_codes = :codes, updated_at = :now WHERE id = :id");
            $stmt->execute([':codes' => $backup['hashes_json'], ':now' => date('Y-m-d H:i:s'), ':id' => $userId]);
        }

        $finalUsername = $_SESSION['twofa_pending_username'] ?? $user['username'];
        $finalRole     = $_SESSION['twofa_pending_role'] ?? ($user['role'] ?? 'admin');
        admin_finalize_login(
            $finalUsername,
            $userId,
            $_SESSION['twofa_pending_display_name'] ?? ($user['display_name'] ?: $user['username']),
            $finalRole
        );
        // Audit after session is set
        if (function_exists('audit_log')) {
            $auditAction = $isBackupCode ? 'twofa_backup_used' : 'twofa_success';
            audit_log([
                'action'         => $auditAction,
                'outcome'        => 'success',
                'actor_user_id'  => $userId,
                'actor_username' => $finalUsername,
                'actor_role'     => $finalRole,
                'entity_type'    => 'session',
                'entity_id'      => null,
                'entity_label'   => $finalUsername,
            ]);
        }
        return ['success' => true, 'message' => 'Two-factor authentication verified'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error'];
    }
}

// =============================================================================
// ROLE-BASED ACCESS CONTROL
// =============================================================================

/**
 * Get current admin user's role
 *
 * @return string 'admin', 'agent', or 'organizer'
 */
function get_admin_role() {
    $role = $_SESSION['admin_role'] ?? '';
    return in_array($role, ['admin', 'agent', 'organizer'], true) ? $role : 'agent';
}

/**
 * Check if current user has admin role
 *
 * @return bool
 */
function is_admin_role() {
    return get_admin_role() === 'admin';
}

function is_agent_role() {
    return get_admin_role() === 'agent';
}

function is_organizer_role() {
    return get_admin_role() === 'organizer';
}

function current_admin_user_id() {
    return isset($_SESSION['admin_user_id']) ? intval($_SESSION['admin_user_id']) : null;
}

function can_manage_event($eventId) {
    $eventId = intval($eventId);
    if ($eventId <= 0) {
        return false;
    }
    if (is_admin_role() || is_agent_role()) {
        return true;
    }
    if (!is_organizer_role()) {
        return false;
    }

    $userId = current_admin_user_id();
    if ($userId === null || !defined('DB_PATH') || !file_exists(DB_PATH)) {
        return false;
    }

    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $table = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='event_organizers'")->fetchColumn();
        if (!$table) {
            return false;
        }
        $stmt = $db->prepare("SELECT 1 FROM event_organizers WHERE event_id = :event_id AND user_id = :user_id LIMIT 1");
        $stmt->execute([':event_id' => $eventId, ':user_id' => $userId]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
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

    // Audit before session is destroyed
    if (function_exists('audit_log') && !empty($_SESSION['admin_logged_in'])) {
        audit_log([
            'action'         => 'logout',
            'outcome'        => 'success',
            'actor_user_id'  => isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : null,
            'actor_username' => $_SESSION['admin_username'] ?? null,
            'actor_role'     => $_SESSION['admin_role'] ?? null,
            'entity_type'    => 'session',
            'entity_id'      => null,
            'entity_label'   => $_SESSION['admin_username'] ?? null,
        ]);
    }

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
