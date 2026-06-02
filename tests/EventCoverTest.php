<?php
/**
 * Event Cover Image Tests (v7.5.0)
 * Tests: schema, migration idempotency, get_event_cover_image() helper,
 *        admin API upload/delete, homepage HTML/CSS, translations.
 */

require_once __DIR__ . '/../config.php';

// ── DB helpers ───────────────────────────────────────────────────────────────

function _ec_db(): ?PDO {
    if (!file_exists(DB_PATH)) return null;
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA busy_timeout = 3000");
    return $db;
}

function _ec_eventCols(): array {
    $db = _ec_db();
    if (!$db) return [];
    $stmt = $db->query("PRAGMA table_info(events)");
    $c = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    $stmt->closeCursor(); $stmt = null; $db = null;
    return $c;
}

function _ec_insertEvent(string $slug): int {
    $db = _ec_db();
    if (!$db) return 0;
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("INSERT OR IGNORE INTO events (slug, name, created_at, updated_at) VALUES (?,?,?,?)");
    $stmt->execute([$slug, 'EC Test Event', $now, $now]);
    $stmt->closeCursor(); $stmt = null;
    $id = (int)$db->lastInsertId();
    if (!$id) {
        $id = (int)$db->query("SELECT id FROM events WHERE slug = " . $db->quote($slug))->fetchColumn();
    }
    $db = null;
    return $id;
}

function _ec_deleteEvent(string $slug): void {
    $db = _ec_db();
    if (!$db) return;
    $stmt = $db->prepare("DELETE FROM events WHERE slug = ?");
    $stmt->execute([$slug]);
    $stmt->closeCursor(); $stmt = null; $db = null;
}

function _ec_setCover(int $eid, ?string $path): void {
    $db = _ec_db();
    if (!$db) return;
    $stmt = $db->prepare("UPDATE events SET cover_image = ? WHERE id = ?");
    $stmt->execute([$path, $eid]);
    $stmt->closeCursor(); $stmt = null; $db = null;
}

// ── Tests ─────────────────────────────────────────────────────────────────────

// 1. Schema: cover_image column exists
function testEcSchemaCoverImageColumnExists($test) {
    $cols = _ec_eventCols();
    $test->assertTrue(in_array('cover_image', $cols), 'events.cover_image column should exist');
}

// 2. Schema: cover_image is TEXT and nullable
function testEcSchemaCoverImageIsTextNullable($test) {
    $db = _ec_db();
    if (!$db) { echo ' [SKIP:no db] '; return; }
    $rows = $db->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_ASSOC);
    $db = null;
    foreach ($rows as $row) {
        if ($row['name'] === 'cover_image') {
            $test->assertEquals('TEXT', strtoupper($row['type']), 'cover_image type should be TEXT');
            $test->assertEquals('0', (string)$row['notnull'], 'cover_image should allow NULL');
            return;
        }
    }
    $test->assertTrue(false, 'cover_image column not found in PRAGMA');
}

// 3. Migration idempotency: duplicate ALTER TABLE should throw "duplicate column"
function testEcMigrationIdempotent($test) {
    $db = _ec_db();
    if (!$db) { echo ' [SKIP:no db] '; return; }
    $threw = false;
    try {
        $db->exec("ALTER TABLE events ADD COLUMN cover_image TEXT DEFAULT NULL");
    } catch (PDOException $e) {
        $threw = true;
        $test->assertContains('duplicate column', strtolower($e->getMessage()),
            'Should throw duplicate column error when re-running migration');
    }
    $db = null;
    $test->assertTrue($threw, 'Re-running migration on existing column should throw');
}

// 4. Helper: returns cover_image when set
function testEcHelperReturnsCoverImage($test) {
    $event = ['id' => 1, 'cover_image' => 'uploads/events/1/cover_abc.jpg'];
    $result = get_event_cover_image($event, 'uploads/events/1/fallback.jpg');
    $test->assertEquals('uploads/events/1/cover_abc.jpg', $result,
        'Should return cover_image over fallback');
}

// 5. Helper: falls back to fallbackPicture when cover_image empty
function testEcHelperFallbackPicture($test) {
    $event = ['id' => 1, 'cover_image' => ''];
    $result = get_event_cover_image($event, 'uploads/events/1/pic.jpg');
    $test->assertEquals('uploads/events/1/pic.jpg', $result,
        'Should return fallback when cover_image empty string');
}

// 6. Helper: returns null when both empty
function testEcHelperReturnsNullWhenNoCover($test) {
    $event = ['id' => 1, 'cover_image' => null];
    $result = get_event_cover_image($event, null);
    $test->assertNull($result, 'Should return null when no cover and no fallback');
}

// 7. Helper: missing key falls back
function testEcHelperHandlesMissingKey($test) {
    $event = ['id' => 1];
    $result = get_event_cover_image($event, 'uploads/events/1/fallback.jpg');
    $test->assertEquals('uploads/events/1/fallback.jpg', $result,
        'Should use fallback when cover_image key missing from array');
}

// 8. DB: store and retrieve cover_image path
function testEcDbStoreAndRetrieve($test) {
    $db = _ec_db();
    if (!$db) { echo ' [SKIP:no db] '; return; }
    $db = null;
    $slug = 'ec-test-store-' . uniqid();
    $eid = _ec_insertEvent($slug);
    if (!$eid) { echo ' [SKIP:insert failed] '; return; }
    $testPath = 'uploads/events/' . $eid . '/cover_test.jpg';
    _ec_setCover($eid, $testPath);
    $db2 = _ec_db();
    $row = $db2->query("SELECT cover_image FROM events WHERE id = $eid")->fetch(PDO::FETCH_ASSOC);
    $db2 = null;
    $test->assertEquals($testPath, $row['cover_image'], 'Should store and retrieve cover_image path');
    _ec_deleteEvent($slug);
}

// 9. DB: can clear cover_image to NULL
function testEcDbClearToNull($test) {
    $db = _ec_db();
    if (!$db) { echo ' [SKIP:no db] '; return; }
    $db = null;
    $slug = 'ec-test-null-' . uniqid();
    $eid = _ec_insertEvent($slug);
    if (!$eid) { echo ' [SKIP:insert failed] '; return; }
    _ec_setCover($eid, 'uploads/events/' . $eid . '/cover_test.jpg');
    _ec_setCover($eid, null);
    $db2 = _ec_db();
    $row = $db2->query("SELECT cover_image FROM events WHERE id = $eid")->fetch(PDO::FETCH_ASSOC);
    $db2 = null;
    $test->assertNull($row['cover_image'], 'Should clear cover_image to NULL');
    _ec_deleteEvent($slug);
}

// 10. Admin API: event_cover_upload case registered
function testEcAdminApiHasCoverUploadCase($test) {
    $api = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertContains("case 'event_cover_upload':", $api,
        'admin/api.php should have event_cover_upload case');
}

// 11. Admin API: event_cover_delete case registered
function testEcAdminApiHasCoverDeleteCase($test) {
    $api = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertContains("case 'event_cover_delete':", $api,
        'admin/api.php should have event_cover_delete case');
}

// 12. Admin API: uploadEventCover function defined
function testEcAdminApiUploadFunctionDefined($test) {
    $api = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertContains('function uploadEventCover()', $api,
        'uploadEventCover function should be defined');
}

// 13. Admin API: deleteEventCover function defined
function testEcAdminApiDeleteFunctionDefined($test) {
    $api = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertContains('function deleteEventCover()', $api,
        'deleteEventCover function should be defined');
}

// 14. Admin API: uploadEventCover invalidates query cache
function testEcUploadInvalidatesQueryCache($test) {
    $api = file_get_contents(__DIR__ . '/../admin/api.php');
    $start = strpos($api, 'function uploadEventCover()');
    $end   = strpos($api, 'function deleteEventCover()');
    $fn    = substr($api, $start, $end - $start);
    $test->assertContains('invalidate_query_cache', $fn,
        'uploadEventCover should call invalidate_query_cache');
}

// 15. Admin API: deleteEventCover invalidates query cache
function testEcDeleteInvalidatesQueryCache($test) {
    $api = file_get_contents(__DIR__ . '/../admin/api.php');
    $start = strpos($api, 'function deleteEventCover()');
    $end   = strpos($api, 'function deleteArtistPicture()');
    $fn    = substr($api, $start, $end - $start);
    $test->assertContains('invalidate_query_cache', $fn,
        'deleteEventCover should call invalidate_query_cache');
}

// 16. Helper function is callable
function testEcHelperFunctionDefined($test) {
    $test->assertTrue(function_exists('get_event_cover_image'),
        'get_event_cover_image should be defined');
}

// 17. Admin UI: eventCoverSection div exists
function testEcAdminUiCoverSectionExists($test) {
    $ui = file_get_contents(__DIR__ . '/../admin/index.php');
    $test->assertContains('id="eventCoverSection"', $ui,
        'admin/index.php should have eventCoverSection element');
}

// 18. Admin UI: Cropper.js lazy loader function present
function testEcAdminUiCropperLoader($test) {
    $ui = file_get_contents(__DIR__ . '/../admin/index.php');
    $test->assertContains('loadCropperJs', $ui,
        'admin/index.php should have loadCropperJs function');
}

// 19. Admin UI: cropperjs CDN referenced
function testEcAdminUiCropperjsCdn($test) {
    $ui = file_get_contents(__DIR__ . '/../admin/index.php');
    $test->assertContains('cropperjs', strtolower($ui),
        'admin/index.php should reference cropperjs');
}

// 20. Admin UI: Cropper aspect ratio 16/9 set
function testEcAdminUiCropperAspectRatio($test) {
    $ui = file_get_contents(__DIR__ . '/../admin/index.php');
    $test->assertContains('16 / 9', $ui,
        'admin/index.php should set Cropper aspectRatio to 16/9');
}

// 21. Homepage: hero-carousel class in listing HTML
function testEcHomepageHeroCarouselHtml($test) {
    $idx = file_get_contents(__DIR__ . '/../index.php');
    $test->assertContains('hero-carousel', $idx,
        'index.php should have hero-carousel class');
}

// 22. Homepage: events-grid class in listing HTML
function testEcHomepageEventsGridHtml($test) {
    $idx = file_get_contents(__DIR__ . '/../index.php');
    $test->assertContains('events-grid', $idx,
        'index.php should have events-grid class');
}

// 23. Homepage: fts search bar present (categories-section removed in v9.0.0)
function testEcHomepageCategoriesSection($test) {
    $idx = file_get_contents(__DIR__ . '/../index.php');
    $test->assertContains('fts-search-form', $idx,
        'index.php should have fts-search-form (categories-section removed v9.0.0)');
}

// 24. Homepage: hero-calendar-row layout
function testEcHomepageHeroCalendarRow($test) {
    $idx = file_get_contents(__DIR__ . '/../index.php');
    $test->assertContains('hero-calendar-row', $idx,
        'index.php should have hero-calendar-row class');
}

// 25. Homepage: calls get_event_cover_image
function testEcHomepageCallsHelper($test) {
    $idx = file_get_contents(__DIR__ . '/../index.php');
    $test->assertContains('get_event_cover_image', $idx,
        'index.php should call get_event_cover_image');
}

// 26. CSS: .hero-carousel defined
function testEcCssHeroCarousel($test) {
    $css = file_get_contents(__DIR__ . '/../styles/index.css');
    $test->assertContains('.hero-carousel', $css,
        'index.css should define .hero-carousel');
}

// 27. CSS: .events-grid defined
function testEcCssEventsGrid($test) {
    $css = file_get_contents(__DIR__ . '/../styles/index.css');
    $test->assertContains('.events-grid', $css,
        'index.css should define .events-grid');
}

// 28. CSS: .fts-result-item defined (category tiles removed in v9.0.0)
function testEcCssCategoryTile($test) {
    $css = file_get_contents(__DIR__ . '/../styles/index.css');
    $test->assertContains('.fts-result-item', $css,
        'index.css should define .fts-result-item (category-tile removed v9.0.0)');
}

// 29. CSS: hero-calendar-row grid defined
function testEcCssHeroCalendarRow($test) {
    $css = file_get_contents(__DIR__ . '/../styles/index.css');
    $test->assertContains('.hero-calendar-row', $css,
        'index.css should define .hero-calendar-row');
}

// 30. Translations: homepage.categoriesTitle in all 3 languages
function testEcTranslationsCategoryTitle($test) {
    $trans = file_get_contents(__DIR__ . '/../js/translations.js');
    $count = substr_count($trans, "'homepage.categoriesTitle'");
    $test->assertEquals(3, $count,
        'translations.js should have homepage.categoriesTitle key in 3 languages');
}

// 31. Migration script exists
function testEcMigrationScriptExists($test) {
    $path = __DIR__ . '/../tools/migrate-add-event-cover-image-column.php';
    $test->assertTrue(file_exists($path), 'Migration script should exist');
}

// 32. setup.php includes hasCoverImageColumn
function testEcSetupPhpHasCoverCheck($test) {
    $setup = file_get_contents(__DIR__ . '/../setup.php');
    $test->assertContains('$hasCoverImageColumn', $setup,
        'setup.php should reference $hasCoverImageColumn in allTablesOk');
}

// 33. Schema: cover_image_card column exists
function testEcSchemaCoverImageCardColumnExists($test) {
    $cols = _ec_eventCols();
    $test->assertTrue(in_array('cover_image_card', $cols),
        'events.cover_image_card column should exist');
}

// 34. Schema: cover_image_card is TEXT nullable
function testEcSchemaCoverImageCardIsTextNullable($test) {
    $db = _ec_db();
    if (!$db) { echo ' [SKIP:no db] '; return; }
    $rows = $db->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_ASSOC);
    $db = null;
    foreach ($rows as $row) {
        if ($row['name'] === 'cover_image_card') {
            $test->assertEquals('TEXT', strtoupper($row['type']), 'cover_image_card should be TEXT');
            $test->assertEquals('0', (string)$row['notnull'], 'cover_image_card should allow NULL');
            return;
        }
    }
    $test->assertTrue(false, 'cover_image_card column not found in PRAGMA');
}

// 35. DB: store and retrieve cover_image_card
function testEcDbStoreCoverImageCard($test) {
    $db = _ec_db();
    if (!$db) { echo ' [SKIP:no db] '; return; }
    $db = null;
    $slug = 'ec-test-card-' . uniqid();
    $eid = _ec_insertEvent($slug);
    if (!$eid) { echo ' [SKIP:insert failed] '; return; }
    $testPath = 'uploads/events/' . $eid . '/card_test.jpg';
    $db2 = _ec_db();
    $stmt = $db2->prepare("UPDATE events SET cover_image_card = ? WHERE id = ?");
    $stmt->execute([$testPath, $eid]);
    $stmt->closeCursor(); $stmt = null;
    $row = $db2->query("SELECT cover_image_card FROM events WHERE id = $eid")->fetch(PDO::FETCH_ASSOC);
    $db2 = null;
    $test->assertEquals($testPath, $row['cover_image_card'], 'Should store cover_image_card path');
    _ec_deleteEvent($slug);
}

// 36. API: upload validates cover_type param
function testEcApiUploadAcceptsCoverTypeCard($test) {
    $api = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertContains("'hero', 'card'", $api,
        'uploadEventCover should validate cover_type against hero/card allowlist');
}

// 37. API: upload uses cover_image_card column for card type
function testEcApiUploadWritesCoverImageCard($test) {
    $api = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertContains('cover_image_card', $api,
        'admin/api.php should reference cover_image_card column');
}

// 38. API: delete accepts cover_type in JSON body
function testEcApiDeleteAcceptsCoverTypeParam($test) {
    $api = file_get_contents(__DIR__ . '/../admin/api.php');
    $start = strpos($api, 'function deleteEventCover()');
    $end   = strpos($api, 'function deleteArtistPicture()');
    $fn    = substr($api, $start, $end - $start);
    $test->assertContains("cover_type", $fn,
        'deleteEventCover should read cover_type from input');
}

// 39. index.php: uses cover_image_card for event cards
function testEcHomepageUsesCardCover($test) {
    $idx = file_get_contents(__DIR__ . '/../index.php');
    $test->assertContains('cover_image_card', $idx,
        'index.php should use cover_image_card for event grid cards');
}

// 40. admin/index.php: has two separate cover upload buttons
function testEcAdminUiHeroCoverSection($test) {
    $ui = file_get_contents(__DIR__ . '/../admin/index.php');
    $test->assertContains('coverHeroFileInput', $ui,
        'admin/index.php should have Hero cover file input');
}

// 41. admin/index.php: has card cover section
function testEcAdminUiCardCoverSection($test) {
    $ui = file_get_contents(__DIR__ . '/../admin/index.php');
    $test->assertContains('coverCardFileInput', $ui,
        'admin/index.php should have Card cover file input');
}

// 42. admin/index.php: Cropper aspect ratio changes per type
function testEcAdminUiCropperDynamicAspect($test) {
    $ui = file_get_contents(__DIR__ . '/../admin/index.php');
    $test->assertContains('4 / 3', $ui,
        'admin/index.php should set Cropper to 4/3 for card covers');
}

// 43. migration script for card column exists
function testEcMigrationScriptCardExists($test) {
    $path = __DIR__ . '/../tools/migrate-add-event-cover-image-card-column.php';
    $test->assertTrue(file_exists($path), 'Card cover migration script should exist');
}

// 44. setup.php references hasCoverImageCardColumn
function testEcSetupPhpHasCoverCardCheck($test) {
    $setup = file_get_contents(__DIR__ . '/../setup.php');
    $test->assertContains('$hasCoverImageCardColumn', $setup,
        'setup.php should reference $hasCoverImageCardColumn');
}
