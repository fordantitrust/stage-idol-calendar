<?php
/**
 * Personal Upcoming Programs ICS Feed
 * Idol Stage Timetable
 *
 * Provides a live calendar subscription for a user's favorited artists.
 * URL: /my/{slug}/feed  (via .htaccess rewrite)
 *
 * Shows all upcoming programs from the user's followed artists across
 * all active events.  Summary is prefixed with "[Event Name]" so calendar
 * apps can distinguish which event each program belongs to.
 *
 * Cache strategy: static .ics file co-located with the favorites JSON in the
 * same shard directory (cache/favorites/{shard}/{token}.ics), TTL 1 hour.
 * GC in fav_cleanup_expired() removes both .json and .ics together.
 * ETag combines the token + global data_version + sorted artist IDs so it
 * changes immediately when admin updates programs or the user changes their
 * followed artists.
 */

require_once 'config.php';
require_once 'functions/favorites.php';
require_once 'functions/ics.php';

header('X-Content-Type-Options: nosniff');

if (FAVORITES_HMAC_SECRET === 'REPLACE_WITH_GENERATED_SECRET') {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Favorites not configured.';
    exit;
}

// ── Validate slug ─────────────────────────────────────────────────────────────
$rawSlug = $_GET['slug'] ?? '';
$parsed  = $rawSlug ? fav_parse_slug($rawSlug) : null;

if (!$parsed) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid or expired link.';
    exit;
}

$token   = $parsed['token'];
$favData = fav_read($token);

if (!$favData) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Favorites not found or expired.';
    exit;
}

fav_touch($favData);

$artistIds = $favData['artists'] ?? [];

// ── ETag ──────────────────────────────────────────────────────────────────────
// Changes when: any program is updated (dataVersion) OR artist list changes.
$dataVersion   = get_data_version(null);
$sortedArtists = $artistIds;
sort($sortedArtists);
$etag = '"fav-feed-' . md5($token . '-' . $dataVersion . '-' . implode(',', $sortedArtists)) . '"';

if (
    isset($_SERVER['HTTP_IF_NONE_MATCH']) &&
    $_SERVER['HTTP_IF_NONE_MATCH'] === $etag
) {
    header('HTTP/1.1 304 Not Modified');
    header('ETag: ' . $etag);
    header('Cache-Control: no-store, no-cache');
    exit;
}

// ── Static file cache ─────────────────────────────────────────────────────────
// Co-located with the favorites JSON in the same shard directory so that GC
// can clean up both files together when the token expires.
$favShard  = substr($token, -3);
$cacheFile = FAVORITES_DIR . '/' . $favShard . '/' . $token . '.ics';

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < FEED_CACHE_TTL) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="my-upcoming.ics"');
        header('Cache-Control: no-store, no-cache');
        header('Pragma: no-cache');
        header('ETag: ' . $etag);
        echo $cached;
        exit;
    }
}

// ── Fetch programs ────────────────────────────────────────────────────────────
$programs = [];

if (!empty($artistIds)) {
    try {
        $db  = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pls = implode(',', array_fill(0, count($artistIds), '?'));

        // Expand artist IDs to include groups that followed artists belong to
        $stmtG = $db->prepare("SELECT group_id FROM artists WHERE id IN ($pls) AND group_id IS NOT NULL");
        $stmtG->execute($artistIds);
        $groupIds = array_column($stmtG->fetchAll(PDO::FETCH_ASSOC), 'group_id');
        $stmtG->closeCursor(); $stmtG = null;
        $allArtistIds = array_values(array_unique(array_merge($artistIds, array_map('intval', $groupIds))));
        $plsAll = implode(',', array_fill(0, count($allArtistIds), '?'));

        $today = date('Y-m-d');
        $stmt  = $db->prepare("
            SELECT DISTINCT p.id, p.title, p.start, p.end, p.location,
                   p.categories, p.program_type, p.stream_url, p.uid,
                   p.description, p.updated_at,
                   e.name AS event_name
            FROM programs p
            JOIN events e ON e.id = p.event_id AND e.is_active = 1
            WHERE p.id IN (
                SELECT DISTINCT pa.program_id FROM program_artists pa WHERE pa.artist_id IN ($plsAll)
            )
            AND DATE(p.start) >= :today
            ORDER BY p.start ASC
        ");
        $stmt->execute(array_merge($allArtistIds, ['today' => $today]));
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $stmt = null;
        $db   = null;
    } catch (PDOException $e) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Database unavailable.';
        exit;
    }
}

// ── Generate ICS ──────────────────────────────────────────────────────────────
ob_start();

$siteTitle = get_site_title();
$calName   = 'My Upcoming Programs – ' . $siteTitle;
$eventCount = count($programs);

icsLine("BEGIN:VCALENDAR");
icsLine("VERSION:2.0");
icsLine("PRODID:-//" . icsEscapeText($siteTitle) . "//NONSGML v1.0//EN");
icsLine("CALSCALE:GREGORIAN");
icsLine("METHOD:PUBLISH");
icsLine("X-WR-CALNAME:" . icsEscape($calName));
icsLine("X-WR-TIMEZONE:Asia/Bangkok");
icsLine("X-WR-CALDESC:" . icsEscapeText($siteTitle) . " – Personal upcoming programs feed ($eventCount programs)");
icsLine("X-PUBLISHED-TTL:PT1H");
icsLine("REFRESH-INTERVAL;VALUE=DURATION:PT1H");

foreach ($programs as $p) {
    $startTime = gmdate('Ymd\THis\Z', strtotime($p['start']));
    $endTime   = gmdate('Ymd\THis\Z', strtotime($p['end']));
    $updatedTs = !empty($p['updated_at']) ? strtotime($p['updated_at']) : time();
    $dtstamp   = gmdate('Ymd\THis\Z', $updatedTs);
    $uid = !empty($p['uid'])
        ? $p['uid']
        : md5($p['title'] . $p['start']) . '@stageidol.local';

    // Prefix event name so calendar apps can distinguish events at a glance
    $summary = (!empty($p['event_name']) ? '[' . $p['event_name'] . '] ' : '') . $p['title'];

    icsLine("BEGIN:VEVENT");
    icsLine("UID:" . $uid);
    icsLine("DTSTAMP:" . $dtstamp);
    icsLine("LAST-MODIFIED:" . $dtstamp);
    icsLine("DTSTART:" . $startTime);
    icsLine("DTEND:" . $endTime);
    icsLine("SUMMARY:" . icsEscapeText($summary));

    if (!empty($p['location'])) {
        icsLine("LOCATION:" . icsEscapeText($p['location']));
    }
    if (!empty($p['description'])) {
        icsLine("DESCRIPTION:" . icsEscapeText($p['description']));
    }
    if (!empty($p['stream_url']) && preg_match('/^https?:\/\//i', $p['stream_url'])) {
        icsLine("URL:" . icsEscapeText($p['stream_url']));
    }

    $catValues = [];
    if (!empty($p['categories'])) {
        foreach (array_map('trim', explode(',', $p['categories'])) as $cat) {
            if ($cat !== '') $catValues[] = icsEscape($cat);
        }
    }
    if (!empty($p['program_type'])) {
        $catValues[] = icsEscape($p['program_type']);
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
file_put_contents($cacheFile, $icsContent);

// ── Send response ─────────────────────────────────────────────────────────────
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="my-upcoming.ics"');
header('Cache-Control: no-store, no-cache');
header('Pragma: no-cache');
header('ETag: ' . $etag);
echo $icsContent;
