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
    $test->assertContains('data/calendar.db', $dbPath, 'DB_PATH should point to data/calendar.db');
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
    $dbPath = DB_PATH;
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

// ============================================================================
// MULTI-EVENT SUPPORT TESTS
// ============================================================================

function testMultiEventConstantsDefined($test) {
    $test->assertTrue(defined('MULTI_EVENT_MODE'), 'MULTI_EVENT_MODE should be defined');
    $test->assertTrue(defined('DEFAULT_EVENT_SLUG'), 'DEFAULT_EVENT_SLUG should be defined');
    $test->assertNotEmpty(DEFAULT_EVENT_SLUG, 'DEFAULT_EVENT_SLUG should not be empty');
}

function testMultiEventHelperFunctionsExist($test) {
    $functions = [
        'get_current_event_slug',
        'get_event_by_slug',
        'get_event_id',
        'get_all_active_events',
        'get_event_venue_mode',
        'event_url'
    ];

    foreach ($functions as $func) {
        $test->assertTrue(function_exists($func), "Function {$func} should be defined");
    }
}

function testGetCurrentEventSlugDefault($test) {
    // Without $_GET['event'], should return DEFAULT_EVENT_SLUG
    unset($_GET['event']);
    $slug = get_current_event_slug();
    $test->assertEquals(DEFAULT_EVENT_SLUG, $slug, 'Should return default slug when no event parameter');
}

function testGetCurrentEventSlugSanitization($test) {
    $_GET['event'] = 'valid-slug_123';
    $slug = get_current_event_slug();
    $test->assertEquals('valid-slug_123', $slug, 'Should accept valid slug characters');

    $_GET['event'] = 'invalid<script>slug';
    $slug = get_current_event_slug();
    $test->assertEquals('invalidscriptslug', $slug, 'Should sanitize invalid characters from slug');

    // Cleanup
    unset($_GET['event']);
}

function testGetEventVenueModeFallback($test) {
    // When eventMeta is null, should fall back to VENUE_MODE constant
    $mode = get_event_venue_mode(null);
    $test->assertEquals(VENUE_MODE, $mode, 'Should fall back to VENUE_MODE when eventMeta is null');

    // When eventMeta has venue_mode, should use it
    $mode = get_event_venue_mode(['venue_mode' => 'single']);
    $test->assertEquals('single', $mode, 'Should use venue_mode from eventMeta');

    $mode = get_event_venue_mode(['venue_mode' => 'multi']);
    $test->assertEquals('multi', $mode, 'Should use venue_mode from eventMeta');
}

function testEventUrlWithoutMultiEvent($test) {
    $url = event_url('index.php', DEFAULT_EVENT_SLUG);
    $test->assertEquals('/', $url, 'Should return root path for index.php with default slug');
}

function testEventUrlWithEventSlug($test) {
    if (!MULTI_EVENT_MODE) {
        $test->assertTrue(true, 'Skipped: MULTI_EVENT_MODE is disabled');
        return;
    }

    $url = event_url('index.php', 'test-event');
    $test->assertContains('/event/test-event', $url, 'Should use clean event path');
    $test->assertTrue(strpos($url, 'event=') === false, 'Should not use query string for event');
}

function testEventUrlCleanUrl($test) {
    $url = event_url('credits.php', DEFAULT_EVENT_SLUG);
    $test->assertEquals('/credits', $url, 'Should remove .php extension for clean URL');

    $url2 = event_url('how-to-use.php', DEFAULT_EVENT_SLUG);
    $test->assertEquals('/how-to-use', $url2, 'Should remove .php for hyphenated pages');
}

function testEventUrlCleanEventPath($test) {
    if (!MULTI_EVENT_MODE) {
        $test->assertTrue(true, 'Skipped: MULTI_EVENT_MODE is disabled');
        return;
    }

    $url = event_url('credits.php', 'feb-2026');
    $test->assertEquals('/event/feb-2026/credits', $url, 'Should build /event/slug/page path');

    $url2 = event_url('index.php', 'feb-2026');
    $test->assertEquals('/event/feb-2026', $url2, 'Should build /event/slug for index');
}

function testEventUrlWithExtraParams($test) {
    if (!MULTI_EVENT_MODE) {
        $test->assertTrue(true, 'Skipped: MULTI_EVENT_MODE is disabled');
        return;
    }

    $url = event_url('index.php', 'test-event', ['page' => '2']);
    $test->assertContains('/event/test-event', $url, 'Should include event in path');
    $test->assertContains('page=2', $url, 'Should include extra parameters as query string');
}

function testMigrationToolExists($test) {
    $migrationFile = dirname(__DIR__) . '/tools/migrate-add-events-meta-table.php';
    $test->assertFileExists($migrationFile, 'Multi-event migration script should exist');
}

function testMigrationRenameToolExists($test) {
    $migrationFile = dirname(__DIR__) . '/tools/migrate-rename-tables-columns.php';
    $test->assertFileExists($migrationFile, 'Table/column rename migration script should exist');
}

function testIcsParserAcceptsEventMetaId($test) {
    require_once dirname(__DIR__) . '/IcsParser.php';

    // Constructor should accept 4th parameter for eventMetaId
    $parser = new IcsParser('ics', false, 'data/calendar.db', null);
    $test->assertTrue($parser instanceof IcsParser, 'IcsParser should accept null eventMetaId');

    $parser2 = new IcsParser('ics', false, 'data/calendar.db', 1);
    $test->assertTrue($parser2 instanceof IcsParser, 'IcsParser should accept integer eventMetaId');
}

function testGetDataVersionWithEventMetaId($test) {
    // Should work with null (backward compatible)
    $version = get_data_version(null);
    $test->assertNotEmpty($version, 'get_data_version(null) should return a version string');

    // Should work with a specific ID (even non-existent)
    $version = get_data_version(99999);
    $test->assertNotEmpty($version, 'get_data_version(99999) should return a version string');
}

function testGetCachedCreditsWithEventMetaId($test) {
    // Should work with null (backward compatible)
    $credits = get_cached_credits(null);
    $test->assertTrue(is_array($credits), 'get_cached_credits(null) should return an array');

    // Should work with a specific ID
    $credits = get_cached_credits(99999);
    $test->assertTrue(is_array($credits), 'get_cached_credits(99999) should return an array');
}

function testGetAllActiveEventsReturnsArray($test) {
    $events = get_all_active_events();
    $test->assertTrue(is_array($events), 'get_all_active_events should return an array');
}

function testGetEventMetaBySlugNonExistent($test) {
    $meta = get_event_by_slug('non_existent_slug_12345');
    $test->assertNull($meta, 'Should return null for non-existent slug');
}

function testGetEventMetaIdNonExistent($test) {
    $id = get_event_id('non_existent_slug_12345');
    $test->assertNull($id, 'Should return null for non-existent slug');
}
