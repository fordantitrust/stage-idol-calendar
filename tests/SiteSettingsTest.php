<?php
/**
 * Site Settings Tests
 * Tests get_site_title() helper and admin-controlled site title infrastructure
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions/helpers.php';

// ── Helper: backup/restore site-settings.json around tests ─────────────────

function _settings_backup() {
    $f = dirname(__DIR__) . '/cache/site-settings.json';
    return file_exists($f) ? file_get_contents($f) : null;
}

function _settings_restore($backup) {
    $f = dirname(__DIR__) . '/cache/site-settings.json';
    if ($backup !== null) {
        file_put_contents($f, $backup);
    } elseif (file_exists($f)) {
        unlink($f);
    }
}

function _settings_write($title) {
    $f   = dirname(__DIR__) . '/cache/site-settings.json';
    $dir = dirname($f);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($f, json_encode(['site_title' => $title, 'updated_at' => time()]));
}

// ── 1. Function registration ────────────────────────────────────────────────

function testGetSiteTitleFunctionExists($test) {
    $test->assertTrue(
        function_exists('get_site_title'),
        'get_site_title() should be defined in functions/helpers.php'
    );
}

// ── 2. Default (no cache file) → APP_NAME ───────────────────────────────────

function testGetSiteTitleDefaultIsAppName($test) {
    $backup = _settings_backup();
    $f = dirname(__DIR__) . '/cache/site-settings.json';
    if (file_exists($f)) unlink($f);

    $result = get_site_title();

    _settings_restore($backup);

    $test->assertEquals(APP_NAME, $result, 'Should return APP_NAME when no cache file exists');
}

// ── 3. Reads custom title from cache ────────────────────────────────────────

function testGetSiteTitleReadsFromCache($test) {
    $backup = _settings_backup();
    _settings_write('My Custom Event');

    $result = get_site_title();

    _settings_restore($backup);

    $test->assertEquals('My Custom Event', $result, 'Should return custom title from cache file');
}

// ── 4. Empty title in cache → fallback to APP_NAME ──────────────────────────

function testGetSiteTitleEmptyTitleFallsToDefault($test) {
    $backup = _settings_backup();
    $f = dirname(__DIR__) . '/cache/site-settings.json';
    $dir = dirname($f);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($f, json_encode(['site_title' => '', 'updated_at' => time()]));

    $result = get_site_title();

    _settings_restore($backup);

    $test->assertEquals(APP_NAME, $result, 'Empty site_title in cache should fall back to APP_NAME');
}

// ── 5. Whitespace-only title → fallback to APP_NAME ─────────────────────────

function testGetSiteTitleWhitespaceOnlyFallsToDefault($test) {
    $backup = _settings_backup();
    $f = dirname(__DIR__) . '/cache/site-settings.json';
    $dir = dirname($f);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($f, json_encode(['site_title' => '   ', 'updated_at' => time()]));

    $result = get_site_title();

    _settings_restore($backup);

    $test->assertEquals(APP_NAME, $result, 'Whitespace-only title should fall back to APP_NAME');
}

// ── 6. Title is trimmed ──────────────────────────────────────────────────────

function testGetSiteTitleTrimsWhitespace($test) {
    $backup = _settings_backup();
    _settings_write('  Sakura Fest  ');

    $result = get_site_title();

    _settings_restore($backup);

    $test->assertEquals('Sakura Fest', $result, 'get_site_title() should trim surrounding whitespace');
}

// ── 7. Malformed JSON in cache → fallback to APP_NAME ───────────────────────

function testGetSiteTitleMalformedJsonFallback($test) {
    $backup = _settings_backup();
    $f = dirname(__DIR__) . '/cache/site-settings.json';
    $dir = dirname($f);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($f, 'not-valid-json{{{');

    $result = get_site_title();

    _settings_restore($backup);

    $test->assertEquals(APP_NAME, $result, 'Malformed JSON in cache should fall back to APP_NAME');
}

// ── 8. Admin API has title_get / title_save switch cases ────────────────────

function testAdminApiHasTitleCases($test) {
    $content = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertContains("case 'title_get':", $content, "admin/api.php should have case 'title_get'");
    $test->assertContains("case 'title_save':", $content, "admin/api.php should have case 'title_save'");
}

// ── 9. Admin API defines the two title functions ─────────────────────────────

function testAdminApiTitleFunctionsDefined($test) {
    $content = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertContains('function getTitleSetting()', $content, 'getTitleSetting() should be defined in admin/api.php');
    $test->assertContains('function saveTitleSetting()', $content, 'saveTitleSetting() should be defined in admin/api.php');
}

// ── 10. Public pages call get_site_title() ───────────────────────────────────

function testPublicPagesCallGetSiteTitle($test) {
    $pages = ['index.php', 'how-to-use.php', 'contact.php', 'credits.php'];
    foreach ($pages as $page) {
        $content = file_get_contents(dirname(__DIR__) . '/' . $page);
        $test->assertContains(
            'get_site_title()',
            $content,
            "{$page} should call get_site_title()"
        );
    }
}

// ── 11. Public pages inject window.SITE_TITLE before translations.js ─────────

function testPublicPagesInjectSiteTitleVar($test) {
    $pages = ['index.php', 'how-to-use.php', 'contact.php', 'credits.php'];
    foreach ($pages as $page) {
        $content = file_get_contents(dirname(__DIR__) . '/' . $page);
        $test->assertContains(
            'window.SITE_TITLE',
            $content,
            "{$page} should inject window.SITE_TITLE before translations.js"
        );
    }
}

// ── 12. translations.js has the SITE_TITLE patching IIFE ─────────────────────

function testTranslationsJsHasSiteTitlePatch($test) {
    $content = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    $test->assertContains(
        'window.SITE_TITLE',
        $content,
        'js/translations.js should reference window.SITE_TITLE for patching'
    );
    $test->assertContains(
        'Idol Stage Timetable',
        $content,
        'js/translations.js IIFE should reference the default title string to replace'
    );
}

// ── 13. saveTitleSetting requires admin role ──────────────────────────────────

function testSaveTitleSettingRequiresAdminRole($test) {
    $content = file_get_contents(dirname(__DIR__) . '/admin/api.php');

    if (preg_match('/function saveTitleSetting\(\)\s*\{(.+?)^}/ms', $content, $m)) {
        $body = $m[1];
        $test->assertContains(
            'require_api_admin_role()',
            $body,
            'saveTitleSetting() should call require_api_admin_role()'
        );
    } else {
        throw new Exception('Could not extract saveTitleSetting() body from admin/api.php');
    }
}

// ── 14. APP_NAME constant is defined ─────────────────────────────────────────

function testAppNameConstantDefined($test) {
    $test->assertTrue(defined('APP_NAME'), 'APP_NAME constant should be defined in config/app.php');
    $test->assertNotEmpty(APP_NAME, 'APP_NAME should not be empty');
}
