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
    <title>วิธีการใช้งาน - Idol Stage Event Calendar</title>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-JBRL4XB417"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-JBRL4XB417');
    </script>
    <!-- Shared CSS -->
    <link rel="stylesheet" href="<?php echo asset_url('styles/common.css'); ?>">
    <style>
        /* Page specific styles */
        .screenshot {
            max-width: 100%;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="language-switcher">
                <button class="lang-btn active" data-lang="th" onclick="changeLanguage('th')">TH</button>
                <button class="lang-btn" data-lang="en" onclick="changeLanguage('en')">EN</button>
                <button class="lang-btn" data-lang="ja" onclick="changeLanguage('ja')">日本</button>
            </div>
            <h1 data-i18n="howToUse.title">📖 วิธีการใช้งาน</h1>
            <p data-i18n="howToUse.subtitle">คู่มือการใช้งานปฏิทินกิจกรรม Idol Stage Event</p>
            <nav class="header-nav">
                <a href="<?php echo event_url('index.php'); ?>" class="header-nav-link" data-i18n="nav.home">🏠 หน้าแรก</a>
                <a href="<?php echo event_url('contact.php'); ?>" class="header-nav-link" data-i18n="nav.contact">✉️ ติดต่อเรา</a>
            </nav>
        </header>

        <div class="content">
            <div class="section">
                <h2 data-i18n="section1.title">🎯 ภาพรวม</h2>
                <p data-i18n="section1.desc">ปฏิทินกิจกรรมนี้ช่วยให้คุณดูตารางการแสดงของศิลปิน Idol ทั้งหมดในงาน Idol Stage Event ได้อย่างง่ายดาย พร้อมฟีเจอร์การกรองและส่งออกข้อมูล</p>
            </div>

            <div class="section">
                <h2 data-i18n="section2.title">🔍 การกรองข้อมูล</h2>

                <h3 data-i18n="section2.filter1.title">1. กรองตามศิลปิน</h3>
                <p data-i18n="section2.filter1.desc">เลือกศิลปินที่คุณสนใจโดยคลิกที่ checkbox หรือใช้ช่องค้นหาเพื่อหาชื่อศิลปินที่ต้องการ</p>
                <div class="feature-box">
                    <strong data-i18n="section2.filter1.tip">💡 เคล็ดลับ:</strong>
                    <span data-i18n="section2.filter1.tipText">คุณสามารถเลือกหลายศิลปินพร้อมกันได้</span>
                </div>

                <h3 data-i18n="section2.filter2.title">2. กรองตามเวที</h3>
                <p data-i18n="section2.filter2.desc">เลือกเวทีที่คุณต้องการดูกิจกรรม เช่น Fan Meeting Hall, Common Stage เป็นต้น</p>

                <h3 data-i18n="section2.action.title">3. ดำเนินการ</h3>
                <ul>
                    <li data-i18n="section2.action1"><strong>🔍 ค้นหา:</strong> กดปุ่มนี้เพื่อแสดงผลลัพธ์ตามที่คุณเลือก</li>
                    <li data-i18n="section2.action2"><strong>🔄 รีเซ็ต:</strong> ล้างตัวกรองทั้งหมดและแสดงกิจกรรมทั้งหมด</li>
                </ul>

                <h3 data-i18n="section2.selectedTags.title">4. ดูรายการที่เลือก</h3>
                <p data-i18n="section2.selectedTags.desc">เมื่อเลือกศิลปินหรือเวทีแล้ว จะแสดงเป็น tag ด้านบน checkbox list</p>
                <div class="feature-box">
                    <strong data-i18n="section2.selectedTags.tip">💡 เคล็ดลับ:</strong>
                    <span data-i18n="section2.selectedTags.tipText">กดปุ่ม ✕ ที่ tag เพื่อลบออกและ reload หน้าอัตโนมัติ</span>
                </div>
            </div>

            <div class="section">
                <h2 data-i18n="section3.title">💾 การบันทึกและส่งออก</h2>

                <h3 data-i18n="section3.image.title">1. บันทึกเป็นรูปภาพ (📸)</h3>
                <p data-i18n="section3.image.desc">บันทึกตารางกิจกรรมเป็นไฟล์รูปภาพ PNG เพื่อแชร์ในโซเชียลมีเดียหรือเก็บไว้ดูออฟไลน์</p>
                <div class="feature-box">
                    <strong data-i18n="section3.image.note">📱 หมายเหตุ:</strong>
                    <span data-i18n="section3.image.noteText">บนมือถือจะบันทึกแบบ card layout, บน desktop จะบันทึกแบบ table</span>
                </div>

                <h3 data-i18n="section3.calendar.title">2. ส่งออกไปปฏิทิน (📅)</h3>
                <p data-i18n="section3.calendar.desc">ดาวน์โหลดไฟล์ .ics เพื่อเพิ่มกิจกรรมเข้าในปฏิทินของคุณ (Google Calendar, Apple Calendar, Outlook)</p>
                <p data-i18n="section3.calendar.steps"><strong>วิธีการ:</strong></p>
                <ul>
                    <li data-i18n="section3.calendar.step1">เลือกศิลปินและเวทีที่ต้องการ</li>
                    <li data-i18n="section3.calendar.step2">กดปุ่ม "📅 Export to Calendar"</li>
                    <li data-i18n="section3.calendar.step3">เปิดไฟล์ .ics ที่ดาวน์โหลดมา</li>
                    <li data-i18n="section3.calendar.step4">เลือกปฏิทินที่ต้องการเพิ่มเข้าไป</li>
                </ul>
            </div>

            <div class="section">
                <h2 data-i18n="section4.title">🌍 การเปลี่ยนภาษา</h2>
                <p data-i18n="section4.desc">คลิกปุ่มเปลี่ยนภาษาที่มุมขวาบนเพื่อสลับระหว่าง:</p>
                <ul>
                    <li data-i18n="section4.lang1"><strong>TH</strong> - ภาษาไทย (เวลาแบบ 24 ชม., ปี พ.ศ.)</li>
                    <li data-i18n="section4.lang2"><strong>EN</strong> - English (12-hour format, Christian year)</li>
                    <li data-i18n="section4.lang3"><strong>日本</strong> - 日本語 (24時間形式)</li>
                </ul>
            </div>

            <div class="section">
                <h2 data-i18n="section7.title">📊 มุมมองไทม์ไลน์ (Gantt Chart)</h2>
                <p data-i18n="section7.desc">นอกจากมุมมองรายการแล้ว ยังมีมุมมองไทม์ไลน์ที่ช่วยให้เห็นภาพรวมของกิจกรรมทั้งวัน:</p>
                <div class="feature-box">
                    <strong>🔄</strong>
                    <span data-i18n="section7.toggle">ใช้ Toggle Switch ด้านล่างปุ่มค้นหาเพื่อสลับมุมมอง</span>
                </div>
                <ul>
                    <li data-i18n="section7.feature1">แสดงหลายเวทีพร้อมกัน เห็นช่วงเวลาซ้อนทับได้ง่าย</li>
                    <li data-i18n="section7.feature2">คลิกที่แถบ program เพื่อดูรายละเอียด</li>
                    <li data-i18n="section7.feature3">ระบบจะจำมุมมองที่คุณเลือกไว้</li>
                </ul>
            </div>

            <div class="section">
                <h2 data-i18n="section8.title">📝 แจ้งเพิ่ม/แก้ไข Program</h2>
                <p data-i18n="section8.desc">หากพบว่าข้อมูลไม่ครบ หรือมี program ใหม่ที่ยังไม่มีในระบบ คุณสามารถแจ้งได้:</p>

                <h3 data-i18n="section8.add.title">1. แจ้งเพิ่ม Program ใหม่</h3>
                <p data-i18n="section8.add.desc">กดปุ่ม "📝 แจ้งเพิ่ม Program" แล้วกรอกข้อมูล program ที่ต้องการ</p>

                <h3 data-i18n="section8.modify.title">2. แจ้งแก้ไข Program ที่มีอยู่</h3>
                <p data-i18n="section8.modify.desc">กดปุ่ม "✏️" ที่ program ที่ต้องการแก้ไข ระบบจะ pre-fill ข้อมูลให้</p>

                <div class="feature-box">
                    <strong data-i18n="section8.note.title">💡 หมายเหตุ:</strong>
                    <span data-i18n="section8.note.text">คำขอของคุณจะถูกส่งไปให้ Admin ตรวจสอบก่อนจะแสดงในระบบ</span>
                </div>
            </div>

            <div class="section">
                <h2 data-i18n="section5.title">📱 การใช้งานบนมือถือ</h2>
                <p data-i18n="section5.desc">หน้าเว็บนี้ออกแบบให้ใช้งานบนมือถือได้อย่างสะดวก:</p>
                <ul>
                    <li data-i18n="section5.feature1">แสดงผลแบบ card สำหรับมือถือ อ่านง่าย</li>
                    <li data-i18n="section5.feature2">ปุ่มกดขนาดใหญ่เหมาะกับการสัมผัส</li>
                    <li data-i18n="section5.feature3">รองรับทั้ง portrait และ landscape</li>
                    <li data-i18n="section5.feature4">บันทึกรูปภาพได้ตามรูปแบบที่แสดงบนหน้าจอ</li>
                </ul>
            </div>

            <div class="section">
                <h2 data-i18n="section6.title">❓ คำถามที่พบบ่อย</h2>

                <h3 data-i18n="section6.q1">Q: ข้อมูลจะอัปเดตบ่อยแค่ไหน?</h3>
                <p data-i18n="section6.a1">A: ข้อมูลจะอัปเดตเมื่อมีการเปลี่ยนแปลงตารางจากทางผู้จัดงาน กรุณาตรวจสอบเวอร์ชันที่มุมซ้ายบน</p>

                <h3 data-i18n="section6.q2">Q: สามารถใช้งานออฟไลน์ได้หรือไม่?</h3>
                <p data-i18n="section6.a2">A: แนะนำให้บันทึกเป็นรูปภาพหรือส่งออกไปยังปฏิทินเพื่อดูออฟไลน์</p>

                <h3 data-i18n="section6.q3">Q: พบข้อมูลผิดพลาดต้องทำอย่างไร?</h3>
                <p data-i18n="section6.a3">A: กรุณาแจ้งผ่านหน้า <a href="<?php echo event_url('contact.php'); ?>" style="color: #667eea; text-decoration: none; font-weight: 600;">ติดต่อเรา</a></p>
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
