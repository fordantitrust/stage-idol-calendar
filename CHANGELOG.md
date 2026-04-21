# Changelog

All notable changes to Idol Stage Timetable will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [6.4.0] - 2026-04-21

### Added
- **Google Admin UI** — Google Analytics & Google AdSense configurable via Admin UI › Settings › 🔵 Google sub-tab; same pattern as Telegram config (JSON file + PHP loader + API)
- **`config/google-config.json`** — stores `ga_id`, `ads_client`, `ads_slot_leaderboard`, `ads_slot_rectangle`, `ads_slot_responsive`; protected by existing `config/.htaccess` (`Deny from all` for `.json`)
- **`analytics_config_get` / `analytics_config_save`** API endpoints in `admin/api.php` — admin-role required; reads/writes `config/google-config.json`; fields HTML-escaped on output; `updated_at` timestamp on save
- **`loadAnalyticsSetting()` / `saveAnalyticsSetting()`** JS functions in `admin/index.php` — auto-loaded with Settings tab; populate/save all 5 fields; uses `decodeHtml()` pattern from other settings
- **i18n keys** in `admin/js/admin-i18n.js` — `settings.subtab.analytics`, `settings.analytics*` keys in both TH and EN dictionaries

### Changed
- **`config/analytics.php`** renamed to **`config/google.php`** — combined GA + AdSense loader; reads from `config/google-config.json` (like `config/telegram.php`), falling back to empty strings; constants `GOOGLE_ANALYTICS_ID`, `GOOGLE_ADS_CLIENT`, `GOOGLE_ADS_SLOT_*` unchanged for backward compatibility
- **Admin UI sub-tab** label changed from `📊 Analytics` to `🔵 Google`

### Documentation
- **Admin Help updated (TH + EN)** — `admin/help.php` and `admin/help-en.php` updated to cover v6.4.0:
  - Settings sub-tabs table updated from 6 → **7 sub-tabs**; added `🔵 Google` row (between Telegram and Disclaimer)
  - New `🔵 Google Services (v6.3.0–6.4.0)` section covering Google Analytics (GA4 Measurement ID) and Google AdSense (Publisher ID + 3 slot IDs), setup steps, and security callouts
  - Roles table updated: `Settings (Title + Theme + Disclaimer)` → `Settings (Title + Theme + Google + Disclaimer)`
- **All .md files updated** — README.md, API.md, PROJECT-STRUCTURE.md, SECURITY.md, SETUP.md, INSTALLATION.md, TESTING.md updated with v6.2.0–v6.4.0 entries (Sitemap, AdSense, Google Admin UI), new file listings, API endpoint docs, security checklist, and test counts

### Files Changed
**New files:**
- `config/google.php` — (renamed from `config/analytics.php`) GA + AdSense loader from JSON
- `config/google-config.json` — JSON config for GA + AdSense

**Modified files:**
- `config.php` — updated require to `config/google.php`
- `admin/api.php` — added `analytics_config_get` + `analytics_config_save` endpoints + functions; config file path updated to `google-config.json`
- `admin/index.php` — added 🔵 Google sub-tab button + HTML panel + JS functions + loader call
- `admin/js/admin-i18n.js` — added analytics i18n keys (TH + EN); sub-tab label updated to 🔵 Google
- `admin/help.php` — Settings sub-tabs (6→7), 🔵 Google section, Roles table
- `admin/help-en.php` — Settings sub-tabs (6→7), 🔵 Google section, Roles table
- `config/app.php` — version bump to 6.4.0

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

## [6.3.0] - 2026-04-21

### Added
- **Google AdSense System** — monetization system with enable/disable identical to Google Analytics (`GOOGLE_ADS_CLIENT = ''` = disabled)
- **`render_ad_unit(string $slot, string $class = ''): void`** helper in `functions/ads.php` — renders `<ins class="adsbygoogle">` with correct attributes; silently skips if publisher ID or slot ID is empty; supports 3 slot types: `leaderboard` (728×90), `rectangle` (300×250), `responsive` (auto)
- **AdSense `<head>` script** added to all 7 public pages (`index.php`, `artist.php`, `artists.php`, `how-to-use.php`, `credits.php`, `contact.php`, `past-events.php`) — loaded only when `GOOGLE_ADS_CLIENT` is set
- **8 ad placements** across public pages: leaderboard after event-detail header (`index.php`), responsive before cross-event section (`index.php`), rectangle after artist header (`artist.php`), leaderboard after header (`artists.php`), responsive before footer on `how-to-use.php`, `credits.php`, `contact.php`
- **`.ads-unit` CSS classes** in `styles/common.css` — `.ads-leaderboard` (max-width 728px), `.ads-rectangle` (max-width 336px), `.ads-responsive` (full width)
- **4 new constants** in `config/google.php` — `GOOGLE_ADS_CLIENT`, `GOOGLE_ADS_SLOT_LEADERBOARD`, `GOOGLE_ADS_SLOT_RECTANGLE`, `GOOGLE_ADS_SLOT_RESPONSIVE`

### Files Changed
**New files:**
- `functions/ads.php` — `render_ad_unit()` helper function

**Modified files:**
- `config/google.php` — added `GOOGLE_ADS_CLIENT` + 3 slot constants
- `config.php` — added `require_once functions/ads.php`
- `index.php` — AdSense head script + 2 ad placements
- `artist.php` — AdSense head script + 1 ad placement
- `artists.php` — AdSense head script + 1 ad placement
- `how-to-use.php` — AdSense head script + 1 ad placement
- `credits.php` — AdSense head script + 1 ad placement
- `contact.php` — AdSense head script + 1 ad placement
- `past-events.php` — AdSense head script (no placement)
- `styles/common.css` — added `.ads-unit` CSS classes
- `config/app.php` — version bump to 6.3.0

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

## [6.2.0] - 2026-04-20

### Added
- **Dynamic XML Sitemap** (`sitemap.php`) — generates RFC-compliant `sitemap.xml` at `/sitemap.xml` via `.htaccess` rewrite; includes static pages (`/`, `/artists`, `/how-to-use`, `/contact`, `/credits`), all active events (`/event/{slug}` + `/event/{slug}/credits`, excluding default slug which maps to root), and all artist profile pages (`/artist/{id}`); `lastmod` derived from `updated_at` in DB; per-URL `changefreq` and `priority` tuned by page type
- **Sitemap file cache** — output captured with `ob_start()`/`ob_get_clean()` and saved to `cache/sitemap.xml`; subsequent requests served via `readfile()` without touching SQLite; TTL 1 hour (`SITEMAP_CACHE_TTL`); `invalidate_sitemap_cache()` called automatically by Admin API on any event or artist write (create/update/delete — 6 call sites total); also cleared by `invalidate_all_caches()` on DB restore
- **Dynamic robots.txt** (`robots.php`) — serves `/robots.txt` via `.htaccess` rewrite; auto-injects `Sitemap:` directive with correct absolute URL (protocol + host + base path); Disallows only user-specific paths (`/my/`, `/my-favorites/`) — internal directories intentionally omitted to avoid disclosing server structure
- **`.htaccess` rewrite rules** — added `^sitemap\.xml$` → `sitemap.php` and `^robots\.txt$` → `robots.php` at the top of the rewrite block (before all other rules)

### Files Changed

**New files:**
- `sitemap.php` — dynamic XML sitemap generator with file cache
- `robots.php` — dynamic robots.txt with auto-injected Sitemap URL
- `robots.txt` — static fallback (superseded by `robots.php` via rewrite)

**Modified files:**
- `config/cache.php` — added `SITEMAP_CACHE_FILE` and `SITEMAP_CACHE_TTL` constants
- `functions/cache.php` — added `invalidate_sitemap_cache()`; added `sitemap.xml` to `invalidate_all_caches()` patterns
- `admin/api.php` — added `invalidate_sitemap_cache()` to `createEvent`, `updateEvent`, `deleteEvent`, `createArtist`, `updateArtist`, `deleteArtist`
- `.htaccess` — added rewrite rules for `sitemap.xml` and `robots.txt`
- `config/app.php` — version bump to 6.2.0

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

---

## [6.1.5] - 2026-04-18

### Fixed
- **`setup.php` missing migration check for v6.1.3 review columns** — `program_requests.admin_note`, `reviewed_at`, `reviewed_by` columns added in v6.1.3 were not tracked in the setup wizard's Migration Status checklist; existing databases without these columns would pass the `$allTablesOk` check and not surface the gap
  - Added `$hasRequestReviewColumns` detection via `PRAGMA table_info(program_requests)`
  - Added `$hasRequestReviewColumns` to `$allTablesOk` condition
  - Added v6.1.3 entry to `$migrationChecks` pointing to `run_all_migrations` action
  - Corrected migration block comment from v6.1.2 → v6.1.3
- **Back button in admin note form does nothing** — `showAdminNoteForm()` back button called `openRequestDetail(window._currentReqData)` which does not exist; changed to `viewRequestDetail(window._currentReqData?.id)` to correctly re-render the detail modal

### Files Changed

**Modified files:**
- `setup.php` — added `$hasRequestReviewColumns` detection, `$allTablesOk` condition, and `$migrationChecks` entry for v6.1.3
- `admin/index.php` — fixed back button in `showAdminNoteForm()` to call `viewRequestDetail()` with the stored request ID

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

## [6.1.4] - 2026-04-18

### Added
- **Admin note input on approve/reject** — replaced the bare `confirm()` dialog with an inline admin note form inside the request detail modal; clicking Approve or Reject now shows a textarea (optional) for an admin note before submitting; a Back button returns to the detail view without closing the modal
- `admin_note` is saved to `program_requests` and displayed in the detail modal under "หมายเหตุ Admin" when set; `reviewed_at` and `reviewed_by` are also recorded

### Fixed
- **`approveReq`/`rejectReq` ReferenceError** — table-row Approve/Reject buttons still called the old removed functions; replaced with `viewRequestDetail(id); showAdminNoteForm(id, action)` so they open the detail modal then immediately show the note form

### Files Changed

**Modified files:**
- `admin/index.php` — replaced `approveReq()`/`rejectReq()` with `showAdminNoteForm()` + `confirmReqAction()`; table-row buttons updated to match
- `admin/api.php` — `approveRequest()` and `rejectRequest()` now write `admin_note` (max 500 chars) from request body
- `admin/js/admin-i18n.js` — added `req.adminNote`, `req.adminNotePlaceholder`, `common.optional` keys (TH + EN)

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

## [6.1.3] - 2026-04-18

### Fixed
- **Admin approve/reject request fails** — `admin/api.php` `approveRequest()` and `rejectRequest()` UPDATE statements referenced `admin_note`, `reviewed_at`, `reviewed_by` columns that did not exist in the `program_requests` table (the schema was never updated when these fields were added to the API logic); SQLite rejected the UPDATE with a PDOException caught as a silent failure, so every approve/reject returned an error
  - Added the three missing columns to the live DB via `ALTER TABLE`
  - Updated `setup.php` `CREATE TABLE program_requests` to include the new columns for fresh installs
  - Added a migration block in `setup.php` `run_all_migrations` to add the columns to existing databases

### Files Changed

**Modified files:**
- `admin/api.php` — restored full `approveRequest()` and `rejectRequest()` UPDATE statements with `admin_note`, `reviewed_at`, `reviewed_by` now that columns exist
- `setup.php` — added `admin_note TEXT`, `reviewed_at DATETIME`, `reviewed_by TEXT` to `CREATE TABLE program_requests`; added migration block for existing DBs

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

## [6.1.2] - 2026-04-18

### Fixed
- **Request form submit fails with "Submit failed"** — `api/request.php` INSERT used stale column names that no longer match the actual `program_requests` table schema (renamed in a prior migration); the query silently threw a PDOException that was caught and returned as `{"success":false,"message":"Submit failed"}`
  - `type` → `request_type` (column has `NOT NULL` constraint — root cause of the exception)
  - `title` → `summary`
  - `requester_note` → `note`
- **Admin Requests tab not showing submitted requests** — `admin/api.php` `listRequests()` and `approveRequest()` used the same stale column names when reading back from the DB, causing the admin UI (which references `r.type`, `r.title`, `r.requester_note`) to receive `undefined` for all three fields and render nothing
  - `listRequests()`: changed `SELECT *` to `SELECT *, request_type AS type, summary AS title, note AS requester_note`; fixed PHP reference `$req['type']` → `$req['request_type']`
  - `approveRequest()`: same SELECT alias fix; fixed `$req['type']` → `$req['request_type']` and `$req['title']` → `$req['summary']` in INSERT/UPDATE statements

### Files Changed

**Modified files:**
- `api/request.php` — corrected column names in `$data` array keys and INSERT statement to match actual DB schema (`request_type`, `summary`, `note`)
- `admin/api.php` — added column aliases in `listRequests()` and `approveRequest()` SELECT queries; fixed PHP references to use actual column names when reading request data

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

## [6.1.1] - 2026-04-18

### Fixed
- **Telegram cron incorrect datetime comparison (T-separator bug)** — `cron/send-telegram-notifications.php` used raw `BETWEEN` string comparison in SQL for the notification window; programs stored with ISO 8601 `T` separator (e.g. `"2026-04-17T11:00:00"`) were falsely matched because SQLite lexicographic string comparison treats `T` (ASCII 84) as greater than space (ASCII 32), causing `"2026-04-17T11:00:00"` to rank *after* `"2026-04-17 23:53:24"` on the same date — resulting in programs notifying 12+ hours after their actual start time (e.g. an 11:00 program triggering a notification at 23:48)
  - Fixed by wrapping both sides of the comparison with SQLite's `datetime()` function: `datetime(p.start) BETWEEN datetime(:windowStart) AND datetime(:windowEnd)`
  - `datetime()` normalises both `T`-separator and space-separator formats to `YYYY-MM-DD HH:MM:SS` before comparison; no timezone conversion occurs since both sides are already in the event's local timezone (Bangkok)

### Files Changed

**Modified files:**
- `cron/send-telegram-notifications.php` — changed `p.start BETWEEN :windowStart AND :windowEnd` to `datetime(p.start) BETWEEN datetime(:windowStart) AND datetime(:windowEnd)`

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

## [6.1.0] - 2026-04-18

### Changed
- **Request form redesign — unified "Add / Edit" button** — merged the separate "Add Program" button and the per-row ✏️ "Edit Request" column into a single `btn-request` button in the action bar
  - **Removed "Edit Request" column** (`<th>` + `<td>` per row) from the program table — reduces table width and UI clutter
  - **New `btn-request` button** (sakura outline style, replacing `btn-warning`); label "📝 แจ้งเพิ่ม / แก้ไข" (TH) / "📝 Request Add / Edit" (EN) / "📝 追加・編集をリクエスト" (JA)
  - **Modal redesign** — added visible type radio toggle (Add / Edit) replacing `<input type="hidden">`; selecting "Edit" reveals a program dropdown that loads all programs in the current event and pre-fills all fields (title, venue, start/end date & time, categories, description) automatically
  - **Added "End Date" field** (`reqEndDate`) to support cross-day programs; auto-syncs when start date changes and end date is empty or earlier
  - **Removed Organizer field** from the form — not needed for the request workflow
  - **Renamed "Categories" label → "Artist / Group"** (TH: ศิลปิน) for clarity across all three languages
  - **`api/request.php` `action=programs`** — added `end`, `categories`, `description` to SELECT; changed ORDER to `start ASC`; increased LIMIT to 200

### Files Changed

**Modified files:**
- `index.php` — removed `<th class="col-edit-request">` + `<td class="program-action-cell">`; changed button to `btn-request`; redesigned modal (type radio, program dropdown, end date field, removed Organizer); added JS functions `onReqTypeChange()`, `onReqStartDateChange()`, `onProgramSelected()`, `loadProgramsIntoSelector()`; updated `openRequestModal()` and `submitRequest()`
- `styles/index.css` — removed `.btn-edit-request`, `.program-action-cell`; added `.btn-request` (sakura outline + `::before` divider)
- `js/translations.js` — updated `button.requestAdd`; changed `modal.categories`; added 7 new keys (`modal.requestType`, `modal.typeAdd`, `modal.typeModify`, `modal.selectProgram`, `modal.selectProgramPlaceholder`, `modal.startDate`, `modal.endDate`) for all three languages (TH/EN/JA)
- `api/request.php` — added columns to `getEvents()` SELECT; changed ORDER and LIMIT

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

## [6.0.1] - 2026-04-17

### Fixed
- **`setup.php` init_database missing artist picture columns** — `CREATE TABLE IF NOT EXISTS artists` in the `init_database` action was missing `display_picture` and `cover_picture` columns (added in v6.0.0); fresh installs created the `artists` table without these columns, causing setup migration status to show ⚠️ pending and `ArtistPictureTest` to fail after a clean database initialisation

### Files Changed

**Modified files:**
- `setup.php` — 5 fixes:
  - `init_database` action: added `display_picture TEXT DEFAULT NULL` + `cover_picture TEXT DEFAULT NULL` to `CREATE TABLE IF NOT EXISTS artists`; added `PRAGMA table_info(artists)` + `ALTER TABLE` fallback for existing databases that already have the `artists` table
  - `add_artist_tables` action: added `PRAGMA table_info(artists)` check in `else` branch to `ALTER TABLE` and add missing picture columns
  - `run_all_migrations` action: same `else` branch fix as `add_artist_tables`
  - `$allTablesOk`: added `&& $hasArtistPictureColumns` condition so setup shows ⚠️ when columns are missing
  - `$migrationChecks`: added v6.0.0 entry `artists.display_picture + artists.cover_picture columns` pointing to `add_artist_tables` action
- `config/app.php` — version bump to 6.0.1

> **Test Coverage**: All 3666 automated tests pass (100% pass rate)

---

## [6.0.0] - 2026-04-17

### Added
- 🖼️ **Artist Cover & Display Picture System** — full upload pipeline for per-artist profile images stored in `uploads/artists/`
  - **`display_picture`** — circular avatar (400×400 px), shown beside the artist name on `/artist/{id}` and as a hover tooltip on artist badge pills in the program list
  - **`cover_picture`** — landscape banner (1200×400 px), shown as a full-width background in the artist profile header; sakura gradient overlay ensures text remains readable
  - PHP GD **server-side resize + center-crop** (JPEG quality 85%); supports JPG, PNG, GIF, WEBP input; max file size 5 MB
  - `processAndSaveArtistImage()` helper handles aspect-ratio-aware center-crop before downscaling
  - Old file automatically deleted when a new upload replaces it

- 🛠️ **Admin UI — Artist Picture Section in Edit Modal**
  - Appears only in **edit mode** (artist must be saved first); hidden in create/copy modes
  - Display picture preview: 72×72 px circle; cover picture preview: full-width banner strip (80 px height)
  - `📸 เปลี่ยนรูป` button opens native file picker → uploads immediately via AJAX (no form re-submit needed)
  - `🗑️ ลบรูป` button appears only when a picture exists; confirmation dialog before delete
  - `showArtistPictureSection(artist)` / `resetArtistPictureSection()` JS helpers manage section visibility and populate previews on modal open/close
  - Uses `APP_ROOT` (not `BASE_PATH`) to build image URLs — avoids `/admin/uploads/...` wrong-path bug

- 🎨 **Artist Profile Page (`/artist/{id}`) — visual overhaul**
  - Header restructured with `.artist-header-top` flex row: circular avatar (or emoji placeholder 🎤/🎵) on the left, name + meta on the right
  - **Cover picture**: injected as `--cover-url` CSS variable on `.artist-profile-header.has-cover`; dark gradient overlay via `::before` pseudo-element keeps white text legible
  - **Display picture**: `<img class="artist-display-picture">` (100×100 px desktop, 72×72 px mobile) with white border + soft box-shadow
  - Falls back to emoji placeholder div when no picture is set

- 💬 **Hover tooltip on program list artist badges**
  - Single shared `<div id="artistDpTooltip">` with `position: fixed; z-index: 9999` — sidesteps all `overflow: hidden` clipping from `.events-table tbody tr` and `.program-card` parent elements
  - Artist badge wraps render `data-display-pic` + `data-display-name` attributes only when `display_picture` is set (no empty DOM nodes for unpictured artists)
  - JS delegate listener (`mouseover` / `mouseout`) on `document` — positions tooltip above the badge using `getBoundingClientRect()`; auto-flips below if insufficient top space
  - Auto-hides on `scroll` and `resize` events
  - Tooltip shows 60×60 px circle image + artist name

- 🗄️ **Database** — `display_picture TEXT DEFAULT NULL` + `cover_picture TEXT DEFAULT NULL` columns added to `artists` table
- 🔧 **Migration** — `tools/migrate-add-artist-pictures-column.php` (idempotent; also creates `uploads/artists/` directory)
- 📁 **New directory** — `uploads/artists/` with `.htaccess` blocking directory listing and PHP execution
- 🐳 **Dockerfile** — `mkdir -p uploads/artists` + `chmod -R 777 uploads/` added to build step

### Changed
- `admin/api.php` — `listArtists()` and `getArtist()` now SELECT `display_picture` and `cover_picture`; `escapeOutputData()` includes both picture fields; upload endpoint returns `path` (relative) instead of a PHP-computed `url` (which was using wrong `get_base_path()` in admin context)
- `setup.php` — both `CREATE TABLE artists` statements updated with new columns; `$toCreate` and `$dirChecks` arrays include `uploads/` and `uploads/artists/`

### New Admin API Actions
| Action | Method | Description |
|--------|--------|-------------|
| `artist_picture_upload` | POST multipart | Upload display or cover picture; resize; save; update DB |
| `artist_picture_delete` | POST JSON | Delete physical file; clear DB column |

### Files Changed

**New files:**
- `tools/migrate-add-artist-pictures-column.php` — idempotent migration (ALTER TABLE + create uploads dir)
- `uploads/.htaccess` — block directory listing + PHP execution
- `uploads/artists/.htaccess` — same
- `tests/ArtistPictureTest.php` — 61 automated tests

**Modified files:**
- `admin/api.php` — `listArtists()` / `getArtist()` select picture fields; `uploadArtistPicture()`, `deleteArtistPicture()`, `processAndSaveArtistImage()` new functions; upload returns `path` not `url`
- `admin/index.php` — picture section in artist edit modal; `showArtistPictureSection()`, `resetArtistPictureSection()`, `uploadArtistPicture()`, `deleteArtistPicture()` JS functions; uses `APP_ROOT` for image URLs
- `artist.php` — cover picture banner + display picture avatar in header
- `index.php` — `a.display_picture` in program_artists query; `data-display-pic` attributes on badge wraps; shared `#artistDpTooltip` fixed-position JS tooltip
- `styles/artist.css` — `.artist-display-picture`, `.artist-display-placeholder`, `.artist-profile-header.has-cover`, `.artist-header-top` rules
- `styles/index.css` — removed CSS hover card rules (replaced by JS `position:fixed` tooltip)
- `setup.php` — `CREATE TABLE artists` includes new columns; `$toCreate` + `$dirChecks` include `uploads/` and `uploads/artists/`
- `Dockerfile` — `mkdir -p uploads/artists` + `chmod -R 777 uploads/`
- `tests/run-tests.php` — registered `ArtistPictureTest` suite
- `config/app.php` — version bump to 6.0.0

> **Test Coverage**: All 3666 automated tests pass (100% pass rate, 16 suites)

---

## [5.5.3] - 2026-04-17

### Changed
- **Telegram notification window — dynamic half-window** — `cron/send-telegram-notifications.php` replaced hardcoded `±7.5 min` window with `halfWindow = min(notify_before / 2, 7.5) minutes`. Prevents the window from extending past program start for short notify times (e.g. notify=5 min → ±2.5 min; notify≥15 min → ±7.5 min capped).
- **Notify before — dropdown instead of free-text input** — `<input type="number">` replaced with `<select>` offering 5 / 10 / 15 / 30 / 60 minutes in Admin › Settings › Telegram.
- **Cron interval — recommendation instead of config field** — removed `cron_interval_minutes` as a user-configurable setting. Admin UI now shows a dynamic cron recommendation box (`updateCronRecommendation()`) that auto-calculates the optimal crontab interval using `floor(min(notify_before, 15) / 1.5)`, guaranteeing ≥150% coverage. The recommended command updates live when the notify dropdown changes.

### Files Changed
- `cron/send-telegram-notifications.php` — dynamic `$halfWindow = (int)(min(TELEGRAM_NOTIFY_BEFORE_MINUTES / 2, 7.5) * 60)`
- `config/telegram.php` — removed `cron_interval_minutes` from `$defaultTelegramConfig`; removed `TELEGRAM_CRON_INTERVAL_MINUTES` constant
- `config/telegram-config.json` — removed `cron_interval_minutes` field
- `admin/api.php` — removed `cron_interval_minutes` from `getTelegramConfig()` default and `saveTelegramConfig()` save logic
- `admin/index.php` — notify select replaces number input; cron dropdown replaced with recommendation box; `updateCronRecommendation()` JS function; `onchange` wired to notify select; called on config load
- `admin/js/admin-i18n.js` — updated hint keys (TH/EN); added `telegramCronInterval`/`telegramCronIntervalHint`/`telegramCronPathHint` keys; removed `(5-1440)` range hint from notify field
- `config/app.php` — version bump to 5.5.3

---

## [5.5.2] - 2026-04-16

### Fixed
- **Admin event/artist dropdowns showing HTML entities** — After the v5.3.1 server-side HTML escaping change, `escapeOutputData()` in `admin/api.php` began returning HTML-escaped strings (e.g. `Idol&#039;s`) in JSON responses. The `populateEventSelect()` function and artist group dropdown builders in `admin/index.php` were setting `option.textContent = meta.name` directly, which renders the raw string — displaying `&#039;` literally instead of `'`. Fixed by wrapping all 6 affected `option.textContent` assignments with `decodeHtml()`: 3 in `populateEventSelect()` (Recent / Active Events / Past Events optgroups) and 3 in artist group selects (artist form, bulk add-to-group modal, bulk remove-from-group modal).

### Files Changed
- `admin/index.php` — wrapped 6 `option.textContent` assignments in `decodeHtml()` (Recent / Active Events / Past Events optgroups + 3 artist group selects)
- `config/app.php` — version bump to 5.5.2

---

## [5.5.1] - 2026-04-15

### Fixed
- **Telegram group program resolution** — `_telegram_resolve_artists()` in `api/telegram.php` now adds the **parent group ID** when a followed artist is a group member, instead of expanding to all sibling members. This matches the same logic used by My Upcoming Programs (`my.php`): programs tagged to the group entity are now shown correctly via `/today`, `/tomorrow`, `/week`, `/upcoming`, and `/next` commands.
- **Telegram cron notification timezone bug** — `cron/send-telegram-notifications.php` was comparing notification window timestamps using `CAST(strftime('%s', p.start) AS INTEGER)` which treats stored datetimes as UTC. Since `p.start` is stored in event-local time (Bangkok, UTC+7), notifications were delayed by 7 hours (e.g. a program at 18:30 Bangkok triggered a notification at 01:20 the next day). Fixed by converting PHP window timestamps to event-timezone datetime strings and using `p.start BETWEEN :windowStart AND :windowEnd` for string comparison instead.
- **Telegram cron group resolution** — `cron/send-telegram-notifications.php` was expanding followed artists to sibling group members (B, C, D) instead of the parent group entity (G). Programs tagged to the group directly were never matched, causing missed notifications for group-level programs. Fixed to use parent-group lookup identical to `my.php` and `api/telegram.php`.

- **Admin backup timestamps showing UTC** — `createBackup()`, `listBackups()`, `restoreBackup()`, and `uploadAndRestoreBackup()` in `admin/api.php` all used `gmdate()` which returns UTC time. Backup filenames (e.g. `backup_20260415_113000.db`) and the created-at timestamps shown in the Backup UI were 7 hours behind Bangkok time. Fixed by replacing all 5 `gmdate()` calls with `date()`, which uses `Asia/Bangkok` already set via `date_default_timezone_set()` in `config/app.php`.

### Files Changed
- `api/telegram.php` — updated `_telegram_resolve_artists()` SQL from sibling-expansion to parent-group lookup
- `cron/send-telegram-notifications.php` — fixed timezone comparison (strftime → datetime string BETWEEN); fixed group resolution to use parent-group lookup matching `my.php`
- `admin/api.php` — replaced `gmdate()` with `date()` in all 5 backup timestamp calls (createBackup filename, createBackup response, listBackups display, restoreBackup auto-backup filename, uploadAndRestoreBackup auto-backup filename)
- `config/app.php` — version bump to 5.5.1

> **Test Coverage**: All 3064 automated tests pass (100% pass rate)

## [5.5.0] - 2026-04-15

### Added
- **5 New Themes** — expanded theme system from 7 to 12 themes; all new themes follow the same CSS variable pattern as existing themes and support image export (PHP GD palette), Admin theme picker (gradient preview), and per-event theme override
  - 🔴 **Crimson** — bold deep red (`#C62828`), energetic idol-stage feel
  - 🩵 **Teal** — teal/aqua (`#00796B`), fresh summer aquamarine between ocean and forest
  - 🌹 **Rose** — rose-gold (`#E11D48`), warm coral-pink distinct from sakura
  - 🌟 **Amber** — gold/amber (`#F57F17`), premium warm yellow-orange distinct from sunset
  - 🔷 **Indigo** — indigo/navy (`#3F51B5`), deep blue-purple bridging ocean and midnight

### Files Changed
- `styles/themes/crimson.css` — new theme file (28 CSS variables)
- `styles/themes/teal.css` — new theme file (28 CSS variables)
- `styles/themes/rose.css` — new theme file (28 CSS variables)
- `styles/themes/amber.css` — new theme file (28 CSS variables)
- `styles/themes/indigo.css` — new theme file (28 CSS variables)
- `functions/helpers.php` — added 5 themes to `$validThemes` array in `get_site_theme()`
- `image.php` — added 5 RGB palette entries in `$_palettes` for server-side image export
- `admin/index.php` — added 5 options in `conventionTheme` select + 5 entries in `THEME_OPTIONS` array (gradient preview)
- `admin/api.php` — added 5 themes to `$validThemes` whitelist in all 3 occurrences (`createEvent`, `updateEvent`, `saveThemeSetting`)
- `config/app.php` — version bump to 5.5.0

> **Test Coverage**: All 3064 automated tests pass (100% pass rate)

## [5.4.1] - 2026-04-15

### Fixed
- Fixed `<code>` tags disappearing in How-to-Use page Telegram section 4 (Schedule Commands) and section 5 (Notification Controls) when switching languages — moved `data-i18n` from `<li>` to `<span>` wrapping only the description text, keeping command names as static HTML; updated `translations.js` to store description-only values (removed command prefix) for all 3 languages (TH/EN/JA)

### Files Changed
- `how-to-use.php` — restructured `<li>` elements in sections 4 and 5: `<code>` as static HTML, `data-i18n` on `<span>` for description only
- `js/translations.js` — updated 12 translation keys (commands.* and controls.*) × 3 languages to description-only values
- `config/app.php` — version bump to 5.4.1

## [5.4.0] - 2026-04-15

### Added
- **New Telegram Bot Commands** — 8 new commands + 2 modified; full schedule browsing and notification control without leaving Telegram

  **Schedule Commands:**
  - `/tomorrow` — events + program count for tomorrow (same format as `/today`)
  - `/week` — next 7 days grouped by day, each day shows events + program count
  - `/artists` — list all followed artists (fetched from DB by followed IDs, sorted A–Z)
  - `/next` — alias for `/upcoming 1`; shows the single soonest upcoming program

  **Modified Commands:**
  - `/today` — changed format from full per-program detail to condensed event list + count per event
  - `/upcoming [N]` — default changed 5 → 3; now accepts optional numeric argument 1–10; invalid input shows error then proceeds with default 3

  **Notification Control Commands:**
  - `/lang th|en|ja` — change notification language directly in bot (previously required re-linking)
  - `/mute {N}` — mute push notifications for N hours (1–72); shows mute-until time in Asia/Bangkok timezone
  - `/notify on|off` — toggle push notifications on/off without unlinking; opt-out model (absent = on)
  - `/status` — account summary: followed artist count, current language, notification on/off, mute status

- **New favorites JSON fields** (no migration — absent = default):
  - `telegram_mute_until` — Unix timestamp; absent/0 = not muted
  - `telegram_notify_enabled` — bool; absent/null = true (opt-out)

- **Cron notification guards** — `send-telegram-notifications.php` now skips users with `telegram_notify_enabled = false` or active mute before processing any DB queries

- **Helper functions** (`functions/telegram.php`):
  - `find_favorites_by_chat_id(int $chat_id)` — shared shard-scan helper eliminating duplicate code across handlers
  - `telegram_is_muted(array $favData)` — checks `telegram_mute_until` vs `time()`
  - `telegram_notify_is_enabled(array $favData)` — opt-out model check
  - `telegram_format_events_list(array $programs, string $dateStr, string $language, string $context)` — condensed event+count format for `/today`, `/tomorrow`, `/week`
  - `_telegram_resolve_artists(array $ids)` — resolves group members; now used by all program-fetching commands including `/upcoming` and `/next`

- **54 new tests** in `tests/TelegramTest.php` (function existence, unit tests for muted/notify helpers, format tests, message keys for all 16 new keys × 3 languages, handler existence, router routes, cron guards) — **3064 total tests**

### Files Changed
- `functions/telegram.php` — new helpers: `find_favorites_by_chat_id()`, `telegram_is_muted()`, `telegram_notify_is_enabled()`, `telegram_format_events_list()`, `_telegram_resolve_artists()`
- `api/telegram.php` — added handlers for `/tomorrow`, `/week`, `/artists`, `/next`, `/lang`, `/mute`, `/notify`, `/status`; updated `/today` and `/upcoming`
- `cron/send-telegram-notifications.php` — added mute and notify-enabled guards before processing
- `tests/TelegramTest.php` — 54 new tests for new commands and helpers
- `tests/run-tests.php` — registered TelegramTest suite
- `js/translations.js` — 16 new translation keys × 3 languages for Telegram command responses
- `how-to-use.php` — updated Telegram section with new commands documentation

> **Test Coverage**: All 3064 automated tests pass (100% pass rate)

---

## [5.3.1] - 2026-04-14

### Security
- **Full Server-Side HTML Escaping for Admin API** — restored `escapeOutputData()` in `admin/api.php` to actually escape with `htmlspecialchars(ENT_QUOTES|ENT_SUBSTITUTE)` as defense-in-depth; previously was a no-op since v3.5.3
  - ✅ **escapeOutputData() restored** — covers all existing call sites: programs, requests, credits, events, users, artists, artist_variants (~14 endpoints)
  - ✅ **5 additional endpoints escaped** — `title_get`, `disclaimer_get`, `telegram_config_get`, `contact_channels_list`, `contact_channel_get` now escape string fields before returning JSON
  - ✅ **Fixed 2 XSS vulnerabilities** — `error.message` (line 4869) and `result.message` (line 5368) were directly concatenated into `innerHTML` without escaping; wrapped with `escapeHtml()`
- **`escapeHtml()` updated to attribute-safe** — added `.replace(/"/g, '&quot;')` so the function is safe in both text-node and HTML-attribute contexts
- **Added `decodeHtml()` JS helper** — decodes `htmlspecialchars()` entities back to raw text for form `.value` assignments; uses `<textarea>.innerHTML` (safe, no script execution); prevents `&#039;` appearing literally in edit form inputs
- **Removed double-escaping from display paths** — removed `escapeHtml()` wrapping from 40+ `innerHTML` table-display call sites where server-side escaping now handles protection (programs, requests, credits, events, users, artists, contact channels)
- **Wrapped ~40 form `.value` assignments** with `decodeHtml()` — all edit modals (program, credit, event, user, artist, copy), settings panels (site title, disclaimer, telegram, contact channel) now correctly show raw characters (`'`, `&`) instead of HTML entities
- **Unified `escHtml()` → `escapeHtml()`** — replaced all 6+ call sites of the duplicate `escHtml()` regex function with the canonical DOM-based `escapeHtml()`; deleted duplicate function definition; `colorizeLogOutput()` (telegram log viewer) retains escaping internally as log content must remain raw before colorization

### Files Changed
- `admin/api.php` — restored `escapeOutputData()`, added escaping to 5 endpoints, validated `stream_url` scheme
- `admin/index.php` — fixed 2 XSS vulnerabilities (`error.message`, `result.message`), added `decodeHtml()` helper, wrapped 40+ `.value` assignments, unified `escHtml()` → `escapeHtml()`

> **Security posture**: Admin API now applies HTML escaping at both server (JSON output) and client (innerHTML display) layers — defense-in-depth. Form inputs correctly show raw text via `decodeHtml()`.

> **Test Coverage**: All 2523 automated tests pass (100% pass rate)

---

## [5.3.0] - 2026-04-14

### Added
- **Telegram Log Viewer in Admin UI** — View and download Telegram notification cron logs directly from the admin panel
  - 📋 **Log Viewer Section** — New section in Admin › Settings › 🤖 Telegram with file selector, refresh, and download buttons
  - 📂 **File Management** — Dropdown lists active log (`telegram-cron.log`) + dated archives (`telegram-cron-YYYY-MM-DD.log`), newest first
  - 🎨 **Colored Output** — Log entries color-coded by level: `[INFO]` (green), `[DEBUG]` (gray), `[WARN]` (orange), `[ERROR]` (red)
  - 📊 **Line Tracking** — Shows "Displaying X / Y lines" info; displays last 500 lines to prevent memory issues
  - ⬇️ **Download Logs** — Download selected log file directly via download button

### New API Endpoints
- `GET ?action=telegram_log_get[&file=FILENAME]` — Returns file list + last 500 lines of selected log
- `GET ?action=telegram_log_download[&file=FILENAME]` — Downloads full log file as attachment

### Implementation
- Auto-loads log viewer when switching to Telegram sub-tab in Admin Settings
- File selection validated against whitelist — no path traversal possible
- Admin-role required for downloads; login-only for viewing
- Responsive layout with color-coded terminal-style display

### Files Changed
- `admin/api.php` — Added `telegram_log_get` and `telegram_log_download` endpoints
- `admin/index.php` — Added Log Viewer HTML section + JS functions in Telegram sub-tab; updated `switchSettingsSubtab()` to auto-load logs
- `admin/js/admin-i18n.js` — Added 3 new i18n keys (TH + EN): `settings.telegramLogTitle`, `settings.telegramLogRefresh`, `settings.telegramLogDownload`
- `config/app.php` — Version bump to 5.3.0

> **User Experience**: Admins can now monitor Telegram notification cron health without SSH access
> **Security**: File access validated, admin-role protected for downloads

## [5.2.0] - 2026-04-14

### Added
- **Telegram Log Rotation & Cleanup** — Dedicated daily cron script for log management
  - 🔄 **Daily Rotation** — `cron/rotate-telegram-logs.php` renames `cache/logs/telegram-cron.log` to dated archives (`telegram-cron-YYYY-MM-DD.log`) every day
  - 🗑️ **Automatic Cleanup** — Deletes archived logs older than 7 days via scheduled cron job
  - 🔒 **Security Hardening** — Added `cron/.htaccess` with `Deny from all` for Apache-level HTTP access blocking
  - 📋 **Flexible Output** — Script outputs timestamped messages to STDOUT for easy log capture and monitoring

### Implementation
- Non-destructive addition — existing 10 MB size-based rotation in `send-telegram-notifications.php` remains as safety valve
- Both rotation mechanisms coexist peacefully; `glob` pattern `telegram-cron-*.log` captures both daily and size-rotated archives
- Cron scheduling recommendation: `0 0 * * * php /path/to/cron/rotate-telegram-logs.php >> /path/to/cache/logs/rotate-cron.log 2>&1`

### Files Changed
- `cron/rotate-telegram-logs.php` *(new)* — daily log rotation and 7-day cleanup script (CLI-only)
- `cron/.htaccess` *(new)* — Apache-level HTTP access protection for cron directory
- `config/app.php` — version bump to 5.2.0

> **Robustness**: Log rotation now combines daily schedule + automatic retention, eliminating manual log cleanup burden
> **Security**: Added Apache-level directory protection for all cron scripts

## [5.1.1] - 2026-04-14

### Added
- **Admin Help Documentation (Thai)** — Updated `admin/help.php` with v5.1.0 features
  - 📝 **Header & Account Settings** — Added App Version Badge explanation
  - ⚙️ **Settings Tab Sub-tabs** — Documented new 6 sub-tabs structure (Site • Contact • Users • Backup • Telegram • Disclaimer)
  - 📋 Comprehensive table explaining each Settings sub-tab function and category
  - Support for all admin role explanations

- **Admin Help Documentation (English)** — Updated `admin/help-en.php` with v5.1.0 features
  - 📝 **Header & Account Settings** — Added App Version Badge explanation (English)
  - ⚙️ **Settings Tab Sub-tabs** — Documented new 6 sub-tabs structure with English descriptions
  - 📋 Comprehensive table with English function descriptions
  - Full English documentation parity with Thai version

- **How-to-Use Guide (3 Languages)** — Verified `how-to-use.php` internationalization support
  - 🌍 Confirmed 3-language support (Thai/English/日本語) via i18n system
  - 📝 Verified footer version display updates automatically from APP_VERSION constant
  - 🔍 Confirmed all data-i18n attributes for proper translations

### Files Changed
- `admin/help.php` — Updated Settings Sub-tabs documentation with v5.1.0 feature explanation (Thai)
- `admin/help-en.php` — Updated Settings Sub-tabs documentation with v5.1.0 feature explanation (English)
- `how-to-use.php` — Verified internationalization; no content changes needed (i18n-driven)

> **Documentation Quality**: Admin help files now comprehensively document v5.1.0 Settings sub-tabs changes
> **i18n Coverage**: how-to-use.php supports full 3-language experience via translations.js

## [5.1.0] - 2026-04-14

### Added
- **Admin UI Settings Sub-tabs** — Reorganized Admin Settings with cleaner sub-tab navigation
  - 📝 Site (Title + Theme)
  - ✉️ Contact (Channel management)
  - 👤 Users (User management)
  - 💾 Backup (Backup/restore)
  - 🤖 Telegram (Notification settings)
  - ⚠️ Disclaimer (Multilingual disclaimer)
- **App Version Badge** — Added version display (e.g., `v5.1.0`) in admin header between language toggle and help link

### Changed
- **Settings Tab Structure** — Removed redundant Users, Backup, and Contact top-level tabs; consolidated into Settings sub-tabs for cleaner navigation
  - Old: 7 top-level tabs (Programs, Events, Requests, Credits, Import, Artists, Settings + Users + Backup + Contact)
  - New: 7 top-level tabs (Programs, Events, Requests, Credits, Import, Artists, Settings) with 6 organized sub-tabs inside Settings
  - Sub-tab order optimized: Site → Contact → Users → Backup → Telegram → Disclaimer

### Files Changed
- `config/app.php` — Version bump to 5.1.0
- `admin/index.php` — Reorganized Settings sub-tabs, removed old Users/Backup/Contact sections, updated `switchTab()` and `switchSettingsSubtab()` functions, removed redundant main tab buttons, added version badge in header
- `admin/js/admin-i18n.js` — No changes needed (sub-tab keys already defined)

> **Test Coverage**: All 2523 automated tests pass (100% pass rate)
> **Backward Compatibility**: Full compatibility maintained; Settings functionality unchanged, only UI organization improved

## [5.0.0] - 2026-04-14

### Added
- **Telegram Bot Notifications** — Users can now link their Telegram account to receive automatic push notifications before upcoming programs
  - 🔔 **Link Telegram** — New UI section in "My Upcoming Programs" (`/my/{slug}`) page to connect Telegram account via 2 methods:
    - **Method 1 (Recommended)**: Click "เปิด Telegram" button → Opens deep-link to bot with `start` parameter pre-filled
    - **Method 2 (Fallback)**: Manual search and `/start {slug}` command entry (for when deep-link unavailable)
    - Clear visual separation between methods with color-coded info boxes
    - Slug removal from modal (already embedded in deep-link button and command instructions)
  - 🌐 **In-bot Language Selection** — Users select language (Thai/English/日本語) via inline keyboard buttons after `/start` command
  - ⚡ **Per-program Notifications** — Automatic push notification N minutes before each program starts (configurable, default 60 minutes)
  - 📅 **Daily Summary Notifications** — Automatic summary of all upcoming programs grouped by event, sent at 9:00 AM each day
  - 🔄 **Cron-based Delivery** — CLI script runs every 15 minutes to scan favorites and send notifications; no DB schema changes needed
    - Shard directory scanning in both webhook handler (`/upcoming`, `/today` commands) and cron script properly discovers favorites files
  - 🔐 **Secure Linking** — Uses existing HMAC-signed favorites slug for authentication; Telegram chat_id stored in favorites JSON
  - 🌐 **Multilingual** — Full support for Thai, English, and 日本語; notifications formatted with program details
  - ⚙️ **Admin UI Settings** — Configure Bot Token, Bot Username, Webhook Secret, Notify Minutes from Admin › Settings › 🤖 Telegram Notifications
  - 🧹 **Production-ready code** — All verbose debugging infrastructure removed, kept only standard error logging for reliability

### Files Changed
- `config/telegram.php` *(new)* — Bot configuration loader (JSON → PHP constants)
- `config/telegram-config.json` *(new)* — Runtime settings (edited via Admin UI)
- `functions/telegram.php` *(new)* — API helpers, account linking, message formatting, notification utilities
- `api/telegram.php` *(new)* — Webhook handler for `/start`, `/stop`, `/upcoming` commands
- `cron/send-telegram-notifications.php` *(new)* — Cron script for sending notifications every 15 minutes
- `tools/setup-telegram-webhook.php` *(new)* — Helper script to register webhook URL with Telegram Bot API
- `TELEGRAM_SETUP.md` *(new)* — Setup guide (Thai)
- `TELEGRAM_SETUP_EN.md` *(new)* — Setup guide (English)
- `config.php` — load telegram config and functions
- `my.php` — Telegram linking UI section with modal and JavaScript functions
- `api/favorites.php` — new `unlink_telegram` action to disconnect Telegram
- `js/translations.js` — 24+ new translation keys (TH/EN/JA) for Telegram UI
- `admin/index.php` — Telegram notifications settings section in Settings tab
- `admin/api.php` — three new API endpoints: `telegram_config_get`, `telegram_config_save`, `telegram_webhook_test`
- `admin/js/admin-i18n.js` — 18+ new translation keys for Telegram settings UI
- `admin/help.php` — "🤖 Telegram Notifications" section (Thai)
- `admin/help-en.php` — "🤖 Telegram Notifications" section (English)
- `config/.htaccess` — deny HTTP access to .json files in config directory

### Configuration
Users need to set up Telegram bot integration via Admin UI:
1. Go to Admin › Settings › 🤖 Telegram Notifications
2. Enter Bot Token, Bot Username
3. Generate or paste Webhook Secret
4. Set Notify Minutes (default 60)
5. Click "Test Webhook" to verify
6. Click "Save Telegram"

Then add cron job: `*/15 * * * * php /path/to/cron/send-telegram-notifications.php >> /var/log/tg-notify.log 2>&1`

### Architecture
- **No DB migration** — Telegram metadata stored in existing favorites JSON files (`telegram_chat_id`, `telegram_notified` map)
- **Idempotent** — Notifications tracked with program ID and timestamp to prevent duplicates (window ±7.5 minutes)
- **Scalable** — Hybrid design: JSON-based for <1000 users, can migrate to DB table when needed
- **Flexible** — Notification window, history retention, and duplicate prevention all configurable
- **Reliable shard discovery** — Webhook handler and cron script properly iterate `cache/favorites/{3-char hex shard}/*.json` structure

> **Test Coverage**: All 2523 automated tests pass (100% pass rate)
> **Code Quality**: Production-ready with debug code cleaned up; no warnings or excessive logging

## [4.5.1] - 2026-04-12

### Fixed
- **Admin filter state not persisting after program edit** — Event filter dropdown (`eventMetaFilter`) in Programs/Requests/Credits tabs now preserves selected value when reloading data after editing and saving a program
  - **Issue**: `populateEventSelect()` rebuilt dropdown options but didn't restore the previously selected event, causing filter to appear "empty" despite being set
  - **Root cause**: Dropdown HTML rebuild cleared all optgroups and options without preserving the `selected` state
  - **Fix**: Save dropdown's current selected value before rebuild, then restore it after new options are appended
  - **Result**: Filter selection now visually persists across modal close → API receives correct event_id parameter → correct filtered data displayed

### Changed
- **`populateEventSelect()` function** — Now includes value preservation logic for all event selector dropdowns

### Files Changed
- `admin/index.php` — Updated `populateEventSelect()` to save/restore selected value
- `config/app.php` — Version bump to 4.5.1

> **Test Coverage**: All 2523 automated tests pass (100% pass rate)

## [4.5.0] - 2026-04-11

### Changed
- **SUMMARY field format in ICS feeds** — Standardized format across all feed types to `Program Title [Event Name]` (event name moved from prefix to suffix in My Upcoming Programs)
  - **export.php**: Changed from `Program Title` to `Program Title [Event Name]`
  - **feed.php (single event)**: Changed from `Program Title` to `Program Title [Event Name]`
  - **feed.php (artist feed)**: Added per-program event name support; now shows correct event name for each program when artist performs at multiple events
  - **my-feed.php**: Changed from `[Event Name] Program Title` to `Program Title [Event Name]` for consistency
  - **Benefit**: More readable calendar appointments with program name visible first, then event context in brackets

### Fixed
- **Artist feed SUMMARY using wrong event name** — Artist feed was showing artist name instead of program's actual event name in SUMMARY field (breaking for artists with multiple events)
  - Added `$eventNameMap` to fetch per-program event information from database
  - SUMMARY now correctly shows event name for each program: `"Program Title [Idol Stage Feb 2026]"`, `"Program Title [Japan Expo 2026]"`, etc.

### Files Changed
- `export.php` — Updated SUMMARY format to include event name
- `feed.php` — Added per-program event name map for artist feeds; updated SUMMARY format
- `my-feed.php` — Changed SUMMARY format from prefix to suffix event name
- `tests/FeedTest.php` — Updated test to match flexible SUMMARY pattern (either `$eventName` or `$summaryEventName`)

> **Test Coverage**: All 2523 automated tests pass (100% pass rate)

## [4.4.1] - 2026-04-10

### Fixed
- **Events tab filter dropdowns rendering empty** — `data-i18n` attribute was placed on `<select>` element instead of first `<option>`, causing the i18n system to replace the entire select's textContent and delete all options; moved `data-i18n` to the first option in `eventActiveFilter` and `eventVenueFilter` dropdowns
- **Events tab date filters not resetting pagination** — Date range inputs (`eventDateFrom`, `eventDateTo`) didn't reset `eventsCurrentPage` to 1 when filters changed; added `eventsCurrentPage=1;` to `onchange` handlers
- **Events table body ID mismatch** — HTML had `id="conventionsTableBody"` but JavaScript searched for `id="eventsConventionsTableBody"`; renamed HTML table body ID to match
- **Removed debugging code** — Cleaned up 39 `console.log()` statements and CSS debug borders (red/blue) left from troubleshooting

### Files Changed
- `admin/index.php` — Fixed filter dropdown i18n, pagination reset, table ID mismatch, removed debugging code

## [4.4.0] - 2026-04-06

### Added
- **Events Tab Feature Parity with Programs Tab** — Admin Events tab now has complete filtering, pagination, and sorting capabilities matching Programs tab
  - ✨ **Server-side filtering**: active status (is_active), venue mode, date range (date_from, date_to)
  - ✨ **Server-side pagination**: configurable page size (20/50/100), page navigation with info display
  - ✨ **Server-side sorting**: sortable columns (ID, Name, Start Date, End Date, Active, Programs) with visual indicators
  - 🔧 **N+1 query fix**: Subquery for event_count instead of loop SELECT per event
  - 📊 **Pagination controls**: Previous/Next buttons with page info and total count display
  - 🎨 **Search box layout**: Search bar spans full width; filter controls and buttons wrap to next lines

### Changed
- **Events API endpoint (`admin/api.php` `listEvents()`)** — Updated to support pagination and filtering parameters; now returns `{ events: [...], pagination: {...} }` structure matching Programs API
  - **New parameters**: `search`, `is_active`, `venue_mode`, `date_from`, `date_to`, `sort`, `order`, `page`, `limit`
  - **Query optimization**: Uses subquery for `event_count` instead of N+1 loop queries
  - **Default pagination**: 20 events per page, supports up to 100
- **Admin UI toolbar layout** — Search box now takes full width on its own line; filter dropdowns and buttons wrap to subsequent lines
- **Add Program/Event buttons** — Now span full width on their own line in toolbar

### Fixed
- **Recent events dropdown not showing**: Fixed API call to fetch all events (limit=100) instead of default limit (20), ensuring recent events are found in the filtered list
- **Recent events sort order**: Recent events now display in selection order (newest first) instead of arbitrary order
- **Recent section placement**: Recent group now appears at the top of event dropdown (before Active/Past groups) for quick access
- **Import workflow**: Added "📥 Import ไฟล์ถัดไป" button on summary screen to allow clearing and importing next file without leaving Import tab

### Files Changed
- `admin/api.php` — Enhanced `listEvents()` with full pagination, filtering, and sorting
- `admin/index.php` — Updated Events toolbar, added pagination controls, fixed dropdown event ordering, improved import summary
- `admin/js/admin-i18n.js` — Added 12 new translation keys for Events filters and pagination (TH/EN)
- `config/app.php` — App constants

> **Test Coverage**: All 2523 automated tests pass (100% pass rate)

## [4.3.0] - 2026-04-06

### Added
- **Smart Event Dropdown Filtering** — Admin Programs/Requests/Credits tabs now display events grouped by status (Active/Past) instead of flat list
  - 📌 **Recent Events section** — Top 3 recently selected events pinned at the top of dropdown for quick access; stored in `localStorage`
  - 🎪 **Active Events group** — Events with end_date ≥ today, sorted by start_date DESC (newest first)
  - 📋 **Past Events group** — Events with end_date < today, sorted by start_date DESC (newest first)
- **Automatic Recent Event Tracking** — Selecting an event from `eventMetaFilter` dropdown automatically saves it to recent list; dropdown re-renders to show updated recent section
- **Helper Functions**:
  - `getRecentEvents()` — retrieves recent events from localStorage
  - `saveRecentEvent(eventId, eventName)` — adds/updates event in recent list (top 3)
  - `groupAndSortEvents(metas)` — separates active/past events and sorts by date
  - `populateEventSelect(selectId, allMetas, recentIds)` — renders optgroups with proper grouping

### Changed
- Event dropdown in Admin panel now uses `<optgroup>` for visual separation instead of flat list
- All 6 event filter selectors (`eventMetaFilter`, `reqEventMetaFilter`, `creditsEventMetaFilter`, `eventConvention`, `creditEventMetaId`, `icsImportEventMeta`) use the same grouping logic for consistency

### Files Changed
- `admin/index.php` — Added smart event dropdown with Recent/Active/Past grouping
- `config/app.php` — Constants for recent events feature

## [4.2.0] - 2026-04-04

### Added
- **Bilingual Admin UI (TH/EN)** — `admin/js/admin-i18n.js` new file with 200+ translation keys per language; TH/EN language toggle button in Admin panel header and login page; preference saved to `localStorage` (`admin_lang`)
- **`adminT(key)`** — core translation lookup function; returns Thai or English string based on current language
- **`applyAdminTranslations()`** — scans DOM for `data-i18n`, `data-i18n-placeholder`, and `data-i18n-title` attributes and applies translations; called on page load and language switch
- **`changeAdminLang(lang)`** — switches language, updates toggle button state, dispatches `adminLangChange` custom event
- **`adminLangChange` custom event** — listened to by `admin/index.php`; re-calls the active tab's `loadX()` function so JS-rendered table rows (which use `adminT()` inline in template literals) are rebuilt in the new language
- **`data-i18n` attributes** — added to 280+ static HTML elements across `admin/index.php` and `admin/login.php`: tab labels, toolbar buttons, table headers, form labels, form hints (`<small>`), modal titles and body text, bulk-bar labels, status badges, and confirmation messages
- **Dynamic content translated** — all JS-generated HTML (program rows, request rows, artist rows, credits rows, user rows) uses `adminT()` for "Edit", "Delete", "Copy", "Variants", "View" buttons and status badge labels so they switch language on toggle

### Changed
- `admin/login.php` language toggle syncs with `admin/index.php` via same `localStorage` key — switching language on the login page persists to the admin panel and vice versa
- All form hints (`<small class="form-hint">`) including Artist modal, Variants modal, Import Artists modal, Bulk Edit modal, Event modal, and ICS Import artist-mapping hint are now fully bilingual

### Files Changed
- `admin/js/admin-i18n.js` (new) — Core i18n lookup function `adminT()` with 200+ keys per language
- `admin/index.php` — Language toggle button, `data-i18n` attributes on 280+ elements, dynamic content translation
- `admin/login.php` — Language toggle synced with admin panel via localStorage
- `config/app.php` — App constants

## [4.1.0] - 2026-04-01

### Added
- **Cross-day programs** — Admin form now has separate "วันที่สิ้นสุด" (end date) field alongside the existing end time; programs spanning midnight (e.g. 23:00–02:00 next day) can now be saved correctly
- **+N badge on public schedule (list view)** — When a program's end date differs from its start date, a pink superscript badge (`+1`, `+2`, …) appears next to the end time in the list view
- **+N badge in calendar view** — Calendar chips, day-panel time rows, and the detail modal all show the same `+N` superscript badge when a program ends on a later date; `calCrossDay(ev)` helper function added to `common.js`
- **Auto-sync end date** — Changing the start date in the admin form automatically advances the end date if it would otherwise precede the new start date

### Changed
- Admin program form layout: date + time fields reorganised into two rows (start date/time row, end date/time row) for clarity
- `.program-time-nextday` and `.cal-chip-nextday` share a single CSS rule block for consistent styling

### Files Changed
- `admin/index.php` — End date field in program form with auto-sync logic
- `index.php` — Cross-day badge (`+N`) display in list view and event detail
- `js/common.js` — `calCrossDay()` helper for calendar view cross-day detection
- `styles/index.css` — `.program-time-nextday` styling

## [4.0.3] - 2026-03-26

### Added
- **My Upcoming Programs — event color coding** — each event gets a distinct pastel background + left-border accent (6 colors cycling: pink, blue, green, amber, purple, teal); applies to both the main program list and the mini calendar day modal; `now-playing` highlight still overrides event color

### Files Changed
- `my.php` — Event color map, pastel background + left-border accent styling

## [4.0.2] - 2026-03-26

### Fixed
- **ICS Export filter mismatch** — `exportToIcs()` in `common.js` was not forwarding `type[]` filter to export URL; type filter was silently ignored on export
- **ICS Export artist filter mismatch** — `export.php` was filtering artists against raw `categories` text field, while `index.php` (v3.0.0+) uses the `program_artists` junction table with canonical artist names; artists selected in UI could be missed in exported ICS; `export.php` now mirrors `index.php` logic (junction table first, fallback to categories text)

### Files Changed
- `js/common.js` — Forward `type[]` filter parameter in `exportToIcs()`
- `export.php` — Mirror `index.php` artist filter logic (junction table + variants)

## [4.0.1] - 2026-03-25

### Fixed
- 🌐 **Timezone label language switch** — `program-time-local` spans now re-render label text (`เวลาท้องถิ่น` / `local time` / `現地時刻`) on language change via `appLangChange` event; `updateTimezoneLabels(lang)` reads stored `data-localtime` attribute instead of recomputing
- 🌐 **Timezone badge inline** — event page timezone badge changed from tooltip (`title`) to inline text: `🕐 Asia/Tokyo (Asia/Bangkok)` when client timezone differs; `🕐 Asia/Bangkok` when same
- 🕐 **Local time shows full range** — `program-time-local` now shows start–end range `(10:00–11:00 local)` instead of start only; `data-utc-end` attribute added to `.program-time` span in `index.php`
- 📐 **`program-time-local` block layout** — changed to `display: block; margin-top: 2px` so local time appears on its own line below the event-timezone time
- 🛡️ **Duplicate span guard** — `initTimezoneDisplay()` checks `nextSibling.classList` before appending to prevent duplicate `.program-time-local` spans on re-call
- 📅 **Calendar view local time** — `calLocalTimeRange(ev)` helper added; local time now shown in all three calendar surfaces:
  - **Chip** (desktop grid): `cal-chip-time-local` span on new line via `flex-wrap`; chip gets `cal-chip-has-local` class
  - **Day panel** (mobile): `cal-dp-item-time-local` div after time row
  - **Detail modal**: `cal-detail-time-local` div below the time heading
- 🔴 **Day panel Live button separate line** — `.cal-dp-join` changed to `display: block; width: fit-content; margin-top: 0.4rem` so 🔴 Live button is always on its own line

### Files Changed
- `index.php` — Timezone badge inline display, `data-utc-end` for local time conversion
- `js/common.js` — `updateTimezoneLabels()`, local time range re-render on language switch
- `styles/index.css` — `.program-time-local` block layout, calendar detail modal local time styling
- `styles/common.css` — `.event-timezone` badge styling
- `tests/TimezoneTest.php` — Tests for v4.0.1 timezone label and layout fixes

## [4.0.0] - 2026-03-25

### Added
- **Per-event Timezone** — `timezone TEXT DEFAULT 'Asia/Bangkok'` column in `events` table; each event can have its own timezone (e.g. Asia/Tokyo, America/Los_Angeles)
- **Timezone badge** on event page header — shows the event's timezone; if browser timezone differs, badge shows tooltip with user's local timezone
- **Local time conversion** — JS `initTimezoneDisplay()` in `common.js`; detects browser timezone vs event timezone mismatch; appends `(HH:MM local time)` after program times for users in a different timezone
- **`data-utc` attribute** on `.program-time` spans — UTC Unix timestamp for JS timezone conversion via `Intl.DateTimeFormat`
- **`window.EVENT_TIMEZONE`** injected in `index.php` for client-side timezone handling
- **ICS export with TZID format** — `export.php` and `feed.php` now use `DTSTART;TZID=Asia/Bangkok:20260319T100000` format + VTIMEZONE block instead of UTC `Z` format; `X-WR-TIMEZONE` reflects per-event timezone
- **`icsVtimezone(string $tzid): string`** in `functions/ics.php` — generates RFC 5545-compliant VTIMEZONE block with STANDARD + DAYLIGHT components (auto-detected via PHP `DateTimeZone::getTransitions()`)
- **`icsOffsetString(int $seconds): string`** in `functions/ics.php` — formats UTC offset as ±HHMM
- **`get_event_timezone($eventMeta): string`** in `functions/helpers.php` — priority: event.timezone → DEFAULT_TIMEZONE → 'Asia/Bangkok'
- **`define('DEFAULT_TIMEZONE', 'Asia/Bangkok')`** in `config/app.php`
- **Admin timezone picker** — `<select id="conventionTimezone">` with 16 common timezones in 4 region groups (Asia, Europe, Americas, Pacific)
- **Image export timezone label** — `image.php` footer shows timezone alongside generated timestamp
- **Migration** — `tools/migrate-add-timezone-column.php` (idempotent); `setup.php` CREATE TABLE includes timezone column
- **CSS** — `.event-timezone` (monospace header badge) and `.program-time-local` (small italic local time annotation) in `styles/index.css`
- **i18n** — `tz.badge` and `tz.localTime` keys in `js/translations.js` (TH/EN/JA)
- **Admin Help Pages** — `admin/help.php` (Thai) and `admin/help-en.php` (English) each have a new "🌐 Per-event Timezone (v4.0.0)" section covering: effects table (ICS/feed, event page, image export), how-to-set steps, 16-option timezone reference table, and 4 verification methods (ICS export test, live feed test, browser badge test with DevTools Sensors, automated CLI test)
- **67 new automated tests** in `tests/TimezoneTest.php` — DB schema, migration idempotency, DEFAULT_TIMEZONE constant, `get_event_timezone()` priority logic, `icsOffsetString()`, `icsVtimezone()` RFC 5545 VTIMEZONE block, UTC timestamp computation, DB CRUD, export.php/feed.php TZID format, index.php injection, admin API, translations.js keys, common.js `initTimezoneDisplay()`, CSS classes, setup.php integration — **total: 2509 tests (14 suites)**

### Changed
- `index.php` `normalizedEvents` timestamp computation changed from `strtotime()` to `new DateTime($t, $eventTzObj)->getTimestamp()` for correct UTC when event timezone ≠ Asia/Bangkok
- `admin/api.php` `createEvent()` and `updateEvent()` now accept and persist `timezone` field with PHP `DateTimeZone` validation

### Files Changed
- `tools/migrate-add-timezone-column.php` (new) — Idempotent migration adding `timezone` column to `events` table
- `tests/TimezoneTest.php` (new) — 67 automated tests for all timezone features
- `config/app.php` — `DEFAULT_TIMEZONE` constant definition
- `functions/helpers.php` — `get_event_timezone()` priority logic with validation
- `functions/ics.php` — `icsVtimezone()` RFC 5545 VTIMEZONE block generation, `icsOffsetString()` UTC offset formatting
- `admin/api.php` — Timezone field acceptance in event CRUD operations
- `admin/index.php` — Timezone picker (`<select>`) in event form with 16 timezone options
- `admin/help.php` — Help page section "🌐 Per-event Timezone" with effects table and verification methods (Thai)
- `admin/help-en.php` — Same help section in English
- `export.php` — DTSTART with TZID format, VTIMEZONE block prepended to ICS feed
- `feed.php` — Per-event timezone in X-WR-TIMEZONE, VTIMEZONE block generation
- `index.php` — Event timezone badge display, local time conversion via `initTimezoneDisplay()`
- `image.php` — Timezone label in PNG image footer
- `js/common.js` — `initTimezoneDisplay()` function for client-side local time conversion
- `js/translations.js` — `tz.badge` and `tz.localTime` keys (TH/EN/JA)
- `styles/index.css` — `.event-timezone` badge and `.program-time-local` annotation styling
- `setup.php` — Timezone column in CREATE TABLE and setup wizard integration
- `tests/run-tests.php` — Test runner updates for new TimezoneTest suite

## [3.7.0] - 2026-03-25

### Added
- 🎤 **Artist & Group Portal** (`artists.php`) — new public page at `/artists` listing every group and solo artist in the system; groups displayed as gradient cards showing group name, member count, program count, and clickable member chips; solo artists shown in a responsive grid; all items link to their `/artist/{id}` profile page
- 🔍 **Real-time search** — search bar filters both group cards (including member names inside each card) and solo artist cards simultaneously with no page reload; matching member chip highlighted in yellow
- 🗂️ **Tab filter** — three tabs (All / Groups / Solo) let users narrow the view instantly client-side
- 📊 **Stats bar** — shows total group count and total artist count at a glance
- 🌐 **i18n** — full TH/EN/JA support via `data-i18n` attributes and new `portal.*` + `nav.artists` translation keys in `js/translations.js`
- ⚡ **Query cache** — portal data (groups + members + solo artists) cached in `cache/query_portal.json` (TTL 1 hr); invalidated automatically by `invalidate_artist_query_cache()` whenever artists or variants change
- 🔗 **Nav link on homepage** — `🎤 ศิลปิน` link added to `<nav class="header-nav">` on both the event-listing header and the event-detail header in `index.php`; placed before `📋 แหล่งข้อมูลอ้างอิง`

### Files Changed
- `artists.php` (new, previously `portal.php`) — Artist & Group Portal page with real-time search and tab filters
- `styles/portal.css` (new) — Styling for group cards, member chips, and solo artist grid
- `js/translations.js` — New `portal.*` and `nav.artists` translation keys (TH/EN/JA)
- `functions/cache.php` — `invalidate_artist_query_cache()` integration
- `index.php` — Nav link to artists portal on event-listing and event-detail headers
- `config/app.php` — App constants

---

## [3.6.12] - 2026-03-25

### Added
- 🎤 **Admin Artists — member count badge for groups** — group rows in the Artists table now display a yellow badge showing the number of members (e.g. `3 คน`) immediately after the `กลุ่ม` type badge; badge is hidden when the group has no members yet; count is computed server-side via a subquery (`SELECT COUNT(*) FROM artists WHERE group_id = a.id AND is_group = 0`) added to `listArtists()` in `admin/api.php`

### Files Changed
- `admin/api.php` — Subquery for member count in `listArtists()`
- `admin/index.php` — Member count badge display in Artists table
- `config/app.php` — App constants

---

## [3.6.11] - 2026-03-24

### Fixed
- 🌐 **i18n: 404 page now multilingual** — the 404 error page previously had all text hardcoded in Thai; replaced the PHP echo block with a proper HTML template using `data-i18n` attributes, loading `translations.js`, and an inline script that reads `localStorage.lang` and applies translations; now renders correctly in TH/EN/JA like every other page in the app
- 🌐 **i18n: Filter empty-state text** — "no artist data" and "no venue data" messages inside the filter panel were hardcoded in Thai with no i18n; added `data-i18n` attributes (`filter.noArtist`, `filter.noVenue`) and added translation keys for all three languages
- 🌐 **i18n: `my.copyUrl` / `fav.copyUrl` keys were English in all locales** — Thai locale now uses `'📋 คัดลอก URL'` and Japanese locale now uses `'📋 URLをコピー'`
- 🌐 **i18n: `fav.noArtists` (JA) grammar** — `'フォロー中がいません'` (incomplete) → `'フォロー中のアーティストがいません'` (grammatically complete)
- 🌐 **i18n: `fav.statsPrograms` (JA) text truncated** — `'アップカミング'` → `'アップカミングプログラム'` to match the TH/EN meaning
- 🌐 **i18n: `howToUse.subtitle` stale `"your event"` placeholder** — Thai and Japanese locales contained the literal string `"your event"` instead of the site name; replaced with `'Idol Stage Timetable'` so the existing IIFE at the bottom of `translations.js` can substitute the custom title when one is configured
- 🌐 **i18n: `section1.desc` stale `"your event"` placeholder** — same fix applied to TH and JA
- 🌐 **i18n: `contact.disclaimer.text` stale `"your event organizers"` placeholder** — removed the unresolved placeholder from all three locales; text now reads as a complete, self-contained sentence
- 🐛 **`openLcalDayModal` status badge not translating** — `const translations` in `translations.js` is a block-scoped global and is not a property of `window`, so `window.translations` was always `undefined`; the event status badge in the homepage calendar day modal always showed the raw JS value `'ongoing'` / `'upcoming'` / `'past'` regardless of selected language; fixed by using `translations[lang]` directly instead of `window.translations[lang]`; same fix applied to the "▼ Read more" button initialisation
- 🐛 **Event Picker "currently viewing" badge not translating** — `✓ ดูอยู่` badge on the active event card had no `data-i18n` attribute and no translation key; added `data-i18n="eventPicker.viewing"` and translation keys for TH/EN/JA
- 🐛 **`window.currentLang` always undefined in inline scripts** — `currentLang` in `common.js` is declared as `let`, which is not a property of `window`; all inline scripts in `index.php` that read `window.currentLang` always got `undefined` and fell back to Thai regardless of the selected language; fixed by adding `window.currentLang = lang` inside `changeLanguage()` so it is always kept in sync
- 🐛 **Homepage calendar day modal not re-rendering on language switch** — the modal's innerHTML was built once on open and had no `data-i18n` attributes, so `updateLanguage()` could not update it; fixed by storing the active date in `window._lcalActiveDate` and listening for the new `appLangChange` custom event dispatched by `changeLanguage()`; the listing calendar grid and open day modal are both re-rendered immediately when language changes

### Added
- 🌐 **New translation keys** — `filter.noArtist`, `filter.noVenue`, `notFound.heading`, `notFound.desc`, `notFound.back`, `eventPicker.viewing` added in TH/EN/JA
- 🔧 **`appLangChange` custom DOM event** — `changeLanguage()` in `common.js` now dispatches `document.dispatchEvent(new CustomEvent('appLangChange', { detail: { lang } }))` after updating the page; page-specific inline scripts can listen to this event to re-render dynamic content without monkey-patching `window.changeLanguage`

### Files Changed
- `index.php`
- `js/translations.js`
- `js/common.js`
- `config/app.php`

## [3.6.10] - 2026-03-23

### Changed
- 🎨 **Event listing card header — dates displayed below the event name** — changed `.program-card-header` from row layout (name left / date right) to column layout so long event names have full width without being squeezed by the date
- 🎨 **Homepage calendar modal event cards — same layout change** — `.lcal-event-card-header` updated to match

### Files Changed
- `styles/index.css`

## [3.6.9] - 2026-03-22

### Added
- ✨ **Now-playing highlight on My Upcoming Programs** — when the page loads, programs that are currently in progress are highlighted with a distinct style so users can instantly see what is on right now; highlight is applied once on page load (no auto-refresh — users reload the page manually to update)

### Files Changed
- `my.php`

## [3.6.8] - 2026-03-21

### Fixed
- 🐛 **`credits.php` missing `BASE_PATH` → `fav_slug` cleared on visit** — `credits.php` was the only public page that did not define `window.BASE_PATH` / `const BASE_PATH` before loading `common.js`; when `injectFavNavButton()` ran, `base` fell back to `''` and the background validation fetch went to `/api/favorites?...` (root-relative) instead of the correct subdirectory path; sites hosted in a subdirectory (e.g. `/stage-idol-calendar/`) received a 404, which triggered `localStorage.removeItem('fav_slug')` and silently removed the user's favorites shortcut buttons; fixed by adding `const BASE_PATH = <?php echo json_encode(get_base_path()); ?>;` in the inline script before `common.js` loads

### Files Changed
- `credits.php`

## [3.6.7] - 2026-03-20

### Added
- 🔧 **`fav_slug` recovery UX** — when the favorites token in localStorage is expired or invalid, `my-favorites.php` and `my.php` error screens now show two recovery buttons: "🗑️ Clear from Browser" (removes `fav_slug` from localStorage and redirects to home) and "✨ Create New Favorites" (POSTs to `api/favorites.php?action=create`, saves the new slug to localStorage, and redirects to the new favorites URL) — users no longer need to open developer tools to recover from a stale token
- 🔧 **Silent self-healing in `injectFavNavButton()`** — after injecting ⭐/📅 nav buttons, a background `fetch` validates the stored slug against the server; on 400/404 response it automatically removes `fav_slug` from localStorage and removes the injected buttons
- 🌐 **Translation keys** — added `fav.clearStorage` and `fav.createError` in TH/EN/JA

### Files Changed
- `my-favorites.php`
- `my.php`
- `js/common.js`
- `js/translations.js`

## [3.6.6] - 2026-03-20

### Added
- 📋 **Table of Contents on How-to-Use page** — `<nav class="toc-section">` renders 18 section links in a 2-column grid (1 column on mobile); each item is an anchor link that jumps directly to the target section; labels use existing `data-i18n` keys and re-render automatically when switching TH/EN/JA
- 🔑 **`toc.title` translation key** — TH `📋 สารบัญ` / EN `📋 Table of Contents` / JA `📋 目次`

### Changed
- 🔀 **Section order reorganized by priority** — Overview → Event Picker → Homepage Calendar → Filtering → Date Jump Bar → Program Detail Modal → Live Stream → Gantt Chart → Calendar View → Save/Export → Artist Profile → Artist Feed Subscribe → My Favorites → Past Events → Submit Request → Language → Mobile → FAQ; all sections have `id="s-*"` attributes for anchor navigation

### Files Changed
- `how-to-use.php`
- `js/translations.js`
- `styles/how-to-use.css`

## [3.6.5] - 2026-03-20

### Added
- ⚡ **Homepage listing query cache** (`cache/query_listing.json`, TTL 3600s) — caches both `$activeEvents` (from `get_all_active_events()`) and `$listingCalData` (homepage calendar dot data) together in a single file; a cache hit skips both DB queries entirely; a cache miss runs both queries and saves the result; automatically invalidated when any program or event is modified

### Changed
- 🔄 **`invalidate_query_cache()`** — `query_listing.json` added to the invalidation list for both specific-event and global (null) call patterns
- 🔄 **Admin `createEvent()` / `updateEvent()` / `deleteEvent()`** — now call `invalidate_query_cache()` to bust `query_listing.json` when event metadata changes; previously event writes did not invalidate the query cache

### Files Changed
- `index.php`
- `functions/cache.php`
- `admin/api.php`

## [3.6.4] - 2026-03-20

### Added
- 📅 **Homepage Calendar View** — monthly calendar above the Events listing on the homepage; days with programs show a pink dot; clicking a day opens a modal listing the **Events** (conventions) active on that day — each shown as a mini event card with gradient header (name + date range), status badge (กำลังจัดงาน / กำลังจะมาถึง / จบแล้ว), and "📋 ดูตารางเวลา" button; calendar navigates per month (defaults to current month); shows all active events including past; language-aware month/day labels re-render on language switch

### Changed
- 🎨 **Calendar section title** — `"📅 ปฏิทินกิจกรรม"` header now has `margin-top: 10px` for better visual separation
- 🎨 **Events listing title** — renamed from `"Events"` → `"🎪 รายการกิจกรรม"` (EN: `🎪 Events`, JA: `🎪 イベント一覧`) with icon prefix

### Files Changed
- `index.php`
- `styles/index.css`
- `js/translations.js`
- `how-to-use.php`

## [3.6.3] - 2026-03-20

### Changed
- 🔔 **My Upcoming Programs — include group programs** — if a followed artist belongs to a group, programs linked to that group are now included automatically in `my.php` and `my-feed.php`; group IDs are resolved from `artists.group_id` and merged into the program query `artist_id IN (...)` set; no changes to followed-artist list or UI

### Files Changed
- `my.php`
- `my-feed.php`

## [3.6.2] - 2026-03-20

### Added
- 📊 **Admin Events tab — sortable columns** — the Events table in Admin Panel supports sorting by clicking any column header: `#`, `Name`, `Start Date`, `End Date`, `Active`, `Programs`; client-side sort (no API reload required); default sort `Start Date DESC` (newest first); click again to toggle asc/desc; ↕ / ↑ / ↓ icons indicate sort state

### Files Changed
- `admin/index.php`

## [3.6.1] - 2026-03-20

### Changed
- 🗂️ **Personal feed cache — shard co-location** — personal feed `.ics` cache files moved from `cache/feed_fav_{md5}.ics` (flat directory) into `cache/favorites/{shard}/{token}.ics` (same shard directory as the favorites `.json` file); `fav_cleanup_expired()` GC now deletes both `.json` and `.ics` for expired tokens together; no user-visible behavior change

### Files Changed
- `my-feed.php`
- `functions/favorites.php`

## [3.6.0] - 2026-03-20

### Added
- 🔔 **Personal ICS Subscription Feed** (`my-feed.php`) — live webcal feed scoped to a user's favorited artists; URL `/my/{slug}/feed` (via `.htaccess`); shows all upcoming programs from followed artists across active events; SUMMARY prefixed with `[Event Name]` for context in calendar apps; RFC 5545 compliant (line folding, CATEGORIES delimiter, VALARM 15-min reminder)
- 🔔 **Subscribe button on My Upcoming Programs** — 🔔 Subscribe button added to the Save URL banner; opens a modal with webcal:// link (Apple Calendar / iOS / Thunderbird) + https:// URL + Copy button + Outlook subscription instructions + sync frequency notice
- 📦 **`functions/ics.php`** — ICS helper functions (`icsLine`, `icsFold`, `icsEscape`, `icsEscapeText`) extracted from `feed.php` into a shared file; both `feed.php` and `my-feed.php` `require_once 'functions/ics.php'`

### Changed
- 🏗️ **`feed.php` refactor** — removed inline function definitions; now delegates to `functions/ics.php`; no behavior change

### Files Changed
- `my-feed.php` (new)
- `functions/ics.php` (new)
- `feed.php`
- `.htaccess`
- `my.php`
- `tests/FeedTest.php`

## [3.5.4] - 2026-03-20

### Fixed
- 🐛 **Admin artist profile link 404** — artist name links in Admin › Artists were pointing to `/admin/artist/{id}` instead of `/artist/{id}` because `BASE_PATH` resolves from `admin/index.php`'s `SCRIPT_NAME` and returns `/admin`; fixed by adding JS constant `APP_ROOT = dirname(BASE_PATH)` and using `APP_ROOT` for all links pointing to public pages

### Files Changed
- `admin/index.php`

## [3.5.3] - 2026-03-20

### Fixed
- 🐛 **Admin form HTML entity encoding** — `'` (single quote) and `&` entered in admin forms were being stored and re-displayed as `&#039;` and `&amp;` due to `htmlspecialchars()` being incorrectly applied to JSON API responses; removed `escapeOutputData()` side effects and all standalone `htmlspecialchars()` calls from `admin/api.php` JSON output paths — JSON transport now carries raw data; HTML escaping remains in `admin/index.php` JS layer (`escapeHtml()` on `innerHTML` insertions, `textContent`/`.value` for form fields)

### Files Changed
- `admin/api.php`

## [3.5.2] - 2026-03-20

### Added
- 📅 **Mini Calendar on My Upcoming Programs** — monthly calendar grid inserted between the "Followed Artists" section and the "Upcoming Programs" list; navigates only between months that have programs (◀ ▶ disabled at boundary); dates with programs show a pink dot; today is highlighted with a filled circle
- 🗓️ **Day Programs Modal** — clicking a date with a dot opens a modal showing all programs for that day in the same format as the list (time, title, type badge, event name, location, categories, Live button); closes on ✕ button, overlay click, or Escape key
- 🌐 **Calendar re-renders on language change** — month/year title, day-of-week headers, and modal date label all update immediately when switching TH/EN/JA
- 🧪 **FavoritesTest** — 84 new automated tests covering the full v3.5.x Favorites system: config constants, UUID v7 format/uniqueness, HMAC determinism, slug build/parse/tamper resistance, file I/O (write→read roundtrip, sharded path), `api/favorites.php` action structure (create/get/add/remove, rate-limit 429, slug validation), `my-favorites.php` solo/group split + sort controls + localStorage preference, `my.php` mini calendar + day modal + XSS-safe `JSON_HEX_TAG`, translations.js 3-language coverage, `js/common.js` nav injection, `artist.php` follow/unfollow, `.htaccess` routing, `how-to-use.php` section17 keys — **total 2036 tests** (13 suites)

### Changed
- 📝 **`how-to-use.php` section17 updated** — My Favorites description updated to mention the solo/group split; A→Z / Z→A sort sub-point added; My Upcoming Programs description updated to "grouped by date"; new "📅 Mini Calendar View" sub-section with 3 bullet points (position, dot indicators, day modal)
- 📝 **`js/translations.js` new keys** — `section17.myfav.sort` and `section17.cal.title` / `section17.cal.feature1-3` added in all 3 languages (TH/EN/JA)
- 📖 **`admin/help.php` + `admin/help-en.php` Artists tab** — three new sub-sections documented: **Copy Artist** (pre-fill behavior, variants checkbox, copy flow), **Bulk Import Artists** (Step 1 textarea → Step 2 results + summary), **Bulk Select & Bulk Actions** (Add to Group / Remove from Group table + `is_group=0` filter callout)

### Files Changed
- `my.php`
- `how-to-use.php`
- `js/translations.js`
- `admin/help.php`
- `admin/help-en.php`
- `tests/FavoritesTest.php` (new)
- `tests/run-tests.php`

## [3.5.1] - 2026-03-20

### Changed
- 🎤 **My Favorites — split into two sections** — solo artists (🎤) and groups (🎵) are now rendered in separate sections instead of a single mixed list; PHP splits `$artistIds` into `$solos` and `$groups` before rendering
- 🔃 **Sort controls per section** — each section has its own A→Z / Z→A sort buttons; sorting is applied client-side with `localeCompare` (locale-aware, handles Thai/Japanese); active sort button is highlighted; preference is saved to `localStorage` (`fav_sort_solo` / `fav_sort_group`) and restored on page load
- 🌐 **i18n** — new translation keys `fav.soloArtists`, `fav.groups`, `fav.sort`, `fav.sortAZ`, `fav.sortZA` added to TH / EN / JA

### Files Changed
- `my-favorites.php`
- `js/translations.js`

## [3.5.0] - 2026-03-20

### Added
- 📋 **Copy Artist modal** — "Copy" button on each artist row opens a pre-filled modal (name + " (copy)", same is_group and group_id); a "Variants to copy" section lists all source variants as checkboxes (all checked by default) with "Select all" / "Deselect all" buttons; all fields are editable before saving; after a successful create, selected variants are created one-by-one via `artists_variants_create`
- 👥 **Bulk artist selection + Bulk Add to Group** — per-row checkboxes with a Select All header checkbox; a yellow Bulk Toolbar appears when ≥ 1 artist is selected; "Add to Group" button opens a group picker modal; "Remove from Group" button clears `group_id` for all selected artists; artists with `is_group = 1` are automatically skipped server-side
- 📥 **Bulk Import Artists** — "📥 Import" button in the Artists toolbar; Step 1 modal accepts a newline-separated list (1 name per line, up to 500), an optional "Is Group" checkbox, and an optional target group dropdown; Step 2 shows a per-name result list (✅ created / ⚠️ duplicate / ❌ error) with a summary bar and a "← Back" button to import another batch; artist list auto-refreshes when any artists were created

- 🔒 **Access denied on `/my` and `/my-favorites` without slug** — visiting either page without a personal slug (UUID-HMAC) now shows a 🔒 "Access Denied" screen with a description and a home button, instead of a generic empty state
- 🌐 **Full 3-language support for `/my` and `/my-favorites`** — all UI text uses `data-i18n` attributes; new translation keys `fav.noAccess` and `fav.noAccessDesc` added to TH / EN / JA in `js/translations.js`

### Changed
- 🔄 **Artists table** — added a checkbox column (individual + select-all) for bulk selection
- 🔄 **`my.php` footer** — aligned with `index.php`: "Built with ❤️ for idol fans" tagline, GitHub link, version badge
- 🔄 **`my-favorites.php` footer** — same footer alignment as `index.php`
- 🔄 **`my.php` header nav** — both ⭐ My Favorites and 📅 My Upcoming Programs buttons always shown when slug is present; current page button highlighted (sakura-medium background)
- 🔄 **`my-favorites.php` header nav** — both ⭐ and 📅 buttons always shown when slug is present; current page button highlighted
- 🔄 **`my.php` program sort order** — Upcoming Programs are now sorted by program start datetime across all followed events (date-first grouping), instead of being grouped by event; each date group shows a date header, and each program row shows the event name as inline metadata; programs within the same date are ordered by start time (`ORDER BY p.start ASC`)

### API
- `POST admin/api.php?action=artists_bulk_set_group` — accepts `{ids[], group_id}`; updates `group_id` for multiple artists (`is_group = 0` only); `group_id = null` removes group membership
- `POST admin/api.php?action=artists_bulk_import` — accepts `{names[], is_group, group_id}`; inserts one artist per name; returns `{results: [{name, status, id?}]}` with `created/duplicate/error` statuses; invalidates caches when `created > 0`

### Files Changed
- `admin/api.php`
- `admin/index.php`
- `config/app.php`
- `my.php`
- `my-favorites.php`
- `js/translations.js`

## [3.4.0] - 2026-03-20

### Added
- ⭐ **Anonymous Favorites system** — users can follow artists without logging in; UUID v7 token + HMAC-signed slug (`{uuid}-{hmac[:12]}`); stored in `cache/favorites/{shard}/{uuid}.json`; TTL 365 days with auto-touch on each visit; `fav_maybe_cleanup()` probabilistic garbage collection
- 📅 **My Upcoming Programs** (`my.php`) — `/my/{uuid-hmac}`; server-side PHP dashboard showing upcoming programs from followed artists, grouped by event and date; Save URL banner (URL + Copy + warning); auto-saves slug to `localStorage`
- ⭐ **My Favorites** (`my-favorites.php`) — `/my-favorites/{uuid-hmac}`; server-side PHP page showing followed artist list with profile links and unfollow buttons; Save URL banner; link button to My Upcoming Programs; auto-saves slug to `localStorage`
- 🔗 **Persistent nav shortcuts** — `injectFavNavButton()` in `js/common.js`; when `fav_slug` exists in `localStorage`, injects ⭐ (`/my-favorites/{slug}`) and 📅 (`/my/{slug}`) as circular icon buttons into `.header-top-left` on all pages; skipped on `/my/` and `/my-favorites/` pages
- 🔌 **Favorites API** (`api/favorites.php`) — `action=follow`, `action=unfollow`, `action=get`, `action=remove`; HMAC validation on all write operations; rate limiting; returns artist details when `?details=1`
- ⚙️ **`config/favorites.php`** — `FAVORITES_DIR`, `FAVORITES_TTL`, `FAVORITES_HMAC_SECRET`, `FAVORITES_HMAC_LENGTH`, `FAVORITES_MAX_ARTISTS`, `FAVORITES_RATE_LIMIT`, `FAVORITES_RATE_WINDOW`, `FAVORITES_RL_DIR`
- 🛠️ **`tools/generate-favorites-secret.php`** — generates a secure 256-bit hex HMAC secret

### Changed
- 🔗 **`.htaccess`** — added `^my-favorites/([0-9a-f-]+)/?$` and `^my/([0-9a-f-]+)/?$` rewrite rules; `^api/favorites/?$` → `api/favorites.php`
- 🎨 **Page titles** — `/my/{slug}` = "📅 My Upcoming Programs"; `/my-favorites/{slug}` = "⭐ My Favorites"
- 🗑️ **Removed how-to-use icon** from event detail header (`index.php`) — reduces icon count on mobile
- 🔄 **`localStorage.fav_slug`** — now a shortcut helper only; auto-saved/replaced when visiting either favorites page via URL
- 🔄 **Follow button toggle** (`artist.php`) — ☆ ติดตาม / ★ ติดตามแล้ว toggles in-place without redirect; first-time follow (no existing `fav_slug`) redirects to `/my-favorites/{slug}`; subsequent follow/unfollow updates button state only

### Files Changed
- `my.php` (new)
- `my-favorites.php` (rewritten)
- `api/favorites.php` (new)
- `functions/favorites.php` (new)
- `config/favorites.php` (new)
- `tools/generate-favorites-secret.php` (new)
- `js/common.js`
- `js/translations.js`
- `styles/common.css`
- `.htaccess`
- `index.php`
- `artist.php`
- `setup.php`
- `config.php`

## [3.3.0] - 2026-03-19

### Added
- 🖼️ **Server-side image export** (`image.php`) — replaces html2canvas with PHP GD; generates PNG server-side with no external JS dependency; supports Thai/multi-byte text via TrueType fonts (Sarabun/Noto, fallback to system fonts); `fonts/README.md` added with font download instructions
- 🎨 **Image layout** — Sakura-themed table with alternating row colors; column header; date group headers; program rows with time badge (sakura-medium pink, white text, compact fixed height), title, venue, type badge, artist badges; vertical column separators between fields; footer with site title + generated timestamp
- 📋 **Single-venue image mode** — when `venue_mode=single`, venue column is removed and venue name (from first program) is shown below event title in image header
- 🔔 **Image export uses current filters** — `saveAsImage()` in `js/common.js` passes current URL query params (artist, venue, type, q, event) to `/image` endpoint; `_t` timestamp prevents browser caching
- 🗄️ **Image cache** (`cache/images/`) — generated PNGs cached server-side for 1 hour (key = md5 of event + filters + lang + APP_VERSION); served via `readfile()` on hit; auto-invalidated when programs are created/updated/deleted via `invalidate_image_cache()`; `IMAGE_CACHE_DIR` + `IMAGE_CACHE_TTL` constants in `config/cache.php`
- 🔤 **Three-font architecture** (`image.php`) — `gdText()` / `gdMeasure()` split text into per-character runs and route each character to the correct font: Thai/Latin → main font; Japanese/CJK → `$fontCjk`; BMP symbols → symbol fallback font
- 🔤 **Japanese / CJK font support** — `isCjkCodepoint()` detects Hiragana (U+3040–U+309F), Katakana (U+30A0–U+30FF), Kanji (U+4E00–U+9FFF), CJK Symbols & Punctuation (U+3000–U+303F, covers 【】「」『』 etc.), and Fullwidth Forms (U+FF00–U+FF9F); `$fontCjk` auto-detected via differential pixel test: か (U+304B) vs き (U+304D) — distinct shapes confirm real Hiragana glyphs
- 🔤 **GNU Unifont as shared-hosting CJK fallback** — `unifont.ttf` / `unifont.otf` added to CJK font candidates; users who place Unifont for symbol support automatically get Japanese rendering without additional files; covers full BMP including Hiragana, Katakana, and common Kanji
- 🔤 **SMP Math Alphanumeric normalization** — `gdNormalizeSmp()` converts Mathematical Alphanumeric Symbols (U+1D400–U+1D7FF) to base ASCII before GD rendering since PHP GD/libgd cannot handle 4-byte UTF-8 (SMP) on many systems; 𝗕𝗔𝗖𝗞 𝗜𝗡 𝗧𝗜𝗠𝗘 → BACK IN TIME; `gdMapMathChar()` covers all major letter/digit style ranges (Bold, Italic, Sans-Serif, Monospace, etc.)
- 🔤 **Reliable font detection** — symbol fallback: differential pixel test ♾ (U+267E) vs ★ (U+2605); CJK: か vs き; both use BMP 3-byte UTF-8 (reliable on all GD builds); rejects color/bitmap fonts (CBDT → 0 pixels) and fonts where both chars render as identical .notdef

### Changed
- 🔄 **`saveAsImage()` rewrite** — removed html2canvas lazy-load; replaced with `fetch()` to `/image` endpoint; downloads PNG via Blob URL
- 🔄 **Image cache key includes active font paths** — adding or replacing a font file automatically busts cached images; prevents serving stale PNGs generated before a font was installed
- 🐛 **Japanese labels now render correctly** — column headers (時間 プログラム 会場 タイプ), date group headers (2026年3月19日（水）), and "no programs" message (プログラムなし) were using `imagettftext()` directly (bypassing per-character routing); changed to `gdText()` so Japanese text is correctly routed to `$fontCjk`
- 🐛 **LIVE indicator `●` → `*`** — `●` (U+25CF) rendered as missing-glyph square on some fonts; replaced with ASCII `*` which all fonts support
- 📖 **`fonts/README.md` rewritten for shared hosting** — new structure: Section 1 (Thai font), Section 2 (GNU Unifont — recommended, covers symbols + Japanese in one file), Section 3 (Symbola — symbol-only alternative), Section 4 (dedicated Noto Sans JP — higher quality Japanese); warning about Google Fonts variable font vs static version; recommended setups table
- 🐳 **Dockerfile** — added `fonts-noto-cjk` for proper Japanese rendering in Docker; updated comments to reflect three-font architecture
- 🎨 **Theme-aware image palette** — generated PNG matches the event's theme; `get_site_theme($eventMeta)` is called before the cache check so each theme gets its own cached image; palette lookup table covers all 7 themes (sakura/ocean/forest/midnight/sunset/dark/gray) with per-theme RGB values for: header background (deep), column header (medium), accent badges, date section background, date text, borders, alternating row tint, and venue subtitle; theme is included in the image cache key so switching a theme automatically invalidates previous cached images
- 🐛 **Artist filter mismatch fix** — `image.php` was filtering artists via the `categories` text field while `index.php` uses the `program_artists` junction table (canonical artist names); fixed by reading `program_artists_map` from query cache when available, and querying the `program_artists` table directly when cache is cold; fallback to `categories` text when junction table is absent — mirrors `index.php` `$useArtistsTable` logic exactly
- ⚡ **Image cache key: `xxh128` replaces `md5`** — `hash('xxh128', ...)` is faster than `md5()` for non-cryptographic cache key generation; produces 32 hex chars (same length as md5); PHP 8.1+ built-in
- 🛠️ **`setup.php` v3.3.0 support** — GD extension check (`extension_loaded('gd') && function_exists('imagettftext')`) added as optional requirement in Step 1; `cache/images/` and `fonts/` directories added to Step 2 directory checks; font file detection for NotoSansThai, NotoSansJP, NotoEmoji, Symbola, unifont (all tested and confirmed working); summary badges (Thai/Latin ✅, Japanese/CJK ✅, Symbols/Emoji ✅); font rows displayed inline with directory rows using `check-row` class for consistent margin/padding

### Files Changed
- `image.php` (new)
- `js/common.js`
- `fonts/README.md` (new)
- `config/cache.php`
- `functions/cache.php`
- `Dockerfile`
- `nginx-clean-url.conf`
- `setup.php`

## [3.2.0] - 2026-03-19

### Added
- **Artist ICS Subscription Feed** (`/artist/{id}/feed`) — live `webcal://` + `https://` feed scoped to a single artist across all events; resolves artist name + all variant names from `artist_variants` table for `categories`-based filtering; cache file `cache/feed_artist_{id}_{hash}.ics` (TTL 1 hour); 404 on unknown artist
- **Group programs feed** (`/artist/{id}/feed?group=1`) — when artist belongs to a group, `?group=1` resolves the `group_id` and filters by group name + group variants; cache key includes `_own` / `_group` suffix
- **`styles/artist.css`** — extracted all inline `<style>` from `artist.php` into a standalone stylesheet (artist header, badges, programs table, toggle, `.btn`, `.btn-subscribe`, `.req-modal-overlay` / modal styles)

### Changed
- **Subscribe buttons on Artist Profile** — header card shows `🔔 <ArtistName>` button; members of a group get a second `🔔 <GroupName>` button for group programs feed; both buttons labeled with the actual name (not generic "Subscribe")
- **`openSubscribeModal(isGroup)` in `js/common.js`** — accepts `isGroup` flag; builds URL as `/artist/{id}/feed` or `/artist/{id}/feed?group=1`; falls back to existing event-feed logic when not on artist page
- **`invalidate_feed_cache()` in `functions/cache.php`** — always deletes `feed_artist_*.ics` alongside event-specific files, since artist feeds span all events
- **`.htaccess`** — new rewrite rule `^artist/([0-9]+)/feed/?$` → `feed.php?artist_id=$1` (placed before the existing artist profile rule)

### Files Changed
- `feed.php`
- `artist.php`
- `js/common.js`
- `functions/cache.php`
- `.htaccess`
- `styles/artist.css` (new)

## [3.1.0] - 2026-03-19

### Added
- **Query Cache for event page** (`index.php`) — DB query results (programs, venues, types, artists, artist maps, cross-event data) cached as `cache/query_event_{id}.json`; cache key includes `$eventId` (0 = no filter); IcsParser + all PDO queries skipped on cache hit; filtering still applied PHP-side from cached data
- **Query Cache for artist profile page** (`artist.php`) — artist info, members, variants, programs, and group programs cached as `cache/query_artist_{id}.json`; all DB queries skipped on cache hit; derived vars (`$byEvent`, `$groupByEvent`, `$totalPrograms`) re-computed from cached data on every request
- **`get_query_cache(string $filename): array|false`** — reads JSON cache file; returns `false` on miss, expiry, or decode error; uses `filemtime()` for TTL check
- **`save_query_cache(string $filename, array $data): void`** — writes array as JSON with `LOCK_EX` to prevent concurrent write corruption
- **`invalidate_query_cache(?int $eventId): bool`** — deletes `cache/query_event_{id}.json` + `cache/query_event_0.json` (global page); no `$eventId` = delete all `query_event_*.json`
- **`invalidate_artist_query_cache(): bool`** — deletes all `cache/query_artist_*.json` files
- **`QUERY_CACHE_DIR`** and **`QUERY_CACHE_TTL`** constants in `config/cache.php`; TTL default 3600 s; shares the `cache/` directory

### Changed
- `invalidate_all_caches()` — now also deletes `query_event_*.json` and `query_artist_*.json` patterns (used after DB restore)
- Admin API program write operations (create, update, delete, bulk delete, bulk update, ICS import confirm) — now call `invalidate_query_cache()` + `invalidate_artist_query_cache()` alongside existing `invalidate_data_version_cache()` + `invalidate_feed_cache()`
- Admin API artist write operations (create, update, delete) — now call `invalidate_artist_query_cache()` alongside existing `invalidate_data_version_cache()`
- Admin API variant write operations (create, delete) — now call `invalidate_artist_query_cache()` (previously had no cache invalidation)

### Files Changed
- `config/cache.php`
- `functions/cache.php`
- `index.php`
- `artist.php`
- `admin/api.php`
- `tools/update-version.php`
- `README.md`
- `PROJECT-STRUCTURE.md`
- `ICS_FORMAT.md`

### Fixed
- **`tools/update-version.php` — smart line-by-line replacement** — เปลี่ยนจาก global `str_replace` เป็น line-by-line พร้อม skip patterns; บรรทัดที่เป็น historical version label จะไม่ถูกแทนที่: `(vX.Y.Z+)` (introduced-in label), `**vX.Y.Z+**:` (bold introduced-in), `| vX.Y.Z |` (table Since column), `| **vX.Y.Z**` (Feature Timeline rows), `### vX.Y.Z —` (historical headings), upgrade guide references (`Upgrading from`, `new vX.Y.Z features`, `all vX.Y.Z features`), inline code comments (`= Something vX.Y.Z`)
- **`README.md`, `PROJECT-STRUCTURE.md`, `ICS_FORMAT.md`** — แก้ไข version labels ของ Artist Reuse System features (v3.0.0 → v3.1.0 ผิดพลาดจาก update-version.php ก่อนหน้า) กลับเป็น v3.0.0 ให้ถูกต้อง

---

## [3.0.0] - 2026-03-18

### Added
- **Artist Reuse System** — `artists` table as single source of truth across all events; artist records reused via `program_artists` junction table.
- **`program_artists` junction table** — many-to-many `programs ↔ artists`; ICS import auto-links CATEGORIES field to `artist_id` by direct name match and variant lookup
- **`artist_variants` table** — stores alias/variant names per artist; manageable via Admin UI variants modal
- **Artist Profile page** (`artist.php`) — `/artist/{id}`; displays all programs grouped by event; shows group members and variant names; `.htaccess` rewrite `^artist/([0-9]+)` → `artist.php?id=$1`
- **Artist Profile programs toggle** (`artist.php`) — pill-style toggle between "Programs ทั้งหมด" (own) and "Programs ในนามวง" (group); shown only when artist belongs to a group; default is own programs; choice persists in `localStorage` per artist
- **Clickable artist badges** — artist badge in program rows is a split pill: left button filters by artist, right `↗` link opens artist profile; uses `program_artists` junction for artist id
- **Artist filter — event count badge** — each artist checkbox shows a pink count bubble when the artist appears in multiple events, plus a `↗` profile link
- **"Also appears in" cross-event section** — rendered before the footer on every event page; groups shared artists by event as flex-wrap cards with artist chips linking to profiles
- **Admin Artists tab** — Variants column shows variant count per artist; Variants button opens modal to add/remove variant names; artist name is a link to the profile page
- **Migration** — `tools/migrate-add-artist-variants-table.php` (idempotent); auto-imports variants from `data/artists-mapping.json`
- **Admin API** — `artists_variants_list`, `artists_variants_create`, `artists_variants_delete`
- **`setup.php` bilingual support (TH / EN)** — language switcher (TH / EN buttons) in setup header; session-based detection (`$_SESSION['setup_lang']`, `?lang=th` / `?lang=en` GET param); all visible UI translated: lock banner, status banners, 6 step titles/badges/labels/descriptions, migration table, config summary, quick links, footer; JS `confirm()` / `alert()` strings injected via PHP `setupI18n` object using `json_encode()` for XSS safety

### Changed
- ICS import (`uploadAndParseIcs`, `confirmIcsImport`) now uses `artist_variants` DB table instead of `data/artists-mapping.json` for auto-linking artist names
- Artist filter in `index.php` reads from `artists` table directly instead of the `categories` text field (falls back to text field if `program_artists` table is absent)
- Admin Programs list — "Categories" column header renamed to **"Artist / Group"**
- Admin Program form — "Categories" label renamed to **"Artist / Group"**; plain text input replaced with **tag-input widget**: artist chips with `×` remove, autocomplete dropdown from `artists` table (🎤 solo / 🎵 group icons), type-and-Enter/comma to add free-text name; new artists created in `artists` table on Save
- Admin Bulk Edit — "Categories" label renamed to **"Artist / Group"**; same tag-input chip widget with autocomplete applied (shared via `createArtistTagInput()` factory function)

### Fixed
- `createProgram()` and `updateProgram()` now call `syncProgramArtists()` — categories edited through Admin UI are reflected in the `program_artists` junction table immediately, so artist filter on the public event page works correctly after saving
- `syncProgramArtists()` auto-creates a new `artists` record (`is_group = 0`) when a category name has no direct name match or variant match, preventing manually typed artist names from being silently dropped

### Added (continued)
- **`artists_autocomplete` Admin API** (`?action=artists_autocomplete&q=...`) — lightweight GET endpoint returning `id`, `name`, `is_group` for matching artists (up to 20; returns top 50 when query is empty); used by the tag-input widget in the program form
- **`createArtistTagInput()` JS factory function** (`admin/index.php`) — shared factory that initializes the tag-input widget for both the single-program form and the Bulk Edit form (different element IDs, same logic); eliminates code duplication; exposes `setValue()` and `reset()` on the returned public API object

### Files Changed
- `artist.php` *(new)*
- `tools/migrate-add-artist-variants-table.php` *(new)*
- `admin/api.php`
- `admin/index.php`
- `index.php`
- `.htaccess`
- `styles/index.css`
- `setup.php`
- `tests/ProgramTypeTest.php`

### Upgrade Notes

> **ℹ️ Not a breaking change** — existing data and all functionality continue to work unchanged after deploying the new code. Fallback code detects whether the new tables exist and gracefully falls back to the `categories` text field if they don't.

**What works without migration** (out of the box):
- ✅ Programs list, Gantt, Calendar view — unchanged
- ✅ Artist filter — works from `categories` text field (fallback mode)
- ✅ ICS import — works (fallback: skips artist auto-linking if tables absent)
- ✅ All admin operations — unchanged

**What requires migration** (to enable new v3.0.0 features):
- ❌ Artist Profile page (`/artist/{id}`) — empty until `artists` table is populated
- ❌ Split badge pills (filter + ↗ profile link) — shows plain badge instead
- ❌ Event-count bubble on artist filter — hidden
- ❌ "Also appears in" cross-event section — not rendered
- ❌ Admin Artists tab Variants modal — variants column empty

**Migration steps** (run once after deploying):

```bash
cd tools

# 1. Create tables + import variant names from data/artists-mapping.json
php migrate-add-artist-variants-table.php

# 2. Link existing programs to artists via CATEGORIES field
php migrate-artists-from-mapping.php
```

After migration, all v3.0.0 features activate automatically.

---

## [2.10.2] - 2026-03-13

### Fixed

- **Calendar view day panel language not updating** (`js/common.js`) — when changing language while a day panel was open, the date header in the panel remained in the previous language; fixed by persisting `_calActiveDayKey`/`_calActiveDayEvs` state in `openDayPanel()`, clearing in `closeDayPanel()`, and re-rendering the panel inside `renderAndMountCalendar()` after each language switch
- **PHP 8.5 deprecation: `ReflectionProperty::setAccessible()`** (`tests/run-tests.php`) — removed two `setAccessible(true)` calls that are no-ops since PHP 8.1 and deprecated in PHP 8.5

### Changed

- **CI/CD: PHP 8.4 and 8.5 added to test matrix** (`.github/workflows/tests.yml`) — extended `php-version` matrix from `['8.1','8.2','8.3']` to `['8.1','8.2','8.3','8.4','8.5']`; also added missing migration scripts `migrate-add-stream-url-column.php` and `migrate-add-contact-channels-table.php` to both `test` and `integration-check` jobs

### Files Changed
- `js/common.js`
- `tests/run-tests.php`
- `.github/workflows/tests.yml`

## [2.10.1] - 2026-03-13

### Fixed
- **`contact.php` long URL overflow on mobile iOS** — added `word-break: break-all` and `overflow-wrap: anywhere` on contact channel `<a>` tags to prevent long URLs from exceeding layout width on narrow screens
- **`credits.php` global view event order** — changed `ksort` to `krsort` so event groups are sorted newest-first (descending by `event_id`); "ทั่วไป" (no event) group moved to last position

### Files Changed
- `contact.php`
- `credits.php`

## [2.10.0] - 2026-03-13

### Added
- **Contact Channels (DB-driven)** — contact channels moved from hardcoded HTML to SQLite `contact_channels` table; Admin › Contact tab (admin role only) with full CRUD (icon, title, description, url, display_order, is_active); table auto-created via `ensureContactChannelsTable()` on first API call — no manual migration required; `setup.php` `init_database` creates the table on fresh install
- **Disclaimer multilingual** — disclaimer text in 3 languages (TH/EN/JA) editable from Admin › Settings; stored in `cache/site-settings.json` (keys: `disclaimer_th`, `disclaimer_en`, `disclaimer_ja`); `get_site_disclaimer()` helper in `functions/helpers.php`; PHP-side translation patching via inline `<script>` injected between translations.js and common.js
- **Migration script** — `tools/migrate-add-contact-channels-table.php` (idempotent)

### Changed
- **`contact.php`** — contact channels rendered server-side from DB; empty state shown when no channels are configured; disclaimer loaded via `get_site_disclaimer()`; removed "ขอบคุณ" (Thank You) section
- **`js/translations.js`** — added `contact.noChannels` key (TH/EN/JA); removed `contact.section3.*` and `contact.social.*` keys
- **`admin/api.php`** — added actions: `disclaimer_get`, `disclaimer_save`, `contact_channels_list`, `contact_channels_get`, `contact_channels_create`, `contact_channels_update`, `contact_channels_delete`
- **`admin/index.php`** — added Contact tab (desktop + mobile dropdown); Disclaimer textareas in Settings section; channel modal; JS functions: `loadDisclaimerSetting()`, `saveDisclaimerSetting()`, `loadContactChannels()`, `renderContactChannels()`, `openChannelModal()`, `closeChannelModal()`, `submitChannelForm()`, `deleteChannel()`
- **`admin/help.php` + `admin/help-en.php`** — added documentation for Contact tab and Disclaimer settings

### Files Changed
- `contact.php`
- `js/translations.js`
- `functions/helpers.php`
- `admin/api.php`
- `admin/index.php`
- `admin/help.php`
- `admin/help-en.php`
- `setup.php`
- `tools/migrate-add-contact-channels-table.php`

## [2.9.0] - 2026-03-13

### Added

- ✨ **Nav icon buttons — Contact & How-to-use** — "ติดต่อเรา" and "วิธีการใช้งาน" links removed from `<nav>` text links and replaced with circular icon buttons in `.header-top-left`; envelope SVG for contact, open-book SVG for how-to-use; consistent order across all pages: home → [event-schedule if in event context] → contact → how-to-use → event-picker
- ✨ **Home icon always goes to root** — home icon on `credits.php`, `how-to-use.php`, and `contact.php` previously linked to `event_url('index.php')` (current event); changed to `get_base_path() . '/'` so it always navigates to the root listing page regardless of event context
- ✨ **New event-schedule icon** — calendar SVG icon added between home and contact; appears only when `$eventMeta` is set (viewing in context of a specific event); links back to that event's schedule via `event_url('index.php')`; tooltip translates via `nav.eventSchedule` (TH: ตารางงาน / EN: Event Schedule / JA: イベント)
- ✨ **Event Picker Modal on `credits.php`** — event-picker grid-dots button and full modal added to `credits.php` header (same condition: `MULTI_EVENT_MODE && count > 1`); modal cards link to `credits.php` of the target event instead of `index.php`; `$activeEvents` and `$today` loaded in PHP header; event picker CSS moved from `styles/index.css` → `styles/common.css` so it is available to all pages
- ✨ **`credits.php` event-specific banner** — when viewing credits for a specific event, a prominent glassmorphism banner displays the event name in the header (`font-size: 1.35em, font-weight: 800`, `backdrop-filter: blur(12px)`, white border); replaces the small `event-subtitle` pill
- ✨ **`credits.php` global view grouped by event** — when no event slug is given, credits are grouped into sections by `event_id`; each section header shows a calendar icon + event name as a clickable link to that event's schedule; credits belonging to inactive/deleted events are hidden; "ทั่วไป" section for `event_id IS NULL` credits (no link)

### Changed

- 🎨 **Credits menu renamed to "แหล่งข้อมูลอ้างอิง"** — `footer.credits` and `listing.credits` translation keys updated in all 3 languages (TH: แหล่งข้อมูลอ้างอิง / EN: References / JA: 参考資料); `credits.title` and `credits.list.title` keys updated to Thai for the TH locale; related section headings (`credits.announcements.title`, `credits.channels.title`, `credits.disclaimer.title`) translated to Thai
- 🎨 **`credits.php` page title translated to Thai** — hardcoded fallback text in `<h1>` and `<h2>` updated from "Credits & References" to "แหล่งข้อมูลอ้างอิง"

### Files Changed
- `index.php`
- `credits.php`
- `how-to-use.php`
- `contact.php`
- `js/translations.js`
- `styles/common.css`
- `styles/index.css`
- `styles/credits.css`

## [2.8.0] - 2026-03-13

### Added

- ✨ **Event Picker Modal** — replaces native `<select>` dropdown for switching events; button is a 38px circular grid-dots icon (top-left, same position as old version badge); modal shows all active events as cards with name, date range, and status badge (Ongoing / Upcoming / Past); currently-viewed event highlighted with a "Viewing" badge
- ✨ **Event Picker search + filter** — real-time search by event name (UTF-8/Thai safe via `data-name` lowercase attribute); status filter tabs (All / Ongoing / Upcoming / Past); both filters combine as AND; "no results" empty state; i18n TH/EN/JA including placeholder
- ✨ **Version moved to footer (all pages)** — app version removed from top-left header badge on all pages including the event listing homepage; now appears inline after "Powered by Stage Idol Calendar" in the footer as `vX.X.X` in monospace on all public pages (`index.php`, `contact.php`, `credits.php`, `how-to-use.php`); `footer-version` CSS class in `styles/common.css`

### Changed

- 🎨 **`index.php` title bar includes event name** — `<title>` renders `[Event Name] - [Site Name]` when viewing a specific event for better social sharing previews (from v2.7.6)
- 🎨 **Gantt bar layout — time + title inline** — time and title on same row (`flex`); type badge below; title truncates with `…` (from v2.7.7)
- 🎨 **Event Picker modal sort order** — events sorted: currently-viewing (top) → ongoing (start DESC) → upcoming (start ASC, nearest first) → past (start DESC); `usort()` in modal render loop; filter tabs still work independently after sort
- 🎨 **Event Picker mobile layout** — bottom-sheet modal (slides up, `border-radius: 16px 16px 0 0`); grid switches to flex list; each row uses CSS grid (2-col: name+date left, badges right) so status badge and "Viewing" badge never overlap or wrap; dates restored on mobile

### Files Changed
- `index.php`
- `js/common.js`
- `js/translations.js`
- `styles/index.css`
- `styles/common.css`
- `contact.php`
- `credits.php`
- `how-to-use.php`

## [2.7.7] - 2026-03-13

### Changed

- 🎨 **Gantt bar layout — time + title inline** — inside each program bar, time and title are now displayed on the same row (`display: flex; align-items: baseline`) instead of stacked vertically; type badge moves below the row; title truncates with `…` (`white-space: nowrap; text-overflow: ellipsis`) instead of 2-line clamp; makes short bars more readable at a glance

### Files Changed
- `js/common.js`
- `styles/index.css`

## [2.7.6] - 2026-03-13

### Added

- ✨ **Event name in page title** — `index.php` `<title>` now renders `[Event Name] - [Site Name]` when viewing a specific event (e.g. `Idol Stage Feb 2026 - Idol Stage Timetable`); improves social sharing previews and browser tab clarity; falls back to site name only on the event listing page or when event name equals site name

### Fixed

- 🐛 **`js/common.js` unused variable** — `const lang` in `openCalendarDetailModal()` was declared but never referenced; removed to eliminate lint warning

### Files Changed
- `index.php`
- `js/common.js`

## [2.7.5] - 2026-03-12

### Fixed

- 🐛 **`feed.php` SUMMARY comma truncation** — `icsEscape()` was escaping commas to `\,` in SUMMARY; some calendar clients (iOS, Outlook) misinterpret `\,` and truncate the event title at that position. Added `icsEscapeText()` for single-value TEXT properties (SUMMARY, LOCATION, DESCRIPTION) that leaves commas unescaped — commas are not value delimiters in these properties, so this is safe and matches Apple Calendar / Google Calendar export behaviour. `icsEscape()` (with comma escaping) is still used for individual CATEGORIES values where comma IS the RFC 5545 value delimiter.
- 🐛 **`feed.php` calendar header properties unescaped** — `X-WR-CALNAME`, `X-WR-CALDESC`, and `PRODID` were outputting `$calName`/`$siteTitle` without any escaping; backslash/semicolon/newline in the event name or site title would produce a malformed ICS header. `X-WR-CALNAME` now uses `icsEscape()` (comma escaped to `\,` to prevent calendar-name truncation at comma in clients); `X-WR-CALDESC` and `PRODID` use `icsEscapeText()` (comma left unescaped as it is plain text).
- 🧪 **FeedTest +11 tests** — `_feed_icsEscapeText()` replica + 7 unit tests + 2 SUMMARY source-check tests + 2 header-escaping source-check tests; total 1630 tests (80 in FeedTest)

### Files Changed
- `feed.php`
- `tests/FeedTest.php`

## [2.7.4] - 2026-03-12

### Fixed
- 🔒 **`credits.php` inactive event data leak** — same root cause as v2.7.3: inactive slug caused `$eventId = null`, exposing credits from all events; now returns 404 page
- 🔒 **`index.php` `$_SERVER["SCRIPT_NAME"]` JS injection** — replaced bare `echo` with `json_encode()` for `BASE_PATH` constant; prevents JS syntax error if server path contains quotes or backslashes
- 🔒 **`api/request.php` datetime validation strengthened** — replaced lenient `strtotime()` check with `checkdate()` + explicit range checks (`hour ≤ 23`, `minute ≤ 59`, `second ≤ 59`); rejects overflow values such as month 13, Feb 31, or hour 25 that `strtotime()` silently accepted by rolling over
- 🔒 **`functions/cache.php` concurrent write without lock** — `file_put_contents()` in `get_data_version()` and `get_cached_credits()` now uses `LOCK_EX` flag to prevent cache file corruption under concurrent requests
- 🔒 **`admin/api.php` restore without guaranteed auto-backup** — `copy()` return value for auto-backup was ignored in both `restoreBackup()` and `uploadAndRestoreBackup()`; restore now aborts with an error if the auto-backup copy fails (e.g. disk full or permission denied), preventing data loss
- 🔒 **`admin/api.php` ICS upload MIME over-permissive** — removed `application/octet-stream` from allowed MIME types (accepted any binary file); added structural validation that uploaded content contains `BEGIN:VCALENDAR` and `END:VCALENDAR` before parsing
- 🔒 **`admin/api.php` `stream_url` scheme not validated** — `createProgram()` and `updateProgram()` stored any value for `stream_url` including `javascript:` URIs; now validates with `preg_match('/^https?:\/\//i')` and stores `null` for non-http(s) values, preventing stored XSS via stream URL
- 🐛 **`feed.php` TOCTOU race condition on cache read** — `file_exists()` + `readfile()` had a window where the cache file could be deleted (by a concurrent `invalidate_feed_cache()`) between the two calls, causing a PHP warning and empty response; replaced with a single `@file_get_contents()` call that gracefully falls through to regenerate on race loss

### Files Changed
- `credits.php`
- `index.php`
- `api/request.php`
- `functions/cache.php`
- `admin/api.php`
- `feed.php`

## [2.7.3] - 2026-03-12

### Fixed
- 🔒 **Inactive event data leak** — when a specific event slug was requested but the event was inactive (or did not exist), `get_event_by_slug()` returned `null`, causing `$eventId` to be `null`; `IcsParser` then fetched programs from **all events** instead of returning nothing
  - `feed.php` — returns HTTP 404 instead of serving another event's ICS feed
  - `export.php` — returns HTTP 404 instead of exporting another event's ICS file
  - `api.php` — returns HTTP 404 with an empty JSON array instead of leaking programs from other events
  - `api/request.php` `getEvents()` — returns an empty program list instead of returning programs from all events
  - `index.php` — renders a 404 HTML page with a link back to the homepage instead of displaying programs from all events

### Tests
- 🧪 **3 new tests in `IntegrationTest`** — cover the inactive event scenarios that triggered the data leak; total 1587 tests across 12 suites
  - `testGetEventBySlugReturnsNullForInactiveEvent` — `get_event_by_slug()` must return `null` when `is_active = 0`
  - `testGetEventIdReturnsNullForInactiveEvent` — `get_event_id()` must return `null` when event is inactive
  - `testGetAllActiveEventsExcludesInactiveEvent` — `get_all_active_events()` must not include inactive events

### Files Changed
- `feed.php`
- `export.php`
- `api.php`
- `api/request.php`
- `index.php`
- `tests/IntegrationTest.php`

## [2.7.2] - 2026-03-12

### Changed
- 🔧 **Rename `$eventMetaId` → `$eventId` across codebase** — the old name was a leftover from the `events_meta` era (before the v1.2.9 table rename to `events`); all public pages, APIs, cache functions, and tests now use the consistent name `$eventId`
  - **Files updated:** `feed.php`, `export.php`, `credits.php`, `index.php`, `api.php`, `api/request.php`, `tools/import-ics-to-sqlite.php`, `functions/cache.php`, `tests/FeedTest.php`
  - No functional changes — rename only

### Files Changed
- `feed.php`
- `export.php`
- `credits.php`
- `index.php`
- `api.php`
- `api/request.php`
- `tools/import-ics-to-sqlite.php`
- `functions/cache.php`
- `tests/FeedTest.php`

## [2.7.1] - 2026-03-11

### Added
- ✨ **Duration display in calendar detail** — detail modal and day panel now show program duration `(Xh Ym)` next to the time range; computed via `formatDuration()` helper in `js/common.js`

### Fixed
- 🐛 **Calendar view right-edge gap** — replaced `border: 1px solid` on `.month-calendar` with `box-shadow: inset 0 0 0 1px`; physical border was consuming 1px of content area, leaving a visible sub-pixel gap between the rightmost grid column and the border in rows with dark backgrounds (DOW header, trailing empty cells)
- 🐛 **Cell divider pixel-rounding artifact** — changed `.cal-dow` and `.cal-day` from `border-right` to `border-left`; right-side borders can leave a residual strip at the grid's right edge due to pixel rounding across 7 columns; left-side borders eliminate this by anchoring dividers to the leading edge of each column

### Files Changed
- `js/common.js`
- `styles/common.css`

## [2.7.0] - 2026-03-11

### Added
- 📅 **Calendar View (`venue_mode = 'calendar'`)** — third venue type alongside multi/single, designed for stream/online event schedules
  - Monthly 7-column grid with ◀ ▶ navigation; navigation is restricted to months that have programs (buttons hidden when only one month exists)
  - **Desktop**: per-day program chips — platform icon (📷/𝕏/▶️/🔴) + artist name + time; tap chip → detail modal (header: program title; body: time + Join Live button)
  - **Mobile**: dot indicators per day (up to 3 dots + "+N"); tap day → day panel below grid showing full program list with title, categories, time, type badge, description, and Live button; grid fills full width with `minmax(46px, 1fr)` columns, scrolls on narrow screens
  - All colors use CSS variables — compatible with all 6 themes (Ocean/Forest/Midnight/Sunset/Gray/Dark)
  - XSS-safe: index-based chip registry (`window._calChipEvents`) + panel-specific registry (`window._calDpEvents`) — no JSON in HTML attributes
  - List/Timeline toggle hidden in calendar mode
  - Full i18n: month/day names re-render automatically on language change (TH/EN/JA)
  - Admin Events form: added `Calendar` option to Venue Mode dropdown
  - Updated user guide (`how-to-use.php`) and admin help (`admin/help.php`, `admin/help-en.php`) with Calendar View documentation

### Files Changed
- `index.php`
- `admin/api.php`
- `admin/index.php`
- `admin/help.php`
- `admin/help-en.php`
- `how-to-use.php`
- `js/common.js`
- `js/translations.js`
- `styles/common.css`

## [2.6.5] - 2026-03-10

### Security
- 🔒 **XSS fix in filter tag removal buttons** — `index.php` onclick handlers for artist/type/venue tag-remove buttons used `addslashes()` (database escaping, not JS-safe) combined with `htmlspecialchars()`; replaced with `json_encode()` + `htmlspecialchars(ENT_QUOTES)` which correctly encodes all special characters for inline JavaScript context
  - **Affected lines**: `index.php:343`, `index.php:370`, `index.php:399`
- 🔒 **Race condition fix in public request rate limiting** — `checkRateLimit()` and `recordRequest()` in `api/request.php` had a TOCTOU race: concurrent requests could all pass the limit check before any recorded, multiplying effective limit; fixed with `flock(LOCK_EX)` wrapping the full read→modify→write cycle
- 🔒 **JSON error handling in rate limit files** — `json_decode()` return value was checked with `!$data` (falsy), silently treating corrupted files as empty; replaced with explicit `json_last_error() !== JSON_ERROR_NONE` check in both `checkRateLimit()` and `recordRequest()`

### Files Changed
- `index.php`
- `api/request.php`

---

## [2.6.4] - 2026-03-09

### Fixed
- 🐛 **ICS Import preview edit bug** — `editPreviewEvent()` referenced `getElementById('eventTitle')` which does not exist in the DOM (actual ID is `title`), causing `TypeError: Cannot set properties of null` and breaking the ✏️ edit button on all preview rows
- 🐛 **ICS Import preview edit missing fields** — `programType` and `streamUrl` were not populated when opening the preview edit modal, so existing values were lost silently on save
- 🐛 **ICS Import preview edit saved to DB instead of preview** — `saveEvent()` never read `window.previewEditIndex`, so clicking Save in preview-edit mode would POST a new record to the database instead of updating the in-memory preview; fixed by adding an early-return block that updates `uploadedEvents[index]` and re-renders the preview table
- 🐛 **`previewEditIndex` state leak** — `closeModal()` now resets `window.previewEditIndex = null` to prevent preview-edit mode from persisting into subsequent normal add/edit modal opens

### Files Changed
- `admin/index.php`

---

## [2.6.3] - 2026-03-06

### Fixed
- 🗑️ **ORGANIZER parse error fix (Outlook calendar wipe)** — removed `ORGANIZER` property from VEVENT in `feed.php`; this is the root cause of all events disappearing from Outlook after every subscription pull
  - **Root cause**: `ORGANIZER;CN="event name":mailto:...` applied `icsEscape()` to the CN parameter value, converting `,` → `\,` and `;` → `\;`; RFC 5545 QUOTED-STRING parameters do not use backslash escaping — the literal backslash characters caused Outlook's strict ICS parser to reject the VEVENT and stop processing the entire file; Outlook then cleared all existing calendar entries and imported nothing
  - **Fix**: `ORGANIZER` property removed from VEVENTs entirely (optional in METHOD:PUBLISH feeds); event name is already conveyed via `X-WR-CALNAME` at the VCALENDAR level
  - **Affected endpoint**: `GET /feed` and `GET /event/{slug}/feed`
  - **Impact**: High — all subscribed Outlook calendars would empty on every sync cycle
- 🔄 **ICS feed Cache-Control hardened for CDN/proxy bypass** — `Cache-Control` upgraded from `no-cache` to `no-store, no-cache`; `Pragma: no-cache` added for legacy proxy compatibility
  - **Reason**: `no-cache` alone allows CDN proxies (e.g. Cloudflare) to store the response and serve 304 from their own cache, bypassing origin ETag checks; `no-store` instructs all intermediate proxies not to store the response at all
- 🔄 **ICS feed sync fix for iOS Calendar** — `Cache-Control` changed from `public, max-age=3600` to `no-store, no-cache` so iOS always re-validates with the server on every poll; ETag + 304 Not Modified is still used to avoid re-downloading unchanged content
  - **Root cause**: `max-age=3600` instructed iOS to serve the cached feed for up to 1 hour without contacting the server at all — no `If-None-Match` request was ever sent during that window, so data changes made in the admin panel were invisible to subscribed calendars until the cache expired
- 📅 **DTSTAMP stability fix** — `DTSTAMP` (and new `LAST-MODIFIED`) in each VEVENT is now sourced from the program's `updated_at` database column instead of the current request timestamp
  - **Root cause**: `DTSTAMP` was set to `gmdate('Ymd\THis\Z')` (current time) on every request, so every feed refresh presented all events as newly modified while `SEQUENCE:0` remained constant
  - **Fix**: `DTSTAMP` and `LAST-MODIFIED` now reflect the actual last-edit time of each event; they only change when an admin modifies the program record
  - **Files changed**: `feed.php`, `IcsParser.php` (added `updated_at` to SELECT queries)

### Files Changed
- `feed.php`
- `IcsParser.php`

---

## [2.6.2] - 2026-03-05

### Fixed
- 🔒 **Directory access hardening** — `.htaccess` files in `data/`, `cache/`, `backups/`, and `ics/` previously allowed access from local network ranges (`192.168.0.0/16`, `10.0.0.0/8`); now set to `Deny from all` to prevent direct web access to sensitive files (database, backups, cache) from any IP
  - **Vulnerability**: Users on the same LAN could download `backups/*.db` (full database including password hashes), read `cache/login_attempts.json`, `cache/site-settings.json`, and raw `.ics` files
  - **Fix**: Commented out all `Allow from` rules — effective policy is now `Deny from all` for all four directories
  - **Affected directories**: `data/`, `cache/`, `backups/`, `ics/`
  - **Impact**: Medium (requires attacker to be on the same local network), but could expose full database content
- 🔒 **Path disclosure fix in public API** — `api/request.php` previously leaked server file paths and PDO error details in JSON error responses visible to anyone
  - **Vulnerability**: DB-not-found error returned `'Database file not found: /var/www/...'`; connection failure returned full `PDOException::getMessage()` including internal paths; query failure returned raw SQL error text
  - **Fix**: All three error responses replaced with generic messages (`'Service unavailable'`, `'Failed to fetch programs'`) — internal details no longer exposed
  - **Affected endpoint**: `GET/POST /api/request.php`
  - **Impact**: Low (information disclosure only), but reveals server filesystem layout to unauthenticated users

### Files Changed
- `data/.htaccess`
- `cache/.htaccess`
- `backups/.htaccess`
- `ics/.htaccess`
- `api/request.php`

---

## [2.6.1] - 2026-03-05

### Fixed
- 🔒 **LIKE SQL Injection prevention** — admin search queries in `listPrograms()` and `listCredits()` now properly escape LIKE wildcard characters (`%`, `_`) before constructing the WHERE clause; added ESCAPE clause to LIKE operators so special characters are treated as literals, not wildcards
  - **Vulnerability**: User input like `a%` would be interpreted as `LIKE '%a%%'` (match anything starting with 'a'), allowing predictable query result manipulation
  - **Fix**: Input is escaped via `str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $search)` before being wrapped with wildcards; LIKE clause becomes `title LIKE :search ESCAPE '\\'` which treats escaped characters as literals
  - **Affected endpoints**: `GET /admin/api.php?action=programs_list` (programs search) and `GET /admin/api.php?action=credits_list` (credits search)
  - **Impact**: Low (admin-only, requires login + CSRF token), but essential for defense-in-depth

### Files Changed
- `admin/api.php`

---

## [2.6.0] - 2026-03-04

### Added
- 📅 **Date Jump Bar desktop scroll** — arrow buttons `◀` `▶` flanking the date buttons strip let mouse users scroll to dates outside the viewport; mousewheel over the strip now scrolls horizontally (`wheel` → `scrollLeft`); thin 3px sakura-tinted scrollbar shown on `@media (hover: hover)` devices only; `updateArrows()` hides the relevant arrow when the strip is already at its start/end
- 🔴 **Live Stream support (`stream_url`)** — new `stream_url TEXT DEFAULT NULL` column in `programs` table stores IG Live / X Spaces / YouTube Live links
  - **Public UI (`index.php`)**: rows with a stream URL get class `program-live` (subtle pink left glow); platform icon (📷 Instagram / 𝕏 X/Twitter / ▶️ YouTube / 🔴 other) + `🔴 เข้าร่วม` join button rendered inline in the title cell
  - **Admin UI (`admin/index.php`)**: `streamUrl` input (type=url) in the program form; list rows show `🔴` badge linked to the live URL; `openEditModal()` / `duplicateEvent()` pre-fill the field; `saveEvent()` includes `stream_url` in the payload
  - **Admin API (`admin/api.php`)**: `listPrograms`, `getProgram`, `createProgram`, `updateProgram`, and `confirmIcsImport` all read/write `stream_url`
  - **Public API (`api.php`)**: `stream_url` included in `$fieldsToEscape` → XSS-safe field in JSON response
  - **ICS Parser (`IcsParser.php`)**: parses RFC 5545 `URL:` property → `stream_url`; `getAllEventsFromDatabase()` SELECTs `stream_url`
  - **Export (`export.php`, `feed.php`)**: emits `URL:<stream_url>` VEVENT property when `stream_url` is not empty
  - **CSS (`styles/index.css`)**: `.program-live`, `.program-live-icon`, `.program-join-btn`, `.program-join-btn:hover`, `.stream-link-badge`
  - **Translations (`js/translations.js`)**: `badge.joinLive` key in TH (`🔴 เข้าร่วม`) / EN (`🔴 Join Live`) / JA (`🔴 参加する`)
  - **Migration (`tools/migrate-add-stream-url-column.php`)**: idempotent `ALTER TABLE` script for existing installs
  - **Setup (`setup.php`)**: `stream_url TEXT DEFAULT NULL` in `CREATE TABLE programs`; `fix_programs_title` migration preserves `stream_url`; `$allTablesOk` checks `$hasStreamUrlColumn`
  - **Tests (`tests/StreamUrlTest.php`)**: 31 new tests (schema, migration idempotency, CRUD, IcsParser URL parsing, admin/public API, export/feed URL: property, public/admin UI, CSS, translations, setup.php) — total **1584 tests** (12 suites)

### Files Changed
- `styles/index.css`
- `index.php`
- `admin/index.php`
- `admin/api.php`
- `api.php`
- `IcsParser.php`
- `export.php`
- `feed.php`
- `setup.php`
- `js/translations.js`
- `tests/run-tests.php`
- `tests/ProgramTypeTest.php`
- 🆕 `tools/migrate-add-stream-url-column.php`
- 🆕 `tests/StreamUrlTest.php`

---

## [2.5.4] - 2026-03-04

### Added
- 📖 **How-to-use expanded** — `how-to-use.php` updated to cover all current end-user features: Filter by Type (section 2 item 3, with renumbering), Subscribe to Calendar (🔔 section 3 item 3 with webcal/Google/Outlook steps), Date Jump Bar (new section), Description Modal/Read More (new section)
- 🌐 **Translation keys** — `js/translations.js` adds `section2.filter3.*`, `section3.subscribe.*`, `section9.*`, `section10.*` in all 3 languages (TH/EN/JA); renumbers `section2.action.title` → 4, `section2.selectedTags.title` → 5, `section2.quickFilter.title` → 6

### Files Changed
- `how-to-use.php`
- `js/translations.js`

---

## [2.5.3] - 2026-03-03

### Changed
- 🔧 **`GOOGLE_ANALYTICS_ID` moved to `config/analytics.php`** — extracted GA Measurement ID from `config/app.php` into a dedicated `config/analytics.php`; prevents `tools/update-version.php` from touching the site-specific GA ID when bumping the version; `config.php` bootstrap loads the new file automatically

### Files Changed
- 🆕 `config/analytics.php`
- `config/app.php`
- `config.php`

---

## [2.5.2] - 2026-03-03

### Added
- ⚡ **Feed static file cache** — `feed.php` now captures generated ICS output via `ob_start()`/`ob_get_clean()` and saves it to `cache/feed_{eventId}_{hash}.ics`; subsequent requests are served with `readfile()` — no SQLite query, no IcsParser instantiation, no event filtering on every hit
- 🔑 **Cache key includes sorted filter params** — `artist[]`, `venue[]`, `type[]` arrays are sorted before hashing so `?artist[]=A&artist[]=B` and `?artist[]=B&artist[]=A` always map to the same cache file
- 🗑️ **`invalidate_feed_cache($eventMetaId)`** — new function in `functions/cache.php`; deletes matching `feed_*.ics` files; when a specific event is invalidated, the global (`feed_0_*.ics`) cache is also cleared; `invalidate_all_caches()` updated to include feed ICS files
- 🔄 **Auto-invalidate on data change** — `admin/api.php` calls `invalidate_feed_cache()` immediately after `invalidate_data_version_cache()` at all 6 program write operations: `createProgram`, `updateProgram`, `deleteProgram`, `bulkDeletePrograms`, `bulkUpdatePrograms`, and `confirmIcsImport`
- ⚙️ **`FEED_CACHE_DIR` + `FEED_CACHE_TTL`** — new constants in `config/cache.php`; TTL default 3600 s (1 hour); directory is the existing `cache/` folder
- 🧪 **FeedTest** — 20 new cache tests added (total 69 / 1276 cumulative)

### Files Changed
- `feed.php`
- `functions/cache.php`
- `config/cache.php`
- `admin/api.php`
- 🆕 `tests/FeedTest.php` (+20 tests)

---

## [2.5.1] - 2026-03-03

### Fixed
- 🔧 **RFC 5545 line folding** — `feed.php` now folds any ICS property line exceeding 75 octets with CRLF + SPACE continuation; UTF-8 multi-byte character boundaries are respected (Thai characters 3 bytes/char are never split mid-sequence); required for strict RFC 5545 compliance and Outlook parsing
- 🏷️ **CATEGORIES comma delimiter fix** — `feed.php` previously escaped all commas (`\,`) in CATEGORIES via the shared escape function, causing Outlook to treat `Artist1\,Artist2` as a single category; fixed by splitting on `,` first, escaping each value individually (no comma escaping inside values), then rejoining with unescaped delimiter commas — Outlook now correctly shows N separate categories
- ✏️ **ICS text value escaping (`icsEscape()`)** — properly escapes backslash (first), semicolon, comma, and newline per RFC 5545 §3.3.11; used for SUMMARY, LOCATION, DESCRIPTION, ORGANIZER CN; CATEGORIES values go through the same function after being split, so delimiter commas in `implode(',', ...)` are never passed to the escaper
- 📧 **Outlook subscribe instructions** — subscribe modal now shows a dedicated Outlook instruction box (blue highlight) with step-by-step path: Outlook → Calendar → Add calendar → **Subscribe from web** → paste URL; clarifies that `webcal://` is for Apple/iOS/Thunderbird and `https://` is for Google Calendar / Outlook
- 📱 **Mobile action buttons compact layout** — filter buttons changed from `flex-direction: column` (6 full-width rows, ~338px) to `flex-wrap: wrap` with `flex: 1 1 calc(33.33% - 4px)` (3 per row = 2 rows, ~86px); scoped to `.filter-buttons .btn` so modal buttons are unaffected; reduced padding `8px 6px`, font `0.82em`, min-height `40px`
- 📱 **Subscribe modal URL input overflow** — flex container and input now have `min-width: 0` preventing flex overflow on narrow screens; input `font-size` raised to `1rem` (≥16px) to prevent iOS auto-zoom; `overflow: hidden; text-overflow: ellipsis` truncates long URLs instead of pushing the Copy button off-screen

### Files Changed
- `feed.php`
- `index.php`
- 🆕 `tests/FeedTest.php` (49 tests)

---

## [2.5.0] - 2026-03-03

### Added
- 🔔 **ICS Subscription Feed (`feed.php`)** — live calendar subscription endpoint; subscribe once and your calendar app auto-syncs whenever programs are added or changed; supports `webcal://` (Apple Calendar, iOS, Thunderbird) and `https://` (Google Calendar, Outlook)
  - URLs: `/feed` (all events) and `/event/{slug}/feed` (specific event) via existing `.htaccess` rules — no new rewrite rules needed
  - HTTP caching: `ETag` based on `get_data_version()`, `Cache-Control: public, max-age=3600`; calendar apps receive `304 Not Modified` when data is unchanged
  - Refresh hints: `X-PUBLISHED-TTL:PT1H` (Apple Calendar) + `REFRESH-INTERVAL;VALUE=DURATION:PT1H` (RFC 7986 / Google Calendar)
  - Filter parameters: `?artist[]=X&venue[]=Y&type[]=Z` — same as export.php
  - 15-minute `VALARM` reminder on every event (same as export.php)
- 🔔 **Subscribe button** — `🔔 Subscribe` button (`btn-subscribe`, purple gradient) added to filter action bar alongside Export; opens subscribe modal
- 🔔 **Subscribe modal** — shows webcal:// link (tap to open in Calendar App), https:// URL with Copy button, and Outlook-specific instructions; translations in TH/EN/JA
- 🗑️ **`invalidate_data_version_cache()`** — new function in `functions/cache.php`; deletes `cache/data_version*.json` for a specific event or all events; called by admin/api.php after every programs CRUD operation and ICS import so the feed ETag updates immediately without waiting for the 10-minute cache TTL

### Changed
- ⚡ **Admin programs CRUD triggers data version cache invalidation** — `createProgram()`, `updateProgram()`, `deleteProgram()`, `bulkDeletePrograms()`, `bulkUpdatePrograms()`, and `confirmIcsImport()` in `admin/api.php` all call `invalidate_data_version_cache()` on success; ensures subscribed calendar apps receive fresh data on their next poll after admin changes

### Files Changed
- 🆕 `feed.php`
- `index.php`
- `js/common.js`
- `js/translations.js`
- `functions/cache.php`
- `admin/api.php`
- `config/app.php`

---

## [2.4.7] - 2026-03-03

### Added
- 📍 **Venue display in single venue mode** — when `venue_mode = single`, a `📍 venue name` line appears below the event name subtitle in the page header; derived from the first entry in `$venues` (aggregated from programs); not shown in multi-venue mode

### Fixed
- 📱 **Event selector dropdown overflow on mobile** — `.program-selector select` now has `max-width: 100%` and `box-sizing: border-box`; on `≤768px` breakpoint `width: 100%; min-width: 0` overrides the desktop `min-width: 200px` so long event names no longer overflow the header

### Files Changed
- `index.php`

## [2.4.6] - 2026-03-03

### Changed
- 🃏 **Event listing: horizontal card layout** — main event listing page redesigned from vertical cards to horizontal-style cards; gradient header (name + date) spans full width at top, body section shows status badge + description + meta inline with "View Schedule" button on the right; mobile collapses to compact vertical card
- 📖 **Event description modal** — clicking/tapping a truncated description opens a modal with full event info (name, dates, status badge, full description, meta, link); "▼ Read more" chip button appears only when text is actually clamped (`scrollHeight > clientHeight`)
- 👆 **Read-more button: touch-friendly chip** — `▼ Read more` restyled from plain text link to pill-shaped chip with sakura background, border, `min-height: 30px`, and `-webkit-tap-highlight-color: transparent` for easier mobile tapping
- 📱 **Program table mobile: compact card redesign** — each program row now renders as a compact card with gradient time strip at top; reduced `tr` padding from 15px → 0 (cells handle own spacing), `td` padding from 8px 0 → 4px 12px; total vertical saving ~120px per card
- ✏️ **Edit button repositioned on mobile** — `program-action-cell` is `position: absolute; top: 33px; right: 8px` inside `position: relative` card; appears as 30×30px icon button in top-right of white body area without adding card height; fixed CSS specificity to override `width: 100% !important` using `.events-table tbody .program-action-cell`
- 🙈 **Empty cells hidden on mobile** — venue, type, and categories cells with no data receive `cell-empty` class and are `display: none !important`; removes all padding/space for empty fields
- ➖ **Removed `-` dash for empty data** — cells show nothing when data is absent (venue, type, categories, title fallback)
- ↔️ **Type + Categories on same line (mobile)** — `program-type-cell` and `program-categories-cell` changed to `display: inline-flex !important; width: auto !important` using higher-specificity selector to beat `td { width: 100% !important }`
- 🏷️ **Badge size unified** — `program-categories-badge` and `program-type-badge` share identical layout properties (`padding: 4px 12px`, `border-radius: 16px`, `font-size: 0.85em`, `margin: 2px 2px 2px 0`); only background/text color differs; mobile override reduces both equally (`padding: 3px 9px`, `font-size: 0.8em`)

### Files Changed
- `index.php`
- `styles/common.css`

---

## [2.4.5] - 2026-03-03

### Changed
- 🕐 **Collapse same-time display** — when a program's start time equals its end time (HH:MM), only the start time is shown (no `- end time`); applies to List view, Gantt tooltip, and Admin Programs list
- 📅 **Collapse same-date display** — when a convention's start date equals its end date, only the start date is shown on the event listing card (no `- end date`); Admin ICS import preview also collapses same-date and same-time ranges via new `formatDateTimeRange()` helper

### Files Changed
- `index.php`
- `admin/index.php`

---

## [2.4.4] - 2026-03-03

### Added
- 🔧 **`tools/update-version.php`** — automated version update script; updates `APP_VERSION` in `config/app.php` and 8 documentation files in a single command (`php tools/update-version.php X.Y.Z`); excludes `CHANGELOG.md` and `CLAUDE.md` which require manual content
- 📅 **ICS Export: 15-minute reminder** — every exported VEVENT now includes a `VALARM` component (`TRIGGER:-PT15M`, `ACTION:DISPLAY`) so Google Calendar, Apple Calendar, and other RFC 5545-compliant apps will show a notification 15 minutes before each program

### Fixed
- 🧪 **`IntegrationTest::testDocumentationExists`** — removed `QUICKSTART.md` and `SQLITE_MIGRATION.md` from the docs list after both files were deleted (merged into README.md and PROJECT-STRUCTURE.md in this version)

### Files Changed
- `export.php`
- `tests/IntegrationTest.php`
- 🆕 `tools/update-version.php`

### Documentation
- 🌐 **Full English translation** — translated `SETUP.md`, `CHANGELOG.md`, `API.md`, and `PROJECT-STRUCTURE.md` from Thai/mixed to English
- 📝 **README.md Features section updated** — added missing v2.4.x features: Program Types, Quick Filter Badges, Date Jump Bar, Per-Event Theme, Site Title Setting, Setup Wizard; corrected Venue Mode table (List/Timeline toggle is `Visible` in single-venue mode, per v2.3.4 change); updated unit test count to 999 across 10 suites
- 🔀 **Documentation consolidation** — merged `QUICKSTART.md` into `README.md` (expanded Quick Start + Core Features table + Pro Tips section); merged `SQLITE_MIGRATION.md` into `PROJECT-STRUCTURE.md` (Database Schema, Performance benchmarks, and Management sections); deleted both merged source files
- ➕ **`API.md` updates** — added `?type=X` public API filter and `programs_types` admin endpoint (both introduced in v2.4.0 but missing from docs); translated all remaining Thai text

---

## [2.4.3] - 2026-03-02

### Added
- 🧪 **ProgramTypeTest** — 35 automated tests covering all changes in v2.4.x
  - **Schema**: `programs.program_type` column exists and is nullable
  - **Migration**: `tools/migrate-add-program-type-column.php` exists and is idempotent
  - **CRUD**: insert/read/update/delete `program_type` values including NULL
  - **Public API**: `?type=` filter works via `$typeFilter` variable
  - **Admin API**: `programs_types` action, `SELECT DISTINCT program_type`, CREATE/UPDATE/bulk-update handle `program_type`
  - **index.php UI**: `appendFilter()` function, `URLSearchParams`, `$hasTypes` flag, `.event-subtitle`, `data-i18n="table.type"`, clickable badges, `htmlspecialchars(json_encode())` pattern
  - **Translations**: `'table.type'` key present in all 3 languages (Type / Type / タイプ), appearing 3 times
  - **Admin UI v2.4.2**: `sortBy('categories')`, no `sortBy('organizer')`, `event.categories`, no `<th>Organizer</th>`
- 📊 **Total tests: 999** (increased from 964 → 999, all passing across 10 suites)

### Fixed
- 🐛 **setup.php `fix_programs_title` — `program_type` column lost after fix** — this action recreates the `programs` table from `summary` → `title`, but the new `CREATE TABLE` was missing the `program_type` column, causing it to be immediately dropped
  - Added `program_type TEXT DEFAULT NULL` to the `CREATE TABLE` that is always recreated
  - Checks whether `programs_old` already has `program_type`: if yes → includes the column in `INSERT SELECT` to copy values; if no → omits the column from `INSERT` (default is NULL)

### Files Changed
- `setup.php`
- 🆕 `tests/ProgramTypeTest.php` (35 tests)

---

## [2.4.2] - 2026-03-02

### Changed
- 🗂️ **Admin Programs List: Organizer → Categories** — renamed the "Organizer" column in the Admin Programs table to "Categories" (related artists)
  - Programs list table: header `Organizer` → `Categories`, sort key `organizer` → `categories`, data `event.organizer` → `event.categories`
  - ICS import preview table: header changed from "Organizer" to "Related Artists", data `event.organizer` → `event.categories`
  - The Add/Edit Program form still retains the Organizer field for editing existing data

### Files Changed
- `admin/index.php`

---

## [2.4.1] - 2026-03-02

### Added
- 🖱️ **Clickable Filter Badges** — click any badge in the table to instantly append a filter, without using the filter fields at the top
  - **Related Artists**: categories are split into individual artist badges — click to append an `artist[]` filter
  - **Type**: type badge in the "Type" column — click to append a `type[]` filter
  - `appendFilter(type, value)` JS function: appends a filter to the URL (doesn't remove existing filters), works with or without pre-existing filters, won't add duplicates
- 📋 **Program Type Column** — separates "Type" into its own dedicated column instead of being embedded in the title cell
  - Column is shown when the event has at least 1 program with a defined `program_type` (`$hasTypes = !empty($types)`)
  - Supports 3 languages (`table.type`: Type / Type / タイプ)
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

### Files Changed
- `index.php`
- `js/translations.js`
- `how-to-use.php`

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

### Files Changed
- `index.php`
- `admin/index.php`
- `admin/api.php`
- `api.php`
- `export.php`
- `IcsParser.php`
- `setup.php`
- `config/app.php`
- `.github/workflows/tests.yml`
- 🆕 `tools/migrate-add-program-type-column.php`

## [2.3.4] - 2026-03-02

### Fixed
- 🗓️ **Gantt Chart not showing in Single Venue Mode** — the toggle switch was hidden by `if ($currentVenueMode === 'multi')`, causing the `#viewToggle` element to not exist in the DOM, and `initializeView()` to not run
  - Removed the `venue_mode === 'multi'` condition — toggle switch now shows in all modes
  - Gantt Chart works in single venue mode (displays 1 column)

### Changed
- ⬆️ **APP_VERSION** → `2.3.4` (cache busting)

### Files Changed
- `index.php`

## [2.3.3] - 2026-03-02

### Fixed
- 🗓️ **Gantt Chart: programs 4+ not displaying when overlap exceeds 3** — the CSS class `stack-h-N` was designed for only 2 or 3 overlaps, but JS assigned directly from `stackIndex + 1`, causing program 4 to receive `stack-h-4` (1/3 center) which overlapped invisibly with `stack-h-1` and `stack-h-2`
  - Fixed by switching from CSS classes to inline styles dynamically calculated from `stackIndex / stackTotal`
  - Column space is divided equally among all programs regardless of count (N=4 → 25% each, N=5 → 20% each, …)
  - Removed CSS classes `stack-h-1` through `stack-h-5` (no longer used)

### Changed
- ⬆️ **APP_VERSION** → `2.3.3` (cache busting)

### Files Changed
- `index.php`
- `styles/common.css`

## [2.3.2] - 2026-03-02

### Fixed
- 🕐 **Inconsistent timezone across the system** — no timezone was defined, causing PHP to use the server timezone (Linux/Docker = UTC), resulting in `export.php` converting times incorrectly by ±7 hours
  - Added `date_default_timezone_set('Asia/Bangkok')` in `config/app.php` before all constants
- 🕐 **IcsParser discarding Z suffix** — `DTSTART:20260207T100000Z` (UTC 10:00 = Thailand 17:00) was being stored as `10:00:00` instead of `17:00:00`
  - Fixed `IcsParser::parseDateTime()` to detect the Z suffix and convert UTC → Asia/Bangkok before storing to DB

### Changed
- ⬆️ **APP_VERSION** → `2.3.2` (cache busting)

### Files Changed
- `config/app.php`
- `IcsParser.php`

## [2.3.1] - 2026-03-02

### Fixed
- 🐛 **Bulk Edit Programs not saving to database** — `bulkUpdatePrograms()` in `admin/api.php` mixed named parameters (`:location`, `:updated_at`) with positional `?` in the same WHERE IN clause
  - PDO does not support mixing both types — `execute()` ran successfully but no rows were updated (silent fail)
  - Fixed to use only named parameters: each ID uses `:id_0`, `:id_1`, … instead of `?`

### Changed
- ⬆️ **APP_VERSION** → `2.3.1` (cache busting)

### Files Changed
- `admin/api.php`

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

### Files Changed
- `admin/index.php`
- `admin/api.php`
- `export.php`
- `setup.php`
- `config/app.php`
- 🆕 `tools/migrate-add-event-email-column.php`
- 🆕 `tests/EventEmailTest.php` (19 tests)

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

### Files Changed
- `setup.php`
- `admin/help.php`
- `admin/help-en.php`

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
  - Makes the subtitle descriptive like TH (`'Idol Stage Event Schedule'`) and JA (`'アイドルステージタイムテーブル'`)
  - The brand name remains only in `header.title`

### Files Changed
- `config/app.php`
- `functions/helpers.php`
- `admin/api.php`
- `admin/index.php`
- `js/translations.js`
- `index.php`
- `export.php`
- `credits.php`
- `how-to-use.php`
- `contact.php`
- 🆕 `tests/SiteSettingsTest.php`

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

### Files Changed
- `functions/helpers.php`
- `admin/api.php`
- `admin/index.php`
- `index.php`
- `credits.php`
- `how-to-use.php`
- `contact.php`
- `setup.php`
- 🆕 `tools/migrate-add-theme-column.php`

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

### Files Changed
- `styles/index.css` *(new)* — extracted from `index.php` inline styles
- `styles/credits.css` *(new)* — extracted from `credits.php` inline styles
- `styles/how-to-use.css` *(new)* — extracted from `how-to-use.php` inline styles
- `styles/ocean.css` *(new)*, `styles/forest.css` *(new)*, `styles/midnight.css` *(new)*, `styles/sunset.css` *(new)*, `styles/dark.css` *(new)*, `styles/gray.css` *(new)* — theme CSS files
- `functions/helpers.php` — `get_site_theme()` helper
- `admin/api.php` — `theme_get` / `theme_save` API actions
- `admin/index.php` — Settings tab with theme picker
- `admin/help.php` — Settings section documentation (Thai)
- `admin/help-en.php` — Settings section documentation (English)
- `index.php`, `credits.php`, `how-to-use.php`, `contact.php` — load theme CSS server-side; inline styles moved to external files

## [2.0.1] - 2026-02-27

### Changed
- ⚙️ **Google Analytics ID configurable** — moved the Measurement ID from being hardcoded in each PHP file to a setting in `config/app.php`
  - Added constant `GOOGLE_ANALYTICS_ID` — set to `''` to disable Analytics
  - Updated `index.php`, `how-to-use.php`, `contact.php`, `credits.php` to use the constant instead of hardcoded values

### Files Changed
- `config/app.php` — `GOOGLE_ANALYTICS_ID` constant
- `index.php`, `how-to-use.php`, `contact.php`, `credits.php` — use constant instead of hardcoded GA ID

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

### Files Changed
- `setup.php` *(new)* — Setup Wizard (5 steps: requirements, directories, database, import, security)
- `admin/help.php` *(new)* — Admin panel user guide (Thai)
- `admin/help-en.php` *(new)* — Admin panel user guide (English)
- `tools/migrate-rename-tables-columns.php` *(new)* — DB schema rename migration
- `tools/migrate-add-indexes.php` *(new)* — DB performance indexes
- `functions/helpers.php` — `get_db()` singleton, renamed helper functions
- `functions/admin.php` — updated for renamed tables
- `admin/api.php` — all renamed functions, new endpoints, mobile responsive
- `admin/index.php` — renamed tabs, mobile responsive layout
- `admin/login.php` — login rate limiting
- `index.php` — pre-computed timestamps, CSS class renames
- `api.php` — ETag + Cache-Control headers
- `js/translations.js` — renamed translation keys
- `config/app.php` — version bump

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

### Files Changed
- `tools/migrate-add-role-column.php` *(new)* — adds `role` column to `admin_users` table
- `tests/UserManagementTest.php` *(new)* — 19 automated tests
- `functions/admin.php` — `$_SESSION['admin_role']` + 4 role helper functions
- `admin/api.php` — admin-only action gate + 5 user CRUD endpoints
- `admin/index.php` — Users tab/modal, hide from agent role
- `config/app.php` — version bump

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

### Files Changed
- `tools/migrate-add-admin-users-table.php` *(new)* — creates `admin_users` table
- `functions/admin.php` — DB auth functions, `admin_login()` reads from DB first
- `config/admin.php` — config credentials become fallback only
- `admin/api.php` — `change_password` endpoint, backup delete → POST
- `admin/index.php` — Change Password button + modal in header
- `tools/generate-password-hash.php` — updated recommendations

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

### Files Changed
- `config/database.php` — `DB_PATH` constant pointing to `data/calendar.db`
- `admin/api.php` — uses `DB_PATH`, backup dir → `backups/`
- `admin/index.php` — Backup tab with full backup/restore UI
- `functions/cache.php` — `invalidate_all_caches()` function
- `docker-compose.yml` — volume mount updated to `data/`
- `tools/` — updated all migration tools to use new paths
- `tests/` — updated tests to use new DB path

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

### Files Changed
- `.htaccess` — Apache rewrite rules for clean URLs and event path routing
- `nginx-clean-url.conf` *(new)* — Nginx configuration example
- `functions/helpers.php` — `event_url()` generates clean URLs
- `admin/api.php` — credits per-event support
- `admin/index.php` — credits convention selector, date jump bar UI
- `index.php` — date jump bar, clean URL generation
- `js/common.js` — `exportToIcs()` uses clean URL paths
- `js/translations.js` — 20 new request modal keys

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

### Files Changed
- `tools/migrate-add-events-meta-table.php` *(new)* — creates `events_meta` table
- `config/app.php` — `MULTI_EVENT_MODE`, `DEFAULT_EVENT_SLUG` constants
- `functions/helpers.php` — 6 new multi-event helper functions
- `admin/api.php` — 5 new `event_meta_*` endpoints
- `admin/index.php` — Conventions tab with CRUD
- `index.php` — convention selector, per-convention filtering
- `export.php`, `credits.php`, `api.php`, `api/request.php` — convention scoping
- `IcsParser.php` — `--event=slug` argument support
- `tools/import-ics-to-sqlite.php` — `--event=slug` CLI argument
- `tests/IntegrationTest.php` — 15 new multi-event tests

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

### Files Changed
- `Dockerfile` *(new)*, `docker-compose.yml` *(new)*, `docker-compose.dev.yml` *(new)*, `.dockerignore` *(new)*, `DOCKER.md` *(new)* — Docker support
- `tools/migrate-add-credits-table.php` *(new)* — creates `credits` table
- `tests/run-tests.php` *(new)*, `tests/TestRunner.php` *(new)*, `tests/SecurityTest.php` *(new)*, `tests/CacheTest.php` *(new)*, `tests/AdminAuthTest.php` *(new)*, `tests/CreditsApiTest.php` *(new)*, `tests/IntegrationTest.php` *(new)* — automated test suite
- `quick-test.sh` *(new)*, `quick-test.bat` *(new)* — pre-commit test scripts
- `.github/workflows/tests.yml` *(new)* — CI/CD GitHub Actions
- `functions/security.php` — `sanitize_string()`, `sanitize_string_array()`, `get_sanitized_param()`
- `functions/admin.php` — session security rewrite (timing attack prevention, session fixation, timeout)
- `functions/cache.php` — `get_cached_credits()`, `invalidate_credits_cache()`
- `config/cache.php` — `CREDITS_CACHE_FILE`, `CREDITS_CACHE_TTL` constants
- `config/admin.php` — `SESSION_TIMEOUT` constant
- `admin/api.php` — 6 credits CRUD endpoints, bulk operations, CSRF protection
- `admin/index.php` — Credits tab, bulk delete/edit, ICS upload, per-page selector
- `credits.php` — DB-driven from `get_cached_credits()`
- `index.php`, `export.php` — input sanitization

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

### Files Changed
- `index.php` — main calendar page (initial version)
- `how-to-use.php` — user guide page
- `contact.php` — contact page
- `credits.php` — credits/references page
- `export.php` — ICS export endpoint
- `api.php` — public API endpoint
- `config.php` — bootstrap file
- `IcsParser.php` — ICS parser class
- `config/app.php`, `config/admin.php`, `config/security.php`, `config/database.php`, `config/cache.php` *(new)* — modular config files
- `functions/helpers.php`, `functions/cache.php`, `functions/admin.php`, `functions/security.php` *(new)* — helper function modules
- `admin/index.php`, `admin/api.php`, `admin/login.php` — admin interface
- `api/request.php` — user request API
- `styles/common.css` — shared Sakura theme styles
- `js/translations.js` — 3-language translations
- `js/common.js` — shared JS utilities
- `.htaccess` — rewrite rules
- `tools/import-ics-to-sqlite.php`, `tools/update-ics-categories.php`, `tools/migrate-add-requests-table.php`, `tools/migrate-add-credits-table.php` — development tools
