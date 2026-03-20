<?php
require_once 'config.php';
send_security_headers();

// Multi-event support
$eventSlug = get_current_event_slug();
$eventMeta = get_event_by_slug($eventSlug);

// Load contact channels from DB
$contactChannels = [];
try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Check table exists before querying
    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='contact_channels'");
    if ($tableCheck->fetch()) {
        $stmt = $db->query("SELECT * FROM contact_channels WHERE is_active = 1 ORDER BY display_order ASC, id ASC");
        $contactChannels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Silently fall through — show empty channels list
}

// Load disclaimer
$disclaimer = get_site_disclaimer();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>ติดต่อเรา - <?php echo htmlspecialchars(get_site_title()); ?></title>
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
    <?php $siteTheme = get_site_theme($eventMeta); ?>
    <?php if ($siteTheme !== 'sakura'): ?>
    <link rel="stylesheet" href="<?php echo asset_url('styles/themes/' . $siteTheme . '.css'); ?>">
    <?php endif; ?>
</head>
<body>
    <div class="container">
        <header>
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
            <h1 data-i18n="contact.title">✉️ ติดต่อเรา</h1>
            <p data-i18n="contact.subtitle">หากพบปัญหาหรือต้องการข้อมูลเพิ่มเติม</p>
        </header>

        <div class="content">
            <div class="section">
                <h2 data-i18n="contact.section1.title">📧 ช่องทางการติดต่อ</h2>
                <p data-i18n="contact.section1.desc">หากคุณพบข้อผิดพลาดในข้อมูล มีคำแนะนำ หรือต้องการรายงานปัญหาการใช้งาน กรุณาติดต่อผ่านช่องทางด้านล่าง</p>

                <div class="contact-box">
                    <?php if (!empty($contactChannels)): ?>
                        <?php foreach ($contactChannels as $ch): ?>
                        <div class="contact-item">
                            <?php if (!empty($ch['icon'])): ?>
                            <div class="contact-icon"><?php echo htmlspecialchars($ch['icon']); ?></div>
                            <?php endif; ?>
                            <div class="contact-info">
                                <h3><?php echo htmlspecialchars($ch['title']); ?></h3>
                                <?php if (!empty($ch['description'])): ?>
                                <p><?php echo htmlspecialchars($ch['description']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($ch['url'])): ?>
                                <a href="<?php echo htmlspecialchars($ch['url']); ?>" target="_blank" rel="noopener noreferrer" style="word-break:break-all;overflow-wrap:anywhere;">
                                    <?php echo htmlspecialchars($ch['url']); ?>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: var(--text-muted, #666); padding: 12px 0;" data-i18n="contact.noChannels">ยังไม่มีช่องทางติดต่อที่กำหนดไว้</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section">
                <h2 data-i18n="contact.section2.title">⚠️ ข้อมูลที่ควรระบุ</h2>
                <p data-i18n="contact.section2.desc">เมื่อรายงานปัญหา กรุณาระบุข้อมูลดังต่อไปนี้:</p>
                <ul>
                    <li data-i18n="contact.section2.item1">อุปกรณ์ที่ใช้งาน (มือถือ/คอมพิวเตอร์, iOS/Android/Windows/Mac)</li>
                    <li data-i18n="contact.section2.item2">Browser ที่ใช้งาน (Safari, Chrome, Firefox, etc.)</li>
                    <li data-i18n="contact.section2.item3">คำอธิบายปัญหาอย่างละเอียด</li>
                    <li data-i18n="contact.section2.item4">ขั้นตอนการทำซ้ำปัญหา (ถ้ามี)</li>
                    <li data-i18n="contact.section2.item5">Screenshot (ถ้าเป็นไปได้)</li>
                </ul>
            </div>

            <div class="disclaimer">
                <strong data-i18n="contact.disclaimer.title">⚠️ ข้อจำกัดความรับผิดชอบ</strong>
                <p id="disclaimerText" data-i18n="contact.disclaimer.text">ปฏิทินนี้เป็น Unofficial Calendar ที่จัดทำขึ้นเพื่อความสะดวกของผู้เข้าชมงาน ข้อมูลอาจมีความคลาดเคลื่อนจากตารางจริงของผู้จัดงาน กรุณาตรวจสอบข้อมูลล่าสุดจากเว็บไซต์ทางการของงาน อีกครั้ง</p>
            </div>
        </div>

        <footer>
            <div class="footer-text">
                <p data-i18n="footer.madeWith">สร้างด้วย ❤️ เพื่อแฟนไอดอล</p>
                <p data-i18n="footer.copyright">© 2026 Idol Stage Timetable. All rights reserved.</p>
                <p>Powered by <a href="https://github.com/fordantitrust/stage-idol-calendar" target="_blank">Stage Idol Calendar</a> <span class="footer-version">v<?php echo APP_VERSION; ?></span></p>
            </div>
        </footer>
    </div>

    <!-- Shared JavaScript -->
    <script>window.SITE_TITLE = <?php echo json_encode(get_site_title()); ?>;</script>
    <script src="<?php echo asset_url('js/translations.js'); ?>"></script>
    <?php
    // Patch disclaimer translations with DB values if configured
    $hasDisclaimerTh = !empty($disclaimer['th']);
    $hasDisclaimerEn = !empty($disclaimer['en']);
    $hasDisclaimerJa = !empty($disclaimer['ja']);
    if ($hasDisclaimerTh || $hasDisclaimerEn || $hasDisclaimerJa):
    ?>
    <script>
    (function() {
        <?php if ($hasDisclaimerTh): ?>
        if (translations.th) translations.th['contact.disclaimer.text'] = <?php echo json_encode($disclaimer['th']); ?>;
        <?php endif; ?>
        <?php if ($hasDisclaimerEn): ?>
        if (translations.en) translations.en['contact.disclaimer.text'] = <?php echo json_encode($disclaimer['en']); ?>;
        <?php endif; ?>
        <?php if ($hasDisclaimerJa): ?>
        if (translations.ja) translations.ja['contact.disclaimer.text'] = <?php echo json_encode($disclaimer['ja']); ?>;
        <?php endif; ?>
    })();
    </script>
    <?php endif; ?>
    <script src="<?php echo asset_url('js/common.js'); ?>"></script>
    <script>
    const DEFAULT_EVENT_SLUG = '<?php echo DEFAULT_EVENT_SLUG; ?>';
    const BASE_PATH = <?php echo json_encode(rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/\\")); ?>;
    </script>    
</body>
</html>
