<?php
/**
 * Integration Tests
 * Tests API endpoints and full workflows
 */

require_once __DIR__ . '/../config.php';

function testApiRequestEndpointExists($test) {
    $apiFile = dirname(__DIR__) . '/api/request.php';
    $test->assertFileExists($apiFile, 'API request endpoint should exist');
}

function testAdminApiEndpointExists($test) {
    $apiFile = dirname(__DIR__) . '/admin/api.php';
    $test->assertFileExists($apiFile, 'Admin API endpoint should exist');
}

function testConfigFilesExist($test) {
    $configFiles = [
        'config.php',
        'config/app.php',
        'config/admin.php',
        'config/security.php',
        'config/database.php',
        'config/cache.php'
    ];

    foreach ($configFiles as $file) {
        $path = dirname(__DIR__) . '/' . $file;
        $test->assertFileExists($path, "Config file {$file} should exist");
    }
}

function testFunctionFilesExist($test) {
    $functionFiles = [
        'functions/helpers.php',
        'functions/cache.php',
        'functions/admin.php',
        'functions/security.php'
    ];

    foreach ($functionFiles as $file) {
        $path = dirname(__DIR__) . '/' . $file;
        $test->assertFileExists($path, "Function file {$file} should exist");
    }
}

function testSecurityFunctionsLoaded($test) {
    $functions = [
        'sanitize_string',
        'sanitize_string_array',
        'get_sanitized_param',
        'get_sanitized_array_param',
        'send_security_headers'
    ];

    foreach ($functions as $func) {
        $test->assertTrue(function_exists($func), "Function {$func} should be defined");
    }
}

function testCacheFunctionsLoaded($test) {
    $functions = [
        'get_data_version',
        'get_cached_credits',
        'invalidate_credits_cache'
    ];

    foreach ($functions as $func) {
        $test->assertTrue(function_exists($func), "Function {$func} should be defined");
    }
}

function testAdminFunctionsLoaded($test) {
    $functions = [
        'safe_session_start',
        'is_session_valid',
        'is_logged_in',
        'require_login',
        'require_api_login',
        'admin_login',
        'admin_logout'
    ];

    foreach ($functions as $func) {
        $test->assertTrue(function_exists($func), "Function {$func} should be defined");
    }
}

function testConstantsDefined($test) {
    $constants = [
        'APP_VERSION',
        'ADMIN_USERNAME',
        'ADMIN_PASSWORD_HASH',
        'SESSION_TIMEOUT',
        'DB_PATH',
        'DATA_VERSION_CACHE_FILE',
        'DATA_VERSION_CACHE_TTL',
        'CREDITS_CACHE_FILE',
        'CREDITS_CACHE_TTL'
    ];

    foreach ($constants as $const) {
        $test->assertTrue(defined($const), "Constant {$const} should be defined");
    }
}

function testAppVersion($test) {
    $version = APP_VERSION;

    $test->assertNotEmpty($version, 'APP_VERSION should not be empty');
    // APP_VERSION uses semantic versioning (e.g., "1.1.0")
    $test->assertTrue(
        preg_match('/^\d+\.\d+\.\d+/', $version) === 1,
        'APP_VERSION should follow semantic versioning format (e.g., 1.1.0)'
    );
}

function testSessionTimeoutValue($test) {
    $timeout = SESSION_TIMEOUT;

    $test->assertGreaterThan(0, $timeout, 'SESSION_TIMEOUT should be positive');
    $test->assertEquals(7200, $timeout, 'Default SESSION_TIMEOUT should be 7200 seconds (2 hours)');
}

function testCacheTTLValues($test) {
    // Data version cache TTL
    $test->assertEquals(600, DATA_VERSION_CACHE_TTL, 'Data version cache TTL should be 600 seconds (10 minutes)');

    // Credits cache TTL
    $test->assertEquals(3600, CREDITS_CACHE_TTL, 'Credits cache TTL should be 3600 seconds (1 hour)');
}

function testDatabasePath($test) {
    $dbPath = DB_PATH;

    $test->assertNotEmpty($dbPath, 'DB_PATH should not be empty');
    $test->assertContains('calendar.db', $dbPath, 'DB_PATH should point to calendar.db');
}

function testIcsParserExists($test) {
    $parserFile = dirname(__DIR__) . '/IcsParser.php';
    $test->assertFileExists($parserFile, 'IcsParser.php should exist');
}

function testIcsParserClass($test) {
    require_once dirname(__DIR__) . '/IcsParser.php';

    $test->assertTrue(class_exists('IcsParser'), 'IcsParser class should be defined');

    // Check if class has required public methods
    $methods = ['getAllEvents', 'getAllOrganizers', 'getAllLocations', 'parseEvent'];

    foreach ($methods as $method) {
        $test->assertTrue(
            method_exists('IcsParser', $method),
            "IcsParser should have {$method} method"
        );
    }
}

function testPublicPagesExist($test) {
    $pages = [
        'index.php',
        'credits.php',
        'export.php',
        'api.php'
    ];

    foreach ($pages as $page) {
        $path = dirname(__DIR__) . '/' . $page;
        $test->assertFileExists($path, "Public page {$page} should exist");
    }
}

function testAdminPagesExist($test) {
    $pages = [
        'admin/index.php',
        'admin/login.php',
        'admin/api.php'
    ];

    foreach ($pages as $page) {
        $path = dirname(__DIR__) . '/' . $page;
        $test->assertFileExists($path, "Admin page {$page} should exist");
    }
}

function testMigrationToolsExist($test) {
    $tools = [
        'tools/import-ics-to-sqlite.php',
        'tools/migrate-add-requests-table.php',
        'tools/migrate-add-credits-table.php',
        'tools/generate-password-hash.php'
    ];

    foreach ($tools as $tool) {
        $path = dirname(__DIR__) . '/' . $tool;
        $test->assertFileExists($path, "Migration tool {$tool} should exist");
    }
}

function testStylesExist($test) {
    $styleFile = dirname(__DIR__) . '/styles/common.css';
    $test->assertFileExists($styleFile, 'Common CSS file should exist');

    // Check file is not empty
    $content = file_get_contents($styleFile);
    $test->assertNotEmpty($content, 'CSS file should not be empty');
}

function testJavascriptExists($test) {
    $jsFiles = [
        'js/translations.js',
        'js/common.js'
    ];

    foreach ($jsFiles as $file) {
        $path = dirname(__DIR__) . '/' . $file;
        $test->assertFileExists($path, "JavaScript file {$file} should exist");
    }
}

function testTranslationsStructure($test) {
    $transFile = dirname(__DIR__) . '/js/translations.js';
    $content = file_get_contents($transFile);

    // Check for language objects
    $test->assertContains('th:', $content, 'Should have Thai translations');
    $test->assertContains('en:', $content, 'Should have English translations');
    $test->assertContains('ja:', $content, 'Should have Japanese translations');
}

function testSecurityHeaders($test) {
    // Start output buffering to capture headers
    ob_start();

    send_security_headers();

    // Get headers (note: headers_list() only works in real HTTP context)
    // In CLI, we just verify the function runs without error

    ob_end_clean();

    // If we got here without error, function executed successfully
    $test->assertTrue(true, 'send_security_headers should execute without error');
}

function testCacheDirectoryStructure($test) {
    $cacheDir = dirname(__DIR__) . '/cache';

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    $test->assertTrue(is_dir($cacheDir), 'Cache directory should exist');
    $test->assertTrue(is_writable($cacheDir), 'Cache directory should be writable');

    // Check .gitignore exists to prevent cache files from being committed
    $gitignore = dirname(__DIR__) . '/cache/.gitignore';

    if (file_exists($gitignore)) {
        $content = file_get_contents($gitignore);
        $test->assertContains('*', $content, 'Cache .gitignore should ignore all files');
        $test->assertContains('!.gitignore', $content, 'Cache .gitignore should keep itself');
    }
}

function testRateLimitingConstants($test) {
    // These should be defined in config/security.php or api/request.php
    $test->assertTrue(defined('RATE_LIMIT_MAX'), 'RATE_LIMIT_MAX should be defined');
    $test->assertTrue(defined('RATE_LIMIT_WINDOW'), 'RATE_LIMIT_WINDOW should be defined');

    // Check values
    $test->assertEquals(10, RATE_LIMIT_MAX, 'RATE_LIMIT_MAX should be 10');
    $test->assertEquals(3600, RATE_LIMIT_WINDOW, 'RATE_LIMIT_WINDOW should be 3600 seconds');
}

function testIcsDirectoryExists($test) {
    $icsDir = dirname(__DIR__) . '/ics';

    $test->assertTrue(is_dir($icsDir), 'ICS directory should exist');
}

function testFullWorkflowSimulation($test) {
    // This test simulates a full user workflow
    // 1. User visits index.php (loads events)
    // 2. Cache is created
    // 3. User views credits.php
    // 4. Credits cache is created

    // Skip if no database
    $dbPath = dirname(__DIR__) . '/calendar.db';
    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    // Clear caches
    invalidate_credits_cache();
    $dataVersionCache = DATA_VERSION_CACHE_FILE;
    if (file_exists($dataVersionCache)) {
        unlink($dataVersionCache);
    }

    // Simulate getting data version (like index.php does)
    $version = get_data_version();
    $test->assertNotNull($version, 'Should get data version');

    // Check cache was created
    $test->assertFileExists($dataVersionCache, 'Data version cache should be created');

    // Simulate getting credits (like credits.php does)
    $credits = get_cached_credits();
    $test->assertTrue(is_array($credits), 'Should get credits array');

    // Check credits cache was created (if we have credits)
    if (!empty($credits)) {
        $creditsCache = CREDITS_CACHE_FILE;
        $test->assertFileExists($creditsCache, 'Credits cache should be created');
    }

    $test->assertTrue(true, 'Full workflow simulation completed successfully');
}

function testErrorHandling($test) {
    // Test safe_error_message function
    $result = safe_error_message('User friendly message', 'Technical details');

    $test->assertNotEmpty($result, 'Error message should not be empty');

    // In production, should not expose technical details
    if (defined('IS_PRODUCTION') && IS_PRODUCTION) {
        $test->assertNotContains('Technical details', $result, 'Should hide technical details in production');
    }
}

function testPasswordHashFormat($test) {
    $hash = ADMIN_PASSWORD_HASH;

    // Check it's bcrypt format ($2y$ or $2a$ or $2b$)
    $test->assertTrue(
        strpos($hash, '$2y$') === 0 ||
        strpos($hash, '$2a$') === 0 ||
        strpos($hash, '$2b$') === 0,
        'Password hash should be bcrypt format'
    );

    // Check length (bcrypt is always 60 characters)
    $test->assertEquals(60, strlen($hash), 'Bcrypt hash should be 60 characters');
}

function testIPWhitelistConfiguration($test) {
    $test->assertTrue(defined('ADMIN_IP_WHITELIST_ENABLED'), 'ADMIN_IP_WHITELIST_ENABLED should be defined');
    $test->assertTrue(defined('ADMIN_ALLOWED_IPS'), 'ADMIN_ALLOWED_IPS should be defined');

    // Check ADMIN_ALLOWED_IPS is an array
    $allowedIps = ADMIN_ALLOWED_IPS;
    $test->assertTrue(is_array($allowedIps), 'ADMIN_ALLOWED_IPS should be an array');

    // Should at least have localhost
    $test->assertContains('127.0.0.1', $allowedIps, 'Should allow localhost IPv4');
}

function testDocumentationExists($test) {
    $docs = [
        'README.md',
        'QUICKSTART.md',
        'INSTALLATION.md',
        'CHANGELOG.md',
        'CLAUDE.md',
        'TESTING.md',
        'SQLITE_MIGRATION.md'
    ];

    foreach ($docs as $doc) {
        $path = dirname(__DIR__) . '/' . $doc;
        $test->assertFileExists($path, "Documentation {$doc} should exist");
    }
}

function testLicenseExists($test) {
    $licensePath = dirname(__DIR__) . '/LICENSE';

    // License file is optional, but if exists should not be empty
    if (file_exists($licensePath)) {
        $content = file_get_contents($licensePath);
        $test->assertNotEmpty($content, 'LICENSE file should not be empty if it exists');
    } else {
        // Just pass if no license file
        $test->assertTrue(true, 'LICENSE file is optional');
    }
}
