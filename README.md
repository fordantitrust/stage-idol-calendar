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
| 📸 **Save as Image** | Export filtered schedule as PNG image (server-side PHP GD, no external JS) | v3.3.0 |
| ⭐ **Anonymous Favorites** | Follow artists without creating an account — UUID v7 + HMAC-signed personal slug stored in localStorage; data persists 365 days with auto-touch | v3.4.0 |
| 📅 **My Upcoming Programs** | Personal page (`/my/{slug}`) showing upcoming programs from all followed artists, grouped by event and date — auto-updates when admin adds new programs | v3.4.0 |
| 🌟 **My Favorites Page** | Personal page (`/my-favorites/{slug}`) listing all followed artists with profile links and one-tap unfollow; ⭐ + 📅 nav shortcuts always shown on both pages (current page highlighted) | v3.4.0 |
| 🎤 **Artist & Group Portal** | Public portal page (`/artists`) listing every group (gradient card + member chips) and solo artist (grid); real-time search (matches member names too); tab filter (All / Groups / Solo) | v3.7.0 |
| 🌐 **Per-event Timezone** | Each event can have its own timezone (e.g. Asia/Tokyo, America/LA); event page shows inline timezone badge `🕐 Asia/Tokyo (Asia/Bangkok)` when client TZ differs; JS auto-appends `(HH:MM–HH:MM local)` range after program times | v4.0.0 |
| 🎨 **Event Color Coding** | My Upcoming Programs page colors each event's program rows in a distinct pastel shade (6 colors cycling) with a matching left-border accent, making it easy to identify which event each program belongs to at a glance | v4.0.3 |
| 🔔 **Telegram Notifications** | Link Telegram account via deep-link (`/start {slug}`); receive per-program push notifications N minutes before each followed artist's program starts + daily summary at 9:00 AM; notifications grouped by event; configurable timing; admin UI settings | v5.0.0 |
| 🖼️ **Event Pictures Gallery** | Per-event photo gallery displayed on the event page; natural aspect ratios (no forced crop); 4 selectable layout templates per event: `grid1` (1 col), `grid2` (2 col), `grid3` (3 col, default), `masonry` (CSS column-count); click any image to open a keyboard-navigable lightbox | v7.0.0 |
| 🔗 **Artist Social Links** | Facebook, Instagram, Twitter/X, and TikTok icons displayed on artist profile pages (`/artist/{id}`) and the artists portal (`/artists`); opens in new tab | v9.5.0 |
| 🎟️ **Ticket Button** | "🎟️ ซื้อบัตร / Get Ticket" orange button in event-detail header nav when a ticket URL is set for the event; hidden when no URL is configured | v9.5.0 |
| 🔗 **Favorites PWA Transfer** | Cross-device Favorites slug transfer via QR Code — collapsible "🔗 ย้าย Favorites" section on `/my/{slug}` + `/my-favorites/{slug}` displays a QR Code; `/connect` page on the destination device opens a camera scanner (jsQR) to read the QR and restore `fav_slug` into the PWA's isolated localStorage — solves iOS PWA `localStorage` isolation from Safari; paste-URL fallback included; 🔗 nav button auto-appears when no slug is present | v15.3.0 / v15.6.0 |
| 📴 **PWA Offline Cache** | Service worker caches visited pages + static assets; reopen core pages without a connection (network-first HTML with 3 s timeout, cache-first assets, stale-while-revalidate public API); install as an app via "Add to Home Screen"; unseen URLs fall back to a self-contained `offline.html` (TH/EN/JA) | v15.7.0 |
| 📊 **My Timeline View** | My Upcoming Programs adds a 📋 List / 📊 Timeline toggle — per-day Gantt chart that surfaces time overlaps across followed events (🔴 clash badge + overlap zone shading); time axis uses your own timezone | v15.8.0 |
| 🏛️ **Venue Portal & Profiles** | Public `/venues` portal (grid + realtime search + per-venue program counts) and `/venue/{id}` profile page (programs grouped by event); venue cells in program tables are clickable; online platforms (YouTube, X Spaces…) get a `🌐 Online` badge and are hidden from the grid | v16.0.0 |
| 🔴 **Live Now Strip** | Homepage strip showing programs on now (with countdown) + starting within 60 min, aggregated across all active events; click a row to open that event; auto-refreshes every 30 s and computes status against your device clock (correct across timezones) | v16.1.0 |
| 🌐 **Timezone-aware Notifications** | Telegram & Web Push notifications show the event-local time with your local time in parentheses, e.g. `18:00 (19:00 Asia/Tokyo)`, when timezones differ; control your timezone in-bot with `/tz Asia/Tokyo` or `/tz auto` | v16.1.1 |
| 🔔 **Telegram Summary-only Mode** | `/notify on\|off\|summary` — keep just the 9 AM daily summary while turning off per-program alerts (`on` = both, `summary` = daily only, `off` = none) | v16.2.0 |
| 🎤 **Calendar View Artist Links** | Artist names in Calendar view (day panel + detail modal) are clickable links to `/artist/{id}`; artists without a profile remain plain text | v16.3.0 |

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
| 🔐 **Admin 2FA (TOTP)** | Optional per-user RFC 6238 Authenticator app codes, one-time backup codes, and admin reset for DB-managed users | v10.0.0 |
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
| 🖼️ **Artist Picture Management** | Upload display picture (circular, 400×400 px) and cover picture (banner, 1200×400 px) directly from Artist edit modal; PHP GD server-side resize & center-crop; preview in modal; one-click delete | v6.0.0 |
| 🖼️ **Artist Pictures** | Upload display picture (circular avatar, 400×400 px) and cover picture (banner, 1200×400 px) per artist; PHP GD auto-resize & center-crop on upload (max 5 MB); stored in `uploads/artists/`; display picture shows as hover tooltip on program list badges | v6.0.0 |
| 💰 **Google AdSense** | Monetize with AdSense — `render_ad_unit()` helper in `functions/ads.php`; 3 sizes: leaderboard (728×90), rectangle (300×250), responsive (auto); 8 placements across public pages; disabled by default (`GOOGLE_ADS_CLIENT = ''`) | v6.3.0 |
| 🔵 **Google Admin UI** | Configure Google Analytics 4 + AdSense entirely from Admin › Settings › 🔵 Google — no SSH needed; settings stored in `config/google-config.json` (protected from HTTP access); same JSON-loader pattern as Telegram config | v6.4.0 |
| 🖼️ **Event Pictures Upload** | Upload multiple photos per event from Admin › Events edit modal; PHP GD scale-to-fit (1200×900 px, no upscale, no crop, JPEG 85%); max 5 MB per file (JPG/PNG/GIF/WEBP); live upload progress bar (X/N Y%); thumbnail grid with × delete button; drag-and-drop reordering; click thumbnail to preview fullscreen (lightbox, keyboard nav); bulk select mode to delete multiple pictures at once; `gallery_template` dropdown (grid1/grid2/grid3/masonry); pictures sharded to `uploads/events/{event_id}/` | v7.0.0 |
| 🖼️ **Dual Cover Images** | Upload separate Hero cover (1600×900, for homepage carousel) and Card cover (800×600, for events grid) per event via Cropper.js; crop to exact aspect ratio in-browser before upload; CSRF sent via `X-CSRF-Token` header; fallback chain: card → hero → gallery picture → theme gradient | v8.0.0 |
| 🏠 **Ticket-Marketplace Homepage** | Redesigned homepage: Hero Carousel (events with cover images, auto-rotates 5 s, swipe support); side-by-side layout (hero + mini calendar); 4-column Events Grid; Categories Tiles (aggregated program type counts) | v8.0.0 |
| 🔍 **FTS5 Full-Text Search** | Full-text search across programs, events, and artists using SQLite FTS5 (`unicode61` tokenizer); 2-column results (programs paginated 10/page + events/artists sidebar); graceful `LIKE '%…%'` fallback when FTS tables are absent | v9.0.0 |
| 🖼️ **Site Header Cover** | Upload site-wide header cover (1920×480, 4:1 ratio) and per-event header cover from Admin; Cropper.js 4:1 crop in-browser before upload; `get_header_cover_bg()` priority: event → site-wide → gradient | v9.2.0 |
| 🖼️ **Site-wide Header Cover** | Upload a 1920×480 px (4:1) banner image shown behind every page header site-wide; per-event header cover image also supported; Cropper.js 4:1 ratio crop | v9.2.0 |
| 🗓️ **Event Request Management** | Admin › Requests tab has sub-toggle: "📝 Program Requests" and "🗓️ Event Requests"; review user-submitted event proposals; approve type=add → auto-creates new event (inactive by default); unified pending badge counts both request types | v9.3.0 |
| 📝 **Event Request System** | Users can submit new event proposals via "📝 แจ้งเพิ่มงาน" button in listing header nav (listing page only); rate-limited 10 req/hr/IP; Admin reviews in dedicated sub-tab with approve/reject workflow | v9.3.0 |
| 📋 **Inline Credits on Event Pages** | "แหล่งข้อมูลอ้างอิง" section displayed automatically at the bottom of each event-detail page; no need to navigate to the Credits page | v9.4.0 |
| 🔗 **Artist Social Link Management** | Set Facebook, Instagram, Twitter/X, and TikTok URLs per artist in Admin › Artists edit modal; validated as http(s)://; stored as `social_*` columns on the `artists` table | v9.5.0 |
| 🎟️ **Ticket URL per Event** | Set a ticket purchase URL per event in Admin › Events modal; shows as orange "🎟️ ซื้อบัตร" button on the event page; hidden when blank; validated as http(s):// scheme | v9.5.0 |
| 📧 **Email Notifications** | Admin › Settings › Email configures SMTP host, port, encryption, sender, recipients, enable toggle, and test email; sends notifications when new Program/Event Requests are submitted | v9.6.0 |
| 🎨 **Requests Empty State** | Program Requests and Event Requests now show matching centered muted "No requests" rows when filtered results are empty | v9.6.0 |
| ⚡ **2FA Schema Flag** | Admin API checks 2FA schema once, then uses `data/.admin_2fa_columns_ready`; migration remains manual through `setup.php` or CLI | v10.1.0 |
| 👥 **Organizer Role** | Admin users can be assigned as event organizers and manage only their assigned Events, Programs, Credits, and event media | v12.0.0 |
| 🟡 **Organizer Active Requests** | Organizer users cannot directly activate events; Requests menu separates Program Requests, Event Requests (Guest), and Event Active Requests (Organizer) | v12.1.0 |
| 🎤 **Organizer Program Autocomplete** | Organizer Program form can reuse central Venue and Artist autocomplete; Artist values must reference existing artists only | v12.2.0 |
| 🎤 **Organizer Artist Requests** | Organizer users can request new artists from Artists › Request new artist; admin/agent review in Requests › Artist Request; admin/agent Dashboard request status covers 4 request types while organizer Dashboard shows only relevant owned data and Artist Requests | v12.3.0 |
| 🔎 **Admin Audit Log** | Every admin action (auth, CRUD, backup, settings, config, password/2FA) logged as JSON Lines to `cache/logs/admin-audit-YYYY-MM-DD.log` (30-day retention); viewable & filterable in Settings › 🔎 Audit Log with a click-to-open detail modal; admin role only | v14.0.0 |
| 📱 **Web Push Settings** | Admin › Settings › 📱 Web Push — generate VAPID keys, set subject/site URL/notify-before minutes, enable toggle, and send a test push; cron setup instructions included | v15.0.0 |
| 📋 **Web Push & Email Log Viewers** | Settings sub-tabs for Web Push and Email each include an activity-log viewer (file selector + filter + color-coded table + download), matching the Telegram log viewer | v15.1.0 |
| 🔄 **Web Push & Email Log Rotation** | Dedicated daily cron scripts (`rotate-webpush-logs.php`, `rotate-email-logs.php`) archive logs to dated files and prune older than 7 days; admin UI shows the rotation cron command | v15.2.0 |
| 🏛️ **Admin Venues Tab** | Manage canonical venues (admin/agent) — list with program/variant counts, Add/Edit (rename rewrites `programs.location`), Variants modal, and bulk-select **Merge** (pick canonical target, others become variants, programs rewritten); `🌐 Online platform` checkbox hides a venue from the public portal | v16.0.0 |
| 🧰 **Bulk Social Import (CLI)** | `tools/import-artist-socials.php` reads a CSV/JSON and bulk-updates artist social links; matches by name → variant alias → id; validates http(s):// URLs; `--dry-run` preview and `--overwrite` flags; blank cells never clobber existing values | v16.4.0 |

### ⚡ Technical Highlights
| Feature | Description | Since |
|---------|-------------|-------|
| 🗄️ **SQLite Database** | Lightweight, high-performance storage via PDO SQLite | v1.0.0 |
| 📁 **ICS Compatible** | Import events from standard .ics calendar files; export with `?type=` filter support; live subscription feed (RFC 5545/7986) | v1.0.0 |
| 🛠️ **No Dependencies** | Pure PHP, vanilla JavaScript, no frameworks required | v1.0.0 |
| 🔒 **Security First** | XSS protection, CSRF tokens, rate limiting, IP whitelist, security headers | v1.1.0 |
| 🔄 **Smart Caching** | Data version cache (10 min) · Credits cache (1 hr) · Feed static file cache (1 hr) · Query cache for event + artist pages (1 hr) · **Image PNG cache (1 hr)** — all auto-invalidated on admin writes | v1.1.0 |
| 🐳 **Docker Support** | One-command deployment with Docker Compose | v1.1.0 |
| 🧪 **Automated Tests** | Comprehensive test suite (26 suites, 13,231 tests), CI/CD with GitHub Actions (PHP 8.1-8.5) | v1.1.0 |
| 🎪 **Multi-Event** | Support multiple events with per-event venue mode, theme, and caching | v1.2.0 |
| ⚡ **DB Indexes** | Performance indexes for faster queries (2–5× speedup on large datasets) | v2.0.0 |
| 🎤 **Artist Reuse** | `artists` + `program_artists` junction + `artist_variants` — single artist record reused across all events | v3.0.0 |
| 🌐 **Per-event Timezone** | `timezone` column in `events` table; ICS export uses `DTSTART;TZID=` + RFC 5545 VTIMEZONE block; UTC timestamps computed correctly per event TZ | v4.0.0 |
| 🔍 **FTS5 Search Index** | `programs_fts`, `events_fts`, `artists_fts` virtual tables + 9 auto-sync triggers (ai/au/ad × 3 tables); `fts5_rebuild_all()` after ICS import; `unicode61` tokenizer (requires SQLite ≥ 3.43 for trigram) | v9.0.0 |
| 🔐 **Admin 2FA Crypto** | RFC 6238 TOTP (`functions/totp.php`) with replay guard + hashed one-time backup codes; schema readiness cached via `data/.admin_2fa_columns_ready` (no per-request `ALTER TABLE`) | v10.0.0 |
| 📱 **Web Push Crypto Stack** | Full VAPID + RFC 8291 (aes128gcm) push using PHP 8.1+ OpenSSL built-ins — no Composer; ECDH + HKDF + AES-128-GCM, ES256 JWT; endpoint allow-list (FCM/Mozilla/Apple/WNS) guards SSRF at subscribe + send time | v15.0.0 |
| 🛡️ **Security Audit Hardening** | Full remediation of a security audit — Telegram webhook fail-closed, Web Push SSRF allow-list, login CSRF gate, admin-only log viewers, `Require all denied` on `config/`+`tools/` — final score 9.8/10 | v15.5.0 |
| 📴 **PWA Service Worker** | `service-worker.js` 6-route fetch dispatcher (network-only private APIs, SWR public API with ETag/304, cache-first assets, network-first HTML + offline fallback); versioned caches (`app-*-v{VER}`) auto-pruned; `CACHE_VERSION` synced by `sync-sw-version.php` | v15.7.0 |
| 🏛️ **Venue Dedup Layer** | `venues` + `venue_variants` tables with `venue_resolve_canonical()` (exact → variant → auto-create) called before binding `programs.location`; lightweight (no `venue_id` FK, location stays canonical text) so ~30 location-reading files are untouched | v16.0.0 |

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
| **v5.5.3** | 2026-04-17 | **Telegram cron notification window — dynamic + smart recommendation** — notification half-window now scales with notify_before (`min(N/2, 7.5) min`) preventing post-program-start alerts for short notify times · notify-before changed to dropdown (5/10/15/30/60 min) · cron_interval removed as config; replaced with dynamic recommendation box (`floor(min(N,15)/1.5)` min, ≥150% coverage) that updates live in Admin UI |
| **v6.0.0** | 2026-04-17 | **Artist Cover & Display Picture System** — `display_picture` + `cover_picture` columns in `artists` table; Admin artist edit modal gets picture upload section with live preview; PHP GD auto-resize (display → 400×400 px · cover → 1200×400 px center-crop, JPEG 85%); cover displayed as full-width banner in `/artist/{id}`; display picture as circular avatar beside name; hover tooltip on program list artist badges (`position:fixed` JS tooltip avoids `overflow:hidden` clipping); `uploads/artists/` directory; **3666 automated tests pass** (16 suites) |
| **v6.0.1** | 2026-04-17 | **Bug fix: setup.php init_database missing artist picture columns** — `CREATE TABLE artists` in `init_database` lacked `display_picture`/`cover_picture`; fresh DB init created table without v6.0.0 columns; fixed schema + added `ALTER TABLE` fallback in all 3 setup action handlers; migration checklist updated with v6.0.0 entry |
| **v6.1.0** | 2026-04-18 | **Request form redesign** — merged "Add" button + per-row ✏️ Edit column into a single `btn-request` (sakura outline) in the action bar; modal now has a visible type radio toggle (Add / Edit); selecting Edit shows a program dropdown that pre-fills all fields; added End Date field for cross-day programs; removed Organizer field; relabelled "Categories" → "ศิลปิน / Artist"; `api/request.php action=programs` extended (added `end`, `categories`, `description` to SELECT; ORDER `start ASC`; LIMIT 200) |
| **v6.1.1** | 2026-04-18 | **Bug fix: Telegram cron T-separator datetime comparison** — raw SQLite `BETWEEN` compared ISO 8601 `T`-separator strings lexicographically (`T` > space), causing programs stored as `2026-04-17T11:00:00` to falsely match a window of `2026-04-17 23:xx` — resulting in notifications 12+ hours late; fixed by wrapping both sides with `datetime()`: `datetime(p.start) BETWEEN datetime(:windowStart) AND datetime(:windowEnd)` |
| **v6.1.2** | 2026-04-18 | **Bug fix: request submit + admin Requests tab broken** — `api/request.php` INSERT used stale column names (`type`, `title`, `requester_note`) from before the DB rename migration; `request_type` has `NOT NULL` so every submit threw a PDOException; `admin/api.php` `listRequests()` / `approveRequest()` had the same stale names, causing admin JS to receive `undefined` for all three fields and render nothing; fixed with correct column names + `SELECT … AS` aliases |
| **v6.1.3** | 2026-04-18 | **Bug fix: admin approve/reject silent failure** — UPDATE in `approveRequest()` / `rejectRequest()` referenced `admin_note`, `reviewed_at`, `reviewed_by` columns that did not yet exist; SQLite threw a PDOException caught silently; fixed by adding the three columns to the live DB via `ALTER TABLE` + updating `setup.php` `CREATE TABLE` + adding migration block |
| **v6.1.4** | 2026-04-18 | **Admin note form for approve/reject** — replaced bare `confirm()` dialog with an inline textarea form inside the request detail modal; Approve/Reject shows an optional note field before submitting; Back button returns to the detail view; `admin_note`, `reviewed_at`, `reviewed_by` saved to `program_requests`; fixed `approveReq`/`rejectReq` ReferenceError (table-row buttons still called removed functions) |
| **v6.1.5** | 2026-04-18 | **Bug fixes: setup.php migration check + Back button** — added `$hasRequestReviewColumns` detection for `admin_note`/`reviewed_at`/`reviewed_by` columns in `program_requests` so setup wizard surfaces missing v6.1.3 migration; fixed Back button in admin note form calling non-existent `openRequestDetail()` → changed to `viewRequestDetail(window._currentReqData?.id)` |
| **v6.2.0** | 2026-04-20 | **Dynamic XML Sitemap** — `sitemap.php` at `/sitemap.xml` (Apache rewrite); includes static pages, active events (`/event/{slug}` + `/event/{slug}/credits`), and artist profiles (`/artist/{id}`); `lastmod` from DB · **Sitemap file cache** — output saved to `cache/sitemap.xml` (TTL 1 hr); `invalidate_sitemap_cache()` auto-called on event/artist changes (6 points); included in `invalidate_all_caches()` · **Dynamic robots.txt** — `robots.php` at `/robots.txt`; injects `Sitemap:` URL from actual host; Disallows only `/my/` and `/my-favorites/` |
| **v6.3.0** | 2026-04-21 | **Google AdSense System** — `render_ad_unit()` helper in `functions/ads.php`; 3 ad sizes: leaderboard (728×90), rectangle (300×250), responsive (auto); 8 placements across `index.php`, `artist.php`, `artists.php`, `how-to-use.php`, `credits.php`, `contact.php`, `past-events.php`; disabled when `GOOGLE_ADS_CLIENT = ''`; CSS classes in `styles/common.css` |
| **v6.4.0** | 2026-04-21 | **Google Admin UI** — Google Analytics 4 + AdSense settings configurable via Admin › Settings › 🔵 Google (renamed from 📊 Analytics); `config/google-config.json` stores `ga_id`, `ads_client`, `ads_slot_*`; `analytics_config_get` + `analytics_config_save` API endpoints (admin-role only); `config/analytics.php` renamed + rewritten as `config/google.php` loading from JSON; no SSH required |
| **v6.5.0** | 2026-04-22 | **Comprehensive SEO Support** — `functions/seo.php` with 4 helpers (`seo_full_url`, `seo_truncate`, `seo_render_meta`, `seo_render_json_ld`); all public pages get meta descriptions, Open Graph, Twitter Cards, canonical URLs; `index.php` + `artist.php` + `artists.php` get JSON-LD structured data (WebSite/Event/MusicGroup/BreadcrumbList/ItemList schemas); personal pages (`my.php`, `my-favorites.php`) get `noindex`; **4331 automated tests pass** (17 suites) |
| **v7.0.0** ⚠️ | 2026-04-24 | **Event Pictures Gallery** — **Run migration:** `php tools/migrate-add-event-pictures-table.php`; new `event_pictures` table (id, event_id, filename, caption, display_order, created_at; ON DELETE CASCADE); `events.gallery_template TEXT DEFAULT 'grid3'` column; Admin event edit modal gets picture upload section (multi-select, thumbnail grid, × delete); PHP GD `mode='fit'` — scale-to-fit 1200×900 px, no upscale, no crop, JPEG 85%; 4 CSS gallery templates: `grid1/grid2/grid3/masonry`; `height:auto` on all images — natural aspect ratio; lightbox with keyboard nav (Escape/←/→); responsive: grid3 → 2 col ≤768px, all → 1 col ≤480px; **5053 automated tests pass** (18 suites) |
| **v7.1.0** | 2026-04-24 | **Drag-and-drop picture reordering** — Admin › Events picture section: thumbnail cards are draggable (HTML5 DnD, no external libs); ⠿ drag handle; dashed blue outline on hover target; inserts before/after by cursor midpoint; auto-saves order via `event_pictures_reorder` API on drop; hint cycles saving → saved → "ลากเพื่อเรียงลำดับ" |
| **v7.2.0** | 2026-04-24 | **Event pictures shard storage** — pictures now stored in `uploads/events/{event_id}/{uniqid}.jpg` (subdirectory per event) instead of flat `uploads/events/{event_id}_{uniqid}.jpg`; prevents a single directory from growing unbounded; `.htaccess` on parent directory covers all subdirs automatically; backward compatible with existing flat-path records |
| **v7.3.0** | 2026-04-25 | **Upload progress bar + click-to-preview lightbox** — progress bar replaces spinner, shows `X/N (Y%)` and updates after each file; green/orange summary on completion; auto-hides after 3 s · Clicking any thumbnail opens a fullscreen admin lightbox (prev/next buttons, Escape/←/→ keyboard, overlay click to close) |
| **v7.4.0** | 2026-04-25 | **Bulk delete event pictures** — "☑ เลือก" button enters select mode; clicking thumbnails toggles selection (blue outline + ✓ badge); yellow bulk-action bar shows count + "🗑️ ลบที่เลือก" (disabled when nothing selected) + Cancel; loops existing `event_picture_delete` API; drag-sort and lightbox automatically disabled in select mode |
| **v7.4.1** | 2026-04-27 | **Bug fix: admin program edit/duplicate showing wrong end date** — `toISOString()` converts to UTC before extracting the date, causing Bangkok users (UTC+7) to see end date shift one day back for programs ending before 07:00 local time; fixed with `localDateStr(d)` helper using `getFullYear/Month/Date` (local TZ); applied to 4 locations in admin modal |
| **v8.0.0** ⚠️ | 2026-05-04 | **Homepage Redesign — Ticket-Marketplace Style** — Hero Carousel (events with `cover_image`, auto-rotates 5 s, swipe); side-by-side hero + mini calendar (`hero-calendar-row`); 4-column Events Grid; Categories Tiles from aggregated `program_type` counts · **Dual Cover Image System** — `events.cover_image` (Hero 16:9, 1600×900) + `events.cover_image_card` (Card 4:3, 800×600); Cropper.js lazy-loaded from CDN; CSRF via `X-CSRF-Token` header; fallback chain card → hero → gallery picture → gradient · **Listing cache extended** — `event_covers` + `program_type_counts` added to `query_listing.json` · **5097 tests pass** (19 suites) |
| **v9.0.0** ⚠️ | 2026-05-04 | **FTS5 Full-Text Search** — **Run migration:** `php tools/migrate-add-fts5.php`; `programs_fts`, `events_fts`, `artists_fts` virtual tables (`unicode61` tokenizer); 9 auto-sync triggers (`programs_ai/au/ad`, `events_ai/au/ad`, `artists_ai/au/ad`); `fts5_rebuild_all()` called after ICS import; graceful `LIKE '%…%'` fallback when FTS absent · **2-column search results** — main column: Programs paginated 10/page (newest first); sidebar: Events top 5 + Artists top 10; mobile stacks single column · **Public API** — `api.php?action=search&q=` returns `{ programs, events, artists }` · **Admin integration** — Programs/Events/Artists lists use FTS when `?q=` present · **`functions/search.php`** — 8 public functions: `fts5_available`, `fts5_escape`, `fts5_count_programs`, `fts5_search_programs`, `fts5_count_events`, `fts5_search_events`, `fts5_search_artists`, `fts5_rebuild_all` · **45 new Fts5Test tests** |
| **v9.1.0** | 2026-05-05 | **"▼ อ่านเพิ่มเติม" on homepage event cards** — description hidden with `height:0; overflow:hidden` until button/card tapped; tapping opens modal with "📋 ดูตารางเวลา" link; `e.preventDefault()` + `e.stopPropagation()` prevents accidental navigation (`.event-card` is `<a>`) · **Event description block on timetable page** — `event-desc-block` shown above `.calendar-container` when `$eventMeta['description']` is non-empty · **`past-events.php` redesign** — now uses same `events-grid`/`event-card` markup as homepage; 20 per page; JS uses `openEventCardModal()` · **Pagination on credits.php** — 20 items/page, both event-specific (`?page=N`) and global view; global view paginates flat then re-groups by event_id |
| **v9.2.0** ⚠️ | 2026-05-06 | **Site-wide Header Cover Background Image** — Admin › Settings › Site: upload 1920×480 px (4:1) banner shown behind every page header; stored in `uploads/site/`; **Run migration:** `php tools/migrate-add-header-cover-image-column.php` · **Per-event Header Cover Image** — `events.header_cover_image TEXT DEFAULT NULL` separate from hero (16:9) and card (4:3) cover images; set via Admin › Events › Cover Images · **`get_header_cover_bg(?$eventMeta)`** — priority: `event.header_cover_image` → site-wide cover → empty (gradient fallback); injects `class="has-site-cover"` + `--header-cover-url` CSS var on `<header>` on all public pages · **Cropper.js 4:1** for header covers (site + per-event); modal moved to top-level `<body>` child to avoid `display:none` parent hiding fixed modal · **Date Jump Bar centering fix** — `.date-jump-inner` wrapper (`max-width:1200px; margin:0 auto`); `.date-jump-bar` is full-width fixed background separate from inner content |
| **v9.3.0** | 2026-05-07 | **Event Request System** — users can submit new event proposals via "📝 แจ้งเพิ่มงาน" button in listing header nav (listing page only); `event_requests` table stores type, name, description, dates, requester info, status, admin_note · **`api/event-request.php`** — public API: `action=submit` (POST, rate-limit 10 req/hr/IP via `evrate_` prefix), `action=events` (GET, returns active events for reference) · **Admin Event Requests** — sub-toggle tabs in Requests section (📝 Program Requests / 🗓️ Event Requests); filter by status; 6-column table; detail modal with approve/reject + admin note; approve type=add → INSERT new event (is_active=0, auto-slug); unified pending badge sums both `program_requests` + `event_requests` |
| **v9.4.0** | 2026-05-07 | **Inline Credits on Event Pages** — "แหล่งข้อมูลอ้างอิง" section auto-displayed at bottom of each event-detail page (below cross-event artists section), using `get_cached_credits($eventId)` · **Event-detail header nav simplified** — removed Credits and Artists links; only data-version badge remains in nav · **Credits page event-picker removed** — event-picker button and modal removed from `credits.php` · **how-to-use.php section 21** — new section "📋 แหล่งข้อมูลอ้างอิง" with TOC entry and TH/EN/JA translations |
| **v9.5.0** | 2026-05-08 | **Artist Social Links** — `social_facebook`, `social_instagram`, `social_twitter`, `social_tiktok TEXT DEFAULT NULL` on `artists` table; icon buttons displayed on `/artist/{id}` header and `/artists` portal cards (group + solo); `portal_social_svg()` PHP helper for inline SVG; `sanitize_social_url()` validates http/https schemes · **Ticket URL** — `ticket_url TEXT DEFAULT NULL` on `events` table; orange "🎟️ ซื้อบัตร" button in event-detail `<nav class="header-nav">` (hidden when empty); validated http/https; `i18n key event.buyTicket` (TH/EN/JA) · **Solo card restructured** — `artists.php` solo card wrapper changed from `<a>` → `<div>` (inner `<a class="portal-solo-name-link">`) for valid nested anchor HTML supporting social icon links |
| **v9.6.0** | 2026-05-07 | **Email Notifications** — Admin › Settings › Email configures SMTP host/port/encryption, username/password, from address/name, recipients, enable toggle, and test send; `config/email.php` loads `config/email-config.json`; `functions/email.php` handles SMTP delivery, recipient parsing, logging to `cache/logs/email.log`, and escaped HTML/plain-text request email bodies · **Request Alerts** — `api/request.php` and `api/event-request.php` send admin emails after successful Program/Event Request submission when enabled · **Requests Empty State Fix** — Program Requests and Event Requests now render matching centered muted "No requests" rows with correct column spans · **EmailNotificationTest** suite added |
| **v10.0.0** ⚠️ | 2026-05-07 | **Admin 2FA (TOTP / RFC 6238)** — optional per-user 2FA for DB-managed admin users; password login enters a pending 2FA step when enabled; Change Password modal includes setup/disable/backup-code regeneration; Users table shows 2FA status and admin reset; `functions/totp.php` implements Base32, HOTP/TOTP, otpauth URI, replay prevention, and one-time backup codes · **Run migration:** `php tools/migrate-add-admin-2fa-columns.php` |
| **v10.1.0** | 2026-05-07 | **2FA Migration Control** — Admin API no longer auto-adds 2FA columns during normal requests; run `setup.php` or `php tools/migrate-add-admin-2fa-columns.php` manually · **Schema Flag Cache** — after columns are confirmed, `data/.admin_2fa_columns_ready` skips repeated schema checks until removed |
| **v12.0.0** | 2026-05-07 | **Organizer Role** — adds event-assigned organizer users with scoped Events/Programs/Credits/media permissions, admin-only assignment API, migration `php tools/migrate-add-organizer-role.php`, and Admin UI role option |
| **v12.1.0** | 2026-05-07 | **Organizer Active Request Flow** — organizer-created/edited events cannot be activated directly; organizers request activation from separated Event Active Requests (Organizer), while guest add/edit requests stay under Event Requests (Guest) |
| **v12.2.0** | 2026-05-07 | **Organizer Program Autocomplete** — organizer users can autocomplete central Venue and Artist data while adding/editing Programs; Artist input is reference-only and cannot create new artist records |
| **v12.3.0** | 2026-05-08 | **Organizer Artist Requests** — organizer users can submit new artist requests from the Artists tab; admin/agent review Artist Request alongside Program, Event Guest, and Event Active requests; organizer Dashboard is role-focused and long Dashboard tables render full width |
| **v12.3.1** | 2026-05-08 | **Organizer Artist Request Access Fix** — organizer Artists tab no longer calls the admin-only `artists_list` endpoint before opening Request new artist; `switchTab('artists')` loads the full artist database only for admin/agent roles |
| **v12.3.2** | 2026-05-08 | **Artist Request Approve Refresh** — after admin/agent approve an Artist Request, the Artist list refreshes immediately and the success toast includes the created `artist_id`; regression tests confirm approve creates an `artists` record |
| **v12.3.3** | 2026-05-08 | **Role-Aware Admin Help** — Thai/English Admin Help is updated through v12.3.3 and now shows documentation appropriate to the logged-in role: admin, agent, or organizer |
| **v12.3.4** | 2026-05-11 | **Request Email Admin Link Fix** — Program/Event Request notification emails now send `Open Admin Requests tab` to `/admin/` instead of `/api/admin/`, with regression coverage for HTML and plain-text email bodies |
| **v14.0.0** | 2026-05-18 | **Admin Audit Log** — file-based audit log (`cache/logs/admin-audit-YYYY-MM-DD.log`, JSON Lines, 30-day retention) tracks login/logout/2FA, all admin CRUD, and public Program/Event Request submissions; Admin UI: Settings › Audit Log table with filter + detail modal; Telegram Activity Log viewer upgraded to same table+modal format |
| **v15.0.0** | 2026-05-18 | **Web Push Notifications (PWA)** — VAPID EC P-256 + RFC 8291 aes128gcm push without Composer; `manifest.json` + `service-worker.js` make the site installable as a PWA; `api/push.php` subscription API (subscribe/unsubscribe/status, HMAC-slug auth); `functions/webpush.php` pure-PHP crypto (keygen, JWT ES256, encryption, send); Admin Settings › 📱 Web Push sub-tab (VAPID keygen, subject, notify window); `cron/send-web-push-notifications.php` mirrors Telegram cron; sakura-gradient PWA icons (72/192/512 px); **9338 automated tests pass** (24 suites) |
| **v15.1.0** | 2026-05-19 | **Admin Log Viewers + Icons Directory Fix** — Web Push and Email sub-tabs each show a file selector (active + dated archives), filter input, scrollable table (Timestamp/Level/Message), and click-to-detail modal; `webpush_log_get` + `email_log_get` actions in `admin/api.php`; shared `_renderCronTable()` helpers reused by Telegram log viewer; renamed `icons/` → `icon/` (parent directory was blocked at hosting level) and updated 9 references including `manifest.json`, `service-worker.js`, cron, generator, and tests |
| **v15.2.0** | 2026-05-19 | **Web Push & Email Log Rotation** — `cron/rotate-webpush-logs.php` + `cron/rotate-email-logs.php` daily-archive `cache/logs/{name}.log` to `{name}-YYYY-MM-DD.log` with 7-day retention and same-date collision guard (`-daily` suffix); Admin UI Web Push / Email sub-tabs each show a daily rotation cron box; CLI-only (`cron/.htaccess` blocks HTTP) |
| **v15.3.0** | 2026-05-19 | **Favorites QR Code Display (PWA Slug Transfer — Part 1)** — collapsible "🔗 ย้าย Favorites" section on `/my/{slug}` + `/my-favorites/{slug}` shows a QR Code (via lazy-loaded `qrcodejs` CDN) encoding the `/my/{slug}` URL; scanning from PWA / another device triggers the existing auto-save logic; solves iOS PWA localStorage isolation; `transfer.*` i18n keys in TH/EN/JA |
| **v15.4.0** | 2026-05-19 | **Favorites Connect Page (PWA Slug Transfer — Part 2)** — new `/connect` page (`connect.php`) with camera QR scanner via `jsQR` (multi-CDN fallback: jsdelivr → unpkg → cdnjs); overrides `Permissions-Policy: camera=(self)` to allow `getUserMedia` despite global block; paste-URL fallback; `injectFavNavButton()` in `js/common.js` injects 🔗 link to `/connect` when no `fav_slug` exists; `how-to-use.php` Section 24 covers the two-device flow; `connect.*` + `section24.*` i18n keys in TH/EN/JA |
| **v15.5.0** | 2026-05-20 | **Security Audit Hardening (`docs/SECURITY_AUDIT_2026.md` rev 1–6)** — all 7 findings closed with regression tests: HIGH-1 (Telegram webhook secret leak retired by clearing `webhook_secret`; `verify_telegram_request()` fail-closes on empty), MEDIUM-1 (Web Push endpoint SSRF closed via `webpush_validate_endpoint()` allow-list at subscribe + send; `WEBPUSH_ALLOW_LOCALHOST` dev gate), MEDIUM-2 (`admin/login.php` CSRF check before rate-limit + audit log), LOW-1 (3 log viewers + dispatcher list now admin-only), LOW-2/3 (`config/.htaccess` + `tools/.htaccess` switched to `Require all denied` with Apache 2.2 fallback), LOW-4 (`.gitignore` blocks `*.db`/`test-*.php`/`debug-*.php`). Test suite 9361 → **9809 (+448)**. See [docs/SECURITY_AUDIT_2026.md](docs/SECURITY_AUDIT_2026.md) — final score 9.8 / 10, FULLY REMEDIATED |
| **v15.6.0** | 2026-05-20 | **Mobile UX — Favorites Pages Consolidation** — `/my/{slug}` and `/my-favorites/{slug}` reduced ~75–80% above-the-fold height on mobile; three stacked banners (QR Transfer, Telegram, Web Push) on `my.php` collapsed into single `.fav-actions-bar` of three chips with status dots (`is-active` green when Telegram linked / Push subscribed); QR Transfer chip opens new `#qrTransferModal` (was inline expand); Telegram chip → link/unlink; Web Push chip → subscribe/unsubscribe toggle; browser-unsupported disables chip with tooltip · `my-favorites.php` gets matching chip + modal (1 chip, since Telegram/Push were not present); inlined `.req-modal*` CSS because page only loads `common.css` while modal styles live in `artist.css`/`index.css` · "Followed Artists" section converted from always-expanded chip grid into stats line + collapsible: shows "👥 ติดตาม {N} ศิลปิน · {M} upcoming programs" with chevron rotating 180° on expand; `.fav-manage-link` ("→ จัดการทั้งหมด") inside expanded content links to `/my-favorites/{slug}` for batch management; empty state disables toggle but keeps onboarding message · 6 new i18n keys × 3 locales (TH/EN/JA): `actions.qrTransfer`, `actions.telegram`, `actions.push`, `transfer.modalTitle`, `fav.statsFollowing`, `fav.manageAll` |
| **v15.6.1** | 2026-05-21 | **Bug Fix — Duplicate Telegram Notifications (Race Condition)** — fixed read-clobber race between `cron/send-telegram-notifications.php` and `cron/send-web-push-notifications.php` that caused duplicate Telegram notifications when both crons ran on the same 6-minute interval: each cron read the favorites JSON under `LOCK_SH`, released the lock during 1–2 s of HTTP calls (Telegram Bot API / Apple Web Push), then wrote the entire stale `$favData` snapshot back under `LOCK_EX` — the cron that wrote last clobbered the other's per-key update (`telegram_notified[X]` cleared by Web Push cron's write, `push_subscriptions[].notified[X]` cleared by Telegram cron's write), so on the next 6-min tick `telegram_should_notify()` saw no record and sent the notification again · Fix changes the write-back in both crons to read-modify-write under `LOCK_EX`: re-read the file inside the exclusive lock, then overwrite only the cron's own keys (`telegram_notified` + `telegram_summary_date` for Telegram, `push_subscriptions` for Web Push) instead of the entire `$favData` · `telegram_cleanup_old_notifications()` moved inside the lock window so it operates on the merged state · Fallback: if `json_decode()` returns non-array on the in-lock re-read (empty/corrupt file) the in-memory `$favData` is used · Defense-in-depth side benefit: also protects against web-request-vs-cron races (`api/favorites.php`, `api/push.php` writes during a cron tick) · No DB migration, no cron schedule change required — fix takes effect on next tick after deploy |
| **v15.7.0** | 2026-05-21 | **PWA Offline Cache** — added `fetch` handler with cache strategies to `service-worker.js` (previously push + lifecycle only): cache-first for static assets (`app-static-v{VER}`), network-first with 3 s timeout for HTML pages (`app-pages-v{VER}`), stale-while-revalidate for public `api.php` with `ETag` / `If-None-Match` conditional revalidation that preserves cached body on `304` (`app-api-v{VER}`); 7-day max-age guard prevents serving misleading stale data; network-only bypass for user / private / write APIs (`api/favorites.php`, `api/push.php`, `api/request.php`, `api/event-request.php`, `api/telegram.php`), feeds (`feed.php`, `my-feed.php`), `/admin/`, `/setup.php`, `/tools/`, the SW itself, and `sync-sw-version.php`; cross-origin requests pass through untouched · New self-contained `offline.html` (sakura gradient inline CSS, 3-language inline JS picker from `localStorage.lang` → `<html lang>` → `navigator.language`, derive-home-path from `location.pathname` by stripping clean route prefixes — subdir safe; no external CSS / JS) · New CLI-only `sync-sw-version.php` at root that syncs `CACHE_VERSION` in `service-worker.js` with `APP_VERSION` from `config/app.php` (idempotent, fail-loud, guarded by `php_sapi_name()` + `.htaccess` `Require all denied` with Apache 2.2 `Deny from all` fallback); does not modify `tools/update-version.php` — the version bump workflow is now two steps: `php tools/update-version.php X.Y.Z` then `php sync-sw-version.php` · `PRECACHE_ASSETS` covers `offline.html`, `manifest.json`, 3 icons (72 / 192 / 512), 4 stylesheets (common / index / artist / portal), 2 scripts (common / translations); `new URL(p, self.registration.scope)` makes precache subdir-safe · `PwaOfflineTest` adds 59 tests (cumulative: **985 / 985**); push / notificationclick / pushsubscriptionchange handlers preserved as regression guard |
| **v15.8.0** | 2026-05-22 | **Timeline View — My Upcoming Programs** — added Gantt-style Timeline view on `/my/{slug}` alongside existing List view: per-day Gantt block (one per date) where Y-axis is **events** (each followed event becomes a horizontal lane) and X-axis is time (auto-fit to that day's min/max hour); programs render as colored bars positioned by `start_iso` / `end_iso` (raw `HH:MM` added to `$calPrograms` shape) — overlap between events on the same day is now immediately visible from bar alignment instead of requiring users to scan timestamps row-by-row · **Toggle List / Timeline** — segmented control above program list (📋 List / 📊 Timeline); `localStorage.fav_view_mode` persists choice; preferred view restored on page reload · **Cross-event overlap visualization (3 layers)**: (1) translucent red vertical zone strip (`rgba(229,57,53,.10)` + dashed top/bottom borders) covering the overlapping time range, rendered in every affected event column at `z-index:1` with `pointer-events:none`; (2) red badge "🔴 มี event ทับซ้อน" / "🔴 Overlapping events" / "🔴 重複あり" appended to date header when day has cross-event overlap; (3) bars naturally separated into different event columns make overlap visible from vertical alignment alone · **Overlap detection algorithms** — `_favDetectWithinEventOverlaps(progs)` does pair-wise sweep within same event with dynamic `stackIndex` / `stackTotal` for equal-width bar splitting (pattern from `js/common.js::detectOverlaps()`); `_favFindCrossEventOverlaps(programs)` does sort-by-start sweep-line collecting zones `{startMin, endMin, slugs[]}` when two programs overlap AND have different `event_slug` · **Event color coding** — bars + event headers use existing `.fav-ec-{0..5}` palette (pink / blue / green / amber / purple / teal) mapped via the existing `EVENT_COLOR_MAP[event_slug]` JS const from v4.0.3+; CSS overrides set border-color + background tint per code · **Time range auto-fit** — `minHour` / `maxHour` computed only from that day's programs; programs with `end ≤ start` get minimum 30-minute display height to avoid 0-height bars · **Click-to-detail** — clicking a bar opens the existing `openDayModal(date)` showing the full list of programs for that day with event / location / categories / stream URL — no separate detail popup needed · **Language switch re-render** — `changeLanguage()` IIFE patch re-renders the entire timeline when active (`_favTimelineRendered === true`), since "Time" axis label, date headers, and overlap badge text are language-dependent · 3 new i18n keys × 3 langs (TH/EN/JA): `fav.view.list`, `fav.view.timeline`, `fav.timeline.overlapHint` · No DB schema migration; data flow unchanged (one PHP query, one JS render); responsive: slot height 60 px mobile / 70 px desktop, event column width 140 / 180 px; existing tests (985 / 985) pass without modification |
| **v16.0.0** | 2026-05-27 | **Venue / Location Dedup System** — canonical layer for `programs.location` mirroring Artist Reuse (no `venue_id` FK — lightweight, location stays canonical text so the ~30 files reading/filtering location are untouched): new `venues` table + `venue_variants` aliases · `venue_resolve_canonical($db, $raw, $allowCreate)` in `admin/api.php` (exact → variant → auto-create) called before binding `location` in `createProgram`/`updateProgram`/`bulkUpdatePrograms`/`confirmIcsImport` so aliases auto-normalise and new venues self-register · `tools/migrate-add-venues-table.php` seeds `venues` from `DISTINCT location` + seeds `venue_variants` from the historical dedup mapping (`tools/dedup-locations.sql`, e.g. `Phenix Pratunam` → `Lot of Live, 3rd Fl. Phenix Pratunam`) · Admin **Venues tab** (admin/agent): list + program/variant counts, Add/Edit (rename rewrites `programs.location`), Variants modal, bulk-select + **Merge** (canonical target + others→variants + programs rewritten); form/bulk datalists repointed to canonical `venues_list` · public `venue.php` → `/venue/{id}` (programs grouped by event, matched `location = name OR location IN variants`, `query_venue_{id}.json` cache) + `venues.php` → `/venues` portal (search, `query_portal_venues.json`) + `🏛️ สถานที่` nav link + clickable venue cells (`$venueMeta`) · admin API `venues_list/get/create/update/delete`, `venues_autocomplete`, `venues_variants_*`, `venues_merge` (organizer gets read-only autocomplete/list) · `invalidate_venue_query_cache()`; merge/rename also invalidate data-version + feed · `VenueTest` 27 tests → **11,806 total** (26 suites), 100% pass; no `programs.location` data change required |
| **v16.0.1** | 2026-05-27 | **Venue `is_online` flag + system-wide TH translation cleanup ("เวที" → "สถานที่")** — added `venues.is_online INTEGER DEFAULT 0` (idempotent ALTER); migration auto-flags `YouTube` / `Instagram Live` / `X Spaces`; Admin venue Add/Edit gets `🌐 Online platform` checkbox + `🌐 Online` row badge; `/venues` portal filters `WHERE COALESCE(is_online, 0) = 0` (hides online platforms from grid); `/venue/{id}` profile shows `🌐 Online Platform` header badge when flagged; admin API `venues_create` / `venues_update` / `venues_get` / `venues_list` accept `is_online` (all guarded with `PRAGMA table_info` probe for backward compat with un-migrated installs). System-wide TH translation cleanup: replaced "เวที" (stage) with "สถานที่" (place) across `js/translations.js`, `admin/js/admin-i18n.js`, `index.php`, `admin/index.php`, `how-to-use.php`, `image.php`, `admin/help.php` HTML fallbacks (display-only, no logic change). `VenueTest` +7 tests |
| **v16.0.2** | 2026-05-28 | **Bug Fix — Event page showed wrong time for non-Bangkok event timezones** — `$start_ts` was correctly built as a UTC unix timestamp via `(new DateTime($event['start'], $eventTzObj))->getTimestamp()` but the 4 downstream `date(...)` calls that produced `data-start` / `data-end` and the day grouping / date-jump-bar values used PHP's default Bangkok timezone, re-formatting the UTC instant as Bangkok-local time; Taipei events (UTC+8) appeared 1 hour late, Tokyo (UTC+9) 2 hours late, Pacific events more. Fix: new `$evFmt($ts, $fmt)` closure wraps the UTC timestamp in a `DateTime` set to `$eventTzObj` before formatting; replaced 10 `date()` calls in `index.php` (program-time `data-start` / `data-end`, day-grouping key, day-header `d`/`m`/`Y`/`w`, date-jump-bar `d`/`m`/`w`). `feed.php` / `export.php` already correct (use `DateTime->format()` which keeps its own TZ); `image.php` group uses `strtotime + date` in the same default TZ (roundtrips); calendar / Gantt JS uses `ev.start.substring(...)` directly on the event-local stored string |
| **v16.0.3** | 2026-05-28 | **Bug Fix — My Upcoming Programs + personal ICS feed wrong time across timezones** — companion to v16.0.2 for `/my/{slug}` (which aggregates programs from multiple events with potentially different TZs). (1) "Now Playing" highlight on `my.php` was off by the event-vs-browser TZ offset because JS `new Date(row.dataset.start.replace(' ', 'T'))` parsed the stored event-local string as **browser-local** time. (2) Personal ICS feed (`my-feed.php`) `gmdate('Ymd\THis\Z', strtotime($p['start']))` interpreted the stored string in **PHP's Bangkok default TZ**, emitting UTC timestamps offset by the event-vs-Bangkok difference. Fix: SQL `SELECT … COALESCE(e.timezone, 'Asia/Bangkok') AS event_timezone` (later refactored in v16.0.5 to use `DEFAULT_TIMEZONE`); `my.php` emits ISO-8601 with explicit offset (`2026-05-29T18:20:00+08:00`) for `data-start` / `data-end`; `my-feed.php` uses `(new DateTime($p['start'], $tz))->getTimestamp()` for the UTC instant. Verified: test slug following SSr Vol.68 (Taipei) → `data-start="2026-05-29T18:20:00+08:00"` + `DTSTART:20260529T102000Z` (Taipei 18:20 = UTC 10:20 ✓) |
| **v16.0.4** | 2026-05-28 | **My Upcoming Programs — cross-timezone local-time annotation** — after v16.0.3 fixed the underlying timestamps, programs displayed event-local time correctly (Taipei 18:20 actually showed `18:20`) but a Bangkok-based user looking at the page had no way to tell that meant `17:20` on their own clock — the event page already shows `(HH:MM local)` per-row + `🕐 Asia/Tokyo (Asia/Bangkok)` header badge but the per-user / cross-event My Upcoming Programs page didn't have an equivalent. Added per-row `(HH:MM local)` annotation inside `.fav-time` (italic, muted) for cross-TZ programs (computed via `Date.toLocaleTimeString({ timeZone: userTz })` from `data-start` ISO-with-offset); per-row `🕐 Asia/Taipei` chip in `.fav-prog-meta` (next to event name / location / categories); `$calPrograms[]` now carries `start_full` (ISO+offset) / `end_full` / `event_tz` so day-modal JS-generated rows annotate identically; re-annotates on `appLangChange` (refreshes the "local" word per language); no-op when event TZ matches browser TZ so the common case is unchanged |
| **v16.0.5** | 2026-05-28 | **Timezone follow-up — `DEFAULT_TIMEZONE` constant + Timeline/Gantt local-time annotation** — three small follow-ups to v16.0.2 → v16.0.4: (1) v16.0.3/v16.0.4 had hardcoded literal `'Asia/Bangkok'` in `my.php` / `my-feed.php` (6 sites); replaced with the existing `DEFAULT_TIMEZONE` constant from `config/app.php` (single source of truth for the project default). (2) Event-page Gantt: `renderGanttChart()` now emits `data-utc-start` / `data-utc-end` (UTC ms) on every `.gantt-program-vertical`; new `annotateGanttLocalTime()` injects `<span class="gantt-program-time-local-v">(HH:MM local)</span>` next to the bold event-local time when `EVENT_TIMEZONE` ≠ browser TZ. (3) My Timeline cross-TZ annotations at two levels: per-bar `<div class="fav-tl-bar-time-local">(17:20 local)</div>` and per-lane-header `· 🕐 Asia/Taipei` chip when the lane's TZ differs from the browser TZ. Reuses the existing `tz.localTime` translation key for TH/EN/JA — no new i18n keys; `_favUserTz()` cached helper to avoid repeated `Intl.DateTimeFormat().resolvedOptions().timeZone` calls per render |
| **v16.0.6** | 2026-05-28 | **Event-page Gantt tooltip — show user-local time too** — companion to v16.0.5. The Gantt bar's click-tooltip listed only the event-local time row (e.g. "Time: 18:00 - 18:20") with no hint of the user-local equivalent that the bar itself had been showing since v16.0.5. Added an italic `(17:00–17:20 local)` line beneath the existing time row via `showEventTooltip()` in `js/common.js`, computed from the `data-utc-start` / `data-utc-end` attributes already attached to each bar in v16.0.5 (no new data attribute needed); reuses the existing `tz.localTime` translation key. Class `.tooltip-time-local` with inline styling matching the per-bar `.gantt-program-time-local-v`. No-op when `EVENT_TIMEZONE` matches browser TZ |
| **v16.0.7** | 2026-05-28 | **Profile pages — cross-TZ local-time annotation on `/artist/{id}` and `/venue/{id}`** — final wrap-up of the v16.0.2 → v16.0.6 timezone series. Both profile pages list programs grouped by event with potentially different TZs per event (same multi-TZ situation as `/my/{slug}` from v16.0.4). SQL adds `e.timezone AS event_timezone` to 3 queries (artist's own programs + group programs + venue's programs); `render_programs_table()` / `render_venue_programs()` compute UTC ms per program (`(new DateTime($p['start'], new DateTimeZone($evTz)))->getTimestamp() * 1000`) and emit `data-utc-start` / `data-utc-end` / `data-event-tz` on each program's second `<td class="prog-time">` (the time cell); new shared `annotateProfileTimes()` in `js/common.js` runs on `DOMContentLoaded` + `appLangChange`, scans `td.prog-time[data-event-tz][data-utc-start]`, and appends `<span class="prog-time-local">(17:20 local)</span>` inside the cell when the row's TZ ≠ browser TZ; CSS in `styles/artist.css` (shared between both pages). `/venues` portal unchanged (shows venue names + program counts, no time fields) |
| **v16.0.8** | 2026-05-28 | **My Timeline axis — align to user's local clock** — the Timeline view was originally designed for real-time overlap detection (v15.8.0) but its time-axis was event-local: each lane positioned bars by `_favTimeToMin(p.start_iso)` (event-local HH:MM minutes). For mixed-TZ followed events a Taipei 18:00 program and a Bangkok 17:00 program (both UTC 10:00, truly overlapping) appeared on **different rows** of the axis (row 18 vs row 17) so neither the overlap zone strip nor the date-header "🔴 มี event ทับซ้อน" badge fired — the whole point of the Timeline view was undermined for cross-TZ schedules. Added `_favUserLocalMin(isoStr)` via `Intl.DateTimeFormat(... hourCycle: 'h23').formatToParts()` to parse ISO-with-offset → user-local minutes-since-midnight; `_favProgMins(p)` caches the result on the program object as `p._uStart` / `p._uEnd` with graceful fallback to `_favTimeToMin(p.start_iso)`; updated 6 sites (lane sort, `minHour` / `maxHour` axis range, bar `top` / `height` positioning, within-event overlap detection, cross-event overlap-zone computation) to use the user-local minutes — overlap detection now matches real-time alignment. Same-TZ users see byte-identical UI to v16.0.7 |
| **v16.0.9** | 2026-05-28 | **My Timeline — flip bar label: user-local primary, event-local in parens** — after v16.0.8 the axis became user-local but the bar label was still showing event-local time as the bold primary text with `(HH:MM local)` underneath, creating a small visual inconsistency: the bar sat on the axis row matching user-local 17:00 but the bold number inside the bar said `18:00` (the Taipei event-local time). This release flips the label priority so the bold number on each bar matches the user-local axis row it sits on, with the event-local time moved into parens beneath. Primary `.fav-tl-bar-time` reads the user-local HH:MM range (computed from cached `p._uStart` / `p._uEnd`); secondary `.fav-tl-bar-time-sub` (italic, parens, only when cross-TZ) shows the event-local HH:MM from `p.time`. CSS class rename `.fav-tl-bar-time-local` → `.fav-tl-bar-time-sub`. New `_favFormatMin(min)` helper (minutes-since-midnight → "HH:MM"); bar `title` (browser native tooltip) now reads `${userTime} ${title} [${eventTime} ${eventTz}]` for cross-TZ programs. No-op when same-TZ |
| **v16.0.10** | 2026-05-28 | **Bug Fix — `init_database` left venues out of sync with seeded sample programs** — `testVenuesSeededFromLocations` (the v16.0.0 invariant: `venues.count >= COUNT(DISTINCT programs.location)`) was failing because Setup Wizard's fresh-install path seeds 3 sample programs via a direct `INSERT INTO programs (...)` loop that bypasses `venue_resolve_canonical()`, leaving `venues=0` while `programs.location` contained `Main Stage` / `Sub Stage`. Fix: added `INSERT OR IGNORE INTO venues (name) SELECT DISTINCT location FROM programs WHERE location IS NOT NULL AND location != ''` immediately after the sample-program seed loop in `init_database`; also added the same re-sync as a safety-net step in `run_all_migrations` (with a count-check first that surfaces "Sync venues จาก programs.location — เพิ่ม N สถานที่" in the migration messages when something was actually re-synced — catches installations that imported programs before v16.0.0 when `venue_resolve_canonical()` did not exist). Both initialization paths now genuinely maintain the v16.0.0 invariant |
| **v16.0.11** | 2026-05-28 | **Bug Fix — My Timeline mobile horizontal scroll broken with 3+ events** — on mobile the Timeline view could display the first two event columns but the user could not horizontally scroll to reach a third (or fourth, fifth …) event. The `overflow-x: auto` on the scroll container looked correct in CSS but never produced a scrollbar — instead the entire timeline card overflowed the viewport horizontally, the body's `overflow-x: hidden` then cropped it, and the right-side events were unreachable. Root cause: `renderFavTimelineDay()` was emitting the calculated `minWidth` (50px time-axis + N × 140px event columns on mobile) as an **inline `min-width` on `.fav-tl-chart` itself** (the scroll container). With min-width on the scroll container, the container itself grew past viewport width instead of staying at viewport width with overflowing inner content. Fix: moved the inline `min-width` from `.fav-tl-chart` (scroll container) to `.fav-tl-header` and `.fav-tl-body` (the inner content). The scroll container now fills the parent card at viewport width but its inner header/body force themselves to at least `minWidth` wide — overflowing the container, which triggers `overflow-x: auto` to render a horizontal scrollbar; the user can swipe/drag inside the timeline card to reach all events while the card stays within the viewport |
| **v16.0.12** | 2026-05-31 | **Bug Fix — Telegram & WebPush notifications fired at wrong time for non-Bangkok event timezones** — both cron scripts compared `programs.start` (stored in the event's own TZ, e.g. Taipei) against a Bangkok-timezone window string, causing cross-TZ events to notify 1 h late or be missed; `telegram_format_notification()` hardcoded `'Asia/Bangkok'` showing wrong display time. Fix: SQL now fetches `COALESCE(e.timezone, DEFAULT_TZ) AS event_timezone`; BETWEEN window expanded by ±14 h as loose pre-filter; PHP does exact UTC check via `(new DateTime($prog['start'], $evTz))->getTimestamp()`; display time in WebPush body + Telegram message uses `$evTz` |
| **v16.0.13** | 2026-06-01 | **Cross-timezone local-time annotation in Telegram & WebPush notifications** — when a program's event timezone differs from `DEFAULT_TIMEZONE`, the notification body appends the local-equivalent time in parentheses (Telegram `(HH:MM–HH:MM)`, WebPush compact `(HH:MM)`) so recipients see both the official event time and their own; same-timezone programs unchanged |
| **v16.1.0** | 2026-06-01 | **"Live Now" strip on the homepage (listing)** — top-of-page strip showing 🔴 programs on now + ⏭️ starting within 60 min, aggregated across all active events; PHP emits candidate programs (today ±1 day) as ISO-8601-with-offset, JS classifies live/soon against the browser clock (correct across event TZs + listing cache) with `(HH:MM local)` annotation; 🔴 Watch button when a `stream_url` exists; self-refreshes every 30 s + on `appLangChange`; hides entirely when nothing is live/soon; stored under new `live_programs` key in the existing `query_listing.json` (no new cache file, no schema change); `LiveNowTest` (15 tests) |
| **v16.1.1** | 2026-06-01 | **Notifications use the user's timezone (Telegram + Web Push)** — parenthetical time switched from `DEFAULT_TIMEZONE` to the user's TZ, e.g. `18:00 (19:00 Asia/Tokyo)`; centralized TZ model in favorites JSON (`user_timezone`, `user_timezone_manual`) + per-device `tz` on push subscriptions resolved by `fav_resolve_user_timezone()` (manual → device → stored → site default); TZ picker in `my.php` (auto/manual) with browser-TZ auto re-sync on travel; `/tz` bot command (`/tz`, `/tz Asia/Tokyo`, `/tz auto`) + `/status` shows effective TZ; `api/favorites.php?action=set_timezone` + push subscribe accepts `tz`; `is_valid_timezone()` helper; `TelegramTest` +21, `WebPushTest` +6 |
| **v16.2.0** | 2026-06-01 | **Telegram "daily-summary-only" notification mode** — favorites JSON gains `telegram_notify_mode` ∈ `all`/`summary`/`off` (backward-compat with the legacy boolean): `all` = per-program + summary (default), `summary` = daily summary only, `off` = none; `/notify on\|off\|summary` writes the mode (+ syncs legacy boolean) with mode-aware replies; cron wraps the per-program window/send loop in `if (telegram_per_program_enabled())` so `summary` skips per-program but the 9 AM daily-summary block still runs; `/status` shows 3 states; `/mute` still silences both channels; `TelegramTest` +15 |
| **v16.3.0** | 2026-06-01 | **Artist profile links in Calendar view** — Calendar view's day panel + detail modal now render each artist name as a link to `/artist/{id}` (new tab); `index.php` adds an `artists` (`{id,name}`) field per event in `window.CALENDAR_EVENTS` from `$programArtistIdMap` (mirrors the list-view categories cell), `categories` kept as text fallback; `calArtistLinksHtml(ev)` helper in `js/common.js` (escapes via `escapeHtml()`, uses `BASE_PATH`); `.cal-artist-link` added to the day-panel click guard so tapping a link navigates instead of opening the modal; artists without a profile stay plain text — no schema/cache-shape change |
| **v16.4.0** | 2026-06-01 | **Bulk-import artist social links from CSV/JSON (CLI tool)** — `tools/import-artist-socials.php <file.csv> [--dry-run] [--overwrite]` reads a header-row CSV (or `.json` array) and updates `artists.social_facebook/instagram/twitter/tiktok`; resolves each row by `artists.name` (exact) → `artist_variants` alias → `id` column (ambiguous names skipped + reported); validates http(s):// (mirrors `sanitize_social_url()`); blank cells leave existing values untouched by default, `--overwrite` replaces, `--dry-run` previews; case-insensitive header aliases + UTF-8 BOM (Excel-friendly); calls `invalidate_artist_query_cache()` + `invalidate_data_version_cache()` on ≥1 write; `tools/sample-artist-socials.csv` template |
| **v16.5.0** | 2026-06-01 | **Cross-event section also references each artist's group** — the "Also appears in" section now matches not just the same `artist_id` but also each solo artist's parent group, so events where the artist's **group** performs (tagged as the group, not the member) are surfaced; the cross-event query in `index.php` `UNION`s each current-event artist's parent `group_id` into the `IN (...)` lookup and joins `artists` to select `artist_name` per row (group names resolve even when the group isn't in the current event); surfaced group appearances render as `.cross-event-artist-chip` links to `/artist/{group_id}`, deduped per event; solo→group only (no group→member expansion); no schema/migration/CSS change; reuses My Upcoming Programs' resolution logic |
| **v16.5.1** | 2026-06-02 | **Docs — Help & How-to-Use coverage brought up to v16.5.0** — documentation-only release (no runtime/schema/API change; tests stay 13,231/13,231). Admin Help (TH+EN) Telegram section gains the `/tz [zone\|auto]` command (v16.1.1), `/notify on\|off` → `/notify on\|off\|summary` with all three modes (v16.2.0), an updated `/status`, and a new "Notification Modes & Timezone" subsection. How-to-Use adds two new sections — 🔴 **Live Now** (v16.1.0) and 🏛️ **All Venues** (`/venues` + `/venue/{id}`, v16.0.0/v16.0.1) — documents the My Favorites 📋 List / 📊 Timeline toggle (v15.8.0), adds `/tz` + `/notify summary` to the Telegram guide, and rewrites the FAQ offline answer to mention PWA Offline Cache (v15.7.0). New/updated i18n keys across TH/EN/JA in `js/translations.js` (`section25.*`, `section26.*`, `section17.myupcoming.timeline`, `section20.tz.*`, etc.) + 2 new TOC entries |

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
| 📝 **Report New Event** | Click "📝 แจ้งเพิ่มงาน" button in the nav bar |
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
    '129.1.0.1',
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
├── functions/       Helper functions (helpers, cache, admin, totp, security)
├── styles/ / js/   CSS + JavaScript (Sakura theme, translations)
├── data/            SQLite database (calendar.db, .setup_locked)
├── backups/         Database backups (auto-created)
├── cache/           Cache files (data_version, credits, login_attempts)
├── ics/             ICS source files
├── api/             Public API (request.php)
├── admin/           Admin panel (login.php, index.php, api.php)
├── tools/           CLI migration scripts
├── tests/           Automated tests (24 suites including TwoFactorAuthTest, Fts5Test, OrganizerRoleTest, and WebPushTest)
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
# Run all automated tests (25 suites, 985 cumulative)
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

The project includes **automated unit tests** covering all critical functionality (24 suites):

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
- 🌐 **TimezoneTest** (487) - events.timezone schema, migration idempotency, DEFAULT_TIMEZONE constant, get_event_timezone() priority logic, icsOffsetString() ±HHMM format, icsVtimezone() RFC 5545 VTIMEZONE block (STANDARD + DAYLIGHT auto-detection), UTC timestamp computation, DB CRUD, export.php TZID format, feed.php TZID format, index.php timezone injection, admin API timezone picker, translations.js keys, common.js initTimezoneDisplay(), CSS classes, setup.php integration
- 🔔 **TelegramTest** (541) - Telegram helper functions, bot commands, notification logic, cron guards, favorites JSON fields
- 📧 **EmailNotificationTest** (554) - Email notification config/helper/API/UI coverage plus Program/Event Requests empty-state checks
- 🔐 **TwoFactorAuthTest** (563) - RFC 6238 TOTP vectors, Base32, otpauth URI, replay guard, backup codes, manual 2FA migration sources, schema flag, API/UI/i18n coverage
- 🖼️ **ArtistPictureTest** (624) - Artist display/cover picture upload, GD resize, admin API, hover tooltip on program list
- 🔍 **SeoTest** (687) - seo_full_url() CLI safety, seo_truncate() word boundary, seo_render_meta() all OG/Twitter/noindex scenarios, seo_render_json_ld() Unicode/empty-array, JSON-LD schema keys, source-level checks on all 8 modified public pages
- 🖼️ **EventPicturesTest** (744) - event_pictures table/columns/indexes/CASCADE, events.gallery_template column, migration idempotency, DB CRUD, admin API (upload/delete/reorder/list/getEvent), processAndSaveImage mode='fit', uploads/events directory/htaccess, setup.php integration, index.php gallery HTML + lightbox, admin/index.php picture section + template dropdown, CSS templates, translations
- 🖼️ **EventCoverTest** (788) - event cover image schema, Cropper.js upload flow, CSRF header, fallback chain, admin API, listing cache keys
- 🔍 **Fts5Test** (833) - FTS5 virtual tables (programs_fts/events_fts/artists_fts), trigger auto-sync (ai/au/ad), fts5_available() caching, fts5_escape(), fts5_rebuild_all(), graceful LIKE fallback, admin search integration, public search API (`action=search&q=`), 2-column results layout

**Run All Tests (24 suites):**
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

✅ **All tests pass on PHP 8.1, 8.2, 8.3, 8.4, and 8.5**

**Expected Output:**
```
✅ ALL TESTS PASSED

Total: XXXX tests
Passed: XXXX
Pass Rate: 100.0%
```

For detailed testing documentation, see [tests/README.md](tests/README.md) and [TESTING.md](TESTING.md).

---

## 📜 Changelog

See [CHANGELOG.md](CHANGELOG.md) for full version history and release notes.

**Current Version**: 14.0.0

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
