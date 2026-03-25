<?php
/**
 * Admin Help Page - คู่มือการใช้งาน Admin
 */
require_once __DIR__ . '/../config.php';
send_security_headers();

require_allowed_ip();
require_login();

$adminUsername = $_SESSION['admin_display_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
$adminRole = $_SESSION['admin_role'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Help - <?php echo htmlspecialchars(get_site_title()); ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('../styles/common.css'); ?>">
    <style>
        :root {
            --admin-primary: #2563eb;
            --admin-primary-dark: #1e40af;
            --admin-primary-light: #dbeafe;
            --admin-gradient: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
            --admin-bg: #f8fafc;
            --admin-surface: #ffffff;
            --admin-border: #cbd5e1;
            --admin-border-light: #e2e8f0;
            --admin-text: #1e293b;
            --admin-text-light: #64748b;
        }

        body { background: var(--admin-bg); color: var(--admin-text); font-family: sans-serif; }

        .admin-container { max-width: 960px; margin: 0 auto; padding: 20px; }

        /* Header */
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 20px;
            background: var(--admin-gradient);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(37,99,235,.15);
            flex-wrap: wrap;
            gap: 15px;
        }
        .admin-header h1 { color: #fff; margin: 0; font-size: 1.75rem; font-weight: 700; }
        .admin-header a {
            color: #fff; text-decoration: none; padding: 8px 16px;
            background: rgba(255,255,255,.2); border-radius: 8px; font-weight: 500;
            transition: all .2s;
        }
        .admin-header a:hover { background: rgba(255,255,255,.3); }

        /* Language switcher */
        .lang-switcher {
            display: inline-flex;
            background: rgba(255,255,255,.15);
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.3);
        }
        .lang-switcher a {
            padding: 6px 14px;
            font-size: 0.85rem;
            border-radius: 0;
            background: transparent;
            border: none;
        }
        .lang-switcher a.active {
            background: rgba(255,255,255,.35);
            font-weight: 700;
        }
        .lang-switcher a:first-child { border-right: 1px solid rgba(255,255,255,.25); }

        /* TOC sidebar layout */
        .help-layout { display: grid; grid-template-columns: 240px 1fr; gap: 24px; align-items: start; }
        @media(max-width:768px){
            .help-layout { grid-template-columns: 1fr; }
            .toc { display: none; }
            .mobile-toc { display: block; } /* แสดง mobile dropdown แทน */
        }

        .toc {
            position: sticky; top: 20px;
            background: var(--admin-surface);
            border: 1px solid var(--admin-border-light);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }
        .toc h3 { margin: 0 0 14px; font-size: 1rem; color: var(--admin-primary-dark); }
        .toc ul { list-style: none; padding: 0; margin: 0; }
        .toc li { margin-bottom: 6px; }
        .toc a {
            color: var(--admin-text-light); text-decoration: none; font-size: .9rem;
            display: block; padding: 4px 8px; border-radius: 6px; transition: all .15s;
        }
        .toc a:hover { color: var(--admin-primary); background: var(--admin-primary-light); }
        .toc .toc-sub { padding-left: 14px; }

        /* Content */
        .help-content { min-width: 0; }

        .help-section {
            background: var(--admin-surface);
            border: 1px solid var(--admin-border-light);
            border-radius: 12px;
            padding: 28px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            scroll-margin-top: 20px;
        }
        .help-section h2 {
            margin: 0 0 18px;
            font-size: 1.35rem;
            color: var(--admin-primary-dark);
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--admin-primary-light);
        }
        .help-section h3 {
            font-size: 1.05rem;
            color: var(--admin-text);
            margin: 20px 0 10px;
        }
        .help-section p { line-height: 1.7; margin: 0 0 12px; }
        .help-section ul, .help-section ol { line-height: 1.8; padding-left: 22px; margin: 0 0 12px; }
        .help-section li { margin-bottom: 4px; }

        /* Badge role */
        .badge-admin { display: inline-block; background: #2563eb; color: #fff; font-size: .72rem; padding: 2px 8px; border-radius: 10px; vertical-align: middle; margin-left: 6px; }
        .badge-agent { display: inline-block; background: #7c3aed; color: #fff; font-size: .72rem; padding: 2px 8px; border-radius: 10px; vertical-align: middle; margin-left: 6px; }

        /* Step boxes */
        .steps { counter-reset: step; padding-left: 0; list-style: none; }
        .steps li {
            position: relative;
            padding: 12px 16px 12px 52px;
            margin-bottom: 10px;
            background: #f8fafc;
            border-left: 3px solid var(--admin-primary);
            border-radius: 0 8px 8px 0;
        }
        .steps li::before {
            counter-increment: step;
            content: counter(step);
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 24px; height: 24px;
            background: var(--admin-gradient);
            color: #fff;
            border-radius: 50%;
            font-size: .8rem;
            font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }

        /* Callout boxes */
        .callout {
            padding: 14px 18px;
            border-radius: 8px;
            margin: 14px 0;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            font-size: .92rem;
        }
        .callout-icon { font-size: 1.3rem; flex-shrink: 0; }
        .callout-tip { background: #ecfdf5; border-left: 4px solid #22c55e; }
        .callout-warn { background: #fffbeb; border-left: 4px solid #f59e0b; }
        .callout-danger { background: #fef2f2; border-left: 4px solid #ef4444; }
        .callout-info { background: var(--admin-primary-light); border-left: 4px solid var(--admin-primary); }

        /* Table */
        .help-table { width: 100%; border-collapse: collapse; font-size: .9rem; margin: 14px 0; }
        .help-table th { background: var(--admin-gradient); color: #fff; padding: 10px 14px; text-align: left; }
        .help-table td { padding: 10px 14px; border-bottom: 1px solid var(--admin-border-light); }
        .help-table tbody tr:hover { background: var(--admin-primary-light); }

        /* Code inline */
        code {
            background: #f1f5f9; border: 1px solid var(--admin-border);
            padding: 2px 6px; border-radius: 4px; font-size: .85em; font-family: monospace;
        }

        /* Quick-ref shortcuts */
        kbd {
            display: inline-block; background: #e2e8f0; border: 1px solid #94a3b8;
            border-radius: 4px; padding: 1px 6px; font-size: .8em; font-family: monospace;
        }

        /* Mobile TOC dropdown */
        .mobile-toc {
            display: none; /* hidden on desktop */
            margin-bottom: 16px;
            border-radius: 10px;
            overflow: hidden;
            background: var(--admin-surface);
            border: 1px solid var(--admin-border-light);
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }
        .mobile-toc-btn {
            width: 100%;
            padding: 14px 16px;
            background: var(--admin-gradient);
            color: #fff;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-align: left;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-height: 44px;
        }
        .mobile-toc-arrow { transition: transform 0.2s; }
        .mobile-toc.open .mobile-toc-arrow { transform: rotate(180deg); }
        .mobile-toc-menu {
            display: none;
            flex-direction: column;
        }
        .mobile-toc.open .mobile-toc-menu { display: flex; }
        .mobile-toc-menu a {
            display: block;
            padding: 12px 16px;
            color: var(--admin-text);
            text-decoration: none;
            font-size: 0.9rem;
            border-bottom: 1px solid var(--admin-border-light);
            min-height: 44px;
            display: flex;
            align-items: center;
        }
        .mobile-toc-menu a:last-child { border-bottom: none; }
        .mobile-toc-menu a:hover { background: var(--admin-primary-light); color: var(--admin-primary); }

        /* Tab guide chips */
        .tab-chip {
            display: inline-flex; align-items: center; gap: 5px;
            background: var(--admin-gradient); color: #fff;
            padding: 3px 12px; border-radius: 20px; font-size: .85rem; font-weight: 600;
            margin: 2px;
        }

        /* =====================================================
           RESPONSIVE - MOBILE (iOS + Android)
           ===================================================== */

        /* ── 768px ── */
        @media (max-width: 768px) {
            .admin-container { padding: 10px; }

            /* Header */
            .admin-header { padding: 14px; gap: 10px; }
            .admin-header h1 { font-size: 1.25rem; }
            .admin-header a { padding: 8px 10px; font-size: 0.85rem; }
        }

        /* ── 600px ── */
        @media (max-width: 600px) {
            /* Help table: horizontal scroll */
            .help-table {
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                white-space: nowrap;
            }
            .help-table th,
            .help-table td {
                white-space: normal;
                min-width: 110px;
                padding: 8px 10px;
                font-size: 0.85rem;
            }
        }

        /* ── 480px: Small phones ── */
        @media (max-width: 480px) {
            .admin-container { padding: 6px; }
            .admin-header { border-radius: 8px; padding: 12px; }
            .admin-header h1 { font-size: 1.05rem; }

            /* Help section */
            .help-section {
                padding: 16px;
                border-radius: 8px;
                margin-bottom: 16px;
            }
            .help-section h2 {
                font-size: 1.15rem;
                margin-bottom: 14px;
            }
            .help-section h3 { font-size: 0.95rem; }

            /* Steps */
            .steps li {
                padding: 10px 12px 10px 46px;
            }
            .steps li::before {
                left: 12px;
                width: 22px;
                height: 22px;
                font-size: 0.75rem;
            }

            /* Tab chips */
            .tab-chip { font-size: 0.8rem; padding: 3px 10px; }

            /* Callout */
            .callout { padding: 10px 12px; }

            /* Code */
            code { font-size: 0.8em; word-break: break-all; }

            /* TOC stays hidden, but add quick-nav chips at top for mobile */
        }
    </style>
</head>
<body>
<div class="admin-container">

    <!-- Header -->
    <div class="admin-header">
        <h1>📖 คู่มือการใช้งาน Admin</h1>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <span style="color:rgba(255,255,255,.9);font-weight:600;">สวัสดี, <?php echo htmlspecialchars($adminUsername); ?>
                <small style="opacity:.7;font-weight:400;">(<?php echo htmlspecialchars($adminRole); ?>)</small>
            </span>
            <div class="lang-switcher">
                <a href="help.php" class="active">TH</a>
                <a href="help-en.php">EN</a>
            </div>
            <a href="index.php">← กลับ Admin</a>
            <a href="../index.php">← หน้าหลัก</a>
        </div>
    </div>

    <!-- Mobile TOC dropdown (hidden on desktop via CSS) -->
    <div class="mobile-toc" id="mobileToc">
        <button class="mobile-toc-btn" onclick="this.parentElement.classList.toggle('open')" aria-expanded="false" aria-controls="mobileTocMenu">
            📑 สารบัญ <span class="mobile-toc-arrow">▼</span>
        </button>
        <div class="mobile-toc-menu" id="mobileTocMenu">
            <a href="#overview">ภาพรวมระบบ</a>
            <a href="#login">การเข้าสู่ระบบ</a>
            <a href="#header">Header &amp; การตั้งค่า</a>
            <a href="#programs">Tab: Programs</a>
            <a href="#events">Tab: Events</a>
            <a href="#requests">Tab: Requests</a>
            <a href="#credits">Tab: Credits</a>
            <a href="#import">Tab: Import</a>
            <a href="#import-type">↳ Program Type</a>
            <a href="#feed">Feed / Subscribe</a>
            <a href="#users">Tab: Users</a>
            <a href="#backup">Tab: Backup</a>
            <a href="#settings">Tab: Settings</a>
            <a href="#contact">Tab: Contact</a>
            <a href="#roles">สิทธิ์ผู้ใช้ (Roles)</a>
            <a href="#tips">เคล็ดลับ &amp; FAQ</a>
        </div>
    </div>

    <div class="help-layout">

        <!-- Table of Contents -->
        <nav class="toc">
            <h3>📑 สารบัญ</h3>
            <ul>
                <li><a href="#overview">ภาพรวมระบบ</a></li>
                <li><a href="#login">การเข้าสู่ระบบ</a></li>
                <li><a href="#header">Header &amp; การตั้งค่า</a></li>
                <li><a href="#programs">Tab: Programs</a>
                    <ul class="toc-sub">
                        <li><a href="#prog-search">ค้นหา &amp; กรอง</a></li>
                        <li><a href="#prog-add">เพิ่ม Program</a></li>
                        <li><a href="#prog-edit">แก้ไข / ลบ</a></li>
                        <li><a href="#prog-bulk">Bulk Actions</a></li>
                        <li><a href="#prog-sort">เรียงลำดับ</a></li>
                    </ul>
                </li>
                <li><a href="#events">Tab: Events</a></li>
                <li><a href="#requests">Tab: Requests</a></li>
                <li><a href="#credits">Tab: Credits</a></li>
                <li><a href="#import">Tab: Import</a>
                    <ul class="toc-sub">
                        <li><a href="#import-type">Program Type</a></li>
                    </ul>
                </li>
                <li><a href="#artists">Tab: Artists</a></li>
                <li><a href="#feed">Feed / Subscribe</a>
                    <ul class="toc-sub">
                        <li><a href="#feed-event">Feed ตาม Event</a></li>
                        <li><a href="#feed-artist">Artist Feed</a></li>
                    </ul>
                </li>
                <li><a href="#users">Tab: Users</a></li>
                <li><a href="#backup">Tab: Backup</a></li>
                <li><a href="#settings">Tab: Settings</a></li>
                <li><a href="#contact">Tab: Contact</a></li>
                <li><a href="#roles">สิทธิ์ผู้ใช้ (Roles)</a></li>
                <li><a href="#tips">เคล็ดลับ &amp; FAQ</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="help-content">

            <!-- Overview -->
            <section class="help-section" id="overview">
                <h2>🌸 ภาพรวมระบบ Admin</h2>
                <p>
                    Admin Panel ของ <strong>Idol Stage Timetable</strong> ใช้สำหรับจัดการข้อมูลทั้งหมดที่แสดงบนเว็บไซต์
                    รวมถึง Programs (รายการแสดง), Events (งาน/convention), คำขอจากผู้ใช้, Credits และการสำรองข้อมูล
                </p>
                <p>Admin Panel ประกอบด้วย <strong>9 แท็บหลัก</strong>:</p>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;">
                    <span class="tab-chip">🎵 Programs</span>
                    <span class="tab-chip">🎪 Events</span>
                    <span class="tab-chip">📝 Requests</span>
                    <span class="tab-chip">✨ Credits</span>
                    <span class="tab-chip">📤 Import</span>
                    <span class="tab-chip">🎤 Artists</span>
                    <span class="tab-chip">👤 Users <span class="badge-admin">admin</span></span>
                    <span class="tab-chip">💾 Backup <span class="badge-admin">admin</span></span>
                    <span class="tab-chip">⚙️ Settings <span class="badge-admin">admin</span></span>
                    <span class="tab-chip">✉️ Contact <span class="badge-admin">admin</span></span>
                </div>
                <div class="callout callout-info" style="margin-top:16px;">
                    <span class="callout-icon">ℹ️</span>
                    <div>แท็บ <strong>👤 Users</strong>, <strong>💾 Backup</strong>, <strong>⚙️ Settings</strong> และ <strong>✉️ Contact</strong> มองเห็นได้เฉพาะผู้ใช้ที่มี role <strong>admin</strong> เท่านั้น</div>
                </div>
            </section>

            <!-- Login -->
            <section class="help-section" id="login">
                <h2>🔐 การเข้าสู่ระบบ</h2>
                <p>เข้า Admin ได้ที่ <code>/admin/login</code> หรือ <code>/admin/login.php</code></p>
                <h3>ขั้นตอนการ Login</h3>
                <ol class="steps">
                    <li>กรอก <strong>Username</strong> และ <strong>Password</strong></li>
                    <li>กด <strong>Login</strong></li>
                    <li>ระบบจะ redirect ไปยังหน้า Admin Dashboard</li>
                </ol>

                <h3>ข้อจำกัดความปลอดภัย</h3>
                <table class="help-table">
                    <thead><tr><th>กฎ</th><th>รายละเอียด</th></tr></thead>
                    <tbody>
                        <tr><td>Rate Limiting</td><td>พยายาม login ผิดพลาดได้สูงสุด <strong>5 ครั้ง / 15 นาที</strong> / IP หากเกินจะถูก block ชั่วคราว</td></tr>
                        <tr><td>Session Timeout</td><td>ไม่มีการใช้งาน <strong>2 ชั่วโมง</strong> จะ logout อัตโนมัติ</td></tr>
                        <tr><td>IP Whitelist</td><td>หากเปิดใช้งาน ระบบจะอนุญาตเฉพาะ IP ที่กำหนดไว้ใน <code>config/admin.php</code></td></tr>
                    </tbody>
                </table>

                <div class="callout callout-warn">
                    <span class="callout-icon">⚠️</span>
                    <div>หาก login ผิดพลาดหลายครั้งและถูก block ให้รอ 15 นาที หรือติดต่อผู้ดูแลระบบเพื่อล้าง <code>cache/login_attempts.json</code></div>
                </div>
            </section>

            <!-- Header -->
            <section class="help-section" id="header">
                <h2>⚙️ Header &amp; การตั้งค่าบัญชี</h2>
                <p>Header แถบสีน้ำเงินด้านบนของทุกหน้า Admin ประกอบด้วย:</p>
                <table class="help-table">
                    <thead><tr><th>ปุ่ม / ข้อมูล</th><th>หน้าที่</th></tr></thead>
                    <tbody>
                        <tr><td>ชื่อผู้ใช้ &amp; Role</td><td>แสดงชื่อที่ login อยู่ และ role ปัจจุบัน (admin / agent)</td></tr>
                        <tr><td>🔑 Change Password</td><td>เปลี่ยนรหัสผ่านของตัวเอง (แสดงเฉพาะผู้ใช้ที่สร้างจาก database)</td></tr>
                        <tr><td>← กลับหน้าหลัก</td><td>ไปยังหน้าเว็บหลัก (index)</td></tr>
                        <tr><td>Logout</td><td>ออกจากระบบ</td></tr>
                    </tbody>
                </table>

                <h3>การเปลี่ยนรหัสผ่าน</h3>
                <ol class="steps">
                    <li>คลิก <strong>🔑 Change Password</strong> ใน header</li>
                    <li>กรอก <em>Current Password</em> (รหัสผ่านปัจจุบัน)</li>
                    <li>กรอก <em>New Password</em> (อย่างน้อย 8 ตัวอักษร)</li>
                    <li>กรอก <em>Confirm New Password</em> ให้ตรงกัน</li>
                    <li>กด <strong>Change Password</strong></li>
                </ol>
            </section>

            <!-- Programs Tab -->
            <section class="help-section" id="programs">
                <h2>📋 Tab: Programs</h2>
                <p>
                    <strong>Programs</strong> คือรายการแสดง/กิจกรรมย่อยภายในงาน เช่น ช่วงการแสดงของแต่ละศิลปิน
                    นี่คือแท็บหลักที่ใช้งานบ่อยที่สุด
                </p>

                <h3 id="prog-search">🔍 ค้นหา &amp; กรองข้อมูล</h3>
                <table class="help-table">
                    <thead><tr><th>ตัวกรอง</th><th>หน้าที่</th></tr></thead>
                    <tbody>
                        <tr><td>Event Selector</td><td>กรองตามงาน (event) เลือก "All Events" เพื่อดูทุกงาน</td></tr>
                        <tr><td>ช่องค้นหา</td><td>ค้นหาชื่อ program, organizer, หรือ description (กด Enter หรือรอ 500ms)</td></tr>
                        <tr><td>✕ (ปุ่มล้าง)</td><td>ล้างคำค้นหาออก</td></tr>
                        <tr><td>Venue Filter</td><td>กรองตามชื่อเวที</td></tr>
                        <tr><td>จากวันที่ / ถึงวันที่</td><td>กรองตามช่วงวันที่</td></tr>
                        <tr><td>Clear Filters</td><td>ล้างตัวกรองทั้งหมด</td></tr>
                        <tr><td>N / หน้า</td><td>เลือกจำนวนรายการต่อหน้า: 20, 50, หรือ 100</td></tr>
                    </tbody>
                </table>

                <h3 id="prog-add">➕ เพิ่ม Program ใหม่</h3>
                <ol class="steps">
                    <li>คลิก <strong>+ เพิ่ม Program</strong> (มุมบนขวา)</li>
                    <li>กรอกข้อมูลในฟอร์ม:</li>
                </ol>
                <table class="help-table">
                    <thead><tr><th>ฟิลด์</th><th>หมายเหตุ</th></tr></thead>
                    <tbody>
                        <tr><td>Event <span style="color:#999">(ไม่บังคับ)</span></td><td>เลือกงานที่ program นี้สังกัด</td></tr>
                        <tr><td>ชื่อ Program <span style="color:red">*</span></td><td>ชื่อการแสดง / กิจกรรม (บังคับ)</td></tr>
                        <tr><td>Organizer</td><td>ชื่อศิลปิน / ผู้จัด</td></tr>
                        <tr><td>เวที</td><td>พิมพ์ชื่อเวที หรือเลือกจาก dropdown autocomplete</td></tr>
                        <tr><td>วันที่ <span style="color:red">*</span></td><td>วันที่จัดการแสดง</td></tr>
                        <tr><td>เวลาเริ่ม / สิ้นสุด <span style="color:red">*</span></td><td>เวลาในรูปแบบ HH:MM</td></tr>
                        <tr><td>Description</td><td>รายละเอียดเพิ่มเติม</td></tr>
                        <tr><td>Artist / Group</td><td>ศิลปินที่เกี่ยวข้องกับ program นี้ — พิมพ์ชื่อแล้วกด <kbd>Enter</kbd> หรือ <kbd>,</kbd> เพื่อเพิ่ม chip; กด <code>×</code> เพื่อลบ; ระบบดึง autocomplete จากตาราง Artists (ไอคอน 🎤 = solo, 🎵 = group); ถ้าพิมพ์ชื่อใหม่ที่ยังไม่มีในระบบจะถูกสร้างอัตโนมัติเมื่อกด <strong>บันทึก</strong></td></tr>
                        <tr><td>Program Type</td><td>ประเภทของ program เช่น <code>stage</code>, <code>booth</code>, <code>meet &amp; greet</code> (ไม่บังคับ รองรับ autocomplete จาก type ที่มีในระบบ)</td></tr>
                        <tr><td>Live Stream URL</td><td>URL ลิงก์ถ่ายทอดสด เช่น YouTube, X/Twitter, TikTok (ต้องเป็น <code>https://</code> เท่านั้น — ค่าอื่นจะถูก ignore); เมื่อกรอกแล้วหน้าเว็บจะแสดงไอคอน platform และปุ่ม <strong>🔴 เข้าร่วม</strong>; ไฟล์ ICS feed จะมี <code>URL:</code> property ด้วย</td></tr>
                    </tbody>
                </table>
                <ol class="steps" start="3">
                    <li>กด <strong>บันทึก</strong></li>
                </ol>

                <h3 id="prog-edit">✏️ แก้ไข &amp; 🗑️ ลบ Program</h3>
                <ul>
                    <li><strong>แก้ไข</strong>: คลิกปุ่ม <strong>✏️</strong> ในคอลัมน์ Actions → แก้ไขข้อมูล → กด <em>บันทึก</em></li>
                    <li><strong>ลบ</strong>: คลิกปุ่ม <strong>🗑️</strong> → ยืนยันในกล่อง popup → ข้อมูลจะถูกลบถาวร</li>
                </ul>
                <div class="callout callout-danger">
                    <span class="callout-icon">🚫</span>
                    <div>การลบ Program <strong>ไม่สามารถย้อนกลับได้</strong> ควรสร้าง Backup ก่อนลบข้อมูลจำนวนมาก</div>
                </div>

                <h3 id="prog-bulk">📦 Bulk Actions (การจัดการหลายรายการพร้อมกัน)</h3>
                <p>เลือกหลาย Program พร้อมกันเพื่อแก้ไขหรือลบในครั้งเดียว:</p>
                <ol class="steps">
                    <li>ติ๊ก <strong>Checkbox</strong> ที่ต้องการ (หรือติ๊ก checkbox ใน header เพื่อเลือกทั้งหน้า)</li>
                    <li>แถบ Bulk Actions (สีเหลือง) จะปรากฏขึ้นด้านบนตาราง</li>
                    <li>เลือกการกระทำ:</li>
                </ol>
                <table class="help-table">
                    <thead><tr><th>ปุ่ม</th><th>หน้าที่</th></tr></thead>
                    <tbody>
                        <tr><td>เลือกทั้งหมด</td><td>เลือก program ทุกรายการในหน้าปัจจุบัน</td></tr>
                        <tr><td>ยกเลิกทั้งหมด</td><td>ยกเลิกการเลือกทั้งหมด</td></tr>
                        <tr><td>✏️ แก้ไขหลายรายการ</td><td>เปลี่ยน Venue / Organizer / Artist&ndash;Group / Program Type ของรายการที่เลือกพร้อมกัน (สูงสุด 100) — ฟิลด์ Artist / Group ใช้ tag-input widget เหมือนการแก้ไขเดี่ยว</td></tr>
                        <tr><td>🗑️ ลบหลายรายการ</td><td>ลบรายการที่เลือกทั้งหมดพร้อมกัน (สูงสุด 100)</td></tr>
                    </tbody>
                </table>
                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>Bulk Edit: ฟิลด์ที่ปล่อยว่างจะ <strong>ไม่ถูกเปลี่ยนแปลง</strong> กรอกเฉพาะฟิลด์ที่ต้องการอัปเดต</div>
                </div>

                <h3 id="prog-sort">↕️ เรียงลำดับข้อมูล</h3>
                <p>คลิกที่หัวคอลัมน์ที่มีไอคอน ↕ เพื่อเรียงลำดับ:</p>
                <ul>
                    <li>คลิกครั้งแรก → เรียง <strong>จากน้อยไปมาก</strong> (↑)</li>
                    <li>คลิกอีกครั้ง → เรียง <strong>จากมากไปน้อย</strong> (↓)</li>
                </ul>
                <p>คอลัมน์ที่เรียงได้: <code>#</code>, <code>Title</code>, <code>Date/Time</code>, <code>Venue</code>, <code>Organizer</code></p>
            </section>

            <!-- Events Tab -->
            <section class="help-section" id="events">
                <h2>🎪 Tab: Events</h2>
                <p>
                    <strong>Events</strong> (เดิมเรียกว่า Conventions) คือข้อมูลของงาน/event หลัก
                    เช่น "Idol Stage Feb 2026" ซึ่ง Programs จะสังกัดอยู่ภายในแต่ละ Event
                </p>

                <h3>ข้อมูลของ Event</h3>
                <table class="help-table">
                    <thead><tr><th>ฟิลด์</th><th>หมายเหตุ</th></tr></thead>
                    <tbody>
                        <tr><td>Name <span style="color:red">*</span></td><td>ชื่อ event เต็ม เช่น "Idol Stage February 2026"</td></tr>
                        <tr><td>Slug <span style="color:red">*</span></td><td>ชื่อย่อสำหรับ URL เช่น <code>idol-stage-feb-2026</code> (ตัวเล็ก, ตัวเลข, - เท่านั้น)</td></tr>
                        <tr><td>Description</td><td>รายละเอียดงาน</td></tr>
                        <tr><td>Start Date / End Date</td><td>วันเริ่มและวันสิ้นสุดของงาน</td></tr>
                        <tr><td>Venue Mode</td><td><strong>multi</strong> = หลายเวที (แสดง venue filter, Gantt) | <strong>single</strong> = เวทีเดียว | <strong>calendar</strong> = ปฏิทินรายเดือน</td></tr>
                        <tr><td>Theme</td><td>Theme สีสำหรับ event นี้โดยเฉพาะ (ถ้าไม่เลือกจะใช้ global theme จาก Settings)</td></tr>
                        <tr><td>Active</td><td>เปิด/ปิดการแสดงผล event บนหน้าเว็บ</td></tr>
                    </tbody>
                </table>

                <h3>Venue Mode: Calendar</h3>
                <p>เมื่อเลือก <strong>calendar</strong> หน้าเว็บของ event นั้นจะแสดงเป็นปฏิทินรายเดือนแทนตาราง/ไทม์ไลน์:</p>
                <ul>
                    <li><strong>Desktop</strong> — แต่ละวันแสดง chips (icon platform + ชื่อศิลปิน + เวลา) กดเพื่อดู detail modal</li>
                    <li><strong>Mobile</strong> — แต่ละวันแสดง dot indicators กดวันเพื่อเปิด panel รายการ program ของวันนั้น</li>
                    <li>ปุ่ม ◀ ▶ นำทางเฉพาะเดือนที่มีข้อมูล (ซ่อนอัตโนมัติถ้ามีแค่เดือนเดียว)</li>
                    <li>เหมาะสำหรับ event ที่มี program กระจายหลายวัน เช่น streaming schedule</li>
                </ul>
                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>Calendar mode ซ่อน Toggle List/Timeline โดยอัตโนมัติ และรองรับทุก theme สี</div>
                </div>

                <h3>การเรียงลำดับ (Sorting)</h3>
                <p>คลิกที่หัวคอลัมน์เพื่อเรียงลำดับ — คลิกซ้ำเพื่อสลับ ↑ / ↓ ไอคอน ↕ หมายถึงยังไม่ได้เรียง</p>
                <table class="help-table">
                    <thead><tr><th>คอลัมน์</th><th>หมายเหตุ</th></tr></thead>
                    <tbody>
                        <tr><td>#</td><td>เรียงตาม ID</td></tr>
                        <tr><td>Name</td><td>เรียงตามชื่อ event (A→Z / Z→A)</td></tr>
                        <tr><td>Start Date</td><td>เรียงตามวันเริ่มงาน (ค่าเริ่มต้น: ใหม่ก่อน)</td></tr>
                        <tr><td>End Date</td><td>เรียงตามวันสิ้นสุดงาน</td></tr>
                        <tr><td>Active</td><td>เรียงตามสถานะ Active/Inactive</td></tr>
                        <tr><td>Programs</td><td>เรียงตามจำนวน program ในงาน</td></tr>
                    </tbody>
                </table>

                <h3>การใช้งาน URL</h3>
                <p>เข้าถึง event เฉพาะผ่าน URL: <code>/event/{slug}</code></p>
                <p>ตัวอย่าง: <code>/event/idol-stage-feb-2026</code></p>

                <div class="callout callout-warn">
                    <span class="callout-icon">⚠️</span>
                    <div>การลบ Event จะ <strong>ไม่ลบ Programs</strong> ที่สังกัดอยู่ แต่ Programs เหล่านั้นจะไม่มี Event อ้างอิง ควรย้ายหรือลบ Programs ก่อน</div>
                </div>

                <h3>Default Event และหน้ารวม Events</h3>
                <p>
                    เมื่อ Initialize Database ระบบจะสร้าง <strong>Default Event</strong> โดยอัตโนมัติ
                    (slug ตรงกับค่า <code>DEFAULT_EVENT_SLUG</code> ใน <code>config/app.php</code> ค่าเริ่มต้นคือ <code>default</code>)
                </p>
                <table class="help-table">
                    <thead><tr><th>สถานการณ์</th><th>ผลที่เกิดขึ้น</th></tr></thead>
                    <tbody>
                        <tr><td>มีเฉพาะ Default Event (ยังไม่ได้สร้าง event จริง)</td><td>หน้าหลัก (<code>/</code>) แสดง <strong>calendar view</strong> ของ default event โดยตรง — ไม่แสดงหน้ารวม</td></tr>
                        <tr><td>มี Event จริงอย่างน้อย 1 รายการ (slug ≠ default)</td><td>หน้าหลัก (<code>/</code>) แสดง <strong>หน้ารวม Events</strong> (event cards) — default event ถูกซ่อน</td></tr>
                        <tr><td>เข้า URL <code>/event/default</code> โดยตรง</td><td>แสดง calendar view ของ default event เสมอ</td></tr>
                    </tbody>
                </table>
                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>
                        <strong>Default Event ถูกซ่อนจากหน้ารวม Events โดยตั้งใจ</strong> — มันทำหน้าที่เป็น container
                        รับ Programs ที่ import โดยไม่ระบุ event ถ้าต้องการให้แสดงในหน้ารวม ให้สร้าง Event ใหม่ที่มี slug ต่างออกไป
                        แล้ว import Programs ไปที่ event นั้นแทน
                    </div>
                </div>
            </section>

            <!-- Requests Tab -->
            <section class="help-section" id="requests">
                <h2>📝 Tab: Requests</h2>
                <p>
                    <strong>Requests</strong> คือคำขอที่ผู้ใช้งานทั่วไปส่งมาผ่านหน้าเว็บ เพื่อขอ
                    <span style="color:#4caf50;font-weight:600;">เพิ่ม Program ใหม่</span> หรือ
                    <span style="color:#2196f3;font-weight:600;">แก้ไข Program ที่มีอยู่</span>
                </p>

                <h3>สถานะของคำขอ</h3>
                <table class="help-table">
                    <thead><tr><th>สถานะ</th><th>ความหมาย</th></tr></thead>
                    <tbody>
                        <tr><td><span style="background:#ff9800;color:#fff;padding:2px 8px;border-radius:10px;font-size:.8rem;">pending</span></td><td>รอดำเนินการ (ยังไม่ได้รีวิว)</td></tr>
                        <tr><td><span style="background:#4caf50;color:#fff;padding:2px 8px;border-radius:10px;font-size:.8rem;">approved</span></td><td>อนุมัติแล้ว (program ถูกสร้าง/อัปเดตแล้ว)</td></tr>
                        <tr><td><span style="background:#f44336;color:#fff;padding:2px 8px;border-radius:10px;font-size:.8rem;">rejected</span></td><td>ปฏิเสธแล้ว</td></tr>
                    </tbody>
                </table>

                <h3>การอนุมัติ / ปฏิเสธคำขอ</h3>
                <ol class="steps">
                    <li>คลิกปุ่ม <strong>👁️ ดู</strong> ที่คำขอที่ต้องการรีวิว</li>
                    <li>ตรวจสอบข้อมูลในหน้าต่าง: ประเภทคำขอ, ข้อมูล Program ที่ขอเพิ่ม/แก้ไข, ข้อมูลผู้แจ้ง</li>
                    <li>หากเป็นคำขอแก้ไข จะแสดง <strong>Comparison View</strong> เปรียบเทียบข้อมูลเดิมและใหม่</li>
                    <li>กด <strong>✅ อนุมัติ</strong> เพื่ออนุมัติและ auto-สร้าง/อัปเดต Program หรือกด <strong>❌ ปฏิเสธ</strong></li>
                </ol>

                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>จำนวนคำขอ <strong>pending</strong> จะแสดงเป็น badge สีแดงบน tab "Requests" เพื่อเตือนให้รีวิว</div>
                </div>

                <h3>การกรอง Requests</h3>
                <ul>
                    <li><strong>Event Filter</strong>: กรองคำขอตาม event</li>
                    <li><strong>Status Filter</strong>: เลือกดูเฉพาะสถานะที่ต้องการ (pending, approved, rejected, ทุกสถานะ)</li>
                </ul>
            </section>

            <!-- Credits Tab -->
            <section class="help-section" id="credits">
                <h2>📋 Tab: Credits</h2>
                <p>
                    <strong>Credits</strong> คือรายการขอบคุณ/อ้างอิงข้อมูล ที่แสดงในหน้า Credits ของเว็บไซต์
                    เช่น ขอบคุณแหล่งข้อมูล, ผู้สนับสนุน, หรือศิลปิน
                </p>

                <h3>ข้อมูลของ Credit</h3>
                <table class="help-table">
                    <thead><tr><th>ฟิลด์</th><th>หมายเหตุ</th></tr></thead>
                    <tbody>
                        <tr><td>Title <span style="color:red">*</span></td><td>ชื่อ/หัวข้อที่จะแสดง (สูงสุด 200 ตัวอักษร)</td></tr>
                        <tr><td>Link</td><td>URL เว็บไซต์หรือโปรไฟล์ (ถ้ามี)</td></tr>
                        <tr><td>Description</td><td>คำอธิบายหรือรายละเอียดเพิ่มเติม</td></tr>
                        <tr><td>Display Order</td><td>ลำดับการแสดงผล (เลขน้อยขึ้นก่อน, default = 0)</td></tr>
                        <tr><td>Event</td><td>ระบุ event ที่ credit นี้สังกัด (ว่าง = แสดงทุก event / Global)</td></tr>
                    </tbody>
                </table>

                <h3>Bulk Delete Credits</h3>
                <p>เลือก checkbox หลายรายการ → แถบ Bulk Actions จะปรากฏ → กด <strong>🗑️ ลบหลายรายการ</strong></p>

                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>ใช้ <strong>Display Order</strong> เพื่อจัดลำดับ Credits ที่แสดงบนหน้าเว็บ เลขน้อย = ขึ้นก่อน</div>
                </div>
            </section>

            <!-- Import ICS Tab -->
            <section class="help-section" id="import">
                <h2>📤 Tab: Import</h2>
                <p>
                    นำเข้าข้อมูล Programs จากไฟล์ <strong>.ics</strong> (iCalendar format)
                    ระบบจะ parse ไฟล์และแสดง Preview ก่อนยืนยันการ import
                </p>

                <h3>ขั้นตอนการ Import</h3>
                <ol class="steps">
                    <li>เลือก <strong>Event</strong> ปลายทางที่จะ import Programs เข้าไป</li>
                    <li>คลิกพื้นที่อัปโหลด หรือ<strong>ลากไฟล์ .ics</strong> มาวาง (สูงสุด 5MB)</li>
                    <li>ระบบจะ parse ไฟล์และแสดง <strong>Preview Table</strong></li>
                    <li>ตรวจสอบรายการ:
                        <ul>
                            <li><span style="background:#c8e6c9;padding:1px 6px;border-radius:4px;">➕ ใหม่</span> = Program ที่จะเพิ่มใหม่</li>
                            <li><span style="background:#fff9c4;padding:1px 6px;border-radius:4px;">⚠️ ซ้ำ</span> = มี UID ตรงกับที่มีอยู่แล้วในฐานข้อมูล</li>
                            <li><span style="background:#ffcdd2;padding:1px 6px;border-radius:4px;">❌ ผิดพลาด</span> = ข้อมูลไม่สมบูรณ์</li>
                        </ul>
                    </li>
                    <li>สำหรับรายการซ้ำ เลือก action: <strong>Insert</strong> (เพิ่มอีกรายการ) / <strong>Update</strong> (อัปเดตรายการเดิม) / <strong>Skip</strong> (ข้าม)</li>
                    <li>ยกเลิกรายการที่ไม่ต้องการโดย untick checkbox หรือกด <strong>ลบที่เลือก</strong></li>
                    <li>กด <strong>✅ ยืนยันการ Import</strong></li>
                    <li>ระบบจะแสดง <strong>Import Summary</strong> (จำนวนที่เพิ่ม/อัปเดต/ข้าม/ผิดพลาด)</li>
                </ol>

                <h3>รูปแบบไฟล์ ICS ที่รองรับ</h3>
                <table class="help-table">
                    <thead><tr><th>Field ICS</th><th>จับคู่กับ</th></tr></thead>
                    <tbody>
                        <tr><td>SUMMARY</td><td>ชื่อ Program (title)</td></tr>
                        <tr><td>DTSTART / DTEND</td><td>วันเวลาเริ่ม / สิ้นสุด</td></tr>
                        <tr><td>LOCATION</td><td>เวที (venue)</td></tr>
                        <tr><td>ORGANIZER (CN)</td><td>ผู้จัด (organizer)</td></tr>
                        <tr><td>CATEGORIES</td><td>หมวดหมู่ (categories)</td></tr>
                        <tr><td>DESCRIPTION</td><td>รายละเอียด</td></tr>
                        <tr><td>UID</td><td>Unique ID (ใช้ตรวจจับ duplicate)</td></tr>
                        <tr><td>X-PROGRAM-TYPE</td><td>ประเภท program (<code>program_type</code>) — custom field เฉพาะระบบนี้ กำหนดได้แต่ละ event</td></tr>
                    </tbody>
                </table>

                <h3 id="import-type">🏷️ การกำหนด Program Type ตอน Import</h3>
                <p>ระบบรองรับการกำหนด Program Type ระหว่าง import ด้วย 3 วิธี (เรียงตามลำดับความสำคัญ):</p>
                <table class="help-table">
                    <thead><tr><th>วิธี</th><th>รายละเอียด</th></tr></thead>
                    <tbody>
                        <tr><td>1. <code>X-PROGRAM-TYPE:</code> ในไฟล์ ICS</td><td>กำหนด type แยกตาม event แต่ละรายการ — มีความสำคัญสูงสุด</td></tr>
                        <tr><td>2. ช่อง "🏷️ Program Type (default)" ใน UI</td><td>ใช้กับ programs ที่ไม่มี <code>X-PROGRAM-TYPE</code> ในไฟล์ (batch default)</td></tr>
                        <tr><td>3. <code>--type=value</code> (command line)</td><td>ใช้ค่านี้เป็น default สำหรับทุก program เมื่อ import ผ่าน CLI</td></tr>
                    </tbody>
                </table>
                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>สามารถ import ICS ผ่าน command line ได้ด้วย: <code>php tools/import-ics-to-sqlite.php --event=slug --type=stage</code></div>
                </div>
            </section>

            <!-- Artists Tab -->
            <section class="help-section" id="artists">
                <h2>🎤 Tab: Artists</h2>
                <p>จัดการข้อมูลศิลปินทั้งหมดในระบบ — ศิลปินสามารถปรากฏใน program ของหลาย events ได้ (Artist Reuse System)</p>

                <h3>ข้อมูลของ Artist</h3>
                <table class="help-table">
                    <thead><tr><th>ฟิลด์</th><th>หมายเหตุ</th></tr></thead>
                    <tbody>
                        <tr><td>ชื่อ <span style="color:red">*</span></td><td>ชื่อหลักของศิลปิน ใช้ match กับ CATEGORIES ใน ICS</td></tr>
                        <tr><td>ประเภท</td><td><strong>Solo</strong> = ศิลปินเดี่ยว | <strong>Group</strong> = วง/กลุ่ม</td></tr>
                        <tr><td>กลุ่มที่สังกัด</td><td>สำหรับ Solo artist — เลือกวงที่สังกัด (ถ้ามี)</td></tr>
                        <tr><td>Variants</td><td>ชื่อเรียกอื่น เช่น ชื่อย่อ, ชื่อภาษาอื่น, ชื่อเก่า</td></tr>
                    </tbody>
                </table>

                <h3>Variants (ชื่อเรียกอื่น)</h3>
                <p>ระบบใช้ variants เพื่อ match ชื่อศิลปินจากไฟล์ ICS ที่อาจสะกดต่างกัน:</p>
                <ul>
                    <li>กดปุ่ม <strong>Variants</strong> ในแถวของศิลปินเพื่อเปิด modal จัดการ variants</li>
                    <li>กด <strong>+ เพิ่ม</strong> แล้วพิมพ์ชื่อเรียกอื่น → กด Add</li>
                    <li>กด <strong>×</strong> ข้าง variant เพื่อลบ</li>
                </ul>
                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>เมื่อ Import ICS ระบบจะ auto-link program กับ artist โดย match ชื่อจาก CATEGORIES — ทั้งชื่อหลักและ variants</div>
                </div>

                <h3>Copy Artist (คัดลอกศิลปิน)</h3>
                <p>ปุ่ม <strong>Copy</strong> ในแต่ละแถวของตาราง Artists เปิด modal สำหรับสร้างศิลปินใหม่โดยอิงจากศิลปินที่มีอยู่:</p>
                <ul>
                    <li>ข้อมูลถูก pre-fill จากต้นฉบับ (ชื่อ + " (copy)", ประเภท, กลุ่มที่สังกัด)</li>
                    <li>ส่วน <strong>Variants ที่จะ copy</strong> แสดง checkbox ทุก variant ของต้นฉบับ (ติ๊กทั้งหมด default)</li>
                    <li>ปุ่ม "เลือกทั้งหมด" / "ยกเลิกทั้งหมด" สำหรับ variants; สามารถแก้ไขทุก field ก่อน Save</li>
                    <li>หลัง Save ระบบสร้าง artist ใหม่ แล้ว loop สร้าง variants ที่เลือกทั้งหมด</li>
                </ul>

                <h3>Bulk Import Artists (นำเข้าหลายคนพร้อมกัน)</h3>
                <p>ปุ่ม <strong>📥 Import หลายคน</strong> ใน toolbar เปิด modal สำหรับ import ศิลปินหลายคนในคราวเดียว:</p>
                <ul>
                    <li><strong>Step 1</strong>: พิมพ์ชื่อศิลปิน 1 บรรทัดต่อ 1 คน (สูงสุด 500 ชื่อ); เลือก checkbox "เป็นกลุ่ม" และกลุ่มปลายทาง (ถ้ามี)</li>
                    <li><strong>Step 2</strong>: ดูผลลัพธ์ ✅ สร้างใหม่ / ⚠️ ชื่อซ้ำ / ❌ Error พร้อม summary bar; artist list refresh อัตโนมัติ</li>
                </ul>

                <h3>Bulk Select & Bulk Actions (เลือกหลายคนพร้อมกัน)</h3>
                <p>ตาราง Artists มี checkbox column สำหรับเลือกหลายคนพร้อมกัน — Bulk Toolbar (แถบสีเหลือง) แสดงขึ้นเมื่อเลือกอย่างน้อย 1 รายการ:</p>
                <table class="help-table">
                    <thead><tr><th>Action</th><th>คำอธิบาย</th></tr></thead>
                    <tbody>
                        <tr><td>👥 เพิ่มเข้ากลุ่ม</td><td>เปิด modal เลือกกลุ่มปลายทาง → ตั้ง <code>group_id</code> ให้ศิลปินที่เลือกทั้งหมด (ข้าม artists ที่เป็น Group)</td></tr>
                        <tr><td>🚫 ถอดออกจากกลุ่ม</td><td>ตั้ง <code>group_id = null</code> ให้ศิลปินที่เลือกทั้งหมด</td></tr>
                    </tbody>
                </table>
                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>Bulk Add to Group ข้าม artists ที่มี <code>is_group = 1</code> โดยอัตโนมัติ — SQL กรอง <code>WHERE is_group = 0</code> เฉพาะ Solo artists เท่านั้น</div>
                </div>

                <h3>Artist Portal (หน้ารายการศิลปินสำหรับผู้ใช้)</h3>
                <p>หน้า <code>/artists</code> เป็นหน้า public ที่รวบรวมกลุ่มและศิลปินทุกคนในระบบ — ผู้ใช้เข้าถึงได้จากเมนู "🎤 ศิลปิน" บนหน้าแรก</p>
                <ul>
                    <li>Groups แสดงเป็น card พร้อมสมาชิก (chip), จำนวน programs และลิงก์ไปหน้าโปรไฟล์</li>
                    <li>Solo artists แสดงเป็น grid card พร้อมจำนวน programs</li>
                    <li>ค้นหา realtime — ค้นได้ทั้งชื่อกลุ่ม, ชื่อสมาชิก และศิลปินเดี่ยว</li>
                </ul>
                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>ข้อมูลบนหน้า Artist Portal มาจาก cache (<code>cache/query_portal.json</code>, TTL 1 ชั่วโมง) — cache ถูก invalidate อัตโนมัติทุกครั้งที่มีการเพิ่ม/แก้ไข/ลบ artist หรือ variant ในหน้า Admin นี้</div>
                </div>

                <h3>Artist Profile Page</h3>
                <p>ชื่อ artist ในตาราง Artists เป็น link ไปหน้าโปรไฟล์ <code>/artist/{id}</code> ที่แสดงต่อผู้ใช้ — แสดง programs จัดกลุ่มตาม event เฉพาะงานที่ยังไม่จบ</p>

                <h3>ความสัมพันธ์กับฟอร์ม Program</h3>
                <p>ฟิลด์ <strong>Artist / Group</strong> ในฟอร์มเพิ่ม/แก้ไข Program เชื่อมตรงกับตาราง Artists นี้:</p>
                <ul>
                    <li>พิมพ์ชื่อ → ระบบ autocomplete จากตาราง Artists</li>
                    <li>เลือกจาก dropdown หรือกด <kbd>Enter</kbd>/<kbd>,</kbd> เพื่อเพิ่มเป็น chip</li>
                    <li>ถ้าชื่อที่พิมพ์ <strong>ไม่มีในระบบ</strong> จะถูกสร้าง artist ใหม่อัตโนมัติตอนกด <em>บันทึก</em></li>
                    <li>เมื่อบันทึก ระบบ sync <code>program_artists</code> junction table ทันที → filter ศิลปินในหน้า public ทำงานถูกต้อง</li>
                </ul>
                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>Artist ที่สร้างผ่านฟอร์ม Program จะปรากฏในตาราง Artists tab นี้โดยอัตโนมัติ สามารถเพิ่ม Variants หรือกำหนดกลุ่มที่สังกัดได้ภายหลัง</div>
                </div>
            </section>

            <!-- Feed / Subscribe -->
            <section class="help-section" id="feed">
                <h2>🔔 Feed / Subscribe</h2>
                <p>ระบบรองรับ ICS Subscription Feed ที่ปฏิทิน (Google Calendar, Apple Calendar, Outlook, Thunderbird) สามารถ pull อัตโนมัติได้ ผู้ใช้ไม่ต้อง export ซ้ำทุกครั้งที่ข้อมูลเปลี่ยน</p>

                <h3 id="feed-event">📅 Feed ตาม Event</h3>
                <p>ปุ่ม <strong>🔔 Subscribe</strong> ในหน้าแสดงตารางงาน (<code>/event/{slug}</code>) เปิด modal ให้ผู้ใช้ copy URL หรือกด webcal:// โดย URL จะรวม filter ปัจจุบัน (artist[], venue[], type[]) เข้าไปด้วยอัตโนมัติ</p>
                <table class="help-table">
                    <thead><tr><th>Endpoint</th><th>คำอธิบาย</th></tr></thead>
                    <tbody>
                        <tr><td><code>/feed</code></td><td>Feed ของ Default Event (ไม่ระบุ slug)</td></tr>
                        <tr><td><code>/event/{slug}/feed</code></td><td>Feed ของ Event เฉพาะ (filter ด้วย artist[], venue[], type[] query string ได้)</td></tr>
                    </tbody>
                </table>
                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>Feed ใช้ static file cache (<code>cache/feed_*.ics</code>, TTL 1 ชั่วโมง) — ทุกครั้งที่ Admin เขียนข้อมูล (เพิ่ม/แก้ไข/ลบ Program, import ICS) cache จะถูก invalidate ทันที ผู้ subscribe จะได้ข้อมูลใหม่ในรอบ pull ถัดไปของปฏิทิน</div>
                </div>

                <h3 id="feed-artist">🎤 Artist Feed (Feed เฉพาะศิลปิน)</h3>
                <p>หน้าโปรไฟล์ศิลปิน (<code>/artist/{id}</code>) มีปุ่ม subscribe แยก 2 ปุ่ม:</p>
                <table class="help-table">
                    <thead><tr><th>ปุ่ม</th><th>Endpoint</th><th>ดึงข้อมูล</th></tr></thead>
                    <tbody>
                        <tr><td>🔔 ชื่อศิลปิน</td><td><code>/artist/{id}/feed</code></td><td>Programs ทั้งหมดของศิลปินข้ามทุก event (ชื่อ + variant names ทั้งหมด)</td></tr>
                        <tr><td>🔔 ชื่อวง</td><td><code>/artist/{id}/feed?group=1</code></td><td>Programs ที่แสดงในนามวงที่ศิลปินสังกัด (แสดงเฉพาะเมื่อ artist มี group_id)</td></tr>
                    </tbody>
                </table>
                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>Artist Feed ดึงข้อมูลข้ามทุก event — เฉพาะ programs จาก <strong>Active events</strong> เท่านั้น; ใช้ตาราง <code>artist_variants</code> ในการ match ชื่อ artist กับ field <code>categories</code> ของ program; cache key แยกระหว่าง <code>_own</code> และ <code>_group</code></div>
                </div>
                <div class="callout callout-warning">
                    <span class="callout-icon">⚠️</span>
                    <div>เมื่อแก้ไข Artist Variants ข้อมูลใน Artist Feed จะ reflect ใน pull รอบถัดไปอัตโนมัติ (cache TTL 1 ชั่วโมง หรือหลัง Admin เขียนข้อมูล Program/Artist)</div>
                </div>
            </section>

            <!-- Users Tab -->
            <section class="help-section" id="users">
                <h2>👤 Tab: Users <span class="badge-admin">admin only</span></h2>
                <p>จัดการบัญชีผู้ใช้ Admin ทั้งหมด เฉพาะผู้ใช้ที่มี role <strong>admin</strong> เท่านั้น</p>

                <h3>ข้อมูลของ User</h3>
                <table class="help-table">
                    <thead><tr><th>ฟิลด์</th><th>หมายเหตุ</th></tr></thead>
                    <tbody>
                        <tr><td>Username <span style="color:red">*</span></td><td>ชื่อผู้ใช้สำหรับ login (ตัวอักษร, ตัวเลข, <code>_</code>, <code>-</code>, <code>.</code>)</td></tr>
                        <tr><td>Display Name</td><td>ชื่อที่แสดงใน header admin</td></tr>
                        <tr><td>Password</td><td>อย่างน้อย 8 ตัวอักษร (เมื่อแก้ไข: เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยน)</td></tr>
                        <tr><td>Role</td><td><strong>admin</strong> = เข้าถึงทุกแท็บ | <strong>agent</strong> = จัดการ Programs เท่านั้น</td></tr>
                        <tr><td>Active</td><td>เปิด/ปิดบัญชี (ปิด = login ไม่ได้)</td></tr>
                    </tbody>
                </table>

                <h3>ข้อจำกัดเพื่อป้องกัน Lockout</h3>
                <ul>
                    <li>ไม่สามารถ<strong>ลบตัวเอง</strong></li>
                    <li>ไม่สามารถ<strong>เปลี่ยน role ของตัวเอง</strong></li>
                    <li>ต้องมี admin อย่างน้อย 1 คนอยู่เสมอ (ห้ามลบ admin คนสุดท้าย)</li>
                </ul>

                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>
                        Users ที่สร้างผ่าน Admin UI จะเก็บไว้ในฐานข้อมูล SQLite<br>
                        หากฐานข้อมูลยังไม่มีตาราง <code>admin_users</code> ระบบจะใช้ credentials จาก <code>config/admin.php</code> แทน
                    </div>
                </div>
            </section>

            <!-- Backup Tab -->
            <section class="help-section" id="backup">
                <h2>💾 Tab: Backup <span class="badge-admin">admin only</span></h2>
                <p>สำรองและกู้คืนฐานข้อมูล SQLite ทั้งหมด</p>

                <h3>การสร้าง Backup</h3>
                <ol class="steps">
                    <li>คลิก <strong>💾 สร้าง Backup</strong></li>
                    <li>ระบบจะสร้างไฟล์ <code>backup_YYYYMMDD_HHMMSS.db</code> ใน folder <code>backups/</code></li>
                    <li>ไฟล์ backup จะปรากฏในตารางด้านล่าง</li>
                </ol>

                <h3>การดาวน์โหลด Backup</h3>
                <p>คลิก <strong>⬇️ Download</strong> ที่ backup ที่ต้องการ เพื่อดาวน์โหลดไฟล์ .db มาเก็บไว้ในเครื่อง</p>

                <h3>การ Restore จาก Backup</h3>
                <ol class="steps">
                    <li>คลิก <strong>🔄 Restore</strong> ที่ backup ที่ต้องการ</li>
                    <li>อ่านคำเตือน: ระบบจะสร้าง auto-backup ก่อน restore</li>
                    <li>กด <strong>Restore</strong> เพื่อยืนยัน</li>
                </ol>

                <h3>การ Restore จากไฟล์ที่อัปโหลด</h3>
                <ol class="steps">
                    <li>คลิก <strong>📤 Upload &amp; Restore</strong></li>
                    <li>เลือกไฟล์ <code>.db</code> จากเครื่อง</li>
                    <li>ยืนยัน (ระบบสร้าง auto-backup ก่อนเสมอ)</li>
                </ol>

                <div class="callout callout-danger">
                    <span class="callout-icon">🚫</span>
                    <div>
                        <strong>การ Restore จะแทนที่ข้อมูลทั้งหมดในฐานข้อมูลปัจจุบัน!</strong><br>
                        ระบบจะสร้าง auto-backup อัตโนมัติก่อน restore เสมอ แต่ควรตรวจสอบให้แน่ใจก่อนดำเนินการ
                    </div>
                </div>

                <h3>การลบ Backup</h3>
                <p>คลิก <strong>🗑️ ลบ</strong> ที่ backup ที่ต้องการ → ยืนยัน → ไฟล์จะถูกลบถาวร</p>
            </section>

            <!-- Settings Tab -->
            <section class="help-section" id="settings">
                <h2>⚙️ Tab: Settings <span class="badge-admin">admin only</span></h2>
                <p>ตั้งค่าทั่วไปของเว็บไซต์ ได้แก่ <strong>Site Title</strong>, <strong>Site Theme</strong> และ <strong>Disclaimer</strong> เฉพาะผู้ใช้ที่มี role <strong>admin</strong> เท่านั้น</p>

                <h3>📝 Site Title คืออะไร</h3>
                <p>
                    ชื่อเว็บไซต์ที่แสดงใน <strong>browser tab</strong>, <strong>header</strong> ของทุกหน้า public
                    และ <strong>ICS export</strong> (ชื่อปฏิทิน) ค่าเริ่มต้นคือ "Idol Stage Timetable"
                </p>

                <h3>ขั้นตอนการเปลี่ยน Site Title</h3>
                <ol class="steps">
                    <li>คลิกแท็บ <strong>⚙️ Settings</strong></li>
                    <li>พิมพ์ชื่อใหม่ในช่อง <strong>Site Title</strong> (สูงสุด 100 ตัวอักษร)</li>
                    <li>กด <strong>💾 บันทึก Title</strong></li>
                    <li>เห็นข้อความ <strong>✅ บันทึกแล้ว</strong> → reload หน้าเว็บ public เพื่อดูผล</li>
                </ol>

                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>ชื่อเว็บไซต์ถูกเก็บไว้ใน <code>cache/site-settings.json</code> และมีผลกับ browser tab, header, footer copyright และ ICS calendar name</div>
                </div>

                <h3>🎨 Site Theme คืออะไร</h3>
                <p>
                    Admin สามารถเลือก theme สีสำหรับหน้าเว็บ public ทั้งหมด (หน้าหลัก, วิธีใช้, ติดต่อ, credits)
                    โดย server จะโหลด CSS ของ theme ที่เลือกโดยอัตโนมัติ ผู้ใช้ทุกคนจะเห็น theme เดียวกัน
                </p>

                <h3>Themes ที่ใช้ได้</h3>
                <table class="help-table">
                    <thead><tr><th>Theme</th><th>สี</th></tr></thead>
                    <tbody>
                        <tr><td>🌸 Sakura</td><td>ชมพู</td></tr>
                        <tr><td>🌊 Ocean</td><td>ฟ้า</td></tr>
                        <tr><td>🌿 Forest</td><td>เขียว</td></tr>
                        <tr><td>🌙 Midnight</td><td>ม่วง</td></tr>
                        <tr><td>☀️ Sunset</td><td>ส้ม</td></tr>
                        <tr><td>🖤 Dark</td><td>น้ำเงิน-เทา (Charcoal) — <em>ค่า fallback เมื่อไม่มีการตั้งค่า</em></td></tr>
                        <tr><td>🩶 Gray</td><td>เทา (Silver)</td></tr>
                    </tbody>
                </table>

                <h3>ขั้นตอนการเปลี่ยน Theme</h3>
                <ol class="steps">
                    <li>คลิกแท็บ <strong>⚙️ Settings</strong></li>
                    <li>ระบบจะโหลด theme ปัจจุบันและแสดง palette สี</li>
                    <li>คลิกที่วงกลมสีของ theme ที่ต้องการ (กรอบจะเปลี่ยนเป็น selected)</li>
                    <li>กด <strong>💾 บันทึก Theme</strong></li>
                    <li>เห็นข้อความ <strong>✅ บันทึกแล้ว</strong> → เปิดหน้าเว็บ public เพื่อดูผล</li>
                </ol>

                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>Theme มีผลทันทีเมื่อ reload หน้าเว็บ public ไม่ต้อง restart server ใดๆ</div>
                </div>

                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>ข้อมูล theme ถูกเก็บไว้ใน <code>cache/site-theme.json</code> และ server จะอ่านไฟล์นี้ทุกครั้งที่โหลดหน้าเว็บ</div>
                </div>

                <h3>🎨 Per-Event Theme (Theme ตาม Event)</h3>
                <p>
                    นอกจาก global theme แล้ว ยังสามารถกำหนด theme เฉพาะสำหรับแต่ละ Event ได้
                    โดยระบบจะเลือก theme ตาม <strong>ลำดับความสำคัญ</strong> ดังนี้:
                </p>
                <ol class="steps">
                    <li><strong>Theme ของ Event</strong> — ถ้า Event มี theme ที่ตั้งค่าไว้ จะใช้ theme นั้นสำหรับทุกหน้าใน event นั้น</li>
                    <li><strong>Global theme</strong> — ถ้า Event ไม่ได้เลือก theme จะใช้ global theme จากแท็บ Settings</li>
                    <li><strong>Fallback: <code>dark</code></strong> — ถ้าทั้งสองข้อข้างต้นไม่มีการตั้งค่า จะใช้ theme <code>dark</code> โดยอัตโนมัติ</li>
                </ol>

                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>ตั้งค่า theme ของ Event ได้ที่แท็บ <strong>🎪 Events</strong> → คลิก <strong>➕ เพิ่ม Event</strong> หรือ <strong>✏️</strong> แก้ไข → ช่อง <strong>Theme</strong></div>
                </div>

                <h3>⚠️ Disclaimer (ข้อจำกัดความรับผิดชอบ)</h3>
                <p>
                    ข้อความ disclaimer ที่แสดงในหน้า <strong>ติดต่อเรา</strong> รองรับ 3 ภาษา (ไทย / English / 日本語)
                    หากเว้นว่างจะใช้ค่า default จาก <code>translations.js</code>
                </p>

                <h3>ขั้นตอนตั้งค่า Disclaimer</h3>
                <ol class="steps">
                    <li>คลิกแท็บ <strong>⚙️ Settings</strong></li>
                    <li>เลื่อนลงมาที่ส่วน <strong>Disclaimer</strong></li>
                    <li>พิมพ์ข้อความในช่อง 🇹🇭 ภาษาไทย, 🇬🇧 English และ 🇯🇵 日本語 ตามต้องการ (ช่องไหนว่างจะใช้ค่า default)</li>
                    <li>กด <strong>💾 บันทึก Disclaimer</strong></li>
                    <li>เห็นข้อความ <strong>✅ บันทึกแล้ว</strong> → ค่าใหม่จะมีผลทันทีเมื่อ reload หน้า "ติดต่อเรา"</li>
                </ol>

                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>Disclaimer ถูกเก็บใน <code>cache/site-settings.json</code> ร่วมกับ Site Title หน้า "ติดต่อเรา" จะ patch ค่า translation ด้วย PHP ก่อน JavaScript โหลด ทำให้ข้อความเปลี่ยนตามภาษาที่ผู้ใช้เลือกได้</div>
                </div>
            </section>

            <!-- Contact Tab -->
            <section class="help-section" id="contact">
                <h2>✉️ Tab: Contact <span class="badge-admin">admin only</span></h2>
                <p>จัดการ <strong>ช่องทางติดต่อ</strong> ที่แสดงในหน้า "ติดต่อเรา" ของเว็บไซต์ เช่น Twitter/X, Line, Email ฯลฯ ข้อมูลเก็บใน SQLite ไม่ต้อง hardcode ใน code</p>

                <h3>ฟีเจอร์หลัก</h3>
                <ul>
                    <li>เพิ่ม / แก้ไข / ลบ ช่องทางติดต่อได้ไม่จำกัด</li>
                    <li>กำหนด <strong>ลำดับการแสดง</strong> ด้วยตัวเลข (น้อย = แสดงก่อน)</li>
                    <li>เปิด/ปิดการแสดงช่องทางได้ทีละรายการ (<strong>Active</strong> toggle)</li>
                    <li>ไม่ต้องรัน migration script — ตาราง <code>contact_channels</code> ถูกสร้างอัตโนมัติ</li>
                </ul>

                <h3>ฟิลด์ของช่องทางติดต่อ</h3>
                <table class="help-table">
                    <thead><tr><th>ฟิลด์</th><th>คำอธิบาย</th></tr></thead>
                    <tbody>
                        <tr><td>Icon (emoji)</td><td>emoji แสดงหน้าชื่อช่องทาง เช่น 💬 📧 📱</td></tr>
                        <tr><td>ชื่อช่องทาง <span style="color:red">*</span></td><td>ชื่อที่แสดงบนหน้าเว็บ เช่น "Twitter (X)", "Line Official"</td></tr>
                        <tr><td>รายละเอียด</td><td>คำอธิบายสั้นๆ เช่น "ติดตามข่าวสารและอัปเดต"</td></tr>
                        <tr><td>URL / Contact</td><td>ลิงก์ที่คลิกได้ เช่น https://x.com/... หรือ mailto:...</td></tr>
                        <tr><td>ลำดับการแสดง</td><td>ตัวเลข 0 ขึ้นไป ค่าน้อยจะแสดงก่อน</td></tr>
                        <tr><td>Active</td><td>เปิด/ปิดการแสดงผลในหน้า "ติดต่อเรา"</td></tr>
                    </tbody>
                </table>

                <h3>ขั้นตอนเพิ่มช่องทางติดต่อ</h3>
                <ol class="steps">
                    <li>คลิกแท็บ <strong>✉️ Contact</strong></li>
                    <li>กด <strong>➕ เพิ่มช่องทาง</strong></li>
                    <li>กรอกข้อมูลในฟอร์ม (ชื่อช่องทางเป็นข้อมูลบังคับ)</li>
                    <li>กด <strong>💾 บันทึก</strong></li>
                    <li>ช่องทางใหม่จะแสดงทันทีในหน้า "ติดต่อเรา"</li>
                </ol>

                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>ถ้าต้องการซ่อนช่องทางชั่วคราวโดยไม่ลบ ให้ untick <strong>Active</strong> ในฟอร์มแก้ไข ช่องทางนั้นจะไม่แสดงบนหน้าเว็บ public</div>
                </div>
            </section>

            <!-- Roles -->
            <section class="help-section" id="roles">
                <h2>🛡️ สิทธิ์ผู้ใช้ (Roles)</h2>
                <p>ระบบมี 2 roles:</p>
                <table class="help-table">
                    <thead><tr><th>ฟีเจอร์</th><th><span class="badge-admin">admin</span></th><th><span class="badge-agent">agent</span></th></tr></thead>
                    <tbody>
                        <tr><td>Programs (CRUD)</td><td>✅</td><td>✅</td></tr>
                        <tr><td>Events (CRUD)</td><td>✅</td><td>✅</td></tr>
                        <tr><td>Requests (ดู/อนุมัติ/ปฏิเสธ)</td><td>✅</td><td>✅</td></tr>
                        <tr><td>Credits (CRUD)</td><td>✅</td><td>✅</td></tr>
                        <tr><td>Import ICS</td><td>✅</td><td>✅</td></tr>
                        <tr><td>Users (CRUD)</td><td>✅</td><td>❌</td></tr>
                        <tr><td>Backup / Restore</td><td>✅</td><td>❌</td></tr>
                        <tr><td>Settings (Title + Theme + Disclaimer)</td><td>✅</td><td>❌</td></tr>
                        <tr><td>Contact Channels (CRUD)</td><td>✅</td><td>❌</td></tr>
                    </tbody>
                </table>
            </section>

            <!-- Tips & FAQ -->
            <section class="help-section" id="tips">
                <h2>💡 เคล็ดลับ &amp; FAQ</h2>

                <h3>Q: Cache ไม่อัปเดต ข้อมูลบนเว็บยังเก่าอยู่</h3>
                <p>แก้ไข <code>APP_VERSION</code> ใน <code>config/app.php</code> เป็นเลขใหม่ เช่น จาก <code>1.2.11</code> เป็น <code>1.2.12</code>
                เพื่อบังคับ browser โหลดไฟล์ JS/CSS ใหม่</p>
                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>หากใช้ Cloudflare ให้ Purge Cache เพิ่มเติมด้วย</div>
                </div>

                <h3>Q: เพิ่ม Program แล้วไม่เห็นบนหน้าเว็บ</h3>
                <ul>
                    <li>ตรวจสอบว่า <strong>Event ที่ Program สังกัด</strong> มีสถานะ <strong>Active</strong></li>
                    <li>ตรวจสอบว่าไม่ได้กรองวันที่ / เวทีที่ทำให้ Program ไม่แสดง</li>
                    <li>ลอง hard-refresh browser (<kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>R</kbd>)</li>
                </ul>

                <h3>Q: Import ICS แล้วไม่มีข้อมูล</h3>
                <ul>
                    <li>ตรวจสอบว่าไฟล์ .ics มี field <code>DTSTART</code>, <code>DTEND</code>, <code>SUMMARY</code> ครบ</li>
                    <li>ขนาดไฟล์ต้องไม่เกิน <strong>5MB</strong></li>
                    <li>ดู error log ใน browser console หรือ PHP error log</li>
                </ul>

                <h3>Q: เพิ่ม Program แล้ว Feed (webcal) ยังไม่อัปเดต</h3>
                <ul>
                    <li>Feed ใช้ static cache (TTL 1 ชั่วโมง) — ทุก write operation ใน Admin จะ invalidate cache ทันที แต่ปฏิทินแต่ละ app มีรอบ pull ของตัวเอง (Apple ~1 ชั่วโมง, Google ~24 ชั่วโมง)</li>
                    <li>หากต้องการ force refresh: ใน Apple Calendar กด "Refresh" ที่ calendar; ใน Outlook Desktop คลิก "Sync"; ใน Google Calendar ต้อง remove แล้ว subscribe ใหม่</li>
                </ul>

                <h3>Q: หน้าเว็บโหลดช้าหลัง import ข้อมูลจำนวนมาก</h3>
                <ul>
                    <li>ระบบมี Query Cache สำหรับหน้า Event (<code>cache/query_event_{id}.json</code>) และหน้า Artist Profile (<code>cache/query_artist_{id}.json</code>) — TTL 1 ชั่วโมง</li>
                    <li>ทุก write operation ใน Admin (เพิ่ม/แก้ไข/ลบ Program หรือ Artist) จะ invalidate cache ทันที; หลังจากนั้นการโหลดครั้งแรกจะ rebuild cache ใหม่ และ request ถัดไปจะเร็ว</li>
                    <li>ถ้าต้องการล้าง cache ด้วยตนเอง: ลบไฟล์ <code>cache/query_event_*.json</code> และ <code>cache/query_artist_*.json</code> ผ่าน server</li>
                </ul>

                <h3>Q: ต้องการกำหนด Program Type ตอน import ICS</h3>
                <ul>
                    <li>เพิ่ม <code>X-PROGRAM-TYPE:stage</code> ในแต่ละ VEVENT ของไฟล์ ICS เพื่อกำหนด type แยกรายการ</li>
                    <li>หรือพิมพ์ค่า type ในช่อง <strong>🏷️ Program Type (default)</strong> ก่อนอัปโหลดไฟล์ ใน Admin → Import</li>
                    <li>หรือใช้ argument <code>--type=value</code> เมื่อรัน CLI: <br><code>php tools/import-ics-to-sqlite.php --event=slug --type=stage</code></li>
                    <li>ค่าจาก <code>X-PROGRAM-TYPE:</code> ในไฟล์ ICS จะมีความสำคัญสูงกว่า default type เสมอ</li>
                </ul>

                <h3>Q: เปลี่ยน Site Title แล้วหน้าเว็บยังแสดงชื่อเก่า</h3>
                <ul>
                    <li>ตรวจสอบว่ากด <strong>💾 บันทึก Title</strong> แล้วเห็น "✅ บันทึกแล้ว"</li>
                    <li>Reload หน้าเว็บ <em>public</em> ด้วย <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>R</kbd></li>
                    <li>ตรวจสอบว่าไดเรกทอรี <code>cache/</code> มีสิทธิ์เขียน (writable)</li>
                </ul>

                <h3>Q: เปลี่ยน Theme แล้วหน้าเว็บยังไม่เปลี่ยนสี</h3>
                <ul>
                    <li>ตรวจสอบว่ากด <strong>💾 บันทึก Theme</strong> แล้วเห็น "✅ บันทึกแล้ว"</li>
                    <li>Reload หน้าเว็บ <em>public</em> (ไม่ใช่หน้า Admin) ด้วย <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>R</kbd></li>
                    <li>ตรวจสอบว่าไดเรกทอรี <code>cache/</code> มีสิทธิ์เขียน (writable)</li>
                    <li>หากเข้าผ่าน URL <code>/event/slug</code> และ Event นั้นมี theme ตั้งค่าไว้ theme ของ Event จะ <strong>override</strong> global theme เสมอ</li>
                </ul>

                <h3>Q: ต้องการให้แต่ละ Event มีสีต่างกัน</h3>
                <ol class="steps">
                    <li>ไปที่แท็บ <strong>🎪 Events</strong></li>
                    <li>คลิก <strong>✏️</strong> ที่ Event ที่ต้องการ</li>
                    <li>เลือก theme จากช่อง <strong>Theme</strong> (หรือเลือก "— ใช้ Global Theme —" เพื่อใช้ global)</li>
                    <li>กด <strong>💾 บันทึก</strong></li>
                </ol>

                <h3>Q: ต้องการ backup ฐานข้อมูลสำรองอัตโนมัติ</h3>
                <p>ตั้ง cron job เรียก endpoint backup_create หรือรัน script backup เองเป็นประจำ
                หรือสร้าง Backup ผ่านหน้า Admin ก่อนแก้ไขข้อมูลจำนวนมากทุกครั้ง</p>

                <h3>Q: ลืมรหัสผ่าน Admin</h3>
                <ol class="steps">
                    <li>รัน: <code>php tools/generate-password-hash.php yourNewPassword</code></li>
                    <li>อัปเดต hash ใน <code>config/admin.php</code> (สำหรับ fallback config user) หรืออัปเดตตรงใน database table <code>admin_users</code></li>
                </ol>

                <h3>Keyboard Shortcuts</h3>
                <table class="help-table">
                    <thead><tr><th>Shortcut</th><th>หน้าที่</th></tr></thead>
                    <tbody>
                        <tr><td><kbd>Enter</kbd> (ในช่องค้นหา)</td><td>ค้นหาทันที</td></tr>
                        <tr><td><kbd>Esc</kbd> (ใน Modal)</td><td>ปิด modal (คลิก × หรือพื้นที่นอก modal)</td></tr>
                        <tr><td><kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>R</kbd></td><td>Hard refresh browser (ล้าง cache)</td></tr>
                    </tbody>
                </table>
            </section>

            <!-- Footer note -->
            <div style="text-align:center;padding:20px 0;color:var(--admin-text-light);font-size:.9rem;">
                Idol Stage Timetable v<?php echo htmlspecialchars(APP_VERSION); ?> &nbsp;|&nbsp;
                <a href="index.php" style="color:var(--admin-primary);">← กลับ Admin Dashboard</a>
            </div>

        </main>
    </div>
</div>
<script>
// Mobile TOC: ปิด dropdown เมื่อคลิกลิงก์ใดๆ ในเมนู
document.querySelectorAll('.mobile-toc-menu a').forEach(function(link) {
    link.addEventListener('click', function() {
        var toc = document.getElementById('mobileToc');
        if (toc) toc.classList.remove('open');
    });
});
</script>
</body>
</html>
