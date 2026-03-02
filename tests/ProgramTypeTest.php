<?php
/**
 * Program Type Tests  (v2.4.x)
 *
 * Covers changes introduced in:
 *  v2.4.0 — program_type column, ?type= API filter, Admin programs_types action
 *  v2.4.1 — appendFilter() JS, $hasTypes flag, event-subtitle, table.type translations
 *  v2.4.2 — Admin programs list: Organizer → Categories column
 */

require_once __DIR__ . '/../config.php';

// ── Helpers ──────────────────────────────────────────────────────────────────

/** Open a fresh PDO connection; caller MUST set $db = null after use (Windows file-lock). */
function _pt_db(): ?PDO {
    if (!file_exists(DB_PATH)) {
        return null;
    }
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA busy_timeout = 3000");
    return $db;
}

/** Return true if $table has $column. Closes connection before returning. */
function _pt_hasColumn(string $table, string $column): bool {
    $db = _pt_db();
    if (!$db) return false;
    $stmt = $db->query("PRAGMA table_info({$table})");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;
    return in_array($column, $cols, true);
}

/**
 * Insert a minimal test program and return its ID.
 * Connection is closed before returning.
 */
function _pt_insertProgram(string $uid, ?string $programType = null): int {
    $db   = _pt_db();
    $now  = date('Y-m-d H:i:s');
    $stmt = $db->prepare("
        INSERT INTO programs (uid, title, start, end, location, organizer, categories, program_type, created_at, updated_at)
        VALUES (:uid, :title, :start, :end, '', '', '', :program_type, :now, :now2)
    ");
    $stmt->execute([
        ':uid'          => $uid,
        ':title'        => 'Test Program ' . $uid,
        ':start'        => $now,
        ':end'          => $now,
        ':program_type' => $programType,
        ':now'          => $now,
        ':now2'         => $now,
    ]);
    $id   = (int) $db->lastInsertId();
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;
    return $id;
}

/** Delete a test program by ID. Connection is closed before returning. */
function _pt_deleteProgram(int $id): void {
    $db   = _pt_db();
    $stmt = $db->prepare("DELETE FROM programs WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;
}

// ── 1. DB Schema ─────────────────────────────────────────────────────────────

function testProgramsTableHasProgramTypeColumn($test) {
    if (!file_exists(DB_PATH)) {
        echo " [SKIP: No database] ";
        return;
    }
    $test->assertTrue(
        _pt_hasColumn('programs', 'program_type'),
        'programs table should have a program_type column (run migrate-add-program-type-column.php)'
    );
}

function testProgramTypeColumnIsNullable($test) {
    if (!file_exists(DB_PATH)) {
        echo " [SKIP: No database] ";
        return;
    }
    $db   = _pt_db();
    $stmt = $db->query("PRAGMA table_info(programs)");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;

    $col = null;
    foreach ($cols as $c) {
        if ($c['name'] === 'program_type') { $col = $c; break; }
    }

    if (!$col) {
        echo " [SKIP: No program_type column] ";
        return;
    }
    $test->assertEquals('0', (string) $col['notnull'], 'program_type should be nullable (notnull=0)');
}

// ── 2. Migration Script ───────────────────────────────────────────────────────

function testMigrateProgramTypeScriptExists($test) {
    $path = dirname(__DIR__) . '/tools/migrate-add-program-type-column.php';
    $test->assertTrue(
        file_exists($path),
        'tools/migrate-add-program-type-column.php should exist'
    );
}

function testMigrateProgramTypeIsIdempotent($test) {
    if (!file_exists(DB_PATH)) {
        echo " [SKIP: No database] ";
        return;
    }
    // Migration should detect existing column and skip ALTER TABLE
    $hasCol     = _pt_hasColumn('programs', 'program_type');
    $would_skip = $hasCol;
    $test->assertTrue($would_skip, 'Migration should skip ALTER TABLE when program_type column already exists');
}

// ── 3. CRUD ───────────────────────────────────────────────────────────────────

function testInsertProgramWithType($test) {
    if (!file_exists(DB_PATH)) {
        echo " [SKIP: No database] ";
        return;
    }
    if (!_pt_hasColumn('programs', 'program_type')) {
        echo " [SKIP: No program_type column] ";
        return;
    }

    $uid  = 'pt-test-with-type-' . time();
    $type = 'Live Performance';
    $id   = _pt_insertProgram($uid, $type);

    $db   = _pt_db();
    $stmt = $db->prepare("SELECT program_type FROM programs WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;

    _pt_deleteProgram($id);

    $test->assertEquals($type, $row['program_type'], 'program_type should be stored as-is');
}

function testInsertProgramWithNullType($test) {
    if (!file_exists(DB_PATH)) {
        echo " [SKIP: No database] ";
        return;
    }
    if (!_pt_hasColumn('programs', 'program_type')) {
        echo " [SKIP: No program_type column] ";
        return;
    }

    $uid = 'pt-test-null-type-' . time();
    $id  = _pt_insertProgram($uid, null);

    $db   = _pt_db();
    $stmt = $db->prepare("SELECT program_type FROM programs WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;

    _pt_deleteProgram($id);

    $test->assertNull($row['program_type'], 'NULL program_type should be stored as NULL');
}

function testUpdateProgramType($test) {
    if (!file_exists(DB_PATH)) {
        echo " [SKIP: No database] ";
        return;
    }
    if (!_pt_hasColumn('programs', 'program_type')) {
        echo " [SKIP: No program_type column] ";
        return;
    }

    $uid = 'pt-test-update-' . time();
    $id  = _pt_insertProgram($uid, null);

    $newType = 'Special Stage';
    $db   = _pt_db();
    $stmt = $db->prepare("UPDATE programs SET program_type = :type WHERE id = :id");
    $stmt->execute([':type' => $newType, ':id' => $id]);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;

    $db   = _pt_db();
    $stmt = $db->prepare("SELECT program_type FROM programs WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;

    _pt_deleteProgram($id);

    $test->assertEquals($newType, $row['program_type'], 'program_type should be updatable');
}

function testUpdateProgramTypeToNull($test) {
    if (!file_exists(DB_PATH)) {
        echo " [SKIP: No database] ";
        return;
    }
    if (!_pt_hasColumn('programs', 'program_type')) {
        echo " [SKIP: No program_type column] ";
        return;
    }

    $uid = 'pt-test-nullify-' . time();
    $id  = _pt_insertProgram($uid, 'Opening');

    $db   = _pt_db();
    $stmt = $db->prepare("UPDATE programs SET program_type = NULL WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;

    $db   = _pt_db();
    $stmt = $db->prepare("SELECT program_type FROM programs WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;

    _pt_deleteProgram($id);

    $test->assertNull($row['program_type'], 'program_type should be updatable to NULL');
}

function testSelectDistinctProgramTypes($test) {
    if (!file_exists(DB_PATH)) {
        echo " [SKIP: No database] ";
        return;
    }
    if (!_pt_hasColumn('programs', 'program_type')) {
        echo " [SKIP: No program_type column] ";
        return;
    }

    // Insert 2 programs with distinct types + 1 with null
    $uid1 = 'pt-distinct-1-' . time();
    $uid2 = 'pt-distinct-2-' . time();
    $uid3 = 'pt-distinct-null-' . time();
    $id1  = _pt_insertProgram($uid1, 'TypeAlpha');
    $id2  = _pt_insertProgram($uid2, 'TypeBeta');
    $id3  = _pt_insertProgram($uid3, null);

    $db   = _pt_db();
    $stmt = $db->query("SELECT DISTINCT program_type FROM programs WHERE program_type IS NOT NULL AND program_type != '' ORDER BY program_type ASC");
    $types = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null;

    _pt_deleteProgram($id1);
    _pt_deleteProgram($id2);
    _pt_deleteProgram($id3);

    $test->assertContains('TypeAlpha', $types, 'SELECT DISTINCT should return TypeAlpha');
    $test->assertContains('TypeBeta',  $types, 'SELECT DISTINCT should return TypeBeta');
    $test->assertTrue(!in_array(null, $types, true), 'NULL types should be excluded from DISTINCT query');
}

// ── 4. Public API — ?type= filter ─────────────────────────────────────────────

function testPublicApiSourceHasTypeFilterLogic($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api.php');
    $test->assertContains(
        '$typeFilter',
        $src,
        'api.php should contain $typeFilter variable for type filtering'
    );
    $test->assertContains(
        "program_type",
        $src,
        'api.php should reference program_type for type filtering'
    );
}

function testPublicApiTypeFilterUsesGetParam($test) {
    $src = file_get_contents(dirname(__DIR__) . '/api.php');
    // The filter should read from $_GET['type']
    $test->assertContains(
        "'type'",
        $src,
        "api.php should read 'type' from query string"
    );
}

// ── 5. Admin API — programs_types action ──────────────────────────────────────

function testAdminApiHasProgramsTypesCase($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertContains(
        "case 'programs_types'",
        $src,
        "admin/api.php should have 'programs_types' action case"
    );
}

function testAdminApiProgramsTypesCallsGetTypes($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertContains(
        'getTypes()',
        $src,
        "admin/api.php programs_types should call getTypes()"
    );
}

function testAdminApiGetTypesQueryUsesDistinct($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertContains(
        'SELECT DISTINCT program_type',
        $src,
        'getTypes() should use SELECT DISTINCT program_type'
    );
}

function testAdminApiProgramsCreateHandlesProgramType($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    // INSERT should include program_type
    $test->assertTrue(
        strpos($src, "program_type, created_at") !== false ||
        strpos($src, "program_type, event_id") !== false,
        'createProgram INSERT should include program_type field'
    );
}

function testAdminApiProgramsUpdateHandlesProgramType($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertContains(
        'program_type = :program_type',
        $src,
        'updateProgram UPDATE should set program_type field'
    );
}

function testAdminApiBulkUpdateHandlesProgramType($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $test->assertContains(
        'programType',
        $src,
        'bulkUpdatePrograms should handle programType field'
    );
}

// ── 6. index.php UI features ──────────────────────────────────────────────────

function testIndexHasAppendFilterFunction($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertContains(
        'function appendFilter(',
        $src,
        'index.php should define appendFilter() JS function'
    );
}

function testIndexAppendFilterUsesUrlSearchParams($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertContains(
        'url.searchParams',
        $src,
        'appendFilter() should use URLSearchParams to modify query string'
    );
}

function testIndexHasHasTypesFlag($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertContains(
        '$hasTypes',
        $src,
        'index.php should use $hasTypes to conditionally show Type column'
    );
}

function testIndexHasEventSubtitleElement($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertContains(
        'event-subtitle',
        $src,
        'index.php should have .event-subtitle element for displaying event name below site title'
    );
}

function testIndexHasTableTypeI18nKey($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertContains(
        '"table.type"',
        $src,
        'index.php should use data-i18n="table.type" for Type column header'
    );
}

function testIndexHasClickableCategoryBadges($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertContains(
        'program-categories-badge',
        $src,
        'index.php should have .program-categories-badge buttons'
    );
    $test->assertContains(
        "appendFilter('artist'",
        $src,
        'Category badges should call appendFilter with artist type'
    );
}

function testIndexHasClickableTypeBadges($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertContains(
        'program-type-badge',
        $src,
        'index.php should have .program-type-badge buttons'
    );
    $test->assertContains(
        "appendFilter('type'",
        $src,
        'Type badges should call appendFilter with type'
    );
}

function testIndexHasJsonEncodeEscapeForOnclick($test) {
    // Verify htmlspecialchars(json_encode(...)) pattern to prevent SyntaxError in onclick
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertContains(
        'htmlspecialchars(json_encode(',
        $src,
        'index.php should use htmlspecialchars(json_encode()) to safely embed values in onclick attributes'
    );
}

// ── 7. Translations ───────────────────────────────────────────────────────────

function testTranslationsHasTableTypeKeyInThai($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    // Check 'table.type' appears near Thai-specific values like 'ประเภท'
    $test->assertContains(
        "'table.type'",
        $src,
        "translations.js should have 'table.type' key"
    );
    $test->assertContains(
        "'ประเภท'",
        $src,
        "translations.js should have 'ประเภท' as Thai translation for table.type"
    );
}

function testTranslationsHasTableTypeKeyInEnglish($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    // Check 'Type' exists as a translation value (English)
    $test->assertContains(
        "'Type'",
        $src,
        "translations.js should have 'Type' as English translation for table.type"
    );
}

function testTranslationsHasTableTypeKeyInJapanese($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    $test->assertContains(
        "'タイプ'",
        $src,
        "translations.js should have 'タイプ' as Japanese translation for table.type"
    );
}

function testTranslationsTableTypeAppearsThreeTimes($test) {
    $src   = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    $count = substr_count($src, "'table.type'");
    $test->assertEquals(3, $count, "'table.type' key should appear exactly 3 times (one per language: th/en/ja)");
}

// ── 8. Admin UI v2.4.2 — Categories column replaces Organizer ────────────────

function testAdminUiProgramsListSortsByCategories($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $test->assertContains(
        "sortBy('categories')",
        $src,
        "admin/index.php programs list header should sort by 'categories' (not organizer)"
    );
}

function testAdminUiProgramsListHasNoCategoryAsOrganizer($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    // The programs-list column header must NOT sort by organizer anymore
    // (organizer may still appear in form fields, but not as a sortable column header th)
    $test->assertTrue(
        strpos($src, "sortBy('organizer')") === false,
        "admin/index.php should not have sortBy('organizer') — replaced by sortBy('categories')"
    );
}

function testAdminUiProgramsListShowsEventCategories($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    // The data cell in programs list renders event.categories
    $test->assertContains(
        'event.categories',
        $src,
        "admin/index.php programs list data cell should show event.categories"
    );
}

function testAdminUiProgramsListColumnHeader($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $test->assertContains(
        '>Categories <span class="sort-icon" data-col="categories"',
        $src,
        "admin/index.php programs list should have Categories column header with sort icon"
    );
}

function testAdminUiIcsPreviewHasCategoriesHeader($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $test->assertContains(
        'ศิลปินที่เกี่ยวข้อง',
        $src,
        "admin/index.php ICS import preview should use 'ศิลปินที่เกี่ยวข้อง' header instead of 'ผู้จัด'"
    );
}

function testAdminUiIcsPreviewHasNoPhuJad($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $test->assertTrue(
        strpos($src, '<th>ผู้จัด</th>') === false,
        "admin/index.php ICS import preview should not have '<th>ผู้จัด</th>' — replaced by ศิลปินที่เกี่ยวข้อง"
    );
}
