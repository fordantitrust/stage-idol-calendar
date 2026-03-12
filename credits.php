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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Credits - <?php echo htmlspecialchars(get_site_title()); ?></title>
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
    <!-- Shared CSS -->
    <link rel="stylesheet" href="<?php echo asset_url('styles/common.css'); ?>">
    <!-- Credits page CSS -->
    <link rel="stylesheet" href="<?php echo asset_url('styles/credits.css'); ?>">
    <?php $siteTheme = get_site_theme($eventMeta); ?>
    <?php if ($siteTheme !== 'sakura'): ?>
    <link rel="stylesheet" href="<?php echo asset_url('styles/themes/' . $siteTheme . '.css'); ?>">
    <?php endif; ?>
</head>
<body>
    <div class="container">
        <header>
            <div class="language-switcher">
                <button class="lang-btn active" data-lang="th" onclick="changeLanguage('th')">TH</button>
                <button class="lang-btn" data-lang="en" onclick="changeLanguage('en')">EN</button>
                <button class="lang-btn" data-lang="ja" onclick="changeLanguage('ja')">日本</button>
            </div>
            <h1 data-i18n="credits.title">📋 Credits & References</h1>
            <p data-i18n="credits.subtitle">แหล่งข้อมูลที่ใช้ในการจัดทำปฏิทิน</p>
            <nav class="header-nav">
                <a href="<?php echo event_url('index.php'); ?>" class="header-nav-link" data-i18n="nav.home">🏠 หน้าแรก</a>
                <a href="<?php echo event_url('how-to-use.php'); ?>" class="header-nav-link" data-i18n="nav.howToUse">📖 วิธีการใช้งาน</a>
            </nav>
        </header>

        <?php
        // Fetch credits from cache (or database if cache expired)
        $credits = get_cached_credits($eventId);
        ?>

        <div class="content">
            <?php if (!empty($credits)): ?>
                <div class="section">
                    <h2 data-i18n="credits.list.title">📋 Credits & References</h2>
                    <ul class="reference-list">
                        <?php foreach ($credits as $credit): ?>
                            <li class="reference-item">
                                <div class="reference-title"><?php echo htmlspecialchars($credit['title']); ?></div>

                                <?php if (!empty($credit['description'])): ?>
                                    <p style="margin: 8px 0; color: #666; font-size: 0.9rem;">
                                        <?php echo nl2br(htmlspecialchars($credit['description'])); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($credit['link'])): ?>
                                    <a href="<?php echo htmlspecialchars($credit['link']); ?>"
                                       target="_blank"
                                       rel="noopener noreferrer"
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

        <footer>
            <div class="footer-text">
                <p data-i18n="footer.madeWith">สร้างด้วย ❤️ เพื่อแฟนไอดอล</p>
                <p data-i18n="footer.copyright">© 2026 Idol Stage Timetable. All rights reserved.</p>
                <p>Powered by <a href="https://github.com/fordantitrust/stage-idol-calendar" target="_blank">Stage Idol Calendar</a></p>
            </div>
        </footer>
    </div>

    <!-- Shared JavaScript -->
    <script>window.SITE_TITLE = <?php echo json_encode(get_site_title()); ?>;</script>
    <script src="<?php echo asset_url('js/translations.js'); ?>"></script>
    <script src="<?php echo asset_url('js/common.js'); ?>"></script>
</body>
</html>
