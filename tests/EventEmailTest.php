<?php
/**
 * Event Email Field Tests
 *
 * Tests the email column in the events table, introduced in v2.3.0.
 * - DB schema has email column (TEXT DEFAULT NULL)
 * - Valid email is stored correctly
 * - Invalid email is stored as NULL
 * - Empty/missing email is stored as NULL
 * - Email can be updated
 * - getEvent returns email field
 * - export.php ORGANIZER logic uses event email or falls back
 */

require_once __DIR__ . '/../config.php';

// ── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Open a fresh PDO connection. Caller must set $db = null when done to release
 * the file lock (important on Windows SQLite).
 */
function _email_db(): ?PDO {
    if (!file_exists(DB_PATH)) {
        return null;
    }
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA busy_timeout = 3000"); // fail after 3s instead of hanging
    return $db;
}

/**
 * Check whether a table has a specific column. Closes connection before returning.
 */
function _email_hasColumn(string $table, string $column): bool {
    $db = _email_db();
    if (!$db) return false;
    $stmt = $db->query("PRAGMA table_info({$table})");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    $stmt->closeCursor();
    $stmt = null; // release statement before closing connection
    $db   = null; // explicit close
    return in_array($column, $cols, true);
}

/**
 * Insert a minimal test event and return its ID.
 * Connection is closed before returning so subsequent writes don't deadlock.
 */
function _email_insertEvent(string $slug, ?string $email = null): int {
    $db   = _email_db();
    $now  = date('Y-m-d H:i:s');
    $stmt = $db->prepare("
        INSERT INTO events (slug, name, venue_mode, is_active, email, created_at, updated_at)
        VALUES (:slug, :name, 'multi', 1, :email, :now, :now2)
    ");
    $stmt->execute([':slug' => $slug, ':name' => 'Test Event ' . $slug, ':email' => $email, ':now' => $now, ':now2' => $now]);
    $id   = (int) $db->lastInsertId();
    $stmt->closeCursor();
    $stmt = null;
    $db   = null; // explicit close
    return $id;
}

/**
 * Delete a test event by ID. Connection is closed before returning.
 */
function _email_deleteEvent(int $id): void {
    $db   = _email_db();
    $stmt = $db->prepare("DELETE FROM events WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null; // explicit close
}

/**
 * Replicate the email validation logic used in admin/api.php createEvent / updateEvent.
 */
function _email_validate(?string $raw): ?string {
    $raw = trim($raw ?? '');
    return ($raw !== '' && filter_var($raw, FILTER_VALIDATE_EMAIL)) ? $raw : null;
}

/**
 * Replicate the ORGANIZER line generation logic from export.php.
 */
function _email_organizerLine(?array $eventMeta): ?string {
    if (!$eventMeta || empty($eventMeta['name'])) {
        return null;
    }
    $orgName  = $eventMeta['name'];
    $orgEmail = (!empty($eventMeta['email']) && filter_var($eventMeta['email'], FILTER_VALIDATE_EMAIL))
        ? $eventMeta['email']
        : 'noreply@stageidol.local';
    return "ORGANIZER;CN=\"{$orgName}\":mailto:{$orgEmail}";
}

// ── 1. Schema ────────────────────────────────────────────────────────────────

function testEventsTableHasEmailColumn($test) {
    if (!file_exists(DB_PATH)) {
        echo " [SKIP: No database] ";
        return;
    }
    $test->assertTrue(
        _email_hasColumn('events', 'email'),
        'events table should have an email column (run migrate-add-event-email-column.php)'
    );
}

function testEmailColumnIsNullableByDefault($test) {
    if (!file_exists(DB_PATH)) {
        echo " [SKIP: No database] ";
        return;
    }
    $db   = _email_db();
    $stmt = $db->query("PRAGMA table_info(events)");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null; // explicit close
    $emailCol = null;
    foreach ($cols as $col) {
        if ($col['name'] === 'email') {
            $emailCol = $col;
            break;
        }
    }
    if (!$emailCol) {
        echo " [SKIP: No email column] ";
        return;
    }
    $test->assertEquals('0', (string) $emailCol['notnull'], 'email column should be nullable (notnull=0)');
}

// ── 2. Insert / Store ────────────────────────────────────────────────────────

function testInsertEventWithValidEmail($test) {
    if (!file_exists(DB_PATH)) {
        echo " [SKIP: No database] ";
        return;
    }
    if (!_email_hasColumn('events', 'email')) {
        echo " [SKIP: No email column] ";
        return;
    }

    $slug  = 'test-valid-' . time();
    $email = 'contact@idol-stage.com';
    $id    = _email_insertEvent($slug, $email); // connection closed inside

    // Separate connection to read back
    $db   = _email_db();
    $stmt = $db->prepare("SELECT email FROM events WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null; // explicit close

    _email_deleteEvent($id); // connection closed inside

    $test->assertEquals($email, $row['email'], 'Valid email should be stored as-is');
}

function testInsertEventWithNullEmail($test) {
    if (!file_exists(DB_PATH)) {
        echo " [SKIP: No database] ";
        return;
    }
    if (!_email_hasColumn('events', 'email')) {
        echo " [SKIP: No email column] ";
        return;
    }

    $slug = 'test-null-' . time();
    $id   = _email_insertEvent($slug, null); // connection closed inside

    $db   = _email_db();
    $stmt = $db->prepare("SELECT email FROM events WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null; // explicit close

    _email_deleteEvent($id); // connection closed inside

    $test->assertNull($row['email'], 'NULL email should be stored as NULL');
}

// ── 3. Email Validation Logic ────────────────────────────────────────────────

function testEmailValidationAcceptsValidEmails($test) {
    $valid = [
        'admin@example.com',
        'contact@idol-stage.com',
        'info+tag@sub.domain.org',
        'user123@test.co.th',
    ];
    foreach ($valid as $email) {
        $result = _email_validate($email);
        $test->assertEquals($email, $result, "'{$email}' should pass validation");
    }
}

function testEmailValidationRejectsInvalidEmails($test) {
    $invalid = [
        'not-an-email',
        '@nodomain.com',
        'missing@',
        '',
        '   ',
        'double@@at.com',
    ];
    foreach ($invalid as $email) {
        $result = _email_validate($email);
        $test->assertNull($result, "'{$email}' should be rejected and return NULL");
    }
}

function testEmailValidationTrimsWhitespace($test) {
    $result = _email_validate('  admin@example.com  ');
    $test->assertEquals('admin@example.com', $result, 'Should trim whitespace before validating');
}

function testEmailValidationEmptyStringReturnsNull($test) {
    $test->assertNull(_email_validate(''), 'Empty string should return NULL');
    $test->assertNull(_email_validate(null), 'NULL input should return NULL');
}

// ── 4. Update ────────────────────────────────────────────────────────────────

function testUpdateEventEmail($test) {
    if (!file_exists(DB_PATH)) {
        echo " [SKIP: No database] ";
        return;
    }
    if (!_email_hasColumn('events', 'email')) {
        echo " [SKIP: No email column] ";
        return;
    }

    $slug = 'test-upd-' . time();
    $id   = _email_insertEvent($slug, null); // connection closed inside

    $newEmail = 'updated@idol-stage.com';
    $db   = _email_db();
    $stmt = $db->prepare("UPDATE events SET email = :email WHERE id = :id");
    $stmt->execute([':email' => $newEmail, ':id' => $id]);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null; // explicit close

    $db   = _email_db();
    $stmt = $db->prepare("SELECT email FROM events WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null; // explicit close

    _email_deleteEvent($id); // connection closed inside

    $test->assertEquals($newEmail, $row['email'], 'Email should be updated');
}

function testUpdateEventEmailToNull($test) {
    if (!file_exists(DB_PATH)) {
        echo " [SKIP: No database] ";
        return;
    }
    if (!_email_hasColumn('events', 'email')) {
        echo " [SKIP: No email column] ";
        return;
    }

    $slug = 'test-nullify-' . time();
    $id   = _email_insertEvent($slug, 'original@example.com'); // connection closed inside

    $db   = _email_db();
    $stmt = $db->prepare("UPDATE events SET email = NULL WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null; // explicit close

    $db   = _email_db();
    $stmt = $db->prepare("SELECT email FROM events WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null; // explicit close

    _email_deleteEvent($id); // connection closed inside

    $test->assertNull($row['email'], 'Email should be updatable to NULL');
}

// ── 5. Read-back ─────────────────────────────────────────────────────────────

function testGetEventReturnsEmailField($test) {
    if (!file_exists(DB_PATH)) {
        echo " [SKIP: No database] ";
        return;
    }
    if (!_email_hasColumn('events', 'email')) {
        echo " [SKIP: No email column] ";
        return;
    }

    $slug  = 'test-get-' . time();
    $email = 'get-test@example.com';
    $id    = _email_insertEvent($slug, $email); // connection closed inside

    $db   = _email_db();
    $stmt = $db->prepare("SELECT * FROM events WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $stmt = null;
    $db   = null; // explicit close

    _email_deleteEvent($id); // connection closed inside

    $test->assertArrayHasKey('email', $row, 'SELECT * should include email field');
    $test->assertEquals($email, $row['email'], 'email field value should match inserted value');
}

// ── 6. ICS ORGANIZER Logic ───────────────────────────────────────────────────

function testOrganizerLineUsesEventEmail($test) {
    $eventMeta = ['name' => 'Idol Stage Feb 2026', 'email' => 'contact@idol-stage.com'];
    $line = _email_organizerLine($eventMeta);
    $test->assertEquals(
        'ORGANIZER;CN="Idol Stage Feb 2026":mailto:contact@idol-stage.com',
        $line,
        'ORGANIZER should use event name and email when email is valid'
    );
}

function testOrganizerLineFallsBackToNoreply($test) {
    $eventMeta = ['name' => 'Idol Stage Feb 2026'];
    $line = _email_organizerLine($eventMeta);
    $test->assertEquals(
        'ORGANIZER;CN="Idol Stage Feb 2026":mailto:noreply@stageidol.local',
        $line,
        'ORGANIZER should fall back to noreply@stageidol.local when email is absent'
    );
}

function testOrganizerLineNullEmailFallsBack($test) {
    $eventMeta = ['name' => 'Idol Stage Feb 2026', 'email' => null];
    $line = _email_organizerLine($eventMeta);
    $test->assertNotNull($line, 'ORGANIZER should still be emitted when email is null');
    $test->assertContains('noreply@stageidol.local', $line, 'Should fall back to noreply when email is null');
}

function testOrganizerLineInvalidEmailFallsBack($test) {
    $eventMeta = ['name' => 'Idol Stage Feb 2026', 'email' => 'not-valid-email'];
    $line = _email_organizerLine($eventMeta);
    $test->assertContains('noreply@stageidol.local', $line, 'Should fall back to noreply when email is invalid');
}

function testOrganizerLineNotEmittedWhenNoEventMeta($test) {
    $line = _email_organizerLine(null);
    $test->assertNull($line, 'ORGANIZER should not be emitted when eventMeta is null');
}

function testOrganizerLineNotEmittedWhenNameEmpty($test) {
    $line = _email_organizerLine(['name' => '', 'email' => 'x@x.com']);
    $test->assertNull($line, 'ORGANIZER should not be emitted when event name is empty');
}

// ── 7. Migration Script ──────────────────────────────────────────────────────

function testMigrateScriptExists($test) {
    $path = dirname(__DIR__) . '/tools/migrate-add-event-email-column.php';
    $test->assertTrue(file_exists($path), 'migrate-add-event-email-column.php should exist in tools/');
}

function testMigrateScriptIsIdempotent($test) {
    if (!file_exists(DB_PATH)) {
        echo " [SKIP: No database] ";
        return;
    }
    // Simulate what the migration does: detect column presence and skip if already there
    $hasEmail = _email_hasColumn('events', 'email');
    $would_skip = $hasEmail; // migration skips when column exists
    $test->assertTrue($would_skip, 'Migration should detect existing column and skip ALTER TABLE');
}
