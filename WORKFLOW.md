# 🔄 Workflow Guide

**Idol Stage Timetable** — Step-by-step workflows for common development, operational, and deployment tasks.

Stack: **PHP 8.1+ / SQLite (PDO) / Apache / Vanilla JS**
Current version: **v15.8.0** | DB: `data/calendar.db` | Tests: `php tests/run-tests.php` (25 suites, 985 cumulative)

---

## 🚀 Initial Setup & Installation

### Step 1: Clone & Explore Repository

```bash
# Clone the project
git clone <repo-url>
cd stage-idol-calendar

# Key files to read first
cat README.md         # Overview
cat INSTALLATION.md   # Detailed setup guide
cat CLAUDE.md         # Project specs & full CHANGELOG
cat SKILL.md          # AI-oriented architecture cheat sheet
cat WORKFLOW.md       # This file!
```

### Step 2: Choose Installation Method

#### Option A: Docker (Recommended) ⭐
```bash
# 1. Start containers
docker-compose up -d

# 2. Check health
docker-compose logs -f          # Watch startup
docker ps                        # Verify running

# 3. Access web
# Open http://localhost:8000 in browser

# 4. Run tests (dev)
docker exec idol-stage-calendar php tests/run-tests.php

# 5. Stop later
docker-compose down
```

#### Option B: PHP Built-in Server (Local Dev)
```bash
# 1. Check PHP version (must be 8.1+)
php --version

# 2. Start server
php -S localhost:8000

# 3. Access web
# Open http://localhost:8000 in browser
```

#### Option C: Apache/Nginx (Production)
See [INSTALLATION.md](INSTALLATION.md) for VirtualHost setup, .htaccess configuration, document root settings.
Also see [DOCKER.md](DOCKER.md) for production Docker guide.

### Step 3: Initialize Database

**Option 1: Setup Wizard UI** (Recommended)
```
Browser: http://localhost:8000/setup.php
Follow 6 steps:
1. System Requirements ✓
2. Directories ✓
3. Database (Initialize)
4. Import ICS (optional)
5. Admin Account (set password)
6. Production Cleanup & Lock
```

**Option 2: Manual Import**
```bash
cd tools
php import-ics-to-sqlite.php    # Parse ICS files from ics/ folder
```

### Step 4: Run Migrations (Existing DB)

```bash
cd tools

# Core migrations (run in order if upgrading from older version)
php migrate-rename-tables-columns.php        # v1.2.9 schema rename
php migrate-add-indexes.php                  # Performance indexes
php migrate-add-admin-users-table.php        # DB-based auth
php migrate-add-role-column.php              # RBAC roles
php migrate-add-event-email-column.php       # events.email
php migrate-add-program-type-column.php      # programs.program_type
php migrate-add-stream-url-column.php        # programs.stream_url
php migrate-add-theme-column.php             # events.theme
php migrate-add-contact-channels-table.php   # contact_channels table
php migrate-add-timezone-column.php          # events.timezone
php migrate-add-artist-variants-table.php    # artist_variants table
php migrate-add-artist-pictures-column.php   # artists display/cover picture
php migrate-add-event-pictures-table.php     # event_pictures table
php migrate-add-header-cover-image-column.php # events.header_cover_image
php migrate-add-ticket-url-column.php        # events.ticket_url
php migrate-add-artist-social-columns.php    # artists social links
php migrate-add-fts5.php                     # FTS5 full-text search
php migrate-add-event-requests-table.php     # event_requests table
php migrate-add-artist-requests-table.php    # artist_requests table
```

All migration scripts are **idempotent** — safe to run multiple times.

### Step 5: Verify Installation

```bash
# Run full test suite (must all pass)
php tests/run-tests.php

# Expected output:
# ✅ ALL TESTS PASSED
# Total: run all suites; current suite count is 23
# Pass Rate: 100.0%
```

---

## 💻 Development Workflow

### Daily Development Routine

#### 1️⃣ Start Your Session

```bash
# Update from main
git fetch origin
git pull origin master

# Create feature branch
git checkout -b feature/my-feature
# Or bug fix:
git checkout -b bugfix/issue-name

# Start server
php -S localhost:8000
# Or Docker:
docker-compose up -d
```

#### 2️⃣ Make Changes

Files typically affected per feature type:

| Change Type | Files to Edit |
|-------------|--------------|
| New DB column | `tools/migrate-add-*.php`, `setup.php`, `admin/api.php`, `admin/index.php`, public page |
| New admin action | `admin/api.php` (switch block), `admin/index.php` (JS + UI) |
| New public feature | `index.php` / relevant page, `styles/index.css`, `js/common.js` |
| New i18n text | `js/translations.js` (TH/EN/JA) and/or `admin/js/admin-i18n.js` (TH/EN) |
| New test suite | `tests/NewFeatureTest.php`, `tests/run-tests.php` (register), `CLAUDE.md` (count) |

#### 3️⃣ Test Locally

**Automated Tests**:
```bash
# Run all tests
php tests/run-tests.php

# Run specific suite
php tests/run-tests.php FavoritesTest

# Run single test
php tests/run-tests.php SecurityTest::testSanitizeString
```

**Test Suites** (24 suites):

| Suite | Tests | Coverage |
|-------|-------|----------|
| SecurityTest | 7 | XSS, SQL injection, input validation |
| CacheTest | 17 | Cache TTL, invalidation, concurrency |
| AdminAuthTest | 38 | Login, sessions, timing attacks |
| CreditsApiTest | 49 | CRUD operations, bulk actions |
| IntegrationTest | 100 | Workflows, API endpoints, multi-event |
| UserManagementTest | 119 | Roles, permissions, user CRUD |
| ThemeTest | 143 | Theme system, CSS, admin API |
| SiteSettingsTest | 157 | Site title, settings, cache |
| EventEmailTest | 176 | Event email field, ORGANIZER, ICS |
| ProgramTypeTest | 211 | Program type system, filtering |
| FeedTest | 291 | RFC 5545 ICS, escaping, caching |
| StreamUrlTest | 322 | Stream URL, platform icons, ICS |
| FavoritesTest | 406 | UUID v7, HMAC, favorites system |
| TimezoneTest | 487 | Per-event timezone, UTC conversion |
| TelegramTest | 54 | Bot commands, notification helpers |
| EmailNotificationTest | 13 | Email notification config, request hooks, admin links, empty states |
| TwoFactorAuthTest | 9 | TOTP 2FA, backup codes, schema flag, admin UI/API |
| SeoTest | 63 | Meta tags, JSON-LD, canonical URLs |
| ArtistPictureTest | 61 | Upload, crop, admin API |
| EventPicturesTest | 57 | Gallery, lightbox, reorder |
| Fts5Test | 45 | FTS5 search, fallback, triggers |
| WebPushTest | 45 | VAPID crypto, push encrypt, manifest, service-worker, PWA icons |

**Manual Testing** (Browser):
```
1. http://localhost:8000              # Public schedule
2. http://localhost:8000/artists      # Artist portal
3. http://localhost:8000/admin        # Admin panel
4. http://localhost:8000/setup.php    # Setup wizard
5. Test filter/search (FTS5 + checkbox)
6. Test export (ICS, image PNG)
7. Test language switch (TH/EN/JA)
8. Check DevTools console (F12) — no JS errors
```

**Browser DevTools**:
- **Console**: Check for JS errors (red icons)
- **Network**: Verify API calls succeed (200 status)
- **Application**: Check localStorage for preferences (`lang`, `fav_slug`, `admin_lang`, `admin_recent_events`)

#### 4️⃣ Verify Database Integrity

```bash
sqlite3 data/calendar.db

.schema programs           # programs table structure
.schema artists            # artists + social columns
.schema events             # events + timezone/theme/ticket_url
SELECT COUNT(*) FROM programs;
SELECT COUNT(*) FROM program_artists;
SELECT COUNT(*) FROM artist_variants;
.quit
```

#### 5️⃣ Commit Changes

```bash
git status
git diff admin/index.php   # Review specific file

# Stage specific files (avoid git add . — risk of committing .env or DB)
git add admin/index.php admin/api.php js/translations.js

git commit -m "feat(admin): add social links to artist modal

- Facebook, Instagram, Twitter/X, TikTok link fields
- sanitize_social_url() validates http(s):// only
- portal_social_svg() renders inline SVG icons

Closes #789"
```

**Commit Message Format**:
```
<type>(<scope>): <subject>

<body>
```
Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`
Scopes: `admin`, `api`, `styles`, `js`, `db`, `tests`, `cache`

#### 6️⃣ Push & Create PR

```bash
git push origin feature/my-feature

gh pr create \
  --title "Add artist social links" \
  --body "Fixes #789. See description above."
```

---

## 🎯 Feature Development Process

### Complete Example: Add New Column to Events Table

#### Phase 1: Migration Script

```bash
# Create idempotent migration
cat > tools/migrate-add-myfield-column.php << 'EOF'
<?php
$db = new PDO('sqlite:' . __DIR__ . '/../data/calendar.db');
$cols = $db->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_ASSOC);
$has = in_array('myfield', array_column($cols, 'name'));
if (!$has) {
    $db->exec("ALTER TABLE events ADD COLUMN myfield TEXT DEFAULT NULL");
    echo "Added myfield column\n";
} else {
    echo "myfield column already exists\n";
}
EOF

php tools/migrate-add-myfield-column.php
```

#### Phase 2: Update `setup.php`

Add to 4 locations in `setup.php`:
1. `CREATE TABLE events (... myfield TEXT DEFAULT NULL ...)` in `init_database`
2. `ALTER TABLE` fallback block in `init_database` (for existing DB)
3. `$allTablesOk` condition: `&& $hasMyfield`
4. `$migrationChecks` array entry

#### Phase 3: Admin API (`admin/api.php`)

```php
// In getEvent():
'myfield' => $row['myfield'],

// In createEvent() / updateEvent():
$myfield = sanitize_string($_POST['myfield'] ?? '');
// ... validate ...
// In INSERT/UPDATE:
':myfield' => $myfield ?: null,

// In escapeOutputData():
// Automatically escaped by the function
```

#### Phase 4: Admin UI (`admin/index.php`)

```html
<!-- Form field -->
<label data-i18n="event.myfieldLabel"></label>
<input type="text" id="conventionMyfield" data-i18n-placeholder="event.myfieldPlaceholder">
```

```javascript
// In openEditModal():
document.getElementById('conventionMyfield').value = decodeHtml(meta.myfield || '');

// In saveEvent() POST body:
myfield: document.getElementById('conventionMyfield').value,
```

#### Phase 5: i18n Keys

```javascript
// admin/js/admin-i18n.js — TH + EN required
'event.myfieldLabel': { th: 'ฟิลด์ใหม่', en: 'New Field' },

// js/translations.js — TH + EN + JA required (if shown on public pages)
myfieldKey: { th: '...', en: '...', ja: '...' }
```

#### Phase 6: Cache Invalidation

```php
// After write in admin/api.php:
invalidate_query_cache($eventId);
invalidate_data_version_cache();
invalidate_feed_cache($eventId);
invalidate_sitemap_cache();
```

#### Phase 7: Tests

```bash
# Create tests/MyfieldTest.php
# Add to tests/run-tests.php:
$runner->addSuite('MyfieldTest', __DIR__ . '/MyfieldTest.php');

php tests/run-tests.php MyfieldTest    # New suite only
php tests/run-tests.php               # Full suite — all must pass
```

---

## 🐛 Bug Fix Workflow

### Common Bug Patterns & Fixes

#### "Export/Feed artist filter mismatch"

```php
// ❌ Wrong (text-only):
$artists = explode(',', $program['categories']);

// ✅ Correct (mirror index.php logic):
if ($useArtistsTable) {
    // Use program_artists junction table
} else {
    // Fallback to categories text
}
```

#### "Admin form shows &#039; instead of '"

```javascript
// ❌ Wrong:
document.getElementById('myInput').value = data.name;

// ✅ Correct (decode server htmlspecialchars):
document.getElementById('myInput').value = decodeHtml(data.name);
```

#### "Database is locked" on Windows (tests)

```php
// ❌ Wrong (static PDO held across test runs):
static $db = null;
if (!$db) $db = new PDO(...);

// ✅ Correct (per-call, released on return):
function get_cached_credits(...) {
    $db = new PDO('sqlite:' . DB_PATH);
    // ... query ...
    // $db released automatically when function returns
}

// In tests — always close stmt BEFORE db:
$stmt->closeCursor();
$stmt = null;
$db = null;     // Now safe to release
```

#### "Cache/Favorites empty on subdirectory install"

```html
<!-- Must define BASE_PATH BEFORE loading common.js -->
<script>
const BASE_PATH = <?php echo json_encode(get_base_path()); ?>;
</script>
<script src="js/common.js?v=<?= APP_VERSION ?>"></script>
```

#### "Admin login rate limit stuck"

```bash
rm cache/login_attempts.json   # Clear immediately
# Or wait 15 minutes for auto-reset
```

#### "Telegram notifications wrong time"

```php
// ❌ Wrong (CAST to Unix assumes UTC, but p.start is Bangkok time):
CAST(strftime('%s', p.start) AS INTEGER)

// ✅ Correct (compare as datetime strings):
datetime(p.start) BETWEEN datetime(:windowStart) AND datetime(:windowEnd)
```

### Bug Fix Steps

```bash
# 1. Reproduce bug
# 2. Create branch
git checkout -b bugfix/description

# 3. Fix code

# 4. Test fix + run full suite
php tests/run-tests.php

# 5. Write regression test

# 6. Commit
git commit -m "fix(scope): short description

Root cause: ...
Fix: ...
Fixes #NNN"

git push origin bugfix/description
```

---

## 📦 Release & Deployment Workflow

### Version Release Process

#### Step 1: Prepare

```bash
git checkout master
git pull origin master
grep "APP_VERSION" config/app.php   # Check current version
git checkout -b release/v12.0.0
```

#### Step 2: Bump Version (Auto-updates 9 files)

```bash
cd tools && php update-version.php 12.0.0
```

Auto-updated files: `config/app.php`, `README.md`, `SETUP.md`, `API.md`, `PROJECT-STRUCTURE.md`, `INSTALLATION.md`, `TESTING.md`, `SECURITY.md`; `ICS_FORMAT.md` is updated only when it contains the previous version string.

#### Step 2b: Sync Service Worker Version (PWA cache invalidation, v15.7.0+)

```bash
cd ..   # back to project root
php sync-sw-version.php
```

Idempotent CLI-only script that reads `APP_VERSION` from `config/app.php` and updates `CACHE_VERSION` in `service-worker.js`. Required after every version bump so existing PWA users get fresh caches on next revalidation. The script is intentionally kept separate from `tools/update-version.php` to keep version-bump concerns (docs/config) distinct from runtime cache invalidation (SW).

#### Step 3: Manual Documentation Updates

**CHANGELOG.md** — Add new section:
```markdown
### v12.0.0 (2026-XX-XX)

- ✨ **Feature Name** — description; sub-bullets for details
- 🐛 **Bug Fix** — what was wrong; what was fixed

**Files changed:**
- `path/to/file.php` — what changed
```

**CLAUDE.md** — Add same section to `## 📝 Changelog`

**SKILL.md** — Update version number in Architecture block if needed

#### Step 4: Run Full Tests

```bash
php tests/run-tests.php
# ALL tests must pass before release
```

#### Step 5: Commit & Tag

```bash
git add config/app.php CHANGELOG.md CLAUDE.md SKILL.md README.md
# (+ other auto-updated files)

git commit -m "chore: release v12.0.0"
git tag -a v12.0.0 -m "Release v12.0.0"
```

#### Step 6: Merge & Push

```bash
git checkout master
git merge release/v12.0.0
git push origin master
git push origin v12.0.0
git branch -d release/v12.0.0
```

#### Step 7: Deploy

**Docker**:
```bash
ssh user@production-server
cd /var/www/idol-stage-calendar
git fetch origin && git checkout v12.0.0
docker-compose build
docker-compose down && docker-compose up -d
docker-compose logs -f    # Verify healthy
```

**Traditional Server**:
```bash
ssh user@production-server
cp /var/www/html/data/calendar.db /backups/calendar.db.$(date +%Y%m%d)
cd /var/www/html
git fetch origin && git checkout v12.0.0
rm -rf cache/*.json cache/*.ics cache/images/*.png
sudo systemctl restart php-fpm
```

---

## 🔒 Security Rules (ห้ามละเว้น)

1. **SQL**: PDO prepared statements เสมอ — ห้าม string concatenation ใน SQL
2. **XSS**: `htmlspecialchars()` ทุก user input ที่ echo ออก HTML; `sanitize_string()` สำหรับ API input
3. **CSRF**: ทุก POST/PUT/DELETE ต้องตรวจ CSRF token; admin API ใช้ `X-CSRF-Token` header
4. **stream_url / ticket_url**: `preg_match('/^https?:\/\//i')` — ค่าอื่นเก็บเป็น `null`
5. **social_url**: ใช้ `sanitize_social_url()` ใน `admin/api.php`
6. **Role check**: admin-only actions ต้องเรียก `require_api_admin_role()` — **ไม่ใช่** `requireAdminRole()` (ไม่มี)
7. **JSON in HTML**: ใช้ `json_encode(..., JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)`
8. **File upload**: validate MIME type + `getimagesize()` + max 5MB + store outside webroot หรือมี `.htaccess` block PHP

---

## ⚡ Performance Optimization

### Database Indexes

```bash
sqlite3 data/calendar.db

# Check query plan
EXPLAIN QUERY PLAN
SELECT * FROM programs WHERE event_id = 1 ORDER BY start ASC;

# Look for: "USING INDEX idx_programs_event_id" (good)
# Avoid: "FULL SCAN TABLE programs" (bad → add index)
```

```php
// Add index in migration:
$db->exec("CREATE INDEX IF NOT EXISTS idx_programs_event_start
  ON programs(event_id, start)");
```

### Cache Check Pattern

```php
// Always check query cache first
$cached = get_query_cache("query_event_{$eventId}.json");
if ($cached) {
    // use cached data
} else {
    // query DB
    save_query_cache("query_event_{$eventId}.json", $data);
}
```

### Cache Invalidation (after every write)

```php
invalidate_query_cache($eventId);         // query_event_*.json
invalidate_artist_query_cache();           // query_artist_*.json + query_portal.json
invalidate_data_version_cache();           // data_version*.json (ETag)
invalidate_feed_cache($eventId);           // feed_*.ics
invalidate_sitemap_cache();                // sitemap.xml
```

### Cache Files Reference

| File | TTL | Purpose |
|------|-----|---------|
| `cache/data_version*.json` | 10 min | ETag for ICS feeds |
| `cache/credits.json` | 1 hr | Credits per event |
| `cache/feed_*.ics` | 1 hr | Static ICS feed |
| `cache/query_event_{id}.json` | 1 hr | Full event query result |
| `cache/query_artist_{id}.json` | 1 hr | Artist profile data |
| `cache/query_listing.json` | 1 hr | Homepage listing data |
| `cache/query_portal.json` | 1 hr | Artists portal data |
| `cache/images/img_*.png` | 1 hr | PNG image export |
| `cache/site-settings.json` | persistent | Site title / disclaimer |
| `cache/sitemap.xml` | 1 hr | XML sitemap |
| `cache/favorites/{shard}/*.json` | 365 days | User favorites |

---

## 🌐 URL Routes Reference

```
/                          → index.php (listing)
/event/{slug}              → index.php?event={slug}
/artist/{id}               → artist.php?id={id}
/artist/{id}/feed          → feed.php?artist_id={id}
/artists                   → artists.php
/my/{slug}                 → my.php?slug={slug}
/my-favorites/{slug}       → my-favorites.php?slug={slug}
/my/{slug}/feed            → my-feed.php?slug={slug}
/sitemap.xml               → sitemap.php
/robots.txt                → robots.php
```

---

## 🔍 Troubleshooting Guide

### "Database is locked" (Windows / Tests)
- Check for `static $db` in `functions/*.php` → replace with `new PDO()` per-call
- In test teardown: `$stmt->closeCursor(); $stmt = null;` BEFORE `$db = null;`

### "Translation key shows as raw string"
- Key missing from `admin/js/admin-i18n.js` or `js/translations.js`
- `adminT()` returns the key string on miss (truthy) — `|| 'fallback'` never triggers

### "Admin form saves HTML entities"
- Wrap form `.value` assignments with `decodeHtml()` in JS
- Example: `input.value = decodeHtml(data.name)`

### "Favorites slug lost after visiting credits.php"
- `credits.php` missing `const BASE_PATH = ...` before `common.js` loads
- Fix: add `<script>const BASE_PATH = <?= json_encode(get_base_path()); ?>;</script>`

### "ICS feed not updating on calendar apps"
- Check `Cache-Control: no-store, no-cache` header in `feed.php`
- Verify `invalidate_data_version_cache()` is called after admin writes

### "Telegram notifications wrong time or missing"
- Check `cron/send-telegram-notifications.php` uses `datetime()` BETWEEN comparison
- Verify `$eventTz` matches DB stored format (Bangkok = `Asia/Bangkok`)
- Check cron is running: `crontab -l | grep telegram`

### "Artist filter mismatch in export/image"
- `export.php` and `image.php` must use `program_artists` junction table
- Mirror `$useArtistsTable` logic from `index.php`

| ปัญหา | ไฟล์ที่ดู |
|------|----------|
| DB schema / columns | `setup.php`, `tools/migrate-*.php` |
| Cache not updating | `functions/cache.php`, `admin/api.php` invalidation calls |
| Admin form not saving | `admin/api.php` (action handler), `admin/index.php` (JS fetch) |
| Translation missing | `js/translations.js` + `admin/js/admin-i18n.js` |
| URL routing 404 | `.htaccess`, `nginx-clean-url.conf` |
| ICS feed issues | `functions/ics.php`, `feed.php`, `export.php` |
| Artist filter mismatch | `index.php` `$useArtistsTable` → mirror in `export.php` + `image.php` |
| Telegram not notifying | `cron/send-telegram-notifications.php` |

---

## 📊 Monitoring & Maintenance

### Weekly Checklist

```bash
# 1. Check disk space
df -h data/ cache/ uploads/

# 2. Verify backups
ls -lah backups/

# 3. Review error logs
tail -50 /var/log/apache2/error.log

# 4. Check stale cache
find cache/ -mtime +1 -ls

# 5. DB health
sqlite3 data/calendar.db "SELECT name, is_active, start_date, end_date FROM events ORDER BY start_date DESC LIMIT 10;"
```

### Monthly Checklist

```bash
# 1. Manual DB backup
cp data/calendar.db backups/calendar.db.$(date +%Y-%m-%d)

# 2. Clean old caches
find cache/ -name "*.json" -mtime +7 -delete
find cache/images/ -name "*.png" -mtime +7 -delete

# 3. Check orphaned records
sqlite3 data/calendar.db "SELECT p.id, p.title FROM programs p LEFT JOIN events e ON p.event_id = e.id WHERE e.id IS NULL;"

# 4. Check FTS5 sync
sqlite3 data/calendar.db "SELECT COUNT(*) FROM programs_fts;"

# 5. Audit admin users
sqlite3 data/calendar.db "SELECT id, username, role FROM admin_users;"

# 6. Rotate Telegram logs
php cron/rotate-telegram-logs.php
```

### Quarterly Checklist

```bash
# 1. Check PHP version (must be 8.1+)
php --version

# 2. Test disaster recovery
cp backups/calendar.db.latest /tmp/test.db
php -r "new PDO('sqlite:/tmp/test.db'); echo 'DB OK\n';"

# 3. Run full test suite
php tests/run-tests.php

# 4. Review GitHub Actions for failures
gh run list --limit 20
```

---

## 🎓 Learning Paths

### Backend Developer (PHP/SQL)
1. Read `SKILL.md` — Architecture, critical constraints (SQLite locking!)
2. Explore `admin/api.php` — Main CRUD logic, global `$db` pattern
3. Study `functions/helpers.php` + `functions/cache.php`
4. Read test files (`tests/*.php`) — See patterns
5. Build: Add a new admin action following the Admin API Pattern

### Frontend Developer (JS/CSS)
1. Read `SKILL.md` — URL routes, i18n rules
2. Explore `js/common.js` — Main JS, event delegation, calendar view
3. Study `js/translations.js` — 3-language i18n system
4. Review `styles/index.css` + `styles/common.css` — CSS variables, 12 themes
5. Test in mobile DevTools — Responsive design

### DevOps/SRE
1. Read `DOCKER.md` — Container setup
2. Understand `docker-compose.yml` — Service configuration
3. Review `cron/` — Telegram notifications, log rotation
4. Setup monitoring for `cache/logs/telegram-cron.log`
5. Test backup/restore workflow via Admin UI

### QA/Tester
1. Read `TESTING.md` — 129 manual test cases
2. Run automated tests: `php tests/run-tests.php`
3. Check all 3 languages (TH/EN/JA) on every UI change
4. Test on mobile (DevTools responsive mode)
5. Verify admin role vs agent role access differences

---

## Recent Release Notes

### v15.8.0 (2026-05-22)

**Timeline View — My Upcoming Programs**

เพิ่ม Gantt-style Timeline view ใน `/my/{slug}` คู่กับ List view เดิม โดย Y-axis = event ที่ติดตามแล้วมี program ในวันนั้น, X-axis = เวลา (auto-fit ตาม min/max hour); ผู้ใช้เห็น overlap ระหว่าง events ในวันเดียวกันได้ทันทีจาก bar alignment

- **Toggle List / Timeline** — segmented control ที่ส่วนหัวของ Upcoming Programs; `localStorage.fav_view_mode` จำค่า; restore เมื่อ reload
- **Per-day Gantt block** — แต่ละวันที่มี programs render เป็น block (`.fav-timeline-day`); sticky header row + scroll-x; time axis 50/60px (mobile/desktop); event column 140/180px; slot height 60/70px
- **Cross-event overlap visualization** — translucent red zone strip + date header badge (`🔴 มี event ทับซ้อน`) + bar layout แยก column
- **Algorithms** — `_favDetectWithinEventOverlaps()` (pair-wise stack) + `_favFindCrossEventOverlaps()` (sweep-line, zones with cross-slug guard)
- **Event color** — bars + headers ใช้ `.fav-ec-{0..5}` เดิม map จาก `EVENT_COLOR_MAP[event_slug]`
- **Click-to-detail** — bar click → `openDayModal(date)` เดิม
- **i18n re-render** — `changeLanguage()` IIFE patch เรียก `renderFavTimeline()` เมื่อ Timeline กำลังแสดง
- **PHP data shape** — `$calPrograms[$date][]` เพิ่ม `start_iso` + `end_iso` (raw `HH:MM`); ไม่มี DB schema migration
- **i18n keys** — `fav.view.list`, `fav.view.timeline`, `fav.timeline.overlapHint` × TH/EN/JA = 9 entries

**Files changed:**
- `my.php` — `$calPrograms` shape + CSS ~140 lines + HTML toggle wrapper + JS ~150 lines (8 functions)
- `js/translations.js` — 9 entries (3 keys × 3 langs)
- `service-worker.js` — `CACHE_VERSION` bump (auto via `sync-sw-version.php`)
- `config/app.php` — version bump to v15.8.0
- `SETUP.md`, `API.md`, `PROJECT-STRUCTURE.md`, `INSTALLATION.md`, `TESTING.md`, `SECURITY.md`, `ICS_FORMAT.md` — version refs (auto)
- `CHANGELOG.md`, `CLAUDE.md`, `README.md`, `WORKFLOW.md`, `SKILL.md` — release notes (manual)

### v15.7.0 (2026-05-21)

**PWA Offline Cache**

เพิ่ม `fetch` handler + caching strategies ใน `service-worker.js` (เดิมมีแค่ push + lifecycle) — ผู้ใช้ที่เปิด PWA ขณะสัญญาณไม่ดี/อยู่ในงาน สามารถ reload / reopen หน้า core ที่เคยเปิด online ได้แม้ offline; URL ที่ไม่เคย cache → แสดง `offline.html`

- **Cache strategy matrix**:
  - **Static assets** (CSS/JS/icons/fonts/images) → cache-first, versioned (`app-static-v{VER}`)
  - **Visited HTML pages** → network-first + 3 s timeout + cache fallback (`app-pages-v{VER}`)
  - **Public `api.php`** → stale-while-revalidate + `ETag` / `If-None-Match`; on `304` preserve cached body, refresh timestamp (`app-api-v{VER}`)
  - **Network-only bypass**: `api/favorites.php`, `api/push.php`, `api/request.php`, `api/event-request.php`, `api/telegram.php` (+ `/api/...` clean URLs), `feed.php`, `my-feed.php`, `/admin/`, `/setup.php`, `/tools/`, SW เอง, `sync-sw-version.php`
  - **Cross-origin** (AdSense, GA, CDN) → passthrough
- **Freshness controls** — `MAX_API_CACHE_AGE_MS = 7 days` (cache เก่ากว่า 7 วัน → ถือเป็น miss → serve `offline.html`); `NETWORK_TIMEOUT_MS = 3000` (HTML strategy fall back to cache เมื่อ network ช้า > 3s กัน captive Wi-Fi UX แย่); `X-SW-Cached-At` header ทุก `cache.put`
- **Activate cleanup** — `caches.keys()` filter ลบทุก `app-*` ที่ไม่ตรง `VALID_CACHES` ของ version ปัจจุบัน — APP_VERSION bump → cache เก่าลบทันที
- **`offline.html`** (new, root) — self-contained: inline CSS sakura gradient, inline JS 3 ภาษา (TH/EN/JA จาก `localStorage.lang` → `<html lang>` → `navigator.language`); ปุ่ม "🔄 ลองอีกครั้ง" → `location.reload()`; ปุ่ม "🏠 กลับหน้าแรก" derive base จาก `location.pathname` โดยตัด clean route prefixes — subdir safe
- **`sync-sw-version.php`** (new, root) — CLI-only sync script, idempotent, fail-loud; ไม่แตะ `tools/update-version.php` — workflow ใหม่ 2 ขั้น: `php tools/update-version.php X.Y.Z` แล้ว `php sync-sw-version.php`
- **`.htaccess`** — `<Files "sync-sw-version.php">` block (`Require all denied` + Apache 2.2 `Deny from all` fallback)
- **`PwaOfflineTest`** — 59 tests ใหม่ ครอบคลุม SW source checks, `offline.html`, sync script, `.htaccess`, regression guards (push/notificationclick/pushsubscriptionchange preserved, `tools/update-version.php` ไม่อ้าง service-worker.js)

**Files changed:**
- `service-worker.js`
- `offline.html` (new)
- `sync-sw-version.php` (new)
- `.htaccess`
- `tests/PwaOfflineTest.php` (new)
- `tests/run-tests.php`
- `config/app.php` — version bump to v15.7.0
- `CHANGELOG.md`, `CLAUDE.md`, `README.md`, `WORKFLOW.md`, `SKILL.md`, `SETUP.md`, `API.md`, `PROJECT-STRUCTURE.md`, `SECURITY.md`, `ICS_FORMAT.md` — version + docs updates

> ไม่มี DB migration; ไม่มี breaking changes; ผู้ใช้เดิมจะได้ SW ใหม่อัตโนมัติเมื่อ browser revalidate (cache-control: no-cache บน service-worker.js ที่ `.htaccess:82-85` บังคับ refetch ทุก load)

### v15.6.1 (2026-05-21)

**Bug Fix — Duplicate Telegram Notifications (Race Condition)**

แก้ race condition ระหว่าง Telegram cron กับ Web Push cron ที่ทำให้แจ้งเตือนซ้ำเมื่อทั้ง 2 cron ทำงานใน 6-min interval เดียวกัน

- **Root cause** — ทั้ง 2 cron อ่านไฟล์ favorites พร้อมกัน (LOCK_SH → release), ทำ HTTP send 1-2 วินาที (ไม่มี lock), แล้วเขียน `$favData` ทั้งก้อน (LOCK_EX) ทับ — `$favData` ใน memory ของแต่ละ cron ยังคงเป็น snapshot จากตอนอ่าน ไม่มี update ของ cron อีกตัว → ตัวที่เขียนทีหลังลบ update ของตัวที่เขียนก่อน ทำให้ cron รอบถัดไปไม่เจอ `telegram_notified[X]` → ส่งซ้ำ
- **Fix** — เปลี่ยน write-back ของทั้ง 2 cron จาก "เขียน `$favData` ทั้งก้อน" เป็น "อ่านไฟล์ล่าสุดอีกครั้ง under LOCK_EX แล้ว overwrite เฉพาะ key ของตัวเอง":
  - `cron/send-telegram-notifications.php` — overwrite `telegram_notified` + `telegram_summary_date`
  - `cron/send-web-push-notifications.php` — overwrite `push_subscriptions`
  - Fallback: ถ้า `json_decode()` คืน non-array ใช้ in-memory state เป็น fallback
- **Defense-in-depth** — pattern กันได้ทุก race ไม่เฉพาะ cron-vs-cron — รวมถึง web request ที่เขียน favorites ระหว่าง cron กำลังประมวลผล

**Files changed:**
- `cron/send-telegram-notifications.php`
- `cron/send-web-push-notifications.php`
- `config/app.php` — version bump to v15.6.1

> ไม่มี DB migration; ไม่ต้องเปลี่ยน cron schedule — fix อยู่ที่ระดับ code

### v15.6.0 (2026-05-20)

**Mobile UX — Favorites Pages Consolidation**

ลดความสูง above-the-fold บน `/my/{slug}` และ `/my-favorites/{slug}` ประมาณ 75–80% บนมือถือ; 3 banners ซ้อนกัน (QR Transfer + Telegram + Web Push) รวมเป็น action chip bar เดียว และ Followed Artists เปลี่ยนเป็น stats line + collapsible

- **Action Chip Bar** — `my.php` รวม "🔗 ย้าย Favorites / PWA" + "🔔 เชื่อมต่อ Telegram" + "🔔 Web Push Notifications" เป็น `.fav-actions-bar` เดียวมี 3 ปุ่ม `.fav-action-chip` พร้อม status dot สีเขียวเมื่อ active; QR Transfer → modal ใหม่ (`#qrTransferModal`); Telegram/Push → toggle ตาม `.is-active` class; ลบ CSS เก่า (`.fav-tg-banner`, `.fav-push-banner`, `.fav-transfer-section`, `.btn-transfer-toggle`)
- **`checkPushStatus()` refactor** — toggle `#pushChip.is-active` แทน update `#pushStatusText`/`#pushSubscribeBtn`/`#pushUnsubscribeBtn` ที่ลบไปแล้ว
- **`my-favorites.php` parity** — QR section เปลี่ยนเป็น modal; chip bar 1 chip; inlined `.req-modal*` CSS (เพราะหน้านี้ load แค่ `common.css` ส่วน modal styles อยู่ใน `artist.css`/`index.css`)
- **Followed Artists → stats + collapsible** — `<button class="fav-followed-toggle">` แสดง "👥 ติดตาม {N} ศิลปิน · {M} upcoming programs" + caret หมุน 180°; expand เห็น chip grid + `.fav-manage-link` ("→ จัดการทั้งหมด") ไป `/my-favorites/{slug}`; empty state disabled toggle + แสดง onboarding message
- **i18n keys ใหม่** (TH/EN/JA, 18 entries รวม): `actions.qrTransfer`, `actions.telegram`, `actions.push`, `transfer.modalTitle`, `fav.statsFollowing`, `fav.manageAll`

**Files changed:**
- `my.php`
- `my-favorites.php`
- `js/translations.js`
- `config/app.php`

> ไม่มี DB migration; ไม่มี breaking changes — restructure ฝั่ง template/client เท่านั้น

### v15.5.0 (2026-05-20)

**Security Audit Hardening — All Findings Closed**

Full remediation of the 2026-05-19 audit (`docs/SECURITY_AUDIT_2026.md` rev 1–6). 1 HIGH + 2 MEDIUM + 4 LOW findings all closed with regression tests; final score **9.8 / 10**.

- **HIGH-1 RESOLVED** — Removed `test-telegram-webhook.php` and `test-webhook-verify.php` (debug scripts that hardcoded a live Telegram `webhook_secret`); cleared the secret in `config/telegram-config.json` to retire the leaked value (`verify_telegram_request()` in `api/telegram.php:16` fail-closes on empty).
- **MEDIUM-1 RESOLVED** — Web Push endpoint SSRF closed: `webpush_validate_endpoint()` allow-lists FCM, Mozilla autopush, Apple Web Push, and WNS; rejects IP literals, http scheme, lookalike-suffix attacks, and bare suffix-as-host; runs at both subscribe (`api/push.php`) and send (`webpush_send()` returns `expired=true` to drop pre-allow-list records on next cron tick). New `WEBPUSH_ALLOW_LOCALHOST` flag (default off, also requires `PRODUCTION_MODE=false`) accepts `localhost / 127.0.0.1 / ::1 / host.docker.internal` for dev/test.
- **MEDIUM-2 RESOLVED** — `admin/login.php` POST handler now calls `verify_csrf_token($_POST['csrf_token'])` before rate-limit accounting so an attacker without a token cannot exhaust the per-IP budget; CSRF failures `audit_log()` with `error_code=csrf_invalid`; new `login.errCsrf` i18n key in TH/EN.
- **LOW-1 RESOLVED** — `getTelegramLog()` / `getWebPushLog()` / `getEmailLog()` now call `require_api_admin_role()`; all 8 log actions added to dispatcher-level `$adminOnlyActions`.
- **LOW-2 / LOW-3 RESOLVED** — `config/.htaccess` and `tools/.htaccess` switched to Apache 2.4 `Require all denied` with `mod_access_compat` fallback; all leaky `Allow from` lines (LAN CIDRs in `tools/`, inert `127.0.0.1` in `config/`) removed.
- **LOW-4 RESOLVED** — `.gitignore` extended to block `*.db`, `test-*.php`, `debug-*.php`.

Test suite: 9361 → **9809 (+448)**. `WebPushTest` 35 → 68 (+33). `AdminAuthTest` 38 → 46 (+8). `IntegrationTest` 108 → 121 (+13 cumulative including LOW-2/3/4 guards).

**Files changed:**
- `config/app.php`, `config/webpush.php`, `config/.htaccess`, `config/telegram-config.json`
- `functions/webpush.php`, `api/push.php`
- `admin/api.php`, `admin/login.php`, `admin/js/admin-i18n.js`
- `tools/.htaccess`, `.gitignore`
- `test-telegram-webhook.php` (deleted), `test-webhook-verify.php` (deleted)
- `tests/WebPushTest.php`, `tests/AdminAuthTest.php`, `tests/IntegrationTest.php`
- `docs/SECURITY_AUDIT_2026.md`

> **Migration:** no DB schema migration required.
> **Operational note:** with `webhook_secret` cleared, Telegram bot webhook handler rejects all incoming webhooks (fail-closed). To re-enable, generate a fresh secret via Admin › Settings › Telegram and re-register the webhook with Telegram.

### v15.4.0 (2026-05-19)

**Favorites Connect Page (PWA Slug Transfer — Part 2)**
- New `/connect` page (`connect.php`) with camera-based QR scanner via `jsQR` library; animated viewfinder; multi-CDN fallback (jsdelivr → unpkg → cdnjs) for iOS PWA reliability.
- Overrides `Permissions-Policy: camera=(self)` immediately after `send_security_headers()` to allow `getUserMedia` (global header is `camera=()` blocking camera everywhere else).
- `SLUG_RE` regex validates UUID v7 + HMAC-12 slug format from scanned QR data or pasted URL; successful scan saves `fav_slug` to localStorage then redirects to `/my/{slug}`.
- Paste-URL fallback input for devices where camera is unavailable or denied.
- `injectFavNavButton()` in `js/common.js` now injects 🔗 link to `/connect` when no slug exists; ⭐ + 📅 still appear when slug is set; skip on `/connect` and `/my*` pages.
- `how-to-use.php` Section 24 documents the two-device transfer flow.
- Header/Footer match homepage structure (site title h1, language switcher, nav links, footer-text with copyright + Powered-by + version).
- i18n: `connect.*` (19 keys), `transfer.*` (5 keys), `section24.*` (8 keys) in TH/EN/JA.

**Files changed:**
- `connect.php` (new)
- `.htaccess`
- `js/common.js`
- `js/translations.js`
- `how-to-use.php`
- `config/app.php`

> **Migration:** no DB schema migration required.

### v15.3.0 (2026-05-19)

**Favorites QR Code Display (PWA Slug Transfer — Part 1)**
- Collapsible "🔗 ย้าย Favorites ไปยังอุปกรณ์อื่น / PWA" section added below the fav-save-banner on `/my/{slug}` and `/my-favorites/{slug}`.
- Lazy-loads `qrcodejs` CDN on first toggle; QR encodes the `/my/{slug}` URL.
- Solves iOS PWA `localStorage` isolation: scanning the QR from the PWA's camera navigates to `/my/{slug}` inside the PWA's WebView, where the existing auto-save logic sets `fav_slug` in the PWA's isolated localStorage.
- No new endpoint or DB migration required — reuses existing auto-save chain.
- i18n: `transfer.*` keys (showQr, desc, step1–3) in TH/EN/JA.

**Files changed:**
- `my.php`
- `my-favorites.php`
- `js/translations.js`
- `config/app.php`

> **Migration:** no DB schema migration required.

### v15.2.0 (2026-05-19)

**Web Push & Email Log Rotation**
- New cron scripts `cron/rotate-webpush-logs.php` + `cron/rotate-email-logs.php`; daily-archive `cache/logs/{name}.log` to `{name}-YYYY-MM-DD.log`; 7-day retention; `-daily` collision guard for same-date size-rotated archives; CLI-only.
- Admin UI: Web Push and Email sub-tabs each show daily rotation cron entry; outputs to shared `rotate-cron.log`.
- Admin Help: Web Push and Email cron sections updated (TH + EN).

**Files changed:**
- `cron/rotate-webpush-logs.php` (new)
- `cron/rotate-email-logs.php` (new)
- `admin/index.php`
- `admin/js/admin-i18n.js`
- `admin/help.php`
- `admin/help-en.php`
- `config/app.php`

> **Migration:** no DB schema migration required; add new cron entries to crontab.

### v15.1.0 (2026-05-19)

**Admin Log Viewers + Icons Directory Fix**
- Web Push and Email sub-tabs in Admin Settings each include a log viewer: file selector dropdown (active + dated archives), filter input, scrollable Timestamp/Level/Message table with sticky header, and click-to-open detail modal showing full JSON context.
- `webpush_log_get` + `webpush_log_download` + `email_log_get` + `email_log_download` actions added to `admin/api.php`.
- Shared cron-log helpers `_cronLevelBadge()`, `openCronLogDetail()`, `_renderCronTable()` in `admin/index.php` reused by Telegram, Web Push, and Email log viewers.
- Renamed `icons/` → `icon/` directory because shared-hosting WAF blocked the `icons` name causing 404s on all PWA icon assets; updated 9 file references.

**Files changed:**
- `admin/api.php`
- `admin/index.php`
- `admin/js/admin-i18n.js`
- `manifest.json`
- `manifest.php`
- `service-worker.js`
- `cron/send-web-push-notifications.php`
- `tools/generate-pwa-icons.php`
- `tests/WebPushTest.php`
- `setup.php`
- `admin/help.php`
- `admin/help-en.php`
- `config/app.php`

> **Migration:** no DB schema migration required; run `php tools/generate-pwa-icons.php` to regenerate icons into `icon/` directory.

### v15.0.0 (2026-05-18)

**Web Push Notifications (PWA)**
- VAPID EC P-256 + RFC 8291 aes128gcm implementation using PHP 8.1+ built-ins; no Composer required.
- `manifest.json` + `service-worker.js` enable PWA installability; `api/push.php` subscription API (HMAC-slug auth, max subs per token, HTTP 410 cleanup).
- `cron/send-web-push-notifications.php` mirrors Telegram cron; respects opt-out + mute; logs to `cache/logs/webpush-cron.log`.
- Admin UI: Settings › 📱 Web Push sub-tab (9th sub-tab) with VAPID keygen, subject, notify-before minutes, cron instructions.
- All public pages updated with `<link rel="manifest">` + theme-color + Apple PWA meta tags.
- `WebPushTest` (45 tests) added; **9338 tests total** (24 suites).

**Files changed:** `functions/webpush.php` (new), `config/webpush.php` (new), `config/webpush-config.json` (new), `manifest.json` (new), `service-worker.js` (new), `api/push.php` (new), `cron/send-web-push-notifications.php` (new), `tools/generate-pwa-icons.php` (new), `icons/icon-72/192/512.png` (new), `tests/WebPushTest.php` (new), `config.php`, `admin/api.php`, `admin/index.php`, `admin/js/admin-i18n.js`, `admin/help.php`, `admin/help-en.php`, `my.php`, `js/common.js`, `js/translations.js`, all public pages, `.htaccess`, `setup.php`, `tests/run-tests.php`, `config/app.php`

> **Migration:** no DB schema migration required; run `php tools/generate-pwa-icons.php` once; configure VAPID keys via Admin UI.

### v14.0.0 (2026-05-18)

**Admin Audit Log (Phase 1 — File-based)**
- File-based audit log written to `cache/logs/admin-audit-YYYY-MM-DD.log` (JSON Lines); 30-day retention via `cron/rotate-admin-audit-logs.php`.
- Tracks: login/logout/2FA, all admin CRUD (programs, events, credits, artists, users, settings, backup, config), public Program/Event Request submissions and rate-limit blocks.
- Admin UI: Settings › 🔎 Audit Log sub-tab — parsed table (Timestamp / Action / Outcome / Actor / Entity / IP) with sticky header, 400 px scrollable container, filter input, and click-to-open detail modal.
- Telegram Activity Log viewer upgraded to same table+modal format.

**Files changed:** `functions/audit.php` (new), `config/admin.php`, `config.php`, `cron/rotate-admin-audit-logs.php` (new), `functions/admin.php`, `admin/login.php`, `admin/api.php`, `admin/index.php`, `admin/js/admin-i18n.js`, `api/request.php`, `api/event-request.php`, `setup.php`, docs, `config/app.php`

> **Migration:** no DB schema migration required; `cache/logs/` directory is auto-created on first write.

### v12.3.4 (2026-05-11)

**Request Email Admin Link Fix**
- Program/Event Request notification emails now point `Open Admin Requests tab` to `/admin/` instead of `/api/admin/`.
- Email admin URL generation normalizes `SCRIPT_NAME`, `PHP_SELF`, and `REQUEST_URI`, strips `/api` or `/admin`, and keeps subdirectory deployments intact.
- Added `EmailNotificationTest` coverage for the `/api/request.php?action=create` fallback and for HTML/plain-text email bodies.

**Files changed:** `functions/email.php`, `tests/EmailNotificationTest.php`, docs, `config/app.php`

> **Migration:** no DB schema migration required.

### v12.3.3 (2026-05-08)

**Role-Aware Admin Help**
- 📖 Thai and English Admin Help now hide sections that the current role cannot use.
- 🛡️ Role matrix updated for `admin`, `agent`, and scoped `organizer` users through v12.3.3.
- 📝 Requests and Artists Help now documents Organizer Active Requests and Artist Request review/approval behavior.

**Files changed:** `admin/help.php`, `admin/help-en.php`, docs, `config/app.php`

> **Migration:** no DB schema migration required.

### v12.3.2 (2026-05-08)

**Artist Request Approve Refresh**
- Admin/agent UI refreshes the Artist list after approving an Artist Request so the newly created artist is visible immediately.
- Approve success toast includes the created `artist_id`.
- Added `OrganizerRoleTest` regression coverage for approve inserting into `artists`, returning `artist_id`, and refreshing the Artist list.

**Files changed:** `admin/index.php`, `tests/OrganizerRoleTest.php`, docs, `config/app.php`

> **Migration:** no DB schema migration required beyond v12.3.0.

### v12.3.1 (2026-05-08)

**Organizer Artist Request Access Fix**
- Organizer users can enter the Artists tab and open `Request new artist` without triggering the admin-only `artists_list` API.
- `switchTab('artists')` now loads the full Artist database only for non-organizer roles.
- Added regression coverage in `OrganizerRoleTest` for the unconditional `loadArtists()` guard.

**Files changed:** `admin/index.php`, `tests/OrganizerRoleTest.php`, docs, `config/app.php`

> **Migration:** no DB schema migration required beyond v12.3.0.

### v12.3.0 (2026-05-08)

**Organizer Artist Requests**
- Organizer users can submit `Artists › Request new artist` using the existing Artist form in request mode.
- Admin/agent users review `Requests › Artist Request`; approve creates an `artists` record and reject updates request status only.
- Added `artist_requests` migration/setup support and dashboard request counts for Program, Event Guest, Event Active, and Artist groups.
- Organizer Dashboard now focuses on owned Events/Programs/Credits, Artist Request status, content health, and practical quick actions; admin review breakdown and artist totals are hidden.
- Dashboard long tables (`Upcoming Events`, `Programs by Event`) render full width instead of two columns.

**Files changed:** `admin/api.php`, `admin/index.php`, `admin/js/admin-i18n.js`, `setup.php`, `tools/migrate-add-artist-requests-table.php`, `tests/OrganizerRoleTest.php`, docs, `config/app.php`

> **Migration:** run `php tools/migrate-add-artist-requests-table.php` or `setup.php` → Run All Migrations for existing installs.

---

**Last Updated**: v15.8.0 (2026-05-22)

See also: [SKILL.md](SKILL.md), [CLAUDE.md](CLAUDE.md), [PROJECT-STRUCTURE.md](PROJECT-STRUCTURE.md)
