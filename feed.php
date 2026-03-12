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
 *   - All property values escape: backslash, semicolon, newline
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
$filterArtists = get_sanitized_array_param('artist', 200, 50);
$filterVenues  = get_sanitized_array_param('venue',  200, 50);
$filterTypes   = get_sanitized_array_param('type',   200, 50);

// ── Feed cache check ──────────────────────────────────────────────────────────
// Cache key encodes the event + all active filters so each unique feed URL
// gets its own cache file. Filters are sorted for a stable key regardless
// of query-string order.
$sortedArtists = $filterArtists; sort($sortedArtists);
$sortedVenues  = $filterVenues;  sort($sortedVenues);
$sortedTypes   = $filterTypes;   sort($sortedTypes);

$feedCacheKey  = md5(json_encode([
    'event'   => $eventId,
    'artists' => $sortedArtists,
    'venues'  => $sortedVenues,
    'types'   => $sortedTypes,
]));
$feedCacheFile = FEED_CACHE_DIR . '/feed_' . ($eventId ?? '0') . '_' . $feedCacheKey . '.ics';

if (file_exists($feedCacheFile) && (time() - filemtime($feedCacheFile)) < FEED_CACHE_TTL) {
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: inline; filename="feed.ics"');
    header('Cache-Control: no-store, no-cache');
    header('Pragma: no-cache');
    header('ETag: ' . $etag);
    readfile($feedCacheFile);
    exit;
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
$calName    = ($eventName ? $eventName . ' - ' : '') . $siteTitle;

icsLine("BEGIN:VCALENDAR");
icsLine("VERSION:2.0");
icsLine("PRODID:-//" . $siteTitle . "//NONSGML v1.0//EN");
icsLine("CALSCALE:GREGORIAN");
icsLine("METHOD:PUBLISH");
icsLine("X-WR-CALNAME:" . $calName);
icsLine("X-WR-TIMEZONE:Asia/Bangkok");
icsLine("X-WR-CALDESC:" . $siteTitle . " live calendar feed ($eventCount programs)");
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
    icsLine("SUMMARY:" . icsEscape($event['title']));

    if (!empty($event['location'])) {
        icsLine("LOCATION:" . icsEscape($event['location']));
    }

    if (!empty($event['description'])) {
        icsLine("DESCRIPTION:" . icsEscape($event['description']));
    }

    if (!empty($event['stream_url'])) {
        icsLine("URL:" . icsEscape($event['stream_url']));
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

/**
 * Output one RFC 5545-compliant ICS line with CRLF and line folding.
 *
 * RFC 5545 §3.1: lines SHOULD NOT exceed 75 octets (excluding CRLF).
 * Longer lines are folded by inserting CRLF + SPACE (continuation).
 * Folding respects UTF-8 multi-byte character boundaries.
 */
function icsLine(string $line): void {
    echo icsFold($line) . "\r\n";
}

function icsFold(string $line): string {
    if (strlen($line) <= 75) {
        return $line;
    }

    $folded    = '';
    $lineBytes = 0;
    $i         = 0;
    $len       = strlen($line);

    while ($i < $len) {
        // Determine UTF-8 character byte length from leading byte
        $byte = ord($line[$i]);
        if ($byte < 0x80)      { $charLen = 1; }
        elseif ($byte < 0xE0)  { $charLen = 2; }
        elseif ($byte < 0xF0)  { $charLen = 3; }
        else                   { $charLen = 4; }

        // Fold before this character if it would exceed 75 bytes
        if ($lineBytes + $charLen > 75 && $lineBytes > 0) {
            $folded    .= "\r\n ";  // CRLF + SPACE continuation
            $lineBytes  = 1;        // the leading space is 1 byte
        }

        $folded    .= substr($line, $i, $charLen);
        $lineBytes += $charLen;
        $i         += $charLen;
    }

    return $folded;
}

/**
 * Escape special characters in an ICS text value per RFC 5545 §3.3.11.
 * Escapes: backslash, semicolon, comma, newline.
 *
 * Used for: SUMMARY, LOCATION, DESCRIPTION, and individual CATEGORIES values.
 * For CATEGORIES the caller splits on ',' first so that the delimiter commas
 * are never passed into this function.
 */
function icsEscape(string $value): string {
    $value = str_replace('\\', '\\\\', $value); // backslash must be first
    $value = str_replace(';',  '\\;',  $value);
    $value = str_replace(',',  '\\,',  $value);
    $value = str_replace("\n", '\\n',  $value);
    $value = str_replace("\r", '',     $value);
    return $value;
}
