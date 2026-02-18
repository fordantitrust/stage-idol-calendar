<?php
/**
 * Cache System Tests
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions/cache.php';

function testCacheDirectoryExists($test) {
    $cacheDir = dirname(__DIR__) . '/cache';

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    $test->assertTrue(is_dir($cacheDir), 'Cache directory should exist');
    $test->assertTrue(is_writable($cacheDir), 'Cache directory should be writable');
}

function testDataVersionCacheCreation($test) {
    // Skip if no database (cache won't be created without DB)
    if (!file_exists(DB_PATH)) {
        echo " [SKIP: No database] ";
        return;
    }

    // Get data version (should create cache)
    $version = get_data_version();

    $test->assertNotNull($version, 'Should return version');
    $test->assertNotEmpty($version, 'Version should not be empty');

    // Check cache file created
    $cacheFile = DATA_VERSION_CACHE_FILE;
    $test->assertFileExists($cacheFile, 'Cache file should be created');

    // Verify cache content
    $cacheContent = file_get_contents($cacheFile);
    $cache = json_decode($cacheContent, true);

    $test->assertNotNull($cache, 'Cache should contain valid JSON');
    $test->assertArrayHasKey('version', $cache, 'Cache should have version key');
    $test->assertArrayHasKey('timestamp', $cache, 'Cache should have timestamp key');
}

function testDataVersionCacheHit($test) {
    // Create cache manually
    $cacheFile = DATA_VERSION_CACHE_FILE;
    $expectedVersion = 'Test Version ' . time();

    $cacheData = [
        'version' => $expectedVersion,
        'timestamp' => time()
    ];

    file_put_contents($cacheFile, json_encode($cacheData));

    // Get version (should use cache)
    $version = get_data_version();

    $test->assertEquals($expectedVersion, $version, 'Should return cached version');
}

function testDataVersionCacheExpiration($test) {
    // Skip if no database (cache won't be rewritten without DB)
    if (!file_exists(DB_PATH)) {
        echo " [SKIP: No database] ";
        return;
    }

    // Create expired cache
    $cacheFile = DATA_VERSION_CACHE_FILE;

    $cacheData = [
        'version' => 'Expired Version',
        'timestamp' => time() - DATA_VERSION_CACHE_TTL - 10 // Expired
    ];

    file_put_contents($cacheFile, json_encode($cacheData));

    // Get version (should regenerate)
    $version = get_data_version();

    $test->assertNotEquals('Expired Version', $version, 'Should not use expired cache');

    // Check new cache created
    $cacheContent = file_get_contents($cacheFile);
    $cache = json_decode($cacheContent, true);

    $test->assertGreaterThan(time() - 10, $cache['timestamp'], 'Should have fresh timestamp');
}

function testCreditsCache($test) {
    // Skip if no database
    $dbPath = DB_PATH;
    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    // Clear cache first
    invalidate_credits_cache();

    // Get credits (should create cache)
    $credits = get_cached_credits();

    $test->assertTrue(is_array($credits), 'Should return array');

    // Check cache file created
    $cacheFile = CREDITS_CACHE_FILE;

    if (!empty($credits)) {
        $test->assertFileExists($cacheFile, 'Cache file should be created');

        // Verify cache content
        $cacheContent = file_get_contents($cacheFile);
        $cache = json_decode($cacheContent, true);

        $test->assertArrayHasKey('data', $cache, 'Cache should have data key');
        $test->assertArrayHasKey('timestamp', $cache, 'Cache should have timestamp key');
    }
}

function testCreditsCacheInvalidation($test) {
    // Create cache
    $cacheFile = CREDITS_CACHE_FILE;

    $cacheData = [
        'data' => [
            ['id' => 1, 'title' => 'Test Credit']
        ],
        'timestamp' => time()
    ];

    if (!is_dir(dirname($cacheFile))) {
        mkdir(dirname($cacheFile), 0755, true);
    }

    file_put_contents($cacheFile, json_encode($cacheData));

    $test->assertFileExists($cacheFile, 'Cache file should exist before invalidation');

    // Invalidate cache
    $result = invalidate_credits_cache();

    $test->assertTrue($result, 'Invalidation should return true');
    $test->assertFileNotExists($cacheFile, 'Cache file should be deleted after invalidation');
}

function testCreditsCacheHit($test) {
    // Skip if no database
    $dbPath = DB_PATH;
    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    // Create fresh cache
    $cacheFile = CREDITS_CACHE_FILE;
    $testData = [
        ['id' => 1, 'title' => 'Cached Credit 1'],
        ['id' => 2, 'title' => 'Cached Credit 2'],
    ];

    $cacheData = [
        'data' => $testData,
        'timestamp' => time()
    ];

    if (!is_dir(dirname($cacheFile))) {
        mkdir(dirname($cacheFile), 0755, true);
    }

    file_put_contents($cacheFile, json_encode($cacheData));

    // Get credits (should use cache, not database)
    $credits = get_cached_credits();

    $test->assertEquals($testData, $credits, 'Should return cached data');
}

function testCreditsCacheExpiration($test) {
    // Skip if no database
    $dbPath = DB_PATH;
    if (!file_exists($dbPath)) {
        echo " [SKIP: No database] ";
        return;
    }

    // Create expired cache
    $cacheFile = CREDITS_CACHE_FILE;

    $cacheData = [
        'data' => [['id' => 1, 'title' => 'Expired']],
        'timestamp' => time() - CREDITS_CACHE_TTL - 10 // Expired
    ];

    if (!is_dir(dirname($cacheFile))) {
        mkdir(dirname($cacheFile), 0755, true);
    }

    file_put_contents($cacheFile, json_encode($cacheData));

    // Get credits (should fetch fresh from database)
    $credits = get_cached_credits();

    // Should get fresh data, not expired cache
    $test->assertTrue(is_array($credits), 'Should return array');

    // Check cache updated
    if (file_exists($cacheFile)) {
        $cacheContent = file_get_contents($cacheFile);
        $cache = json_decode($cacheContent, true);

        if ($cache) {
            $test->assertGreaterThan(time() - 10, $cache['timestamp'], 'Should have fresh timestamp');
        }
    }
}

function testCacheFallbackOnError($test) {
    // Temporarily rename database to simulate error
    $dbPath = DB_PATH;
    $backupPath = DB_PATH . '.backup';

    if (file_exists($dbPath)) {
        rename($dbPath, $backupPath);
    }

    // Clear cache
    invalidate_credits_cache();

    // Get credits (should handle error gracefully)
    $credits = get_cached_credits();

    $test->assertTrue(is_array($credits), 'Should return array even on error');
    $test->assertEmpty($credits, 'Should return empty array on error');

    // Restore database
    if (file_exists($backupPath)) {
        rename($backupPath, $dbPath);
    }
}

function testCachePermissions($test) {
    $cacheDir = dirname(__DIR__) . '/cache';

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    // Check permissions
    $test->assertTrue(is_readable($cacheDir), 'Cache directory should be readable');
    $test->assertTrue(is_writable($cacheDir), 'Cache directory should be writable');
}
