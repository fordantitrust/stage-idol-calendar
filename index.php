<?php
require_once 'config.php';
require_once 'IcsParser.php';

// Security headers
send_security_headers();

// Multi-event support
$eventSlug = get_current_event_slug();
$eventMeta = get_event_by_slug($eventSlug);

// If a specific slug was requested but the event doesn't exist or is inactive,
// show 404 instead of silently falling back to all programs.
if ($eventSlug !== DEFAULT_EVENT_SLUG && $eventMeta === null) {
    http_response_code(404);
    $siteTitle = get_site_title();
    $theme = get_site_theme();
    $basePath = get_base_path();
    ?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Not Found - <?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="stylesheet" href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/styles/common.css?v=<?php echo APP_VERSION; ?>">
</head>
<body class="theme-<?php echo htmlspecialchars($theme, ENT_QUOTES, 'UTF-8'); ?>">
<div style="text-align:center;padding:80px 20px">
    <h1 data-i18n="notFound.heading">🌸 404 – ไม่พบ Event</h1>
    <p data-i18n="notFound.desc">Event นี้ไม่มีอยู่ หรือถูกปิดใช้งานแล้ว</p>
    <a href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/" style="color:var(--sakura-dark)" data-i18n="notFound.back">← กลับหน้าหลัก</a>
</div>
<script>const BASE_PATH = <?php echo json_encode($basePath); ?>;</script>
<script src="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/js/translations.js?v=<?php echo APP_VERSION; ?>"></script>
<script>
(function () {
    var lang = 'th';
    try { lang = localStorage.getItem('lang') || 'th'; } catch (e) {}
    var t = translations[lang] || translations['th'];
    document.documentElement.lang = t.langCode || lang;
    document.querySelectorAll('[data-i18n]').forEach(function (el) {
        var k = el.getAttribute('data-i18n');
        if (t[k]) el.textContent = t[k];
    });
}());
</script>
</body>
</html>
<?php
    exit;
}

$eventId = $eventMeta ? intval($eventMeta['id']) : null;
$currentVenueMode = get_event_venue_mode($eventMeta);
// Try listing cache (stores activeEvents + listingCalData + event_covers + program_type_counts)
$_listingCache = get_query_cache('query_listing.json');
if ($_listingCache !== false) {
    $activeEvents             = $_listingCache['active_events'];
    $_listingCalDataFromCache  = $_listingCache['listing_cal_data'];
    $eventCoversCache         = $_listingCache['event_covers'] ?? [];
    $programTypeCountsCache   = $_listingCache['program_type_counts'] ?? [];
    $livePrograms             = $_listingCache['live_programs'] ?? [];
} else {
    $activeEvents             = get_all_active_events();
    $_listingCalDataFromCache  = null;
    $eventCoversCache         = null; // computed later
    $programTypeCountsCache   = null;
    $livePrograms             = null; // computed later
}
$eventName = $eventMeta ? $eventMeta['name'] : 'Idol Stage Event';
$eventTz   = get_event_timezone($eventMeta);

// Check if we should show event listing (homepage) or calendar view
// The default slug event is intentionally hidden from the listing (it is a container for un-assigned programs).
// Only show the listing when there is at least one non-default active event.
$nonDefaultEvents = array_filter($activeEvents, fn($e) => $e['slug'] !== DEFAULT_EVENT_SLUG);
$showEventListing = MULTI_EVENT_MODE && $eventSlug === DEFAULT_EVENT_SLUG && count($nonDefaultEvents) > 0;

// Only load calendar data when showing calendar view
if (!$showEventListing) {
    $queryCacheFile = 'query_event_' . ($eventId ?? '0') . '.json';
    $qcd = get_query_cache($queryCacheFile);
    if ($qcd !== false) {
        $allEvents          = $qcd['all_events'];
        $venues             = $qcd['venues'];
        $types              = $qcd['types'];
        $artists            = $qcd['artists'];
        $artistMeta         = $qcd['artist_meta'];
        $programArtistsMap  = $qcd['program_artists_map'];
        $programArtistIdMap = $qcd['program_artist_id_map'];
        $artistOtherEvents  = $qcd['artist_other_events'];
        $useArtistsTable    = $qcd['use_artists_table'];
        $eventPictures      = $qcd['event_pictures'] ?? [];
        $venueMeta          = $qcd['venue_meta'] ?? [];
    } else {
    $parser = new IcsParser('ics', true, 'data/calendar.db', $eventId);

    // ดึงข้อมูลทั้งหมด
    $allEvents = $parser->getAllEvents();
    $venues = $parser->getAllLocations();
    $types = $parser->getAllTypes();

    // ดึง artist list และ program→artists map จาก artists table (ถ้ามี)
    // Fallback ไป categories text ถ้า program_artists ยังไม่มี
    $artists       = [];
    $artistMeta    = []; // name => ['id' => int, 'event_count' => int]
    $programArtistsMap  = []; // program_id => [name, ...]
    $programArtistIdMap = []; // program_id => [['id'=>int,'name'=>string], ...]
    $artistOtherEvents  = []; // artist_id => [['id'=>int,'name'=>str,'slug'=>str], ...]
    $useArtistsTable    = false;

    try {
        $dbArtists = new PDO('sqlite:' . DB_PATH);
        $dbArtists->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $hasPATable = (bool)$dbArtists->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='program_artists'"
        )->fetch();

        if ($hasPATable) {
            $useArtistsTable = true;

            // Artist list for current event + total event_count across all events
            if ($eventId !== null) {
                $stmtA = $dbArtists->prepare("
                    SELECT a.id, a.name,
                        (SELECT COUNT(DISTINCT p2.event_id)
                         FROM program_artists pa2
                         JOIN programs p2 ON p2.id = pa2.program_id
                         WHERE pa2.artist_id = a.id) AS event_count
                    FROM artists a
                    WHERE EXISTS (
                        SELECT 1 FROM program_artists pa
                        JOIN programs p ON p.id = pa.program_id
                        WHERE pa.artist_id = a.id AND p.event_id = ?
                    )
                    ORDER BY a.name ASC
                ");
                $stmtA->execute([$eventId]);
            } else {
                $stmtA = $dbArtists->query("
                    SELECT DISTINCT a.id, a.name,
                        (SELECT COUNT(DISTINCT p2.event_id)
                         FROM program_artists pa2
                         JOIN programs p2 ON p2.id = pa2.program_id
                         WHERE pa2.artist_id = a.id) AS event_count
                    FROM artists a
                    JOIN program_artists pa ON pa.artist_id = a.id
                    ORDER BY a.name ASC
                ");
            }
            while ($rowA = $stmtA->fetch(PDO::FETCH_ASSOC)) {
                $artists[] = $rowA['name'];
                $artistMeta[$rowA['name']] = [
                    'id'          => (int)$rowA['id'],
                    'event_count' => (int)$rowA['event_count'],
                ];
            }

            // program→artists map (name + id)
            if ($eventId !== null) {
                $stmtPA = $dbArtists->prepare("
                    SELECT pa.program_id, a.id AS artist_id, a.name, a.display_picture
                    FROM program_artists pa
                    JOIN artists a ON a.id = pa.artist_id
                    JOIN programs p ON p.id = pa.program_id
                    WHERE p.event_id = ?
                ");
                $stmtPA->execute([$eventId]);
            } else {
                $stmtPA = $dbArtists->query("
                    SELECT pa.program_id, a.id AS artist_id, a.name, a.display_picture
                    FROM program_artists pa
                    JOIN artists a ON a.id = pa.artist_id
                ");
            }
            while ($rowPA = $stmtPA->fetch(PDO::FETCH_ASSOC)) {
                $pid = (int)$rowPA['program_id'];
                $programArtistsMap[$pid][]  = $rowPA['name'];
                $programArtistIdMap[$pid][] = [
                    'id'   => (int)$rowPA['artist_id'],
                    'name' => $rowPA['name'],
                    'pic'  => $rowPA['display_picture'] ?? '',
                ];
            }

            // Other events each artist appears in (excluding current event).
            // The match set also includes the parent group of each current-event
            // artist (solo→group), so events where the artist's group performs
            // are surfaced even when the individual member isn't tagged there.
            if ($eventId !== null) {
                $stmtOE = $dbArtists->prepare("
                    SELECT pa.artist_id, a.name AS aname, e.id AS eid, e.name AS ename, e.slug AS eslug, e.end_date AS eend
                    FROM program_artists pa
                    JOIN programs p ON p.id = pa.program_id
                    JOIN events e ON e.id = p.event_id
                    JOIN artists a ON a.id = pa.artist_id
                    WHERE p.event_id != ?
                      AND e.is_active = 1
                      AND (e.end_date IS NULL OR e.end_date >= date('now', 'localtime'))
                      AND pa.artist_id IN (
                          SELECT DISTINCT pa2.artist_id
                          FROM program_artists pa2
                          JOIN programs p2 ON p2.id = pa2.program_id
                          WHERE p2.event_id = ?
                          UNION
                          SELECT DISTINCT a3.group_id
                          FROM program_artists pa3
                          JOIN programs p3 ON p3.id = pa3.program_id
                          JOIN artists a3 ON a3.id = pa3.artist_id
                          WHERE p3.event_id = ? AND a3.group_id IS NOT NULL
                      )
                    GROUP BY pa.artist_id, e.id
                    ORDER BY e.start_date ASC
                ");
                $stmtOE->execute([$eventId, $eventId, $eventId]);
                while ($rowOE = $stmtOE->fetch(PDO::FETCH_ASSOC)) {
                    $artistOtherEvents[(int)$rowOE['artist_id']][] = [
                        'id'          => (int)$rowOE['eid'],
                        'name'        => $rowOE['ename'],
                        'slug'        => $rowOE['eslug'],
                        'artist_name' => $rowOE['aname'],
                    ];
                }
            }
        }
        $dbArtists = null;
    } catch (PDOException $e) {
        error_log('Artist table load error: ' . $e->getMessage());
    }

    // Fallback: ใช้ categories text field เดิม
    if (!$useArtistsTable) {
        $artists = $parser->getAllOrganizers();
    }

    // Event pictures (v6.6.0)
    $eventPictures = [];
    if ($eventId !== null) {
        try {
            $dbPic = new PDO('sqlite:' . DB_PATH);
            $dbPic->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmtPic = $dbPic->prepare(
                "SELECT id, filename, caption FROM event_pictures
                 WHERE event_id = ? ORDER BY display_order ASC, id ASC"
            );
            $stmtPic->execute([$eventId]);
            $eventPictures = $stmtPic->fetchAll(PDO::FETCH_ASSOC);
            $dbPic = null;
        } catch (PDOException $e) { $eventPictures = []; }
    }

    // Venue id map (location string → venue id) for clickable venue links (v16.0.0)
    $venueMeta = [];
    try {
        $dbVenue = new PDO('sqlite:' . DB_PATH);
        $dbVenue->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $hasVenuesTbl = (bool)$dbVenue->query("SELECT name FROM sqlite_master WHERE type='table' AND name='venues'")->fetch();
        if ($hasVenuesTbl) {
            foreach ($dbVenue->query("SELECT id, name FROM venues") as $vr) {
                $venueMeta[$vr['name']] = (int)$vr['id'];
            }
            $hasVarTbl = (bool)$dbVenue->query("SELECT name FROM sqlite_master WHERE type='table' AND name='venue_variants'")->fetch();
            if ($hasVarTbl) {
                foreach ($dbVenue->query("SELECT variant, venue_id FROM venue_variants") as $vr) {
                    if (!isset($venueMeta[$vr['variant']])) $venueMeta[$vr['variant']] = (int)$vr['venue_id'];
                }
            }
        }
        $dbVenue = null;
    } catch (PDOException $e) { $venueMeta = []; }

    save_query_cache($queryCacheFile, [
        'all_events'            => $allEvents,
        'venues'                => $venues,
        'types'                 => $types,
        'artists'               => $artists,
        'artist_meta'           => $artistMeta,
        'program_artists_map'   => $programArtistsMap,
        'program_artist_id_map' => $programArtistIdMap,
        'artist_other_events'   => $artistOtherEvents,
        'use_artists_table'     => $useArtistsTable,
        'event_pictures'        => $eventPictures,
        'venue_meta'            => $venueMeta,
    ]);
    } // end query cache miss block
} else {
    $allEvents = [];
    $artists = [];
    $venues = [];
    $types = [];
    $programArtistsMap  = [];
    $programArtistIdMap = [];
    $artistMeta         = [];
    $artistOtherEvents  = [];
    $useArtistsTable    = false;
    $eventPictures      = [];
    $venueMeta          = [];
}

// รับค่า filter จาก GET parameters (รองรับหลายค่า) with sanitization
$filterArtists = get_sanitized_array_param('artist', 200, 50);
$filterVenues = get_sanitized_array_param('venue', 200, 50);
$filterTypes = get_sanitized_array_param('type', 200, 50);

// 🚀 Optimization: Pre-normalize categories + Pre-compute timestamps (avoid repeated strtotime calls)
$eventTzObj = new DateTimeZone($eventTz);
// Format a unix timestamp in the event's timezone (so display matches the stored
// event-local time, not the PHP default TZ). Without this, an event in Asia/Taipei
// (UTC+8) viewed from a Bangkok-defaulted PHP (UTC+7) would render 1 hour off.
$evFmt = function($ts, $fmt) use ($eventTzObj) {
    return (new DateTime('@' . (int)$ts))->setTimezone($eventTzObj)->format($fmt);
};
$normalizedEvents = array_map(function($event) use ($eventTzObj) {
    $event['categoriesArray'] = !empty($event['categories'])
        ? array_map('trim', explode(',', $event['categories']))
        : [];
    $event['start_ts'] = !empty($event['start']) ? (new DateTime($event['start'], $eventTzObj))->getTimestamp() : 0;
    $event['end_ts']   = !empty($event['end'])   ? (new DateTime($event['end'],   $eventTzObj))->getTimestamp() : 0;
    return $event;
}, $allEvents);

// Create lookup arrays (O(1) search instead of O(n) with in_array)
$filterArtistsSet = array_flip($filterArtists);
$filterVenuesSet = array_flip($filterVenues);
$filterTypesSet = array_flip($filterTypes);

// กรองข้อมูล
$filteredEvents = array_filter($normalizedEvents, function($event) use ($filterArtistsSet, $filterVenuesSet, $filterTypesSet, $programArtistsMap, $useArtistsTable) {
    // ตรวจสอบ artist filter
    $artistMatch = empty($filterArtistsSet);
    if (!$artistMatch) {
        if ($useArtistsTable) {
            // ใช้ program_artists junction table (canonical names)
            $names = $programArtistsMap[(int)($event['id'] ?? 0)] ?? [];
            foreach ($names as $name) {
                if (isset($filterArtistsSet[$name])) {
                    $artistMatch = true;
                    break;
                }
            }
        } else {
            // Fallback: ใช้ categories text field เดิม
            foreach ($event['categoriesArray'] as $category) {
                if (isset($filterArtistsSet[$category])) {
                    $artistMatch = true;
                    break;
                }
            }
        }
    }

    // Check venue with O(1) lookup
    $venueMatch = empty($filterVenuesSet) || isset($filterVenuesSet[$event['location'] ?? null]);

    // Check program type with O(1) lookup
    $typeMatch = empty($filterTypesSet) || isset($filterTypesSet[$event['program_type'] ?? '']);

    return $artistMatch && $venueMatch && $typeMatch;
});

// จัดกลุ่มข้อมูลตามวัน
$eventsByDay = [];
foreach ($filteredEvents as $event) {
    $timestamp = $event['start_ts'];
    $dayKey = $evFmt($timestamp, 'Y-m-d');
    if (!isset($eventsByDay[$dayKey])) {
        $eventsByDay[$dayKey] = [];
    }
    $eventsByDay[$dayKey][] = $event;
}

ksort($eventsByDay);

// เรียงลำดับ events ภายในแต่ละวันตามเวลา start
foreach ($eventsByDay as $dayKey => &$dayEvents) {
    usort($dayEvents, function($a, $b) {
        return $a['start_ts'] - $b['start_ts'];
    });
}
unset($dayEvents); // ยกเลิก reference

// แสดง column "ประเภท" เมื่อมี program ที่มี program_type อย่างน้อย 1 รายการ
$hasTypes = !empty($types);
$today = date('Y-m-d');

// Prepare listing calendar data — events (per date) from all active events (homepage only)
// date => [unique events that have at least one program on that date]
$listingCalData = [];
if ($_listingCalDataFromCache !== null) {
    // Cache hit: restore calendar data
    $listingCalData = $_listingCalDataFromCache;
} elseif ($showEventListing) {
    // Cache miss: run query
    try {
        $dbLC = new PDO('sqlite:' . DB_PATH);
        $dbLC->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmtLC = $dbLC->prepare("
            SELECT DISTINCT DATE(p.start) AS program_date,
                   e.id, e.name, e.slug, e.start_date, e.end_date
            FROM programs p
            JOIN events e ON e.id = p.event_id AND e.is_active = 1
            WHERE e.slug != ?
            ORDER BY program_date ASC, e.name ASC
        ");
        $stmtLC->execute([DEFAULT_EVENT_SLUG]);
        foreach ($stmtLC->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $date = $row['program_date'];
            if (!$date || $date === '0000-00-00') continue;
            $listingCalData[$date][] = [
                'id'         => (int)$row['id'],
                'name'       => $row['name'],
                'slug'       => $row['slug'],
                'start_date' => $row['start_date'],
                'end_date'   => $row['end_date'],
            ];
        }
        $stmtLC->closeCursor(); $stmtLC = null;
        $dbLC = null;
    } catch (PDOException $e) { /* continue */ }

    // Batch query: first event_picture per event (cover fallback) + program_type counts
    $eventCoversCache       = [];
    $programTypeCountsCache = [];
    $listingEventIds = array_values(array_map('intval', array_column(
        array_filter($activeEvents, fn($e) => $e['slug'] !== DEFAULT_EVENT_SLUG), 'id'
    )));
    if (!empty($listingEventIds)) {
        try {
            $dbCov = new PDO('sqlite:' . DB_PATH);
            $dbCov->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $in = implode(',', $listingEventIds);
            // First picture per event ordered by display_order ASC, id ASC
            $picRows = $dbCov->query(
                "SELECT event_id, filename FROM event_pictures
                 WHERE event_id IN ($in)
                 ORDER BY event_id, display_order ASC, id ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($picRows as $pr) {
                $eid = (int)$pr['event_id'];
                if (!isset($eventCoversCache[$eid])) {
                    $eventCoversCache[$eid] = $pr['filename'];
                }
            }
            // Program type counts across active events
            $typeRows = $dbCov->query(
                "SELECT program_type, COUNT(*) AS cnt FROM programs
                 WHERE program_type IS NOT NULL AND program_type != ''
                   AND event_id IN ($in)
                 GROUP BY program_type ORDER BY cnt DESC LIMIT 6"
            )->fetchAll(PDO::FETCH_ASSOC);
            $programTypeCountsCache = $typeRows;
            $dbCov = null;
        } catch (PDOException $e) { /* continue */ }
    }

    // Live Now candidates: programs of active events within today ±1 day.
    // Emitted as ISO-8601-with-offset (absolute instant) so the client can
    // classify "live"/"starting soon" against the browser clock regardless of
    // event timezone — see renderLiveNow() in the inline JS below.
    $livePrograms = [];
    try {
        $dbLive = new PDO('sqlite:' . DB_PATH);
        $dbLive->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmtLive = $dbLive->prepare("
            SELECT p.id, p.title, p.start, p.end, p.location, p.categories,
                   p.stream_url, p.program_type,
                   e.name AS event_name, e.slug AS event_slug,
                   COALESCE(e.timezone, :defTz) AS event_timezone
            FROM programs p
            JOIN events e ON e.id = p.event_id AND e.is_active = 1
            WHERE e.slug != :defSlug
              AND DATE(p.start) BETWEEN DATE('now','-1 day') AND DATE('now','+1 day')
            ORDER BY p.start ASC
        ");
        $stmtLive->execute([':defTz' => DEFAULT_TIMEZONE, ':defSlug' => DEFAULT_EVENT_SLUG]);
        foreach ($stmtLive->fetchAll(PDO::FETCH_ASSOC) as $lp) {
            $lpTzName = $lp['event_timezone'] ?: DEFAULT_TIMEZONE;
            try { $lpTz = new DateTimeZone($lpTzName); }
            catch (Exception $e) { $lpTz = new DateTimeZone(DEFAULT_TIMEZONE); $lpTzName = DEFAULT_TIMEZONE; }
            try {
                $lpStartIso = (new DateTime($lp['start'], $lpTz))->format('c');
                $lpEndIso   = !empty($lp['end']) ? (new DateTime($lp['end'], $lpTz))->format('c') : '';
            } catch (Exception $e) { continue; }
            $livePrograms[] = [
                'title'      => $lp['title'],
                'start_iso'  => $lpStartIso,
                'end_iso'    => $lpEndIso,
                'location'   => $lp['location'],
                'categories' => $lp['categories'],
                'event_name' => $lp['event_name'],
                'event_tz'   => $lpTzName,
                'stream_url' => $lp['stream_url'],
                'url'        => event_url('index.php', $lp['event_slug']),
            ];
        }
        $stmtLive->closeCursor(); $stmtLive = null;
        $dbLive = null;
    } catch (PDOException $e) { /* continue */ }

    // Save listing cache
    save_query_cache('query_listing.json', [
        'active_events'       => $activeEvents,
        'listing_cal_data'    => $listingCalData,
        'event_covers'        => $eventCoversCache,
        'program_type_counts' => $programTypeCountsCache,
        'live_programs'       => $livePrograms,
    ]);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#E91E63">
    <link rel="manifest" href="<?php echo get_base_path(); ?>/manifest.json">
    <title><?php
        $siteTitle = get_site_title();
        $pageTitle = $siteTitle;
        if ($eventMeta && $eventMeta['name'] !== $siteTitle) {
            $pageTitle = $eventMeta['name'] . ' - ' . $siteTitle;
        }
        echo htmlspecialchars($pageTitle);
    ?></title>
    <?php
    // ── SEO meta tags ─────────────────────────────────────────────────────────
    if ($eventMeta) {
        $startFmt    = !empty($eventMeta['start_date']) ? date('d/m/Y', strtotime($eventMeta['start_date'])) : '';
        $endFmt      = !empty($eventMeta['end_date'])   ? date('d/m/Y', strtotime($eventMeta['end_date']))   : $startFmt;
        $venueStr    = !empty($venues) ? $venues[0] : '';
        $artistCount = count($artists);
        $seoDesc = $eventMeta['name'] . ' — ตารางกิจกรรม ' . $startFmt . '–' . $endFmt
                 . ($venueStr    ? ' · ' . $venueStr    : '')
                 . ($artistCount ? ' · ' . $artistCount . ' ศิลปิน' : '')
                 . ' | ' . $siteTitle;
        $seoCanonical = seo_full_url('/event/' . $eventMeta['slug']);
    } else {
        $seoDesc      = $siteTitle . ' — ปฏิทินตารางกิจกรรม Idol Stage';
        $seoCanonical = seo_full_url('/');
    }
    seo_render_meta([
        'title'       => $pageTitle,
        'description' => $seoDesc,
        'canonical'   => $seoCanonical,
        'og_type'     => 'website',
        'site_title'  => $siteTitle,
    ]);

    // JSON-LD: WebSite (always)
    seo_render_json_ld([
        '@context'        => 'https://schema.org',
        '@type'           => 'WebSite',
        'name'            => $siteTitle,
        'url'             => seo_full_url('/'),
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => [
                '@type'       => 'EntryPoint',
                'urlTemplate' => seo_full_url('/') . '?artist={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ]);

    // JSON-LD: Event (when viewing a specific event)
    if ($eventMeta) {
        $eventSchema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Event',
            'name'        => $eventMeta['name'],
            'startDate'   => $eventMeta['start_date'] ?? '',
            'endDate'     => $eventMeta['end_date']   ?? ($eventMeta['start_date'] ?? ''),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'organizer'   => [
                '@type' => 'Organization',
                'name'  => $siteTitle,
                'url'   => seo_full_url('/'),
            ],
            'url'         => $seoCanonical,
        ];
        if (!empty($venues)) {
            $eventSchema['location'] = ['@type' => 'Place', 'name' => $venues[0]];
        }
        seo_render_json_ld($eventSchema);
    }
    ?>
    <?php if (defined('GOOGLE_ANALYTICS_ID') && GOOGLE_ANALYTICS_ID): ?>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars(GOOGLE_ANALYTICS_ID); ?>"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '<?php echo htmlspecialchars(GOOGLE_ANALYTICS_ID); ?>');
    </script>
    <?php endif; ?>
    <?php if (defined('GOOGLE_ADS_CLIENT') && GOOGLE_ADS_CLIENT): ?>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?php echo htmlspecialchars(GOOGLE_ADS_CLIENT, ENT_QUOTES, 'UTF-8'); ?>"
         crossorigin="anonymous"></script>
    <?php endif; ?>
    <!-- Shared CSS -->
    <link rel="stylesheet" href="<?php echo asset_url('styles/common.css'); ?>">
    <!-- Index page CSS -->
    <link rel="stylesheet" href="<?php echo asset_url('styles/index.css'); ?>">
    <?php $siteTheme = get_site_theme($eventMeta); ?>
    <?php if ($siteTheme !== 'sakura'): ?>
    <link rel="stylesheet" href="<?php echo asset_url('styles/themes/' . $siteTheme . '.css'); ?>">
    <?php endif; ?>
    <?php
    $headerCoverBg      = get_header_cover_bg();           // listing view header (no event context)
    $eventHeaderCoverBg = get_header_cover_bg($eventMeta); // event-detail header (event override)
    ?>
</head>
<body>
    <?php if (!$showEventListing && !empty($eventsByDay) && count($eventsByDay) > 1): ?>
    <div class="date-jump-bar" id="dateJumpBar">
        <div class="date-jump-inner">
        <span class="date-jump-label" data-i18n="dateJump.label">📅 ข้ามไปวันที่:</span>
        <button class="date-jump-arrow" id="jumpPrev" onclick="scrollJumpBar(-200)" aria-label="Previous">◀</button>
        <div class="date-jump-buttons" id="jumpButtons">
            <?php foreach ($eventsByDay as $djKey => $djEvents): ?>
            <?php
                $djTimestamp = $djEvents[0]['start_ts'];
                $djDay = $evFmt($djTimestamp, 'd');
                $djMonth = $evFmt($djTimestamp, 'm');
                $djDayOfWeek = $evFmt($djTimestamp, 'w');
            ?>
            <a href="#day-<?php echo $djKey; ?>" class="date-jump-btn" data-day="<?php echo $djDay; ?>" data-month="<?php echo $djMonth; ?>" data-dayofweek="<?php echo $djDayOfWeek; ?>">
                <span class="date-jump-day"><?php echo $djDay . '/' . $djMonth; ?></span>
                <span class="date-jump-weekday" data-dayofweek="<?php echo $djDayOfWeek; ?>"></span>
            </a>
            <?php endforeach; ?>
        </div>
        <button class="date-jump-arrow" id="jumpNext" onclick="scrollJumpBar(200)" aria-label="Next">▶</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="container<?php echo $currentVenueMode === 'calendar' ? ' calendar-mode' : ''; ?>">
        <?php if ($showEventListing): ?>
        <!-- ========================================
             Program Listing (Homepage)
             ======================================== -->
        <header<?php if ($headerCoverBg): ?> class="has-site-cover" style="--header-cover-url: url('<?php echo htmlspecialchars(get_base_path() . '/' . $headerCoverBg, ENT_QUOTES, 'UTF-8'); ?>')"<?php endif; ?>>
            <div class="header-top-left">
                <a href="<?php echo get_base_path(); ?>/" class="home-icon-btn" data-i18n-title="nav.home" title="หน้าแรก">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M10 2L2 9h2v9h5v-5h2v5h5V9h2L10 2z" fill="currentColor"/>
                    </svg>
                </a>
                <a href="<?php echo event_url('contact.php'); ?>" class="home-icon-btn" data-i18n-title="nav.contact" title="ติดต่อเรา">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
                <a href="<?php echo event_url('how-to-use.php'); ?>" class="home-icon-btn" data-i18n-title="nav.howToUse" title="วิธีการใช้งาน">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            </div>
            <div class="language-switcher">
                <button class="lang-btn active" data-lang="th" onclick="changeLanguage('th')">TH</button>
                <button class="lang-btn" data-lang="en" onclick="changeLanguage('en')">EN</button>
                <button class="lang-btn" data-lang="ja" onclick="changeLanguage('ja')">日本</button>
            </div>
            <h1 data-i18n="header.title"><?php echo htmlspecialchars(get_site_title()); ?></h1>
            <h2 data-i18n="header.subtitle">Idol stage event calendar</h2>
            <nav class="header-nav">
                <a href="<?php echo get_base_path(); ?>/artists" class="header-nav-link" data-i18n="nav.artists">🎤 ศิลปิน</a>
                <a href="<?php echo get_base_path(); ?>/venues" class="header-nav-link" data-i18n="nav.venues">🏛️ สถานที่</a>
                <a href="<?php echo event_url('credits.php'); ?>" class="header-nav-link" data-i18n="footer.credits">📋 แหล่งข้อมูลอ้างอิง</a>
                <a href="#" class="header-nav-link" onclick="openEventRequestModal();return false;" data-i18n="nav.eventRequest">📝 แจ้งเพิ่มงาน</a>
            </nav>
        </header>
        <?php render_ad_unit('leaderboard'); ?>

        <!-- Live Now strip: populated client-side by renderLiveNow() from window.LIVE_PROGRAMS -->
        <section id="liveNowStrip" class="live-now-strip" hidden>
            <div class="live-now-group live-now-current" id="liveNowCurrentGroup" hidden>
                <h3 class="live-now-heading" data-i18n="live.nowTitle">🔴 กำลังแสดงตอนนี้</h3>
                <div class="live-now-rows" id="liveNowCurrentRows"></div>
            </div>
            <div class="live-now-group live-now-soon" id="liveNowSoonGroup" hidden>
                <h3 class="live-now-heading" data-i18n="live.soonTitle">⏭️ กำลังจะเริ่ม</h3>
                <div class="live-now-rows" id="liveNowSoonRows"></div>
            </div>
        </section>

        <?php
        // Sort events: ongoing first, then upcoming (exclude past & default slug)
        $today = date('Y-m-d');
        $sortedEvents = array_values(array_filter($activeEvents, function($e) use ($today) {
            if ($e['slug'] === DEFAULT_EVENT_SLUG) return false;
            $end = $e['end_date'] ?? ($e['start_date'] ?? null);
            return $end === null || $end >= $today;
        }));
        usort($sortedEvents, function($a, $b) use ($today) {
            $aStart = $a['start_date'] ?? '9999-12-31';
            $aEnd   = $a['end_date']   ?? $aStart;
            $bStart = $b['start_date'] ?? '9999-12-31';
            $bEnd   = $b['end_date']   ?? $bStart;
            $aStatus = ($aStart <= $today && $aEnd >= $today) ? 0 : ($aStart > $today ? 1 : 2);
            $bStatus = ($bStart <= $today && $bEnd >= $today) ? 0 : ($bStart > $today ? 1 : 2);
            if ($aStatus !== $bStatus) return $aStatus - $bStatus;
            return $aStatus === 2 ? strcmp($bStart, $aStart) : strcmp($aStart, $bStart);
        });

        // Build hero slides: events that have a cover image (or event_picture fallback)
        $heroSlides = [];
        foreach ($sortedEvents as $ev) {
            $evId     = intval($ev['id']);
            $fallback = $eventCoversCache[$evId] ?? null;
            $coverImg = get_event_cover_image($ev, $fallback);
            if ($coverImg) {
                $evStart  = $ev['start_date'] ?? null;
                $evEnd    = $ev['end_date']   ?? $evStart;
                $evStatus = 'upcoming';
                if ($evStart && $evEnd) {
                    if ($evStart <= $today && $evEnd >= $today) $evStatus = 'ongoing';
                    elseif ($evEnd < $today) $evStatus = 'past';
                }
                $heroSlides[] = [
                    'name'      => $ev['name'],
                    'slug'      => $ev['slug'],
                    'cover'     => $coverImg,
                    'start'     => $evStart,
                    'end'       => $evEnd,
                    'status'    => $evStatus,
                    'url'       => event_url('index.php', $ev['slug']),
                ];
                if (count($heroSlides) >= 5) break;
            }
        }

        // FTS5 search
        $searchQuery    = trim(get_sanitized_param('q', '', 200));
        $searchResults  = null;
        $searchPrograms = [];
        $searchTotal    = 0;
        $searchPages    = 1;
        $searchPage     = 1;
        $searchEvents   = [];
        $searchArtists  = [];
        if ($searchQuery !== '' && mb_strlen($searchQuery) >= 3) {
            $searchPerPage = 10;
            $searchPage    = max(1, intval($_GET['page'] ?? 1));
            $searchOffset  = ($searchPage - 1) * $searchPerPage;
            try {
                $dbSearch       = new PDO('sqlite:' . DB_PATH);
                $dbSearch->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $searchPrograms = fts5_search_programs($dbSearch, $searchQuery, null, $searchPerPage, $searchOffset);
                $searchTotal    = fts5_count_programs($dbSearch, $searchQuery);
                $searchPages    = max(1, (int)ceil($searchTotal / $searchPerPage));
                $searchEvents   = fts5_search_events($dbSearch, $searchQuery, 5);
                $searchArtists  = fts5_search_artists($dbSearch, $searchQuery, 10);
                $searchResults  = true; // flag: search was executed
            } catch (Exception $e) {
                $searchResults = true;
            }
        }

        // Pagination (only used when no search query)
        $perPage     = 12;
        $totalItems  = count($sortedEvents);
        $totalPages  = max(1, (int)ceil($totalItems / $perPage));
        $currentPage = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
        $pagedEvents = array_slice($sortedEvents, ($currentPage - 1) * $perPage, $perPage);
        $baseUrl     = get_base_path() . '/';
        ?>

        <!-- ===== Search Bar ===== -->
        <div class="fts-search-bar-wrap">
            <form class="fts-search-form" method="get" action="<?php echo htmlspecialchars(get_base_path() . '/'); ?>">
                <div class="fts-search-inner">
                    <span class="fts-search-icon">🔍</span>
                    <input type="search" name="q"
                           value="<?php echo htmlspecialchars($searchQuery); ?>"
                           placeholder="" data-i18n-placeholder="search.placeholder"
                           autocomplete="off" class="fts-search-input">
                    <?php if ($searchQuery !== ''): ?>
                    <a class="fts-clear-btn" href="<?php echo htmlspecialchars(get_base_path() . '/'); ?>"
                       aria-label="Clear search">✕</a>
                    <?php endif; ?>
                    <button type="submit" class="fts-submit-btn" data-i18n="search.button">ค้นหา</button>
                </div>
            </form>
        </div>

        <?php if ($searchResults !== null): ?>
        <!-- ===== Search Results (2-column: Programs main + Sidebar) ===== -->
        <?php $hasAny = !empty($searchPrograms) || !empty($searchEvents) || !empty($searchArtists); ?>
        <div class="fts-results-wrap">
            <h3 class="fts-results-heading">
                <span data-i18n="search.resultsFor">ผลการค้นหา:</span>
                <em>&ldquo;<?php echo htmlspecialchars($searchQuery); ?>&rdquo;</em>
                <?php if ($searchTotal > 0): ?>
                <span class="fts-total-count">(<?php echo $searchTotal; ?> programs)</span>
                <?php endif; ?>
            </h3>

            <?php if (!$hasAny): ?>
            <div class="fts-no-results" data-i18n="search.noResults">ไม่พบผลลัพธ์ที่ตรงกัน</div>
            <?php else: ?>

            <div class="fts-results-layout">

                <!-- Main: Programs (paginated) -->
                <main class="fts-results-main">
                    <?php if (!empty($searchPrograms)): ?>
                    <div class="fts-section-title" data-i18n="search.programs">📋 Programs</div>
                    <?php foreach ($searchPrograms as $pr): ?>
                    <?php $prEventUrl = htmlspecialchars(event_url('index.php', $pr['event_slug'] ?? '')); ?>
                    <a class="fts-result-item" href="<?php echo $prEventUrl; ?>">
                        <div class="fts-result-name"><?php echo htmlspecialchars($pr['title'] ?? ''); ?></div>
                        <?php if (!empty($pr['snippet'])): ?>
                        <div class="fts-result-snippet"><?php echo $pr['snippet']; ?></div>
                        <?php endif; ?>
                        <div class="fts-result-meta">
                            🎪 <?php echo htmlspecialchars($pr['event_name'] ?? ''); ?>
                            <?php if (!empty($pr['start'])): ?>
                            · 📅 <?php echo htmlspecialchars(substr($pr['start'], 0, 10)); ?>
                            · 🕐 <?php echo htmlspecialchars(substr($pr['start'], 11, 5)); ?>
                            <?php endif; ?>
                            <?php if (!empty($pr['location'])): ?>
                            · 📍 <?php echo htmlspecialchars($pr['location']); ?>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>

                    <?php if ($searchPages > 1): ?>
                    <nav class="fts-pagination" aria-label="Search pagination">
                        <?php
                        $qParam  = '?q=' . urlencode($searchQuery);
                        $baseSearchUrl = get_base_path() . '/' . $qParam;
                        ?>
                        <?php if ($searchPage > 1): ?>
                        <a class="fts-page-btn" href="<?php echo $baseSearchUrl . '&page=' . ($searchPage - 1); ?>">←</a>
                        <?php endif; ?>
                        <?php for ($sp = 1; $sp <= $searchPages; $sp++):
                            if ($sp === 1 || $sp === $searchPages || abs($sp - $searchPage) <= 1): ?>
                            <?php if ($sp === $searchPage): ?>
                            <span class="fts-page-btn fts-page-current"><?php echo $sp; ?></span>
                            <?php else: ?>
                            <a class="fts-page-btn" href="<?php echo $baseSearchUrl . '&page=' . $sp; ?>"><?php echo $sp; ?></a>
                            <?php endif; ?>
                        <?php elseif (abs($sp - $searchPage) === 2): ?>
                            <span class="fts-page-ellipsis">…</span>
                        <?php endif; endfor; ?>
                        <?php if ($searchPage < $searchPages): ?>
                        <a class="fts-page-btn" href="<?php echo $baseSearchUrl . '&page=' . ($searchPage + 1); ?>">→</a>
                        <?php endif; ?>
                    </nav>
                    <?php endif; ?>

                    <?php elseif (empty($searchEvents) && empty($searchArtists)): ?>
                    <div class="fts-no-results" data-i18n="search.noResults">ไม่พบผลลัพธ์ที่ตรงกัน</div>
                    <?php endif; ?>
                </main>

                <!-- Sidebar: Events (top 5) + Artists (top 10) -->
                <?php if (!empty($searchEvents) || !empty($searchArtists)): ?>
                <aside class="fts-results-sidebar">

                    <?php if (!empty($searchEvents)): ?>
                    <div class="fts-section-title" data-i18n="search.events">🎪 Events</div>
                    <?php foreach ($searchEvents as $ev): ?>
                    <a class="fts-result-item fts-sidebar-item"
                       href="<?php echo htmlspecialchars(event_url('index.php', $ev['slug'])); ?>">
                        <div class="fts-result-name"><?php echo htmlspecialchars($ev['name']); ?></div>
                        <?php if (!empty($ev['start_date'])): ?>
                        <div class="fts-result-meta">📅 <?php echo htmlspecialchars($ev['start_date']); ?></div>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($searchArtists)): ?>
                    <div class="fts-section-title" <?php if (!empty($searchEvents)): ?>style="margin-top:16px"<?php endif; ?> data-i18n="search.artists">🎤 ศิลปิน</div>
                    <?php foreach ($searchArtists as $ar): ?>
                    <a class="fts-result-item fts-sidebar-item"
                       href="<?php echo htmlspecialchars(get_base_path() . '/artist/' . intval($ar['id'])); ?>">
                        <div class="fts-result-name"><?php echo htmlspecialchars($ar['name']); ?></div>
                        <?php if (!empty($ar['is_group'])): ?>
                        <div class="fts-result-meta" data-i18n="search.group">กลุ่ม / วง</div>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>

                </aside>
                <?php endif; ?>

            </div><!-- .fts-results-layout -->
            <?php endif; // $hasAny ?>
        </div>

        <?php else: // normal listing — no search ?>

        <?php if (count($heroSlides) >= 1): ?>
        <!-- ===== Hero Carousel + Compact Calendar (side-by-side) ===== -->
        <div class="hero-calendar-row">

            <section class="hero-carousel" id="heroCarousel" aria-label="Featured events">
                <div class="hero-track" id="heroTrack">
                    <?php foreach ($heroSlides as $slide): ?>
                    <article class="hero-slide"
                             style="background-image: url('<?php echo htmlspecialchars(get_base_path() . '/' . $slide['cover']); ?>')"
                             role="group">
                        <div class="hero-overlay"></div>
                        <div class="hero-content">
                            <span class="hero-status-badge hero-badge-<?php echo $slide['status']; ?>"
                                  data-i18n="listing.<?php echo $slide['status']; ?>">
                                <?php echo $slide['status'] === 'ongoing' ? 'กำลังจัดงาน' : ($slide['status'] === 'upcoming' ? 'กำลังจะมาถึง' : 'จบแล้ว'); ?>
                            </span>
                            <h2 class="hero-title"><?php echo htmlspecialchars($slide['name']); ?></h2>
                            <?php if ($slide['start']): ?>
                            <div class="hero-meta">
                                📅 <?php echo date('d/m/Y', strtotime($slide['start'])); ?>
                                <?php if ($slide['end'] && $slide['end'] !== $slide['start']): ?>
                                    – <?php echo date('d/m/Y', strtotime($slide['end'])); ?>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <a href="<?php echo htmlspecialchars($slide['url']); ?>" class="hero-cta" data-i18n="listing.viewSchedule">
                                📋 ดูตารางเวลา
                            </a>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php if (count($heroSlides) > 1): ?>
                <button class="hero-nav hero-prev" id="heroPrev" aria-label="Previous" onclick="heroNav(-1)">‹</button>
                <button class="hero-nav hero-next" id="heroNext" aria-label="Next" onclick="heroNav(1)">›</button>
                <div class="hero-dots" id="heroDots">
                    <?php foreach ($heroSlides as $i => $s): ?>
                    <button class="hero-dot <?php echo $i === 0 ? 'active' : ''; ?>" onclick="heroGoTo(<?php echo $i; ?>)" aria-label="Slide <?php echo $i + 1; ?>"></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>

            <?php if (!empty($listingCalData)): ?>
            <div class="listing-cal-section listing-cal-compact">
                <h3 class="listing-cal-section-title" data-i18n="listing.calTitle">📅 ปฏิทินกิจกรรม</h3>
                <div class="listing-cal-wrap">
                    <div class="listing-cal-header">
                        <button class="listing-cal-nav" id="lcalPrevBtn" onclick="lcalNav(-1)" aria-label="Previous month">◀</button>
                        <span class="listing-cal-title" id="lcalTitle"></span>
                        <button class="listing-cal-nav" id="lcalNextBtn" onclick="lcalNav(1)" aria-label="Next month">▶</button>
                    </div>
                    <div class="listing-cal-grid" id="lcalGrid"></div>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- .hero-calendar-row -->
        <?php elseif (!empty($listingCalData)): ?>
        <!-- Fallback: calendar only (not enough cover images for carousel) -->
        <div class="listing-cal-section">
            <h3 class="listing-cal-section-title" data-i18n="listing.calTitle">📅 ปฏิทินกิจกรรม</h3>
            <div class="listing-cal-wrap">
                <div class="listing-cal-header">
                    <button class="listing-cal-nav" id="lcalPrevBtn" onclick="lcalNav(-1)" aria-label="Previous month">◀</button>
                    <span class="listing-cal-title" id="lcalTitle"></span>
                    <button class="listing-cal-nav" id="lcalNextBtn" onclick="lcalNav(1)" aria-label="Next month">▶</button>
                </div>
                <div class="listing-cal-grid" id="lcalGrid"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== Current Events Grid ===== -->
        <div class="program-listing">
            <h3 class="program-listing-title" data-i18n="listing.title">🎪 รายการกิจกรรม</h3>

            <?php if (empty($sortedEvents)): ?>
                <div class="no-events-listing">
                    <div class="no-events-icon" style="font-size:4em;opacity:0.3;margin-bottom:20px;">📅</div>
                    <h2 data-i18n="listing.noEvents">ยังไม่มี Event ในระบบ</h2>
                </div>
            <?php else: ?>
                <div class="events-grid">
                    <?php foreach ($pagedEvents as $ev):
                        $evId    = intval($ev['id']);
                        $evStart = $ev['start_date'] ?? null;
                        $evEnd   = $ev['end_date']   ?? $evStart;
                        $evStatus = 'upcoming';
                        if ($evStart && $evEnd) {
                            if ($evStart <= $today && $evEnd >= $today) $evStatus = 'ongoing';
                            elseif ($evEnd < $today) $evStatus = 'past';
                        }
                        $displayStart = $evStart ? date('d/m/Y', strtotime($evStart)) : '-';
                        $displayEnd   = $evEnd   ? date('d/m/Y', strtotime($evEnd))   : '-';
                        $fallback     = $eventCoversCache[$evId] ?? null;
                        // Card cover: cover_image_card (4:3) → cover_image (16:9 fallback) → event_pictures → null
                        $cardCoverImg = !empty($ev['cover_image_card'])
                            ? $ev['cover_image_card']
                            : get_event_cover_image($ev, $fallback);
                        $evUrl        = htmlspecialchars(event_url('index.php', $ev['slug']));
                    ?>
                    <a class="event-card" href="<?php echo $evUrl; ?>">
                        <div class="event-card-cover<?php echo $cardCoverImg ? '' : ' event-card-cover-gradient'; ?>"
                             <?php if ($cardCoverImg): ?>style="background-image: url('<?php echo htmlspecialchars(get_base_path() . '/' . $cardCoverImg); ?>')"<?php endif; ?>>
                            <span class="event-card-badge <?php echo $evStatus; ?>" data-i18n="listing.<?php echo $evStatus; ?>">
                                <?php echo $evStatus === 'ongoing' ? 'กำลังจัดงาน' : ($evStatus === 'upcoming' ? 'กำลังจะมาถึง' : 'จบแล้ว'); ?>
                            </span>
                        </div>
                        <div class="event-card-body">
                            <h4 class="event-card-name"><?php echo htmlspecialchars($ev['name']); ?></h4>
                            <div class="event-card-date">
                                📅 <?php echo $displayStart; ?><?php if ($displayStart !== $displayEnd): ?> – <?php echo $displayEnd; ?><?php endif; ?>
                            </div>
                            <?php if (!empty($ev['description'])): ?>
                            <div class="event-card-description"><?php echo nl2br(htmlspecialchars($ev['description'])); ?></div>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Pagination">
                    <?php if ($currentPage > 1): ?>
                        <a href="<?php echo $baseUrl . '?page=' . ($currentPage - 1); ?>" data-i18n="listing.pagePrev">←</a>
                    <?php endif; ?>
                    <?php for ($p = 1; $p <= $totalPages; $p++):
                        if ($p === 1 || $p === $totalPages || abs($p - $currentPage) <= 1): ?>
                        <?php if ($p === $currentPage): ?>
                            <span class="current"><?php echo $p; ?></span>
                        <?php else: ?>
                            <a href="<?php echo $baseUrl . '?page=' . $p; ?>"><?php echo $p; ?></a>
                        <?php endif; ?>
                    <?php elseif (abs($p - $currentPage) === 2): ?>
                        <span class="ellipsis">…</span>
                    <?php endif; endfor; ?>
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="<?php echo $baseUrl . '?page=' . ($currentPage + 1); ?>" data-i18n="listing.pageNext">→</a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>


        <div style="text-align:center;margin-top:20px;padding-bottom:8px">
            <a href="<?php echo get_base_path(); ?>/past-events"
               class="past-events-btn"
               data-i18n="listing.pastEventsBtn">🗂️ ดูงานที่จบแล้ว</a>
        </div>

        <!-- Listing Calendar Day Modal -->
        <div class="listing-day-overlay" id="lcalDayOverlay" onclick="if(event.target===this)closeLcalDayModal()">
            <div class="listing-day-modal" role="dialog" aria-modal="true">
                <div class="listing-day-modal-header">
                    <span class="listing-day-modal-title" id="lcalDayTitle"></span>
                    <button class="listing-day-modal-close" onclick="closeLcalDayModal()" aria-label="Close">×</button>
                </div>
                <div class="listing-day-modal-body" id="lcalDayBody"></div>
            </div>
        </div>

        <?php endif; // end: else (normal listing — no search) ?>

        <?php else: ?>
        <!-- ========================================
             Calendar View (Event Detail)
             ======================================== -->
        <header<?php if ($eventHeaderCoverBg): ?> class="has-site-cover" style="--header-cover-url: url('<?php echo htmlspecialchars(get_base_path() . '/' . $eventHeaderCoverBg, ENT_QUOTES, 'UTF-8'); ?>')"<?php endif; ?>>
            <div class="header-top-left">
                <?php if (MULTI_EVENT_MODE): ?>
                <a href="<?php echo get_base_path(); ?>/" class="home-icon-btn" data-i18n-title="nav.home" title="Home">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M10 2L2 9h2v9h5v-5h2v5h5V9h2L10 2z" fill="currentColor"/>
                    </svg>
                </a>
                <?php endif; ?>
                <a href="<?php echo event_url('contact.php'); ?>" class="home-icon-btn" data-i18n-title="nav.contact" title="ติดต่อเรา">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
                <?php if (MULTI_EVENT_MODE && count($activeEvents) > 1): ?>
                <button class="event-picker-btn" onclick="openEventPicker()" data-i18n-title="eventPicker.title" title="เลือก Event">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                        <circle cx="3" cy="3" r="2" fill="currentColor"/>
                        <circle cx="9" cy="3" r="2" fill="currentColor"/>
                        <circle cx="15" cy="3" r="2" fill="currentColor"/>
                        <circle cx="3" cy="9" r="2" fill="currentColor"/>
                        <circle cx="9" cy="9" r="2" fill="currentColor"/>
                        <circle cx="15" cy="9" r="2" fill="currentColor"/>
                        <circle cx="3" cy="15" r="2" fill="currentColor"/>
                        <circle cx="9" cy="15" r="2" fill="currentColor"/>
                        <circle cx="15" cy="15" r="2" fill="currentColor"/>
                    </svg>
                </button>
                <?php endif; ?>
            </div>
            <div class="language-switcher">
                <button class="lang-btn active" data-lang="th" onclick="changeLanguage('th')">TH</button>
                <button class="lang-btn" data-lang="en" onclick="changeLanguage('en')">EN</button>
                <button class="lang-btn" data-lang="ja" onclick="changeLanguage('ja')">日本</button>
            </div>
            <h1 data-i18n="header.title"><?php echo htmlspecialchars(get_site_title()); ?></h1>
            <?php if ($eventMeta): ?>
            <div class="event-subtitle"><?php echo htmlspecialchars($eventName); ?></div>
            <?php if ($currentVenueMode === 'single' && !empty($venues)): ?>
            <?php $eventHeaderVenueId = $venueMeta[$venues[0]] ?? null; ?>
            <div class="event-venue">📍
                <?php if ($eventHeaderVenueId): ?>
                <a href="<?php echo get_base_path(); ?>/venue/<?php echo (int)$eventHeaderVenueId; ?>" class="event-venue-link" style="color:inherit;text-decoration:none;border-bottom:1px dotted currentColor"><?php echo htmlspecialchars($venues[0]); ?></a>
                <?php else: ?>
                <?php echo htmlspecialchars($venues[0]); ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="event-timezone" id="eventTimezoneDisplay">🕐 <?php echo htmlspecialchars($eventTz); ?></div>
            <?php endif; ?>
            <h2 data-i18n="header.subtitle">Idol stage event calendar</h2>
            <p data-i18n="header.disclaimer">* Please check the latest information again. We are not responsible for any errors that may occur during the preparation of this document.</p>
            <nav class="header-nav">
                <?php if (!empty($eventMeta['ticket_url'])): ?>
                <a href="<?php echo htmlspecialchars($eventMeta['ticket_url'], ENT_QUOTES, 'UTF-8'); ?>"
                   class="header-nav-link btn-ticket" target="_blank" rel="noopener noreferrer"
                   data-i18n="event.buyTicket">🎟️ ซื้อบัตร</a>
                <?php endif; ?>
                <a href="#data-version" class="header-nav-link">🔄️ <?php echo get_data_version($eventId); ?></a>
            </nav>
        </header>
        <?php render_ad_unit('leaderboard'); ?>

        <div class="filters">
            <form method="GET" action="<?php echo event_url('index.php'); ?>"  >
                <div class="filter-group">
                    <div class="filter-item">
                        <label data-i18n="filter.artist">🎤 กรองตามวง/ศิลปิน:</label>
                        <div class="search-box-wrapper" id="artistSearchWrapper">
                            <input type="text" class="search-box" id="artistSearch" data-i18n-placeholder="filter.searchArtist" placeholder="🔍 ค้นหาชื่อวง..." oninput="handleSearchInput('artistSearch', 'artistCheckboxes', 'artistSearchWrapper')" onfocus="this.select()">
                            <button type="button" class="search-clear-btn" onclick="clearSearch('artistSearch', 'artistCheckboxes', 'artistSearchWrapper')" title="ล้างการค้นหา">✕</button>
                        </div>
                        <?php if (!empty($filterArtists)): ?>
                        <div class="selected-tags" id="selectedArtists">
                            <?php foreach ($filterArtists as $artist): ?>
                            <span class="selected-tag">
                                <?php echo htmlspecialchars($artist); ?>
                                <button type="button" class="tag-remove" onclick="removeFilter('artist', <?php echo htmlspecialchars(json_encode($artist), ENT_QUOTES, 'UTF-8'); ?>)" title="ลบ">✕</button>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="checkbox-group" id="artistCheckboxes">
                            <?php foreach ($artists as $artist): ?>
                                <?php $aMeta = $artistMeta[$artist] ?? null; ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="artist[]" value="<?php echo htmlspecialchars($artist); ?>"
                                           <?php echo (in_array($artist, $filterArtists)) ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($artist); ?></span>
                                    <?php if ($aMeta && $aMeta['event_count'] > 1): ?>
                                        <span class="artist-event-count" title="ปรากฏใน <?php echo $aMeta['event_count']; ?> งาน"><?php echo $aMeta['event_count']; ?></span>
                                    <?php endif; ?>
                                    <?php if ($aMeta && !empty($aMeta['id'])): ?>
                                        <a href="<?php echo get_base_path(); ?>/artist/<?php echo $aMeta['id']; ?>" target="_blank" class="artist-filter-profile-link" title="ดูโปรไฟล์">↗</a>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                            <?php if (empty($artists)): ?>
                                <p class="no-options" data-i18n="filter.noArtist">ไม่มีข้อมูลศิลปิน</p>
                            <?php endif; ?>
                        </div>
                    </div>


                    <?php if (!empty($types)): ?>
                    <div class="filter-item">
                        <label data-i18n="filter.type">🏷️ กรองตามประเภท:</label>
                        <?php if (!empty($filterTypes)): ?>
                        <div class="selected-tags" id="selectedTypes">
                            <?php foreach ($filterTypes as $type): ?>
                            <span class="selected-tag">
                                <?php echo htmlspecialchars($type); ?>
                                <button type="button" class="tag-remove" onclick="removeFilter('type', <?php echo htmlspecialchars(json_encode($type), ENT_QUOTES, 'UTF-8'); ?>)" title="ลบ">✕</button>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="checkbox-group" id="typeCheckboxes">
                            <?php foreach ($types as $type): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="type[]" value="<?php echo htmlspecialchars($type); ?>"
                                           <?php echo (in_array($type, $filterTypes)) ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($type); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($currentVenueMode === 'multi'): ?>
                    <div class="filter-item">
                        <label data-i18n="filter.venue">🏛️ กรองตามสถานที่:</label>
                        <div class="search-box-wrapper" id="venueSearchWrapper">
                            <input type="text" class="search-box" id="venueSearch" data-i18n-placeholder="filter.searchVenue" placeholder="🔍 ค้นหาชื่อสถานที่..." oninput="handleSearchInput('venueSearch', 'venueCheckboxes', 'venueSearchWrapper')" onfocus="this.select()">
                            <button type="button" class="search-clear-btn" onclick="clearSearch('venueSearch', 'venueCheckboxes', 'venueSearchWrapper')" title="ล้างการค้นหา">✕</button>
                        </div>
                        <?php if (!empty($filterVenues)): ?>
                        <div class="selected-tags" id="selectedVenues">
                            <?php foreach ($filterVenues as $venue): ?>
                            <span class="selected-tag">
                                <?php echo htmlspecialchars($venue); ?>
                                <button type="button" class="tag-remove" onclick="removeFilter('venue', <?php echo htmlspecialchars(json_encode($venue), ENT_QUOTES, 'UTF-8'); ?>)" title="ลบ">✕</button>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="checkbox-group" id="venueCheckboxes">
                            <?php foreach ($venues as $venue): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="venue[]" value="<?php echo htmlspecialchars($venue); ?>"
                                           <?php echo (in_array($venue, $filterVenues)) ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($venue); ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (empty($venues)): ?>
                                <p class="no-options" data-i18n="filter.noVenue">ไม่มีข้อมูลสถานที่</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary" data-i18n="button.search">🔍 ค้นหา</button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='<?php echo event_url('index.php'); ?>'" data-i18n="button.reset">🔄 รีเซ็ต</button>
                    <button type="button" class="btn btn-success" onclick="saveAsImage()" data-i18n="button.saveImage">📸 บันทึกเป็นรูปภาพ</button>
                    <button type="button" class="btn btn-primary" onclick="exportToIcs()" data-i18n="button.exportIcs">📅 Export to Calendar</button>
                    <button type="button" class="btn btn-subscribe" onclick="openSubscribeModal()" data-i18n="button.subscribe">🔔 Subscribe</button>
                    <button type="button" class="btn btn-request" onclick="openRequestModal()" data-i18n="button.requestAdd">📝 แจ้งเพิ่ม / แก้ไข</button>
                </div>

                <!-- View Toggle Switch (hidden in calendar mode) -->
                <?php if ($currentVenueMode !== 'calendar'): ?>
                <div class="view-toggle">
                    <label class="toggle-label">
                        <span class="toggle-text active" data-i18n="view.list">รายการ</span>
                        <div class="toggle-switch">
                            <input type="checkbox" id="viewToggle" onchange="toggleView(this.checked)">
                            <span class="toggle-slider"></span>
                        </div>
                        <span class="toggle-text" data-i18n="view.gantt">ไทม์ไลน์</span>
                    </label>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($eventMeta && !empty($eventMeta['description'])): ?>
        <div class="event-desc-block">
            <div class="event-desc-title">ℹ️ <span data-i18n="event.about">เกี่ยวกับงาน</span></div>
            <div class="event-desc-body"><?php echo nl2br(htmlspecialchars($eventMeta['description'])); ?></div>
        </div>
        <?php endif; ?>

        <?php if ($currentVenueMode === 'calendar'): ?>
        <!-- Monthly calendar grid (rendered by JS) -->
        <div id="month-calendar-view"></div>
        <?php endif; ?>

        <div class="calendar-container"<?php echo $currentVenueMode === 'calendar' ? ' style="display:none;"' : ''; ?>>
            <?php if (empty($filteredEvents)): ?>
                <div class="no-events">
                    <div class="no-events-icon">📅</div>
                    <h2 data-i18n="message.noPrograms">ไม่พบกิจกรรม</h2>
                </div>
            <?php else: ?>
                <?php foreach ($eventsByDay as $dayKey => $events): ?>
                    <?php
                        $firstEventTimestamp = $events[0]['start_ts'];
                        $day = $evFmt($firstEventTimestamp, 'd');
                        $month = $evFmt($firstEventTimestamp, 'm');
                        $year = $evFmt($firstEventTimestamp, 'Y');
                        $dayOfWeek = $evFmt($firstEventTimestamp, 'w');
                    ?>
                    <div class="day-section" id="day-<?php echo $dayKey; ?>">
                        <div class="day-header" data-day="<?php echo $day; ?>" data-month="<?php echo $month; ?>" data-year="<?php echo $year; ?>" data-dayofweek="<?php echo $dayOfWeek; ?>">
                            📅 <span class="day-header-text"><?php echo $day . '/' . $month . '/' . $year; ?></span>
                            <span class="day-name-header" style="margin-left: 8px;"></span>
                        </div>

                        <div class="events-table-container">
                            <table class="events-table">
                                <thead>
                                    <tr>
                                        <th data-i18n="table.time">เวลา</th>
                                        <th data-i18n="table.program">การแสดง/ศิลปิน</th>
                                        <?php if ($currentVenueMode === 'multi'): ?>
                                        <th data-i18n="table.venue">สถานที่</th>
                                        <?php endif; ?>
                                        <?php if ($hasTypes): ?>
                                        <th data-i18n="table.type">ประเภท</th>
                                        <?php endif; ?>
                                        <th data-i18n="table.categories">ศิลปินที่เกี่ยวข้อง</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $event): ?>
                                        <?php
                                            $streamUrl = $event['stream_url'] ?? '';
                                            $streamPlatform = '';
                                            if (!empty($streamUrl)) {
                                                if (str_contains($streamUrl, 'instagram.com')) $streamPlatform = '📷';
                                                elseif (str_contains($streamUrl, 'x.com') || str_contains($streamUrl, 'twitter.com')) $streamPlatform = '𝕏';
                                                elseif (str_contains($streamUrl, 'youtube.com') || str_contains($streamUrl, 'youtu.be')) $streamPlatform = '▶️';
                                                else $streamPlatform = '🔴';
                                            }
                                        ?>
                                        <tr<?php echo !empty($streamUrl) ? ' class="program-live"' : ''; ?>>
                                            <td class="program-datetime-cell">
                                                <?php
                                                    // Format in event TZ — Bangkok PHP default would offset Taipei/Tokyo/etc. events
                                                    $startDay = $evFmt($event['start_ts'], 'Y-m-d');
                                                    $endDay   = $evFmt($event['end_ts'],   'Y-m-d');
                                                    $crossDay = ($endDay !== $startDay);
                                                ?>
                                                <span class="program-time"
                                                      data-start="<?php echo $evFmt($event['start_ts'], 'H:i'); ?>"
                                                      data-end="<?php echo $evFmt($event['end_ts'],   'H:i'); ?>"
                                                      data-utc="<?php echo $event['start_ts']; ?>"
                                                      data-utc-end="<?php echo $event['end_ts']; ?>"
                                                      <?php if ($crossDay): ?>data-end-date="<?php echo htmlspecialchars($endDay); ?>"<?php endif; ?>></span><?php if ($crossDay): ?><span class="program-time-nextday" title="<?php echo htmlspecialchars($endDay); ?>">+<?php echo (int)((strtotime($endDay) - strtotime($startDay)) / 86400); ?></span><?php endif; ?>
                                            </td>
                                            <td class="program-info-cell">
                                                <div class="program-title-name">
                                                    <?php if ($streamPlatform): ?><span class="program-live-icon"><?php echo $streamPlatform; ?></span><?php endif; ?>
                                                    <?php echo htmlspecialchars($event['title'] ?? ''); ?>
                                                    <?php if (!empty($streamUrl)): ?>
                                                        <a href="<?php echo htmlspecialchars($streamUrl); ?>" target="_blank" rel="noopener" class="program-join-btn" data-i18n="badge.joinLive">🔴 เข้าร่วม</a>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($event['description'])): ?>
                                                    <div class="event-description">
                                                        <?php echo htmlspecialchars($event['description']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($currentVenueMode === 'multi'): ?>
                                            <td class="program-venue-cell<?php echo empty($event['location']) ? ' cell-empty' : ''; ?>">
                                                <?php if (!empty($event['location'])): ?>
                                                    <?php $venueLinkId = $venueMeta[$event['location']] ?? null; ?>
                                                    <?php if ($venueLinkId): ?>
                                                    <a href="<?php echo get_base_path(); ?>/venue/<?php echo (int)$venueLinkId; ?>" class="program-venue-link" style="color: #212529; font-size: 0.95em; text-decoration: none; border-bottom: 1px dotted var(--sakura-medium, #F48FB1);">
                                                        <?php echo htmlspecialchars($event['location']); ?>
                                                    </a>
                                                    <?php else: ?>
                                                    <span style="color: #212529; font-size: 0.95em;">
                                                        <?php echo htmlspecialchars($event['location']); ?>
                                                    </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                            <?php if ($hasTypes): ?>
                                            <td class="program-type-cell<?php echo empty($event['program_type']) ? ' cell-empty' : ''; ?>">
                                                <?php if (!empty($event['program_type'])): ?>
                                                <button type="button" class="program-type-badge" onclick="appendFilter('type', <?php echo htmlspecialchars(json_encode($event['program_type']), ENT_QUOTES, 'UTF-8'); ?>)" title="กรองตามประเภท: <?php echo htmlspecialchars($event['program_type']); ?>">
                                                    <?php echo htmlspecialchars($event['program_type']); ?>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                            <?php
                                                $pid = (int)($event['id'] ?? 0);
                                                $artistLinks = $programArtistIdMap[$pid] ?? [];
                                                if (empty($artistLinks)) {
                                                    // fallback to raw categories text
                                                    $cats = array_filter(array_map('trim', $event['categoriesArray']));
                                                    foreach ($cats as $c) {
                                                        $artistLinks[] = ['id' => null, 'name' => $c];
                                                    }
                                                }
                                            ?>
                                            <td class="program-categories-cell<?php echo empty($artistLinks) ? ' cell-empty' : ''; ?>">
                                                <?php foreach ($artistLinks as $al): ?>
                                                    <?php if (!empty($al['id'])): ?>
                                                        <span class="program-categories-badge-wrap"<?php if (!empty($al['pic'])): ?> data-display-pic="<?php echo htmlspecialchars(get_base_path() . '/' . $al['pic'], ENT_QUOTES, 'UTF-8'); ?>" data-display-name="<?php echo htmlspecialchars($al['name'], ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
                                                            <button type="button" class="program-categories-badge" onclick="appendFilter('artist', <?php echo htmlspecialchars(json_encode($al['name']), ENT_QUOTES, 'UTF-8'); ?>)" title="กรองตามศิลปิน: <?php echo htmlspecialchars($al['name']); ?>">
                                                                <?php echo htmlspecialchars($al['name']); ?>
                                                            </button><a href="<?php echo get_base_path(); ?>/artist/<?php echo $al['id']; ?>" target="_blank" class="artist-profile-link" title="ดูโปรไฟล์ศิลปิน">↗</a>
                                                        </span>
                                                    <?php else: ?>
                                                        <button type="button" class="program-categories-badge" onclick="appendFilter('artist', <?php echo htmlspecialchars(json_encode($al['name']), ENT_QUOTES, 'UTF-8'); ?>)" title="กรองตามศิลปิน: <?php echo htmlspecialchars($al['name']); ?>">
                                                            <?php echo htmlspecialchars($al['name']); ?>
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Events data for Gantt chart -->
                        <script type="application/json" class="events-data">
                        <?php echo json_encode(array_values($events), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>
                        </script>

                        <!-- Gantt view container -->
                        <div class="gantt-view" style="display: none;"></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?> <!-- end showEventListing conditional -->

        <?php if ($eventId !== null && !empty($eventPictures)): ?>
        <?php $galleryTemplate = htmlspecialchars($eventMeta['gallery_template'] ?? 'grid3', ENT_QUOTES, 'UTF-8'); ?>
        <section class="event-gallery-section">
            <div class="event-gallery-grid template-<?php echo $galleryTemplate; ?>" id="eventGalleryGrid">
                <?php foreach ($eventPictures as $i => $pic): ?>
                <div class="event-gallery-item"
                     data-index="<?php echo $i; ?>"
                     data-src="<?php echo htmlspecialchars(get_base_path() . '/' . $pic['filename'], ENT_QUOTES, 'UTF-8'); ?>"
                     data-caption="<?php echo htmlspecialchars($pic['caption'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <img src="<?php echo htmlspecialchars(get_base_path() . '/' . $pic['filename'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($pic['caption'] ?? 'รูปภาพจากงาน', ENT_QUOTES, 'UTF-8'); ?>"
                         loading="lazy">
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php
        if ($eventId !== null && !empty($artistOtherEvents)):
            $crossEvents = [];
            foreach ($artistOtherEvents as $aId => $evList) {
                foreach ($evList as $ev) {
                    // Prefer the name carried on the entry (also resolves groups not
                    // present in the current event); fall back to $artistMeta for
                    // older cache entries written before artist_name existed.
                    $aName = $ev['artist_name'] ?? '';
                    if ($aName === '') {
                        foreach ($artistMeta as $n => $m) {
                            if ($m['id'] === $aId) { $aName = $n; break; }
                        }
                    }
                    if (!isset($crossEvents[$ev['id']])) {
                        $crossEvents[$ev['id']] = ['name' => $ev['name'], 'slug' => $ev['slug'], 'artists' => []];
                    }
                    // Dedupe chips per event by artist id.
                    if (!isset($crossEvents[$ev['id']]['artists'][$aId])) {
                        $crossEvents[$ev['id']]['artists'][$aId] = ['id' => $aId, 'name' => $aName];
                    }
                }
            }
        ?>
        <?php render_ad_unit('responsive'); ?>
        <section class="cross-event-section">
            <h3 class="cross-event-section-title" data-i18n="section.crossEvent">งานอื่นที่เกี่ยวข้องกับศิลปิน</h3>
            <div class="cross-event-list">
                <?php foreach ($crossEvents as $ceid => $cev): ?>
                <div class="cross-event-item">
                    <a href="<?php echo get_base_path(); ?>/event/<?php echo htmlspecialchars($cev['slug']); ?>"
                       class="cross-event-name">
                        <?php echo htmlspecialchars($cev['name']); ?>
                    </a>
                    <div class="cross-event-artists">
                        <?php foreach ($cev['artists'] as $ca): ?>
                        <a href="<?php echo get_base_path(); ?>/artist/<?php echo $ca['id']; ?>"
                           class="cross-event-artist-chip">
                            <?php echo htmlspecialchars($ca['name']); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php
        $eventCredits = $eventId !== null ? get_cached_credits($eventId) : [];
        if (!empty($eventCredits)):
        ?>
        <section class="event-credits-section">
            <h3 class="event-credits-title" data-i18n="footer.credits">📋 แหล่งข้อมูลอ้างอิง</h3>
            <ul class="event-credits-list">
                <?php foreach ($eventCredits as $credit): ?>
                <li class="event-credits-item">
                    <div class="event-credits-item-title"><?php echo htmlspecialchars($credit['title']); ?></div>
                    <?php if (!empty($credit['description'])): ?>
                    <p class="event-credits-item-desc"><?php echo nl2br(htmlspecialchars($credit['description'])); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($credit['link'])): ?>
                    <a href="<?php echo htmlspecialchars($credit['link']); ?>" target="_blank" rel="noopener noreferrer" class="event-credits-item-link">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                            <polyline points="15 3 21 3 21 9"></polyline>
                            <line x1="10" y1="14" x2="21" y2="3"></line>
                        </svg>
                        <?php echo htmlspecialchars($credit['link']); ?>
                    </a>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <footer>
            <div class="footer-text">
                <p data-i18n="footer.madeWith">สร้างด้วย ❤️ เพื่อแฟนไอดอล</p>
                <p data-i18n="footer.copyright">© 2026 Idol Stage Timetable. All rights reserved.</p>
                <p>Powered by <a href="https://github.com/fordantitrust/stage-idol-calendar" target="_blank">Stage Idol Calendar</a> <span class="footer-version">v<?php echo APP_VERSION; ?></span></p>
            </div>
        </footer>
    </div>

    <!-- Shared JavaScript (includes translations and common functions) -->
    <script>window.SITE_TITLE = <?php echo json_encode(get_site_title()); ?>;</script>
    <script src="<?php echo asset_url('js/translations.js'); ?>"></script>
    <script src="<?php echo asset_url('js/common.js'); ?>"></script>

    <script>
    const DEFAULT_EVENT_SLUG = '<?php echo DEFAULT_EVENT_SLUG; ?>';
    const BASE_PATH = <?php echo json_encode(rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/\\")); ?>;
    window.EVENT_TIMEZONE = <?php echo json_encode($eventTz); ?>;
    </script>

    <?php if (!$showEventListing): ?>
    <!-- Venues data for Gantt chart -->
    <script>
        window.VENUES_DATA = <?php echo json_encode(array_values($venues), JSON_UNESCAPED_UNICODE); ?>;
    </script>

    <!-- Subscribe Modal -->
    <div id="subscribeModal" class="req-modal-overlay">
        <div class="req-modal" style="max-width:480px;">
            <div class="req-modal-header">
                <h2 data-i18n="subscribe.title">🔔 Subscribe to Calendar</h2>
                <button onclick="closeSubscribeModal()" class="req-close">&times;</button>
            </div>
            <div class="req-modal-body">
                <p style="margin:0 0 12px;color:#555;" data-i18n="subscribe.desc">Subscribe ครั้งเดียว ปฏิทินของคุณจะอัปเดตอัตโนมัติเมื่อมีการเพิ่ม/แก้ไข program</p>

                <!-- webcal:// — Apple Calendar / iOS / Thunderbird -->
                <a id="subscribeWebcalLink" href="#" class="btn btn-subscribe" style="display:block;text-align:center;text-decoration:none;margin-bottom:4px;" data-i18n="subscribe.openApp">🔗 เปิดใน Calendar App (webcal://)</a>
                <p style="font-size:0.75em;color:#999;margin:0 0 14px;text-align:center;" data-i18n="subscribe.webcalHint">🍎 Apple Calendar · 📱 iOS · 🦅 Thunderbird</p>

                <!-- https:// URL — Google Calendar, Outlook, manual -->
                <p style="font-size:0.85em;color:#666;margin:0 0 6px;font-weight:500;" data-i18n="subscribe.orCopy">หรือ copy URL สำหรับ Google Calendar / Outlook:</p>
                <div style="display:flex;gap:8px;align-items:center;min-width:0;">
                    <input id="subscribeFeedUrl" type="text" readonly
                        style="flex:1;min-width:0;font-size:1rem;padding:7px 10px;border:1px solid #ddd;border-radius:6px;background:#f9f9f9;color:#333;overflow:hidden;text-overflow:ellipsis;">
                    <button onclick="copyFeedUrl()" class="btn btn-secondary" style="white-space:nowrap;flex-shrink:0;width:auto;padding:8px 14px;" data-i18n="subscribe.copy">📋 Copy</button>
                </div>
                <p id="subscribeCopied" style="display:none;color:#388e3c;font-size:0.85em;margin:6px 0 0;" data-i18n="subscribe.copied">✅ Copy แล้ว!</p>

                <!-- Outlook-specific instructions -->
                <div style="margin-top:14px;padding:10px 12px;background:#f0f4ff;border-radius:8px;border-left:3px solid #4a6cf7;">
                    <p style="margin:0 0 4px;font-size:0.82em;font-weight:600;color:#4a6cf7;" data-i18n="subscribe.outlookTitle">📧 Microsoft Outlook</p>
                    <p style="margin:0;font-size:0.78em;color:#555;line-height:1.5;" data-i18n="subscribe.outlookHint">Copy URL ด้านบน → เปิด Outlook → Calendar → Add calendar → Subscribe from web → วาง URL</p>
                </div>

                <!-- Sync frequency notice -->
                <div style="margin-top:12px;padding:10px 12px;background:#fffbf0;border-radius:8px;border-left:3px solid #f59e0b;">
                    <p style="margin:0 0 6px;font-size:0.82em;font-weight:600;color:#92400e;" data-i18n="subscribe.syncTitle">⏱ รอบการอัปเดตของแต่ละบริการ</p>
                    <p style="margin:0 0 6px;font-size:0.76em;color:#78350f;line-height:1.4;" data-i18n="subscribe.syncNote">ปฏิทินแต่ละแอปมีรอบดึงข้อมูลไม่เท่ากัน ข้อมูลอาจไม่แสดงทันทีหลังอัปเดต</p>
                    <ul style="margin:0;padding-left:16px;font-size:0.76em;color:#555;line-height:1.7;">
                        <li data-i18n="subscribe.syncApple">🍎 Apple Calendar / iOS — ~1 ชั่วโมง</li>
                        <li data-i18n="subscribe.syncGoogle">🌐 Google Calendar — ~24 ชั่วโมง</li>
                        <li data-i18n="subscribe.syncOutlookDesktop">📧 Outlook Desktop — ~24 ชั่วโมง (กด Refresh เพื่อดึงทันที)</li>
                        <li data-i18n="subscribe.syncOutlookWeb">🌐 Outlook.com / New Outlook — ~3 วัน (remove แล้ว subscribe ใหม่เพื่อดึงทันที)</li>
                        <li data-i18n="subscribe.syncThunderbird">🦅 Thunderbird — ~1 ชั่วโมง</li>
                    </ul>
                </div>
            </div>
            <div class="req-modal-footer">
                <button onclick="closeSubscribeModal()" class="btn btn-secondary" data-i18n="modal.cancel">ยกเลิก</button>
            </div>
        </div>
    </div>

    <!-- Event Picture Lightbox -->
    <div id="eventPicLightbox" class="ep-lightbox" style="display:none" role="dialog" aria-modal="true">
        <div class="ep-lightbox-overlay" onclick="epCloseLightbox()"></div>
        <button class="ep-lightbox-close" onclick="epCloseLightbox()">&times;</button>
        <button class="ep-lightbox-prev" onclick="epLightboxNav(-1)">&#10094;</button>
        <button class="ep-lightbox-next" onclick="epLightboxNav(1)">&#10095;</button>
        <div class="ep-lightbox-inner">
            <img id="epLightboxImg" src="" alt="">
            <p id="epLightboxCaption" class="ep-lightbox-caption"></p>
        </div>
    </div>

    <!-- Request Modal -->
    <div id="requestModal" class="req-modal-overlay">
        <div class="req-modal">
            <div class="req-modal-header">
                <h2 id="modalTitle" data-i18n="modal.addTitle">📝 แจ้งเพิ่ม Program</h2>
                <button onclick="closeRequestModal()" class="req-close">&times;</button>
            </div>
            <form id="requestForm" onsubmit="submitRequest(event)">
                <input type="hidden" id="reqEventId" value="">
                <div class="req-modal-body">

                    <!-- Type toggle -->
                    <div class="req-group req-type-group">
                        <label data-i18n="modal.requestType">ประเภทคำขอ *</label>
                        <div class="req-types">
                            <label class="req-type">
                                <input type="radio" name="reqTypeRadio" id="reqTypeAdd" value="add" checked onchange="onReqTypeChange()">
                                <span data-i18n="modal.typeAdd">📝 เพิ่ม program ใหม่</span>
                            </label>
                            <label class="req-type">
                                <input type="radio" name="reqTypeRadio" id="reqTypeModify" value="modify" onchange="onReqTypeChange()">
                                <span data-i18n="modal.typeModify">✏️ แก้ไข program ที่มีอยู่</span>
                            </label>
                        </div>
                    </div>

                    <!-- Program selector (modify only) -->
                    <div class="req-group" id="reqProgramSelectorGroup" style="display:none">
                        <label data-i18n="modal.selectProgram">เลือก program ที่ต้องการแก้ไข *</label>
                        <select id="reqProgramSelector" onchange="onProgramSelected()">
                            <option value="" data-i18n="modal.selectProgramPlaceholder">-- เลือก program --</option>
                        </select>
                    </div>

                    <div class="req-group"><label data-i18n="modal.programName">ชื่อ Program *</label><input type="text" id="reqTitle" required maxlength="200"></div>
                    <div class="req-row">
                        <div class="req-group">
                            <label data-i18n="modal.venue">สถานที่</label>
                            <select id="reqLocation">
                                <option value="" data-i18n="modal.selectVenue">-- เลือก --</option>
                                <?php foreach ($venues as $v): ?><option value="<?php echo htmlspecialchars($v); ?>"><?php echo htmlspecialchars($v); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="req-group"><label data-i18n="modal.categories">Categories</label><input type="text" id="reqCategories" maxlength="500"></div>
                    </div>
                    <div class="req-row">
                        <div class="req-group"><label data-i18n="modal.startDate">วันที่เริ่ม *</label><input type="date" id="reqDate" required onchange="onReqStartDateChange(this.value)"></div>
                        <div class="req-group"><label data-i18n="modal.startTime">เวลาเริ่ม *</label><input type="time" id="reqStart" required></div>
                    </div>
                    <div class="req-row">
                        <div class="req-group"><label data-i18n="modal.endDate">วันที่สิ้นสุด *</label><input type="date" id="reqEndDate" required></div>
                        <div class="req-group"><label data-i18n="modal.endTime">เวลาสิ้นสุด *</label><input type="time" id="reqEnd" required></div>
                    </div>
                    <div class="req-group"><label data-i18n="modal.description">รายละเอียด</label><textarea id="reqDesc" rows="2" maxlength="2000"></textarea></div>
                    <hr style="margin:15px 0;border:none;border-top:1px solid #ddd;">
                    <div class="req-row">
                        <div class="req-group"><label data-i18n="modal.requesterName">ชื่อผู้แจ้ง *</label><input type="text" id="reqName" required maxlength="100"></div>
                        <div class="req-group"><label>Email</label><input type="email" id="reqEmail" maxlength="200"></div>
                    </div>
                    <div class="req-group"><label data-i18n="modal.requesterNote">หมายเหตุ</label><textarea id="reqNote" rows="2" maxlength="1000" data-i18n-placeholder="modal.notePlaceholder" placeholder="แหล่งข้อมูล, เหตุผล"></textarea></div>
                </div>
                <div class="req-modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeRequestModal()" data-i18n="modal.cancel">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="reqSubmitBtn" data-i18n="modal.submit">ส่งคำขอ</button>
                </div>
            </form>
        </div>
    </div>



    <script>
    const VENUE_MODE = '<?php echo $currentVenueMode; ?>';
    <?php if ($currentVenueMode === 'calendar'): ?>
    <?php
        // Attach artist link data (id + name) to each calendar event so the day
        // panel + detail modal can render clickable /artist/{id} links — mirrors
        // the list-view logic at the program-categories cell above.
        $calendarEvents = array_map(function($ev) use ($programArtistIdMap) {
            $pid = (int)($ev['id'] ?? 0);
            $links = $programArtistIdMap[$pid] ?? [];
            if (empty($links)) {
                // fallback to raw categories text (id = null)
                foreach (array_filter(array_map('trim', $ev['categoriesArray'] ?? [])) as $c) {
                    $links[] = ['id' => null, 'name' => $c];
                }
            }
            $ev['artists'] = array_map(function($l) {
                return ['id' => $l['id'] ?? null, 'name' => $l['name']];
            }, $links);
            return $ev;
        }, array_values($filteredEvents));
    ?>
    window.CALENDAR_EVENTS = <?php echo json_encode($calendarEvents, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    <?php endif; ?>
    const EVENT_SLUG = '<?php echo htmlspecialchars($eventSlug); ?>';

    // Date jump bar: fixed position, show/hide on scroll, highlight active date
    (function() {
        const jumpBar = document.getElementById('dateJumpBar');
        if (!jumpBar) return;

        const jumpBtns = jumpBar.querySelectorAll('.date-jump-btn');
        const jumpButtons = document.getElementById('jumpButtons');
        const jumpPrev = document.getElementById('jumpPrev');
        const jumpNext = document.getElementById('jumpNext');
        const daySections = document.querySelectorAll('.day-section[id^="day-"]');
        const calendarContainer = document.querySelector('.calendar-container');
        if (daySections.length === 0 || !calendarContainer) return;

        const jumpBarHeight = 56; // approximate bar height for offset

        // Position the bar to match container width
        function positionBar() {
            const container = document.querySelector('.container');
            if (container) {
                const rect = container.getBoundingClientRect();
                jumpBar.style.left = rect.left + 'px';
                jumpBar.style.width = rect.width + 'px';
                jumpBar.style.maxWidth = rect.width + 'px';
            }
        }

        // Update arrow button visibility based on scroll position of button strip
        function updateArrows() {
            if (!jumpButtons || !jumpPrev || !jumpNext) return;
            const atStart = jumpButtons.scrollLeft <= 2;
            const atEnd = jumpButtons.scrollLeft >= jumpButtons.scrollWidth - jumpButtons.clientWidth - 2;
            jumpPrev.disabled = atStart;
            jumpNext.disabled = atEnd;
        }

        // Show/hide based on scroll position
        function updateVisibility() {
            const calRect = calendarContainer.getBoundingClientRect();
            // Show when calendar top is above viewport
            if (calRect.top < 0 && calRect.bottom > jumpBarHeight) {
                jumpBar.classList.add('visible');
                positionBar();
                updateArrows();
            } else {
                jumpBar.classList.remove('visible');
            }
        }

        window.addEventListener('scroll', updateVisibility, { passive: true });
        window.addEventListener('resize', function() {
            if (jumpBar.classList.contains('visible')) { positionBar(); updateArrows(); }
        }, { passive: true });

        // Arrow scroll function (called from HTML onclick)
        window.scrollJumpBar = function(delta) {
            if (jumpButtons) {
                jumpButtons.scrollBy({ left: delta, behavior: 'smooth' });
            }
        };

        // Sync arrow state when user scrolls the button strip (touch or mouse)
        if (jumpButtons) {
            jumpButtons.addEventListener('scroll', updateArrows, { passive: true });

            // Mousewheel → horizontal scroll on desktop
            jumpButtons.addEventListener('wheel', function(e) {
                e.preventDefault();
                jumpButtons.scrollLeft += e.deltaY || e.deltaX;
                updateArrows();
            }, { passive: false });
        }

        // Smooth scroll with offset for fixed bar
        jumpBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const target = document.getElementById(targetId);
                if (target) {
                    const y = target.getBoundingClientRect().top + window.pageYOffset - jumpBarHeight;
                    window.scrollTo({ top: y, behavior: 'smooth' });
                    // Update active state
                    jumpBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });

        // IntersectionObserver to highlight current visible date
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = entry.target.id;
                    jumpBtns.forEach(b => {
                        b.classList.toggle('active', b.getAttribute('href') === '#' + id);
                    });
                }
            });
        }, { rootMargin: '-60px 0px -60% 0px', threshold: 0 });

        daySections.forEach(section => observer.observe(section));
    })();

    var _reqProgramsLoaded = false;

    async function openRequestModal() {
        document.getElementById('requestForm').reset();
        document.getElementById('reqTypeAdd').checked = true;
        document.getElementById('reqEventId').value = '';
        document.getElementById('reqProgramSelectorGroup').style.display = 'none';
        document.getElementById('modalTitle').setAttribute('data-i18n', 'modal.addTitle');
        document.getElementById('modalTitle').textContent = translations[currentLang]['modal.addTitle'] || '📝 แจ้งเพิ่ม Program';
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('reqDate').value = today;
        document.getElementById('reqEndDate').value = today;
        // โหลด programs เข้า dropdown ครั้งเดียว
        if (!_reqProgramsLoaded) {
            await loadProgramsIntoSelector();
            _reqProgramsLoaded = true;
        }
        document.getElementById('requestModal').classList.add('active');
    }

    function onReqTypeChange() {
        const isModify = document.getElementById('reqTypeModify').checked;
        document.getElementById('reqProgramSelectorGroup').style.display = isModify ? 'block' : 'none';
        const titleKey = isModify ? 'modal.editTitle' : 'modal.addTitle';
        document.getElementById('modalTitle').setAttribute('data-i18n', titleKey);
        document.getElementById('modalTitle').textContent = translations[currentLang][titleKey] || (isModify ? '✏️ แจ้งแก้ไข Program' : '📝 แจ้งเพิ่ม Program');
        if (!isModify) {
            document.getElementById('reqProgramSelector').value = '';
            document.getElementById('reqEventId').value = '';
            ['reqTitle','reqOrganizer','reqCategories','reqDesc'].forEach(function(id) {
                document.getElementById(id).value = '';
            });
        }
    }

    function onReqStartDateChange(val) {
        const endDate = document.getElementById('reqEndDate');
        if (!endDate.value || endDate.value < val) {
            endDate.value = val;
        }
    }

    function onProgramSelected() {
        const sel = document.getElementById('reqProgramSelector');
        const opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) return;
        var prog = {};
        try { prog = JSON.parse(opt.dataset.program || '{}'); } catch(e) {}
        document.getElementById('reqEventId').value = prog.id || '';
        document.getElementById('reqTitle').value = prog.title || '';
        document.getElementById('reqCategories').value = prog.categories || '';
        document.getElementById('reqDesc').value = prog.description || '';
        if (prog.start) {
            var startStr = prog.start.replace(' ', 'T');
            document.getElementById('reqDate').value = startStr.slice(0, 10);
            document.getElementById('reqStart').value = startStr.slice(11, 16);
        }
        if (prog.end) {
            var endStr = prog.end.replace(' ', 'T');
            document.getElementById('reqEndDate').value = endStr.slice(0, 10);
            document.getElementById('reqEnd').value = endStr.slice(11, 16);
        }
        // match venue dropdown
        var locSel = document.getElementById('reqLocation');
        for (var i = 0; i < locSel.options.length; i++) {
            if (locSel.options[i].value === (prog.location || '')) {
                locSel.selectedIndex = i; break;
            }
        }
    }

    async function loadProgramsIntoSelector() {
        try {
            var url = BASE_PATH + '/api/request?action=programs' + (EVENT_SLUG ? '&event=' + encodeURIComponent(EVENT_SLUG) : '');
            var res = await fetch(url);
            var data = await res.json();
            var sel = document.getElementById('reqProgramSelector');
            sel.innerHTML = '<option value="">' + (translations[currentLang]['modal.selectProgramPlaceholder'] || '-- เลือก program --') + '</option>';
            var programs = Array.isArray(data) ? data : (data.data || data.programs || []);
            programs.forEach(function(p) {
                var opt = document.createElement('option');
                opt.value = p.id;
                var startDate = p.start ? p.start.slice(5, 10).replace('-', '/') : '';
                var startTime = p.start ? p.start.slice(11, 16) : '';
                opt.textContent = (startDate ? startDate + ' ' : '') + (startTime ? startTime + ' ' : '') + (p.title || '');
                opt.dataset.program = JSON.stringify(p);
                sel.appendChild(opt);
            });
        } catch(e) { console.error('Failed to load programs', e); }
    }

    function closeRequestModal() {
        document.getElementById('requestModal').classList.remove('active');
    }

    async function submitRequest(ev) {
        ev.preventDefault();
        const btn = document.getElementById('reqSubmitBtn');
        const type = document.querySelector('input[name="reqTypeRadio"]:checked')?.value || 'add';
        const startDate = document.getElementById('reqDate').value;
        const endDate = document.getElementById('reqEndDate').value;

        const data = {
            type,
            program_id: type === 'modify' ? document.getElementById('reqEventId').value : null,
            title: document.getElementById('reqTitle').value,
            start: startDate + ' ' + document.getElementById('reqStart').value + ':00',
            end: endDate + ' ' + document.getElementById('reqEnd').value + ':00',
            location: document.getElementById('reqLocation').value,
            organizer: '',
            description: document.getElementById('reqDesc').value,
            categories: document.getElementById('reqCategories').value,
            requester_name: document.getElementById('reqName').value,
            requester_email: document.getElementById('reqEmail').value,
            requester_note: document.getElementById('reqNote').value,
            event_slug: EVENT_SLUG
        };

        btn.disabled = true;
        btn.textContent = translations[currentLang]['modal.submitting'] || 'กำลังส่ง...';

        try {
            const res = await fetch(BASE_PATH + '/api/request?action=submit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                alert(translations[currentLang]['modal.submitSuccess'] || 'ส่งคำขอสำเร็จ!');
                closeRequestModal();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (e) {
            alert(translations[currentLang]['modal.submitError'] || 'ไม่สามารถส่งได้');
        } finally {
            btn.disabled = false;
            btn.textContent = translations[currentLang]['modal.submit'] || 'ส่งคำขอ';
        }
    }

    // เพิ่ม filter value และ reload หน้า (ไม่ duplicate, ใช้ได้ทั้งมีและไม่มี filter อยู่ก่อน)
    function appendFilter(type, value) {
        const url = new URL(window.location.href);
        const params = url.searchParams;
        const currentValues = params.getAll(type + '[]');
        if (!currentValues.includes(value)) {
            params.append(type + '[]', value);
        }
        window.location.href = url.toString();
    }

    function removeFilter(type, value) {
        const url = new URL(window.location.href);
        const params = url.searchParams;

        // ดึงค่าปัจจุบันของ filter type นั้น
        const currentValues = params.getAll(type + '[]');

        // ลบค่าที่ต้องการออก
        const newValues = currentValues.filter(v => v !== value);

        // ลบ parameter เดิมทั้งหมด
        params.delete(type + '[]');

        // เพิ่ม parameter ใหม่ (ยกเว้นค่าที่ลบ)
        newValues.forEach(v => params.append(type + '[]', v));

        // reload หน้าด้วย URL ใหม่
        window.location.href = url.toString();
    }

    // Switch event (multi-event selector) - uses clean URL /event/slug
    function switchEvent(slug) {
        if (slug && slug !== DEFAULT_EVENT_SLUG) {
            window.location.href = BASE_PATH + '/event/' + slug;
        } else {
            window.location.href = BASE_PATH + '/';
        }
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            if (document.getElementById('subscribeModal').classList.contains('active')) {
                closeSubscribeModal();
            } else if (document.getElementById('requestModal').classList.contains('active')) {
                closeRequestModal();
            }
        }
    });
    </script>
    <?php endif; ?>

    <?php if ($showEventListing): ?>
    <script>
    (function () {
        // Build modal element (injected once)
        const overlay = document.createElement('div');
        overlay.className = 'event-modal-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.style.display = 'none';
        overlay.innerHTML =
            '<div class="event-modal">' +
                '<div class="event-modal-header">' +
                    '<button class="event-modal-close" aria-label="ปิด">&#x2715;</button>' +
                    '<h3 class="event-modal-name"></h3>' +
                    '<div class="event-modal-dates"></div>' +
                    '<span class="event-modal-badge program-card-badge"></span>' +
                '</div>' +
                '<div class="event-modal-body">' +
                    '<div class="event-modal-description"></div>' +
                    '<div class="event-modal-meta"></div>' +
                    '<a class="event-modal-link" href="#"></a>' +
                '</div>' +
            '</div>';
        document.body.appendChild(overlay);

        function openModal(card) {
            var nameEl   = card.querySelector('.program-card-name');
            var datesEl  = card.querySelector('.program-card-dates');
            var badgeEl  = card.querySelector('.program-card-badge');
            var descEl   = card.querySelector('.program-card-description');
            var metaEl   = card.querySelector('.program-card-meta');
            var linkEl   = card.querySelector('.program-card-link');

            overlay.querySelector('.event-modal-name').textContent  = nameEl  ? nameEl.textContent.trim()  : '';
            overlay.querySelector('.event-modal-dates').textContent = datesEl ? datesEl.textContent.trim() : '';

            var modalBadge = overlay.querySelector('.event-modal-badge');
            if (badgeEl) {
                modalBadge.textContent = badgeEl.textContent.trim();
                modalBadge.className   = 'event-modal-badge program-card-badge ' +
                    (badgeEl.classList.contains('ongoing')  ? 'ongoing'  :
                     badgeEl.classList.contains('upcoming') ? 'upcoming' : 'past');
                modalBadge.style.display = '';
            } else {
                modalBadge.style.display = 'none';
            }

            var modalDesc = overlay.querySelector('.event-modal-description');
            if (descEl) {
                modalDesc.innerHTML      = descEl.innerHTML;
                modalDesc.style.display  = '';
                // Remove line-clamp inside modal
                modalDesc.style.webkitLineClamp = 'unset';
                modalDesc.style.display = 'block';
                modalDesc.style.overflow = 'visible';
            } else {
                modalDesc.style.display = 'none';
            }

            var modalMeta = overlay.querySelector('.event-modal-meta');
            if (metaEl && metaEl.children.length) {
                modalMeta.innerHTML     = metaEl.innerHTML;
                modalMeta.style.display = '';
            } else {
                modalMeta.style.display = 'none';
            }

            var modalLink = overlay.querySelector('.event-modal-link');
            if (linkEl) {
                modalLink.href           = linkEl.getAttribute('href');
                modalLink.textContent    = linkEl.textContent.trim();
                modalLink.style.display  = '';
            } else {
                modalLink.style.display  = 'none';
            }

            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            overlay.style.display = 'none';
            document.body.style.overflow = '';
        }

        // Wire up each description
        document.querySelectorAll('.program-card-description').forEach(function (desc) {
            var card = desc.closest('.program-card');
            if (!card) return;

            desc.addEventListener('click', function () { openModal(card); });

            // Show "read more" button only when text is actually clamped
            if (desc.scrollHeight > desc.clientHeight + 2) {
                var btn = document.createElement('button');
                btn.type      = 'button';
                btn.className = 'program-card-readmore';
                btn.setAttribute('data-i18n', 'listing.readMore');
                btn.textContent = (typeof translations !== 'undefined' && translations[window.currentLang || 'th'])
                    ? (translations[window.currentLang || 'th']['listing.readMore'] || '▼ อ่านเพิ่มเติม')
                    : '▼ อ่านเพิ่มเติม';
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    openModal(card);
                });
                desc.insertAdjacentElement('afterend', btn);
            }
        });

        // Homepage event-grid cards (event-card is an <a> — prevent navigation on desc click)
        document.querySelectorAll('.event-card-description').forEach(function (desc) {
            var card = desc.closest('.event-card');
            if (!card) return;

            desc.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                openEventCardModal(card);
            });

            if (desc.scrollHeight > desc.clientHeight + 2) {
                var btn = document.createElement('button');
                btn.type      = 'button';
                btn.className = 'program-card-readmore';
                btn.setAttribute('data-i18n', 'listing.readMore');
                btn.textContent = (typeof translations !== 'undefined' && translations[window.currentLang || 'th'])
                    ? (translations[window.currentLang || 'th']['listing.readMore'] || '▼ อ่านเพิ่มเติม')
                    : '▼ อ่านเพิ่มเติม';
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openEventCardModal(card);
                });
                desc.insertAdjacentElement('afterend', btn);
            }
        });

        function openEventCardModal(card) {
            var nameEl  = card.querySelector('.event-card-name');
            var dateEl  = card.querySelector('.event-card-date');
            var badgeEl = card.querySelector('.event-card-badge');
            var descEl  = card.querySelector('.event-card-description');

            overlay.querySelector('.event-modal-name').textContent  = nameEl ? nameEl.textContent.trim() : '';
            overlay.querySelector('.event-modal-dates').textContent = dateEl ? dateEl.textContent.trim() : '';

            var modalBadge = overlay.querySelector('.event-modal-badge');
            if (badgeEl) {
                modalBadge.textContent = badgeEl.textContent.trim();
                modalBadge.className   = 'event-modal-badge program-card-badge ' +
                    (badgeEl.classList.contains('ongoing')  ? 'ongoing'  :
                     badgeEl.classList.contains('upcoming') ? 'upcoming' : 'past');
                modalBadge.style.display = '';
            } else {
                modalBadge.style.display = 'none';
            }

            var modalDesc = overlay.querySelector('.event-modal-description');
            if (descEl) {
                modalDesc.innerHTML             = descEl.innerHTML;
                modalDesc.style.webkitLineClamp = 'unset';
                modalDesc.style.display         = 'block';
                modalDesc.style.overflow        = 'visible';
            } else {
                modalDesc.style.display = 'none';
            }

            overlay.querySelector('.event-modal-meta').style.display = 'none';

            var modalLink = overlay.querySelector('.event-modal-link');
            modalLink.href          = card.getAttribute('href');
            modalLink.textContent   = '📋 ดูตารางเวลา';
            modalLink.style.display = '';

            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Close on overlay backdrop click
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal();
        });

        overlay.querySelector('.event-modal-close').addEventListener('click', closeModal);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.style.display !== 'none') closeModal();
        });
    })();
    </script>

    <?php if (count($heroSlides) >= 1): ?>
    <script>
    // ── Hero Carousel ──────────────────────────────────────────────────────────
    (function() {
        var track    = document.getElementById('heroTrack');
        var dots     = document.querySelectorAll('.hero-dot');
        var total    = <?php echo count($heroSlides); ?>;
        var current  = 0;
        var timer    = null;

        function goTo(idx) {
            current = (idx + total) % total;
            track.style.transform = 'translateX(-' + (current * 100) + '%)';
            dots.forEach(function(d, i) { d.classList.toggle('active', i === current); });
        }

        window.heroNav = function(dir) { clearTimer(); goTo(current + dir); startTimer(); };
        window.heroGoTo = function(idx) { clearTimer(); goTo(idx); startTimer(); };

        function startTimer() {
            timer = setInterval(function() { goTo(current + 1); }, 5000);
        }
        function clearTimer() { if (timer) { clearInterval(timer); timer = null; } }

        // Pause on hover
        var carousel = document.getElementById('heroCarousel');
        carousel.addEventListener('mouseenter', clearTimer);
        carousel.addEventListener('mouseleave', startTimer);

        // Swipe support
        var touchStartX = 0;
        carousel.addEventListener('touchstart', function(e) { touchStartX = e.changedTouches[0].clientX; }, {passive: true});
        carousel.addEventListener('touchend', function(e) {
            var dx = e.changedTouches[0].clientX - touchStartX;
            if (Math.abs(dx) > 40) { clearTimer(); goTo(current + (dx < 0 ? 1 : -1)); startTimer(); }
        });

        // Slide click → navigate to event
        track.addEventListener('click', function(e) {
            var slide = e.target.closest('.hero-slide');
            if (!slide) return;
            var link = slide.querySelector('.hero-cta');
            if (link && !e.target.closest('.hero-cta')) {
                window.location.href = link.href;
            }
        });

        if (total > 1) startTimer();
    })();
    </script>
    <?php endif; ?>

    <?php if ($showEventListing): ?>
    <script>
    // ── Live Now strip (Homepage) ──────────────────────────────────────────────
    // Programs are emitted as ISO-8601-with-offset (absolute instants); the client
    // classifies live / starting-soon against its own clock so cross-timezone events
    // and the 1-hour listing cache never produce a stale "live" state.
    window.LIVE_PROGRAMS = <?= json_encode($livePrograms ?: [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    (function() {
        var SOON_MS = 60 * 60 * 1000; // starting-soon window: 60 minutes
        var strip = document.getElementById('liveNowStrip');
        if (!strip || !Array.isArray(window.LIVE_PROGRAMS) || window.LIVE_PROGRAMS.length === 0) return;

        var curGroup  = document.getElementById('liveNowCurrentGroup');
        var soonGroup = document.getElementById('liveNowSoonGroup');
        var curRows   = document.getElementById('liveNowCurrentRows');
        var soonRows  = document.getElementById('liveNowSoonRows');

        var userTz = '';
        try { userTz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}

        function _liveLang() { return window.currentLang || localStorage.getItem('lang') || 'th'; }
        function _liveT(key) {
            var lang = _liveLang();
            var dict = (typeof translations !== 'undefined' && translations[lang]) ? translations[lang] : null;
            return (dict && dict[key]) ? dict[key] : key;
        }
        function _esc(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }
        // event-local "HH:MM" from ISO-with-offset (chars 11..16, no TZ conversion)
        function _evtHHMM(iso) { return (typeof iso === 'string' && iso.length >= 16) ? iso.slice(11, 16) : ''; }
        // user-local "HH:MM" from an absolute instant
        function _userHHMM(d) {
            try {
                return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: userTz || undefined });
            } catch (e) { return ''; }
        }

        function renderRow(p, kind, now) {
            var s = new Date(p.start_iso);
            var e = p.end_iso ? new Date(p.end_iso) : null;
            var title = _esc(p.title || p.categories || '');

            var html = '<div class="live-now-row' + (kind === 'live' ? ' is-live' : '') + '">';
            html += '<a class="live-now-link" href="' + _esc(p.url) + '">';
            if (kind === 'live') html += '<span class="live-now-badge" aria-hidden="true"></span>';
            html += '<span class="live-now-main">';
            html += '<span class="live-now-title">' + title + '</span>';
            var meta = [];
            if (p.location)   meta.push('📍 ' + _esc(p.location));
            if (p.event_name) meta.push('@ ' + _esc(p.event_name));
            if (meta.length) html += '<span class="live-now-meta">' + meta.join(' · ') + '</span>';
            html += '</span>'; // .live-now-main

            var evt = _evtHHMM(p.start_iso);
            var evtEnd = _evtHHMM(p.end_iso);
            var timeStr = evt + (evtEnd ? '–' + evtEnd : '');
            html += '<span class="live-now-time">' + _esc(timeStr);
            if (userTz && p.event_tz && p.event_tz !== userTz) {
                var ul = _userHHMM(s);
                if (ul) html += ' <span class="live-now-time-local">(' + _esc(ul) + ' ' + _esc(_liveT('tz.localTime')) + ')</span>';
            }
            html += '</span>';

            var cd = '';
            if (kind === 'live' && e && !isNaN(e)) {
                cd = _liveT('live.timeLeft').replace('{n}', Math.max(0, Math.round((e - now) / 60000)));
            } else if (kind === 'soon') {
                cd = _liveT('live.startsIn').replace('{n}', Math.max(0, Math.round((s - now) / 60000)));
            }
            if (cd) html += '<span class="live-now-countdown">' + _esc(cd) + '</span>';
            html += '</a>'; // .live-now-link
            if (p.stream_url) {
                html += '<a class="live-now-watch" href="' + _esc(p.stream_url) + '" target="_blank" rel="noopener">' + _esc(_liveT('live.watch')) + '</a>';
            }
            html += '</div>';
            return html;
        }

        function renderLiveNow() {
            var now = new Date();
            var live = [], soon = [];
            window.LIVE_PROGRAMS.forEach(function(p) {
                var s = new Date(p.start_iso);
                if (isNaN(s)) return;
                var e = p.end_iso ? new Date(p.end_iso) : null;
                var hasEnd = e && !isNaN(e);
                if (s <= now && (!hasEnd || e > now)) live.push(p);
                else if (now < s && (s - now) <= SOON_MS) soon.push(p);
            });
            soon.sort(function(a, b) { return new Date(a.start_iso) - new Date(b.start_iso); });

            curRows.innerHTML  = live.map(function(p) { return renderRow(p, 'live', now); }).join('');
            soonRows.innerHTML = soon.map(function(p) { return renderRow(p, 'soon', now); }).join('');
            curGroup.hidden  = live.length === 0;
            soonGroup.hidden = soon.length === 0;
            strip.hidden     = (live.length === 0 && soon.length === 0);
        }

        renderLiveNow();
        setInterval(renderLiveNow, 30000);
        window.addEventListener('appLangChange', renderLiveNow);
    })();
    </script>
    <?php endif; ?>

    <?php if (!empty($listingCalData)): ?>
    <script>
    // ── Listing Calendar (Homepage) ────────────────────────────────────────────
    const LISTING_CAL_DATA = <?= json_encode($listingCalData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    const LCAL_MONTHS_LONG = {
        th: ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'],
        en: ['January','February','March','April','May','June','July','August','September','October','November','December'],
        ja: ['1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月']
    };
    const LCAL_MONTHS = {
        th: ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'],
        en: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        ja: ['1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月']
    };
    const LCAL_DAYS = {
        th: ['อา','จ','อ','พ','พฤ','ศ','ส'],
        en: ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],
        ja: ['日','月','火','水','木','金','土']
    };

    // Build sorted list of months that have programs
    const _lcalDates = Object.keys(LISTING_CAL_DATA).sort();
    const _lcalMonthSet = {};
    _lcalDates.forEach(function(d) { _lcalMonthSet[d.slice(0,7)] = true; });
    const LCAL_MONTH_KEYS = Object.keys(_lcalMonthSet).sort();

    // Default to current month if it exists, else nearest future month, else last month
    (function() {
        var todayKey = new Date().toISOString().slice(0,7);
        var idx = LCAL_MONTH_KEYS.indexOf(todayKey);
        if (idx < 0) {
            // find nearest upcoming month
            for (var i = 0; i < LCAL_MONTH_KEYS.length; i++) {
                if (LCAL_MONTH_KEYS[i] >= todayKey) { idx = i; break; }
            }
        }
        window._lcalIdx = idx >= 0 ? idx : LCAL_MONTH_KEYS.length - 1;
    })();

    function _lcalYM() {
        var parts = (LCAL_MONTH_KEYS[window._lcalIdx] || '').split('-');
        return { year: parseInt(parts[0]), month: parseInt(parts[1]) - 1 };
    }

    function renderLcal(lang) {
        var grid  = document.getElementById('lcalGrid');
        var title = document.getElementById('lcalTitle');
        var prev  = document.getElementById('lcalPrevBtn');
        var next  = document.getElementById('lcalNextBtn');
        if (!grid) return;

        var ym = _lcalYM();
        var year = ym.year, month = ym.month;
        var months = LCAL_MONTHS_LONG[lang] || LCAL_MONTHS_LONG.th;
        var days   = LCAL_DAYS[lang] || LCAL_DAYS.th;
        var todayStr = new Date().toISOString().slice(0,10);

        if (title) title.textContent = months[month] + ' ' + year;
        if (prev) prev.disabled = (window._lcalIdx <= 0);
        if (next) next.disabled = (window._lcalIdx >= LCAL_MONTH_KEYS.length - 1);

        var html = '';
        for (var i = 0; i < 7; i++) {
            html += '<div class="listing-cal-dow">' + days[i] + '</div>';
        }

        var firstDay = new Date(year, month, 1).getDay();
        var daysInMonth = new Date(year, month + 1, 0).getDate();

        for (var i = 0; i < firstDay; i++) {
            html += '<div class="listing-cal-day other-month"></div>';
        }

        for (var d = 1; d <= daysInMonth; d++) {
            var mm = String(month + 1).padStart(2, '0');
            var dd = String(d).padStart(2, '0');
            var dateStr = year + '-' + mm + '-' + dd;
            var hasP = !!LISTING_CAL_DATA[dateStr];
            var isToday = (dateStr === todayStr);

            var cls = 'listing-cal-day';
            if (hasP)    cls += ' has-programs';
            if (isToday) cls += ' today';

            var onclick = hasP ? ' onclick="openLcalDayModal(\'' + dateStr + '\')"' : '';
            html += '<div class="' + cls + '"' + onclick + '>';
            html += '<span class="listing-cal-day-num">' + d + '</span>';
            if (hasP) html += '<span class="listing-cal-dot"></span>';
            html += '</div>';
        }

        var total = firstDay + daysInMonth;
        var trailing = (7 - (total % 7)) % 7;
        for (var i = 0; i < trailing; i++) {
            html += '<div class="listing-cal-day other-month"></div>';
        }

        grid.innerHTML = html;
    }

    function lcalNav(dir) {
        window._lcalIdx = Math.max(0, Math.min(LCAL_MONTH_KEYS.length - 1, window._lcalIdx + dir));
        renderLcal(window.currentLang || 'th');
    }

    function openLcalDayModal(dateStr) {
        var events = LISTING_CAL_DATA[dateStr];
        if (!events || !events.length) return;
        window._lcalActiveDate = dateStr;

        var d = new Date(dateStr + 'T00:00:00');
        var months = LCAL_MONTHS[window.currentLang] || LCAL_MONTHS.th;
        var days   = LCAL_DAYS[window.currentLang]   || LCAL_DAYS.th;
        var label  = d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear() + ' (' + days[d.getDay()] + ')';

        var titleEl = document.getElementById('lcalDayTitle');
        var bodyEl  = document.getElementById('lcalDayBody');
        if (titleEl) titleEl.textContent = label;
        if (bodyEl) {
            var lang = window.currentLang || 'th';
            var tr = (typeof translations !== 'undefined' && translations[lang]) || {};
            var todayStr = new Date().toISOString().slice(0,10);
            var html = '';
            events.forEach(function(ev) {
                var start = ev.start_date || '';
                var end   = ev.end_date   || start;
                var status = (end < todayStr) ? 'past'
                           : (start <= todayStr && end >= todayStr) ? 'ongoing'
                           : 'upcoming';
                var statusLabel = tr['listing.' + status] || status;
                var fmtDate = function(iso) {
                    if (!iso) return '';
                    var p = iso.slice(0,10).split('-');
                    return p[2] + '/' + p[1] + '/' + p[0];
                };
                var dStart = fmtDate(start);
                var dEnd   = fmtDate(end);
                var dateRange = dStart + (dEnd && dEnd !== dStart ? ' – ' + dEnd : '');
                var viewLabel = tr['listing.viewSchedule'] || '📋 ดูตารางเวลา';

                html += '<div class="lcal-event-card">';
                html += '<div class="lcal-event-card-header">';
                html += '<span class="lcal-event-card-name">' + _lcEsc(ev.name) + '</span>';
                if (dateRange) html += '<span class="lcal-event-card-dates">📅 ' + _lcEsc(dateRange) + '</span>';
                html += '</div>';
                html += '<div class="lcal-event-card-body">';
                html += '<span class="program-card-badge ' + status + '">' + _lcEsc(statusLabel) + '</span>';
                html += '<a href="' + BASE_PATH + '/event/' + _lcEsc(ev.slug) + '" class="lcal-event-link">' + _lcEsc(viewLabel) + '</a>';
                html += '</div></div>';
            });
            bodyEl.innerHTML = html;
        }

        var overlay = document.getElementById('lcalDayOverlay');
        if (overlay) overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeLcalDayModal() {
        var overlay = document.getElementById('lcalDayOverlay');
        if (overlay) overlay.classList.remove('open');
        document.body.style.overflow = '';
        window._lcalActiveDate = null;
    }

    function _lcEsc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var overlay = document.getElementById('lcalDayOverlay');
            if (overlay && overlay.classList.contains('open')) closeLcalDayModal();
        }
    });

    // Re-render listing calendar and day modal when language changes
    document.addEventListener('appLangChange', function(e) {
        var lang = e.detail && e.detail.lang;
        if (lang) {
            renderLcal(lang);
            if (window._lcalActiveDate) openLcalDayModal(window._lcalActiveDate);
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        renderLcal(window.currentLang || 'th');
    });
    </script>
    <?php endif; ?>
    <?php endif; ?>

<!-- Artist Display Picture Tooltip (shared, fixed-position) -->
<div id="artistDpTooltip" style="display:none;position:fixed;z-index:9999;pointer-events:none;text-align:center;background:#fff;border-radius:12px;padding:10px 12px 8px;box-shadow:0 6px 20px rgba(0,0,0,.18);border:1px solid rgba(0,0,0,.07);min-width:80px;transform:translateX(-50%)">
    <div id="artistDpTooltipImg" style="width:60px;height:60px;border-radius:50%;background:center/cover no-repeat #f3f4f6;margin:0 auto 5px;border:2px solid var(--sakura-light,#FFB7C5)"></div>
    <div id="artistDpTooltipName" style="font-size:.75rem;font-weight:600;color:#374151;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></div>
</div>
<script>
(function(){
    var tip = document.getElementById('artistDpTooltip');
    var tipImg = document.getElementById('artistDpTooltipImg');
    var tipName = document.getElementById('artistDpTooltipName');
    var activeEl = null;

    function show(el) {
        var pic = el.getAttribute('data-display-pic');
        var name = el.getAttribute('data-display-name');
        if (!pic) return;
        tipImg.style.backgroundImage = 'url(' + JSON.stringify(pic) + ')';
        tipName.textContent = name || '';
        tip.style.display = 'block';
        position(el);
    }
    function hide() {
        tip.style.display = 'none';
        activeEl = null;
    }
    function position(el) {
        var r = el.getBoundingClientRect();
        var tipH = tip.offsetHeight || 100;
        var cx = r.left + r.width / 2;
        var top = r.top - tipH - 8;
        if (top < 8) top = r.bottom + 8; // flip below if no room above
        tip.style.left = cx + 'px';
        tip.style.top  = top + 'px';
    }

    document.addEventListener('mouseover', function(e) {
        var el = e.target.closest('[data-display-pic]');
        if (el && el !== activeEl) {
            activeEl = el;
            show(el);
        } else if (!el && activeEl) {
            hide();
        }
    });
    document.addEventListener('mouseout', function(e) {
        var el = e.target.closest('[data-display-pic]');
        if (el) {
            var to = e.relatedTarget;
            if (!el.contains(to)) hide();
        }
    });
    // Also hide on scroll/resize
    window.addEventListener('scroll', hide, { passive: true });
    window.addEventListener('resize', hide, { passive: true });
})();
</script>

<?php if (MULTI_EVENT_MODE && count($activeEvents) > 1): ?>
<!-- Event Picker Modal -->
<div id="eventPickerModal" class="event-picker-overlay" onclick="if(event.target===this)closeEventPicker()">
    <div class="event-picker-modal">
        <div class="event-picker-modal-header">
            <span data-i18n="eventPicker.title">เลือก Event</span>
            <button class="event-picker-close" onclick="closeEventPicker()">✕</button>
        </div>
        <div class="event-picker-controls">
            <input type="search" id="eventPickerSearch"
                   class="event-picker-search"
                   placeholder="ค้นหา event..."
                   data-i18n-placeholder="eventPicker.searchPlaceholder"
                   oninput="filterEventPicker()"
                   autocomplete="off">
            <div class="event-picker-filter-tabs" id="eventPickerTabs">
                <button class="ep-tab active" data-status="all"     onclick="setEventPickerTab(this)" data-i18n="eventPicker.all">ทั้งหมด</button>
                <button class="ep-tab"         data-status="ongoing" onclick="setEventPickerTab(this)" data-i18n="listing.ongoing">กำลังจัดงาน</button>
                <button class="ep-tab"         data-status="upcoming" onclick="setEventPickerTab(this)" data-i18n="listing.upcoming">กำลังจะมาถึง</button>
                <button class="ep-tab"         data-status="past"    onclick="setEventPickerTab(this)" data-i18n="listing.past">จบแล้ว</button>
            </div>
        </div>
        <div class="event-picker-grid" id="eventPickerGrid">
            <?php
            // Sort: current first → ongoing (start DESC) → upcoming (start ASC) → past (start DESC)
            $pickerEvents = $activeEvents;
            usort($pickerEvents, function($a, $b) use ($today, $eventSlug) {
                $aIsCurrent = ($a['slug'] === $eventSlug) ? 0 : 1;
                $bIsCurrent = ($b['slug'] === $eventSlug) ? 0 : 1;
                if ($aIsCurrent !== $bIsCurrent) return $aIsCurrent - $bIsCurrent;

                $aStart = $a['start_date'] ?? '9999-12-31';
                $aEnd   = $a['end_date']   ?? $aStart;
                $bStart = $b['start_date'] ?? '9999-12-31';
                $bEnd   = $b['end_date']   ?? $bStart;

                $aStatus = ($aStart <= $today && $aEnd >= $today) ? 0 : ($aStart > $today ? 1 : 2);
                $bStatus = ($bStart <= $today && $bEnd >= $today) ? 0 : ($bStart > $today ? 1 : 2);
                if ($aStatus !== $bStatus) return $aStatus - $bStatus;

                // ongoing & past: most recent start first (DESC)
                // upcoming: nearest start first (ASC)
                return $aStatus === 1
                    ? strcmp($aStart, $bStart)   // upcoming ASC
                    : strcmp($bStart, $aStart);  // ongoing/past DESC
            });
            foreach ($pickerEvents as $ev):
                $evStart = $ev['start_date'] ?? null;
                $evEnd   = $ev['end_date'] ?? $evStart;
                $evStatus = 'upcoming';
                if ($evStart && $evEnd) {
                    if ($evStart <= $today && $evEnd >= $today) $evStatus = 'ongoing';
                    elseif ($evEnd < $today) $evStatus = 'past';
                }
                $displayStart = $evStart ? date('d/m/Y', strtotime($evStart)) : null;
                $displayEnd   = $evEnd   ? date('d/m/Y', strtotime($evEnd))   : null;
                $isCurrent    = ($ev['slug'] === $eventSlug);
                $cardUrl      = event_url('index.php', $ev['slug']);
                $statusLabel  = $evStatus === 'ongoing' ? 'กำลังจัดงาน' : ($evStatus === 'upcoming' ? 'กำลังจะมาถึง' : 'จบแล้ว');
                $statusI18n   = 'listing.' . $evStatus;
            ?>
            <a href="<?php echo htmlspecialchars($cardUrl); ?>"
               class="event-picker-card<?php echo $isCurrent ? ' current' : ''; ?>"
               data-name="<?php echo htmlspecialchars(mb_strtolower($ev['name'], 'UTF-8')); ?>"
               data-status="<?php echo $evStatus; ?>">
                <?php if ($isCurrent): ?>
                <span class="event-picker-current-badge" data-i18n="eventPicker.viewing">✓ ดูอยู่</span>
                <?php endif; ?>
                <div class="event-picker-card-name"><?php echo htmlspecialchars($ev['name']); ?></div>
                <?php if ($displayStart): ?>
                <div class="event-picker-card-dates">📅 <?php
                    echo $displayStart;
                    if ($displayEnd && $displayEnd !== $displayStart) echo ' – ' . $displayEnd;
                ?></div>
                <?php endif; ?>
                <span class="event-picker-card-badge <?php echo $evStatus; ?>"
                      data-i18n="<?php echo $statusI18n; ?>"><?php echo $statusLabel; ?></span>
            </a>
            <?php endforeach; ?>
            <div class="event-picker-empty" id="eventPickerEmpty" style="display:none" data-i18n="eventPicker.noResults">ไม่พบ event ที่ตรงกัน</div>
        </div>
    </div>
</div>
<?php endif; ?>
<script>
(function() {
    var _epItems = [];
    var _epIdx   = 0;

    function epOpenLightbox(idx) {
        var grid = document.getElementById('eventGalleryGrid');
        if (!grid) return;
        var items = grid.querySelectorAll('.event-gallery-item');
        _epItems = Array.prototype.slice.call(items);
        _epIdx   = idx;
        epRender();
        document.getElementById('eventPicLightbox').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function epRender() {
        var item = _epItems[_epIdx];
        if (!item) return;
        document.getElementById('epLightboxImg').src     = item.getAttribute('data-src') || '';
        document.getElementById('epLightboxImg').alt     = item.getAttribute('data-caption') || '';
        document.getElementById('epLightboxCaption').textContent = item.getAttribute('data-caption') || '';
        var lb = document.getElementById('eventPicLightbox');
        lb.querySelector('.ep-lightbox-prev').style.display = _epItems.length > 1 ? '' : 'none';
        lb.querySelector('.ep-lightbox-next').style.display = _epItems.length > 1 ? '' : 'none';
    }

    window.epCloseLightbox = function() {
        document.getElementById('eventPicLightbox').style.display = 'none';
        document.body.style.overflow = '';
    };

    window.epLightboxNav = function(dir) {
        _epIdx = (_epIdx + dir + _epItems.length) % _epItems.length;
        epRender();
    };

    document.addEventListener('keydown', function(e) {
        var lb = document.getElementById('eventPicLightbox');
        if (!lb || lb.style.display === 'none') return;
        if (e.key === 'Escape')     window.epCloseLightbox();
        if (e.key === 'ArrowLeft')  window.epLightboxNav(-1);
        if (e.key === 'ArrowRight') window.epLightboxNav(1);
    });

    document.addEventListener('DOMContentLoaded', function() {
        var grid = document.getElementById('eventGalleryGrid');
        if (!grid) return;
        grid.addEventListener('click', function(e) {
            var item = e.target.closest('.event-gallery-item');
            if (!item) return;
            epOpenLightbox(parseInt(item.getAttribute('data-index'), 10) || 0);
        });
    });
}());
</script>

<!-- Event Request Modal -->
<div id="eventRequestModal" class="req-modal-overlay">
    <div class="req-modal">
        <div class="req-modal-header">
            <h2 id="evReqModalTitle" data-i18n="evReq.titleAdd">📝 แจ้งเพิ่มงาน (Event)</h2>
            <button class="req-close" onclick="closeEventRequestModal()">✕</button>
        </div>
        <form id="eventRequestForm" onsubmit="submitEventRequest(event)">
            <div class="req-modal-body">
                <div class="req-group"><label data-i18n="evReq.name">ชื่องาน</label><input type="text" id="evReqName" maxlength="200"></div>
                <div class="req-row">
                    <div class="req-group"><label data-i18n="evReq.startDate">วันที่เริ่ม</label><input type="date" id="evReqStartDate"></div>
                    <div class="req-group"><label data-i18n="evReq.endDate">วันที่สิ้นสุด</label><input type="date" id="evReqEndDate"></div>
                </div>
                <div class="req-group"><label data-i18n="evReq.description">คำอธิบาย / รายละเอียด</label><textarea id="evReqDesc" maxlength="2000" rows="3"></textarea></div>
                <hr style="margin:12px 0;border:none;border-top:1px solid #ddd;">
                <div class="req-group"><label data-i18n="modal.requesterName">ชื่อผู้แจ้ง *</label><input type="text" id="evReqRequesterName" maxlength="100" required></div>
                <div class="req-group"><label>Email</label><input type="email" id="evReqEmail" maxlength="200"></div>
                <div class="req-group"><label data-i18n="modal.requesterNote">หมายเหตุ / แหล่งข้อมูล</label><textarea id="evReqNote" maxlength="1000" rows="2"></textarea></div>
            </div>
            <div class="req-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEventRequestModal()" data-i18n="modal.cancel">ยกเลิก</button>
                <button type="submit" class="btn btn-primary" id="evReqSubmitBtn" data-i18n="modal.submit">ส่งคำขอ</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    window.openEventRequestModal = function() {
        document.getElementById('eventRequestForm').reset();
        document.getElementById('eventRequestModal').classList.add('active');
    };

    window.closeEventRequestModal = function() {
        document.getElementById('eventRequestModal').classList.remove('active');
    };

    window.submitEventRequest = async function(ev) {
        ev.preventDefault();
        var btn = document.getElementById('evReqSubmitBtn');
        var lang = window.currentLang || 'th';

        var data = {
            type: 'add',
            event_id: null,
            name: document.getElementById('evReqName').value,
            description: document.getElementById('evReqDesc').value,
            start_date: document.getElementById('evReqStartDate').value,
            end_date: document.getElementById('evReqEndDate').value,
            requester_name: document.getElementById('evReqRequesterName').value,
            requester_email: document.getElementById('evReqEmail').value,
            note: document.getElementById('evReqNote').value,
        };

        btn.disabled = true;
        btn.textContent = (translations[lang] && translations[lang]['modal.submitting']) || 'กำลังส่ง...';

        try {
            var res = await fetch(BASE_PATH + '/api/event-request?action=submit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            var result = await res.json();
            if (result.success) {
                alert((translations[lang] && translations[lang]['evReq.submitSuccess']) || 'ส่งคำขอสำเร็จ!');
                closeEventRequestModal();
            } else {
                alert('Error: ' + result.message);
            }
        } catch(e) {
            alert((translations[lang] && translations[lang]['modal.submitError']) || 'ไม่สามารถส่งได้');
        } finally {
            btn.disabled = false;
            btn.textContent = (translations[lang] && translations[lang]['modal.submit']) || 'ส่งคำขอ';
        }
    };

    document.getElementById('eventRequestModal').addEventListener('click', function(e) {
        if (e.target === this) closeEventRequestModal();
    });
}());
</script>
</body>
</html>
