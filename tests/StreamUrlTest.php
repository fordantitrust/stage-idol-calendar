<?php
/**
 * Stream URL Tests  (v2.6.x)
 *
 * Covers changes introduced when live stream support was added:
 *  - stream_url TEXT DEFAULT NULL column in programs table
 *  - IcsParser parses URL: property → stream_url
 *  - Admin CRUD (createProgram / updateProgram / getProgram) includes stream_url
 *  - Public api.php includes stream_url in response
 *  - export.php / feed.php emit URL: in VEVENT when stream_url is set
 *  - index.php public UI shows join button & platform icon
 *  - admin/index.php form has streamUrl field
 *  - Migration script exists and is idempotent
 */

require_once __DIR__ . '/../config.php';

// ── Helpers ──────────────────────────────────────────────────────────────────

/** Open a fresh PDO connection; caller MUST set $db = null after use (Windows file-lock). */
function _su_db(): ?PDO {
    if (!file_exists(DB_PATH)) {
        return null;
    }
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA busy_timeout = 3000");
    return $db;
}

/** Return true if programs table has $column. */
function _su_hasColumn(string $column): bool {
    $db = _su_db();
    if (!$db) return false;
    $stmt = $db->query("PRAGMA table_info(programs)");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;
    return in_array($column, $cols, true);
}

/**
 * Insert a minimal test program with optional stream_url. Returns ID.
 * Connection is closed before returning.
 */
function _su_insertProgram(string $uid, ?string $streamUrl = null): int {
    $db  = _su_db();
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("
        INSERT INTO programs (uid, title, start, end, location, organizer, categories, program_type, stream_url, created_at, updated_at)
        VALUES (:uid, :title, :start, :end, '', '', '', NULL, :stream_url, :now, :now2)
    ");
    $stmt->execute([
        ':uid'        => $uid,
        ':title'      => 'Test StreamUrl ' . $uid,
        ':start'      => $now,
        ':end'        => $now,
        ':stream_url' => $streamUrl,
        ':now'        => $now,
        ':now2'       => $now,
    ]);
    $id = (int) $db->lastInsertId();
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;
    return $id;
}

/** Delete a test program by ID. */
function _su_deleteProgram(int $id): void {
    $db   = _su_db();
    $stmt = $db->prepare("DELETE FROM programs WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;
}

// ── 1. DB Schema ──────────────────────────────────────────────────────────────

function testStreamUrlColumnExists($test) {
    if (!file_exists(DB_PATH)) { echo " [SKIP: No database] "; return; }
    $test->assertTrue(
        _su_hasColumn('stream_url'),
        'programs table should have stream_url column (run migrate-add-stream-url-column.php)'
    );
}

function testStreamUrlColumnIsNullable($test) {
    if (!file_exists(DB_PATH)) { echo " [SKIP: No database] "; return; }
    $db   = _su_db();
    $stmt = $db->query("PRAGMA table_info(programs)");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;

    $col = null;
    foreach ($cols as $c) {
        if ($c['name'] === 'stream_url') { $col = $c; break; }
    }
    if (!$col) { echo " [SKIP: No stream_url column] "; return; }
    $test->assertEquals('0', (string) $col['notnull'], 'stream_url should be nullable (notnull=0)');
}

// ── 2. Migration Script ───────────────────────────────────────────────────────

function testMigrateStreamUrlScriptExists($test) {
    $path = dirname(__DIR__) . '/tools/migrate-add-stream-url-column.php';
    $test->assertTrue(file_exists($path), 'tools/migrate-add-stream-url-column.php should exist');
}

function testMigrateStreamUrlIsIdempotent($test) {
    if (!file_exists(DB_PATH)) { echo " [SKIP: No database] "; return; }
    $hasCol = _su_hasColumn('stream_url');
    $test->assertTrue($hasCol, 'Migration should skip ALTER TABLE when stream_url column already exists');
}

// ── 3. CRUD ───────────────────────────────────────────────────────────────────

function testInsertProgramWithStreamUrl($test) {
    if (!file_exists(DB_PATH)) { echo " [SKIP: No database] "; return; }
    if (!_su_hasColumn('stream_url')) { echo " [SKIP: No stream_url column] "; return; }

    $uid = 'su-with-url-' . time();
    $url = 'https://www.instagram.com/test_live';
    $id  = _su_insertProgram($uid, $url);

    $db   = _su_db();
    $stmt = $db->prepare("SELECT stream_url FROM programs WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;

    _su_deleteProgram($id);
    $test->assertEquals($url, $row['stream_url'] ?? null, 'stream_url should be stored and retrievable');
}

function testInsertProgramWithNullStreamUrl($test) {
    if (!file_exists(DB_PATH)) { echo " [SKIP: No database] "; return; }
    if (!_su_hasColumn('stream_url')) { echo " [SKIP: No stream_url column] "; return; }

    $uid = 'su-no-url-' . time();
    $id  = _su_insertProgram($uid, null);

    $db   = _su_db();
    $stmt = $db->prepare("SELECT stream_url FROM programs WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;

    _su_deleteProgram($id);
    $test->assertNull($row['stream_url'], 'stream_url should be NULL when not provided');
}

function testUpdateProgramStreamUrl($test) {
    if (!file_exists(DB_PATH)) { echo " [SKIP: No database] "; return; }
    if (!_su_hasColumn('stream_url')) { echo " [SKIP: No stream_url column] "; return; }

    $uid = 'su-update-url-' . time();
    $id  = _su_insertProgram($uid, null);

    $newUrl = 'https://x.com/i/spaces/abc123';
    $db     = _su_db();
    $stmt   = $db->prepare("UPDATE programs SET stream_url = :url WHERE id = :id");
    $stmt->execute([':url' => $newUrl, ':id' => $id]);
    $stmt->closeCursor();
    $stmt = null;

    $stmt = $db->prepare("SELECT stream_url FROM programs WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;

    _su_deleteProgram($id);
    $test->assertEquals($newUrl, $row['stream_url'], 'stream_url should be updatable');
}

// ── 4. IcsParser — SELECT query ───────────────────────────────────────────────

function testIcsParserSelectIncludesStreamUrl($test) {
    $src = file_get_contents(dirname(__DIR__) . '/IcsParser.php');
    $test->assertContains(
        'stream_url',
        $src,
        'IcsParser.php SELECT should include stream_url column'
    );
}

function testIcsParserParsesUrlProperty($test) {
    $src = file_get_contents(dirname(__DIR__) . '/IcsParser.php');
    $test->assertContains(
        "URL:",
        $src,
        'IcsParser.php should parse URL: ICS property into stream_url'
    );
    $test->assertContains(
        "stream_url",
        $src,
        'IcsParser.php should map parsed URL to stream_url field'
    );
}

// ── 5. Admin API ──────────────────────────────────────────────────────────────

function testAdminApiCreateProgramIncludesStreamUrl($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertContains(
        'stream_url',
        $src,
        'admin/api.php createProgram should include stream_url in INSERT'
    );
}

function testAdminApiUpdateProgramIncludesStreamUrl($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertContains(
        'stream_url = :stream_url',
        $src,
        'admin/api.php updateProgram should SET stream_url in UPDATE'
    );
}

function testAdminApiListProgramsSelectIncludesStreamUrl($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    // listPrograms SELECT should include stream_url
    $test->assertContains(
        'stream_url, created_at',
        $src,
        'admin/api.php listPrograms SELECT should include stream_url'
    );
}

function testAdminApiConfirmIcsImportIncludesStreamUrl($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertContains(
        ':stream_url',
        $src,
        'admin/api.php confirmIcsImport params should include :stream_url'
    );
}

// ── 6. Public API ─────────────────────────────────────────────────────────────

function testPublicApiFieldsToEscapeIncludesStreamUrl($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api.php');
    $test->assertContains(
        "'stream_url'",
        $src,
        'api.php $fieldsToEscape should include stream_url'
    );
}

// ── 7. export.php / feed.php ──────────────────────────────────────────────────

function testExportPhpEmitsUrlProperty($test) {
    $src = file_get_contents(dirname(__DIR__) . '/export.php');
    $test->assertContains(
        '"URL:"',
        $src,
        'export.php should emit URL: property in VEVENT when stream_url is set'
    );
    $test->assertContains(
        "stream_url",
        $src,
        'export.php should reference stream_url when building URL: line'
    );
}

function testFeedPhpEmitsUrlProperty($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains(
        '"URL:"',
        $src,
        'feed.php should emit URL: property in VEVENT when stream_url is set'
    );
    $test->assertContains(
        'stream_url',
        $src,
        'feed.php should reference stream_url when building URL: line'
    );
}

function testExportPhpUrlPropertyIsConditional($test) {
    $src = file_get_contents(dirname(__DIR__) . '/export.php');
    // Should only emit URL: when stream_url is not empty
    $test->assertContains(
        "!empty(\$event['stream_url'])",
        $src,
        'export.php should check !empty($event[stream_url]) before emitting URL: line'
    );
}

function testFeedPhpUrlPropertyIsConditional($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains(
        "!empty(\$event['stream_url'])",
        $src,
        'feed.php should check !empty($event[stream_url]) before emitting URL: line'
    );
}

// ── 8. Public UI (index.php) ──────────────────────────────────────────────────

function testIndexPhpHasLiveRowClass($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertContains(
        'program-live',
        $src,
        'index.php should apply program-live class to rows with stream_url'
    );
}

function testIndexPhpHasJoinButton($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertContains(
        'program-join-btn',
        $src,
        'index.php should render .program-join-btn link for live stream rows'
    );
}

function testIndexPhpHasPlatformIconLogic($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertContains(
        'instagram.com',
        $src,
        'index.php should detect instagram.com URL for 📷 platform icon'
    );
    $test->assertContains(
        'x.com',
        $src,
        'index.php should detect x.com URL for 𝕏 platform icon'
    );
}

// ── 9. Admin UI (admin/index.php) ─────────────────────────────────────────────

function testAdminIndexHasStreamUrlFormField($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $test->assertContains(
        'id="streamUrl"',
        $src,
        'admin/index.php program form should have streamUrl input field'
    );
}

function testAdminIndexSaveEventIncludesStreamUrl($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $test->assertContains(
        'stream_url',
        $src,
        'admin/index.php saveEvent() should include stream_url in data payload'
    );
}

function testAdminIndexListRowShowsStreamIcon($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $test->assertContains(
        'stream-link-badge',
        $src,
        'admin/index.php programs list row should show stream-link-badge for live programs'
    );
}

// ── 10. CSS ───────────────────────────────────────────────────────────────────

function testIndexCssHasProgramLiveStyle($test) {
    $src = file_get_contents(dirname(__DIR__) . '/styles/index.css');
    $test->assertContains(
        'program-live',
        $src,
        'styles/index.css should have .program-live style for live stream row highlight'
    );
}

function testIndexCssHasProgramJoinBtnStyle($test) {
    $src = file_get_contents(dirname(__DIR__) . '/styles/index.css');
    $test->assertContains(
        'program-join-btn',
        $src,
        'styles/index.css should have .program-join-btn style'
    );
}

// ── 11. Translations ──────────────────────────────────────────────────────────

function testTranslationsHasBadgeJoinLiveTh($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    $test->assertContains(
        "'badge.joinLive'",
        $src,
        "js/translations.js should have 'badge.joinLive' translation key"
    );
    $test->assertContains(
        'เข้าร่วม',
        $src,
        "js/translations.js badge.joinLive (TH) should contain 'เข้าร่วม'"
    );
}

function testTranslationsHasBadgeJoinLiveEn($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    $test->assertContains(
        'Join Live',
        $src,
        "js/translations.js should have English 'Join Live' translation"
    );
}

function testTranslationsHasBadgeJoinLiveJa($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    $test->assertContains(
        '参加する',
        $src,
        "js/translations.js should have Japanese '参加する' translation"
    );
}

// ── 12. setup.php ─────────────────────────────────────────────────────────────

function testSetupPhpCreateTableIncludesStreamUrl($test) {
    $src = file_get_contents(dirname(__DIR__) . '/setup.php');
    $test->assertContains(
        'stream_url TEXT DEFAULT NULL',
        $src,
        'setup.php CREATE TABLE programs should include stream_url TEXT DEFAULT NULL'
    );
}

function testSetupPhpAllTablesOkIncludesStreamUrl($test) {
    $src = file_get_contents(dirname(__DIR__) . '/setup.php');
    $test->assertContains(
        '$hasStreamUrlColumn',
        $src,
        'setup.php $allTablesOk should check $hasStreamUrlColumn'
    );
}
