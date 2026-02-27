<?php
/**
 * Theme System Tests
 * Tests get_site_theme() helper and admin-controlled theme infrastructure
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions/helpers.php';

// ── Helper: backup/restore site-theme.json around tests ────────────────────

function _theme_backup() {
    $f = dirname(__DIR__) . '/cache/site-theme.json';
    return file_exists($f) ? file_get_contents($f) : null;
}

function _theme_restore($backup) {
    $f = dirname(__DIR__) . '/cache/site-theme.json';
    if ($backup !== null) {
        file_put_contents($f, $backup);
    } elseif (file_exists($f)) {
        unlink($f);
    }
}

function _theme_write($theme) {
    $f   = dirname(__DIR__) . '/cache/site-theme.json';
    $dir = dirname($f);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($f, json_encode(['theme' => $theme, 'updated_at' => time()]));
}

// ── 1. Function registration ────────────────────────────────────────────────

function testGetSiteThemeFunctionExists($test) {
    $test->assertTrue(
        function_exists('get_site_theme'),
        'get_site_theme() should be defined in functions/helpers.php'
    );
}

// ── 2. Default (no cache file) ──────────────────────────────────────────────

function testGetSiteThemeDefaultSakura($test) {
    $backup = _theme_backup();
    $f = dirname(__DIR__) . '/cache/site-theme.json';
    if (file_exists($f)) unlink($f);

    $result = get_site_theme();

    _theme_restore($backup);

    $test->assertEquals('sakura', $result, 'Should return sakura when no cache file exists');
}

// ── 3. Reads each valid theme from cache ────────────────────────────────────

function testGetSiteThemeReadsValidThemes($test) {
    $backup = _theme_backup();
    $valid  = ['sakura', 'ocean', 'forest', 'midnight', 'sunset', 'dark', 'gray'];

    foreach ($valid as $t) {
        _theme_write($t);
        $result = get_site_theme();
        $test->assertEquals($t, $result, "Should return theme '$t' from cache file");
    }

    _theme_restore($backup);
}

// ── 4. Invalid theme in cache → fallback to sakura ──────────────────────────

function testGetSiteThemeInvalidThemeFallback($test) {
    $backup = _theme_backup();
    _theme_write('hacked_theme');

    $result = get_site_theme();

    _theme_restore($backup);

    $test->assertEquals('sakura', $result, 'Invalid theme name should fall back to sakura');
}

// ── 5. Malformed JSON in cache → fallback to sakura ─────────────────────────

function testGetSiteThemeMalformedJsonFallback($test) {
    $backup = _theme_backup();
    $f      = dirname(__DIR__) . '/cache/site-theme.json';
    $dir    = dirname($f);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($f, 'not-valid-json{{{');

    $result = get_site_theme();

    _theme_restore($backup);

    $test->assertEquals('sakura', $result, 'Malformed JSON in cache should fall back to sakura');
}

// ── 6. Missing "theme" key in JSON → fallback to sakura ─────────────────────

function testGetSiteThemeMissingKeyFallback($test) {
    $backup = _theme_backup();
    $f      = dirname(__DIR__) . '/cache/site-theme.json';
    $dir    = dirname($f);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($f, json_encode(['updated_at' => time()]));

    $result = get_site_theme();

    _theme_restore($backup);

    $test->assertEquals('sakura', $result, 'JSON without "theme" key should fall back to sakura');
}

// ── 7. Theme CSS files exist ────────────────────────────────────────────────

function testThemeCssFilesExist($test) {
    $themes = ['ocean', 'forest', 'midnight', 'sunset', 'dark', 'gray'];
    foreach ($themes as $t) {
        $path = dirname(__DIR__) . "/styles/themes/{$t}.css";
        $test->assertFileExists($path, "Theme CSS file styles/themes/{$t}.css should exist");
    }
}

// ── 8. theme-switcher.js file still present on disk ─────────────────────────

function testThemeSwitcherJsFileExists($test) {
    $path = dirname(__DIR__) . '/js/theme-switcher.js';
    $test->assertFileExists($path, 'js/theme-switcher.js should exist on disk (kept for reference)');
}

// ── 9. Admin API contains theme_get / theme_save switch cases ───────────────

function testAdminApiHasThemeCases($test) {
    $content = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertContains("case 'theme_get':", $content, "admin/api.php should have case 'theme_get'");
    $test->assertContains("case 'theme_save':", $content, "admin/api.php should have case 'theme_save'");
}

// ── 10. Admin API defines the two theme functions ───────────────────────────

function testAdminApiThemeFunctionsDefined($test) {
    $content = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertContains('function getThemeSetting()', $content, 'getThemeSetting() should be defined in admin/api.php');
    $test->assertContains('function saveThemeSetting()', $content, 'saveThemeSetting() should be defined in admin/api.php');
}

// ── 11. saveThemeSetting does NOT call undefined validate_csrf_token() ───────

function testSaveThemeSettingNoUndefinedCsrfCall($test) {
    $content = file_get_contents(dirname(__DIR__) . '/admin/api.php');

    // Extract the body of saveThemeSetting
    if (preg_match('/function saveThemeSetting\(\)\s*\{(.+?)^}/ms', $content, $m)) {
        $body = $m[1];
        $test->assertFalse(
            strpos($body, 'validate_csrf_token') !== false,
            'saveThemeSetting() must not call undefined validate_csrf_token() — CSRF is handled globally'
        );
    } else {
        throw new Exception('Could not extract saveThemeSetting() body from admin/api.php');
    }
}

// ── 12. saveThemeSetting calls require_api_admin_role() ─────────────────────

function testSaveThemeSettingRequiresAdminRole($test) {
    $content = file_get_contents(dirname(__DIR__) . '/admin/api.php');

    if (preg_match('/function saveThemeSetting\(\)\s*\{(.+?)^}/ms', $content, $m)) {
        $body = $m[1];
        $test->assertContains(
            'require_api_admin_role()',
            $body,
            'saveThemeSetting() should call require_api_admin_role()'
        );
    } else {
        throw new Exception('Could not extract saveThemeSetting() body from admin/api.php');
    }
}

// ── 13. Public pages have server-side theme link ─────────────────────────────

function testPublicPagesHaveServerSideThemeLink($test) {
    $pages = ['index.php', 'how-to-use.php', 'contact.php', 'credits.php'];
    foreach ($pages as $page) {
        $content = file_get_contents(dirname(__DIR__) . '/' . $page);
        $test->assertContains(
            'get_site_theme()',
            $content,
            "{$page} should call get_site_theme()"
        );
        $test->assertContains(
            'styles/themes/',
            $content,
            "{$page} should reference theme CSS path"
        );
    }
}

// ── 14. Public pages do NOT contain theme-switcher div or JS ─────────────────

function testPublicPagesHaveNoThemeSwitcherUI($test) {
    $pages = ['index.php', 'how-to-use.php', 'contact.php', 'credits.php'];
    foreach ($pages as $page) {
        $content = file_get_contents(dirname(__DIR__) . '/' . $page);
        $test->assertFalse(
            strpos($content, 'class="theme-switcher"') !== false,
            "{$page} should not contain theme-switcher div (admin-controlled now)"
        );
        $test->assertFalse(
            strpos($content, 'theme-switcher.js') !== false,
            "{$page} should not load theme-switcher.js"
        );
    }
}

// ── 15. Admin panel has Settings tab and picker UI ───────────────────────────

function testAdminPanelHasSettingsTab($test) {
    $content = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $test->assertContains("switchTab('settings')", $content, 'Admin panel should have Settings tab button');
    $test->assertContains('settingsSection',        $content, 'Admin panel should have settingsSection div');
    $test->assertContains('loadThemeSettings',      $content, 'Admin panel should define loadThemeSettings()');
    $test->assertContains('saveThemeSetting',       $content, 'Admin panel should define saveThemeSetting() JS');
    $test->assertContains('renderThemePicker',      $content, 'Admin panel should define renderThemePicker() JS');
}

// ── 16. cache directory is writable ──────────────────────────────────────────

function testCacheDirWritableForThemeFile($test) {
    $cacheDir = dirname(__DIR__) . '/cache';
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);

    $test->assertTrue(is_dir($cacheDir),      'cache/ directory should exist');
    $test->assertTrue(is_writable($cacheDir), 'cache/ directory should be writable');
}
