<?php
/**
 * Venue Profile Page (v16.0.0)
 * แสดง programs ทั้งหมดที่จัดขึ้นที่สถานที่นี้ข้าม events
 * Match programs ด้วย location = venue.name OR location IN (variants)
 */
require_once 'config.php';
send_security_headers();

$siteTitle     = get_site_title();
$theme         = get_site_theme();
$headerCoverBg = get_header_cover_bg();

// ---- Resolve venue ID ----
$rawId   = $_GET['id'] ?? '';
$venueId = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!$venueId) {
    http_response_code(404);
    include_venue_404('ไม่พบสถานที่');
}

// ---- Query cache check ----
$venueCacheFile = 'query_venue_' . $venueId . '.json';
$qcd = get_query_cache($venueCacheFile);
if ($qcd !== false) {
    $venue    = $qcd['venue'];
    $variants = $qcd['variants'];
    $programs = $qcd['programs'];
} else {

try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database unavailable');
}

// venues table must exist
$hasVenues = (bool)$db->query(
    "SELECT name FROM sqlite_master WHERE type='table' AND name='venues'"
)->fetch();
if (!$hasVenues) {
    http_response_code(503);
    exit('Venue system not yet initialised. Run: php tools/migrate-add-venues-table.php');
}

// Fetch venue
$hasIsOnlineCol = in_array('is_online', $db->query("PRAGMA table_info(venues)")->fetchAll(PDO::FETCH_COLUMN, 1), true);
$venueCols = "id, name, description, map_url" . ($hasIsOnlineCol ? ", is_online" : "");
$stmtV = $db->prepare("SELECT $venueCols FROM venues WHERE id = ?");
$stmtV->execute([$venueId]);
$venue = $stmtV->fetch(PDO::FETCH_ASSOC);
if ($venue && !$hasIsOnlineCol) $venue['is_online'] = 0;
if (!$venue) {
    http_response_code(404);
    include_venue_404('ไม่พบสถานที่');
}

// Variants
$variants = [];
$hasVarTable = (bool)$db->query(
    "SELECT name FROM sqlite_master WHERE type='table' AND name='venue_variants'"
)->fetch();
if ($hasVarTable) {
    $sv = $db->prepare("SELECT variant FROM venue_variants WHERE venue_id = ? ORDER BY variant ASC");
    $sv->execute([$venueId]);
    $variants = array_column($sv->fetchAll(PDO::FETCH_ASSOC), 'variant');
}

// Programs at this venue (name + variants), active/upcoming events, grouped later by event
$matchNames = array_merge([$venue['name']], $variants);
$placeholders = implode(',', array_fill(0, count($matchNames), '?'));
$stmtP = $db->prepare("
    SELECT p.id, p.title, p.start, p.end, p.location, p.categories, p.program_type, p.stream_url,
           e.id AS event_id, e.name AS event_name, e.slug AS event_slug,
           e.timezone AS event_timezone
    FROM programs p
    LEFT JOIN events e ON e.id = p.event_id
    WHERE p.location IN ($placeholders)
      AND (e.end_date IS NULL OR e.end_date >= date('now', 'localtime'))
    ORDER BY p.start ASC
");
$stmtP->execute($matchNames);
$programs = $stmtP->fetchAll(PDO::FETCH_ASSOC);

$db = null;

    save_query_cache($venueCacheFile, [
        'venue'    => $venue,
        'variants' => $variants,
        'programs' => $programs,
    ]);
} // end cache miss block

// ---- Derived ----
$totalPrograms = count($programs);
$byEvent = [];
foreach ($programs as $p) {
    $eid = $p['event_id'] ?? 0;
    $byEvent[$eid][] = $p;
}

// ---- Helpers ----
function venue_time_range(string $start, string $end): string {
    if (!$start) return '';
    $s = date('H:i', strtotime($start));
    if (!$end || $start === $end) return $s;
    $e = date('H:i', strtotime($end));
    return $s === $e ? $s : "$s – $e";
}

function include_venue_404(string $msg): never {
    global $siteTitle, $theme;
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8">'
        . '<title>ไม่พบ – ' . htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') . '</title>'
        . '<link rel="stylesheet" href="styles/common.css?v=' . APP_VERSION . '">'
        . '</head><body class="theme-' . htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') . '">'
        . '<div style="text-align:center;padding:80px 20px">'
        . '<h1>🌸 404</h1><p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<a href="/" style="color:var(--sakura-dark)">← กลับหน้าหลัก</a>'
        . '</div></body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="theme-color" content="#E91E63">
    <link rel="manifest" href="<?php echo get_base_path(); ?>/manifest.json">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo get_base_path(); ?>/icon/icon-192.png">
    <link rel="icon" type="image/png" sizes="72x72" href="<?php echo get_base_path(); ?>/icon/icon-72.png">
    <link rel="icon" href="<?php echo get_base_path(); ?>/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="<?php echo get_base_path(); ?>/icon/icon-192.png">
    <title><?php echo htmlspecialchars($venue['name'], ENT_QUOTES, 'UTF-8'); ?> – <?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php
    $eventCount   = count($byEvent);
    $seoDesc      = $venue['name'] . ' — สถานที่จัดงาน มีโปรแกรมใน ' . $eventCount . ' งาน | ' . $siteTitle;
    $seoCanonical = seo_full_url('/venue/' . $venueId);
    seo_render_meta([
        'title'       => $venue['name'] . ' – ' . $siteTitle,
        'description' => $seoDesc,
        'canonical'   => $seoCanonical,
        'og_type'     => 'place',
        'site_title'  => $siteTitle,
    ]);
    seo_render_json_ld([
        '@context' => 'https://schema.org',
        '@type'    => 'Place',
        'name'     => $venue['name'],
        'url'      => $seoCanonical,
    ]);
    seo_render_json_ld([
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'หน้าแรก', 'item' => seo_full_url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'สถานที่', 'item' => seo_full_url('/venues')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $venue['name'], 'item' => $seoCanonical],
        ],
    ]);
    ?>
    <?php if (defined('GOOGLE_ANALYTICS_ID') && GOOGLE_ANALYTICS_ID): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars(GOOGLE_ANALYTICS_ID); ?>"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?php echo htmlspecialchars(GOOGLE_ANALYTICS_ID); ?>');</script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo asset_url('styles/common.css'); ?>">
    <?php if ($theme !== 'sakura'): ?>
    <link rel="stylesheet" href="<?php echo asset_url('styles/themes/' . $theme . '.css'); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo asset_url('styles/artist.css'); ?>">
</head>
<body class="theme-<?php echo htmlspecialchars($theme, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="container">
        <header<?php if ($headerCoverBg): ?> class="has-site-cover" style="--header-cover-url: url('<?php echo htmlspecialchars(get_base_path() . '/' . $headerCoverBg, ENT_QUOTES, 'UTF-8'); ?>')"<?php endif; ?>>
            <div class="header-top-left">
                <a href="<?php echo get_base_path(); ?>/" class="home-icon-btn" title="หน้าแรก">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M10 2L2 9h2v9h5v-5h2v5h5V9h2L10 2z" fill="currentColor"/>
                    </svg>
                </a>
            </div>
            <div class="language-switcher">
                <button class="lang-btn active" data-lang="th" onclick="changeLanguage('th')">TH</button>
                <button class="lang-btn" data-lang="en" onclick="changeLanguage('en')">EN</button>
                <button class="lang-btn" data-lang="ja" onclick="changeLanguage('ja')">日本</button>
            </div>
            <h1 style="font-size:1.1em;font-weight:600;margin:4px 0" data-i18n="venue.pageTitle">🏛️ Venue</h1>
        </header>

        <?php
        $tableHead = '<thead><tr>'
            . '<th data-i18n="venue.colDate">วันที่</th>'
            . '<th data-i18n="venue.colTime">เวลา</th>'
            . '<th data-i18n="venue.colTitle">ชื่อ Program</th>'
            . '<th data-i18n="venue.colType">ประเภท</th>'
            . '</tr></thead>';

        function render_venue_programs(array $byEventMap, string $tableHead): void {
            $basePath = get_base_path();
            foreach ($byEventMap as $evProgs) {
                $firstProg = $evProgs[0];
                $evName = $firstProg['event_name'] ?? '';
                $evSlug = $firstProg['event_slug'] ?? null;
                $evUrl  = $evSlug ? ($basePath . '/event/' . $evSlug) : null;
                echo '<div class="event-group">';
                echo '<div class="event-group-header">';
                if ($evUrl) {
                    echo '<a href="' . htmlspecialchars($evUrl, ENT_QUOTES, 'UTF-8') . '">'
                       . htmlspecialchars($evName, ENT_QUOTES, 'UTF-8') . '</a>';
                } else {
                    echo '<span style="font-weight:700">' . htmlspecialchars($evName, ENT_QUOTES, 'UTF-8') . '</span>';
                }
                echo '<span style="color:#9ca3af;font-size:0.85em">(' . count($evProgs) . ' programs)</span>';
                echo '</div>';
                echo '<div class="table-scroll-wrapper"><table class="programs-table">';
                echo $tableHead;
                echo '<tbody>';
                foreach ($evProgs as $p) {
                    $start = $p['start'] ? date('d M', strtotime($p['start'])) : '-';
                    $time  = venue_time_range($p['start'] ?? '', $p['end'] ?? '');
                    // UTC ms + event TZ for cross-TZ "(HH:MM local)" annotation (v16.0.7+)
                    $_evTz = $p['event_timezone'] ?: DEFAULT_TIMEZONE;
                    try {
                        $_tzObj = new DateTimeZone($_evTz);
                        $_utcStart = !empty($p['start']) ? (new DateTime($p['start'], $_tzObj))->getTimestamp() * 1000 : 0;
                        $_utcEnd   = !empty($p['end'])   ? (new DateTime($p['end'],   $_tzObj))->getTimestamp() * 1000 : 0;
                    } catch (Exception $e) { $_utcStart = $_utcEnd = 0; }
                    $_tzAttr = ' data-utc-start="' . $_utcStart . '" data-utc-end="' . $_utcEnd . '" data-event-tz="' . htmlspecialchars($_evTz, ENT_QUOTES, 'UTF-8') . '"';
                    echo '<tr>';
                    echo '<td class="prog-time">' . $start . '</td>';
                    echo '<td class="prog-time"' . $_tzAttr . '>' . $time . '</td>';
                    echo '<td>' . htmlspecialchars($p['title'] ?? '', ENT_QUOTES, 'UTF-8');
                    if (!empty($p['stream_url'])) {
                        echo '&nbsp;<a class="prog-stream-btn" href="' . htmlspecialchars($p['stream_url'], ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">🔴 Live</a>';
                    }
                    echo '</td>';
                    echo '<td>';
                    if (!empty($p['program_type'])) {
                        echo '<span class="prog-type-badge">' . htmlspecialchars($p['program_type'], ENT_QUOTES, 'UTF-8') . '</span>';
                    } else {
                        echo '<span style="color:#d1d5db">–</span>';
                    }
                    echo '</td></tr>';
                }
                echo '</tbody></table></div></div>';
            }
        }
        ?>

        <div class="content" style="padding-top:16px">

            <div class="artist-profile-header">
                <div class="artist-header-content">
                <div class="artist-header-top">
                    <div class="artist-display-placeholder"><?php echo !empty($venue['is_online']) ? '🌐' : '🏛️'; ?></div>
                    <div class="artist-header-info">
                        <h1><?php echo htmlspecialchars($venue['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
                        <div class="artist-meta-row">
                            <?php if (!empty($venue['is_online'])): ?>
                            <span class="artist-badge" data-i18n="venue.badgeOnline">🌐 Online Platform</span>
                            <?php else: ?>
                            <span class="artist-badge" data-i18n="venue.badge">🏛️ สถานที่ / Venue</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($venue['description'])): ?>
                        <p style="margin:8px 0 0;color:#555;line-height:1.5;"><?php echo nl2br(htmlspecialchars($venue['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                        <?php endif; ?>
                        <div class="artist-stats">
                            <?php echo $totalPrograms; ?> <span data-i18n="venue.statsPrograms">programs</span>
                            &nbsp;·&nbsp;
                            <?php echo count($byEvent); ?> <span data-i18n="venue.statsEvents">events</span>
                        </div>
                        <?php if (!empty($venue['map_url'])): ?>
                        <div style="margin-top:12px;">
                            <a class="btn btn-subscribe" href="<?php echo htmlspecialchars($venue['map_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                📍 <span data-i18n="venue.map">ดูแผนที่</span>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                </div>
            </div>

            <?php if (!empty($variants)): ?>
            <div class="artist-section">
                <h3><span data-i18n="venue.sectionVariants">🔤 ชื่อเรียกอื่น</span> (<?php echo count($variants); ?>)</h3>
                <div class="variant-chips">
                    <?php foreach ($variants as $v): ?>
                    <span class="variant-chip"><?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="artist-section">
                <h3><span data-i18n="venue.sectionPrograms">📅 Programs ทั้งหมด</span> (<?php echo $totalPrograms; ?>)</h3>
                <?php if (empty($programs)): ?>
                    <p class="empty-state" data-i18n="venue.emptyPrograms">ยังไม่มี programs ที่สถานที่นี้ (เฉพาะงานที่กำลังจะมาถึง)</p>
                <?php else: ?>
                    <?php render_venue_programs($byEvent, $tableHead); ?>
                <?php endif; ?>
            </div>

        </div><!-- .content -->
        <?php render_ad_unit('leaderboard'); ?>

        <footer>
            <div class="footer-text">
                <p data-i18n="footer.madeWith">สร้างด้วย ❤️ เพื่อแฟนไอดอล</p>
                <p data-i18n="footer.copyright">© 2026 Idol Stage Timetable. All rights reserved.</p>
                <p>Powered by <a href="https://github.com/fordantitrust/stage-idol-calendar" target="_blank">Stage Idol Calendar</a> <span class="footer-version">v<?php echo APP_VERSION; ?></span></p>
            </div>
        </footer>
    </div>

    <script>
    const BASE_PATH = <?php echo json_encode(get_base_path()); ?>;
    window.SITE_TITLE = <?php echo json_encode(get_site_title()); ?>;
    </script>
    <script src="<?php echo asset_url('js/translations.js'); ?>"></script>
    <script src="<?php echo asset_url('js/common.js'); ?>"></script>
</body>
</html>
