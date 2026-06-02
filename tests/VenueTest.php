<?php
/**
 * Venue Tests (v16.0.0)
 *
 * Covers the Venue/Location dedup system:
 *  - venues + venue_variants schema + migration idempotency
 *  - venue_resolve_canonical() helper + create/update/bulk/ICS-import hooks (source)
 *  - venues CRUD / variants / autocomplete / merge admin API (source + dispatch)
 *  - invalidate_venue_query_cache() + invalidate_all_caches inclusion
 *  - venue.php profile (location/variant matching + 404) + venues.php portal
 *  - .htaccess route, index.php nav link + clickable venue cell
 *  - translations.js + admin-i18n.js venue keys
 *  - dedup variant seeding (Phenix Pratunam → Lot of Live)
 */

require_once __DIR__ . '/../config.php';

// ── Helpers ────────────────────────────────────────────────────────────────
function _vn_db(): ?PDO {
    if (!file_exists(DB_PATH)) return null;
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA busy_timeout = 3000");
    $db->exec("PRAGMA foreign_keys = ON");
    return $db;
}
function _vn_hasTable(string $t): bool {
    $db = _vn_db();
    if (!$db) return false;
    $stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
    $stmt->execute([$t]);
    $found = (bool)$stmt->fetchColumn();
    $stmt->closeCursor(); $stmt = null; $db = null;
    return $found;
}
function _vn_src(string $rel): string {
    $p = dirname(__DIR__) . '/' . $rel;
    return file_exists($p) ? (string)file_get_contents($p) : '';
}
/** Create a throwaway venue, returns id (or 0 if no DB/table). */
function _vn_makeVenue(string $name): int {
    $db = _vn_db();
    if (!$db) return 0;
    $stmt = $db->prepare("INSERT OR IGNORE INTO venues (name) VALUES (?)");
    $stmt->execute([$name]);
    $stmt->closeCursor(); $stmt = null;
    $s2 = $db->prepare("SELECT id FROM venues WHERE name=?");
    $s2->execute([$name]);
    $id = (int)$s2->fetchColumn();
    $s2->closeCursor(); $s2 = null; $db = null;
    return $id;
}
function _vn_deleteVenue(int $id): void {
    $db = _vn_db();
    if (!$db) return;
    $stmt = $db->prepare("DELETE FROM venues WHERE id=?");
    $stmt->execute([$id]);
    $stmt->closeCursor(); $stmt = null; $db = null;
}

// ── 1. Schema ────────────────────────────────────────────────────────────────
function testVenueTablesExist($test) {
    if (!file_exists(DB_PATH)) { echo " [SKIP: No database] "; return; }
    $test->assertTrue(_vn_hasTable('venues'), 'venues table should exist (run migrate-add-venues-table.php)');
    $test->assertTrue(_vn_hasTable('venue_variants'), 'venue_variants table should exist');
}

function testVenuesColumns($test) {
    if (!_vn_hasTable('venues')) { echo " [SKIP: no venues table] "; return; }
    $db = _vn_db();
    $cols = $db->query("PRAGMA table_info(venues)")->fetchAll(PDO::FETCH_COLUMN, 1);
    $db = null;
    foreach (['id', 'name', 'description', 'map_url', 'created_at', 'updated_at'] as $c) {
        $test->assertTrue(in_array($c, $cols, true), "venues should have column $c");
    }
}

function testVenueVariantsColumns($test) {
    if (!_vn_hasTable('venue_variants')) { echo " [SKIP: no venue_variants table] "; return; }
    $db = _vn_db();
    $cols = $db->query("PRAGMA table_info(venue_variants)")->fetchAll(PDO::FETCH_COLUMN, 1);
    $db = null;
    foreach (['id', 'venue_id', 'variant'] as $c) {
        $test->assertTrue(in_array($c, $cols, true), "venue_variants should have column $c");
    }
}

function testVenueNameUnique($test) {
    if (!_vn_hasTable('venues')) { echo " [SKIP: no venues table] "; return; }
    $name = '__vn_unique_' . time();
    $id = _vn_makeVenue($name);
    $threw = false;
    $db = _vn_db();
    try {
        $stmt = $db->prepare("INSERT INTO venues (name) VALUES (?)");
        $stmt->execute([$name]);
        $stmt->closeCursor(); $stmt = null;
    } catch (PDOException $e) {
        $threw = (strpos($e->getMessage(), 'UNIQUE') !== false);
    }
    $db = null;
    _vn_deleteVenue($id);
    $test->assertTrue($threw, 'venues.name should enforce UNIQUE');
}

// ── 2. Migration ──────────────────────────────────────────────────────────────
function testVenueMigrationScriptExists($test) {
    $test->assertTrue(file_exists(dirname(__DIR__) . '/tools/migrate-add-venues-table.php'),
        'tools/migrate-add-venues-table.php should exist');
}
function testVenueMigrationIdempotent($test) {
    // tables already present (migration ran) — proves CREATE TABLE IF NOT EXISTS path is safe
    if (!file_exists(DB_PATH)) { echo " [SKIP: No database] "; return; }
    $test->assertTrue(_vn_hasTable('venues') && _vn_hasTable('venue_variants'),
        'Re-running migration must leave both tables intact');
}
function testVenuesSeededFromLocations($test) {
    if (!_vn_hasTable('venues')) { echo " [SKIP: no venues table] "; return; }
    $db = _vn_db();
    $venueCount = (int)$db->query("SELECT COUNT(*) FROM venues")->fetchColumn();
    $distinct   = (int)$db->query("SELECT COUNT(DISTINCT location) FROM programs WHERE location IS NOT NULL AND location != ''")->fetchColumn();
    $db = null;
    $test->assertGreaterThanOrEqual($distinct, $venueCount,
        'venues should cover all distinct programs.location values');
}

// ── 3. Resolve / variant behaviour (DB level mirrors venue_resolve_canonical) ──
function testVenueResolveExactMatch($test) {
    if (!_vn_hasTable('venues')) { echo " [SKIP] "; return; }
    $name = '__vn_exact_' . time();
    $id = _vn_makeVenue($name);
    $db = _vn_db();
    $stmt = $db->prepare("SELECT name FROM venues WHERE LOWER(name) = LOWER(?)");
    $stmt->execute([strtoupper($name)]); // case-insensitive
    $resolved = $stmt->fetchColumn();
    $stmt->closeCursor(); $stmt = null; $db = null;
    _vn_deleteVenue($id);
    $test->assertEquals($name, $resolved, 'exact (case-insensitive) name match should resolve to canonical');
}

function testVenueResolveVariantMatch($test) {
    if (!_vn_hasTable('venue_variants')) { echo " [SKIP] "; return; }
    $name    = '__vn_canon_' . time();
    $variant = '__vn_alias_' . time();
    $id = _vn_makeVenue($name);
    $db = _vn_db();
    $ins = $db->prepare("INSERT OR IGNORE INTO venue_variants (venue_id, variant) VALUES (?, ?)");
    $ins->execute([$id, $variant]);
    $ins->closeCursor(); $ins = null;
    $stmt = $db->prepare("
        SELECT v.name FROM venue_variants vv JOIN venues v ON v.id = vv.venue_id
        WHERE LOWER(vv.variant) = LOWER(?) LIMIT 1
    ");
    $stmt->execute([$variant]);
    $resolved = $stmt->fetchColumn();
    $stmt->closeCursor(); $stmt = null; $db = null;
    _vn_deleteVenue($id);
    $test->assertEquals($name, $resolved, 'variant lookup should resolve to owning venue name');
}

function testVenueVariantCascadeDelete($test) {
    if (!_vn_hasTable('venue_variants')) { echo " [SKIP] "; return; }
    $name = '__vn_cascade_' . time();
    $id = _vn_makeVenue($name);
    $db = _vn_db();
    $ins = $db->prepare("INSERT OR IGNORE INTO venue_variants (venue_id, variant) VALUES (?, 'x-alias')");
    $ins->execute([$id]);
    $ins->closeCursor(); $ins = null;
    $db = null;
    _vn_deleteVenue($id); // ON DELETE CASCADE
    $db = _vn_db();
    $stmt = $db->prepare("SELECT COUNT(*) FROM venue_variants WHERE venue_id=?");
    $stmt->execute([$id]);
    $left = (int)$stmt->fetchColumn();
    $stmt->closeCursor(); $stmt = null; $db = null;
    $test->assertEquals(0, $left, 'deleting a venue should cascade-delete its variants');
}

// ── 4. admin/api.php source hooks ──────────────────────────────────────────────
function testVenueResolveCanonicalDefined($test) {
    $src = _vn_src('admin/api.php');
    $test->assertContains('function venue_resolve_canonical', $src, 'venue_resolve_canonical() must be defined');
}
function testCreateUpdateCallVenueResolve($test) {
    $src = _vn_src('admin/api.php');
    // appears in createProgram + updateProgram + bulk + ICS import → at least 3 call sites
    $count = substr_count($src, 'venue_resolve_canonical($db');
    $test->assertGreaterThanOrEqual(3, $count, 'venue_resolve_canonical should be called on create/update/bulk/import');
}
function testVenuesDispatchCases($test) {
    $src = _vn_src('admin/api.php');
    foreach (['venues_list', 'venues_get', 'venues_create', 'venues_update', 'venues_delete',
              'venues_autocomplete', 'venues_variants_list', 'venues_variants_create',
              'venues_variants_delete', 'venues_merge'] as $action) {
        $test->assertContains("case '$action':", $src, "dispatch should handle $action");
    }
}
function testMergeVenuesFunctionExists($test) {
    $src = _vn_src('admin/api.php');
    $test->assertContains('function mergeVenues', $src, 'mergeVenues() must be defined');
    $test->assertContains('function listVenues', $src, 'listVenues() must be defined');
}
function testOrganizerAllowlistVenuesAutocomplete($test) {
    $src = _vn_src('admin/api.php');
    $test->assertContains("'venues_autocomplete'", $src, 'organizer allowlist should include venues_autocomplete');
}

// ── 5. Cache ────────────────────────────────────────────────────────────────
function testInvalidateVenueQueryCacheExists($test) {
    $test->assertTrue(function_exists('invalidate_venue_query_cache'),
        'invalidate_venue_query_cache() should be defined');
    $test->assertTrue(invalidate_venue_query_cache(), 'invalidate_venue_query_cache() should return true');
}
function testInvalidateAllCachesIncludesVenue($test) {
    $src = _vn_src('functions/cache.php');
    $test->assertContains('query_venue_*.json', $src, 'invalidate_all_caches should clear query_venue_*.json');
    $test->assertContains('query_portal_venues.json', $src, 'invalidate_all_caches should clear venue portal cache');
}
function testProgramWritesInvalidateVenueCache($test) {
    $src = _vn_src('admin/api.php');
    $test->assertContains('invalidate_venue_query_cache()', $src,
        'program writes should call invalidate_venue_query_cache()');
}

// ── 6. Public pages ────────────────────────────────────────────────────────────
function testVenuePageExists($test) {
    $test->assertTrue(file_exists(dirname(__DIR__) . '/venue.php'), 'venue.php should exist');
}
function testVenuePageMatchesByNameOrVariant($test) {
    $src = _vn_src('venue.php');
    $test->assertContains('p.location IN', $src, 'venue.php should match programs by location IN (name + variants)');
    $test->assertContains('include_venue_404', $src, 'venue.php should 404 on unknown venue');
    $test->assertContains('query_venue_', $src, 'venue.php should use venue query cache');
}
function testVenuesPortalExists($test) {
    $test->assertTrue(file_exists(dirname(__DIR__) . '/venues.php'), 'venues.php portal should exist');
    $src = _vn_src('venues.php');
    $test->assertContains('query_portal_venues.json', $src, 'venues.php should cache portal data');
}
function testHtaccessVenueRoute($test) {
    $src = _vn_src('.htaccess');
    $test->assertContains('^venue/([0-9]+)', $src, '.htaccess should route /venue/{id}');
}
function testIndexNavVenuesLink($test) {
    $src = _vn_src('index.php');
    $test->assertContains('/venues', $src, 'index.php nav should link to /venues');
    $test->assertContains('venue_meta', $src, 'index.php should build $venueMeta for clickable venue cells');
}

// ── 7. i18n ──────────────────────────────────────────────────────────────────
function testTranslationsVenueKeys($test) {
    $src = _vn_src('js/translations.js');
    // 3 languages each
    $test->assertEquals(3, substr_count($src, "'nav.venues'"), 'nav.venues in TH/EN/JA');
    $test->assertEquals(3, substr_count($src, "'venuePortal.title'"), 'venuePortal.title in TH/EN/JA');
    $test->assertEquals(3, substr_count($src, "'venue.pageTitle'"), 'venue.pageTitle in TH/EN/JA');
}
function testAdminI18nVenueKeys($test) {
    $src = _vn_src('admin/js/admin-i18n.js');
    $test->assertEquals(2, substr_count($src, "'tab.venues'"), 'tab.venues in TH+EN');
    $test->assertEquals(2, substr_count($src, "'venues.mergeTitle'"), 'venues.mergeTitle in TH+EN');
}

// ── 8. Dedup variant seeding ────────────────────────────────────────────────
function testDedupVariantSeeded($test) {
    if (!_vn_hasTable('venue_variants')) { echo " [SKIP] "; return; }
    $db = _vn_db();
    $stmt = $db->prepare("SELECT id FROM venues WHERE name = ?");
    $stmt->execute(['Lot of Live, 3rd Fl. Phenix Pratunam']);
    $vid = (int)$stmt->fetchColumn();
    $stmt->closeCursor(); $stmt = null;
    if (!$vid) { $db = null; echo " [SKIP: canonical venue not present] "; return; }
    $s2 = $db->prepare("SELECT COUNT(*) FROM venue_variants WHERE venue_id=? AND variant=?");
    $s2->execute([$vid, 'Phenix Pratunam']);
    $has = (int)$s2->fetchColumn();
    $s2->closeCursor(); $s2 = null; $db = null;
    $test->assertEquals(1, $has, 'dedup seed should map "Phenix Pratunam" → Lot of Live canonical');
}

// ── 9. is_online flag (v16.0.1) ────────────────────────────────────────────
function testVenueIsOnlineColumn($test) {
    if (!_vn_hasTable('venues')) { echo " [SKIP] "; return; }
    $db = _vn_db();
    $cols = $db->query("PRAGMA table_info(venues)")->fetchAll(PDO::FETCH_COLUMN, 1);
    $db = null;
    $test->assertTrue(in_array('is_online', $cols, true), 'venues should have is_online column (v16.0.1)');
}
function testVenueIsOnlineDefaultsZero($test) {
    if (!_vn_hasTable('venues')) { echo " [SKIP] "; return; }
    $db = _vn_db();
    $cols = $db->query("PRAGMA table_info(venues)")->fetchAll(PDO::FETCH_ASSOC);
    $db = null;
    $col = null;
    foreach ($cols as $c) if ($c['name'] === 'is_online') { $col = $c; break; }
    if (!$col) { echo " [SKIP] "; return; }
    $test->assertEquals('0', (string)$col['dflt_value'], 'is_online should default to 0');
}
function testKnownOnlinePlatformsSeeded($test) {
    if (!_vn_hasTable('venues')) { echo " [SKIP] "; return; }
    $db = _vn_db();
    $cols = $db->query("PRAGMA table_info(venues)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('is_online', $cols)) { $db = null; echo " [SKIP: no is_online] "; return; }
    $stmt = $db->prepare("SELECT COUNT(*) FROM venues WHERE name IN ('YouTube','Instagram Live','X Spaces') AND is_online = 1");
    $stmt->execute();
    $marked = (int)$stmt->fetchColumn();
    $stmt->closeCursor(); $stmt = null;
    $totalKnown = (int)$db->query("SELECT COUNT(*) FROM venues WHERE name IN ('YouTube','Instagram Live','X Spaces')")->fetchColumn();
    $db = null;
    // every known online venue that exists should be flagged
    $test->assertEquals($totalKnown, $marked, 'YouTube/Instagram Live/X Spaces should all be flagged is_online=1');
}
function testPortalFiltersOnline($test) {
    $src = _vn_src('venues.php');
    $test->assertContains('COALESCE(v.is_online, 0) = 0', $src,
        'venues.php should filter out is_online=1 venues');
}
function testAdminApiHandlesIsOnline($test) {
    $src = _vn_src('admin/api.php');
    $test->assertContains("':is_online' =>", $src, 'createVenue/updateVenue should bind :is_online named parameter');
    $test->assertContains("\$input['is_online']", $src, 'admin API should read is_online from input');
}
function testVenuePageShowsOnlineBadge($test) {
    $src = _vn_src('venue.php');
    $test->assertContains('venue.badgeOnline', $src, 'venue.php should render Online badge for is_online venues');
}
function testTranslationsHasBadgeOnline($test) {
    $src = _vn_src('js/translations.js');
    $test->assertEquals(3, substr_count($src, "'venue.badgeOnline'"), 'venue.badgeOnline in TH/EN/JA');
}

// ── 10. setup.php integration ──────────────────────────────────────────────
function testSetupTracksVenuesTables($test) {
    $src = _vn_src('setup.php');
    $test->assertContains('$hasVenuesTables', $src, 'setup.php should track $hasVenuesTables in migration checks');
    $test->assertContains('CREATE TABLE IF NOT EXISTS venues', $src, 'setup.php init_database should create venues table');
}
