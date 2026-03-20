<?php
/**
 * Live ICS Subscription Feed
 * Idol Stage Timetable
 *
 * Provides a live calendar subscription URL (webcal:// / https://).
 * Calendar apps poll this endpoint periodically and automatically
 * sync new/updated events — no manual re-export needed.
 *
 * Supports:
 *   GET /feed                        - all events (default event)
 *   GET /event/{slug}/feed           - specific event (via .htaccess)
 *   GET /feed?artist[]=X&venue[]=Y   - filtered feed
 *
 * RFC 5545 compliance:
 *   - Lines exceeding 75 octets are folded (CRLF + SPACE continuation)
 *   - CATEGORIES uses comma-delimiter correctly (not escaped)
 *   - SUMMARY/LOCATION/DESCRIPTION: backslash, semicolon, newline escaped;
 *     commas left unescaped (single-value TEXT — comma is not a delimiter)
 *   - CATEGORIES individual values: all special chars including comma escaped
 *
 * Cache strategy:
 *   ETag based on data version (invalidated immediately when admin
 *   creates/updates/deletes programs).
 *   Calendar apps always re-validate (Cache-Control: no-cache) and
 *   send If-None-Match on every request → 304 Not Modified
 *   when no data has changed, or 200 with new content when it has.
 *
 * Client compatibility:
 *   - Apple Calendar / iOS: webcal:// or https://
 *   - Google Calendar: https:// ("Add calendar > From URL")
 *   - Microsoft Outlook: https:// ("Add calendar > Subscribe from web")
 *   - Thunderbird: webcal:// or https://
 */

require_once 'config.php';
require_once 'IcsParser.php';
require_once 'functions/ics.php';

header('X-Content-Type-Options: nosniff');

// Multi-event support
$eventSlug   = get_current_event_slug();
$eventMeta   = get_event_by_slug($eventSlug);

// If a specific slug was requested but the event doesn't exist or is inactive,
// return 404 instead of silently falling back to all programs.
if ($eventSlug !== DEFAULT_EVENT_SLUG && $eventMeta === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Event not found or inactive.";
    exit;
}

$eventId     = $eventMeta ? intval($eventMeta['id']) : null;
$eventName   = $eventMeta ? $eventMeta['name'] : 'Idol Stage Event';

// ── Artist feed mode ───────────────────────────────────────────────────────────
// When artist_id is provided (/artist/{id}/feed), ignore event scoping and
// filter all programs by the artist's name + all variant names.
// ?group=1 switches to the artist's group programs (uses group name + variants).
$artistFeedId    = filter_var($_GET['artist_id'] ?? 0, FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]);
$artistFeedName  = null;
$artistFeedGroup = !empty($_GET['group']) && $_GET['group'] === '1';

if ($artistFeedId) {
    try {
        $dbAF = new PDO('sqlite:' . DB_PATH);
        $dbAF->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmtAF = $dbAF->prepare("SELECT id, name, group_id FROM artists WHERE id = ?");
        $stmtAF->execute([$artistFeedId]);
        $artistFeedRow = $stmtAF->fetch(PDO::FETCH_ASSOC);
        $stmtAF->closeCursor();
        $stmtAF = null;

        if (!$artistFeedRow) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Artist not found.";
            exit;
        }

        if ($artistFeedGroup && $artistFeedRow['group_id']) {
            // Group feed: filter by the group's name + group's variants
            $targetId = intval($artistFeedRow['group_id']);
            $stmtG = $dbAF->prepare("SELECT name FROM artists WHERE id = ?");
            $stmtG->execute([$targetId]);
            $groupRow = $stmtG->fetch(PDO::FETCH_ASSOC);
            $stmtG->closeCursor();
            $stmtG = null;
            $artistFeedName = $groupRow ? $groupRow['name'] : $artistFeedRow['name'];
        } else {
            // Own programs feed (default)
            $targetId       = $artistFeedId;
            $artistFeedName = $artistFeedRow['name'];
        }

        // Collect variant names for the target (artist or group)
        $stmtAFV = $dbAF->prepare("SELECT variant FROM artist_variants WHERE artist_id = ?");
        $stmtAFV->execute([$targetId]);
        $artistFeedVariants = array_column($stmtAFV->fetchAll(PDO::FETCH_ASSOC), 'variant');
        $stmtAFV->closeCursor();
        $stmtAFV = null;
        $dbAF = null;
    } catch (PDOException $e) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Database unavailable.";
        exit;
    }

    // Override: fetch all events (no event restriction), filter by target names
    $eventId       = null;
    $eventName     = $artistFeedName;
    $filterArtists = array_merge([$artistFeedName], $artistFeedVariants);
}

// ── ETag caching ──────────────────────────────────────────────────────────────
$dataVersion = get_data_version($eventId);
$etag = '"feed-' . md5($dataVersion . '-' . ($eventId ?? '0')) . '"';

if (
    isset($_SERVER['HTTP_IF_NONE_MATCH']) &&
    $_SERVER['HTTP_IF_NONE_MATCH'] === $etag
) {
    header('HTTP/1.1 304 Not Modified');
    header('ETag: ' . $etag);
    header('Cache-Control: no-store, no-cache');
    exit;
}

// ── Filter params ─────────────────────────────────────────────────────────────
// In artist feed mode $filterArtists is already set above — do not overwrite it.
if (!$artistFeedId) {
    $filterArtists = get_sanitized_array_param('artist', 200, 50);
}
$filterVenues = get_sanitized_array_param('venue',  200, 50);
$filterTypes  = get_sanitized_array_param('type',   200, 50);

// ── Feed cache check ──────────────────────────────────────────────────────────
// Cache key encodes the event + all active filters so each unique feed URL
// gets its own cache file. Filters are sorted for a stable key regardless
// of query-string order.
$sortedArtists = $filterArtists; sort($sortedArtists);
$sortedVenues  = $filterVenues;  sort($sortedVenues);
$sortedTypes   = $filterTypes;   sort($sortedTypes);

if ($artistFeedId) {
    // Artist feed: cache key encodes artist id + group flag
    $feedCacheKey  = md5($artistFeedId . ($artistFeedGroup ? '_group' : '_own'));
    $feedCacheFile = FEED_CACHE_DIR . '/feed_artist_' . $artistFeedId . '_' . $feedCacheKey . '.ics';
} else {
    $feedCacheKey  = md5(json_encode([
        'event'   => $eventId,
        'artists' => $sortedArtists,
        'venues'  => $sortedVenues,
        'types'   => $sortedTypes,
    ]));
    $feedCacheFile = FEED_CACHE_DIR . '/feed_' . ($eventId ?? '0') . '_' . $feedCacheKey . '.ics';
}

if (file_exists($feedCacheFile) && (time() - filemtime($feedCacheFile)) < FEED_CACHE_TTL) {
    $cachedContent = @file_get_contents($feedCacheFile);
    if ($cachedContent !== false) {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="feed.ics"');
        header('Cache-Control: no-store, no-cache');
        header('Pragma: no-cache');
        header('ETag: ' . $etag);
        echo $cachedContent;
        exit;
    }
    // Cache file was deleted between file_exists() and read — fall through to regenerate
}

// ── Fetch & filter programs ───────────────────────────────────────────────────
$parser    = new IcsParser('ics', true, 'data/calendar.db', $eventId);
$allEvents = $parser->getAllEvents();

$filteredEvents = array_filter(
    $allEvents,
    function ($event) use ($filterArtists, $filterVenues, $filterTypes) {
        $artistMatch = empty($filterArtists);
        if (!$artistMatch && isset($event['categories'])) {
            $cats = array_map('trim', explode(',', $event['categories']));
            foreach ($filterArtists as $fa) {
                if (in_array($fa, $cats)) { $artistMatch = true; break; }
            }
        }
        $venueMatch = empty($filterVenues) ||
            (isset($event['location']) && in_array($event['location'], $filterVenues));
        $typeMatch  = empty($filterTypes) ||
            in_array($event['program_type'] ?? '', $filterTypes);

        return $artistMatch && $venueMatch && $typeMatch;
    }
);

// ── Generate ICS content (captured for caching) ───────────────────────────────
ob_start();

$siteTitle  = get_site_title();
$eventCount = count($filteredEvents);
$calName    = $artistFeedId
    ? $artistFeedName . ' – ' . $siteTitle
    : (($eventName ? $eventName . ' - ' : '') . $siteTitle);

icsLine("BEGIN:VCALENDAR");
icsLine("VERSION:2.0");
icsLine("PRODID:-//" . icsEscapeText($siteTitle) . "//NONSGML v1.0//EN");
icsLine("CALSCALE:GREGORIAN");
icsLine("METHOD:PUBLISH");
icsLine("X-WR-CALNAME:" . icsEscape($calName));
icsLine("X-WR-TIMEZONE:Asia/Bangkok");
icsLine("X-WR-CALDESC:" . icsEscapeText($siteTitle) . " live calendar feed ($eventCount programs)");
icsLine("X-PUBLISHED-TTL:PT1H");              // Apple Calendar refresh hint
icsLine("REFRESH-INTERVAL;VALUE=DURATION:PT1H"); // RFC 7986 (Google Calendar)

foreach ($filteredEvents as $event) {
    $startTime    = gmdate('Ymd\THis\Z', strtotime($event['start']));
    $endTime      = gmdate('Ymd\THis\Z', strtotime($event['end']));
    $updatedTs    = !empty($event['updated_at']) ? strtotime($event['updated_at']) : time();
    $dtstamp      = gmdate('Ymd\THis\Z', $updatedTs);
    $uid = isset($event['uid'])
        ? $event['uid']
        : md5($event['title'] . $event['start']) . '@stageidol.local';

    icsLine("BEGIN:VEVENT");
    icsLine("UID:" . $uid);
    icsLine("DTSTAMP:" . $dtstamp);
    icsLine("LAST-MODIFIED:" . $dtstamp);
    icsLine("DTSTART:" . $startTime);
    icsLine("DTEND:" . $endTime);
    icsLine("SUMMARY:" . icsEscapeText($event['title']));

    if (!empty($event['location'])) {
        icsLine("LOCATION:" . icsEscapeText($event['location']));
    }

    if (!empty($event['description'])) {
        icsLine("DESCRIPTION:" . icsEscapeText($event['description']));
    }

    if (!empty($event['stream_url']) && preg_match('/^https?:\/\//i', $event['stream_url'])) {
        icsLine("URL:" . icsEscapeText($event['stream_url']));
    }

    // CATEGORIES: RFC 5545 uses comma as VALUE delimiter — do NOT escape delimiter commas.
    // Each individual category value has its own special chars escaped.
    $catValues = [];
    if (!empty($event['categories'])) {
        foreach (array_map('trim', explode(',', $event['categories'])) as $cat) {
            if ($cat !== '') $catValues[] = icsEscape($cat);
        }
    }
    if (!empty($event['program_type'])) {
        $catValues[] = icsEscape($event['program_type']);
    }
    if (!empty($catValues)) {
        icsLine("CATEGORIES:" . implode(',', $catValues));
    }

    icsLine("STATUS:CONFIRMED");
    icsLine("SEQUENCE:0");
    icsLine("BEGIN:VALARM");
    icsLine("TRIGGER:-PT15M");
    icsLine("ACTION:DISPLAY");
    icsLine("DESCRIPTION:Reminder");
    icsLine("END:VALARM");
    icsLine("END:VEVENT");
}

icsLine("END:VCALENDAR");

$icsContent = ob_get_clean();

// ── Save to cache ─────────────────────────────────────────────────────────────
if (!is_dir(FEED_CACHE_DIR)) {
    mkdir(FEED_CACHE_DIR, 0755, true);
}
file_put_contents($feedCacheFile, $icsContent);

// ── Response headers + output ─────────────────────────────────────────────────
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="feed.ics"');
header('Cache-Control: no-store, no-cache');
header('Pragma: no-cache');
header('ETag: ' . $etag);
echo $icsContent;

// ICS helper functions (icsLine, icsFold, icsEscape, icsEscapeText) are in functions/ics.php
