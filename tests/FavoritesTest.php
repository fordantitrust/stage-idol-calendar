<?php
/**
 * FavoritesTest — Automated tests for Anonymous Favorites System (v3.5.x)
 *
 * Coverage:
 *  1.  config/favorites.php         — constants (v3.5.0)
 *  2.  functions/favorites.php      — UUID, HMAC, slug, file I/O (v3.5.0)
 *  3.  api/favorites.php            — action structure & guards (v3.5.0)
 *  4.  my-favorites.php             — solo/group split + sort controls (v3.5.1)
 *  5.  my.php                       — mini calendar + day modal (v3.5.2)
 *  6.  js/translations.js           — new i18n keys (v3.5.1 + v3.5.2)
 *  7.  js/common.js                 — injectFavNavButton (v3.5.0)
 *  8.  artist.php                   — follow/unfollow button (v3.5.0)
 *  9.  .htaccess                    — routing for /my and /my-favorites (v3.5.0)
 *  10. how-to-use.php               — section17 updated (v3.5.1 + v3.5.2)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions/favorites.php';

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Ensure FAVORITES_DIR (and at least one shard) is writable for file I/O tests. */
function _fav_dir_ok(): bool {
    if (!defined('FAVORITES_DIR')) return false;
    if (!is_dir(FAVORITES_DIR)) {
        @mkdir(FAVORITES_DIR, 0755, true);
    }
    return is_dir(FAVORITES_DIR) && is_writable(FAVORITES_DIR);
}

// ── 1. Config Constants ───────────────────────────────────────────────────────

function testFavConfigFileExists($test) {
    $test->assertTrue(
        file_exists(dirname(__DIR__) . '/config/favorites.php'),
        'config/favorites.php should exist'
    );
}

function testFavConfigConstantsDefined($test) {
    $test->assertTrue(defined('FAVORITES_DIR'),                  'FAVORITES_DIR should be defined');
    $test->assertTrue(defined('FAVORITES_TTL'),                  'FAVORITES_TTL should be defined');
    $test->assertTrue(defined('FAVORITES_LAST_ACCESS_THROTTLE'), 'FAVORITES_LAST_ACCESS_THROTTLE should be defined');
    $test->assertTrue(defined('FAVORITES_MAX_ARTISTS'),          'FAVORITES_MAX_ARTISTS should be defined');
    $test->assertTrue(defined('FAVORITES_RATE_LIMIT'),           'FAVORITES_RATE_LIMIT should be defined');
    $test->assertTrue(defined('FAVORITES_RATE_WINDOW'),          'FAVORITES_RATE_WINDOW should be defined');
    $test->assertTrue(defined('FAVORITES_RL_DIR'),               'FAVORITES_RL_DIR should be defined');
    $test->assertTrue(defined('FAVORITES_HMAC_SECRET'),          'FAVORITES_HMAC_SECRET should be defined');
    $test->assertTrue(defined('FAVORITES_HMAC_LENGTH'),          'FAVORITES_HMAC_LENGTH should be defined');
}

function testFavConfigTtlIs365Days($test) {
    $test->assertEquals(365 * 86400, FAVORITES_TTL, 'FAVORITES_TTL should be 365 days in seconds');
}

function testFavConfigMaxArtists($test) {
    $test->assertEquals(50, FAVORITES_MAX_ARTISTS, 'FAVORITES_MAX_ARTISTS should be 50');
}

function testFavConfigHmacLength($test) {
    $test->assertEquals(12, FAVORITES_HMAC_LENGTH, 'FAVORITES_HMAC_LENGTH should be 12');
}

function testFavConfigHmacSecretNotEmpty($test) {
    $test->assertNotEmpty(FAVORITES_HMAC_SECRET, 'FAVORITES_HMAC_SECRET should not be empty');
    $test->assertGreaterThan(16, strlen(FAVORITES_HMAC_SECRET), 'FAVORITES_HMAC_SECRET should be at least 16 chars');
}

function testFavConfigRateLimitPositive($test) {
    $test->assertGreaterThan(0, FAVORITES_RATE_LIMIT, 'FAVORITES_RATE_LIMIT should be > 0');
    $test->assertGreaterThan(0, FAVORITES_RATE_WINDOW, 'FAVORITES_RATE_WINDOW should be > 0');
}

// ── 2a. UUID v7 Generation ────────────────────────────────────────────────────

function testFavFunctionsFileExists($test) {
    $test->assertTrue(
        file_exists(dirname(__DIR__) . '/functions/favorites.php'),
        'functions/favorites.php should exist'
    );
}

function testFavUuidV7Format($test) {
    $uuid = fav_generate_uuid_v7();
    $test->assertTrue(
        (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid),
        'UUID v7 must match 8-4-4-4-12 hex pattern with version=7 and variant bit — got: ' . $uuid
    );
}

function testFavUuidV7Uniqueness($test) {
    $seen = [];
    for ($i = 0; $i < 10; $i++) {
        $seen[] = fav_generate_uuid_v7();
    }
    $test->assertCount(10, array_unique($seen), 'fav_generate_uuid_v7() must produce 10 unique values in a row');
}

function testFavUuidV7VersionBit($test) {
    $uuid = fav_generate_uuid_v7();
    // 3rd group must start with '7'
    $parts = explode('-', $uuid);
    $test->assertEquals('7', $parts[2][0], 'UUID v7 third group must start with 7');
}

// ── 2b. HMAC ─────────────────────────────────────────────────────────────────

function testFavHmacLength($test) {
    $uuid = fav_generate_uuid_v7();
    $hmac = fav_hmac($uuid);
    $test->assertEquals(FAVORITES_HMAC_LENGTH, strlen($hmac), 'fav_hmac() must return exactly FAVORITES_HMAC_LENGTH chars');
}

function testFavHmacIsHex($test) {
    $uuid = fav_generate_uuid_v7();
    $hmac = fav_hmac($uuid);
    $test->assertTrue((bool) preg_match('/^[0-9a-f]+$/', $hmac), 'fav_hmac() must return lowercase hex chars only');
}

function testFavHmacDeterministic($test) {
    $uuid = fav_generate_uuid_v7();
    $test->assertEquals(fav_hmac($uuid), fav_hmac($uuid), 'fav_hmac() must be deterministic for the same input');
}

function testFavHmacDifferentForDifferentInputs($test) {
    $a = fav_generate_uuid_v7();
    $b = fav_generate_uuid_v7();
    $test->assertNotEquals(fav_hmac($a), fav_hmac($b), 'fav_hmac() should differ for different UUIDs');
}

// ── 2c. Slug Build / Parse ────────────────────────────────────────────────────

function testFavBuildSlugFormat($test) {
    $uuid = fav_generate_uuid_v7();
    $slug = fav_build_slug($uuid);
    // UUID = 5 dash-parts; slug appends one more dash + HMAC = 6 parts total
    $parts = explode('-', $slug);
    $test->assertCount(6, $parts, 'fav_build_slug() slug must have 6 dash-separated parts (5 UUID + 1 HMAC)');
}

function testFavParseSlugValidSlug($test) {
    $token  = fav_generate_uuid_v7();
    $slug   = fav_build_slug($token);
    $parsed = fav_parse_slug($slug);
    $test->assertNotNull($parsed, 'fav_parse_slug() should return non-null for a valid slug');
    $test->assertArrayHasKey('token', $parsed, 'Parsed result must contain token key');
    $test->assertEquals($token, $parsed['token'], 'Parsed token must match original UUID');
}

function testFavParseSlugInvalidStringReturnsNull($test) {
    $test->assertNull(fav_parse_slug('not-a-valid-slug'),        'Non-slug string must return null');
    $test->assertNull(fav_parse_slug(''),                        'Empty string must return null');
    $test->assertNull(fav_parse_slug('abc-def-ghi-jkl-mno-pqr'), 'Short random string must return null');
}

function testFavParseSlugTamperedHmacReturnsNull($test) {
    $uuid    = fav_generate_uuid_v7();
    $slug    = fav_build_slug($uuid);
    // Flip the last character of the HMAC
    $lastCh  = substr($slug, -1);
    $flipped = $lastCh === 'a' ? 'b' : 'a';
    $tampered = substr($slug, 0, -1) . $flipped;
    $test->assertNull(fav_parse_slug($tampered), 'Tampered HMAC must cause fav_parse_slug() to return null');
}

// ── 2d. File I/O ──────────────────────────────────────────────────────────────

function testFavWriteReturnsTrueOnSuccess($test) {
    if (!_fav_dir_ok()) { echo ' [SKIP: FAVORITES_DIR not writable] '; return; }
    $token = fav_generate_uuid_v7();
    $data  = ['token' => $token, 'artists' => [], 'created_at' => date('c'), 'last_access' => date('c')];
    $ok    = fav_write($data);
    $test->assertTrue($ok, 'fav_write() should return true when write succeeds');
    // Cleanup: shard = last 3 chars of token string (UUID with dashes)
    @unlink(FAVORITES_DIR . '/' . substr($token, -3) . '/' . $token . '.json');
}

function testFavWriteCreatesShardedFile($test) {
    if (!_fav_dir_ok()) { echo ' [SKIP: FAVORITES_DIR not writable] '; return; }
    $token = fav_generate_uuid_v7();
    $path  = FAVORITES_DIR . '/' . substr($token, -3) . '/' . $token . '.json';
    fav_write(['token' => $token, 'artists' => [42], 'created_at' => date('c'), 'last_access' => date('c')]);
    $test->assertTrue(file_exists($path), 'fav_write() must create file at the correct sharded path');
    @unlink($path);
}

function testFavWriteThenRead($test) {
    if (!_fav_dir_ok()) { echo ' [SKIP: FAVORITES_DIR not writable] '; return; }
    $token = fav_generate_uuid_v7();
    fav_write(['token' => $token, 'artists' => [1, 2, 3], 'created_at' => date('c'), 'last_access' => date('c')]);
    $data = fav_read($token);
    $test->assertNotNull($data, 'fav_read() must return data immediately after fav_write()');
    $test->assertArrayHasKey('artists', $data, 'Read data must contain artists key');
    $test->assertContains(2, $data['artists'], 'Read artists must contain the written values');
    // Cleanup
    @unlink(FAVORITES_DIR . '/' . substr($token, -3) . '/' . $token . '.json');
}

function testFavReadNonExistentReturnsNull($test) {
    $token = fav_generate_uuid_v7();
    // Do not write — just read
    $test->assertNull(fav_read($token), 'fav_read() must return null for a token that was never written');
}

// ── 3. api/favorites.php — Structure & Guards ────────────────────────────────

function testFavApiFileExists($test) {
    $test->assertTrue(
        file_exists(dirname(__DIR__) . '/api/favorites.php'),
        'api/favorites.php should exist'
    );
}

function testFavApiHasCreateAction($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/favorites.php');
    $test->assertContains('action=create', $src, 'api/favorites.php must handle action=create');
    $test->assertContains('fav_check_rate_limit', $src, 'create action must call fav_check_rate_limit()');
    $test->assertContains('fav_build_slug',       $src, 'create action must call fav_build_slug()');
    $test->assertContains('fav_write',            $src, 'create action must call fav_write()');
}

function testFavApiHasGetAction($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/favorites.php');
    $test->assertContains('action=get', $src, 'api/favorites.php must handle action=get');
    $test->assertContains('fav_read',   $src, 'get action must call fav_read()');
}

function testFavApiHasAddAction($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/favorites.php');
    $test->assertContains('action=add',          $src, 'api/favorites.php must handle action=add');
    $test->assertContains('FAVORITES_MAX_ARTISTS', $src, 'add action must enforce FAVORITES_MAX_ARTISTS limit');
}

function testFavApiHasRemoveAction($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/favorites.php');
    $test->assertContains('action=remove', $src, 'api/favorites.php must handle action=remove');
}

function testFavApiValidatesSlug($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/favorites.php');
    $test->assertContains('fav_parse_slug', $src, 'api/favorites.php must validate slug via fav_parse_slug()');
}

function testFavApiReturns429OnRateLimit($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/favorites.php');
    $test->assertContains('429', $src, 'api/favorites.php must return HTTP 429 when rate-limited');
}

function testFavApiReturnsJson($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/favorites.php');
    $test->assertContains('application/json', $src, 'api/favorites.php must set Content-Type: application/json');
}

function testFavApiReturns404ForInvalidSlug($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api/favorites.php');
    $test->assertContains('404', $src, 'api/favorites.php must return HTTP 404 for unknown slug');
}

// ── 4. my-favorites.php — Solo / Group Split (v3.5.1) ────────────────────────

function testMyFavoritesFileExists($test) {
    $test->assertTrue(
        file_exists(dirname(__DIR__) . '/my-favorites.php'),
        'my-favorites.php should exist'
    );
}

function testMyFavoritesPhpSplitsToSolosAndGroups($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my-favorites.php');
    $test->assertContains('$solos',   $src, 'my-favorites.php must define $solos array');
    $test->assertContains('$groups',  $src, 'my-favorites.php must define $groups array');
    $test->assertContains('is_group', $src, 'my-favorites.php must use is_group to split artists');
}

function testMyFavoritesHasSoloListId($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my-favorites.php');
    $test->assertContains('soloList',         $src, 'my-favorites.php must have soloList section');
    $test->assertContains('fav.soloArtists',  $src, 'my-favorites.php must reference fav.soloArtists i18n key');
}

function testMyFavoritesHasGroupListId($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my-favorites.php');
    $test->assertContains('groupList',  $src, 'my-favorites.php must have groupList section');
    $test->assertContains('fav.groups', $src, 'my-favorites.php must reference fav.groups i18n key');
}

function testMyFavoritesHasSortBar($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my-favorites.php');
    $test->assertContains('fav-sort-bar', $src, 'my-favorites.php must render .fav-sort-bar element');
    $test->assertContains('btn-sort',     $src, 'my-favorites.php must render .btn-sort buttons');
}

function testMyFavoritesHasSortAzZaLabels($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my-favorites.php');
    $test->assertContains('fav.sortAZ', $src, 'my-favorites.php must reference fav.sortAZ i18n key');
    $test->assertContains('fav.sortZA', $src, 'my-favorites.php must reference fav.sortZA i18n key');
}

function testMyFavoritesHasSortSection($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my-favorites.php');
    $test->assertContains('sortSection',  $src, 'my-favorites.php must define sortSection() JS function');
    $test->assertContains('localeCompare', $src, 'sortSection() must use localeCompare for locale-aware sorting');
}

function testMyFavoritesHasDataNameAttribute($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my-favorites.php');
    $test->assertContains('data-name=', $src, 'my-favorites.php cards must have data-name attribute for sort');
}

function testMyFavoritesLocalStoragePreference($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my-favorites.php');
    // Sort pref keys are built dynamically: 'fav_sort_' + key, where key='solo'/'group'
    $test->assertContains("'fav_sort_'",  $src, "my-favorites.php must use 'fav_sort_' prefix for localStorage sort keys");
    $test->assertContains("'solo'",       $src, "my-favorites.php must pass 'solo' as key to sortSection()");
    $test->assertContains("'group'",      $src, "my-favorites.php must pass 'group' as key to sortSection()");
    $test->assertContains('localStorage', $src, 'my-favorites.php must use localStorage for sort preference');
}

function testMyFavoritesHasNoAccessScreen($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my-favorites.php');
    $test->assertContains('fav.noAccess', $src, 'my-favorites.php must show no-access screen when slug is missing');
}

function testMyFavoritesHasDualNavButtons($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my-favorites.php');
    // Both the ⭐ (self) and 📅 (my.php) nav buttons should be present
    $test->assertContains('my-favorites', $src, 'my-favorites.php must link to my-favorites page');
    $test->assertContains('/my/',         $src, 'my-favorites.php must link to /my/ (upcoming programs)');
}

function testMyFavoritesHasSaveUrlBanner($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my-favorites.php');
    $test->assertContains('fav.saveBanner', $src, 'my-favorites.php must show Save URL banner (fav.saveBanner i18n key)');
    $test->assertContains('fav-save-banner', $src, 'my-favorites.php must have .fav-save-banner element');
}

// ── 5a. my.php — Mini Calendar (v3.5.2) ──────────────────────────────────────

function testMyPhpFileExists($test) {
    $test->assertTrue(
        file_exists(dirname(__DIR__) . '/my.php'),
        'my.php should exist'
    );
}

function testMyPhpHasCalProgramsPhpArray($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my.php');
    $test->assertContains('$calPrograms', $src, 'my.php must build $calPrograms PHP array for JS injection');
}

function testMyPhpHasMyProgramsConstWithXssProtection($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my.php');
    $test->assertContains('MY_PROGRAMS', $src, 'my.php must define MY_PROGRAMS JS const');
    $test->assertContains('JSON_HEX_TAG', $src, 'my.php must use JSON_HEX_TAG to prevent XSS via JSON injection');
}

function testMyPhpHasCalendarWrapElement($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my.php');
    $test->assertContains('fav-cal-wrap', $src, 'my.php must render .fav-cal-wrap element for mini calendar');
    $test->assertContains('fav-cal-grid', $src, 'my.php must render .fav-cal-grid element inside calendar');
}

function testMyPhpHasCalMonthsArray($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my.php');
    $test->assertContains('CAL_MONTHS', $src, 'my.php must define CAL_MONTHS JS array (months restricted to those with data)');
}

function testMyPhpHasRenderFavCalFunction($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my.php');
    $test->assertContains('renderFavCal', $src, 'my.php must define renderFavCal() JS function');
}

function testMyPhpHasFavCalNavFunction($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my.php');
    $test->assertContains('favCalNav', $src, 'my.php must define favCalNav() JS function for ◀ ▶ navigation');
}

function testMyPhpHasPrevNextNavCalls($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my.php');
    $test->assertContains('favCalNav(-1)', $src, 'my.php must call favCalNav(-1) for ◀ button');
    $test->assertContains('favCalNav(1)',  $src, 'my.php must call favCalNav(1) for ▶ button');
}

// ── 5b. my.php — Day Modal (v3.5.2) ──────────────────────────────────────────

function testMyPhpHasDayModalOverlay($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my.php');
    $test->assertContains('favDayOverlay', $src, 'my.php must render #favDayOverlay for day-programs modal');
    $test->assertContains('fav-day-modal', $src, 'my.php must have .fav-day-modal element inside overlay');
}

function testMyPhpHasOpenAndCloseDayModal($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my.php');
    $test->assertContains('openDayModal',  $src, 'my.php must define openDayModal() JS function');
    $test->assertContains('closeDayModal', $src, 'my.php must define closeDayModal() JS function');
}

function testMyPhpHasEscapeHelper($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my.php');
    $test->assertContains('function _esc', $src, 'my.php must define _esc() helper for XSS-safe HTML insertion in calendar');
}

// ── 5c. my.php — Other v3.5.x Features ───────────────────────────────────────

function testMyPhpHasByDateGrouping($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my.php');
    $test->assertContains('$byDate', $src, 'my.php must use $byDate grouping (date-first, v3.5.0 sort order change)');
}

function testMyPhpReRendersCalOnLanguageChange($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my.php');
    $test->assertContains('changeLanguage', $src, 'my.php must override changeLanguage()');
    $test->assertContains('renderFavCal',   $src, 'changeLanguage override must call renderFavCal() to re-render month/DOW labels');
}

function testMyPhpHasNoAccessScreen($test) {
    $src = file_get_contents(dirname(__DIR__) . '/my.php');
    $test->assertContains('fav.noAccess', $src, 'my.php must show no-access screen when slug is missing');
}

// ── 6. js/translations.js — v3.5.1 Keys ─────────────────────────────────────

function testTranslationsHasFavSoloArtists($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    $test->assertContains("'fav.soloArtists'", $src, "translations.js must have 'fav.soloArtists' key");
}

function testTranslationsHasFavGroups($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    $test->assertContains("'fav.groups'", $src, "translations.js must have 'fav.groups' key");
}

function testTranslationsHasFavSort($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    $test->assertContains("'fav.sort'", $src, "translations.js must have 'fav.sort' key");
}

function testTranslationsHasFavSortAZ($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    $test->assertContains("'fav.sortAZ'", $src, "translations.js must have 'fav.sortAZ' key");
}

function testTranslationsHasFavSortZA($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    $test->assertContains("'fav.sortZA'", $src, "translations.js must have 'fav.sortZA' key");
}

function testTranslationsHasFavNoAccess($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    $test->assertContains("'fav.noAccess'",     $src, "translations.js must have 'fav.noAccess' key");
    $test->assertContains("'fav.noAccessDesc'",  $src, "translations.js must have 'fav.noAccessDesc' key");
}

// ── 7. js/translations.js — v3.5.2 Keys ─────────────────────────────────────

function testTranslationsHasSection17CalTitle($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    $test->assertContains("'section17.cal.title'", $src, "translations.js must have 'section17.cal.title' key");
}

function testTranslationsHasSection17CalFeatures($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    $test->assertContains("'section17.cal.feature1'", $src, "translations.js must have 'section17.cal.feature1' key");
    $test->assertContains("'section17.cal.feature2'", $src, "translations.js must have 'section17.cal.feature2' key");
    $test->assertContains("'section17.cal.feature3'", $src, "translations.js must have 'section17.cal.feature3' key");
}

function testTranslationsHasSection17MyfavSort($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    $test->assertContains("'section17.myfav.sort'", $src, "translations.js must have 'section17.myfav.sort' key");
}

function testTranslationsFavSortKeysInAllThreeLanguages($test) {
    $src  = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    $keys = ['fav.soloArtists', 'fav.groups', 'fav.sort', 'fav.sortAZ', 'fav.sortZA'];
    foreach ($keys as $key) {
        $count = substr_count($src, "'$key'");
        $test->assertEquals(3, $count, "Key '$key' must appear exactly 3 times (TH + EN + JA)");
    }
}

function testTranslationsCalKeysInAllThreeLanguages($test) {
    $src  = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    $keys = ['section17.cal.title', 'section17.cal.feature1', 'section17.cal.feature2', 'section17.cal.feature3', 'section17.myfav.sort'];
    foreach ($keys as $key) {
        $count = substr_count($src, "'$key'");
        $test->assertEquals(3, $count, "Key '$key' must appear exactly 3 times (TH + EN + JA)");
    }
}

// ── 8. js/common.js — Nav Injection (v3.5.0) ─────────────────────────────────

function testCommonJsHasInjectFavNavButton($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/common.js');
    $test->assertContains('injectFavNavButton', $src, 'js/common.js must define injectFavNavButton() function');
}

function testCommonJsReadsFavSlugFromLocalStorage($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/common.js');
    $test->assertContains('fav_slug', $src, 'js/common.js must read fav_slug from localStorage');
}

function testCommonJsNavButtonLinksToMyFavorites($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/common.js');
    $test->assertContains('my-favorites', $src, 'js/common.js nav button must link to /my-favorites/{slug}');
}

function testCommonJsNavButtonLinksToMy($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/common.js');
    $test->assertContains("'/my/'", $src, "js/common.js nav button must link to /my/{slug}");
}

// ── 9. artist.php — Follow / Unfollow Button (v3.5.0) ────────────────────────

function testArtistPhpFileExists($test) {
    $test->assertTrue(
        file_exists(dirname(__DIR__) . '/artist.php'),
        'artist.php should exist'
    );
}

function testArtistPhpHasFollowButton($test) {
    $src = file_get_contents(dirname(__DIR__) . '/artist.php');
    $test->assertContains('btn-fav', $src, 'artist.php must render a .btn-fav element (follow/unfollow toggle)');
    $test->assertContains('toggleFavArtist', $src, 'artist.php must define toggleFavArtist() JS function');
}

function testArtistPhpCallsFavoritesApi($test) {
    $src = file_get_contents(dirname(__DIR__) . '/artist.php');
    $test->assertContains('api/favorites', $src, 'artist.php must call api/favorites endpoint for follow/unfollow');
}

function testArtistPhpExposesArtistIdGlobal($test) {
    $src = file_get_contents(dirname(__DIR__) . '/artist.php');
    $test->assertContains('ARTIST_ID', $src, 'artist.php must expose window.ARTIST_ID (or equivalent) for JS');
}

function testArtistPhpHasToggleFollowState($test) {
    $src = file_get_contents(dirname(__DIR__) . '/artist.php');
    // Must toggle between follow / unfollow states
    $test->assertContains('action=add',    $src, 'artist.php must call action=add to follow an artist');
    $test->assertContains('action=remove', $src, 'artist.php must call action=remove to unfollow an artist');
}

// ── 10. .htaccess — Routing (v3.5.0) ─────────────────────────────────────────

function testHtaccessHasMyRoute($test) {
    $src = file_get_contents(dirname(__DIR__) . '/.htaccess');
    $test->assertContains('my.php', $src, '.htaccess must route /my/{slug} → my.php');
}

function testHtaccessHasMyFavoritesRoute($test) {
    $src = file_get_contents(dirname(__DIR__) . '/.htaccess');
    $test->assertContains('my-favorites.php', $src, '.htaccess must route /my-favorites/{slug} → my-favorites.php');
}

function testHtaccessHasFavoritesApiRoute($test) {
    $src = file_get_contents(dirname(__DIR__) . '/.htaccess');
    $test->assertContains('favorites.php', $src, '.htaccess must route /api/favorites → api/favorites.php');
}

// ── 11. how-to-use.php — Section17 Updated (v3.5.1 + v3.5.2) ────────────────

function testHowToUseHasSection17MyfavSort($test) {
    $src = file_get_contents(dirname(__DIR__) . '/how-to-use.php');
    $test->assertContains(
        'section17.myfav.sort',
        $src,
        'how-to-use.php section17 must reference section17.myfav.sort (A→Z/Z→A sort description)'
    );
}

function testHowToUseHasSection17CalTitle($test) {
    $src = file_get_contents(dirname(__DIR__) . '/how-to-use.php');
    $test->assertContains(
        'section17.cal.title',
        $src,
        'how-to-use.php section17 must have section17.cal.title (Mini Calendar sub-section)'
    );
}

function testHowToUseHasSection17CalFeatures($test) {
    $src = file_get_contents(dirname(__DIR__) . '/how-to-use.php');
    $test->assertContains('section17.cal.feature1', $src, 'how-to-use.php must reference section17.cal.feature1');
    $test->assertContains('section17.cal.feature2', $src, 'how-to-use.php must reference section17.cal.feature2');
    $test->assertContains('section17.cal.feature3', $src, 'how-to-use.php must reference section17.cal.feature3');
}
