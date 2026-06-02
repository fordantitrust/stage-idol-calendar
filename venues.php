<?php
/**
 * Venue Portal (v16.0.0)
 * แสดงรายการสถานที่ (venues) ทั้งหมดในระบบ + program count, link ไปหน้าโปรไฟล์สถานที่
 */
require_once 'config.php';
send_security_headers();

$siteTitle     = get_site_title();
$theme         = get_site_theme();
$basePath      = get_base_path();
$headerCoverBg = get_header_cover_bg();

// ---- Query cache ----
$cacheFile = 'query_portal_venues.json';
$cached    = get_query_cache($cacheFile);

if ($cached !== false) {
    $venues = $cached['venues'];
} else {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        http_response_code(500);
        exit('Database unavailable');
    }

    $hasVenues = (bool)$db->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='venues'"
    )->fetch();
    if (!$hasVenues) {
        http_response_code(503);
        exit('Venue system not yet initialised. Run: php tools/migrate-add-venues-table.php');
    }

    $hasVar = (bool)$db->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='venue_variants'"
    )->fetch();
    $pcExpr = $hasVar
        ? "(SELECT COUNT(*) FROM programs p
             WHERE p.location = v.name
                OR p.location IN (SELECT vv.variant FROM venue_variants vv WHERE vv.venue_id = v.id))"
        : "(SELECT COUNT(*) FROM programs p WHERE p.location = v.name)";

    // Hide online platforms (YouTube / Instagram Live / X Spaces …) from the portal — v16.0.1
    $hasIsOnline = in_array('is_online', $db->query("PRAGMA table_info(venues)")->fetchAll(PDO::FETCH_COLUMN, 1), true);
    $onlineFilter = $hasIsOnline ? "WHERE COALESCE(v.is_online, 0) = 0" : "";
    $venues = $db->query("
        SELECT v.id, v.name, $pcExpr AS program_count
        FROM venues v
        $onlineFilter
        ORDER BY v.name COLLATE NOCASE ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    save_query_cache($cacheFile, ['venues' => $venues]);
}

$totalVenues = count($venues);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="theme-color" content="#E91E63">
    <link rel="manifest" href="<?php echo get_base_path(); ?>/manifest.json">
    <title><?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?> – Venue Portal</title>
    <?php
    $seoDesc      = 'รายชื่อสถานที่จัดงานทั้งหมด ' . $totalVenues . ' แห่ง | ' . $siteTitle;
    $seoCanonical = seo_full_url('/venues');
    seo_render_meta([
        'title'       => $siteTitle . ' – Venue Portal',
        'description' => $seoDesc,
        'canonical'   => $seoCanonical,
        'og_type'     => 'website',
        'site_title'  => $siteTitle,
    ]);
    $topItems = array_slice($venues, 0, 20);
    if (!empty($topItems)) {
        $ldItems = [];
        foreach ($topItems as $i => $v) {
            $ldItems[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $v['name'], 'url' => seo_full_url('/venue/' . $v['id'])];
        }
        seo_render_json_ld([
            '@context' => 'https://schema.org', '@type' => 'ItemList',
            'name' => 'สถานที่', 'itemListElement' => $ldItems,
        ]);
    }
    ?>
    <?php if (defined('GOOGLE_ANALYTICS_ID') && GOOGLE_ANALYTICS_ID): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars(GOOGLE_ANALYTICS_ID); ?>"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?php echo htmlspecialchars(GOOGLE_ANALYTICS_ID); ?>');</script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo asset_url('styles/common.css'); ?>">
    <?php if ($theme !== 'sakura'): ?>
    <link rel="stylesheet" href="<?php echo asset_url('styles/themes/' . $theme . '.css'); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo asset_url('styles/portal.css'); ?>">
</head>
<body class="theme-<?php echo htmlspecialchars($theme, ENT_QUOTES, 'UTF-8'); ?>">
<div class="container wide">

    <header<?php if ($headerCoverBg): ?> class="has-site-cover" style="--header-cover-url: url('<?php echo htmlspecialchars($basePath . '/' . $headerCoverBg, ENT_QUOTES, 'UTF-8'); ?>')"<?php endif; ?>>
        <div class="header-top-left">
            <a href="<?php echo $basePath; ?>/" class="home-icon-btn" title="หน้าแรก">
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
        <h1 data-i18n="venuePortal.title">🏛️ Venue Portal</h1>
        <p class="portal-subtitle" data-i18n="venuePortal.subtitle">รายการสถานที่จัดงานทั้งหมดในระบบ</p>
    </header>
    <?php render_ad_unit('leaderboard'); ?>

    <div class="content" style="padding-top:8px">

        <div class="portal-search-row">
            <div class="portal-search-wrap">
                <svg class="portal-search-icon" width="16" height="16" viewBox="0 0 20 20" fill="none">
                    <circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="2"/>
                    <path d="M13.5 13.5L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <input id="portalSearch" type="text" class="portal-search-input"
                       data-i18n-placeholder="venuePortal.searchPlaceholder"
                       placeholder="ค้นหาสถานที่..."
                       oninput="filterVenues(this.value)">
                <button class="portal-search-clear" id="portalSearchClear" onclick="clearVenueSearch()" title="ล้าง">✕</button>
            </div>
            <div class="portal-stats">
                <span class="portal-stat-chip">
                    <strong><?php echo $totalVenues; ?></strong>
                    <span data-i18n="venuePortal.statVenues">สถานที่</span>
                </span>
            </div>
        </div>

        <div id="portalNoResults" class="portal-empty" style="display:none" data-i18n="venuePortal.noResults">ไม่พบสถานที่ที่ค้นหา</div>

        <section class="portal-section">
            <?php if (empty($venues)): ?>
            <p class="portal-empty" data-i18n="venuePortal.empty">ยังไม่มีสถานที่ในระบบ</p>
            <?php else: ?>
            <div class="portal-solo-grid" id="venuesGrid">
                <?php foreach ($venues as $v):
                    $vId   = (int)$v['id'];
                    $vName = htmlspecialchars($v['name'], ENT_QUOTES, 'UTF-8');
                    $vProg = (int)$v['program_count'];
                ?>
                <div class="portal-solo-card" data-name="<?php echo htmlspecialchars(mb_strtolower($v['name'], 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>">
                    <a href="<?php echo $basePath; ?>/venue/<?php echo $vId; ?>" class="portal-solo-name-link">
                        <span class="portal-solo-name">🏛️ <?php echo $vName; ?></span>
                        <?php if ($vProg > 0): ?>
                        <span class="portal-solo-prog"><?php echo $vProg; ?> <span data-i18n="portal.programs">programs</span></span>
                        <?php endif; ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

    </div><!-- .content -->

    <footer>
        <div class="footer-text">
            <p data-i18n="footer.madeWith">สร้างด้วย ❤️ เพื่อแฟนไอดอล</p>
            <p data-i18n="footer.copyright">© 2026 Idol Stage Timetable. All rights reserved.</p>
            <p>Powered by <a href="https://github.com/fordantitrust/stage-idol-calendar" target="_blank">Stage Idol Calendar</a> <span class="footer-version">v<?php echo APP_VERSION; ?></span></p>
        </div>
    </footer>
</div><!-- .container -->

<script>
const BASE_PATH = <?php echo json_encode($basePath); ?>;
window.SITE_TITLE = <?php echo json_encode($siteTitle); ?>;
</script>
<script src="<?php echo asset_url('js/translations.js'); ?>"></script>
<script src="<?php echo asset_url('js/common.js'); ?>"></script>
<script>
(function () {
    let _query = '';
    window.filterVenues = function (val) {
        _query = (val || '').trim().toLowerCase();
        document.getElementById('portalSearchClear').style.display = _query ? 'flex' : 'none';
        var anyVisible = false;
        document.querySelectorAll('.portal-solo-card').forEach(function (card) {
            var visible = !_query || (card.dataset.name || '').includes(_query);
            card.style.display = visible ? '' : 'none';
            if (visible) anyVisible = true;
        });
        document.getElementById('portalNoResults').style.display = (!anyVisible && _query) ? '' : 'none';
    };
    window.clearVenueSearch = function () {
        var inp = document.getElementById('portalSearch');
        inp.value = '';
        filterVenues('');
        inp.focus();
    };
    document.addEventListener('appLangChange', function (e) {
        var lang = (e.detail && e.detail.lang) || 'th';
        var t = { th: 'ค้นหาสถานที่...', en: 'Search venues...', ja: '会場を検索...' };
        var inp = document.getElementById('portalSearch');
        if (inp) inp.placeholder = t[lang] || t.th;
    });
})();
</script>
</body>
</html>
