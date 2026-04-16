# 🌸 Idol Stage Timetable

A beautiful, responsive event calendar system designed for idol performances and stage events, featuring a stunning Sakura (桜) theme with multi-language support and powerful filtering capabilities.

**Perfect for**: Concert schedules, festival lineups, idol events, convention programming, and any multi-stage event management.

---

## 🌐 Sites Using This

| Site | URL |
|------|-----|
| **Idol Track** — Live Idol TH | [fordantitrust.com/idoltrack/](https://fordantitrust.com/idoltrack/) |

---

## ✨ Features

### 🎭 For Event Attendees
| Feature | Description | Since |
|---------|-------------|-------|
| 🌸 **Sakura Theme** | Beautiful cherry blossom-themed UI with Japanese aesthetics | v1.0.0 |
| 🌏 **Multi-language** | Full support for Thai, English, and Japanese (日本語) | v1.0.0 |
| 📱 **Mobile Optimized** | Responsive design works perfectly on all devices including iOS | v1.0.0 |
| 📊 **Dual View Modes** | Switch between List and Gantt Chart timeline views (all venue modes) | v1.0.0 |
| 🔍 **Advanced Filtering** | Filter by artists, venues, program types, or search keywords; multi-value support | v1.0.0 |
| 📸 **Save as Image** | Export filtered schedule as PNG image (server-side PHP GD, no external JS) | v3.3.0 |
| 📅 **Export to Calendar** | Download filtered programs as .ics file for Google Calendar, Apple Calendar, etc. | v1.0.0 |
| 📝 **Request Changes** | Submit requests to add or modify programs (rate-limited) | v1.0.0 |
| 🎪 **Multi-Event** | Support for multiple conventions/events with searchable modal card picker (filter by status) | v1.2.0 |
| 📅 **Date Jump Bar** | Fixed-position navigation bar (with ◀ ▶ arrows + mousewheel scroll) to jump quickly to any date | v1.2.1 |
| 🏷️ **Program Types** | Filter programs by type with badge display on rows and Gantt bars | v2.4.0 |
| 🖱️ **Quick Filter Badges** | Click any artist or type badge in results to instantly append that filter | v2.4.1 |
| 🔔 **Live Subscription** | Subscribe to a live webcal:// feed — calendar apps auto-sync when programs change (no re-export needed) | v2.5.0 |
| 🔴 **Live Stream Links** | Platform icon (📷/𝕏/▶️/🔴) + join button displayed on programs that have a stream URL | v2.6.0 |
| 📅 **Calendar View** | Monthly grid view for online/stream schedules (`venue_mode=calendar`); desktop chips + mobile day-panel with dot indicators | v2.7.0 |
| 👤 **Artist Profiles** | Dedicated artist page (`/artist/{id}`) showing all programs grouped by event, group members, and variant names | v3.0.0 |
| 🎪 **Cross-Event Section** | "Also appears in" section before the footer — shows other events the same artists perform at, with clickable profile links | v3.0.0 |
| 🏷️ **Artist Badge Links** | Artist badges in program rows are split pills: left side filters, right `↗` opens artist profile page | v3.0.0 |
| 🔔 **Artist Feed** | Subscribe to a per-artist `webcal://` feed (`/artist/{id}/feed`) — calendar apps auto-sync all programs for that artist across every event; members of a group get a separate feed button for group programs (`?group=1`) | v3.2.0 |
| ⭐ **Anonymous Favorites** | Follow artists without creating an account — UUID v7 + HMAC-signed personal slug stored in localStorage; data persists 365 days with auto-touch | v3.4.0 |
| 📅 **My Upcoming Programs** | Personal page (`/my/{slug}`) showing upcoming programs from all followed artists, grouped by event and date — auto-updates when admin adds new programs | v3.4.0 |
| 🌟 **My Favorites Page** | Personal page (`/my-favorites/{slug}`) listing all followed artists with profile links and one-tap unfollow; ⭐ + 📅 nav shortcuts always shown on both pages (current page highlighted) | v3.4.0 |
| 🎤 **Artist & Group Portal** | Public portal page (`/artists`) listing every group (gradient card + member chips) and solo artist (grid); real-time search (matches member names too); tab filter (All / Groups / Solo) | v3.7.0 |
| 🌐 **Per-event Timezone** | Each event can have its own timezone (e.g. Asia/Tokyo, America/LA); event page shows inline timezone badge `🕐 Asia/Tokyo (Asia/Bangkok)` when client TZ differs; JS auto-appends `(HH:MM–HH:MM local)` range after program times | v4.0.0 |
| 🎨 **Event Color Coding** | My Upcoming Programs page colors each event's program rows in a distinct pastel shade (6 colors cycling) with a matching left-border accent, making it easy to identify which event each program belongs to at a glance | v4.0.3 |
| 🔔 **Telegram Notifications** | Link Telegram account via deep-link (`/start {slug}`); receive per-program push notifications N minutes before each followed artist's program starts + daily summary at 9:00 AM; notifications grouped by event; configurable timing; admin UI settings | v5.0.0 |

### 👨‍💼 For Event Organizers (Admin)
| Feature | Description | Since |
|---------|-------------|-------|
| ⚙️ **Full CRUD** | Create, read, update, and delete programs via web interface | v1.0.0 |
| 📋 **Request Management** | Review and approve user-submitted program requests | v1.0.0 |
| 🔍 **Comparison View** | Side-by-side comparison of original vs. requested changes | v1.0.0 |
| 📦 **Bulk Operations** | Select and edit/delete multiple programs at once (up to 100) | v1.1.0 |
| ✏️ **Bulk Edit** | Update venue, organizer, or Artist/Group for multiple programs simultaneously; tag-input widget with autocomplete | v1.1.0 |
| 🎯 **Flexible Venue** | Add new venues on-the-fly with autocomplete suggestions | v1.1.0 |
| 📊 **Custom Pagination** | Choose 20, 50, or 100 programs per page | v1.1.0 |
| 💳 **Credits Management** | Manage credits/references with full CRUD and bulk operations | v1.1.0 |
| 📤 **ICS Upload** | Upload and preview ICS files before importing | v1.1.0 |
| 🔒 **Secure Access** | Session-based authentication with optional IP whitelist | v1.1.0 |
| 🔐 **CSRF Protection** | Token-based CSRF validation for all admin operations | v1.1.0 |
| 🎪 **Events Management** | Full CRUD for managing multiple events/conventions | v1.2.0 |
| 💾 **Backup/Restore** | Backup and restore database with auto-safety backup before every restore | v1.2.3 |
| 🔐 **DB Auth & Multi-user** | Admin credentials in SQLite, supports multiple admin users | v1.2.4 |
| 🔑 **Change Password** | Change admin password via UI with current password verification | v1.2.4 |
| 👤 **User Management** | Full CRUD for admin users with role assignment | v1.2.5 |
| 🛡️ **Role-Based Access** | Admin (full access) / Agent (programs management only) role system | v1.2.5 |
| 🛠️ **Setup Wizard** | Interactive 6-step install/maintenance wizard with Production Cleanup (`setup.php`); bilingual TH/EN UI | v2.0.0 |
| 🎨 **Per-Event Theme** | Assign a separate color theme to each event (7 themes); global theme fallback | v2.1.1 |
| 📝 **Site Title & Disclaimer** | Customize site title (v2.2.0) and multilingual disclaimer (v2.10.0) from Admin Settings | v2.2.0 |
| 🏷️ **Program Types** | Assign free-text program types with autocomplete; filter by type in admin and public UI | v2.4.0 |
| 🔴 **Live Stream URL** | Set a stream URL per program; validates http/https scheme; badge displayed in admin list | v2.6.0 |
| 📞 **Contact Channels** | Manage contact channels (DB-driven) via Admin › Contact tab; displays on contact page | v2.10.0 |
| 🎤 **Artist Management** | Artists tab — manage artist records, assign group members, add/remove variant names (aliases); tag-input widget with autocomplete for Artist/Group field in program form | v3.0.0 |
| 📋 **Copy Artist** | Copy any artist (solo or group) — pre-filled modal lets you verify/edit name, type, group, and choose which variant aliases to carry over before saving | v3.5.0 |
| 📥 **Bulk Import Artists** | Paste a list of artist names (1 per line, up to 500) with optional group assignment; step-2 result screen shows created / duplicate / error per name | v3.5.0 |
| ☑️ **Bulk Artist Actions** | Select multiple artists with checkboxes; bulk "Add to Group" modal or "Remove from Group" in one click | v3.5.0 |
| 🌐 **Admin Timezone Picker** | Set per-event timezone via dropdown (16 timezones in 4 region groups); empty = use server default | v4.0.0 |
| 🌏 **Bilingual Admin UI** | TH/EN language toggle in Admin panel header and login page — all labels, form hints, table headers, and JS-rendered buttons adapt instantly; preference saved to `localStorage` | v4.2.0 |
| 🎪 **Smart Event Dropdown** | Event filter dropdowns in Admin (Programs, Requests, Credits) grouped by status (Active/Past) with recent 3 events pinned to top; auto-saved on selection | v4.3.0 |
| 🎪 **Events Tab Parity** | Admin Events tab now has full filtering, pagination (20/50/100), and server-side sorting with visual indicators — matching Programs tab; search by name/slug/description; filter by active status, venue mode, date range | v4.4.0 |
| 📥 **Import Next File** | Import workflow improved — summary screen now has "📥 Import ไฟล์ถัดไป" button to clear and import another file without leaving Import tab | v4.4.0 |
| 🎨 **Admin Layout Improvements** | Search box spans full width on its own line; filter dropdowns and buttons wrap to subsequent lines; Add Program/Event buttons on separate full-width lines for better usability | v4.4.0 |
| 🎨 **Admin Settings Sub-tabs** | Reorganized Settings tab with 6 organized sub-tabs: 📝 Site (Title + Theme) • ✉️ Contact (Channels) • 👤 Users • 💾 Backup • 🤖 Telegram • ⚠️ Disclaimer; removed redundant Users/Contact/Backup top-level tabs; added app version badge in header | v5.1.0 |
| 🔔 **Telegram Settings** | Admin › Settings › Telegram Notifications — configure bot token, username, webhook secret, and notification timing; register webhook with Telegram; test webhook connectivity; all settings stored in JSON config | v5.1.0 |
| 📖 **Admin Help Documentation** | Updated admin/help.php (Thai) and admin/help-en.php (English) with comprehensive documentation of Settings Sub-tabs, version badge, and all admin features | v5.1.1 |
| 📚 **How-to-Use Guide Internationalization** | Verified how-to-use.php provides full 3-language support (Thai/English/日本語) through i18n system; version updates automatically from APP_VERSION constant | v5.1.1 |
| 🔄 **Telegram Log Rotation** | Dedicated cron script (`cron/rotate-telegram-logs.php`) — daily rotation of telegram-cron.log to dated archives + automatic cleanup of logs older than 7 days; Apache-level directory protection (`cron/.htaccess`) | v5.2.0 |
| 📋 **Telegram Log Viewer** | Admin › Settings › 🤖 Telegram now includes Activity Log section — dropdown file selector, refresh button, download button, and color-coded log display (green/gray/orange/red for INFO/DEBUG/WARN/ERROR); auto-loads on tab open; shows last 500 lines + total count | v5.3.0 |
| 🔒 **Server-Side HTML Escaping** | `escapeOutputData()` in admin API restored to actually escape with `htmlspecialchars()`; `decodeHtml()` JS helper for form inputs; removed double-escaping from display paths; 2 XSS fixes in error message `innerHTML`; unified `escHtml()` → `escapeHtml()` | v5.3.1 |
| 🤖 **Telegram Bot Commands (Extended)** | 8 new commands: `/tomorrow`, `/week`, `/artists`, `/next`, `/lang`, `/mute`, `/notify`, `/status` · Modified `/today` (event list + count) and `/upcoming` (default 3, supports `/upcoming N` 1–10) · Mute/notify controls with favorites JSON state · All program commands include group member resolution | v5.4.0 |

### ⚡ Technical Highlights
| Feature | Description | Since |
|---------|-------------|-------|
| 🗄️ **SQLite Database** | Lightweight, high-performance storage via PDO SQLite | v1.0.0 |
| 📁 **ICS Compatible** | Import events from standard .ics calendar files; export with `?type=` filter support; live subscription feed (RFC 5545/7986) | v1.0.0 |
| 🛠️ **No Dependencies** | Pure PHP, vanilla JavaScript, no frameworks required | v1.0.0 |
| 🔒 **Security First** | XSS protection, CSRF tokens, rate limiting, IP whitelist, security headers | v1.1.0 |
| 🔄 **Smart Caching** | Data version cache (10 min) · Credits cache (1 hr) · Feed static file cache (1 hr) · Query cache for event + artist pages (1 hr) · **Image PNG cache (1 hr)** — all auto-invalidated on admin writes | v1.1.0 |
| 🐳 **Docker Support** | One-command deployment with Docker Compose | v1.1.0 |
| 🧪 **3064 Unit Tests** | Automated test suite across 15 suites, CI/CD with GitHub Actions (PHP 8.1-8.5) | v1.1.0 |
| 🎪 **Multi-Event** | Support multiple events with per-event venue mode, theme, and caching | v1.2.0 |
| ⚡ **DB Indexes** | Performance indexes for faster queries (2–5× speedup on large datasets) | v2.0.0 |
| 🎤 **Artist Reuse** | `artists` + `program_artists` junction + `artist_variants` — single artist record reused across all events | v3.0.0 |
| 🌐 **Per-event Timezone** | `timezone` column in `events` table; ICS export uses `DTSTART;TZID=` + RFC 5545 VTIMEZONE block; UTC timestamps computed correctly per event TZ | v4.0.0 |

---

## 🗓️ Feature Timeline

> Based on [CHANGELOG.md](CHANGELOG.md). Pure bug-fix patch releases are grouped into ranges.

| Version | Date | Key Features Added |
|---------|------|--------------------|
| **v1.0.0** | 2026-02-09 | Sakura theme · 3-language UI · List + Gantt views · Artist/venue filtering · Save as Image · Export ICS · Admin CRUD · SQLite storage · Session auth + CSRF/XSS protection |
| **v1.1.0** | 2026-02-11 | Docker support · Credits management · Bulk operations (up to 100) · ICS upload + preview import · Security overhaul · 187 automated tests + CI/CD |
| **v1.2.0** | 2026-02-11 | Multi-Event support · Per-convention venue mode · `?event=slug` URL routing |
| **v1.2.1** | 2026-02-12 | Clean URLs · Date Jump Bar · Credits per-event · Full i18n |
| **v1.2.3** | 2026-02-17 | Backup/Restore system with auto-safety backup |
| **v1.2.4** | 2026-02-17 | DB-based admin auth (multi-user) · Change Password UI |
| **v1.2.5** | 2026-02-18 | User Management CRUD · Role-Based Access Control (admin / agent) |
| **v2.0.0** ⚠️ | 2026-02-27 | **Breaking (run migration):** Tables & API actions renamed · Setup Wizard · DB indexes (2–5× speedup) · Login rate limiting · Admin UI mobile responsive |
| **v2.1.x** | 2026-02-27 | Global + Per-Event Theme system (7 themes) · Google Analytics config |
| **v2.2.0** | 2026-02-27 | Site Title editable from Admin Settings |
| **v2.3.0** | 2026-03-02 | Event Contact Email · ICS ORGANIZER redesign · Production Cleanup wizard step |
| **v2.4.x** | 2026-03-02–03 | Program Type system (filter, badges, API) · Clickable filter badges · ICS VALARM reminders · Mobile UI improvements |
| **v2.5.x** | 2026-03-03 | ICS Live Subscription Feed (`webcal://`) · Subscribe button + modal · Feed static file cache (1 hr) |
| **v2.6.x** | 2026-03-04–10 | Live Stream URL (platform icon + Join button) · Date Jump Bar arrows/scroll · Security hardening (7 fixes) |
| **v2.7.x** | 2026-03-11–13 | Calendar View (monthly grid, `venue_mode=calendar`) · Security fixes (inactive event leak + 6 more) · Event name in page title |
| **v2.8.0** | 2026-03-13 | Event Picker Modal (replaces dropdown) — card grid, search, status tabs, mobile bottom-sheet |
| **v2.9.0** | 2026-03-13 | Nav icon buttons · Event Picker on credits page · Credits grouped by event |
| **v2.10.0** | 2026-03-13 | Contact Channels (DB-driven, Admin CRUD) · Multilingual Disclaimer setting |
| **v3.0.0** | 2026-03-18 | Artist Reuse System — `artists` table + `program_artists` junction + `artist_variants` · Artist Profile page (`/artist/{id}`) · Clickable artist badge pills · "Also appears in" cross-event section · Admin Artists tab + tag-input widget |
| **v3.1.0** | 2026-03-19 | Query Cache for event + artist pages (1 hr, auto-invalidated on writes) |
| **v3.2.0** | 2026-03-19 | Artist ICS Feed (`/artist/{id}/feed`) — per-artist webcal subscription across all events |
| **v3.3.0** | 2026-03-19 | Server-side image export (PHP GD, theme-aware PNG, 1 hr cache) · Three-font architecture (Thai/Latin/CJK/Symbol) |
| **v3.4.0** | 2026-03-20 | Anonymous Favorites — follow artists without login · My Favorites page (`/my-favorites/{slug}`) · My Upcoming Programs page (`/my/{slug}`) · Persistent ⭐ + 📅 nav shortcuts on every page |
| **v3.5.0** | 2026-03-20 | Copy Artist modal (pre-filled + variant checkboxes) · Bulk Import Artists (paste list, up to 500, optional group) · Bulk select + Add to Group / Remove from Group · `/my` + `/my-favorites` full i18n (TH/EN/JA) · access denied on missing slug · aligned footer + dual nav buttons |
| **v3.5.1** | 2026-03-20 | My Favorites: separate solo artists / groups sections · A→Z / Z→A sort per section (preference saved to localStorage) · My Upcoming Programs sorted by program datetime across events |
| **v3.5.2** | 2026-03-20 | My Upcoming Programs: mini calendar view with dot indicators · click date → day programs modal · calendar re-renders on language change · how-to-use.php section17 + admin/help.php Artists tab docs updated · FavoritesTest (84 tests → 2036 total, 13 suites) |
| **v3.5.3–3.5.4** | 2026-03-20 | Bug fixes: admin form `'`/`&` HTML entity double-encoding in JSON API responses · admin artist profile link pointing to wrong path (`/admin/artist/{id}`) |
| **v3.6.0** | 2026-03-20 | Personal ICS Feed (`/my/{slug}/feed`) — subscribe to upcoming programs of followed artists via webcal:// · Subscribe button + modal on My Upcoming Programs · `functions/ics.php` shared ICS helpers |
| **v3.6.1–3.6.3** | 2026-03-20 | Personal feed cache shard co-location (`.ics` + `.json` same shard dir, cleanup together) · Admin Events tab: sortable columns (client-side, default Start Date DESC) · My Upcoming Programs includes group programs when a followed solo artist belongs to a group |
| **v3.6.4** | 2026-03-20 | Homepage Calendar View — monthly grid showing events with programs; click date → modal with mini event cards; navigate by month; re-renders on language switch |
| **v3.6.5–3.6.6** | 2026-03-20 | Homepage listing query cache (`cache/query_listing.json`, TTL 1 hr, auto-invalidated on event/program writes) · How-to-use page: 18-item 2-column TOC + section reorder by importance |
| **v3.6.7** | 2026-03-20 | `fav_slug` recovery UX on error screens (Clear from Browser + Create New Favorites buttons) · Silent self-healing in `injectFavNavButton()` (background fetch removes stale slug automatically) |
| **v3.6.8** | 2026-03-21 | Bug fix: `credits.php` was missing `BASE_PATH`, causing `injectFavNavButton()` to fetch `/api/favorites` at root instead of correct subdirectory path → 404 → `fav_slug` silently cleared from localStorage |
| **v3.6.9** | 2026-03-22 | Now-playing highlight on My Upcoming Programs — programs currently in progress are highlighted on page load |
| **v3.6.10** | 2026-03-23 | Event listing card and homepage calendar modal: dates displayed below event name (column layout) so long names get full width |
| **v3.6.11** | 2026-03-24 | i18n fixes: 404 page multilingual · filter empty-state text translated · `my/fav.copyUrl` translated in TH/JA · JA grammar fixes · stale `"your event"` placeholders removed · `window.currentLang` sync fix · homepage calendar day modal re-renders on language switch · `appLangChange` custom event · `eventPicker.viewing` key added |
| **v3.6.12** | 2026-03-25 | Admin Artists: group rows now show yellow member-count badge (e.g. `3 คน`) next to the `กลุ่ม` badge — count via server-side subquery |
| **v3.7.0** | 2026-03-25 | Artist & Group Portal (`/artists`) — gradient group cards with member chips · solo artist grid · real-time search (matches member names) · tab filter (All/Groups/Solo) · `cache/query_portal.json` (1 hr) · 🎤 nav link before Credits on homepage |
| **v4.0.0** ⚠️ | 2026-03-25 | **Run migration:** Per-event Timezone — `timezone` column in `events` · ICS/Feed use `DTSTART;TZID=` + RFC 5545 VTIMEZONE block · event page timezone badge + JS local-time conversion · image export timezone label · Admin timezone picker (16 options) · `DEFAULT_TIMEZONE` constant · 67 new TimezoneTest (→ 2523 total) |
| **v4.0.1** | 2026-03-25 | Timezone badge changed to inline text `🕐 Asia/Tokyo (Asia/Bangkok)` · local time shows full range `(HH:MM–HH:MM local)` · language-switch re-renders local time labels · calendar view (chip, day panel, detail modal) all show local time range · `data-utc-end` attribute added to program time spans |
| **v4.0.2** | 2026-03-26 | Bug fixes: ICS export was silently dropping `type[]` filter · ICS export artist filter used raw `categories` text instead of `program_artists` junction table, causing artist-filtered exports to miss programs; both now mirror `index.php` logic |
| **v4.0.3** | 2026-03-26 | My Upcoming Programs: program rows are color-coded by event (6 pastel colors cycling with left-border accent); applies to both the main list and mini-calendar day modal |
| **v4.1.0** | 2026-04-01 | Cross-day programs — separate end-date field in admin form; `+N` superscript badge shown after end time in list view, calendar chips, day panel, and detail modal when a program ends on a later date |
| **v4.2.0** | 2026-04-04 | Bilingual Admin UI — TH/EN toggle in Admin panel header and login page; `admin/js/admin-i18n.js` with 200+ keys per language; all static labels (`data-i18n`), form hints, placeholders, and JS-rendered buttons fully translated |
| **v4.3.0** | 2026-04-06 | Smart Event Dropdown Filtering — event selectors grouped by status (Active/Past), recent 3 events pinned to top with localStorage persistence, auto-invalidate on selection; Event search support in Admin Events tab (LIKE search on name/slug/description) |
| **v4.4.0** | 2026-04-06 | Events Tab Feature Parity — Admin Events tab gets filtering (active status, venue mode, date range), pagination (20/50/100), and server-side sorting with visual indicators; "Import ไฟล์ถัดไป" button on import summary; Admin UI layout: search spans full width, filters/buttons wrap to next lines, Add buttons on separate lines; N+1 query fix with event_count subquery |
| **v4.5.0** | 2026-04-11 | Standardized SUMMARY format in ICS feeds (export, feed, my-feed) — `Program Title [Event Name]` · Fixed artist feed showing wrong event name for multi-event artists via `$eventNameMap` |
| **v4.5.1** | 2026-04-12 | Bug fix: Admin filter state persistence — event filter dropdown now preserves selected value when reloading data after program edit/save (save/restore logic in `populateEventSelect()`) |
| **v5.0.0** | 2026-04-14 | **Telegram Bot Notifications** — users link Telegram via `/start {slug}` deep-link or manual entry (2 methods in modal); per-program push notifications N min before start + daily summary at 9:00 AM; in-bot language selection; `cron/send-telegram-notifications.php` every 15 min; secure HMAC-signed slug; no DB schema changes · **Refinements**: fixed shard directory discovery in webhook handler + cron script · simplified modal UI (removed slug field, 2-option layout) · cleaned up debug code (removed verbose logging) · **2523 tests pass** |
| **v5.1.0** | 2026-04-14 | **Admin UI Settings Sub-tabs** — reorganized Settings tab with 6 nested sub-tabs (📝 Site • ✉️ Contact • 👤 Users • 💾 Backup • 🤖 Telegram • ⚠️ Disclaimer); removed redundant Users/Contact/Backup top-level tabs · **App Version Badge** — header displays current version (e.g. `v5.1.0`) for quick reference without opening config |
| **v5.1.1** | 2026-04-14 | **Admin Help Documentation** — comprehensive bilingual help in `admin/help.php` (Thai) + `admin/help-en.php` (English); documented Settings Sub-tabs structure and all admin features · **How-to-Use Verification** — confirmed full 3-language i18n support (Thai/English/日本語) in how-to-use.php; footer version auto-updates from APP_VERSION constant |
| **v5.2.0** | 2026-04-14 | **Telegram Log Rotation Cron Script** — `cron/rotate-telegram-logs.php` daily rotation + 7-day cleanup; renames `cache/logs/telegram-cron.log` to `telegram-cron-YYYY-MM-DD.log` · automatic deletion of archives >7 days old · `cron/.htaccess` Apache-level protection |
| **v5.3.0** | 2026-04-14 | **Telegram Log Viewer in Admin UI** — new Activity Log section in Admin › Settings › 🤖 Telegram; file dropdown (active + dated archives), refresh/download buttons, color-coded output (INFO/DEBUG/WARN/ERROR); API endpoints `telegram_log_get` + `telegram_log_download`; displays last 500 lines + total count |
| **v5.3.1** | 2026-04-14 | **Full Server-Side HTML Escaping** — restored `escapeOutputData()` to escape with `htmlspecialchars()`; added `decodeHtml()` JS helper for form inputs; removed double-escaping from 40+ display paths; fixed 2 XSS bugs in error `innerHTML`; unified `escHtml()` → `escapeHtml()` · **2523 tests pass** |
| **v5.4.0** | 2026-04-15 | **Extended Telegram Bot Commands** — `/tomorrow`, `/week`, `/artists`, `/next` (new); `/lang`, `/mute N`, `/notify on\|off`, `/status` (notification controls); modified `/today` (event list + count) and `/upcoming [N]` (default 3, max 10); group member resolution for all program commands · **3064 tests pass** |
| **v5.5.0** | 2026-04-15 | **5 New Themes** — Crimson 🔴 · Teal 🩵 · Rose 🌹 · Amber 🌟 · Indigo 🔷; theme system expanded from 7 → 12 themes; each theme includes CSS variables, image export GD palette, Admin picker gradient preview, and per-event override support |
| **v5.5.1** | 2026-04-15 | **4 bug fixes** — (1) Telegram `api/telegram.php` group resolution: parent group ID instead of siblings; (2) Telegram cron timezone: `strftime('%s')` treated Bangkok datetimes as UTC causing 7-hour notification delay — fixed with datetime string `BETWEEN`; (3) Telegram cron group resolution: parent-group lookup applied to cron; (4) Admin backup timestamps: `gmdate()` → `date()` so filenames and UI times show Bangkok time |
| **v5.5.2** | 2026-04-16 | **Bug fix: Admin dropdowns showing HTML entities** — `populateEventSelect()` and artist group selects used `option.textContent = meta.name` directly; after v5.3.1 server-side escaping, names like `Idol's` were stored as `Idol&#039;s` in JSON and displayed literally; fixed by wrapping all 6 `option.textContent` assignments with `decodeHtml()` |

---

## 📚 Documentation Index

### 🚀 Getting Started
| File | Description |
|------|-------------|
| **[README.md](README.md)** | This file — features, quick start, configuration, testing summary |
| [INSTALLATION.md](INSTALLATION.md) | Detailed installation for Apache/Nginx/PHP built-in server |
| [DOCKER.md](DOCKER.md) | Docker & Docker Compose deployment guide |
| [SETUP.md](SETUP.md) | Interactive 6-step Setup Wizard (`setup.php`) guide |

### 📖 Reference
| File | Description |
|------|-------------|
| [API.md](API.md) | All API endpoints with request/response examples (Public, Request, Admin) |
| [ICS_FORMAT.md](ICS_FORMAT.md) | ICS file format reference — fields, escaping, examples |
| [PROJECT-STRUCTURE.md](PROJECT-STRUCTURE.md) | File structure, DB schema, function list, **complete tools/ list** |

### 🔒 Policy & Contributing
| File | Description |
|------|-------------|
| [SECURITY.md](SECURITY.md) | Security policy, deployment checklist, built-in protections |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Contribution guidelines |
| [CHANGELOG.md](CHANGELOG.md) | Full version history |

### 🧪 Testing
| File | Description |
|------|-------------|
| [TESTING.md](TESTING.md) | Manual QA testing checklist (129 test cases) |
| [tests/README.md](tests/README.md) | Automated test suite — how to write/run tests, assertions API, CI/CD |

---

## 📑 In This File

- [Feature Timeline](#️-feature-timeline)
- [Quick Start](#-quick-start)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Usage](#-usage)
- [Admin Panel](#️-admin-panel)
- [Configuration](#-configuration)
- [Project Structure](#-project-structure)
- [Testing](#-testing)
- [Contributing](#-contributing)
- [License](#-license)
- [Support](#-support)

---

## 🚀 Quick Start

### 🐳 Method 1: Docker (Recommended)

```bash
# 1. Navigate to project folder
cd stage-idol-calendar

# 2. Start with Docker Compose
docker-compose up -d

# 3. Open browser: http://localhost:8000
```

**That's it!** 🎉 See [DOCKER.md](DOCKER.md) for more options.

### 💻 Method 2: PHP Built-in Server

```bash
# 1. Navigate to project folder
cd stage-idol-calendar

# 2. Start PHP server
php -S localhost:8000

# 3. Open setup wizard: http://localhost:8000/setup.php
# 4. Follow the 6-step wizard to initialize the database
```

### ⚡ For Better Performance (Recommended)

Import ICS files to SQLite for 10–20x faster page loads:

```bash
php tools/import-ics-to-sqlite.php
```

### 🎯 Core Features at a Glance

| Feature | How to Use |
|---------|-----------|
| 🔍 **Search** | Type artist/event name in search box |
| 🏷️ **Filter by Artist** | Check artist checkboxes |
| 🏢 **Filter by Venue** | Check venue checkboxes |
| 🏷️ **Filter by Type** | Check program type checkboxes |
| 🖱️ **Quick Filter** | Click any badge in results to append filter |
| 📊 **Switch Views** | Toggle List / Gantt Chart (or Calendar in calendar mode) |
| 📅 **Jump to Date** | Use the fixed Date Jump Bar (arrows or mousewheel to scroll) |
| 📸 **Save Image** | Click "Save as Image" button |
| 📅 **Export Calendar** | Click "Export to Calendar" button |
| 🔔 **Subscribe** | Click "Subscribe" button for live webcal:// calendar link |
| 📝 **Request Changes** | Click "Request to Add Event" or ✏️ button |
| 🎪 **Switch Event** | Click the grid-dots icon (top-left) to open Event Picker modal |
| ⭐ **Follow Artist** | Tap ☆ Follow on any artist profile page — no account needed |
| 📅 **My Upcoming** | Bookmark `/my/{slug}` for your personalized upcoming programs page |

---

## 🔧 Requirements

- **PHP 8.1+** (tested on PHP 8.1, 8.2, 8.3, 8.4, 8.5) with PDO SQLite extension
- **Web Server** (Apache, Nginx, or PHP built-in server)
- Modern web browser with JavaScript enabled

---

## 📦 Installation

| Method | Best For | Guide |
|------|-----------|-------|
| 🐳 **Docker** | Production, easiest setup | [DOCKER.md](DOCKER.md) |
| 🧙 **Setup Wizard** | All types of fresh install | [SETUP.md](SETUP.md) |
| 💻 **PHP Built-in** | Development/Local | [INSTALLATION.md](INSTALLATION.md) |
| 🌐 **Apache/Nginx** | Production server | [INSTALLATION.md](INSTALLATION.md) |

**Docker (fastest):**
```bash
docker-compose up -d
# http://localhost:8000
```

**PHP Built-in:**
```bash
php -S localhost:8000
# Then open http://localhost:8000/setup.php
```

See [INSTALLATION.md](INSTALLATION.md) for full details.

---

## 📖 Usage

### Viewing Events

The calendar displays all events with two view modes:

1. **List View** - Traditional table layout with full event details
2. **Gantt Chart View** - Visual timeline showing event overlaps across venues

Toggle between views using the switch below the search controls.

### Filtering Events

- **Text Search**: Click the search box (auto-selects), type artist/event name
- **Artist Filter**: Check one or more artists
- **Venue Filter**: Check one or more venues
- **Clear Filters**: Click the ✕ button in search box or remove individual tags

### Exporting Data

- **📸 Save as Image**: Downloads the filtered schedule as PNG
- **📅 Export to Calendar**: Downloads filtered events as .ics file
- **🔔 Subscribe to Feed**: Click "Subscribe" to get a live `webcal://` link — paste into Apple Calendar, Google Calendar, or Thunderbird and the calendar app will auto-sync whenever programs are updated. Outlook users use the `https://` URL via "Add calendar › Subscribe from web"

### Requesting Changes

Users can request to add new events or modify existing ones:

1. Click **"📝 Request to Add Event"** button to add new event
2. Click **"✏️"** button next to any event to request modifications
3. Fill in the form and submit
4. Admins will review and approve/reject requests

**Rate Limit**: 10 requests per hour per IP address

---

## ⚙️ Admin Panel

### Accessing Admin

1. Navigate to `/admin/`
2. Login with configured credentials
3. Default credentials are set in [config/admin.php](config/admin.php)

### Admin Capabilities

**Events Tab:**
- Create, edit, and delete events (with convention assignment)
- Bulk operations (select and delete/edit up to 100 events)
- Filter by venue or convention
- Pagination for large event lists (20/50/100 per page)

**Requests Tab:**
- View pending user requests
- Compare original vs. requested changes (side-by-side)
- Approve or reject requests
- Filter by status and convention

**Credits Tab:**
- Create, edit, and delete credits/references
- Bulk delete multiple credits
- Search, sort, and pagination
- Manage title, link, description, and display order

**Conventions Tab:**
- Create, edit, and delete conventions/events
- Configure name, slug, dates, venue mode, active status
- Per-convention venue mode (multi/single/calendar)

**Artists Tab** (admin + agent):
- View all artists with program counts and number of events they appear in
- Manage variant/alias names per artist via Variants modal (add/remove)
- Artist name links to public profile page (`/artist/{id}`)
- ICS import auto-links CATEGORIES field to artist records via name match and variant lookup

**Users Tab** (admin role only):
- Create, edit, and delete admin users
- Assign roles: `admin` (full access) or `agent` (events management only)
- Toggle active/inactive status
- Safety: cannot delete self, cannot change own role, must keep 1+ admin

**Backup Tab** (admin role only):
- Create database backup (stored on server in `backups/`)
- Download backup files to local machine
- Restore from server backup or upload .db file
- Auto-backup created before every restore operation
- Delete old backup files

**Contact Tab** (admin role only):
- Create, edit, and delete contact channels (name, URL, description) stored in SQLite
- Channels appear on the public contact page; empty state shown when no channels configured

**Settings Tab** (admin role only):
- Set **Site Title** — displayed in browser tab, page header, and ICS export (saved to `cache/site-settings.json`)
- Set **Site Theme** — choose from 7 color themes: Sakura, Ocean, Forest, Midnight, Sunset, Dark, Gray
- Set **Disclaimer** — multilingual disclaimer text (TH/EN/JA); displayed on public pages

**Authentication & Roles:**
- Admin credentials stored in SQLite (`admin_users` table) - supports multiple users
- Role-based access: `admin` sees all tabs; `agent` sees Programs, Requests, ICS Import, Credits, Events, Artists
- Change Password button in admin header (current password required)
- Fallback to `config/admin.php` if `admin_users` table doesn't exist

### Initial Setup

#### Option A: Setup Wizard (Recommended) 🧙

Open `http://localhost:8000/setup.php` and follow the 6-step wizard:

1. **System Requirements** — checks PHP version, extensions, permissions
2. **Directories** — creates `data/`, `cache/`, `backups/`, `ics/`
3. **Database** — creates all tables and seeds admin user (auto-login)
4. **Import Data** — imports `.ics` files from `ics/` folder
5. **Admin & Security** — change default password, add indexes, lock setup

See [SETUP.md](SETUP.md) for detailed guide.

#### Option B: Manual CLI

```bash
cd tools

# Create core tables
php import-ics-to-sqlite.php
php migrate-add-requests-table.php
php migrate-add-credits-table.php
php migrate-add-events-meta-table.php
php migrate-add-admin-users-table.php
php migrate-add-role-column.php
php migrate-rename-tables-columns.php
php migrate-add-indexes.php

# Add feature columns
php migrate-add-event-email-column.php
php migrate-add-program-type-column.php
php migrate-add-stream-url-column.php
php migrate-add-theme-column.php
php migrate-add-contact-channels-table.php
php migrate-add-artist-variants-table.php
```

**(Optional) Enable IP whitelist** in `config/admin.php`:
```php
define('ADMIN_IP_WHITELIST_ENABLED', true);
define('ADMIN_ALLOWED_IPS', [
    '127.0.0.1',
    '192.168.1.0/24',  // Your office network
]);
```

For more details, see [INSTALLATION.md](INSTALLATION.md) and [SETUP.md](SETUP.md).

---

## 🔌 API Documentation

The system has 3 API groups:

| API | URL | Auth | Description |
|-----|-----|------|-------------|
| **Public** | `/api.php` | ❌ | Programs, organizers, locations, events list |
| **Request** | `/api/request.php` | ❌ | User request submission (rate limited) |
| **Admin** | `/admin/api.php` | ✅ Session + CSRF | Full CRUD for all resources |

**Public API example:**
```http
GET /api.php?action=programs&event=idol-stage-feb-2026
```

**Admin API requires:**
- Session cookie (login at `/admin/login`)
- Header `X-CSRF-Token` for POST/PUT/DELETE

See **[API.md](API.md)** for complete endpoint documentation with request/response examples.

---

## 🎨 Configuration

### Changing Version (Cache Busting)

Edit [config/app.php](config/app.php):
```php
define('APP_VERSION', '3.1.0'); // Change this to force cache refresh
define('APP_NAME', 'Idol Stage Timetable'); // Default site title (fallback if not set via admin)
```

The site title can also be changed live from **Admin → Settings → Site Title** without editing code.

### Multi-Event Mode

Enable multiple conventions support in [config/app.php](config/app.php):

```php
define('MULTI_EVENT_MODE', true);       // Enable multi-event support
define('DEFAULT_EVENT_SLUG', 'default'); // Default convention slug
```

Access events via URL: `/event/slug` (e.g., `/event/idol-stage-feb-2026`)

### Venue Mode

Toggle between multi-venue and single-venue layouts in [config/app.php](config/app.php):

```php
define('VENUE_MODE', 'multi');      // Multiple venues: shows venue filter, Gantt view, venue columns
define('VENUE_MODE', 'single');     // Single venue: hides venue filter, Gantt view, venue columns
define('VENUE_MODE', 'calendar');   // Calendar view: monthly grid layout for online/stream schedules
```

| Feature | `multi` | `single` | `calendar` |
|---------|---------|----------|------------|
| Venue filter (checkboxes) | Visible | Hidden | Hidden |
| List/Timeline toggle switch | Visible | Visible | Hidden |
| Monthly grid calendar | Hidden | Hidden | Visible |
| Venue column in event table | Visible | Hidden | Hidden |
| Venue column in admin table | Visible | Hidden | Hidden |

### Cache Configuration

Edit [config/cache.php](config/cache.php):
```php
// Data version cache (footer display)
define('DATA_VERSION_CACHE_TTL', 600); // 10 minutes

// Credits cache (credits.php page)
define('CREDITS_CACHE_TTL', 3600); // 1 hour
```

**Cache files** (auto-created in `cache/` directory):
- `cache/data_version.json` - Last update timestamp (ETag for subscription feed)
- `cache/credits.json` - Credits data with timestamp
- `cache/site-theme.json` - Active site theme (set by admin)
- `cache/site-settings.json` - Site settings: custom title, disclaimer (set by admin)
- `cache/feed_*.ics` - Static ICS feed cache (1 hour TTL; served directly, bypasses SQLite)

**Manual cache clear**:
```bash
# Clear all cache
rm cache/*.json

# Clear credits cache only
rm cache/credits.json
```

### Customizing Theme Colors

Edit `styles/common.css`:
```css
:root {
    --sakura-light: #FFB7C5;
    --sakura-medium: #F48FB1;
    --sakura-dark: #E91E63;
    --sakura-deep: #C2185B;
    --sakura-gradient: linear-gradient(135deg, #FFB7C5 0%, #E91E63 100%);
}
```

### Adding/Editing Translations

Edit `js/translations.js` to add or modify translations for Thai, English, and Japanese.

### ICS File Format

The system supports standard iCalendar (.ics) format:

Place `.ics` files in the `ics/` folder and run the import script.

For details on ICS file structure for import/export, program types, stream URLs, and examples:

See **[ICS_FORMAT.md](ICS_FORMAT.md)** for complete ICS format reference guide.

---

## 📁 Project Structure

```
stage-idol-calendar/
├── index.php / api.php / artist.php / ...  # Root PHP pages (artist.php = Artist Profile v3.0.0)
├── config/          Configuration constants (app, admin, security, database, cache)
├── functions/       Helper functions (helpers, cache, admin, security)
├── styles/ / js/   CSS + JavaScript (Sakura theme, translations)
├── data/            SQLite database (calendar.db, .setup_locked)
├── backups/         Database backups (auto-created)
├── cache/           Cache files (data_version, credits, login_attempts)
├── ics/             ICS source files
├── api/             Public API (request.php)
├── admin/           Admin panel (login.php, index.php, api.php)
├── tools/           CLI migration scripts
├── tests/           2523 automated tests (14 suites)
└── *.md             Documentation
```

See **[PROJECT-STRUCTURE.md](PROJECT-STRUCTURE.md)** for details on all files, file relationships, and function descriptions.

---

## 🔒 Security

Security is a top priority for this project.

### Security Features

#### 🛡️ Input Protection
- **XSS Prevention**: Comprehensive input sanitization with dedicated functions
  - `sanitize_string()` - Removes null bytes, trims, limits length
  - `sanitize_string_array()` - Handles array inputs with item limits
  - `get_sanitized_param()` - Safe GET parameter retrieval
  - `get_sanitized_array_param()` - Safe array parameter retrieval
- **Output Encoding**: All user-generated content properly escaped before display
- **JSON Security**: Safe JSON encoding with `JSON_HEX_*` flags for HTML attributes

#### 🔐 Session Security
- **Session Timeout**: Automatic logout after 2 hours of inactivity (configurable in `config/admin.php`)
- **Timing Attack Prevention**: Constant-time comparison (`hash_equals()`) for username/password checks
- **Session Fixation Prevention**: Session ID regeneration on login and logout
- **Secure Cookies**: httponly, secure, SameSite=Strict attributes
- **Race Condition Prevention**: Safe session start with status checks

#### 🔌 API Security
- **CSRF Protection**: Token-based validation for all state-changing operations (POST, PUT, DELETE)
- **SQL Injection Prevention**: PDO prepared statements for all database queries
- **Authentication Required**: All admin endpoints protected by login check
- **IP Whitelist**: Optional IP restriction for admin panel (configurable in `config/admin.php`)

#### 🌐 General Security
- **Security Headers**: X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy, Permissions-Policy
- **Rate Limiting**: Prevents abuse of user request submission (10 requests/hour/IP)
- **Input Validation**: Length limits, null byte removal, array size limits
- **Error Handling**: Safe error messages in production mode (hides details)

### Reporting Security Issues

If you discover a security vulnerability, please email the author directly instead of opening a public issue. See [CONTRIBUTING.md](CONTRIBUTING.md) for contact information.

---

## 🛠️ Development

### Developer Tools

Located in `tools/` folder. Common tools:

| Tool | Purpose |
|------|---------|
| `import-ics-to-sqlite.php` | Import ICS files to SQLite database |
| `update-version.php` | Bump APP_VERSION across 9 files (`php tools/update-version.php X.Y.Z`) |
| `generate-password-hash.php` | Generate bcrypt password hash for admin |
| `debug-parse.php` | Debug ICS file parsing |

For the complete tools list including all migration scripts and their descriptions, see **[PROJECT-STRUCTURE.md — tools/](PROJECT-STRUCTURE.md#️-tools)**.

### Running Tests

```bash
# Run all 2523 automated tests
php tests/run-tests.php

# Run specific suite
php tests/run-tests.php SecurityTest

# Quick pre-commit tests
quick-test.bat          # Windows
./quick-test.sh         # Linux/Mac
```

See [Testing](#-testing) section for full details.

### Docker Development

```bash
# Development mode (live reload)
docker-compose -f docker-compose.dev.yml up

# Run tests in container
docker exec idol-stage-calendar php tests/run-tests.php
```

See [DOCKER.md](DOCKER.md) for complete Docker guide.

### Database Management

See [PROJECT-STRUCTURE.md](PROJECT-STRUCTURE.md) for database schema, migration guide, and performance benchmarks.

---

## 💡 Pro Tips

1. **Multiple ICS Files**: Put as many `.ics` files as you want in `ics/` — the system combines them all.
2. **File Names Don't Matter**: `event1.ics`, `concert.ics`, `xyz.ics` — all work the same.
3. **Cache Busting**: If changes don't appear, edit `APP_VERSION` in `config/app.php`.
4. **Performance**: Use `php tools/import-ics-to-sqlite.php` for large datasets — 10–20x faster.
5. **Backup**: Keep your `.ics` files — they're your source of truth.
6. **Quick Tests**: Run `php tests/run-tests.php` (or `quick-test.bat` on Windows) before deploying.

---

## 🐛 Troubleshooting

### Events Not Showing

- Check that `.ics` files exist in `ics/` folder
- Run `php tools/import-ics-to-sqlite.php` to import
- Verify file permissions allow PHP to read files

### Cache Not Updating

- Change `APP_VERSION` in `config.php`
- Clear browser cache (Ctrl+F5 or Cmd+Shift+R)
- If using Cloudflare, purge cache

### Image Export Not Working

- Ensure PHP GD extension is enabled: `php -m | grep gd`
- Place a TrueType font in `fonts/` directory (see `fonts/README.md`)
- Open browser console to check for errors
- Ensure popup blocker is not blocking download

### Database Errors

- Verify PHP has SQLite extension enabled: `php -m | grep pdo_sqlite`
- Check database file permissions: `chmod 644 data/calendar.db`
- Try deleting `data/calendar.db` and re-running import

---

### Quick Guidelines

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

### Built With

- **Backend**: PHP 8.1+ (tested on 8.1, 8.2, 8.3, 8.4, 8.5), SQLite
- **Frontend**: Vanilla JavaScript, CSS3
- **Image Export**: PHP GD (server-side PNG generation, TrueType font support)
- **Design**: Sakura (桜) theme with Material Design influences

### Use Case Example

This project was originally created for **Idol Stage Event** to manage idol stage schedules across multiple venues.

---

## 🧪 Testing

### Automated Test Suite

The project includes **2523 automated unit tests** covering all critical functionality:

**Test Suites** (cumulative count = tests reported when running that suite alone):
- 🔒 **SecurityTest** (7) - Input sanitization, XSS protection, SQL injection prevention
- 💾 **CacheTest** (17) - Cache creation, invalidation, TTL, fallback behavior
- 🔐 **AdminAuthTest** (38) - Authentication, session management, timing attack resistance, DB auth, change password
- 📋 **CreditsApiTest** (49) - Database CRUD operations, bulk operations
- 🔗 **IntegrationTest** (100) - File structure, configuration, full workflows, API endpoints
- 👤 **UserManagementTest** (119) - Role column schema, role helpers, user CRUD, permission checks
- 🎨 **ThemeTest** (143) - Theme system, get_site_theme(), per-event theme, CSS files, admin API, public pages
- 📝 **SiteSettingsTest** (157) - Site title: get_site_title(), cache read/write, fallbacks, admin API, public page injection
- 📧 **EventEmailTest** (176) - events.email schema, CRUD, validation logic, ICS ORGANIZER fallback
- 🏷️ **ProgramTypeTest** (211) - programs.program_type schema, CRUD, public API type filter, admin API, index.php UI, translations, admin v2.4.2 categories column
- 🔔 **FeedTest** (291) - icsEscape(), icsFold() UTF-8 folding, CATEGORIES delimiter, ORGANIZER logic, ETag format, invalidate_data_version_cache(), feed.php RFC 5545/7986 compliance, static file cache, feed SUMMARY/header escaping
- 🔴 **StreamUrlTest** (322) - stream_url schema, CRUD, public API, admin API, ICS import/export, XSS prevention
- ⭐ **FavoritesTest** (406) - config constants, UUID v7 format/uniqueness, HMAC, slug build/parse/tamper resistance, file I/O (write→read roundtrip, sharded path), api/favorites.php actions, my-favorites.php solo/group split + sort, my.php mini calendar + day modal, translations 3-language coverage, common.js nav injection, artist.php follow/unfollow, .htaccess routing
- 🌐 **TimezoneTest** (473) - events.timezone schema, migration idempotency, DEFAULT_TIMEZONE constant, get_event_timezone() priority logic, icsOffsetString() ±HHMM format, icsVtimezone() RFC 5545 VTIMEZONE block (STANDARD + DAYLIGHT auto-detection), UTC timestamp computation, DB CRUD, export.php TZID format, feed.php TZID format, index.php timezone injection, admin API timezone picker, translations.js keys, common.js initTimezoneDisplay(), CSS classes, setup.php integration

**Run All Tests:**
```bash
php tests/run-tests.php
```

**Run Specific Suite:**
```bash
php tests/run-tests.php SecurityTest
php tests/run-tests.php CacheTest
php tests/run-tests.php IntegrationTest
```

**Quick Pre-Commit Tests:**
```bash
# Windows
quick-test.bat

# Linux/Mac
./quick-test.sh
```

**Test Documentation:**
- [tests/README.md](tests/README.md) - Testing guide
- [TESTING.md](TESTING.md) - Manual testing checklist

**CI/CD Integration:**

GitHub Actions automatically run tests on every push/PR across **PHP 8.1, 8.2, 8.3, 8.4, and 8.5**.

```yaml
# .github/workflows/tests.yml included
strategy:
  matrix:
    php-version: ['8.1', '8.2', '8.3', '8.4', '8.5']
```

✅ **All 2523 tests pass on PHP 8.1, 8.2, 8.3, 8.4, and 8.5**

**Expected Output:**
```
✅ ALL TESTS PASSED

Total: 2523 tests
Passed: 2523
Pass Rate: 100.0%
```

For detailed testing documentation, see [tests/README.md](tests/README.md) and [TESTING.md](TESTING.md).

---

## 📜 Changelog

See [CHANGELOG.md](CHANGELOG.md) for full version history and release notes.

**Current Version**: 5.5.2

---

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

---

## 📞 Support

- **Documentation**: [README](README.md) | [Setup](SETUP.md) | [Install](INSTALLATION.md) | [API](API.md) | [Structure](PROJECT-STRUCTURE.md)
- **Issues**: [GitHub Issues](https://github.com/fordantitrust/stage-idol-calendar/issues)
- **Twitter**: [@FordAntiTrust](https://x.com/FordAntiTrust)

---

### ❤️ Donation & Sponsorship

If this project has been useful to you or your event, consider supporting its continued development:

- **GitHub Sponsors**: Sponsor the project directly on GitHub
- **Sponsor Badge**: Organizations that sponsor the project can have their name/logo featured in the Credits section

Your support helps keep the project actively maintained and free for the community.

### 🏢 White-label & Premium Support

Need a custom deployment for your event? We offer:

- **Managed Installation** — Full setup on your own domain or hosting, configured for your event(s)
- **Custom Branding** — Logo, color theme, site title, and contact channels tailored to your organization
- **Ongoing Support** — Admin training, program data import assistance, and priority issue resolution
- **Telegram Bot Setup** — Complete Telegram notification bot configuration for your attendees

Contact us via Twitter [@FordAntiTrust](https://x.com/FordAntiTrust) to discuss your requirements.

---

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

**TL;DR**: You can use, modify, and distribute this software freely, even for commercial purposes.

---

<div align="center">

🌸 **Idol Stage Timetable** 🌸

Made with ❤️ for event organizers and idol fans everywhere

[⭐ Star this repo](https://github.com/fordantitrust/stage-idol-calendar) | [🐛 Report Bug](https://github.com/fordantitrust/stage-idol-calendar/issues) | [✨ Request Feature](https://github.com/fordantitrust/stage-idol-calendar/issues)

</div>
