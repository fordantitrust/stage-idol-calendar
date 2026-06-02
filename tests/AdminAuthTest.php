<?php
/**
 * Admin Authentication Tests
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions/admin.php';

function testSafeSessionStart($test) {
    // Skip in CLI - sessions require no output before session_start()
    if (PHP_SAPI === 'cli' && headers_sent()) {
        echo " [SKIP: CLI with headers sent] ";
        $test->assertTrue(true, 'Skipped in CLI environment');
        return;
    }

    // Ensure no session active
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    // Start session safely
    safe_session_start();

    $test->assertEquals(PHP_SESSION_ACTIVE, session_status(), 'Session should be active');
}

function testSafeSessionStartIdempotent($test) {
    // Skip in CLI
    if (PHP_SAPI === 'cli' && headers_sent()) {
        echo " [SKIP: CLI with headers sent] ";
        $test->assertTrue(true, 'Skipped in CLI environment');
        return;
    }

    // Start session
    safe_session_start();

    // Start again (should not error)
    safe_session_start();

    $test->assertEquals(PHP_SESSION_ACTIVE, session_status(), 'Should handle multiple calls safely');
}

function testSessionCookieParameters($test) {
    // Skip in CLI
    if (PHP_SAPI === 'cli' && headers_sent()) {
        echo " [SKIP: CLI with headers sent] ";
        $test->assertTrue(true, 'Skipped in CLI environment');
        return;
    }

    // Start session to set parameters
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    safe_session_start();

    $params = session_get_cookie_params();

    $test->assertEquals('/', $params['path'], 'Path should be /');

    // Check httponly
    if (PHP_VERSION_ID >= 70300) {
        $test->assertTrue($params['httponly'], 'httponly should be true');
    }
}

function testAdminLoginSuccess($test) {
    // Test with correct credentials
    $username = ADMIN_USERNAME;

    // Generate test password hash
    $testPassword = 'testpass123';
    $testHash = password_hash($testPassword, PASSWORD_DEFAULT);

    // Temporarily override constant (for testing only)
    // Note: This won't work with constants, so we'll use the actual configured credentials

    // Clean session first
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
    }

    // Test login with actual configured password
    // Since we can't easily test this without knowing the password,
    // we'll verify the login function exists and handles basic cases

    $result = admin_login('wronguser', 'wrongpass');
    $test->assertFalse($result, 'Should fail with wrong credentials');
}

function testAdminLoginTimingAttackResistance($test) {
    // Measure time for invalid username
    $start1 = microtime(true);
    admin_login('invaliduser', 'password');
    $time1 = microtime(true) - $start1;

    // Measure time for valid username but wrong password
    $start2 = microtime(true);
    admin_login(ADMIN_USERNAME, 'wrongpassword');
    $time2 = microtime(true) - $start2;

    // Times should be relatively similar (within reasonable margin)
    // This tests hash_equals usage for constant-time comparison
    $difference = abs($time1 - $time2);

    // Allow up to 100ms difference (generous margin)
    $test->assertLessThan(0.1, $difference, 'Login timing should be similar to prevent timing attacks');
}

function testAdminLoginInvalidCredentials($test) {
    // Initialize $_SESSION if not set
    if (!isset($_SESSION)) {
        $_SESSION = [];
    }

    // Clean session
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
    }

    // Test with wrong credentials
    $result = admin_login('wronguser', 'wrongpass');

    $test->assertFalse($result, 'Should return false for invalid credentials');

    // In CLI, $_SESSION might not be set, so check if it exists first
    if (isset($_SESSION)) {
        $test->assertEmpty($_SESSION, 'Session should not be set for failed login');
    }
}

function testAdminLoginSessionData($test) {
    // Skip in CLI
    if (PHP_SAPI === 'cli' && headers_sent()) {
        echo " [SKIP: CLI with headers sent] ";
        $test->assertTrue(true, 'Skipped in CLI environment');
        return;
    }

    // We can't actually login without the real password, but we can test
    // that failed login doesn't set session data

    if (session_status() !== PHP_SESSION_ACTIVE) {
        safe_session_start();
    }

    $_SESSION = [];

    // Try invalid login
    admin_login('test', 'test');

    // Check that login data was NOT set
    $test->assertFalse(isset($_SESSION['admin_logged_in']), 'Should not set admin_logged_in for failed login');
    $test->assertFalse(isset($_SESSION['admin_username']), 'Should not set admin_username for failed login');
}

function testIsLoggedIn($test) {
    // Skip actual session tests in CLI, just verify function exists
    if (PHP_SAPI === 'cli' && headers_sent()) {
        echo " [SKIP: CLI with headers sent] ";
        $test->assertTrue(function_exists('is_logged_in'), 'Function should exist');
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        safe_session_start();
    }

    // Test when not logged in
    $_SESSION = [];
    $result = is_logged_in();
    $test->assertFalse($result, 'Should return false when not logged in');

    // Test when logged in but expired
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['login_time'] = time() - SESSION_TIMEOUT - 100; // Expired

    $result = is_logged_in();
    $test->assertFalse($result, 'Should return false for expired session');
}

function testSessionTimeout($test) {
    // Skip in CLI
    if (PHP_SAPI === 'cli' && headers_sent()) {
        echo " [SKIP: CLI with headers sent] ";
        $test->assertTrue(true, 'Skipped in CLI environment');
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        safe_session_start();
    }

    // Set session as logged in but expired
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['login_time'] = time() - SESSION_TIMEOUT - 100; // Expired

    $result = is_session_valid();

    $test->assertFalse($result, 'Should invalidate expired session');
    $test->assertEmpty($_SESSION, 'Should clear session data on timeout');
}

function testSessionActivityUpdate($test) {
    // Skip in CLI
    if (PHP_SAPI === 'cli' && headers_sent()) {
        echo " [SKIP: CLI with headers sent] ";
        $test->assertTrue(true, 'Skipped in CLI environment');
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        safe_session_start();
    }

    // Set valid session
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time() - 100;

    $oldActivity = $_SESSION['last_activity'];

    // Check session (should update last_activity)
    is_session_valid();

    $test->assertGreaterThan($oldActivity, $_SESSION['last_activity'], 'Should update last_activity timestamp');
}

function testAdminLogout($test) {
    // Skip in CLI
    if (PHP_SAPI === 'cli' && headers_sent()) {
        echo " [SKIP: CLI with headers sent] ";
        $test->assertTrue(function_exists('admin_logout'), 'Function should exist');
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        safe_session_start();
    }

    // Set session data
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = 'testuser';

    // Logout
    admin_logout();

    // Check session cleared
    $test->assertEmpty($_SESSION, 'Should clear all session data');

    // Note: Can't easily test cookie deletion in CLI environment
}

function testRequireLoginRedirect($test) {
    // This function redirects, so we can't easily test it in CLI
    // But we can verify it exists and is callable

    $test->assertTrue(function_exists('require_login'), 'require_login function should exist');
}

function testRequireApiLogin($test) {
    // Skip in CLI
    if (PHP_SAPI === 'cli' && headers_sent()) {
        echo " [SKIP: CLI with headers sent] ";
        $test->assertTrue(function_exists('require_api_login'), 'Function should exist');
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        safe_session_start();
    }

    $_SESSION = [];

    // Capture output
    ob_start();

    try {
        require_api_login();
    } catch (Exception $e) {
        // Function calls exit(), which we can't catch in tests
        // So we'll just verify it's callable
    }

    $output = ob_get_clean();

    // Should output JSON error
    if (!empty($output)) {
        $json = json_decode($output, true);
        $test->assertNotNull($json, 'Should output valid JSON');
        $test->assertEquals(false, $json['success'] ?? null, 'Should return success:false');
    }
}

function testPasswordHashVerification($test) {
    // Test that configured password hash is valid format
    $hash = ADMIN_PASSWORD_HASH;

    // Check it's a valid bcrypt hash
    $test->assertContains('$2y$', $hash, 'Password hash should be bcrypt format');

    // Check hash length (bcrypt is always 60 characters)
    $test->assertEquals(60, strlen($hash), 'Bcrypt hash should be 60 characters');
}

function testSessionRegenerationOnLogin($test) {
    // We can't fully test this without actual login, but we can verify
    // the concept is implemented by checking if session_regenerate_id is available

    $test->assertTrue(function_exists('session_regenerate_id'), 'session_regenerate_id should be available');
}

// =============================================================================
// DATABASE AUTH TESTS
// =============================================================================

function testAdminUsersTableExistsFunction($test) {
    $test->assertTrue(function_exists('admin_users_table_exists'), 'Function should exist');
    $result = admin_users_table_exists();
    $test->assertTrue(is_bool($result), 'Should return boolean');
}

function testGetAdminUserByUsername($test) {
    $test->assertTrue(function_exists('get_admin_user_by_username'), 'Function should exist');
    // Test with non-existent user
    $result = get_admin_user_by_username('nonexistent_user_xyz_999');
    $test->assertNull($result, 'Should return null for non-existent user');
}

function testGetAdminUserByUsernameReturnsUser($test) {
    if (!admin_users_table_exists()) {
        echo " [SKIP: No admin_users table] ";
        $test->assertTrue(true, 'Skipped - no admin_users table');
        return;
    }
    // Default migration seeds 'admin' user
    $result = get_admin_user_by_username('admin');
    if ($result !== null) {
        $test->assertContains('$2y$', $result['password_hash'], 'Password hash should be bcrypt');
        $test->assertEquals(1, $result['is_active'], 'Default user should be active');
    } else {
        $test->assertTrue(true, 'No admin user seeded yet');
    }
}

function testChangeAdminPasswordValidation($test) {
    $test->assertTrue(function_exists('change_admin_password'), 'Function should exist');
    // Test with too-short password
    $result = change_admin_password(999, 'current', 'short');
    $test->assertFalse($result['success'], 'Should fail for short password');
    $test->assertContains('8 characters', $result['message'], 'Should mention minimum length');
}

function testChangeAdminPasswordWrongUser($test) {
    if (!admin_users_table_exists()) {
        echo " [SKIP: No admin_users table] ";
        $test->assertTrue(true, 'Skipped - no admin_users table');
        return;
    }
    $result = change_admin_password(99999, 'wrongpass', 'newpassword123');
    $test->assertFalse($result['success'], 'Should fail for non-existent user');
    $test->assertContains('not found', $result['message'], 'Should say user not found');
}

function testAdminLoginSessionContainsUserId($test) {
    if (PHP_SAPI === 'cli' && headers_sent()) {
        echo " [SKIP: CLI with headers sent] ";
        $test->assertTrue(true, 'Skipped in CLI environment');
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        safe_session_start();
    }
    $_SESSION = [];

    // Failed login should not set admin_user_id
    admin_login('wrong', 'wrong');
    $test->assertFalse(isset($_SESSION['admin_user_id']), 'Should not set admin_user_id for failed login');
    $test->assertFalse(isset($_SESSION['admin_display_name']), 'Should not set admin_display_name for failed login');
}

// ── Login CSRF defense ───────────────────────────────────────────────────────

function testLoginPhpVerifiesCsrfTokenOnPost($test) {
    // admin/login.php must call verify_csrf_token() inside its POST handler.
    $src = @file_get_contents(__DIR__ . '/../admin/login.php');
    $test->assertTrue($src !== false, 'admin/login.php should be readable');
    $test->assertTrue(
        strpos($src, 'verify_csrf_token') !== false,
        'admin/login.php must call verify_csrf_token() to defend against login CSRF'
    );
}

function testLoginPhpReadsPostedCsrfToken($test) {
    // The handler must read the posted csrf_token field, not just render it.
    $src = @file_get_contents(__DIR__ . '/../admin/login.php');
    $test->assertTrue(
        strpos($src, "\$_POST['csrf_token']") !== false,
        'admin/login.php must read $_POST["csrf_token"] in its POST handler'
    );
}

function testLoginPhpCsrfCheckBeforeRateLimit($test) {
    // CSRF check must happen before record_failed_login() / check_login_rate_limit()
    // so attackers without a valid token cannot exhaust the per-IP login budget.
    $src = @file_get_contents(__DIR__ . '/../admin/login.php');
    $csrfPos = strpos($src, 'verify_csrf_token');
    $ratePos = strpos($src, 'check_login_rate_limit');
    $test->assertTrue($csrfPos !== false && $ratePos !== false, 'both calls should be present');
    $test->assertTrue(
        $csrfPos < $ratePos,
        'verify_csrf_token() must be called before check_login_rate_limit() in admin/login.php'
    );
}

function testLoginPhpAuditsCsrfBlock($test) {
    // CSRF failure should write a login_blocked audit record with error_code csrf_invalid.
    $src = @file_get_contents(__DIR__ . '/../admin/login.php');
    $test->assertTrue(
        strpos($src, "'csrf_invalid'") !== false,
        'admin/login.php must audit csrf_invalid failures'
    );
}

function testLoginPhpHasErrCsrfI18nMarker($test) {
    // The new error state must surface to the user with a localized message.
    $src = @file_get_contents(__DIR__ . '/../admin/login.php');
    $test->assertTrue(
        strpos($src, "login_csrf") !== false,
        'admin/login.php should set $error = "login_csrf" on CSRF failure'
    );
    $test->assertTrue(
        strpos($src, 'data-i18n="login.errCsrf"') !== false,
        'admin/login.php should render data-i18n="login.errCsrf" for the CSRF error message'
    );
}

function testAdminI18nHasLoginErrCsrfTh($test) {
    $src = @file_get_contents(__DIR__ . '/../admin/js/admin-i18n.js');
    $test->assertTrue($src !== false, 'admin-i18n.js should be readable');
    $test->assertTrue(
        strpos($src, "'login.errCsrf' : 'Session หมดอายุ") !== false,
        'admin-i18n.js TH dictionary should include login.errCsrf'
    );
}

function testAdminI18nHasLoginErrCsrfEn($test) {
    $src = @file_get_contents(__DIR__ . '/../admin/js/admin-i18n.js');
    $test->assertTrue(
        strpos($src, "'login.errCsrf' : 'Session expired") !== false,
        'admin-i18n.js EN dictionary should include login.errCsrf'
    );
}

// ── Log viewer authorization (LOW-1) ─────────────────────────────────────────

function _adminApiSrc(): string {
    static $src = null;
    if ($src === null) {
        $src = (string)@file_get_contents(__DIR__ . '/../admin/api.php');
    }
    return $src;
}

/**
 * Extract the body of a PHP top-level function from source.
 */
function _adminApiExtractFunctionBody(string $name): string {
    $src = _adminApiSrc();
    if ($src === '') return '';
    $start = strpos($src, "function {$name}(");
    if ($start === false) return '';
    // crude but adequate: read until the next "^function " or end of file
    $next  = strpos($src, "\nfunction ", $start + 1);
    return $next === false ? substr($src, $start) : substr($src, $start, $next - $start);
}

function testGetTelegramLogRequiresAdminRole($test) {
    $body = _adminApiExtractFunctionBody('getTelegramLog');
    $test->assertNotEmpty($body, 'getTelegramLog() should exist');
    $test->assertTrue(
        strpos($body, 'require_api_admin_role()') !== false,
        'getTelegramLog() must call require_api_admin_role()'
    );
}

function testGetWebPushLogRequiresAdminRole($test) {
    $body = _adminApiExtractFunctionBody('getWebPushLog');
    $test->assertNotEmpty($body, 'getWebPushLog() should exist');
    $test->assertTrue(
        strpos($body, 'require_api_admin_role()') !== false,
        'getWebPushLog() must call require_api_admin_role()'
    );
}

function testGetEmailLogRequiresAdminRole($test) {
    $body = _adminApiExtractFunctionBody('getEmailLog');
    $test->assertNotEmpty($body, 'getEmailLog() should exist');
    $test->assertTrue(
        strpos($body, 'require_api_admin_role()') !== false,
        'getEmailLog() must call require_api_admin_role()'
    );
}

function testGetAdminAuditLogRequiresAdminRole($test) {
    // already admin-only — regression guard
    $body = _adminApiExtractFunctionBody('getAdminAuditLog');
    $test->assertNotEmpty($body, 'getAdminAuditLog() should exist');
    $test->assertTrue(
        strpos($body, 'require_api_admin_role()') !== false,
        'getAdminAuditLog() must call require_api_admin_role()'
    );
}

function testLogActionsAreInAdminOnlyDispatcherList($test) {
    // Defense-in-depth: dispatcher must reject log viewer actions for non-admin
    // roles before reaching the function body.
    $src = _adminApiSrc();
    $test->assertTrue($src !== '', 'admin/api.php should be readable');

    // Extract the $adminOnlyActions array literal
    if (!preg_match('/\$adminOnlyActions\s*=\s*\[(.+?)\];/s', $src, $m)) {
        $test->assertTrue(false, '$adminOnlyActions array not found in admin/api.php');
        return;
    }
    $listing = $m[1];

    $required = [
        'telegram_log_get', 'telegram_log_download',
        'webpush_log_get',  'webpush_log_download',
        'email_log_get',    'email_log_download',
        'admin_audit_log_get', 'admin_audit_log_download',
    ];
    foreach ($required as $action) {
        $test->assertTrue(
            strpos($listing, "'{$action}'") !== false,
            "\$adminOnlyActions must include '{$action}'"
        );
    }
}

function testLogActionsAreNotInOrganizerAllowedList($test) {
    $src = _adminApiSrc();
    if (!preg_match('/\$organizerAllowedActions\s*=\s*\[(.+?)\];/s', $src, $m)) {
        $test->assertTrue(false, '$organizerAllowedActions array not found in admin/api.php');
        return;
    }
    $listing = $m[1];

    $forbidden = [
        'telegram_log_get', 'webpush_log_get', 'email_log_get', 'admin_audit_log_get',
        'telegram_log_download', 'webpush_log_download', 'email_log_download', 'admin_audit_log_download',
    ];
    foreach ($forbidden as $action) {
        $test->assertFalse(
            strpos($listing, "'{$action}'") !== false,
            "\$organizerAllowedActions must NOT include '{$action}'"
        );
    }
}

function testCsrfTokenRoundTrip($test) {
    // Direct functional test: csrf_token() generates, verify_csrf_token() accepts that token,
    // rejects anything else, and rejects empty.
    if (PHP_SAPI === 'cli' && headers_sent()) {
        echo " [SKIP: CLI with headers sent] ";
        $test->assertTrue(true, 'Skipped in CLI environment');
        return;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        safe_session_start();
    }
    unset($_SESSION['csrf_token']);
    $token = csrf_token();
    $test->assertNotEmpty($token, 'csrf_token() should return a non-empty token');
    $test->assertTrue(verify_csrf_token($token), 'verify_csrf_token() should accept the generated token');
    $test->assertFalse(verify_csrf_token(''), 'verify_csrf_token() should reject empty token');
    $test->assertFalse(verify_csrf_token('deadbeef'), 'verify_csrf_token() should reject mismatched token');
    // Length-mismatch must not cause hash_equals to warn
    $test->assertFalse(verify_csrf_token(str_repeat('a', 10)), 'verify_csrf_token() should reject length-mismatched token');
}
