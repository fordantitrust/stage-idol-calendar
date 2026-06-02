# 📁 Project Structure

File and folder structure for Idol Stage Timetable v16.5.1

---

## Overview

```
stage-idol-calendar/
│
├── 📄 Root PHP Pages
├── ⚙️  config/          Configuration constants
├── 🔧 functions/        Helper functions
├── 🎨 styles/           CSS
├── 📜 js/               JavaScript
├── 🗄️  data/             SQLite database
├── 💾 backups/          Database backups
├── 🗂️  ics/              ICS source files
├── 📦 cache/            Cache files
├── 🔌 api/              Public API endpoints
├── 🔐 admin/            Admin panel
├── 🛠️  tools/            CLI migration tools
├── 🧪 tests/            Automated test suite
└── 📚 docs/*.md         Documentation
```

---

## Root PHP Pages

| File | Purpose |
|------|---------|
| `index.php` | Main page — displays programs table (List + Gantt + Calendar view) |
| `how-to-use.php` | How-to guide (3 languages: TH/EN/JA) |
| `contact.php` | Contact page — channels loaded from DB (3 languages) |
| `credits.php` | Credits & References (loaded from DB + cache, global/per-event view) |
| `export.php` | Export ICS handler — download .ics from filtered programs |
| `feed.php` | Live ICS subscription feed — ETag, static file cache, RFC 5545/7986 |
| `api.php` | Public API endpoint (programs, organizers, locations, events_list) |
| `artist.php` | Artist Profile page — `/artist/{id}`; all programs grouped by event; group members, variants |
| `artists.php` | Artist & Group Portal (`/artists`) — gradient group cards + solo grid, real-time search, tab filter |
| `sitemap.php` | Dynamic XML Sitemap at `/sitemap.xml` (Apache rewrite) — static pages, active events, artist profiles; file-cached to `cache/sitemap.xml` (TTL 1 hr) |
| `robots.php` | Dynamic `robots.txt` at `/robots.txt` (Apache rewrite) — injects `Sitemap:` URL from actual host; Disallows `/my/`, `/my-favorites/` |
| `robots.txt` | Static fallback robots.txt (rewrite routes to `robots.php`) |
| `past-events.php` | Past Events archive page |
| `setup.php` | Setup Wizard — fresh install & maintenance (6 steps) |
| `config.php` | Bootstrap — loads all config/ and functions/ |
| `IcsParser.php` | ICS Parser class — parse .ics files → SQLite |
| `.htaccess` | Apache clean URL rewrite rules (removes .php extension) |
| `nginx-clean-url.conf` | Nginx complete server config (clean URLs, directory restrictions, security headers) |

---

## ⚙️ config/

Configuration constants for the entire system, loaded via `config.php`

| File | Defines | Purpose |
|------|---------|---------|
| `app.php` | `APP_VERSION`, `APP_NAME`, `PRODUCTION_MODE`, `VENUE_MODE`, `MULTI_EVENT_MODE`, `DEFAULT_EVENT_SLUG`, `DEFAULT_TIMEZONE` | App settings + cache busting + site title default |
| `admin.php` | `ADMIN_USERNAME`, `ADMIN_PASSWORD_HASH`, `SESSION_TIMEOUT`, `ADMIN_IP_WHITELIST_ENABLED`, `ADMIN_ALLOWED_IPS` | Admin auth fallback + IP whitelist |
| `security.php` | Security rate limiting constants | Rate limiting config |
| `database.php` | `DB_PATH` (`data/calendar.db`) | Database file path |
| `cache.php` | `DATA_VERSION_CACHE_TTL` (600s), `CREDITS_CACHE_TTL` (3600s), `FEED_CACHE_DIR`, `FEED_CACHE_TTL` (3600s), `SITEMAP_CACHE_FILE`, `SITEMAP_CACHE_TTL` (3600s) | Cache TTL settings + ICS feed cache + sitemap cache |
| `google.php` | `GOOGLE_ANALYTICS_ID`, `GOOGLE_ADS_CLIENT`, `GOOGLE_ADS_SLOT_LEADERBOARD`, `GOOGLE_ADS_SLOT_RECTANGLE`, `GOOGLE_ADS_SLOT_RESPONSIVE` | Loads from `google-config.json`; constants kept for backward compatibility (replaces `analytics.php` in v6.4.1) |
| `google-config.json` | JSON file with `ga_id`, `ads_client`, `ads_slot_*` | Runtime-editable Google config; protected from HTTP by `config/.htaccess` |
| `favorites.php` | `FAV_SECRET`, `FAV_CACHE_DIR`, `FAV_CACHE_TTL` | Anonymous favorites HMAC secret + storage config |
| `telegram.php` | `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, `TELEGRAM_NOTIFY_BEFORE_MINUTES` | Loads from `telegram-config.json`; constants for telegram bot |
| `email.php` | `EMAIL_ENABLED`, `EMAIL_SMTP_*`, `EMAIL_FROM_*`, `EMAIL_RECIPIENTS` | Loads SMTP notification settings from `email-config.json`; disabled by default |
| `email-config.json` | JSON file with SMTP host/port/encryption, sender, recipients, enabled flag | Runtime-editable Email Notifications config; protected from HTTP by `config/.htaccess` |

---

## 🔧 functions/

Helper functions loaded via `config.php`

| File | Key Functions | Purpose |
|------|--------------|---------|
| `helpers.php` | `get_db()`, `get_site_title()`, `get_site_theme()`, `get_event_by_slug()`, `get_event_id()`, `get_all_active_events()`, `get_event_venue_mode()`, `event_url()`, `get_event_timezone()` | General utilities + DB singleton + site title/theme + multi-event helpers + timezone |
| `cache.php` | `get_data_version()`, `get_cached_credits()`, `invalidate_data_version_cache()`, `invalidate_credits_cache()`, `invalidate_feed_cache()`, `invalidate_sitemap_cache()`, `invalidate_query_cache()`, `invalidate_artist_query_cache()`, `invalidate_all_caches()` | Cache read/write/invalidate (data version, credits, ICS feed, sitemap, query cache) |
| `admin.php` | `admin_login()`, `admin_login_attempt()`, `admin_complete_twofa()`, `safe_session_start()`, `admin_logout()`, `get_admin_role()`, `is_admin_role()`, `require_admin_role()`, `check_login_rate_limit()`, `record_failed_login()`, `clear_login_attempts()` | Auth + session + RBAC + rate limiting + 2FA login flow |
| `totp.php` | `totp_hotp()`, `totp_code()`, `totp_verify()`, `totp_otpauth_uri()`, `twofa_generate_backup_codes()`, `twofa_consume_backup_code()` | RFC 6238 TOTP + Base32 + one-time backup-code helpers for Admin 2FA |
| `security.php` | `sanitize_string()`, `sanitize_string_array()`, `get_sanitized_param()`, `send_security_headers()`, `check_ip_whitelist()`, `generate_csrf_token()`, `validate_csrf_token()` | XSS, CSRF, headers, IP whitelist |
| `ads.php` | `render_ad_unit(type)` | Google AdSense helper — renders leaderboard/rectangle/responsive ad units; no-op when `GOOGLE_ADS_CLIENT` is empty (v6.3.0+) |
| `ics.php` | `icsLine()`, `icsFold()`, `icsEscape()`, `icsEscapeText()`, `icsVtimezone()`, `icsOffsetString()` | Shared ICS helpers for RFC 5545 compliant export and feed generation |
| `telegram.php` | `send_telegram_message()`, `find_favorites_by_chat_id()`, `telegram_is_muted()`, `telegram_notify_is_enabled()`, `telegram_format_events_list()` | Telegram Bot API helpers + notification state |
| `email.php` | `email_send()`, `email_parse_recipients()`, `email_notify_program_request_created()`, `email_notify_event_request_created()` | Native SMTP email notification helpers for Program/Event Requests; logs to `cache/logs/email.log` |
| `favorites.php` | `fav_create()`, `fav_load()`, `fav_save()`, `fav_build_slug()`, `fav_parse_slug()`, `fav_verify_slug()`, `fav_maybe_cleanup()` | Anonymous favorites: HMAC-signed slug, JSON file I/O, sharded storage |
| `seo.php` | `seo_full_url()`, `seo_truncate()`, `seo_render_meta()`, `seo_render_json_ld()` | CLI-safe SEO helpers — meta description, Open Graph, Twitter Card, canonical URL, noindex, JSON-LD structured data (v6.5.0+) |
| `search.php` | `fts5_available()`, `fts5_escape()`, `fts5_count_programs()`, `fts5_search_programs()`, `fts5_count_events()`, `fts5_search_events()`, `fts5_search_artists()`, `fts5_search_all()`, `fts5_rebuild_all()` | FTS5 full-text search across programs, events, artists; `unicode61` tokenizer; graceful LIKE fallback when FTS tables absent (v9.0.0+) |

---

## 🗄️ data/

| File | Purpose |
|------|---------|
| `calendar.db` | Main SQLite database |
| `.setup_locked` | Lock file for setup.php (present = locked) |
| `.admin_2fa_columns_ready` | Runtime flag created after Admin API confirms `admin_users.twofa_*` columns exist; delete to force a schema re-check |

> **Security**: The `data/` directory is protected by `.htaccess` to prevent direct web browser access.

---

## 💾 backups/

Auto-created by the Admin Panel (Backup tab)

| File | Purpose |
|------|---------|
| `backup_YYYYMMDD_HHMMSS.db` | Database backup files |

---

## 📦 cache/

Auto-created by the system

| File | Purpose | TTL |
|------|---------|-----|
| `data_version.json` | Last data update timestamp (ETag for public API + feed) | 10 minutes |
| `credits.json` | Credits data cache | 1 hour |
| `feed_*.ics` | Static ICS feed cache files (key = md5 of sorted filters+eventId) | 1 hour |
| `sitemap.xml` | Static XML sitemap cache (served by `readfile()` on hit) | 1 hour |
| `query_event_{id}.json` | Event page DB query results (programs, artists, venues, types) | 1 hour |
| `query_artist_{id}.json` | Artist profile page DB query results | 1 hour |
| `query_listing.json` | Homepage listing query cache (`$activeEvents` + `$listingCalData`) | 1 hour |
| `query_portal.json` | Artists & Group Portal page query cache | 1 hour |
| `logs/email.log` | Email notification delivery log | Persistent (append-only; rotate externally if needed) |
| `login_attempts.json` | Login rate limiting data | 15 minutes |
| `site-theme.json` | Global site theme setting | Persistent (changed by admin) |
| `site-settings.json` | Site settings: `site_title`, `disclaimer_th/en/ja` | Persistent (changed by admin) |
| `images/img_*.png` | Server-side image export PNG cache (theme-aware) | 1 hour |

---

## 🔌 api/

Public API endpoints — no login required

| File | Purpose |
|------|---------|
| `request.php` | Program request submission — submit add/modify request + programs listing (for modal) |
| `event-request.php` | Event request submission — propose new events (add-only); rate-limited 10 req/hr/IP (v9.3.0+) |

See [API.md](API.md) for full endpoint documentation.

---

## 🔐 admin/

Admin panel — login required

| File | Purpose |
|------|---------|
| `login.php` | Login page (rate limited: 5 attempts/15 min/IP) |
| `index.php` | Admin dashboard — Tabs: Dashboard, Programs, Requests, Credits, Events, Artists, Import, Settings |
| `api.php` | All CRUD API endpoints (requires session + CSRF token) |

See [API.md](API.md) for admin endpoint documentation.

---

## 🛠️ tools/

CLI scripts for developers — run via `php tools/script.php`

| File | Purpose | Idempotent |
|------|---------|-----------|
| `import-ics-to-sqlite.php` | Import .ics files → `programs` table | ✅ (INSERT OR REPLACE) |
| `update-ics-categories.php` | Add CATEGORIES field to .ics files | - |
| `migrate-add-requests-table.php` | Create `program_requests` table | ✅ |
| `migrate-add-credits-table.php` | Create `credits` table | ✅ |
| `migrate-add-events-meta-table.php` | Create `events` (meta) table | ✅ |
| `migrate-add-admin-users-table.php` | Create `admin_users` table + seed from config | ✅ |
| `migrate-add-role-column.php` | Add `role` column to `admin_users` | ✅ |
| `migrate-add-admin-2fa-columns.php` | Add TOTP 2FA columns to `admin_users` | ✅ |
| `migrate-rename-tables-columns.php` | Rename tables/columns to v2.0.0 schema | ✅ |
| `migrate-add-indexes.php` | Add 7 performance indexes | ✅ |
| `migrate-add-event-email-column.php` | Add `email` column to `events` | ✅ |
| `migrate-add-program-type-column.php` | Add `program_type` column to `programs` | ✅ |
| `migrate-add-stream-url-column.php` | Add `stream_url` column to `programs` | ✅ |
| `migrate-add-theme-column.php` | Add `theme` column to `events` | ✅ |
| `migrate-add-contact-channels-table.php` | Create `contact_channels` table | ✅ |
| `migrate-add-artist-variants-table.php` | Create `artist_variants` table + import variants from `data/artists-mapping.json` | ✅ |
| `migrate-add-timezone-column.php` | Add `timezone TEXT DEFAULT 'Asia/Bangkok'` column to `events` | ✅ |
| `migrate-add-artist-pictures-column.php` | Add `display_picture` + `cover_picture TEXT DEFAULT NULL` to `artists`; create `uploads/artists/` dir | ✅ |
| `migrate-add-event-pictures-table.php` | Create `event_pictures` table + `events.gallery_template` column | ✅ |
| `migrate-add-fts5.php` | Create FTS5 virtual tables (`programs_fts`, `events_fts`, `artists_fts`) + 9 auto-sync triggers; rebuild index | ✅ |
| `migrate-add-header-cover-image-column.php` | Add `header_cover_image TEXT DEFAULT NULL` to `events` | ✅ |
| `migrate-add-event-requests-table.php` | Create `event_requests` table | ✅ |
| `migrate-add-artist-requests-table.php` | Create `artist_requests` table for organizer Artist Request workflow | ✅ |
| `migrate-add-artist-social-columns.php` | Add `social_facebook/instagram/twitter/tiktok TEXT DEFAULT NULL` to `artists` | ✅ |
| `migrate-add-ticket-url-column.php` | Add `ticket_url TEXT DEFAULT NULL` to `events` | ✅ |
| `update-version.php` | Bump `APP_VERSION` across 9 files automatically | - |
| `migrate-add-organizer-role.php` | Add organizer role ownership schema (`events.created_by_user_id`, `event_organizers`) | v12.0.0 |
| `generate-password-hash.php` | Generate bcrypt password hash | |
| `debug-parse.php` | Debug ICS file parsing | |
| `test-parse.php` | Test ICS parser | |

> **Note**: For a fresh install, use the [Setup Wizard](SETUP.md) instead of running tools individually.

---

## 🧪 tests/

Automated test suite — 24 suites (cumulative runner), PHP 8.1/8.2/8.3/8.4/8.5

| File | Unique Tests | Cumulative | Coverage |
|------|-------------|-----------|---------|
| `TestRunner.php` | — | — | Lightweight test framework (20 assertion methods) |
| `run-tests.php` | — | — | Main runner + colored output + suite selector |
| `SecurityTest.php` | 7 | 7 | XSS, null bytes, input sanitization, safe errors |
| `CacheTest.php` | 10 | 17 | Cache TTL, hit/miss, invalidation, fallback on error |
| `AdminAuthTest.php` | 21 | 38 | Session, login, timing attack resistance, DB auth |
| `CreditsApiTest.php` | 11 | 49 | Credits CRUD, bulk delete, SQL injection prevention |
| `IntegrationTest.php` | 51 | 100 | Config, file structure, workflows, API, multi-event |
| `UserManagementTest.php` | 19 | 119 | Role schema, RBAC helpers, user CRUD, permission guards |
| `ThemeTest.php` | 24 | 143 | Theme system, get_site_theme(), per-event theme, CSS files |
| `SiteSettingsTest.php` | 14 | 157 | Site title: get_site_title(), cache, fallbacks, admin API |
| `EventEmailTest.php` | 19 | 176 | events.email schema, CRUD, validation, ICS ORGANIZER |
| `ProgramTypeTest.php` | 35 | 211 | program_type schema, CRUD, API filter, UI badges, translations |
| `FeedTest.php` | 80 | 291 | icsEscape/icsEscapeText/icsFold, CATEGORIES, ETag, feed cache, RFC 5545 |
| `StreamUrlTest.php` | 31 | 322 | stream_url schema, CRUD, admin badge, public UI, ICS URL property |
| `FavoritesTest.php` | 84 | 406 | Anonymous favorites, UUID v7, HMAC, personal feeds, artist profiles |
| `TimezoneTest.php` | 81 | 487 | Per-event timezone, UTC conversion, TZID format, local time display, migration |
| `TelegramTest.php` | 54 | 541 | Telegram bot commands, helpers, mute/notify state, group resolution |
| `EmailNotificationTest.php` | 13 | 554 | Email config/helper loading, site-name subjects, admin URL base path, recipient parsing, SMTP disabled guard, defensive helper loading, request email hooks, Admin Email UI/API, Requests empty state |
| `TwoFactorAuthTest.php` | 9 | 563 | RFC 6238 TOTP vectors, Base32, otpauth URI, replay guard, backup codes, manual 2FA migration sources, schema flag, API/UI/i18n |
| `ArtistPictureTest.php` | 61 | 624 | Artist display/cover picture upload, GD resize, admin API, tooltip |
| `SeoTest.php` | 63 | 687 | seo_full_url() CLI safety, seo_truncate() word boundary, seo_render_meta() OG/Twitter/noindex, seo_render_json_ld() Unicode, JSON-LD schemas, source checks on 8 public pages |
| `EventPicturesTest.php` | 57 | 744 | event_pictures table/columns/indexes/CASCADE, events.gallery_template, migration idempotency, DB CRUD, admin API (upload/delete/reorder/list), processAndSaveImage mode='fit', uploads/events dir/htaccess, setup.php, index.php gallery+lightbox, admin/index.php picture section+template dropdown, CSS templates, translations |
| `EventCoverTest.php` | 44 | 788 | events.cover_image/cover_image_card schema, Cropper.js upload flow, CSRF X-CSRF-Token header, fallback chain, admin API, listing cache keys, migration idempotency |
| `Fts5Test.php` | 45 | 833 | FTS5 virtual tables, triggers (ai/au/ad × 3 tables), fts5_available() caching, fts5_search_*, fts5_rebuild_all(), LIKE fallback, public API action=search, admin FTS integration |
| `WebPushTest.php` | 45 | 882 | WEBPUSH_* constants, webpush_is_enabled(), base64url encode/decode, VAPID keygen (EC P-256, 87-char public key), JWT/encrypt/send functions, service-worker.js push+notificationclick, manifest.json, icons, admin API webpush_config_get/save/vapid_generate, api/push.php, cron CLI guard, admin-i18n.js + translations.js keys, config.php loading |

> **Cumulative mechanism**: `run-tests.php` uses `get_defined_functions()` — each suite re-runs all functions loaded so far. Total reported = sum of per-suite cumulative counts = 9338.

```bash
# Run all tests
php tests/run-tests.php

# Run specific suite
php tests/run-tests.php SecurityTest
php tests/run-tests.php FeedTest
php tests/run-tests.php Fts5Test
php tests/run-tests.php StreamUrlTest::testStreamUrlColumn
```

---

## 🐳 Docker

| File | Purpose |
|------|---------|
| `Dockerfile` | PHP 8.1-apache + PDO SQLite |
| `docker-compose.yml` | Production (port 8000, volumes) |
| `docker-compose.dev.yml` | Development (live reload, error display) |
| `.dockerignore` | Reduces Docker image size |

---

## 📚 Documentation

| File | Purpose |
|------|---------|
| `README.md` | Project overview + Quick Start |
| `SETUP.md` | Setup Wizard guide (fresh install + 6-step wizard) |
| `INSTALLATION.md` | Detailed installation guide (Apache/Nginx/XAMPP/Docker) |
| `API.md` | Full API endpoint documentation |
| `ICS_FORMAT.md` | ICS file format guide (fields, examples, import/export) |
| `PROJECT-STRUCTURE.md` | File structure (this file) |
| `DOCKER.md` | Docker deployment guide |
| `TESTING.md` | Manual testing checklist |
| `CHANGELOG.md` | Version history |
| `CONTRIBUTING.md` | Contribution guidelines |
| `SECURITY.md` | Security policy |

---

## 🗄️ Database Schema

> **Note**: Tables and columns were renamed in v2.0.0. Run `tools/migrate-rename-tables-columns.php` to update existing databases.

### Table: `programs`

Individual show/performance records (formerly `events`).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Unique program ID |
| `uid` | TEXT | UNIQUE NOT NULL | ICS UID (globally unique) |
| `title` | TEXT | NOT NULL | Program title/summary |
| `start` | DATETIME | NOT NULL | Start date and time |
| `end` | DATETIME | NOT NULL | End date and time |
| `location` | TEXT | | Venue/stage name |
| `organizer` | TEXT | | Performer/artist |
| `description` | TEXT | | Program description |
| `categories` | TEXT | | Artist names (comma-separated) |
| `program_type` | TEXT | DEFAULT NULL | Program type (free-text, v2.4.0+) |
| `stream_url` | TEXT | DEFAULT NULL | Live stream URL (http/https only, v2.6.0+) |
| `event_id` | INTEGER | FK → `events.id` | Event this program belongs to |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |

**Indexes** (v1.2.10): `idx_programs_event_id`, `idx_programs_start`, `idx_programs_location`, `idx_programs_categories`

---

### Table: `program_requests`

User-submitted requests to add or modify programs (formerly `event_requests`).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Request ID |
| `type` | TEXT | NOT NULL | `'add'` or `'modify'` |
| `program_id` | INTEGER | FK → `programs.id` | Program to modify (for `type='modify'`) |
| `event_id` | INTEGER | FK → `events.id` | Event this request belongs to |
| `title` | TEXT | NOT NULL | Requested program title |
| `start` | DATETIME | NOT NULL | Requested start time |
| `end` | DATETIME | NOT NULL | Requested end time |
| `location` | TEXT | | Requested venue |
| `organizer` | TEXT | | Requested performer |
| `description` | TEXT | | Requested description |
| `categories` | TEXT | | Requested artist names |
| `requester_name` | TEXT | | Name of the requester |
| `requester_email` | TEXT | | Email of the requester |
| `requester_note` | TEXT | | Additional notes |
| `status` | TEXT | DEFAULT `'pending'` | `'pending'`, `'approved'`, or `'rejected'` |
| `admin_note` | TEXT | | Admin's note when approving/rejecting (v6.1.3+) |
| `reviewed_at` | DATETIME | | When the request was reviewed (v6.1.3+) |
| `reviewed_by` | TEXT | | Admin username who reviewed (v6.1.3+) |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Submission time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last status update |

**Indexes**: `idx_program_requests_status`, `idx_program_requests_event_id`

---

### Table: `events`

Convention/event metadata for multi-event support (formerly `events_meta`).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Event ID |
| `slug` | TEXT | UNIQUE NOT NULL | URL-friendly identifier (e.g., `idol-stage-feb-2026`) |
| `name` | TEXT | NOT NULL | Event display name |
| `description` | TEXT | | Optional description |
| `start_date` | DATE | | Event start date |
| `end_date` | DATE | | Event end date |
| `venue_mode` | TEXT | DEFAULT `'multi'` | `'multi'` / `'single'` / `'calendar'` (calendar grid view, v2.7.0+) |
| `is_active` | BOOLEAN | DEFAULT 1 | Whether event is publicly visible |
| `theme` | TEXT | DEFAULT NULL | Per-event color theme (v2.1.1+) |
| `email` | TEXT | DEFAULT NULL | Contact email for ICS ORGANIZER field (v2.3.0+) |
| `timezone` | TEXT | DEFAULT `'Asia/Bangkok'` | Per-event timezone for ICS export and UTC conversion (v4.0.0+) |
| `cover_image` | TEXT | DEFAULT NULL | Hero cover image path (1600×900, 16:9) for homepage carousel (v8.0.0+) |
| `cover_image_card` | TEXT | DEFAULT NULL | Card cover image path (800×600, 4:3) for events grid (v8.0.0+) |
| `gallery_template` | TEXT | DEFAULT `'grid3'` | Photo gallery layout: `grid1`/`grid2`/`grid3`/`masonry` (v7.0.0+) |
| `header_cover_image` | TEXT | DEFAULT NULL | Header cover image path (1920×480, 4:1) shown behind page header (v9.2.0+) |
| `ticket_url` | TEXT | DEFAULT NULL | Ticket purchase URL (http/https only); displays orange "🎟️ ซื้อบัตร" button in event-detail nav (v9.5.0+) |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |

**Referenced by**: `programs.event_id`, `program_requests.event_id`, `credits.event_id`, `event_requests.event_id`

---

### Table: `credits`

Credits and references displayed on `credits.php`.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Credit ID |
| `title` | TEXT | NOT NULL | Credit title/name |
| `link` | TEXT | | URL (optional) |
| `description` | TEXT | | Additional description (optional) |
| `display_order` | INTEGER | DEFAULT 0 | Sort order (lower = shown first) |
| `event_id` | INTEGER | FK → `events.id` | Event this credit belongs to |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |

**Cache**: Credits data is cached for 1 hour (`cache/credits.json`, configurable in `config/cache.php`)

---

### Table: `admin_users`

Admin user credentials and roles.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | User ID |
| `username` | TEXT | UNIQUE NOT NULL | Login username |
| `password_hash` | TEXT | NOT NULL | Bcrypt password hash |
| `display_name` | TEXT | | Display name in UI |
| `role` | TEXT | NOT NULL DEFAULT `'admin'` | `'admin'` (full access) or `'agent'` (programs only) |
| `twofa_enabled` | INTEGER | DEFAULT 0 | Optional TOTP 2FA enabled flag |
| `twofa_secret` | TEXT | | Base32 TOTP secret (DB-managed users only) |
| `twofa_backup_codes` | TEXT | | JSON array of hashed one-time recovery codes |
| `twofa_confirmed_at` | DATETIME | | Timestamp when 2FA was enabled |
| `twofa_last_used_step` | INTEGER | | Last accepted TOTP step for replay prevention |
| `is_active` | BOOLEAN | DEFAULT 1 | Whether user is active |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |
| `last_login_at` | DATETIME | | Last successful login |

---

### Table: `contact_channels`

Contact channel entries displayed on `contact.php`. Auto-created by `ensureContactChannelsTable()` (v2.10.0+).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Channel ID |
| `icon` | TEXT | DEFAULT `''` | Icon emoji or SVG string |
| `title` | TEXT | NOT NULL DEFAULT `''` | Channel name/label |
| `description` | TEXT | DEFAULT `''` | Additional description |
| `url` | TEXT | DEFAULT `''` | Link URL |
| `display_order` | INTEGER | DEFAULT 0 | Sort order (lower = shown first) |
| `is_active` | INTEGER | DEFAULT 1 | Whether channel is publicly visible |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |

> Managed via **Admin › Contact** tab (admin role only). Not required for setup — table is created on demand.

---

### Table: `artists`

Artist/group records shared across all events (v3.0.0+).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Artist ID |
| `name` | TEXT | UNIQUE NOT NULL | Canonical display name |
| `is_group` | INTEGER | DEFAULT 0 | 1 = group/unit, 0 = solo artist |
| `group_id` | INTEGER | FK → `artists.id` | Parent group (if this artist is a member) |
| `display_picture` | TEXT | DEFAULT NULL | Circular profile picture path (400×400 px, stored in `uploads/artists/`) (v6.0.0+) |
| `cover_picture` | TEXT | DEFAULT NULL | Banner cover picture path (1200×400 px, stored in `uploads/artists/`) (v6.0.0+) |
| `social_facebook` | TEXT | DEFAULT NULL | Facebook profile URL (v9.5.0+) |
| `social_instagram` | TEXT | DEFAULT NULL | Instagram profile URL (v9.5.0+) |
| `social_twitter` | TEXT | DEFAULT NULL | Twitter/X profile URL (v9.5.0+) |
| `social_tiktok` | TEXT | DEFAULT NULL | TikTok profile URL (v9.5.0+) |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |

> **Reuse rate**: 74.7% of artists (62/83) appear in 2+ events.
> **Social links** *(v9.5.0)*: Displayed as icon buttons on `/artist/{id}` header and `/artists` portal (group cards + solo cards). Validated with `sanitize_social_url()` — accepts http/https only.

---

### Table: `event_pictures`

Per-event photo gallery (v7.0.0+). Managed via Admin › Events edit modal.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Picture ID |
| `event_id` | INTEGER | FK → `events.id` ON DELETE CASCADE | Owning event |
| `filename` | TEXT | NOT NULL | Relative path (e.g. `uploads/events/1/abc123.jpg`) |
| `caption` | TEXT | DEFAULT `''` | Optional caption text |
| `display_order` | INTEGER | DEFAULT 0 | Sort order for gallery display |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Upload time |

**Indexes**: `idx_event_pictures_event_id`, `idx_event_pictures_order`
**Storage**: Files sharded to `uploads/events/{event_id}/` (v7.2.0+)

---

### Table: `event_requests`

User-submitted proposals to add new events plus organizer activation requests (v12.1.0+). Managed via Admin › Requests › 🗓️ Event Requests sub-tab.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Request ID |
| `request_type` | TEXT | NOT NULL | `'add'`, `'modify'`, or `'activate'` |
| `event_id` | INTEGER | FK → `events.id` (nullable) | Existing event for modify/activate requests |
| `name` | TEXT | | Proposed event name |
| `description` | TEXT | | Proposed event description |
| `start_date` | DATE | | Proposed start date (YYYY-MM-DD) |
| `end_date` | DATE | | Proposed end date (YYYY-MM-DD) |
| `requester_name` | TEXT | NOT NULL | Name of the person submitting |
| `requester_email` | TEXT | | Email of the submitter (optional) |
| `note` | TEXT | | Additional notes from submitter |
| `status` | TEXT | DEFAULT `'pending'` | `'pending'`, `'approved'`, or `'rejected'` |
| `admin_note` | TEXT | | Admin's note when approving/rejecting |
| `reviewed_at` | DATETIME | | When the request was reviewed |
| `reviewed_by` | TEXT | | Admin username who reviewed |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Submission time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |

---

### Table: `artist_requests`

Organizer-submitted requests to add new artists (v12.3.0+). Managed via Admin › Requests › Artist Request; organizers submit from Admin › Artists › Request new artist.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Request ID |
| `name` | TEXT | NOT NULL | Proposed artist/group name |
| `is_group` | INTEGER | DEFAULT 0 | `1` for group, `0` for solo/member |
| `group_id` | INTEGER | FK → `artists.id` (nullable) | Parent group for solo/member requests |
| `social_facebook` | TEXT | | Facebook URL |
| `social_instagram` | TEXT | | Instagram URL |
| `social_twitter` | TEXT | | X/Twitter URL |
| `social_tiktok` | TEXT | | TikTok URL |
| `requester_user_id` | INTEGER | FK → `admin_users.id` | Organizer user who submitted |
| `requester_name` | TEXT | NOT NULL | Organizer display name/username |
| `requester_email` | TEXT | | Reserved requester email field |
| `status` | TEXT | DEFAULT `'pending'` | `'pending'`, `'approved'`, or `'rejected'` |
| `admin_note` | TEXT | | Reviewer note |
| `reviewed_at` | DATETIME | | When reviewed |
| `reviewed_by` | TEXT | | Reviewer username |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Submission time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |

**Indexes**: `idx_artist_requests_status`, `idx_artist_requests_created_at`, `idx_artist_requests_requester_user_id`

---

### Table: `program_artists`

Many-to-many junction between programs and artists (v3.0.0+).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Row ID |
| `program_id` | INTEGER | FK → `programs.id` ON DELETE CASCADE | Program |
| `artist_id` | INTEGER | FK → `artists.id` ON DELETE CASCADE | Artist |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Link creation time |

**Unique constraint**: `(program_id, artist_id)`

> ICS import auto-links CATEGORIES field → `artist_id` via direct name match and `artist_variants` lookup.
> 98.2% of programs with categories are linked (336/342).

---

### Table: `artist_variants`

Alias/variant names for artists — used by ICS import to recognise alternate spellings (v3.0.0+).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INTEGER | PRIMARY KEY AUTOINCREMENT | Variant ID |
| `artist_id` | INTEGER | FK → `artists.id` ON DELETE CASCADE | Owning artist |
| `variant` | TEXT | NOT NULL | Alternate name / alias |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |

**Unique constraint**: `(artist_id, variant)`
**Index**: `idx_artist_variants_artist_id`

> Managed via **Admin › Artists** tab — Variants modal per artist. Seeded from `data/artists-mapping.json` by migration script.

---

### FTS5 Virtual Tables *(v9.0.0+)*

Full-text search virtual tables powered by SQLite FTS5 (`unicode61` tokenizer). Created by `tools/migrate-add-fts5.php`.

| Virtual Table | Indexed Columns | Description |
|---------------|-----------------|-------------|
| `programs_fts` | `title`, `categories`, `organizer`, `description` | Full-text index for programs |
| `events_fts` | `name`, `description` | Full-text index for events |
| `artists_fts` | `name` | Full-text index for artists |

**Auto-sync triggers** (9 total): `programs_ai/au/ad`, `events_ai/au/ad`, `artists_ai/au/ad` — keep FTS tables in sync on INSERT/UPDATE/DELETE.

> `fts5_rebuild_all()` is called automatically after ICS import. A graceful `LIKE '%q%'` fallback activates when FTS tables are absent.

---

## 📊 Performance

### ICS Files vs. SQLite (500 events)

| Metric | ICS Files | SQLite | Improvement |
|--------|-----------|--------|-------------|
| Initial page load | 2.1s | 0.18s | **11.7x faster** |
| Filter by artist | 1.8s | 0.09s | **20x faster** |
| Get all organizers | 1.5s | 0.05s | **30x faster** |
| Memory usage | 45 MB | 12 MB | **3.75x less** |

### Why SQLite is Faster

1. **No parsing** — data already structured in the DB
2. **Indexed queries** — fast lookups on `start`, `location`, `categories`
3. **Application cache** — data version (10 min) + credits (1 hour)
4. **Compiled SQL** — optimized query execution via PDO

---

## 🔄 Database Management

### SQLite CLI

```bash
sqlite3 data/calendar.db

# View all tables
.tables
# → programs, events, program_requests, credits, admin_users

# Show table schema
.schema programs

# Count programs
SELECT COUNT(*) FROM programs;

# Programs on specific date
SELECT * FROM programs WHERE DATE(start) = '2026-02-07' ORDER BY start;

.quit
```

### Backup & Restore

Use **Admin Panel → Backup tab** for GUI-based backup/restore, or via CLI:

```bash
# Backup
cp data/calendar.db backups/calendar.db.backup

# Restore
cp backups/calendar.db.backup data/calendar.db
```

### Optimize (run monthly on large databases)

```bash
sqlite3 data/calendar.db "VACUUM;"   # Compact and reclaim space
sqlite3 data/calendar.db "ANALYZE;"  # Update query planner statistics
```

### Troubleshooting

| Error | Cause | Fix |
|-------|-------|-----|
| `unable to open database file` | `data/` missing or DB not created | Run `setup.php` or `php tools/import-ics-to-sqlite.php` |
| `database is locked` | Another process holds the DB | Check `lsof data/calendar.db`; restart PHP |
| `database disk image is malformed` | Corruption | Restore from backup (Admin → Backup tab) or re-import from ICS |
| Data not updating | Stale cache or browser cache | Re-run import + change `APP_VERSION` in `config/app.php` |

---

## 🔗 File Relationships

```
config.php (bootstrap)
    ├── config/app.php          → APP_VERSION, VENUE_MODE, MULTI_EVENT_MODE
    ├── config/admin.php        → Auth fallback, IP whitelist, SESSION_TIMEOUT
    ├── config/security.php     → Rate limiting
    ├── config/database.php     → DB_PATH
    ├── config/cache.php        → Cache TTL constants
    ├── config/email.php        → EMAIL_* SMTP notification constants
    ├── functions/helpers.php   → get_db(), event helpers
    ├── functions/cache.php     → Cache read/write
    ├── functions/admin.php     → Auth + RBAC + rate limiting
    ├── functions/totp.php      → Admin TOTP 2FA helpers
    ├── functions/security.php  → Sanitize, CSRF, headers
    └── functions/email.php     → Request email notifications

index.php
    ├── config.php              → bootstrap
    ├── IcsParser.php           → if no DB (fallback mode)
    └── data/calendar.db        → via get_db() or IcsParser

admin/api.php
    ├── config.php              → bootstrap
    ├── data/calendar.db        → $db global (PDO)
    └── cache/                  → invalidate after writes

admin/index.php
    └── admin/api.php           → fetch via JS (AJAX)

api.php (public)
    ├── config.php              → bootstrap
    └── data/calendar.db        → queries with ETag caching

setup.php
    ├── config.php              → bootstrap (for constants)
    ├── data/calendar.db        → init + write
    └── data/.setup_locked      → lock/unlock
```

---

*Idol Stage Timetable v16.5.1*
