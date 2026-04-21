# 📁 Project Structure

File and folder structure for Idol Stage Timetable v6.4.1

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

---

## 🔧 functions/

Helper functions loaded via `config.php`

| File | Key Functions | Purpose |
|------|--------------|---------|
| `helpers.php` | `get_db()`, `get_site_title()`, `get_site_theme()`, `get_event_by_slug()`, `get_event_id()`, `get_all_active_events()`, `get_event_venue_mode()`, `event_url()`, `get_event_timezone()` | General utilities + DB singleton + site title/theme + multi-event helpers + timezone |
| `cache.php` | `get_data_version()`, `get_cached_credits()`, `invalidate_data_version_cache()`, `invalidate_credits_cache()`, `invalidate_feed_cache()`, `invalidate_sitemap_cache()`, `invalidate_query_cache()`, `invalidate_artist_query_cache()`, `invalidate_all_caches()` | Cache read/write/invalidate (data version, credits, ICS feed, sitemap, query cache) |
| `admin.php` | `admin_login()`, `safe_session_start()`, `check_admin_session()`, `admin_logout()`, `get_admin_role()`, `is_admin_role()`, `require_admin_role()`, `check_login_rate_limit()`, `record_failed_login()`, `clear_login_attempts()` | Auth + session + RBAC + rate limiting |
| `security.php` | `sanitize_string()`, `sanitize_string_array()`, `get_sanitized_param()`, `send_security_headers()`, `check_ip_whitelist()`, `generate_csrf_token()`, `validate_csrf_token()` | XSS, CSRF, headers, IP whitelist |
| `ads.php` | `render_ad_unit(type)` | Google AdSense helper — renders leaderboard/rectangle/responsive ad units; no-op when `GOOGLE_ADS_CLIENT` is empty (v6.3.0+) |
| `ics.php` | `icsLine()`, `icsFold()`, `icsEscape()`, `icsEscapeText()`, `icsVtimezone()`, `icsOffsetString()` | Shared ICS helpers for RFC 5545 compliant export and feed generation |
| `telegram.php` | `send_telegram_message()`, `find_favorites_by_chat_id()`, `telegram_is_muted()`, `telegram_notify_is_enabled()`, `telegram_format_events_list()` | Telegram Bot API helpers + notification state |
| `favorites.php` | `fav_create()`, `fav_load()`, `fav_save()`, `fav_build_slug()`, `fav_parse_slug()`, `fav_verify_slug()`, `fav_maybe_cleanup()` | Anonymous favorites: HMAC-signed slug, JSON file I/O, sharded storage |

---

## 🗄️ data/

| File | Purpose |
|------|---------|
| `calendar.db` | Main SQLite database |
| `.setup_locked` | Lock file for setup.php (present = locked) |

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
| `login_attempts.json` | Login rate limiting data | 15 minutes |
| `site-theme.json` | Global site theme setting | Persistent (changed by admin) |
| `site-settings.json` | Site settings: `site_title`, `disclaimer_th/en/ja` | Persistent (changed by admin) |
| `images/img_*.png` | Server-side image export PNG cache (theme-aware) | 1 hour |

---

## 🔌 api/

Public API endpoints — no login required

| File | Purpose |
|------|---------|
| `request.php` | User request submission (submit + programs listing) |

See [API.md](API.md) for full endpoint documentation.

---

## 🔐 admin/

Admin panel — login required

| File | Purpose |
|------|---------|
| `login.php` | Login page (rate limited: 5 attempts/15 min/IP) |
| `index.php` | Admin dashboard — Tabs: Programs, Requests, Credits, Events, Users, Backup, Contact, Settings |
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
| `migrate-rename-tables-columns.php` | Rename tables/columns to v2.0.0 schema | ✅ |
| `migrate-add-indexes.php` | Add 7 performance indexes | ✅ |
| `migrate-add-event-email-column.php` | Add `email` column to `events` | ✅ |
| `migrate-add-program-type-column.php` | Add `program_type` column to `programs` | ✅ |
| `migrate-add-stream-url-column.php` | Add `stream_url` column to `programs` | ✅ |
| `migrate-add-theme-column.php` | Add `theme` column to `events` | ✅ |
| `migrate-add-contact-channels-table.php` | Create `contact_channels` table | ✅ |
| `migrate-add-artist-variants-table.php` | Create `artist_variants` table + import variants from `data/artists-mapping.json` | ✅ |
| `update-version.php` | Bump `APP_VERSION` across 9 files automatically | - |
| `generate-password-hash.php` | Generate bcrypt password hash | |
| `debug-parse.php` | Debug ICS file parsing | |
| `test-parse.php` | Test ICS parser | |

> **Note**: For a fresh install, use the [Setup Wizard](SETUP.md) instead of running tools individually.

---

## 🧪 tests/

Automated test suite — 3666 tests (cumulative), PHP 8.1/8.2/8.3/8.4/8.5

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
| `TimezoneTest.php` | 67 | 473 | Per-event timezone, UTC conversion, TZID format, local time display, migration |
| `ArtistPictureTest.php` | 61 | — | Artist display/cover picture upload, GD resize, admin API, tooltip |
| `TelegramTest.php` | 54 | — | Telegram bot commands, helpers, mute/notify state, group resolution |

> **Cumulative mechanism**: `run-tests.php` uses `get_defined_functions()` — each suite re-runs all functions loaded so far. Total reported = sum of per-suite cumulative counts = 3666.

```bash
# Run all 3666 tests
php tests/run-tests.php

# Run specific suite
php tests/run-tests.php SecurityTest
php tests/run-tests.php FeedTest
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
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |

**Referenced by**: `programs.event_id`, `program_requests.event_id`, `credits.event_id`

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
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Last update time |

> **Reuse rate**: 74.7% of artists (62/83) appear in 2+ events.

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
    ├── functions/helpers.php   → get_db(), event helpers
    ├── functions/cache.php     → Cache read/write
    ├── functions/admin.php     → Auth + RBAC + rate limiting
    └── functions/security.php  → Sanitize, CSRF, headers

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

*Idol Stage Timetable v6.4.1*
