# Changelog

All notable changes to Idol Stage Timetable will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.4.3] - 2026-03-02

### Added
- 🧪 **ProgramTypeTest** — 35 automated tests covering all changes in v2.4.x
  - **Schema**: `programs.program_type` column exists and is nullable
  - **Migration**: `tools/migrate-add-program-type-column.php` exists and is idempotent
  - **CRUD**: insert/read/update/delete `program_type` values including NULL
  - **Public API**: `?type=` filter works via `$typeFilter` variable
  - **Admin API**: `programs_types` action, `SELECT DISTINCT program_type`, CREATE/UPDATE/bulk-update handle `program_type`
  - **index.php UI**: `appendFilter()` function, `URLSearchParams`, `$hasTypes` flag, `.event-subtitle`, `data-i18n="table.type"`, clickable badges, `htmlspecialchars(json_encode())` pattern
  - **Translations**: `'table.type'` key present in all 3 languages (ประเภท / Type / タイプ), appearing 3 times
  - **Admin UI v2.4.2**: `sortBy('categories')`, no `sortBy('organizer')`, `event.categories`, no `<th>ผู้จัด</th>`
- 📊 **Total tests: 999** (increased from 964 → 999, all passing across 10 suites)

### Fixed
- 🐛 **setup.php `fix_programs_title` — `program_type` column lost after fix** — this action recreates the `programs` table from `summary` → `title`, but the new `CREATE TABLE` was missing the `program_type` column, causing it to be immediately dropped
  - Added `program_type TEXT DEFAULT NULL` to the `CREATE TABLE` that is always recreated
  - Checks whether `programs_old` already has `program_type`: if yes → includes the column in `INSERT SELECT` to copy values; if no → omits the column from `INSERT` (default is NULL)

---

## [2.4.2] - 2026-03-02

### Changed
- 🗂️ **Admin Programs List: Organizer → Categories** — renamed the "Organizer" column in the Admin Programs table to "Categories" (related artists)
  - Programs list table: header `Organizer` → `Categories`, sort key `organizer` → `categories`, data `event.organizer` → `event.categories`
  - ICS import preview table: header changed from "Organizer" to "Related Artists", data `event.organizer` → `event.categories`
  - The Add/Edit Program form still retains the Organizer field for editing existing data

---

## [2.4.1] - 2026-03-02

### Added
- 🖱️ **Clickable Filter Badges** — click any badge in the table to instantly append a filter, without using the filter fields at the top
  - **Related Artists**: categories are split into individual artist badges — click to append an `artist[]` filter
  - **Type**: type badge in the "Type" column — click to append a `type[]` filter
  - `appendFilter(type, value)` JS function: appends a filter to the URL (doesn't remove existing filters), works with or without pre-existing filters, won't add duplicates
- 📋 **Program Type Column** — separates "Type" into its own dedicated column instead of being embedded in the title cell
  - Column is shown when the event has at least 1 program with a defined `program_type` (`$hasTypes = !empty($types)`)
  - Supports 3 languages (`table.type`: ประเภท / Type / タイプ)
  - Badge is clickable → appends filter by type; rows without a type → display `-`

### Changed
- 🏷️ **Event Name Subtitle** — event name is displayed as a separate subtitle below the site title on the schedule page
  - Moved the event name out of `<h1>` (previously "Site Title - Event Name") into a separate `<div class="event-subtitle">`
  - Always shown when viewing any event's schedule — regardless of whether the dropdown selector is displayed
  - Benefit: when only one event exists in the system, the dropdown won't appear, but the event name still shows clearly below the site title

### Documentation
- 📖 **how-to-use.php updated** — added section "5. Quick filter from badges in the table" to the filtering section in all 3 languages (TH/EN/JA)
  - Describes artist badges (pink) and type badges (blue)
  - Explains append filter behavior (does not remove existing filters)

### Fixed
- 🐛 **SyntaxError in badge onclick** — `json_encode()` returned a string containing `"` which prematurely closed the HTML attribute; fixed with `htmlspecialchars(json_encode(...), ENT_QUOTES, 'UTF-8')`

## [2.4.0] - 2026-03-02

### Added
- 🏷️ **Program Type System** — type classification system for programs (stage, booth, meet & greet, etc.)
  - `program_type TEXT DEFAULT NULL` column in `programs` table (backward compatible — NULL means no type)
  - Free-text entry: type any program type freely, with autocomplete from existing types in the system
  - **Admin form**: input + datalist in create/edit modal, badge in list view, bulk edit option
  - **Public filter UI**: checkbox group to filter by type (same as artist/venue filter) — shown only when data exists
  - **Program badge**: displays a blue badge above the program name in the main table
  - **Gantt Chart**: shows type label on program bar (small, at the top)
  - **Public API**: `?type=` filter parameter + `action=types` endpoint
  - **ICS Export**: `?type[]=` filter + `program_type` appended to CATEGORIES field
  - Migration script: `tools/migrate-add-program-type-column.php` (idempotent)
  - GitHub Actions: added migration to workflow
- 🏷️ **Program Type in ICS Import** — type can be set in 3 ways (listed in priority order)
  - `X-PROGRAM-TYPE:` field in the VEVENT block (per-event, highest priority)
  - "🏷️ Program Type (default)" field in Admin → Import UI (batch default for web upload)
  - `--type=value` argument when importing via CLI: `php tools/import-ics-to-sqlite.php --event=slug --type=stage`
  - `IcsParser::parseEvent()` now supports the `X-PROGRAM-TYPE:` field

### Fixed
- 🐛 **setup.php `init_database` missing `program_type` column** — `CREATE TABLE programs` in fresh install was missing `program_type TEXT DEFAULT NULL`, causing the status check `$allTablesOk = false` and the bottom button to display incorrectly
  - Added `program_type TEXT DEFAULT NULL` to the `CREATE TABLE` statement in the `init_database` handler

### Documentation
- 📖 **Admin Help Pages updated** (`admin/help.php`, `admin/help-en.php`)
  - Added Program Type field to the Add/Edit Program form table
  - Added X-PROGRAM-TYPE to the Supported ICS Fields table
  - Added section "Setting Program Type on Import" with a table of 3 methods
  - Added FAQ: Setting Program Type when importing ICS
  - Updated Bulk Edit description to include Program Type

### Changed
- ⬆️ **APP_VERSION** → `2.4.0` (cache busting)

## [2.3.4] - 2026-03-02

### Fixed
- 🗓️ **Gantt Chart not showing in Single Venue Mode** — the toggle switch was hidden by `if ($currentVenueMode === 'multi')`, causing the `#viewToggle` element to not exist in the DOM, and `initializeView()` to not run
  - Removed the `venue_mode === 'multi'` condition — toggle switch now shows in all modes
  - Gantt Chart works in single venue mode (displays 1 column)

### Changed
- ⬆️ **APP_VERSION** → `2.3.4` (cache busting)

## [2.3.3] - 2026-03-02

### Fixed
- 🗓️ **Gantt Chart: programs 4+ not displaying when overlap exceeds 3** — the CSS class `stack-h-N` was designed for only 2 or 3 overlaps, but JS assigned directly from `stackIndex + 1`, causing program 4 to receive `stack-h-4` (1/3 center) which overlapped invisibly with `stack-h-1` and `stack-h-2`
  - Fixed by switching from CSS classes to inline styles dynamically calculated from `stackIndex / stackTotal`
  - Column space is divided equally among all programs regardless of count (N=4 → 25% each, N=5 → 20% each, …)
  - Removed CSS classes `stack-h-1` through `stack-h-5` (no longer used)

### Changed
- ⬆️ **APP_VERSION** → `2.3.3` (cache busting)

## [2.3.2] - 2026-03-02

### Fixed
- 🕐 **Inconsistent timezone across the system** — no timezone was defined, causing PHP to use the server timezone (Linux/Docker = UTC), resulting in `export.php` converting times incorrectly by ±7 hours
  - Added `date_default_timezone_set('Asia/Bangkok')` in `config/app.php` before all constants
- 🕐 **IcsParser discarding Z suffix** — `DTSTART:20260207T100000Z` (UTC 10:00 = Thailand 17:00) was being stored as `10:00:00` instead of `17:00:00`
  - Fixed `IcsParser::parseDateTime()` to detect the Z suffix and convert UTC → Asia/Bangkok before storing to DB

### Changed
- ⬆️ **APP_VERSION** → `2.3.2` (cache busting)

## [2.3.1] - 2026-03-02

### Fixed
- 🐛 **Bulk Edit Programs not saving to database** — `bulkUpdatePrograms()` in `admin/api.php` mixed named parameters (`:location`, `:updated_at`) with positional `?` in the same WHERE IN clause
  - PDO does not support mixing both types — `execute()` ran successfully but no rows were updated (silent fail)
  - Fixed to use only named parameters: each ID uses `:id_0`, `:id_1`, … instead of `?`

### Changed
- ⬆️ **APP_VERSION** → `2.3.1` (cache busting)

## [2.3.0] - 2026-03-02

### Added
- 📧 **Event Email Field** — added `email` column to the `events` table
  - Admin › Events form has a "Contact Email" input field
  - Stored as TEXT DEFAULT NULL; invalid email → stored as NULL (server-side `FILTER_VALIDATE_EMAIL`)
  - Migration script: `tools/migrate-add-event-email-column.php` (idempotent, safe to run multiple times)
- 📅 **ICS ORGANIZER Redesign** — changed the ORGANIZER in ICS export to represent the event/convention instead of the artist
  - `ORGANIZER;CN="Event Name":mailto:email@event.com` — following RFC 5545 semantics
  - Fallback: `noreply@stageidol.local` when no email is set (does not use the artist's email)
- 🧹 **Production Cleanup (Setup Wizard Step 6)** — system for deleting dev/docs files via `setup.php`
  - Check/delete files with grouped checkboxes (Docs, Tests, Tools, Docker, Nginx, CI/CD)
  - Whitelist-based security (prevents path traversal); locked when setup is locked
  - File groups:
    - **Docs**: `README.md`, `QUICKSTART.md`, `INSTALLATION.md`, `DOCKER.md`, `CHANGELOG.md`, `TESTING.md`, `SQLITE_MIGRATION.md`, `SECURITY.md`, `CONTRIBUTING.md`, `SETUP.md`, `API.md`, `PROJECT-STRUCTURE.md`, `LICENSE`
    - **Tests**: `tests/` directory
    - **Tools**: `tools/` directory
    - **Docker**: `Dockerfile`, `docker-compose.yml`, `docker-compose.dev.yml`, `.dockerignore`, `.env.example`
    - **Nginx**: `nginx-clean-url.conf`
    - **CI/CD**: `.github/`, `.gitignore`, `quick-test.bat`, `quick-test.sh`
- 🧪 **EventEmailTest** — 19 automated tests for the email field (637 total in the system)
  - Schema: email column nullable, TEXT type
  - CRUD: insert valid/null email, update email, update to null, read-back via SELECT *
  - Validation logic: accepts valid emails, rejects invalid/empty (returns null)
  - ICS ORGANIZER logic: uses event email, falls back to noreply, skips when no event meta
  - Migration: script exists, idempotent when column already present

### Changed
- ⬆️ **APP_VERSION** → `2.3.0` (cache busting)
- 🔧 **`tools/migrate-add-event-email-column.php`** — the migrated table is `events` (not `programs`)

## [2.2.1] - 2026-02-28

### Fixed
- 🐛 **setup.php creates programs table with wrong schema** — `CREATE TABLE programs` used `summary TEXT` instead of `title TEXT NOT NULL`, causing Admin › Programs › create new program to fail (`"Failed to create event"`) because the PDOException was hidden by `PRODUCTION_MODE`
  - Fixed `CREATE TABLE programs` to match the actual schema (`title`, `uid NOT NULL`, `start NOT NULL`, `end NOT NULL`, FK `event_id`)
  - Added migration action `fix_programs_title` in `setup.php` for DBs installed with the old setup.php
  - Added Setup Wizard UI button **"Fix programs.title"** (shown when the programs table has `summary` instead of `title`)
  - `$allTablesOk` now also checks `$hasTitleColumn`
- 🐛 **Events listing page shows empty after init database** — `$showEventListing` counted all `$activeEvents` including the default event, triggering the events listing page but skipping the default event in the card loop → empty page
  - Fixed to use `$nonDefaultEvents` (filters out the default slug first) instead of `$activeEvents` in the condition
  - When only the default event exists → fallback to directly displaying calendar view

### Added
- 🌱 **Sample programs seed on Initialize Database** — `setup.php` automatically creates 3 sample programs (Opening Ceremony, Artist Performance, Closing Stage) using today's date as start/end, so the real layout is visible immediately after a fresh install
- 📖 **Admin Help Pages updated: Default Event behavior** (`admin/help.php` + `admin/help-en.php`)
  - Added table "Default Event and Events Listing Page" describing 3 cases (default only / has real events / direct URL access)
  - Added callout explaining that the default event is intentionally hidden from the Events listing page

## [2.2.0] - 2026-02-27

### Added
- 📝 **Site Title Editable from Admin UI** — admins can change the site title via the Settings tab
  - Constant `APP_NAME` in `config/app.php` serves as the default/fallback
  - Helper `get_site_title()` in `functions/helpers.php` — reads `cache/site-settings.json` → fallback to `APP_NAME`
  - Admin API actions `title_get` / `title_save` + functions `getTitleSetting()` / `saveTitleSetting()`
  - Settings tab UI: input field + Save button (placed before the Site Theme picker)
  - All public pages: `<title>` and `<h1>` use `get_site_title()` dynamically
  - PHP injects `window.SITE_TITLE` before `translations.js` on every public page
  - ICS export: `PRODID`, `X-WR-CALNAME`, `X-WR-CALDESC` use `get_site_title()`
  - Storage: `cache/site-settings.json` — `{"site_title": "...", "updated_at": ...}` (general-purpose settings file)
- 🌐 **JS Translation Patching IIFE** in `js/translations.js`
  - Self-patching IIFE reads `window.SITE_TITLE` and replaces `'Idol Stage Timetable'` in all translation keys
  - Works automatically when the site title changes — supports all 3 languages
- 📖 **Admin Help Pages updated** to support Site Title
  - Added "📝 Site Title" subsection before "🎨 Site Theme" in the Settings section (TH + EN)
  - Updated Roles table: "Settings (Theme)" → "Settings (Title + Theme)"
  - Added FAQ: Site Title not updating after saving
- 🧪 **SiteSettingsTest** — 14 new tests (618 total in the system)
  - Tests `get_site_title()`: no cache, reads cache, empty/whitespace fallback, trim, malformed JSON
  - Tests Admin API: `title_get`/`title_save` cases, functions defined, `require_api_admin_role()` guard
  - Tests public pages: `get_site_title()` call, `window.SITE_TITLE` injection
  - Tests `js/translations.js`: has IIFE patching block
  - Tests `APP_NAME` constant is defined and non-empty

### Changed
- 🌐 **`header.subtitle` EN** changed from `'Idol Stage Timetable'` → `'Event Schedule'`
  - Makes the subtitle descriptive like TH (`'ตารางกิจกรรม Idol Stage'`) and JA (`'アイドルステージタイムテーブル'`)
  - The brand name remains only in `header.title`

## [2.1.1] - 2026-02-27

### Added
- 🎨 **Per-Event Theme** — assign a separate color theme per event
  - `theme TEXT DEFAULT NULL` column in the `events` table
  - `get_site_theme($eventMeta = null)` accepts event meta to resolve the theme by priority:
    1. Event-specific theme (`events.theme`) — if set and valid
    2. Global theme (Settings tab, `cache/site-theme.json`)
    3. Default fallback: `dark`
  - Admin Event form has a theme picker (🌸 Sakura / 🌊 Ocean / 🌿 Forest / 🌙 Midnight / ☀️ Sunset / 🖤 Dark / 🩶 Gray)
  - All public pages pass `$eventMeta` to `get_site_theme()`: `index.php`, `credits.php`, `how-to-use.php`, `contact.php`
  - Migration script: `tools/migrate-add-theme-column.php` (idempotent)
  - Setup wizard support: fresh install creates the `theme` column automatically; existing install has a "+ theme column" button
- 🧪 **ThemeTest added 8 tests** (24 total / 464 in system)
  - Tests priority: event → global → dark fallback
  - Tests null/empty/invalid event theme fallback
  - Tests Admin API supports the theme field

### Changed
- 🎨 **Default theme fallback** changed from `sakura` → `dark`
  - `sakura` is only the base CSS in `common.css` (it has no override file of its own)
  - If no Global theme is set and the Event has no theme → uses `dark` theme

## [2.1.0] - 2026-02-27

### Added
- 🎨 **Theme System** — admin sets a color theme for all public pages
  - Theme CSS files: `ocean.css` 🌊 Blue, `forest.css` 🌿 Green, `midnight.css` 🌙 Purple, `sunset.css` ☀️ Orange, `dark.css` 🖤 Charcoal, `gray.css` 🩶 Silver
  - "⚙️ Settings" tab in Admin panel (admin role only) with theme picker UI
  - Admin API: `theme_get`, `theme_save` actions in `admin/api.php`
  - Helper: `get_site_theme()` in `functions/helpers.php` (reads `cache/site-theme.json` + validates + fallback to sakura)
  - Public pages load theme CSS server-side in `<head>`
- 📖 **Admin Help Pages — fully updated to cover all features** (`admin/help.php` Thai + `admin/help-en.php` English)
  - Added ⚙️ Settings section: describes Site Theme, 7 available themes, steps to change theme
  - Updated overview: 8 tabs (added Settings), tab chips with full emoji icons
  - Updated Roles table: added Settings (Theme) row — admin ✅, agent ❌
  - Added FAQ: Changed theme but page color didn't change
  - TOC (mobile + desktop): added Settings link, renamed "Import ICS" → "Import"

### Changed
- 🎨 **CSS Extracted to External Files** — moved inline `<style>` blocks from PHP files to external CSS files
  - `index.php` → `styles/index.css` (file size reduced from ~90KB → ~43KB)
  - `credits.php` → `styles/credits.css`
  - `how-to-use.php` → `styles/how-to-use.css`
- 🧭 **Admin Nav Icons** — added emoji icons to all tabs in Admin panel (desktop + mobile)
  - 🎵 Programs, 🎪 Events, 📝 Requests, ✨ Credits, 📤 Import, 👤 Users, 💾 Backup, ⚙️ Settings
  - Renamed "Import ICS" → "Import" in nav (section content still describes ICS format)

## [2.0.1] - 2026-02-27

### Changed
- ⚙️ **Google Analytics ID configurable** — moved the Measurement ID from being hardcoded in each PHP file to a setting in `config/app.php`
  - Added constant `GOOGLE_ANALYTICS_ID` — set to `''` to disable Analytics
  - Updated `index.php`, `how-to-use.php`, `contact.php`, `credits.php` to use the constant instead of hardcoded values

## [2.0.0] - 2026-02-27

### ⚠️ Breaking Changes
- 🗄️ **Database Schema Rename** — renamed all tables/columns **(must run migration script)**
  - Table `events` → `programs` (individual shows)
  - Table `events_meta` → `events` (meta events/conventions)
  - Table `event_requests` → `program_requests`
  - Column `programs.event_meta_id` → `programs.event_id` (FK to events)
  - Column `program_requests.event_id` → `program_requests.program_id` (FK to programs)
  - Column `program_requests.event_meta_id` → `program_requests.event_id` (FK to events)
  - Column `credits.event_meta_id` → `credits.event_id` (FK to events)
  - Migration script: `tools/migrate-rename-tables-columns.php` (idempotent)
- 🔌 **API Action Names Renamed**
  - Public API: `action=events` → `action=programs`
  - Admin API Programs: `list`→`programs_list`, `get`→`programs_get`, `create`→`programs_create`, `update`→`programs_update`, `delete`→`programs_delete`, `venues`→`programs_venues`, `bulk_delete`→`programs_bulk_delete`, `bulk_update`→`programs_bulk_update`
  - Admin API Events: `event_meta_list`→`events_list`, `event_meta_get`→`events_get`, `event_meta_create`→`events_create`, `event_meta_update`→`events_update`, `event_meta_delete`→`events_delete`
  - Request API: `action=events` → `action=programs`
- 🏷️ **Terminology Rename** — renamed terminology throughout the system
  - "Events" (individual shows) → **"Programs"**
  - "Conventions" → **"Events"**

### Added
- 🛠️ **Setup Wizard** (`setup.php`) — interactive system installer for fresh install and maintenance
  - 5 steps: System Requirements → Directories → Database → Import Data → Admin & Security
  - Auto-login after Initialize Database, inline password change, default credentials box
  - Lock/Unlock mechanism (`data/.setup_locked`), Auth gate (no login required for fresh install)
- 📖 **Admin Help Pages** — Admin Panel user guide
  - `admin/help.php` (Thai) + `admin/help-en.php` (English) with language switcher
  - Covers: Overview, Login, Header, Programs, Events, Requests, Credits, Import ICS, Users, Backup, Roles & Permissions, Tips & FAQ
  - "📖 Help" button in Admin header
- ⚡ **Database Indexes** (`tools/migrate-add-indexes.php`) — 7 indexes for 2-5x speed improvement
  - `idx_programs_event_id`, `idx_programs_start`, `idx_programs_location`, `idx_programs_categories` on `programs` table
  - `idx_program_requests_status`, `idx_program_requests_event_id` on `program_requests` table
  - `idx_credits_event_id` on `credits` table
  - Migration script is idempotent (`CREATE INDEX IF NOT EXISTS`)
- 🚦 **Login Rate Limiting** — limits login to no more than 5 attempts/15 minutes/IP
  - Functions: `check_login_rate_limit()`, `record_failed_login()`, `clear_login_attempts()`
  - Stores data in `cache/login_attempts.json`, displays remaining wait time
- 🔑 **`get_db()` Singleton** (`functions/helpers.php`) — PDO singleton for web context (1 connection/request)
- `tools/migrate-rename-tables-columns.php` — Migration script (idempotent) for existing databases

### Changed
- 📱 **Admin UI Mobile Responsive** — full mobile support (iOS + Android)
  - iOS Auto-Zoom Fix: date input `font-size: 0.9rem → 1rem` (prevents iOS zoom when focused)
  - Touch Targets: modal-close button `32×32px → 44×44px`, checkboxes `18px → 20px`, btn-sm `min-height: 40px`
  - Hamburger Tab Menu: dropdown navigation on mobile (≤600px) with badge + active state
  - Table Scroll Fix: wrapper div pattern (`<div class="table-scroll-wrapper">`) prevents iOS scroll capture
  - 3 Breakpoints: 768px (tablet), 600px (small phone), 480px (very small phone)
  - Help page TOC mobile: Sidebar hidden on mobile, uses collapsible dropdown instead
- 🌐 **HTTP Cache Headers** (`api.php`) — ETag + Cache-Control + 304 Not Modified
  - Programs/organizers/locations: max-age=300 (5 minutes), events_list: max-age=600 (10 minutes)
- ⚡ **Pre-computed Timestamps** (`index.php`) — `start_ts`/`end_ts` calculated once per record
  - Reduces repeated `strtotime()` calls in loops from 6 locations → calculated once per record
- 🌐 **Translation Updates** (`js/translations.js`) — updated for 3 languages (TH/EN/JA)
  - Key renames: `message.noEvents`→`message.noPrograms`, `table.event`→`table.program`, `gantt.noEvents`→`gantt.noPrograms`, `modal.eventName`→`modal.programName`
- 🎨 **CSS Class Renames** — `.event-*`→`.program-*`, `.gantt-event-*`→`.gantt-program-*`
- 🔧 **PHP Backend Function Renames**
  - `admin/api.php`: `listEvents()`→`listPrograms()`, `getEvent()`→`getProgram()`, `createEvent()`→`createProgram()`, `updateEvent()`→`updateProgram()`, `deleteEvent()`→`deleteProgram()`, `bulkDeleteEvents()`→`bulkDeletePrograms()`, `bulkUpdateEvents()`→`bulkUpdatePrograms()`
  - `admin/api.php`: `listEventMeta()`→`listEvents()`, `getEventMeta()`→`getEvent()`, `createEventMeta()`→`createEvent()`, `updateEventMeta()`→`updateEvent()`, `deleteEventMeta()`→`deleteEvent()`
  - `functions/helpers.php`: `get_event_meta_by_slug()`→`get_event_by_slug()`, `get_event_meta_id()`→`get_event_id()`
- ⚙️ **Admin Panel Tab Renames**: "Events"→"Programs", "🏟️ Conventions"→"🏟️ Events"
- `config/app.php`: APP_VERSION → '2.0.0'

### Documentation
- 🔌 **[API.md](API.md)** — complete API endpoint documentation (Public / Request / Admin APIs) with request/response examples
- 📁 **[PROJECT-STRUCTURE.md](PROJECT-STRUCTURE.md)** — file structure + function list + config constants + file relationships
- 📖 **[SETUP.md](SETUP.md)** — comprehensive Setup Wizard user guide
- Updated README, QUICKSTART, INSTALLATION, SQLITE_MIGRATION, TESTING to match the new schema

### Migration Guide (from v1.2.5)
```bash
# 1. Run schema migration (Breaking change — must do this first)
php tools/migrate-rename-tables-columns.php

# 2. Add database indexes (performance)
php tools/migrate-add-indexes.php
```

### Testing
- 🧪 **324 automated tests** — all passing (PHP 8.1, 8.2, 8.3)

## [1.2.5] - 2026-02-18

### Added

- 👤 **User Management System** — manage admin users through the Admin panel
  - "👤 Users" tab in Admin panel (shown only for admin role)
  - User table: ID, Username, Display Name, Role, Active, Last Login, Actions
  - Create new user: username, password (min 8 chars), display_name, role, is_active
  - Edit user: password optional, username cannot be changed
  - Delete user: cannot delete self, must keep at least 1 admin
  - API endpoints: `users_list`, `users_get`, `users_create`, `users_update`, `users_delete`

- 🛡️ **Role-Based Access Control** — role-based permission system
  - 2 roles: `admin` (full access) and `agent` (events management only)
  - `admin` role: access all tabs + manage users + backup/restore
  - `agent` role: access only Events, Requests, Import ICS, Credits, Conventions
  - Defense in depth: PHP hides HTML elements + API-level role checks
  - Prevents lockout: cannot delete self, cannot change own role, cannot deactivate self
  - Must always have at least 1 active admin
  - Config fallback users always have admin role (backward compatible)
  - Role badge shown next to username in header
  - Helper functions: `get_admin_role()`, `is_admin_role()`, `require_admin_role()`, `require_api_admin_role()`
  - Migration script: `tools/migrate-add-role-column.php`

### Changed
- `functions/admin.php`: added `$_SESSION['admin_role']` in `admin_login()` + 4 role helper functions
- `admin/api.php`: added admin-only action gate for backup/users actions + 5 user CRUD endpoints
- `admin/index.php`: added Users tab/modal + hides Users/Backup tabs from agent role
- `config/app.php`: APP_VERSION → '1.2.5'

### Testing
- 🧪 **226 automated tests** (up from 207) — added 19 tests in `UserManagementTest.php`
  - Schema tests: role column, default values
  - Role helper tests: `get_admin_role()`, `is_admin_role()`
  - User CRUD tests: create, update, delete, validation
  - Permission tests: admin-only actions, agent restrictions

## [1.2.4] - 2026-02-17

### Added

- 🔐 **Database-based Admin Authentication** — moved credentials from config to SQLite
  - `admin_users` table supports multiple admin users (username, password_hash, display_name, is_active)
  - Login tries DB first → fallback to config constants (backward compatible)
  - Records `last_login_at` on every successful login
  - Dummy `password_verify` when username not found to prevent timing attacks
  - Migration script: `tools/migrate-add-admin-users-table.php`

- 🔑 **Change Password UI** — change password via Admin panel
  - "🔑 Change Password" button in Admin header (shown only for DB users)
  - Modal form: current password + new password + confirm password
  - Validation: must enter current password, new password minimum 8 characters
  - API endpoint: `POST ?action=change_password`

### Fixed
- 🐛 **Backup Delete Fix** — fixed issue where deleting a backup file showed "Invalid filename"
  - Changed HTTP method from DELETE to POST (Apache/Windows don't send body in DELETE request)
  - Fixed JS variable scope bug: `closeDeleteBackupModal()` was clearing the filename variable before `fetch` could use it
  - Saves filename as a local variable before closing the modal

### Changed
- `functions/admin.php`: added 4 functions (`admin_users_table_exists`, `get_admin_user_by_username`, `update_admin_last_login`, `change_admin_password`) + fixed `admin_login()` to read from DB first
- `config/admin.php`: `ADMIN_USERNAME` / `ADMIN_PASSWORD_HASH` are now fallback (deprecation comment)
- `tools/generate-password-hash.php`: recommends 3 methods to change password (Admin UI, config, SQL)
- `admin/api.php`: changed backup delete from DELETE to POST method
- Added 6 new tests (207 total from 189)

## [1.2.3] - 2026-02-17

### Added

- 💾 **Backup/Restore System** — manage database backups through Admin UI
  - **Backup Tab**: new "💾 Backup" tab in Admin panel
  - **Create Backup**: creates a .db backup file and saves it on the server in `backups/`
  - **Download Backup**: downloads backup file to local machine
  - **Restore from Server**: choose to restore from a backup file stored on the server
  - **Upload & Restore**: upload a .db file from local machine to restore
  - **Delete Backup**: delete unwanted backup files
  - **Auto-Backup Safety**: automatically creates an auto-backup before every restore
  - **SQLite Validation**: verifies the SQLite header before restore
  - **Path Traversal Protection**: prevents path traversal attacks in filename

- 📂 **Database Directory Restructure** — reorganized directory structure
  - **`data/`**: moved `calendar.db` to `data/calendar.db`
  - **`backups/`**: stores backup files separately in `backups/` directory
  - **DB_PATH Constant**: uses `DB_PATH` constant instead of hardcoded path throughout the system
  - **Docker Updated**: updated docker-compose.yml to mount volume as `data/`

### Changed
- `config/database.php`: DB_PATH points to `data/calendar.db`
- `admin/api.php`: uses `DB_PATH` constant, backup dir changed to `backups/`
- `functions/cache.php`: added `invalidate_all_caches()` for restore
- Updated migration tools, tests, Docker files to use the new path

## [1.2.1] - 2026-02-12

### Added

- 🔗 **Clean URL Rewrite** - Remove `.php` extension from all public URLs
  - **`.htaccess`**: Apache rewrite rules for clean URLs and event path routing
  - **`nginx-clean-url.conf`**: Nginx configuration example for clean URLs
  - **Event Path Routing**: `/event/slug` → `index.php?event=slug`, `/event/slug/credits` → `credits.php?event=slug`
  - **Backward Compatible**: Old `.php` URLs still work
  - **Admin URLs unchanged**: `/admin/` paths remain as-is
  - **Updated `event_url()`**: Generates clean URLs (`/credits` instead of `/credits.php`)

- 📅 **Date Jump Bar** - Quick navigation between days in multi-day events
  - Fixed-position bar appears when scrolling past the calendar area
  - Shows day/month and weekday name for each date
  - Smooth scroll with offset for fixed bar height
  - IntersectionObserver highlights current visible date
  - Responsive design for mobile
  - Translatable label in all 3 languages

- 📦 **ICS Import Event Selector** - Choose target convention when importing ICS files
  - Dedicated dropdown in ICS upload area to select target convention
  - Convention name badge shown in preview stats

- 📋 **Admin Credits Per-Event** - Assign credits to specific conventions
  - Convention selector dropdown in credit create/edit form
  - Supports global credits (null = shown in all conventions)

- 🌏 **Complete i18n for Request Modal** - All form elements fully translated
  - 20 new translation keys for request modal (labels, buttons, messages) in TH/EN/JA
  - `data-i18n` attributes on all form labels and buttons
  - JavaScript alert/confirm messages use translation system
  - Added missing `credits.list.title` and `credits.noData` keys

### Changed
- Updated `event_url()` to generate clean event paths (`/event/slug/page`)
- Updated `exportToIcs()` to use clean URL paths
- Updated inline JS API calls to use clean URLs (`api/request` instead of `api/request.php`)

### Testing
- 🧪 **189 automated tests** (up from 187) - Added clean URL routing tests

## [1.2.0] - 2026-02-11

### Added

- 🎪 **Multi-Event (Conventions) Support** - Manage multiple events/conventions in one system
  - **New Table**: `events_meta` for storing convention metadata (name, slug, dates, venue_mode, is_active)
  - **Convention Management**: Full CRUD for conventions via new "Conventions" tab in admin panel
  - **Event Scoping**: Each event, request, and credit can belong to a specific convention
  - **URL-based Selection**: Access conventions via `?event=slug` URL parameter
  - **Convention Selector**: Dropdown in header to switch between conventions (public + admin)
  - **Per-Convention Venue Mode**: Each convention can have its own `multi` or `single` venue mode
  - **Backward Compatible**: Existing data works without migration (null event_meta_id = global)
  - **Feature Flag**: `MULTI_EVENT_MODE` constant to enable/disable multi-event features
  - **Migration Script**: `tools/migrate-add-events-meta-table.php` creates tables and migrates existing data
  - **New Config Constants**: `DEFAULT_EVENT_SLUG`, `MULTI_EVENT_MODE` in `config/app.php`
  - **New Helper Functions**: `get_current_event_slug()`, `get_event_meta_by_slug()`, `get_event_meta_id()`, `get_all_active_events()`, `get_event_venue_mode()`, `event_url()`
  - **Admin API Endpoints**: `event_meta_list`, `event_meta_get`, `event_meta_create`, `event_meta_update`, `event_meta_delete`
  - **Public API**: New `events_list` action returns all active conventions; all actions support `?event=slug` filtering
  - **ICS Import**: `--event=slug` argument for CLI import tool
  - **Cache Scoping**: Data version and credits cache scoped per convention
  - **15 New Tests**: Multi-event helper functions, IcsParser filtering, cache scoping (total: 187 tests)

## [1.1.0] - 2026-02-11

### Added

- 🐳 **Docker Support** - One-command deployment with Docker Compose
  - **Dockerfile**: PHP 8.1-apache with PDO SQLite, auto-creates directories and imports data
  - **docker-compose.yml**: Production setup with port 8000, volume mounts (ics, cache, database)
  - **docker-compose.dev.yml**: Development mode with live reload and error display
  - **.dockerignore**: Optimized build exclusions for smaller image size
  - **Health Check**: Built-in container health monitoring
  - **Auto-Setup**: Automatically creates tables and imports ICS files on first run
  - **DOCKER.md**: Comprehensive Docker deployment guide (Quick Start, Production, Development, Advanced)

- 📋 **Credits Management System** - Complete CRUD system for managing credits and references
  - **Database Table**: SQLite table with fields: id, title, link, description, display_order, created_at, updated_at
  - **Admin UI**: New "Credits" tab in admin panel with full management interface
    - Create, Read, Update, Delete operations
    - Search functionality with 300ms debounce
    - Sortable columns (ID, Title, Display Order)
    - Pagination with 20/50/100 per page options
    - Bulk selection with master checkbox
    - Bulk delete up to 100 credits at once
  - **Admin API**: 6 RESTful endpoints
    - `credits_list` - List with pagination, search, sorting
    - `credits_get` - Get single credit
    - `credits_create` - Create new credit
    - `credits_update` - Update existing credit
    - `credits_delete` - Delete single credit
    - `credits_bulk_delete` - Bulk delete with transaction support
  - **Public Display**: credits.php now loads from database instead of hardcoded HTML
  - **Validation**: Title required (max 200 chars), description optional (max 1000 chars)
  - **Migration Script**: `tools/migrate-add-credits-table.php` for database setup

- 🔄 **Cache System for Credits** - Performance optimization for credits page
  - **Cache Function**: `get_cached_credits()` in `functions/cache.php`
  - **TTL**: 1 hour (3600 seconds) configurable via `CREDITS_CACHE_TTL`
  - **Cache File**: `cache/credits.json` with timestamp and data
  - **Auto-Invalidation**: Cache automatically cleared on create/update/delete operations
  - **Fallback**: Returns empty array on cache miss or database error
  - **Performance**: Reduces database queries for frequently accessed credits data
  - **Configuration**: Settings in `config/cache.php`

- 📦 **Bulk Operations** - Admin can now manage multiple events simultaneously
  - Checkbox selection with master checkbox (select all/deselect all)
  - Bulk Delete - Delete up to 100 events at once with confirmation
  - Bulk Edit - Update venue, organizer, and categories for multiple events
  - Selection count display in bulk actions toolbar
  - Transaction handling with partial failure support
  - Visual feedback with selected row highlighting
  - Indeterminate checkbox state for partial selections

- 🎯 **Flexible Venue Entry** - Add new venues without limitations
  - Changed from `<select>` dropdown to `<input>` with `<datalist>`
  - Autocomplete suggestions from existing venues
  - Ability to type new venue names on-the-fly
  - Applies to both single event form and bulk edit modal

- 📤 **ICS Upload & Import** - Upload ICS files directly through Admin UI
  - File upload with validation (max 5MB, .ics files only)
  - MIME type checking (text/calendar, text/plain, application/octet-stream)
  - Preview parsed events before importing
  - Duplicate detection (checks against existing UIDs in database)
  - Per-event action: insert, update, or skip
  - Option to save uploaded file to `ics/` folder
  - Import statistics (inserted, updated, skipped, errors)

- 📊 **Per-Page Selector** - Customize events displayed per page
  - Options: 20, 50, or 100 events per page
  - Auto-reset to page 1 when changing page size
  - Works seamlessly with filters, search, and sorting
  - Dropdown integrated in admin toolbar

- 🎨 **Admin UI Improvements**
  - Professional Blue/Gray color scheme (distinct from user-facing Sakura theme)
  - Enhanced gradient header with icon
  - Card-style tab navigation
  - Improved contrast and readability
  - Fixed username and table header visibility issues

### Changed
- **Cache Configuration** (`config/cache.php`)
  - Added `CREDITS_CACHE_FILE` and `CREDITS_CACHE_TTL` constants
  - Organized cache settings for both data version and credits

- **Cache Functions** (`functions/cache.php`)
  - Added `get_cached_credits()` - Fetch credits with caching
  - Added `invalidate_credits_cache()` - Clear cache after modifications
  - Maintained existing `get_data_version()` function

- **Credits Display** (`credits.php`)
  - Replaced hardcoded HTML with database-driven dynamic content
  - Uses `get_cached_credits()` for optimized loading
  - Proper HTML escaping with `htmlspecialchars()`
  - Support for optional fields (link, description)
  - Empty state handling when no credits exist

- **Admin API** (`admin/api.php`)
  - Added 6 new switch cases for credits operations
  - Cache invalidation after all state-changing operations
  - Consistent error handling and JSON responses

- **Bulk Edit API** (`admin/api.php`)
  - Added support for categories field alongside venue and organizer
  - Validation ensures at least one field is provided
  - Dynamic UPDATE query construction based on provided fields
  - Maximum 100 events per bulk operation for performance

- **Admin Event List** (`admin/index.php`)
  - Added checkbox column to events table
  - Enhanced toolbar with bulk actions bar (shows when events selected)
  - Improved state management with `perPage` variable
  - Better pagination with customizable limits

### Security
- 🔒 **Enhanced Input Sanitization** - Comprehensive protection against XSS and injection attacks
  - **New Functions** in `functions/security.php`:
    - `sanitize_string()` - Remove null bytes, trim, length limits
    - `sanitize_string_array()` - Sanitize array inputs with max items limit
    - `get_sanitized_param()` - Safe GET parameter extraction (string)
    - `get_sanitized_array_param()` - Safe GET parameter extraction (array)
  - **Applied to**: `index.php`, `export.php`, `admin/api.php`
  - **Parameters sanitized**: artist, venue, search, date filters
  - **Protection**: Max length validation, null byte removal, array size limits

- 🛡️ **Session Security Improvements** - Complete rewrite of session management (`functions/admin.php`)
  - **Timing Attack Prevention**: Use `hash_equals()` for username comparison (constant-time)
  - **Session Fixation Prevention**: `session_regenerate_id()` before login and logout
  - **Session Timeout**: Automatic logout after 2 hours of inactivity (configurable)
  - **Secure Cookies**: httponly, secure (HTTPS), SameSite=Strict attributes
  - **Session Validation**: Check timeout on every request
  - **New constant**: `SESSION_TIMEOUT` in `config/admin.php` (default: 7200 seconds)

- 🔐 **JSON Security** - Safe JSON encoding in HTML attributes
  - **Changed**: `htmlspecialchars(json_encode())` → `json_encode()` with security flags
  - **Flags used**: `JSON_HEX_QUOT`, `JSON_HEX_TAG`, `JSON_HEX_AMP`, `JSON_HEX_APOS`
  - **Benefit**: No JSON structure corruption, safe in HTML attributes
  - **Applied to**: `index.php` request modal data attributes

- **Credits System Security**
  - All credits API endpoints protected by authentication (`require_api_login()`)
  - CSRF token validation for create/update/delete operations
  - SQL injection prevention via PDO prepared statements
  - XSS prevention via `htmlspecialchars()` on output
  - Input validation (required fields, length limits)
  - Rate limiting inherited from admin panel
  - Transaction rollback on bulk operation failures

- **General Security**
  - All bulk operations protected by CSRF tokens
  - Input validation for bulk IDs (max 100, integer sanitization)
  - Transaction rollback on errors
  - Prepared statements for all database operations
  - Safe session handling with race condition prevention

### Testing
- 🧪 **Automated Test Suite** - 187 comprehensive unit tests
  - **Test Framework**: Custom lightweight TestRunner with 20 assertion methods
  - **SecurityTest** (15 tests): Input sanitization, XSS protection, null byte injection, SQL injection prevention
  - **CacheTest** (11 tests): Cache creation, TTL, invalidation, hit/miss, error fallback
  - **AdminAuthTest** (15 tests): Session management, login/logout, timing attack resistance, password verification
  - **CreditsApiTest** (13 tests): Database CRUD, bulk operations, SQL injection protection, display order sorting
  - **IntegrationTest** (118 tests): File structure validation, configuration checks, full workflow simulation, API endpoints
  - **Test Runner**: `tests/run-tests.php` with colored output, test filtering, detailed statistics
  - **Quick Tests**: `quick-test.sh` (Linux/Mac) and `quick-test.bat` (Windows) for pre-commit testing
  - **CI/CD**: GitHub Actions workflow (`.github/workflows/tests.yml`) for automated testing on push/PR
    - Matrix testing across **PHP 8.1, 8.2, and 8.3** (all tests pass)
    - Separate jobs for security and integration tests
    - Automatic test result artifact upload on failure
  - **Documentation**:
    - `tests/README.md` - Automated testing guide (usage, assertions, writing tests, troubleshooting)
    - `TESTING.md` - Manual testing checklist with 129 test cases

### Documentation
- Updated CLAUDE.md with credits management and testing features
- Updated README.md with cache system and testing information
- Updated QUICKSTART.md with testing section and quick test commands
- Updated INSTALLATION.md with testing & QA procedures, pre-production checklist
- Added credits migration script to tools documentation
- Updated file structure diagrams to include cache/ and tests/ directories
- Added TESTING.md with 129 manual test cases covering all features
- Added tests/README.md with comprehensive automated testing guide

## [1.0.0] - 2026-02-09

### Added
- 🌸 **Sakura Theme** - Beautiful cherry blossom theme with Japanese aesthetics
- 🌏 **Multi-language Support** - Thai, English, and Japanese (日本語) with proper html lang attributes
- 📱 **Responsive Design** - Full support for all screen sizes including iOS devices
- 📊 **Dual View Modes**
  - List View: Traditional table layout with full details
  - Gantt Chart View: Horizontal timeline showing event overlaps across venues
- 🔍 **Advanced Filtering**
  - Search by artist/performer name (with auto-select and clear button)
  - Filter by multiple artists
  - Filter by multiple venues
  - Selected tags display with one-click removal
- 📸 **Image Export** - Save calendar as PNG image (lazy-loaded html2canvas)
- 📅 **ICS Export** - Export filtered events to calendar apps (Google Calendar, Apple Calendar, etc.)
- 📝 **User Request System**
  - Users can request to add new events
  - Users can request to modify existing events
  - Rate limiting (10 requests per hour per IP)
  - Request form with pre-filled data for modifications
- ⚙️ **Admin Panel**
  - Full CRUD operations for events
  - Request management (approve/reject user requests)
  - Side-by-side comparison view for modification requests
  - Highlight changed fields (yellow) and new fields (green)
  - Search and filter by venue
  - Pagination support
  - Session-based authentication
  - Optional IP whitelist
- ⚡ **SQLite Database Support**
  - 10-20x faster than parsing ICS files
  - Efficient querying and filtering
  - Auto-generated unique IDs
  - Timestamps for created_at and updated_at
- 🔄 **Cache Busting** - Version-based cache control for CSS/JS files
- 🔒 **Security Features**
  - XSS Protection (server-side and client-side)
  - CSRF token validation
  - Security headers (CSP, X-Content-Type-Options, X-Frame-Options, etc.)
  - Rate limiting for API requests
  - Input validation and sanitization
  - Prepared statements (SQL injection protection)
- 🗂️ **ICS File Support** - Parse and display events from multiple ICS files
- 🌊 **iOS Scroll Indicators** - Gradient shadows on timeline for better UX on iOS
- 📊 **Auto Data Version** - Displays last update time from database

### Fixed
- **Critical Password Hash Bug** - Fixed admin login system that was broken due to password hash regenerating on every page load
  - Changed from dynamic `password_hash()` call to static hash constant
  - Added clear instructions in SECURITY.md for generating password hash
  - Prevents login failures caused by changing hash values
- **Missing `is_logged_in()` function** - Restored function that was accidentally omitted during config reorganization
- iOS Timeline Header Bug - Fixed venue headers not showing for 5+ venues on iOS Safari
  - Added explicit min-width to prevent compositing issues
  - Moved horizontal scroll to parent container
  - Fixed header/body desync on iOS
- Events sorting - Events now properly sorted by time after admin approval
- Modal overflow - Modals now scrollable on small screens
- PHP 7.0 compatibility - Replaced arrow functions with anonymous functions
- Navigation buttons i18n - All nav buttons now properly change language
- IcsParser - Now returns event `id` field for proper tracking

### Changed
- **Reorganized configuration system** for better maintainability:
  - Split monolithic `config.php` into modular structure
  - Created `config/` folder with categorized configuration files:
    - `config/app.php` - Application settings (version, production mode)
    - `config/admin.php` - Authentication & admin settings
    - `config/security.php` - Security & rate limiting
    - `config/database.php` - Database configuration
    - `config/cache.php` - Cache settings
  - Created `functions/` folder with categorized helper functions:
    - `functions/helpers.php` - General utilities
    - `functions/cache.php` - Cache-related functions
    - `functions/admin.php` - Authentication functions
    - `functions/security.php` - Security functions
  - Root `config.php` now acts as bootstrap file loading all configs
- **Reorganized file structure** with dedicated folders:
  - `styles/` for shared CSS
  - `js/` for shared JavaScript
  - `tools/` for development utilities
  - `admin/` for admin interface
  - `api/` for public APIs
- Removed redundant "Back" buttons
- Improved filter UX with selected tags display
- Enhanced admin comparison view for better change visibility

### Documentation
- README.md - Comprehensive feature documentation (updated for new config structure)
- INSTALLATION.md - Detailed installation guide with multiple deployment options
- QUICKSTART.md - 3-step quick start guide
- SQLITE_MIGRATION.md - Database migration and performance guide
- CHANGELOG.md - Version history (this file)
- LICENSE - MIT License
- CONTRIBUTING.md - Contribution guidelines
- SECURITY.md - Security policy and deployment best practices
