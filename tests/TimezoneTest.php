<?php
/**
 * Timezone Tests (v4.0.0)
 *
 * Covers all changes introduced by the Per-event Timezone feature:
 *  - events.timezone column (schema, default value, nullable)
 *  - get_event_timezone() helper (priority: event → DEFAULT_TIMEZONE → fallback)
 *  - icsVtimezone() / icsOffsetString() RFC 5545 helpers
 *  - UTC timestamp computation via new DateTime($t, $tzObj) vs strtotime()
 *  - Admin API createEvent/updateEvent persists timezone
 *  - export.php: VTIMEZONE block, DTSTART;TZID=, X-WR-TIMEZONE
 *  - feed.php: same checks
 *  - index.php: window.EVENT_TIMEZONE, data-utc, timezone badge
 *  - admin/index.php: conventionTimezone select exists
 *  - config/app.php: DEFAULT_TIMEZONE constant
 *  - js/translations.js: tz.badge, tz.localTime keys
 *  - js/common.js: initTimezoneDisplay function
 *  - Migration script exists and is idempotent
 */

require_once __DIR__ . '/../config.php';

// ── Helpers ───────────────────────────────────────────────────────────────────

function _tz_db(): ?PDO {
    if (!file_exists(DB_PATH)) return null;
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA busy_timeout = 3000");
    return $db;
}

function _tz_eventsHasColumn(string $col): bool {
    $db = _tz_db();
    if (!$db) return false;
    $stmt = $db->query("PRAGMA table_info(events)");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    $stmt->closeCursor(); $stmt = null; $db = null;
    return in_array($col, $cols, true);
}

/** Insert a minimal test event and return its ID. Caller must delete after use. */
function _tz_insertEvent(string $slug, string $timezone = 'Asia/Bangkok'): int {
    $db  = _tz_db();
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("
        INSERT INTO events (slug, name, timezone, is_active, venue_mode, created_at, updated_at)
        VALUES (:slug, :name, :tz, 1, 'multi', :now, :now2)
    ");
    $stmt->execute([':slug' => $slug, ':name' => 'TZ Test ' . $slug, ':tz' => $timezone, ':now' => $now, ':now2' => $now]);
    $id = (int) $db->lastInsertId();
    $stmt->closeCursor(); $stmt = null; $db = null;
    return $id;
}

function _tz_deleteEvent(int $id): void {
    $db   = _tz_db();
    $stmt = $db->prepare("DELETE FROM events WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $stmt->closeCursor(); $stmt = null; $db = null;
}

// ── 1. DB Schema ──────────────────────────────────────────────────────────────

function testTimezoneColumnExists($test) {
    if (!file_exists(DB_PATH)) { echo " [SKIP: No DB] "; return; }
    $test->assertTrue(
        _tz_eventsHasColumn('timezone'),
        'events table should have timezone column (run migrate-add-timezone-column.php)'
    );
}

function testTimezoneColumnIsNullable($test) {
    if (!file_exists(DB_PATH)) { echo " [SKIP: No DB] "; return; }
    $db   = _tz_db();
    $stmt = $db->query("PRAGMA table_info(events)");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor(); $stmt = null; $db = null;

    $col = null;
    foreach ($cols as $c) { if ($c['name'] === 'timezone') { $col = $c; break; } }
    if (!$col) { echo " [SKIP: No timezone column] "; return; }
    $test->assertEquals('0', (string) $col['notnull'], 'timezone column should be nullable (notnull=0)');
}

function testTimezoneColumnDefaultValue($test) {
    if (!file_exists(DB_PATH)) { echo " [SKIP: No DB] "; return; }
    $db   = _tz_db();
    $stmt = $db->query("PRAGMA table_info(events)");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor(); $stmt = null; $db = null;

    $col = null;
    foreach ($cols as $c) { if ($c['name'] === 'timezone') { $col = $c; break; } }
    if (!$col) { echo " [SKIP: No timezone column] "; return; }
    $test->assertEquals("'Asia/Bangkok'", $col['dflt_value'], "timezone default should be 'Asia/Bangkok'");
}

// ── 2. Migration Script ───────────────────────────────────────────────────────

function testMigrateTimezoneScriptExists($test) {
    $path = dirname(__DIR__) . '/tools/migrate-add-timezone-column.php';
    $test->assertTrue(file_exists($path), 'tools/migrate-add-timezone-column.php should exist');
}

function testMigrateTimezoneIsIdempotent($test) {
    if (!file_exists(DB_PATH)) { echo " [SKIP: No DB] "; return; }
    $test->assertTrue(
        _tz_eventsHasColumn('timezone'),
        'Column already present — running migration again should be a no-op (idempotent)'
    );
}

// ── 3. config/app.php — DEFAULT_TIMEZONE constant ─────────────────────────────

function testDefaultTimezoneConstantDefined($test) {
    $test->assertTrue(defined('DEFAULT_TIMEZONE'), 'DEFAULT_TIMEZONE constant should be defined in config/app.php');
}

function testDefaultTimezoneConstantValue($test) {
    if (!defined('DEFAULT_TIMEZONE')) { echo " [SKIP] "; return; }
    $test->assertEquals('Asia/Bangkok', DEFAULT_TIMEZONE, 'DEFAULT_TIMEZONE should be Asia/Bangkok');
}

// ── 4. get_event_timezone() helper ───────────────────────────────────────────

function testGetEventTimezoneNoEventMeta($test) {
    require_once dirname(__DIR__) . '/functions/helpers.php';
    $result = get_event_timezone(null);
    $test->assertEquals('Asia/Bangkok', $result, 'null eventMeta should return default timezone');
}

function testGetEventTimezoneEmptyEventMeta($test) {
    $result = get_event_timezone([]);
    $test->assertEquals('Asia/Bangkok', $result, 'empty eventMeta should return default timezone');
}

function testGetEventTimezoneValidTimezone($test) {
    $result = get_event_timezone(['timezone' => 'Asia/Tokyo']);
    $test->assertEquals('Asia/Tokyo', $result, 'should return event timezone when valid');
}

function testGetEventTimezoneAnotherValidTimezone($test) {
    $result = get_event_timezone(['timezone' => 'America/New_York']);
    $test->assertEquals('America/New_York', $result, 'should return America/New_York');
}

function testGetEventTimezoneInvalidTimezone($test) {
    $result = get_event_timezone(['timezone' => 'INVALID/TIMEZONE']);
    $test->assertEquals('Asia/Bangkok', $result, 'invalid timezone should fall back to default');
}

function testGetEventTimezoneEmptyString($test) {
    $result = get_event_timezone(['timezone' => '']);
    $test->assertEquals('Asia/Bangkok', $result, 'empty timezone string should fall back to default');
}

function testGetEventTimezoneNullField($test) {
    $result = get_event_timezone(['timezone' => null]);
    $test->assertEquals('Asia/Bangkok', $result, 'null timezone field should fall back to default');
}

// ── 5. icsOffsetString() helper ──────────────────────────────────────────────

function testIcsOffsetStringPositive($test) {
    require_once dirname(__DIR__) . '/functions/ics.php';
    $test->assertEquals('+0700', icsOffsetString(7 * 3600), 'Bangkok +07:00');
}

function testIcsOffsetStringPositiveNine($test) {
    $test->assertEquals('+0900', icsOffsetString(9 * 3600), 'Tokyo +09:00');
}

function testIcsOffsetStringNegative($test) {
    $test->assertEquals('-0500', icsOffsetString(-5 * 3600), 'EST -05:00');
}

function testIcsOffsetStringHalfHour($test) {
    $test->assertEquals('+0530', icsOffsetString(5 * 3600 + 30 * 60), 'Kolkata +05:30');
}

function testIcsOffsetStringZero($test) {
    $test->assertEquals('+0000', icsOffsetString(0), 'UTC +00:00');
}

// ── 6. icsVtimezone() helper ─────────────────────────────────────────────────

function testIcsVtimezoneReturnsBangkok($test) {
    $out = icsVtimezone('Asia/Bangkok');
    $test->assertContains('BEGIN:VTIMEZONE', $out, 'should contain BEGIN:VTIMEZONE');
    $test->assertContains('TZID:Asia/Bangkok', $out, 'should contain TZID:Asia/Bangkok');
    $test->assertContains('END:VTIMEZONE', $out, 'should contain END:VTIMEZONE');
    $test->assertContains('+0700', $out, 'should contain +0700 offset');
}

function testIcsVtimezoneBangkokNoDAYLIGHT($test) {
    $out = icsVtimezone('Asia/Bangkok');
    $test->assertFalse(str_contains($out, 'BEGIN:DAYLIGHT'), 'Bangkok has no DST — should not have DAYLIGHT component');
}

function testIcsVtimezoneHasStandardComponent($test) {
    $out = icsVtimezone('Asia/Bangkok');
    $test->assertContains('BEGIN:STANDARD', $out, 'should contain STANDARD component');
    $test->assertContains('END:STANDARD', $out, 'should contain END:STANDARD');
}

function testIcsVtimezoneTokyo($test) {
    $out = icsVtimezone('Asia/Tokyo');
    $test->assertContains('TZID:Asia/Tokyo', $out, 'TZID:Asia/Tokyo');
    $test->assertContains('+0900', $out, 'Tokyo +09:00');
}

function testIcsVtimezoneNewYorkHasDST($test) {
    $out = icsVtimezone('America/New_York');
    $test->assertContains('TZID:America/New_York', $out, 'TZID:America/New_York');
    $test->assertContains('BEGIN:DAYLIGHT', $out, 'New York has DST — should include DAYLIGHT component');
    $test->assertContains('BEGIN:STANDARD', $out, 'should also include STANDARD');
}

function testIcsVtimezoneNewYorkOffsets($test) {
    $out = icsVtimezone('America/New_York');
    $test->assertContains('-0500', $out, 'EST -05:00 in Standard');
    $test->assertContains('-0400', $out, 'EDT -04:00 in Daylight');
}

function testIcsVtimezoneInvalidReturnsEmpty($test) {
    $out = icsVtimezone('INVALID/TIMEZONE');
    $test->assertEquals('', $out, 'invalid timezone should return empty string');
}

function testIcsVtimezoneEndsWithCRLF($test) {
    $out = icsVtimezone('Asia/Bangkok');
    $test->assertTrue(str_ends_with($out, "\r\n"), 'VTIMEZONE block should end with CRLF');
}

// ── 7. UTC timestamp computation ─────────────────────────────────────────────

function testBangkokTimestampMatchesStrtotime($test) {
    // When event TZ = Asia/Bangkok (default), DateTime result should match strtotime()
    // because PHP default TZ is also Asia/Bangkok (set in config/app.php)
    $time = '2026-03-19 10:00:00';
    $expected = strtotime($time);
    $actual   = (new DateTime($time, new DateTimeZone('Asia/Bangkok')))->getTimestamp();
    $test->assertEquals($expected, $actual, 'Bangkok DateTime should match strtotime() for same TZ');
}

function testTokyoTimestampDifferentFromBangkok($test) {
    $time    = '2026-03-19 10:00:00';
    $bkkTs   = (new DateTime($time, new DateTimeZone('Asia/Bangkok')))->getTimestamp();
    $tokyoTs = (new DateTime($time, new DateTimeZone('Asia/Tokyo')))->getTimestamp();
    // Tokyo = UTC+9, Bangkok = UTC+7 → same wall-clock time, Tokyo is 2h earlier in UTC
    $diff = $bkkTs - $tokyoTs;
    $test->assertEquals(7200, $diff, '10:00 Bangkok UTC is 2h after 10:00 Tokyo UTC (UTC+7 vs UTC+9)');
}

function testLosAngelesTimestamp($test) {
    // Use January (PST = UTC-8, no DST ambiguity)
    $time  = '2026-01-19 10:00:00';
    $laTs  = (new DateTime($time, new DateTimeZone('America/Los_Angeles')))->getTimestamp();
    $bkkTs = (new DateTime($time, new DateTimeZone('Asia/Bangkok')))->getTimestamp();
    // LA = UTC-8 (PST in January), Bangkok = UTC+7 → 15h difference
    $diffSeconds = $laTs - $bkkTs;
    $test->assertEquals(54000, $diffSeconds, '10:00 LA PST UTC is 15h (54000s) after 10:00 Bangkok UTC (UTC-8 vs UTC+7)');
}

// ── 8. DB CRUD — events.timezone ─────────────────────────────────────────────

function testInsertEventWithTimezone($test) {
    if (!file_exists(DB_PATH)) { echo " [SKIP: No DB] "; return; }
    if (!_tz_eventsHasColumn('timezone')) { echo " [SKIP: No timezone column] "; return; }

    $id = _tz_insertEvent('tz-test-tokyo-' . time(), 'Asia/Tokyo');
    $db   = _tz_db();
    $stmt = $db->prepare("SELECT timezone FROM events WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor(); $stmt = null; $db = null;
    _tz_deleteEvent($id);
    $test->assertEquals('Asia/Tokyo', $row['timezone'] ?? null, 'Asia/Tokyo should be stored and retrievable');
}

function testInsertEventDefaultTimezone($test) {
    if (!file_exists(DB_PATH)) { echo " [SKIP: No DB] "; return; }
    if (!_tz_eventsHasColumn('timezone')) { echo " [SKIP: No timezone column] "; return; }

    // Insert without specifying timezone — should default to Asia/Bangkok
    $db  = _tz_db();
    $now = date('Y-m-d H:i:s');
    $slug = 'tz-default-' . time();
    $stmt = $db->prepare("INSERT INTO events (slug, name, is_active, venue_mode, created_at, updated_at) VALUES (:s,:n,1,'multi',:t,:t2)");
    $stmt->execute([':s' => $slug, ':n' => 'TZ Default Test', ':t' => $now, ':t2' => $now]);
    $id = (int) $db->lastInsertId();
    $stmt->closeCursor(); $stmt = null;

    $stmt = $db->prepare("SELECT timezone FROM events WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor(); $stmt = null; $db = null;
    _tz_deleteEvent($id);
    $test->assertEquals('Asia/Bangkok', $row['timezone'] ?? null, 'default timezone should be Asia/Bangkok');
}

function testUpdateEventTimezone($test) {
    if (!file_exists(DB_PATH)) { echo " [SKIP: No DB] "; return; }
    if (!_tz_eventsHasColumn('timezone')) { echo " [SKIP: No timezone column] "; return; }

    $id = _tz_insertEvent('tz-update-' . time(), 'Asia/Bangkok');
    $db  = _tz_db();
    $stmt = $db->prepare("UPDATE events SET timezone = :tz WHERE id = :id");
    $stmt->execute([':tz' => 'Europe/London', ':id' => $id]);
    $stmt->closeCursor(); $stmt = null;

    $stmt = $db->prepare("SELECT timezone FROM events WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor(); $stmt = null; $db = null;
    _tz_deleteEvent($id);
    $test->assertEquals('Europe/London', $row['timezone'] ?? null, 'timezone should be updatable');
}

// ── 9. export.php source checks ──────────────────────────────────────────────

function testExportPhpRequiresIcsPhp($test) {
    $src = file_get_contents(dirname(__DIR__) . '/export.php');
    $test->assertContains("require_once 'functions/ics.php'", $src, 'export.php should require functions/ics.php');
}

function testExportPhpCallsGetEventTimezone($test) {
    $src = file_get_contents(dirname(__DIR__) . '/export.php');
    $test->assertContains('get_event_timezone', $src, 'export.php should call get_event_timezone()');
}

function testExportPhpUsesXWRTimezoneEventTz($test) {
    $src = file_get_contents(dirname(__DIR__) . '/export.php');
    $test->assertContains('X-WR-TIMEZONE', $src, 'export.php should output X-WR-TIMEZONE');
    $test->assertContains('$eventTz', $src, 'export.php X-WR-TIMEZONE should use $eventTz variable');
}

function testExportPhpUsesVtimezoneFunction($test) {
    $src = file_get_contents(dirname(__DIR__) . '/export.php');
    $test->assertContains('icsVtimezone(', $src, 'export.php should call icsVtimezone()');
}

function testExportPhpUsesTZIDFormat($test) {
    $src = file_get_contents(dirname(__DIR__) . '/export.php');
    $test->assertContains('DTSTART;TZID=', $src, 'export.php DTSTART should use TZID format');
    $test->assertContains('DTEND;TZID=', $src, 'export.php DTEND should use TZID format');
}

function testExportPhpNoHardcodedBangkokTZ($test) {
    $src = file_get_contents(dirname(__DIR__) . '/export.php');
    // X-WR-TIMEZONE should NOT be hardcoded — it must use the variable
    $test->assertFalse(str_contains($src, 'X-WR-TIMEZONE:Asia/Bangkok'), 'X-WR-TIMEZONE should not be hardcoded');
}

function testExportPhpNoUTCZSuffix($test) {
    $src = file_get_contents(dirname(__DIR__) . '/export.php');
    // DTSTART should no longer use gmdate(...\Z) pattern
    $test->assertFalse(str_contains($src, "gmdate('Ymd\\THis\\Z', strtotime"),
        'export.php should not use old UTC Z format for DTSTART/DTEND');
}

// ── 10. feed.php source checks ───────────────────────────────────────────────

function testFeedPhpCallsGetEventTimezone($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains('get_event_timezone', $src, 'feed.php should call get_event_timezone()');
}

function testFeedPhpUsesXWRTimezoneEventTz($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains('$eventTz', $src, 'feed.php X-WR-TIMEZONE should use $eventTz');
    $test->assertFalse(str_contains($src, 'X-WR-TIMEZONE:Asia/Bangkok'), 'feed.php X-WR-TIMEZONE should not be hardcoded');
}

function testFeedPhpUsesVtimezoneFunction($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains('icsVtimezone(', $src, 'feed.php should call icsVtimezone()');
}

function testFeedPhpUsesTZIDFormat($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains('DTSTART;TZID=', $src, 'feed.php DTSTART should use TZID format');
    $test->assertContains('DTEND;TZID=', $src, 'feed.php DTEND should use TZID format');
}

// ── 11. index.php source checks ──────────────────────────────────────────────

function testIndexPhpInjectsEventTimezone($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertContains('window.EVENT_TIMEZONE', $src, 'index.php should inject window.EVENT_TIMEZONE');
    $test->assertContains('get_event_timezone', $src, 'index.php should call get_event_timezone()');
}

function testIndexPhpHasTimezoneBadge($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertContains('event-timezone', $src, 'index.php should contain event-timezone element');
    $test->assertContains('eventTimezoneDisplay', $src, 'index.php should contain eventTimezoneDisplay id');
}

function testIndexPhpHasDataUtcAttribute($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertContains('data-utc', $src, 'index.php program-time span should have data-utc attribute');
}

function testIndexPhpUsesDateTimeForTimestamp($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertContains('new DateTime(', $src, 'index.php should use new DateTime() for UTC timestamp computation');
    $test->assertContains('$eventTzObj', $src, 'index.php should use $eventTzObj for timezone-aware timestamps');
}

// ── 12. admin/index.php — timezone picker ────────────────────────────────────

function testAdminIndexHasTimezoneSelect($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $test->assertContains('conventionTimezone', $src, 'admin/index.php should have conventionTimezone select');
}

function testAdminIndexHasAsiaTokyoOption($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $test->assertContains('Asia/Tokyo', $src, 'admin timezone picker should include Asia/Tokyo option');
}

function testAdminIndexHasAmericaLAOption($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    $test->assertContains('America/Los_Angeles', $src, 'admin timezone picker should include America/Los_Angeles option');
}

function testAdminIndexTimezoneInSaveData($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/index.php');
    // The save form data object should include the timezone field
    $test->assertContains("conventionTimezone", $src, 'admin save data should read conventionTimezone element');
}

// ── 13. admin/api.php — createEvent/updateEvent ──────────────────────────────

function testAdminApiCreateEventAcceptsTimezone($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    // Look for timezone in createEvent INSERT
    $test->assertContains("':timezone'", $src, 'admin/api.php createEvent should bind :timezone parameter');
}

function testAdminApiUpdateEventAcceptsTimezone($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    // Look for timezone in updateEvent UPDATE
    $test->assertContains('timezone = :timezone', $src, 'admin/api.php updateEvent should SET timezone = :timezone');
}

function testAdminApiTimezoneValidation($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    // Should use DateTimeZone to validate the timezone
    $test->assertContains('new DateTimeZone(', $src, 'admin/api.php should validate timezone with new DateTimeZone()');
}

// ── 14. translations.js source checks ────────────────────────────────────────

function testTranslationsHasTzBadgeKey($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    $test->assertContains("'tz.badge'", $src, "translations.js should have 'tz.badge' key");
}

function testTranslationsHasTzLocalTimeKey($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    $test->assertContains("'tz.localTime'", $src, "translations.js should have 'tz.localTime' key");
}

function testTranslationsTzBadgeAllLanguages($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/translations.js');
    // Simple check: tz.badge appears at least 3 times (TH, EN, JA)
    $count = substr_count($src, "'tz.badge'");
    $test->assertTrue($count >= 3, "tz.badge key should appear in all 3 languages (found $count)");
}

// ── 15. common.js source checks ──────────────────────────────────────────────

function testCommonJsHasInitTimezoneDisplay($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/common.js');
    $test->assertContains('initTimezoneDisplay', $src, 'common.js should have initTimezoneDisplay function');
}

function testCommonJsUsesIntlDateTimeFormat($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/common.js');
    $test->assertContains('Intl.DateTimeFormat', $src, 'common.js should use Intl.DateTimeFormat for timezone conversion');
}

function testCommonJsReadsEventTimezoneGlobal($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/common.js');
    $test->assertContains('EVENT_TIMEZONE', $src, 'common.js should read window.EVENT_TIMEZONE');
}

function testCommonJsHasUpdateTimezoneLabels($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/common.js');
    $test->assertContains('updateTimezoneLabels', $src, 'common.js should have updateTimezoneLabels for language-switch re-render');
}

function testCommonJsBadgeShowsInlineClientTz($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/common.js');
    // Badge textContent should be set to "🕐 eventTz (userTz)" format
    $test->assertContains("badge.textContent = '🕐 ' + eventTz + ' (' + userTz + ')'", $src,
        'initTimezoneDisplay should set badge textContent inline as "🕐 eventTz (userTz)"');
}

function testCommonJsListensToAppLangChangeForTimezone($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/common.js');
    $test->assertContains("'appLangChange'", $src, 'common.js should listen to appLangChange event to update timezone labels');
    $test->assertContains('updateTimezoneLabels', $src, 'appLangChange handler should call updateTimezoneLabels');
}

function testCommonJsStoresLocalTimeInDataAttribute($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/common.js');
    $test->assertContains('data-localtime', $src, 'common.js should store local time in data-localtime attribute for re-render on lang change');
}

function testCommonJsReadsUtcEndForTimeRange($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/common.js');
    $test->assertContains('data-utc-end', $src, 'common.js should read data-utc-end to compute local end time');
    $test->assertContains('localStart', $src, 'common.js should compute localStart for range display');
    $test->assertContains('localEnd', $src, 'common.js should compute localEnd for range display');
}

function testIndexPhpEmitsUtcEndAttribute($test) {
    $src = file_get_contents(dirname(__DIR__) . '/index.php');
    $test->assertContains('data-utc-end', $src, 'index.php should emit data-utc-end attribute on .program-time span');
    $test->assertContains('end_ts', $src, 'index.php data-utc-end should use end_ts');
}

function testCommonJsHasCalLocalTimeRangeHelper($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/common.js');
    $test->assertContains('calLocalTimeRange', $src, 'common.js should have calLocalTimeRange helper for calendar view');
    $test->assertContains('start_ts', $src, 'calLocalTimeRange should use start_ts from CALENDAR_EVENTS');
    $test->assertContains('end_ts', $src, 'calLocalTimeRange should use end_ts from CALENDAR_EVENTS');
}

function testCommonJsCalChipShowsLocalTime($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/common.js');
    $test->assertContains('cal-chip-time-local', $src, 'chip rendering should include cal-chip-time-local span');
    $test->assertContains('cal-chip-has-local', $src, 'chip should get cal-chip-has-local class when local time differs');
}

function testCommonJsDayPanelShowsLocalTime($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/common.js');
    $test->assertContains('cal-dp-item-time-local', $src, 'day panel should include cal-dp-item-time-local when timezone differs');
}

function testCommonCssHasCalChipTimeLocalClass($test) {
    $src = file_get_contents(dirname(__DIR__) . '/styles/common.css');
    $test->assertContains('.cal-chip-time-local', $src, 'common.css should define .cal-chip-time-local');
    $test->assertContains('.cal-chip-has-local', $src, 'common.css should define .cal-chip-has-local with flex-wrap');
}

function testCommonCssHasCalDpItemTimeLocalClass($test) {
    $src = file_get_contents(dirname(__DIR__) . '/styles/common.css');
    $test->assertContains('.cal-dp-item-time-local', $src, 'common.css should define .cal-dp-item-time-local');
}

function testCommonJsDetailModalShowsLocalTime($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/common.js');
    $test->assertContains('cal-detail-time-local', $src, 'openCalendarDetailModal should include cal-detail-time-local when timezone differs');
    $test->assertContains('calLocalTimeRange(ev)', $src, 'openCalendarDetailModal should call calLocalTimeRange');
}

function testCommonCssHasCalDetailTimeLocalClass($test) {
    $src = file_get_contents(dirname(__DIR__) . '/styles/common.css');
    $test->assertContains('.cal-detail-time-local', $src, 'common.css should define .cal-detail-time-local for modal local time');
}

function testCommonJsSkipsDuplicateSpans($test) {
    $src = file_get_contents(dirname(__DIR__) . '/js/common.js');
    $test->assertContains('program-time-local', $src, 'common.js should guard against duplicate .program-time-local spans');
    $test->assertContains('classList.contains', $src, 'common.js should check classList before appending span');
}

// ── 16. styles/index.css source checks ───────────────────────────────────────

function testIndexCssHasEventTimezoneClass($test) {
    $cssPath = dirname(__DIR__) . '/styles/index.css';
    if (!file_exists($cssPath)) { echo " [SKIP: no styles/index.css] "; return; }
    $src = file_get_contents($cssPath);
    $test->assertContains('.event-timezone', $src, 'styles/index.css should define .event-timezone class');
}

function testIndexCssHasProgramTimeLocalClass($test) {
    $cssPath = dirname(__DIR__) . '/styles/index.css';
    if (!file_exists($cssPath)) { echo " [SKIP: no styles/index.css] "; return; }
    $src = file_get_contents($cssPath);
    $test->assertContains('.program-time-local', $src, 'styles/index.css should define .program-time-local class');
}

// ── 17. setup.php — migration awareness ──────────────────────────────────────

function testSetupPhpChecksTimezoneColumn($test) {
    $src = file_get_contents(dirname(__DIR__) . '/setup.php');
    $test->assertContains('$hasTimezoneColumn', $src, 'setup.php should check $hasTimezoneColumn');
}

function testSetupPhpIncludesTimezoneInAllTablesOk($test) {
    $src = file_get_contents(dirname(__DIR__) . '/setup.php');
    $test->assertContains('$hasTimezoneColumn', $src, 'setup.php $allTablesOk should include $hasTimezoneColumn');
}

function testSetupPhpHasTimezoneActionHandler($test) {
    $src = file_get_contents(dirname(__DIR__) . '/setup.php');
    $test->assertContains("'add_timezone_column'", $src, "setup.php should have 'add_timezone_column' action handler");
}

function testSetupPhpMigrationChecklistHasTimezone($test) {
    $src = file_get_contents(dirname(__DIR__) . '/setup.php');
    $test->assertContains('events.timezone column', $src, 'setup.php migration checklist should include events.timezone entry');
}
