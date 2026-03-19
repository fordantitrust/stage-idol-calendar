<?php
require_once 'config.php';
send_security_headers();

// Multi-event support
$eventSlug = get_current_event_slug();
$eventMeta = get_event_by_slug($eventSlug);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>วิธีการใช้งาน - <?php echo htmlspecialchars(get_site_title()); ?></title>
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
    <!-- How-to-use page CSS -->
    <link rel="stylesheet" href="<?php echo asset_url('styles/how-to-use.css'); ?>">
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
                <a href="<?php echo event_url('contact.php'); ?>" class="home-icon-btn" data-i18n-title="nav.contact" title="ติดต่อเรา">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            </div>
            <div class="language-switcher">
                <button class="lang-btn active" data-lang="th" onclick="changeLanguage('th')">TH</button>
                <button class="lang-btn" data-lang="en" onclick="changeLanguage('en')">EN</button>
                <button class="lang-btn" data-lang="ja" onclick="changeLanguage('ja')">日本</button>
            </div>
            <h1 data-i18n="howToUse.title">📖 วิธีการใช้งาน</h1>
            <p data-i18n="howToUse.subtitle">คู่มือการใช้งานปฏิทินกิจกรรม Idol Stage Event</p>
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

                <h3 data-i18n="section2.filter3.title">3. กรองตามประเภท</h3>
                <p data-i18n="section2.filter3.desc">เลือกประเภทของ program เช่น Live, Fan Meeting, Talk Show เพื่อดูเฉพาะ program ที่สนใจ</p>
                <div class="feature-box">
                    <strong data-i18n="section2.filter3.tip">💡 เคล็ดลับ:</strong>
                    <span data-i18n="section2.filter3.tipText">สามารถเลือกหลายประเภทพร้อมกันได้ ประเภทจะแสดงเฉพาะเมื่อมีข้อมูลในระบบ</span>
                </div>

                <h3 data-i18n="section2.action.title">4. ดำเนินการ</h3>
                <ul>
                    <li data-i18n="section2.action1"><strong>🔍 ค้นหา:</strong> กดปุ่มนี้เพื่อแสดงผลลัพธ์ตามที่คุณเลือก</li>
                    <li data-i18n="section2.action2"><strong>🔄 รีเซ็ต:</strong> ล้างตัวกรองทั้งหมดและแสดงกิจกรรมทั้งหมด</li>
                </ul>

                <h3 data-i18n="section2.selectedTags.title">5. ดูรายการที่เลือก</h3>
                <p data-i18n="section2.selectedTags.desc">เมื่อเลือกศิลปินหรือเวทีแล้ว จะแสดงเป็น tag ด้านบน checkbox list</p>
                <div class="feature-box">
                    <strong data-i18n="section2.selectedTags.tip">💡 เคล็ดลับ:</strong>
                    <span data-i18n="section2.selectedTags.tipText">กดปุ่ม ✕ ที่ tag เพื่อลบออกและ reload หน้าอัตโนมัติ</span>
                </div>

                <h3 data-i18n="section2.quickFilter.title">6. กรองด่วนจาก badge ในตาราง</h3>
                <p data-i18n="section2.quickFilter.desc">คลิกที่ badge ในตารางรายการเพื่อ append filter ได้ทันที โดยไม่ต้องเลื่อนขึ้นไปใช้ช่อง filter ด้านบน</p>
                <ul>
                    <li data-i18n="section2.quickFilter.item1"><strong>🎤 Badge ศิลปิน (สีชมพู)</strong> — คอลัมน์ "ศิลปินที่เกี่ยวข้อง": คลิกชื่อศิลปินเพื่อเพิ่มเข้า filter</li>
                    <li data-i18n="section2.quickFilter.item2"><strong>🏷️ Badge ประเภท (สีน้ำเงิน)</strong> — คอลัมน์ "ประเภท": คลิกเพื่อกรองตามประเภท program</li>
                </ul>
                <div class="feature-box">
                    <strong data-i18n="section2.quickFilter.tip">💡 เคล็ดลับ:</strong>
                    <span data-i18n="section2.quickFilter.tipText">filter ที่คลิกจะ append ต่อจาก filter ที่มีอยู่ — เลือกหลายศิลปิน/ประเภทได้โดยไม่ลบการเลือกเดิม</span>
                </div>
            </div>

            <div class="section">
                <h2 data-i18n="section3.title">💾 การบันทึกและส่งออก</h2>

                <h3 data-i18n="section3.image.title">1. บันทึกเป็นรูปภาพ (📸)</h3>
                <p data-i18n="section3.image.desc">สร้างรูปภาพ PNG ของตารางกิจกรรมฝั่ง server — ไม่พึ่ง library ภายนอก รองรับภาษาไทย ญี่ปุ่น และ symbol; สีโทนตรงกับ theme ของ event</p>
                <div class="feature-box">
                    <strong data-i18n="section3.image.note">🎨 หมายเหตุ:</strong>
                    <span data-i18n="section3.image.noteText">รูปภาพจะใช้สีโทนเดียวกับ theme ของ event (sakura/ocean/forest ฯลฯ) และรวม filter ที่เลือกไว้ปัจจุบัน</span>
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

                <h3 data-i18n="section3.subscribe.title">3. Subscribe ปฏิทิน (🔔)</h3>
                <p data-i18n="section3.subscribe.desc">Subscribe ครั้งเดียว — ปฏิทินจะ sync อัตโนมัติเมื่อมีการเพิ่ม/แก้ไข program ใหม่ ไม่ต้อง export ซ้ำ</p>
                <ul>
                    <li data-i18n="section3.subscribe.step1">กดปุ่ม "🔔 Subscribe" ในแถบปุ่มด้านบน</li>
                    <li data-i18n="section3.subscribe.step2">เลือกวิธี subscribe ที่ต้องการ:</li>
                </ul>
                <ul>
                    <li data-i18n="section3.subscribe.webcal">🍎 Apple Calendar / iOS / Thunderbird — กด "🔗 เปิดใน Calendar App (webcal://)"</li>
                    <li data-i18n="section3.subscribe.google">🌐 Google Calendar — Copy URL แล้วไปที่ Google Calendar → เพิ่มปฏิทิน → จาก URL</li>
                    <li data-i18n="section3.subscribe.outlook">📧 Microsoft Outlook — Copy URL → Calendar → Add calendar → Subscribe from web → วาง URL</li>
                </ul>
                <div class="feature-box">
                    <strong data-i18n="section3.subscribe.note">💡 หมายเหตุ:</strong>
                    <span data-i18n="section3.subscribe.noteText">URL ที่ Subscribe จะรวม filter ปัจจุบัน (ศิลปิน/เวที/ประเภท) เข้าไปด้วย — Subscribe ก่อนจะได้ filter ที่ต้องการ</span>
                </div>
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
                <h2 data-i18n="section11.title">📅 มุมมองปฏิทินรายเดือน (Calendar View)</h2>
                <p data-i18n="section11.desc">สำหรับ event ที่ Admin กำหนดเป็นโหมด Calendar ระบบจะแสดงผลเป็นปฏิทินรายเดือนแทนตาราง:</p>
                <ul>
                    <li data-i18n="section11.feature1">กด chip บนวันที่ (desktop) เพื่อดูรายละเอียด program</li>
                    <li data-i18n="section11.feature2">กดที่วัน (mobile) เพื่อเปิด panel แสดงรายการ program ของวันนั้น</li>
                    <li data-i18n="section11.feature3">ปุ่ม ◀ ▶ เลื่อนระหว่างเดือนที่มีข้อมูล</li>
                </ul>
                <div class="feature-box">
                    <strong data-i18n="section11.note">💡 หมายเหตุ:</strong>
                    <span data-i18n="section11.noteText">ปุ่ม Live ในรายการ program ช่วยให้เข้าถึง stream โดยตรง</span>
                </div>
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
                <h2 data-i18n="section9.title">📅 กระโดดไปวันที่</h2>
                <p data-i18n="section9.desc">เมื่องานมีหลายวัน แถบวันที่จะปรากฏที่ด้านล่างของหน้าจอ (fixed position) ช่วยให้เลื่อนไปยังวันที่ต้องการได้ทันที</p>
                <ul>
                    <li data-i18n="section9.feature1">กดปุ่มวันที่ที่ต้องการเพื่อเลื่อนหน้าไปยังส่วนนั้นทันที</li>
                    <li data-i18n="section9.feature2">วันที่ที่กำลังดูอยู่จะ highlight อัตโนมัติ (ใช้ IntersectionObserver)</li>
                </ul>
                <div class="feature-box">
                    <strong data-i18n="section9.tip">💡 เคล็ดลับ:</strong>
                    <span data-i18n="section9.tipText">แถบวันที่จะซ่อนตัวเองเมื่อไม่มีหลายวัน และปรากฏเฉพาะเมื่อมี program มากกว่า 1 วันเท่านั้น</span>
                </div>
            </div>

            <div class="section">
                <h2 data-i18n="section10.title">📖 ดูรายละเอียด Program</h2>
                <p data-i18n="section10.desc">description ของ program บางรายการอาจถูกตัดสั้นลง (clamp) — สามารถกดเพื่อดูข้อมูลเต็มได้:</p>
                <ul>
                    <li data-i18n="section10.feature1">มองหาป้าย "▼ อ่านเพิ่มเติม" ใต้ description</li>
                    <li data-i18n="section10.feature2">กดที่ description หรือป้าย "▼ อ่านเพิ่มเติม" เพื่อเปิด modal แสดงข้อมูลเต็ม</li>
                    <li data-i18n="section10.feature3">กด ✕ หรือ tap นอก modal เพื่อปิด</li>
                </ul>
            </div>

            <div class="section">
                <h2 data-i18n="section12.title">👤 หน้าโปรไฟล์ศิลปิน</h2>
                <p data-i18n="section12.desc">ศิลปินแต่ละคนมีหน้าโปรไฟล์แสดง programs ทั้งหมดที่เคยปรากฏข้ามทุก event</p>
                <ul>
                    <li data-i18n="section12.feature1">กดปุ่ม <strong>↗</strong> ข้างชื่อศิลปินใน badge หรือรายการตัวกรองเพื่อเปิดหน้าโปรไฟล์</li>
                    <li data-i18n="section12.feature2">หน้าโปรไฟล์แสดง programs จัดกลุ่มตาม event — เฉพาะงานที่ยังไม่จบ</li>
                    <li data-i18n="section12.feature3">หากศิลปินอยู่ในวง จะแสดง programs ที่แสดงในนามวงด้วย</li>
                    <li data-i18n="section12.feature4">แสดง variant names (ชื่อเรียกอื่น) ของศิลปิน</li>
                    <li data-i18n="section12.feature5">ส่วน "🎪 งานอื่นที่เกี่ยวข้องกับศิลปิน" ด้านล่างตารางแสดง events อื่นที่ศิลปินนี้มี program</li>
                </ul>
            </div>

            <div class="section">
                <h2 data-i18n="section13.title">🗂️ งานที่จบแล้ว</h2>
                <p data-i18n="section13.desc">กดปุ่ม "ดูงานที่จบแล้ว" ที่ด้านล่างหน้ารายการ events เพื่อดู events ทั้งหมดที่สิ้นสุดแล้ว</p>
                <ul>
                    <li data-i18n="section13.feature1">แสดงรายการงานที่จบแล้วแบบ pagination 5 รายการต่อหน้า</li>
                    <li data-i18n="section13.feature2">กดปุ่ม "📋 ดูตารางเวลา" เพื่อเปิดตาราง program ของงานนั้น</li>
                </ul>
            </div>

            <div class="section">
                <h2 data-i18n="section14.title">🎪 การเลือก Event</h2>
                <p data-i18n="section14.desc">เมื่อระบบมีหลาย events ปุ่ม 🎪 (grid-dots) ที่มุมซ้ายบนจะเปิด Event Picker Modal เพื่อเปลี่ยน event ที่ดูอยู่</p>
                <ul>
                    <li data-i18n="section14.feature1">พิมพ์ค้นหาชื่อ event ได้ทันที (รองรับภาษาไทย/English)</li>
                    <li data-i18n="section14.feature2">กรองตามสถานะ: ทั้งหมด / กำลังจัดงาน / กำลังจะมาถึง / จบแล้ว</li>
                    <li data-i18n="section14.feature3">Event ที่กำลังดูอยู่จะมีเครื่องหมาย ✓ และ highlight</li>
                </ul>
                <div class="feature-box">
                    <strong data-i18n="section14.tip">💡 เคล็ดลับ:</strong>
                    <span data-i18n="section14.tipText">กดนอก modal หรือกด ✕ เพื่อปิด — สามารถเข้าถึง event ที่ต้องการได้จากทุกหน้า</span>
                </div>
            </div>

            <div class="section">
                <h2 data-i18n="section15.title">🔔 Subscribe Feed ศิลปิน</h2>
                <p data-i18n="section15.desc">ในหน้าโปรไฟล์ศิลปิน สามารถ subscribe ICS feed เฉพาะศิลปินนั้นได้ — ปฏิทินจะ sync เฉพาะ programs ของศิลปินที่เลือกจากทุก event</p>
                <ul>
                    <li data-i18n="section15.feature1">ปุ่ม 🔔 ชื่อศิลปิน — subscribe programs ทั้งหมดของศิลปินข้ามทุก event</li>
                    <li data-i18n="section15.feature2">ปุ่ม 🔔 ชื่อวง — subscribe programs ที่แสดงในนามวง (แสดงเฉพาะเมื่อศิลปินสังกัดวง)</li>
                    <li data-i18n="section15.feature3">รองรับ Apple Calendar, Google Calendar, Outlook, Thunderbird — เหมือนกับ Subscribe ปกติ</li>
                </ul>
                <div class="feature-box">
                    <strong data-i18n="section15.note">💡 หมายเหตุ:</strong>
                    <span data-i18n="section15.noteText">Artist Feed ดึงข้อมูลข้ามทุก event อัตโนมัติ — ต่างจาก Subscribe ในหน้า event ที่กรองเฉพาะ event นั้น</span>
                </div>
            </div>

            <div class="section">
                <h2 data-i18n="section16.title">🔴 Live Stream</h2>
                <p data-i18n="section16.desc">เมื่อ program มีลิงก์ live stream จะแสดงไอคอน platform และปุ่ม 🔴 เข้าร่วม ในแถว program</p>
                <ul>
                    <li data-i18n="section16.feature1">ไอคอน platform แสดงให้รู้ว่า stream อยู่ที่ไหน (YouTube, X/Twitter, TikTok, หรืออื่นๆ)</li>
                    <li data-i18n="section16.feature2">กดปุ่ม "🔴 เข้าร่วม" เพื่อเปิดลิงก์ stream โดยตรง</li>
                    <li data-i18n="section16.feature3">ใน Calendar View — chip บนปฏิทินจะ highlight สีพิเศษเมื่อ program มี stream</li>
                </ul>
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
                <p data-i18n="footer.copyright">© 2026 Idol Stage Timetable. All rights reserved.</p>
                <p>Powered by <a href="https://github.com/fordantitrust/stage-idol-calendar" target="_blank">Stage Idol Calendar</a> <span class="footer-version">v<?php echo APP_VERSION; ?></span></p>
            </div>
        </footer>
    </div>

    <!-- Shared JavaScript -->
    <script>window.SITE_TITLE = <?php echo json_encode(get_site_title()); ?>;</script>
    <script src="<?php echo asset_url('js/translations.js'); ?>"></script>
    <script src="<?php echo asset_url('js/common.js'); ?>"></script>
</body>
</html>
