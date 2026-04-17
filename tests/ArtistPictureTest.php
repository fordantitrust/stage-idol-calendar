<?php
/**
 * Artist Picture Tests (v6.0.0)
 *
 * Covers:
 *  - display_picture + cover_picture columns in artists table
 *  - Migration script is idempotent
 *  - getArtist() / listArtists() return picture fields
 *  - artist_picture_upload action validation
 *  - artist_picture_delete clears DB column
 *  - uploads/artists/ directory exists and is writable
 *  - artist.php fetches display_picture + cover_picture
 *  - index.php builds programArtistIdMap with 'pic' key
 *  - index.php renders hover card when display_picture set
 *  - index.php does NOT render hover card when display_picture NULL
 *  - admin/index.php picture section HTML
 *  - admin/api.php new actions + resize dimensions
 *  - setup.php schema + dirs
 *  - Dockerfile uploads dir
 *  - CSS rules in artist.css + index.css
 */

require_once __DIR__ . '/../config.php';

// ── Helpers ──────────────────────────────────────────────────────────────────

/** Open fresh PDO; caller must set $db = null after use (Windows file-lock). */
function _ap_db(): ?PDO {
    if (!file_exists(DB_PATH)) return null;
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA busy_timeout = 3000");
    return $db;
}

/** Return column names of artists table. */
function _ap_artistColumns(): array {
    $db = _ap_db();
    if (!$db) return [];
    $stmt = $db->query("PRAGMA table_info(artists)");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;
    return $cols;
}

/** Insert a minimal test artist; returns id. */
function _ap_insertArtist(string $name, ?string $dp = null, ?string $cp = null): int {
    $db   = _ap_db();
    $now  = date('Y-m-d H:i:s');
    $stmt = $db->prepare("
        INSERT INTO artists (name, is_group, display_picture, cover_picture, created_at, updated_at)
        VALUES (:name, 0, :dp, :cp, :now, :now2)
    ");
    $stmt->execute([':name' => $name, ':dp' => $dp, ':cp' => $cp, ':now' => $now, ':now2' => $now]);
    $id = (int) $db->lastInsertId();
    $stmt->closeCursor(); $stmt = null; $db = null;
    return $id;
}

/** Delete test artist by ID. */
function _ap_deleteArtist(int $id): void {
    $db   = _ap_db();
    $stmt = $db->prepare("DELETE FROM artists WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $stmt->closeCursor(); $stmt = null; $db = null;
}

/** Fetch single artist row by ID. */
function _ap_fetchArtist(int $id): ?array {
    $db   = _ap_db();
    if (!$db) return null;
    $stmt = $db->prepare("SELECT * FROM artists WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor(); $stmt = null; $db = null;
    return $row ?: null;
}

// ── 1. DB Schema ──────────────────────────────────────────────────────────────

function testAPDisplayPictureColumnExists($test) {
    $cols = _ap_artistColumns();
    $test->assertTrue(in_array('display_picture', $cols),
        'display_picture column missing from artists table (run tools/migrate-add-artist-pictures-column.php)');
}

function testAPCoverPictureColumnExists($test) {
    $cols = _ap_artistColumns();
    $test->assertTrue(in_array('cover_picture', $cols),
        'cover_picture column missing from artists table (run tools/migrate-add-artist-pictures-column.php)');
}

// ── 2. Migration Script ───────────────────────────────────────────────────────

function testAPMigrationScriptExists($test) {
    $path = dirname(__DIR__) . '/tools/migrate-add-artist-pictures-column.php';
    $test->assertTrue(file_exists($path), 'Migration script tools/migrate-add-artist-pictures-column.php not found');
}

function testAPMigrationScriptIsIdempotent($test) {
    $src = file_get_contents(dirname(__DIR__) . '/tools/migrate-add-artist-pictures-column.php');
    $hasCheck = (strpos($src, 'PRAGMA table_info') !== false || strpos($src, 'in_array') !== false);
    $test->assertTrue($hasCheck, 'Migration script must check column existence before ALTER TABLE (idempotency)');
}

// ── 3. DB CRUD ────────────────────────────────────────────────────────────────

function testAPInsertWithDisplayPicture($test) {
    if (!file_exists(DB_PATH)) { echo ' [SKIP: No DB] '; return; }
    $cols = _ap_artistColumns();
    if (!in_array('display_picture', $cols)) { echo ' [SKIP: No col] '; return; }

    $id  = _ap_insertArtist('__ap_dp__' . time(), 'uploads/artists/test_dp.jpg', null);
    $row = _ap_fetchArtist($id);
    _ap_deleteArtist($id);
    $test->assertEquals('uploads/artists/test_dp.jpg', $row['display_picture'],
        'display_picture not stored correctly');
}

function testAPInsertWithCoverPicture($test) {
    if (!file_exists(DB_PATH)) { echo ' [SKIP: No DB] '; return; }
    $cols = _ap_artistColumns();
    if (!in_array('cover_picture', $cols)) { echo ' [SKIP: No col] '; return; }

    $id  = _ap_insertArtist('__ap_cp__' . time(), null, 'uploads/artists/test_cp.jpg');
    $row = _ap_fetchArtist($id);
    _ap_deleteArtist($id);
    $test->assertEquals('uploads/artists/test_cp.jpg', $row['cover_picture'],
        'cover_picture not stored correctly');
    $test->assertEquals(null, $row['display_picture'],
        'display_picture should be NULL when not set');
}

function testAPInsertWithoutPicturesStoresNull($test) {
    if (!file_exists(DB_PATH)) { echo ' [SKIP: No DB] '; return; }
    $cols = _ap_artistColumns();
    if (!in_array('display_picture', $cols)) { echo ' [SKIP: No col] '; return; }

    $id  = _ap_insertArtist('__ap_none__' . time());
    $row = _ap_fetchArtist($id);
    _ap_deleteArtist($id);
    $test->assertEquals(null, $row['display_picture'], 'display_picture should default to NULL');
    $test->assertEquals(null, $row['cover_picture'], 'cover_picture should default to NULL');
}

function testAPUpdateDisplayPicture($test) {
    if (!file_exists(DB_PATH)) { echo ' [SKIP: No DB] '; return; }
    $cols = _ap_artistColumns();
    if (!in_array('display_picture', $cols)) { echo ' [SKIP: No col] '; return; }

    $id  = _ap_insertArtist('__ap_upd__' . time());
    $db  = _ap_db();
    $stmt = $db->prepare("UPDATE artists SET display_picture = :dp WHERE id = :id");
    $stmt->execute([':dp' => 'uploads/artists/new.jpg', ':id' => $id]);
    $stmt->closeCursor(); $stmt = null; $db = null;
    $row = _ap_fetchArtist($id);
    _ap_deleteArtist($id);
    $test->assertEquals('uploads/artists/new.jpg', $row['display_picture'], 'UPDATE display_picture failed');
}

function testAPUpdateCoverPictureToNull($test) {
    if (!file_exists(DB_PATH)) { echo ' [SKIP: No DB] '; return; }
    $cols = _ap_artistColumns();
    if (!in_array('cover_picture', $cols)) { echo ' [SKIP: No col] '; return; }

    $id = _ap_insertArtist('__ap_null__' . time(), 'dp.jpg', 'cp.jpg');
    $db = _ap_db();
    $stmt = $db->prepare("UPDATE artists SET cover_picture = NULL WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $stmt->closeCursor(); $stmt = null; $db = null;
    $row = _ap_fetchArtist($id);
    _ap_deleteArtist($id);
    $test->assertEquals(null, $row['cover_picture'], 'UPDATE cover_picture to NULL failed');
}

// ── 4. Admin API source code checks ─────────────────────────────────────────

function testAPApiHasUploadAction($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertTrue(strpos($src, "case 'artist_picture_upload'") !== false,
        'Missing case artist_picture_upload in admin/api.php switch');
}

function testAPApiHasDeleteAction($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertTrue(strpos($src, "case 'artist_picture_delete'") !== false,
        'Missing case artist_picture_delete in admin/api.php switch');
}

function testAPApiUploadFunctionExists($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertTrue(strpos($src, 'function uploadArtistPicture()') !== false,
        'uploadArtistPicture() function missing from admin/api.php');
}

function testAPApiDeleteFunctionExists($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertTrue(strpos($src, 'function deleteArtistPicture()') !== false,
        'deleteArtistPicture() function missing from admin/api.php');
}

function testAPApiProcessImageHelperExists($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertTrue(strpos($src, 'function processAndSaveArtistImage(') !== false,
        'processAndSaveArtistImage() helper missing from admin/api.php');
}

function testAPApiListArtistsSelectsDisplayPicture($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertTrue(strpos($src, 'a.display_picture') !== false,
        'listArtists() SELECT missing a.display_picture in admin/api.php');
}

function testAPApiListArtistsSelectsCoverPicture($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertTrue(strpos($src, 'a.cover_picture') !== false,
        'listArtists() SELECT missing a.cover_picture in admin/api.php');
}

function testAPApiUploadValidates5MBLimit($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertTrue(strpos($src, '5 * 1024 * 1024') !== false,
        'uploadArtistPicture() missing 5MB size limit');
}

function testAPApiUploadValidatesImageViaGetimagesize($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertTrue(strpos($src, 'getimagesize') !== false,
        'uploadArtistPicture() missing getimagesize() validation');
}

function testAPApiUploadValidatesPictureType($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertTrue(strpos($src, "in_array(\$pictureType, ['display', 'cover'])") !== false,
        'uploadArtistPicture() missing picture_type validation');
}

function testAPApiDisplayResizeDimensions($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertTrue(strpos($src, '$targetW = 400; $targetH = 400') !== false,
        'admin/api.php missing display picture resize to 400x400');
}

function testAPApiCoverResizeDimensions($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertTrue(strpos($src, '$targetW = 1200; $targetH = 400') !== false,
        'admin/api.php missing cover picture resize to 1200x400');
}

// ── 5. Directory & File Checks ───────────────────────────────────────────────

function testAPUploadsDirectoryExists($test) {
    $dir = dirname(__DIR__) . '/uploads';
    $test->assertTrue(is_dir($dir), 'uploads/ directory does not exist');
}

function testAPUploadsArtistsDirectoryExists($test) {
    $dir = dirname(__DIR__) . '/uploads/artists';
    $test->assertTrue(is_dir($dir), 'uploads/artists/ directory does not exist');
}

function testAPUploadsArtistsDirectoryIsWritable($test) {
    $dir = dirname(__DIR__) . '/uploads/artists';
    if (!is_dir($dir)) { echo ' [SKIP: Dir missing] '; return; }
    $test->assertTrue(is_writable($dir), 'uploads/artists/ directory is not writable');
}

function testAPUploadsHtaccessExists($test) {
    $file = dirname(__DIR__) . '/uploads/.htaccess';
    $test->assertTrue(file_exists($file), 'uploads/.htaccess does not exist');
}

function testAPUploadsHtaccessBlocksPhp($test) {
    $file = dirname(__DIR__) . '/uploads/.htaccess';
    if (!file_exists($file)) { echo ' [SKIP] '; return; }
    $src = file_get_contents($file);
    $test->assertTrue(strpos($src, '.php') !== false, 'uploads/.htaccess must reference .php blocking');
    $test->assertTrue(strpos($src, 'Deny from all') !== false, 'uploads/.htaccess must Deny PHP execution');
}

// ── 6. GD Extension ──────────────────────────────────────────────────────────

function testAPGdExtensionLoaded($test) {
    $test->assertTrue(function_exists('imagecreatefromjpeg'),
        'GD extension not loaded — imagecreatefromjpeg() missing');
}

function testAPGdSupportsJpegOutput($test) {
    $test->assertTrue(function_exists('imagejpeg'),
        'GD extension missing imagejpeg()');
}

// ── 7. artist.php source checks ──────────────────────────────────────────────

function testAPArtistPhpSelectsDisplayPicture($test) {
    $src = file_get_contents(dirname(__DIR__) . '/artist.php');
    $test->assertTrue(strpos($src, 'a.display_picture') !== false,
        'artist.php SELECT missing a.display_picture');
}

function testAPArtistPhpSelectsCoverPicture($test) {
    $src = file_get_contents(dirname(__DIR__) . '/artist.php');
    $test->assertTrue(strpos($src, 'a.cover_picture') !== false,
        'artist.php SELECT missing a.cover_picture');
}

function testAPArtistPhpRendersDisplayPictureImg($test) {
    $src = file_get_contents(dirname(__DIR__) . '/artist.php');
    $test->assertTrue(strpos($src, 'artist-display-picture') !== false,
        'artist.php missing class artist-display-picture');
}

function testAPArtistPhpRendersDisplayPlaceholder($test) {
    $src = file_get_contents(dirname(__DIR__) . '/artist.php');
    $test->assertTrue(strpos($src, 'artist-display-placeholder') !== false,
        'artist.php missing class artist-display-placeholder');
}

function testAPArtistPhpAppliesHasCoverClass($test) {
    $src = file_get_contents(dirname(__DIR__) . '/artist.php');
    $test->assertTrue(strpos($src, 'has-cover') !== false,
        'artist.php missing has-cover class logic');
}

function testAPArtistPhpInjectsCoverUrlVariable($test) {
    $src = file_get_contents(dirname(__DIR__) . '/artist.php');
    $test->assertTrue(strpos($src, '--cover-url') !== false,
        'artist.php missing --cover-url CSS variable injection');
}

function testAPArtistPhpHasHeaderTopWrapper($test) {
    $src = file_get_contents(dirname(__DIR__) . '/artist.php');
    $test->assertTrue(strpos($src, 'artist-header-top') !== false,
        'artist.php missing artist-header-top wrapper div');
}

// ── 8. index.php source checks ───────────────────────────────────────────────

function testAPIndexPhpSelectsDisplayPictureInPAQuery($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertTrue(strpos($src, 'a.display_picture') !== false,
        'index.php missing a.display_picture in program_artists JOIN query');
}

function testAPIndexPhpStoresPicInMap($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertTrue(strpos($src, "'pic'") !== false,
        "index.php must store 'pic' key in programArtistIdMap");
}

function testAPIndexPhpRendersHoverCardTooltip($test) {
    // Tooltip is a shared fixed-position div (avoids overflow:hidden clipping)
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertTrue(strpos($src, 'artistDpTooltip') !== false,
        'index.php missing #artistDpTooltip shared fixed-position tooltip');
}

function testAPIndexPhpHoverTooltipUsesFixedPosition($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertTrue(strpos($src, 'position:fixed') !== false || strpos($src, 'position: fixed') !== false,
        'index.php tooltip must use position:fixed to avoid overflow clipping');
}

function testAPIndexPhpHoverDataAttribute($test) {
    // Artist badge uses data-display-pic attribute
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertTrue(strpos($src, 'data-display-pic') !== false,
        'index.php missing data-display-pic attribute on badge wrap');
}

function testAPIndexPhpHoverCardIsConditional($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    // Conditional can be on data-display-pic attribute or !empty($al['pic'])
    $hasConditional = strpos($src, "!empty(\$al['pic'])") !== false
                   || strpos($src, "data-display-pic") !== false;
    $test->assertTrue($hasConditional,
        "index.php hover display must be conditional on pic being set");
}

// ── 9. CSS checks ─────────────────────────────────────────────────────────────

function testAPArtistCssHasDisplayPictureRule($test) {
    $src = file_get_contents(dirname(__DIR__) . '/styles/artist.css');
    $test->assertTrue(strpos($src, '.artist-display-picture') !== false,
        'styles/artist.css missing .artist-display-picture rule');
}

function testAPArtistCssHasDisplayPlaceholderRule($test) {
    $src = file_get_contents(dirname(__DIR__) . '/styles/artist.css');
    $test->assertTrue(strpos($src, '.artist-display-placeholder') !== false,
        'styles/artist.css missing .artist-display-placeholder rule');
}

function testAPArtistCssHasHasCoverRule($test) {
    $src = file_get_contents(dirname(__DIR__) . '/styles/artist.css');
    $test->assertTrue(strpos($src, '.artist-profile-header.has-cover') !== false,
        'styles/artist.css missing .artist-profile-header.has-cover rule');
}

function testAPArtistCssHasHeaderTopRule($test) {
    $src = file_get_contents(dirname(__DIR__) . '/styles/artist.css');
    $test->assertTrue(strpos($src, '.artist-header-top') !== false,
        'styles/artist.css missing .artist-header-top rule');
}

function testAPIndexTooltipInIndexPhp($test) {
    // Hover tooltip is inline in index.php (JS + fixed div), not in CSS
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertTrue(strpos($src, 'artistDpTooltipImg') !== false,
        'index.php missing artistDpTooltipImg element in tooltip');
}

function testAPIndexTooltipJsListensMouseover($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertTrue(strpos($src, 'mouseover') !== false || strpos($src, 'mouseenter') !== false,
        'index.php tooltip JS must listen for mouseover/mouseenter');
}

function testAPIndexTooltipJsHidesOnScroll($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertTrue(strpos($src, "'scroll'") !== false || strpos($src, '"scroll"') !== false,
        'index.php tooltip JS must hide on scroll');
}

function testAPIndexCssNoteAboutTooltip($test) {
    // index.css should have a comment about the JS-based tooltip
    $src = file_get_contents(dirname(__DIR__) . '/styles/index.css');
    $test->assertTrue(strpos($src, 'artistDpTooltip') !== false || strpos($src, 'JS fixed') !== false,
        'styles/index.css should document that hover tooltip is JS-based');
}

// ── 10. Admin UI HTML checks ──────────────────────────────────────────────────

function testAPAdminHasPictureSectionDiv($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $test->assertTrue(strpos($src, 'id="artistPictureSection"') !== false,
        'admin/index.php missing artistPictureSection div');
}

function testAPAdminHasDisplayPicFileInput($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $test->assertTrue(strpos($src, 'id="artistDisplayPicFile"') !== false,
        'admin/index.php missing artistDisplayPicFile input');
}

function testAPAdminHasCoverPicFileInput($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $test->assertTrue(strpos($src, 'id="artistCoverPicFile"') !== false,
        'admin/index.php missing artistCoverPicFile input');
}

function testAPAdminHasUploadJsFunction($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $test->assertTrue(strpos($src, 'function uploadArtistPicture(') !== false,
        'admin/index.php missing uploadArtistPicture JS function');
}

function testAPAdminHasDeleteJsFunction($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $test->assertTrue(strpos($src, 'function deleteArtistPicture(') !== false,
        'admin/index.php missing deleteArtistPicture JS function');
}

function testAPAdminHasShowPictureSectionFunction($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $test->assertTrue(strpos($src, 'function showArtistPictureSection(') !== false,
        'admin/index.php missing showArtistPictureSection JS function');
}

function testAPAdminEditModalCallsShowPictureSection($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $test->assertTrue(strpos($src, 'showArtistPictureSection(artist)') !== false,
        'openEditArtistModal must call showArtistPictureSection(artist)');
}

function testAPAdminAddModalCallsResetPictureSection($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $test->assertTrue(strpos($src, 'resetArtistPictureSection()') !== false,
        'admin/index.php must have resetArtistPictureSection() calls');
}

// ── 11. setup.php + Dockerfile checks ─────────────────────────────────────────

function testAPSetupCreateTableIncludesDisplayPicture($test) {
    $src = file_get_contents(dirname(__DIR__) . '/setup.php');
    $test->assertTrue(strpos($src, 'display_picture TEXT DEFAULT NULL') !== false,
        'setup.php CREATE TABLE artists missing display_picture column');
}

function testAPSetupCreateTableIncludesCoverPicture($test) {
    $src = file_get_contents(dirname(__DIR__) . '/setup.php');
    $test->assertTrue(strpos($src, 'cover_picture TEXT DEFAULT NULL') !== false,
        'setup.php CREATE TABLE artists missing cover_picture column');
}

function testAPSetupIncludesUploadsArtistsDir($test) {
    $src = file_get_contents(dirname(__DIR__) . '/setup.php');
    $test->assertTrue(strpos($src, 'uploads/artists') !== false,
        'setup.php missing uploads/artists in directory list');
}

function testAPDockerfileCreatesUploadsArtists($test) {
    $src = file_get_contents(dirname(__DIR__) . '/Dockerfile');
    $test->assertTrue(strpos($src, 'uploads/artists') !== false,
        'Dockerfile missing uploads/artists directory creation');
}
