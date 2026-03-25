<?php
/**
 * Admin Help Page - English Version
 */
require_once __DIR__ . '/../config.php';
send_security_headers();

require_allowed_ip();
require_login();

$adminUsername = $_SESSION['admin_display_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
$adminRole = $_SESSION['admin_role'] ?? 'admin';
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
<body>
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
            <a href="#header">Header &amp; Account</a>
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
                        <li><a href="#feed-event">Event Feed</a></li>
                        <li><a href="#feed-artist">Artist Feed</a></li>
                    </ul>
                </li>
                <li><a href="#users">Tab: Users</a></li>
                <li><a href="#backup">Tab: Backup</a></li>
                <li><a href="#settings">Tab: Settings</a></li>
                <li><a href="#contact">Tab: Contact</a></li>
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
                <p>The Admin Panel has <strong>9 main tabs</strong>:</p>
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
                    <div>The <strong>👤 Users</strong>, <strong>💾 Backup</strong>, <strong>⚙️ Settings</strong>, and <strong>✉️ Contact</strong> tabs are only visible to users with the <strong>admin</strong> role.</div>
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
                    <li>You will be redirected to the Admin Dashboard</li>
                </ol>

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
                        <tr><td>Username &amp; Role</td><td>Shows the currently logged-in user and their role (admin / agent)</td></tr>
                        <tr><td>🔑 Change Password</td><td>Change your own password (only shown for database-managed users)</td></tr>
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
                        <tr><td>Artist / Group</td><td>Artists associated with this program — type a name and press <kbd>Enter</kbd> or <kbd>,</kbd> to add a chip; click <code>×</code> to remove; autocomplete pulls from the Artists table (🎤 = solo, 🎵 = group); a new artist name not yet in the system will be created automatically when you click <strong>Save</strong></td></tr>
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
                        <tr><td>Active</td><td>Toggle visibility of this event on the public website</td></tr>
                    </tbody>
                </table>

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

                <h3>🌐 Per-Event Timezone <span class="badge-version">v4.0.0</span></h3>
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
            <section class="help-section" id="requests">
                <h2>📝 Tab: Requests</h2>
                <p>
                    <strong>Requests</strong> are submissions from public users asking to
                    <span style="color:#4caf50;font-weight:600;">add a new Program</span> or
                    <span style="color:#2196f3;font-weight:600;">modify an existing Program</span>.
                </p>

                <h3>Request Statuses</h3>
                <table class="help-table">
                    <thead><tr><th>Status</th><th>Meaning</th></tr></thead>
                    <tbody>
                        <tr><td><span style="background:#ff9800;color:#fff;padding:2px 8px;border-radius:10px;font-size:.8rem;">pending</span></td><td>Waiting for review — no action taken yet</td></tr>
                        <tr><td><span style="background:#4caf50;color:#fff;padding:2px 8px;border-radius:10px;font-size:.8rem;">approved</span></td><td>Approved — the program has been created or updated automatically</td></tr>
                        <tr><td><span style="background:#f44336;color:#fff;padding:2px 8px;border-radius:10px;font-size:.8rem;">rejected</span></td><td>Rejected by an admin</td></tr>
                    </tbody>
                </table>

                <h3>Approving / Rejecting a Request</h3>
                <ol class="steps">
                    <li>Click the <strong>👁️ View</strong> button on the request you want to review</li>
                    <li>Check the modal: request type, program data, and submitter information</li>
                    <li>For modification requests, a <strong>Comparison View</strong> shows the original vs. proposed changes side by side</li>
                    <li>Click <strong>✅ Approve</strong> to accept and auto-create/update the Program, or <strong>❌ Reject</strong> to decline</li>
                </ol>

                <div class="callout callout-info">
                    <span class="callout-icon">ℹ️</span>
                    <div>The number of <strong>pending</strong> requests is shown as a red badge on the "Requests" tab to alert you to new items.</div>
                </div>

                <h3>Filtering Requests</h3>
                <ul>
                    <li><strong>Event Filter</strong>: Show requests for a specific event</li>
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
            <section class="help-section" id="import">
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
            <section class="help-section" id="artists">
                <h2>🎤 Tab: Artists</h2>
                <p>Manage all artists in the system. Artists can appear in programs across multiple events (Artist Reuse System).</p>

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

            <!-- Users Tab -->
            <section class="help-section" id="users">
                <h2>👤 Tab: Users <span class="badge-admin">admin only</span></h2>
                <p>Manage all Admin accounts. Only users with the <strong>admin</strong> role can access this tab.</p>

                <h3>User Fields</h3>
                <table class="help-table">
                    <thead><tr><th>Field</th><th>Notes</th></tr></thead>
                    <tbody>
                        <tr><td>Username <span style="color:red">*</span></td><td>Login name (letters, numbers, <code>_</code>, <code>-</code>, <code>.</code> only)</td></tr>
                        <tr><td>Display Name</td><td>The name shown in the admin header</td></tr>
                        <tr><td>Password</td><td>Minimum 8 characters. Leave blank when editing to keep the existing password.</td></tr>
                        <tr><td>Role</td><td><strong>admin</strong> = full access to all tabs | <strong>agent</strong> = Programs management only</td></tr>
                        <tr><td>Active</td><td>Enable / disable the account (inactive users cannot log in)</td></tr>
                    </tbody>
                </table>

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
                    </div>
                </div>
            </section>

            <!-- Backup Tab -->
            <section class="help-section" id="backup">
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
            <section class="help-section" id="settings">
                <h2>⚙️ Tab: Settings <span class="badge-admin">admin only</span></h2>
                <p>Configure global site settings — <strong>Site Title</strong>, <strong>Site Theme</strong>, and <strong>Disclaimer</strong> — for all public-facing pages. Only users with the <strong>admin</strong> role can access this tab.</p>

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
            <section class="help-section" id="contact">
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
                <p>The system has 2 roles:</p>
                <table class="help-table">
                    <thead><tr><th>Feature</th><th><span class="badge-admin">admin</span></th><th><span class="badge-agent">agent</span></th></tr></thead>
                    <tbody>
                        <tr><td>Programs (CRUD)</td><td>✅</td><td>✅</td></tr>
                        <tr><td>Events (CRUD)</td><td>✅</td><td>✅</td></tr>
                        <tr><td>Requests (view / approve / reject)</td><td>✅</td><td>✅</td></tr>
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
