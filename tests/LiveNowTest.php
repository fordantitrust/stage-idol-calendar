<?php
/**
 * Live Now Tests (v16.1.0)
 *
 * Covers the homepage "Live Now" strip:
 *  - index.php live candidate query (active events, today ±1 day window, COALESCE timezone)
 *  - ISO-8601-with-offset emission via DateTime->format('c')
 *  - listing-cache integration (live_programs save + read-back)
 *  - #liveNowStrip markup + data-i18n headings
 *  - inline JS: window.LIVE_PROGRAMS, renderLiveNow(), setInterval, appLangChange,
 *    live/soon classification (SOON_MS), cross-TZ local annotation, stream watch button
 *  - translations.js live.* keys (TH/EN/JA)
 *  - styles/index.css live-now classes + pulse keyframes
 */

require_once __DIR__ . '/../config.php';

// ── Helpers ────────────────────────────────────────────────────────────────
function _ln_src(string $rel): string {
    $p = dirname(__DIR__) . '/' . $rel;
    return file_exists($p) ? (string)file_get_contents($p) : '';
}

// ── 1. PHP query + emission ──────────────────────────────────────────────────
function testLiveNowCandidateQuery($test) {
    $src = _ln_src('index.php');
    $test->assertNotEmpty($src, 'index.php should be readable');
    $test->assertContains("DATE('now','-1 day')", $src, 'live query should use today-1 lower bound');
    $test->assertContains("DATE('now','+1 day')", $src, 'live query should use today+1 upper bound');
    $test->assertContains('COALESCE(e.timezone, :defTz)', $src, 'live query should fall back to default timezone');
    $test->assertContains('e.is_active = 1', $src, 'live query should only join active events');
}

function testLiveNowExcludesDefaultSlug($test) {
    $src = _ln_src('index.php');
    // The live query binds DEFAULT_EVENT_SLUG to exclude the container event
    $test->assertContains(':defSlug', $src, 'live query should bind a default-slug exclusion param');
    $test->assertContains('e.slug != :defSlug', $src, 'live query should exclude the default slug event');
}

function testLiveNowIsoEmission($test) {
    $src = _ln_src('index.php');
    $test->assertContains("'start_iso'", $src, '$livePrograms rows should carry start_iso');
    $test->assertContains("'end_iso'", $src, '$livePrograms rows should carry end_iso');
    $test->assertContains("->format('c')", $src, 'times should be emitted as ISO-8601 with offset');
    $test->assertContains("'event_tz'", $src, '$livePrograms rows should carry event_tz for cross-TZ annotation');
    $test->assertContains("event_url('index.php'", $src, 'live rows should link to the event timetable page');
}

function testLiveNowIsoFormatProducesOffset($test) {
    // Validates the exact emission pattern used in index.php for a non-Bangkok TZ
    $iso = (new DateTime('2026-06-01 18:20:00', new DateTimeZone('Asia/Taipei')))->format('c');
    $test->assertContains('+08:00', $iso, 'Asia/Taipei instant should carry a +08:00 offset');
    $test->assertContains('2026-06-01T18:20:00', $iso, 'event-local wall time should be preserved in the ISO string');
}

// ── 2. Listing cache integration ─────────────────────────────────────────────
function testLiveNowCacheSaveKey($test) {
    $src = _ln_src('index.php');
    $test->assertContains("'live_programs'       => \$livePrograms", $src, 'listing cache should persist live_programs');
}

function testLiveNowCacheReadBack($test) {
    $src = _ln_src('index.php');
    $test->assertContains("\$_listingCache['live_programs']", $src, 'cache-hit branch should restore live_programs');
}

// ── 3. HTML markup ───────────────────────────────────────────────────────────
function testLiveNowStripMarkup($test) {
    $src = _ln_src('index.php');
    $test->assertContains('id="liveNowStrip"', $src, '#liveNowStrip container should exist');
    $test->assertContains('id="liveNowCurrentRows"', $src, 'current rows container should exist');
    $test->assertContains('id="liveNowSoonRows"', $src, 'soon rows container should exist');
    $test->assertContains('data-i18n="live.nowTitle"', $src, 'current heading should be translatable');
    $test->assertContains('data-i18n="live.soonTitle"', $src, 'soon heading should be translatable');
}

function testLiveNowStripStartsHidden($test) {
    $src = _ln_src('index.php');
    // The strip and groups start hidden; JS reveals them only when populated
    $test->assertContains('class="live-now-strip" hidden', $src, 'strip should start hidden to avoid an empty box');
}

// ── 4. Inline JS behaviour ───────────────────────────────────────────────────
function testLiveNowJsGlobalAndRenderer($test) {
    $src = _ln_src('index.php');
    $test->assertContains('window.LIVE_PROGRAMS', $src, 'programs should be injected as window.LIVE_PROGRAMS');
    $test->assertContains('function renderLiveNow', $src, 'renderLiveNow() should exist');
    $test->assertContains('setInterval(renderLiveNow', $src, 'renderLiveNow should auto-refresh on an interval');
    $test->assertContains("addEventListener('appLangChange', renderLiveNow", $src, 'strip should re-render on language change');
}

function testLiveNowJsClassification($test) {
    $src = _ln_src('index.php');
    $test->assertContains('SOON_MS', $src, 'a starting-soon window constant should exist');
    $test->assertContains('60 * 60 * 1000', $src, 'starting-soon window should be 60 minutes');
    // live = started and not ended; soon = within SOON_MS before start
    $test->assertContains('s <= now', $src, 'live classification should test start <= now');
}

function testLiveNowJsCrossTzAnnotation($test) {
    $src = _ln_src('index.php');
    $test->assertContains('live-now-time-local', $src, 'cross-TZ local time annotation span should exist');
    $test->assertContains("_liveT('tz.localTime')", $src, 'annotation should reuse the tz.localTime key');
    $test->assertContains('resolvedOptions().timeZone', $src, 'should resolve the browser timezone for comparison');
}

function testLiveNowJsStreamWatchButton($test) {
    $src = _ln_src('index.php');
    $test->assertContains('live-now-watch', $src, 'a watch-live button should be rendered');
    $test->assertContains('p.stream_url', $src, 'watch button should be gated on stream_url');
    $test->assertContains("_liveT('live.watch')", $src, 'watch button label should be translatable');
}

// ── 5. Translations ──────────────────────────────────────────────────────────
function testLiveNowTranslationKeys($test) {
    $src = _ln_src('js/translations.js');
    foreach (['live.nowTitle', 'live.soonTitle', 'live.timeLeft', 'live.startsIn', 'live.watch'] as $key) {
        // Each key must appear in all three language blocks (TH/EN/JA)
        $count = substr_count($src, "'{$key}'");
        $test->assertGreaterThanOrEqual(3, $count, "translation key {$key} should exist in TH/EN/JA");
    }
}

function testLiveNowTranslationPlaceholders($test) {
    $src = _ln_src('js/translations.js');
    // Countdown strings must keep the {n} placeholder that renderLiveNow() substitutes
    $test->assertContains('{n}', $src, 'countdown translations should contain the {n} placeholder');
}

// ── 6. CSS ───────────────────────────────────────────────────────────────────
function testLiveNowCss($test) {
    $css = _ln_src('styles/index.css');
    $test->assertContains('.live-now-strip', $css, 'live-now-strip CSS should exist');
    $test->assertContains('.live-now-row', $css, 'live-now-row CSS should exist');
    $test->assertContains('@keyframes liveNowPulse', $css, 'pulsing badge animation should exist');
    $test->assertContains('.live-now-watch', $css, 'watch button CSS should exist');
    $test->assertContains('var(--sakura', $css, 'live-now styles should use theme CSS variables');
}
