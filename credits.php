<?php
/**
 * Credits/References Page
 * แสดงแหล่งข้อมูลที่ใช้อ้างอิง
 */
require_once 'config.php';
send_security_headers();

// Multi-event support
$eventSlug = get_current_event_slug();
$eventMeta = get_event_by_slug($eventSlug);

// If a specific slug was requested but the event doesn't exist or is inactive,
// return 404 instead of silently showing credits from all events.
if ($eventSlug !== DEFAULT_EVENT_SLUG && $eventMeta === null) {
    http_response_code(404);
    $siteTitle = get_site_title();
    $theme = get_site_theme();
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>ไม่พบ Event - ' . htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') . '</title>'
        . '<link rel="stylesheet" href="styles/common.css?v=' . APP_VERSION . '">'
        . '</head><body class="theme-' . htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') . '">'
        . '<div style="text-align:center;padding:80px 20px">'
        . '<h1>🌸 404 – ไม่พบ Event</h1>'
        . '<p>Event นี้ไม่มีอยู่ หรือถูกปิดใช้งานแล้ว</p>'
        . '<a href="/" style="color:var(--sakura-dark)">← กลับหน้าหลัก</a>'
        . '</div></body></html>';
    exit;
}

$eventId = $eventMeta ? intval($eventMeta['id']) : null;
$activeEvents = MULTI_EVENT_MODE ? get_all_active_events() : [];
$today = date('Y-m-d');

// Build event id → name/slug maps for labelling credits in global view
$eventNameMap = [];
$eventSlugMap = [];
foreach ($activeEvents as $ev) {
    $eventNameMap[intval($ev['id'])] = $ev['name'];
    $eventSlugMap[intval($ev['id'])] = $ev['slug'];
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
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo get_base_path(); ?>/icon/icon-192.png">
    <link rel="icon" type="image/png" sizes="72x72" href="<?php echo get_base_path(); ?>/icon/icon-72.png">
    <link rel="icon" href="<?php echo get_base_path(); ?>/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="<?php echo get_base_path(); ?>/icon/icon-192.png">
    <title>Credits - <?php echo htmlspecialchars(get_site_title()); ?></title>
    <?php seo_render_meta([
        'description' => 'แหล่งข้อมูลอ้างอิงและ Credits สำหรับ ' . get_site_title(),
        'canonical'   => seo_full_url('/credits'),
        'og_type'     => 'website',
    ]); ?>
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
    <!-- Credits page CSS -->
    <link rel="stylesheet" href="<?php echo asset_url('styles/credits.css'); ?>">
    <?php $siteTheme = get_site_theme($eventMeta); ?>
    <?php if ($siteTheme !== 'sakura'): ?>
    <link rel="stylesheet" href="<?php echo asset_url('styles/themes/' . $siteTheme . '.css'); ?>">
    <?php endif; ?>
    <?php $headerCoverBg = get_header_cover_bg($eventMeta ?? null); ?>
</head>
<body>
    <div class="container">
        <header<?php if ($headerCoverBg): ?> class="has-site-cover" style="--header-cover-url: url('<?php echo htmlspecialchars(get_base_path() . '/' . $headerCoverBg, ENT_QUOTES, 'UTF-8'); ?>')"<?php endif; ?>>
            <div class="header-top-left">
                <a href="<?php echo get_base_path(); ?>/" class="home-icon-btn" data-i18n-title="nav.home" title="หน้าแรก">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M10 2L2 9h2v9h5v-5h2v5h5V9h2L10 2z" fill="currentColor"/>
                    </svg>
                </a>
                <?php if ($eventMeta): ?>
                <a href="<?php echo event_url('index.php'); ?>" class="home-icon-btn" data-i18n-title="nav.eventSchedule" title="ตารางงาน">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                        <path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </a>
                <?php endif; ?>
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
            <h1 data-i18n="credits.title">📋 แหล่งข้อมูลอ้างอิง</h1>
            <p data-i18n="credits.subtitle">แหล่งข้อมูลที่ใช้ในการจัดทำปฏิทิน</p>
            <?php if ($eventMeta): ?>
            <div class="credits-event-banner">
                <span class="credits-event-banner-name"><?php echo htmlspecialchars($eventMeta['name']); ?></span>
            </div>
            <?php endif; ?>
        </header>

        <?php
        // Fetch credits from cache (or database if cache expired)
        $credits = get_cached_credits($eventId);

        $isGlobalView = ($eventId === null) && MULTI_EVENT_MODE;

        // For global view: drop credits from inactive/deleted events
        if ($isGlobalView) {
            $credits = array_values(array_filter($credits, function ($c) use ($eventNameMap) {
                return $c['event_id'] === null || isset($eventNameMap[intval($c['event_id'])]);
            }));
        }

        // Pagination — applied to both views
        $perPage        = 20;
        $currentPage    = max(1, (int)($_GET['page'] ?? 1));
        $totalCredits   = count($credits);
        $totalPages     = max(1, (int)ceil($totalCredits / $perPage));
        $currentPage    = min($currentPage, $totalPages);
        $pagedCredits   = array_slice($credits, ($currentPage - 1) * $perPage, $perPage);
        $creditsBaseUrl = $isGlobalView
            ? (get_base_path() . '/credits')
            : event_url('credits.php', $eventSlug);

        // For global view: group the current page's credits by event_id
        $grouped = [];
        if ($isGlobalView && !empty($pagedCredits)) {
            foreach ($pagedCredits as $c) {
                $key = ($c['event_id'] === null) ? 0 : intval($c['event_id']);
                $grouped[$key][] = $c;
            }
            $globalGroup = isset($grouped[0]) ? [0 => $grouped[0]] : [];
            unset($grouped[0]);
            krsort($grouped);
            $grouped = $grouped + $globalGroup;
        }
        ?>

        <div class="content">
            <?php if (!empty($credits)): ?>
                <?php if ($isGlobalView): ?>
                    <?php foreach ($grouped as $gEventId => $groupCredits): ?>
                        <?php
                        if ($gEventId === 0) {
                            $groupName = 'ทั่วไป';
                            $groupUrl  = null;
                        } elseif (isset($eventNameMap[$gEventId])) {
                            $groupName = $eventNameMap[$gEventId];
                            $groupUrl  = event_url('index.php', $eventSlugMap[$gEventId]);
                        } else {
                            $groupName = 'Event #' . $gEventId;
                            $groupUrl  = null;
                        }
                        ?>
                        <div class="section">
                            <h2 class="credits-group-title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="flex-shrink:0">
                                    <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                                    <path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                                <?php if ($groupUrl): ?>
                                <a href="<?php echo htmlspecialchars($groupUrl); ?>" class="credits-group-link">
                                    <?php echo htmlspecialchars($groupName); ?>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </a>
                                <?php else: ?>
                                <?php echo htmlspecialchars($groupName); ?>
                                <?php endif; ?>
                            </h2>
                            <ul class="reference-list">
                                <?php foreach ($groupCredits as $credit): ?>
                                    <li class="reference-item">
                                        <div class="reference-title"><?php echo htmlspecialchars($credit['title']); ?></div>
                                        <?php if (!empty($credit['description'])): ?>
                                            <p style="margin: 8px 0; color: #666; font-size: 0.9rem;">
                                                <?php echo nl2br(htmlspecialchars($credit['description'])); ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (!empty($credit['link'])): ?>
                                            <a href="<?php echo htmlspecialchars($credit['link']); ?>"
                                               target="_blank" rel="noopener noreferrer"
                                               class="reference-link">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                        </div>
                    <?php endforeach; ?>
                    <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="Pagination">
                        <?php if ($currentPage > 1): ?>
                            <a href="<?php echo $creditsBaseUrl . '?page=' . ($currentPage - 1); ?>" data-i18n="listing.pagePrev">←</a>
                        <?php endif; ?>
                        <?php for ($p = 1; $p <= $totalPages; $p++):
                            if ($p === 1 || $p === $totalPages || abs($p - $currentPage) <= 1): ?>
                            <?php if ($p === $currentPage): ?>
                                <span class="current"><?php echo $p; ?></span>
                            <?php else: ?>
                                <a href="<?php echo $creditsBaseUrl . '?page=' . $p; ?>"><?php echo $p; ?></a>
                            <?php endif; ?>
                        <?php elseif (abs($p - $currentPage) === 2): ?>
                            <span class="ellipsis">…</span>
                        <?php endif; endfor; ?>
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="<?php echo $creditsBaseUrl . '?page=' . ($currentPage + 1); ?>" data-i18n="listing.pageNext">→</a>
                        <?php endif; ?>
                    </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="section">
                        <h2 data-i18n="credits.list.title">📋 แหล่งข้อมูลอ้างอิง</h2>
                        <ul class="reference-list">
                            <?php foreach ($pagedCredits as $credit): ?>
                                <li class="reference-item">
                                    <div class="reference-title"><?php echo htmlspecialchars($credit['title']); ?></div>
                                    <?php if (!empty($credit['description'])): ?>
                                        <p style="margin: 8px 0; color: #666; font-size: 0.9rem;">
                                            <?php echo nl2br(htmlspecialchars($credit['description'])); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($credit['link'])): ?>
                                        <a href="<?php echo htmlspecialchars($credit['link']); ?>"
                                           target="_blank" rel="noopener noreferrer"
                                           class="reference-link">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                        <?php if ($totalPages > 1): ?>
                        <nav class="pagination" aria-label="Pagination">
                            <?php if ($currentPage > 1): ?>
                                <a href="<?php echo $creditsBaseUrl . '?page=' . ($currentPage - 1); ?>" data-i18n="listing.pagePrev">←</a>
                            <?php endif; ?>
                            <?php for ($p = 1; $p <= $totalPages; $p++):
                                if ($p === 1 || $p === $totalPages || abs($p - $currentPage) <= 1): ?>
                                <?php if ($p === $currentPage): ?>
                                    <span class="current"><?php echo $p; ?></span>
                                <?php else: ?>
                                    <a href="<?php echo $creditsBaseUrl . '?page=' . $p; ?>"><?php echo $p; ?></a>
                                <?php endif; ?>
                            <?php elseif (abs($p - $currentPage) === 2): ?>
                                <span class="ellipsis">…</span>
                            <?php endif; endfor; ?>
                            <?php if ($currentPage < $totalPages): ?>
                                <a href="<?php echo $creditsBaseUrl . '?page=' . ($currentPage + 1); ?>" data-i18n="listing.pageNext">→</a>
                            <?php endif; ?>
                        </nav>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="section">
                    <p style="text-align: center; padding: 40px; color: #999;" data-i18n="credits.noData">
                        ยังไม่มีข้อมูล credits
                    </p>
                </div>
            <?php endif; ?>

            <div class="disclaimer">
                <strong data-i18n="credits.disclaimer.title">Disclaimer:</strong>
                <span data-i18n="credits.disclaimer.text">ข้อมูลในปฏิทินนี้รวบรวมจากแหล่งข้อมูลสาธารณะเพื่อความสะดวกในการติดตามกำหนดการ กรุณาตรวจสอบข้อมูลอย่างเป็นทางการจากผู้จัดงานก่อนเข้าร่วมงาน ข้อมูลอาจมีการเปลี่ยนแปลงโดยไม่แจ้งให้ทราบล่วงหน้า</span>
            </div>
        </div>

        <?php render_ad_unit('responsive'); ?>
        <footer>
            <div class="footer-text">
                <p data-i18n="footer.madeWith">สร้างด้วย ❤️ เพื่อแฟนไอดอล</p>
                <p data-i18n="footer.copyright">© 2026 Idol Stage Timetable. All rights reserved.</p>
                <p>Powered by <a href="https://github.com/fordantitrust/stage-idol-calendar" target="_blank">Stage Idol Calendar</a> <span class="footer-version">v<?php echo APP_VERSION; ?></span></p>
            </div>
        </footer>
    </div>

    <!-- Shared JavaScript -->
    <script>window.SITE_TITLE = <?php echo json_encode(get_site_title()); ?>;
    const BASE_PATH = <?php echo json_encode(get_base_path()); ?>;
    <script src="<?php echo asset_url('js/translations.js'); ?>"></script>
    <script src="<?php echo asset_url('js/common.js'); ?>"></script>

</body>
</html>
