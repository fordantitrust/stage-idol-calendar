<?php
/**
 * Artist & Group Portal
 * แสดงรายการ groups และ artists ทั้งหมดในระบบ
 */
require_once 'config.php';
send_security_headers();

function portal_social_svg(string $network): string {
    switch ($network) {
        case 'facebook':
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="#4267B2" aria-hidden="true"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>';
        case 'instagram':
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="#E1306C" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>';
        case 'twitter':
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="#000" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.748l7.73-8.835L1.254 2.25H8.08l4.253 5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>';
        case 'tiktok':
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="#010101" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.3 6.3 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.78 1.52V6.75a4.85 4.85 0 01-1.01-.06z"/></svg>';
        default:
            return '';
    }
}

$siteTitle     = get_site_title();
$theme         = get_site_theme();
$basePath      = get_base_path();
$headerCoverBg = get_header_cover_bg();

// ---- Query cache ----
$cacheFile = 'query_portal.json';
$cached    = get_query_cache($cacheFile);

if ($cached !== false) {
    $groups      = $cached['groups'];
    $soloArtists = $cached['solo_artists'];
    $membersByGroup = $cached['members_by_group'];
} else {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        http_response_code(500);
        exit('Database unavailable');
    }

    $hasPATable = (bool)$db->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='program_artists'"
    )->fetch();

    $pcExpr = $hasPATable
        ? "(SELECT COUNT(DISTINCT pa.program_id) FROM program_artists pa WHERE pa.artist_id = a.id)"
        : "0";

    // ---- Groups ----
    $stmt = $db->query("
        SELECT a.id, a.name,
               a.social_facebook, a.social_instagram, a.social_twitter, a.social_tiktok,
               (SELECT COUNT(*) FROM artists m WHERE m.group_id = a.id AND m.is_group = 0) AS member_count,
               $pcExpr AS program_count
        FROM artists a
        WHERE a.is_group = 1
        ORDER BY a.name ASC
    ");
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---- All non-group artists ----
    $stmt = $db->query("
        SELECT a.id, a.name, a.group_id,
               a.social_facebook, a.social_instagram, a.social_twitter, a.social_tiktok,
               $pcExpr AS program_count
        FROM artists a
        WHERE a.is_group = 0
        ORDER BY a.name ASC
    ");
    $allMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organise: members per group + true solo (no group)
    $membersByGroup = [];
    $soloArtists    = [];
    foreach ($allMembers as $m) {
        if ($m['group_id']) {
            $membersByGroup[(int)$m['group_id']][] = $m;
        } else {
            $soloArtists[] = $m;
        }
    }

    save_query_cache($cacheFile, [
        'groups'          => $groups,
        'solo_artists'    => $soloArtists,
        'members_by_group'=> $membersByGroup,
    ]);
}

// FTS5 server-side filter when ?q= present
$portalQuery   = trim(get_sanitized_param('q', '', 200));
$ftsArtistIds  = null;
if ($portalQuery !== '' && mb_strlen($portalQuery) >= 3) {
    try {
        $dbSearch = new PDO('sqlite:' . DB_PATH);
        $dbSearch->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $ftsRows     = fts5_search_artists($dbSearch, $portalQuery, 500);
        $ftsArtistIds = array_flip(array_column($ftsRows, 'id'));
    } catch (Exception $e) {
        $ftsArtistIds = [];
    }
}

// Apply FTS filter to groups and soloArtists
if ($ftsArtistIds !== null) {
    $groups      = array_values(array_filter($groups, fn($g) => isset($ftsArtistIds[$g['id']])));
    $soloArtists = array_values(array_filter($soloArtists, fn($a) => isset($ftsArtistIds[$a['id']])));
}

$totalGroups  = count($groups);
$totalSolos   = count($soloArtists);
$totalMembers = 0;
foreach ($groups as $g) $totalMembers += (int)$g['member_count'];
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
    <title><?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?> – Artist Portal</title>
    <?php
    // ── SEO meta tags ─────────────────────────────────────────────────────────
    $seoDesc      = 'รายชื่อศิลปินและวง ' . $totalGroups . ' วง ' . $totalSolos . ' เดี่ยว | ' . $siteTitle;
    $seoCanonical = seo_full_url('/artists');
    seo_render_meta([
        'title'       => $siteTitle . ' – Artist Portal',
        'description' => $seoDesc,
        'canonical'   => $seoCanonical,
        'og_type'     => 'website',
        'site_title'  => $siteTitle,
    ]);

    // JSON-LD: ItemList of top artists (limit 20)
    $topItems = array_slice(array_merge($groups, $soloArtists), 0, 20);
    if (!empty($topItems)) {
        $ldItems = [];
        foreach ($topItems as $i => $a) {
            $ldItems[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $a['name'],
                'url'      => seo_full_url('/artist/' . $a['id']),
            ];
        }
        seo_render_json_ld([
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'name'            => 'ศิลปิน & วง',
            'itemListElement' => $ldItems,
        ]);
    }
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
        <h1 data-i18n="portal.title">🎤 Artist Portal</h1>
        <p class="portal-subtitle" data-i18n="portal.subtitle">รายการกลุ่มและศิลปินทั้งหมดในระบบ</p>
    </header>
    <?php render_ad_unit('leaderboard'); ?>

    <div class="content" style="padding-top:8px">

        <!-- Search + stats bar -->
        <div class="portal-search-row">
            <div class="portal-search-wrap">
                <svg class="portal-search-icon" width="16" height="16" viewBox="0 0 20 20" fill="none">
                    <circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="2"/>
                    <path d="M13.5 13.5L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <input id="portalSearch" type="text" class="portal-search-input"
                       data-i18n-placeholder="portal.searchPlaceholder"
                       placeholder="ค้นหา..."
                       oninput="filterPortal(this.value)">
                <button class="portal-search-clear" id="portalSearchClear" onclick="clearPortalSearch()" title="ล้าง">✕</button>
            </div>
            <div class="portal-stats">
                <span class="portal-stat-chip">
                    <strong><?php echo $totalGroups; ?></strong>
                    <span data-i18n="portal.statGroups">กลุ่ม</span>
                </span>
                <span class="portal-stat-chip">
                    <strong><?php echo $totalMembers + $totalSolos; ?></strong>
                    <span data-i18n="portal.statArtists">ศิลปิน</span>
                </span>
            </div>
        </div>

        <!-- Filter tabs -->
        <div class="portal-tabs" id="portalTabs">
            <button class="portal-tab active" data-tab="all" onclick="switchPortalTab('all')" data-i18n="portal.tabAll">ทั้งหมด</button>
            <button class="portal-tab" data-tab="groups" onclick="switchPortalTab('groups')" data-i18n="portal.tabGroups">กลุ่ม/วง</button>
            <button class="portal-tab" data-tab="solo" onclick="switchPortalTab('solo')" data-i18n="portal.tabSolo">ศิลปินเดี่ยว</button>
        </div>

        <!-- No results -->
        <div id="portalNoResults" class="portal-empty" style="display:none" data-i18n="portal.noResults">ไม่พบศิลปินที่ค้นหา</div>

        <!-- ==================== GROUPS ==================== -->
        <section class="portal-section" id="sectionGroups">
            <h2 class="portal-section-heading">
                <span data-i18n="portal.tabGroups">กลุ่ม/วง</span>
                <span class="portal-section-count"><?php echo $totalGroups; ?></span>
            </h2>

            <?php if (empty($groups)): ?>
            <p class="portal-empty" data-i18n="portal.emptyGroups">ยังไม่มีกลุ่มในระบบ</p>
            <?php else: ?>
            <div class="portal-groups-grid" id="groupsGrid">
                <?php foreach ($groups as $g):
                    $gId       = (int)$g['id'];
                    $gName     = htmlspecialchars($g['name'], ENT_QUOTES, 'UTF-8');
                    $gMemCount = (int)$g['member_count'];
                    $gProgCount= (int)$g['program_count'];
                    $gMembers  = $membersByGroup[$gId] ?? [];
                    $gUrl      = $basePath . '/artist/' . $gId;
                ?>
                <div class="portal-group-card" data-name="<?php echo strtolower($g['name']); ?>">
                    <a href="<?php echo $gUrl; ?>" class="portal-group-header">
                        <span class="portal-group-name"><?php echo $gName; ?></span>
                        <span class="portal-group-badges">
                            <?php if ($gProgCount > 0): ?>
                            <span class="portal-badge portal-badge-prog"><?php echo $gProgCount; ?> <span data-i18n="portal.programs">programs</span></span>
                            <?php endif; ?>
                            <span class="portal-badge portal-badge-member"><?php echo $gMemCount; ?> <span data-i18n="portal.members">สมาชิก</span></span>
                        </span>
                    </a>
                    <?php if (!empty($gMembers)): ?>
                    <div class="portal-member-chips">
                        <?php foreach ($gMembers as $m): ?>
                        <a href="<?php echo $basePath; ?>/artist/<?php echo (int)$m['id']; ?>"
                           class="portal-member-chip"
                           data-name="<?php echo strtolower($m['name']); ?>">
                            <?php echo htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php if ((int)$m['program_count'] > 0): ?>
                            <span class="portal-chip-count"><?php echo (int)$m['program_count']; ?></span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="portal-no-members" data-i18n="portal.noMembers">ยังไม่มีสมาชิก</p>
                    <?php endif; ?>
                    <?php
                    $gSocials = array_filter([
                        'facebook'  => $g['social_facebook']  ?? '',
                        'instagram' => $g['social_instagram'] ?? '',
                        'twitter'   => $g['social_twitter']   ?? '',
                        'tiktok'    => $g['social_tiktok']    ?? '',
                    ]);
                    if (!empty($gSocials)): ?>
                    <div class="portal-social-links">
                        <?php foreach ($gSocials as $net => $url): ?>
                        <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
                           class="portal-social-icon portal-social-<?php echo $net; ?>"
                           target="_blank" rel="noopener noreferrer"><?php echo portal_social_svg($net); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <!-- ==================== SOLO ARTISTS ==================== -->
        <section class="portal-section" id="sectionSolo">
            <h2 class="portal-section-heading">
                <span data-i18n="portal.tabSolo">ศิลปินเดี่ยว</span>
                <span class="portal-section-count"><?php echo $totalSolos; ?></span>
            </h2>

            <?php if (empty($soloArtists)): ?>
            <p class="portal-empty" data-i18n="portal.emptySolo">ยังไม่มีศิลปินเดี่ยวในระบบ</p>
            <?php else: ?>
            <div class="portal-solo-grid" id="soloGrid">
                <?php foreach ($soloArtists as $a):
                    $aId    = (int)$a['id'];
                    $aName  = htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8');
                    $aProg  = (int)$a['program_count'];
                    $aSocials = array_filter([
                        'facebook'  => $a['social_facebook']  ?? '',
                        'instagram' => $a['social_instagram'] ?? '',
                        'twitter'   => $a['social_twitter']   ?? '',
                        'tiktok'    => $a['social_tiktok']    ?? '',
                    ]);
                ?>
                <div class="portal-solo-card" data-name="<?php echo strtolower($a['name']); ?>">
                    <a href="<?php echo $basePath; ?>/artist/<?php echo $aId; ?>" class="portal-solo-name-link">
                        <span class="portal-solo-name"><?php echo $aName; ?></span>
                        <?php if ($aProg > 0): ?>
                        <span class="portal-solo-prog"><?php echo $aProg; ?> <span data-i18n="portal.programs">programs</span></span>
                        <?php endif; ?>
                    </a>
                    <?php if (!empty($aSocials)): ?>
                    <div class="portal-social-links">
                        <?php foreach ($aSocials as $net => $url): ?>
                        <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
                           class="portal-social-icon portal-social-<?php echo $net; ?>"
                           target="_blank" rel="noopener noreferrer"><?php echo portal_social_svg($net); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
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
    let _currentTab = 'all';
    let _query      = '';

    // ── Tab switch ──────────────────────────────────────────────────────────
    window.switchPortalTab = function (tab) {
        _currentTab = tab;
        document.querySelectorAll('.portal-tab').forEach(function (b) {
            b.classList.toggle('active', b.dataset.tab === tab);
        });
        applyFilter();
    };

    // ── Search ──────────────────────────────────────────────────────────────
    window.filterPortal = function (val) {
        _query = (val || '').trim().toLowerCase();
        document.getElementById('portalSearchClear').style.display = _query ? 'flex' : 'none';
        applyFilter();
    };

    window.clearPortalSearch = function () {
        var inp = document.getElementById('portalSearch');
        inp.value = '';
        filterPortal('');
        inp.focus();
    };

    // ── Core filter logic ───────────────────────────────────────────────────
    function applyFilter() {
        var showGroups = _currentTab === 'all' || _currentTab === 'groups';
        var showSolo   = _currentTab === 'all' || _currentTab === 'solo';
        var q          = _query;

        // Groups section visibility
        var secGroups = document.getElementById('sectionGroups');
        var secSolo   = document.getElementById('sectionSolo');
        if (secGroups) secGroups.style.display = showGroups ? '' : 'none';
        if (secSolo)   secSolo.style.display   = showSolo   ? '' : 'none';

        var anyVisible = false;

        // Filter group cards
        if (showGroups) {
            document.querySelectorAll('.portal-group-card').forEach(function (card) {
                var name    = card.dataset.name || '';
                var visible = !q || name.includes(q);

                // Also check member names
                if (!visible && q) {
                    var chips = card.querySelectorAll('.portal-member-chip');
                    chips.forEach(function (chip) {
                        if ((chip.dataset.name || '').includes(q)) visible = true;
                    });
                }
                card.style.display = visible ? '' : 'none';
                if (visible) anyVisible = true;

                // Highlight matching member chips
                card.querySelectorAll('.portal-member-chip').forEach(function (chip) {
                    chip.classList.toggle('portal-chip-match', q ? (chip.dataset.name || '').includes(q) : false);
                });
            });
        }

        // Filter solo cards
        if (showSolo) {
            document.querySelectorAll('.portal-solo-card').forEach(function (card) {
                var visible = !q || (card.dataset.name || '').includes(q);
                card.style.display = visible ? '' : 'none';
                if (visible) anyVisible = true;
            });
        }

        // No-results message
        document.getElementById('portalNoResults').style.display = (!anyVisible && q) ? '' : 'none';
    }

    // ── Placeholder i18n ────────────────────────────────────────────────────
    document.addEventListener('appLangChange', function (e) {
        var lang = (e.detail && e.detail.lang) || 'th';
        var t = {
            th: 'ค้นหากลุ่มหรือศิลปิน...',
            en: 'Search groups or artists...',
            ja: 'グループ・アーティストを検索...'
        };
        var inp = document.getElementById('portalSearch');
        if (inp) inp.placeholder = t[lang] || t.th;
    });
})();
</script>
</body>
</html>
