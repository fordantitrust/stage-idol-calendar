<?php
/**
 * FTS5 Full-Text Search Tests (v9.0.0)
 * Tests: FTS tables, triggers, helper functions, API, UI, translations
 */

require_once __DIR__ . '/../config.php';

// ── DB helpers ────────────────────────────────────────────────────────────────

function _fts_db(): ?PDO {
    if (!file_exists(DB_PATH)) return null;
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA busy_timeout = 3000");
    return $db;
}

function _fts_tables(): array {
    $db = _fts_db(); if (!$db) return [];
    $t = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    $db = null; return $t;
}

function _fts_triggers(): array {
    $db = _fts_db(); if (!$db) return [];
    $t = $db->query("SELECT name FROM sqlite_master WHERE type='trigger'")->fetchAll(PDO::FETCH_COLUMN);
    $db = null; return $t;
}

// ── Schema tests ──────────────────────────────────────────────────────────────

// 1. programs_fts table exists
function testFts5ProgramsFtsTableExists($test) {
    $test->assertTrue(in_array('programs_fts', _fts_tables()), 'programs_fts table should exist');
}

// 2. events_fts table exists
function testFts5EventsFtsTableExists($test) {
    $test->assertTrue(in_array('events_fts', _fts_tables()), 'events_fts table should exist');
}

// 3. artists_fts table exists
function testFts5ArtistsFtsTableExists($test) {
    $test->assertTrue(in_array('artists_fts', _fts_tables()), 'artists_fts table should exist');
}

// 4–6. Triggers: ai (after insert)
function testFts5ProgramsAiTriggerExists($test) {
    $test->assertTrue(in_array('programs_ai', _fts_triggers()), 'programs_ai trigger should exist');
}
function testFts5EventsAiTriggerExists($test) {
    $test->assertTrue(in_array('events_ai', _fts_triggers()), 'events_ai trigger should exist');
}
function testFts5ArtistsAiTriggerExists($test) {
    $test->assertTrue(in_array('artists_ai', _fts_triggers()), 'artists_ai trigger should exist');
}

// 7–9. Triggers: au (after update)
function testFts5ProgramsAuTriggerExists($test) {
    $test->assertTrue(in_array('programs_au', _fts_triggers()), 'programs_au trigger should exist');
}
function testFts5EventsAuTriggerExists($test) {
    $test->assertTrue(in_array('events_au', _fts_triggers()), 'events_au trigger should exist');
}
function testFts5ArtistsAuTriggerExists($test) {
    $test->assertTrue(in_array('artists_au', _fts_triggers()), 'artists_au trigger should exist');
}

// 10–12. Triggers: ad (after delete)
function testFts5ProgramsAdTriggerExists($test) {
    $test->assertTrue(in_array('programs_ad', _fts_triggers()), 'programs_ad trigger should exist');
}
function testFts5EventsAdTriggerExists($test) {
    $test->assertTrue(in_array('events_ad', _fts_triggers()), 'events_ad trigger should exist');
}
function testFts5ArtistsAdTriggerExists($test) {
    $test->assertTrue(in_array('artists_ad', _fts_triggers()), 'artists_ad trigger should exist');
}

// ── fts5_available() ──────────────────────────────────────────────────────────

// 13. fts5_available returns true when tables exist
function testFts5AvailableFunctionTrue($test) {
    $db = _fts_db();
    if (!$db) { echo ' [SKIP:no db] '; return; }
    $test->assertTrue(fts5_available($db), 'fts5_available() should return true');
    $db = null;
}

// 14. fts5_available function is defined
function testFts5AvailableFunctionDefined($test) {
    $test->assertTrue(function_exists('fts5_available'), 'fts5_available() should be defined');
}

// ── fts5_escape() ─────────────────────────────────────────────────────────────

// 15. Short query returns empty string
function testFts5EscapeShortQueryReturnsEmpty($test) {
    $test->assertEquals('', fts5_escape('ab'), 'Query < 3 chars should return empty string');
}

// 16. Single term gets quoted
function testFts5EscapeSingleTermQuoted($test) {
    $result = fts5_escape('idol');
    $test->assertEquals('"idol"', $result, 'Single term should be wrapped in double quotes');
}

// 17. Multiple terms each get quoted
function testFts5EscapeMultipleTermsQuoted($test) {
    $result = fts5_escape('idol stage');
    $test->assertEquals('"idol" "stage"', $result, 'Each term should be separately quoted');
}

// 18. Embedded double quotes are escaped
function testFts5EscapeInternalQuotes($test) {
    $result = fts5_escape('say "hello"');
    $test->assertContains('""', $result, 'Internal double quotes should be doubled');
}

// 19. Thai text (3+ chars) passes through
function testFts5EscapeThaiText($test) {
    $result = fts5_escape('ไอดอล');
    $test->assertNotEquals('', $result, 'Thai text >= 3 chars should not be filtered out');
    $test->assertContains('"ไอดอล"', $result, 'Thai term should be quoted');
}

// ── fts5_search_programs() ───────────────────────────────────────────────────

// 20. Returns array
function testFts5SearchProgramsReturnsArray($test) {
    $db = _fts_db();
    if (!$db) { echo ' [SKIP:no db] '; return; }
    $result = fts5_search_programs($db, 'abc', null, 5);
    $test->assertTrue(is_array($result), 'fts5_search_programs should return array');
    $db = null;
}

// 21. Short query returns empty array (no FTS call)
function testFts5SearchProgramsShortQueryEmpty($test) {
    $db = _fts_db();
    if (!$db) { echo ' [SKIP:no db] '; return; }
    $result = fts5_search_programs($db, 'ab', null, 5);
    $test->assertEquals([], $result, 'Query < 3 chars should return empty array');
    $db = null;
}

// 22. Result rows have 'snippet' key
function testFts5SearchProgramsResultHasSnippet($test) {
    $db = _fts_db();
    if (!$db) { echo ' [SKIP:no db] '; return; }
    // Use a term likely to exist in any dataset, fallback gracefully
    $result = fts5_search_programs($db, 'the', null, 3);
    if (!empty($result)) {
        $test->assertContains('snippet', array_keys($result[0]), 'Result row should have snippet key');
    } else {
        echo ' [SKIP:no data] ';
    }
    $db = null;
}

// ── fts5_search_events() ─────────────────────────────────────────────────────

// 23. Returns array
function testFts5SearchEventsReturnsArray($test) {
    $db = _fts_db();
    if (!$db) { echo ' [SKIP:no db] '; return; }
    $result = fts5_search_events($db, 'abc', 5);
    $test->assertTrue(is_array($result), 'fts5_search_events should return array');
    $db = null;
}

// 24. Short query returns empty array
function testFts5SearchEventsShortQueryEmpty($test) {
    $db = _fts_db();
    if (!$db) { echo ' [SKIP:no db] '; return; }
    $result = fts5_search_events($db, 'ab', 5);
    $test->assertEquals([], $result, 'Query < 3 chars should return empty array');
    $db = null;
}

// ── fts5_search_artists() ────────────────────────────────────────────────────

// 25. Returns array
function testFts5SearchArtistsReturnsArray($test) {
    $db = _fts_db();
    if (!$db) { echo ' [SKIP:no db] '; return; }
    $result = fts5_search_artists($db, 'abc', 5);
    $test->assertTrue(is_array($result), 'fts5_search_artists should return array');
    $db = null;
}

// ── fts5_search_all() ────────────────────────────────────────────────────────

// 26. Returns all three keys
function testFts5SearchAllReturnsAllKeys($test) {
    $db = _fts_db();
    if (!$db) { echo ' [SKIP:no db] '; return; }
    $result = fts5_search_all($db, 'stage');
    $test->assertTrue(isset($result['programs']), 'search_all should have programs key');
    $test->assertTrue(isset($result['events']),   'search_all should have events key');
    $test->assertTrue(isset($result['artists']),  'search_all should have artists key');
    $db = null;
}

// ── fts5_rebuild_all() ───────────────────────────────────────────────────────

// 27. fts5_rebuild_all runs without error
function testFts5RebuildAllNoError($test) {
    $db = _fts_db();
    if (!$db) { echo ' [SKIP:no db] '; return; }
    $threw = false;
    try { fts5_rebuild_all($db); } catch (Exception $e) { $threw = true; }
    $test->assertFalse($threw, 'fts5_rebuild_all should not throw');
    $db = null;
}

// ── File checks ──────────────────────────────────────────────────────────────

// 28. Migration script exists
function testFts5MigrationScriptExists($test) {
    $test->assertTrue(file_exists(__DIR__ . '/../tools/migrate-add-fts5.php'),
        'FTS5 migration script should exist');
}

// 29. functions/search.php loaded
function testFts5SearchPhpLoaded($test) {
    $test->assertTrue(function_exists('fts5_search_programs'), 'fts5_search_programs should be defined');
    $test->assertTrue(function_exists('fts5_search_events'),   'fts5_search_events should be defined');
    $test->assertTrue(function_exists('fts5_search_artists'),  'fts5_search_artists should be defined');
    $test->assertTrue(function_exists('fts5_search_all'),      'fts5_search_all should be defined');
    $test->assertTrue(function_exists('fts5_rebuild_all'),     'fts5_rebuild_all should be defined');
}

// 30. config.php requires search.php
function testFts5ConfigRequiresSearchPhp($test) {
    $cfg = file_get_contents(__DIR__ . '/../config.php');
    $test->assertContains('functions/search.php', $cfg, 'config.php should require search.php');
}

// 31. api.php has action=search case
function testFts5ApiHasSearchAction($test) {
    $api = file_get_contents(__DIR__ . '/../api.php');
    $test->assertContains("case 'search':", $api, 'api.php should have search action');
}

// 32. api.php handles ?q= in programs
function testFts5ApiProgramsHandlesQParam($test) {
    $api = file_get_contents(__DIR__ . '/../api.php');
    $test->assertContains('fts5_search_programs', $api, 'api.php should call fts5_search_programs');
}

// 33. admin/api.php uses FTS in listPrograms
function testFts5AdminListProgramsUsesFts($test) {
    $api = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertContains('fts5_search_programs', $api, 'admin/api.php should call fts5_search_programs');
}

// 34. admin/api.php uses FTS in listEvents
function testFts5AdminListEventsUsesFts($test) {
    $api = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertContains('fts5_search_events', $api, 'admin/api.php should call fts5_search_events');
}

// 35. admin/api.php uses FTS in listArtists
function testFts5AdminListArtistsUsesFts($test) {
    $api = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertContains('fts5_search_artists', $api, 'admin/api.php should call fts5_search_artists');
}

// 36. admin/api.php calls fts5_rebuild_all after ICS import
function testFts5AdminRebuildAfterImport($test) {
    $api = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertContains('fts5_rebuild_all', $api, 'admin/api.php should call fts5_rebuild_all after ICS import');
}

// 37. index.php has search bar HTML
function testFts5IndexHasSearchBar($test) {
    $idx = file_get_contents(__DIR__ . '/../index.php');
    $test->assertContains('fts-search-form', $idx, 'index.php should have fts-search-form');
}

// 38. index.php calls fts5_search_events (paginated, replaces fts5_search_all)
function testFts5IndexCallsSearchAll($test) {
    $idx = file_get_contents(__DIR__ . '/../index.php');
    $test->assertContains('fts5_search_events', $idx,
        'index.php should call fts5_search_events (paginated search results v9.0.0)');
}

// 39. artists.php calls fts5_search_artists
function testFts5ArtistsPhpUsesFts($test) {
    $art = file_get_contents(__DIR__ . '/../artists.php');
    $test->assertContains('fts5_search_artists', $art, 'artists.php should call fts5_search_artists');
}

// 40. CSS has .fts-search-form
function testFts5CssSearchFormExists($test) {
    $css = file_get_contents(__DIR__ . '/../styles/index.css');
    $test->assertContains('.fts-search-form', $css, 'index.css should define .fts-search-form');
}

// 41. CSS has .fts-result-item
function testFts5CssResultItemExists($test) {
    $css = file_get_contents(__DIR__ . '/../styles/index.css');
    $test->assertContains('.fts-result-item', $css, 'index.css should define .fts-result-item');
}

// 42. Translations: search.placeholder in all 3 languages
function testFts5TranslationsSearchPlaceholder($test) {
    $trans = file_get_contents(__DIR__ . '/../js/translations.js');
    $count = substr_count($trans, "'search.placeholder'");
    $test->assertEquals(3, $count, 'translations.js should have search.placeholder in 3 languages');
}

// 43. Translations: search.noResults in all 3 languages
function testFts5TranslationsSearchNoResults($test) {
    $trans = file_get_contents(__DIR__ . '/../js/translations.js');
    $count = substr_count($trans, "'search.noResults'");
    $test->assertEquals(3, $count, 'translations.js should have search.noResults in 3 languages');
}

// 44. setup.php checks hasFts5Tables
function testFts5SetupPhpHasFts5Check($test) {
    $setup = file_get_contents(__DIR__ . '/../setup.php');
    $test->assertContains('$hasFts5Tables', $setup, 'setup.php should check $hasFts5Tables');
}

// 45. setup.php checks hasFts5Triggers
function testFts5SetupPhpHasFts5TriggersCheck($test) {
    $setup = file_get_contents(__DIR__ . '/../setup.php');
    $test->assertContains('$hasFts5Triggers', $setup, 'setup.php should check $hasFts5Triggers');
}
