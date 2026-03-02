# 📁 Project Structure

File and folder structure for Idol Stage Timetable v2.4.4

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
| `index.php` | Main page — displays programs table (List + Gantt view) |
| `how-to-use.php` | How-to guide (3 languages: TH/EN/JA) |
| `contact.php` | Contact page (3 languages) |
| `credits.php` | Credits & References (loaded from DB + cache) |
| `export.php` | Export ICS handler — download .ics from filtered programs |
| `api.php` | Public API endpoint (programs, organizers, locations, events_list) |
| `setup.php` | Setup Wizard — fresh install & maintenance (6 steps) |
| `config.php` | Bootstrap — loads all config/ and functions/ |
| `IcsParser.php` | ICS Parser class — parse .ics files → SQLite |
| `.htaccess` | Apache clean URL rewrite rules (removes .php extension) |
| `nginx-clean-url.conf` | Nginx clean URL config example |

---

## ⚙️ config/

Configuration constants for the entire system, loaded via `config.php`

| File | Defines | Purpose |
|------|---------|---------|
| `app.php` | `APP_VERSION`, `APP_NAME`, `PRODUCTION_MODE`, `VENUE_MODE`, `MULTI_EVENT_MODE`, `DEFAULT_EVENT_SLUG`, `GOOGLE_ANALYTICS_ID` | App settings + cache busting + site title default |
| `admin.php` | `ADMIN_USERNAME`, `ADMIN_PASSWORD_HASH`, `SESSION_TIMEOUT`, `ADMIN_IP_WHITELIST_ENABLED`, `ADMIN_ALLOWED_IPS` | Admin auth fallback + IP whitelist |
| `security.php` | Security rate limiting constants | Rate limiting config |
| `database.php` | `DB_PATH` (`data/calendar.db`) | Database file path |
| `cache.php` | `DATA_VERSION_CACHE_TTL` (600s), `CREDITS_CACHE_TTL` (3600s) | Cache TTL settings |

---

## 🔧 functions/

Helper functions loaded via `config.php`

| File | Key Functions | Purpose |
|------|--------------|---------|
| `helpers.php` | `get_db()`, `get_site_title()`, `get_site_theme()`, `get_event_by_slug()`, `get_event_id()`, `get_all_active_events()`, `get_event_venue_mode()`, `event_url()` | General utilities + DB singleton + site title/theme + multi-event helpers |
| `cache.php` | `get_data_version()`, `get_cached_credits()`, `invalidate_data_version_cache()`, `invalidate_credits_cache()`, `invalidate_all_caches()` | Cache read/write/invalidate |
| `admin.php` | `admin_login()`, `safe_session_start()`, `check_admin_session()`, `admin_logout()`, `get_admin_role()`, `is_admin_role()`, `require_admin_role()`, `check_login_rate_limit()`, `record_failed_login()`, `clear_login_attempts()` | Auth + session + RBAC + rate limiting |
| `security.php` | `sanitize_string()`, `sanitize_string_array()`, `get_sanitized_param()`, `send_security_headers()`, `check_ip_whitelist()`, `generate_csrf_token()`, `validate_csrf_token()` | XSS, CSRF, headers, IP whitelist |

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
| `data_version.json` | Last data update timestamp (footer display) | 10 minutes |
| `credits.json` | Credits data cache | 1 hour |
| `login_attempts.json` | Login rate limiting data | 15 minutes |
| `site-theme.json` | Global site theme setting | Persistent (changed by admin) |
| `site-settings.json` | Site settings: `site_title` (changed by admin) | Persistent (changed by admin) |

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
| `index.php` | Admin dashboard — Tabs: Programs, Requests, Credits, Events, Users, Backup |
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
| `generate-password-hash.php` | Generate bcrypt password hash | |
| `debug-parse.php` | Debug ICS file parsing | |
| `test-parse.php` | Test ICS parser | |

> **Note**: For a fresh install, use the [Setup Wizard](SETUP.md) instead of running tools individually.

---

## 🧪 tests/

Automated test suite — 999 tests, PHP 8.1/8.2/8.3

| File | Tests | Coverage |
|------|-------|---------|
| `TestRunner.php` | — | Lightweight test framework (20 assertion methods) |
| `run-tests.php` | — | Main runner + colored output + suite selector |
| `SecurityTest.php` | 7 | XSS, null bytes, input sanitization, safe errors |
| `CacheTest.php` | 17 | Cache TTL, hit/miss, invalidation, fallback on error |
| `AdminAuthTest.php` | 38 | Session, login, timing attack resistance, DB auth |
| `CreditsApiTest.php` | 49 | Credits CRUD, bulk delete, SQL injection prevention |
| `IntegrationTest.php` | 97 | Config, file structure, workflows, API, multi-event |
| `UserManagementTest.php` | 116 | Role schema, RBAC helpers, user CRUD, permission guards |
| `ThemeTest.php` | 140 | Theme system, get_site_theme(), per-event theme, CSS files, admin API |
| `SiteSettingsTest.php` | 154 | Site title: get_site_title(), cache, fallbacks, admin API, page injection |
| `EventEmailTest.php` | 19 | events.email schema, CRUD, validation logic, ICS ORGANIZER fallback |
| `ProgramTypeTest.php` | 35 | program_type schema, migration, CRUD, public API type filter, admin API, UI features, translations |

```bash
# Run all 999 tests
php tests/run-tests.php

# Run specific suite
php tests/run-tests.php SecurityTest
php tests/run-tests.php UserManagementTest::testRoleColumn
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
| `SETUP.md` | Setup Wizard guide (fresh install) |
| `QUICKSTART.md` | 3-step quick start guide |
| `INSTALLATION.md` | Detailed installation guide (Apache/Nginx/XAMPP/Docker) |
| `API.md` | Full API endpoint documentation |
| `PROJECT-STRUCTURE.md` | File structure (this file) |
| `DOCKER.md` | Docker deployment guide |
| `TESTING.md` | Manual testing checklist (129 cases) |
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
| `venue_mode` | TEXT | DEFAULT `'multi'` | `'multi'` or `'single'` |
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

*Idol Stage Timetable v2.4.4*
