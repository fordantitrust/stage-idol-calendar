<?php
/**
 * Event Pictures Gallery Tests (v6.6.0)
 */

require_once __DIR__ . '/../config.php';

// ── Helpers ──────────────────────────────────────────────────────────────────

function _ep_db(): ?PDO {
    if (!file_exists(DB_PATH)) return null;
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA busy_timeout = 3000");
    return $db;
}

function _ep_tables(): array {
    $db = _ep_db();
    if (!$db) return [];
    $t = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    $db = null;
    return $t;
}

function _ep_eventColumns(): array {
    $db = _ep_db();
    if (!$db) return [];
    $stmt = $db->query("PRAGMA table_info(events)");
    $c = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    $stmt->closeCursor(); $stmt = null; $db = null;
    return $c;
}

function _ep_epColumns(): array {
    $db = _ep_db();
    if (!$db) return [];
    if (!in_array('event_pictures', _ep_tables())) { $db = null; return []; }
    $stmt = $db->query("PRAGMA table_info(event_pictures)");
    $c = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    $stmt->closeCursor(); $stmt = null; $db = null;
    return $c;
}

function _ep_insertEvent(string $slug): int {
    $db = _ep_db();
    if (!$db) return 0;
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("INSERT OR IGNORE INTO events (slug, name, created_at, updated_at) VALUES (?,?,?,?)");
    $stmt->execute([$slug, 'EP Test Event', $now, $now]);
    $stmt->closeCursor(); $stmt = null;
    $id = (int)$db->lastInsertId();
    if (!$id) {
        $id = (int)$db->query("SELECT id FROM events WHERE slug = " . $db->quote($slug))->fetchColumn();
    }
    $db = null;
    return $id;
}

function _ep_insertPicture(int $eid, string $fn = 'test.jpg', int $ord = 0): int {
    $db = _ep_db();
    if (!$db) return 0;
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("INSERT INTO event_pictures (event_id, filename, caption, display_order, created_at) VALUES (?,?,?,?,?)");
    $stmt->execute([$eid, $fn, 'cap', $ord, $now]);
    $stmt->closeCursor(); $stmt = null;
    $id = (int)$db->lastInsertId();
    $db = null;
    return $id;
}

function _ep_cleanEvent(string $slug): void {
    $db = _ep_db();
    if (!$db) return;
    $eid = $db->query("SELECT id FROM events WHERE slug = " . $db->quote($slug))->fetchColumn();
    if ($eid && in_array('event_pictures', _ep_tables())) {
        $db->exec("DELETE FROM event_pictures WHERE event_id = $eid");
    }
    if ($eid) $db->exec("DELETE FROM events WHERE id = $eid");
    $db = null;
}

// ── DB Schema ─────────────────────────────────────────────────────────────────

function testEpEventPicturesTableExists($test) {
    $test->assertTrue(in_array('event_pictures', _ep_tables()), 'event_pictures table exists');
}

function testEpEventPicturesColumns($test) {
    $cols = _ep_epColumns();
    foreach (['id', 'event_id', 'filename', 'caption', 'display_order', 'created_at'] as $c) {
        $test->assertTrue(in_array($c, $cols), "event_pictures.$c column exists");
    }
}

function testEpEventPicturesIndexes($test) {
    $db = _ep_db();
    if (!$db) { echo ' [SKIP:no db] '; return; }
    $idx = $db->query("SELECT name FROM sqlite_master WHERE type='index'")->fetchAll(PDO::FETCH_COLUMN);
    $db = null;
    $test->assertTrue(in_array('idx_event_pictures_event_id', $idx), 'idx_event_pictures_event_id');
    $test->assertTrue(in_array('idx_event_pictures_order', $idx),    'idx_event_pictures_order');
}

function testEpGalleryTemplateColumnExists($test) {
    $test->assertTrue(in_array('gallery_template', _ep_eventColumns()), 'events.gallery_template column exists');
}

function testEpGalleryTemplateDefault($test) {
    $db = _ep_db();
    if (!$db) { echo ' [SKIP:no db] '; return; }
    $info = $db->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_ASSOC);
    $db = null;
    $found = false;
    foreach ($info as $row) {
        if ($row['name'] === 'gallery_template') {
            // SQLite stores quoted default: "'grid3'" — strip surrounding quotes before comparing
            $dflt = trim($row['dflt_value'], "'\"");
            $test->assertEquals('grid3', $dflt, 'gallery_template default is grid3');
            $found = true;
        }
    }
    $test->assertTrue($found, 'gallery_template column found in PRAGMA');
}

// ── Migration Script ──────────────────────────────────────────────────────────

function testEpMigrationScriptExists($test) {
    $test->assertTrue(
        file_exists(__DIR__ . '/../tools/migrate-add-event-pictures-table.php'),
        'migration script exists'
    );
}

function testEpMigrationScriptIdempotent($test) {
    $src = file_get_contents(__DIR__ . '/../tools/migrate-add-event-pictures-table.php');
    $test->assertTrue(
        strpos($src, 'IF NOT EXISTS') !== false || strpos($src, "in_array('event_pictures'") !== false,
        'migration is idempotent'
    );
}

function testEpMigrationScriptHasGalleryTemplate($test) {
    $src = file_get_contents(__DIR__ . '/../tools/migrate-add-event-pictures-table.php');
    $test->assertTrue(strpos($src, 'gallery_template') !== false, 'migration handles gallery_template');
}

// ── DB CRUD ───────────────────────────────────────────────────────────────────

function testEpInsertAndSelectPicture($test) {
    if (!file_exists(DB_PATH)) { echo ' [SKIP:no db] '; return; }
    if (!in_array('event_pictures', _ep_tables())) { echo ' [SKIP:no table] '; return; }
    $slug = 'ep-test-' . time();
    $eid  = _ep_insertEvent($slug);
    $test->assertTrue($eid > 0, 'event inserted');
    $pid  = _ep_insertPicture($eid, 'uploads/events/t.jpg', 0);
    $test->assertTrue($pid > 0, 'picture inserted');
    $db = _ep_db();
    $row = $db->query("SELECT * FROM event_pictures WHERE id=$pid")->fetch(PDO::FETCH_ASSOC);
    $db = null;
    $test->assertEquals($eid, (int)$row['event_id'], 'event_id matches');
    $test->assertEquals('uploads/events/t.jpg', $row['filename'], 'filename stored');
    _ep_cleanEvent($slug);
}

function testEpReorderDisplayOrder($test) {
    if (!file_exists(DB_PATH)) { echo ' [SKIP:no db] '; return; }
    if (!in_array('event_pictures', _ep_tables())) { echo ' [SKIP:no table] '; return; }
    $slug = 'ep-reorder-' . time();
    $eid  = _ep_insertEvent($slug);
    $p1   = _ep_insertPicture($eid, 'a.jpg', 0);
    $p2   = _ep_insertPicture($eid, 'b.jpg', 1);
    $db   = _ep_db();
    $db->exec("UPDATE event_pictures SET display_order=1 WHERE id=$p1");
    $db->exec("UPDATE event_pictures SET display_order=0 WHERE id=$p2");
    $rows = $db->query("SELECT id FROM event_pictures WHERE event_id=$eid ORDER BY display_order ASC")->fetchAll(PDO::FETCH_COLUMN);
    $db = null;
    $test->assertEquals($p2, (int)$rows[0], 'p2 first after reorder');
    _ep_cleanEvent($slug);
}

function testEpCascadeDeleteWithForeignKeys($test) {
    if (!file_exists(DB_PATH)) { echo ' [SKIP:no db] '; return; }
    if (!in_array('event_pictures', _ep_tables())) { echo ' [SKIP:no table] '; return; }
    $slug = 'ep-cascade-' . time();
    $eid  = _ep_insertEvent($slug);
    _ep_insertPicture($eid, 'c.jpg', 0);
    $db = _ep_db();
    $db->exec("PRAGMA foreign_keys = ON");
    $db->exec("DELETE FROM events WHERE id=$eid");
    $cnt = (int)$db->query("SELECT COUNT(*) FROM event_pictures WHERE event_id=$eid")->fetchColumn();
    $db  = null;
    $test->assertEquals(0, $cnt, 'pictures cascade-deleted');
}

// ── admin/api.php Source ──────────────────────────────────────────────────────

function testEpAdminApiSwitchCasesExist($test) {
    $src = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertTrue(strpos($src, "case 'event_pictures_list'") !== false,    'case event_pictures_list');
    $test->assertTrue(strpos($src, "case 'event_picture_upload'") !== false,   'case event_picture_upload');
    $test->assertTrue(strpos($src, "case 'event_picture_delete'") !== false,   'case event_picture_delete');
    $test->assertTrue(strpos($src, "case 'event_pictures_reorder'") !== false, 'case event_pictures_reorder');
}

function testEpAdminApiProcessAndSaveImageExists($test) {
    $src = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertTrue(strpos($src, 'function processAndSaveImage(') !== false, 'processAndSaveImage function');
}

function testEpAdminApiProcessFitModeExists($test) {
    $src = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertTrue(
        strpos($src, "= 'fit'") !== false || strpos($src, "mode='fit'") !== false || strpos($src, "'fit'") !== false,
        "mode='fit' exists"
    );
}

function testEpAdminApiThinWrapperExists($test) {
    $src = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertTrue(strpos($src, 'function processAndSaveArtistImage(') !== false, 'thin wrapper function');
    $test->assertTrue(strpos($src, 'return processAndSaveImage(') !== false, 'wrapper calls processAndSaveImage');
}

function testEpAdminApiUploadFunctionExists($test) {
    $src = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertTrue(strpos($src, 'function uploadEventPicture(') !== false, 'uploadEventPicture function');
}

function testEpAdminApiDeleteFunctionExists($test) {
    $src = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertTrue(strpos($src, 'function deleteEventPicture(') !== false, 'deleteEventPicture function');
}

function testEpAdminApiReorderFunctionExists($test) {
    $src = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertTrue(strpos($src, 'function reorderEventPictures(') !== false, 'reorderEventPictures function');
}

function testEpAdminApiListFunctionExists($test) {
    $src = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertTrue(strpos($src, 'function listEventPictures(') !== false, 'listEventPictures function');
}

function testEpAdminApiValidTemplates($test) {
    $src = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertTrue(strpos($src, '$validTemplates') !== false, '$validTemplates exists');
    foreach (['grid1', 'grid2', 'grid3', 'masonry'] as $t) {
        $test->assertTrue(strpos($src, "'$t'") !== false, "template '$t' present");
    }
}

function testEpAdminApiGalleryTemplateInCreateUpdate($test) {
    $src = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertTrue(strpos($src, 'gallery_template') !== false, 'gallery_template in api.php');
}

function testEpAdminApiGetEventIncludesPictures($test) {
    $src = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertTrue(strpos($src, 'FROM event_pictures') !== false, 'getEvent queries event_pictures');
}

function testEpAdminApiUploadValidation($test) {
    $src = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertTrue(
        strpos($src, 'UPLOAD_ERR_OK') !== false || strpos($src, 'upload_err') !== false,
        'upload error validation'
    );
}

function testEpAdminApiDeleteOwnershipCheck($test) {
    $src = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertTrue(
        strpos($src, 'event_id = :eid') !== false || strpos($src, 'AND event_id') !== false,
        'delete ownership check'
    );
}

function testEpAdminApiReorderTransaction($test) {
    $src = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertTrue(strpos($src, 'beginTransaction') !== false, 'reorder uses transaction');
}

function testEpAdminApiCacheInvalidation($test) {
    $src = file_get_contents(__DIR__ . '/../admin/api.php');
    $test->assertTrue(strpos($src, 'invalidate_query_cache') !== false, 'cache invalidation called');
}

// ── Directory / .htaccess ─────────────────────────────────────────────────────

function testEpUploadsEventsDirExists($test) {
    $test->assertTrue(is_dir(__DIR__ . '/../uploads/events'), 'uploads/events/ exists');
}

function testEpUploadsEventsDirWritable($test) {
    $test->assertTrue(is_writable(__DIR__ . '/../uploads/events'), 'uploads/events/ writable');
}

function testEpUploadsEventsHtaccessExists($test) {
    $test->assertTrue(file_exists(__DIR__ . '/../uploads/events/.htaccess'), 'uploads/events/.htaccess exists');
}

function testEpUploadsEventsHtaccessBlocksPhp($test) {
    $src = file_get_contents(__DIR__ . '/../uploads/events/.htaccess');
    $test->assertTrue(
        strpos($src, 'Deny from all') !== false || strpos($src, 'deny from all') !== false,
        '.htaccess Deny from all'
    );
    $test->assertTrue(strpos($src, '.php') !== false, '.htaccess references .php');
}

// ── setup.php ─────────────────────────────────────────────────────────────────

function testEpSetupEventPicturesCreateTable($test) {
    $src = file_get_contents(__DIR__ . '/../setup.php');
    $test->assertTrue(
        strpos($src, 'CREATE TABLE IF NOT EXISTS event_pictures') !== false,
        'setup.php CREATE TABLE event_pictures'
    );
}

function testEpSetupUploadsEventsInToCreate($test) {
    $src = file_get_contents(__DIR__ . '/../setup.php');
    $test->assertTrue(strpos($src, 'uploads/events') !== false, 'uploads/events in setup.php');
}

function testEpSetupDirChecksKey($test) {
    $src = file_get_contents(__DIR__ . '/../setup.php');
    $test->assertTrue(strpos($src, "'uploads_events'") !== false, '$dirChecks uploads_events key');
}

function testEpSetupHasGalleryTemplateColumn($test) {
    $src = file_get_contents(__DIR__ . '/../setup.php');
    $test->assertTrue(strpos($src, 'hasGalleryTemplateColumn') !== false, '$hasGalleryTemplateColumn in setup.php');
}

function testEpSetupAllTablesOk($test) {
    $src = file_get_contents(__DIR__ . '/../setup.php');
    $test->assertTrue(
        strpos($src, 'hasGalleryTemplateColumn') !== false && strpos($src, '$allTablesOk') !== false,
        '$allTablesOk includes $hasGalleryTemplateColumn'
    );
}

// ── index.php ─────────────────────────────────────────────────────────────────

function testEpIndexQueriesEventPictures($test) {
    $src = file_get_contents(__DIR__ . '/../index.php');
    $test->assertTrue(strpos($src, 'FROM event_pictures') !== false, 'index.php queries event_pictures');
}

function testEpIndexEventPicturesInSaveCache($test) {
    $src = file_get_contents(__DIR__ . '/../index.php');
    $test->assertTrue(strpos($src, "'event_pictures'") !== false, "index.php saves event_pictures in cache");
}

function testEpIndexEventPicturesFromCacheHit($test) {
    $src = file_get_contents(__DIR__ . '/../index.php');
    $test->assertTrue(
        strpos($src, "event_pictures' ?? []") !== false ||
        strpos($src, "event_pictures'] ?? []") !== false,
        'index.php reads event_pictures from cache hit'
    );
}

function testEpIndexGalleryHtml($test) {
    $src = file_get_contents(__DIR__ . '/../index.php');
    $test->assertTrue(strpos($src, 'event-gallery-section') !== false, 'gallery section HTML');
    $test->assertTrue(strpos($src, 'event-gallery-grid') !== false, 'gallery grid div');
}

function testEpIndexTemplateClassInjection($test) {
    $src = file_get_contents(__DIR__ . '/../index.php');
    $test->assertTrue(
        strpos($src, 'template-') !== false && strpos($src, 'gallery_template') !== false,
        'template class injected from gallery_template'
    );
}

function testEpIndexGalleryConditional($test) {
    $src = file_get_contents(__DIR__ . '/../index.php');
    $test->assertTrue(strpos($src, '!empty($eventPictures)') !== false, 'gallery conditional on eventPictures');
}

function testEpIndexLightboxHtml($test) {
    $src = file_get_contents(__DIR__ . '/../index.php');
    $test->assertTrue(strpos($src, 'eventPicLightbox') !== false, 'lightbox div');
    $test->assertTrue(strpos($src, 'ep-lightbox') !== false, 'ep-lightbox class');
}

function testEpIndexLightboxKeyboard($test) {
    $src = file_get_contents(__DIR__ . '/../index.php');
    $test->assertTrue(strpos($src, 'ArrowLeft') !== false,  'keyboard ArrowLeft');
    $test->assertTrue(strpos($src, 'ArrowRight') !== false, 'keyboard ArrowRight');
    $test->assertTrue(strpos($src, 'Escape') !== false,     'keyboard Escape');
}

function testEpIndexGalleryBeforeCrossEvent($test) {
    $src = file_get_contents(__DIR__ . '/../index.php');
    $gp = strpos($src, 'event-gallery-section');
    $cp = strpos($src, 'cross-event-section');
    $test->assertTrue($gp !== false && $cp !== false, 'both sections present');
    $test->assertTrue($gp < $cp, 'gallery before cross-event');
}

// ── admin/index.php ───────────────────────────────────────────────────────────

function testEpAdminGalleryTemplateSelectExists($test) {
    $src = file_get_contents(__DIR__ . '/../admin/index.php');
    $test->assertTrue(strpos($src, 'conventionGalleryTemplate') !== false, 'gallery template select');
}

function testEpAdminGalleryTemplateOptions($test) {
    $src = file_get_contents(__DIR__ . '/../admin/index.php');
    foreach (['grid3', 'grid2', 'grid1', 'masonry'] as $v) {
        $test->assertTrue(strpos($src, '"' . $v . '"') !== false, "option value $v present");
    }
}

function testEpAdminPictureSectionDiv($test) {
    $src = file_get_contents(__DIR__ . '/../admin/index.php');
    $test->assertTrue(strpos($src, 'eventPictureSection') !== false, 'eventPictureSection div');
}

function testEpAdminJsFunctions($test) {
    $src = file_get_contents(__DIR__ . '/../admin/index.php');
    foreach (['uploadEventPictures', 'deleteEventPicture', 'loadEventPictures', 'renderEventPictureGrid', 'resetEventPictureSection', 'showEventPictureSection'] as $fn) {
        $test->assertTrue(strpos($src, "function $fn(") !== false, "$fn JS function");
    }
}

function testEpAdminGalleryTemplateInSaveConvention($test) {
    $src = file_get_contents(__DIR__ . '/../admin/index.php');
    $test->assertTrue(strpos($src, 'gallery_template:') !== false, 'gallery_template in save data');
}

// ── CSS ───────────────────────────────────────────────────────────────────────

function testEpCssGallerySection($test) {
    $src = file_get_contents(__DIR__ . '/../styles/index.css');
    $test->assertTrue(strpos($src, 'event-gallery-section') !== false, '.event-gallery-section');
}

function testEpCssTemplates($test) {
    $src = file_get_contents(__DIR__ . '/../styles/index.css');
    foreach (['template-grid1', 'template-grid2', 'template-grid3', 'template-masonry'] as $cls) {
        $test->assertTrue(strpos($src, $cls) !== false, ".$cls in CSS");
    }
}

function testEpCssNoForcedCrop($test) {
    $src = file_get_contents(__DIR__ . '/../styles/index.css');
    $block = substr($src, strpos($src, 'Event Gallery'), 2500);
    $test->assertTrue(
        strpos($block, 'height: auto') !== false || strpos($block, 'height:auto') !== false,
        'height:auto (no crop) in gallery CSS'
    );
}

function testEpCssLightbox($test) {
    $src = file_get_contents(__DIR__ . '/../styles/index.css');
    $test->assertTrue(strpos($src, 'ep-lightbox') !== false, '.ep-lightbox in CSS');
}

// ── Translations ──────────────────────────────────────────────────────────────

function testEpTranslationsEventGalleryTH($test) {
    $src = file_get_contents(__DIR__ . '/../js/translations.js');
    $test->assertTrue(strpos($src, "'section.eventGallery'") !== false, 'section.eventGallery key');
    $test->assertTrue(strpos($src, 'รูปภาพจากงาน') !== false, 'TH translation');
}

function testEpTranslationsEventGalleryEN($test) {
    $src = file_get_contents(__DIR__ . '/../js/translations.js');
    $test->assertTrue(strpos($src, 'Event Gallery') !== false, 'EN translation');
}

function testEpTranslationsEventGalleryJA($test) {
    $src = file_get_contents(__DIR__ . '/../js/translations.js');
    $test->assertTrue(strpos($src, 'イベントギャラリー') !== false, 'JA translation');
}

function testEpAdminI18nGalleryTemplateKeys($test) {
    $src = file_get_contents(__DIR__ . '/../admin/js/admin-i18n.js');
    $test->assertTrue(strpos($src, 'galleryTemplateLabel') !== false, 'galleryTemplateLabel key');
    $test->assertTrue(strpos($src, 'Gallery Layout') !== false, 'EN galleryTemplateLabel');
    $test->assertTrue(strpos($src, 'galleryTemplate.grid3') !== false, 'galleryTemplate.grid3 key');
    $test->assertTrue(strpos($src, '3 Columns') !== false, 'EN grid3 value');
}
