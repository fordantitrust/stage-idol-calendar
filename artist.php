<?php
/**
 * Artist Profile Page
 * แสดง programs ทั้งหมดของศิลปินนี้ข้าม events
 */
require_once 'config.php';
send_security_headers();

$siteTitle = get_site_title();
$theme     = get_site_theme();

// ---- Resolve artist ID ----
$rawId    = $_GET['id'] ?? '';
$artistId = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!$artistId) {
    http_response_code(404);
    include_404('ไม่พบศิลปิน');
}

// ---- Query cache check ----
$artistCacheFile = 'query_artist_' . $artistId . '.json';
$qcd = get_query_cache($artistCacheFile);
if ($qcd !== false) {
    $artist        = $qcd['artist'];
    $members       = $qcd['members'];
    $variants      = $qcd['variants'];
    $programs      = $qcd['programs'];
    $groupPrograms = $qcd['group_programs'];
} else {

// ---- Load artist data ----
try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database unavailable');
}

// Check program_artists table exists
$hasPATable = (bool)$db->query(
    "SELECT name FROM sqlite_master WHERE type='table' AND name='program_artists'"
)->fetch();
if (!$hasPATable) {
    http_response_code(503);
    exit('Artist system not yet initialised. Run: php tools/migrate-add-artists-table.php');
}

// Fetch artist
$stmtA = $db->prepare("
    SELECT a.id, a.name, a.is_group, a.group_id,
           a.display_picture, a.cover_picture,
           g.name AS group_name
    FROM artists a
    LEFT JOIN artists g ON g.id = a.group_id
    WHERE a.id = ?
");
$stmtA->execute([$artistId]);
$artist = $stmtA->fetch(PDO::FETCH_ASSOC);

if (!$artist) {
    http_response_code(404);
    include_404('ไม่พบศิลปิน');
}

// Members (if this artist is a group)
$members = [];
if ($artist['is_group']) {
    $stmtM = $db->prepare("
        SELECT id, name FROM artists WHERE group_id = ? ORDER BY name ASC
    ");
    $stmtM->execute([$artistId]);
    $members = $stmtM->fetchAll(PDO::FETCH_ASSOC);
}

// Variants
$variants = [];
$hasVTable = (bool)$db->query(
    "SELECT name FROM sqlite_master WHERE type='table' AND name='artist_variants'"
)->fetch();
if ($hasVTable) {
    $stmtV = $db->prepare("SELECT variant FROM artist_variants WHERE artist_id = ? ORDER BY variant ASC");
    $stmtV->execute([$artistId]);
    $variants = array_column($stmtV->fetchAll(PDO::FETCH_ASSOC), 'variant');
}

// Programs linked to this artist, grouped by event
$stmtP = $db->prepare("
    SELECT p.id, p.title, p.start, p.end, p.location, p.categories, p.program_type, p.stream_url,
           e.id AS event_id, e.name AS event_name, e.slug AS event_slug
    FROM program_artists pa
    JOIN programs p ON p.id = pa.program_id
    LEFT JOIN events e ON e.id = p.event_id
    WHERE pa.artist_id = ?
      AND (e.end_date IS NULL OR e.end_date >= date('now', 'localtime'))
    ORDER BY p.start ASC
");
$stmtP->execute([$artistId]);
$programs = $stmtP->fetchAll(PDO::FETCH_ASSOC);

// Programs of the group this artist belongs to (if any)
$groupPrograms = [];
if (!$artist['is_group'] && $artist['group_id']) {
    $stmtGP = $db->prepare("
        SELECT p.id, p.title, p.start, p.end, p.location, p.categories, p.program_type, p.stream_url,
               e.id AS event_id, e.name AS event_name, e.slug AS event_slug
        FROM program_artists pa
        JOIN programs p ON p.id = pa.program_id
        LEFT JOIN events e ON e.id = p.event_id
        WHERE pa.artist_id = ?
          AND (e.end_date IS NULL OR e.end_date >= date('now', 'localtime'))
        ORDER BY p.start ASC
    ");
    $stmtGP->execute([$artist['group_id']]);
    $groupPrograms = $stmtGP->fetchAll(PDO::FETCH_ASSOC);
}

$db = null;

    save_query_cache($artistCacheFile, [
        'artist'        => $artist,
        'members'       => $members,
        'variants'      => $variants,
        'programs'      => $programs,
        'group_programs' => $groupPrograms,
    ]);
} // end cache miss block

// ---- Derived vars (always computed, cache hit or miss) ----
$totalPrograms = count($programs);

$byEvent = [];
foreach ($programs as $p) {
    $eid = $p['event_id'] ?? 0;
    $byEvent[$eid][] = $p;
}

$groupByEvent = [];
foreach ($groupPrograms as $p) {
    $eid = $p['event_id'] ?? 0;
    $groupByEvent[$eid][] = $p;
}

// ---- Helper ----
function format_time_range(string $start, string $end): string {
    if (!$start) return '';
    $s = date('H:i', strtotime($start));
    if (!$end || $start === $end) return $s;
    $e = date('H:i', strtotime($end));
    return $s === $e ? $s : "$s – $e";
}

function include_404(string $msg): never {
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
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title><?php echo htmlspecialchars($artist['name'], ENT_QUOTES, 'UTF-8'); ?> – <?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php
    // ── SEO meta tags ─────────────────────────────────────────────────────────
    $artistType   = $artist['is_group'] ? 'วง' : 'ศิลปิน';
    $eventCount   = count($byEvent);
    $seoDesc      = $artist['name'] . ' — ' . $artistType
                  . ' มีโปรแกรมใน ' . $eventCount . ' งาน | ' . $siteTitle;
    $seoCanonical = seo_full_url('/artist/' . $artistId);
    $imagePath = $artist['cover_picture'] ?: ($artist['display_picture'] ?: '');
    $seoImage  = $imagePath ? seo_full_url('/' . ltrim($imagePath, '/')) : '';
    seo_render_meta([
        'title'       => $artist['name'] . ' – ' . $siteTitle,
        'description' => $seoDesc,
        'canonical'   => $seoCanonical,
        'og_type'     => 'profile',
        'og_image'    => $seoImage,
        'site_title'  => $siteTitle,
    ]);

    // JSON-LD: Person or MusicGroup
    $ldType   = $artist['is_group'] ? 'MusicGroup' : 'Person';
    $ldSchema = [
        '@context' => 'https://schema.org',
        '@type'    => $ldType,
        'name'     => $artist['name'],
        'url'      => $seoCanonical,
    ];
    if ($seoImage) {
        $ldSchema['image'] = $seoImage;
    }
    if ($artist['is_group'] && !empty($members)) {
        $ldSchema['member'] = array_map(
            fn($m) => ['@type' => 'Person', 'name' => $m['name'], 'url' => seo_full_url('/artist/' . $m['id'])],
            $members
        );
    }
    seo_render_json_ld($ldSchema);

    // JSON-LD: BreadcrumbList
    seo_render_json_ld([
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'หน้าแรก', 'item' => seo_full_url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'ศิลปิน',  'item' => seo_full_url('/artists')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $artist['name'], 'item' => $seoCanonical],
        ],
    ]);
    ?>
    <?php if (defined('GOOGLE_ANALYTICS_ID') && GOOGLE_ANALYTICS_ID): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars(GOOGLE_ANALYTICS_ID); ?>"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?php echo htmlspecialchars(GOOGLE_ANALYTICS_ID); ?>');</script>
    <?php endif; ?>
    <?php if (defined('GOOGLE_ADS_CLIENT') && GOOGLE_ADS_CLIENT): ?>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?php echo htmlspecialchars(GOOGLE_ADS_CLIENT, ENT_QUOTES, 'UTF-8'); ?>"
         crossorigin="anonymous"></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo asset_url('styles/common.css'); ?>">
    <?php if ($theme !== 'sakura'): ?>
    <link rel="stylesheet" href="<?php echo asset_url('styles/themes/' . $theme . '.css'); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo asset_url('styles/artist.css'); ?>">
</head>
<body class="theme-<?php echo htmlspecialchars($theme, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="container">
        <header>
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
            <h1 style="font-size:1.1em;font-weight:600;margin:4px 0" data-i18n="artist.pageTitle">🎤 Artist Profile</h1>
        </header>

        <?php
        // Shared table header row (used in both group & own programs sections)
        $tableHead = '<thead><tr>'
            . '<th data-i18n="artist.colDate">วันที่</th>'
            . '<th data-i18n="artist.colTime">เวลา</th>'
            . '<th data-i18n="artist.colTitle">ชื่อ Program</th>'
            . '<th data-i18n="artist.colVenue">เวที</th>'
            . '<th data-i18n="artist.colType">ประเภท</th>'
            . '</tr></thead>';

        function render_programs_table(array $byEventMap, string $tableHead): void {
            $basePath = get_base_path();
            foreach ($byEventMap as $evProgs) {
                $firstProg = $evProgs[0];
                $evName    = $firstProg['event_name'] ?? '';
                $evSlug    = $firstProg['event_slug'] ?? null;
                $evUrl     = $evSlug ? ($basePath . '/event/' . $evSlug) : null;
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
                    $time  = format_time_range($p['start'] ?? '', $p['end'] ?? '');
                    echo '<tr>';
                    echo '<td class="prog-time">' . $start . '</td>';
                    echo '<td class="prog-time">' . $time . '</td>';
                    echo '<td>' . htmlspecialchars($p['title'] ?? '', ENT_QUOTES, 'UTF-8');
                    if (!empty($p['stream_url'])) {
                        echo '&nbsp;<a class="prog-stream-btn" href="' . htmlspecialchars($p['stream_url'], ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">🔴 Live</a>';
                    }
                    echo '</td>';
                    echo '<td>' . htmlspecialchars($p['location'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
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

            <!-- Artist Header Card -->
            <?php
            $coverPic   = $artist['cover_picture'] ?? '';
            $displayPic = $artist['display_picture'] ?? '';
            $hasCover   = !empty($coverPic);
            $headerStyle = $hasCover
                ? ' style="--cover-url:url(\'' . htmlspecialchars(get_base_path() . '/' . $coverPic, ENT_QUOTES, 'UTF-8') . '\')"'
                : '';
            $headerClass = 'artist-profile-header' . ($hasCover ? ' has-cover' : '');
            ?>
            <div class="<?php echo $headerClass; ?>"<?php echo $headerStyle; ?>>
                <div class="artist-header-content">
                <div class="artist-header-top">
                    <?php if (!empty($displayPic)): ?>
                    <img class="artist-display-picture"
                         src="<?php echo htmlspecialchars(get_base_path() . '/' . $displayPic, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($artist['name'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php else: ?>
                    <div class="artist-display-placeholder">
                        <?php echo $artist['is_group'] ? '🎵' : '🎤'; ?>
                    </div>
                    <?php endif; ?>
                    <div class="artist-header-info">
                <h1><?php echo htmlspecialchars($artist['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <div class="artist-meta-row">
                    <?php if ($artist['is_group']): ?>
                        <span class="artist-badge" data-i18n="artist.badgeGroup">🎵 กลุ่ม / Group</span>
                    <?php else: ?>
                        <span class="artist-badge" data-i18n="artist.badgeSolo">🎤 ศิลปิน / Solo</span>
                        <?php if ($artist['group_name']): ?>
                        <span class="artist-badge">
                            <span data-i18n="artist.group">วง:</span>
                            <a class="artist-group-link"
                               href="<?php echo get_base_path(); ?>/artist/<?php echo (int)$artist['group_id']; ?>">
                                <?php echo htmlspecialchars($artist['group_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="artist-stats">
                    <?php echo $totalPrograms; ?> <span data-i18n="artist.statsPrograms">programs</span>
                    &nbsp;·&nbsp;
                    <?php echo count($byEvent); ?> <span data-i18n="artist.statsEvents">events</span>
                    <?php if (!empty($groupPrograms)): ?>
                    &nbsp;·&nbsp;
                    <?php echo count($groupPrograms); ?>
                    <span data-i18n="artist.statsGroupPrograms">programs ในนามวง</span>
                    <?php echo htmlspecialchars($artist['group_name'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </div>
                <div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;">
                    <button class="btn btn-subscribe"
                            onclick="openSubscribeModal(false, <?php echo htmlspecialchars(json_encode($artist['name']), ENT_QUOTES, 'UTF-8'); ?>)">
                        🔔 <?php echo htmlspecialchars($artist['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                    <?php if (!$artist['is_group'] && $artist['group_id']): ?>
                    <button class="btn btn-subscribe" style="opacity:.85;"
                            onclick="openSubscribeModal(true, <?php echo htmlspecialchars(json_encode($artist['group_name']), ENT_QUOTES, 'UTF-8'); ?>)">
                        🔔 <?php echo htmlspecialchars($artist['group_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-fav" id="favBtn" onclick="toggleFavArtist()" style="background:linear-gradient(135deg,#fff8e1,#fff3cd);border:1px solid #ffe082;color:#f57f17;">
                        ☆ ติดตาม
                    </button>
                </div>
                    </div><!-- /.artist-header-info -->
                </div><!-- /.artist-header-top -->
                </div><!-- /.artist-header-content -->
            </div>

            <?php if ($artist['is_group'] && !empty($members)): ?>
            <div class="artist-section">
                <h3><span data-i18n="artist.sectionMembers">👥 สมาชิก</span> (<?php echo count($members); ?>)</h3>
                <div class="member-list">
                    <?php foreach ($members as $m): ?>
                    <a class="member-chip" href="<?php echo get_base_path(); ?>/artist/<?php echo (int)$m['id']; ?>">
                        <?php echo htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($variants)): ?>
            <div class="artist-section">
                <h3><span data-i18n="artist.sectionVariants">🔤 Variant Names</span> (<?php echo count($variants); ?>)</h3>
                <div class="variant-chips">
                    <?php foreach ($variants as $v): ?>
                    <span class="variant-chip"><?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Programs section (with toggle when artist belongs to a group) -->
            <div class="artist-section">
                <?php if (!empty($groupByEvent)): ?>
                <!-- Toggle buttons -->
                <div class="programs-toggle" id="programsToggle">
                    <button class="programs-toggle-btn active" id="toggleOwn" onclick="switchPrograms('own')">
                        <span data-i18n="artist.sectionPrograms">Programs ทั้งหมด</span>
                        (<?php echo $totalPrograms; ?>)
                    </button>
                    <button class="programs-toggle-btn" id="toggleGroup" onclick="switchPrograms('group')">
                        <span data-i18n="artist.sectionGroupPrograms">Programs ในนามวง</span>
                        <a href="<?php echo get_base_path(); ?>/artist/<?php echo (int)$artist['group_id']; ?>"
                           onclick="event.stopPropagation()"
                           style="color:inherit;text-decoration:underline;margin-left:3px">
                            <?php echo htmlspecialchars($artist['group_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        (<?php echo count($groupPrograms); ?>)
                    </button>
                </div>
                <?php else: ?>
                <h3><span data-i18n="artist.sectionPrograms">📅 Programs ทั้งหมด</span> (<?php echo $totalPrograms; ?>)</h3>
                <?php endif; ?>

                <!-- Own programs panel -->
                <div id="panelOwn">
                    <?php if (empty($programs)): ?>
                        <p class="empty-state" data-i18n="artist.emptyPrograms">ยังไม่มี programs ที่เชื่อมกับศิลปินนี้</p>
                    <?php else: ?>
                        <?php render_programs_table($byEvent, $tableHead); ?>
                    <?php endif; ?>
                </div>

                <!-- Group programs panel (only rendered when group exists) -->
                <?php if (!empty($groupByEvent)): ?>
                <div id="panelGroup" style="display:none">
                    <?php render_programs_table($groupByEvent, $tableHead); ?>
                </div>
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

    <!-- Subscribe Modal -->
    <div id="subscribeModal" class="req-modal-overlay">
        <div class="req-modal" style="max-width:480px;">
            <div class="req-modal-header">
                <h2 data-i18n="subscribe.title">🔔 Subscribe to Calendar</h2>
                <button onclick="closeSubscribeModal()" class="req-close">&times;</button>
            </div>
            <div class="req-modal-body">
                <p id="subscribeFeedName" style="margin:0 0 8px;font-weight:700;color:var(--sakura-deep,#C2185B);font-size:0.95em;"></p>
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

    <script>
    const BASE_PATH = <?php echo json_encode(get_base_path()); ?>;
    window.SITE_TITLE = <?php echo json_encode(get_site_title()); ?>;
    window.ARTIST_ID  = <?php echo (int)$artistId; ?>;

    // ─── Favorites ───────────────────────────────────────────────────────────────
    (function () {
        const ARTIST_ID = window.ARTIST_ID;
        const btn = document.getElementById('favBtn');
        if (!btn || !ARTIST_ID) return;

        let _isFollowing = false;

        function setFollowing(f) {
            _isFollowing = f;
            btn.innerHTML = f ? '★ ติดตามแล้ว' : '☆ ติดตาม';
            btn.style.background = f ? 'linear-gradient(135deg,#fff9c4,#fff176)' : 'linear-gradient(135deg,#fff8e1,#fff3cd)';
        }

        // Check current status
        const slug = localStorage.getItem('fav_slug');
        if (slug) {
            fetch(BASE_PATH + '/api/favorites?action=get&slug=' + encodeURIComponent(slug))
                .then(r => r.ok ? r.json() : null)
                .then(data => {
                    if (data && Array.isArray(data.artists) && data.artists.includes(ARTIST_ID)) {
                        setFollowing(true);
                    }
                })
                .catch(() => {});
        }

        window.toggleFavArtist = async function () {
            btn.disabled = true;
            let currentSlug = localStorage.getItem('fav_slug');

            if (_isFollowing && currentSlug) {
                // Unfollow
                const r = await fetch(BASE_PATH + '/api/favorites?action=remove&slug=' + encodeURIComponent(currentSlug), {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({artist_id: ARTIST_ID})
                });
                btn.disabled = false;
                if (r.ok) {
                    setFollowing(false);
                } else {
                    const e = await r.json();
                    alert(e.error || 'เกิดข้อผิดพลาด');
                }
                return;
            }

            const isNew = !currentSlug;

            // Create favorites if not exists
            if (!currentSlug) {
                const r = await fetch(BASE_PATH + '/api/favorites?action=create', {method: 'POST'});
                const j = await r.json();
                if (!r.ok) { btn.disabled = false; alert(j.error || 'เกิดข้อผิดพลาด'); return; }
                currentSlug = j.slug;
                localStorage.setItem('fav_slug', currentSlug);
            }

            // Follow
            const r2 = await fetch(BASE_PATH + '/api/favorites?action=add&slug=' + encodeURIComponent(currentSlug), {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({artist_id: ARTIST_ID})
            });

            btn.disabled = false;
            if (r2.ok) {
                if (isNew) {
                    window.location.href = BASE_PATH + '/my-favorites/' + encodeURIComponent(currentSlug);
                } else {
                    setFollowing(true);
                }
            } else {
                const e = await r2.json();
                alert(e.error || 'เกิดข้อผิดพลาด');
            }
        };
    })();

    <?php if (!empty($groupByEvent)): ?>
    (function () {
        var LS_KEY = 'artistProgramsView_<?php echo (int)$artistId; ?>';
        function switchPrograms(view) {
            var own   = document.getElementById('panelOwn');
            var grp   = document.getElementById('panelGroup');
            var btnOwn = document.getElementById('toggleOwn');
            var btnGrp = document.getElementById('toggleGroup');
            if (view === 'group') {
                own.style.display  = 'none';
                grp.style.display  = '';
                btnOwn.classList.remove('active');
                btnGrp.classList.add('active');
            } else {
                grp.style.display  = 'none';
                own.style.display  = '';
                btnGrp.classList.remove('active');
                btnOwn.classList.add('active');
            }
            try { localStorage.setItem(LS_KEY, view); } catch(e) {}
        }
        window.switchPrograms = switchPrograms;
        // Restore persisted preference
        try {
            var saved = localStorage.getItem(LS_KEY);
            if (saved === 'group') switchPrograms('group');
        } catch(e) {}
    })();
    <?php endif; ?>
    </script>
    <script src="<?php echo asset_url('js/translations.js'); ?>"></script>
    <script src="<?php echo asset_url('js/common.js'); ?>"></script>
</body>
</html>
