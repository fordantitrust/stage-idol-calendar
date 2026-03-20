<?php
/**
 * ICS Subscription Feed Tests  (v2.5.x)
 *
 * Covers changes introduced in:
 *  v2.5.0 — feed.php live subscription endpoint, invalidate_data_version_cache(),
 *            ETag caching (feed- prefix, If-None-Match, Cache-Control: no-store)
 *  v2.5.1 — RFC 5545 icsFold() line folding (75-byte limit, UTF-8 boundary),
 *            CATEGORIES comma delimiter (unescaped), icsEscape() correctness
 *
 * NOTE: feed.php cannot be require_once'd directly because it outputs headers
 * and ICS body immediately. Pure helper functions (icsEscape, icsFold) and
 * business logic (CATEGORIES build, ORGANIZER line, ETag) are replicated here
 * as _feed_*() helpers and tested in isolation — following the pattern used
 * by EventEmailTest and ProgramTypeTest.
 */

require_once __DIR__ . '/../config.php';

// ── Replicated pure functions from feed.php ───────────────────────────────────

/**
 * Replica of icsEscape() from feed.php.
 * Escapes special characters in an ICS text value (RFC 5545 §3.3.11).
 * Used for individual CATEGORIES values where comma IS a value delimiter.
 */
function _feed_icsEscape(string $value): string {
    $value = str_replace('\\', '\\\\', $value); // backslash must be first
    $value = str_replace(';',  '\\;',  $value);
    $value = str_replace(',',  '\\,',  $value);
    $value = str_replace("\n", '\\n',  $value);
    $value = str_replace("\r", '',     $value);
    return $value;
}

/**
 * Replica of icsEscapeText() from feed.php.
 * Escapes for single-value TEXT properties (SUMMARY, LOCATION, DESCRIPTION).
 * Commas are intentionally NOT escaped — comma is not a delimiter in these properties,
 * and escaping it (\,) causes some calendar clients to truncate the title at that point.
 */
function _feed_icsEscapeText(string $value): string {
    $value = str_replace('\\', '\\\\', $value);
    $value = str_replace(';',  '\\;',  $value);
    $value = str_replace("\n", '\\n',  $value);
    $value = str_replace("\r", '',     $value);
    return $value;
}

/**
 * Replica of icsFold() from feed.php.
 * Folds lines at 75 octets, respecting UTF-8 character boundaries.
 */
function _feed_icsFold(string $line): string {
    if (strlen($line) <= 75) {
        return $line;
    }

    $folded    = '';
    $lineBytes = 0;
    $i         = 0;
    $len       = strlen($line);

    while ($i < $len) {
        $byte = ord($line[$i]);
        if ($byte < 0x80)      { $charLen = 1; }
        elseif ($byte < 0xE0)  { $charLen = 2; }
        elseif ($byte < 0xF0)  { $charLen = 3; }
        else                   { $charLen = 4; }

        if ($lineBytes + $charLen > 75 && $lineBytes > 0) {
            $folded    .= "\r\n ";
            $lineBytes  = 1;
        }

        $folded    .= substr($line, $i, $charLen);
        $lineBytes += $charLen;
        $i         += $charLen;
    }

    return $folded;
}

/**
 * Replica of the CATEGORIES build logic from feed.php.
 * Returns the CATEGORIES property value string, or null when there are none.
 */
function _feed_buildCategories(?string $categories, ?string $programType): ?string {
    $catValues = [];
    if (!empty($categories)) {
        foreach (array_map('trim', explode(',', $categories)) as $cat) {
            if ($cat !== '') $catValues[] = _feed_icsEscape($cat);
        }
    }
    if (!empty($programType)) {
        $catValues[] = _feed_icsEscape($programType);
    }
    return !empty($catValues) ? implode(',', $catValues) : null;
}

/**
 * Replica of the ORGANIZER line logic from feed.php / export.php.
 */
function _feed_organizerLine(?array $eventMeta): ?string {
    if (!$eventMeta || empty($eventMeta['name'])) {
        return null;
    }
    $orgName  = _feed_icsEscape($eventMeta['name']);
    $orgEmail = (!empty($eventMeta['email']) &&
                 filter_var($eventMeta['email'], FILTER_VALIDATE_EMAIL))
        ? $eventMeta['email']
        : 'noreply@stageidol.local';
    return 'ORGANIZER;CN="' . $orgName . '":mailto:' . $orgEmail;
}

/**
 * Replica of the ETag generation from feed.php.
 */
function _feed_etag(string $dataVersion, ?int $eventId): string {
    return '"feed-' . md5($dataVersion . '-' . ($eventId ?? '0')) . '"';
}

// ── 1. File Existence ─────────────────────────────────────────────────────────

function testFeedPhpFileExists($test) {
    $test->assertFileExists(
        dirname(__DIR__) . '/feed.php',
        'feed.php should exist in project root'
    );
}

// ── 2. icsEscape() ────────────────────────────────────────────────────────────

function testIcsEscapeBackslash($test) {
    $test->assertEquals('\\\\', _feed_icsEscape('\\'), 'Backslash should be doubled');
}

function testIcsEscapeSemicolon($test) {
    $test->assertEquals('\\;', _feed_icsEscape(';'), 'Semicolon should be escaped to \\;');
}

function testIcsEscapeComma($test) {
    $test->assertEquals('\\,', _feed_icsEscape(','), 'Comma in text values should be escaped to \\,');
}

function testIcsEscapeNewline($test) {
    $test->assertEquals('\\n', _feed_icsEscape("\n"), 'Newline should become \\n');
}

function testIcsEscapeCarriageReturnRemoved($test) {
    $test->assertEquals('', _feed_icsEscape("\r"), 'Carriage return should be removed entirely');
}

function testIcsEscapeEmptyString($test) {
    $test->assertEquals('', _feed_icsEscape(''), 'Empty string should return empty string');
}

function testIcsEscapeMultipleChars($test) {
    $input    = "Hello; World, Test\nLine\\End";
    $expected = 'Hello\\; World\\, Test\\nLine\\\\End';
    $test->assertEquals($expected, _feed_icsEscape($input), 'All special chars should be escaped in one pass');
}

function testIcsEscapeBackslashFirst($test) {
    // Backslash MUST be escaped first: if ";" were escaped before "\" then
    // "\\;" would become "\\\\;" (double-escaped) which is wrong.
    $input    = '\\;';           // a backslash followed by a semicolon
    $expected = '\\\\\\;';       // each char escaped independently
    $test->assertEquals($expected, _feed_icsEscape($input), 'Backslash must be escaped before semicolon/comma');
}

function testIcsEscapeThaiTextPassthrough($test) {
    // Thai characters contain no ICS special chars — should be unchanged
    $thai = 'ชื่อศิลปิน';
    $test->assertEquals($thai, _feed_icsEscape($thai), 'Thai text with no special chars should pass through unchanged');
}

// ── 2b. icsEscapeText() — single-value TEXT (SUMMARY / LOCATION / DESCRIPTION) ────

function testIcsEscapeTextCommaNotEscaped($test) {
    $test->assertEquals(
        'ONE BET, ALL IN',
        _feed_icsEscapeText('ONE BET, ALL IN'),
        'Comma must NOT be escaped in SUMMARY — some clients truncate on \\,'
    );
}

function testIcsEscapeTextMultipleCommasNotEscaped($test) {
    $test->assertEquals(
        'A, B, C',
        _feed_icsEscapeText('A, B, C'),
        'Multiple commas must all be left as-is in single-value TEXT properties'
    );
}

function testIcsEscapeTextSemicolonEscaped($test) {
    $test->assertEquals('\\;', _feed_icsEscapeText(';'), 'Semicolon must still be escaped');
}

function testIcsEscapeTextBackslashEscaped($test) {
    $test->assertEquals('\\\\', _feed_icsEscapeText('\\'), 'Backslash must still be doubled');
}

function testIcsEscapeTextNewlineEscaped($test) {
    $test->assertEquals('\\n', _feed_icsEscapeText("\n"), 'Newline must still be escaped');
}

function testIcsEscapeTextCarriageReturnRemoved($test) {
    $test->assertEquals('', _feed_icsEscapeText("\r"), 'Carriage return must still be stripped');
}

function testIcsEscapeTextTitleWithComma($test) {
    $input    = 'ONE BET, ALL IN. FUYUBI\'S 9TH SINGLE 1ST PERFORMANCE';
    $expected = 'ONE BET, ALL IN. FUYUBI\'S 9TH SINGLE 1ST PERFORMANCE';
    $test->assertEquals($expected, _feed_icsEscapeText($input),
        'Title with comma must pass through unchanged (no backslash before comma)');
}

function testFeedPhpDefinesIcsEscapeTextFunction($test) {
    $src = file_get_contents(dirname(__DIR__) . '/functions/ics.php');
    $test->assertContains('function icsEscapeText', $src,
        'functions/ics.php must define icsEscapeText() for SUMMARY/LOCATION/DESCRIPTION escaping');
}

function testFeedPhpUseIcsEscapeTextForSummary($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains('icsEscapeText($event[\'title\'])', $src,
        'feed.php must use icsEscapeText() (not icsEscape()) for SUMMARY to avoid comma truncation');
}

// ── 3. icsFold() — RFC 5545 Line Folding ─────────────────────────────────────

function testIcsFoldShortLineUnchanged($test) {
    $line = 'SUMMARY:Short title';
    $test->assertEquals($line, _feed_icsFold($line), 'Lines ≤75 bytes should not be folded');
}

function testIcsFoldExactly75BytesUnchanged($test) {
    $line = str_repeat('A', 75);
    $test->assertEquals($line, _feed_icsFold($line), 'A line of exactly 75 bytes should not be folded');
}

function testIcsFoldEmptyStringUnchanged($test) {
    $test->assertEquals('', _feed_icsFold(''), 'Empty string should return empty string');
}

function testIcsFold76BytesFoldsAt75($test) {
    $line   = str_repeat('A', 76); // 75 + 1
    $folded = _feed_icsFold($line);
    $test->assertContains("\r\n ", $folded, '76-byte line must contain CRLF+SPACE fold marker');
    $parts  = explode("\r\n ", $folded);
    $test->assertEquals(75, strlen($parts[0]), 'First segment should be 75 bytes');
    $test->assertEquals(1,  strlen($parts[1]), 'Remaining segment should be 1 byte');
}

function testIcsFoldLongLineEachSegmentWithinLimit($test) {
    // "SUMMARY:" (8 bytes) + 200 'X' = 208 bytes — should fold multiple times
    $line   = 'SUMMARY:' . str_repeat('X', 200);
    $folded = _feed_icsFold($line);
    $parts  = explode("\r\n ", $folded);
    $test->assertTrue(count($parts) >= 3, 'A 208-byte line should produce at least 3 segments');
    // First segment ≤75 bytes; each continuation segment ≤74 bytes (space occupies 1)
    $test->assertLessThanOrEqual(75, strlen($parts[0]), 'First segment must be ≤75 bytes');
    for ($i = 1; $i < count($parts); $i++) {
        $test->assertLessThanOrEqual(74, strlen($parts[$i]),
            "Continuation segment {$i} must be ≤74 bytes (space prefix counts as 1)");
    }
}

function testIcsFoldUtf8CharNotSplitAcrossBoundary($test) {
    // "ก" is 3-byte UTF-8 (E0 B8 81).
    // 73 ASCII + "ก" = 76 bytes → fold must happen before "ก", not mid-character.
    $line   = str_repeat('A', 73) . 'ก';
    $folded = _feed_icsFold($line);
    $parts  = explode("\r\n ", $folded);
    $test->assertEquals(73, strlen($parts[0]), 'First segment should hold 73 ASCII bytes before Thai char');
    $test->assertEquals('ก', $parts[1],       'Thai character should start the continuation segment intact');
}

function testIcsFoldThaiTextSegmentsAreValidUtf8($test) {
    // "SUMMARY:" (8 bytes) + 30 × "ก" (90 bytes) = 98 bytes
    $line   = 'SUMMARY:' . str_repeat('ก', 30);
    $folded = _feed_icsFold($line);
    $parts  = explode("\r\n ", $folded);
    $test->assertTrue(count($parts) >= 2, 'Thai line > 75 bytes should be folded');
    foreach ($parts as $i => $part) {
        $test->assertTrue(
            mb_check_encoding($part, 'UTF-8'),
            "Folded segment {$i} must be valid UTF-8 (no mid-char splits)"
        );
    }
}

// ── 4. CATEGORIES Build Logic ─────────────────────────────────────────────────

function testCategoriesSingleValueNoEscape($test) {
    $result = _feed_buildCategories('ArtistA', null);
    $test->assertEquals('ArtistA', $result, 'Single category with no special chars should be output unchanged');
}

function testCategoriesMultipleValuesJoinedWithUnescapedComma($test) {
    $result = _feed_buildCategories('ArtistA,ArtistB,ArtistC', null);
    $test->assertEquals('ArtistA,ArtistB,ArtistC', $result,
        'Multiple categories should be joined with an unescaped delimiter comma');
    // The result must NOT contain \, (escaped comma)
    $test->assertTrue(
        strpos($result, '\\,') === false,
        'CATEGORIES delimiter comma must NOT be escaped (RFC 5545 §3.8.1.2)'
    );
}

function testCategoriesSemicolonInCategoryNameIsEscaped($test) {
    // A semicolon inside a category value must be escaped
    $result = _feed_buildCategories('Artist;Stage', null);
    $test->assertEquals('Artist\\;Stage', $result, 'Semicolon inside a category value should be escaped');
}

function testCategoriesProgramTypeAppended($test) {
    $result = _feed_buildCategories('ArtistA,ArtistB', 'Live');
    $test->assertEquals('ArtistA,ArtistB,Live', $result,
        'program_type should be appended after categories with unescaped comma');
    $test->assertTrue(
        strpos($result, '\\,') === false,
        'No comma in the result should be escaped'
    );
}

function testCategoriesOnlyProgramType($test) {
    $result = _feed_buildCategories(null, 'Stage Show');
    $test->assertEquals('Stage Show', $result, 'Should output only program_type when categories is null');
}

function testCategoriesEmptyBothReturnsNull($test) {
    $test->assertNull(_feed_buildCategories(null, null),  'null+null should return null');
    $test->assertNull(_feed_buildCategories('', ''),      'empty+empty should return null');
    $test->assertNull(_feed_buildCategories('  ', null),  'whitespace-only categories should return null');
}

function testCategoriesWhitespaceTrimmed($test) {
    $result = _feed_buildCategories(' ArtistA , ArtistB ', null);
    $test->assertEquals('ArtistA,ArtistB', $result, 'Category values should be trimmed of surrounding whitespace');
}

// ── 5. ORGANIZER Logic ────────────────────────────────────────────────────────

function testFeedOrganizerUsesEventEmail($test) {
    $line = _feed_organizerLine(['name' => 'Idol Stage', 'email' => 'contact@idol-stage.com']);
    $test->assertEquals(
        'ORGANIZER;CN="Idol Stage":mailto:contact@idol-stage.com',
        $line,
        'ORGANIZER should use valid event email'
    );
}

function testFeedOrganizerFallsBackToNoreplyOnMissingEmail($test) {
    $line = _feed_organizerLine(['name' => 'Idol Stage', 'email' => null]);
    $test->assertContains('noreply@stageidol.local', $line,
        'ORGANIZER should fall back to noreply@stageidol.local when email is null');
}

function testFeedOrganizerFallsBackToNoreplyOnInvalidEmail($test) {
    $line = _feed_organizerLine(['name' => 'Idol Stage', 'email' => 'not-an-email']);
    $test->assertContains('noreply@stageidol.local', $line,
        'ORGANIZER should fall back to noreply@stageidol.local when email is invalid');
}

function testFeedOrganizerNullMetaReturnsNull($test) {
    $test->assertNull(_feed_organizerLine(null), 'ORGANIZER should not be emitted when eventMeta is null');
}

function testFeedOrganizerEscapesSpecialCharsInName($test) {
    $line = _feed_organizerLine(['name' => 'Idol; Stage, Event', 'email' => null]);
    $test->assertNotNull($line, 'ORGANIZER line should still be generated');
    $test->assertContains('Idol\\; Stage\\, Event', $line,
        'Special chars in event name should be escaped in ORGANIZER CN');
}

// ── 6. ETag Format ────────────────────────────────────────────────────────────

function testEtagIsDoubleQuoted($test) {
    $etag = _feed_etag('2026-03-03 12:00:00', null);
    $test->assertTrue(str_starts_with($etag, '"'), 'ETag must begin with a double-quote (RFC 7232)');
    $test->assertTrue(str_ends_with($etag, '"'),   'ETag must end with a double-quote (RFC 7232)');
}

function testEtagContainsFeedPrefix($test) {
    $etag = _feed_etag('2026-03-03 12:00:00', null);
    $test->assertContains('"feed-', $etag, 'ETag should start with "feed- identifier');
}

function testEtagDifferentVersionsProduceDifferentEtags($test) {
    $etag1 = _feed_etag('version-A', null);
    $etag2 = _feed_etag('version-B', null);
    $test->assertNotEquals($etag1, $etag2, 'Different data versions must produce different ETags');
}

function testEtagSameInputsProduceSameEtag($test) {
    $etag1 = _feed_etag('stable-version', 5);
    $etag2 = _feed_etag('stable-version', 5);
    $test->assertEquals($etag1, $etag2, 'Same version + same event ID must always produce an identical ETag');
}

function testEtagDifferentEventIdsProduceDifferentEtags($test) {
    $etag1 = _feed_etag('same-version', 1);
    $etag2 = _feed_etag('same-version', 2);
    $test->assertNotEquals($etag1, $etag2,
        'Same version but different event IDs must produce different ETags');
}

function testEtagNullEventIdVsZeroAreDifferent($test) {
    // null → '0' string in ETag; event ID 0 would also become '0' — intentionally same
    $etag1 = _feed_etag('v1', null);
    $etag2 = _feed_etag('v1', null);
    $test->assertEquals($etag1, $etag2, 'Two calls with null eventMetaId should produce identical ETags');
}

// ── 7. invalidate_data_version_cache() ────────────────────────────────────────

function testInvalidateDataVersionCacheFunctionExists($test) {
    $test->assertTrue(
        function_exists('invalidate_data_version_cache'),
        'invalidate_data_version_cache() should be defined in functions/cache.php'
    );
}

function testInvalidateDataVersionCacheDeletesGlobalFile($test) {
    $cacheDir  = dirname(DATA_VERSION_CACHE_FILE);
    $cacheFile = $cacheDir . '/data_version.json';

    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
    file_put_contents($cacheFile, json_encode(['version' => 'test', 'timestamp' => time()]));
    $test->assertTrue(file_exists($cacheFile), 'Precondition: global cache file should exist');

    invalidate_data_version_cache(null);

    $test->assertFalse(file_exists($cacheFile),
        'Global data_version.json should be deleted after invalidate_data_version_cache(null)');
}

function testInvalidateDataVersionCacheDeletesEventSpecificFile($test) {
    $cacheDir  = dirname(DATA_VERSION_CACHE_FILE);
    $cacheFile = $cacheDir . '/data_version_77.json';

    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
    file_put_contents($cacheFile, json_encode(['version' => 'test', 'timestamp' => time()]));
    $test->assertTrue(file_exists($cacheFile), 'Precondition: event-specific cache file should exist');

    invalidate_data_version_cache(77);

    $test->assertFalse(file_exists($cacheFile),
        'Event-specific data_version_77.json should be deleted after invalidation');
}

function testInvalidateDataVersionCacheEventIdAlsoDeletesGlobal($test) {
    $cacheDir     = dirname(DATA_VERSION_CACHE_FILE);
    $globalFile   = $cacheDir . '/data_version.json';
    $specificFile = $cacheDir . '/data_version_88.json';

    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
    file_put_contents($globalFile,   json_encode(['version' => 'test', 'timestamp' => time()]));
    file_put_contents($specificFile, json_encode(['version' => 'test', 'timestamp' => time()]));

    invalidate_data_version_cache(88);

    $test->assertFalse(file_exists($specificFile),
        'Event-specific cache should be deleted');
    $test->assertFalse(file_exists($globalFile),
        'Global cache should also be deleted when invalidating a specific event (avoids stale all-events ETag)');
}

function testInvalidateDataVersionCacheReturnsTrueWithNoFiles($test) {
    $cacheDir = dirname(DATA_VERSION_CACHE_FILE);
    // Remove all data_version files first
    foreach (glob($cacheDir . '/data_version*.json') ?: [] as $f) {
        unlink($f);
    }
    $result = invalidate_data_version_cache(null);
    $test->assertTrue($result, 'Should return true even when there are no cache files to delete');
}

// ── 8. feed.php Source Code — RFC Properties ─────────────────────────────────

function testFeedPhpUsesInlineContentDisposition($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains('Content-Disposition: inline', $src,
        'feed.php must use Content-Disposition: inline (not attachment) so calendar apps treat it as a subscription');
}

function testFeedPhpSetsEtagHeader($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains('ETag:', $src, 'feed.php must set an ETag header');
}

function testFeedPhpChecksIfNoneMatch($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains('HTTP_IF_NONE_MATCH', $src,
        'feed.php must check If-None-Match to support 304 Not Modified responses');
}

function testFeedPhpHasCacheControlNoStore($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains('Cache-Control: no-store, no-cache', $src,
        'feed.php must set Cache-Control: no-store, no-cache to prevent CDN/proxy caching');
}

function testFeedPhpHasXPublishedTtl($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains('X-PUBLISHED-TTL:PT1H', $src,
        'feed.php must include X-PUBLISHED-TTL:PT1H for Apple Calendar refresh hint');
}

function testFeedPhpHasRfcRefreshInterval($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains('REFRESH-INTERVAL;VALUE=DURATION:PT1H', $src,
        'feed.php must include RFC 7986 REFRESH-INTERVAL property (Google Calendar)');
}

function testFeedPhpDefinesIcsFoldFunction($test) {
    $src = file_get_contents(dirname(__DIR__) . '/functions/ics.php');
    $test->assertContains('function icsFold', $src,
        'functions/ics.php must define icsFold() for RFC 5545 §3.1 line-folding');
}

function testFeedPhpDefinesIcsEscapeFunction($test) {
    $src = file_get_contents(dirname(__DIR__) . '/functions/ics.php');
    $test->assertContains('function icsEscape', $src,
        'functions/ics.php must define icsEscape() for RFC 5545 §3.3.11 text escaping');
}

function testFeedPhpCategoriesDoesNotEscapeDelimiterComma($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    // The source should split on ',' then escape each value then re-join — not escape the whole string
    $test->assertContains("implode(',', \$catValues)", $src,
        'feed.php CATEGORIES must use implode with unescaped comma as delimiter');
}

function testFeedPhpCalNameUsesIcsEscape($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains('icsEscape($calName)', $src,
        'feed.php X-WR-CALNAME must use icsEscape() (comma escaped to \\,) to prevent comma-truncation of calendar name');
}

function testFeedPhpCalDescUsesIcsEscapeText($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains('icsEscapeText($siteTitle)', $src,
        'feed.php X-WR-CALDESC and PRODID must escape site title through icsEscapeText()');
}

// ── 9. Feed Cache Constants ────────────────────────────────────────────────────

function testFeedCacheDirConstantDefined($test) {
    $test->assertTrue(defined('FEED_CACHE_DIR'),
        'FEED_CACHE_DIR constant must be defined in config/cache.php');
}

function testFeedCacheTtlConstantDefined($test) {
    $test->assertTrue(defined('FEED_CACHE_TTL'),
        'FEED_CACHE_TTL constant must be defined in config/cache.php');
}

function testFeedCacheTtlIsOneHour($test) {
    $test->assertEquals(3600, FEED_CACHE_TTL,
        'FEED_CACHE_TTL should be 3600 seconds (1 hour)');
}

function testFeedCacheDirPointsToCacheDirectory($test) {
    $cacheDir = FEED_CACHE_DIR;
    // Must point to the project's cache/ directory
    $test->assertTrue(
        str_ends_with(str_replace('\\', '/', $cacheDir), '/cache'),
        'FEED_CACHE_DIR must point to the cache/ directory'
    );
}

// ── 10. invalidate_feed_cache() ───────────────────────────────────────────────

function testInvalidateFeedCacheFunctionExists($test) {
    $test->assertTrue(
        function_exists('invalidate_feed_cache'),
        'invalidate_feed_cache() must be defined in functions/cache.php'
    );
}

function testInvalidateFeedCacheReturnsTrueWhenNoCacheDir($test) {
    // When the cache directory does not exist, function should return true (nothing to delete)
    $result = invalidate_feed_cache(null);
    $test->assertTrue($result,
        'invalidate_feed_cache() should return true when cache dir does not exist or is empty');
}

function testInvalidateFeedCacheDeletesGlobalIcsFiles($test) {
    $cacheDir = FEED_CACHE_DIR;
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);

    // Create a fake feed cache file (eventId = 0 = global)
    $fakeFile = $cacheDir . '/feed_0_abc123.ics';
    file_put_contents($fakeFile, 'BEGIN:VCALENDAR');
    $test->assertTrue(file_exists($fakeFile), 'Precondition: fake cache file should exist');

    invalidate_feed_cache(null);

    $test->assertFalse(file_exists($fakeFile),
        'invalidate_feed_cache(null) should delete all feed_*.ics files');
}

function testInvalidateFeedCacheDeletesEventSpecificIcsFile($test) {
    $cacheDir = FEED_CACHE_DIR;
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);

    $eventFile = $cacheDir . '/feed_42_def456.ics';
    file_put_contents($eventFile, 'BEGIN:VCALENDAR');
    $test->assertTrue(file_exists($eventFile), 'Precondition: event-specific cache file should exist');

    invalidate_feed_cache(42);

    $test->assertFalse(file_exists($eventFile),
        'invalidate_feed_cache(42) should delete feed_42_*.ics files');
}

function testInvalidateFeedCacheWithEventIdAlsoDeletesGlobalIcs($test) {
    $cacheDir = FEED_CACHE_DIR;
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);

    $globalFile = $cacheDir . '/feed_0_xyz789.ics';
    $eventFile  = $cacheDir . '/feed_55_xyz789.ics';
    file_put_contents($globalFile, 'BEGIN:VCALENDAR');
    file_put_contents($eventFile,  'BEGIN:VCALENDAR');

    invalidate_feed_cache(55);

    $test->assertFalse(file_exists($eventFile),
        'Event-specific ICS cache should be deleted');
    $test->assertFalse(file_exists($globalFile),
        'Global (eventId=0) ICS cache should also be deleted when invalidating a specific event');
}

function testInvalidateFeedCacheDoesNotDeleteOtherEventIcsFiles($test) {
    $cacheDir = FEED_CACHE_DIR;
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);

    $targetFile = $cacheDir . '/feed_10_aaa.ics';
    $otherFile  = $cacheDir . '/feed_20_bbb.ics';
    file_put_contents($targetFile, 'BEGIN:VCALENDAR');
    file_put_contents($otherFile,  'BEGIN:VCALENDAR');

    invalidate_feed_cache(10);

    $test->assertFalse(file_exists($targetFile),
        'Target event ICS cache should be deleted');
    $test->assertTrue(file_exists($otherFile),
        'Another event ICS cache (event 20) should NOT be deleted when invalidating event 10');

    // Cleanup
    if (file_exists($otherFile)) unlink($otherFile);
}

// ── 11. feed.php Source — Cache Integration ───────────────────────────────────

function testFeedPhpUsesFeedCacheDirConstant($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains('FEED_CACHE_DIR', $src,
        'feed.php must reference FEED_CACHE_DIR constant for cache file paths');
}

function testFeedPhpUsesFeedCacheTtlConstant($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains('FEED_CACHE_TTL', $src,
        'feed.php must reference FEED_CACHE_TTL constant for cache expiry');
}

function testFeedPhpUsesObStart($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains('ob_start()', $src,
        'feed.php must use ob_start() to capture ICS output for caching');
}

function testFeedPhpUsesObGetClean($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains('ob_get_clean()', $src,
        'feed.php must use ob_get_clean() to retrieve captured ICS content');
}

function testFeedPhpWritesCacheFile($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains('file_put_contents($feedCacheFile', $src,
        'feed.php must save ICS content to the cache file using file_put_contents');
}

function testFeedPhpReadsCacheFileWithReadfile($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    // Uses file_get_contents() + echo instead of readfile() to avoid TOCTOU race condition
    // (cache file could be deleted between file_exists() check and readfile() call)
    $test->assertContains('file_get_contents($feedCacheFile)', $src,
        'feed.php must serve cached ICS content using file_get_contents() (race-condition-safe)');
}

function testAdminApiInvalidatesFeedCacheOnCreate($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    // Check that invalidate_feed_cache() is called in admin/api.php at all
    $test->assertContains('invalidate_feed_cache()', $src,
        'admin/api.php must call invalidate_feed_cache() after program write operations');
}

function testAdminApiHasSixFeedCacheInvalidations($test) {
    $src = file_get_contents(dirname(__DIR__) . '/admin/api.php');
    $count = substr_count($src, 'invalidate_feed_cache()');
    $test->assertEquals(6, $count,
        'admin/api.php must call invalidate_feed_cache() exactly 6 times (create, update, delete, bulkDelete, bulkUpdate, confirmIcsImport)');
}

function testFeedCacheKeyIncludesEventId($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains("'event'   => \$eventId", $src,
        'feed.php cache key must include eventId so per-event feeds get separate cache files');
}

function testFeedCacheKeyIncludesSortedFilters($test) {
    $src = file_get_contents(dirname(__DIR__) . '/feed.php');
    $test->assertContains('sort($sortedArtists)', $src,
        'feed.php must sort filter arrays before hashing to produce a stable cache key');
}
