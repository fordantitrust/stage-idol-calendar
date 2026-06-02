<?php
/**
 * Admin Help Page - English Version
 */
require_once __DIR__ . '/../config.php';
send_security_headers();

require_allowed_ip();
require_login();

$adminUsername = $_SESSION['admin_display_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
$adminRole = get_admin_role();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Help (EN) - <?php echo htmlspecialchars(get_site_title()); ?></title>
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
            .mobile-toc { display: block; }
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
        .badge-organizer { display: inline-block; background: #059669; color: #fff; font-size: .72rem; padding: 2px 8px; border-radius: 10px; vertical-align: middle; margin-left: 6px; }
        body.role-agent .admin-only,
        body.role-agent .admin-organizer-only,
        body.role-agent .organizer-only,
        body.role-organizer .admin-only,
        body.role-organizer .admin-agent-only,
        body.role-organizer .non-organizer-only,
        body.role-admin .organizer-only { display: none !important; }

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
            display: none;
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
        .mobile-toc-menu { display: none; flex-direction: column; }
        .mobile-toc.open .mobile-toc-menu { display: flex; }
        .mobile-toc-menu a {
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

        @media (max-width: 768px) {
            .admin-container { padding: 10px; }
            .admin-header { padding: 14px; gap: 10px; }
            .admin-header h1 { font-size: 1.25rem; }
            .admin-header a { padding: 8px 10px; font-size: 0.85rem; }
        }

        @media (max-width: 600px) {
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

        @media (max-width: 480px) {
            .admin-container { padding: 6px; }
            .admin-header { border-radius: 8px; padding: 12px; }
            .admin-header h1 { font-size: 1.05rem; }
            .help-section { padding: 16px; border-radius: 8px; margin-bottom: 16px; }
            .help-section h2 { font-size: 1.15rem; margin-bottom: 14px; }
            .help-section h3 { font-size: 0.95rem; }
            .steps li { padding: 10px 12px 10px 46px; }
            .steps li::before { left: 12px; width: 22px; height: 22px; font-size: 0.75rem; }
            .tab-chip { font-size: 0.8rem; padding: 3px 10px; }
            .callout { padding: 10px 12px; }
            code { font-size: 0.8em; word-break: break-all; }
        }
    </style>
</head>
<body class="role-<?php echo htmlspecialchars($adminRole, ENT_QUOTES, 'UTF-8'); ?>">
<div class="admin-container">

    <!-- Header -->
    <div class="admin-header">
        <h1>📖 Admin User Guide</h1>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <span style="color:rgba(255,255,255,.9);font-weight:600;">Hello, <?php echo htmlspecialchars($adminUsername); ?>
                <small style="opacity:.7;font-weight:400;">(<?php echo htmlspecialchars($adminRole); ?>)</small>
            </span>
            <div class="lang-switcher">
                <a href="help.php">TH</a>
                <a href="help-en.php" class="active">EN</a>
            </div>
            <a href="index.php">← Back to Admin</a>
            <a href="../index.php">← Home</a>
        </div>
    </div>

    <!-- Mobile TOC dropdown -->
    <div class="mobile-toc" id="mobileToc">
        <button class="mobile-toc-btn" onclick="this.parentElement.classList.toggle('open')" aria-expanded="false" aria-controls="mobileTocMenu">
            📑 Table of Contents <span class="mobile-toc-arrow">▼</span>
        </button>
        <div class="mobile-toc-menu" id="mobileTocMenu">
            <a href="#overview">System Overview</a>
            <a href="#login">Login &amp; Security</a>
            <a href="#login-2fa">↳ 2FA / TOTP</a>
            <a href="#header">Header &amp; Account</a>
            <a href="#programs">Tab: Programs</a>
            <a href="#events">Tab: Events</a>
            <a href="#events-gallery">↳ Event Pictures Gallery</a>
            <a href="#requests" class="non-organizer-only">Tab: Requests</a>
            <a href="#credits">Tab: Credits</a>
            <a href="#import" class="non-organizer-only">Tab: Import</a>
            <a href="#import-type" class="non-organizer-only">↳ Program Type</a>
            <a href="#artists" class="admin-organizer-only">Tab: Artists</a>
            <a href="#feed">Feed / Subscribe</a>
            <a href="#telegram" class="admin-only">Telegram Notifications</a>
            <a href="#webpush" class="admin-only">Web Push (PWA)</a>
            <a href="#audit-log" class="admin-only">Admin Audit Log</a>
            <a href="#users" class="admin-only">Tab: Users</a>
            <a href="#backup" class="admin-only">Tab: Backup</a>
            <a href="#settings" class="admin-only">Tab: Settings</a>
            <a href="#contact" class="admin-only">Tab: Contact</a>
            <a href="#roles">User Roles</a>
            <a href="#tips">Tips &amp; FAQ</a>
        </div>
    </div>

    <div class="help-layout">

        <!-- Table of Contents -->
        <nav class="toc">
            <h3>📑 Contents</h3>
            <ul>
                <li><a href="#overview">System Overview</a></li>
                <li><a href="#login">Login &amp; Security</a></li>
                <li><a href="#login-2fa">2FA / TOTP</a></li>
                <li><a href="#header">Header &amp; Account</a></li>
                <li><a href="#programs">Tab: Programs</a>
                    <ul class="toc-sub">
                        <li><a href="#prog-search">Search &amp; Filter</a></li>
                        <li><a href="#prog-add">Add Program</a></li>
                        <li><a href="#prog-edit">Edit / Delete</a></li>
                        <li><a href="#prog-bulk">Bulk Actions</a></li>
                        <li><a href="#prog-sort">Sorting</a></li>
                    </ul>
                </li>
                <li><a href="#events">Tab: Events</a>
                    <ul class="toc-sub">
                        <li><a href="#events-gallery">Event Pictures Gallery</a></li>
                        <li><a href="#events-timezone">Timezone</a></li>
                    </ul>
                </li>
                <li class="non-organizer-only"><a href="#requests">Tab: Requests</a></li>
                <li><a href="#credits">Tab: Credits</a></li>
                <li class="non-organizer-only"><a href="#import">Tab: Import</a>
                    <ul class="toc-sub">
                        <li><a href="#import-type">Program Type</a></li>
                    </ul>
                </li>
                <li class="admin-organizer-only"><a href="#artists">Tab: Artists</a></li>
                <li><a href="#feed">Feed / Subscribe</a>
                    <ul class="toc-sub">
                        <li><a href="#feed-event">Event Feed</a></li>
                        <li><a href="#feed-artist">Artist Feed</a></li>
                    </ul>
                </li>
                <li class="admin-only"><a href="#telegram">Telegram Notifications</a></li>
                <li class="admin-only"><a href="#webpush">Web Push (PWA)</a></li>
                <li class="admin-only"><a href="#audit-log">Admin Audit Log</a></li>
                <li class="admin-only"><a href="#users">Tab: Users</a></li>
                <li class="admin-only"><a href="#backup">Tab: Backup</a></li>
                <li class="admin-only"><a href="#settings">Tab: Settings</a></li>
                <li class="admin-only"><a href="#contact">Tab: Contact</a></li>
                <li><a href="#roles">User Roles</a></li>
                <li><a href="#tips">Tips &amp; FAQ</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="help-content">

            <!-- Overview -->
            <section class="help-section" id="overview">
                <h2>🌸 System Overview</h2>
                <p>
                    The <strong>Idol Stage Timetable</strong> Admin Panel is used to manage all data displayed
                    on the website — Programs (individual performances), Events (conventions/shows),
                    user-submitted Requests, Credits, and database Backups.
                </p>
                <p>The Admin Panel shows tabs according to the logged-in role (updated through <strong>v12.3.3</strong>):</p>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;">
                    <span class="tab-chip">🎵 Programs</span>
                    <span class="tab-chip">🎪 Events</span>
                    <span class="tab-chip non-organizer-only">📝 Requests</span>
                    <span class="tab-chip">✨ Credits</span>
                    <span class="tab-chip non-organizer-only">📤 Import</span>
                    <span class="tab-chip admin-organizer-only">🎤 Artists <span class="badge-organizer">organizer request</span></span>
                    <span class="tab-chip admin-only">👤 Users <span class="badge-admin">admin</span></span>
                    <span class="tab-chip admin-only">💾 Backup <span class="badge-admin">admin</span></span>
                    <span class="tab-chip admin-only">⚙️ Settings <span class="badge-admin">admin</span></span>
                    <span class="tab-chip admin-only">✉️ Contact <span class="badge-admin">admin</span></span>
                </div>
                <div class="callout callout-info" style="margin-top:16px;">
                    <span class="callout-icon">ℹ️</span>
                    <div>This page automatically hides topics that the current role cannot use: admins see everything, agents see operational and review tools, and organizers see only their own Events / Programs / Credits / Artist request workflow.</div>
                </div>
            </section>

            <!-- Login -->
            <section class="help-section" id="login">
                <h2>🔐 Login &amp; Security</h2>
                <p>Access the Admin panel at <code>/admin/login</code> or <code>/admin/login.php</code></p>

                <h3>Login Steps</h3>
                <ol class="steps">
                    <li>Enter your <strong>Username</strong> and <strong>Password</strong></li>
                    <li>Click <strong>Login</strong></li>
                    <li>If 2FA is enabled for your account, enter the 6-digit Authenticator code or a recovery code</li>
                    <li>You will be redirected to the Admin Dashboard</li>
                </ol>

                <h3 id="login-2fa">🔐 2FA / TOTP <span class="badge-version">v10.0.0</span></h3>
                <p>The system supports RFC 6238 TOTP 2FA for database-managed users in <code>admin_users</code>. Fallback users from <code>config/admin.php</code> remain password-only for backward compatibility.</p>
                <table class="help-table">
                    <thead><tr><th>Topic</th><th>Details</th></tr></thead>
                    <tbody>
                        <tr><td>Code format</td><td>6-digit codes that rotate every 30 seconds; compatible with Google Authenticator, Microsoft Authenticator, 1Password, Authy, and similar apps</td></tr>
                        <tr><td>Recovery</td><td>One-time backup codes are generated when enabling 2FA and can be regenerated later</td></tr>
                        <tr><td>Replay protection</td><td>A TOTP code already accepted for the same time step is rejected</td></tr>
                        <tr><td>Migration <span class="badge-version">v10.1.0</span></td><td>Add the 2FA columns through <code>setup.php</code> or <code>php tools/migrate-add-admin-2fa-columns.php</code>; the API does not auto-migrate and uses <code>data/.admin_2fa_columns_ready</code> after confirming the schema</td></tr>
                        <tr><td>Rate limit</td><td>Wrong 2FA or recovery codes count toward the login rate limit</td></tr>
                    </tbody>
                </table>

                <h3>Security Restrictions</h3>
                <table class="help-table">
                    <thead><tr><th>Rule</th><th>Details</th></tr></thead>
                    <tbody>
                        <tr><td>Rate Limiting</td><td>Maximum <strong>5 failed login attempts per 15 minutes</strong> per IP. Exceeding this results in a temporary block.</td></tr>
                        <tr><td>Session Timeout</td><td>Sessions expire after <strong>2 hours</strong> of inactivity — you will be logged out automatically.</td></tr>
                        <tr><td>IP Whitelist</td><td>If enabled, only IP addresses listed in <code>config/admin.php</code> can access the admin panel.</td></tr>
                    </tbody>
                </table>

                <div class="callout callout-warn">
                    <span class="callout-icon">⚠️</span>
                    <div>If you are blocked due to too many failed attempts, wait 15 minutes or ask a server admin to clear <code>cache/login_attempts.json</code>.</div>
                </div>
            </section>

            <!-- Header -->
            <section class="help-section" id="header">
                <h2>⚙️ Header &amp; Account Settings</h2>
                <p>The blue header bar at the top of every admin page contains:</p>
                <table class="help-table">
                    <thead><tr><th>Element</th><th>Function</th></tr></thead>
                    <tbody>
                        <tr><td>Username &amp; Role</td><td>Shows the currently logged-in user and their role (admin / agent / organizer)</td></tr>
                        <tr><td>Version Badge</td><td>Displays the current app version, e.g. <code>v<?php echo htmlspecialchars(APP_VERSION); ?></code> (from the APP_VERSION constant) — useful for checking the version while using Admin</td></tr>
                        <tr><td>🔑 Change Password</td><td>Change your own password and manage 2FA (only shown for database-managed users)</td></tr>
                        <tr><td>📖 Help</td><td>Opens this help page</td></tr>
                        <tr><td>← Home</td><td>Return to the public-facing website</td></tr>
                        <tr><td>Logout</td><td>End your session and return to the login page</td></tr>
                    </tbody>
                </table>

                <h3>Changing Your Password</h3>
                <ol class="steps">
                    <li>Click <strong>🔑 Change Password</strong> in the header</li>
                    <li>Enter your <em>Current Password</em></li>
                    <li>Enter a <em>New Password</em> (minimum 8 characters)</li>
                    <li>Re-enter the new password in <em>Confirm New Password</em></li>
                    <li>Click <strong>Change Password</strong></li>
                </ol>

                <h3>Enabling 2FA <span class="badge-version">v10.0.0</span></h3>
                <ol class="steps">
                    <li>Click <strong>🔑 Change Password</strong> in the header</li>
                    <li>In <strong>Two-Factor Authentication</strong>, click <strong>Enable 2FA</strong></li>
                    <li>Scan the QR code or copy the manual key into your Authenticator app</li>
                    <li>Enter the 6-digit code to confirm, then save the backup codes shown by the system</li>
                </ol>
                <div class="callout callout-warn">
                    <span class="callout-icon">⚠️</span>
                    <div>Backup codes are shown once. If you lose your Authenticator device, use a backup code or ask another admin to reset 2FA from the Users tab.</div>
                </div>
            </section>

            <!-- Programs Tab -->
            <section class="help-section" id="programs">
                <h2>📋 Tab: Programs</h2>
                <p>
                    <strong>Programs</strong> are individual performance slots or activities within an event —
                    for example, a specific artist's stage time. This is the most frequently used tab.
                </p>

                <h3 id="prog-search">🔍 Search &amp; Filter</h3>
                <table class="help-table">
                    <thead><tr><th>Filter</th><th>Function</th></tr></thead>
                    <tbody>
                        <tr><td>Event Selector</td><td>Filter programs by event. Select "All Events" to show everything.</td></tr>
                        <tr><td>Search box</td><td>Search by program title, organizer, or description (press Enter or wait 500 ms)</td></tr>
                        <tr><td>✕ (clear button)</td><td>Clear the current search query</td></tr>
                        <tr><td>Venue Filter</td><td>Filter by venue name</td></tr>
                        <tr><td>Date From / Date To</td><td>Filter by date range</td></tr>
                        <tr><td>Clear Filters</td><td>Reset all active filters</td></tr>
                        <tr><td>N / page</td><td>Set items per page: 20, 50, or 100</td></tr>
                    </tbody>
                </table>

                <h3 id="prog-add">➕ Add a New Program</h3>
                <ol class="steps">
                    <li>Click <strong>+ Add Program</strong> (top right)</li>
                    <li>Fill in the form:</li>
                </ol>
                <table class="help-table">
                    <thead><tr><th>Field</th><th>Notes</th></tr></thead>
                    <tbody>
                        <tr><td>Event <span style="color:#999">(optional)</span></td><td>The event this program belongs to</td></tr>
                        <tr><td>Program Title <span style="color:red">*</span></td><td>Name of the performance / activity (required)</td></tr>
                        <tr><td>Organizer</td><td>Artist name or organizer</td></tr>
                        <tr><td>Venue</td><td>Type a venue name or choose from the autocomplete dropdown</td></tr>
                        <tr><td>Date <span style="color:red">*</span></td><td>Date of the performance</td></tr>
                        <tr><td>Start Time / End Time <span style="color:red">*</span></td><td>Time in HH:MM format</td></tr>
                        <tr><td>Description</td><td>Optional additional details</td></tr>
                        <tr><td>Artist / Group</td><td>Artists associated with this program — autocomplete pulls from the Artists table (🎤 = solo, 🎵 = group); admin/agent users may type a new name to create an artist automatically on save, while organizers must select an existing artist from autocomplete</td></tr>
                        <tr><td>Program Type</td><td>Type of program, e.g. <code>stage</code>, <code>booth</code>, <code>meet &amp; greet</code> (optional, supports autocomplete from existing types)</td></tr>
                        <tr><td>Live Stream URL</td><td>URL of the live stream (YouTube, X/Twitter, TikTok, etc.) — must begin with <code>https://</code>; any other value is silently ignored; once set, the public page displays a platform icon and a <strong>🔴 Join Live</strong> button; the ICS feed includes a <code>URL:</code> property for calendar apps</td></tr>
                    </tbody>
                </table>
                <ol class="steps" start="3">
                    <li>Click <strong>Save</strong></li>
                </ol>

                <h3 id="prog-edit">✏️ Edit &amp; 🗑️ Delete a Program</h3>
                <ul>
                    <li><strong>Edit</strong>: Click the <strong>✏️</strong> button in the Actions column → update the fields → click <em>Save</em></li>
                    <li><strong>Delete</strong>: Click the <strong>🗑️</strong> button → confirm in the popup → the record is permanently removed</li>
                </ul>
                <div class="callout callout-danger">
                    <span class="callout-icon">🚫</span>
                    <div>Deleting a Program is <strong>irreversible</strong>. Create a Backup before bulk-deleting data.</div>
                </div>

                <h3 id="prog-bulk">📦 Bulk Actions</h3>
                <p>Select multiple programs at once to edit or delete them in a single operation:</p>
                <ol class="steps">
                    <li>Tick the <strong>checkboxes</strong> next to the programs you want (or tick the header checkbox to select the whole page)</li>
                    <li>The yellow <strong>Bulk Actions bar</strong> will appear above the table</li>
                    <li>Choose an action:</li>
                </ol>
                <table class="help-table">
                    <thead><tr><th>Button</th><th>Function</th></tr></thead>
                    <tbody>
                        <tr><td>Select All</td><td>Select all programs on the current page</td></tr>
                        <tr><td>Deselect All</td><td>Clear all selections</td></tr>
                        <tr><td>✏️ Bulk Edit</td><td>Update Venue / Organizer / Artist&ndash;Group / Program Type for all selected programs at once (up to 100) — the Artist / Group field uses the same tag-input widget as the single-edit form</td></tr>
                        <tr><td>🗑️ Bulk Delete</td><td>Delete all selected programs at once (up to 100)</td></tr>
                    </tbody>
                </table>
                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>In Bulk Edit, fields left <strong>blank are not changed</strong> — only fill in the fields you want to update.</div>
                </div>

                <h3 id="prog-sort">↕️ Sorting</h3>
                <p>Click any column header marked with ↕ to sort:</p>
                <ul>
                    <li>First click → sort <strong>ascending</strong> (↑)</li>
                    <li>Second click → sort <strong>descending</strong> (↓)</li>
                </ul>
                <p>Sortable columns: <code>#</code>, <code>Title</code>, <code>Date/Time</code>, <code>Venue</code>, <code>Organizer</code></p>
            </section>

            <!-- Events Tab -->
            <section class="help-section" id="events">
                <h2>🎪 Tab: Events</h2>
                <p>
                    <strong>Events</strong> (formerly called Conventions) are the top-level event containers,
                    such as "Idol Stage Feb 2026". Programs are assigned to and grouped under Events.
                </p>

                <h3>Event Fields</h3>
                <table class="help-table">
                    <thead><tr><th>Field</th><th>Notes</th></tr></thead>
                    <tbody>
                        <tr><td>Name <span style="color:red">*</span></td><td>Full event name, e.g. "Idol Stage February 2026"</td></tr>
                        <tr><td>Slug <span style="color:red">*</span></td><td>URL-friendly short name, e.g. <code>idol-stage-feb-2026</code> (lowercase, numbers, hyphens only)</td></tr>
                        <tr><td>Description</td><td>Optional event description</td></tr>
                        <tr><td>Start Date / End Date</td><td>The event's opening and closing dates</td></tr>
                        <tr><td>Venue Mode</td><td><strong>multi</strong> = multiple venues (shows venue filter, Gantt view) | <strong>single</strong> = single venue | <strong>calendar</strong> = monthly calendar</td></tr>
                        <tr><td>Theme</td><td>Color theme specific to this event (if not set, falls back to the global theme from Settings)</td></tr>
                        <tr><td>Gallery Layout</td><td>Layout for the event's photo gallery: <strong>grid3</strong> (3 columns, default) | <strong>grid2</strong> | <strong>grid1</strong> | <strong>masonry</strong></td></tr>
                        <tr><td>Cover Image (Hero) <span class="badge-version">v8.0.0</span></td><td>Hero-ratio cover image at 16:9 (recommended 1600×900 px) — displayed in the Hero Carousel on the homepage; Cropper.js is used to crop before upload</td></tr>
                        <tr><td>Cover Image (Card) <span class="badge-version">v8.0.0</span></td><td>Card-ratio cover image at 4:3 (recommended 800×600 px) — displayed in event cards on the listing page; Fallback chain: Cover Image (Hero) → event pictures → gradient</td></tr>
                        <tr><td>Header Cover Image <span class="badge-version">v9.2.0</span></td><td>Banner image at 4:1 ratio (recommended 1920×480 px) — displayed as the header background on that event's pages; takes priority over the site-wide Header Cover</td></tr>
                        <tr><td>Active</td><td>Toggle visibility of this event on the public website</td></tr>
                    </tbody>
                </table>

                <h3 id="events-cover">🖼️ Cover Images <span class="badge-version">v8.0.0</span></h3>
                <p>Each Event has two separate cover images (Hero + Card) and one Header Cover, all independent:</p>
                <table class="help-table">
                    <thead><tr><th>Type</th><th>Ratio</th><th>Recommended Size</th><th>Used In</th></tr></thead>
                    <tbody>
                        <tr><td>Cover Image (Hero)</td><td>16:9</td><td>1600×900 px</td><td>Hero Carousel on homepage; Social OG image</td></tr>
                        <tr><td>Cover Image (Card)</td><td>4:3</td><td>800×600 px</td><td>Event cards on the listing page</td></tr>
                        <tr><td>Header Cover Image</td><td>4:1</td><td>1920×480 px</td><td>Header background on that event's pages</td></tr>
                    </tbody>
                </table>
                <h4>How to Upload a Cover Image</h4>
                <ol>
                    <li>Admin → <strong>Events</strong> tab → click ✏️ to edit an event</li>
                    <li>Scroll down to the <strong>Cover Images</strong> section</li>
                    <li>Click <strong>📸 Upload Hero</strong>, <strong>📸 Upload Card</strong>, or <strong>📸 Upload Header</strong></li>
                    <li>Select a file — a Cropper.js window opens with the correct aspect ratio frame</li>
                    <li>Adjust the crop area → click <strong>✅ Crop &amp; Upload</strong></li>
                    <li>The new image is shown in preview immediately; click 🗑️ to remove an existing image</li>
                </ol>
                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>The CSRF token is sent via the <code>X-CSRF-Token</code> HTTP header automatically — no extra steps needed.</div>
                </div>

                <h3>Venue Mode: Calendar</h3>
                <p>When set to <strong>calendar</strong>, the event page displays a monthly calendar instead of a list or timeline:</p>
                <ul>
                    <li><strong>Desktop</strong> — each day shows chips (platform icon + artist + time); tap a chip to open a detail modal</li>
                    <li><strong>Mobile</strong> — each day shows dot indicators; tap a day to open a panel listing all programs for that day</li>
                    <li>◀ ▶ buttons navigate only between months that have programs (hidden automatically if only one month)</li>
                    <li>Best suited for events with programs spread across many days, such as streaming schedules</li>
                </ul>
                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>Calendar mode automatically hides the List/Timeline toggle and is compatible with all color themes.</div>
                </div>

                <h3>Sorting</h3>
                <p>Click any column header to sort — click again to toggle ↑ / ↓. The ↕ icon means unsorted.</p>
                <table class="help-table">
                    <thead><tr><th>Column</th><th>Notes</th></tr></thead>
                    <tbody>
                        <tr><td>#</td><td>Sort by ID</td></tr>
                        <tr><td>Name</td><td>Sort by event name (A→Z / Z→A)</td></tr>
                        <tr><td>Start Date</td><td>Sort by event start date (default: newest first)</td></tr>
                        <tr><td>End Date</td><td>Sort by event end date</td></tr>
                        <tr><td>Active</td><td>Sort by Active / Inactive status</td></tr>
                        <tr><td>Programs</td><td>Sort by number of programs in the event</td></tr>
                    </tbody>
                </table>

                <h3>Accessing Events via URL</h3>
                <p>Each event can be accessed directly via: <code>/event/{slug}</code></p>
                <p>Example: <code>/event/idol-stage-feb-2026</code></p>

                <div class="callout callout-warn">
                    <span class="callout-icon">⚠️</span>
                    <div>Deleting an Event does <strong>not</strong> delete its Programs — they simply lose their event reference. Move or delete the Programs first.</div>
                </div>

                <h3>The Default Event and the Events Listing Page</h3>
                <p>
                    When the database is initialized, the system automatically creates a <strong>Default Event</strong>
                    whose slug matches the <code>DEFAULT_EVENT_SLUG</code> value in <code>config/app.php</code> (default: <code>default</code>).
                </p>
                <table class="help-table">
                    <thead><tr><th>Situation</th><th>What happens at <code>/</code></th></tr></thead>
                    <tbody>
                        <tr><td>Only the Default Event exists (no real events created yet)</td><td>The homepage shows the <strong>calendar view</strong> of the default event directly — the events listing is not shown</td></tr>
                        <tr><td>At least one real Event exists (slug ≠ default)</td><td>The homepage shows the <strong>Events listing</strong> (event cards) — the default event is hidden from this listing</td></tr>
                        <tr><td>Visiting <code>/event/default</code> directly</td><td>Always shows the calendar view of the default event</td></tr>
                    </tbody>
                </table>
                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>
                        <strong>The Default Event is intentionally hidden from the Events listing page.</strong>
                        It acts as a fallback container for Programs imported without an explicit event assignment.
                        To have an event appear in the listing, create a new Event with a different slug and import Programs into that event instead.
                    </div>
                </div>

                <h3 id="events-gallery">🖼️ Event Pictures Gallery <span class="badge-version">v7.0.0</span></h3>
                <p>
                    Each Event can have a <strong>photo gallery</strong> displayed below the event details on the public page.
                    Visitors can click any photo to open a lightbox with ◀ ▶ navigation and caption display.
                </p>

                <h4>Managing Photos</h4>
                <p>The <strong>📸 Event Pictures</strong> section appears inside the Edit Event modal (not visible when creating a new event):</p>
                <ol>
                    <li>Admin → Tab <strong>Events</strong> → click ✏️ to edit the event.</li>
                    <li>Scroll down to the <strong>📸 Event Pictures</strong> section.</li>
                    <li>Click <strong>+ Add Picture</strong> → select files (JPG, PNG, GIF, WEBP; max 5 MB per file; multiple files can be selected at once).</li>
                    <li>Photos upload and appear in the thumbnail grid immediately. Click <strong>×</strong> on any thumbnail to delete it.</li>
                </ol>
                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>Every photo is automatically resized by PHP GD using <strong>scale-to-fit</strong> mode (max 1200×900 px, no upscaling, no cropping) and saved as JPEG 85%. Portrait, landscape, and square originals are all displayed at their <strong>natural aspect ratio</strong> — nothing is stretched or cropped.</div>
                </div>

                <h4>Gallery Layout</h4>
                <p>Select the layout from the <strong>Gallery Layout</strong> dropdown in the Event form — this controls the public gallery only (Admin thumbnails are always square):</p>
                <table class="help-table">
                    <thead><tr><th>Value</th><th>Layout</th><th>Responsive (≤768px)</th><th>Responsive (≤480px)</th></tr></thead>
                    <tbody>
                        <tr><td><code>grid3</code> (default)</td><td>3 equal columns</td><td>2 columns</td><td>1 column</td></tr>
                        <tr><td><code>grid2</code></td><td>2 equal columns</td><td>2 columns</td><td>1 column</td></tr>
                        <tr><td><code>grid1</code></td><td>1 full-width column</td><td>1 column</td><td>1 column</td></tr>
                        <tr><td><code>masonry</code></td><td>CSS column-count 3 (portrait photos flow down naturally)</td><td>2 columns</td><td>1 column</td></tr>
                    </tbody>
                </table>
                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>Use <strong>masonry</strong> when mixing portrait (tall) and landscape photos — images flow into columns naturally without leaving blank gaps between rows.</div>
                </div>

                <h3 id="events-timezone">🌐 Per-Event Timezone <span class="badge-version">v4.0.0</span></h3>
                <p>
                    Each event can have its own <strong>Timezone</strong> — for example, a Japan event uses <code>Asia/Tokyo</code>
                    while a Thailand event uses <code>Asia/Bangkok</code>. This setting affects three areas:
                </p>
                <table class="help-table">
                    <thead><tr><th>Area</th><th>Behavior</th></tr></thead>
                    <tbody>
                        <tr>
                            <td>ICS Export / Feed</td>
                            <td>
                                Switches from UTC format (<code>DTSTART:...Z</code>) to TZID format:<br>
                                <code>DTSTART;TZID=Asia/Tokyo:20260319T100000</code><br>
                                A <code>VTIMEZONE</code> block (RFC 5545) is included — Apple Calendar, Google Calendar, and Outlook all display the correct local time.
                            </td>
                        </tr>
                        <tr>
                            <td>Event Page (public)</td>
                            <td>
                                A badge <strong>🕐 Asia/Tokyo</strong> is shown below the event name.<br>
                                If the visitor's browser is in a different timezone, a <em>(HH:MM local)</em> annotation is automatically appended after each program's time.
                            </td>
                        </tr>
                        <tr>
                            <td>Image Export (PNG)</td>
                            <td>The image footer shows the event's timezone alongside the generated timestamp.</td>
                        </tr>
                    </tbody>
                </table>

                <h3>How to Set the Timezone</h3>
                <ol>
                    <li>Go to Admin → <strong>Events</strong> tab → click <strong>+ Add Event</strong> or ✏️ to edit an existing event.</li>
                    <li>Select the <strong>Timezone</strong> from the dropdown (options are grouped by region).</li>
                    <li>Click <strong>Save</strong>.</li>
                </ol>
                <table class="help-table">
                    <thead><tr><th>Timezone option</th><th>Typical use</th></tr></thead>
                    <tbody>
                        <tr><td><code>Asia/Bangkok</code> (UTC+7)</td><td>Events in Thailand, Vietnam, Indonesia (WIB)</td></tr>
                        <tr><td><code>Asia/Tokyo</code> (UTC+9)</td><td>Events in Japan</td></tr>
                        <tr><td><code>Asia/Seoul</code> (UTC+9)</td><td>Events in South Korea</td></tr>
                        <tr><td><code>Asia/Singapore</code> (UTC+8)</td><td>Events in Singapore, Malaysia, Philippines</td></tr>
                        <tr><td><code>America/Los_Angeles</code> (UTC-8/-7)</td><td>Events in California</td></tr>
                        <tr><td><code>America/New_York</code> (UTC-5/-4)</td><td>Events in New York</td></tr>
                        <tr><td><code>Europe/London</code> (UTC+0/+1)</td><td>Events in London</td></tr>
                        <tr><td>… and 9 more timezones</td><td>See the form dropdown for the full list</td></tr>
                    </tbody>
                </table>

                <h3>How to Verify the Timezone Feature</h3>

                <h4>1. Test ICS Export</h4>
                <ol>
                    <li>Create a new Event, set Timezone = <code>Asia/Tokyo</code>, add a Program with a time such as <code>10:00 – 11:00</code>.</li>
                    <li>Open <code>/export?event={slug}</code> or click the Export button on the event page.</li>
                    <li>Download the ICS file and open it in a text editor. Check for:
                        <ul>
                            <li><code>BEGIN:VTIMEZONE</code> … <code>TZID:Asia/Tokyo</code> before the first <code>BEGIN:VEVENT</code></li>
                            <li><code>DTSTART;TZID=Asia/Tokyo:20260319T100000</code> — no trailing <code>Z</code></li>
                            <li><code>X-WR-TIMEZONE:Asia/Tokyo</code></li>
                        </ul>
                    </li>
                    <li>Import into Google Calendar or Apple Calendar → the event must show <strong>10:00 JST</strong>, not 10:00 Bangkok time.</li>
                </ol>

                <h4>2. Test the Live Feed</h4>
                <ol>
                    <li>Open <code>/event/{slug}/feed</code> and inspect the source.</li>
                    <li>Apply the same checks as ICS Export above.</li>
                    <li>Subscribe via Google Calendar → verify times are correct.</li>
                </ol>

                <h4>3. Test the Timezone Badge on the Event Page</h4>
                <ol>
                    <li>Open the event page in your browser.</li>
                    <li>Below the event name you should see the badge <strong>🕐 Asia/Tokyo</strong>.</li>
                    <li>Open DevTools → More Tools → Sensors → set Location/Timezone to <code>America/New_York</code> → reload.</li>
                    <li>Each program's time should now have a <em>(HH:MM local)</em> annotation.
                        <br><small>Example: 10:00 JST = 20:00 previous day EST (UTC-5).</small></li>
                </ol>

                <h4>4. Run Automated Tests (CLI)</h4>
                <pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;font-size:0.82rem;overflow-x:auto;">php tests/run-tests.php TimezoneTest</pre>
                <p>Expected result: <strong>67 tests PASSED</strong> — covering helper functions, UTC computation, ICS format, DB schema, admin API, translations, and JS.</p>

                <div class="callout callout-warn">
                    <span class="callout-icon">⚠️</span>
                    <div>
                        <strong>Important — time values stored in the database</strong><br>
                        Times entered in the Admin form (e.g. <code>10:00:00</code>) are interpreted as local time in the event's configured timezone.
                        If you change an event's Timezone after programs have already been entered, the displayed and exported times will shift accordingly.
                        Always set the Timezone <em>before</em> entering Program data.
                    </div>
                </div>
            </section>

            <!-- Requests Tab -->
            <section class="help-section non-organizer-only" id="requests">
                <h2>📝 Tab: Requests</h2>
                <p>
                    <strong>Requests</strong> are submissions from public users and organizers. They are split into four groups:
                </p>
                <ul>
                    <li><strong>📝 Program Requests</strong> — ask to add a new Program or modify an existing one</li>
                    <li><strong>🗓️ Event Requests</strong> — ask to add a new Event or modify an existing one <span class="badge-version">v9.3.0</span></li>
                    <li><strong>🟡 Event Active Requests</strong> — organizers request activation for assigned events <span class="badge-version">v12.1.0</span></li>
                    <li><strong>🎤 Artist Request</strong> — organizers request a new artist for admin/agent review <span class="badge-version">v12.3.0</span></li>
                </ul>

                <h3>Sub-tabs in Requests</h3>
                <p>The Requests tab has four sub-tabs toggled by the buttons at the top of the section:</p>
                <table class="help-table">
                    <thead><tr><th>Sub-tab</th><th>Content</th></tr></thead>
                    <tbody>
                        <tr><td>📝 <strong>Program Requests</strong></td><td>Requests to add / modify Programs; Approve → auto-creates or updates the Program</td></tr>
                        <tr><td>🗓️ <strong>Event Requests (Guest)</strong></td><td>Requests to add / modify Events from public users; Approve → auto-creates an inactive Event or updates event data</td></tr>
                        <tr><td>🟡 <strong>Event Active Requests (Organizer)</strong></td><td>Activation requests from organizers; Approve → sets <code>is_active=1</code> on the existing event</td></tr>
                        <tr><td>🎤 <strong>Artist Request</strong></td><td>New artist requests from organizers; Approve → creates an <code>artists</code> record, refreshes the Artist list, and shows the created <code>artist_id</code> <span class="badge-version">v12.3.2</span></td></tr>
                    </tbody>
                </table>
                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>The red badge on the "Requests" tab shows the combined count of <strong>pending Program + Guest Event + Active Event + Artist Requests</strong>.</div>
                </div>

                <h3>Request Statuses</h3>
                <table class="help-table">
                    <thead><tr><th>Status</th><th>Meaning</th></tr></thead>
                    <tbody>
                        <tr><td><span style="background:#ff9800;color:#fff;padding:2px 8px;border-radius:10px;font-size:.8rem;">pending</span></td><td>Waiting for review — no action taken yet</td></tr>
                        <tr><td><span style="background:#4caf50;color:#fff;padding:2px 8px;border-radius:10px;font-size:.8rem;">approved</span></td><td>Approved — the item has been created or updated automatically</td></tr>
                        <tr><td><span style="background:#f44336;color:#fff;padding:2px 8px;border-radius:10px;font-size:.8rem;">rejected</span></td><td>Rejected by an admin</td></tr>
                    </tbody>
                </table>

                <h3>Approving / Rejecting a Program Request</h3>
                <ol class="steps">
                    <li>Click the <strong>📝 Program Requests</strong> sub-tab</li>
                    <li>Click <strong>👁️ View</strong> on the request you want to review</li>
                    <li>Check the modal: request type, program data, and submitter information</li>
                    <li>For modification requests, a <strong>Comparison View</strong> shows the original vs. proposed changes side by side</li>
                    <li>Click <strong>✅ Approve</strong> to accept and auto-create/update the Program, or <strong>❌ Reject</strong> to decline</li>
                    <li>Optionally add an <strong>Admin Note</strong> before confirming</li>
                </ol>

                <h3>Approving / Rejecting an Event Request <span class="badge-version">v9.3.0</span></h3>
                <ol class="steps">
                    <li>Click the <strong>🗓️ Event Requests</strong> sub-tab</li>
                    <li>Click <strong>👁️ View</strong> on the request you want to review</li>
                    <li>Check the details: request type (add/modify), event name, description, dates, and submitter information</li>
                    <li>Click <strong>✅ Approve</strong> — the system will:
                        <ul>
                            <li><strong>type = add</strong>: Automatically create a new Event (status = <strong>inactive</strong> — you must activate it manually from the Events tab)</li>
                            <li><strong>type = modify</strong>: Update the specified event's non-empty fields</li>
                        </ul>
                    </li>
                    <li>Or click <strong>❌ Reject</strong> and optionally add an Admin Note</li>
                </ol>
                <div class="callout callout-warn">
                    <span class="callout-icon">⚠️</span>
                    <div>Events created by approving an Event Request are always <strong>inactive</strong> — go to the <strong>Events</strong> tab to activate them so they appear on the public website.</div>
                </div>

                <h3>Filtering Requests</h3>
                <ul>
                    <li><strong>Event Filter</strong>: Show requests for a specific event (Program Requests only)</li>
                    <li><strong>Status Filter</strong>: View requests by status (pending, approved, rejected, or all)</li>
                </ul>
            </section>

            <!-- Credits Tab -->
            <section class="help-section" id="credits">
                <h2>📋 Tab: Credits</h2>
                <p>
                    <strong>Credits</strong> are acknowledgements and references displayed on the public Credits page —
                    e.g. data sources, supporters, or featured artists.
                </p>

                <h3>Credit Fields</h3>
                <table class="help-table">
                    <thead><tr><th>Field</th><th>Notes</th></tr></thead>
                    <tbody>
                        <tr><td>Title <span style="color:red">*</span></td><td>Display name / heading (max 200 characters)</td></tr>
                        <tr><td>Link</td><td>URL to a website or profile (optional)</td></tr>
                        <tr><td>Description</td><td>Additional details or context</td></tr>
                        <tr><td>Display Order</td><td>Sort order on the credits page (lower number = shown first, default = 0)</td></tr>
                        <tr><td>Event</td><td>Assign to a specific event, or leave blank to show globally on all events</td></tr>
                    </tbody>
                </table>

                <h3>Bulk Delete Credits</h3>
                <p>Check multiple items → the Bulk Actions bar appears → click <strong>🗑️ Delete Selected</strong>.</p>

                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>Use <strong>Display Order</strong> to control the sequence of credits shown on the website. Lower number = appears first.</div>
                </div>
            </section>

            <!-- Import ICS Tab -->
            <section class="help-section non-organizer-only" id="import">
                <h2>📤 Tab: Import</h2>
                <p>
                    Import Programs from an <strong>.ics</strong> file (iCalendar format).
                    The system parses the file and shows a Preview before you confirm the import.
                </p>

                <h3>Import Steps</h3>
                <ol class="steps">
                    <li>Select the <strong>destination Event</strong> where the Programs will be imported</li>
                    <li>Click the upload area or <strong>drag &amp; drop</strong> your .ics file (max 5 MB)</li>
                    <li>The system parses the file and shows a <strong>Preview Table</strong></li>
                    <li>Review each row's status:
                        <ul>
                            <li><span style="background:#c8e6c9;padding:1px 6px;border-radius:4px;">➕ New</span> = will be added as a new Program</li>
                            <li><span style="background:#fff9c4;padding:1px 6px;border-radius:4px;">⚠️ Duplicate</span> = a matching UID already exists in the database</li>
                            <li><span style="background:#ffcdd2;padding:1px 6px;border-radius:4px;">❌ Error</span> = incomplete or invalid data</li>
                        </ul>
                    </li>
                    <li>For duplicates, choose an action: <strong>Insert</strong> (add another copy) / <strong>Update</strong> (overwrite existing) / <strong>Skip</strong> (ignore)</li>
                    <li>Uncheck rows you don't want to import, or click <strong>Delete Selected</strong></li>
                    <li>Click <strong>✅ Confirm Import</strong></li>
                    <li>An <strong>Import Summary</strong> is displayed (inserted / updated / skipped / errors)</li>
                </ol>

                <h3>Supported ICS Fields</h3>
                <table class="help-table">
                    <thead><tr><th>ICS Field</th><th>Maps to</th></tr></thead>
                    <tbody>
                        <tr><td>SUMMARY</td><td>Program title</td></tr>
                        <tr><td>DTSTART / DTEND</td><td>Start / end date &amp; time</td></tr>
                        <tr><td>LOCATION</td><td>Venue</td></tr>
                        <tr><td>ORGANIZER (CN)</td><td>Organizer name</td></tr>
                        <tr><td>CATEGORIES</td><td>Categories</td></tr>
                        <tr><td>DESCRIPTION</td><td>Description</td></tr>
                        <tr><td>UID</td><td>Unique ID (used for duplicate detection)</td></tr>
                        <tr><td>X-PROGRAM-TYPE</td><td>Program type (<code>program_type</code>) — a custom field specific to this system, set per event</td></tr>
                    </tbody>
                </table>

                <h3 id="import-type">🏷️ Setting Program Type During Import</h3>
                <p>The system supports three ways to assign a Program Type during import (listed by priority):</p>
                <table class="help-table">
                    <thead><tr><th>Method</th><th>Details</th></tr></thead>
                    <tbody>
                        <tr><td>1. <code>X-PROGRAM-TYPE:</code> in the ICS file</td><td>Set the type per individual event in the file — highest priority</td></tr>
                        <tr><td>2. "🏷️ Program Type (default)" field in the UI</td><td>Applied to programs that have no <code>X-PROGRAM-TYPE</code> in the file (batch default)</td></tr>
                        <tr><td>3. <code>--type=value</code> (command line)</td><td>Used as the default type for all programs when importing via CLI</td></tr>
                    </tbody>
                </table>
                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>You can also import via the command line: <code>php tools/import-ics-to-sqlite.php --event=slug --type=stage</code></div>
                </div>
            </section>

            <!-- Artists Tab -->
            <section class="help-section admin-organizer-only" id="artists">
                <h2>🎤 Tab: Artists</h2>
                <p>Manage all artists in the system. Artists can appear in programs across multiple events (Artist Reuse System).</p>
                <div class="callout callout-info organizer-only">
                    <span class="callout-icon">ℹ️</span>
                    <div>For organizer users, the Artists tab is for submitting <strong>Request new artist</strong> only. The request goes to admin/agent review before a real artist record is created. <span class="badge-version">v12.3.0</span></div>
                </div>
                <div class="organizer-only">
                    <h3>How to Request a New Artist</h3>
                    <ol class="steps">
                        <li>Open the <strong>🎤 Artists</strong> tab or click <strong>Request New Artist</strong> from the Dashboard</li>
                        <li>Fill in the artist name, solo/group type, group membership, and related details</li>
                        <li>Submit the request; the system creates an item under <strong>Requests → Artist Request</strong></li>
                        <li>Wait for admin/agent approval; after approval the artist is created and can be used in Programs</li>
                    </ol>
                    <div class="callout callout-warn">
                        <span class="callout-icon">⚠️</span>
                        <div>Organizers cannot create or edit the Artists database directly. In the Program form, organizers must select artists from the existing autocomplete list.</div>
                    </div>
                </div>
                <div class="admin-only">

                <h3>Artist Fields</h3>
                <table class="help-table">
                    <thead><tr><th>Field</th><th>Notes</th></tr></thead>
                    <tbody>
                        <tr><td>Name <span style="color:red">*</span></td><td>Primary artist name — used to match against CATEGORIES in ICS files</td></tr>
                        <tr><td>Type</td><td><strong>Solo</strong> = individual artist | <strong>Group</strong> = band/group</td></tr>
                        <tr><td>Group</td><td>For Solo artists — select which group they belong to (if any)</td></tr>
                        <tr><td>Variants</td><td>Alternate names e.g. abbreviations, other languages, former names</td></tr>
                    </tbody>
                </table>

                <h3>Variants (Alternate Names)</h3>
                <p>Variants allow the system to match artist names from ICS files that may use different spellings:</p>
                <ul>
                    <li>Click the <strong>Variants</strong> button on an artist row to open the variants modal</li>
                    <li>Click <strong>+ Add</strong>, type the alternate name, then click Add</li>
                    <li>Click <strong>×</strong> next to a variant to remove it</li>
                </ul>
                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>When importing an ICS file, the system auto-links programs to artists by matching CATEGORIES — both the primary name and all variants are checked.</div>
                </div>

                <h3>Copy Artist</h3>
                <p>The <strong>Copy</strong> button on each artist row opens a modal to create a new artist based on an existing one:</p>
                <ul>
                    <li>Fields are pre-filled from the original (name + " (copy)", type, group membership)</li>
                    <li>A <strong>Variants to copy</strong> section lists all variants of the original with checkboxes (all checked by default)</li>
                    <li>"Select all" / "Deselect all" buttons are available for variants; all fields can be edited before saving</li>
                    <li>After saving, the system creates the new artist then loops through selected variants to create them</li>
                </ul>

                <h3>Bulk Import Artists</h3>
                <p>The <strong>📥 Import Multiple</strong> button in the toolbar opens a modal for importing many artists at once:</p>
                <ul>
                    <li><strong>Step 1</strong>: Enter one artist name per line (up to 500 names); optionally check "Is a Group" and select a destination group</li>
                    <li><strong>Step 2</strong>: Review results — ✅ Created / ⚠️ Duplicate / ❌ Error — with a summary bar; the artist list refreshes automatically</li>
                </ul>

                <h3>Bulk Select &amp; Bulk Actions</h3>
                <p>The Artists table has a checkbox column for selecting multiple artists at once. A Bulk Toolbar (yellow bar) appears when at least one artist is selected:</p>
                <table class="help-table">
                    <thead><tr><th>Action</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td>👥 Add to Group</td><td>Opens a modal to choose a destination group → sets <code>group_id</code> on all selected artists (Group-type artists are skipped)</td></tr>
                        <tr><td>🚫 Remove from Group</td><td>Sets <code>group_id = null</code> on all selected artists</td></tr>
                    </tbody>
                </table>
                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>Bulk Add to Group automatically skips artists with <code>is_group = 1</code> — the SQL filters <code>WHERE is_group = 0</code> so only Solo artists are affected.</div>
                </div>

                <h3>Artist Portal (Public Artist Listing)</h3>
                <p>The <code>/artists</code> page is a public page listing every group and solo artist in the system — accessible to users via the "🎤 Artists" link in the homepage navigation.</p>
                <ul>
                    <li>Groups are shown as cards with member chips, program count, and a link to the group profile</li>
                    <li>Solo artists are shown in a responsive grid with program count</li>
                    <li>Real-time search — searches group names, member names inside cards, and solo artist names simultaneously</li>
                </ul>
                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>The Artist Portal page is served from cache (<code>cache/query_portal.json</code>, TTL 1 hour) — the cache is invalidated automatically whenever an artist or variant is added, edited, or deleted in this Admin panel.</div>
                </div>

                <h3>Artist Profile Page</h3>
                <p>The artist name in the Artists table links to the public profile page <code>/artist/{id}</code> — showing that artist's programs grouped by event, for events that have not yet ended.</p>

                <h3>Connection to the Program Form</h3>
                <p>The <strong>Artist / Group</strong> field in the Add / Edit Program form connects directly to this Artists table:</p>
                <ul>
                    <li>Type a name → the system autocompletes from the Artists table</li>
                    <li>Select from the dropdown or press <kbd>Enter</kbd> / <kbd>,</kbd> to add the name as a chip</li>
                    <li>If the typed name <strong>does not exist</strong> in the system, a new artist record is created automatically when you click <em>Save</em></li>
                    <li>On save, the <code>program_artists</code> junction table is synced immediately — the artist filter on the public event page reflects the change right away</li>
                </ul>
                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>Artists created through the Program form will appear in this Artists tab automatically. You can add Variants or assign a group membership at any time afterwards.</div>
                </div>
                </div>
            </section>

            <!-- Venues Tab -->
            <section class="help-section admin-only" id="venues">
                <h2>🏛️ Tab: Venues <span class="badge-version">v16.0.0</span></h2>
                <p>Manage a canonical list of venues to remove duplicate <code>location</code> values — similar to Artists, but each program has exactly one venue (location stays text, no FK).</p>

                <h3>Automatic behaviour</h3>
                <ul>
                    <li>When adding/editing a Program or importing ICS, the location is <strong>normalised</strong>: if it matches a venue's canonical name or an alternate name it is rewritten to the canonical form; if unknown, a new venue is auto-registered.</li>
                    <li>The Program form's venue autocomplete now pulls canonical names from the Venues table instead of DISTINCT location.</li>
                </ul>

                <h3>Alternate names (Variants)</h3>
                <ul>
                    <li>Click <strong>Variants</strong> on a venue row to add/remove aliases.</li>
                    <li>Aliases let differently-spelled ICS imports map to a single venue.</li>
                </ul>

                <h3>Merge (combine duplicate venues)</h3>
                <ol class="steps">
                    <li>Tick the checkboxes for the venues to combine (≥2) → click <strong>🔀 Merge Selected</strong>.</li>
                    <li>Pick the canonical venue to keep — the rest become alternate names.</li>
                    <li>Confirm: all matching <code>programs.location</code> (including each source's variants) are rewritten to the canonical name, then the duplicate venues are deleted.</li>
                </ol>
                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>Renaming a venue (Edit) also rewrites the <code>programs.location</code> values that used the old name.</div>
                </div>

                <h3>Public pages</h3>
                <ul>
                    <li>The venue name in the table links to <code>/venue/{id}</code> (programs at that venue across events).</li>
                    <li><code>/venues</code> lists all venues, reachable from the "🏛️ สถานที่ / Venues" homepage nav link.</li>
                </ul>
                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>First-time setup: run <code>php tools/migrate-add-venues-table.php</code> or Setup → Run All Migrations (seeds venues from existing locations + variants from the earlier dedup).</div>
                </div>
            </section>

            <!-- Feed / Subscribe -->
            <section class="help-section" id="feed">
                <h2>🔔 Feed / Subscribe</h2>
                <p>The system provides ICS Subscription Feeds that calendar apps (Google Calendar, Apple Calendar, Outlook, Thunderbird) can pull automatically — subscribers receive updates without needing to export again.</p>

                <h3 id="feed-event">📅 Event Feed</h3>
                <p>The <strong>🔔 Subscribe</strong> button on the event schedule page (<code>/event/{slug}</code>) opens a modal where users can copy the feed URL or open it with webcal://. The URL automatically includes any active filters (artist[], venue[], type[]).</p>
                <table class="help-table">
                    <thead><tr><th>Endpoint</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>/feed</code></td><td>Feed for the Default Event (no slug)</td></tr>
                        <tr><td><code>/event/{slug}/feed</code></td><td>Feed for a specific Event (supports artist[], venue[], type[] query string filters)</td></tr>
                    </tbody>
                </table>
                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>Feeds use a static file cache (<code>cache/feed_*.ics</code>, TTL 1 hour). Every Admin write operation (add / edit / delete Program, ICS import) invalidates the cache immediately — subscribers will receive fresh data on their calendar app's next pull cycle.</div>
                </div>

                <h3 id="feed-artist">🎤 Artist Feed</h3>
                <p>The artist profile page (<code>/artist/{id}</code>) offers two separate subscribe buttons:</p>
                <table class="help-table">
                    <thead><tr><th>Button</th><th>Endpoint</th><th>Pulls</th></tr></thead>
                    <tbody>
                        <tr><td>🔔 ArtistName</td><td><code>/artist/{id}/feed</code></td><td>All programs for this artist across every active event (name + all variant names)</td></tr>
                        <tr><td>🔔 GroupName</td><td><code>/artist/{id}/feed?group=1</code></td><td>Programs performed as the artist's group (shown only when the artist has a group_id)</td></tr>
                    </tbody>
                </table>
                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>Artist Feeds span all events and only include programs from <strong>Active events</strong>. They use the <code>artist_variants</code> table to match the artist name against the <code>categories</code> field in programs. Cache keys are separated between <code>_own</code> and <code>_group</code>.</div>
                </div>
                <div class="callout callout-warning">
                    <span class="callout-icon">⚠️</span>
                    <div>When you edit Artist Variants, the change will be reflected in the Artist Feed after the next pull cycle (cache TTL 1 hour, or after any Admin write to Programs / Artists).</div>
                </div>
            </section>

            <!-- Telegram Notifications -->
            <section class="help-section admin-only" id="telegram">
                <h2>🔔 Telegram Notifications</h2>
                <p>Send push notifications to Telegram when programs from followed artists are about to start. Users link their Telegram account via deep-link and receive automatic reminders N minutes before each program.</p>

                <h3>How It Works</h3>
                <ol class="steps">
                    <li><strong>User links Telegram:</strong> On the <code>/my/{slug}</code> page, user clicks "🔔 Link Telegram" → deep-link to bot → sends <code>/start {slug}</code> → notification history saved</li>
                    <li><strong>Cron job runs every 15 minutes:</strong> Scans all users with Telegram linked and finds programs starting within the notification window</li>
                    <li><strong>Push notification sent:</strong> Bot sends formatted message (program name, artist, venue, time, event name)</li>
                    <li><strong>Duplicate prevention:</strong> Idempotent tracking prevents the same program from being notified multiple times</li>
                </ol>

                <h3>Setup Requirements</h3>
                <table class="help-table">
                    <thead><tr><th>Component</th><th>Details</th></tr></thead>
                    <tbody>
                        <tr><td>Telegram Bot</td><td>Created via @BotFather with a unique token</td></tr>
                        <tr><td>Webhook URL</td><td>HTTPS endpoint for Telegram to send user commands (must be HTTPS, not HTTP)</td></tr>
                        <tr><td>Configuration</td><td><code>config/telegram.php</code> with bot token, secret, notification minutes</td></tr>
                        <tr><td>Cron Job</td><td>Server cron to run <code>cron/send-telegram-notifications.php</code> every 15 minutes</td></tr>
                    </tbody>
                </table>

                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>Complete setup guide with step-by-step instructions: <strong><a href="../TELEGRAM_SETUP_EN.md" target="_blank">TELEGRAM_SETUP_EN.md</a></strong> (Setup Wizard) or <strong><a href="../TELEGRAM_SETUP.md" target="_blank">TELEGRAM_SETUP.md</a></strong> (Thai version)</div>
                </div>

                <h3>Configuration</h3>
                <p>Edit <code>config/telegram.php</code>:</p>
                <table class="help-table">
                    <thead><tr><th>Setting</th><th>Example</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>TELEGRAM_BOT_TOKEN</code></td><td><code>123456789:ABC...</code></td><td>Token from @BotFather (keep secret!)</td></tr>
                        <tr><td><code>TELEGRAM_BOT_USERNAME</code></td><td><code>IdolStageBot</code></td><td>Bot @username without the @ sign</td></tr>
                        <tr><td><code>TELEGRAM_WEBHOOK_SECRET</code></td><td><code>a1b2c3d4...</code></td><td>32-char random string for webhook validation</td></tr>
                        <tr><td><code>TELEGRAM_NOTIFY_BEFORE_MINUTES</code></td><td><code>60</code></td><td>Minutes before program to send notification (30, 60, 120, 1440, etc.)</td></tr>
                        <tr><td><code>TELEGRAM_ENABLED</code></td><td><code>true</code> or <code>false</code></td><td>Master on/off switch for the feature</td></tr>
                    </tbody>
                </table>

                <h3>User Commands</h3>
                <table class="help-table">
                    <thead><tr><th>Command</th><th>Function</th></tr></thead>
                    <tbody>
                        <tr><td><code>/start {slug}</code></td><td>Link Telegram account to favorites; select notification language via inline keyboard (TH/EN/JA)</td></tr>
                        <tr><td><code>/stop</code></td><td>Unlink the Telegram account — stops all notifications</td></tr>
                        <tr><td><code>/today</code></td><td>Show today's events with program counts (condensed format)</td></tr>
                        <tr><td><code>/tomorrow</code></td><td>Show tomorrow's events with program counts</td></tr>
                        <tr><td><code>/week</code></td><td>Show the next 7 days grouped by day</td></tr>
                        <tr><td><code>/upcoming [N]</code></td><td>Show next N upcoming programs (default 3, max 10)</td></tr>
                        <tr><td><code>/next</code></td><td>Alias for <code>/upcoming 1</code> — the very next program</td></tr>
                        <tr><td><code>/artists</code></td><td>List all followed artists (A–Z)</td></tr>
                        <tr><td><code>/lang th|en|ja</code></td><td>Change the bot's notification language</td></tr>
                        <tr><td><code>/tz [zone|auto]</code></td><td><span class="badge-version">v16.1.1</span> Show or set the timezone used for notification times. <code>/tz Asia/Tokyo</code> sets a manual override; <code>/tz auto</code> reverts to automatic</td></tr>
                        <tr><td><code>/mute N</code></td><td>Silence notifications for N hours (1–72)</td></tr>
                        <tr><td><code>/notify on|off|summary</code></td><td><span class="badge-version">v16.2.0</span> Notification mode: <code>on</code> = per-program + daily summary (default), <code>summary</code> = daily summary only, <code>off</code> = disable all</td></tr>
                        <tr><td><code>/status</code></td><td>Show account status: artist count, language, effective timezone, notify mode, mute expiry</td></tr>
                    </tbody>
                </table>

                <h3>Notification Modes &amp; Timezone</h3>
                <ul>
                    <li><strong>Per-program</strong> — sent N minutes before each followed program starts (<code>TELEGRAM_NOTIFY_BEFORE_MINUTES</code>).</li>
                    <li><strong>Daily summary</strong> — sent once each morning (09:00–09:30) listing the day's programs grouped by event.</li>
                    <li><strong>Notification times use the user's timezone</strong> <span class="badge-version">v16.1.1</span> — the message shows the event-local time with the user's local time in parentheses, e.g. <code>18:00 (19:00 Asia/Tokyo)</code>, when the event timezone differs from the recipient's. Users control their timezone with <code>/tz</code>.</li>
                </ul>

                <h3>Troubleshooting</h3>
                <table class="help-table">
                    <thead><tr><th>Problem</th><th>Solution</th></tr></thead>
                    <tbody>
                        <tr><td>Button "🔔 Link Telegram" not showing on /my page</td><td>Check <code>TELEGRAM_ENABLED=true</code> in config and that <code>TELEGRAM_BOT_TOKEN</code> is set. Clear browser cache.</td></tr>
                        <tr><td>Webhook registration failed</td><td>Verify domain is HTTPS (not HTTP), token is correct, and domain is accessible from the internet.</td></tr>
                        <tr><td>No notifications being sent</td><td>Check cron job is running: <code>crontab -l</code>. Run <code>php cron/send-telegram-notifications.php</code> manually to test.</td></tr>
                        <tr><td>Duplicate notifications</td><td>Notifications are idempotent — duplicates should not occur. If they do, check server clock is synchronized.</td></tr>
                    </tbody>
                </table>

                <div class="callout callout-warn">
                    <span class="callout-icon">⚠️</span>
                    <div><strong>HTTPS Only:</strong> Telegram does not support HTTP webhooks. Your domain must have a valid SSL certificate and be accessible via HTTPS.</div>
                </div>

                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div><strong>Test Mode:</strong> Before adding to production cron, test manually: <code>php cron/send-telegram-notifications.php</code>. Should output notification count and any errors.</div>
                </div>
            </section>

            <!-- Web Push Notifications (PWA) -->
            <section class="help-section admin-only" id="webpush">
                <h2>📱 Web Push Notifications (PWA)</h2>
                <p>Delivers <strong>browser push notifications</strong> directly to users without requiring Telegram or any additional app. Supports Chrome, Firefox, Edge, and Safari 16.4+ on both desktop and mobile. Users click "Subscribe" on the <code>/my/{slug}</code> page and receive notifications N minutes before a program starts. The same system also enables PWA installation (Add to Home Screen).</p>

                <h3>How It Works</h3>
                <ol class="steps">
                    <li><strong>User subscribes:</strong> On <code>/my/{slug}</code> → browser requests permission → subscription (endpoint + encryption keys) is saved to the user's favorites JSON file</li>
                    <li><strong>Cron job runs every 15 minutes:</strong> Scans all users with active push subscriptions and finds programs within the notification window</li>
                    <li><strong>Push notification sent:</strong> Payload encrypted per RFC 8291 (aes128gcm) + VAPID JWT, then HTTP-POSTed to the browser's push service (FCM/Mozilla/Apple)</li>
                    <li><strong>Service Worker displays it:</strong> <code>service-worker.js</code> receives the push event and calls <code>showNotification()</code>; clicking the notification opens the event page</li>
                </ol>

                <h3>Initial Setup</h3>
                <ol class="steps">
                    <li>Go to <strong>Settings → 📱 Web Push</strong></li>
                    <li>Click <strong>Generate VAPID Keys</strong> to create a new EC P-256 key pair (one-time only)</li>
                    <li>Enter a <strong>Subject</strong> — the admin contact email, e.g. <code>mailto:admin@example.com</code></li>
                    <li>Enter the <strong>Site URL</strong> — the full URL of your site including subdirectory, no trailing slash (e.g. <code>https://example.com/stage-idol-calendar</code>); used to build links in notifications</li>
                    <li>Set <strong>Notify Before (minutes)</strong> as needed (default: 60 minutes)</li>
                    <li>Toggle <strong>Enable Web Push</strong> on and click Save</li>
                    <li>Add the cron job shown in the Settings page</li>
                    <li>Run <code>php tools/generate-pwa-icons.php</code> once to create the PWA icons (72/192/512 px)</li>
                </ol>

                <div class="callout callout-warn">
                    <span class="callout-icon">⚠️</span>
                    <div><strong>Important:</strong> VAPID keys must be generated <strong>once only</strong>. Regenerating keys after users have subscribed will invalidate all existing subscriptions immediately (browsers receive HTTP 410 Gone on the next push attempt).</div>
                </div>

                <h3>Setup Requirements</h3>
                <table class="help-table">
                    <thead><tr><th>Component</th><th>Details</th></tr></thead>
                    <tbody>
                        <tr><td>VAPID Keys</td><td>EC P-256 key pair generated via Admin UI (Generate VAPID Keys button)</td></tr>
                        <tr><td>HTTPS</td><td>Service Workers require HTTPS (localhost is an exception)</td></tr>
                        <tr><td>Configuration</td><td><code>config/webpush-config.json</code> with VAPID keys, subject, settings (written by Admin UI)</td></tr>
                        <tr><td>Cron Job</td><td>Server cron to run <code>cron/send-web-push-notifications.php</code> every 15 minutes</td></tr>
                        <tr><td>PWA Icons</td><td><code>icon/icon-72.png</code>, <code>icon-192.png</code>, <code>icon-512.png</code> (generated by <code>php tools/generate-pwa-icons.php</code>)</td></tr>
                    </tbody>
                </table>

                <h3>Configuration (Admin UI)</h3>
                <table class="help-table">
                    <thead><tr><th>Setting</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Enable Web Push</strong></td><td>Master on/off switch for Web Push (requires VAPID keys to be set first)</td></tr>
                        <tr><td><strong>VAPID Public Key</strong></td><td>Displays the base64url public key (87 chars) — sent to the browser during subscription</td></tr>
                        <tr><td><strong>VAPID Subject</strong></td><td>Admin contact as <code>mailto:...</code> or a URL; used in the VAPID JWT authorization header</td></tr>
                        <tr><td><strong>Site URL</strong></td><td>Full URL of the website including subdirectory, no trailing slash (e.g. <code>https://example.com/stage-idol-calendar</code>) — used to build notification links and icon URLs</td></tr>
                        <tr><td><strong>Notify Before (minutes)</strong></td><td>Minutes before program start to send notification (default: 60)</td></tr>
                        <tr><td><strong>Max Subscriptions per Token</strong></td><td>Maximum number of subscriptions per favorites token (default: 5)</td></tr>
                        <tr><td><strong>Generate VAPID Keys</strong></td><td>Creates a new key pair (first-time only — regenerating invalidates all existing subscriptions)</td></tr>
                        <tr><td><strong>Test Push</strong></td><td>Sends a test notification to the most recent subscription in an active favorites file</td></tr>
                    </tbody>
                </table>

                <h3>Cron Job Setup</h3>
                <pre><code># Run every 15 minutes (recommended)
*/15 * * * * php /path/to/cron/send-web-push-notifications.php >> /path/to/cache/logs/webpush-cron.log 2&gt;&amp;1

# Daily log rotation at midnight (keeps 7 days)
0 0 * * * php /path/to/cron/rotate-webpush-logs.php >> /path/to/cache/logs/rotate-cron.log 2&gt;&amp;1</code></pre>
                <p>Logs are written to <code>cache/logs/webpush-cron.log</code>. Size-based rotation kicks in automatically at 10 MB; daily rotation via <code>cron/rotate-webpush-logs.php</code> archives to dated files and deletes logs older than 7 days.</p>

                <h3>Key Files</h3>
                <table class="help-table">
                    <thead><tr><th>File</th><th>Purpose</th></tr></thead>
                    <tbody>
                        <tr><td><code>service-worker.js</code></td><td>Service Worker that receives push events and shows notifications; scope <code>/</code></td></tr>
                        <tr><td><code>manifest.json</code></td><td>Web App Manifest — name, icons, theme_color; enables the browser's "Add to Home Screen" prompt</td></tr>
                        <tr><td><code>icons/</code></td><td>PNG icons in 3 sizes (72/192/512 px); generated by <code>php tools/generate-pwa-icons.php</code></td></tr>
                        <tr><td><code>api/push.php</code></td><td>Public API: subscribe / unsubscribe / status (requires HMAC-signed favorites slug)</td></tr>
                        <tr><td><code>config/webpush-config.json</code></td><td>VAPID keys + settings (HTTP-protected by <code>config/.htaccess</code> — never exposed)</td></tr>
                        <tr><td><code>functions/webpush.php</code></td><td>Crypto core: VAPID JWT (ES256), RFC 8291 aes128gcm encryption, key generation</td></tr>
                        <tr><td><code>cron/send-web-push-notifications.php</code></td><td>Cron script that sends notifications (CLI-only, exits cleanly if Web Push is disabled)</td></tr>
                        <tr><td><code>tools/generate-pwa-icons.php</code></td><td>Generates sakura-gradient PNG icons using PHP GD</td></tr>
                    </tbody>
                </table>

                <h3>Troubleshooting</h3>
                <table class="help-table">
                    <thead><tr><th>Problem</th><th>Solution</th></tr></thead>
                    <tbody>
                        <tr><td>Subscribe button not shown on /my page</td><td>Check that <code>WEBPUSH_ENABLED=true</code> and VAPID Public Key is non-empty in config</td></tr>
                        <tr><td>Browser does not ask for permission</td><td>Verify the site is served over HTTPS (Service Workers require HTTPS) and <code>service-worker.js</code> exists at root</td></tr>
                        <tr><td>No notifications being sent</td><td>Check cron is running. Test manually: <code>php cron/send-web-push-notifications.php</code></td></tr>
                        <tr><td>HTTP 410 from push service</td><td>Subscription has expired — the cron automatically removes it on the next run</td></tr>
                        <tr><td>Duplicate notifications</td><td>System has an idempotent ±7.5-minute window; check server clock is synchronized</td></tr>
                        <tr><td>VAPID key generation fails (Windows)</td><td>Ensure the OpenSSL extension is enabled in php.ini and that <code>webpush_openssl_ec_config()</code> can locate <code>openssl.cnf</code></td></tr>
                    </tbody>
                </table>

                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div><strong>Browser Support:</strong> Chrome 42+, Firefox 44+, Edge 17+, Samsung Internet 4+ support Web Push fully. Safari requires iOS 16.4+ / macOS Ventura+ and the user must have added the site to their Home Screen before push notifications work (Safari does not support Web Push on regular web pages).</div>
                </div>
            </section>

            <!-- Admin Audit Log -->
            <section class="help-section admin-only" id="audit-log">
                <h2>🔎 Admin Audit Log <span class="badge-admin">admin only</span></h2>
                <p>Records all admin actions as JSON Lines files — one file per day (<code>cache/logs/admin-audit-YYYY-MM-DD.log</code>), retained for 30 days.</p>

                <h3>Events Recorded</h3>
                <ul>
                    <li><strong>Auth</strong> — login_success, login_failure, login_blocked, logout, twofa_success, twofa_failure, twofa_backup_used</li>
                    <li><strong>Programs/Events/Artists/Credits/Users</strong> — create, update, delete, bulk_delete</li>
                    <li><strong>Backup</strong> — backup_create, backup_download, backup_delete, backup_restore, backup_upload_restore</li>
                    <li><strong>Settings</strong> — settings_update (theme, title, disclaimer), site_cover_delete</li>
                    <li><strong>Config</strong> — config_update (telegram, email, analytics)</li>
                    <li><strong>Password/2FA</strong> — change_password, twofa_setup, twofa_disable, twofa_regenerate_backup_codes, twofa_reset</li>
                </ul>

                <h3>Viewing the Audit Log</h3>
                <ol>
                    <li>Go to <strong>Settings → 🔎 Audit Log</strong></li>
                    <li>Choose a date from the dropdown (newest first)</li>
                    <li>Use the <em>Filter</em> field to narrow results by action / actor / outcome / entity</li>
                    <li>Click <em>Download</em> to get the full log file</li>
                </ol>

                <h3>Log Record Format (JSON Lines)</h3>
                <ul>
                    <li><code>ts</code> — timestamp (Asia/Bangkok)</li>
                    <li><code>request_id</code> — unique ID for the HTTP request</li>
                    <li><code>action</code> — event name, e.g. <code>program_create</code></li>
                    <li><code>outcome</code> — <code>success</code> / <code>failure</code> / <code>blocked</code></li>
                    <li><code>actor_user_id</code>, <code>actor_username</code>, <code>actor_role</code></li>
                    <li><code>ip</code>, <code>ua</code> — IP address and User-Agent</li>
                    <li><code>entity_type</code>, <code>entity_id</code>, <code>entity_label</code></li>
                    <li><code>metadata</code> — extra detail (changed values, counts, etc.)</li>
                </ul>

                <h3>Log Rotation (Cron)</h3>
                <pre><code>0 0 * * * php /path/to/cron/rotate-admin-audit-logs.php >> /path/to/cache/logs/rotate-cron.log 2&gt;&amp;1</code></pre>
            </section>

            <!-- Users Tab -->
            <section class="help-section admin-only" id="users">
                <h2>👤 Tab: Users <span class="badge-admin">admin only</span></h2>
                <p>Manage all Admin accounts. Only users with the <strong>admin</strong> role can access this tab.</p>

                <h3>User Fields</h3>
                <table class="help-table">
                    <thead><tr><th>Field</th><th>Notes</th></tr></thead>
                    <tbody>
                        <tr><td>Username <span style="color:red">*</span></td><td>Login name (letters, numbers, <code>_</code>, <code>-</code>, <code>.</code> only)</td></tr>
                        <tr><td>Display Name</td><td>The name shown in the admin header</td></tr>
                        <tr><td>Password</td><td>Minimum 8 characters. Leave blank when editing to keep the existing password.</td></tr>
                        <tr><td>Role</td><td><strong>admin</strong> = full access to all tabs | <strong>agent</strong> = operational and request review tools | <strong>organizer</strong> = assigned-event access only</td></tr>
                        <tr><td>2FA <span class="badge-version">v10.0.0</span></td><td>Shows Two-Factor Authentication status for the account; if enabled, an admin can reset it for account recovery</td></tr>
                        <tr><td>Active</td><td>Enable / disable the account (inactive users cannot log in)</td></tr>
                    </tbody>
                </table>

                <h3>Resetting a User's 2FA <span class="badge-version">v10.0.0</span></h3>
                <ol class="steps">
                    <li>Go to <strong>Settings → Users</strong></li>
                    <li>Check the <strong>2FA</strong> column to see whether the account has 2FA enabled</li>
                    <li>Click <strong>Reset 2FA</strong> on the target user's row</li>
                    <li>The user must set up 2FA again after their next login</li>
                </ol>

                <h3>Lockout Prevention Rules</h3>
                <ul>
                    <li>You <strong>cannot delete your own account</strong></li>
                    <li>You <strong>cannot change your own role</strong></li>
                    <li>At least one <strong>admin account must always exist</strong> (cannot delete the last admin)</li>
                </ul>

                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>
                        Users created through the Admin UI are stored in the SQLite database.<br>
                        If the <code>admin_users</code> table does not exist, the system falls back to the credentials in <code>config/admin.php</code>.
                        2FA is available only for users stored in <code>admin_users</code>.
                    </div>
                </div>
            </section>

            <!-- Backup Tab -->
            <section class="help-section admin-only" id="backup">
                <h2>💾 Tab: Backup <span class="badge-admin">admin only</span></h2>
                <p>Back up and restore the entire SQLite database.</p>

                <h3>Creating a Backup</h3>
                <ol class="steps">
                    <li>Click <strong>💾 Create Backup</strong></li>
                    <li>A file named <code>backup_YYYYMMDD_HHMMSS.db</code> is saved in the <code>backups/</code> folder</li>
                    <li>The new backup appears in the table below</li>
                </ol>

                <h3>Downloading a Backup</h3>
                <p>Click <strong>⬇️ Download</strong> next to any backup to save the <code>.db</code> file to your device.</p>

                <h3>Restoring from a Backup</h3>
                <ol class="steps">
                    <li>Click <strong>🔄 Restore</strong> next to the backup you want to restore</li>
                    <li>Read the warning — the system will create an auto-backup before restoring</li>
                    <li>Click <strong>Restore</strong> to confirm</li>
                </ol>

                <h3>Restoring from an Uploaded File</h3>
                <ol class="steps">
                    <li>Click <strong>📤 Upload &amp; Restore</strong></li>
                    <li>Select a <code>.db</code> file from your device</li>
                    <li>Confirm — an auto-backup is always created first</li>
                </ol>

                <div class="callout callout-danger">
                    <span class="callout-icon">🚫</span>
                    <div>
                        <strong>A Restore will replace all current database data!</strong><br>
                        An automatic backup is always created before restoring, but double-check before proceeding.
                    </div>
                </div>

                <h3>Deleting a Backup</h3>
                <p>Click <strong>🗑️ Delete</strong> next to a backup → confirm → the file is permanently removed.</p>
            </section>

            <!-- Settings Tab -->
            <section class="help-section admin-only" id="settings">
                <h2>⚙️ Tab: Settings <span class="badge-admin">admin only</span></h2>
                <p>The Settings tab organizes configuration with 9 sub-tabs for <strong>Site</strong>, <strong>Contact Channels</strong>, <strong>Users</strong>, <strong>Backup/Restore</strong>, <strong>Telegram Notifications</strong>, <strong>Email Notifications</strong>, <strong>Google Services</strong>, <strong>Web Push (PWA)</strong>, and <strong>Disclaimer</strong>. Only users with the <strong>admin</strong> role can access this tab.</p>

                <h3>📝 Settings Sub-tabs (v6.4.0+)</h3>
                <p>The Settings tab is organized into 9 sub-tabs for easy navigation:</p>
                <table class="help-table">
                    <thead><tr><th>Sub-tab</th><th>Function</th><th>Category</th></tr></thead>
                    <tbody>
                        <tr><td>📝 <strong>Site</strong></td><td>Configure Site Title, Site Theme, Site-wide Header Cover Image</td><td>Global settings</td></tr>
                        <tr><td>✉️ <strong>Contact</strong></td><td>Manage Contact Channels for the website</td><td>Contact information</td></tr>
                        <tr><td>👤 <strong>Users</strong></td><td>Manage Admin Users, permissions (Admin/Agent/Organizer)</td><td>User management</td></tr>
                        <tr><td>💾 <strong>Backup</strong></td><td>Backup/Restore database</td><td>Database management</td></tr>
                        <tr><td>🤖 <strong>Telegram</strong></td><td>Configure Telegram Bot, Notifications</td><td>Telegram integration</td></tr>
                        <tr><td>📧 <strong>Email</strong></td><td>Configure SMTP and send test emails for new request notifications</td><td>Email notifications</td></tr>
                        <tr><td>🔵 <strong>Google</strong></td><td>Configure Google Analytics (GA4) and Google AdSense</td><td>Monetization &amp; tracking</td></tr>
                        <tr><td>📱 <strong>Web Push</strong></td><td>Configure VAPID keys, send browser push notifications, PWA icons</td><td>Web Push notifications</td></tr>
                        <tr><td>⚠️ <strong>Disclaimer</strong></td><td>Configure Disclaimer (TH/EN/JA)</td><td>Legal content</td></tr>
                    </tbody>
                </table>

                <h3>📝 What is Site Title?</h3>
                <p>
                    The site title is displayed in the <strong>browser tab</strong>, the <strong>header</strong> of every public page,
                    and in <strong>ICS exports</strong> (calendar name). The default value is "Idol Stage Timetable".
                </p>

                <h3>How to Change the Site Title</h3>
                <ol class="steps">
                    <li>Click the <strong>⚙️ Settings</strong> tab</li>
                    <li>Type the new name in the <strong>Site Title</strong> field (max 100 characters)</li>
                    <li>Click <strong>💾 Save Title</strong></li>
                    <li>You will see <strong>✅ Saved</strong> — reload a public page to verify the change</li>
                </ol>

                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>The site title is stored in <code>cache/site-settings.json</code> and affects the browser tab, header, footer copyright, and ICS calendar name.</div>
                </div>

                <h3>🎨 What is Site Theme?</h3>
                <p>
                    Admins can choose a color theme for all public pages (home, how-to-use, contact, credits).
                    The server loads the selected theme's CSS automatically — every visitor sees the same theme.
                </p>

                <h3>Available Themes</h3>
                <table class="help-table">
                    <thead><tr><th>Theme</th><th>Color</th></tr></thead>
                    <tbody>
                        <tr><td>🌸 Sakura</td><td>Pink</td></tr>
                        <tr><td>🌊 Ocean</td><td>Blue</td></tr>
                        <tr><td>🌿 Forest</td><td>Green</td></tr>
                        <tr><td>🌙 Midnight</td><td>Purple</td></tr>
                        <tr><td>☀️ Sunset</td><td>Orange</td></tr>
                        <tr><td>🖤 Dark</td><td>Blue-Gray (Charcoal) — <em>system fallback when no theme is configured</em></td></tr>
                        <tr><td>🩶 Gray</td><td>Gray (Silver)</td></tr>
                    </tbody>
                </table>

                <h3>How to Change the Theme</h3>
                <ol class="steps">
                    <li>Click the <strong>⚙️ Settings</strong> tab</li>
                    <li>The current theme loads and the color palette is displayed</li>
                    <li>Click the color circle of the theme you want (a border highlights the selected one)</li>
                    <li>Click <strong>💾 Save Theme</strong></li>
                    <li>You will see <strong>✅ Saved</strong> — open a public page to verify the change</li>
                </ol>

                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>The theme takes effect immediately when the public page is reloaded — no server restart required.</div>
                </div>

                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>The theme setting is stored in <code>cache/site-theme.json</code> and is read by the server on every page load.</div>
                </div>

                <h3>🎨 Per-Event Theme</h3>
                <p>
                    In addition to the global site theme, you can assign a color theme to each individual Event.
                    The system selects the theme using the following <strong>priority order</strong>:
                </p>
                <ol class="steps">
                    <li><strong>Event theme</strong> — if the Event has a theme set, that theme is applied to all pages within that event</li>
                    <li><strong>Global theme</strong> — if the Event has no theme selected, the global theme from the Settings tab is used</li>
                    <li><strong>Fallback: <code>dark</code></strong> — if neither is configured, the <code>dark</code> theme is applied automatically</li>
                </ol>

                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>Set a per-event theme in the <strong>🎪 Events</strong> tab → click <strong>➕ Add Event</strong> or the <strong>✏️</strong> edit button → <strong>Theme</strong> field</div>
                </div>

                <h3>🖼️ Site-wide Header Cover Image <span class="badge-version">v9.2.0</span></h3>
                <p>
                    Upload a <strong>4:1</strong> banner image (recommended 1920×480 px) to use as the <code>&lt;header&gt;</code> background
                    on all public pages automatically. If an individual Event has its own Header Cover Image set, the event's image takes priority.
                </p>
                <table class="help-table">
                    <thead><tr><th>Priority</th><th>Header Source</th></tr></thead>
                    <tbody>
                        <tr><td>1 (highest)</td><td>Per-event Header Cover Image (set in Events tab)</td></tr>
                        <tr><td>2</td><td>Site-wide Header Cover (set here, in Settings → Site)</td></tr>
                        <tr><td>3 (fallback)</td><td>Theme gradient (no image)</td></tr>
                    </tbody>
                </table>
                <h4>How to Upload the Site-wide Header Cover</h4>
                <ol class="steps">
                    <li>Click the <strong>⚙️ Settings</strong> tab → <strong>📝 Site</strong> sub-tab</li>
                    <li>Scroll down to the <strong>Header Cover Background</strong> section</li>
                    <li>Click <strong>📸 Upload Cover</strong> → select an image file</li>
                    <li>The Cropper.js window opens with a 4:1 frame — adjust the crop and click <strong>✅ Crop &amp; Upload</strong></li>
                    <li>The image is applied to all pages that do not have a per-event Header Cover immediately</li>
                </ol>
                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>The image is saved in <code>uploads/site/</code> and its URL is stored in <code>cache/site-settings.json</code>. Click 🗑️ to remove the image and revert to the gradient fallback.</div>
                </div>

                <h3>🔵 Google Services (v6.3.0–6.4.0)</h3>
                <p>
                    Configure <strong>Google Analytics</strong> and <strong>Google AdSense</strong> directly from the Admin UI —
                    no SSH or file editing required. Settings are saved in <code>config/google-config.json</code>
                    and loaded via <code>config/google.php</code>.
                </p>

                <h4>📈 Google Analytics (GA4)</h4>
                <table class="help-table">
                    <thead><tr><th>Field</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td>Measurement ID</td><td>GA4 Measurement ID in the format <code>G-XXXXXXXXXX</code> — found in your Google Analytics dashboard. Leave blank to disable Analytics.</td></tr>
                    </tbody>
                </table>

                <h4>💰 Google AdSense</h4>
                <table class="help-table">
                    <thead><tr><th>Field</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td>Publisher ID</td><td>Format: <code>ca-pub-XXXXXXXXXXXXXXXX</code> — found in your AdSense dashboard. <strong>Leave blank to disable all ads.</strong></td></tr>
                        <tr><td>Slot: Leaderboard</td><td>Ad slot ID for the leaderboard unit (728×90) — displayed after the event header on the main page.</td></tr>
                        <tr><td>Slot: Rectangle</td><td>Ad slot ID for the rectangle unit (300×250) — displayed on artist profile pages.</td></tr>
                        <tr><td>Slot: Responsive</td><td>Ad slot ID for the responsive unit (auto-size) — displayed before the footer on other pages.</td></tr>
                    </tbody>
                </table>

                <h4>How to Configure Google Services</h4>
                <ol class="steps">
                    <li>Click the <strong>⚙️ Settings</strong> tab</li>
                    <li>Click the <strong>🔵 Google</strong> sub-tab</li>
                    <li>Enter your <strong>Measurement ID</strong> for Analytics and/or <strong>Publisher ID + Slot IDs</strong> for AdSense</li>
                    <li>Click <strong>💾 Save Analytics</strong></li>
                    <li>You will see <strong>✅ Saved</strong> — the GA4/AdSense code loads automatically on all public pages</li>
                </ol>

                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>
                        <strong>Analytics and Ads are independent:</strong>
                        Leaving Publisher ID blank disables all ads while Analytics continues to work (if Measurement ID is set), and vice versa.
                    </div>
                </div>

                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>
                        <code>config/google-config.json</code> is protected from HTTP access by <code>config/.htaccess</code> automatically — your API keys are never exposed through the web.
                    </div>
                </div>

                <h3>⚠️ Disclaimer</h3>
                <p>
                    The disclaimer text shown on the <strong>Contact</strong> page supports 3 languages (Thai / English / Japanese).
                    If a field is left blank, the default value from <code>translations.js</code> is used instead.
                </p>

                <h3>How to Configure the Disclaimer</h3>
                <ol class="steps">
                    <li>Click the <strong>⚙️ Settings</strong> tab</li>
                    <li>Scroll down to the <strong>Disclaimer</strong> section</li>
                    <li>Fill in the text for 🇹🇭 Thai, 🇬🇧 English, and 🇯🇵 Japanese as needed (blank = use default)</li>
                    <li>Click <strong>💾 Save Disclaimer</strong></li>
                    <li>You will see <strong>✅ Saved</strong> — the new text takes effect immediately when the Contact page is reloaded</li>
                </ol>

                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>The disclaimer is stored in <code>cache/site-settings.json</code> alongside the Site Title. The Contact page patches the translation strings server-side before JavaScript loads, so the text switches correctly when the visitor changes language.</div>
                </div>
            </section>

            <!-- Contact Tab -->
            <section class="help-section admin-only" id="contact">
                <h2>✉️ Tab: Contact <span class="badge-admin">admin only</span></h2>
                <p>Manage the <strong>contact channels</strong> displayed on the public Contact page — e.g. Twitter/X, Line, Email. Data is stored in SQLite; no code changes required.</p>

                <h3>Key Features</h3>
                <ul>
                    <li>Add / Edit / Delete unlimited contact channels</li>
                    <li>Set a <strong>display order</strong> using a number (lower = shown first)</li>
                    <li>Show or hide individual channels with the <strong>Active</strong> toggle</li>
                    <li>No migration script needed — the <code>contact_channels</code> table is created automatically</li>
                </ul>

                <h3>Contact Channel Fields</h3>
                <table class="help-table">
                    <thead><tr><th>Field</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td>Icon (emoji)</td><td>Emoji shown before the channel name, e.g. 💬 📧 📱</td></tr>
                        <tr><td>Channel Name <span style="color:red">*</span></td><td>Name displayed on the page, e.g. "Twitter (X)", "Line Official"</td></tr>
                        <tr><td>Description</td><td>Short note, e.g. "Follow for news and updates"</td></tr>
                        <tr><td>URL / Contact</td><td>A clickable link, e.g. https://x.com/... or mailto:...</td></tr>
                        <tr><td>Display Order</td><td>Integer ≥ 0; lower values are shown first</td></tr>
                        <tr><td>Active</td><td>Controls visibility on the public Contact page</td></tr>
                    </tbody>
                </table>

                <h3>How to Add a Contact Channel</h3>
                <ol class="steps">
                    <li>Click the <strong>✉️ Contact</strong> tab</li>
                    <li>Click <strong>➕ Add Channel</strong></li>
                    <li>Fill in the form (Channel Name is required)</li>
                    <li>Click <strong>💾 Save</strong></li>
                    <li>The new channel appears immediately on the public Contact page</li>
                </ol>

                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>To temporarily hide a channel without deleting it, uncheck <strong>Active</strong> in the edit form. The channel will not appear on the public Contact page until re-activated.</div>
                </div>
            </section>

            <!-- Roles -->
            <section class="help-section" id="roles">
                <h2>🛡️ User Roles</h2>
                <p>The system has 3 roles:</p>
                <table class="help-table">
                    <thead><tr><th>Feature</th><th><span class="badge-admin">admin</span></th><th><span class="badge-agent">agent</span></th><th><span class="badge-organizer">organizer</span></th></tr></thead>
                    <tbody>
                        <tr><td>Programs (CRUD)</td><td>✅</td><td>✅</td><td>✅ assigned events only</td></tr>
                        <tr><td>Events (CRUD)</td><td>✅</td><td>✅</td><td>✅ own events / request activation</td></tr>
                        <tr><td>Requests (view / approve / reject)</td><td>✅</td><td>✅</td><td>❌</td></tr>
                        <tr><td>Credits (CRUD)</td><td>✅</td><td>✅</td><td>✅ related events only</td></tr>
                        <tr><td>Artists</td><td>✅ CRUD</td><td>❌</td><td>✅ submit artist requests</td></tr>
                        <tr><td>Import ICS</td><td>✅</td><td>✅</td><td>❌</td></tr>
                        <tr><td>Users (CRUD)</td><td>✅</td><td>❌</td><td>❌</td></tr>
                        <tr><td>Manage own 2FA</td><td>✅</td><td>✅</td><td>✅</td></tr>
                        <tr><td>Reset another user's 2FA</td><td>✅</td><td>❌</td><td>❌</td></tr>
                        <tr><td>Backup / Restore</td><td>✅</td><td>❌</td><td>❌</td></tr>
                        <tr><td>Settings (Title + Theme + Google + Disclaimer)</td><td>✅</td><td>❌</td><td>❌</td></tr>
                        <tr><td>Contact Channels (CRUD)</td><td>✅</td><td>❌</td><td>❌</td></tr>
                    </tbody>
                </table>
            </section>

            <!-- Tips & FAQ -->
            <section class="help-section" id="tips">
                <h2>💡 Tips &amp; FAQ</h2>

                <h3>Q: The cache is stale — the website still shows old data</h3>
                <p>Update <code>APP_VERSION</code> in <code>config/app.php</code> to a new value (e.g. <code>1.2.11</code> → <code>1.2.12</code>)
                to force browsers to reload CSS/JS assets.</p>
                <div class="callout callout-tip">
                    <span class="callout-icon">💡</span>
                    <div>If you use Cloudflare, also perform a <strong>Purge Cache</strong> from the Cloudflare dashboard.</div>
                </div>

                <h3>Q: I added a Program but it doesn't appear on the website</h3>
                <ul>
                    <li>Verify that the <strong>Event the Program belongs to</strong> has <strong>Active</strong> status</li>
                    <li>Check that no date or venue filter is hiding the Program</li>
                    <li>Try a hard-refresh in your browser (<kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>R</kbd>)</li>
                </ul>

                <h3>Q: I imported an ICS file but no data appeared</h3>
                <ul>
                    <li>Confirm the <code>.ics</code> file contains the required fields: <code>DTSTART</code>, <code>DTEND</code>, <code>SUMMARY</code></li>
                    <li>File size must not exceed <strong>5 MB</strong></li>
                    <li>Check the browser console or PHP error log for details</li>
                </ul>

                <h3>Q: I added a Program but the Feed (webcal) still shows old data</h3>
                <ul>
                    <li>The feed uses a static cache (TTL 1 hour). Every Admin write operation invalidates the cache immediately — but each calendar app has its own pull schedule (Apple ~1 hr, Google ~24 hr).</li>
                    <li>To force an immediate refresh: in Apple Calendar press "Refresh"; in Outlook Desktop click "Sync"; in Google Calendar you need to remove and re-subscribe.</li>
                </ul>

                <h3>Q: The public page is slow after a large ICS import</h3>
                <ul>
                    <li>The system uses a Query Cache for the event schedule page (<code>cache/query_event_{id}.json</code>) and artist profile page (<code>cache/query_artist_{id}.json</code>) — TTL 1 hour.</li>
                    <li>Every Admin write (add / edit / delete Program or Artist) invalidates the cache immediately. The first page load after invalidation rebuilds the cache; subsequent requests will be fast.</li>
                    <li>To clear the cache manually: delete <code>cache/query_event_*.json</code> and <code>cache/query_artist_*.json</code> from the server.</li>
                </ul>

                <h3>Q: How do I assign a Program Type when importing an ICS file?</h3>
                <ul>
                    <li>Add <code>X-PROGRAM-TYPE:stage</code> inside each VEVENT block in the ICS file to set the type per program</li>
                    <li>Or enter a value in the <strong>🏷️ Program Type (default)</strong> field before uploading your file in Admin → Import</li>
                    <li>Or use the <code>--type=value</code> argument when running the CLI: <br><code>php tools/import-ics-to-sqlite.php --event=slug --type=stage</code></li>
                    <li>Values from <code>X-PROGRAM-TYPE:</code> in the ICS file always take priority over the default type</li>
                </ul>

                <h3>Q: I changed the Site Title but the page still shows the old name</h3>
                <ul>
                    <li>Make sure you clicked <strong>💾 Save Title</strong> and saw the "✅ Saved" confirmation</li>
                    <li>Reload the <em>public</em> page with <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>R</kbd></li>
                    <li>Check that the <code>cache/</code> directory is writable on the server</li>
                </ul>

                <h3>Q: I changed the theme but the website color didn't change</h3>
                <ul>
                    <li>Make sure you clicked <strong>💾 Save Theme</strong> and saw the "✅ Saved" confirmation</li>
                    <li>Reload the <em>public</em> page (not the Admin panel) with <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>R</kbd></li>
                    <li>Check that the <code>cache/</code> directory is writable on the server</li>
                    <li>If accessing via <code>/event/slug</code> and that Event has a theme set, the Event theme will always <strong>override</strong> the global theme</li>
                </ul>

                <h3>Q: How do I give each Event a different color theme?</h3>
                <ol class="steps">
                    <li>Go to the <strong>🎪 Events</strong> tab</li>
                    <li>Click the <strong>✏️</strong> edit button on the Event you want to customize</li>
                    <li>Select a theme from the <strong>Theme</strong> dropdown (or choose "— Use Global Theme —" to inherit the global setting)</li>
                    <li>Click <strong>💾 Save</strong></li>
                </ol>

                <h3>Q: How do I schedule automatic database backups?</h3>
                <p>Set up a cron job to call the <code>backup_create</code> API endpoint, or run a backup script on a schedule.
                Alternatively, always create a manual Backup through the Admin panel before making large data changes.</p>

                <h3>Q: I forgot the admin password</h3>
                <ol class="steps">
                    <li>Run: <code>php tools/generate-password-hash.php yourNewPassword</code></li>
                    <li>Update the hash in <code>config/admin.php</code> (for the fallback config user) or update the <code>admin_users</code> table in the database directly</li>
                </ol>

                <h3>Keyboard Shortcuts</h3>
                <table class="help-table">
                    <thead><tr><th>Shortcut</th><th>Action</th></tr></thead>
                    <tbody>
                        <tr><td><kbd>Enter</kbd> (in search box)</td><td>Trigger search immediately</td></tr>
                        <tr><td><kbd>Esc</kbd> (in a modal)</td><td>Close the modal (also works by clicking × or the overlay)</td></tr>
                        <tr><td><kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>R</kbd></td><td>Hard refresh the browser (clears cached assets)</td></tr>
                    </tbody>
                </table>
            </section>

            <!-- Footer -->
            <div style="text-align:center;padding:20px 0;color:var(--admin-text-light);font-size:.9rem;">
                Idol Stage Timetable v<?php echo htmlspecialchars(APP_VERSION); ?> &nbsp;|&nbsp;
                <a href="index.php" style="color:var(--admin-primary);">← Back to Admin Dashboard</a>
            </div>

        </main>
    </div>
</div>
<script>
document.querySelectorAll('.mobile-toc-menu a').forEach(function(link) {
    link.addEventListener('click', function() {
        var toc = document.getElementById('mobileToc');
        if (toc) toc.classList.remove('open');
    });
});
</script>
</body>
</html>
