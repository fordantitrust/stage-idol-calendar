<?php
require_once 'config.php';
send_security_headers();

// Multi-event support
$eventSlug = get_current_event_slug();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>ติดต่อเรา - Idol Stage Event Calendar</title>
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
</head>
<body>
    <div class="container">
        <header>
            <div class="language-switcher">
                <button class="lang-btn active" data-lang="th" onclick="changeLanguage('th')">TH</button>
                <button class="lang-btn" data-lang="en" onclick="changeLanguage('en')">EN</button>
                <button class="lang-btn" data-lang="ja" onclick="changeLanguage('ja')">日本</button>
            </div>
            <h1 data-i18n="contact.title">✉️ ติดต่อเรา</h1>
            <p data-i18n="contact.subtitle">หากพบปัญหาหรือต้องการข้อมูลเพิ่มเติม</p>
            <nav class="header-nav">
                <a href="<?php echo event_url('index.php'); ?>" class="header-nav-link" data-i18n="nav.home">🏠 หน้าแรก</a>
                <a href="<?php echo event_url('how-to-use.php'); ?>" class="header-nav-link" data-i18n="nav.howToUse">📖 วิธีการใช้งาน</a>
            </nav>
        </header>

        <div class="content">
            <div class="section">
                <h2 data-i18n="contact.section1.title">📧 ช่องทางการติดต่อ</h2>
                <p data-i18n="contact.section1.desc">หากคุณพบข้อผิดพลาดในข้อมูล มีคำแนะนำ หรือต้องการรายงานปัญหาการใช้งาน กรุณาติดต่อผ่านช่องทางด้านล่าง</p>

                <div class="contact-box">
                    <div class="contact-item">
                        <div class="contact-icon">💬</div>
                        <div class="contact-info">
                            <h3 data-i18n="contact.social.title">Twitter (X)</h3>
                            <p data-i18n="contact.social.desc">ติดตามข่าวสารและอัปเดต</p>
                            <a href="https://x.com/FordAntiTrust" target="_blank" rel="noopener noreferrer">
                                @FordAntiTrust
                            </a>
                        </div>
                    </div>
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
                <p data-i18n="contact.disclaimer.text">ปฏิทินนี้เป็น Unofficial Calendar ที่จัดทำขึ้นเพื่อความสะดวกของผู้เข้าชมงาน ข้อมูลอาจมีความคลาดเคลื่อนจากตารางจริงของผู้จัดงาน กรุณาตรวจสอบข้อมูลล่าสุดจากเว็บไซต์ทางการของงาน JP EXPO Thailand อีกครั้ง</p>
            </div>

            <div class="section">
                <h2 data-i18n="contact.section3.title">🙏 ขอบคุณ</h2>
                <p data-i18n="contact.section3.text">ขอบคุณที่ใช้งานปฏิทินกิจกรรมนี้ หากมีข้อเสนอแนะหรือต้องการช่วยพัฒนาโปรเจกต์นี้ เรายินดีรับฟังและต้อนรับเสมอ!</p>
            </div>
        </div>

        <footer>
            <div class="footer-text">
                <p data-i18n="footer.madeWith">สร้างด้วย ❤️ เพื่อแฟนไอดอล</p>
                <p data-i18n="footer.copyright">© 2026 JP EXPO TH Unofficial Calendar. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <!-- Shared JavaScript -->
    <script src="<?php echo asset_url('js/translations.js'); ?>"></script>
    <script src="<?php echo asset_url('js/common.js'); ?>"></script>
</body>
</html>
